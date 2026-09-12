<?php
/**
 * Student Duplicate Finder: the scan.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads Students, Students Reports and Feedback every three hours and keeps the addresses that
 * have more than one row in any of them, with a proposal for every row.
 *
 * Built like the four Airtable syncs, `WPCPM_Sponsors_Sync` above all: a resumable state machine in
 * an option, ticks of a budgeted slice under a lock, the shared three-hour recurrence at an offset
 * of its own, and the progress payload `assets/js/admin.js` polls. It departs from them in one place
 * on purpose: **a failed read ends the run.** A sync that stops on an error keeps its state and
 * stays "running", so the recurring event skips every later run until somebody cancels; this one
 * records the error, drops the working state and keeps the last good report, and the next run
 * starts clean three hours later (spec decision 3.4).
 *
 * It writes nothing to Airtable, and the report it keeps holds duplicates only (decision 3.5).
 */
final class WPCPM_Duplicates_Scan {

	/** The recurring run. */
	const CRON_SCAN = 'wpcpm_duplicates_scan';

	/** The single event that carries a run on between ticks. */
	const CRON_TICK = 'wpcpm_duplicates_tick';

	const OPT_STATE  = 'wpcpm_duplicates_state';
	const OPT_REPORT = 'wpcpm_duplicates_report';
	const OPT_LAST   = 'wpcpm_duplicates_last_scan';
	const OPT_ERROR  = 'wpcpm_duplicates_last_error';
	const OPT_LOCK   = 'wpcpm_duplicates_lock';

	const BUDGET       = 18;
	const BUDGET_AJAX  = 8;
	const LOCK_TIMEOUT = 120;

	/**
	 * Minutes into the three-hour cycle the scan runs at.
	 *
	 * After the students (30), mentors (60), institutions (90) and sponsors (120) syncs, so that no
	 * two of the five share a WP-Cron request. Minutes, and not a constant expression on
	 * `MINUTE_IN_SECONDS`, for the reason `WPCPM_Sponsors_Sync::SCHEDULE_OFFSET_MINUTES` gives.
	 */
	const SCHEDULE_OFFSET_MINUTES = 150;

	/** The report's shape. A stored report of any other version is not read. */
	const REPORT_VERSION = 1;

	/** The setting that names each table. */
	const TABLE_SETTINGS = array(
		'students' => 'students_table',
		'reports'  => 'reports_table',
		'feedback' => 'feedback_table',
	);

	/**
	 * Post types whose meta is the finder's own, and never a reason to keep a row.
	 *
	 * The copies `WPCPM_Duplicate_Vault` keeps carry the record ID they are a copy of. A copy whose
	 * delete Airtable never confirmed is of a row that may still be there, and counting the copy as
	 * the site pointing at that row would lock it against the very delete it was made for. Spelled
	 * out rather than read from the vault's constant, so this class loads without it.
	 */
	const OWN_POST_TYPES = array( 'wpcpm_dup_copy' );

	/**
	 * The phases, their progress-bar weights and the pages each is expected to take.
	 *
	 * Eleven pages a table: a hundred rows a page and a little over a thousand rows in each on
	 * 10 September 2026. The count only shapes the bar; a longer table simply fills its share later.
	 *
	 * @return array<string, array{label: string, weight: int, steps: int}>
	 */
	public static function phases() {
		return array(
			'students' => array(
				'label'  => __( 'Reading Students', 'wpcredits-program-manager' ),
				'weight' => 30,
				'steps'  => 11,
			),
			'reports'  => array(
				'label'  => __( 'Reading Students Reports', 'wpcredits-program-manager' ),
				'weight' => 30,
				'steps'  => 11,
			),
			'feedback' => array(
				'label'  => __( 'Reading Feedback', 'wpcredits-program-manager' ),
				'weight' => 30,
				'steps'  => 11,
			),
			'finish'   => array(
				'label'  => __( 'Finding duplicates', 'wpcredits-program-manager' ),
				'weight' => 10,
				'steps'  => 1,
			),
		);
	}

	/**
	 * Hook the two cron events and make sure the recurring one is on the clock.
	 */
	public static function register_cron() {
		add_action( self::CRON_SCAN, array( __CLASS__, 'cron_scan' ) );
		add_action( self::CRON_TICK, array( __CLASS__, 'run_tick' ) );

		self::schedule();
	}

	/**
	 * Ensure the recurring event exists, on the three-hour recurrence, at this scan's offset.
	 *
	 * The recurrence is checked and not only the event's existence, as `WPCPM_Sponsors_Sync` does,
	 * and the interval is the students sync's, never one of this class's own: an event on a schedule
	 * WordPress cannot find is dropped without a word.
	 */
	public static function schedule() {
		WPCPM_Students_Sync::register_interval();

		$event = wp_get_scheduled_event( self::CRON_SCAN );

		if ( $event && isset( $event->schedule ) && WPCPM_Students_Sync::EVERY_THREE_HOURS === $event->schedule ) {
			return;
		}

		if ( $event ) {
			wp_clear_scheduled_hook( self::CRON_SCAN );
		}

		wp_schedule_event(
			WPCPM_Students_Sync::cycle_start() + ( self::SCHEDULE_OFFSET_MINUTES * MINUTE_IN_SECONDS ),
			WPCPM_Students_Sync::EVERY_THREE_HOURS,
			self::CRON_SCAN
		);
	}

	/**
	 * Activation: the schedule.
	 */
	public static function activate() {
		self::schedule();
	}

	/**
	 * Deactivation: both events off the clock, the working state gone. The report stays.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_SCAN );
		wp_clear_scheduled_hook( self::CRON_TICK );
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
	}

	/**
	 * Uninstall: everything the scan keeps, the report of duplicated students included.
	 */
	public static function uninstall() {
		self::deactivate();

		foreach ( array( self::OPT_REPORT, self::OPT_LAST, self::OPT_ERROR ) as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * The recurring run.
	 */
	public static function cron_scan() {
		if ( self::is_running() ) {
			return;
		}

		self::start();
	}

	/**
	 * Begin a run.
	 *
	 * @param bool $run_first_tick Process one slice before returning, for WP-CLI.
	 * @return true|WP_Error
	 */
	public static function start( $run_first_tick = false ) {
		if ( ! WPCPM_Settings::is_connected() ) {
			$error = new WP_Error( 'wpcpm_not_connected', __( 'Add an Airtable Personal Access Token and Base ID before scanning.', 'wpcredits-program-manager' ) );
			update_option( self::OPT_ERROR, $error->get_error_message(), false );

			return $error;
		}

		delete_option( self::OPT_LOCK );

		update_option(
			self::OPT_STATE,
			array(
				'phase'   => 'students',
				'offset'  => null,
				'started' => time(),
				'touched' => time(),
				'steps'   => array(),
				// Read once, so one run classifies every row against the same statuses and columns.
				'context' => self::context(),
				// Address key => table => reduced rows, as the pages arrive.
				'rows'    => array(),
				'stats'   => self::empty_stats(),
			),
			false
		);

		delete_option( self::OPT_ERROR );

		if ( ! wp_next_scheduled( self::CRON_TICK ) ) {
			wp_schedule_single_event( time() + 10, self::CRON_TICK );
		}

		if ( $run_first_tick ) {
			self::run_tick();
		}

		return true;
	}

	/**
	 * Stop a run. The last good report stays.
	 */
	public static function cancel() {
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * Whether a run is in progress.
	 *
	 * @return bool
	 */
	public static function is_running() {
		$state = get_option( self::OPT_STATE );

		return is_array( $state ) && ! empty( $state['phase'] ) && 'done' !== $state['phase'];
	}

	/**
	 * When the last run finished, unix time, or 0.
	 *
	 * @return int
	 */
	public static function last_read() {
		return (int) get_option( self::OPT_LAST, 0 );
	}

	/**
	 * The last good report, or an empty array when there is none of this version.
	 *
	 * @return array
	 */
	public static function report() {
		$report = get_option( self::OPT_REPORT );

		return ( is_array( $report ) && isset( $report['v'] ) && self::REPORT_VERSION === (int) $report['v'] ) ? $report : array();
	}

	/**
	 * Process one slice of work.
	 *
	 * @param int|null $budget Seconds of work to attempt.
	 */
	public static function run_tick( $budget = null ) {
		$state = get_option( self::OPT_STATE );

		if ( ! is_array( $state ) || empty( $state['phase'] ) || 'done' === $state['phase'] ) {
			return;
		}

		if ( ! self::acquire_lock() ) {
			return;
		}

		$budget   = ( null === $budget ) ? self::BUDGET : max( 1, (int) $budget );
		$deadline = microtime( true ) + $budget;
		$airtable = new WPCPM_Airtable();
		$settings = WPCPM_Settings::get();

		while ( microtime( true ) < $deadline && 'done' !== $state['phase'] ) {
			$before = $state['phase'];

			if ( 'finish' === $state['phase'] ) {
				$result = self::phase_finish( $state );
			} elseif ( isset( self::TABLE_SETTINGS[ $state['phase'] ] ) ) {
				$result = self::phase_read( $state, $airtable, $settings );
			} else {
				$state['phase'] = 'done';
				$result         = true;
			}

			if ( ! isset( $state['steps'][ $before ] ) ) {
				$state['steps'][ $before ] = 0;
			}
			++$state['steps'][ $before ];
			$state['touched'] = time();

			if ( is_wp_error( $result ) ) {
				self::fail( $result );

				return;
			}

			update_option( self::OPT_STATE, $state, false );
		}

		self::release_lock();

		if ( 'done' === $state['phase'] ) {
			self::finish();

			return;
		}

		if ( ! wp_next_scheduled( self::CRON_TICK ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_TICK );
		}
	}

	/**
	 * Progress for the screen and the AJAX poll: the keys assets/js/admin.js reads.
	 *
	 * @return array
	 */
	public static function progress() {
		$state   = get_option( self::OPT_STATE );
		$state   = is_array( $state ) ? $state : array();
		$phase   = isset( $state['phase'] ) ? (string) $state['phase'] : '';
		$phases  = self::phases();
		$stats   = isset( $state['stats'] ) && is_array( $state['stats'] ) ? $state['stats'] : self::empty_stats();
		$running = self::is_running();

		$order   = array_keys( $phases );
		$index   = array_search( $phase, $order, true );
		$index   = ( false === $index ) ? 0 : (int) $index;
		$started = isset( $state['started'] ) ? (int) $state['started'] : 0;
		$touched = isset( $state['touched'] ) ? (int) $state['touched'] : $started;

		return array(
			'running'    => $running,
			'phase'      => $phase,
			'label'      => isset( $phases[ $phase ]['label'] ) ? $phases[ $phase ]['label'] . '…' : '',
			'detail'     => self::phase_detail( $phase, $stats ),
			'percent'    => self::percent( $state ),
			'step'       => $running ? $index + 1 : count( $order ),
			'step_total' => count( $order ),
			/* translators: 1: current phase number, 2: total number of phases. */
			'step_label' => $running ? sprintf( __( 'Step %1$d of %2$d', 'wpcredits-program-manager' ), $index + 1, count( $order ) ) : '',
			'stats'      => $stats,
			'elapsed'    => $started ? max( 0, time() - $started ) : 0,
			'idle'       => $touched ? max( 0, time() - $touched ) : 0,
			'error'      => (string) get_option( self::OPT_ERROR, '' ),
			'stalled'    => $running && $touched && ( time() - $touched ) > self::LOCK_TIMEOUT,
		);
	}

	/**
	 * A zeroed statistics array.
	 *
	 * @return array<string, int>
	 */
	public static function empty_stats() {
		return array(
			'students_seen' => 0,
			'reports_seen'  => 0,
			'feedback_seen' => 0,
			'no_email'      => 0,
			'addresses'     => 0,
		);
	}

	/**
	 * What the rules are asked against, read from the site: the live statuses and the work columns.
	 *
	 * The live statuses are the active list of the `student_statuses` setting, the one both syncs
	 * fetch by. The work columns are every column the Student Report Card's report form writes for
	 * any track the program map knows, the four built-in tracks and every Track Builder track: the
	 * map is `WPCPM_Program::labels()`, and `fields()` takes the key `track()` gives each status.
	 *
	 * @return array `live` (string[]) and `work_columns` (string[]).
	 */
	public static function context() {
		$statuses = WPCPM_Mentors_Sync::tracked_statuses();
		$work     = array();

		foreach ( array_keys( WPCPM_Program::labels() ) as $status ) {
			foreach ( array_keys( WPCPM_Student_Report_Form::fields( WPCPM_Program::track( $status ) ) ) as $column ) {
				$work[ (string) $column ] = true;
			}
		}

		return array(
			'live'         => $statuses['active'],
			'work_columns' => array_keys( $work ),
		);
	}

	/**
	 * Everything on this site whose meta value is exactly one of these record IDs.
	 *
	 * User meta and post meta both, matched on the value rather than on a list of keys, so a key a
	 * later release adds is covered the day it lands. The finder's own copies are left out
	 * (`OWN_POST_TYPES`). Asked twice: by the scan for the list, and by the delete handler just
	 * before it deletes, because a note written in between is exactly what must stop a delete.
	 *
	 * @param string[] $ids Record IDs.
	 * @return array<string, array[]> Record ID => each `kind` (`user` or a post type), `key`,
	 *                               `object` (the user or post ID) and, for a post, `status`.
	 */
	public static function refs_for( array $ids ) {
		global $wpdb;

		$ids  = array_values( array_unique( array_filter( array_map( 'strval', $ids ), array( 'WPCPM_Airtable', 'is_record_id' ) ) ) );
		$refs = array();

		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			$in = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One placeholder per ID, built above; a read that must be fresh.
			$users = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_value IN ($in)", $chunk ), ARRAY_A );

			foreach ( (array) $users as $row ) {
				$refs[ (string) $row['meta_value'] ][] = array(
					'kind'   => 'user',
					'key'    => (string) $row['meta_key'],
					'object' => (int) $row['user_id'],
				);
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- As above.
			$posts = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_type, p.post_status, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_value IN ($in)", $chunk ), ARRAY_A );

			foreach ( (array) $posts as $row ) {
				if ( in_array( (string) $row['post_type'], self::OWN_POST_TYPES, true ) ) {
					continue;
				}

				$refs[ (string) $row['meta_value'] ][] = array(
					'kind'   => (string) $row['post_type'],
					'key'    => (string) $row['meta_key'],
					'object' => (int) $row['ID'],
					'status' => (string) $row['post_status'],
				);
			}
		}

		return $refs;
	}

	/**
	 * Take rows Airtable confirmed deleted out of the stored report, at once.
	 *
	 * The list and the dashboard count are then right without waiting three hours for the next scan
	 * (spec 7.3 step 9). Each touched student is classified again on what is left; one with no
	 * table holding more than a single row is no longer a duplicate and leaves the report.
	 *
	 * @param array $gone Each `key`, `table` and `id`.
	 */
	public static function drop_deleted( array $gone ) {
		$report = self::report();

		if ( ! $report ) {
			return;
		}

		$context = self::context();
		$touched = array();

		foreach ( $gone as $one ) {
			$key   = (string) $one['key'];
			$table = (string) $one['table'];
			$id    = (string) $one['id'];

			if ( ! isset( $report['groups'][ $key ]['rows'][ $table ] ) ) {
				continue;
			}

			$before = count( $report['groups'][ $key ]['rows'][ $table ] );

			$report['groups'][ $key ]['rows'][ $table ] = array_values(
				array_filter(
					$report['groups'][ $key ]['rows'][ $table ],
					static function ( $row ) use ( $id ) {
						return $row['id'] !== $id;
					}
				)
			);

			if ( count( $report['groups'][ $key ]['rows'][ $table ] ) < $before && isset( $report['totals'][ $table ] ) ) {
				--$report['totals'][ $table ];
			}

			unset( $report['groups'][ $key ]['refs'][ $id ] );
			$touched[ $key ] = true;
		}

		foreach ( array_keys( $touched ) as $key ) {
			$tables = $report['groups'][ $key ]['rows'];
			$still  = false;

			foreach ( $tables as $rows ) {
				if ( count( $rows ) > 1 ) {
					$still = true;
					break;
				}
			}

			if ( ! $still ) {
				unset( $report['groups'][ $key ] );
				continue;
			}

			$report['groups'][ $key ] = self::group( $tables, $report['groups'][ $key ]['refs'], $context );
		}

		$report['groups'] = self::sorted( $report['groups'] );
		$report['counts'] = self::counts( $report['groups'] );

		update_option( self::OPT_REPORT, $report, false );
	}

	/**
	 * Read one page of the table the run is on.
	 *
	 * @param array          $state    Scan state, by reference.
	 * @param WPCPM_Airtable $airtable Client.
	 * @param array          $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private static function phase_read( array &$state, WPCPM_Airtable $airtable, array $settings ) {
		$table  = (string) $state['phase'];
		$source = isset( $settings[ self::TABLE_SETTINGS[ $table ] ] ) ? (string) $settings[ self::TABLE_SETTINGS[ $table ] ] : '';
		$page   = $airtable->fetch_page( $source, array( 'offset' => $state['offset'] ) );

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		foreach ( $page['records'] as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}

			$row = WPCPM_Duplicate_Rules::reduce( $table, $record, $state['context'] );
			$key = WPCPM_Duplicate_Rules::key( $row['email'] );

			++$state['stats'][ $table . '_seen' ];

			if ( '' === $key || '' === $row['id'] ) {
				++$state['stats']['no_email'];
				continue;
			}

			$state['rows'][ $key ][ $table ][] = $row;
		}

		$state['offset'] = empty( $page['offset'] ) ? null : (string) $page['offset'];

		if ( null !== $state['offset'] ) {
			return true;
		}

		// A table that answers with no rows at all is a renamed table or a revoked token, not a
		// base without students, and a report built on it would say nobody is duplicated.
		if ( 0 === (int) $state['stats'][ $table . '_seen' ] ) {
			return new WP_Error(
				'wpcpm_duplicates_empty',
				/* translators: %s: table setting name, for example students_table. */
				sprintf( __( 'Airtable returned no rows for %s, so the last list is kept.', 'wpcredits-program-manager' ), self::TABLE_SETTINGS[ $table ] )
			);
		}

		$order          = array_keys( self::phases() );
		$state['phase'] = $order[ (int) array_search( $table, $order, true ) + 1 ];

		return true;
	}

	/**
	 * Keep the duplicated addresses, look up what the site points at, classify, and store.
	 *
	 * @param array $state Scan state, by reference.
	 * @return true
	 */
	private static function phase_finish( array &$state ) {
		$dupes = array();
		$ids   = array();

		foreach ( $state['rows'] as $key => $tables ) {
			foreach ( $tables as $rows ) {
				if ( count( $rows ) > 1 ) {
					$dupes[ $key ] = $tables;
					break;
				}
			}
		}

		foreach ( $dupes as $tables ) {
			foreach ( $tables as $rows ) {
				foreach ( $rows as $row ) {
					$ids[] = $row['id'];
				}
			}
		}

		$refs   = self::refs_for( $ids );
		$groups = array();

		foreach ( $dupes as $key => $tables ) {
			$groups[ $key ] = self::group( $tables, $refs, $state['context'] );
		}

		$groups = self::sorted( $groups );

		update_option(
			self::OPT_REPORT,
			array(
				'v'        => self::REPORT_VERSION,
				'read'     => time(),
				'started'  => (int) $state['started'],
				'totals'   => array(
					'students' => (int) $state['stats']['students_seen'],
					'reports'  => (int) $state['stats']['reports_seen'],
					'feedback' => (int) $state['stats']['feedback_seen'],
				),
				'no_email' => (int) $state['stats']['no_email'],
				'groups'   => $groups,
				'counts'   => self::counts( $groups ),
			),
			false
		);

		$state['stats']['addresses'] = count( $groups );
		$state['rows']               = array();
		$state['phase']              = 'done';

		return true;
	}

	/**
	 * One student's classified rows, with the address to show and what the site points at.
	 *
	 * @param array $tables  Table => reduced rows.
	 * @param array $refs    Record ID => site references, for any rows (only this student's are kept).
	 * @param array $context As for `WPCPM_Duplicate_Rules::classify()`.
	 * @return array
	 */
	private static function group( array $tables, array $refs, array $context ) {
		$own = array();

		foreach ( $tables as $rows ) {
			foreach ( $rows as $row ) {
				if ( ! empty( $refs[ $row['id'] ] ) ) {
					$own[ $row['id'] ] = $refs[ $row['id'] ];
				}
			}
		}

		$group          = WPCPM_Duplicate_Rules::classify( $tables, $own, $context );
		$group['refs']  = $own;
		$group['email'] = '';
		$group['last']  = '';

		foreach ( WPCPM_Duplicate_Rules::TABLES as $table ) {
			foreach ( $group['rows'][ $table ] as $row ) {
				if ( '' === $group['email'] ) {
					$group['email'] = $row['email'];
				}
				$group['last'] = max( $group['last'], $row['created'] );
			}
		}

		return $group;
	}

	/**
	 * Ready students first, then those needing a decision; the most recent activity first in each.
	 *
	 * @param array $groups Key => group.
	 * @return array
	 */
	private static function sorted( array $groups ) {
		uasort(
			$groups,
			static function ( $a, $b ) {
				if ( $a['verdict'] !== $b['verdict'] ) {
					return WPCPM_Duplicate_Rules::READY === $a['verdict'] ? -1 : 1;
				}

				return strcmp( (string) $b['last'], (string) $a['last'] );
			}
		);

		return $groups;
	}

	/**
	 * The numbers the screen's tiles and the dashboard read.
	 *
	 * @param array $groups Key => group.
	 * @return array `addresses`, `ready`, `decide`, and table => n under `candidates` and `held`.
	 */
	private static function counts( array $groups ) {
		$counts = array(
			'addresses'  => count( $groups ),
			'ready'      => 0,
			'decide'     => 0,
			'candidates' => array_fill_keys( WPCPM_Duplicate_Rules::TABLES, 0 ),
			'held'       => array_fill_keys( WPCPM_Duplicate_Rules::TABLES, 0 ),
		);

		foreach ( $groups as $group ) {
			++$counts[ WPCPM_Duplicate_Rules::READY === $group['verdict'] ? 'ready' : 'decide' ];

			foreach ( $group['rows'] as $table => $rows ) {
				foreach ( $rows as $row ) {
					if ( WPCPM_Duplicate_Rules::DELETE === $row['proposal'] ) {
						++$counts['candidates'][ $table ];
					} elseif ( WPCPM_Duplicate_Rules::REVIEW === $row['proposal'] ) {
						++$counts['held'][ $table ];
					}
				}
			}
		}

		return $counts;
	}

	/**
	 * End the run on an error, keeping the last good report (decision 3.4).
	 *
	 * @param WP_Error $error What went wrong.
	 */
	private static function fail( WP_Error $error ) {
		update_option( self::OPT_ERROR, $error->get_error_message(), false );
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * Record the finish and clear the working state. The report itself was written by `finish`'s
	 * phase, which is why nothing of the state is needed here.
	 */
	private static function finish() {
		update_option( self::OPT_LAST, time(), false );
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_LOCK );
		wp_clear_scheduled_hook( self::CRON_TICK );
	}

	/**
	 * Claim the right to run a tick: `add_option()`'s test-and-set, with a stale takeover.
	 *
	 * @return bool
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

	/**
	 * Release the tick lock.
	 */
	private static function release_lock() {
		delete_option( self::OPT_LOCK );
	}

	/**
	 * Estimated completion percentage, from the phases' weights and their steps so far.
	 *
	 * @param array $state Scan state.
	 * @return int
	 */
	private static function percent( array $state ) {
		$phases = self::phases();
		$phase  = isset( $state['phase'] ) ? (string) $state['phase'] : '';

		if ( '' === $phase || 'done' === $phase ) {
			return isset( $state['phase'] ) ? 100 : 0;
		}

		$done = 0;

		foreach ( $phases as $key => $spec ) {
			if ( $key === $phase ) {
				$steps    = isset( $state['steps'][ $key ] ) ? (int) $state['steps'][ $key ] : 0;
				$expected = max( 1, (int) $spec['steps'] );
				$done    += (int) round( $spec['weight'] * min( 1, $steps / $expected ) );
				break;
			}

			$done += (int) $spec['weight'];
		}

		return max( 0, min( 99, $done ) );
	}

	/**
	 * One line about the phase, from the counts.
	 *
	 * @param string $phase The phase.
	 * @param array  $stats The counts.
	 * @return string
	 */
	private static function phase_detail( $phase, array $stats ) {
		switch ( $phase ) {
			case 'students':
				/* translators: %d: Students rows read so far. */
				return sprintf( __( '%d Students rows read', 'wpcredits-program-manager' ), (int) $stats['students_seen'] );
			case 'reports':
				/* translators: %d: Students Reports rows read so far. */
				return sprintf( __( '%d Students Reports rows read', 'wpcredits-program-manager' ), (int) $stats['reports_seen'] );
			case 'feedback':
				/* translators: %d: Feedback rows read so far. */
				return sprintf( __( '%d Feedback rows read', 'wpcredits-program-manager' ), (int) $stats['feedback_seen'] );
			case 'finish':
				return __( 'Grouping the rows by address', 'wpcredits-program-manager' );
		}

		return '';
	}
}
