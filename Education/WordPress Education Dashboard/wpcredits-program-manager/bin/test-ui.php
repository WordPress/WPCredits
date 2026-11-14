<?php
/**
 * WPCPM_UI: the surface switch (design 3.5) and the parts' sheet registration (design 3.1).
 *
 * What each block pins, and why:
 *
 * - **The Administrator Dashboard is on, every other surface is off.** `LIVE` is `array( 'administrator' )` in the
 *   source (Task 18, release 1): `on()` is true for that surface alone, and false for every other surface and every
 *   wp-admin tab until a release adds its own, so every other page of the site renders as before.
 * - **The surface is a stack.** `enter()` names the surface that is drawing, `leave()` returns to the one
 *   named before; none named is ''.
 * - **`set_live()` is the suites' own** and `null` puts the constant back; `root_class()` is the one
 *   word a root prints while its surface is on.
 * - **The sheet is registered once**, `assets/css/parts.css`, and is a dependency of the front end's
 *   `dashboard.css`, of the frame's sheet and of wp-admin's `wpcpm-admin`.
 *
 * Run: php bin/test-ui.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

// The style registry, as the dashboards' suites stand it in: every registration is recorded, so a second one shows.
$GLOBALS['style_src']           = array();
$GLOBALS['style_args']          = array();
$GLOBALS['style_registrations'] = array();
$GLOBALS['script_src']          = array();
$GLOBALS['script_args']         = array();
$GLOBALS['script_registrations'] = array();
$GLOBALS['enqueued_styles']     = array();
function wp_style_is( $handle, $list = 'enqueued' ) { return 'registered' === $list && isset( $GLOBALS['style_src'][ $handle ] ); }
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) { $GLOBALS['style_src'][ $handle ] = $src; $GLOBALS['style_args'][ $handle ] = array( $deps, $ver ); $GLOBALS['style_registrations'][] = $handle; }
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	if ( '' !== $src ) {
		$GLOBALS['style_src'][ $handle ]  = $src;
		$GLOBALS['style_args'][ $handle ] = array( $deps, $ver );
	}
	$GLOBALS['enqueued_styles'][] = $handle;
}
function wp_script_is( $handle, $list = 'enqueued' ) { return 'registered' === $list && isset( $GLOBALS['script_src'][ $handle ] ); }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) { $GLOBALS['script_src'][ $handle ] = $src; $GLOBALS['script_args'][ $handle ] = array( $deps, $ver, $footer ); $GLOBALS['script_registrations'][] = $handle; }
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) {
	if ( '' !== $src ) {
		$GLOBALS['script_src'][ $handle ]  = $src;
		$GLOBALS['script_args'][ $handle ] = array( $deps, $ver, $footer );
	}
}
function __( $s, $d = null ) { return $s; }

foreach ( array(
	'/../includes/class-wpcpm-ui.php',
	'/../includes/class-wpcpm-dashboard-frame.php',
	'/../includes/modules/class-wpcpm-mentors-dashboard.php',
	'/../includes/class-wpcpm-admin.php',
) as $file ) {
	if ( file_exists( __DIR__ . $file ) ) {
		require_once __DIR__ . $file;
	}
}

$fail   = 0;
$checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		$fail++;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '     expected: ' . var_export( $expected, true ) . "\n     actual:   " . var_export( $actual, true ) . "\n";
	}
}

if ( ! class_exists( 'WPCPM_UI' ) ) {
	ck( 'WPCPM_UI exists', false, true );
	echo "\n$fail of $checks checks failed\n";
	exit( 1 );
}

// The constant, from the source: the Administrator Dashboard alone (Task 18, release 1).
$source = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-ui.php' );
ck( 'LIVE is the Administrator Dashboard alone in the source', 1 === preg_match( "/^\tconst LIVE = array\( 'administrator' \);$/m", $source ), true );
ck( 'LIVE is the Administrator Dashboard alone', WPCPM_UI::LIVE, array( 'administrator' ) );
ck( 'the surfaces', WPCPM_UI::SURFACES, array( 'administrator', 'sponsor', 'institution', 'report-cards' ) );
ck( 'the style handle', WPCPM_UI::STYLE, 'wpcpm-parts' );
ck( 'the script handle', WPCPM_UI::SCRIPT, 'wpcpm-parts' );

// Under the real constant: the Administrator Dashboard is on, every other surface and tab off, and none named is ''.
ck( 'no surface named', WPCPM_UI::surface(), '' );
ck( 'off with none named', WPCPM_UI::on(), false );
foreach ( array_merge( WPCPM_UI::SURFACES, array( 'wpcpm-institutions/pipeline', 'wpcpm-sponsors/queue', 'administrator/x', 'Administrator' ) ) as $name ) {
	$is_on = 'administrator' === $name;
	WPCPM_UI::enter( $name );
	ck( "$name is named", WPCPM_UI::surface(), $name );
	ck( $is_on ? "$name is on" : "$name is off", WPCPM_UI::on(), $is_on );
	ck( $is_on ? "$name prints the root class" : "$name prints no root class", WPCPM_UI::root_class(), $is_on ? ' wpcpm-ui-on' : '' );
	WPCPM_UI::leave();
}
ck( 'left, none named', WPCPM_UI::surface(), '' );

// A stack.
WPCPM_UI::enter( 'administrator' );
WPCPM_UI::enter( 'report-cards' );
ck( 'the inner surface', WPCPM_UI::surface(), 'report-cards' );
WPCPM_UI::leave();
ck( 'leave returns to the surface named before', WPCPM_UI::surface(), 'administrator' );
WPCPM_UI::leave();
ck( 'leave to none', WPCPM_UI::surface(), '' );
WPCPM_UI::leave();
ck( 'leave on an empty stack stays at none', WPCPM_UI::surface(), '' );

// The suites' own live list.
ck( 'live is the constant', WPCPM_UI::live(), array( 'administrator' ) );
WPCPM_UI::set_live( array() );
WPCPM_UI::enter( 'administrator' );
ck( 'an empty list puts the Administrator Dashboard off, as a suite draws its off branch', array( WPCPM_UI::on(), WPCPM_UI::root_class() ), array( false, '' ) );
WPCPM_UI::leave();
WPCPM_UI::set_live( array( 'administrator' ) );
ck( 'live replaced', WPCPM_UI::live(), array( 'administrator' ) );
ck( 'on with none named is still off', WPCPM_UI::on(), false );
WPCPM_UI::enter( 'administrator' );
ck( 'on where live', WPCPM_UI::on(), true );
ck( 'the root class where on', WPCPM_UI::root_class(), ' wpcpm-ui-on' );
WPCPM_UI::enter( 'sponsor' );
ck( 'off after entering another', WPCPM_UI::on(), false );
ck( 'no root class where off', WPCPM_UI::root_class(), '' );
WPCPM_UI::leave();
ck( 'on again after leaving', WPCPM_UI::on(), true );
WPCPM_UI::leave();
WPCPM_UI::set_live( null );
ck( 'null puts the constant back', WPCPM_UI::live(), array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
ck( 'on again, by the constant', WPCPM_UI::on(), true );
WPCPM_UI::enter( 'sponsor' );
ck( 'and the Sponsor Dashboard off, by the constant', WPCPM_UI::on(), false );
WPCPM_UI::leave();
WPCPM_UI::leave();

// The sheet: registered once.
WPCPM_UI::register_assets();
ck( 'the parts sheet is registered', $GLOBALS['style_src']['wpcpm-parts'] ?? null, WPCPM_PLUGIN_URL . 'assets/css/parts.css' );
ck( 'the parts sheet has no dependency, the version is the plugin\'s', $GLOBALS['style_args']['wpcpm-parts'] ?? null, array( array(), 'test' ) );
ck( 'the parts script is registered in the footer', $GLOBALS['script_args']['wpcpm-parts'] ?? null, array( array(), 'test', true ) );
ck( 'the parts script file', $GLOBALS['script_src']['wpcpm-parts'] ?? null, WPCPM_PLUGIN_URL . 'assets/js/parts.js' );
WPCPM_UI::register_assets();
ck( 'a second call registers nothing', array_count_values( $GLOBALS['style_registrations'] )['wpcpm-parts'] ?? 0, 1 );
ck( 'nor the script', array_count_values( $GLOBALS['script_registrations'] )['wpcpm-parts'] ?? 0, 1 );

// The three dependants.
$GLOBALS['style_src']           = array();
$GLOBALS['style_args']          = array();
$GLOBALS['style_registrations'] = array();
WPCPM_Mentors_Dashboard::register_assets();
ck( 'dashboard.css depends on dashicons and the parts', $GLOBALS['style_args'][ WPCPM_Mentors_Dashboard::STYLE ][0] ?? null, array( 'dashicons', 'wpcpm-parts' ) );
ck( 'the front end registered the parts', $GLOBALS['style_src']['wpcpm-parts'] ?? null, WPCPM_PLUGIN_URL . 'assets/css/parts.css' );

$GLOBALS['style_src']           = array();
$GLOBALS['style_args']          = array();
$GLOBALS['style_registrations'] = array();
WPCPM_Dashboard_Frame::register_assets();
ck( 'the frame sheet depends on the parts', $GLOBALS['style_args'][ WPCPM_Dashboard_Frame::STYLE ][0] ?? null, array( 'wpcpm-parts' ) );
ck( 'the frame registered the parts when they were not', $GLOBALS['style_src']['wpcpm-parts'] ?? null, WPCPM_PLUGIN_URL . 'assets/css/parts.css' );
ck( 'once', array_count_values( $GLOBALS['style_registrations'] )['wpcpm-parts'] ?? 0, 1 );

$GLOBALS['style_src']           = array();
$GLOBALS['style_args']          = array();
$GLOBALS['style_registrations'] = array();
$admin = ( new ReflectionClass( 'WPCPM_Admin' ) )->newInstanceWithoutConstructor();
$admin->enqueue_assets( 'toplevel_page_wpcpm' );
ck( 'wpcpm-admin depends on the parts', $GLOBALS['style_args']['wpcpm-admin'][0] ?? null, array( 'wpcpm-parts' ) );
ck( 'wp-admin registered the parts', $GLOBALS['style_src']['wpcpm-parts'] ?? null, WPCPM_PLUGIN_URL . 'assets/css/parts.css' );

// ---- Every dashboard and every wp-admin tab of the plugin names itself (Task 9) ----
// Each render names its surface once, before it prints its root, and leaves once, after its root is closed: a
// renderer it calls then asks `on()` of the surface drawing. Read from the source, one function each.
$named_in = array(
	'includes/modules/class-wpcpm-administrators-dashboard.php' => "WPCPM_UI::enter( 'administrator' );",
	'includes/modules/class-wpcpm-sponsors-dashboard.php'       => "WPCPM_UI::enter( 'sponsor' );",
	'includes/modules/class-wpcpm-institutions-dashboard.php'   => "WPCPM_UI::enter( 'institution' );",
	'includes/modules/class-wpcpm-mentors-dashboard.php'        => "WPCPM_UI::enter( 'report-cards' );",
	'includes/modules/class-wpcpm-students-dashboard.php'       => "WPCPM_UI::enter( 'report-cards' );",
	'includes/modules/class-wpcpm-institutions.php'             => "WPCPM_UI::enter( \$this->page_slug() . '/' . \$tab );",
	'includes/modules/class-wpcpm-sponsors.php'                 => "WPCPM_UI::enter( \$this->page_slug() . '/' . \$tab );",
	'includes/modules/class-wpcpm-mentors.php'                  => "WPCPM_UI::enter( \$this->page_slug() . '/' . \$tab );",
	'includes/modules/class-wpcpm-students.php'                 => "WPCPM_UI::enter( \$this->page_slug() . '/' . \$tab );",
	'includes/modules/class-wpcpm-administrators.php'           => "WPCPM_UI::enter( \$this->page_slug() . '/' . \$tab );",
	'includes/modules/trait-wpcpm-accounts-screen.php'          => "WPCPM_UI::enter( \$this->page_slug() . '/' . \$tab );",
	'includes/tools/class-wpcpm-emails.php'                     => "WPCPM_UI::enter( \$this->page_slug() . '/' . \$tab );",
	// The two views drawn in a dashboard's frame name the frame's surface: the Administrator Dashboard's, or the
	// Sponsor Dashboard's for its own updates and guide (follow-up round, item 3).
	'includes/modules/class-wpcpm-administrator-updates-view.php' => "WPCPM_UI::enter( 'sponsor' === \$audience ? 'sponsor' : 'administrator' );",
	'includes/modules/class-wpcpm-administrator-guide-view.php'   => "WPCPM_UI::enter( 'WPCPM_Sponsors_Dashboard' === \$guide['dashboard'] ? 'sponsor' : 'administrator' );",
);
$named = array();
foreach ( $named_in as $file => $call ) {
	$src   = (string) file_get_contents( __DIR__ . '/../' . $file );
	$at    = strpos( $src, $call );
	$from  = false === $at ? false : strrpos( substr( $src, 0, $at ), 'function ' );
	$to    = false === $at ? false : strpos( $src, "\n\t}\n", $at );
	$body  = false === $from || false === $to ? '' : substr( $src, $from, $to - $from );
	$root  = false === $at ? false : strpos( $src, '<div class="', $at );
	$named[ $file ] = array(
		substr_count( $src, 'WPCPM_UI::enter(' ),
		substr_count( $body, $call ),
		substr_count( $body, 'WPCPM_UI::leave();' ),
		false !== $root && false !== $to && $root < $to,
		1 === preg_match( "/echo '<\/div>';\s*(?:WPCPM_Dashboard_Frame::close\(\);\s*)?\} finally \{\s*WPCPM_UI::leave\(\);\s*\}/", $body ),
		// Follow-up round, item 4: what is drawn between the two is one try, and the leave is its finally, so a
		// render that throws still leaves its surface.
		false !== $at && 1 === preg_match( '/\A' . preg_quote( $call, '/' ) . '\s*try \{/', substr( $src, $at ) ),
	);
}
ck( 'each dashboard, each view drawn in a dashboard\'s frame and each wp-admin screen that draws tabs names its surface once, before its root, and leaves once, in the finally of the try that draws it, after its root is closed', $named, array_fill_keys( array_keys( $named_in ), array( 1, 1, 1, true, true, true ) ) );
// The dashboards' roots, and the frame's, carry the switch's class; on a surface that is off it is '' and the page is
// byte for byte today's. The wp-admin wraps take it in release 2, with the tabs it turns on.
$roots = array();
foreach ( array(
	'includes/modules/class-wpcpm-administrators-dashboard.php' => "printf( '<div class=\"wpcpm-dashboard wpcpm-administrator wpcpm-administrator--%1\$s%2\$s\">', esc_attr( \$view ), esc_attr( WPCPM_UI::root_class() ) );",
	'includes/modules/class-wpcpm-sponsors-dashboard.php'       => "printf( '<div class=\"wpcpm-dashboard wpcpm-sponsor wpcpm-sponsor--%1\$s%2\$s\">', esc_attr( \$view ), esc_attr( WPCPM_UI::root_class() ) );",
	'includes/modules/class-wpcpm-institutions-dashboard.php'   => "echo '<div class=\"wpcpm-dashboard wpcpm-institution' . esc_attr( WPCPM_UI::root_class() ) . '\">';",
	'includes/modules/class-wpcpm-mentors-dashboard.php'        => "echo '<div class=\"wpcpm-dashboard' . esc_attr( WPCPM_UI::root_class() ) . '\">';",
	'includes/modules/class-wpcpm-students-dashboard.php'       => "echo '<div class=\"wpcpm-dashboard wpcpm-student' . esc_attr( WPCPM_UI::root_class() ) . '\">';",
	'includes/class-wpcpm-dashboard-frame.php'                  => "'<div class=\"wpcpm-frame wpcpm-frame--%1\$s%2\$s\" data-wpcpm-frame>',",
) as $file => $line ) {
	$roots[ $file ] = substr_count( (string) file_get_contents( __DIR__ . '/../' . $file ), $line );
}
ck( 'each dashboard\'s root and the frame\'s root print the switch\'s class', $roots, array_fill_keys( array_keys( $roots ), 1 ) );

echo "\n" . ( $fail ? "$fail of $checks checks failed" : "all $checks checks passed" ) . "\n";
exit( $fail ? 1 : 0 );
