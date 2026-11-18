<?php
/**
 * Viewing as institution, the compact control (Task 14): one small control at the end of the Institution
 * Dashboard's head, where picking a school loads its page.
 *
 * What each block pins, and why:
 *
 * - **Every other surface prints today's switcher, byte for byte.** `WPCPM_Dashboards::render_switcher()` is shared
 *   with the Sponsor Dashboard's "Viewing as sponsor" and the Report Cards' switchers, and `render_combo()` with
 *   every long list of the site. Their markup is pinned by md5 digests taken from the code at d419cae0 (where the
 *   two were as they were at c0dfdf0a), under every surface name that draws them, with `institution` live: the
 *   Sponsor Dashboard, the Report Cards, the Institutions screen's tabs, no surface at all, and the Institution
 *   Dashboard itself while its surface is off (whole pages, not only the form).
 * - **The parts' branch, on the Institution Dashboard alone:** a GET form marked `data-wpcpm-pick-submits`, today's
 *   hidden fields (the view kept), the label "Viewing as institution" beside its box, the one combobox, the note
 *   "Only Administrators see this control." for screen readers tied to the box by `aria-describedby`, and Show, a
 *   secondary button, inside the wrapper the parts' script hides once it submits on a pick. The parts' script is
 *   enqueued with it.
 * - **Where it sits:** at the end of the head's first row, beside the page's one H1, on the Overview and on every
 *   view; in the choice state it is the main area's one field, under the head, and not in it.
 * - **The choice state is empty:** an empty first option, "Choose an institution", chosen, that names no school
 *   (the script never lists it, so its field is empty); Show with it chosen stays on the choice.
 * - **Permissions do not move:** a school's own reader gets no form, no list of schools and no hidden field, and
 *   an address naming another school changes nothing for them; the school is still resolved from the request by
 *   `requested_record()` and the index.
 * - **What picking does**, run by node on this markup (bin/js/switcher-pick.js): the two real scripts, switcher.js
 *   announcing a pick and parts.js submitting a marked form once.
 * - The layout harness keeps the head with the control for an Administrator viewing the school with the
 *   40-character name, a view's head and the choice state.
 *
 * Run from the plugin root:  php bin/test-institution-switcher.php
 */

// phpcs:disable

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
$GLOBALS['edit_others'] = array();
$GLOBALS['uid']     = 0;
$GLOBALS['calls']   = array();
$GLOBALS['styles']  = array();
$GLOBALS['queried'] = 0;
$GLOBALS['pagenow'] = 'index.php';
$GLOBALS['ajax']    = false;
$GLOBALS['mentors'] = array();
$GLOBALS['next_id'] = 100;

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
	public $ID = 0, $post_title = '', $post_name = '', $post_content = '', $post_type = 'page', $post_status = 'publish';
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
// As WordPress's: an array or an object is '' (`_sanitize_text_fields()`), so an array in the address reads as nothing.
function sanitize_text_field( $s ) { return is_array( $s ) || is_object( $s ) ? '' : trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
// Core's, as far as the fixtures' names reach it: they hold no accent. The switcher sorts by it.
function remove_accents( $s, $locale = '' ) { return (string) $s; }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_action', $h, $p ); }
function add_filter( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_filter', $h, $p ); }
function add_shortcode( $tag, $c ) { $GLOBALS['calls'][] = array( 'add_shortcode', $tag ); }
function register_block_type( $dir, $args = array() ) { $GLOBALS['calls'][] = array( 'register_block_type', $dir ); return true; }
function wp_json_encode( $d, $o = 0, $depth = 512 ) { return json_encode( $d, $o, $depth ); }
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
// Like WordPress: the access level is registered with a default of 'public', which is what a
// page with no row at all answers. `metadata_exists()` is how the two are told apart.
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
function metadata_exists( $type, $id, $k ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; $GLOBALS['calls'][] = array( 'update_post_meta', (int) $id, $k, $v ); return true; }

function get_post( $id ) { return $id instanceof WP_Post ? $id : ( $GLOBALS['posts'][ (int) $id ] ?? null ); }
function get_post_status( $id ) { $p = get_post( $id ); return $p instanceof WP_Post ? $p->post_status : false; }
function get_permalink( $id ) { $p = get_post( $id ); return $p instanceof WP_Post ? 'https://example.test/' . $p->post_name . '/' : false; }
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
	$GLOBALS['calls'][]            = array( 'wp_insert_post', $post->post_name, $post->post_title );
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
	if ( 'edit_others_posts' === $c ) { return in_array( $id, $GLOBALS['edit_others'], true ); }
	return in_array( $id, $GLOBALS['manage'], true );
}
function current_user_can( $c ) { return user_can( $GLOBALS['uid'], $c ); }

function wp_login_url( $to = '' ) { return 'https://example.test/wp-login.php?redirect_to=' . rawurlencode( $to ); }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
/**
 * `add_query_arg()` as WordPress builds it (the views suite's copy): one key and value or an array of them, set on
 * the URL's query (a key already there is replaced), the fragment kept at the end, the new values put in as given.
 */
function add_query_arg( ...$a ) {
	if ( is_array( $a[0] ) ) {
		$set = $a[0];
		$url = isset( $a[1] ) ? (string) $a[1] : '';
	} else {
		$set = array( $a[0] => $a[1] );
		$url = isset( $a[2] ) ? (string) $a[2] : '';
	}
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
// The dashboards' frame (Task 5): the theme's support for the bare canvas, the account's photo and Log out.
function get_theme_support( $f ) { return isset( $GLOBALS['theme_support'][ $f ] ) ? $GLOBALS['theme_support'][ $f ] : false; }
function get_avatar( $id, $size = 96, $d = '', $alt = '', $args = array() ) { return '<img class="wpcpm-frame__avatar" alt="" src="https://example.test/avatar.png">'; }
function wp_logout_url( $to = '' ) { return 'https://example.test/wp-login.php?action=logout'; }
function sanitize_email( $s ) { return false !== strpos( (string) $s, '@' ) ? trim( (string) $s ) : ''; }
// The real flash, over the suite's user meta (Review Focus 5: which view takes a card's message).
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function wp_slash( $v ) { return $v; }
// The school's details requests, read by the real request class for the Overview's news: none in this suite.
function get_posts( $args = array() ) { return array(); }
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
// The movable modules (1.96.4): the mover's nonce, the handler's exits, the redirect's arguments.
if ( ! function_exists( 'check_admin_referer' ) ) { function check_admin_referer( $a = '', $q = '' ) { $GLOBALS['nonce_checked'][] = $a; return true; } }
if ( ! function_exists( 'wp_die' ) ) { function wp_die( $m = '', $t = '', $a = array() ) { throw new Exception( 'die: ' . ( is_array( $a ) && isset( $a['response'] ) ? $a['response'] : 'died' ) ); } }
if ( ! function_exists( 'wp_send_json_success' ) ) { function wp_send_json_success( $d = null ) { throw new Exception( 'json: ' . json_encode( $d ) ); } }
if ( ! function_exists( 'wp_nonce_field' ) ) { function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce">'; } }
if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $k, $v, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . rawurlencode( (string) $v ); } }
if ( ! function_exists( 'home_url' ) ) { function home_url( $p = '/' ) { return 'https://example.test' . $p; } }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) {}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
// The switch of the shared parts, which each screen and dashboard names itself to while it draws, as the plugin
// loads it before its modules.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-module-order.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-card.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
// The frame the page is drawn in (Task 5), its icons, and the flash its cards take their messages from.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-frame.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
// The real helper and not a stand-in: it is where the empty-page wording lives now, and a
// copy of the sentence here would let the page ship one wording while the suite pinned
// another. Only `nothing_to_show()` is ever called, and it reads nothing but the functions
// stubbed above.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

/**
 * A card's message channel, read from the real class's source (Task 9), so the stand-ins below take the channels the
 * real cards take without a copy of their names: `const FLASH = '...'`.
 *
 * @param string $file The class's file under `includes/modules/`.
 * @return string
 */
function channel_of( $file ) {
	return preg_match( "/const FLASH\\s*=\\s*'([^']+)'/", (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/' . $file ), $m ) ? $m[1] : '';
}

/**
 * Take a card's message as the real card does when it draws, and print it once, so a check can count it (Task 9).
 *
 * @param string $channel The channel.
 */
function take_message( $channel ) {
	$said = WPCPM_Flash::take( $channel );
	if ( '' !== $said && null !== $said ) {
		echo '<p class="wpcpm-test-message" data-channel="' . esc_attr( $channel ) . '"></p>';
	}
}

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $value ) {
			return is_string( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/D', trim( $value ) );
		}
	}
}

if ( ! class_exists( 'WPCPM_Content_Access' ) ) {
	class WPCPM_Content_Access {
		const META_KEY = '_wpcpm_access_level';
	}
}

if ( ! class_exists( 'WPCPM_Two_Factor' ) ) {
	class WPCPM_Two_Factor {
		public static function prompt( $user ) { echo '<!-- 2fa -->'; }
	}
}

if ( ! class_exists( 'WPCPM_Mentors_Dashboard' ) ) {
	class WPCPM_Mentors_Dashboard {
		const STYLE = 'wpcpm-mentor-dashboard';
		public static function register_assets() {
			if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
				wp_register_style( self::STYLE, WPCPM_PLUGIN_URL . 'assets/css/dashboard.css', array( 'dashicons' ), WPCPM_VERSION );
			}
		}
		public static function is_mentor( $user = null ) {
			$user = WPCPM_Roles::resolve_user( $user );
			return $user instanceof WP_User && in_array( $user->ID, $GLOBALS['mentors'], true );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	class WPCPM_Institution_Members {
		const META_RECORD_ID = 'wpcpm_institution_record_id';
		const META_ACTIVE    = 'wpcpm_institution_active';
		const META_PROFILE   = 'wpcpm_institution_profile';
		const META_LEVEL     = 'wpcpm_institution_level';
		const LEVEL_REPRESENTATIVE = 'representative';
		const LEVEL_TUTOR          = 'tutor';
		/** As the real class reads it: a tutor only where the meta says so, a representative otherwise. */
		public static function level_of( $user = null ) {
			$user = WPCPM_Roles::resolve_user( $user );
			if ( ! $user instanceof WP_User || ! $user->exists() ) { return self::LEVEL_REPRESENTATIVE; }
			return 'tutor' === (string) get_user_meta( $user->ID, self::META_LEVEL, true ) ? self::LEVEL_TUTOR : self::LEVEL_REPRESENTATIVE;
		}
		public static function institution_of( $user = null ) {
			$user = WPCPM_Roles::resolve_user( $user );
			if ( ! $user instanceof WP_User || ! $user->exists() ) { return ''; }
			$stamp = trim( (string) get_user_meta( $user->ID, self::META_RECORD_ID, true ) );
			$live  = '1' === (string) get_user_meta( $user->ID, self::META_ACTIVE, true );
			return ( $live && WPCPM_Mentors_Sync::is_record_id( $stamp ) ) ? $stamp : '';
		}
		public static function memberships_of( $user = null ) {
			$one = self::institution_of( $user );
			return '' === $one ? array() : array( $one );
		}
		public static function is_member( $user = null ) { return '' !== self::institution_of( $user ); }
		public static function members_of( $record_id ) {
			$out = array();
			foreach ( $GLOBALS['users'] as $user ) {
				if ( 0 === strcmp( self::institution_of( $user ), (string) $record_id ) && '' !== (string) $record_id ) { $out[] = $user; }
			}
			return $out;
		}
	}
}

if ( ! class_exists( 'WPCPM_Institutions_Index' ) ) {
	class WPCPM_Institutions_Index {
		const OPT_NAME  = 'wpcpm_institutions_index';
		const VERSION = 1;
		public static function read() {
			$o = get_option( self::OPT_NAME );
			return ( is_array( $o ) && isset( $o['v'] ) && self::VERSION === $o['v'] ) ? $o : array( 'v' => 1, 'read' => 0, 'rows' => array() );
		}
		public static function rows() { $r = self::read(); return $r['rows']; }
		public static function row( $id ) {
			if ( ! WPCPM_Mentors_Sync::is_record_id( $id ) ) { return null; }
			$rows = self::rows();
			return isset( $rows[ $id ] ) ? $rows[ $id ] : null;
		}
		public static function has( $id ) { return null !== self::row( $id ); }
	}
}

if ( ! class_exists( 'WPCPM_Roster_Index' ) ) {
	class WPCPM_Roster_Index {
		const OPT_PREFIX = 'wpcpm_roster_';
		const VERSION       = 1;
		public static function option_name( $id ) { return self::OPT_PREFIX . $id; }
		public static function read( $id ) {
			$o = get_option( self::option_name( $id ) );
			return is_array( $o ) ? $o : array( 'v' => 1, 'read' => 0, 'rows' => array() );
		}
		public static function rows( $id ) { return self::read( $id )['rows']; }
		/** The roster's four groups for a cohort (Task 6's figures count two of them): the fixture's, or none. */
		public static function groups( $id, $cohort = '' ) { return $GLOBALS['groups'][ (string) $id ][ (string) $cohort ] ?? array(); }
	}
}

if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	class WPCPM_Institution_Agreement {
		const SUMMARY_NONE      = 'none';
		const SUMMARY_GENERATED = 'generated';
		const SUMMARY_SUBMITTED = 'submitted';
		const SUMMARY_RETURNED  = 'returned';
		const SUMMARY_REVOKED   = 'revoked';
		const SUMMARY_ACCEPTED  = 'accepted';
		const SUMMARY_ON_FILE   = 'on_file';
		public static function is_settled( $record_id ) {
			$row = get_option( 'wpcpm_agreement_' . $record_id );
			return is_array( $row ) && ! empty( $row['settled'] );
		}
		public static function summary( $record_id ) {
			$row = get_option( 'wpcpm_agreement_' . $record_id );
			return array(
				'state'           => is_array( $row ) && isset( $row['site_state'] ) ? $row['site_state'] : self::SUMMARY_NONE,
				'kind'            => '',
				'accepted_at'     => '',
				'agreement_id'    => 0,
				'pending_id'      => 0,
				'generated_id'    => 0,
				'airtable_status' => is_array( $row ) && isset( $row['airtable_status'] ) ? $row['airtable_status'] : '',
				'route'           => '',
			);
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Policy' ) ) {
	/**
	 * The fence, stubbed to `decide()`'s contract for the one action this page asks about.
	 *
	 * ACT_AGREEMENT is the only ungated action, so a member passes it whether or not their
	 * agreement is settled. That is deliberate here: the shell asks this question precisely
	 * because it must recognize a locked member as a member.
	 */
	class WPCPM_Institution_Policy {
		// 1.123.98: the two acts the Overview's rows that wait ask for.
		const ACT_RESEND_INVITE = 'resend_invite';
		const ACT_EDIT_STUDENT  = 'edit_student';
		const GROUND_MANAGER = 'manager';
		const GROUND_MEMBER  = 'member';
		const GROUND_TUTOR   = 'tutor';
		const ACT_VIEW_ROSTER          = 'view_roster';
		const ACT_AGREEMENT            = 'agreement';
		// The two more questions the menu asks (Task 3's `views_for()`), gated as every action but the agreement is.
		const ACT_ADD_STUDENT          = 'add_student';
		const ACT_VIEW_SEMESTER_REPORT = 'view_semester_report';
		// The details card's own question, which the Overview's request row asks too (Task 25, fix round 1, B2).
		const ACT_EDIT_DETAILS         = 'edit_details';
		public static function subject_institution( $record_id ) {
			return array( 'type' => 'institution', 'id' => $record_id, 'institution_ids' => array( $record_id ), 'evidence' => 'index' );
		}
		public static function decide( $action, array $subject, $user = null ) {
			$refused = array( 'allowed' => false, 'ground' => '', 'institution' => '', 'fields' => array(), 'why' => '' );
			$user    = WPCPM_Roles::resolve_user( $user );
			if ( ! $user instanceof WP_User || ! $user->exists() ) { return $refused; }
			$ids = array_values( array_filter( (array) $subject['institution_ids'], array( 'WPCPM_Mentors_Sync', 'is_record_id' ) ) );
			if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) ) {
				return array( 'allowed' => true, 'ground' => self::GROUND_MANAGER, 'institution' => $ids ? $ids[0] : '', 'fields' => null, 'why' => '' );
			}
			if ( empty( $ids ) ) { return $refused; }
			// A tutor holds the views of the roster and of the semester report, behind the gate, and nothing else (the
			// real rule is the policy suite's).
			$tutor = WPCPM_Institution_Members::LEVEL_TUTOR === WPCPM_Institution_Members::level_of( $user );
			if ( $tutor && ! in_array( $action, array( self::ACT_VIEW_ROSTER, self::ACT_VIEW_SEMESTER_REPORT ), true ) ) { return $refused; }
			foreach ( WPCPM_Institution_Members::memberships_of( $user ) as $mine ) {
				if ( ! in_array( $mine, $ids, true ) ) { continue; }
				if ( self::ACT_AGREEMENT !== $action && ! WPCPM_Institution_Agreement::is_settled( $mine ) ) { continue; }
				return array( 'allowed' => true, 'ground' => $tutor ? self::GROUND_TUTOR : self::GROUND_MEMBER, 'institution' => $mine, 'fields' => null, 'why' => '' );
			}
			return $refused;
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Roster' ) ) {
	/** Spec 5.5, stubbed exactly: the switcher under CAP_MANAGE, then the stamp, then the first institution with a member. */
	class WPCPM_Institution_Roster {
		const ARG_VIEW = 'wpcpm_institution_view';
		public static function resolve_institution( $viewer, $can_manage ) {
			// How often the page asked (Task 7: the choice state is decided before it is asked).
			$GLOBALS['resolve_calls'] = ( $GLOBALS['resolve_calls'] ?? 0 ) + 1;
			if ( $can_manage ) {
				$asked = WPCPM_Request::text( self::ARG_VIEW );
				if ( WPCPM_Mentors_Sync::is_record_id( $asked ) && WPCPM_Institutions_Index::has( $asked ) ) {
					return $asked;
				}
			}
			$mine = WPCPM_Institution_Members::institution_of( $viewer );
			if ( '' !== $mine ) { return $mine; }
			if ( $can_manage ) {
				foreach ( array_keys( WPCPM_Institutions_Index::rows() ) as $record_id ) {
					if ( ! empty( WPCPM_Institution_Members::members_of( $record_id ) ) ) { return $record_id; }
				}
			}
			return '';
		}
		public static function switcher_options() {
			$out = array();
			foreach ( WPCPM_Institutions_Index::rows() as $record_id => $row ) {
				$out[ $record_id ] = trim( (string) $row['name'] );
			}
			return $out;
		}
	}
}

/*
 * The first of the four cards. Each says only that it ran, which is all this suite asks of
 * them - but they do not all land at once. The other three are declared further down, after
 * the section that renders without them, because the shell names every card as a string
 * precisely so a checkout can be missing one. A class declared at the top level of a file is
 * bound when the file is compiled, so a top-level declaration is a class that exists from
 * the first line: only a conditional one is absent until execution reaches it.
 */
if ( ! class_exists( 'WPCPM_Handbook_Assistant' ) ) {
	/**
	 * Stands in for the shared Resources section.
	 *
	 * Records the audience it was asked for and hands back the audience-specific block, which
	 * is all this page contributes to it; what the section itself draws is its own suite's.
	 */
	class WPCPM_Handbook_Assistant {
		const INSTITUTION_GUIDE_PATH = 'institution-guide';
		/** The guide entry the menu's Help reads (Task 5): the handbook's page until the site publishes its own. */
		public static function guides() {
			$own = get_page_by_path( self::INSTITUTION_GUIDE_PATH );
			return array(
				'institution' => array(
					'label' => 'Institution guide',
					'url'   => $own instanceof WP_Post && 'publish' === $own->post_status ? get_permalink( $own->ID ) : 'https://make.wordpress.org/community/handbook/education/credits/institutions/',
					'slack' => 'https://wordpress.slack.com/archives/C0959D2M3T8',
				),
			);
		}
		public static function render_resources( $audience = '', $extra = '' ) {
			$GLOBALS['resources'][] = (string) $audience;

			return '<section class="wpcpm-handbook__resources" data-audience="' . $audience . '">' . $extra . '</section>';
		}
	}
}

if ( ! class_exists( 'WPCPM_Countries' ) ) {
	/** Stands in for the country routing map. */
	class WPCPM_Countries {
		public static function routing( $record_id ) {
			return isset( $GLOBALS['routing'][ $record_id ] ) ? $GLOBALS['routing'][ $record_id ] : null;
		}
	}
}

// The Administrator an institution is assigned to (Institutions off Airtable, R1): its record, at the one read
// the assignment makes, and the booking link on the Administrator's own account.
if ( ! class_exists( 'WPCPM_Institution_Record' ) ) {
	class WPCPM_Institution_Record {
		public static function get( $record_id ) {
			// The history's read of the record is what this log is for; the assignment's read of who the
			// institution is assigned to (the contact block) is another question.
			$caller = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
			if ( empty( $caller[1]['class'] ) || 'WPCPM_Institution_Assignment' !== $caller[1]['class'] ) {
				$GLOBALS['history_reads'][] = array( 'record', $record_id );
			}
			return isset( $GLOBALS['kept'][ $record_id ] ) ? $GLOBALS['kept'][ $record_id ] : array();
		}
	}
}
if ( ! class_exists( 'WPCPM_Manager_Booking' ) ) {
	class WPCPM_Manager_Booking {
		public static function url_for( $user_id ) { return isset( $GLOBALS['booking'][ (int) $user_id ] ) ? $GLOBALS['booking'][ (int) $user_id ] : ''; }
	}
}
if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
}
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-assignment.php';

if ( ! class_exists( 'WPCPM_Institution_Panel' ) ) {
	class WPCPM_Institution_Panel {
		/** The sentence the Overview's agreement row says why with (Task 4); its words are the panel suite's. */
		public static function state_sentence( $state, array $summary ) { return 'The agreement waits for you (' . $state . ').'; }
		public static function render( $record_id, array $context ) {
			$GLOBALS['cards'][]      = array( 'panel', $record_id, $context );
			$GLOBALS['card_calls'][] = __CLASS__;
			// As the real panel: it takes the agreement's outcome, on the Institutions screen's channel (Task 9).
			take_message( channel_of( 'class-wpcpm-institutions.php' ) );
			echo '<div class="wpcpm-institution__card" data-card="panel"></div>';
		}
	}
}

/*
 * What the menu and the Overview's counts ask besides the four cards (Task 5): the enrollment form (the card of its
 * own view, Enroll students) and the batch it finds, the program's updates, and the details requests, the real
 * class, which the For Administrators section below reads too.
 */
$GLOBALS['import_on']    = true;
$GLOBALS['active_batch'] = array();
$GLOBALS['batches']      = array();
$GLOBALS['updates']      = array();
$GLOBALS['card_calls']   = array();
if ( ! class_exists( 'WPCPM_Institution_Import_Form' ) ) {
	class WPCPM_Institution_Import_Form {
		const FLASH     = 'institution_import';
		const ARG_BATCH = 'wpcpm_batch';
		public static function enabled() { return (bool) $GLOBALS['import_on']; }
		public static function waiting_sentence( int $batch_id, string $record, array $batch = array() ): string { return '3 of 3 rows can be created.'; }
		public static function render( $record_id, array $context = array() ) {
			$GLOBALS['cards'][]      = array( 'import', $record_id, $context );
			$GLOBALS['card_calls'][] = __CLASS__;
			// As the real form: it takes its message when it draws (Review Focus 5).
			take_message( self::FLASH );
			echo '<div class="wpcpm-institution__card" data-card="import"></div>';
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Import' ) ) {
	class WPCPM_Institution_Import {
		const STATE_STAGED   = 'staged';
		const STATE_CREATING = 'creating';
		public static function active_for( $record ) { return (int) ( $GLOBALS['active_batch'][ (string) $record ] ?? 0 ); }
		public static function batch( $id ) { return $GLOBALS['batches'][ (int) $id ] ?? null; }
	}
}
if ( ! class_exists( 'WPCPM_Updates' ) ) {
	class WPCPM_Updates {
		public static function posts( $max = 5, $audience = '' ) {
			$GLOBALS['updates_asked'][] = (string) $audience;
			return array_slice( (array) $GLOBALS['updates'], 0, (int) $max );
		}
	}
}
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-request.php';

// 1.123.98: what the Overview's new rows ask, where this suite stands in for none of it.
require_once __DIR__ . '/stubs/overview-more.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php';
// The real rule for a test name and the one helper that asks it, loaded so the guard below bites if the
// dashboard ever drew a mark (Ruling 7: the institution's own dashboard holds neither mark nor label).
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-test-institutions.php';
if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = 'default' ) { return $text; }
}

$GLOBALS['transients'] = array();
$GLOBALS['http']       = array();
$GLOBALS['fetched']    = array();

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * Render the dashboard as one viewer, with one set of query arguments.
 *
 * @param int   $uid  User to render as; 0 for a logged-out visitor.
 * @param array $get  Query arguments the render should see.
 * @param array $atts Shortcode attributes.
 * @return string
 */
function render_as( $uid, array $get = array(), array $atts = array() ) {
	$GLOBALS['uid']        = (int) $uid;
	$GLOBALS['cards']      = array();
	$GLOBALS['card_calls'] = array();
	$GLOBALS['resolve_calls'] = 0;
	$_GET                  = $get;
	// Each render is its own request: the views and the rows are worked out once a request (Task 4).
	WPCPM_Institutions_Dashboard::forget();

	return WPCPM_Institutions_Dashboard::render( $atts );
}

/** Which cards ran on the last render, in order. */
function cards_run() {
	return array_map( function ( $card ) { return $card[0]; }, $GLOBALS['cards'] );
}

/**
 * The cards each of the views with cards draws for one reader (Task 5: a view per menu item), a view that drew
 * none left out. Students, Your institution and Agreement: the four cards' views.
 *
 * @param int   $uid Who.
 * @param array $get More query arguments.
 * @return array<string, string[]>
 */
function cards_by_view( $uid, array $get = array() ) {
	$out = array();
	foreach ( array( 'students', 'institution', 'agreement' ) as $view ) {
		render_as( $uid, array_merge( $get, array( 'wpcpm_view' => $view ) ) );
		if ( array() !== cards_run() ) {
			$out[ $view ] = cards_run();
		}
	}
	return $out;
}

// The rule's word list is shared with the school's details card suite, which draws the real card in every
// state it has and holds it to the same list (`bin/stubs/internal-words.php`).
require_once __DIR__ . '/stubs/internal-words.php';

/* ---- the cards, the events list and the history's neighbors, stood in for as the Institution Dashboard's suite does ---- */

if ( ! class_exists( 'WPCPM_Institution_Roster_View' ) ) {
	class WPCPM_Institution_Roster_View {
		// The filter bar's own argument names. They are this class's, not the shell's, which is
		// the whole point of the two readers below: the shell asks rather than guessing.
		const ARG_COHORT = 'wpcpm_cohort';
		const ARG_STATUS = 'wpcpm_roster_status';
		const ARG_SEARCH = 'wpcpm_roster_search';
		const ARG_STUDENT = 'wpcpm_institution_student';
		public static function render( $record_id, array $context ) {
			$GLOBALS['cards'][]      = array( 'roster', $record_id, $context );
			$GLOBALS['card_calls'][] = __CLASS__;
			// As the real roster: one student's card in place of the list when the address names a student, and its
			// three parts (the form, the status, the invitation) take their messages (Task 9).
			if ( isset( $_GET[ self::ARG_STUDENT ] ) ) {
				foreach ( array( 'class-wpcpm-institution-student-form.php', 'class-wpcpm-institution-students.php', 'class-wpcpm-institution-resend.php' ) as $part ) {
					take_message( channel_of( $part ) );
				}
			}
			echo '<div class="wpcpm-institution__card" data-card="roster"></div>';
			// As the real roster closes: with its own read line only where no module title is
			// above it. The page passes `module_titled`, so a copy here is a page that stopped.
			if ( empty( $context['module_titled'] ) ) {
				self::read_line( isset( $context['read'] ) ? $context['read'] : 0, 'wpcpm-roster__read wpcpm-roster__read--footer' );
			}
		}
		/** The wording and the formats are the roster view suite's; this is the line the page asks for. */
		public static function read_line( $read, $classes, $one = false ) {
			printf( '<p class="%s">Read from the program records on %s.</p>', $classes, gmdate( 'Y-m-d H:i', (int) $read ) );
		}
		/** As the real roster: the student whose card is drawn in place of the list, or 0 (Gate B, B6). */
		public static function card_student() {
			return isset( $_GET[ self::ARG_STUDENT ] ) ? (int) $_GET[ self::ARG_STUDENT ] : 0;
		}
		/** The cohort the Students view opens on (Task 6); the rule is the roster view suite's. */
		public static function opening_cohort( string $record ): string { return (string) ( $GLOBALS['opening_cohort'] ?? '2026-H1' ); }
		public static function cohort_from_request() {
			$asked = WPCPM_Request::text( self::ARG_COHORT );
			return WPCPM_Cohort::is_key( $asked ) ? $asked : '';
		}
		public static function filters_from_request() {
			$status = WPCPM_Request::key( self::ARG_STATUS );
			$groups = array( 'current', 'waiting', 'finished', 'not_started' );
			return array(
				'status' => in_array( $status, $groups, true ) ? $status : '',
				'search' => trim( WPCPM_Request::text( self::ARG_SEARCH ) ),
			);
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Details_Card' ) ) {
	class WPCPM_Institution_Details_Card {
		public static function render( $record_id, array $context ) {
			$GLOBALS['cards'][]      = array( 'details', $record_id, $context );
			$GLOBALS['card_calls'][] = __CLASS__;
			// As the real card: it takes the details request's message when it draws (Review Focus 5).
			take_message( WPCPM_Institution_Request::FLASH_DETAILS );
			echo '<div class="wpcpm-institution__card" data-card="details"></div>';
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_People' ) ) {
	class WPCPM_Institution_People {
		// The words the institution's history reads a member row with (`WPCPM_Institution_History`), which its own
		// suite checks against the real card.
		public static function how_label( $how ) { return 'How: ' . $how; }
		public static function reason_label( $reason ) { return 'Reason: ' . $reason; }
		public static function render( $record_id, array $context ) {
			$GLOBALS['cards'][]      = array( 'people', $record_id, $context );
			$GLOBALS['card_calls'][] = __CLASS__;
			// As the real card: its message, and those of the invitations and the tutor list it draws (Task 9).
			foreach ( array( 'class-wpcpm-institution-people.php', 'class-wpcpm-institution-invite.php', 'class-wpcpm-institution-tutors.php' ) as $part ) {
				take_message( channel_of( $part ) );
			}
			echo '<div class="wpcpm-institution__card" data-card="people"></div>';
		}
	}
}
// The semester reports (Task 5's Semester reports view): the report class the view's guard asks for, holding no
// report approved, and the screen, which prints nothing while `quiet_cards` is set.
$GLOBALS['quiet_cards'] = false;
if ( ! class_exists( 'WPCPM_Semester_Report' ) ) {
	class WPCPM_Semester_Report {
		const STATE_APPROVED = 'approved';
		/** 1.123.98: the teams the Overview's block lists, from the suite's global, none by default. */
		public static function team_counts( array $rows, $cohort ) { return $GLOBALS['team_counts'] ?? array(); }
		/** No report approved, unless a scene of the Overview (Task 6) holds some: cohort to `state` and `at`. */
		public static function reports_of( $record ) { return $GLOBALS['reports'][ (string) $record ] ?? array(); }
		public static function state( $post ) { return (string) ( $post['state'] ?? '' ); }
		public static function approved_at( $post ) { return array( 'at' => (int) ( $post['at'] ?? 0 ) ); }
	}
}
if ( ! class_exists( 'WPCPM_Semester_Report_Screen' ) ) {
	class WPCPM_Semester_Report_Screen {
		const ARG = 'wpcpm_report';
		public static function render( $record_id, array $context = array() ) {
			$GLOBALS['cards'][]      = array( 'report', $record_id, $context );
			$GLOBALS['card_calls'][] = __CLASS__;
			// As the real screen: it takes its message when it draws (Task 9).
			take_message( channel_of( 'class-wpcpm-semester-report-screen.php' ) );
			if ( empty( $GLOBALS['quiet_cards'] ) ) {
				echo '<div class="wpcpm-institution__card" data-card="report"></div>';
			}
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Agreement_Card' ) ) {
	class WPCPM_Institution_Agreement_Card {
		public static function render( $record_id, array $context ) {
			$GLOBALS['cards'][]      = array( 'agreement-card', $record_id, $context );
			$GLOBALS['card_calls'][] = __CLASS__;
			// As the real card: nothing until the agreement is settled (the foot of a settled page).
			if ( WPCPM_Institution_Agreement::is_settled( $record_id ) ) {
				echo '<div class="wpcpm-institution__card" data-card="agreement-card"></div>';
			}
		}
	}
}

$GLOBALS['sevents_on'] = true;
if ( ! class_exists( 'WPCPM_Sponsor_Event_List' ) ) {
	/**
	 * Stands in for the list: records whom it was drawn for, and on which page, and marks where. As the real list,
	 * the menu's question (`shows()`) only peeks at the outcome waiting on its channel, and the list takes and prints
	 * it when it draws.
	 */
	class WPCPM_Sponsor_Event_List {
		const BACK_INSTITUTION = 'institution';
		const FLASH            = 'sevent-list';
		public static function shows( WP_User $viewer ): bool {
			return ! empty( $GLOBALS['sevents_on'] ) || '' !== (string) WPCPM_Flash::peek( self::FLASH );
		}
		public static function render( $viewer, $back ) {
			$GLOBALS['sevents'][]    = array( $viewer instanceof WP_User ? (int) $viewer->ID : 0, (string) $back );
			$GLOBALS['card_calls'][] = __CLASS__;
			$status                  = (string) WPCPM_Flash::take( self::FLASH );
			echo '<section class="wpcpm-sevents" data-stub="sevents">' . ( '' !== $status ? '<p class="wpcpm-sevents__message">' . $status . '</p>' : '' ) . '</section>';
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Audit' ) ) {
	class WPCPM_Institution_Audit {
		// The ground a withdrawal is read by (`WPCPM_Institution_History`).
		const GROUND_MANAGER = 'manager';
		public static function entries_for( $record_id, $limit = 50 ) {
			$GLOBALS['history_reads'][] = array( 'audit', $record_id, $limit );
			return isset( $GLOBALS['audit_rows'][ $record_id ] ) ? array_slice( $GLOBALS['audit_rows'][ $record_id ], 0, $limit ) : array();
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Application' ) ) {
	class WPCPM_Institution_Application {
		public static function site_fields() {
			return array(
				'contact_role'      => array( 'label' => 'Their role at the institution' ),
				'student_languages' => array( 'label' => 'In which languages do your students study?' ),
				'signer_name'       => array( 'label' => 'Who will sign the agreement for the institution?' ),
				'signer_title'      => array( 'label' => 'Their title' ),
				'credits_page'      => array( 'label' => 'Your page about WordPress Credits' ),
			);
		}
	}
}
if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $time = null ) { return gmdate( $format, (int) $time ); }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';
// What a history row is read with: the request's kinds and states, for a closed request (the real class, which
// nothing here stands in for), and from the People card and the audit the words and the ground a member row and
// a withdrawal are read with (this suite's stand-ins for those two carry them, below). Loaded before the history,
// as the history suite loads them, so a fixture that draws such a row cannot turn into a fatal.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-history.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';

/* ---- reading a page ---- */

/** The menu's markup: the frame's `<nav>`, or ''. */
function menu_html( $html ) {
	$a = strpos( (string) $html, '<nav class="wpcpm-frame__menu"' );
	if ( false === $a ) {
		return '';
	}
	$b = strpos( $html, '</nav>', $a );
	return substr( $html, $a, ( false === $b ? strlen( $html ) : $b + 6 ) - $a );
}

/**
 * The menu's items, as the frame prints them: each `label`, `url`, `current` and `badge`, in the views' group
 * (`views`, the group with no heading) or under Help (`help`).
 *
 * @param string $html  A page.
 * @param string $group `views` or `help`.
 * @return array[]
 */
function menu_items( $html, $group = 'views' ) {
	$nav  = menu_html( $html );
	$help = strpos( $nav, 'wpcpm-frame__group--help' );
	$part = 'views' === $group ? ( false === $help ? $nav : substr( $nav, 0, $help ) ) : ( false === $help ? '' : substr( $nav, $help ) );
	preg_match_all( '#<a class="wpcpm-frame__item( is-current)?" href="([^"]*)"[^>]*>.*?<span class="wpcpm-frame__label">(.*?)(?:<span class="screen-reader-text">[^<]*</span>)?</span>(?:<span aria-hidden="true" class="wpcpm-frame__badge[^"]*">([^<]*)</span>)?#s', $part, $m, PREG_SET_ORDER );
	return array_map(
		function ( $one ) {
			return array( 'label' => html_entity_decode( $one[3], ENT_QUOTES ), 'url' => $one[2], 'current' => '' !== $one[1], 'badge' => isset( $one[4] ) ? $one[4] : '' );
		},
		$m
	);
}
function menu_labels( $html ) { return array_column( menu_items( $html ), 'label' ); }
function current_item( $html ) {
	foreach ( menu_items( $html ) as $item ) {
		if ( $item['current'] ) { return $item['label']; }
	}
	return '';
}
function h2s( $html ) {
	preg_match_all( '#<h2[^>]*>(.*?)</h2>#s', (string) $html, $m );
	return array_map( function ( $t ) { return trim( html_entity_decode( strip_tags( $t ), ENT_QUOTES ) ); }, $m[1] );
}
/** The menu as the frame places it, beside an empty main area, for the layout harness. */
function menu_case( $html ) {
	return '<div class="wpcpm-frame__body">' . menu_html( $html ) . '<div class="wpcpm-frame__main"></div></div>';
}
/** The main area's markup: what the frame's main holds. */
function main_html( $html ) {
	$a = strpos( (string) $html, '<div class="wpcpm-frame__main">' );
	return false === $a ? '' : substr( $html, $a + strlen( '<div class="wpcpm-frame__main">' ), -strlen( '</div></div></div>' ) );
}

/** The hidden fields of the form that holds the select with this id, name to value. */
function hidden_inputs( $html, $id ) {
	foreach ( preg_split( '#(?=<form )#', (string) $html ) as $part ) {
		$end  = strpos( $part, '</form>' );
		$form = false === $end ? $part : substr( $part, 0, $end );
		if ( 0 !== strpos( $form, '<form ' ) || false === strpos( $form, 'id="' . $id . '"' ) ) {
			continue;
		}
		preg_match_all( '#<input type="hidden" name="([^"]*)" value="([^"]*)" />#', $form, $m, PREG_SET_ORDER );
		$out = array();
		foreach ( $m as $one ) {
			$out[ html_entity_decode( $one[1], ENT_QUOTES ) ] = html_entity_decode( $one[2], ENT_QUOTES );
		}
		return $out;
	}
	return null;
}

/* ---- the fixture: the plan's harness's, a school whose name is one 40-character word ---- */

$rec  = 'recEXA00000000001';
$rec2 = 'recEXA00000000002';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] = array( 'v' => 1, 'read' => 1756000000, 'rows' => array() );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $rec ]  = array( 'record_id' => $rec, 'name' => 'Exampleinstituteforappliedwebengineerings', 'stage' => 'Confirmed', 'city' => 'Example City', 'country' => 'recCOUNTRYPOLAND1', 'country_name' => 'Poland', 'website' => '', 'contact_person' => 'Avery Example', 'contact_email' => 'averyveryveryverylongmailboxname1234@example.org' );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $rec2 ] = array( 'record_id' => $rec2, 'name' => 'Exampleinstituteforappliedwebengineerings Second Campus', 'stage' => 'Agreement Sent', 'city' => '', 'country' => 'recCOUNTRYPOLAND1', 'country_name' => 'Poland', 'website' => '', 'contact_person' => '', 'contact_email' => '' );
$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec ] = array( 'v' => 1, 'settled' => true, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, 'airtable_status' => 'Accepted' );
$rep   = 20;
$tutor = 21;
$admin = 4;
$GLOBALS['users'][ $rep ]   = new WP_User( $rep, 'Riley Example', 'riley@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][ $tutor ] = new WP_User( $tutor, 'Taylor Example', 'taylor@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][ $admin ] = new WP_User( $admin, 'Admin Example', 'admin@example.org', array( 'administrator' ) );
foreach ( array( $rep, $tutor ) as $uid ) {
	$GLOBALS['umeta'][ $uid ][ WPCPM_Institution_Members::META_RECORD_ID ] = $rec;
	$GLOBALS['umeta'][ $uid ][ WPCPM_Institution_Members::META_ACTIVE ]    = '1';
}
$GLOBALS['umeta'][ $tutor ][ WPCPM_Institution_Members::META_LEVEL ] = 'tutor';
$GLOBALS['users'][12] = new WP_User( 12, 'Ola Example', 'ola@example.org' );
$GLOBALS['manage']    = array( 4, 12 );
$GLOBALS['kept']      = array( $rec => array( 'assigned' => 12 ), $rec2 => array( 'assigned' => 12 ) );
$GLOBALS['flash_for'] = $rep;
$GLOBALS['sevents_on'] = false;
// The page, so the menu's items have the page's address to link to.
WPCPM_Institutions_Dashboard::ensure_page();

/** The page as one reader, with one address's arguments. */
function page_as( $uid, array $get ) {
	$GLOBALS['scripts'] = array();
	return render_as( $uid, $get );
}
/** A part of the markup, from the first match of $from to the end of the first $to after it, or ''. */
function cut( $html, $from, $to ) {
	$a = strpos( (string) $html, $from );
	if ( false === $a ) {
		return '';
	}
	$b = strpos( $html, $to, $a );
	return false === $b ? '' : substr( $html, $a, $b + strlen( $to ) - $a );
}
/** What a callable prints. */
function printed( callable $draw ) {
	ob_start();
	$draw();
	return (string) ob_get_clean();
}
/** The options of the institution switcher's select, as value, label and whether chosen. */
function options_of( $html ) {
	$select = preg_match( '#<select[^>]* name="wpcpm_institution_view".*?</select>#s', (string) $html, $found ) ? $found[0] : '';
	preg_match_all( '#<option value="([^"]*)"([^>]*)>([^<]*)</option>#', $select, $m, PREG_SET_ORDER );
	return array_map( function ( $o ) { return array( $o[1], html_entity_decode( $o[3], ENT_QUOTES ), false !== strpos( $o[2], 'selected' ) ); }, $m );
}

// The switchers the other surfaces draw, as their dashboards call the helper, and the long lists the other forms
// draw through `render_combo()`.
$sponsor_args = array(
	'id'      => 'wpcpm-sponsor-switcher',
	'name'    => 'wpcpm_sponsor_view',
	'options' => array( 'zed' => 'Zed Example Sponsor', 'acme' => 'Acme Example', 'bee' => 'Bee Example Co' ),
	'current' => 'bee',
	'label'   => 'Viewing as sponsor',
	'find'    => 'Find a sponsor',
	'none'    => 'No sponsors match that search.',
	'keep'    => array( 'wpcpm_view' => 'events' ),
);
$student_args = array(
	'id'      => 'wpcpm-student-switcher',
	'name'    => 'wpcpm_student_view',
	'options' => array( 7 => 'Robin Example', 3 => 'Alex Example' ),
	'current' => 7,
	'label'   => 'Viewing as student',
	'find'    => 'Find a student',
	'none'    => 'No students match that search.',
);
$combos = array(
	'a country'   => array( 'id' => 'wpcpm-country', 'name' => 'country', 'options' => array( '' => 'Choose a country', 'recPL' => 'Poland', 'recRO' => 'Romania' ), 'current' => '', 'label' => 'Country', 'find' => 'Find a country', 'none' => 'No countries match.', 'mark' => 'Required', 'describedby' => 'wpcpm-country-hint', 'required' => true ),
	'refused'     => array( 'id' => 'wpcpm-school', 'name' => 'school', 'options' => array( 'recA' => 'Example A', 'recB' => 'Example B' ), 'current' => 'recB', 'label' => 'Institution', 'find' => 'Find an institution', 'none' => 'None match.', 'describedby' => 'wpcpm-school-problem', 'invalid' => true ),
	'a log field' => array( 'id' => 'wpcpm-email', 'name' => 'email', 'options' => array( '' => 'All emails', 'welcome' => 'Welcome' ), 'current' => 'welcome', 'label' => 'Email', 'find' => 'Find an email', 'none' => 'No emails match.', 'count' => 'Emails in the list: %s' ),
);

// The digests of today's markup, taken from the code at d419cae0 (render_switcher() and render_combo() as at
// c0dfdf0a), each printed by the same call.
$base = array(
	'sponsor switcher'     => '65eb89b0aacdb54bc7121b67e3d76471',
	'sponsor, none chosen' => '8cb11274a7c7a4c2bfc2e81703f2a040',
	'student switcher'     => 'd77d96b4554a97cc34e62fffa574e0a0',
	'a country'            => '7babc37899b00ebdd5f466ae65c39357',
	'refused'              => '00d96d726d9524b6d7002a2ccddc8bf7',
	'a log field'          => '11af0cee92ec4687ba43015ea3dd0cbd',
);

echo "=== Every other surface prints today's markup, byte for byte ===\n";

WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
foreach ( array( 'sponsor', 'report-cards', 'wpcpm-institutions/accounts', 'wpcpm-institutions/reports', '' ) as $surface ) {
	if ( '' !== $surface ) {
		WPCPM_UI::enter( $surface );
	}
	$drawn = array(
		md5( printed( function () use ( $sponsor_args ) { WPCPM_Dashboards::render_switcher( $sponsor_args ); } ) ),
		md5( printed( function () use ( $sponsor_args ) { WPCPM_Dashboards::render_switcher( array_merge( $sponsor_args, array( 'current' => '' ) ) ); } ) ),
		md5( printed( function () use ( $student_args ) { WPCPM_Dashboards::render_switcher( $student_args ); } ) ),
	);
	foreach ( $combos as $name => $args ) {
		$drawn[] = md5( printed( function () use ( $args ) { WPCPM_Dashboards::render_combo( $args ); } ) );
	}
	if ( '' !== $surface ) {
		WPCPM_UI::leave();
	}
	ck( 'under ' . ( '' === $surface ? 'no surface' : $surface ) . ', with institution live: the switchers and the long lists as at d419cae0', $drawn, array( $base['sponsor switcher'], $base['sponsor, none chosen'], $base['student switcher'], $base['a country'], $base['refused'], $base['a log field'] ) );
}
// The Sponsor Dashboard's own switch on as well: the compact control is the Institution Dashboard's alone.
WPCPM_UI::set_live( array( 'administrator', 'institution', 'sponsor', 'report-cards' ) );
WPCPM_UI::enter( 'sponsor' );
$sponsor_live = md5( printed( function () use ( $sponsor_args ) { WPCPM_Dashboards::render_switcher( $sponsor_args ); } ) );
WPCPM_UI::leave();
WPCPM_UI::enter( 'report-cards' );
$cards_live = md5( printed( function () use ( $student_args ) { WPCPM_Dashboards::render_switcher( $student_args ); } ) );
WPCPM_UI::leave();
ck( 'with the Sponsor Dashboard and the Report Cards themselves live, their switchers are still today\'s', array( $sponsor_live, $cards_live ), array( $base['sponsor switcher'], $base['student switcher'] ) );
// A caller passing the empty choice on another surface prints none of it: today's switcher.
WPCPM_UI::enter( 'sponsor' );
$sponsor_blank = md5( printed( function () use ( $sponsor_args ) { WPCPM_Dashboards::render_switcher( array_merge( $sponsor_args, array( 'current' => '', 'blank' => 'Choose an institution' ) ) ); } ) );
WPCPM_UI::leave();
ck( 'the empty choice is the parts\' branch\'s alone: elsewhere the switcher ignores it', $sponsor_blank, $base['sponsor, none chosen'] );

echo "\n=== The compact control, on the Institution Dashboard with its surface on ===\n";

WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
$html = page_as( $admin, array( 'wpcpm_institution_view' => $rec ) );
$form = cut( $html, '<form class="wpcpm-ui-viewing"', '</form>' );
ck( 'one form, marked to submit on a pick, and no old switcher', array( substr_count( $html, '<form class="wpcpm-ui-viewing" method="get" data-wpcpm-pick-submits>' ), strpos( $html, 'wpcpm-dashboard__switcher"' ), strpos( $html, 'wpcpm-dashboard__switcher-note' ) ), array( 1, false, false ) );
ck( 'its label, beside its box, names the select', false !== strpos( $form, '<label class="wpcpm-ui-viewing__label" id="wpcpm-institution-switcher-label" for="wpcpm-institution-switcher">Viewing as institution</label>' ), true );
ck(
	'the select and the field carry the box\'s class and are described by the note, which is for screen readers',
	array(
		false !== strpos( $form, '<select class="wpcpm-ui-viewing__box" name="wpcpm_institution_view" id="wpcpm-institution-switcher" autocomplete="off" title="Exampleinstituteforappliedwebengineerings" aria-describedby="wpcpm-institution-switcher-note">' ),
		1 === preg_match( '#<input type="text" id="wpcpm-institution-switcher-input" class="wpcpm-dashboard__switcher-input wpcpm-ui-viewing__box" role="combobox"[^>]* placeholder="Find an institution" title="Exampleinstituteforappliedwebengineerings" aria-describedby="wpcpm-institution-switcher-note" />#', $form ),
		false !== strpos( $form, '<span class="screen-reader-text" id="wpcpm-institution-switcher-note">Only Administrators see this control. Choosing an institution opens its page.</span>' ),
	),
	array( true, true, true )
);
ck( 'Show is a secondary button inside the wrapper the parts\' script hides', false !== strpos( $form, '<span class="wpcpm-ui-viewing__show" data-wpcpm-noscript><button type="submit" class="wpcpm-btn wpcpm-btn--secondary">Show</button></span>' ), true );
ck( 'the schools A to Z, the one shown chosen, and no empty choice', options_of( $html ), array( array( $rec, 'Exampleinstituteforappliedwebengineerings', true ), array( $rec2, 'Exampleinstituteforappliedwebengineerings Second Campus', false ) ) );
ck( 'the parts\' script is enqueued, beside the field\'s', array( in_array( 'wpcpm-parts', $GLOBALS['scripts'], true ), in_array( WPCPM_Dashboards::SWITCHER_SCRIPT, $GLOBALS['scripts'], true ) ), array( true, true ) );
ck( 'on the Overview it keeps no view', hidden_inputs( $html, 'wpcpm-institution-switcher' ), array() );
ck( 'and on a view it keeps the view', hidden_inputs( page_as( $admin, array( 'wpcpm_institution_view' => $rec, 'wpcpm_view' => 'students' ) ), 'wpcpm-institution-switcher' ), array( 'wpcpm_view' => 'students' ) );

// Where it sits: the head's first row, after the page's one H1, on the Overview and on every view.
$GLOBALS['theme_support']['wpcpm-dashboard-frame'] = array( array( 'institution' ) );
$where = array();
foreach ( array( '', 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators' ) as $view ) {
	$page    = page_as( $admin, '' === $view ? array( 'wpcpm_institution_view' => $rec ) : array( 'wpcpm_institution_view' => $rec, 'wpcpm_view' => $view ) );
	$row     = cut( $page, '<div class="wpcpm-ui-head-row">', '</form>' );
	$where[] = array(
		'' === $view ? 'overview' : $view,
		substr_count( $page, '<h1' ),
		0 === strpos( $row, '<div class="wpcpm-ui-head-row"><h1 class="wpcpm-head__eyebrow">Institution Dashboard</h1><form class="wpcpm-ui-viewing"' ),
		strpos( $page, '<header class="wpcpm-head' ) < strpos( $page, '<form class="wpcpm-ui-viewing"' ) && strpos( $page, '<form class="wpcpm-ui-viewing"' ) < strpos( $page, '</header>' ),
		substr_count( $page, 'id="wpcpm-institution-switcher"' ),
	);
}
ck(
	'on the Overview and every view: one H1, and the control at the end of the head\'s first row, inside the head',
	$where,
	array_map( function ( $v ) { return array( $v, 1, true, true, 1 ); }, array( 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators' ) )
);
$head_case = page_as( $admin, array( 'wpcpm_institution_view' => $rec ) );
$view_case = page_as( $admin, array( 'wpcpm_institution_view' => $rec, 'wpcpm_view' => 'students' ) );
unset( $GLOBALS['theme_support']['wpcpm-dashboard-frame'] );
$bare = page_as( $admin, array( 'wpcpm_institution_view' => $rec ) );
ck( 'while the theme prints the H1, the row holds the control alone', array( substr_count( $bare, '<h1' ), false !== strpos( $bare, '<div class="wpcpm-ui-head-row"><form class="wpcpm-ui-viewing"' ) ), array( 0, true ) );

echo "\n=== The choice state: the main area's one field, empty ===\n";

$GLOBALS['theme_support']['wpcpm-dashboard-frame'] = array( array( 'institution' ) );
$choice = page_as( $admin, array( 'wpcpm_view' => 'reports' ) );
ck( 'the head says Choose an institution', h2s( $choice ), array( 'Choose an institution' ) );
ck( 'the control is under the head, not in it, and no head row holds it', array( strpos( $choice, '</header>' ) < strpos( $choice, '<form class="wpcpm-ui-viewing"' ), strpos( $choice, 'wpcpm-ui-head-row' ), substr_count( $choice, '<form class="wpcpm-ui-viewing" method="get" data-wpcpm-pick-submits>' ) ), array( true, false, 1 ) );
ck(
	'its first option names no school and is chosen; no school is chosen',
	options_of( $choice ),
	array( array( '', 'Choose an institution', true ), array( $rec, 'Exampleinstituteforappliedwebengineerings', false ), array( $rec2, 'Exampleinstituteforappliedwebengineerings Second Campus', false ) )
);
ck( 'the empty option is marked for the script, which lists it never', false !== strpos( $choice, '<option value="" data-wpcpm-blank selected=\'selected\'>Choose an institution</option>' ), true );
ck( 'the view asked for is kept', hidden_inputs( $choice, 'wpcpm-institution-switcher' ), array( 'wpcpm_view' => 'reports' ) );
$shown = page_as( $admin, array( 'wpcpm_institution_view' => '', 'wpcpm_view' => 'reports' ) );
ck( 'Show with the empty option chosen stays on the choice: no school opens, nothing is refused', array( h2s( $shown ), current_item( $shown ), $GLOBALS['card_calls'], options_of( $shown )[0] ), array( array( 'Choose an institution' ), 'Overview', array(), array( '', 'Choose an institution', true ) ) );
$choice_case = $choice;

echo "\n=== Permissions do not move ===\n";

foreach ( array( 'representative' => $rep, 'tutor' => $tutor ) as $who => $uid ) {
	foreach ( array( array(), array( 'wpcpm_institution_view' => $rec2 ), array( 'wpcpm_view' => 'students' ) ) as $get ) {
		$page = page_as( $uid, $get );
		ck(
			"a $who, " . ( $get ? http_build_query( $get ) : 'their page' ) . ': no form, no list of schools, no hidden field, and their own school',
			array( strpos( $page, 'wpcpm-ui-viewing' ), strpos( $page, 'wpcpm-institution-switcher' ), strpos( $page, 'Second Campus' ), strpos( $page, 'name="wpcpm_institution_view"' ), hidden_inputs( $page, 'wpcpm-institution-switcher' ), h2s( $page )[0] ),
			array( false, false, false, false, null, isset( $get['wpcpm_view'] ) ? 'Students' : 'Exampleinstituteforappliedwebengineerings' )
		);
	}
}
$GLOBALS['umeta'][ $rep ] = array();
ck( 'a reader who is no Administrator and no member: no form', strpos( page_as( $rep, array( 'wpcpm_institution_view' => $rec ) ), 'wpcpm-ui-viewing' ), false );
$GLOBALS['umeta'][ $rep ][ WPCPM_Institution_Members::META_RECORD_ID ] = $rec;
$GLOBALS['umeta'][ $rep ][ WPCPM_Institution_Members::META_ACTIVE ]    = '1';
// A locked school's representative, a visitor who is not logged in, and an address naming the school as an array.
$locked = 22;
$GLOBALS['users'][ $locked ] = new WP_User( $locked, 'Lee Example', 'lee@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['umeta'][ $locked ][ WPCPM_Institution_Members::META_RECORD_ID ] = $rec2;
$GLOBALS['umeta'][ $locked ][ WPCPM_Institution_Members::META_ACTIVE ]    = '1';
$notices = array();
set_error_handler(
	function ( $no, $str ) use ( &$notices ) {
		$notices[] = $str;
		return true;
	}
);
$readers = array(
	'a locked school\'s representative'                => array( $locked, array() ),
	'a locked school\'s representative naming another' => array( $locked, array( 'wpcpm_institution_view' => $rec ) ),
	'a visitor who is not logged in'                   => array( 0, array( 'wpcpm_institution_view' => $rec ) ),
	'a representative naming schools as an array'      => array( $rep, array( 'wpcpm_institution_view' => array( $rec2, $rec ) ) ),
	'a visitor naming schools as an array'             => array( 0, array( 'wpcpm_institution_view' => array( $rec2 ) ) ),
);
foreach ( $readers as $who => $reader ) {
	$page = page_as( $reader[0], $reader[1] );
	ck(
		$who . ': no form, no list of schools, no hidden field',
		array( strpos( $page, 'wpcpm-ui-viewing' ), strpos( $page, 'wpcpm-institution-switcher' ), strpos( $page, 'name="wpcpm_institution_view"' ), substr_count( $page, '<option' ) ),
		array( false, false, false, 0 )
	);
}
$array_admin = page_as( $admin, array( 'wpcpm_institution_view' => array( $rec2 ) ) );
restore_error_handler();
ck( 'an Administrator naming schools as an array is asked to choose', h2s( $array_admin ), array( 'Choose an institution' ) );
ck( 'and none of these raised a notice or a warning', $notices, array() );
ck( 'an Administrator naming a school the index does not hold is asked to choose', h2s( page_as( $admin, array( 'wpcpm_institution_view' => 'recEXA00000000099' ) ) ), array( 'Choose an institution' ) );
ck( 'and one naming a school by a value that is no record ID too', h2s( page_as( $admin, array( 'wpcpm_institution_view' => '<b>x</b>' ) ) ), array( 'Choose an institution' ) );

echo "\n=== What picking does, run by node ===\n";

$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
if ( '' === $node ) {
	echo "skip the pick was not run: node is not on the path\n";
} else {
	// An unmarked form: today's switcher, which every other caller still prints (the Sponsor Dashboard's surface named),
	// with the Institution Dashboard's own arguments, as that page printed it before its switch was on.
	$unmarked_draw = new ReflectionMethod( 'WPCPM_Institutions_Dashboard', 'render_switcher' );
	if ( PHP_VERSION_ID < 80100 ) {
		$unmarked_draw->setAccessible( true );
	}
	WPCPM_UI::enter( 'sponsor' );
	$unmarked = cut( printed( function () use ( $unmarked_draw, $rec ) { $unmarked_draw->invoke( null, $rec ); } ), '<form class="wpcpm-dashboard__switcher"', '</form>' );
	WPCPM_UI::leave();
	$pages = array(
		'marked'   => cut( $head_case, '<form class="wpcpm-ui-viewing"', '</form>' ),
		'choice'   => cut( $choice_case, '<form class="wpcpm-ui-viewing"', '</form>' ),
		'unmarked' => $unmarked,
	);
	$process = proc_open(
		escapeshellarg( $node ) . ' ' . escapeshellarg( WPCPM_PLUGIN_DIR . 'bin/js/switcher-pick.js' ) . ' ' . escapeshellarg( WPCPM_PLUGIN_DIR . 'assets/js/switcher.js' ) . ' ' . escapeshellarg( WPCPM_PLUGIN_DIR . 'assets/js/parts.js' ) . ' - 2>&1',
		array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ) ),
		$pipes
	);
	fwrite( $pipes[0], (string) json_encode( $pages ) );
	fclose( $pipes[0] );
	$out = explode( "\n", rtrim( (string) stream_get_contents( $pipes[1] ) ) );
	fclose( $pipes[1] );
	$status  = proc_close( $process );
	$ran     = 0;
	$differ  = 0;
	$summary = null;
	foreach ( $out as $line ) {
		if ( 0 === strpos( $line, 'ok   ' ) ) {
			++$ran;
			echo $line . "\n";
		} elseif ( 0 === strpos( $line, 'FAIL ' ) ) {
			++$ran;
			++$differ;
			++$fail;
			echo $line . "\n";
		} elseif ( 0 === strpos( $line, '       ' ) ) {
			echo $line . "\n";
		} elseif ( '' !== trim( $line ) ) {
			if ( preg_match( '/^(\d+) scenarios, (\d+) differ$/', trim( $line ), $counts ) ) {
				$summary = array( (int) $counts[1], (int) $counts[2] );
			}
			echo '     ' . $line . "\n";
		}
	}
	ck( 'the pick ran scenarios', $ran > 0, true );
	ck( 'and its own summary counts the scenarios and the failures shown above', $summary, array( $ran, $differ ) );
	ck( 'and node ended with a status of 0, or of 1 when a scenario differed', $status, $differ ? 1 : 0 );
}

echo "\n=== S3 (1.123.99): a long name in the control can be read ===\n";

// The select carries the chosen school's full name as its title, escaped, so a name the 320px box cuts can be read on
// hover and by assistive technology that reads titles. Nothing else in the form moves.
$rec3 = 'recEXA00000000003';
$s3_name = 'Example Institute of Applied Web Engineering & Design, North';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $rec3 ] = array( 'record_id' => $rec3, 'name' => $s3_name, 'stage' => 'Confirmed', 'city' => 'Example City', 'country' => 'recCOUNTRYPOLAND1', 'country_name' => 'Poland', 'website' => '', 'contact_person' => '', 'contact_email' => '' );
$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec3 ] = array( 'v' => 1, 'settled' => true, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, 'airtable_status' => 'Accepted' );
$GLOBALS['kept'][ $rec3 ] = array( 'assigned' => 12 );
$s3_case  = page_as( $admin, array( 'wpcpm_institution_view' => $rec3 ) );
$s3_form  = cut( $s3_case, '<form class="wpcpm-ui-viewing"', '</form>' );
$s3_title = static function ( $form ) {
	return preg_match( '#<select [^>]*\btitle="([^"]*)"#', $form, $m ) ? $m[1] : null;
};
$s3_chosen = static function ( $form ) {
	return preg_match( "#<option value=\"[^\"]*\" selected='selected'>([^<]*)</option>#", $form, $m ) ? $m[1] : null;
};
ck( 'S3: the select\'s title is the 60-character name, escaped once', array( strlen( $s3_name ), $s3_title( $s3_form ) ), array( 60, 'Example Institute of Applied Web Engineering &amp; Design, North' ) );
ck( 'S3: the title is the chosen option\'s text, on every view', array(
	$s3_title( $s3_form ) === $s3_chosen( $s3_form ),
	$s3_title( cut( page_as( $admin, array( 'wpcpm_institution_view' => $rec3, 'wpcpm_view' => 'students' ) ), '<form class="wpcpm-ui-viewing"', '</form>' ) ),
	$s3_title( cut( $head_case, '<form class="wpcpm-ui-viewing"', '</form>' ) ) === $s3_chosen( cut( $head_case, '<form class="wpcpm-ui-viewing"', '</form>' ) ),
), array( true, 'Example Institute of Applied Web Engineering &amp; Design, North', true ) );
ck( 'S3: two titles in the form, the select\'s and the same on the field the script shows in its place; the choice state, with no school chosen, has none', array(
	substr_count( $s3_form, ' title="Example Institute of Applied Web Engineering &amp; Design, North"' ),
	1 === preg_match( '#<input type="text" id="wpcpm-institution-switcher-input" [^>]*placeholder="Find an institution" title="Example Institute of Applied Web Engineering &amp; Design, North" aria-describedby#', $s3_form ),
	substr_count( $s3_form, ' title=' ),
	substr_count( cut( $choice_case, '<form class="wpcpm-ui-viewing"', '</form>' ), ' title=' ),
), array( 2, true, 2, 0 ) );
ck( 'S3: the form is the 40-character school\'s but for its names and values', str_replace( array( $rec3, 'Example Institute of Applied Web Engineering &amp; Design, North' ), array( $rec, 'Exampleinstituteforappliedwebengineerings' ), preg_replace( '#<option value="' . $rec . '">[^<]*</option>#', '', $s3_form ) ), preg_replace( '#<option value="' . $rec . '"( selected=\'selected\')?>[^<]*</option>#', '<option value="' . $rec . '" selected=\'selected\'>Exampleinstituteforappliedwebengineerings</option>', cut( $head_case, '<form class="wpcpm-ui-viewing"', '</form>' ), 1 ) );

if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Viewing as institution: Administrator viewing a 60-character school name, Overview head (S3)', main_html( $s3_case ) );
	wpcpm_layout_keep( 'Viewing as institution: Administrator viewing the 40-character school, Overview head', main_html( $head_case ) );
	wpcpm_layout_keep( 'Viewing as institution: Administrator viewing the 40-character school, Students head', main_html( $view_case ) );
	wpcpm_layout_keep( 'Viewing as institution: the choice state', main_html( $choice_case ) );
}

WPCPM_UI::set_live( null );
echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
