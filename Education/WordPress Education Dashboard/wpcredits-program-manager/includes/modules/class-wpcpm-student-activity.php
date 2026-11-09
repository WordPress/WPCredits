<?php
/**
 * When a student was last active, and how.
 *
 * Every later part of the student automation (quiet-student flags, reminders, the checklist) asks
 * one question: "when was this student last active?". This class is the one answer, so the flags,
 * the mails and the cards can never disagree about a student's quiet time.
 *
 * Last activity is the latest of: the last sign-in, the last visit, every call or group session of
 * the student that is not trashed (at the later of its booking time and its start once the start
 * has passed, else its booking time), the site's own report-save stamp, the time Airtable last saw
 * the report edited (students edit through outside forms too, which the site's stamp misses), and
 * the time somebody recorded "we've already talked". The clock start (the later of the account's
 * registration and the track's start date) is a floor under all of them: when it is later than every
 * activity it is the answer, so a student who signed in once before their track began, or who is back
 * on an old account for a new track, counts from the start and is not quiet before they have begun.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * The last-activity answer, and the stamps it reads.
 */
final class WPCPM_Student_Activity {

	/**
	 * User meta: Unix time of the last report save made on this site.
	 */
	const META_REPORT = 'wpcpm_last_report_save';

	/**
	 * User meta: Unix time of the last edit Airtable saw on the student's report row, read by the
	 * students sync from the "Student report last edited" column.
	 */
	const META_EDITED = 'wpcpm_report_edited';

	/**
	 * User meta: array( 'at' => Unix time, 'by' => user ID ) when somebody said "we've already talked".
	 */
	const META_TALKED = 'wpcpm_student_talked';

	/**
	 * The latest activity of a student, or the clock start when that is later.
	 *
	 * Activity only: a call booked for later counts at its booking time here, and whether a booked
	 * call keeps a student from being quiet is the flags' question (`WPCPM_Student_Flags`), not this
	 * answer's.
	 *
	 * @param int $user_id The student's account.
	 * @param int $now     Unix time to read calls against; 0 for the current time.
	 * @return array{at:int,kind:string} Kind: sign_in, visit, call, report, edited, talked or start.
	 */
	public static function last( $user_id, $now = 0 ) {
		return self::last_of( $user_id, self::times( $user_id, $now ) );
	}

	/**
	 * Each kind of activity of a student, at its own latest time: the stamps `last()` folds into one
	 * answer, handed out one by one for a caller that has to leave one kind out, as the Student Report
	 * Card's notice leaves out the sign-in and the visit of the return that brought the student to it.
	 *
	 * @param int $user_id The student's account.
	 * @param int $now     Unix time to read calls against; 0 for the current time.
	 * @return array<string,int> sign_in, visit, report, edited, talked and call, in that order; 0 for
	 *                           a kind with none.
	 */
	public static function times( $user_id, $now = 0 ) {
		$user_id = (int) $user_id;
		$now     = $now > 0 ? (int) $now : time();
		$found   = array();

		$found['sign_in'] = self::meta_time( $user_id, WPCPM_Sign_Ins::META_SIGN_IN );
		$found['visit']   = self::meta_time( $user_id, WPCPM_Sign_Ins::META_VISIT );
		$found['report']  = self::meta_time( $user_id, self::META_REPORT );
		$found['edited']  = self::meta_time( $user_id, self::META_EDITED );

		$talked = get_user_meta( $user_id, self::META_TALKED, true );

		$found['talked'] = ( is_array( $talked ) && isset( $talked['at'] ) && is_numeric( $talked['at'] ) ) ? (int) $talked['at'] : 0;
		$found['call']   = self::latest_call( $user_id, $now );

		return $found;
	}

	/**
	 * The one answer from each kind's time (`times()`): the latest of them, or the clock start when
	 * that is later.
	 *
	 * @param int   $user_id The student's account, whose clock start is read.
	 * @param array $times   Kind => Unix time, as `times()` gives them; a kind left at 0 is none.
	 * @return array{at:int,kind:string}
	 */
	public static function last_of( $user_id, array $times ) {
		// Ties go to the first kind listed, so the answer is stable.
		$best = array(
			'at'   => 0,
			'kind' => '',
		);

		foreach ( $times as $kind => $at ) {
			if ( (int) $at > $best['at'] ) {
				$best = array(
					'at'   => (int) $at,
					'kind' => (string) $kind,
				);
			}
		}

		// The clock start is a floor, not only the answer when nothing exists (spec 2.1: the clock
		// starts at the account date or the track's start, whichever is later). A tie goes to the
		// activity.
		$start = self::clock_start( $user_id );

		if ( $best['at'] > 0 && $best['at'] >= $start ) {
			return $best;
		}

		return array(
			'at'   => $start,
			'kind' => 'start',
		);
	}

	/**
	 * When a student's quiet clock starts: the later of the account's registration and the program
	 * start date, when that date parses. A start in the future holds the clock back.
	 *
	 * @param int $user_id The student's account.
	 * @return int Unix time.
	 */
	public static function clock_start( $user_id ) {
		$user_id = (int) $user_id;
		$user    = get_userdata( $user_id );
		$at      = 0;

		if ( $user && ! empty( $user->user_registered ) ) {
			$parsed = strtotime( $user->user_registered . ' UTC' );
			$at     = false === $parsed ? 0 : (int) $parsed;
		}

		$program = WPCPM_Students_Sync::get_program( $user_id );
		$start   = ( is_array( $program ) && isset( $program['start'] ) ) ? trim( (string) $program['start'] ) : '';

		if ( '' !== $start ) {
			$parsed = strtotime( $start . ' UTC' );

			if ( false !== $parsed && $parsed > $at ) {
				$at = (int) $parsed;
			}
		}

		return $at;
	}

	/**
	 * Which of the two dates the clock start is (`clock_start()`): the track's start when it parses and
	 * is the later, the account's registration otherwise, a tie included. So a card can say "Track
	 * started" or "Account made" for a student never seen since, rather than naming one date for both.
	 *
	 * @param int $user_id The student's account.
	 * @return string 'track' or 'account'.
	 */
	public static function clock_start_from( $user_id ) {
		$user_id    = (int) $user_id;
		$user       = get_userdata( $user_id );
		$registered = 0;

		if ( $user && ! empty( $user->user_registered ) ) {
			$parsed     = strtotime( $user->user_registered . ' UTC' );
			$registered = false === $parsed ? 0 : (int) $parsed;
		}

		$program = WPCPM_Students_Sync::get_program( $user_id );
		$start   = ( is_array( $program ) && isset( $program['start'] ) ) ? trim( (string) $program['start'] ) : '';
		$parsed  = '' !== $start ? strtotime( $start . ' UTC' ) : false;

		return ( false !== $parsed && (int) $parsed > $registered ) ? 'track' : 'account';
	}

	/**
	 * Whole days since the last activity; 0 when that time is still ahead.
	 *
	 * @param int $user_id The student's account.
	 * @param int $now     Unix time; 0 for the current time.
	 * @return int
	 */
	public static function quiet_days( $user_id, $now = 0 ) {
		$now = $now > 0 ? (int) $now : time();

		return self::days_since( self::last( $user_id, $now )['at'], $now );
	}

	/**
	 * Whole days from a time to now; 0 when that time is still ahead. The one rule `quiet_days()`
	 * counts by, for a caller that already holds `last()` and should not read it again.
	 *
	 * @param int $at  Unix time, such as `last()['at']`.
	 * @param int $now Unix time.
	 * @return int
	 */
	public static function days_since( $at, $now ) {
		$at  = (int) $at;
		$now = (int) $now;

		if ( $at >= $now ) {
			return 0;
		}

		return (int) floor( ( $now - $at ) / DAY_IN_SECONDS );
	}

	/**
	 * Record a report save made on this site. Called only after Airtable accepted the write.
	 *
	 * @param int $user_id The student's account.
	 * @param int $time    Unix time; 0 for the current time.
	 * @return void
	 */
	public static function stamp_report( $user_id, $time = 0 ) {
		update_user_meta( (int) $user_id, self::META_REPORT, $time > 0 ? (int) $time : time() );
	}

	/**
	 * A user meta holding a Unix time, or 0.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @return int
	 */
	private static function meta_time( $user_id, $key ) {
		$value = get_user_meta( $user_id, $key, true );

		return ( is_numeric( $value ) && (int) $value > 0 ) ? (int) $value : 0;
	}

	/**
	 * The latest time of any live call or group session of the student.
	 *
	 * @param int $user_id The student's account.
	 * @param int $now     Unix time.
	 * @return int
	 */
	private static function latest_call( $user_id, $now ) {
		$latest = 0;

		foreach ( (array) WPCPM_Mentor_Calls::for_student( $user_id, false ) as $call ) {
			if ( ! is_object( $call ) || 'trash' === $call->post_status ) {
				continue;
			}

			$details = WPCPM_Mentor_Calls::details( $call );
			$booked  = isset( $details['booked'] ) ? (int) $details['booked'] : 0;
			$start   = isset( $details['start'] ) ? (int) $details['start'] : 0;
			$at      = $booked;

			if ( $start > 0 && $start <= $now && $start > $at ) {
				$at = $start;
			}

			if ( $at > $latest ) {
				$latest = $at;
			}
		}

		return $latest;
	}
}
