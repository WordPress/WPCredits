<?php
/**
 * Plugin Name:       WPCredits-Tracker
 * Plugin URI:        https://wpeducationalinitiatives.app/
 * Description:       A native WordPress rendering of the WordPress Credits program dashboard (scale, growth, partner map, contributions, and student voices) — no iframe. Data is synced weekly from Airtable and profiles.wordpress.org, and displayed via a "WPCredits-Tracker" block or the [wpcredits_tracker] shortcode. A PHP port of the wordpress/WPCredits-Tracker build.
 * Version:           1.4.4
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Maciej (Matt) Pilarski, Isotta Peira
 * Author URI:        https://profiles.wordpress.org/gomp/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcredits-tracker
 *
 * @package WPCredits_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPCT_VERSION', '1.4.4' );
define( 'WPCT_FILE', __FILE__ );
define( 'WPCT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCT_URL', plugin_dir_url( __FILE__ ) );

// Option / hook names.
define( 'WPCT_OPT_SETTINGS', 'wpct_settings' );   // Airtable config.
define( 'WPCT_OPT_DATA', 'wpct_data' );           // Public dashboard data blob.
define( 'WPCT_OPT_STATE', 'wpct_state' );         // Resumable sync state.
define( 'WPCT_OPT_LASTSYNC', 'wpct_last_sync' );  // Timestamp of last successful sync.
define( 'WPCT_OPT_LASTERR', 'wpct_last_error' );  // Last sync error message.
define( 'WPCT_CRON_WEEKLY', 'wpct_cron_weekly' ); // Recurring scheduled sync (weekly).
define( 'WPCT_CRON_RUN', 'wpct_run_sync' );       // Single-event sync step (resumable).

require_once WPCT_DIR . 'includes/class-wpct-sync.php';
require_once WPCT_DIR . 'includes/class-wpct-render.php';
require_once WPCT_DIR . 'includes/class-wpct-settings.php';
require_once WPCT_DIR . 'includes/class-wpct-block.php';

/**
 * Default settings.
 *
 * @return array
 */
function wpct_default_settings() {
	return array(
		'airtable_pat' => '',
		'base_id'      => 'appIzQKfwTn5dyPVp',
	);
}

/**
 * Get merged settings.
 *
 * @return array
 */
function wpct_get_settings() {
	$saved = get_option( WPCT_OPT_SETTINGS, array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), wpct_default_settings() );
}

/**
 * Boot the plugin.
 */
function wpct_init() {
	WPCT_Settings::instance();
	WPCT_Block::instance();

	// Cron handlers (both point at the resumable step runner).
	add_action( WPCT_CRON_WEEKLY, array( 'WPCT_Sync', 'run_step' ) );
	add_action( WPCT_CRON_RUN, array( 'WPCT_Sync', 'run_step' ) );

	// Shortcode + a back-compat alias for the pre-rename slug, so pages that
	// used the previous plugin's [education_credits_dashboard] keep working.
	add_shortcode( 'wpcredits_tracker', array( 'WPCT_Render', 'shortcode' ) );
	add_shortcode( 'education_credits_dashboard', array( 'WPCT_Render', 'shortcode' ) );

	// Register (do not force-enqueue) the shared assets; the block + shortcode
	// enqueue them on demand when they render.
	wpct_register_assets();
}
add_action( 'init', 'wpct_init' );

/**
 * Register the bundled front-end assets (Chart.js, Leaflet, dashboard CSS/JS).
 */
function wpct_register_assets() {
	if ( ! wp_style_is( 'wpct-leaflet', 'registered' ) ) {
		wp_register_style( 'wpct-leaflet', WPCT_URL . 'assets/lib/leaflet/leaflet.css', array(), '1.9.4' );
	}
	if ( ! wp_style_is( 'wpct-dashboard', 'registered' ) ) {
		wp_register_style( 'wpct-dashboard', WPCT_URL . 'assets/css/dashboard.css', array(), WPCT_VERSION );
	}
	if ( ! wp_script_is( 'wpct-leaflet', 'registered' ) ) {
		wp_register_script( 'wpct-leaflet', WPCT_URL . 'assets/lib/leaflet/leaflet.js', array(), '1.9.4', true );
	}
	if ( ! wp_script_is( 'wpct-chart', 'registered' ) ) {
		wp_register_script( 'wpct-chart', WPCT_URL . 'assets/lib/chart.umd.min.js', array(), '4.4.1', true );
	}
	if ( ! wp_script_is( 'wpct-dashboard', 'registered' ) ) {
		wp_register_script( 'wpct-dashboard', WPCT_URL . 'assets/js/dashboard.js', array( 'wpct-leaflet', 'wpct-chart' ), WPCT_VERSION, true );
	}
	// Shared editor script for the section blocks (registered client-side).
	if ( ! wp_script_is( 'wpct-blocks-editor', 'registered' ) ) {
		wp_register_script(
			'wpct-blocks-editor',
			WPCT_URL . 'assets/js/blocks-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
			WPCT_VERSION,
			true
		);
	}
}

/**
 * Register a custom weekly cron schedule (WordPress has no built-in "weekly").
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
function wpct_cron_schedules( $schedules ) {
	if ( ! isset( $schedules['wpct_weekly'] ) ) {
		$schedules['wpct_weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once weekly (WPCredits-Tracker)', 'wpcredits-tracker' ),
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'wpct_cron_schedules' ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected

/**
 * The next Monday 06:00 UTC as a Unix timestamp (matches the upstream cadence).
 *
 * @return int
 */
function wpct_next_monday_0600_utc() {
	$now  = time();
	$next = strtotime( 'next monday 06:00 UTC', $now );
	if ( false === $next ) {
		$next = $now + DAY_IN_SECONDS;
	}
	return $next;
}

/**
 * Activation: seed the bundled snapshot and schedule the weekly sync.
 */
function wpct_activate() {
	if ( false === get_option( WPCT_OPT_SETTINGS, false ) ) {
		update_option( WPCT_OPT_SETTINGS, wpct_default_settings() );
	}

	// Seed the data blob from the bundled snapshot if we have none yet, so the
	// dashboard renders real numbers before the first live sync.
	$existing = get_option( WPCT_OPT_DATA, false );
	if ( empty( $existing ) || ! is_array( $existing ) ) {
		$seed_file = WPCT_DIR . 'data/seed.json';
		if ( is_readable( $seed_file ) ) {
			$seed = json_decode( file_get_contents( $seed_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( is_array( $seed ) ) {
				update_option( WPCT_OPT_DATA, $seed, false );
				update_option( WPCT_OPT_LASTSYNC, 0 ); // 0 = seed snapshot, never live-synced.
			}
		}
	}

	if ( ! wp_next_scheduled( WPCT_CRON_WEEKLY ) ) {
		wp_schedule_event( wpct_next_monday_0600_utc(), 'wpct_weekly', WPCT_CRON_WEEKLY );
	}
}
register_activation_hook( __FILE__, 'wpct_activate' );

/**
 * Deactivation: clear scheduled events.
 */
function wpct_deactivate() {
	wp_clear_scheduled_hook( WPCT_CRON_WEEKLY );
	wp_clear_scheduled_hook( WPCT_CRON_RUN );
}
register_deactivation_hook( __FILE__, 'wpct_deactivate' );
