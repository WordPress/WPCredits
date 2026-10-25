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
 * - **The program contact is the assigned manager only while that account is a program manager.**
 *   A manager who lost the capability stays on the row, and the member's page names them nowhere:
 *   no contact block, no empty heading, no name and no booking link.
 * - **The render order:** the two-factor prompt, the identity, the At a glance strip when it has
 *   tiles to show, the help (Institutions release 3), then the four sections, which are movable
 *   modules (Institutions release 3), in the order the owner approved on 27 September 2026 unless
 *   the company has moved them: Your sponsorship (the tier card's two cards, Reports, the badge),
 *   What you offer (offers, usage, posts), Mentors, and Your company (the profile, People, the
 *   agreement). The help is the Institution Dashboard's, in two columns, the program contact under
 *   the Resources buttons, and the page never prints a "Read from the program records" line.
 *   The offers, usage, posts and agreement cards are not loaded here and are left as silent gaps,
 *   so What you offer is not printed until the At a glance section draws the Offers card through
 *   a stand-in. Each section says under its heading, in one sentence, what its cards hold. The
 *   People card, this page's own, folds, and its row says how many accounts act for the sponsor.
 * - **Offer more is off the page (1.123.22).** Its six support choices were the sponsor
 *   application's before the levels, and its Tell us said what the Sponsorship card's Talk to your
 *   program contact says: no group draws it, no row action or tile names it, and messages() merges
 *   none of its statuses. WPCPM_Sponsor_Interests stays, the one road an interest takes.
 * - **The header's contact line names the contact person and never an address**, as the Institution
 *   Dashboard's does, and a sponsor with a contact address and no name has no contact line.
 * - **The At a glance strip sits right under the identity**, the Administrator Dashboard's
 *   attention tiles markup for markup and dressed by its sheet, which the page's own depends on
 *   (Institutions release 3): the tier first, while the launch switch is on, in its words, and a
 *   waiting item in the last thirty days of a term and from the day after it ended; then one tile
 *   for each kind of thing waiting on the sponsor's people, in this order: offers returned with a
 *   note, offers running low on codes, offers not switched on yet, posts returned, the latest
 *   event returned, the Collaboration Agreement returned or revoked, and the company profile's
 *   missing details. Each is a link to the card that holds it, carries the waiting mark and is
 *   drawn only when its count is above zero and its card is drawn, asked the card's own way (the
 *   event only where the Events card can show the note, inside the planner's plan form, which is
 *   where the planner's allowance is empty: not for a term used, an event open or a tier with no
 *   planner);
 *   when nothing waits, the tier tile included, a muted "Nothing waits for you" with the number 0
 *   and no link follows, or stands alone with the switch off. A strip of one tile is a strip; a
 *   sponsor the store holds no row for gets none. The figures that were tiles (the next event, the
 *   offers live, the mentors, the yearly reports) show on their cards' lines. That section draws
 *   the Offers, Posts and Collaboration Agreement cards through stand-ins that answer the one
 *   question the strip asks, and loads the real event store and what the planner reads, as
 *   bin/test-sponsor-cards.php does, so the Events card holds a real planner.
 * - **A section is printed only when one of its cards drew.** The first section's cards are drawn only
 *   while the launch switch is on (the tier card from 1.112.0, the badge from 1.113.0, Reports from
 *   1.115.0), so with the switch off the section is not printed at all: no wrapper, no heading, no
 *   lead, no arrows. With it on, the section's line names exactly the cards it draws, in their order (1.123.22):
 *   the tier, then the events, the reports and the badge where each is drawn, each of the eight
 *   lines one whole sentence in the source, asked of the same tests the cards ask. With it on, a Champion's group reads the tier card's sponsorship card and Events, then
 *   Reports once a final report exists, drawn by the real card class from a report the real
 *   store reads, then the badge. A section whose cards print nothing but white space is not printed
 *   either: this suite runs itself as a child (`--blank-group-child`) whose first section's cards are
 *   stand-ins that print only white space, since a class once loaded cannot be swapped here.
 * - **The four sections are the company's to arrange (Institutions release 3).** Each is drawn through
 *   the printer both dashboards share (`WPCPM_Module_Order::render_module()`) under the Sponsor
 *   Dashboard's own title and lead classes, with Move up and Move down named after it, in the order
 *   the company keeps: saved per sponsor key, repaired on read, today's order the default, a section
 *   with nothing to print left out and the outer arrows counting only what is drawn. The company's
 *   members and Administrators are given the arrows and the shared script; a move is saved by the
 *   page's own handler, which takes the company from the account and never from the form for anyone
 *   but an Administrator.
 * - **The plugin's own sheets dress what the theme dresses on this page**, value for value where
 *   the base's rhythm allows: a row's state, its action beside the row with the card as its
 *   positioning context, the resources in thirds and the program contact in a column of its own.
 * - **The page's scripts:** the double-submit guard, the badge card's Copy script and the opener
 *   that opens a card a link lands on, the one the Administrator Dashboard enqueues too, each
 *   enqueued, and the Copy script never registered over a registration something else made first.
 *   The page asks the dashboards' shell for the opener, and how the shell registers it, a
 *   registration made first kept, is pinned in bin/test-dashboard-card.php.
 * - **leave() and messages() are the one door every card's handler uses**, called by array
 *   callable so bin/check-references.php cannot be fooled into thinking the door exists
 *   before this class does. Every card the page draws is merged into messages(), a card with
 *   no form of its own among them, so the two lists move together; so is the Events card's
 *   planner (1.117.0), whose presses leave through the same door from inside the tier card.
 * - **A refusal whose sentence is its detail shows a notice.** A refusal for words the cleaner
 *   would take (the profile's and the Offers card's) has no sentence of its own: the card's
 *   sentence, which names the box, is the detail, and the page prints it alone.
 *   With neither a sentence nor a detail, or with a sentence whose values the flash does not carry,
 *   nothing is printed, as before. A detail is printed escaped, after a sentence and alone alike.
 *   A child run (`--loss-child`) loads the real Offers card, presses
 *   each card's own Save as the member with a box the cleaner would take words from, and reads the
 *   page each lands on: the notice in the error tone and the box holding what was typed. leave()
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
// The tier card reads today once per page (WPCPM_Sponsor_Tiers::today()). Until the At a glance
// section pins a day in $GLOBALS['today'], today is the clock's, which nothing above it pins; every
// other call formats the timestamp it is given, on the clock it is given, else on UTC, the site's.
function wp_date( $format, $ts = null, $zone = null ) {
	if ( null === $ts && 'Y-m-d' === $format && isset( $GLOBALS['today'] ) ) { return $GLOBALS['today']; }
	$ts = null === $ts ? time() : (int) $ts;
	return $zone instanceof DateTimeZone ? ( new DateTime( '@' . $ts ) )->setTimezone( $zone )->format( $format ) : gmdate( $format, $ts );
}
// The event store and the planner, loaded in the At a glance section, read the site's clock by these.
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
 * The Resources section, recording the audience and the columns it was asked for as `audience:columns`.
 *
 * Shaped as the real one is where this suite looks: the updates column first, then the Resources
 * column with its buttons and, in two columns, the extra markup under them; in three, the extra in a
 * column of its own and the strip wearing the thirds modifier. That is what lets a check read where
 * the page put the program contact and whether it asked for thirds, from the page's output.
 */
class WPCPM_Handbook_Assistant {
	public static function render_resources( $audience = '', $extra = '', $columns = 2 ) {
		$GLOBALS['resources'][] = $audience . ':' . (int) $columns;
		$three = 3 === (int) $columns && '' !== $extra;
		$out   = '<section class="wpcpm-student__section wpcpm-handbook__resources wpcpm-resources--split' . ( $three ? ' wpcpm-resources--split-3' : '' ) . '" data-audience="' . $audience . '">';
		$out  .= '<div class="wpcpm-resources__col wpcpm-resources__col--updates"><h3 class="wpcpm-student__heading">Program updates and announcements</h3></div>';
		$out  .= '<div class="wpcpm-resources__col wpcpm-resources__col--help"><h3 class="wpcpm-student__heading">Resources</h3><p class="wpcpm-student__actions"><a class="wpcpm-button" href="#guide">Sponsor guide</a></p>' . ( $three ? '' : $extra ) . '</div>';
		$out  .= $three ? '<div class="wpcpm-resources__col wpcpm-resources__col--contact">' . $extra . '</div>' : '';
		return $out . '</section>';
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
 * The Administrator Dashboard's stylesheet registration, which the sponsor sheet depends on: the At
 * a glance tiles are that page's attention tiles, dressed by its rules. Registered here the way the
 * real class registers it, on the mentor sheet, and only when nothing registered it first.
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
 * sync's index is empty unless a check fills `$GLOBALS['mentors_index']`, as the mentors tile's
 * check does.
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
// The movable modules' handler: the nonce it checks first is recorded, a refusal is a die with its status,
// and the background save's answer is the JSON the script reads.
if ( ! function_exists( 'check_admin_referer' ) ) { function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) { $GLOBALS['nonce_checked'][] = $action; return 1; } }
function wp_die( $message = '', $title = '', $args = array() ) { $GLOBALS['died'] = (string) $message; throw new Exception( 'die: ' . ( is_array( $args ) && isset( $args['response'] ) ? $args['response'] : 'died' ) ); }
function wp_send_json_success( $data = null ) { throw new Exception( 'json: ' . json_encode( $data ) ); }
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
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
// The movable modules' shared pieces: the repair of a saved order, one move, the arrows and the printer
// of one module, real here because the page's markup is what they print.
require_once __DIR__ . '/../includes/class-wpcpm-module-order.php';
require_once __DIR__ . '/../includes/class-wpcpm-dashboards.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-manager-booking.php';
// The shell every card is drawn in.
require_once __DIR__ . '/../includes/class-wpcpm-dashboard-card.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-profile.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-mentors.php';
// Run as a child (`--blank-group-child`) by the check that a group whose cards print nothing but white
// space is not printed: there the first group's three cards are stand-ins printing only white space,
// as a card could with a stray newline before it returns, the real ones never loaded, and the child
// prints what the page drew and stops.
$blank_group_child = in_array( '--blank-group-child', (array) ( $argv ?? array() ), true );
// Run as a child (`--loss-child`) by the check that a refused save shows its sentence: there the
// real Offers card is loaded, which the page here otherwise leaves as a gap, and the member presses
// the profile's and the Offers card's own Save, each with a box the cleaner would take words from,
// and loads the page after each.
$loss_child = in_array( '--loss-child', (array) ( $argv ?? array() ), true );
if ( $blank_group_child ) {
	/** White space alone, where the sponsorship card and Events would be. */
	class WPCPM_Sponsor_Tier_Card { const CARD = 'sponsorship'; const CARD_EVENTS = 'events'; public static function render( $record, array $context ) { echo "\n \t\n"; } }
	/** White space alone, where Reports would be. */
	class WPCPM_Sponsor_Report_Card { const CARD = 'reports'; public static function render( $record, array $context ) { echo "\n \t\n"; } }
	/** White space alone, where the badge would be. */
	class WPCPM_Sponsor_Badge { const CARD = 'badge'; public static function render( $record, array $context ) { echo "\n \t\n"; } }
} else {
	require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tier-card.php';
	require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-badge.php';
}
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-files.php';
// Loaded only when it is there, so that before the card exists its checks fail as checks.
if ( ! $blank_group_child && file_exists( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-card.php' ) ) {
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
WPCPM_Sponsors_Index::write( array( $A => array( 'name' => 'Mango Example ', 'status' => 'Approved', 'website' => 'plugins.mango-example.test', 'product_type' => 'Hosting', 'contact_person' => 'Rep One', 'contact_email' => 'maciej@a8c.com', 'manager' => '1' ), $B => array( 'name' => 'Wexample', 'status' => 'Approved' ) ), time() );
WPCPM_Sponsors_Index::write_logo_record( $A, array( 'colour' => 501, 'source' => 'airtable', 'airtable_id' => 'attA' ) );
// 1.111.0: the manager is a site user now (manager_of() resolves through get_userdata()), so
// user 1 carries the name and address the held Team Members option used to, and the booking link
// is the field on that user's own profile, set through the real WPCPM_Manager_Booking.
$GLOBALS['users'] = array( 1 => new WP_User( 1, array( 'administrator' ), 'Maciej (Matt) Pilarski', 'maciej@a8c.com' ), 5 => new WP_User( 5, array( 'wpcpm_sponsor' ), 'Rep One' ), 9 => new WP_User( 9, array( 'subscriber' ), 'Stranger' ) );
WPCPM_Manager_Booking::set( 1, 'https://calendly.example/matt' );
$GLOBALS['umeta'][5] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $A, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['manage'] = array( 1 );
$GLOBALS['settings'] = array( 'sponsor_home' => true );
$D = 'WPCPM_Sponsors_Dashboard';

// The child: a member's page with the launch switch off, whose first section's cards print only white
// space. It says which sections the page printed, and whether the white space or the first section's
// heading reached it.
if ( $blank_group_child ) {
	$GLOBALS['uid'] = 5;
	$blank_page     = $D::render();
	preg_match_all( '/<div class="wpcpm-module wpcpm-module--([a-z]+)" id="wpcpm-module-\1">/', $blank_page, $blank_groups );
	echo 'modules ' . implode( ',', $blank_groups[1] ) . "\n";
	echo 'white space ' . ( false !== strpos( $blank_page, "\n \t\n" ) ? 'printed' : 'not printed' ) . "\n";
	echo 'heading ' . ( false !== strpos( $blank_page, '>Your sponsorship</h2>' ) ? 'printed' : 'not printed' ) . "\n";
	exit( 0 );
}

// The child: the member presses each card's Save with a box the cleaner would take words from, and
// the page the press lands on is loaded. It prints, for each card, what was flashed, the notice the
// page shows as a browser reads it, and what the box the words were typed into holds.
if ( $loss_child ) {
	if ( ! function_exists( 'check_admin_referer' ) ) {
		/** The nonce, good: the refusals pressed here come after it. */
		function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) { return 1; }
	}
	/** The claims, as far as the Offers card draws them: none this month. */
	class WPCPM_Sponsor_Claims { public static function stats( $record ) { return array( 'totals' => array( 'month' => 0 ) ); } }
	require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-codes.php';
	require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-offers.php';

	$GLOBALS['uid'] = 5;
	$presses        = array(
		'profile'   => array( array( 'WPCPM_Sponsor_Profile', 'handle_save' ), array( 'wpcpm_sponsor' => $A, 'wpcpm_contact_person' => 'Ana <ana@example.test>' ), '#name="wpcpm_contact_person" value="([^"]*)"#' ),
		'offers'    => array( array( 'WPCPM_Sponsor_Offers', 'handle_save' ), array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => '0', 'wpcpm_title' => 'Kids <12 free, adults >18 pay', 'wpcpm_kind' => 'none' ), '#id="wpcpm-offer-new-title" name="wpcpm_title" value="([^"]*)"#' ),
	);
	$seen = array();
	foreach ( $presses as $card => $press ) {
		$_POST = $press[1];
		$GLOBALS['post'] = $press[1];
		try {
			call_user_func( $press[0] );
			$left = 'fell through';
		} catch ( WPCPM_Test_Redirect $e ) {
			$left = isset( $GLOBALS['flash'][ $D::FLASH ]['status'] ) ? $GLOBALS['flash'][ $D::FLASH ]['status'] : 'no flash';
		}
		$_POST = array();
		$GLOBALS['post'] = array();
		$page   = $D::render();
		$notice = preg_match( '#<p class="wpcpm-dashboard__message wpcpm-dashboard__message--([a-z]+)">(.*?)</p>#s', $page, $found ) ? array( $found[1], html_entity_decode( strip_tags( $found[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : null;
		$box    = preg_match( $press[2], $page, $typed ) ? html_entity_decode( (string) preg_replace( '/^\n/', '', $typed[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : null;
		$seen[ $card ] = array( 'left' => $left, 'notice' => $notice, 'box' => $box );
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
// The launch switch is off and A has no tier: no card of the first section draws, and What you offer
// holds no card this suite loads here (Offer more left it in 1.123.22), so the page goes from the
// header to the strip, to the help under it (Institutions release 3: the Institution Dashboard's
// order, the help "to easy access support", where it was once the page's last section), and then
// straight to Mentors and Your company.
$needles = array( 'wpcpm-two-factor-marker', 'wpcpm-sponsor__identity', 'id="wpcpm-sponsor-glance"', 'wpcpm-handbook__resources', '<h2 class="wpcpm-student__heading wpcpm-sponsor__module-title">Mentors</h2>', 'id="wpcpm-sponsor-mentors"', 'id="wpcpm-sponsor-looking"', '<h2 class="wpcpm-student__heading wpcpm-sponsor__module-title">Your company</h2>', 'id="wpcpm-sponsor-profile"', 'id="wpcpm-sponsor-people"' );
$order   = array();
foreach ( $needles as $needle ) { $order[] = strpos( $out, $needle ); }
$sorted = $order; sort( $sorted );
ck( 'a member sees the prompt, the identity, the strip, the help, then the sections under their headings: Mentors, Your company (profile, people)', ! in_array( false, $order, true ) && $order === $sorted, true );
// Under the strip means right after it, and before the first section: the help is the only one of its
// kind, and the page ends on the last section.
ck( 'the help follows the strip directly, once, and the last section closes the page', array(
	false !== strpos( $out, glance_of( $out ) . '<section class="wpcpm-student__section wpcpm-handbook__resources wpcpm-resources--split" data-audience="sponsor">' ),
	substr( $out, -strlen( '</section></div></div>' ) ),
	substr_count( $out, 'wpcpm-handbook__resources' ),
), array( true, '</section></div></div>', 1 ) );
// Each section opens with its wrapper, the arrows, its heading and the one sentence that says what its
// cards hold, said once here rather than inside each folded card: one sentence each, as the owner
// approved on 27 September 2026. The heading and the sentence are what follows the arrows, so they
// are matched from the heading on. esc_html() writes each apostrophe as an entity.
$module_opening = static function ( $title, $lead ) {
	return '<h2 class="wpcpm-student__heading wpcpm-sponsor__module-title">' . $title . '</h2><p class="wpcpm-student__note wpcpm-sponsor__module-lead">' . $lead . '</p>';
};
$openings       = array(
	'sponsorship' => $module_opening( 'Your sponsorship', 'Your tier, your events and your badge.' ),
	'offer'       => $module_opening( 'What you offer', 'What your company gives the program&#039;s students and mentors, and what it wrote for them.' ),
	'mentors'     => $module_opening( 'Mentors', 'The mentors your company sponsors, and the ones looking for a sponsor.' ),
	'company'     => $module_opening( 'Your company', 'Your company&#039;s details and logo, the people who can sign in for it, and the Collaboration Agreement.' ),
);
/**
 * The sections a page prints, by key, in the order it prints them.
 *
 * @param string $html The page.
 * @return string[]
 */
function modules_printed( $html ) {
	preg_match_all( '/<div class="wpcpm-module wpcpm-module--([a-z]+)" id="wpcpm-module-\1">/', $html, $m );
	return $m[1];
}
/**
 * One section as a page printed it, from its wrapper to the next section or the page's end; null when
 * the page does not print it.
 *
 * @param string $html The page.
 * @param string $key  The section's key.
 * @return string|null
 */
function module_of( $html, $key ) {
	$at = strpos( $html, '<div class="wpcpm-module wpcpm-module--' . $key . '" id="wpcpm-module-' . $key . '">' );
	if ( false === $at ) { return null; }
	$next = strpos( $html, '<div class="wpcpm-module wpcpm-module--', $at + 1 );
	return false === $next ? substr( $html, $at ) : substr( $html, $at, $next - $at );
}
/**
 * A section after its arrows: its heading, its sentence and its cards.
 *
 * @param string|null $module A section as module_of() returns it.
 * @return string
 */
function module_after_arrows( $module ) {
	$module = (string) $module;
	$end    = strpos( $module, '</form>' );
	return 0 === strpos( substr( $module, (int) strpos( $module, '>' ) + 1 ), '<form class="wpcpm-module__mover"' ) && false !== $end ? substr( $module, $end + strlen( '</form>' ) ) : $module;
}
ck( 'with the switch off, the two sections that hold a card here, each a wrapper of its own, in the approved order', modules_printed( $out ), array( 'mentors', 'company' ) );
// Offer more (1.123.22): its support choices were the sponsor application's before the levels, and its
// Tell us said what the Sponsorship card's Talk to your program contact says. No section draws it, no
// action after a fold names it, and What you offer, whose other cards this suite leaves out, is not
// printed over nothing.
ck( 'Offer more is not on the page: no card, no Tell us, no title, and no What you offer section over nothing', array( strpos( $out, 'wpcpm-sponsor-interests' ), strpos( $out, 'Tell us' ), strpos( $out, 'Offer more' ), strpos( $out, 'name="wpcpm_support[]"' ), strpos( $out, 'wpcpm-module--offer' ) ), array( false, false, false, false, false ) );
// A card is the section the shell draws, so a module's first card follows its sentence directly and
// its last card closes right before the module does.
$first_card = static function ( $key ) {
	return '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-' . $key . '"';
};
/**
 * How many modules close right after a card: the next module follows each, or the page itself ends.
 *
 * @param string $html The page.
 * @return int
 */
function modules_closed_after_a_card( $html ) {
	return substr_count( $html, '</section></div><div class="wpcpm-module wpcpm-module--' ) + ( '</section></div></div>' === substr( $html, -strlen( '</section></div></div>' ) ) ? 1 : 0 );
}
ck( 'each opens with its heading and its one sentence, its cards inside it from the next tag on, and no other lead is printed', array( false !== strpos( $out, $openings['mentors'] . $first_card( 'mentors' ) ), false !== strpos( $out, $openings['company'] . $first_card( 'profile' ) ), modules_closed_after_a_card( $out ), substr_count( $out, '<p class="wpcpm-student__note wpcpm-sponsor__module-lead">' ) ), array( true, true, 2, 2 ) );
// A card that prints nothing but white space prints no card, and a section whose cards all do so is not
// printed, rather than a heading and a sentence over an empty line: shown by a child of this suite,
// whose first section's cards are stand-ins that print only white space.
$blank_child  = array();
$blank_status = null;
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --blank-group-child 2>&1', $blank_child, $blank_status );
ck( 'a section whose cards print nothing but white space is not printed at all, and neither is their white space', array( $blank_status, $blank_child ), array( 0, array( 'modules mentors,company', 'white space not printed', 'heading not printed' ) ) );
ck( 'and no card of the first section is drawn, so the section is not printed at all: no wrapper, no heading, no lead', array( strpos( $out, 'id="wpcpm-sponsor-sponsorship"' ), strpos( $out, 'id="wpcpm-sponsor-events"' ), strpos( $out, 'id="wpcpm-sponsor-reports"' ), strpos( $out, 'id="wpcpm-sponsor-badge"' ), strpos( $out, 'wpcpm-module--sponsorship' ), strpos( $out, '>Your sponsorship</h2>' ), strpos( $out, 'Your tier, your events, your reports and your badge.' ) ), array( false, false, false, false, false, false, false ) );
// 1.111.0: the site is the record, so no lead names program records a sponsor's words are written to.
ck( 'no lead names program records', strpos( $out, 'program records' ), false );
ck( 'the help is the sponsor audience, asked for in the default two columns, the program contact under the buttons', $GLOBALS['resources'], array( 'sponsor:2' ) );
ck( 'the identity shows the site\'s logo, never Airtable\'s URL', false !== strpos( $out, 'https://example.test/uploads/501.png' ) && false === strpos( $out, 'airtableusercontent' ), true );
ck( 'the name trimmed, the website completed, and the contact person named in the header, the address in no part of it', array(
	false !== strpos( $out, '>Mango Example<' ),
	false !== strpos( $out, 'href="https://plugins.mango-example.test"' ),
	false !== strpos( $out, '<p class="wpcpm-sponsor__fact wpcpm-sponsor__contact">Contact: Rep One</p>' ),
	false !== strpos( header_of( $out ), WPCPM_Sponsors_Index::row( $A )['contact_email'] ),
), array( true, true, true, false ) );
// The type of product (1.123.22): the application no longer asks it and nothing public shows it, so the
// header no longer prints it as a fact about the company, nor the profile card a box for it; A's row
// holds Hosting all the same.
$identity = substr( $out, (int) strpos( $out, '<header class="wpcpm-sponsor__identity">' ) );
$identity = substr( $identity, 0, (int) strpos( $identity, '</header>' ) );
ck( 'the type of product the row holds is printed nowhere: not under the name, not on the profile card', array( WPCPM_Sponsors_Index::row( $A )['product_type'], strpos( $identity, 'Hosting' ), substr_count( $identity, 'wpcpm-sponsor__fact' ), strpos( $out, 'Hosting' ), strpos( $out, 'Type of product' ) ), array( 'Hosting', false, 1, false, false ) );
ck( 'the program contact block names the manager, the address and the booking link', false !== strpos( $out, 'Maciej (Matt) Pilarski' ) && false !== strpos( $out, 'mailto:maciej@a8c.com' ) && false !== strpos( $out, 'href="https://calendly.example/matt"' ), true );
// The same manager once the account is no program manager: still on A's row, and nobody's contact.
$GLOBALS['manage'] = array();
$demoted           = $D::render();
$GLOBALS['manage'] = array( 1 );
ck( 'a manager who lost the program capability is named nowhere on the member\'s page: no contact block, no empty heading, no name, no booking link', array( strpos( $demoted, 'wpcpm-resources__contact' ), strpos( $demoted, 'Your contact at the program' ), strpos( $demoted, 'Maciej (Matt) Pilarski' ), strpos( $demoted, 'calendly.example/matt' ) ), array( false, false, false, false ) );
ck( 'no switcher for a member', strpos( $out, 'wpcpm-dashboard__switcher' ), false );
ck( 'nor the script that narrows one', in_array( 'wpcpm-switcher', $GLOBALS['scripts'], true ), false );
ck( 'no heading unless the block asks for one', strpos( $out, 'wpcpm-dashboard__title' ), false );
ck( 'the block\'s optional heading is honoured', false !== strpos( $D::render( array( 'title' => 'Our sponsorship' ) ), '<h2 class="wpcpm-dashboard__title">Our sponsorship</h2>' ), true );
$people_card = substr( $out, strpos( $out, 'id="wpcpm-sponsor-people"' ) ); $people_card = substr( $people_card, 0, strpos( $people_card, '</section>' ) );
ck( 'the people card lists the accounts and offers no form to a member', false !== strpos( $people_card, 'Rep One' ) && 0 === substr_count( $people_card, '<form' ), true );
// The row a folded People card shows (the design the owner approved on 27 September 2026): how many
// accounts act for the sponsor, and no action, since accounts are attached by the program.
ck( 'the People row: folded, how many accounts act for the sponsor, and no action', array( false !== strpos( $out, '<details id="wpcpm-sponsor-people" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">People <span class="wpcpm-group__count">1</span></h3><span class="wpcpm-sponsor__state">1 account</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>' ), strpos( $people_card, 'wpcpm-sponsor__row-action' ) ), array( true, false ) );
// A folded card's action follows its fold, and names for a screen reader what it does, so a link
// read out of its row says more than "Edit": on this member's page, the profile's alone, Offer more's
// Tell us having gone with its card (1.123.22).
ck( 'the member\'s page offers the profile\'s action alone, after its fold and naming what it does for a screen reader', array(
	substr_count( $out, 'wpcpm-sponsor__row-action' ),
	false !== strpos( $out, '<p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-profile">Edit<span class="screen-reader-text"> the company profile and logo</span></a></p></section>' ),
), array( 1, true ) );
ck( 'the stylesheet is registered from assets/css/sponsor.css and switched on', isset( $GLOBALS['styles'][ $D::STYLE ] ) && false !== strpos( $GLOBALS['styles'][ $D::STYLE ]['src'], 'assets/css/sponsor.css' ) && ! empty( $GLOBALS['styles'][ $D::STYLE ]['on'] ), true );
// The At a glance tiles are the Administrator Dashboard's attention tiles, so the sheet depends on the
// one that dresses them there, and registers it first itself, as it does the mentor sheet: one set of
// tile rules for both pages, on any theme, and never a sheet that waits on a handle nobody registered.
ck( 'on the mentor stylesheet and the Administrator Dashboard\'s, whose attention tiles the At a glance strip wears, each registered by the page itself', array( $GLOBALS['styles'][ $D::STYLE ]['deps'], isset( $GLOBALS['styles'][ WPCPM_Mentors_Dashboard::STYLE ] ), isset( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ] ) ), array( array( WPCPM_Mentors_Dashboard::STYLE, WPCPM_Administrators_Dashboard::STYLE ), true, true ) );
ck( 'the double-submit guard is armed', in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ), true );
// The Badge card's Copy button (1.113.0): enqueued with the guard, before any card is drawn,
// so the script is there whichever cards the page holds.
ck( 'and so is the Copy button\'s script, registered from assets/js/copy.js', array( in_array( 'wpcpm-copy', $GLOBALS['scripts'], true ), isset( $GLOBALS['script_src']['wpcpm-copy'] ) ? $GLOBALS['script_src']['wpcpm-copy'] : '' ), array( true, WPCPM_PLUGIN_URL . 'assets/js/copy.js' ) );
// The opener: a link that lands on a card's anchor (a row's action, a link in a mail) opens the card,
// which a browser following a link to a closed disclosure does not do. The dashboards' one opener, the
// Administrator Dashboard's too, enqueued with the other two, before any card is drawn, through the
// shell both pages share (its registration is pinned in bin/test-dashboard-card.php).
ck( 'and so is the dashboards\' opener, through the shell both pages share', in_array( 'wpcpm-dashboard-open', $GLOBALS['scripts'], true ), true );
// The product name "Student Report Card" is allowed wherever a card names the product; any other
// "Student" would be a person or a role leaking onto the sponsor's page.
ck( 'no student is named anywhere', preg_match( '/Student(?! Report Card)/', $out ), 0 );
// The same page with the launch switch on and A a Champion: the first section is printed, first,
// holding the sponsorship card, Events and the badge, a Champion's too; the three others follow
// as above. Drawn after every check above reads $out and the resources list, and put back as it
// was for the sections below.
$GLOBALS['settings']['sponsor_tiers_live'] = true;
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion' ) );
$out_live     = $D::render();
$live_needles = $needles;
array_splice( $live_needles, 4, 0, array( '<h2 class="wpcpm-student__heading wpcpm-sponsor__module-title">Your sponsorship</h2>', 'id="wpcpm-sponsor-sponsorship"', 'id="wpcpm-sponsor-events"', 'id="wpcpm-sponsor-badge"' ) );
$live_order = array();
foreach ( $live_needles as $needle ) { $live_order[] = strpos( $out_live, $needle ); }
$live_sorted = $live_order; sort( $live_sorted );
ck( 'with the switch on, Your sponsorship comes first, right after the help: the sponsorship card, Events, the badge, then What you offer, Mentors and Your company', ! in_array( false, $live_order, true ) && $live_order === $live_sorted, true );
ck( 'the three sections that hold a card here, in the approved order, each opening with its heading and its one sentence, its cards inside it, and Offer more on none of them', array( modules_printed( $out_live ), false !== strpos( $out_live, $openings['sponsorship'] . $first_card( 'sponsorship' ) ), false !== strpos( $out_live, $openings['mentors'] . $first_card( 'mentors' ) ), false !== strpos( $out_live, $openings['company'] . $first_card( 'profile' ) ), modules_closed_after_a_card( $out_live ), substr_count( $out_live, '<p class="wpcpm-student__note wpcpm-sponsor__module-lead">' ), strpos( $out_live, 'wpcpm-sponsor-interests' ) ), array( array( 'sponsorship', 'mentors', 'company' ), true, true, true, 3, 3, false ) );
// The Reports card (1.115.0): one final report of A's, stored as the store stores one, and the card
// takes its place after Events and before the badge while the switch is on. The same report with
// the switch off draws nothing, and the page reads as it did before the report existed.
$final_report = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Report::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Mango Example, October 2025 to September 2026' ) );
update_post_meta( $final_report, WPCPM_Sponsor_Report::META_SPONSOR, $A );
update_post_meta( $final_report, WPCPM_Sponsor_Report::META_UNTIL, '2026-09-30' );
update_post_meta( $final_report, WPCPM_Sponsor_Report::META_STATE, WPCPM_Sponsor_Report::STATE_FINAL );
$out_reports     = $D::render();
$reports_needles = array( '<h2 class="wpcpm-student__heading wpcpm-sponsor__module-title">Your sponsorship</h2>', 'id="wpcpm-sponsor-sponsorship"', 'id="wpcpm-sponsor-events"', 'id="wpcpm-sponsor-reports"', 'id="wpcpm-sponsor-badge"', '<h2 class="wpcpm-student__heading wpcpm-sponsor__module-title">Mentors</h2>' );
$reports_order   = array();
foreach ( $reports_needles as $needle ) { $reports_order[] = strpos( $out_reports, $needle ); }
$reports_sorted = $reports_order; sort( $reports_sorted );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$out_reports_off = $D::render();
ck( 'the Reports card follows Events and comes before the badge in the first section while the switch is on, and is not drawn while it is off, nor its section', array(
	! in_array( false, $reports_order, true ) && $reports_order === $reports_sorted,
	false !== strpos( $out_reports, 'Mango Example, October 2025 to September 2026' ),
	strpos( $out_reports_off, 'id="wpcpm-sponsor-reports"' ),
	strpos( $out_reports_off, 'October 2025 to September 2026' ),
	modules_printed( $out_reports_off ),
), array( true, true, false, false, array( 'mentors', 'company' ) ) );
// The section's line names exactly the cards the section draws for this sponsor, in the section's order
// (1.123.22): every line said "Your tier, your events, your reports and your badge." though a Friend
// has none of the three and a Supporter or a Backer no events. Each card here is drawn by its own
// class, so the line and the cards are read off the same page: the Events card while the tier card's
// own test draws it (here, with no event store, while the tier includes events), the Reports card
// while a final report exists, and the badge while the sponsor is Approved at a tier that includes
// one. The report is A's, written above, final where a case has one and a draft, the program's to
// finish, where it has none: this suite's get_posts() does not read a query's sponsor clause.
/**
 * The Your sponsorship section's line and the cards it drew, by key, in order; null when the section
 * is not printed.
 *
 * @param string $html The page.
 * @return array|null `lead` and `cards`.
 */
function sponsorship_group( $html ) {
	$module = module_of( $html, 'sponsorship' );
	if ( null === $module ) { return null; }
	preg_match( '#<p class="wpcpm-student__note wpcpm-sponsor__module-lead">(.*?)</p>#', $module, $lead );
	preg_match_all( '#<details id="wpcpm-sponsor-([a-z]+)"#', $module, $cards );
	return array( 'lead' => $lead[1] ?? '', 'cards' => $cards[1] );
}
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
	$leads_seen[ $who ] = sponsorship_group( $D::render() );
}
WPCPM_Sponsors_Index::patch( $A, array( 'status' => 'Approved' ) );
ck( 'the section\'s line names exactly the cards the section draws, in the section\'s order, for each of the eight ways the tier\'s events, the reports and the badge can fall', $leads_seen, array(
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
ck( 'B has no logo: initials stand in', false !== strpos( $out, 'wpcpm-sponsor__initials' ) && false !== strpos( $out, '>W<' ), true );
ck( 'B has no manager: no contact block, and no empty heading', strpos( $out, 'wpcpm-resources__contact' ), false );
ck( 'a manager sees the status line', false !== strpos( $out, 'Status: Approved' ), true );
ck( 'the people card points a manager at the accounts of the sponsor being viewed, on the Sponsors screen\'s Accounts tab, where they are attached and removed', false !== strpos( substr( $out, strpos( $out, 'id="wpcpm-sponsor-people"' ) ), '<p class="wpcpm-student__note"><a href="https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=accounts&wpcpm_sponsor=' . $B . '">Attach or remove accounts on the Sponsors screen.</a></p>' ), true );
ck( 'and a sponsor no account acts for reads none on the People row', false !== strpos( $out, '<h3 class="wpcpm-group__title">People <span class="wpcpm-group__count">0</span></h3><span class="wpcpm-sponsor__state">0 accounts</span>' ), true );
$GLOBALS['get'] = array();

echo "\n=== Messages and leave() ===\n";
$GLOBALS['uid'] = 5;
$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => 'profile-saved', 'card' => 'profile' );
$out = $D::render();
ck( 'a flash prints its sentence with the success tone', false !== strpos( $out, 'wpcpm-dashboard__message--success' ) && false !== strpos( $out, 'Your profile was saved' ), true );
ck( 'and opens the card it names', false !== strpos( $out, 'id="wpcpm-sponsor-profile" class="wpcpm-group wpcpm-group__disclosure" open' ), true );
$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => 'refused', 'card' => '', 'detail' => 'Line 3 repeats line 1.' );
ck( 'a flash may carry a detail, printed after the sentence', false !== strpos( $D::render(), '<span class="wpcpm-dashboard__detail">Line 3 repeats line 1.</span>' ), true );
// A refusal for words the cleaner would take has no sentence of its own: the card's whole sentence,
// which names the box, is the detail, and the notice prints it alone. With neither a sentence nor
// a detail, nothing is printed.
$loss_said = WPCPM_Typed_Text::loss_message( WPCPM_Sponsor_Profile::labels()['contact_person'] );
try { $D::leave( 'profile-loss', 'profile', $A, $loss_said ); } catch ( WPCPM_Test_Redirect $e ) {}
$loss_page   = $D::render();
$loss_notice = preg_match( '#<p class="wpcpm-dashboard__message wpcpm-dashboard__message--error">(.*?)</p>#s', $loss_page, $loss_found ) ? $loss_found[1] : null;
ck( 'a status whose sentence is its detail prints the detail alone, one notice in the error tone, read by a browser as the card\'s sentence naming the box', array( substr_count( $loss_page, '<p class="wpcpm-dashboard__message ' ), null === $loss_notice ? null : 0 === strpos( $loss_notice, '<span class="wpcpm-dashboard__detail">' ), null === $loss_notice ? null : html_entity_decode( strip_tags( $loss_notice ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ), array( 1, true, $loss_said ) );
$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => 'profile-loss', 'card' => 'profile', 'detail' => '' );
ck( 'and with neither a sentence nor a detail, nothing is printed', strpos( $D::render(), 'wpcpm-dashboard__message' ), false );
// A detail is text, escaped for the page, after a sentence and alone alike: a flash left by an
// older release or edited by hand can hold markup.
$detail_markup = array();
foreach ( array( 'refused', 'profile-loss' ) as $detail_status ) {
	$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => $detail_status, 'card' => '', 'detail' => 'Line <b>3</b> & "4"' );
	$detail_markup[]               = false !== strpos( $D::render(), '<span class="wpcpm-dashboard__detail">Line &lt;b&gt;3&lt;/b&gt; &amp; &quot;4&quot;</span></p>' );
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
ck( 'the page\'s cards hold one status whose sentence is its detail, the profile\'s refusal for lost words, and two whose sentence names values, a start outside the term; each prints a notice', array( $loss_silent, $loss_printed ), array( array( 'empty' => array( 'profile-loss' ), 'named' => array( 'sevent-past-term', 'sevent-before-term' ) ), array( 'profile-loss' => true, 'sevent-past-term' => true, 'sevent-before-term' => true ) ) );
$loss_child_out    = array();
$loss_child_status = null;
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --loss-child 2>&1', $loss_child_out, $loss_child_status );
$loss_seen = json_decode( (string) end( $loss_child_out ), true );
ck(
	'as the member, a refused save of the profile and of the Offers card each lands on a page that shows the card\'s sentence naming the box, in the error tone, and the box holding what was typed; with the Offers card loaded, its refusal is the second whose sentence is its detail',
	array( $loss_child_status, $loss_seen ),
	array(
		0,
		array(
			'profile'   => array( 'left' => 'profile-loss', 'notice' => array( 'error', WPCPM_Typed_Text::loss_message( 'Contact person' ) ), 'box' => 'Ana <ana@example.test>' ),
			'offers'    => array( 'left' => 'offer-loss', 'notice' => array( 'error', WPCPM_Typed_Text::loss_message( 'Title' ) ), 'box' => 'Kids <12 free, adults >18 pay' ),
			'empty'     => array( 'offer-loss', 'profile-loss' ),
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
ck( 'a flash that carries them prints the sentence with each value where it names it, escaped', false !== strpos( $named, '<p class="wpcpm-dashboard__message wpcpm-dashboard__message--error">Choose a start on or before January 31, 2027, the last day of your sponsorship term on the site&#039;s clock (Europe / Warsaw).</p>' ), true );
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
// folds (1.93.2). A card class left on the disclosure would draw that rhythm on the wrong box.
ck( 'every card is a section wrapping its disclosure', preg_match_all( '#<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-#', $out ), substr_count( $out, '<details id="wpcpm-sponsor-' ) );
ck( 'and at least the profile and the people cards render that way', preg_match_all( '#<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-#', $out ) >= 2, true );
ck( 'the disclosure itself no longer carries the card class', strpos( $out, 'wpcpm-group__disclosure wpcpm-sponsor__card"' ), false );
// A card's action follows its fold inside its section, never inside the summary (27 September
// 2026): so a section closes right after its disclosure, or right after that one action.
ck( 'each section closes right after its disclosure, or after the one action that follows its fold', preg_match_all( '#</details>(?:<p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="[^"]*">[^<]*(?:<span class="screen-reader-text">[^<]*</span>)?</a></p>)?</section>#', $out ), substr_count( $out, '<section class="wpcpm-sponsor__card">' ) );
// The action is printed whatever opened its card, and the stylesheets hide it while the card is open:
// the profile the flash opened still carries its Edit, which shows once the sponsor folds the card.
ck( 'and on this page, whose profile a flash opened, the action is still the profile\'s, after its fold: the stylesheets hide it while the card is open', array( substr_count( $out, 'wpcpm-sponsor__row-action' ), false !== strpos( $out, '<details id="wpcpm-sponsor-profile" class="wpcpm-group wpcpm-group__disclosure" open>' ), false !== strpos( $out, 'href="#wpcpm-sponsor-profile">Edit<span class="screen-reader-text"> the company profile and logo</span></a></p></section>' ) ), array( 1, true, true ) );
ck( 'taken, so it shows once', isset( $GLOBALS['flash'][ $D::FLASH ] ), false );
$GLOBALS['flash'][ $D::FLASH ] = array( 'status' => 'refused', 'card' => '' );
ck( 'a refusal prints with the error tone', false !== strpos( $D::render(), 'wpcpm-dashboard__message--error' ), true );
try { $D::leave( 'profile-saved', 'profile', $A ); } catch ( WPCPM_Test_Redirect $e ) {}
// leave() always writes a 'detail' key too, empty or not: this call passes none, so it is ''.
ck( 'leave() flashes the status and the card', $GLOBALS['flash'][ $D::FLASH ], array( 'status' => 'profile-saved', 'card' => 'profile', 'detail' => '' ) );
ck( 'and sends a member to the page and the card, without the switcher argument', $GLOBALS['redirected'], get_permalink( $page_id ) . '#wpcpm-sponsor-profile' );
$GLOBALS['uid'] = 1;
try { $D::leave( 'profile-saved', 'profile', $A ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'a manager is sent back through the switcher', false !== strpos( $GLOBALS['redirected'], WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A ) && false !== strpos( $GLOBALS['redirected'], '#wpcpm-sponsor-profile' ), true );
ck( 'and the whole address is the page, the sponsor and the card', $GLOBALS['redirected'], get_permalink( $page_id ) . '?' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A . '#wpcpm-sponsor-profile' );
// What the argument may be is the shape of a sponsor key, as the institution side's guard asks for a
// record ID, and not merely "not empty": a value that is no key adds nothing to the address.
foreach ( array( 'nothing at all' => '', 'only spaces' => '   ', 'a word' => 'x', 'a short ID' => 'recSPONSOR', 'markup' => '<b>recSPONSOR0000001</b>' ) as $what => $record ) {
	try { $D::leave( 'profile-saved', 'profile', $record ); } catch ( WPCPM_Test_Redirect $e ) {}
	ck( 'a manager sent back with ' . $what . ' is sent to the page and the card, with no argument', $GLOBALS['redirected'], get_permalink( $page_id ) . '#wpcpm-sponsor-profile' );
}
try { $D::leave( 'profile-saved', 'profile', ' ' . $A . ' ' ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'and a record ID with spaces round it is sent trimmed', $GLOBALS['redirected'], get_permalink( $page_id ) . '?' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A . '#wpcpm-sponsor-profile' );
// A sponsor the site made itself is keyed by mint_key(), `spo` and fourteen characters, which is no
// record ID. It goes back through the switcher as a migrated sponsor does, or a manager's press for
// it would come back on another sponsor: their own, or the first with a member.
$minted = 'spoSITEMADE000001';
ck( 'a minted site key is a key the store could hold, and no record ID', array( WPCPM_Sponsors_Index::is_key( $minted ), WPCPM_Mentors_Sync::is_record_id( $minted ) ), array( true, false ) );
try { $D::leave( 'profile-saved', 'profile', $minted ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'a manager pressing for a sponsor the site made is sent back through the switcher to that sponsor', $GLOBALS['redirected'], get_permalink( $page_id ) . '?' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $minted . '#wpcpm-sponsor-profile' );
try { $D::leave( 'profile-saved', 'profile', ' ' . $minted . "\n" ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'and a site key with white space round it is sent trimmed', $GLOBALS['redirected'], get_permalink( $page_id ) . '?' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $minted . '#wpcpm-sponsor-profile' );
foreach ( array( 'a short site key' => 'spoSITEMADE', 'a site key one too long' => 'spoSITEMADE0000001', 'another prefix' => 'sopSITEMADE000001', 'a capital prefix' => 'SPOSITEMADE000001', 'no scalar' => array( $minted ) ) as $what => $record ) {
	try { $D::leave( 'profile-saved', 'profile', $record ); } catch ( WPCPM_Test_Redirect $e ) {}
	ck( 'a manager sent back with ' . $what . ' is sent to the page and the card, with no argument', $GLOBALS['redirected'], get_permalink( $page_id ) . '#wpcpm-sponsor-profile' );
}
$GLOBALS['uid'] = 5;
try { $D::leave( 'profile-saved', 'profile', $B ); } catch ( WPCPM_Test_Redirect $e ) {}
ck( 'a member is sent to the page and the card whatever record they pass', $GLOBALS['redirected'], get_permalink( $page_id ) . '#wpcpm-sponsor-profile' );
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
// The theme dresses a card's row and the resources in thirds on this page; the plugin's own sheets
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
// A phone's strip (1.123.22), in the Administrator Dashboard's sheet both strips wear: one tile a row,
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
// Every card the page draws is in messages()' union, so a card that gains a handler has its
// sentences in reach without a second list to remember; read from the source, since a card with
// no form, like the Badge card, merges nothing a run could see.
preg_match_all( "/self::MODULE_[A-Z]+\s*=> array\(([^)]*)\)/", $src, $card_lists );
preg_match_all( "/'(WPCPM_[A-Za-z_]+)'/", implode( ' ', $card_lists[1] ), $drawn_cards );
$messages_src = substr( $src, (int) strpos( $src, 'public static function messages()' ) );
$messages_src = substr( $messages_src, 0, (int) strpos( $messages_src, "\n\t}\n" ) );
preg_match_all( "/'(WPCPM_[A-Za-z_]+)'/", $messages_src, $merged_cards );
ck( 'every card the page draws is merged into messages(), the Badge card among them', array( count( $drawn_cards[1] ) > 0, array_values( array_diff( $drawn_cards[1], $merged_cards[1] ) ) ), array( true, array() ) );
// The four sections' cards as the owner approved them on 27 September 2026 (`module_cards()`), read as lists of names
// rather than as a run of source, so a layout with one entry to a line reads the same. messages()
// merges the same cards, with the Events card's planner right after the tier card that draws it and
// the logo right after the profile card that holds it. Its order beyond those two is no behavior: the
// only keys two cards share carry the same sentence in each, so whichever wins the merge reads alike.
$group_cards = array();
foreach ( $card_lists[1] as $card_list ) {
	preg_match_all( "/'([A-Za-z_]+)'/", $card_list, $names );
	$group_cards[] = $names[1];
}
ck( 'four sections of cards, in the approved order: the sponsorship, what the company offers, the mentors, the company', $group_cards, array(
	array( 'WPCPM_Sponsor_Tier_Card', 'WPCPM_Sponsor_Report_Card', 'WPCPM_Sponsor_Badge' ),
	array( 'WPCPM_Sponsor_Offers', 'WPCPM_Sponsor_Usage', 'WPCPM_Sponsor_Posts' ),
	array( 'WPCPM_Sponsor_Mentors' ),
	array( 'WPCPM_Sponsor_Profile', 'people', 'WPCPM_Sponsor_Agreement_Card' ),
) );
$tier_at    = array_search( 'WPCPM_Sponsor_Tier_Card', $merged_cards[1], true );
$profile_at = array_search( 'WPCPM_Sponsor_Profile', $merged_cards[1], true );
// The offers' review (1.123.18) draws no card here: a decision of it posted without the Administrator
// Dashboard's way back lands on the Offers card, so its outcomes are merged, right after the Offers card's.
$offers_at  = array_search( 'WPCPM_Sponsor_Offers', $merged_cards[1], true );
ck( 'messages() merges the offers\' review right after the Offers card, for a decision that lands here', false === $offers_at ? array() : array_slice( $merged_cards[1], $offers_at, 2 ), array( 'WPCPM_Sponsor_Offers', 'WPCPM_Sponsor_Offer_Review' ) );
$merged_set = array_values( array_diff( $merged_cards[1], array( 'WPCPM_Sponsor_Event_Planner', 'WPCPM_Sponsor_Logo', 'WPCPM_Sponsor_Offer_Review' ) ) );
$drawn_set  = array_values( array_diff( array_merge( array(), ...$group_cards ), array( 'people' ) ) );
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

echo "\n=== The badge beside the details (1.115.1) ===\n";
// A Champion, switch on: the header carries the light badge, built from the same helpers the
// Badge card itself uses, so the two never describe the badge differently, at the versioned address
// (1.123.22), so a change of level shows at once rather than once a browser's copy has expired.
$GLOBALS['uid'] = 1; $GLOBALS['get'] = array( WPCPM_Sponsor_Roster::ARG_VIEW => $A );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion' ) );
$mango        = WPCPM_Sponsors_Index::row( $A );
$header_badge = sprintf(
	'<div class="wpcpm-sponsor__badge"><img class="wpcpm-sponsor__badge-image" src="%1$s" alt="%2$s" width="%3$d" height="%4$d" loading="lazy" decoding="async" /></div>',
	esc_url( WPCPM_Sponsor_Badge::versioned_url( $mango ) ),
	esc_attr( WPCPM_Sponsor_Badge::light_alt( $mango ) ),
	WPCPM_Sponsor_Badge::WIDTH,
	WPCPM_Sponsor_Badge::HEIGHT
);
ck( 'a Champion, switch on: the header carries the light badge with the Badge card\'s own alt, at the address that names its tier and year', array( false !== strpos( $D::render(), $header_badge ), false !== strpos( $header_badge, '?v=champion-' ) ), array( true, true ) );

$GLOBALS['settings']['sponsor_tiers_live'] = false;
ck( 'the same Champion, switch off: nothing in the header', strpos( $D::render(), 'wpcpm-sponsor__badge' ), false );

$GLOBALS['settings']['sponsor_tiers_live'] = true;
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'mentor' ) );
ck( 'a Friend, switch on: no badge, since its tier carries none', strpos( $D::render(), 'wpcpm-sponsor__badge' ), false );

// A name that sanitizes to nothing (sanitize_title() strips every character of it) leaves
// insert() no slug to build an address from, even though the row is otherwise eligible.
$C = 'spoNOSLUG00000001';
WPCPM_Sponsors_Index::write( array( $C => array( 'name' => '###', 'status' => 'Approved', 'tier' => 'champion' ) ), time() );
ck( 'that row really holds no slug', WPCPM_Sponsors_Index::row( $C )['slug'], '' );
$GLOBALS['get'] = array( WPCPM_Sponsor_Roster::ARG_VIEW => $C );
ck( 'a row with no slug, switch on, otherwise eligible: still no badge, since there is no address for it', strpos( $D::render(), 'wpcpm-sponsor__badge' ), false );

WPCPM_Sponsors_Index::patch( $A, array( 'tier' => '' ) );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$GLOBALS['get'] = array();

echo "\n=== At a glance ===\n";
// The strip under the header (the owner, 27 September 2026; Institutions release 3, 25 October 2026):
// the tier first, while the tiers are live, then one tile for each kind of thing waiting on the
// sponsor's people, each a link to the card that holds it and drawn only when that card is, and,
// when nothing waits at all, the muted Nothing waits for you. The figure tiles (the next event, the
// offers live, the mentors, the yearly reports) are gone: their figures show in their cards' lines.
// From here on today is a known day, so a term's days are counted from it, and the date format is
// the one the site writes a day in.
$GLOBALS['today']               = '2026-10-06';
$GLOBALS['opts']['date_format'] = 'F j, Y';
$GLOBALS['opts']['time_format'] = 'g:i a';
$GLOBALS['uid']                 = 5;
$GLOBALS['get']                 = array();
$M1 = 'recMENTOR00000001'; $M2 = 'recMENTOR00000002'; $M3 = 'recMENTOR00000003';
// The profile's details are the fixture's three, filled, and the checks below empty some of them and put them back.
$A_email = (string) WPCPM_Sponsors_Index::row( $A )['contact_email'];
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => '', 'tier_until' => '', 'mentors' => array( $M1, $M2, $M3 ) ) );

/**
 * The strip a page printed, from its opening tag to its closing one, or '' for a page with none.
 *
 * @param string $html The page.
 * @return string
 */
function glance_of( $html ) {
	$at = strpos( $html, '<section class="wpcpm-sponsor__card wpcpm-attention wpcpm-sponsor__glance"' );
	if ( false === $at ) { return ''; }
	$end = strpos( $html, '</ul></section>', $at );
	return false === $end ? substr( $html, $at ) : substr( $html, $at, $end + strlen( '</ul></section>' ) - $at );
}
/**
 * One tile as the Administrator Dashboard draws its own, linked to a card of this page.
 *
 * @param string $card    The card's key.
 * @param string $value   What the tile's number line says, as printed.
 * @param string $label   What its label says, as printed.
 * @param bool   $zero    Whether it is muted.
 * @param bool   $waiting Whether it carries the waiting mark.
 * @return string
 */
function glance_tile( $card, $value, $label, $zero = false, $waiting = false ) {
	return '<li class="wpcpm-attention__tile' . ( $zero ? ' wpcpm-attention__tile--zero' : '' ) . ( $waiting ? ' wpcpm-attention__tile--waiting' : '' ) . '"><a class="wpcpm-attention__link" href="#wpcpm-sponsor-' . $card . '"><span class="wpcpm-attention__n">' . $value . '</span><span class="wpcpm-attention__l">' . $label . '</span></a></li>';
}
/**
 * The tile for a sponsor with nothing waiting: muted, the number 0, and no link, since no card holds
 * nothing. Its link's box is a span of the same class, so the one set of tile rules dresses it.
 *
 * @return string
 */
function glance_none() {
	return '<li class="wpcpm-attention__tile wpcpm-attention__tile--zero"><span class="wpcpm-attention__link"><span class="wpcpm-attention__n">0</span><span class="wpcpm-attention__l">Nothing waits for you</span></span></li>';
}
/**
 * The strip around its tiles: a card of this page, the Administrator Dashboard's strip inside it.
 *
 * @param string[] $tiles The tiles, as glance_tile() writes them.
 * @return string
 */
function glance_strip( array $tiles ) {
	return '<section class="wpcpm-sponsor__card wpcpm-attention wpcpm-sponsor__glance" id="wpcpm-sponsor-glance" aria-label="At a glance"><ul class="wpcpm-attention__tiles">' . implode( '', $tiles ) . '</ul></section>';
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
 * The cards a page's tiles link to that the page did not draw: none, when every tile lands on a card.
 *
 * @param string $html The page.
 * @return string[]
 */
function tiles_without_card( $html ) {
	preg_match_all( '/<a class="wpcpm-attention__link" href="#wpcpm-sponsor-([a-z]+)">/', $html, $m );
	return array_values( array_filter( $m[1], static function ( $card ) use ( $html ) { return false === strpos( $html, '<details id="wpcpm-sponsor-' . $card . '"' ); } ) );
}
/**
 * The `Y-m-d` day this many days after the pinned today; a negative count is a day already past.
 *
 * @param int $days Days.
 * @return string
 */
function glance_day( $days ) { return gmdate( 'Y-m-d', strtotime( $GLOBALS['today'] . ' 00:00:00 UTC' ) + (int) $days * DAY_IN_SECONDS ); }
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
function glance_event( $record, $state, $start, $minutes, $title, array $people = array() ) {
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

// With none of the cards a sponsor's people wait on installed but the profile's, a Friend with the
// switch off has nothing to be told: the strip is "Nothing waits for you" alone, a strip of one tile
// (the old rule that one tile is no strip is gone), muted, the number 0, and no link, since no card
// holds nothing.
$only_none = $D::render();
ck( 'a Friend with the switch off, nothing waiting and no waiting card but a complete profile: the strip is "Nothing waits for you" alone, muted, with no link, right under the header', array(
	glance_of( $only_none ),
	false !== strpos( $only_none, '</header>' . glance_strip( array( glance_none() ) ) ),
	substr_count( glance_of( $only_none ), '<a ' ),
	tiles_without_card( $only_none ),
), array( glance_strip( array( glance_none() ) ), true, 0, array() ) );

// From here on the Offers, Posts and Collaboration Agreement cards are stand-ins drawing their cards in
// the shell under their own anchors, each answering the one question the strip asks of it, as the
// fixture sets it per sponsor (their own suites are bin/test-sponsor-offers.php, bin/test-sponsor-posts.php
// and bin/test-sponsor-cards.php, which pin the readers against the cards). The event store and what the
// planner reads are the real classes, as bin/test-sponsor-cards.php loads them, with the store's clock at
// noon on the pinned today, so the Events card holds a real planner and the strip reads the real store.
if ( ! class_exists( 'WPCPM_Sponsor_Offers' ) ) {
	/** The Offers card, drawn in the shell, and the counts of what waits on its sponsor. */
	class WPCPM_Sponsor_Offers {
		const CARD = 'offers';
		public static function waiting_counts( $record, $today = '' ) { return isset( $GLOBALS['offers_waiting'][ $record ] ) ? $GLOBALS['offers_waiting'][ $record ] : array( 'returned' => 0, 'low' => 0, 'draft' => 0 ); }
		public static function render( $record, array $context ) { WPCPM_Dashboard_Card::open( array( 'key' => self::CARD, 'title' => 'Offers and codes' ) ); echo '<p>The offers.</p>'; WPCPM_Dashboard_Card::close(); }
	}
}
if ( ! class_exists( 'WPCPM_Sponsor_Posts' ) ) {
	/**
	 * The Posts card, drawn in the shell for a sponsor `$GLOBALS['posts_drawn']` names, its own test for
	 * whether it draws, which What you offer's line and the strip ask, and the count of its returned
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
// about the sponsor, so there is no strip, however much the stand-ins say waits for it and however many
// of its cards are drawn. Asked before any event or report exists: this suite's get_posts() does not
// read a query's sponsor clause, and would answer another sponsor's.
$GLOBALS['users'][7] = new WP_User( 7, array( 'wpcpm_sponsor' ), 'Rep Seven', 'sponsor-member@example.test' );
$GLOBALS['umeta'][7] = array( WPCPM_Sponsor_Members::META_RECORD_ID => 'recSPONSOR0000009', WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['uid']      = 7;
$GLOBALS['offers_waiting']    = array( 'recSPONSOR0000009' => offers_waiting( 2, 1, 1 ) );
$GLOBALS['posts_drawn']       = array( 'recSPONSOR0000009' => true );
$GLOBALS['posts_returned']    = array( 'recSPONSOR0000009' => 1 );
$GLOBALS['agreement_waiting'] = array( 'recSPONSOR0000009' => 'returned' );
$rowless_off                  = $D::render();
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$rowless_on = $D::render();
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$GLOBALS['offers_waiting']    = array();
$GLOBALS['posts_drawn']       = array();
$GLOBALS['posts_returned']    = array();
$GLOBALS['agreement_waiting'] = array();
$GLOBALS['uid']               = 5;
ck( 'nothing is printed for a sponsor the store holds no row for, whose tiles would read only the empty row: not with the switch off, its offers, mentors, posts and profile cards drawn, nor with it on, its sponsorship card drawn too, though offers, a post and the agreement wait', array(
	false !== strpos( $rowless_on, 'wpcpm-sponsor__identity' ),
	false !== strpos( $rowless_off, '<details id="wpcpm-sponsor-offers"' ) && false !== strpos( $rowless_off, '<details id="wpcpm-sponsor-mentors"' ) && false !== strpos( $rowless_off, '<details id="wpcpm-sponsor-profile"' ),
	false !== strpos( $rowless_on, '<details id="wpcpm-sponsor-sponsorship"' ),
	glance_of( $rowless_off ),
	glance_of( $rowless_on ),
	strpos( $rowless_on . $rowless_off, 'wpcpm-attention' ),
), array( true, true, true, '', '', false ) );

// A Friend, the switch off, with every card installed and nothing waiting: no tile a tier decides and
// no figure tile, though its offers, mentors and profile cards are drawn.
$mentor_off = $D::render();
$none_strip = glance_strip( array( glance_none() ) );
ck( 'a Friend with the switch off and nothing waiting: "Nothing waits for you" alone, in the Administrator Dashboard\'s markup under this page\'s own name, right under the header, and no tile for its offers or its three mentors', array(
	glance_of( $mentor_off ),
	false !== strpos( $mentor_off, '</header>' . $none_strip ),
	tiles_without_card( $mentor_off ),
	false !== strpos( $mentor_off, '<details id="wpcpm-sponsor-offers"' ) && false !== strpos( $mentor_off, '<details id="wpcpm-sponsor-mentors"' ),
	strpos( $mentor_off, 'href="#wpcpm-sponsor-mentors"' ),
), array( $none_strip, true, array(), true, false ) );
// The Mentors card still counts what the strip used to: the mentors the mentors sync's index holds,
// drawn by name, and the ones it does not, counted as not currently active. One of A's three is in the
// index here, so both halves of the count are read, beside the card's title where the figure stays.
$GLOBALS['mentors_index'] = array( $M1 => array( 'name' => 'Mentor One', 'profile' => '', 'user_id' => 0, 'expertise' => array(), 'status' => 'Active', 'wants' => false, 'sponsored' => true ) );
$indexed                  = $D::render();
$GLOBALS['mentors_index'] = array();
ck( 'the mentors the strip no longer counts are still counted beside the Mentors card\'s title: a mentor the index holds and the ones it does not', array(
	false !== strpos( $indexed, '<h3 class="wpcpm-group__title">Mentors you sponsor <span class="wpcpm-group__count">3</span></h3>' ),
	false !== strpos( $indexed, '<h4 class="wpcpm-mentor-tile__name">Mentor One</h4>' ),
	false !== strpos( $indexed, 'And 2 mentors who are not currently active.' ),
	false !== strpos( glance_of( $indexed ), 'mentors sponsored' ),
), array( true, true, true, false ) );
// The help follows the strip directly (Institutions release 3), where the resources once closed the page
// under the cards, and the cards follow the help.
ck( 'the help follows the strip directly, and the first section follows the help', array( false !== strpos( $mentor_off, $none_strip . '<section class="wpcpm-student__section wpcpm-handbook__resources' ), strpos( $mentor_off, 'wpcpm-handbook__resources' ) < strpos( $mentor_off, '<div class="wpcpm-module wpcpm-module--offer" id="wpcpm-module-offer">' ) ), array( true, true ) );
// What you offer, once a card of it is drawn: its line as before, the Offers card first, and no Offer
// more after the cards this suite draws (1.123.22).
$offer_group = module_after_arrows( module_of( $mentor_off, 'offer' ) );
// Its line says what the company wrote for them only while the group draws the Posts card, asked of the
// card's own test (1.123.22): a Friend or a Supporter with no post of its own has no Posts card, and
// the line then says what the company gives alone. Each line is one whole sentence in the source.
$short_offer = $module_opening( 'What you offer', 'What your company gives the program&#039;s students and mentors.' );
$GLOBALS['posts_drawn'] = array( $A => true );
$with_posts             = $D::render();
$GLOBALS['posts_drawn'] = array();
$offer_src              = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-dashboard.php' );
ck( 'What you offer opens on its line and the Offers card, and holds no Offer more; with no Posts card its line says what the company gives alone, and with one what it wrote for them too', array(
	0 === strpos( $offer_group, $short_offer . $first_card( 'offers' ) ),
	substr_count( $offer_group, '<section class="wpcpm-sponsor__card">' ),
	strpos( $offer_group, 'wpcpm-sponsor-interests' ),
	false !== strpos( $with_posts, $openings['offer'] . $first_card( 'offers' ) ),
	false !== strpos( $with_posts, '<details id="wpcpm-sponsor-posts"' ),
	array( substr_count( $offer_src, "__( 'What your company gives the program\\'s students and mentors.', 'wpcredits-program-manager' )" ), substr_count( $offer_src, "__( 'What your company gives the program\\'s students and mentors, and what it wrote for them.', 'wpcredits-program-manager' )" ) ),
), array( true, 1, false, true, true, array( 1, 1 ) ) );

// One tile waiting is a strip: the tiers off, an offer returned with a note, the waiting mark on its
// tile, a link to the Offers card, and no "Nothing waits for you" beside it.
$GLOBALS['offers_waiting'] = array( $A => offers_waiting( 1 ) );
$one_waiting               = $D::render();
ck( 'the switch off, one offer returned with a note: that tile alone is the strip, marked as waiting and linked to the Offers card, and nothing says that nothing waits', array(
	glance_of( $one_waiting ),
	tiles_without_card( $one_waiting ),
	false !== strpos( glance_of( $one_waiting ), 'Nothing waits for you' ),
), array( glance_strip( array( glance_tile( 'offers', '1', 'Offer returned with a note', false, true ) ) ), array(), false ) );

// Each kind has its tile, in the order the owner approved, a kind at 0 has none, and a company with
// two details missing counts them: offers returned (two, so plural), one running low and three not
// switched on yet, a returned post, the agreement sent back, and the profile's two missing details.
// The tiers are off, so no tier tile and no event tile come with them.
$GLOBALS['offers_waiting']    = array( $A => offers_waiting( 2, 1, 3 ) );
$GLOBALS['posts_drawn']       = array( $A => true );
$GLOBALS['posts_returned']    = array( $A => 1 );
$GLOBALS['agreement_waiting'] = array( $A => 'returned' );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => '', 'contact_email' => '' ) );
$all_off = $D::render();
ck( 'the switch off, every kind waiting: the offers returned, running low and not switched on yet, the post, the agreement and the profile\'s missing details, each tile marked as waiting and lands on its card, in that order, and no "Nothing waits for you"', array(
	glance_of( $all_off ),
	tiles_without_card( $all_off ),
), array( glance_strip( array(
	glance_tile( 'offers', '2', 'Offers returned with a note', false, true ),
	glance_tile( 'offers', '1', 'Offer running low on codes', false, true ),
	glance_tile( 'offers', '3', 'Offers not switched on yet', false, true ),
	glance_tile( 'posts', '1', 'Post returned with a note', false, true ),
	glance_tile( 'agreement', '1', 'Collaboration Agreement returned', false, true ),
	glance_tile( 'profile', '2', 'Company profile details missing', false, true ),
) ), array() ) );
// The singular and the plural of each label: one returned offer and two running low, one not switched
// on yet, the agreement out of force, not sent back, and one profile detail missing.
$GLOBALS['offers_waiting']    = array( $A => offers_waiting( 1, 2, 1 ) );
$GLOBALS['agreement_waiting'] = array( $A => 'revoked' );
$GLOBALS['posts_returned']    = array( $A => 2 );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => 'plugins.mango-example.test' ) );
$all_ones = $D::render();
ck( 'the other number of each label: an offer returned, two running low, one not switched on yet, two posts returned, the agreement revoked and one profile detail missing', array( glance_of( $all_ones ), tiles_without_card( $all_ones ) ), array( glance_strip( array(
	glance_tile( 'offers', '1', 'Offer returned with a note', false, true ),
	glance_tile( 'offers', '2', 'Offers running low on codes', false, true ),
	glance_tile( 'offers', '1', 'Offer not switched on yet', false, true ),
	glance_tile( 'posts', '2', 'Posts returned with a note', false, true ),
	glance_tile( 'agreement', '1', 'Collaboration Agreement revoked', false, true ),
	glance_tile( 'profile', '1', 'Company profile detail missing', false, true ),
) ), array() ) );
// A kind at 0 draws no tile, whatever its neighbors hold: only the offers not switched on yet, and the
// numbers read as the site writes numbers.
$GLOBALS['offers_waiting']    = array( $A => offers_waiting( 0, 0, 1500 ) );
$GLOBALS['agreement_waiting'] = array();
$GLOBALS['posts_returned']    = array();
WPCPM_Sponsors_Index::patch( $A, array( 'contact_email' => $A_email ) );
ck( 'a kind at zero draws no tile, and a count reads as the site writes numbers: fifteen hundred offers not switched on yet, alone', glance_of( $D::render() ), glance_strip( array( glance_tile( 'offers', '1,500', 'Offers not switched on yet', false, true ) ) ) );
// Posts: the tile is drawn only while the Posts card is, asked the card's own way (draws()), however
// many posts it counts as returned.
$GLOBALS['offers_waiting'] = array();
$GLOBALS['posts_drawn']    = array();
$GLOBALS['posts_returned'] = array( $A => 3 );
$posts_gone                = $D::render();
ck( 'a returned post makes no tile while the Posts card is not drawn: neither the card nor a link to it', array( glance_of( $posts_gone ), strpos( $posts_gone, '<details id="wpcpm-sponsor-posts"' ), strpos( $posts_gone, 'href="#wpcpm-sponsor-posts"' ) ), array( $none_strip, false, false ) );
$GLOBALS['posts_drawn'] = array( $A => true );
ck( 'and one tile for them while it is, counting the three', glance_of( $D::render() ), glance_strip( array( glance_tile( 'posts', '3', 'Posts returned with a note', false, true ) ) ) );
$GLOBALS['posts_drawn']    = array();
$GLOBALS['posts_returned'] = array();

// The tier tile, first once the switch is on, in the words the tile has always had, and a waiting item
// in the last thirty days of a term and from the day after it ended: it takes the waiting mark and opens
// the Sponsorship card. Thirty-one days left, no term, and a date left on a Friend's row (no term to
// speak for) do not. Nothing else waits, so a tile that does not is followed by "Nothing waits for you",
// and a tile that does stands alone.
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$tier_cases = array(
	array( 'champion', glance_day( 100 ), 'Champion', 'to January 14, 2027, 100 days', false ),
	array( 'champion', glance_day( 31 ), 'Champion', 'to November 6, 2026, 31 days', false ),
	array( 'champion', glance_day( 30 ), 'Champion', 'to November 5, 2026, 30 days', true ),
	array( 'champion', glance_day( 1 ), 'Champion', 'to October 7, 2026, 1 day', true ),
	array( 'champion', glance_day( 0 ), 'Champion', 'to October 6, 2026, ending today', true ),
	array( 'champion', glance_day( -1 ), 'Friend', 'term ended October 5, 2026', true ),
	array( 'backer', glance_day( -200 ), 'Friend', 'term ended March 20, 2026', true ),
	array( 'backer', '', 'Backer', 'no term', false ),
	array( '', '', 'Friend', 'no term', false ),
	array( '', glance_day( 10 ), 'Friend', 'no term', false ),
);
foreach ( $tier_cases as $case ) {
	WPCPM_Sponsors_Index::patch( $A, array( 'tier' => $case[0], 'tier_until' => $case[1] ) );
	$tier_page = $D::render();
	$tier_only = glance_tile( 'sponsorship', $case[2], $case[3], false, $case[4] );
	ck( 'the tier tile first: ' . $case[2] . ', ' . $case[3] . ( $case[4] ? ', waiting, alone as the strip' : ', not waiting, then "Nothing waits for you"' ), array(
		glance_of( $tier_page ),
		tiles_without_card( $tier_page ),
	), array( glance_strip( $case[4] ? array( $tier_only ) : array( $tier_only, glance_none() ) ), array() ) );
}
// The Sponsorship card's own row and note say the same of the same term: the thirty-day note is drawn
// exactly where the tile is waiting for a running term, and the tile and the row read the term alike.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion', 'tier_until' => glance_day( 30 ) ) );
$thirty = $D::render();
WPCPM_Sponsors_Index::patch( $A, array( 'tier_until' => glance_day( 31 ) ) );
$thirty_one = $D::render();
ck( 'the tile is waiting exactly where the Sponsorship card says how many days are left: at thirty days, not at thirty-one', array(
	false !== strpos( glance_of( $thirty ), 'wpcpm-attention__tile--waiting' ),
	false !== strpos( $thirty, 'Your term ends on November 5, 2026, in 30 days.' ),
	false !== strpos( glance_of( $thirty_one ), 'wpcpm-attention__tile--waiting' ),
	false !== strpos( $thirty_one, 'Your term ends on' ),
), array( true, true, false, false ) );

// The tier tile is a waiting item among the others, and does not stand alone when they wait too: a term
// with a hundred days left, and an offer returned, is the tier, unmarked, then the offer, marked, and no
// "Nothing waits for you"; with twenty days left both are marked.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion', 'tier_until' => glance_day( 100 ) ) );
$GLOBALS['offers_waiting'] = array( $A => offers_waiting( 1 ) );
$mixed = $D::render();
WPCPM_Sponsors_Index::patch( $A, array( 'tier_until' => glance_day( 20 ) ) );
$mixed_waiting = $D::render();
ck( 'the tier tile unmarked, then a waiting kind; and both marked in the last thirty days; no "Nothing waits for you" in either', array( glance_of( $mixed ), glance_of( $mixed_waiting ) ), array(
	glance_strip( array( glance_tile( 'sponsorship', 'Champion', 'to January 14, 2027, 100 days' ), glance_tile( 'offers', '1', 'Offer returned with a note', false, true ) ) ),
	glance_strip( array( glance_tile( 'sponsorship', 'Champion', 'to October 26, 2026, 20 days', false, true ), glance_tile( 'offers', '1', 'Offer returned with a note', false, true ) ) ),
) );
$GLOBALS['offers_waiting'] = array();

// The figures that were tiles are gone, though every one of them is there to be shown: a Champion with
// an event coming up and two people on it, a final report and a draft, which is the program's to finish,
// and three mentors. The strip holds the tier and "Nothing waits for you" and none of their words; each
// figure shows on its card's line, where it stays.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion', 'tier_until' => glance_day( 100 ) ) );
$next_event   = glance_event( $A, 'approved', strtotime( '2026-10-20 15:00:00 UTC' ), 60, 'Mango "live" & friends', array( 42, 43 ) );
$glance_final = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Report::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Mango Example, October 2025 to September 2026' ) );
update_post_meta( $glance_final, WPCPM_Sponsor_Report::META_SPONSOR, $A );
update_post_meta( $glance_final, WPCPM_Sponsor_Report::META_UNTIL, '2026-09-30' );
update_post_meta( $glance_final, WPCPM_Sponsor_Report::META_STATE, WPCPM_Sponsor_Report::STATE_FINAL );
$glance_draft = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Report::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Mango Example, October 2026 to September 2027' ) );
update_post_meta( $glance_draft, WPCPM_Sponsor_Report::META_SPONSOR, $A );
update_post_meta( $glance_draft, WPCPM_Sponsor_Report::META_UNTIL, '2027-09-30' );
$champion = $D::render();
$strip    = glance_of( $champion );
ck( 'a Champion with an event coming up, a final report and three mentors: the strip is the tier and "Nothing waits for you", and none of the next event\'s title, "offers live", "mentors sponsored" or the yearly reports is in it', array(
	$strip,
	tiles_without_card( $champion ),
	array_map( static function ( $words ) use ( $strip ) { return false === stripos( $strip, $words ); }, array( 'Mango', 'Oct 20', 'joined', 'offers live', 'claim', 'mentors sponsored', 'yearly report', 'no event planned' ) ),
), array( glance_strip( array( glance_tile( 'sponsorship', 'Champion', 'to January 14, 2027, 100 days' ), glance_none() ) ), array(), array( true, true, true, true, true, true, true, true ) ) );
ck( 'each figure the strip lost stays on its card\'s line: the event, the yearly reports, the mentors', array( page_row( $champion, 'events' ), page_row( $champion, 'reports' ), page_row( $champion, 'mentors' ) ), array( 'Mango &quot;live&quot; &amp; friends on October 20, 2026, 2 joined', '1 yearly report, the latest for October 2025 to September 2026', '3 mentors' ) );

// The Events tile: one when the sponsor's latest event was returned with a note (the event store's
// returned_note()) and the Events card can show it, which it does only inside the planner's plan form,
// drawn only when the planner's allowance is '' (WPCPM_Sponsor_Event_Planner::allowance(), which implies
// the card's own draws_events() and the launch switch). The tile opens the Events card. The event the
// sponsor planned is canceled by the program with a note, and the planner stands it above the form.
// Another event, canceled so that it counts for nothing, is held back to be approved in the past below,
// to spend the term; it is made before the returned one, which must stay the sponsor's latest.
$GLOBALS['pmeta'][ $next_event ][ WPCPM_Sponsor_Event::META_STATE ] = WPCPM_Sponsor_Event::STATE_CANCELLED;
$past_event     = glance_event( $A, WPCPM_Sponsor_Event::STATE_CANCELLED, strtotime( '2026-09-20 15:00:00 UTC' ), 60, 'Mango last month' );
$returned_event = glance_event( $A, WPCPM_Sponsor_Event::STATE_CANCELLED, strtotime( '2026-11-02 15:00:00 UTC' ), 60, 'Mango after the term' );
$GLOBALS['pmeta'][ $returned_event ][ WPCPM_Sponsor_Event::META_DECISION ] = array( 'by' => 1, 'at' => 0, 'note' => 'Please add an agenda.' );
$event_page  = $D::render();
$events_card = substr( $event_page, (int) strpos( $event_page, '<details id="wpcpm-sponsor-events"' ) );
$events_card = substr( $events_card, 0, (int) strpos( $events_card, '</section>' ) );
ck( 'the latest event returned with a note: one "Event returned" tile, marked as waiting, after the tier\'s and linked to the Events card, which is drawn and says the same note', array(
	WPCPM_Sponsor_Event::returned_note( $A ),
	glance_of( $event_page ),
	tiles_without_card( $event_page ),
	false !== strpos( $events_card, 'Your last plan was returned: Please add an agenda.' ),
), array( 'Please add an agenda.', glance_strip( array( glance_tile( 'sponsorship', 'Champion', 'to January 14, 2027, 100 days' ), glance_tile( 'events', '1', 'Event returned', false, true ) ) ), array(), true ) );
// A Backer's tier has no events, so with nothing coming the card is not drawn and the tile is not, though
// the note is still there to read.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'backer' ) );
$backer = $D::render();
ck( 'the Events tile is drawn only where the Events card shows the note: not for a Backer with nothing coming, though the note is held', array(
	WPCPM_Sponsor_Event::returned_note( $A ),
	glance_of( $backer ),
	strpos( $backer, '<details id="wpcpm-sponsor-events"' ),
	strpos( $backer, 'href="#wpcpm-sponsor-events"' ),
), array( 'Please add an agenda.', glance_strip( array( glance_tile( 'sponsorship', 'Backer', 'to January 14, 2027, 100 days' ), glance_none() ) ), false, false ) );
// An event of its own still to take place draws the Events card for any tier (the card is where the
// sponsor reads that the event is no longer offered, or cancels it), and the card's own test,
// draws_events(), counts it. The note is printed only inside the plan form, which that card does not draw
// for a Backer (no planner) nor for a Champion that holds an event open (one_open): the tile is not drawn
// either, the note being held, since it would say a return the card does not.
$GLOBALS['pmeta'][ $next_event ][ WPCPM_Sponsor_Event::META_STATE ] = WPCPM_Sponsor_Event::STATE_APPROVED;
$backer_event = $D::render();
$backer_card  = substr( $backer_event, (int) strpos( $backer_event, '<details id="wpcpm-sponsor-events"' ) );
$backer_card  = substr( $backer_card, 0, (int) strpos( $backer_card, '</section>' ) );
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion' ) );
$open_event = $D::render();
$open_card  = substr( $open_event, (int) strpos( $open_event, '<details id="wpcpm-sponsor-events"' ) );
$open_card  = substr( $open_card, 0, (int) strpos( $open_card, '</section>' ) );
$open_row   = WPCPM_Sponsors_Index::row( $A );
ck( 'an event still to take place draws the Events card but not the plan form, so no tile: not for a Backer (no planner), nor for a Champion with one open, though the card\'s own test counts the event and the note is held', array(
	WPCPM_Sponsor_Tier_Card::draws_events( $A, $open_row, 'backer' ),
	WPCPM_Sponsor_Event_Planner::allowance( $A, $open_row, 'backer' ),
	false !== strpos( $backer_card, 'Mango' ) || false !== strpos( $backer_card, 'wpcpm-sevent__own' ),
	false !== strpos( $backer_card, 'Your last plan was returned' ),
	glance_of( $backer_event ),
	WPCPM_Sponsor_Tier_Card::draws_events( $A, $open_row, 'champion' ),
	WPCPM_Sponsor_Event_Planner::allowance( $A, $open_row, 'champion' ),
	false !== strpos( $open_card, 'wpcpm-sevent__own' ),
	false !== strpos( $open_card, 'Your last plan was returned' ),
	glance_of( $open_event ),
	tiles_without_card( $open_event ),
), array(
	true,
	'no_tier',
	true,
	false,
	glance_strip( array( glance_tile( 'sponsorship', 'Backer', 'to January 14, 2027, 100 days' ), glance_none() ) ),
	true,
	'one_open',
	true,
	false,
	glance_strip( array( glance_tile( 'sponsorship', 'Champion', 'to January 14, 2027, 100 days' ), glance_none() ) ),
	array(),
) );
// With the switch off the tier card draws nothing, the Events card with it, though the card's own test
// counts the event still to take place: the tile is not drawn either, and no link points at nothing.
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$events_off    = $D::render();
$counts_anyway = WPCPM_Sponsor_Tier_Card::draws_events( $A, WPCPM_Sponsors_Index::row( $A ), 'champion' );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
ck( 'with the switch off the Events card is not drawn and the tile is not, though the card\'s own test counts the event', array( $counts_anyway, glance_of( $events_off ), strpos( $events_off, '<details id="wpcpm-sponsor-events"' ) ), array( true, $none_strip, false ) );
$GLOBALS['pmeta'][ $next_event ][ WPCPM_Sponsor_Event::META_STATE ] = WPCPM_Sponsor_Event::STATE_CANCELLED;
// A Champion whose term's event has taken place holds the planner (the card is drawn, and says the term
// is spent) without the plan form that carries the note: the term is used, and so no tile.
$GLOBALS['pmeta'][ $past_event ][ WPCPM_Sponsor_Event::META_STATE ] = WPCPM_Sponsor_Event::STATE_APPROVED;
$used_page = $D::render();
$used_card = substr( $used_page, (int) strpos( $used_page, '<details id="wpcpm-sponsor-events"' ) );
$used_card = substr( $used_card, 0, (int) strpos( $used_card, '</section>' ) );
$used_allowance = WPCPM_Sponsor_Event_Planner::allowance( $A, WPCPM_Sponsors_Index::row( $A ), 'champion' );
$used_note      = WPCPM_Sponsor_Event::returned_note( $A );
$GLOBALS['pmeta'][ $past_event ][ WPCPM_Sponsor_Event::META_STATE ] = WPCPM_Sponsor_Event::STATE_CANCELLED;
ck( 'a Champion whose term is used (its event took place) has the Events card and no plan form, so no tile, though the card\'s own test is true and the note is held', array(
	$used_allowance,
	$used_note,
	false !== strpos( $used_page, '<details id="wpcpm-sponsor-events"' ),
	false !== strpos( $used_card, 'Your last plan was returned' ),
	glance_of( $used_page ),
	tiles_without_card( $used_page ),
), array( 'term_used', 'Please add an agenda.', true, false, glance_strip( array( glance_tile( 'sponsorship', 'Champion', 'to January 14, 2027, 100 days' ), glance_none() ) ), array() ) );
// A plan made after the return puts the note away, and the tile goes with it.
$replanned = glance_event( $A, WPCPM_Sponsor_Event::STATE_PLANNED, strtotime( '2026-11-20 15:00:00 UTC' ), 60, 'Mango again' );
$replan    = $D::render();
ck( 'a new plan puts the returned note away, and the tile with it', array( WPCPM_Sponsor_Event::returned_note( $A ), false !== strpos( glance_of( $replan ), 'Event returned' ) ), array( '', false ) );
// The new plan is returned in its turn, so the sponsor's latest event holds a note again.
$GLOBALS['pmeta'][ $replanned ][ WPCPM_Sponsor_Event::META_STATE ] = WPCPM_Sponsor_Event::STATE_CANCELLED;
$GLOBALS['pmeta'][ $replanned ][ WPCPM_Sponsor_Event::META_DECISION ] = array( 'by' => 1, 'at' => 0, 'note' => 'Please add an agenda.' );

// All eight at once, in the order the owner approved: the tier (in its last thirty days), the offers
// returned, running low and not switched on yet, the post, the event, the agreement and the profile.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion', 'tier_until' => glance_day( 20 ), 'contact_email' => '' ) );
$GLOBALS['offers_waiting']    = array( $A => offers_waiting( 1, 1, 1 ) );
$GLOBALS['posts_drawn']       = array( $A => true );
$GLOBALS['posts_returned']    = array( $A => 1 );
$GLOBALS['agreement_waiting'] = array( $A => 'revoked' );
$eight        = $D::render();
$eight_strip  = glance_strip( array(
	glance_tile( 'sponsorship', 'Champion', 'to October 26, 2026, 20 days', false, true ),
	glance_tile( 'offers', '1', 'Offer returned with a note', false, true ),
	glance_tile( 'offers', '1', 'Offer running low on codes', false, true ),
	glance_tile( 'offers', '1', 'Offer not switched on yet', false, true ),
	glance_tile( 'posts', '1', 'Post returned with a note', false, true ),
	glance_tile( 'events', '1', 'Event returned', false, true ),
	glance_tile( 'agreement', '1', 'Collaboration Agreement revoked', false, true ),
	glance_tile( 'profile', '1', 'Company profile detail missing', false, true ),
) );
ck( 'all eight tiles at once, in the approved order, each waiting, each landing on a card the page draws, and no "Nothing waits for you"', array( glance_of( $eight ), tiles_without_card( $eight ), false !== strpos( $eight, '</header>' . $eight_strip ) ), array( $eight_strip, array(), true ) );
// What waits is the sponsor's, not the viewer's: a program manager reading the page through the switcher
// sees the same strip.
$GLOBALS['uid'] = 1;
$GLOBALS['get'] = array( WPCPM_Sponsor_Roster::ARG_VIEW => $A );
$as_manager     = $D::render();
$GLOBALS['uid'] = 5;
$GLOBALS['get'] = array();
ck( 'a program manager viewing the sponsor through the switcher reads the same strip', array( false !== strpos( $as_manager, 'wpcpm-dashboard__switcher' ), glance_of( $as_manager ) ), array( true, $eight_strip ) );

// The waiting mark and the retired helpers. The mark is the leading edge in the Administrator Dashboard's
// attention color, a rule of the sheet both strips wear, beside the muted tile's; the helpers that drew the
// figure tiles (the event's tile and its title's cut) are gone with the tiles.
$admin_sheet = sheet_rules( 'administrator.css' );
$retired     = new ReflectionClass( 'WPCPM_Sponsors_Dashboard' );
ck( 'the waiting mark is the Administrator Dashboard\'s sheet\'s own rule, a four pixel leading edge in its attention color; the muted tile\'s rule is still there', array(
	$admin_sheet['|.wpcpm-attention__tile--waiting'] ?? null,
	isset( $admin_sheet['|.wpcpm-attention__tile--zero'] ),
), array( 'border-inline-start: 4px solid #b32d2e;', true ) );
ck( 'the helpers of the retired figure tiles are gone: the event tile, the title\'s cut and its length', array( $retired->hasMethod( 'glance_event_tile' ), $retired->hasMethod( 'glance_title' ), $retired->hasConstant( 'GLANCE_TITLE' ) ), array( false, false, false ) );

// With no final report, the draft left the program's to finish, there is no Reports card; the fixtures
// of this section go, and the sponsor with them.
unset( $GLOBALS['posts'][ $glance_final ], $GLOBALS['pmeta'][ $glance_final ], $GLOBALS['posts'][ $glance_draft ], $GLOBALS['pmeta'][ $glance_draft ] );
$GLOBALS['offers_waiting']    = array();
$GLOBALS['posts_drawn']       = array();
$GLOBALS['posts_returned']    = array();
$GLOBALS['agreement_waiting'] = array();
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => '', 'tier_until' => '', 'mentors' => array(), 'contact_email' => $A_email ) );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );

echo "\n=== The help under the strip, its contact and the header (Institutions release 3) ===\n";
// The help moved up from the page's foot to right under the At a glance strip, in the Institution
// Dashboard's two columns, with the program contact under the Resources buttons and a sentence that is
// true of a sponsor; the header's contact line names the person alone. From here the switch is off and A
// is a Friend with nothing waiting, as the cleanup above left it, on the pinned day.
$GLOBALS['uid']       = 5;
$GLOBALS['get']       = array();
$GLOBALS['manage']    = array( 1 );
$GLOBALS['resources'] = array();
$manager_user         = $GLOBALS['users'][1];
$manager_booking      = WPCPM_Manager_Booking::url_for( 1 );

/**
 * The help a page printed, from its opening tag to its closing one, or '' for a page with none.
 *
 * @param string $html The page.
 * @return string
 */
function help_of( $html ) {
	$at = strpos( $html, '<section class="wpcpm-student__section wpcpm-handbook__resources' );
	if ( false === $at ) { return ''; }
	return substr( $html, $at, (int) strpos( $html, '</section>', $at ) + strlen( '</section>' ) - $at );
}
/**
 * The header a page printed, or '' for a page with none.
 *
 * @param string $html The page.
 * @return string
 */
function header_of( $html ) {
	$at = strpos( $html, '<header class="wpcpm-sponsor__identity">' );
	if ( false === $at ) { return ''; }
	return substr( $html, $at, (int) strpos( $html, '</header>', $at ) + strlen( '</header>' ) - $at );
}

$under_strip = $D::render();
$marks       = array( strpos( $under_strip, '<header class="wpcpm-sponsor__identity">' ), strpos( $under_strip, 'id="wpcpm-sponsor-glance"' ), strpos( $under_strip, 'wpcpm-handbook__resources' ), strpos( $under_strip, '<div class="wpcpm-module ' ) );
$sorted      = $marks; sort( $sorted );
ck( 'the identity header, the strip and the help appear in that order, and the help comes before the first section', array( ! in_array( false, $marks, true ) && $marks === $sorted, substr_count( $under_strip, 'wpcpm-handbook__resources' ), $GLOBALS['resources'] ), array( true, 1, array( 'sponsor:2' ) ) );

// The same with the launch switch on and A a Champion: the first section is Your sponsorship, and the
// help is still above it, under a strip that now also holds the tier.
$GLOBALS['settings']['sponsor_tiers_live'] = true;
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion', 'tier_until' => glance_day( 100 ) ) );
$live_page  = $D::render();
$live_marks = array( strpos( $live_page, '<header class="wpcpm-sponsor__identity">' ), strpos( $live_page, 'id="wpcpm-sponsor-glance"' ), strpos( $live_page, 'wpcpm-handbook__resources' ), strpos( $live_page, '<div class="wpcpm-module wpcpm-module--sponsorship" id="wpcpm-module-sponsorship">' ) );
$live_sorted = $live_marks; sort( $live_sorted );
ck( 'and with the switch on, a Champion\'s help is under the strip and above Your sponsorship', array( ! in_array( false, $live_marks, true ) && $live_marks === $live_sorted, substr_count( $live_page, 'wpcpm-handbook__resources' ) ), array( true, 1 ) );

// A narrow reading of "the Institution Dashboard's shape": the default two columns, so the page asks for
// no thirds and draws no column of its own for the person, whose block follows the Resources buttons.
$help          = help_of( $under_strip );
$contact_block = '';
$contact_at    = strpos( $help, '<div class="wpcpm-resources__contact">' );
if ( false !== $contact_at ) { $contact_block = substr( $help, $contact_at, (int) strpos( $help, '</div>', $contact_at ) + strlen( '</div>' ) - $contact_at ); }
ck( 'the help holds no wpcpm-resources--split-3 and no column for the contact, and "Your contact at the program" follows the Resources buttons, once', array(
	false === strpos( $under_strip, 'wpcpm-resources--split-3' ),
	false === strpos( $under_strip, 'wpcpm-resources__col--contact' ),
	false !== strpos( $help, '<h3 class="wpcpm-student__heading">Resources</h3><p class="wpcpm-student__actions"><a class="wpcpm-button" href="#guide">Sponsor guide</a></p><div class="wpcpm-resources__contact"><h3 class="wpcpm-student__heading">Your contact at the program</h3>' ),
	substr_count( $under_strip, 'Your contact at the program' ),
), array( true, true, true, 1 ) );

// The block's markup is the one it had, and its sentence is the new one.
$expected_contact = '<div class="wpcpm-resources__contact"><h3 class="wpcpm-student__heading">Your contact at the program</h3>'
	. '<p class="wpcpm-resources__contact-name">' . esc_html( $manager_user->display_name ) . '</p>'
	. '<p class="wpcpm-resources__contact-links"><a href="mailto:' . esc_attr( $manager_user->user_email ) . '">' . esc_html( $manager_user->user_email ) . '</a> &middot; <a href="' . $manager_booking . '" target="_blank" rel="noopener noreferrer">Book a call</a></p>'
	. '<p class="wpcpm-student__note">They look after your company&#039;s sponsorship. Write to them about anything the guide does not answer.</p></div>';
ck( 'the contact keeps its markup: the name, a link to write, Book a call, and the sentence "They look after your company\'s sponsorship. Write to them about anything the guide does not answer."', $contact_block, $expected_contact );
ck( 'and the old sentence is gone from the page', strpos( $under_strip . $live_page, 'They look after your sponsorship.' ), false );

// An assigned Administrator who has since lost the program's capability (Review Focus 5): the row keeps
// the id, `manager_of()` answers null, and the help is drawn all the same, its Resources buttons alone,
// with no contact line, no empty heading, no name and no booking link; the tier card's Talk to your
// program contact is still on the page, which mails whoever the interests road falls back to.
$GLOBALS['manage']    = array();
$GLOBALS['resources'] = array();
$demoted_help_page    = $D::render();
$demoted_help         = help_of( $demoted_help_page );
$GLOBALS['manage']    = array( 1 );
ck( 'an assigned Administrator without the program\'s capability draws no contact line: the help is there, its columns the default two, the Resources buttons its last block', array(
	'' !== $demoted_help,
	$GLOBALS['resources'],
	false !== strpos( $demoted_help, 'Sponsor guide</a></p></div></section>' ),
	strpos( $demoted_help_page, 'wpcpm-resources__contact' ),
	strpos( $demoted_help_page, 'Your contact at the program' ),
	strpos( $demoted_help_page, $manager_user->display_name ),
	strpos( $demoted_help_page, $manager_booking ),
	strpos( $demoted_help_page, 'wpcpm-resources__col--contact' ),
	false !== strpos( $demoted_help_page, 'Talk to your program contact' ),
), array( true, array( 'sponsor:2' ), true, false, false, false, false, false, true ) );
// An assigned id whose account is gone is nobody's contact either.
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => '9999' ) );
$gone_page = $D::render();
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => '1' ) );
ck( 'and so is an assigned id whose account no longer exists', array( '' !== help_of( $gone_page ), strpos( $gone_page, 'wpcpm-resources__contact' ), strpos( $gone_page, 'Your contact at the program' ) ), array( true, false, false ) );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => '', 'tier_until' => '' ) );

// The header's contact line: the person's name alone, in the Institution Dashboard's wording and markup
// under this page's own class names. The address stays on the profile card and the program's screens.
$head = header_of( $D::render() );
ck( 'the header\'s contact line holds the name and no address', array(
	false !== strpos( $head, '<p class="wpcpm-sponsor__fact wpcpm-sponsor__contact">Contact: Rep One</p>' ),
	substr_count( $head, 'wpcpm-sponsor__contact' ),
	strpos( $head, $A_email ),
	strpos( $head, '@' ),
	strpos( $head, 'mailto:' ),
), array( true, 1, false, false, false ) );
// With an address and no name there is no line to draw: a name-only line has nothing to say.
WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => '' ) );
$nameless = header_of( $D::render() );
WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => 'Rep One' ) );
ck( 'a sponsor with a contact address and no contact person has no contact line, and the address is not printed in its place', array( strpos( $nameless, 'wpcpm-sponsor__contact' ), strpos( $nameless, 'Contact:' ), strpos( $nameless, $A_email ), strpos( $nameless, '@' ) ), array( false, false, false, false ) );
// The name is escaped where it is printed.
WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => 'Ana & <i>Lee</i>' ) );
$escaped_head = header_of( $D::render() );
WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => 'Rep One' ) );
ck( 'and the name is escaped where it is printed', array( false !== strpos( $escaped_head, 'Contact: Ana &amp; &lt;i&gt;Lee&lt;/i&gt;</p>' ), strpos( $escaped_head, '<i>' ) ), array( true, false ) );

// Sponsor records live on the site, so the page has nothing to say about where it was read from: no
// "Read from the program records" line closes it, for a member, with the switch on, or for a manager.
$GLOBALS['uid']    = 1;
$GLOBALS['get']    = array( WPCPM_Sponsor_Roster::ARG_VIEW => $A );
$as_manager_page   = $D::render();
$GLOBALS['uid']    = 5;
$GLOBALS['get']    = array();
ck( 'the page prints no "Read from the program records" line', array( strpos( $under_strip, 'Read from the program records' ), strpos( $live_page, 'Read from the program records' ), strpos( $as_manager_page, 'Read from the program records' ), strpos( $under_strip, 'program records' ) ), array( false, false, false, false ) );

echo "\n=== The four sections as modules (Institutions release 3) ===\n";
// The four groups are movable modules, in the Institution Dashboard's shape: each a heading, its
// lead and the arrows named after it, drawn through the printer both dashboards share, in the
// order the company keeps. Keyed by the old group ids, saved per sponsor key, repaired on read.
$GLOBALS['uid']  = 5;
$GLOBALS['get']  = array();
$GLOBALS['post'] = array();
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => '', 'tier_until' => '' ) );
$default_modules = array( 'sponsorship', 'offer', 'mentors', 'company' );

ck( 'the constants the order, the mover and the form are kept by', array( $D::OPT_MODULES_PREFIX, $D::ACTION_MOVE, $D::FIELD_SPONSOR ), array( 'wpcpm_sponsor_modules_', 'wpcpm_sponsor_module_move', 'wpcpm_sponsor' ) );
ck( 'the modules are the four groups, under their ids, titled as the arrows name them', $D::modules(), array( 'sponsorship' => 'Your sponsorship', 'offer' => 'What you offer', 'mentors' => 'Mentors', 'company' => 'Your company' ) );
$D::init();
ck( 'the page\'s own handler listens on its action', in_array( array( 'add_action', 'admin_post_wpcpm_sponsor_module_move', 10 ), $GLOBALS['calls'], true ), true );

// The leads are today's group leads: the offer lead keeps its two forms by the Posts card's own test,
// and the sponsorship lead is the line the page already prints, so the two never say different things.
$GLOBALS['posts_drawn'] = array();
$plain_leads            = $D::leads( $A );
$GLOBALS['posts_drawn'] = array( $A => true );
$posts_leads            = $D::leads( $A );
$GLOBALS['posts_drawn'] = array();
ck( 'the leads, one for each module: the sponsorship line is empty while the tiers are off, the offer lead has two forms', array( array_keys( $plain_leads ), $plain_leads['sponsorship'], $plain_leads['offer'], $posts_leads['offer'], $plain_leads['mentors'], $plain_leads['company'] ), array(
	$default_modules,
	'',
	'What your company gives the program\'s students and mentors.',
	'What your company gives the program\'s students and mentors, and what it wrote for them.',
	'The mentors your company sponsors, and the ones looking for a sponsor.',
	'Your company\'s details and logo, the people who can sign in for it, and the Collaboration Agreement.',
) );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion', 'tier_until' => glance_day( 100 ) ) );
$live_lead = sponsorship_group( $D::render() );
ck( 'with the tiers live the sponsorship lead is the line the section prints', array( '' !== $D::leads( $A )['sponsorship'], $D::leads( $A )['sponsorship'] === html_entity_decode( (string) $live_lead['lead'], ENT_QUOTES, 'UTF-8' ) ), array( true, true ) );

// The order: today's by default, repaired on read, never trusted.
$order_key = $D::OPT_MODULES_PREFIX . $A;
ck( 'nothing saved: today\'s order, Your sponsorship first', $D::module_order( $A ), $default_modules );
$GLOBALS['opts'][ $order_key ] = array( 'mentors', 'bogus', 'offer', 'sponsorship' );
ck( 'a saved order with a key this version does not know and without Your company: the key dropped, Your company joining in the default place', $D::module_order( $A ), array( 'mentors', 'offer', 'sponsorship', 'company' ) );
$GLOBALS['opts'][ $order_key ] = array( 'company', 'sponsorship' );
ck( 'a short saved order takes the modules it lacks in the default order', $D::module_order( $A ), array( 'company', 'sponsorship', 'offer', 'mentors' ) );
$GLOBALS['opts'][ $order_key ] = array( 'offer', 'offer', 'company' );
ck( 'a module saved twice is kept once', $D::module_order( $A ), array( 'offer', 'company', 'sponsorship', 'mentors' ) );
$GLOBALS['opts'][ $order_key ] = 'company';
ck( 'a saved value that is not a list is the default', $D::module_order( $A ), $default_modules );
unset( $GLOBALS['opts'][ $order_key ] );
ck( 'a record that is no sponsor key has the default order and reads nothing saved', array( $D::module_order( '' ), $D::module_order( 'not-a-key' ) ), array( $default_modules, $default_modules ) );

// The page, the tiers live and a Champion: four modules in the default order, each with its title, its lead
// and the arrows named after it, the first with Move up disabled and the last with Move down disabled.
$GLOBALS['scripts'] = array();
$page4              = $D::render();
/**
 * The arrows' forms a page printed.
 *
 * @param string $html The page.
 * @return string[]
 */
function movers_of( $html ) {
	preg_match_all( '#<form class="wpcpm-module__mover".*?</form>#s', $html, $m );
	return $m[0];
}
$mod_buttons        = static function ( $html, $key ) {
	$module = (string) module_of( $html, $key );
	return array(
		1 === preg_match( '/wpcpm-module__move--up"[^>]* disabled>/', $module ),
		1 === preg_match( '/wpcpm-module__move--down"[^>]* disabled>/', $module ),
	);
};
ck( 'a member\'s page is the four modules in the default order', modules_printed( $page4 ), $default_modules );
ck( 'each with an eyebrow title and its lead in the sponsor\'s own classes, and no group wrapper left', array(
	substr_count( $page4, '<h2 class="wpcpm-student__heading wpcpm-sponsor__module-title">' ),
	substr_count( $page4, '<p class="wpcpm-student__note wpcpm-sponsor__module-lead">' ),
	substr_count( $page4, 'wpcpm-sponsor__group' ),
	substr_count( $page4, 'wpcpm-institution__module' ),
	false !== strpos( $page4, $openings['sponsorship'] ),
	false !== strpos( $page4, $openings['mentors'] ),
), array( 4, 4, 0, 0, true, true ) );
ck( 'and one mover for each, naming the module, posting to the page\'s action under its nonce and naming the sponsor', array(
	substr_count( $page4, 'class="wpcpm-module__mover"' ),
	substr_count( $page4, 'name="action" value="wpcpm_sponsor_module_move"' ),
	substr_count( $page4, 'value="nonce-wpcpm_sponsor_module_move"' ),
	count( array_filter( movers_of( $page4 ), static function ( $mover ) use ( $A ) { return false !== strpos( $mover, '<input type="hidden" name="wpcpm_sponsor" value="' . $A . '">' ); } ) ),
	false !== strpos( $page4, 'aria-label="Move Your sponsorship up"' ) && false !== strpos( $page4, 'aria-label="Move Your sponsorship down"' ),
	false !== strpos( $page4, 'aria-label="Move What you offer down"' ),
	false !== strpos( $page4, 'aria-label="Move Mentors up"' ),
	false !== strpos( $page4, 'aria-label="Move Your company up"' ),
	false !== strpos( $page4, 'data-wpcpm-moved="Your sponsorship moved down."' ),
), array( 4, 4, 4, 4, true, true, true, true, true ) );
ck( 'the first drawn has Move up disabled, the last Move down, and the two between neither', array( $mod_buttons( $page4, 'sponsorship' ), $mod_buttons( $page4, 'offer' ), $mod_buttons( $page4, 'mentors' ), $mod_buttons( $page4, 'company' ) ), array( array( true, false ), array( false, false ), array( false, false ), array( false, true ) ) );
ck( 'the shared script is registered from assets/js/modules.js and enqueued for the company\'s member', array( in_array( 'wpcpm-modules', $GLOBALS['scripts'], true ), isset( $GLOBALS['script_src']['wpcpm-modules'] ) ? $GLOBALS['script_src']['wpcpm-modules'] : '' ), array( true, WPCPM_PLUGIN_URL . 'assets/js/modules.js' ) );

// The company's order, in the page: the saved order is the page's, the edges are the drawn modules'.
$GLOBALS['opts'][ $order_key ] = array( 'company', 'mentors', 'offer', 'sponsorship' );
$page_saved                    = $D::render();
ck( 'the page follows the order the company saved, and the outer arrows follow it', array( modules_printed( $page_saved ), $mod_buttons( $page_saved, 'company' ), $mod_buttons( $page_saved, 'mentors' ), $mod_buttons( $page_saved, 'sponsorship' ) ), array( array( 'company', 'mentors', 'offer', 'sponsorship' ), array( true, false ), array( false, false ), array( false, true ) ) );
unset( $GLOBALS['opts'][ $order_key ] );

// A module whose cards print nothing is left out: with the tiers off the sponsorship module draws none of
// its cards, and the arrows count what is drawn. Saved last, it would have been the last of the order.
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$GLOBALS['opts'][ $order_key ] = array( 'company', 'mentors', 'offer', 'sponsorship' );
$page_off                      = $D::render();
ck( 'with the tiers off the sponsorship module is not drawn, so it has no wrapper, no title, no lead and no arrows', array( modules_printed( $page_off ), strpos( $page_off, 'Move Your sponsorship' ), strpos( $page_off, '>Your sponsorship</h2>' ), substr_count( $page_off, 'class="wpcpm-module__mover"' ) ), array( array( 'company', 'mentors', 'offer' ), false, false, 3 ) );
ck( 'and the outer arrows count the modules drawn: the last drawn is What you offer, whose Move down is disabled although the saved order goes on to another module', array( $mod_buttons( $page_off, 'company' ), $mod_buttons( $page_off, 'mentors' ), $mod_buttons( $page_off, 'offer' ) ), array( array( true, false ), array( false, false ), array( false, true ) ) );
unset( $GLOBALS['opts'][ $order_key ] );
$page_off = $D::render();
ck( 'in the default order the first drawn is What you offer and the last Your company', array( modules_printed( $page_off ), $mod_buttons( $page_off, 'offer' ), $mod_buttons( $page_off, 'company' ) ), array( array( 'offer', 'mentors', 'company' ), array( true, false ), array( false, true ) ) );

// An Administrator looking through the switcher is given the arrows for that company, and the script.
$GLOBALS['uid']     = 1;
$GLOBALS['get']     = array( WPCPM_Sponsor_Roster::ARG_VIEW => $A );
$GLOBALS['scripts'] = array();
$admin_page         = $D::render();
ck( 'an Administrator on a company\'s page is given the arrows, naming that company, and the shared script', array( count( movers_of( $admin_page ) ), count( array_filter( movers_of( $admin_page ), static function ( $mover ) use ( $A ) { return false !== strpos( $mover, '<input type="hidden" name="wpcpm_sponsor" value="' . $A . '">' ); } ) ), in_array( 'wpcpm-modules', $GLOBALS['scripts'], true ) ), array( 3, 3, true ) );
$GLOBALS['get'] = array();

// The mover's handler: the nonce first, the company from the account, JSON for the script and a
// redirect for the form.
$GLOBALS['users'][6]  = new WP_User( 6, array( 'wpcpm_sponsor' ), 'Rep Two' );
$GLOBALS['umeta'][6]  = array( WPCPM_Sponsor_Members::META_RECORD_ID => $B, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['users'][11] = new WP_User( 11, array( 'administrator' ), 'Admin Member' );
$GLOBALS['umeta'][11] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $B, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['manage']    = array( 1, 11 );
$move_page            = $D::page_url();
$order_b              = $D::OPT_MODULES_PREFIX . $B;
/**
 * Runs the handler and says how it ended: the redirect it asked for, the JSON it answered, the status
 * it died with or that it returned.
 *
 * @return string
 */
function ran_move() {
	$GLOBALS['nonce_checked'] = array();
	try {
		WPCPM_Sponsors_Dashboard::handle_move();
		return 'returned';
	} catch ( WPCPM_Test_Redirect $e ) {
		return 'redirect: ' . $e->getMessage();
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
}
$refusal = 'Only someone who acts for the company, or an Administrator on their behalf, can arrange a Sponsor Dashboard.';
ck( 'the page the handler lands on is the Sponsor Dashboard\'s', '' !== $move_page, true );

$GLOBALS['uid']  = 5;
$GLOBALS['post'] = array( 'wpcpm_sponsor' => $A, 'wpcpm_module' => 'company', 'wpcpm_direction' => 'up' );
ck( 'a member moves their company\'s page, after the nonce, and lands back on it at the module, with no Viewing as argument', array( ran_move(), $GLOBALS['nonce_checked'] ), array( 'redirect: ' . $move_page . '#wpcpm-module-company', array( 'wpcpm_sponsor_module_move' ) ) );
ck( 'the order is the company\'s own, saved not autoloaded', array( $GLOBALS['opts'][ $order_key ], array_values( array_filter( $GLOBALS['calls'], static function ( $call ) use ( $order_key ) { return 'update_option' === $call[0] && $order_key === $call[1]; } ) ) ), array( array( 'sponsorship', 'offer', 'company', 'mentors' ), array( array( 'update_option', $order_key, false ) ) ) );

$GLOBALS['post'] = array( 'wpcpm_sponsor' => $B, 'wpcpm_module' => 'company', 'wpcpm_direction' => 'up' );
ck( 'a member posting another company\'s key moves their own company and lands on their own page', ran_move(), 'redirect: ' . $move_page . '#wpcpm-module-company' );
ck( 'and not the other: the other company\'s page is untouched', array( $GLOBALS['opts'][ $order_key ], isset( $GLOBALS['opts'][ $order_b ] ) ), array( array( 'sponsorship', 'company', 'offer', 'mentors' ), false ) );

$GLOBALS['post'] = array( 'wpcpm_module' => 'mentors', 'wpcpm_direction' => 'up' );
ck( 'a member who names no company moves their own', array( ran_move(), $GLOBALS['opts'][ $order_key ] ), array( 'redirect: ' . $move_page . '#wpcpm-module-mentors', array( 'sponsorship', 'company', 'mentors', 'offer' ) ) );

$GLOBALS['post'] = array( 'wpcpm_sponsor' => $A, 'wpcpm_module' => 'mentors', 'wpcpm_direction' => 'up', 'wpcpm_async' => '1' );
ck( 'the script gets the kept order back as JSON', ran_move(), 'json: {"order":["sponsorship","mentors","company","offer"]}' );

$GLOBALS['post'] = array( 'wpcpm_sponsor' => $A, 'wpcpm_module' => 'bogus', 'wpcpm_direction' => 'up' );
$before          = $GLOBALS['opts'][ $order_key ];
ck( 'a module this version does not know moves nothing and the page has no anchor to land on', array( ran_move(), $GLOBALS['opts'][ $order_key ] ), array( 'redirect: ' . $move_page, $before ) );
$GLOBALS['post'] = array( 'wpcpm_sponsor' => $A, 'wpcpm_module' => 'company', 'wpcpm_direction' => 'sideways' );
ck( 'and so does a direction that is neither up nor down', array( ran_move(), $GLOBALS['opts'][ $order_key ] ), array( 'redirect: ' . $move_page . '#wpcpm-module-company', $before ) );

$GLOBALS['uid']  = 1;
$GLOBALS['post'] = array( 'wpcpm_sponsor' => $B, 'wpcpm_module' => 'offer', 'wpcpm_direction' => 'down' );
ck( 'an Administrator moves the company they name, and lands on its page with the Viewing as argument', array( ran_move(), isset( $GLOBALS['opts'][ $order_b ] ) ? $GLOBALS['opts'][ $order_b ] : null ), array( 'redirect: ' . $move_page . '?' . WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $B . '#wpcpm-module-offer', array( 'sponsorship', 'mentors', 'offer', 'company' ) ) );
ck( 'and no other company\'s order changed', $GLOBALS['opts'][ $order_key ], $before );

$GLOBALS['post'] = array( 'wpcpm_sponsor' => 'not-a-key', 'wpcpm_module' => 'offer', 'wpcpm_direction' => 'down' );
ck( 'an Administrator who names no sponsor key, and acts for no company, is refused', array( ran_move(), $GLOBALS['died'] ), array( 'die: 403', $refusal ) );
$GLOBALS['post'] = array( 'wpcpm_module' => 'offer', 'wpcpm_direction' => 'down', 'wpcpm_async' => '1' );
ck( 'and so is the background save', ran_move(), 'die: 403' );

$GLOBALS['uid']  = 11;
$GLOBALS['post'] = array( 'wpcpm_sponsor' => 'not-a-key', 'wpcpm_module' => 'company', 'wpcpm_direction' => 'up' );
ck( 'an Administrator who also acts for a company, and names no valid key, moves their own, with no Viewing as argument', array( ran_move(), $GLOBALS['opts'][ $order_b ] ), array( 'redirect: ' . $move_page . '#wpcpm-module-company', array( 'sponsorship', 'mentors', 'company', 'offer' ) ) );

// Anyone else is refused after the nonce and before anything is written.
$written = $GLOBALS['opts'];
foreach ( array( 0 => 'an anonymous visitor', 9 => 'an account that acts for no company' ) as $who_id => $who ) {
	$GLOBALS['uid']  = $who_id;
	$GLOBALS['died'] = '';
	$GLOBALS['post'] = array( 'wpcpm_sponsor' => $A, 'wpcpm_module' => 'company', 'wpcpm_direction' => 'down' );
	ck( $who . ' is refused with a 403 and the sentence, after the nonce', array( ran_move(), $GLOBALS['died'], $GLOBALS['nonce_checked'] ), array( 'die: 403', $refusal, array( 'wpcpm_sponsor_module_move' ) ) );
}
$detached = 7;
$GLOBALS['users'][ $detached ] = new WP_User( $detached, array( 'wpcpm_sponsor' ), 'Rep Gone' );
$GLOBALS['umeta'][ $detached ] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $A, WPCPM_Sponsor_Members::META_ACTIVE => 0 );
$GLOBALS['uid']                = $detached;
$GLOBALS['post']               = array( 'wpcpm_sponsor' => $A, 'wpcpm_module' => 'company', 'wpcpm_direction' => 'down' );
ck( 'and so is an account detached from the company, whose stamp is still on it', ran_move(), 'die: 403' );
ck( 'none of them changed any order', $GLOBALS['opts'], $written );

$GLOBALS['uid']    = 5;
$GLOBALS['post']   = array();
$GLOBALS['manage'] = array( 1 );
unset( $GLOBALS['opts'][ $order_key ], $GLOBALS['opts'][ $order_b ], $GLOBALS['settings']['sponsor_tiers_live'] );
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => '', 'tier_until' => '' ) );

// Counted by ck() itself: a literal here read 45 while the file carried 49 (1.93.2), and a count
// nobody maintains is a count nobody should trust.
printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', (int) $checks );
exit( $fail ? 1 : 0 );
