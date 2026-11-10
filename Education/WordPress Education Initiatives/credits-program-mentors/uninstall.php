<?php
/**
 * Uninstall routine — removes plugin options and cached records.
 *
 * @package CreditsProgramMentors
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'credprme_settings' );
delete_option( 'credprme_cache_salt' );

// Remove cached Airtable record transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_credprme_records_%' OR option_name LIKE '_transient_timeout_credprme_records_%' OR option_name LIKE '_transient_credprme_error_%' OR option_name LIKE '_transient_timeout_credprme_error_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
