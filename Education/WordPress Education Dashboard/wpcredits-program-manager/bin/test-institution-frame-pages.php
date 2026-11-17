<?php
/**
 * The Institution guide and the institutions' Program updates in the Institution Dashboard's frame (Task 11).
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The Institution guide is drawn in the frame for a school's representative, a school's tutor and an
 *   Administrator, and only where the theme names `institution-guide`: with today's theme nothing changes.
 *   A logged-out reader, a sponsor's member and a reader with no school get the page as the theme draws it.
 * - An update institutions read is drawn in the institution frame for a school's member, and for an
 *   Administrator when the address names a school or the Administrator Dashboard does not list it. The
 *   sponsor is decided first, exactly as before, so a reader who is both a sponsor's member and a school's
 *   member reads an update both list in the sponsor's frame.
 * - No school leaks: an Administrator's links keep the school the address names; a member's links name no
 *   school, whatever the address says.
 * - Each view names the surface of the frame it draws in, and only `administrator`, `sponsor` or
 *   `institution`: the Administrator's and the Sponsor's stay what they were.
 *
 * The Institution Dashboard, its menu, the frame and the two views are the real ones.
 *
 * Run: php bin/test-institution-frame-pages.php
 *
 * @package WPCreditsProgramManager
 */

// phpcs:disable

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']    = array();
$GLOBALS['manage']  = false;
$GLOBALS['current'] = null;
$GLOBALS['support'] = false;
$GLOBALS['req']     = array();

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { return (string) $s; }
function wp_unslash( $v ) { return $v; }
function wp_slash( $v ) { return $v; }
function add_action() {}
function add_filter() {}
function apply_filters( $tag, $value ) { return $value; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_post_status( $id ) { return 'publish'; }
// The site's own Institution guide is published, so the menu's link to it is the frame's and keeps the school.
function get_page_by_path( $path, $output = null, $type = 'page' ) {
	if ( 'institution-guide' !== $path ) {
		return null;
	}
	$own              = new WP_Post();
	$own->ID          = 700;
	$own->post_name   = 'institution-guide';
	$own->post_type   = 'page';
	$own->post_status = 'publish';
	return $own;
}
function get_users( $args = array() ) { return array(); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function wp_logout_url( $to = '' ) { return 'https://example.test/wp-login.php?action=logout'; }
function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
function wp_parse_str( $s, &$out ) { parse_str( $s, $out ); }
function get_avatar( $id, $size = 96, $d = '', $alt = '', $args = array() ) { return '<img alt="">'; }
function wp_list_pluck( $list, $field ) { return array_map( static function ( $item ) use ( $field ) { return $item->$field; }, $list ); }

// An update's address is its own; anything else is the Institution Dashboard's page.
function get_permalink( $post = 0 ) { return $post instanceof WP_Post ? 'https://example.test/' . $post->post_name . '/' : 'https://example.test/institution-dashboard/'; }
function get_the_date( $format, $post ) { return 'November 1, 2026'; }
function get_post_time( $format, $gmt, $post ) { return '2026-11-01T09:00:00+00:00'; }

// The reader and the request.
function wp_get_current_user() { return $GLOBALS['current'] instanceof WP_User ? $GLOBALS['current'] : new WP_User( 0 ); }
function get_current_user_id() { return wp_get_current_user()->ID; }
function is_user_logged_in() { return wp_get_current_user()->exists(); }
function current_user_can( $c ) { return WPCPM_Roles::CAP_MANAGE === $c && (bool) $GLOBALS['manage']; }
function user_can( $user, $cap ) { return WPCPM_Roles::CAP_MANAGE === $cap && (bool) $GLOBALS['manage']; }
function get_theme_support( $feature ) { return 'wpcpm-dashboard-frame' === $feature ? $GLOBALS['support'] : false; }
function is_admin() { return false; }
function is_feed() { return false; }
function doing_filter( $hook ) { return false; }
function is_singular( $type = '' ) { return isset( $GLOBALS['req']['post'] ) && $GLOBALS['req']['post'] instanceof WP_Post && $type === $GLOBALS['req']['post']->post_type; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
function get_post() { return $GLOBALS['req']['post'] ?? null; }
function get_queried_object_id() { return get_post() instanceof WP_Post ? get_post()->ID : 0; }

$GLOBALS['umeta'] = array();
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['umeta'][ (int) $id ][ $key ] = $value; return true; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['umeta'][ (int) $id ][ $key ] ); return true; }

$GLOBALS['styles']  = array();
$GLOBALS['scripts'] = array();
function wp_style_is( $h, $list = 'enqueued' ) { return isset( $GLOBALS['styles'][ $h ] ); }
function wp_register_style( $h, $src, $deps = array(), $ver = false ) { $GLOBALS['styles'][ $h ] = true; }
function wp_enqueue_style( $h ) {}
function wp_script_is( $h, $list = 'enqueued' ) { return isset( $GLOBALS['scripts'][ $h ] ); }
function wp_register_script( $h, $src, $deps = array(), $ver = false, $footer = false ) { $GLOBALS['scripts'][ $h ] = true; }
function wp_enqueue_script( $h ) {}
function wp_localize_script( $h, $name, $data ) { return true; }

function add_query_arg( ...$a ) {
	$set = is_array( $a[0] ) ? $a[0] : array( $a[0] => $a[1] );
	$url = is_array( $a[0] ) ? (string) ( $a[1] ?? '' ) : (string) ( $a[2] ?? '' );
	$frag = '';
	if ( false !== ( $h = strpos( $url, '#' ) ) ) {
		$frag = substr( $url, $h );
		$url  = substr( $url, 0, $h );
	}
	$query = array();
	if ( false !== ( $q = strpos( $url, '?' ) ) ) {
		parse_str( substr( $url, $q + 1 ), $query );
		$query = array_map( function ( $v ) { return urlencode( (string) $v ); }, $query );
		$url   = substr( $url, 0, $q );
	}
	foreach ( $set as $k => $v ) {
		$query[ $k ] = $v;
	}
	$pairs = array();
	foreach ( $query as $k => $v ) {
		$pairs[] = $k . '=' . $v;
	}
	return $url . ( array() === $pairs ? '' : '?' . implode( '&', $pairs ) ) . $frag;
}

class WP_User {
	public $ID;
	public $user_login;
	public $display_name = 'Example Reader';
	public function __construct( $id = 0, $login = '' ) {
		$this->ID         = (int) $id;
		$this->user_login = (string) $login;
	}
	public function exists() { return $this->ID > 0; }
}

class WP_Post {
	public $ID = 0, $post_title = '', $post_name = '', $post_type = 'post', $post_parent = 0, $post_status = 'publish';
}

// The stand-ins the Institution Dashboard's menu asks, as in bin/test-institution-views.php.
$GLOBALS['allow']   = array();
$GLOBALS['members'] = array();
$GLOBALS['settled'] = array();

class WPCPM_Institution_Policy {
	const ACT_VIEW_ROSTER          = 'view_roster';
	const ACT_ADD_STUDENT          = 'add_student';
	const ACT_VIEW_SEMESTER_REPORT = 'view_semester_report';
	// The details card's own question, which the Overview's request row asks too (Task 25, fix round 1, B2).
	const ACT_EDIT_DETAILS         = 'edit_details';
	const ACT_AGREEMENT            = 'agreement';
	public static function subject_institution( $record ) { return array( 'kind' => 'institution', 'institution_ids' => array( (string) $record ) ); }
	public static function decide( $act, array $subject, $user = null ) {
		$login = $user instanceof WP_User ? $user->user_login : '';
		$ok    = ! empty( $GLOBALS['allow'][ $login ][ $act ] );
		// As the real policy gates a school whose agreement is not settled: an Administrator is never gated; a
		// representative is asked only about the agreement; a tutor is refused every question.
		$record = isset( $subject['institution_ids'][0] ) ? (string) $subject['institution_ids'][0] : '';
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) && ! WPCPM_Institution_Agreement::is_settled( $record ) ) {
			$ok = $ok && self::ACT_AGREEMENT === $act && WPCPM_Institution_Members::LEVEL_TUTOR !== WPCPM_Institution_Members::level_of( $user );
		}
		return array( 'allowed' => $ok, 'ground' => $ok ? 'member' : '', 'institution' => '', 'fields' => array(), 'why' => $ok ? '' : 'refused' );
	}
}

class WPCPM_Institution_Members {
	const LEVEL_TUTOR          = 'tutor';
	const LEVEL_REPRESENTATIVE = 'representative';
	const META_ACTIVE          = 'wpcpm_institution_active';
	public static function institution_of( $user = null ) {
		$user = null === $user ? wp_get_current_user() : $user;
		return $user instanceof WP_User && isset( $GLOBALS['members'][ $user->ID ] ) ? $GLOBALS['members'][ $user->ID ][0] : '';
	}
	public static function level_of( $user = null ) {
		$user = null === $user ? wp_get_current_user() : $user;
		return $user instanceof WP_User && isset( $GLOBALS['members'][ $user->ID ] ) && 'tutor' === $GLOBALS['members'][ $user->ID ][1] ? self::LEVEL_TUTOR : self::LEVEL_REPRESENTATIVE;
	}
	// The real rule: a member is somebody with a school, a representative or a tutor alike.
	public static function is_member( $user = null ) { return '' !== self::institution_of( $user ); }
}

class WPCPM_Institution_Agreement {
	const SUMMARY_NONE      = 'none';
	const SUMMARY_GENERATED = 'generated';
	const SUMMARY_SUBMITTED = 'submitted';
	const SUMMARY_RETURNED  = 'returned';
	const SUMMARY_REVOKED   = 'revoked';
	const SUMMARY_ACCEPTED  = 'accepted';
	const SUMMARY_ON_FILE   = 'on_file';
	public static function is_settled( $record ) { return in_array( (string) $record, $GLOBALS['settled'], true ); }
	public static function summary( $record ) { return array( 'state' => self::SUMMARY_SUBMITTED ); }
}

class WPCPM_Settings {
	public static function get_value( $key ) { return 'import_enabled' === $key ? true : null; }
}

class WPCPM_Sponsor_Event_List {
	const BACK_INSTITUTION = 'institution';
	public static function shows( WP_User $viewer ): bool { return false; }
	public static function render( $viewer, $back ) {}
}

class WPCPM_Institution_Panel { public static function render( ...$a ) {} }
class WPCPM_Institution_Details_Card { public static function render( ...$a ) {} }
class WPCPM_Institution_People { public static function render( ...$a ) {} }
class WPCPM_Institution_History { public static function render( ...$a ) { return ''; } }
class WPCPM_Semester_Report { public static function reports_of( $record ) { return array(); } }
class WPCPM_Institution_Import { const STATE_CREATING = 'creating'; public static function active_for( $record ) { return 0; } public static function batch( $id ) { return null; } }
class WPCPM_Institution_Request {
	const STATE_DONE     = 'done';
	const STATE_DECLINED = 'declined';
	public static function latest_details_outcome( $record ) { return array(); }
	public static function open_details_for( $record ) { return 0; }
}
class WPCPM_Institution_Assignment { public static function contact_of( $record ) { return null; } }
class WPCPM_Test_Institutions { public static function label( $name, $judged ) { return (string) $name; } }
class WPCPM_Handbook_Assistant {
	const INSTITUTION_GUIDE_PATH = 'institution-guide';
	public static function guides() { return array( 'institution' => array( 'url' => 'https://example.test/institution-guide/', 'slack' => 'https://example.org/slack' ) ); }
}

// Every icon the frame draws notes the surface named while it draws: what the frame reads inside it.
$GLOBALS['surfaces'] = array();
class WPCPM_Icons {
	public static function svg( $name, $size ) { $GLOBALS['surfaces'][ WPCPM_UI::surface() ] = true; return '<svg data-icon="' . $name . '"></svg>'; }
}

class WPCPM_Settings_Screen {
	const GUIDE_PAGE_PATH = 'administrator-guide';
}

// The other two dashboards' menus, standing in.
class WPCPM_Administrators_Dashboard {
	const VIEW_GUIDE   = 'guide';
	const VIEW_UPDATES = 'updates';
	public static function menu_args( $view ) { return array( 'page' => 'administrator-' . $view, 'label' => 'Administrator Dashboard', 'groups' => array(), 'help' => array() ); }
}
class WPCPM_Sponsors_Dashboard {
	const FRAME_PAGE   = 'sponsor';
	const VIEW_GUIDE   = 'guide';
	const VIEW_UPDATES = 'updates';
	public static function menu_args( $view ) { return array( 'page' => 'sponsor-' . $view, 'label' => 'Sponsor Dashboard', 'groups' => array(), 'help' => array() ); }
	public static function requested_record() { return current_user_can( WPCPM_Roles::CAP_MANAGE ) ? (string) ( $_GET['wpcpm_sponsor_view'] ?? '' ) : ''; }
	public static function keep_sponsor( $url, $record ) { return '' !== (string) $record ? add_query_arg( 'wpcpm_sponsor_view', $record, $url ) : $url; }
}
$GLOBALS['sponsor_members'] = array();
class WPCPM_Sponsor_Members {
	public static function is_member( $user = null, $record = '' ) { return in_array( wp_get_current_user()->ID, $GLOBALS['sponsor_members'], true ); }
}

// The updates, standing in: which audiences' lists hold which posts, and what each list was asked.
$GLOBALS['lists'] = array();
$GLOBALS['all_posts'] = array();
$GLOBALS['asked'] = array();
class WPCPM_Updates {
	public static function posts( $max, $audience ) {
		$GLOBALS['asked'][] = array( $max, $audience );
		return array_slice( array_values( array_filter( $GLOBALS['all_posts'], function ( $p ) use ( $audience ) { return WPCPM_Updates::lists( $p, $audience ); } ) ), 0, $max );
	}
	public static function lists( $post, $audience ) { return $post instanceof WP_Post && in_array( $post->ID, $GLOBALS['lists'][ $audience ] ?? array(), true ); }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster-view.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
WPCPM_UI::set_live( array() );
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-frame.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrator-guide-view.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrator-updates-view.php';

$GLOBALS['failed'] = 0;

function ck( $label, $actual, $expected ) {
	if ( $actual === $expected ) {
		echo "ok   $label\n";
		return;
	}

	$GLOBALS['failed']++;
	echo "FAIL $label\n     expected: " . var_export( $expected, true ) . "\n     actual:   " . var_export( $actual, true ) . "\n";
}

$rec   = 'recEXA00000000001';
$other = 'recEXA00000000002';
$held  = 'recEXA00000000003'; // A school whose agreement is not settled.

$GLOBALS['opts'][ WPCPM_Institutions_Dashboard::OPT_PAGE ] = 42;
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]     = array(
	'v'    => WPCPM_Institutions_Index::VERSION,
	'read' => 1,
	'rows' => array(
		$rec   => array( 'name' => 'Example Institute' ),
		$other => array( 'name' => 'Example College' ),
		$held  => array( 'name' => 'Example Academy' ),
	),
);
$GLOBALS['settled'] = array( $rec, $other );
$all                = array( 'view_roster' => true, 'add_student' => true, 'view_semester_report' => true, 'agreement' => true );
$GLOBALS['allow']   = array(
	'rep'   => $all,
	'tutor' => array( 'view_roster' => true, 'view_semester_report' => true ),
	'both'  => $all,
	'admin' => $all,
	'lrep'  => $all,
	'ltutor' => array( 'view_roster' => true, 'view_semester_report' => true ),
);

$rep     = new WP_User( 101, 'rep' );
$tutor   = new WP_User( 102, 'tutor' );
$admin   = new WP_User( 103, 'admin' );
$sponsor = new WP_User( 104, 'sponsor' );
$nobody  = new WP_User( 105, 'nobody' );
$both    = new WP_User( 106, 'both' );
$lrep    = new WP_User( 107, 'lrep' );
$ltutor  = new WP_User( 108, 'ltutor' );
$GLOBALS['members']         = array( 101 => array( $rec, 'representative' ), 102 => array( $rec, 'tutor' ), 106 => array( $rec, 'representative' ), 107 => array( $held, 'representative' ), 108 => array( $held, 'tutor' ) );
$GLOBALS['sponsor_members'] = array( 104, 106 );

$every_frame = array( array( 'administrator', 'administrator-guide', 'administrator-updates', 'sponsor', 'sponsor-guide', 'sponsor-updates', 'institution', 'institution-guide', 'institution-updates' ) );
$today       = array( array( 'administrator', 'administrator-guide', 'administrator-updates', 'sponsor', 'sponsor-guide', 'sponsor-updates', 'institution' ) );

/** Sign in as one reader (or nobody), with one address's arguments and one theme. */
function as_reader( $user, array $get = array(), $support = null ) {
	global $every_frame;
	WPCPM_Institutions_Dashboard::forget();
	$GLOBALS['current'] = $user;
	$GLOBALS['manage']  = $user instanceof WP_User && 'admin' === $user->user_login;
	$GLOBALS['support'] = null === $support ? $every_frame : $support;
	$_GET               = $get;
	$_POST              = array();
	foreach ( array( 'WPCPM_Administrator_Guide_View', 'WPCPM_Administrator_Updates_View' ) as $class ) {
		$drawn = new ReflectionProperty( $class, 'drawn' );
		if ( PHP_VERSION_ID < 80100 ) {
			$drawn->setAccessible( true );
		}
		$drawn->setValue( null, false );
	}
	$GLOBALS['surfaces'] = array();
	$GLOBALS['asked']    = array();
}

function page_at( $name, $parent = 0 ) {
	$post              = new WP_Post();
	$post->ID          = 700;
	$post->post_name   = $name;
	$post->post_title  = '';
	$post->post_type   = 'page';
	$post->post_parent = $parent;
	return $post;
}

function update_post( $id, $name ) {
	$post             = new WP_Post();
	$post->ID         = $id;
	$post->post_name  = $name;
	$post->post_title = 'Update ' . $id;
	return $post;
}

/** The addresses of every link in some markup. */
function hrefs( $html ) {
	preg_match_all( '/href="([^"]*)"/', $html, $m );
	return array_map( 'html_entity_decode', $m[1] );
}

/** The links that stay on the site: every one but the homepage and signing out. */
function site_links( $html ) {
	return array_values( array_filter( hrefs( $html ), function ( $u ) { return 0 === strpos( $u, 'https://example.test/' ) && 'https://example.test/' !== $u && false === strpos( $u, 'wp-login.php' ); } ) );
}

/** The labels of every menu item the frame draws, in order. */
function frame_labels( $html ) {
	preg_match_all( '#<span class="wpcpm-frame__label">([^<]*)#', $html, $m );
	return $m[1];
}

/** A private method of one of the views, called. */
function call_private( $class, $method, ...$args ) {
	$m = new ReflectionMethod( $class, $method );
	if ( PHP_VERSION_ID < 80100 ) {
		$m->setAccessible( true );
	}
	return $m->invoke( null, ...$args );
}

// The updates, set before anything is drawn, so the menus hold their Program updates item.
$for_schools  = update_post( 3001, 'for-schools' );       // Institutions only.
$for_all      = update_post( 3002, 'for-everyone' );      // Administrators and institutions.
$for_sponsors = update_post( 3003, 'for-sponsors' );      // Sponsors only.
$shared       = update_post( 3004, 'for-partners' );      // Sponsors and institutions.
$for_admins   = update_post( 3005, 'for-administrators' ); // Administrators only.
$GLOBALS['all_posts'] = array( $for_schools, $for_all, $for_sponsors, $shared, $for_admins );
$GLOBALS['lists']     = array(
	'institution'   => array( 3001, 3002, 3004 ),
	'administrator' => array( 3002, 3005 ),
	'sponsor'       => array( 3003, 3004 ),
);

/*
 * --------------------------------------------------------------------
 * Each view names only the three surfaces (the controller's ruling 2)
 * --------------------------------------------------------------------
 */

ck( 'the guide view names its dashboard\'s surface: the Administrator\'s, the Sponsor\'s, the Institution\'s, and the Administrator\'s for anything it does not know', method_exists( 'WPCPM_Administrator_Guide_View', 'surface_of' ) ? array(
	call_private( 'WPCPM_Administrator_Guide_View', 'surface_of', 'WPCPM_Administrators_Dashboard' ),
	call_private( 'WPCPM_Administrator_Guide_View', 'surface_of', 'WPCPM_Sponsors_Dashboard' ),
	call_private( 'WPCPM_Administrator_Guide_View', 'surface_of', 'WPCPM_Institutions_Dashboard' ),
	call_private( 'WPCPM_Administrator_Guide_View', 'surface_of', 'WPCPM_Example_Dashboard' ),
) : 'no surface_of()', array( 'administrator', 'sponsor', 'institution', 'administrator' ) );

ck( 'the updates view names its audience\'s surface: the Administrator\'s, the Sponsor\'s, the Institution\'s, and the Administrator\'s for anything it does not know', method_exists( 'WPCPM_Administrator_Updates_View', 'surface_of' ) ? array(
	call_private( 'WPCPM_Administrator_Updates_View', 'surface_of', 'administrator' ),
	call_private( 'WPCPM_Administrator_Updates_View', 'surface_of', 'sponsor' ),
	call_private( 'WPCPM_Administrator_Updates_View', 'surface_of', 'institution' ),
	call_private( 'WPCPM_Administrator_Updates_View', 'surface_of', 'example' ),
) : 'no surface_of()', array( 'administrator', 'sponsor', 'institution', 'administrator' ) );

/*
 * --------------------------------------------------------------------
 * The Institution guide: who reads it in the frame
 * --------------------------------------------------------------------
 */

$guide_page = page_at( 'institution-guide' );

ck( 'the Institution guide is a guide, at its page\'s path, a top-level page', array(
	WPCPM_Administrator_Guide_View::guide_of( $guide_page ),
	WPCPM_Administrator_Guide_View::guide_of( page_at( 'institution-guide', 12 ) ),
	WPCPM_Administrator_Guide_View::is_guide( $guide_page ),
), array( 'institution-guide', '', false ) );

$frames = array();
foreach ( array(
	'a representative'                   => $rep,
	'a tutor'                            => $tutor,
	'an Administrator'                   => $admin,
	'somebody who acts for a sponsor'    => $sponsor,
	'a reader with no school'            => $nobody,
	'a reader who is not logged in'      => null,
) as $name => $user ) {
	as_reader( $user );
	$frames[ $name ] = WPCPM_Administrator_Guide_View::frame_for( $guide_page );
}
ck( 'frame_for() of the Institution guide, theme naming it: the frame for a representative, a tutor and an Administrator; none for a sponsor\'s member, a reader with no school or a reader not logged in', $frames, array(
	'a representative'                => 'institution-guide',
	'a tutor'                         => 'institution-guide',
	'an Administrator'                => 'institution-guide',
	'somebody who acts for a sponsor' => '',
	'a reader with no school'         => '',
	'a reader who is not logged in'   => '',
) );

$plain = array();
foreach ( array( 'a representative' => $rep, 'a tutor' => $tutor, 'an Administrator' => $admin ) as $name => $user ) {
	as_reader( $user, array(), $today );
	$plain[ $name ] = array( WPCPM_Administrator_Guide_View::frame_for( $guide_page ) );
	as_reader( $user, array(), false );
	$plain[ $name ][] = WPCPM_Administrator_Guide_View::frame_for( $guide_page );
	$GLOBALS['req']   = array( 'post' => $guide_page );
	$plain[ $name ][] = WPCPM_Administrator_Guide_View::filter_content( '<p>x</p>' );
}
ck( 'with today\'s theme, which does not name institution-guide, and with no theme support, the guide is drawn outside the frame for everybody', $plain, array_fill_keys( array( 'a representative', 'a tutor', 'an Administrator' ), array( '', '', '<p>x</p>' ) ) );

/*
 * --------------------------------------------------------------------
 * The Institution guide drawn
 * --------------------------------------------------------------------
 */

$body = '<h2 id="start">Start</h2><p>One.</p><h2 id="students">Students</h2><p>Two.</p>';

as_reader( $rep );
$GLOBALS['req'] = array( 'post' => $guide_page );
$drawn          = WPCPM_Administrator_Guide_View::filter_content( $body );
$surfaces       = array_keys( $GLOBALS['surfaces'] );
ck( 'a representative\'s guide is drawn in the Institution Dashboard\'s frame, named institution while it draws, and the surface is left after', array(
	false !== strpos( $drawn, 'class="wpcpm-frame wpcpm-frame--institution-guide' ),
	$surfaces,
	WPCPM_UI::surface(),
), array( true, array( 'institution' ), '' ) );
ck( 'with the institution menu, the Institution guide current, the page\'s one H1 the guide\'s title, the On this page column, and the guide\'s body as it came', array(
	false !== strpos( $drawn, '<span class="wpcpm-frame__label">Your institution</span>' ),
	false !== strpos( $drawn, '<span class="wpcpm-frame__label">Enroll students</span>' ),
	1 === preg_match( '#<a class="wpcpm-frame__item is-current" href="https://example.test/institution-guide/" aria-current="page"><svg data-icon="book"></svg><span class="wpcpm-frame__label">Institution guide</span>#', $drawn ),
	substr_count( $drawn, 'aria-current="page"' ),
	substr_count( $drawn, '<h1' ),
	false !== strpos( $drawn, '<h1 class="wpcpm-head__title">Institution guide</h1>' ),
	false !== strpos( $drawn, '<span class="wpcpm-guide__contents-label">On this page</span>' ),
	false !== strpos( $drawn, '<div class="wpcpm-guide__body">' . $body . '</div>' ),
), array( true, true, true, 1, 1, true, true, true ) );
ck( 'a representative\'s guide names no school in any link', array( strpos( $drawn, 'wpcpm_institution_view' ), strpos( $drawn, $rec ) ), array( false, false ) );
ck( 'and its menu\'s Institution guide and Program updates links are the plain addresses (fix round 1, Minor 2)', array_values( array_intersect( hrefs( $drawn ), array( 'https://example.test/institution-guide/', 'https://example.test/for-schools/' ) ) ), array( 'https://example.test/for-schools/', 'https://example.test/institution-guide/' ) );

// Ruling 4: a crafted address gives a member nothing; their menu is their own school's.
as_reader( $rep, array( 'wpcpm_institution_view' => $other ) );
$crafted = WPCPM_Administrator_Guide_View::render( $body, '', 'institution-guide' );
as_reader( $rep );
$own = WPCPM_Administrator_Guide_View::render( $body, '', 'institution-guide' );
ck( 'a member whose address names another school is drawn the same guide, byte for byte, and no link names a school', array( $crafted === $own, strpos( $crafted, 'wpcpm_institution_view' ), strpos( $crafted, $other ) ), array( true, false, false ) );

as_reader( $tutor );
$tutor_drawn = WPCPM_Administrator_Guide_View::render( $body, '', 'institution-guide' );
ck( 'a tutor\'s guide is drawn in the same frame, with a tutor\'s menu (no Enroll students)', array(
	false !== strpos( $tutor_drawn, 'wpcpm-frame--institution-guide' ),
	false !== strpos( $tutor_drawn, '<span class="wpcpm-frame__label">Students</span>' ),
	false !== strpos( $tutor_drawn, 'Enroll students' ),
), array( true, true, false ) );

// Ruling 4: an Administrator's links keep the school the address names.
as_reader( $admin, array( 'wpcpm_institution_view' => $rec ) );
$admin_drawn = WPCPM_Administrator_Guide_View::render( $body, '', 'institution-guide' );
$to_page     = site_links( $admin_drawn );
$kept        = array_filter( $to_page, function ( $u ) use ( $rec ) { return false !== strpos( $u, 'wpcpm_institution_view=' . $rec ); } );
ck( 'an Administrator naming a school: the school\'s menu, For Administrators in it, every link on the site keeping the school, the Institution guide and Program updates included', array(
	false !== strpos( $admin_drawn, 'wpcpm-frame--institution-guide' ),
	false !== strpos( $admin_drawn, '<span class="wpcpm-frame__label">For Administrators</span>' ),
	count( $to_page ) > 0,
	count( $kept ) === count( $to_page ),
	in_array( 'https://example.test/institution-guide/?wpcpm_institution_view=' . $rec, $to_page, true ),
	in_array( 'https://example.test/for-schools/?wpcpm_institution_view=' . $rec, $to_page, true ),
	array_keys( $GLOBALS['surfaces'] ),
), array( true, true, true, true, true, true, array( 'institution' ) ) );

// Fix round 1, Minor 1: the readers the page refuses or locks. A tutor of a school whose agreement is not settled
// is refused every question, so the guide and an update draw the frame's fixed items alone: no view, no count, no
// link holding the school. A representative of that school gets the locked page's views, the Overview and the
// Agreement.
$fixed = array( 'Program updates', 'Institution guide', '#wpcredits on Slack', 'Collapse menu' );
as_reader( $ltutor );
$refused_guide = WPCPM_Administrator_Guide_View::render( $body, '', 'institution-guide' );
as_reader( $ltutor );
$refused_update = WPCPM_Administrator_Updates_View::render( '<p>News.</p>', $for_schools, 'institution' );
ck( 'a tutor of a school not settled: the guide and an update hold the frame\'s fixed items alone, no count, no link holding the school', array(
	frame_labels( $refused_guide ),
	frame_labels( $refused_update ),
	substr_count( $refused_guide . $refused_update, 'wpcpm-frame__badge' ),
	strpos( $refused_guide . $refused_update, $held ),
	strpos( $refused_guide . $refused_update, 'wpcpm_institution_view' ),
), array( $fixed, $fixed, 0, false, false ) );
as_reader( $lrep );
$locked_guide = WPCPM_Administrator_Guide_View::render( $body, '', 'institution-guide' );
as_reader( $lrep );
$locked_update = WPCPM_Administrator_Updates_View::render( '<p>News.</p>', $for_schools, 'institution' );
ck( 'a representative of that school: the Overview and the Agreement, then the fixed items, on the guide and on an update, no link holding the school', array(
	frame_labels( $locked_guide ),
	frame_labels( $locked_update ),
	strpos( $locked_guide . $locked_update, $held ),
), array( array_merge( array( 'Overview', 'Agreement' ), $fixed ), array_merge( array( 'Overview', 'Agreement' ), $fixed ), false ) );

// The Administrator's and the Sponsor's guides still name their own surfaces (ruling 1).
as_reader( $admin );
WPCPM_Administrator_Guide_View::render( $body, 'Guide', 'sponsor-guide' );
$sponsor_seen = array_keys( $GLOBALS['surfaces'] );
as_reader( $admin );
WPCPM_Administrator_Guide_View::render( $body, 'Guide' );
ck( 'the Sponsor guide still names sponsor, the Administrator guide administrator', array( $sponsor_seen, array_keys( $GLOBALS['surfaces'] ) ), array( array( 'sponsor' ), array( 'administrator' ) ) );

/*
 * --------------------------------------------------------------------
 * An update institutions read: whose frame
 * --------------------------------------------------------------------
 */

$whose = array();
foreach ( array(
	'a representative, an institutions\' update'                         => array( $rep, array(), null, $for_schools ),
	'a tutor, an institutions\' update'                                  => array( $tutor, array(), null, $for_schools ),
	'a representative, an update Administrators and institutions read'   => array( $rep, array(), null, $for_all ),
	'a representative, an Administrators\' update'                       => array( $rep, array(), null, $for_admins ),
	'a representative, an institutions\' update, the theme of today'     => array( $rep, array(), $today, $for_schools ),
	'an Administrator naming no school, an update both list'            => array( $admin, array(), null, $for_all ),
	'an Administrator naming a school, an update both list'             => array( $admin, array( 'wpcpm_institution_view' => $rec ), null, $for_all ),
	'an Administrator naming a school the index does not hold'          => array( $admin, array( 'wpcpm_institution_view' => 'recEXA00000000009' ), null, $for_all ),
	'an Administrator, an institutions\' update alone'                   => array( $admin, array(), null, $for_schools ),
	'an Administrator, an institutions\' update, the theme of today'     => array( $admin, array(), $today, $for_schools ),
	'an Administrator, a sponsors\' update'                              => array( $admin, array(), null, $for_sponsors ),
	'an Administrator naming a school, an update sponsors and institutions read' => array( $admin, array( 'wpcpm_institution_view' => $rec ), null, $shared ),
	'an Administrator naming no school, an update sponsors and institutions read' => array( $admin, array(), null, $shared ),
	'an Administrator naming a sponsor and a school, an update sponsors and institutions read' => array( $admin, array( 'wpcpm_sponsor_view' => 'recEXA00000000050', 'wpcpm_institution_view' => $rec ), null, $shared ),
	'a sponsor\'s member, an update sponsors and institutions read'      => array( $sponsor, array(), null, $shared ),
	'a sponsor\'s member, a sponsors\' update'                           => array( $sponsor, array(), null, $for_sponsors ),
	'a sponsor\'s member, an institutions\' update'                      => array( $sponsor, array(), null, $for_schools ),
	'a sponsor\'s and a school\'s member, an update both read'           => array( $both, array(), null, $shared ),
	'a sponsor\'s and a school\'s member, an institutions\' update'      => array( $both, array(), null, $for_schools ),
	'a reader with no school, an institutions\' update'                  => array( $nobody, array(), null, $for_schools ),
	'a reader not logged in, an institutions\' update'                   => array( null, array(), null, $for_schools ),
) as $name => $case ) {
	as_reader( $case[0], $case[1], $case[2] );
	$whose[ $name ] = WPCPM_Administrator_Updates_View::audience_of( $case[3] );
}
ck( 'audience_of(): a school\'s member reads institutions\' updates in the institution frame; an Administrator does when the address names a school or the Administrator Dashboard does not list it; the sponsor an address names is decided first, then the school it names, then the sponsor\'s unlisted update; nothing where the theme does not name institution-updates', $whose, array(
	'a representative, an institutions\' update'                         => 'institution',
	'a tutor, an institutions\' update'                                  => 'institution',
	'a representative, an update Administrators and institutions read'   => 'institution',
	'a representative, an Administrators\' update'                       => '',
	'a representative, an institutions\' update, the theme of today'     => '',
	'an Administrator naming no school, an update both list'            => 'administrator',
	'an Administrator naming a school, an update both list'             => 'institution',
	'an Administrator naming a school the index does not hold'          => 'administrator',
	'an Administrator, an institutions\' update alone'                   => 'institution',
	'an Administrator, an institutions\' update, the theme of today'     => '',
	'an Administrator, a sponsors\' update'                              => 'sponsor',
	'an Administrator naming a school, an update sponsors and institutions read' => 'institution',
	'an Administrator naming no school, an update sponsors and institutions read' => 'sponsor',
	'an Administrator naming a sponsor and a school, an update sponsors and institutions read' => 'sponsor',
	'a sponsor\'s member, an update sponsors and institutions read'      => 'sponsor',
	'a sponsor\'s member, a sponsors\' update'                           => 'sponsor',
	'a sponsor\'s member, an institutions\' update'                      => '',
	'a sponsor\'s and a school\'s member, an update both read'           => 'sponsor',
	'a sponsor\'s and a school\'s member, an institutions\' update'      => 'institution',
	'a reader with no school, an institutions\' update'                  => '',
	'a reader not logged in, an institutions\' update'                   => '',
) );

/*
 * --------------------------------------------------------------------
 * An update institutions read, drawn
 * --------------------------------------------------------------------
 */

as_reader( $rep );
$GLOBALS['req'] = array( 'post' => $for_schools );
$member_page    = WPCPM_Administrator_Updates_View::filter_content( '<p>News.</p>' );
ck( 'a member\'s update is drawn in the Institution Dashboard\'s frame, named institution while it draws, with the institutions\' list beside it', array(
	false !== strpos( $member_page, 'class="wpcpm-frame wpcpm-frame--institution-updates' ),
	array_keys( $GLOBALS['surfaces'] ),
	WPCPM_UI::surface(),
	in_array( array( 20, 'institution' ), $GLOBALS['asked'], true ),
	false !== strpos( $member_page, '<span class="wpcpm-frame__label">Your institution</span>' ),
	1 === preg_match( '#<a class="wpcpm-frame__item is-current" href="https://example.test/for-schools/" aria-current="page"><svg data-icon="inbox"></svg><span class="wpcpm-frame__label">Program updates</span>#', $member_page ),
	false !== strpos( $member_page, 'href="https://example.test/for-schools/" aria-current="page">Update 3001<' ),
	false !== strpos( $member_page, 'href="https://example.test/for-everyone/">Update 3002<' ),
	false !== strpos( $member_page, 'href="https://example.test/for-partners/">Update 3004<' ),
	false !== strpos( $member_page, 'for-administrators' ),
	false !== strpos( $member_page, '<div class="wpcpm-guide__body"><p>News.</p></div>' ),
), array( true, array( 'institution' ), '', true, true, true, true, true, true, false, true ) );

as_reader( $rep, array( 'wpcpm_institution_view' => $other ) );
$member_crafted = WPCPM_Administrator_Updates_View::render( '<p>News.</p>', $for_schools, 'institution' );
as_reader( $rep );
$member_own = WPCPM_Administrator_Updates_View::render( '<p>News.</p>', $for_schools, 'institution' );
ck( 'a member whose address names another school is drawn the same update, byte for byte, and no link names a school', array( $member_crafted === $member_own, strpos( $member_crafted, 'wpcpm_institution_view' ), strpos( $member_crafted, $other ) ), array( true, false, false ) );

as_reader( $admin, array( 'wpcpm_institution_view' => $rec ) );
$admin_page = WPCPM_Administrator_Updates_View::render( '<p>News.</p>', $for_all, 'institution' );
$to_page    = site_links( $admin_page );
$kept       = array_filter( $to_page, function ( $u ) use ( $rec ) { return false !== strpos( $u, 'wpcpm_institution_view=' . $rec ); } );
ck( 'an Administrator naming a school reads the update in its frame, every link to the dashboard and to an update keeping the school', array(
	false !== strpos( $admin_page, 'wpcpm-frame--institution-updates' ),
	array_keys( $GLOBALS['surfaces'] ),
	count( $to_page ) > 3,
	count( $kept ) === count( $to_page ),
	false !== strpos( $admin_page, 'href="https://example.test/for-everyone/?wpcpm_institution_view=' . $rec . '" aria-current="page">Update 3002<' ),
), array( true, array( 'institution' ), true, true, true ) );

// The Sponsor's and the Administrator's updates still draw in their own frames, named as before (ruling 1).
as_reader( $admin );
WPCPM_Administrator_Updates_View::render( '<p>x</p>', $for_sponsors, 'sponsor' );
$sponsor_seen = array( array_keys( $GLOBALS['surfaces'] ), $GLOBALS['asked'] );
as_reader( $admin );
WPCPM_Administrator_Updates_View::render( '<p>x</p>', $for_admins );
ck( 'the sponsors\' updates still name sponsor and list the sponsors\' updates; the Administrator\'s administrator and theirs', array( $sponsor_seen, array( array_keys( $GLOBALS['surfaces'] ), $GLOBALS['asked'] ) ), array( array( array( 'sponsor' ), array( array( 20, 'sponsor' ) ) ), array( array( 'administrator' ), array( array( 20, 'administrator' ) ) ) ) );

ck( 'nothing is stored', $GLOBALS['umeta'], array() );

$GLOBALS['current'] = null;
$_GET               = array();

echo "\n" . ( $GLOBALS['failed'] ? $GLOBALS['failed'] . ' failed' : 'all passed' ) . "\n";
exit( $GLOBALS['failed'] ? 1 : 0 );
