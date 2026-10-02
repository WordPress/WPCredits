<?php
/**
 * Where a decision goes back to.
 *
 * What this pins: a posted URL is never followed; only `dashboard` reaches the Administrator
 * Dashboard, and only while that page exists; an anchor outside the known list is dropped;
 * `field()` prints nothing for the wp-admin default, so a form the queue draws is unchanged.
 * The way from a wp-admin screen into the dashboard, "Open on the Administrator Dashboard", is
 * one printer here, beside the card ids it reads: to a card, or to one item of it, and nothing
 * for a card the dashboard does not have or while its page is missing.
 *
 * Run from the plugin root:  php bin/test-return.php
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return esc_html( __( $s, $d ) ); }
// Escaped the way an attribute is, so a check can see that the address went through it.
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
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
function link_printed( $card, $item = '', $class = '' ) {
	if ( ! method_exists( 'WPCPM_Return', 'render_dashboard_link' ) ) {
		return '(no printer)';
	}
	ob_start();
	WPCPM_Return::render_dashboard_link( $card, $item, $class );
	return (string) ob_get_clean();
}

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
ck( 'the anchors are the thirteen cards and the strip', WPCPM_Return::ANCHORS, array( 'attention', 'applications', 'agreements', 'reports', 'requests', 'sponsor-applications', 'sponsor-posts', 'sponsor-agreements', 'offers-low', 'duplicates', 'interests', 'sponsors', 'programs', 'health' ) );

// Read from the cards rather than from a copy of the list: card_open()'s own contract says its
// id is one of these, and three of the twelve ids - offers-low, interests and sponsors - were
// not, so a decision posted from one of those cards would have come back to the top of the page
// with the anchor silently dropped by field() (deep check FADMN-6).
preg_match_all( "/self::card_open\(\s*'([a-z-]+)'/", (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-cards.php' ), $cards );
$ids = array_values( array_unique( $cards[1] ) );
ck( 'and every card on the Administrator Dashboard uses one of them', array( count( $ids ) > 0, array_values( array_diff( $ids, WPCPM_Return::ANCHORS ) ) ), array( true, array() ) );

// One printer for every wp-admin screen that links into the dashboard: to the card at its id, the
// one `card_open()` gives it, or to one item of the card when the caller names the item's element
// id; nothing for a card the dashboard does not have, and nothing while the page is missing, which
// the caller says in its own place.
$GLOBALS['admin_page'] = 'https://example.test/administrator-dashboard/';
ck( 'the way to a card is one paragraph, Open on the Administrator Dashboard, to the page at the card\'s id',
	link_printed( 'sponsor-posts' ),
	'<p><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-posts">Open on the Administrator Dashboard</a></p>' );
ck( 'and the way to one item of the card is the same paragraph at the item\'s own id',
	link_printed( 'sponsor-posts', 'wpcpm-sponsor-post-905' ),
	'<p><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-post-905">Open on the Administrator Dashboard</a></p>' );
ck( 'nothing for a card the dashboard does not have, with an item or without',
	array( link_printed( 'evil' ), link_printed( 'evil', 'wpcpm-sponsor-post-905' ), link_printed( '' ) ),
	array( '', '', '' ) );
ck( 'the address is escaped where it is printed',
	false !== strpos( link_printed( 'sponsor-posts', 'x" onclick="y' ), 'href="https://example.test/administrator-dashboard/#x&quot; onclick=&quot;y"' ),
	true );
// A block that ends in a line of its own markup, as a review block does, names the paragraph's class.
ck( 'given a class, the paragraph carries it, and only a class name gets through',
	array(
		link_printed( 'agreements', 'wpcpm-review-12', 'wpcpm-review__open' ),
		link_printed( 'sponsor-posts', '', 'x" onclick="y' ),
		link_printed( 'sponsor-posts', '', '"' ),
	),
	array(
		'<p class="wpcpm-review__open"><a href="https://example.test/administrator-dashboard/#wpcpm-review-12">Open on the Administrator Dashboard</a></p>',
		'<p class="xonclicky"><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-posts">Open on the Administrator Dashboard</a></p>',
		'<p><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-posts">Open on the Administrator Dashboard</a></p>',
	) );
$GLOBALS['admin_page'] = '';
ck( 'and nothing at all while the page is missing, to a card or to an item',
	array( link_printed( 'sponsor-posts' ), link_printed( 'sponsor-posts', 'wpcpm-sponsor-post-905' ), link_printed( 'agreements', 'wpcpm-review-12', 'wpcpm-review__open' ) ),
	array( '', '', '' ) );

// The line is written once, here, so every screen and every block that links into the dashboard
// prints it through this method and none keeps a copy of its own.
$written = array();
foreach ( array_merge( glob( WPCPM_PLUGIN_DIR . 'includes/*.php' ), glob( WPCPM_PLUGIN_DIR . 'includes/*/*.php' ) ) as $file ) {
	$times = substr_count( (string) file_get_contents( $file ), "'Open on the Administrator Dashboard'" );
	if ( $times > 0 ) {
		$written[ basename( $file ) ] = $times;
	}
}
ck( 'and the line is written once in the plugin, in this method', $written, array( 'class-wpcpm-return.php' => 1 ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
