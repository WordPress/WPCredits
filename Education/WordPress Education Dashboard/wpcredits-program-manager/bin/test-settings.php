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
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
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
function get_user_meta( $id, $k, $s = false ) { return ''; }
function update_user_meta( $id, $k, $v ) { return true; }
function delete_user_meta( $id, $k ) { return true; }
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
ck( 'a saved but empty status list is given both statuses', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], array( 'Paused', 'Pending graduation' ) );
$GLOBALS['opts'] = $keep;

// The stage list goes through the same loop as the status lists.
$saved = WPCPM_Settings::save( array( 'institution_active_stages' => " Confirmed \n\nConfirmed\n<b>Student</b>\n" ) );
ck( 'institution_active_stages is split, trimmed, stripped and de-duplicated',
    array( $saved['institution_active_stages'] ), array( array( 'Confirmed', 'Student' ) ) );

$saved = WPCPM_Settings::save( array( 'institution_on_inactive' => 'delete' ) );
ck( 'institution_on_inactive never becomes anything but keep or revoke', array( $saved['institution_on_inactive'] ), array( 'revoke' ) );

/* ---- the two statuses reach a saved list --------------------------------- */

echo "\n=== Paused and Pending graduation reach a saved list ===\n";

// Both syncs build their Airtable formula from the saved list, so a site that saved before
// the two statuses existed fetches no Paused student while every line of code looks right.
$three = array( 'In Sensei', 'In Sensei 50h', 'Developer Track' );
$five  = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' );

$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array(
		'student_statuses' => $three,
		'base_id'          => 'appSAVED0000000001',
	),
);

WPCPM_Settings::maybe_upgrade();

ck( 'both are appended to a saved list of three, in order', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], $five );
ck( 'the rest of the option is untouched', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['base_id'], 'appSAVED0000000001' );
ck( 'and the version is stamped', get_option( WPCPM_Settings::OPT_VERSION ), WPCPM_Settings::SETTINGS_VERSION );

$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'student_statuses' => array( 'In Sensei', 'Pending graduation' ) ),
);

WPCPM_Settings::maybe_upgrade();

ck( 'only the missing one is appended, after what was there',
    $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], array( 'In Sensei', 'Pending graduation', 'Paused' ) );

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
ck( 'the default carries both', WPCPM_Settings::get_value( 'student_statuses' ), $five );

// A saved option from before the list existed at all inherits the default rather than
// getting a two-item list written over it.
$GLOBALS['opts'] = array(
	WPCPM_Settings::OPT_NAME => array( 'base_id' => 'appSAVED0000000002' ),
);

WPCPM_Settings::maybe_upgrade();

ck( 'a saved option with no list is not given one', array_key_exists( 'student_statuses', $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] ), false );
ck( 'so it reads the default', WPCPM_Settings::get_value( 'student_statuses' ), $five );

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
preg_match( '/const UNRENDERED_SWITCHES = array\( (.*?) \);/', $admin, $unrendered );

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
// This suite's user-meta stubs keep nothing, so the flash itself cannot be read back here; what
// is asserted is that save() queues it and the notice reads the same channel with the labels.
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

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );

exit( $fail ? 1 : 0 );
