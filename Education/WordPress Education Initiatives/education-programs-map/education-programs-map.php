<?php
/**
 * Plugin Name:       Education Programs Map
 * Plugin URI:        https://github.com/WordPress/wordpress.org/issues/584
 * Description:       Displays a world map with city-level markers for WordPress Campus Connect (WPCC), WPCredits, and Student Club activity, with a Dashboard settings screen for adding and managing institutions. Implements https://github.com/WordPress/wordpress.org/issues/584.
 * Version:           2.4.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Maciej Pilarski
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       education-programs-map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EPM_VERSION', '2.4.0' );
define( 'EPM_PLUGIN_FILE', __FILE__ );
define( 'EPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EPM_DB_VERSION', '1.5' );

require_once EPM_PLUGIN_DIR . 'includes/class-epm-db.php';
require_once EPM_PLUGIN_DIR . 'includes/class-epm-programs.php';
require_once EPM_PLUGIN_DIR . 'includes/class-epm-settings.php';
require_once EPM_PLUGIN_DIR . 'includes/class-epm-geocoder.php';
require_once EPM_PLUGIN_DIR . 'includes/class-epm-airtable-client.php';
require_once EPM_PLUGIN_DIR . 'includes/class-epm-airtable.php';
require_once EPM_PLUGIN_DIR . 'includes/class-epm-campus-connect.php';
require_once EPM_PLUGIN_DIR . 'includes/class-epm-admin.php';
require_once EPM_PLUGIN_DIR . 'includes/class-epm-rest.php';
require_once EPM_PLUGIN_DIR . 'includes/class-epm-shortcode.php';
require_once EPM_PLUGIN_DIR . 'includes/class-epm-block.php';

/**
 * One-time migration for sites upgrading from the plugin's former identity as
 * "WP Education Map" (options were prefixed "weim_" instead of "epm_"). Safe to
 * run on every load: each option is only touched once, the first time the new
 * key is missing but the old one still has data.
 */
function epm_migrate_legacy_options() {
	$option_map = array(
		'weim_db_version'           => 'epm_db_version',
		'weim_programs'             => 'epm_programs',
		'weim_settings'             => 'epm_settings',
		'weim_airtable_settings'    => 'epm_airtable_settings',
		'weim_airtable_last_result' => 'epm_airtable_last_result',
	);

	foreach ( $option_map as $old_name => $new_name ) {
		if ( false === get_option( $new_name, false ) && false !== get_option( $old_name, false ) ) {
			update_option( $new_name, get_option( $old_name ), false );
			delete_option( $old_name );
		}
	}
}
add_action( 'plugins_loaded', 'epm_migrate_legacy_options', 5 );

/**
 * Drop the retired per-program WPCC connection. Campus Connect activity now comes
 * from the events table via EPM_Campus_Connect, so the old connection's settings
 * are dead weight — and they hold an Airtable token, which should not linger in
 * the database for a connection that can no longer be reached from the admin.
 *
 * Institutions it imported are left alone; they are ordinary rows and an admin can
 * still edit or delete them by hand.
 */
function epm_remove_retired_wpcc_connection() {
	if ( get_option( 'epm_wpcc_connection_removed' ) ) {
		return;
	}

	foreach ( array( EPM_Airtable::OPTION_NAME, EPM_Airtable::LAST_RESULT_NAME ) as $option_name ) {
		$stored = get_option( $option_name );

		if ( is_array( $stored ) && isset( $stored['wpcc'] ) ) {
			unset( $stored['wpcc'] );
			update_option( $option_name, $stored, false );
		}
	}

	update_option( 'epm_wpcc_connection_removed', 1, false );
}
add_action( 'plugins_loaded', 'epm_remove_retired_wpcc_connection', 6 );

/**
 * Create/upgrade the institutions table on activation.
 */
function epm_activate() {
	EPM_DB::maybe_upgrade();
}
register_activation_hook( __FILE__, 'epm_activate' );

/**
 * Clear the scheduled Airtable auto-sync so no orphaned cron event lingers after deactivation.
 */
function epm_deactivate() {
	wp_clear_scheduled_hook( EPM_Airtable::CRON_HOOK );
}
register_deactivation_hook( __FILE__, 'epm_deactivate' );

/**
 * Keep the table schema current if the plugin is updated without deactivation.
 */
add_action( 'plugins_loaded', array( 'EPM_DB', 'maybe_upgrade' ) );

add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'education-programs-map', false, dirname( plugin_basename( EPM_PLUGIN_FILE ) ) . '/languages' );
	}
);

new EPM_Admin();
new EPM_REST();
new EPM_Shortcode();
new EPM_Block();
new EPM_Airtable();
