<?php
/**
 * Plugin Name:       Credits Program Mentors
 * Plugin URI:        https://wordpress.org/plugins/credits-program-mentors/
 * Description:        Displays content from the public "Sponsored mentors" Airtable base via a [credits_program_mentors] shortcode.
 * Version:           1.5.1
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Maciej Pilarski
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       credits-program-mentors
 *
 * @package CreditsProgramMentors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'CREDPRME_VERSION', '1.5.1' );
define( 'CREDPRME_PLUGIN_FILE', __FILE__ );
define( 'CREDPRME_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CREDPRME_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CREDPRME_PLUGIN_DIR . 'includes/class-credprme-airtable-client.php';
require_once CREDPRME_PLUGIN_DIR . 'includes/class-credprme-avatars.php';
require_once CREDPRME_PLUGIN_DIR . 'includes/class-credprme-map.php';
require_once CREDPRME_PLUGIN_DIR . 'includes/class-credprme-settings.php';
require_once CREDPRME_PLUGIN_DIR . 'includes/class-credprme-shortcode.php';
require_once CREDPRME_PLUGIN_DIR . 'includes/class-credprme-block.php';

/**
 * Boot the plugin.
 */
function credprme_bootstrap() {
	new CREDPRME_Settings();
	$shortcode = new CREDPRME_Shortcode();
	new CREDPRME_Block( $shortcode );
}
add_action( 'plugins_loaded', 'credprme_bootstrap' );

// Translations for WordPress.org-hosted plugins are loaded automatically since
// WordPress 4.6, so no load_plugin_textdomain() call is needed here.
