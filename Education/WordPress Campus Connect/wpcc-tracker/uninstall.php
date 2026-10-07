<?php
/**
 * Uninstall cleanup: remove every option this plugin ever writes.
 *
 * Deleting the plugin must not leave the Airtable personal access token - the
 * one credential it stores, inside wpcct_settings - sitting in wp_options.
 *
 * This file is loaded directly by WordPress, outside the plugin's own
 * bootstrap, so nothing here may rely on the WPCCT_* constants defined in
 * wpcc-tracker.php; the option names are spelled out in full. The guard is
 * WP_UNINSTALL_PLUGIN rather than ABSPATH, which is what tells this file it
 * was reached through the uninstall handler and not requested directly.
 *
 * @package WPCC_Tracker
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wpcct_options = array(
	'wpcct_settings',      // Airtable PAT, base id and table names.
	'wpcct_data',          // The normalised dashboard blob.
	'wpcct_last_sync',     // Timestamp of the last successful sync.
	'wpcct_last_error',    // Message from the last failed sync.
	'wpcct_sync_progress', // Live/last progress record.
	'wpcct_sync_lock',     // Short-lived concurrency lock.
);

foreach ( $wpcct_options as $wpcct_option ) {
	delete_option( $wpcct_option );
}

// Multisite: the same options are per-site, so clear them on every site.
if ( is_multisite() ) {
	$wpcct_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $wpcct_site_ids as $wpcct_site_id ) {
		switch_to_blog( $wpcct_site_id );
		foreach ( $wpcct_options as $wpcct_option ) {
			delete_option( $wpcct_option );
		}
		restore_current_blog();
	}
}
