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
 *
 * **Two sources, the site's first (1.122.33).** The applications selected on this site come first,
 * the newest selection first, each looked up on WordPress.org exactly as an Airtable mentor is; a
 * row where the course shows is made a mentor through `WPCPM_Mentor_Activation`, or, while Make
 * mentors from applications is off, is left waiting and says so. The mentors holding the source
 * status in Airtable come after them, for as long as they are read: while Airtable is connected and
 * a person has not ticked Stop checking mentors in Airtable. It is a stop and not a "keep reading"
 * box so that a save of the checker's screen from a page that does not draw it, which posts no box,
 * leaves Airtable's mentors checked rather than dropping them. A site row's key is `app-` and the
 * application's ID (`APP_KEY`), where an Airtable row's is its record ID.
 */
class WPCPM_Mentor_Checker_Runner {

	const CRON_HOOK    = 'wpcpm_checker_weekly_check';
	const RECURRENCE   = 'daily';
	const QUEUE_PREFIX = 'wpcpm_checker_queue_';
	const RESULT_KEY   = 'wpcpm_checker_last_run';
	const QUEUE_TTL    = 3 * HOUR_IN_SECONDS;
	const MAX_LOG_ROWS = 500;

	/** What a site row's key begins with, the application's ID after it (`application_of()`). */
	const APP_KEY = 'app-';

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
	 * Whether Airtable's mentors are read: while Airtable is connected and Stop checking mentors in
	 * Airtable is not ticked.
	 *
	 * @return bool
	 */
	private function reads_airtable() {
		return WPCPM_Mentor_Checker::is_configured() && empty( $this->settings['skip_airtable'] );
	}

	/**
	 * The applications selected on this site, as rows to check: the newest selection first, and the
	 * higher ID first among selections of one second, so the order is one on every PHP the plugin
	 * runs on (`usort()` keeps equal rows in place only from PHP 8.0).
	 *
	 * @return array[] Each `id` (`app-` and the ID), `application`, `name`, `profile` (the profile's
	 *                 address), `status` and `source` (`site`).
	 */
	public function site_rows() {
		$rows = array();
		$at   = array();
		$ids  = array();

		foreach ( WPCPM_Mentor_Application::applications( array( WPCPM_Mentor_Application::STATE_SELECTED ) ) as $post ) {
			$rows[] = $this->application_row( $post );
			$at[]   = WPCPM_Mentor_Application::last_at( (int) $post->ID, WPCPM_Mentor_Application::EVENT_SELECTED );
			$ids[]  = (int) $post->ID;
		}

		array_multisort( $at, SORT_DESC, SORT_NUMERIC, $ids, SORT_DESC, SORT_NUMERIC, $rows );

		return $rows;
	}

	/**
	 * One application as the row the checker works on.
	 *
	 * @param WP_Post $post The application.
	 * @return array See `site_rows()`.
	 */
	private function application_row( WP_Post $post ) {
		return array(
			'id'          => self::APP_KEY . (int) $post->ID,
			'application' => (int) $post->ID,
			'name'        => WPCPM_Mentor_Application::name_of( $post ),
			'profile'     => WPCPM_Mentor_Application::profile_url( WPCPM_Mentor_Application::handle_of( $post ) ),
			'status'      => __( 'Selected on this site', 'wpcredits-program-manager' ),
			'source'      => 'site',
		);
	}

	/**
	 * The application a key names: `app-` and digits to the end of the string, else 0 (so a record
	 * ID, a key with anything after its digits, a line break included, and a malformed one are none).
	 *
	 * @param mixed $key A row's key.
	 * @return int The application's ID, or 0.
	 */
	public static function application_of( $key ) {
		return is_string( $key ) && 1 === preg_match( '/^' . preg_quote( self::APP_KEY, '/' ) . '([0-9]+)\z/', $key, $found ) ? (int) $found[1] : 0;
	}

	/**
	 * Every mentor to check: the site's applications, then every mentor currently holding the source
	 * status in Airtable while those are read.
	 *
	 * A read of Airtable that fails costs the run its Airtable mentors and nothing more while there are
	 * applications to check, and the error is handed back for the run to keep; with none to check the
	 * error is the answer, as it was when Airtable was the only source.
	 *
	 * @return array|WP_Error `mentors` (a list of arrays with id, application, name, profile, status and
	 *                        source keys) and `error` (why Airtable's could not be read, or '').
	 */
	private function get_mentors_to_check() {
		$mentors = $this->site_rows();
		$error   = '';

		if ( $this->reads_airtable() ) {
			$records = $this->airtable->fetch_all(
				$this->settings['table_id'],
				array(
					'formula' => $this->airtable->formula_in( $this->settings['field_status'], array( $this->settings['source_status'] ) ),
					'fields'  => array( $this->settings['field_name'], $this->settings['field_profile'], $this->settings['field_status'] ),
				)
			);

			if ( is_wp_error( $records ) ) {
				if ( array() === $mentors ) {
					return $records;
				}

				$error = $records->get_error_message();
			} else {
				foreach ( $records as $record ) {
					$mentors[] = $this->shape_mentor( $record );
				}
			}
		}

		return array(
			'mentors' => $mentors,
			'error'   => $error,
		);
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
	 * Read one application of this site fresh, as the row to check, or why it is not one.
	 *
	 * @param int $application The application's ID.
	 * @return array|WP_Error A row as `site_rows()` gives, or `wpcpm_checker_not_selected` for an
	 *                        application that is none or has left the selected state since the run.
	 */
	private function read_application( $application ) {
		$post = WPCPM_Mentor_Application::application( $application );

		if ( null === $post || WPCPM_Mentor_Application::STATE_SELECTED !== WPCPM_Mentor_Application::state_of( $post ) ) {
			return new WP_Error( 'wpcpm_checker_not_selected', __( 'That application is no longer waiting for the course.', 'wpcredits-program-manager' ) );
		}

		return $this->application_row( $post );
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
	 * Reduce a raw Airtable record to the keys this tool works with: the four it reads, and the two
	 * that tell it from an application of this site (`application` 0, `source` `airtable`).
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
			'id'          => isset( $record['id'] ) ? (string) $record['id'] : '',
			'application' => 0,
			'name'        => $read( 'field_name' ),
			'profile'     => $read( 'field_profile' ),
			'status'      => $read( 'field_status' ),
			'source'      => 'airtable',
		);
	}

	/**
	 * Build the queue of mentors to check.
	 *
	 * @param bool $apply Whether matches should be promoted, or made mentors, as the run proceeds.
	 * @return array|WP_Error Array with run_id, total, batch_size, queue and airtable_error keys;
	 *                        the error when Airtable could not be read and no application waits.
	 */
	public function start( $apply = false ) {
		$found = $this->get_mentors_to_check();

		if ( is_wp_error( $found ) ) {
			return $found;
		}

		$mentors = $found['mentors'];

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
				'run_id'         => $run_id,
				'started'        => time(),
				'finished'       => 0,
				'apply'          => (bool) $apply,
				'total'          => count( $mentors ),
				'source'         => $this->settings['source_status'],
				'target'         => $this->settings['target_status'],
				'course'         => $this->settings['course_title'],
				// Why Airtable's mentors were left out of this run, or '': the screen says so above the
				// results and WP-CLI after the run.
				'airtable_error' => $found['error'],
				'rows'           => array(),
				'is_partial'     => true,
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
			'run_id'         => $run_id,
			'total'          => count( $mentors ),
			'batch_size'     => max( 1, (int) $this->settings['batch_size'] ),
			'queue'          => $queue,
			'airtable_error' => $found['error'],
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

			// One message for the whole run, whichever started it, and with it anything an earlier
			// message failed to deliver - so a run that promoted nobody still sends that.
			WPCPM_Mentor_Checker_Slack::flush();
		}

		return array(
			'rows'        => $rows,
			'next_offset' => $next,
			'total'       => $total,
			'done'        => $done,
		);
	}

	/**
	 * Check a single mentor, optionally promoting them, or, for an application of this site, optionally
	 * making a mentor of them.
	 *
	 * An application's look is kept on it whatever the answer (`WPCPM_Mentor_Application::note_check()`).
	 * Where the course shows and the check applies, the application is made a mentor through
	 * `WPCPM_Mentor_Activation::activate()`; while Make mentors from applications is off it is `eligible`
	 * all the same, and says it waits for the switch.
	 *
	 * @param array $mentor   Mentor record (id, name, profile, status), and for an application of this
	 *                        site `application`, its ID.
	 * @param bool  $apply    Whether to write the target status, or make a mentor, on a match.
	 * @param bool  $use_cache Whether a cached profile result may be reused.
	 * @return array Result row.
	 */
	public function check_mentor( array $mentor, $apply = false, $use_cache = true ) {
		$result      = $this->checker->check( $mentor['profile'], $use_cache );
		$application = isset( $mentor['application'] ) ? (int) $mentor['application'] : 0;

		if ( $application > 0 ) {
			WPCPM_Mentor_Application::note_check( $application, $result['state'], $result['message'] );
		}

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

		if ( $application > 0 ) {
			return $this->make_mentor( $row, $application, $apply );
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
	 * Make a mentor of an application whose course shows, when the check applies and Make mentors from
	 * applications is on.
	 *
	 * @param array $row         The result row so far.
	 * @param int   $application The application's ID.
	 * @param bool  $apply       Whether the check applies.
	 * @return array The result row.
	 */
	private function make_mentor( array $row, $application, $apply ) {
		// Whether it waits for the switch is said when the row is read (`note_of()`), never kept on it:
		// the row outlives the moment, and the switch may be on by the time it is drawn.
		if ( ! $apply || ! WPCPM_Mentor_Activation::enabled() ) {
			$row['action']      = 'eligible';
			$row['action_note'] = self::ready_note();

			return $row;
		}

		$answer  = WPCPM_Mentor_Activation::activate( $application, self::actor() );
		$outcome = isset( $answer['outcome'] ) ? (string) $answer['outcome'] : '';
		$message = isset( $answer['message'] ) ? trim( (string) $answer['message'] ) : '';

		if ( 'activated' === $outcome ) {
			// What was held or not written on the way (a held invitation, an Airtable row refused) is
			// said after it, since the mentor is made all the same and the person pressing has to know.
			$row['action']      = 'activated';
			$row['action_note'] = trim( __( 'Made a mentor on this site.', 'wpcredits-program-manager' ) . ' ' . $message );
			$row['status']      = __( 'Made a mentor', 'wpcredits-program-manager' );
		} elseif ( in_array( $outcome, array( 'already', 'already-mentor' ), true ) ) {
			$row['action']      = 'skipped';
			$row['action_note'] = __( 'Already a mentor on this site; left unchanged.', 'wpcredits-program-manager' );
			$row['status']      = __( 'Made a mentor', 'wpcredits-program-manager' );
		} else {
			$row['action']      = 'failed';
			$row['action_note'] = '' !== $message ? $message : __( 'The application was not made a mentor.', 'wpcredits-program-manager' );
		}

		return $row;
	}

	/**
	 * Who is making mentors, for the application's history: nobody under the daily check or WP-CLI,
	 * else the person signed in.
	 *
	 * @return int A user ID, or 0.
	 */
	private static function actor() {
		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return 0;
		}

		return (int) get_current_user_id();
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

		// Kept for the Slack message the action that called this sends when it has finished.
		WPCPM_Mentor_Checker_Slack::record( $record );

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
	 * Re-check one mentor by key and promote them, or make them a mentor, if they qualify.
	 *
	 * Used by the per-row "Promote" button, which must never trust the browser's
	 * claim that a mentor passed the check. An application is read fresh, never from the
	 * profile cache, and one that is no longer selected is refused.
	 *
	 * @param string $record_id Airtable record ID, or an application's key (`app-` and its ID).
	 * @return array|WP_Error Result row.
	 */
	public function verify_and_promote( $record_id ) {
		$application = self::application_of( $record_id );
		$record      = $application > 0 ? $this->read_application( $application ) : $this->read_mentor( $record_id );

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
	 * Whether a result row is an application of this site that waits for Make mentors from
	 * applications: its course shows, so the run left it `eligible`, and the switch is off now. It is
	 * read now rather than kept on the row, so the rows of a run made while the switch was off can be
	 * promoted as soon as it is on.
	 *
	 * @param array $row A result row.
	 * @return bool
	 */
	public static function waits_for_switch( array $row ) {
		return isset( $row['action'], $row['record_id'] )
			&& 'eligible' === $row['action']
			&& self::application_of( $row['record_id'] ) > 0
			&& ! WPCPM_Mentor_Activation::enabled();
	}

	/**
	 * What a result row says beside its outcome. An application whose course shows says it is ready
	 * to be made a mentor and, while it waits for Make mentors from applications
	 * (`waits_for_switch()`), that it waits until the switch is on: worded now, from the switch as it
	 * is, whatever the row kept, so a row kept while the switch was off, an earlier release's
	 * included, reads as ready once it is on. Any other row says what it kept.
	 *
	 * @param array $row A result row.
	 * @return string
	 */
	public static function note_of( array $row ) {
		if ( self::waits_for_switch( $row ) ) {
			return __( 'Ready to be made a mentor; it waits until Make mentors from applications is switched on.', 'wpcredits-program-manager' );
		}

		if ( isset( $row['action'], $row['record_id'] ) && 'eligible' === $row['action'] && self::application_of( $row['record_id'] ) > 0 ) {
			return self::ready_note();
		}

		return isset( $row['action_note'] ) ? (string) $row['action_note'] : '';
	}

	/**
	 * The note of an application whose course shows, kept on its row.
	 *
	 * @return string
	 */
	private static function ready_note() {
		return __( 'Ready to be made a mentor.', 'wpcredits-program-manager' );
	}

	/**
	 * Summarise a run's rows into counts: an application that waits for Make mentors from
	 * applications (`waits_for_switch()`) is counted as `waiting`, never as `eligible`, since nothing
	 * can promote it until the switch is on.
	 *
	 * @param array $rows Result rows.
	 * @return array
	 */
	public static function summarize( array $rows ) {
		$summary = array(
			'checked'   => count( $rows ),
			'completed' => 0,
			'eligible'  => 0,
			'waiting'   => 0,
			'promoted'  => 0,
			'activated' => 0,
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
					++$summary[ self::waits_for_switch( $row ) ? 'waiting' : 'eligible' ];
					break;
				case 'promoted':
					++$summary['promoted'];
					break;
				case 'activated':
					++$summary['activated'];
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
	 * @param bool          $apply    Whether to promote matches and make mentors of the applications whose
	 *                                course shows.
	 * @param callable|null $progress Optional callback receiving each result row.
	 * @return array|WP_Error Array with rows, summary and airtable_error keys (why Airtable's mentors were
	 *                        left out, or '').
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
			'rows'           => $rows,
			'summary'        => self::summarize( $rows ),
			'airtable_error' => $start['airtable_error'],
		);
	}

	/**
	 * Hook the daily cron event, and schedule it while the check is switched on.
	 */
	public static function register_cron() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_cron' ) );

		$settings = WPCPM_Mentor_Checker::config();

		if ( ! empty( $settings['cron_enabled'] ) ) {
			self::schedule();
		}
	}

	/**
	 * Schedule or unschedule the daily event to match the setting.
	 *
	 * @param bool $enabled Whether the schedule should exist.
	 */
	public static function sync_cron( $enabled ) {
		if ( $enabled ) {
			self::schedule();
		} elseif ( wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Ensure the event exists on the daily recurrence.
	 *
	 * The check ran weekly until 1.122.4, when the owner asked for it every day. **The recurrence is
	 * checked, not just the existence**, as `WPCPM_Mentors_Sync::schedule()` checks its own: an event
	 * keeps the schedule it was created with, so a site that has been running the check weekly would
	 * go on running it weekly with the code saying daily. `register_cron()` calls this on every
	 * request, so the first one after the update moves the event, and the next run comes within the
	 * hour rather than at the end of the old week.
	 *
	 * The hook keeps its name, `wpcpm_checker_weekly_check`, as the mentors sync kept
	 * `wpcpm_mentors_daily` when it moved to three hours: renaming it would leave the old event to be
	 * found and cleared on every site, uninstall included, for no change in behavior.
	 */
	private static function schedule() {
		$event = wp_get_scheduled_event( self::CRON_HOOK );

		if ( $event && isset( $event->schedule ) && self::RECURRENCE === $event->schedule ) {
			return;
		}

		if ( $event ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, self::RECURRENCE, self::CRON_HOOK );
	}

	/**
	 * The daily cron callback.
	 */
	public static function run_cron() {
		$settings = WPCPM_Mentor_Checker::config();

		// With or without the Airtable connection: the site's applications are checked either way, and
		// Airtable's mentors only while it is connected (`reads_airtable()`).
		if ( empty( $settings['cron_enabled'] ) ) {
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
