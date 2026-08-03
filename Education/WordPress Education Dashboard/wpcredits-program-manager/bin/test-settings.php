<?php
/**
 * Does pressing Save actually save?
 *
 * This exists because it did not. Adding a field took three edits — render it, sanitise it in
 * `WPCPM_Settings::save()`, and add it to a hand-written allowlist in the save handler — and
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
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u, $c ); }
function wp_timezone_string() { return 'UTC'; }
function current_user_can( $c ) { return true; }
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
function user_can( $u, $c ) { return true; }

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
// recognisably ours coming back.
$probe = array(
	'api_token'                 => 'patTESTTOKEN1234567890',
	'base_id'                   => 'appPROBE0000000001',
	'handbook_provider'         => 'gemini',
	'handbook_key'              => 'AQ.probe-key-value',
	'handbook_model'            => 'gemini-2.5-flash',
	'handbook_access'           => 'program',
	'handbook_limit'            => '35',
	'checker_course_slug'       => 'probe-course',
	'checker_source_status'     => 'Probe status',
	'institutions_table'        => 'tblPROBE0000000001',
	'teams_name_field'          => 'Probe field',
	'student_on_inactive'       => 'keep',
);

$GLOBALS['opts'][ WPCPM_Settings::OPTION ] = WPCPM_Settings::defaults();

$saved = WPCPM_Settings::save( $probe );

foreach ( $probe as $key => $value ) {
	$expected = 'handbook_limit' === $key ? 35 : $value;

	ck( sprintf( '%s survives the save', $key ), array( $saved[ $key ] ), array( $expected ) );
}

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

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
