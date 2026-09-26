<?php
/**
 * Does pressing Save actually save?
 *
 * This exists because it did not. Adding a field took three edits - render it, sanitise it in
 * `WPCPM_Settings::save()`, and add it to a hand-written allowlist in the save handler - and
 * forgetting the third produced a field that renders, accepts what you type, posts it, and
 * discards it without a word. Twenty-one settings were in that state, including the AI
 * provider and its key, which is how it was found: somebody entered a key and it vanished.
 *
 * The allowlist is now derived from the defaults, and this suite pins that: every setting the
 * form renders must survive a round trip through the real handler.
 *
 * Run from the plugin root:  php bin/test-settings.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MONTH_IN_SECONDS', 2592000 );

$GLOBALS['opts'] = array();

class WP_Error {
	public function __construct( $c = '', $m = '' ) {}
	public function get_error_message() { return ''; }
}
class WP_User {
	public $ID = 0, $roles = array(), $display_name = '';
	public function __construct( $id = 0 ) { $this->ID = $id; }
	public function exists() { return $this->ID > 0; }
}
class WP_Post { public $ID = 0, $post_content = '', $post_status = 'publish', $post_title = ''; }

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
/**
 * Honours the protocol allowlist, as the real one does.
 *
 * A pass-through stub here would make the "a javascript: endpoint is refused" assertion pass
 * no matter what the plugin did, which is worse than not asserting it.
 */
function esc_url_raw( $u, $protocols = null ) {
	$u = trim( (string) $u );

	if ( '' === $u ) {
		return '';
	}

	$scheme = strtolower( (string) parse_url( $u, PHP_URL_SCHEME ) );

	if ( null === $protocols ) {
		$protocols = array( 'http', 'https', 'mailto' );
	}

	return in_array( $scheme, (array) $protocols, true ) ? $u : '';
}
function esc_textarea( $s ) { return esc_html( $s ); }
/**
 * Core's, in the two ways a status list tells apart (BUILDER-3's fix round): a "<" that opens no tag
 * is kept as `&lt;` rather than taken with the rest of the line, and every run of spaces, tabs and
 * line breaks is one space. A stand-in that did neither could not see a guard compare a raw status
 * with a sanitized one.
 *
 * @param mixed $s The value.
 * @return string
 */
function sanitize_text_field( $s ) {
	$s = (string) $s;

	if ( false !== strpos( $s, '<' ) ) {
		$s = preg_replace_callback(
			'%<[^>]*?((?=<)|>|$)%',
			function ( $m ) {
				return false === strpos( $m[0], '>' ) ? esc_html( $m[0] ) : $m[0];
			},
			$s
		);
		$s = strip_tags( $s );
	}

	return trim( preg_replace( '/[\r\n\t ]+/', ' ', $s ) );
}
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
/**
 * Strips what the real one strips, so a junk address does not become a valid-looking one
 * by accident here and get asserted as kept.
 */
function sanitize_email( $e ) {
	$e = (string) $e;

	if ( strlen( $e ) < 3 || false === strpos( $e, '@', 1 ) || substr_count( $e, '@' ) > 1 ) {
		return '';
	}

	list( $local, $domain ) = explode( '@', $e, 2 );

	$local  = preg_replace( '/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.\[\]-]/', '', $local );
	$domain = preg_replace( '/[^a-zA-Z0-9\.\-]/', '', $domain );

	return ( '' === $local || '' === $domain ) ? '' : $local . '@' . $domain;
}
function is_email( $e ) { return false !== filter_var( (string) $e, FILTER_VALIDATE_EMAIL ) ? $e : false; }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u, $c ); }
function wp_timezone_string() { return 'UTC'; }
require_once __DIR__ . '/stubs/caps.php';
$GLOBALS['caps'] = true; // the settings screen is a manager's
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { return true; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect' ); }
function wp_die( $m = '' ) { throw new Exception( 'wp_die: ' . $m ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_bloginfo( $k = 'name' ) { return 'Test'; }
function wp_specialchars_decode( $s, $q = null ) { return (string) $s; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
// Kept, so a flash can be read back from the raw meta the real WPCPM_Flash writes (BUILDER-3).
$GLOBALS['umeta'] = array();
function get_user_meta( $id, $k, $s = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_current_user_id() { return 1; }
function wp_get_current_user() { return new WP_User( 1 ); }
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-handbook-answer.php';
// The settings screen's own save handler, pressed for real in the BUILDER-3 section below.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
// The track rules, whose comparison of a status with the past statuses the "Past students" section
// below holds the save to: the real one, since a stand-in would only agree with itself.
require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-definition.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/* ---- every rendered field survives a save ------------------------------- */

echo "=== Nothing the form renders is discarded ===\n";

$admin    = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php' );
$defaults = WPCPM_Settings::defaults();

// The fields the settings screen puts on the page, however it draws them.
$rendered = array();

preg_match_all( "/\\\$this->text_row\(\s*\n?\s*'([a-z_]+)'/", $admin, $m );
$rendered = array_merge( $rendered, $m[1] );

preg_match_all( '/name="([a-z_]+)(?:\[\])?"/', $admin, $m );
$rendered = array_merge( $rendered, $m[1] );

$rendered = array_values( array_intersect( array_unique( $rendered ), array_keys( $defaults ) ) );

ck( 'the form renders a meaningful number of settings', array( count( $rendered ) > 20 ), array( true ) );

// The handler reads from the defaults, so what it forwards is derivable rather than listed.
// Asserted by *reading the source*: the failure being pinned is a hand-written list drifting
// from the form, and a list cannot drift if there is not one.
ck( 'the handler derives its keys from the defaults, not a hand-written list',
    array(
        false !== strpos( $admin, '$defaults = WPCPM_Settings::defaults();' ),
        false !== strpos( $admin, "\$keys = array( 'api_token'" ),
    ),
    array( true, false ) );

// A round trip through the real sanitiser for every non-boolean field, with a value that is
// recognisably ours coming back. Every key in the defaults must appear here or in the
// boolean probe below, and that is asserted, so a setting cannot be added without its
// round trip - the failure this file exists for is a field that saves nothing.
$probe = array(
	'api_token'                     => 'patTESTTOKEN1234567890',
	'schema_token'                  => 'patSCHEMATOKEN9876543210',
	'base_id'                       => 'appPROBE0000000001',
	'mentors_table'                 => 'tblPROBE0000000002',
	'mentor_status'                 => 'Probe active',
	'reports_table'                 => 'tblPROBE0000000003',
	'students_table'                => 'tblPROBE0000000004',
	'tutors_table'                  => 'tblPROBE0000000014',
	'feedback_table'                => 'tblPROBE0000000005',
	'institutions_table'            => 'tblPROBE0000000001',
	'teams_table'                   => 'tblPROBE0000000006',
	'sponsors_table'                => 'tblPROBE0000000007',
	'institutions_name_field'       => 'Probe institution',
	'teams_name_field'              => 'Probe field',
	'sponsors_name_field'           => 'Probe sponsor',
	'student_statuses'              => array( 'Probe current', 'Probe paused' ),
	'past_statuses'                 => array( 'Probe past' ),
	'on_inactive'                   => 'keep',
	'handbook_provider'             => 'gemini',
	'handbook_key'                  => 'AQ.probe-key-value',
	'handbook_model'                => 'gemini-2.5-flash',
	'handbook_access'               => 'program',
	'handbook_limit'                => '35',
	'student_on_inactive'           => 'keep',
	'checker_source_status'         => 'Probe status',
	'checker_target_status'         => 'Probe target',
	'checker_course_slug'           => 'probe-course',
	'checker_course_title'          => 'Probe course',
	'checker_completion_phrase'     => 'Probe phrase',
	'checker_timeline_filter'       => 'all',
	'checker_max_pages'             => '7',
	'checker_batch_size'            => '4',
	'checker_request_delay'         => '250',
	'checker_cache_ttl'             => '7200',
	// Institutions module.
	'countries_table'               => 'tblPROBE0000000008',
	'countries_name_field'          => 'Probe country',
	'institution_new_stage'         => 'Probe stage',
	'institution_active_stages'     => array( 'Probe stage', 'Probe confirmed' ),
	'two_factor_roles'              => array( 'administrator', 'wpcpm_mentor' ),
	'institution_on_inactive'       => 'keep',
	'application_spam_days'         => '45',
	'application_rejected_days'     => '400',
	'application_approved_days'     => '90',
	'application_trusted_proxy'     => '203.0.113.7',
	'agreement_max_mb'              => '20',
	'agreement_uploads_per_day'     => '8',
	'agreement_generations_per_day' => '15',
	'agreement_review_days'         => '5',
	'agreement_doc_url'             => 'https://docs.google.com/document/d/PROBEDOC/edit',
	'agreement_notify'              => 'one@example.org,two@example.org',
	'agreement_discard_days'        => '60',
	'invite_retention_days'         => '45',
	// The semester report approval flow.
	'report_autodraft_grace_days'   => '30',
	'report_notify'                 => 'one@example.org,two@example.org',
	// The Sponsors module.
	'team_members_table'            => 'tblPROBE0000000015',
	'sponsor_on_inactive'           => 'revoke',
	'sponsor_notify'                => 'probe-one@example.org,probe-two@example.org',
	'logo_max_kb'                   => '2048',
	'offer_low_stock'               => '250',
);

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = WPCPM_Settings::defaults();

$saved = WPCPM_Settings::save( $probe );

foreach ( $probe as $key => $value ) {
	// Integers are posted as strings and come back cast; everything else comes back as sent.
	$expected = is_int( $defaults[ $key ] ) ? (int) $value : $value;

	ck( sprintf( '%s survives the save', $key ), array( $saved[ $key ] ), array( $expected ) );
}

// Every checkbox, flipped away from its default so a value stuck at the default shows.
$bool_probe = array();

foreach ( $defaults as $key => $default ) {
	if ( is_bool( $default ) ) {
		$bool_probe[ $key ] = ! $default;
	}
}

$saved = WPCPM_Settings::save( $bool_probe );

foreach ( $bool_probe as $key => $value ) {
	ck( sprintf( '%s can be flipped to %s', $key, var_export( $value, true ) ), array( $saved[ $key ] ), array( $value ) );
}

echo "\n=== The schema token, the second secret ===\n";

$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => 'patKEEPME0000000000' ) );

ck( 'a blank schema token leaves the stored one alone, as the everyday token does',
    WPCPM_Settings::save( array( 'schema_token' => '' ) )['schema_token'], 'patKEEPME0000000000' );

$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => 'patKEEPME0000000000' ) );

ck( 'the mask coming back on submit never overwrites it either',
    WPCPM_Settings::save( array( 'schema_token' => WPCPM_Settings::masked_schema_token() ) )['schema_token'], 'patKEEPME0000000000' );

$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => 'patKEEPME0000000000' ) );

// A field that only ever shows a mask has no other way to say "take it away", and a site that
// has finished creating columns should be able to put the right back.
ck( 'and the word remove clears it, which is the only way to take the right away again',
    WPCPM_Settings::save( array( 'schema_token' => 'Remove' ) )['schema_token'], '' );

$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => 'patKEEPME0000000000' ) );

ck( 'the mask shows the last four characters and nothing else',
    WPCPM_Settings::masked_schema_token(), str_repeat( "\u{2022}", 12 ) . '0000' );

ck( 'and the site knows whether it may create columns at all',
    array( WPCPM_Settings::has_schema_token(), ( function () { $GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => '' ) ); return WPCPM_Settings::has_schema_token(); } )() ),
    array( true, false ) );

ck( 'every setting has a round-trip probe',
    array_values( array_diff( array_keys( $defaults ), array_keys( $probe ), array_keys( $bool_probe ) ) ),
    array() );

ck( 'and every probe names a real setting',
    array_values( array_diff( array_keys( $probe ), array_keys( $defaults ) ) ),
    array() );

/* ---- the shapes that are easy to get wrong ------------------------------ */

echo "\n=== Awkward shapes ===\n";

// An unchecked checkbox posts nothing, so "off" and "this form did not render it" look the
// same. The handler always supplies every boolean, which is why this works.
$saved = WPCPM_Settings::save( array( 'handbook_enabled' => false ) );
ck( 'a boolean can be switched off', array( $saved['handbook_enabled'] ), array( false ) );

$saved = WPCPM_Settings::save( array( 'handbook_enabled' => '1' ) );
ck( 'and on again', array( $saved['handbook_enabled'] ), array( true ) );

// The key is write-only from the form's point of view: the screen shows a mask, and posting
// the mask back must not overwrite the real key with a row of dots.
WPCPM_Settings::save( array( 'handbook_key' => 'AQ.the-real-key' ) );
$saved = WPCPM_Settings::save( array( 'handbook_key' => WPCPM_Settings::masked_handbook_key() ) );
ck( 'posting the masked key back leaves the real one alone',
    array( $saved['handbook_key'] ), array( 'AQ.the-real-key' ) );

$saved = WPCPM_Settings::save( array( 'handbook_key' => '' ) );
ck( 'and so does posting nothing', array( $saved['handbook_key'] ), array( 'AQ.the-real-key' ) );

// Only providers the plugin can actually talk to.
$saved = WPCPM_Settings::save( array( 'handbook_provider' => 'nonsense' ) );
ck( 'an unknown provider falls back to none', array( $saved['handbook_provider'] ), array( '' ) );

$saved = WPCPM_Settings::save( array( 'handbook_access' => 'nonsense' ) );
ck( 'an unknown audience falls back to mentors', array( $saved['handbook_access'] ), array( 'mentor' ) );

$saved = WPCPM_Settings::save( array( 'handbook_limit' => '9999' ) );
ck( 'the rate limit is capped', array( $saved['handbook_limit'] ), array( 200 ) );

// Google AI Studio is the only provider, so there is no endpoint to configure and nothing
// here should invent one.
ck( 'no endpoint setting exists to be got wrong',
    array( array_key_exists( 'handbook_endpoint', WPCPM_Settings::defaults() ) ), array( false ) );

/* ---- the institutions settings ------------------------------------------ */

echo "\n=== Institutions settings ===\n";

// The settings screen does not render the four institution switches yet, so a save of the
// existing form omits them. That must leave them alone - the same input switches off a
// checkbox the form does render, which is the contrast being pinned.
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array_merge(
	WPCPM_Settings::defaults(),
	array(
		'institution_provision' => true,
		'institution_home'      => true,
		'applications_enabled'  => true,
		'import_enabled'        => true,
		'auto_sync'             => true,
	)
);

$saved = WPCPM_Settings::save( array( 'base_id' => 'appPROBE0000000002' ) );

foreach ( array( 'institution_provision', 'institution_home', 'applications_enabled', 'import_enabled' ) as $flag ) {
	ck( sprintf( '%s survives a save that omits it', $flag ), array( $saved[ $flag ] ), array( true ) );
}

ck( 'while a checkbox the form renders is switched off by the same save', array( $saved['auto_sync'] ), array( false ) );

$saved = WPCPM_Settings::save( array( 'institution_provision' => '', 'institution_home' => '0' ) );
ck( 'and an explicit off is honoured', array( $saved['institution_provision'], $saved['institution_home'] ), array( false, false ) );

// Each integer is clamped at both ends. The floors are the point: a typo of 0 in a retention
// field would otherwise purge everything on the next cron run.
$ranges = array(
	'application_spam_days'         => array( 1, 365 ),
	'application_rejected_days'     => array( 30, 3650 ),
	'application_approved_days'     => array( 0, 3650 ),
	'agreement_max_mb'              => array( 1, 50 ),
	'agreement_uploads_per_day'     => array( 1, 50 ),
	'agreement_generations_per_day' => array( 1, 100 ),
	'agreement_review_days'         => array( 1, 60 ),
	'agreement_discard_days'        => array( 7, 365 ),
	'invite_retention_days'         => array( 7, 365 ),
);

foreach ( $ranges as $key => $range ) {
	$saved = WPCPM_Settings::save( array( $key => (string) ( $range[0] - 1 ) ) );
	ck( sprintf( '%s is floored at %d', $key, $range[0] ), array( $saved[ $key ] ), array( $range[0] ) );

	$saved = WPCPM_Settings::save( array( $key => (string) ( $range[1] + 1 ) ) );
	ck( sprintf( '%s is capped at %d', $key, $range[1] ), array( $saved[ $key ] ), array( $range[1] ) );

	$saved = WPCPM_Settings::save( array( $key => (string) $range[0] ) );
	ck( sprintf( '%s accepts its floor', $key ), array( $saved[ $key ] ), array( $range[0] ) );
}

// The notify list: commas or newlines, valid addresses kept once, everything else dropped.
$saved = WPCPM_Settings::save(
	array(
		'agreement_notify' => "one@example.org, not-an-address\ntwo@example.org,name@\n@example.org, one@example.org\n\n  three@example.org  ",
	)
);
ck( 'agreement_notify keeps the addresses and drops the junk',
    array( $saved['agreement_notify'] ), array( 'one@example.org,two@example.org,three@example.org' ) );

$saved = WPCPM_Settings::save( array( 'agreement_notify' => "nothing here\njavascript:alert(1)" ) );
ck( 'and is empty when nothing valid was typed', array( $saved['agreement_notify'] ), array( '' ) );

$saved = WPCPM_Settings::save( array( 'agreement_notify' => 'kept@example.org' ) );
$saved = WPCPM_Settings::save( array( 'base_id' => 'appPROBE0000000003' ) );
ck( 'a save that omits the field leaves it alone', array( $saved['agreement_notify'] ), array( 'kept@example.org' ) );

$saved = WPCPM_Settings::save( array( 'agreement_notify' => 'A@Example.org, a@example.org' ) );
ck( 'one mailbox typed two ways is kept once', array( $saved['agreement_notify'] ), array( 'a@example.org' ) );

$saved = WPCPM_Settings::save( array( 'agreement_notify' => array( array( 'nested' ), 'b@example.org' ) ) );
ck( 'a nested array in a crafted request is skipped, not fatal', array( $saved['agreement_notify'] ), array( 'b@example.org' ) );

// The trusted proxy is compared with `REMOTE_ADDR`, so it is an IP or nothing. A value that
// can never match would read as a setting while trusting no header, which is empty in disguise;
// a list, a range or a URL is refused for the same reason, since none of them is one address.
foreach ( array(
	'not an ip'                => '',
	'203.0.113.7, 203.0.113.8' => '',
	'203.0.113.0/24'           => '',
	'https://203.0.113.7'      => '',
	'203.0.113.7'              => '203.0.113.7',
	' 203.0.113.7 '            => '203.0.113.7',
	'2001:db8::7'              => '2001:db8::7',
	''                         => '',
) as $typed => $want ) {
	$saved = WPCPM_Settings::save( array( 'application_trusted_proxy' => $typed ) );
	ck( sprintf( 'application_trusted_proxy refuses or keeps "%s"', $typed ), $saved['application_trusted_proxy'], $want );
}

$saved = WPCPM_Settings::save( array( 'application_trusted_proxy' => '203.0.113.7' ) );
$saved = WPCPM_Settings::save( array( 'base_id' => 'appPROBE0000000004' ) );
ck( 'a save that omits the proxy leaves it alone', $saved['application_trusted_proxy'], '203.0.113.7' );

$saved = WPCPM_Settings::save( array( 'application_trusted_proxy' => array( '203.0.113.7' ) ) );
ck( 'an array in a crafted request is dropped, not fatal', $saved['application_trusted_proxy'], '' );

// The generation ceiling is clamped like the other counts; its floor is 1 because 0 would refuse
// every institution its own template.
$saved = WPCPM_Settings::save( array( 'agreement_generations_per_day' => '250' ) );
ck( 'agreement_generations_per_day is capped at 100', $saved['agreement_generations_per_day'], 100 );
$saved = WPCPM_Settings::save( array( 'agreement_generations_per_day' => '0' ) );
ck( 'and floored at 1', $saved['agreement_generations_per_day'], 1 );
$saved = WPCPM_Settings::save( array( 'agreement_generations_per_day' => '10' ) );
ck( 'and the default of ten is inside the range', $saved['agreement_generations_per_day'], WPCPM_Settings::defaults()['agreement_generations_per_day'] );

// The save handler reads every rendered checkbox unconditionally and skips the ones the
// form does not render yet; the two lists must agree with the form itself, or a switch is
// either silently turned off on every save or silently never saved.
preg_match( '/const UNRENDERED_SWITCHES = array\((.*?)\);/', $admin, $m );
preg_match_all( "/'([a-z_]+)'/", isset( $m[1] ) ? $m[1] : '', $listed );
preg_match_all( '/name="([a-z_]+)"/', $admin, $rendered );
$switches = array_keys( array_filter( WPCPM_Settings::defaults(), 'is_bool' ) );

// **The list being empty is a fine answer, and used not to be.** This asserted it was not
// empty, on the reasoning that a switch was always going to be waiting for its screen. Then
// the import's screen shipped, the last entry came out, and the assertion failed for the
// state everybody wants: every switch has a box. What actually matters is the pairing below,
// and a switch that had no box and no entry would still be caught by it.
ck( 'the declared list is a list, whether or not anything is in it', is_array( $listed[1] ), true );
ck( 'every switch is either rendered by the form or declared unrendered',
    array_values( array_diff( $switches, $rendered[1], $listed[1] ) ), array() );
ck( 'and none is both', array_values( array_intersect( $rendered[1], $listed[1] ) ), array() );

$keep = $GLOBALS['opts'];
$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'student_statuses' => array() ) );
WPCPM_Settings::maybe_upgrade();
ck( 'a saved but empty status list is given every status a version has added', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], array( 'Paused', 'Pending graduation', 'Designer Track' ) );
$GLOBALS['opts'] = $keep;

// The stage list goes through the same loop as the status lists.
$saved = WPCPM_Settings::save( array( 'institution_active_stages' => " Confirmed \n\nConfirmed\n<b>Student</b>\n" ) );
ck( 'institution_active_stages is split, trimmed, stripped and de-duplicated',
    array( $saved['institution_active_stages'] ), array( array( 'Confirmed', 'Student' ) ) );

$saved = WPCPM_Settings::save( array( 'institution_on_inactive' => 'delete' ) );
ck( 'institution_on_inactive never becomes anything but keep or revoke', array( $saved['institution_on_inactive'] ), array( 'revoke' ) );

/* ---- the two statuses reach a saved list --------------------------------- */

echo "\n=== The statuses a new version adds reach a saved list ===\n";

// Both syncs build their Airtable formula from the saved list, so a site that saved before
// a status existed fetches none of its students while every line of code looks right.
$three = array( 'In Sensei', 'In Sensei 50h', 'Developer Track' );
// The default, in the order `defaults()` lists it: the four tracks, then the two states.
$all   = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' );
// What a version-0 site's list of three grows into. The appends land at the end, version by
// version, so this is the default's members in the order the upgrade added them.
$five  = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation', 'Designer Track' );

$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array(
		'student_statuses' => $three,
		'base_id'          => 'appSAVED0000000001',
	),
);

WPCPM_Settings::maybe_upgrade();

ck( 'every missing status is appended to a saved list of three, oldest version first', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], $five );
ck( 'the rest of the option is untouched', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['base_id'], 'appSAVED0000000001' );
ck( 'and the version is stamped', get_option( WPCPM_Settings::OPT_VERSION ), WPCPM_Settings::SETTINGS_VERSION );

$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => array( 'In Sensei', 'Pending graduation' ) ),
);

WPCPM_Settings::maybe_upgrade();

ck( 'only the missing ones are appended, after what was there',
    $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], array( 'In Sensei', 'Pending graduation', 'Paused', 'Designer Track' ) );

// Already has them: nothing is written to the option at all.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => $five ),
);
$before = $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ];

WPCPM_Settings::maybe_upgrade();

ck( 'a list that already has them is left alone', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ], $before );
ck( 'but the version is still stamped', get_option( WPCPM_Settings::OPT_VERSION ), WPCPM_Settings::SETTINGS_VERSION );

// Idempotent: a second run on an upgraded site changes nothing.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => $three ),
);
WPCPM_Settings::maybe_upgrade();
$after_once = $GLOBALS['opts'];
WPCPM_Settings::maybe_upgrade();
ck( 'a second run changes nothing', $GLOBALS['opts'], $after_once );

// A site that has never saved has no option to migrate: the new default already holds both.
$GLOBALS['opts'] = array();

WPCPM_Settings::maybe_upgrade();

ck( 'with no saved option, the version is stamped', get_option( WPCPM_Settings::OPT_VERSION ), WPCPM_Settings::SETTINGS_VERSION );
ck( 'and no option is invented', array_key_exists( WPCPM_Settings::OPT_NAME, $GLOBALS['opts'] ), false );
ck( 'the default carries all six', WPCPM_Settings::get_value( 'student_statuses' ), $all );

// A saved option from before the list existed at all inherits the default rather than
// getting a two-item list written over it.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'base_id' => 'appSAVED0000000002' ),
);

WPCPM_Settings::maybe_upgrade();

ck( 'a saved option with no list is not given one', array_key_exists( 'student_statuses', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] ), false );
ck( 'so it reads the default', WPCPM_Settings::get_value( 'student_statuses' ), $all );

// The point of the version key: a manager who removes a status on purpose must not find it
// back after the next request.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => $three ),
);

WPCPM_Settings::maybe_upgrade();
WPCPM_Settings::save( array( 'student_statuses' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Pending graduation' ) ) );
WPCPM_Settings::maybe_upgrade();

ck( 'a status a manager removed stays removed',
    WPCPM_Settings::get_value( 'student_statuses' ), array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Pending graduation' ) );

/* ---- the Designer Track reaches a saved list, and only once -------------- */

// Version 3, added with the Designer Track. The rule this pins is the one the version key
// exists for: a status is appended by the version that introduced it and never again, so a
// manager who takes one out later keeps it out. `Developer Track` shipped before there was
// an upgrade path at all and is in no version's list, so it is the case that proves the
// difference: the same run that appends `Designer Track` must leave it removed.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' ) ),
	WPCPM_Settings::OPT_VERSION => 2,
);

WPCPM_Settings::maybe_upgrade();

ck( 'a version-2 list gains the Designer Track and nothing else',
    $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'],
    array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation', 'Designer Track' ) );
ck( 'and the version moves to 3', get_option( WPCPM_Settings::OPT_VERSION ), 3 );
ck( 'which is what the class calls current', WPCPM_Settings::SETTINGS_VERSION, 3 );

// A manager who deliberately removed the Developer Track: version 2 is stamped, so the
// version-2 appends are done, and the version-3 append must not smuggle the old one back.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => array( 'In Sensei', 'In Sensei 50h', 'Paused', 'Pending graduation' ) ),
	WPCPM_Settings::OPT_VERSION => 2,
);

WPCPM_Settings::maybe_upgrade();

ck( 'a status a manager removed before this version is not put back by it',
    $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'],
    array( 'In Sensei', 'In Sensei 50h', 'Paused', 'Pending graduation', 'Designer Track' ) );

// Already has it: no write at all, and a second run changes nothing.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME    => array( 'student_statuses' => $all, 'base_id' => 'appSAVED0000000003' ),
	WPCPM_Settings::OPT_VERSION => 2,
);
$before = $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ];

WPCPM_Settings::maybe_upgrade();

ck( 'a list that already names the Designer Track is untouched', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ], $before );

$after_once = $GLOBALS['opts'];
WPCPM_Settings::maybe_upgrade();
ck( 'and a second run at the current version changes nothing', $GLOBALS['opts'], $after_once );

// A manager who removes it *after* this version has been stamped keeps it removed, which is
// the same rule one version on.
WPCPM_Settings::save( array( 'student_statuses' => array( 'In Sensei', 'Designer Track' ) ) );
WPCPM_Settings::save( array( 'student_statuses' => array( 'In Sensei' ) ) );
WPCPM_Settings::maybe_upgrade();
ck( 'a Designer Track a manager removes after the upgrade stays removed',
    WPCPM_Settings::get_value( 'student_statuses' ), array( 'In Sensei' ) );

// And a fresh save on a site that never ran the upgrade stamps the version itself, so the
// upgrade cannot arrive later and put back what that save left out.
$GLOBALS['opts'] = array();

WPCPM_Settings::save( array( 'student_statuses' => array( 'In Sensei' ) ) );
ck( 'save() stamps the version', get_option( WPCPM_Settings::OPT_VERSION ), WPCPM_Settings::SETTINGS_VERSION );

WPCPM_Settings::maybe_upgrade();
ck( 'so an upgrade after a save appends nothing', WPCPM_Settings::get_value( 'student_statuses' ), array( 'In Sensei' ) );

// The address of the agreement wording is rendered as a link on a manager's screen, so it is
// held to https and to Google's own hosts rather than taken as typed.
foreach ( array(
	'http://docs.google.com/document/d/x'   => '',
	'https://evil.example/document/d/x'     => '',
	'javascript:alert(1)'                   => '',
	'not a url at all'                      => '',
	'https://drive.google.com/drive/f/abc'  => 'https://drive.google.com/drive/f/abc',
) as $typed => $want ) {
	$saved = WPCPM_Settings::save( array( 'agreement_doc_url' => $typed ) );
	ck( sprintf( 'agreement_doc_url refuses or keeps %s', $typed ), $saved['agreement_doc_url'], $want );
}

/* ---- the Institutions module has a section of its own --------------------- */

// It used to be one row at the foot of the Students card, under a heading that said Students
// module, which is where a program manager would never look for it. The other four had no
// control at all and could only be changed in code.
$admin = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-wpcpm-admin.php' );
$card  = substr( $admin, (int) strpos( $admin, 'private function render_institution_settings' ) );
$card  = substr( $card, 0, (int) strpos( $card, "\n\t}\n" ) );

ck( 'the Institutions card exists and is named for the module', false !== strpos( $card, "'Institutions module'" ), true );

foreach ( array( 'applications_enabled', 'institution_home', 'institution_provision', 'institution_on_inactive', 'agreement_review_days', 'agreement_notify', 'agreement_doc_url' ) as $name ) {
	ck( sprintf( '%s has a control on it', $name ), false !== strpos( $card, 'name="' . $name . '"' ), true );
}

$students = substr( $admin, (int) strpos( $admin, "'Students module'" ) );
$students = substr( $students, 0, (int) strpos( $students, 'render_institution_settings' ) );

ck( 'and the application form is no longer filed under Students', false !== strpos( $students, 'applications_enabled' ), false );

// A rendered checkbox is read unconditionally on save, so the two that moved must be off the
// unrendered list or the first save of this screen would switch them both off.
// The list is empty today, so the pattern has to allow `array()` with nothing between the
// parentheses: written to expect a value, it matched nothing and the two reads below were of
// an array key that was not there.
$found = preg_match( '/const UNRENDERED_SWITCHES = array\((.*?)\);/s', $admin, $unrendered );

ck( 'the unrendered list is where the check looks for it', $found, 1 );

ck( 'the two switches that gained a control are off the unrendered list', array(
	false !== strpos( $unrendered[1], "'institution_home'" ),
	false !== strpos( $unrendered[1], "'institution_provision'" ),
), array( false, false ) );


echo "\n=== Three fields that must never be blank ===\n";

// formula_in() turns an empty list into no filter at all, so a blank status setting does not
// fetch nobody, it fetches every row of the table: an account and a role for every SPAM and
// rejected row, or, for the current-student list alone, the revocation of every current student.
// The default goes back in and the screen is told.
$GLOBALS['flash'] = array();
$blank = WPCPM_Settings::save( array( 'mentor_status' => '', 'student_statuses' => '', 'institution_active_stages' => '' ) );
$saved = WPCPM_Settings::get();
ck( 'a blank mentor status is put back to the default', $saved['mentor_status'], WPCPM_Settings::defaults()['mentor_status'] );
ck( 'so is a blank current-student list', $saved['student_statuses'], WPCPM_Settings::defaults()['student_statuses'] );
ck( 'and the blank institution stages', $saved['institution_active_stages'], WPCPM_Settings::defaults()['institution_active_stages'] );
// Asserted by reading the source: save() queues the restored fields, and the notice reads the same
// channel with the labels. (The user-meta stand-ins keep what they are given since BUILDER-3,
// whose section below reads a refusal back from the raw meta.)
$settings_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php' );
ck( 'save() tells the screen which fields it restored', false !== strpos( $settings_src, "WPCPM_Flash::set( 'settings-defaults', \$restored );" ), true );
ck( 'and the notice reads that channel', false !== strpos( $settings_src, "WPCPM_Flash::take( 'settings-defaults' )" ), true );
ck( 'the labels the notice prints are the three fields\' own', array_keys( WPCPM_Settings::never_blank() ), array( 'mentor_status', 'student_statuses', 'institution_active_stages' ) );
$filled = WPCPM_Settings::save( array( 'mentor_status' => 'Active' ) );
ck( 'a filled field is saved as given', WPCPM_Settings::get()['mentor_status'], 'Active' );

echo "\n=== Semester report settings ===\n";

$defaults = WPCPM_Settings::defaults();
ck( 'the three report settings and their defaults', array( $defaults['report_autodraft'], $defaults['report_autodraft_grace_days'], $defaults['report_notify'] ), array( true, 45, '' ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = WPCPM_Settings::defaults();
$saved = WPCPM_Settings::save( array( 'report_autodraft_grace_days' => '2', 'report_notify' => "one@example.org, junk\nmaciej@a8c.com" ) );
ck( 'the grace has a floor of a week', $saved['report_autodraft_grace_days'], 7 );
ck( 'the addresses are cleaned like the agreement ones', $saved['report_notify'], 'one@example.org,maciej@a8c.com' );
ck( 'a save that does not carry the switch leaves it on', $saved['report_autodraft'], true );
$saved = WPCPM_Settings::save( array( 'report_autodraft' => '', 'report_autodraft_grace_days' => '900' ) );
ck( 'the switch carried empty is off', $saved['report_autodraft'], false );
ck( 'and the grace has a ceiling of a year', $saved['report_autodraft_grace_days'], 365 );

echo "\n=== Sponsors module settings ===\n";

// The fifth audience's settings (design spec of 4 September 2026, section 5).
ck( 'the Team Members table has its own setting: teams_table is the Contribution areas table', WPCPM_Settings::defaults()['team_members_table'], 'tblUYWUSEcRLJ5BaR' );
ck( 'sponsors are routed home at login by default, like everybody', WPCPM_Settings::defaults()['sponsor_home'], true );
ck( 'a sponsor that stops being Approved keeps its accounts unless told otherwise', WPCPM_Settings::defaults()['sponsor_on_inactive'], 'keep' );
ck( 'interest mail falls back to every manager', WPCPM_Settings::defaults()['sponsor_notify'], '' );
ck( 'a logo may be a megabyte', WPCPM_Settings::defaults()['logo_max_kb'], 1024 );

$saved = WPCPM_Settings::save( array( 'sponsor_on_inactive' => 'anything', 'logo_max_kb' => '999999', 'sponsor_notify' => ' maciej@a8c.com ', 'team_members_table' => 'tblX' ) );
ck( 'an unknown on-inactive answer reads as keep', $saved['sponsor_on_inactive'], 'keep' );
ck( 'and revoke is the one other answer', WPCPM_Settings::save( array( 'sponsor_on_inactive' => 'revoke' ) )['sponsor_on_inactive'], 'revoke' );
ck( 'the logo ceiling is clamped', $saved['logo_max_kb'], 8192 );
ck( 'the notify list is trimmed', $saved['sponsor_notify'], 'maciej@a8c.com' );
ck( 'and cleaned like its siblings: split, lowered, the non-addresses dropped, duplicates gone',
	WPCPM_Settings::save( array( 'sponsor_notify' => 'Maciej@a8c.com, not-an-address maciej@a8c.com' ) )['sponsor_notify'],
	'maciej@a8c.com' );
ck( 'the table ID is saved as text', $saved['team_members_table'], 'tblX' );

echo "\n=== S2: the tools switches and the low-stock default ===\n";
$defaults = WPCPM_Settings::defaults();
ck( 'students see the Tools section by default, mentors do not', array( $defaults['tools_students'], $defaults['tools_mentors'] ), array( true, false ) );
ck( 'the low-stock threshold defaults to ten codes', $defaults['offer_low_stock'], 10 );

$saved = WPCPM_Settings::save( array( 'offer_low_stock' => '0' ) );
ck( 'a low-stock threshold of zero is floored at one', $saved['offer_low_stock'], 1 );
$saved = WPCPM_Settings::save( array( 'offer_low_stock' => '5000' ) );
ck( 'and five thousand is capped at a thousand', $saved['offer_low_stock'], 1000 );
$saved = WPCPM_Settings::save( array( 'offer_low_stock' => '19' ) );
ck( 'while an ordinary value is kept as given', $saved['offer_low_stock'], 19 );

// tools_students and tools_mentors join the same guarded flags list sponsor_home is in
// (see save()): absent from an input means "leave it as it was", not "off". A checkbox
// already off - set here explicitly, since a fresh run starts every flag at its default
// of on - stays off when a later save does not mention it, exactly like sponsor_home.
WPCPM_Settings::save( array( 'tools_students' => '' ) );
$saved = WPCPM_Settings::save( array( 'tools_mentors' => '1' ) );
ck( 'tools_students stays off when a save omits it, like sponsor_home', $saved['tools_students'], false );
ck( 'tools_mentors carried as 1 reads as on', $saved['tools_mentors'], true );

echo "\n=== A published track's status joins the list, and nothing leaves it (1.101.0) ===\n";

$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'student_statuses' => array( 'In Sensei', 'Paused' ) ) );
ck( 'a status the list lacks is appended', array( WPCPM_Settings::add_student_status( 'Marketing Track' ), $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] ), array( true, array( 'In Sensei', 'Paused', 'Marketing Track' ) ) );
ck( 'a status it holds is not added twice, and nothing is taken away', array( WPCPM_Settings::add_student_status( 'Marketing Track' ), $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] ), array( false, array( 'In Sensei', 'Paused', 'Marketing Track' ) ) );
ck( 'trimmed like every status, and nothing for an empty one', array( WPCPM_Settings::add_student_status( ' Writing Track ' ), WPCPM_Settings::add_student_status( '  ' ), end( $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] ) ), array( true, false, 'Writing Track' ) );
$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'api_token' => 'kept' ) );
WPCPM_Settings::add_student_status( 'Marketing Track' );
ck( 'a saved option with no list starts from the default one, and keeps its other settings', array( $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['api_token'] ), array( array_merge( WPCPM_Settings::defaults()['student_statuses'], array( 'Marketing Track' ) ), 'kept' ) );

echo "\n=== The Student Duplicate Finder's switch (1.102.0) ===\n";

// Off until a manager turns it on, the way the import's switch shipped: a delete removes rows from
// the shared base, so the finder ships scanning and listing and deleting nothing (spec decision
// 3.10). The generic checks above already hold that the box is rendered and that it can be flipped.
ck( 'deleting duplicates is off until a manager turns it on', isset( WPCPM_Settings::defaults()['duplicate_delete_enabled'] ) ? WPCPM_Settings::defaults()['duplicate_delete_enabled'] : null, false );
ck( 'and the switch is a checkbox of its own on the settings screen', false !== strpos( $admin, 'name="duplicate_delete_enabled"' ), true );

echo "\n=== Saving the settings compiles the tracks again ===\n";

// "Currently mentoring" and the past statuses are rules every published track was compiled
// against, so a save that changed them left the live site running the last compile's answer until
// something unrelated published (the design's decision 14).
class WPCPM_Track_Store {
	public static $compiled = 0;

	public static function compile() {
		++self::$compiled;

		return array();
	}
}

$compiled_before = WPCPM_Track_Store::$compiled;
WPCPM_Settings::save( array( 'student_statuses' => array( 'In Sensei' ) ) );

// The recompile hangs on the settings screen's own handler, not on `save()`: `save()` has callers
// of its own, and one of them is the handbook's model migration on `init` priority 5, before the
// track post type is registered at 10 (the T2b whole-branch review). Asserted by reading both
// sources, because what is being pinned is which of the two carries the call.
ck( 'saving through the sanitiser alone compiles nothing', WPCPM_Track_Store::$compiled - $compiled_before, 0 );

$settings_source = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-settings.php' );

ck( 'and the sanitiser carries no compile at all', false !== strpos( $settings_source, 'WPCPM_Track_Store::compile()' ), false );

ck( 'the settings screen handler compiles after it saves, which is what decision 14 asks for',
    array(
        false !== strpos( $admin, 'WPCPM_Settings::save( $input );' ),
        false !== strpos( $admin, 'WPCPM_Track_Store::compile();' ),
        strpos( $admin, 'WPCPM_Settings::save( $input );' ) < strpos( $admin, 'WPCPM_Track_Store::compile();' ),
    ),
    array( true, true, true ) );

echo "\n=== A Settings page drawn before a publish keeps the published track's status (BUILDER-3) ===\n";

// The deep check of 1.109.1, BUILDER-3: the form posted "Currently mentoring" whole from its
// textarea, so a Settings tab drawn before a track was published and saved after it wrote the old
// list back, and the next students sync took the Student role from everybody on the track. The
// form now carries the list as it drew it, and a save that would take a live track's status out
// is refused, naming the track (the design's 7.5). Pressed through the real handler.

/** The compiled tracks, stood in for the one thing the save asks of them: which run from their definitions. */
class WPCPM_Tracks {
	public static $live = array();

	public static function live() {
		return self::$live;
	}
}

/**
 * Press Save on the Settings screen the way a page drew it: the textarea as typed, the list the
 * page drew beside it, every switch as it was drawn, and any other field given.
 *
 * @param string[] $drawn The list the page drew.
 * @param string   $typed What the textarea holds when Save is pressed.
 * @param array    $extra Other fields, by name.
 * @param bool     $carry Whether the page carried the drawn list; a page drawn before 1.110.0 did not.
 * @return string How the handler ended.
 */
function press_settings( array $drawn, $typed, array $extra = array(), $carry = true ) {
	$settings = WPCPM_Settings::get();
	$_POST    = array_merge(
		array(
			WPCPM_Admin::SETTINGS_NONCE => 'x',
			'student_statuses'          => $typed,
		),
		$extra
	);

	if ( $carry ) {
		$_POST[ WPCPM_Settings::FIELD_DRAWN_STATUSES ] = $drawn;
	}

	foreach ( WPCPM_Settings::defaults() as $key => $default ) {
		if ( is_bool( $default ) && ! empty( $settings[ $key ] ) ) {
			$_POST[ $key ] = '1';
		}
	}

	$ended = 'no outcome';

	try {
		( new WPCPM_Admin() )->handle_settings_save();
	} catch ( Exception $e ) {
		$ended = $e->getMessage();
	}

	$_POST = array();

	return $ended;
}

$GLOBALS['opts']  = array(
	WPCPM_Settings::OPT_NAME    => WPCPM_Settings::defaults(),
	WPCPM_Settings::OPT_VERSION => WPCPM_Settings::SETTINGS_VERSION,
);
$GLOBALS['umeta'] = array();
$six              = WPCPM_Settings::get()['student_statuses'];

// Tab A draws the Settings screen; tab B publishes a track of somebody's own, which appends its
// status; tab A is saved for something else.
WPCPM_Settings::add_student_status( 'Mentor Track' );
WPCPM_Tracks::$live = array( 'Mentor Track' => array( 'key' => 'mentor', 'label' => 'Mentor Track', 'source' => 'definition', 'post' => 41 ) );
$seven              = array_merge( $six, array( 'Mentor Track' ) );

ck( 'a page drawn before a track was published, saved after it for something else, keeps the track\'s status and saves the rest',
    array( press_settings( $six, implode( "\n", $six ), array( 'mentor_status' => 'Active, changed' ) ), WPCPM_Settings::get()['student_statuses'], WPCPM_Settings::get()['mentor_status'] ),
    array( 'redirect', $seven, 'Active, changed' ) );

ck( 'and the same page with the list changed keeps the change, with the status gained since it was drawn put after it',
    array( press_settings( $six, "In Sensei\nIn Sensei 50h\nDeveloper Track\nDesigner Track\nPending graduation" ), WPCPM_Settings::get()['student_statuses'] ),
    array( 'redirect', array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Pending graduation', 'Mentor Track' ) ) );

$compiled_then  = WPCPM_Track_Store::$compiled;
$drawn_now      = WPCPM_Settings::get()['student_statuses'];
$without_mentor = implode( "\n", array_diff( $drawn_now, array( 'Mentor Track' ) ) );
$kept_presses   = array();

// The ruling of BUILDER-3's fix round, 23 September 2026: such a save is not refused whole. Everything
// else it posts is saved and compiled as ever, Currently mentoring stays as stored, and the screen
// gets both notices: saved, and why the list was left as it was. Each press here changes the mentor
// status too, so each shows the rest was saved.
foreach ( array(
	'taken out on a page drawn now' => array( $drawn_now, $without_mentor, true ),
	'on a page from before 1.110.0' => array( array(), $without_mentor, false ),
	'blanked'                       => array( $drawn_now, '', true ),
) as $case => $page ) {
	$GLOBALS['umeta']      = array();
	$ended                 = press_settings( $page[0], $page[1], array( 'mentor_status' => 'Changed, ' . $case ), $page[2] );
	$queued                = $GLOBALS['umeta'][1][ WPCPM_Flash::META ] ?? array();
	$kept_presses[ $case ] = array( $ended, $queued['settings'] ?? null, $queued['settings-refused'] ?? null, $queued['settings-defaults'] ?? null, WPCPM_Settings::get()['mentor_status'], WPCPM_Settings::get()['student_statuses'] );
}

$kept_by = array( 'Mentor Track' => 'Mentor Track' );

ck( 'a save whose Currently mentoring change would take a live track\'s status out saves everything else and compiles, and leaves the list as stored, however the page came to lack the status, with both notices and the track named',
    array( $kept_presses, WPCPM_Track_Store::$compiled - $compiled_then ),
    array(
        array(
            'taken out on a page drawn now' => array( 'redirect', 'saved', $kept_by, null, 'Changed, taken out on a page drawn now', $drawn_now ),
            'on a page from before 1.110.0' => array( 'redirect', 'saved', $kept_by, null, 'Changed, on a page from before 1.110.0', $drawn_now ),
            'blanked'                       => array( 'redirect', 'saved', $kept_by, null, 'Changed, blanked', $drawn_now ),
        ),
        3,
    ) );

// A live track whose status the list already lacks is not the save's doing: an unrelated change to
// the list saves, and the Track Builder's list flags the track, which is where the ruling puts that
// state. Only a save that would take a status out is refused.
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] = array( 'In Sensei', 'Paused' );
WPCPM_Tracks::$live['Writing Track'] = array( 'key' => 'writing', 'label' => 'Writing Track', 'source' => 'definition', 'post' => 42 );

ck( 'a live status the stored list already lacks does not refuse an unrelated change to the list',
    array( press_settings( array( 'In Sensei', 'Paused' ), 'In Sensei' ), WPCPM_Settings::get()['student_statuses'] ),
    array( 'redirect', array( 'In Sensei' ) ) );

unset( WPCPM_Tracks::$live['Writing Track'] );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] = $drawn_now;

// Once the track is off the live site, taking its status out is the manager's decision (7.5).
WPCPM_Tracks::$live = array();

ck( 'and once the track is off the live site, its status may leave the list',
    array( press_settings( $drawn_now, $without_mentor ), in_array( 'Mentor Track', WPCPM_Settings::get()['student_statuses'], true ) ),
    array( 'redirect', false ) );

// A blanked textarea on a page drawn before the list gained a status (the fix round of BUILDER-3):
// the gained status was merged into the blank and written alone, past the never-blank rule and
// its notice, and with live tracks the refusal named every track but the one the blank would
// drop. A blank is a blank however stale the page: the defaults go back, and the notice says so.
$GLOBALS['opts']    = array(
	WPCPM_Settings::OPT_NAME    => WPCPM_Settings::defaults(),
	WPCPM_Settings::OPT_VERSION => WPCPM_Settings::SETTINGS_VERSION,
);
$GLOBALS['umeta']   = array();
WPCPM_Tracks::$live = array();
WPCPM_Settings::add_student_status( 'Mentor Track' );

ck( 'a blank on a page drawn before the list gained a status is saved as the default list, with the notice that says so',
    array( press_settings( $six, '' ), WPCPM_Settings::get()['student_statuses'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-defaults'] ?? null ),
    array( 'redirect', $six, array( 'student_statuses' ) ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] = $seven;
$GLOBALS['umeta'] = array();
WPCPM_Tracks::$live = array(
	'In Sensei'       => array( 'key' => '150h', 'label' => 'WordPress Credits Program 150h', 'source' => 'definition', 'post' => 11 ),
	'In Sensei 50h'   => array( 'key' => '50h', 'label' => 'WordPress Credits Program 50h', 'source' => 'definition', 'post' => 12 ),
	'Developer Track' => array( 'key' => 'dev', 'label' => 'Developer Track', 'source' => 'definition', 'post' => 13 ),
	'Designer Track'  => array( 'key' => 'design', 'label' => 'Designer Track', 'source' => 'definition', 'post' => 14 ),
	'Mentor Track'    => array( 'key' => 'mentor', 'label' => 'Mentor Track', 'source' => 'definition', 'post' => 41 ),
);
press_settings( $six, '' );

ck( 'and with every track live, the blank is held against the default list, so only the track it would drop is named',
    array( $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-refused'] ?? null, WPCPM_Settings::get()['student_statuses'] ),
    array( array( 'Mentor Track' => 'Mentor Track' ), $seven ) );

WPCPM_Tracks::$live = array();

// The other half of the rule: Currently mentoring is written only when the person changed the
// textarea (the fix round of BUILDER-3, which found nothing pinning it). Another administrator took
// Paused out after this page was drawn, and this page is saved for something else with the textarea
// as it was drawn: the list stays as stored, Paused out, rather than the drawn list written back.
$GLOBALS['opts']  = array(
	WPCPM_Settings::OPT_NAME    => WPCPM_Settings::defaults(),
	WPCPM_Settings::OPT_VERSION => WPCPM_Settings::SETTINGS_VERSION,
);
$GLOBALS['umeta'] = array();
$without_paused   = array_values( array_diff( $six, array( 'Paused' ) ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] = $without_paused;

ck( 'a textarea nobody changed leaves the list as stored, so a status another administrator took out since the page was drawn stays out',
    array( press_settings( $six, implode( "\n", $six ), array( 'mentor_status' => 'Active, again' ) ), WPCPM_Settings::get()['student_statuses'], WPCPM_Settings::get()['mentor_status'] ),
    array( 'redirect', $without_paused, 'Active, again' ) );

// A live status as its track holds it may not be what the list holds: publishing appends it trimmed
// alone, and the list is sanitized when it is read, which folds a run of spaces into one and keeps a
// "<" that opens no tag as `&lt;`. Compared raw, such a status was never found in the list, and
// taking it out slipped past the guard (the fix round of BUILDER-3). Both are judged sanitized now.
$GLOBALS['opts']  = array(
	WPCPM_Settings::OPT_NAME    => WPCPM_Settings::defaults(),
	WPCPM_Settings::OPT_VERSION => WPCPM_Settings::SETTINGS_VERSION,
);
$GLOBALS['umeta'] = array();
WPCPM_Settings::add_student_status( 'Mentor  Track' );
WPCPM_Settings::add_student_status( 'Less < More' );
WPCPM_Tracks::$live = array(
	'Mentor  Track' => array( 'key' => 'mentor', 'label' => 'Mentor Track', 'source' => 'definition', 'post' => 41 ),
	'Less < More'   => array( 'key' => 'less', 'label' => 'Less or More Track', 'source' => 'definition', 'post' => 43 ),
);
$drawn_odd = WPCPM_Settings::get()['student_statuses'];

press_settings( $drawn_odd, implode( "\n", $six ) );

ck( 'a live status holding a run of spaces or a "<" is judged as the list keeps it, so a save taking it out is caught too',
    array( $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-refused'] ?? null, WPCPM_Settings::get()['student_statuses'] ),
    array( array( 'Mentor  Track' => 'Mentor Track', 'Less < More' => 'Less or More Track' ), $drawn_odd ) );

WPCPM_Tracks::$live = array();

$GLOBALS['umeta'] = array();

echo "\n=== A live track's status is kept out of \"Past students\" ===\n";

// A past status is refused to every track (`status_refused`), and a Settings save compiles
// (decision 14), so a save putting a live track's status among the past statuses took the track off
// the live site with nothing standing in: its students lost its form, and with the 150-hour track
// every student on no track lost theirs too (decision 34). The twin of BUILDER-3's rule: the list
// stays as stored, everything else is saved and compiled, and a notice names each track. Pressed
// through the real handler.
$GLOBALS['opts']    = array(
	WPCPM_Settings::OPT_NAME    => WPCPM_Settings::defaults(),
	WPCPM_Settings::OPT_VERSION => WPCPM_Settings::SETTINGS_VERSION,
);
$GLOBALS['umeta']   = array();
WPCPM_Tracks::$live = array(
	'In Sensei'       => array( 'key' => '150h', 'label' => 'WordPress Credits Program 150h', 'post' => 11 ),
	'In Sensei 50h'   => array( 'key' => '50h', 'label' => 'WordPress Credits Program 50h', 'post' => 12 ),
	'Developer Track' => array( 'key' => 'dev', 'label' => 'Developer Track', 'post' => 13 ),
	'Designer Track'  => array( 'key' => 'design', 'label' => 'Designer Track', 'post' => 14 ),
	'Mentor Track'    => array( 'key' => 'mentor', 'label' => 'Mentor Track', 'post' => 41 ),
);
$past_stored   = WPCPM_Settings::get()['past_statuses'];
$compiled_then = WPCPM_Track_Store::$compiled;
$ended         = press_settings( $six, implode( "\n", $six ), array( 'past_statuses' => "Graduate\nDropped out\nIn Sensei 50h\nMentor Track", 'mentor_status' => 'Active, past' ) );
$queued        = $GLOBALS['umeta'][1][ WPCPM_Flash::META ] ?? array();

ck( 'a save putting live tracks\' statuses among the past statuses saves everything else and compiles, leaves the list as stored, and names each track, one of the four original tracks included',
    array( $ended, $queued['settings'] ?? null, $queued['settings-past-refused'] ?? null, WPCPM_Settings::get()['past_statuses'], WPCPM_Settings::get()['mentor_status'], WPCPM_Track_Store::$compiled - $compiled_then ),
    array( 'redirect', 'saved', array( 'In Sensei 50h' => 'WordPress Credits Program 50h', 'Mentor Track' => 'Mentor Track' ), $past_stored, 'Active, past', 1 ) );

// The rule compares folded, case and runs of spaces aside, so a past status typed in lower case
// took the track off as surely as one typed as the track holds it.
$GLOBALS['umeta'] = array();
press_settings( $six, implode( "\n", $six ), array( 'past_statuses' => "Graduate\nDropped out\n  in  sensei  " ) );

ck( 'a past status is judged as the track rules judge it, so "in  sensei" in lower case is caught as the 150-hour track\'s status',
    array( $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-past-refused'] ?? null, WPCPM_Settings::get()['past_statuses'] ),
    array( array( 'In Sensei' => 'WordPress Credits Program 150h' ), $past_stored ) );

$GLOBALS['umeta'] = array();

ck( 'a past status no live track holds is saved as ever, with no notice',
    array( press_settings( $six, implode( "\n", $six ), array( 'past_statuses' => "Graduate\nDropped out\nWithdrawn" ) ), WPCPM_Settings::get()['past_statuses'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-past-refused'] ?? null ),
    array( 'redirect', array( 'Graduate', 'Dropped out', 'Withdrawn' ), null ) );

// A live status the stored list already holds, which only a hand edit can leave there since Publish
// refuses a past status, is not the save's doing: keeping the list would not keep the track, which
// the Track Builder's list shows as left out. As BUILDER-3's rule treats a status Currently
// mentoring already lacks, an unrelated change to the list saves.
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['past_statuses'] = array( 'Graduate', 'Mentor Track' );
$GLOBALS['umeta']                                             = array();

ck( 'a live status the stored list already holds does not refuse an unrelated change to the list',
    array( press_settings( $six, implode( "\n", $six ), array( 'past_statuses' => "Graduate\nMentor Track\nDropped out" ) ), WPCPM_Settings::get()['past_statuses'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-past-refused'] ?? null ),
    array( 'redirect', array( 'Graduate', 'Mentor Track', 'Dropped out' ), null ) );

// Once a track is off the live site, its status may become a past status, as it may then leave
// Currently mentoring (7.5).
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['past_statuses'] = $past_stored;
$GLOBALS['umeta']                                             = array();
unset( WPCPM_Tracks::$live['Mentor Track'] );

ck( 'and once the track is off the live site, its status may join the list',
    array( press_settings( $six, implode( "\n", $six ), array( 'past_statuses' => "Graduate\nDropped out\nMentor Track" ) ), WPCPM_Settings::get()['past_statuses'], $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-past-refused'] ?? null ),
    array( 'redirect', array( 'Graduate', 'Dropped out', 'Mentor Track' ), null ) );

WPCPM_Tracks::$live = array();

$GLOBALS['umeta'] = array();

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );

exit( $fail ? 1 : 0 );
