<?php
/**
 * Removes the connector's own options on uninstall.
 *
 * Airtable data is left untouched. This only clears local settings, queue,
 * cache and log.
 *
 * Nothing new needs adding here for the Campus Connect sync, and that is by
 * design. Both secrets (the Airtable token and the Central application
 * password) live inside the wcac_settings row, and every Campus Connect state
 * key (cc_block, cc_fails, cc_last_ok, cc_preflight_ok and the rest) lives
 * inside wcac_state. Deleting those two rows removes all of them. Giving
 * either secret an option row of its own would risk leaving a credential
 * behind after an uninstall, so if you add a setting, put it in one of the
 * existing arrays rather than in a new option.
 *
 * What this file must not touch: a credential the site owner placed in
 * wp-config.php as WCAC_CENTRAL_USER or WCAC_CENTRAL_APP_PASSWORD. Those
 * constants are the owner's file, not the plugin's data, and are removed by
 * editing wp-config.php.
 *
 * Uninstalling revokes nothing at the far end either. Delete the application
 * password on central.wordcamp.org (Users > Profile > Application Passwords)
 * and the Airtable personal access token yourself.
 *
 * @package WordCamp_Airtable_Connector
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'wcac_settings', 'wcac_queue', 'wcac_camps', 'wcac_state', 'wcac_log' ) as $option ) {
	delete_option( $option );
}

delete_transient( 'wcac_lock' );

wp_clear_scheduled_hook( 'wcac_run_queue' );
wp_clear_scheduled_hook( 'wcac_continue_queue' );
