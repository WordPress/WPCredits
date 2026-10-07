<?php
/**
 * Plugin Name:       WPCC-Tracker
 * Plugin URI:        https://nisabareportingp2.wpcomstaging.com/
 * Description:       WordPress Campus Connect programme data - events, regions, pipeline, attendance and a world map - rendered as native blocks. Synced daily from Airtable.
 * Version:           1.0.4
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Maciej (Matt) Pilarski
 * Author URI:        https://profiles.wordpress.org/gomp/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcc-tracker
 *
 * @package WPCC_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPCCT_VERSION', '1.0.4' );
define( 'WPCCT_FILE', __FILE__ );
define( 'WPCCT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCCT_URL', plugin_dir_url( __FILE__ ) );

define( 'WPCCT_OPT_SETTINGS', 'wpcct_settings' );
define( 'WPCCT_OPT_DATA', 'wpcct_data' );
define( 'WPCCT_OPT_LASTSYNC', 'wpcct_last_sync' );
define( 'WPCCT_OPT_LASTERR', 'wpcct_last_error' );
define( 'WPCCT_CRON_SYNC', 'wpcct_cron_sync' );

/**
 * Cache-busting version for a bundled asset.
 *
 * The plugin version alone is not enough: it stays pinned across a release, so
 * a redeploy that changes a script keeps the same `?ver=` and browsers and CDNs
 * go on serving the old file. Falling back to the file's modification time means
 * every real change busts the cache, whether or not the version moved.
 *
 * @param string $relative_path Path below the plugin root, e.g. 'assets/js/tracker.js'.
 * @return string
 */
function wpcct_asset_version( $relative_path ) {
	$file = WPCCT_DIR . ltrim( $relative_path, '/' );
	$mtime = is_readable( $file ) ? filemtime( $file ) : false;

	return $mtime ? WPCCT_VERSION . '.' . $mtime : WPCCT_VERSION;
}

require_once WPCCT_DIR . 'includes/class-wpcct-normalize.php';
require_once WPCCT_DIR . 'includes/class-wpcct-sync.php';
require_once WPCCT_DIR . 'includes/class-wpcct-settings.php';

add_action( 'plugins_loaded', array( 'WPCCT_Sync', 'instance' ) );
register_activation_hook( WPCCT_FILE, array( 'WPCCT_Sync', 'activate' ) );
register_deactivation_hook( WPCCT_FILE, array( 'WPCCT_Sync', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WPCCT_Settings', 'instance' ) );

require_once WPCCT_DIR . 'includes/class-wpcct-render.php';
add_shortcode( 'wpcc_tracker', array( 'WPCCT_Render', 'shortcode' ) );

require_once WPCCT_DIR . 'includes/class-wpcct-block.php';
add_action( 'plugins_loaded', array( 'WPCCT_Block', 'instance' ) );
