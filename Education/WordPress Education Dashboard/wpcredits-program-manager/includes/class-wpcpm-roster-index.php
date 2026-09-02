<?php
/**
 * The per-institution roster index.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One option per institution holding that institution's Students rows, plus the two
 * program-wide ones: the rows that name no institution, and the counts.
 *
 * Written by the students sync's `finish()` and by nothing else during a sync (design
 * spec section 8.1). A `WP_User_Query` cannot return a student who has no account, and
 * the institution side needs exactly those: the imported row whose account does not
 * exist yet, the applicant who never started, the graduate whose account was never
 * made. So the roster is the Students table, read once per run into these options, and
 * every institution-side surface reads the index and prints its read time rather than
 * paging Airtable per render.
 *
 * The rows hold what the roster needs and nothing a student told the program in
 * confidence: no accessibility disclosure, no free text. `clean()` enforces that at
 * every write, so a caller that hands over a wider row cannot widen the index.
 */
class WPCPM_Roster_Index {

	/** One option per institution: the prefix followed by the Institutions record ID. */
	const OPTION_PREFIX = 'wpcpm_roster_';

	/** The rows that name no institution. Manager screen only. */
	const OPTION_UNLINKED = 'wpcpm_roster_unlinked';

	/** Participation per institution per cohort, and the reconciliation summary. */
	const OPTION_COUNTS = 'wpcpm_roster_counts';

	/** Envelope version; an option written by another version is discarded on read. */
	const VERSION = 1;

	/**
	 * The keys a row holds, in the order they are stored.
	 *
	 * Anything else a caller passes is dropped, which is the point: the Students table
	 * also carries `Accessibility needs` and `Notes`, and neither may reach an index an
	 * institution reads from.
	 */
	const KEYS = array(
		'record_id',
		'name',
		'email',
		'email_key',
		'status',
		'institution',
		'start',
		'end',
		'has_mentor',
		'username',
		'field_of_study',
		'tutor',
		'import_key',
		'reports',
		'user_id',
	);

	/**
	 * The option name for one institution.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	public static function option_name( $record_id ) {
		return self::OPTION_PREFIX . trim( (string) $record_id );
	}

	/**
	 * The empty envelope, for a roster that has never been written.
	 *
	 * @return array{v: int, read: int, rows: array}
	 */
	private static function empty_shape() {
		return array(
			'v'    => self::VERSION,
			'read' => 0,
			'rows' => array(),
		);
	}

	/**
	 * Read one stored envelope, or the empty shape when it is absent or from another version.
	 *
	 * @param string $option Option name.
	 * @return array{v: int, read: int, rows: array}
	 */
	private static function read_option( $option ) {
		$stored = get_option( $option );

		if ( ! is_array( $stored ) || ! isset( $stored['v'] ) || self::VERSION !== (int) $stored['v'] ) {
			return self::empty_shape();
		}

		return array(
			'v'    => self::VERSION,
			'read' => isset( $stored['read'] ) ? (int) $stored['read'] : 0,
			'rows' => isset( $stored['rows'] ) && is_array( $stored['rows'] ) ? $stored['rows'] : array(),
		);
	}

	/**
	 * One institution's roster envelope.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return array{v: int, read: int, rows: array} Rows keyed by Students record ID.
	 */
	public static function read( $record_id ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return self::empty_shape();
		}

		return self::read_option( self::option_name( $record_id ) );
	}

	/**
	 * One institution's rows.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return array Rows keyed by Students record ID.
	 */
	public static function rows( $record_id ) {
		return self::read( $record_id )['rows'];
	}

	/**
	 * The rows that name no institution.
	 *
	 * @return array Rows keyed by Students record ID.
	 */
	public static function unlinked() {
		return self::read_option( self::OPTION_UNLINKED )['rows'];
	}

	/**
	 * Participation per institution per cohort, and the reconciliation summary.
	 *
	 * @return array{v: int, read: int, institutions: array, reconciliation: array}
	 */
	public static function counts() {
		$stored = get_option( self::OPTION_COUNTS );

		if ( ! is_array( $stored ) || ! isset( $stored['v'] ) || self::VERSION !== (int) $stored['v'] ) {
			$stored = array();
		}

		return array(
			'v'              => self::VERSION,
			'read'           => isset( $stored['read'] ) ? (int) $stored['read'] : 0,
			'institutions'   => isset( $stored['institutions'] ) && is_array( $stored['institutions'] ) ? $stored['institutions'] : array(),
			'reconciliation' => isset( $stored['reconciliation'] ) && is_array( $stored['reconciliation'] ) ? $stored['reconciliation'] : array(),
		);
	}

	/**
	 * Write every option from one completed sync.
	 *
	 * The only writer during a sync, so a run that fails part-way leaves last run's
	 * options in place rather than a half-written index. An institution that had rows last
	 * run and has none now is written empty with this run's read time, not deleted: a
	 * roster that reads "0 students as of today" is the truth, and one that reads "never
	 * read" is not.
	 *
	 * @param array $by_institution Rows grouped by Institutions record ID, each keyed by Students record ID.
	 * @param array $unlinked       Rows with no institution, keyed by Students record ID.
	 * @param array $counts         Institution => cohort key => participation buckets.
	 * @param array $reconciliation The reconciliation summary for the manager screen.
	 * @param int   $read           Unix time the Students table was read.
	 */
	public static function write_all( array $by_institution, array $unlinked, array $counts, array $reconciliation, $read ) {
		$read    = (int) $read;
		$written = array();

		foreach ( $by_institution as $record_id => $rows ) {
			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			$record_id = trim( (string) $record_id );

			update_option(
				self::option_name( $record_id ),
				array(
					'v'    => self::VERSION,
					'read' => $read,
					'rows' => self::clean_rows( is_array( $rows ) ? $rows : array() ),
				),
				false
			);

			$written[ $record_id ] = true;
		}

		// Last run's institutions, from the counts option, so a stale roster is emptied
		// without a LIKE query over the options table on every run.
		foreach ( array_keys( self::counts()['institutions'] ) as $record_id ) {
			if ( isset( $written[ $record_id ] ) || ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			update_option(
				self::option_name( $record_id ),
				array(
					'v'    => self::VERSION,
					'read' => $read,
					'rows' => array(),
				),
				false
			);
		}

		update_option(
			self::OPTION_UNLINKED,
			array(
				'v'    => self::VERSION,
				'read' => $read,
				'rows' => self::clean_rows( $unlinked ),
			),
			false
		);

		update_option(
			self::OPTION_COUNTS,
			array(
				'v'              => self::VERSION,
				'read'           => $read,
				'institutions'   => $counts,
				'reconciliation' => $reconciliation,
			),
			false
		);
	}

	/**
	 * Add or replace one row on one institution's roster, keeping its read time.
	 *
	 * For the import path, so a student created a moment ago is on the roster now rather
	 * than after the next sync. The read time is the sync's, deliberately: the rest of the
	 * roster is still as old as it was.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param array  $row       Row in the index shape; `record_id` is the Students record ID.
	 */
	public static function insert( $record_id, array $row ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return;
		}

		$row = self::clean( $row );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $row['record_id'] ) ) {
			return;
		}

		$stored = self::read( $record_id );

		$stored['rows'][ $row['record_id'] ] = $row;

		update_option( self::option_name( $record_id ), $stored, false );
	}

	/**
	 * Remove every roster option. Called on uninstall.
	 *
	 * The per-institution options are named by prefix rather than enumerable, so the names
	 * are read with a LIKE and each is deleted through `delete_option()`, which also drops
	 * it from the object cache; a raw DELETE would leave a cached copy behind on a host
	 * with a persistent cache. The two named options start with the same prefix, and are
	 * deleted by name as well so that a prefix change never orphans them.
	 */
	public static function delete_all() {
		global $wpdb;

		$names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::OPTION_PREFIX ) . '%'
			)
		);

		foreach ( (array) $names as $name ) {
			delete_option( (string) $name );
		}

		delete_option( self::OPTION_UNLINKED );
		delete_option( self::OPTION_COUNTS );
	}

	/**
	 * Clean a set of rows, re-keyed by Students record ID.
	 *
	 * @param array $rows Rows in any key order.
	 * @return array
	 */
	private static function clean_rows( array $rows ) {
		$out = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$row = self::clean( $row );

			// A row is keyed by its record ID; one that is not a record ID cannot be
			// found again, joined, or inserted over, so it is not a row.
			if ( ! WPCPM_Mentors_Sync::is_record_id( $row['record_id'] ) ) {
				continue;
			}

			$out[ $row['record_id'] ] = $row;
		}

		return $out;
	}

	/**
	 * A row reduced to the index shape, every key present and typed.
	 *
	 * @param array $row Row from the sync or from an import.
	 * @return array
	 */
	private static function clean( array $row ) {
		$out = array();

		foreach ( self::KEYS as $key ) {
			$value = isset( $row[ $key ] ) ? $row[ $key ] : null;

			switch ( $key ) {
				case 'has_mentor':
					$out[ $key ] = (bool) $value;
					break;

				case 'user_id':
					$out[ $key ] = (int) $value;
					break;

				case 'reports':
					$ids = array();

					foreach ( is_array( $value ) ? $value : array() as $id ) {
						if ( WPCPM_Mentors_Sync::is_record_id( $id ) ) {
							$ids[] = trim( (string) $id );
						}
					}

					$out[ $key ] = array_values( array_unique( $ids ) );
					break;

				default:
					$out[ $key ] = is_scalar( $value ) ? trim( (string) $value ) : '';
					break;
			}
		}

		// The key derives from the address; a caller that sends one without the other still
		// gets a row the email join can find. Lowercased here whichever way it arrived, because
		// the join is a comparison of these keys and one row in the wrong case is a student who
		// silently belongs to nobody.
		if ( '' === $out['email_key'] && '' !== $out['email'] ) {
			$out['email_key'] = $out['email'];
		}

		$out['email_key'] = strtolower( $out['email_key'] );

		return $out;
	}
}
