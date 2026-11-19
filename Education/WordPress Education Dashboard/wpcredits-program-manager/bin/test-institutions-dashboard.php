<?php
/**
 * The Institution Dashboard's shell: the page, the routing, and the locked branch.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The page is created once. `ensure_page()` adopts an existing `institution-dashboard`
 *   page before it inserts one, so a site restored from a backup does not end up with a
 *   second page at `institution-dashboard-2` that nothing links to.
 * - The title is stamped at TITLE_VERSION whatever the outcome, so a site that renamed the
 *   page by hand is not asked again on every request. A counter, not a flag: the student
 *   page found out the hard way that a boolean skips a site for ever.
 * - **The page is in the dashboards' frame, a view per menu item** (Task 5): the menu holds the views the
 *   reader may open, in order, each with the count of what waits in it, then Program updates and Help; a view
 *   draws its own cards and no other's, so a card's message waits for the view that holds the card.
 * - **The locked branch, which is the point of this piece.** A member whose Collaboration
 *   Agreement nobody has accepted is offered the Overview and the Agreement and nothing else:
 *   no roster, no People card, no agreement card. Any other address draws them the Overview and
 *   calls no card. Asserted on the markup and on the calls, because "renders nothing" is the sort
 *   of claim that quietly stops being true.
 * - An Administrator on the same unsettled institution gets every view, the banner naming the
 *   state on the Agreement view. Decision 6: the gate limits what an institution may do for
 *   itself, and refusing the manager cannot make an agreement arrive sooner.
 * - Somebody who is neither a member nor a manager is told nothing about any institution.
 *   A page that named the school it would have shown would be a membership oracle. The
 *   sentence is `WPCPM_Dashboards`'s, asserted against what that helper returns, so the
 *   three dashboards go on shipping one wording rather than three.
 * - The banner names every agreement state the summary can hold, because a manager's next
 *   move depends on which one it is, and the Airtable half is drawn only when the base has
 *   something to say.
 * - The head prefers the pipeline index to the membership stamp. Nothing refreshes the stamp
 *   after `attach()` writes it, so a stale one must never win over a row the sync read on its
 *   last run - and with no row at all the stamp is what draws the head instead of nothing.
 * - **A school reads none of the base's words.** The Stage line is the program's own pipeline
 *   word and is never drawn for a member; a member's page, locked or settled, and every view's
 *   title and lead hold no table name, view ID, record kind or "Airtable": no sentence is
 *   allowed to, the read lines included.
 * - **The read line is printed once, at the foot of the roster** on the Students view. The roster
 *   prints none inside a view that titles it; a locked member has no Students view and so none.
 * - The four cards are named as strings and guarded, so a checkout missing one of them
 *   leaves a gap rather than a fatal. Only the panel exists when the section below the
 *   fixture runs: a guard whose whole job is to survive an absent class cannot be
 *   exercised by a suite that declares every class up front.
 * - The switcher is drawn under CAP_MANAGE and for nobody else, and the cohort argument is
 *   read as text: `sanitize_key()` lowercases, and `2026-H1` is not `2026-h1`.
 * - Open question 12: an account that both mentors and acts for an institution lands on the
 *   Mentor Report Card, so `login_redirect` steps aside for it.
 * - The events from the program's sponsors (1.117.0): the list is drawn on the Events view for a contact of
 *   the institution on its own page, and never for an Administrator looking through the switcher, nor for a
 *   tutor or a locked account, whose menus have no Events.
 * - **A link to a folded part opens it** (1.123.46). The page loads the dashboards' opener, the one the
 *   Sponsor Dashboard and the Administrator Dashboard load, enqueued through the shell they share, for the
 *   locked account and the Administrator alike. What the opener does with an anchor is pinned in
 *   bin/test-dashboard-card.php.
 * - **No arrows** (builder's call 1): no view draws a module or a mover, and nothing handles the arrows'
 *   action. The shared printer (`WPCPM_Module_Order::render_module()`) keeps what it prints for the arguments it
 *   was built for.
 *
 * Run from the plugin root:  php bin/test-institutions-dashboard.php
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
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array(), $user_registered = '';
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
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
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
require_once __DIR__ . '/stubs/view-faults.php';

/* ---- the fixture --------------------------------------------------------- */

$krakow = 'recKRAKOW12345678';
$institute  = 'recINSTITUTE12345';

// Checked before anything reads them. Every guard in this module refuses a malformed ID
// silently and correctly, so a fixture one character short passes as "not a member" and
// every assertion below turns green for the wrong reason.
ck(
	'the fixture\'s record IDs are the shape Airtable uses',
	array( WPCPM_Mentors_Sync::is_record_id( $krakow ), WPCPM_Mentors_Sync::is_record_id( $institute ) ),
	array( true, true )
);

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] = array(
	'v'    => 1,
	'read' => 1756000000,
	'rows' => array(
		// The trailing space is the base's, on ten records. The header trims it; the
		// manager screen is where the stored value is reported.
		$krakow => array(
			'record_id'      => $krakow,
			'name'           => 'Politechnika Example ',
			'stage'          => 'Confirmed',
			'city'           => 'Krakow',
			'country'        => 'recCOUNTRYPOLAND1',
			'country_name'   => 'Poland',
			'website'        => 'politechnika.example',
			'contact_person' => 'Anna Kowalska',
			'contact_email'  => 'anna@example.edu',
		),
		$institute  => array(
			'record_id'      => $institute,
			'name'           => 'Institute Example',
			'stage'          => 'Confirmed',
			'city'           => 'Pune',
			'country'        => 'recCOUNTRYINDIA01',
			'country_name'   => 'India',
			'website'        => '',
			'contact_person' => '',
			'contact_email'  => 'contact@institute.example',
		),
	),
);

$GLOBALS['opts'][ 'wpcpm_roster_' . $krakow ] = array( 'v' => 1, 'read' => 1756100000, 'rows' => array() );

// Two members at Krakow, one manager, one mentor who is also a member, one bystander.
$GLOBALS['users'][2] = new WP_User( 2, 'Anna Kowalska', 'anna@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][3] = new WP_User( 3, 'Piotr Nowak', 'piotr@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][4] = new WP_User( 4, 'Program Manager', 'manager@example.test', array( 'administrator' ) );
$GLOBALS['users'][5] = new WP_User( 5, 'Someone Else', 'nobody@example.test', array( 'subscriber' ) );
$GLOBALS['users'][6] = new WP_User( 6, 'Mentor Member', 'both@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION, WPCPM_Roles::ROLE_MENTOR ) );

$GLOBALS['manage']  = array( 4 );
$GLOBALS['mentors'] = array( 6 );

foreach ( array( 2, 3, 6 ) as $member ) {
	$GLOBALS['umeta'][ $member ][ WPCPM_Institution_Members::META_RECORD_ID ] = $krakow;
	$GLOBALS['umeta'][ $member ][ WPCPM_Institution_Members::META_ACTIVE ]    = '1';
}

// The profile stamp is a snapshot taken at attach(), and nothing refreshes it afterwards:
// this one still reads the stage the record was at that day, and predates the city being
// filled in. Both halves matter below - the fresh index wins where they disagree, and the
// stamp fills what the index has no answer for.
$GLOBALS['umeta'][2][ WPCPM_Institution_Members::META_PROFILE ] = array(
	'name'           => 'Politechnika Example ',
	'city'           => '',
	'country_name'   => 'Poland',
	'stage'          => 'Agreement Sent',
	'website'        => 'https://politechnika.example/',
	'contact_person' => 'Anna Kowalska',
);

/* ---- a checkout where only the panel has landed -------------------------- */

// Phase 2's cards land as separate commits, so `card()` names each one as a string and
// returns early when the class is not there, and `context()` asks the roster view for the
// filter bar's arguments only if it can. Three of the four classes do not exist yet, which
// is the only state in which those guards do anything at all. Rendered as a manager, on the
// Agreement view (Task 5: a view per menu item), which draws the panel and the agreement card.
$out = render_as(
	4,
	array(
		'wpcpm_view'             => 'agreement',
		'wpcpm_institution_view' => $krakow,
		'wpcpm_cohort'           => '2026-H1',
		'wpcpm_roster_status'    => 'waiting',
	)
);

ck( 'a card whose class has not landed leaves a gap', cards_run(), array( 'panel' ) );
ck( 'and the page around it is still drawn', false !== strpos( $out, 'class="wpcpm-frame wpcpm-frame--institution' ), true );
ck( 'switcher and all', false !== strpos( $out, 'id="wpcpm-institution-switcher"' ), true );
ck( 'with no markup from the three that are missing', substr_count( $out, 'wpcpm-institution__card' ), 1 );

// The filter bar belongs to the roster view, so with no roster view there is nobody to ask
// and the answer is "nothing was filtered" rather than a guess of the shell's own.
$context = $GLOBALS['cards'][0][2];
ck( 'with no roster view there is no cohort to read', $context['cohort'], '' );
ck( 'and no filters either, though the URL carries both', $context['filters'], array() );
ck( 'while the read time, which is not the roster view\'s to give, still arrives', $context['read'], 1756100000 );

/* ---- the other three cards, landing after it ----------------------------- */

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
		/** The wording and the formats are the roster view suite's; this is the line the page asks for, `one` marked. */
		public static function read_line( $read, $classes, $one = false ) {
			printf( '<p class="%s"%s>Read from the program records on %s.</p>', $classes, $one ? ' data-one' : '', gmdate( 'Y-m-d H:i', (int) $read ) );
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
		public static function reports_of( $record ) { $GLOBALS['reports_of_calls'] = ( $GLOBALS['reports_of_calls'] ?? 0 ) + 1; return $GLOBALS['reports'][ (string) $record ] ?? array(); }
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

// The same renders, now that they have: the guards let the pieces land in any order, and
// this is the order they landed in, each card on the view that holds it.
ck( 'and once they land, every card is drawn, each on its view', cards_by_view( 4, array( 'wpcpm_institution_view' => $krakow ) ), array( 'students' => array( 'roster' ), 'institution' => array( 'details', 'people' ), 'agreement' => array( 'panel', 'agreement-card' ) ) );

/* ---- the page ------------------------------------------------------------ */

$page_id = WPCPM_Institutions_Dashboard::ensure_page();
ck( 'ensure_page() creates the page', $page_id > 0, true );
ck( 'and records it in the option', (int) get_option( WPCPM_Institutions_Dashboard::OPT_PAGE ), $page_id );
ck( 'at the slug that is never renamed', get_post( $page_id )->post_name, 'institution-dashboard' );
ck( 'with the title open question 14 settled', get_post( $page_id )->post_title, 'Institution Dashboard' );
ck( 'holding the block, not the shortcode', get_post( $page_id )->post_content, '<!-- wp:wpcpm/institution-dashboard /-->' );
// The stub answers 'public' for a page with no row, as WordPress does through the registered
// default; a gate that asked the value instead of `metadata_exists()` would read that as
// "already set" and leave the page public. It did, on the live site, once.
ck( 'gated to the Institution role, and not left at the registered default', array( get_post_meta( $page_id, WPCPM_Content_Access::META_KEY, true ), metadata_exists( 'post', $page_id, WPCPM_Content_Access::META_KEY ) ), array( WPCPM_Roles::ROLE_INSTITUTION, true ) );

$GLOBALS['calls'] = array();
ck( 'a second call creates nothing', WPCPM_Institutions_Dashboard::ensure_page(), $page_id );
ck( 'and inserts no post', array_filter( $GLOBALS['calls'], function ( $c ) { return 'wp_insert_post' === $c[0]; } ), array() );

// A site that has deliberately gated the page to something else keeps its own answer.
update_post_meta( $page_id, WPCPM_Content_Access::META_KEY, WPCPM_Roles::ROLE_MENTOR );
WPCPM_Institutions_Dashboard::ensure_page();
ck( 'and never re-gates a page a site has set for itself', get_post_meta( $page_id, WPCPM_Content_Access::META_KEY, true ), WPCPM_Roles::ROLE_MENTOR );
update_post_meta( $page_id, WPCPM_Content_Access::META_KEY, WPCPM_Roles::ROLE_INSTITUTION );

// The option lost, the page still there: adopted by slug rather than duplicated.
delete_option( WPCPM_Institutions_Dashboard::OPT_PAGE );
$GLOBALS['calls'] = array();
ck( 'a lost option finds the page by its slug', WPCPM_Institutions_Dashboard::ensure_page(), $page_id );
ck( 'and still inserts nothing', array_filter( $GLOBALS['calls'], function ( $c ) { return 'wp_insert_post' === $c[0]; } ), array() );

ck( 'page_url() is the permalink', WPCPM_Institutions_Dashboard::page_url(), 'https://example.test/institution-dashboard/' );

// A trashed page is not a destination: get_post_status() returns 'trash', which is truthy.
$GLOBALS['posts'][ $page_id ]->post_status = 'trash';
ck( 'a trashed page has no URL', WPCPM_Institutions_Dashboard::page_url(), '' );
$GLOBALS['posts'][ $page_id ]->post_status = 'publish';

/* ---- the title revision -------------------------------------------------- */

ck( 'the title revision is unstamped to begin with', get_option( WPCPM_Institutions_Dashboard::OPT_TITLE_FIXED ), false );
WPCPM_Institutions_Dashboard::maybe_rename_page();
ck(
	'maybe_rename_page() stamps TITLE_VERSION',
	(int) get_option( WPCPM_Institutions_Dashboard::OPT_TITLE_FIXED ),
	WPCPM_Institutions_Dashboard::TITLE_VERSION
);
ck( 'and leaves the title alone: nothing older has shipped', get_post( $page_id )->post_title, 'Institution Dashboard' );

// Renamed by hand, and asked again: the stamp is what stops it, not the title.
$GLOBALS['posts'][ $page_id ]->post_title = 'Our Students';
$GLOBALS['calls']                         = array();
WPCPM_Institutions_Dashboard::maybe_rename_page();
ck( 'a site that renamed the page is not asked twice', get_post( $page_id )->post_title, 'Our Students' );
ck( 'and nothing was written', array_filter( $GLOBALS['calls'], function ( $c ) { return 'wp_update_post' === $c[0]; } ), array() );
$GLOBALS['posts'][ $page_id ]->post_title = 'Institution Dashboard';

/* ---- the assets ---------------------------------------------------------- */

WPCPM_Institutions_Dashboard::register_assets();
ck( 'the stylesheet is registered', isset( $GLOBALS['styles'][ WPCPM_Institutions_Dashboard::STYLE ] ), true );
ck(
	'from assets/css/institution.css',
	$GLOBALS['styles'][ WPCPM_Institutions_Dashboard::STYLE ]['src'],
	WPCPM_PLUGIN_URL . 'assets/css/institution.css'
);
ck(
	'depending on the mentor stylesheet, so the shell is defined once',
	$GLOBALS['styles'][ WPCPM_Institutions_Dashboard::STYLE ]['deps'],
	array( WPCPM_Mentors_Dashboard::STYLE )
);
ck( 'which register_assets() registered for it', isset( $GLOBALS['styles'][ WPCPM_Mentors_Dashboard::STYLE ] ), true );

/* ---- logged out ---------------------------------------------------------- */

$out = render_as( 0 );
ck( 'a logged-out visitor is asked to log in', false !== strpos( $out, 'Log in to see your institution on this site.' ), true );
ck( 'and is told about no institution at all', false !== strpos( $out, 'Politechnika Example' ), false );

/* ---- a locked member: the head, the panel, and nothing else -------------- */

// Task 5: the page is a view per menu item, and a locked member's menu is the Overview and the Agreement. What
// the two draw together is what the one page drew: the head and the panel.
delete_option( 'wpcpm_agreement_' . $krakow );
$GLOBALS['scripts'] = array();
$overview           = render_as( 2 );
$overview_cards     = cards_run();
$out                = $overview . render_as( 2, array( 'wpcpm_view' => 'agreement' ) );

ck( 'a locked member gets the head', false !== strpos( $overview, '<header class="wpcpm-head' ), true );
ck( 'naming their institution, trimmed', false !== strpos( $overview, '<h2 class="wpcpm-head__title">Politechnika Example</h2>' ), true );
// Task 6: the facts are back, in the head's one line, each its own.
ck( 'with the city the index knows and the stamp does not', in_array( 'Krakow, Poland', facts( $overview ), true ), true );

// The stage is the program's own pipeline word for where an institution stands, and a school
// is not told it: neither the index's, which is the fresher of the two sources, nor the
// stamp's, which has been stale since attach() wrote it.
ck( 'a member is not shown the Stage line', false !== strpos( $out, 'Stage:' ), false );
ck( 'nor the stage the index holds', false !== strpos( $out, 'Confirmed' ), false );
ck( 'nor the one the stamp holds', false !== strpos( $out, 'Agreement Sent' ), false );
ck( 'and no stage line is drawn at all', false !== strpos( $out, 'wpcpm-institution__stage' ), false );

ck( 'a locked member\'s page holds none of the base\'s words', internal_words( $out ), array() );
ck( 'and no read line, since it has no roster to read it from', false !== strpos( $out, 'Read from the program records' ), false );

ck( 'and the panel, on the Agreement view', false !== strpos( $out, 'data-card="panel"' ), true );
ck( 'the Overview calls no card', $overview_cards, array() );
// The agreement card is asked too and, as the real card does, prints nothing while the agreement is not settled.
ck( 'and the Agreement view asks the panel and the agreement card, nothing else', cards_run(), array( 'panel', 'agreement-card' ) );
ck( 'no roster markup', false !== strpos( $out, 'data-card="roster"' ), false );
ck( 'no People card', false !== strpos( $out, 'data-card="people"' ), false );
// The details card is a gated action (`ACT_EDIT_DETAILS` is not in `ungated()`) and a school whose agreement
// is not settled sets its name on the agreement document in the panel instead (Institutions release 2).
ck( 'no details card: a school whose agreement is not settled corrects nothing here', false !== strpos( $out, 'data-card="details"' ), false );
ck( 'no agreement card', false !== strpos( $out, 'data-card="agreement-card"' ), false );
ck( 'no banner: that is the manager\'s, not theirs', false !== strpos( $out, 'wpcpm-institution__banner' ), false );
ck( 'and no switcher', false !== strpos( $out, 'wpcpm-institution-switcher' ), false );
ck( 'nor the script that narrows one', in_array( 'wpcpm-switcher', $GLOBALS['scripts'], true ), false );
ck( 'and the dashboards\' opener is loaded all the same, since the panel is a disclosure a link can land on', in_array( 'wpcpm-dashboard-open', $GLOBALS['scripts'], true ), true );
ck( 'the account-security prompt is chrome and still runs', false !== strpos( $overview, '<!-- 2fa -->' ), true );

// The same account, appending the manager's switcher argument by hand.
$out = render_as( 2, array( 'wpcpm_institution_view' => $institute ) );
ck( 'a member cannot switch institutions with a query argument', false !== strpos( $out, 'Institute Example' ), false );
ck( 'and still sees their own', false !== strpos( $out, 'Politechnika Example' ), true );

// And what the stamp is for. `WPCPM_Institutions_Index::read()` discards a stored copy at a
// version it does not know, so between a shape change and the next sync no institution has
// a row at all - and a head that went blank at that moment would be a worse answer than a
// head a few weeks old.
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['v'] = 99;
$out = render_as( 2 );
ck( 'with no index row the stamp draws the head', false !== strpos( $out, '<h2 class="wpcpm-head__title">Politechnika Example</h2>' ), true );
ck( 'and the stamp\'s stage is not printed for a member either', false !== strpos( $out, 'Stage:' ), false );
// Needled on the place *fact* rather than on the bare city word: only the index ever carried this one.
ck( 'and the city only the index ever knew is simply absent', false !== strpos( $out, 'Krakow, Poland' ), false );
ck( 'leaving the country the stamp does hold, with no stray separator', false !== strpos( $out, '<span class="wpcpm-ui-item__fact">Poland</span>' ), true );
// One fact to its own span, in the order somebody reads them: where it is, how to reach it, and who the program
// writes to. Each draws its own dot, so a missing one leaves none behind.
ck( 'the head puts each fact on its own, in that order', facts( $out ), array( 'Poland', 'politechnika.example', 'Contact: Anna Kowalska' ) );
ck( 'and no longer runs them together in a sentence', false !== strpos( $out, 'Poland Contact:' ), false );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['v'] = 1;

/* ---- a manager on the same unsettled institution ------------------------- */

$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array(
	'v'               => 1,
	'settled'         => false,
	'site_state'      => WPCPM_Institution_Agreement::SUMMARY_SUBMITTED,
	'airtable_status' => 'Awaiting review',
);

$GLOBALS['scripts'] = array();
$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => 'agreement' ) );
// Every form on the page carries data-wpcpm-once; the page has to load the script that reads it,
// or two presses of Upload file two agreements.
ck( 'the dashboard arms the double-submit guard its forms carry', in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ), true );
// A link to #wpcpm-import (the Enroll students part, folded by default) scrolls to the closed part and
// leaves it closed unless the opener the other two dashboards load is on the page.
ck( 'and loads the dashboards\' opener, once, so a link to a folded part such as #wpcpm-import opens it', array(
	in_array( 'wpcpm-dashboard-open', $GLOBALS['scripts'], true ),
	count( array_keys( $GLOBALS['scripts'], 'wpcpm-dashboard-open', true ) ),
), array( true, 1 ) );

// The banner's sentences are the Agreement view's first lines for an Administrator: the panel prints them, inside its
// anchored block (Task 21, bin/test-institution-panel.php), so the page prints no banner of its own.
$banner = implode( ' ', WPCPM_Institutions_Dashboard::banner_sentences( $krakow ) );
ck( 'a manager\'s Agreement view hands the banner to the panel: the page prints none of its own, and the panel is drawn', array( strpos( $out, 'wpcpm-institution__banner' ), false !== strpos( $out, 'data-card="panel"' ) ), array( false, true ) );
ck( 'naming the state in words', false !== strpos( $banner, 'not settled: a signed copy is waiting for review' ), true );
// Task 25, fix round 1, B1: the sentence says what a school with no settled agreement shows its people now.
ck( 'and what the school\'s people see: its representatives the Overview and the Agreement, its tutors no page, and no panel', array( false !== strpos( $banner, 'This institution\'s agreement is not settled: a signed copy is waiting for review. Its representatives see only the Overview and the Agreement, and its tutors no page.' ), false === stripos( $banner, 'agreement panel' ) ), array( true, true ) );
ck( 'and what the base says, which is the other half of the predicate', false !== strpos( $banner, 'Agreement Status as Awaiting review' ), true );
ck( 'and no other view holds the banner', array( false !== strpos( render_as( 4, array( 'wpcpm_institution_view' => $krakow ) ), 'wpcpm-institution__banner' ), false !== strpos( render_as( 4, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => 'students' ) ), 'wpcpm-institution__banner' ) ), array( false, false ) );
ck( 'and the read line once, on the Students view, as the member\'s', substr_count( render_as( 4, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => 'students' ) ), 'Read from the program records on' ), 1 );
ck( 'and gets every view\'s cards under it', cards_by_view( 4, array( 'wpcpm_institution_view' => $krakow ) ), array( 'students' => array( 'roster' ), 'institution' => array( 'details', 'people' ), 'agreement' => array( 'panel', 'agreement-card' ) ) );
render_as( 4, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => 'institution' ) );
ck( 'the details card included, drawn for the institution being viewed and not for the manager', array( $GLOBALS['cards'][0][0], $GLOBALS['cards'][0][1] ), array( 'details', $krakow ) );
$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'with the switcher', false !== strpos( $out, 'id="wpcpm-institution-switcher"' ), true );
// Task 6: the head's facts, read for an Administrator through the switcher.
ck( 'and the head falls back to the index, since a manager holds no stamp', false !== strpos( $out, 'Stage: Confirmed' ), true );
ck( 'an Administrator reading the page through the switcher still sees the Stage, last of the facts', facts( $out ), array( 'Krakow, Poland', 'politechnika.example', 'Contact: Anna Kowalska', 'Stage: Confirmed' ) );
ck( 'and a bare host from the base is given a scheme', false !== strpos( $out, 'href="https://politechnika.example"' ), true );
ck( 'and printed without one', false !== strpos( $out, '>politechnika.example</a>' ), true );
ck( 'posting the field the resolver actually reads', false !== strpos( $out, 'name="' . WPCPM_Institution_Roster::ARG_VIEW . '"' ), true );
ck( 'listing both institutions', substr_count( $out, '<option value="rec' ), 2 );
ck( 'with the one being viewed selected', false !== strpos( $out, 'value="' . $krakow . '" selected' ), true );
// The index holds Politechnika first, as Airtable does; a reader looks for Institute under I.
ck( 'listed A to Z rather than in the index\'s order', strpos( $out, 'value="' . $institute . '"' ) < strpos( $out, 'value="' . $krakow . '"' ), true );
ck( 'with the one field that finds and picks an institution', false !== strpos( $out, 'role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="wpcpm-institution-switcher-list" autocomplete="off" spellcheck="false" placeholder="Find an institution" title="Politechnika Example" aria-describedby="wpcpm-institution-switcher-note" />' ), true );
ck( 'and the note names the Administrators, for a screen reader, tied to the field', false !== strpos( $out, '<span class="screen-reader-text" id="wpcpm-institution-switcher-note">Only Administrators see this control. Choosing an institution opens its page.</span>' ), true );
ck( 'and the page loads the script that makes the field work', in_array( 'wpcpm-switcher', $GLOBALS['scripts'], true ), true );
ck( 'and the head falls back to the index, since a manager holds no stamp', false !== strpos( $out, '<h2 class="wpcpm-head__title">Politechnika Example</h2>' ), true );

// Without pretty permalinks the page is addressed by query string, which a GET form drops.
$GLOBALS['queried'] = $page_id;
$out                = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'the switcher carries the page ID when there are no pretty permalinks', false !== strpos( $out, '<input type="hidden" name="page_id" value="' . $page_id . '" />' ), true );
$GLOBALS['opts']['permalink_structure'] = '/%postname%/';
$out                                    = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'and does not when there are', false !== strpos( $out, 'name="page_id"' ), false );
unset( $GLOBALS['opts']['permalink_structure'] );
$GLOBALS['queried'] = 0;

// The switcher, honored.
$out = render_as( 4, array( 'wpcpm_institution_view' => $institute, 'wpcpm_view' => 'students' ) );
ck( 'the switcher moves a manager to another institution', false !== strpos( $out, 'Institute Example' ), true );
ck( 'and the roster it is handed is that institution\'s', $GLOBALS['cards'][0][1], $institute );

// No argument at all: no school's page, but the choice of one (Task 7), so no card runs. The roster's own fallback,
// the first institution with a live member, is left as it is for the handlers and the exports
// (bin/test-institution-roster.php).
$out = render_as( 4, array( 'wpcpm_view' => 'agreement' ) );
ck( 'with no argument a manager is asked to choose an institution, and no card runs', array( false !== strpos( $out, '<h2 class="wpcpm-head__title">Choose an institution</h2>' ), cards_run() ), array( true, array() ) );

/* ---- every state the banner can name ------------------------------------- */

/**
 * The banner a manager reads over the Krakow record, in one agreement state, on the Agreement view: the sentences the
 * panel prints there (`banner_sentences()`, the one place they are written), after the page is drawn.
 *
 * What is worth pinning is the sentence a manager reads, not the branch that produced it.
 *
 * @param string $state           A summary state.
 * @param string $airtable_status What the base records, or '' when it records nothing.
 * @return string The sentences, one after the other.
 */
function banner_for( $state, $airtable_status = 'Awaiting review' ) {
	global $krakow;

	$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array(
		'v'               => 1,
		'settled'         => false,
		'site_state'      => $state,
		'airtable_status' => $airtable_status,
	);

	render_as( 4, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => 'agreement' ) );
	return implode( ' ', WPCPM_Institutions_Dashboard::banner_sentences( $krakow ) );
}

// Every state the summary can hold, because the manager's next move depends on which one it
// is: a submitted copy is theirs to review, a `none` is the institution's to act on, and an
// `accepted` or `on_file` reaching this banner at all means the two sources disagree - which
// is neither party's move until somebody looks. A branch nobody renders is a sentence nobody
// has read.
$states = array(
	WPCPM_Institution_Agreement::SUMMARY_GENERATED => 'the template has been generated and no signed copy uploaded',
	WPCPM_Institution_Agreement::SUMMARY_SUBMITTED => 'a signed copy is waiting for review',
	WPCPM_Institution_Agreement::SUMMARY_RETURNED  => 'the last copy was returned',
	WPCPM_Institution_Agreement::SUMMARY_REVOKED   => 'revoked',
	WPCPM_Institution_Agreement::SUMMARY_ACCEPTED  => 'accepted on this site, which Airtable has not confirmed',
	WPCPM_Institution_Agreement::SUMMARY_ON_FILE   => 'on file according to this site, which Airtable has not confirmed',
	WPCPM_Institution_Agreement::SUMMARY_NONE      => 'not started',
);

foreach ( $states as $state => $words ) {
	ck(
		'the banner puts the ' . $state . ' state in words',
		false !== strpos( banner_for( $state ), 'not settled: ' . $words . '.' ),
		true
	);
}

// Read from the agreement module's own source rather than from the stub above, so the table
// cannot quietly fall behind it: a state added there with no sentence here reaches a manager
// as "not started", which is a wrong answer rather than a missing one.
preg_match_all(
	'/const SUMMARY_[A-Z_]+\s*=\s*\'([a-z_]+)\'/',
	(string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' ),
	$declared
);
ck( 'and there is no state it can hold that the banner has no words for', array_values( array_diff( $declared[1], array_keys( $states ) ) ), array() );

// The fall-through. A state this page has no branch for reads as the one the summary means
// by "nothing has happened yet", rather than leaving the sentence with a hole in it.
ck(
	'and anything it has no branch for reads as not started',
	false !== strpos( banner_for( 'astonished' ), 'not settled: not started.' ),
	true
);

// The other half of the predicate: the gate is closed when either source says so, and a
// manager reading "a signed copy is waiting" needs to know whether the base is what is
// holding it. Drawn only when the base has an answer - a line reading "records the Agreement
// Status as" with nothing after it would look like the bug it is not.
$out = banner_for( WPCPM_Institution_Agreement::SUMMARY_SUBMITTED, 'Sent to institution' );
ck( 'and names what the base records', false !== strpos( $out, 'Agreement Status as Sent to institution.' ), true );

$out = banner_for( WPCPM_Institution_Agreement::SUMMARY_SUBMITTED, '' );
ck( 'and says nothing about the base when the base says nothing', false !== strpos( $out, 'Agreement Status' ), false );
ck( 'though the banner itself is still said', '' !== $out, true );
ck( 'with the state in it', false !== strpos( $out, 'not settled: a signed copy is waiting for review.' ), true );

/* ---- a settled member ---------------------------------------------------- */

$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array(
	'v'               => 1,
	'settled'         => true,
	'site_state'      => WPCPM_Institution_Agreement::SUMMARY_ACCEPTED,
	'airtable_status' => 'Accepted',
);

$out = render_as( 2 );
ck( 'a settled member gets every view\'s cards', cards_by_view( 2 ), array( 'students' => array( 'roster' ), 'institution' => array( 'details', 'people' ), 'agreement' => array( 'panel', 'agreement-card' ) ) );
/** Every view a settled member's menu offers, drawn one after another: what the one page used to hold. */
$out = $out . implode( '', array_map( function ( $v ) { return render_as( 2, array( 'wpcpm_view' => $v ) ); }, array( 'students', 'institution', 'agreement' ) ) );

// The school's details card is the first card of the Your institution view, above the people, for a member and for an
// Administrator alike; the agreement card is the Agreement view's.
$member_view = render_as( 2, array( 'wpcpm_view' => 'institution' ) );
ck( 'the details card is drawn once, and first in Your institution, above the people', array( substr_count( $member_view, 'data-card="details"' ), 1 === preg_match( '/data-card="details".*?data-card="people"/s', $member_view ), false !== strpos( $member_view, 'data-card="agreement-card"' ) ), array( 1, true, false ) );
ck( 'handed the record and the dashboard\'s context like every card', array( $GLOBALS['cards'][0][0], $GLOBALS['cards'][0][1], $GLOBALS['cards'][0][2]['can_manage'] ), array( 'details', $krakow, false ) );

$manager_page = render_as( 4, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => 'institution' ) );
ck( 'and an Administrator is handed the same card first in the same view', array( substr_count( $manager_page, 'data-card="details"' ), 1 === preg_match( '/data-card="details".*?data-card="people"/s', $manager_page ) ), array( 1, true ) );
ck( 'with the Administrator\'s own flag', array( $GLOBALS['cards'][0][0], $GLOBALS['cards'][0][2]['can_manage'] ), array( 'details', true ) );
ck( 'and no banner, because there is nothing outstanding', false !== strpos( $out, 'wpcpm-institution__banner' ), false );
ck( 'and no switcher: it is drawn under CAP_MANAGE and nowhere else', false !== strpos( $out, 'wpcpm-institution-switcher' ), false );
ck( 'a settled member\'s page holds none of the base\'s words either', internal_words( $out ), array() );

// The page above draws stand-ins for its cards, so the rule is held to the real details card as well: its own suite
// draws it in every state it has, as a member and as an Administrator, and holds every page and every sentence it can
// say to this suite's word list (`bin/stubs/internal-words.php`), and tells this one what it found.
$words_out = array();
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/test-institution-details-card.php' ) . ' words 2>&1', $words_out );
$words_line = array_values( array_filter( $words_out, function ( $line ) { return 0 === strpos( $line, 'WORDS:' ); } ) );
ck( 'the real details card, in every state it is drawn in and every sentence it says, holds none of the base\'s words', array( count( $words_line ), $words_line ? json_decode( substr( $words_line[0], 6 ), true ) : null ), array( 1, array() ) );
ck( 'nor the Stage line, which is the Administrators\'', false !== strpos( $out, 'wpcpm-institution__stage' ), false );

// The read line is the Students view's, once, at the roster's foot: the roster is told its view titles it and
// prints none, and the view prints the one.
$students = render_as( 2, array( 'wpcpm_view' => 'students' ) );
ck( 'the roster is handed the flag that tells it its view titles it', $GLOBALS['cards'][0][2]['module_titled'], true );
ck( 'the Students view holds the read line exactly once', substr_count( $students, 'Read from the program records on' ), 1 );
ck( 'as the view\'s own line', false !== strpos( $students, '<p class="wpcpm-dashboard__updated">Read from the program records on ' ), true );
ck( 'after the roster, at its foot', strpos( $students, 'wpcpm-dashboard__updated' ) > strpos( $students, 'data-card="roster"' ), true );

// Task 28 fix round 2, X6: the menu's counts read no news. A view that is not the Overview asks for no report list; the
// Overview, for the same reader, asks for it (the news is printed there).
$GLOBALS['reports_of_calls'] = 0;
render_as( 2, array( 'wpcpm_view' => 'students' ) );
$x6_students = $GLOBALS['reports_of_calls'];
$GLOBALS['reports_of_calls'] = 0;
render_as( 2 );
ck( 'a view that is not the Overview asks for no report list, and the Overview does', array( $x6_students, $GLOBALS['reports_of_calls'] > 0 ), array( 0, true ) );
ck( 'and not from the roster\'s own footer', false !== strpos( $students, 'wpcpm-roster__read' ), false );
ck( 'and no other view of the page holds it', substr_count( $out, 'Read from the program records on' ), 1 );

// Gate B, B6: on one student's card the view's read line is the roster's, in words for one student.
$one_card = render_as( 2, array( 'wpcpm_view' => 'students', 'wpcpm_institution_student' => '12' ) );
ck( 'on a student\'s card the Students view prints its read line once, asked in words for one student; on the roster, for many', array( substr_count( $one_card, 'Read from the program records on' ), false !== strpos( $one_card, '<p class="wpcpm-dashboard__updated" data-one>' ), false !== strpos( $students, ' data-one' ) ), array( 1, true, false ) );

// The Semester reports view's sentence is the module's of before (design 12).
$leads = WPCPM_Institutions_Dashboard::leads();
ck( 'the Semester report lead says what a school gets and when it can read it', $leads[ WPCPM_Institutions_Dashboard::MODULE_REPORT ], 'The report the program prepares for your institution each semester, to read and download as a PDF once an Administrator has approved it.' );
// Task 25, fix round 2 (N7): the old modules' names and the two leads nobody saw are gone; the Semester reports lead is the one left.
ck( 'the lead holds none of the base\'s words', array_filter( array_map( 'internal_words', $leads ) ), array() );
ck( 'and what nothing read since the arrows went is gone: modules(), the students\' and the people\'s leads, their keys and FIELD_INSTITUTION', array( method_exists( 'WPCPM_Institutions_Dashboard', 'modules' ), array_keys( $leads ), defined( 'WPCPM_Institutions_Dashboard::MODULE_STUDENTS' ), defined( 'WPCPM_Institutions_Dashboard::MODULE_PEOPLE' ), defined( 'WPCPM_Institutions_Dashboard::FIELD_INSTITUTION' ) ), array( false, array( 'report' ), false, false, false ) );

/* ---- somebody who is neither --------------------------------------------- */

// Asserted against what the shared helper returns rather than against a sentence written
// out here: the three dashboards answer this question with one wording, and a page holding
// a private copy of it - which this one did - is a second wording that ships.
$out = render_as( 5 );
ck( 'a stranger is told this page is not theirs', false !== strpos( $out, WPCPM_Dashboards::nothing_to_show( 'institutions', false ) ), true );
ck( 'in the shared words and no others', false !== strpos( $out, 'not linked to one' ), false );
ck( 'and no institution is named', array( false !== strpos( $out, 'Politechnika Example' ), false !== strpos( $out, 'Institute Example' ) ), array( false, false ) );
ck( 'and no card ran', cards_run(), array() );
ck( 'the details card included', false !== strpos( $out, 'data-card="details"' ), false );

// The same, with another institution's record asked for by hand.
$out = render_as( 5, array( 'wpcpm_institution_view' => $institute ) );
ck( 'and asking for one by record ID changes nothing', false !== strpos( $out, 'Institute Example' ), false );

/* ---- a manager with nothing to show -------------------------------------- */

// The manager half of the same helper, which no other suite reaches through this page. The
// resolver falls back to the first institution *with a live member*, so an index full of
// institutions can still resolve to nothing, and the sentence points at the screen that
// provisions rather than telling anybody to run a sync.
$rows = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'];
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = array();
$out = render_as( 4 );
ck( 'a manager with no institution to show gets the shared wording too', false !== strpos( $out, WPCPM_Dashboards::nothing_to_show( 'institutions', true ) ), true );
ck( 'which is a link to the Institutions screen\'s Accounts tab, where accounts are created', false !== strpos( $out, 'admin.php?page=wpcpm-institutions&tab=accounts' ), true );
ck( 'and never says "add one from"', false !== strpos( $out, 'Add one from' ), false );
ck( 'and no card ran for them either', cards_run(), array() );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $rows;

/* ---- what the cards are handed ------------------------------------------- */

// An address with the roster's filters and no view is the Students view (Task 2's inference), which draws the roster.
render_as( 2, array( 'wpcpm_cohort' => '2026-H1', 'wpcpm_roster_status' => 'waiting', 'wpcpm_roster_search' => 'Nowak' ) );
$context = $GLOBALS['cards'][0][2];

ck( 'the cohort survives its capital H, because it is read as text', $context['cohort'], '2026-H1' );
ck( 'the filters are read once and handed over', $context['filters'], array( 'status' => 'waiting', 'search' => 'Nowak' ) );
ck( 'a member is not told they can manage', $context['can_manage'], false );
ck( 'and the read time is the roster index\'s, not now', $context['read'], 1756100000 );

// The filter bar is the roster view's, so its argument names are the ones that count. The
// shell reading names of its own would look like it worked and filter nothing.
render_as( 2, array( 'wpcpm_view' => 'students', 'wpcpm_status' => 'waiting', 'wpcpm_search' => 'Nowak' ) );
ck( 'and only under the roster view\'s own argument names', $GLOBALS['cards'][0][2]['filters'], array( 'status' => '', 'search' => '' ) );

render_as( 2, array( 'wpcpm_cohort' => 'sometime' ) );
ck( 'junk in the cohort argument reads as no cohort', $GLOBALS['cards'][0][2]['cohort'], '' );

render_as( 2, array( 'wpcpm_cohort' => WPCPM_Cohort::NONE ) );
ck( 'and the empty bucket is a cohort like any other', $GLOBALS['cards'][0][2]['cohort'], WPCPM_Cohort::NONE );

// A group the roster view does not have is not a group. The shell hands the answer straight
// to the roster, so a status it never validated would narrow the list to nothing at all.
render_as( 2, array( 'wpcpm_roster_status' => 'expelled' ) );
ck( 'an unknown group reads as no filter', $GLOBALS['cards'][0][2]['filters']['status'], '' );

render_as( 4, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => 'agreement' ) );
ck( 'a manager is told so', $GLOBALS['cards'][0][2]['can_manage'], true );

// An institution with no roster option yet: a read time of 0, not a warning.
render_as( 4, array( 'wpcpm_institution_view' => $institute, 'wpcpm_view' => 'agreement' ) );
ck( 'an institution with no index read yet reads 0', $GLOBALS['cards'][0][2]['read'], 0 );

/* ---- the optional heading ------------------------------------------------ */

$out = render_as( 2, array(), array( 'title' => 'Our students' ) );
ck( 'the block\'s heading is printed when it is set', false !== strpos( $out, '<h2 class="wpcpm-dashboard__title">Our students</h2>' ), true );
$out = render_as( 2 );
ck( 'and nothing is printed when it is not', false !== strpos( $out, 'wpcpm-dashboard__title' ), false );

/* ---- routing ------------------------------------------------------------- */

$GLOBALS['uid'] = 2;
ck(
	'a member landing on wp-login goes to their dashboard',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][2] ),
	'https://example.test/institution-dashboard/'
);
$GLOBALS['editors'][] = 2;
ck(
	'a member who can write posts still goes to their dashboard (WordPress/WPCredits#295)',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][2] ),
	'https://example.test/institution-dashboard/'
);
$GLOBALS['edit_others'][] = 2;
ck(
	'a member who can edit others\' posts, an editor, is left where WordPress sends them (1.123.59)',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][2] ),
	'https://example.test/wp-admin/'
);
$GLOBALS['edit_others'] = array();
$GLOBALS['editors'] = array_values( array_diff( $GLOBALS['editors'], array( 2 ) ) );
ck(
	'a member who asked for somewhere in particular is taken there',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/guides/getting-started/', $GLOBALS['users'][2] ),
	'https://example.test/wp-admin/'
);
ck(
	'a program manager is left in wp-admin',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][4] ),
	'https://example.test/wp-admin/'
);
ck(
	'a stranger is left alone',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][5] ),
	'https://example.test/wp-admin/'
);
// Open question 12: mentoring is the time-critical half, so the mentor page wins.
ck(
	'an account that mentors and is a member lands on the Mentor Report Card',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][6] ),
	'https://example.test/wp-admin/'
);
// The setting is what turns the routing off, and it turns off both halves.
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array( 'institution_home' => false );
ck(
	'and nobody is routed when institution_home is off',
	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][2] ),
	'https://example.test/wp-admin/'
);
unset( $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] );

/* ---- the admin dashboard ------------------------------------------------- */

/**
 * What `replace_admin_dashboard()` did, as a string.
 *
 * @param int    $uid     Who is asking.
 * @param string $pagenow The admin screen they are on.
 * @return string The redirect target, or ''.
 */
function admin_hop( $uid, $pagenow ) {
	$GLOBALS['uid']     = (int) $uid;
	$GLOBALS['pagenow'] = $pagenow;
	try {
		WPCPM_Institutions_Dashboard::replace_admin_dashboard();
	} catch ( Exception $e ) {
		return str_replace( 'redirect: ', '', $e->getMessage() );
	}
	return '';
}

ck( 'a member on the wp-admin Dashboard is moved to their page', admin_hop( 2, 'index.php' ), 'https://example.test/institution-dashboard/' );
ck( 'and is left alone on their own profile screen', admin_hop( 2, 'profile.php' ), '' );
ck( 'a program manager keeps wp-admin', admin_hop( 4, 'index.php' ), '' );
$GLOBALS['ajax'] = true;
ck( 'and an ajax request is never redirected', admin_hop( 2, 'index.php' ), '' );
$GLOBALS['ajax'] = false;

/* ---- init and register --------------------------------------------------- */

$GLOBALS['calls'] = array();
WPCPM_Institutions_Dashboard::init();
$hooks = array_map( function ( $c ) { return $c[0] . ':' . $c[1]; }, $GLOBALS['calls'] );
ck(
	'init() hooks the register, the rename, the editor style and both routes',
	array(
		in_array( 'add_action:init', $hooks, true ),
		in_array( 'add_action:enqueue_block_editor_assets', $hooks, true ),
		in_array( 'add_filter:login_redirect', $hooks, true ),
		in_array( 'add_action:admin_init', $hooks, true ),
	),
	array( true, true, true, true )
);

$GLOBALS['calls'] = array();
WPCPM_Institutions_Dashboard::register();
$names = array_map( function ( $c ) { return $c[0] . ':' . $c[1]; }, $GLOBALS['calls'] );
ck( 'register() adds the shortcode', in_array( 'add_shortcode:' . WPCPM_Institutions_Dashboard::SHORTCODE, $names, true ), true );
ck(
	'and registers the block from its own directory',
	in_array( 'register_block_type:' . WPCPM_PLUGIN_DIR . 'blocks/institution-dashboard', $names, true ),
	true
);

/* ---- the block's own files ----------------------------------------------- */

$block = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/institution-dashboard/block.json' ), true );
ck( 'block.json parses', is_array( $block ), true );
ck( 'and names the block the class does', $block['name'], WPCPM_Institutions_Dashboard::BLOCK );
ck( 'with this plugin\'s text domain', $block['textdomain'], 'wpcredits-program-manager' );
ck( 'and points at the hand-written editor script', $block['editorScript'], 'file:./editor.js' );

$asset = require WPCPM_PLUGIN_DIR . 'blocks/institution-dashboard/editor.asset.php';
ck( 'editor.asset.php declares the wp.* globals editor.js reads', $asset['dependencies'], array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ) );
ck( 'at the same version block.json carries', $asset['version'], $block['version'] );

/* ---- the menu's Help, and the person in it -------------------------------- */

echo "\n=== Where to go with a question ===\n";

// The Resources section is gone from the page (Task 5): the menu's Help holds the Institution guide, the program's
// Slack channel and the person, the Administrator the institution is assigned to, as Write to the program. The
// Countries table's contact for the country is somebody else here, and is never read.
$GLOBALS['routing'] = array(
	'recCOUNTRYPOLAND1' => array(
		'name'     => 'Poland',
		'manager'  => 'Old Contact',
		'email'    => 'old.contact@example.org',
		'calendly' => 'https://calendly.com/example/old',
	),
);
$GLOBALS['users'][12]  = new WP_User( 12, 'Ola Nowak', 'ola@example.org' );
$GLOBALS['manage'][]   = 12;
$GLOBALS['booking']    = array( 12 => 'https://calendly.com/example/intro' );
$GLOBALS['kept']       = array( $krakow => array( 'assigned' => 12 ) );

$GLOBALS['resources'] = array();
$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );

ck( 'the Resources section is no longer drawn: the menu replaces it', array( $GLOBALS['resources'], false !== strpos( $out, 'wpcpm-handbook__resources' ) ), array( array(), false ) );
ck( 'Help names the guide, the channel and the assigned Administrator\'s address', array( help_labels( $out ), menu_url( $out, 'Write to the program' ) ), array( array( 'Institution guide', '#wpcredits on Slack', 'Write to the program' ), 'mailto:ola@example.org' ) );
ck( 'and nothing of the Countries table\'s contact for the country', array(
	false !== strpos( $out, 'Old Contact' ),
	false !== strpos( $out, 'old.contact@example.org' ),
	false !== strpos( $out, 'calendly.com/example/old' ),
), array( false, false, false ) );

// An institution nobody is assigned to has nobody to write to, whatever the Countries table says for its country.
$out = render_as( 4, array( 'wpcpm_institution_view' => $institute ) );
ck( 'an unassigned institution still gets Help, without Write to the program', help_labels( $out ), array( 'Institution guide', '#wpcredits on Slack' ) );

// An Administrator who has lost the capability is not offered as the contact.
$GLOBALS['manage'] = array( 4 );
$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'an assignee who is no Administrator now is not offered to write to', array( in_array( 'Write to the program', help_labels( $out ), true ), false !== strpos( $out, 'ola@example.org' ) ), array( false, false ) );
$GLOBALS['manage'] = array( 4, 12 );

// The locked account is the one most likely to need somebody to ask.
delete_option( 'wpcpm_agreement_' . $krakow );
$locked = render_as( 2 );
ck( 'a locked account gets Help too, with its own contact in it', array( help_labels( $locked ), menu_url( $locked, 'Write to the program' ) ), array( array( 'Institution guide', '#wpcredits on Slack', 'Write to the program' ), 'mailto:ola@example.org' ) );
$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array( 'v' => 1, 'settled' => true, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, 'airtable_status' => 'Accepted' );

$GLOBALS['kept']    = array();
$GLOBALS['manage']  = array( 4 );
$GLOBALS['routing'] = array();


/* ---- the institution's own site icon ------------------------------------- */

echo "\n=== The site icon beside the name ===\n";

// Resolved from the institution's own site and never through somebody's icon service: an
// <img> pointed at one of those would tell it which institution every program manager looked
// at, and when.
$GLOBALS['transients'] = array();
$GLOBALS['fetched']    = array();
$GLOBALS['http']       = array(
	'https://politechnika.example' => array(
		'code' => 200,
		'body' => '<html><head><link rel="shortcut icon" href="/assets/crest.png"></head><body></body></html>',
	),
	'https://politechnika.example/assets/crest.png' => array(
		'code'    => 200,
		'headers' => array( 'content-type' => 'image/png' ),
	),
);

$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );

ck( 'the icon the site declares is the one shown', false !== strpos( $out, 'src="https://politechnika.example/assets/crest.png"' ), true );
ck( 'and it carries no alt text, being decoration beside the name it repeats', false !== strpos( $out, 'class="wpcpm-institution__icon" src="https://politechnika.example/assets/crest.png" alt=""' ), true );

// The server proving an icon and the reader loading it are different questions: this site can
// reach a university that a program manager on another continent cannot, and an <img> that
// fails leaves a broken-image glyph beside the name. It takes itself off the page instead.
ck( 'an icon the reader cannot load removes itself rather than showing broken', false !== strpos( $out, 'onerror="this.remove()"' ), true );
// Beside the whole head, not inside it (builder's call 10): the head's copy is one block, and an icon against
// its first line reads as a bullet on that line.
ck( 'and it sits beside the head\'s copy rather than inside it', array(
	false !== strpos( $out, '<header class="wpcpm-head wpcpm-head--logo"><img class="wpcpm-institution__icon"' ),
	strpos( $out, 'wpcpm-institution__icon' ) < strpos( $out, 'wpcpm-head__copy' ),
	strpos( $out, 'wpcpm-head__copy' ) < strpos( $out, 'wpcpm-head__title' ),
), array( true, true, true ) );
ck( 'the site was asked once and the icon proved with a HEAD', $GLOBALS['fetched'], array(
	array( 'get', 'https://politechnika.example' ),
	array( 'head', 'https://politechnika.example/assets/crest.png' ),
) );
ck( 'and the answer is cached for a week, keyed by host', array(
	count( $GLOBALS['transients'] ),
	false !== strpos( (string) key( $GLOBALS['transients'] ), 'wpcpm_icon_' ),
), array( 1, true ) );

// Asked again, nothing is fetched: the cache is the point.
$GLOBALS['fetched'] = array();
$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'a second render asks the site nothing', $GLOBALS['fetched'], array() );

// A site that declares none falls back to /favicon.ico, and a 404 there means no icon at all
// rather than a broken image where a crest should be.
$GLOBALS['transients'] = array();
$GLOBALS['fetched']    = array();
$GLOBALS['http']       = array(
	'https://politechnika.example' => array( 'code' => 200, 'body' => '<html><head></head></html>' ),
);

$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'a site with no icon shows none', false !== strpos( $out, 'wpcpm-institution__icon' ), false );
ck( 'having tried the conventional path', in_array( array( 'head', 'https://politechnika.example/favicon.ico' ), $GLOBALS['fetched'], true ), true );
ck( 'and the failure is cached too, so it is not asked again every page load', count( $GLOBALS['transients'] ), 1 );

// A head longer than the old 200KB read cap. One university in this program does not close
// its head until byte 309,827, so its icon declarations were past the cut and the resolver
// reported the site as having none - a wrong answer, cached for a week, with nothing failing
// and nothing logged. The padding here is a comment rather than junk, so the document stays a
// document and the only thing being tested is its size.
$GLOBALS['transients'] = array();
$GLOBALS['fetched']    = array();
$GLOBALS['http']       = array(
	'https://politechnika.example'                  => array(
		'code' => 200,
		'body' => '<html><head><!--' . str_repeat( 'x', 400000 ) . '--><link rel="icon" href="/assets/crest.png"></head><body></body></html>',
	),
	'https://politechnika.example/assets/crest.png' => array( 'code' => 200, 'headers' => array( 'content-type' => 'image/png' ) ),
);

$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'an icon declared past the old read cap is still found', false !== strpos( $out, 'src="https://politechnika.example/assets/crest.png"' ), true );

// Only the head declares an icon, and stopping there is what keeps the larger read cheap. A
// `<link>` in the body is a document's own business and not a declaration of the site's icon.
$GLOBALS['transients'] = array();
$GLOBALS['fetched']    = array();
$GLOBALS['http']       = array(
	'https://politechnika.example'                 => array(
		'code' => 200,
		'body' => '<html><head></head><body><link rel="icon" href="/body-crest.png"></body></html>',
	),
	'https://politechnika.example/favicon.ico'     => array( 'code' => 404 ),
	'https://politechnika.example/body-crest.png'  => array( 'code' => 200, 'headers' => array( 'content-type' => 'image/png' ) ),
);

$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'a link tag in the body is not the site\'s icon', false !== strpos( $out, 'body-crest.png' ), false );
ck( 'and the conventional path is what was tried instead', in_array( array( 'head', 'https://politechnika.example/favicon.ico' ), $GLOBALS['fetched'], true ), true );

// A 200 that is not an image is what a missing favicon usually is: a sign-in page apologizing.
$GLOBALS['transients'] = array();
$GLOBALS['http'] = array(
	'https://politechnika.example' => array( 'code' => 200, 'body' => '<html><head></head></html>' ),
	'https://politechnika.example/favicon.ico' => array( 'code' => 200, 'headers' => array( 'content-type' => 'text/html; charset=utf-8' ) ),
);
$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'an HTML page answering where an icon should be is not an icon', false !== strpos( $out, 'wpcpm-institution__icon' ), false );

// The document is not the program's, so what it points at is not trusted: an icon on somebody
// else's host would let a page this program does not own aim a dashboard wherever it liked.
$GLOBALS['transients'] = array();
$GLOBALS['http'] = array(
	'https://politechnika.example' => array(
		'code' => 200,
		'body' => '<html><head><link rel="icon" href="https://tracker.example/pixel.png"></head></html>',
	),
	'https://tracker.example/pixel.png' => array( 'code' => 200, 'headers' => array( 'content-type' => 'image/png' ) ),
);
$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'an icon on another host is refused', array(
	false !== strpos( $out, 'tracker.example' ),
	false !== strpos( $out, 'wpcpm-institution__icon' ),
), array( false, false ) );

// But refusing the declaration is not refusing the institution. Plenty of sites serve their
// logo from a CDN, which is ordinary; one of the program's own institutions declares its logo
// on Cloudinary and serves a perfectly good /favicon.ico, and showed nothing at all because
// the lookup stopped at the first candidate instead of trying the next.
$GLOBALS['transients'] = array();
$GLOBALS['http'] = array(
	'https://politechnika.example' => array(
		'code' => 200,
		'body' => '<html><head><link rel="icon" href="https://cdn.example/logo.png" type="image/png"></head></html>',
	),
	'https://cdn.example/logo.png' => array( 'code' => 200, 'headers' => array( 'content-type' => 'image/png' ) ),
	'https://politechnika.example/favicon.ico' => array( 'code' => 200, 'headers' => array( 'content-type' => 'image/x-icon' ) ),
);

$out = render_as( 4, array( 'wpcpm_institution_view' => $krakow ) );

ck( 'a logo on a CDN falls through to the site\'s own icon rather than to nothing', array(
	false !== strpos( $out, 'src="https://politechnika.example/favicon.ico"' ),
	false !== strpos( $out, 'cdn.example' ),
), array( true, false ) );

$GLOBALS['transients'] = array();
$GLOBALS['http']       = array();
$GLOBALS['fetched']    = array();


echo "\n=== The three modules' saved order, left unused (Task 5) ===\n";
// The page is a view per menu item, so there is no long page to arrange (builder's call 1): no arrows, no mover,
// no handler. A school's saved order is left in its option, and nothing reads it (Task 25, fix round 1, B4: the
// order's reader, its option's prefix and the arrows' action are gone; uninstall deletes the option by its name).
ck( 'nothing reads the saved order: no module_order(), no OPT_MODULES_PREFIX, no ACTION_MOVE', array( method_exists( 'WPCPM_Institutions_Dashboard', 'module_order' ), defined( 'WPCPM_Institutions_Dashboard::OPT_MODULES_PREFIX' ), defined( 'WPCPM_Institutions_Dashboard::ACTION_MOVE' ) ), array( false, false, false ) );
$GLOBALS['opts'][ 'wpcpm_institution_modules_' . $krakow ] = array( 'people', 'bogus', 'students' );
$manager = (int) $GLOBALS['manage'][0];
$pages   = '';
foreach ( array( 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators' ) as $v ) {
	$pages .= render_as( $manager, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => $v ) ) . render_as( 2, array( 'wpcpm_view' => $v ) );
}
ck( 'and no view, an Administrator\'s or a representative\'s, draws a module, an arrow or a mover, whatever was saved', array( substr_count( $pages, 'id="wpcpm-module-' ), substr_count( $pages, 'wpcpm-module__mover' ), substr_count( $pages, 'wpcpm_institution_module_move' ), in_array( 'wpcpm-modules', (array) $GLOBALS['scripts'], true ) ), array( 0, 0, 0, false ) );
unset( $GLOBALS['opts'][ 'wpcpm_institution_modules_' . $krakow ] );
$GLOBALS['calls'] = array();
WPCPM_Institutions_Dashboard::init();
ck( 'and nothing handles the arrows\' action any more', has_hook_call( 'admin_post_wpcpm_institution_module_move' ), false );

/**
 * The markup a module has always been drawn in by the shared printer (`WPCPM_Module_Order::render_module()`), with
 * the arguments this page gave it: written out here and not asked of the code. The page no longer draws modules;
 * the printer is shared, and these checks keep what it prints for the arguments it was built for.
 *
 * @param string $key      Module key.
 * @param string $title    The title and the arrows' name.
 * @param string $lead     The lead, or '' for none.
 * @param bool   $arrows   Whether the arrows are drawn.
 * @param bool   $up_off   Whether Move up is disabled.
 * @param bool   $down_off Whether Move down is disabled.
 * @param string $drawn    The keys of the modules the page draws, as the arrows post them.
 * @return string
 */
function module_expected( $key, $title, $lead, $arrows, $up_off, $down_off, $drawn = 'students,report,people' ) {
	global $krakow;
	$out = '<div class="wpcpm-module wpcpm-module--' . $key . '" id="wpcpm-module-' . $key . '">';
	if ( $arrows ) {
		$out .= '<form class="wpcpm-module__mover" method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-refused="The move was not kept.">'
			. '<input type="hidden" name="action" value="wpcpm_institution_module_move"><input type="hidden" name="_wpnonce" value="nonce">'
			. '<input type="hidden" name="wpcpm_module" value="' . $key . '"><input type="hidden" name="wpcpm_institution" value="' . $krakow . '"><input type="hidden" name="wpcpm_drawn" value="' . $drawn . '">'
			. '<button type="submit" class="wpcpm-module__move wpcpm-module__move--up" name="wpcpm_direction" value="up" aria-label="Move ' . $title . ' up" title="Move ' . $title . ' up" data-wpcpm-moved="' . $title . ' moved up."' . ( $up_off ? ' disabled' : '' ) . '><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M6.5 14.5 12 9l5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
			. '<button type="submit" class="wpcpm-module__move wpcpm-module__move--down" name="wpcpm_direction" value="down" aria-label="Move ' . $title . ' down" title="Move ' . $title . ' down" data-wpcpm-moved="' . $title . ' moved down."' . ( $down_off ? ' disabled' : '' ) . '><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M6.5 9.5 12 15l5.5-5.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
			. '</form>';
	}
	$out .= '<h2 class="wpcpm-student__heading wpcpm-institution__module-title">' . $title . '</h2>';
	if ( '' !== $lead ) {
		$out .= '<p class="wpcpm-student__note wpcpm-institution__module-lead">' . $lead . '</p>';
	}
	return $out . '<p>BODY</p></div>';
}
$students_lead = 'Your students on the program, by cohort and by where they are, with the export and the enrollment form.';
$report_lead   = 'The report the program prepares for your institution each semester, to read and download as a PDF once an Administrator has approved it.';
$people_lead   = 'Who at your institution can see this page, invitations to colleagues, and the Collaboration Agreement.';

echo "\n=== The printer both dashboards draw a module with (Institutions release 3) ===\n";
// The Sponsor Dashboard's four sections are drawn by the printer this dashboard's three modules are,
// `WPCPM_Module_Order::render_module()`; the two pages differ in the arguments only: the action and the
// hidden fields the arrows post, and the two classes after the shared ones.
ck( 'the printer is on the shared class', method_exists( 'WPCPM_Module_Order', 'render_module' ), true );
/**
 * What the shared printer prints for one module.
 *
 * @param array $args The printer's arguments.
 * @return string
 */
function shared_module( array $args ) {
	if ( ! method_exists( 'WPCPM_Module_Order', 'render_module' ) ) {
		return '(no printer)';
	}
	ob_start();
	WPCPM_Module_Order::render_module( $args );
	return (string) ob_get_clean();
}
$institution_args = static function ( $key, $title, $lead, $index, $count, $can_move ) {
	global $krakow;
	return array(
		'key'         => $key,
		'title'       => $title,
		'lead'        => $lead,
		'body'        => '<p>BODY</p>',
		'index'       => $index,
		'count'       => $count,
		'action'      => 'wpcpm_institution_module_move',
		'fields'      => array( 'wpcpm_institution' => $krakow, 'wpcpm_drawn' => 'students,report,people' ),
		'can_move'    => $can_move,
		'title_class' => 'wpcpm-institution__module-title',
		'lead_class'  => 'wpcpm-institution__module-lead',
	);
};
ck( 'given this dashboard\'s arguments it prints the markup this dashboard has always printed: the first module, the last, one alone, and one the reader may not arrange', array(
	shared_module( $institution_args( 'students', 'Students', $students_lead, 0, 3, true ) ),
	shared_module( $institution_args( 'people', 'Representatives and agreement', $people_lead, 2, 3, true ) ),
	shared_module( $institution_args( 'people', 'Representatives and agreement', $people_lead, 0, 1, true ) ),
	shared_module( $institution_args( 'students', 'Students', $students_lead, 0, 3, false ) ),
), array(
	module_expected( 'students', 'Students', $students_lead, true, true, false ),
	module_expected( 'people', 'Representatives and agreement', $people_lead, true, false, true ),
	module_expected( 'people', 'Representatives and agreement', $people_lead, true, true, true ),
	module_expected( 'students', 'Students', $students_lead, false, false, false ),
) );
$sponsor_module = shared_module( array(
	'key'         => 'offer',
	'title'       => 'What you offer',
	'lead'        => 'What your company gives.',
	'body'        => '<section>CARDS</section>',
	'index'       => 1,
	'count'       => 3,
	'action'      => 'wpcpm_sponsor_module_move',
	'fields'      => array( 'wpcpm_sponsor' => 'recSPONSOR0000001', 'wpcpm_extra' => 'x' ),
	'can_move'    => true,
	'title_class' => 'wpcpm-sponsor__module-title',
	'lead_class'  => 'wpcpm-sponsor__module-lead',
) );
ck( 'the action, the hidden fields and the two classes come from the arguments, the body is printed as it is given and the wrapper is the same', array(
	0 === strpos( $sponsor_module, '<div class="wpcpm-module wpcpm-module--offer" id="wpcpm-module-offer"><form class="wpcpm-module__mover"' ),
	false !== strpos( $sponsor_module, '<input type="hidden" name="action" value="wpcpm_sponsor_module_move">' ),
	false !== strpos( $sponsor_module, '<input type="hidden" name="wpcpm_module" value="offer"><input type="hidden" name="wpcpm_sponsor" value="recSPONSOR0000001"><input type="hidden" name="wpcpm_extra" value="x">' ),
	false !== strpos( $sponsor_module, 'aria-label="Move What you offer up"' ) && false !== strpos( $sponsor_module, 'aria-label="Move What you offer down"' ),
	false !== strpos( $sponsor_module, '</form><h2 class="wpcpm-student__heading wpcpm-sponsor__module-title">What you offer</h2><p class="wpcpm-student__note wpcpm-sponsor__module-lead">What your company gives.</p><section>CARDS</section></div>' ),
	false === strpos( $sponsor_module, 'wpcpm-institution__module' ),
), array( true, true, true, true, true, true ) );
ck( 'a middle module has neither arrow disabled', substr_count( $sponsor_module, ' disabled' ), 0 );
$bare = shared_module( array( 'key' => 'mentors', 'title' => 'Q & A', 'lead' => '', 'body' => '<p>BODY</p>', 'index' => 0, 'count' => 1, 'action' => 'a', 'fields' => array(), 'can_move' => false, 'title_class' => 'x', 'lead_class' => 'y' ) );
ck( 'with no lead no sentence is printed, with no right to move no arrows, and a title is escaped', $bare, '<div class="wpcpm-module wpcpm-module--mentors" id="wpcpm-module-mentors"><h2 class="wpcpm-student__heading x">Q &amp; A</h2><p>BODY</p></div>' );

echo "\n=== The events from the program's sponsors (1.117.0) ===\n";

// Declared here and not with the other cards, so every render above ran without it, as a checkout
// without the class would: the page guards the call. What the list draws is its own suite's
// (bin/test-sponsor-event-list.php); this page's part is whom it asks the list to draw for, and on which view.
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

$GLOBALS['sevents'] = array();
$overview           = render_as( 2 );
$html               = render_as( 2, array( 'wpcpm_view' => 'events' ) );
ck(
	'a contact of the institution on its own page gets the list on the Events view, once, as themselves, on the Institution Dashboard\'s key, and the Overview does not draw it',
	array( $GLOBALS['sevents'], substr_count( $html, 'data-stub="sevents"' ), strpos( $overview, 'data-stub="sevents"' ), in_array( 'Events', menu_labels( $overview ), true ) ),
	array( array( array( 2, 'institution' ) ), 1, false, true )
);

$GLOBALS['sevents'] = array();
$asked              = render_as( $manager, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => 'events' ) );
render_as( $manager, array( 'wpcpm_view' => 'events' ) );
ck( 'an Administrator looking through the switcher, or landing on the first institution, is not offered it: a Join would be their own', array( $GLOBALS['sevents'], in_array( 'Events', menu_labels( $asked ), true ), current_item( $asked ) ), array( array(), false, 'Overview' ) );

$GLOBALS['sevents'] = array();
delete_option( 'wpcpm_agreement_' . $krakow );
$html = render_as( 2, array( 'wpcpm_view' => 'events' ) );
ck( 'a locked account gets no list either, and asking for it draws the Overview', array( $GLOBALS['sevents'], strpos( $html, 'data-stub="sevents"' ), current_item( $html ) ), array( array(), false, 'Overview' ) );
$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array( 'v' => 1, 'settled' => true, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, 'airtable_status' => 'Accepted' );

// The review of Task 3: an events message pending while another view is drawn is still there when the Events view
// is drawn next, and prints there. The menu asks the list on every view, and asking only peeks.
WPCPM_Flash::set( WPCPM_Sponsor_Event_List::FLASH, 'You joined the event.', 3 );
$students_page = render_as( 3, array( 'wpcpm_view' => 'students' ) );
ck( 'an events message pending while Students is drawn is not printed there and is still pending', array( false !== strpos( $students_page, 'You joined the event.' ), WPCPM_Flash::peek( WPCPM_Sponsor_Event_List::FLASH, 3 ), in_array( 'Events', menu_labels( $students_page ), true ) ), array( false, 'You joined the event.', true ) );
$events_page = render_as( 3, array( 'wpcpm_view' => 'events' ) );
ck( 'and the Events view drawn next prints it, once, and takes it', array( substr_count( $events_page, 'You joined the event.' ), get_user_meta( 3, WPCPM_Flash::META, true ) ), array( 1, '' ) );

echo "\n=== A tutor (Institutions release 1) ===\n";

// A tutor of Krakow sees the page and arranges nothing. What each card draws for a tutor is that card's
// own suite's, since the cards here are stand-ins; this page's part is the claim, the menu and the list of events.
$GLOBALS['users'][10] = new WP_User( 10, 'Tess Tutor', 'tess@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['umeta'][10][ WPCPM_Institution_Members::META_RECORD_ID ] = $krakow;
$GLOBALS['umeta'][10][ WPCPM_Institution_Members::META_ACTIVE ]    = '1';
$GLOBALS['umeta'][10][ WPCPM_Institution_Members::META_LEVEL ]     = 'tutor';
$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array( 'v' => 1, 'settled' => true, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, 'airtable_status' => 'Accepted' );

$GLOBALS['sevents'] = array();
$GLOBALS['scripts'] = array();
$tutor_page         = render_as( 10 );
ck( 'a tutor of a school whose agreement is settled is drawn the page: the same cards a representative is drawn, view by view', cards_by_view( 10 ), cards_by_view( 2 ) );
ck( 'with the school\'s name in the head, from the index, as a member\'s is', false !== strpos( $tutor_page, '<h2 class="wpcpm-head__title">Politechnika Example</h2>' ), true );
ck( 'and a menu without Enroll students and Events', menu_labels( $tutor_page ), array( 'Overview', 'Students', 'Semester reports', 'Your institution', 'Agreement' ) );
$GLOBALS['sevents'] = array();
$asked              = render_as( 10, array( 'wpcpm_view' => 'events' ) );
ck( 'and the sponsors\' events, which a Join would put them on, are not drawn for a tutor who asks for them', array( strpos( $asked, 'data-stub="sevents"' ), $GLOBALS['sevents'], current_item( $asked ) ), array( false, array(), 'Overview' ) );
ck( 'nor is the section for Administrators', array( false !== strpos( render_as( 10, array( 'wpcpm_view' => 'administrators' ) ), 'For Administrators' ), $GLOBALS['card_calls'] ), array( false, array() ) );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array( 'v' => 1, 'settled' => false, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_NONE, 'airtable_status' => '' );
$locked_tutor = render_as( 10 );
ck( 'a tutor of a school whose agreement is not settled is told there is nothing to show, as somebody with no claim is, and is drawn no card, no panel and no agreement', array( false !== strpos( $locked_tutor, WPCPM_Dashboards::nothing_to_show( 'institutions', false ) ), cards_run() ), array( true, array() ) );
ck( 'which is the notice a stranger is given, byte for byte', $locked_tutor, render_as( 5 ) );
$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array( 'v' => 1, 'settled' => true, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, 'airtable_status' => 'Accepted' );
$GLOBALS['uid'] = 0;
unset( $GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] );

echo "\n=== The For Administrators section (1.123.29) ===\n";

// Review Focus 3. The section holds the answers the application gave, the addresses invitations
// went to, who did what and the notes an Administrator wrote: an Administrator's, through the
// switcher or on the page they land on, and never a member's, whatever state the agreement is in.
$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array( 'v' => 1, 'settled' => true, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, 'airtable_status' => 'Accepted' );

// Declared here and not with the other cards, so every render above ran without the class, as a
// checkout without it would: the page guards the call.
$before = render_as( $manager, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => 'administrators' ) );
ck( 'a checkout without the section\'s class draws the view without it, and says so', array( class_exists( 'WPCPM_Institution_History' ), false !== strpos( $before, 'id="wpcpm-for-administrators"' ), false !== strpos( $before, 'Nothing to show here yet.' ) ), array( false, false, true ) );

$GLOBALS['history_reads'] = array();
$GLOBALS['kept']          = array(
	$krakow => array(
		'contact_role'      => 'Internship coordinator',
		'student_languages' => array( 'English', 'Polish' ),
		'signer_name'       => 'Example Signer',
	),
);
$GLOBALS['audit_rows']    = array(
	$krakow => array(
		array( 'id' => 3, 'kind' => 'invite_accepted', 'actor' => 2, 'message' => 'The invitation to invitee@example.test was accepted.', 'data' => array(), 'time' => 1756300000 ),
		array( 'id' => 2, 'kind' => 'agreement_revoke', 'actor' => 4, 'message' => 'An accepted Collaboration Agreement was revoked, and the institution\'s account closed.', 'data' => array( 'note' => 'Ended with the partnership.' ), 'time' => 1756200000 ),
		array( 'id' => 1, 'kind' => 'student_linked', 'actor' => 0, 'message' => '', 'data' => array(), 'time' => 1756100000 ),
	),
);

// The section's neighbors, as the real classes keep them, stood in for to their contracts.
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

// Everything the section prints for Krakow, by the words an Administrator reads, for the pages
// that must hold none of it.
$tells = array(
	'For Administrators',
	'Only Administrators see this section',
	'wpcpm-institution__admin',
	'wpcpm-for-administrators',
	'From the application',
	'Internship coordinator',
	'English, Polish',
	'Example Signer',
	'invitee@example.test',
	'Ended with the partnership',
	'A student was linked to this institution',
	'wpcpm-admin-history',
);

/** The tell-tale words one page holds. */
function tells_in( $html ) {
	global $tells;
	return array_values( array_filter( $tells, function ( $word ) use ( $html ) { return false !== strpos( $html, $word ); } ) );
}

ck( 'the class is loaded for everything below, so a page without the section is the page\'s doing', class_exists( 'WPCPM_Institution_History' ), true );
// A member row and a closed request are read with classes this suite stands in for or loads before the history,
// so a fixture that draws one is a sentence and not a fatal.
ck(
	'the history reads a member row and a closed request with what this suite carries for them',
	array(
		WPCPM_Institution_History::sentence( array( 'kind' => 'member_added', 'message' => '', 'data' => array( 'user' => 99999, 'how' => 'manager' ) ) ),
		WPCPM_Institution_History::sentence( array( 'kind' => 'request_resolved', 'message' => '', 'ground' => 'manager', 'data' => array( 'state' => WPCPM_Institution_Request::STATE_WITHDRAWN, 'kind' => 'details' ) ) ),
	),
	array( 'A former member was added as a member. How: manager.', 'An Administrator withdrew a request.' )
);

// An Administrator, through the switcher, on the For Administrators view (Task 5), whose head is the section's
// title and line: on the parts the section says neither again (Task 22).
$GLOBALS['history_reads'] = array();
$out                      = render_as( $manager, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => 'administrators' ) );
ck( 'an Administrator viewing an institution through the switcher gets the section, once, named once, by the view\'s head', array( substr_count( $out, 'For Administrators</h' ), substr_count( $out, '<h2 class="wpcpm-head__title">For Administrators</h2>' ), substr_count( $out, 'id="wpcpm-for-administrators"' ) ), array( 1, 1, 1 ) );
ck( 'with the line that says who it is for, once, in the view\'s head', array( substr_count( $out, '<p class="wpcpm-head__lede">Only Administrators see this section.</p>' ), substr_count( $out, 'Only Administrators see this section.' ) ), array( 1, 1 ) );
ck( 'the answers the application gave', array( false !== strpos( $out, '<td class="wpcpm-ui-cell--text" role="cell">Internship coordinator</td>' ), false !== strpos( $out, '<td class="wpcpm-ui-cell--text" role="cell">English, Polish</td>' ), false !== strpos( $out, '<td class="wpcpm-ui-cell--text" role="cell">Example Signer</td>' ) ), array( true, true, true ) );
ck( 'and the history, in plain sentences, newest first, the revoke row with its note', 1 === preg_match( '/invitee@example\.test was accepted\..*account closed\. Note: Ended with the partnership\..*A student was linked to this institution\./s', $out ), true );
ck( 'read for the institution being viewed, the history up to a hundred and one rows', $GLOBALS['history_reads'], array( array( 'record', $krakow ), array( 'audit', $krakow, 101 ) ) );
ck( 'alone on its view: no card of another view, no read line', array( $GLOBALS['card_calls'], substr_count( $out, 'Read from the program records on' ) ), array( array(), 0 ) );
ck( 'and no other view of the Administrator\'s draws it', array( substr_count( render_as( $manager, array( 'wpcpm_institution_view' => $krakow ) ), 'id="wpcpm-for-administrators"' ), substr_count( render_as( $manager, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => 'students' ) ), 'id="wpcpm-for-administrators"' ) ), array( 0, 0 ) );

// The page an Administrator lands on with no switcher argument: the choice of a school (Task 7), so no school's
// section is drawn and no school's history is read.
$GLOBALS['history_reads'] = array();
$out = render_as( $manager, array( 'wpcpm_view' => 'administrators' ) );
ck( 'an Administrator on the page they land on is asked to choose, and no section is drawn or read', array( substr_count( $out, 'id="wpcpm-for-administrators"' ), $GLOBALS['history_reads'], hidden_inputs( $out, 'wpcpm-institution-switcher' ) ), array( 0, array(), array( 'wpcpm_view' => 'administrators' ) ) );

// An institution with nothing kept and nothing recorded still has the section, and says so.
$GLOBALS['history_reads'] = array();
$out                      = render_as( $manager, array( 'wpcpm_institution_view' => $institute, 'wpcpm_view' => 'administrators' ) );
ck( 'an institution with nothing kept and no history is still shown the section, with both empty states', array( substr_count( $out, 'id="wpcpm-for-administrators"' ), false !== strpos( $out, 'Nothing kept from the application.' ), false !== strpos( $out, 'Nothing has been recorded for this institution yet.' ) ), array( 1, true, true ) );
ck( 'and asked about that institution, not Krakow', $GLOBALS['history_reads'], array( array( 'record', $institute ), array( 'audit', $institute, 101 ) ) );

// An Administrator on an institution whose agreement is not settled: the banner, and the section.
$out = banner_for( WPCPM_Institution_Agreement::SUMMARY_SUBMITTED );
$adm = render_as( 4, array( 'wpcpm_institution_view' => $krakow, 'wpcpm_view' => 'administrators' ) );
ck( 'the banner over an unsettled institution does not take the section away', array( false !== strpos( $out, 'not settled: a signed copy is waiting for review.' ), substr_count( $adm, 'id="wpcpm-for-administrators"' ) ), array( true, 1 ) );
$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array( 'v' => 1, 'settled' => true, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, 'airtable_status' => 'Accepted' );

// A member's own page, and the same page asked for another institution by hand.
foreach ( array( 'own page' => array(), 'asking for Krakow by hand' => array( 'wpcpm_institution_view' => $krakow ), 'asking for the other institution' => array( 'wpcpm_institution_view' => $institute ) ) as $how => $get ) {
	$GLOBALS['history_reads'] = array();
	$out                      = render_as( 2, $get ) . render_as( 2, array_merge( $get, array( 'wpcpm_view' => 'administrators' ) ) );
	ck( 'a member\'s ' . $how . ' holds none of the section, its heading or any of its text', tells_in( $out ), array() );
	ck( 'and the page never read the history or the record to build it', $GLOBALS['history_reads'], array() );
}

// A member whose agreement is not settled: the locked page.
delete_option( 'wpcpm_agreement_' . $krakow );
$GLOBALS['history_reads'] = array();
$out                      = render_as( 2 ) . render_as( 2, array( 'wpcpm_view' => 'administrators' ) );
ck( 'a locked account\'s page holds none of the section either, and read nothing for it: asking for it draws the Overview and calls no card', array( tells_in( $out ), $GLOBALS['history_reads'], cards_run() ), array( array(), array(), array() ) );
$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array( 'v' => 1, 'settled' => true, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, 'airtable_status' => 'Accepted' );

// Nobody else.
$GLOBALS['history_reads'] = array();
$out                      = render_as( 5, array( 'wpcpm_institution_view' => $krakow ) );
ck( 'a stranger\'s page holds none of it', array( tells_in( $out ), $GLOBALS['history_reads'] ), array( array(), array() ) );
$out = render_as( 0 );
ck( 'nor does a logged-out visitor\'s', array( tells_in( $out ), $GLOBALS['history_reads'] ), array( array(), array() ) );

// A contact of the institution who is also given the capability to manage the program is an
// Administrator for this page's purposes: the section follows the capability, not the membership.
$GLOBALS['manage'][] = 2;
$out                 = render_as( 2, array( 'wpcpm_view' => 'administrators' ) );
ck( 'the section follows the capability and not the membership', substr_count( $out, 'id="wpcpm-for-administrators"' ), 1 );
$GLOBALS['manage'] = array( 4 );
$out               = render_as( 2 ) . render_as( 2, array( 'wpcpm_view' => 'administrators' ) );
ck( 'and goes with it', tells_in( $out ), array() );

echo "\n=== A test institution's own dashboard holds neither mark nor label (Ruling 7) ===\n";

// The Test mark belongs to the lists an Administrator reads; the institution's own dashboard, which its
// members and the Administrators who view it see, names it as any institution is named. A guard: it
// passes before and after Task 3, and fails the day a mark or a "(Test)" reaches this page.
$test_record = 'recTESTINSTITUT01';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $test_record ] = array(
	'record_id'      => $test_record,
	'name'           => 'TEST - Example Institution',
	'stage'          => 'Confirmed',
	'city'           => 'Pune',
	'country'        => 'recCOUNTRYINDIA01',
	'country_name'   => 'India',
	'website'        => '',
	'contact_person' => '',
	'contact_email'  => 'contact@example.test',
);
$GLOBALS['manage'] = array( 4 );
$test_page         = render_as( 4, array( 'wpcpm_institution_view' => $test_record ) );
ck( 'the test institution\'s dashboard names it, with no Test mark, no badge and no "(Test)" label',
	array(
		false !== strpos( $test_page, 'TEST - Example Institution' ),
		substr_count( $test_page, 'wpcpm-inst-mark--test' ),
		substr_count( $test_page, 'wpcpm-badge--test' ),
		substr_count( $test_page, '(Test)' ),
	),
	array( true, 0, 0, 0 ) );

// The shared parts' switch under the real constant (Task 23): the Administrator Dashboard and the Institution
// Dashboard are on. With no set_live() at all the page, for a member and for an Administrator viewing a school, is
// drawn on the parts, byte for byte the drawing with the switch turned on by hand, the switch's class on its root.
WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
$inst_on = array( render_as( 2 ), render_as( 4, array( 'wpcpm_institution_view' => $test_record ) ) );
WPCPM_UI::set_live( null );
$inst_real = array( render_as( 2 ), render_as( 4, array( 'wpcpm_institution_view' => $test_record ) ) );
ck( 'under the real switch the Institution Dashboard is drawn on the parts, for a member and for an Administrator viewing a school, the switch\'s class on its root',
	array(
		WPCPM_UI::live(),
		$inst_real === $inst_on,
		$inst_real[1] === $test_page,
		false !== strpos( $inst_real[0], '<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on">' ),
		false !== strpos( $inst_real[1], '<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on">' ),
	),
	array( array( 'administrator', 'institution' ), true, true, true, true ) );

echo "\n=== In the dashboards' frame, a view at a time (Task 5) ===\n";

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
function help_labels( $html ) { return array_column( menu_items( $html, 'help' ), 'label' ); }
function current_item( $html ) {
	foreach ( menu_items( $html ) as $item ) {
		if ( $item['current'] ) { return $item['label']; }
	}
	return '';
}
function menu_badges( $html ) {
	$out = array();
	foreach ( menu_items( $html ) as $item ) {
		if ( '' !== $item['badge'] ) { $out[ $item['label'] ] = $item['badge']; }
	}
	return $out;
}
function menu_url( $html, $label ) {
	foreach ( array_merge( menu_items( $html ), menu_items( $html, 'help' ) ) as $item ) {
		if ( $label === $item['label'] ) { return $item['url']; }
	}
	return null;
}
/** Whether every link of the views' group holds the needle (and the group has links). */
function all_menu_urls_hold( $html, $needle ) {
	$urls = array_column( menu_items( $html ), 'url' );
	return array() !== $urls && count( $urls ) === count( array_filter( $urls, function ( $u ) use ( $needle ) { return false !== strpos( $u, $needle ); } ) );
}
/** Whether any link of the menu holds the needle. */
function any_menu_url_holds( $html, $needle ) {
	foreach ( array_merge( menu_items( $html ), menu_items( $html, 'help' ) ) as $item ) {
		if ( false !== strpos( $item['url'], $needle ) ) { return true; }
	}
	return false;
}
/** The text of every H2 on a page, in order. */
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
/** Whether `init()` hooks the action. */
function has_hook_call( $hook ) {
	$GLOBALS['calls'] = array();
	WPCPM_Institutions_Dashboard::init();
	foreach ( $GLOBALS['calls'] as $call ) {
		if ( 'add_action' === $call[0] && $hook === $call[1] ) { return true; }
	}
	return false;
}
/** The page as one reader, with one address's arguments. */
function page_as( $uid, array $get ) { return render_as( $uid, $get ); }
/** A message held for one card on the representative's channel, as its handler holds it. */
function set_flash( $channel, $value ) { WPCPM_Flash::set( $channel, $value, (int) $GLOBALS['flash_for'] ); }
/** Whether a card's message is still stored, read from the table and not through the flash, whose memo keeps a taken one. */
function has_flash( $channel ) {
	$pending = get_user_meta( (int) $GLOBALS['flash_for'], WPCPM_Flash::META, true );
	return is_array( $pending ) && array_key_exists( $channel, $pending );
}
/**
 * The page while a checked list of students waits at a school, and, when given, the agreement in a state: what the
 * menu counts.
 *
 * @param int    $uid    Who.
 * @param string $record The school.
 * @param string $state  An agreement summary state to hold it in, not settled, or '' to leave it.
 * @return string
 */
function page_as_with_waiting( $uid, $record, $state = '' ) {
	$GLOBALS['active_batch'][ $record ] = 7;
	$GLOBALS['batches'][7]              = array( 'state' => WPCPM_Institution_Import::STATE_STAGED, 'institution' => $record, 'rows' => array() );
	$kept                               = get_option( 'wpcpm_agreement_' . $record );
	if ( '' !== $state ) {
		$GLOBALS['opts'][ 'wpcpm_agreement_' . $record ] = array( 'v' => 1, 'settled' => false, 'site_state' => $state, 'airtable_status' => '' );
	}
	$html = render_as( $uid, user_can( $uid, WPCPM_Roles::CAP_MANAGE ) ? array( 'wpcpm_institution_view' => $record ) : array() );
	$GLOBALS['opts'][ 'wpcpm_agreement_' . $record ] = $kept;
	$GLOBALS['active_batch'] = array();
	$GLOBALS['batches']      = array();
	return $html;
}
/** The page while the program has an update for institutions. */
function page_as_with_update( $uid, array $get = array() ) {
	$post              = new WP_Post();
	$post->ID          = 9001;
	$post->post_name   = 'an-update';
	$post->post_type   = 'wpcpm_update';
	$GLOBALS['posts'][9001] = $post;
	$GLOBALS['updates']     = array( $post );
	$GLOBALS['updates_asked'] = array();
	$html = render_as( $uid, $get );
	$GLOBALS['updates'] = array();
	unset( $GLOBALS['posts'][9001] );
	return $html;
}
/** A view whose cards print nothing. */
function page_as_no_cards( $uid, $view ) {
	$GLOBALS['quiet_cards'] = true;
	$html = render_as( $uid, array( 'wpcpm_view' => $view ) );
	$GLOBALS['quiet_cards'] = false;
	return $html;
}

// The fixture of the plan's harness: a school whose name is one 40-character word, settled; a second, locked; a
// representative and a tutor of the first, a representative of the second, and the Administrator.
$rec    = 'recEXA00000000001';
$rec2   = 'recEXA00000000002';
$page   = 'https://example.test/institution-dashboard/';
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $rec ]  = array( 'record_id' => $rec, 'name' => 'Exampleinstituteforappliedwebengineerings', 'stage' => 'Confirmed', 'city' => 'Example City', 'country' => 'recCOUNTRYPOLAND1', 'country_name' => 'Poland', 'website' => '', 'contact_person' => 'Avery Example', 'contact_email' => 'averyveryveryverylongmailboxname1234@example.org' );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $rec2 ] = array( 'record_id' => $rec2, 'name' => 'Exampleinstituteforappliedwebengineerings Second Campus', 'stage' => 'Agreement Sent', 'city' => '', 'country' => 'recCOUNTRYPOLAND1', 'country_name' => 'Poland', 'website' => '', 'contact_person' => '', 'contact_email' => '' );
$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec ] = array( 'v' => 1, 'settled' => true, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, 'airtable_status' => 'Accepted' );
delete_option( 'wpcpm_agreement_' . $rec2 );
$rep    = 20;
$tutor  = 21;
$locked = 22;
$admin  = 4;
$GLOBALS['users'][ $rep ]    = new WP_User( $rep, 'Riley Example', 'riley@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][ $tutor ]  = new WP_User( $tutor, 'Taylor Example', 'taylor@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][ $locked ] = new WP_User( $locked, 'Lee Example', 'lee@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
foreach ( array( $rep => $rec, $tutor => $rec, $locked => $rec2 ) as $uid => $school ) {
	$GLOBALS['umeta'][ $uid ][ WPCPM_Institution_Members::META_RECORD_ID ] = $school;
	$GLOBALS['umeta'][ $uid ][ WPCPM_Institution_Members::META_ACTIVE ]    = '1';
}
$GLOBALS['umeta'][ $tutor ][ WPCPM_Institution_Members::META_LEVEL ] = 'tutor';
// The school is assigned to an Administrator with an address: Write to the program.
$GLOBALS['users'][12] = new WP_User( 12, 'Ola Nowak', 'ola@example.org' );
$GLOBALS['manage']    = array( 4, 12 );
$GLOBALS['kept']      = array( $rec => array( 'assigned' => 12 ), $rec2 => array( 'assigned' => 12 ) );
$GLOBALS['flash_for'] = $rep;
$GLOBALS['sevents_on'] = true;

$html = page_as( $rep, array() );
ck( 'the page is in the frame', substr_count( $html, 'class="wpcpm-frame wpcpm-frame--institution' ), 1 );
ck( 'menu items, in order', menu_labels( $html ), array( 'Overview', 'Students', 'Enroll students', 'Semester reports', 'Your institution', 'Agreement', 'Events' ) );
// Task 25, fix round 1, B4: the arrows' action by its name, now that the constant is gone.
ck( 'no arrows', array( strpos( $html, 'Move Students up' ), strpos( $html, 'wpcpm_institution_module_move' ) ), array( false, false ) );
ck( 'the move handler is gone', has_hook_call( 'admin_post_wpcpm_institution_module_move' ), false );
ck( 'the Overview calls no card', $GLOBALS['card_calls'], array() );
ck( 'the Overview is the current item, inside the page\'s root, in the frame\'s main', array( current_item( $html ), 0 === strpos( main_html( $html ), '<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on"><!-- 2fa -->' ) ), array( 'Overview', true ) );
ck( 'its head is the school\'s name, and no H1 while the theme draws the page', array( h2s( $html ), false !== strpos( $html, '<h1' ) ), array( array( 'Exampleinstituteforappliedwebengineerings', 'Waiting for you', 'Your contact at the program' ), false ) );
ck( 'every view item is the view\'s own address, the Overview the bare page', array_column( menu_items( $html ), 'url' ), array( $page, $page . '?wpcpm_view=students', $page . '?wpcpm_view=enroll', $page . '?wpcpm_view=reports', $page . '?wpcpm_view=institution', $page . '?wpcpm_view=agreement', $page . '?wpcpm_view=events' ) );
ck( 'the icons, as design 15 names them', array( substr_count( menu_html( $html ), 'wpcpm-icon--home' ), substr_count( menu_html( $html ), 'wpcpm-icon--users' ), substr_count( menu_html( $html ), 'wpcpm-icon--profile' ), substr_count( menu_html( $html ), 'wpcpm-icon--chart' ), substr_count( menu_html( $html ), 'wpcpm-icon--building' ), substr_count( menu_html( $html ), 'wpcpm-icon--file' ), substr_count( menu_html( $html ), 'wpcpm-icon--calendar' ), substr_count( menu_html( $html ), 'wpcpm-icon--book' ), substr_count( menu_html( $html ), 'wpcpm-icon--slack' ), substr_count( menu_html( $html ), 'wpcpm-icon--email' ) ), array( 1, 1, 1, 1, 1, 1, 1, 1, 1, 1 ) );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Institution Dashboard, representative: menu', menu_case( $html ) );
	wpcpm_layout_keep( 'Institution Dashboard, representative: Overview', main_html( $html ) );
}
$GLOBALS['theme_support']['wpcpm-dashboard-frame'] = array( array( 'institution' ) );
$owned = page_as( $rep, array() );
ck( 'the page\'s one H1 is its name, when the theme leaves the page to the frame', array( substr_count( $owned, '<h1' ), false !== strpos( $owned, '<h1 class="wpcpm-head__eyebrow">Institution Dashboard</h1>' ) ), array( 1, true ) );
unset( $GLOBALS['theme_support']['wpcpm-dashboard-frame'] );

$html = page_as( $rep, array( 'wpcpm_view' => 'students' ) );
ck( 'Students draws the roster', $GLOBALS['card_calls'], array( 'WPCPM_Institution_Roster_View' ) );
ck( 'the read line once, at its foot', substr_count( $html, 'wpcpm-dashboard__updated' ), 1 );
ck( 'one H2, the view title', h2s( $html ), array( 'Students' ) );
ck( 'with its sentence', false !== strpos( $html, '<p class="wpcpm-head__lede">Your students on the program, by cohort and by where they are, with the list you can download.</p>' ), true );
ck( 'the item is current', current_item( $html ), 'Students' );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Institution Dashboard, representative: Students', main_html( $html ) );
}
// Task 15: on the parts the Students view still tells the roster that the view titles it, so its name is the view's
// head alone (Gate A's carry: one name per page) and the read line is printed once, by the view, under the roster.
WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
$GLOBALS['cards'] = array();
$students_on      = page_as( $rep, array( 'wpcpm_view' => 'students' ) );
WPCPM_UI::set_live( null );
$roster_call = array_values( array_filter( $GLOBALS['cards'], static function ( $c ) { return 'roster' === $c[0]; } ) );
ck( 'on the parts, Students hands the roster the titled view, prints its name once, the read line once at its foot', array(
	isset( $roster_call[0][2]['module_titled'] ) ? $roster_call[0][2]['module_titled'] : null,
	h2s( $students_on ),
	substr_count( $students_on, 'wpcpm-dashboard__updated' ),
	strpos( $students_on, 'wpcpm-dashboard__updated' ) > strpos( $students_on, 'data-card="roster"' ),
	false !== strpos( $students_on, 'wpcpm-ui-on' ),
), array( true, array( 'Students' ), 1, true, true ) );
page_as( $rep, array( 'wpcpm_view' => 'institution' ) );
ck( 'Your institution', $GLOBALS['card_calls'], array( 'WPCPM_Institution_Details_Card', 'WPCPM_Institution_People' ) );

// Each view: its title and sentence (design 12), and its cards.
$views_expected = array(
	'enroll'    => array( 'Enroll students', 'Add students to the program, one at a time or from a list.', array( 'WPCPM_Institution_Import_Form' ) ),
	'reports'   => array( 'Semester reports', 'The report the program prepares for your institution each semester, to read and download as a PDF once an Administrator has approved it.', array( 'WPCPM_Semester_Report_Screen' ) ),
	'agreement' => array( 'Collaboration Agreement', 'The agreement between your institution and the program, and the signed copy on file.', array( 'WPCPM_Institution_Panel', 'WPCPM_Institution_Agreement_Card' ) ),
	'events'    => array( 'Events', 'Events the program&#039;s sponsors host, to join with your colleagues.', array( 'WPCPM_Sponsor_Event_List' ) ),
);
foreach ( $views_expected as $v => $want ) {
	$html = page_as( $rep, array( 'wpcpm_view' => $v ) );
	ck( "the $v view: its title, its sentence and its cards", array( h2s( $html ), false !== strpos( $html, '<p class="wpcpm-head__lede">' . $want[1] . '</p>' ), $GLOBALS['card_calls'], current_item( $html ) ), array( array( $want[0] ), true, $want[2], menu_labels( $html )[ array_search( $v, array( 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'events' ), true ) ] ) );
}

// Review Focus 3.
foreach ( array( array( 'wpcpm_view' => 'students' ), array( 'wpcpm_view' => 'enroll' ), array( 'wpcpm_institution_student' => '12' ), array( 'wpcpm_report' => '2026-H1' ) ) as $q ) {
	$html = page_as( $locked, $q );
	ck( 'locked, ' . http_build_query( $q ) . ': the Overview, no card', array( current_item( $html ), $GLOBALS['card_calls'] ), array( 'Overview', array() ) );
}
$html = page_as( $locked, array() );
ck( 'a locked menu', menu_labels( $html ), array( 'Overview', 'Agreement' ) );
$agreement = page_as( $locked, array( 'wpcpm_view' => 'agreement' ) );
ck( 'a locked school\'s Agreement view draws the panel', array( current_item( $agreement ), $GLOBALS['card_calls'] ), array( 'Agreement', array( 'WPCPM_Institution_Panel', 'WPCPM_Institution_Agreement_Card' ) ) );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Institution Dashboard, locked representative: menu', menu_case( $html ) );
	wpcpm_layout_keep( 'Institution Dashboard, locked representative: Overview', main_html( $html ) );
	wpcpm_layout_keep( 'Institution Dashboard, locked representative: Agreement', main_html( $agreement ) );
}

// Review Focus 4.
foreach ( array( 'enroll', 'events' ) as $v ) {
	ck( "a tutor asking for $v gets the Overview", current_item( page_as( $tutor, array( 'wpcpm_view' => $v ) ) ), 'Overview' );
}
$html = page_as( $tutor, array() );
ck( 'a tutor\'s menu', menu_labels( $html ), array( 'Overview', 'Students', 'Semester reports', 'Your institution', 'Agreement' ) );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Institution Dashboard, tutor: menu', menu_case( $html ) );
	wpcpm_layout_keep( 'Institution Dashboard, tutor: Overview', main_html( $html ) );
	wpcpm_layout_keep( 'Institution Dashboard, tutor: Your institution', main_html( page_as( $tutor, array( 'wpcpm_view' => 'institution' ) ) ) );
}

// Review Focus 5. A representative of their own, since a message once taken stays taken for the request (the
// flash's memo), and the cards above have drawn for the first one. The stand-ins take their messages as the real
// cards do, so a view that drew either card would take it.
$GLOBALS['users'][23] = new WP_User( 23, 'Robin Example', 'robin@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['umeta'][23][ WPCPM_Institution_Members::META_RECORD_ID ] = $rec;
$GLOBALS['umeta'][23][ WPCPM_Institution_Members::META_ACTIVE ]    = '1';
$GLOBALS['flash_for'] = 23;
set_flash( WPCPM_Institution_Import_Form::FLASH, array( 'status' => 'checked' ) );
set_flash( WPCPM_Institution_Request::FLASH_DETAILS, array( 'status' => 'sent' ) );
page_as( 23, array() );
ck( 'the Overview leaves the cards\' flashes', array( has_flash( WPCPM_Institution_Import_Form::FLASH ), has_flash( WPCPM_Institution_Request::FLASH_DETAILS ) ), array( true, true ) );
page_as( 23, array( 'wpcpm_view' => 'students' ) );
ck( 'and so does a view that draws neither card', array( has_flash( WPCPM_Institution_Import_Form::FLASH ), has_flash( WPCPM_Institution_Request::FLASH_DETAILS ) ), array( true, true ) );
page_as( 23, array( 'wpcpm_view' => 'enroll' ) );
ck( 'and the view that holds the form takes the form\'s alone', array( has_flash( WPCPM_Institution_Import_Form::FLASH ), has_flash( WPCPM_Institution_Request::FLASH_DETAILS ) ), array( false, true ) );
page_as( 23, array( 'wpcpm_view' => 'institution' ) );
ck( 'and Your institution the details card\'s', has_flash( WPCPM_Institution_Request::FLASH_DETAILS ), false );

// Task 9, every channel (Review Focus 5): each card's message waits while another view is drawn, the Overview first,
// and is printed once by the view its handler's way back opens. A reader of their own for each, since the flash
// remembers what it gave for the rest of the request (an empty answer too). The student's three parts print on the
// Students view while it shows that student, the address a press on the card returns to.
$student_view = array( 'wpcpm_view' => 'students', 'wpcpm_institution_student' => '12' );
$channels     = array(
	array( WPCPM_Institution_Import_Form::FLASH, array( 'wpcpm_view' => 'enroll' ) ),
	array( WPCPM_Institution_Request::FLASH_DETAILS, array( 'wpcpm_view' => 'institution' ) ),
	array( channel_of( 'class-wpcpm-institution-people.php' ), array( 'wpcpm_view' => 'institution' ) ),
	array( channel_of( 'class-wpcpm-institution-invite.php' ), array( 'wpcpm_view' => 'institution' ) ),
	array( channel_of( 'class-wpcpm-institution-tutors.php' ), array( 'wpcpm_view' => 'institution' ) ),
	array( channel_of( 'class-wpcpm-semester-report-screen.php' ), array( 'wpcpm_view' => 'reports' ) ),
	array( channel_of( 'class-wpcpm-institution-student-form.php' ), $student_view ),
	array( channel_of( 'class-wpcpm-institution-students.php' ), $student_view ),
	array( channel_of( 'class-wpcpm-institution-resend.php' ), $student_view ),
	array( channel_of( 'class-wpcpm-institutions.php' ), array( 'wpcpm_view' => 'agreement' ) ),
);
$reader       = 30;
$events_drawn = array();
foreach ( $channels as $row ) {
	list( $channel, $home ) = $row;
	$GLOBALS['users'][ $reader ] = new WP_User( $reader, 'Reader Example', 'reader' . $reader . '@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
	$GLOBALS['umeta'][ $reader ][ WPCPM_Institution_Members::META_RECORD_ID ] = $rec;
	$GLOBALS['umeta'][ $reader ][ WPCPM_Institution_Members::META_ACTIVE ]    = '1';
	$GLOBALS['flash_for'] = $reader;
	set_flash( $channel, array( 'status' => 'done' ) );
	$elsewhere = array();
	foreach ( array( array(), array( 'wpcpm_view' => 'students' ), array( 'wpcpm_view' => 'enroll' ), array( 'wpcpm_view' => 'reports' ), array( 'wpcpm_view' => 'institution' ), array( 'wpcpm_view' => 'agreement' ), array( 'wpcpm_view' => 'events' ) ) as $other ) {
		if ( $other !== $home ) {
			$drawn       = page_as( $reader, $other );
			$elsewhere[] = array( isset( $other['wpcpm_view'] ) ? $other['wpcpm_view'] : 'overview', has_flash( $channel ), substr_count( $drawn, 'data-channel="' . $channel . '"' ) );
			if ( array( 'wpcpm_view' => 'events' ) === $other ) {
				$events_drawn[] = false !== strpos( $drawn, 'data-stub="sevents"' );
			}
		}
	}
	ck( "channel $channel: the Overview and every other view leave it waiting, unprinted", array_values( array_filter( $elsewhere, static function ( $seen ) { return true !== $seen[1] || 0 !== $seen[2]; } ) ), array() );
	$drawn = page_as( $reader, $home );
	ck( "channel $channel: the view its way back opens prints it once, and takes it", array( substr_count( $drawn, 'data-channel="' . $channel . '"' ), has_flash( $channel ) ), array( 1, false ) );
	++$reader;
}
ck( 'the Events view each reader was drawn holds the events list, so it is the view and not the Overview', array_unique( $events_drawn ), array( true ) );
// An Administrator viewing the school, on their own channel, one of their own for each: the Overview and For
// Administrators (the view only they have) leave it waiting and unprinted, and the view the way back opens prints it.
$manager          = 60;
$admin_view_drawn = array();
foreach ( $channels as $row ) {
	list( $channel, $home ) = $row;
	$GLOBALS['users'][ $manager ] = new WP_User( $manager, 'Manager Example', 'manager' . $manager . '@example.test', array( 'administrator' ) );
	$GLOBALS['manage'][]          = $manager;
	$GLOBALS['flash_for']         = $manager;
	set_flash( $channel, array( 'status' => 'done' ) );
	$seen = array();
	foreach ( array( array(), array( 'wpcpm_view' => 'administrators' ) ) as $other ) {
		$drawn  = page_as( $manager, $other + array( 'wpcpm_institution_view' => $rec ) );
		$seen[] = array( has_flash( $channel ), substr_count( $drawn, 'data-channel="' . $channel . '"' ) );
		if ( array() !== $other ) {
			$admin_view_drawn[] = false !== strpos( $drawn, 'Only Administrators see this section.' );
		}
	}
	$drawn = page_as( $manager, $home + array( 'wpcpm_institution_view' => $rec ) );
	ck( "channel $channel, an Administrator viewing the school: the Overview and For Administrators leave it, its view prints it once", array( $seen, substr_count( $drawn, 'data-channel="' . $channel . '"' ), has_flash( $channel ) ), array( array( array( true, 0 ), array( true, 0 ) ), 1, false ) );
	$GLOBALS['manage'] = array_values( array_diff( $GLOBALS['manage'], array( $manager ) ) );
	++$manager;
}
ck( 'the For Administrators view each was drawn is that view, and not the Overview', array_unique( $admin_view_drawn ), array( true ) );
// An Administrator viewing the school, on their own channel: the same.
$GLOBALS['users'][45] = new WP_User( 45, 'Second Manager', 'manager2@example.test', array( 'administrator' ) );
$GLOBALS['manage'][]   = 45;
$GLOBALS['flash_for'] = 45;
set_flash( channel_of( 'class-wpcpm-institution-people.php' ), array( 'status' => 'added' ) );
$overview = page_as( 45, array( 'wpcpm_institution_view' => $rec ) );
ck( 'an Administrator\'s People message waits on the Overview of the school they view', array( has_flash( channel_of( 'class-wpcpm-institution-people.php' ) ), substr_count( $overview, 'data-channel=' ) ), array( true, 0 ) );
ck( 'and Your institution of that school prints it once', substr_count( page_as( 45, array( 'wpcpm_view' => 'institution', 'wpcpm_institution_view' => $rec ) ), 'data-channel="' . channel_of( 'class-wpcpm-institution-people.php' ) . '"' ), 1 );
$GLOBALS['manage']    = array_values( array_diff( $GLOBALS['manage'], array( 45 ) ) );
$GLOBALS['flash_for'] = 23;

// The counts are the Sponsor Dashboard's own words: `_n( '%s waits', '%s wait', $n )`, so one reads "1 waits" and two
// "2 wait". A school's representative is the one to act on a checked list; an agreement that waits for them locks
// their page, so the two together wait only for an Administrator (a signed copy to review, and the list).
ck( 'counts on the menu', menu_badges( page_as_with_waiting( $admin, $rec2, WPCPM_Institution_Agreement::SUMMARY_SUBMITTED ) ), array( 'Overview' => '2 wait', 'Enroll students' => '1 waits', 'Agreement' => '1 waits' ) );
ck( 'and a representative\'s, for the list', menu_badges( page_as_with_waiting( $rep, $rec ) ), array( 'Overview' => '1 waits', 'Enroll students' => '1 waits' ) );
ck( 'the agreement waiting for a locked school is counted on its menu', menu_badges( page_as_with_waiting( $locked, $rec2 ) ), array( 'Overview' => '1 waits', 'Agreement' => '1 waits' ) );
ck( 'with nothing waiting, no count', menu_badges( page_as( $rep, array() ) ), array() );
ck( 'Help', help_labels( page_as( $rep, array() ) ), array( 'Institution guide', '#wpcredits on Slack', 'Write to the program' ) );
ck( 'Help\'s addresses: the handbook\'s guide while the site has none, the channel, the assigned Administrator', array( menu_url( $html = page_as( $rep, array() ), 'Institution guide' ), menu_url( $html, '#wpcredits on Slack' ), menu_url( $html, 'Write to the program' ) ), array( 'https://make.wordpress.org/community/handbook/education/credits/institutions/', 'https://wordpress.slack.com/archives/C0959D2M3T8', 'mailto:ola@example.org' ) );
$html = page_as_with_update( $rep );
ck( 'Program updates while one exists', in_array( 'Program updates', menu_labels( $html ), true ), true );
ck( 'the newest update for institutions, after the views', array( $GLOBALS['updates_asked'], array_slice( menu_labels( $html ), -1 ), menu_url( $html, 'Program updates' ) ), array( array( 'institution' ), array( 'Program updates' ), 'https://example.test/an-update/' ) );
ck( 'and none while there is none', in_array( 'Program updates', menu_labels( page_as( $rep, array() ) ), true ), false );
$html = page_as( $admin, array( 'wpcpm_institution_view' => $rec ) );
ck( 'an Administrator\'s menu: every view but Events, and For Administrators', menu_labels( $html ), array( 'Overview', 'Students', 'Enroll students', 'Semester reports', 'Your institution', 'Agreement', 'For Administrators' ) );
ck( 'an Administrator\'s links keep the school', all_menu_urls_hold( $html, 'wpcpm_institution_view=' . $rec ), true );
ck( 'a member\'s links name none', any_menu_url_holds( page_as( $rep, array() ), 'wpcpm_institution_view' ), false );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Institution Dashboard, Administrator viewing a school: menu', menu_case( $html ) );
	wpcpm_layout_keep( 'Institution Dashboard, Administrator viewing a school: Overview', main_html( $html ) );
	wpcpm_layout_keep( 'Institution Dashboard, Administrator viewing a locked school: Agreement', main_html( page_as( $admin, array( 'wpcpm_institution_view' => $rec2, 'wpcpm_view' => 'agreement' ) ) ) );
}
$html = page_as_with_update( $admin, array( 'wpcpm_institution_view' => $rec ) );
ck( 'and its Program updates keeps the school too', menu_url( $html, 'Program updates' ), 'https://example.test/an-update/?wpcpm_institution_view=' . $rec );
// The site's own Institution guide draws in this page's frame, so it keeps the school; the handbook's is linked as it is.
ck( 'the handbook\'s guide is linked as it is, for an Administrator too', menu_url( $html, 'Institution guide' ), 'https://make.wordpress.org/community/handbook/education/credits/institutions/' );
$guide_post              = new WP_Post();
$guide_post->ID          = 9002;
$guide_post->post_name   = 'institution-guide';
$GLOBALS['posts'][9002]  = $guide_post;
ck( 'the site\'s own guide keeps the school for an Administrator, and a member\'s names none', array( menu_url( page_as( $admin, array( 'wpcpm_institution_view' => $rec ) ), 'Institution guide' ), menu_url( page_as( $rep, array() ), 'Institution guide' ) ), array( 'https://example.test/institution-guide/?wpcpm_institution_view=' . $rec, 'https://example.test/institution-guide/' ) );
unset( $GLOBALS['posts'][9002] );
ck( 'an empty view says so', strpos( page_as_no_cards( $rep, 'reports' ), 'Nothing to show here yet.' ) !== false, true );
ck( 'and a view whose cards print says nothing of the kind', strpos( page_as( $rep, array( 'wpcpm_view' => 'reports' ) ), 'Nothing to show here yet.' ), false );
ck( 'the Students item keeps the filters on a card', menu_url( page_as( $rep, array( 'wpcpm_view' => 'students', 'wpcpm_institution_student' => '12', 'wpcpm_roster_status' => 'waiting' ) ), 'Students' ), $page . '?wpcpm_view=students&wpcpm_roster_status=waiting' );
ck( 'and an Administrator\'s keeps the school with them', menu_url( page_as( $admin, array( 'wpcpm_institution_view' => $rec, 'wpcpm_institution_student' => '12', 'wpcpm_cohort' => '2026-H1' ) ), 'Students' ), $page . '?wpcpm_view=students&wpcpm_cohort=2026-H1&wpcpm_institution_view=' . $rec );
ck( 'with no student named, the Students item is the view\'s own address', menu_url( page_as( $rep, array( 'wpcpm_view' => 'students', 'wpcpm_roster_status' => 'waiting' ) ), 'Students' ), $page . '?wpcpm_view=students' );
// An Administrator's switcher is one small control at the end of the head's first row, on every view (Task 14).
$html = page_as( $admin, array( 'wpcpm_institution_view' => $rec, 'wpcpm_view' => 'students' ) );
ck( 'an Administrator\'s switcher is drawn once, in the view\'s head, before its title', array( substr_count( $html, 'id="wpcpm-institution-switcher"' ), strpos( $html, '<header class="wpcpm-head' ) < strpos( $html, 'id="wpcpm-institution-switcher"' ), strpos( $html, 'id="wpcpm-institution-switcher"' ) < strpos( $html, 'class="wpcpm-head__title"' ) ), array( 1, true, true ) );
echo "\n=== Choose an institution, and Viewing as institution keeps the view (Task 7) ===\n";

/** The head's sentence, as text. */
function lede( $html ) {
	return preg_match( '#<p class="wpcpm-head__lede">(.*?)</p>#s', (string) $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : '';
}
/** Whether every link of the views' group is the page's own, with no school named (and the group has links). */
function menu_urls_name_no_school( $html ) {
	global $page;
	$urls = array_column( menu_items( $html ), 'url' );
	return array() !== $urls && count( $urls ) === count( array_filter( $urls, function ( $u ) use ( $page ) { return 0 === strpos( $u, $page ) && false === strpos( $u, 'wpcpm_institution_view' ); } ) );
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
/** What the page wrote while it drew: options and user meta. */
function writes_while( callable $draw ) {
	$GLOBALS['calls'] = array();
	$umeta            = $GLOBALS['umeta'];
	$draw();
	$options = array_values( array_filter( $GLOBALS['calls'], function ( $c ) { return 'update_option' === $c[0]; } ) );
	return array( $options, $umeta === $GLOBALS['umeta'] );
}

$html = page_as( $admin, array() );
ck( 'no school chosen: the choice', h2s( $html ), array( 'Choose an institution' ) );
ck( 'its lede', lede( $html ), 'Pick the school whose page you want to see. Only Administrators see this.' );
ck( 'the first school is not drawn', $GLOBALS['card_calls'], array() );
ck( 'resolve_institution is not asked', $GLOBALS['resolve_calls'], 0 );
ck( 'no school\'s name is printed', array( strpos( main_html( $html ), '<h2 class="wpcpm-head__title">Exampleinstitute' ), strpos( $html, 'wpcpm-ui-figures' ), strpos( $html, 'Waiting for you' ) ), array( false, false, false ) );
ck( 'the menu, no counts', array( menu_labels( $html ), menu_badges( $html ) ), array( array( 'Overview', 'Students', 'Enroll students', 'Semester reports', 'Your institution', 'Agreement', 'For Administrators' ), array() ) );
ck( 'the Overview is the current item', current_item( $html ), 'Overview' );
ck( 'each lands on the choice', menu_urls_name_no_school( $html ), true );
ck( 'the views\' addresses', array_column( menu_items( $html ), 'url' ), array( $page, $page . '?wpcpm_view=students', $page . '?wpcpm_view=enroll', $page . '?wpcpm_view=reports', $page . '?wpcpm_view=institution', $page . '?wpcpm_view=agreement', $page . '?wpcpm_view=administrators' ) );
ck( 'no Write to the program, since no school is assigned to anybody here', help_labels( $html ), array( 'Institution guide', '#wpcredits on Slack' ) );
ck( 'the switcher is the main area\'s one field, after the head', array( substr_count( $html, 'id="wpcpm-institution-switcher"' ), strpos( $html, '</header>' ) < strpos( $html, 'class="wpcpm-ui-viewing"' ) ), array( 1, true ) );
ck( 'it posts the field the roster reads, nothing selected but the empty first choice', array( false !== strpos( $html, 'name="wpcpm_institution_view" id="wpcpm-institution-switcher"' ), substr_count( $html, 'selected=' ), false !== strpos( $html, '<option value="" data-wpcpm-blank selected=\'selected\'>Choose an institution</option>' ) ), array( true, 1, true ) );
ck( 'on the Overview it keeps no view', hidden_inputs( $html, 'wpcpm-institution-switcher' ), array() );
ck( 'the switcher keeps the view', hidden_inputs( page_as( $admin, array( 'wpcpm_view' => 'reports' ) ), 'wpcpm-institution-switcher' ), array( 'wpcpm_view' => 'reports' ) );
$html = page_as( $admin, array( 'wpcpm_view' => 'students' ) );
ck( 'every view asked is the choice, its item current', array( h2s( $html ), current_item( $html ), $GLOBALS['card_calls'], $GLOBALS['resolve_calls'] ), array( array( 'Choose an institution' ), 'Overview', array(), 0 ) );
ck( 'an old address is the choice too, its view kept', hidden_inputs( page_as( $admin, array( 'wpcpm_institution_student' => '12' ) ), 'wpcpm-institution-switcher' ), array( 'wpcpm_view' => 'students' ) );
ck( 'a view that is not one keeps nothing', hidden_inputs( page_as( $admin, array( 'wpcpm_view' => 'nonsense' ) ), 'wpcpm-institution-switcher' ), array() );
$html = page_as( $admin, array( 'wpcpm_institution_view' => 'recEXA00000000099' ) );
ck( 'a school that does not exist: the choice, not the first school', array( h2s( $html ), $GLOBALS['card_calls'], $GLOBALS['resolve_calls'] ), array( array( 'Choose an institution' ), array(), 0 ) );
ck( 'a value that is no record ID: the choice', h2s( page_as( $admin, array( 'wpcpm_institution_view' => '<b>x</b>' ) ) ), array( 'Choose an institution' ) );
$GLOBALS['update_option_calls'] = writes_while( function () use ( $admin ) { page_as( $admin, array( 'wpcpm_view' => 'students' ) ); } );
ck( 'nothing stored', $GLOBALS['update_option_calls'], array( array(), true ) );
ck( 'nor when a school is chosen', writes_while( function () use ( $admin, $rec ) { page_as( $admin, array( 'wpcpm_institution_view' => $rec ) ); } ), array( array(), true ) );
ck( 'a member is unchanged', h2s( page_as( $rep, array() ) )[0] !== 'Choose an institution', true );
ck( 'and asked their school as before', $GLOBALS['resolve_calls'], 1 );
foreach ( array( $tutor, $locked ) as $uid ) {
	ck( "nor does member $uid see the choice or the switcher", array( in_array( 'Choose an institution', h2s( page_as( $uid, array() ) ), true ), strpos( page_as( $uid, array() ), 'wpcpm-institution-switcher' ) ), array( false, false ) );
}
ck( 'a member naming a school still gets their own', array( h2s( page_as( $rep, array( 'wpcpm_institution_view' => $rec2 ) ) )[0], strpos( page_as( $rep, array( 'wpcpm_institution_view' => $rec2 ) ), 'wpcpm-institution-switcher' ) ), array( 'Exampleinstituteforappliedwebengineerings', false ) );
$html = page_as( $admin, array( 'wpcpm_institution_view' => $rec ) );
ck( 'an Administrator with a school', $GLOBALS['resolve_calls'] > 0 && 'Choose an institution' !== h2s( $html )[0], true );
ck( 'the school chosen is selected, and the Overview keeps no view', array( false !== strpos( $html, '<option value="' . $rec . '" selected=\'selected\'>' ), hidden_inputs( $html, 'wpcpm-institution-switcher' ) ), array( true, array() ) );
foreach ( array( 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators' ) as $v ) {
	ck( "a school chosen, on $v: the switcher keeps $v", hidden_inputs( page_as( $admin, array( 'wpcpm_institution_view' => $rec, 'wpcpm_view' => $v ) ), 'wpcpm-institution-switcher' ), array( 'wpcpm_view' => $v ) );
}
// An Administrator who also holds a school's stamp (attach() refuses it, but the meta could say so): their page, as before.
$GLOBALS['umeta'][ $admin ][ WPCPM_Institution_Members::META_RECORD_ID ] = $rec2;
$GLOBALS['umeta'][ $admin ][ WPCPM_Institution_Members::META_ACTIVE ]    = '1';
$html = page_as( $admin, array() );
ck( 'an Administrator with a stamp of their own is not asked to choose', array( in_array( 'Choose an institution', h2s( $html ), true ), $GLOBALS['resolve_calls'] > 0 ), array( false, true ) );
unset( $GLOBALS['umeta'][ $admin ] );
// One school is no choice (the switcher draws nothing for fewer than two): the page is as before, that school's.
$rows = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'];
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = array( $rec => $rows[ $rec ] );
$html = page_as( $admin, array() );
ck( 'with one school there is nothing to choose: its page, as before', array( h2s( $html )[0], $GLOBALS['resolve_calls'] ), array( 'Exampleinstituteforappliedwebengineerings', 1 ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $rows;
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	$html = page_as( $admin, array( 'wpcpm_view' => 'students' ) );
	wpcpm_layout_keep( 'Institution Dashboard, Administrator choosing an institution: menu', menu_case( $html ) );
	wpcpm_layout_keep( 'Institution Dashboard, Administrator choosing an institution', main_html( $html ) );
	wpcpm_layout_keep( 'Institution Dashboard, Administrator viewing a school: Students', main_html( page_as( $admin, array( 'wpcpm_institution_view' => $rec, 'wpcpm_view' => 'students' ) ) ) );
}

// Carried from Task 7's review: an Administrator who changes school on a view the new school does not have is drawn
// that school's Overview, and the Overview is the current item.
$GLOBALS['import_on'] = false;
$html = page_as( $admin, array( 'wpcpm_institution_view' => $rec, 'wpcpm_view' => 'enroll' ) );
ck( 'an Administrator kept on Enroll students at a school with enrollment off is drawn the Overview, its item current', array( h2s( $html )[0], current_item( $html ), in_array( 'Enroll students', menu_labels( $html ), true ), $GLOBALS['card_calls'] ), array( 'Exampleinstituteforappliedwebengineerings', 'Overview', false, array() ) );
$GLOBALS['import_on'] = true;

echo "\n=== The menu the guide and the updates are drawn with (Task 8) ===\n";

/** The frame `menu_args( $view )` opens, as one reader with one address's arguments: its menu, as the page's is read. */
function guide_menu_as( $uid, array $get = array(), $view = 'guide' ) {
	$GLOBALS['uid']           = (int) $uid;
	$GLOBALS['card_calls']    = array();
	$GLOBALS['resolve_calls'] = 0;
	$_GET                     = $get;
	WPCPM_Institutions_Dashboard::forget();
	ob_start();
	WPCPM_Dashboard_Frame::open( is_callable( array( 'WPCPM_Institutions_Dashboard', 'menu_args' ) ) ? WPCPM_Institutions_Dashboard::menu_args( $view ) : array() );
	WPCPM_Dashboard_Frame::close();
	return (string) ob_get_clean();
}
ck( 'the Institution Dashboard opens its frame for institutions, on the guide\'s menu and the updates\'',
	array( WPCPM_Institutions_Dashboard::menu_args( 'guide' )['audience'] ?? null, WPCPM_Institutions_Dashboard::menu_args( 'updates' )['audience'] ?? null ),
	array( 'institution', 'institution' ) );
/** A menu's items, the views' and Help's, all but which one is current. */
function menu_but_current( $html ) {
	$drop = function ( array $items ) { return array_map( function ( $i ) { unset( $i['current'] ); return $i; }, $items ); };
	return array( $drop( menu_items( $html ) ), $drop( menu_items( $html, 'help' ) ) );
}
/** The current item under Help. */
function current_help( $html ) {
	foreach ( menu_items( $html, 'help' ) as $item ) {
		if ( $item['current'] ) { return $item['label']; }
	}
	return '';
}
/** Draw while a checked list waits at a school, and, when given, its agreement waits in a state: the menu's counts. */
function while_waiting( $record, $state, callable $draw ) {
	$GLOBALS['active_batch'][ $record ] = 7;
	$GLOBALS['batches'][7]              = array( 'state' => WPCPM_Institution_Import::STATE_STAGED, 'institution' => $record, 'rows' => array() );
	$kept                               = get_option( 'wpcpm_agreement_' . $record );
	if ( '' !== $state ) {
		$GLOBALS['opts'][ 'wpcpm_agreement_' . $record ] = array( 'v' => 1, 'settled' => false, 'site_state' => $state, 'airtable_status' => '' );
	}
	$out = $draw();
	$GLOBALS['opts'][ 'wpcpm_agreement_' . $record ] = $kept;
	$GLOBALS['active_batch'] = array();
	$GLOBALS['batches']      = array();
	return $out;
}

// Ruling 2: the guide's menu is the dashboard's own for the same reader, counts and all.
list( $guide, $own ) = while_waiting( $rec, '', function () use ( $rep ) { return array( guide_menu_as( $rep ), page_as( $rep, array() ) ); } );
ck( 'a representative: the guide\'s menu is the dashboard\'s, all but which item is current', menu_but_current( $guide ), menu_but_current( $own ) );
ck( 'with the representative\'s own counts', menu_badges( $guide ), array( 'Overview' => '1 waits', 'Enroll students' => '1 waits' ) );
ck( 'the Institution guide is current, and no view', array( current_help( $guide ), current_item( $guide ) ), array( 'Institution guide', '' ) );
ck( 'the frame is the guide\'s page', substr_count( $guide, 'class="wpcpm-frame wpcpm-frame--institution-guide' ), 1 );
list( $guide, $own ) = while_waiting( $rec2, WPCPM_Institution_Agreement::SUMMARY_SUBMITTED, function () use ( $admin, $rec2 ) { return array( guide_menu_as( $admin, array( 'wpcpm_institution_view' => $rec2 ) ), page_as( $admin, array( 'wpcpm_institution_view' => $rec2 ) ) ); } );
ck( 'an Administrator naming a school: the guide\'s menu is the dashboard\'s', menu_but_current( $guide ), menu_but_current( $own ) );
ck( 'with the Administrator\'s counts, and every view keeping the school', array( menu_badges( $guide ), all_menu_urls_hold( $guide, 'wpcpm_institution_view=' . $rec2 ) ), array( array( 'Overview' => '2 wait', 'Enroll students' => '1 waits', 'Agreement' => '1 waits' ), true ) );
foreach ( array( 'tutor' => $tutor, 'locked representative' => $locked ) as $who => $uid ) {
	ck( "a $who: the guide's menu is the dashboard's", menu_but_current( guide_menu_as( $uid ) ), menu_but_current( page_as( $uid, array() ) ) );
}

// Ruling 1: a school or the choice, decided as the page decides it.
$guide = guide_menu_as( $admin );
ck( 'an Administrator who names no school: the choice\'s menu, as the page draws it', menu_but_current( $guide ), menu_but_current( page_as( $admin, array() ) ) );
ck( 'with no count, no school asked, and the guide current', array( menu_badges( $guide ), $GLOBALS['resolve_calls'], current_help( $guide ) ), array( array(), 0, 'Institution guide' ) );
ck( 'a member whose address names another school: their own school\'s menu', menu_but_current( guide_menu_as( $rep, array( 'wpcpm_institution_view' => $rec2 ) ) ), menu_but_current( page_as( $rep, array() ) ) );
ck( 'and none of its links names a school', any_menu_url_holds( guide_menu_as( $rep, array( 'wpcpm_institution_view' => $rec2 ) ), 'wpcpm_institution_view' ), false );
$GLOBALS['umeta'][ $admin ][ WPCPM_Institution_Members::META_RECORD_ID ] = $rec2;
$GLOBALS['umeta'][ $admin ][ WPCPM_Institution_Members::META_ACTIVE ]    = '1';
$guide = guide_menu_as( $admin );
ck( 'an Administrator with a stamp of their own: that school\'s menu, as the page draws it', array( menu_but_current( $guide ), all_menu_urls_hold( $guide, 'wpcpm_institution_view=' . $rec2 ) ), array( menu_but_current( page_as( $admin, array() ) ), true ) );
unset( $GLOBALS['umeta'][ $admin ] );
$rows = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'];
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = array( $rec => $rows[ $rec ] );
$guide = guide_menu_as( $admin );
ck( 'an Administrator with one school to choose from: the school the page draws', array( menu_but_current( $guide ), all_menu_urls_hold( $guide, 'wpcpm_institution_view=' . $rec ) ), array( menu_but_current( page_as( $admin, array() ) ), true ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $rows;

// The updates: their own page, and their item current while there is one.
$post            = new WP_Post();
$post->ID        = 9001;
$post->post_name = 'an-update';
$post->post_type = 'wpcpm_update';
$GLOBALS['posts'][9001] = $post;
$GLOBALS['updates']     = array( $post );
$guide = guide_menu_as( $rep, array(), 'updates' );
ck( 'the updates\' menu: their page, Program updates current, the guide not', array( substr_count( $guide, 'class="wpcpm-frame wpcpm-frame--institution-updates' ), current_item( $guide ), current_help( $guide ) ), array( 1, 'Program updates', '' ) );
ck( 'and the dashboard\'s menu otherwise', menu_but_current( $guide ), menu_but_current( page_as( $rep, array() ) ) );
$GLOBALS['updates'] = array();
unset( $GLOBALS['posts'][9001] );
ck( 'a view that is neither is the guide\'s', substr_count( guide_menu_as( $rep, array(), 'bogus' ), 'class="wpcpm-frame wpcpm-frame--institution-guide' ), 1 );

// Fix round 1: the tutor of a school whose agreement is not settled, whom the page tells there is nothing to show
// (pinned above: "a tutor of a school whose agreement is not settled is told there is nothing to show..."), is given
// the guide's menu of a reader with no school: no view, no count, no link to the school.
$GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] = array( 'v' => 1, 'settled' => false, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_NONE, 'airtable_status' => '' );
foreach ( array( 'guide', 'updates' ) as $v ) {
	$refused = guide_menu_as( 10, array(), $v );
	ck( "a tutor of a school not settled: the $v menu is a stranger's", menu_but_current( $refused ), menu_but_current( guide_menu_as( 5, array(), $v ) ) );
	ck( "with no view, and no link naming the school", array( menu_labels( $refused ), any_menu_url_holds( $refused, $krakow ) ), array( array(), false ) );
}
ck( 'and their page is still the notice a stranger is given', render_as( 10 ), render_as( 5 ) );

// Fix round 2: drawing the guide's menu only peeks at the events list's message, so the Events view drawn next prints it.
// A representative of their own, whose channel nothing has taken yet: the flash's memo lasts the suite's one request.
$GLOBALS['users'][24] = new WP_User( 24, 'Jordan Example', 'jordan@example.edu', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['umeta'][24][ WPCPM_Institution_Members::META_RECORD_ID ] = $rec;
$GLOBALS['umeta'][24][ WPCPM_Institution_Members::META_ACTIVE ]    = '1';
$GLOBALS['flash_for'] = 24;
set_flash( WPCPM_Sponsor_Event_List::FLASH, 'full' );
$guide = guide_menu_as( 24 );
ck( 'an events message pending: the guide\'s menu lists Events and leaves the message in place', array( in_array( 'Events', menu_labels( $guide ), true ), WPCPM_Flash::peek( WPCPM_Sponsor_Event_List::FLASH, 24 ), has_flash( WPCPM_Sponsor_Event_List::FLASH ) ), array( true, 'full', true ) );
ck( 'and the Events view drawn next prints it once', array( substr_count( page_as( 24, array( 'wpcpm_view' => 'events' ) ), '<p class="wpcpm-sevents__message">full</p>' ), has_flash( WPCPM_Sponsor_Event_List::FLASH ) ), array( 1, false ) );
$GLOBALS['flash_for'] = $rep;
unset( $GLOBALS['opts'][ 'wpcpm_agreement_' . $krakow ] );
ck( 'the guide\'s menu draws no card and stores nothing', writes_while( function () use ( $rep ) { guide_menu_as( $rep ); } ), array( array(), true ) );
ck( 'and calls no card', $GLOBALS['card_calls'], array() );

echo "\n=== The Overview: the school's head, its figures, what waits and what is new, and its contact (Task 6) ===\n";

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';

/** The head's title, as markup: the school's name through `WPCPM_UI::breakable()`. */
function h2s_html( $html ) {
	preg_match_all( '#<h2 class="wpcpm-head__title">(.*?)</h2>#s', (string) $html, $m );
	return $m[1];
}
/** The head's facts, each its own, as text. */
function facts( $html ) {
	if ( ! preg_match( '#<p class="wpcpm-ui-item__facts wpcpm-head__facts">(.*?)</p>#s', (string) $html, $line ) ) {
		return array();
	}
	preg_match_all( '#<span class="wpcpm-ui-item__fact">(.*?)</span>#s', $line[1], $m );
	return array_map( function ( $t ) { return html_entity_decode( strip_tags( $t ), ENT_QUOTES ); }, $m[1] );
}
/** The head's facts line, as markup. */
function facts_html( $html ) {
	return preg_match( '#<p class="wpcpm-ui-item__facts wpcpm-head__facts">.*?</p>#s', (string) $html, $m ) ? $m[0] : '';
}
/** The words of the head's buttons. */
function head_buttons( $html ) {
	if ( ! preg_match( '#<header class="wpcpm-head.*?</header>#s', (string) $html, $head ) || ! preg_match( '#<div class="wpcpm-head__actions">(.*?)</div>#s', $head[0], $acts ) ) {
		return array();
	}
	preg_match_all( '#<a class="wpcpm-btn[^"]*" href="[^"]*">(.*?)</a>#s', $acts[1], $m );
	return $m[1];
}
/** The head's buttons, as markup. */
function head_actions_html( $html ) {
	return preg_match( '#<div class="wpcpm-head__actions">.*?</div>#s', (string) $html, $m ) ? $m[0] : '';
}
function figure_labels( $html ) {
	preg_match_all( '#<p class="wpcpm-figure__label">(.*?)</p>#s', (string) $html, $m );
	return $m[1];
}
function figure_values( $html ) {
	preg_match_all( '#<p class="wpcpm-figure__value">(.*?)</p>#s', (string) $html, $m );
	return $m[1];
}
function figure_bases( $html ) {
	preg_match_all( '#<p class="wpcpm-figure__base">(.*?)</p>#s', (string) $html, $m );
	return $m[1];
}
/** The rows of what waits, each as markup. */
function waiting_rows( $html ) {
	return array_merge( group_rows( $html, 'waiting' ), group_rows( $html, 'news' ) );
}
/** The rows of one of the Overview's two groups, `waiting` (Waiting for you) or `news` (Good to know). */
function group_rows( $html, $group ) {
	if ( ! preg_match( '#<section class="wpcpm-institution__' . $group . '".*?</section>#s', (string) $html, $sec ) ) {
		return array();
	}
	preg_match_all( '#<li class="wpcpm-ui-list__row[^"]*">.*?</li>#s', $sec[0], $m );
	return $m[0];
}
/** Each group's heading and its rows' tags and titles, or its quiet line. */
function groups_of( $html ) {
	$out = array();
	foreach ( array( 'waiting', 'news' ) as $group ) {
		if ( ! preg_match( '#<section class="wpcpm-institution__' . $group . '".*?</section>#s', (string) $html, $sec ) ) {
			continue;
		}
		preg_match( '#<h2 class="wpcpm-ui-h"[^>]*>(.*?)</h2>#', $sec[0], $h );
		$rows = array_map( function ( $row ) {
			preg_match( '#<span class="wpcpm-status[^"]*">(.*?)</span>#s', $row, $tag );
			preg_match( '#<span class="wpcpm-ui-list__name">(.*?)</span>#s', $row, $name );
			return ( $tag[1] ?? '' ) . ': ' . html_entity_decode( $name[1] ?? '', ENT_QUOTES );
		}, group_rows( $html, $group ) );
		if ( preg_match( '#<p class="wpcpm-overview__quiet">(.*?)</p>#', $sec[0], $q ) ) {
			$rows[] = $q[1];
		}
		$out[ $h[1] ?? '' ] = $rows;
	}
	return $out;
}
/** One row's tag, title, why and its one action's words. */
function row_parts( $html, $i ) {
	$row = waiting_rows( $html )[ $i ] ?? '';
	preg_match( '#<span class="wpcpm-status[^"]*">(.*?)</span>#s', $row, $tag );
	preg_match( '#<span class="wpcpm-ui-list__name">(.*?)</span>#s', $row, $name );
	preg_match_all( '#<span class="wpcpm-ui-item__fact">(.*?)</span>#s', $row, $why );
	preg_match_all( '#<a class="wpcpm-btn wpcpm-btn--secondary" href="[^"]*">(.*?)</a>#s', $row, $acts );
	return array( $tag[1] ?? null, html_entity_decode( $name[1] ?? '', ENT_QUOTES ), html_entity_decode( implode( '|', $why[1] ), ENT_QUOTES ), implode( '|', $acts[1] ) );
}
function tag_classes( $html, $i ) {
	return preg_match( '#<span class="(wpcpm-status[^"]*)">#', waiting_rows( $html )[ $i ] ?? '', $m ) ? $m[1] : null;
}
function row_href( $html, $i ) {
	return preg_match( '#<a class="wpcpm-btn wpcpm-btn--secondary" href="([^"]*)">#', waiting_rows( $html )[ $i ] ?? '', $m ) ? $m[1] : null;
}
function row_titles( $html ) {
	return array_map( function ( $i ) use ( $html ) { return row_parts( $html, $i )[0] . ': ' . row_parts( $html, $i )[1]; }, array_keys( waiting_rows( $html ) ) );
}
/** The contact card's heading, name, address, booking link's words and sentence. */
function contact_parts( $html ) {
	if ( ! preg_match( '#<section class="wpcpm-institution__contact">(.*?)</section>#s', (string) $html, $sec ) ) {
		return array();
	}
	preg_match( '#<h2 class="wpcpm-ui-h">(.*?)</h2>#s', $sec[1], $h );
	preg_match( '#<p class="wpcpm-institution__contact-name">(.*?)</p>#s', $sec[1], $name );
	preg_match( '#<a href="(mailto:[^"]*)">#', $sec[1], $mail );
	preg_match( '#<a href="[^"]*" target="_blank" rel="noopener noreferrer">(.*?)</a>#', $sec[1], $book );
	preg_match( '#<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">(.*?)</p>#s', $sec[1], $note );
	return array( $h[1] ?? null, strip_tags( $name[1] ?? '' ), $mail[1] ?? null, $book[1] ?? null, $note[1] ?? null );
}

// The school of this section: the 40-character word in its name, its place, its website and its contact; assigned
// to an Administrator with a long address and a booking link.
$base_row = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $rec ];
/** The section's school, the given facts over its own. */
function school( array $over = array() ) {
	return array_merge( array( 'name' => 'Exampleinstituteforappliedwebengineerings of Example', 'city' => 'Exampleton', 'country_name' => 'Exampleland', 'website' => 'https://www.example.org/', 'contact_person' => 'Robin Example', 'stage' => 'Confirmed' ), $over );
}
$school = school();
$GLOBALS['users'][13] = new WP_User( 13, 'Alex Example', 'alex.example@example.org' );
$GLOBALS['manage'][]  = 13;
$GLOBALS['booking'][13] = 'https://booking.example.org/alex-example';
$GLOBALS['kept'][ $rec ]['assigned'] = 13;
$GLOBALS['transients'][ 'wpcpm_icon_' . md5( 'www.example.org' ) ] = 'https://www.example.org/icon.png';
$GLOBALS['opts']['date_format'] = 'F j, Y';
// The figures' cohort, its rows and its groups: 5 signed up, 1 graduated, 3 on the program now, 1 waiting.
$GLOBALS['opening_cohort'] = '2026-H1';
/**
 * Hold the school's roster at given counts in the opening cohort.
 *
 * @return void
 */
function hold_counts( $signed, $graduated, $current, $waiting ) {
	global $rec;
	$rows = array();
	for ( $i = 0; $i < $signed; $i++ ) {
		$rows[ 'recSTUDENT' . str_pad( (string) $i, 7, '0', STR_PAD_LEFT ) ] = array( 'status' => $i < $graduated ? 'Graduate' : 'In Sensei', 'start' => '2026-02-16' );
	}
	$GLOBALS['opts'][ 'wpcpm_roster_' . $rec ] = array( 'v' => 1, 'read' => 1792800000, 'rows' => $rows );
	$GLOBALS['groups'][ $rec ]['2026-H1']      = array( 'current' => array_fill( 0, $current, array() ), 'waiting' => array_fill( 0, $waiting, array() ), 'finished' => array(), 'not_started' => array() );
}
/**
 * The Overview as one reader, with the school's facts as given (an Administrator views the school).
 *
 * @return string The page.
 */
function overview_as( $uid, array $row = array() ) {
	global $rec, $base_row;
	$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $rec ] = array_merge( $base_row, $row );
	$html = render_as( $uid, user_can( $uid, WPCPM_Roles::CAP_MANAGE ) ? array( 'wpcpm_institution_view' => $rec ) : array() );
	$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $rec ] = $base_row;
	return $html;
}
function overview_with_counts( $signed, $graduated, $current, $waiting ) {
	global $rep, $school;
	hold_counts( $signed, $graduated, $current, $waiting );
	$html = overview_as( $rep, $school );
	hold_counts( 5, 1, 3, 1 );
	return $html;
}
/** The Overview with nothing waiting and nothing new. */
function overview_nothing( $uid ) {
	global $school;
	return overview_as( $uid, $school );
}
/**
 * The Overview while a checked list waits at the school and, for its people, two semester reports are newly approved;
 * with `$creating`, the list is being created (said "In progress").
 */
function overview_with_rows( $uid, $creating = false, $record = null, $list = true, $reports = true ) {
	global $rec, $school;
	$record = null === $record ? $rec : $record;
	if ( $list ) {
		$GLOBALS['active_batch'][ $record ] = 7;
		$GLOBALS['batches'][7]              = array( 'state' => $creating ? WPCPM_Institution_Import::STATE_CREATING : WPCPM_Institution_Import::STATE_STAGED, 'institution' => $record, 'rows' => array() );
	}
	$GLOBALS['reports'][ $record ] = $reports ? array(
		'2026-H1' => array( 'state' => 'approved', 'at' => time() - 3 * DAY_IN_SECONDS ),
		'2025-H2' => array( 'state' => 'approved', 'at' => time() - 9 * DAY_IN_SECONDS ),
	) : array();
	$html = $record === $rec ? overview_as( $uid, $school ) : render_as( $uid, array( 'wpcpm_institution_view' => $record ) );
	$GLOBALS['active_batch'] = array();
	$GLOBALS['batches']      = array();
	$GLOBALS['reports']      = array();
	return $html;
}
/** The Overview of a school nobody is assigned to. */
function overview_no_contact( $uid ) {
	global $rec, $school;
	$kept = $GLOBALS['kept'][ $rec ];
	$GLOBALS['kept'][ $rec ] = array();
	$html = overview_as( $uid, $school );
	$GLOBALS['kept'][ $rec ] = $kept;
	return $html;
}
hold_counts( 5, 1, 3, 1 );

$html = overview_as( $rep, $school );
// Task 22b: the head's title keeps `breakable()` on the harness's word: with plain words the head overflows at 569 and 358.
ck( 'the H2 is the school, breakable', h2s_html( $html ), array( WPCPM_UI::breakable( 'Exampleinstituteforappliedwebengineerings of Example' ) ) );
ck( 'the facts, each its own', facts( $html ), array( 'Exampleton, Exampleland', 'www.example.org', 'Contact: Robin Example' ) );
ck( 'the facts are an item\'s facts line in the head, each its own span', 0 === strpos( facts_html( $html ), '<p class="wpcpm-ui-item__facts wpcpm-head__facts"><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact">Exampleton, Exampleland</span><span class="wpcpm-ui-item__fact"><a ' ), true );
ck( 'the helper: the line item_header() prints, with the caller\'s class; nothing for no fact', array( WPCPM_UI::facts_line( array( 'A <b>', array( 'html' => '<a href="x">x</a>' ) ), 'my-line' ), WPCPM_UI::facts_line( array( 'A' ) ), WPCPM_UI::facts_line( array() ), WPCPM_UI::facts_line( array( '' ), 'my-line' ) ), array( '<p class="wpcpm-ui-item__facts my-line"><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact">A &lt;b&gt;</span><span class="wpcpm-ui-item__fact"><a href="x">x</a></span></span></p>', '<p class="wpcpm-ui-item__facts"><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact">A</span></span></p>', '', '' ) );
ck( 'and it is the facts line item_header() prints under a title', WPCPM_UI::item_header( array( 'name' => 'N', 'facts' => array( 'A', 'B' ) ) ), '<h4 class="wpcpm-ui-item__title">N</h4>' . WPCPM_UI::facts_line( array( 'A', 'B' ) ) );
ck( 'the website is a link to the address, printed without its scheme', false !== strpos( facts_html( $html ), '<a href="https://www.example.org/" rel="external noopener">www.example.org</a>' ), true );
ck( 'a member never sees the stage', strpos( $html, 'Stage:' ), false );
ck( 'an Administrator sees it last', array_slice( facts( overview_as( $admin, $school ) ), -1 ), array( 'Stage: Confirmed' ) );
ck( 'no city: no separator', facts( overview_as( $rep, school( array( 'city' => '', 'country_name' => '' ) ) ) )[0], 'www.example.org' );
ck( 'no facts at all: no facts line', facts_html( overview_as( $rep, school( array( 'city' => '', 'country_name' => '', 'website' => '', 'contact_person' => '' ) ) ) ), '' );
ck( 'the icon at 48px', (bool) preg_match( '/<img class="wpcpm-institution__icon" src="[^"]+" alt="" width="48" height="48"/', $html ), true );
// Review Focus 4.
ck( 'Enroll students in the head', head_buttons( $html ), array( 'Enroll students' ) );
ck( 'it opens the enrollment form, a secondary button', head_actions_html( $html ), '<div class="wpcpm-head__actions"><a class="wpcpm-btn wpcpm-btn--secondary" href="' . $page . '?wpcpm_view=enroll#wpcpm-import">Enroll students</a></div>' );
ck( 'and for an Administrator it keeps the school', head_actions_html( overview_as( $admin, $school ) ), '<div class="wpcpm-head__actions"><a class="wpcpm-btn wpcpm-btn--secondary" href="' . $page . '?wpcpm_view=enroll&wpcpm_institution_view=' . $rec . '#wpcpm-import">Enroll students</a></div>' );
ck( 'a tutor\'s head has none', head_buttons( overview_as( $tutor, $school ) ), array() );
ck( 'a locked head has none', head_buttons( overview_as( $locked, $school ) ), array() );
ck( 'figures', figure_labels( $html ), array( 'signed up', 'graduated', 'on the program now', 'waiting for a mentor' ) );
ck( 'their values', figure_values( $html ), array( '5', '1', '3', '1' ) );
ck( 'the base names the cohort', figure_bases( $html )[0], 'in January to June 2026' );
ck( 'the band is the Sponsor Dashboard\'s, with its own label', false !== strpos( $html, '<section class="wpcpm-figures wpcpm-figures--4" aria-label="Your students at a glance">' ), true );
ck( 'a zero is left out', figure_labels( overview_with_counts( 3, 0, 2, 1 ) ), array( 'signed up', 'on the program now', 'waiting for a mentor' ) );
ck( 'and the band counts its cells', false !== strpos( overview_with_counts( 3, 0, 2, 1 ), 'wpcpm-figures wpcpm-figures--3' ), true );
ck( 'all zero: no band', strpos( overview_with_counts( 0, 0, 0, 0 ), 'wpcpm-figures' ), false );
ck( 'and no read line without it', strpos( overview_with_counts( 0, 0, 0, 0 ), 'Read from the program records on' ), false );
ck( 'no figures for a locked school', strpos( overview_as( $locked, $school ), 'wpcpm-figures' ), false );
ck( 'a tutor reads them, as the Students view is theirs', figure_labels( overview_as( $tutor, $school ) ), array( 'signed up', 'graduated', 'on the program now', 'waiting for a mentor' ) );
ck( 'the read line once', substr_count( $html, 'Read from the program records on' ), 1 );
ck( 'under the band, as the page\'s own line', array( strpos( $html, '<p class="wpcpm-dashboard__updated">Read from the program records on' ) > strpos( $html, '<section class="wpcpm-figures' ), strpos( $html, 'wpcpm-dashboard__updated' ) < strpos( $html, 'Nothing waits for you.' ) ), array( true, true ) );
ck( 'the Overview still calls no card', $GLOBALS['card_calls'], array() );
ck( 'nothing waits', strpos( overview_nothing( $rep ), 'Nothing waits for you.' ) !== false, true );
ck( 'said in one quiet line, under the group\'s heading', false !== strpos( overview_nothing( $rep ), '<section class="wpcpm-institution__waiting" aria-labelledby="wpcpm-institution-overview-waiting"><h2 class="wpcpm-ui-h" id="wpcpm-institution-overview-waiting">Waiting for you</h2><p class="wpcpm-overview__quiet">Nothing waits for you.</p></section>' ), true );
ck( 'all empty: one group, the line, no second heading', array( groups_of( overview_nothing( $rep ) ), strpos( overview_nothing( $rep ), 'Good to know' ) ), array( array( 'Waiting for you' => array( 'Nothing waits for you.' ) ), false ) );
// Fix round 1: "Waiting for you" holds only what waits; what is said without waiting and the news are "Good to know".
ck( 'only waits: one group, its rows', groups_of( overview_with_rows( $rep, false, null, true, false ) ), array( 'Waiting for you' => array( 'Waits: A checked list of students waits to be created' ) ) );
ck( 'only news: nothing waits, and the news is good to know', groups_of( overview_with_rows( $rep, false, null, false, true ) ), array( 'Waiting for you' => array( 'Nothing waits for you.' ), 'Good to know' => array( 'New: Your semester report for January to June 2026 is ready', 'New: Your semester report for July to December 2025 is ready' ) ) );
ck( 'only a neutral row: nothing waits, and the list in progress is good to know', groups_of( overview_with_rows( $rep, true, null, true, false ) ), array( 'Waiting for you' => array( 'Nothing waits for you.' ), 'Good to know' => array( 'In progress: A list of students is being created' ) ) );
ck( 'the second group\'s markup: the same heading and rows, after the first', false !== strpos( overview_with_rows( $rep, false, null, false, true ), '</section><section class="wpcpm-institution__news" aria-labelledby="wpcpm-institution-overview-news"><h2 class="wpcpm-ui-h" id="wpcpm-institution-overview-news">Good to know</h2><ul class="wpcpm-ui-list"><li class="wpcpm-ui-list__row">' ), true );
$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec2 ] = array( 'v' => 1, 'settled' => false, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_NONE, 'airtable_status' => '' );
$with_school = overview_with_rows( $admin, false, $rec2, false, false );
ck( 'an Administrator\'s "With the school" row is good to know, and counted nowhere', array( groups_of( $with_school ), menu_badges( $with_school ) ), array( array( 'Waiting for you' => array( 'Nothing waits for you.' ), 'Good to know' => array( 'With the school: The agreement is not settled' ) ), array() ) );
delete_option( 'wpcpm_agreement_' . $rec2 );
$rows = overview_with_rows( $rep );
ck( 'a row: tag, title, why, one secondary', row_parts( $rows, 0 ), array( 'Waits', 'A checked list of students waits to be created', WPCPM_Institution_Import_Form::waiting_sentence( 7, $rec ), 'Open the list' ) );
ck( 'what waits is red', tag_classes( $rows, 0 ), 'wpcpm-status wpcpm-status--red' );
ck( 'a news row is neutral', tag_classes( $rows, 1 ), 'wpcpm-status' );
ck( 'the rows: what waits, then the news, newest first', row_titles( $rows ), array( 'Waits: A checked list of students waits to be created', 'New: Your semester report for January to June 2026 is ready', 'New: Your semester report for July to December 2025 is ready' ) );
ck( 'a news row opens its report', row_href( $rows, 1 ), $page . '?wpcpm_view=reports&wpcpm_report=2026-H1#wpcpm-report' );
ck( 'under the section\'s heading', false !== strpos( $rows, '<section class="wpcpm-institution__waiting" aria-labelledby="wpcpm-institution-overview-waiting"><h2 class="wpcpm-ui-h" id="wpcpm-institution-overview-waiting">Waiting for you</h2><ul class="wpcpm-ui-list">' ), true );
ck( 'with no quiet line beside it', strpos( $rows, 'Nothing waits for you.' ), false );
ck( 'the row opens its card', row_href( overview_with_rows( $admin ), 0 ), $page . '?wpcpm_view=enroll&wpcpm_institution_view=' . $rec . '#wpcpm-import' );
ck( 'a list being created is In progress, neutral', array( row_parts( overview_with_rows( $rep, true ), 0 )[0], tag_classes( overview_with_rows( $rep, true ), 0 ) ), array( 'In progress', 'wpcpm-status' ) );
// The controller's ruling 1: what waits first, then what is said without waiting, then the news, whatever order the
// rows came in. For an Administrator a school's unsettled agreement it has to act on is "With the school".
$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec2 ] = array( 'v' => 1, 'settled' => false, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_NONE, 'airtable_status' => '' );
$mixed = overview_with_rows( $admin, false, $rec2 );
ck( 'waits first, then neutral', row_titles( $mixed ), array( 'Waits: A checked list of students waits to be created', 'With the school: The agreement is not settled' ) );
ck( 'each in its group', groups_of( $mixed ), array( 'Waiting for you' => array( 'Waits: A checked list of students waits to be created' ), 'Good to know' => array( 'With the school: The agreement is not settled' ) ) );
ck( 'and the news after the neutral rows', groups_of( overview_with_rows( $rep, true ) )['Good to know'] ?? null, array( 'In progress: A list of students is being created', 'New: Your semester report for January to June 2026 is ready', 'New: Your semester report for July to December 2025 is ready' ) );
ck( 'and the count is what waits alone', menu_badges( $mixed ), array( 'Overview' => '1 waits', 'Enroll students' => '1 waits' ) );
delete_option( 'wpcpm_agreement_' . $rec2 );
ck( 'the contact card', contact_parts( $html ), array( 'Your contact at the program', 'Alex Example', 'mailto:alex.example@example.org', 'Book a call', 'They look after your institution. Write to them about anything the guide does not answer.' ) );
ck( 'its address and its booking link, as before', false !== strpos( $html, '<p class="wpcpm-institution__contact-links"><a href="mailto:alex.example@example.org">alex.example@example.org</a> &middot; <a href="https://booking.example.org/alex-example" target="_blank" rel="noopener noreferrer">Book a call</a></p>' ), true );
ck( 'the contact after what waits', strpos( $html, 'wpcpm-institution__contact' ) > strpos( $html, 'Nothing waits for you.' ), true );
ck( 'no contact: no card', strpos( overview_no_contact( $rep ), 'Your contact at the program' ), false );
ck( 'no Resources block', strpos( $html, 'wpcpm-resources' ), false );
ck( 'the Overview\'s H2s: the school, what waits, then the contact', h2s( $html ), array( 'Exampleinstituteforappliedwebengineerings of Example', 'Waiting for you', 'Your contact at the program' ) );
ck( 'a locked school: the head, what waits, the contact, no figures', array( h2s( overview_as( $locked, $school ) ), strpos( overview_as( $locked, $school ), 'wpcpm-figures' ) ), array( array( 'Exampleinstituteforappliedwebengineerings Second Campus', 'Waiting for you', 'Your contact at the program' ), false ) );
ck( 'a member\'s Overview holds none of the base\'s words', internal_words( $html ), array() );
ck( 'nor its strings an em or en dash', array( false !== strpos( $html, "\u{2014}" ), false !== strpos( $html, "\u{2013}" ) ), array( false, false ) );

if ( function_exists( 'wpcpm_layout_keep' ) ) {
	$long = school( array( 'website' => 'https://averyveryveryverylongsubdomainnameofthe.example.org/a/long/path/to/the/school', 'contact_person' => 'Averyveryveryverylongcontactpersonname1234 Example' ) );
	$GLOBALS['users'][13]->user_email = 'averyveryveryverylongmailboxname1234@example.org';
	wpcpm_layout_keep( 'Institution Dashboard Overview, representative: list waiting, two news rows, long name, website, contact', main_html( overview_with_rows( $rep ) ) );
	$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $rec ] = array_merge( $base_row, $long );
	$GLOBALS['active_batch'][ $rec ] = 7;
	$GLOBALS['batches'][7]           = array( 'state' => WPCPM_Institution_Import::STATE_CREATING, 'institution' => $rec, 'rows' => array() );
	$GLOBALS['reports'][ $rec ]      = array( '2026-H1' => array( 'state' => 'approved', 'at' => time() - DAY_IN_SECONDS ) );
	wpcpm_layout_keep( 'Institution Dashboard Overview, representative: list in progress, news, long website and contact', main_html( render_as( $rep, array() ) ) );
	$GLOBALS['active_batch'] = array();
	$GLOBALS['batches']      = array();
	$GLOBALS['reports']      = array();
	wpcpm_layout_keep( 'Institution Dashboard Overview, Administrator viewing a school: stage, long website and contact', main_html( render_as( $admin, array( 'wpcpm_institution_view' => $rec ) ) ) );
	$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'][ $rec ] = $base_row;
	$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec2 ] = array( 'v' => 1, 'settled' => false, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_NONE, 'airtable_status' => '' );
	wpcpm_layout_keep( 'Institution Dashboard Overview, Administrator viewing a locked school: waits then neutral', main_html( overview_with_rows( $admin, false, $rec2 ) ) );
	delete_option( 'wpcpm_agreement_' . $rec2 );
	wpcpm_layout_keep( 'Institution Dashboard Overview, representative: nothing waits, two news rows good to know', main_html( overview_with_rows( $rep, false, null, false, true ) ) );
	wpcpm_layout_keep( 'Institution Dashboard Overview, locked representative', main_html( overview_as( $locked, $school ) ) );
	hold_counts( 0, 0, 0, 0 );
	wpcpm_layout_keep( 'Institution Dashboard Overview, representative: no figures, no rows, no contact', main_html( overview_no_contact( $rep ) ) );
	hold_counts( 5, 1, 3, 1 );
	$GLOBALS['users'][13]->user_email = 'alex.example@example.org';
	// The head beside the school's site icon and without one, under a name short enough for one line (1.123.97): the
	// layout suite draws the first twice, with the icon's address answered and with it refused (the icon then takes
	// itself off the page), and the second as it is (bin/test-ui-layout.php, "the head beside a school's site icon").
	// The second is the same school once its site has answered with no icon, cached as '' (`site_icon()`): the two
	// pages differ in the icon and the head's class, and in nothing else.
	$icon_key = 'wpcpm_icon_' . md5( 'www.example.org' );
	$icon_was = $GLOBALS['transients'][ $icon_key ];
	wpcpm_layout_keep( 'Institution Dashboard Overview, representative: short name, site icon', main_html( overview_as( $rep, school( array( 'name' => 'Example College' ) ) ) ) );
	$GLOBALS['transients'][ $icon_key ] = '';
	wpcpm_layout_keep( 'Institution Dashboard Overview, representative: short name, no site icon', main_html( overview_as( $rep, school( array( 'name' => 'Example College' ) ) ) ) );
	$GLOBALS['transients'][ $icon_key ] = $icon_was;
}

echo "\n=== 1.123.98: the Overview tells a school more ===\n";

/**
 * Hold the school's students for the Overview's new rows and block: the opening cohort's six, of whom one waits to
 * graduate, two have not signed in, one placement ended and is still open, two finish within 30 days, three teams, two
 * mentors and hours on record for three. Every one of them is in the roster's groups of every cohort ('').
 *
 * @param bool $teams Whether the semester report's count finds teams.
 */
function hold_more( $teams = true ) {
	global $rec;
	$day  = static function ( $n ) { return gmdate( 'Y-m-d', time() + $n * DAY_IN_SECONDS ); };
	$base = array( 'start' => '2026-02-16', 'has_mentor' => true, 'reports' => array(), 'institution' => $rec, 'team' => '', 'hours' => '', 'mentor_name' => '' );
	$rows = array(
		'recSTU00000000101' => array_merge( $base, array( 'name' => 'Ada Example', 'status' => 'Developer Track', 'end' => $day( 10 ), 'user_id' => 301, 'mentor_name' => 'Mia Example', 'hours' => '12' ) ),
		'recSTU00000000102' => array_merge( $base, array( 'name' => 'Bo Example', 'status' => 'Developer Track', 'end' => $day( -2 ), 'user_id' => 302, 'mentor_name' => 'Mia Example', 'hours' => '40.5' ) ),
		'recSTU00000000103' => array_merge( $base, array( 'name' => 'Cy Example', 'status' => 'In Sensei', 'end' => $day( 90 ), 'user_id' => 303, 'has_mentor' => false, 'hours' => '0' ) ),
		'recSTU00000000104' => array_merge( $base, array( 'name' => 'Di Example', 'status' => 'Pending graduation', 'end' => $day( -30 ), 'user_id' => 304, 'mentor_name' => 'Noor Example', 'hours' => '150' ) ),
		'recSTU00000000105' => array_merge( $base, array( 'name' => 'Ed Example', 'status' => 'Designer Track', 'end' => $day( 0 ), 'user_id' => 0, 'mentor_name' => 'Noor Example' ) ),
		'recSTU00000000106' => array_merge( $base, array( 'name' => 'Fi Example', 'status' => 'Graduate', 'end' => $day( -60 ), 'user_id' => 306 ) ),
	);
	$GLOBALS['opts'][ 'wpcpm_roster_' . $rec ] = array( 'v' => 1, 'read' => 1792800000, 'rows' => $rows );
	$active = array_intersect_key( $rows, array_flip( array( 'recSTU00000000101', 'recSTU00000000102', 'recSTU00000000104', 'recSTU00000000105' ) ) );
	$GLOBALS['groups'][ $rec ]['']        = array( 'current' => $active, 'waiting' => array( 'recSTU00000000103' => $rows['recSTU00000000103'] ), 'finished' => array( 'recSTU00000000106' => $rows['recSTU00000000106'] ), 'not_started' => array() );
	$GLOBALS['groups'][ $rec ]['2026-H1'] = $GLOBALS['groups'][ $rec ][''];
	// Fix round 1, B2: the site records sign-ins since 40 days ago, and every student's account was made after that.
	$GLOBALS['opts']['wpcpm_sign_ins_since'] = time() - 40 * DAY_IN_SECONDS;
	foreach ( array( 301 => 'Ada Example', 302 => 'Bo Example', 303 => 'Cy Example', 304 => 'Di Example', 306 => 'Fi Example' ) as $more_id => $more_name ) {
		$GLOBALS['users'][ $more_id ]                  = new WP_User( $more_id, $more_name, strtolower( str_replace( ' ', '.', $more_name ) ) . '@example.org', array( 'wpcpm_student' ) );
		$GLOBALS['users'][ $more_id ]->user_registered = gmdate( 'Y-m-d H:i:s', time() - 39 * DAY_IN_SECONDS );
	}
	$GLOBALS['umeta'][302]['wpcpm_last_sign_in'] = time() - 3 * DAY_IN_SECONDS;
	$GLOBALS['umeta'][304]['wpcpm_last_sign_in'] = time() - 3 * DAY_IN_SECONDS;
	$GLOBALS['umeta'][304]['wpcpm_student_pending_since'] = array( 'at' => 1792800000, 'before' => false );
	$GLOBALS['team_counts'] = $teams ? array( array( 'team' => 'Polyglots', 'count' => 3 ), array( 'team' => 'Core', 'count' => 2 ), array( 'team' => 'Training', 'count' => 1 ) ) : array();
}
/** The school's students as they were before this scene. */
function drop_more() {
	global $rec;
	unset( $GLOBALS['groups'][ $rec ][''], $GLOBALS['umeta'][302], $GLOBALS['umeta'][304], $GLOBALS['users'][301], $GLOBALS['users'][302], $GLOBALS['users'][303], $GLOBALS['users'][304], $GLOBALS['users'][306], $GLOBALS['opts']['wpcpm_sign_ins_since'] );
	$GLOBALS['team_counts'] = array();
	hold_counts( 5, 1, 3, 1 );
}
/** The block What your students work on, or ''. */
function work_html( $html ) {
	return preg_match( '#<section class="wpcpm-institution__work".*?</section>#s', (string) $html, $m ) ? $m[0] : '';
}
hold_more();
$more_rep = overview_as( $rep, $school );
// Fix round 1, B3: the figures are disjoint: the student waiting to graduate is counted there alone, beside the graduates.
ck( 'A1: the figures gain waiting to graduate, beside graduated, and add up to no more than signed up', array( figure_labels( $more_rep ), figure_values( $more_rep ) ), array( array( 'signed up', 'graduated', 'waiting to graduate', 'on the program now', 'waiting for a mentor' ), array( '6', '1', '1', '3', '1' ) ) );
// Fix round 1, B1: the four are facts of the school under Good to know, with no tag, counted nowhere.
ck( 'A2 to A5: nothing waits; the four facts with no tag under Good to know, in the order a placement runs', groups_of( $more_rep ), array(
	'Waiting for you' => array( 'Nothing waits for you.' ),
	'Good to know'    => array( ': 2 students have not signed in yet', ': 2 students finish within 30 days', ': 1 placement ended and is still open', ': 1 student is waiting to graduate' ),
) );
ck( 'each opens its narrowed list on the Students view', array_map( static function ( $row ) { preg_match( '#<a class="wpcpm-btn wpcpm-btn--secondary" href="([^"]+)">See them</a>#', $row, $m ); return html_entity_decode( $m[1] ?? '' ); }, array_merge( group_rows( $more_rep, 'waiting' ), group_rows( $more_rep, 'news' ) ) ), array(
	$page . '?wpcpm_view=students&wpcpm_only=no-sign-in#wpcpm-roster',
	$page . '?wpcpm_view=students&wpcpm_only=finishing#wpcpm-roster',
	$page . '?wpcpm_view=students&wpcpm_only=ended-open#wpcpm-roster',
	$page . '?wpcpm_view=students&wpcpm_only=pending-graduation#wpcpm-roster',
) );
ck( 'the wait to graduate of one student says when it began, in the site\'s date format', false !== strpos( $more_rep, 'The program is checking their work. The wait began on ' . gmdate( 'F j, Y', 1792800000 ) . '.' ), true );
ck( 'A6 and A7: What your students work on, under Good to know: the teams most first in the key and value table, then one muted line', array(
	strpos( $more_rep, 'wpcpm-institution__work' ) > strpos( $more_rep, 'wpcpm-institution__news' ) && strpos( $more_rep, 'wpcpm-institution__work' ) < strpos( $more_rep, 'wpcpm-institution__contact' ),
	work_html( $more_rep ),
), array( true, '<section class="wpcpm-institution__work" aria-labelledby="wpcpm-institution-overview-work"><h2 class="wpcpm-ui-h" id="wpcpm-institution-overview-work">What your students work on</h2>'
	. '<table class="wpcpm-ui-table wpcpm-ui-table--keyvalue" role="table" aria-labelledby="wpcpm-institution-overview-work"><tbody role="rowgroup">'
	. '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text">Polyglots</th><td class="wpcpm-ui-cell--text" role="cell">3 students</td></tr>'
	. '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text">Core</th><td class="wpcpm-ui-cell--text" role="cell">2 students</td></tr>'
	. '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text">Training</th><td class="wpcpm-ui-cell--text" role="cell">1 student</td></tr>'
	. '</tbody></table><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">2 mentors work with your students. Hours are on record for 3 of 6 students.</p></section>' ) );
hold_more( false );
ck( 'with no team, the line alone under the heading', work_html( overview_as( $rep, $school ) ), '<section class="wpcpm-institution__work" aria-labelledby="wpcpm-institution-overview-work"><h2 class="wpcpm-ui-h" id="wpcpm-institution-overview-work">What your students work on</h2><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">2 mentors work with your students. Hours are on record for 3 of 6 students.</p></section>' );
hold_more();
$more_tutor = overview_as( $tutor, $school );
ck( 'a tutor: the two facts for every reader of the roster and the block, neither fact a tutor cannot act on', array( groups_of( $more_tutor )['Waiting for you'] ?? null, groups_of( $more_tutor )['Good to know'] ?? null, '' !== work_html( $more_tutor ) ), array( array( 'Nothing waits for you.' ), array( ': 2 students finish within 30 days', ': 1 student is waiting to graduate' ), true ) );
$more_admin = overview_as( $admin, $school );
ck( 'an Administrator looking at the school: the four facts as the school has them, no tag, then the block', array( groups_of( $more_admin ), '' !== work_html( $more_admin ) ), array( array(
	'Waiting for you' => array( 'Nothing waits for you.' ),
	'Good to know'    => array( ': 2 students have not signed in yet', ': 2 students finish within 30 days', ': 1 placement ended and is still open', ': 1 student is waiting to graduate' ),
), true ) );
$more_locked = overview_as( $locked, $school );
ck( 'a school with no settled agreement: none of it', array( strpos( $more_locked, 'signed in yet' ), strpos( $more_locked, 'waiting to graduate' ), work_html( $more_locked ) ), array( false, false, '' ) );
$more_badge = static function ( $html ) { return preg_match( '#<span class="wpcpm-frame__label">Students<span class="screen-reader-text">, ([^<]+)</span>#', (string) $html, $m ) ? $m[1] : ''; };
ck( 'the menu counts none of them beside Students, for any reader', array( $more_badge( $more_rep ), $more_badge( $more_tutor ), $more_badge( $more_admin ) ), array( '', '', '' ) );
// Fix round 1, B3: the figures never add up to more than signed up, on every Overview this suite draws.
$more_sums = array();
foreach ( array( 'held' => $more_rep, 'tutor' => $more_tutor, 'admin' => $more_admin, 'five' => overview_with_counts( 5, 1, 3, 1 ), 'two' => overview_with_counts( 3, 0, 2, 1 ) ) as $more_name => $more_html ) {
	$more_v = array_map( 'intval', figure_values( $more_html ) );
	$more_sums[ $more_name ] = array() === $more_v || array_sum( array_slice( $more_v, 1 ) ) <= $more_v[0];
}
ck( 'B3: graduated, waiting to graduate, on the program now and waiting for a mentor never add up to more than signed up', array_values( array_unique( $more_sums ) ), array( true ) );

// Fix round 4 (read off the pictures): the block's table stands on the page with no card around it, and its team names
// stood 12px in from the heading above them and the line under them. The page's sheet sets the table's outer cells at
// the table's edges, as the parts set a card's table.
$more_inst_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/institution.css' );
ck(
	'What your students work on: the table\'s outer cells have no padding at its edges, so the team names line up with the heading',
	array(
		preg_match( '/\.wpcpm-frame \.wpcpm-institution__work \.wpcpm-ui-table th:first-child,\s*\.wpcpm-frame \.wpcpm-institution__work \.wpcpm-ui-table td:first-child\s*\{\s*padding-inline-start:\s*0;/', $more_inst_css ),
		preg_match( '/\.wpcpm-frame \.wpcpm-institution__work \.wpcpm-ui-table th:last-child,\s*\.wpcpm-frame \.wpcpm-institution__work \.wpcpm-ui-table td:last-child\s*\{\s*padding-inline-end:\s*0;/', $more_inst_css ),
		false !== strpos( work_html( $more_rep ), '<table class="wpcpm-ui-table wpcpm-ui-table--keyvalue"' ),
	),
	array( 1, 1, true )
);

// Fix round 2 (found in the pictures): with five figures the band kept its four columns, the fifth figure wrapped under
// the first and the band's line color filled the three empty places beside it. The band counts its cells in its class,
// and the frame's sheet gives five a row of their own, stacked in the narrow form as the other counts are.
$more_frame_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/dashboard-frame.css' );
ck(
	'five figures: the band says five, the frame\'s sheet draws five in one row, and one under another in the narrow form',
	array(
		false !== strpos( $more_rep, '<section class="wpcpm-figures wpcpm-figures--5" aria-label="Your students at a glance">' ),
		preg_match( '/\n\.wpcpm-figures--5\s*\{\s*grid-template-columns:\s*repeat\(\s*5,\s*minmax\(\s*0,\s*1fr\s*\)\s*\);\s*\}/', $more_frame_css ),
		preg_match( '/@container wpcpm-main \( max-width: 760px \) \{.*?\.wpcpm-figures--3,\s*\.wpcpm-figures--5\s*\{\s*grid-template-columns:\s*minmax\(\s*0,\s*1fr\s*\);/s', $more_frame_css ),
	),
	array( true, 1, 1 )
);

// No remote read because of this release: on the Overview and on every view, with the new rows held, nothing is fetched
// (the icon is the transient's), and the Airtable, Report Card and feedback classes are not loaded in this suite at all,
// so a read of one would stop it.
$GLOBALS['fetched'] = array();
foreach ( array( array( $rep, array() ), array( $tutor, array() ), array( $admin, array( 'wpcpm_institution_view' => $rec ) ), array( $rep, array( 'wpcpm_view' => 'students' ) ), array( $rep, array( 'wpcpm_view' => 'institution' ) ), array( $rep, array( 'wpcpm_view' => 'reports' ) ) ) as $more_scene ) {
	render_as( $more_scene[0], $more_scene[1] );
}
ck( 'no remote read on the Overview or any view, with every new row held', array( $GLOBALS['fetched'], class_exists( 'WPCPM_Airtable', false ), class_exists( 'WPCPM_Student_Report_Form', false ), class_exists( 'WPCPM_Feedback', false ) ), array( array(), false, false, false ) );

if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Institution Dashboard Overview, 1.123.98: every new row and the block, a representative', main_html( $more_rep ) );
	wpcpm_layout_keep( 'Institution Dashboard Overview, 1.123.98: the facts and the block, an Administrator viewing a school', main_html( $more_admin ) );
	hold_more( false );
	wpcpm_layout_keep( 'Institution Dashboard Overview, 1.123.98: the block with its line alone, a tutor', main_html( overview_as( $tutor, $school ) ) );
}
drop_more();

echo "\n=== An old link that names only an anchor: the Overview's map (Task 10) ===\n";

/**
 * The maps a page prints: the text of each `script.wpcpm-anchor-views`, decoded.
 *
 * @param string $html A page.
 * @return array[]
 */
function anchor_maps( $html ) {
	preg_match_all( '#<script type="application/json" class="wpcpm-anchor-views">(.*?)</script>#s', (string) $html, $m );
	return array_map( function ( $t ) { return json_decode( $t, true ); }, $m[1] );
}

$maps = anchor_maps( page_as( $rep, array() ) );
ck( 'a representative\'s Overview prints one map, in the main area', array( count( $maps ), count( anchor_maps( main_html( page_as( $rep, array() ) ) ) ) ), array( 1, 1 ) );
$map = isset( $maps[0] ) && is_array( $maps[0] ) ? $maps[0] : array();
ck( 'it sends each old anchor to its view', array_intersect_key( $map, array_flip( array( 'wpcpm-import', 'wpcpm-report', 'wpcpm-details', 'wpcpm-people', 'wpcpm-tutors', 'wpcpm-student-form', 'wpcpm-agreement', 'wpcpm-sevents' ) ) ), array(
	'wpcpm-student-form' => $page . '?wpcpm_view=students',
	'wpcpm-import'       => $page . '?wpcpm_view=enroll',
	'wpcpm-report'       => $page . '?wpcpm_view=reports',
	'wpcpm-details'      => $page . '?wpcpm_view=institution',
	'wpcpm-people'       => $page . '?wpcpm_view=institution',
	'wpcpm-tutors'       => $page . '?wpcpm_view=institution',
	'wpcpm-agreement'    => $page . '?wpcpm_view=agreement',
	'wpcpm-sevents'      => $page . '?wpcpm_view=events',
) );
ck( 'and holds no For Administrators card and no school', array( isset( $map['wpcpm-for-administrators'] ), false !== strpos( implode( ' ', $map ), 'wpcpm_institution_view' ) ), array( false, false ) );
$tmap = anchor_maps( page_as( $tutor, array() ) );
ck( 'a tutor\'s holds no Enroll students and no Events', array( count( $tmap ), isset( $tmap[0]['wpcpm-import'] ), isset( $tmap[0]['wpcpm-sevents'] ), isset( $tmap[0]['wpcpm-report'] ) ), array( 1, false, false, true ) );
ck( 'a locked school\'s representative\'s holds the Agreement alone', anchor_maps( page_as( $locked, array() ) ), array( array( 'wpcpm-agreement' => $page . '?wpcpm_view=agreement' ) ) );
$amap = anchor_maps( page_as( $admin, array( 'wpcpm_institution_view' => $rec ) ) );
$aone = isset( $amap[0] ) && is_array( $amap[0] ) ? $amap[0] : array();
ck( 'an Administrator\'s addresses hold the school, every one, the For Administrators cards among them', array( count( $amap ), count( $aone ) > 0 && count( array_filter( $aone, function ( $u ) use ( $rec ) { return false !== strpos( $u, 'wpcpm_institution_view=' . $rec ); } ) ) === count( $aone ), $aone['wpcpm-for-administrators'] ?? '' ), array( 1, true, $page . '?wpcpm_view=administrators&wpcpm_institution_view=' . $rec ) );
ck( 'and nothing of the other school', false !== strpos( implode( ' ', $aone ), $rec2 ), false );
$others = array();
foreach ( array( 'students', 'enroll', 'reports', 'institution', 'agreement', 'events' ) as $view ) {
	$others[ $view ] = count( anchor_maps( page_as( $rep, array( 'wpcpm_view' => $view ) ) ) );
}
$others['administrators'] = count( anchor_maps( page_as( $admin, array( 'wpcpm_view' => 'administrators', 'wpcpm_institution_view' => $rec ) ) ) );
$others['old address']    = count( anchor_maps( page_as( $rep, array( 'wpcpm_batch' => '7' ) ) ) );
ck( 'no other view prints it, an old address that infers its view included', $others, array( 'students' => 0, 'enroll' => 0, 'reports' => 0, 'institution' => 0, 'agreement' => 0, 'events' => 0, 'administrators' => 0, 'old address' => 0 ) );
ck( 'and Choose an institution prints none', array( h2s( page_as( $admin, array() ) ), count( anchor_maps( page_as( $admin, array() ) ) ) ), array( array( 'Choose an institution' ), 0 ) );
ck( 'and the tag is data: nothing but the map\'s text between its tags, holding no <, > or &', 1 === preg_match( '#<script type="application/json" class="wpcpm-anchor-views">([^<>&]*)</script>#', page_as( $admin, array( 'wpcpm_institution_view' => $rec ) ) ), true );

echo "\n=== Whole views, every reader (Task 23) ===\n";

// Every view each reader has, drawn through the page's real render() in the frame the theme leaves to it (so the
// page's one H1 is the head's eyebrow): exactly one H1; the headings in order, never a level skipped; no id printed
// twice; the view's lede said once; and nothing under the head repeating the view's title as a heading. The cards are
// this suite's stand-ins (each card's own suite draws the real one), so what is held here is the page around them.
$GLOBALS['theme_support']['wpcpm-dashboard-frame'] = array( array( 'institution' ) );
/** Every view a page's menu offers, as the GET arguments that open it, the Overview first. */
function t23_views_of( $html, array $base ) {
	$out = array();
	foreach ( menu_items( $html ) as $item ) {
		$query = (string) parse_url( html_entity_decode( $item['url'], ENT_QUOTES ), PHP_URL_QUERY );
		parse_str( $query, $get );
		$out[ $item['label'] ] = $get + $base;
	}
	return $out;
}
/** What is wrong with the page around the cards: the shared faults of its main area (`bin/stubs/view-faults.php`), and the H1 counted over the whole page. */
function t23_view_faults( $html ) {
	$faults = wpcpm_view_faults( main_html( $html ) );
	if ( 1 !== substr_count( $html, '<h1' ) && ! in_array( substr_count( main_html( $html ), '<h1' ) . ' h1', $faults, true ) ) {
		$faults[] = substr_count( $html, '<h1' ) . ' h1 on the page';
	}
	return $faults;
}
$t23_readers = array(
	'a representative'                                     => array( $rep, array() ),
	'a tutor'                                              => array( $tutor, array() ),
	'a locked school\'s representative, no agreement'      => array( $locked, array() ),
	'an Administrator viewing a school'                    => array( $admin, array( 'wpcpm_institution_view' => $rec ) ),
	'an Administrator with no school'                      => array( $admin, array() ),
);
$t23_faults = array();
$t23_drawn  = 0;
foreach ( $t23_readers as $t23_reader => $t23_who ) {
	foreach ( t23_views_of( page_as( $t23_who[0], $t23_who[1] ), $t23_who[1] ) as $t23_view => $t23_get ) {
		$t23_page = page_as( $t23_who[0], $t23_get );
		++$t23_drawn;
		if ( t23_view_faults( $t23_page ) ) {
			$t23_faults[ $t23_reader . ': ' . $t23_view ] = t23_view_faults( $t23_page );
		}
	}
}
// A locked school whose signed copy waits for review: its representative's Overview and Agreement.
$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec2 ] = array( 'v' => 1, 'settled' => false, 'site_state' => WPCPM_Institution_Agreement::SUMMARY_SUBMITTED, 'airtable_status' => '' );
foreach ( t23_views_of( page_as( $locked, array() ), array() ) as $t23_view => $t23_get ) {
	$t23_page = page_as( $locked, $t23_get );
	++$t23_drawn;
	if ( t23_view_faults( $t23_page ) ) {
		$t23_faults[ 'a locked school\'s representative, a copy waiting: ' . $t23_view ] = t23_view_faults( $t23_page );
	}
}
delete_option( 'wpcpm_agreement_' . $rec2 );
ck( 'the page around the cards, every view of every reader: one H1, the headings in order, no id twice, the lede once, no title said again under the head', $t23_faults, array() );
ck( 'and the views drawn are every view of the six readers\' menus', $t23_drawn >= 25, true );
// The head the card suites draw their real cards under (`wpcpm_view_head()`) is the page's own, view by view: a
// representative's views, a locked school's Collaboration Agreement and an Administrator's For Administrators (its
// head row's Viewing as institution control set aside), read off the real page.
$t23_heads = array();
foreach ( array( array( $rep, array() ), array( $locked, array() ), array( $admin, array( 'wpcpm_institution_view' => $rec ) ) ) as $t23_who ) {
	foreach ( t23_views_of( page_as( $t23_who[0], $t23_who[1] ), $t23_who[1] ) as $t23_label => $t23_get ) {
		$t23_view = 'Agreement' === $t23_label ? 'Collaboration Agreement' : $t23_label; // The menu's short label.
		if ( ! isset( wpcpm_view_heads()[ $t23_view ] ) || isset( $t23_heads[ $t23_view ] ) ) {
			continue;
		}
		$t23_main = main_html( page_as( $t23_who[0], $t23_get ) );
		$t23_real = preg_match( '#<header class="wpcpm-head">.*?</header>#s', $t23_main, $t23_m ) ? $t23_m[0] : '';
		// An Administrator's head row holds the H1 and the Viewing as institution control; the rest is the same head.
		$t23_real = (string) preg_replace( '#<div class="wpcpm-ui-head-row">(<h1 class="wpcpm-head__eyebrow">[^<]*</h1>)<form class="wpcpm-ui-viewing".*?</form></div>#s', '$1', $t23_real );
		$t23_heads[ $t23_view ] = $t23_real === wpcpm_view_head( $t23_view ) ? 'same' : $t23_real;
	}
}
ksort( $t23_heads );
$t23_want = array_fill_keys( array_keys( wpcpm_view_heads() ), 'same' );
ksort( $t23_want );
ck( 'and the head every card suite draws its real card under is the page\'s own, for each of the seven views', $t23_heads, $t23_want );
unset( $GLOBALS['theme_support']['wpcpm-dashboard-frame'] );

$GLOBALS['kept']   = array();
$GLOBALS['manage'] = array( 4 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
