<?php
/**
 * The header menu's own links (inc/site-menu.php, theme 1.32.20), on stand-ins written here: which
 * entries a reader is offered, the blocks they become, and where they go among a navigation's items.
 *
 * Run: php bin/test-site-menu.php
 *
 * @package WPCredits_Theme
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['pages']     = array();
$GLOBALS['logged_in'] = false;
$GLOBALS['filters']   = array();

class WP_Post {
	public $ID = 0;
	public $post_status = 'publish';
	public $post_name = '';
}
class WPCPM_Institution_Application {
	public static $url = 'https://site.example/institution-application/';
	public static function page_url() {
		return self::$url;
	}
}
function __( $text, $domain = null ) {
	return $text;
}
function is_user_logged_in() {
	return (bool) $GLOBALS['logged_in'];
}
function get_page_by_path( $path ) {
	return isset( $GLOBALS['pages'][ $path ] ) ? $GLOBALS['pages'][ $path ] : null;
}
function get_permalink( $post ) {
	return 'https://site.example/' . $post->post_name . '/';
}
function sanitize_html_class( $text ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', $text );
}
function add_filter( $hook, $callback ) {
	$GLOBALS['filters'][ $hook ][] = $callback;
}
function page( $id, $name, $status = 'publish' ) {
	$post              = new WP_Post();
	$post->ID          = $id;
	$post->post_name   = $name;
	$post->post_status = $status;

	$GLOBALS['pages'][ $name ] = $post;
}

require dirname( __DIR__ ) . '/inc/site-menu.php';

$fail  = 0;
$total = 0;

function ck( $label, $actual, $expected ) {
	global $fail, $total;

	++$total;

	$ok = $actual === $expected;

	if ( ! $ok ) {
		++$fail;
	}

	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";

	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n";
		echo '       actual:   ' . var_export( $actual, true ) . "\n";
	}
}

/** The entries as a short list: a key, and for a submenu its rows' keys. */
function keys() {
	$out = array();

	foreach ( wpcredits_site_menu_entries() as $entry ) {
		$out[] = empty( $entry['rows'] ) ? $entry['key'] : $entry['key'] . ': ' . implode( ', ', array_column( $entry['rows'], 'key' ) );
	}

	return $out;
}

page( 5, 'students' );
page( 6, 'mentors' );
page( 7, 'student-impact' );
page( 8, 'stats' );

ck( 'a visitor is offered Students, Mentors, Institutions and Impact with its two rows, in the bar\'s order',
	keys(),
	array( 'students', 'mentors', 'institutions', 'impact: student-impact, stats' ) );

$GLOBALS['logged_in'] = true;
ck( 'a signed-in reader, whose bar carries their own links too, is not offered Institutions', keys(), array( 'students', 'mentors', 'impact: student-impact, stats' ) );
$GLOBALS['logged_in'] = false;

page( 6, 'mentors', 'draft' );
unset( $GLOBALS['pages']['stats'] );
WPCPM_Institution_Application::$url = '';
ck( 'a link is offered only while its page is published: a draft, a page that is not there and a form with no page are left out', keys(), array( 'students', 'impact: student-impact' ) );

unset( $GLOBALS['pages']['student-impact'] );
ck( 'a submenu with no row is left out', keys(), array( 'students' ) );

unset( $GLOBALS['pages']['students'] );
ck( 'and a site with none of the pages gets a navigation left as it was',
	array( wpcredits_site_menu_blocks(), wpcredits_site_menu_navigation( array( array( 'blockName' => 'core/navigation-link' ) ) ) ),
	array( array(), array( array( 'blockName' => 'core/navigation-link' ) ) ) );

page( 5, 'students' );
page( 6, 'mentors' );
page( 7, 'student-impact' );
page( 8, 'stats' );
WPCPM_Institution_Application::$url = 'https://site.example/institution-application/';

$blocks = wpcredits_site_menu_blocks();

ck( 'the entries are the navigation\'s own blocks: three links and a submenu',
	array_column( $blocks, 'blockName' ),
	array( 'core/navigation-link', 'core/navigation-link', 'core/navigation-link', 'core/navigation-submenu' ) );
ck( 'a page is named by its ID, so core marks the page being viewed and drops one no longer published; the form by its address',
	array( $blocks[0]['attrs'], $blocks[2]['attrs'] ),
	array(
		array( 'label' => 'Students', 'className' => 'wpc-nav__site wpc-nav__site--students', 'type' => 'page', 'kind' => 'post-type', 'id' => 5, 'url' => 'https://site.example/students/' ),
		array( 'label' => 'Institutions', 'className' => 'wpc-nav__site wpc-nav__site--institutions', 'kind' => 'custom', 'url' => 'https://site.example/institution-application/' ),
	) );
ck( 'the submenu is Impact, holding its two rows as links, with a place for each in its content',
	array( $blocks[3]['attrs']['label'], array_column( array_column( $blocks[3]['innerBlocks'], 'attrs' ), 'label' ), $blocks[3]['innerContent'] ),
	array( 'Impact', array( 'Student impact', 'The program in numbers' ), array( null, null ) ) );

$drawn = wpcredits_site_menu_navigation( array( array( 'blockName' => 'core/navigation-link', 'attrs' => array( 'label' => 'Saved' ) ), array( 'blockName' => 'wpcredits/get-involved' ) ) );

ck( 'they go first among the items core draws from the site\'s saved menu, before its own and what is hooked at its end, through the filter core hands those items to',
	array( array_column( $drawn, 'blockName' ), isset( $GLOBALS['filters']['block_core_navigation_render_fallback'] ), wpcredits_site_menu_navigation( 'not blocks' ) ),
	array( array( 'core/navigation-link', 'core/navigation-link', 'core/navigation-link', 'core/navigation-submenu', 'core/navigation-link', 'wpcredits/get-involved' ), true, 'not blocks' ) );

$sheet = (string) file_get_contents( dirname( __DIR__ ) . '/style.css' );
ck( 'without JavaScript a submenu opens under the pointer and while the focus is in it, so Impact\'s two rows are always reached, at their own height and unclipped',
	preg_match( '#html:not\( \.wpc-js \) \.wpc-nav \.has-child:hover > \.wp-block-navigation__submenu-container,\s*html:not\( \.wpc-js \) \.wpc-nav \.has-child:focus-within > \.wp-block-navigation__submenu-container \{[^}]*display: flex;[^}]*height: auto;[^}]*overflow: visible;[^}]*visibility: visible;#', $sheet ),
	1 );

$functions = (string) file_get_contents( dirname( __DIR__ ) . '/functions.php' );
ck( 'functions.php loads the file', substr_count( $functions, "inc/site-menu.php" ), 1 );

printf( "\n%s (%d checks)\n", $fail ? $fail . ' FAILURE(S)' : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
