<?php
/**
 * Quiet students: who has gone silent, kept in one daily snapshot.
 *
 * A student is quiet when nothing has been seen of them for the set number of days (the last
 * sign-in, visit, call, report save or "we've already talked": `WPCPM_Student_Activity`), and they
 * have no call or group session booked ahead: a student with a call to come is in touch, whenever
 * they booked it. The daily job computes the list once and stores it; the Administrator
 * Dashboard's card reads that snapshot and never works out a row of its own, so the card costs one
 * option read however many students there are, and the card and the mail can never disagree.
 *
 * A switch, Off, Preview or On, keeps the line from acting before a person says so. Off computes
 * and stores nothing. Preview stores the snapshot, so Administrators see the card, and notes on
 * each row whether a reminder would go out, but mails nobody. On is the same, and is where the
 * mail is sent: one reminder to each student who has gone quiet, once for each quiet spell; one
 * digest a day to each mentor, listing the students just reminded; and, on Mondays, one list to each
 * Administrator who has quiet students. Only the daily job sends, under a lock, and a reminder is
 * stamped before it is sent, so a run that stops half way never mails anyone twice.
 *
 * A run reads the switch again from its row in the database before each write of the list and each
 * email (Release B): a cron request's own copy of the options does not see a save another request
 * made since it began, so without it an Off saved while a run is sending would let the run mail on
 * and put back the list Off deleted.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * The quiet-student snapshot, its switch and its daily job.
 */
final class WPCPM_Student_Flags {

	/**
	 * Settings values of `student_flags_mode`.
	 */
	const MODE_OFF     = 'off';
	const MODE_PREVIEW = 'preview';
	const MODE_ON      = 'on';

	/**
	 * The daily job's cron hook.
	 */
	const CRON_DAILY = 'wpcpm_student_flags_daily';

	/**
	 * Option: array( 'at' => Unix time of the run, 'rows' => list of rows ). Not autoloaded.
	 */
	const OPT_SNAPSHOT = 'wpcpm_student_flags';

	/**
	 * Option: Unix time of the latest move of the mode into On (from Off or Preview). A quiet stretch
	 * is mailed only when it reached the threshold at or after it (`reached_since_on()`), so neither
	 * the launch nor an On after a stretch Off mails the students who were already quiet that day,
	 * while a student who goes quiet after it is reminded when they reach the threshold.
	 */
	const OPT_ON_SINCE = 'wpcpm_student_flags_on_since';

	/**
	 * Option: the ISO week ('o-W') the weekly email last went out for.
	 */
	const OPT_WEEKLY = 'wpcpm_student_flags_weekly';

	/**
	 * User meta: array( 'for' => the spell's last-activity time, 'at' => Unix time the reminder went ).
	 */
	const META_REMINDED = 'wpcpm_student_reminded';

	/**
	 * The lock the daily run holds, and how long it holds it if the run never lets go.
	 */
	const LOCK     = 'wpcpm_student_flags_lock';
	const LOCK_TTL = 600;

	/**
	 * Quiet days used when the setting holds nothing usable.
	 */
	const DEFAULT_DAYS = 14;

	/**
	 * The admin-post action of "We've already talked", and the base of its nonce
	 * (`wpcpm_student_talked_<student id>`).
	 */
	const ACTION_TALKED = 'wpcpm_student_talked';

	/**
	 * The one-shot channel a press of "We've already talked" leaves its line on, for the page it lands
	 * on (`WPCPM_Flash`): array( 'student' => ID, 'name' => the student's name, 'at' => Unix time ).
	 */
	const FLASH_TALKED = 'student-talked';

	/**
	 * How long that line waits for its page, in seconds: a press lands on its page at once, so a line
	 * older than this is from a page that never took it, and says nothing any more.
	 */
	const SAID_FOR = 600;

	/**
	 * Hang the daily job on its hook, keep it on the clock, and follow the switch's saves.
	 */
	public static function init() {
		add_action( self::CRON_DAILY, array( __CLASS__, 'run_daily' ) );
		add_action( 'admin_post_' . self::ACTION_TALKED, array( __CLASS__, 'handle_talked' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
		add_action( 'update_option_' . WPCPM_Settings::OPT_NAME, array( __CLASS__, 'follow_switch' ), 10, 2 );
	}

	/**
	 * Put the daily job on the clock at the next 06:00 UTC, when it is not there.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_DAILY ) ) {
			wp_schedule_event( self::next_run( time() ), 'daily', self::CRON_DAILY );
		}
	}

	/**
	 * Take the daily job off the clock.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_DAILY );
	}

	/**
	 * The next 06:00 UTC after a time.
	 *
	 * @param int $now Unix time.
	 * @return int
	 */
	public static function next_run( $now ) {
		$today = (int) $now - ( (int) $now % DAY_IN_SECONDS ) + 6 * HOUR_IN_SECONDS;

		return $today > $now ? $today : $today + DAY_IN_SECONDS;
	}

	/**
	 * The switch: off, preview or on. Anything else reads as off.
	 *
	 * @return string
	 */
	public static function mode() {
		$mode = (string) WPCPM_Settings::get_value( 'student_flags_mode', self::MODE_OFF );

		return in_array( $mode, array( self::MODE_PREVIEW, self::MODE_ON ), true ) ? $mode : self::MODE_OFF;
	}

	/**
	 * The quiet days the setting holds, 7 to 60.
	 *
	 * @return int
	 */
	public static function threshold() {
		$days = (int) WPCPM_Settings::get_value( 'student_quiet_days', self::DEFAULT_DAYS );

		return max( 7, min( 60, $days > 0 ? $days : self::DEFAULT_DAYS ) );
	}

	/**
	 * Whether a student is one the line watches: a student account that is active and whose status
	 * is on a track. A paused or pending student (states on no track), a past status and an account
	 * with no program row are left out, since nobody expects activity from them.
	 *
	 * @param int $user_id The account.
	 * @return bool
	 */
	public static function is_followed( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 || ! WPCPM_Students_Dashboard::is_student( $user_id ) ) {
			return false;
		}

		if ( 1 !== (int) get_user_meta( $user_id, WPCPM_Students_Sync::META_ACTIVE, true ) ) {
			return false;
		}

		$status = WPCPM_Students_Sync::status_of( WPCPM_Students_Sync::get_program( $user_id ) );

		return '' !== $status && '' !== WPCPM_Program::track( $status );
	}

	/**
	 * The Administrator of a student: the one their institution is assigned to, while that account may
	 * manage the program.
	 *
	 * @param int $user_id The student's account.
	 * @return int User ID, or 0 when the chain breaks anywhere (no institution, nobody assigned to it,
	 *             an account that is gone or cannot manage the program).
	 */
	public static function manager_of( $user_id ) {
		$institution = trim( (string) get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_INSTITUTION, true ) );

		if ( '' === $institution ) {
			return 0;
		}

		$contact = WPCPM_Institution_Assignment::contact_of( $institution );

		return null !== $contact ? (int) $contact['user_id'] : 0;
	}

	/**
	 * The quiet students now, most quiet first. Computes; stores nothing.
	 *
	 * A followed student is quiet when their last activity (or the clock start, when later) is at
	 * least the threshold's days ago and they have no call or group session booked ahead.
	 *
	 * In Preview a row says whether a reminder would go out: every rule of the one-reminder-per-spell
	 * ruling holds but the "reached the threshold after the switch went On" one, which Preview does
	 * not read. In On the job stores false here; the sending decides for itself. A row says when its
	 * stretch was reminded by the rule the sending skips by (`reminded_this_stretch()`): a reminder is
	 * this stretch's when no activity came after it went, whatever stretch it was sent for.
	 *
	 * @param int $now Unix time; 0 for the current time.
	 * @return array[] Rows: user_id, record, name, days, last_at, last_kind, mentor_id, manager_id,
	 *                 test, reminded_at, would_remind, start_from (for a row of kind `start`, 'track'
	 *                 or 'account', the date its clock started from; '' for any other kind).
	 */
	public static function rows( $now = 0 ) {
		$now       = $now > 0 ? (int) $now : time();
		$threshold = self::threshold();
		$preview   = self::MODE_PREVIEW === self::mode();
		$out       = array();

		$ids = get_users(
			array(
				'role'   => WPCPM_Roles::ROLE_STUDENT,
				'fields' => 'ID',
			)
		);

		foreach ( (array) $ids as $entry ) {
			$user_id = WPCPM_Roles::id_of( $entry );

			if ( $user_id <= 0 || ! self::is_followed( $user_id ) ) {
				continue;
			}

			// One read of the activity per student: the days come from the same answer the row prints.
			$last = WPCPM_Student_Activity::last( $user_id, $now );
			$days = WPCPM_Student_Activity::days_since( $last['at'], $now );

			if ( $days < $threshold || self::has_call_ahead( $user_id, $now ) ) {
				continue;
			}

			$institution = (string) get_user_meta( $user_id, WPCPM_Students_Sync::META_INSTITUTION, true );
			$test        = '' !== $institution && WPCPM_Test_Institutions::is_test( $institution );
			$reminded    = self::reminded_this_stretch( self::reminded( $user_id ), (int) $last['at'] );
			$mentor      = self::mentor_of( $user_id );

			$out[] = array(
				'user_id'      => $user_id,
				'record'       => (string) get_user_meta( $user_id, WPCPM_Students_Sync::META_RECORD_ID, true ),
				'name'         => self::name_of( $user_id ),
				'days'         => $days,
				'last_at'      => (int) $last['at'],
				'last_kind'    => (string) $last['kind'],
				'mentor_id'    => $mentor ? (int) $mentor->ID : 0,
				'manager_id'   => self::manager_of( $user_id ),
				'test'         => $test,
				'reminded_at'  => $reminded,
				'would_remind' => $preview && ! $test && 0 === $reminded,
				'start_from'   => 'start' === (string) $last['kind'] ? WPCPM_Student_Activity::clock_start_from( $user_id ) : '',
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				if ( $a['days'] !== $b['days'] ) {
					return $b['days'] <=> $a['days'];
				}

				return $a['user_id'] <=> $b['user_id'];
			}
		);

		return $out;
	}

	/**
	 * The reminder a student last had, as numbers.
	 *
	 * @param int $user_id The student's account.
	 * @return array{for:int,at:int}
	 */
	public static function reminded( $user_id ) {
		$meta = get_user_meta( (int) $user_id, self::META_REMINDED, true );

		return array(
			'for' => ( is_array( $meta ) && isset( $meta['for'] ) ) ? (int) $meta['for'] : 0,
			'at'  => ( is_array( $meta ) && isset( $meta['at'] ) ) ? (int) $meta['at'] : 0,
		);
	}

	/**
	 * When the stretch a student is in was reminded: the reminder's sent time while no activity came
	 * after it, 0 when there is no reminder or the student was active after it went. One rule for the
	 * sending, the card's "Reminded <date>, no reply" and Preview's "Would remind" (Release B, from the
	 * release review): an activity the site learns of late, such as an Airtable edit, newer than the
	 * stretch the reminder was for but older than the reminder, opens no new stretch, and a last
	 * activity that moved back (a canceled call) is the same stretch.
	 *
	 * @param array{for:int,at:int} $reminded What `reminded()` gave.
	 * @param int                   $last_at  The student's last activity now.
	 * @return int Unix time the reminder went, or 0.
	 */
	public static function reminded_this_stretch( array $reminded, $last_at ) {
		$sent = max( isset( $reminded['for'] ) ? (int) $reminded['for'] : 0, isset( $reminded['at'] ) ? (int) $reminded['at'] : 0 );

		if ( $sent <= 0 || (int) $last_at > $sent ) {
			return 0;
		}

		return isset( $reminded['at'] ) && (int) $reminded['at'] > 0 ? (int) $reminded['at'] : $sent;
	}

	/**
	 * Whether a student has a call or group session booked that has not started yet. Such a student
	 * is not quiet, however long ago the booking was made: the booking horizon runs months ahead, and
	 * a reminder telling them to book a call would be wrong. A trashed call is not one.
	 *
	 * @param int $user_id The student's account.
	 * @param int $now     Unix time.
	 * @return bool
	 */
	public static function has_call_ahead( $user_id, $now ) {
		foreach ( (array) WPCPM_Mentor_Calls::for_student( (int) $user_id, true ) as $call ) {
			if ( ! is_object( $call ) || 'trash' === $call->post_status ) {
				continue;
			}

			$details = WPCPM_Mentor_Calls::details( $call );

			if ( isset( $details['start'] ) && (int) $details['start'] > (int) $now ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a quiet stretch reached the threshold at or after the latest move of the switch into
	 * On: only such a stretch is mailed. A stretch already past the threshold at that moment is
	 * listed and never mailed, so the launch, and an On after a stretch Off, write to nobody who was
	 * already quiet; a student last active shortly before it is reminded the day they reach it.
	 *
	 * @param int $last_at The stretch's last-activity time.
	 * @param int $since   The time the switch went On; 0 to read the stored time. With none stored,
	 *                     nothing is held back by it.
	 * @return bool
	 */
	public static function reached_since_on( $last_at, $since = 0 ) {
		$since = $since > 0 ? (int) $since : (int) get_option( self::OPT_ON_SINCE, 0 );

		return (int) $last_at + self::threshold() * DAY_IN_SECONDS >= $since;
	}

	/**
	 * The stored snapshot. With the switch off, no rows: a card never shows what was stored before
	 * somebody switched the line off (and saving Off deletes it, so it does not come back with the
	 * switch either).
	 *
	 * @return array{at:int,rows:array[]}
	 */
	public static function snapshot() {
		$stored = get_option( self::OPT_SNAPSHOT, array() );

		if ( self::MODE_OFF === self::mode() || ! is_array( $stored ) ) {
			return array(
				'at'   => 0,
				'rows' => array(),
			);
		}

		return array(
			'at'   => isset( $stored['at'] ) ? (int) $stored['at'] : 0,
			'rows' => ( isset( $stored['rows'] ) && is_array( $stored['rows'] ) ) ? array_values( $stored['rows'] ) : array(),
		);
	}

	/**
	 * The daily job: compute the quiet students and store them; then, with the switch On, mail them.
	 * Nothing at all while the switch is off, and no mail in Preview.
	 *
	 * The whole run holds a lock, so a slow cron and a manual run never work at once, and lets it go
	 * in `finally`. The snapshot is stored before any mail goes, and a second time, with the
	 * reminders' times, after. The switch is read from its row before each of the two writes and
	 * before each email (`fresh_switch()`, `still_on()`): an Off saved while the list is made stores
	 * nothing, and one saved while the mail goes stops the run at the next email, which is never
	 * sent, and leaves the list Off deleted unwritten.
	 *
	 * @param int $now Unix time; 0 for the current time.
	 */
	public static function run_daily( $now = 0 ) {
		if ( self::MODE_OFF === self::mode() ) {
			return;
		}

		$token = WPCPM_Lock::take( self::LOCK, self::LOCK_TTL );

		if ( '' === $token ) {
			return;
		}

		try {
			$now    = $now > 0 ? (int) $now : time();
			$rows   = self::rows( $now );
			$switch = self::fresh_switch();

			if ( self::MODE_OFF === $switch['mode'] ) {
				return;
			}

			update_option(
				self::OPT_SNAPSHOT,
				array(
					'at'   => $now,
					'rows' => $rows,
				),
				false
			);

			if ( self::MODE_ON !== $switch['mode'] ) {
				return;
			}

			$since = $switch['since'];

			if ( $since <= 0 ) {
				// On without the time it went On (set outside the settings screen): from now, so nobody who
				// was already quiet is mailed.
				$since = $now;
				update_option( self::OPT_ON_SINCE, $since, false );
			}

			$sent = self::remind( $rows, $now, $since );

			self::digest( $sent, $since );
			self::weekly( $rows, $now, $since );
			self::note_reminders( $sent, $now );
		} finally {
			WPCPM_Lock::release( self::LOCK, $token );
		}
	}

	/**
	 * Put the reminders' time on their rows of the stored list. The list is read again from its row
	 * first, and only the rows still in it are touched (Release B, from the release review): a press
	 * of "We've already talked" made in another request while the mail went took its row off, and
	 * this request's own copy of the list, made before, must not put it back. Nothing is written once
	 * the switch is Off, which deleted the list.
	 *
	 * @param array<int,array> $sent The rows `remind()` stamped, by student ID.
	 * @param int              $now  Unix time of the run.
	 */
	private static function note_reminders( array $sent, $now ) {
		if ( array() === $sent || self::MODE_OFF === self::fresh_switch()['mode'] ) {
			return;
		}

		$stored = self::fresh_option( self::OPT_SNAPSHOT );

		if ( ! is_array( $stored ) || ! isset( $stored['rows'] ) || ! is_array( $stored['rows'] ) ) {
			return;
		}

		foreach ( $stored['rows'] as $n => $row ) {
			if ( isset( $row['user_id'] ) && isset( $sent[ (int) $row['user_id'] ] ) ) {
				$stored['rows'][ $n ]['reminded_at'] = (int) $now;
			}
		}

		update_option( self::OPT_SNAPSHOT, $stored, false );
	}

	/**
	 * The switch and the time it went On as the database holds them now, past this request's copy of
	 * the options: a cron request loads the options once, when it starts, and a save another request
	 * makes after that is not in its copy. One read of the two rows.
	 *
	 * @phpstan-impure It reads the database each time: another request may have saved in between.
	 *
	 * @return array{mode:string,since:int}
	 */
	private static function fresh_switch() {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! isset( $wpdb->options ) ) {
			return array(
				'mode'  => self::mode(),
				'since' => (int) get_option( self::OPT_ON_SINCE, 0 ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The rows themselves, past this request's copy of the options: see above.
		$found = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ( %s, %s )", WPCPM_Settings::OPT_NAME, self::OPT_ON_SINCE ) );
		$rows  = array();

		foreach ( (array) $found as $row ) {
			if ( is_object( $row ) && isset( $row->option_name ) ) {
				$rows[ (string) $row->option_name ] = maybe_unserialize( $row->option_value );
			}
		}

		return array(
			'mode'  => self::mode_of( isset( $rows[ WPCPM_Settings::OPT_NAME ] ) ? $rows[ WPCPM_Settings::OPT_NAME ] : array() ),
			'since' => isset( $rows[ self::OPT_ON_SINCE ] ) ? (int) $rows[ self::OPT_ON_SINCE ] : 0,
		);
	}

	/**
	 * Whether the run may still send: the switch is On, and went On at the time the run started under.
	 * An Off saved since stops it, and so does an Off and On again, whose new time decides afresh who is
	 * mailed.
	 *
	 * @phpstan-impure It reads the database each time (`fresh_switch()`).
	 *
	 * @param int $since The time the switch went On when the run began.
	 * @return bool
	 */
	private static function still_on( $since ) {
		$switch = self::fresh_switch();

		return self::MODE_ON === $switch['mode'] && (int) $switch['since'] === (int) $since;
	}

	/**
	 * One option as the database holds it now, past this request's copy (see `fresh_switch()`).
	 *
	 * @phpstan-impure It reads the database each time.
	 *
	 * @param string $name The option.
	 * @return mixed The value, or null when there is no such row.
	 */
	private static function fresh_option( $name ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! isset( $wpdb->options ) ) {
			return get_option( $name, null );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The row itself, past this request's copy of the options: see fresh_switch().
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", (string) $name ) );

		return null === $raw ? null : maybe_unserialize( $raw );
	}

	/**
	 * Where a quiet student is sent to book: the calls section of their Student Report Card when they
	 * have a mentor, else the booking page of the Administrator their institution is assigned to, else the Student
	 * Report Card. Never an empty address: the site's front page stands in when no card is published.
	 *
	 * @param int $user_id The student's account.
	 * @return string
	 */
	public static function booking_link( $user_id ) {
		$card = (string) WPCPM_Students_Dashboard::page_url();

		if ( self::mentor_of( (int) $user_id ) ) {
			return '' !== $card ? $card . '#wpcpm-module-calls' : home_url( '/' );
		}

		$calendly = self::calendly_of( (int) $user_id );

		if ( '' !== $calendly ) {
			return $calendly;
		}

		return '' !== $card ? $card : home_url( '/' );
	}

	/**
	 * Whether a quiet student has somewhere to book a call: a mentor, whose calls section on the
	 * Student Report Card books with them, or the booking page of the Administrator their institution is assigned to.
	 * With neither, `booking_link()` falls back to the Student Report Card itself, whose calls section
	 * offers nothing to book, so the reminder and the note offer no booking (fix round 1 of Release B):
	 * the reminder names the program's contact address instead, and the note leaves out Book a call.
	 *
	 * @param int $user_id The student's account.
	 * @return bool
	 */
	public static function can_book( $user_id ) {
		return null !== self::mentor_of( (int) $user_id ) || '' !== self::calendly_of( (int) $user_id );
	}

	/**
	 * The booking page of the Administrator a student's institution is assigned to, or ''.
	 *
	 * @param int $user_id The student's account.
	 * @return string
	 */
	private static function calendly_of( $user_id ) {
		$institution = trim( (string) get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_INSTITUTION, true ) );
		$contact     = '' !== $institution ? WPCPM_Institution_Assignment::contact_of( $institution ) : null;

		return null !== $contact ? trim( (string) $contact['calendly'] ) : '';
	}

	/**
	 * A student's mentor, when it is somebody else. A student whose program row names their own
	 * mentor record (an account that is both) has no mentor here (Release B, the trial's F3): they are
	 * not told "Your mentor, <their own name>" and get no digest about themself.
	 *
	 * @param int $user_id The student's account.
	 * @return WP_User|null
	 */
	private static function mentor_of( $user_id ) {
		$mentor = WPCPM_Mentor_Calls::mentor_for_student( (int) $user_id );

		return ( $mentor && (int) $mentor->ID !== (int) $user_id ) ? $mentor : null;
	}

	/**
	 * A student's name as the program row holds it, else the account's.
	 *
	 * @param int $user_id The student's account.
	 * @return string
	 */
	private static function name_of( $user_id ) {
		$program = WPCPM_Students_Sync::get_program( (int) $user_id );
		$name    = ( is_array( $program ) && ! empty( $program['name'] ) ) ? trim( (string) $program['name'] ) : '';

		if ( '' === $name ) {
			$user = get_userdata( (int) $user_id );
			$name = $user ? (string) $user->display_name : '';
		}

		return $name;
	}

	/**
	 * The snapshot's row of one student, for the notice on their Report Cards, checked again for that
	 * one student at the moment it is read (Release B, from the release review): a student who saved
	 * their report, was edited in Airtable, had a call, booked one ahead or was marked "we've already
	 * talked" since the morning's list sees no notice, and their mentor no line. Only while the switch
	 * is On, and only while the student is still followed.
	 *
	 * On the student's own card the sign-in and the visit made since the list are left out of that
	 * check: they are this return itself, the way the reminder sends the student to the notice and its
	 * button, and counting them would take the notice away the moment the student arrived. Read by
	 * anyone else, they count, so a mentor or an Administrator is never told a student who came back
	 * today has not been seen.
	 *
	 * @param int  $user_id The student's account.
	 * @param bool $own     Whether the student is reading their own card.
	 * @param int  $now     Unix time; 0 for the current time.
	 * @return array|null The row, its days and last activity as they are now for anyone but the
	 *                    student, or null.
	 */
	public static function notice_row( $user_id, $own = false, $now = 0 ) {
		$user_id = (int) $user_id;

		if ( self::MODE_ON !== self::mode() || ! self::is_followed( $user_id ) ) {
			return null;
		}

		$snapshot = self::snapshot();
		$row      = null;

		foreach ( $snapshot['rows'] as $stored ) {
			if ( isset( $stored['user_id'] ) && (int) $stored['user_id'] === $user_id ) {
				$row = $stored;
				break;
			}
		}

		$now = $now > 0 ? (int) $now : time();

		if ( null === $row || self::has_call_ahead( $user_id, $now ) ) {
			return null;
		}

		$times = WPCPM_Student_Activity::times( $user_id, $now );

		if ( $own ) {
			foreach ( array( 'sign_in', 'visit' ) as $kind ) {
				if ( $times[ $kind ] > (int) $snapshot['at'] ) {
					$times[ $kind ] = 0;
				}
			}
		}

		$last = WPCPM_Student_Activity::last_of( $user_id, $times );

		// The student's own view keeps the list's stretch: a sign-in left out above may have been the
		// one the stretch began with, and the days the list counted are never more than the truth.
		if ( $own ) {
			return WPCPM_Student_Activity::days_since( max( (int) $last['at'], isset( $row['last_at'] ) ? (int) $row['last_at'] : 0 ), $now ) < self::threshold() ? null : $row;
		}

		$days = WPCPM_Student_Activity::days_since( $last['at'], $now );

		if ( $days < self::threshold() ) {
			return null;
		}

		$row['days']      = $days;
		$row['last_at']   = (int) $last['at'];
		$row['last_kind'] = (string) $last['kind'];

		return $row;
	}

	/**
	 * Send each quiet student one reminder for the spell they are in.
	 *
	 * A student is mailed only when everything holds at the moment of sending, read again here and not
	 * taken from the rows: the student is still followed (a graduate or a paused student since the
	 * snapshot is skipped), their last activity is still the spell's (a student who signed in since is
	 * no longer quiet), the days are still at the threshold, no call or group session is booked ahead,
	 * the institution is not a test one, the stretch reached the threshold at or after the latest move
	 * into On (`reached_since_on()`), and this stretch has had no reminder (`reminded_this_stretch()`).
	 * The switch is read again before each one, and an Off stops the run there. The reminder is
	 * stamped before it is sent. Only the daily job sends, so the senders are the class's own.
	 *
	 * @param array[] $rows  The snapshot's rows.
	 * @param int     $now   Unix time.
	 * @param int     $since The time the switch went On, which the run sends under.
	 * @return array<int,array> The rows that were stamped, by student ID, each with `mailed`: whether the
	 *                          mail layer took the reminder.
	 */
	private static function remind( array $rows, $now, $since ) {
		$sent = array();

		foreach ( $rows as $row ) {
			$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;
			$last_at = isset( $row['last_at'] ) ? (int) $row['last_at'] : 0;

			if ( $user_id <= 0 || ! self::reached_since_on( $last_at, $since ) ) {
				continue;
			}

			// What follows is read again from the database: making the list loaded every student's meta
			// and calls into this request's copies, which do not see what another request saved since,
			// a press of "We've already talked", a sign-in or a status change (fix round 1).
			self::forget_cached( $user_id );

			if ( ! self::is_followed( $user_id ) ) {
				continue;
			}

			$institution = (string) get_user_meta( $user_id, WPCPM_Students_Sync::META_INSTITUTION, true );

			if ( '' !== $institution && WPCPM_Test_Institutions::is_test( $institution ) ) {
				continue;
			}

			$last = WPCPM_Student_Activity::last( $user_id, $now );
			$days = WPCPM_Student_Activity::days_since( $last['at'], $now );

			if ( (int) $last['at'] !== $last_at || $days < self::threshold() || self::has_call_ahead( $user_id, $now ) ) {
				continue;
			}

			// A new spell needs activity after the last reminder went: an activity learned late, older
			// than the reminder, and a last activity that moved back (a canceled call stops counting) are
			// the same quiet stretch.
			if ( self::reminded_this_stretch( self::reminded( $user_id ), $last_at ) > 0 ) {
				continue;
			}

			// The switch as saved now, before the stamp: an Off saved since stops the run here, with
			// nobody stamped who was not mailed.
			if ( ! self::still_on( $since ) ) {
				break;
			}

			// Before the mail, so a run that stops half way never sends this student a second one.
			update_user_meta(
				$user_id,
				self::META_REMINDED,
				array(
					'for' => $last_at,
					'at'  => (int) $now,
				)
			);

			$mentor = self::mentor_of( $user_id );
			$values = self::reminder_values( $user_id, $last, $days, $mentor, isset( $row['manager_id'] ) ? (int) $row['manager_id'] : 0 );

			$mailed = (bool) WPCPM_Mail::send(
				$user_id,
				'student-quiet-student',
				static function () use ( $values ) {
					return WPCPM_Mail_Templates::mail(
						'student-quiet-student',
						array_merge( array( 'site_name' => WPCPM_Mail::site_name() ), $values )
					);
				}
			);

			$row['days']      = $days;
			$row['mentor_id'] = $mentor ? (int) $mentor->ID : 0;
			$row['mailed']    = $mailed;
			$sent[ $user_id ] = $row;
		}

		return $sent;
	}

	/**
	 * Drop this request's copies of what the send-time check reads about one student, so it reads the
	 * database: the account's user meta (its sign-in, visit, report and talked stamps, its active flag,
	 * its program row and institution), and the cached call queries, by moving the posts' last-changed
	 * time, so a call booked meanwhile is found. A cron request keeps both from the moment the list was
	 * made, and another request's save is not in them.
	 *
	 * @param int $user_id The student's account.
	 */
	private static function forget_cached( $user_id ) {
		wp_cache_delete( (int) $user_id, 'user_meta' );
		wp_cache_set_posts_last_changed();
	}

	/**
	 * The values of one student's reminder. The opening says how long it has been in one of three
	 * sentences, each with its own count, so a student never seen is not told "since we last saw you":
	 * since the track started, or since the account was made, whichever the clock started from. The
	 * mentor's sentence and the Administrator's go only for a student who has one. The booking
	 * sentence goes only for a student who can book (`can_book()`); for one who cannot, a sentence
	 * naming the program's contact address takes its place, while the setting holds one.
	 *
	 * @param int          $user_id    The student's account.
	 * @param array        $last       What `WPCPM_Student_Activity::last()` gave.
	 * @param int          $days       Days quiet.
	 * @param WP_User|null $mentor     The student's mentor, or null.
	 * @param int          $manager_id The Administrator the student's institution is assigned to, or 0.
	 * @return array<string,string>
	 */
	private static function reminder_values( $user_id, array $last, $days, $mentor, $manager_id ) {
		$from    = 'start' === (string) $last['kind'] ? WPCPM_Student_Activity::clock_start_from( $user_id ) : '';
		$manager = $manager_id > 0 ? get_userdata( $manager_id ) : false;
		$book    = self::can_book( $user_id );

		return array(
			'days'               => (string) $days,
			'days_since_seen'    => '' === $from ? (string) $days : '',
			'days_since_track'   => 'track' === $from ? (string) $days : '',
			'days_since_account' => 'account' === $from ? (string) $days : '',
			'mentor_name'        => $mentor ? trim( (string) $mentor->display_name ) : '',
			'administrator_name' => $manager ? trim( (string) $manager->display_name ) : '',
			'booking_link'       => $book ? self::booking_link( $user_id ) : '',
			'contact_address'    => $book ? '' : WPCPM_Mail_Templates::program_contact(),
			'dashboard_link'     => self::or_home( (string) WPCPM_Students_Dashboard::page_url() ),
		);
	}

	/**
	 * One email to each mentor, listing their students who were reminded in this run, each a line that
	 * links to the student on the Mentor Report Card. A student with no mentor, or whose mentor has no
	 * account, is reminded alone, and a student whose reminder the mail layer did not take is not
	 * listed. The lines are made while the email is built, in the mentor's language. The switch is read
	 * again before each email.
	 *
	 * @param array[] $sent  The rows `remind()` stamped.
	 * @param int     $since The time the switch went On, which the run sends under.
	 */
	private static function digest( array $sent, $since ) {
		$by_mentor = array();

		foreach ( $sent as $row ) {
			if ( ! empty( $row['mailed'] ) && ! empty( $row['mentor_id'] ) ) {
				$by_mentor[ (int) $row['mentor_id'] ][] = $row;
			}
		}

		if ( array() === $by_mentor ) {
			return;
		}

		$card = (string) WPCPM_Mentors_Dashboard::page_url();

		foreach ( $by_mentor as $mentor_id => $list ) {
			if ( ! self::still_on( $since ) ) {
				return;
			}

			WPCPM_Mail::send(
				$mentor_id,
				'student-quiet-mentor',
				static function () use ( $list, $card ) {
					$lines = array();

					foreach ( $list as $row ) {
						$url = '';

						if ( '' !== $card && '' !== (string) $row['record'] ) {
							$url = add_query_arg( 'wpcpm_student', $row['record'], $card ) . '#' . WPCPM_Mentor_Notes::anchor( $row['record'] );
						}

						$lines[] = self::list_line( (string) $row['name'], (int) $row['days'], $url );
					}

					return WPCPM_Mail_Templates::mail(
						'student-quiet-mentor',
						array(
							'site_name'      => WPCPM_Mail::site_name(),
							'count'          => (string) count( $list ),
							'students_list'  => implode( "\n", $lines ),
							'dashboard_link' => self::or_home( $card ),
						)
					);
				}
			);
		}
	}

	/**
	 * The Monday list: on the first run of a Monday (site time), once for each ISO week, one email to
	 * each Administrator who has real quiet students, counting and listing those students. A test
	 * institution's student is neither counted nor listed, and an Administrator with only test
	 * students gets nothing. The week is noted before any mail goes. The lines are made while each
	 * email is built, in that Administrator's language, and the switch is read again before each.
	 *
	 * @param array[] $rows  The snapshot's rows.
	 * @param int     $now   Unix time.
	 * @param int     $since The time the switch went On, which the run sends under.
	 */
	private static function weekly( array $rows, $now, $since ) {
		if ( '1' !== (string) wp_date( 'N', (int) $now ) ) {
			return;
		}

		$week = (string) wp_date( 'o-W', (int) $now );

		if ( (string) get_option( self::OPT_WEEKLY, '' ) === $week || ! self::still_on( $since ) ) {
			return;
		}

		update_option( self::OPT_WEEKLY, $week, false );

		$by_manager = array();

		foreach ( $rows as $row ) {
			if ( empty( $row['test'] ) && ! empty( $row['manager_id'] ) ) {
				$by_manager[ (int) $row['manager_id'] ][] = $row;
			}
		}

		$students = (string) WPCPM_Students_Dashboard::page_url();
		$admin    = (string) WPCPM_Administrators_Dashboard::page_url();

		foreach ( $by_manager as $manager_id => $list ) {
			if ( ! self::still_on( $since ) ) {
				return;
			}

			WPCPM_Mail::send(
				$manager_id,
				'student-quiet-weekly',
				static function () use ( $list, $students, $admin ) {
					$lines = array();

					foreach ( $list as $row ) {
						$url     = '' !== $students ? add_query_arg( 'wpcpm_student_view', (int) $row['user_id'], $students ) : '';
						$lines[] = self::list_line( (string) $row['name'], (int) $row['days'], $url );
					}

					return WPCPM_Mail_Templates::mail(
						'student-quiet-weekly',
						array(
							'site_name'     => WPCPM_Mail::site_name(),
							'count'         => (string) count( $list ),
							'students_list' => implode( "\n", $lines ),
							'card_link'     => '' !== $admin ? WPCPM_Administrators_Dashboard::card_url( 'quiet-students' ) : home_url( '/' ),
						)
					);
				}
			);
		}
	}

	/**
	 * One student's line in a list: the name, how long, and the link when there is one.
	 *
	 * @param string $name The student's name.
	 * @param int    $days Days quiet.
	 * @param string $url  The link, or ''.
	 * @return string
	 */
	private static function list_line( $name, $days, $url ) {
		$line = sprintf( /* translators: 1: a student's name, 2: a number of days. */ _n( '%1$s, %2$d day', '%1$s, %2$d days', (int) $days, 'wpcredits-program-manager' ), '' !== trim( $name ) ? trim( $name ) : __( 'A student', 'wpcredits-program-manager' ), (int) $days );

		return '' !== $url ? $line . ': ' . $url : $line;
	}

	/**
	 * An address, or the site's front page when there is none.
	 *
	 * @param string $url An address or ''.
	 * @return string
	 */
	private static function or_home( $url ) {
		return '' !== $url ? $url : home_url( '/' );
	}

	/**
	 * Drop a student's row from the snapshot at once ("we've already talked"), without waiting for
	 * tomorrow's run.
	 *
	 * @param int $user_id The student's account.
	 */
	public static function forget_student( $user_id ) {
		$stored = get_option( self::OPT_SNAPSHOT, array() );

		if ( ! is_array( $stored ) || empty( $stored['rows'] ) || ! is_array( $stored['rows'] ) ) {
			return;
		}

		$kept = array();

		foreach ( $stored['rows'] as $row ) {
			if ( ! isset( $row['user_id'] ) || (int) $row['user_id'] !== (int) $user_id ) {
				$kept[] = $row;
			}
		}

		if ( count( $kept ) === count( $stored['rows'] ) ) {
			return;
		}

		$stored['rows'] = $kept;
		update_option( self::OPT_SNAPSHOT, $stored, false );
	}

	/**
	 * Whether the current person may say that a student has already talked with the program: the
	 * student themself, their mentor, or an Administrator.
	 *
	 * @param int $student_id The student's account.
	 * @return bool
	 */
	public static function may_say_talked( $student_id ) {
		$student_id = (int) $student_id;
		$me         = (int) get_current_user_id();

		if ( $student_id <= 0 || $me <= 0 || ! WPCPM_Students_Dashboard::is_student( $student_id ) ) {
			return false;
		}

		if ( $me === $student_id || current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return true;
		}

		$mentor = WPCPM_Mentor_Calls::mentor_for_student( $student_id );

		return $mentor && (int) $mentor->ID === $me;
	}

	/**
	 * Whether the current person may press "We've already talked" for the students one mentor's page
	 * lists: the mentor on their own page, or an Administrator on any. One answer for the whole Mentor
	 * Report Card, which lists the mentor's own students, so the page asks once rather than looking up
	 * each quiet student's mentor (Release B). The press itself is checked student by student, as
	 * every press is (`handle_talked()`).
	 *
	 * @param int $mentor_id Whose page it is.
	 * @return bool
	 */
	public static function may_say_talked_as_mentor( $mentor_id ) {
		$me = (int) get_current_user_id();

		return $me > 0 && ( $me === (int) $mentor_id || current_user_can( WPCPM_Roles::CAP_MANAGE ) );
	}

	/**
	 * "We've already talked": record it as activity, so the student is no longer quiet, take the row
	 * off the card at once, leave a line saying so for the page the press lands on, and go back to the
	 * page the press came from. Allowed for the student, their mentor and Administrators; a stale nonce
	 * stops on WordPress's own screen.
	 */
	public static function handle_talked() {
		$student_id = WPCPM_Request::posted_id( 'student' );

		check_admin_referer( self::ACTION_TALKED . '_' . $student_id );

		if ( ! self::may_say_talked( $student_id ) ) {
			wp_die( esc_html__( 'You cannot say that for this student.', 'wpcredits-program-manager' ), 403 );
		}

		update_user_meta(
			$student_id,
			WPCPM_Student_Activity::META_TALKED,
			array(
				'at' => time(),
				'by' => (int) get_current_user_id(),
			)
		);
		self::forget_student( $student_id );

		WPCPM_Flash::set(
			self::FLASH_TALKED,
			array(
				'student' => $student_id,
				'name'    => self::name_of( $student_id ),
				'at'      => time(),
			)
		);

		$back = WPCPM_Request::back( (string) WPCPM_Students_Dashboard::page_url(), home_url( '/' ) );

		// The Administrator Dashboard's card posts where it should land, which the allowlist maps.
		if ( WPCPM_Return::DASHBOARD === WPCPM_Request::posted_key( WPCPM_Return::FIELD ) ) {
			$back = WPCPM_Return::url( $back );
		}

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * The press this person just made, when it is recent: what `handle_talked()` left for the page it
	 * landed on. Read through the one-shot store, which keeps it for the rest of the request and clears
	 * it for the next.
	 *
	 * @return array{student:int,name:string}|null
	 */
	public static function said() {
		$said = class_exists( 'WPCPM_Flash' ) ? WPCPM_Flash::take( self::FLASH_TALKED ) : '';

		if ( ! is_array( $said ) || empty( $said['student'] ) || ! isset( $said['at'] ) || (int) $said['at'] < time() - self::SAID_FOR ) {
			return null;
		}

		return array(
			'student' => (int) $said['student'],
			'name'    => isset( $said['name'] ) ? (string) $said['name'] : '',
		);
	}

	/**
	 * Whether the press this person just made was for this student.
	 *
	 * @param int $student_id The student's account.
	 * @return bool
	 */
	public static function said_for( $student_id ) {
		$said = self::said();

		return null !== $said && (int) $student_id > 0 && $said['student'] === (int) $student_id;
	}

	/**
	 * The line a press leaves: to the student who pressed it on their own card, thanks; to anybody
	 * else, that the student is off the list until they go quiet again.
	 *
	 * @param string $name The student's name.
	 * @param bool   $own  Whether the student is the reader.
	 * @return string
	 */
	public static function said_sentence( $name, $own ) {
		if ( $own ) {
			return __( 'Noted, thank you for letting us know.', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: %s: a student's name. */
			__( 'Noted. %s is off the list until they go quiet again.', 'wpcredits-program-manager' ),
			'' !== trim( (string) $name ) ? trim( (string) $name ) : __( 'The student', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The press's line on the Student Report Card or the Mentor Report Card, where the notice was, as
	 * every dashboard's outcome is drawn: toned, heard and moved to when the page loads.
	 *
	 * @param string $text The sentence.
	 * @return string Escaped markup.
	 */
	private static function said_markup( $text ) {
		$message = class_exists( 'WPCPM_Dashboard_Card' )
			? WPCPM_Dashboard_Card::message( 'success', $text, 'wpcpm-notes__message' )
			: '<p class="wpcpm-notes__message is-success" role="status">' . esc_html( $text ) . '</p>';

		return '<div class="wpcpm-quiet__notice">' . $message . '</div>';
	}

	/**
	 * The "We've already talked" button, as a form of its own, for a Report Card or a dashboard card.
	 *
	 * The nonce goes without the id core's field gives it, the same `_wpnonce` on every form of a
	 * page, which a Mentor Report Card with several quiet students would repeat (Release B, the
	 * trial's F5); the way back to the page the press came from stays.
	 *
	 * @param int    $student_id The student's account.
	 * @param string $label      The button's words; '' for "We've already talked".
	 * @param string $card       A card key of `WPCPM_Return::ANCHORS` when the press is made on the
	 *                           Administrator Dashboard, so it lands back on that card; '' elsewhere,
	 *                           where the press returns to the page it was made on.
	 * @return string Escaped form markup.
	 */
	public static function talked_form( $student_id, $label = '', $card = '' ) {
		$student_id = (int) $student_id;
		$label      = '' !== trim( (string) $label ) ? (string) $label : __( 'We\'ve already talked', 'wpcredits-program-manager' );
		$user       = get_userdata( $student_id );

		ob_start();

		if ( '' !== (string) $card ) {
			WPCPM_Return::field( WPCPM_Return::DASHBOARD, (string) $card );
		}

		$fields = (string) ob_get_clean();

		return sprintf(
			'<form class="wpcpm-quiet__talked" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s"><input type="hidden" name="_wpnonce" value="%3$s" />%4$s%5$s<input type="hidden" name="action" value="%6$s" /><input type="hidden" name="student" value="%7$d" /><button type="submit" class="wpcpm-button wpcpm-button--secondary">%8$s%9$s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' ),
			esc_attr( wp_create_nonce( self::ACTION_TALKED . '_' . $student_id ) ),
			wp_referer_field( false ),
			$fields,
			esc_attr( self::ACTION_TALKED ),
			$student_id,
			esc_html( $label ),
			$user ? ' <span class="screen-reader-text">' . esc_html( sprintf( /* translators: %s: a student's name. */ __( 'for %s', 'wpcredits-program-manager' ), (string) $user->display_name ) ) . '</span>' : ''
		);
	}

	/**
	 * The opening of the one form a dashboard card draws for all its "We've already talked" buttons
	 * (Release B, the trial's F5): the action and the way back to the card, once. The card closes it
	 * with `</form>` after its table, and each row holds a `talked_button()`.
	 *
	 * @param string $card A card key of `WPCPM_Return::ANCHORS`, the card the press lands back on.
	 * @return string Escaped markup.
	 */
	public static function talked_card_form( $card ) {
		ob_start();
		WPCPM_Return::field( WPCPM_Return::DASHBOARD, (string) $card );
		$fields = (string) ob_get_clean();

		return sprintf(
			'<form class="wpcpm-quiet__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s"><input type="hidden" name="action" value="%3$s" />%4$s',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' ),
			esc_attr( self::ACTION_TALKED ),
			$fields
		);
	}

	/**
	 * One row's "We've already talked" button inside a card's one form (`talked_card_form()`): the
	 * student in its value, and the student's own nonce in the address it posts to, so the press is
	 * checked against that student as every press is, with no field of its own and no id to repeat.
	 *
	 * @param int    $student_id The student's account.
	 * @param string $name       The student's name, for the words a screen reader hears after the label.
	 * @return string Escaped markup.
	 */
	public static function talked_button( $student_id, $name = '' ) {
		$student_id = (int) $student_id;

		return sprintf(
			'<button type="submit" class="wpcpm-button wpcpm-button--secondary" name="student" value="%1$d" formaction="%2$s">%3$s%4$s</button>',
			$student_id,
			esc_url( add_query_arg( '_wpnonce', wp_create_nonce( self::ACTION_TALKED . '_' . $student_id ), admin_url( 'admin-post.php' ) ) ),
			esc_html__( 'We\'ve already talked', 'wpcredits-program-manager' ),
			'' !== trim( (string) $name ) ? ' <span class="screen-reader-text">' . esc_html( sprintf( /* translators: %s: a student's name. */ __( 'for %s', 'wpcredits-program-manager' ), trim( (string) $name ) ) ) . '</span>' : ''
		);
	}

	/**
	 * The one paragraph on the Student Report Card for a student who has gone quiet: how long it has
	 * been and, for the student, where to book, with the button "We've already talked" for whoever may
	 * press it. Right after a press for this student, the line saying it was noted instead. Empty for a
	 * student who is not in the snapshot or is active again (`notice_row()`), and while the switch is
	 * not On.
	 *
	 * The student reads how long since they were last seen, or, never seen, since their track started
	 * or their account was made; the booking link is theirs alone, so an Administrator viewing the card
	 * as the student reads the notice without it (Release B), and it is offered only where there is
	 * something to book (`can_book()`).
	 *
	 * @param WP_User $student The student whose card it is.
	 * @param int     $viewer  The reader's account ID.
	 * @param int     $now     Unix time; 0 for the current time.
	 * @return string Escaped markup, or ''.
	 */
	public static function student_notice( WP_User $student, $viewer, $now = 0 ) {
		$own  = (int) $viewer === (int) $student->ID;
		$said = self::said();

		if ( null !== $said && $said['student'] === (int) $student->ID ) {
			return self::said_markup( self::said_sentence( $said['name'], $own ) );
		}

		$row = self::notice_row( (int) $student->ID, $own, $now );

		if ( null === $row ) {
			return '';
		}

		$days = (int) $row['days'];

		if ( $own ) {
			$kind = isset( $row['last_kind'] ) ? (string) $row['last_kind'] : '';
			$text = self::own_sentence( (int) $student->ID, $kind, $days );
			$book = '';

			// Book a call and its lead-in only where there is something to book (`can_book()`).
			if ( self::can_book( (int) $student->ID ) ) {
				$lead = 'start' === $kind ? __( 'A short call is the easiest way to get going.', 'wpcredits-program-manager' ) : __( 'A short call is the easiest way to get going again.', 'wpcredits-program-manager' );
				$book = ' ' . esc_html( $lead ) . ' <a href="' . esc_url( self::booking_link( (int) $student->ID ) ) . '">' . esc_html__( 'Book a call', 'wpcredits-program-manager' ) . '</a>';
			}

			$out = '<div class="wpcpm-quiet__notice"><p>' . esc_html( $text ) . $book . '</p>'; // The booking part is escaped above.
		} else {
			$text = sprintf(
				/* translators: 1: a student's name, 2: a number of days. */
				_n( '%1$s has not been seen here for %2$d day.', '%1$s has not been seen here for %2$d days.', $days, 'wpcredits-program-manager' ),
				(string) $student->display_name,
				$days
			);
			$out = '<div class="wpcpm-quiet__notice"><p>' . esc_html( $text ) . '</p>';
		}

		if ( self::may_say_talked( (int) $student->ID ) ) {
			$out .= self::talked_form( (int) $student->ID );
		}

		return $out . '</div>';
	}

	/**
	 * What the student reads on their own card: how long since they were last seen, or, for a student
	 * never seen, since their track started or their account was made, never "since we last saw you".
	 * The booking lead-in is the caller's, for a student who can book.
	 *
	 * @param int    $student_id The student's account.
	 * @param string $kind       The row's last-activity kind.
	 * @param int    $days       Days quiet.
	 * @return string
	 */
	private static function own_sentence( $student_id, $kind, $days ) {
		if ( 'start' !== $kind ) {
			return sprintf(
				/* translators: %d: a number of days. */
				_n( 'It has been %d day since we last saw you here.', 'It has been %d days since we last saw you here.', $days, 'wpcredits-program-manager' ),
				$days
			);
		}

		if ( 'track' === WPCPM_Student_Activity::clock_start_from( $student_id ) ) {
			return sprintf(
				/* translators: %d: a number of days. */
				_n( 'Your track started %d day ago.', 'Your track started %d days ago.', $days, 'wpcredits-program-manager' ),
				$days
			);
		}

		return sprintf(
			/* translators: %d: a number of days. */
			_n( 'Your account was made %d day ago.', 'Your account was made %d days ago.', $days, 'wpcredits-program-manager' ),
			$days
		);
	}

	/**
	 * The line on a mentee's card of the Mentor Report Card for a student who has gone quiet, with the
	 * button "We've already talked"; right after a press for this student, the line saying it was
	 * noted instead. Empty for a student who is not in the snapshot or is active again.
	 *
	 * @param int       $student_id The student's account, 0 for a mentee with none.
	 * @param bool|null $may_say    The page's one answer to whether the reader may press it
	 *                              (`may_say_talked_as_mentor()`); null to ask for this student alone.
	 * @param int       $now        Unix time; 0 for the current time.
	 * @return string Escaped markup, or ''.
	 */
	public static function mentee_line( $student_id, $may_say = null, $now = 0 ) {
		$student_id = (int) $student_id;

		if ( $student_id <= 0 ) {
			return '';
		}

		$said = self::said();

		if ( null !== $said && $said['student'] === $student_id ) {
			return self::said_markup( self::said_sentence( $said['name'], false ) );
		}

		$row = self::notice_row( $student_id, false, $now );

		if ( null === $row ) {
			return '';
		}

		$days = (int) $row['days'];
		$text = sprintf(
			/* translators: %d: a number of days. */
			_n( 'Not seen on the site for %d day.', 'Not seen on the site for %d days.', $days, 'wpcredits-program-manager' ),
			$days
		);
		$out = '<div class="wpcpm-quiet__notice"><p>' . esc_html( $text ) . '</p>';

		if ( null === $may_say ? self::may_say_talked( $student_id ) : (bool) $may_say ) {
			$out .= self::talked_form( $student_id );
		}

		return $out . '</div>';
	}

	/**
	 * Follow a save of the settings. A move into On, from Off or Preview, writes its time as the
	 * on-since time, so only a stretch that reaches the threshold after it is mailed; a save that
	 * leaves the switch On keeps the time. A save of Off deletes the stored list, so a list from
	 * before Off never comes back with the switch: the next run makes a fresh one.
	 *
	 * @param mixed $before The settings before the save.
	 * @param mixed $after  The settings after it.
	 */
	public static function follow_switch( $before, $after ) {
		$was = self::mode_of( $before );
		$now = self::mode_of( $after );

		if ( self::MODE_OFF === $now ) {
			delete_option( self::OPT_SNAPSHOT );
			return;
		}

		if ( self::MODE_ON === $now && self::MODE_ON !== $was ) {
			update_option( self::OPT_ON_SINCE, time(), false );
		}
	}

	/**
	 * The switch a settings array holds, read as `mode()` reads the stored one: anything but Preview
	 * or On is Off.
	 *
	 * @param mixed $settings A settings array, or anything else.
	 * @return string
	 */
	private static function mode_of( $settings ) {
		$mode = ( is_array( $settings ) && isset( $settings['student_flags_mode'] ) ) ? (string) $settings['student_flags_mode'] : self::MODE_OFF;

		return in_array( $mode, array( self::MODE_PREVIEW, self::MODE_ON ), true ) ? $mode : self::MODE_OFF;
	}
}
