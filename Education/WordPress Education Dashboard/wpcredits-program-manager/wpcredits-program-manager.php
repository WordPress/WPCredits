<?php
/**
 * Plugin Name:       WPCredits Program Manager
 * Plugin URI:        https://github.com/gomp/wpcredits-program-manager
 * Description:       Runs the WPCredits program on WordPress in five modules - Students, Mentors, Institutions, Sponsors and Administrators - plus a Tools section. Provisions role-based accounts from Airtable, gives each mentor a private page listing the students assigned to them, and includes the Mentor Status Checker.
 * Version:           1.91.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Maciej Pilarski
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcredits-program-manager
 * Domain Path:       /languages
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'WPCPM_VERSION', '1.91.0' );
define( 'WPCPM_PLUGIN_FILE', __FILE__ );
define( 'WPCPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-content-access.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-privacy-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-wporg-profile.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-notices.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ics.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-updates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-agreement-template.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-two-factor.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roster-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-private-files.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-report-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-feedback.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-notes.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-availability.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-call-calendar.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-group-sessions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-view.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-people.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-panel.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-agreement-generate.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-approval.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-students.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-export.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-create.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-notes.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-invite.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-modules.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-header-notices.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-answer.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-assistant.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-profile.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-runner.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-tools.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';

/**
 * Boot the plugin.
 *
 * Roles and content gating are global; everything else belongs to a module or a
 * tool and is booted through its registry, so the modules stay a stable
 * description of the program while tools come and go independently.
 */
function wpcpm_bootstrap() {
	// Role labels are translated and get stored in the database, so the upgrade
	// check waits for `init` - calling __() on plugins_loaded triggers WordPress's
	// "translations loaded too early" warning and would store an untranslated label.
	add_action( 'init', 'wpcpm_load_textdomain', 1 );
	add_action( 'init', array( 'WPCPM_Roles', 'maybe_upgrade' ), 5 );
	// Same moment for the settings: `save()` stamps the version, and a settings form posted
	// before this ran would stamp it without the statuses the upgrade exists to add.
	add_action( 'init', array( 'WPCPM_Settings', 'maybe_upgrade' ), 5 );
	// A save that had to put a default back says so on the screen it redirects to.
	add_action( 'admin_notices', array( 'WPCPM_Settings', 'render_notices' ) );

	WPCPM_Two_Factor::init();
	WPCPM_Content_Access::init();
	WPCPM_Privacy_Guard::init();
	WPCPM_Notices::init();
	WPCPM_Mail::init();
	WPCPM_Modules::boot();
	WPCPM_Tools::boot();
	WPCPM_Dashboards::init();
	new WPCPM_Admin();
}
add_action( 'plugins_loaded', 'wpcpm_bootstrap' );

/**
 * Load translations.
 */
function wpcpm_load_textdomain() {
	load_plugin_textdomain(
		'wpcredits-program-manager',
		false,
		dirname( plugin_basename( WPCPM_PLUGIN_FILE ) ) . '/languages'
	);
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cli.php';
	WP_CLI::add_command( 'wpcredits', 'WPCPM_CLI' );
}

/**
 * Create roles and module pages on activation.
 */
function wpcpm_activate() {
	WPCPM_Roles::register();
	WPCPM_Modules::activate();
	WPCPM_Tools::activate();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wpcpm_activate' );

/**
 * Stop scheduled work on deactivation. Roles and users are left in place - they
 * are program data, not plugin state, and are only removed on uninstall.
 */
function wpcpm_deactivate() {
	WPCPM_Modules::deactivate();
	WPCPM_Tools::deactivate();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wpcpm_deactivate' );
