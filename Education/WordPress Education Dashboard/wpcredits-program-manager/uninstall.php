<?php
/**
 * Uninstall cleanup.
 *
 * Removes plugin state: settings, sync state, module options, custom roles and
 * the user meta this plugin wrote. User *accounts* are deliberately left behind —
 * they belong to real mentors, and a plugin removal is not a reason to delete
 * people. Accounts holding only a program role are moved to Subscriber.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-roles.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-airtable.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-content-access.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-wporg-profile.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-program.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-icons.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-request.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-flash.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-notices.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-ics.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-mail.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-contribution-teams.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-updates.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-module.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-students.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-students-sync.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-students-dashboard.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-student-profile.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-student-report-form.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentors.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentors-dashboard.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-notes.php';
// The calendar's three classes are required here for one reason: `WPCPM_Mentors`
// names them in its own `uninstall()`. This file builds its dependencies by hand
// rather than booting the plugin, so a class the modules reach for and this list
// forgets is a fatal in the middle of cleanup — which leaves everything behind and
// says nothing.
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-availability.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-calls.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-call-calendar.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-group-sessions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-institutions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-administrators.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-modules.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-tool.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-header-notices.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-handbook-answer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-handbook-assistant.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-handbook.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-mentor-checker-profile.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-mentor-checker-runner.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-mentor-checker.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-tools.php';

WPCPM_Modules::uninstall();
WPCPM_Tools::uninstall();
WPCPM_Roles::unregister();

delete_option( WPCPM_Settings::OPTION );

// Pending one-shot messages. Nobody is going to read "Saved." after the plugin is gone.
delete_metadata( 'user', 0, WPCPM_Flash::META, '', true );

// The four header notices, both one-time setup flags, and the posts and audience meta the
// briefly post-backed version left behind. `delete_all()` removes the option and the posts;
// the meta goes separately because the posts are deleted by ID and orphaned rows would
// otherwise survive a post that had already been removed by hand.
WPCPM_Notices::delete_all();
delete_option( WPCPM_Notices::OPT_MIGRATED );
delete_option( WPCPM_Notices::OPT_PLAIN );
delete_metadata( 'post', 0, WPCPM_Notices::META_AUDIENCE, '', true );

// Access levels are meaningless once the capabilities that read them are gone.
delete_metadata( 'post', 0, WPCPM_Content_Access::META_KEY, '', true );

// Both syncs, not just the mentors one. The students module clears its own in
// `deactivate()`, which WordPress does run in the ordinary deactivate-then-delete
// flow — but a plugin can also be deleted from a state where that never fired, and
// a scheduled hook whose callback no longer exists is a cron entry that fails
// silently forever. The mentors hooks were already cleared here; the asymmetry was
// the bug.
wp_clear_scheduled_hook( WPCPM_Mentors_Sync::CRON_DAILY );
wp_clear_scheduled_hook( WPCPM_Mentors_Sync::CRON_TICK );
wp_clear_scheduled_hook( WPCPM_Students_Sync::CRON_AUTO );
wp_clear_scheduled_hook( WPCPM_Students_Sync::CRON_TICK );
wp_clear_scheduled_hook( WPCPM_Mentor_Calls::CRON_REMINDERS );
wp_clear_scheduled_hook( WPCPM_Mail::CRON_QUEUE );
// Named as literals because the classes that owned them are gone. A site upgrading from the
// version that kept a local copy still has both schedules and the table, and a scheduled hook
// whose callback no longer exists fails silently for ever.
wp_clear_scheduled_hook( 'wpcpm_handbook_sync_daily' );
wp_clear_scheduled_hook( 'wpcpm_handbook_sync_tick' );

// The mail log and anyone still waiting for an invitation that is no longer coming.
WPCPM_Mail::clear_log();
WPCPM_Mail::clear_queue();

// Reminder markers on calls. `WPCPM_Mentor_Calls::delete_all()` removes the calls
// themselves, but a call deleted by hand before now would leave its marker behind.
delete_metadata( 'post', 0, WPCPM_Mentor_Calls::META_REMINDED, '', true );
