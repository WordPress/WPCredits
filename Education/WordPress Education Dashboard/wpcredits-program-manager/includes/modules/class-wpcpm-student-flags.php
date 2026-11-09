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
	 * The program manager of a student: the account behind the contact of the country of the
	 * student's institution, when that account may manage the program.
	 *
	 * @param int $user_id The student's account.
	 * @return int User ID, or 0 when the chain breaks anywhere (no institution, no country, no
	 *             contact, no account, an account that cannot manage the program).
	 */
	public static function manager_of( $user_id ) {
		$institution = trim( (string) get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_INSTITUTION, true ) );

		if ( '' === $institution ) {
			return 0;
		}

		$row     = WPCPM_Institutions_Index::row( $institution );
		$country = ( is_array( $row ) && ! empty( $row['country'] ) ) ? (string) $row['country'] : '';

		if ( '' === $country ) {
			return 0;
		}

		$routing = WPCPM_Countries::routing( $country );
		$email   = ( is_array( $routing ) && ! empty( $routing['email'] ) ) ? trim( (string) $routing['email'] ) : '';

		if ( '' === $email ) {
			return 0;
		}

		$account = get_user_by( 'email', $email );

		if ( ! $account || ! user_can( $account, WPCPM_Roles::CAP_MANAGE ) ) {
			return 0;
		}

		return (int) $account->ID;
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
	 * stretch was reminded by the rule the sending skips by: a reminder whose stretch time is at or
	 * after the row's last activity (last activity that moved back, a canceled call, is the same
	 * stretch).
	 *
	 * @param int $now Unix time; 0 for the current time.
	 * @return array[] Rows: user_id, record, name, days, last_at, last_kind, mentor_id, manager_id,
	 *                 test, reminded_at, would_remind.
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

			$program     = WPCPM_Students_Sync::get_program( $user_id );
			$name        = ( is_array( $program ) && ! empty( $program['name'] ) ) ? trim( (string) $program['name'] ) : '';
			$institution = (string) get_user_meta( $user_id, WPCPM_Students_Sync::META_INSTITUTION, true );
			$test        = '' !== $institution && WPCPM_Test_Institutions::is_test( $institution );
			$reminded    = self::reminded( $user_id );
			$mentor      = WPCPM_Mentor_Calls::mentor_for_student( $user_id );

			if ( '' === $name ) {
				$user = get_userdata( $user_id );
				$name = $user ? (string) $user->display_name : '';
			}

			$out[] = array(
				'user_id'      => $user_id,
				'record'       => (string) get_user_meta( $user_id, WPCPM_Students_Sync::META_RECORD_ID, true ),
				'name'         => $name,
				'days'         => $days,
				'last_at'      => (int) $last['at'],
				'last_kind'    => (string) $last['kind'],
				'mentor_id'    => $mentor ? (int) $mentor->ID : 0,
				'manager_id'   => self::manager_of( $user_id ),
				'test'         => $test,
				'reminded_at'  => ( $reminded['for'] > 0 && (int) $last['at'] <= $reminded['for'] ) ? $reminded['at'] : 0,
				'would_remind' => $preview && ! $test && (int) $last['at'] > $reminded['for'],
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
	 * reminders' times, after.
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
			$now  = $now > 0 ? (int) $now : time();
			$rows = self::rows( $now );

			update_option(
				self::OPT_SNAPSHOT,
				array(
					'at'   => $now,
					'rows' => $rows,
				),
				false
			);

			if ( self::MODE_ON !== self::mode() ) {
				return;
			}

			$sent = self::remind( $rows, $now );

			self::digest( $sent, $now );
			self::weekly( $rows, $now );

			if ( array() !== $sent ) {
				foreach ( $rows as $n => $row ) {
					if ( isset( $sent[ $row['user_id'] ] ) ) {
						$rows[ $n ]['reminded_at'] = $now;
					}
				}

				update_option(
					self::OPT_SNAPSHOT,
					array(
						'at'   => $now,
						'rows' => $rows,
					),
					false
				);
			}
		} finally {
			WPCPM_Lock::release( self::LOCK, $token );
		}
	}

	/**
	 * Where a quiet student is sent to book: the calls section of their Student Report Card when they
	 * have a mentor, else the booking page of the Administrator for their country, else the Student
	 * Report Card. Never an empty address: the site's front page stands in when no card is published.
	 *
	 * @param int $user_id The student's account.
	 * @return string
	 */
	public static function booking_link( $user_id ) {
		$card = (string) WPCPM_Students_Dashboard::page_url();

		if ( WPCPM_Mentor_Calls::mentor_for_student( (int) $user_id ) ) {
			return '' !== $card ? $card . '#wpcpm-module-calls' : home_url( '/' );
		}

		$institution = trim( (string) get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_INSTITUTION, true ) );
		$row         = '' !== $institution ? WPCPM_Institutions_Index::row( $institution ) : null;
		$country     = ( is_array( $row ) && ! empty( $row['country'] ) ) ? (string) $row['country'] : '';
		$routing     = '' !== $country ? WPCPM_Countries::routing( $country ) : null;
		$calendly    = ( is_array( $routing ) && ! empty( $routing['calendly'] ) ) ? trim( (string) $routing['calendly'] ) : '';

		if ( '' !== $calendly ) {
			return $calendly;
		}

		return '' !== $card ? $card : home_url( '/' );
	}

	/**
	 * The snapshot's row of one student, for the notice on their Report Cards. Only while the switch
	 * is On: a Report Card says nothing about a reminder while nothing is being sent. And only while
	 * the student is still followed: one who graduated, paused or was deactivated since the morning's
	 * list sees no notice.
	 *
	 * @param int $user_id The student's account.
	 * @return array|null The row, or null.
	 */
	public static function notice_row( $user_id ) {
		if ( self::MODE_ON !== self::mode() || ! self::is_followed( (int) $user_id ) ) {
			return null;
		}

		foreach ( self::snapshot()['rows'] as $row ) {
			if ( isset( $row['user_id'] ) && (int) $row['user_id'] === (int) $user_id ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Send each quiet student one reminder for the spell they are in.
	 *
	 * A student is mailed only when everything holds at the moment of sending, read again here and not
	 * taken from the rows: the student is still followed (a graduate or a paused student since the
	 * snapshot is skipped), their last activity is still the spell's (a student who signed in since is
	 * no longer quiet), the days are still at the threshold, no call or group session is booked ahead,
	 * the institution is not a test one, the stretch reached the threshold at or after the latest move
	 * into On (`reached_since_on()`), and this spell has had no reminder. The reminder is stamped
	 * before it is sent.
	 *
	 * @param array[] $rows The snapshot's rows.
	 * @param int     $now  Unix time.
	 * @return array<int,array> The rows that were mailed, by student ID.
	 */
	public static function remind( array $rows, $now ) {
		$since = (int) get_option( self::OPT_ON_SINCE, 0 );

		if ( $since <= 0 ) {
			// On without the time it went On (set outside the settings screen): from now, so nobody who
			// was already quiet is mailed.
			$since = (int) $now;
			update_option( self::OPT_ON_SINCE, $since, false );
		}

		$sent = array();

		foreach ( $rows as $row ) {
			$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;
			$last_at = isset( $row['last_at'] ) ? (int) $row['last_at'] : 0;

			if ( $user_id <= 0 || ! self::reached_since_on( $last_at, $since ) || ! self::is_followed( $user_id ) ) {
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

			// A new spell needs activity after the last reminder: last activity that moved back (a
			// canceled call stops counting) is the same quiet stretch.
			if ( $last_at <= self::reminded( $user_id )['for'] ) {
				continue;
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

			$mentor = WPCPM_Mentor_Calls::mentor_for_student( $user_id );
			$values = array(
				'days'           => (string) $days,
				'mentor_name'    => $mentor ? trim( (string) $mentor->display_name ) : '',
				'booking_link'   => self::booking_link( $user_id ),
				'dashboard_link' => self::or_home( (string) WPCPM_Students_Dashboard::page_url() ),
			);

			WPCPM_Mail::send(
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
			$sent[ $user_id ] = $row;
		}

		return $sent;
	}

	/**
	 * One email to each mentor, listing their students who were reminded in this run, each a line that
	 * links to the student on the Mentor Report Card. A student with no mentor, or whose mentor has no
	 * account, is reminded alone.
	 *
	 * @param array[] $sent The rows `remind()` mailed.
	 * @param int     $now  Unix time.
	 */
	public static function digest( array $sent, $now ) {
		unset( $now );

		$by_mentor = array();

		foreach ( $sent as $row ) {
			if ( ! empty( $row['mentor_id'] ) ) {
				$by_mentor[ (int) $row['mentor_id'] ][] = $row;
			}
		}

		if ( array() === $by_mentor ) {
			return;
		}

		$card = (string) WPCPM_Mentors_Dashboard::page_url();

		foreach ( $by_mentor as $mentor_id => $list ) {
			$lines = array();

			foreach ( $list as $row ) {
				$url = '';

				if ( '' !== $card && '' !== (string) $row['record'] ) {
					$url = add_query_arg( 'wpcpm_student', $row['record'], $card ) . '#' . WPCPM_Mentor_Notes::anchor( $row['record'] );
				}

				$lines[] = self::list_line( (string) $row['name'], (int) $row['days'], $url );
			}

			$values = array(
				'students_list'  => implode( "\n", $lines ),
				'dashboard_link' => self::or_home( $card ),
			);

			WPCPM_Mail::send(
				$mentor_id,
				'student-quiet-mentor',
				static function () use ( $values ) {
					return WPCPM_Mail_Templates::mail(
						'student-quiet-mentor',
						array_merge( array( 'site_name' => WPCPM_Mail::site_name() ), $values )
					);
				}
			);
		}
	}

	/**
	 * The Monday list: on the first run of a Monday (site time), once for each ISO week, one email to
	 * each Administrator who has real quiet students, counting and listing those students. A test
	 * institution's student is neither counted nor listed, and an Administrator with only test
	 * students gets nothing. The week is noted before any mail goes.
	 *
	 * @param array[] $rows The snapshot's rows.
	 * @param int     $now  Unix time.
	 */
	public static function weekly( array $rows, $now ) {
		if ( '1' !== (string) wp_date( 'N', (int) $now ) ) {
			return;
		}

		$week = (string) wp_date( 'o-W', (int) $now );

		if ( (string) get_option( self::OPT_WEEKLY, '' ) === $week ) {
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
			$lines = array();

			foreach ( $list as $row ) {
				$url     = '' !== $students ? add_query_arg( 'wpcpm_student_view', (int) $row['user_id'], $students ) : '';
				$lines[] = self::list_line( (string) $row['name'], (int) $row['days'], $url );
			}

			$values = array(
				'count'         => (string) count( $list ),
				'students_list' => implode( "\n", $lines ),
				'card_link'     => '' !== $admin ? $admin . '#wpcpm-quiet-students' : home_url( '/' ),
			);

			WPCPM_Mail::send(
				$manager_id,
				'student-quiet-weekly',
				static function () use ( $values ) {
					return WPCPM_Mail_Templates::mail(
						'student-quiet-weekly',
						array_merge( array( 'site_name' => WPCPM_Mail::site_name() ), $values )
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
	 * "We've already talked": record it as activity, so the student is no longer quiet, take the row
	 * off the card at once, and go back to the page the press came from. Allowed for the student,
	 * their mentor and Administrators; a stale nonce stops on WordPress's own screen.
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

		$back = WPCPM_Request::back( (string) WPCPM_Students_Dashboard::page_url(), home_url( '/' ) );

		// The Administrator Dashboard's card posts where it should land, which the allowlist maps.
		if ( WPCPM_Return::DASHBOARD === WPCPM_Request::posted_key( WPCPM_Return::FIELD ) ) {
			$back = WPCPM_Return::url( $back );
		}

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * The "We've already talked" button, as a form of its own, for a Report Card or a dashboard card.
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
		wp_nonce_field( self::ACTION_TALKED . '_' . $student_id );

		if ( '' !== (string) $card ) {
			WPCPM_Return::field( WPCPM_Return::DASHBOARD, (string) $card );
		}

		$fields = (string) ob_get_clean();

		return sprintf(
			'<form class="wpcpm-quiet__talked" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">%3$s<input type="hidden" name="action" value="%4$s" /><input type="hidden" name="student" value="%5$d" /><button type="submit" class="wpcpm-button wpcpm-button--secondary">%6$s%7$s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' ),
			$fields,
			esc_attr( self::ACTION_TALKED ),
			$student_id,
			esc_html( $label ),
			$user ? ' <span class="screen-reader-text">' . esc_html( sprintf( /* translators: %s: a student's name. */ __( 'for %s', 'wpcredits-program-manager' ), (string) $user->display_name ) ) . '</span>' : ''
		);
	}

	/**
	 * The one paragraph on the Student Report Card for a student who has gone quiet: how long it has
	 * been, where to book, and the button "We've already talked" for whoever may press it. Empty for a
	 * student who is not in the snapshot, and while the switch is not On.
	 *
	 * @param WP_User $student The student whose card it is.
	 * @param int     $viewer  The reader's account ID.
	 * @return string Escaped markup, or ''.
	 */
	public static function student_notice( WP_User $student, $viewer ) {
		$row = self::notice_row( (int) $student->ID );

		if ( null === $row ) {
			return '';
		}

		$days = (int) $row['days'];
		$book = self::booking_link( (int) $student->ID );

		if ( (int) $viewer === (int) $student->ID ) {
			$text = sprintf(
				/* translators: %d: a number of days. */
				_n( 'It has been %d day since we last saw you here. A short call is the easiest way to get going again.', 'It has been %d days since we last saw you here. A short call is the easiest way to get going again.', $days, 'wpcredits-program-manager' ),
				$days
			);
		} else {
			$text = sprintf(
				/* translators: 1: a student's name, 2: a number of days. */
				_n( '%1$s has not been seen here for %2$d day.', '%1$s has not been seen here for %2$d days.', $days, 'wpcredits-program-manager' ),
				(string) $student->display_name,
				$days
			);
		}

		$out = '<div class="wpcpm-quiet__notice"><p>' . esc_html( $text ) . ' <a href="' . esc_url( $book ) . '">' . esc_html__( 'Book a call', 'wpcredits-program-manager' ) . '</a></p>';

		if ( self::may_say_talked( (int) $student->ID ) ) {
			$out .= self::talked_form( (int) $student->ID );
		}

		return $out . '</div>';
	}

	/**
	 * The line on a mentee's card of the Mentor Report Card for a student who has gone quiet, with the
	 * button "We've already talked". Empty for a student who is not in the snapshot.
	 *
	 * @param int $student_id The student's account, 0 for a mentee with none.
	 * @return string Escaped markup, or ''.
	 */
	public static function mentee_line( $student_id ) {
		$row = (int) $student_id > 0 ? self::notice_row( (int) $student_id ) : null;

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

		if ( self::may_say_talked( (int) $student_id ) ) {
			$out .= self::talked_form( (int) $student_id );
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
