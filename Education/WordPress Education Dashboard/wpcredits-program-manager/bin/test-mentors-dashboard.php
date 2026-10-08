<?php
/**
 * The Mentor Report Card page: the script it loads for the forms it draws.
 *
 * A mentor's note carries a Delete that asks a question first, and the question is
 * `assets/js/forms.js` reading `data-wpcpm-confirm`. The page draws a calendar whose script names
 * the forms script as a dependency, so the script reaches every page that draws a Delete, and
 * `render()` still enqueues it itself, above all of its early returns, and does not rely on the
 * calendar for it. This pins that house rule and not the question: if a later edit drops those
 * lines, the page stops loading the script on its own. The page is drawn here for the two visitors
 * who stop early, one logged out and one with no student list, because the enqueue sits above both
 * of those returns.
 *
 * Run from the plugin root:  php bin/test-mentors-dashboard.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );

$GLOBALS['opts']       = array();
$GLOBALS['umeta']      = array();
$GLOBALS['users']      = array();
$GLOBALS['uid']        = 0;
$GLOBALS['manage']     = array();
$GLOBALS['scripts']    = array();
$GLOBALS['registered'] = array();

class WP_Error {
	public function __construct( $c = '', $m = '' ) {}
	public function get_error_message() { return ''; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $roles = array();
	public function __construct( $id = 0, $name = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $u, $p = null ) { return (string) $u; }
function wp_kses( $s, $allowed ) { return (string) $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function shortcode_atts( $pairs, $atts, $shortcode = '' ) { return array_merge( $pairs, array_intersect_key( $atts, $pairs ) ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {}
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }
function current_user_can( $c ) { return in_array( $GLOBALS['uid'], $GLOBALS['manage'], true ); }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_post_status( $id ) { return false; }
function get_permalink( $id = 0 ) { return ''; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_login_url( $redirect = '' ) { return 'https://example.test/wp-login.php'; }
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }
function wp_create_nonce( $a = -1 ) { return 'nonce'; }
function wp_style_is( $h, $l = 'enqueued' ) { return false; }
function wp_register_style() {} function wp_enqueue_style() {}
function wp_localize_script() {}

// The script functions keep what they were asked, so the page can be read for what it loads.
function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	$GLOBALS['registered'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'footer' => $in_footer );
}
function wp_script_is( $handle, $list = 'enqueued' ) {
	return 'registered' === $list ? isset( $GLOBALS['registered'][ $handle ] ) : in_array( $handle, $GLOBALS['scripts'], true );
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	if ( '' !== $src ) {
		wp_register_script( $handle, $src, $deps, $ver, $in_footer );
	}

	$GLOBALS['scripts'][] = $handle;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/plugin/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-dashboard.php';

$fail = 0;
function ck( $label, $actual, $expected = true ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       exp: ' . var_export( $expected, true ) . '  got: ' . var_export( $actual, true ) . "\n";
	}
}

/**
 * Draw the page for one visitor, with nothing loaded or registered beforehand.
 *
 * @param int $uid     The signed-in user, or 0 for a visitor who is logged out.
 * @param array $known Script handles another module has already registered.
 * @return string The page.
 */
function page_for( $uid, array $known = array() ) {
	$GLOBALS['uid']        = $uid;
	$GLOBALS['scripts']    = array();
	$GLOBALS['registered'] = array_fill_keys( $known, array( 'src' => 'registered-earlier', 'deps' => array(), 'ver' => false, 'footer' => true ) );

	return WPCPM_Mentors_Dashboard::render( array() );
}

$GLOBALS['users'][7] = new WP_User( 7, 'Visitor', array( 'subscriber' ) );

echo "\n=== The page loads the script that asks a note's Delete ===\n";

$out = page_for( 0 );
ck( 'a visitor who is logged out is sent to log in', false !== strpos( $out, 'Please log in to see the students assigned to you.' ) );
ck( 'and the page enqueued wpcpm-forms before it stopped there', in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ) );

$out = page_for( 7 );
ck( 'a signed-in visitor with no student list is told so', false !== strpos( $out, 'This page is for program mentors.' ) );
ck( 'and the page enqueued wpcpm-forms before it stopped there', in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ) );
ck( 'and only once', count( array_keys( $GLOBALS['scripts'], 'wpcpm-forms', true ) ), 1 );

page_for( 0 );
ck( 'the script is registered from assets/js/forms.js, at the plugin version, in the footer, when no module has registered it yet',
    $GLOBALS['registered']['wpcpm-forms'] ?? null,
    array( 'src' => 'https://example.test/plugin/assets/js/forms.js', 'deps' => array(), 'ver' => 'test', 'footer' => true ) );

page_for( 0, array( 'wpcpm-forms' ) );
ck( 'and left as the calendar registered it when one has, so the handle stays one script',
    array( $GLOBALS['registered']['wpcpm-forms']['src'] ?? null, in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ) ),
    array( 'registered-earlier', true ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
