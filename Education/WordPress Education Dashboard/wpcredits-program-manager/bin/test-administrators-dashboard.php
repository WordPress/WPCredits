<?php
/**
 * The Administrator Dashboard: every queue on one page, the counts above them, and the
 * decisions posted to the handlers that already exist.
 *
 * What each block pins, and why:
 *
 * - **Every card reads through the owning class.** The collaborators are stubbed to their
 *   contracts and record what was asked; the cards never query posts themselves.
 * - **The counts are the cards' counts.** The strip's eight numbers come from the same
 *   arrays the cards draw, so a tile and its card cannot disagree.
 * - **A decision posted here comes back here.** Every application and request form
 *   carries `wpcpm_return=dashboard`; the agreement forms return to the referer already.
 * - **Every link to an institution carries the switcher argument**, or a manager lands on
 *   their fallback institution and reads another school's report under this row's name.
 * - **The programs calculation reads only status, start, end, reports and mentor_name**
 *   off roster rows, and a finished student no longer says their track, so "finished this
 *   semester" is one number rather than one per track.
 * - **A viewer without the capability sees the refusal and no form.**
 *
 * Run from the plugin root:  php bin/test-administrators-dashboard.php
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']    = array();
$GLOBALS['umeta']   = array();
$GLOBALS['pmeta']   = array();
$GLOBALS['users']   = array();
$GLOBALS['posts']   = array();
$GLOBALS['manage']  = array();
$GLOBALS['editors'] = array();
$GLOBALS['uid']     = 0;
$GLOBALS['calls']   = array();
$GLOBALS['styles']  = array();
$GLOBALS['queried'] = 0;
$GLOBALS['pagenow'] = 'index.php';
$GLOBALS['ajax']    = false;
$GLOBALS['mentors'] = array();
$GLOBALS['next_id'] = 100;
$GLOBALS['transients'] = array();

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = strtolower( str_replace( ' ', '', $name ) ); $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	// post_date_gmt and post_modified_gmt are declared, not left dynamic: PHP deprecates an
	// undeclared property created by assignment, and seed_post() below sets both directly on
	// the stored post object (the copied block above never needed either field).
	public $ID = 0, $post_title = '', $post_name = '', $post_content = '', $post_type = 'page', $post_status = 'publish', $post_date_gmt = '', $post_modified_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function wp_kses( $s, $allowed ) { return (string) $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_action', $h, $p ); }
function add_filter( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_filter', $h, $p ); }
function add_shortcode( $tag, $c ) { $GLOBALS['calls'][] = array( 'add_shortcode', $tag ); }
function register_block_type( $dir, $args = array() ) { $GLOBALS['calls'][] = array( 'register_block_type', $dir ); return true; }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( (string) $u ) : parse_url( (string) $u, $c ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/\\' ); }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function human_time_diff( $a, $b = 0 ) { return '4 hours'; }
function wp_doing_ajax() { return (bool) $GLOBALS['ajax']; }
function is_multisite() { return false; }
function network_admin_url( $p = '' ) { return 'https://example.test/wp-admin/network/' . $p; }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['calls'][] = array( 'update_option', $k, $a ); return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
// Transients, and the two outbound requests the site-icon lookup makes. Both are recorded
// rather than performed: what is asserted is which host was asked and what was done with the
// answer, and a suite that reached the internet would be a suite that fails on a train.
function get_transient( $k ) {
	return array_key_exists( $k, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $k ] : false;
}
function set_transient( $k, $v, $ttl = 0 ) {
	$GLOBALS['transients'][ $k ] = $v;
	$GLOBALS['calls'][]          = array( 'set_transient', $k, $ttl );
	return true;
}
function wp_safe_remote_get( $url, $args = array() ) {
	$GLOBALS['fetched'][] = array( 'get', $url );
	return isset( $GLOBALS['http'][ $url ] ) ? $GLOBALS['http'][ $url ] : new WP_Error( 'http', 'nothing seeded' );
}
function wp_safe_remote_head( $url, $args = array() ) {
	$GLOBALS['fetched'][] = array( 'head', $url );
	return isset( $GLOBALS['http'][ $url ] ) ? $GLOBALS['http'][ $url ] : new WP_Error( 'http', 'nothing seeded' );
}
function wp_remote_retrieve_body( $r ) {
	return is_array( $r ) && isset( $r['body'] ) ? $r['body'] : '';
}
function wp_remote_retrieve_response_code( $r ) {
	return is_array( $r ) && isset( $r['code'] ) ? $r['code'] : 0;
}
function wp_remote_retrieve_header( $r, $name ) {
	return is_array( $r ) && isset( $r['headers'][ $name ] ) ? $r['headers'][ $name ] : '';
}

function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ( '_wpcpm_access_level' === $k ? 'public' : '' ); }
// Like WordPress: the access level is registered with a default of 'public', which is what a
// page with no row at all answers. `metadata_exists()` is how the two are told apart.
function metadata_exists( $type, $id, $k ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; $GLOBALS['calls'][] = array( 'update_post_meta', (int) $id, $k, $v ); return true; }

function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_status( $id ) { $p = get_post( $id ); return $p instanceof WP_Post ? $p->post_status : false; }
function get_permalink( $id = 0 ) { $p = get_post( $id ); return $p instanceof WP_Post ? 'https://example.test/' . $p->post_name . '/' : false; }
function get_page_by_path( $slug, $output = null, $type = 'page' ) {
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( $post->post_name === $slug ) { return $post; }
	}
	return null;
}
function wp_insert_post( $args, $wp_error = false ) {
	$post               = new WP_Post();
	$post->ID           = ++$GLOBALS['next_id'];
	$post->post_title   = $args['post_title'] ?? '';
	$post->post_name    = $args['post_name'] ?? '';
	$post->post_content = $args['post_content'] ?? '';
	$post->post_status  = $args['post_status'] ?? 'publish';
	$GLOBALS['posts'][ $post->ID ] = $post;
	$GLOBALS['calls'][]            = array( 'wp_insert_post', $post->post_name, $post->post_title, $wp_error );
	return $post->ID;
}
function wp_update_post( $args, $wp_error = false ) {
	$post = get_post( $args['ID'] ?? 0 );
	if ( $post instanceof WP_Post && isset( $args['post_title'] ) ) { $post->post_title = $args['post_title']; }
	$GLOBALS['calls'][] = array( 'wp_update_post', (int) ( $args['ID'] ?? 0 ) );
	return (int) ( $args['ID'] ?? 0 );
}
function get_queried_object_id() { return (int) $GLOBALS['queried']; }

function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
		if ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) { return $user; }
	}
	return false;
}
function get_users( $args = array() ) { return array_values( $GLOBALS['users'] ); }
function user_can( $u, $c ) {
	$id = is_object( $u ) ? $u->ID : (int) $u;
	if ( 'edit_posts' === $c ) { return in_array( $id, $GLOBALS['editors'], true ); }
	return in_array( $id, $GLOBALS['manage'], true );
}
function current_user_can( $c ) { return user_can( $GLOBALS['uid'], $c ); }

function wp_login_url( $to = '' ) { return 'https://example.test/wp-login.php?redirect_to=' . rawurlencode( $to ); }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $k, $v = '', $u = '' ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect: ' . $to ); }
function selected( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? " selected='selected'" : '';
	if ( $echo ) { echo $out; }
	return $out;
}
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}

function wp_style_is( $handle, $list = 'enqueued' ) {
	return isset( $GLOBALS['styles'][ $handle ] ) && ( 'registered' === $list || ! empty( $GLOBALS['styles'][ $handle ]['on'] ) );
}
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {
	$GLOBALS['styles'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'on' => false );
}
function wp_enqueue_style( $handle ) { if ( isset( $GLOBALS['styles'][ $handle ] ) ) { $GLOBALS['styles'][ $handle ]['on'] = true; } }
function wp_enqueue_script( $handle ) { $GLOBALS['scripts'][] = $handle; }
function wp_script_is( $handle, $list = 'enqueued' ) { return false; }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) {}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );


require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';

/* ---- the collaborators, stubbed to their contracts ----------------------- */

if ( ! function_exists( 'get_post_time' ) ) {
	function get_post_time( $f = 'U', $gmt = false, $post = null ) { return isset( $post->post_date_gmt ) ? (int) strtotime( $post->post_date_gmt . ' UTC' ) : 0; }
}
if ( ! function_exists( 'get_post_modified_time' ) ) {
	function get_post_modified_time( $f = 'U', $gmt = false, $post = null ) { return isset( $post->post_modified_gmt ) ? (int) strtotime( $post->post_modified_gmt . ' UTC' ) : 0; }
}
if ( ! function_exists( 'human_time_diff' ) ) {
	function human_time_diff( $from, $to = 0 ) { return '2 days'; }
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook ) { return isset( $GLOBALS['next'][ $hook ] ) ? $GLOBALS['next'][ $hook ] : false; }
}
if ( ! function_exists( 'get_user_by' ) ) {
	function get_user_by( $by, $v ) { return isset( $GLOBALS['users'][ (int) $v ] ) ? $GLOBALS['users'][ (int) $v ] : false; }
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $n ) { return (string) $n; }
}
if ( ! function_exists( '_n' ) ) {
	function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
}
// Neither is in the copied block above: the institutions dashboard suite never needed a
// date format or a nonce field, and this one does (a report's "approved on" line, and the
// revoked agreement's Reinstate form). Same shape as every other suite that seeds one
// (bin/test-institution-request.php, bin/test-semester-report.php): gmdate() so the suite
// never depends on the host's timezone, and a nonce field that records the action it was
// asked for so a form can be told apart without a real nonce ever being verified.
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null, $timezone = null ) { return gmdate( $format, null === $timestamp ? time() : (int) $timestamp ); }
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce-' . esc_attr( $action ) . '" />'; }
}

function seed_post( $id, $type, $title, $meta = array(), $date = '2026-09-01 10:00:00' ) {
	$post                    = new WP_Post();
	$post->ID                = (int) $id;
	$post->post_type         = $type;
	$post->post_status       = 'private';
	$post->post_title        = $title;
	$post->post_date_gmt     = $date;
	$post->post_modified_gmt = $date;
	$GLOBALS['posts'][ (int) $id ] = $post;
	$GLOBALS['pmeta'][ (int) $id ] = $meta;
	return $post;
}

class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $v ) ); }
	public static function tracked_statuses( $settings = null ) {
		return array(
			// 'SPAM' is never a real active status (WPCPM_Roster_Index::NEVER_SHOWN and this
			// list are disjoint on every real site), but it is added here so the fixture's SPAM
			// row would be counted toward in_progress if the NEVER_SHOWN skip in programs() ever
			// stopped running - which is exactly what "a SPAM row counts for nothing" has to be
			// able to catch (final review, Important 6). WPCPM_Program::track( 'SPAM' ), the real
			// class, still answers '' either way, so this cannot make it a track status too.
			'active' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation', 'SPAM' ),
			'past'   => array( 'Graduate', 'Dropped out' ),
		);
	}
	const OPT_LAST = 'wpcpm_mentors_last_sync'; const CRON_DAILY = 'wpcpm_mentors_daily';
	public static function progress() { return $GLOBALS['sync']['mentors']; }
}
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		$known = array( 'agreement_review_days' => 3, 'institution_active_stages' => array( 'Confirmed', 'Student' ), 'past_statuses' => array( 'Graduate', 'Dropped out' ) );
		return isset( $known[ $key ] ) ? $known[ $key ] : $fallback;
	}
	public static function is_connected() { return true; }
}
class WPCPM_Content_Access {
	const META_KEY = '_wpcpm_access_level';
}
class WPCPM_Two_Factor {
	public static function prompt( $user ) { $GLOBALS['prompted'][] = $user instanceof WP_User ? $user->ID : 0; }
}
class WPCPM_Flash {
	public static function set( $c, $v, $u = 0 ) { $GLOBALS['flash'][ $c ] = $v; }
	public static function take( $c, $u = 0 ) { $v = isset( $GLOBALS['flash'][ $c ] ) ? $GLOBALS['flash'][ $c ] : ''; unset( $GLOBALS['flash'][ $c ] ); return $v; }
}
class WPCPM_Students_Dashboard {
	public static function page_url() { return ''; }
	public static function is_student() { return false; }
}
class WPCPM_Mentors_Dashboard {
	const STYLE = 'wpcpm-mentor-dashboard';
	public static function page_url() { return ''; }
	public static function is_mentor() { return false; }
	/** The real one registers dashboard.css; here it need only be registerable as a dependency. */
	public static function register_assets() { wp_register_style( self::STYLE, 'dashboard.css', array(), '1' ); }
}
class WPCPM_Institutions_Dashboard {
	public static function page_url() { return 'https://example.test/institution-dashboard/'; }
	public static function is_member() { return false; }
}
class WPCPM_Institution_Roster {
	const ARG_VIEW = 'wpcpm_institution_view';
	public static function locked_today() { return isset( $GLOBALS['locked'] ) ? $GLOBALS['locked'] : array(); }
}
class WPCPM_Institutions_Index {
	public static function rows() { return isset( $GLOBALS['inst_rows'] ) ? $GLOBALS['inst_rows'] : array(); }
	public static function row( $id ) { return isset( $GLOBALS['inst_rows'][ (string) $id ] ) ? $GLOBALS['inst_rows'][ (string) $id ] : null; }
	public static function has( $id ) { return isset( $GLOBALS['inst_rows'][ (string) $id ] ); }
}
class WPCPM_Roster_Index {
	const NEVER_SHOWN = array( 'SPAM', 'Duplicated' );
	public static function read( $id ) { return isset( $GLOBALS['rosters'][ (string) $id ] ) ? $GLOBALS['rosters'][ (string) $id ] : array( 'v' => 4, 'read' => 0, 'rows' => array() ); }
	public static function rows( $id ) { return self::read( $id )['rows']; }
}
class WPCPM_Countries {
	public static function contact_of( $country ) { return 'recCOUNTRY0000001' === (string) $country ? 'Ana Manager' : ''; }
}
class WPCPM_Institution_Application {
	const STATE_NEW = 'new'; const STATE_HELD = 'held'; const STATE_INFO = 'info'; const STATE_REJECTED = 'rejected'; const STATE_SPAM = 'spam'; const STATE_APPROVED = 'approved';
	const META_STATE = '_wpcpm_app_state'; const META_COUNTRY = '_wpcpm_app_country'; const META_COUNTRY_NAME = '_wpcpm_app_country_name';
	public static function applications( $states ) {
		$GLOBALS['asked'][] = array( 'applications', (array) $states );
		$out = array();
		foreach ( $GLOBALS['posts'] as $post ) {
			if ( 'wpcpm_inst_app' === $post->post_type && in_array( get_post_meta( $post->ID, self::META_STATE, true ), (array) $states, true ) ) { $out[] = $post; }
		}
		return $out;
	}
	public static function reference( $id ) { return sprintf( 'APP-2026-%04d', (int) $id ); }
}
class WPCPM_Institutions {
	const FLASH = 'institutions';
	const ACTION_APPROVE = 'wpcpm_app_approve';
	public static function open_states() { return array( 'new', 'held', 'info' ); }
	public static function application_state( WP_Post $post ) { return (string) get_post_meta( $post->ID, '_wpcpm_app_state', true ); }
	public static function application_reference( WP_Post $post ) { return WPCPM_Institution_Application::reference( $post->ID ); }
	public static function country_name( $id, $stored ) { return '' !== $stored ? $stored : ( 'recCOUNTRY0000001' === (string) $id ? 'Poland' : '' ); }
	public static function queue_messages() { return array( 'app-approved' => array( 'success', 'The application is approved.' ) ); }
	public function render_application_answers( WP_Post $post ) { echo '<table class="wpcpm-app-answers" data-app="' . (int) $post->ID . '"></table>'; }
	public function render_application_actions( WP_Post $post, $state, $return = '' ) {
		echo '<form class="wpcpm-app-action" method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Working">';
		echo '<input type="hidden" name="action" value="' . self::ACTION_APPROVE . '" /><input type="hidden" name="wpcpm_application" value="' . (int) $post->ID . '" />';
		WPCPM_Return::field( (string) $return, 'applications' );
		echo '<button type="submit" class="button button-primary">Approve</button></form>';
		// The real class's render_decision_form() also draws confirm-guarded decisions (Reject
		// as spam, Delete for good); mirrored here for the 'new' state only, so the applications
		// card's HTML really does carry an onsubmit="return confirm(" for the guard to yield to.
		if ( 'new' === $state ) {
			// Attribute order as the real render_decision_form() prints it: the guard's two
			// attributes first, the confirm appended last.
			echo '<form class="wpcpm-app-action" method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Working" onsubmit="return confirm(&#039;Mark as spam?&#039;);">';
			echo '<input type="hidden" name="action" value="wpcpm_app_spam" /><input type="hidden" name="wpcpm_application" value="' . (int) $post->ID . '" />';
			WPCPM_Return::field( (string) $return, 'applications' );
			echo '<button type="submit" class="button">Mark as spam</button></form>';
		}
	}
}
class WPCPM_Modules {
	public static function get( $id ) { return 'institutions' === $id ? new WPCPM_Institutions() : null; }
}
class WPCPM_Institution_Agreement {
	const POST_TYPE = 'wpcpm_agreement'; const META_INSTITUTION = '_wpcpm_agr_institution'; const META_STATE = '_wpcpm_agr_state'; const META_NOTE = '_wpcpm_agr_note';
	const STATE_SUBMITTED = 'submitted'; const STATE_RETURNED = 'returned'; const STATE_REVOKED = 'revoked';
	const ACTION_REINSTATE = 'wpcpm_agreement_reinstate';
	public static function awaiting_review( $limit = 200 ) { return isset( $GLOBALS['agr']['submitted'] ) ? $GLOBALS['agr']['submitted'] : array(); }
	public static function in_state( $state, $limit = 50 ) { return isset( $GLOBALS['agr'][ $state ] ) ? $GLOBALS['agr'][ $state ] : array(); }
	public static function summary( $record ) { return array( 'state' => isset( $GLOBALS['agr_summary'][ $record ] ) ? $GLOBALS['agr_summary'][ $record ] : 'none' ); }
}
class WPCPM_Institution_Panel {
	public static function render_review( $post_id ) { $GLOBALS['reviews'][] = (int) $post_id; echo '<section class="wpcpm-review" id="wpcpm-review-' . (int) $post_id . '"><form class="wpcpm-review__form wpcpm-review__form--accept"></form></section>'; }
	public static function messages() { return array( 'agreement-accepted' => array( 'success', 'Accepted.' ) ); }
}
class WPCPM_Semester_Report {
	const STATE_DRAFT = 'draft'; const STATE_APPROVED = 'approved';
	public static function queue() { return isset( $GLOBALS['reports']['queue'] ) ? $GLOBALS['reports']['queue'] : array(); }
	public static function due( $today ) { $GLOBALS['asked'][] = array( 'due', $today ); return isset( $GLOBALS['reports']['due'] ) ? $GLOBALS['reports']['due'] : array(); }
	public static function approved_since( $ts ) { $GLOBALS['asked'][] = array( 'approved_since', (int) $ts ); return isset( $GLOBALS['reports']['approved'] ) ? $GLOBALS['reports']['approved'] : array(); }
	public static function reports_of( $record ) { return isset( $GLOBALS['reports_of'][ $record ] ) ? $GLOBALS['reports_of'][ $record ] : array(); }
	public static function state( WP_Post $post ) { return (string) get_post_meta( $post->ID, '_wpcpm_report_state', true ); }
}
class WPCPM_Semester_Report_Screen {
	const ACTION_DRAFT = 'wpcpm_report_draft';
	const FLASH        = 'institution_report';
	public static function report_url( $cohort ) { return 'https://example.test/institution-dashboard/?wpcpm_report=' . $cohort . '#wpcpm-report'; }
	public static function render_draft_form( $record, $cohort, $label = '', $return = '' ) {
		echo '<form class="wpcpm-report-card__generate" data-wpcpm-once><input type="hidden" name="action" value="' . self::ACTION_DRAFT . '" /><input type="hidden" name="institution" value="' . esc_attr( $record ) . '" /><input type="hidden" name="cohort" value="' . esc_attr( $cohort ) . '" />';
		if ( class_exists( 'WPCPM_Return' ) ) {
			WPCPM_Return::field( (string) $return, 'reports' );
		}
		echo '</form>';
	}
	// Mirrors the real class's map just enough for the dashboard's render_report_message() to
	// be exercised: 'refused' is the one status a member or a stranger can actually provoke.
	public static function message_for( array $flash ) {
		$said = array( 'refused' => 'That is not something you can do here.' );
		$key  = ! empty( $flash['status'] ) ? (string) $flash['status'] : '';
		if ( '' === $key ) { return array( '', '' ); }
		return array( $key, isset( $said[ $key ] ) ? $said[ $key ] : 'Something about that report could not be read.' );
	}
}
class WPCPM_Institution_Request {
	const STATE_OPEN = 'open'; const STATE_DONE = 'done'; const STATE_DECLINED = 'declined'; const OVERDUE_DAYS = 14;
	const ACTION_RESOLVE = 'wpcpm_resolve_request';
	public static function open_requests( $limit = 20 ) { return isset( $GLOBALS['requests']['open'] ) ? $GLOBALS['requests']['open'] : array(); }
	public static function closed_requests( $limit = 20 ) { return isset( $GLOBALS['requests']['closed'] ) ? $GLOBALS['requests']['closed'] : array(); }
	public static function facts( $id ) { return isset( $GLOBALS['facts'][ (int) $id ] ) ? $GLOBALS['facts'][ (int) $id ] : array(); }
	public static function render_decisions( $id, $return = '' ) {
		echo '<form class="wpcpm-request__decide" method="post" data-wpcpm-once data-wpcpm-busy="Saving"><input type="hidden" name="action" value="' . self::ACTION_RESOLVE . '" /><input type="hidden" name="request" value="' . (int) $id . '" />';
		WPCPM_Return::field( (string) $return, 'requests' );
		echo '<button type="submit" name="state" value="done">Mark as handled</button></form>';
	}
	public static function messages() { return array( 'request-done' => array( 'success', 'Handled.' ) ); }
}
class WPCPM_Students_Sync {
	const OPT_LAST = 'wpcpm_students_last_sync'; const CRON_AUTO = 'wpcpm_students_daily';
	public static function progress() { return $GLOBALS['sync']['students']; }
}
class WPCPM_Institutions_Sync {
	const CRON_DAILY = 'wpcpm_institutions_sync_daily';
	public static function progress() { return $GLOBALS['sync']['institutions']; }
	public static function last_read() { return (int) get_option( 'wpcpm_institutions_last_sync', 0 ); }
}
class WPCPM_Private_Files {
	public static function probe_result() { return isset( $GLOBALS['probe'] ) ? $GLOBALS['probe'] : null; }
	public static function verdict( array $r ) { return ! empty( $r['blocked'] ) ? 'blocked' : ( $r['status'] >= 200 && $r['status'] < 300 ? 'served' : 'unknown' ); }
}
class WPCPM_Mail {
	public static function log() { return isset( $GLOBALS['mail_log'] ) ? $GLOBALS['mail_log'] : array(); }
	public static function run() { return isset( $GLOBALS['invite_run'] ) ? $GLOBALS['invite_run'] : array(); }
	public static function queued() { return isset( $GLOBALS['invite_queued'] ) ? (int) $GLOBALS['invite_queued'] : 0; }
}
class WPCPM_Handbook_Assistant {
	public static function render_resources( $audience = '', $extra = '' ) { $GLOBALS['resources'][] = $audience; return '<section class="wpcpm-handbook__resources" data-audience="' . esc_attr( $audience ) . '"></section>'; }
}
class WPCPM_Sponsor_Tools {
	const AUDIENCE_MANAGERS = 'managers';
	public static function render( $audience, $viewer ) { $GLOBALS['tools'][] = array( $audience, $viewer instanceof WP_User ? $viewer->ID : 0 ); echo '<section class="wpcpm-student__section wpcpm-tools" id="wpcpm-tools"></section>'; }
}
abstract class WPCPM_Sync_Module {
	public static function sync_messages() { return array( 'started' => array( 'success', 'Sync started.' ) ); }
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-cards.php';

$fail  = 0;
$total = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $total;
	$total++;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) { echo '       exp: ' . var_export( $expected, true ) . "\n       got: " . var_export( $actual, true ) . "\n"; }
}
function has( $h, $n ) { return false !== strpos( (string) $h, (string) $n ); }
function capture( $fn ) { ob_start(); call_user_func( $fn ); return (string) ob_get_clean(); }

/* ---- the fixture ---------------------------------------------------------- */

$A = 'recINSTA000000001';
$B = 'recINSTB000000002';
$C = 'recINSTC000000003';

$GLOBALS['manage']  = array( 3 );
$GLOBALS['uid']     = 3;
$GLOBALS['users']   = array( 3 => new WP_User( 3, 'Manager Three', 'maciej@a8c.com' ), 21 => new WP_User( 21, 'Rep One', 'maciej@a8c.com' ) );
$GLOBALS['posts']   = array();
$GLOBALS['pmeta']   = array();
$GLOBALS['asked']   = array();
$GLOBALS['reviews'] = array();
$GLOBALS['next']    = array( 'wpcpm_students_daily' => 1757100000 );
$GLOBALS['opts']['wpcpm_students_last_sync']     = 1757000000;
$GLOBALS['opts']['wpcpm_mentors_last_sync']      = 1756990000;
$GLOBALS['opts']['wpcpm_institutions_last_sync'] = 1756980000;

$GLOBALS['inst_rows'] = array(
	$A => array( 'record_id' => $A, 'name' => 'Uniwersytet Alpha', 'stage' => 'Confirmed', 'country' => 'recCOUNTRY0000001', 'country_name' => 'Poland' ),
	$B => array( 'record_id' => $B, 'name' => 'Universidad Beta ', 'stage' => 'Confirmed', 'country' => '', 'country_name' => '' ),
	$C => array( 'record_id' => $C, 'name' => 'Instituto Gamma', 'stage' => 'Confirmed', 'country' => '', 'country_name' => '' ),
);
// The roster dates below are derived from the current cohort's own range rather than written
// as absolute 2026 dates, so the signed-up and finished checks stay true on 1 January 2027 and
// every year after (final review, Important 5) - the same reasoning the approved_since check
// below already relies on. A date that must fall inside the cohort is the range's start plus a
// few days, or its end minus a few days; a date that must fall outside it is a year before the
// range starts, which is always a different half regardless of which half is current.
$range         = WPCPM_Cohort::range( WPCPM_Cohort::current() );
$in_early      = gmdate( 'Y-m-d', strtotime( $range['from'] . ' +5 days' ) );
$in_early_dev  = gmdate( 'Y-m-d', strtotime( $range['from'] . ' +10 days' ) );
$in_early_spam = gmdate( 'Y-m-d', strtotime( $range['from'] . ' +7 days' ) );
$in_dropped_at = gmdate( 'Y-m-d', strtotime( $range['from'] . ' +12 days' ) );
$in_late       = gmdate( 'Y-m-d', strtotime( $range['to'] . ' -10 days' ) );
$in_dropped_to = gmdate( 'Y-m-d', strtotime( $range['to'] . ' -6 days' ) );
$outside       = gmdate( 'Y-m-d', strtotime( $range['from'] . ' -1 year' ) );
$outside_start = gmdate( 'Y-m-d', strtotime( $range['from'] . ' -1 year -140 days' ) );
$outside_end   = gmdate( 'Y-m-d', strtotime( $range['from'] . ' -1 year -20 days' ) );

$GLOBALS['rosters'] = array(
	$A => array( 'v' => 4, 'read' => 1756900000, 'rows' => array(
		array( 'record_id' => 'recS0000000000001', 'status' => 'In Sensei', 'start' => $in_early, 'end' => '2026-12-15', 'reports' => array( 'recR1' ), 'mentor_name' => 'Mentor One' ),
		array( 'record_id' => 'recS0000000000002', 'status' => 'Developer Track', 'start' => $in_early_dev, 'end' => '2026-11-30', 'reports' => array(), 'mentor_name' => '' ),
		array( 'record_id' => 'recS0000000000003', 'status' => 'In Sensei 50h', 'start' => $outside, 'end' => '', 'reports' => array( 'recR2' ), 'mentor_name' => 'Mentor One' ),
		array( 'record_id' => 'recS0000000000004', 'status' => 'Graduate', 'start' => '2026-02-01', 'end' => $in_late, 'reports' => array( 'recR3' ), 'mentor_name' => 'Mentor Two' ),
		array( 'record_id' => 'recS0000000000005', 'status' => 'SPAM', 'start' => $in_early_spam, 'end' => '', 'reports' => array(), 'mentor_name' => '' ),
		// Dropped out, ending inside the current cohort: "finished this semester" must count
		// graduates only (final review, Important 4), so this row proves neither in_progress
		// nor finished moves for it.
		array( 'record_id' => 'recS0000000000007', 'status' => 'Dropped out', 'start' => $in_dropped_at, 'end' => $in_dropped_to, 'reports' => array(), 'mentor_name' => '' ),
	) ),
	$B => array( 'v' => 4, 'read' => 1756800000, 'rows' => array(
		// Graduated outside the current cohort, always: 'finished' must stay attributable to
		// institution A's row alone (finished === 1), on any date this suite runs.
		array( 'record_id' => 'recS0000000000006', 'status' => 'Graduate', 'start' => $outside_start, 'end' => $outside_end, 'reports' => array( 'recR4' ), 'mentor_name' => 'Mentor Two' ),
	) ),
);
$GLOBALS['agr_summary'] = array( $A => 'accepted', $B => 'submitted' );
seed_post( 701, 'wpcpm_inst_report', 'Report A 2026-H1', array( '_wpcpm_report_state' => 'draft', '_wpcpm_report_institution' => $A, '_wpcpm_report_cohort' => '2026-H1' ) );
$GLOBALS['reports_of'] = array( $A => array( '2026-H1' => $GLOBALS['posts'][701] ) );

seed_post( 501, 'wpcpm_inst_app', 'Uni Nueva', array( '_wpcpm_app_state' => 'new', '_wpcpm_app_country' => 'recCOUNTRY0000001', '_wpcpm_app_country_name' => 'Poland' ), '2026-09-01 08:00:00' );
seed_post( 502, 'wpcpm_inst_app', 'Uni Held', array( '_wpcpm_app_state' => 'held', '_wpcpm_app_country' => '', '_wpcpm_app_country_name' => '' ), '2026-09-02 08:00:00' );
seed_post( 503, 'wpcpm_inst_app', 'Uni Rejected', array( '_wpcpm_app_state' => 'rejected', '_wpcpm_app_country' => '', '_wpcpm_app_country_name' => '' ), '2026-08-01 08:00:00' );

seed_post( 601, 'wpcpm_agreement', 'Agreement B', array( '_wpcpm_agr_institution' => $B, '_wpcpm_agr_state' => 'submitted' ), '2026-08-20 08:00:00' );
seed_post( 602, 'wpcpm_agreement', 'Agreement C', array( '_wpcpm_agr_institution' => $C, '_wpcpm_agr_state' => 'submitted' ), gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
seed_post( 603, 'wpcpm_agreement', 'Agreement A returned', array( '_wpcpm_agr_institution' => $A, '_wpcpm_agr_state' => 'returned', '_wpcpm_agr_note' => 'Page two is missing.' ), '2026-08-25 08:00:00' );
seed_post( 604, 'wpcpm_agreement', 'Agreement C revoked', array( '_wpcpm_agr_institution' => $C, '_wpcpm_agr_state' => 'revoked', '_wpcpm_agr_note' => 'Withdrawn by the rector.' ), '2026-08-26 08:00:00' );
$GLOBALS['agr'] = array( 'submitted' => array( 601, 602 ), 'returned' => array( 603 ), 'revoked' => array( 604 ) );

$GLOBALS['reports'] = array(
	'queue'    => array( array( 'post_id' => 701, 'institution' => $A, 'cohort' => '2026-H1', 'generated' => 1756500000, 'origin' => 'auto', 'in_progress' => 1, 'age_days' => 5, 'approved_at' => 0, 'approved_by' => 0 ) ),
	'due'      => array( array( 'institution' => $B, 'cohort' => '2025-H2', 'in_progress' => 0, 'window_end' => '2025-12-31' ) ),
	'approved' => array( array( 'post_id' => 702, 'institution' => $C, 'cohort' => '2026-H1', 'generated' => 1756000000, 'origin' => 'manager', 'in_progress' => 0, 'age_days' => 9, 'approved_at' => 1756600000, 'approved_by' => 3 ) ),
);

$GLOBALS['requests'] = array( 'open' => array( 801, 802 ), 'closed' => array( 803 ) );
$GLOBALS['facts'] = array(
	801 => array( 'id' => 801, 'kind' => 'mentor', 'kind_label' => 'A mentor is wanted', 'state' => 'open', 'institution' => $A, 'institution_name' => 'Uniwersytet Alpha', 'note' => 'Two students without a mentor.', 'at' => time() - 20 * DAY_IN_SECONDS, 'overdue' => true, 'actor' => 21, 'actor_name' => 'Rep One' ),
	802 => array( 'id' => 802, 'kind' => 'mentor', 'kind_label' => 'A mentor is wanted', 'state' => 'open', 'institution' => $B, 'institution_name' => 'Universidad Beta', 'note' => '', 'at' => time() - DAY_IN_SECONDS, 'overdue' => false, 'actor' => 21, 'actor_name' => 'Rep One' ),
	803 => array( 'id' => 803, 'kind' => 'mentor', 'kind_label' => 'A mentor is wanted', 'state' => 'done', 'institution' => $A, 'institution_name' => 'Uniwersytet Alpha', 'note' => 'Handled.', 'at' => time() - 30 * DAY_IN_SECONDS, 'overdue' => false, 'actor' => 21, 'actor_name' => 'Rep One' ),
);
$GLOBALS['locked'] = array( new WP_User( 21, 'Rep One', 'maciej@a8c.com' ) );
$GLOBALS['sync'] = array(
	'students'     => array( 'running' => false, 'phase' => 'done', 'label' => 'Done', 'error' => '', 'elapsed' => 0 ),
	'mentors'      => array( 'running' => true, 'phase' => 'reports', 'label' => 'Reading reports', 'error' => '', 'elapsed' => 40 ),
	'institutions' => array( 'running' => false, 'phase' => 'done', 'label' => 'Done', 'error' => 'HTTP 429 from Airtable <b>x</b>', 'elapsed' => 0 ),
);
$GLOBALS['probe']         = array( 'status' => 403, 'time' => 1756700000, 'blocked' => true, 'error' => '', 'control_status' => 200, 'encrypted' => true );
$GLOBALS['mail_log']      = array( array( 'time' => 1756990000, 'to' => 'm***@a8c.com', 'context' => 'report-drafted', 'sent' => true ) );
$GLOBALS['invite_run']    = array( 'total' => 5, 'started' => 1756990000, 'finished' => 0 );
$GLOBALS['invite_queued'] = 2;

/* ---- collect() and counts() ---------------------------------------------- */

echo "=== The data is read once, through the owners ===\n";

$data = WPCPM_Administrators_Cards::collect();
ck( 'open applications are the three open states, through applications()', in_array( array( 'applications', array( 'new', 'held', 'info' ) ), $GLOBALS['asked'], true ), true );
ck( 'and the closed list is rejected and spam', in_array( array( 'applications', array( 'rejected', 'spam' ) ), $GLOBALS['asked'], true ), true );
ck( 'two open applications, one closed', array( count( $data['applications']['open'] ), count( $data['applications']['closed'] ) ), array( 2, 1 ) );
ck( 'two agreements awaiting review, one of them overdue', array( count( $data['agreements']['awaiting'] ), $data['agreements']['overdue'] ), array( 2, 1 ) );
ck( 'the overdue one is the older', $data['agreements']['awaiting'][0]['overdue'], true );
ck( 'returned and revoked come with their note', array( $data['agreements']['returned'][0]['note'], $data['agreements']['revoked'][0]['note'] ), array( 'Page two is missing.', 'Withdrawn by the rector.' ) );
ck( 'the queue, the due list and the approved list are the report class\'s', array( count( $data['reports']['queue'] ), count( $data['reports']['due'] ), count( $data['reports']['approved'] ) ), array( 1, 1, 1 ) );
ck( 'due is asked for today', in_array( array( 'due', gmdate( 'Y-m-d' ) ), $GLOBALS['asked'], true ), true );
ck( 'and approved since the start of this half-year', in_array( array( 'approved_since', (int) strtotime( WPCPM_Cohort::range( WPCPM_Cohort::current() )['from'] . ' 00:00:00 UTC' ) ), $GLOBALS['asked'], true ), true );
ck( 'two open requests, one overdue, one closed', array( count( $data['requests']['open'] ), $data['requests']['overdue'], count( $data['requests']['closed'] ) ), array( 2, 1, 1 ) );
ck( 'one locked account', count( $data['locked'] ), 1 );

$counts = WPCPM_Administrators_Cards::counts( $data );
ck( 'eight tiles in the spec\'s order', array_keys( $counts ), array( 'applications', 'agreements', 'overdue_agreements', 'drafts', 'due', 'requests', 'overdue_requests', 'locked' ) );
// array_map() keeps the input array's keys, and counts() is keyed by tile name (the
// previous check pins that order), so the expectation is keyed the same way rather than
// the plain list the brief first wrote, which could never === an array with string keys.
ck( 'each tile is a number and a card', array_map( static function ( $t ) { return $t['n'] . ':' . $t['card']; }, $counts ), array( 'applications' => '2:applications', 'agreements' => '2:agreements', 'overdue_agreements' => '1:agreements', 'drafts' => '1:reports', 'due' => '1:reports', 'requests' => '2:requests', 'overdue_requests' => '1:requests', 'locked' => '1:health' ) );

/* ---- programs() ---------------------------------------------------------- */

echo "\n=== Programs running ===\n";

$programs = $data['programs'];
ck( 'the tracks strip counts students in progress per track', array( $programs['tracks']['150h']['in_progress'], $programs['tracks']['50h']['in_progress'], $programs['tracks']['dev']['in_progress'] ), array( 1, 1, 1 ) );
ck( 'signed up this semester per track, from the start date', array( $programs['tracks']['150h']['signed_up'], $programs['tracks']['50h']['signed_up'], $programs['tracks']['dev']['signed_up'] ), array( 1, 0, 1 ) );
ck( 'finished this semester is one number: a graduate no longer says their track', $programs['finished'], 1 );
// A Dropped out row, ending inside the same cohort as the one Graduate row above: finished
// counts graduates only, so it stays 1, and Dropped out is not an active status either, so
// in_progress (checked again below, still 3) does not gain it (final review, Important 4).
ck( 'and Dropped out inside the cohort is neither finished nor in progress', array( $programs['finished'], $programs['rows'][0]['in_progress'] ), array( 1, 3 ) );
ck( 'one row per institution with somebody in progress', array_column( $programs['rows'], 'record' ), array( $A ) );
ck( 'with the count, the breakdown by label, the waiting and the distinct mentors', array( $programs['rows'][0]['in_progress'], $programs['rows'][0]['by_status'], $programs['rows'][0]['waiting'], $programs['rows'][0]['mentors'] ), array( 3, array( 'WordPress Credits Program 150h' => 1, 'Developer Track' => 1, 'WordPress Credits Program 50h' => 1 ), 1, 1 ) );
ck( 'the earliest and latest end among those in progress', array( $programs['rows'][0]['earliest'], $programs['rows'][0]['latest'] ), array( '2026-11-30', '2026-12-15' ) );
ck( 'the agreement state and the latest report state ride along', array( $programs['rows'][0]['agreement'], $programs['rows'][0]['report'] ), array( 'accepted', 'draft' ) );
ck( 'an institution with nobody in progress is counted, not listed', $programs['quiet'], 1 );
// The oldest non-zero read, not the newest: "how old are these numbers" is honestly answered
// by the stalest roster on the page, not the freshest one (final review, 7h). Institution A
// read 1756900000, B read 1756800000 - B is older.
ck( 'the read time is the oldest roster read, not the newest', $programs['read'], 1756800000 );
// This used to hold no matter what the NEVER_SHOWN skip did, because 'SPAM' also failed the
// active-status test on its own; the stub above now makes 'SPAM' an active status too (a
// status WPCPM_Program::track() still answers '' for, so signed_up is unaffected), so this
// can only pass today because the skip runs before that test is ever reached.
ck( 'a SPAM row counts for nothing', array( $programs['rows'][0]['in_progress'], $programs['tracks']['150h']['signed_up'] ), array( 3, 1 ) );

/* ---- the cards ----------------------------------------------------------- */

echo "\n=== The cards ===\n";

$strip = capture( static function () use ( $counts ) { WPCPM_Administrators_Cards::render_strip( $counts ); } );
// Eight, counting the opening tag rather than the bare class: the wrapping
// <ul class="wpcpm-attention__tiles"> also matches the bare needle, since "tiles" starts with
// "tile", so the bare count would read nine and call the wrapper a ninth tile.
ck( 'the strip is one section with eight tiles linking to the cards', array( substr_count( $strip, '<li class="wpcpm-attention__tile' ), has( $strip, 'id="wpcpm-attention"' ), has( $strip, 'href="#wpcpm-agreements"' ) ), array( 8, true, true ) );
// None of the fixture's eight counts is 0, so this used to hold no matter what render_strip()
// did with a zero; a tile is zeroed here, from counts()'s own output, so the check can
// actually fail if --zero ever stops being drawn (final review, Important 6).
$zero_counts = $counts;
$zero_counts['locked']['n'] = 0;
$zero_strip = capture( static function () use ( $zero_counts ) { WPCPM_Administrators_Cards::render_strip( $zero_counts ); } );
ck( 'a zero is drawn muted, not hidden', array( substr_count( $zero_strip, 'wpcpm-attention__tile--zero' ), substr_count( $zero_strip, '<li' ) ), array( 1, 8 ) );

$apps = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_applications( $data['applications'] ); } );
ck( 'the applications card is open with its count', has( $apps, 'id="wpcpm-applications"' ) && has( $apps, 'wpcpm-group__disclosure" open' ) && has( $apps, '<span class="wpcpm-group__count">2</span>' ), true );
ck( 'each open application names itself, its reference, country and routed manager', has( $apps, 'Uni Nueva' ) && has( $apps, 'APP-2026-0501' ) && has( $apps, 'Poland' ) && has( $apps, 'Ana Manager' ), true );
ck( 'the answers are the module\'s, behind a disclosure', has( $apps, 'data-app="501"' ) && has( $apps, 'wpcpm-administrator__answers' ), true );
ck( 'the decisions are the module\'s, and come back here', substr_count( $apps, 'name="wpcpm_return" value="dashboard"' ), 4 );
// Reject, Reject as spam and Delete for good carry a confirm; a cancelled dialog must not
// leave the guard thinking a submit is on its way (final review, Critical 1).
preg_match( '/<form[^>]*onsubmit="return confirm\([^>]*>/', $apps, $confirm_form );
ck( 'a decision drawn with a confirm still carries the double-submit guard', isset( $confirm_form[0] ) && has( $confirm_form[0], 'data-wpcpm-once' ) && has( $confirm_form[0], 'data-wpcpm-busy=' ), true );
ck( 'the closed ones sit in a second, closed disclosure', has( $apps, 'Uni Rejected' ) && has( $apps, 'wpcpm-administrator__closed' ), true );

echo "\n=== The application lists cap where the queue caps ===\n";

// 51 new rejected applications, on top of Uni Rejected (503) already in the fixture, are 52 -
// enough to prove the page cuts its own list exactly where the wp-admin queue's QUEUE_MAX
// does, rather than drawing every row the base has ever refused. Ids 1001-1051: an unused range.
for ( $i = 0; $i < 51; $i++ ) {
	seed_post( 1001 + $i, 'wpcpm_inst_app', 'Capped Uni ' . $i, array( '_wpcpm_app_state' => 'rejected', '_wpcpm_app_country' => '', '_wpcpm_app_country_name' => '' ), '2026-08-01 08:00:00' );
}

$capped = WPCPM_Administrators_Cards::collect();
ck( 'the closed list is cut at the page\'s own cap', count( $capped['applications']['closed'] ), 50 );
ck( 'but the total still counts every one of them', $capped['applications']['closed_total'], 52 );
$capped_html = capture( static function () use ( $capped ) { WPCPM_Administrators_Cards::render_applications( $capped['applications'] ); } );
ck( 'and the card says how many more are on the Institutions screen', has( $capped_html, '2 more are waiting' ) && has( $capped_html, 'page=wpcpm-institutions' ), true );

// Removed so every later check sees the fixture it was written against.
foreach ( range( 1001, 1051 ) as $id ) {
	unset( $GLOBALS['posts'][ $id ], $GLOBALS['pmeta'][ $id ] );
}

// Named $agr_html and not $agr: at this top-level script scope $agr would be the very same
// storage as $GLOBALS['agr'] (the WPCPM_Institution_Agreement fixture PHP mirrors global and
// superglobal-indexed access to), so assigning the captured markup to $agr would silently
// overwrite the fixture data and starve every later re-read of it (collect() is called again
// inside WPCPM_Administrators_Dashboard::render(), below).
$agr_html = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_agreements( $data['agreements'] ); } );
ck( 'each awaiting agreement is the panel\'s review block', $GLOBALS['reviews'], array( 601, 602 ) );
ck( 'the overdue one is marked', has( $agr_html, 'wpcpm-administrator__item--overdue' ), true );
ck( 'the institution is linked through the switcher', has( $agr_html, 'wpcpm_institution_view=' . $B ), true );
ck( 'the returned list shows the note', has( $agr_html, 'Page two is missing.' ), true );
ck( 'and the revoked one offers reinstate with its own nonce', has( $agr_html, 'value="wpcpm_agreement_reinstate"' ) && has( $agr_html, 'name="wpcpm_agreement_post" value="604"' ), true );

$rep = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_reports( $data['reports'] ); } );
ck( 'a draft to review links to the editor as that institution', has( $rep, 'wpcpm_report=2026-H1' ) && has( $rep, 'wpcpm_institution_view=' . $A ), true );
ck( 'and says the site drafted it with one still in progress', has( $rep, 'by the site' ) && has( $rep, '>1<' ), true );
ck( 'a due cohort has a Draft now form', has( $rep, 'value="wpcpm_report_draft"' ) && has( $rep, 'name="cohort" value="2025-H2"' ), true );
// A refusal (the semester already has a report, the ceiling is spent) has to come back here
// rather than to wp-admin's default (final review, Important 2).
ck( 'and it carries the dashboard return', has( $rep, 'name="wpcpm_return" value="dashboard"' ) && has( $rep, 'name="wpcpm_return_to" value="reports"' ), true );
ck( 'an approved report names who approved it', has( $rep, 'Manager Three' ), true );

$req = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_requests( $data['requests'] ); } );
ck( 'open requests draw the decisions, coming back here', substr_count( $req, 'value="wpcpm_resolve_request"' ) === 2 && substr_count( $req, 'name="wpcpm_return" value="dashboard"' ) === 2, true );
ck( 'the overdue one is marked and the note is printed', has( $req, 'wpcpm-administrator__item--overdue' ) && has( $req, 'Two students without a mentor.' ), true );
ck( 'the closed list says handled', has( $req, 'Handled' ), true );

$prog = capture( static function () use ( $programs ) { WPCPM_Administrators_Cards::render_programs( $programs ); } );
// Five, not four, for the same reason the strip count above is nine: the wrapping
// <ul class="wpcpm-programs__tiles"> matches the needle as well as the three track tiles
// and the finished tile it contains.
ck( 'the programs card has three track tiles and a finished tile', substr_count( $prog, 'wpcpm-programs__tile' ), 5 );
ck( 'the institution row links through the switcher and carries its numbers', has( $prog, 'wpcpm_institution_view=' . $A ) && has( $prog, '2026-12-15' ) && has( $prog, 'Uniwersytet Alpha' ), true );
ck( 'the quiet institutions are one closing line', has( $prog, '1 more institution' ), true );
ck( 'and the read time is printed', has( $prog, 'Read from the program records' ), true );

$health = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_health( $data['health'], $data['locked'] ); } );
ck( 'three syncs with their state', substr_count( $health, 'wpcpm-health__sync' ), 3 );
ck( 'the error is printed verbatim and escaped', has( $health, 'HTTP 429 from Airtable &lt;b&gt;x&lt;/b&gt;' ), true );
ck( 'the locked account is named', has( $health, 'Rep One' ), true );
ck( 'the probe verdict, the last mail and the invitation run are there', has( $health, 'blocked' ) && has( $health, 'report-drafted' ) && has( $health, '3 of 5' ), true );

// A run() that has already finished is history, not a live count: it must stop being read as
// "N of M sent" once the run itself says it is done (final review, Important 7g).
$health_data = $data['health'];
$health_data['invites']['run']['finished'] = 1756990500;
$health_finished = capture( static function () use ( $health_data, $data ) { WPCPM_Administrators_Cards::render_health( $health_data, $data['locked'] ); } );
ck( 'a finished run no longer prints Invitations:', has( $health_finished, 'Invitations:' ), false );

/* ---- the page and the render ---------------------------------------------- */

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-dashboard.php';

echo "\n=== The page ===\n";

$page_id = WPCPM_Administrators_Dashboard::ensure_page();
ck( 'ensure_page() creates the page and records it', $page_id > 0 && (int) get_option( 'wpcpm_administrator_page_id' ) === $page_id, true );
// wp_insert_post() only ever returns a WP_Error when asked to, and is_wp_error( $page_id ) is
// the very next line - asking is what WPCPM_Institutions_Dashboard::ensure_page() does too
// (final review, 7i). Filtered rather than the last call overall: gate_page() and
// update_option() also record calls of their own, after this one.
$insert_calls = array_values( array_filter( $GLOBALS['calls'], static function ( $c ) { return 'wp_insert_post' === $c[0]; } ) );
ck( 'and it asks wp_insert_post() for a WP_Error on failure', end( $insert_calls )[3], true );
ck( 'with the slug, the title and the block', array( $GLOBALS['posts'][ $page_id ]->post_name, $GLOBALS['posts'][ $page_id ]->post_title, $GLOBALS['posts'][ $page_id ]->post_content ), array( 'administrator-dashboard', 'Administrator Dashboard', '<!-- wp:wpcpm/administrator-dashboard /-->' ) );
ck( 'gated to administrators, and the meta exists rather than defaults', array( get_post_meta( $page_id, '_wpcpm_access_level', true ), metadata_exists( 'post', $page_id, '_wpcpm_access_level' ) ), array( 'administrator', true ) );
ck( 'a second call inserts nothing', WPCPM_Administrators_Dashboard::ensure_page(), $page_id );
update_post_meta( $page_id, '_wpcpm_access_level', 'public' );
WPCPM_Administrators_Dashboard::ensure_page();
ck( 'a level the site set is never re-gated', get_post_meta( $page_id, '_wpcpm_access_level', true ), 'public' );
update_post_meta( $page_id, '_wpcpm_access_level', 'administrator' );
unset( $GLOBALS['opts']['wpcpm_administrator_page_id'] );
ck( 'a lost option adopts the page by slug', WPCPM_Administrators_Dashboard::ensure_page(), $page_id );
ck( 'page_url() is the permalink', WPCPM_Administrators_Dashboard::page_url(), get_permalink( $page_id ) );

echo "\n=== The render ===\n";

// register() is what wp_register_style()s the sheet; on a real site it runs on init, before
// any request reaches render(). bin/test-institutions-dashboard.php calls the sibling
// dashboard's register() the same way before testing its enqueue.
WPCPM_Administrators_Dashboard::register();

$admin_style = $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ];
ck( 'administrator.css is registered on the mentors\' stylesheet, where the Tools section is laid out', array( false !== strpos( $admin_style['src'], 'administrator.css' ), in_array( WPCPM_Mentors_Dashboard::STYLE, $admin_style['deps'], true ) ), array( true, true ) );

$GLOBALS['uid']    = 0;
// An array of allowed IDs, not a bare flag: user_can() above tests it with in_array(), the
// same contract bin/test-institutions-dashboard.php's stub uses.
$GLOBALS['manage'] = array();
$out = WPCPM_Administrators_Dashboard::render( array() );
ck( 'logged out: a notice and no card', has( $out, 'wpcpm-dashboard--notice' ) && ! has( $out, 'wpcpm-attention' ), true );
// Same shape as WPCPM_Institutions_Dashboard::render() for a logged-out visitor (final
// review, 7j): a sentence and a Log in button, not a dead end.
ck( 'and it offers a way to sign in, not a dead end', has( $out, 'wp-login.php' ) && has( $out, '>Log in<' ), true );

$GLOBALS['uid']    = 21;
$GLOBALS['manage'] = array();
$out = WPCPM_Administrators_Dashboard::render( array() );
ck( 'a signed-in non-manager gets the refusal and no form', has( $out, 'cannot manage the program' ) && ! has( $out, '<form' ), true );

$GLOBALS['uid']       = 3;
$GLOBALS['manage']    = array( 3 );
$GLOBALS['flash']     = array( 'institutions' => 'app-approved' );
$GLOBALS['prompted']  = array();
$GLOBALS['resources'] = array();
$GLOBALS['tools']     = array();
$GLOBALS['reviews']   = array();
$out = WPCPM_Administrators_Dashboard::render( array( 'title' => 'Today' ) );
ck( 'the manager gets the page with the title', has( $out, 'class="wpcpm-dashboard wpcpm-administrator"' ) && has( $out, '<h2 class="wpcpm-dashboard__title">Today</h2>' ), true );
ck( 'the two-factor prompt is for the viewer', $GLOBALS['prompted'], array( 3 ) );
ck( 'the flash on the institutions channel is drawn in the queue\'s words', has( $out, 'The application is approved.' ) && has( $out, 'wpcpm-dashboard__message--success' ), true );
ck( 'and taken, so it shows once', isset( $GLOBALS['flash']['institutions'] ), false );
$positions = array();
foreach ( array( 'id="wpcpm-attention"', 'id="wpcpm-applications"', 'id="wpcpm-agreements"', 'id="wpcpm-reports"', 'id="wpcpm-requests"', 'id="wpcpm-programs"', 'id="wpcpm-health"', 'id="wpcpm-tools"', 'wpcpm-handbook__resources' ) as $needle ) {
	$positions[] = strpos( $out, $needle );
}
$sorted = $positions;
sort( $sorted );
ck( 'every card is drawn, in the spec\'s order, the resources last', ! in_array( false, $positions, true ) && $positions === $sorted, true );
ck( 'the Tools section is drawn for the manager viewing, as the managers audience', $GLOBALS['tools'], array( array( 'managers', $GLOBALS['uid'] ) ) );
ck( 'the resources are the administrator audience', $GLOBALS['resources'], array( 'administrator' ) );
ck( 'the agreement reviews were drawn once each', $GLOBALS['reviews'], array( 601, 602 ) );
ck( 'the stylesheet is registered from assets/css/administrator.css', isset( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ] ) && false !== strpos( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ]['src'], 'assets/css/administrator.css' ), true );
ck( 'and render() switched it on', ! empty( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ]['on'] ), true );
ck( 'the dashboard arms the double-submit guard its forms carry', in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ), true );

// A refused Draft now flashes on the semester report screen's own channel, not the queue's
// (final review, Important 2); render_messages() has to read that channel too and print it in
// the screen's own words. A fresh render(), after every check above that pins what the first
// one drew, so the extra call's own side effects (another resources audience, another pair of
// reviews) cannot make an earlier check read the wrong count.
$GLOBALS['flash'] = array( 'institution_report' => array( 'status' => 'refused' ) );
$out_report_flash = WPCPM_Administrators_Dashboard::render( array() );
ck( 'render_messages() also prints a refusal flashed on the report screen\'s channel, with its class', has( $out_report_flash, 'That is not something you can do here.' ) && has( $out_report_flash, 'wpcpm-dashboard__message--error wpcpm-dashboard__message--refused' ), true );
ck( 'and that channel is taken too, so it shows once', isset( $GLOBALS['flash']['institution_report'] ), false );

echo "\n=== The toolbar and the refusal ===\n";

$GLOBALS['manage'] = array( 3 );
ck( 'a manager\'s toolbar lists the Administrator Dashboard', in_array( 'wpcpm-administrator-dashboard', array_column( WPCPM_Dashboards::links(), 'id' ), true ), true );
$GLOBALS['manage'] = array();
ck( 'and a member\'s does not', in_array( 'wpcpm-administrator-dashboard', array_column( WPCPM_Dashboards::links(), 'id' ), true ), false );
ck( 'the refusal names the audience, not the Mentor role', WPCPM_Dashboards::nothing_to_show( 'administrators', false ), 'This page is for the program managers. Your account cannot manage the program.' );
ck( 'and a manager with nothing is pointed at the Administrators screen', has( WPCPM_Dashboards::nothing_to_show( 'administrators', true ), 'page=wpcpm-administrators' ), true );

echo "\n=== The wiring ===\n";

$module_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators.php' );
ck( 'the module boots the dashboard, ensures the page on activation and deletes its options on uninstall', array( has( $module_src, 'WPCPM_Administrators_Dashboard::init()' ), has( $module_src, 'WPCPM_Administrators_Dashboard::ensure_page()' ), has( $module_src, 'WPCPM_Administrators_Dashboard::OPT_PAGE' ), has( $module_src, 'WPCPM_Administrators_Dashboard::OPT_TITLE_FIXED' ) ), array( true, true, true, true ) );
$block = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/administrator-dashboard/block.json' ), true );
$asset = include WPCPM_PLUGIN_DIR . 'blocks/administrator-dashboard/editor.asset.php';
ck( 'the block is named for the class and versioned with the release', array( $block['name'], $block['version'], $asset['version'] ), array( 'wpcpm/administrator-dashboard', '1.92.0', '1.92.0' ) );
$dashes = array();
foreach ( array( 'includes/modules/class-wpcpm-administrators-cards.php', 'includes/modules/class-wpcpm-administrators-dashboard.php', 'includes/class-wpcpm-return.php', 'assets/css/administrator.css', 'blocks/administrator-dashboard/editor.js', 'bin/test-administrators-dashboard.php' ) as $relative ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $relative ) ) ) {
		$dashes[] = $relative;
	}
}
ck( 'no dash but the plain hyphen in any new file', $dashes, array() );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
