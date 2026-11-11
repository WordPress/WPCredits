<?php
/**
 * The program's updates in the Administrator Dashboard's frame (1.123.71).
 *
 * Plain PHP with stubs: a post is drawn in the frame only when it is an update the Administrator
 * Dashboard lists, read by an Administrator, in a theme that names these pages; once; with the post's
 * title as the one H1, its date, and the updates listed beside it, the one being read marked.
 *
 * Usage: php bin/test-administrator-updates.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function __( $s, $d = null ) { return $s; }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function add_filter() {}

$GLOBALS['req'] = array();
function req( $key ) { return $GLOBALS['req'][ $key ]; }
function is_admin() { return req( 'admin' ); }
function is_feed() { return req( 'feed' ); }
function doing_filter( $hook ) { return 'get_the_excerpt' === $hook && req( 'excerpt' ); }
function is_singular( $type = '' ) { return 'post' === $type && req( 'single_post' ); }
function in_the_loop() { return req( 'loop' ); }
function is_main_query() { return req( 'main' ); }
function get_post() { return req( 'post' ); }
function get_queried_object_id() { return isset( $GLOBALS['req']['queried'] ) ? $GLOBALS['req']['queried'] : ( req( 'post' ) instanceof WP_Post ? req( 'post' )->ID : 0 ); }
function wp_list_pluck( $list, $field ) { return array_map( static function ( $item ) use ( $field ) { return $item->$field; }, $list ); }
function is_user_logged_in() { return req( 'logged_in' ); }
function current_user_can( $cap ) { return 'wpcpm_manage_program' === $cap && req( 'manager' ); }
function get_theme_support( $feature ) { return 'wpcpm-dashboard-frame' === $feature ? req( 'support' ) : false; }
function get_permalink( $post ) { return 'https://example.test/' . $post->post_name . '/'; }
function get_the_date( $format, $post ) { return $post->date_words; }
function get_post_time( $format, $gmt, $post ) { return $post->post_date . 'T09:00:00+00:00'; }

$GLOBALS['enqueued'] = array();
function wp_enqueue_style( $h ) { $GLOBALS['enqueued'][] = 'style:' . $h; }
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = 'script:' . $h; }

class WP_Post {
	public $ID = 0, $post_title = '', $post_name = '', $post_type = 'post', $post_date = '', $date_words = '';
}

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}

class WPCPM_Icons {
	public static function svg( $name, $size ) { return '<svg data-icon="' . $name . '"></svg>'; }
}

// The updates, standing in: the Administrator Dashboard's list, and whether it holds a post.
class WPCPM_Updates {
	public static function posts( $max, $audience ) { $GLOBALS['asked'][] = array( $max, $audience ); return array_slice( 'sponsor' === $audience ? ( $GLOBALS['sponsor_updates'] ?? array() ) : $GLOBALS['updates'], 0, $max ); }
	public static function lists( $post, $audience ) {
		$list = 'sponsor' === $audience ? ( $GLOBALS['sponsor_updates'] ?? array() ) : ( 'administrator' === $audience ? $GLOBALS['updates'] : array() );
		return $post instanceof WP_Post && in_array( $post->ID, array_column( $list, 'ID' ), true );
	}
}

class WPCPM_Administrator_Guide_View {
	const STYLE   = 'wpcpm-administrator-guide';
	const SCRIPT  = 'wpcpm-administrator-guide';
	const LIST_ID = 'wpcpm-guide-list';
	public static function register_assets() { $GLOBALS['registered'] = true; }
}

class WPCPM_Dashboard_Frame {
	public static $opened = array();
	public static function owns_page( $page ) {
		$support = get_theme_support( 'wpcpm-dashboard-frame' );
		return is_array( $support ) && isset( $support[0] ) && in_array( $page, (array) $support[0], true );
	}
	public static function open( array $args ) { self::$opened[] = $args; echo '[open]'; }
	public static function close() { echo '[close]'; }
}

class WPCPM_Administrators_Dashboard {
	const VIEW_UPDATES = 'updates';
	public static function menu_args( $view ) { return array( 'page' => 'administrator-updates', 'from' => 'menu_args:' . $view ); }
}

// The Sponsor Dashboard's menu and who acts for a sponsor (1.123.78), standing in: a switch for the reader.
class WPCPM_Sponsors_Dashboard {
	const FRAME_PAGE   = 'sponsor';
	const VIEW_UPDATES = 'updates';
	public static function menu_args( $view ) { return array( 'page' => 'sponsor-updates', 'from' => 'sponsor menu_args:' . $view ); }
}

class WPCPM_Sponsor_Members {
	public static function is_member( $user = null, $record = '' ) { return ! empty( $GLOBALS['req']['member'] ); }
}

require_once __DIR__ . '/../includes/modules/class-wpcpm-administrator-updates-view.php';

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

function update( $id, $title, $name, $date, $words ) {
	$post             = new WP_Post();
	$post->ID         = $id;
	$post->post_title = $title;
	$post->post_name  = $name;
	$post->post_date  = $date;
	$post->date_words = $words;
	return $post;
}

$newest  = update( 1979, 'Site update, 24 Oct to 6 Nov 2026', 'site-update-2', '2026-11-05', 'November 5, 2026' );
$older   = update( 1677, 'Site update & <notes>', 'site-update-1', '2026-10-24', 'October 24, 2026' );
$outside = update( 900, 'A student announcement', 'student-news', '2026-10-01', 'October 1, 2026' );

$GLOBALS['updates'] = array( $newest, $older );

function reading( array $change = array() ) {
	$GLOBALS['req'] = array_merge(
		array(
			'admin'       => false,
			'feed'        => false,
			'excerpt'     => false,
			'single_post' => true,
			'loop'        => true,
			'main'        => true,
			'post'        => $GLOBALS['updates'][0],
			'logged_in'   => true,
			'manager'     => true,
			'support'     => array( array( 'administrator', 'administrator-guide', 'administrator-updates' ) ),
		),
		$change
	);
}

function fresh() {
	$drawn = new ReflectionProperty( 'WPCPM_Administrator_Updates_View', 'drawn' );
	if ( PHP_VERSION_ID < 80100 ) {
		$drawn->setAccessible( true );
	}
	$drawn->setValue( null, false );
	WPCPM_Dashboard_Frame::$opened = array();
	$GLOBALS['enqueued']           = array();
	$GLOBALS['asked']              = array();
}

/*
 * --------------------------------------------------------------------
 * Which posts, for whom
 * --------------------------------------------------------------------
 */

$cases = array(
	'an Administrator reading an update'          => array(),
	'in wp-admin'                                 => array( 'admin' => true ),
	'in a feed'                                   => array( 'feed' => true ),
	'while an excerpt is made'                    => array( 'excerpt' => true ),
	'on a page, not a post'                       => array( 'single_post' => false ),
	'outside the loop'                            => array( 'loop' => false ),
	'in a secondary query'                        => array( 'main' => false ),
	'a post the list does not hold'               => array( 'post' => $outside ),
	'another update a Query Loop draws inside it' => array( 'queried' => 4242 ),
	'a visitor who is not logged in'              => array( 'logged_in' => false ),
	'a reader who is not an Administrator'        => array( 'manager' => false ),
	'a theme that names only the guide'           => array( 'support' => array( array( 'administrator', 'administrator-guide' ) ) ),
	'a theme that names no page for the frame'    => array( 'support' => false ),
);
$wrapped = array();
foreach ( $cases as $name => $change ) {
	fresh();
	reading( $change );
	$wrapped[ $name ] = '<p>x</p>' !== WPCPM_Administrator_Updates_View::filter_content( '<p>x</p>' );
}
ck( 'a post is drawn in the frame only when it is an update the Administrator Dashboard lists, drawn in the main loop for a logged-in Administrator, in a theme that names these pages; never in wp-admin, a feed or an excerpt', array_keys( array_filter( $wrapped ) ), array( 'an Administrator reading an update' ) );

reading();
ck( 'draws() answers the theme the same, post by post and reader by reader', array(
	WPCPM_Administrator_Updates_View::draws( $newest ),
	WPCPM_Administrator_Updates_View::draws( $outside ),
	WPCPM_Administrator_Updates_View::draws( null ),
), array( true, false, false ) );
reading( array( 'manager' => false ) );
ck( 'and for a reader who is not an Administrator, no post', WPCPM_Administrator_Updates_View::draws( $newest ), false );

/*
 * --------------------------------------------------------------------
 * The page
 * --------------------------------------------------------------------
 */

fresh();
reading( array( 'post' => $older ) );
$page   = WPCPM_Administrator_Updates_View::filter_content( '<p>The update.</p>' );
$second = WPCPM_Administrator_Updates_View::filter_content( '<p>The update.</p>' );

ck( 'once a request, in the Administrator Dashboard\'s menu as it marks the updates, with the guide\'s sheet and script', array( count( WPCPM_Dashboard_Frame::$opened ), $second, WPCPM_Dashboard_Frame::$opened[0], $GLOBALS['enqueued'] ), array( 1, '<p>The update.</p>', array( 'page' => 'administrator-updates', 'from' => 'menu_args:updates' ), array( 'style:wpcpm-administrator-guide', 'script:wpcpm-administrator-guide' ) ) );

ck( 'the post\'s own title, escaped, as the one H1 in the serif head, its date under it, then the list, then the post', array(
	0 === strpos( $page, '[open]<div class="wpcpm-guide wpcpm-guide--updates" data-wpcpm-table-label="Table, scrolls sideways"><header class="wpcpm-head wpcpm-guide__head"><div class="wpcpm-head__copy"><h1 class="wpcpm-head__title">Site update &amp; &lt;notes&gt;</h1><p class="wpcpm-head__facts"><time datetime="2026-10-24T09:00:00+00:00">October 24, 2026</time></p></div></header><nav class="wpcpm-guide__contents"' ),
	false !== strpos( $page, '</ul></nav><div class="wpcpm-guide__body"><p>The update.</p></div></div>[close]' ),
	substr_count( $page, '<h1' ),
), array( true, true, 1 ) );

ck( 'the list is the Administrator Dashboard\'s own, newest first, up to twenty, headed Program updates, each with its date, the one being read marked as the page', array(
	$GLOBALS['asked'],
	false !== strpos( $page, '<span class="wpcpm-guide__contents-label">Program updates</span><button class="wpcpm-guide__contents-toggle" type="button" aria-expanded="false" aria-controls="wpcpm-guide-list" hidden>Program updates<svg data-icon="chevron-down"></svg></button>' ),
	false !== strpos( $page, '<li class="wpcpm-guide__entry"><a class="wpcpm-guide__link wpcpm-guide__link--post" href="https://example.test/site-update-2/">Site update, 24 Oct to 6 Nov 2026<span class="wpcpm-guide__date">November 5, 2026</span></a></li><li class="wpcpm-guide__entry"><a class="wpcpm-guide__link wpcpm-guide__link--post" href="https://example.test/site-update-1/" aria-current="page">Site update &amp; &lt;notes&gt;<span class="wpcpm-guide__date">October 24, 2026</span></a></li>' ),
	substr_count( $page, 'aria-current=' ),
), array( array( array( 20, 'administrator' ) ), true, true, 1 ) );

fresh();
$GLOBALS['updates'] = array( $newest );
reading();
$alone = WPCPM_Administrator_Updates_View::filter_content( '<p>Only one.</p>' );
ck( 'with one update alone there is no list beside it, and the post fills the page', array( false !== strpos( $alone, '<nav' ), false !== strpos( $alone, '</header><div class="wpcpm-guide__body"><p>Only one.</p></div>' ) ), array( false, true ) );

fresh();
$untitled             = update( 1980, '  ', 'untitled', '2026-11-06', 'November 6, 2026' );
$GLOBALS['updates']   = array( $untitled, $newest );
reading( array( 'post' => $untitled ) );
ck( 'an update with no title still has its one H1', false !== strpos( WPCPM_Administrator_Updates_View::filter_content( '' ), '<h1 class="wpcpm-head__title">Program update</h1>' ), true );

// The release's review (1.123.71): an update older than the newest twenty, opened from a saved link, takes
// the list's last place, so it is in the list and marked, and the list keeps its length and order.
fresh();
$many = array();
foreach ( range( 1, 25 ) as $n ) {
	$many[] = update( 3000 + $n, 'Update ' . $n, 'update-' . $n, '2026-01-01', 'January 1, 2026' );
}
$GLOBALS['updates'] = $many;
reading( array( 'post' => $many[22] ) );
$old_one = WPCPM_Administrator_Updates_View::render( '', $many[22] );
preg_match_all( '#wpcpm-guide__link--post" href="https://example.test/([^/]+)/"#', $old_one, $listed );
ck( 'an update older than the newest twenty takes the list\'s last place, marked', array( count( $listed[1] ), $listed[1][0], $listed[1][18], $listed[1][19], substr_count( $old_one, 'aria-current="page">Update 23<' ) ), array( 20, 'update-1', 'update-19', 'update-23', 1 ) );

/*
 * --------------------------------------------------------------------
 * A sponsor's updates, in the Sponsor Dashboard's frame (1.123.78)
 * --------------------------------------------------------------------
 */

$for_sponsors              = update( 2001, 'Welcome to your Sponsor Dashboard', 'welcome', '2026-09-08', 'September 8, 2026' );
$for_sponsors_too          = update( 2002, 'New for sponsors', 'new-for-sponsors', '2026-10-08', 'October 8, 2026' );
$GLOBALS['updates']        = array( $newest, $older );
$GLOBALS['sponsor_updates'] = array( $for_sponsors_too, $for_sponsors );
$all_frames                = array( array( 'administrator', 'administrator-guide', 'administrator-updates', 'sponsor', 'sponsor-guide', 'sponsor-updates' ) );

$whose = array();
foreach ( array(
	'an Administrator, an Administrator\'s update'             => array( array( 'support' => $all_frames ), $newest ),
	'an Administrator, an update for sponsors alone'           => array( array( 'support' => $all_frames ), $for_sponsors ),
	'somebody who acts for a sponsor, a sponsor\'s update'     => array( array( 'support' => $all_frames, 'manager' => false, 'member' => true ), $for_sponsors ),
	'somebody who acts for a sponsor, an Administrator\'s one' => array( array( 'support' => $all_frames, 'manager' => false, 'member' => true ), $newest ),
	'a sponsor\'s update under a theme that names only the Administrator\'s' => array( array( 'manager' => false, 'member' => true ), $for_sponsors ),
	'a reader who acts for no sponsor'                         => array( array( 'support' => $all_frames, 'manager' => false ), $for_sponsors ),
) as $name => $case ) {
	reading( $case[0] );
	$whose[ $name ] = WPCPM_Administrator_Updates_View::audience_of( $case[1] );
}
ck( 'audience_of() names the frame an update is drawn in for this reader: an Administrator\'s for the updates the Administrator Dashboard lists, a sponsor\'s for somebody who acts for a sponsor and the updates the Sponsor Dashboard lists, and none otherwise', $whose, array(
	'an Administrator, an Administrator\'s update'             => 'administrator',
	'an Administrator, an update for sponsors alone'           => '',
	'somebody who acts for a sponsor, a sponsor\'s update'     => 'sponsor',
	'somebody who acts for a sponsor, an Administrator\'s one' => '',
	'a sponsor\'s update under a theme that names only the Administrator\'s' => '',
	'a reader who acts for no sponsor'                         => '',
) );

fresh();
reading( array( 'post' => $for_sponsors, 'support' => $all_frames, 'manager' => false, 'member' => true ) );
$sponsor_page = WPCPM_Administrator_Updates_View::filter_content( '<p>Welcome.</p>' );
ck( 'a sponsor\'s update is drawn in the Sponsor Dashboard\'s menu, with the sponsors\' list beside it, the one being read marked', array(
	WPCPM_Dashboard_Frame::$opened[0],
	$GLOBALS['asked'],
	false !== strpos( $sponsor_page, 'href="https://example.test/new-for-sponsors/">New for sponsors<' ),
	false !== strpos( $sponsor_page, 'href="https://example.test/welcome/" aria-current="page">Welcome to your Sponsor Dashboard<' ),
	substr_count( $sponsor_page, '<h1' ),
), array( array( 'page' => 'sponsor-updates', 'from' => 'sponsor menu_args:updates' ), array( array( 20, 'sponsor' ) ), true, true, 1 ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
