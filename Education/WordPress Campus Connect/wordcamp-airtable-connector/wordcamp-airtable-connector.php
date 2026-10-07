<?php
/**
 * Plugin Name:       WordCamp Airtable Connector
 * Plugin URI:        https://github.com/gomp/wordcamp-airtable-connector
 * Description:       Pulls WordCamps, Meetups, Sessions, Speakers, Sponsors and Campus Connect events from the WordCamp.org REST API and upserts them into an Airtable base on a schedule.
 * Version:           1.1.9
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Maciej Pilarski
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wordcamp-airtable-connector
 *
 * @package WordCamp_Airtable_Connector
 */

defined( 'ABSPATH' ) || exit;

define( 'WCAC_VERSION', '1.1.9' );
define( 'WCAC_FILE', __FILE__ );
define( 'WCAC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCAC_URL', plugin_dir_url( __FILE__ ) );

/**
 * The recurring cron hook that drains the sync queue.
 */
define( 'WCAC_CRON_HOOK', 'wcac_run_queue' );

/**
 * A one-off hook used to chain slices together while a backlog exists, so a
 * backfill is not paced by the recurring interval.
 */
define( 'WCAC_CONTINUE_HOOK', 'wcac_continue_queue' );

require_once WCAC_DIR . 'includes/class-wcac-settings.php';
require_once WCAC_DIR . 'includes/class-wcac-logger.php';
require_once WCAC_DIR . 'includes/class-wcac-source.php';
require_once WCAC_DIR . 'includes/class-wcac-airtable.php';
require_once WCAC_DIR . 'includes/class-wcac-mapper.php';
require_once WCAC_DIR . 'includes/class-wcac-sync.php';
require_once WCAC_DIR . 'includes/class-wcac-admin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WCAC_DIR . 'includes/class-wcac-cli.php';
	WP_CLI::add_command( 'wcac', 'WCAC_CLI' );
}

/**
 * Register the plugin's own cron interval.
 *
 * WordPress ships nothing shorter than hourly, and draining a backfill queue an
 * hour at a time would take weeks.
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
function wcac_cron_schedules( $schedules ) {
	$schedules['wcac_five_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every five minutes (WordCamp Airtable Connector)', 'wordcamp-airtable-connector' ),
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'wcac_cron_schedules' );

/**
 * Drain a slice of the queue. Fired by WP-Cron.
 *
 * @return void
 */
function wcac_cron_tick() {
	( new WCAC_Sync() )->run_slice();
}
add_action( WCAC_CRON_HOOK, 'wcac_cron_tick' );
add_action( WCAC_CONTINUE_HOOK, 'wcac_cron_tick' );

/**
 * Release the Campus Connect failure latch whenever the Central credential
 * actually changes.
 *
 * WCAC_Settings fires this from save(), set_secret() and forget_central(),
 * and only when the stored credential's fingerprint differs. It is
 * deliberately not fired on every settings save: a form saved for an
 * unrelated reason must not re-arm a job that is failing to authenticate.
 */
add_action(
	'wcac_central_credential_changed',
	function () {
		( new WCAC_Sync() )->clear_campus_block();
	}
);

/**
 * Schedule the recurring queue runner if it is missing.
 *
 * Called on activation and defensively on every admin load, so that a cron
 * table wiped by a migration repairs itself.
 *
 * @return void
 */
function wcac_maybe_schedule() {
	if ( ! wp_next_scheduled( WCAC_CRON_HOOK ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'wcac_five_minutes', WCAC_CRON_HOOK );
	}
}
add_action( 'admin_init', 'wcac_maybe_schedule' );

/**
 * Rewrite stored settings that a newer version stores differently.
 *
 * Runs on the same defensive admin load as the cron repair above, and, since
 * 1.1.3, on WP-CLI and cron requests too, for the same reason: nothing else
 * ever rewrites the row. A source site saved by 1.0.x can carry a "user:pass@"
 * authority, which 1.1.1 strips on read but which would otherwise sit in
 * wp_options and travel in every backup.
 *
 * @return void
 */
function wcac_maybe_upgrade() {
	WCAC_Settings::maybe_upgrade();
}
add_action( 'admin_init', 'wcac_maybe_upgrade' );

// admin_init fires on a wp-admin request and nowhere else, so the repair above
// never reaches a site driven entirely by WP-CLI and cron -- which is the route
// the Campus Connect instructions themselves describe, and so the profile most
// likely to be carrying a legacy "user:pass@" source site. Both of those
// contexts load WordPress far enough to fire init. maybe_upgrade() reads one
// autoloaded option and returns unless the stored root really carries such an
// authority, so on a healthy site this costs a read and nothing else.
if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
	add_action( 'init', 'wcac_maybe_upgrade' );
}

register_activation_hook(
	__FILE__,
	function () {
		wcac_maybe_schedule();
		wcac_maybe_upgrade();
		WCAC_Logger::log( 'info', 'Plugin activated.' );
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( WCAC_CRON_HOOK );
		wp_clear_scheduled_hook( WCAC_CONTINUE_HOOK );
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( is_admin() ) {
			new WCAC_Admin();
		}
	}
);
