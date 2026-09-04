<?php
/**
 * Where a decision goes back to.
 *
 * What this pins: a posted URL is never followed; only `dashboard` reaches the Administrator
 * Dashboard, and only while that page exists; an anchor outside the known list is dropped;
 * `field()` prints nothing for the wp-admin default, so a form the queue draws is unchanged.
 *
 * Run from the plugin root:  php bin/test-return.php
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function is_multisite() { return false; }

class WPCPM_Administrators_Dashboard {
	public static function page_url() { return (string) $GLOBALS['admin_page']; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';

$fail  = 0;
$total = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $total;
	$total++;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) { echo '       exp: ' . var_export( $expected, true ) . '  got: ' . var_export( $actual, true ) . "\n"; }
}
function printed( $where, $anchor = '' ) { ob_start(); WPCPM_Return::field( $where, $anchor ); return (string) ob_get_clean(); }

$default = 'https://example.test/wp-admin/admin.php?page=wpcpm-institutions';
$GLOBALS['admin_page'] = 'https://example.test/administrator-dashboard/';

$_POST = array();
ck( 'nothing posted is the default', WPCPM_Return::url( $default ), $default );
$_POST = array( 'wpcpm_return' => 'admin' );
ck( 'admin is the default', WPCPM_Return::url( $default ), $default );
$_POST = array( 'wpcpm_return' => 'https://evil.example/' );
ck( 'a URL is not a place', WPCPM_Return::url( $default ), $default );
$_POST = array( 'wpcpm_return' => 'dashboard' );
ck( 'dashboard is the Administrator Dashboard', WPCPM_Return::url( $default ), 'https://example.test/administrator-dashboard/' );
$_POST = array( 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'agreements' );
ck( 'a known anchor travels', WPCPM_Return::url( $default ), 'https://example.test/administrator-dashboard/#wpcpm-agreements' );
$_POST = array( 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'evil' );
ck( 'an unknown anchor is dropped', WPCPM_Return::url( $default ), 'https://example.test/administrator-dashboard/' );
$GLOBALS['admin_page'] = '';
$_POST = array( 'wpcpm_return' => 'dashboard' );
ck( 'no page is the default', WPCPM_Return::url( $default ), $default );
$_POST = array();

ck( 'the field prints nothing for the wp-admin default', printed( 'admin' ), '' );
ck( 'and nothing for an empty target', printed( '' ), '' );
$html = printed( 'dashboard', 'requests' );
ck( 'and both inputs for the dashboard', false !== strpos( $html, 'name="wpcpm_return" value="dashboard"' ) && false !== strpos( $html, 'name="wpcpm_return_to" value="requests"' ), true );
ck( 'an unknown anchor is not printed', false !== strpos( printed( 'dashboard', 'evil' ), 'wpcpm_return_to' ), false );
ck( 'the anchors are the six cards and the strip', WPCPM_Return::ANCHORS, array( 'attention', 'applications', 'agreements', 'reports', 'requests', 'programs', 'health' ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
