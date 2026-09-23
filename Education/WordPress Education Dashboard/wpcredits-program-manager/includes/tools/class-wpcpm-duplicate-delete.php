<?php
/**
 * Student Duplicate Finder: the delete.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One confirmed delete, from a posted selection to the outcome the screen prints.
 *
 * The tool's handler checks the capability and the nonce and then hands the selection here, so
 * the whole of spec 7.3 runs, and is tested, without a request. The order is the spec's:
 *
 * 1. the switch is on, no scan is running, and the site can seal; from here to the end one delete
 *    runs at a time, under a lock (DUPLICATES-1);
 * 2. the selection is expanded against the stored report again;
 * 3. the selected addresses are read from Airtable again, in the tables a row was chosen from, so
 *    the chosen rows and their siblings come back together;
 * 4. the site references are read from the database again, and `WPCPM_Duplicate_Rules::recheck()`
 *    asks every question again: **the stored report is the menu, not the authority** (3.6); a
 *    read of the references that fails deletes nothing (DUPLICATES-3);
 * 5. a sealed copy of each row that passed is kept, pending, before anything is deleted, and a copy
 *    that cannot be kept stops the delete with nothing deleted (3.7); a row's older copy still
 *    pending from a refused batch goes, since the re-read found that row still there
 *    (DUPLICATES-5);
 * 6. the rows are deleted ten at a time, Feedback first, then Students Reports, then Students (3.8);
 * 7. each copy whose row Airtable confirms becomes the log entry; a copy of a row that was sent and
 *    not confirmed stays pending for the daily job; a copy of a row never sent is removed (7.5);
 * 8. the stored report drops the deleted rows at once.
 */
final class WPCPM_Duplicate_Delete {

	/**
	 * The option a delete holds from the report read to the report written (DUPLICATES-1).
	 *
	 * `WPCPM_Duplicate_Rules::recheck()` counts an address's rows against its own request's
	 * re-read, so two confirmations that each tick one of a student's two rows, processed at the
	 * same time, would each see the other row standing and together leave that student no row in
	 * the table, which spec 5.7 rules out. A double-submitted Delete would run twice the same way.
	 */
	const OPT_LOCK = 'wpcpm_duplicates_delete_lock';

	/**
	 * How long a lock is honored before a delete that never let it go is treated as dead.
	 *
	 * Longer than a slow delete, which the scan's 120 seconds is not: three re-reads and ten
	 * batches of ten, each allowed the client's 20-second timeout (DUPLICATES-1). Not a bound on
	 * every delete: a re-read may page up to twenty times a table, and the client paces its
	 * requests, so a delete can outlast it and have its lock taken over.
	 */
	const LOCK_TIMEOUT = 300;

	/**
	 * Run a delete.
	 *
	 * @param string[] $keys  Posted student keys.
	 * @param string[] $pairs Posted `table:record` pairs.
	 * @param int      $actor The manager deleting.
	 * @return array The outcome: `status`, and `deleted` (table => n), `refused`, `detail` and
	 *               `until` where they apply.
	 */
	public static function run( array $keys, array $pairs, $actor ) {
		$settings = WPCPM_Settings::get();

		if ( empty( $settings['duplicate_delete_enabled'] ) ) {
			return array( 'status' => 'switched-off' );
		}

		// A scan is about to write a new list over the one this selection was read from.
		if ( WPCPM_Duplicates_Scan::is_running() ) {
			return array( 'status' => 'scan-running' );
		}

		if ( ! WPCPM_Secret::can_encrypt() ) {
			return array( 'status' => 'no-seal' );
		}

		// Refused before anything is read, and without touching the lock the other delete holds.
		if ( ! self::acquire_lock() ) {
			return array( 'status' => 'delete-running' );
		}

		// Released however the delete ends, since it answers from several places below.
		try {
			return self::run_locked( $keys, $pairs, $actor, $settings );
		} finally {
			delete_option( self::OPT_LOCK );
		}
	}

	/**
	 * Everything a delete does once it holds the lock: steps 2 to 8 of the class's list.
	 *
	 * @param string[] $keys     Posted student keys.
	 * @param string[] $pairs    Posted `table:record` pairs.
	 * @param int      $actor    The manager deleting.
	 * @param array    $settings Plugin settings.
	 * @return array The outcome, as `run()` answers it.
	 */
	private static function run_locked( array $keys, array $pairs, $actor, array $settings ) {
		$report = WPCPM_Duplicates_Scan::report();
		$chosen = WPCPM_Duplicate_Rules::expand( $report, $keys, $pairs );

		if ( empty( $chosen['rows'] ) ) {
			return array(
				'status'  => 'nothing',
				'refused' => $chosen['dropped'],
			);
		}

		$airtable = new WPCPM_Airtable( $settings );
		$context  = WPCPM_Duplicates_Scan::context();
		$live     = self::read_live( $airtable, $settings, $report, $chosen['rows'], $context );

		if ( is_wp_error( $live ) ) {
			return array(
				'status' => 'read-failed',
				'detail' => $live->get_error_message(),
			);
		}

		$ids = array();
		foreach ( $live['rows'] as $tables ) {
			foreach ( $tables as $rows ) {
				foreach ( $rows as $row ) {
					$ids[] = $row['id'];
				}
			}
		}

		$refs = WPCPM_Duplicates_Scan::refs_for( $ids );

		// Not "nothing points at these rows": that would let through a row a site account, a call
		// note or an audit entry points at. Answered before any copy is kept (DUPLICATES-3).
		if ( is_wp_error( $refs ) ) {
			return array( 'status' => 'refs-failed' );
		}

		$checked = WPCPM_Duplicate_Rules::recheck( $chosen['rows'], $live['rows'], $refs, $context );
		$refused = array_merge( $chosen['dropped'], $checked['refused'] );

		if ( empty( $checked['go'] ) ) {
			return array(
				'status'  => 'nothing',
				'refused' => $refused,
			);
		}

		$copies = array();

		foreach ( $checked['go'] as $pick ) {
			$copy = WPCPM_Duplicate_Vault::keep( $pick['table'], $live['records'][ $pick['id'] ], (int) $actor, isset( $report['read'] ) ? (int) $report['read'] : 0 );

			if ( is_wp_error( $copy ) ) {
				foreach ( $copies as $made ) {
					WPCPM_Duplicate_Vault::discard( $made );
				}

				return array(
					'status'  => 'copy-failed',
					'detail'  => $copy->get_error_message(),
					'refused' => $refused,
				);
			}

			$copies[ $pick['id'] ] = $copy;
		}

		// The re-read found each of these rows still in Airtable, so a copy of one left pending by
		// an earlier, refused batch is of a delete that never happened (DUPLICATES-5).
		WPCPM_Duplicate_Vault::supersede( $copies );

		$deleted   = array();
		$attempted = array();
		$error     = null;

		foreach ( WPCPM_Duplicate_Rules::DELETE_ORDER as $table ) {
			$batch = array();

			foreach ( $checked['go'] as $pick ) {
				if ( $table === $pick['table'] ) {
					$batch[] = $pick['id'];
				}
			}

			if ( empty( $batch ) ) {
				continue;
			}

			$attempted[ $table ] = true;
			$answer              = $airtable->delete_records( (string) $settings[ WPCPM_Duplicates_Scan::TABLE_SETTINGS[ $table ] ], $batch );

			if ( is_wp_error( $answer ) ) {
				$data = (array) $answer->get_error_data();

				foreach ( isset( $data['deleted'] ) ? (array) $data['deleted'] : array() as $id ) {
					$deleted[ (string) $id ] = true;
				}

				$error = $answer;
				break;
			}

			foreach ( array_keys( $answer ) as $id ) {
				$deleted[ (string) $id ] = true;
			}
		}

		$confirmed = array();
		$gone      = array();

		foreach ( $checked['go'] as $pick ) {
			if ( isset( $deleted[ $pick['id'] ] ) ) {
				$confirmed[] = $copies[ $pick['id'] ];
				$gone[]      = $pick;
			} elseif ( empty( $attempted[ $pick['table'] ] ) ) {
				WPCPM_Duplicate_Vault::discard( $copies[ $pick['id'] ] );
			}
		}

		WPCPM_Duplicate_Vault::confirm( $confirmed );
		WPCPM_Duplicates_Scan::drop_deleted( $gone );

		$tally = array_fill_keys( WPCPM_Duplicate_Rules::TABLES, 0 );
		foreach ( $gone as $pick ) {
			++$tally[ $pick['table'] ];
		}

		return array(
			'status'  => null === $error ? 'deleted' : 'stopped',
			'deleted' => $tally,
			'refused' => $refused,
			'detail'  => null === $error ? '' : $error->get_error_message(),
			'until'   => time() + WPCPM_Duplicate_Vault::KEEP_DAYS * DAY_IN_SECONDS,
		);
	}

	/**
	 * Every row the base holds now for the selected addresses, in the tables a row was chosen from.
	 *
	 * One formula for all the addresses, trimmed and lowercased on both sides, and paged: the
	 * chosen rows and their siblings come back together, which is what the last-row rule needs.
	 *
	 * The scan groups a row by its address trimmed and lowercased (`WPCPM_Duplicate_Rules::key()`),
	 * and Airtable's LOWER() does not trim, so a row typed with a space around the address never
	 * came back and was refused as gone while it was still there (DUPLICATES-4). Hence
	 * `TRIM(LOWER({Email}))` on the base's side, asked of the client's own `formula_in()` with both
	 * of its flags, whose escaping is the client's: this class built the formula itself until the
	 * final fix wave, copying that escaping.
	 *
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @param array          $report   The stored report.
	 * @param array          $picks    `expand()`'s rows.
	 * @param array          $context  The scan's context.
	 * @return array|WP_Error `rows` (key => table => reduced rows) and `records` (ID => record).
	 */
	private static function read_live( WPCPM_Airtable $airtable, array $settings, array $report, array $picks, array $context ) {
		$addresses = array();
		$tables    = array();

		foreach ( $picks as $pick ) {
			if ( isset( $report['groups'][ $pick['key'] ]['email'] ) ) {
				$addresses[ strtolower( trim( (string) $report['groups'][ $pick['key'] ]['email'] ) ) ] = true;
			}
			$tables[ $pick['table'] ] = true;
		}

		$formula = $airtable->formula_in( WPCPM_Duplicate_Rules::EMAIL, array_keys( $addresses ), true, true );
		$rows    = array();
		$records = array();

		foreach ( WPCPM_Duplicate_Rules::TABLES as $table ) {
			if ( empty( $tables[ $table ] ) ) {
				continue;
			}

			$offset = null;
			$pages  = 0;

			do {
				$page = $airtable->fetch_page(
					(string) $settings[ WPCPM_Duplicates_Scan::TABLE_SETTINGS[ $table ] ],
					array(
						'formula' => $formula,
						'offset'  => $offset,
					)
				);

				if ( is_wp_error( $page ) ) {
					return $page;
				}

				foreach ( $page['records'] as $record ) {
					$row = WPCPM_Duplicate_Rules::reduce( $table, (array) $record, $context );
					$key = WPCPM_Duplicate_Rules::key( $row['email'] );

					if ( '' === $key ) {
						continue;
					}

					$rows[ $key ][ $table ][] = $row;
					$records[ $row['id'] ]    = (array) $record;
				}

				$offset = empty( $page['offset'] ) ? null : $page['offset'];
				++$pages;
			} while ( null !== $offset && $pages < 20 );
		}

		return array(
			'rows'    => $rows,
			'records' => $records,
		);
	}

	/**
	 * Claim the right to delete: `add_option()`'s test-and-set, with a stale takeover.
	 *
	 * The house pattern (`WPCPM_Duplicates_Scan::acquire_lock()`). `add_option()` is a read and
	 * then an insert, so it closes the race at the speed of two people pressing Delete, not two
	 * inserts landing in the same millisecond (`WPCPM_Sponsor_Codes::lock()` records the window).
	 *
	 * @return bool Whether this request now holds the lock.
	 */
	private static function acquire_lock() {
		if ( add_option( self::OPT_LOCK, time(), '', false ) ) {
			return true;
		}

		$held = (int) get_option( self::OPT_LOCK );

		if ( $held && ( time() - $held ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		update_option( self::OPT_LOCK, time(), false );

		return true;
	}
}
