<?php
// Collects the names to refuse on screen: institutions' and sponsors' names from the site's own stored lists.
// Prints JSON to stdout. Refuses unless the site is the local copy.
if ( 'http://localhost:8900' !== get_option( 'siteurl' ) ) { fwrite( STDERR, "refusing\n" ); exit( 1 ); }
$names = array();
$add = static function ( $v ) use ( &$names ) {
	if ( is_string( $v ) ) { $v = trim( html_entity_decode( wp_strip_all_tags( $v ), ENT_QUOTES, 'UTF-8' ) ); if ( mb_strlen( $v ) >= 3 ) { $names[ $v ] = true; } }
};
if ( class_exists( 'WPCPM_Institutions_Index' ) ) {
	foreach ( (array) WPCPM_Institutions_Index::rows() as $row ) {
		foreach ( array( 'name', 'institution', 'institution_name' ) as $k ) { if ( isset( $row[ $k ] ) ) { $add( $row[ $k ] ); } }
		if ( ! empty( $row['website'] ) && is_string( $row['website'] ) ) { $h = preg_replace( '#^(https?://)?(www\.)?#i', '', strtolower( trim( $row['website'] ) ) ); $h = preg_replace( '~[/?#].*$~', '', $h ); if ( strlen( $h ) >= 6 && false === strpos( $h, 'example' ) ) { $names[ $h ] = true; } }
	}
}
if ( class_exists( 'WPCPM_Sponsors_Index' ) ) {
	foreach ( (array) WPCPM_Sponsors_Index::rows() as $row ) {
		foreach ( array( 'name', 'company_name', 'sponsor_name', 'title' ) as $k ) { if ( isset( $row[ $k ] ) ) { $add( $row[ $k ] ); } }
	}
}
global $wpdb;
$types = array( 'wpcpm_inst_record', 'wpcpm_inst_app', 'wpcpm_sponsor_co', 'wpcpm_sponsor_app', 'wpcpm_agreement', 'wpcpm_sponsor_agr' );
$in = implode( ',', array_fill( 0, count( $types ), '%s' ) );
foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE post_type IN ($in)", $types ) ) as $t ) { $add( $t ); }
// Institution applications' stored answers: the school name cell.
foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'wpcpm_inst_app' ) ) as $id ) {
	$f = get_post_meta( (int) $id, WPCPM_Institution_Application::META_FIELDS, true );
	if ( is_array( $f ) ) { $fields = WPCPM_Institutions_Sync::fields(); if ( isset( $f[ $fields['name'] ] ) ) { $add( $f[ $fields['name'] ] ); } }
}
echo wp_json_encode( array_keys( $names ) );
