<?php
/**
 * Orchestrates a check run: queue the mentors, scan profiles, promote matches.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the check in batches so a long mentor list never depends on one long request.
 */
class WPCPM_Mentor_Checker_Runner {

	const CRON_HOOK    = 'wpcpm_checker_weekly_check';
	const QUEUE_PREFIX = 'wpcpm_checker_queue_';
	const RESULT_KEY   = 'wpcpm_checker_last_run';
	const QUEUE_TTL    = 3 * HOUR_IN_SECONDS;
	const MAX_LOG_ROWS = 500;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Airtable client, shared with the rest of the plugin.
	 *
	 * @var WPCPM_Airtable
	 */
	private $airtable;

	/**
	 * Profile checker.
	 *
	 * @var WPCPM_Mentor_Checker_Profile
	 */
	private $checker;

	/**
	 * Constructor.
	 *
	 * @param array|null $settings Optional settings override.
	 */
	public function __construct( $settings = null ) {
		$this->settings = is_array( $settings ) ? $settings : WPCPM_Mentor_Checker::config();
		$this->airtable = new WPCPM_Airtable( $this->settings );
		$this->checker  = new WPCPM_Mentor_Checker_Profile( $this->settings );
	}

	/**
	 * Every mentor currently holding the source status.
	 *
	 * @return array|WP_Error List of arrays with id, name, profile and status keys.
	 */
	private function get_mentors_to_check() {
		$records = $this->airtable->fetch_all(
			$this->settings['table_id'],
			array(
				'formula' => $this->airtable->formula_in( $this->settings['field_status'], array( $this->settings['source_status'] ) ),
				'fields'  => array( $this->settings['field_name'], $this->settings['field_profile'], $this->settings['field_status'] ),
			)
		);

		if ( is_wp_error( $records ) ) {
			return $records;
		}

		$mentors = array();

		foreach ( $records as $record ) {
			$mentors[] = $this->shape_mentor( $record );
		}

		return $mentors;
	}

	/**
	 * Read one mentor record fresh from Airtable.
	 *
	 * @param string $record_id Airtable record ID.
	 * @return array|WP_Error
	 */
	private function read_mentor( $record_id ) {
		$record = $this->airtable->get_record( $this->settings['table_id'], $record_id );

		return is_wp_error( $record ) ? $record : $this->shape_mentor( $record );
	}

	/**
	 * Write a status onto one mentor record.
	 *
	 * @param string $record_id Airtable record ID.
	 * @param string $status    Status value to write.
	 * @return array|WP_Error
	 */
	private function write_status( $record_id, $status ) {
		return $this->airtable->update_records(
			$this->settings['table_id'],
			array(
				array(
					'id'     => $record_id,
					'fields' => array( $this->settings['field_status'] => $status ),
				),
			)
		);
	}

	/**
	 * Reduce a raw Airtable record to the four keys this tool works with.
	 *
	 * @param array $record Raw record.
	 * @return array
	 */
	private function shape_mentor( array $record ) {
		$fields = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();

		$read = function ( $key ) use ( $fields ) {
			return WPCPM_Airtable::flatten( isset( $fields[ $this->settings[ $key ] ] ) ? $fields[ $this->settings[ $key ] ] : '' );
		};

		return array(
			'id'      => isset( $record['id'] ) ? (string) $record['id'] : '',
			'name'    => $read( 'field_name' ),
			'profile' => $read( 'field_profile' ),
			'status'  => $read( 'field_status' ),
		);
	}

	/**
	 * Build the queue of mentors to check.
	 *
	 * @param bool $apply Whether matches should be promoted as the run proceeds.
	 * @return array|WP_Error Array with run_id, total, batch_size and queue keys.
	 */
	public function start( $apply = false ) {
		$mentors = $this->get_mentors_to_check();

		if ( is_wp_error( $mentors ) ) {
			return $mentors;
		}

		// Lower-case only: the run ID travels through sanitize_key() on the way back
		// from the browser, which would fold any capitals and break the lookup.
		$run_id = strtolower( wp_generate_password( 20, false, false ) );

		set_transient(
			self::QUEUE_PREFIX . $run_id,
			array(
				'mentors' => $mentors,
				'apply'   => (bool) $apply,
			),
			self::QUEUE_TTL
		);

		update_option(
			self::RESULT_KEY,
			array(
				'run_id'     => $run_id,
				'started'    => time(),
				'finished'   => 0,
				'apply'      => (bool) $apply,
				'total'      => count( $mentors ),
				'source'     => $this->settings['source_status'],
				'target'     => $this->settings['target_status'],
				'course'     => $this->settings['course_title'],
				'rows'       => array(),
				'is_partial' => true,
			),
			false
		);

		// Hand back the whole queue so the screen can list every mentor straight away
		// and fill each row in as it resolves, instead of showing an empty table
		// until the first batch of profile reads finishes.
		$queue = array();
		foreach ( $mentors as $mentor ) {
			$queue[] = array(
				'record_id' => $mentor['id'],
				'name'      => $mentor['name'],
				'profile'   => $mentor['profile'],
				'username'  => WPCPM_Mentor_Checker_Profile::normalize_username( $mentor['profile'] ),
				'status'    => $mentor['status'],
				'state'     => 'pending',
			);
		}

		return array(
			'run_id'     => $run_id,
			'total'      => count( $mentors ),
			'batch_size' => max( 1, (int) $this->settings['batch_size'] ),
			'queue'      => $queue,
		);
	}

	/**
	 * Check one batch of mentors and record the outcome.
	 *
	 * @param string $run_id Run identifier from start().
	 * @param int    $offset Zero-based queue offset.
	 * @return array|WP_Error Array with rows, next_offset, total and done keys.
	 */
	public function process_batch( $run_id, $offset ) {
		$queue = get_transient( self::QUEUE_PREFIX . $run_id );

		if ( ! is_array( $queue ) || ! isset( $queue['mentors'] ) ) {
			return new WP_Error( 'wpcpm_checker_queue_missing', __( 'This run has expired. Start the check again.', 'wpcredits-program-manager' ) );
		}

		$mentors = $queue['mentors'];
		$apply   = ! empty( $queue['apply'] );
		$total   = count( $mentors );
		$offset  = max( 0, (int) $offset );
		$size    = max( 1, (int) $this->settings['batch_size'] );
		$batch   = array_slice( $mentors, $offset, $size );
		$rows    = array();

		foreach ( $batch as $mentor ) {
			$rows[] = $this->check_mentor( $mentor, $apply );
		}

		$this->append_rows( $run_id, $rows );

		$next = $offset + count( $batch );
		$done = $next >= $total;

		if ( $done ) {
			$this->finish( $run_id );
			delete_transient( self::QUEUE_PREFIX . $run_id );
		}

		return array(
			'rows'        => $rows,
			'next_offset' => $next,
			'total'       => $total,
			'done'        => $done,
		);
	}

	/**
	 * Check a single mentor, optionally promoting them.
	 *
	 * @param array $mentor   Mentor record (id, name, profile, status).
	 * @param bool  $apply    Whether to write the target status on a match.
	 * @param bool  $use_cache Whether a cached profile result may be reused.
	 * @return array Result row.
	 */
	public function check_mentor( array $mentor, $apply = false, $use_cache = true ) {
		$result = $this->checker->check( $mentor['profile'], $use_cache );

		$row = array(
			'record_id'   => $mentor['id'],
			'name'        => $mentor['name'],
			'profile'     => $mentor['profile'],
			'username'    => $result['username'],
			'status'      => $mentor['status'],
			'completed'   => $result['completed'],
			'state'       => $result['state'],
			'message'     => $result['message'],
			'timestamp'   => $result['timestamp'],
			'pages'       => $result['pages'],
			'cached'      => $result['cached'],
			'action'      => 'none',
			'action_note' => '',
		);

		if ( ! $result['completed'] ) {
			return $row;
		}

		if ( ! $apply ) {
			$row['action']      = 'eligible';
			$row['action_note'] = sprintf(
				/* translators: %s: target status name. */
				__( 'Ready to move to %s.', 'wpcredits-program-manager' ),
				$this->settings['target_status']
			);
			return $row;
		}

		$promotion = $this->promote( $mentor['id'] );

		if ( is_wp_error( $promotion ) ) {
			$row['action']      = 'failed';
			$row['action_note'] = $promotion->get_error_message();
			return $row;
		}

		$row['action']      = $promotion['promoted'] ? 'promoted' : 'skipped';
		$row['action_note'] = $promotion['message'];
		$row['status']      = $promotion['status'];

		return $row;
	}

	/**
	 * Write the target status for one mentor, re-confirming the source status first.
	 *
	 * The re-read guards against promoting a record that someone changed in Airtable
	 * after the queue was built.
	 *
	 * @param string $record_id Airtable record ID.
	 * @return array|WP_Error Array with promoted, status and message keys.
	 */
	public function promote( $record_id ) {
		$record = $this->read_mentor( $record_id );

		if ( is_wp_error( $record ) ) {
			return $record;
		}

		if ( $record['status'] === $this->settings['target_status'] ) {
			return array(
				'promoted' => false,
				'status'   => $record['status'],
				'message'  => sprintf(
					/* translators: %s: target status name. */
					__( 'Already %s in Airtable; left unchanged.', 'wpcredits-program-manager' ),
					$this->settings['target_status']
				),
			);
		}

		if ( $record['status'] !== $this->settings['source_status'] ) {
			return array(
				'promoted' => false,
				'status'   => $record['status'],
				'message'  => sprintf(
					/* translators: 1: current status in Airtable, 2: expected source status. */
					__( 'Status is now "%1$s", not "%2$s"; left unchanged.', 'wpcredits-program-manager' ),
					$record['status'],
					$this->settings['source_status']
				),
			);
		}

		$updated = $this->write_status( $record_id, $this->settings['target_status'] );

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return array(
			'promoted' => true,
			'status'   => $this->settings['target_status'],
			'message'  => sprintf(
				/* translators: 1: source status, 2: target status. */
				__( 'Moved from "%1$s" to "%2$s".', 'wpcredits-program-manager' ),
				$this->settings['source_status'],
				$this->settings['target_status']
			),
		);
	}

	/**
	 * Re-check one mentor by record ID and promote them if they qualify.
	 *
	 * Used by the per-row "Promote" button, which must never trust the browser's
	 * claim that a mentor passed the check.
	 *
	 * @param string $record_id Airtable record ID.
	 * @return array|WP_Error Result row.
	 */
	public function verify_and_promote( $record_id ) {
		$record = $this->read_mentor( $record_id );

		if ( is_wp_error( $record ) ) {
			return $record;
		}

		$row = $this->check_mentor( $record, true, false );

		if ( ! $row['completed'] ) {
			$row['action']      = 'failed';
			$row['action_note'] = __( 'Re-check did not find the course completion; nothing was changed.', 'wpcredits-program-manager' );
		}

		$this->update_stored_row( $row );

		return $row;
	}

	/**
	 * Append result rows to the stored run.
	 *
	 * @param string $run_id Run identifier.
	 * @param array  $rows   Result rows.
	 */
	private function append_rows( $run_id, array $rows ) {
		$run = self::get_last_run();

		if ( empty( $run ) || ! isset( $run['run_id'] ) || $run['run_id'] !== $run_id ) {
			return;
		}

		$run['rows'] = array_slice( array_merge( $run['rows'], $rows ), -self::MAX_LOG_ROWS );

		update_option( self::RESULT_KEY, $run, false );
	}

	/**
	 * Mark the stored run as finished.
	 *
	 * @param string $run_id Run identifier.
	 */
	private function finish( $run_id ) {
		$run = self::get_last_run();

		if ( empty( $run ) || ! isset( $run['run_id'] ) || $run['run_id'] !== $run_id ) {
			return;
		}

		$run['finished']   = time();
		$run['is_partial'] = false;

		update_option( self::RESULT_KEY, $run, false );
	}

	/**
	 * Replace a stored row after a manual promotion.
	 *
	 * @param array $row Updated result row.
	 */
	private function update_stored_row( array $row ) {
		$run = self::get_last_run();

		if ( empty( $run ) || empty( $run['rows'] ) ) {
			return;
		}

		foreach ( $run['rows'] as $index => $stored ) {
			if ( isset( $stored['record_id'] ) && $stored['record_id'] === $row['record_id'] ) {
				$run['rows'][ $index ] = $row;
				update_option( self::RESULT_KEY, $run, false );
				return;
			}
		}
	}

	/**
	 * The stored results of the most recent run.
	 *
	 * @return array
	 */
	public static function get_last_run() {
		$run = get_option( self::RESULT_KEY, array() );

		if ( ! is_array( $run ) ) {
			return array();
		}

		if ( ! isset( $run['rows'] ) || ! is_array( $run['rows'] ) ) {
			$run['rows'] = array();
		}

		return $run;
	}

	/**
	 * Discard the stored run.
	 */
	public static function clear_last_run() {
		delete_option( self::RESULT_KEY );
	}

	/**
	 * Summarise a run's rows into counts.
	 *
	 * @param array $rows Result rows.
	 * @return array
	 */
	public static function summarize( array $rows ) {
		$summary = array(
			'checked'   => count( $rows ),
			'completed' => 0,
			'eligible'  => 0,
			'promoted'  => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'problems'  => 0,
		);

		foreach ( $rows as $row ) {
			if ( ! empty( $row['completed'] ) ) {
				++$summary['completed'];
			}

			if ( isset( $row['state'] ) && in_array( $row['state'], array( 'no_username', 'not_found', 'error' ), true ) ) {
				++$summary['problems'];
			}

			switch ( isset( $row['action'] ) ? $row['action'] : 'none' ) {
				case 'eligible':
					++$summary['eligible'];
					break;
				case 'promoted':
					++$summary['promoted'];
					break;
				case 'skipped':
					++$summary['skipped'];
					break;
				case 'failed':
					++$summary['failed'];
					break;
			}
		}

		return $summary;
	}

	/**
	 * Run the whole check start to finish, without batching.
	 *
	 * Suitable for WP-CLI and cron, where there is no browser to drive the batches.
	 *
	 * @param bool          $apply    Whether to promote matches.
	 * @param callable|null $progress Optional callback receiving each result row.
	 * @return array|WP_Error Array with rows and summary keys.
	 */
	public function run_all( $apply = false, $progress = null ) {
		$start = $this->start( $apply );

		if ( is_wp_error( $start ) ) {
			return $start;
		}

		$offset = 0;
		$rows   = array();

		do {
			$batch = $this->process_batch( $start['run_id'], $offset );

			if ( is_wp_error( $batch ) ) {
				return $batch;
			}

			foreach ( $batch['rows'] as $row ) {
				$rows[] = $row;
				if ( is_callable( $progress ) ) {
					call_user_func( $progress, $row );
				}
			}

			// Guard against an empty batch stalling the loop.
			if ( $batch['next_offset'] <= $offset && ! $batch['done'] ) {
				break;
			}

			$offset = $batch['next_offset'];
		} while ( ! $batch['done'] );

		return array(
			'rows'    => $rows,
			'summary' => self::summarize( $rows ),
		);
	}

	/**
	 * Hook the weekly cron event.
	 */
	public static function register_cron() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_cron' ) );

		$settings = WPCPM_Mentor_Checker::config();

		if ( ! empty( $settings['cron_enabled'] ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK );
		}
	}

	/**
	 * Schedule or unschedule the weekly event to match the setting.
	 *
	 * @param bool $enabled Whether the schedule should exist.
	 */
	public static function sync_cron( $enabled ) {
		$scheduled = wp_next_scheduled( self::CRON_HOOK );

		if ( $enabled && ! $scheduled ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK );
		} elseif ( ! $enabled && $scheduled ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * The weekly cron callback.
	 */
	public static function run_cron() {
		$settings = WPCPM_Mentor_Checker::config();

		if ( empty( $settings['cron_enabled'] ) || ! WPCPM_Mentor_Checker::is_configured() ) {
			return;
		}

		$runner = new self( $settings );
		$result = $runner->run_all( ! empty( $settings['cron_promotes'] ) );

		if ( is_wp_error( $result ) ) {
			/**
			 * Fires when a scheduled mentor check fails.
			 *
			 * @param WP_Error $error The failure.
			 */
			do_action( 'wpcpm_checker_cron_failed', $result );
			return;
		}

		/**
		 * Fires after a scheduled mentor check completes.
		 *
		 * @param array $summary Counts for the run.
		 * @param array $rows    Individual result rows.
		 */
		do_action( 'wpcpm_checker_cron_completed', $result['summary'], $result['rows'] );
	}
}
