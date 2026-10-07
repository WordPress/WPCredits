<?php
/**
 * Daily Airtable sync. Reads the Campus Connect Events table for the reporting
 * spine and the WordCamps table for coordinates, joins them on WordCamp ID, and
 * stores the normalised blob in wpcct_data.
 *
 * @package WPCC_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCCT_Sync {

	const API_URL = 'https://api.airtable.com/v0';

	/**
	 * Option holding the current/last sync's progress. A plain (non-autoload)
	 * option rather than a transient: WordPress always writes an option's
	 * value to the wp_options table on update() regardless of whether an
	 * external object cache is active, whereas set_transient()/get_transient()
	 * skip the DB entirely and live only in the object cache once a
	 * persistent external cache is present (as it is on the target host).
	 * Using a plain option guarantees a durable, DB-backed write that any
	 * PHP worker process can read, independent of that assumption.
	 */
	const OPT_PROGRESS = 'wpcct_sync_progress';

	/**
	 * Option holding a short-lived run lock. Two syncs must never overlap:
	 * they share the single OPT_PROGRESS record, so run B's 'starting'
	 * re-stamps started_at and run A's poller sees the stage go backwards,
	 * and whichever run finishes last silently wins the stored blob.
	 */
	const OPT_LOCK = 'wpcct_sync_lock';

	/**
	 * Seconds after which a held lock is treated as abandoned. A sync that
	 * dies hard (fatal, worker killed) never reaches release_lock(), so the
	 * lock must expire on its own or manual sync is wedged forever. Well
	 * above a realistic run, well below "come back tomorrow": 15 minutes.
	 */
	const LOCK_TTL = 900;

	/** Ordered stage keys, used to compute a discrete step position. */
	const STAGES = array( 'starting', 'events', 'coords', 'saving', 'done' );

	/** Campus Connect subset of the WordCamps table. */
	const WC_EVENT_TYPE_FIELD = 'fldy8KgSynQemzN1P';
	const WC_ID_FIELD         = 'fldLuWFoqkTj4fwtM';
	const WC_LAT_FIELD        = 'fldatbggVWCplyNBR';
	const WC_LNG_FIELD        = 'fldUmzvFHhXrXGF1P';
	const WC_SITE_FIELD       = 'fldLFLMaxNa4WFnF5';

	/** @var WPCCT_Sync|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( WPCCT_CRON_SYNC, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
	}

	/**
	 * Stored settings, with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( WPCCT_OPT_SETTINGS, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge(
			array(
				'airtable_pat'    => '',
				'base_id'         => 'appoiPJkMFdnJEmfa',
				'events_table'    => 'Campus Connect Events',
				'wordcamps_table' => 'tblLBVsO4rU2oj2Ki',
			),
			$saved
		);
	}

	/**
	 * The blob the front end renders. Falls back to the bundled seed so the
	 * plugin is never blank before its first sync.
	 *
	 * @return array
	 */
	public static function data() {
		$data = get_option( WPCCT_OPT_DATA, array() );
		if ( is_array( $data ) && ! empty( $data['totals'] ) ) {
			return $data;
		}

		$seed = WPCCT_DIR . 'data/seed.json';
		if ( is_readable( $seed ) ) {
			$decoded = json_decode( file_get_contents( $seed ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return array();
	}

	/**
	 * Write the current sync progress. A plain option write (see OPT_PROGRESS)
	 * so it is visible to any other PHP worker process reading it, not just
	 * this one.
	 *
	 * `started_at` is stamped fresh only when a new run begins (stage
	 * 'starting') and carried forward unchanged on every later call within
	 * the same run, so a poller can tell a fresh run's early progress apart
	 * from a stale leftover from the previous run.
	 *
	 * @param string $stage   One of self::STAGES, or 'error'.
	 * @param string $message Human-readable status text.
	 * @param array  $counts  Optional stage-specific counters.
	 */
	public static function set_progress( $stage, $message, $counts = array() ) {
		$existing   = get_option( self::OPT_PROGRESS, array() );
		$started_at = ( 'starting' === $stage || empty( $existing['started_at'] ) )
			? time()
			: (int) $existing['started_at'];

		update_option(
			self::OPT_PROGRESS,
			array(
				'stage'      => $stage,
				'message'    => $message,
				'counts'     => is_array( $counts ) ? $counts : array(),
				'started_at' => $started_at,
				'updated_at' => time(),
			),
			false
		);
	}

	/**
	 * Read the current sync progress.
	 *
	 * @return array
	 */
	public static function get_progress() {
		$progress = get_option( self::OPT_PROGRESS, array() );
		if ( ! is_array( $progress ) || empty( $progress['stage'] ) ) {
			return array(
				'stage'      => 'idle',
				'message'    => '',
				'counts'     => array(),
				'started_at' => 0,
				'updated_at' => 0,
			);
		}

		return $progress;
	}

	/**
	 * Discard any stored progress. Called by run() itself, once it holds the
	 * lock, so a previous run's terminal state cannot linger past its
	 * usefulness - and so a refused concurrent run cannot clear the record of
	 * the run that is actually in flight.
	 */
	public static function clear_progress() {
		delete_option( self::OPT_PROGRESS );
	}

	/**
	 * Run a full sync.
	 *
	 * On failure the previous blob is left untouched - a broken sync degrades
	 * to stale data, never to an empty dashboard.
	 *
	 * @return true|WP_Error
	 */
	public static function run() {
		if ( ! self::acquire_lock() ) {
			/* Deliberately not routed through fail(): the run already in
			   flight owns the progress record and the last-error option, and
			   this one must not disturb either. Nor does it release the lock
			   on the way out - it never held it. */
			return new WP_Error(
				'wpcct_sync_running',
				__( 'A sync is already running. Wait for it to finish before starting another.', 'wpcc-tracker' )
			);
		}

		/* Only now that the lock is held - clearing before acquiring would let
		   a second admin's refused click delete the record belonging to the
		   run already in flight, dropping its poller back to a blank bar and
		   making the running sync restamp started_at on its next write. */
		self::clear_progress();
		self::set_progress( 'starting', __( 'Validating settings…', 'wpcc-tracker' ) );

		$settings = self::settings();
		if ( empty( $settings['airtable_pat'] ) ) {
			return self::fail( __( 'No Airtable token configured.', 'wpcc-tracker' ), 'starting' );
		}

		$events = self::fetch_all( $settings, $settings['events_table'], array(), 'events' );
		if ( is_wp_error( $events ) ) {
			return self::fail( $events->get_error_message(), 'events' );
		}

		self::set_progress(
			'coords',
			__( 'Reading coordinates from the WordCamps table…', 'wpcc-tracker' ),
			array( 'events' => count( $events ) )
		);

		$coords = self::fetch_coords( $settings );
		if ( is_wp_error( $coords ) ) {
			return self::fail( $coords->get_error_message(), 'coords' );
		}

		if ( empty( $coords ) ) {
			/* A healthy filter returns ~71 coordinate rows. An empty result means
			   the Event Type field was renamed or its value changed, not that the
			   programme has no completed/scheduled events with coordinates. An
			   empty result is unambiguous, unlike a merely small one - keep the
			   old blob rather than silently blanking the map. */
			return self::fail( __( 'Airtable returned no coordinates from the WordCamps table; refusing to overwrite good data.', 'wpcc-tracker' ), 'coords' );
		}

		$rows = array();

		/* Two ages, and the one a reader needs is the second. This sync is the
		   Airtable-to-tracker hop; 'Synced At' is stamped by
		   wordcamp-airtable-connector on the Central-to-Airtable hop, so the
		   newest value across these rows is when Central was last read. Both
		   are tracked as timestamps rather than compared as strings, so a
		   change in Airtable's datetime formatting cannot silently pick the
		   wrong maximum. */
		$synced_ts  = 0;
		$created_ts = 0;

		foreach ( $events as $record ) {
			$f = isset( $record['fields'] ) ? $record['fields'] : array();

			if ( ! empty( $f['Synced At'] ) && is_scalar( $f['Synced At'] ) ) {
				$ts = strtotime( (string) $f['Synced At'] );
				if ( $ts && $ts > $synced_ts ) {
					$synced_ts = $ts;
				}
			}

			/* Airtable's own, and only evidence of when a row first appeared.
			   It does not move on update, so it is a fallback for the case
			   that matters right now: no row has ever been synced, and without
			   this the footer could say nothing at all about the data's age. */
			if ( ! empty( $record['createdTime'] ) && is_scalar( $record['createdTime'] ) ) {
				$ts = strtotime( (string) $record['createdTime'] );
				if ( $ts && $ts > $created_ts ) {
					$created_ts = $ts;
				}
			}

			$rows[] = array(
				'Start Date (YYYY-mm-dd)'         => isset( $f['Start Date'] ) ? $f['Start Date'] : '',
				'End Date (YYYY-mm-dd)'           => isset( $f['End Date'] ) ? $f['End Date'] : '',
				'Status'                          => isset( $f['Status'] ) ? $f['Status'] : '',
				'Name'                            => isset( $f['Name'] ) ? $f['Name'] : '',
				'Institution Name'                => isset( $f['Institution Name'] ) ? $f['Institution Name'] : '',
				'City'                            => isset( $f['City'] ) ? $f['City'] : '',
				'Country'                         => isset( $f['Country'] ) ? $f['Country'] : '',
				'Number of Anticipated Attendees' => isset( $f['Anticipated Attendees'] ) ? $f['Anticipated Attendees'] : '',
				'Actual Attendees'                => isset( $f['Actual Attendees'] ) ? $f['Actual Attendees'] : '',
				'URL'                             => isset( $f['URL'] ) ? $f['URL'] : '',
				'ID'                              => isset( $f['WordCamp ID'] ) ? (string) $f['WordCamp ID'] : '',
			);
		}

		if ( count( $rows ) < 50 ) {
			/* A healthy export is ~142 rows. A tiny result means a filter or a
			   renamed field, not a shrunken programme - keep the old blob. */
			return self::fail(
				sprintf(
					/* translators: %d: number of records returned. */
					__( 'Airtable returned only %d events; refusing to overwrite good data.', 'wpcc-tracker' ),
					count( $rows )
				),
				'events'
			);
		}

		self::set_progress(
			'saving',
			__( 'Normalising and saving…', 'wpcc-tracker' ),
			array(
				'events' => count( $rows ),
				'coords' => count( $coords ),
			)
		);

		$blob = WPCCT_Normalize::build(
			$rows,
			$coords,
			array(
				'synced'  => $synced_ts ? gmdate( 'c', $synced_ts ) : '',
				'created' => $created_ts ? gmdate( 'c', $created_ts ) : '',
			)
		);

		if ( ! WPCCT_Normalize::is_plausible( $blob ) ) {
			/* The row-count guard above counts records as they arrive, which
			   a field rename does not change: the Events table is addressed
			   by field name, so renaming one still delivers all ~142 records
			   - they simply lose that column. This guard reads the built blob
			   instead. See WPCCT_Normalize::is_plausible() for exactly what it
			   does and does not catch. */
			return self::fail(
				__( 'Airtable returned records, but the normalised result is empty: either no event has a recognised Status, or no event country maps to a region. The Status or Country field has most likely been renamed upstream. Refusing to overwrite good data.', 'wpcc-tracker' ),
				'saving'
			);
		}

		update_option( WPCCT_OPT_DATA, $blob, false );
		update_option( WPCCT_OPT_LASTSYNC, time(), false );
		delete_option( WPCCT_OPT_LASTERR );

		/* 'done' - and specifically its counts - is only ever written here,
		   after wpcct_data has actually been persisted above. Nothing reports
		   100%/done ahead of the real write.

		   The counts are the stored blob's own, not the inputs': $rows counts
		   fetched records including the denied and cancelled ones that never
		   reach the blob, and $coords counts every Campus Connect WordCamp
		   with coordinates, including cancelled/declined camps that are not
		   drawn. Reporting the inputs overstates both. */
		self::set_progress(
			'done',
			__( 'Sync complete.', 'wpcc-tracker' ),
			array(
				'events'  => count( $blob['events'] ),
				'markers' => count( $blob['markers'] ),
			)
		);

		self::release_lock();

		return true;
	}

	/** Object-cache group for the run lock. */
	const CACHE_GROUP = 'wpcct';

	/**
	 * Take the run lock, if it is free.
	 *
	 * Two layers, because neither is sufficient on its own:
	 *
	 * - wp_cache_add() is a genuine atomic test-and-set - both memcached and
	 *   Redis implement ADD as "fail if the key already exists" - but only
	 *   where a persistent object cache is installed (as it is on the target
	 *   host). On the default non-persistent cache it is per-request and so
	 *   always succeeds, which is why it cannot stand alone.
	 * - The option is durable and visible to every worker, but its write is
	 *   NOT atomic. add_option() would be no better than update_option() here:
	 *   it ends in an INSERT … ON DUPLICATE KEY UPDATE, so it cannot fail on
	 *   the unique key, and two workers that both read a free lock would both
	 *   come away believing they hold it.
	 *
	 * So: a real mutex wherever an external object cache is in play, and a
	 * best-effort one elsewhere, where the remaining race needs two workers to
	 * read the same free lock in the same instant. Losing that race yields the
	 * behaviour that existed before the lock, so it does not justify a heavier
	 * primitive.
	 *
	 * @return bool True when this process now holds the lock.
	 */
	private static function acquire_lock() {
		$now      = time();
		$ext_cache = function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();

		if ( $ext_cache && ! wp_cache_add( self::OPT_LOCK, $now, self::CACHE_GROUP, self::LOCK_TTL ) ) {
			return false;
		}

		$held = (int) get_option( self::OPT_LOCK, 0 );

		if ( $held > 0 && ( $now - $held ) < self::LOCK_TTL ) {
			// The durable record says a run is still in flight; give back the
			// cache key just taken, or the lock would outlive that run.
			if ( $ext_cache ) {
				wp_cache_delete( self::OPT_LOCK, self::CACHE_GROUP );
			}
			return false;
		}

		// Either free, or abandoned by a run that died before releasing.
		update_option( self::OPT_LOCK, $now, false );

		return true;
	}

	/**
	 * Release the run lock, in both layers. Must be reached on every exit path
	 * from run() that acquired it - success returns here, failures via fail().
	 */
	private static function release_lock() {
		delete_option( self::OPT_LOCK );

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			wp_cache_delete( self::OPT_LOCK, self::CACHE_GROUP );
		}
	}

	/**
	 * Coordinates for the Campus Connect subset of the WordCamps table.
	 *
	 * @param array $settings Settings.
	 * @return array|WP_Error WordCamp ID => array( lat, lng, site ).
	 */
	private static function fetch_coords( $settings ) {
		$records = self::fetch_all(
			$settings,
			$settings['wordcamps_table'],
			array(
				'returnFieldsByFieldId' => 'true',
				'filterByFormula'       => "{" . self::WC_EVENT_TYPE_FIELD . "}='Campus Connect'",
			),
			'coords'
		);
		if ( is_wp_error( $records ) ) {
			return $records;
		}

		$out = array();
		foreach ( $records as $record ) {
			$f = isset( $record['fields'] ) ? $record['fields'] : array();
			if ( empty( $f[ self::WC_ID_FIELD ] ) || ! isset( $f[ self::WC_LAT_FIELD ], $f[ self::WC_LNG_FIELD ] ) ) {
				continue;
			}
			$out[ (string) $f[ self::WC_ID_FIELD ] ] = array(
				'lat'  => (float) $f[ self::WC_LAT_FIELD ],
				'lng'  => (float) $f[ self::WC_LNG_FIELD ],
				'site' => isset( $f[ self::WC_SITE_FIELD ] ) ? $f[ self::WC_SITE_FIELD ] : '',
			);
		}

		return $out;
	}

	/**
	 * Fetch every record from a table, following Airtable's offset pagination.
	 *
	 * @param array  $settings       Settings.
	 * @param string $table          Table id or name.
	 * @param array  $extra          Extra query args.
	 * @param string $progress_stage When non-empty, one of 'events'/'coords';
	 *                               reports a records-so-far count after each
	 *                               page arrives, under that stage.
	 * @return array|WP_Error
	 */
	private static function fetch_all( $settings, $table, $extra = array(), $progress_stage = '' ) {
		$records = array();
		$offset  = '';
		$guard   = 0;

		do {
			if ( ++$guard > 50 ) {
				return new WP_Error( 'wpcct_pagination', __( 'Airtable pagination did not terminate.', 'wpcc-tracker' ) );
			}

			$args = array_merge( array( 'pageSize' => 100 ), $extra );
			if ( $offset ) {
				$args['offset'] = $offset;
			}

			$url = self::API_URL . '/' . rawurlencode( $settings['base_id'] ) . '/' . rawurlencode( $table )
				. '?' . http_build_query( $args );

			$resp = wp_remote_get(
				$url,
				array(
					'timeout' => 20,
					'headers' => array( 'Authorization' => 'Bearer ' . $settings['airtable_pat'] ),
				)
			);

			if ( is_wp_error( $resp ) ) {
				return new WP_Error( 'wpcct_http', $resp->get_error_message() );
			}

			$code = (int) wp_remote_retrieve_response_code( $resp );
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );

			if ( 200 !== $code ) {
				$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
				return new WP_Error( 'wpcct_api', sprintf( 'Airtable (%s): %s', $table, $msg ) );
			}

			if ( ! is_array( $body ) ) {
				return new WP_Error( 'wpcct_api', sprintf( 'Airtable (%s): malformed response body.', $table ) );
			}

			if ( ! empty( $body['records'] ) ) {
				$records = array_merge( $records, $body['records'] );
			}

			if ( '' !== $progress_stage ) {
				$count = count( $records );
				if ( 'events' === $progress_stage ) {
					self::set_progress(
						'events',
						sprintf(
							/* translators: %d: number of event records fetched so far. */
							__( 'Reading events from Airtable… %d so far.', 'wpcc-tracker' ),
							$count
						),
						array( 'events_so_far' => $count )
					);
				} else {
					self::set_progress(
						'coords',
						sprintf(
							/* translators: %d: number of coordinate records fetched so far. */
							__( 'Reading coordinates from the WordCamps table… %d so far.', 'wpcc-tracker' ),
							$count
						),
						array( 'coords_so_far' => $count )
					);
				}
			}

			$offset = isset( $body['offset'] ) ? $body['offset'] : '';
		} while ( $offset );

		return $records;
	}

	/**
	 * Record a failure without disturbing the last good blob, and surface it
	 * through the progress store so a poller sees exactly which stage failed
	 * and stops - it must never sit waiting on a stage that has died.
	 *
	 * Every failing exit from run() goes through here, so this is also where
	 * the run lock is released.
	 *
	 * @param string $message Message.
	 * @param string $stage   Stage that was in progress when the failure
	 *                        happened (one of self::STAGES).
	 * @return WP_Error
	 */
	private static function fail( $message, $stage = 'starting' ) {
		update_option( WPCCT_OPT_LASTERR, $message, false );
		self::set_progress( 'error', $message, array( 'failed_stage' => $stage ) );
		self::release_lock();

		return new WP_Error( 'wpcct_sync', $message );
	}

	/**
	 * Hook name used before the sync moved from weekly to daily.
	 *
	 * Kept only so an install carrying the old event can have it retired; the
	 * hook itself is no longer registered, so a stale event would otherwise fire
	 * into nothing forever.
	 */
	const LEGACY_CRON_HOOK = 'wpcct_cron_weekly';

	/**
	 * Make sure the recurring sync is scheduled, once a day.
	 *
	 * Runs on every request but costs two cached option reads in the common
	 * case. It covers three situations in one pass: a fresh activation, an
	 * install still carrying the pre-daily weekly event, and a site whose event
	 * was lost (a cron table reset, or a restore from a backup taken while the
	 * plugin was inactive).
	 */
	public static function maybe_schedule() {
		// Retire the pre-daily hook if it is still on the schedule.
		if ( wp_next_scheduled( self::LEGACY_CRON_HOOK ) ) {
			wp_unschedule_hook( self::LEGACY_CRON_HOOK );
		}

		$event = wp_get_scheduled_event( WPCCT_CRON_SYNC );

		/*
		 * An event already scheduled on some other recurrence -- an install that
		 * predates this change, or one whose schedule was altered by hand -- is
		 * cleared so it can be replaced, rather than left running at the wrong
		 * interval.
		 */
		if ( $event && 'daily' !== $event->schedule ) {
			wp_unschedule_hook( WPCCT_CRON_SYNC );
			$event = false;
		}

		if ( ! $event ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', WPCCT_CRON_SYNC );
		}
	}

	/**
	 * Schedule the daily sync. Called on activation.
	 */
	public static function activate() {
		self::maybe_schedule();
	}

	/**
	 * Clear the schedule. Called on deactivation.
	 */
	public static function deactivate() {
		wp_unschedule_hook( WPCCT_CRON_SYNC );
		wp_unschedule_hook( self::LEGACY_CRON_HOOK );
	}
}
