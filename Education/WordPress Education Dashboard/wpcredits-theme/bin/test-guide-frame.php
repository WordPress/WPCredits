<?php
/**
 * The Administrator guide on its bare canvas (theme 1.32.23, with WPCredits Program Manager 1.123.70).
 *
 * The guide's page names the Documentation template; while the plugin can draw the guide in the
 * Administrator Dashboard's frame, the theme draws it on `page-administrator-guide` instead, and loads
 * the frame's sheet and faces there. The page's slug already puts that template in the list, after the
 * stored one, so it has to be moved to the front, not added: core orders the list with `array_flip()`,
 * where the last of two equal entries wins.
 *
 * Usage: php bin/test-guide-frame.php
 *
 * @package WPCredits_Theme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCREDITS_THEME_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['req'] = array();
function add_action() {}
function add_filter() {}
function get_theme_support( $feature ) { return 'wpcpm-dashboard-frame' === $feature ? $GLOBALS['req']['support'] : false; }
function is_page() { return $GLOBALS['req']['is_page']; }
function is_single() { return ! empty( $GLOBALS['req']['is_single'] ); }
function get_queried_object() { return $GLOBALS['req']['object']; }
// The Administrator Dashboard's own page test is template-tags.php's; here it is a switch.
function wpcredits_is_administrator_page() { return $GLOBALS['req']['dashboard']; }

class WP_Post {
	public $post_name = '';
}

// The plugin's answer, standing in: the page at the guide's path.
class WPCPM_Administrator_Guide_View {
	public static function is_guide( $post ) { return $post instanceof WP_Post && 'administrator-guide' === $post->post_name; }
}

// The plugin's answer for an update (1.123.71), standing in: an update drawn for this reader.
class WPCPM_Administrator_Updates_View {
	public static function draws( $post ) { return $post instanceof WP_Post && 'site-update' === $post->post_name && ! empty( $GLOBALS['req']['manager'] ); }
}

require_once WPCREDITS_THEME_DIR . 'inc/dashboard.php';

$fail   = 0;
$checks = 0;

function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n";
		echo '       actual:   ' . var_export( $actual, true ) . "\n";
	}
}

function on( $slug, array $change = array() ) {
	$post            = new WP_Post();
	$post->post_name = $slug;
	$GLOBALS['req']  = array_merge(
		array(
			'support'   => array( array( 'administrator', 'administrator-guide' ) ),
			'is_page'   => true,
			'object'    => $post,
			'dashboard' => false,
		),
		$change
	);
}

// What core hands the filter for page 560: the stored template, then page-{slug}, page-{id}, page.
$hierarchy = array( 'page-documentation', 'page-administrator-guide.php', 'page-560.php', 'page.php' );

on( 'administrator-guide' );
$moved = wpcredits_guide_frame_template( $hierarchy );
ck( 'on the guide, its bare canvas is moved ahead of the Documentation template, once, so it is the one core picks', array( $moved, array_search( 'page-administrator-guide.php', array_flip( array_flip( $moved ) ), true ) ), array( array( 'page-administrator-guide.php', 'page-documentation', 'page-560.php', 'page.php' ), 0 ) );

$sorted = array_flip( array_map( static function ( $t ) { return preg_replace( '/\.php$/', '', $t ); }, $moved ) );
ck( 'and core\'s own ordering, array_flip() of the list, ranks it first', $sorted['page-administrator-guide'] < $sorted['page-documentation'], true );

ck( 'the guide is a page the plugin draws in its frame, so the frame\'s sheet and faces load there', wpcredits_is_frame_page(), true );

$untouched = array(
	'another page'                            => array( 'mentor-guide', array() ),
	'a theme that names only the dashboard'   => array( 'administrator-guide', array( 'support' => array( array( 'administrator' ) ) ) ),
	'a theme that names nothing'              => array( 'administrator-guide', array( 'support' => false ) ),
	'a request that is not for a page'        => array( 'administrator-guide', array( 'is_page' => false ) ),
);
$left = array();
foreach ( $untouched as $name => $case ) {
	on( $case[0], $case[1] );
	$left[ $name ] = array( wpcredits_guide_frame_template( $hierarchy ) === $hierarchy, wpcredits_is_guide_frame_page() );
}
ck( 'anywhere else the list is left as core made it, and the page is no guide in the frame', $left, array_fill_keys( array_keys( $untouched ), array( true, false ) ) );

on( 'mentor-guide', array( 'dashboard' => true ) );
ck( 'the Administrator Dashboard is still a page in the frame', wpcredits_is_frame_page(), true );

on( 'mentor-guide', array( 'dashboard' => true, 'support' => array( array( 'administrator-guide' ) ) ) );
ck( 'but only while the theme names it', wpcredits_is_frame_page(), false );

ck( 'the bare canvas is the dashboard\'s: the WordPress.org bar, the post content in the app\'s main area, the footer, and no title or contents list of its own', array(
	file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-administrator-guide.html' ) === str_replace( 'wpc-main--administrator', 'wpc-main--administrator-guide', file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-administrator-dashboard.html' ) ),
	false !== strpos( file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' ), "array( 'administrator', 'administrator-guide'" ),
), array( true, true ) );

// Theme 1.32.24: a program update an Administrator reads is drawn on its own bare canvas, ahead of every
// template core would pick for the post; the plugin decides, post by post and reader by reader.
$single = array( 'single-post-site-update.php', 'single-post.php', 'single.php' );
$names  = array( array( 'administrator', 'administrator-guide', 'administrator-updates' ) );
on( 'site-update', array( 'is_page' => false, 'is_single' => true, 'manager' => true, 'support' => $names ) );
ck( 'an update the plugin draws for this reader is on single-administrator-update, first, and is a page in the frame', array( wpcredits_update_frame_template( $single ), wpcredits_is_frame_page() ), array( array( 'single-administrator-update.php', 'single-post-site-update.php', 'single-post.php', 'single.php' ), true ) );
$kept = array(
	'a reader the plugin does not draw it for' => array( 'site-update', array( 'is_page' => false, 'is_single' => true, 'manager' => false, 'support' => $names ) ),
	'another post'                             => array( 'news', array( 'is_page' => false, 'is_single' => true, 'manager' => true, 'support' => $names ) ),
	'a theme that does not name the updates'   => array( 'site-update', array( 'is_page' => false, 'is_single' => true, 'manager' => true, 'support' => array( array( 'administrator', 'administrator-guide' ) ) ) ),
	'a request that is not for one post'       => array( 'site-update', array( 'is_page' => false, 'is_single' => false, 'manager' => true, 'support' => $names ) ),
);
$as_core = array();
foreach ( $kept as $name => $case ) {
	on( $case[0], $case[1] );
	$as_core[ $name ] = array( wpcredits_update_frame_template( $single ) === $single, wpcredits_is_update_frame_page() );
}
ck( 'anywhere else the post keeps the templates core made for it', $as_core, array_fill_keys( array_keys( $kept ), array( true, false ) ) );
ck( 'the update\'s canvas is the dashboard\'s, and functions.php names the updates for the frame', array(
	file_get_contents( WPCREDITS_THEME_DIR . 'templates/single-administrator-update.html' ) === str_replace( 'wpc-main--administrator', 'wpc-main--administrator-update', file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-administrator-dashboard.html' ) ),
	false !== strpos( file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' ), "add_theme_support( 'wpcpm-dashboard-frame', array( 'administrator', 'administrator-guide', 'administrator-updates' ) );" ),
), array( true, true ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
