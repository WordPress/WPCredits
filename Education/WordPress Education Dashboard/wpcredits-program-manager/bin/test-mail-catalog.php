<?php
/**
 * The mail catalog: every email the site can send has a name, an area and an audience.
 *
 * The catalog is what the Log tab shows in its Email and Area columns, so an email the code sends
 * under a context the catalog does not know would show as "Other email" for no reason. The source
 * scan below reads every context the plugin passes to the mail layer and fails on one the catalog
 * lacks.
 *
 * Run from the plugin root:  php bin/test-mail-catalog.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $s, $d = null ) { return $s; }

require __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

$emails = WPCPM_Mail_Catalog::emails();

ck( 'the catalog names the 42 contexts the plugin sends today', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 42 );
ck( 'and reads the same list twice in a request', WPCPM_Mail_Catalog::emails() === $emails, true );

$bad = array();
foreach ( $emails as $id => $e ) {
	if ( ! preg_match( '/^[a-z0-9-]+$/', $id ) || '' === $e['label'] || ! isset( WPCPM_Mail_Catalog::modules()[ $e['module'] ] ) || ( '' !== $e['audience'] && ! isset( WPCPM_Mail_Catalog::types()[ $e['audience'] ] ) ) || ! is_bool( $e['test'] ) ) {
		$bad[] = $id;
	}
}
ck( 'every entry has a key of letters, digits and hyphens, a label, a known area and a known audience', $bad, array() );

ck( 'the four samples from Settings > Mail are tests, nothing else is', array_keys( array_filter( $emails, function ( $e ) { return $e['test']; } ) ), array( 'test-student', 'test-mentor', 'test-institution', 'test-sponsor' ) );

ck( 'an unknown id is "Other email" in the Other area', array( WPCPM_Mail_Catalog::label( 'no-such' ), WPCPM_Mail_Catalog::module_of( 'no-such' ), WPCPM_Mail_Catalog::label( '' ) ), array( 'Other email', 'other', 'Other email' ) );

ck( 'a known id reads back', array( WPCPM_Mail_Catalog::label( 'call-booked' ), WPCPM_Mail_Catalog::module_of( 'call-booked' ) ), array( 'Call booked', 'calls' ) );

ck( 'the last member leaving an institution is named, as the Administrators\' email it is', WPCPM_Mail_Catalog::get( 'member-last' ), array( 'label' => 'Institution has no members left, to Administrators', 'module' => 'institutions', 'audience' => 'administrator', 'test' => false ) );

ck( 'the areas are the ones the Log lists, in its order', array_values( WPCPM_Mail_Catalog::modules() ), array( 'Invitations', 'Mentor calls', 'Institutions', 'Semester reports', 'Sponsors', 'WordPress', 'Two Factor', 'Other' ) );

// Every context the plugin hands the mail layer, read from the source: a literal, or a constant the
// same file declares, as the second argument of send(), send_to() and mail_members(), the first of
// notify_managers() (called directly or through call_user_func()), the third of mail_manager() or
// its default, and the two built from a kind.
$contexts = array();
foreach ( array_merge( glob( __DIR__ . '/../includes/*.php' ), glob( __DIR__ . '/../includes/*/*.php' ) ) as $file ) {
	$src = (string) file_get_contents( $file );
	preg_match_all( "/const\s+(\w+)\s*=\s*'([a-z0-9-]+)'\s*;/", $src, $c, PREG_SET_ORDER );
	$consts = array_column( $c, 2, 1 );
	$arg    = "(?:'([a-z0-9-]+)'|self::(\w+))\s*[,)]";
	foreach ( array( "/(?:WPCPM_Mail|self)::send(?:_to)?\(\s*[^,]+,\s*$arg/", "/mail_members\(\s*[^,]+,\s*$arg/", "/notify_managers(?:\(|'\s*\),)\s*$arg/", "/mail_manager\(\s*[^,]+,\s*[^,]+,\s*$arg/" ) as $pattern ) {
		preg_match_all( $pattern, $src, $m, PREG_SET_ORDER );
		foreach ( $m as $hit ) {
			$contexts[ ! empty( $hit[1] ) ? $hit[1] : ( isset( $consts[ $hit[2] ] ) ? $consts[ $hit[2] ] : 'unresolved:' . $hit[2] ) ] = true;
		}
	}
	if ( preg_match( '/mail_manager\(\s*[^,()]+,\s*\$\w+\s*\)/', $src ) ) {
		$contexts['sponsor-interest'] = true;
	}
	foreach ( array( 'invite-', 'test-' ) as $built ) {
		if ( false !== strpos( $src, "'" . $built . "' . " ) ) {
			foreach ( array( 'student', 'mentor', 'institution', 'sponsor' ) as $k ) {
				$contexts[ $built . $k ] = true;
			}
		}
	}
}
$missing = array_values( array_diff( array_keys( $contexts ), array_keys( $emails ) ) );
sort( $missing );
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 42 of them (it is not reading nothing)', count( $contexts ), 42 );

$filters = WPCPM_Mail_Catalog::wordpress_filters();
$unnamed = array_values( array_diff( array_values( $filters ), array_keys( $emails ) ) );
ck( 'every WordPress and Two Factor filter names an email the catalog has', $unnamed, array() );
ck( 'and every WordPress and Two Factor email the catalog has is named by one', array_values( array_diff( array_keys( array_filter( $emails, function ( $e ) { return in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), array_values( $filters ) ) ), array() );
ck( 'the Two Factor sign-in code is named by its subject filter', isset( $filters['two_factor_token_email_subject'] ) ? $filters['two_factor_token_email_subject'] : '', 'two-factor-code' );
ck( 'WordPress\'s password reset is named', isset( $filters['retrieve_password_notification_email'] ) ? $filters['retrieve_password_notification_email'] : '', 'wp-password-reset' );
ck(
	'and so are the erasure, the confirmed request and the background update details',
	array( $filters['user_erasure_fulfillment_email_content'] ?? '', $filters['user_request_confirmed_email_content'] ?? '', $filters['automatic_updates_debug_email'] ?? '' ),
	array( 'wp-personal-data-erased', 'wp-personal-data-request-confirmed', 'wp-updates-debug' )
);

$text = (string) file_get_contents( __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php' );
ck( 'no em or en dash in the catalog', preg_match( '/\x{2013}|\x{2014}/u', $text ), 0 );
ck( 'and no label says module', array_values( array_filter( array_merge( array_column( $emails, 'label' ), array_values( WPCPM_Mail_Catalog::modules() ), array_values( WPCPM_Mail_Catalog::types() ) ), function ( $l ) { return 1 === preg_match( '/\bmodules?\b/i', $l ); } ) ), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
