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

ck( 'the catalog names the plugin\'s 70 emails: the 42 contexts it sent before 1.122.19, the institution invitation for an agreement on file, the test of the signature and footer, the 19 Mentor calls emails of their own since 1.122.21, the 4 Institutions and 2 Sponsors emails of their own since 1.122.22, and the institution\'s invitation with no sender named, its own since 1.122.23', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 70 );
ck( 'and reads the same list twice in a request', WPCPM_Mail_Catalog::emails() === $emails, true );

$bad = array();
foreach ( $emails as $id => $e ) {
	if ( ! preg_match( '/^[a-z0-9-]+$/', $id ) || '' === $e['label'] || ! isset( WPCPM_Mail_Catalog::modules()[ $e['module'] ] ) || ( '' !== $e['audience'] && ! isset( WPCPM_Mail_Catalog::types()[ $e['audience'] ] ) ) || ! is_bool( $e['test'] ) ) {
		$bad[] = $id;
	}
}
ck( 'every entry has a key of letters, digits and hyphens, a label, a known area and a known audience', $bad, array() );

ck( 'the four samples from Settings > Mail and the test of the signature and footer are tests, nothing else is', array_keys( array_filter( $emails, function ( $e ) { return $e['test']; } ) ), array( 'test-student', 'test-mentor', 'test-institution', 'test-sponsor', 'footer-test' ) );
ck( 'the test of the signature and footer belongs to no area, and goes to an Administrator', WPCPM_Mail_Catalog::get( 'footer-test' ), array( 'label' => 'Test of the signature and footer', 'module' => 'other', 'audience' => 'administrator', 'test' => true ) );

ck( 'an unknown id is "Other email" in the Other area', array( WPCPM_Mail_Catalog::label( 'no-such' ), WPCPM_Mail_Catalog::module_of( 'no-such' ), WPCPM_Mail_Catalog::label( '' ) ), array( 'Other email', 'other', 'Other email' ) );

ck( 'a known id reads back', array( WPCPM_Mail_Catalog::label( 'call-booked' ), WPCPM_Mail_Catalog::module_of( 'call-booked' ) ), array( 'Call booked (until 1.122.21)', 'calls' ) );

// Each email of the Mentor calls area that says words of its own has an id of its own, named for who
// receives it; the two a group session sends its mentor and its students in the same words are one
// each, and the account decides who it is to. The five ids the area was sent under before 1.122.21
// keep their names with " (until 1.122.21)" after them, so the Log's Email field, which lists them
// beside the new names, tells the old from the new.
$calls = array();
foreach ( $emails as $id => $e ) {
	if ( 'calls' === $e['module'] ) {
		$calls[ $id ] = $e['label'] . ' / ' . ( '' === $e['audience'] ? 'the account decides' : $e['audience'] );
	}
}
ck(
	'the Mentor calls area names its five ids of before 1.122.21, each labeled as one of before, and the nineteen emails it sends since, each with who receives it',
	$calls,
	array(
		'call-booked'                   => 'Call booked (until 1.122.21) / the account decides',
		'series-joined'                 => 'Joined a session series (until 1.122.21) / the account decides',
		'session-moved'                 => 'Group session moved (until 1.122.21) / the account decides',
		'call-cancelled'                => 'Call canceled (until 1.122.21) / the account decides',
		'call-reminder'                 => 'Call reminder (until 1.122.21) / the account decides',
		'call-booked-mentor'            => 'Call booked, to the mentor / mentor',
		'call-booked-mentor-no-link'    => 'Call booked, to a mentor with no meeting link / mentor',
		'call-booked-student'           => 'Call booked, to the student / student',
		'session-joined-mentor'         => 'Group session joined, to the mentor / mentor',
		'session-joined-mentor-no-link' => 'Group session joined, to a mentor with no meeting link / mentor',
		'session-joined-student'        => 'Group session joined, to the student / student',
		'series-joined-mentor'          => 'Session series joined, to the mentor / mentor',
		'series-joined-mentor-no-link'  => 'Session series joined, to a mentor with no meeting link / mentor',
		'series-joined-student'         => 'Session series joined, to the student / student',
		'session-moved-student'         => 'Group session moved, to the student / student',
		'session-moved-mentor'          => 'Group session moved, to the mentor / mentor',
		'session-left'                  => 'Left a group session, to the student / student',
		'session-released'              => 'Place on a group session released, to the student / student',
		'call-cancelled-mentor'         => 'Call canceled, to the mentor / mentor',
		'call-cancelled-student'        => 'Call canceled, to the student / student',
		'session-cancelled'             => 'Group session canceled / the account decides',
		'call-reminder-mentor'          => 'Call reminder, to the mentor / mentor',
		'call-reminder-student'         => 'Call reminder, to the student / student',
		'session-reminder'              => 'Group session reminder / the account decides',
	)
);
ck(
	'and the Semester reports area\'s three ids are its three emails, as they were',
	array_map( function ( $id ) { return WPCPM_Mail_Catalog::get( $id ); }, array( 'report-drafted', 'report-approved', 'report-consent' ) ),
	array(
		array( 'label' => 'Semester report drafted', 'module' => 'reports', 'audience' => 'administrator', 'test' => false ),
		array( 'label' => 'Semester report approved', 'module' => 'reports', 'audience' => 'institution', 'test' => false ),
		array( 'label' => 'Consent for the semester report', 'module' => 'reports', 'audience' => 'student', 'test' => false ),
	)
);

// Each email of the Institutions and the Sponsors areas that says words of its own has an id of its own
// since 1.122.22: the ids each area was sent under keep their emails, their names reading true of
// them, and six new ones name the emails that said other words under them (the acknowledgement with
// no call to book or whose logo was not kept, the upload the courtesy scan noticed something in, the
// revocation with no country contact) or another email altogether (the agreement put back in force,
// sent as an acceptance until then, and the request to sponsor a mentor, sent as a sponsor's
// interest). The institution's agreement emails are named for the Collaboration Agreement in full,
// and the sponsor's say who receives each, as the institution's do.
$named = array();
foreach ( $emails as $id => $e ) {
	if ( in_array( $e['module'], array( 'institutions', 'sponsors' ), true ) ) {
		$named[ $e['module'] ][ $id ] = $e['label'] . ' / ' . ( '' === $e['audience'] ? 'the account decides' : $e['audience'] );
	}
}
ck(
	'the Institutions area names its thirteen ids of before 1.122.22, the four emails of their own since, and the invitation with no sender named, its own since 1.122.23, each with who receives it',
	isset( $named['institutions'] ) ? $named['institutions'] : null,
	array(
		'institution-applied'          => 'Application received, to the applicant / applicant',
		'institution-applied-no-call'  => 'Application received, to an applicant with no call to book / applicant',
		'institution-application'      => 'New institution application, to Administrators / administrator',
		'institution-information'      => 'Question about an application / applicant',
		'institution-declined'         => 'Application declined / applicant',
		'institution-invite'           => 'Invitation to join an institution / institution',
		'institution-invite-no-sender' => 'Invitation to join an institution, with no sender named / institution',
		'agreement-received'           => 'Collaboration Agreement received, to the institution / institution',
		'agreement-landed'             => 'Collaboration Agreement uploaded, to Administrators / administrator',
		'agreement-landed-flagged'     => 'Collaboration Agreement uploaded with the scan\'s notes, to Administrators / administrator',
		'agreement-accepted'           => 'Collaboration Agreement accepted, to the institution / institution',
		'agreement-reinstated'         => 'Collaboration Agreement back in force, to the institution / institution',
		'agreement-returned'           => 'Collaboration Agreement returned with a note, to the institution / institution',
		'agreement-revoked'            => 'Collaboration Agreement taken out of force, to the institution / institution',
		'agreement-revoked-no-contact' => 'Collaboration Agreement taken out of force, to an institution with no country contact / institution',
		'agreement-reminder'           => 'Collaboration Agreements waiting for review / administrator',
		'agreement-template'           => 'Collaboration Agreement could not be generated / administrator',
		'member-last'                  => 'Institution has no members left, to Administrators / administrator',
	)
);
ck(
	'and the Sponsors area its thirteen ids of before 1.122.22 and the two emails of their own since; the low codes go to the sponsor\'s accounts and its Administrator in the same words, so the account decides',
	isset( $named['sponsors'] ) ? $named['sponsors'] : null,
	array(
		'sponsor-applied'              => 'Sponsor application received, to the applicant / applicant',
		'sponsor-applied-no-logo'      => 'Sponsor application received, to an applicant whose logo was not kept / applicant',
		'sponsor-application'          => 'New sponsor application, to Administrators / administrator',
		'sponsor-information'          => 'Question about a sponsor application / applicant',
		'sponsor-declined'             => 'Sponsor application declined / applicant',
		'sponsor-agreement-received'   => 'Collaboration Agreement received, to Administrators / administrator',
		'sponsor-agreement-accepted'   => 'Collaboration Agreement accepted, to the sponsor / sponsor',
		'sponsor-agreement-returned'   => 'Collaboration Agreement returned with a note, to the sponsor / sponsor',
		'sponsor-agreement-revoked'    => 'Collaboration Agreement taken out of force, to the sponsor / sponsor',
		'sponsor-agreement-reinstated' => 'Collaboration Agreement back in force, to the sponsor / sponsor',
		'sponsor-interest'             => 'Sponsor interest / administrator',
		'sponsor-mentor-interest'      => 'Request to sponsor a mentor / administrator',
		'offer-low-stock'              => 'Codes running low / the account decides',
		'claim-problem'                => 'Problem with a claimed code / administrator',
		'sponsor-post-returned'        => 'Sponsor post returned with a note / sponsor',
	)
);

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
	if ( false !== strpos( $src, "'invite-' . " ) ) {
		foreach ( array( 'student', 'mentor', 'institution', 'sponsor' ) as $k ) {
			$contexts[ 'invite-' . $k ] = true;
		}
	}
	if ( false !== strpos( $src, "'test-' . " ) ) {
		$contexts['test- built from a kind'] = true;
	}
}
$missing = array_values( array_diff( array_keys( $contexts ), array_keys( $emails ) ) );
sort( $missing );
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 34 of them (it is not reading nothing); the institution\'s invitation is set from a choice between two ids since 1.122.23, and read from the registry\'s files below', count( $contexts ), 34 );

// The template registry's ids, read from its definition files as each file writes them: one at the
// start of each line of the array the file returns. An invitation is named by its template's id,
// and one of them, the institution invitation for an agreement on file, is set from a choice
// between two ids rather than passed as a literal the scan above can read; so, since 1.122.22, is
// each email of the Institutions and the Sponsors that says other words in one case than in another
// (the acknowledgement with or without a call to book, or with its logo kept or not; the upload with
// the scan's notes or none; the revocation with a country contact or none), and, since 1.122.23, the
// institution's invitation, with its inviter named or with nobody to name; so the registry's files
// are where both of each two are read.
$templates = array();
foreach ( glob( __DIR__ . '/../includes/mail/templates/*.php' ) as $file ) {
	preg_match_all( "/^\t'([a-z0-9-]+)'\s*=>\s*array\(/m", (string) file_get_contents( $file ), $t );
	$templates = array_merge( $templates, $t[1] );
}
ck( 'every template the registry defines is in the catalog', array_values( array_diff( $templates, array_keys( $emails ) ) ), array() );
// The four samples Settings > Mail sent until 1.122.19, and the five ids the Mentor calls were sent
// under until 1.122.21, stay in the catalog, so the Log names the rows they left, which it keeps for
// 30 days; nothing sends them now: Send me a test logs a test under its template's own id, and each
// email of the Mentor calls is sent under its own template's.
$retired    = array( 'test-student', 'test-mentor', 'test-institution', 'test-sponsor', 'call-booked', 'series-joined', 'session-moved', 'call-cancelled', 'call-reminder' );
$plugin_ids = array_values( array_diff( array_keys( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), $retired ) );
$sent_ids   = array_unique( array_merge( array_keys( $contexts ), $templates ) );
sort( $plugin_ids );
sort( $sent_ids );
ck( 'and every email of the plugin\'s the catalog names is one the source sends or a template the registry holds, but the four samples Settings > Mail sent before 1.122.19 and the five Mentor calls ids of before 1.122.21', $plugin_ids, $sent_ids );
ck( 'which nothing sends any more', array_values( array_intersect( $retired, $sent_ids ) ), array() );

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
