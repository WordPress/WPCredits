<?php
/**
 * WPCPM_Sponsors_Dashboard: the page, the gate, the render order and the routing.
 *
 * What each block pins, and why:
 *
 * - **The page is adopted, gated and titled the way every other dashboard's is.**
 *   metadata_exists() decides the gate, never the stored value, because a brand-new page
 *   reads as public by WordPress's own default.
 * - **render() decides who sees what before it draws anything.** Logged out gets a sign-in
 *   sentence; a stranger and a sponsor whose policy check fails both get the same refusal
 *   nothing_to_show() gives every other module, so neither ever learns which sponsor a
 *   record names.
 * - **The identity never prints an Airtable URL.** The logo is the site's own attachment or
 *   initials; a student is never named anywhere on this page.
 * - **The render order is the spec's:** the two-factor prompt, the identity, the resources
 *   (with the program contact folded in), then profile, mentors, interests, people, with the
 *   S2 to S4 cards left as silent gaps.
 * - **leave() and messages() are the one door every card's handler uses**, called by array
 *   callable so bin/check-references.php cannot be fooled into thinking the door exists
 *   before this class does.
 * - **The toolbar entry is membership, never the role**, and login routing mirrors the
 *   institution page's: an explicit destination is honoured, and the setting is a switch.
 *
 * Run from the plugin root:  php bin/test-sponsors-dashboard.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']       = array();
$GLOBALS['umeta']      = array();
$GLOBALS['pmeta']      = array();
$GLOBALS['users']      = array();
$GLOBALS['posts']      = array();
$GLOBALS['manage']     = array();
$GLOBALS['editors']    = array();
$GLOBALS['uid']        = 0;
$GLOBALS['calls']      = array();
$GLOBALS['styles']     = array();
$GLOBALS['queried']    = 0;
$GLOBALS['pagenow']    = 'index.php';
$GLOBALS['ajax']       = false;
$GLOBALS['next_id']    = 100;
$GLOBALS['transients'] = array();

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}
/**
 * The constructor's argument order (id, roles, name, email) is Task 8's own
 * (bin/test-sponsors-screen.php and bin/test-sponsor-cards.php), not bin/test-administrators-
 * dashboard.php's (id, name, email, roles): this suite's fixture below constructs every
 * account the first way (`new WP_User( 1, array( 'administrator' ), 'Manager' )`), and every
 * other sponsor suite already reads that way, so the copied class is adjusted here rather than
 * the fixture.
 */
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, array $roles = array(), $name = '', $email = '' ) {
		$this->ID           = (int) $id;
		$this->roles        = $roles;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( ' ', '', $name ) );
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_name = '', $post_content = '', $post_type = 'page', $post_status = 'publish';
}

/** The redirect leave() and login-related checks throw, instead of really redirecting. */
class WPCPM_Test_Redirect extends Exception {}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s, $protocols = null ) { return (string) $s; }
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
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/\\' ); }
function wp_doing_ajax() { return (bool) $GLOBALS['ajax']; }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['calls'][] = array( 'update_option', $k, $a ); return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }

function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }

function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ( '_wpcpm_access_level' === $k ? 'public' : '' ); }
// Like WordPress: the access level is registered with a default of 'public', which is what a
// page with no row at all answers. metadata_exists() is how the two are told apart.
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

function wp_login_url( $to = '' ) { return 'https://example.test/wp-login.php?redirect_to=' . rawurlencode( $to ); }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $k, $v = '', $u = '' ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function selected( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? " selected='selected'" : '';
	if ( $echo ) { echo $out; }
	return $out;
}
function checked( $a, $b = true, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? " checked='checked'" : '';
	if ( $echo ) { echo $out; }
	return $out;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce-' . esc_attr( $action ) . '" />'; }
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

/* ---- the stand-ins ---------------------------------------------------------------------- */

class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student'; const ROLE_MENTOR = 'wpcpm_mentor'; const ROLE_INSTITUTION = 'wpcpm_institution'; const ROLE_SPONSOR = 'wpcpm_sponsor'; const ROLE_ADMIN = 'administrator'; const CAP_MANAGE = 'wpcpm_manage_program';
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
}
class WPCPM_Content_Access { const META_KEY = '_wpcpm_access_level'; }
class WPCPM_Two_Factor { public static function prompt( $user ) { echo '<div class="wpcpm-two-factor-marker"></div>'; } }
class WPCPM_Handbook_Assistant { public static function render_resources( $audience = '', $extra = '' ) { $GLOBALS['resources'][] = $audience; return '<section class="wpcpm-student__section wpcpm-handbook__resources" data-audience="' . $audience . '">' . $extra . '</section>'; } }
/**
 * Extended with page_url() beyond the brief's own stand-in: WPCPM_Dashboards::links() (loaded
 * real below) calls WPCPM_Mentors_Dashboard::page_url() with no class_exists() guard, the way
 * it calls WPCPM_Students_Dashboard::page_url() too, so both need a full stand-in here or
 * links() fatals on this suite's very first call.
 */
class WPCPM_Mentors_Dashboard {
	const STYLE = 'wpcpm-mentor-dashboard';
	public static function register_assets() { if ( ! wp_style_is( self::STYLE, 'registered' ) ) { wp_register_style( self::STYLE, 'x/dashboard.css', array(), '1' ); } }
	public static function is_mentor( $user = null ) { return false; }
	public static function get_mentees( $id ) { return array(); }
	public static function page_url() { return ''; }
}
/** Not in the brief's own list: WPCPM_Dashboards::links() calls this one unconditionally too. */
class WPCPM_Students_Dashboard {
	public static function page_url() { return ''; }
	public static function is_student() { return false; }
}
class WPCPM_Mentors_Sync { public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); } public static function sponsorship() { return array(); } }
class WPCPM_Students_Sync { const META_RECORD_ID = 'wpcpm_student_record_id'; }
class WPCPM_Institution_Members { const META_RECORD_ID = 'wpcpm_institution_record_id'; const META_ACTIVE = 'wpcpm_institution_active'; public static function institution_of( $u = null ) { return ''; } }
class WPCPM_Institution_Audit { const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const GROUND_SYSTEM = 'system'; const EVIDENCE_INDEX = 'index'; const EVIDENCE_CACHE = 'cache'; public static function record_sponsor( array $e ) { return 1; } }
class WPCPM_Ceiling { public static function claim( $k, $l, $w, $a = 1 ) { return true; } public static function count( $k, $w ) { return 0; } public static function key( ...$p ) { return implode( ':', $p ); } }
class WPCPM_Flash { public static function set( $k, $v ) { $GLOBALS['flash'][ $k ] = $v; } public static function take( $k ) { $v = isset( $GLOBALS['flash'][ $k ] ) ? $GLOBALS['flash'][ $k ] : ''; unset( $GLOBALS['flash'][ $k ] ); return $v; } }
class WPCPM_Request {
	public static function text( $n, $f = '' ) { return isset( $GLOBALS['get'][ $n ] ) ? trim( (string) $GLOBALS['get'][ $n ] ) : $f; }
	public static function posted_text( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) ? trim( (string) $GLOBALS['post'][ $n ] ) : $f; }
	public static function is_explicit_redirect( $r ) { return '' !== (string) $r && false === strpos( (string) $r, 'wp-admin' ); }
}
class WPCPM_Settings { public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; } }
class WPCPM_Field_Value { public static function clean_url( $raw ) { return $raw; } }
class WPCPM_Mail { public static function send( $u, $c, $b ) { return true; } public static function send_to( $e, $c, $b ) { return true; } }
class WPCPM_Institutions { public static function notify_managers( $c, $b, $k = '' ) { return 1; } }

function wp_safe_redirect( $url ) { $GLOBALS['redirected'] = $url; throw new WPCPM_Test_Redirect( $url ); }
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { return 501 === (int) $id ? 'https://example.test/uploads/501.png' : false; }
/** As in bin/test-sponsor-members.php: filters by meta_key/meta_value, unlike the "return
 *  everyone" stand-in bin/test-administrators-dashboard.php uses, which this suite drops. */
function get_users( array $args ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $user ) {
		if ( isset( $args['meta_key'] ) ) {
			$have = get_user_meta( $user->ID, $args['meta_key'], true );
			if ( isset( $args['meta_value'] ) ) {
				if ( (string) $have !== (string) $args['meta_value'] ) { continue; }
			} elseif ( '' === (string) $have ) { continue; }
		}
		if ( isset( $args['role'] ) && ! in_array( $args['role'], $user->roles, true ) ) { continue; }
		$out[] = $user;
	}
	return $out;
}

// stubs/caps.php provides user_can() and current_user_can() reading $GLOBALS['manage'];
// this suite carries no user_can()/current_user_can() of its own, in place of both.
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/class-wpcpm-dashboards.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-profile.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-mentors.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-dashboard.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	$checks++;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

// The fixture: sponsor A (logo 501 copied), sponsor B (no logo, no manager), member 5 of A,
// manager 1, stranger 9.
$A = 'recSPONSOR0000001'; $B = 'recSPONSOR0000002';
WPCPM_Sponsors_Index::write( array( $A => array( 'name' => 'miniOrange ', 'status' => 'Approved', 'website' => 'plugins.miniorange.com', 'product_type' => 'Hosting', 'contact_person' => 'Rep One', 'contact_email' => 'maciej@a8c.com', 'manager' => 'recTEAM0000000001' ), $B => array( 'name' => 'Wetopi', 'status' => 'Approved' ) ), time() );
WPCPM_Sponsors_Index::write_team( array( 'recTEAM0000000001' => array( 'name' => 'Maciej (Matt) Pilarski', 'email' => 'maciej@a8c.com', 'calendly' => 'https://calendly.com/matt' ) ), time() );
WPCPM_Sponsors_Index::write_logo_record( $A, array( 'colour' => 501, 'source' => 'airtable', 'airtable_id' => 'attA' ) );
$GLOBALS['users'] = array( 1 => new WP_User( 1, array( 'administrator' ), 'Manager' ), 5 => new WP_User( 5, array( 'wpcpm_sponsor' ), 'Rep One' ), 9 => new WP_User( 9, array( 'subscriber' ), 'Stranger' ) );
$GLOBALS['umeta'][5] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $A, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['manage'] = array( 1 );
$GLOBALS['settings'] = array( 'sponsor_home' => true );
$D = 'WPCPM_Sponsors_Dashboard';

echo "=== The page ===\n";
ck( 'the constants', array( $D::SHORTCODE, $D::BLOCK, $D::OPT_PAGE, $D::STYLE, $D::SLUG, $D::FLASH ), array( 'wpcpm_sponsor_dashboard', 'wpcpm/sponsor-dashboard', 'wpcpm_sponsor_page_id', 'wpcpm-sponsor-dashboard', 'sponsor-dashboard', 'sponsor_dashboard' ) );
$page_id = $D::ensure_page();
ck( 'ensure_page() creates the page and records it', $page_id > 0 && (int) get_option( $D::OPT_PAGE ) === $page_id, true );
ck( 'titled and slugged', array( get_post( $page_id )->post_title, get_post( $page_id )->post_name, get_post( $page_id )->post_content ), array( 'Sponsor Dashboard', 'sponsor-dashboard', '<!-- wp:wpcpm/sponsor-dashboard /-->' ) );
ck( 'gated to the sponsor level by writing the meta, since the default reads as public', array( metadata_exists( 'post', $page_id, WPCPM_Content_Access::META_KEY ), get_post_meta( $page_id, WPCPM_Content_Access::META_KEY, true ) ), array( true, 'wpcpm_sponsor' ) );
ck( 'ensure_page() again adopts, never duplicates', $D::ensure_page(), $page_id );
update_post_meta( $page_id, WPCPM_Content_Access::META_KEY, 'public' );
$D::ensure_page();
ck( 'a level somebody set deliberately is left alone', get_post_meta( $page_id, WPCPM_Content_Access::META_KEY, true ), 'public' );
update_post_meta( $page_id, WPCPM_Content_Access::META_KEY, 'wpcpm_sponsor' );
ck( 'page_url() is the permalink of a published page', $D::page_url(), get_permalink( $page_id ) );
$GLOBALS['posts'][ $page_id ]->post_status = 'trash';
ck( 'and nothing for a trashed one', $D::page_url(), '' );
$GLOBALS['posts'][ $page_id ]->post_status = 'publish';
unset( $GLOBALS['opts'][ $D::OPT_PAGE ] );
ck( 'a page that exists by slug is adopted when the option is lost', $D::ensure_page(), $page_id );
ck( 'the title revision is stamped', ( function () use ( $D ) { $D::maybe_rename_page(); return (int) get_option( $D::OPT_TITLE_FIXED ); } )(), $D::TITLE_VERSION );

echo "\n=== Who sees what ===\n";
$GLOBALS['uid'] = 0; $GLOBALS['logged_in'] = false;
$out = $D::render();
ck( 'logged out: one sentence and a sign-in link, no form', false !== strpos( $out, 'Sign in' ) && false !== strpos( $out, 'wp-login.php' ) && false === strpos( $out, '<form' ), true );
$GLOBALS['logged_in'] = true; $GLOBALS['uid'] = 9;
$out = $D::render();
ck( 'a stranger: the sponsors refusal, no form, no identity', false !== strpos( $out, 'This page is for the program sponsors.' ) && false === strpos( $out, '<form' ) && false === strpos( $out, 'wpcpm-sponsor__identity' ), true );
$GLOBALS['uid'] = 5; $GLOBALS['resources'] = array(); $GLOBALS['styles'] = array(); $GLOBALS['scripts'] = array();
$D::register();
$out = $D::render();
$order = array();
foreach ( array( 'wpcpm-two-factor-marker', 'wpcpm-sponsor__identity', 'wpcpm-handbook__resources', 'id="wpcpm-sponsor-profile"', 'id="wpcpm-sponsor-mentors"', 'id="wpcpm-sponsor-interests"', 'id="wpcpm-sponsor-people"' ) as $needle ) { $order[] = strpos( $out, $needle ); }
$sorted = $order; sort( $sorted );
ck( 'a member sees the prompt, the identity, the resources, then the four cards in the spec\'s order', ! in_array( false, $order, true ) && $order === $sorted, true );
ck( 'the resources are the sponsor audience', $GLOBALS['resources'], array( 'sponsor' ) );
ck( 'the identity shows the site\'s logo, never Airtable\'s URL', false !== strpos( $out, 'https://example.test/uploads/501.png' ) && false === strpos( $out, 'airtableusercontent' ), true );
ck( 'the name trimmed, the website completed, the product type and the contact', false !== strpos( $out, '>miniOrange<' ) && false !== strpos( $out, 'href="https://plugins.miniorange.com"' ) && false !== strpos( $out, 'Hosting' ) && false !== strpos( $out, 'Rep One' ) && false !== strpos( $out, 'maciej@a8c.com' ), true );
ck( 'the program contact block names the manager, the address and the booking link', false !== strpos( $out, 'Maciej (Matt) Pilarski' ) && false !== strpos( $out, 'mailto:maciej@a8c.com' ) && false !== strpos( $out, 'https://calendly.com/matt' ), true );
ck( 'no switcher for a member', strpos( $out, 'wpcpm-dashboard__switcher' ), false );
ck( 'no heading unless the block asks for one', strpos( $out, 'wpcpm-dashboard__title' ), false );
ck( 'the block\'s optional heading is honoured', false !== strpos( $D::render( array( 'title' => 'Our sponsorship' ) ), '<h2 class="wpcpm-dashboard__title">Our sponsorship</h2>' ), true );
ck( 'the people card lists the accounts and offers no form to a member', false !== strpos( $out, 'Rep One' ) && 0 === substr_count( substr( $out, strpos( $out, 'id="wpcpm-sponsor-people"' ) ), '<form' ), true );
ck( 'the stylesheet is registered from assets/css/sponsor.css and switched on', isset( $GLOBALS['styles'][ $D::STYLE ] ) && false !== strpos( $GLOBALS['styles'][ $D::STYLE ]['src'], 'assets/css/sponsor.css' ) && ! empty( $GLOBALS['styles'][ $D::STYLE ]['on'] ), true );
ck( 'the double-submit guard is armed', in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ), true );
ck( 'no student is named anywhere', strpos( $out, 'Student' ), false );

echo "\n=== A manager ===\n";
$GLOBALS['uid'] = 1; $GLOBALS['get'] = array( WPCPM_Sponsor_Roster::ARG_VIEW => $B );
$out = $D::render();
ck( 'the switcher lists both sponsors and the manager is viewing B', false !== strpos( $out, 'name="' . WPCPM_Sponsor_Roster::ARG_VIEW . '"' ) && false !== strpos( $out, 'value="' . $B . '" selected' ), true );
ck( 'B has no logo: initials stand in', false !== strpos( $out, 'wpcpm-sponsor__initials' ) && false !== strpos( $out, '>W<' ), true );
ck( 'B has no manager: no contact block, and no empty heading', strpos( $out, 'wpcpm-resources__contact' ), false );
ck( 'a manager sees the status line', false !== strpos( $out, 'Status: Approved' ), true );
ck( 'the people card points a manager at the Sponsors screen', false !== strpos( substr( $out, strpos( $out, 'id="wpcpm-sponsor-people"' ) ), 'page=wpcpm-sponsors' ), true );
$GLOBALS['get'] = array();

echo "\n=== Messages and leave() ===\n";
$GLOBALS['uid'] = 5;
$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => 'profile-saved', 'card' => 'profile' );
$out = $D::render();
ck( 'a flash prints its sentence with the success tone', false !== strpos( $out, 'wpcpm-dashboard__message--success' ) && false !== strpos( $out, 'Your profile was saved' ), true );
ck( 'and opens the card it names', false !== strpos( $out, 'id="wpcpm-sponsor-profile" class="wpcpm-group wpcpm-group__disclosure" open' ), true );
$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => 'refused', 'card' => '', 'detail' => 'Line 3 repeats line 1.' );
ck( 'a flash may carry a detail, printed after the sentence', false !== strpos( $D::render(), '<span class="wpcpm-dashboard__detail">Line 3 repeats line 1.</span>' ), true );
try { $D::leave( 'codes-refused', 'offers', $A, str_repeat( 'x', 400 ) ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'leave() stores the detail trimmed to three hundred characters', mb_strlen( $GLOBALS['flash'][ $D::FLASH ]['detail'] ), 300 );
// leave() always flashes before it redirects, so the call above left one sitting on the channel
// again; drained here rather than left for the "taken, so it shows once" check below to trip
// over; a real request would have consumed it on the next page load, not this one.
unset( $GLOBALS['flash'][ $D::FLASH ] );
// Every card is a section with the disclosure inside it, the way the Institution Dashboard wraps its
// semester report: the section carries the card rhythm (the rule above, the room), the disclosure only
// folds (1.93.2). A card class left on the disclosure would draw that rhythm on the wrong box.
ck( 'every card is a section wrapping its disclosure', preg_match_all( '#<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-#', $out ), substr_count( $out, '<details id="wpcpm-sponsor-' ) );
ck( 'and at least the profile and the people cards render that way', preg_match_all( '#<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-#', $out ) >= 2, true );
ck( 'the disclosure itself no longer carries the card class', strpos( $out, 'wpcpm-group__disclosure wpcpm-sponsor__card"' ), false );
ck( 'each section closes right after its disclosure', substr_count( $out, '</details></section>' ), substr_count( $out, '<section class="wpcpm-sponsor__card">' ) );
ck( 'taken, so it shows once', isset( $GLOBALS['flash'][ $D::FLASH ] ), false );
$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => 'refused', 'card' => '' );
ck( 'a refusal prints with the error tone', false !== strpos( $D::render(), 'wpcpm-dashboard__message--error' ), true );
try { $D::leave( 'interest-sent', 'interests', $A ); } catch ( WPCPM_Test_Redirect $e ) {}
// leave() now always writes a 'detail' key too (Task 6), empty or not: this call passes none, so it is ''.
ck( 'leave() flashes the status and the card', $GLOBALS['flash'][ $D::FLASH ], array( 'status' => 'interest-sent', 'card' => 'interests', 'detail' => '' ) );
ck( 'and sends a member to the page and the card, without the switcher argument', $GLOBALS['redirected'], get_permalink( $page_id ) . '#wpcpm-sponsor-interests' );
$GLOBALS['uid'] = 1;
try { $D::leave( 'interest-sent', 'interests', $A ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'a manager is sent back through the switcher', false !== strpos( $GLOBALS['redirected'], WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A ) && false !== strpos( $GLOBALS['redirected'], '#wpcpm-sponsor-interests' ), true );
ck( 'messages() knows every card\'s statuses', array_values( array_diff( array( 'profile-saved', 'interest-sent', 'mentor-interest-sent', 'refused' ), array_keys( $D::messages() ) ) ), array() );

echo "\n=== The toolbar and login routing ===\n";
$GLOBALS['uid'] = 5;
ck( 'a member\'s toolbar lists the Sponsor Dashboard as their own', ( function () { foreach ( WPCPM_Dashboards::links() as $l ) { if ( 'wpcpm-sponsor-dashboard' === $l['id'] ) { return array( $l['title'], $l['own'] ); } } return null; } )(), array( 'Sponsor Dashboard', true ) );
$GLOBALS['uid'] = 9;
ck( 'and a stranger\'s does not', in_array( 'wpcpm-sponsor-dashboard', array_column( WPCPM_Dashboards::links(), 'id' ), true ), false );
$GLOBALS['uid'] = 5;
ck( 'a member is routed to the page at login', $D::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][5] ), get_permalink( $page_id ) );
ck( 'an explicit destination is honoured', $D::login_redirect( 'https://example.test/somewhere/', 'https://example.test/somewhere/', $GLOBALS['users'][5] ), 'https://example.test/somewhere/' );
$GLOBALS['settings']['sponsor_home'] = false;
ck( 'and the switch turns it off', $D::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][5] ), 'https://example.test/wp-admin/' );

echo "\n=== House rules ===\n";
$src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-dashboard.php' );
ck( 'no em or en dash in the class or the stylesheet', preg_match( '/\x{2013}|\x{2014}/u', $src . file_get_contents( __DIR__ . '/../assets/css/sponsor.css' ) ), 0 );
ck( 'the gate is metadata_exists(), never the value', false !== strpos( $src, 'metadata_exists(' ), true );
ck( 'the block carries the version', json_decode( file_get_contents( __DIR__ . '/../blocks/sponsor-dashboard/block.json' ), true )['version'], '1.93.0' );

// Counted by ck() itself: a literal here read 45 while the file carried 49 (1.93.2), and a count
// nobody maintains is a count nobody should trust.
printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', (int) $checks );
exit( $fail ? 1 : 0 );
