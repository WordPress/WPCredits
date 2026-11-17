<?php
/**
 * The Administrator guide on its bare canvas (theme 1.32.23, with WPCredits Program Manager 1.123.70), and the
 * Sponsor Dashboard, its guide and a sponsor's updates on theirs (theme 1.32.27, with the plugin's 1.123.78), and
 * the Institution Dashboard, its guide and an institution's updates on theirs.
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
// The Administrator and Sponsor Dashboards' own page tests are template-tags.php's; here they are switches.
function wpcredits_is_administrator_page() { return $GLOBALS['req']['dashboard']; }
function wpcredits_is_sponsor_page() { return ! empty( $GLOBALS['req']['sponsor_dashboard'] ); }
// The real test also asks for the plugin's class, `WPCPM_Institutions_Dashboard`; here that is a switch too.
function wpcredits_is_institution_page() { return ! empty( $GLOBALS['req']['institution_dashboard'] ) && empty( $GLOBALS['req']['no_institutions_class'] ); }

class WP_Post {
	public $post_name = '';
}

// The plugin's answer, standing in: the frame a guide's page is drawn in for this reader (1.123.78), the
// Administrator guide for anybody who opens it here, the Sponsor guide only for a reader the plugin draws it for.
class WPCPM_Administrator_Guide_View {
	public static function is_guide( $post ) { return 'administrator-guide' === self::frame_for( $post ); }
	public static function frame_for( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		if ( 'administrator-guide' === $post->post_name ) {
			return 'administrator-guide';
		}
		if ( 'institution-guide' === $post->post_name ) {
			return ! empty( $GLOBALS['req']['institution_reader'] ) ? 'institution-guide' : '';
		}
		return 'sponsor-guide' === $post->post_name && ! empty( $GLOBALS['req']['sponsor_reader'] ) ? 'sponsor-guide' : '';
	}
}

// The plugin's answer for an update (1.123.71; whose frame since 1.123.78), standing in: an Administrator's
// frame for a manager, a sponsor's for somebody who acts for a sponsor.
class WPCPM_Administrator_Updates_View {
	public static function draws( $post ) { return '' !== self::audience_of( $post ); }
	public static function audience_of( $post ) {
		if ( ! $post instanceof WP_Post || 'site-update' !== $post->post_name ) {
			return '';
		}
		if ( ! empty( $GLOBALS['req']['manager'] ) ) {
			return 'administrator';
		}
		if ( ! empty( $GLOBALS['req']['member'] ) ) {
			return 'sponsor';
		}
		return ! empty( $GLOBALS['req']['school_contact'] ) ? 'institution' : '';
	}
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
	false !== strpos( file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' ), "add_theme_support( 'wpcpm-dashboard-frame', array( 'administrator', 'administrator-guide', 'administrator-updates', 'sponsor', 'sponsor-guide', 'sponsor-updates', 'institution', 'institution-guide', 'institution-updates' ) );" ),
), array( true, true ) );

// Theme 1.32.27, with the plugin's 1.123.78: the Sponsor Dashboard, the Sponsor guide and each update a sponsor
// reads are drawn in the Sponsor Dashboard's frame, each on a bare canvas of its own.
$all = array( array( 'administrator', 'administrator-guide', 'administrator-updates', 'sponsor', 'sponsor-guide', 'sponsor-updates' ) );

on( 'sponsor-dashboard', array( 'sponsor_dashboard' => true, 'support' => $all ) );
ck( 'the Sponsor Dashboard is a page in the frame while functions.php names it', wpcredits_is_frame_page(), true );
on( 'sponsor-dashboard', array( 'sponsor_dashboard' => true ) );
ck( 'and is not while it does not', wpcredits_is_frame_page(), false );

$sponsor_hierarchy = array( 'page-documentation', 'page-sponsor-guide.php', 'page-1770.php', 'page.php' );
on( 'sponsor-guide', array( 'sponsor_reader' => true, 'support' => $all ) );
ck( 'the Sponsor guide, for a reader the plugin draws it for, is moved onto page-sponsor-guide, once, ahead of the Documentation template, and is a page in the frame', array( wpcredits_guide_frame_template( $sponsor_hierarchy ), wpcredits_is_frame_page() ), array( array( 'page-sponsor-guide.php', 'page-documentation', 'page-1770.php', 'page.php' ), true ) );

$sponsor_left = array(
	'a reader the plugin does not draw it for' => array( 'sponsor-guide', array( 'support' => $all ) ),
	'a theme that names only the Administrator guide' => array( 'sponsor-guide', array( 'sponsor_reader' => true ) ),
);
$as_named = array();
foreach ( $sponsor_left as $name => $case ) {
	on( $case[0], $case[1] );
	$as_named[ $name ] = array( wpcredits_guide_frame_template( $sponsor_hierarchy ) === $sponsor_hierarchy, wpcredits_is_guide_frame_page() );
}
ck( 'anywhere else the Sponsor guide keeps the Documentation template it names', $as_named, array_fill_keys( array_keys( $sponsor_left ), array( true, false ) ) );

on( 'site-update', array( 'is_page' => false, 'is_single' => true, 'member' => true, 'support' => $all ) );
ck( 'an update somebody who acts for a sponsor reads is on single-sponsor-update, first, and is a page in the frame', array( wpcredits_update_frame_template( $single ), wpcredits_is_frame_page() ), array( array( 'single-sponsor-update.php', 'single-post-site-update.php', 'single-post.php', 'single.php' ), true ) );
on( 'site-update', array( 'is_page' => false, 'is_single' => true, 'member' => true, 'support' => $names ) );
ck( 'and keeps its own templates while functions.php does not name the sponsor\'s updates', array( wpcredits_update_frame_template( $single ) === $single, wpcredits_is_update_frame_page() ), array( true, false ) );

ck( 'the Sponsor Dashboard\'s, the Sponsor guide\'s and a sponsor\'s update\'s canvases are the Administrator Dashboard\'s, each with its own class', array(
	file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-sponsor-dashboard.html' ) === str_replace( 'wpc-main--administrator', 'wpc-main--sponsor', file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-administrator-dashboard.html' ) ),
	file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-sponsor-guide.html' ) === str_replace( 'wpc-main--administrator', 'wpc-main--sponsor-guide', file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-administrator-dashboard.html' ) ),
	file_get_contents( WPCREDITS_THEME_DIR . 'templates/single-sponsor-update.html' ) === str_replace( 'wpc-main--administrator', 'wpc-main--sponsor-update', file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-administrator-dashboard.html' ) ),
), array( true, true, true ) );

// The Institution Dashboard, the Institution guide and each update an institution reads, in the Institution
// Dashboard's frame, each on a bare canvas of its own: the sponsor's, with `sponsor` read as `institution`.
$every = array( array( 'administrator', 'administrator-guide', 'administrator-updates', 'sponsor', 'sponsor-guide', 'sponsor-updates', 'institution', 'institution-guide', 'institution-updates' ) );

ck( 'functions.php names the Institution Dashboard, its guide and its updates for the frame, after the sponsor\'s', false !== strpos( file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' ), "'sponsor', 'sponsor-guide', 'sponsor-updates', 'institution', 'institution-guide', 'institution-updates' ) );" ), true );

on( 'institution-dashboard', array( 'institution_dashboard' => true, 'support' => $every ) );
ck( 'the Institution Dashboard is a page in the frame while the plugin\'s class exists and functions.php names it', wpcredits_is_frame_page(), true );
$institution_left = array(
	'a theme that names the sponsor\'s pages only'  => array( 'support' => $all ),
	'a theme that names the guide and updates only' => array( 'support' => array( array( 'institution-guide', 'institution-updates' ) ) ),
	'a site without the plugin\'s class'            => array( 'support' => $every, 'no_institutions_class' => true ),
);
$as_today = array();
foreach ( $institution_left as $name => $change ) {
	on( 'institution-dashboard', array_merge( array( 'institution_dashboard' => true ), $change ) );
	$as_today[ $name ] = wpcredits_is_frame_page();
}
ck( 'and is not, as today, without the name or without the plugin', $as_today, array_fill_keys( array_keys( $institution_left ), false ) );
ck( 'the real page test still asks for the plugin\'s class first', 1 === preg_match( "/function wpcredits_is_institution_page\(\) \{\s*if \( ! wpcredits_plugin_active\(\) \|\| ! is_singular\(\) \|\| ! class_exists\( 'WPCPM_Institutions_Dashboard' \) \) \{\s*return false;/", file_get_contents( WPCREDITS_THEME_DIR . 'inc/template-tags.php' ) ), true );

$institution_hierarchy = array( 'page-documentation', 'page-institution-guide.php', 'page-1880.php', 'page.php' );
on( 'institution-guide', array( 'institution_reader' => true, 'support' => $every ) );
ck( 'the Institution guide, for a reader the plugin draws it for, is moved onto page-institution-guide, once, ahead of the Documentation template, and is a page in the frame', array( wpcredits_guide_frame_template( $institution_hierarchy ), wpcredits_is_frame_page() ), array( array( 'page-institution-guide.php', 'page-documentation', 'page-1880.php', 'page.php' ), true ) );
$guide_left = array(
	'a reader the plugin does not draw it for'  => array( 'support' => $every ),
	'a theme that names the sponsor\'s pages only' => array( 'institution_reader' => true, 'support' => $all ),
);
$guide_kept = array();
foreach ( $guide_left as $name => $change ) {
	on( 'institution-guide', $change );
	$guide_kept[ $name ] = array( wpcredits_guide_frame_template( $institution_hierarchy ) === $institution_hierarchy, wpcredits_is_guide_frame_page() );
}
ck( 'anywhere else the Institution guide keeps the Documentation template it names', $guide_kept, array_fill_keys( array_keys( $guide_left ), array( true, false ) ) );

on( 'site-update', array( 'is_page' => false, 'is_single' => true, 'school_contact' => true, 'support' => $every ) );
ck( 'an update an institution reads is on single-institution-update, first, and is a page in the frame', array( wpcredits_update_frame_template( $single ), wpcredits_is_frame_page() ), array( array( 'single-institution-update.php', 'single-post-site-update.php', 'single-post.php', 'single.php' ), true ) );
on( 'site-update', array( 'is_page' => false, 'is_single' => true, 'school_contact' => true, 'support' => $all ) );
ck( 'and keeps its own templates while functions.php does not name the institution\'s updates', array( wpcredits_update_frame_template( $single ) === $single, wpcredits_is_update_frame_page() ), array( true, false ) );

// Every other page resolves what it resolved before the institution's names were added.
$before_after = array();
foreach ( array( 'before' => $all, 'after' => $every ) as $when => $support ) {
	$answers = array();
	on( 'administrator-dashboard', array( 'dashboard' => true, 'support' => $support ) );
	$answers['Administrator Dashboard in the frame'] = wpcredits_is_frame_page();
	on( 'sponsor-dashboard', array( 'sponsor_dashboard' => true, 'support' => $support ) );
	$answers['Sponsor Dashboard in the frame'] = wpcredits_is_frame_page();
	on( 'administrator-guide', array( 'support' => $support ) );
	$answers['Administrator guide'] = array( wpcredits_guide_frame_template( $hierarchy ), wpcredits_is_frame_page() );
	on( 'sponsor-guide', array( 'sponsor_reader' => true, 'support' => $support ) );
	$answers['Sponsor guide'] = array( wpcredits_guide_frame_template( $sponsor_hierarchy ), wpcredits_is_frame_page() );
	on( 'site-update', array( 'is_page' => false, 'is_single' => true, 'manager' => true, 'support' => $support ) );
	$answers['an Administrator\'s update'] = array( wpcredits_update_frame_template( $single ), wpcredits_is_frame_page() );
	on( 'site-update', array( 'is_page' => false, 'is_single' => true, 'member' => true, 'support' => $support ) );
	$answers['a sponsor\'s update'] = array( wpcredits_update_frame_template( $single ), wpcredits_is_frame_page() );
	on( 'mentor-guide', array( 'support' => $support ) );
	$answers['another page'] = array( wpcredits_guide_frame_template( $hierarchy ), wpcredits_is_frame_page() );
	$before_after[ $when ] = $answers;
}
ck( 'the Administrator\'s and the sponsor\'s pages answer as they did before the institution\'s names', $before_after['after'], $before_after['before'] );

$sponsor_dashboard = file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-sponsor-dashboard.html' );
$institution_page  = (string) file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-institution-dashboard.html' );
ck( 'the Institution Dashboard\'s, the Institution guide\'s and an institution\'s update\'s canvases are the sponsor\'s, with `sponsor` read as `institution` in the class only', array(
	$institution_page === str_replace( 'wpc-main--sponsor', 'wpc-main--institution', $sponsor_dashboard ),
	@file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-institution-guide.html' ) === str_replace( 'wpc-main--sponsor-guide', 'wpc-main--institution-guide', file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-sponsor-guide.html' ) ),
	@file_get_contents( WPCREDITS_THEME_DIR . 'templates/single-institution-update.html' ) === str_replace( 'wpc-main--sponsor-update', 'wpc-main--institution-update', file_get_contents( WPCREDITS_THEME_DIR . 'templates/single-sponsor-update.html' ) ),
), array( true, true, true ) );
ck( 'the Institution Dashboard\'s canvas holds the app\'s main area, the WordPress.org bar once, and no site header part or post title', array(
	false !== strpos( $institution_page, 'wpc-main wpc-main--app wpc-main--institution' ),
	substr_count( $institution_page, '"slug":"wporg-header"' ),
	false !== strpos( $institution_page, '"slug":"header"' ),
	false !== strpos( $institution_page, 'wp:post-title' ),
), array( true, 1, false, false ) );
ck( 'the body class wpc-institution-page still comes from the institution page test', 1 === preg_match( "/if \( wpcredits_is_institution_page\(\) \) \{\s*\\\$classes\[\] = 'wpc-institution-page';/", file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' ) ), true );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
