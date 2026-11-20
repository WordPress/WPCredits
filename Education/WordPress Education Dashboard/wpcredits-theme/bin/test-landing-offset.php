<?php
/**
 * Where an anchored card lands on a page with no sticky header (theme 1.32.31).
 *
 * The Administrator Dashboard, the Institution Dashboard and the Sponsor Dashboard are drawn in the plugin's
 * frame, on a bare canvas: the WordPress.org bar, which is not pinned, the content and the footer, and no
 * `.wpc-header-part`. The landing offset, `--wpc-landing-offset`, is the sticky bar's height and 28px of air,
 * and until 1.32.30 it reserved a bar's height there too, so a card reached by its anchor landed 104px below
 * the top of the window at desktop width and 208px on a phone, with nothing above it but the admin bar, which
 * WordPress makes room for itself. A page in the frame now carries `wpc-frame-page` on the body, and there the
 * offset is the air alone. The Mentor Report Card and the Student Report Card keep their sticky header, and
 * their offset is as it was.
 *
 * Read from the theme as it is written: the body class from `wpcredits_body_class()` in functions.php, run on
 * switches for the page tests; the templates, for which of them pins the header; the sheets, comments
 * stripped, for the rule and the offsets it leaves alone. The landing itself is measured in a browser.
 *
 * What this does not hold: the class on the three guides and the three updates in the frame. Their page tests
 * are stubbed here (no guide or update is ever the page), so only the three dashboards' class is checked, against
 * the list functions.php gives `add_theme_support( 'wpcpm-dashboard-frame' )`.
 *
 * Usage: php bin/test-landing-offset.php
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
function is_page() { return ! empty( $GLOBALS['req']['is_page'] ); }
function is_single() { return false; }
function is_user_logged_in() { return ! empty( $GLOBALS['req']['signed_in'] ); }
function get_queried_object() { return null; }
// The page tests are template-tags.php's; here they are switches.
function wpcredits_is_mentor_page() { return 'mentor' === $GLOBALS['req']['page']; }
function wpcredits_is_student_page() { return 'student' === $GLOBALS['req']['page']; }
function wpcredits_is_institution_page() { return 'institution' === $GLOBALS['req']['page']; }
function wpcredits_is_administrator_page() { return 'administrator' === $GLOBALS['req']['page']; }
function wpcredits_is_sponsor_page() { return 'sponsor' === $GLOBALS['req']['page']; }
function wpcredits_is_dashboard_page() { return in_array( $GLOBALS['req']['page'], array( 'mentor', 'student', 'institution', 'administrator', 'sponsor' ), true ); }

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

/**
 * A sheet of the theme, without its comments.
 *
 * @param string $path Below the theme root.
 * @return string
 */
function sheet( $path ) {
	return (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( WPCREDITS_THEME_DIR . $path ) );
}

// The body class filter, as functions.php writes it, run here on the switches above.
$functions = (string) file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' );
preg_match( '/^function wpcredits_body_class\(.*?^}$/ms', $functions, $found );
ck( 'functions.php has the body class filter', isset( $found[0] ), true );
eval( $found[0] ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- the theme's own source, read to run it on switches.

// The pages functions.php names for the frame, read from its own `add_theme_support()` call, so a dashboard
// dropped from that list is a dashboard that loses the class here.
preg_match( "/add_theme_support\\( 'wpcpm-dashboard-frame', array\\(([^)]*)\\) \\);/", $functions, $support );
preg_match_all( "/'([a-z-]+)'/", isset( $support[1] ) ? $support[1] : '', $named );
ck( 'functions.php names pages for the frame', ! empty( $named[1] ), true );
$names = array( $named[1] );

function classes_on( $page, array $change = array() ) {
	global $names;
	$GLOBALS['req'] = array_merge( array( 'page' => $page, 'support' => $names, 'is_page' => true, 'signed_in' => true ), $change );
	return wpcredits_body_class( array() );
}

$frame = array();
foreach ( array( 'administrator' => 'Administrator Dashboard', 'institution' => 'Institution Dashboard', 'sponsor' => 'Sponsor Dashboard' ) as $page => $name ) {
	$frame[ $name ] = in_array( 'wpc-frame-page', classes_on( $page ), true );
}
ck( 'the three dashboards in the frame carry wpc-frame-page, so their header gets no room', $frame, array( 'Administrator Dashboard' => true, 'Institution Dashboard' => true, 'Sponsor Dashboard' => true ) );

ck( 'the Mentor Report Card and the Student Report Card keep their classes, and no wpc-frame-page', array( classes_on( 'mentor' ), classes_on( 'student' ) ), array( array( 'wpc-dashboard-page', 'wpc-signed-in' ), array( 'wpc-dashboard-page', 'wpc-student-page', 'wpc-signed-in' ) ) );

ck( 'a frame dashboard keeps every class it had, the frame\'s added last but one', classes_on( 'administrator' ), array( 'wpc-dashboard-page', 'wpc-administrator-page', 'wpc-frame-page', 'wpc-signed-in' ) );

ck( 'a dashboard the theme does not name for the frame is drawn with the header, and has no wpc-frame-page', in_array( 'wpc-frame-page', classes_on( 'sponsor', array( 'support' => array( array( 'administrator' ) ) ) ), true ), false );

ck( 'any other page has no wpc-frame-page', classes_on( 'none' ), array( 'wpc-signed-in' ) );

// Which templates pin the header: every one but the frame's nine bare canvases.
$bare = array();
foreach ( glob( WPCREDITS_THEME_DIR . 'templates/*.html' ) as $file ) {
	if ( false === strpos( (string) file_get_contents( $file ), '"className":"wpc-header-part"' ) ) {
		$bare[] = basename( $file, '.html' );
	}
}
sort( $bare );
$unnamed = array();
foreach ( $bare as $canvas ) {
	$frame_name = preg_replace( array( '/^page-(.+)-dashboard$/', '/^page-(.+-guide)$/', '/^single-(.+)-update$/' ), array( '$1', '$1', '$1-updates' ), $canvas );
	if ( ! in_array( $frame_name, $names[0], true ) ) {
		$unnamed[] = $canvas;
	}
}
ck( 'each of the nine canvases is named for the frame in functions.php', $unnamed, array() );
ck( 'the templates without the sticky header are the frame\'s nine canvases', $bare, array( 'page-administrator-dashboard', 'page-administrator-guide', 'page-institution-dashboard', 'page-institution-guide', 'page-sponsor-dashboard', 'page-sponsor-guide', 'single-administrator-update', 'single-institution-update', 'single-sponsor-update' ) );
ck( 'the two Report Cards\' templates pin the header', array(
	false !== strpos( (string) file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-mentor-dashboard.html' ), '"className":"wpc-header-part"' ),
	false !== strpos( (string) file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-student-dashboard.html' ), '"className":"wpc-header-part"' ),
), array( true, true ) );

// The sheet: one rule, outside every at-rule so it holds at a phone's width too, the air alone.
$style = sheet( 'style.css' );
preg_match_all( '/([^{}]*\.wpc-frame-page[^{}]*)\{([^{}]*)\}/', $style, $rules, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
ck( 'style.css has one rule for wpc-frame-page', count( $rules ), 1 );

$at_depth = 0;
if ( $rules ) {
	$before   = substr( $style, 0, $rules[0][0][1] );
	$at_depth = substr_count( $before, '{' ) - substr_count( $before, '}' );
}
ck( 'and it is outside every media query, so it holds at desktop and phone widths', $at_depth, 0 );
ck( 'its selector is the body class alone', $rules ? trim( $rules[0][1][0] ) : null, '.wpc-frame-page' );
ck( 'it sets the landing offset to the air alone, and nothing else', $rules ? array_values( array_filter( array_map( 'trim', explode( ';', $rules[0][2][0] ) ) ) ) : null, array( '--wpc-landing-offset: 28px' ) );

preg_match( '/:root\s*\{[^}]*--wpc-landing-offset:\s*calc\( var\( --wpc-header-live, var\( --wpc-header-height \) \) \+ (\d+px) \);/', $style, $root );
ck( 'the air is the root offset\'s own, and the root offset is as it was', $root ? $root[1] : null, '28px' );
ck( 'the phone\'s stand-in for a bar not yet measured is as it was', 1 === preg_match( '/@media \( max-width: 640px \) \{\s*:root \{[^}]*--wpc-landing-offset: calc\( var\( --wpc-header-live, 180px \) \+ 28px \);/', $style ), true );
ck( 'the header\'s constant and its live height are as they were', array(
	1 === preg_match( '/--wpc-header-height: 76px;/', $style ),
	1 === preg_match( '/--wpc-header-live: initial;/', $style ),
), array( true, true ) );

// The dashboards' anchors still read the one offset, and the Mentor Report Card's row its 24px more.
$dashboard = sheet( 'assets/css/dashboard.css' );
ck( 'every plugin anchor on a dashboard lands by the landing offset, a student\'s row by 24px more', array(
	1 === preg_match( '/\.wpc-dashboard-page \[id\^="wpcpm-"\] \{\s*scroll-margin-top: var\( --wpc-landing-offset \);\s*\}/', $dashboard ),
	1 === preg_match( '/\.wpc-dashboard-page \.wpcpm-mentee\[id\] \{\s*scroll-margin-top: calc\( var\( --wpc-landing-offset \) \+ 24px \);\s*\}/', $dashboard ),
), array( true, true ) );

ck( 'dashboard.css\'s comment says the offset clears the header only where there is one', false !== strpos( (string) file_get_contents( WPCREDITS_THEME_DIR . 'assets/css/dashboard.css' ), 'wpc-frame-page' ), true );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
