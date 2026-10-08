<?php
/**
 * Plugin Name:       WPCredits Program Manager
 * Plugin URI:        https://github.com/gomp/wpcredits-program-manager
 * Description:       Runs the WPCredits program on WordPress for five audiences - Students, Mentors, Institutions, Sponsors and Administrators - plus a Tools section. Provisions role-based accounts from Airtable, gives each mentor a Mentor Report Card listing the students assigned to them, and includes the Mentor Status Checker.
 * Version:           1.122.12
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

define( 'WPCPM_VERSION', '1.122.12' );
define( 'WPCPM_PLUGIN_FILE', __FILE__ );
define( 'WPCPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-choices.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-rows.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-learn.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-content-access.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-privacy-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-wporg-profile.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-module-order.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-refusal-meter.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-notices.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ics.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-palette.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-columns.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-questions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-diff.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-publish.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-definition.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-tracks.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-store.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-updates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-agreement-template.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-two-factor.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roster-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-secret.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-private-files.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-image-upload.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-pdf-check.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-stash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
// Before the modules that use it: PHP declares a class only once the traits it uses are declared.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
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
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-roster.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-codes.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-offers.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-claims.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-tools.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-usage.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-profile.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-interests.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-mentors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-logo.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-agreement.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-agreement-card.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-approval.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-posts.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-cards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-modules.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-header-notices.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-answer.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-assistant.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-profile.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-runner.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker-slack.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-mentor-checker.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-delete.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-builder-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-builder.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-editor-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-history-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-editor.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-tools.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-overview.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';

/**
 * Load the audience screens' account lists, the first time one is needed.
 *
 * Not with the rest: the lists extend core's list table, which core loads for wp-admin requests,
 * after plugins have loaded, so declaring them as the plugin loads would load core's class on the
 * front end, in REST and on cron as well. The Students, Mentors, Institutions, Sponsors and
 * Administrators screens call this, in the plumbing they share (`WPCPM_Accounts_Screen`), on their
 * load hooks, when their rows-per-page choices are saved and when they draw their lists without that
 * hook; the Students, Mentors, Institutions and Sponsors screens also wherever they read their
 * accounts table's role and invitation stamp (their invitations cards, and the cards' buttons, which
 * admin-post.php runs), and where they print what a press on the ticked rows did in the table's
 * words. The base still loads core's class itself when it is missing.
 */
function wpcpm_load_accounts_tables() {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-accounts-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-table.php';
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-table.php';
}

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
	// The Track Builder's tracks reach the program map through its filters (1.100.0), hooked
	// before any module asks the map anything.
	WPCPM_Track_Store::init();
	WPCPM_Tracks::init();
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
