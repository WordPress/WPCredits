<?php
/**
 * WPCPM_Sponsors_Dashboard: the page, the gate, the frame, its views and the routing.
 *
 * What each block pins, and why:
 *
 * - **The page is adopted, gated and titled the way every other dashboard's is.**
 *   metadata_exists() decides the gate, never the stored value, because a brand-new page
 *   reads as public by WordPress's own default.
 * - **render() decides who sees what before it draws anything.** Logged out gets a sign-in
 *   sentence; a stranger and a sponsor whose policy check fails both get the same refusal
 *   nothing_to_show() gives every other module, so neither ever learns which sponsor a
 *   record names, and neither is drawn the frame.
 * - **The page is drawn in the dashboards' frame, one view per menu item (1.123.78).** `?wpcpm_view=`
 *   names the view: none is the Overview, an unknown one is the Overview, and Your sponsorship is the
 *   Overview too while the tiers are not launched. Every view is the frame around the page's own box,
 *   the menu marks the view drawn as current, and the page's name is its one H1 only while the theme
 *   leaves the page to the frame (`add_theme_support( 'wpcpm-dashboard-frame', array( 'sponsor' ) )`).
 * - **Every link to a card goes to the view that holds it.** view_url() and card_url() make the
 *   addresses, checked against bin/stubs/sponsor-card-url.php, which the other suites use; leave()
 *   sends a handler's reader back to that view, with the sponsor an Administrator was looking at.
 * - **The Overview:** the head (the logo or initials, the company's name, a thank-you, the status for
 *   an Administrator, New offer), the figures while the store holds the sponsor's row, what waits on
 *   the company's people as rows with a red status tag in words, and the mentors door. The rows are
 *   the At a glance strip's tests, kind for kind and in its order (`waiting_items()`): the tier's term
 *   in its last thirty days or ended, while the tiers are launched; offers returned, running low and
 *   not switched on yet; posts returned while the Posts card is drawn; the latest event returned only
 *   where the Events card shows its note, inside the planner's plan form (not for a term used, an
 *   event open or a tier with no planner, and only for the running term, 1.123.40); the agreement
 *   returned or revoked; the profile's missing details. Each links to its card at its view, and the
 *   menu counts what waits beside each view, all of it beside Overview. Nothing waiting is one quiet
 *   line. That section draws the Offers, Posts and Collaboration Agreement cards through stand-ins
 *   that answer the one question a row asks, and loads the real event store and what the planner reads, as
 *   bin/test-sponsor-cards.php does, so the Events card holds a real planner.
 * - **The other views:** each a head (its title and one sentence) and its cards in a box of their own,
 *   or "Nothing to show here yet." when they print nothing, white space included: this suite runs
 *   itself as a child (`--blank-view-child`) whose Your sponsorship cards are stand-ins that print only
 *   white space, since a class once loaded cannot be swapped here. Your sponsorship's sentence names
 *   exactly the cards it draws, in their order, each of its eight lines one whole sentence in the
 *   source (1.123.22); the Offers view's says what the company wrote only while the Posts card is
 *   drawn. The People card, this page's own, folds on Your company and says how many accounts act for
 *   the sponsor.
 * - **The identity never prints an Airtable URL.** The logo is the site's own attachment or
 *   initials; no view names a student; no head prints the website, the contact person or an address,
 *   which are the profile card's.
 * - **Help is the menu's (1.123.78):** the Sponsor guide while its page is published, and Write to the
 *   program, a mail link to the company's contact at the program while that account is a program
 *   manager. The Resources block and the program contact block are gone, and with them the contact's
 *   name and booking link: a contact who lost the capability is named nowhere.
 * - **The four movable sections are gone with the arrows (1.123.78):** a view per section leaves nothing
 *   to arrange. No view prints a module's markup, init() hooks no mover, and a company's saved order,
 *   kept in its option for uninstall, changes nothing.
 * - **Offer more is off the page (1.123.22).** No view draws it, no row names it, and messages()
 *   merges none of its statuses. WPCPM_Sponsor_Interests stays, the one road an interest takes.
 * - **The plugin's own sheets dress what the theme dresses on this page**, value for value where
 *   the base's rhythm allows: a row's state, and its action beside the row with the card as its
 *   positioning context.
 * - **The page's scripts:** the double-submit guard, the badge card's Copy script and the opener
 *   that opens a card a link lands on, the one the Administrator Dashboard enqueues too, each
 *   enqueued, and the Copy script never registered over a registration something else made first.
 *   The page asks the dashboards' shell for the opener, and how the shell registers it, a
 *   registration made first kept, is pinned in bin/test-dashboard-card.php.
 * - **leave() and messages() are the one door every card's handler uses**, called by array
 *   callable so bin/check-references.php cannot be fooled into thinking the door exists
 *   before this class does. Every card a view draws is merged into messages(), a card with
 *   no form of its own among them, so the two lists move together; so is the Events card's
 *   planner (1.117.0), whose presses leave through the same door from inside the tier card.
 *   An outcome is printed inside the card it names on that card's view, and at the top of any
 *   other view.
 * - **A refusal whose sentence is its detail shows a notice.** A refusal for words the cleaner
 *   would take (the profile's and the Offers card's) has no sentence of its own: the card's
 *   sentence, which names the box, is the detail, and the page prints it alone.
 *   With neither a sentence nor a detail, or with a sentence whose values the flash does not carry,
 *   nothing is printed, as before. A detail is printed escaped, after a sentence and alone alike.
 *   A child run (`--loss-child`) loads the real Offers card, presses
 *   each card's own Save as the member with a box the cleaner would take words from, and reads the
 *   view each lands on: the notice in the error tone and the box holding what was typed. leave()
 *   keeps a detail as core's one-line cleaner leaves it (bin/stubs/cleaners.php), and the esc_html()
 *   stand-in keeps an entity it finds, as core's does.
 * - **The toolbar entry is membership, never the role**, and login routing mirrors the
 *   institution page's: an explicit destination is honored, and the setting is a switch.
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
 * The constructor's argument order (id, roles, name, email) is the sponsor suites' own
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
	public $ID = 0, $post_title = '', $post_name = '', $post_content = '', $post_type = 'page', $post_status = 'publish', $post_author = 0;
}

/** The redirect leave() and login-related checks throw, instead of really redirecting. */
class WPCPM_Test_Redirect extends Exception {}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
// The H1's "sponsor since" names a month and year in a format with a translator's context.
function _x( $s, $c, $d = null ) { return $s; }
// As core's esc_html(): an entity the text already holds, the ones the one-line cleaner writes among them, is kept, not encoded again.
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
// As core's with ENT_QUOTES: the five entities a cleaner writes, read back once. The event planner's
// Change form measures what its boxes draw with it (WPCPM_Typed_Text::drawn_limit()).
function wp_specialchars_decode( $s, $quotes = ENT_NOQUOTES ) { return htmlspecialchars_decode( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s, $protocols = null ) { return (string) $s; }
function wp_kses( $s, $allowed ) { return (string) $s; }
// Core's two text cleaners (bin/stubs/cleaners.php): leave() keeps a detail as the one-line cleaner
// leaves it, and a stand-in on strip_tags() alone would cut a refusal's sentence at its first "<".
require_once __DIR__ . '/stubs/cleaners.php';
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
// Core's, as far as the fixtures' names reach it: one accent, on the sponsor the switcher's order is
// pinned with. The switcher sorts by it.
function remove_accents( $s, $locale = '' ) { return strtr( (string) $s, array( 'Á' => 'A' ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_action', $h, $p ); }
function add_filter( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_filter', $h, $p ); }
function add_shortcode( $tag, $c ) { $GLOBALS['calls'][] = array( 'add_shortcode', $tag ); }
function register_block_type( $dir, $args = array() ) { $GLOBALS['calls'][] = array( 'register_block_type', $dir ); return true; }
// With the thousands separator core's English locale prints, so a number that skipped it shows.
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/\\' ); }
// The Mentors card reads a mentor's WordPress.org username out of the profile address it is given.
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( (string) $u ) : parse_url( (string) $u, $c ); }
function wp_doing_ajax() { return (bool) $GLOBALS['ajax']; }
// The tier card reads today once per page (WPCPM_Sponsor_Tiers::today()). Until the section on what
// waits pins a day in $GLOBALS['today'], today is the clock's, which nothing above it pins; every
// other call formats the timestamp it is given, on the clock it is given, else on UTC, the site's,
// and the figures' "As of" line, which asks for now in the site's format, is read as words.
function wp_date( $format, $ts = null, $zone = null ) {
	if ( null === $ts && 'Y-m-d' === $format && isset( $GLOBALS['today'] ) ) { return $GLOBALS['today']; }
	$ts = null === $ts ? time() : (int) $ts;
	return $zone instanceof DateTimeZone ? ( new DateTime( '@' . $ts ) )->setTimezone( $zone )->format( $format ) : gmdate( $format, $ts );
}
// The event store and the planner, loaded in the section on what waits, read the site's clock by these.
function wp_timezone_string() { return 'UTC'; }
function wp_timezone() { return new DateTimeZone( 'UTC' ); }

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
// A post or its ID, as core takes either: the menu hands over the Sponsor guide's page and the newest
// update as posts.
function get_permalink( $id = 0 ) { $p = $id instanceof WP_Post ? $id : get_post( $id ); return $p instanceof WP_Post ? 'https://example.test/' . $p->post_name . '/' : false; }
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
	// The default stays 'page' (this suite's own pages never pass one), but the store (1.111.0)
	// asks for its own post type by name and would otherwise never be found again by it.
	$post->post_type    = $args['post_type'] ?? 'page';
	$post->post_author  = (int) ( $args['post_author'] ?? 0 );
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
// The array form too, as core takes it, for the report's printed document and PDF links, which
// the Reports card draws (1.115.0); the key and value form every other caller uses is unchanged.
function add_query_arg( $k, $v = '', $u = '' ) {
	if ( is_array( $k ) ) {
		$u = (string) $v;
		return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . http_build_query( $k );
	}
	return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v;
}
function wp_nonce_url( $url, $action = -1 ) { return $url . '&_wpnonce=' . rawurlencode( (string) $action ); }
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
// A handle is registered once, as core keeps it: 'registered' answers whether wp_register_script()
// ran for it, here or, in the check that says so, somewhere else first. Each registration is kept,
// with the dependencies and the footer flag it asked for.
function wp_script_is( $handle, $list = 'enqueued' ) { return 'registered' === $list && isset( $GLOBALS['script_src'][ $handle ] ); }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) { $GLOBALS['script_src'][ $handle ] = $src; $GLOBALS['script_args'][ $handle ] = array( $deps, $footer ); $GLOBALS['script_registrations'][] = $handle; }

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
/**
 * The Handbook Assistant, as far as this page could reach it: the Resources block the page drew under
 * the At a glance strip before 1.123.78, recorded as `audience:columns` whenever it is asked for, which
 * the page no longer does (its links are the menu's). It answers none of the questions the frame asks
 * before it draws the Ask box (`is_available()` and the rest), so no view here carries one.
 */
class WPCPM_Handbook_Assistant {
	public static function render_resources( $audience = '', $extra = '', $columns = 2 ) {
		$GLOBALS['resources'][] = $audience . ':' . (int) $columns;
		return '<section class="wpcpm-student__section wpcpm-handbook__resources" data-audience="' . $audience . '">' . $extra . '</section>';
	}
}
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
/**
 * The Administrator Dashboard's stylesheet registration, which the sponsor sheet depends on: the
 * Overview's quiet lines are that page's, dressed by its rules. Registered here the way the real class
 * registers it, on the mentor sheet, and only when nothing registered it first.
 */
class WPCPM_Administrators_Dashboard {
	const STYLE = 'wpcpm-administrator-dashboard';
	public static function register_assets() { WPCPM_Mentors_Dashboard::register_assets(); if ( ! wp_style_is( self::STYLE, 'registered' ) ) { wp_register_style( self::STYLE, 'x/administrator.css', array( WPCPM_Mentors_Dashboard::STYLE ), '1' ); } }
}
/** Not in the brief's own list: WPCPM_Dashboards::links() calls this one unconditionally too. */
class WPCPM_Students_Dashboard {
	public static function page_url() { return ''; }
	public static function is_student() { return false; }
}
/**
 * The real is_record_id() trims what it is given and refuses what is not a scalar; the stand-in does
 * the same, so the check that a minted site key is no record ID reads the real answer. The mentors
 * sync's index is empty unless a check fills `$GLOBALS['mentors_index']`, as the checks of the
 * Mentors card and the Overview's door do.
 */
class WPCPM_Mentors_Sync { public static function is_record_id( $v ) { return is_scalar( $v ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $v ) ); } public static function sponsorship() { return isset( $GLOBALS['mentors_index'] ) ? (array) $GLOBALS['mentors_index'] : array(); } }
class WPCPM_Students_Sync { const META_RECORD_ID = 'wpcpm_student_record_id'; }
class WPCPM_Institution_Members { const META_RECORD_ID = 'wpcpm_institution_record_id'; const META_ACTIVE = 'wpcpm_institution_active'; public static function institution_of( $u = null ) { return ''; } }
class WPCPM_Institution_Audit { const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const GROUND_SYSTEM = 'system'; const EVIDENCE_INDEX = 'index'; const EVIDENCE_CACHE = 'cache'; public static function record_sponsor( array $e ) { return 1; } }
class WPCPM_Ceiling { public static function claim( $k, $l, $w, $a = 1 ) { return true; } public static function count( $k, $w ) { return 0; } public static function key( ...$p ) { return implode( ':', $p ); } }
class WPCPM_Flash {
	public static function set( $k, $v ) { $GLOBALS['flash'][ $k ] = $v; }
	public static function take( $k ) { $v = isset( $GLOBALS['flash'][ $k ] ) ? $GLOBALS['flash'][ $k ] : ''; unset( $GLOBALS['flash'][ $k ] ); return $v; }
	// What a refused form's kept typing sweeps first (`WPCPM_Typed_Text::keep()`): each channel the test picks, cleared unread.
	public static function sweep( $stale ) { foreach ( (array) ( $GLOBALS['flash'] ?? array() ) as $k => $v ) { if ( $stale( (string) $k, $v ) ) { unset( $GLOBALS['flash'][ $k ] ); } } }
	// What a refused form keeps (`WPCPM_Flash::keep()`, 1.123.58): its values and the sentence for each refused field, on a channel of its own, taken once.
	public static function keep( $form, array $values, array $problems = array() ) { $GLOBALS['flash']['kept:' . $form] = array( 'at' => time(), 'values' => $values, 'problems' => $problems ); }
	public static function kept( $form ) { $entry = self::take( 'kept:' . $form ); return array( 'values' => is_array( $entry ) && isset( $entry['values'] ) ? $entry['values'] : array(), 'problems' => is_array( $entry ) && isset( $entry['problems'] ) ? $entry['problems'] : array() ); }
	public static function forget( $form ) { self::take( 'kept:' . $form ); }
}
class WPCPM_Request {
	public static function text( $n, $f = '' ) { return isset( $GLOBALS['get'][ $n ] ) ? trim( (string) $GLOBALS['get'][ $n ] ) : $f; }
	public static function posted_text( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) ? trim( (string) $GLOBALS['post'][ $n ] ) : $f; }
	// The readers a card's Save reads its form with, as the real class reads them, for the checks that press one (`--loss-child`).
	public static function posted_lines( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) && is_scalar( $GLOBALS['post'][ $n ] ) ? trim( sanitize_textarea_field( (string) $GLOBALS['post'][ $n ] ) ) : $f; }
	public static function posted_raw( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) && is_scalar( $GLOBALS['post'][ $n ] ) ? (string) $GLOBALS['post'][ $n ] : $f; }
	public static function posted_verbatim( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) && is_scalar( $GLOBALS['post'][ $n ] ) ? trim( (string) $GLOBALS['post'][ $n ] ) : $f; }
	public static function posted_key( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) ? sanitize_key( (string) $GLOBALS['post'][ $n ] ) : $f; }
	public static function posted_id( $n ) { return isset( $GLOBALS['post'][ $n ] ) && is_scalar( $GLOBALS['post'][ $n ] ) ? absint( $GLOBALS['post'][ $n ] ) : 0; }
	public static function is_explicit_redirect( $r ) { return '' !== (string) $r && false === strpos( (string) $r, 'wp-admin' ); }
}
class WPCPM_Settings { public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; } }
class WPCPM_Field_Value { public static function clean_url( $raw ) { return $raw; } }
class WPCPM_Mail { public static function send( $u, $c, $b ) { return true; } public static function send_to( $e, $c, $b ) { return true; } }
class WPCPM_Institutions { public static function notify_managers( $c, $b, $k = '' ) { return 1; } }
/**
 * The Sponsors module, as far as the page reaches it: the address of one sponsor's accounts on its
 * screen, which the People card sends a manager to. The module's own builder of that address is
 * pinned by bin/test-sponsors-screen.php.
 */
class WPCPM_Sponsors {
	public static function accounts_view_url( $record ) { return admin_url( 'admin.php?page=wpcpm-sponsors&tab=accounts&wpcpm_sponsor=' . rawurlencode( (string) $record ) ); }
}

function wp_safe_redirect( $url ) { $GLOBALS['redirected'] = $url; throw new WPCPM_Test_Redirect( $url ); }
// A card's handler: the nonce it checks first is recorded, and a refusal is a die with its status.
if ( ! function_exists( 'check_admin_referer' ) ) { function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) { $GLOBALS['nonce_checked'][] = $action; return 1; } }
function wp_die( $message = '', $title = '', $args = array() ) { $GLOBALS['died'] = (string) $message; throw new Exception( 'die: ' . ( is_array( $args ) && isset( $args['response'] ) ? $args['response'] : 'died' ) ); }
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
require_once __DIR__ . '/stubs/posts.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/class-wpcpm-form-errors.php';
require_once __DIR__ . '/../includes/class-wpcpm-dates.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/class-wpcpm-dashboards.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-manager-booking.php';
// The shell every card is drawn in.
require_once __DIR__ . '/../includes/class-wpcpm-dashboard-card.php';
// The frame of the version 3 concepts the page is drawn in since 1.123.78: the top bar, the menu and the
// main area, with the few WordPress functions it asks that the page did not ask before.
require_once __DIR__ . '/../includes/class-wpcpm-icons.php';
require_once __DIR__ . '/../includes/class-wpcpm-dashboard-frame.php';
require_once __DIR__ . '/stubs/sponsor-card-url.php';
function get_theme_support( $feature ) { return $GLOBALS['theme_support'][ $feature ] ?? false; }
function get_avatar( $id, $size = 96, $default = '', $alt = '', $args = array() ) { return '<img class="' . ( $args['class'] ?? 'avatar' ) . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '">'; }
function wp_logout_url( $redirect = '' ) { return 'https://example.test/wp-login.php?action=logout'; }
if ( ! function_exists( 'sanitize_email' ) ) { function sanitize_email( $e ) { return filter_var( (string) $e, FILTER_VALIDATE_EMAIL ) ? (string) $e : ''; } }
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-profile.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-mentors.php';
// Run as a child (`--blank-view-child`) by the check that a view whose cards print nothing but white
// space says it has nothing to show: there Your sponsorship's three cards are stand-ins printing only
// white space, as a card could with a stray newline before it returns, the real ones never loaded, and
// the child prints what the view drew and stops. Each stand-in also answers what the page asks of its
// card before it draws one (the term's days for what waits, the tests the view's sentence is made of),
// as the real card answers for a sponsor with nothing to say.
$blank_view_child = in_array( '--blank-view-child', (array) ( $argv ?? array() ), true );
// Run as a child (`--loss-child`) by the check that a refused save shows its sentence: there the
// real Offers card is loaded, which the page here otherwise leaves as a gap, and the member presses
// the profile's and the Offers card's own Save, each with a box the cleaner would take words from,
// and loads the view each press lands on.
$loss_child = in_array( '--loss-child', (array) ( $argv ?? array() ), true );
if ( $blank_view_child ) {
	/** White space alone, where the sponsorship card and Events would be; no term, and no events. */
	class WPCPM_Sponsor_Tier_Card {
		const CARD = 'sponsorship'; const CARD_EVENTS = 'events';
		public static function render( $record, array $context ) { echo "\n \t\n"; }
		public static function term_days_left( array $row, $today ) { return null; }
		public static function draws_events( $record, array $row, $tier ) { return false; }
	}
	/** White space alone, where Reports would be; no final report. */
	class WPCPM_Sponsor_Report_Card { const CARD = 'reports'; public static function render( $record, array $context ) { echo "\n \t\n"; } public static function finals( $record ) { return array(); } }
	/** White space alone, where the badge would be; no badge to draw. */
	class WPCPM_Sponsor_Badge { const CARD = 'badge'; public static function render( $record, array $context ) { echo "\n \t\n"; } public static function eligible( array $row ) { return false; } }
} else {
	require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tier-card.php';
	require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-badge.php';
}
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-files.php';
// Loaded only when it is there, so that before the card exists its checks fail as checks.
if ( ! $blank_view_child && file_exists( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-card.php' ) ) {
	require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-card.php';
}
// The Events card's planner (1.117.0), drawn inside the tier card and so in no card list of its own:
// its words have to reach messages() all the same. Loaded without the event store, whose absence it
// guards, so the planner draws nothing here and only its words are asked for.
if ( file_exists( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-planner.php' ) ) {
	require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-planner.php';
}
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
// 1.111.0: the manager is a site user id now, not a Team Members record - user 1 below, the
// numeric form of what "manager 1" always meant.
$A = 'recSPONSOR0000001'; $B = 'recSPONSOR0000002';
WPCPM_Sponsors_Index::write( array( $A => array( 'name' => 'Mango Example ', 'status' => 'Approved', 'website' => 'plugins.mango-example.test', 'product_type' => 'Hosting', 'contact_person' => 'Rep One', 'contact_email' => 'administrator@example.test', 'manager' => '1' ), $B => array( 'name' => 'Wexample', 'status' => 'Approved' ) ), time() );
WPCPM_Sponsors_Index::write_logo_record( $A, array( 'colour' => 501, 'source' => 'airtable', 'airtable_id' => 'attA' ) );
// 1.111.0: the manager is a site user now (manager_of() resolves through get_userdata()), so
// user 1 carries the name and address the held Team Members option used to, and the booking link
// is the field on that user's own profile, set through the real WPCPM_Manager_Booking.
$GLOBALS['users'] = array( 1 => new WP_User( 1, array( 'administrator' ), 'Program Administrator', 'administrator@example.test' ), 5 => new WP_User( 5, array( 'wpcpm_sponsor' ), 'Rep One' ), 9 => new WP_User( 9, array( 'subscriber' ), 'Stranger' ) );
WPCPM_Manager_Booking::set( 1, 'https://calendly.example/administrator' );
$GLOBALS['umeta'][5] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $A, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['manage'] = array( 1 );
$GLOBALS['settings'] = array( 'sponsor_home' => true );
$D = 'WPCPM_Sponsors_Dashboard';

// The child: a member's Your sponsorship with the launch switch on, whose cards print only white
// space. It says which view the page drew, whether its head did, whether the white space or a box of
// cards reached it, and whether the one quiet line stands right under the head.
if ( $blank_view_child ) {
	$GLOBALS['uid']                            = 5;
	$GLOBALS['settings']['sponsor_tiers_live'] = true;
	$_GET[ $D::VIEW_ARG ]                      = $D::VIEW_SPONSORSHIP;
	$blank_page                                = $D::render();
	echo 'view ' . ( preg_match( '#<div class="wpcpm-dashboard wpcpm-sponsor wpcpm-sponsor--([a-z]+)">#', $blank_page, $blank_view ) ? $blank_view[1] : 'none' ) . "\n";
	echo 'head ' . ( false !== strpos( $blank_page, '<h2 class="wpcpm-head__title">Your sponsorship</h2>' ) ? 'printed' : 'not printed' ) . "\n";
	echo 'white space ' . ( false !== strpos( $blank_page, "\n \t\n" ) ? 'printed' : 'not printed' ) . "\n";
	echo 'cards ' . ( false !== strpos( $blank_page, 'wpcpm-sponsor__cards' ) ? 'printed' : 'not printed' ) . "\n";
	echo 'quiet ' . ( false !== strpos( $blank_page, '</header><p class="wpcpm-overview__quiet">Nothing to show here yet.</p></div>' ) ? 'right under the head, closing the view' : 'not there' ) . "\n";
	exit( 0 );
}

// The child: the member presses each card's Save with a box the cleaner would take words from, and
// the view the press lands on is loaded, read off the address leave() sent the reader to. It prints,
// for each card, what was flashed, the view loaded, the notice the page shows as a browser reads it,
// and what the box the words were typed into holds.
if ( $loss_child ) {
	if ( ! function_exists( 'check_admin_referer' ) ) {
		/** The nonce, good: the refusals pressed here come after it. */
		function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) { return 1; }
	}
	/** The claims, as far as the Offers card draws them: none this month. */
	class WPCPM_Sponsor_Claims { public static function stats( $record ) { return array( 'totals' => array( 'month' => 0 ) ); } }
	require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-codes.php';
	require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-offers.php';

	$D::ensure_page();
	$GLOBALS['uid'] = 5;
	$presses        = array(
		'profile'   => array( array( 'WPCPM_Sponsor_Profile', 'handle_save' ), array( 'wpcpm_sponsor' => $A, 'wpcpm_contact_person' => 'Ana <ana@example.test>' ), '#name="wpcpm_contact_person" value="([^"]*)"#' ),
		'offers'    => array( array( 'WPCPM_Sponsor_Offers', 'handle_save' ), array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => '0', 'wpcpm_title' => 'Kids <12 free, adults >18 pay', 'wpcpm_kind' => 'none' ), '#id="wpcpm-offer-new-title" name="wpcpm_title" value="([^"]*)"#' ),
	);
	$seen = array();
	foreach ( $presses as $card => $press ) {
		$_POST = $press[1];
		$GLOBALS['post'] = $press[1];
		$GLOBALS['redirected'] = '';
		try {
			call_user_func( $press[0] );
			$left = 'fell through';
		} catch ( WPCPM_Test_Redirect $e ) {
			$left = isset( $GLOBALS['flash'][ $D::FLASH ]['status'] ) ? $GLOBALS['flash'][ $D::FLASH ]['status'] : 'no flash';
		}
		$_POST = array();
		$GLOBALS['post'] = array();
		// The browser follows the address: its view is what the page draws next.
		$landed = array();
		parse_str( (string) parse_url( (string) $GLOBALS['redirected'], PHP_URL_QUERY ), $landed );
		$_GET   = isset( $landed[ $D::VIEW_ARG ] ) ? array( $D::VIEW_ARG => $landed[ $D::VIEW_ARG ] ) : array();
		$view   = $D::view();
		$page   = $D::render();
		$_GET   = array();
		$notice = preg_match( '#<p class="wpcpm-dashboard__message wpcpm-dashboard__message--([a-z]+) is-[a-z]+" role="[a-z]+" tabindex="-1" data-wpcpm-card-message>(.*?)</p>#s', $page, $found ) ? array( $found[1], html_entity_decode( strip_tags( $found[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : null;
		$box    = preg_match( $press[2], $page, $typed ) ? html_entity_decode( (string) preg_replace( '/^\n/', '', $typed[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : null;
		$seen[ $card ] = array( 'left' => $left, 'view' => $view, 'notice' => $notice, 'box' => $box );
	}
	// With the Offers card loaded too, every status of the page's map whose sentence is its detail.
	$seen['empty'] = array();
	foreach ( $D::messages() as $status => $pair ) {
		if ( '' === (string) $pair[1] ) {
			$seen['empty'][] = $status;
		}
	}
	sort( $seen['empty'] );
	echo json_encode( $seen ) . "\n";
	exit( 0 );
}

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

echo "\n=== The views and their addresses (1.123.78) ===\n";
// The page is one view at a time, named by `?wpcpm_view=`, as the Administrator Dashboard's; an address
// with no argument, every link that existed before the frame among them, is the Overview.
/**
 * The page at one view, asked for as a browser asks for it, the query put back after.
 *
 * @param string $view       A view's key; the Overview's sends no argument.
 * @param array  $attributes The block's attributes.
 * @return string
 */
function in_view( $view, array $attributes = array() ) {
	$kept = $_GET;
	unset( $_GET[ WPCPM_Sponsors_Dashboard::VIEW_ARG ] );
	if ( WPCPM_Sponsors_Dashboard::VIEW_OVERVIEW !== $view ) { $_GET[ WPCPM_Sponsors_Dashboard::VIEW_ARG ] = $view; }
	$html = WPCPM_Sponsors_Dashboard::render( $attributes );
	$_GET = $kept;
	return $html;
}
/**
 * The page's views, the Overview first, then the others in the menu's order.
 *
 * @return string[]
 */
function the_views() { return array_merge( array( WPCPM_Sponsors_Dashboard::VIEW_OVERVIEW ), array_keys( WPCPM_Sponsors_Dashboard::VIEW_CARDS ) ); }
/**
 * Every view as the current viewer reads it, by its key. Your sponsorship, asked for while the tiers
 * are not launched, is the Overview.
 *
 * @return array<string, string>
 */
function every_view() {
	$pages = array();
	foreach ( the_views() as $view ) { $pages[ $view ] = in_view( $view ); }
	return $pages;
}
/**
 * The view a page drew, by the class on the page's own box, or '' when it drew none.
 *
 * @param string $html The page.
 * @return string
 */
function view_drawn( $html ) { return preg_match( '#<div class="wpcpm-dashboard wpcpm-sponsor wpcpm-sponsor--([a-z]+)">#', (string) $html, $m ) ? $m[1] : ''; }
/**
 * The frame's main area, where the page's own box is, or '' for a page with no frame.
 *
 * @param string $html The page.
 * @return string
 */
function main_of( $html ) { $at = strpos( (string) $html, '<div class="wpcpm-frame__main">' ); return false === $at ? '' : substr( (string) $html, $at ); }
/**
 * Every head a page printed, one after the other.
 *
 * @param string $html The page.
 * @return string
 */
function heads_of( $html ) { preg_match_all( '#<header class="wpcpm-head[^"]*">.*?</header>#s', (string) $html, $m ); return implode( '', $m[0] ); }
/**
 * A view's head: its title and its one sentence, as printed; null when the page prints no head.
 *
 * @param string $html The page.
 * @return array|null `title` and `lede`.
 */
function view_head( $html ) {
	if ( 1 !== preg_match( '#<header class="wpcpm-head[^"]*">.*?<h2 class="wpcpm-head__title">(.*?)</h2>(?:<p class="wpcpm-head__lede">(.*?)</p>)?#s', (string) $html, $m ) ) { return null; }
	return array( 'title' => $m[1], 'lede' => $m[2] ?? '' );
}
/**
 * A view's head as the page prints it on a view other than the Overview, with no H1 above the title.
 *
 * @param string $title The view's title, as printed.
 * @param string $lede  Its sentence, as printed.
 * @return string
 */
function view_opening( $title, $lede ) { return '<header class="wpcpm-head"><div class="wpcpm-head__copy"><h2 class="wpcpm-head__title">' . $title . '</h2><p class="wpcpm-head__lede">' . $lede . '</p></div></header>'; }
/**
 * The cards a page drew, by key, in order.
 *
 * @param string $html The page.
 * @return string[]
 */
function view_cards( $html ) { preg_match_all( '#<details id="wpcpm-sponsor-([a-z]+)"#', main_of( $html ), $m ); return $m[1]; }
/**
 * Whether each of the needles is in the page, in the order given.
 *
 * @param string   $html    The page.
 * @param string[] $needles What to find.
 * @return bool
 */
function in_order( $html, array $needles ) {
	$at = array();
	foreach ( $needles as $needle ) { $at[] = strpos( (string) $html, $needle ); }
	$sorted = $at;
	sort( $sorted );
	return ! in_array( false, $at, true ) && $at === $sorted;
}

// The view asked for, or the Overview: an unknown view, the Overview's own key, and Your sponsorship
// while the tiers are not launched, since its cards are drawn only then.
$asked_views = array();
foreach ( array( 'nothing' => null, 'an unknown view' => 'bogus', 'the Overview\'s own key' => 'overview', 'Your company' => 'company', 'Your company in capitals' => 'Company', 'Offers' => 'offers', 'Students reached' => 'reached', 'Mentors to sponsor' => 'mentors', 'Agreement' => 'agreement', 'the Sponsor guide, a page of its own' => 'guide', 'Your sponsorship, the tiers off' => 'sponsorship' ) as $what => $asked ) {
	$_GET = null === $asked ? array() : array( $D::VIEW_ARG => $asked );
	$asked_views[ $what ] = $D::view();
}
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$_GET = array( $D::VIEW_ARG => 'sponsorship' );
$asked_views['Your sponsorship, the tiers launched'] = $D::view();
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$_GET = array();
ck( 'view() is the view asked for, or the Overview: for nothing, an unknown view, the Overview\'s own key and a page of its own, and for Your sponsorship until the tiers are launched', $asked_views, array(
	'nothing'                             => 'overview',
	'an unknown view'                     => 'overview',
	'the Overview\'s own key'             => 'overview',
	'Your company'                        => 'company',
	'Your company in capitals'            => 'company',
	'Offers'                              => 'offers',
	'Students reached'                    => 'reached',
	'Mentors to sponsor'                  => 'mentors',
	'Agreement'                           => 'agreement',
	'the Sponsor guide, a page of its own' => 'overview',
	'Your sponsorship, the tiers off'     => 'overview',
	'Your sponsorship, the tiers launched' => 'sponsorship',
) );
ck( 'the views are the menu\'s, each with the cards it draws by their anchor keys, and view_of() names the view of each, the Overview for a key no view lists', array(
	$D::VIEW_CARDS,
	array_map( array( $D, 'view_of' ), array( 'sponsorship', 'events', 'reports', 'badge', 'offers', 'posts', 'usage', 'mentors', 'looking', 'profile', 'people', 'agreement', 'no-such-card', '' ) ),
), array(
	array(
		'sponsorship' => array( 'sponsorship', 'events', 'reports', 'badge' ),
		'offers'      => array( 'offers', 'posts' ),
		'reached'     => array( 'usage' ),
		'mentors'     => array( 'mentors', 'looking' ),
		'company'     => array( 'profile', 'people' ),
		'agreement'   => array( 'agreement' ),
	),
	array( 'sponsorship', 'sponsorship', 'sponsorship', 'sponsorship', 'offers', 'offers', 'reached', 'mentors', 'mentors', 'company', 'company', 'agreement', 'overview', 'overview' ),
) );
// A view's address: the page's own for the Overview, `?wpcpm_view=` for the others, then the sponsor an
// Administrator is looking at, trimmed, when it is a key the store could hold; anything else adds nothing.
$page = get_permalink( $page_id );
ck( 'view_url() is the page for the Overview and the page with the view for the others, the sponsor\'s key after it when one is given, trimmed, and only a key the store could hold', array(
	$D::view_url(),
	$D::view_url( 'overview', $A ),
	$D::view_url( 'company' ),
	$D::view_url( 'company', $A ),
	$D::view_url( 'company', ' ' . $A . "\n" ),
	$D::view_url( 'company', 'spoSITEMADE000001' ),
	$D::view_url( 'company', 'not-a-key' ),
	$D::view_url( 'company', array( $A ) ),
	$D::view_url( 'bogus', $A ),
	$D::view_url( 'guide' ),
), array(
	$page,
	$page . '?wpcpm_sponsor_view=' . $A,
	$page . '?wpcpm_view=company',
	$page . '?wpcpm_view=company&wpcpm_sponsor_view=' . $A,
	$page . '?wpcpm_view=company&wpcpm_sponsor_view=' . $A,
	$page . '?wpcpm_view=company&wpcpm_sponsor_view=spoSITEMADE000001',
	$page . '?wpcpm_view=company',
	$page . '?wpcpm_view=company',
	$page . '?wpcpm_sponsor_view=' . $A,
	$page,
) );
// Every card's address, the real routine against the stand-in the other suites build their addresses
// with (bin/stubs/sponsor-card-url.php): for every card a view lists and for a key none lists, a
// member's link and an Administrator's, and an anchor inside the card in place of the card's own.
$card_urls = array();
$stub_urls = array();
foreach ( array_merge( array_merge( ...array_values( $D::VIEW_CARDS ) ), array( 'no-such-card' ) ) as $card ) {
	foreach ( array( '', $A, 'spoSITEMADE000001' ) as $record ) {
		$card_urls[ $card . ' ' . $record ] = $D::card_url( $card, $record );
		$stub_urls[ $card . ' ' . $record ] = wpcpm_test_sponsor_card_url( $page, $card, $record );
	}
}
$card_urls['offers, at an offer'] = $D::card_url( 'offers', $A, 'wpcpm-offer-12' );
$stub_urls['offers, at an offer'] = wpcpm_test_sponsor_card_url( $page, 'offers', $A, 'wpcpm-offer-12' );
ck( 'card_url() is the address of the view that draws the card, the sponsor\'s key after the view, and the card\'s anchor, or the anchor asked for inside it, exactly as the other suites\' stand-in builds it, for every card and for a key no view lists', array( count( $card_urls ), $card_urls ), array( 40, $stub_urls ) );
ck( 'so a card on Your company is at the view, and a key no view lists at the Overview, with its own anchor', array( $D::card_url( 'profile' ), $D::card_url( 'no-such-card', $A ) ), array( $page . '?wpcpm_view=company#wpcpm-sponsor-profile', $page . '?wpcpm_sponsor_view=' . $A . '#wpcpm-sponsor-no-such-card' ) );
// With no published page there is no address to give: both say nothing, as the stand-in does.
$GLOBALS['posts'][ $page_id ]->post_status = 'draft';
$no_page = array( $D::view_url( 'company', $A ), $D::card_url( 'profile', $A ), wpcpm_test_sponsor_card_url( $D::page_url(), 'profile', $A ) );
$GLOBALS['posts'][ $page_id ]->post_status = 'publish';
ck( 'with no published page view_url() and card_url() are empty, as the stand-in is', $no_page, array( '', '', '' ) );

echo "\n=== Who sees what ===\n";
$GLOBALS['uid'] = 0; $GLOBALS['logged_in'] = false;
$out = $D::render();
ck( 'logged out: one sentence and a sign-in link, no form', false !== strpos( $out, 'Sign in' ) && false !== strpos( $out, 'wp-login.php' ) && false === strpos( $out, '<form' ), true );
$GLOBALS['logged_in'] = true; $GLOBALS['uid'] = 9;
$out = $D::render();
ck( 'a stranger: the sponsors refusal, no form, no frame and no head', false !== strpos( $out, 'This page is for the program sponsors.' ) && false === strpos( $out, '<form' ) && false === strpos( $out, 'wpcpm-frame' ) && false === strpos( $out, 'wpcpm-head' ), true );
$GLOBALS['uid'] = 5; $GLOBALS['resources'] = array(); $GLOBALS['styles'] = array(); $GLOBALS['scripts'] = array();
$D::register();
$out = $D::render();
// The launch switch is off and A has no tier, no mentor and no card this suite draws on the Overview:
// the page is the frame, its menu, then the page's own box in the main area, the two-factor prompt, the
// Overview's head with the company's name, and the one quiet line, which closes the box.
ck( 'a member\'s Overview is drawn in the frame: the menu, then the page\'s box in the main area, the two-factor prompt, the head with the company\'s name, and "Nothing waits for you."', array(
	0 === strpos( $out, '<div class="wpcpm-frame wpcpm-frame--sponsor" data-wpcpm-frame>' ),
	in_order( $out, array( '<nav class="wpcpm-frame__menu"', '<div class="wpcpm-frame__main"><div class="wpcpm-dashboard wpcpm-sponsor wpcpm-sponsor--overview"><div class="wpcpm-two-factor-marker"></div>', '<header class="wpcpm-head wpcpm-head--logo">', '<h2 class="wpcpm-head__title">Mango Example</h2>', '<p class="wpcpm-overview__quiet wpcpm-sponsor__nothing">Nothing waits for you.</p>' ) ),
), array( true, true ) );
ck( 'the quiet line closes the page\'s box, and the box the main area, the body and the frame', substr( $out, -strlen( '</p></div></div></div></div>' ) ), '</p></div></div></div></div>' );
// Each card is on the view that holds it (1.123.78), so the Overview draws none of them.
ck( 'with the switch off the Overview draws no card: every card is on a view of its own', substr_count( $out, '<details id="wpcpm-sponsor-' ), 0 );
$views_off = every_view();
$all_off   = implode( '', $views_off );
// Offer more (1.123.22): its support choices were the sponsor application's before the levels, and its
// Tell us said what the Sponsorship card's Talk to your program contact says. No view draws it.
ck( 'Offer more is on no view: no card, no Tell us, no title and no support choices', array( strpos( $all_off, 'wpcpm-sponsor-interests' ), strpos( $all_off, 'Tell us' ), strpos( $all_off, 'Offer more' ), strpos( $all_off, 'name="wpcpm_support[]"' ) ), array( false, false, false, false ) );
// A card is the section the shell draws, so a view's first card follows its head and the box's opening
// directly, and its last card closes the box, which closes the page's own.
$first_card = static function ( $key ) {
	return '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-' . $key . '"';
};
$mentors_view = $views_off['mentors'];
$company_view = $views_off['company'];
ck( 'each view opens on its head, its title and its one sentence, its cards in a box of their own from the next tag on, the last card closing the box and the box the view, and no other sentence is printed', array(
	false !== strpos( $mentors_view, view_opening( 'Mentors to sponsor', 'The mentors your company sponsors, and the ones looking for a sponsor.' ) . '<div class="wpcpm-sponsor__cards">' . $first_card( 'mentors' ) ),
	false !== strpos( $company_view, view_opening( 'Your company', 'Your company&#039;s details and logo, and the people who can sign in for it.' ) . '<div class="wpcpm-sponsor__cards">' . $first_card( 'profile' ) ),
	substr( $mentors_view, -strlen( '</section></div></div></div></div></div>' ) ),
	substr( $company_view, -strlen( '</section></div></div></div></div></div>' ) ),
	array( substr_count( $mentors_view, 'wpcpm-head__lede' ), substr_count( $company_view, 'wpcpm-head__lede' ) ),
), array( true, true, '</section></div></div></div></div></div>', '</section></div></div></div></div></div>', array( 1, 1 ) ) );
// The views whose cards this suite does not load here (the Offers, Usage and Agreement cards are gaps
// until the section on what waits draws stand-ins) still say what they are, and say in one quiet line
// that there is nothing to show yet, rather than standing empty under their head.
$quiet_views = array();
foreach ( array( 'offers', 'reached', 'agreement' ) as $quiet_view ) {
	$quiet_views[ $quiet_view ] = array( view_head( $views_off[ $quiet_view ] ), false !== strpos( $views_off[ $quiet_view ], '</header><p class="wpcpm-overview__quiet">Nothing to show here yet.</p></div>' ), strpos( $views_off[ $quiet_view ], 'wpcpm-sponsor__cards' ) );
}
ck( 'a view whose cards print nothing keeps its title and sentence, and says in one quiet line under them that there is nothing to show here yet: Offers, Students reached and Agreement here', $quiet_views, array(
	'offers'    => array( array( 'title' => 'Offers', 'lede' => 'What your company gives the program&#039;s students and mentors.' ), true, false ),
	'reached'   => array( array( 'title' => 'Students reached', 'lede' => 'How many people claimed each of your offers, this month and in all. The program shows you numbers, never names.' ), true, false ),
	'agreement' => array( array( 'title' => 'Collaboration Agreement', 'lede' => 'The agreement between your company and the program, and the signed copy on file.' ), true, false ),
) );
// A card that prints nothing but white space prints no card, and a view whose cards all do so says it
// has nothing to show, rather than drawing an empty box over a line of white space: shown by a child of
// this suite, whose Your sponsorship cards are stand-ins that print only white space.
$blank_child  = array();
$blank_status = null;
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --blank-view-child 2>&1', $blank_child, $blank_status );
ck( 'a view whose cards print nothing but white space keeps its head and says it has nothing to show, and prints neither the white space nor a box of cards', array( $blank_status, $blank_child ), array( 0, array( 'view sponsorship', 'head printed', 'white space not printed', 'cards not printed', 'quiet right under the head, closing the view' ) ) );
ck( 'with the switch off no card of Your sponsorship is drawn on any view, and asking for the view draws the Overview: no head, no sentence', array( view_drawn( $views_off['sponsorship'] ), strpos( $all_off, 'id="wpcpm-sponsor-sponsorship"' ), strpos( $all_off, 'id="wpcpm-sponsor-events"' ), strpos( $all_off, 'id="wpcpm-sponsor-reports"' ), strpos( $all_off, 'id="wpcpm-sponsor-badge"' ), strpos( $all_off, '<h2 class="wpcpm-head__title">Your sponsorship</h2>' ), strpos( $all_off, 'Your tier' ) ), array( 'overview', false, false, false, false, false, false ) );
// 1.111.0: the site is the record, so no sentence names program records a sponsor's words are written to.
ck( 'no view\'s sentence names program records', strpos( $all_off, 'program records' ), false );
// The Resources block went with the help under the strip (1.123.78): its links are the menu's.
ck( 'the Resources block is drawn on no view: the page never asks the Handbook Assistant for it', array( $GLOBALS['resources'], strpos( $all_off, 'wpcpm-handbook__resources' ), strpos( $all_off, 'wpcpm-resources' ) ), array( array(), false, false ) );
ck( 'the Overview head shows the site\'s logo, never Airtable\'s URL', false !== strpos( heads_of( $out ), '<img class="wpcpm-sponsor__logo-image" src="https://example.test/uploads/501.png"' ) && false === strpos( $all_off, 'airtableusercontent' ), true );
// The head is the company's name, the thanks and New offer; the website, the contact person and the
// address are the profile card's (1.123.78), on Your company, as the Institution Dashboard keeps an
// address off its head.
$all_heads = heads_of( $all_off );
ck( 'the Overview head is the company\'s name, trimmed; the website and the contact person are the profile card\'s, on Your company, and no head prints them, the address or a contact line', array(
	false !== strpos( heads_of( $out ), '<h2 class="wpcpm-head__title">Mango Example</h2>' ),
	false !== strpos( $company_view, 'name="wpcpm_website" value="plugins.mango-example.test"' ),
	false !== strpos( $company_view, 'name="wpcpm_contact_person" value="Rep One"' ),
	array_values( array_filter( array( 'plugins.mango-example.test', 'Rep One', 'administrator@example.test', 'Contact:', 'mailto:', '@' ), static function ( $words ) use ( $all_heads ) { return false !== strpos( $all_heads, $words ); } ) ),
), array( true, true, true, array() ) );
// The type of product (1.123.22): the application no longer asks it and nothing public shows it, so no
// view prints it as a fact about the company, nor the profile card a box for it; A's row holds Hosting.
ck( 'the type of product the row holds is printed nowhere: not in a head, not on the profile card, on no view', array( WPCPM_Sponsors_Index::row( $A )['product_type'], strpos( $all_heads, 'Hosting' ), strpos( $all_off, 'Hosting' ), strpos( $all_off, 'Type of product' ) ), array( 'Hosting', false, false, false ) );
// The program contact is the menu's Write to the program (1.123.78): a mail link to the address, named for
// what it does; the contact's name and booking link, which the Resources block printed, are on no view.
ck( 'the menu\'s Help writes to the program contact, a mail link to the manager\'s address, and no view names the manager or prints their booking link', array( help_items( $out ), strpos( $all_off, 'Program Administrator' ), strpos( $all_off, 'calendly.example/administrator' ) ), array( array( 'Write to the program' => array( 'url' => 'mailto:administrator@example.test', 'marked' => false, 'current' => false, 'badge' => '', 'tone' => '' ) ), false, false ) );
// The same manager once the account is no program manager: still on A's row, and nobody's contact.
$GLOBALS['manage'] = array();
$demoted           = implode( '', every_view() );
$GLOBALS['manage'] = array( 1 );
// The manager's address is also A's contact address in this fixture, which the profile card holds, so the
// address is looked for as a link to write to.
ck( 'a manager who lost the program capability is named nowhere on the member\'s views: no Write to the program, no Help in the menu at all, no name, no link to their address, no booking link', array( help_items( $demoted ), strpos( $demoted, 'wpcpm-frame__group--help' ), strpos( $demoted, 'Write to the program' ), strpos( $demoted, 'Program Administrator' ), strpos( $demoted, 'mailto:' ), strpos( $demoted, 'calendly.example/administrator' ) ), array( array(), false, false, false, false, false ) );
ck( 'no switcher for a member, on any view', strpos( $all_off, 'wpcpm-dashboard__switcher' ), false );
ck( 'nor the script that narrows one', in_array( 'wpcpm-switcher', $GLOBALS['scripts'], true ), false );
ck( 'no heading unless the block asks for one', strpos( $all_off, 'wpcpm-dashboard__title' ), false );
ck( 'the block\'s optional heading is honoured, on a view as on the Overview', array( false !== strpos( $D::render( array( 'title' => 'Our sponsorship' ) ), '<h2 class="wpcpm-dashboard__title">Our sponsorship</h2>' ), false !== strpos( in_view( 'company', array( 'title' => 'Our sponsorship' ) ), '<h2 class="wpcpm-dashboard__title">Our sponsorship</h2>' ) ), array( true, true ) );
$people_card = substr( $company_view, strpos( $company_view, 'id="wpcpm-sponsor-people"' ) ); $people_card = substr( $people_card, 0, strpos( $people_card, '</section>' ) );
ck( 'the people card, on Your company, lists the accounts and offers no form to a member', false !== strpos( $people_card, 'Rep One' ) && 0 === substr_count( $people_card, '<form' ), true );
// The row a folded People card shows (the design the owner approved on 27 September 2026): how many
// accounts act for the sponsor, and no action, since accounts are attached by the program.
ck( 'the People row: folded, how many accounts act for the sponsor, and no action', array( false !== strpos( $company_view, '<details id="wpcpm-sponsor-people" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">People <span class="wpcpm-group__count">1</span></h3><span class="wpcpm-sponsor__state">1 account</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>' ), strpos( $people_card, 'wpcpm-sponsor__row-action' ) ), array( true, false ) );
// A folded card's action follows its fold, and names for a screen reader what it does, so a link
// read out of its row says more than "Edit": on this member's views, the profile's alone, Offer more's
// Tell us having gone with its card (1.123.22).
ck( 'the member\'s views offer the profile\'s action alone, on Your company, after its fold and naming what it does for a screen reader', array(
	substr_count( $all_off, 'wpcpm-sponsor__row-action' ),
	false !== strpos( $company_view, '<p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-profile">Edit<span class="screen-reader-text"> the company profile and logo</span></a></p></section>' ),
), array( 1, true ) );
ck( 'the stylesheet is registered from assets/css/sponsor.css and switched on', isset( $GLOBALS['styles'][ $D::STYLE ] ) && false !== strpos( $GLOBALS['styles'][ $D::STYLE ]['src'], 'assets/css/sponsor.css' ) && ! empty( $GLOBALS['styles'][ $D::STYLE ]['on'] ), true );
// The sheet still depends on the Administrator Dashboard's, which dresses the Overview's quiet lines
// (.wpcpm-overview__quiet) as on that page, and registers it first itself, as it does the mentor sheet:
// never a sheet that waits on a handle nobody registered.
ck( 'on the mentor stylesheet and the Administrator Dashboard\'s, whose quiet line the Overview\'s "Nothing waits for you." wears, each registered by the page itself', array( $GLOBALS['styles'][ $D::STYLE ]['deps'], isset( $GLOBALS['styles'][ WPCPM_Mentors_Dashboard::STYLE ] ), isset( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ] ), isset( sheet_rules( 'administrator.css' )['|.wpcpm-overview__quiet'] ) ), array( array( WPCPM_Mentors_Dashboard::STYLE, WPCPM_Administrators_Dashboard::STYLE ), true, true, true ) );
// The frame's own sheet and script, which dress the menu, the head and the status tags and open the menu
// as a drawer on a phone, come with the frame on every view.
ck( 'and the frame\'s own sheet and script are switched on with it', array( ! empty( $GLOBALS['styles'][ WPCPM_Dashboard_Frame::STYLE ]['on'] ), in_array( WPCPM_Dashboard_Frame::SCRIPT, $GLOBALS['scripts'], true ) ), array( true, true ) );
ck( 'the double-submit guard is armed', in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ), true );
// The Badge card's Copy button (1.113.0): enqueued with the guard, before any card is drawn,
// so the script is there whichever cards the view holds.
ck( 'and so is the Copy button\'s script, registered from assets/js/copy.js', array( in_array( 'wpcpm-copy', $GLOBALS['scripts'], true ), isset( $GLOBALS['script_src']['wpcpm-copy'] ) ? $GLOBALS['script_src']['wpcpm-copy'] : '' ), array( true, WPCPM_PLUGIN_URL . 'assets/js/copy.js' ) );
// The opener: a link that lands on a card's anchor (a row's link, a link in a mail) opens the card,
// which a browser following a link to a closed disclosure does not do. The dashboards' one opener, the
// Administrator Dashboard's too, enqueued with the other two, before any card is drawn, through the
// shell both pages share (its registration is pinned in bin/test-dashboard-card.php).
ck( 'and so is the dashboards\' opener, through the shell both pages share', in_array( 'wpcpm-dashboard-open', $GLOBALS['scripts'], true ), true );
// The product name "Student Report Card" is allowed wherever a card names the product, and the view
// "Students reached" names the number of people an offer reached, not one of them; any other "Student"
// would be a person or a role leaking onto the sponsor's page.
ck( 'no student is named on any view', preg_match( '/Student(?! Report Card|s reached)/', $all_off ), 0 );
// The same page with the launch switch on and A a Champion: Your sponsorship is a view, holding the
// sponsorship card, Events and the badge, a Champion's too. Drawn after every check above reads the
// views and the resources list, and put back as it was for the sections below.
$GLOBALS['settings']['sponsor_tiers_live'] = true;
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion' ) );
$sponsorship_live = in_view( 'sponsorship' );
$views_live       = every_view();
ck( 'with the switch on, Your sponsorship is drawn: its head and its sentence, then the sponsorship card, Events and the badge, in that order, the first right after the head', array( view_drawn( $sponsorship_live ), view_head( $sponsorship_live ), view_cards( $sponsorship_live ), false !== strpos( $sponsorship_live, view_opening( 'Your sponsorship', 'Your tier, your events and your badge.' ) . '<div class="wpcpm-sponsor__cards">' . $first_card( 'sponsorship' ) ) ), array( 'sponsorship', array( 'title' => 'Your sponsorship', 'lede' => 'Your tier, your events and your badge.' ), array( 'sponsorship', 'events', 'badge' ), true ) );
ck( 'and the other views keep their cards, none of Your sponsorship\'s among them, and Offer more on none', array( view_cards( $views_live['overview'] ), view_cards( $views_live['mentors'] ), view_cards( $views_live['company'] ), strpos( implode( '', $views_live ), 'wpcpm-sponsor-interests' ) ), array( array(), array( 'mentors', 'looking' ), array( 'profile', 'people' ), false ) );
// The Reports card (1.115.0): one final report of A's, stored as the store stores one, and the card
// takes its place after Events and before the badge while the switch is on. The same report with
// the switch off draws nothing on any view, and the pages read as they did before the report existed.
$final_report = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Report::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Mango Example, October 2025 to September 2026' ) );
update_post_meta( $final_report, WPCPM_Sponsor_Report::META_SPONSOR, $A );
update_post_meta( $final_report, WPCPM_Sponsor_Report::META_UNTIL, '2026-09-30' );
update_post_meta( $final_report, WPCPM_Sponsor_Report::META_STATE, WPCPM_Sponsor_Report::STATE_FINAL );
$out_reports = in_view( 'sponsorship' );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$out_reports_off = implode( '', every_view() );
ck( 'the Reports card follows Events and comes before the badge on Your sponsorship while the switch is on, and is drawn on no view while it is off', array(
	view_cards( $out_reports ),
	false !== strpos( $out_reports, 'Mango Example, October 2025 to September 2026' ),
	strpos( $out_reports_off, 'id="wpcpm-sponsor-reports"' ),
	strpos( $out_reports_off, 'October 2025 to September 2026' ),
), array( array( 'sponsorship', 'events', 'reports', 'badge' ), true, false, false ) );
// The view's sentence names exactly the cards the view draws for this sponsor, in the view's order
// (1.123.22): every line said "Your tier, your events, your reports and your badge." though a Friend
// has none of the three and a Supporter or a Backer no events. Each card here is drawn by its own
// class, so the line and the cards are read off the same view: the Events card while the tier card's
// own test draws it (here, with no event store, while the tier includes events), the Reports card
// while a final report exists, and the badge while the sponsor is Approved at a tier that includes
// one. The report is A's, written above, final where a case has one and a draft, the program's to
// finish, where it has none: this suite's get_posts() does not read a query's sponsor clause.
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$leads_seen = array();
foreach ( array(
	'a Friend, no report'                    => array( 'mentor', 'Approved', false ),
	'a Supporter, no report'                 => array( 'supporter', 'Approved', false ),
	'a Friend with a report'                 => array( 'mentor', 'Approved', true ),
	'a Supporter with a report'              => array( 'supporter', 'Approved', true ),
	'a paused Champion, no report'           => array( 'champion', 'Paused', false ),
	'a Champion, no report'                  => array( 'champion', 'Approved', false ),
	'a paused Champion with a report'        => array( 'champion', 'Paused', true ),
	'a Champion with a report'               => array( 'champion', 'Approved', true ),
) as $who => $setup ) {
	WPCPM_Sponsors_Index::patch( $A, array( 'tier' => $setup[0], 'status' => $setup[1] ) );
	update_post_meta( $final_report, WPCPM_Sponsor_Report::META_STATE, $setup[2] ? WPCPM_Sponsor_Report::STATE_FINAL : WPCPM_Sponsor_Report::STATE_DRAFT );
	$lead_view          = in_view( 'sponsorship' );
	$leads_seen[ $who ] = array( 'lead' => view_head( $lead_view )['lede'] ?? null, 'cards' => view_cards( $lead_view ) );
}
WPCPM_Sponsors_Index::patch( $A, array( 'status' => 'Approved' ) );
ck( 'Your sponsorship\'s sentence names exactly the cards the view draws, in the view\'s order, for each of the eight ways the tier\'s events, the reports and the badge can fall', $leads_seen, array(
	'a Friend, no report'             => array( 'lead' => 'Your tier and what it includes.', 'cards' => array( 'sponsorship' ) ),
	'a Supporter, no report'          => array( 'lead' => 'Your tier and your badge.', 'cards' => array( 'sponsorship', 'badge' ) ),
	'a Friend with a report'          => array( 'lead' => 'Your tier and your reports.', 'cards' => array( 'sponsorship', 'reports' ) ),
	'a Supporter with a report'       => array( 'lead' => 'Your tier, your reports and your badge.', 'cards' => array( 'sponsorship', 'reports', 'badge' ) ),
	'a paused Champion, no report'    => array( 'lead' => 'Your tier and your events.', 'cards' => array( 'sponsorship', 'events' ) ),
	'a Champion, no report'           => array( 'lead' => 'Your tier, your events and your badge.', 'cards' => array( 'sponsorship', 'events', 'badge' ) ),
	'a paused Champion with a report' => array( 'lead' => 'Your tier, your events and your reports.', 'cards' => array( 'sponsorship', 'events', 'reports' ) ),
	'a Champion with a report'        => array( 'lead' => 'Your tier, your events, your reports and your badge.', 'cards' => array( 'sponsorship', 'events', 'reports', 'badge' ) ),
) );
// Each line is one whole sentence for the translator, never put together from pieces.
$lead_strings = array();
foreach ( array( 'Your tier and what it includes.', 'Your tier and your badge.', 'Your tier and your reports.', 'Your tier, your reports and your badge.', 'Your tier and your events.', 'Your tier, your events and your badge.', 'Your tier, your events and your reports.', 'Your tier, your events, your reports and your badge.' ) as $lead_sentence ) {
	$lead_strings[] = substr_count( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-dashboard.php' ), "__( '" . $lead_sentence . "', 'wpcredits-program-manager' )" );
}
ck( 'and each of the eight lines is one whole translatable sentence in the source', $lead_strings, array( 1, 1, 1, 1, 1, 1, 1, 1 ) );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
unset( $GLOBALS['posts'][ $final_report ], $GLOBALS['pmeta'][ $final_report ] );
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => '' ) );
// Something else may register the Copy script first, as the Sponsors screen's handbook box can on a
// site that loads both: the dashboard enqueues that registration and never makes its own over it.
$GLOBALS['script_src']['wpcpm-copy'] = 'https://example.test/registered-first/copy.js';
$GLOBALS['script_registrations']     = array();
$GLOBALS['scripts']                  = array();
$D::render();
ck( 'a Copy script something else registered first is enqueued as it is, and not registered again', array( in_array( 'wpcpm-copy', $GLOBALS['scripts'], true ), $GLOBALS['script_src']['wpcpm-copy'], in_array( 'wpcpm-copy', $GLOBALS['script_registrations'], true ) ), array( true, 'https://example.test/registered-first/copy.js', false ) );
$GLOBALS['script_src']['wpcpm-copy'] = WPCPM_PLUGIN_URL . 'assets/js/copy.js';

echo "\n=== A manager ===\n";
$GLOBALS['uid'] = 1; $GLOBALS['get'] = array( WPCPM_Sponsor_Roster::ARG_VIEW => $B ); $GLOBALS['scripts'] = array();
$out = $D::render();
ck( 'the switcher lists both sponsors and the manager is viewing B', false !== strpos( $out, 'name="' . WPCPM_Sponsor_Roster::ARG_VIEW . '"' ) && false !== strpos( $out, 'value="' . $B . '" selected' ), true );
ck( 'with the one field that finds and picks a sponsor, and the page loads the script that makes the field work', array( false !== strpos( $out, 'role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="wpcpm-sponsor-switcher-list" autocomplete="off" spellcheck="false" placeholder="Find a sponsor" />' ), in_array( 'wpcpm-switcher', $GLOBALS['scripts'], true ) ), array( true, true ) );
ck( 'the note names the Administrators', false !== strpos( $out, '<span class="wpcpm-dashboard__switcher-note">Only Administrators see this control.</span>' ), true );
// A GET form replaces its address's query, so the view a manager reads travels in the switcher as a
// hidden field (1.123.78), and a manager moving to another sponsor stays on the same view.
$b_company = in_view( 'company' );
ck( 'the switcher keeps the view a manager reads as a hidden field, and on the Overview carries none', array( false !== strpos( $b_company, '<form class="wpcpm-dashboard__switcher" method="get"><input type="hidden" name="wpcpm_view" value="company" /><div class="wpcpm-dashboard__switcher-fields">' ), false !== strpos( $out, '<form class="wpcpm-dashboard__switcher" method="get"><div class="wpcpm-dashboard__switcher-fields">' ), strpos( $out, 'name="wpcpm_view"' ) ), array( true, true, false ) );
// A third sponsor, added last as the index adds a new row, whose name opens on an accent: index
// order leaves it last, and so does an order read byte by byte. A to Z puts it first. The index
// holds each sponsor as a post of its own, so the third is inserted alone and its post taken out
// after.
$C = 'recSPONSOR0000003';
WPCPM_Sponsors_Index::insert( array( 'record_id' => $C, 'name' => 'Ápex Example', 'status' => 'Approved' ) );
$listed = $D::render();
preg_match_all( '#<option value="(rec[A-Za-z0-9]+)"#', $listed, $values );
ck( 'the switcher lists the sponsors A to Z, not in the index\'s order', $values[1], array( $C, $A, $B ) );
$c_post = WPCPM_Sponsors_Index::post_id_of( $C );
unset( $GLOBALS['posts'][ $c_post ], $GLOBALS['pmeta'][ $c_post ] );
WPCPM_Sponsors_Index::forget();
ck( 'and the third sponsor is gone again, so the checks after it see the two', array( $c_post > 0, array_keys( WPCPM_Sponsors_Index::rows() ) ), array( true, array( $A, $B ) ) );
ck( 'B has no logo: initials stand in', false !== strpos( heads_of( $out ), '<span class="wpcpm-sponsor__initials" aria-hidden="true">W</span>' ), true );
ck( 'B has no manager: no Write to the program, and with no guide no Help in the menu at all', array( help_items( $out ), strpos( $out, 'wpcpm-frame__group--help' ), strpos( $out, 'mailto:' ) ), array( array(), false, false ) );
// The record's status is the Administrators' to read (1.123.78): it follows the thanks in the head, and a
// member's head never prints it.
ck( 'a manager reads the sponsor\'s status in the Overview head, after the thanks, and a member never does', array( 1 === preg_match( '#<p class="wpcpm-head__lede">[^<]*</p><p class="wpcpm-head__facts">Status: Approved</p>#', heads_of( $out ) ), strpos( $all_off, 'Status:' ), strpos( $all_off, 'wpcpm-head__facts' ) ), array( true, false, false ) );
ck( 'the people card points a manager at the accounts of the sponsor being viewed, on the Sponsors screen\'s Accounts tab, where they are attached and removed', false !== strpos( substr( $b_company, strpos( $b_company, 'id="wpcpm-sponsor-people"' ) ), '<p class="wpcpm-student__note"><a href="https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=accounts&wpcpm_sponsor=' . $B . '">Attach or remove accounts on the Sponsors screen.</a></p>' ), true );
ck( 'and a sponsor no account acts for reads none on the People row', false !== strpos( $b_company, '<h3 class="wpcpm-group__title">People <span class="wpcpm-group__count">0</span></h3><span class="wpcpm-sponsor__state">0 accounts</span>' ), true );
$GLOBALS['get'] = array();

echo "\n=== Messages and leave() ===\n";
$GLOBALS['uid'] = 5;
// A card's outcome is said inside the card, on the view that draws it, which is where leave() sends the
// reader (1.123.78): the profile's on Your company.
try { $D::leave( 'profile-saved', 'profile', $A ); } catch ( WPCPM_Test_Redirect $e ) {}
$out = in_view( $D::view_of( 'profile' ) );
ck( 'a flash prints its sentence with the success tone, on the view its card is on', array( view_drawn( $out ), false !== strpos( $out, 'wpcpm-dashboard__message--success' ) && false !== strpos( $out, 'Your profile was saved' ) ), array( 'company', true ) );
ck( 'a card\'s outcome is printed at the top of the card it names, drawn open, as a status, and not at the top of the view',
	array(
		substr_count( $out, 'data-wpcpm-card-message' ),
		strpos( $out, 'data-wpcpm-card-message' ) > strpos( $out, 'id="wpcpm-sponsor-profile"' ),
		false !== strpos( $out, 'role="status" tabindex="-1" data-wpcpm-card-message>Your profile was saved' ),
		false === strpos( substr( $out, 0, strpos( $out, 'id="wpcpm-sponsor-profile"' ) ), 'data-wpcpm-card-message' ),
	),
	array( 1, true, true, true ) );
ck( 'and opens the card it names', false !== strpos( $out, 'id="wpcpm-sponsor-profile" class="wpcpm-group wpcpm-group__disclosure" open' ), true );
try { $D::leave( 'profile-saved', 'no-such-card', $A ); } catch ( WPCPM_Test_Redirect $e ) {}
$said_top = $D::render();
ck( 'a status no view\'s card draws is said at the top of the Overview, before its head',
	array( substr_count( $said_top, 'data-wpcpm-card-message' ), strpos( $said_top, 'data-wpcpm-card-message' ) < strpos( $said_top, '<header class="wpcpm-head' ) ),
	array( 1, true ) );
// A view that does not draw the card the outcome names says it at the top all the same (1.123.58), so an
// outcome read on another view, an older link's or a reload's, is never lost.
try { $D::leave( 'profile-saved', 'profile', $A ); } catch ( WPCPM_Test_Redirect $e ) {}
$said_elsewhere = in_view( 'mentors' );
ck( 'and so is a card\'s outcome read on a view that does not draw the card: once, before the view\'s head, and the card is not drawn', array( substr_count( $said_elsewhere, 'data-wpcpm-card-message' ), strpos( $said_elsewhere, 'data-wpcpm-card-message' ) < strpos( $said_elsewhere, '<header class="wpcpm-head' ), false !== strpos( $said_elsewhere, '>Your profile was saved' ), strpos( $said_elsewhere, 'id="wpcpm-sponsor-profile"' ) ), array( 1, true, true, false ) );
try { $D::leave( 'refused', 'profile', $A ); } catch ( WPCPM_Test_Redirect $e ) {}
$refused_in = in_view( 'company' );
ck( 'a refusal is an alert in its card', array( substr_count( $refused_in, 'role="alert"' ), strpos( $refused_in, 'role="alert"' ) > strpos( $refused_in, 'id="wpcpm-sponsor-profile"' ) ), array( 1, true ) );
$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => 'refused', 'card' => '', 'detail' => 'Line 3 repeats line 1.' );
ck( 'a flash may carry a detail, which follows the status\'s sentence in the same message', false !== strpos( $D::render(), '>That is not something your account can do here. Line 3 repeats line 1.</p>' ), true );
// A refusal for words the cleaner would take has no sentence of its own: the card's whole sentence,
// which names the box, is the detail, and the notice prints it alone. With neither a sentence nor
// a detail, nothing is printed.
$loss_said = WPCPM_Typed_Text::loss_message( WPCPM_Sponsor_Profile::labels()['contact_person'] );
try { $D::leave( 'profile-loss', 'profile', $A, $loss_said ); } catch ( WPCPM_Test_Redirect $e ) {}
$loss_page   = in_view( 'company' );
$loss_notice = preg_match( '#<p class="wpcpm-dashboard__message wpcpm-dashboard__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>(.*?)</p>#s', $loss_page, $loss_found ) ? $loss_found[1] : null;
ck( 'a status whose sentence is its detail prints the detail alone, one notice in the error tone, read by a browser as the card\'s sentence naming the box', array( substr_count( $loss_page, '<p class="wpcpm-dashboard__message ' ), null === $loss_notice ? null : false === strpos( $loss_notice, '<span' ), null === $loss_notice ? null : html_entity_decode( strip_tags( $loss_notice ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ), array( 1, true, $loss_said ) );
$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => 'profile-loss', 'card' => 'profile', 'detail' => '' );
ck( 'and with neither a sentence nor a detail, nothing is printed', strpos( in_view( 'company' ), 'wpcpm-dashboard__message' ), false );
// A detail is text, escaped for the page, after a sentence and alone alike: a flash left by an
// older release or edited by hand can hold markup.
$detail_markup = array();
foreach ( array( 'refused', 'profile-loss' ) as $detail_status ) {
	$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => $detail_status, 'card' => '', 'detail' => 'Line <b>3</b> & "4"' );
	$detail_markup[]               = false !== strpos( $D::render(), 'Line &lt;b&gt;3&lt;/b&gt; &amp; &quot;4&quot;</p>' );
}
ck( 'a detail is printed escaped, after a sentence and alone alike', $detail_markup, array( true, true ) );
// Every status of the page's cards that a notice could print in silence: a sentence of nothing, whose
// detail is the message, or one that names values the flash carries beside it. Each of the first
// prints its detail; each of the second prints with its values (the planner's presses always pass
// them: bin/test-sponsor-event-planner.php).
$loss_silent = array();
foreach ( $D::messages() as $loss_status => $loss_pair ) {
	if ( '' === (string) $loss_pair[1] ) {
		$loss_silent['empty'][] = $loss_status;
	} elseif ( preg_match( '/%(?:\d+\$)?[sd]/', (string) $loss_pair[1] ) ) {
		$loss_silent['named'][] = $loss_status;
	}
}
ksort( $loss_silent );
$loss_printed = array();
foreach ( $loss_silent as $loss_kind => $loss_statuses ) {
	foreach ( $loss_statuses as $loss_status ) {
		$GLOBALS['flash'][ $D::FLASH ] = 'empty' === $loss_kind ? array( 'status' => $loss_status, 'card' => '', 'detail' => 'The whole sentence.' ) : array( 'status' => $loss_status, 'card' => '', 'detail' => '', 'args' => array( 'January 31, 2027', 'Europe / Warsaw' ) );
		$loss_printed[ $loss_status ] = 1 === substr_count( $D::render(), '<p class="wpcpm-dashboard__message ' );
	}
}
ck( 'the page\'s cards hold two statuses whose sentence is their detail, the profile\'s refusals for lost words and for a value, and four whose sentence names values, a start outside the term, the hour the planner\'s ceiling lets go and the day an interest was filed; each prints a notice', array( $loss_silent, $loss_printed ), array( array( 'empty' => array( 'profile-loss', 'profile-rejected' ), 'named' => array( 'sevent-past-term', 'sevent-before-term', 'sevent-ceiling-cancel', 'sevent-ceiling', 'mentor-interest-repeat' ) ), array( 'profile-loss' => true, 'profile-rejected' => true, 'sevent-past-term' => true, 'sevent-before-term' => true, 'sevent-ceiling-cancel' => true, 'sevent-ceiling' => true, 'mentor-interest-repeat' => true ) ) );
$loss_child_out    = array();
$loss_child_status = null;
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --loss-child 2>&1', $loss_child_out, $loss_child_status );
$loss_seen = json_decode( (string) end( $loss_child_out ), true );
ck(
	'as the member, a refused save of the profile and of the Offers card each lands on the view that draws its card, Your company and Offers, which shows the card\'s sentence naming the box, in the error tone, and the box holding what was typed; with the Offers card loaded, its refusal is the second whose sentence is its detail',
	array( $loss_child_status, $loss_seen ),
	array(
		0,
		array(
			'profile'   => array( 'left' => 'profile-loss', 'view' => 'company', 'notice' => array( 'error', WPCPM_Typed_Text::loss_message( 'Contact person' ) ), 'box' => 'Ana <ana@example.test>' ),
			'offers'    => array( 'left' => 'offer-loss', 'view' => 'offers', 'notice' => array( 'error', WPCPM_Typed_Text::loss_message( 'Title' ) ), 'box' => 'Kids <12 free, adults >18 pay' ),
			'empty'     => array( 'offer-loss', 'profile-loss', 'profile-rejected' ),
		),
	)
);
unset( $GLOBALS['flash'][ $D::FLASH ] );
try { $D::leave( 'codes-refused', 'offers', $A, str_repeat( 'x', 400 ) ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'leave() stores the detail trimmed to three hundred characters', mb_strlen( $GLOBALS['flash'][ $D::FLASH ]['detail'] ), 300 );
// A sentence that names a day stays one sentence: the values it names travel beside the status, and
// the page the flash lands on puts them where the sentence names them.
try { $D::leave( 'sevent-past-term', 'events', $A, '', array( 'January 31, 2027', 'Europe / Warsaw' ) ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'leave() keeps the values a sentence names beside the status, and only then', $GLOBALS['flash'][ $D::FLASH ], array( 'status' => 'sevent-past-term', 'card' => 'events', 'detail' => '', 'args' => array( 'January 31, 2027', 'Europe / Warsaw' ) ) );
$named = $D::render();
ck( 'a flash that carries them prints the sentence with each value where it names it, escaped', false !== strpos( $named, '<p class="wpcpm-dashboard__message wpcpm-dashboard__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>Choose a start on or before January 31, 2027, the last day of your sponsorship term on the site&#039;s clock (Europe / Warsaw).</p>' ), true );
$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => 'sevent-past-term', 'card' => 'events', 'detail' => '' );
$unnamed = $D::render();
ck( 'and one that does not, left by an older release or edited by hand, prints no sentence rather than a placeholder', array( strpos( $unnamed, 'wpcpm-dashboard__message' ), strpos( $unnamed, '%1$s' ) ), array( false, false ) );
$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => 'sevent-past-term', 'card' => 'events', 'detail' => 'A detail beside it.' );
$unnamed = $D::render();
ck( 'nor does one that carries a detail but not the values: no notice at all, the detail not printed alone', array( strpos( $unnamed, 'wpcpm-dashboard__message' ), strpos( $unnamed, 'A detail beside it.' ) ), array( false, false ) );
// leave() always flashes before it redirects, so the call above left one sitting on the channel
// again; drained here rather than left for the "taken, so it shows once" check below to trip
// over; a real request would have consumed it on the next page load, not this one.
unset( $GLOBALS['flash'][ $D::FLASH ] );
// Every card is a section with the disclosure inside it, the way the Institution Dashboard wraps its
// semester report: the section carries the card rhythm (the rule above, the room), the disclosure only
// folds (1.93.2). A card class left on the disclosure would draw that rhythm on the wrong box. Read off
// the views that draw this page's own card and the profile, and the mentors' two.
$cards_seen = $out . in_view( 'mentors' );
ck( 'every card is a section wrapping its disclosure', preg_match_all( '#<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-#', $cards_seen ), substr_count( $cards_seen, '<details id="wpcpm-sponsor-' ) );
ck( 'and at least the profile, the people and the mentors\' two cards render that way', preg_match_all( '#<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-#', $cards_seen ) >= 4, true );
ck( 'the disclosure itself no longer carries the card class', strpos( $cards_seen, 'wpcpm-group__disclosure wpcpm-sponsor__card"' ), false );
// A card's action follows its fold inside its section, never inside the summary (27 September
// 2026): so a section closes right after its disclosure, or right after that one action.
ck( 'each section closes right after its disclosure, or after the one action that follows its fold', preg_match_all( '#</details>(?:<p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="[^"]*">[^<]*(?:<span class="screen-reader-text">[^<]*</span>)?</a></p>)?</section>#', $cards_seen ), substr_count( $cards_seen, '<section class="wpcpm-sponsor__card">' ) );
// The action is printed whatever opened its card, and the stylesheets hide it while the card is open:
// the profile the flash opened still carries its Edit, which shows once the sponsor folds the card.
ck( 'and on Your company, whose profile a flash opened, the action is still the profile\'s, after its fold: the stylesheets hide it while the card is open', array( substr_count( $out, 'wpcpm-sponsor__row-action' ), false !== strpos( $out, '<details id="wpcpm-sponsor-profile" class="wpcpm-group wpcpm-group__disclosure" open>' ), false !== strpos( $out, 'href="#wpcpm-sponsor-profile">Edit<span class="screen-reader-text"> the company profile and logo</span></a></p></section>' ) ), array( 1, true, true ) );
ck( 'taken, so it shows once', isset( $GLOBALS['flash'][ $D::FLASH ] ), false );
$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => 'refused', 'card' => '' );
ck( 'a refusal prints with the error tone', false !== strpos( $D::render(), 'wpcpm-dashboard__message--error' ), true );
try { $D::leave( 'profile-saved', 'profile', $A ); } catch ( WPCPM_Test_Redirect $e ) {}
// leave() always writes a 'detail' key too, empty or not: this call passes none, so it is ''.
ck( 'leave() flashes the status and the card', $GLOBALS['flash'][ $D::FLASH ], array( 'status' => 'profile-saved', 'card' => 'profile', 'detail' => '' ) );
// The way back is the view that draws the card, at the card (1.123.78), as card_url() writes it.
ck( 'and sends a member to the view that draws the card, at the card, without the switcher argument', $GLOBALS['redirected'], get_permalink( $page_id ) . '?wpcpm_view=company#wpcpm-sponsor-profile' );
$GLOBALS['uid'] = 1;
try { $D::leave( 'profile-saved', 'profile', $A ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'a manager is sent back through the switcher', false !== strpos( $GLOBALS['redirected'], WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A ) && false !== strpos( $GLOBALS['redirected'], '#wpcpm-sponsor-profile' ), true );
ck( 'and the whole address is the view, the sponsor and the card, as card_url() writes it', array( $GLOBALS['redirected'], $GLOBALS['redirected'] === $D::card_url( 'profile', $A ) ), array( get_permalink( $page_id ) . '?wpcpm_view=company&' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A . '#wpcpm-sponsor-profile', true ) );
// What the argument may be is the shape of a sponsor key, as the institution side's guard asks for a
// record ID, and not merely "not empty": a value that is no key adds nothing to the address.
foreach ( array( 'nothing at all' => '', 'only spaces' => '   ', 'a word' => 'x', 'a short ID' => 'recSPONSOR', 'markup' => '<b>recSPONSOR0000001</b>' ) as $what => $record ) {
	try { $D::leave( 'profile-saved', 'profile', $record ); } catch ( WPCPM_Test_Redirect $e ) {}
	ck( 'a manager sent back with ' . $what . ' is sent to the view and the card, with no argument', $GLOBALS['redirected'], get_permalink( $page_id ) . '?wpcpm_view=company#wpcpm-sponsor-profile' );
}
try { $D::leave( 'profile-saved', 'profile', ' ' . $A . ' ' ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'and a record ID with spaces round it is sent trimmed', $GLOBALS['redirected'], get_permalink( $page_id ) . '?wpcpm_view=company&' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A . '#wpcpm-sponsor-profile' );
// A sponsor the site made itself is keyed by mint_key(), `spo` and fourteen characters, which is no
// record ID. It goes back through the switcher as a migrated sponsor does, or a manager's press for
// it would come back on another sponsor: their own, or the first with a member.
$minted = 'spoSITEMADE000001';
ck( 'a minted site key is a key the store could hold, and no record ID', array( WPCPM_Sponsors_Index::is_key( $minted ), WPCPM_Mentors_Sync::is_record_id( $minted ) ), array( true, false ) );
try { $D::leave( 'profile-saved', 'profile', $minted ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'a manager pressing for a sponsor the site made is sent back through the switcher to that sponsor', $GLOBALS['redirected'], get_permalink( $page_id ) . '?wpcpm_view=company&' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $minted . '#wpcpm-sponsor-profile' );
try { $D::leave( 'profile-saved', 'profile', ' ' . $minted . "\n" ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'and a site key with white space round it is sent trimmed', $GLOBALS['redirected'], get_permalink( $page_id ) . '?wpcpm_view=company&' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $minted . '#wpcpm-sponsor-profile' );
foreach ( array( 'a short site key' => 'spoSITEMADE', 'a site key one too long' => 'spoSITEMADE0000001', 'another prefix' => 'sopSITEMADE000001', 'a capital prefix' => 'SPOSITEMADE000001', 'no scalar' => array( $minted ) ) as $what => $record ) {
	try { $D::leave( 'profile-saved', 'profile', $record ); } catch ( WPCPM_Test_Redirect $e ) {}
	ck( 'a manager sent back with ' . $what . ' is sent to the view and the card, with no argument', $GLOBALS['redirected'], get_permalink( $page_id ) . '?wpcpm_view=company#wpcpm-sponsor-profile' );
}
// Each card goes back to its own view: the Offers card to Offers, the Events card to Your sponsorship,
// the mentors looking for a sponsor to Mentors to sponsor; a press that names no card to the Overview.
$ways_back = array();
foreach ( array( 'offers', 'events', 'looking', '' ) as $card ) {
	try { $D::leave( 'refused', $card, $A ); } catch ( WPCPM_Test_Redirect $e ) {}
	$ways_back[ '' === $card ? 'no card' : $card ] = $GLOBALS['redirected'];
}
ck( 'each card is sent back to its own view, and a press that names no card to the Overview, for the sponsor the manager was looking at', $ways_back, array(
	'offers'  => get_permalink( $page_id ) . '?wpcpm_view=offers&' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A . '#wpcpm-sponsor-offers',
	'events'  => get_permalink( $page_id ) . '?wpcpm_view=sponsorship&' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A . '#wpcpm-sponsor-events',
	'looking' => get_permalink( $page_id ) . '?wpcpm_view=mentors&' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A . '#wpcpm-sponsor-looking',
	'no card' => get_permalink( $page_id ) . '?' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A,
) );
// With no published page there is no view to go back to: home, with the card's anchor as before.
$GLOBALS['posts'][ $page_id ]->post_status = 'draft';
$homeward = array();
foreach ( array( 'profile', '' ) as $card ) {
	try { $D::leave( 'refused', $card, $A ); } catch ( WPCPM_Test_Redirect $e ) {}
	$homeward[] = $GLOBALS['redirected'];
}
$GLOBALS['posts'][ $page_id ]->post_status = 'publish';
ck( 'with no published page a press goes home, at the card\'s anchor when it names a card, and with no sponsor argument', $homeward, array( 'https://example.test/#wpcpm-sponsor-profile', 'https://example.test/' ) );
$GLOBALS['uid'] = 5;
try { $D::leave( 'profile-saved', 'profile', $B ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'a member is sent to the view and the card whatever record they pass', $GLOBALS['redirected'], get_permalink( $page_id ) . '?wpcpm_view=company#wpcpm-sponsor-profile' );
unset( $GLOBALS['flash'][ $D::FLASH ], $GLOBALS['flash'][ WPCPM_Dashboard_Card::TARGET ] );
$GLOBALS['uid'] = 1;
ck( 'messages() knows every card\'s statuses, the Events card\'s planner among them', array_values( array_diff( array( 'profile-saved', 'mentor-interest-sent', 'tier-sent', 'sevent-sent', 'sevent-fields', 'refused' ), array_keys( $D::messages() ) ) ), array() );
// Offer more's statuses went with its card (1.123.22): a flash one left before the release names a
// status the page no longer knows, and prints nothing, as any unknown status prints nothing.
ck( 'and none of Offer more\'s, which went with its card', array_values( array_filter( array_keys( $D::messages() ), static function ( $status ) { return 0 === strpos( (string) $status, 'interest-' ); } ) ), array() );

echo "\n=== The toolbar and login routing ===\n";
$GLOBALS['uid'] = 5;
ck( 'a member\'s toolbar lists the Sponsor Dashboard as their own', ( function () { foreach ( WPCPM_Dashboards::links() as $l ) { if ( 'wpcpm-sponsor-dashboard' === $l['id'] ) { return array( $l['title'], $l['own'] ); } } return null; } )(), array( 'Sponsor Dashboard', true ) );
$GLOBALS['uid'] = 9;
ck( 'and a stranger\'s does not', in_array( 'wpcpm-sponsor-dashboard', array_column( WPCPM_Dashboards::links(), 'id' ), true ), false );
$GLOBALS['uid'] = 5;
ck( 'a member is routed to the page at login', $D::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][5] ), get_permalink( $page_id ) );
ck( 'an explicit destination is honoured', $D::login_redirect( 'https://example.test/somewhere/', 'https://example.test/somewhere/', $GLOBALS['users'][5] ), 'https://example.test/somewhere/' );
// FSPON-3: the fixture above gives user 5 the stamp and no capability, which is not what a
// real attach() leaves. Posting defaults on, so WPCPM_Sponsor_Posts::apply_caps() grants these
// three to every member of every sponsor, and the clause that used to ask edit_posts excluded
// exactly the people the routing exists for.
$GLOBALS['grants'][5] = array( 'edit_posts', 'delete_posts', 'upload_files' );
ck( 'a real member, holding exactly what attach() grants, is still routed', $D::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][5] ), get_permalink( $page_id ) );
$GLOBALS['grants'][5][] = 'edit_others_posts';
ck( 'and an account that edits other people\'s posts is left in wp-admin, which is the question the clause meant to ask', $D::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][5] ), 'https://example.test/wp-admin/' );
unset( $GLOBALS['grants'][5] );
$GLOBALS['manage'] = array( 1, 5 );
ck( 'a program manager is left alone too', $D::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][5] ), 'https://example.test/wp-admin/' );
$GLOBALS['manage'] = array( 1 );
$GLOBALS['settings']['sponsor_home'] = false;
ck( 'and the switch turns it off', $D::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][5] ), 'https://example.test/wp-admin/' );

echo "\n=== House rules ===\n";
$src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-dashboard.php' );
ck( 'no em or en dash in the class or the stylesheet', preg_match( '/\x{2013}|\x{2014}/u', $src . file_get_contents( __DIR__ . '/../assets/css/sponsor.css' ) ), 0 );
ck( 'the gate is metadata_exists(), never the value', false !== strpos( $src, 'metadata_exists(' ), true );
/**
 * A plugin stylesheet's rules, each selector's declarations keyed `<media query>|<selector>`, the
 * query '' for a rule outside any, with comments dropped and white space folded to one space.
 *
 * @param string $sheet The file under assets/css/.
 * @return array<string, string>
 */
function sheet_rules( $sheet ) {
	$css   = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( __DIR__ . '/../assets/css/' . $sheet ) );
	$rules = array();
	$media = '';
	$head  = '';
	$len   = strlen( $css );
	for ( $i = 0; $i < $len; $i++ ) {
		if ( '{' === $css[ $i ] ) {
			$selectors = trim( (string) preg_replace( '/\s+/', ' ', $head ) );
			$head      = '';
			if ( 1 === preg_match( '/^@media\s*(.*)$/', $selectors, $query ) ) {
				$media = trim( $query[1] );
				continue;
			}
			$end  = (int) strpos( $css, '}', $i );
			$body = trim( (string) preg_replace( '/\s+/', ' ', substr( $css, $i + 1, $end - $i - 1 ) ) );
			foreach ( explode( ',', $selectors ) as $selector ) {
				$key           = $media . '|' . trim( $selector );
				$rules[ $key ] = isset( $rules[ $key ] ) ? $rules[ $key ] . ' ' . $body : $body;
			}
			$i = $end;
			continue;
		}
		if ( '}' === $css[ $i ] ) {
			$media = '';
			$head  = '';
			continue;
		}
		$head .= $css[ $i ];
	}
	return $rules;
}
// The theme dresses a card's row on this page, and the resources in thirds on the pages that still
// draw them (the Sponsor Dashboard's help went to the menu in 1.123.78); the plugin's own sheets
// carry the same, as sponsor.css's first lines promise, value for value where the base's own rhythm
// allows. There a card keeps its 2em over the row, where the theme takes it and the card's line off
// every card of a group, and the row keeps its 1.25em of side padding: so the action sits the theme's
// 8px down the row rather than down the card, inside the row's own padding, and the disclosure gives
// up the group's 2.5em under it, as the theme's groups do, so under 600px the action sits 8px under
// its row. The action shows only while its card is folded: the shell prints it on every card that has
// one, and one rule, keyed on the disclosure's own state, hides it once the card is open, however it
// opened, and takes it out of the reading and focus order with it; the row keeps room for it only
// while it shows.
$sponsor_sheet     = sheet_rules( 'sponsor.css' );
$student_sheet     = sheet_rules( 'student.css' );
$institution_sheet = sheet_rules( 'institution.css' );
$row_room          = '.wpcpm-sponsor__card:has( > .wpcpm-sponsor__row-action ) > .wpcpm-group__disclosure:not([open]) > .wpcpm-group__summary';
ck( 'the plugin\'s own sheet dresses a card\'s row as the theme does: the state in the preview voice, the action beside the row with the card as its positioning context, the disclosure with no room of its own under it and room kept in the row, and under 600px the action 8px under its row', array(
	$sponsor_sheet['|.wpcpm-sponsor__state'] ?? null,
	false !== strpos( $sponsor_sheet['|.wpcpm-sponsor__card'] ?? '', 'position: relative;' ),
	$sponsor_sheet['|.wpcpm-sponsor__card > .wpcpm-group__disclosure'] ?? null,
	$sponsor_sheet['|.wpcpm-sponsor__row-action'] ?? null,
	$sponsor_sheet[ '|' . $row_room ] ?? null,
	$sponsor_sheet['( max-width: 600px )|.wpcpm-sponsor__row-action'] ?? null,
	$sponsor_sheet[ '( max-width: 600px )|' . $row_room ] ?? null,
), array(
	'color: #656a71; font-size: 14px; line-height: 20px;',
	true,
	'margin-bottom: 0;',
	'display: flex; margin: 0; position: absolute; right: calc( 1.25em + 1px ); top: calc( 2em + 8px );',
	'padding-right: calc( 1.25em + 136px );',
	'margin-top: 8px; position: static;',
	'padding-right: 1.25em;',
) );
$room_rules = array_values( array_filter( array_keys( $sponsor_sheet ), static function ( $key ) { return false !== strpos( $key, 'wpcpm-group__summary' ) && false !== strpos( $key, 'row-action' ); } ) );
ck( 'and the action shows only while its card is folded: one rule, outside every query, hides it once the card\'s disclosure is open, and the row keeps room for it only while the disclosure is folded', array(
	$sponsor_sheet['|.wpcpm-sponsor__card:has( > .wpcpm-group__disclosure[open] ) > .wpcpm-sponsor__row-action'] ?? null,
	array_values( array_filter( array_keys( $sponsor_sheet ), static function ( $key ) { return false !== strpos( $key, '[open]' ) && '.wpcpm-sponsor__row-action' === substr( $key, -strlen( '.wpcpm-sponsor__row-action' ) ) && 0 !== strpos( $key, '|' ); } ) ),
	$room_rules,
), array(
	'display: none;',
	array(),
	array( '|' . $row_room, '( max-width: 600px )|' . $row_room ),
) );
$student_raw = (string) file_get_contents( __DIR__ . '/../assets/css/student.css' );
ck( 'and the resources in thirds, the theme\'s three tracks, stacked under 600px by the rule for halves that follows them, the program contact in a column of its own without the line and the room that set it apart under the guide\'s buttons', array(
	$student_sheet['|.wpcpm-resources--split-3'] ?? null,
	$student_sheet['( max-width: 600px )|.wpcpm-resources--split'] ?? null,
	(int) strpos( $student_raw, '.wpcpm-resources--split-3 {' ) > 0 && strpos( $student_raw, '.wpcpm-resources--split-3 {' ) < strpos( $student_raw, 'Halves do not fit; they stack' ),
	$institution_sheet['|.wpcpm-resources__col--contact > .wpcpm-resources__contact'] ?? null,
), array(
	'grid-template-columns: repeat( 3, minmax( 0, 1fr ) );',
	'grid-template-columns: 1fr;',
	true,
	'border-top: 0; margin-top: 0; padding-top: 0;',
) );
// The Usage card's table has seven columns, more than a phone is wide: open at 390px it took the page
// to 595px (1.123.16). Since 1.123.17 it sits in a box of its own, as the Administrator Dashboard's
// tables with column headers do since 1.122.35: on a phone the box, not the table, scrolls sideways,
// by that page's rule value for value at the same width, and no rule of this sheet gives the table's
// own box a display, an overflow or a position at any width, so it is a table again inside the box.
// The box is a tab stop, and its ring is that page's, weightless, so a theme's own ring wins.
$admin_sheet = sheet_rules( 'administrator.css' );
$usage_table = array();
foreach ( $sponsor_sheet as $key => $body ) {
	if ( 1 === preg_match( '/\|\.wpcpm-usage$/', $key ) && 1 === preg_match( '/(^|;\s*)(display|overflow|overflow-x|overflow-y|position)\s*:/', $body ) ) {
		$usage_table[] = $key;
	}
}
ck( 'on a phone the Usage table scrolls inside its own box, by the Administrator Dashboard\'s rule for its boxes, value for value at the same width, and no rule of this sheet sets the table\'s own box', array(
	$sponsor_sheet['( max-width: 782px )|.wpcpm-usage__scroll'] ?? null,
	( $sponsor_sheet['( max-width: 782px )|.wpcpm-usage__scroll'] ?? 'a' ) === ( $admin_sheet['( max-width: 782px )|.wpcpm-administrator__scroll'] ?? 'b' ),
	$usage_table,
), array(
	'display: block; overflow-x: auto; position: relative;',
	true,
	array(),
) );
ck( 'and the box\'s focus ring is that page\'s for its boxes, weightless inside :where(), at every width', array(
	$sponsor_sheet['|:where( .wpcpm-usage__scroll:focus-visible )'] ?? null,
	( $sponsor_sheet['|:where( .wpcpm-usage__scroll:focus-visible )'] ?? 'a' ) === ( $admin_sheet['|:where( .wpcpm-administrator__scroll:focus-visible )'] ?? 'b' ),
	array_values( array_filter( array_keys( $sponsor_sheet ), static function ( $key ) { return false !== strpos( $key, '.wpcpm-usage__scroll:focus' ) && false === strpos( $key, ':where(' ); } ) ),
), array(
	'outline: 2px solid currentColor; outline-offset: 2px;',
	true,
	array(),
) );
// A phone's strip (1.123.22), in the Administrator Dashboard's sheet, which this page's own strip wore
// until 1.123.78 and that page's still does: one tile a row,
// the number beside its words. The theme sets the number's font with a shorthand at two classes, which
// resets its figures, so the one-width figures are asked at three classes to hold. A level's name at
// 28px, "Supporter" or "Champion", can leave the words too little room on a narrow phone, so the words
// take at least 7em beside the number or drop under it, 4px below, and break a long word anywhere
// rather than run out of the tile.
ck( 'on a phone the tile sets its number beside its words, the words dropping under it when they do not fit, and the number\'s one-width figures are asked at three classes, past the theme\'s font shorthand', array(
	$admin_sheet['( max-width: 480px )|.wpcpm-attention__link'] ?? null,
	$admin_sheet['( max-width: 480px )|.wpcpm-attention__n'] ?? null,
	$admin_sheet['( max-width: 480px )|.wpcpm-attention__tiles .wpcpm-attention__link .wpcpm-attention__n'] ?? null,
	$admin_sheet['( max-width: 480px )|.wpcpm-attention__l'] ?? null,
), array(
	'align-items: baseline; column-gap: 12px; display: flex; flex-wrap: wrap; padding: 12px 16px; row-gap: 4px;',
	'flex: none; min-width: 1.25em;',
	'font-variant-numeric: tabular-nums;',
	'flex: 1 1 7em; min-width: 0; overflow-wrap: anywhere;',
) );
// Read off the source as the Administrator Dashboard's suite reads its cards: the Usage card prints
// the box right before its one table and closes it right after, and it is the card's one box.
$usage_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-usage.php' );
ck( 'the Usage card opens its box right before its table, closes it right after, and opens no other', array(
	preg_match_all( '/self::scroll_open\(\);\s*echo \'<table class="wpcpm-table wpcpm-usage">/', $usage_src ),
	substr_count( $usage_src, "echo '</tr></tfoot></table></div>';" ),
	substr_count( $usage_src, 'self::scroll_open()' ),
	substr_count( $usage_src, '<table ' ),
), array( 1, 1, 1, 1 ) );
// Every card a view draws is in messages()' union, so a card that gains a handler has its sentences in
// reach without a second list to remember; read from the source, since a card with no form, like the
// Badge card, merges nothing a run could see. The views' cards are named once, in view_owners().
$owners_src = substr( $src, (int) strpos( $src, 'private static function view_owners(' ) );
$owners_src = substr( $owners_src, 0, (int) strpos( $owners_src, "\n\t}\n" ) );
preg_match_all( "/self::VIEW_([A-Z]+)\s*=> array\(([^)]*)\)/", $owners_src, $card_lists );
preg_match_all( "/'(WPCPM_[A-Za-z_]+)'/", implode( ' ', $card_lists[2] ), $drawn_cards );
$messages_src = substr( $src, (int) strpos( $src, 'public static function messages()' ) );
$messages_src = substr( $messages_src, 0, (int) strpos( $messages_src, "\n\t}\n" ) );
preg_match_all( "/'(WPCPM_[A-Za-z_]+)'/", $messages_src, $merged_cards );
ck( 'every card a view draws is merged into messages(), the Badge card among them', array( count( $drawn_cards[1] ) > 0, array_values( array_diff( $drawn_cards[1], $merged_cards[1] ) ) ), array( true, array() ) );
// The views' cards (1.123.78), read as lists of names rather than as a run of source, so a layout with one
// entry to a line reads the same: the four sections of 27 September 2026 split into the menu's views, the
// Usage card a view of its own and the agreement one too. messages() merges the same cards, with the
// Events card's planner right after the tier card that draws it and the logo right after the profile card
// that holds it. Its order beyond those two is no behavior: the only keys two cards share carry the same
// sentence in each, so whichever wins the merge reads alike.
$group_cards = array();
foreach ( $card_lists[2] as $at => $card_list ) {
	preg_match_all( "/'([A-Za-z_]+)'/", $card_list, $names );
	$group_cards[ strtolower( $card_lists[1][ $at ] ) ] = $names[1];
}
ck( 'six views of cards, in the menu\'s order and the order VIEW_CARDS lists them: the sponsorship, the offers and posts, the claims, the mentors, the company and the agreement', array( $group_cards, array_keys( $group_cards ) === array_keys( $D::VIEW_CARDS ) ), array( array(
	'sponsorship' => array( 'WPCPM_Sponsor_Tier_Card', 'WPCPM_Sponsor_Report_Card', 'WPCPM_Sponsor_Badge' ),
	'offers'      => array( 'WPCPM_Sponsor_Offers', 'WPCPM_Sponsor_Posts' ),
	'reached'     => array( 'WPCPM_Sponsor_Usage' ),
	'mentors'     => array( 'WPCPM_Sponsor_Mentors' ),
	'company'     => array( 'WPCPM_Sponsor_Profile', 'people' ),
	'agreement'   => array( 'WPCPM_Sponsor_Agreement_Card' ),
), true ) );
$tier_at    = array_search( 'WPCPM_Sponsor_Tier_Card', $merged_cards[1], true );
$profile_at = array_search( 'WPCPM_Sponsor_Profile', $merged_cards[1], true );
// The offers' review (1.123.18) draws no card here: a decision of it posted without the Administrator
// Dashboard's way back lands on the Offers card, so its outcomes are merged, right after the Offers card's.
$offers_at  = array_search( 'WPCPM_Sponsor_Offers', $merged_cards[1], true );
ck( 'messages() merges the offers\' review right after the Offers card, for a decision that lands here', false === $offers_at ? array() : array_slice( $merged_cards[1], $offers_at, 2 ), array( 'WPCPM_Sponsor_Offers', 'WPCPM_Sponsor_Offer_Review' ) );
$merged_set = array_values( array_diff( $merged_cards[1], array( 'WPCPM_Sponsor_Event_Planner', 'WPCPM_Sponsor_Logo', 'WPCPM_Sponsor_Offer_Review' ) ) );
$drawn_set  = array_values( array_diff( array_merge( array(), ...array_values( $group_cards ) ), array( 'people' ) ) );
sort( $merged_set );
sort( $drawn_set );
ck( 'and messages() merges exactly those cards, the planner right after the tier card and the logo right after the profile', array(
	$merged_set,
	false === $tier_at ? array() : array_slice( $merged_cards[1], $tier_at, 2 ),
	false === $profile_at ? array() : array_slice( $merged_cards[1], $profile_at, 2 ),
), array(
	$drawn_set,
	array( 'WPCPM_Sponsor_Tier_Card', 'WPCPM_Sponsor_Event_Planner' ),
	array( 'WPCPM_Sponsor_Profile', 'WPCPM_Sponsor_Logo' ),
) );
ck( 'the block carries the version', json_decode( file_get_contents( __DIR__ . '/../blocks/sponsor-dashboard/block.json' ), true )['version'], '1.96.0' );

echo "\n=== The badge, on Your sponsorship (1.123.78) ===\n";
// The badge beside the details (1.115.1) went with the identity header: the Overview's head is the
// concept's (the logo, the name, the thanks), and the badge is its card's, on Your sponsorship, built from
// the Badge card's own helpers at the versioned address (1.123.22), so a change of level shows at once
// rather than once a browser's copy has expired.
$GLOBALS['uid'] = 1; $GLOBALS['get'] = array( WPCPM_Sponsor_Roster::ARG_VIEW => $A );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion' ) );
$mango       = WPCPM_Sponsors_Index::row( $A );
$light_badge = sprintf(
	'<figure class="wpcpm-sponsor-badge__figure wpcpm-sponsor-badge__figure--light"><img src="%1$s" alt="%2$s" width="%3$d" height="%4$d" />',
	esc_url( WPCPM_Sponsor_Badge::versioned_url( $mango ) ),
	esc_attr( WPCPM_Sponsor_Badge::light_alt( $mango ) ),
	WPCPM_Sponsor_Badge::WIDTH,
	WPCPM_Sponsor_Badge::HEIGHT
);
$badge_views = every_view();
ck( 'a Champion, switch on: the light badge is drawn once, by its card on Your sponsorship, with the Badge card\'s own alt at the address that names its tier and year, and no head carries a badge', array(
	substr_count( implode( '', $badge_views ), $light_badge ),
	false !== strpos( $badge_views['sponsorship'], $light_badge ),
	false !== strpos( $light_badge, '?v=champion-' ),
	strpos( heads_of( implode( '', $badge_views ) ), 'sponsor-badge' ),
	strpos( implode( '', $badge_views ), 'wpcpm-sponsor__badge' ),
), array( 1, true, true, false, false ) );

$GLOBALS['settings']['sponsor_tiers_live'] = false;
ck( 'the same Champion, switch off: no badge on any view', array( strpos( implode( '', every_view() ), 'sponsor-badge' ) ), array( false ) );

$GLOBALS['settings']['sponsor_tiers_live'] = true;
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'mentor' ) );
$friend_badge = in_view( 'sponsorship' );
ck( 'a Friend, switch on: no Badge card on Your sponsorship, since its tier carries none', array( view_drawn( $friend_badge ), strpos( $friend_badge, 'id="wpcpm-sponsor-badge"' ), strpos( $friend_badge, 'sponsor-badge' ) ), array( 'sponsorship', false, false ) );

WPCPM_Sponsors_Index::patch( $A, array( 'tier' => '' ) );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$GLOBALS['get'] = array();

echo "\n=== What waits: the Overview's rows and the menu's counts (1.123.78) ===\n";
// What the At a glance strip counted (the owner, 27 September 2026; Institutions release 3) is the
// Overview's rows since 1.123.78, the version 3 concepts: one row for each kind of thing waiting on the
// sponsor's people, each with its status tag in words, the sentence that says why and a link to the
// view and card that hold it, drawn only when that card is, and asked the card's own way; the term once
// the tiers are live, in its last thirty days or ended; and, when nothing waits at all, one quiet line.
// The menu counts what waits beside the view that holds it, and all of it beside Overview.
// From here on today is a known day, so a term's days are counted from it, and the date format is
// the one the site writes a day in.
$GLOBALS['today']               = '2026-10-06';
$GLOBALS['opts']['date_format'] = 'F j, Y';
$GLOBALS['opts']['time_format'] = 'g:i a';
$GLOBALS['uid']                 = 5;
$GLOBALS['get']                 = array();
$M1 = 'recMENTOR00000001'; $M2 = 'recMENTOR00000002'; $M3 = 'recMENTOR00000003';
$page = get_permalink( $page_id );
// The profile's details are the fixture's three, filled, and the checks below empty some of them and put them back.
$A_email = (string) WPCPM_Sponsors_Index::row( $A )['contact_email'];

/**
 * What waits, as the Overview printed it: the rows' section, or the one quiet line, or '' for an
 * Overview that says neither.
 *
 * @param string $html The page.
 * @return string
 */
function waiting_of( $html ) {
	$main = main_of( $html );
	$at   = strpos( $main, '<section class="wpcpm-sponsor__waiting"' );
	if ( false !== $at ) { return substr( $main, $at, (int) strpos( $main, '</section>', $at ) + strlen( '</section>' ) - $at ); }
	return false !== strpos( $main, nothing_waits() ) ? nothing_waits() : '';
}
/**
 * The quiet line for a sponsor with nothing waiting, which links nowhere, since no card holds nothing.
 *
 * @return string
 */
function nothing_waits() { return '<p class="wpcpm-overview__quiet wpcpm-sponsor__nothing">Nothing waits for you.</p>'; }
/**
 * The rows' section around its rows, under its own heading.
 *
 * @param string[] $rows The rows, as wait_row() writes them.
 * @return string
 */
function waiting_section( array $rows ) { return '<section class="wpcpm-sponsor__waiting" aria-labelledby="wpcpm-sponsor-overview-waiting"><h2 class="wpcpm-sponsor__group-title" id="wpcpm-sponsor-overview-waiting">Waiting for you</h2>' . implode( '', $rows ) . '</section>'; }
/**
 * One row as the Overview draws it: the red tag in words, what waits and why, and the link to the card
 * that holds it at the view that draws it, as the other suites' stand-in writes that address.
 *
 * @param string $kind   What waits: `term`, `ended`, `returned`, `low`, `draft`, `posts`, `event`,
 *                       `agreement returned`, `agreement revoked` or `profile`.
 * @param int    $n      How many.
 * @param string $day    The term's last day, as the site writes a day, for `term` and `ended`.
 * @param string $record The sponsor's key, for an Administrator's link, or ''.
 * @return string
 */
function wait_row( $kind, $n = 1, $day = '', $record = '' ) {
	$num   = number_format_i18n( $n ) . ' ';
	$one   = 1 === (int) $n;
	$note  = 'An Administrator wrote a note on what to change.';
	$kinds = array(
		'term'               => array( 'sponsorship', 'Your term ends on ' . $day, 'Your sponsorship says how to renew it.', 'See your sponsorship' ),
		'ended'              => array( 'sponsorship', 'Your term ended on ' . $day, 'Your sponsorship says how to renew it.', 'See your sponsorship' ),
		'returned'           => array( 'offers', $num . ( $one ? 'offer returned with a note' : 'offers returned with a note' ), $note, 'Open the offers' ),
		'low'                => array( 'offers', $num . ( $one ? 'offer running low on codes' : 'offers running low on codes' ), 'Fewer codes are left than the number you set for a warning. Add more, or pause the offer.', 'Open the offers' ),
		'draft'              => array( 'offers', $num . ( $one ? 'offer not switched on yet' : 'offers not switched on yet' ), 'Nobody can claim an offer until it is switched on.', 'Open the offers' ),
		'posts'              => array( 'posts', $num . ( $one ? 'post returned with a note' : 'posts returned with a note' ), $note, 'Open the posts' ),
		'event'              => array( 'events', 'Your event plan was returned with a note', $note, 'Open the events' ),
		'agreement returned' => array( 'agreement', 'Collaboration Agreement returned', 'The program needs a new signed copy from your company.', 'Open the agreement' ),
		'agreement revoked'  => array( 'agreement', 'Collaboration Agreement revoked', 'The program needs a new signed copy from your company.', 'Open the agreement' ),
		'profile'            => array( 'profile', $num . ( $one ? 'company profile detail missing' : 'company profile details missing' ), 'Fill in what is missing on the company profile.', 'Complete the profile' ),
	);
	$row   = $kinds[ $kind ];
	return '<div class="wpcpm-sponsor__wait"><span class="wpcpm-status wpcpm-status--red">Waits</span><p class="wpcpm-sponsor__wait-text"><strong>' . esc_html( $row[1] ) . '</strong> <span>' . esc_html( $row[2] ) . '</span></p><a class="wpcpm-btn wpcpm-btn--secondary" href="' . wpcpm_test_sponsor_card_url( WPCPM_Sponsors_Dashboard::page_url(), $row[0], $record ) . '">' . esc_html( $row[3] ) . '</a></div>';
}
/**
 * What waits as waiting_items() answers it, each the card that holds it and how many, in order.
 *
 * @param string $record Sponsor key.
 * @return string[]
 */
function waits( $record ) {
	$row = WPCPM_Sponsors_Index::row( $record );
	return array_map( static function ( $item ) { return $item['card'] . ' ' . $item['count']; }, WPCPM_Sponsors_Dashboard::waiting_items( $record, is_array( $row ) ? $row : WPCPM_Sponsors_Index::empty_row() ) );
}
/**
 * The cards the Overview's rows link to that the view their link opens does not draw: none, when every
 * row lands on its card. Each link is followed as a browser follows it, its view and, for an
 * Administrator, its sponsor read off the address.
 *
 * @param string $html The page.
 * @return string[]
 */
function rows_without_card( $html ) {
	preg_match_all( '#<div class="wpcpm-sponsor__wait">.*?<a class="wpcpm-btn wpcpm-btn--secondary" href="([^"]*)">#', main_of( $html ), $m );
	$missing = array();
	foreach ( $m[1] as $href ) {
		$query = array();
		parse_str( (string) parse_url( $href, PHP_URL_QUERY ), $query );
		$kept = $GLOBALS['get'] ?? array();
		if ( isset( $query[ WPCPM_Sponsor_Roster::ARG_VIEW ] ) ) { $GLOBALS['get'][ WPCPM_Sponsor_Roster::ARG_VIEW ] = $query[ WPCPM_Sponsor_Roster::ARG_VIEW ]; }
		$landed         = in_view( isset( $query[ WPCPM_Sponsors_Dashboard::VIEW_ARG ] ) ? $query[ WPCPM_Sponsors_Dashboard::VIEW_ARG ] : WPCPM_Sponsors_Dashboard::VIEW_OVERVIEW );
		$GLOBALS['get'] = $kept;
		$anchor         = (string) parse_url( $href, PHP_URL_FRAGMENT );
		if ( false === strpos( $landed, '<details id="' . $anchor . '"' ) ) { $missing[] = $anchor; }
	}
	return $missing;
}
/**
 * One card as a view printed it, from its disclosure to its section's end, or '' when it is not drawn.
 *
 * @param string $html The page.
 * @param string $key  The card's key.
 * @return string
 */
function card_of( $html, $key ) {
	$at = strpos( (string) $html, '<details id="wpcpm-sponsor-' . $key . '"' );
	if ( false === $at ) { return ''; }
	return substr( (string) $html, $at, (int) strpos( (string) $html, '</section>', $at ) - $at );
}
/**
 * What a card's row says: the phrase in its summary, as printed, or null when it says none or the
 * card is not drawn.
 *
 * @param string $html The page.
 * @param string $key  The card's key.
 * @return string|null
 */
function page_row( $html, $key ) {
	$at = strpos( $html, '<details id="wpcpm-sponsor-' . $key . '"' );
	if ( false === $at ) { return null; }
	$summary = substr( $html, $at, (int) strpos( $html, '</summary>', $at ) - $at );
	return preg_match( '#<span class="wpcpm-sponsor__state">(.*?)</span>#s', $summary, $m ) ? $m[1] : null;
}
/**
 * The `Y-m-d` day this many days after the pinned today; a negative count is a day already past.
 *
 * @param int $days Days.
 * @return string
 */
function day_from_today( $days ) { return gmdate( 'Y-m-d', strtotime( $GLOBALS['today'] . ' 00:00:00 UTC' ) + (int) $days * DAY_IN_SECONDS ); }
/**
 * The counts the Offers card's reader answers (`WPCPM_Sponsor_Offers::waiting_counts()`), as the
 * stand-in below hands them back.
 *
 * @param int $returned Offers returned with a note.
 * @param int $low      Offers running low on codes.
 * @param int $draft    Offers not switched on yet.
 * @return array
 */
function offers_waiting( $returned, $low = 0, $draft = 0 ) { return array( 'returned' => $returned, 'low' => $low, 'draft' => $draft ); }
/**
 * A sponsor event written the way WPCPM_Sponsor_Event::plan() writes one, as bin/test-sponsor-cards.php
 * writes its own: under an ID far above the store's posts, with the people on it as one list of IDs.
 *
 * @param string $record  Sponsor key.
 * @param string $state   `planned`, `approved` or `cancelled`.
 * @param int    $start   A UTC timestamp.
 * @param int    $minutes How long it runs.
 * @param string $title   The title.
 * @param int[]  $people  The user IDs on it.
 * @return int The event's ID.
 */
function sponsor_event( $record, $state, $start, $minutes, $title, array $people = array() ) {
	static $next = 9000;
	$id                      = $next++;
	$post                    = new WP_Post();
	$post->ID                = $id;
	$post->post_type         = WPCPM_Sponsor_Event::POST_TYPE;
	$post->post_status       = 'private';
	$post->post_title        = $title;
	$post->post_content      = 'What the event covers.';
	$GLOBALS['posts'][ $id ] = $post;
	$GLOBALS['pmeta'][ $id ] = array(
		WPCPM_Sponsor_Event::META_SPONSOR  => $record,
		WPCPM_Sponsor_Event::META_KIND     => WPCPM_Sponsor_Event::KIND_CO_HOSTED,
		WPCPM_Sponsor_Event::META_START    => (int) $start,
		WPCPM_Sponsor_Event::META_END      => (int) $start + (int) $minutes * 60,
		WPCPM_Sponsor_Event::META_ZONE     => 'UTC',
		WPCPM_Sponsor_Event::META_MINUTES  => (int) $minutes,
		WPCPM_Sponsor_Event::META_PLACES   => 0,
		WPCPM_Sponsor_Event::META_LINK     => 'https://call.example/room',
		WPCPM_Sponsor_Event::META_STATE    => $state,
		WPCPM_Sponsor_Event::META_REVISION => 0,
		WPCPM_Sponsor_Event::META_PERSON   => $people,
	);
	return $id;
}

// With none of the cards a sponsor's people wait on installed but the profile's, and A with no mentor yet,
// a Friend with the switch off has nothing to be told: one quiet line, right under the head, linking
// nowhere, since no card holds nothing, and no count in the menu.
$only_none = $D::render();
ck( 'a Friend with the switch off, nothing waiting and no card a row could name but a complete profile: "Nothing waits for you." alone, one quiet line with no link right under the Overview head, and no count in the menu', array(
	waiting_of( $only_none ),
	false !== strpos( $only_none, '</header>' . nothing_waits() ),
	substr_count( waiting_of( $only_none ), '<a ' ),
	waits( $A ),
	menu_counts( $only_none ),
), array( nothing_waits(), true, 0, array(), array() ) );
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => '', 'tier_until' => '', 'mentors' => array( $M1, $M2, $M3 ) ) );

// From here on the Offers, Posts and Collaboration Agreement cards are stand-ins drawing their cards in
// the shell under their own anchors, each answering the one question a row asks of it, as the fixture
// sets it per sponsor (their own suites are bin/test-sponsor-offers.php, bin/test-sponsor-posts.php and
// bin/test-sponsor-cards.php, which pin the readers against the cards). The event store and what the
// planner reads are the real classes, as bin/test-sponsor-cards.php loads them, with the store's clock at
// noon on the pinned today, so the Events card holds a real planner and the rows read the real store.
if ( ! class_exists( 'WPCPM_Sponsor_Offers' ) ) {
	/** The Offers card, drawn in the shell, and the counts of what waits on its sponsor. */
	class WPCPM_Sponsor_Offers {
		const CARD        = 'offers';
		const KIND_CODES  = 'codes';
		const STATE_LIVE  = 'live';
		const STATE_ENDED = 'ended';
		// The offers and which are over, for the figures' check (1.123.78); none until a check names some.
		public static function offers_of( $record ) { return isset( $GLOBALS['offers_of'][ $record ] ) ? $GLOBALS['offers_of'][ $record ] : array(); }
		public static function is_past( array $offer ) { return ! empty( $offer['past'] ); }
		public static function waiting_counts( $record, $today = '' ) { return isset( $GLOBALS['offers_waiting'][ $record ] ) ? $GLOBALS['offers_waiting'][ $record ] : array( 'returned' => 0, 'low' => 0, 'draft' => 0 ); }
		public static function render( $record, array $context ) { WPCPM_Dashboard_Card::open( array( 'key' => self::CARD, 'title' => 'Offers and codes' ) ); echo '<p>The offers.</p>'; WPCPM_Dashboard_Card::close(); }
	}
}
if ( ! class_exists( 'WPCPM_Sponsor_Posts' ) ) {
	/**
	 * The Posts card, drawn in the shell for a sponsor `$GLOBALS['posts_drawn']` names, its own test for
	 * whether it draws, which the Offers view's sentence and the rows ask, and the count of its returned
	 * posts. The real ones are pinned in bin/test-sponsor-posts.php.
	 */
	class WPCPM_Sponsor_Posts {
		const CARD = 'posts';
		public static function draws( $record ) { return ! empty( $GLOBALS['posts_drawn'][ $record ] ); }
		public static function returned_count( $record ) { return isset( $GLOBALS['posts_returned'][ $record ] ) ? (int) $GLOBALS['posts_returned'][ $record ] : 0; }
		public static function render( $record, array $context ) { if ( self::draws( $record ) ) { WPCPM_Dashboard_Card::open( array( 'key' => self::CARD, 'title' => 'Posts' ) ); echo '<p>The posts.</p>'; WPCPM_Dashboard_Card::close(); } }
	}
}
if ( ! class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
	/** The two summary states the Collaboration Agreement card's reader answers with. */
	class WPCPM_Sponsor_Agreement {
		const SUMMARY_RETURNED = 'returned';
		const SUMMARY_REVOKED  = 'revoked';
	}
}
if ( ! class_exists( 'WPCPM_Sponsor_Agreement_Card' ) ) {
	/** The Collaboration Agreement card, drawn in the shell, and the state in which it asks the company for a file. */
	class WPCPM_Sponsor_Agreement_Card {
		const CARD = 'agreement';
		public static function waiting_state( $record ) { return isset( $GLOBALS['agreement_waiting'][ $record ] ) ? $GLOBALS['agreement_waiting'][ $record ] : ''; }
		public static function render( $record, array $context ) { WPCPM_Dashboard_Card::open( array( 'key' => self::CARD, 'title' => 'Collaboration Agreement' ) ); echo '<p>The agreement.</p>'; WPCPM_Dashboard_Card::close(); }
	}
}
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-availability.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event.php';
WPCPM_Sponsor_Event::for_tests( static function () { return (int) strtotime( $GLOBALS['today'] . ' 12:00:00 UTC' ); } );
$GLOBALS['offers_waiting']    = array();
$GLOBALS['posts_returned']    = array();
$GLOBALS['agreement_waiting'] = array();

// A member whose sponsor the store holds no row for: its stamp outlived the row. The page is drawn on
// the empty row, whose floor (no tier, no mentor, a profile with all three details missing) is no fact
// about the sponsor, so nothing is said to wait for it, however much the stand-ins say waits and however
// many of its cards are drawn on their views. Asked before any event or report exists: this suite's
// get_posts() does not read a query's sponsor clause, and would answer another sponsor's.
$GLOBALS['users'][7] = new WP_User( 7, array( 'wpcpm_sponsor' ), 'Rep Seven', 'sponsor-member@example.test' );
$GLOBALS['umeta'][7] = array( WPCPM_Sponsor_Members::META_RECORD_ID => 'recSPONSOR0000009', WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['uid']      = 7;
$GLOBALS['offers_waiting']    = array( 'recSPONSOR0000009' => offers_waiting( 2, 1, 1 ) );
$GLOBALS['posts_drawn']       = array( 'recSPONSOR0000009' => true );
$GLOBALS['posts_returned']    = array( 'recSPONSOR0000009' => 1 );
$GLOBALS['agreement_waiting'] = array( 'recSPONSOR0000009' => 'returned' );
$rowless_off                  = $D::render();
$rowless_cards_off            = array( view_cards( in_view( 'offers' ) ), view_cards( in_view( 'mentors' ) ), view_cards( in_view( 'company' ) ) );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$rowless_on       = $D::render();
$rowless_cards_on = view_cards( in_view( 'sponsorship' ) );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$GLOBALS['offers_waiting']    = array();
$GLOBALS['posts_drawn']       = array();
$GLOBALS['posts_returned']    = array();
$GLOBALS['agreement_waiting'] = array();
$GLOBALS['uid']               = 5;
ck( 'a sponsor the store holds no row for still has its cards drawn on their views: its offers and posts, mentors and profile with the switch off, its sponsorship card with it on', array( $rowless_cards_off, $rowless_cards_on ), array( array( array( 'offers', 'posts' ), array( 'mentors', 'looking' ), array( 'profile', 'people' ) ), array( 'sponsorship' ) ) );
ck( 'but nothing is said to wait for it, since its rows would read the empty row: no row, no quiet line, no count in the menu and no figures, with the switch off or on, though offers, a post and the agreement wait', array(
	waiting_of( $rowless_off ),
	waiting_of( $rowless_on ),
	menu_counts( $rowless_off ),
	menu_counts( $rowless_on ),
	strpos( $rowless_off . $rowless_on, 'wpcpm-figures' ),
), array( '', '', array(), array(), false ) );

// A Friend, the switch off, with every card installed and nothing waiting: the figures count its three
// mentors, and no row is drawn for its offers or its mentors, though its offers, mentors and profile are
// drawn on their views. The figures' source line names today, and is matched as words.
$mentor_off = $D::render();
ck( 'a Friend with the switch off and nothing waiting: "Nothing waits for you." alone, right under the figures and the line that says where they come from, no count in the menu, and no row for its offers or its three mentors', array(
	waiting_of( $mentor_off ),
	1 === preg_match( '#</header><section class="wpcpm-figures wpcpm-figures--1" aria-label="What your support did">.*?</section><p class="wpcpm-figures__source">As of [^<]*, from the claims on this site and the program&\#039;s mentor records\.</p>' . preg_quote( nothing_waits(), '#' ) . '#', main_of( $mentor_off ) ),
	menu_counts( $mentor_off ),
	view_cards( in_view( 'offers' ) ),
	view_cards( in_view( 'mentors' ) ),
), array( nothing_waits(), true, array(), array( 'offers' ), array( 'mentors', 'looking' ) ) );
// New offer: with the Offers card installed, the Overview's head opens the Offers view at the new offer's
// form, which the Offers card opens as a link to it lands (`WPCPM_Sponsor_Offers::NEW_ID`).
ck( 'with the Offers card installed the Overview head offers New offer, which opens Offers at the new offer\'s form', false !== strpos( heads_of( $mentor_off ), '<div class="wpcpm-head__actions"><a class="wpcpm-btn wpcpm-btn--secondary" href="' . $page . '?wpcpm_view=offers#wpcpm-offer-new">New offer</a></div></div></header>' ), true );
// The Mentors card still counts what the strip used to: the mentors the mentors sync's index holds,
// drawn by name, and the ones it does not, counted as not currently active. One of A's three is in the
// index here, so both halves of the count are read, beside the card's title where the figure stays; the
// Overview's figures count the three too, and how many have students now. Another mentor in the index is
// looking for a sponsor, which opens the Overview's door to the list.
$GLOBALS['mentors_index'] = array(
	$M1                 => array( 'name' => 'Mentor One', 'profile' => '', 'user_id' => 0, 'expertise' => array(), 'status' => 'Active', 'wants' => false, 'sponsored' => true ),
	'recMENTOR00000009' => array( 'name' => 'Mentor Nine', 'profile' => '', 'user_id' => 0, 'expertise' => array(), 'status' => 'Active', 'wants' => true, 'sponsored' => false ),
);
$indexed          = in_view( 'mentors' );
$indexed_overview = $D::render();
$GLOBALS['mentors_index'] = array();
ck( 'the mentors the strip once counted are counted beside the Mentors card\'s title on Mentors to sponsor: a mentor the index holds and the ones it does not', array(
	false !== strpos( $indexed, '<h3 class="wpcpm-group__title">Mentors you sponsor <span class="wpcpm-group__count">3</span></h3>' ),
	false !== strpos( $indexed, '<h4 class="wpcpm-mentor-tile__name">Mentor One</h4>' ),
	false !== strpos( $indexed, 'And 2 mentors who are not currently active.' ),
), array( true, true, true ) );
ck( 'and on the Overview in the thanks and the figures, the three the company sponsors and how many have students now, with no row for them', array(
	false !== strpos( heads_of( $indexed_overview ), '<p class="wpcpm-head__lede">Thank you. Your company sponsors 3 mentors.</p>' ),
	false !== strpos( $indexed_overview, '<section class="wpcpm-figures wpcpm-figures--1" aria-label="What your support did"><div class="wpcpm-figure"><p class="wpcpm-figure__value">3</p><p class="wpcpm-figure__label">mentors you sponsor</p><p class="wpcpm-figure__base">0 with students now</p></div></section>' ),
	waiting_of( $indexed_overview ),
), array( true, true, nothing_waits() ) );
ck( 'a mentor looking for a sponsor opens the Overview\'s door, after what waits, to the list of those looking on Mentors to sponsor; with nobody looking there is no door', array(
	in_order( $indexed_overview, array( nothing_waits(), '<a class="wpcpm-sponsor__door" href="' . $page . '?wpcpm_view=mentors#wpcpm-sponsor-looking">' ) ),
	false !== strpos( $indexed_overview, '<strong>1 mentor is looking for a sponsor</strong> <span>Each gives a few hours a week to students on the program.</span></span><span class="wpcpm-sponsor__door-cue">See who</span></a>' ),
	strpos( $mentor_off, 'wpcpm-sponsor__door' ),
), array( true, true, false ) );
// The Offers view's sentence says what the company wrote for them only while the view draws the Posts card,
// asked of the card's own test (1.123.22): a Friend or a Supporter with no post of its own has no Posts
// card, and the sentence then says what the company gives alone. Each is one whole sentence in the source.
$plain_offers           = in_view( 'offers' );
$GLOBALS['posts_drawn'] = array( $A => true );
$with_posts             = in_view( 'offers' );
$GLOBALS['posts_drawn'] = array();
$offer_src              = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-dashboard.php' );
ck( 'Offers opens on its sentence and the Offers card, and holds no Offer more; with no Posts card its sentence says what the company gives alone, and with one what it wrote for them too, the Posts card after the Offers card', array(
	false !== strpos( $plain_offers, view_opening( 'Offers', 'What your company gives the program&#039;s students and mentors.' ) . '<div class="wpcpm-sponsor__cards">' . $first_card( 'offers' ) ),
	view_cards( $plain_offers ),
	strpos( $plain_offers, 'wpcpm-sponsor-interests' ),
	false !== strpos( $with_posts, view_opening( 'Offers', 'What your company gives the program&#039;s students and mentors, and what it wrote for them.' ) . '<div class="wpcpm-sponsor__cards">' . $first_card( 'offers' ) ),
	view_cards( $with_posts ),
	array( substr_count( $offer_src, "__( 'What your company gives the program\\'s students and mentors.', 'wpcredits-program-manager' )" ), substr_count( $offer_src, "__( 'What your company gives the program\\'s students and mentors, and what it wrote for them.', 'wpcredits-program-manager' )" ) ),
), array( true, array( 'offers' ), false, true, array( 'offers', 'posts' ), array( 1, 1 ) ) );

// One row is enough to be shown: the tiers off, an offer returned with a note, its tag, its sentence and
// a link to the Offers card on Offers, and no "Nothing waits for you." beside it.
$GLOBALS['offers_waiting'] = array( $A => offers_waiting( 1 ) );
$one_waiting               = $D::render();
ck( 'the switch off, one offer returned with a note: that row alone waits, its red tag, its sentence and a link that opens the Offers card on Offers, counted beside Overview and Offers, and nothing says that nothing waits', array(
	waiting_of( $one_waiting ),
	rows_without_card( $one_waiting ),
	strpos( $one_waiting, 'Nothing waits for you' ),
	menu_counts( $one_waiting ),
), array( waiting_section( array( wait_row( 'returned' ) ) ), array(), false, array( 'Overview' => '1 waits (red)', 'Offers' => '1 waits (red)' ) ) );

// Each kind has its row, in the order the owner approved, a kind at 0 has none, and a company with two
// details missing counts them: offers returned (two, so plural), one running low and three not switched on
// yet, a returned post, the agreement sent back, and the profile's two missing details. The tiers are off,
// so no term and no event row come with them. Each view's count is what waits in it, each offer counted
// once, under the first kind it matches.
$GLOBALS['offers_waiting']    = array( $A => offers_waiting( 2, 1, 3 ) );
$GLOBALS['posts_drawn']       = array( $A => true );
$GLOBALS['posts_returned']    = array( $A => 1 );
$GLOBALS['agreement_waiting'] = array( $A => 'returned' );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => '', 'contact_email' => '' ) );
$every_off = $D::render();
ck( 'the switch off, every kind waiting: the offers returned, running low and not switched on yet, the post, the agreement and the profile\'s missing details, each a row in that order that lands on its card, and no "Nothing waits for you."', array(
	waiting_of( $every_off ),
	rows_without_card( $every_off ),
	waits( $A ),
), array( waiting_section( array(
	wait_row( 'returned', 2 ),
	wait_row( 'low', 1 ),
	wait_row( 'draft', 3 ),
	wait_row( 'posts', 1 ),
	wait_row( 'agreement returned' ),
	wait_row( 'profile', 2 ),
) ), array(), array( 'offers 2', 'offers 1', 'offers 3', 'posts 1', 'agreement 1', 'profile 2' ) ) );
ck( 'and the menu counts them in red beside the views that hold them: seven beside Offers, two beside Your company, one beside Agreement, and the ten beside Overview, which a screen reader hears with the item\'s name', array( menu_counts( $every_off ), false !== strpos( $every_off, '<span class="wpcpm-frame__label">Overview<span class="screen-reader-text">, 10 wait</span></span><span aria-hidden="true" class="wpcpm-frame__badge wpcpm-frame__badge--red">10 wait</span>' ) ), array( array( 'Overview' => '10 wait (red)', 'Offers' => '7 wait (red)', 'Your company' => '2 wait (red)', 'Agreement' => '1 waits (red)' ), true ) );
// The singular and the plural of each label: one returned offer and two running low, one not switched
// on yet, the agreement out of force, not sent back, and one profile detail missing.
$GLOBALS['offers_waiting']    = array( $A => offers_waiting( 1, 2, 1 ) );
$GLOBALS['agreement_waiting'] = array( $A => 'revoked' );
$GLOBALS['posts_returned']    = array( $A => 2 );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => 'plugins.mango-example.test' ) );
$all_ones = $D::render();
ck( 'the other number of each label: an offer returned, two running low, one not switched on yet, two posts returned, the agreement revoked and one profile detail missing', array( waiting_of( $all_ones ), rows_without_card( $all_ones ) ), array( waiting_section( array(
	wait_row( 'returned', 1 ),
	wait_row( 'low', 2 ),
	wait_row( 'draft', 1 ),
	wait_row( 'posts', 2 ),
	wait_row( 'agreement revoked' ),
	wait_row( 'profile', 1 ),
) ), array() ) );
// A kind at 0 draws no row, whatever its neighbors hold: only the offers not switched on yet, and the
// numbers read as the site writes numbers, in the row and in the menu.
$GLOBALS['offers_waiting']    = array( $A => offers_waiting( 0, 0, 1500 ) );
$GLOBALS['agreement_waiting'] = array();
$GLOBALS['posts_returned']    = array();
WPCPM_Sponsors_Index::patch( $A, array( 'contact_email' => $A_email ) );
$many = $D::render();
ck( 'a kind at zero draws no row, and a count reads as the site writes numbers: fifteen hundred offers not switched on yet, alone, in the row and in the menu', array( waiting_of( $many ), menu_counts( $many ) ), array( waiting_section( array( wait_row( 'draft', 1500 ) ) ), array( 'Overview' => '1,500 wait (red)', 'Offers' => '1,500 wait (red)' ) ) );
// Posts: the row is drawn only while the Posts card is, asked the card's own way (draws()), however
// many posts it counts as returned.
$GLOBALS['offers_waiting'] = array();
$GLOBALS['posts_drawn']    = array();
$GLOBALS['posts_returned'] = array( $A => 3 );
$posts_gone                = $D::render();
ck( 'a returned post makes no row while the Posts card is not drawn: neither the card on Offers nor a link to it, nor a count', array( waiting_of( $posts_gone ), strpos( in_view( 'offers' ), '<details id="wpcpm-sponsor-posts"' ), strpos( $posts_gone, '#wpcpm-sponsor-posts' ), menu_counts( $posts_gone ) ), array( nothing_waits(), false, false, array() ) );
$GLOBALS['posts_drawn'] = array( $A => true );
$posts_back             = $D::render();
ck( 'and one row for them while it is, counting the three, which lands on the Posts card on Offers', array( waiting_of( $posts_back ), rows_without_card( $posts_back ) ), array( waiting_section( array( wait_row( 'posts', 3 ) ) ), array() ) );
$GLOBALS['posts_drawn']    = array();
$GLOBALS['posts_returned'] = array();

// The term, once the switch is on: a row in the last thirty days of a term and from the day after it
// ended, naming the day, which opens the Sponsorship card on Your sponsorship. Thirty-one days left, no
// term, and a date left on a Friend's row (no term to speak for) draw none. Nothing else waits, so a term
// with no row leaves "Nothing waits for you.", and a row stands alone.
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$tier_cases = array(
	array( 'champion', day_from_today( 100 ), 'a Champion with a hundred days left', null ),
	array( 'champion', day_from_today( 31 ), 'a Champion with thirty-one days left', null ),
	array( 'champion', day_from_today( 30 ), 'a Champion with thirty days left', array( 'term', 'November 5, 2026' ) ),
	array( 'champion', day_from_today( 1 ), 'a Champion with one day left', array( 'term', 'October 7, 2026' ) ),
	array( 'champion', day_from_today( 0 ), 'a Champion on its term\'s last day', array( 'term', 'October 6, 2026' ) ),
	array( 'champion', day_from_today( -1 ), 'a Champion whose term ended yesterday', array( 'ended', 'October 5, 2026' ) ),
	array( 'backer', day_from_today( -200 ), 'a Backer whose term ended in March', array( 'ended', 'March 20, 2026' ) ),
	array( 'backer', '', 'a Backer with no term', null ),
	array( '', '', 'a Friend', null ),
	array( '', day_from_today( 10 ), 'a Friend with a date left on its row, no term to speak for', null ),
);
foreach ( $tier_cases as $case ) {
	WPCPM_Sponsors_Index::patch( $A, array( 'tier' => $case[0], 'tier_until' => $case[1] ) );
	$tier_page = $D::render();
	ck( 'the term: ' . $case[2] . ( null === $case[3] ? ', no row, and "Nothing waits for you."' : ', one row naming ' . $case[3][1] . ', alone, counted beside Your sponsorship' ), array(
		waiting_of( $tier_page ),
		rows_without_card( $tier_page ),
		menu_counts( $tier_page ),
	), array(
		null === $case[3] ? nothing_waits() : waiting_section( array( wait_row( $case[3][0], 1, $case[3][1] ) ) ),
		array(),
		null === $case[3] ? array() : array( 'Overview' => '1 waits (red)', 'Your sponsorship' => '1 waits (red)' ),
	) );
}
// The Sponsorship card's own note says the same of the same term: the thirty-day note is drawn exactly
// where the row is, for a running term.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion', 'tier_until' => day_from_today( 30 ) ) );
$thirty      = in_view( 'sponsorship' );
$thirty_rows = waits( $A );
WPCPM_Sponsors_Index::patch( $A, array( 'tier_until' => day_from_today( 31 ) ) );
$thirty_one      = in_view( 'sponsorship' );
$thirty_one_rows = waits( $A );
ck( 'the row is there exactly where the Sponsorship card says how many days are left: at thirty days, not at thirty-one', array(
	$thirty_rows,
	false !== strpos( $thirty, 'Your term ends on November 5, 2026, in 30 days.' ),
	$thirty_one_rows,
	false !== strpos( $thirty_one, 'Your term ends on' ),
), array( array( 'sponsorship 1' ), true, array(), false ) );

// The term is a waiting row among the others, first: a term with a hundred days left, and an offer
// returned, is the offer alone; with twenty days left the term, then the offer.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion', 'tier_until' => day_from_today( 100 ) ) );
$GLOBALS['offers_waiting'] = array( $A => offers_waiting( 1 ) );
$mixed = $D::render();
WPCPM_Sponsors_Index::patch( $A, array( 'tier_until' => day_from_today( 20 ) ) );
$mixed_waiting = $D::render();
ck( 'the term is no row with a hundred days left, so the offer waits alone; in the last thirty days the term comes first, then the offer; no "Nothing waits for you." in either', array( waiting_of( $mixed ), waiting_of( $mixed_waiting ) ), array(
	waiting_section( array( wait_row( 'returned' ) ) ),
	waiting_section( array( wait_row( 'term', 1, 'October 26, 2026' ), wait_row( 'returned' ) ) ),
) );
$GLOBALS['offers_waiting'] = array();

// The figures that were tiles are no rows, though every one of them is there to be shown: a Champion with
// an event coming up and two people on it, a final report and a draft, which is the program's to finish,
// and three mentors. Nothing waits; each figure shows on its card's line, on the view that draws the card.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion', 'tier_until' => day_from_today( 100 ) ) );
$next_event   = sponsor_event( $A, 'approved', strtotime( '2026-10-20 15:00:00 UTC' ), 60, 'Mango "live" & friends', array( 42, 43 ) );
$glance_final = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Report::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Mango Example, October 2025 to September 2026' ) );
update_post_meta( $glance_final, WPCPM_Sponsor_Report::META_SPONSOR, $A );
update_post_meta( $glance_final, WPCPM_Sponsor_Report::META_UNTIL, '2026-09-30' );
update_post_meta( $glance_final, WPCPM_Sponsor_Report::META_STATE, WPCPM_Sponsor_Report::STATE_FINAL );
$glance_draft = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Report::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Mango Example, October 2026 to September 2027' ) );
update_post_meta( $glance_draft, WPCPM_Sponsor_Report::META_SPONSOR, $A );
update_post_meta( $glance_draft, WPCPM_Sponsor_Report::META_UNTIL, '2027-09-30' );
$champion      = $D::render();
$champion_main = main_of( $champion );
ck( 'a Champion with an event coming up, a final report and three mentors, and nothing waiting: "Nothing waits for you.", and the Overview holds none of the event, the people on it, the yearly reports or the Events card\'s words', array(
	waiting_of( $champion ),
	array_map( static function ( $words ) use ( $champion_main ) { return false === stripos( $champion_main, $words ); }, array( 'live&quot; &amp; friends', 'Oct 20', 'October 20', 'joined', 'yearly report', 'no event planned', 'offers live' ) ),
), array( nothing_waits(), array( true, true, true, true, true, true, true ) ) );
$champion_sponsorship = in_view( 'sponsorship' );
ck( 'each figure the strip once showed stays on its card\'s line, on the view that draws the card: the event and the yearly reports on Your sponsorship, the mentors on Mentors to sponsor', array( page_row( $champion_sponsorship, 'events' ), page_row( $champion_sponsorship, 'reports' ), page_row( in_view( 'mentors' ), 'mentors' ) ), array( 'Mango &quot;live&quot; &amp; friends on October 20, 2026, 2 joined', '1 yearly report, the latest for October 2025 to September 2026', '3 mentors' ) );

// The event row: one when the sponsor's latest event was returned with a note (the event store's
// returned_note()) and the Events card can show it, which it does only inside the planner's plan form,
// drawn only when the planner's allowance is '' (WPCPM_Sponsor_Event_Planner::allowance(), which implies
// the card's own draws_events() and the launch switch). The row opens the Events card on Your sponsorship.
// The event the sponsor planned is canceled by the program with a note, and the planner stands it above
// the form. Another event, canceled so that it counts for nothing, is held back to be approved in the past
// below, to spend the term; it is made before the returned one, which must stay the sponsor's latest.
$GLOBALS['pmeta'][ $next_event ][ WPCPM_Sponsor_Event::META_STATE ] = WPCPM_Sponsor_Event::STATE_CANCELLED;
$past_event     = sponsor_event( $A, WPCPM_Sponsor_Event::STATE_CANCELLED, strtotime( '2026-09-20 15:00:00 UTC' ), 60, 'Mango last month' );
$returned_event = sponsor_event( $A, WPCPM_Sponsor_Event::STATE_CANCELLED, strtotime( '2026-11-02 15:00:00 UTC' ), 60, 'Mango after the term' );
$GLOBALS['pmeta'][ $returned_event ][ WPCPM_Sponsor_Event::META_DECISION ] = array( 'by' => 1, 'at' => 0, 'note' => 'Please add an agenda.' );
$event_page  = $D::render();
$events_card = card_of( in_view( 'sponsorship' ), 'events' );
ck( 'the latest event returned with a note: one row that says so, linked to the Events card on Your sponsorship, which is drawn and says the same note, and counted beside Your sponsorship', array(
	WPCPM_Sponsor_Event::returned_note( $A ),
	waiting_of( $event_page ),
	rows_without_card( $event_page ),
	false !== strpos( $events_card, 'Your last plan was returned: Please add an agenda.' ),
	menu_counts( $event_page ),
), array( 'Please add an agenda.', waiting_section( array( wait_row( 'event' ) ) ), array(), true, array( 'Overview' => '1 waits (red)', 'Your sponsorship' => '1 waits (red)' ) ) );
// The note belongs to the running term (1.123.40). The plan returned above is dated November 2, in the
// term that began on October 1, the day after the last day of the final report (`WPCPM_Sponsor_Event::term_from()`).
// Dated September 25 instead it is the term before's, and a renewal has left it behind: neither the
// Events card nor the Overview says it came back, and the running term's own events, a canceled one with
// no note among them, are the latest there is. With no end recorded for a term before, nothing says
// a renewal happened, and the same plan is the sponsor's latest as it always was: its note and its row.
$returned_start = $GLOBALS['pmeta'][ $returned_event ][ WPCPM_Sponsor_Event::META_START ];
$returned_end   = $GLOBALS['pmeta'][ $returned_event ][ WPCPM_Sponsor_Event::META_END ];
$GLOBALS['pmeta'][ $returned_event ][ WPCPM_Sponsor_Event::META_START ] = (int) strtotime( '2026-09-25 15:00:00 UTC' );
$GLOBALS['pmeta'][ $returned_event ][ WPCPM_Sponsor_Event::META_END ]   = (int) strtotime( '2026-09-25 16:00:00 UTC' );
$renewed_page = $D::render();
$renewed_card = card_of( in_view( 'sponsorship' ), 'events' );
$kept_ends    = array();
foreach ( array( $glance_final ) as $end_id ) {
	$kept_ends[ $end_id ] = $GLOBALS['pmeta'][ $end_id ][ WPCPM_Sponsor_Report::META_UNTIL ];
	$GLOBALS['pmeta'][ $end_id ][ WPCPM_Sponsor_Report::META_UNTIL ] = '';
}
$first_term_page = $D::render();
$first_term_card = card_of( in_view( 'sponsorship' ), 'events' );
foreach ( $kept_ends as $end_id => $end_day ) { $GLOBALS['pmeta'][ $end_id ][ WPCPM_Sponsor_Report::META_UNTIL ] = $end_day; }
ck( 'renewed, a plan returned in the term before is no news of the term that runs: no note, no line above the plan form and no row, though the plan form is drawn', array(
	WPCPM_Sponsor_Event::term_from( WPCPM_Sponsors_Index::row( $A ) ),
	WPCPM_Sponsor_Event::returned_note( $A ),
	false !== strpos( $renewed_card, 'Plan your co-hosted event' ),
	false !== strpos( $renewed_card, 'Your last plan was returned' ),
	waiting_of( $renewed_page ),
	menu_counts( $renewed_page ),
), array( '2026-10-01', '', true, false, nothing_waits(), array() ) );
ck( 'and the same plan with no end recorded for a term before is the sponsor\'s latest event as it always was: its note above the plan form, and the row', array(
	false !== strpos( $first_term_card, 'Your last plan was returned: Please add an agenda.' ),
	waiting_of( $first_term_page ),
	rows_without_card( $first_term_page ),
), array( true, waiting_section( array( wait_row( 'event' ) ) ), array() ) );
$GLOBALS['pmeta'][ $returned_event ][ WPCPM_Sponsor_Event::META_START ] = $returned_start;
$GLOBALS['pmeta'][ $returned_event ][ WPCPM_Sponsor_Event::META_END ]   = $returned_end;
ck( 'put back in its own term, the plan is the running term\'s latest again: its note shows', WPCPM_Sponsor_Event::returned_note( $A ), 'Please add an agenda.' );

// A Backer's tier has no events, so with nothing coming the card is not drawn and the row is not, though
// the note is still there to read.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'backer' ) );
$backer = $D::render();
ck( 'the event row is drawn only where the Events card shows the note: not for a Backer with nothing coming, though the note is held', array(
	WPCPM_Sponsor_Event::returned_note( $A ),
	waiting_of( $backer ),
	strpos( in_view( 'sponsorship' ), '<details id="wpcpm-sponsor-events"' ),
	strpos( $backer, '#wpcpm-sponsor-events' ),
), array( 'Please add an agenda.', nothing_waits(), false, false ) );
// An event of its own still to take place draws the Events card for any tier (the card is where the
// sponsor reads that the event is no longer offered, or cancels it), and the card's own test,
// draws_events(), counts it. The note is printed only inside the plan form, which that card does not draw
// for a Backer (no planner) nor for a Champion that holds an event open (one_open): the row is not drawn
// either, the note being held, since it would say a return the card does not.
$GLOBALS['pmeta'][ $next_event ][ WPCPM_Sponsor_Event::META_STATE ] = WPCPM_Sponsor_Event::STATE_APPROVED;
$backer_event = $D::render();
$backer_card  = card_of( in_view( 'sponsorship' ), 'events' );
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion' ) );
$open_event = $D::render();
$open_card  = card_of( in_view( 'sponsorship' ), 'events' );
$open_row   = WPCPM_Sponsors_Index::row( $A );
ck( 'an event still to take place draws the Events card but not the plan form, so no row: not for a Backer (no planner), nor for a Champion with one open, though the card\'s own test counts the event and the note is held', array(
	WPCPM_Sponsor_Tier_Card::draws_events( $A, $open_row, 'backer' ),
	WPCPM_Sponsor_Event_Planner::allowance( $A, $open_row, 'backer' ),
	false !== strpos( $backer_card, 'Mango' ) || false !== strpos( $backer_card, 'wpcpm-sevent__own' ),
	false !== strpos( $backer_card, 'Your last plan was returned' ),
	waiting_of( $backer_event ),
	WPCPM_Sponsor_Tier_Card::draws_events( $A, $open_row, 'champion' ),
	WPCPM_Sponsor_Event_Planner::allowance( $A, $open_row, 'champion' ),
	false !== strpos( $open_card, 'wpcpm-sevent__own' ),
	false !== strpos( $open_card, 'Your last plan was returned' ),
	waiting_of( $open_event ),
), array(
	true,
	'no_tier',
	true,
	false,
	nothing_waits(),
	true,
	'one_open',
	true,
	false,
	nothing_waits(),
) );
// With the switch off the tier card draws nothing, the Events card with it, though the card's own test
// counts the event still to take place: the row is not drawn either, and no link points at nothing.
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$events_off      = $D::render();
$events_off_view = in_view( 'sponsorship' );
$counts_anyway   = WPCPM_Sponsor_Tier_Card::draws_events( $A, WPCPM_Sponsors_Index::row( $A ), 'champion' );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
ck( 'with the switch off the Events card is drawn on no view and the row is not, though the card\'s own test counts the event', array( $counts_anyway, waiting_of( $events_off ), view_drawn( $events_off_view ), strpos( $events_off . $events_off_view, '<details id="wpcpm-sponsor-events"' ) ), array( true, nothing_waits(), 'overview', false ) );
$GLOBALS['pmeta'][ $next_event ][ WPCPM_Sponsor_Event::META_STATE ] = WPCPM_Sponsor_Event::STATE_CANCELLED;
// A Champion whose term's event has taken place holds the planner (the card is drawn, and says the term
// is spent) without the plan form that carries the note: the term is used, and so no row.
$GLOBALS['pmeta'][ $past_event ][ WPCPM_Sponsor_Event::META_STATE ] = WPCPM_Sponsor_Event::STATE_APPROVED;
$used_page      = $D::render();
$used_card      = card_of( in_view( 'sponsorship' ), 'events' );
$used_allowance = WPCPM_Sponsor_Event_Planner::allowance( $A, WPCPM_Sponsors_Index::row( $A ), 'champion' );
$used_note      = WPCPM_Sponsor_Event::returned_note( $A );
$GLOBALS['pmeta'][ $past_event ][ WPCPM_Sponsor_Event::META_STATE ] = WPCPM_Sponsor_Event::STATE_CANCELLED;
ck( 'a Champion whose term is used (its event took place) has the Events card and no plan form, so no row, though the card\'s own test is true and the note is held', array(
	$used_allowance,
	$used_note,
	'' !== $used_card,
	false !== strpos( $used_card, 'Your last plan was returned' ),
	waiting_of( $used_page ),
), array( 'term_used', 'Please add an agenda.', true, false, nothing_waits() ) );
// A plan made after the return puts the note away, and the row goes with it.
$replanned = sponsor_event( $A, WPCPM_Sponsor_Event::STATE_PLANNED, strtotime( '2026-11-20 15:00:00 UTC' ), 60, 'Mango again' );
$replan    = $D::render();
ck( 'a new plan puts the returned note away, and the row with it', array( WPCPM_Sponsor_Event::returned_note( $A ), false !== strpos( waiting_of( $replan ), 'Your event plan was returned' ) ), array( '', false ) );
// The new plan is returned in its turn, so the sponsor's latest event holds a note again.
$GLOBALS['pmeta'][ $replanned ][ WPCPM_Sponsor_Event::META_STATE ] = WPCPM_Sponsor_Event::STATE_CANCELLED;
$GLOBALS['pmeta'][ $replanned ][ WPCPM_Sponsor_Event::META_DECISION ] = array( 'by' => 1, 'at' => 0, 'note' => 'Please add an agenda.' );

// All eight at once, in the order the owner approved: the term (in its last thirty days), the offers
// returned, running low and not switched on yet, the post, the event, the agreement and the profile.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion', 'tier_until' => day_from_today( 20 ), 'contact_email' => '' ) );
$GLOBALS['offers_waiting']    = array( $A => offers_waiting( 1, 1, 1 ) );
$GLOBALS['posts_drawn']       = array( $A => true );
$GLOBALS['posts_returned']    = array( $A => 1 );
$GLOBALS['agreement_waiting'] = array( $A => 'revoked' );
$eight        = $D::render();
$eight_rows   = array(
	wait_row( 'term', 1, 'October 26, 2026' ),
	wait_row( 'returned' ),
	wait_row( 'low' ),
	wait_row( 'draft' ),
	wait_row( 'posts' ),
	wait_row( 'event' ),
	wait_row( 'agreement revoked' ),
	wait_row( 'profile' ),
);
ck( 'all eight at once, in the approved order, each a row that lands on a card its view draws, after the figures, and no "Nothing waits for you."', array( waiting_of( $eight ), rows_without_card( $eight ), waits( $A ), in_order( $eight, array( '</header>', '<section class="wpcpm-figures', '<section class="wpcpm-sponsor__waiting"' ) ), strpos( $eight, 'Nothing waits for you' ) ), array( waiting_section( $eight_rows ), array(), array( 'sponsorship 1', 'offers 1', 'offers 1', 'offers 1', 'posts 1', 'events 1', 'agreement 1', 'profile 1' ), true, false ) );
// What waits is a status tag in words on a plain row, never a colored edge (the owner, 10 November 2026):
// each row carries the red tag, and no row a class of its own that would set it apart; nothing of the
// retired strip's tiles is printed. The tag is the frame's, a rule of its sheet, the row the page's own.
$eight_views = implode( '', every_view() );
ck( 'every row of the eight carries the red status tag in words, and no row a class of its own; nothing of the strip\'s tiles is on any view, and the tag and the row are dressed by the frame\'s sheet and the page\'s', array(
	substr_count( waiting_of( $eight ), '<span class="wpcpm-status wpcpm-status--red">Waits</span>' ),
	preg_match_all( '#<div class="wpcpm-sponsor__wait">#', $eight ),
	preg_match_all( '#class="wpcpm-sponsor__wait [^"]*"#', $eight_views ),
	strpos( $eight_views, 'wpcpm-attention' ),
	isset( sheet_rules( 'dashboard-frame.css' )['|.wpcpm-status--red'] ),
	isset( sheet_rules( 'sponsor.css' )['|.wpcpm-sponsor__wait'] ),
), array( 8, 8, 0, false, true, true ) );
ck( 'and the menu counts what waits in each view: eight beside Overview, the term and the event beside Your sponsorship, the three offers and the post beside Offers, the profile beside Your company and the agreement beside Agreement, each in red, on every view alike', array( menu_counts( $eight ), menu_counts( in_view( 'company' ) ) === menu_counts( $eight ) ), array( array( 'Overview' => '8 wait (red)', 'Your sponsorship' => '2 wait (red)', 'Offers' => '4 wait (red)', 'Your company' => '1 waits (red)', 'Agreement' => '1 waits (red)' ), true ) );
// Each view draws the cards VIEW_CARDS lists for it, which is what card_url() routes by, so a link to a
// card always lands where the card is: every card here, the Usage card's view aside, whose card this
// suite does not load.
$drawn_by_view = array();
foreach ( the_views() as $view ) { $drawn_by_view[ $view ] = view_cards( in_view( $view ) ); }
ck( 'each view draws only the cards VIEW_CARDS lists for it, in that order, so every card\'s address is the view that draws it', $drawn_by_view, array( 'overview' => array(), 'sponsorship' => array( 'sponsorship', 'events', 'reports', 'badge' ), 'offers' => array( 'offers', 'posts' ), 'reached' => array(), 'mentors' => array( 'mentors', 'looking' ), 'company' => array( 'profile', 'people' ), 'agreement' => array( 'agreement' ) ) );
ck( 'and with every card drawn no student is named on any view', preg_match( '/Student(?! Report Card|s reached)/', $eight_views ), 0 );

// What waits is the sponsor's, not the viewer's: a program manager reading the page through the switcher
// reads the same rows, each link keeping the sponsor, and so does New offer.
$GLOBALS['uid'] = 1;
$GLOBALS['get'] = array( WPCPM_Sponsor_Roster::ARG_VIEW => $A );
$as_manager     = $D::render();
$manager_rows   = rows_without_card( $as_manager );
$manager_views  = implode( '', every_view() );
$GLOBALS['uid'] = 5;
$GLOBALS['get'] = array();
ck( 'a program manager viewing the sponsor through the switcher reads the same rows and counts, each link carrying the sponsor and landing on its card, and New offer too', array(
	false !== strpos( $as_manager, 'wpcpm-dashboard__switcher' ),
	waiting_of( $as_manager ),
	$manager_rows,
	menu_counts( $as_manager ),
	false !== strpos( heads_of( $as_manager ), 'href="' . $page . '?wpcpm_view=offers&' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A . '#wpcpm-offer-new">New offer</a>' ),
	preg_match( '/Student(?! Report Card|s reached)/', $manager_views ),
), array( true, waiting_section( array(
	wait_row( 'term', 1, 'October 26, 2026', $A ),
	wait_row( 'returned', 1, '', $A ),
	wait_row( 'low', 1, '', $A ),
	wait_row( 'draft', 1, '', $A ),
	wait_row( 'posts', 1, '', $A ),
	wait_row( 'event', 1, '', $A ),
	wait_row( 'agreement revoked', 1, '', $A ),
	wait_row( 'profile', 1, '', $A ),
) ), array(), menu_counts( $eight ), true, 0 ) );

// The helpers that drew the strip are gone with it, the event tile and its title's cut among them, as are
// the identity header and the help that followed the strip.
$retired = new ReflectionClass( 'WPCPM_Sponsors_Dashboard' );
ck( 'the helpers of the retired strip, header and help are gone: the strip, its tiles, the event tile, the title\'s cut and its length, the identity, the help and its contact block', array( $retired->hasMethod( 'render_glance' ), $retired->hasMethod( 'glance_tiles' ), $retired->hasMethod( 'glance_event_tile' ), $retired->hasMethod( 'glance_title' ), $retired->hasConstant( 'GLANCE_TITLE' ), $retired->hasMethod( 'render_identity' ), $retired->hasMethod( 'render_help' ), $retired->hasMethod( 'contact_block' ) ), array( false, false, false, false, false, false, false, false ) );

// With no final report, the draft left the program's to finish, there is no Reports card; the fixtures
// of this section go, and the sponsor with them.
unset( $GLOBALS['posts'][ $glance_final ], $GLOBALS['pmeta'][ $glance_final ], $GLOBALS['posts'][ $glance_draft ], $GLOBALS['pmeta'][ $glance_draft ] );
$GLOBALS['offers_waiting']    = array();
$GLOBALS['posts_drawn']       = array();
$GLOBALS['posts_returned']    = array();
$GLOBALS['agreement_waiting'] = array();
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => '', 'tier_until' => '', 'mentors' => array(), 'contact_email' => $A_email ) );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );

echo "\n=== The frame and its menu (1.123.78) ===\n";
// The page is drawn in the dashboards' frame, as the Administrator Dashboard is: the menu down the side,
// a link for each view, a count in words beside what waits, then the help; the main area holds the page's
// own box. From here the switch is on, A is a Friend with nothing waiting, as the cleanup above left it,
// on the pinned day, and its manager is a program manager.
$GLOBALS['uid']       = 5;
$GLOBALS['get']       = array();
$GLOBALS['manage']    = array( 1 );
$GLOBALS['resources'] = array();
/**
 * The frame's items in a piece of a page, by each item's words, in order: its address, whether it is
 * marked current by its class (`marked`) and by `aria-current="page"` (`current`), and the words of its
 * badge ('' for none) and their tone ('' for a plain count).
 *
 * @param string $fragment Markup holding the items.
 * @return array[]
 */
function frame_items( $fragment ) {
	preg_match_all( '#<li><a class="wpcpm-frame__item( is-current)?" href="([^"]*)"( aria-current="page")?>.*?<span class="wpcpm-frame__label">([^<]*)(?:<span class="screen-reader-text">, [^<]*</span>)?</span>(?:<span aria-hidden="true" class="wpcpm-frame__badge( wpcpm-frame__badge--[a-z]+)?">([^<]*)</span>)?.*?</a></li>#s', (string) $fragment, $found, PREG_SET_ORDER );
	$items = array();
	foreach ( $found as $item ) {
		$items[ html_entity_decode( $item[4], ENT_QUOTES ) ] = array(
			'url'     => $item[2],
			'marked'  => '' !== $item[1],
			'current' => '' !== $item[3],
			'badge'   => isset( $item[6] ) ? $item[6] : '',
			'tone'    => isset( $item[5] ) && '' !== $item[5] ? substr( $item[5], strlen( ' wpcpm-frame__badge--' ) ) : '',
		);
	}
	return $items;
}
/**
 * The frame's whole menu as a page draws it, the views and the help.
 *
 * @param string $html The page.
 * @return array[]
 */
function menu_items( $html ) { return frame_items( (string) strstr( (string) strstr( (string) $html, '<nav class="wpcpm-frame__menu"' ), '</nav>', true ) ); }
/**
 * The menu's Help items alone.
 *
 * @param string $html The page.
 * @return array[]
 */
function help_items( $html ) { return frame_items( (string) strstr( (string) strstr( (string) $html, '<div class="wpcpm-frame__group wpcpm-frame__group--help">' ), '</nav>', true ) ); }
/**
 * The counts the menu says beside its items, by the item's words, each with its tone after it.
 *
 * @param string $html The page.
 * @return array<string, string>
 */
function menu_counts( $html ) {
	$counts = array();
	foreach ( menu_items( $html ) as $label => $item ) {
		if ( '' !== $item['badge'] ) { $counts[ $label ] = $item['badge'] . ( '' !== $item['tone'] ? ' (' . $item['tone'] . ')' : '' ); }
	}
	return $counts;
}
$view_labels = array( 'overview' => 'Overview', 'sponsorship' => 'Your sponsorship', 'offers' => 'Offers', 'reached' => 'Students reached', 'mentors' => 'Mentors to sponsor', 'company' => 'Your company', 'agreement' => 'Agreement' );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$framed = every_view();
$frames = array();
foreach ( $framed as $view => $html ) {
	$frames[ $view ] = array( 0 === strpos( $html, '<div class="wpcpm-frame wpcpm-frame--sponsor" data-wpcpm-frame>' ), view_drawn( $html ), substr_count( $html, '<div class="wpcpm-frame__main"><div class="wpcpm-dashboard wpcpm-sponsor wpcpm-sponsor--' . $view . '">' ), substr( $html, -strlen( '</div></div></div></div>' ) ) );
}
ck( 'every view is drawn in the frame, the sponsor\'s, its own box once in the main area, named for the view, and closed with the main area, the body and the frame', $frames, array_map( static function ( $view ) { return array( true, $view, 1, '</div></div></div></div>' ); }, array_combine( the_views(), the_views() ) ) );
ck( 'the member\'s menu lists the views in the concept\'s order, then the help: Your sponsorship after Overview only while the tiers are launched, and Write to the program under Help', array( array_keys( menu_items( $framed['overview'] ) ), array_keys( menu_items( $views_off['overview'] ) ), array_keys( help_items( $framed['overview'] ) ) ), array(
	array( 'Overview', 'Your sponsorship', 'Offers', 'Students reached', 'Mentors to sponsor', 'Your company', 'Agreement', 'Write to the program' ),
	array( 'Overview', 'Offers', 'Students reached', 'Mentors to sponsor', 'Your company', 'Agreement', 'Write to the program' ),
	array( 'Write to the program' ),
) );
$marked = array();
foreach ( $framed as $view => $html ) {
	$items           = menu_items( $html );
	$marked[ $view ] = array(
		array_keys( array_filter( $items, static function ( $item ) { return $item['current']; } ) ),
		array_keys( array_filter( $items, static function ( $item ) { return $item['marked']; } ) ),
		substr_count( $html, 'aria-current=' ),
	);
}
ck( 'the view drawn is the one item marked current, by its class and by aria-current="page", on every view', $marked, array_map( static function ( $label ) { return array( array( $label ), array( $label ), 1 ); }, $view_labels ) );
$member_urls = array_map( static function ( $item ) { return $item['url']; }, menu_items( $framed['overview'] ) );
ck( 'each view is at the page\'s own address, the Overview at the page itself and the others with their view, as view_url() writes them', $member_urls, array(
	'Overview'             => $page,
	'Your sponsorship'     => $page . '?wpcpm_view=sponsorship',
	'Offers'               => $page . '?wpcpm_view=offers',
	'Students reached'     => $page . '?wpcpm_view=reached',
	'Mentors to sponsor'   => $page . '?wpcpm_view=mentors',
	'Your company'         => $page . '?wpcpm_view=company',
	'Agreement'            => $page . '?wpcpm_view=agreement',
	'Write to the program' => 'mailto:administrator@example.test',
) );
// An Administrator moving between views keeps the sponsor they look at: every view's link carries it.
$GLOBALS['uid'] = 1;
$GLOBALS['get'] = array( WPCPM_Sponsor_Roster::ARG_VIEW => $A );
$manager_menu   = menu_items( in_view( 'mentors' ) );
$GLOBALS['uid'] = 5;
$GLOBALS['get'] = array();
ck( 'an Administrator\'s menu carries the sponsor being viewed on every view\'s link, so moving between views keeps it, and marks the view drawn', array( array_map( static function ( $item ) { return $item['url']; }, $manager_menu ), $manager_menu['Mentors to sponsor']['current'] ), array( array(
	'Overview'             => $page . '?wpcpm_sponsor_view=' . $A,
	'Your sponsorship'     => $page . '?wpcpm_view=sponsorship&wpcpm_sponsor_view=' . $A,
	'Offers'               => $page . '?wpcpm_view=offers&wpcpm_sponsor_view=' . $A,
	'Students reached'     => $page . '?wpcpm_view=reached&wpcpm_sponsor_view=' . $A,
	'Mentors to sponsor'   => $page . '?wpcpm_view=mentors&wpcpm_sponsor_view=' . $A,
	'Your company'         => $page . '?wpcpm_view=company&wpcpm_sponsor_view=' . $A,
	'Agreement'            => $page . '?wpcpm_view=agreement&wpcpm_sponsor_view=' . $A,
	'Write to the program' => 'mailto:administrator@example.test',
), true ) );
// The page's name is its one H1 only while the theme draws the page as a bare canvas for the frame
// (`add_theme_support( 'wpcpm-dashboard-frame', array( 'sponsor' ) )`); otherwise the theme's own template
// prints the title, and the frame's bar holds the Menu button alone. The H1 opens every view's head, the
// view's title under it, and names the page once: the menu keeps the name as its label for a screen reader.
$GLOBALS['theme_support'] = array( WPCPM_Dashboard_Frame::THEME_SUPPORT => array( array( 'sponsor' ) ) );
$owned                    = every_view();
$GLOBALS['theme_support'] = array( WPCPM_Dashboard_Frame::THEME_SUPPORT => array( array( 'administrator' ) ) );
$others_owned             = implode( '', every_view() );
unset( $GLOBALS['theme_support'] );
$owned_heads = array();
foreach ( $owned as $view => $html ) {
	$owned_heads[ $view ] = array( substr_count( $html, '<h1' ), false !== strpos( $html, '<div class="wpcpm-head__copy"><h1 class="wpcpm-head__eyebrow">Sponsor Dashboard</h1><h2 class="wpcpm-head__title">' . ( 'overview' === $view ? 'Mango Example' : view_head( $html )['title'] ) . '</h2>' ), substr_count( $html, '>Sponsor Dashboard<' ), false !== strpos( $html, '</script><div class="wpcpm-frame__bar"><button class="wpcpm-frame__menu-button"' ) );
}
ck( 'while the theme leaves the page to the frame, the frame draws its full bar and every view\'s head opens with the page\'s name as the page\'s one H1, the view\'s title under it, the name shown nowhere else', $owned_heads, array_map( static function () { return array( 1, true, 1, true ); }, $view_labels ) );
ck( 'otherwise, and while the theme leaves only another dashboard to the frame, no view prints an H1 and the frame\'s bar is slim', array( substr_count( implode( '', $framed ), '<h1' ), substr_count( $others_owned, '<h1' ), substr_count( implode( '', $framed ), '<div class="wpcpm-frame__bar wpcpm-frame__bar--slim"><button class="wpcpm-frame__menu-button"' ), substr_count( $others_owned, '<div class="wpcpm-frame__bar"><button' ) ), array( 0, 0, count( $framed ), 0 ) );
ck( 'and the menu names the page for a screen reader on every view', substr_count( implode( '', $framed ), '<nav class="wpcpm-frame__menu" id="wpcpm-frame-menu" aria-label="Sponsor Dashboard">' ), count( $framed ) );
// With the day the company became a sponsor on its record, the H1 says so after the name, as the concept
// draws it; a day that is no day says nothing.
$GLOBALS['theme_support'] = array( WPCPM_Dashboard_Frame::THEME_SUPPORT => array( array( 'sponsor' ) ) );
WPCPM_Sponsors_Index::patch( $A, array( 'created' => '2026-03-14 09:30:00' ) );
$since = in_view( 'company' );
WPCPM_Sponsors_Index::patch( $A, array( 'created' => '2026-02-30' ) );
$no_since = $D::render();
WPCPM_Sponsors_Index::patch( $A, array( 'created' => '' ) );
unset( $GLOBALS['theme_support'] );
ck( 'the H1 names the month the company became a sponsor after the page\'s name, and a day that is no day names none', array( false !== strpos( $since, '<h1 class="wpcpm-head__eyebrow">Sponsor Dashboard <span class="wpcpm-sponsor__since">&middot; sponsor since March 2026</span></h1><h2 class="wpcpm-head__title">Your company</h2>' ), false !== strpos( $no_since, '<h1 class="wpcpm-head__eyebrow">Sponsor Dashboard</h1>' ), strpos( $no_since, 'sponsor since' ) ), array( true, true, false ) );

// Help (1.123.78): the Sponsor guide while its page is published at /sponsor-guide/, and Write to the
// program while the company's contact at the program is a program manager with an address. The Resources
// block the help once was is gone, and the program contact block with it: no name, no booking link.
$guide_page              = new WP_Post();
$guide_page->ID          = 777;
$guide_page->post_name   = 'sponsor-guide';
$guide_page->post_title  = 'Sponsor guide';
$guide_page->post_status = 'draft';
$GLOBALS['posts'][777]   = $guide_page;
$draft_help              = help_items( $D::render() );
$guide_page->post_status = 'publish';
$guide_views             = every_view();
$guide_help              = help_items( $guide_views['overview'] );
ck( 'the Sponsor guide is under Help only while its page is published, before Write to the program, at the page\'s address, on every view and marked current on none', array( array_keys( $draft_help ), $guide_help, array_values( array_map( static function ( $html ) { return array_keys( help_items( $html ) ); }, $guide_views ) ) ), array(
	array( 'Write to the program' ),
	array(
		'Sponsor guide'        => array( 'url' => 'https://example.test/sponsor-guide/', 'marked' => false, 'current' => false, 'badge' => '', 'tone' => '' ),
		'Write to the program' => array( 'url' => 'mailto:administrator@example.test', 'marked' => false, 'current' => false, 'badge' => '', 'tone' => '' ),
	),
	array_fill( 0, count( the_views() ), array( 'Sponsor guide', 'Write to the program' ) ),
) );
// An assigned Administrator who has since lost the program's capability (Review Focus 5): the row keeps
// the id, `manager_of()` answers null, and the menu draws no Write to the program, the guide alone under
// Help; the tier card's Talk to your program contact is still on Your sponsorship, which mails whoever the
// interests road falls back to.
$GLOBALS['manage'] = array();
$demoted_views     = every_view();
$GLOBALS['manage'] = array( 1 );
ck( 'an assigned Administrator without the program\'s capability draws no Write to the program: the guide is Help\'s one item, and no view names the manager, links to their address or prints their booking link', array(
	array_keys( help_items( $demoted_views['overview'] ) ),
	strpos( implode( '', $demoted_views ), 'mailto:' ),
	strpos( implode( '', $demoted_views ), 'Program Administrator' ),
	strpos( implode( '', $demoted_views ), 'calendly.example/administrator' ),
	false !== strpos( $demoted_views['sponsorship'], 'Talk to your program contact' ),
), array( array( 'Sponsor guide' ), false, false, false, true ) );
// An assigned id whose account is gone is nobody's contact either; with no guide published either, the
// menu has no Help at all, rather than an empty heading.
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => '9999' ) );
$gone_page = $D::render();
unset( $GLOBALS['posts'][777] );
$gone_bare = $D::render();
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => '1' ) );
ck( 'and so is an assigned id whose account no longer exists; with no guide either, the menu has no Help at all', array( array_keys( help_items( $gone_page ) ), help_items( $gone_bare ), strpos( $gone_bare, 'wpcpm-frame__group--help' ), strpos( $gone_bare, '>Help<' ) ), array( array( 'Sponsor guide' ), array(), false, false ) );
ck( 'and neither the program contact block\'s sentence, old or new, is on any view', strpos( implode( '', $framed ) . implode( '', $guide_views ), 'They look after' ), false );

// Program updates: after the views, the newest update a sponsor may read (`WPCPM_Updates::posts()` with
// the sponsor audience), only while there is one, and never before the updates exist on the site.
$no_updates_class = array_keys( menu_items( $D::render() ) );
if ( ! class_exists( 'WPCPM_Updates' ) ) {
	/** The program's updates, as far as the menu asks them: the newest posts for an audience, newest first. */
	class WPCPM_Updates {
		public static function posts( $max = 5, $audience = '' ) { return 'sponsor' === $audience ? array_slice( (array) ( $GLOBALS['sponsor_updates'] ?? array() ), 0, (int) $max ) : array(); }
	}
}
$GLOBALS['sponsor_updates'] = array();
$no_updates                 = array_keys( menu_items( $D::render() ) );
$update_post                = new WP_Post();
$update_post->ID            = 778;
$update_post->post_type     = 'post';
$update_post->post_name     = 'a-word-for-sponsors';
$GLOBALS['posts'][778]      = $update_post;
$GLOBALS['sponsor_updates'] = array( $update_post );
$with_updates               = menu_items( $D::render() );
$GLOBALS['sponsor_updates'] = array();
unset( $GLOBALS['posts'][778] );
ck( 'Program updates follows the views only while a sponsor may read an update, at the newest one\'s address and not marked current, and not at all before the updates exist', array( $no_updates_class, $no_updates, array_keys( $with_updates ), $with_updates['Program updates'] ?? null ), array(
	array( 'Overview', 'Your sponsorship', 'Offers', 'Students reached', 'Mentors to sponsor', 'Your company', 'Agreement', 'Write to the program' ),
	array( 'Overview', 'Your sponsorship', 'Offers', 'Students reached', 'Mentors to sponsor', 'Your company', 'Agreement', 'Write to the program' ),
	array( 'Overview', 'Your sponsorship', 'Offers', 'Students reached', 'Mentors to sponsor', 'Your company', 'Agreement', 'Program updates', 'Write to the program' ),
	array( 'url' => 'https://example.test/a-word-for-sponsors/', 'marked' => false, 'current' => false, 'badge' => '', 'tone' => '' ),
) );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );

// Sponsor records live on the site, so the page has nothing to say about where it was read from: no
// "Read from the program records" line closes any view, for a member, with the switch on, or for a manager.
$GLOBALS['uid']  = 1;
$GLOBALS['get']  = array( WPCPM_Sponsor_Roster::ARG_VIEW => $A );
$as_manager_page = implode( '', every_view() );
$GLOBALS['uid']  = 5;
$GLOBALS['get']  = array();
ck( 'no view prints a "Read from the program records" line', array( strpos( $all_off, 'Read from the program records' ), strpos( implode( '', $framed ), 'Read from the program records' ), strpos( $as_manager_page, 'Read from the program records' ), strpos( $all_off . $as_manager_page, 'program records' ) ), array( false, false, false, false ) );

echo "\n=== No arrows: a view for each section (1.123.78) ===\n";
// The four sections were movable modules (Institutions release 3), each with its arrows, in the order the
// company kept. A view per section leaves nothing to arrange (the builder's call in the design of
// 11 November 2026): the arrows, their handler and the order are gone, and the option a company's order
// was kept in is left as it was, unused, so uninstall still finds it.
$GLOBALS['uid']  = 5;
$GLOBALS['get']  = array();
$GLOBALS['post'] = array();
$retired         = new ReflectionClass( 'WPCPM_Sponsors_Dashboard' );
ck( 'the option a company\'s order was kept in keeps its prefix, for uninstall, and the mover\'s constants and methods are gone', array(
	$D::OPT_MODULES_PREFIX,
	array_map( array( $retired, 'hasConstant' ), array( 'ACTION_MOVE', 'FIELD_SPONSOR', 'MODULE_SPONSORSHIP', 'MODULE_OFFER', 'MODULE_MENTORS', 'MODULE_COMPANY' ) ),
	array_map( array( $retired, 'hasMethod' ), array( 'modules', 'leads', 'module_order', 'handle_move', 'module_cards', 'render_module_body' ) ),
), array( 'wpcpm_sponsor_modules_', array( false, false, false, false, false, false ), array( false, false, false, false, false, false ) ) );
$GLOBALS['calls'] = array();
$D::init();
ck( 'init() hooks the page, its rename, the login route and the wp-admin route, and no mover', $GLOBALS['calls'], array( array( 'add_action', 'init', 10 ), array( 'add_action', 'init', 20 ), array( 'add_filter', 'login_redirect', 10 ), array( 'add_action', 'admin_init', 10 ) ) );
// No view draws a module's wrapper, its arrows, its title and lead classes or the field that named the
// sections drawn, for a member or for an Administrator, and the shared script is not asked for.
$GLOBALS['scripts']               = array();
$GLOBALS['settings']['sponsor_tiers_live'] = true;
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion', 'tier_until' => day_from_today( 100 ) ) );
$member_views     = every_view();
$GLOBALS['uid']   = 1;
$GLOBALS['get']   = array( WPCPM_Sponsor_Roster::ARG_VIEW => $A );
$admin_views      = implode( '', every_view() );
$GLOBALS['uid']   = 5;
$GLOBALS['get']   = array();
$no_modules       = implode( '', $member_views ) . $admin_views;
ck( 'no view prints a module\'s wrapper, its arrows, its title or its lead, for a member or an Administrator, and the modules\' script is not enqueued', array( strpos( $no_modules, 'wpcpm-module' ), strpos( $no_modules, 'wpcpm_drawn' ), strpos( $no_modules, 'aria-label="Move ' ), strpos( $no_modules, 'wpcpm-sponsor__module-' ), strpos( $no_modules, 'wpcpm_sponsor_module_move' ), in_array( 'wpcpm-modules', $GLOBALS['scripts'], true ) ), array( false, false, false, false, false, false ) );
// A company that arranged its page before 1.123.78 still has its order saved: it changes nothing, on any
// view, since there is nothing left to arrange.
$GLOBALS['opts'][ $D::OPT_MODULES_PREFIX . $A ] = array( 'company', 'mentors', 'offer', 'sponsorship' );
$saved_views = every_view();
unset( $GLOBALS['opts'][ $D::OPT_MODULES_PREFIX . $A ] );
ck( 'an order a company saved before changes nothing: every view reads as it does with none saved', array_map( 'main_of', $saved_views ) === array_map( 'main_of', $member_views ) && menu_items( $saved_views['overview'] ) === menu_items( $member_views['overview'] ), true );

$GLOBALS['manage'] = array( 1 );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => '', 'tier_until' => '' ) );

// The release's review (1.123.78), finding 1: the codes figure leaves out an offer that is over, switched on
// but past its last day included, as the Overview's cards do, so the codes left are named in an offer still
// running. The claims stand in with two pools, the older one past its last day and nearly empty.
if ( ! class_exists( 'WPCPM_Sponsor_Claims' ) ) {
	/** The claims, by offer: what `stats()` answers for a sponsor a check names. */
	class WPCPM_Sponsor_Claims {
		public static function stats( $record ) { return isset( $GLOBALS['claims_stats'][ $record ] ) ? $GLOBALS['claims_stats'][ $record ] : array( 'months' => array(), 'offers' => array(), 'totals' => array() ); }
		public static function people_reached( $record ) { return 0; }
	}
}
$GLOBALS['uid'] = 5;
$GLOBALS['get'] = array();
$GLOBALS['claims_stats'][ $A ] = array(
	'months' => array(),
	'offers' => array(
		11 => array( 'title' => 'Old pool', 'kind' => 'codes', 'state' => 'live', 'total' => 5, 'month' => 0, 'series' => array(), 'available' => 1, 'claimed' => 5, 'void' => 0 ),
		12 => array( 'title' => 'Fresh pool', 'kind' => 'codes', 'state' => 'live', 'total' => 2, 'month' => 0, 'series' => array(), 'available' => 8, 'claimed' => 2, 'void' => 0 ),
	),
	'totals' => array(),
);
$figures_of = static function () use ( $D ) {
	$html = $D::render();
	return (string) strstr( (string) strstr( $html, '<section class="wpcpm-figures' ), '</section>', true );
};
$GLOBALS['offers_of'][ $A ] = array( array( 'id' => 11, 'past' => false ), array( 'id' => 12, 'past' => false ) );
$both_running               = $figures_of();
$GLOBALS['offers_of'][ $A ] = array( array( 'id' => 11, 'past' => true ), array( 'id' => 12, 'past' => false ) );
$one_over                   = $figures_of();
ck( 'the codes figure counts the pools of the offers still running and names the codes left in the one closest to running out; an offer switched on but past its last day is left out of both', array(
	false !== strpos( $both_running, '7<small> / 16</small>' ),
	false !== strpos( $both_running, '1 left in Old pool' ),
	false !== strpos( $one_over, '2<small> / 10</small>' ),
	false !== strpos( $one_over, '8 left in Fresh pool' ),
	false !== strpos( $one_over, 'Old pool' ),
), array( true, true, true, true, false ) );
unset( $GLOBALS['claims_stats'][ $A ], $GLOBALS['offers_of'][ $A ] );

// The release's review (1.123.78), finding 2: an Administrator looking at a sponsor keeps it on the way to the
// Sponsor guide and back. The guide's link carries the sponsor, and the menu the guide draws reads it from the
// address: its links carry it, its counts are that sponsor's. With no sponsor named, the menu counts nobody's
// and carries none, rather than an Administrator's own company or the first one with an account.
$GLOBALS['uid'] = 1;
$GLOBALS['get'] = array( WPCPM_Sponsor_Roster::ARG_VIEW => $B );
$guide_page->post_status = 'publish';
$GLOBALS['posts'][777]   = $guide_page;
$manager_help = help_items( $D::render() );
$named_menu   = WPCPM_Sponsors_Dashboard::menu_args( WPCPM_Sponsors_Dashboard::VIEW_GUIDE );
$GLOBALS['get'] = array();
$bare_menu      = WPCPM_Sponsors_Dashboard::menu_args( WPCPM_Sponsors_Dashboard::VIEW_GUIDE );
$GLOBALS['get'] = array( WPCPM_Sponsor_Roster::ARG_VIEW => 'recNOSUCHSPONSOR1' );
$unknown_menu   = WPCPM_Sponsors_Dashboard::menu_args( WPCPM_Sponsors_Dashboard::VIEW_GUIDE );
$GLOBALS['get'] = array();
$urls_of        = static function ( array $menu ) { return array_column( $menu['groups'][0]['items'], 'url' ); };
ck( 'an Administrator looking at a sponsor reaches the Sponsor guide with that sponsor in its address, and the guide\'s menu leads back to it; with no sponsor named, or one the store does not hold, its links name none', array(
	$manager_help['Sponsor guide']['url'],
	$urls_of( $named_menu )[0],
	$urls_of( $named_menu )[1],
	strpos( implode( ' ', $urls_of( $bare_menu ) ), WPCPM_Sponsor_Roster::ARG_VIEW ),
	strpos( implode( ' ', $urls_of( $unknown_menu ) ), WPCPM_Sponsor_Roster::ARG_VIEW ),
	$named_menu['page'],
), array(
	'https://example.test/sponsor-guide/?' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $B,
	$D::view_url( $D::VIEW_OVERVIEW, $B ),
	$D::view_url( $D::VIEW_OFFERS, $B ),
	false,
	false,
	'sponsor-guide',
) );
unset( $GLOBALS['posts'][777] );
$GLOBALS['uid'] = 5;

// Counted by ck() itself: a literal here read 45 while the file carried 49 (1.93.2), and a count
// nobody maintains is a count nobody should trust.
printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', (int) $checks );
exit( $fail ? 1 : 0 );
