<?php
/**
 * Admin settings screen: Airtable credentials, manual sync, and status.
 * Mirrors ESS_Settings in the sibling education-student-stories plugin.
 *
 * @package WPCredits_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCT_Settings {

	/** @var WPCT_Settings|null */
	private static $instance = null;

	const PAGE  = 'wpcredits-tracker';
	const GROUP = 'wpct_settings_group';
	const NONCE = 'wpct_sync_now';

	/**
	 * Singleton.
	 *
	 * @return WPCT_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_post_wpct_sync_now', array( $this, 'handle_sync_now' ) );
	}

	/**
	 * Add the top-level dashboard menu item.
	 */
	public function add_page() {
		add_menu_page(
			__( 'WPCredits-Tracker', 'wpcredits-tracker' ),
			__( 'WPCredits-Tracker', 'wpcredits-tracker' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' ),
			'dashicons-chart-area',
			31
		);
	}

	/**
	 * Register the settings + sanitizer.
	 */
	public function register() {
		register_setting(
			self::GROUP,
			WPCT_OPT_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitize + preserve the stored PAT when the field is left blank/masked.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$current = wpct_get_settings();
		$out     = wpct_default_settings();

		$out['base_id'] = isset( $input['base_id'] ) ? sanitize_text_field( $input['base_id'] ) : $out['base_id'];

		$pat = isset( $input['airtable_pat'] ) ? trim( $input['airtable_pat'] ) : '';
		if ( '' !== $pat && false === strpos( $pat, '•' ) ) {
			$out['airtable_pat'] = $pat;
		} else {
			$out['airtable_pat'] = $current['airtable_pat'];
		}

		return $out;
	}

	/**
	 * Handle the "Sync now" POST.
	 */
	public function handle_sync_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wpcredits-tracker' ) );
		}
		check_admin_referer( self::NONCE );

		$settings = wpct_get_settings();
		if ( empty( $settings['airtable_pat'] ) ) {
			$this->redirect_back( 'notoken' );
		}

		WPCT_Sync::start();
		$this->redirect_back( 'started' );
	}

	/**
	 * Redirect back to the settings page with a status flag.
	 *
	 * @param string $flag Status flag.
	 */
	private function redirect_back( $flag ) {
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'ecd' => $flag ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings  = wpct_get_settings();
		$data      = get_option( WPCT_OPT_DATA, array() );
		$last_sync = (int) get_option( WPCT_OPT_LASTSYNC, 0 );
		$last_err  = get_option( WPCT_OPT_LASTERR, '' );
		$running   = WPCT_Sync::is_running();
		$flag      = isset( $_GET['ecd'] ) ? sanitize_key( wp_unslash( $_GET['ecd'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$g        = is_array( $data ) && isset( $data['global'] ) ? $data['global'] : array();
		$pat_mask = $settings['airtable_pat'] ? str_repeat( '•', 8 ) . substr( $settings['airtable_pat'], -4 ) : '';
		$next     = wp_next_scheduled( WPCT_CRON_WEEKLY );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WPCredits-Tracker', 'wpcredits-tracker' ); ?></h1>

			<?php if ( 'started' === $flag ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Sync started in the background. This page auto-refreshes while it runs.', 'wpcredits-tracker' ); ?></p></div>
			<?php elseif ( 'notoken' === $flag ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Add your Airtable Personal Access Token first, then run a sync.', 'wpcredits-tracker' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $last_err ) : ?>
				<div class="notice notice-error"><p><strong><?php esc_html_e( 'Last sync error:', 'wpcredits-tracker' ); ?></strong> <?php echo esc_html( $last_err ); ?></p></div>
			<?php endif; ?>

			<div class="card" style="max-width:none;padding:12px 16px;">
				<p style="margin:.2em 0;">
					<strong><?php esc_html_e( 'Status:', 'wpcredits-tracker' ); ?></strong>
					<?php
					if ( $running ) {
						echo '<span style="color:#b26200;">● ' . esc_html( WPCT_Sync::progress_label() ) . '</span>';
					} else {
						echo '<span style="color:#1a7f37;">● ' . esc_html__( 'Idle', 'wpcredits-tracker' ) . '</span>';
					}
					?>
				</p>
				<p style="margin:.2em 0;">
					<strong><?php esc_html_e( 'Current data:', 'wpcredits-tracker' ); ?></strong>
					<?php
					if ( $g ) {
						echo esc_html(
							sprintf(
								/* translators: 1: active students, 2: graduates. */
								__( '%1$d active students, %2$d graduates', 'wpcredits-tracker' ),
								isset( $g['activeStudents'] ) ? (int) $g['activeStudents'] : 0,
								isset( $g['graduates'] ) ? (int) $g['graduates'] : 0
							)
						);
					} else {
						esc_html_e( 'No data yet', 'wpcredits-tracker' );
					}
					?>
				</p>
				<p style="margin:.2em 0;">
					<strong><?php esc_html_e( 'Last synced:', 'wpcredits-tracker' ); ?></strong>
					<?php
					if ( $last_sync > 0 ) {
						echo esc_html( sprintf( /* translators: %s: human time diff. */ __( '%s ago', 'wpcredits-tracker' ), human_time_diff( $last_sync ) ) );
						echo ' (' . esc_html( wp_date( 'Y-m-d H:i', $last_sync ) ) . ')';
					} else {
						esc_html_e( 'Never (showing bundled snapshot)', 'wpcredits-tracker' );
					}
					?>
				</p>
				<?php if ( $next ) : ?>
					<p style="margin:.2em 0;">
						<strong><?php esc_html_e( 'Next scheduled sync:', 'wpcredits-tracker' ); ?></strong>
						<?php echo esc_html( wp_date( 'Y-m-d H:i', $next ) ); ?>
					</p>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
					<input type="hidden" name="action" value="wpct_sync_now" />
					<?php wp_nonce_field( self::NONCE ); ?>
					<?php submit_button( $running ? __( 'Sync running…', 'wpcredits-tracker' ) : __( 'Sync now', 'wpcredits-tracker' ), 'primary', 'submit', false, $running ? array( 'disabled' => 'disabled' ) : array() ); ?>
				</form>
			</div>

			<form method="post" action="options.php" style="margin-top:20px;">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpct_pat"><?php esc_html_e( 'Airtable Personal Access Token', 'wpcredits-tracker' ); ?></label></th>
						<td>
							<input type="password" id="wpct_pat" name="<?php echo esc_attr( WPCT_OPT_SETTINGS ); ?>[airtable_pat]" value="" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr( $pat_mask ? $pat_mask : 'pat…' ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Stored in the database and used server-side only. Leave blank to keep the current token.', 'wpcredits-tracker' ); ?>
								<?php if ( $pat_mask ) : ?><br /><em><?php echo esc_html( sprintf( /* translators: %s: masked token. */ __( 'Current: %s', 'wpcredits-tracker' ), $pat_mask ) ); ?></em><?php endif; ?>
								<br /><strong><?php esc_html_e( 'Security:', 'wpcredits-tracker' ); ?></strong> <?php esc_html_e( 'use a token scoped to read-only access to just this base.', 'wpcredits-tracker' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpct_base"><?php esc_html_e( 'Base ID', 'wpcredits-tracker' ); ?></label></th>
						<td>
							<input type="text" id="wpct_base" name="<?php echo esc_attr( WPCT_OPT_SETTINGS ); ?>[base_id]" value="<?php echo esc_attr( $settings['base_id'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'The same Airtable base the WordPress Credits program uses. Table and field IDs are built in (override with the wpct_tables / wpct_fields filters if the base changes).', 'wpcredits-tracker' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'wpcredits-tracker' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'How to display it', 'wpcredits-tracker' ); ?></h2>
			<p><?php esc_html_e( 'Insert the full "WPCredits-Tracker (full)" block for the whole tabbed dashboard, or use the shortcode:', 'wpcredits-tracker' ); ?></p>
			<p><code>[wpcredits_tracker]</code></p>
			<p class="description"><?php esc_html_e( 'The old [education_credits_dashboard] shortcode still works as an alias, so pages from the previous plugin name keep rendering.', 'wpcredits-tracker' ); ?></p>
			<p><?php esc_html_e( 'For a modular layout, the blocks under the "WPCredits-Tracker" category let you place individual sections anywhere: Scale & Momentum, Growth Chart, Partner Map, Field of Study, Skills, What Students Produce, Outcomes & Quality, and Voices.', 'wpcredits-tracker' ); ?></p>

			<h2><?php esc_html_e( 'How the data works', 'wpcredits-tracker' ); ?></h2>
			<p class="description" style="max-width:820px;">
				<?php esc_html_e( 'On each sync the plugin reads the program tables from Airtable, scrapes each student\'s profiles.wordpress.org page for translation activity, and computes the same public aggregates as the upstream WordPress Credits dashboard — the same numbers, hosted natively on this site. Syncs run automatically once a week (Monday 06:00 UTC) and on demand via "Sync now". A bundled snapshot is shown until the first live sync completes.', 'wpcredits-tracker' ); ?>
			</p>
		</div>

		<?php if ( $running ) : ?>
			<script>setTimeout(function(){ location.href = <?php echo wp_json_encode( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>; }, 5000);</script>
		<?php endif; ?>
		<?php
	}
}
