<?php
/**
 * Plugin Name: Contributor Team Matcher
 * Plugin URI:  https://make.wordpress.org/contribute/
 * Description: An interactive quiz that helps contributors find the right WordPress contribution team based on their interests and skills.
 * Version:     1.0.10
 * Author:      Maciej Pilarski
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: find-your-team
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONTTEMA_VERSION', '1.0.10' );
define( 'CONTTEMA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONTTEMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CONTTEMA_PLUGIN_DIR . 'includes/class-conttema-quiz-data.php';
require_once CONTTEMA_PLUGIN_DIR . 'includes/class-conttema-shortcode.php';
require_once CONTTEMA_PLUGIN_DIR . 'includes/class-conttema-admin.php';
require_once CONTTEMA_PLUGIN_DIR . 'includes/class-conttema-block.php';

add_action( 'plugins_loaded', array( 'CONTTEMA_Shortcode', 'init' ) );
add_action( 'plugins_loaded', array( 'CONTTEMA_Admin', 'init' ) );
add_action( 'plugins_loaded', array( 'CONTTEMA_Block', 'init' ) );
