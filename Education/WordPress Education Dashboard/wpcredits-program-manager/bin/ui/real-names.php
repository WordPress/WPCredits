<?php
/**
 * Collects the names to refuse on screen, from the copy's own stored lists, and prints them as JSON.
 *
 * Run on the local copy only, by bin/ui/copy.sh prepare (`wp eval-file`), before the scrub; the list goes to
 * the work folder and nowhere else, so no name is ever written in a file of this repository. The names are
 * the institutions' and sponsors' names and the schools' website hosts from the site's own indexes and
 * records, and the Administrators' own names. It refuses, printing nothing, unless both the copy's siteurl
 * and home are exactly http://localhost:8900.
 *
 * @package WPCreditsProgramManager
 */

if ( 'http://localhost:8900' !== (string) get_option( 'siteurl' ) || 'http://localhost:8900' !== (string) get_option( 'home' ) ) {
	fwrite( STDERR, "refusing: the copy's siteurl and home are not both http://localhost:8900\n" );
	exit( 1 );
}

$wpcpm_ui_names = array();
$wpcpm_ui_add   = static function ( $v ) use ( &$wpcpm_ui_names ) {
	if ( ! is_string( $v ) ) {
		return;
	}
	$v = trim( html_entity_decode( wp_strip_all_tags( $v ), ENT_QUOTES, 'UTF-8' ) );
	// A record ID (rec and fourteen letters or digits) is not a name, and neither is anything invented.
	if ( mb_strlen( $v ) >= 3 && ! preg_match( '/^rec[A-Za-z0-9]{14}$/', $v ) && false === stripos( $v, 'example' ) ) {
		$wpcpm_ui_names[ $v ] = true;
	}
};

if ( class_exists( 'WPCPM_Institutions_Index' ) ) {
	foreach ( (array) WPCPM_Institutions_Index::rows() as $row ) {
		foreach ( array( 'name', 'institution', 'institution_name' ) as $k ) {
			if ( isset( $row[ $k ] ) ) {
				$wpcpm_ui_add( $row[ $k ] );
			}
		}
		// A school's website names it too.
		if ( ! empty( $row['website'] ) && is_string( $row['website'] ) ) {
			$host = preg_replace( '#^(https?://)?(www\.)?#i', '', strtolower( trim( $row['website'] ) ) );
			$host = preg_replace( '~[/?#].*$~', '', (string) $host );
			if ( strlen( (string) $host ) >= 6 && false === strpos( (string) $host, 'example' ) && ! preg_match( '#^(?:localhost|127\.0\.0\.1)(?::\d+)?$#', (string) $host ) ) {
				$wpcpm_ui_names[ (string) $host ] = true;
			}
		}
	}
}

if ( class_exists( 'WPCPM_Sponsors_Index' ) ) {
	foreach ( (array) WPCPM_Sponsors_Index::rows() as $row ) {
		foreach ( array( 'name', 'company_name', 'sponsor_name', 'title' ) as $k ) {
			if ( isset( $row[ $k ] ) ) {
				$wpcpm_ui_add( $row[ $k ] );
			}
		}
	}
}

global $wpdb;
$wpcpm_ui_types = array( 'wpcpm_inst_record', 'wpcpm_inst_app', 'wpcpm_sponsor_co', 'wpcpm_sponsor_app', 'wpcpm_agreement', 'wpcpm_sponsor_agr' );
$wpcpm_ui_in    = implode( ',', array_fill( 0, count( $wpcpm_ui_types ), '%s' ) );
// phpcs:ignore WordPress.DB -- A read of titles on the local copy, by a script run by hand.
foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE post_type IN ($wpcpm_ui_in)", $wpcpm_ui_types ) ) as $wpcpm_ui_title ) {
	$wpcpm_ui_add( $wpcpm_ui_title );
}

// Institution applications' stored answers: the school name cell.
if ( class_exists( 'WPCPM_Institution_Application' ) && class_exists( 'WPCPM_Institutions_Sync' ) ) {
	$wpcpm_ui_fields = WPCPM_Institutions_Sync::fields();
	// phpcs:ignore WordPress.DB -- As above.
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'wpcpm_inst_app' ) ) as $wpcpm_ui_id ) {
		$wpcpm_ui_f = get_post_meta( (int) $wpcpm_ui_id, WPCPM_Institution_Application::META_FIELDS, true );
		if ( is_array( $wpcpm_ui_f ) && isset( $wpcpm_ui_fields['name'], $wpcpm_ui_f[ $wpcpm_ui_fields['name'] ] ) ) {
			$wpcpm_ui_add( $wpcpm_ui_f[ $wpcpm_ui_fields['name'] ] );
		}
	}
}

// The Administrators' own names, as the copy holds them before the scrub: a first or last name, and a
// display name that is not the account's login. An address is not listed: snap.mjs refuses every address
// that is not a role address or at an invented domain on its own.
foreach ( get_users( array( 'role__in' => array( 'administrator' ) ) ) as $wpcpm_ui_user ) {
	$wpcpm_ui_add( (string) get_user_meta( $wpcpm_ui_user->ID, 'first_name', true ) );
	$wpcpm_ui_add( (string) get_user_meta( $wpcpm_ui_user->ID, 'last_name', true ) );
	// The scrub keeps an Administrator's login, and many logins are a person's name run together: listed,
	// so the Administrators screen's Accounts tab (and any page that prints a login) is refused. snap.mjs
	// leaves out the rig's own login.
	$wpcpm_ui_add( (string) $wpcpm_ui_user->user_login );
	if ( strtolower( (string) $wpcpm_ui_user->display_name ) !== strtolower( (string) $wpcpm_ui_user->user_login ) ) {
		$wpcpm_ui_add( (string) $wpcpm_ui_user->display_name );
	}
}

echo wp_json_encode( array_keys( $wpcpm_ui_names ) );
