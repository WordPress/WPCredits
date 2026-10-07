<?php
/**
 * Settings screen: Airtable connection fields, a manual sync trigger, a sync
 * status panel, and the unmapped-countries and unrecognised-statuses reports.
 *
 * @package WPCC_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCCT_Settings {

	const OPTION_GROUP = 'wpcct_settings_group';
	const PAGE_SLUG    = 'wpcct-settings';

	/** Nonce action shared by the no-JS form and both AJAX endpoints. */
	const SYNC_NONCE_ACTION = 'wpcct_sync';

	/** @var WPCCT_Settings|null */
	private static $instance = null;

	/**
	 * Hook suffix returned by add_menu_page(), used to load admin.js/admin.css
	 * only on this plugin's own page rather than on every wp-admin screen.
	 *
	 * @var string
	 */
	private static $hook_suffix = '';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_wpcct_sync', array( __CLASS__, 'handle_sync' ) );
		add_action( 'wp_ajax_wpcct_sync_start', array( __CLASS__, 'ajax_sync_start' ) );
		add_action( 'wp_ajax_wpcct_sync_progress', array( __CLASS__, 'ajax_sync_progress' ) );
	}

	/**
	 * Register the top-level "WPCC-Tracker" wp-admin menu item.
	 *
	 * Position 58.7 is a float deliberately chosen to avoid a whole-number
	 * collision with another plugin's menu entry: it sits just above core's
	 * second separator (59), i.e. below the content-management cluster
	 * (Posts/Media/Pages/Comments) and above Appearance/Plugins/Users/Settings.
	 */
	public static function menu() {
		self::$hook_suffix = add_menu_page(
			__( 'WPCC-Tracker', 'wpcc-tracker' ),
			__( 'WPCC-Tracker', 'wpcc-tracker' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-chart-area',
			58.7
		);
	}

	/**
	 * Enqueue the progress-bar script/style, only on this plugin's own page.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( empty( self::$hook_suffix ) || self::$hook_suffix !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wpcct-admin',
			WPCCT_URL . 'assets/css/admin.css',
			array(),
			wpcct_asset_version( 'assets/css/admin.css' )
		);

		wp_enqueue_script(
			'wpcct-admin',
			WPCCT_URL . 'assets/js/admin.js',
			array(),
			wpcct_asset_version( 'assets/js/admin.js' ),
			true
		);

		wp_localize_script(
			'wpcct-admin',
			'wpcctAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::SYNC_NONCE_ACTION ),
				'actions' => array(
					'start'    => 'wpcct_sync_start',
					'progress' => 'wpcct_sync_progress',
				),
				'stages'  => WPCCT_Sync::STAGES,
				'i18n'    => array(
					'starting'     => __( 'Starting…', 'wpcc-tracker' ),
					'error'        => __( 'Sync failed.', 'wpcc-tracker' ),
					'done'         => __( 'Sync complete.', 'wpcc-tracker' ),
					'eventsLabel'  => __( 'events', 'wpcc-tracker' ),
					'markersLabel' => __( 'map markers', 'wpcc-tracker' ),
				),
			)
		);
	}

	/**
	 * AJAX: start a sync and wait for it to finish.
	 *
	 * This call blocks for the duration of the sync, same as the no-JS
	 * admin-post handler - the progress bar is driven by the concurrent
	 * ajax_sync_progress() polling requests, handled by other PHP worker
	 * processes while this one is still running, not by this response.
	 */
	public static function ajax_sync_start() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'wpcc-tracker' ) ), 403 );
		}

		check_ajax_referer( self::SYNC_NONCE_ACTION, 'nonce' );

		/* run() clears the previous run's progress itself, once it holds the
		   run lock. Doing it here instead would let a refused concurrent click
		   wipe the record of the run already in flight. */
		$result = WPCCT_Sync::run();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Sync complete.', 'wpcc-tracker' ) ) );
	}

	/**
	 * AJAX: read the current sync progress.
	 *
	 * The response never contains settings of any kind - only stage,
	 * message, counters, and the server's own clock - so it cannot leak the
	 * Airtable token even indirectly.
	 *
	 * 'now' is the server's current timestamp. The poller needs it to decide
	 * whether a reading belongs to the run it just started: started_at is a
	 * server timestamp, and comparing it against the browser's clock misjudges
	 * every reading as stale on a machine whose clock is even slightly behind.
	 */
	public static function ajax_sync_progress() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'wpcc-tracker' ) ), 403 );
		}

		check_ajax_referer( self::SYNC_NONCE_ACTION, 'nonce' );

		wp_send_json_success( array_merge( WPCCT_Sync::get_progress(), array( 'now' => time() ) ) );
	}

	/**
	 * Register the Settings API fields.
	 */
	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			WPCCT_OPT_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'wpcct_main',
			__( 'Airtable connection', 'wpcc-tracker' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'wpcct_airtable_pat',
			__( 'Airtable personal access token', 'wpcc-tracker' ),
			array( __CLASS__, 'field_pat' ),
			self::PAGE_SLUG,
			'wpcct_main'
		);

		add_settings_field(
			'wpcct_base_id',
			__( 'Base ID', 'wpcc-tracker' ),
			array( __CLASS__, 'field_text' ),
			self::PAGE_SLUG,
			'wpcct_main',
			array( 'key' => 'base_id' )
		);

		add_settings_field(
			'wpcct_events_table',
			__( 'Events table', 'wpcc-tracker' ),
			array( __CLASS__, 'field_text' ),
			self::PAGE_SLUG,
			'wpcct_main',
			array( 'key' => 'events_table' )
		);

		add_settings_field(
			'wpcct_wordcamps_table',
			__( 'WordCamps table', 'wpcc-tracker' ),
			array( __CLASS__, 'field_text' ),
			self::PAGE_SLUG,
			'wpcct_main',
			array( 'key' => 'wordcamps_table' )
		);
	}

	/**
	 * Sanitise the submitted settings.
	 *
	 * The Airtable token is the one sensitive field on this page: it is never
	 * echoed back into the form (see field_pat()), so a blank submission is
	 * indistinguishable from "leave it as it was" and is treated that way
	 * - the stored token is preserved rather than being wiped out.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$existing = WPCCT_Sync::settings();
		$out      = array();

		$pat = isset( $input['airtable_pat'] ) ? sanitize_text_field( wp_unslash( $input['airtable_pat'] ) ) : '';
		$out['airtable_pat'] = ( '' === $pat ) ? $existing['airtable_pat'] : $pat;

		foreach ( array( 'base_id', 'events_table', 'wordcamps_table' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] )
				? sanitize_text_field( wp_unslash( $input[ $key ] ) )
				: $existing[ $key ];
		}

		return $out;
	}

	/**
	 * Render the Airtable PAT field. The stored value is never echoed - only
	 * a placeholder indicating whether a token is already saved.
	 */
	public static function field_pat() {
		$settings  = WPCCT_Sync::settings();
		$has_token = ! empty( $settings['airtable_pat'] );
		$placeholder = $has_token
			? '••••••••••••••••'
			: __( 'e.g. patXXXXXXXXXXXXXX', 'wpcc-tracker' );
		?>
		<input
			type="password"
			id="wpcct_airtable_pat"
			name="<?php echo esc_attr( WPCCT_OPT_SETTINGS ); ?>[airtable_pat]"
			value=""
			autocomplete="off"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			class="regular-text"
		/>
		<?php if ( $has_token ) : ?>
			<p class="description"><?php esc_html_e( 'A token is already stored. Leave this field blank to keep it unchanged.', 'wpcc-tracker' ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No token is stored yet. Sync now will not work until one is saved.', 'wpcc-tracker' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a plain text settings field.
	 *
	 * @param array $args { 'key' => option key within WPCCT_OPT_SETTINGS }.
	 */
	public static function field_text( $args ) {
		$key      = $args['key'];
		$settings = WPCCT_Sync::settings();
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		?>
		<input
			type="text"
			id="wpcct_<?php echo esc_attr( $key ); ?>"
			name="<?php echo esc_attr( WPCCT_OPT_SETTINGS ); ?>[<?php echo esc_attr( $key ); ?>]"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<?php
	}

	/**
	 * Handle the "Sync now" button: verify nonce and capability, run the
	 * sync, and redirect back to the settings page with the outcome.
	 */
	public static function handle_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wpcc-tracker' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::SYNC_NONCE_ACTION, 'wpcct_sync_nonce' );

		/* As in ajax_sync_start(): the previous run's terminal state is
		   cleared by run() itself, after it takes the run lock. */
		$result = WPCCT_Sync::run();

		$redirect = add_query_arg(
			array(
				'page'              => self::PAGE_SLUG,
				'wpcct_sync_result' => is_wp_error( $result ) ? 'error' : 'success',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render the settings page: fields form, Sync now button, status panel,
	 * and the unmapped-countries and unrecognised-statuses reports.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WPCC-Tracker', 'wpcc-tracker' ); ?></h1>

			<?php self::render_sync_notice(); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save Settings', 'wpcc-tracker' ) );
				?>
			</form>

			<h2><?php esc_html_e( 'Manual sync', 'wpcc-tracker' ); ?></h2>
			<p><?php esc_html_e( 'Runs immediately. A daily sync also runs automatically in the background.', 'wpcc-tracker' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wpcct-sync-form">
				<input type="hidden" name="action" value="wpcct_sync" />
				<?php wp_nonce_field( self::SYNC_NONCE_ACTION, 'wpcct_sync_nonce' ); ?>
				<?php submit_button( __( 'Sync now', 'wpcc-tracker' ), 'secondary', 'submit', false, array( 'id' => 'wpcct-sync-submit' ) ); ?>
			</form>

			<?php /* Progressive enhancement only: with JavaScript disabled or
			admin.js failing to load, this stays hidden and the form above
			submits normally to admin-post.php, exactly as before. */ ?>
			<div id="wpcct-sync-progress" class="wpcct-sync-progress" hidden aria-live="polite">
				<div class="wpcct-sync-progress__bar">
					<div id="wpcct-sync-progress-fill" class="wpcct-sync-progress__fill"></div>
				</div>
				<p id="wpcct-sync-progress-stage" class="wpcct-sync-progress__stage"></p>
			</div>

			<?php
			self::render_status();
			self::render_unmapped();
			self::render_unmapped_statuses();
			?>
		</div>
		<?php
	}

	/**
	 * Show an admin notice with the outcome of the last "Sync now" click.
	 */
	private static function render_sync_notice() {
		if ( ! isset( $_GET['wpcct_sync_result'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$result = sanitize_text_field( wp_unslash( $_GET['wpcct_sync_result'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'success' === $result ) {
			echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__( 'Sync completed successfully.', 'wpcc-tracker' ) .
				'</p></div>';
		} elseif ( 'error' === $result ) {
			echo '<div class="notice notice-error is-dismissible"><p>' .
				esc_html__( 'Sync failed. See the error below for details.', 'wpcc-tracker' ) .
				'</p></div>';
		}
	}

	/**
	 * Status panel: last sync time, last error, and current headline totals.
	 */
	private static function render_status() {
		$last_sync = (int) get_option( WPCCT_OPT_LASTSYNC, 0 );
		$last_err  = get_option( WPCCT_OPT_LASTERR, '' );
		$data      = WPCCT_Sync::data();
		$totals    = ( isset( $data['totals'] ) && is_array( $data['totals'] ) ) ? $data['totals'] : array();
		$is_seed   = ! empty( $data['seed'] );

		echo '<h2>' . esc_html__( 'Sync status', 'wpcc-tracker' ) . '</h2>';

		if ( $is_seed ) {
			echo '<p><em>' . esc_html__( 'The dashboard is currently showing bundled seed data - no successful sync has run yet.', 'wpcc-tracker' ) . '</em></p>';
		}

		echo '<table class="widefat striped" style="max-width:600px;">';
		echo '<tbody>';

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		/* Two hops, shown separately and labelled so they cannot be read as
		   one number. This plugin only performs the second. The front-end
		   provenance line deliberately reports the first, because that is the
		   age of the figures a reader sees; this screen is the only place the
		   operator can see both. */
		echo '<tr><th style="width:220px;">' . esc_html__( 'Central to Airtable', 'wpcc-tracker' ) . '</th><td>';
		$source_synced = empty( $data['source_synced'] ) ? 0 : strtotime( (string) $data['source_synced'] );
		if ( $source_synced ) {
			echo esc_html( wp_date( $format, $source_synced ) );
		} else {
			$source_created = empty( $data['source_created'] ) ? 0 : strtotime( (string) $data['source_created'] );
			echo '<span style="color:#a00;">';
			if ( $source_created ) {
				printf(
					/* translators: %s: date the Airtable rows were first created. */
					esc_html__( 'Never. The rows are still the import of %s, so every figure below is that old.', 'wpcc-tracker' ),
					esc_html( wp_date( get_option( 'date_format' ), $source_created ) )
				);
			} else {
				esc_html_e( 'Never, and the age of the rows could not be established.', 'wpcc-tracker' );
			}
			echo '</span><br /><span class="description">';
			esc_html_e( 'Written by the WordCamp Airtable Connector plugin, not by this one. Check: wp wcac central-check', 'wpcc-tracker' );
			echo '</span>';
		}
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Airtable to dashboard', 'wpcc-tracker' ) . '</th><td>';
		if ( $last_sync > 0 ) {
			echo esc_html( wp_date( $format, $last_sync ) );
			echo '<br /><span class="description">';
			esc_html_e( 'This plugin\'s own daily sync. A recent time here says nothing about the age of the data above.', 'wpcc-tracker' );
			echo '</span>';
		} else {
			esc_html_e( 'Never synced yet.', 'wpcc-tracker' );
		}
		echo '</td></tr>';

		if ( $last_err ) {
			echo '<tr><th>' . esc_html__( 'Last error', 'wpcc-tracker' ) . '</th><td style="color:#a00;">' . esc_html( $last_err ) . '</td></tr>';
		}

		echo '</tbody></table>';

		$labels = array(
			'completed'           => __( 'Completed events', 'wpcc-tracker' ),
			'scheduled'           => __( 'Scheduled events', 'wpcc-tracker' ),
			'planning'            => __( 'In planning', 'wpcc-tracker' ),
			'attendees'           => __( 'Total attendees', 'wpcc-tracker' ),
			'completed_this_year' => __( 'Completed this year', 'wpcc-tracker' ),
			'countries'           => __( 'Countries', 'wpcc-tracker' ),
			'institutions'        => __( 'Institutions', 'wpcc-tracker' ),
		);

		echo '<h3>' . esc_html__( 'Current totals', 'wpcc-tracker' ) . '</h3>';
		echo '<table class="widefat striped" style="max-width:600px;">';
		echo '<tbody>';
		foreach ( $labels as $key => $label ) {
			$value = isset( $totals[ $key ] ) ? $totals[ $key ] : 0;
			echo '<tr><th style="width:220px;">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Unmapped-countries panel.
	 */
	private static function render_unmapped() {
		$data     = WPCCT_Sync::data();
		$unmapped = ( isset( $data['unmapped_countries'] ) && is_array( $data['unmapped_countries'] ) ) ? $data['unmapped_countries'] : array();

		echo '<h2>' . esc_html__( 'Unmapped countries', 'wpcc-tracker' ) . '</h2>';
		echo '<p>' . esc_html__( 'These events have a Country value that could not be matched to one of the five reporting regions. They are still counted in the headline totals above, but excluded from the regional roll-up. Fix a mismatch by adding a mapping with the wpcct_region_map filter, or drop the row entirely with the wpcct_excluded_rows filter.', 'wpcc-tracker' ) . '</p>';

		if ( empty( $unmapped ) ) {
			echo '<p>' . esc_html__( 'None - every event country currently maps to a region.', 'wpcc-tracker' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:600px;">';
		echo '<thead><tr><th>' . esc_html__( 'Country', 'wpcc-tracker' ) . '</th><th>' . esc_html__( 'Events', 'wpcc-tracker' ) . '</th></tr></thead>';
		echo '<tbody>';
		foreach ( $unmapped as $country => $count ) {
			$label = ( '' === trim( (string) $country ) ) ? __( '(blank)', 'wpcc-tracker' ) : (string) $country;
			echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( (string) $count ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Statuses the bucket map does not recognise.
	 *
	 * The counterpart to the countries report above, and it exists for the
	 * same reason. A Status the map does not know falls to the excluded
	 * bucket, which is the safe direction but a silent one: the report renamed
	 * 'Cancelled' to 'Canceled' between two pulls in 2026 and nothing said so.
	 * Had that been a planning stage instead, the planning figure would simply
	 * have been short.
	 */
	private static function render_unmapped_statuses() {
		$data     = WPCCT_Sync::data();
		$unmapped = ( isset( $data['unmapped_statuses'] ) && is_array( $data['unmapped_statuses'] ) ) ? $data['unmapped_statuses'] : array();

		echo '<h2>' . esc_html__( 'Unrecognised statuses', 'wpcc-tracker' ) . '</h2>';
		echo '<p>' . esc_html__( 'These events have a Status that is not in the bucket map, so they are counted as excluded and appear in no headline figure. That is deliberate, because guessing would be worse, but it means a status renamed upstream shows up here rather than as an unexplained shortfall. Map one with the wpcct_status_buckets filter. Cancelled and Declined are known exclusions and are not listed.', 'wpcc-tracker' ) . '</p>';

		if ( empty( $unmapped ) ) {
			echo '<p>' . esc_html__( 'None - every event status is currently recognised.', 'wpcc-tracker' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:600px;">';
		echo '<thead><tr><th>' . esc_html__( 'Status', 'wpcc-tracker' ) . '</th><th>' . esc_html__( 'Events', 'wpcc-tracker' ) . '</th></tr></thead>';
		echo '<tbody>';
		foreach ( $unmapped as $status => $count ) {
			$label = ( '' === trim( (string) $status ) ) ? __( '(blank)', 'wpcc-tracker' ) : (string) $status;
			echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( (string) $count ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
