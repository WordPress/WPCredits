<?php
/**
 * The status a finished Feedback form 3 sets: Pending graduation (1.123.95).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets a student's Students row to Pending graduation when they finish Feedback form 3 on this site, and keeps a
 * write that failed owed until the program's records say so.
 *
 * **What the base's own form does, done here.** A track ends with Form 3. Sent through the form in the program's
 * records, an automation of that base sets the student's Students row to `Pending graduation`, and the Administrator
 * Dashboard's card "Students waiting to graduate" lists the students at that status. Sent on this site, the answers
 * reach the Feedback table by `update_records()`, which fires no form trigger, so nothing set the status and a finished
 * student waited unseen. `WPCPM_Student_Feedback::handle_save()` now calls `form_finished()` when a save leaves Form 3
 * complete that was not complete before it, once per account: an account the site has set the status for
 * (`META_SET`) is never set again from a later save, so an Administrator who puts a student back on a track is not
 * overruled by the student opening their form.
 *
 * **The row is found by its record ID, never by an address.** The roster index holds every Students row the last
 * students sync read, each with the account the sync joined it to (`user_id`), and that join is the site's own word
 * about whose row it is (`rows_of()`). The base's automation matches a typed address and misses when it differs.
 *
 * **Written only from a track, on what the records say now.** The site's copy of the student's status (the program
 * row the students sync keeps) must be a track first; then each row is read live, and only a row the records still
 * hold at a track, and that still carries the address the site holds for the student's reports row, is written
 * (`attempt()`). The save and the sync's retry both go through it.
 *
 * **It never costs the student anything.** The answers and the consent stamp are saved first; a fault in here becomes
 * a debt and is never the student's error; and a finishing save adds at most `BUDGET` seconds: the save's own client
 * is given short timeouts, and a request with no room left is not sent but owed.
 *
 * **A failed write is owed, not lost.** A status that could not be read or written is stamped on the account
 * (`META_OWED`). At the end of each students sync run every owing account is looked at (`retry()`): written when its
 * rows are still on a track, cleared when it can no longer be paid, kept with its reason when it has no Students row.
 * The card lists the student meanwhile, with words saying which of the two it is.
 *
 * No hours or time guard, as the base's automation has none; no mail, as the card is the notice; and no backfill: a
 * Form 3 finished before 1.123.95 is not touched.
 */
class WPCPM_Student_Graduation {

	/**
	 * User meta: when this site set the student's Students row to Pending graduation, by whom and as whom, and which
	 * rows: `at` (Unix time of the write), `finished` (when the form was finished: the save's own time, or the debt's
	 * first time for a retry), `by` (the account that saved the form), `as` (`student` or `administrator`), `records`
	 * (Students record IDs), `via` (`form`, or `sync` for a retry), `report` (the student's reports record) and `track`
	 * (the track they finished). The base keeps no date for the status; this answers "when did this student become
	 * pending" for the ones the site set, and `blocks()` reads the last two: the site sets the status once per program.
	 */
	const META_SET = 'wpcpm_student_pending_set';

	/**
	 * User meta: a Pending graduation this site owes the program's records, because the write failed or had no row to
	 * go to: `at` (when the form was finished, kept across attempts), `by`, `as`, `reason` (what the records or the
	 * site said), `tried` (the last attempt), `kind` (which line the card draws: `retry` for a debt the sync pays itself,
	 * `no-row` when no Students row matched, `other-email` when the row holds another address), `report` and `track`
	 * (the reports record and the track it was made for: a debt is only ever paid for that program). Deleted when the
	 * status is written, or when a students sync finds it can no longer be paid.
	 */
	const META_OWED = 'wpcpm_student_pending_owed';

	/** Who saved the form: the student themselves. */
	const AS_STUDENT = 'student';

	/** Who saved the form: an Administrator typing it up for the student. */
	const AS_ADMINISTRATOR = 'administrator';

	/** The audit trail's kind for the write. */
	const KIND_LOG = 'student_pending';

	/** Seconds a finishing save may add to what a save takes, the answers' read before it included. */
	const BUDGET = 10;

	/** Seconds one request of a finishing save, or of the sync's retry, may take at most. */
	const REQUEST_MAX = 5;

	/** Seconds the retry step at the end of a students sync run may take; what is left goes to the next run. */
	const STEP_BUDGET = 20;

	/** The card's line for a debt the students sync pays itself. */
	const KIND_RETRY = 'retry';

	/** The card's line for a debt with no Students row matched. */
	const KIND_NO_ROW = 'no-row';

	/** The card's line for a debt whose row holds another address. */
	const KIND_OTHER_EMAIL = 'other-email';

	/**
	 * Stand-in for `microtime( true )`, for the suites. Null means the real clock.
	 *
	 * @var callable|null
	 */
	public static $clock = null;

	/**
	 * Now, in seconds, from the clock above.
	 *
	 * @return float
	 */
	public static function now() {
		return is_callable( self::$clock ) ? (float) call_user_func( self::$clock ) : microtime( true );
	}

	/**
	 * Whether the site has already set this account's status after a finished Form 3.
	 *
	 * @param int $user_id The account.
	 * @return bool
	 */
	public static function was_set( $user_id ) {
		$set = get_user_meta( (int) $user_id, self::META_SET, true );

		return is_array( $set ) && ! empty( $set['at'] );
	}

	/**
	 * Whether the site's earlier write blocks setting the status again for this program.
	 *
	 * Once per program: while the student's reports record and track are the ones the status was set for, a later save
	 * never sets it again, so an Administrator who puts a student back on the same track is not overruled. A new
	 * reports record (a new program) or another track is not blocked. A set meta of round 1's shape names no program,
	 * and blocks as it did: it can only be on a trial copy, and blocking is the reading that never writes where the
	 * earlier build would not.
	 *
	 * @param int   $user_id The account.
	 * @param array $program Its program row now (`WPCPM_Students_Sync::get_program()`).
	 * @return bool
	 */
	public static function blocks( $user_id, array $program ) {
		$set = get_user_meta( (int) $user_id, self::META_SET, true );

		if ( ! is_array( $set ) || empty( $set['at'] ) ) {
			return false;
		}

		if ( ! array_key_exists( 'report', $set ) ) {
			return true;
		}

		$track = isset( $set['track'] ) ? (string) $set['track'] : '';

		return self::report_of( $program ) === (string) $set['report'] && WPCPM_Students_Sync::status_of( $program ) === $track;
	}

	/**
	 * The reports record a program row is about.
	 *
	 * @param array $program A program row.
	 * @return string
	 */
	private static function report_of( array $program ) {
		return isset( $program['record_id'] ) && is_scalar( $program['record_id'] ) ? trim( (string) $program['record_id'] ) : '';
	}

	/**
	 * Keep a debt after a fault the students sync caught in its retry: the time the form was finished, who saved it and
	 * as whom stay; the reason is the fault's.
	 *
	 * @param int    $user_id The account.
	 * @param string $reason  What went wrong.
	 */
	public static function keep_owed( $user_id, $reason ) {
		$owed = self::owed( $user_id );

		self::owe( $user_id, $owed ? $owed['by'] : 0, $owed ? $owed['as'] : self::AS_STUDENT, $reason, self::KIND_RETRY );
	}

	/**
	 * A Form 3 was just finished: set the student's Students rows on a track to Pending graduation.
	 *
	 * Never throws: whatever goes wrong in here becomes a debt, and the student's save goes on as it would have.
	 *
	 * @param int   $student_id The student whose form it is.
	 * @param int   $actor_id   Who saved it: the student, or an Administrator for them.
	 * @param float $spent      Seconds of `BUDGET` the save has already used, on the answers' read before it.
	 * @return string What happened: `set`, `already-set`, `not-track` (the site's copy says no track), `not-in-base`
	 *                (the records hold no row on a track), or `owed`.
	 */
	public static function form_finished( $student_id, $actor_id, $spent = 0.0 ) {
		$student_id = (int) $student_id;
		$actor_id   = (int) $actor_id;
		$role       = $actor_id === $student_id ? self::AS_STUDENT : self::AS_ADMINISTRATOR;

		try {
			return self::finish( $student_id, $actor_id, $role, (float) $spent );
		} catch ( \Throwable $fault ) {
			try {
				self::owe( $student_id, $actor_id, $role, $fault->getMessage(), self::KIND_RETRY );
			} catch ( \Throwable $also ) {
				// Nothing more can be kept; the student's save is still not theirs to lose over it.
				unset( $also );
			}

			return 'owed';
		}
	}

	/**
	 * The body of `form_finished()`.
	 *
	 * @param int    $student_id The student.
	 * @param int    $actor_id   Who saved the form.
	 * @param string $role       `student` or `administrator`.
	 * @param float  $spent      Seconds of the budget already used.
	 * @return string
	 */
	private static function finish( $student_id, $actor_id, $role, $spent ) {
		$program = WPCPM_Students_Sync::get_program( $student_id );
		$status  = WPCPM_Students_Sync::status_of( $program );

		if ( self::blocks( $student_id, $program ) ) {
			return 'already-set';
		}

		if ( ! WPCPM_Program::is_track( $status ) ) {
			return 'not-track';
		}

		$rows = self::rows_of( $student_id );

		if ( empty( $rows ) ) {
			self::owe( $student_id, $actor_id, $role, self::reason_no_row(), self::KIND_NO_ROW );

			return 'owed';
		}

		$result = self::attempt( $program, array_keys( $rows ), self::now() + self::BUDGET - max( 0.0, $spent ) );

		// What the records say now is what the card reads now: a row already at Pending graduation is listed at once.
		foreach ( $result['live'] as $record => $live ) {
			if ( '' !== $live && ! WPCPM_Program::is_track( $live ) && $live !== $rows[ $record ]['status'] ) {
				self::index( $rows[ $record ]['institution'], $record, $live );
			}
		}

		if ( 'owed' === $result['outcome'] ) {
			self::owe( $student_id, $actor_id, $role, $result['reason'], $result['kind'] );

			return 'owed';
		}

		if ( 'none' === $result['outcome'] ) {
			return 'not-in-base';
		}

		// From here on the write has landed: nothing may turn it into a debt but a marker the site could not keep.
		try {
			foreach ( array_keys( $result['written'] ) as $record ) {
				self::index( $rows[ $record ]['institution'], $record, WPCPM_Program::STATUS_PENDING );
			}
		} catch ( \Throwable $fault ) {
			unset( $fault );
		}

		if ( ! self::settled( $student_id, $actor_id, $role, $result['written'], $program, 'form', time() ) ) {
			self::owe( $student_id, $actor_id, $role, self::reason_unmarked(), self::KIND_RETRY );

			return 'owed';
		}

		return 'set';
	}

	/**
	 * Try an owed status again, at the end of a students sync run, for one owing account.
	 *
	 * - An account the run no longer knows as a student, or whose program status is no longer a track (Graduate, a
	 *   drop-out, Pending graduation already, anything not a track): the debt is cleared, nothing written.
	 * - No Students row joined to it: the debt stays, with the no-row reason.
	 * - Rows the run holds at a track: each is read live and written when it is still on a track and still the
	 *   student's (`attempt()`); a row now at another status settles the debt for that row.
	 *
	 * @param int        $user_id  The student's account.
	 * @param array      $rows     Students record ID => the status this run read for it, the rows the run joined to it.
	 * @param bool       $known    Whether the run read the account's reports row.
	 * @param float|null $deadline When the sync's retry step runs out (`STEP_BUDGET`), or null for none.
	 * @return string[]|WP_Error The record IDs written, an empty list when nothing was, or why a write is still owed,
	 *                           with the client's own error under `cause` in the data when there was one.
	 */
	public static function retry( $user_id, array $rows, $known, $deadline = null ) {
		$user_id = (int) $user_id;
		$owed    = self::owed( $user_id );

		if ( empty( $owed ) ) {
			return array();
		}

		$program = WPCPM_Students_Sync::get_program( $user_id );
		$status  = WPCPM_Students_Sync::status_of( $program );

		// The write landed although its answer timed out, and the records have since copied the Students row's status to
		// the reports row: the program status reads Pending graduation for the debt's own reports record. That is the
		// site's own write, settled with no read and no write, under the debt's track and from the debt's first day.
		if ( null !== $owed['report'] && null !== $owed['track'] && $known && WPCPM_Program::STATUS_PENDING === $status
			&& self::report_of( $program ) === $owed['report'] ) {
			$held = array_fill_keys( array_map( 'strval', array_keys( $rows ) ), $owed['track'] );

			if ( ! self::settled( $user_id, $owed['by'], $owed['as'], $held, array_merge( $program, array( 'program' => $owed['track'] ) ), 'sync', $owed['at'], array_keys( $held ) ) ) {
				self::owe( $user_id, $owed['by'], $owed['as'], self::reason_unmarked(), self::KIND_RETRY );

				return new WP_Error( 'wpcpm_pending_unmarked', self::reason_unmarked() );
			}

			return array();
		}

		// Only for the program it was made for: a debt naming none (round 1's shape, on trial copies only), another
		// reports record or another track is cleared; the student's new program asks its own Form 3. Nor while the site's
		// earlier write for this program stands (`blocks()`), nor for an account the run did not read, nor off a track.
		if ( null === $owed['report'] || null === $owed['track'] || self::report_of( $program ) !== $owed['report'] || $status !== $owed['track']
			|| ! $known || ! WPCPM_Program::is_track( $status ) || self::blocks( $user_id, $program ) ) {
			delete_user_meta( $user_id, self::META_OWED );

			return array();
		}

		// Kept, and not named in the run report: no run can pay it until the row exists, and the card says so.
		if ( empty( $rows ) ) {
			self::owe( $user_id, $owed['by'], $owed['as'], self::reason_no_row(), self::KIND_NO_ROW );

			return array();
		}

		// The rows the run holds at a track, and at Pending graduation: one of those may be the site's own write, which
		// landed although its answer never came.
		$candidates = array();

		foreach ( $rows as $record => $held ) {
			$held = trim( (string) $held );

			if ( WPCPM_Program::is_track( $held ) || WPCPM_Program::STATUS_PENDING === $held ) {
				$candidates[] = (string) $record;
			}
		}

		if ( empty( $candidates ) ) {
			delete_user_meta( $user_id, self::META_OWED );

			return array();
		}

		$result = self::attempt( $program, $candidates, $deadline );

		if ( 'owed' === $result['outcome'] ) {
			self::owe( $user_id, $owed['by'], $owed['as'], $result['reason'], $result['kind'] );

			return new WP_Error( 'wpcpm_pending_owed', $result['reason'], array( 'cause' => $result['error'] ) );
		}

		// A row read now at Pending graduation, for the debt's own program: the site's write, settled as its own.
		$landed = array();

		foreach ( $result['live'] as $record => $live ) {
			if ( WPCPM_Program::STATUS_PENDING === $live ) {
				$landed[ $record ] = $owed['track'];
			}
		}

		$settled = $result['written'] + $landed;

		if ( empty( $settled ) ) {
			delete_user_meta( $user_id, self::META_OWED );

			return array();
		}

		if ( ! self::settled( $user_id, $owed['by'], $owed['as'], $settled, $program, 'sync', $owed['at'], array_keys( $landed ) ) ) {
			self::owe( $user_id, $owed['by'], $owed['as'], self::reason_unmarked(), self::KIND_RETRY );

			return new WP_Error( 'wpcpm_pending_unmarked', self::reason_unmarked() );
		}

		return array_keys( $settled );
	}

	/**
	 * Read each row live and write Pending graduation to the ones still on a track and still the student's.
	 *
	 * Checked before any read: the settings name a Students table and a status column. A row is the student's when the
	 * address on it, read now, is the address the site holds for the student's reports row: the pair the sync's join
	 * matched, never anything typed on a form. With a deadline, each request is given what is left of it, up to
	 * `REQUEST_MAX` seconds, and one with under a second left is not sent: the write is owed and the sync makes it.
	 *
	 * @param array      $program  The student's program row (`WPCPM_Students_Sync::get_program()`).
	 * @param string[]   $records  Students record IDs to read.
	 * @param float|null $deadline When the save's budget runs out, or null for the sync, which has no one waiting.
	 * @return array `outcome` (`set`, `none` or `owed`), `reason` and `kind` (for `owed`), `error` (the client's own
	 *               error, when one stopped it), `written` (record ID => the track it was at), `live` (record ID => the
	 *               status read now).
	 */
	private static function attempt( array $program, array $records, $deadline ) {
		$out      = array(
			'outcome' => 'none',
			'reason'  => '',
			'kind'    => self::KIND_RETRY,
			'error'   => null,
			'written' => array(),
			'live'    => array(),
		);
		$settings = WPCPM_Settings::get();
		$table    = isset( $settings['students_table'] ) ? trim( (string) $settings['students_table'] ) : '';
		$fields   = WPCPM_Mentors_Sync::fields();
		$column   = self::column();
		$address  = isset( $fields['student_email'] ) && is_string( $fields['student_email'] ) ? $fields['student_email'] : '';

		if ( '' === $table || '' === $column ) {
			$out['outcome'] = 'owed';
			$out['reason']  = __( 'The settings name no Students table or status column.', 'wpcredits-program-manager' );
			$out['error']   = new WP_Error( 'wpcpm_pending_no_column', $out['reason'] );

			return $out;
		}

		$airtable = new WPCPM_Airtable( $settings );
		$email    = strtolower( trim( isset( $program['email'] ) && is_scalar( $program['email'] ) ? (string) $program['email'] : '' ) );
		$tracked  = array();
		$other    = false;
		$unknown  = false;

		foreach ( $records as $record ) {
			if ( ! self::room( $airtable, $deadline ) ) {
				$out['outcome'] = 'owed';
				$out['reason']  = self::reason_time();

				return $out;
			}

			$read = $airtable->get_record( $table, $record );

			if ( is_wp_error( $read ) ) {
				$out['outcome'] = 'owed';
				$out['reason']  = $read->get_error_message();
				$out['error']   = $read;

				return $out;
			}

			$live = trim( WPCPM_Airtable::flatten( isset( $read['fields'][ $column ] ) ? $read['fields'][ $column ] : '' ) );

			$out['live'][ $record ] = $live;

			if ( ! WPCPM_Program::is_track( $live ) ) {
				continue;
			}

			$on_row = strtolower( trim( WPCPM_Airtable::flatten( '' !== $address && isset( $read['fields'][ $address ] ) ? $read['fields'][ $address ] : '' ) ) );

			if ( '' === $email ) {
				$unknown = true;
				continue;
			}

			if ( $on_row !== $email ) {
				$other = true;
				continue;
			}

			$tracked[ $record ] = $live;
		}

		if ( empty( $tracked ) ) {
			if ( $unknown ) {
				$out['outcome'] = 'owed';
				$out['reason']  = __( 'The site holds no email address for this student, so their Students row cannot be matched to them and was not written.', 'wpcredits-program-manager' );
				$out['kind']    = self::KIND_NO_ROW;
			} elseif ( $other ) {
				$out['outcome'] = 'owed';
				$out['reason']  = self::reason_other();
				$out['kind']    = self::KIND_OTHER_EMAIL;
			}

			return $out;
		}

		if ( ! self::room( $airtable, $deadline ) ) {
			$out['outcome'] = 'owed';
			$out['reason']  = self::reason_time();

			return $out;
		}

		$cells = array();

		foreach ( array_keys( $tracked ) as $record ) {
			$cells[] = array(
				'id'     => (string) $record,
				'fields' => array( $column => WPCPM_Program::STATUS_PENDING ),
			);
		}

		$result = $airtable->update_records( $table, $cells );

		if ( is_wp_error( $result ) ) {
			$out['outcome'] = 'owed';
			$out['reason']  = $result->get_error_message();
			$out['error']   = $result;

			return $out;
		}

		$out['outcome'] = 'set';
		$out['written'] = $tracked;

		return $out;
	}

	/**
	 * Whether there is room for one more request before the deadline, and give the client what is left of it.
	 *
	 * @param WPCPM_Airtable $airtable The save's client.
	 * @param float|null     $deadline When the budget runs out, or null for no budget.
	 * @return bool
	 */
	private static function room( WPCPM_Airtable $airtable, $deadline ) {
		if ( null === $deadline ) {
			return true;
		}

		$left = (float) $deadline - self::now();

		if ( $left < 1 ) {
			return false;
		}

		$airtable->set_timeout( min( (float) self::REQUEST_MAX, $left ) );

		return true;
	}

	/**
	 * What an account owes, or an empty array.
	 *
	 * @param int $user_id The account.
	 * @return array `at`, `by`, `as`, `reason`, `tried`, `kind`, `report` and `track` (null on a debt naming none).
	 */
	public static function owed( $user_id ) {
		$owed = get_user_meta( (int) $user_id, self::META_OWED, true );

		if ( ! is_array( $owed ) || empty( $owed['at'] ) ) {
			return array();
		}

		return array(
			'at'     => (int) $owed['at'],
			'by'     => isset( $owed['by'] ) ? (int) $owed['by'] : 0,
			'as'     => isset( $owed['as'] ) && self::AS_ADMINISTRATOR === $owed['as'] ? self::AS_ADMINISTRATOR : self::AS_STUDENT,
			'reason' => isset( $owed['reason'] ) ? (string) $owed['reason'] : '',
			'tried'  => isset( $owed['tried'] ) ? (int) $owed['tried'] : 0,
			'kind'   => isset( $owed['kind'] ) && in_array( $owed['kind'], array( self::KIND_NO_ROW, self::KIND_OTHER_EMAIL ), true ) ? (string) $owed['kind'] : self::KIND_RETRY,
			'report' => array_key_exists( 'report', $owed ) && is_scalar( $owed['report'] ) ? (string) $owed['report'] : null,
			'track'  => array_key_exists( 'track', $owed ) && is_scalar( $owed['track'] ) ? (string) $owed['track'] : null,
		);
	}

	/**
	 * Every account that owes a Pending graduation: one query, the accounts' meta primed with it.
	 *
	 * @return array<int, array> Account ID => what it owes.
	 */
	public static function owing() {
		$out = array();

		foreach ( (array) get_users(
			array(
				'meta_key' => self::META_OWED,
				'fields'   => 'all_with_meta',
				'number'   => -1,
			)
		) as $user ) {
			$id   = is_object( $user ) ? (int) $user->ID : (int) $user;
			$owed = $id > 0 ? self::owed( $id ) : array();

			if ( ! empty( $owed ) ) {
				$out[ $id ] = $owed;
			}
		}

		return $out;
	}

	/**
	 * Whether an account's program status, as the students sync last wrote it, is a track.
	 *
	 * @param int $user_id The account.
	 * @return bool
	 */
	public static function on_track( $user_id ) {
		$program = get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_PROGRAM, true );

		return is_array( $program ) && isset( $program['program'] ) && is_scalar( $program['program'] )
			&& WPCPM_Program::is_track( trim( (string) $program['program'] ) );
	}

	/**
	 * The Students rows the roster index joins to an account: every roster the counts name, then the rows that name no
	 * institution. `SPAM` and `Duplicated` rows are no student's.
	 *
	 * @param int $user_id The account.
	 * @return array<string, array> Students record ID => `institution` (an Institutions record ID, or '') and `status`.
	 */
	public static function rows_of( $user_id ) {
		$user_id = (int) $user_id;
		$sources = array();
		$out     = array();

		foreach ( array_keys( WPCPM_Roster_Index::counts()['institutions'] ) as $institution ) {
			$sources[ (string) $institution ] = WPCPM_Roster_Index::rows( (string) $institution );
		}

		$sources[''] = WPCPM_Roster_Index::unlinked();

		foreach ( $sources as $institution => $rows ) {
			foreach ( (array) $rows as $record => $row ) {
				$status = is_array( $row ) && isset( $row['status'] ) ? trim( (string) $row['status'] ) : '';

				if ( ! is_array( $row ) || $user_id < 1 || (int) ( isset( $row['user_id'] ) ? $row['user_id'] : 0 ) !== $user_id
					|| ! WPCPM_Mentors_Sync::is_record_id( $record ) || in_array( $status, WPCPM_Roster_Index::NEVER_SHOWN, true ) ) {
					continue;
				}

				$out[ (string) $record ] = array(
					'institution' => (string) $institution,
					'status'      => $status,
				);
			}
		}

		return $out;
	}

	/**
	 * The reason a student with no Students row owes the status.
	 *
	 * @return string
	 */
	public static function reason_no_row() {
		return __( 'The site holds no Students row for this student.', 'wpcredits-program-manager' );
	}

	/**
	 * The reason a row that no longer carries the student's address is not written.
	 *
	 * @return string
	 */
	private static function reason_other() {
		return __( 'The Students row on a track holds an email address other than this student\'s, so it was not written.', 'wpcredits-program-manager' );
	}

	/**
	 * The reason a debt stays after a write that landed, when the site could not keep its own marker of it.
	 *
	 * @return string
	 */
	private static function reason_unmarked() {
		return __( 'The status was set in the program\'s records, but the site could not record that it set it: the next students sync looks again.', 'wpcredits-program-manager' );
	}

	/**
	 * The reason a save with no time left owes the write to the students sync.
	 *
	 * @return string
	 */
	private static function reason_time() {
		return __( 'The program\'s records were slow to answer, so the students sync sets the status instead.', 'wpcredits-program-manager' );
	}

	/**
	 * The Students table's status column, by the name the base gives it.
	 *
	 * @return string
	 */
	private static function column() {
		$fields = WPCPM_Mentors_Sync::fields();

		return isset( $fields['student_status'] ) && is_string( $fields['student_status'] ) ? $fields['student_status'] : '';
	}

	/**
	 * Put a status on the roster index's row now, the way the next students sync would.
	 *
	 * @param string $institution The roster holding the row, or '' for the rows that name no institution.
	 * @param string $record      Students record ID.
	 * @param string $status      The status.
	 */
	private static function index( $institution, $record, $status ) {
		if ( '' === (string) $institution ) {
			WPCPM_Roster_Index::update_unlinked( $record, array( 'status' => $status ) );

			return;
		}

		WPCPM_Roster_Index::update( $institution, $record, array( 'status' => $status ) );
	}

	/**
	 * After a write: record it on the account (`META_SET`), read the marker back, and only then clear what was owed;
	 * then keep the course and the day the form was finished for the card's "Waiting since", and write the audit row.
	 *
	 * The write has landed, so nothing after the marker can make a debt: each later step has its own guard. The marker
	 * is read back because `update_user_meta()` answers false both on a database fault and on an unchanged value.
	 *
	 * @param int    $user_id  The student.
	 * @param int    $by       Who saved the form.
	 * @param string $role     `student` or `administrator`.
	 * @param array  $tracked  Students record ID => the track the records held it at.
	 * @param array  $program  The student's program row: its reports record and the track they finished.
	 * @param string $via      `form` or `sync`.
	 * @param int    $finished When the form was finished.
	 * @param array  $found    The record IDs whose status was found already at Pending graduation rather than written
	 *                         in this request: the site did not see its own write land, and somebody else may have set
	 *                         it, so their audit rows say so.
	 * @return bool Whether the marker holds this program's record and track: false keeps the debt.
	 */
	private static function settled( $user_id, $by, $role, array $tracked, array $program, $via, $finished, array $found = array() ) {
		$finished = (int) $finished;
		$course   = WPCPM_Students_Sync::status_of( $program );
		$report   = self::report_of( $program );

		try {
			update_user_meta(
				$user_id,
				self::META_SET,
				array(
					'at'       => time(),
					'finished' => $finished,
					'by'       => (int) $by,
					'as'       => $role,
					'records'  => array_keys( $tracked ),
					'via'      => $via,
					'report'   => $report,
					'track'    => $course,
				)
			);

			$marker = get_user_meta( $user_id, self::META_SET, true );
		} catch ( \Throwable $fault ) {
			$marker = null;
		}

		if ( ! is_array( $marker ) || ! isset( $marker['report'], $marker['track'] ) || $report !== (string) $marker['report'] || $course !== (string) $marker['track'] ) {
			return false;
		}

		try {
			delete_user_meta( $user_id, self::META_OWED );
		} catch ( \Throwable $fault ) {
			unset( $fault );
		}

		try {
			// The course the card names beside the status, as the students sync keeps it (`META_COURSE`).
			if ( WPCPM_Program::is_track( $course ) ) {
				update_user_meta( $user_id, WPCPM_Students_Sync::META_COURSE, $course );
			}

			// The card's "Waiting since": the day the form was finished, kept by the students sync after.
			$held = get_user_meta( $user_id, WPCPM_Students_Sync::META_PENDING, true );

			if ( ! is_array( $held ) || empty( $held['at'] ) || (int) $held['at'] > $finished ) {
				update_user_meta(
					$user_id,
					WPCPM_Students_Sync::META_PENDING,
					array(
						'at'     => $finished,
						'before' => false,
					)
				);
			}
		} catch ( \Throwable $fault ) {
			unset( $fault );
		}

		if ( ! class_exists( 'WPCPM_Institution_Audit' ) ) {
			return true;
		}

		foreach ( $tracked as $record => $from ) {
			try {
				WPCPM_Institution_Audit::record_program(
					array(
						'kind'     => self::KIND_LOG,
						'subject'  => (string) $record,
						'actor'    => (int) $by,
						'ground'   => WPCPM_Institution_Audit::GROUND_SYSTEM,
						'evidence' => WPCPM_Institution_Audit::EVIDENCE_LIVE,
						'message'  => in_array( (string) $record, array_map( 'strval', $found ), true )
							? sprintf(
								/* translators: 1: the status before the change, 2: the status after it, which the site found already set rather than wrote. */
								__( 'Status: %1$s to %2$s (found already set in the program\'s records).', 'wpcredits-program-manager' ),
								$from,
								WPCPM_Program::STATUS_PENDING
							)
							: sprintf(
								/* translators: 1: the status before the change, 2: the status after it. */
								__( 'Status: %1$s to %2$s.', 'wpcredits-program-manager' ),
								$from,
								WPCPM_Program::STATUS_PENDING
							),
						'data'     => array(
							'field'           => self::column(),
							'from'            => $from,
							'to'              => WPCPM_Program::STATUS_PENDING,
							'students_record' => (string) $record,
							'as'              => $role,
						),
					)
				);
			} catch ( \Throwable $fault ) {
				unset( $fault );
			}
		}

		return true;
	}

	/**
	 * Stamp what an account owes, keeping the time the form was finished across attempts.
	 *
	 * @param int    $user_id The student.
	 * @param int    $by      Who saved the form.
	 * @param string $role    `student` or `administrator`.
	 * @param string $reason  What the records or the site said.
	 * @param string $kind    Which line the card draws (`KIND_RETRY`, `KIND_NO_ROW`, `KIND_OTHER_EMAIL`).
	 */
	private static function owe( $user_id, $by, $role, $reason, $kind = self::KIND_RETRY ) {
		$held = self::owed( $user_id );

		// What the debt is for: kept from the first stamp, else the program the account is on now.
		if ( ! empty( $held ) && null !== $held['report'] && null !== $held['track'] ) {
			$report = $held['report'];
			$track  = $held['track'];
		} else {
			$program = WPCPM_Students_Sync::get_program( $user_id );
			$report  = self::report_of( $program );
			$track   = WPCPM_Students_Sync::status_of( $program );
		}

		update_user_meta(
			$user_id,
			self::META_OWED,
			array(
				'at'     => ! empty( $held['at'] ) ? (int) $held['at'] : time(),
				'by'     => (int) $by,
				'as'     => $role,
				'reason' => (string) $reason,
				'tried'  => time(),
				'kind'   => (string) $kind,
				'report' => $report,
				'track'  => $track,
			)
		);
	}
}
