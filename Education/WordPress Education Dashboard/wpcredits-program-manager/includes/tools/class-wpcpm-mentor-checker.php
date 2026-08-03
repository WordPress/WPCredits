<?php
/**
 * Tool — Mentor Status Checker.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Promotes vetted mentors to Active once their WordPress.org profile shows the
 * mentor's course completion.
 *
 * Was the standalone "Credits Program Mentor Checker" plugin. Folded in here so
 * there is one Airtable connection, one settings screen and one place to look —
 * the runner and profile reader are that plugin's, largely unchanged; what
 * changed is that they now read the shared connection instead of their own.
 */
class WPCPM_Mentor_Checker extends WPCPM_Tool {

	const NONCE = 'wpcpm_checker_run';

	/**
	 * Tool ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'mentor-status-checker';
	}

	/**
	 * Tool label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Mentor Status Checker', 'wpcredits-program-manager' );
	}

	/**
	 * Tool description.
	 *
	 * @return string
	 */
	public function description() {
		$settings = self::config();

		return sprintf(
			/* translators: 1: source status, 2: course title, 3: target status. */
			__( 'Checks every mentor whose Airtable status is "%1$s" against their WordPress.org profile, and moves those who have completed "%2$s" to "%3$s".', 'wpcredits-program-manager' ),
			$settings['source_status'],
			$settings['course_title'],
			$settings['target_status']
		);
	}

	/**
	 * The configuration the runner and profile reader expect.
	 *
	 * Deliberately shaped like the standalone plugin's settings array, so the two
	 * ported classes needed no rewriting: the Airtable connection and the Mentors
	 * table now come from the shared plugin settings, and the field names from the
	 * Mentors module, while the checker keeps its own `checker_*` options.
	 *
	 * @return array
	 */
	public static function config() {
		$settings = WPCPM_Settings::get();
		$fields   = WPCPM_Mentors_Sync::fields();

		return array(
			// Shared connection.
			'api_token'         => $settings['api_token'],
			'base_id'           => $settings['base_id'],
			'table_id'          => $settings['mentors_table'],
			'view_id'           => '',

			// Shared field names, from the Mentors module.
			'field_name'        => $fields['mentor_name'],
			'field_profile'     => $fields['mentor_profile'],
			'field_status'      => $fields['mentor_status'],

			// This tool's own settings.
			'source_status'     => $settings['checker_source_status'],
			'target_status'     => $settings['checker_target_status'],
			'course_slug'       => $settings['checker_course_slug'],
			'course_title'      => $settings['checker_course_title'],
			'completion_phrase' => $settings['checker_completion_phrase'],
			'timeline_filter'   => $settings['checker_timeline_filter'],
			'max_pages'         => $settings['checker_max_pages'],
			'batch_size'        => $settings['checker_batch_size'],
			'request_delay'     => $settings['checker_request_delay'],
			'profile_cache_ttl' => $settings['checker_cache_ttl'],
			'cron_enabled'      => $settings['checker_cron_enabled'],
			'cron_promotes'     => $settings['checker_cron_promotes'],
		);
	}

	/**
	 * Whether the tool can reach Airtable.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return WPCPM_Settings::is_connected();
	}

	/**
	 * Hooks.
	 */
	public function boot() {
		WPCPM_Mentor_Checker_Runner::register_cron();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wpcpm_checker_flush_cache', array( $this, 'handle_flush_cache' ) );
		add_action( 'admin_post_wpcpm_checker_clear_results', array( $this, 'handle_clear_results' ) );

		add_action( 'wp_ajax_wpcpm_checker_start_run', array( $this, 'ajax_start_run' ) );
		add_action( 'wp_ajax_wpcpm_checker_process_batch', array( $this, 'ajax_process_batch' ) );
		add_action( 'wp_ajax_wpcpm_checker_promote', array( $this, 'ajax_promote' ) );
		add_action( 'wp_ajax_wpcpm_checker_promote_all', array( $this, 'ajax_promote_all' ) );
	}

	/**
	 * Deactivation: drop the weekly schedule.
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( WPCPM_Mentor_Checker_Runner::CRON_HOOK );
	}

	/**
	 * Uninstall: drop stored results and cached profile reads.
	 */
	public function uninstall() {
		WPCPM_Mentor_Checker_Runner::clear_last_run();
		WPCPM_Mentor_Checker_Profile::flush_cache();
		wp_clear_scheduled_hook( WPCPM_Mentor_Checker_Runner::CRON_HOOK );
	}

	/**
	 * A status line for the Tools screen.
	 *
	 * @return string
	 */
	public function status_line() {
		$run = WPCPM_Mentor_Checker_Runner::get_last_run();

		if ( empty( $run['started'] ) ) {
			return __( 'Never run.', 'wpcredits-program-manager' );
		}

		$summary = WPCPM_Mentor_Checker_Runner::summarize( isset( $run['rows'] ) ? $run['rows'] : array() );

		return sprintf(
			/* translators: 1: human-readable time difference, 2: number checked, 3: number awaiting promotion. */
			__( 'Last run %1$s ago — %2$s checked, %3$s awaiting promotion.', 'wpcredits-program-manager' ),
			human_time_diff( (int) $run['started'], time() ),
			number_format_i18n( $summary['checked'] ),
			number_format_i18n( $summary['eligible'] )
		);
	}

	/**
	 * Load the tool's own CSS and JS on its screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, $this->page_slug() ) ) {
			return;
		}

		wp_enqueue_style(
			'wpcpm-mentor-checker',
			WPCPM_PLUGIN_URL . 'assets/css/mentor-checker.css',
			array( 'wpcpm-admin' ),
			WPCPM_VERSION
		);

		wp_enqueue_script(
			'wpcpm-mentor-checker',
			WPCPM_PLUGIN_URL . 'assets/js/mentor-checker.js',
			array(),
			WPCPM_VERSION,
			true
		);

		$settings = self::config();

		wp_localize_script(
			'wpcpm-mentor-checker',
			'wpcpmCheckerData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'i18n'    => array(
					'starting'     => __( 'Reading mentors from Airtable…', 'wpcredits-program-manager' ),
					'checking'     => __( 'Reading WordPress.org profiles…', 'wpcredits-program-manager' ),
					/* translators: 1: mentors checked so far, 2: mentors in the run. */
					'progress'     => __( 'Checked %1$d of %2$d mentors.', 'wpcredits-program-manager' ),
					/* translators: 1: mentors checked so far, 2: mentors in the run, 3: percentage complete. */
					'count'        => __( '%1$d of %2$d · %3$d%%', 'wpcredits-program-manager' ),
					/* translators: 1: number who completed the course, 2: number needing attention, 3: elapsed time as m:ss. */
					'meta'         => __( '%1$d completed the course · %2$d need attention · %3$s elapsed', 'wpcredits-program-manager' ),
					'etaOne'       => __( 'about 1 minute left', 'wpcredits-program-manager' ),
					/* translators: %d: whole minutes remaining. */
					'etaMany'      => __( 'about %d minutes left', 'wpcredits-program-manager' ),
					'etaSoon'      => __( 'almost done', 'wpcredits-program-manager' ),
					'doneLabel'    => __( 'Finished', 'wpcredits-program-manager' ),
					'stoppedLabel' => __( 'Stopped', 'wpcredits-program-manager' ),
					/* translators: %d: number of mentors checked. */
					'done'         => __( 'Finished. Checked %d mentors.', 'wpcredits-program-manager' ),
					/* translators: %s: the error that stopped the run. */
					'failed'       => __( 'The run stopped: %s', 'wpcredits-program-manager' ),
					'promoting'    => __( 'Promoting…', 'wpcredits-program-manager' ),
					'promote'      => __( 'Promote', 'wpcredits-program-manager' ),
					'confirmApply' => sprintf(
						/* translators: %s: target status name, e.g. "Active". */
						__( 'This changes mentor statuses to "%s" in the shared Airtable base. Continue?', 'wpcredits-program-manager' ),
						$settings['target_status']
					),
					'confirmOne'   => __( 'Re-check this mentor and update their Airtable status?', 'wpcredits-program-manager' ),
				),
			)
		);
	}

	/**
	 * Clear the cached profile results.
	 */
	public function handle_flush_cache() {
		$this->verify_request( 'wpcpm_checker_flush_cache' );

		WPCPM_Mentor_Checker_Profile::flush_cache();

		wp_safe_redirect( add_query_arg( 'wpcpm_notice', 'cache-flushed', $this->admin_url() ) );
		exit;
	}

	/**
	 * Discard the stored results of the last run.
	 */
	public function handle_clear_results() {
		$this->verify_request( 'wpcpm_checker_clear_results' );

		WPCPM_Mentor_Checker_Runner::clear_last_run();

		wp_safe_redirect( add_query_arg( 'wpcpm_notice', 'results-cleared', $this->admin_url() ) );
		exit;
	}

	/**
	 * AJAX: build the queue of mentors to check.
	 */
	public function ajax_start_run() {
		$this->verify_ajax();

		$apply  = ! empty( $_POST['apply'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in verify_ajax().
		$runner = new WPCPM_Mentor_Checker_Runner();
		$start  = $runner->start( $apply );

		if ( is_wp_error( $start ) ) {
			wp_send_json_error( array( 'message' => $start->get_error_message() ) );
		}

		$start['queue'] = array_map( array( $this, 'prepare_row_for_js' ), $start['queue'] );

		wp_send_json_success( $start );
	}

	/**
	 * AJAX: check one batch of mentors.
	 */
	public function ajax_process_batch() {
		$this->verify_ajax();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in verify_ajax().
		// Not sanitize_key(): it lower-cases, which would no longer match the run ID
		// the queue was stored under. Stripping to the alphanumerics is the sanitizer here —
		// phpcs does not recognise preg_replace() as one, hence the annotation.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by the preg_replace() on this line.
		$run_id = isset( $_POST['run_id'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_POST['run_id'] ) ) : '';
		$offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $run_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing run identifier.', 'wpcredits-program-manager' ) ) );
		}

		$runner = new WPCPM_Mentor_Checker_Runner();
		$batch  = $runner->process_batch( $run_id, $offset );

		if ( is_wp_error( $batch ) ) {
			wp_send_json_error( array( 'message' => $batch->get_error_message() ) );
		}

		$batch['rows'] = array_map( array( $this, 'prepare_row_for_js' ), $batch['rows'] );

		wp_send_json_success( $batch );
	}

	/**
	 * AJAX: re-check and promote a single mentor.
	 */
	public function ajax_promote() {
		$this->verify_ajax();

		$record_id = isset( $_POST['record_id'] ) ? sanitize_text_field( wp_unslash( $_POST['record_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in verify_ajax().

		if ( ! preg_match( '/^rec[A-Za-z0-9]{14}$/', $record_id ) ) {
			wp_send_json_error( array( 'message' => __( 'That does not look like an Airtable record ID.', 'wpcredits-program-manager' ) ) );
		}

		$runner = new WPCPM_Mentor_Checker_Runner();
		$row    = $runner->verify_and_promote( $record_id );

		if ( is_wp_error( $row ) ) {
			wp_send_json_error( array( 'message' => $row->get_error_message() ) );
		}

		wp_send_json_success( array( 'row' => $this->prepare_row_for_js( $row ) ) );
	}

	/**
	 * AJAX: promote every mentor the last run marked eligible.
	 */
	public function ajax_promote_all() {
		$this->verify_ajax();

		$run  = WPCPM_Mentor_Checker_Runner::get_last_run();
		$rows = isset( $run['rows'] ) ? $run['rows'] : array();
		$ids  = array();

		foreach ( $rows as $row ) {
			if ( isset( $row['action'] ) && 'eligible' === $row['action'] && ! empty( $row['record_id'] ) ) {
				$ids[] = $row['record_id'];
			}
		}

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No mentors are waiting to be promoted.', 'wpcredits-program-manager' ) ) );
		}

		$runner  = new WPCPM_Mentor_Checker_Runner();
		$updated = array();

		foreach ( $ids as $record_id ) {
			$result = $runner->verify_and_promote( $record_id );

			if ( is_wp_error( $result ) ) {
				$updated[] = $this->prepare_row_for_js(
					array(
						'record_id'   => $record_id,
						'name'        => '',
						'action'      => 'failed',
						'action_note' => $result->get_error_message(),
					)
				);
				continue;
			}

			$updated[] = $this->prepare_row_for_js( $result );
		}

		wp_send_json_success( array( 'rows' => $updated ) );
	}

	/**
	 * Capability and nonce check for the form posts.
	 *
	 * @param string $action Nonce action.
	 */
	private function verify_request( $action ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Reject AJAX requests that are unauthenticated or lack a valid nonce.
	 */
	private function verify_ajax() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ) ), 403 );
		}

		check_ajax_referer( self::NONCE, 'nonce' );
	}

	/**
	 * Add the rendered fragments the JS needs to a result row.
	 *
	 * @param array $row Result row.
	 * @return array
	 */
	public function prepare_row_for_js( array $row ) {
		$row         = self::normalize_row( $row );
		$row['html'] = $this->render_row( $row );

		return $row;
	}

	/**
	 * Fill in any keys a result row is missing.
	 *
	 * Rows come from three places — a batch, a manual promotion, and the stored log
	 * of an earlier run — so nothing downstream should assume a complete shape.
	 *
	 * @param array $row Result row.
	 * @return array
	 */
	private static function normalize_row( array $row ) {
		return wp_parse_args(
			$row,
			array(
				'record_id'   => '',
				'name'        => '',
				'profile'     => '',
				'username'    => '',
				'status'      => '',
				'completed'   => false,
				'state'       => 'error',
				'message'     => '',
				'timestamp'   => 0,
				'pages'       => 0,
				'cached'      => false,
				'action'      => 'none',
				'action_note' => '',
			)
		);
	}

	/**
	 * Render one result table row.
	 *
	 * @param array $row Result row.
	 * @return string
	 */
	private function render_row( array $row ) {
		$row = self::normalize_row( $row );

		$states = array(
			'pending'       => array(
				'label' => __( 'Queued', 'wpcredits-program-manager' ),
				'class' => 'wpcpm-checker-pill wpcpm-checker-pill--pending',
			),
			'completed'     => array(
				'label' => __( 'Completed', 'wpcredits-program-manager' ),
				'class' => 'wpcpm-checker-pill wpcpm-checker-pill--yes',
			),
			'not_completed' => array(
				'label' => __( 'Not completed', 'wpcredits-program-manager' ),
				'class' => 'wpcpm-checker-pill wpcpm-checker-pill--no',
			),
			'no_username'   => array(
				'label' => __( 'No username', 'wpcredits-program-manager' ),
				'class' => 'wpcpm-checker-pill wpcpm-checker-pill--warn',
			),
			'not_found'     => array(
				'label' => __( 'Profile not found', 'wpcredits-program-manager' ),
				'class' => 'wpcpm-checker-pill wpcpm-checker-pill--warn',
			),
			'error'         => array(
				'label' => __( 'Could not check', 'wpcredits-program-manager' ),
				'class' => 'wpcpm-checker-pill wpcpm-checker-pill--warn',
			),
		);

		$actions = array(
			'none'     => array(
				'label' => '—',
				'class' => '',
			),
			'eligible' => array(
				'label' => __( 'Awaiting promotion', 'wpcredits-program-manager' ),
				'class' => 'wpcpm-checker-action wpcpm-checker-action--eligible',
			),
			'promoted' => array(
				'label' => __( 'Promoted', 'wpcredits-program-manager' ),
				'class' => 'wpcpm-checker-action wpcpm-checker-action--promoted',
			),
			'skipped'  => array(
				'label' => __( 'Skipped', 'wpcredits-program-manager' ),
				'class' => 'wpcpm-checker-action wpcpm-checker-action--skipped',
			),
			'failed'   => array(
				'label' => __( 'Failed', 'wpcredits-program-manager' ),
				'class' => 'wpcpm-checker-action wpcpm-checker-action--failed',
			),
		);

		$state  = isset( $states[ $row['state'] ] ) ? $states[ $row['state'] ] : $states['error'];
		$action = isset( $actions[ $row['action'] ] ) ? $actions[ $row['action'] ] : $actions['none'];

		ob_start();
		?>
		<tr data-record-id="<?php echo esc_attr( $row['record_id'] ); ?>" data-action="<?php echo esc_attr( $row['action'] ); ?>">
			<td class="wpcpm-checker-col-name">
				<strong><?php echo esc_html( '' !== $row['name'] ? $row['name'] : __( '(unnamed)', 'wpcredits-program-manager' ) ); ?></strong>
			</td>
			<td class="wpcpm-checker-col-profile">
				<?php if ( '' !== $row['username'] ) : ?>
					<a href="<?php echo esc_url( 'https://profiles.wordpress.org/' . $row['username'] . '/' ); ?>" target="_blank" rel="noopener noreferrer">
						@<?php echo esc_html( $row['username'] ); ?>
					</a>
				<?php else : ?>
					<span class="wpcpm-checker-muted"><?php echo esc_html( '' !== $row['profile'] ? $row['profile'] : __( '(empty)', 'wpcredits-program-manager' ) ); ?></span>
				<?php endif; ?>
			</td>
			<td class="wpcpm-checker-col-course">
				<?php $is_pending = ( 'pending' === $row['state'] ); ?>
				<?php /* Only a queued row swaps its label for the spinner, so a resolved row keeps its pill if it is ever marked busy. */ ?>
				<span class="<?php echo esc_attr( $state['class'] ); ?><?php echo $is_pending ? ' wpcpm-checker-when-queued' : ''; ?>"><?php echo esc_html( $state['label'] ); ?></span>
				<?php if ( $is_pending ) : ?>
					<span class="wpcpm-checker-pill wpcpm-checker-pill--checking wpcpm-checker-when-checking">
						<span class="wpcpm-checker-spinner" aria-hidden="true"></span>
						<?php esc_html_e( 'Checking…', 'wpcredits-program-manager' ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $row['timestamp'] > 0 ) : ?>
					<span class="wpcpm-checker-date"><?php echo esc_html( wp_date( get_option( 'date_format' ), $row['timestamp'] ) ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $row['message'] ) : ?>
					<span class="wpcpm-checker-note"><?php echo esc_html( $row['message'] ); ?></span>
				<?php endif; ?>
			</td>
			<td class="wpcpm-checker-col-status"><?php echo esc_html( $row['status'] ); ?></td>
			<td class="wpcpm-checker-col-action">
				<?php if ( '' !== $action['class'] ) : ?>
					<span class="<?php echo esc_attr( $action['class'] ); ?>"><?php echo esc_html( $action['label'] ); ?></span>
				<?php else : ?>
					<span class="wpcpm-checker-muted"><?php echo esc_html( $action['label'] ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $row['action_note'] ) : ?>
					<span class="wpcpm-checker-note"><?php echo esc_html( $row['action_note'] ); ?></span>
				<?php endif; ?>
				<?php if ( 'eligible' === $row['action'] && '' !== $row['record_id'] ) : ?>
					<button type="button" class="button button-small wpcpm-checker-promote" data-record-id="<?php echo esc_attr( $row['record_id'] ); ?>">
						<?php esc_html_e( 'Promote', 'wpcredits-program-manager' ); ?>
					</button>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Whether the plugin this tool replaces is still active.
	 *
	 * Both would schedule their own weekly check and scrape the same profiles, so
	 * it is worth saying out loud rather than letting the work silently double.
	 *
	 * @return bool
	 */
	private static function standalone_plugin_is_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'credits-program-mentor-checker/credits-program-mentor-checker.php' );
	}

	/**
	 * Render the tool's screen.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$settings   = self::config();
		$configured = self::is_configured();
		$run        = WPCPM_Mentor_Checker_Runner::get_last_run();
		$rows       = isset( $run['rows'] ) ? $run['rows'] : array();
		$summary    = WPCPM_Mentor_Checker_Runner::summarize( $rows );
		$notice     = WPCPM_Request::key( 'wpcpm_notice' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
		?>
		<div class="wrap wpcpm-wrap wpcpm-checker-wrap">
			<h1><?php echo esc_html( $this->label() ); ?></h1>

			<?php if ( 'cache-flushed' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cached profile results cleared.', 'wpcredits-program-manager' ); ?></p></div>
			<?php elseif ( 'results-cleared' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Stored results cleared.', 'wpcredits-program-manager' ); ?></p></div>
			<?php endif; ?>

			<?php if ( self::standalone_plugin_is_active() ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'The standalone Credits Program Mentor Checker plugin is still active.', 'wpcredits-program-manager' ); ?></strong>
						<?php esc_html_e( 'This tool replaces it. Both will run their own weekly check and read the same WordPress.org profiles twice, so deactivate the standalone plugin.', 'wpcredits-program-manager' ); ?>
						<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Open plugins', 'wpcredits-program-manager' ); ?></a>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( ! $configured ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: %s: link to the settings screen. */
							esc_html__( 'Add an Airtable Personal Access Token before running a check. %s', 'wpcredits-program-manager' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=wpcpm-settings' ) ) . '">' . esc_html__( 'Open settings', 'wpcredits-program-manager' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<p class="wpcpm-lede">
				<?php
				printf(
					/* translators: 1: source status, 2: course title, 3: target status. */
					esc_html__( 'Every mentor whose Airtable status is %1$s is looked up on WordPress.org. If their contribution history records completing %2$s, their status is changed to %3$s.', 'wpcredits-program-manager' ),
					'<code>' . esc_html( $settings['source_status'] ) . '</code>',
					'<em>' . esc_html( $settings['course_title'] ) . '</em>',
					'<code>' . esc_html( $settings['target_status'] ) . '</code>'
				);
				?>
			</p>

			<p class="description">
				<?php esc_html_e( 'Promoting writes to the shared Airtable base, so the token also needs the "data.records:write" scope. "Report only" never writes anything.', 'wpcredits-program-manager' ); ?>
			</p>

			<div class="wpcpm-checker-controls">
				<button type="button" class="button button-primary" id="wpcpm-checker-run-dry" <?php disabled( ! $configured ); ?>>
					<?php esc_html_e( 'Run check (report only)', 'wpcredits-program-manager' ); ?>
				</button>
				<button type="button" class="button" id="wpcpm-checker-run-apply" <?php disabled( ! $configured ); ?>>
					<?php
					printf(
						/* translators: %s: target status name. */
						esc_html__( 'Run check and promote to %s', 'wpcredits-program-manager' ),
						esc_html( $settings['target_status'] )
					);
					?>
				</button>
				<button type="button" class="button" id="wpcpm-checker-promote-all" <?php disabled( ! $configured || 0 === $summary['eligible'] ); ?>>
					<?php esc_html_e( 'Promote all eligible', 'wpcredits-program-manager' ); ?>
					<span id="wpcpm-checker-eligible-count">(<?php echo esc_html( number_format_i18n( $summary['eligible'] ) ); ?>)</span>
				</button>

				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="wpcpm-checker-inline-form">
					<?php wp_nonce_field( 'wpcpm_checker_flush_cache' ); ?>
					<input type="hidden" name="action" value="wpcpm_checker_flush_cache" />
					<button type="submit" class="button button-link"><?php esc_html_e( 'Clear cached profile results', 'wpcredits-program-manager' ); ?></button>
				</form>

				<?php if ( ! empty( $rows ) ) : ?>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="wpcpm-checker-inline-form">
						<?php wp_nonce_field( 'wpcpm_checker_clear_results' ); ?>
						<input type="hidden" name="action" value="wpcpm_checker_clear_results" />
						<button type="submit" class="button button-link"><?php esc_html_e( 'Clear results', 'wpcredits-program-manager' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<p class="wpcpm-checker-status" id="wpcpm-checker-status" role="status" aria-live="polite">
				<?php
				if ( ! empty( $run['started'] ) ) {
					printf(
						/* translators: 1: human-readable time difference, 2: "report only" or "promoting" mode label. */
						esc_html__( 'Last run %1$s ago (%2$s).', 'wpcredits-program-manager' ),
						esc_html( human_time_diff( (int) $run['started'], time() ) ),
						empty( $run['apply'] ) ? esc_html__( 'report only', 'wpcredits-program-manager' ) : esc_html__( 'promoting', 'wpcredits-program-manager' )
					);

					if ( ! empty( $run['is_partial'] ) ) {
						echo ' <strong>' . esc_html__( 'This run did not finish.', 'wpcredits-program-manager' ) . '</strong>';
					}
				}
				?>
			</p>

			<div class="wpcpm-checker-progress" id="wpcpm-checker-progress" hidden>
				<div class="wpcpm-checker-progress__head">
					<span class="wpcpm-checker-progress__label" id="wpcpm-checker-progress-label"></span>
					<span class="wpcpm-checker-progress__count" id="wpcpm-checker-progress-count"></span>
				</div>
				<div class="wpcpm-checker-progress__bar" role="progressbar" id="wpcpm-checker-progress-bar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
					<span id="wpcpm-checker-progress-fill"></span>
				</div>
				<div class="wpcpm-checker-progress__meta">
					<span id="wpcpm-checker-progress-meta"></span>
					<span class="wpcpm-checker-progress__eta" id="wpcpm-checker-progress-eta"></span>
				</div>
			</div>

			<div class="wpcpm-checker-summary" id="wpcpm-checker-summary" <?php echo empty( $rows ) ? 'hidden' : ''; ?>>
				<span class="wpcpm-checker-stat"><b data-stat="checked"><?php echo esc_html( number_format_i18n( $summary['checked'] ) ); ?></b> <?php esc_html_e( 'checked', 'wpcredits-program-manager' ); ?></span>
				<span class="wpcpm-checker-stat"><b data-stat="completed"><?php echo esc_html( number_format_i18n( $summary['completed'] ) ); ?></b> <?php esc_html_e( 'completed the course', 'wpcredits-program-manager' ); ?></span>
				<span class="wpcpm-checker-stat"><b data-stat="eligible"><?php echo esc_html( number_format_i18n( $summary['eligible'] ) ); ?></b> <?php esc_html_e( 'awaiting promotion', 'wpcredits-program-manager' ); ?></span>
				<span class="wpcpm-checker-stat"><b data-stat="promoted"><?php echo esc_html( number_format_i18n( $summary['promoted'] ) ); ?></b> <?php esc_html_e( 'promoted', 'wpcredits-program-manager' ); ?></span>
				<span class="wpcpm-checker-stat"><b data-stat="problems"><?php echo esc_html( number_format_i18n( $summary['problems'] ) ); ?></b> <?php esc_html_e( 'need attention', 'wpcredits-program-manager' ); ?></span>
			</div>

			<table class="wp-list-table widefat striped wpcpm-checker-results">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Mentor', 'wpcredits-program-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'WordPress.org', 'wpcredits-program-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Course', 'wpcredits-program-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Airtable status', 'wpcredits-program-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Outcome', 'wpcredits-program-manager' ); ?></th>
					</tr>
				</thead>
				<tbody id="wpcpm-checker-results-body">
					<?php if ( empty( $rows ) ) : ?>
						<tr class="wpcpm-checker-empty-row">
							<td colspan="5"><?php esc_html_e( 'No results yet. Run a check to see where each vetted mentor stands.', 'wpcredits-program-manager' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php echo $this->render_row( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render_row(). ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
