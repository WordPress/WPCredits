<?php
/**
 * The check the mentors and students syncs make before they take anybody's role.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a sync may go on to its revoke step, from two counts (LOG-01, 1.123.58).
 *
 * The mentors sync and the students sync take the role from every linked account the run did not
 * read. A read that came back with nothing, or with far fewer than the site holds (a status renamed in
 * the shared base, a status option deleted, a formula column renamed), would take it from everybody
 * within three hours. So before the revoke step each sync compares how many records the run read with
 * how many accounts are linked to a record and marked active, which is how many the last completed
 * runs left linked plus any this run linked:
 *
 * - nothing linked is nothing to take, and the run goes on whatever it read;
 * - a read of no records stops the run;
 * - a read under half of the linked accounts stops it; exactly half goes on;
 * - a run an Administrator accepted (Run anyway on the Sync tab, `--accept` on the command line)
 *   goes on, for that run only.
 *
 * A stop keeps every account: the sync returns the error, its tick keeps the state for Run anyway,
 * and the Sync tab and the command line print the sentence, which names both counts. The two codes
 * are never retried on their own (`is_stop()`): the counts will not change until somebody looks.
 */
final class WPCPM_Sync_Guard {

	/** The run read no records while accounts are linked. */
	const CODE_EMPTY = 'wpcpm_sync_read_empty';

	/** The run read under half as many records as there are linked accounts. */
	const CODE_FELL = 'wpcpm_sync_read_fell';

	/**
	 * Whether the run may take roles.
	 *
	 * @param string $who      'mentors' or 'students': whose sync asks, for the sentence.
	 * @param int    $read     How many records in scope the run read.
	 * @param int    $linked   How many accounts are linked to a record and marked active.
	 * @param bool   $accepted Whether an Administrator accepted this run's counts.
	 * @return true|WP_Error
	 */
	public static function check( $who, $read, $linked, $accepted = false ) {
		$read   = max( 0, (int) $read );
		$linked = max( 0, (int) $linked );

		if ( $accepted || 0 === $linked ) {
			return true;
		}

		if ( 0 === $read ) {
			return new WP_Error( self::CODE_EMPTY, self::sentence( $who, $read, $linked ), self::data( $who, $read, $linked ) );
		}

		if ( $read * 2 < $linked ) {
			return new WP_Error( self::CODE_FELL, self::sentence( $who, $read, $linked ), self::data( $who, $read, $linked ) );
		}

		return true;
	}

	/**
	 * Whether an error is one of this guard's stops, which nothing retries on its own.
	 *
	 * @param mixed $error What a phase returned.
	 * @return bool
	 */
	public static function is_stop( $error ) {
		return is_wp_error( $error ) && in_array( $error->get_error_code(), array( self::CODE_EMPTY, self::CODE_FELL ), true );
	}

	/**
	 * What a stop carries for the Sync tab: whose sync, and the two counts.
	 *
	 * @param string $who    'mentors' or 'students'.
	 * @param int    $read   Records read.
	 * @param int    $linked Accounts linked and active.
	 * @return array{who: string, read: int, linked: int}
	 */
	private static function data( $who, $read, $linked ) {
		return array(
			'who'    => 'students' === $who ? 'students' : 'mentors',
			'read'   => (int) $read,
			'linked' => (int) $linked,
		);
	}

	/**
	 * The sentence a stop says, naming both counts: two sentences, each with its own plural.
	 *
	 * @param string $who    'mentors' or 'students'.
	 * @param int    $read   Records read.
	 * @param int    $linked Accounts linked and active.
	 * @return string
	 */
	public static function sentence( $who, $read, $linked ) {
		$read   = (int) $read;
		$linked = (int) $linked;

		if ( 'students' === $who ) {
			$kept = sprintf(
				/* translators: %s: how many accounts are linked to a student's record. */
				_n( 'That is under half of the %s account linked to a student\'s record, so the students sync stopped before taking anyone\'s Student role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.', 'That is under half of the %s accounts linked to a student\'s record, so the students sync stopped before taking anyone\'s Student role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.', $linked, 'wpcredits-program-manager' ),
				number_format_i18n( $linked )
			);

			return self::found( $who, $read ) . ' ' . $kept;
		}

		$kept = sprintf(
			/* translators: %s: how many accounts are linked to a mentor's record. */
			_n( 'That is under half of the %s account linked to a mentor\'s record, so the mentors sync stopped before taking anyone\'s Mentor role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.', 'That is under half of the %s accounts linked to a mentor\'s record, so the mentors sync stopped before taking anyone\'s Mentor role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.', $linked, 'wpcredits-program-manager' ),
			number_format_i18n( $linked )
		);

		return self::found( $who, $read ) . ' ' . $kept;
	}

	/**
	 * The stop of the mentors sync as `wp wpcredits sync_mentors` says it: the same first sentence, and
	 * a second that names --accept where the Sync tab's names Run anyway.
	 *
	 * @param int $read   Mentors read.
	 * @param int $linked Accounts linked and active.
	 * @return string
	 */
	public static function command_sentence( $read, $linked ) {
		$linked = (int) $linked;
		$kept   = sprintf(
			/* translators: %s: how many accounts are linked to a mentor's record. */
			_n( 'That is under half of the %s account linked to a mentor\'s record, so the mentors sync stopped before taking anyone\'s Mentor role, and every account was kept. If the base really holds that few now, run the command again with --accept.', 'That is under half of the %s accounts linked to a mentor\'s record, so the mentors sync stopped before taking anyone\'s Mentor role, and every account was kept. If the base really holds that few now, run the command again with --accept.', $linked, 'wpcredits-program-manager' ),
			number_format_i18n( $linked )
		);

		return self::found( 'mentors', (int) $read ) . ' ' . $kept;
	}

	/**
	 * What a stopped run's state says of its stop, for `progress()`: the two counts and the stop's
	 * sentence, or null when the state holds no stop.
	 *
	 * @param array  $state The run's state.
	 * @param string $who   'students' or 'mentors'.
	 * @return array{read: int, linked: int, sentence: string}|null
	 */
	public static function stop_in( array $state, $who ) {
		if ( empty( $state['guard'] ) || ! is_array( $state['guard'] ) ) {
			return null;
		}

		$read   = isset( $state['guard']['read'] ) ? (int) $state['guard']['read'] : 0;
		$linked = isset( $state['guard']['linked'] ) ? (int) $state['guard']['linked'] : 0;

		return array(
			'read'     => $read,
			'linked'   => $linked,
			'sentence' => self::sentence( $who, $read, $linked ),
		);
	}

	/**
	 * The stop's first sentence: how many records Airtable returned, with its zero and its singular.
	 *
	 * @param string $who  'mentors' or 'students'.
	 * @param int    $read Records read.
	 * @return string
	 */
	private static function found( $who, $read ) {
		if ( 'students' === $who ) {
			return 0 === $read
				? __( 'Airtable returned no current students.', 'wpcredits-program-manager' )
				/* translators: %s: how many current students Airtable returned. */
				: sprintf( _n( 'Airtable returned %s current student.', 'Airtable returned %s current students.', $read, 'wpcredits-program-manager' ), number_format_i18n( $read ) );
		}

		return 0 === $read
			? __( 'Airtable returned no mentors.', 'wpcredits-program-manager' )
			/* translators: %s: how many mentors Airtable returned. */
			: sprintf( _n( 'Airtable returned %s mentor.', 'Airtable returned %s mentors.', $read, 'wpcredits-program-manager' ), number_format_i18n( $read ) );
	}
}
