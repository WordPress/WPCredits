<?php
/**
 * The Administrator Dashboard: every queue on one page, the counts above them, and the
 * decisions posted to the handlers that already exist.
 *
 * What each block pins, and why:
 *
 * - **Every card reads through the owning class.** The collaborators are stubbed to their
 *   contracts and record what was asked; the cards never query posts themselves.
 * - **At a glance shows what needs a manager, in the cards' own numbers.** The page itself no longer
 *   prints the strip (1.123.69), and its renderer left with the old branches (Task 18b): the counts it printed,
 *   `WPCPM_Administrators_Cards::counts()`, are what the page's head and its menu's badges read. Each tile's count comes
 *   from the same arrays the cards draw, so a tile and its card cannot disagree. A tile is drawn
 *   only for a count above zero, in the order of the cards the tiles link to, each a link to a
 *   card the page draws; a card's part of its own queue folds into its tile (the requests from institutions
 *   and the Collaboration Agreements overdue, each "overdue" asked in a context of its own), the
 *   semester reports' two counts share one tile, led by the reports to review while there is one
 *   and by the semesters due for drafting when there is none, each of a folded tile's counts is
 *   said through a plural form of its own, each tile's words begin with its card's title (the two
 *   tiles of Syncs and health, the syncs that need a look as its row counts them and the locked
 *   accounts it lists, excepted), and with nothing to show the strip is one sentence in the same
 *   section, which it never is while the Syncs and health card counts a sync that needs a look.
 *   The one tile with no card here, the mentor applications waiting (1.122.31), counts through
 *   the record class, stands between the institutions' tiles and the sponsors', and opens the
 *   Mentors screen at Waiting for review, where they are decided.
 * - **A decision posted here comes back here, to its card.** Every decision form on the page
 *   carries `wpcpm_return=dashboard` and its card's anchor, the institution's Collaboration
 *   Agreement forms among them (the review block's Accept and Return, and Reinstate).
 * - **Every link to an institution carries the switcher argument**, or a manager lands on
 *   their fallback institution and reads another school's report under this row's name.
 * - **The programs calculation reads only status, start, end, reports and mentor_name**
 *   off roster rows, and a finished student no longer says their track, so "finished this
 *   semester" is one number rather than one per track.
 * - **A card prints words, never a stored state.** Programs running's Agreement and Report cells
 *   are the words the Collaboration Agreement and semester report classes give their states
 *   (including "Not started" and "Not drafted" for none), and an Institution applications row says
 *   its state in the Institutions screen's words as they are given, links its title to the
 *   application in that screen's queue as a sponsor application's links to its own, and carries a
 *   note when the anti-spam checks held it. The requests card and its tile are called Requests from
 *   institutions.
 * - **A viewer without the capability sees the refusal and no form.**
 * - **A school's request to correct its details is drawn as the chosen mockup A** (Institutions
 *   release 2): the school's name linked as every name here is, who asked and on which day, the
 *   waiting line the other requests use, a table of Detail, On record now and Asked for, the note
 *   box for the school (required to decline) and the two buttons, Approve and update the records
 *   and Decline, in a form that posts the details decision keyed to the request and comes back to
 *   the card. What is asked is escaped, a note the decision refused is given back once, and the
 *   closed ones are Approved, Declined or Withdrawn in the fold. The facts, "On record now" among
 *   them, are the request class's to read when they are built (bin/test-institution-request.php).
 * - **Every card is drawn in the dashboards' one shell** (WPCPM_Dashboard_Card, pinned in
 *   bin/test-dashboard-card.php) in this page's shape: the anchor on the card's section, where the
 *   mails, the wp-admin screens and every decision's way back land, and the page's class on the
 *   disclosure before the shared two. The opener the Sponsor Dashboard's cards use is enqueued
 *   here too, so a tile, a link in a mail or a decision's way back opens the card it lands on.
 * - **The Sponsor reports card (1.115.0) reads the report store and draws Draft now with the
 *   Sponsors screen's own renderer of its draft form.** The store is stubbed to its contract; the
 *   Sponsors screen's report class and the files class are the real ones, so the card's form is
 *   the handler's own, and a refusal flashed on this page's channel prints in that class's words.
 *   Each part keeps its heading when it is empty, and no report's narratives or uploaded PDF name
 *   reach the card, a draft's or a final one's: both carry a planted student.
 * - **The Events card (1.117.0) is drawn by the queue class, the real one, from what it read
 *   through the event store**, which is stubbed to its contract. The card and its tile exist only
 *   while Sponsor tiers is switched on; each event reads on the viewing manager's own clock; the
 *   names of the people on an event and the call link are shown to the manager; each decision is a
 *   form keyed to its event and bound for this card; an event whose sponsor no longer offers events
 *   says so, and waiting it has Return alone, whose fold makes no promise that it can plan again.
 * - **The page is three groups** (the design the owner approved on 28 September 2026), laid out as
 *   the version 3 concepts have them (1.123.69): the page is drawn in the dashboards' frame, a menu down
 *   the side holding the page's views with a count beside what waits, the screens in wp-admin and the
 *   help, the view drawn marked current. The views are asked for by `?wpcpm_view=`. The Overview, the
 *   default, opens with its head, how many decisions wait and the oldest queue, then the program's
 *   figures and the last decision's words, then two columns: Waiting for you, where only a queue with
 *   something in it is drawn as a card, the mentor applications as a row, and one sentence names every
 *   empty queue with a link to its screen, and Watch. The Reference view is the third group, the
 *   program's numbers, then the Tools section and the resources. Each group is a heading and one
 *   sentence over its cards; a group none of whose cards draws is not printed at all, and the Events
 *   card is no queue and has no place in its group while Sponsor tiers is off. The group printer is
 *   the dashboards' shell's, and its rule for a group over nothing is pinned in
 *   bin/test-dashboard-card.php, with its fold and its age.
 * - **Each card's row says what it holds.** A card waiting for a decision, drawn alone, is open while
 *   it holds something and folded with nothing; a card to watch and a card of the program's numbers are
 *   drawn folded whatever they hold. On the Overview every card is drawn folded, one row each, a queue's
 *   with a Review cue and how long its oldest item has waited, unless it holds the outcome of the
 *   decision just taken, which is drawn open even when its queue is now empty. Each row carries one
 *   phrase, the badge beside the title is the
 *   first number the phrase says, and the cards whose work has one home in wp-admin link to it
 *   after the fold, at the tab of the Institutions or the Sponsors screen that holds their items
 *   and at their own card on that tab where it has one, in the words "Open on the Institutions
 *   screen" or "Open on the Sponsors screen". The row's link shows only while the card is folded,
 *   so each of those cards ends its body with the same link as a line, on every draw, an empty
 *   card's too, and on a card whose list this page cut at fifty after that list's own line, which
 *   says how many more there are and links nowhere: the Institution applications card and the
 *   Sponsor applications, Sponsor posts to review and Sponsor Collaboration Agreements cards (the
 *   other lists this page cuts at fifty stop without one). The Duplicated students card's home is
 *   the Student Duplicate Finder: its row links there in the finder's own words, and its body ends
 *   with its own button to the finder instead of that line. Both links of a card with a home on
 *   either screen carry the card's title after their words, inside the link and for a screen reader
 *   alone, so links that read alike still say which card they come from: `capture()` sets those
 *   words aside, so every check of what a manager sees reads the visible words alone, and
 *   `hidden_words()` reads them. A Sponsor terms ending row opens its sponsor's Edit form on
 *   the Sponsors tab, through the Sponsors screen's one builder of that address. A strip of one or
 *   two tiles keeps tiles of a tile's width, and from three the tiles fill the row. The Syncs and
 *   health card counts the syncs that need a look: while Automatic sync is on, an audience's sync
 *   whose last run left an error or that finished no run in the last six hours; while it is off,
 *   one that left an error; and, the switch on or off, the outcome figures' weekly read when its
 *   last read was held or failed or the figures stored are past the 29 days. Each sync's row links
 *   its own screen at the tab where that sync is run. This page's own sheet dresses the row as the
 *   Sponsor Dashboard's does, since the page loads none of that sheet.
 * - **The head says what waits** (the version 3 concepts, where one sentence under the title said
 *   what the page is), the page's name its one H1 only while the theme leaves the page to the frame,
 *   and never on the notice a visitor who cannot use the page gets, which is drawn outside the frame;
 *   the welcome, what the page is for and where to ask are the program manager guide's. The outcome a
 *   decision leaves with no card to hold it is said where the head's view puts it, after the figures
 *   on the Overview and after the head on the Reference view. The Tools section and the resources
 *   after the Reference view's cards sit under one heading for a screen reader, printed only over
 *   something, so their headings do not read as the Reference group's. The Resources column's way
 *   into wp-admin is pinned in bin/test-handbook.php, which draws the real section; its room above
 *   it, here.
 * - **The block's description names the three groups and every card the page draws in each**, each
 *   card as a whole entry of its group's list.
 * - **Every item a wp-admin screen links to carries an id of its own**, an institution or a
 *   sponsor application, a sponsor post, a sponsor agreement, so the link lands on the item
 *   rather than on the top of its card; the decisions inside it are drawn as before.
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
$GLOBALS['tz']         = 'UTC';

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
	// the stored post object (the copied block above never needed either field). post_author is
	// declared as core's is, 0 for no author: the event queue's row reads who planned an event from it.
	public $ID = 0, $post_title = '', $post_name = '', $post_content = '', $post_type = 'page', $post_status = 'publish', $post_date_gmt = '', $post_modified_gmt = '', $post_author = 0;
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
// Records each form it is asked for while a check sets $GLOBALS['plurals_asked'] to an array: the
// English forms of a folded tile's words are the same in the singular and the plural, so which
// count each form is keyed to shows only here. It also keeps each number a plural string is asked
// for, in order, under the string's singular, so a check can read which number chose a line's
// form: in English any number above one reads the same, and in other languages it does not.
function _n( $a, $b, $n, $d = null ) { if ( isset( $GLOBALS['plurals_asked'] ) ) { $GLOBALS['plurals_asked'][] = array( $a, $b, (int) $n ); } $GLOBALS['plural_for'][ $a ][] = (int) $n; return 1 === (int) $n ? $a : $b; }
// The same for a form asked with a context, the context recorded after the count: two tiles say
// "overdue" of two different things, and a translator can word the two apart only when each is asked
// with a context of its own.
function _nx( $a, $b, $n, $c, $d = null ) { if ( isset( $GLOBALS['plurals_asked'] ) ) { $GLOBALS['plurals_asked'][] = array( $a, $b, (int) $n, $c ); } return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( isset( $GLOBALS['translations'][ $s ] ) ? $GLOBALS['translations'][ $s ] : $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_textarea( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function wp_kses( $s, $allowed ) { return (string) $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
/*
 * A pass-through, except for the filters a check puts in `$GLOBALS['live_filters']` (1.100.0): the
 * Programs running card's tiles are the program map's tracks, and the only way to show a track
 * added to the map is to add one. `add_filter()` below still records calls without running them,
 * so nothing the plugin hooks at load time starts running here.
 */
$GLOBALS['live_filters'] = array();
function apply_filters( $t, $v ) {
	foreach ( $GLOBALS['live_filters'][ $t ] ?? array() as $callback ) {
		$v = $callback( $v );
	}

	return $v;
}
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

function get_post( $id ) { return $id instanceof WP_Post ? $id : ( $GLOBALS['posts'][ (int) $id ] ?? null ); }
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
function get_users( $args = array() ) { $GLOBALS['users_asked'][] = $args; return array_values( $GLOBALS['users'] ); }
function user_can( $u, $c ) {
	$id = is_object( $u ) ? $u->ID : (int) $u;
	if ( 'edit_posts' === $c ) { return in_array( $id, $GLOBALS['editors'], true ); }
	return in_array( $id, $GLOBALS['manage'], true );
}
function current_user_can( $c ) { return user_can( $GLOBALS['uid'], $c ); }

function wp_login_url( $to = '' ) { return 'https://example.test/wp-login.php?redirect_to=' . rawurlencode( $to ); }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
// The array form too, as core takes it: the Sponsors screen's report class builds the editor's
// address that way (1.115.0). The key and value form every other caller uses is unchanged.
function add_query_arg( $k, $v = '', $u = '' ) {
	if ( is_array( $k ) ) {
		$u = (string) $v;
		return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . http_build_query( $k );
	}
	return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v;
}
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
// What a script was registered from, with its dependencies and whether it waits for the footer.
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) { $GLOBALS['script_src'][ $handle ] = $src; $GLOBALS['script_args'][ $handle ] = array( $deps, $footer ); }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

require_once __DIR__ . '/stubs/compiled-seeds.php';
require_once __DIR__ . '/stubs/scroll-regions.php';
require_once __DIR__ . '/stubs/parts-inventory.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-guide-jobs.php';
// What the courtesy scan noticed, in words (`WPCPM_Pdf_Check::in_words()`).
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-pdf-check.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
// The names the health card gives the emails it reads, which are the catalog's own data.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';

/* ---- the collaborators, stubbed to their contracts ----------------------- */

if ( ! function_exists( 'get_post_time' ) ) {
	function get_post_time( $f = 'U', $gmt = false, $post = null, $translate = false ) { return isset( $post->post_date_gmt ) ? (int) strtotime( $post->post_date_gmt . ' UTC' ) : 0; }
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
// revoked agreement's Reinstate form). The nonce field records the action it was asked for,
// so a form can be told apart without a real nonce ever being verified.
//
// `wp_date()` formats in the site's zone, which is what it is for and what `gmdate()` is
// not. Written with gmdate() the stub answered at Greenwich whatever `$GLOBALS['tz']` said,
// so nothing in this suite could tell a display the cards computed with `wp_date()` from one
// they computed at Greenwich - east of Greenwich those are different days, and the semester
// tile's sentence is the day its own window starts on. The host's own zone still never comes
// into it: `$GLOBALS['tz']` is UTC unless a check says otherwise.
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		$at = new DateTimeImmutable( '@' . ( null === $timestamp ? time() : (int) $timestamp ) );

		return $at->setTimezone( $timezone instanceof DateTimeZone ? $timezone : wp_timezone() )->format( $format );
	}
}
// The site's own zone, which is UTC here unless a check says otherwise: the semester's first
// day is midnight where the site is, and a suite that only ever ran at Greenwich could not
// tell the two apart (the clean-up release's parked P2).
if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone() { return new DateTimeZone( isset( $GLOBALS['tz'] ) ? $GLOBALS['tz'] : 'UTC' ); }
}
// With `$GLOBALS['faithful_nonces']` set, as WordPress prints them: the nonce field with the id core gives it and
// the referer beside it, so a form drawn through `WPCPM_UI::nonce()` (the same name and value, no id) can be
// compared with today's field by field (UI shared parts, Task 11).
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		if ( empty( $GLOBALS['faithful_nonces'] ) ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce-' . esc_attr( $action ) . '" />';
			return;
		}
		echo '<input type="hidden" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="nonce-' . esc_attr( $action ) . '" />';
		if ( $referer ) {
			wp_referer_field();
		}
	}
}
if ( ! function_exists( 'wp_referer_field' ) ) {
	function wp_referer_field( $display = true ) {
		$field = '<input type="hidden" name="_wp_http_referer" value="/administrator-dashboard/" />';
		if ( $display ) {
			echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A constant.
		}
		return $field;
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) { $GLOBALS['nonces_made'][] = $action; return 'nonce-' . $action; }
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
		// The launch switch is off unless a check turns it on: every card the tiers bring is dark
		// while it is, the Events card among them (1.117.0). Automatic sync is on, as the
		// setting is by default, unless a check turns it off. Every read is recorded, by key.
		$GLOBALS['settings_read'][] = $key;
		$known = array( 'agreement_review_days' => 3, 'institution_active_stages' => array( 'Confirmed', 'Student' ), 'past_statuses' => array( 'Graduate', 'Dropped out' ), 'sponsor_tiers_live' => ! empty( $GLOBALS['tiers_live'] ), 'auto_sync' => empty( $GLOBALS['auto_sync_off'] ), 'student_flags_mode' => isset( $GLOBALS['flags_mode'] ) ? $GLOBALS['flags_mode'] : 'off', 'student_quiet_days' => 14 );
		return isset( $known[ $key ] ) ? $known[ $key ] : $fallback;
	}
	public static function is_connected() { return true; }
}
class WPCPM_Content_Access {
	const META_KEY = '_wpcpm_access_level';
}
class WPCPM_Two_Factor {
	public static function prompt( $user ) { if ( ! empty( $GLOBALS['prompt_throws'] ) ) { throw new RuntimeException( 'The prompt failed.' ); } $GLOBALS['prompted'][] = $user instanceof WP_User ? $user->ID : 0; }
}
class WPCPM_Flash {
	public static function set( $c, $v, $u = 0 ) { $GLOBALS['flash'][ $c ] = $v; }
	public static function take( $c, $u = 0 ) { $v = isset( $GLOBALS['flash'][ $c ] ) ? $GLOBALS['flash'][ $c ] : ''; unset( $GLOBALS['flash'][ $c ] ); return $v; }
}
class WPCPM_Students_Dashboard {
	public static function page_url() { return isset( $GLOBALS['student_page'] ) ? $GLOBALS['student_page'] : ''; }
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
/** The institution an account acts for, by user ID, as a check says it is: the cards name a locked account's institution through it. */
class WPCPM_Institution_Members {
	public static function institution_of( $user = null ) {
		$id = $user instanceof WP_User ? (int) $user->ID : (int) $user;
		return isset( $GLOBALS['institution_of'][ $id ] ) ? $GLOBALS['institution_of'][ $id ] : '';
	}
}
class WPCPM_Institution_Roster {
	const ARG_VIEW = 'wpcpm_institution_view';
	public static function locked_today() { return isset( $GLOBALS['locked'] ) ? $GLOBALS['locked'] : array(); }
}
class WPCPM_Institutions_Index {
	// Whether the site keeps the record of institutions (1.123.102, Part B): off unless a check turns it on.
	public static function on_site() { return ! empty( $GLOBALS['on_site'] ); }
	public static function rows() { return isset( $GLOBALS['inst_rows'] ) ? $GLOBALS['inst_rows'] : array(); }
	public static function row( $id ) { return isset( $GLOBALS['inst_rows'][ (string) $id ] ) ? $GLOBALS['inst_rows'][ (string) $id ] : null; }
	public static function has( $id ) { return isset( $GLOBALS['inst_rows'][ (string) $id ] ); }
}
class WPCPM_Roster_Index {
	const NEVER_SHOWN = array( 'SPAM', 'Duplicated' );
	public static function read( $id ) { return isset( $GLOBALS['rosters'][ (string) $id ] ) ? $GLOBALS['rosters'][ (string) $id ] : array( 'v' => 4, 'read' => 0, 'rows' => array() ); }
	// The real predicate, word for word: a tracked student is waiting only with neither a mentor
	// on the Students row nor a joined report record (WPCredits#222). The roster's groups() and
	// programs() below share it, which is what keeps the two counts from drifting apart again.
	public static function is_waiting( array $row ) { return empty( $row['reports'] ) && empty( $row['has_mentor'] ); }
	public static function rows( $id ) { return self::read( $id )['rows']; }
	// The rows that name no institution (1.123.93: the students waiting to graduate read them too).
	public static function unlinked() { return isset( $GLOBALS['unlinked'] ) ? $GLOBALS['unlinked'] : array(); }
	// The rosters the last sync wrote, as the real counts option names them: every roster this world holds (1.123.93).
	public static function counts() { return array( 'v' => 4, 'read' => 0, 'institutions' => array_fill_keys( array_keys( $GLOBALS['rosters'] ), array() ), 'reconciliation' => array() ); }
}
// The Administrator an application or an institution is assigned to (Institutions off Airtable, R1), at the reads the
// assignment makes: the institution's record and the booking link. The Countries table's contact below is somebody
// else, and the card never reads it.
class WPCPM_Institution_Record {
	public static function get( $record_id ) { return isset( $GLOBALS['kept'][ $record_id ] ) ? $GLOBALS['kept'][ $record_id ] : array(); }
}
class WPCPM_Manager_Booking {
	public static function url_for( $user_id ) { return ''; }
}
class WPCPM_Countries {
	public static function contact_of( $country ) { return 'recCOUNTRY0000001' === (string) $country ? 'Ana Manager' : ''; }
	// The name the countries map holds for a record, which the answers table prints before the one stored.
	public static function name_of( $country ) { return 'recCOUNTRY0000001' === (string) $country ? 'Poland' : ''; }
}
/**
 * A wpdb as far as the helper reaches it: its one prepared read of `ID, post_title` among the IDs given,
 * answered from the world's posts of the application type, so the totals read a title and no meta.
 */
class WPCPM_Dashboard_Test_Wpdb {
	public $posts = 'wp_posts';
	public function prepare( $query, ...$args ) {
		$args = ( 1 === count( $args ) && is_array( $args[0] ) ) ? $args[0] : $args;
		$i    = 0;
		return preg_replace_callback( '/%[ds]/', static function ( $m ) use ( $args, &$i ) { $v = $args[ $i++ ]; return 'd' === $m[0][1] ? (string) (int) $v : "'" . $v . "'"; }, $query );
	}
	public function get_results( $query, $output = 'OBJECT' ) {
		preg_match( '/IN \(([0-9,\s]*)\)/', $query, $found );
		$ids  = isset( $found[1] ) ? array_map( 'intval', explode( ',', $found[1] ) ) : array();
		$type = preg_match( "/post_type = '([a-z_]+)'/", $query, $t ) ? $t[1] : '';
		$rows = array();
		foreach ( $GLOBALS['posts'] as $post ) {
			if ( in_array( (int) $post->ID, $ids, true ) && $type === $post->post_type ) { $rows[] = (object) array( 'ID' => (string) $post->ID, 'post_title' => $post->post_title ); }
		}
		return $rows;
	}
}
$GLOBALS['wpdb'] = new WPCPM_Dashboard_Test_Wpdb();
class WPCPM_Institution_Application {
	const POST_TYPE = 'wpcpm_inst_app';
	const STATE_NEW = 'new'; const STATE_HELD = 'held'; const STATE_INFO = 'info'; const STATE_REJECTED = 'rejected'; const STATE_SPAM = 'spam'; const STATE_APPROVED = 'approved';
	const META_STATE = '_wpcpm_app_state'; const META_COUNTRY = '_wpcpm_app_country'; const META_COUNTRY_NAME = '_wpcpm_app_country_name';
	const META_ASSIGNED = '_wpcpm_app_assigned'; const META_MANAGER = '_wpcpm_app_manager';
	const META_FIELDS = '_wpcpm_app_fields'; const META_CONSENT = '_wpcpm_app_consent'; const META_SITE = '_wpcpm_app_site';
	/**
	 * The form's questions in the order it asks them, at the spec's shape the answers table reads: the
	 * thirteen keyed by Airtable column, and the five kept on the site since 1.123.29, flagged `site`.
	 */
	public static function fields() {
		return array(
			'Name'              => array( 'group' => 'about', 'label' => 'Name of your institution' ),
			'Country'           => array( 'group' => 'about', 'label' => 'Country' ),
			'City'              => array( 'group' => 'about', 'label' => 'City' ),
			'Website'           => array( 'group' => 'about', 'label' => 'Website' ),
			'Contact Person'    => array( 'group' => 'contact', 'label' => 'Name of the person we should contact' ),
			'Contact Email'     => array( 'group' => 'contact', 'label' => 'Their email address' ),
			'contact_role'      => array( 'group' => 'contact', 'label' => 'Their role at the institution', 'site' => true ),
			'Department'        => array( 'group' => 'contact', 'label' => 'Department or faculty' ),
			'How do your internships or practices typically work?' => array( 'group' => 'program', 'label' => 'How do your internships or practicums typically work?' ),
			'Comments'          => array( 'group' => 'program', 'label' => 'If you ticked "Other", please tell us how' ),
			'Estimated number of students who may be interested'   => array( 'group' => 'program', 'label' => 'Roughly how many students may be interested?' ),
			'student_languages' => array( 'group' => 'program', 'label' => 'In which languages do your students study?', 'site' => true ),
			'signer_name'       => array( 'group' => 'agreement', 'label' => 'Who will sign the agreement for the institution?', 'site' => true ),
			'signer_title'      => array( 'group' => 'agreement', 'label' => 'Their title', 'site' => true ),
			'credits_page'      => array( 'group' => 'agreement', 'label' => 'Your page about WordPress Credits', 'site' => true ),
			'Why are you interested in offering WordPress Credits to your students?' => array( 'group' => 'more', 'label' => 'Why are you interested in offering WordPress Credits to your students?' ),
			"Anything else you\xE2\x80\x99d like us to know?" => array( 'group' => 'more', 'label' => "Anything else you\xE2\x80\x99d like us to know?" ),
			'Privacy Policy Compliance' => array( 'group' => 'consent', 'label' => 'I confirm I have read the WordPress.org privacy policy and that this institution can share students\' details with the program under it.' ),
		);
	}
	/** The answers kept on the site, as the real one reads them: the stored list, or none. */
	public static function site_answers( $application_id ) {
		$answers = get_post_meta( (int) $application_id, self::META_SITE, true );
		return is_array( $answers ) ? $answers : array();
	}
	/**
	 * The rows in those states, as the real one reads them: bounded when the caller says so.
	 *
	 * `$GLOBALS['loaded']` counts the post objects handed out, which is what the real
	 * `get_posts()` would build and prime the meta cache for - the applicant's whole
	 * submitted form, per row. The card must never pay that for a row it does not draw
	 * (deep check FADMN-2).
	 */
	public static function applications( $states, $limit = 0 ) {
		$GLOBALS['asked'][] = array( 'applications', (array) $states, (int) $limit );
		$out = self::matching( $states );
		if ( (int) $limit > 0 ) { $out = array_slice( $out, 0, (int) $limit ); }
		$GLOBALS['loaded'] += count( $out );
		return $out;
	}
	/** The same rows as IDs: no object, no meta cache, which is what a total costs. */
	public static function application_ids( $states ) {
		$GLOBALS['asked'][] = array( 'application_ids', (array) $states );
		return array_map( static function ( $post ) { return (int) $post->ID; }, self::matching( $states ) );
	}
	private static function matching( $states ) {
		$out = array();
		foreach ( $GLOBALS['posts'] as $post ) {
			if ( 'wpcpm_inst_app' === $post->post_type && in_array( get_post_meta( $post->ID, self::META_STATE, true ), (array) $states, true ) ) { $out[] = $post; }
		}
		return $out;
	}
	public static function reference( $id ) { return sprintf( 'APP-2026-%04d', (int) $id ); }
	/** Where the Institutions screen's queue opens one application, as the real class builds it. */
	public static function queue_url( $id ) { return 'https://example.test/wp-admin/admin.php?page=wpcpm-institutions&wpcpm_app_id=' . (int) $id; }
}
class WPCPM_Institutions {
	const FLASH = 'institutions';
	const ACTION_APPROVE = 'wpcpm_app_approve';
	public static function open_states() { return array( 'new', 'held', 'info' ); }
	public static function application_state( WP_Post $post ) { return (string) get_post_meta( $post->ID, '_wpcpm_app_state', true ); }
	public static function application_reference( WP_Post $post ) { return WPCPM_Institution_Application::reference( $post->ID ); }
	/** The words the Institutions screen uses for a state, as the real class holds them. */
	public static function application_state_label( $state ) {
		$words = array( 'new' => 'new', 'held' => 'held by the anti-spam checks', 'info' => 'waiting on the applicant', 'spam' => 'marked as spam', 'rejected' => 'rejected', 'approved' => 'approved' );
		return isset( $words[ $state ] ) ? $words[ $state ] : $state;
	}
	public static function country_name( $id, $stored ) { return '' !== $stored ? $stored : ( 'recCOUNTRY0000001' === (string) $id ? 'Poland' : '' ); }
	public static function queue_messages() { return array_merge( array( 'app-approved' => array( 'success', 'The application is approved.' ) ), isset( $GLOBALS['wpcpm_test_extra_queue_messages'] ) ? (array) $GLOBALS['wpcpm_test_extra_queue_messages'] : array() ); }
	/**
	 * The answers table, the real one's: the Institutions screen's class is read in under another
	 * name below, and this hands it the post, so the card's "Read the application" folds in exactly
	 * what the screen's queue draws.
	 */
	public function render_application_answers( WP_Post $post ) { ( new WPCPM_Test_Real_Institutions() )->render_application_answers( $post ); }
	public function render_application_actions( WP_Post $post, $state, $return = '' ) {
		// On the parts the real one prints one Decide area (bin/test-institutions-screen.php draws it); this one prints
		// the same area through the real helper, Approve at once and, for a new one, Mark as spam with its question.
		if ( WPCPM_UI::on() ) {
			$hidden = static function ( $action ) use ( $post, $return ) {
				return static function () use ( $action, $post, $return ) {
					echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" /><input type="hidden" name="wpcpm_application" value="' . (int) $post->ID . '" />';
					WPCPM_Return::field( (string) $return, 'applications' );
				};
			};
			$attrs     = array( 'method' => 'post', 'action' => 'https://example.test/wp-admin/admin-post.php', 'data-wpcpm-once' => true, 'data-wpcpm-busy' => 'Working' );
			$decisions = array( array( 'key' => 'approve', 'label' => 'Approve', 'kind' => 'primary', 'form_attrs' => $attrs, 'hidden' => $hidden( self::ACTION_APPROVE ) ) );
			if ( 'new' === $state ) {
				$decisions[] = array( 'key' => 'spam', 'label' => 'Mark as spam', 'kind' => 'danger', 'form_attrs' => $attrs, 'hidden' => $hidden( 'wpcpm_app_spam' ), 'confirm' => 'Mark as spam?' );
			}
			echo WPCPM_UI::decide( array( 'id' => 'wpcpm-decide-app-' . (int) $post->ID, 'decisions' => $decisions, 'side' => 'front' ) );
			return;
		}
		echo '<form class="wpcpm-app-action" method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Working">';
		echo '<input type="hidden" name="action" value="' . self::ACTION_APPROVE . '" /><input type="hidden" name="wpcpm_application" value="' . (int) $post->ID . '" />';
		WPCPM_Return::field( (string) $return, 'applications' );
		echo '<button type="submit" class="button button-primary">Approve</button></form>';
		// The real class's render_decision_form() also draws confirm-guarded decisions (Reject
		// as spam, Delete for good); mirrored here for the 'new' state only, so the applications
		// card's HTML really does carry a data-wpcpm-confirm sentence for the guard to yield to.
		if ( 'new' === $state ) {
			// Attribute order as the real render_decision_form() prints it: the guard's two
			// attributes first, the confirm appended last.
			echo '<form class="wpcpm-app-action" method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Working" data-wpcpm-confirm="Mark as spam?">';
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
	/** The day a document was revoked, as the real class reads it: nothing for a document that is not revoked. */
	public static function revoked_facts( $post_id ) { return isset( $GLOBALS['agr_revoked_facts'][ (int) $post_id ] ) ? $GLOBALS['agr_revoked_facts'][ (int) $post_id ] : array( 'at' => 0, 'note' => '' ); }
	public static function summary( $record ) { return array( 'state' => isset( $GLOBALS['agr_summary'][ $record ] ) ? $GLOBALS['agr_summary'][ $record ] : 'none' ); }
	/** The real rule, word for word (1.123.102, Part B and round 3, V4): why a revoked agreement may not be reinstated. */
	public static function reinstate_block( $record ) {
		$row = WPCPM_Institutions_Index::row( trim( (string) $record ) );
		if ( ! is_array( $row ) ) {
			return array( 'reason' => 'unlisted', 'stage' => '' );
		}
		$stage  = isset( $row['stage'] ) && is_scalar( $row['stage'] ) ? trim( (string) $row['stage'] ) : '';
		$active = (array) WPCPM_Settings::get_value( 'institution_active_stages', array() );
		return array( 'reason' => WPCPM_Institutions_Sync::is_in_pipeline( $stage, $active ) ? '' : 'left', 'stage' => $stage );
	}
	/** The words an Administrator reads for a summary state, as the real class holds them. */
	public static function summary_label( $state ) {
		$words = array( 'none' => 'Not started', 'generated' => 'Template generated', 'submitted' => 'Awaiting review', 'returned' => 'Returned', 'revoked' => 'Revoked', 'accepted' => 'Accepted', 'on_file' => 'On file' );
		return isset( $words[ $state ] ) ? $words[ $state ] : (string) $state;
	}
}
class WPCPM_Institution_Panel {
	// The way back a card asks the block for, its third argument after whether the block draws the
	// decisions, recorded apart from the post: the real block prints it in both of its forms (pinned in
	// bin/test-institution-panel.php). On the parts the real block keeps its anchor and no class of the old block, and
	// decides in one Decide area: so does this one.
	public static function render_review( $post_id, $decide = true, $where = '' ) { $GLOBALS['reviews'][] = (int) $post_id; $GLOBALS['review_returns'][] = (string) $where; echo WPCPM_UI::on() ? '<section class="wpcpm-ui-item__part" id="wpcpm-review-' . (int) $post_id . '"><div class="wpcpm-ui-decide" id="wpcpm-decide-agr-' . (int) $post_id . '" data-stub="review"></div></section>' : '<section class="wpcpm-review" id="wpcpm-review-' . (int) $post_id . '"><form class="wpcpm-review__form wpcpm-review__form--accept"></form></section>'; }
	/** The institution agreement's own words, under the keys the sponsor agreement's map shares. */
	public static function messages() {
		return array(
			'agreement-accepted' => array( 'success', 'Accepted.' ),
			'agreement-returned' => array( 'success', 'Returned: everybody at the institution has your note.' ),
			'agreement-note'     => array( 'error', 'Nothing was returned. The note is the whole of what the institution is told.' ),
		);
	}
}
class WPCPM_Semester_Report {
	const STATE_DRAFT = 'draft'; const STATE_APPROVED = 'approved';
	public static function queue() { return isset( $GLOBALS['reports']['queue'] ) ? $GLOBALS['reports']['queue'] : array(); }
	public static function due( $today ) { $GLOBALS['asked'][] = array( 'due', $today ); return isset( $GLOBALS['reports']['due'] ) ? $GLOBALS['reports']['due'] : array(); }
	public static function approved_since( $ts ) { $GLOBALS['asked'][] = array( 'approved_since', (int) $ts ); return isset( $GLOBALS['reports']['approved'] ) ? $GLOBALS['reports']['approved'] : array(); }
	public static function reports_of( $record ) { return isset( $GLOBALS['reports_of'][ $record ] ) ? $GLOBALS['reports_of'][ $record ] : array(); }
	public static function state( WP_Post $post ) { return (string) get_post_meta( $post->ID, '_wpcpm_report_state', true ); }
	/** The words an Administrator reads for a report's state, as the real class holds them. */
	public static function state_label( $state ) {
		$words = array( 'none' => 'Not drafted', 'approved' => 'Approved' );
		return isset( $words[ $state ] ) ? $words[ $state ] : 'Draft';
	}
}
class WPCPM_Semester_Report_Screen {
	const ACTION_DRAFT = 'wpcpm_report_draft';
	const FLASH        = 'institution_report';
	// With `$GLOBALS['no_report_url']` set, as the real class answers while the Institution Dashboard's page is missing.
	public static function report_url( $cohort ) { return ! empty( $GLOBALS['no_report_url'] ) ? '' : 'https://example.test/institution-dashboard/?wpcpm_report=' . $cohort . '#wpcpm-report'; }
	public static function render_draft_form( $record, $cohort, $label = '', $return = '' ) {
		// On the parts, bound for the dashboard, the real one prints a form of no class with the quiet button
		// (bin/test-semester-report.php): so does this one.
		if ( WPCPM_Return::DASHBOARD === (string) $return && WPCPM_UI::on() ) {
			echo '<form method="post" data-wpcpm-once><input type="hidden" name="action" value="' . self::ACTION_DRAFT . '" /><input type="hidden" name="institution" value="' . esc_attr( $record ) . '" /><input type="hidden" name="cohort" value="' . esc_attr( $cohort ) . '" />';
			WPCPM_Return::field( (string) $return, 'reports' );
			echo WPCPM_UI::button( array( 'label' => '' !== $label ? $label : 'Draft now', 'kind' => 'quiet', 'side' => 'front' ) ) . '</form>';
			return;
		}
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
	const STATE_OPEN = 'open'; const STATE_DONE = 'done'; const STATE_DECLINED = 'declined'; const STATE_WITHDRAWN = 'withdrawn'; const OVERDUE_DAYS = 14;
	const KIND_DETAILS = 'details';
	const ACTION_RESOLVE = 'wpcpm_resolve_request';
	// A school's details request is decided by the card's own buttons (Institutions release 2). The
	// names are the real class's, which the check below reads out of its file.
	const ACTION_DETAILS_DECIDE = 'wpcpm_decide_details';
	const TYPED_NOTE = 'request-close';
	const MAX_NOTE = 2000;
	public static function open_requests( $limit = 20 ) { return isset( $GLOBALS['requests']['open'] ) ? $GLOBALS['requests']['open'] : array(); }
	public static function closed_requests( $limit = 20 ) { return isset( $GLOBALS['requests']['closed'] ) ? $GLOBALS['requests']['closed'] : array(); }
	public static function facts( $id ) { return isset( $GLOBALS['facts'][ (int) $id ] ) ? $GLOBALS['facts'][ (int) $id ] : array(); }
	public static function render_decisions( $id, $return = '' ) {
		// On the parts the real one prints two intro lines and one Decide area (bin/test-institution-request.php): so does
		// this one, with no old class, the way back inside the area as the real area's forms hold it.
		if ( WPCPM_UI::on() ) {
			echo WPCPM_UI::intro( 'The request.' ) . '<div class="wpcpm-ui-decide" id="wpcpm-decide-req-' . (int) $id . '">';
			WPCPM_Return::field( (string) $return, 'requests' );
			echo '</div>';
			return;
		}
		echo '<form class="wpcpm-request__decide" method="post" data-wpcpm-once data-wpcpm-busy="Saving"><input type="hidden" name="action" value="' . self::ACTION_RESOLVE . '" /><input type="hidden" name="request" value="' . (int) $id . '" />';
		WPCPM_Return::field( (string) $return, 'requests' );
		echo '<button type="submit" name="state" value="done">Mark as handled</button></form>';
	}
	public static function messages() { return array( 'request-done' => array( 'success', 'Handled.' ) ); }
	// The details request's Decide area, drawn for real in bin/test-institution-request.php (Task 12 fix round 1).
	public static function render_details_decide( $id, $return = '' ) {
		$GLOBALS['details_decides'][] = array( (int) $id, (string) $return );
		echo '<div class="wpcpm-ui-decide" id="wpcpm-decide-req-' . (int) $id . '" data-stub="details-decide"></div>';
	}
}
class WPCPM_Students_Sync {
	const OPT_LAST = 'wpcpm_students_last_sync'; const CRON_AUTO = 'wpcpm_students_daily';
	// The two keys the students waiting to graduate are read by (1.123.93), the real class's.
	const META_PENDING = 'wpcpm_student_pending_since'; const META_COURSE = 'wpcpm_student_course';
	// And the three an account owing a Pending graduation is drawn from when no roster row is its (1.123.95).
	const META_INSTITUTION = 'wpcpm_student_institution'; const META_PROGRAM = 'wpcpm_student_program'; const META_MENTOR = 'wpcpm_student_mentor';
	public static function progress() { return $GLOBALS['sync']['students']; }
}
class WPCPM_Institutions_Sync {
	const CRON_DAILY = 'wpcpm_institutions_sync_daily';
	public static function progress() { return $GLOBALS['sync']['institutions']; }
	// The columns the answers table matches the country and the consent by, as the real map names them.
	public static function fields() { return array( 'name' => 'Name', 'country' => 'Country', 'contact_email' => 'Contact Email', 'consent' => 'Privacy Policy Compliance' ); }
	public static function last_read() { return (int) get_option( 'wpcpm_institutions_last_sync', 0 ); }
	// The real test, word for word: an empty stage is inside the pipeline (1.123.102, Part B).
	public static function is_in_pipeline( $stage, array $active ) { return '' === trim( $stage ) || in_array( $stage, $active, true ); }
}
class WPCPM_Private_Files {
	public static function probe_result() { return isset( $GLOBALS['probe'] ) ? $GLOBALS['probe'] : null; }
	public static function verdict( array $r ) { return ! empty( $r['blocked'] ) ? 'blocked' : ( $r['status'] >= 200 && $r['status'] < 300 ? 'served' : 'unknown' ); }
}
class WPCPM_Mail {
	public static function run() { return isset( $GLOBALS['invite_run'] ) ? $GLOBALS['invite_run'] : array(); }
	public static function queued() { return isset( $GLOBALS['invite_queued'] ) ? (int) $GLOBALS['invite_queued'] : 0; }
	// The real one's rule: the mailbox's first character and the whole domain.
	public static function mask_address( $a ) { $at = strrpos( (string) $a, '@' ); return false === $at || 0 === $at ? ( '' === (string) $a ? '' : '***' ) : substr( (string) $a, 0, 1 ) . '***' . substr( (string) $a, $at ); }
}
// The email log the health card reads: its latest row, and how many failed since the time asked.
class WPCPM_Mail_Log {
	const STATUS_FAILED = 'failed';
	// Whether the log's table is there; a check that takes it away sets `$GLOBALS['mail_log_missing']`.
	public static function exists() { return empty( $GLOBALS['mail_log_missing'] ); }
	// With no table the real ones find nothing: no latest row, no failures.
	public static function latest() { return isset( $GLOBALS['mail_latest'] ) && self::exists() ? $GLOBALS['mail_latest'] : null; }
	public static function failures_since( $since ) { $GLOBALS['mail_failed_since'][] = $since; return isset( $GLOBALS['mail_failed'] ) && self::exists() ? (int) $GLOBALS['mail_failed'] : 0; }
	public static function status_label( $status ) { return 'sent' === $status ? 'Handed to the mail server' : ( 'failed' === $status ? 'Failed' : 'Not recorded' ); }
}
// The tool registry, with the Emails tool filtered out of it: the card links the Log at the address
// the tool would have. A check registers a tool by putting one in `$emails`.
class WPCPM_Tools {
	public static $emails = null;
	public static function get( $id ) { return 'emails' === $id ? self::$emails : null; }
}
// The program's updates (1.123.71), standing in: the newest an Administrator may read, none unless a
// check sets them.
class WPCPM_Updates {
	public static function posts( $max = 5, $audience = '' ) { $GLOBALS['updates_asked'][] = array( $max, $audience ); return array_slice( $GLOBALS['updates_posts'] ?? array(), 0, $max ); }
}

class WPCPM_Handbook_Assistant {
	// Prints nothing while the suite sets `resources_quiet`, as the Tools stand-in below does with
	// `tools_quiet`: the page's heading over the two sections is printed only over something.
	public static function render_resources( $audience = '', $extra = '' ) { $GLOBALS['resources'][] = $audience; return empty( $GLOBALS['resources_quiet'] ) ? '<section class="wpcpm-handbook__resources" data-audience="' . esc_attr( $audience ) . '"></section>' : ''; }
}
class WPCPM_Sponsor_Tools {
	const AUDIENCE_MANAGERS = 'managers';
	public static function render( $audience, $viewer ) { $GLOBALS['tools'][] = array( $audience, $viewer instanceof WP_User ? $viewer->ID : 0 ); if ( empty( $GLOBALS['tools_quiet'] ) ) { echo WPCPM_UI::on() ? '<section class="wpcpm-tools" id="wpcpm-tools"></section>' : '<section class="wpcpm-student__section wpcpm-tools" id="wpcpm-tools"></section>'; } }
}
abstract class WPCPM_Sync_Module {
	public static function sync_messages() { return array( 'started' => array( 'success', 'Sync started.' ) ); }
}
/**
 * One read of a sponsor queue, as each class's own read answers it: the oldest `$limit` of what
 * waits. The cards read a queue twice, its rows at the card's own limit and how many wait, the
 * posts and the agreements at one more than the Sponsors screen's ceiling and the applications in
 * full, and the two are separate queries: a check that puts a number in
 * `$GLOBALS['count_short'][ $queue ]` has the longer read see that many fewer, as a row decided
 * between the two queries would.
 *
 * @param string $queue   `posts`, `agreements` or `applications`.
 * @param array  $waiting Everything waiting in the queue, oldest first.
 * @param int    $limit   Most rows to read.
 * @return array
 */
function sponsor_read( $queue, array $waiting, $limit ) {
	$read = array_slice( $waiting, 0, max( 1, (int) $limit ) );

	if ( (int) $limit > WPCPM_Administrators_Cards::LIMIT && ! empty( $GLOBALS['count_short'][ $queue ] ) ) {
		$read = array_slice( $read, 0, max( 0, count( $read ) - (int) $GLOBALS['count_short'][ $queue ] ) );
	}

	return $read;
}
class WPCPM_Sponsor_Posts {
	public static function pending_all( $limit = 50 ) { return sponsor_read( 'posts', isset( $GLOBALS['sponsor_posts'] ) ? $GLOBALS['sponsor_posts'] : array(), $limit ); }
	// On the parts the real one prints one Decide area (bin/test-sponsor-posts.php): so does this one, with no old class.
	public static function render_decision( $id, $return = '' ) { echo WPCPM_UI::on() ? '<div class="wpcpm-ui-decide" id="wpcpm-decide-post-' . (int) $id . '" data-post="' . (int) $id . '" data-return="' . esc_attr( $return ) . '"></div>' : '<div class="wpcpm-request__decide wpcpm-sponsor-post__decide" data-post="' . (int) $id . '" data-return="' . esc_attr( $return ) . '"></div>'; }
	public static function messages() { return array( 'post-published' => array( 'success', 'The post is published.' ) ); }
}
/** The review of sponsor offers (1.123.18): its rows as the queue reads them, and each item drawn as a marker. */
class WPCPM_Sponsor_Offer_Review {
	const CARD = 'sponsor-offers-review';
	public static function waiting_all( $limit = 50 ) { return sponsor_read( 'offers', isset( $GLOBALS['offer_reviews'] ) ? $GLOBALS['offer_reviews'] : array(), $limit ); }
	// On the parts the real one opens the item through WPCPM_UI::item_open() and decides in one Decide area
	// (bin/test-sponsor-offers.php): so does this one, the way back recorded on the area.
	public static function render_item( array $row, $return = '' ) {
		if ( WPCPM_UI::on() ) {
			echo WPCPM_UI::item_open( 'wpcpm-sponsor-offer', 'wpcpm-sponsor-offer-' . (int) $row['id'] ) . '<div class="wpcpm-ui-decide" id="wpcpm-decide-offer-' . (int) $row['id'] . '" data-return="' . esc_attr( $return ) . '"></div></article>';
			return;
		}
		echo '<article class="wpcpm-administrator__item wpcpm-sponsor-offer" id="wpcpm-sponsor-offer-' . (int) $row['id'] . '" data-return="' . esc_attr( $return ) . '"></article>';
	}
	public static function messages() { return array( 'offer-approved' => array( 'success', 'Approved. Students and mentors the offer is open to see it as it now reads.' ) ); }
}
class WPCPM_Sponsor_Agreement {
	public static function awaiting_review( $limit = 200 ) { return sponsor_read( 'agreements', isset( $GLOBALS['agr_review'] ) ? $GLOBALS['agr_review'] : array(), $limit ); }
	public static function revoked_all( $limit = 200 ) { return isset( $GLOBALS['agr_revoked'] ) ? $GLOBALS['agr_revoked'] : array(); }
	public static function review_facts( $id ) { return isset( $GLOBALS['agr_facts'][ (int) $id ] ) ? $GLOBALS['agr_facts'][ (int) $id ] : array(); }
	// On the parts the real one prints one Decide area (bin/test-sponsor-agreement.php): so does this one, with no old class.
	public static function render_decision( $id, $return = '' ) { echo WPCPM_UI::on() ? '<div class="wpcpm-ui-decide" id="wpcpm-decide-sagr-' . (int) $id . '" data-post="' . (int) $id . '" data-return="' . esc_attr( $return ) . '"></div>' : '<div class="wpcpm-request__decide wpcpm-sponsor-agreement__decide" data-post="' . (int) $id . '" data-return="' . esc_attr( $return ) . '"></div>'; }
	// Task V1, ruling 14: Reinstate's one form for a revoked agreement's row (bin/test-sponsor-agreement.php pins the real one).
	public static function reinstate_form( $id, $return = '' ) { return '<form data-post="' . (int) $id . '" data-return="' . esc_attr( $return ) . '"><button class="wpcpm-btn wpcpm-btn--quiet">Reinstate</button></form>'; }
	/** The address of an uploaded copy's bytes, '' for a Drive copy (Task 13b: the item header's "Download" on the parts). */
	public static function download_url( $id ) { return 'own' === ( $GLOBALS['agr_facts'][ (int) $id ]['kind'] ?? '' ) ? 'https://example.test/download/' . (int) $id : ''; }
	/** Before each outcome a decision taken on the Administrator Dashboard leaves on its channel. */
	const DASHBOARD_PREFIX = 'sponsor-';
	/** The sponsor agreement's words for the company, under the institution agreement's keys. */
	public static function manager_messages() {
		return array(
			'agreement-accepted' => array( 'success', 'The agreement is accepted.' ),
			'agreement-returned' => array( 'success', 'Returned: everybody at the company has your note.' ),
			'agreement-note'     => array( 'error', 'Nothing was returned. The note is the whole of what the company is told.' ),
		);
	}
}
class WPCPM_Sponsor_Application {
	const QUERY_QUEUE = 'wpcpm_sapp_id';
	public static function queue_facts( $limit = 50 ) { return sponsor_read( 'applications', isset( $GLOBALS['sponsor_apps'] ) ? $GLOBALS['sponsor_apps'] : array(), $limit ); }
	/** The open applications, at most `$limit` of them, the menu bubble's count, or every one for 0, the cards' count. */
	public static function pending_count( $limit = 0 ) { return count( sponsor_read( 'applications', isset( $GLOBALS['sponsor_apps'] ) ? $GLOBALS['sponsor_apps'] : array(), (int) $limit > 0 ? (int) $limit : PHP_INT_MAX ) ); }
	// On the parts the real ones print one Decide area and the answers as the parts' key and value table
	// (bin/test-sponsor-application.php): so do these, with no old class.
	public static function render_decision( $id, $return = '' ) { echo WPCPM_UI::on() ? '<div class="wpcpm-ui-decide" id="wpcpm-decide-sapp-' . (int) $id . '" data-post="' . (int) $id . '" data-return="' . esc_attr( $return ) . '"></div>' : '<div class="wpcpm-app-action wpcpm-sponsor-application__decide" data-post="' . (int) $id . '" data-return="' . esc_attr( $return ) . '"></div>'; }
	public static function render_details( $post ) { echo WPCPM_UI::on() ? '<table class="wpcpm-ui-table wpcpm-ui-table--keyvalue" role="table" data-post="' . (int) $post->ID . '"><tbody role="rowgroup"></tbody></table>' : '<div class="wpcpm-sapp-details-stub" data-post="' . (int) $post->ID . '"></div>'; }
	public static function manager_messages() { return array( 'sapp-approved' => array( 'success', 'The sponsor application is approved.' ) ); }
}
/** The mentor applications waiting for a decision (1.122.31): their number, all the cards read of them. */
class WPCPM_Mentor_Application {
	public static function waiting_count() { return isset( $GLOBALS['mentor_waiting'] ) ? (int) $GLOBALS['mentor_waiting'] : 0; }
	public static function open_states() { return array( 'new', 'confirmed' ); }
	// The open applications as posts, which the page reads only while some wait, for the oldest's age.
	public static function applications( array $states, $limit = 0 ) { return isset( $GLOBALS['mentor_open_posts'] ) ? (array) $GLOBALS['mentor_open_posts'] : array(); }
}
class WPCPM_Sponsor_Offers {
	const KIND_CODES  = 'codes';
	const KIND_SHARED = 'shared';
	public static function all() { return isset( $GLOBALS['offers'] ) ? $GLOBALS['offers'] : array(); }
	public static function live() { return array_values( array_filter( self::all(), static function ( $o ) { return 'live' === $o['state']; } ) ); }
	public static function is_live( array $offer, $today = '' ) {
		if ( 'live' !== $offer['state'] ) { return false; }
		if ( '' === (string) $offer['expires'] ) { return true; }
		return strcmp( (string) $offer['expires'], '' !== $today ? $today : wp_date( 'Y-m-d' ) ) >= 0;
	}
}
class WPCPM_Sponsor_Codes {
	public static function counts( $offer_id ) { return isset( $GLOBALS['codes'][ (int) $offer_id ] ) ? $GLOBALS['codes'][ (int) $offer_id ] : array( 'available' => 0, 'claimed' => 0, 'void' => 0, 'total' => 0 ); }
	public static function claims( $offer_id ) { return isset( $GLOBALS['claims'][ (int) $offer_id ] ) ? $GLOBALS['claims'][ (int) $offer_id ] : array(); }
}
class WPCPM_Sponsors_Index {
	const STATUS_APPROVED = 'Approved';
	/** The real rule for a test name, body for body: `bin/test-test-institutions.php` holds this copy to the class's own. */
	public static function is_test_name( $name ) {
		$name    = trim( (string) $name );
		$matched = preg_match( '/^TEST(?![\p{L}\p{N}])/u', $name );

		if ( false !== $matched ) {
			return 1 === $matched;
		}

		return 0 === strncmp( $name, 'TEST', 4 ) && ( 4 === strlen( $name ) || 1 !== preg_match( '/^[A-Za-z0-9]$/', $name[4] ) );
	}
	public static function rows() { return isset( $GLOBALS['sponsor_rows'] ) ? $GLOBALS['sponsor_rows'] : array(); }
	public static function row( $record ) { $rows = self::rows(); return isset( $rows[ $record ] ) ? $rows[ $record ] : null; }
	public static function is_key( $v ) { return 1 === preg_match( '/^(rec|spo)[A-Za-z0-9]{14}\z/', (string) $v ); }
	/** The assigned manager as the program's screens name them, for the calls made: the real rule is pinned in bin/test-sponsors-store.php. */
	public static function manager_label( array $row ) { return isset( $row['record_id'], $GLOBALS['sponsor_managers'][ $row['record_id'] ] ) ? $GLOBALS['sponsor_managers'][ $row['record_id'] ] : ''; }
}
class WPCPM_Sponsor_Roster {
	const ARG_VIEW = 'wpcpm_sponsor_view';
}
require_once __DIR__ . '/stubs/sponsor-card-url.php';
class WPCPM_Sponsors_Dashboard {
	const VIEW_ARG         = 'wpcpm_view';
	const VIEW_OFFERS      = 'offers';
	const VIEW_SPONSORSHIP = 'sponsorship';
	public static function card_url( $card, $record = '', $item = '' ) { return wpcpm_test_sponsor_card_url( self::page_url(), $card, $record, $item ); }
	public static function page_url() { return isset( $GLOBALS['sponsor_page_url'] ) ? $GLOBALS['sponsor_page_url'] : 'https://site.example/sponsor-dashboard/'; }
	// Not in the brief's stub, but WPCPM_Dashboards::links() (a pre-existing check in this same
	// suite) calls this on every class it finds through class_exists(), so it must exist once
	// this class does; false matches WPCPM_Institutions_Dashboard's own stub just above.
	public static function is_member() { return false; }
}
class WPCPM_Institution_Audit {
	public static function sponsor_entries( $kind = '', $limit = 50 ) {
		$rows = isset( $GLOBALS['audit'] ) ? $GLOBALS['audit'] : array();
		if ( '' !== $kind ) { $rows = array_values( array_filter( $rows, static function ( $r ) use ( $kind ) { return $r['kind'] === $kind; } ) ); }
		usort( $rows, static function ( $a, $b ) { return $b['time'] <=> $a['time']; } );
		return array_slice( $rows, 0, (int) $limit );
	}
}
class WPCPM_Sponsor_Interests {
	const LOG_KIND = 'sponsor_interest';
}
// The Student Duplicate Finder's scan, as the strip's last tile and its card read it (1.102.0): the
// stored report, of which the dashboard reads the counts and the read time and nothing else.
class WPCPM_Duplicates_Scan {
	public static function report() { return isset( $GLOBALS['dup_report'] ) ? $GLOBALS['dup_report'] : array(); }
}
if ( ! function_exists( 'size_format' ) ) { function size_format( $b, $d = 0 ) { return $b . ' B'; } }
/**
 * The sponsor report's store, stubbed to the contract the Sponsor reports card reads (1.115.0):
 * the three lists, and the three readers of one report, which answer from the seeded post's meta
 * under the store's own keys. The two lists that take an argument record it, as the semester
 * report's do, so a check can read the day and the moment the card asked about. The longest
 * narrative too, which the real report screen's words name.
 */
class WPCPM_Sponsor_Report {
	const MAX_TEXT     = 5000;
	const META_SPONSOR = '_wpcpm_sreport_sponsor';
	const META_UNTIL   = '_wpcpm_sreport_until';
	const META_FINAL   = '_wpcpm_sreport_final';
	public static function queue() { return array_map( 'get_post', isset( $GLOBALS['sreports']['queue'] ) ? $GLOBALS['sreports']['queue'] : array() ); }
	public static function due( $today = '' ) { $GLOBALS['asked'][] = array( 'sreport_due', $today ); return isset( $GLOBALS['sreports']['due'] ) ? $GLOBALS['sreports']['due'] : array(); }
	public static function final_since( $timestamp ) { $GLOBALS['asked'][] = array( 'final_since', (int) $timestamp ); return array_map( 'get_post', isset( $GLOBALS['sreports']['final'] ) ? $GLOBALS['sreports']['final'] : array() ); }
	public static function sponsor_of( WP_Post $post ) { return (string) get_post_meta( $post->ID, self::META_SPONSOR, true ); }
	public static function until_of( WP_Post $post ) { return (string) get_post_meta( $post->ID, self::META_UNTIL, true ); }
	public static function final_at( WP_Post $post ) {
		$stamp = get_post_meta( $post->ID, self::META_FINAL, true );
		return is_array( $stamp ) && ! empty( $stamp['at'] ) ? array( 'at' => (int) $stamp['at'], 'by' => isset( $stamp['by'] ) ? (int) $stamp['by'] : 0 ) : array();
	}
}
// The Sponsors screen's report class and the files class are the real ones: the card reads the
// draft form's action and field names and the editor's address from the first, which takes the
// editor's argument from the second, and the dashboard merges the first's words.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-report-files.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-report-screen.php';

// The tier ladder the Sponsors card's tiles and the terms-ending card both read; loaded before
// the cards class so class_exists( 'WPCPM_Sponsor_Tiers' ) is true for every check below.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-tiers.php';

/**
 * The sponsor event store, stubbed to the contract the Events card reads (1.117.0): the two
 * lists, each recorded as asked, the three readers of one event, answered from the seeded facts,
 * and the store's two readers of words the card prints, the sponsor's name and the length. The
 * waiting list comes back soonest first, as the real store answers it.
 */
class WPCPM_Sponsor_Event {
	const STATE_PLANNED   = 'planned';
	const STATE_APPROVED  = 'approved';
	const STATE_CANCELLED = 'cancelled';
	const MAX_NOTE        = 2000;
	public static function waiting() { $GLOBALS['asked'][] = array( 'sevent_waiting' ); return array_map( 'get_post', isset( $GLOBALS['sevents']['waiting'] ) ? $GLOBALS['sevents']['waiting'] : array() ); }
	public static function approved_upcoming() { $GLOBALS['asked'][] = array( 'sevent_upcoming' ); return array_map( 'get_post', isset( $GLOBALS['sevents']['upcoming'] ) ? $GLOBALS['sevents']['upcoming'] : array() ); }
	public static function facts( WP_Post $post ) { return $GLOBALS['sevent_facts'][ $post->ID ]; }
	public static function link_of( WP_Post $post ) { return isset( $GLOBALS['sevent_links'][ $post->ID ] ) ? $GLOBALS['sevent_links'][ $post->ID ] : ''; }
	public static function joiners( $post ) { $id = $post instanceof WP_Post ? $post->ID : (int) $post; return isset( $GLOBALS['sevent_people'][ $id ] ) ? $GLOBALS['sevent_people'][ $id ] : array(); }
	/** Whether an event is the program's own, as the store reads it: its kind, here the fixture's facts. */
	public static function is_program( WP_Post $post ) { return isset( $GLOBALS['sevent_facts'][ $post->ID ]['kind'] ) && 'program' === $GLOBALS['sevent_facts'][ $post->ID ]['kind']; }
	/** Whether a sponsor still offers its events: yes unless the fixture says otherwise. Asked of an event, of its sponsor, and the program's own always does, as the store answers. */
	public static function offers_events( $record ) {
		if ( $record instanceof WP_Post ) {
			if ( self::is_program( $record ) ) { return true; }
			$record = isset( $GLOBALS['sevent_facts'][ $record->ID ]['sponsor'] ) ? $GLOBALS['sevent_facts'][ $record->ID ]['sponsor'] : '';
		}
		return ! isset( $GLOBALS['sevent_offers'][ (string) $record ] ) || (bool) $GLOBALS['sevent_offers'][ (string) $record ];
	}
	/** The sponsor's name as the store's reader answers it: the index's name, else the key. */
	public static function sponsor_name( $record ) { $row = WPCPM_Sponsors_Index::row( (string) $record ); $name = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : ''; return '' !== $name ? $name : (string) $record; }
	/** How long an event runs, in the store's words. */
	public static function length_line( $minutes ) { return sprintf( _n( '%s minute', '%s minutes', (int) $minutes ), number_format_i18n( (int) $minutes ) ); }
}
if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $id ) { return get_user_by( 'id', $id ); }
}
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-assignment.php';
// Core's warm-up of many accounts in one query, which the card asks before it reads the names of
// the people on an event: nothing to warm here.
if ( ! function_exists( 'cache_users' ) ) {
	function cache_users( $user_ids ) { $GLOBALS['gr_cached'][] = array_values( (array) $user_ids ); }
}
if ( ! function_exists( 'wp_timezone_string' ) ) {
	function wp_timezone_string() { return isset( $GLOBALS['tz'] ) ? $GLOBALS['tz'] : 'UTC'; }
}
// The zone readers and the range the card prints an event's time with are the plugin's own.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-availability.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php';
// The rules for typed text, which the plugin loads on every request: the queue's refusals of lost
// words are said in their sentences.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';
// The queue is the real one, loaded only when it is there, so the checks that need it fail on
// their own before it exists rather than ending the run.
if ( file_exists( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-event-queue.php' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-event-queue.php';
}

// The dashboards' one shell, which every card on the page is drawn in.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-card.php';
// The frame of the version 3 concepts the page is drawn in (1.123.69): the top bar, the menu and the main
// area, with the few WordPress functions it asks that the page did not ask before.
if ( ! class_exists( 'WPCPM_Icons' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
}
if ( ! class_exists( 'WPCPM_UI' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
}
// The refusal markup the parts' boxes are drawn with, which the plugin loads on every request: the cards whose old
// branches left draw their boxes on the parts in every block (Task 18b).
if ( ! class_exists( 'WPCPM_Form_Errors' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
}
// The real switch has the Administrator Dashboard on (Task 18). Every drawing in this suite is its off branch unless a
// block says otherwise: the switch is put off here, each block that draws the parts turns it on and puts it off again
// after (Task 18a), and the suite's last block draws under the real switch, with no set_live() at all.
WPCPM_UI::set_live( array() );
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-frame.php';
if ( ! function_exists( 'get_theme_support' ) ) {
	function get_theme_support( $feature ) { return $GLOBALS['theme_support'][ $feature ] ?? false; }
}
if ( ! function_exists( 'get_avatar' ) ) {
	function get_avatar( $id, $size = 96, $default = '', $alt = '', $args = array() ) { return '<img class="' . ( $args['class'] ?? 'avatar' ) . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '">'; }
}
if ( ! function_exists( 'wp_logout_url' ) ) {
	function wp_logout_url( $redirect = '' ) { return 'https://example.test/wp-login.php?action=logout'; }
}
if ( ! function_exists( 'wp_sprintf' ) ) {
	function wp_sprintf( $pattern, ...$args ) {
		if ( '%l' !== $pattern ) {
			return vsprintf( $pattern, $args );
		}
		$list = array_values( (array) $args[0] );
		if ( count( $list ) < 3 ) {
			return implode( ' and ', $list );
		}
		$last = array_pop( $list );
		return implode( ', ', $list ) . ', and ' . $last;
	}
}
// The Sponsors screen's class, the real one: the ceiling its posts and agreements cards count to
// and the one printer of their counts, which the sponsor posts and agreements cards and tiles here
// print through, so this page prints those counts in that screen's form; and the one builder of
// the address of a sponsor's Edit form, which each Sponsor terms ending row links, an address
// bin/test-sponsors-screen.php pins. It extends the stand-in above, and the accounts screens'
// trait it uses is declared first.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-cards.php';
// What an account owes when a Feedback form 3 finished on the site could not set its status (1.123.95): the real class.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-graduation.php';
// The one helper that decides which institutions are tests, over the stand-ins of the index and of the
// sponsors' rule above (Ruling 21).
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-test-institutions.php';
if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = 'default' ) { return $text; }
}
// The Institutions screen's class, the real one, read in under another name through a rewrite of its
// source, as bin/test-sponsors-screen.php reads the sponsor posts' class: the stand-in above keeps
// the class's own name for everything the cards ask of it, and hands the answers table to this one,
// which extends the same stand-in parent and uses the trait declared above.
$wpcpm_test_institutions_source = preg_replace( '/^<\?php/', '', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' ), 1 );
$wpcpm_test_institutions_source = str_replace( "\nclass WPCPM_Institutions extends WPCPM_Sync_Module {\n", "\nclass WPCPM_Test_Real_Institutions extends WPCPM_Sync_Module {\n", $wpcpm_test_institutions_source, $wpcpm_test_renamed );
if ( 1 !== $wpcpm_test_renamed ) {
	echo "FAIL the real WPCPM_Institutions was not found where its declaration was read, so the answers table has nothing to draw it\n";
	exit( 1 );
}
eval( $wpcpm_test_institutions_source );
// The Mentor engagement card reads its summary through this class (1.122.26). The seam is set to an
// empty site before the suite's first collect() and cleared at the end of the run, so no later code
// in a longer process reads a stale input.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-sign-ins.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-flags.php';
WPCPM_Mentor_Engagement::for_tests( array( 'accounts' => array(), 'notes' => array(), 'calls' => array() ) );

// The program map is the compiled tracks' (bin/stubs/compiled-seeds.php), through the five filters
// `WPCPM_Tracks::init()` hooks. This suite's add_filter() records a hook without running it, so the
// five go where its apply_filters() runs them, as the checks below put theirs.
foreach ( array(
	'wpcpm_program_labels'        => 'filter_labels',
	'wpcpm_program_courses'       => 'filter_courses',
	'wpcpm_program_hours_targets' => 'filter_hours',
	'wpcpm_program_tracks'        => 'filter_tracks',
	'wpcpm_program_course_ids'    => 'filter_course_ids',
) as $map_hook => $map_filter ) {
	$GLOBALS['live_filters'][ $map_hook ][] = array( 'WPCPM_Tracks', $map_filter );
}
wpcpm_seed_compiled_options( $GLOBALS['opts'] );

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
/** Whether the words are in the text, a no-break space read as a space (Task 18f: a date kept whole in a cell or a row). */
function has_words( $h, $n ) { return has( str_replace( "\u{00A0}", ' ', (string) $h ), $n ); }
/** Whether both are in the text and the first comes first; false when either is missing. */
function before_in( $h, $first, $second ) { $a = strpos( (string) $h, (string) $first ); $b = strpos( (string) $h, (string) $second ); return is_int( $a ) && is_int( $b ) && $a < $b; }
/**
 * What something draws, as a manager sees it: the words a card's two links to its home in wp-admin
 * carry for a screen reader alone are set aside (`seen()`), so every check of the visible words and
 * markup reads them unchanged. The words themselves are read by `hidden_words()`.
 *
 * @param callable $fn What draws.
 * @return string
 */
function capture( $fn ) { ob_start(); call_user_func( $fn ); return seen( (string) ob_get_clean() ); }
/**
 * A drawn card with the number in its age tag ("441 days waiting") written as N, for a digest. The tag is the card
 * shell's (`WPCPM_Dashboard_Card::age()`), left set by the last page this suite drew, and counts the days from a
 * queue's oldest moment, a fixed day of the fixture, to now: it grows by one each day the suite runs. Only the
 * number is blanked; the tag, its classes and its words stay.
 *
 * @param string $html What was drawn.
 * @return string
 */
function no_age_days( $html ) { return (string) preg_replace( '#(<span class="wpcpm-card__age [^"]*">)\d[\d,]*( days? waiting</span>)#', '${1}N${2}', (string) $html ); }
/**
 * Markup with the hidden span after the words of a card's row action and of its body's last line,
 * the two links to the card's home in wp-admin, taken out. Nothing else is touched: a hidden span
 * anywhere else, on a button or in a list's own links, stays for the checks that read it.
 *
 * @param string $html The markup.
 * @return string
 */
function seen( $html ) {
	// The body's last line is the fold's foot since the cards' old branches left (Task 18b).
	return (string) preg_replace( '#(<p class="(?:wpcpm-sponsor__row-action|wpcpm-administrator__more|wpcpm-ui-fold__foot)"><a [^>]*>[^<]*)<span class="screen-reader-text">[^<]*</span>(</a></p>)#', '$1$2', (string) $html );
}
/**
 * The words a card's two links to its home in wp-admin carry for a screen reader alone, read from the
 * card as drawn: the row action's, then the body's last line's, each null where that link carries
 * none or is not drawn. Each must sit right after the link's words, inside the link, with the space
 * before it inside the hidden span.
 *
 * @param callable $draw What draws the card.
 * @return array{0: string|null, 1: string|null}
 */
function hidden_words( $draw ) {
	ob_start();
	call_user_func( $draw );
	$card = (string) ob_get_clean();
	$read = static function ( $class ) use ( $card ) {
		return 1 === preg_match( '#<p class="' . $class . '"><a [^>]*>[^<]+<span class="screen-reader-text"> ([^<]+)</span></a></p>#', $card, $words ) ? $words[1] : null;
	};
	// The body's last line is the fold's foot since the cards' old branches left (Task 18b).
	return array( $read( 'wpcpm-sponsor__row-action' ), $read( 'wpcpm-ui-fold__foot' ) );
}
/** Whether the text ends with the tail. */
function ends_with( $h, $tail ) { return substr( (string) $h, -strlen( (string) $tail ) ) === (string) $tail; }
/**
 * A tab of a wp-admin screen, at an element on it where one is named: the address a card's row
 * action and its last line link.
 *
 * @param string $page   The screen's page slug.
 * @param string $tab    The tab.
 * @param string $anchor The id of the card on that tab that lists the items, or '' for the tab's top.
 * @return string
 */
function tab_url( $page, $tab, $anchor = '' ) {
	return 'https://example.test/wp-admin/admin.php?page=' . $page . '&tab=' . $tab . ( '' !== $anchor ? '#' . $anchor : '' );
}
/**
 * How a card with a home in wp-admin ends, in the dashboards' shell: the line its body ends with, then,
 * after the fold, its row action, the two linking one address in the same words.
 *
 * @param string $href  The address.
 * @param string $words The link's words.
 * @return string
 */
function card_foot( $href, $words ) {
	// The body's line is the fold's foot since the cards' old branches left (Task 18b); this block draws the shell off.
	return '<p class="wpcpm-ui-fold__foot"><a href="' . $href . '">' . $words . '</a></p></div></details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="' . $href . '">' . $words . '</a></p></section>';
}

/**
 * One student waiting to graduate (1.123.93), on no institution's roster and with no account yet, for the pages drawn
 * with every queue holding something: the programs' figures read the institutions' rosters only, so they stay as
 * they are.
 *
 * @return array[] The roster rows that name no institution.
 */
function pending_unlinked() {
	return array(
		'recS0000000000090' => array( 'record_id' => 'recS0000000000090', 'name' => 'Pending Unlinked', 'status' => 'Pending graduation', 'start' => '2026-03-02', 'end' => '', 'reports' => array(), 'mentor_name' => '' ),
	);
}

/* ---- the fixture ---------------------------------------------------------- */

$A = 'recINSTA000000001';
$B = 'recINSTB000000002';
$C = 'recINSTC000000003';

$GLOBALS['manage']  = array( 3 );
$GLOBALS['uid']     = 3;
$GLOBALS['users']   = array( 3 => new WP_User( 3, 'Manager Three', 'administrator@example.test' ), 21 => new WP_User( 21, 'Rep One', 'administrator@example.test' ) );
$GLOBALS['posts']   = array();
$GLOBALS['pmeta']   = array();
$GLOBALS['asked']   = array();
// Every application post object a card was handed: the meter behind FADMN-2.
$GLOBALS['loaded']  = 0;
$GLOBALS['reviews'] = array();
$GLOBALS['review_returns'] = array();
$GLOBALS['next']    = array( 'wpcpm_students_daily' => 1757100000 );
// The site's date format, which the Sponsors tile's sentence prints the semester's first day in.
$GLOBALS['opts']['date_format']                  = 'j F Y';
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

// Uni Nueva carries the five answers kept on the site since 1.123.29; Uni Held was filed before them.
seed_post( 501, 'wpcpm_inst_app', 'Uni Nueva', array(
	'_wpcpm_app_state'        => 'new',
	'_wpcpm_app_country'      => 'recCOUNTRY0000001',
	'_wpcpm_app_country_name' => 'Poland',
	'_wpcpm_app_assigned'     => 12,
	'_wpcpm_app_fields'       => array( 'Name' => 'Uni Nueva', 'City' => 'Krakow', 'Contact Email' => 'office@example.test' ),
	'_wpcpm_app_site'         => array(
		'contact_role'      => 'Internship coordinator',
		'student_languages' => array( 'Polish', 'English' ),
		'signer_name'       => '',
		'signer_title'      => '',
		'credits_page'      => 'https://uni-nueva.example/credits',
	),
), '2026-09-01 08:00:00' );
seed_post( 502, 'wpcpm_inst_app', 'Uni Held', array( '_wpcpm_app_state' => 'held', '_wpcpm_app_country' => '', '_wpcpm_app_country_name' => '', '_wpcpm_app_manager' => array( 'manager' => 'recTEAMAAAAAAAAAA', 'email' => 'old.snapshot@example.test', 'calendly' => '' ) ), '2026-09-02 08:00:00' );
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
$GLOBALS['sponsor_posts'] = array( array( 'id' => 905, 'title' => 'Ten tips', 'record' => 'recSPN00000000001', 'company' => 'TEST Sponsor', 'author' => 'Member One', 'at' => time() - 3600, 'preview' => 'https://example.test/?p=905&preview=true' ) );
$GLOBALS['agr_review']  = array( 913 );
$GLOBALS['agr_revoked'] = array( 880 );
$GLOBALS['agr_facts']   = array(
	913 => array( 'post_id' => 913, 'state' => 'submitted', 'kind' => 'own', 'sponsor' => 'recSPN00000000001', 'sponsor_name' => 'TEST Sponsor', 'uploaded_by' => 'Member One', 'uploaded_at' => '2026-09-06', 'original_name' => 'signed.pdf', 'flags' => array(), 'size' => 652, 'members' => 1 ),
	880 => array( 'post_id' => 880, 'state' => 'revoked', 'kind' => 'legacy', 'sponsor' => 'recSPN00000000002', 'sponsor_name' => 'Old Sponsor', 'uploaded_by' => '', 'uploaded_at' => '2026-05-01', 'original_name' => '', 'flags' => array( 'javascript', '/OpenAction' ), 'size' => 0, 'members' => 2 ),
);
$GLOBALS['sponsor_apps'] = array(
	array( 'id' => 950, 'reference' => 'SAPP-2026-0950', 'company' => 'Gadgetry Inc', 'website' => 'https://gadgetry.example', 'person' => 'Sam Sponsor', 'email' => 'administrator@example.test', 'at' => time() - 7200, 'state' => 'new', 'state_label' => 'new', 'signals' => array( 'in-base' ), 'held' => false, 'duplicate' => false, 'in_base' => true ),
);
// get_post() must answer for every id in $GLOBALS['sponsor_apps'] above: render_sponsor_applications()
// looks the post up to fold render_details() under "Read the application" (S6).
seed_post( 950, 'wpcpm_sponsor_app', 'Gadgetry Inc' );
$GLOBALS['facts'] = array(
	801 => array( 'id' => 801, 'kind' => 'mentor', 'kind_label' => 'A mentor is wanted', 'state' => 'open', 'institution' => $A, 'institution_name' => 'Uniwersytet Alpha', 'note' => 'Two students without a mentor.', 'at' => time() - 20 * DAY_IN_SECONDS, 'overdue' => true, 'actor' => 21, 'actor_name' => 'Rep One' ),
	802 => array( 'id' => 802, 'kind' => 'mentor', 'kind_label' => 'A mentor is wanted', 'state' => 'open', 'institution' => $B, 'institution_name' => 'Universidad Beta', 'note' => '', 'at' => time() - DAY_IN_SECONDS, 'overdue' => false, 'actor' => 21, 'actor_name' => 'Rep One' ),
	803 => array( 'id' => 803, 'kind' => 'mentor', 'kind_label' => 'A mentor is wanted', 'state' => 'done', 'institution' => $A, 'institution_name' => 'Uniwersytet Alpha', 'note' => 'Handled.', 'at' => time() - 30 * DAY_IN_SECONDS, 'overdue' => false, 'actor' => 21, 'actor_name' => 'Rep One' ),
);
$GLOBALS['locked'] = array( new WP_User( 21, 'Rep One', 'administrator@example.test' ) );
$GLOBALS['sync'] = array(
	'students'     => array( 'running' => false, 'phase' => 'done', 'label' => 'Done', 'error' => '', 'elapsed' => 0 ),
	'mentors'      => array( 'running' => true, 'phase' => 'reports', 'label' => 'Reading reports', 'error' => '', 'elapsed' => 40 ),
	'institutions' => array( 'running' => false, 'phase' => 'done', 'label' => 'Done', 'error' => 'HTTP 429 from Airtable <b>x</b>', 'elapsed' => 0 ),
);
$GLOBALS['probe']         = array( 'status' => 403, 'time' => 1756700000, 'blocked' => true, 'error' => '', 'control_status' => 200, 'encrypted' => true );
$GLOBALS['mail_latest']   = array( 'sent_at' => '2026-09-04 12:46:40', 'to_email' => 'mia@example.test', 'template' => 'report-drafted', 'status' => 'sent' );
$GLOBALS['mail_failed']   = 1;
$GLOBALS['invite_run']    = array( 'total' => 5, 'started' => 1756990000, 'finished' => 0 );
$GLOBALS['invite_queued'] = 2;
// The tier ladder's fixture dates, from the suite's own clock (wp_date()'s 'Y-m-d'), so a day
// computed here and the day WPCPM_Sponsor_Tiers::today() computes when tiers_ending() runs
// with no $today of its own never drift apart across a midnight the two might straddle
// differently otherwise.
$tier_today = strtotime( wp_date( 'Y-m-d' ) . ' 00:00:00 UTC' );
$tier_in_40 = gmdate( 'Y-m-d', $tier_today + 40 * DAY_IN_SECONDS );
$tier_in_10 = gmdate( 'Y-m-d', $tier_today + 10 * DAY_IN_SECONDS );
$GLOBALS['sponsor_rows'] = array(
	// A term forty days out: inside the terms-ending window, and with a manager assigned below.
	'recSPN00000000001' => array( 'record_id' => 'recSPN00000000001', 'name' => 'TEST Sponsor', 'status' => 'Approved', 'dashboard_account' => true, 'tier' => 'supporter', 'tier_until' => $tier_in_40 ),
	// A recorded tier with no term: effective() still reads it, but days_left() is null, so this
	// one counts on the ladder and is left off the terms-ending card (no day to end on).
	'recSPN00000000002' => array( 'record_id' => 'recSPN00000000002', 'name' => 'Old Sponsor', 'status' => 'Approved', 'dashboard_account' => false, 'tier' => 'champion' ),
	// Paused, not Approved: left out of both the ladder count and the terms-ending card, even
	// though its own term is only ten days out.
	'recSPN00000000003' => array( 'record_id' => 'recSPN00000000003', 'name' => 'Paused Co', 'status' => 'Paused', 'dashboard_account' => true, 'tier' => 'backer', 'tier_until' => $tier_in_10 ),
);
// The Supporter's assigned program manager, named through WPCPM_Sponsors_Index::manager_label();
// the other two rows are left unassigned on purpose, since neither reaches the terms-ending card.
$GLOBALS['sponsor_managers'] = array(
	'recSPN00000000001' => 'Manager Three',
);
$semester_from = (int) strtotime( WPCPM_Cohort::range( WPCPM_Cohort::current() )['from'] . ' 00:00:00 UTC' );
$GLOBALS['offers'] = array(
	941 => array( 'id' => 941, 'title' => 'Pro license', 'sponsor' => 'recSPN00000000001', 'kind' => 'codes', 'state' => 'live', 'low' => 10, 'expires' => '' ),
	942 => array( 'id' => 942, 'title' => 'Course seat', 'sponsor' => 'recSPN00000000002', 'kind' => 'codes', 'state' => 'live', 'low' => 10, 'expires' => '' ),
	943 => array( 'id' => 943, 'title' => 'Everyone link', 'sponsor' => 'recSPN00000000001', 'kind' => 'shared', 'state' => 'live', 'low' => 10, 'expires' => '' ),
	944 => array( 'id' => 944, 'title' => 'Expired pool', 'sponsor' => 'recSPN00000000002', 'kind' => 'codes', 'state' => 'live', 'low' => 10, 'expires' => '2020-01-01' ),
	945 => array( 'id' => 945, 'title' => 'Ended pool', 'sponsor' => 'recSPN00000000001', 'kind' => 'codes', 'state' => 'ended', 'low' => 10, 'expires' => '' ),
	946 => array( 'id' => 946, 'title' => 'Empty draft', 'sponsor' => 'recSPN00000000003', 'kind' => 'codes', 'state' => 'draft', 'low' => 10, 'expires' => '' ),
);
$GLOBALS['codes'] = array(
	941 => array( 'available' => 3, 'claimed' => 7, 'void' => 0, 'total' => 10 ),
	942 => array( 'available' => 50, 'claimed' => 2, 'void' => 1, 'total' => 53 ),
	944 => array( 'available' => 1, 'claimed' => 0, 'void' => 0, 'total' => 1 ),
	945 => array( 'available' => 0, 'claimed' => 9, 'void' => 0, 'total' => 9 ),
);
$GLOBALS['claims'] = array(
	941 => array( array( 'u' => 31, 'i' => 0, 'at' => $semester_from + 86400, 'v' => 0 ), array( 'u' => 32, 'i' => 1, 'at' => $semester_from + 172800, 'v' => 0 ), array( 'u' => 33, 'i' => 2, 'at' => $semester_from + 259200, 'v' => $semester_from + 300000 ), array( 'u' => 34, 'i' => 3, 'at' => $semester_from - 86400, 'v' => 0 ) ),
	943 => array( array( 'u' => 35, 'i' => -1, 'at' => $semester_from + 3600, 'v' => 0 ) ),
	945 => array( array( 'u' => 36, 'i' => 0, 'at' => $semester_from + 7200, 'v' => 0 ) ),
);
// Shaped as WPCPM_Sponsor_Interests::line() writes it: "<date> by <who>: <choices-joined-by-;>;
// events: <events-joined-by-,>; note: <note>", each part left out when it has nothing to say.
$GLOBALS['audit'] = array(
	array( 'id' => 9101, 'kind' => 'sponsor_interest', 'sponsor' => 'recSPN00000000001', 'message' => '2026-09-01 by Member One: Sponsor a mentor or multiple mentors; events: WordCamp Europe; note: "Two mentors from Q1."', 'time' => time() - 5 * DAY_IN_SECONDS ),
	array( 'id' => 9102, 'kind' => 'sponsor_interest', 'sponsor' => 'recSPN00000000002', 'message' => '2026-07-20 by Old One: Sponsor a mentor or multiple mentors; Sponsor tools or services', 'time' => time() - 40 * DAY_IN_SECONDS ),
	array( 'id' => 9103, 'kind' => 'member_added', 'sponsor' => 'recSPN00000000001', 'message' => 'Someone joined', 'time' => time() - DAY_IN_SECONDS ),
);

// The Student Duplicate Finder's last scan: three duplicated students, one of them Ready, and the
// groups the finder's screen lists left out, because the dashboard never reads them.
$GLOBALS['dup_report'] = array( 'v' => 1, 'read' => 1757000000, 'counts' => array( 'addresses' => 3, 'ready' => 1, 'decide' => 2 ) );

// The sponsors' annual reports (1.115.0): a draft of TEST Sponsor's to finish, a term of Old
// Sponsor's that ended with no report, and a report of Old Sponsor's that Manager Three marked
// final ten days into this semester. A student's name planted in both reports' narratives and in
// the names their PDFs were uploaded under must never reach the card, the draft's rows or the
// final ones'.
$sr_final_at = $semester_from + 10 * DAY_IN_SECONDS + 3600;
seed_post( 1301, 'wpcpm_sponsor_report', 'TEST Sponsor, September 2025 to August 2026', array( '_wpcpm_sreport_sponsor' => 'recSPN00000000001', '_wpcpm_sreport_until' => '2026-08-31', '_wpcpm_sreport_state' => 'draft', '_wpcpm_sreport_sections' => array( 'ahead' => 'Student Planted starts again in October.' ), '_wpcpm_sreport_file_name' => 'Student Planted draft transcript.pdf' ) );
seed_post( 1302, 'wpcpm_sponsor_report', 'Old Sponsor, January 2025 to December 2025', array( '_wpcpm_sreport_sponsor' => 'recSPN00000000002', '_wpcpm_sreport_until' => '2025-12-31', '_wpcpm_sreport_state' => 'final', '_wpcpm_sreport_final' => array( 'at' => $sr_final_at, 'by' => 3 ), '_wpcpm_sreport_sections' => array( 'ahead' => 'Student Planted graduated in June.' ), '_wpcpm_sreport_file_name' => 'Student Planted transcript.pdf' ) );
$GLOBALS['sreports'] = array(
	'queue' => array( 1301 ),
	'due'   => array( array( 'sponsor' => 'recSPN00000000002', 'name' => 'Old Sponsor', 'until' => '2026-09-15', 'tier' => 'champion' ) ),
	'final' => array( 1302 ),
);

// Four mentor applications waiting in the Mentors screen's queue.
$GLOBALS['mentor_waiting'] = 4;

/* ---- collect() and counts() ---------------------------------------------- */

echo "=== The data is read once, through the owners ===\n";

$data = WPCPM_Administrators_Cards::collect();
ck( 'open applications are the three open states, through applications(), bounded to what is drawn', in_array( array( 'applications', array( 'new', 'held', 'info' ), 50 ), $GLOBALS['asked'], true ), true );
ck( 'and the closed list is rejected and spam, bounded the same way', in_array( array( 'applications', array( 'rejected', 'spam' ), 50 ), $GLOBALS['asked'], true ), true );
ck( 'while both totals are asked for as IDs, which cost no meta', array( in_array( array( 'application_ids', array( 'new', 'held', 'info' ) ), $GLOBALS['asked'], true ), in_array( array( 'application_ids', array( 'rejected', 'spam' ) ), $GLOBALS['asked'], true ) ), array( true, true ) );
ck( 'two open applications, one closed', array( count( $data['applications']['open'] ), count( $data['applications']['closed'] ) ), array( 2, 1 ) );
ck( 'two agreements awaiting review, one of them overdue', array( count( $data['agreements']['awaiting'] ), $data['agreements']['overdue'] ), array( 2, 1 ) );
ck( 'the overdue one is the older', $data['agreements']['awaiting'][0]['overdue'], true );
ck( 'returned and revoked come with their note', array( $data['agreements']['returned'][0]['note'], $data['agreements']['revoked'][0]['note'] ), array( 'Page two is missing.', 'Withdrawn by the rector.' ) );
ck( 'the queue, the due list and the approved list are the report class\'s', array( count( $data['reports']['queue'] ), count( $data['reports']['due'] ), count( $data['reports']['approved'] ) ), array( 1, 1, 1 ) );
ck( 'due is asked for today', in_array( array( 'due', gmdate( 'Y-m-d' ) ), $GLOBALS['asked'], true ), true );
ck( 'and approved since the start of this half-year', in_array( array( 'approved_since', (int) ( new DateTimeImmutable( WPCPM_Cohort::range( WPCPM_Cohort::current() )['from'] . ' 00:00:00', wp_timezone() ) )->format( 'U' ) ), $GLOBALS['asked'], true ), true );
ck( 'two open requests, one overdue, one closed', array( count( $data['requests']['open'] ), $data['requests']['overdue'], count( $data['requests']['closed'] ) ), array( 2, 1, 1 ) );
ck( 'one locked account', count( $data['locked'] ), 1 );

$counts = WPCPM_Administrators_Cards::counts( $data );
ck( 'the tiles in the order of the cards they link to: every queue waiting for a decision, the mentor applications, which have no card here, between the institutions\' tiles and the sponsors\', beside the other two kinds of application, then offers running low, the duplicated students, and the syncs that need a look and the locked accounts, the cards to watch that have a tile; thirteen while Sponsor tiers is off', array_keys( $counts ), array( 'applications', 'agreements', 'reports', 'requests', 'mentor_applications', 'sponsor_applications', 'sponsor_posts', 'sponsor_agreements', 'sponsor_reports', 'offers_low', 'duplicates', 'syncs', 'locked' ) );
// array_map() keeps the input array's keys, and counts() is keyed by tile name (the
// previous check pins that order), so the expectation is keyed the same way rather than
// the plain list the brief first wrote, which could never === an array with string keys.
// The mentor applications' tile has no card on this page, so it names where it opens instead.
ck( 'each tile is a number and a card, but the mentor applications\', which is a number and the address of their queue', array_map( static function ( $t ) { return $t['n'] . ':' . ( isset( $t['card'] ) ? $t['card'] : $t['url'] ); }, $counts ), array( 'applications' => '2:applications', 'agreements' => '2:agreements', 'reports' => '1:reports', 'requests' => '2:requests', 'mentor_applications' => '4:https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue', 'sponsor_applications' => '1:sponsor-applications', 'sponsor_posts' => '1:sponsor-posts', 'sponsor_agreements' => '1:sponsor-agreements', 'sponsor_reports' => '2:sponsor-reports', 'offers_low' => '1:offers-low', 'duplicates' => '3:duplicates', 'syncs' => '3:health', 'locked' => '1:health' ) );
ck( 'the mentor applications tile reads what waits through the record class, in the words of the design, and carries no card', array( $data['mentor_applications'], $counts['mentor_applications'] ), array( 4, array( 'label' => 'Mentor applications waiting', 'n' => 4, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue' ) ) );
// Two cards count a part of their own queue, and the Semester reports card two counts that add
// up to its number: each is one tile, whose number is its first count and whose label names what
// it counts and says the rest, so the tile reads as one line, "2 Requests from institutions open, 1 overdue".
ck( 'the folded tiles name what they count: the Collaboration Agreements to review and how many of them are overdue, the semester reports to review and the semesters due for drafting, the requests from institutions open and how many of them are overdue', array_map( static function ( $t ) { return $t['n'] . ' / ' . $t['label']; }, array_intersect_key( $counts, array_flip( array( 'agreements', 'reports', 'requests' ) ) ) ), array( 'agreements' => '2 / Collaboration Agreements to review, 1 overdue', 'reports' => '1 / Semester reports to review, 1 due for drafting', 'requests' => '2 / Requests from institutions open, 1 overdue' ) );
// Every count different, so a form keyed to the wrong one of a tile's two counts shows.
$varied                   = array_merge(
	$data,
	array(
		'agreements' => array_merge( $data['agreements'], array( 'awaiting' => array_fill( 0, 3, array() ), 'overdue' => 2 ) ),
		'reports'    => array_merge( $data['reports'], array( 'queue' => array_fill( 0, 4, array() ), 'due' => array_fill( 0, 5, array() ) ) ),
		'requests'   => array_merge( $data['requests'], array( 'open' => array_fill( 0, 7, array() ), 'overdue' => 6 ) ),
	)
);
$GLOBALS['plurals_asked'] = array();
$varied_counts            = WPCPM_Administrators_Cards::counts( $varied );
$varied_plurals           = $GLOBALS['plurals_asked'];
unset( $GLOBALS['plurals_asked'] );
ck( 'and each of a folded tile\'s two counts is said through a plural form of its own, keyed to that count, the words after the tile\'s number and then the second count\'s; the two tiles that say "overdue" each ask for it in a context of its own, since a Collaboration Agreement and a mentor request may take different words for it; and the syncs tile\'s words are keyed to its number, which is read first, through the Syncs and health card\'s own row, its phrase and all', array(
	array_map( static function ( $t ) { return $t['n'] . ' / ' . $t['label']; }, array_intersect_key( $varied_counts, array_flip( array( 'agreements', 'reports', 'requests' ) ) ) ),
	$varied_plurals,
), array(
	array( 'agreements' => '3 / Collaboration Agreements to review, 2 overdue', 'reports' => '4 / Semester reports to review, 5 due for drafting', 'requests' => '7 / Requests from institutions open, 6 overdue' ),
	array(
		array( '%s sync needs a look', '%s syncs need a look', 3 ),
		array( 'Collaboration Agreements to review, %s', 'Collaboration Agreements to review, %s', 3 ),
		array( '%s overdue', '%s overdue', 2, 'of the Collaboration Agreements to review' ),
		array( 'Semester reports to review, %s', 'Semester reports to review, %s', 4 ),
		array( '%s due for drafting', '%s due for drafting', 5 ),
		array( 'Requests from institutions open, %s', 'Requests from institutions open, %s', 7 ),
		array( '%s overdue', '%s overdue', 6, 'of the open requests from institutions' ),
		array( 'Sync needs a look', 'Syncs need a look', 3 ),
	),
) );
// Every sync of the fixture run a moment ago and leaving no error: none needs a look.
$fresh_syncs = array_map( static function ( $sync ) { return array_merge( $sync, array( 'last' => time(), 'progress' => array_merge( $sync['progress'], array( 'error' => '' ) ) ) ); }, $data['health']['syncs'] );
// Each counted part of the fixture at zero in turn, as a quiet day reads it. A sponsor queue's tile
// counts how many wait as `collect()` read them apart from the rows (`sponsor_totals`), so its part
// holds that count at zero beside the rows its card lists.
$quiet_totals = static function ( $queue ) use ( $data ) {
	return array_merge( $data['sponsor_totals'], array( $queue => 0 ) );
};
$quiet_parts  = array(
	'applications'         => array( 'applications' => array_merge( $data['applications'], array( 'open' => array(), 'open_total' => 0 ) ) ),
	'agreements'           => array( 'agreements' => array_merge( $data['agreements'], array( 'awaiting' => array(), 'overdue' => 0 ) ) ),
	'reports'              => array( 'reports' => array_merge( $data['reports'], array( 'queue' => array(), 'due' => array() ) ) ),
	'requests'             => array( 'requests' => array_merge( $data['requests'], array( 'open' => array(), 'overdue' => 0 ) ) ),
	'mentor_applications'  => array( 'mentor_applications' => 0 ),
	'sponsor_applications' => array( 'sponsor_applications' => array(), 'sponsor_totals' => $quiet_totals( 'applications' ) ),
	'sponsor_posts'        => array( 'sponsor_posts' => array(), 'sponsor_totals' => $quiet_totals( 'posts' ) ),
	'sponsor_agreements'   => array( 'sponsor_agreements' => array_merge( $data['sponsor_agreements'], array( 'review' => array() ) ), 'sponsor_totals' => $quiet_totals( 'agreements' ) ),
	'sponsor_reports'      => array( 'sponsor_reports' => array_merge( $data['sponsor_reports'], array( 'queue' => array(), 'due' => array() ) ) ),
	'offers_low'           => array( 'offers_low' => array() ),
	'duplicates'           => array( 'duplicates' => array_merge( $data['duplicates'], array( 'addresses' => 0, 'ready' => 0 ) ) ),
	'syncs'                => array( 'health' => array_merge( $data['health'], array( 'syncs' => $fresh_syncs ) ) ),
	'locked'               => array( 'locked' => array() ),
);
$left_when_quiet = array();
$quiet_data      = $data;
foreach ( $quiet_parts as $tile => $part ) {
	$left_when_quiet[ $tile ] = array_keys( WPCPM_Administrators_Cards::counts( array_merge( $data, $part ) ) );
	$quiet_data               = array_merge( $quiet_data, $part );
}
// The three sponsor queues' counts are one array, which each part above gives with its own queue
// alone at zero, so the quiet day takes it with all three at zero.
$quiet_data['sponsor_totals'] = array_fill_keys( array_keys( $data['sponsor_totals'] ), 0 );
ck( 'a count at zero draws no tile, where it drew a muted zero: each tile in turn is left out, and every other tile stays where it was', $left_when_quiet, array_map( static function ( $tile ) use ( $counts ) { return array_values( array_diff( array_keys( $counts ), array( $tile ) ) ); }, array_combine( array_keys( $quiet_parts ), array_keys( $quiet_parts ) ) ) );
ck( 'a folded tile is drawn while either of its counts is above zero: the semester reports\' tile leads with the reports to review while there is one, a semester due for drafting or none, and with the semesters due for drafting when there is none to review; the two parts counted within a queue lead with the queue\'s count, zero included', array(
	WPCPM_Administrators_Cards::counts( array_merge( $data, array( 'reports' => array_merge( $data['reports'], array( 'queue' => array() ) ) ) ) )['reports'] ?? null,
	WPCPM_Administrators_Cards::counts( array_merge( $data, array( 'reports' => array_merge( $data['reports'], array( 'due' => array() ) ) ) ) )['reports'] ?? null,
	WPCPM_Administrators_Cards::counts( array_merge( $data, array( 'agreements' => array_merge( $data['agreements'], array( 'awaiting' => array() ) ) ) ) )['agreements'] ?? null,
	WPCPM_Administrators_Cards::counts( array_merge( $data, array( 'requests' => array_merge( $data['requests'], array( 'open' => array() ) ) ) ) )['requests'] ?? null,
), array(
	array( 'label' => 'Semester reports due for drafting', 'n' => 1, 'card' => 'reports' ),
	array( 'label' => 'Semester reports to review, 0 due for drafting', 'n' => 1, 'card' => 'reports' ),
	array( 'label' => 'Collaboration Agreements to review, 1 overdue', 'n' => 0, 'card' => 'agreements' ),
	array( 'label' => 'Requests from institutions open, 1 overdue', 'n' => 0, 'card' => 'requests' ),
) );
$quiet_counts = WPCPM_Administrators_Cards::counts( $quiet_data );
// The old attention strip, which drew these counts, left with the old branches (Task 18b); the page's head and menu
// read them.
ck( 'with nothing waiting and nothing to watch there is no tile', $quiet_counts, array() );
// The quiet day with one sync needing a look, each way the Syncs and health card's row counts one:
// while Automatic sync is on, a sync that finished no run in the last six hours, or one whose last run
// left an error; while it is off, one whose last run left an error. Each draws the syncs tile, its
// number the card's own badge, and the strip says nothing is waiting only when the card's row says no
// sync needs a look: while Automatic sync is off, a run older than six hours is the program's choice,
// and the day stays quiet.
$quiet_health = $quiet_data['health'];
$quiet_sync   = static function ( $key, array $change, $auto = true ) use ( $quiet_health ) {
	$health                  = $quiet_health;
	$health['syncs'][ $key ] = array_merge( $health['syncs'][ $key ], $change );
	$health['auto_sync']     = $auto;
	return $health;
};
$looked = array();
foreach ( array(
	'on, a sync seven hours old'  => $quiet_sync( 'students', array( 'last' => time() - 7 * HOUR_IN_SECONDS ) ),
	'on, a sync in error'         => $quiet_sync( 'institutions', array( 'progress' => array_merge( $quiet_health['syncs']['institutions']['progress'], array( 'error' => 'HTTP 500' ) ) ) ),
	'off, a sync in error'        => $quiet_sync( 'mentors', array( 'progress' => array_merge( $quiet_health['syncs']['mentors']['progress'], array( 'error' => 'HTTP 429 from Airtable' ) ) ), false ),
	'off, a sync seven hours old' => $quiet_sync( 'students', array( 'last' => time() - 7 * HOUR_IN_SECONDS ), false ),
) as $day => $health ) {
	$day_counts     = WPCPM_Administrators_Cards::counts( array_merge( $quiet_data, array( 'health' => $health ) ) );
	$day_card       = capture( static function () use ( $health ) { WPCPM_Administrators_Cards::render_health( $health, array() ); } );
	$looked[ $day ] = array(
		'tiles' => array_map( static function ( $tile ) { return $tile['n'] . ' ' . $tile['label'] . ' > ' . $tile['card']; }, $day_counts ),
		'badge' => card_badge( $day_card ),
	);
}
ck( 'the counts never say nothing is waiting while the Syncs and health card counts a sync that needs a look: each such day gives the syncs tile, its number the card\'s badge, and a day the card counts none gives no tile', $looked, array(
	'on, a sync seven hours old'  => array( 'tiles' => array( 'syncs' => '1 Sync needs a look > health' ), 'badge' => 1 ),
	'on, a sync in error'         => array( 'tiles' => array( 'syncs' => '1 Sync needs a look > health' ), 'badge' => 1 ),
	'off, a sync in error'        => array( 'tiles' => array( 'syncs' => '1 Sync needs a look > health' ), 'badge' => 1 ),
	'off, a sync seven hours old' => array( 'tiles' => array(), 'badge' => 0 ),
) );
ck( 'the syncs tile says in its words what it counts, singular or plural as its number asks, and leads to Syncs and health, right before the locked accounts\' tile', array(
	isset( $counts['syncs'] ) ? $counts['syncs'] : null,
	array_slice( array_keys( $counts ), -2 ),
), array(
	array( 'label' => 'Syncs need a look', 'n' => 3, 'card' => 'health' ),
	array( 'syncs', 'locked' ),
) );
// With none to review, the semester reports' tile leads with the semesters due for drafting, its
// words keyed to that count; with neither, it is not drawn.
$GLOBALS['plurals_asked'] = array();
$due_led                  = WPCPM_Administrators_Cards::counts( array_merge( $quiet_data, array( 'reports' => array_merge( $data['reports'], array( 'queue' => array(), 'due' => array_fill( 0, 2, array() ) ) ) ) ) );
// The forms the semester reports' tile asked for, the other tiles' left out: none of the reports to
// review, since there is none, and one keyed to what is due.
$due_led_plurals = array_values( array_filter( $GLOBALS['plurals_asked'], static function ( $asked ) { return 0 === strpos( $asked[0], 'Semester reports' ) || false !== strpos( $asked[0], 'due for drafting' ); } ) );
unset( $GLOBALS['plurals_asked'] );
ck( 'with none to review and two semesters due for drafting, the semester reports\' tile leads with the two, in a plural form keyed to them; with neither, there is no tile', array(
	$due_led,
	$due_led_plurals,
	array_key_exists( 'reports', WPCPM_Administrators_Cards::counts( array_merge( $data, array( 'reports' => array_merge( $data['reports'], array( 'queue' => array(), 'due' => array() ) ) ) ) ) ),
), array(
	array( 'reports' => array( 'label' => 'Semester reports due for drafting', 'n' => 2, 'card' => 'reports' ) ),
	array( array( 'Semester reports due for drafting', 'Semester reports due for drafting', 2 ) ),
	false,
) );

echo "\n=== The sponsors' figures, the pools running low and the new interests (S6) ===\n";
ck( 'thirteen tiles, the last four the cards to watch that have one: offers running low, the duplicated students, and the syncs that need a look and the locked accounts, the two of Syncs and health, in their cards\' order', array( count( $counts ), array_slice( array_keys( $counts ), -4 ), $counts['offers_low'], $counts['locked'] ), array( 13, array( 'offers_low', 'duplicates', 'syncs', 'locked' ), array( 'label' => 'Offers running low', 'n' => 1, 'card' => 'offers-low' ), array( 'label' => 'Locked accounts', 'n' => 1, 'card' => 'health' ) ) );
ck( 'one pool runs low: live, of kind codes, under its own threshold; the expired, ended, shared and well-stocked ones are not it', array_map( static function ( $r ) { return $r['id'] . ':' . $r['available'] . '/' . $r['low'] . ':' . $r['sponsor_name']; }, $data['offers_low'] ), array( '941:3/10:TEST Sponsor' ) );
ck( 'and it links to the sponsor\'s Offers and codes card through the switcher', $data['offers_low'][0]['url'], 'https://site.example/sponsor-dashboard/?wpcpm_view=offers&wpcpm_sponsor_view=recSPN00000000001#wpcpm-sponsor-offers' );
ck( 'one interest in the last thirty days, of the audit kind sponsor_interest only, with the sponsor\'s name and the line as written', array( count( $data['interests'] ), $data['interests'][0]['sponsor_name'], $data['interests'][0]['message'], $data['interests'][0]['url'] ), array( 1, 'TEST Sponsor', '2026-09-01 by Member One: Sponsor a mentor or multiple mentors; events: WordCamp Europe; note: "Two mentors from Q1."', 'https://site.example/sponsor-dashboard/?wpcpm_view=sponsorship&wpcpm_sponsor_view=recSPN00000000001#wpcpm-sponsor-sponsorship' ) );
ck( 'the strip: two Approved sponsors, one with an account, three offers live today, four claims this semester on every offer, the ended one included (a voided one and one from last semester left out)', array_intersect_key( $data['sponsors'], array_flip( array( 'approved', 'with_accounts', 'live_offers', 'claims_semester' ) ) ), array( 'approved' => 2, 'with_accounts' => 1, 'live_offers' => 3, 'claims_semester' => 4 ) );
ck( 'and since names the first day of the current cohort', $data['sponsors']['since'], WPCPM_Cohort::range( WPCPM_Cohort::current() )['from'] );
$GLOBALS['sponsor_page_url'] = '';
ck( 'and with no Sponsor Dashboard page there is no link at all, not a query on the page a manager is on', WPCPM_Administrators_Cards::offers_low()[0]['url'], '' );
$low_no_page = capture( static function () { WPCPM_Administrators_Cards::render_offers_low( WPCPM_Administrators_Cards::offers_low() ); } );
$int_no_page = capture( static function () { WPCPM_Administrators_Cards::render_interests( WPCPM_Administrators_Cards::interests() ); } );
// The renderers, not just the data: with no page to link to, neither prints an anchor to the
// current page, only the title or the name in plain text.
ck( 'and the renderers print no anchor when there is no page to link to, just the title and the name', array( has( $low_no_page, 'href=""' ), has( $low_no_page, 'Pro license' ), has( $int_no_page, 'href=""' ), has( $int_no_page, 'TEST Sponsor' ) ), array( false, true, false, true ) );
unset( $GLOBALS['sponsor_page_url'] );
// 1.111.0: the sponsor record lives on the site, so there is no sponsors sync to list.
ck( 'the syncs card lists the three syncs, students, mentors and institutions, and no sponsors sync', array_keys( $data['health']['syncs'] ), array( 'students', 'mentors', 'institutions' ) );
$low = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_offers_low( $data['offers_low'] ); } );
$empty_low = capture( static function () { WPCPM_Administrators_Cards::render_offers_low( array() ); } );
ck( 'and an empty one says so, folded', array( has( $empty_low, 'No offer is running low.' ), has( $empty_low, '<details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure">' ) ), array( true, true ) );
$int = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_interests( $data['interests'] ); } );
// Since 1.123.22 the Sponsor Dashboard has no Offer more card: a sponsor's name opens its page at the
// Sponsorship card, whose Talk to your program contact files every new line of this kind.
$empty_int = capture( static function () { WPCPM_Administrators_Cards::render_interests( array() ); } );
ck( 'and thirty quiet days say so', has( $empty_int, 'No sponsor said anything new in the last thirty days.' ), true );
$strip2 = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsors_strip( $data['sponsors'] ); } );
// The fourth tile's number comes from $data itself rather than a literal: the fixture's
// claims_semester is 4, not the 3 that would match live_offers just above it, and a
// hardcoded digit here would silently pin the wrong one (brief review).
// Nine tiles now, not four: the tier ladder's own five are drawn under the original four, in
// the same $strip2 (1.112.0), so the tile count this check pins grew with them.
ck( 'the Sponsors card is four tiles on the programs card\'s markup: a name, the number and a qualifier each, never the name twice', array( has( $strip2, 'id="wpcpm-sponsors"' ), substr_count( $strip2, '<li class="wpcpm-programs__tile">' ), preg_match( '#<span class="wpcpm-programs__name">Approved sponsors</span><span class="wpcpm-programs__n">2</span><span class="wpcpm-programs__l">in the program records</span>#', $strip2 ), preg_match( '#<span class="wpcpm-programs__name">With an account</span><span class="wpcpm-programs__n">1</span><span class="wpcpm-programs__l">of the Approved sponsors</span>#', $strip2 ), preg_match( '#<span class="wpcpm-programs__name">Live offers</span><span class="wpcpm-programs__n">3</span><span class="wpcpm-programs__l">shown on the site today</span>#', $strip2 ), preg_match( '#<span class="wpcpm-programs__name">Claims this semester</span><span class="wpcpm-programs__n">' . (int) $data['sponsors']['claims_semester'] . '</span><span class="wpcpm-programs__l">since ' . preg_quote( $data['sponsors']['since_display'], '#' ) . ', on every offer</span>#', $strip2 ) ), array( true, 9, 1, 1, 1, 1 ) );

echo "\n=== Sponsors by tier, and the terms ending (1.112.0) ===\n";

ck( 'the Sponsors card counts the Approved sponsors by tier in ladder order: no Friend, one Supporter, no Backer, one Champion, no Partner', $data['sponsors']['tiers'], array( 'mentor' => 0, 'supporter' => 1, 'backer' => 0, 'champion' => 1, 'partner' => 0 ) );
// Zeros included: Friend and Partner both read 0, and each still draws its own tile
// rather than being skipped, so the ladder reads whole even where nobody has climbed yet.
ck(
	'Sponsor terms ending lists the Supporter\'s term with its day, the days left, its manager and its Edit form on the Sponsors tab, and not the Paused sponsor\'s nor a term with no day',
	array(
		count( $data['tiers_ending'] ),
		$data['tiers_ending'][0]['record'],
		$data['tiers_ending'][0]['name'],
		$data['tiers_ending'][0]['tier'],
		$data['tiers_ending'][0]['until'],
		$data['tiers_ending'][0]['days'],
		$data['tiers_ending'][0]['manager'],
		$data['tiers_ending'][0]['url'],
	),
	array( 1, 'recSPN00000000001', 'TEST Sponsor', 'Supporter', $tier_in_40, 40, 'Manager Three', 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=sponsors&edit=recSPN00000000001' )
);
// A manager who is not one now stays on the row until another is chosen: the card names them as
// the Edit form does, since "Nobody yet" would say the sponsor never had one.
$GLOBALS['sponsor_managers']['recSPN00000000001'] = 'Former Manager (not an Administrator now)';
$GLOBALS['sponsor_managers']['recSPN00000000001'] = 'Manager Three';
$empty_tiers_ending = capture( static function () { WPCPM_Administrators_Cards::render_tiers_ending( array() ); } );
// esc_html() turns the apostrophe into its entity, as the stub above does with ENT_QUOTES.
ck( 'an empty list says so', has( $empty_tiers_ending, 'No sponsor&#039;s term ends in the next sixty days.' ), true );
// A sponsor's term is set on its Edit form, a view of the Sponsors tab, and the Sponsors screen has one
// builder of that address, which every link to the form calls: the row names it, and builds nothing
// of the address itself.
$tiers_ending_card = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_tiers_ending( $data['tiers_ending'] ); } );

echo "\n=== Sponsor reports: what the card reads, its tile and its three parts (1.115.0) ===\n";

/**
 * The Sponsor reports card for some data, or '' while the cards class does not draw one, so the
 * checks below fail as checks rather than the suite as a fatal before the card exists.
 *
 * @param array $sr `queue`, `due`, `final`.
 * @return string
 */
function sponsor_reports_card( array $sr ) {
	return method_exists( 'WPCPM_Administrators_Cards', 'render_sponsor_reports' ) ? capture( static function () use ( $sr ) { WPCPM_Administrators_Cards::render_sponsor_reports( $sr ); } ) : '';
}

// A report's editor is the Sponsors screen's Reports tab with the report named: the report class's
// one builder of that address, which the card's rows carry.
$sr_editor = 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=reports&wpcpm_sreport=';
$sr_data   = isset( $data['sponsor_reports'] ) ? $data['sponsor_reports'] : null;
ck( 'the card reads the report store: the drafts, oldest first, each with its report, its sponsor\'s name and key, its title, its last day and its editor on the Sponsors screen; the terms that ended with no report; and the reports marked final this semester, each with when and by whom', $sr_data, array(
	'queue' => array(
		array( 'post_id' => 1301, 'sponsor' => 'recSPN00000000001', 'name' => 'TEST Sponsor', 'title' => 'TEST Sponsor, September 2025 to August 2026', 'until' => '2026-08-31', 'final_at' => 0, 'final_by' => 0, 'url' => $sr_editor . '1301' ),
	),
	'due'   => array(
		array( 'sponsor' => 'recSPN00000000002', 'name' => 'Old Sponsor', 'until' => '2026-09-15' ),
	),
	'final' => array(
		array( 'post_id' => 1302, 'sponsor' => 'recSPN00000000002', 'name' => 'Old Sponsor', 'title' => 'Old Sponsor, January 2025 to December 2025', 'until' => '2025-12-31', 'final_at' => $sr_final_at, 'final_by' => 3, 'url' => $sr_editor . '1302' ),
	),
) );
// The first moment of a day's semester, as the Reports card and the Sponsors tile count from it:
// midnight on the site's clock on the half-year's first day, so the page's "this semester" is one
// day whichever card says it.
$sr_semester_of = static function ( $day ) {
	return (int) ( new DateTimeImmutable( WPCPM_Cohort::range( WPCPM_Cohort::key( $day ) )['from'] . ' 00:00:00', wp_timezone() ) )->format( 'U' );
};
// What the reader asked the store when handed a day: the terms due, then the final reports' window.
$sr_asks = static function ( $day ) {
	$GLOBALS['asked'] = array();
	if ( method_exists( 'WPCPM_Administrators_Cards', 'sponsor_reports' ) ) {
		WPCPM_Administrators_Cards::sponsor_reports( $day );
	}
	return array_values( array_filter( $GLOBALS['asked'], static function ( $ask ) { return in_array( $ask[0], array( 'sreport_due', 'final_since' ), true ); } ) );
};
// Three days in three half-years, so whatever the clock says when the suite runs, at least two
// of them lie outside the real semester: a window read off the real today fails here.
ck( 'one today for both lists: the terms are asked for the day the reader is given, and the final reports from the first moment of that day\'s semester on the site\'s clock', array(
	$sr_asks( '2026-10-02' ),
	$sr_asks( '2027-01-15' ),
	$sr_asks( '2027-07-15' ),
), array(
	array( array( 'sreport_due', '2026-10-02' ), array( 'final_since', $sr_semester_of( '2026-10-02' ) ) ),
	array( array( 'sreport_due', '2027-01-15' ), array( 'final_since', $sr_semester_of( '2027-01-15' ) ) ),
	array( array( 'sreport_due', '2027-07-15' ), array( 'final_since', $sr_semester_of( '2027-07-15' ) ) ),
) );
// The empty day and today's own ask the same question, since the store reads '' as today: either
// is the page reading today's, and only another day is not.
ck( 'and the page reads today\'s: collect() asks for the terms due by today, and the final reports of today\'s semester', ( function () use ( $sr_semester_of ) {
	$GLOBALS['asked'] = array();
	WPCPM_Administrators_Cards::collect();
	$due   = array();
	$since = array();
	foreach ( $GLOBALS['asked'] as $ask ) {
		if ( 'sreport_due' === $ask[0] ) {
			$due[] = $ask[1];
		} elseif ( 'final_since' === $ask[0] ) {
			$since[] = $ask[1];
		}
	}
	return array( 1 === count( $due ) && in_array( $due[0], array( '', WPCPM_Sponsor_Tiers::today() ), true ), $since );
} )(), array( true, array( $sr_semester_of( WPCPM_Sponsor_Tiers::today() ) ) ) );
ck( 'the strip\'s tile counts the drafts and the terms, the two parts that ask for a press, and leads to the card', isset( $counts['sponsor_reports'] ) ? $counts['sponsor_reports'] : null, array( 'label' => 'Sponsor reports to finish', 'n' => 2, 'card' => 'sponsor-reports' ) );

$sr = sponsor_reports_card( is_array( $sr_data ) ? $sr_data : array() );
ck( 'the card is open under its count, the same number as the tile', array(
	has( $sr, '<section class="wpcpm-administrator__card" id="wpcpm-sponsor-reports"><details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure" open>' ),
	has( $sr, '<h3 class="wpcpm-group__title"><span id="wpcpm-sponsor-reports-title">Sponsor reports</span> <span class="wpcpm-group__count">2</span></h3>' ),
), array( true, true ) );
// Draft now is the Sponsors screen's own form, drawn by the renderer beside the handler that
// reads it, for that sponsor and that day and bound for this card: a field the handler gains is
// in the card's form the day it is added, with nothing here to copy by hand.
// The fields read off the markup, against the handler's own names: a renamed constant on either
// side is caught here, not on the day a manager presses the button.
preg_match( '#<form [^>]*class="wpcpm-administrator__form"[^>]*>(.*?)</form>#s', $sr, $sr_form );
preg_match_all( '#<input type="hidden" name="([^"]+)" value="([^"]*)" />#', isset( $sr_form[1] ) ? $sr_form[1] : '', $sr_fields );
ck( 'Draft now posts the draft action with the sponsor, the term\'s last day and the nonce keyed to the sponsor, and comes back to this card', array_combine( $sr_fields[1], $sr_fields[2] ), array(
	'_wpnonce'                               => 'nonce-' . WPCPM_Sponsor_Report_Screen::ACTION_DRAFT . '_recSPN00000000002',
	'action'                                 => WPCPM_Sponsor_Report_Screen::ACTION_DRAFT,
	WPCPM_Sponsor_Report_Screen::FIELD_RECORD => 'recSPN00000000002',
	WPCPM_Sponsor_Report_Screen::FIELD_UNTIL  => '2026-09-15',
	WPCPM_Return::FIELD                      => WPCPM_Return::DASHBOARD,
	WPCPM_Return::ANCHOR_FIELD               => 'sponsor-reports',
) );
// The planted words, each a part of what a report holds and the card must never print: the name
// in both reports' narratives, and a word of the names their PDFs were uploaded under, which
// survives the cleaning an uploaded name is given.
$sr_planted = array( 'Student Planted', 'transcript' );
$sr_leaks   = static function ( $html ) use ( $sr_planted ) {
	return array_values( array_filter( $sr_planted, static function ( $needle ) use ( $html ) { return has( $html, $needle ); } ) );
};
ck( 'the three parts in that order, and no student named: the card prints each report\'s title, never its narratives or the name its PDF was uploaded under, a draft\'s or a final one\'s', array(
	before_in( $sr, 'Drafts to finish', 'Terms ended with no report' ) && before_in( $sr, 'Terms ended with no report', 'Final this semester' ),
	$sr_leaks( $sr ),
), array( true, array() ) );

$sr_none  = sponsor_reports_card( array( 'queue' => array(), 'due' => array(), 'final' => array() ) );
$sr_blank = sponsor_reports_card( array() );
// Draft now's words for a screen reader name the sponsor as the sponsors index names it, where the
// due list's name comes from too, so this sponsor is on the index while its card is drawn.
$GLOBALS['sponsor_rows']['recSPN00000000009'] = array( 'record_id' => 'recSPN00000000009', 'name' => '<b>Bold</b> & Co', 'status' => 'Approved' );
unset( $GLOBALS['sponsor_rows']['recSPN00000000009'] );

echo "\n=== Duplicated students: the strip's last tile and its card (1.102.0) ===\n";

ck( 'the tile reads the Student Duplicate Finder\'s last scan through the scan\'s own report', array( $data['duplicates'], $counts['duplicates'] ), array( array( 'addresses' => 3, 'ready' => 1, 'read' => 1757000000, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder' ), array( 'label' => 'Duplicated students', 'n' => 3, 'card' => 'duplicates' ) ) );
$dup = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_duplicates( $data['duplicates'] ); } );
$clean = capture( static function () { WPCPM_Administrators_Cards::render_duplicates( array( 'addresses' => 0, 'ready' => 0, 'read' => 1757000000, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder' ) ); } );
$never = capture( static function () { WPCPM_Administrators_Cards::render_duplicates( array() ); } );
ck( 'a clean scan says so, folded, and so does a finder that has not scanned yet', array( has( $clean, 'No duplicated students in Airtable, as of ' ), has( $clean, '<details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure">' ), has( $never, 'The Student Duplicate Finder has not scanned Airtable yet.' ) ), array( true, true, true ) );

/* ---- programs() ---------------------------------------------------------- */

echo "\n=== Programs running ===\n";

$programs = $data['programs'];
ck( 'the tracks strip counts students in progress per track', array( $programs['tracks']['150h']['in_progress'], $programs['tracks']['50h']['in_progress'], $programs['tracks']['dev']['in_progress'] ), array( 1, 1, 1 ) );
// **Every track key the class can answer needs a tile, or its students are counted into an array
// key nobody made and drawn nowhere** - and on PHP 8 the increment warns on every load. Asserted
// as a set relation against `WPCPM_Program` rather than as a list, so the next track added fails
// here instead of going missing from the strip in silence.
$answerable = array();
foreach ( array_keys( WPCPM_Program::labels() ) as $status ) {
	$answerable[] = WPCPM_Program::track( $status );
}
ck( 'every track the program map can answer has a tile on the strip',
    array_values( array_diff( array_filter( $answerable, 'strlen' ), array_keys( WPCPM_Administrators_Cards::tracks() ) ) ), array() );
ck( 'and the Designer Track is one of them, counted like the rest',
    array( in_array( 'design', array_keys( WPCPM_Administrators_Cards::tracks() ), true ), isset( $programs['tracks']['design']['in_progress'] ) ), array( true, true ) );
ck( 'signed up this semester per track, from the start date', array( $programs['tracks']['150h']['signed_up'], $programs['tracks']['50h']['signed_up'], $programs['tracks']['dev']['signed_up'] ), array( 1, 0, 1 ) );
ck( 'finished this semester is one number: a graduate no longer says their track', $programs['finished'], 1 );
// A Dropped out row, ending inside the same cohort as the one Graduate row above: finished
// counts graduates only, so it stays 1, and Dropped out is not an active status either, so
// in_progress (checked again below, still 3) does not gain it (final review, Important 4).
ck( 'and Dropped out inside the cohort is neither finished nor in progress', array( $programs['finished'], $programs['rows'][0]['in_progress'] ), array( 1, 3 ) );
ck( 'one row per institution with somebody in progress', array_column( $programs['rows'], 'record' ), array( $A ) );
ck( 'with the count, the breakdown by label, the waiting and the distinct mentors', array( $programs['rows'][0]['in_progress'], $programs['rows'][0]['by_status'], $programs['rows'][0]['waiting'], $programs['rows'][0]['mentors'] ), array( 3, array( 'WordPress Credits Program 150h' => 1, 'Developer Track' => 1, 'WordPress Credits Program 50h' => 1 ), 1, 1 ) );
ck( 'the earliest and latest end among those in progress', array( $programs['rows'][0]['earliest'], $programs['rows'][0]['latest'] ), array( '2026-11-30', '2026-12-15' ) );

// A mentor on the Students row whose report record the email join never found (WPCredits#222):
// the roster files her under Current, so this count must not call her waiting - and the
// Mentors column must not read 0 beside her either. The index carries no mentor ID, so a
// nameless assigned mentor counts as one mentor of her own.
$GLOBALS['rosters'][ $A ]['rows'][] = array( 'record_id' => 'recS0000000000008', 'status' => 'In Sensei', 'start' => $in_early, 'end' => '2026-12-10', 'reports' => array(), 'mentor_name' => '', 'has_mentor' => true );
$with_222 = WPCPM_Administrators_Cards::programs();
ck( 'a mentored student the join missed is in progress and not waiting', array( $with_222['rows'][0]['in_progress'], $with_222['rows'][0]['waiting'] ), array( 4, 1 ) );
ck( 'and counts as a mentor though the join never brought a name', $with_222['rows'][0]['mentors'], 2 );
array_pop( $GLOBALS['rosters'][ $A ]['rows'] );

// Programs running (Ruling E1): a test institution with three students in progress on a track and one
// Graduate who ended this semester. The same world with and without its roster: tracks, finished, quiet,
// the read time and the phrase are the same, and the world with it lists one more row, its own, marked.
$phrase_of = static function ( $html ) { return 1 === preg_match( '#(\d+ programs? running, \d+ institutions?)#', $html, $m ) ? $m[1] : null; };
$TP_REC        = 'recTESTINSTITUT01';
$progs_without = WPCPM_Administrators_Cards::programs();
$card_without  = capture( static function () use ( $progs_without ) { WPCPM_Administrators_Cards::render_programs( $progs_without ); } );

$GLOBALS['inst_rows'][ $TP_REC ] = array( 'record_id' => $TP_REC, 'name' => 'TEST - Example Institution', 'stage' => 'Confirmed', 'country' => '', 'country_name' => '' );
$GLOBALS['rosters'][ $TP_REC ]   = array( 'v' => 4, 'read' => 1756700000, 'rows' => array(
	array( 'record_id' => 'recS0000000000101', 'status' => 'In Sensei', 'start' => $in_early, 'end' => '2026-12-15', 'reports' => array( 'recR1' ), 'mentor_name' => 'Mentor One' ),
	array( 'record_id' => 'recS0000000000102', 'status' => 'Developer Track', 'start' => $in_early_dev, 'end' => '2026-11-30', 'reports' => array(), 'mentor_name' => '' ),
	array( 'record_id' => 'recS0000000000103', 'status' => 'In Sensei 50h', 'start' => $in_early, 'end' => '', 'reports' => array( 'recR2' ), 'mentor_name' => 'Mentor One' ),
	array( 'record_id' => 'recS0000000000104', 'status' => 'Graduate', 'start' => '2026-02-01', 'end' => $in_late, 'reports' => array( 'recR3' ), 'mentor_name' => 'Mentor Two' ),
) );
$progs_with = WPCPM_Administrators_Cards::programs();
$card_with  = capture( static function () use ( $progs_with ) { WPCPM_Administrators_Cards::render_programs( $progs_with ); } );

ck( 'Programs running: the tracks, the finished, the quiet and the read time are those of the world without the test institution', array( $progs_with['tracks'], $progs_with['finished'], $progs_with['quiet'], $progs_with['read'] ), array( $progs_without['tracks'], $progs_without['finished'], $progs_without['quiet'], $progs_without['read'] ) );
ck( 'and the phrase is the same, the test institution counted among neither the programs nor the institutions', array( $phrase_of( $card_with ), $phrase_of( $card_without ) ), array( '3 programs running, 1 institution', '3 programs running, 1 institution' ) );
ck( 'and the card still lists the test institution\'s row, which the world without it has not: one row more, its own, marked', array(
	count( $progs_with['rows'] ) - count( $progs_without['rows'] ),
	in_array( $TP_REC, array_column( $progs_with['rows'], 'record' ), true ),
	in_array( $TP_REC, array_column( $progs_without['rows'], 'record' ), true ),
	has( $card_with, 'TEST - Example Institution</a> <span class="wpcpm-badge wpcpm-badge--test"' ),
), array( 1, true, false, true ) );
ck( 'the row\'s own figures are its roster\'s: three in progress', array_column( array_filter( $progs_with['rows'], static function ( $row ) use ( $TP_REC ) { return $TP_REC === $row['record']; } ), 'in_progress' ), array( 3 ) );

// Nobody in progress at the test institution: it adds to no "quiet" either.
$GLOBALS['rosters'][ $TP_REC ]['rows'] = array( array( 'record_id' => 'recS0000000000104', 'status' => 'Graduate', 'start' => '2026-02-01', 'end' => $in_late, 'reports' => array(), 'mentor_name' => '' ) );
$progs_idle = WPCPM_Administrators_Cards::programs();

ck( 'a test institution with nobody in progress is not "quiet" either, and its graduate is not finished', array( $progs_idle['quiet'], $progs_idle['finished'], count( $progs_idle['rows'] ) ), array( $progs_without['quiet'], $progs_without['finished'], count( $progs_without['rows'] ) ) );

unset( $GLOBALS['rosters'][ $TP_REC ], $GLOBALS['inst_rows'][ $TP_REC ] );
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

// The old attention strip that drew `counts()` as tiles left with the old branches (Task 18b): nothing in `includes/`
// called it. What the counts hold is pinned above; the page's head and menu read them.

// Every card with nothing in it, drawn folded in this page's shape with its zero printed: the rule the
// page kept when its cards moved onto the dashboards' shell, a card open only while its count is above
// zero. The Events card, drawn only while Sponsor tiers is on, is pinned folded at zero with
// its own checks below.
$folded_at_zero = array();
foreach ( array(
	'applications'         => static function () { WPCPM_Administrators_Cards::render_applications( array() ); },
	'agreements'           => static function () { WPCPM_Administrators_Cards::render_agreements( array() ); },
	'reports'              => static function () { WPCPM_Administrators_Cards::render_reports( array() ); },
	'requests'             => static function () { WPCPM_Administrators_Cards::render_requests( array() ); },
	'sponsor-applications' => static function () { WPCPM_Administrators_Cards::render_sponsor_applications( array() ); },
	'sponsor-posts'        => static function () { WPCPM_Administrators_Cards::render_sponsor_posts( array() ); },
	'sponsor-agreements'   => static function () { WPCPM_Administrators_Cards::render_sponsor_agreements( array() ); },
	'offers-low'           => static function () { WPCPM_Administrators_Cards::render_offers_low( array() ); },
	'duplicates'           => static function () { WPCPM_Administrators_Cards::render_duplicates( array() ); },
	'interests'            => static function () { WPCPM_Administrators_Cards::render_interests( array() ); },
	'sponsors'             => static function () { WPCPM_Administrators_Cards::render_sponsors_strip( array() ); },
	'tiers-ending'         => static function () { WPCPM_Administrators_Cards::render_tiers_ending( array() ); },
	'sponsor-reports'      => static function () { WPCPM_Administrators_Cards::render_sponsor_reports( array() ); },
	'programs'             => static function () { WPCPM_Administrators_Cards::render_programs( array( 'tracks' => array(), 'finished' => 0, 'rows' => array(), 'quiet' => 0, 'read' => 0, 'semester' => '' ) ); },
	'health'               => static function () { WPCPM_Administrators_Cards::render_health( array(), array() ); },
) as $anchor => $draw ) {
	$empty_card                = capture( $draw );
	$folded_at_zero[ $anchor ] = has( $empty_card, '<section class="wpcpm-administrator__card" id="wpcpm-' . $anchor . '"><details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary">' ) && has( $empty_card, ' <span class="wpcpm-group__count">0</span></h3>' );
}
ck( 'every card with nothing in it is drawn folded, in this page\'s shape, its zero printed', $folded_at_zero, array_fill_keys( array_keys( $folded_at_zero ), true ) );

// The help link (M3): a card that waits for a decision carries exactly one small link to its job in the
// Administrator guide, after its disclosure (never in the summary or a form), to the job's own anchor.
// The guide is a published page here only for these checks; a reader who is not an Administrator, and a
// site with no guide, get none.
$guide_page              = new WP_Post();
$guide_page->ID          = 991;
$guide_page->post_name   = 'administrator-guide';
$guide_page->post_status = 'publish';
$guide_page->post_type   = 'page';
$GLOBALS['posts'][991]   = $guide_page;
WPCPM_Guide_Jobs::forget();
$job_of_card             = array(
	'applications'         => array( 'institution-application', 'Institution applications', static function () { WPCPM_Administrators_Cards::render_applications( array() ); } ),
	'agreements'           => array( 'institution-agreement', 'Collaboration Agreements', static function () { WPCPM_Administrators_Cards::render_agreements( array() ); } ),
	'reports'              => array( 'semester-report', 'Semester reports', static function () { WPCPM_Administrators_Cards::render_reports( array() ); } ),
	'requests'             => array( 'institution-requests', 'Requests from institutions', static function () { WPCPM_Administrators_Cards::render_requests( array() ); } ),
	'sponsor-applications' => array( 'sponsor-application', 'Sponsor applications', static function () { WPCPM_Administrators_Cards::render_sponsor_applications( array() ); } ),
	'sponsor-posts'        => array( 'sponsor-post', 'Sponsor posts to review', static function () { WPCPM_Administrators_Cards::render_sponsor_posts( array() ); } ),
	'sponsor-agreements'   => array( 'sponsor-agreement', 'Sponsor Collaboration Agreements', static function () { WPCPM_Administrators_Cards::render_sponsor_agreements( array() ); } ),
	'sponsor-reports'      => array( 'sponsor-report', 'Sponsor reports', static function () { WPCPM_Administrators_Cards::render_sponsor_reports( array() ); } ),
	'duplicates'           => array( 'duplicates', 'Duplicated students', static function () { WPCPM_Administrators_Cards::render_duplicates( array() ); } ),
	'health'               => array( 'syncs', 'Syncs and health', static function () { WPCPM_Administrators_Cards::render_health( array(), array() ); } ),
);
$helped = array();
foreach ( $job_of_card as $anchor => $card ) {
	$html  = capture( $card[2] );
	$want  = '<p class="wpcpm-job-link-row"><a class="wpcpm-job-link" href="' . WPCPM_Guide_Jobs::url( $card[0] ) . '">How to do this<span class="screen-reader-text">: ' . $card[1] . '</span></a></p>';
	$after = strpos( $html, '</details>' );
	// One link, after the disclosure, and none in the summary.
	$helped[ $anchor ] = 1 === substr_count( $html, 'class="wpcpm-job-link"' ) && has( $html, $want ) && false !== $after && strpos( $html, $want ) > $after && '' !== WPCPM_Guide_Jobs::url( $card[0] ) && false === strpos( (string) substr( $html, 0, (int) $after ), 'wpcpm-job-link' );
}
ck( 'each card that waits for a decision carries exactly one help link, after its disclosure, to its job', $helped, array_fill_keys( array_keys( $helped ), true ) );
ck( 'the link is not inside a form', 0, preg_match( '#<form[^>]*>(?:(?!</form>).)*wpcpm-job-link#s', capture( $job_of_card['reports'][2] ) ) );
$GLOBALS['manage'] = array();
ck( 'a reader who cannot manage the program gets no help link on any of them', array_sum( array_map( static function ( $card ) { return substr_count( capture( $card[2] ), 'wpcpm-job-link' ); }, $job_of_card ) ), 0 );
$GLOBALS['manage'] = array( 3 );
unset( $GLOBALS['posts'][991] );
WPCPM_Guide_Jobs::forget();
ck( 'a site with no published guide draws no help link', array_sum( array_map( static function ( $card ) { return substr_count( capture( $card[2] ), 'wpcpm-job-link' ); }, $job_of_card ) ), 0 );

$GLOBALS['users'][12] = new WP_User( 12, 'Beata Admin', 'beata.admin@example.test' );
$GLOBALS['manage']    = array( 3, 12 );
$apps = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_applications( $data['applications'] ); } );
$GLOBALS['manage']    = array( 3 );
ck( 'the applications card is open with its count', has( $apps, 'id="wpcpm-applications"' ) && has( $apps, 'wpcpm-group__disclosure" open' ) && has( $apps, '<span class="wpcpm-group__count">2</span>' ), true );
ck( 'each open application names itself, its reference, country and the Administrator it was given to', has( $apps, 'Uni Nueva' ) && has( $apps, 'APP-2026-0501' ) && has( $apps, 'Poland' ) && has( $apps, 'Assigned to Beata Admin' ), true );
ck( 'and never the Countries table\'s contact for the country', has( $apps, 'Ana Manager' ), false );
ck( 'an application filed before the release, with a snapshot and nobody assigned, names the snapshot\'s contact', has( $apps, 'Assigned to old.snapshot@example.test' ), true );
ck( 'the decisions are the module\'s, and come back here', substr_count( $apps, 'name="wpcpm_return" value="dashboard"' ), 4 );
// Reject, Reject as spam and Delete for good carry a confirm; a canceled dialog must not
// leave the guard thinking a submit is on its way (final review, Critical 1).
preg_match( '/<form[^>]*data-wpcpm-confirm="[^>]*>/', $apps, $confirm_form );
ck( 'a decision drawn with a confirm still carries the double-submit guard', isset( $confirm_form[0] ) && has( $confirm_form[0], 'data-wpcpm-once' ) && has( $confirm_form[0], 'data-wpcpm-busy=' ), true );

// Each application row, by the id its article carries.
$app_rows = array();
if ( preg_match_all( '#<article class="wpcpm-administrator__item wpcpm-application" id="wpcpm-application-(\d+)">(.*?)</article>#s', $apps, $app_found ) ) {
	$app_rows = array_combine( $app_found[1], $app_found[2] );
}
$held_note = '<p class="wpcpm-administrator__note">Held by the anti-spam checks. Open it on the Institutions screen to read which.</p>';

// "Read the application" folds in the Institutions screen's own answers table, drawn by its real
// method (read in above): every question in the form's order, the five kept on the site since
// 1.123.29 with no column name under them, as bin/test-institutions-screen.php pins for the queue.
$headings  = array();
$site_at   = array();
foreach ( array_keys( WPCPM_Institution_Application::fields() ) as $index => $column ) {
	$spec       = WPCPM_Institution_Application::fields()[ $column ];
	$headings[] = empty( $spec['site'] ) ? esc_html( $spec['label'] ) . '<br /><code class="wpcpm-inst-record">' . esc_html( $column ) . '</code>' : esc_html( $spec['label'] );
	if ( ! empty( $spec['site'] ) ) {
		$site_at[] = $index;
	}
}

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
// The closed fold holds decided applications, so its line says they are kept and leave the list
// as the ones above them are deleted or put back in the queue; the open list's line says they
// are waiting and are listed as these are decided.
ck( 'and the closed fold says how many more are kept, and that they are listed as these are deleted or put back in the queue', array(
	has( $capped_html, '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">2 more are kept; they are listed here as these are deleted or put back in the queue.</p>' ),
	strpos( $capped_html, '2 more are kept' ) > strpos( $capped_html, '<details class="wpcpm-administrator__closed">' ),
	has( $capped_html, 'waiting; ' ),
), array( true, true, false ) );
$one_more_html = capture( static function () use ( $capped ) { WPCPM_Administrators_Cards::render_applications( array_merge( $capped['applications'], array( 'closed_total' => count( $capped['applications']['closed'] ) + 1 ) ) ); } );
ck( 'one more is said in the singular', has( $one_more_html, '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">1 more is kept; it is listed here as these are deleted or put back in the queue.</p>' ), true );

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
ck( 'the institution is linked through the switcher', has( $agr_html, 'wpcpm_institution_view=' . $B ), true );
ck( 'the returned list shows the note', has( $agr_html, 'Page two is missing.' ), true );
ck( 'and the revoked one offers reinstate with its own nonce', has( $agr_html, 'value="wpcpm_agreement_reinstate"' ) && has( $agr_html, 'name="wpcpm_agreement_post" value="604"' ), true );
// A decision on an institution's Collaboration Agreement comes back to the card it was made on, open,
// as every decision this page posts does: the review block, whose Accept and Return are the Institutions
// screen's own forms, is asked for bound for this card, and the Reinstate form, drawn here alone,
// carries the same way back.
preg_match( '#<form [^>]*>((?:(?!</form>).)*?value="wpcpm_agreement_reinstate"(?:(?!</form>).)*)</form>#s', $agr_html, $reinstate_form );
preg_match_all( '#<input type="hidden" name="([^"]+)" value="([^"]*)"(?: /)?>#', isset( $reinstate_form[1] ) ? $reinstate_form[1] : '', $reinstate_fields );
ck( 'a decision on an institution\'s Collaboration Agreement comes back to its card: each review block is asked for bound for this card, and the Reinstate form posts the way back with its own fields', array(
	$GLOBALS['review_returns'],
	array_combine( $reinstate_fields[1], $reinstate_fields[2] ),
), array(
	array( 'dashboard', 'dashboard' ),
	array(
		'_wpnonce'             => 'nonce-wpcpm_agreement_reinstate_604',
		'_wp_http_referer'     => '/administrator-dashboard/',
		'action'               => 'wpcpm_agreement_reinstate',
		'wpcpm_agreement_post' => '604',
		'wpcpm_return'         => 'dashboard',
		'wpcpm_return_to'      => 'agreements',
	),
) );

$rep = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_reports( $data['reports'] ); } );
ck( 'a draft to review links to the editor as that institution', has( $rep, 'wpcpm_report=2026-H1' ) && has( $rep, 'wpcpm_institution_view=' . $A ), true );
ck( 'and says the site drafted it with one still in progress', has( $rep, 'by the site' ) && has( $rep, '>1<' ), true );
// The Drafted column's other answer, for a draft an Administrator pressed Draft now for: the card's
// private line, read as the card prints it (the queue above holds only a draft the site made).
$drafted = new ReflectionMethod( 'WPCPM_Administrators_Cards', 'drafted' );
if ( PHP_VERSION_ID < 80100 ) {
	$drafted->setAccessible( true );
}
ck( 'and a draft an Administrator made says so, in the Drafted column', array( substr( $drafted->invoke( null, 1756500000, 'manager' ), -21 ), substr( $drafted->invoke( null, 1756500000, 'auto' ), -11 ) ), array( ', by an Administrator', 'by the site' ) );
ck( 'a due cohort has a Draft now form', has( $rep, 'value="wpcpm_report_draft"' ) && has( $rep, 'name="cohort" value="2025-H2"' ), true );
// A refusal (the semester already has a report, the ceiling is spent) has to come back here
// rather than to wp-admin's default (final review, Important 2).
ck( 'and it carries the dashboard return', has( $rep, 'name="wpcpm_return" value="dashboard"' ) && has( $rep, 'name="wpcpm_return_to" value="reports"' ), true );
ck( 'an approved report names who approved it', has( $rep, 'Manager Three' ), true );

$req = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_requests( $data['requests'] ); } );
$sp = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsor_posts( $data['sponsor_posts'] ); } );
$empty_sp = capture( static function () { WPCPM_Administrators_Cards::render_sponsor_posts( array() ); } );
ck( 'an empty queue says so', has( $empty_sp, 'No sponsor post is waiting for review.' ), true );

// Sponsor offers, and changes to live ones, waiting for review (1.123.18): a card of their own right
// after the posts', counted and folded as the posts' card is, each item drawn by the review class
// bound for the dashboard, and a tile after the posts' tile.
$GLOBALS['offer_reviews'] = array( array( 'id' => 931, 'record' => 'recSPN00000000001', 'company' => 'TEST Sponsor', 'title' => 'A year of hosting', 'change' => 'new', 'by' => 'Member One', 'at' => time() - 600, 'fields' => array() ) );
$so_data   = WPCPM_Administrators_Cards::collect();
$so_counts = WPCPM_Administrators_Cards::counts( $so_data );
ck( 'what waits for review is read through the review class and counted as the posts are, and its tile follows the posts\' tile, in its card\'s words', array( array_column( $so_data['sponsor_offers'], 'id' ), $so_data['sponsor_totals']['offers'], $so_counts['sponsor_offers']['label'], $so_counts['sponsor_offers']['n'], $so_counts['sponsor_offers']['card'], array_search( 'sponsor_offers', array_keys( $so_counts ), true ) - array_search( 'sponsor_posts', array_keys( $so_counts ), true ) ), array( array( 931 ), 1, 'Sponsor offers to review', 1, 'sponsor-offers-review', 1 ) );
$so       = capture( static function () use ( $so_data ) { WPCPM_Administrators_Cards::render_sponsor_offers( $so_data['sponsor_offers'], $so_data['sponsor_totals']['offers'] ); } );
$empty_so = capture( static function () { WPCPM_Administrators_Cards::render_sponsor_offers( array() ); } );
ck( 'the sponsor offers card is open with its count, draws each item through the review class bound for the dashboard, and ends with the way to its card on the Sponsors screen\'s queue tab', array( has( $so, 'id="wpcpm-sponsor-offers-review"' ), has( $so, '<span id="wpcpm-sponsor-offers-review-title">Sponsor offers to review</span>' ), has( $so, '<span class="wpcpm-group__count">1</span>' ), has( $so, '<article class="wpcpm-administrator__item wpcpm-sponsor-offer" id="wpcpm-sponsor-offer-931" data-return="dashboard"></article>' ), has( $so, 'page=wpcpm-sponsors&amp;tab=queue#wpcpm-sponsor-offers-review' ) || has( $so, 'page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-offers-review' ) ), array( true, true, true, true, true ) );
ck( 'and with nothing waiting it says so, folded at zero', array( has( $empty_so, 'No sponsor offer is waiting for review.' ), has( $empty_so, '<span class="wpcpm-group__count">0</span>' ), has( $empty_so, ' open>' ) || has( $empty_so, ' open ' ) ), array( true, true, false ) );
ck( 'its decisions\' outcomes are the dashboard\'s to print', false !== strpos( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-administrators-dashboard.php' ), '$messages = array_merge( $messages, (array) WPCPM_Sponsor_Offer_Review::messages() );' ), true );
unset( $GLOBALS['offer_reviews'] );
ck( 'open requests draw the decisions, coming back here', substr_count( $req, 'value="wpcpm_resolve_request"' ) === 2 && substr_count( $req, 'name="wpcpm_return" value="dashboard"' ) === 2, true );
ck( 'the closed list says handled', has( $req, 'Handled' ), true );
ck( 'the card is called Requests from institutions, and the words Mentor requests are nowhere on it', array( has( $req, '>Requests from institutions<' ), has( $req, 'Mentor requests' ) ), array( true, false ) );

// A closed row is dated by when it was closed, and says so. The card exists so a manager can
// see what a colleague did this week (closed_requests()' own docblock), and a row handled
// yesterday used to read "Handled, 12 January 2026" - the day it was raised - with nothing in
// the sentence to say which date it was (deep check FADMN-3). A row closed before 1.99.0 has
// no closing stamp, and says the date it does have is the opening one.
$closed_two = array(
	'open'    => array(),
	'overdue' => 0,
	'closed'  => array(
		array_merge( $GLOBALS['facts'][803], array( 'id' => 804, 'closed_at' => time() - DAY_IN_SECONDS ) ),
		array_merge( $GLOBALS['facts'][803], array( 'closed_at' => 0 ) ),
	),
);
$req_dates  = capture( static function () use ( $closed_two ) { WPCPM_Administrators_Cards::render_requests( $closed_two ); } );
ck( 'a stamped row is dated by its closing and an unstamped one says the date is the opening', array( substr_count( $req_dates, 'closed on' ), substr_count( $req_dates, 'opened on' ) ), array( 1, 1 ) );

// A school's details request (Institutions release 2), drawn as the chosen mockup A: one decision for
// the whole request. The school's name linked as the Institutions screen links names, who asked and on
// which day, the waiting line the card's other requests use, a table of Detail, On record now and
// Asked for, a note box for the school that is required to decline, and the two buttons. "On record
// now" is the request's facts' (`WPCPM_Institution_Request::facts()` reads it from the index when it
// is drawn), so this draws whatever it is handed, escaped.
$details_at    = time() - 3 * DAY_IN_SECONDS;
$details_facts = array_merge( $GLOBALS['facts'][802], array(
	'id'           => 810,
	'kind'         => 'details',
	'kind_label'   => 'A change to its details',
	'institution'  => $A,
	'student'      => '',
	'student_name' => '',
	'actor_name'   => 'School Representative',
	'note'         => '',
	'at'           => $details_at,
	'overdue'      => false,
	'changes'      => array(
		array( 'key' => 'name', 'label' => 'Name', 'now' => 'Uniwersytet Alpha', 'asked' => 'Alpha University <b>of Poland</b>' ),
		array( 'key' => 'website', 'label' => 'Website', 'now' => 'not given', 'asked' => 'https://alpha.example.edu' ),
	),
) );
$GLOBALS['uid']    = 3;
$GLOBALS['manage'] = array( 3 );
$details_card      = capture( static function () use ( $details_facts ) { WPCPM_Administrators_Cards::render_requests( array( 'open' => array( $details_facts ), 'closed' => array(), 'overdue' => 0 ) ); } );
$details_item      = '';
preg_match( '#<article class="wpcpm-administrator__item wpcpm-request[^"]*">.*?</article>#s', $details_card, $details_found );
$details_item = isset( $details_found[0] ) ? $details_found[0] : '';
// The heading is a sentence with the school's linked name in it, so a translator can put the name where their
// language wants it: the template is one string with a placeholder, and not the school's name beside a
// fragment. A stand-in translation moves the name to the end, and the markup around it stays.
$cards_source           = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-cards.php' );
$GLOBALS['translations'] = array( '%s asks to correct its details' => 'Details to correct: %s' );
$GLOBALS['translations'] = array();
ck( 'the sentence is built by sprintf with a translators comment, and the name is the one escaped link', array(
	1 === preg_match( '#/\* translators: %s: [^*]*\*/\s*esc_html__\( \'%s asks to correct its details\'#', $cards_source ),
	false !== strpos( $cards_source, "esc_html__( 'asks to correct its details'" ),
), array( true, false ) );
ck( 'an account that has gone asked as a member of the institution', has( capture( static function () use ( $details_facts ) { WPCPM_Administrators_Cards::render_requests( array( 'open' => array( array_merge( $details_facts, array( 'actor_name' => '' ) ) ), 'closed' => array(), 'overdue' => 0 ) ); } ), 'Asked by a member of the institution on ' ), true );

$GLOBALS['flash'][ WPCPM_Typed_Text::KEPT_CHANNEL . 'request-close:810' ] = array( 'at' => time(), 'typed' => array( 'note' => "Too <short>\nand kept" ) );

$GLOBALS['uid'] = 9;
$GLOBALS['manage'] = array();
$bystander_card = capture( static function () use ( $details_facts ) { WPCPM_Administrators_Cards::render_requests( array( 'open' => array( $details_facts ), 'closed' => array(), 'overdue' => 0 ) ); } );
ck( 'an account that may not manage the program is drawn the table and no form', array( has( $bystander_card, '<table' ), has( $bystander_card, '<form' ), has( $bystander_card, 'Approve and update the records' ) ), array( true, false, false ) );
$GLOBALS['uid'] = 3;
$GLOBALS['manage'] = array( 3 );



// What the card says of a closed request, in its fold: an approval, a decline and a withdrawal in their
// own words, and a mentor request still Handled.
$closed_rows = array(
	array_merge( $details_facts, array( 'id' => 811, 'state' => 'done', 'closed_at' => time() - DAY_IN_SECONDS, 'changes' => array() ) ),
	array_merge( $details_facts, array( 'id' => 812, 'state' => 'declined', 'closed_at' => time() - 2 * DAY_IN_SECONDS, 'changes' => array() ) ),
	array_merge( $details_facts, array( 'id' => 813, 'state' => 'withdrawn', 'closed_at' => time() - 3 * DAY_IN_SECONDS, 'changes' => array() ) ),
	array_merge( $GLOBALS['facts'][803], array( 'id' => 814, 'closed_at' => time() - 4 * DAY_IN_SECONDS ) ),
);
$closed_card = capture( static function () use ( $closed_rows ) { WPCPM_Administrators_Cards::render_requests( array( 'open' => array(), 'closed' => $closed_rows, 'overdue' => 0 ) ); } );
preg_match_all( '#<li>(.*?)</li>#s', $closed_card, $closed_lis );

$request_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-request.php' );
$declared    = array();
foreach ( array( 'KIND_DETAILS', 'STATE_WITHDRAWN', 'ACTION_DETAILS_DECIDE', 'TYPED_NOTE', 'MAX_NOTE' ) as $const ) {
	$declared[ $const ] = preg_match( "/const {$const} = ('[a-z_-]+'|\d+);/", $request_src, $value ) ? $value[1] : null;
}
ck( 'the names the stand-in request class carries here are the real class\'s, which the card draws the form with', $declared, array(
	'KIND_DETAILS'          => "'" . WPCPM_Institution_Request::KIND_DETAILS . "'",
	'STATE_WITHDRAWN'       => "'" . WPCPM_Institution_Request::STATE_WITHDRAWN . "'",
	'ACTION_DETAILS_DECIDE' => "'" . WPCPM_Institution_Request::ACTION_DETAILS_DECIDE . "'",
	'TYPED_NOTE'            => "'" . WPCPM_Institution_Request::TYPED_NOTE . "'",
	'MAX_NOTE'              => (string) WPCPM_Institution_Request::MAX_NOTE,
) );

$prog = capture( static function () use ( $programs ) { WPCPM_Administrators_Cards::render_programs( $programs ); } );
// One per track, plus the finished tile, plus one for the wrapping
// <ul class="wpcpm-programs__tiles"> - which matches the needle as well as the tiles it
// contains, the same reason the strip count above is nine. Counted from the class rather than
// written down, so adding a track moves this number by itself.
ck( 'the programs card draws a tile per track and a finished tile', substr_count( $prog, 'wpcpm-programs__tile' ), count( array_keys( WPCPM_Administrators_Cards::tracks() ) ) + 2 );
ck( 'the institution row links through the switcher and carries its numbers', has( $prog, 'wpcpm_institution_view=' . $A ) && has( $prog, '2026-12-15' ) && has( $prog, 'Uniwersytet Alpha' ), true );
ck( 'the quiet institutions are one closing line', has( $prog, '1 more institution' ), true );
ck( 'and the read time is printed', has( $prog, 'Read from the program records' ), true );

// The Agreement and Report cells are read by an Administrator: the words the classes that own the
// states give them, never the stored state ("none", "on_file", "accepted", "draft") the row holds.
// Every agreement state and every report state the card can be handed, one row apiece.
$cell_programs         = $programs;
$cell_programs['rows'] = array();
foreach ( array( array( 'none', 'none' ), array( 'generated', 'draft' ), array( 'submitted', 'approved' ), array( 'returned', 'none' ), array( 'revoked', 'draft' ), array( 'accepted', 'approved' ), array( 'on_file', 'draft' ) ) as $cell_pair ) {
	$cell_programs['rows'][] = array_merge( $programs['rows'][0], array( 'agreement' => $cell_pair[0], 'report' => $cell_pair[1] ) );
}
// Read off the parts' cells (Task 18c: the old table's check left in Task 18b, and this one read nothing since).
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$cell_html = capture( static function () use ( $cell_programs ) { WPCPM_Administrators_Cards::render_programs( $cell_programs ); } );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
preg_match_all( '#data-label="Agreement">([^<]*)</td><td [^>]*data-label="Report">([^<]*)</td></tr>#', $cell_html, $cell_found, PREG_SET_ORDER );
$cell_words = array_map( static function ( $cells ) { return array( $cells[1], $cells[2] ); }, $cell_found );
ck( 'Programs running prints each agreement state and each report state as the owning class words it', $cell_words, array(
	array( 'Not started', 'Not drafted' ),
	array( 'Template generated', 'Draft' ),
	array( 'Awaiting review', 'Approved' ),
	array( 'Returned', 'Not drafted' ),
	array( 'Revoked', 'Draft' ),
	array( 'Accepted', 'Approved' ),
	array( 'On file', 'Draft' ),
) );
ck( 'and not one cell is a stored state', array_values( array_filter( array_merge( ...$cell_words ), static function ( $word ) { return in_array( $word, array( 'none', 'on file', 'on_file', 'accepted', 'draft', 'approved', 'generated', 'submitted', 'returned', 'revoked' ), true ); } ) ), array() );

/* ---- the Mentor engagement card (1.122.26) ------------------------------- */

echo "=== The Mentor engagement card ===\n";

$me_summary = array_merge( array_fill_keys( array( 'active', 'signed_in', 'seen_30', 'seen_7', 'never', 'acted', 'idle', 'with_students', 'with_students_hours', 'with_students_link', 'with_students_notes', 'with_students_seen_30', 'calls_held', 'calls_upcoming', 'calls_noted', 'sessions_held', 'sessions_upcoming', 'sessions_noted', 'staff' ), 0 ), array( 'active' => 9, 'seen_30' => 5, 'seen_7' => 2, 'acted' => 4, 'idle' => 3, 'never' => 2, 'with_students' => 6, 'with_students_hours' => 3, 'with_students_notes' => 2, 'calls_held' => 7, 'calls_noted' => 4, 'calls_upcoming' => 1, 'sessions_held' => 2, 'staff' => 1 ) );
$me = capture( static function () use ( $me_summary ) { WPCPM_Administrators_Cards::render_mentor_engagement( $me_summary ); } );
ck( 'the card is a section at its anchor, the canonical disclosure inside', array( has( $me, '<section class="wpcpm-administrator__card" id="wpcpm-mentor-engagement">' ), has( $me, 'wpcpm-group__disclosure' ) ), array( true, true ) );
// Six tiles, plus the wrapping <ul class="wpcpm-programs__tiles">, which matches the needle too.
ck( 'it draws six tiles in the programs card\'s markup', substr_count( $me, 'wpcpm-programs__tile' ), 6 + 1 );
ck( 'each tile its words and number', array( has( $me, 'Active mentors' ), has( $me, '>9<' ), has( $me, '2 in the last 7 days' ), has( $me, '4 with a note, 1 upcoming; 2 group sessions held' ) ), array( true, true, true, true ) );
ck( 'a tile with no line prints no empty line', substr_count( $me, 'wpcpm-programs__l' ), 5 );
ck( 'the Administrators who mentor, and the way to the tab', array( has( $me, 'counted apart: 1.' ), has( $me, 'admin.php?page=wpcpm-mentors&amp;tab=engagement' ) || has( $me, 'admin.php?page=wpcpm-mentors&tab=engagement' ) ), array( true, true ) );
ck( 'an empty summary draws nothing', capture( static function () { WPCPM_Administrators_Cards::render_mentor_engagement( array() ); } ), '' );
ck( 'collect() reads the summary, never the rows', array_key_exists( 'mentor_engagement', WPCPM_Administrators_Cards::collect() ) && ! array_key_exists( 'rows', WPCPM_Administrators_Cards::collect()['mentor_engagement'] ), true );
$order_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-dashboard.php' );
// This page draws its cards in three groups: the card is the Reference group's last, right after
// Programs running, whose tiles it wears, and Syncs and health is in the Watch group above it. The
// drawn page's groups are pinned with the other cards further down.
$reference_at = (int) strpos( $order_src, "'id'    => 'reference'" );
ck( 'the card comes right after Programs running in the Reference group, below Syncs and health in the Watch group, and is drawn once', array(
	$reference_at > 0 && $reference_at < strpos( $order_src, 'render_programs(' ),
	strpos( $order_src, 'render_programs(' ) < strpos( $order_src, 'render_mentor_engagement(' ),
	strpos( $order_src, 'render_health(' ) < $reference_at,
	substr_count( $order_src, 'render_mentor_engagement(' ),
), array( true, true, true, 1 ) );
// In the dashboards' shell, as every card on this page is: the count the card hands its shell is
// the active mentors, which the shell draws as the badge, and its home in wp-admin is the Mentors
// screen's Engagement tab, which its row action links while it is folded and the line its body
// ends with links in the same words while it is open, as every card with a home in wp-admin does
// here; the label names the tab, so no hidden words follow it.
$to_engagement = tab_url( 'wpcpm-mentors', 'engagement' );
ck( 'the card wears the shell: its badge the active mentors, its way to the Mentors screen\'s Engagement tab as its row action and its body\'s last line, in the same words, with no hidden words', array(
	card_badge( $me ),
	ends_with( $me, card_foot( $to_engagement, 'Open the Mentors screen&#039;s Engagement tab' ) ),
	hidden_words( static function () use ( $me_summary ) { WPCPM_Administrators_Cards::render_mentor_engagement( $me_summary ); } ),
	has( $me, 'wpcpm-administrator__read' ),
), array( 9, true, array( null, null ), false ) );

/* ---- the Students gone quiet card (student automation, first line) ------- */

echo "=== The Students gone quiet card ===\n";

$qT = 1800000000;
$qD = DAY_IN_SECONDS;
foreach ( array( 3 => 'Manager Three', 71 => 'Adam Manager', 72 => 'Zoe Manager', 74 => 'Mia Mentor' ) as $qid => $qname ) {
	$GLOBALS['users'][ $qid ] = new WP_User( $qid, $qname, 'q' . $qid . '@example.test' );
}
$q_row = static function ( $id, $name, $days, $manager, array $more = array() ) use ( $qT, $qD ) {
	return array_merge( array(
		'user_id' => $id, 'record' => 'recQ' . $id, 'name' => $name, 'days' => $days, 'last_at' => $qT - $days * $qD, 'last_kind' => 'sign_in',
		'mentor_id' => 0, 'manager_id' => $manager, 'test' => false, 'reminded_at' => 0, 'would_remind' => false,
	), $more );
};
$q_snapshot = array(
	'at'   => $qT,
	'rows' => array(
		$q_row( 901, 'Ann Student', 40, 3, array( 'would_remind' => true ) ),
		$q_row( 902, 'Ben Student', 30, 72, array( 'mentor_id' => 74, 'reminded_at' => $qT - 5 * $qD ) ),
		$q_row( 903, 'Cat Student', 20, 71, array( 'last_kind' => 'call' ) ),
		$q_row( 904, 'Dan Student', 15, 0, array( 'last_kind' => 'start', 'start_from' => 'track' ) ),
		$q_row( 905, 'TEST Tess', 16, 3, array( 'test' => true ) ),
	),
);
$GLOBALS['uid']          = 3;
$GLOBALS['student_page'] = 'https://example.test/student-report-card/';

$GLOBALS['flags_mode'] = 'off';
ck( 'quiet_card_hidden_when_off: the switch off draws nothing at all, whatever the snapshot holds', capture( static function () use ( $q_snapshot ) { WPCPM_Administrators_Cards::render_quiet_students( $q_snapshot ); } ), '' );

$GLOBALS['flags_mode'] = 'preview';
$qc = capture( static function () use ( $q_snapshot ) { WPCPM_Administrators_Cards::render_quiet_students( $q_snapshot ); } );
ck( 'the card is a section at its anchor, the canonical disclosure inside, folded', array( has( $qc, '<section class="wpcpm-administrator__card" id="wpcpm-quiet-students">' ), has( $qc, 'wpcpm-group__disclosure' ), card_row( $qc )['open'] ), array( true, true, false ) );
ck( 'quiet_card_test_marked_not_counted: the badge counts the four real students, the test one is listed with its mark and left out', array( card_badge( $qc ), has( $qc, 'TEST Tess' ), has( $qc, WPCPM_Test_Institutions::MARK_DASHBOARD ), substr_count( $qc, WPCPM_Test_Institutions::MARK_DASHBOARD ) ), array( 4, true, true, 1 ) );
ck( 'the phrase says the count, the threshold and when the list was made', card_row( $qc )['state'], '4 students quiet for 14 days or more, as of 15 January 2027' );
// Task 18f, fix round 2 (d, e): the head phrase's date is whole; "Would remind" is one line.
ck( 'fix round 2: the quiet phrase\'s date whole, and Would remind on one line', array( has( $qc, "as of 15\u{00A0}January\u{00A0}2027" ), has( $qc, "Would\u{00A0}remind" ) ), array( true, true ) );
ck( 'quiet_card_row_facts: the name links to that student on the Student Report Card', has( $qc, '<a href="https://example.test/student-report-card/?wpcpm_student_view=901">Ann Student</a>' ), true );
ck( 'in preview a row that would be reminded says so, and one reminded before says when, with no reply', array( has_words( $qc, 'Would remind' ), has_words( $qc, 'Reminded ' . gmdate( 'j F Y', $qT - 5 * $qD ) . ', no reply' ) ), array( true, true ) );
// A row's weight: the row's own markup, the student's button included, from its <tr> to the next.
preg_match( '#<tr><th scope="row"><a href="[^"]*wpcpm_student_view=901">.*?</tr>#s', $qc, $q_one );
$GLOBALS['flags_mode'] = 'on';
$qc_on = capture( static function () use ( $q_snapshot ) { WPCPM_Administrators_Cards::render_quiet_students( $q_snapshot ); } );
ck( 'in on mode "Would remind" is never drawn and the reminded row says so', array( has( $qc_on, 'Would remind' ), has( $qc_on, 'Reminded ' ) ), array( false, true ) );
$q_stale = array( 'at' => $qT, 'rows' => array( $q_row( 906, 'Eve Student', 25, 3, array( 'reminded_at' => 0, 'would_remind' => true ) ) ) );
$q_old   = array( 'at' => $qT, 'rows' => array( $q_row( 907, 'Fay Student', 25, 3, array( 'reminded_at' => 0, 'would_remind' => false ) ) ) );
$q_draw  = static function ( $snap ) { return capture( static function () use ( $snap ) { WPCPM_Administrators_Cards::render_quiet_students( $snap ); } ); };
$GLOBALS['flags_mode'] = 'preview';
$q_prev_stale = $q_draw( $q_stale );
$q_prev_sent  = $q_draw( array( 'at' => $qT, 'rows' => array( $q_row( 908, 'Gus Student', 25, 3, array( 'reminded_at' => $qT - 3 * $qD, 'would_remind' => false ) ) ) ) );
$GLOBALS['flags_mode'] = 'on';
$q_on_stale = $q_draw( $q_stale );
$q_on_none  = $q_draw( $q_old );
ck( 'one rule for the reminder cell: Reminded when reminded_at is set, in either mode; Would remind only in preview; nothing for a reminder of an earlier spell', array(
	has_words( $q_prev_stale, 'Reminded' ), has_words( $q_prev_stale, 'Would remind' ),
	has_words( $q_prev_sent, 'Reminded ' . gmdate( 'j F Y', $qT - 3 * $qD ) . ', no reply' ), has_words( $q_prev_sent, 'Would remind' ),
	has_words( $q_on_stale, 'Reminded' ), has_words( $q_on_stale, 'Would remind' ), has_words( $q_on_none, 'Reminded' ),
), array( false, true, true, false, false, false, false ) );
// Final review, Minor 3: in On a row that will never be mailed says why, rather than a blank cell.
$GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] = $qT - 10 * $qD;
$GLOBALS['flags_mode'] = 'on';
$GLOBALS['flags_mode'] = 'preview';
unset( $GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] );

// "Joined the program" named the account's date for a student whose track began before it (the trial's F6).
$GLOBALS['flags_mode'] = 'preview';
$q_kinds = $q_draw( array( 'at' => $qT, 'rows' => array(
	$q_row( 911, 'Ida Student', 20, 3, array( 'last_kind' => 'start', 'start_from' => 'account' ) ),
	$q_row( 912, 'Jo Student', 21, 3, array( 'last_kind' => 'start', 'start_from' => 'track' ) ),
	$q_row( 913, 'Kim Student', 22, 3, array( 'last_kind' => 'start' ) ),
) ) );
ck( 'quiet_card_start_says_which: a never-seen student\'s clock reads "Track started" when it came from the track and "Account made" otherwise, and a list stored before the row said which reads "Counting from"', array(
	has_words( $q_kinds, 'Account made, ' . gmdate( 'j F Y', $qT - 20 * $qD ) ),
	has_words( $q_kinds, 'Track started, ' . gmdate( 'j F Y', $qT - 21 * $qD ) ),
	has_words( $q_kinds, 'Counting from ' . gmdate( 'j F Y', $qT - 22 * $qD ) ),
	has_words( $q_kinds, 'Joined the program' ),
), array( true, true, true, false ) );

// After a press on the card the page lands back on it: the card opens, saying so at its top.
$GLOBALS['flash']['student-talked'] = array( 'student' => 902, 'name' => 'Ben Student', 'at' => time() );
$q_said = $q_draw( array( 'at' => $qT, 'rows' => array( $q_row( 901, 'Ann Student', 40, 3 ) ) ) );
ck( 'quiet_card_says_noted: after a press the card is drawn open, its body starting with the line that says so', array(
	card_row( $q_said )['open'],
	has( $q_said, '<div class="wpcpm-group__body"><p class="wpcpm-dashboard__message wpcpm-dashboard__message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>Noted. Ben Student is off the list until they go quiet again.</p>' ),
	isset( $GLOBALS['flash']['student-talked'] ),
), array( true, true, false ) );
WPCPM_Dashboard_Card::forget();
$GLOBALS['flash']['student-talked'] = array( 'student' => 902, 'name' => 'Ben Student', 'at' => time() - 3600 );
ck( 'and a note an hour old opens nothing', card_row( $q_draw( array( 'at' => $qT, 'rows' => array( $q_row( 901, 'Ann Student', 40, 3 ) ) ) ) )['open'], false );
unset( $GLOBALS['flash']['student-talked'] );

$GLOBALS['flags_mode'] = 'preview';

// The tile and the page.
$GLOBALS['opts'][ WPCPM_Student_Flags::OPT_SNAPSHOT ] = $q_snapshot;
$q_data   = WPCPM_Administrators_Cards::collect();
$q_counts = WPCPM_Administrators_Cards::counts( $q_data );
ck( 'collect() holds the stored snapshot under quiet_students, and counts() draws the tile for the real rows only, after the watch tiles', array(
	isset( $q_data['quiet_students']['rows'] ) ? count( $q_data['quiet_students']['rows'] ) : null,
	isset( $q_counts['quiet_students'] ) ? $q_counts['quiet_students'] : null,
	array_search( 'quiet_students', array_keys( $q_counts ), true ) > array_search( 'duplicates', array_keys( WPCPM_Administrators_Cards::counts( array_merge( $q_data, array( 'duplicates' => array( 'addresses' => 2 ) ) ) ) ), true ),
), array( 5, array( 'label' => 'Students gone quiet', 'n' => 4, 'card' => 'quiet-students' ), true ) );
$GLOBALS['flags_mode'] = 'off';
ck( 'quiet_tile_counts_real: with the switch off the snapshot is empty and there is no tile', array( count( WPCPM_Administrators_Cards::collect()['quiet_students']['rows'] ), isset( WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() )['quiet_students'] ) ), array( 0, false ) );
unset( $GLOBALS['flags_mode'], $GLOBALS['student_page'], $GLOBALS['opts'][ WPCPM_Student_Flags::OPT_SNAPSHOT ]);

$health = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_health( $data['health'], $data['locked'] ); } );
ck( 'the error is printed verbatim and escaped', has( $health, 'HTTP 429 from Airtable &lt;b&gt;x&lt;/b&gt;' ), true );
ck( 'the locked account is named', has( $health, 'Rep One' ), true );
ck( 'the probe verdict and the invitation run are there', has( $health, 'blocked' ) && has( $health, '3 of 5' ), true );
// The mail line reads the email log: the latest email by its name, its address masked, when, and its
// status in a sentence of its own; then the last day's failures; each linked to the Emails tool's Log.
// Some names hold a "to" of their own ("..., to Administrators"), so the address follows "sent to"
// and the line never reads "to Administrators to m***@...".
ck( 'the mail line names the latest email, masks its address, and gives its status in a sentence of its own',
	array( has( $health, 'Last email: Semester report drafted, to Administrators, sent to m***@example.test on ' ), has( $health, '. Status: Handed to the mail server. <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log">Open the email log</a>' ), has( $health, 'mia@example.test' ) ),
	array( true, true, false ) );
// The failures counted are the last day's: the data asks the log for those since a day ago. The line that says so,
// linked to the Log's failed emails, is pinned on the parts with the card's other lines (Task 18c, below).
$failed_since = isset( $GLOBALS['mail_failed_since'][0] ) ? (int) strtotime( $GLOBALS['mail_failed_since'][0] . ' UTC' ) : 0;
ck( 'the failures counted are the last day\'s: the log is asked for the ones since a day ago', abs( $failed_since - ( time() - DAY_IN_SECONDS ) ) <= 5, true );
$health_quiet         = $data['health'];
$health_quiet['mail'] = array( 'latest' => array(), 'failed' => 0 );
$health_quiet         = capture( static function () use ( $health_quiet, $data ) { WPCPM_Administrators_Cards::render_health( $health_quiet, $data['locked'] ); } );

// With no table the log has nothing to show, which is not the same as no email sent: the line says
// the log is not ready, and the data the card reads says so.
$GLOBALS['mail_log_missing'] = true;
$health_missing_data         = WPCPM_Administrators_Cards::health();
$GLOBALS['mail_log_missing'] = false;
$health_ready_data           = WPCPM_Administrators_Cards::health();
ck( 'the health data says whether the email log is ready', array( $health_ready_data['mail']['ready'] ?? null, $health_missing_data['mail']['ready'] ?? null ), array( true, false ) );
$health_ready_empty                  = $health_ready_data;
$health_ready_empty['mail']['latest'] = array();
$health_ready_empty['mail']['failed'] = 0;
$health_ready_empty                  = capture( static function () use ( $health_ready_empty, $data ) { WPCPM_Administrators_Cards::render_health( $health_ready_empty, $data['locked'] ); } );

// With the Emails tool registered the card asks it for its address, whatever that is, and keeps the
// Log tab and the failed emails' status on it.
WPCPM_Tools::$emails = new class() {
	public function admin_url() { return 'https://example.test/wp-admin/admin.php?page=moved-emails'; }
};
$health_registered   = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_health( $data['health'], $data['locked'] ); } );
WPCPM_Tools::$emails = null;
ck( 'with the Emails tool registered the card links the Log at the tool\'s own address, the Log tab and the failed emails kept',
	array( has( $health_registered, '<a href="https://example.test/wp-admin/admin.php?page=moved-emails&tab=log">Open the email log</a>' ), has( $health_registered, '<a href="https://example.test/wp-admin/admin.php?page=moved-emails&tab=log&status=failed">Show the failed emails</a>' ), has( $health_registered, 'page=wpcpm-tool-emails' ) ),
	array( true, true, false ) );

// A sync that has not run, and one with no run booked, in the Last run and Next run cells: the
// words the Overview's Syncs table says the same two facts in, a cell's sentence case. The probe's
// sentence keeps its lowercase "never", a word inside a sentence rather than a cell of its own.
$health_new                              = $data['health'];
$health_new['syncs']['students']['last'] = 0;
$health_new['probe']['time']             = 0;
$health_cells                            = capture( static function () use ( $health_new, $data ) { WPCPM_Administrators_Cards::render_health( $health_new, $data['locked'] ); } );
preg_match_all( '#<tr class="wpcpm-health__sync"><td>([^<]*)</td><td>[^<]*</td><td>([^<]*)</td><td>([^<]*)</td>#', $health_cells, $health_rows, PREG_SET_ORDER );
$runs = array();
foreach ( $health_rows as $row ) {
	$runs[ $row[1] ] = array( $row[2], $row[3] );
}

// A run() that has already finished is history, not a live count: it must stop being read as
// "N of M sent" once the run itself says it is done (final review, Important 7g).
$health_data = $data['health'];
$health_data['invites']['run']['finished'] = 1756990500;
$health_finished = capture( static function () use ( $health_data, $data ) { WPCPM_Administrators_Cards::render_health( $health_data, $data['locked'] ); } );
ck( 'a finished run no longer prints Invitations:', has( $health_finished, 'Invitations:' ), false );

// The outcome figures' weekly read (1.122.24), a fifth row in the same data: the real class, read
// from the options here, loaded only now so every check above still sees the four audiences. The
// card's renderer is not changed for it: it draws the row as it draws every sync, the note where it
// prints a sync's last error.
if ( ! class_exists( 'WPCPM_Settings_Screen' ) ) {
	/** The Settings screen's address at a tab, as the real class builds it. */
	class WPCPM_Settings_Screen {
		public static function settings_url( $tab = '' ) { return add_query_arg( 'tab', $tab, admin_url( 'admin.php?page=wpcpm-settings' ) ); }
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-outcome-figures.php';

$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ]     = array(
	'read'       => 1756710000,
	'graduation' => array( 'share' => 87, 'count' => 361, 'n' => 413 ),
	'recommend'  => array( 'share' => 88, 'count' => 277, 'n' => 315 ),
	'keep'       => array( 'share' => 79, 'count' => 245, 'n' => 310 ),
);
$GLOBALS['opts'][ WPCPM_Outcome_Figures::RUN_OPTION ] = array( 'time' => 1757314800, 'result' => 'failed', 'reason' => 'refused', 'message' => 'Failed: Airtable refused the token.', 'counts' => array(), 'monday' => '2026-09-08', 'tries' => 6 );
$GLOBALS['next'][ WPCPM_Outcome_Figures::HOOK ]       = 1757919600;

// The figures' clock an hour after the attempt recorded, Monday 8 September 2025 08:00 UTC, a week
// after the set was read: on the real clock the set is past the 29 days, and the row would say that too.
WPCPM_Outcome_Figures::for_tests( static function () { return 1757318400; } );

$with_figures   = WPCPM_Administrators_Cards::health();

// The sponsors live on the site, so there is no sponsors sync and the figures are the fourth row.
ck( 'the outcome figures are a fourth row of the syncs data, after the three audiences',
	array_keys( $with_figures['syncs'] ), array( 'students', 'mentors', 'institutions', 'outcome_figures' ) );
// The card draws the row as it draws every sync, read off the parts (Task 18c: the old row's check left in Task 18b):
// its name, Idle, when the stored set was read, the next Monday (both fixed moments, through the site's formats), the
// failed run's sentence as its last error, and Open to the Connection tab of Settings, naming its sync.
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$figures_health = capture( static function () use ( $with_figures, $data ) { WPCPM_Administrators_Cards::render_health( $with_figures, $data['locked'] ); } );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
$figures_at = static function ( $t ) { return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $t ) ); };
ck( 'and the card draws it as every sync: its name, Idle, when the stored set was read, the next Monday, the failed run\'s sentence as its last error, and Open to the Connection tab of Settings', array(
	substr_count( $figures_health, '<tr role="row"><th scope="row"' ),
	has_words( $figures_health, '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead">Outcome figures</th><td class="wpcpm-ui-cell--text" role="cell" data-label="State">Idle</td><td class="wpcpm-ui-cell--date" role="cell" data-label="Last run">' . $figures_at( 1756710000 ) . '</td><td class="wpcpm-ui-cell--date" role="cell" data-label="Next run">' . $figures_at( 1757919600 ) . '</td><td class="wpcpm-ui-cell--token" role="cell" data-label="Last error">Failed: Airtable refused the token.</td><td class="wpcpm-ui-cell--action" role="cell"><a class="wpcpm-btn wpcpm-btn--quiet" href="https://example.test/wp-admin/admin.php?page=wpcpm-settings&tab=connection">Open<span class="screen-reader-text"> Outcome figures</span></a></td></tr>' ),
), array( 4, true ) );


// The weekly read is judged on the card's closed row by its own last read and by its age, never by
// the six hours the audiences' syncs are held to: held, as the first read always is until a person
// stores it, or failed needs a look, the switch on or off, and so does a read that has stopped, the
// figures stored past the 29 days so the homepage shows none; waiting, stored or none does not,
// whatever its note says. With nothing else to look at the row says which, a held read as held for a
// look and never as an error; the six-hour sentence speaks of the audiences' syncs alone, and the
// count beside another sync that needs a look is one number for both.
$figures_look = static function ( $result, $auto, $days = null, array $audiences = array() ) use ( $with_figures ) {
	if ( null === $result ) {
		unset( $GLOBALS['opts'][ WPCPM_Outcome_Figures::RUN_OPTION ] );
	} else {
		$GLOBALS['opts'][ WPCPM_Outcome_Figures::RUN_OPTION ] = array( 'time' => time() - HOUR_IN_SECONDS, 'result' => $result, 'reason' => 'refused', 'message' => 'Said.', 'counts' => array(), 'monday' => '2026-09-08', 'tries' => 1 );
	}
	if ( null === $days ) {
		unset( $GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] );
	} else {
		$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = array_merge( $GLOBALS['figures_set'], array( 'read' => time() - (int) round( $days * DAY_IN_SECONDS ) ) );
	}
	$syncs = array();
	foreach ( $audiences as $i => $audience ) {
		$syncs[ 'sync' . $i ] = array( 'label' => 'Sync ' . $i, 'progress' => array( 'running' => false, 'phase' => 'done', 'label' => 'Done', 'error' => $audience[1], 'elapsed' => 0 ), 'last' => time() - (int) round( $audience[0] * HOUR_IN_SECONDS ), 'next' => 0, 'screen' => 'https://example.test/wp-admin/admin.php?page=wpcpm-students' );
	}
	$syncs['outcome_figures'] = $with_figures['syncs']['outcome_figures'];
	$card                     = capture( static function () use ( $syncs, $auto ) { WPCPM_Administrators_Cards::render_health( array( 'syncs' => $syncs, 'auto_sync' => $auto ), array() ); } );
	return array( card_badge( $card ), card_row( $card )['state'] );
};
$GLOBALS['figures_set'] = $GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ];
WPCPM_Outcome_Figures::for_tests();
// The row's words as the card prints them, its apostrophes escaped.
$every = 'Every audience&#039;s sync ran in the last six hours';
ck( 'the outcome figures\' row needs a look when its last read was held or failed, Automatic sync on or off, or when the read has stopped, the figures stored more than 29 days old; waiting, stored or no read at all counts none, and the row then says the audiences\' syncs ran in the last six hours',
	array(
		$figures_look( 'held', true ),
		$figures_look( 'held', false ),
		$figures_look( 'failed', true, 7 ),
		$figures_look( 'failed', false, 7 ),
		$figures_look( 'stored', true, 30 ),
		$figures_look( 'waiting', false, 30 ),
		$figures_look( null, true, 45 ),
		$figures_look( 'held', true, 30 ),
		$figures_look( 'stored', true, 28.9 ),
		$figures_look( 'waiting', true, 7 ),
		$figures_look( null, true ),
		$figures_look( 'stored', false, 7 ),
	),
	array(
		array( 1, 'The outcome figures are held for a look' ),
		array( 1, 'Automatic sync is off; the outcome figures are held for a look' ),
		array( 1, 'The outcome figures&#039; last read failed' ),
		array( 1, 'Automatic sync is off; the outcome figures&#039; last read failed' ),
		array( 1, 'The outcome figures are no longer shown on the homepage' ),
		array( 1, 'Automatic sync is off; the outcome figures are no longer shown on the homepage' ),
		array( 1, 'The outcome figures are no longer shown on the homepage' ),
		array( 1, 'The outcome figures are held for a look' ),
		array( 0, $every ),
		array( 0, $every ),
		array( 0, $every ),
		array( 0, 'Automatic sync is off' ),
	) );
ck( 'beside an audience\'s sync that needs a look the figures are one more in the one count the row says, the switch on or off, and an audience\'s error alone is still an error',
	array(
		$figures_look( 'held', true, null, array( array( 7, '' ) ) ),
		$figures_look( 'stored', true, 30, array( array( 1, '' ), array( 1, 'HTTP 429 from Airtable' ) ) ),
		$figures_look( 'held', false, null, array( array( 24 * 30, 'HTTP 500' ) ) ),
		$figures_look( 'stored', false, 7, array( array( 24 * 30, 'HTTP 500' ), array( 1, '' ) ) ),
		$figures_look( 'stored', true, 7, array( array( 1, '' ), array( 5, '' ), array( 2, '' ) ) ),
	),
	array(
		array( 2, '2 syncs need a look' ),
		array( 2, '2 syncs need a look' ),
		array( 2, 'Automatic sync is off; 2 syncs need a look' ),
		array( 1, 'Automatic sync is off; 1 sync reports an error' ),
		array( 0, $every ),
	) );
// That row's rule is a predicate of its own (1.123.30), for a reader that says it per sync: true for the reads
// the card counts above, held, failed and stopped, and false for the others.
$figures_needs = static function ( $result, $days = null ) use ( $figures_look ) {
	$figures_look( $result, true, $days );
	return WPCPM_Administrators_Cards::figures_need_look();
};
ck( 'figures_need_look() is the row\'s own rule: a held read, a failed one and a stopped one need a look; a waiting one, a stored one and none at all do not',
	array(
		$figures_needs( 'held' ),
		$figures_needs( 'failed', 7 ),
		$figures_needs( 'stored', 30 ),
		$figures_needs( null, 45 ),
		$figures_needs( 'stored', 28.9 ),
		$figures_needs( 'waiting', 7 ),
		$figures_needs( null ),
	),
	array( true, true, true, true, false, false, false ) );
// The Administrator Dashboard's strip counts the weekly read as the card does, from the one row: on
// a quiet day a held, failed or stopped read draws the Syncs tile with the card's own number, the
// switch on or off, and the strip is no longer the sentence that says nothing is waiting; a read
// that needs no look draws no tile and leaves the day one sentence.
$figures_strip = array();
foreach ( array(
	'held, on'     => array( 'held', true ),
	'held, off'    => array( 'held', false ),
	'failed, on'   => array( 'failed', true, 7 ),
	'failed, off'  => array( 'failed', false, 7 ),
	'stopped, on'  => array( 'stored', true, 30 ),
	'stopped, off' => array( null, false, 45 ),
	'stored, on'   => array( 'stored', true, 7 ),
	'waiting, off' => array( 'waiting', false, 7 ),
) as $day => $read ) {
	$badge                              = $figures_look( ...$read )[0];
	$health                             = $quiet_data['health'];
	$health['syncs']['outcome_figures'] = $with_figures['syncs']['outcome_figures'];
	$health['auto_sync']                = $read[1];
	$day_counts                         = WPCPM_Administrators_Cards::counts( array_merge( $quiet_data, array( 'health' => $health ) ) );
	$figures_strip[ $day ]              = array(
		'tiles' => array_map( static function ( $tile ) { return $tile['n'] . ' ' . $tile['label'] . ' > ' . $tile['card']; }, $day_counts ),
		'badge' => $badge,
	);
}
$looks = array( 'tiles' => array( 'syncs' => '1 Sync needs a look > health' ), 'badge' => 1 );
$none  = array( 'tiles' => array(), 'badge' => 0 );
ck( 'the Syncs tile counts the weekly read as the card\'s badge does: a held, failed or stopped read gives it the card\'s own number, the switch on or off, and a read that needs no look gives no tile',
	$figures_strip,
	array(
		'held, on'     => $looks,
		'held, off'    => $looks,
		'failed, on'   => $looks,
		'failed, off'  => $looks,
		'stopped, on'  => $looks,
		'stopped, off' => $looks,
		'stored, on'   => $none,
		'waiting, off' => $none,
	) );
unset( $GLOBALS['figures_set'] );

unset( $GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ], $GLOBALS['opts'][ WPCPM_Outcome_Figures::RUN_OPTION ], $GLOBALS['next'][ WPCPM_Outcome_Figures::HOOK ] );
WPCPM_Outcome_Figures::for_tests();

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

// Read only once it is known to be there, so a register() that stopped registering the sheet fails
// this check by name, and the suite goes on to the checks after it.
$admin_style = isset( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ] ) ? $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ] : array( 'src' => '', 'deps' => array() );
ck( 'register() registers administrator.css itself, the sheet the page and the Sponsor Dashboard\'s strip both wear', isset( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ] ), true );
ck( 'administrator.css is registered on the mentors\' stylesheet, where the Tools section is laid out', array( false !== strpos( $admin_style['src'], 'administrator.css' ), in_array( WPCPM_Mentors_Dashboard::STYLE, $admin_style['deps'], true ) ), array( true, true ) );
// The Sponsor Dashboard's At a glance strip wears this page's attention tiles, so its sheet depends on
// this one and has this class register it, the way it has the mentor sheet registered: on its own,
// with the mentors' sheet under it, and nothing else of the page's, no shortcode and no block. Since
// the version 3 concepts (1.123.69) the page is drawn in the dashboards' frame, whose sheet the frame
// registers here too, after this page's; the cohort timeline's sheet is registered only while its
// class is loaded, and this suite loads none of it.
$registered_before = array( $GLOBALS['styles'], $GLOBALS['calls'] );
$GLOBALS['styles'] = array();
$GLOBALS['calls']  = array();
$assets_alone      = method_exists( 'WPCPM_Administrators_Dashboard', 'register_assets' );
if ( $assets_alone ) {
	WPCPM_Administrators_Dashboard::register_assets();
}
ck( 'register_assets() registers administrator.css on its own, on the mentors\' stylesheet, then the frame\'s sheet the page is drawn in, and nothing else: the Sponsor Dashboard\'s sheet depends on it', array( $assets_alone, array_keys( $GLOBALS['styles'] ), isset( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ] ) ? $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ] : null, isset( $GLOBALS['styles'][ WPCPM_Dashboard_Frame::STYLE ]['src'] ) ? $GLOBALS['styles'][ WPCPM_Dashboard_Frame::STYLE ]['src'] : null, $GLOBALS['calls'] ), array( true, array( WPCPM_Mentors_Dashboard::STYLE, WPCPM_Administrators_Dashboard::STYLE, WPCPM_UI::STYLE, WPCPM_Dashboard_Frame::STYLE ), $admin_style, WPCPM_PLUGIN_URL . 'assets/css/dashboard-frame.css', array() ) );
list( $GLOBALS['styles'], $GLOBALS['calls'] ) = $registered_before;

$GLOBALS['uid']    = 0;
// An array of allowed IDs, not a bare flag: user_can() above tests it with in_array(), the
// same contract bin/test-institutions-dashboard.php's stub uses.
$GLOBALS['manage'] = array();
$out = WPCPM_Administrators_Dashboard::render( array() );
ck( 'logged out: a notice and no card', has( $out, 'wpcpm-dashboard--notice' ) && ! has( $out, 'wpcpm-administrator__card' ), true );
// Same shape as WPCPM_Institutions_Dashboard::render() for a logged-out visitor (final
// review, 7j): a sentence and a Log in button, not a dead end.
ck( 'and it offers a way to sign in, not a dead end', has( $out, 'wp-login.php' ) && has( $out, '>Log in<' ), true );
// The notice is printed before the frame opens: no menu, no head, nothing of the page around it.
ck( 'and none of the page\'s frame or head: the notice is all a visitor who cannot use the page reads', array( has( $out, 'wpcpm-frame' ), has( $out, 'wpcpm-head' ) ), array( false, false ) );

$GLOBALS['uid']    = 21;
$GLOBALS['manage'] = array();
$out = WPCPM_Administrators_Dashboard::render( array() );
ck( 'a signed-in non-manager gets the refusal and no form', has( $out, 'cannot manage the program' ) && ! has( $out, '<form' ), true );
ck( 'and none of the page\'s frame or head either', array( has( $out, 'wpcpm-frame' ), has( $out, 'wpcpm-head' ) ), array( false, false ) );

$GLOBALS['uid']       = 3;
$GLOBALS['manage']    = array( 3 );
$GLOBALS['flash']     = array( 'institutions' => 'app-approved' );
$GLOBALS['prompted']  = array();
$GLOBALS['resources'] = array();
$GLOBALS['tools']     = array();
$GLOBALS['reviews']   = array();
$out = WPCPM_Administrators_Dashboard::render( array( 'title' => 'Today' ) );
// What the Overview asked of the collaborators while it was drawn, kept before the Reference view and
// the Overview with every queue holding something are drawn below; and what counts() gives for the
// same state, which the head and the menu read.
$overview_asked = array(
	'tools'     => $GLOBALS['tools'],
	'resources' => $GLOBALS['resources'],
	'reviews'   => $GLOBALS['reviews'],
);
$out_counts     = WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() );
// The page is drawn in the dashboards' frame (the version 3 concepts, 1.123.69): the page's own wrapper,
// with its view after `--`, sits inside the frame's main area, so every rule the cards were dressed by
// still reaches them.
ck( 'the manager gets the page with the title, in the Overview\'s wrapper, inside the frame\'s main area', array(
	0 === strpos( $out, '<div class="wpcpm-frame wpcpm-frame--administrator" data-wpcpm-frame>' ),
	has( $out, '<div class="wpcpm-frame__main"><div class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--overview"><h2 class="wpcpm-dashboard__title">Today</h2>' ),
), array( true, true ) );
// The page opens with its head, where one sentence under the title said what the page was and the
// attention strip followed it: how many decisions wait, in the serif, the oldest queue in words and
// the one button that opens its card. The figures follow, then the last decision's words, where the
// page's outcome is said when no card holds it (`WPCPM_Dashboard_Card::SLOT`), then the two columns.
// The head counts the queues waiting for a decision as counts() gives them, the mentor applications
// among them: the semester reports' row says when it came in, and it is the oldest of the fixture's.
$waiting_keys = array( 'applications', 'agreements', 'reports', 'requests', 'mentor_applications', 'sponsor_applications', 'sponsor_posts', 'sponsor_offers', 'sponsor_events', 'sponsor_agreements', 'sponsor_reports' );
$waiting_of   = static function ( array $counts ) use ( $waiting_keys ) {
	$sum = 0;
	foreach ( $waiting_keys as $key ) {
		$sum += isset( $counts[ $key ]['n'] ) ? (int) $counts[ $key ]['n'] : 0;
	}
	return $sum;
};
$out_waiting  = $waiting_of( $out_counts );
$reports_days = (int) floor( ( time() - (int) $data['reports']['queue'][0]['generated'] ) / DAY_IN_SECONDS );
ck( 'under the title the page opens with its head, no longer a sentence and the strip: the decisions that wait in the serif, the oldest queue and how long it has waited, and the button to its card; then the figures, the last decision\'s words and the Waiting group, each once (Watch is its own view since 1.123.71)', array(
	has( $out, '<h2 class="wpcpm-dashboard__title">Today</h2><header class="wpcpm-head"><div class="wpcpm-head__copy"><p class="wpcpm-head__title">' . $out_waiting . ' decisions wait for you</p><p class="wpcpm-head__lede">The oldest has waited ' . $reports_days . ' days, in Semester reports.</p><div class="wpcpm-head__actions"><a class="wpcpm-btn" href="#wpcpm-reports">Review the oldest</a></div></div></header><section class="wpcpm-figures wpcpm-figures--2" aria-label="The program&#039;s numbers">' ),
	has( $out, '</p></section><p class="wpcpm-dashboard__message wpcpm-dashboard__message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>The application is approved.</p><div class="wpcpm-overview wpcpm-overview--wait">' ),
	array( substr_count( $out, '<header class="wpcpm-head">' ), substr_count( $out, '<section class="wpcpm-figures' ), substr_count( $out, 'data-wpcpm-card-message' ), substr_count( $out, '<div class="wpcpm-overview wpcpm-overview--' ) ),
	array( has( $out, 'wpcpm-dashboard__intro' ), has( $out, 'id="wpcpm-attention"' ), has( $out, 'wpcpm-attention__tile' ) ),
), array( true, true, array( 1, 1, 1, 1 ), array( false, false, false ) ) );
ck( 'the head\'s count is the sum of what counts() gives the queues waiting for a decision, the mentor applications among them and the cards to watch not, and the menu says the same number beside Overview', array( $out_waiting, menu_items( $out )['Overview']['badge'] ?? null ), array( 16, '16 wait' ) );
// The figures the homepage draws, as far as the fixture holds them: no shares are published here, so
// the band holds the active mentors and the programs running, and its line says they are today's.
ck( 'the figures band holds what the site can say: with no shares published, the active mentors with how many were seen lately, the programs running with the institutions they run at, and one line saying when they were read', has( $out, '<section class="wpcpm-figures wpcpm-figures--2" aria-label="The program&#039;s numbers"><div class="wpcpm-figure"><p class="wpcpm-figure__value">0</p><p class="wpcpm-figure__label">active mentors</p><p class="wpcpm-figure__base">0 seen in the last 30 days</p></div><div class="wpcpm-figure"><p class="wpcpm-figure__value">3</p><p class="wpcpm-figure__label">programs running</p><p class="wpcpm-figure__base">at 1 institution</p></div><p class="wpcpm-figures__source">Mentors and programs as of today, from the program&#039;s records.</p></section>' ), true );
ck( 'the two-factor prompt is for the viewer', $GLOBALS['prompted'], array( 3 ) );
ck( 'the flash on the institutions channel is drawn in the queue\'s words', has( $out, 'The application is approved.' ) && has( $out, 'wpcpm-dashboard__message--success' ), true );
ck( 'and taken, so it shows once', isset( $GLOBALS['flash']['institutions'] ), false );
// The Watch view (1.123.71), drawn with the site as the Overview above was: the cards to watch.
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_WATCH;
$out_watch = WPCPM_Administrators_Dashboard::render( array() );
unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] );
// The Talks view (Institutions off Airtable, release 2, Task 16): its own class draws it (bin/test-institution-talks-view.php);
// here the page's part, with the view stood in for at its contract, declared only now so every check above ran without it.
echo "\n=== The Talks view in the frame (Task 16) ===\n";
if ( ! class_exists( 'WPCPM_Institution_Talks_View' ) ) {
	class WPCPM_Institution_Talks_View {
		public static function shown() { return ! empty( $GLOBALS['talks_on'] ); }
		public static function due_count( $viewer_id ) { $GLOBALS['talks_due_for'][] = (int) $viewer_id; return (int) ( $GLOBALS['talks_due'] ?? 0 ); }
		public static function render( WP_User $viewer, array $frame = array() ) {
			echo '<p data-talks-view="' . (int) $viewer->ID . '">' . $frame['page_name'] . '</p>';
			call_user_func( $frame['messages'] );
		}
	}
}
$talks_kept      = array( $GLOBALS['uid'], $GLOBALS['manage'] );
$GLOBALS['uid']  = 3;
$GLOBALS['manage'] = array( 3 );
$talks_menu_before = array_keys( menu_items( WPCPM_Administrators_Dashboard::render( array() ) ) );
$GLOBALS['talks_on'] = false;
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_TALKS;
$talks_off = WPCPM_Administrators_Dashboard::render( array() );
ck( 'Talks: outside site mode the menu has no Talks item and ?wpcpm_view=talks draws the Overview', array( array_keys( menu_items( $talks_off ) ) === $talks_menu_before, has( $talks_off, 'wpcpm-administrator--overview' ), has( $talks_off, 'data-talks-view' ), isset( menu_items( $talks_off )['Talks'] ) ), array( true, true, false, false ) );
$GLOBALS['talks_on']      = true;
$GLOBALS['talks_due']     = 3;
$GLOBALS['talks_due_for'] = array();
$talks_on  = WPCPM_Administrators_Dashboard::render( array() );
$talks_nav = menu_items( $talks_on );
$talks_at  = array_search( 'Talks', array_keys( $talks_nav ), true );
ck( 'Talks: in site mode the view is drawn in the page\'s root, named talks, with the page\'s name and its messages and card slot', array( has( $talks_on, 'wpcpm-administrator--talks' ), has( $talks_on, 'data-talks-view="' . (int) $GLOBALS['uid'] . '"' ), substr_count( $talks_on, '<!-- wpcpm-card-message -->' ) ), array( true, true, 0 ) );
ck( 'Talks: the menu item after Watch, current on the view, the viewer\'s own due count in amber', array( array_keys( $talks_nav )[ $talks_at - 1 ] ?? null, $talks_nav['Talks']['current'] ?? null, $talks_nav['Talks']['badge'] ?? null, $talks_nav['Talks']['tone'] ?? null, $talks_nav['Talks']['url'] ?? null, array_values( array_unique( $GLOBALS['talks_due_for'] ) ) ), array( 'Watch', true, '3', 'amber', WPCPM_Administrators_Dashboard::page_url() . '?wpcpm_view=talks', array( (int) $GLOBALS['uid'] ) ) );
ck( 'Talks: its icon is the chat bubble', has( $talks_on, WPCPM_Icons::svg( 'chat', 20 ) ) || has( $talks_on, 'M4.5 5.5h15v10h-8l-4.5 3.5v-3.5h-2.5z' ), true );
$GLOBALS['talks_due'] = 0;
unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] );
$talks_zero = menu_items( WPCPM_Administrators_Dashboard::render( array() ) );
ck( 'Talks: with nothing due the item has no badge, and on the Overview it is not current', array( $talks_zero['Talks']['badge'] ?? null, $talks_zero['Talks']['current'] ?? null ), array( '', false ) );
$GLOBALS['uid'] = 0;
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_TALKS;
$talks_out = WPCPM_Administrators_Dashboard::render( array() );
ck( 'Talks: a signed-out visitor asking for the view is asked to log in, and nothing of it is drawn', array( has( $talks_out, 'Log in to see the Administrator Dashboard.' ), has( $talks_out, 'data-talks-view' ) ), array( true, false ) );
$GLOBALS['uid'] = 21;
$talks_member   = WPCPM_Administrators_Dashboard::render( array() );
ck( 'Talks: an account that is no Administrator asking for the view is told the page has nothing for it, and nothing of it is drawn', array( has( $talks_member, 'data-talks-view' ), has( $talks_member, 'wpcpm-frame' ) ), array( false, false ) );
list( $GLOBALS['uid'], $GLOBALS['manage'] ) = $talks_kept;
unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ], $GLOBALS['talks_on'], $GLOBALS['talks_due'], $GLOBALS['talks_due_for'] );
// The Reference view, at the page's own address with ?wpcpm_view=reference, and the Overview with every
// queue holding something but the Events card's, which is no queue while Sponsor tiers is off: the one
// sponsor offer waiting for review the fixture read above is put back for it, and a student waiting to graduate
// (1.123.93) is added.
$GLOBALS['tools']     = array();
$GLOBALS['resources'] = array();
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_REFERENCE;
$out_ref              = WPCPM_Administrators_Dashboard::render( array() );
unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] );
$reference_asked      = array(
	'tools'     => $GLOBALS['tools'],
	'resources' => $GLOBALS['resources'],
);
$GLOBALS['offer_reviews'] = array( array( 'id' => 931, 'record' => 'recSPN00000000001', 'company' => 'TEST Sponsor', 'title' => 'A year of hosting', 'change' => 'new', 'by' => 'Member One', 'at' => time() - 600, 'fields' => array() ) );
$GLOBALS['unlinked']      = pending_unlinked();
$out_full                 = both_views();
unset( $GLOBALS['offer_reviews'], $GLOBALS['unlinked'] );
/**
 * The Overview and the Watch view, drawn one after the other with the site as it stands: every card the
 * page draws outside The program and the cohort timeline, the Overview's first (1.123.71: Watch is its
 * own view).
 *
 * @return string
 */
function watch_view() {
	$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_WATCH;
	$watch = WPCPM_Administrators_Dashboard::render( array() );
	unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] );
	return $watch;
}
/**
 * The Overview, then the Watch view.
 *
 * @return string
 */
function both_views() {
	$overview = WPCPM_Administrators_Dashboard::render( array() );
	$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_WATCH;
	$watch = WPCPM_Administrators_Dashboard::render( array() );
	unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] );
	return $overview . $watch;
}
/**
 * Whether every needle is in the text, in the order given.
 *
 * @param string   $html    The text.
 * @param string[] $needles What it holds, in order.
 * @return bool
 */
function in_order( $html, array $needles ) {
	$positions = array();
	foreach ( $needles as $needle ) {
		$positions[] = strpos( (string) $html, $needle );
	}
	$sorted = $positions;
	sort( $sorted );
	return ! in_array( false, $positions, true ) && $positions === $sorted;
}
/**
 * The frame's menu as a page draws it, by each item's words, in order: its address, whether it is
 * marked current by its class (`marked`) and by `aria-current="page"` (`current`), the words of its
 * badge ('' for none) and their tone ('' for a plain count), and whether it leaves the dashboard for
 * wp-admin.
 *
 * @param string $html The page.
 * @return array[]
 */
function menu_items( $html ) {
	$nav = (string) strstr( (string) strstr( (string) $html, '<nav class="wpcpm-frame__menu"' ), '</nav>', true );
	preg_match_all( '#<li><a class="wpcpm-frame__item( is-current)?" href="([^"]*)"( aria-current="page")?>.*?<span class="wpcpm-frame__label">([^<]*)(?:<span class="screen-reader-text">, [^<]*</span>)?</span>(?:<span aria-hidden="true" class="wpcpm-frame__badge( wpcpm-frame__badge--[a-z]+)?">([^<]*)</span>)?(.*?)</a></li>#s', $nav, $found, PREG_SET_ORDER );
	$items = array();
	foreach ( $found as $item ) {
		$items[ html_entity_decode( $item[4], ENT_QUOTES ) ] = array(
			'url'      => $item[2],
			'marked'   => '' !== $item[1],
			'current'  => '' !== $item[3],
			'badge'    => isset( $item[6] ) ? $item[6] : '',
			'tone'     => isset( $item[5] ) && '' !== $item[5] ? substr( $item[5], strlen( ' wpcpm-frame__badge--' ) ) : '',
			'external' => isset( $item[7] ) && false !== strpos( $item[7], '(in wp-admin)' ),
		);
	}
	return $items;
}
/**
 * The queues the Overview's one sentence names as empty, by name, each with the address its name
 * links: their screens in wp-admin. Empty when the page prints no such sentence.
 *
 * @param string $html The page.
 * @return array<string, string>
 */
function quiet_links( $html ) {
	if ( 1 !== preg_match( '#<p class="wpcpm-ui-group__note">(.*?)</p>#s', (string) $html, $quiet ) ) {
		return array();
	}
	preg_match_all( '#<a href="([^"]*)">([^<]*)</a>#', $quiet[1], $links, PREG_SET_ORDER );
	$named = array();
	foreach ( $links as $link ) {
		$named[ html_entity_decode( $link[2], ENT_QUOTES ) ] = $link[1];
	}
	return $named;
}
/**
 * What each card's row on a page carries besides its title, count and phrase, by the card's anchor:
 * whether its disclosure is drawn open, how long its oldest item has waited (`age`) and the cue a
 * folded row shows (`cue`), each null when the row has none.
 *
 * @param string $html The page.
 * @return array[]
 */
function row_marks( $html ) {
	preg_match_all( '#<section class="wpcpm-administrator__card" id="wpcpm-([a-z-]+)"><details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure"( open)?><summary class="wpcpm-group__summary">(.*?)</summary>#s', (string) $html, $rows, PREG_SET_ORDER );
	$marks = array();
	foreach ( $rows as $row ) {
		$marks[ $row[1] ] = array(
			'open' => '' !== $row[2],
			'age'  => 1 === preg_match( '#<span class="wpcpm-card__age wpcpm-status wpcpm-status--red">([^<]*)</span>#', $row[3], $age ) ? $age[1] : null,
			'cue'  => 1 === preg_match( '#<span class="wpcpm-card__cue" aria-hidden="true">([^<]*)</span>#', $row[3], $cue ) ? $cue[1] : null,
		);
	}
	return $marks;
}
/**
 * The cards counts() leads to, in its order, one for each count that names a card: what the strip's
 * tiles linked to, and what the head and the menu read now.
 *
 * @param array $counts What `WPCPM_Administrators_Cards::counts()` returned.
 * @return string[]
 */
function count_cards( array $counts ) {
	return array_values( array_filter( array_map( static function ( $tile ) { return isset( $tile['card'] ) ? (string) $tile['card'] : ''; }, $counts ) ) );
}
ck( 'every card is drawn in the approved order: on the Overview the head, the figures, the queues waiting for a decision, the mentor applications\' row among them, then the cards to watch; on the Reference view its head, the cards of the program\'s numbers, then the Tools section last, with no Resources column after it (1.123.71)', array(
	in_order( $out_full, array( '<header class="wpcpm-head">', '<section class="wpcpm-figures', 'id="wpcpm-applications"', 'id="wpcpm-agreements"', 'id="wpcpm-reports"', 'id="wpcpm-requests"', 'id="wpcpm-graduation"', 'id="wpcpm-mentor-applications"', 'id="wpcpm-sponsor-applications"', 'id="wpcpm-sponsor-posts"', 'id="wpcpm-sponsor-offers-review"', 'id="wpcpm-sponsor-agreements"', 'id="wpcpm-sponsor-reports"', 'id="wpcpm-watch"', 'id="wpcpm-offers-low"', 'id="wpcpm-duplicates"', 'id="wpcpm-interests"', 'id="wpcpm-tiers-ending"', 'id="wpcpm-health"' ) ),
	in_order( $out_ref, array( '<header class="wpcpm-head">', 'id="wpcpm-sponsors"', 'id="wpcpm-programs"', 'id="wpcpm-mentor-engagement"', 'id="wpcpm-tools"' ) ),
	false === strpos( $out_ref, 'wpcpm-handbook__resources' ),
), array( true, true, true ) );
// Each card once, in this page's shape: its anchor on its section, where the mails, the wp-admin
// screens and every decision's way back land, and the page's class on the disclosure before the
// shared two. None in the Sponsor Dashboard's, whose anchor is on the disclosure. The Overview draws
// the queues' cards and the cards to watch, the Reference view the cards of the program's numbers, so
// the two together draw every card; the strip is no card and is not drawn, the Events card is not
// drawn while Sponsor tiers is off, and Students gone quiet only while its switch is on.
$page_cards = array_values( array_diff( WPCPM_Return::ANCHORS, array( 'attention', 'sponsor-events', 'quiet-students' ) ) );
$shaped     = array();
foreach ( $page_cards as $anchor ) {
	$shaped[ $anchor ] = substr_count( $out_full . $out_ref, '<section class="wpcpm-administrator__card" id="wpcpm-' . $anchor . '"><details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure"' );
}
ck( 'every card is drawn once across the Overview, with every queue holding something, and the Reference view, in this page\'s shape, its anchor on its section, and none in the Sponsor Dashboard\'s', array( $shaped, substr_count( $out_full . $out_ref, '<details id=' ), substr_count( $out_full . $out_ref, 'wpcpm-sponsor__card' ) ), array( array_fill_keys( $page_cards, 1 ), 0, 0 ) );
// A dedicated check, not just a spot in the list above: this one also pins the table, so a
// terms-ending card that rendered as an empty line (no rows read) would still be caught.
ck(
	'the Sponsor reports card closes the cards waiting for a decision, right after the Sponsor Collaboration Agreements, the Overview\'s last card, with its three parts',
	array(
		before_in( $out, 'id="wpcpm-sponsor-agreements"', 'id="wpcpm-sponsor-reports"' ),
		false === strpos( substr( $out, (int) strpos( $out, 'id="wpcpm-sponsor-reports"' ) + 1 ), '<section class="wpcpm-administrator__card"' ),
		has( $out, '>Drafts to finish</h4>' ) && has( $out, '>Terms ended with no report</h4>' ) && has( $out, '>Final this semester</h4>' ),
	),
	array( true, true, true )
);
// The Tools section and the resources moved to the Reference view: the Overview asks for neither.
ck( 'the Tools section is drawn on the Reference view alone, for the manager viewing, as the managers audience', array( $overview_asked['tools'], $reference_asked['tools'] ), array( array(), array( array( 'managers', $GLOBALS['uid'] ) ) ) );
ck( 'and neither view asks for the resources, which the menu carries since 1.123.71', array( $overview_asked['resources'], $reference_asked['resources'] ), array( array(), array() ) );
ck( 'the agreement reviews were drawn once each', $overview_asked['reviews'], array( 601, 602 ) );
ck( 'the stylesheet is registered from assets/css/administrator.css', isset( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ] ) && false !== strpos( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ]['src'], 'assets/css/administrator.css' ), true );
ck( 'and render() switched it on', ! empty( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ]['on'] ), true );
ck( 'the dashboard arms the double-submit guard its forms carry', in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ), true );
// A browser following a link to a closed card leaves it closed; the opener the Sponsor Dashboard's
// cards use opens it, a tile's, a mail's or a decision's way back. The page asks the dashboards' shell
// for it, and how the shell registers it is pinned in bin/test-dashboard-card.php.
ck( 'and the dashboards\' opener, enqueued through the shell both pages share', in_array( 'wpcpm-dashboard-open', $GLOBALS['scripts'], true ), true );

// The views (1.123.69): the Overview by default, the cohort timeline and the Reference view, each at the
// page's own address with ?wpcpm_view=. This suite loads no cohort timeline, so its view is not asked.
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = 'no-such-view';
$out_unknown = WPCPM_Administrators_Dashboard::render( array() );
unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] );
$admin_page_url = WPCPM_Administrators_Dashboard::page_url();
ck( 'the views are asked for by ?wpcpm_view=: none, or one the page does not know, is the Overview, watch the Watch view and reference The program, each in a wrapper class of its own, and view_url() writes each view\'s address', array(
	has( $out, 'class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--overview"' ) && has( $out, '<div class="wpcpm-overview wpcpm-overview--wait">' ),
	has( $out_unknown, 'class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--overview"' ) && has( $out_unknown, '<div class="wpcpm-overview wpcpm-overview--wait">' ),
	has( $out_ref, 'class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--reference"' ) && has( $out_ref, '<div class="wpcpm-reference">' ),
	has( $out_watch, 'class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--watch"' ) && has( $out_watch, '<div class="wpcpm-overview wpcpm-overview--watch" id="wpcpm-watch">' ),
	array( has( $out_ref, 'wpcpm-overview' ), has( $out, '<div class="wpcpm-reference">' ), has( $out, 'wpcpm-overview--watch' ), has( $out_watch, 'wpcpm-overview--wait' ) ),
	array( WPCPM_Administrators_Dashboard::view_url(), WPCPM_Administrators_Dashboard::view_url( 'watch' ), WPCPM_Administrators_Dashboard::view_url( 'reference' ) ),
), array( true, true, true, true, array( false, false, false, false ), array( $admin_page_url, $admin_page_url . '?wpcpm_view=watch', $admin_page_url . '?wpcpm_view=reference' ) ) );
ck( 'the Watch view opens with a head of its own, the page\'s name, Watch in the serif and one line saying what it holds, then its group under a heading of its own, and draws no figures and none of the Overview\'s cards', array(
	has( $out_watch, '<div class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--watch"><header class="wpcpm-head"><div class="wpcpm-head__copy"><p class="wpcpm-head__title">Watch</p><p class="wpcpm-head__lede">What to keep an eye on this week: nothing here waits for a decision.</p></div></header>' ),
	has( $out_watch, '<div class="wpcpm-overview wpcpm-overview--watch" id="wpcpm-watch"><div class="wpcpm-administrator__group wpcpm-administrator__group--watch"><h2 class="wpcpm-student__heading wpcpm-administrator__group-title">This week</h2>' ),
	has( $out_watch, 'wpcpm-figures' ),
	array_values( array_intersect( page_cards( $out_watch ), page_cards( $out ) ) ),
), array( true, true, false, array() ) );
ck( 'the Reference view opens with a head of its own, its name in the serif and one line saying what it holds, then its group, and draws no figures and none of the Overview\'s cards', array(
	has( $out_ref, '<div class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--reference"><header class="wpcpm-head"><div class="wpcpm-head__copy"><p class="wpcpm-head__title">The program</p><p class="wpcpm-head__lede">The sponsors, the programs running and how the mentors use the site, then the tools from our sponsors.</p></div></header><div class="wpcpm-reference"><div class="wpcpm-administrator__group wpcpm-administrator__group--reference">' ),
	has( $out_ref, 'wpcpm-figures' ),
	array_values( array_intersect( page_cards( $out_ref ), page_cards( $out_full ) ) ),
), array( true, false, array() ) );
// The menu down the side: the page's views, then the screens in wp-admin, then the help. Each item that
// leaves the dashboard says so to a screen reader and carries the external icon.
$menu     = menu_items( $out );
$ref_menu = menu_items( $out_ref );
ck( 'the menu lists the page\'s views, Overview, Watch and Reference, then the screens in wp-admin, each marked as leaving the dashboard, then the help', array(
	array_keys( $menu ),
	array_keys( array_filter( $menu, static function ( $item ) { return $item['external']; } ) ),
), array(
	array( 'Overview', 'Watch', 'The program', 'Students', 'Mentors', 'Institutions', 'Sponsors and offers', 'Tracks', 'Tools', 'Settings', 'WordPress Credits handbook', '#wpcredits on Slack' ),
	array( 'Students', 'Mentors', 'Institutions', 'Sponsors and offers', 'Tracks', 'Tools', 'Settings' ),
) );
$current_of = static function ( array $items, $key ) {
	return array_keys( array_filter( $items, static function ( $item ) use ( $key ) { return $item[ $key ]; } ) );
};
$watch_menu = menu_items( $out_watch );
ck( 'each view is at the page\'s own address, Watch at its own since 1.123.71; the view drawn is the one item marked current, by its class and by aria-current="page", on the Overview, Watch and The program alike', array(
	array( $menu['Overview']['url'], $menu['Watch']['url'], $menu['The program']['url'] ),
	array( $current_of( $menu, 'current' ), $current_of( $menu, 'marked' ), substr_count( $out, 'aria-current=' ) ),
	array( $current_of( $watch_menu, 'current' ), $current_of( $watch_menu, 'marked' ), substr_count( $out_watch, 'aria-current=' ) ),
	array( $current_of( $ref_menu, 'current' ), $current_of( $ref_menu, 'marked' ), substr_count( $out_ref, 'aria-current=' ) ),
	has( $out, 'id="wpcpm-watch"' ),
), array(
	array( $admin_page_url, $admin_page_url . '?wpcpm_view=watch', $admin_page_url . '?wpcpm_view=reference' ),
	array( array( 'Overview' ), array( 'Overview' ), 1 ),
	array( array( 'Watch' ), array( 'Watch' ), 1 ),
	array( array( 'The program' ), array( 'The program' ), 1 ),
	false,
) );
// Beside what waits, a count in words, in a tone of its own; part of the item's name for a screen
// reader, so it hears "Overview, 16 wait".
ck( 'beside what waits the menu says how much: the decisions waiting beside Overview in red, the number the head says, how many cards to watch have something to look at beside Watch in amber, Syncs and health once for its syncs and its locked accounts, and the mentor applications waiting beside Mentors, plain; no other item carries a badge', array(
	array_filter( array_map( static function ( $item ) { return '' === $item['badge'] ? '' : $item['badge'] . ( '' !== $item['tone'] ? ' (' . $item['tone'] . ')' : '' ); }, $menu ) ),
	has( $out, '<span class="wpcpm-frame__label">Overview<span class="screen-reader-text">, 16 wait</span></span>' ),
), array(
	array( 'Overview' => '16 wait (red)', 'Watch' => '3 (amber)', 'Mentors' => '4' ),
	true,
) );
// The page's name is its one H1 only while the theme draws the page as a bare canvas for the frame
// (`add_theme_support( 'wpcpm-dashboard-frame', array( 'administrator' ) )`); otherwise the theme's own
// template prints the title, and the frame's bar holds the Menu button alone.
$GLOBALS['theme_support'] = array( WPCPM_Dashboard_Frame::THEME_SUPPORT => array( array( 'administrator' ) ) );
$owned                    = WPCPM_Administrators_Dashboard::render( array() );
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_REFERENCE;
$owned_ref = WPCPM_Administrators_Dashboard::render( array() );
unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ], $GLOBALS['theme_support'] );
ck( 'while the theme leaves the page to the frame, the frame draws its top bar and the head opens with the page\'s name as the page\'s one H1, on either view; otherwise the frame\'s bar is slim and the page prints no H1', array(
	has( $owned, '<div class="wpcpm-frame__bar"><button class="wpcpm-frame__menu-button"' ),
	has( $owned, '<header class="wpcpm-head"><div class="wpcpm-head__copy"><h1 class="wpcpm-head__eyebrow">Administrator Dashboard</h1><p class="wpcpm-head__title">' ),
	has( $owned_ref, '<header class="wpcpm-head"><div class="wpcpm-head__copy"><h1 class="wpcpm-head__eyebrow">Administrator Dashboard</h1><p class="wpcpm-head__title">The program</p>' ),
	array( substr_count( $owned, '<h1' ), substr_count( $owned_ref, '<h1' ) ),
	array( has( $out, '<h1' ), has( $out_ref, '<h1' ), has( $out, '<div class="wpcpm-frame__bar wpcpm-frame__bar--slim"><button class="wpcpm-frame__menu-button"' ) ),
), array( true, true, true, array( 1, 1 ), array( false, false, true ) ) );
// The page names itself once (1.123.70): the menu no longer opens with the page's name, which the head's
// H1 says (the owner, 11 November 2026: "Duplicated"). The menu's label still names it to a screen reader.
ck( 'the page names itself once: no name above the menu, on either theme, and on the bare canvas the H1 is the only place the words are shown; the menu keeps the name as its label', array(
	array( has( $out, 'wpcpm-frame__context' ), has( $owned, 'wpcpm-frame__context' ), has( $owned_ref, 'wpcpm-frame__context' ) ),
	array( substr_count( $owned, '>Administrator Dashboard<' ), substr_count( $owned_ref, '>Administrator Dashboard<' ) ),
	has( $owned, '<nav class="wpcpm-frame__menu" id="wpcpm-frame-menu" aria-label="Administrator Dashboard">' ),
), array( array( false, false, false ), array( 1, 1 ), true ) );

// A refused Draft now flashes on the semester report screen's own channel, not the queue's
// (final review, Important 2); render_messages() has to read that channel too and print it in
// the screen's own words. A fresh render(), after every check above that pins what the first
// one drew, so the extra call's own side effects (another resources audience, another pair of
// reviews) cannot make an earlier check read the wrong count.
$GLOBALS['flash'] = array( 'institution_report' => array( 'status' => 'refused' ) );
$out_report_flash = WPCPM_Administrators_Dashboard::render( array() );
ck( 'render_messages() also prints a refusal flashed on the report screen\'s channel, with its class', has( $out_report_flash, 'That is not something you can do here.' ) && has( $out_report_flash, 'wpcpm-dashboard__message--error is-error" role="alert"' ), true );
ck( 'and that channel is taken too, so it shows once', isset( $GLOBALS['flash']['institution_report'] ), false );
ck( 'with no title the head opens the page, and the refusal is said where the page\'s outcome goes when no card holds it, after the figures and before the Waiting group', array(
	has( $out_report_flash, '<div class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--overview"><header class="wpcpm-head">' ),
	has( $out_report_flash, '</section><p class="wpcpm-dashboard__message wpcpm-dashboard__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>That is not something you can do here.</p><div class="wpcpm-overview wpcpm-overview--wait">' ),
), array( true, true ) );
// A refused Draft now on the Sponsor reports card (1.115.0) comes back here on the institutions
// channel, and prints in the Sponsors screen's own words for it, in the error tone.
$GLOBALS['flash']      = array( 'institutions' => 'sreport-exists' );
$out_sreport_flash     = WPCPM_Administrators_Dashboard::render( array() );
$GLOBALS['flash']      = array( 'institutions' => 'sreport-no-tier' );
$out_sreport_flash_two = WPCPM_Administrators_Dashboard::render( array() );
ck( 'a refused Draft now prints the Sponsors screen\'s sentence for it, in the error tone, and a sentence that holds here too: this page has no Reports card to send a manager to', array(
	has( $out_sreport_flash, '<p class="wpcpm-dashboard__message wpcpm-dashboard__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>' . esc_html( WPCPM_Sponsor_Report_Screen::messages()['sreport-exists'][1] ) . '</p>' ),
	has( $out_sreport_flash_two, '<p class="wpcpm-dashboard__message wpcpm-dashboard__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>' . esc_html( WPCPM_Sponsor_Report_Screen::messages()['sreport-no-tier'][1] ) . '</p>' ),
	has( $out_sreport_flash, 'Reports card' ),
), array( true, true, false ) );

echo "\n=== Three groups, and each card's row ===\n";

/**
 * The groups a page prints, by id and in the order it prints them, each with the anchors of the
 * cards drawn inside it, in order.
 *
 * @param string $html The page.
 * @return array<string, string[]>
 */
function page_groups( $html ) {
	$groups = array();
	foreach ( array_slice( explode( '<div class="wpcpm-administrator__group wpcpm-administrator__group--', (string) $html ), 1 ) as $part ) {
		preg_match_all( '/<section class="wpcpm-administrator__card" id="wpcpm-([a-z-]+)">/', $part, $cards );
		$groups[ (string) strstr( $part, '"', true ) ] = $cards[1];
	}
	return $groups;
}

/**
 * A card's row, as the card's renderer drew it: whether its disclosure is drawn open, the phrase in
 * its summary, and the action after its fold, each null when it is not there. The card's own
 * summary is the first to close in its markup and its own disclosure the last.
 *
 * @param string $card One card's markup.
 * @return array{open: bool|null, state: string|null, action: string|null}
 */
function card_row( $card ) {
	$card    = (string) $card;
	$summary = (string) strstr( $card, '</summary>', true );
	$tail    = substr( $card, (int) strrpos( $card, '</details>' ) );
	return array(
		'open'   => 1 === preg_match( '#^<section class="wpcpm-administrator__card" id="wpcpm-[a-z-]+"><details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure"( open)?><summary #', $card, $shape ) ? ! empty( $shape[1] ) : null,
		// A no-break space in a phrase's date (Task 18f, fix round 2) read as a space; the dates' wholeness has its own check.
		'state'  => 1 === preg_match( '#<span class="wpcpm-sponsor__state">(.*?)</span>#', $summary, $state ) ? str_replace( "\u{00A0}", ' ', $state[1] ) : null,
		'action' => 1 === preg_match( '#^</details><p class="wpcpm-sponsor__row-action">(.*)</p></section>$#s', $tail, $action ) ? $action[1] : null,
	);
}

/**
 * The number beside a card's title, as the card's renderer drew it, or null when it has none.
 *
 * @param string $card One card's markup.
 * @return int|null
 */
function card_badge( $card ) {
	$summary = (string) strstr( (string) $card, '</summary>', true );
	return 1 === preg_match( '#<span class="wpcpm-group__count">([0-9,]+)</span>#', $summary, $badge ) ? (int) str_replace( ',', '', $badge[1] ) : null;
}

/**
 * Each card's badge and phrase on a page, by anchor, each null when the card has none.
 *
 * @param string $html The page.
 * @return array[] Anchor => `badge`, `phrase`.
 */
function page_rows( $html ) {
	$rows = array();
	foreach ( array_slice( explode( '<section class="wpcpm-administrator__card" id="wpcpm-', (string) $html ), 1 ) as $part ) {
		$rows[ (string) strstr( $part, '"', true ) ] = array(
			'badge'  => card_badge( $part ),
			'phrase' => 1 === preg_match( '#<span class="wpcpm-sponsor__state">(.*?)</span>#', (string) strstr( $part, '</summary>', true ), $phrase ) ? $phrase[1] : null,
		);
	}
	return $rows;
}

/**
 * Each card's row, drawn by its own renderer.
 *
 * @param callable[] $draws Anchor => what draws the card.
 * @return array[] Anchor => card_row().
 */
function rows_of( array $draws ) {
	return array_map( static function ( $draw ) { return card_row( capture( $draw ) ); }, $draws );
}

/**
 * A plugin stylesheet's rules, each selector's declarations keyed `<media query>|<selector>`, the
 * query '' for a rule outside any, with comments dropped and white space folded to one space: the
 * Sponsor Dashboard's suite reads sponsor.css this way.
 *
 * @param string $sheet The file under assets/css/.
 * @return array<string, string>
 */
function sheet_rules( $sheet ) {
	$css   = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/' . $sheet ) );
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
			// A container query holds rules as a media query does; its name and condition lead its keys.
			if ( 1 === preg_match( '/^[@]container\s*(.*)$/', $selectors, $query ) ) {
				$media = '@' . 'container ' . trim( $query[1] );
				continue;
			}
			// So does a feature query (Task 18f fix round 1: the tiles' subgrid); its condition leads its keys.
			if ( 1 === preg_match( '/^[@]supports\s*(.*)$/', $selectors, $query ) ) {
				$media = '@' . 'supports ' . trim( $query[1] );
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

// The groups the owner approved on 28 September 2026, laid out as the version 3 concepts have them
// (1.123.69): the Overview, with every queue holding something and Sponsor tiers off, is two columns
// under the head and the figures, Waiting for you beside Watch; the Reference view is the program's
// numbers, then the Tools section and the resources under their heading for a screen reader.
ck( 'the Overview is Waiting for you under the head and the figures, and Watch its own view (1.123.71), each holding its cards in the approved order; The program is the third group, with the Tools section below it', array(
	page_groups( $out_full ),
	page_groups( $out_ref ),
	before_in( $out_full, '<section class="wpcpm-figures', '<div class="wpcpm-overview wpcpm-overview--wait">' ),
	has( $out_full, '<div class="wpcpm-overview wpcpm-overview--wait"><div class="wpcpm-administrator__group wpcpm-administrator__group--waiting">' ),
	has( $out_full, '<div class="wpcpm-overview wpcpm-overview--watch" id="wpcpm-watch"><div class="wpcpm-administrator__group wpcpm-administrator__group--watch">' ),
	has( $out_ref, '</section></div></div><h2 class="screen-reader-text">From our sponsors</h2><section class="wpcpm-student__section wpcpm-tools" id="wpcpm-tools">' ),
	false === strpos( $out_ref, 'wpcpm-handbook__resources' ),
), array(
	array(
		'waiting' => array( 'applications', 'agreements', 'reports', 'requests', 'graduation', 'sponsor-applications', 'sponsor-posts', 'sponsor-offers-review', 'sponsor-agreements', 'sponsor-reports' ),
		'watch'   => array( 'offers-low', 'duplicates', 'interests', 'tiers-ending', 'health' ),
	),
	array(
		'reference' => array( 'sponsors', 'programs', 'mentor-engagement' ),
	),
	true,
	true,
	true,
	true,
	true,
) );
// One row a card on the Overview, so each column reads as a list (the concepts' inbox): every card is
// drawn folded, whatever its own rule asks. A queue's row ends with the Review cue, the words the toggle
// shows, and says how long the oldest of its items has waited, as the homepage's red status tag, when
// the rows say when they came in; the sponsor reports' rows wait from the day the term ended (the release's
// review, 1.123.69). A card to watch has neither.
$full_marks   = row_marks( $out_full );
$full_groups  = page_groups( $out_full );
$marks_of     = static function ( array $anchors, $key ) use ( $full_marks ) {
	$of = array();
	foreach ( $anchors as $anchor ) {
		$of[ $anchor ] = isset( $full_marks[ $anchor ] ) ? $full_marks[ $anchor ][ $key ] : 'not drawn';
	}
	return $of;
};
// The student waiting to graduate on this page has no account, so no moment: that queue's row has no age (1.123.93;
// its age is pinned with the card's own checks).
$dated_queues = array_values( array_diff( $full_groups['waiting'], array( 'graduation' ) ) );
ck( 'on the Overview every card is drawn folded, one row each: each queue\'s with the Review cue and, where its rows say when they came in, how long the oldest has waited, as a red tag, the semester reports as long as the head says; each card to watch with neither', array(
	array_values( array_unique( array_column( $full_marks, 'open' ) ) ),
	array_values( array_unique( $marks_of( $full_groups['waiting'], 'cue' ) ) ),
	array_values( array_unique( $marks_of( $full_groups['watch'], 'cue' ) ) ),
	array_keys( array_filter( $marks_of( $dated_queues, 'age' ), static function ( $age ) { return ! is_string( $age ) || 1 !== preg_match( '/^(?:[0-9]+ days? waiting|Waiting since today)$/', $age ); } ) ),
	array( $full_marks['reports']['age'], $full_marks['sponsor-posts']['age'], $full_marks['sponsor-offers-review']['age'], 1 === preg_match( '/^[0-9]+ days? waiting$/', (string) $full_marks['sponsor-reports']['age'] ) ),
	array_values( array_unique( $marks_of( $full_groups['watch'], 'age' ) ) ),
), array(
	array( false ),
	array( 'Review' ),
	array( null ),
	array(),
	array( $reports_days . ' days waiting', 'Waiting since today', 'Waiting since today', true ),
	array( null ),
) );
// The mentor applications are decided on the Mentors screen, so they have no card: a row of their own in
// the Waiting group, between the institutions' queues and the sponsors', as their count stood between
// those tiles, saying how many wait, and a Review that opens their screen.
// The row's markup on the parts is pinned with the mentor row's parts checks; its old markup left (Task 18b).
ck( 'the mentor applications are a row in the Waiting group, between the requests from institutions and the sponsor applications, opening the Mentors screen\'s queue', array(
	has( $out_full, 'href="https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue"' ),
	in_order( $out_full, array( 'id="wpcpm-requests"', 'id="wpcpm-mentor-applications"', 'id="wpcpm-sponsor-applications"' ) ),
	substr_count( $out_full, 'id="wpcpm-mentor-applications"' ),
), array( true, true, 1 ) );
// A queue with nothing in it is not drawn as a card: one sentence after the Waiting group's cards names
// every empty queue once, each name a link to its screen in wp-admin, so nothing seems to have gone.
// Drawn here with the mentor applications and the sponsors' queues empty, and Sponsor tiers on, so the
// Events card is a queue too; then with every queue holding something. The Events card is drawn though no
// event waits, for the events coming up and the plan, and is not named in the sentence (1.123.93).
$kept_quiet = array( $GLOBALS['sponsor_posts'], $GLOBALS['agr_review'], $GLOBALS['sponsor_apps'], $GLOBALS['mentor_waiting'], isset( $GLOBALS['tiers_live'] ) ? $GLOBALS['tiers_live'] : null );
$GLOBALS['sponsor_posts']  = array();
$GLOBALS['agr_review']     = array();
$GLOBALS['sponsor_apps']   = array();
$GLOBALS['mentor_waiting'] = 0;
$GLOBALS['tiers_live']     = true;
$quiet_page                = WPCPM_Administrators_Dashboard::render( array() );
list( $GLOBALS['sponsor_posts'], $GLOBALS['agr_review'], $GLOBALS['sponsor_apps'], $GLOBALS['mentor_waiting'], $GLOBALS['tiers_live'] ) = $kept_quiet;
$sponsors_queue = static function ( $card ) {
	return tab_url( 'wpcpm-sponsors', 'queue', 'wpcpm-' . $card );
};
ck( 'a queue with nothing in it is not drawn: one sentence after the Waiting group\'s cards names every empty queue once, in the queues\' order, each name a link to its screen in wp-admin; the Events card is drawn and not named', array(
	quiet_links( $quiet_page ),
	array_values( array_intersect( page_cards( $quiet_page ), array( 'mentor-applications', 'sponsor-applications', 'sponsor-posts', 'sponsor-offers-review', 'sponsor-events', 'sponsor-agreements' ) ) ),
	has( $quiet_page, 'id="wpcpm-mentor-applications"' ),
	substr_count( $quiet_page, '<p class="wpcpm-ui-group__note">' ),
	has( $quiet_page, '</section><p class="wpcpm-ui-group__note">Nothing waits in the 6 other queues: <a href="https://example.test/wp-admin/admin.php?page=wpcpm-students&wpcpm_student_status=Pending%20graduation">Students waiting to graduate</a>, <a href="https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue">Mentor applications</a>, <a href="' . $sponsors_queue( 'sponsor-applications' ) . '">Sponsor applications</a>, ' ),
	has( $quiet_page, ', and <a href="' . $sponsors_queue( 'sponsor-agreements' ) . '">Sponsor Collaboration Agreements</a>. Each name opens its screen in wp-admin.</p></div></div></div>' ),
), array(
	array(
		// Nobody waits to graduate on this page (1.123.93): the queue is named, before the mentor applications.
		'Students waiting to graduate'     => 'https://example.test/wp-admin/admin.php?page=wpcpm-students&wpcpm_student_status=Pending%20graduation',
		'Mentor applications'              => 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue',
		'Sponsor applications'             => $sponsors_queue( 'sponsor-applications' ),
		'Sponsor posts to review'          => $sponsors_queue( 'sponsor-posts' ),
		'Sponsor offers to review'         => $sponsors_queue( 'sponsor-offers-review' ),
		'Sponsor Collaboration Agreements' => $sponsors_queue( 'sponsor-agreements' ),
	),
	array( 'sponsor-events' ),
	false,
	1,
	true,
	true,
) );
ck( 'and the queues that hold something are drawn as before, in their order, the Events card at its place among them, the head counting what waits alone', array(
	page_groups( $quiet_page )['waiting'],
	has( $quiet_page, '<p class="wpcpm-head__title">9 decisions wait for you</p>' ),
), array( array( 'applications', 'agreements', 'reports', 'requests', 'sponsor-events', 'sponsor-reports' ), true ) );
// The Students gone quiet card (student automation): the Watch group's last, after Syncs and health,
// drawn only while the switch is not off.
$GLOBALS['opts'][ WPCPM_Student_Flags::OPT_SNAPSHOT ] = $q_snapshot;
$GLOBALS['student_page']                              = 'https://example.test/student-report-card/';
$GLOBALS['flags_mode'] = 'preview';
$GLOBALS['uid']        = 3;
$q_page   = both_views();
$q_counts = WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() );
ck( 'on the page the card is the Watch group\'s last, after Syncs and health; its count leads to it, and the menu\'s Watch badge counts it among the counts to watch above zero', array(
	page_groups( $q_page )['watch'],
	isset( $q_counts['quiet_students']['card'] ) ? $q_counts['quiet_students']['card'] : null,
	menu_items( $q_page )['Watch']['badge'] ?? null,
), array( array( 'offers-low', 'duplicates', 'interests', 'tiers-ending', 'health', 'quiet-students' ), 'quiet-students', '4' ) );
$GLOBALS['flags_mode'] = 'off';
$q_off                 = both_views();
ck( 'with the switch off the page draws no such card, counts() gives it no count, and the Watch badge is one fewer', array(
	has( $q_off, 'wpcpm-quiet-students' ),
	array_key_exists( 'quiet_students', WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() ) ),
	menu_items( $q_off )['Watch']['badge'] ?? null,
), array( false, false, '3' ) );
unset( $GLOBALS['flags_mode'], $GLOBALS['student_page'], $GLOBALS['opts'][ WPCPM_Student_Flags::OPT_SNAPSHOT ] );
// The groups' headings are h2, so the Tools section's h3 would read as the Reference group's in an
// outline: it sits under an h2 of its own, for a screen reader alone, printed only over it. The
// Resources column that followed it is gone (1.123.71, the owner: "Not needed, duplicated"): its links
// are the menu's, its updates the menu's Program updates, and the page asks for none.
$GLOBALS['tools_quiet'] = true;
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_REFERENCE;
$out_nothing_after = WPCPM_Administrators_Dashboard::render( array() );
unset( $GLOBALS['tools_quiet'], $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] );
// With nothing after the cards the view ends with its last card, its group, the Reference view's box
// and the page's wrapper, then the frame's main area, its body and the frame.
$frame_close = '</div></div></div>';
ck( 'on the Reference view the Tools section sits under a heading for a screen reader, From our sponsors, printed once and only over it; no Resources column, and the page asks for none; the Overview prints neither', array(
	substr_count( $out_ref, '<h2 class="screen-reader-text">From our sponsors</h2>' ),
	has( $out_nothing_after, 'From our sponsors' ),
	ends_with( $out_nothing_after, '</section></div></div></div>' . $frame_close ),
	array( has( $out_ref, 'wpcpm-handbook__resources' ), in_array( 'administrator', $GLOBALS['resources'] ?? array(), true ) ),
	array( has( $out, 'From our sponsors' ), has( $out, 'id="wpcpm-tools"' ) ),
), array( 1, false, true, array( false, false ), array( false, false ) ) );

/**
 * The cards a page draws, in the order it draws them.
 *
 * @param string $html The page.
 * @return string[]
 */
function page_cards( $html ) {
	preg_match_all( '/<section class="wpcpm-administrator__card" id="wpcpm-([a-z-]+)">/', (string) $html, $cards );
	return $cards[1];
}
// The strip is no longer drawn on this page; the counts its tiles printed are what the head and the menu
// read, and the Sponsor Dashboard's At a glance strip still wears them. They read the Overview top to
// bottom: each count that names a card names one the Overview draws, in the order it draws the cards.
// One count a card, but Syncs and health's two: the syncs that need a look and the locked accounts.
ck( 'every count counts() gives leads to a card the Overview or Watch draws, in the order they draw the cards, one count a card but Syncs and health, which has two', array(
	count_cards( $out_counts ),
	array_values( array_diff( count_cards( $out_counts ), page_cards( $out . $out_watch ) ) ),
	array_values( array_intersect( page_cards( $out . $out_watch ), count_cards( $out_counts ) ) ) === array_values( array_unique( count_cards( $out_counts ) ) ),
	array_keys( array_filter( array_count_values( count_cards( $out_counts ) ), static function ( $tiles ) { return $tiles > 1; } ) ),
), array(
	array( 'applications', 'agreements', 'reports', 'requests', 'sponsor-applications', 'sponsor-posts', 'sponsor-agreements', 'sponsor-reports', 'offers-low', 'duplicates', 'health', 'health' ),
	array(),
	true,
	array( 'health' ),
) );
// Each group opens with its wrapper, its heading and the one sentence that says what its cards
// hold, in the shape the Sponsor Dashboard prints its groups in, with this page's classes.
// esc_html() writes each apostrophe as an entity.
$group_opening = static function ( $id, $title, $lead, $first ) {
	return '<div class="wpcpm-administrator__group wpcpm-administrator__group--' . $id . '"><h2 class="wpcpm-student__heading wpcpm-administrator__group-title">' . $title . '</h2><p class="wpcpm-student__note wpcpm-administrator__group-lead">' . $lead . '</p><section class="wpcpm-administrator__card" id="wpcpm-' . $first . '">';
};
// The Waiting group closes after the sentence that names its empty queues, which follows its cards, then
// its box and the page's wrapper, then the frame; on its own view since 1.123.71, the Watch group, This
// week, closes right after its last card, then its box and the page's wrapper; The program's group right
// after its last card, then its view's box, then the heading over the Tools section.
ck( 'each group opens with its heading and its one sentence, its first card right after them, and closes right after its last card, the Waiting group after the sentence that names its empty queues', array(
	has( $out, $group_opening( 'waiting', 'Waiting for you', 'What needs an Administrator&#039;s decision, oldest first in each.', 'applications' ) ),
	has( $out_watch, $group_opening( 'watch', 'This week', 'Each card is one row, folded until you open it.', 'offers-low' ) ),
	has( $out_ref, $group_opening( 'reference', 'The program&#039;s numbers', 'Read from the site and Airtable.', 'sponsors' ) ),
	1 === preg_match( '#</section><p class="wpcpm-ui-group__note">(?:(?!</p>).)*</p></div></div></div>' . preg_quote( $frame_close, '#' ) . '$#s', $out ),
	ends_with( $out_watch, '</section></div></div></div>' . $frame_close ),
	has( $out_ref, '</section></div></div><h2 class="screen-reader-text">From our sponsors</h2>' ),
	array( substr_count( $out, '<p class="wpcpm-student__note wpcpm-administrator__group-lead">' ), substr_count( $out_watch, '<p class="wpcpm-student__note wpcpm-administrator__group-lead">' ), substr_count( $out_ref, '<p class="wpcpm-student__note wpcpm-administrator__group-lead">' ) ),
), array( true, true, true, true, true, true, array( 1, 1, 1 ) ) );
// Every card of this page draws something, so no real group here is ever empty: a group whose cards
// print nothing, or nothing but white space, is not printed at all by the dashboards' group printer,
// which this page draws its groups through, and that rule is pinned where the printer lives, in
// bin/test-dashboard-card.php, with this page's classes.

// The row actions, each a link to the tab of the wp-admin screen that holds the card's items, at the
// card's own place on that tab where it has one, in the words "Open on the Institutions screen" or
// "Open on the Sponsors screen"; the duplicated students' to the finder, in its own words.
$to_institutions = static function ( $tab ) {
	return '<a class="wpcpm-button wpcpm-button--secondary" href="' . tab_url( 'wpcpm-institutions', $tab ) . '">Open on the Institutions screen</a>';
};
$to_sponsors     = static function ( $tab, $anchor = '' ) {
	return '<a class="wpcpm-button wpcpm-button--secondary" href="' . tab_url( 'wpcpm-sponsors', $tab, $anchor ) . '">Open on the Sponsors screen</a>';
};
$to_finder       = '<a class="wpcpm-button wpcpm-button--secondary" href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder">Open the Student Duplicate Finder</a>';

// Every card waiting for a decision has a home in wp-admin: the Institutions screen's queue tab holds
// the institutions' applications, agreements and requests and its Semester reports tab their reports;
// the Sponsors screen's queue tab holds the sponsors' applications, posts and agreements, each in a
// card of its own there, and its Reports tab their reports.
ck( 'a card waiting for a decision is open while it holds something and folded with nothing, its row saying how many wait, and links after the fold to the tab of its screen that holds its items, at its own card there where it has one', array(
	rows_of( array(
		'applications'         => static function () use ( $data ) { WPCPM_Administrators_Cards::render_applications( $data['applications'] ); },
		'agreements'           => static function () use ( $data ) { WPCPM_Administrators_Cards::render_agreements( $data['agreements'] ); },
		'reports'              => static function () use ( $data ) { WPCPM_Administrators_Cards::render_reports( $data['reports'] ); },
		'requests'             => static function () use ( $data ) { WPCPM_Administrators_Cards::render_requests( $data['requests'] ); },
		'sponsor-applications' => static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsor_applications( $data['sponsor_applications'] ); },
		'sponsor-posts'        => static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsor_posts( $data['sponsor_posts'] ); },
		'sponsor-agreements'   => static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsor_agreements( $data['sponsor_agreements'] ); },
		'sponsor-reports'      => static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsor_reports( $data['sponsor_reports'] ); },
	) ),
	rows_of( array(
		'applications'         => static function () { WPCPM_Administrators_Cards::render_applications( array() ); },
		'agreements'           => static function () { WPCPM_Administrators_Cards::render_agreements( array() ); },
		'reports'              => static function () { WPCPM_Administrators_Cards::render_reports( array() ); },
		'requests'             => static function () { WPCPM_Administrators_Cards::render_requests( array() ); },
		'sponsor-applications' => static function () { WPCPM_Administrators_Cards::render_sponsor_applications( array() ); },
		'sponsor-posts'        => static function () { WPCPM_Administrators_Cards::render_sponsor_posts( array() ); },
		'sponsor-agreements'   => static function () { WPCPM_Administrators_Cards::render_sponsor_agreements( array() ); },
		'sponsor-reports'      => static function () { WPCPM_Administrators_Cards::render_sponsor_reports( array() ); },
	) ),
), array(
	array(
		'applications'         => array( 'open' => true, 'state' => '2 waiting', 'action' => $to_institutions( 'queue' ) ),
		'agreements'           => array( 'open' => true, 'state' => '2 waiting', 'action' => $to_institutions( 'queue' ) ),
		'reports'              => array( 'open' => true, 'state' => '2 waiting', 'action' => $to_institutions( 'reports' ) ),
		'requests'             => array( 'open' => true, 'state' => '2 waiting', 'action' => $to_institutions( 'queue' ) ),
		'sponsor-applications' => array( 'open' => true, 'state' => '1 waiting', 'action' => $to_sponsors( 'queue', 'wpcpm-sponsor-applications' ) ),
		'sponsor-posts'        => array( 'open' => true, 'state' => '1 waiting', 'action' => $to_sponsors( 'queue', 'wpcpm-sponsor-posts' ) ),
		'sponsor-agreements'   => array( 'open' => true, 'state' => '1 waiting', 'action' => $to_sponsors( 'queue', 'wpcpm-sponsor-agreements' ) ),
		'sponsor-reports'      => array( 'open' => true, 'state' => '2 waiting', 'action' => $to_sponsors( 'reports' ) ),
	),
	array(
		'applications'         => array( 'open' => false, 'state' => '0 waiting', 'action' => $to_institutions( 'queue' ) ),
		'agreements'           => array( 'open' => false, 'state' => '0 waiting', 'action' => $to_institutions( 'queue' ) ),
		'reports'              => array( 'open' => false, 'state' => '0 waiting', 'action' => $to_institutions( 'reports' ) ),
		'requests'             => array( 'open' => false, 'state' => '0 waiting', 'action' => $to_institutions( 'queue' ) ),
		'sponsor-applications' => array( 'open' => false, 'state' => '0 waiting', 'action' => $to_sponsors( 'queue', 'wpcpm-sponsor-applications' ) ),
		'sponsor-posts'        => array( 'open' => false, 'state' => '0 waiting', 'action' => $to_sponsors( 'queue', 'wpcpm-sponsor-posts' ) ),
		'sponsor-agreements'   => array( 'open' => false, 'state' => '0 waiting', 'action' => $to_sponsors( 'queue', 'wpcpm-sponsor-agreements' ) ),
		'sponsor-reports'      => array( 'open' => false, 'state' => '0 waiting', 'action' => $to_sponsors( 'reports' ) ),
	),
) );
/**
 * What a card's body ends with, when it is a link line in the shape of the line that says how many
 * more there are under a cut list: the link's address and its words; null for any other last element.
 *
 * @param string $card One card's markup.
 * @return string[]|null
 */
function body_foot( $card ) {
	$card = (string) $card;
	$end  = strrpos( $card, '</div></details>' );
	if ( false === $end ) {
		return null;
	}
	// The fold's foot (`WPCPM_UI::fold_foot()`), since the cards' old branches left (Task 18b).
	return 1 === preg_match( '#<p class="wpcpm-ui-fold__foot"><a href="([^"]*)">([^<]*)</a></p>$#', substr( $card, 0, $end ), $line ) ? array( $line[1], $line[2] ) : null;
}
/**
 * What a card's body ends with, when it is a button in the card's own actions block, as the
 * Duplicated students card's body ends with its button to the finder: the button's address and its
 * words; null for any other last element.
 *
 * @param string $card One card's markup.
 * @return string[]|null
 */
function body_button( $card ) {
	$card = (string) $card;
	$end  = strrpos( $card, '</div></details>' );
	if ( false === $end ) {
		return null;
	}
	return 1 === preg_match( '#<div class="wpcpm-administrator__actions"><a class="wpcpm-button wpcpm-button--secondary" href="([^"]*)">([^<]*)</a></div>$#', substr( $card, 0, $end ), $button ) ? array( $button[1], $button[2] ) : null;
}
// Open, a card's row action is hidden, so each card whose work has a home in wp-admin ends its body with
// the same link as a line, in its row action's words and to its address, on every draw, an empty card's
// too: the thirteen such cards are pinned below, the institution cards at the Institutions screen's tabs
// and the sponsor cards at the Sponsors screen's, and Mentor engagement, at the Mentors screen's
// Engagement tab, with its own card further up. The cards whose work has no one home there end with
// what they hold and print no such line: Programs running, and Syncs and health, whose rows each link
// their own sync's screen. Duplicated students, whose home is the finder, ends with its own button
// there, to the finder's address and in the finder's words, and with no line.
// Duplicated students ends with the fold's foot to the finder since its old branch, and its button, left (Task 18b).
ck( 'a card with no one home in wp-admin ends its body with what it holds and no line to a screen: Programs running, and Syncs and health, whose rows each link their own sync\'s screen; and Duplicated students ends with its line to the finder', array_map( static function ( $draw ) { $card = capture( $draw ); return array( body_foot( $card ), body_button( $card ) ); }, array(
	'programs'   => static function () use ( $data ) { WPCPM_Administrators_Cards::render_programs( $data['programs'] ); },
	'health'     => static function () use ( $data ) { WPCPM_Administrators_Cards::render_health( $data['health'], $data['locked'] ); },
	'duplicates' => static function () use ( $data ) { WPCPM_Administrators_Cards::render_duplicates( $data['duplicates'] ); },
) ), array( 'programs' => array( null, null ), 'health' => array( null, null ), 'duplicates' => array( array( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder', 'Open the Student Duplicate Finder' ), null ) ) );
// Five open applications of which this page drew the two it holds: the line that says how many more
// are waiting counts that list, so it sits right under the last of them, before the rejected and spam
// ones, where it reads as that list's. It links nowhere, since the Institutions screen's queue lists
// the same window of the oldest; the card's way to that queue is its last line, after the fold of the
// rejected and spam ones, as on every draw, and the row action after its fold says the same.
// The day the Student Duplicate Finder last scanned, as the site writes days.
$dup_day = wp_date( get_option( 'date_format' ), 1757000000 );
// Each sync's own Open link, in the order the card lists the syncs.
// Each sync's Open is the quiet row action since the card's old branch left (Task 18b).
preg_match_all( '#<a class="wpcpm-btn wpcpm-btn--quiet" href="([^"]*)">Open<#', capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_health( $data['health'], $data['locked'] ); } ), $sync_links );
ck( 'a card to watch is folded even while it holds something, its row saying what it holds: the offers under their threshold, the duplicated students as of the day of the last scan, the new interests and the terms ending, each with the tab or the screen that holds it, and the syncs that need a look, with none, since each sync\'s own row links its own screen at the tab where that sync is run', array(
	rows_of( array(
		'offers-low'   => static function () use ( $data ) { WPCPM_Administrators_Cards::render_offers_low( $data['offers_low'] ); },
		'duplicates'   => static function () use ( $data ) { WPCPM_Administrators_Cards::render_duplicates( $data['duplicates'] ); },
		'interests'    => static function () use ( $data ) { WPCPM_Administrators_Cards::render_interests( $data['interests'] ); },
		'tiers-ending' => static function () use ( $data ) { WPCPM_Administrators_Cards::render_tiers_ending( $data['tiers_ending'] ); },
		'health'       => static function () use ( $data ) { WPCPM_Administrators_Cards::render_health( $data['health'], $data['locked'] ); },
	) ),
	$sync_links[1],
), array(
	array(
		'offers-low'   => array( 'open' => false, 'state' => '1 offer under its threshold', 'action' => $to_sponsors( 'offers' ) ),
		'duplicates'   => array( 'open' => false, 'state' => '3 duplicated students as of ' . $dup_day, 'action' => $to_finder ),
		'interests'    => array( 'open' => false, 'state' => '1 new interest in the last 30 days', 'action' => $to_sponsors( 'interests' ) ),
		'tiers-ending' => array( 'open' => false, 'state' => '1 term ending within 60 days', 'action' => $to_sponsors( 'sponsors' ) ),
		'health'       => array( 'open' => false, 'state' => '3 syncs need a look', 'action' => null ),
	),
	array( tab_url( 'wpcpm-students', 'sync' ), tab_url( 'wpcpm-mentors', 'sync' ), tab_url( 'wpcpm-institutions', 'sync' ) ),
) );
ck( 'and each phrase counts in the plural or the singular as its number asks, zero included', array(
	rows_of( array(
		'offers-low'   => static function () use ( $data ) { WPCPM_Administrators_Cards::render_offers_low( array( $data['offers_low'][0], $data['offers_low'][0] ) ); },
		'duplicates'   => static function () use ( $data ) { WPCPM_Administrators_Cards::render_duplicates( array_merge( $data['duplicates'], array( 'addresses' => 1, 'ready' => 0 ) ) ); },
		'interests'    => static function () use ( $data ) { WPCPM_Administrators_Cards::render_interests( array( $data['interests'][0], $data['interests'][0] ) ); },
		'tiers-ending' => static function () use ( $data ) { WPCPM_Administrators_Cards::render_tiers_ending( array( $data['tiers_ending'][0], $data['tiers_ending'][0] ) ); },
	) ),
	rows_of( array(
		'offers-low'   => static function () { WPCPM_Administrators_Cards::render_offers_low( array() ); },
		'duplicates'   => static function () use ( $data ) { WPCPM_Administrators_Cards::render_duplicates( array_merge( $data['duplicates'], array( 'addresses' => 0, 'ready' => 0 ) ) ); },
		'interests'    => static function () { WPCPM_Administrators_Cards::render_interests( array() ); },
		'tiers-ending' => static function () { WPCPM_Administrators_Cards::render_tiers_ending( array() ); },
	) ),
), array(
	array(
		'offers-low'   => array( 'open' => false, 'state' => '2 offers under their threshold', 'action' => $to_sponsors( 'offers' ) ),
		'duplicates'   => array( 'open' => false, 'state' => '1 duplicated student as of ' . $dup_day, 'action' => $to_finder ),
		'interests'    => array( 'open' => false, 'state' => '2 new interests in the last 30 days', 'action' => $to_sponsors( 'interests' ) ),
		'tiers-ending' => array( 'open' => false, 'state' => '2 terms ending within 60 days', 'action' => $to_sponsors( 'sponsors' ) ),
	),
	array(
		'offers-low'   => array( 'open' => false, 'state' => '0 offers under their threshold', 'action' => $to_sponsors( 'offers' ) ),
		'duplicates'   => array( 'open' => false, 'state' => '0 duplicated students as of ' . $dup_day, 'action' => $to_finder ),
		'interests'    => array( 'open' => false, 'state' => '0 new interests in the last 30 days', 'action' => $to_sponsors( 'interests' ) ),
		'tiers-ending' => array( 'open' => false, 'state' => '0 terms ending within 60 days', 'action' => $to_sponsors( 'sponsors' ) ),
	),
) );
// Before the first scan there is no day to say the count as of, and no finder address the card was
// handed: the row says nothing rather than a phrase with a hole in it, and offers no link to
// nowhere.
ck( 'a finder that has not scanned yet leaves the row with no phrase, and a card handed no finder address offers no link', array(
	card_row( capture( static function () { WPCPM_Administrators_Cards::render_duplicates( array() ); } ) ),
	card_row( capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_duplicates( array_merge( $data['duplicates'], array( 'url' => '' ) ) ); } ) ),
), array(
	array( 'open' => false, 'state' => null, 'action' => null ),
	array( 'open' => false, 'state' => '3 duplicated students as of ' . $dup_day, 'action' => null ),
) );

/**
 * The Syncs and health card's badge and phrase for some syncs, each given its last run and the
 * error it left, with Automatic sync on or off.
 *
 * @param array[] $runs `last` and `error` per sync.
 * @param bool    $auto Whether Automatic sync is on.
 * @return array The badge, and the phrase or null.
 */
function syncs_row_of( array $runs, $auto ) {
	$syncs = array();
	foreach ( $runs as $i => $run ) {
		$syncs[ 'sync' . $i ] = array( 'label' => 'Sync ' . $i, 'progress' => array( 'running' => false, 'phase' => 'done', 'label' => 'Done', 'error' => $run['error'], 'elapsed' => 0 ), 'last' => $run['last'], 'next' => 0, 'screen' => 'https://example.test/wp-admin/admin.php?page=wpcpm-students' );
	}
	$card = capture( static function () use ( $syncs, $auto ) { WPCPM_Administrators_Cards::render_health( array( 'syncs' => $syncs, 'auto_sync' => $auto ), array() ); } );
	return array( card_badge( $card ), card_row( $card )['state'] );
}
// A run that finished some hours ago, with the error it left.
$ran = static function ( $hours, $error = '' ) {
	return array( 'last' => time() - (int) round( $hours * HOUR_IN_SECONDS ), 'error' => $error );
};
$never = array( 'last' => 0, 'error' => '' );
ck( 'while Automatic sync is on, a sync needs a look when its last run left an error or no run of it finished in the last six hours, each counted once, the badge the count and the row saying so, or that every sync ran in the last six hours', array(
	syncs_row_of( array( $ran( 5 ), $ran( 5 ), $ran( 5 ) ), true ),
	syncs_row_of( array( $ran( 5 ), $ran( 7 ), $ran( 5 ) ), true ),
	syncs_row_of( array( $ran( 5 ), $ran( 5 ), $ran( 1, 'HTTP 429 from Airtable' ) ), true ),
	syncs_row_of( array( $never, $ran( 7 ), $ran( 7, 'HTTP 429 from Airtable' ) ), true ),
), array(
	array( 0, 'Every audience&#039;s sync ran in the last six hours' ),
	array( 1, '1 sync needs a look' ),
	array( 1, '1 sync needs a look' ),
	array( 3, '3 syncs need a look' ),
) );
ck( 'while it is off, the runs are a manager\'s own and only an error needs a look: the row says Automatic sync is off, and how many syncs report an error when any does', array(
	syncs_row_of( array( $ran( 24 * 30 ), $never, $ran( 5 ) ), false ),
	syncs_row_of( array( $ran( 24 * 30 ), $ran( 5, 'HTTP 429 from Airtable' ), $ran( 5 ) ), false ),
	syncs_row_of( array( $ran( 1, 'HTTP 500' ), $ran( 9, 'HTTP 429 from Airtable' ), $ran( 7 ) ), false ),
), array(
	array( 0, 'Automatic sync is off' ),
	array( 1, 'Automatic sync is off; 1 sync reports an error' ),
	array( 2, 'Automatic sync is off; 2 syncs report an error' ),
) );
ck( 'with no sync to speak of, the row says nothing and counts none, the switch on or off', array( syncs_row_of( array(), true ), syncs_row_of( array(), false ) ), array( array( 0, null ), array( 0, null ) ) );
// The per-sync rule the row applies, as a predicate of its own (1.123.30): a sync needs a look when its last
// run left an error, or, while Automatic sync is on, no run of it finished within the window. Claude access
// answers the same question per sync, and a sync's answer there and the row's count here cannot differ.
$sync_of = static function ( array $run, $running = false ) {
	return array( 'label' => 'Sync', 'progress' => array( 'running' => $running, 'phase' => $running ? 'reading' : 'done', 'label' => '', 'error' => $run['error'], 'elapsed' => 0 ), 'last' => $run['last'], 'next' => 0, 'screen' => 'https://example.test/wp-admin/admin.php?page=wpcpm-students' );
};
$needs = static function ( array $run, $auto, $running = false ) use ( $sync_of ) {
	return WPCPM_Administrators_Cards::sync_needs_look( $sync_of( $run, $running ), $auto );
};
ck( 'the predicate: a sync that never ran needs a look while Automatic sync is on, and none while it is off', array( $needs( $never, true ), $needs( $never, false ) ), array( true, false ) );
ck( 'a sync that finished a run within the six hours needs none, on or off', array( $needs( $ran( 5 ), true ), $needs( $ran( 5 ), false ), $needs( $ran( 0.1 ), true ) ), array( false, false, false ) );
ck( 'the window is six hours: a run just inside it needs none and one just past it needs a look, while Automatic sync is on', array(
	$needs( array( 'last' => time() - WPCPM_Administrators_Cards::SYNC_WINDOW + 30, 'error' => '' ), true ),
	$needs( array( 'last' => time() - WPCPM_Administrators_Cards::SYNC_WINDOW - 30, 'error' => '' ), true ),
	WPCPM_Administrators_Cards::SYNC_WINDOW,
), array( false, true, 6 * HOUR_IN_SECONDS ) );
ck( 'a run past the window needs a look only while Automatic sync is on: off, the old run is the program\'s choice', array( $needs( $ran( 7 ), true ), $needs( $ran( 7 ), false ), $needs( $ran( 24 * 30 ), true ), $needs( $ran( 24 * 30 ), false ) ), array( true, false, true, false ) );
ck( 'an error needs a look whenever it was left, Automatic sync on or off, and a fresh run does not clear it', array( $needs( $ran( 1, 'HTTP 429 from Airtable' ), true ), $needs( $ran( 1, 'HTTP 429 from Airtable' ), false ), $needs( array( 'last' => 0, 'error' => 'HTTP 500' ), false ) ), array( true, true, true ) );
ck( 'an error of white space alone is no error', array( $needs( $ran( 1, "  \n" ), true ), $needs( $ran( 1, "  \n" ), false ) ), array( false, false ) );
ck( 'a sync running now is judged as any other, by its last finished run and its last error: a run that finished 5 hours ago needs none, one 7 hours ago needs a look while Automatic sync is on, and none while it is off', array(
	$needs( $ran( 5 ), true, true ),
	$needs( $ran( 7 ), true, true ),
	$needs( $ran( 7 ), false, true ),
	$needs( $never, true, true ),
	$needs( $never, false, true ),
), array( false, true, false, true, false ) );
ck( 'a sync that holds no progress, or no last run, is read as one that never ran with no error', array(
	WPCPM_Administrators_Cards::sync_needs_look( array(), true ),
	WPCPM_Administrators_Cards::sync_needs_look( array(), false ),
	WPCPM_Administrators_Cards::sync_needs_look( array( 'last' => time() - 60, 'progress' => 'x' ), true ),
), array( true, false, false ) );
ck( 'the answer is a bool', array( is_bool( $needs( $never, true ) ), is_bool( $needs( $ran( 1, 'x' ), false ) ) ), array( true, true ) );

// The row counts exactly the syncs the predicate says need a look (the outcome figures' own read aside, which has
// a rule of its own): the badge of every fixture above is the number of its syncs for which the predicate is true.
foreach ( array(
	'on, all fresh'          => array( array( $ran( 5 ), $ran( 5 ), $ran( 5 ) ), true ),
	'on, one old'            => array( array( $ran( 5 ), $ran( 7 ), $ran( 5 ) ), true ),
	'on, one in error'       => array( array( $ran( 5 ), $ran( 5 ), $ran( 1, 'HTTP 429 from Airtable' ) ), true ),
	'on, never, old, error'  => array( array( $never, $ran( 7 ), $ran( 7, 'HTTP 429 from Airtable' ) ), true ),
	'off, old and never'     => array( array( $ran( 24 * 30 ), $never, $ran( 5 ) ), false ),
	'off, one in error'      => array( array( $ran( 24 * 30 ), $ran( 5, 'HTTP 429 from Airtable' ), $ran( 5 ) ), false ),
	'off, two in error'      => array( array( $ran( 1, 'HTTP 500' ), $ran( 9, 'HTTP 429 from Airtable' ), $ran( 7 ) ), false ),
) as $label => $case ) {
	$counted = count( array_filter( $case[0], static function ( $run ) use ( $needs, $case ) { return $needs( $run, $case[1] ); } ) );
	ck( "the row's badge ($label) is the number of syncs the predicate says need a look", syncs_row_of( $case[0], $case[1] )[0], $counted );
}

// Automatic sync is read once for the page, by health(), and is what the row says it is.
$GLOBALS['settings_read'] = array();
$auto_page                = watch_view();
$auto_reads               = count( array_keys( $GLOBALS['settings_read'], 'auto_sync', true ) );
$GLOBALS['auto_sync_off'] = true;
$off_health               = WPCPM_Administrators_Cards::health();
$off_page                 = watch_view();
unset( $GLOBALS['auto_sync_off'] );
ck( 'health() reads Automatic sync once for the page, on by default as the setting is and off when it is off, and the page\'s row follows it: off, the fixture\'s one error is all that needs a look', array(
	$auto_reads,
	isset( $data['health']['auto_sync'] ) ? $data['health']['auto_sync'] : null,
	isset( $off_health['auto_sync'] ) ? $off_health['auto_sync'] : null,
	isset( page_rows( $auto_page )['health'] ) ? page_rows( $auto_page )['health'] : null,
	isset( page_rows( $off_page )['health'] ) ? page_rows( $off_page )['health'] : null,
), array(
	1,
	true,
	false,
	array( 'badge' => 3, 'phrase' => '3 syncs need a look' ),
	array( 'badge' => 1, 'phrase' => 'Automatic sync is off; 1 sync reports an error' ),
) );

$to_engagement_row = '<a class="wpcpm-button wpcpm-button--secondary" href="' . tab_url( 'wpcpm-mentors', 'engagement' ) . '">Open the Mentors screen&#039;s Engagement tab</a>';
ck( 'a card of the program\'s numbers never opens itself, whatever it counts, its row saying what it counts: the Approved sponsors and the live offers, the programs running and at how many institutions, the active mentors and how many of them were seen in the last 30 days', array(
	rows_of( array(
		'sponsors'          => static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsors_strip( $data['sponsors'] ); },
		'programs'          => static function () use ( $data ) { WPCPM_Administrators_Cards::render_programs( $data['programs'] ); },
		'mentor-engagement' => static function () use ( $me_summary ) { WPCPM_Administrators_Cards::render_mentor_engagement( $me_summary ); },
	) ),
	rows_of( array(
		'sponsors' => static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsors_strip( array_merge( $data['sponsors'], array( 'approved' => 1, 'live_offers' => 1 ) ) ); },
		'programs' => static function () use ( $data ) {
			WPCPM_Administrators_Cards::render_programs( array(
				'tracks'   => array(
					'150h' => array( 'in_progress' => 4, 'signed_up' => 0 ),
					'50h'  => array( 'in_progress' => 0, 'signed_up' => 2 ),
				),
				'finished' => 0,
				'rows'     => array( $data['programs']['rows'][0], $data['programs']['rows'][0] ),
				'quiet'    => 0,
				'read'     => 0,
				'semester' => '',
			) );
		},
		'mentor-engagement' => static function () use ( $me_summary ) { WPCPM_Administrators_Cards::render_mentor_engagement( array_merge( $me_summary, array( 'active' => 1, 'seen_30' => 1 ) ) ); },
	) ),
	rows_of( array(
		'sponsors'          => static function () { WPCPM_Administrators_Cards::render_sponsors_strip( array() ); },
		'programs'          => static function () { WPCPM_Administrators_Cards::render_programs( array( 'tracks' => array(), 'finished' => 0, 'rows' => array(), 'quiet' => 0, 'read' => 0, 'semester' => '' ) ); },
		'mentor-engagement' => static function () use ( $me_summary ) { WPCPM_Administrators_Cards::render_mentor_engagement( array_fill_keys( array_keys( $me_summary ), 0 ) ); },
	) ),
), array(
	array(
		'sponsors'          => array( 'open' => false, 'state' => '2 Approved sponsors, 3 live offers', 'action' => $to_sponsors( 'sponsors' ) ),
		'programs'          => array( 'open' => false, 'state' => '3 programs running, 1 institution', 'action' => null ),
		'mentor-engagement' => array( 'open' => false, 'state' => '9 active mentors, 5 seen in the last 30 days', 'action' => $to_engagement_row ),
	),
	array(
		'sponsors'          => array( 'open' => false, 'state' => '1 Approved sponsor, 1 live offer', 'action' => $to_sponsors( 'sponsors' ) ),
		'programs'          => array( 'open' => false, 'state' => '1 program running, 2 institutions', 'action' => null ),
		'mentor-engagement' => array( 'open' => false, 'state' => '1 active mentor, 1 seen in the last 30 days', 'action' => $to_engagement_row ),
	),
	array(
		'sponsors'          => array( 'open' => false, 'state' => '0 Approved sponsors, 0 live offers', 'action' => $to_sponsors( 'sponsors' ) ),
		'programs'          => array( 'open' => false, 'state' => '0 programs running, 0 institutions', 'action' => null ),
		'mentor-engagement' => array( 'open' => false, 'state' => '0 active mentors, 0 seen in the last 30 days', 'action' => $to_engagement_row ),
	),
) );
// The head and the menu say what needs an Administrator today, and Mentor engagement holds the
// program's numbers, on the Reference view: no count leads to it. The Overview is read above its
// groups, where the head, the figures and the menu are and the summary is zeros, and counts() is read
// again with the summary above zero in the figures a count would count, which a count for the card
// would give.
$head_part           = (string) strstr( $out, '<div class="wpcpm-administrator__group ', true );
$counts_with_engaged = WPCPM_Administrators_Cards::counts( array_merge( $data, array( 'mentor_engagement' => $me_summary ) ) );
ck( 'no count leads to Mentor engagement: the menu, the head and the figures above the Overview\'s groups name no such card, counts() gives it none with the summary above zero, and the card is drawn on the Reference view and not on the Overview', array(
	has( $head_part, '<header class="wpcpm-head">' ),
	has( $head_part, 'wpcpm-mentor-engagement' ),
	array_keys( $counts_with_engaged ) === array_keys( WPCPM_Administrators_Cards::counts( $data ) ),
	in_array( 'mentor-engagement', array_column( $counts_with_engaged, 'card' ), true ),
	array( has( $out, 'id="wpcpm-mentor-engagement"' ), has( $out_ref, 'id="wpcpm-mentor-engagement"' ) ),
), array( true, false, true, false, array( false, true ) ) );
// A row is read as one statement, its title, its number and its phrase: the number beside the title
// is the first number the phrase says, or zero beside a phrase that says none.
$mismatched = static function ( $html ) {
	return array_filter( page_rows( $html ), static function ( $row ) {
		return null !== $row['phrase'] && $row['badge'] !== ( 1 === preg_match( '/[0-9]+/', $row['phrase'], $number ) ? (int) $number[0] : 0 );
	} );
};
// Read across the Overview, with every queue holding something, and the Reference view, which together
// draw every card.
$all_rows = page_rows( $out_full . $out_ref );
ck( 'on every card of the two views the badge is the first number its phrase says, so a row never says two numbers: the syncs that need a look beside Syncs and health, whatever the locked accounts, the programs running beside Programs running and the active mentors beside Mentor engagement', array(
	count( $all_rows ),
	$mismatched( $out_full . $out_ref ),
	isset( $all_rows['health'] ) ? $all_rows['health'] : null,
	isset( $all_rows['programs'] ) ? $all_rows['programs'] : null,
	isset( $all_rows['mentor-engagement'] ) ? $all_rows['mentor-engagement'] : null,
	card_badge( capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_programs( array( 'tracks' => array( '150h' => array( 'in_progress' => 4, 'signed_up' => 0 ), '50h' => array( 'in_progress' => 0, 'signed_up' => 2 ) ), 'finished' => 0, 'rows' => array( $data['programs']['rows'][0], $data['programs']['rows'][0] ), 'quiet' => 0, 'read' => 0, 'semester' => '' ) ); } ) ),
), array(
	// 1.123.93: Students waiting to graduate's row among them.
	18,
	array(),
	array( 'badge' => 3, 'phrase' => '3 syncs need a look' ),
	array( 'badge' => 3, 'phrase' => '3 programs running, 1 institution' ),
	array( 'badge' => 0, 'phrase' => '0 active mentors, 0 seen in the last 30 days' ),
	1,
) );
// The words a row carries are text: a site's date format that holds markup reads as text in the
// row.
$GLOBALS['opts']['date_format'] = 'j F Y \<\b\>';
$marked_day                     = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_duplicates( $data['duplicates'] ); } );
$GLOBALS['opts']['date_format'] = 'j F Y';
ck( 'fix round 2: the duplicates phrase\'s date is whole', has( $marked_day, 'as of ' . str_replace( ' ', "\u{00A0}", esc_html( $dup_day ) ) ), true );
ck( 'a row\'s phrase is printed through the shell, escaped: a date format that holds markup reads as text', array(
	has_words( $marked_day, '<span class="wpcpm-sponsor__state">3 duplicated students as of ' . esc_html( $dup_day ) . ' &lt;b&gt;</span>' ),
	has( (string) strstr( $marked_day, '</summary>', true ), '<b>' ),
), array( true, false ) );

/*
 * The page's own sheet, read rule by rule (Task 18c).
 */
$admin_sheet = sheet_rules( 'administrator.css' );
// Task 18c: the card's old row (its phrase, its action beside the row, the help link's line and lifts) left the sheet:
// since Task 18b the Administrator Dashboard's cards are on the parts alone, so no `wpcpm-administrator__card` holds
// a `wpcpm-group__disclosure`, a `wpcpm-sponsor__state`, a `wpcpm-sponsor__row-action` or a `wpcpm-job-link-row`, and
// the Sponsor Dashboard's cards, which print them, are `wpcpm-sponsor__card` and wear sponsor.css's copies. The section
// keeps the two rules its own class still matches.
ck( 'the card\'s old row left this page\'s sheet; the section keeps its margin and its positioning, which its class still matches', array(
	array_values( array_filter( array_keys( $admin_sheet ), static function ( $key ) { return 1 === preg_match( '/wpcpm-sponsor__state|wpcpm-sponsor__row-action|wpcpm-group__disclosure|wpcpm-group__summary|wpcpm-job-link-row|wpcpm-job-card/', $key ); } ) ),
	$admin_sheet['|.wpcpm-administrator .wpcpm-administrator__card'] ?? null,
	$admin_sheet['|.wpcpm-administrator__card'] ?? null,
	$admin_sheet['|.wpcpm-job-link'] ?? null,
), array( array(), 'margin-bottom: 1.25em;', 'position: relative;', 'color: var( --wpcpm-ink-muted ); font-size: 13px; text-decoration: none; white-space: normal;' ) );

echo "\n=== The toolbar and the refusal ===\n";

$GLOBALS['manage'] = array( 3 );
ck( 'a manager\'s toolbar lists the Administrator Dashboard', in_array( 'wpcpm-administrator-dashboard', array_column( WPCPM_Dashboards::links(), 'id' ), true ), true );
$GLOBALS['manage'] = array();
ck( 'and a member\'s does not', in_array( 'wpcpm-administrator-dashboard', array_column( WPCPM_Dashboards::links(), 'id' ), true ), false );
ck( 'the refusal names the audience, not the Mentor role', WPCPM_Dashboards::nothing_to_show( 'administrators', false ), 'This page is for the Administrators. Your account cannot manage the program.' );
ck( 'and a manager with nothing is pointed at the Administrators screen', has( WPCPM_Dashboards::nothing_to_show( 'administrators', true ), 'page=wpcpm-administrators' ), true );

echo "\n=== The wiring ===\n";

$module_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators.php' );
ck( 'the module boots the dashboard, ensures the page on activation and deletes its options on uninstall', array( has( $module_src, 'WPCPM_Administrators_Dashboard::init()' ), has( $module_src, 'WPCPM_Administrators_Dashboard::ensure_page()' ), has( $module_src, 'WPCPM_Administrators_Dashboard::OPT_PAGE' ), has( $module_src, 'WPCPM_Administrators_Dashboard::OPT_TITLE_FIXED' ) ), array( true, true, true, true ) );
$block = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/administrator-dashboard/block.json' ), true );
$asset = include WPCPM_PLUGIN_DIR . 'blocks/administrator-dashboard/editor.asset.php';
ck( 'the block is named for the class and versioned with the release that last changed what its description says (1.123.93: students waiting to graduate), its editor script with the release that last changed that script (1.123.27)', array( $block['name'], $block['version'], $asset['version'] ), array( 'wpcpm/administrator-dashboard', '1.123.93', '1.123.27' ) );
$editor_js = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/administrator-dashboard/editor.js' );
ck( 'and the editor\'s empty preview speaks of the Administrator viewing the page, not a manager', array( has( $editor_js, 'for the Administrator viewing it.' ), has( $editor_js, 'for the manager viewing it' ), has( $block['description'], 'Shows Administrators what needs them' ) ), array( true, false, true ) );
$dashes = array();
foreach ( array( 'includes/modules/class-wpcpm-administrators-cards.php', 'includes/modules/class-wpcpm-administrators-dashboard.php', 'includes/class-wpcpm-return.php', 'assets/css/administrator.css', 'blocks/administrator-dashboard/editor.js', 'bin/test-administrators-dashboard.php' ) as $relative ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $relative ) ) ) {
		$dashes[] = $relative;
	}
}
ck( 'no dash but the plain hyphen in any new file', $dashes, array() );

/*
 * The stylesheet, measured. The dashboard's base is 14px, so a rule at 0.85em rendered a
 * sentence a manager reads at 11.9px - the institution's name on a Programs tile, the label
 * under its number, and the breakdown line inside a table cell (deep check FFRNT-4). And a
 * queue at zero carried opacity 0.55 on the tile with the label's own 0.72 inside it: opacities
 * multiply, so that label painted at an effective 0.396 and 2.44:1 against the card, under the
 * 4.5:1 WCAG 1.4.3 asks, on the only thing naming the empty queue (FFRNT-5).
 */
$css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/administrator.css' );
ck( 'the two lines a manager reads on a tile are sized in pixels, at the 14px floor, and no rule is at 0.85em', array(
	1 === preg_match( '/\.wpcpm-programs__name,\s*\.wpcpm-programs__l \{[^}]*font-size: 14px;/s', $css ),
	substr_count( $css, 'font-size: 0.85em' ),
), array( true, 0 ) );
ck( 'and the token is declared beside the others in the sheet the dashboards share', 1 === preg_match( '/--wpcpm-ink-muted: color-mix\(/', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/dashboard.css' ) ), true );
// Task 18c: the strip (its tiles, its zero and waiting tiles, its phone layout and its quiet sentence) left the sheet:
// no renderer prints it since Task 18b (`render_strip()` went), and the Sponsor Dashboard's own strip went in 1.123.78.
// The old column table's rules went with it (Task 18b drew the last one); their measurements left with them.
ck( 'no rule of the sheet dresses the strip, the old items, lists, tables, boxes or the Mentor applications\' old row', array_values( array_filter( array_keys( sheet_rules( 'administrator.css' ) ), static function ( $key ) {
	return 1 === preg_match( '/wpcpm-attention__|wpcpm-administrator__(item|facts|fact|note|actions|closed|subtitle|empty|read|list|scroll)\b|wpcpm-admin-table|wpcpm-quiet__(group|form)|wpcpm-overview__row-in|wpcpm-sponsor-offer__codes|wpcpm-offer__/', $key );
} ) ), array() );
// Task 18d: the "We've already talked" form of the Report Cards (`talked_form()`) prints only on pages that do not load
// this sheet, always inside `.wpcpm-quiet__notice`, whose rule in dashboard.css gives it its margin; the Administrator
// Dashboard's card posts through `talked_card_form()`, which carries no class.
ck( 'no rule of the sheet names the Report Cards\' talked form; dashboard.css sets its margin inside the notice', array(
	array_values( array_filter( array_keys( sheet_rules( 'administrator.css' ) ), static function ( $key ) { return false !== strpos( $key, 'wpcpm-quiet__talked' ); } ) ),
	sheet_rules( 'dashboard.css' )['|.wpcpm-quiet__notice .wpcpm-quiet__talked'] ?? null,
), array( array(), 'margin: 0;' ) );
// Task 18f, points 12 and 13: inside the Administrator Dashboard's parts (the root class, which no other surface
// prints) the tiles of a row share their rows, so their figures sit on one line across the row (subgrid; a browser that
// does not know it drops that one declaration and stacks each tile's lines as before), and a tile's label and its line
// under the figure balance their lines. The cohort timeline's four figures alike. No rule names a tile outside the
// root class, so the Sponsor Dashboard, whose sheet depends on this one, cannot match.
$t18f_admin  = sheet_rules( 'administrator.css' );
$t18f_cohort = sheet_rules( 'cohort-timeline.css' );
// Fix round 1 (Minor 3, Minor 4): the subgrid sits inside a feature query, so a browser that does not know subgrid
// draws the tiles exactly as before (no span, no grid); every rule with subgrid or text-wrap starts with the root
// class, whatever query holds it.
$t18f_sup = '@' . 'supports ( grid-template-rows: subgrid )';
$t18f_new = array_filter( $t18f_admin + $t18f_cohort, static function ( $body ) { return false !== strpos( $body, 'subgrid' ) || false !== strpos( $body, 'text-wrap' ); } );
ck( 'tiles in one row share their rows inside the parts, their label and line balanced; the cohort figures alike; nothing new outside the root class', array(
	$t18f_admin[ $t18f_sup . '|.wpcpm-ui-on .wpcpm-programs__tile' ] ?? null,
	$t18f_admin['|.wpcpm-ui-on .wpcpm-programs__name'] ?? null,
	$t18f_admin['|.wpcpm-ui-on .wpcpm-programs__l'] ?? null,
	$t18f_cohort[ $t18f_sup . '|.wpcpm-ui-on .wpcpm-timeline-stats__item' ] ?? null,
	$t18f_cohort['|.wpcpm-ui-on .wpcpm-timeline-stats__label'] ?? null,
	$t18f_cohort['|.wpcpm-ui-on .wpcpm-timeline-stats__note'] ?? null,
	// Fix round 2 (c): a shorter label sits at the bottom of its shared row, right over its figure.
	array( $t18f_admin[ $t18f_sup . '|.wpcpm-ui-on .wpcpm-programs__name' ] ?? null, $t18f_cohort[ $t18f_sup . '|.wpcpm-ui-on .wpcpm-timeline-stats__label' ] ?? null ),
	count( $t18f_new ),
	array_values( array_filter( array_keys( $t18f_new ), static function ( $k ) { return false === strpos( $k, '|.wpcpm-ui-on ' ); } ) ),
	array_values( array_filter( array_keys( $t18f_admin + $t18f_cohort ), static function ( $k ) use ( $t18f_sup ) { return false !== strpos( $k, 'wpcpm-programs__tile' ) && false !== strpos( $k, 'ui-on' ) && 0 !== strpos( $k, $t18f_sup ); } ) ),
), array(
	'display: grid; grid-row: span 3; grid-template-rows: subgrid; row-gap: 0;',
	'text-wrap: balance;',
	'text-wrap: balance;',
	'display: grid; grid-row: span 3; grid-template-rows: subgrid; row-gap: 2px;',
	'text-wrap: balance;',
	'text-wrap: balance;',
	array( 'align-self: end;', 'align-self: end;' ),
	6,
	array(),
	array(),
) );
// The Resources column is gone from the page (1.123.71), and with it the rule that spaced its second
// heading.
ck( 'the page\'s own sheet holds no rule for a Resources column it no longer draws', array_filter( array_keys( sheet_rules( 'administrator.css' ) ), static function ( $selector ) { return false !== strpos( $selector, 'wpcpm-resources__col' ); } ), array() );

echo "\n=== The column tables scroll by keyboard on a phone (1.122.35) ===\n";

/*
 * Below 782px the five tables with column headers were wider than a phone and scrolled sideways in a
 * box of their own, a tab stop and a region named by the heading words above it (1.122.35). Every
 * card draws its tables through the table part since the cards' old branches left (Task 18b), which
 * stacks on a phone: no file of the plugin prints the old table or its box any more, and their rules left the
 * sheet in Task 18c.
 */
$table_sites = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPCPM_PLUGIN_DIR . 'includes', FilesystemIterator::SKIP_DOTS ) ) as $source_file ) {
	if ( 'php' !== $source_file->getExtension() ) {
		continue;
	}
	$source = (string) file_get_contents( $source_file->getPathname() );
	if ( substr_count( $source, '<table class="wpcpm-admin-table' ) || substr_count( $source, 'wpcpm-administrator__scroll' ) ) {
		$table_sites[] = basename( $source_file->getPathname() );
	}
}
ck( 'in the source, no file prints the old column table or its scroll box any more: the cards draw the table part', $table_sites, array() );

$admin_rules = scroll_css_rules( $css );
$phone_box   = array();
$table_box   = array();
$ring        = array();
foreach ( $admin_rules as $rule ) {
	if ( '( max-width: 782px )' === $rule['media'] && in_array( '.wpcpm-administrator__scroll', $rule['selectors'], true ) ) {
		$phone_box = array_merge( $phone_box, $rule['decls'] );
	}
	if ( in_array( '.wpcpm-admin-table', $rule['selectors'], true ) ) {
		$table_box = array_merge( $table_box, array_intersect_key( $rule['decls'], array_flip( array( 'display', 'overflow', 'overflow-x', 'overflow-y', 'position' ) ) ) );
	}
	if ( in_array( ':where( .wpcpm-administrator__scroll:focus-visible )', $rule['selectors'], true ) ) {
		$ring = array_merge( $ring, $rule['decls'] );
	}
}
// Task 18c: with nothing printing the box or the table, their rules left the sheet: no box rule, no table rule, no ring.
ck( 'the old box\'s and the old table\'s rules left the sheet with the markup they dressed', array( $phone_box, $table_box, $ring ), array( array(), array(), array() ) );

echo "\n=== The Sponsor Collaboration Agreements card (1.96.1) ===\n";
$sagr = capture( static function () { WPCPM_Administrators_Cards::render_sponsor_agreements( array( 'review' => array( $GLOBALS['agr_facts'][913] ), 'revoked' => array( $GLOBALS['agr_facts'][880] ) ) ); } );
// A stored name the scanner does not record is drawn as stored, and as text: a name holding a tag, a
// quote and an "&" beside one it words.
ob_start();
WPCPM_Administrators_Cards::render_sponsor_agreements( array( 'review' => array(), 'revoked' => array( array_merge( $GLOBALS['agr_facts'][880], array( 'flags' => array( '<b>"x"&</b>', '/JS' ) ) ) ) ) );
$hostile = (string) ob_get_clean();
ck( 'a stored name holding a tag is drawn as text on the card, escaped, beside one in words', array( has( $hostile, 'The scan noticed: &lt;b&gt;&quot;x&quot;&amp;&lt;/b&gt;, JavaScript code (/JS)' ), has( $hostile, '<b>"x"' ) ), array( true, false ) );
$sagr_empty = capture( static function () { WPCPM_Administrators_Cards::render_sponsor_agreements( array( 'review' => array(), 'revoked' => array() ) ); } );
ck( 'with nothing to read the card says so and shows no subheading', array( has( $sagr_empty, 'No sponsor agreement is waiting for review.' ), has( $sagr_empty, 'Out of force' ) ), array( true, false ) );

echo "\n=== The Sponsor applications card (Phase S5: the decisions, the tile, the anchor) ===\n";
ob_start();
WPCPM_Administrators_Cards::render_sponsor_applications( $GLOBALS['sponsor_apps'] );
$sa = (string) ob_get_clean();
// The sponsor records live on the site, so the note says the site holds the sponsor already, read
// whole: words that named another store would fail here.
ob_start();
WPCPM_Administrators_Cards::render_sponsor_applications( array() );
$sa = (string) ob_get_clean();
ck( 'with nothing waiting the card says so', has( $sa, 'No sponsor application is waiting.' ), true );
ob_start();
WPCPM_Administrators_Cards::render_sponsor_applications( array( array_merge( $GLOBALS['sponsor_apps'][0], array( 'half_done' => true ) ) ) );
$sa = (string) ob_get_clean();
ck( 'a half-done approval is said so here too, because three of the decisions under it are refused (S5 fix wave)', array( has( $sa, 'approval of this application is half done' ), has( $sa, 'Press Approve again to finish.' ) ), array( true, true ) );
// The tier the applicant is considering (1.113.0): one more fact straight after the state, read
// off the key the queue's facts carry and never off the label, which a translation may reword. A
// tier says "Considering:" and its label; not sure yet, an answer and no tier, says so in its own
// sentence; an application with no answer, or facts shaped before the key existed, says nothing.
// A manager surface, so the switch is not asked.
// Split on the item's class alone: the item's id follows the class in its tag.
$sapp = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsor_applications( $data['sponsor_applications'] ); } );
$GLOBALS['uid']    = 3;
$GLOBALS['manage'] = array( 3 );
$GLOBALS['flash']  = array( 'institutions' => 'sapp-approved' );
$out_sapp          = WPCPM_Administrators_Dashboard::render( array() );
ck( 'a decision\'s flash is drawn in the application class\'s words, on the page it came back to', has( $out_sapp, 'The sponsor application is approved.' ), true );

echo "\n=== An institution's agreement and a company's each read their own words here ===\n";

// The institution agreement and the sponsor agreement word their outcomes for their own audiences
// under the same keys, `agreement-returned`, `agreement-note` and fifteen more, and every decision
// The outcome of a decision is printed inside the card it came from (SDB-02, 1.123.58).
$card_of = static function ( $html, $id ) {
	$from = strpos( (string) $html, 'id="' . $id . '"' );
	$card = false === $from ? '' : substr( (string) $html, $from );
	$end  = strpos( $card, '</details>' );

	return false === $end ? $card : substr( $card, 0, $end );
};
$_POST            = array( WPCPM_Return::FIELD => 'dashboard', WPCPM_Return::ANCHOR_FIELD => 'reports' );
WPCPM_Return::url( 'https://example.test/wp-admin/' );
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'sevent-approved' );
$in_card = WPCPM_Administrators_Dashboard::render( array() );
ck( 'a decision posted from a card comes back with its sentence inside that card, as a status',
	array( has( $card_of( $in_card, 'wpcpm-reports' ), 'role="status" tabindex="-1" data-wpcpm-card-message>The event is approved.</p>' ), substr_count( $in_card, 'data-wpcpm-card-message' ) ),
	array( true, 1 ) );
$_POST = array( WPCPM_Return::FIELD => 'dashboard', WPCPM_Return::ANCHOR_FIELD => 'reports' );
WPCPM_Return::url( 'https://example.test/wp-admin/' );
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'sevent-note-empty' );
$in_card = WPCPM_Administrators_Dashboard::render( array() );
ck( 'a refused decision is an alert in its card', array( has( $card_of( $in_card, 'wpcpm-reports' ), 'role="alert"' ), substr_count( $in_card, 'data-wpcpm-card-message' ) ), array( true, 1 ) );
$_POST = array( WPCPM_Return::FIELD => 'dashboard', WPCPM_Return::ANCHOR_FIELD => 'reports' );
WPCPM_Return::url( 'https://example.test/wp-admin/' );
$GLOBALS['flash']['institution_report'] = array( 'status' => 'refused' );
$in_card = WPCPM_Administrators_Dashboard::render( array() );
ck( 'a refused Draft now from the report screen is said in the card it came from', array( has( $card_of( $in_card, 'wpcpm-reports' ), 'That is not something you can do here.' ), has( $card_of( $in_card, 'wpcpm-reports' ), 'role="alert"' ), substr_count( $in_card, 'data-wpcpm-card-message' ) ), array( true, true, 1 ) );
// A refused Draft now that came back with a decision held already (the fix wave of 1.123.58): both
// are said, the refusal after the decision, where before the outcomes moved into the cards both
// paragraphs printed.
$_POST = array( WPCPM_Return::FIELD => 'dashboard', WPCPM_Return::ANCHOR_FIELD => 'reports' );
WPCPM_Return::url( 'https://example.test/wp-admin/' );
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'sevent-approved' );
$GLOBALS['flash']['institution_report'] = array( 'status' => 'refused' );
$beside                                 = WPCPM_Administrators_Dashboard::render( array() );
$beside_card                            = $card_of( $beside, 'wpcpm-reports' );
$decision_at                            = strpos( $beside_card, 'role="status" tabindex="-1" data-wpcpm-card-message>The event is approved.</p>' );
$refusal_at                             = strpos( $beside_card, 'role="alert" tabindex="-1" data-wpcpm-card-message>That is not something you can do here.</p>' );
ck( 'a refused Draft now is said beside a held decision',
	array( false !== $decision_at, false !== $refusal_at && false !== $decision_at && $refusal_at > $decision_at, substr_count( $beside, 'data-wpcpm-card-message' ), isset( $GLOBALS['flash']['institution_report'] ) ),
	array( true, true, 2, false ) );
$_POST            = array();
$GLOBALS['flash'] = array( 'institutions' => 'sevent-approved' );
$no_card          = WPCPM_Administrators_Dashboard::render( array() );
$GLOBALS['flash'] = array( 'institutions' => 'sevent-approved' );
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_REFERENCE;
$no_card_ref      = WPCPM_Administrators_Dashboard::render( array() );
unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] );
// Where the intro line was, the outcome with no card is said where the slot now is: on the Overview
// after the figures and before the Waiting group, on The program after its head and before its group.
ck( 'an outcome with no card is said where the page\'s outcome goes, once: on the Overview after the figures and before the Waiting group, and on The program after its head and before its group', array(
	has( $no_card, '</section><p class="wpcpm-dashboard__message wpcpm-dashboard__message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>The event is approved.</p><div class="wpcpm-overview wpcpm-overview--wait">' ),
	has( $no_card_ref, '</header><p class="wpcpm-dashboard__message wpcpm-dashboard__message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>The event is approved.</p><div class="wpcpm-reference">' ),
	array( substr_count( $no_card, 'data-wpcpm-card-message' ), substr_count( $no_card_ref, 'data-wpcpm-card-message' ), substr_count( $no_card . $no_card_ref, WPCPM_Dashboard_Card::SLOT ) ),
), array( true, true, array( 1, 1, 0 ) ) );
// A card whose queue is empty is not drawn on the Overview, but the card a decision just came back to
// is, open, with its outcome at the top of its body, though what it decided emptied its queue: the
// sponsor offers' queue is empty here, and the one offer it held was just approved. The other queues'
// cards stay folded, and the sentence that names the empty queues does not name that one.
$_POST = array( WPCPM_Return::FIELD => 'dashboard', WPCPM_Return::ANCHOR_FIELD => 'sponsor-offers-review' );
WPCPM_Return::url( 'https://example.test/wp-admin/' );
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'offer-approved' );
$held_empty = WPCPM_Administrators_Dashboard::render( array() );
$_POST      = array();
ck( 'the card a decision just came back to is drawn though its queue is now empty: open, its outcome at the top of its body, the other queues\' cards folded, and not named among the empty queues', array(
	page_cards( $held_empty ),
	isset( row_marks( $held_empty )['sponsor-offers-review'] ) ? row_marks( $held_empty )['sponsor-offers-review']['open'] : null,
	has( $card_of( $held_empty, 'wpcpm-sponsor-offers-review' ), '<div class="wpcpm-group__body"><p class="wpcpm-dashboard__message wpcpm-dashboard__message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>Approved. Students and mentors the offer is open to see it as it now reads.</p>' ),
	substr_count( $held_empty, 'data-wpcpm-card-message' ),
	row_marks( $held_empty )['applications']['open'],
	array_key_exists( 'Sponsor offers to review', quiet_links( $held_empty ) ),
), array(
	array( 'applications', 'agreements', 'reports', 'requests', 'sponsor-applications', 'sponsor-posts', 'sponsor-offers-review', 'sponsor-agreements', 'sponsor-reports' ),
	true,
	true,
	1,
	false,
	false,
) );
$GLOBALS['flash'] = array();

// this page posts flashes on the Institutions screen's channel. Merged under one key, the map the
// page prints from told an institution's Administrator what the company was told. So the sponsor
// agreement flashes its outcomes here under its own prefix, and the page reads its sentences under
// that prefix: the institution's keys, the channel's own, keep the institution's words.
// Read off the page's messages alone: the page also folds in the institution applications' answers,
// and since 1.123.29 one of their questions is "Their role at the institution".
$said_for = static function ( $status ) {
	$GLOBALS['flash'] = array( 'institutions' => $status );
	preg_match_all( '#<p class="wpcpm-dashboard__message wpcpm-dashboard__message--[a-z]+ is-[a-z]+" role="[a-z]+" tabindex="-1" data-wpcpm-card-message>(.*?)</p>#s', WPCPM_Administrators_Dashboard::render( array() ), $said );
	return implode( "\n", $said[1] );
};
$institution_returned = $said_for( 'agreement-returned' );
$institution_refused  = $said_for( 'agreement-note' );
$company_returned     = $said_for( WPCPM_Sponsor_Agreement::DASHBOARD_PREFIX . 'agreement-returned' );
$company_refused      = $said_for( WPCPM_Sponsor_Agreement::DASHBOARD_PREFIX . 'agreement-note' );
ck( 'an institution agreement returned, or its return refused, reads the institution\'s words, never the company\'s', array(
	has( $institution_returned, 'Returned: everybody at the institution has your note.' ),
	has( $institution_returned, 'at the company' ),
	has( $institution_refused, 'Nothing was returned. The note is the whole of what the institution is told.' ),
	has( $institution_refused, 'what the company is told' ),
), array( true, false, true, false ) );
ck( 'and a sponsor agreement\'s, flashed under its prefix, reads the company\'s words, never the institution\'s', array(
	has( $company_returned, 'Returned: everybody at the company has your note.' ),
	has( $company_returned, 'at the institution' ),
	has( $company_refused, 'Nothing was returned. The note is the whole of what the company is told.' ),
	has( $company_refused, 'what the institution is told' ),
), array( true, false, true, false ) );

// What this suite stands in for, read off the real sources: the prefix the sponsor agreement writes
// is the one stood in here, its way back to this page flashes under it, and this page reads its
// sentences under it. And across every map the page merges, the institution's three, the three
// sponsor ones and the sync's, no key is any other map's, so no audience can be read another's words.
$src_of   = static function ( $file ) {
	return (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/' . $file );
};
$body_of  = static function ( $src, $name ) {
	$at   = strpos( $src, 'function ' . $name . '(' );
	$body = false === $at ? '' : substr( $src, $at );
	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
};
$keys_of  = static function ( $body ) {
	preg_match_all( "/^\t\t\t'([a-z0-9-]+)'\s*=>\s*array\(/m", (string) $body, $m );
	return $m[1];
};
$sagr_src = $src_of( 'class-wpcpm-sponsor-agreement.php' );
$prefix   = preg_match( "/const DASHBOARD_PREFIX = '([a-z-]+)';/", $sagr_src, $declared ) ? $declared[1] : '';
ck( 'the real sponsor agreement declares the prefix stood in here, and flashes under it on its way back to this page', array(
	$prefix,
	false !== strpos( $body_of( $sagr_src, 'bounce' ), 'WPCPM_Flash::set( WPCPM_Institutions::FLASH, self::DASHBOARD_PREFIX . $status );' ),
	false !== strpos( $body_of( $src_of( 'class-wpcpm-administrators-dashboard.php' ), 'render_messages' ), '$messages[ WPCPM_Sponsor_Agreement::DASHBOARD_PREFIX . $key ] = $message;' ),
), array( WPCPM_Sponsor_Agreement::DASHBOARD_PREFIX, true, true ) );
$merged = array(
	'Institutions::queue_messages'          => $keys_of( $body_of( $src_of( 'class-wpcpm-institutions.php' ), 'queue_messages' ) ),
	'Institution_Panel::messages'           => $keys_of( $body_of( $src_of( 'class-wpcpm-institution-panel.php' ), 'messages' ) ),
	'Institution_Request::messages'         => $keys_of( $body_of( $src_of( 'class-wpcpm-institution-request.php' ), 'messages' ) ),
	'Sponsor_Posts::messages'               => $keys_of( $body_of( $src_of( 'class-wpcpm-sponsor-posts.php' ), 'messages' ) ),
	'Sponsor_Agreement::manager_messages'   => array_map(
		static function ( $key ) use ( $prefix ) {
			return $prefix . $key;
		},
		$keys_of( $body_of( $sagr_src, 'manager_messages' ) )
	),
	'Sponsor_Application::manager_messages' => $keys_of( $body_of( $src_of( 'class-wpcpm-sponsor-application.php' ), 'manager_messages' ) ),
	'Sync_Module::sync_messages'            => $keys_of( $body_of( $src_of( 'class-wpcpm-sync-module.php' ), 'sync_messages' ) ),
);
$shared = array();
foreach ( $merged as $one => $keys ) {
	foreach ( $merged as $other => $other_keys ) {
		if ( $one < $other && array_intersect( $keys, $other_keys ) ) {
			$shared[ $one . ' and ' . $other ] = array_values( array_intersect( $keys, $other_keys ) );
		}
	}
}
ck( 'every map the page merges was read, each with its keys', array_map( 'count', $merged ) === array_filter( array_map( 'count', $merged ) ) && count( $merged['Institution_Panel::messages'] ) > 30 && count( $merged['Sponsor_Agreement::manager_messages'] ) > 15, true );
ck( 'and no key the page reads is two maps\' at once', $shared, array() );
$GLOBALS['flash'] = array();

echo "\n=== Each item a wp-admin screen links to carries an id of its own ===\n";
// The wp-admin screens read these four queues and link each item to its place here, where it is
// decided: one attribute on the article each item already is, named for the item's post, with the
// decision block drawn inside it as before, bound for this page. The agreements card's two lists
// carry it alike, the document waiting to be read and the agreement out of force.
$items_of = static function ( $html, $kind ) {
	// Today's item, or the parts' (`WPCPM_UI::item_open()`), whose old branch left (Task 18b).
	preg_match_all( '#<article class="(?:wpcpm-administrator__item ' . preg_quote( $kind, '#' ) . '|' . preg_quote( $kind, '#' ) . ' wpcpm-ui-item)"(?: id="([^"]*)")?>(.*?)</article>#s', (string) $html, $found, PREG_SET_ORDER );
	$out = array();
	foreach ( $found as $item ) {
		$out[ (string) $item[1] ] = $item[2];
	}
	// Task V1, ruling 14: an agreement out of force is a list row carrying the same anchor, its Reinstate inside it.
	preg_match_all( '#<li class="wpcpm-ui-list__row" id="(' . preg_quote( $kind, '#' ) . '-\d+)">(.*?)</li>#s', (string) $html, $rows, PREG_SET_ORDER );
	foreach ( $rows as $item ) {
		$out[ (string) $item[1] ] = $item[2];
	}
	ksort( $out );
	return $out;
};
$app_items  = $items_of( $apps, 'wpcpm-application' );
$post_items = $items_of( $sp, 'wpcpm-sponsor-post' );
$agr_items  = $items_of( $sagr, 'wpcpm-sponsor-agreement' );
$sapp_items = $items_of( $sapp, 'wpcpm-sponsor-application' );
ck( 'each institution application carries its own id, the two open ones and the one folded closed', array_keys( $app_items ), array( 'wpcpm-application-501', 'wpcpm-application-502', 'wpcpm-application-503' ) );
ck( 'each sponsor application, each sponsor post and each sponsor agreement, waiting or out of force, carries its own', array( array_keys( $sapp_items ), array_keys( $post_items ), array_keys( $agr_items ) ), array(
	array( 'wpcpm-sponsor-application-950' ),
	array( 'wpcpm-sponsor-post-905' ),
	array( 'wpcpm-sponsor-agreement-880', 'wpcpm-sponsor-agreement-913' ),
) );
ck( 'and every one of them still draws its decisions inside it, bound for this page', array(
	isset( $app_items['wpcpm-application-501'] ) && has( $app_items['wpcpm-application-501'], 'name="wpcpm_return" value="dashboard"' ) && has( $app_items['wpcpm-application-501'], 'value="wpcpm_app_spam"' ),
	isset( $app_items['wpcpm-application-502'] ) && has( $app_items['wpcpm-application-502'], 'name="wpcpm_return" value="dashboard"' ),
	isset( $sapp_items['wpcpm-sponsor-application-950'] ) && has( $sapp_items['wpcpm-sponsor-application-950'], 'data-post="950" data-return="dashboard"' ),
	isset( $post_items['wpcpm-sponsor-post-905'] ) && has( $post_items['wpcpm-sponsor-post-905'], 'data-post="905" data-return="dashboard"' ),
	isset( $agr_items['wpcpm-sponsor-agreement-913'] ) && has( $agr_items['wpcpm-sponsor-agreement-913'], 'data-post="913" data-return="dashboard"' ),
	isset( $agr_items['wpcpm-sponsor-agreement-880'] ) && has( $agr_items['wpcpm-sponsor-agreement-880'], 'data-post="880" data-return="dashboard"' ),
), array( true, true, true, true, true, true ) );
// Counted off the markup rather than off the lists above, which are keyed by the id and would fold
// two items with one id into one.
preg_match_all( '#<(?:article|li) class="[^"]*" id="([^"]*)">#', $apps . $sapp . $sp . $sagr, $printed_ids );
ck( 'and no two items share one', array( count( $printed_ids[1] ), count( array_unique( $printed_ids[1] ) ) ), array( 7, 7 ) );

/* ---- each institution card links its wp-admin tab ------------------------ */

echo "\n=== Each institution card ends with the way to its tab on the Institutions screen ===\n";

// The dashboard decides and wp-admin lists the same items, so each institution card links the
// Institutions screen's tab that lists its items twice over, in the dashboards' shell: its row action
// after the fold, shown while the card is folded, and the same link as the last line of its body, read
// once the card is open; both on every draw of the card, an empty one's too. The line a cut list
// prints says how many more there are and when they are listed (the open list's as these are decided,
// the closed fold's as these are deleted or put back in the queue), and links nowhere: the screen
// lists the same window. The sponsor cards end with the Sponsors screen's link instead (below).
$foot_line = function ( $tab ) {
	return card_foot( tab_url( 'wpcpm-institutions', $tab ), 'Open on the Institutions screen' );
};
ck( 'the four institution cards each end with one line to the tab that holds their items, and after the fold the same link as their row action: the applications, the agreements and the requests the queue, the semester reports their own tab', array(
	ends_with( $apps, $foot_line( 'queue' ) ),
	ends_with( $agr_html, $foot_line( 'queue' ) ),
	ends_with( $rep, $foot_line( 'reports' ) ),
	ends_with( $req, $foot_line( 'queue' ) ),
	substr_count( $apps . $agr_html . $rep . $req, 'Open on the Institutions screen' ),
), array( true, true, true, true, 8 ) );
$empty_cards = array(
	capture( static function () { WPCPM_Administrators_Cards::render_applications( array() ); } ),
	capture( static function () { WPCPM_Administrators_Cards::render_agreements( array() ); } ),
	capture( static function () { WPCPM_Administrators_Cards::render_reports( array() ); } ),
	capture( static function () { WPCPM_Administrators_Cards::render_requests( array() ); } ),
);
ck( 'and so does each of them with nothing in it', array(
	ends_with( $empty_cards[0], $foot_line( 'queue' ) ),
	ends_with( $empty_cards[1], $foot_line( 'queue' ) ),
	ends_with( $empty_cards[2], $foot_line( 'reports' ) ),
	ends_with( $empty_cards[3], $foot_line( 'queue' ) ),
), array( true, true, true, true ) );
ck( 'a cut list keeps its own line, unlinked, and the card still ends with its tab\'s', array(
	has( $capped_html, '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">2 more are kept; they are listed here as these are deleted or put back in the queue.</p>' ),
	ends_with( $capped_html, $foot_line( 'queue' ) ),
), array( true, true ) );
ck( 'and none of the other cards prints one: the sponsor posts and the offers running low, full and empty, the interests, the duplicated students, the Sponsor Collaboration Agreements, full and empty, the sponsors strip and the sponsor applications', substr_count( $sp . $empty_sp . $low . $empty_low . $int . $dup . $sagr . $sagr_empty . $strip2 . $sapp, 'Open on the Institutions screen' ), 0 );

/* ---- each sponsor card links its wp-admin tab ---------------------------- */

echo "\n=== Each sponsor card ends with the way to its tab on the Sponsors screen ===\n";

// The same link, to the Sponsors screen's tab that lists the card's items, as the row action and as the
// body's last line, on every draw of the card, an empty one's too: the applications, the posts and the
// signed agreements wait on the queue tab, each in a card of its own there, and each card's link opens
// that card, as the Overview's counts do.
$sponsors_line = function ( $tab, $anchor = '' ) {
	return card_foot( tab_url( 'wpcpm-sponsors', $tab, $anchor ), 'Open on the Sponsors screen' );
};
$empty_sapp  = capture( static function () { WPCPM_Administrators_Cards::render_sponsor_applications( array() ); } );
$empty_int   = capture( static function () { WPCPM_Administrators_Cards::render_interests( array() ); } );
$empty_strip = capture( static function () { WPCPM_Administrators_Cards::render_sponsors_strip( array() ); } );
ck( 'the six sponsor cards each end with one line to the tab that holds their items, and after the fold the same link as their row action: the applications, the posts and the agreements the queue, each at its own card there, the offers running low Offers and codes, the interests Interests, the sponsors strip Sponsors', array(
	ends_with( $sapp, $sponsors_line( 'queue', 'wpcpm-sponsor-applications' ) ),
	ends_with( $sp, $sponsors_line( 'queue', 'wpcpm-sponsor-posts' ) ),
	ends_with( $sagr, $sponsors_line( 'queue', 'wpcpm-sponsor-agreements' ) ),
	ends_with( $low, $sponsors_line( 'offers' ) ),
	ends_with( $int, $sponsors_line( 'interests' ) ),
	ends_with( $strip2, $sponsors_line( 'sponsors' ) ),
	substr_count( $sapp . $sp . $sagr . $low . $int . $strip2, 'Open on the Sponsors screen' ),
), array( true, true, true, true, true, true, 12 ) );
ck( 'and so does each of them with nothing in it', array(
	ends_with( $empty_sapp, $sponsors_line( 'queue', 'wpcpm-sponsor-applications' ) ),
	ends_with( $empty_sp, $sponsors_line( 'queue', 'wpcpm-sponsor-posts' ) ),
	ends_with( $sagr_empty, $sponsors_line( 'queue', 'wpcpm-sponsor-agreements' ) ),
	ends_with( $empty_low, $sponsors_line( 'offers' ) ),
	ends_with( $empty_int, $sponsors_line( 'interests' ) ),
	ends_with( $empty_strip, $sponsors_line( 'sponsors' ) ),
	substr_count( $empty_sapp . $empty_sp . $sagr_empty . $empty_low . $empty_int . $empty_strip, 'Open on the Sponsors screen' ),
), array( true, true, true, true, true, true, 12 ) );
// The cards the private line adds have their tabs too: a sponsor's term is set on the Sponsors tab,
// where each sponsor's row opens its Edit form, and the reports have a tab of their own. The Events
// card, drawn only while Sponsor tiers is on, is pinned with its other checks (below).
ck( 'and so do the sponsor terms ending, to the Sponsors tab, and the sponsor reports, to the Reports tab, full and empty', array(
	ends_with( $tiers_ending_card, $sponsors_line( 'sponsors' ) ),
	ends_with( $empty_tiers_ending, $sponsors_line( 'sponsors' ) ),
	ends_with( $sr, $sponsors_line( 'reports' ) ),
	ends_with( $sr_none, $sponsors_line( 'reports' ) ),
), array( true, true, true, true ) );
ck( 'and no institution card prints it', substr_count( $apps . $agr_html . $rep . $req . implode( '', $empty_cards ) . $capped_html, 'Open on the Sponsors screen' ), 0 );

/* ---- the closed applications are counted, not loaded (FADMN-2) ----------- */

echo "\n=== A year of rejected applications costs a count, not their answers ===\n";

// The store behind the closed states is written by an anonymous public form and kept for a
// year (application_rejected_days 365, application_spam_days 30). Before this fix the card
// fetched every one of those rows as a WP_Post - priming _wpcpm_app_fields, the applicant's
// whole submitted form, for each - and threw all but fifty away to print one number.
// The fixture already holds one rejected application, so fifty-nine more make sixty.
for ( $i = 1; $i <= 59; $i++ ) {
	seed_post( 5100 + $i, 'wpcpm_inst_app', 'Rejected ' . $i, array( '_wpcpm_app_state' => 0 === $i % 2 ? 'spam' : 'rejected', '_wpcpm_app_country' => '', '_wpcpm_app_country_name' => '' ), '2026-08-01 08:00:00' );
}

$GLOBALS['loaded'] = 0;
$many              = WPCPM_Administrators_Cards::collect();

ck( 'sixty closed applications are reported and fifty of them are held', array( $many['applications']['closed_total'], count( $many['applications']['closed'] ) ), array( 60, 50 ) );
ck( 'and the whole page built fifty-two application objects: fifty closed and the two open ones', $GLOBALS['loaded'], 52 );

$many_html = capture( static function () use ( $many ) { WPCPM_Administrators_Cards::render_applications( $many['applications'] ); } );
ck( 'the card draws the fifty it holds, beside the two open ones, and says the other ten are kept', array( substr_count( $many_html, 'class="wpcpm-application wpcpm-ui-item"' ), has( $many_html, '10 more are kept; they are listed here as these are deleted or put back in the queue.' ) ), array( 52, true ) );

/* ---- the semester begins at the site's own midnight (P2) ----------------- */

echo "\n=== The claims window starts where the site is, not at Greenwich ===\n";

// Every site this plugin runs on has a timezone, and the cohort's first day is a calendar day
// in it. Measured from midnight UTC, a claim made on the last evening of last semester counted
// as this semester's on every site west of Greenwich - and the tile printed a figure nobody
// could reproduce by counting.
$GLOBALS['tz']  = 'America/Los_Angeles';
$semester_since = WPCPM_Cohort::range( WPCPM_Cohort::current() )['from'];
$west           = WPCPM_Administrators_Cards::sponsors();

// The fixture's four counted claims are placed by their UTC offset from the first day, and two
// of them (an hour and two hours in) were made on the evening before the semester began in
// Los Angeles. Under the old rule the window did not move with the site at all.
ck( 'the window moves with the site: four of these claims are this semester\'s at Greenwich and two are in Los Angeles', array( $data['sponsors']['claims_semester'], $west['claims_semester'] ), array( 4, 2 ) );

$last_evening             = ( new DateTimeImmutable( $semester_since . ' 00:00:00', new DateTimeZone( $GLOBALS['tz'] ) ) )->modify( '-30 minutes' );
$GLOBALS['claims'][943][] = array( 'u' => 37, 'i' => -1, 'at' => (int) $last_evening->format( 'U' ), 'v' => 0 );
$site_time                = WPCPM_Administrators_Cards::sponsors();

ck( 'and a claim at half past eleven on that last evening is last semester\'s', $site_time['claims_semester'], $west['claims_semester'] );
ck( 'the first day is named twice: Y-m-d for the readers of this array, the site\'s format for the sentence', array( $site_time['since'], $site_time['since_display'] ), array( $semester_since, gmdate( 'j F Y', strtotime( $semester_since . ' 00:00:00 UTC' ) ) ) );

$site_strip = capture( static function () use ( $site_time ) { WPCPM_Administrators_Cards::render_sponsors_strip( $site_time ); } );
ck( 'and the tile says the day the way the site writes days', array( has( $site_strip, 'since ' . $site_time['since_display'] . ', on every offer' ), has( $site_strip, 'since ' . $semester_since . ',' ) ), array( true, false ) );

// The Reports card asks the same question of the report class, and until the fix round it
// asked it at Greenwich: one page carrying two "this semester"s, a day apart on any site far
// enough from it (deep check, fix round). `collect()` is what the page calls, so this is the
// argument the report class really receives.
$GLOBALS['asked'] = array();
WPCPM_Administrators_Cards::collect();
$site_midnight = (int) ( new DateTimeImmutable( $semester_since . ' 00:00:00', new DateTimeZone( $GLOBALS['tz'] ) ) )->format( 'U' );
ck( 'the Reports card counts from the same midnight the Sponsors tile does', array( in_array( array( 'approved_since', $site_midnight ), $GLOBALS['asked'], true ), $site_midnight === (int) strtotime( $semester_since . ' 00:00:00 UTC' ) ), array( true, false ) );

array_pop( $GLOBALS['claims'][943] );

// East of Greenwich the site's midnight falls on the previous UTC day, so a sentence written
// with gmdate() names the day before the one the window starts on. Nothing pinned that until
// the fix round: the stub above formatted at Greenwich whatever the site's zone was, so a
// display computed the wrong way read the same as one computed the right way.
$GLOBALS['tz'] = 'Pacific/Auckland';
$east          = WPCPM_Administrators_Cards::sponsors();
$east_from     = (int) ( new DateTimeImmutable( $semester_since . ' 00:00:00', new DateTimeZone( $GLOBALS['tz'] ) ) )->format( 'U' );
$east_day      = gmdate( 'j F Y', (int) strtotime( $semester_since . ' 00:00:00 UTC' ) );

ck( 'the day in the sentence is written on the site\'s clock and not on Greenwich\'s', array( $east['since_display'], $east_day === gmdate( 'j F Y', $east_from ) ), array( $east_day, false ) );

$GLOBALS['tz'] = 'UTC';

echo "\n=== A track the program map is given has its tile (1.100.0) ===\n";

$GLOBALS['live_filters']['wpcpm_program_labels'][] = static function ( $labels ) {
	$labels['Research Track'] = 'Research Track';
	return $labels;
};
$GLOBALS['live_filters']['wpcpm_program_tracks'][] = static function ( $tracks ) {
	$tracks['Research Track'] = 'research';
	return $tracks;
};

ck( 'the tiles are the program map\'s tracks, the built-in four first', WPCPM_Administrators_Cards::tracks(), array( '150h' => 'WordPress Credits Program 150h', '50h' => 'WordPress Credits Program 50h', 'dev' => 'Developer Track', 'design' => 'Designer Track', 'research' => 'Research Track' ) );

ob_start();
WPCPM_Administrators_Cards::render_programs( array( 'tracks' => array(), 'finished' => 0, 'rows' => array(), 'quiet' => 0, 'read' => 0, 'semester' => '' ) );
$research_card = ob_get_clean();

ck( 'and the Programs running card draws a tile for the new one, counted from nothing', has( $research_card, '<span class="wpcpm-programs__name">Research Track</span><span class="wpcpm-programs__n">0</span>' ), true );

$GLOBALS['live_filters'] = array();

echo "\n=== The Events card: the tile, the card and its decisions (1.117.0) ===\n";

/**
 * The Events card for some data, or '' while the cards class does not draw one, so the
 * checks below fail as checks rather than the suite as a fatal before the card exists.
 *
 * @param array $events `waiting`, `upcoming`.
 * @return string
 */
function sponsor_events_card( array $events ) {
	return method_exists( 'WPCPM_Administrators_Cards', 'render_sponsor_events' ) ? capture( static function () use ( $events ) { WPCPM_Administrators_Cards::render_sponsor_events( $events ); } ) : '';
}

/**
 * Each form in some markup, keyed by its action and the event it names: its hidden fields in the
 * order printed, where it posts, whether it asks for a note, and whether it asks before it posts.
 *
 * @param string $html Markup.
 * @return array
 */
function sponsor_event_forms( $html ) {
	$out = array();
	preg_match_all( '#<form ([^>]*)>(.*?)</form>#s', (string) $html, $forms, PREG_SET_ORDER );
	foreach ( $forms as $form ) {
		preg_match_all( '#<input type="hidden" name="([^"]+)" value="([^"]*)" />#', $form[2], $fields );
		$hidden = array_combine( $fields[1], $fields[2] );
		$key    = ( isset( $hidden['action'] ) ? $hidden['action'] : '?' ) . ':' . ( isset( $hidden['wpcpm_sevent_id'] ) ? $hidden['wpcpm_sevent_id'] : '?' );

		$out[ $key ] = array(
			'hidden'  => $hidden,
			'posts'   => has( $form[1], 'method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once' ),
			'note'    => 1 === preg_match( '#<textarea id="wpcpm-sevent-(return|cancel)-[0-9]+" name="wpcpm_sevent_note" rows="2" maxlength="2000" required#', $form[2] ),
			'confirm' => has( $form[2], 'data-wpcpm-confirm="' ),
		);
	}
	return $out;
}

/** An event's facts, as the store's facts() answers them. */
function sponsor_event_facts( $id, $sponsor, $title, $description, $start, $minutes, $places, $state, $joined ) {
	return array(
		'id'          => $id,
		'sponsor'     => $sponsor,
		'title'       => $title,
		'description' => $description,
		'start'       => $start,
		'end'         => $start + $minutes * 60,
		'minutes'     => $minutes,
		'places'      => $places,
		'zone'        => 'Europe/Warsaw',
		'kind'        => 'co_hosted',
		'state'       => $state,
		'decision'    => array( 'at' => 0, 'by' => 0, 'note' => '' ),
		'revision'    => 0,
		'joined'      => $joined,
	);
}

// The manager reads every day and time on their own clock, Tokyo's here, while the site keeps
// Greenwich's. Two events wait, the one planned first (1401) on the later day, its sponsor's name one
// to escape, and the other of the sponsor set to Paused, which no longer offers events; two are
// approved, two people on the first, a name to escape among them, and the second of a sponsor whose
// term has ended.
$se_manage                             = $GLOBALS['manage'];
$se_rows                               = $GLOBALS['sponsor_rows'];
$GLOBALS['sponsor_rows']['recSPN00000000005'] = array( 'record_id' => 'recSPN00000000005', 'name' => 'Bold & <b>Co</b>', 'status' => 'Approved', 'dashboard_account' => true, 'tier' => 'partner' );
$GLOBALS['sponsor_rows']['recSPN00000000006'] = array( 'record_id' => 'recSPN00000000006', 'name' => 'Lapsed Co', 'status' => 'Approved', 'dashboard_account' => true, 'tier' => 'partner', 'tier_until' => '2026-01-31' );
$GLOBALS['manage']                     = array( 3 );
$GLOBALS['uid']                        = 3;
$GLOBALS['opts']['time_format']        = 'g:i a';
$GLOBALS['umeta'][3]['wpcpm_timezone'] = 'Asia/Tokyo';
$GLOBALS['users'][22]                  = new WP_User( 22, 'Zed <b>Joiner</b>', 'administrator@example.test' );
$se_long                               = str_repeat( 'Every <session> & "detail" of the day. ', 10 );
seed_post( 1401, 'wpcpm_sponsor_event', 'Q&A "live" <b>' );
seed_post( 1402, 'wpcpm_sponsor_event', 'Short webinar' );
seed_post( 1403, 'wpcpm_sponsor_event', 'Launch party' );
seed_post( 1404, 'wpcpm_sponsor_event', 'Lapsed launch' );
$GLOBALS['sevents']       = array( 'waiting' => array( 1402, 1401 ), 'upcoming' => array( 1403, 1404 ) );
$GLOBALS['sevent_facts']  = array(
	1401 => sponsor_event_facts( 1401, 'recSPN00000000005', 'Q&A "live" <b>', $se_long, (int) strtotime( '2026-11-10 19:00:00 UTC' ), 90, 0, 'planned', 0 ),
	1402 => sponsor_event_facts( 1402, 'recSPN00000000003', 'Short webinar', 'About <i>it</i>.', (int) strtotime( '2026-11-05 09:00:00 UTC' ), 45, 25, 'planned', 0 ),
	1403 => sponsor_event_facts( 1403, 'recSPN00000000001', 'Launch party', 'Cake.', (int) strtotime( '2026-12-01 10:00:00 UTC' ), 90, 40, 'approved', 2 ),
	1404 => sponsor_event_facts( 1404, 'recSPN00000000006', 'Lapsed launch', 'Still on.', (int) strtotime( '2026-12-03 10:00:00 UTC' ), 60, 10, 'approved', 0 ),
);
$GLOBALS['sevent_links']  = array( 1401 => 'https://call.example/room?pwd=a%2Fb&id=7', 1402 => 'https://meet.example/short', 1403 => 'https://meet.example/launch', 1404 => 'https://meet.example/lapsed' );
$GLOBALS['sevent_people'] = array( 1403 => array( 22, 3 ) );
$GLOBALS['sevent_offers'] = array( 'recSPN00000000003' => false, 'recSPN00000000006' => false );

$GLOBALS['tiers_live'] = false;
$GLOBALS['asked']      = array();
$se_off_data           = WPCPM_Administrators_Cards::collect();
$se_off_counts         = WPCPM_Administrators_Cards::counts( $se_off_data );
ck( 'while Sponsor tiers is off there is no tile and no card, and the event store is never asked', array(
	isset( $se_off_data['sponsor_events'] ) ? $se_off_data['sponsor_events'] : null,
	isset( $se_off_counts['sponsor_events'] ),
	count( $se_off_counts ),
	sponsor_events_card( array( 'waiting' => array( array( 'id' => 1401 ) ), 'upcoming' => array() ) ),
	in_array( array( 'sevent_waiting' ), $GLOBALS['asked'], true ) || in_array( array( 'sevent_upcoming' ), $GLOBALS['asked'], true ),
), array( array( 'waiting' => array(), 'upcoming' => array() ), false, 13, '', false ) );

$GLOBALS['tiers_live'] = true;
$GLOBALS['asked']      = array();
$se_data               = WPCPM_Administrators_Cards::collect();
$se_counts             = WPCPM_Administrators_Cards::counts( $se_data );
$se                    = isset( $se_data['sponsor_events'] ) ? $se_data['sponsor_events'] : array();
ck( 'switched on, collect() reads both lists through the event store: the waiting events, the one planned first first, and the approved ones', array(
	in_array( array( 'sevent_waiting' ), $GLOBALS['asked'], true ) && in_array( array( 'sevent_upcoming' ), $GLOBALS['asked'], true ),
	array_map( static function ( $row ) { return $row['id']; }, isset( $se['waiting'] ) ? $se['waiting'] : array() ),
	array_map( static function ( $row ) { return $row['id']; }, isset( $se['upcoming'] ) ? $se['upcoming'] : array() ),
	array_map( static function ( $row ) { return isset( $row['offered'] ) ? $row['offered'] : null; }, array_merge( isset( $se['waiting'] ) ? $se['waiting'] : array(), isset( $se['upcoming'] ) ? $se['upcoming'] : array() ) ),
), array( true, array( 1401, 1402 ), array( 1403, 1404 ), array( true, false, true, false ) ) );
ck( 'the counts gain a tile, Events waiting, right after the sponsor posts\' tile as its card follows theirs, counting what waits and naming the card', array(
	array_keys( $se_counts ),
	isset( $se_counts['sponsor_events'] ) ? $se_counts['sponsor_events'] : null,
), array(
	array( 'applications', 'agreements', 'reports', 'requests', 'mentor_applications', 'sponsor_applications', 'sponsor_posts', 'sponsor_events', 'sponsor_agreements', 'sponsor_reports', 'offers_low', 'duplicates', 'syncs', 'locked' ),
	array( 'label' => 'Events waiting', 'n' => 2, 'card' => 'sponsor-events' ),
) );
// The queue reads nothing while Sponsor tiers is off, so its count is 0 and the zero rule alone
// would keep the tile out: data that holds events waiting shows that the switch itself is asked.
$GLOBALS['tiers_live'] = false;
$se_off_with_events    = WPCPM_Administrators_Cards::counts( $se_data );
$GLOBALS['tiers_live'] = true;
ck( 'switched off, no tile even for data that holds events waiting: the switch keeps the tile out, not only its count', array_key_exists( 'sponsor_events', $se_off_with_events ), false );
ck( 'and with nothing waiting for the program\'s OK there is no tile, though the card is drawn for what is coming up', array_key_exists( 'sponsor_events', WPCPM_Administrators_Cards::counts( array_merge( $se_data, array( 'sponsor_events' => array( 'waiting' => array(), 'upcoming' => isset( $se['upcoming'] ) ? $se['upcoming'] : array() ) ) ) ) ), false );

$se_card = sponsor_events_card( $se );

$se_empty = sponsor_events_card( array( 'waiting' => array(), 'upcoming' => array() ) );
// The Sponsors screen lists the same events on its queue tab, in a card of their own, read-only: the
// card's way there is its row action, and the last line of its body, at that card, as the posts' is.
// With none waiting the row says so and what the card holds (1.123.93), as it stays on the Overview.
ck( 'its row: open while an event waits, saying how many wait, and folded with none, saying that nothing waits and how many events are coming up; after the fold the way to the Sponsors screen\'s queue tab, at the card that lists the events there', array( card_row( $se_card ), card_row( $se_empty ), card_row( sponsor_events_card( array( 'waiting' => array(), 'upcoming' => isset( $se['upcoming'] ) ? $se['upcoming'] : array() ) ) ) ), array(
	array( 'open' => true, 'state' => '2 waiting', 'action' => $to_sponsors( 'queue', 'wpcpm-sponsor-events' ) ),
	array( 'open' => false, 'state' => 'Nothing waiting, none coming up', 'action' => $to_sponsors( 'queue', 'wpcpm-sponsor-events' ) ),
	array( 'open' => false, 'state' => 'Nothing waiting, 2 coming up', 'action' => $to_sponsors( 'queue', 'wpcpm-sponsor-events' ) ),
) );
ck( 'and the card ends with the same link as a line, after all the queue class draws in it, whether an event waits or none does', array(
	ends_with( $se_card, $sponsors_line( 'queue', 'wpcpm-sponsor-events' ) ),
	ends_with( $se_empty, $sponsors_line( 'queue', 'wpcpm-sponsor-events' ) ),
), array( true, true ) );

// The Overview switched on and off, each with what counts() gives in the same state, which the head and
// the menu read; the same two with every queue holding something, the one sponsor offer waiting for
// review put back; and the Reference view, switched on. A student waits to graduate in all of them (1.123.93), on no
// institution's roster and with no account, so every queue but the one emptied holds something.
$GLOBALS['unlinked']   = pending_unlinked();
$GLOBALS['flash']      = array();
$se_page               = both_views();
$se_page_counts        = WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() );
$GLOBALS['tiers_live'] = false;
$se_page_off           = both_views();
$se_page_off_counts    = WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() );
$GLOBALS['tiers_live'] = true;
$GLOBALS['offer_reviews'] = array( array( 'id' => 931, 'record' => 'recSPN00000000001', 'company' => 'TEST Sponsor', 'title' => 'A year of hosting', 'change' => 'new', 'by' => 'Member One', 'at' => time() - 600, 'fields' => array() ) );
$se_full                  = both_views();
$GLOBALS['tiers_live']    = false;
$se_full_off              = both_views();
$GLOBALS['tiers_live']    = true;
unset( $GLOBALS['offer_reviews'] );
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_REFERENCE;
$se_ref = WPCPM_Administrators_Dashboard::render( array() );
unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ], $GLOBALS['unlinked'] );
ck( 'switched on, every count counts() gives leads to a card the Overview or Watch draws, in the order they draw them, the Events count between the sponsor posts\' and the Sponsor Collaboration Agreements\', and Syncs and health\'s two side by side; switched off, no count leads to that card and every count to a card drawn', array(
	count_cards( $se_page_counts ),
	array_values( array_diff( count_cards( $se_page_counts ), page_cards( $se_page ) ) ),
	array_values( array_intersect( page_cards( $se_page ), count_cards( $se_page_counts ) ) ) === array_values( array_unique( count_cards( $se_page_counts ) ) ),
	in_array( 'sponsor-events', count_cards( $se_page_off_counts ), true ),
	array_values( array_diff( count_cards( $se_page_off_counts ), page_cards( $se_page_off ) ) ),
), array(
	array( 'applications', 'agreements', 'reports', 'requests', 'graduation', 'sponsor-applications', 'sponsor-posts', 'sponsor-events', 'sponsor-agreements', 'sponsor-reports', 'offers-low', 'duplicates', 'health', 'health' ),
	array(),
	true,
	false,
	array(),
) );
/**
 * Each card's title on a page, by the card's anchor.
 *
 * @param string $html The page.
 * @return array<string, string>
 */
function card_titles( $html ) {
	preg_match_all( '#<section class="wpcpm-administrator__card" id="wpcpm-([a-z-]+)"><details[^>]*><summary[^>]*><h3 class="wpcpm-group__title">(?:<span id="[^"]+">)?(.*?)(?:</span>)? <span class="wpcpm-group__count">#', (string) $html, $titles );
	return array_combine( $titles[1], $titles[2] );
}

/**
 * Each count counts() gives that leads to a card, in its order: the card and the count's words, which
 * this page's strip printed and the Sponsor Dashboard's At a glance strip still prints.
 *
 * @param array $counts What `WPCPM_Administrators_Cards::counts()` returned.
 * @return string[][]
 */
function count_words( array $counts ) {
	$words = array();
	foreach ( $counts as $tile ) {
		if ( isset( $tile['card'] ) ) {
			$words[] = array( (string) $tile['card'], (string) $tile['label'] );
		}
	}
	return $words;
}
// A count says what it counts, in the words wp-admin names the queue by: the title of the card it
// leads to, as the page draws that card, then what the count is. Syncs and health is the one card whose
// two counts each count a part of what it holds, so each begins with what it counts: the syncs that
// need a look, and the locked accounts it lists. The titles are read off both views, which together
// draw every card.
$se_titles = card_titles( $se_full . $se_ref );
ck( 'each count\'s words begin with the title of the card it leads to, as the page draws it, the two counts of Syncs and health alone excepted', array(
	count_words( $se_page_counts ),
	array_values( array_map( static function ( $tile ) { return $tile[1]; }, array_filter( count_words( $se_page_counts ), static function ( $tile ) use ( $se_titles ) { return ! isset( $se_titles[ $tile[0] ] ) || 0 !== strpos( $tile[1], $se_titles[ $tile[0] ] ); } ) ) ),
), array(
	array(
		array( 'applications', 'Institution applications waiting' ),
		array( 'agreements', 'Collaboration Agreements to review, 1 overdue' ),
		array( 'reports', 'Semester reports to review, 1 due for drafting' ),
		array( 'requests', 'Requests from institutions open, 1 overdue' ),
		array( 'graduation', 'Students waiting to graduate' ),
		array( 'sponsor-applications', 'Sponsor applications waiting' ),
		array( 'sponsor-posts', 'Sponsor posts to review' ),
		array( 'sponsor-events', 'Events waiting' ),
		array( 'sponsor-agreements', 'Sponsor Collaboration Agreements to review' ),
		array( 'sponsor-reports', 'Sponsor reports to finish' ),
		array( 'offers-low', 'Offers running low' ),
		array( 'duplicates', 'Duplicated students' ),
		array( 'health', 'Syncs need a look' ),
		array( 'health', 'Locked accounts' ),
	),
	array( 'Syncs need a look', 'Locked accounts' ),
) );
// The block's description, which the block inserter shows, names the three groups and every card
// the page draws in each, by the card's title, the Overview's two and the Reference view's one, read
// with every queue holding something: a card added, moved or renamed without it fails. A
// title counts only as a whole entry of its group's list, between two of the list's separators (its
// start or end, a comma, a comma and "and", or "and"), so a title found only inside a longer one,
// Collaboration Agreements inside Sponsor Collaboration Agreements, does not name its card.
$described = array();
$named     = array(
	'waiting'   => 'Waiting for you',
	'watch'     => 'Watch',
	'reference' => 'The program',
);
$se_groups = array_merge( page_groups( $se_full ), page_groups( $se_ref ) );
foreach ( $se_groups as $group => $anchors ) {
	$held = isset( $named[ $group ] ) && 1 === preg_match( '/\b' . preg_quote( $named[ $group ], '/' ) . ' \(([^)]*)\)/', (string) $block['description'], $in_group ) ? strtolower( $in_group[1] ) : '';
	foreach ( $anchors as $anchor ) {
		$described[ $group ][ $anchor ] = isset( $se_titles[ $anchor ] ) && '' !== $held && 1 === preg_match( '/(?:^|, (?:and )?| and )' . preg_quote( strtolower( html_entity_decode( $se_titles[ $anchor ], ENT_QUOTES ) ), '/' ) . '(?=$|,| and )/', $held );
	}
}
$block_words = (string) $block['description'];
ck( 'the block\'s description names the three groups in the page\'s order and, in each, every card the page draws there, by its title', array(
	$described,
	false !== strpos( $block_words, 'Waiting for you (' ) && strpos( $block_words, 'Waiting for you (' ) < (int) strpos( $block_words, 'Watch (' ) && strpos( $block_words, 'Watch (' ) < (int) strpos( $block_words, 'The program (' ),
), array(
	array_map( static function ( $anchors ) { return array_fill_keys( $anchors, true ); }, $se_groups ),
	true,
) );
ck( 'on the Overview the card is drawn after Sponsor posts and Sponsor offers to review, and before the Sponsor Collaboration Agreements', in_order( $se_full, array( 'id="wpcpm-sponsor-posts"', 'id="wpcpm-sponsor-offers-review"', 'id="wpcpm-sponsor-events"', 'id="wpcpm-sponsor-agreements"' ) ), true );
// The Events card is a queue only while Sponsor tiers is on (`queues()`): while it is off the page says
// nothing of it, neither a card nor a count nor a name among the empty queues, with every other queue
// holding something or one of them empty.
ck( 'and not at all while Sponsor tiers is off: no card, no count, and the sentence that names the empty queues does not name it', array(
	has( $se_full_off, 'wpcpm-sponsor-events' ),
	has( $se_page_off, 'wpcpm-sponsor-events' ),
	array_key_exists( 'sponsor_events', $se_page_off_counts ),
	array_key_exists( 'Events', quiet_links( $se_page_off ) ),
), array( false, false, false, false ) );
ck( 'its place is in Waiting for you, between Sponsor offers to review and the Sponsor Collaboration Agreements, while Sponsor tiers is on, and the group has no card for it while it is off, its other cards as they are', array(
	page_groups( $se_full ),
	page_groups( $se_full_off ),
), array(
	array(
		'waiting' => array( 'applications', 'agreements', 'reports', 'requests', 'graduation', 'sponsor-applications', 'sponsor-posts', 'sponsor-offers-review', 'sponsor-events', 'sponsor-agreements', 'sponsor-reports' ),
		'watch'   => array( 'offers-low', 'duplicates', 'interests', 'tiers-ending', 'health' ),
	),
	array(
		'waiting' => array( 'applications', 'agreements', 'reports', 'requests', 'graduation', 'sponsor-applications', 'sponsor-posts', 'sponsor-offers-review', 'sponsor-agreements', 'sponsor-reports' ),
		'watch'   => array( 'offers-low', 'duplicates', 'interests', 'tiers-ending', 'health' ),
	),
) );
// Switched on, with every queue holding something the Waiting group ends with its last card; with the
// sponsor offers' queue alone empty, the sentence names that one queue, in the singular.
ck( 'switched on, with every queue holding something no sentence names an empty queue, and with one queue empty the sentence names it alone, in the singular, its name a link to its card on the Sponsors screen', array(
	has( $se_full, 'wpcpm-ui-group__note' ),
	1 === preg_match( '#<p class="wpcpm-ui-group__note">.*?</p>#s', $se_page, $se_one ) ? $se_one[0] : '',
	page_cards( $se_page ) === array_values( array_diff( page_cards( $se_full ), array( 'sponsor-offers-review' ) ) ),
), array(
	false,
	'<p class="wpcpm-ui-group__note">Nothing waits in the 1 other queue: <a href="' . tab_url( 'wpcpm-sponsors', 'queue', 'wpcpm-sponsor-offers-review' ) . '">Sponsor offers to review</a>. Its name opens its screen in wp-admin.</p>',
	true,
) );

$GLOBALS['flash'] = array( 'institutions' => 'sevent-approved' );
$se_said          = WPCPM_Administrators_Dashboard::render( array() );
$GLOBALS['flash'] = array( 'institutions' => 'sevent-note-empty' );
$se_refused       = WPCPM_Administrators_Dashboard::render( array() );
ck( 'a decision on the card comes back with its sentence in the queue\'s words, in its tone', array(
	has( $se_said, '<p class="wpcpm-dashboard__message wpcpm-dashboard__message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>The event is approved.</p>' ),
	has( $se_refused, '<p class="wpcpm-dashboard__message wpcpm-dashboard__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>Nothing was changed. Write a note for the sponsor: it is the whole of what the sponsor is told.</p>' ),
), array( true, true ) );
$GLOBALS['flash'] = array();

/* ---- the Events card stays on the Overview when no event waits ----------- */

echo "\n=== The Events card stays on the Overview when no event waits (1.123.93) ===\n";

// The owner's decision of 15 November 2026: the Events card holds the events coming up, with Join or Leave, Change
// the event and Cancel, and "Plan an event of the program's", so it stays on the Overview whether or not an event
// waits for the program's OK. Its head then says nothing waits and what it holds, with no red tag and the cue
// "Open"; the head, the menu and the counts count only what waits. Each page is the Overview on the parts, as the
// site draws it.
$st_kept  = array( $GLOBALS['sevents'], $GLOBALS['posts'][1401]->post_date_gmt, $GLOBALS['posts'][1402]->post_date_gmt );
$st_page  = static function ( array $sevents ) {
	$GLOBALS['sevents'] = $sevents;
	WPCPM_UI::set_live( array( 'administrator' ) );
	$page = WPCPM_Administrators_Dashboard::render( array() );
	WPCPM_UI::set_live( array() );
	return $page;
};
// The Events card's section, whole: from its start to the end tag that closes it.
$st_card  = static function ( $page ) {
	$page  = (string) $page;
	$at    = strpos( $page, '<section class="wpcpm-administrator__card wpcpm-ui-fold-card" id="wpcpm-sponsor-events">' );
	if ( false === $at ) {
		return '';
	}
	$depth = 0;
	$k     = $at;
	do {
		$open  = strpos( $page, '<section', $k + 1 );
		$close = strpos( $page, '</section>', $k + 1 );
		if ( false === $close ) {
			return '';
		}
		if ( false !== $open && $open < $close ) {
			++$depth;
			$k = $open;
		} else {
			--$depth;
			$k = $close;
		}
	} while ( $depth >= 0 );
	return substr( $page, $at, $k + strlen( '</section>' ) - $at );
};
$st_head  = static function ( $card ) {
	return 1 === preg_match( '#<summary class="wpcpm-ui-fold__head">(.*?)</summary>#s', (string) $card, $m ) ? $m[1] : '';
};
$st_title = static function ( $page ) {
	return 1 === preg_match( '#<header class="wpcpm-head">.*?</header>#s', (string) $page, $m ) ? $m[0] : '';
};
// The head's start, up to the chevron, whose icon markup is the icon set's.
$st_lead  = static function ( $head ) {
	return (string) strstr( (string) $head, '<span class="wpcpm-ui-fold__chev"', true );
};

$st_coming = $st_page( array( 'waiting' => array(), 'upcoming' => array( 1403 ) ) );
$st_none   = $st_page( array( 'waiting' => array(), 'upcoming' => array() ) );
$st_two    = $st_page( array( 'waiting' => array(), 'upcoming' => array( 1403, 1404 ) ) );
$GLOBALS['tiers_live'] = false;
$st_off    = $st_page( array( 'waiting' => array(), 'upcoming' => array( 1403 ) ) );
$GLOBALS['tiers_live'] = true;
$st_coming_card = $st_card( $st_coming );
$st_none_card   = $st_card( $st_none );
ck( 'no event waits and one comes up: the Events card is drawn once, at its place between Sponsor offers to review and the Sponsor Collaboration Agreements, folded', array(
	substr_count( $st_coming, 'id="wpcpm-sponsor-events"' ),
	in_order( $st_coming, array( 'id="wpcpm-sponsor-posts"', 'id="wpcpm-sponsor-events"', 'id="wpcpm-sponsor-agreements"' ) ),
	has( $st_coming_card, '<details class="wpcpm-ui-fold">' ),
	'' !== $st_coming_card,
), array( 1, true, true, true ) );
ck( 'its head: the title, the phrase that nothing waits and one event is coming up, no red tag, the cue Open, the help slot', array(
	$st_lead( $st_head( $st_coming_card ) ),
	has( $st_head( $st_coming_card ), 'wpcpm-status' ),
	$st_lead( $st_head( $st_card( $st_two ) ) ),
), array(
	'<h3 class="wpcpm-ui-fold__title" id="wpcpm-sponsor-events-title">Events</h3><span class="wpcpm-ui-fold__phrase">Nothing waiting, 1 coming up</span><span class="wpcpm-ui-fold__end"><span class="wpcpm-ui-fold__cue" aria-hidden="true">Open</span><span class="wpcpm-ui-fold__help-slot" aria-hidden="true"></span></span>',
	false,
	'<h3 class="wpcpm-ui-fold__title" id="wpcpm-sponsor-events-title">Events</h3><span class="wpcpm-ui-fold__phrase">Nothing waiting, 2 coming up</span><span class="wpcpm-ui-fold__end"><span class="wpcpm-ui-fold__cue" aria-hidden="true">Open</span><span class="wpcpm-ui-fold__help-slot" aria-hidden="true"></span></span>',
) );
ck( 'the other cards keep the cue Review, and the sentence that names the empty queues no longer names Events', array(
	array_values( array_unique( array_map( static function ( $cue ) { return html_entity_decode( $cue, ENT_QUOTES ); }, preg_match_all( '#<span class="wpcpm-ui-fold__cue" aria-hidden="true">([^<]*)</span>#', str_replace( $st_coming_card, '', $st_coming ), $st_cues ) ? $st_cues[1] : array() ) ) ),
	array_key_exists( 'Events', quiet_links( $st_coming ) ),
	array_key_exists( 'Events', quiet_links( $st_none ) ),
	array_keys( quiet_links( $st_coming ) ) === array_keys( quiet_links( $st_off ) ),
), array( array( 'Review' ), false, false, true ) );
ck( 'the head of the page, its count and its oldest, and the menu\'s count are the ones of the same page with no Events queue at all: the card adds nothing to any count', array(
	$st_title( $st_coming ) === $st_title( $st_off ),
	menu_items( $st_coming )['Overview']['badge'] ?? null,
	array_key_exists( 'sponsor_events', WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() ) ),
), array( true, menu_items( $st_off )['Overview']['badge'] ?? 'no badge', false ) );
// The body is the card's own, as its renderer draws it for the same events away from the Overview: this suite has no
// planner, so the plan, "Plan an event of the program's", is checked drawn on an empty card in
// bin/test-sponsor-event-queue.php, which loads the real one.
$st_body = static function ( $card ) {
	return (string) strstr( (string) $card, '</summary>' );
};
$st_alone = static function ( array $sevents ) {
	$GLOBALS['sevents'] = $sevents;
	WPCPM_UI::set_live( array( 'administrator' ) );
	WPCPM_UI::enter( 'administrator' );
	ob_start();
	WPCPM_Administrators_Cards::render_sponsor_events( WPCPM_Sponsor_Event_Queue::data() );
	$card = (string) ob_get_clean();
	WPCPM_UI::leave();
	WPCPM_UI::set_live( array() );
	return $card;
};
ck( 'inside, today\'s body: the empty line of what waits, the event coming up with its Decide area, the foot link, byte for byte what the card draws for the same events on its own', array(
	has( $st_coming_card, 'Nothing is waiting for the program&#039;s OK.' ),
	has( $st_coming_card, 'No event is coming up.' ),
	has( $st_coming_card, 'id="wpcpm-sponsor-event-1403"' ),
	has( $st_coming_card, 'id="wpcpm-decide-event-1403"' ),
	has( $st_coming_card, 'tab=queue#wpcpm-sponsor-events' ),
	$st_body( $st_coming_card ) === $st_body( $st_alone( array( 'waiting' => array(), 'upcoming' => array( 1403 ) ) ) ),
	'' !== $st_body( $st_coming_card ),
), array( true, false, true, true, true, true, true ) );
ck( 'no event at all: the card is drawn with its two empty lines, its head saying no event is coming up, its body the card\'s own', array(
	substr_count( $st_none, 'id="wpcpm-sponsor-events"' ),
	$st_lead( $st_head( $st_none_card ) ),
	has( $st_none_card, 'Nothing is waiting for the program&#039;s OK.</p><p class="wpcpm-ui-list__empty">No event is coming up.</p>' ),
	has( $st_none_card, '<article ' ),
	$st_body( $st_none_card ) === $st_body( $st_alone( array( 'waiting' => array(), 'upcoming' => array() ) ) ),
	$st_title( $st_none ) === $st_title( $st_off ),
), array(
	1,
	'<h3 class="wpcpm-ui-fold__title" id="wpcpm-sponsor-events-title">Events</h3><span class="wpcpm-ui-fold__phrase">Nothing waiting, none coming up</span><span class="wpcpm-ui-fold__end"><span class="wpcpm-ui-fold__cue" aria-hidden="true">Open</span><span class="wpcpm-ui-fold__help-slot" aria-hidden="true"></span></span>',
	true,
	false,
	true,
	true,
) );
list( $st_outside, $st_parts ) = parts_outside_switch( $st_coming );
ck( 'and every part the page draws sits inside the switch\'s root', array( $st_parts > 0, $st_outside ), array( true, array() ) );

// An event waits: the card is byte for byte what 1.123.92 drew. Pinned as a digest of the card's section, the
// number of days in its red tag written as N: the tag counts from the posts' fixed dates to now. Drawn with the two
// waiting posts' dates moved a day earlier, a day later and 400 days earlier, the digest does not move.
$st_digest = static function ( $card ) {
	return md5( (string) preg_replace( '#(<span class="wpcpm-status wpcpm-status--red">)\d[\d,]*( days? waiting</span>)#', '${1}N${2}', (string) $card ) );
};
$st_waits  = $st_card( $st_page( $st_kept[0] ) );
$st_moved  = array();
foreach ( array( '-1 day', '+1 day', '-400 days' ) as $st_shift ) {
	$GLOBALS['posts'][1401]->post_date_gmt = gmdate( 'Y-m-d H:i:s', (int) strtotime( $st_kept[1] . ' UTC ' . $st_shift ) );
	$GLOBALS['posts'][1402]->post_date_gmt = gmdate( 'Y-m-d H:i:s', (int) strtotime( $st_kept[2] . ' UTC ' . $st_shift ) );
	$st_moved[] = $st_digest( $st_card( $st_page( $st_kept[0] ) ) );
}
$GLOBALS['posts'][1401]->post_date_gmt = $st_kept[1];
$GLOBALS['posts'][1402]->post_date_gmt = $st_kept[2];
ck( 'an event waits: the card is byte for byte 1.123.92\'s, its head "2 waiting" with the red tag and the cue Review, on any day', array(
	$st_digest( $st_waits ),
	array_values( array_unique( $st_moved ) ) === array( $st_digest( $st_waits ) ),
	1 === preg_match( '#<span class="wpcpm-ui-fold__phrase">2 waiting</span><span class="wpcpm-ui-fold__end"><span class="wpcpm-status wpcpm-status--red">\d+ days waiting</span><span class="wpcpm-ui-fold__cue" aria-hidden="true">Review</span>#', $st_head( $st_waits ) ),
), array( '35e5503389ba00be21e6daaa169c2781', true, true ) );

// The outcome of a decision held for the Events card, with no event left waiting: the card is drawn once, open, the
// outcome at the top of its body.
$_POST = array( WPCPM_Return::FIELD => 'dashboard', WPCPM_Return::ANCHOR_FIELD => 'sponsor-events' );
WPCPM_Return::url( 'https://example.test/wp-admin/' );
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'sevent-approved' );
$st_held      = $st_page( array( 'waiting' => array(), 'upcoming' => array( 1403 ) ) );
$_POST        = array();
$st_held_card = $st_card( $st_held );
ck( 'the outcome held for the Events card with no event waiting: the card is drawn once, open, its outcome said once, inside it', array(
	substr_count( $st_held, 'id="wpcpm-sponsor-events"' ),
	has( $st_held_card, '<details class="wpcpm-ui-fold" open>' ),
	substr_count( $st_held, 'data-wpcpm-card-message' ),
	has( $st_held_card, 'data-wpcpm-card-message>The event is approved.</p>' ),
	array_key_exists( 'Events', quiet_links( $st_held ) ),
), array( 1, true, 1, true, false ) );
$GLOBALS['flash']   = array();
$GLOBALS['sevents'] = $st_kept[0];

/* ---- each link to wp-admin names its card for a screen reader ------------ */

echo "\n=== A card's links to its tab say which card they come from ===\n";

// Thirteen cards link to their tab in one of two sentences, and a screen reader can list a page's
// links out of their rows: so the row action and the body's last line each carry the card's title
// after their words, as "for the Events card", inside the link and hidden from sight. Every check
// above reads what a manager sees, with those words set aside (`capture()`), and passes as it did
// before them: no visible word moved. These read the words themselves, from the card as drawn, here,
// where the Events card is drawn too.
ck( 'one sponsor card and one institution card, word for word: Sponsor applications and Institution applications each carry their title, as "for the ... card", after the words of their row action and of their last line, the space before it inside the hidden span', array(
	'sponsor-applications' => hidden_words( static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsor_applications( $data['sponsor_applications'] ); } ),
	'applications'         => hidden_words( static function () use ( $data ) { WPCPM_Administrators_Cards::render_applications( $data['applications'] ); } ),
), array(
	'sponsor-applications' => array( 'for the Sponsor applications card', 'for the Sponsor applications card' ),
	'applications'         => array( 'for the Institution applications card', 'for the Institution applications card' ),
) );
ck( 'and the requests card\'s links carry its name, after the words of its row action and of its last line', hidden_words( static function () use ( $data ) { WPCPM_Administrators_Cards::render_requests( $data['requests'] ); } ), array( 'for the Requests from institutions card', 'for the Requests from institutions card' ) );
// Each card names its title twice, for its heading and for its links: read from the heading as drawn,
// so a title changed in one place and not the other fails here.
$thirteen = array(
	'applications'         => static function () use ( $data ) { WPCPM_Administrators_Cards::render_applications( $data['applications'] ); },
	'agreements'           => static function () use ( $data ) { WPCPM_Administrators_Cards::render_agreements( $data['agreements'] ); },
	'reports'              => static function () use ( $data ) { WPCPM_Administrators_Cards::render_reports( $data['reports'] ); },
	'requests'             => static function () use ( $data ) { WPCPM_Administrators_Cards::render_requests( $data['requests'] ); },
	'sponsor-applications' => static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsor_applications( $data['sponsor_applications'] ); },
	'sponsor-posts'        => static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsor_posts( $data['sponsor_posts'] ); },
	'sponsor-events'       => static function () use ( $se ) { WPCPM_Administrators_Cards::render_sponsor_events( $se ); },
	'sponsor-agreements'   => static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsor_agreements( $data['sponsor_agreements'] ); },
	'sponsor-reports'      => static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsor_reports( $data['sponsor_reports'] ); },
	'offers-low'           => static function () use ( $data ) { WPCPM_Administrators_Cards::render_offers_low( $data['offers_low'] ); },
	'interests'            => static function () use ( $data ) { WPCPM_Administrators_Cards::render_interests( $data['interests'] ); },
	'sponsors'             => static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsors_strip( $data['sponsors'] ); },
	'tiers-ending'         => static function () use ( $data ) { WPCPM_Administrators_Cards::render_tiers_ending( $data['tiers_ending'] ); },
);
$heading_twice = static function ( $draw ) {
	return 1 === preg_match( '#<h3 class="wpcpm-group__title">(?:<span id="[^"]+">)?([^<]+?)(?:</span>)?(?: <span class="wpcpm-group__count">[^<]*</span>)?</h3>#', capture( $draw ), $heading ) ? array_fill( 0, 2, 'for the ' . $heading[1] . ' card' ) : array( 'no heading', 'no heading' );
};
ck( 'and every one of the thirteen cards with a home on the Institutions or the Sponsors screen carries in both links the title its heading prints, as "for the ... card"', array_map( 'hidden_words', $thirteen ), array_map( $heading_twice, $thirteen ) );

$GLOBALS['tiers_live']   = false;
$GLOBALS['manage']       = $se_manage;
$GLOBALS['sponsor_rows'] = $se_rows;
unset( $GLOBALS['umeta'][3]['wpcpm_timezone'], $GLOBALS['users'][22] );

/* ---- the sponsor queues count what waits ---------------------------------- */

echo "\n=== Each sponsor card and its tile count what waits ===\n";

// A sponsor queue is read twice: the rows its card draws, the oldest LIMIT, and how many wait, read
// as the Sponsors screen counts the same queue: the posts and the signed agreements one more than
// its ceiling, the applications in full. The count counts() gives the queue, which the head and the
// menu read and the strip's tile printed, the number beside the card's title, the words after it and
// the line under a cut list come from the second, the rows from the first. Every check reads the three
// queues apart, through the Overview a manager opens, as a manager sees it (`seen()`).
$limit        = WPCPM_Administrators_Cards::LIMIT;
$ceiling      = WPCPM_Sponsors::COUNT_MAX;
$saved_queues = array( $GLOBALS['sponsor_posts'], $GLOBALS['agr_review'], $GLOBALS['agr_facts'], $GLOBALS['sponsor_apps'] );

// The sponsor queues holding $posts, $agreements and $applications, oldest first, the last two as
// many as the posts when they are not given, each row in the shape its class's read hands the
// cards: a post's facts, a signed agreement's post ID with the facts its card asks for, and an
// application's facts.
$sponsor_queues = static function ( $posts, $agreements = null, $applications = null ) use ( $saved_queues ) {
	$GLOBALS['sponsor_posts'] = array();
	$GLOBALS['agr_review']    = array();
	$GLOBALS['sponsor_apps']  = array();

	for ( $i = 0; $i < $posts; $i++ ) {
		$GLOBALS['sponsor_posts'][] = array_merge( $saved_queues[0][0], array( 'id' => 7000 + $i, 'title' => 'Post ' . $i ) );
	}

	for ( $i = 0; $i < ( null === $agreements ? $posts : $agreements ); $i++ ) {
		$GLOBALS['agr_review'][]           = 8000 + $i;
		$GLOBALS['agr_facts'][ 8000 + $i ] = array_merge( $saved_queues[2][913], array( 'post_id' => 8000 + $i ) );
	}

	for ( $i = 0; $i < ( null === $applications ? $posts : $applications ); $i++ ) {
		$GLOBALS['sponsor_apps'][] = array_merge( $saved_queues[3][0], array( 'id' => 9000 + $i, 'reference' => sprintf( 'SAPP-2026-%04d', 9000 + $i ) ) );
	}
};

// Each sponsor card as the Overview draws it for a manager, as a manager sees it, '' while it draws
// none, as it draws none for an empty queue; the count counts() gives its queue in the same state, in
// the words the strip's tile printed it in, '' while it gives none, as it gives none at zero; and the
// queues the Overview names as empty, each with its link.
$sponsor_cards = static function () {
	$GLOBALS['uid']    = 3;
	$GLOBALS['manage'] = array( 3 );
	$page              = seen( WPCPM_Administrators_Dashboard::render( array() ) );
	$counts            = WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() );
	$drawn             = array();

	foreach ( array( 'posts' => 'sponsor-posts', 'agreements' => 'sponsor-agreements', 'applications' => 'sponsor-applications' ) as $queue => $id ) {
		preg_match( '#<section class="wpcpm-administrator__card" id="wpcpm-' . $id . '">.*?</section>#s', $page, $card );
		$tile            = isset( $counts[ 'sponsor_' . $queue ] ) ? $counts[ 'sponsor_' . $queue ] : null;
		$drawn[ $queue ] = array(
			'card'  => isset( $card[0] ) ? $card[0] : '',
			'tile'  => null === $tile ? '' : ( isset( $tile['shown'] ) ? (string) $tile['shown'] : number_format_i18n( (int) $tile['n'] ) ),
			'quiet' => quiet_links( $page ),
		);
	}

	return $drawn;
};

// What each sponsor card counts: its queue's count, the number beside its title, the words after
// it on its row, and how many of its queue's items it lists.
$sponsor_counts = static function () use ( $sponsor_cards ) {
	// Each item as the parts draw it, since the queues' old branches left (Task 18b).
	$item = array(
		'posts'        => '<article class="wpcpm-sponsor-post wpcpm-ui-item" ',
		'agreements'   => '<span class="wpcpm-status">Waiting for review</span>',
		'applications' => '<article class="wpcpm-sponsor-application wpcpm-ui-item" ',
	);
	$said = array();

	foreach ( $sponsor_cards() as $queue => $drawn ) {
		preg_match( '#<span class="wpcpm-group__count">([^<]*)</span>#', $drawn['card'], $badge );
		preg_match( '#<span class="wpcpm-sponsor__state">([^<]*)</span>#', $drawn['card'], $state );
		$said[ $queue ] = array( $drawn['tile'], isset( $badge[1] ) ? $badge[1] : '', isset( $state[1] ) ? $state[1] : '', substr_count( $drawn['card'], $item[ $queue ] ) );
	}

	return $said;
};

// The lines under each sponsor card's list that link nowhere, in the order printed.
$sponsor_lines = static function () use ( $sponsor_cards ) {
	$said = array();

	foreach ( $sponsor_cards() as $queue => $drawn ) {
		// The parts' muted note (`WPCPM_UI::intro()`), since the cards' old branches left (Task 18b).
		preg_match_all( '#<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">([^<]*)</p>#', $drawn['card'], $more );
		$said[ $queue ] = $more[1];
	}

	return $said;
};

// The same thing said by each of the three cards.
$alike = static function ( array $said ) {
	return array(
		'posts'        => $said,
		'agreements'   => $said,
		'applications' => $said,
	);
};

// Each card drawn from its rows alone, the way the page drew it before its count was read apart:
// the agreements card with the one agreement out of force the fixture holds. Drawn as the Overview
// draws a queue's card, folded with the Review cue, and with the age its row on the page says, which
// the checks of the Overview's rows read against the fixture's own dates.
$sponsor_alone = static function ( array $ages = array() ) {
	$review = array();

	foreach ( $GLOBALS['agr_review'] as $id ) {
		$review[] = $GLOBALS['agr_facts'][ $id ];
	}

	$as_row = static function ( $anchor, $draw ) use ( $ages ) {
		WPCPM_Dashboard_Card::fold( true, 'Review' );
		WPCPM_Dashboard_Card::age( $anchor, isset( $ages[ $anchor ] ) ? (string) $ages[ $anchor ] : '' );
		$card = capture( $draw );
		WPCPM_Dashboard_Card::age( $anchor, '' );
		WPCPM_Dashboard_Card::fold( false );
		return $card;
	};

	return array(
		'posts'        => $as_row( 'wpcpm-sponsor-posts', static function () { WPCPM_Administrators_Cards::render_sponsor_posts( $GLOBALS['sponsor_posts'] ); } ),
		'agreements'   => $as_row( 'wpcpm-sponsor-agreements', static function () use ( $review ) { WPCPM_Administrators_Cards::render_sponsor_agreements( array( 'review' => $review, 'revoked' => array( $GLOBALS['agr_facts'][880] ) ) ); } ),
		'applications' => $as_row( 'wpcpm-sponsor-applications', static function () { WPCPM_Administrators_Cards::render_sponsor_applications( $GLOBALS['sponsor_apps'] ); } ),
	);
};
// The age each card's row on the page says, by its anchor.
$ages_on = static function ( array $cards ) {
	$ages = array();

	foreach ( $cards as $card ) {
		$marks = row_marks( $card );

		foreach ( $marks as $anchor => $row ) {
			$ages[ 'wpcpm-' . $anchor ] = (string) $row['age'];
		}
	}

	return $ages;
};

$sponsor_queues( 0 );
$none_drawn  = $sponsor_cards();
$none_counts = $sponsor_counts();
$none_lines  = $sponsor_lines();
$none_page   = array_map( static function ( $drawn ) { return $drawn['card']; }, $none_drawn );
$sponsor_queues( 3 );
$three_counts = $sponsor_counts();
$three_lines  = $sponsor_lines();
$three_page   = array_map( static function ( $drawn ) { return $drawn['card']; }, $sponsor_cards() );
$three_alone  = $sponsor_alone( $ages_on( $three_page ) );
ck( 'with nothing waiting no sponsor card is drawn and no count is given, the sentence that names the empty queues naming each of the three with the way to its card on the Sponsors screen; with three waiting and three listed, each card, its row\'s words and its count say what the card lists, as before',
	array( $none_counts, array_intersect_key( $none_drawn['posts']['quiet'], array_flip( array( 'Sponsor applications', 'Sponsor posts to review', 'Sponsor Collaboration Agreements' ) ) ), $three_counts ),
	array(
		$alike( array( '', '', '', 0 ) ),
		array(
			'Sponsor applications'             => tab_url( 'wpcpm-sponsors', 'queue', 'wpcpm-sponsor-applications' ),
			'Sponsor posts to review'          => tab_url( 'wpcpm-sponsors', 'queue', 'wpcpm-sponsor-posts' ),
			'Sponsor Collaboration Agreements' => tab_url( 'wpcpm-sponsors', 'queue', 'wpcpm-sponsor-agreements' ),
		),
		$alike( array( '3', '3', '3 waiting', 3 ) ),
	) );
ck( 'and no card says any more under its list', array( $none_lines, $three_lines ), array( $alike( array() ), $alike( array() ) ) );
ck( 'and each card the Overview draws is byte for byte the card drawn from its rows alone, folded with the Review cue and the age its row says, which each of the three says; with nothing waiting it draws none', array(
	$none_page,
	$three_page === $three_alone,
	array_keys( array_filter( $ages_on( $three_page ), static function ( $age ) { return '' === $age; } ) ),
), array( array_fill_keys( array( 'posts', 'agreements', 'applications' ), '' ), true, array() ) );

$sponsor_queues( $limit + 7 );
ck( 'with seven more waiting than a card lists, each card lists the oldest LIMIT, and its tile, the number beside its title and the words after it count every one waiting',
	$sponsor_counts(),
	$alike( array( (string) ( $limit + 7 ), (string) ( $limit + 7 ), ( $limit + 7 ) . ' waiting', $limit ) ) );
$cut_cards = $sponsor_cards();
ck( 'and the line under each list says seven more are waiting',
	$sponsor_lines(),
	$alike( array( '7 more are waiting; they are listed here as these are decided.' ) ) );
ck( 'the line sits right under the list it speaks of: before the way to the Sponsors screen, and in the agreements card above the agreements out of force', array(
	has( $cut_cards['posts']['card'], '</article><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">7 more are waiting; they are listed here as these are decided.</p><p class="wpcpm-ui-fold__foot"><a href=' ),
	has( $cut_cards['agreements']['card'], '</article><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">7 more are waiting; they are listed here as these are decided.</p>' . WPCPM_UI::heading( 'Out of force', 'h4' ) ),
	has( $cut_cards['applications']['card'], '</article><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">7 more are waiting; they are listed here as these are decided.</p><p class="wpcpm-ui-fold__foot"><a href=' ),
), array( true, true, true ) );

$sponsor_queues( $limit + 1 );
ck( 'one more waiting than a card lists is said in the singular',
	$sponsor_lines(),
	$alike( array( '1 more is waiting; it is listed here as these are decided.' ) ) );

// The three queues of three sizes on one page, so a count wired to another queue's tile or card
// cannot pass: the posts seven past the cut, the agreements one, the applications all listed.
$sponsor_queues( $limit + 7, $limit + 1, 3 );
ck( 'with the three queues of three sizes, each card and its tile count their own queue: the posts and the agreements cut at the oldest LIMIT, the applications listed whole',
	$sponsor_counts(),
	array(
		'posts'        => array( (string) ( $limit + 7 ), (string) ( $limit + 7 ), ( $limit + 7 ) . ' waiting', $limit ),
		'agreements'   => array( (string) ( $limit + 1 ), (string) ( $limit + 1 ), ( $limit + 1 ) . ' waiting', $limit ),
		'applications' => array( '3', '3', '3 waiting', 3 ),
	) );
ck( 'and each says its own under its list: seven more posts, one more agreement, nothing under the applications',
	$sponsor_lines(),
	array(
		'posts'        => array( '7 more are waiting; they are listed here as these are decided.' ),
		'agreements'   => array( '1 more is waiting; it is listed here as these are decided.' ),
		'applications' => array(),
	) );

// Past the ceiling the posts' and the agreements' reads stop at one more than it, so the page knows
// only that more than the ceiling wait. The applications are counted in full, as the Sponsors
// screen's applications card counts them, so their number stands at any size.
$sponsor_queues( $ceiling + 50 );
ck( 'past the Sponsors screen\'s ceiling the posts\' and the agreements\' tiles, the numbers beside their titles and the words after them print the ceiling with a plus sign, and the applications\' every one waiting',
	$sponsor_counts(),
	array(
		'posts'        => array( $ceiling . '+', $ceiling . '+', $ceiling . '+ waiting', $limit ),
		'agreements'   => array( $ceiling . '+', $ceiling . '+', $ceiling . '+ waiting', $limit ),
		'applications' => array( (string) ( $ceiling + 50 ), (string) ( $ceiling + 50 ), ( $ceiling + 50 ) . ' waiting', $limit ),
	) );
// The posts' and the agreements' lines say the least of it, never the read less the rows; the
// applications' line says exactly how many more.
$GLOBALS['plural_for'] = array();
ck( 'and the line under the posts\' and the agreements\' lists gives the ceiling less the rows drawn, with the plus sign, and under the applications\' the exact rest',
	$sponsor_lines(),
	array(
		'posts'        => array( ( $ceiling - $limit ) . '+ more are waiting; they are listed here as these are decided.' ),
		'agreements'   => array( ( $ceiling - $limit ) . '+ more are waiting; they are listed here as these are decided.' ),
		'applications' => array( ( $ceiling + 50 - $limit ) . ' more are waiting; they are listed here as these are decided.' ),
	) );
// In the order the page draws the cards: the applications, the posts, the agreements.
ck( 'and each line\'s singular or plural is chosen by the number it prints: the exact rest for the applications, and for the posts and the agreements the ceiling less the rows, not the read less them',
	isset( $GLOBALS['plural_for']['%s more is waiting; it is listed here as these are decided.'] ) ? $GLOBALS['plural_for']['%s more is waiting; it is listed here as these are decided.'] : array(),
	array( $ceiling + 50 - $limit, $ceiling - $limit, $ceiling - $limit ) );
// The same page's words after each count: every card waiting for a decision asks for them, and the
// three sponsor cards' are the only ones at these sizes.
$waiting_for = isset( $GLOBALS['plural_for']['%s waiting'] ) ? $GLOBALS['plural_for']['%s waiting'] : array();
ck( 'and the words after the posts\' and the agreements\' counts choose theirs by the ceiling they print, never by the count\'s read, the applications\' by every one waiting',
	array( count( array_keys( $waiting_for, $ceiling, true ) ), in_array( $ceiling + 1, $waiting_for, true ), count( array_keys( $waiting_for, $ceiling + 50, true ) ) ),
	array( 2, false, 1 ) );
$past_counts = WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() );
ck( 'and each tile\'s number is still an integer: the posts\' and the agreements\' the count\'s read, one more than the ceiling, and the applications\' every one waiting',
	array( $past_counts['sponsor_posts']['n'], $past_counts['sponsor_agreements']['n'], $past_counts['sponsor_applications']['n'] ),
	array( $ceiling + 1, $ceiling + 1, $ceiling + 50 ) );

// The two reads are separate queries: one decided between them leaves the count's read short of the
// rows the card already holds, and the card must not count fewer than it lists.
$sponsor_queues( 3 );
$GLOBALS['count_short'] = array(
	'posts'        => 1,
	'agreements'   => 1,
	'applications' => 1,
);
ck( 'when the count\'s read answers fewer than the rows a card holds, the count is the rows',
	$sponsor_counts(),
	$alike( array( '3', '3', '3 waiting', 3 ) ) );
ck( 'and no line is printed under them',
	$sponsor_lines(),
	$alike( array() ) );
unset( $GLOBALS['count_short'] );

list( $GLOBALS['sponsor_posts'], $GLOBALS['agr_review'], $GLOBALS['agr_facts'], $GLOBALS['sponsor_apps'] ) = $saved_queues;

echo "\n=== A test institution is listed on every card, with a Test badge after its name ===\n";

// The Administrator Dashboard never loads the admin stylesheet, so the mark is the dashboards' own badge
// (`wpcpm-badge--test`). Each card lists the test institution's item where a real one's is, the badge once,
// right after the link; a real institution's item holds none; the cards list what they listed before.
$T_REC = 'recTESTINSTITUT01';
$U_REC = 'recTESTUSERINST01';
$kept  = array(
	'inst_rows'      => $GLOBALS['inst_rows'],
	'agr'            => $GLOBALS['agr'],
	'reports'        => $GLOBALS['reports'],
	'requests'       => $GLOBALS['requests'],
	'facts'          => $GLOBALS['facts'],
	'locked'         => $GLOBALS['locked'],
	'institution_of' => isset( $GLOBALS['institution_of'] ) ? $GLOBALS['institution_of'] : array(),
);

// What the world gives with no test institution in it, read before any is added: the section below
// holds every number to it.
$base_data   = WPCPM_Administrators_Cards::collect();
$base_counts = WPCPM_Administrators_Cards::counts( $base_data );

$GLOBALS['inst_rows'][ $T_REC ] = array( 'record_id' => $T_REC, 'name' => 'TEST - Example Institution', 'stage' => 'Confirmed', 'country' => '', 'country_name' => '' );

seed_post( 531, 'wpcpm_inst_app', 'TEST - Example Applicant', array( '_wpcpm_app_state' => 'new', '_wpcpm_app_country' => '', '_wpcpm_app_country_name' => '', '_wpcpm_app_fields' => array( 'Name' => 'TEST - Example Applicant' ) ), '2026-09-03 08:00:00' );
seed_post( 611, 'wpcpm_agreement', 'Agreement TEST', array( '_wpcpm_agr_institution' => $T_REC, '_wpcpm_agr_state' => 'submitted' ), '2026-08-21 08:00:00' );
seed_post( 711, 'wpcpm_inst_report', 'Report TEST 2026-H1', array( '_wpcpm_report_state' => 'draft', '_wpcpm_report_institution' => $T_REC, '_wpcpm_report_cohort' => '2026-H1' ) );
seed_post( 712, 'wpcpm_inst_report', 'Report TEST approved', array( '_wpcpm_report_state' => 'approved', '_wpcpm_report_institution' => $T_REC, '_wpcpm_report_cohort' => '2026-H1' ) );

$GLOBALS['agr']['submitted'][]     = 611;
$GLOBALS['reports']['queue'][]     = array( 'post_id' => 711, 'institution' => $T_REC, 'cohort' => '2026-H1', 'generated' => 1756500000, 'origin' => 'auto', 'in_progress' => 1, 'age_days' => 5, 'approved_at' => 0, 'approved_by' => 0 );
$GLOBALS['reports']['due'][]       = array( 'institution' => $T_REC, 'cohort' => '2025-H2', 'in_progress' => 0, 'window_end' => '2025-12-31' );
$GLOBALS['reports']['approved'][]  = array( 'post_id' => 712, 'institution' => $T_REC, 'cohort' => '2026-H1', 'generated' => 1756000000, 'origin' => 'manager', 'in_progress' => 0, 'age_days' => 9, 'approved_at' => 1756600000, 'approved_by' => 3 );
$GLOBALS['requests']['open'][]     = 811;
$GLOBALS['requests']['open'][]     = 812;
$GLOBALS['facts'][811]             = array_merge( $GLOBALS['facts'][801], array( 'id' => 811, 'institution' => $T_REC, 'institution_name' => 'TEST - Example Institution' ) );
$GLOBALS['facts'][812]             = array_merge( $GLOBALS['facts'][801], array( 'id' => 812, 'institution' => $T_REC, 'institution_name' => 'TEST - Example Institution', 'kind' => 'details', 'kind_label' => 'A change to its details', 'changes' => array() ) );
$GLOBALS['locked']                 = array( new WP_User( 21, 'Rep One', 'administrator@example.test' ), new WP_User( 22, 'Rep Two', 'rep.two@example.test' ) );
$GLOBALS['institution_of']         = array( 21 => $A, 22 => $T_REC );

$test_data = WPCPM_Administrators_Cards::collect();
$badge     = 'wpcpm-badge--test';
$cards_t   = array(
	'applications' => capture( static function () use ( $test_data ) { WPCPM_Administrators_Cards::render_applications( $test_data['applications'] ); } ),
	'agreements'   => capture( static function () use ( $test_data ) { WPCPM_Administrators_Cards::render_agreements( $test_data['agreements'] ); } ),
	'reports'      => capture( static function () use ( $test_data ) { WPCPM_Administrators_Cards::render_reports( $test_data['reports'] ); } ),
	'requests'     => capture( static function () use ( $test_data ) { WPCPM_Administrators_Cards::render_requests( $test_data['requests'] ); } ),
	'health'       => capture( static function () use ( $test_data ) { WPCPM_Administrators_Cards::render_health( $test_data['health'], $test_data['locked'] ); } ),
);

// The badge sits right after the closing link of the item's title: `</a> <span class="wpcpm-badge wpcpm-badge--test"`.
$after_link = '</a> <span class="wpcpm-badge ' . $badge . '"';
ck( 'Institution applications: the test application\'s heading holds the badge once, after its link; no real application\'s does', array(
	substr_count( $cards_t['applications'], $badge ),
	has( $cards_t['applications'], 'TEST - Example Applicant' . $after_link ),
	has( $cards_t['applications'], 'Uni Nueva</a> <span class="wpcpm-badge' ),
), array( 1, true, false ) );
ck( 'Collaboration Agreements: the test institution\'s item holds the badge once, after its link; the real ones none', array(
	substr_count( $cards_t['agreements'], $badge ),
	has( $cards_t['agreements'], 'TEST - Example Institution</a> <span class="wpcpm-badge ' . $badge . '"' ),
), array( 1, true ) );
ck( 'Semester reports, to review, due and approved: one badge in each list, after the link', array(
	substr_count( $cards_t['reports'], $badge ),
	substr_count( $cards_t['reports'], 'TEST - Example Institution</a> <span class="wpcpm-badge ' . $badge . '"' ),
), array( 3, 3 ) );
ck( 'Requests from institutions, the mentor request and the details request: a badge each, after the link', array(
	substr_count( $cards_t['requests'], $badge ),
	substr_count( $cards_t['requests'], 'TEST - Example Institution</a> <span class="wpcpm-badge ' . $badge . '"' ),
), array( 2, 2 ) );
ck( 'Syncs and health, locked accounts: the test institution\'s account reads "(Test)" after its username, the real one\'s as before', array(
	has( $cards_t['health'], 'Rep One (' ),
	has( $cards_t['health'], 'Rep Two (reptwo) (Test)' ),
	substr_count( $cards_t['health'], '(Test)' ),
), array( true, true, 1 ) );

// Programs running (Ruling E1): the test institution's row is listed in its place with the badge once,
// after its link; the real row holds none.
$programs_t         = $programs;
$programs_t['rows'] = array(
	array_merge( $programs['rows'][0], array( 'record' => $T_REC, 'name' => 'TEST - Example Institution' ) ),
	$programs['rows'][0],
);
// The mark is judged by the name the row holds, with no read of the index: a row whose name is a test's
// and whose record is a real one's carries the badge, and the reverse carries none.
$programs_n         = $programs;
$programs_n['rows'] = array(
	array_merge( $programs['rows'][0], array( 'record' => $A, 'name' => 'TEST - Passed Name' ) ),
	array_merge( $programs['rows'][0], array( 'record' => $T_REC, 'name' => 'Passed Real Name' ) ),
);
// Read off the parts' table (Task 18c: the old table's checks left in Task 18b).
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$programs_card    = capture( static function () use ( $programs_t ) { WPCPM_Administrators_Cards::render_programs( $programs_t ); } );
$programs_by_name = capture( static function () use ( $programs_n ) { WPCPM_Administrators_Cards::render_programs( $programs_n ); } );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
ck( 'Programs running: the mark follows the name the row holds, not the record it links to', array(
	substr_count( $programs_by_name, $badge ),
	has( $programs_by_name, '>TEST - Passed Name</a> <span class="wpcpm-badge ' . $badge . '"' ),
	has( $programs_by_name, 'Passed Real Name</a></th>' ),
), array( 1, true, true ) );
ck( 'Programs running: the test institution\'s row holds the badge once, right after its link, and the real row none', array(
	substr_count( $programs_card, $badge ),
	has( $programs_card, 'TEST - Example Institution</a> <span class="wpcpm-badge ' . $badge . '"' ),
	has( $programs_card, 'Uniwersytet Alpha</a></th>' ),
), array( 1, true, true ) );

echo "\n=== Test institutions are listed and not counted ===\n";

// The same world with and without the test institution's items, which the section above stood up: every
// tile and card count is the one the world without them gives, and every test item is still listed.
$tile_n      = static function ( array $tiles, $key ) { return isset( $tiles[ $key ] ) ? (int) $tiles[ $key ]['n'] : 0; };
$count_of    = static function ( $html ) { return 1 === preg_match( '#<span class="wpcpm-group__count">([0-9,]+)</span>#', $html, $m ) ? (int) $m[1] : null; };
$test_counts = WPCPM_Administrators_Cards::counts( $test_data );
$keys        = array( 'applications', 'agreements', 'reports', 'requests', 'locked' );

ck( 'the tiles of the world without a test institution are what the tiles of the world with one say', array_map( static function ( $k ) use ( $tile_n, $test_counts ) { return $tile_n( $test_counts, $k ); }, $keys ), array_map( static function ( $k ) use ( $tile_n, $base_counts ) { return $tile_n( $base_counts, $k ); }, $keys ) );
ck( 'and their labels are the same: the agreements tile says its overdue count without the test one', array( $test_counts['agreements']['label'], $test_counts['requests']['label'], $test_counts['reports']['label'] ), array( $base_counts['agreements']['label'], $base_counts['requests']['label'], $base_counts['reports']['label'] ) );
ck( 'the strip holds every tile the world without a test institution holds', array_keys( $test_counts ), array_keys( $base_counts ) );
ck( 'the totals collect() reads leave the test items out: open applications, agreements overdue, requests overdue', array( $test_data['applications']['open_total'], $test_data['agreements']['overdue'], $test_data['requests']['overdue'] ), array( $base_data['applications']['open_total'], $base_data['agreements']['overdue'], $base_data['requests']['overdue'] ) );
ck( 'each card\'s count is the real one, while the card still lists the test items', array(
	$count_of( $cards_t['applications'] ) === $tile_n( $base_counts, 'applications' ),
	$count_of( $cards_t['agreements'] ) === $tile_n( $base_counts, 'agreements' ),
	$count_of( $cards_t['requests'] ) === $tile_n( $base_counts, 'requests' ),
	$count_of( $cards_t['reports'] ),
	count( $test_data['applications']['open'] ) - count( $base_data['applications']['open'] ),
	count( $test_data['agreements']['awaiting'] ) - count( $base_data['agreements']['awaiting'] ),
	count( $test_data['requests']['open'] ) - count( $base_data['requests']['open'] ),
), array( true, true, true, count( $base_data['reports']['queue'] ) + count( $base_data['reports']['due'] ), 1, 1, 2 ) );
ck( 'the reports tile counts the one real draft and the one real pair due, the test ones left out', array( $test_counts['reports']['n'], $test_counts['reports']['label'] ), array( count( $base_data['reports']['queue'] ), $base_counts['reports']['label'] ) );
ck( 'the locked tile counts the real account and the card names both, the test one with "(Test)"', array( $test_counts['locked']['n'], has( $cards_t['health'], 'Rep One (' ), has( $cards_t['health'], 'Rep Two (reptwo) (Test)' ) ), array( 1, true, true ) );

// Without totals, a list of test applications alone counts none, so the card prints no "N more" line.
$only_test = capture( static function () { WPCPM_Administrators_Cards::render_applications( array( 'open' => array( $GLOBALS['posts'][531] ), 'closed' => array() ) ); } );
ck( 'the fallback totals count real rows: a test application alone draws no "more" line and a count of 0', array( has( $only_test, 'more is waiting' ) || has( $only_test, 'more are waiting' ), $count_of( $only_test ), has( $only_test, 'TEST - Example Applicant' ) ), array( false, 0, true ) );

// A rejected application of a test institution: listed in the closed fold, which counts as it did. The
// world's year of rejected applications is set aside, so the fold's window holds the rows it is about.
$posts_kept = $GLOBALS['posts'];
$one_closed = false;
foreach ( $GLOBALS['posts'] as $post_id => $post ) {
	if ( 'wpcpm_inst_app' === $post->post_type && in_array( get_post_meta( $post_id, '_wpcpm_app_state', true ), array( 'rejected', 'spam' ), true ) ) {
		if ( $one_closed ) { unset( $GLOBALS['posts'][ $post_id ] ); }
		$one_closed = true;
	}
}
$base_data    = WPCPM_Administrators_Cards::collect();
$closed_before = capture( static function () use ( $base_data ) { WPCPM_Administrators_Cards::render_applications( $base_data['applications'] ); } );
seed_post( 532, 'wpcpm_inst_app', 'TEST - Example Rejected', array( '_wpcpm_app_state' => 'rejected', '_wpcpm_app_country' => '', '_wpcpm_app_country_name' => '', '_wpcpm_app_fields' => array( 'Name' => 'TEST - Example Rejected' ) ), '2026-09-02 08:00:00' );
$rej_data  = WPCPM_Administrators_Cards::collect();
$closed_t  = capture( static function () use ( $rej_data ) { WPCPM_Administrators_Cards::render_applications( $rej_data['applications'] ); } );
$summary_of = static function ( $html ) { return 1 === preg_match( '#<summary>([0-9,]+) rejected or spam applications?</summary>#', $html, $m ) ? (int) $m[1] : null; };
ck( 'a rejected test application leaves the closed fold\'s summary and total as they were, and is listed in the fold', array(
	$summary_of( $closed_t ),
	$summary_of( $closed_before ),
	$rej_data['applications']['closed_total'],
	$base_data['applications']['closed_total'],
	has( $closed_t, 'TEST - Example Rejected</a> <span class="wpcpm-badge ' . $badge . '"' ),
), array( $summary_of( $closed_before ), $summary_of( $closed_before ), $base_data['applications']['closed_total'], $base_data['applications']['closed_total'], true ) );
$GLOBALS['posts'] = $posts_kept;

foreach ( $kept as $key => $value ) {
	$GLOBALS[ $key ] = $value;
}
unset( $GLOBALS['facts'][811], $GLOBALS['facts'][812], $GLOBALS['posts'][531], $GLOBALS['posts'][611], $GLOBALS['posts'][711], $GLOBALS['posts'][712] );

// The Administrator guide draws the page's own menu (1.123.70): the same items and counts, read the same
// way at the same moment, with the guide marked as where the reader is and nothing else, under its own key.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrator-guide-view.php';
$guide_page              = new WP_Post();
$guide_page->ID          = 99560;
$guide_page->post_name   = 'administrator-guide';
$guide_page->post_title  = 'Administrator guide';
$GLOBALS['posts'][99560] = $guide_page;
$on_page                 = menu_items( WPCPM_Administrators_Dashboard::render( array() ) );
$guide_args              = WPCPM_Administrators_Dashboard::menu_args( WPCPM_Administrators_Dashboard::VIEW_GUIDE );
unset( $GLOBALS['posts'][99560] );
$guide_items = array();
foreach ( array_merge( array_merge( ...array_column( $guide_args['groups'], 'items' ) ), $guide_args['help'] ) as $item ) {
	$guide_items[ $item['label'] ] = $item;
}
ck( 'the Administrator Dashboard opens its frame for Administrators, on the page\'s own menu and on the guide\'s and the updates\'', array(
	$guide_args['audience'] ?? null,
	WPCPM_Administrators_Dashboard::menu_args( WPCPM_Administrators_Dashboard::VIEW_UPDATES )['audience'] ?? null,
), array( 'administrator', 'administrator' ) );
ck( 'the guide\'s menu is the page\'s: the same items in the same order to the same addresses with the same counts, the guide marked current and nothing else, no name above it, under the guide\'s own key', array(
	array_keys( $guide_items ),
	array_column( $guide_items, 'url', 'label' ),
	array_filter( array_column( $guide_items, 'badge', 'label' ) ),
	array_keys( array_filter( array_column( $guide_items, 'current', 'label' ) ) ),
	array( $guide_args['page'], isset( $guide_args['context'] ) ),
), array(
	array_keys( $on_page ),
	array_column( $on_page, 'url' ) ? array_combine( array_keys( $on_page ), array_column( $on_page, 'url' ) ) : array(),
	array_filter( array_combine( array_keys( $on_page ), array_column( $on_page, 'badge' ) ) ),
	array( 'Administrator guide' ),
	array( 'administrator-guide', false ),
) );

// A link to a card goes to the view that draws it (1.123.71): the Watch cards to Watch, The program's to it,
// every other to the Overview; the way back from a decision on a card to watch lands there too.
$_POST       = array( WPCPM_Return::FIELD => 'dashboard', WPCPM_Return::ANCHOR_FIELD => 'health' );
$health_back = WPCPM_Return::url( 'https://example.test/wp-admin/' );
$_POST       = array();
ck( 'card_url() sends each card to its view: Watch\'s, The program\'s and the Overview\'s, an anchor inside a card kept; and a decision on a card to watch comes back to Watch', array(
	WPCPM_Administrators_Dashboard::card_url( 'quiet-students' ),
	WPCPM_Administrators_Dashboard::card_url( 'offers-low' ),
	WPCPM_Administrators_Dashboard::card_url( 'sponsors' ),
	WPCPM_Administrators_Dashboard::card_url( 'applications' ),
	WPCPM_Administrators_Dashboard::card_url( 'requests', 'wpcpm-request-12' ),
	$health_back,
), array(
	$admin_page_url . '?wpcpm_view=watch#wpcpm-quiet-students',
	$admin_page_url . '?wpcpm_view=watch#wpcpm-offers-low',
	$admin_page_url . '?wpcpm_view=reference#wpcpm-sponsors',
	$admin_page_url . '#wpcpm-applications',
	$admin_page_url . '#wpcpm-request-12',
	$admin_page_url . '?wpcpm_view=watch#wpcpm-health',
) );

// Program updates in the menu (1.123.71): the newest update an Administrator may read, after Reference,
// only while there is one; on an update the menu marks it, under the updates' own key.
$update              = new WP_Post();
$update->ID          = 99700;
$update->post_name   = 'site-update-24-oct-to-6-nov';
$update->post_type   = 'post';
$GLOBALS['updates_posts'] = array( $update );
$GLOBALS['updates_asked'] = array();
$with_updates             = menu_items( WPCPM_Administrators_Dashboard::render( array() ) );
$updates_args             = WPCPM_Administrators_Dashboard::menu_args( WPCPM_Administrators_Dashboard::VIEW_UPDATES );
$GLOBALS['updates_posts'] = array();
$without_updates          = menu_items( WPCPM_Administrators_Dashboard::render( array() ) );
$updates_items            = array();
foreach ( array_merge( array_merge( ...array_column( $updates_args['groups'], 'items' ) ), $updates_args['help'] ) as $item ) {
	$updates_items[ $item['label'] ] = $item;
}
ck( 'Program updates follows The program, linking the newest update an Administrator may read, asked of the updates as the Administrator Dashboard\'s own list; with none it is not drawn; on an update it is the one item marked, under the updates\' key', array(
	array_slice( array_keys( $with_updates ), 0, 4 ),
	$with_updates['Program updates']['url'] ?? null,
	$with_updates['Program updates']['current'] ?? null,
	$GLOBALS['updates_asked'][0] ?? null,
	isset( $without_updates['Program updates'] ),
	array_keys( array_filter( array_column( $updates_items, 'current', 'label' ) ) ),
	$updates_args['page'],
), array(
	array( 'Overview', 'Watch', 'The program', 'Program updates' ),
	'https://example.test/site-update-24-oct-to-6-nov/',
	false,
	array( 1, 'administrator' ),
	false,
	array( 'Program updates' ),
	'administrator-updates',
) );
unset( $GLOBALS['updates_posts'], $GLOBALS['updates_asked'] );

echo "=== The shared parts behind the switch (Task 9) ===\n";
// The page names itself to the switch while it draws (design 3.5), so a renderer it calls asks `WPCPM_UI::on()`;
// with the switch empty, as it stays until the release turns it on, every view prints today's markup. Every
// check above ran with it empty. The switch's class sits on the frame's root and the page's root while the page
// is on, so every part, and every rule of parts.css scoped under `.wpcpm-ui-on`, is inside it; off, it and the
// parts are printed nowhere.
/**
 * Every view the page draws: the Overview, Watch, The program and the cohort timeline.
 *
 * @return string
 */
function every_view() {
	$html = WPCPM_Administrators_Dashboard::render( array() );
	foreach ( array( WPCPM_Administrators_Dashboard::VIEW_WATCH, WPCPM_Administrators_Dashboard::VIEW_REFERENCE, 'cohort-timeline' ) as $view ) {
		$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = $view;
		$html .= WPCPM_Administrators_Dashboard::render( array() );
	}
	unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] );
	return $html;
}
/**
 * The elements a page prints with one of the parts' classes, or with the frame's button or tag that parts.css
 * restyles under `.wpcpm-ui-on`, that have no ancestor carrying `wpcpm-ui-on`; and how many such elements it
 * prints in all.
 *
 * @param string $html The page.
 * @return array{0: string[], 1: int}
 */
function parts_outside_switch( $html ) {
	$GLOBALS['parts_inventory_drawn'][] = $html;
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	$outside = array();
	$all     = 0;
	foreach ( ( new DOMXPath( $doc ) )->query( '//*[@class]' ) as $node ) {
		$classes = preg_split( '/\s+/', trim( (string) $node->getAttribute( 'class' ) ) );
		$part    = array_filter( $classes, static function ( $c ) { return 'wpcpm-ui-on' !== $c && 1 === preg_match( '/^wpcpm-(ui-|btn$|btn--|status$|status--)/', $c ); } );
		if ( empty( $part ) ) {
			continue;
		}
		++$all;
		$inside = false;
		for ( $up = $node->parentNode; $up instanceof DOMElement; $up = $up->parentNode ) {
			if ( in_array( 'wpcpm-ui-on', preg_split( '/\s+/', (string) $up->getAttribute( 'class' ) ), true ) ) {
				$inside = true;
				break;
			}
		}
		if ( ! $inside ) {
			$outside[] = $node->nodeName . '.' . implode( '.', $part );
		}
	}
	return array( array_values( array_unique( $outside ) ), $all );
}
$off_views = every_view();
// The cards whose old branches left (Task 18b) draw their parts whatever the switch says; the root's class and the
// surface's balance are still the switch's.
ck( 'off: no view prints the switch\'s class, and the page has named no surface once it is drawn', array( substr_count( $off_views, 'wpcpm-ui-on' ), WPCPM_UI::surface() ), array( 0, '' ) );

// A guide to link to, and one mentor application that came in three days ago, so the row has an age.
$GLOBALS['posts'][991] = $guide_page;
WPCPM_Guide_Jobs::forget();
$mentor_post                    = new WP_Post();
$mentor_post->ID                = 7701;
$mentor_post->post_type         = 'wpcpm_mentor_app';
$mentor_post->post_date_gmt     = gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS - 600 );
$GLOBALS['mentor_open_posts']   = array( $mentor_post );
WPCPM_UI::set_live( array( 'administrator' ) );
$on_overview  = WPCPM_Administrators_Dashboard::render( array() );
$surface_seen = WPCPM_UI::surface();
$on_views     = every_view();
list( $outside, $parts_drawn ) = parts_outside_switch( $on_views );
ck( 'on: the frame\'s root and the page\'s root carry wpcpm-ui-on, and once drawn the page has left its surface', array(
	has( $on_overview, '<div class="wpcpm-frame wpcpm-frame--administrator wpcpm-ui-on" data-wpcpm-frame>' ),
	has( $on_overview, '<div class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--overview wpcpm-ui-on">' ),
	$surface_seen,
), array( true, true, '' ) );
ck( 'on: every part each view prints, and every button and tag parts.css restyles, sits inside an element carrying wpcpm-ui-on', array( $parts_drawn > 0, $outside ), array( true, array() ) );
ck( 'on: every card of the Overview is drawn in the parts\' card fold, none in today\'s disclosure', array(
	substr_count( $on_overview, 'class="wpcpm-administrator__card' ) > 1,
	substr_count( $on_overview, 'class="wpcpm-administrator__card' ) === substr_count( $on_overview, 'wpcpm-ui-fold-card"' ),
	substr_count( $on_overview, 'wpcpm-group__disclosure' ),
), array( true, true, 0 ) );
$ext        = '<span class="wpcpm-ui-fold__ext" aria-hidden="true">' . WPCPM_Icons::svg( 'external', 20 ) . '</span>';
$mentor_job = '<p class="wpcpm-ui-fold__help"><a class="wpcpm-job-link wpcpm-ui-fold__help-link" href="' . WPCPM_Guide_Jobs::url( 'mentor-application' ) . '">How to do this<span class="screen-reader-text">: Mentor applications</span></a></p>';
ck( 'on: the Mentor applications row is the row fold: its waiting phrase, the age tag, the help slot and the external-link icon in the head, the Review link to the Mentors screen\'s queue as the secondary button, and the help link to the guide\'s job after the row', array(
	has( $on_overview, '<section class="wpcpm-administrator__card wpcpm-ui-fold-card" id="wpcpm-mentor-applications"><div class="wpcpm-ui-fold wpcpm-ui-fold--row"><div class="wpcpm-ui-fold__head"><h3 class="wpcpm-ui-fold__title">Mentor applications</h3><span class="wpcpm-ui-fold__phrase">4 waiting</span><span class="wpcpm-ui-fold__end"><span class="wpcpm-status wpcpm-status--red">3 days waiting</span><span class="wpcpm-ui-fold__help-slot" aria-hidden="true"></span></span>' . $ext . '<a class="wpcpm-btn wpcpm-btn--secondary" href="https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue">Review<span class="screen-reader-text"> on the Mentors screen</span></a></div></div>' . $mentor_job . '</section>' ),
	has( WPCPM_Guide_Jobs::url( 'mentor-application' ), '#a-mentor-applied' ),
	substr_count( $on_overview, 'id="wpcpm-mentor-applications"' ),
	has( $on_overview, 'wpcpm-overview__row' ),
), array( true, true, 1, false ) );
$mentor_post->post_date_gmt = gmdate( 'Y-m-d H:i:s', time() - 600 );
$today_row                  = WPCPM_Administrators_Dashboard::render( array() );
ck( 'on: an application that came in today says so on the row', has( $today_row, '<span class="wpcpm-ui-fold__phrase">4 waiting</span><span class="wpcpm-ui-fold__end"><span class="wpcpm-status wpcpm-status--red">Waiting since today</span>' ), true );
WPCPM_UI::set_live( array() );
$mentor_post->post_date_gmt = gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS - 600 );
unset( $GLOBALS['posts'][991], $GLOBALS['mentor_open_posts'] );
WPCPM_Guide_Jobs::forget();
// Follow-up round, item 4: a render that throws part way still leaves its surface, so the surface named around it
// is the one drawing afterwards (here a stand-in for the page that holds the dashboard).
$GLOBALS['prompt_throws'] = true;
$buffers                  = ob_get_level();
WPCPM_UI::enter( 'outer-page' );
$thrown = '';
try {
	WPCPM_Administrators_Dashboard::render( array() );
} catch ( RuntimeException $e ) {
	$thrown = $e->getMessage();
}
while ( ob_get_level() > $buffers ) {
	ob_end_clean();
}
$after_throw = WPCPM_UI::surface();
WPCPM_UI::leave();
unset( $GLOBALS['prompt_throws'] );
ck( 'a render that throws still leaves its surface: the one named before it is the one drawing after', array( $thrown, $after_throw, WPCPM_UI::surface() ), array( 'The prompt failed.', 'outer-page', '' ) );

echo "\n=== Institution applications on the parts, behind the switch (UI shared parts, Task 10) ===\n";

// With the Administrator Dashboard's switch on, each application is an item header, its answers an inner
// fold holding a key and value table, then the module's decisions, which draw their own Decide area (pinned
// in bin/test-institutions-screen.php, where the module's real forms are drawn). Off, the card is today's,
// which the checks above pin.
$GLOBALS['users'][12] = new WP_User( 12, 'Beata Admin', 'beata.admin@example.test' );
$GLOBALS['manage']    = array( 3, 12 );
$parts_data           = WPCPM_Administrators_Cards::collect();
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$parts_apps = capture( static function () use ( $parts_data ) { WPCPM_Administrators_Cards::render_applications( $parts_data['applications'] ); } );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
$GLOBALS['manage'] = array( 3 );

$parts_rows = array();
if ( preg_match_all( '#<article class="wpcpm-application wpcpm-ui-item" id="wpcpm-application-(\d+)">(.*?)</article>#s', $parts_apps, $parts_found ) ) {
	$parts_rows = array_combine( $parts_found[1], $parts_found[2] );
}
ck( 'on, each application is still an item with its own anchor, the one a mail and the queue link to', array( isset( $parts_rows[501] ), isset( $parts_rows[502] ), isset( $parts_rows[503] ), count( $parts_rows ) === substr_count( $parts_apps, '<article ' ) ), array( true, true, true, true ) );
// Task 17b fix round, ruling 2: the rejected and spam applications fold under today's summary as the inner fold, closed
// as today.
ck( 'the rejected and spam applications are the inner fold, today\'s summary its title, closed as today, holding their items', array(
	has( $parts_apps, WPCPM_UI::inner_open( array( 'title' => '50 rejected or spam applications' ) ) . '<article class="wpcpm-application wpcpm-ui-item" id="wpcpm-application-' ),
	substr_count( $parts_apps, WPCPM_UI::inner_close() . '<p class="wpcpm-ui-fold__foot">' ),
	strpos( $parts_apps, 'wpcpm-administrator__closed' ),
), array( true, 1, false ) );
ck( 'its header is the item header: the name linked to the queue, the reference, the state as a neutral tag, then country, the Administrator and the received date as facts', array(
	0 === strpos( $parts_rows['501'] ?? '', '<h4 class="wpcpm-ui-item__title"><a href="' . esc_url( WPCPM_Institution_Application::queue_url( 501 ) ) . '">Uni Nueva</a><code class="wpcpm-ui-item__ref">APP-2026-0501</code></h4><p class="wpcpm-ui-item__facts"><span class="wpcpm-status">new</span><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact">Poland</span><span class="wpcpm-ui-item__fact">Assigned to Beata Admin</span><span class="wpcpm-ui-item__fact">Received ' ),
	1 === preg_match( '#<p class="wpcpm-ui-item__facts"><span class="wpcpm-status">held by the anti-spam checks</span><span class="wpcpm-ui-item__run">(?:<span class="wpcpm-ui-item__fact">[^<]*</span>)*</span></p>#', $parts_rows['502'] ?? '' ),
	strpos( $parts_apps, 'wpcpm-administrator__fact' ),
	strpos( $parts_apps, 'wpcpm-administrator__item-title' ),
), array( true, true, false, false ) );
// Its words as today's, in the note part (Task 17b, ruling 1).
$held_note_on = str_replace( 'wpcpm-administrator__note', 'wpcpm-ui-note', $held_note );
ck( 'a held application keeps its note in today\'s words, between the header and the answers, as the note part', array(
	substr_count( $parts_rows['502'] ?? '', $held_note_on ),
	strpos( $parts_rows['502'] ?? '', $held_note_on ) > strpos( $parts_rows['502'] ?? '', '</p>' ) && strpos( $parts_rows['502'] ?? '', $held_note_on ) < strpos( $parts_rows['502'] ?? '', '<details' ),
	substr_count( $parts_rows['501'] ?? '', $held_note_on ),
), array( 1, true, 0 ) );
ck( '"Read the application" is an inner fold, closed, and the answers are a key and value table in it', array(
	has( $parts_rows['501'] ?? '', '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Read the application</span></span>' ),
	has( $parts_rows['501'] ?? '', '<div class="wpcpm-ui-fold__body"><table class="wpcpm-ui-table wpcpm-ui-table--keyvalue" role="table"><tbody role="rowgroup">' ),
	strpos( $parts_apps, 'wpcpm-administrator__answers' ),
	strpos( $parts_apps, 'wpcpm-app-answers' ),
), array( true, true, false, false ) );
$parts_read = static function ( $row ) {
	preg_match_all( '#<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text">(.*?)</th><td class="(wpcpm-ui-cell--text(?: wpcpm-ui-cell--empty)?)" role="cell">(.*?)</td></tr>#s', (string) $row, $found, PREG_SET_ORDER );
	return array_map( static function ( $r ) { return array( $r[1], $r[2], $r[3] ); }, $found );
};
$parts_new   = $parts_read( $parts_rows['501'] ?? '' );
$parts_label = array_map( static function ( $spec ) { return esc_html( $spec['label'] ); }, array_values( WPCPM_Institution_Application::fields() ) );
ck( 'the table holds every question in the form\'s order, and no Airtable column name (controller\'s ruling F)', array( array_column( $parts_new, 0 ), strpos( $parts_apps, 'wpcpm-inst-record' ) ), array( $parts_label, false ) );
ck( 'an answer is ink; an empty answer keeps its words and its cell is marked empty, muted by the table, not by a class of the old screen', array(
	$parts_new[0] ?? null,
	$parts_new[13] ?? null,
	strpos( $parts_apps, 'wpcpm-inst-muted' ),
), array(
	array( 'Name of your institution', 'wpcpm-ui-cell--text', 'Uni Nueva' ),
	array( 'Their title', 'wpcpm-ui-cell--text wpcpm-ui-cell--empty', 'no answer' ),
	false,
) );
// The answers' values as the parts print them for this fixture (Task 18c: the old card's checks left in Task 18b, which
// the review found weakened to presence only): the five kept on the site since 1.123.29, answered from the site's own
// key, and on one filed before them unanswered; beside them the first answers that go to Airtable.
$parts_value = static function ( array $rows, array $at ) { return array_map( static function ( $i ) use ( $rows ) { return isset( $rows[ $i ] ) ? $rows[ $i ][2] : null; }, $at ); };
ck( 'the five answers kept on the site, answered from the site\'s own key, and on one filed before them all unanswered; beside them the name, the country and the city', array(
	$site_at,
	$parts_value( $parts_new, $site_at ),
	$parts_value( $parts_read( $parts_rows['502'] ?? '' ), $site_at ),
	$parts_value( $parts_new, array( 0, 1, 2 ) ),
), array(
	array( 6, 11, 12, 13, 14 ),
	array( 'Internship coordinator', 'Polish, English', 'no answer', 'no answer', 'https://uni-nueva.example/credits' ),
	array_fill( 0, 5, 'no answer' ),
	array( 'Uni Nueva', 'Poland', 'Krakow' ),
) );
// What follows the fold here is this suite's stand-in for the module (its `render_application_actions()` at the
// top of the file prints a Decide area through the real helper, as the real module does since Task 10); the real
// module's area is pinned in bin/test-institutions-screen.php. This check reads only where the card puts the module's
// output.
ck( 'whatever the module prints follows the fold straight in the item (here the suite\'s stand-in Decide area): the card adds no wrapper of the old card, so the Decide area sits in the card\'s body with the body\'s own padding', array(
	strpos( $parts_apps, 'wpcpm-administrator__actions' ),
	1 === preg_match( '#</div></details><div class="wpcpm-ui-decide" id="wpcpm-decide-app-501">#', $parts_rows['501'] ?? '' ),
), array( false, true ) );
unset( $GLOBALS['users'][12] );

// Review Focus 1: a refused note's outcome is said inside the card it came back to, at the top of its body,
// above the items, so the card is drawn open and forms.js, which focuses the outcome, opens nothing else.
$GLOBALS['wpcpm_test_extra_queue_messages'] = array( 'app-reason-long' => array( 'error', 'Nothing was rejected. The reason is longer than its box allows.' ) );
$_POST = array( WPCPM_Return::FIELD => 'dashboard', WPCPM_Return::ANCHOR_FIELD => 'applications' );
WPCPM_Return::url( 'https://example.test/wp-admin/' );
WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'app-reason-long' );
WPCPM_UI::set_live( array( 'administrator' ) );
$refused_page = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::set_live( array() );
$_POST = array();
unset( $GLOBALS['wpcpm_test_extra_queue_messages'] );
$refused_card = (string) substr( $refused_page, (int) strpos( $refused_page, 'id="wpcpm-applications"' ) );
$refused_said = strpos( $refused_card, 'role="alert" tabindex="-1" data-wpcpm-card-message>Nothing was rejected. The reason is longer than its box allows.</p>' );
ck( 'a refused note is said in its card, open, at the top of the card\'s body and above every application (Review Focus 1)', array(
	false !== strpos( $refused_page, 'id="wpcpm-applications"' ),
	false !== $refused_said,
	false !== $refused_said && $refused_said > (int) strpos( $refused_card, '<div class="wpcpm-ui-fold__body' ) && $refused_said < (int) strpos( $refused_card, '<article' ),
	1 === preg_match( '#<details class="wpcpm-ui-fold"[^>]*id="wpcpm-applications"[^>]* open>|<section[^>]*id="wpcpm-applications"><details class="wpcpm-ui-fold" open>#', $refused_page ),
	substr_count( $refused_page, 'data-wpcpm-card-message' ),
), array( true, true, true, true, 1 ) );

echo "\n=== Signed Collaboration Agreements on the parts, behind the switch (UI shared parts, Task 11) ===\n";

// With the switch on, each agreement awaiting review is an item header (the name linked to the institution, the
// tag "Awaiting review", then the upload and Overdue as facts) and no heading above it, then the panel's review
// block; the returned and revoked are list rows, the revoked with Reinstate as the quiet row action. The review
// block here is this suite's stand-in for the panel (top of the file): the Decide area the real panel prints,
// Accept it and Return with a note, is pinned in bin/test-institution-panel.php, which draws the real one.

/**
 * Every form a render holds that posts `$action`: how it posts, its guard, its hidden fields as `name=value` in
 * the order a browser posts them, its submits and the question it asks.
 *
 * @param string $html   Markup.
 * @param string $action The admin-post action.
 * @return array[]
 */
function agr_posted( $html, $action ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . (string) $html . '</div>' );
	libxml_clear_errors();
	$xpath = new DOMXPath( $doc );
	$out   = array();
	foreach ( $xpath->query( '//form' ) as $form ) {
		$hidden = array();
		$posts  = '';
		foreach ( $xpath->query( './/input[@type="hidden"]', $form ) as $input ) {
			$hidden[] = $input->getAttribute( 'name' ) . '=' . $input->getAttribute( 'value' );
			if ( 'action' === $input->getAttribute( 'name' ) ) {
				$posts = $input->getAttribute( 'value' );
			}
		}
		if ( $action !== $posts ) {
			continue;
		}
		$submits = array();
		$asks    = $form->getAttribute( 'data-wpcpm-confirm' );
		foreach ( $xpath->query( './/button[not(@type="button")]', $form ) as $button ) {
			$submits[] = $button->getAttribute( 'name' ) . '=' . $button->getAttribute( 'value' );
			$asks      = '' !== $button->getAttribute( 'data-wpcpm-confirm' ) ? $button->getAttribute( 'data-wpcpm-confirm' ) : $asks;
		}
		$out[] = array(
			'posts'   => $form->getAttribute( 'method' ) . ' ' . $form->getAttribute( 'action' ),
			'once'    => $form->hasAttribute( 'data-wpcpm-once' ),
			'busy'    => $form->getAttribute( 'data-wpcpm-busy' ),
			'hidden'  => $hidden,
			'submits' => $submits,
			'asks'    => $asks,
		);
	}
	return $out;
}

$GLOBALS['faithful_nonces'] = true;
$agr_data                   = WPCPM_Administrators_Cards::collect()['agreements'];
$agr_draw                   = static function () use ( $agr_data ) {
	$GLOBALS['reviews']        = array();
	$GLOBALS['review_returns'] = array();
	return capture( static function () use ( $agr_data ) { WPCPM_Administrators_Cards::render_agreements( $agr_data ); } );
};
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$agr_on      = $agr_draw();
$agr_reviews = array( $GLOBALS['reviews'], $GLOBALS['review_returns'] );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
unset( $GLOBALS['faithful_nonces'] );


// A returned note with a line break and markup characters: on, it is escaped on its own line, the break kept as
// typed (the sheet keeps it, `white-space: pre-wrap`).
$agr_odd                             = $agr_data;
$agr_odd['returned'][0]['note']      = "Page two <b>is</b> missing & more.\nSign page four.";
$agr_odd_draw                        = static function () use ( $agr_odd ) {
	return capture( static function () use ( $agr_odd ) { WPCPM_Administrators_Cards::render_agreements( $agr_odd ); } );
};
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$agr_odd_on = $agr_odd_draw();
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
ck( 'a note with a line break and markup characters: on, escaped on its own line with the break kept', array(
	has( $agr_odd_on, '<p class="wpcpm-ui-list__note">Page two &lt;b&gt;is&lt;/b&gt; missing &amp; more.' . "\n" . 'Sign page four.</p>' ),
	strpos( $agr_odd_on, '<b>is</b>' ),
), array( true, false ) );

$agr_items = array();
if ( preg_match_all( '#<article class="wpcpm-agreement( wpcpm-administrator__item--overdue)? wpcpm-ui-item">(.*?)</article>#s', $agr_on, $agr_found, PREG_SET_ORDER ) ) {
	foreach ( $agr_found as $found ) {
		$agr_items[] = array( '' !== $found[1], $found[2] );
	}
}
$agr_header = static function ( $record, $name, array $facts, $tag = '<span class="wpcpm-status">Awaiting review</span>' ) {
	$run = '';
	foreach ( $facts as $fact ) {
		$run .= '<span class="wpcpm-ui-item__fact">' . $fact . '</span>';
	}
	return '<h4 class="wpcpm-ui-item__title"><a href="https://example.test/institution-dashboard/?wpcpm_institution_view=' . $record . '">' . $name . '</a></h4>'
		. '<p class="wpcpm-ui-item__facts">' . $tag . '<span class="wpcpm-ui-item__run">' . $run . '</span></p>';
};
$agr_uploaded = static function ( $timestamp ) {
	return 'Uploaded ' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp ) . ' (' . human_time_diff( (int) $timestamp, time() ) . ' ago)';
};
ck( 'on, "Awaiting review" heads nothing: no heading above the items, and the words are each item\'s tag (design 2.10, 5)', array(
	strpos( $agr_on, 'wpcpm-administrator__subtitle' ),
	substr_count( $agr_on, '>Awaiting review</span>' ),
	count( $agr_items ),
), array( false, 2, 2 ) );
// Task 17b, ruling 7: on the parts an overdue item is said by its red tag and its word, not by the old side stripe.
ck( 'each awaiting agreement keeps its article, the overdue one no longer carrying the old overdue modifier, and opens with the item header: the name linked to the institution, the tag (red on the overdue one, neutral otherwise; fix round 1), then the upload and Overdue in words as facts', array(
	array_column( $agr_items, 0 ),
	0 === strpos( $agr_items[0][1] ?? '', $agr_header( $B, 'Universidad Beta', array( $agr_uploaded( $agr_data['awaiting'][0]['at'] ), 'Overdue' ), '<span class="wpcpm-status wpcpm-status--red">Awaiting review</span>' ) ),
	0 === strpos( $agr_items[1][1] ?? '', $agr_header( $C, 'Instituto Gamma', array( $agr_uploaded( $agr_data['awaiting'][1]['at'] ) ) ) ),
	strpos( $agr_on, 'wpcpm-administrator__item-title' ),
	strpos( $agr_on, 'wpcpm-administrator__fact' ),
), array( array( false, false ), true, true, false, false ) );
ck( 'and the review block follows the header in each, asked for to decide and bound for this card, as today', array(
	// The stand-in prints what the real block prints on the parts: its anchor, no class of the old block (Task 18a).
	1 === preg_match( '#</p><section class="wpcpm-ui-item__part" id="wpcpm-review-601"><div class="wpcpm-ui-decide" id="wpcpm-decide-agr-601"#', $agr_items[0][1] ?? '' ),
	1 === preg_match( '#</p><section class="wpcpm-ui-item__part" id="wpcpm-review-602"><div class="wpcpm-ui-decide" id="wpcpm-decide-agr-602"#', $agr_items[1][1] ?? '' ),
	$agr_reviews,
), array( true, true, array( array( 601, 602 ), array( 'dashboard', 'dashboard' ) ) ) );

$agr_row = static function ( $record, $name, array $facts, $note = '', $action = '' ) {
	$run = '';
	foreach ( $facts as $fact ) {
		$run .= '<span class="wpcpm-ui-item__fact">' . $fact . '</span>';
	}
	return '<li class="wpcpm-ui-list__row' . ( '' !== $note ? ' wpcpm-ui-list__row--note' : '' ) . '"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name"><a href="https://example.test/institution-dashboard/?wpcpm_institution_view=' . $record . '">' . $name . '</a></span>'
		. '<span class="wpcpm-ui-list__facts">' . $run . '</span>' . ( '' !== $action ? '<div class="wpcpm-ui-list__act">' . $action . '</div>' : '' ) . '</div>'
		. ( '' !== $note ? '<p class="wpcpm-ui-list__note">' . $note . '</p>' : '' ) . '</li>';
};
$agr_when = static function ( $timestamp ) {
	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp );
};
// Task 18c: their rows carry no tag, so the list asks for the facts under the name (`wpcpm-ui-list--under`); Recently
// closed, whose rows carry a tag, keeps the four columns.
ck( 'the returned and the revoked stay folded under today\'s summaries, each a list of rows: the name linked to the institution, the date as a fact, and the note on its own line under the row\'s line (fix round 1)', array(
	// Each under today's summary, now the inner fold's title, closed as today (Task 17b fix round, ruling 2).
	has_words( $agr_on, WPCPM_UI::inner_open( array( 'title' => '1 returned, waiting for a new upload' ) ) . '<ul class="wpcpm-ui-list wpcpm-ui-list--under">' . $agr_row( $A, 'Uniwersytet Alpha', array( $agr_when( $agr_data['returned'][0]['at'] ) ), 'Page two is missing.' ) . '</ul>' . WPCPM_UI::inner_close() ),
	has_words( $agr_on, WPCPM_UI::inner_open( array( 'title' => '1 revoked agreement' ) ) . '<ul class="wpcpm-ui-list wpcpm-ui-list--under">' ) && false === strpos( $agr_on, 'wpcpm-administrator__closed' ),
	has_words( $agr_on, '<li class="wpcpm-ui-list__row wpcpm-ui-list__row--note"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name"><a href="https://example.test/institution-dashboard/?wpcpm_institution_view=' . $C . '">Instituto Gamma</a></span><span class="wpcpm-ui-list__facts"><span class="wpcpm-ui-item__fact">' . $agr_when( $agr_data['revoked'][0]['at'] ) . '</span></span><div class="wpcpm-ui-list__act"><form ' ),
	has_words( $agr_on, '</form></div></div><p class="wpcpm-ui-list__note">Withdrawn by the rector.</p></li>' ),
	strpos( $agr_on, 'wpcpm-administrator__note' ),
), array( true, true, true, true, false ) );

// Task 18f, point 8: a list row's date keeps its day, month and year together; its time may go to the next line.
ck( 'Task 18f: the returned and revoked rows\' dates are whole, the time after an ordinary space', array(
	1 === preg_match( '/<span class="wpcpm-ui-item__fact">[^<\s]+\x{00A0}[^<\s]+\x{00A0}[^<\s]+ [^<]+<\/span>/u', $agr_on ),
	1 === preg_match( '/<span class="wpcpm-ui-item__fact">\S+ \S+ \S+ /u', str_replace( "\u{00A0}", ' ', $agr_on ) ),
), array( true, true ) );

// The old form left with its branch (Task 18b): what the form posts is pinned to what the old form posted, read at
// 76b5ecff, where this check compared the two.
ck( 'Reinstate posts exactly what the handler reads: the same address and guard, the same hidden fields in order with the nonce keyed to the document and its referer, no submit name, and still no question (none is asked today)', agr_posted( $agr_on, 'wpcpm_agreement_reinstate' ), array(
	array(
		'posts'   => 'post https://example.test/wp-admin/admin-post.php',
		'once'    => true,
		'busy'    => 'Reinstating',
		'hidden'  => array( '_wpnonce=nonce-wpcpm_agreement_reinstate_604', '_wp_http_referer=/administrator-dashboard/', 'action=wpcpm_agreement_reinstate', 'wpcpm_agreement_post=604', 'wpcpm_return=dashboard', 'wpcpm_return_to=agreements' ),
		'submits' => array( '=' ),
		'asks'    => '',
	),
) );
ck( 'its button is the quiet row action "Reinstate", in the revoked row\'s action, a form that posts at once; its nonce prints no id to repeat on the page; the returned row has no action, so no action cell (Task 15b)', array(
	1 === preg_match( '#<div class="wpcpm-ui-list__act"><form method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Reinstating">(?:<input type="hidden" [^>]*>)+<button type="submit" class="wpcpm-btn wpcpm-btn--quiet">Reinstate</button></form></div>#', $agr_on ),
	substr_count( $agr_on, 'data-wpcpm-busy="Reinstating"' ),
	substr_count( $agr_on, 'id="_wpnonce"' ),
	has( $agr_on, '</span></span></div><p class="wpcpm-ui-list__note">Page two is missing.</p>' ),
	strpos( $agr_on, 'wpcpm-button' ),
), array( true, 1, 0, true, false ) );

// 1.123.102, Part B: a revoked row whose school is outside the active stages is listed with its name, its date and its
// note, offers no Reinstate (the handler refuses it), and says why on one more line, in each mode's words. Awkward data: a
// 60-character name, a 40-character word in the note, a long stage. The fold's count is unchanged.
$b_school                        = 'recEXA00000000060';
$b_name                          = 'Universidade Federal Exemplo de Ciencias e Tecnologia Aplica';
$b_stage                         = 'Not Moving Forward until the next academic year begins';
$b_word                          = str_repeat( 'Abcdefghij', 4 );
$GLOBALS['inst_rows'][ $b_school ] = array( 'record_id' => $b_school, 'name' => $b_name, 'stage' => $b_stage, 'country' => '', 'country_name' => '' );
$b_data                          = $agr_data;
$b_data['revoked'][]             = array( 'id' => 605, 'record' => $b_school, 'at' => $b_data['revoked'][0]['at'], 'note' => 'Revoked after the rector wrote ' . $b_word . ' twice.' );
// Round 3, V4: a revoked school the site's list no longer holds, last, with no note.
$b_gone                          = 'recEXA00000000061';
$b_data['revoked'][]             = array( 'id' => 606, 'record' => $b_gone, 'at' => $b_data['revoked'][0]['at'], 'note' => '' );
$b_draw                          = static function ( $on_site ) use ( $b_data ) {
	$GLOBALS['on_site'] = $on_site;
	WPCPM_UI::set_live( array( 'administrator' ) );
	WPCPM_UI::enter( 'administrator' );
	$html = capture( static function () use ( $b_data ) { WPCPM_Administrators_Cards::render_agreements( $b_data ); } );
	WPCPM_UI::leave();
	WPCPM_UI::set_live( array() );
	unset( $GLOBALS['on_site'] );
	return $html;
};
$b_row = static function ( $html, $name ) {
	return preg_match( '#<li class="wpcpm-ui-list__row[^"]*">(?:(?!</li>).)*?' . preg_quote( esc_html( $name ), '#' ) . '(?:(?!</li>).)*</li>#s', $html, $m ) ? $m[0] : '';
};
$b_off      = $b_draw( false );
$b_on       = $b_draw( true );
$b_line_off = '<p class="wpcpm-ui-note">' . esc_html( 'At the stage "' . $b_stage . '". Reinstate after it moves to an active stage in Airtable.' ) . '</p>';
$b_line_on  = '<p class="wpcpm-ui-note">' . esc_html( 'At the stage "' . $b_stage . '". Reinstate after it moves to an active stage on its Talks card.' ) . '</p>';
ck( 'B2: the school outside the active stages is listed with its name, its date and its note, no Reinstate form, and the line in Airtable\'s words with the setting off', array(
	'' !== $b_row( $b_off, $b_name ),
	has( $b_row( $b_off, $b_name ), '<p class="wpcpm-ui-list__note">' . esc_html( 'Revoked after the rector wrote ' . $b_word . ' twice.' ) . '</p>' ),
	has( $b_row( $b_off, $b_name ), '<div class="wpcpm-ui-list__more">' . $b_line_off . '</div>' ),
	has( $b_row( $b_off, $b_name ), '<form' ),
	has( $b_row( $b_off, $b_name ), 'Reinstate<' ),
	has( $b_off, 'name="wpcpm_agreement_post" value="605"' ),
), array( true, true, true, false, false, false ) );
ck( 'B2: in site mode the line sends the Administrator to the Talks card, and nothing else of the row changes', array(
	has( $b_row( $b_on, $b_name ), '<div class="wpcpm-ui-list__more">' . $b_line_on . '</div>' ),
	has( $b_on, 'in Airtable.' ),
	str_replace( $b_line_on, $b_line_off, $b_on ) === $b_off,
), array( true, false, true ) );
ck( 'B2: the revoked school at an active stage keeps its form and draws no such line, and the fold still counts both', array(
	has( $b_off, 'name="wpcpm_agreement_post" value="604"' ),
	substr_count( $b_off, 'value="wpcpm_agreement_reinstate"' ),
	substr_count( $b_off, 'wpcpm-ui-list__more' ),
	has( $b_off, '3 revoked agreements' ),
), array( true, 1, 2, true ) );
$b_gone_line = '<div class="wpcpm-ui-list__more"><p class="wpcpm-ui-note">' . esc_html( 'Not in the site\'s list of institutions any more, so its agreement cannot be reinstated.' ) . '</p></div>';
ck( 'V4: a revoked school the site\'s list no longer holds is listed by its record ID with the line and no form, the same in both modes', array(
	has( $b_row( $b_off, $b_gone ), $b_gone_line ),
	has( $b_row( $b_on, $b_gone ), $b_gone_line ),
	has( $b_row( $b_off, $b_gone ), '<form' ),
	has( $b_off, 'name="wpcpm_agreement_post" value="606"' ),
), array( true, true, false, false ) );
ck( 'V6a: the shared part keeps a noted last row\'s note padding when the row has a more under it, so the two are two paragraphs', 1 === preg_match( '/\.wpcpm-ui-list__row:last-child:not\( \.wpcpm-ui-list__row--more \) > \.wpcpm-ui-list__note \{\s*padding-bottom: 0;/', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/parts.css' ) ), true );
ck( 'B2: the card asks the agreement class\'s one predicate, the one the handler asks', array(
	1 === preg_match( '/function render_agreement_rows\(.*?WPCPM_Institution_Agreement::reinstate_block\(/s', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-cards.php' ) ),
	1 === preg_match( '/function handle_reinstate\(.*?self::reinstate_block\(/s', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' ) ),
), array( true, true ) );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Collaboration Agreements: a revoked school outside the pipeline, a 60-character name, a 40-character word, a long stage', $b_off, 'administrator' );
	wpcpm_layout_keep( 'Collaboration Agreements: the same row in site mode', $b_on, 'administrator' );
}
unset( $GLOBALS['inst_rows'][ $b_school ] );

WPCPM_UI::set_live( array( 'administrator' ) );
$agr_page = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::set_live( array() );
list( $agr_outside, $agr_parts ) = parts_outside_switch( $agr_page );
ck( 'on the page, the agreements card draws its parts, and every one sits inside the switch\'s root', array(
	has( substr( $agr_page, (int) strpos( $agr_page, 'id="wpcpm-agreements"' ) ), '<span class="wpcpm-status">Awaiting review</span>' ),
	$agr_parts > 0,
	$agr_outside,
), array( true, true, array() ) );

echo "\n=== Requests from institutions on the parts, behind the switch (UI shared parts, Task 12) ===\n";

// With the switch on, each request (but a school's details request) is an item header: the institution linked,
// the red tag "Overdue" while it is overdue and no tag otherwise, then the request's kind and "Opened ..." as
// facts; the school's note as today; then the request class's decisions. The decisions here are this suite's
// stand-in for the class (top of the file): the Decide area the real class prints, Mark as handled and Decline
// sharing one panel, is pinned in bin/test-institution-request.php, which draws the real one. A details request
// (Task 12 fix round 1) is an item header holding today's sentence, the overdue tag, the asker and the age as facts,
// its changes in the parts' table, and the request class's own Decide area (`render_details_decide()`, faked here,
// drawn for real in bin/test-institution-request.php): Approve at once, Decline with the note the school reads.
$req_fixed = array(
	'open'    => array(
		array_merge( $GLOBALS['facts'][801], array( 'at' => 1756000000 ) ),
		array_merge( $GLOBALS['facts'][802], array( 'at' => 1756100000 ) ),
		array_merge( $details_facts, array( 'at' => 1756200000 ) ),
	),
	'closed'  => array( array_merge( $GLOBALS['facts'][803], array( 'closed_at' => 1756300000 ) ) ),
	'overdue' => 1,
);
$req_card = static function () use ( $req_fixed ) {
	return capture( static function () use ( $req_fixed ) { WPCPM_Administrators_Cards::render_requests( $req_fixed ); } );
};
$GLOBALS['uid']             = 3;
$GLOBALS['manage']          = array( 3 );
$GLOBALS['faithful_nonces'] = true;
// The earlier blocks draw the requests card on the parts too since its old branch left (Task 18b): only this block's
// two drawings are counted.
unset( $GLOBALS['details_decides'] );
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$req_on = $req_card();
$GLOBALS['flash'][ WPCPM_Typed_Text::KEPT_CHANNEL . 'request-close:810' ] = array( 'at' => time(), 'typed' => array( 'note' => "Too <short>\nand kept" ) );
$req_kept_on = $req_card();
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
unset( $GLOBALS['faithful_nonces'] );


$req_items = array();
if ( preg_match_all( '#<article class="wpcpm-request( wpcpm-request--details)?( wpcpm-administrator__item--overdue)? wpcpm-ui-item">(.*?)</article>#s', $req_on, $req_found, PREG_SET_ORDER ) ) {
	foreach ( $req_found as $found ) {
		$req_items[] = array( '' !== $found[1], '' !== $found[2], $found[3] );
	}
}
$req_header = static function ( $record, $name, $tag, array $facts ) {
	$run = '';
	foreach ( $facts as $fact ) {
		$run .= '<span class="wpcpm-ui-item__fact">' . $fact . '</span>';
	}
	return '<h4 class="wpcpm-ui-item__title"><a href="https://example.test/institution-dashboard/?wpcpm_institution_view=' . $record . '">' . $name . '</a></h4>'
		. '<p class="wpcpm-ui-item__facts">' . $tag . '<span class="wpcpm-ui-item__run">' . $run . '</span></p>';
};
// Task 17b, ruling 7: no old overdue modifier on the parts; the red tag says it.
ck( 'on, each request keeps its article, none carrying the old overdue modifier, and the details request its own', array_map( static function ( $item ) { return array( $item[0], $item[1] ); }, $req_items ), array( array( false, false ), array( false, false ), array( true, false ) ) );
ck( 'each opens with the item header: the institution linked, the red tag "Overdue" on the overdue one and no tag on the other, then the kind and "Opened ..." as muted facts; Overdue is not said again as a fact', array(
	0 === strpos( $req_items[0][2] ?? '', $req_header( $A, 'Uniwersytet Alpha', '<span class="wpcpm-status wpcpm-status--red">Overdue</span>', array( 'A mentor is wanted', 'Opened 4 hours ago' ) ) ),
	0 === strpos( $req_items[1][2] ?? '', $req_header( $B, 'Universidad Beta', '', array( 'A mentor is wanted', 'Opened 4 hours ago' ) ) ),
	substr_count( $req_items[0][2] ?? '', 'Overdue' ),
	substr_count( $req_on, 'wpcpm-ui-item__title' ),
), array( true, true, 1, 3 ) );
ck( 'then the school\'s note in today\'s words as the note part (Task 17b, ruling 1), then the request class\'s decisions bound for this card', array(
	// The stand-in prints what the real class prints on the parts: an intro line, then one Decide area (Task 18a).
	has( $req_items[0][2] ?? '', '</p><p class="wpcpm-ui-note">Two students without a mentor.</p><p class="wpcpm-ui-intro">The request.</p><div class="wpcpm-ui-decide" id="wpcpm-decide-req-' ),
	has( $req_items[1][2] ?? '', '</span></p><p class="wpcpm-ui-intro">The request.</p><div class="wpcpm-ui-decide" id="wpcpm-decide-req-' ),
	substr_count( $req_on, 'name="wpcpm_return_to" value="requests"' ),
), array( true, true, 2 ) );
ck( 'none of today\'s heading, kind or fact markup is left on the two ordinary requests', array(
	strpos( $req_items[0][2] ?? '', 'wpcpm-administrator__item-title' ),
	strpos( $req_items[0][2] ?? '', 'wpcpm-administrator__kind' ),
	strpos( $req_items[0][2] ?? '', 'wpcpm-administrator__fact' ),
	strpos( $req_items[1][2] ?? '', 'wpcpm-administrator__fact' ),
), array( false, false, false, false ) );

$req_details_on = preg_match( '#<article class="wpcpm-request wpcpm-request--details wpcpm-ui-item">(.*?)</article>#s', $req_on, $req_d_on ) ? $req_d_on[1] : 'none on';
ck( 'on, a details request opens with the item header: today\'s sentence with the institution linked, at the header\'s look, keeping its id; then no tag (it is in time) and the asker and the age as facts', array(
	0 === strpos( $req_details_on, '<h4 class="wpcpm-ui-item__title" id="wpcpm-request-810-title"><a href="https://example.test/institution-dashboard/?wpcpm_institution_view=' . $A . '">Uniwersytet Alpha</a> asks to correct its details</h4>'
		. '<p class="wpcpm-ui-item__facts"><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact">Asked by School Representative on ' . WPCPM_Dates::day( 1756200000 ) . '</span><span class="wpcpm-ui-item__fact">Opened 4 hours ago</span></span></p>' ),
	strpos( $req_details_on, 'wpcpm-administrator__' ),
), array( true, false ) );
ck( 'its changes are the parts\' table, labelled by the card\'s title and the request\'s, with no scroll box: Detail the row header, On record now, Asked for kept strong, every value as text', array(
	has( $req_details_on, '</p><table class="wpcpm-ui-table wpcpm-ui-table--pairs-2" role="table" aria-labelledby="wpcpm-requests-title wpcpm-request-810-title"><thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader" class="wpcpm-ui-cell--text">Detail</th><th scope="col" role="columnheader" class="wpcpm-ui-cell--token">On record now</th><th scope="col" role="columnheader" class="wpcpm-ui-cell--token">Asked for</th></tr></thead><tbody role="rowgroup">' ),
	has( $req_details_on, '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead">Name</th><td class="wpcpm-ui-cell--token" role="cell" data-label="On record now">Uniwersytet Alpha</td><td class="wpcpm-ui-cell--token" role="cell" data-label="Asked for"><strong>Alpha University &lt;b&gt;of Poland&lt;/b&gt;</strong></td></tr>' ),
	substr_count( $req_details_on, '<tr role="row">' ),
	strpos( $req_details_on, 'wpcpm-administrator__scroll' ),
	strpos( $req_details_on, '<b>of Poland</b>' ),
), array( true, true, 3, false, false ) );
ck( 'then the request class\'s Decide area, asked for once, bound for this card, after the table; no form of today\'s left', array(
	$GLOBALS['details_decides'] ?? null,
	strpos( $req_details_on, '</table>' ) < strpos( $req_details_on, 'data-stub="details-decide"' ),
	strpos( $req_details_on, 'wpcpm-request__decide' ),
	strpos( $req_details_on, 'A note for the school (required to decline)' ),
), array( array( array( 810, 'dashboard' ), array( 810, 'dashboard' ) ), true, false, false ) );
unset( $GLOBALS['details_decides'] );

$req_details_draw = static function ( array $facts ) {
	WPCPM_UI::set_live( array( 'administrator' ) );
	WPCPM_UI::enter( 'administrator' );
	$html = capture( static function () use ( $facts ) { WPCPM_Administrators_Cards::render_requests( array( 'open' => array( $facts ), 'closed' => array(), 'overdue' => 0 ) ); } );
	WPCPM_UI::leave();
	WPCPM_UI::set_live( array() );
	return $html;
};
$req_details_late = $req_details_draw( array_merge( $details_facts, array( 'at' => 1756200000, 'overdue' => true ) ) );
$req_details_none = $req_details_draw( array_merge( $details_facts, array( 'at' => 1756200000, 'changes' => array() ) ) );
$GLOBALS['uid']   = 9;
$GLOBALS['manage'] = array();
$req_details_seen = $req_details_draw( array_merge( $details_facts, array( 'at' => 1756200000 ) ) );
$GLOBALS['uid']    = 3;
$GLOBALS['manage'] = array( 3 );
ck( 'an overdue details request carries the red tag "Overdue", said once, and not the old overdue modifier (Task 17b, ruling 7)', array(
	has( $req_details_late, '<article class="wpcpm-request wpcpm-request--details wpcpm-ui-item">' ),
	has( $req_details_late, '</h4><p class="wpcpm-ui-item__facts"><span class="wpcpm-status wpcpm-status--red">Overdue</span><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact">Asked by ' ),
	substr_count( $req_details_late, 'Overdue' ),
), array( true, true, 1 ) );
ck( 'a request that holds nothing to approve says so as the parts\' muted line, draws no table, and still draws its Decide area', array(
	has( $req_details_none, '<p class="wpcpm-ui-list__empty">This request holds no change to approve.</p>' ),
	has( $req_details_none, '<table' ),
	has( $req_details_none, 'data-stub="details-decide"' ),
), array( true, false, true ) );
ck( 'an account that may not manage the program is drawn the table and no Decide area, as today', array( has( $req_details_seen, '<table' ), has( $req_details_seen, 'data-stub="details-decide"' ) ), array( true, false ) );
unset( $GLOBALS['details_decides'] );
// Task 17b, ruling 2: under today's fold and summary, each closed request is a list row: the institution its name,
// linked as today, then its kind, what became of it and which date this is as facts, each in today's words. Task V1,
// ruling 6: what became of it is the row's neutral tag, in the tag column; the kind and the date stay facts; the note
// the Administrator closed it with (`settle()` writes it to the request's note) is the row's note under it.
// The old bullet list left with its branch (Task 18b), so the one row is pinned to its words: the institution linked as
// the old list linked it, the kind, Handled and the closing day.
ck( 'Recently closed keeps today\'s fold and summary, and holds the parts\' list: one row per closed request, its institution linked as its name, its outcome as a neutral tag, its kind and its date as facts, in today\'s words; no bullet list left', array(
	// Under today's summary, now the inner fold's title, closed as today (Task 17b fix round, ruling 2).
	1 === preg_match( '#' . preg_quote( WPCPM_UI::inner_open( array( 'title' => 'Recently closed' ) ) . '<ul class="wpcpm-ui-list"><li class="wpcpm-ui-list__row wpcpm-ui-list__row--note"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name"><a href="https://example.test/institution-dashboard/?wpcpm_institution_view=' . $A . '">Uniwersytet Alpha</a></span><span class="wpcpm-status">Handled</span><span class="wpcpm-ui-list__facts"><span class="wpcpm-ui-item__fact">A mentor is wanted</span><span class="wpcpm-ui-item__fact">closed on ', '#' ) . '[^<]+' . preg_quote( '</span></span></div><p class="wpcpm-ui-list__note">Handled.</p></li></ul>' . WPCPM_UI::inner_close(), '#' ) . '#', $req_on ) && false === strpos( $req_on, 'wpcpm-administrator__closed' ),
	strpos( $req_on, 'wpcpm-administrator__list' ),
), array( true, false ) );
// Task 18f, point 8: Recently closed's "closed on" date keeps its day, month and year together.
ck( 'Task 18f: a closed request\'s date is whole in its row\'s facts', 1 === preg_match( '/closed on [^<\s]+\x{00A0}[^<\s]+\x{00A0}[^<\s]+ /u', $req_on ), true );
// Each outcome in its own words, read off the parts' rows (Task 18c: the old fold's check left in Task 18b): an
// approval, a decline and a withdrawal of a details request, and a mentor request still Handled.
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$closed_on = capture( static function () use ( $closed_rows ) { WPCPM_Administrators_Cards::render_requests( array( 'open' => array(), 'closed' => $closed_rows, 'overdue' => 0 ) ); } );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
preg_match_all( '#<span class="wpcpm-ui-list__name">(.*?)</span><span class="wpcpm-status">([^<]*)</span><span class="wpcpm-ui-list__facts"><span class="wpcpm-ui-item__fact">([^<]*)</span>#s', $closed_on, $closed_found, PREG_SET_ORDER );
ck( 'Recently closed names each outcome in its row\'s tag: Approved for an approval, Declined, Withdrawn, and Handled for the other kinds, each after its institution and before its kind', array_map( static function ( $row ) { return strip_tags( $row[1] ) . ' | ' . $row[2] . ' | ' . $row[3]; }, $closed_found ), array(
	'Uniwersytet Alpha | Approved | A change to its details',
	'Uniwersytet Alpha | Declined | A change to its details',
	'Uniwersytet Alpha | Withdrawn | A change to its details',
	'Uniwersytet Alpha | Handled | A mentor is wanted',
) );
list( $req_d_outside, $req_d_parts ) = parts_outside_switch( '<div class="wpcpm-dashboard wpcpm-ui-on">' . $req_on . $req_details_late . $req_details_none . '</div>' );
list( $req_d_bare ) = parts_outside_switch( $req_on );
ck( 'every part the details request draws sits inside the switch\'s root, and none is drawn but inside one', array( $req_d_parts > 0, $req_d_outside, count( $req_d_bare ) > 0 ), array( true, array(), true ) );

WPCPM_UI::set_live( array( 'administrator' ) );
$req_page = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::set_live( array() );
list( $req_outside, $req_parts ) = parts_outside_switch( $req_page );
ck( 'on the page, the requests card draws its item headers, and every part sits inside the switch\'s root', array(
	has( substr( $req_page, (int) strpos( $req_page, 'id="wpcpm-requests"' ) ), '<p class="wpcpm-ui-item__facts"><span class="wpcpm-status wpcpm-status--red">Overdue</span>' ),
	$req_parts > 0,
	$req_outside,
), array( true, true, array() ) );
$GLOBALS['uid']    = 0;
$GLOBALS['manage'] = array();

echo "\n=== Sponsor posts and offers to review on the parts, behind the switch (UI shared parts, Task 13a) ===\n";

// With the switch on, each sponsor post is an item header: the title linked to its preview, then the company,
// "Submitted ...", "By ..." and the link "Preview the post" as muted facts (the approved picture,
// sponsor-posts-to-review-card-open), then the posts class's decision. The decision is this suite's stand-in for the
// class (top of the file); the Decide area the real class prints, Publish at once and Return with a note in a panel,
// is pinned in bin/test-sponsor-posts.php, which draws the real one. The offers card draws each item through the
// review class, whose stand-in here opens the item as the real one does and holds a marker for its Decide area: the
// real item on the parts is pinned in bin/test-sponsor-offers.php.
$sp_fixed = array(
	array( 'id' => 905, 'title' => 'Ten tips & "tricks"', 'record' => 'recSPN00000000001', 'company' => 'TEST Sponsor', 'author' => 'Member One', 'at' => 1756000000, 'preview' => 'https://example.test/?p=905&preview=true' ),
	array( 'id' => 906, 'title' => 'A second guide', 'record' => 'recSPN00000000001', 'company' => 'TEST Sponsor', 'author' => '', 'at' => 1756100000, 'preview' => 'https://example.test/?p=906&preview=true' ),
);
$so_fixed = array( array( 'id' => 931, 'record' => 'recSPN00000000001', 'company' => 'TEST Sponsor', 'title' => 'A year of hosting', 'change' => 'new', 'by' => 'Member One', 'at' => 1756000000, 'fields' => array() ) );
$sp_card  = static function () use ( $sp_fixed ) {
	return capture( static function () use ( $sp_fixed ) { WPCPM_Administrators_Cards::render_sponsor_posts( $sp_fixed, 2 ); } );
};
$so_card  = static function () use ( $so_fixed ) {
	return capture( static function () use ( $so_fixed ) { WPCPM_Administrators_Cards::render_sponsor_offers( $so_fixed, 1 ); } );
};
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$sp_on = $sp_card();
$so_on = $so_card();
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );

$sp_items = array();
if ( preg_match_all( '#<article class="wpcpm-sponsor-post wpcpm-ui-item" id="wpcpm-sponsor-post-(\d+)">(.*?)</article>#s', $sp_on, $sp_found, PREG_SET_ORDER ) ) {
	foreach ( $sp_found as $found ) {
		$sp_items[ (int) $found[1] ] = $found[2];
	}
}
$sp_header = static function ( $id, $title, array $facts ) {
	$run = '';
	foreach ( $facts as $fact ) {
		$run .= '<span class="wpcpm-ui-item__fact">' . $fact . '</span>';
	}
	$run .= '<span class="wpcpm-ui-item__fact"><a href="https://example.test/?p=' . $id . '&preview=true">Preview the post</a></span>';
	return '<h4 class="wpcpm-ui-item__title"><a href="https://example.test/?p=' . $id . '&preview=true">' . $title . '</a></h4>'
		. '<p class="wpcpm-ui-item__facts"><span class="wpcpm-ui-item__run">' . $run . '</span></p>';
};
ck( 'on, each post keeps its article and its anchor, the one the card\'s links and the guides point at', array_keys( $sp_items ), array( 905, 906 ) );
ck( 'each opens with the item header: the title linked to its preview, then the company, "Submitted ..." and "By ..." as muted facts, and the link "Preview the post" last among them (the approved picture); no tag, as today no post carries a state', array(
	0 === strpos( $sp_items[905] ?? '', $sp_header( 905, 'Ten tips &amp; &quot;tricks&quot;', array( 'TEST Sponsor', 'Submitted 4 hours ago', 'By Member One' ) ) ),
	0 === strpos( $sp_items[906] ?? '', $sp_header( 906, 'A second guide', array( 'TEST Sponsor', 'Submitted 4 hours ago' ) ) ),
	substr_count( $sp_on, '>Preview the post</a>' ),
	strpos( ( $sp_items[905] ?? '' ) . ( $sp_items[906] ?? '' ), 'wpcpm-status' ),
), array( true, true, 2, false ) );
ck( 'then the posts class\'s decision, bound for this card, once per post; none of today\'s heading, kind or fact markup is left', array(
	has( $sp_items[905] ?? '', '</span></p><div class="wpcpm-ui-decide" id="wpcpm-decide-post-905" data-post="905" data-return="dashboard"></div>' ),
	has( $sp_items[906] ?? '', 'data-post="906" data-return="dashboard"' ),
	strpos( $sp_on, 'wpcpm-administrator__item-title' ),
	strpos( $sp_on, 'wpcpm-administrator__kind' ),
	strpos( $sp_on, 'wpcpm-administrator__fact' ),
), array( true, true, false, false, false ) );
ck( 'the card\'s own lines are today\'s: the way to the posts\' card on the Sponsors screen after the items', array(
	has( $sp_on, 'page=wpcpm-sponsors&amp;tab=queue#wpcpm-sponsor-posts' ) || has( $sp_on, 'page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-posts' ),
	strrpos( $sp_on, '</article>' ) < strpos( $sp_on, 'tab=queue#wpcpm-sponsor-posts' ),
), array( true, true ) );
ck( 'on, the offers card draws each item through the review class, bound for this card, as today: the item on the parts is the review class\'s, opened by the part (the stand-in prints what the real one does, Task 18a)', has( $so_on, '<article class="wpcpm-sponsor-offer wpcpm-ui-item" id="wpcpm-sponsor-offer-931"><div class="wpcpm-ui-decide" id="wpcpm-decide-offer-931" data-return="dashboard"></div></article>' ), true );
list( $sp_outside, $sp_parts ) = parts_outside_switch( '<div class="wpcpm-dashboard wpcpm-ui-on">' . $sp_on . $so_on . '</div>' );
list( $sp_bare ) = parts_outside_switch( $sp_on );
ck( 'every part the two cards draw sits inside the switch\'s root, and none is drawn but inside one', array( $sp_parts > 0, $sp_outside, count( $sp_bare ) > 0 ), array( true, array(), true ) );

$GLOBALS['uid']           = 3;
$GLOBALS['manage']        = array( 3 );
$sp_kept_posts            = $GLOBALS['sponsor_posts'] ?? array();
$GLOBALS['sponsor_posts'] = $sp_fixed;
WPCPM_UI::set_live( array( 'administrator' ) );
$sp_page = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::set_live( array() );
$GLOBALS['sponsor_posts'] = $sp_kept_posts;
$GLOBALS['uid']           = 0;
$GLOBALS['manage']        = array();
list( $sp_page_outside, $sp_page_parts ) = parts_outside_switch( $sp_page );
ck( 'on the page, the sponsor posts card draws its item headers with "Preview the post", and every part sits inside the switch\'s root', array(
	has( substr( $sp_page, (int) strpos( $sp_page, 'id="wpcpm-sponsor-posts"' ) ), '>Preview the post</a></span></span></p>' ),
	$sp_page_parts > 0,
	$sp_page_outside,
), array( true, true, array() ) );

echo "\n=== Sponsor agreements and sponsor reports on the parts, behind the switch (UI shared parts, Task 13b) ===\n";

// With the switch on, each sponsor agreement is an item header: the company (not a link, as today), its state as
// the tag in today's words ("Waiting for review", "Revoked"), then today's facts and, for an uploaded copy, the
// link "Download" as a muted fact (it decides nothing, design 2.6), then the agreement class's decision. The
// decision is this suite's stand-in for the class (top of the file); the Decide area the real class prints, Accept
// it at once and Return with a note in a panel, and Reinstate as the quiet action, is pinned in
// bin/test-sponsor-agreement.php, which draws the real one.
$sg_kept_facts                 = $GLOBALS['agr_facts'];
$GLOBALS['agr_facts'][914]     = array_merge( $GLOBALS['agr_facts'][913], array( 'post_id' => 914, 'kind' => 'legacy', 'sponsor_name' => 'Bold <b>&</b> "Co"', 'uploaded_by' => '', 'size' => 0, 'flags' => array( '/JS' ), 'members' => 2 ) );
$GLOBALS['agr_facts'][881]     = array_merge( $GLOBALS['agr_facts'][880], array( 'post_id' => 881, 'kind' => 'own', 'sponsor_name' => 'Third Sponsor', 'uploaded_by' => 'Member Two', 'size' => 1200, 'flags' => array(), 'members' => 1 ) );
$sg_data                       = array(
	'review'  => array( $GLOBALS['agr_facts'][913], $GLOBALS['agr_facts'][914] ),
	'revoked' => array( $GLOBALS['agr_facts'][880], $GLOBALS['agr_facts'][881] ),
);
$sg_states = array(
	'two of each'  => $sg_data,
	'one of each'  => array( 'review' => array( $GLOBALS['agr_facts'][913] ), 'revoked' => array( $GLOBALS['agr_facts'][880] ) ),
	'review only'  => array( 'review' => $sg_data['review'], 'revoked' => array() ),
	'revoked only' => array( 'review' => array(), 'revoked' => $sg_data['revoked'] ),
	'empty'        => array( 'review' => array(), 'revoked' => array() ),
);
$sg_card = static function ( array $data, $total = null ) {
	return capture( static function () use ( $data, $total ) { WPCPM_Administrators_Cards::render_sponsor_agreements( $data, $total ); } );
};
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$sg_on = array();
foreach ( $sg_states as $sg_state => $sg_fixture ) {
	$sg_on[ $sg_state ] = $sg_card( $sg_fixture );
}
$sg_more_on = $sg_card( $sg_data, 7 );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );

$sg_items = array();
if ( preg_match_all( '#<article class="wpcpm-sponsor-agreement wpcpm-ui-item" id="wpcpm-sponsor-agreement-(\d+)">(.*?)</article>#s', $sg_on['two of each'], $sg_found, PREG_SET_ORDER ) ) {
	foreach ( $sg_found as $found ) {
		$sg_items[ (int) $found[1] ] = $found[2];
	}
}
$sg_header = static function ( $name, $tag, array $facts ) {
	$run = '';
	foreach ( $facts as $fact ) {
		$run .= '<span class="wpcpm-ui-item__fact">' . $fact . '</span>';
	}
	return '<h4 class="wpcpm-ui-item__title">' . $name . '</h4><p class="wpcpm-ui-item__facts"><span class="wpcpm-status">' . $tag . '</span><span class="wpcpm-ui-item__run">' . $run . '</span></p>';
};
// Task V1, ruling 14: an agreement out of force is a list row (its Reinstate at the row's end), which keeps the anchor.
$sg_rows = array();
if ( preg_match_all( '#<li class="wpcpm-ui-list__row" id="wpcpm-sponsor-agreement-(\d+)">(.*?)</li>#s', $sg_on['two of each'], $sg_found, PREG_SET_ORDER ) ) {
	foreach ( $sg_found as $found ) {
		$sg_rows[ (int) $found[1] ] = $found[2];
	}
}
ck( 'on, each waiting agreement keeps its article and its anchor, the one the Sponsors screen\'s links point at, and each one out of force its anchor on its list row, waiting ones first', array( array_keys( $sg_items ), array_keys( $sg_rows ), strpos( $sg_on['two of each'], 'id="wpcpm-sponsor-agreement-914"' ) < strpos( $sg_on['two of each'], 'id="wpcpm-sponsor-agreement-880"' ) ), array( array( 913, 914 ), array( 880, 881 ), true ) );
ck( 'each waiting document opens with the item header: the company, not a link as today; "Waiting for review" as its tag; today\'s facts, the date in the site\'s date format as the rest of the dashboard prints one (Task 18d); "Download" as the last fact for an uploaded copy, none for a Drive copy', array(
	0 === strpos( $sg_items[913] ?? '', $sg_header( 'TEST Sponsor', 'Waiting for review', array( 'Uploaded by Member One on 6 September 2026', '652 B', 'The scan noticed none of the features it looks for', '1 account', '<a href="https://example.test/download/913">Download<span class="screen-reader-text"> TEST Sponsor</span></a>' ) ) ),
	0 === strpos( $sg_items[914] ?? '', $sg_header( 'Bold &lt;b&gt;&amp;&lt;/b&gt; &quot;Co&quot;', 'Waiting for review', array( 'Recorded on 6 September 2026', 'The scan noticed: JavaScript code (/JS)', '2 accounts' ) ) ),
), array( true, true ) );
$sg_row = static function ( $name, array $facts, $id ) {
	$run = '';
	foreach ( $facts as $fact ) {
		$run .= '<span class="wpcpm-ui-item__fact">' . $fact . '</span>';
	}
	return '<div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">' . $name . '</span><span class="wpcpm-status">Revoked</span><span class="wpcpm-ui-list__facts">' . $run . '</span><div class="wpcpm-ui-list__act"><form data-post="' . $id . '" data-return="dashboard"><button class="wpcpm-btn wpcpm-btn--quiet">Reinstate</button></form></div></div>';
};
ck( 'each agreement out of force is one list row of a list with its facts under the name (Task 18d: the facts-under layout with a tag): the company its name, "Revoked" its neutral tag, the same facts, the date in the site\'s date format, and Reinstate\'s form bound for this card at the row\'s end', array(
	$sg_rows[880] ?? '',
	$sg_rows[881] ?? '',
	substr_count( $sg_on['two of each'], '<h4 class="wpcpm-ui-h">Out of force</h4><ul class="wpcpm-ui-list wpcpm-ui-list--under"><li ' ),
), array(
	$sg_row( 'Old Sponsor', array( "Recorded on 1\u{00A0}May\u{00A0}2026", 'The scan noticed: javascript, an action that runs when the file is opened (/OpenAction)', '2 accounts' ), 880 ),
	$sg_row( 'Third Sponsor', array( "Uploaded by Member Two on 1\u{00A0}May\u{00A0}2026", '1200 B', 'The scan noticed none of the features it looks for', '1 account', '<a href="https://example.test/download/881">Download<span class="screen-reader-text"> Third Sponsor</span></a>' ), 881 ),
	1,
) );
// Task 18d review, Minor 4: the day the store keeps (`Y-m-d` on the site's clock) is the day printed on a site west of
// UTC and on one east of it, the waiting item's and the out of force row's alike: never the day before or after.
$sg_zones = array();
foreach ( array( 'America/Los_Angeles', 'Pacific/Kiritimati', 'Pacific/Pago_Pago', 'Asia/Tokyo' ) as $sg_zone ) {
	$sg_kept_tz     = $GLOBALS['tz'] ?? null;
	$GLOBALS['tz']  = $sg_zone;
	WPCPM_UI::set_live( array( 'administrator' ) );
	WPCPM_UI::enter( 'administrator' );
	$sg_zone_html   = $sg_card( $sg_data );
	WPCPM_UI::leave();
	WPCPM_UI::set_live( array() );
	if ( null === $sg_kept_tz ) {
		unset( $GLOBALS['tz'] );
	} else {
		$GLOBALS['tz'] = $sg_kept_tz;
	}
	$sg_zones[ $sg_zone ] = array( has( $sg_zone_html, 'Uploaded by Member One on 6 September 2026' ), has( $sg_zone_html, "Recorded on 1\u{00A0}May\u{00A0}2026" ), has( $sg_zone_html, "Uploaded by Member Two on 1\u{00A0}May\u{00A0}2026" ) );
}
ck( 'the agreement dates read the same day in a zone west of UTC and in one east of it (Task 18e)', $sg_zones, array_fill_keys( array( 'America/Los_Angeles', 'Pacific/Kiritimati', 'Pacific/Pago_Pago', 'Asia/Tokyo' ), array( true, true, true ) ) );
ck( 'then the agreement class\'s decision, bound for this card, once per agreement, right after the header; no tag is red (nothing here is overdue: a sponsor agreement has no deadline on this card today) and none of today\'s heading, kind or fact markup is left', array(
	has( $sg_items[913] ?? '', '</span></span></p><div class="wpcpm-ui-decide" id="wpcpm-decide-sagr-913" data-post="913" data-return="dashboard"></div>' ),
	has( $sg_items[914] ?? '', '</span></span></p><div class="wpcpm-ui-decide" id="wpcpm-decide-sagr-914" data-post="914" data-return="dashboard"></div>' ),
	has( $sg_rows[880] ?? '', 'data-post="880" data-return="dashboard"' ),
	has( $sg_rows[881] ?? '', 'data-post="881" data-return="dashboard"' ),
	strpos( implode( '', $sg_items ) . implode( '', $sg_rows ), 'wpcpm-status--' ),
	strpos( $sg_on['two of each'], 'wpcpm-administrator__item-title' ),
	strpos( $sg_on['two of each'], 'wpcpm-administrator__kind' ),
	strpos( $sg_on['two of each'], 'wpcpm-administrator__fact' ),
	strpos( $sg_on['two of each'], 'wpcpm-administrator__subheading' ),
), array( true, true, true, true, false, false, false, false, false ) );
ck( 'the agreements out of force keep today\'s heading, "Out of force", as an in-card heading, between the waiting documents and them; with none out of force there is no heading', array(
	substr_count( $sg_on['two of each'], '<h4 class="wpcpm-ui-h">Out of force</h4>' ),
	strpos( $sg_on['two of each'], 'id="wpcpm-sponsor-agreement-914"' ) < strpos( $sg_on['two of each'], '>Out of force</h4>' ) && strpos( $sg_on['two of each'], '>Out of force</h4>' ) < strpos( $sg_on['two of each'], 'id="wpcpm-sponsor-agreement-880"' ),
	has( $sg_on['review only'], 'Out of force' ),
	has( $sg_on['revoked only'], '<p class="wpcpm-ui-list__empty">No sponsor agreement is waiting for review.</p><h4 class="wpcpm-ui-h">Out of force</h4>' ),
), array( 1, true, false, true ) );
// Rewritten in Task 14's fix round 1 (rulings 2 and 3): on the parts the empty sentence is the parts' muted line, the
// line that says how many more wait the parts' muted note in today's words, and the way to the Sponsors screen the
// card's foot. The old lines left with the old branch (Task 18b): the note's words are pinned to today's sentence.
ck( 'the card\'s own lines: the empty sentence as the parts\' muted line, the line that says how many more wait as the parts\' muted note in today\'s words, under the waiting documents, then the way to the agreements\' card on the Sponsors screen as the card\'s foot', array(
	has( $sg_on['empty'], '<p class="wpcpm-ui-list__empty">No sponsor agreement is waiting for review.</p>' ),
	has( $sg_on['empty'], 'tab=queue#wpcpm-sponsor-agreements' ),
	1 === preg_match( '#<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">\d+ more (?:is|are) waiting; (?:it is|they are) listed here as these are decided\.</p>#', $sg_more_on ),
	strpos( $sg_more_on, 'wpcpm-ui-intro--muted' ) < strpos( $sg_more_on, '>Out of force</h4>' ),
	substr_count( $sg_more_on . implode( '', $sg_on ), 'wpcpm-administrator__more' ) + substr_count( $sg_more_on . implode( '', $sg_on ), 'wpcpm-administrator__empty' ),
	strrpos( $sg_on['two of each'], '</article>' ) < strpos( $sg_on['two of each'], 'tab=queue#wpcpm-sponsor-agreements' ),
), array( true, true, true, true, 0, true ) );
// Two agreements with a file on one card: each "Download" names its company to a screen reader, as "Open" and "Draft
// now" on the reports card name theirs, so the two links do not read alike (Task 13b's review, fixed in Task 13c).
$sg_dom = new DOMDocument();
libxml_use_internal_errors( true );
$sg_dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $sg_on['two of each'] . '</div>' );
libxml_clear_errors();
$sg_names = array_map( static function ( $a ) { return trim( preg_replace( '/\s+/', ' ', $a->textContent ) ); }, iterator_to_array( ( new DOMXPath( $sg_dom ) )->query( '//a[starts-with(normalize-space(.), "Download")]' ) ) );
ck( 'two agreements\' "Download" links have two different accessible names: the word, then the company for a screen reader alone, escaped', array(
	$sg_names,
	count( array_unique( $sg_names ) ),
), array( array( 'Download TEST Sponsor', 'Download Third Sponsor' ), 2 ) );
list( $sg_outside, $sg_parts ) = parts_outside_switch( '<div class="wpcpm-dashboard wpcpm-ui-on">' . implode( '', $sg_on ) . '</div>' );
list( $sg_bare ) = parts_outside_switch( $sg_on['two of each'] );
ck( 'every part the agreements card draws sits inside the switch\'s root, and none is drawn but inside one', array( $sg_parts > 0, $sg_outside, count( $sg_bare ) > 0 ), array( true, array(), true ) );
$GLOBALS['agr_facts'] = $sg_kept_facts;

// Sponsor reports: the three tables through the table part, the headings through the heading part with their ids
// kept, Open the quiet link to the report's editor, and Draft now the quiet button of the Sponsors screen's own
// draft form, drawn by its own renderer on the parts while the dashboard's switch is on.
$sr_states = array(
	'all'        => is_array( $sr_data ) ? $sr_data : array(),
	'none'       => array( 'queue' => array(), 'due' => array(), 'final' => array() ),
	'final only' => array( 'queue' => array(), 'due' => array(), 'final' => is_array( $sr_data ) ? $sr_data['final'] : array() ),
	'open only'  => array( 'queue' => is_array( $sr_data ) ? $sr_data['queue'] : array(), 'due' => is_array( $sr_data ) ? $sr_data['due'] : array(), 'final' => array() ),
	'marked'     => array(
		'queue' => array(),
		'due'   => array(),
		'final' => array( array( 'post_id' => 11, 'sponsor' => 'recSPN00000000009', 'name' => 'Bold Co', 'title' => 'Bold Co, 2024', 'until' => '2024-12-31', 'final_at' => 0, 'final_by' => 0, 'url' => $sr_editor . '11' ) ),
	),
);
// The one value that moves with the day the suite runs: when the fixture's final report was marked, a day in the
// current semester. Blanked before the digest.
$sr_when  = esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sr_final_at ) );
$sr_blank = static function ( $html ) use ( $sr_when ) {
	return str_replace( $sr_when, 'WHEN', (string) $html );
};
$sr_form_off   = capture( static function () { WPCPM_Sponsor_Report_Screen::render_draft_form( 'recSPN00000000002', '2026-09-15', WPCPM_Return::DASHBOARD ); } );
$sr_form_wpadm = capture( static function () { WPCPM_Sponsor_Report_Screen::render_draft_form( 'recSPN00000000002', '2026-09-15' ); } );
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'wpcpm-sponsors/reports' );
$sr_form_tab   = capture( static function () { WPCPM_Sponsor_Report_Screen::render_draft_form( 'recSPN00000000002', '2026-09-15', WPCPM_Return::DASHBOARD ); } );
$sr_wpadm_tab  = capture( static function () { WPCPM_Sponsor_Report_Screen::render_draft_form( 'recSPN00000000002', '2026-09-15' ); } );
WPCPM_UI::leave();
WPCPM_UI::enter( 'administrator' );
$sr_on = array();
foreach ( $sr_states as $sr_state => $sr_fixture ) {
	$sr_on[ $sr_state ] = sponsor_reports_card( $sr_fixture );
}
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );

// The digests are of the card and the draft form as they were drawn at 58b045ca, before Task 13b: these same
// fixtures run on that commit's code, before either renderer was changed, the moment the final report was marked
// blanked as "WHEN", and the days in the card's age tag, which grow with the day the suite runs (`no_age_days()`).
ck( 'off, and on the Sponsors screen\'s Reports tab (off in this release), the draft form is today\'s, byte for byte, the dashboard\'s and the screen\'s own', array(
	$sr_form_tab === $sr_form_off,
	$sr_wpadm_tab === $sr_form_wpadm,
	md5( $sr_form_off ),
	md5( $sr_form_wpadm ),
), array( true, true, '5d5db7a92a972c4c1b64c89400960dbd', 'ebb1c7010b0b4a9127722c911564ec92' ) );

$sr_dom = new DOMDocument();
libxml_use_internal_errors( true );
$sr_dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $sr_on['all'] . '</div>' );
libxml_clear_errors();
$sr_x   = new DOMXPath( $sr_dom );
$sr_all = $sr_on['all'];
// Task 18f, point 8: a report's period keeps each month with its year in its cell; it may break at "to".
ck( 'Task 18f: a report title\'s months keep their year in the cell', 1 === preg_match( '/data-label="Report">[^<]*\p{L}+\x{00A0}\d{4}/u', $sr_all ), true );
ck( 'on, the three in-card headings are the heading part, their ids kept (each table is named by the card\'s title and its heading, as today), and no scroll box is left: the table part is the box', array(
	has( $sr_all, '<h4 class="wpcpm-ui-h" id="wpcpm-sponsor-reports-drafts-title">Drafts to finish</h4><table class="wpcpm-ui-table wpcpm-ui-table--fixed wpcpm-ui-table--pairs-1" role="table" aria-labelledby="wpcpm-sponsor-reports-title wpcpm-sponsor-reports-drafts-title"><colgroup><col style="width:30%"><col><col style="width:12%"></colgroup>' ),
	has( $sr_all, '<h4 class="wpcpm-ui-h" id="wpcpm-sponsor-reports-ended-title">Terms ended with no report</h4><table class="wpcpm-ui-table wpcpm-ui-table--fixed wpcpm-ui-table--pairs-1" role="table" aria-labelledby="wpcpm-sponsor-reports-title wpcpm-sponsor-reports-ended-title"><colgroup><col style="width:30%"><col><col style="width:12%"></colgroup>' ),
	has( $sr_all, '<h4 class="wpcpm-ui-h" id="wpcpm-sponsor-reports-final-title">Final this semester</h4><table class="wpcpm-ui-table wpcpm-ui-table--fixed wpcpm-ui-table--pairs-2" role="table" aria-labelledby="wpcpm-sponsor-reports-title wpcpm-sponsor-reports-final-title"><colgroup><col style="width:30%"><col style="width:30%"><col><col style="width:12%"></colgroup>' ),
	strpos( $sr_all, 'wpcpm-administrator__scroll' ),
	strpos( $sr_all, 'wpcpm-admin-table' ),
	strpos( $sr_all, 'wpcpm-administrator__subtitle' ),
	strpos( $sr_all, 'wpcpm-button' ),
), array( true, true, true, false, false, false, false ) );
$sr_heads = static function ( $id ) use ( $sr_x ) {
	return array_map( static function ( $th ) { return $th->getAttribute( 'class' ) . ' ' . trim( $th->textContent ); }, iterator_to_array( $sr_x->query( '//table[contains(@aria-labelledby,"' . $id . '")]/thead//th' ) ) );
};
ck( 'their columns: the sponsor, then the report or the day, and an action column named "Action" for screen readers alone; the day a term ended is a date, which never wraps', array(
	$sr_heads( 'drafts-title' ),
	$sr_heads( 'ended-title' ),
	$sr_heads( 'final-title' ),
), array(
	array( 'wpcpm-ui-cell--text Sponsor', 'wpcpm-ui-cell--text Report', 'wpcpm-ui-cell--action Action' ),
	array( 'wpcpm-ui-cell--text Sponsor', 'wpcpm-ui-cell--date Term ended on', 'wpcpm-ui-cell--action Action' ),
	array( 'wpcpm-ui-cell--text Sponsor', 'wpcpm-ui-cell--text Report', 'wpcpm-ui-cell--text Marked final', 'wpcpm-ui-cell--action Action' ),
) );
ck( 'each row: the sponsor as the row\'s header, as today; each value under its column\'s name for the phone; Open, the quiet row action, linked to the report\'s editor as today with the report\'s title for a screen reader', array(
	has_words( $sr_all, '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead">TEST Sponsor</th><td class="wpcpm-ui-cell--text" role="cell" data-label="Report">TEST Sponsor, September 2025 to August 2026</td><td class="wpcpm-ui-cell--action" role="cell"><a class="wpcpm-btn wpcpm-btn--quiet" href="' . $sr_editor . '1301">Open<span class="screen-reader-text"> TEST Sponsor, September 2025 to August 2026</span></a></td></tr>' ),
	has_words( $sr_all, '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead">Old Sponsor</th><td class="wpcpm-ui-cell--date" role="cell" data-label="Term ended on">15 September 2026</td><td class="wpcpm-ui-cell--action" role="cell"><form ' ),
	has_words( $sr_all, '<td class="wpcpm-ui-cell--text" role="cell" data-label="Marked final">' . $sr_when . ' by Manager Three</td><td class="wpcpm-ui-cell--action" role="cell"><a class="wpcpm-btn wpcpm-btn--quiet" href="' . $sr_editor . '1302">Open<span class="screen-reader-text"> Old Sponsor, January 2025 to December 2025</span></a></td>' ),
), array( true, true, true ) );
ck( 'a report with no stamp keeps its words, "Not recorded", muted as an empty value', has( $sr_on['marked'], '<td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell" data-label="Marked final">Not recorded</td>' ), true );
$GLOBALS['faithful_nonces'] = true;
$sr_form_old   = capture( static function () { WPCPM_Sponsor_Report_Screen::render_draft_form( 'recSPN00000000002', '2026-09-15', WPCPM_Return::DASHBOARD ); } );
$sr_screen_old = capture( static function () { WPCPM_Sponsor_Report_Screen::render_draft_form( 'recSPN00000000002', '2026-09-15' ); } );
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$sr_form_new = capture( static function () { WPCPM_Sponsor_Report_Screen::render_draft_form( 'recSPN00000000002', '2026-09-15', WPCPM_Return::DASHBOARD ); } );
$sr_screen_on = capture( static function () { WPCPM_Sponsor_Report_Screen::render_draft_form( 'recSPN00000000002', '2026-09-15' ); } );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
unset( $GLOBALS['faithful_nonces'] );
ck( 'Draft now posts exactly what today\'s form posts: the same address, guard and busy words, the hidden fields in order with the nonce keyed to the sponsor and its referer, the term\'s last day and the way back to this card (posted fields)', array(
	agr_posted( $sr_form_new, WPCPM_Sponsor_Report_Screen::ACTION_DRAFT ),
	count( agr_posted( $sr_form_old, WPCPM_Sponsor_Report_Screen::ACTION_DRAFT ) ),
	agr_posted( $sr_form_old, WPCPM_Sponsor_Report_Screen::ACTION_DRAFT )[0]['hidden'] ?? null,
), array(
	agr_posted( $sr_form_old, WPCPM_Sponsor_Report_Screen::ACTION_DRAFT ),
	1,
	array( '_wpnonce=nonce-wpcpm_sreport_draft_recSPN00000000002', '_wp_http_referer=/administrator-dashboard/', 'action=wpcpm_sreport_draft', 'wpcpm_record=recSPN00000000002', 'wpcpm_until=2026-09-15', 'wpcpm_return=dashboard', 'wpcpm_return_to=sponsor-reports' ),
) );
ck( 'its one button is the quiet row action, "Draft now", naming the sponsor and the day for a screen reader as today; nothing of today\'s classes is left; the nonce has no id; the form sits in the row\'s action cell', array(
	has( $sr_form_new, '<button type="submit" class="wpcpm-btn wpcpm-btn--quiet">Draft now<span class="screen-reader-text"> for Old Sponsor, the term ending 15 September 2026</span></button></form>' ),
	strpos( $sr_form_new, 'wpcpm-administrator__form' ),
	strpos( $sr_form_new, 'wpcpm-button' ),
	strpos( $sr_form_new, 'id=' ),
	has( $sr_all, '<td class="wpcpm-ui-cell--action" role="cell"><form method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Drafting">' ),
), array( true, false, false, false, true ) );
ck( 'the Sponsors screen\'s own form, with its date box and no way back, is today\'s wherever it is drawn: it is never on the Administrator Dashboard', array( $sr_screen_on === $sr_screen_old, has( $sr_screen_old, 'type="date"' ) ), array( true, true ) );
// Task 14's fix round 1 (ruling 3): on the parts each empty sentence is the parts' muted line, as on the Semester
// reports card.
ck( 'the card\'s own lines: one sentence when nothing is in any part, each part\'s empty sentence under its heading, each the parts\' muted line in today\'s words, then the way to the Sponsors screen\'s Reports tab', array(
	has( $sr_on['none'], '<p class="wpcpm-ui-list__empty">Nothing to finish.</p>' ) && ! has( $sr_on['none'], 'wpcpm-ui-h' ),
	has( $sr_on['final only'], '<h4 class="wpcpm-ui-h" id="wpcpm-sponsor-reports-drafts-title">Drafts to finish</h4><p class="wpcpm-ui-list__empty">No draft to finish.</p><h4 class="wpcpm-ui-h" id="wpcpm-sponsor-reports-ended-title">Terms ended with no report</h4><p class="wpcpm-ui-list__empty">No term is waiting for its report.</p><h4 class="wpcpm-ui-h" id="wpcpm-sponsor-reports-final-title">' ),
	has( $sr_on['open only'], '</table><h4 class="wpcpm-ui-h" id="wpcpm-sponsor-reports-final-title">Final this semester</h4><p class="wpcpm-ui-list__empty">None marked final this semester yet.</p>' ),
	has( $sr_all, 'page=wpcpm-sponsors&tab=reports' ) || has( $sr_all, 'page=wpcpm-sponsors&amp;tab=reports' ),
), array( true, true, true, true ) );
// Every name and title is escaped on the parts too (Task 18c: the old card's check left in Task 18b). The renderer hands
// escaped values to the table part as markup, so a name and a title with markup in them are drawn here: the sponsor
// as the row's header, the title as a cell, the hidden words naming the sponsor on Draft now, and a report marked by an
// account that is gone, which says when alone.
$GLOBALS['sponsor_rows']['recSPN00000000009'] = array( 'record_id' => 'recSPN00000000009', 'name' => '<b>Bold</b> & Co', 'status' => 'Approved' );
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$sr_marked = sponsor_reports_card( array(
	'queue' => array( array( 'post_id' => 9, 'sponsor' => 'recSPN00000000009', 'name' => '<b>Bold</b> & Co', 'title' => '<i>Bold</i> & Co, January to December 2026', 'until' => '2026-12-31', 'final_at' => 0, 'final_by' => 0, 'url' => $sr_editor . '9' ) ),
	'due'   => array( array( 'sponsor' => 'recSPN00000000009', 'name' => '<b>Bold</b> & Co', 'until' => '2026-01-31' ) ),
	'final' => array(
		array( 'post_id' => 10, 'sponsor' => 'recSPN00000000009', 'name' => '<b>Bold</b> & Co', 'title' => '<u>Bold</u> & Co, 2025', 'until' => '2025-12-31', 'final_at' => $sr_final_at, 'final_by' => 999, 'url' => $sr_editor . '10' ),
	),
) );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
unset( $GLOBALS['sponsor_rows']['recSPN00000000009'] );
ck( 'on, every name and title is escaped: the sponsor as each table\'s row header, the title as its cell, the sponsor in Draft now\'s words for a screen reader and in Open\'s; and a report marked by an account that is gone says when alone', array(
	substr_count( $sr_marked, '<th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead">&lt;b&gt;Bold&lt;/b&gt; &amp; Co</th>' ),
	has_words( $sr_marked, '<td class="wpcpm-ui-cell--text" role="cell" data-label="Report">&lt;i&gt;Bold&lt;/i&gt; &amp; Co, January to December 2026</td>' ),
	has_words( $sr_marked, 'Open<span class="screen-reader-text"> &lt;i&gt;Bold&lt;/i&gt; &amp; Co, January to December 2026</span></a>' ),
	has_words( $sr_marked, 'Draft now<span class="screen-reader-text"> for &lt;b&gt;Bold&lt;/b&gt; &amp; Co, the term ending 31 January 2026</span></button>' ),
	has_words( $sr_marked, '<b>' ) || has_words( $sr_marked, '<i>' ) || has_words( $sr_marked, '<u>' ),
	has_words( $sr_marked, '<td class="wpcpm-ui-cell--text" role="cell" data-label="Marked final">' . $sr_when . '</td>' ),
	// Task 18d: the "Final this semester" title is escaped too, its fixture holding markup.
	has_words( $sr_marked, '<td class="wpcpm-ui-cell--text" role="cell" data-label="Report">&lt;u&gt;Bold&lt;/u&gt; &amp; Co, 2025</td>' ),
), array( 3, true, true, true, false, true, true ) );
list( $sr_outside, $sr_parts ) = parts_outside_switch( '<div class="wpcpm-dashboard wpcpm-ui-on">' . implode( '', $sr_on ) . '</div>' );
list( $sr_bare ) = parts_outside_switch( $sr_all );
ck( 'every part the reports card draws sits inside the switch\'s root, and none is drawn but inside one', array( $sr_parts > 0, $sr_outside, count( $sr_bare ) > 0 ), array( true, array(), true ) );

$GLOBALS['uid']    = 3;
$GLOBALS['manage'] = array( 3 );
WPCPM_UI::set_live( array( 'administrator' ) );
$sgr_page = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::set_live( array() );
$GLOBALS['uid']    = 0;
$GLOBALS['manage'] = array();
list( $sgr_page_outside, $sgr_page_parts ) = parts_outside_switch( $sgr_page );
ck( 'on the page, the agreements card draws its item headers and the reports card its tables, and every part sits inside the switch\'s root', array(
	has( substr( $sgr_page, (int) strpos( $sgr_page, 'id="wpcpm-sponsor-agreements"' ) ), '<span class="wpcpm-status">Waiting for review</span>' ),
	has( substr( $sgr_page, (int) strpos( $sgr_page, 'id="wpcpm-sponsor-reports"' ) ), '<table class="wpcpm-ui-table wpcpm-ui-table--fixed wpcpm-ui-table--pairs-1"' ),
	$sgr_page_parts > 0,
	$sgr_page_outside,
), array( true, true, true, array() ) );

echo "\n=== Events to review on the parts, behind the switch (UI shared parts, Task 13c) ===\n";

// With the switch on, each event is an item header: its title (not a link, as today), then its sponsor, when it runs,
// its places, how many joined and, for a sponsor that no longer offers events, "No longer offered", and the call
// link last among them, as muted facts; its description, folded when it is long, and who joined, folded, as inner
// folds; then one Decide area holding today's decisions. This suite draws the real queue over a stand-in store, the
// list's Join and Leave and the planner not loaded: the checks that need them, the posted fields, the refused and kept
// notes and the program's own events are in bin/test-sponsor-event-queue.php, which draws the real ones.
// The refusal markup the note boxes are drawn with on the parts, which the plugin loads on every request.
if ( ! class_exists( 'WPCPM_Form_Errors' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
}
$GLOBALS['uid']        = 3;
$GLOBALS['manage']     = array( 3 );
$GLOBALS['tiers_live'] = true;
$ev_card               = static function () use ( $se ) {
	return sponsor_events_card( $se );
};
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$ev_on = $ev_card();
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );

$ev_items = array();
if ( preg_match_all( '#<article class="wpcpm-sponsor-event wpcpm-ui-item" id="wpcpm-sponsor-event-(\d+)">(.*?)</article>#s', $ev_on, $ev_found, PREG_SET_ORDER ) ) {
	foreach ( $ev_found as $found ) {
		$ev_items[ (int) $found[1] ] = $found[2];
	}
}
$ev_header = static function ( $title, array $facts, $tag = '' ) {
	$run = '';
	foreach ( $facts as $fact ) {
		$run .= '<span class="wpcpm-ui-item__fact">' . $fact . '</span>';
	}
	return '<h4 class="wpcpm-ui-item__title">' . $title . '</h4><p class="wpcpm-ui-item__facts">' . ( '' !== $tag ? '<span class="wpcpm-status">' . $tag . '</span>' : '' ) . '<span class="wpcpm-ui-item__run">' . $run . '</span></p>';
};
$ev_link = static function ( $url ) {
	return 'Call link: <a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a>';
};
ck( 'on, each event keeps its article and its anchor, the one the Sponsors screen\'s queue tab links to, waiting ones first', array_keys( $ev_items ), array( 1401, 1402, 1403, 1404 ) );
ck( 'each opens with the item header: its title, not a link as today, then its group\'s words as its neutral tag (Task 18d), then its sponsor, when on the manager\'s clock, its places, how many joined once approved, "No longer offered" where the sponsor no longer offers events, and the call link last, as muted facts, every value escaped', array(
	0 === strpos( $ev_items[1401] ?? '', $ev_header( 'Q&amp;A &quot;live&quot; &lt;b&gt;', array( 'Bold &amp; &lt;b&gt;Co&lt;/b&gt;', '10 November 2026 7:00 pm to 8:30 pm, 90 minutes, in your time zone (UTC)', 'Open to everyone', $ev_link( 'https://call.example/room?pwd=a%2Fb&id=7' ) ), 'Waiting for the program&#039;s OK' ) ),
	0 === strpos( $ev_items[1402] ?? '', $ev_header( 'Short webinar', array( 'Paused Co', '5 November 2026 9:00 am to 9:45 am, 45 minutes, in your time zone (UTC)', '25 places', 'No longer offered', $ev_link( 'https://meet.example/short' ) ), 'Waiting for the program&#039;s OK' ) ),
	1 === preg_match( '#^<h4 class="wpcpm-ui-item__title">Launch party</h4><p class="wpcpm-ui-item__facts"><span class="wpcpm-status">Coming up</span><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact">[^<]+</span><span class="wpcpm-ui-item__fact">1 December 2026 10:00 am to 11:30 am, 90 minutes, in your time zone \(UTC\)</span><span class="wpcpm-ui-item__fact">40 places</span><span class="wpcpm-ui-item__fact">2 people have joined</span><span class="wpcpm-ui-item__fact">Call link: #', $ev_items[1403] ?? '' ),
	0 === strpos( $ev_items[1404] ?? '', $ev_header( 'Lapsed launch', array( 'Lapsed Co', '3 December 2026 10:00 am to 11:00 am, 60 minutes, in your time zone (UTC)', '10 places', 'Nobody has joined yet', 'No longer offered', $ev_link( 'https://meet.example/lapsed' ) ), 'Coming up' ) ),
	substr_count( $ev_on, '<span class="wpcpm-status">' ),
), array( true, true, true, true, 4 ) );
ck( 'a long description and who joined are inner folds, their content escaped as today; a short description is shown as it is', array(
	has( $ev_items[1401] ?? '', WPCPM_UI::inner_open( array( 'title' => 'Read the description' ) ) . '<p class="wpcpm-ui-note">' . esc_html( $se_long ) . '</p>' . WPCPM_UI::inner_close() ),
	has( $ev_items[1402] ?? '', '</span></span></p><p class="wpcpm-ui-note">About &lt;i&gt;it&lt;/i&gt;.</p>' ),
	has( $ev_items[1403] ?? '', WPCPM_UI::inner_open( array( 'title' => 'Who joined' ) ) . '<ul class="wpcpm-ui-list wpcpm-ui-list--under"><li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">Manager Three</span><span class="wpcpm-ui-list__facts"></span></div></li><li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">Zed &lt;b&gt;Joiner&lt;/b&gt;</span><span class="wpcpm-ui-list__facts"></span></div></li></ul>' . WPCPM_UI::inner_close() ),
	strpos( $ev_on, 'wpcpm-administrator__details' ),
), array( true, true, true, false ) );
$ev_x = new DOMXPath( ( static function ( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	return $doc;
} )( $ev_on ) );
$ev_row = static function ( $id ) use ( $ev_x ) {
	return array_map(
		static function ( $button ) {
			return $button->getAttribute( 'class' ) . ': ' . trim( $button->textContent );
		},
		iterator_to_array( $ev_x->query( '//div[@id="wpcpm-decide-event-' . $id . '"]/div[@class="wpcpm-ui-actions"]//button' ) )
	);
};
ck( 'then one Decide area per event, today\'s decisions in today\'s words: Approve, the one primary, and Return with a note on a waiting event; Return alone where the sponsor no longer offers events; Cancel with a note, red, on each approved one; nothing of today\'s decision markup left', array(
	$ev_row( 1401 ),
	$ev_row( 1402 ),
	$ev_row( 1403 ),
	$ev_row( 1404 ),
	strpos( $ev_on, 'class="button' ),
	strpos( $ev_on, 'wpcpm-sponsor-post__' ),
	strpos( $ev_on, 'wpcpm-request__decide' ),
	strpos( $ev_on, 'wpcpm-administrator__subtitle' ),
	strpos( $ev_on, 'wpcpm-administrator__item-title' ),
), array(
	// Each names its event to a screen reader after its words (Task 13c fix round 1).
	array( 'wpcpm-btn: Approve Q&A "live" <b>', 'wpcpm-btn wpcpm-btn--secondary: Return with a note Q&A "live" <b>' ),
	array( 'wpcpm-btn wpcpm-btn--secondary: Return with a note Short webinar' ),
	array( 'wpcpm-btn wpcpm-btn--danger: Cancel with a note Launch party' ),
	array( 'wpcpm-btn wpcpm-btn--danger: Cancel with a note Lapsed launch' ),
	false,
	false,
	false,
	false,
	false,
) );
ck( 'the card\'s own lines: no heading over a group, each event\'s tag says its group (design 2.10; Task 18d), one item after the next; then the way to the events\' card on the Sponsors screen after the items', array(
	strpos( $ev_on, '<h4 class="wpcpm-ui-h">' ),
	has( $ev_on, '</article><article class="wpcpm-sponsor-event wpcpm-ui-item" id="wpcpm-sponsor-event-1403">' ),
	strrpos( $ev_on, '</article>' ) < strpos( $ev_on, 'tab=queue#wpcpm-sponsor-events' ),
), array( false, true, true ) );
list( $ev_outside, $ev_parts ) = parts_outside_switch( '<div class="wpcpm-dashboard wpcpm-ui-on">' . $ev_on . '</div>' );
list( $ev_bare ) = parts_outside_switch( $ev_on );
ck( 'every part the Events card draws sits inside the switch\'s root, and none is drawn but inside one', array( $ev_parts > 0, $ev_outside, count( $ev_bare ) > 0 ), array( true, array(), true ) );

WPCPM_UI::set_live( array( 'administrator' ) );
$ev_page = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::set_live( array() );
list( $ev_page_outside, $ev_page_parts ) = parts_outside_switch( $ev_page );
ck( 'on the page, the Events card draws its item headers and Decide areas, and every part sits inside the switch\'s root', array(
	has( substr( $ev_page, (int) strpos( $ev_page, 'id="wpcpm-sponsor-events"' ) ), '<div class="wpcpm-ui-decide" id="wpcpm-decide-event-1401">' ),
	$ev_page_parts > 0,
	$ev_page_outside,
), array( true, true, array() ) );
$GLOBALS['uid']    = 0;
$GLOBALS['manage'] = array();

echo "\n=== Semester reports and the Overview's lines on the parts, behind the switch (UI shared parts, Task 14) ===\n";

// With the switch on, the Semester reports card's three parts are each an in-card heading through the heading part,
// its id kept (and one given to "Approved this semester", which had none, so its table is named as the other two
// are), then its table through the table part, named by the card's title and that heading, or today's empty
// sentence as the parts' muted line. Review and View are the quiet row action, linked as today; Draft now is the
// report screen's own form, which this suite stands in for (top of the file): the real form on the parts is pinned
// in bin/test-semester-report.php, which draws it.
$rp_data   = array(
	'queue'    => array(
		array( 'post_id' => 701, 'institution' => $A, 'cohort' => '2026-H1', 'generated' => 1756500000, 'origin' => 'auto', 'in_progress' => 1, 'age_days' => 5, 'approved_at' => 0, 'approved_by' => 0 ),
		array( 'post_id' => 703, 'institution' => $B, 'cohort' => '2025-H2', 'generated' => 1756400000, 'origin' => 'manager', 'in_progress' => 12, 'age_days' => 1, 'approved_at' => 0, 'approved_by' => 0 ),
	),
	'due'      => array(
		array( 'institution' => $B, 'cohort' => '2025-H2', 'in_progress' => 0, 'window_end' => '2025-12-31' ),
		array( 'institution' => $C, 'cohort' => '2026-H1', 'in_progress' => 3, 'window_end' => '2026-06-30' ),
	),
	'approved' => array(
		array( 'post_id' => 702, 'institution' => $C, 'cohort' => '2026-H1', 'generated' => 1756000000, 'origin' => 'manager', 'in_progress' => 0, 'age_days' => 9, 'approved_at' => 1756600000, 'approved_by' => 3 ),
		array( 'post_id' => 704, 'institution' => $A, 'cohort' => '2025-H2', 'generated' => 1755000000, 'origin' => 'auto', 'in_progress' => 0, 'age_days' => 2, 'approved_at' => 1756700000, 'approved_by' => 0 ),
	),
);
$rp_states = array(
	'all'           => $rp_data,
	'none'          => array( 'queue' => array(), 'due' => array(), 'approved' => array() ),
	'queue only'    => array( 'queue' => $rp_data['queue'], 'due' => array(), 'approved' => array() ),
	'due only'      => array( 'queue' => array(), 'due' => $rp_data['due'], 'approved' => array() ),
	'approved only' => array( 'queue' => array(), 'due' => array(), 'approved' => $rp_data['approved'] ),
);
$rp_card = static function ( array $data ) {
	return capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_reports( $data ); } );
};
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'wpcpm-institutions/reports' );
WPCPM_UI::leave();
WPCPM_UI::enter( 'administrator' );
$rp_on = array();
foreach ( $rp_states as $rp_state => $rp_fixture ) {
	$rp_on[ $rp_state ] = $rp_card( $rp_fixture );
}
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );

$rp_all = $rp_on['all'];
$rp_x   = new DOMXPath( ( static function ( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	return $doc;
} )( $rp_all ) );
ck( 'on, the three in-card headings are the heading part, the two ids kept and one given to the third; each table is named by the card\'s title and its heading, as today; no scroll box, table, subheading, list or button of today\'s is left', array(
	has( $rp_all, '<h4 class="wpcpm-ui-h" id="wpcpm-reports-review-title">To review</h4><table class="wpcpm-ui-table wpcpm-ui-table--wide wpcpm-ui-table--wide-936 wpcpm-ui-table--fixed wpcpm-ui-table--pairs-4" role="table" aria-labelledby="wpcpm-reports-title wpcpm-reports-review-title"><colgroup><col style="width:23%"><col style="width:20%"><col style="width:18%"><col style="width:15%"><col style="width:10%"><col style="width:14%"></colgroup>' ),
	has( $rp_all, '<h4 class="wpcpm-ui-h" id="wpcpm-reports-due-title">Due for drafting</h4><table class="wpcpm-ui-table wpcpm-ui-table--wide wpcpm-ui-table--wide-936 wpcpm-ui-table--fixed wpcpm-ui-table--pairs-3" role="table" aria-labelledby="wpcpm-reports-title wpcpm-reports-due-title"><colgroup><col style="width:23%"><col style="width:20%"><col style="width:18%"><col style="width:15%"><col></colgroup>' ),
	has( $rp_all, '<h4 class="wpcpm-ui-h" id="wpcpm-reports-approved-title">Approved this semester</h4><table class="wpcpm-ui-table wpcpm-ui-table--wide wpcpm-ui-table--wide-936 wpcpm-ui-table--fixed wpcpm-ui-table--pairs-2" role="table" aria-labelledby="wpcpm-reports-title wpcpm-reports-approved-title"><colgroup><col style="width:23%"><col style="width:20%"><col><col style="width:14%"></colgroup>' ),
	has( $rp_all, 'id="wpcpm-reports-title"' ),
	strpos( $rp_all, 'wpcpm-administrator__scroll' ),
	strpos( $rp_all, 'wpcpm-admin-table' ),
	strpos( $rp_all, 'wpcpm-administrator__subtitle' ),
	strpos( $rp_all, 'wpcpm-administrator__list' ),
	strpos( $rp_all, 'wpcpm-button' ),
	strpos( $rp_all, 'wpcpm-administrator__empty' ),
), array( true, true, true, true, false, false, false, false, false, false ) );
$rp_heads = static function ( $id ) use ( $rp_x ) {
	return array_map( static function ( $th ) { return $th->getAttribute( 'class' ) . ' ' . trim( $th->textContent ); }, iterator_to_array( $rp_x->query( '//table[contains(@aria-labelledby,"' . $id . '")]/thead//th' ) ) );
};
ck( 'their columns: the institution and the semester, then the facts, the count and the age numbers, the day a window ended a date, and an action column named "Action" for screen readers alone', array(
	$rp_heads( 'review-title' ),
	$rp_heads( 'due-title' ),
	$rp_heads( 'approved-title' ),
), array(
	array( 'wpcpm-ui-cell--text Institution', 'wpcpm-ui-cell--text Semester', 'wpcpm-ui-cell--text Drafted', 'wpcpm-ui-cell--num Still in progress', 'wpcpm-ui-cell--num Age', 'wpcpm-ui-cell--action Action' ),
	array( 'wpcpm-ui-cell--text Institution', 'wpcpm-ui-cell--text Semester', 'wpcpm-ui-cell--date Window ended', 'wpcpm-ui-cell--num Still in progress', 'wpcpm-ui-cell--action Action' ),
	array( 'wpcpm-ui-cell--text Institution', 'wpcpm-ui-cell--text Semester', 'wpcpm-ui-cell--text Approved', 'wpcpm-ui-cell--action Action' ),
) );
$rp_inst = static function ( $record, $name ) {
	return '<a href="https://example.test/institution-dashboard/?wpcpm_institution_view=' . $record . '">' . $name . '</a>';
};
$rp_link = static function ( $record, $cohort ) {
	return esc_url( add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, $record, WPCPM_Semester_Report_Screen::report_url( $cohort ) ) );
};
$rp_when = static function ( $ts ) {
	return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) );
};
// The institution is each row's header, as on every other wide table (Task 17b, ruling 4).
ck( 'each draft to review: the institution the row\'s header; today\'s values, each under its column\'s name for the phone; Review the quiet row action, linked to the report\'s editor as that institution as today, naming the institution and the semester for a screen reader', array(
	has_words( $rp_all, '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead">' . $rp_inst( $A, 'Uniwersytet Alpha' ) . '</th><td class="wpcpm-ui-cell--text" role="cell" data-label="Semester">' . esc_html( WPCPM_Cohort::label( '2026-H1' ) ) . '</td><td class="wpcpm-ui-cell--text" role="cell" data-label="Drafted">' . $rp_when( 1756500000 ) . ', by the site</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Still in progress">1</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Age">5 days</td><td class="wpcpm-ui-cell--action" role="cell"><a class="wpcpm-btn wpcpm-btn--quiet" href="' . $rp_link( $A, '2026-H1' ) . '">Review<span class="screen-reader-text"> Uniwersytet Alpha, ' . esc_html( WPCPM_Cohort::label( '2026-H1' ) ) . '</span></a></td></tr>' ),
	has_words( $rp_all, '<td class="wpcpm-ui-cell--text" role="cell" data-label="Drafted">' . $rp_when( 1756400000 ) . ', by an Administrator</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Still in progress">12</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Age">1 day</td>' ),
), array( true, true ) );
ck( 'each semester due for drafting: the day its window ended as a date, then the Draft now form in the action cell, drawn by the report screen as today, coming back here', array(
	has_words( $rp_all, '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead">' . $rp_inst( $C, 'Instituto Gamma' ) . '</th><td class="wpcpm-ui-cell--text" role="cell" data-label="Semester">' . esc_html( WPCPM_Cohort::label( '2026-H1' ) ) . '</td><td class="wpcpm-ui-cell--date" role="cell" data-label="Window ended">2026-06-30</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Still in progress">3</td><td class="wpcpm-ui-cell--action" role="cell"><form method="post" data-wpcpm-once><input type="hidden" name="action" value="wpcpm_report_draft" /><input type="hidden" name="institution" value="' . $C . '" /><input type="hidden" name="cohort" value="2026-H1" />' ),
	substr_count( $rp_all, 'name="wpcpm_return_to" value="reports"' ),
), array( true, 2 ) );
ck( 'each report approved this semester: when and by whom in the Approved column (the moment alone for an account no longer on the site), and View the quiet row action, linked to the report as today, naming the institution and the semester for a screen reader', array(
	has_words( $rp_all, '<td class="wpcpm-ui-cell--text" role="cell" data-label="Approved">' . $rp_when( 1756600000 ) . ' by Manager Three</td><td class="wpcpm-ui-cell--action" role="cell"><a class="wpcpm-btn wpcpm-btn--quiet" href="' . $rp_link( $C, '2026-H1' ) . '">View<span class="screen-reader-text"> Instituto Gamma, ' . esc_html( WPCPM_Cohort::label( '2026-H1' ) ) . '</span></a></td></tr>' ),
	has_words( $rp_all, '<td class="wpcpm-ui-cell--text" role="cell" data-label="Approved">' . $rp_when( 1756700000 ) . '</td>' ),
), array( true, true ) );
// Task 18f, point 8: a date printed in a table cell keeps its day, month and year together (no-break spaces), its time
// free to go to the next line; a semester's months keep their year; a range still breaks at "to".
$t18f_nb = static function ( $words ) { return str_replace( ' ', "\u{00A0}", (string) $words ); };
// Fix round 1 (Important 1): a semester's name is whole in each of the three tables (To review, Due for drafting,
// Approved this semester), each pinned on its own: every semester cell of the table holds no ordinary space.
$t18f_sem = array();
foreach ( array( 'wpcpm-reports-review-title', 'wpcpm-reports-due-title', 'wpcpm-reports-approved-title' ) as $t18f_tid ) {
	$t18f_at  = strpos( $rp_all, 'aria-labelledby="wpcpm-reports-title ' . $t18f_tid . '"' );
	$t18f_tbl = false === $t18f_at ? '' : substr( $rp_all, $t18f_at, (int) strpos( $rp_all, '</table>', $t18f_at ) - $t18f_at );
	preg_match_all( '#data-label="Semester">([^<]*)</td>#u', $t18f_tbl, $t18f_cells );
	$t18f_sem[ $t18f_tid ] = array( count( $t18f_cells[1] ) > 0, array_values( array_filter( $t18f_cells[1], static function ( $c ) { return false !== strpos( $c, ' ' ) || false === strpos( $c, "\u{00A0}" ); } ) ) );
}
ck( 'Task 18f fix round 1: each Semester reports table prints every semester\'s name whole', $t18f_sem, array_fill_keys( array( 'wpcpm-reports-review-title', 'wpcpm-reports-due-title', 'wpcpm-reports-approved-title' ), array( true, array() ) ) );
ck( 'Task 18f: in the Semester reports tables the drafted and approved dates are whole, the time after an ordinary space', array(
	true,
	has( $rp_all, '<td class="wpcpm-ui-cell--text" role="cell" data-label="Drafted">' . $t18f_nb( wp_date( get_option( 'date_format' ), 1756400000 ) ) . ' ' . wp_date( get_option( 'time_format' ), 1756400000 ) . ', by an Administrator</td>' ),
	has( $rp_all, '<td class="wpcpm-ui-cell--text" role="cell" data-label="Approved">' . $t18f_nb( wp_date( get_option( 'date_format' ), 1756700000 ) ) . ' ' . wp_date( get_option( 'time_format' ), 1756700000 ) . '</td>' ),
), array( true, true, true ) );
$rp_names = array_map( static function ( $a ) { return trim( preg_replace( '/\s+/', ' ', $a->textContent ) ); }, iterator_to_array( $rp_x->query( '//td[contains(@class,"wpcpm-ui-cell--action")]/a' ) ) );
ck( 'no two row actions on the card read alike to a screen reader', array( count( $rp_names ), count( array_unique( $rp_names ) ) ), array( 4, 4 ) );
ck( 'each part with nothing in it says today\'s sentence under its heading, as the parts\' muted line; the card ends with the way to the Institutions screen\'s Reports tab, as today', array(
	has( $rp_on['none'], '<h4 class="wpcpm-ui-h" id="wpcpm-reports-review-title">To review</h4><p class="wpcpm-ui-list__empty">No draft is waiting for review.</p><h4 class="wpcpm-ui-h" id="wpcpm-reports-due-title">Due for drafting</h4><p class="wpcpm-ui-list__empty">Nothing is waiting: every finished semester has a report, or is not finished yet.</p><h4 class="wpcpm-ui-h" id="wpcpm-reports-approved-title">Approved this semester</h4><p class="wpcpm-ui-list__empty">No report has been approved this semester yet.</p><p class="wpcpm-ui-fold__foot"><a href="' ),
	has( $rp_on['queue only'], '</table><h4 class="wpcpm-ui-h" id="wpcpm-reports-due-title">Due for drafting</h4><p class="wpcpm-ui-list__empty">' ),
	has( $rp_on['approved only'], '<p class="wpcpm-ui-list__empty">Nothing is waiting: every finished semester has a report, or is not finished yet.</p><h4 class="wpcpm-ui-h" id="wpcpm-reports-approved-title">Approved this semester</h4><table ' ),
	1 === preg_match( '#</table><p class="wpcpm-ui-fold__foot"><a href="[^"]*tab=reports[^"]*">#', $rp_all ),
	array_sum( array_map( static function ( $html ) { return substr_count( $html, 'wpcpm-administrator__more' ); }, $rp_on ) ),
), array( true, true, true, true, 0 ) );
list( $rp_outside, $rp_parts ) = parts_outside_switch( '<div class="wpcpm-dashboard wpcpm-ui-on">' . implode( '', $rp_on ) . '</div>' );
list( $rp_bare ) = parts_outside_switch( $rp_all );
ck( 'every part the Semester reports card draws sits inside the switch\'s root, and none is drawn but inside one', array( $rp_parts > 0, $rp_outside, count( $rp_bare ) > 0 ), array( true, array(), true ) );

// Task 14, fix round 1. Ruling 1: on the parts every card's foot, "Open on the ... screen", is the fold's own foot,
// the last thing in the card's body, its link and words today's. Read on four
// converted cards: Semester reports, Institution applications, Sponsor Collaboration Agreements and Sponsor reports.
$ft_last = static function ( $html ) {
	return 1 === preg_match( '#<p class="wpcpm-ui-fold__foot"><a href="[^"]+">Open on the [A-Za-z]+ screen(<span class="screen-reader-text">[^<]*</span>)?</a></p></div></details>#', $html )
		&& 1 === substr_count( $html, 'wpcpm-ui-fold__foot' )
		&& false === strpos( $html, 'wpcpm-administrator__more' );
};
ck( 'on, every card\'s foot is the fold\'s own, the last thing in the body, on the Semester reports, Institution applications, Sponsor Collaboration Agreements and Sponsor reports cards; none of today\'s line is left', array(
	$ft_last( $rp_all ),
	$ft_last( $rp_on['none'] ),
	$ft_last( $parts_apps ),
	$ft_last( $sg_on['two of each'] ),
	$ft_last( $sr_all ),
	$ft_last( $sr_on['none'] ),
), array( true, true, true, true, true, true ) );

// Ruling 6: a row whose report has no address (the Institution Dashboard's page is missing) has no action in its
// cell, rather than a quiet button with an empty link, which would print as a submit outside any form; Draft now
// needs no address and stays. Ruling 7: an approval with no moment recorded keeps its words, "Not recorded", muted.
$GLOBALS['no_report_url'] = true;
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$rp_nolink = $rp_card( $rp_data );
$rp_unstamped = $rp_card( array( 'queue' => array(), 'due' => array(), 'approved' => array( array_merge( $rp_data['approved'][0], array( 'approved_at' => 0, 'approved_by' => 0 ) ) ) ) );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
unset( $GLOBALS['no_report_url'] );
$rp_cells = static function ( $html, $title ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	$out = array();
	foreach ( ( new DOMXPath( $doc ) )->query( '//table[contains(@aria-labelledby,"' . $title . '")]//td[contains(@class,"wpcpm-ui-cell--action")]' ) as $td ) {
		$inner = '';
		foreach ( $td->childNodes as $child ) {
			$inner .= $doc->saveHTML( $child );
		}
		$out[] = $inner;
	}
	return $out;
};
// The stand-in's Draft now prints the real one's quiet button on the parts (Task 18a), so the card's buttons are the due
// rows' Draft now alone.
ck( 'a row whose report has no address has nothing in its action cell, in the To review and Approved tables; no button but Draft now and no empty link anywhere on the card; Draft now stays in each due row', array(
	$rp_cells( $rp_nolink, 'review-title' ),
	$rp_cells( $rp_nolink, 'approved-title' ),
	substr_count( $rp_nolink, '<button' ) === substr_count( $rp_nolink, '>Draft now</button>' ),
	strpos( $rp_nolink, 'href=""' ),
	count( array_filter( $rp_cells( $rp_nolink, 'due-title' ), static function ( $c ) { return 0 === strpos( $c, '<form ' ); } ) ),
), array( array( '', '' ), array( '', '' ), true, false, 2 ) );
ck( 'an approval with no moment recorded keeps the words "Not recorded", muted as an empty value', has( $rp_unstamped, '<td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell" data-label="Approved">Not recorded</td>' ), true );

// The Overview's group: its heading and its lead through the group's parts, and the sentence that names every
// empty queue the group's note, 22px under the last row.
$GLOBALS['uid']    = 3;
$GLOBALS['manage'] = array( 3 );
$gp_quiet          = static function ( $html ) {
	return preg_match( '#<p class="(wpcpm-overview__quiet|wpcpm-ui-group__note)">Nothing waits.*?</p>#s', $html, $m ) ? $m[0] : '';
};
$gp_head           = static function ( $html ) {
	return preg_match( '#<div class="wpcpm-administrator__group wpcpm-administrator__group--waiting[^"]*">.*?</p>#s', $html, $m ) ? $m[0] : '';
};
WPCPM_UI::set_live( array( 'administrator' ) );
// A student waits to graduate (1.123.93), so the one empty queue is still the sponsor offers'.
$GLOBALS['unlinked'] = pending_unlinked();
$gp_on_page          = WPCPM_Administrators_Dashboard::render( array() );
unset( $GLOBALS['unlinked'] );
WPCPM_UI::set_live( array() );
$GLOBALS['uid']    = 0;
$GLOBALS['manage'] = array();
ck( 'on, the group keeps its wrapper and its classes, the theme\'s and the anchors\', and draws its heading and lead as the group\'s parts, none of today\'s heading classes left', array(
	$gp_head( $gp_on_page ),
	has( $gp_on_page, 'wpcpm-administrator__group-title' ) || has( $gp_on_page, 'wpcpm-administrator__group-lead' ),
), array(
	'<div class="wpcpm-administrator__group wpcpm-administrator__group--waiting wpcpm-ui-group"><h2 class="wpcpm-ui-group__title wpcpm-ui-group__title--lead">Waiting for you</h2><p class="wpcpm-ui-group__lead">What needs an Administrator&#039;s decision, oldest first in each.</p>',
	false,
) );
ck( 'on, the sentence naming the empty queues is the group\'s note, its words and links today\'s, after the last row and inside the group', array(
	// The old sentence left with its branch (Task 18b): its words and link, read at 76b5ecff, where this compared the two.
	$gp_quiet( $gp_on_page ) === '<p class="wpcpm-ui-group__note">Nothing waits in the 1 other queue: <a href="https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-offers-review">Sponsor offers to review</a>. Its name opens its screen in wp-admin.</p>',
	'' !== $gp_quiet( $gp_on_page ),
	has( $gp_on_page, '</section>' . $gp_quiet( $gp_on_page ) . '</div>' ),
	has( $gp_on_page, 'wpcpm-overview__quiet' ),
), array( true, true, true, false ) );
list( $gp_outside, $gp_parts ) = parts_outside_switch( $gp_on_page );
ck( 'on the page, every part sits inside the switch\'s root', array( $gp_parts > 0, $gp_outside ), array( true, array() ) );

echo "\n=== Sponsor applications on the parts, behind the switch (UI shared parts, Task 13d) ===\n";

// With the switch on, each sponsor application is an item header: the company linked to the application on the
// Sponsors screen, as today, its reference, the state as a neutral tag in today's words, then today's facts; the
// notes as today; "Read the application" an inner fold around the application class's details; then the class's
// decision. The details and the decision are this suite's stand-ins for the class (top of the file); the real
// ones on the parts, the answers as a key and value table and one Decide area, are pinned in
// bin/test-sponsor-application.php, which draws the real class.
seed_post( 953, 'wpcpm_sponsor_app', 'Held & "Co"' );
$sx_fixed = array(
	$GLOBALS['sponsor_apps'][0],
	array( 'id' => 953, 'reference' => 'SAPP-2026-0953', 'company' => 'Held & "Co"', 'website' => '', 'person' => 'Pat Person', 'email' => 'held@example.test', 'at' => time() - 90000, 'state' => 'held', 'state_label' => 'held', 'signals' => array(), 'held' => true, 'duplicate' => true, 'in_base' => false, 'half_done' => true, 'tier_requested' => 'unsure', 'tier_requested_label' => 'Not sure yet' ),
);
$sx_card = static function () use ( $sx_fixed ) {
	return capture( static function () use ( $sx_fixed ) { WPCPM_Administrators_Cards::render_sponsor_applications( $sx_fixed, 5 ); } );
};
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$sx_on = $sx_card();
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );

$sx_items = array();
if ( preg_match_all( '#<article class="wpcpm-sponsor-application wpcpm-ui-item" id="wpcpm-sponsor-application-(\d+)">(.*?)</article>#s', $sx_on, $sx_found, PREG_SET_ORDER ) ) {
	foreach ( $sx_found as $found ) {
		$sx_items[ (int) $found[1] ] = $found[2];
	}
}
$sx_header = static function ( $id, $name, $ref, $state, array $facts ) {
	$run = '';
	foreach ( $facts as $fact ) {
		$run .= '<span class="wpcpm-ui-item__fact">' . $fact . '</span>';
	}
	return '<h4 class="wpcpm-ui-item__title"><a href="https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&wpcpm_sapp_id=' . $id . '">' . $name . '</a><code class="wpcpm-ui-item__ref">' . $ref . '</code></h4>'
		. '<p class="wpcpm-ui-item__facts"><span class="wpcpm-status">' . $state . '</span><span class="wpcpm-ui-item__run">' . $run . '</span></p>';
};
ck( 'on, each application keeps its article and its anchor, the one the opened application on the Sponsors screen and a decision\'s way back link to', array_keys( $sx_items ), array( 950, 953 ) );
ck( 'each opens with the item header: the company linked as today, the reference, the state as a neutral tag in today\'s words, then today\'s facts (when it was applied, the website, the contact, the tier answer)', array(
	0 === strpos( $sx_items[950] ?? '', $sx_header( 950, 'Gadgetry Inc', 'SAPP-2026-0950', 'new', array( 'Applied 4 hours ago', 'https://gadgetry.example', 'Contact: Sam Sponsor, administrator@example.test' ) ) ),
	0 === strpos( $sx_items[953] ?? '', $sx_header( 953, 'Held &amp; &quot;Co&quot;', 'SAPP-2026-0953', 'held', array( 'Applied 4 hours ago', 'Contact: Pat Person, held@example.test', 'Tier: not sure yet' ) ) ),
	substr_count( implode( '', $sx_items ), 'wpcpm-status--' ),
), array( true, true, 0 ) );
ck( 'the notes follow the header in today\'s words and markup: held, the other open application, the record the site holds, the half-done approval', array(
	has( $sx_items[950] ?? '', '</span></p><p class="wpcpm-ui-note">The site already holds a sponsor with this name or website. Approving creates a second record.</p>' ),
	substr_count( $sx_items[953] ?? '', '<p class="wpcpm-ui-note">' ),
	has( $sx_items[953] ?? '', 'Held by the anti-spam checks. Open it on the Sponsors screen to read which.' ),
	has( $sx_items[953] ?? '', 'The approval of this application is half done' ),
), array( true, 3, true, true ) );
// The tier the applicant is considering (1.113.0), read off the parts' facts (Task 18c: the old card's check left in
// Task 18b): a tier says "Considering:" and its label as the last fact; not sure yet says so in its own words; no
// answer, or facts from before the question, says nothing.
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$sx_tiered = capture( static function () {
	WPCPM_Administrators_Cards::render_sponsor_applications( array(
		array_merge( $GLOBALS['sponsor_apps'][0], array( 'tier_requested' => 'backer', 'tier_requested_label' => 'Backer' ) ),
		array_merge( $GLOBALS['sponsor_apps'][0], array( 'id' => 951, 'reference' => 'SAPP-2026-0951', 'company' => 'Quiet Co', 'tier_requested' => '', 'tier_requested_label' => '' ) ),
		array_merge( $GLOBALS['sponsor_apps'][0], array( 'id' => 952, 'reference' => 'SAPP-2026-0952', 'company' => 'Unsure Co', 'tier_requested' => 'unsure', 'tier_requested_label' => 'Not sure yet' ) ),
	) );
} );
$sx_untiered = capture( static function () { WPCPM_Administrators_Cards::render_sponsor_applications( $GLOBALS['sponsor_apps'] ); } );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
$sx_last_fact = static function ( $html, $id ) {
	return preg_match( '#id="wpcpm-sponsor-application-' . $id . '"><h4 .*?<span class="wpcpm-ui-item__run">(?:<span class="wpcpm-ui-item__fact">[^<]*(?:<a [^>]*>[^<]*</a>)?[^<]*</span>)*<span class="wpcpm-ui-item__fact">((?:(?!</span>).)*)</span></span></p>#s', (string) $html, $m ) ? $m[1] : null;
};
ck( 'an application considering a tier says so as its last fact, one not sure yet says that in its own words, and one with no answer, or facts from before the question, says nothing (1.113.0)', array(
	$sx_last_fact( $sx_tiered, 950 ),
	$sx_last_fact( $sx_tiered, 952 ),
	has( $sx_tiered, 'Considering:' ) ? substr_count( $sx_tiered, 'Considering:' ) : 0,
	substr_count( $sx_tiered, 'Tier: not sure yet' ),
	has( $sx_untiered, 'Considering:' ) || has( $sx_untiered, 'Tier: not sure yet' ),
), array( 'Considering: Backer', 'Tier: not sure yet', 1, 1, false ) );
$sx_fold = static function ( $id ) {
	return '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Read the application</span></span><span class="wpcpm-ui-fold__chev" aria-hidden="true">'
		. WPCPM_Icons::ui( 'chevron', 20 ) . '</span></summary><div class="wpcpm-ui-fold__body"><table class="wpcpm-ui-table wpcpm-ui-table--keyvalue" role="table" data-post="' . $id . '"><tbody role="rowgroup"></tbody></table></div></details>'
		. '<div class="wpcpm-ui-decide" id="wpcpm-decide-sapp-' . $id . '" data-post="' . $id . '" data-return="dashboard"></div>';
};
ck( '"Read the application" is an inner fold, closed, around the class\'s details, and the class\'s decision bound for this card follows it, once per application', array(
	has( $sx_items[950] ?? '', $sx_fold( 950 ) ),
	has( $sx_items[953] ?? '', $sx_fold( 953 ) ),
	substr_count( $sx_on, 'wpcpm-ui-fold--inner' ),
), array( true, true, 2 ) );
ck( 'none of today\'s heading, kind, fact or fold markup is left', array(
	strpos( $sx_on, 'wpcpm-administrator__item-title' ),
	strpos( $sx_on, 'wpcpm-administrator__kind' ),
	strpos( $sx_on, 'wpcpm-administrator__fact' ),
	strpos( $sx_on, 'wpcpm-administrator__details' ),
	strpos( $sx_on, 'wpcpm-administrator__more' ),
), array( false, false, false, false, false ) );
ck( 'the card\'s own lines are the parts\': the muted note saying how many more wait, then the card\'s foot to the applications\' card on the Sponsors screen', array(
	has( $sx_on, '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">3 more are waiting; they are listed here as these are decided.</p>' ),
	has( $sx_on, '<p class="wpcpm-ui-fold__foot"><a href="https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&amp;tab=queue#wpcpm-sponsor-applications">Open on the Sponsors screen' ) || has( $sx_on, '<p class="wpcpm-ui-fold__foot"><a href="https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-applications">Open on the Sponsors screen' ),
	strrpos( $sx_on, '</article>' ) < strpos( $sx_on, 'wpcpm-ui-intro--muted' ),
), array( true, true, true ) );
$sx_empty = capture( static function () {
	WPCPM_UI::set_live( array( 'administrator' ) );
	WPCPM_UI::enter( 'administrator' );
	WPCPM_Administrators_Cards::render_sponsor_applications( array() );
	WPCPM_UI::leave();
	WPCPM_UI::set_live( array() );
} );
ck( 'with nothing waiting, the card\'s empty line is the parts\' muted line in today\'s words', array( has( $sx_empty, 'No sponsor application is waiting.' ), has( $sx_empty, 'wpcpm-administrator__empty' ) ), array( true, false ) );
list( $sx_outside, $sx_parts ) = parts_outside_switch( '<div class="wpcpm-dashboard wpcpm-ui-on">' . $sx_on . '</div>' );
list( $sx_bare ) = parts_outside_switch( $sx_on );
ck( 'every part the card draws sits inside the switch\'s root, and none is drawn but inside one', array( $sx_parts > 0, $sx_outside, count( $sx_bare ) > 0 ), array( true, array(), true ) );

echo "\n=== Four Watch cards on the parts, behind the switch (UI shared parts, Task 15a) ===\n";

// With the switch on, Offers running low and Sponsor terms ending are each one table through the table part, named
// by the card's title as today's scroll box was (the table part is the box); New interests is a list of rows, the
// sponsor's name linked as today, how long ago as a fact and the sponsor's own words as the row's note; Duplicated
// students says its sentence as the parts' intro line and ends with the way to the finder as the fold's foot. Every
// card's way to its home is the shell's quiet button after the fold. Nothing on these four cards is a form.
$wl_low   = array(
	array( 'id' => 941, 'title' => 'Pro <b>license</b>', 'sponsor' => 'recSPN00000000001', 'sponsor_name' => 'TEST & Sponsor', 'available' => 3, 'low' => 10, 'url' => 'https://site.example/sponsor-dashboard/?wpcpm_view=offers#wpcpm-sponsor-offers' ),
	array( 'id' => 942, 'title' => 'Free plan', 'sponsor' => 'recSPN00000000002', 'sponsor_name' => 'Other Co', 'available' => 1200, 'low' => 1500, 'url' => '' ),
);
$wl_int   = array(
	array( 'sponsor' => 'recSPN00000000001', 'sponsor_name' => 'TEST Sponsor', 'time' => 1757000000, 'message' => "Line one\nLine \"two\" <b>bold</b>", 'url' => 'https://site.example/sponsor-dashboard/?wpcpm_view=sponsorship#wpcpm-sponsor-sponsorship' ),
	array( 'sponsor' => 'recSPN00000000002', 'sponsor_name' => 'Quiet & Co', 'time' => 1756900000, 'message' => 'Hello.', 'url' => '' ),
);
$wl_edit  = 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=sponsors&edit=';
$wl_tiers = array(
	array( 'record' => 'recSPN00000000002', 'name' => 'Bold & Co', 'tier' => 'Champion', 'until' => '2026-11-14', 'days' => 0, 'manager' => '', 'url' => $wl_edit . 'recSPN00000000002' ),
	array( 'record' => 'recSPN00000000003', 'name' => 'Next Co', 'tier' => 'Backer', 'until' => '2026-11-15', 'days' => 1, 'manager' => 'Former Manager (not an Administrator now)', 'url' => $wl_edit . 'recSPN00000000003' ),
	array( 'record' => 'recSPN00000000001', 'name' => 'TEST Sponsor', 'tier' => 'Supporter', 'until' => '2026-12-24', 'days' => 40, 'manager' => 'Manager Three', 'url' => $wl_edit . 'recSPN00000000001' ),
);
$wl_find  = 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder';
$wl_dups  = array(
	'full'   => array( 'addresses' => 3, 'ready' => 1, 'read' => 1757000000, 'url' => $wl_find ),
	'one'    => array( 'addresses' => 1, 'ready' => 0, 'read' => 1757000000, 'url' => $wl_find ),
	'no url' => array( 'addresses' => 3, 'ready' => 1, 'read' => 1757000000, 'url' => '' ),
	'clean'  => array( 'addresses' => 0, 'ready' => 0, 'read' => 1757000000, 'url' => $wl_find ),
	'never'  => array(),
);
// Each card in each state that changes its markup, drawn raw, the words for screen readers included.
$wl_draw = static function () use ( $wl_low, $wl_int, $wl_tiers, $wl_dups ) {
	$raw = static function ( $fn ) {
		ob_start();
		$fn();
		return (string) ob_get_clean();
	};
	$out = array(
		'offers full'   => $raw( static function () use ( $wl_low ) { WPCPM_Administrators_Cards::render_offers_low( $wl_low ); } ),
		'offers empty'  => $raw( static function () { WPCPM_Administrators_Cards::render_offers_low( array() ); } ),
		'interests full'  => $raw( static function () use ( $wl_int ) { WPCPM_Administrators_Cards::render_interests( $wl_int ); } ),
		'interests empty' => $raw( static function () { WPCPM_Administrators_Cards::render_interests( array() ); } ),
		'tiers full'    => $raw( static function () use ( $wl_tiers ) { WPCPM_Administrators_Cards::render_tiers_ending( $wl_tiers ); } ),
		'tiers empty'   => $raw( static function () { WPCPM_Administrators_Cards::render_tiers_ending( array() ); } ),
	);
	foreach ( $wl_dups as $state => $facts ) {
		$out[ 'duplicates ' . $state ] = $raw( static function () use ( $facts ) { WPCPM_Administrators_Cards::render_duplicates( $facts ); } );
	}
	return $out;
};
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'wpcpm-sponsors/sponsors' );
WPCPM_UI::leave();
WPCPM_UI::enter( 'administrator' );
$wl_on = $wl_draw();
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );

$wl_all = implode( '', $wl_on );
$wl_x   = static function ( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	return new DOMXPath( $doc );
};
ck( 'on, none of today\'s table, scroll box, subheading, item, fact, note, empty line, foot line or button is printed by the four cards', array(
	strpos( $wl_all, 'wpcpm-admin-table' ),
	strpos( $wl_all, 'wpcpm-administrator__scroll' ),
	strpos( $wl_all, 'wpcpm-administrator__subtitle' ),
	strpos( $wl_all, 'wpcpm-administrator__item' ),
	strpos( $wl_all, 'wpcpm-administrator__kind' ),
	strpos( $wl_all, 'wpcpm-administrator__fact' ),
	strpos( $wl_all, 'wpcpm-administrator__note' ),
	strpos( $wl_all, 'wpcpm-administrator__empty' ),
	strpos( $wl_all, 'wpcpm-administrator__more' ),
	strpos( $wl_all, 'wpcpm-administrator__actions' ),
	strpos( $wl_all, 'wpcpm-button' ),
	strpos( $wl_all, ' wpcpm-offers-low"' ),
	strpos( $wl_all, ' wpcpm-tiers-ending"' ),
	strpos( $wl_all, 'wpcpm-interest"' ),
	strpos( $wl_all, 'href=""' ),
), array( false, false, false, false, false, false, false, false, false, false, false, false, false, false, false ) );

// Offers running low.
$wl_lx    = $wl_x( $wl_on['offers full'] );
$wl_heads = static function ( DOMXPath $x ) {
	return array_map( static function ( $th ) { return $th->getAttribute( 'class' ) . ' ' . trim( $th->textContent ); }, iterator_to_array( $x->query( '//table/thead//th' ) ) );
};
$wl_labels = static function ( DOMXPath $x ) {
	return array_map( static function ( $td ) { return $td->getAttribute( 'data-label' ); }, iterator_to_array( $x->query( '//table/tbody//td' ) ) );
};
ck( 'Offers running low on: one table through the table part, named by the card\'s title as today\'s scroll box was; its columns the offer, the sponsor, then the two numbers', array(
	has( $wl_on['offers full'], '<table class="wpcpm-ui-table wpcpm-ui-table--pairs-3" role="table" aria-labelledby="wpcpm-offers-low-title">' ),
	has( $wl_on['offers full'], 'id="wpcpm-offers-low-title"' ),
	$wl_heads( $wl_lx ),
), array( true, true, array( 'wpcpm-ui-cell--text Offer', 'wpcpm-ui-cell--text Sponsor', 'wpcpm-ui-cell--num Codes left', 'wpcpm-ui-cell--num Warns at' ) ) );
ck( 'each row: the offer as the row\'s header, linked to its sponsor\'s Offers and codes card as today or its title alone with no page; each value under its column\'s name for the phone, the numbers as numbers', array(
	has( $wl_on['offers full'], '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead"><a href="https://site.example/sponsor-dashboard/?wpcpm_view=offers#wpcpm-sponsor-offers">Pro &lt;b&gt;license&lt;/b&gt;</a></th><td class="wpcpm-ui-cell--text" role="cell" data-label="Sponsor">TEST &amp; Sponsor</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Codes left">3</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Warns at">10</td></tr>' ),
	has( $wl_on['offers full'], '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead">Free plan</th><td class="wpcpm-ui-cell--text" role="cell" data-label="Sponsor">Other Co</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Codes left">' . number_format_i18n( 1200 ) . '</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Warns at">' . number_format_i18n( 1500 ) . '</td></tr>' ),
	$wl_labels( $wl_lx ),
), array( true, true, array( 'Sponsor', 'Codes left', 'Warns at', 'Sponsor', 'Codes left', 'Warns at' ) ) );

// Sponsor terms ending.
$wl_tx = $wl_x( $wl_on['tiers full'] );
ck( 'Sponsor terms ending on: one table through the table part, named by the card\'s title as today\'s scroll box was; the sponsor, its tier, the day the term ends as a date, and its Administrator', array(
	has( $wl_on['tiers full'], '<table class="wpcpm-ui-table wpcpm-ui-table--pairs-3" role="table" aria-labelledby="wpcpm-tiers-ending-title">' ),
	has( $wl_on['tiers full'], 'id="wpcpm-tiers-ending-title"' ),
	$wl_heads( $wl_tx ),
	$wl_labels( $wl_tx ),
), array( true, true, array( 'wpcpm-ui-cell--text Sponsor', 'wpcpm-ui-cell--text Tier', 'wpcpm-ui-cell--date Ends on', 'wpcpm-ui-cell--text Administrator' ), array( 'Tier', 'Ends on', 'Administrator', 'Tier', 'Ends on', 'Administrator', 'Tier', 'Ends on', 'Administrator' ) ) );
ck( 'each row: the sponsor as the row\'s header linked to its Edit form as today; the days left in today\'s words after the day, in the cell\'s own ink, a space between them as today, with no tag and no old class, since every row has them (Task 15b); a manager no longer one named in the Edit form\'s words', array(
	has_words( $wl_on['tiers full'], '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead"><a href="' . $wl_edit . 'recSPN00000000001">TEST Sponsor</a></th><td class="wpcpm-ui-cell--text" role="cell" data-label="Tier">Supporter</td><td class="wpcpm-ui-cell--date" role="cell" data-label="Ends on">' . esc_html( WPCPM_Sponsor_Tiers::format_day( '2026-12-24' ) ) . ' in 40 days</td><td class="wpcpm-ui-cell--text" role="cell" data-label="Administrator">Manager Three</td></tr>' ),
	has_words( $wl_on['tiers full'], '<td class="wpcpm-ui-cell--date" role="cell" data-label="Ends on">' . esc_html( WPCPM_Sponsor_Tiers::format_day( '2026-11-14' ) ) . ' today</td>' ),
	has_words( $wl_on['tiers full'], '<td class="wpcpm-ui-cell--date" role="cell" data-label="Ends on">' . esc_html( WPCPM_Sponsor_Tiers::format_day( '2026-11-15' ) ) . ' tomorrow</td>' ),
	array( strpos( $wl_on['tiers full'], 'wpcpm-status' ), strpos( $wl_on['tiers full'], 'wpcpm-administrator__fact' ) ),
	has_words( $wl_on['tiers full'], '<td class="wpcpm-ui-cell--text" role="cell" data-label="Administrator">Former Manager (not an Administrator now)</td>' ),
	has_words( $wl_on['tiers full'], '<a href="' . $wl_edit . 'recSPN00000000002">Bold &amp; Co</a>' ),
), array( true, true, true, array( false, false ), true, true ) );
ck( 'a sponsor with no Administrator keeps today\'s words, "Nobody yet", muted as an empty value', has( $wl_on['tiers full'], '<td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell" data-label="Administrator">Nobody yet</td>' ), true );

// New interests.
ck( 'New interests on: one list of rows, each the sponsor\'s name linked to its Sponsorship card as today (its name alone with no page), how long ago as a fact, and the sponsor\'s own words as the row\'s note, escaped, never trimmed, their line breaks kept; no action, so no action cell (Task 15b)', array(
	has( $wl_on['interests full'], '<ul class="wpcpm-ui-list wpcpm-ui-list--under"><li class="wpcpm-ui-list__row wpcpm-ui-list__row--note"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name"><a href="https://site.example/sponsor-dashboard/?wpcpm_view=sponsorship#wpcpm-sponsor-sponsorship">TEST Sponsor</a></span><span class="wpcpm-ui-list__facts"><span class="wpcpm-ui-item__fact">4 hours ago</span></span></div><p class="wpcpm-ui-list__note">Line one' . "\n" . 'Line &quot;two&quot; &lt;b&gt;bold&lt;/b&gt;</p></li>' ),
	has( $wl_on['interests full'], '<li class="wpcpm-ui-list__row wpcpm-ui-list__row--note"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">Quiet &amp; Co</span><span class="wpcpm-ui-list__facts"><span class="wpcpm-ui-item__fact">4 hours ago</span></span></div><p class="wpcpm-ui-list__note">Hello.</p></li></ul>' ),
	substr_count( $wl_on['interests full'], '<li ' ),
	strpos( $wl_on['interests full'], 'wpcpm-status' ),
), array( true, true, 2, false ) );

// Duplicated students.
$wl_when = esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), 1757000000 ) );
$wl_foot = '<p class="wpcpm-ui-fold__foot"><a href="' . $wl_find . '">Open the Student Duplicate Finder</a></p></div></details>';
ck( 'Duplicated students on: its sentence, in today\'s words, as the parts\' intro line, then the way to the finder as the fold\'s foot, the last thing in the body, and none of today\'s buttons', array(
	has( $wl_on['duplicates full'], '<p class="wpcpm-ui-intro">3 duplicated students in Airtable, 1 ready to delete, as of ' . $wl_when . '.</p>' . $wl_foot ),
	has( $wl_on['duplicates one'], '<p class="wpcpm-ui-intro">1 duplicated student in Airtable, 0 ready to delete, as of ' . $wl_when . '.</p>' . $wl_foot ),
	substr_count( $wl_on['duplicates full'], 'href="' . $wl_find . '"' ),
), array( true, true, 1 ) );
ck( 'a clean scan and a finder that has not scanned say so as the parts\' muted line; with no address for the finder there is no foot, no action after the fold and no link to it', array(
	has( $wl_on['duplicates clean'], '<p class="wpcpm-ui-list__empty">No duplicated students in Airtable, as of ' . $wl_when . '.</p>' . $wl_foot ),
	has( $wl_on['duplicates never'], '<p class="wpcpm-ui-list__empty">The Student Duplicate Finder has not scanned Airtable yet.</p></div></details>' ),
	strpos( $wl_on['duplicates no url'], 'wpcpm-ui-fold__foot' ),
	strpos( $wl_on['duplicates no url'], 'wpcpm-ui-fold__action' ),
	strpos( $wl_on['duplicates no url'] . $wl_on['duplicates never'], 'duplicate-finder' ),
	has( $wl_on['duplicates no url'], '<p class="wpcpm-ui-intro">3 duplicated students in Airtable, 1 ready to delete, as of ' . $wl_when . '.</p></div></details>' ),
), array( true, true, false, false, false, true ) );

// The cards' own lines: the empty sentences and the foot; nothing after the fold (task V1, ruling 2).
$wl_sponsors = static function ( $tab, $title ) {
	return array(
		'<p class="wpcpm-ui-fold__foot"><a href="https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=' . $tab . '">Open on the Sponsors screen<span class="screen-reader-text"> for the ' . $title . ' card</span></a></p></div></details>',
		'</section>',
	);
};
list( $wl_low_foot, $wl_low_act ) = $wl_sponsors( 'offers', 'Offers running low' );
list( $wl_int_foot, $wl_int_act ) = $wl_sponsors( 'interests', 'New interests' );
list( $wl_tie_foot, $wl_tie_act ) = $wl_sponsors( 'sponsors', 'Sponsor terms ending' );
ck( 'each card ends its body with the fold\'s foot to its tab of the Sponsors screen, after the table or the list, and nothing after the fold, full or empty', array(
	has( $wl_on['offers full'], '</table>' . $wl_low_foot . $wl_low_act ),
	has( $wl_on['offers empty'], '<p class="wpcpm-ui-list__empty">No offer is running low.</p>' . $wl_low_foot . $wl_low_act ),
	has( $wl_on['interests full'], '</ul>' . $wl_int_foot . $wl_int_act ),
	has( $wl_on['interests empty'], '<p class="wpcpm-ui-list__empty">No sponsor said anything new in the last thirty days.</p>' . $wl_int_foot . $wl_int_act ),
	has( $wl_on['tiers full'], '</table>' . $wl_tie_foot . $wl_tie_act ),
	has( $wl_on['tiers empty'], '<p class="wpcpm-ui-list__empty">No sponsor&#039;s term ends in the next sixty days.</p>' . $wl_tie_foot . $wl_tie_act ),
	has( $wl_on['duplicates full'], $wl_foot . '</section>' ),
	array_map( static function ( $html ) { return substr_count( $html, 'wpcpm-ui-fold__action' ); }, $wl_on ),
), array( true, true, true, true, true, true, true, array(
	'offers full'       => 0,
	'offers empty'      => 0,
	'interests full'    => 0,
	'interests empty'   => 0,
	'tiers full'        => 0,
	'tiers empty'       => 0,
	'duplicates full'   => 0,
	'duplicates one'    => 0,
	'duplicates no url' => 0,
	'duplicates clean'  => 0,
	'duplicates never'  => 0,
) ) );
ck( 'every card stays folded whatever it holds, in the parts\' card fold', array_map( static function ( $html ) { return array( has( $html, '<details class="wpcpm-ui-fold">' ), has( $html, '<details class="wpcpm-ui-fold" open' ) ); }, array( $wl_on['offers full'], $wl_on['interests full'], $wl_on['tiers full'], $wl_on['duplicates full'] ) ), array_fill( 0, 4, array( true, false ) ) );
list( $wl_outside, $wl_parts ) = parts_outside_switch( '<div class="wpcpm-dashboard wpcpm-ui-on">' . $wl_all . '</div>' );
list( $wl_bare ) = parts_outside_switch( $wl_all );
ck( 'every part the four cards draw sits inside the switch\'s root, and none is drawn but inside one', array( $wl_parts > 0, $wl_outside, count( $wl_bare ) > 0 ), array( true, array(), true ) );

$GLOBALS['uid']    = 3;
$GLOBALS['manage'] = array( 3 );
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_WATCH;
WPCPM_UI::set_live( array( 'administrator' ) );
$wl_page = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::set_live( array() );
unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] );
$GLOBALS['uid']    = 0;
$GLOBALS['manage'] = array();
$wl_card = static function ( $id ) use ( $wl_page ) {
	$from = strpos( $wl_page, 'id="wpcpm-' . $id . '"' );
	return false === $from ? '' : substr( $wl_page, (int) $from, (int) strpos( $wl_page, '</section>', (int) $from ) - (int) $from );
};
list( $wl_page_outside, $wl_page_parts ) = parts_outside_switch( $wl_page );
ck( 'on the Watch view, the four cards are drawn on the parts, none of today\'s tables, scroll boxes or empty lines left in them, and every part sits inside the switch\'s root', array(
	array_map( static function ( $id ) use ( $wl_card ) { $html = $wl_card( $id ); return array( '' !== $html, has( $html, 'wpcpm-ui-fold__foot' ) || has( $html, 'wpcpm-ui-intro' ) || has( $html, 'wpcpm-ui-list__empty' ), has( $html, 'wpcpm-admin-table' ) || has( $html, 'wpcpm-administrator__scroll' ) || has( $html, 'wpcpm-administrator__empty' ) ); }, array( 'offers-low', 'duplicates', 'interests', 'tiers-ending' ) ),
	$wl_page_parts > 0,
	$wl_page_outside,
), array( array_fill( 0, 4, array( true, true, false ) ), true, array() ) );

echo "\n=== The rest of Watch on the parts, behind the switch (UI shared parts, Task 15b) ===\n";

// With the switch on, Students gone quiet keeps its one form around every row's press, and lists each group of
// students (yours, each Administrator's by name, nobody's) under a heading through the heading part, its table through
// the table part named by the card's title and that heading; each row's "We've already talked" is the quiet row action,
// posting exactly what it posts today. Syncs and health's syncs are one table through the table part named by the
// card's title, each sync's state in today's words as plain words in its cell (a tag is a short word that never
// wraps, and "Running: <a step's name>" is not one), its Open the quiet row action naming its sync for a screen reader
// (Task 16, the first step); "Locked accounts" is the
// heading part over the names as the parts' intro line, or today's empty sentence as the parts' muted line.
$wq_T    = $qT;
$wq_snap = $q_snapshot;
$wq_said = array( 'at' => $qT, 'rows' => array( $q_row( 901, 'Ann Student', 40, 3 ) ) );
$wh_sync = static function ( $label, $running, $what, $error, $last, $next, $screen ) {
	return array( 'label' => $label, 'progress' => array( 'running' => $running, 'phase' => $running ? 'reading' : 'done', 'label' => $what, 'error' => $error, 'elapsed' => 0 ), 'last' => $last, 'next' => $next, 'screen' => $screen );
};
$wh_syncs = array(
	'students'     => $wh_sync( 'Students', true, 'Reading <page> 2', '', 1756990000, 1757000000, 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=sync' ),
	'mentors'      => $wh_sync( 'Mentors', false, 'Done', 'HTTP 429 from Airtable <b>x</b>', 0, 0, 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=sync' ),
	'institutions' => $wh_sync( 'Institutions', false, 'Done', '', 1756980000, 0, '' ),
);
$wh_full   = array_merge( $data['health'], array( 'syncs' => $wh_syncs ) );
$wh_calm   = array_merge( $wh_full, array( 'mail' => array( 'ready' => true, 'latest' => array(), 'failed' => 0 ), 'invites' => array( 'run' => array(), 'queued' => 0 ) ) );
$wh_unread = array_merge( $wh_full, array( 'mail' => array( 'ready' => false, 'latest' => array(), 'failed' => 0 ), 'auto_sync' => false ) );
$wh_none   = array_merge( $wh_full, array( 'syncs' => array() ) );
$wh_locked = array( new WP_User( 21, 'Rep One', 'administrator@example.test' ), new WP_User( 22, 'Rep <b>Two</b>', 'two@example.test' ) );
// Each card in each state that changes its markup, drawn raw, the words for screen readers included.
$wr_draw = static function () use ( $wq_snap, $wq_said, $wh_full, $wh_calm, $wh_unread, $wh_none, $wh_locked, $wq_T, $q_row ) {
	$raw = static function ( $fn ) {
		ob_start();
		$fn();
		return (string) ob_get_clean();
	};
	$quiet = static function ( $snap ) use ( $raw ) {
		return $raw( static function () use ( $snap ) { WPCPM_Administrators_Cards::render_quiet_students( $snap ); } );
	};
	$out                         = array();
	$GLOBALS['flags_mode']       = 'preview';
	$out['quiet preview']        = $quiet( $wq_snap );
	$out['quiet empty']          = $quiet( array( 'at' => $wq_T, 'rows' => array() ) );
	$out['quiet never']          = $quiet( array( 'at' => 0, 'rows' => array() ) );
	$GLOBALS['flags_mode']       = 'on';
	$GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] = $wq_T - 10 * DAY_IN_SECONDS;
	$out['quiet on']             = $quiet( $wq_snap );
	unset( $GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] );
	$GLOBALS['flags_mode']       = 'preview';
	$GLOBALS['flash']['student-talked'] = array( 'student' => 902, 'name' => 'Ben Student', 'at' => time() );
	$out['quiet said']           = $quiet( $wq_said );
	unset( $GLOBALS['flash']['student-talked'] );
	WPCPM_Dashboard_Card::forget();
	$GLOBALS['flags_mode']       = 'off';
	$out['quiet off']            = $quiet( $wq_snap );
	$out['health full']          = $raw( static function () use ( $wh_full, $wh_locked ) { WPCPM_Administrators_Cards::render_health( $wh_full, $wh_locked ); } );
	$out['health calm']          = $raw( static function () use ( $wh_calm ) { WPCPM_Administrators_Cards::render_health( $wh_calm, array() ); } );
	$out['health unread']        = $raw( static function () use ( $wh_unread, $wh_locked ) { WPCPM_Administrators_Cards::render_health( $wh_unread, array( $wh_locked[0] ) ); } );
	$out['health no syncs']      = $raw( static function () use ( $wh_none ) { WPCPM_Administrators_Cards::render_health( $wh_none, array() ); } );
	$out['health empty']         = $raw( static function () { WPCPM_Administrators_Cards::render_health( array(), array() ); } );
	unset( $GLOBALS['flags_mode'] );
	return $out;
};
$GLOBALS['uid']          = 3;
$GLOBALS['student_page'] = 'https://example.test/student-report-card/';
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$wr_on = $wr_draw();
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );

$wr_all = implode( '', $wr_on );
$wr_x   = static function ( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	return new DOMXPath( $doc );
};
ck( 'on, none of today\'s table, scroll box, subheading, group row, quiet line, note, empty line, foot line or button is printed by the two cards', array(
	strpos( $wr_all, 'wpcpm-admin-table' ),
	strpos( $wr_all, 'wpcpm-administrator__scroll' ),
	strpos( $wr_all, 'wpcpm-administrator__subtitle' ),
	strpos( $wr_all, 'wpcpm-quiet__table' ),
	strpos( $wr_all, 'wpcpm-quiet__group' ),
	strpos( $wr_all, 'wpcpm-administrator__quiet' ),
	strpos( $wr_all, 'wpcpm-administrator__note' ),
	strpos( $wr_all, 'wpcpm-administrator__empty' ),
	strpos( $wr_all, 'wpcpm-administrator__more' ),
	strpos( $wr_all, 'wpcpm-button' ),
	strpos( $wr_all, 'wpcpm-health__sync' ),
	strpos( $wr_all, 'href=""' ),
	$wr_on['quiet off'],
), array( false, false, false, false, false, false, false, false, false, false, false, false, '' ) );

// Students gone quiet.
$wq_on  = $wr_on['quiet preview'];
$wq_qx  = $wr_x( $wq_on );
$wq_day = static function ( $days ) use ( $wq_T ) { return gmdate( 'j F Y', $wq_T - $days * DAY_IN_SECONDS ); };
// The form carries no class on the parts: its one rule only set a form's own margin, and no script reads it (Task 17b,
// ruling 5); what it posts is unchanged.
ck( 'Students gone quiet on: today\'s sentence as the parts\' muted line, then the one form, as today but with no class of its own, around every group', array(
	has( $wq_on, '<div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">Students on a track who have not signed in, visited, had a call or group session, or saved or edited their report. Pressing We&#039;ve already talked records that somebody has been in touch.</p><form method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Saving">' ),
	substr_count( $wq_on, '<form' ),
	has( $wq_on, '</table></form></div></details></section>' ),
), array( true, 1, true ) );
ck( 'each group is a heading through the heading part, in today\'s order and words, with an id of its own, and its table through the table part named by the card\'s title and that heading (the table part replaces today\'s scroll box)', array(
	array_map( static function ( $h ) { return $h->getAttribute( 'id' ) . ' ' . $h->getAttribute( 'class' ) . ' ' . $h->textContent; }, iterator_to_array( $wq_qx->query( '//form/h4' ) ) ),
	array_map( static function ( $t ) { return $t->getAttribute( 'class' ) . ' ' . $t->getAttribute( 'aria-labelledby' ); }, iterator_to_array( $wq_qx->query( '//form/table' ) ) ),
	array_map( static function ( $n ) { return $n->nodeName; }, iterator_to_array( $wq_qx->query( '//form/*[not(self::input)]' ) ) ),
), array(
	array( 'wpcpm-quiet-students-group-1 wpcpm-ui-h Your students', 'wpcpm-quiet-students-group-2 wpcpm-ui-h Adam Manager', 'wpcpm-quiet-students-group-3 wpcpm-ui-h Zoe Manager', 'wpcpm-quiet-students-group-4 wpcpm-ui-h No Administrator assigned' ),
	// The last group's table ends the card from inside the form, so it says so (Task 17); the others print as before.
	// Not wide: a main area of 800px holds it, and under 800px every table stacks (Task 18f, fix round 2).
	array_map( static function ( $n ) { return 'wpcpm-ui-table' . ( 4 === $n ? ' wpcpm-ui-table--end' : '' ) . ' wpcpm-ui-table--wide wpcpm-ui-table--pairs-4 wpcpm-quiet-students-title wpcpm-quiet-students-group-' . $n; }, array( 1, 2, 3, 4 ) ),
	array( 'h4', 'table', 'h4', 'table', 'h4', 'table', 'h4', 'table' ),
) );
// A viewer who manages none of them gets no "Your students" group, read off the parts (Task 18c: the old card's check
// left in Task 18b): each Administrator by display name, then nobody's.
$GLOBALS['uid']        = 9;
$GLOBALS['flags_mode'] = 'preview';
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$wq_other = capture( static function () use ( $wq_snap ) { WPCPM_Administrators_Cards::render_quiet_students( $wq_snap ); } );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
unset( $GLOBALS['flags_mode'] );
$GLOBALS['uid'] = 3;
ck( 'a viewer who manages none of them gets no "Your students" group: each Administrator by display name, then nobody\'s', array_map( static function ( $h ) { return $h->textContent; }, iterator_to_array( $wr_x( $wq_other )->query( '//form/h4' ) ) ), array( 'Adam Manager', 'Manager Three', 'Zoe Manager', 'No Administrator assigned' ) );
ck( 'each table\'s columns: the student, the days quiet as a number, when last seen, the mentor, the reminder, then the action for screen readers only; every value under its column\'s name for the phone', array(
	array_map( static function ( $th ) { return $th->getAttribute( 'class' ) . ' ' . trim( $th->textContent ); }, iterator_to_array( $wq_qx->query( '(//table)[1]/thead//th' ) ) ),
	array_map( static function ( $td ) { return $td->getAttribute( 'data-label' ); }, iterator_to_array( $wq_qx->query( '(//table)[1]/tbody//td' ) ) ),
), array(
	array( 'wpcpm-ui-cell--text Student', 'wpcpm-ui-cell--num Days quiet', 'wpcpm-ui-cell--text Last seen', 'wpcpm-ui-cell--text Mentor', 'wpcpm-ui-cell--text Reminder', 'wpcpm-ui-cell--action Action' ),
	array( 'Days quiet', 'Last seen', 'Mentor', 'Reminder', '', 'Days quiet', 'Last seen', 'Mentor', 'Reminder', '' ),
) );
$wq_btn = static function ( $id, $name ) {
	return '<button type="submit" class="wpcpm-btn wpcpm-btn--quiet" name="student" value="' . $id . '" formaction="https://example.test/wp-admin/admin-post.php?_wpnonce=nonce-wpcpm_student_talked_' . $id . '">We&#039;ve already talked<span class="screen-reader-text"> for ' . $name . '</span></button>';
};
ck( 'each row: the student as its header, linked to the Student Report Card as today, a test one marked as today; the days; the last activity; the mentor, or "No mentor" muted as an empty value; the reminder in today\'s words; and "We\'ve already talked" as the quiet row action naming its student for a screen reader', array(
	has_words( $wq_on, '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead"><a href="https://example.test/student-report-card/?wpcpm_student_view=901">Ann Student</a></th><td class="wpcpm-ui-cell--num" role="cell" data-label="Days quiet">40</td><td class="wpcpm-ui-cell--text" role="cell" data-label="Last seen">Signed in, ' . $wq_day( 40 ) . '</td><td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell" data-label="Mentor">No mentor</td><td class="wpcpm-ui-cell--text" role="cell" data-label="Reminder">Would remind</td><td class="wpcpm-ui-cell--action" role="cell">' . $wq_btn( 901, 'Ann Student' ) . '</td></tr>' ),
	has( $wq_on, '<a href="https://example.test/student-report-card/?wpcpm_student_view=905">TEST Tess</a>' . WPCPM_Test_Institutions::badge( WPCPM_Test_Institutions::MARK_DASHBOARD ) . '</th>' ),
	has( $wq_on, '<td class="wpcpm-ui-cell--text" role="cell" data-label="Mentor">Mia Mentor</td><td class="wpcpm-ui-cell--text" role="cell" data-label="Reminder">Reminded ' . str_replace( ' ', "\u{00A0}", $wq_day( 5 ) ) . ', no reply</td>' ),
	has( $wq_on, '<td class="wpcpm-ui-cell--text" role="cell" data-label="Last seen">Track started, ' . str_replace( ' ', "\u{00A0}", $wq_day( 15 ) ) . '</td>' ),
	has( $wr_on['quiet on'], '<td class="wpcpm-ui-cell--text" role="cell" data-label="Reminder">Not mailed: quiet before the switch went On</td>' ),
	has( $wr_on['quiet on'], '<td class="wpcpm-ui-cell--text wpcpm-ui-cell--blank" role="cell" data-label="Reminder"></td>' ),
	substr_count( $wq_on, '<tr role="row">' ),
), array( true, true, true, true, true, true, 9 ) );

// What the card posts, on and off: the one form's method, address and hidden fields in order, then every press, its
// name, its value and the address that carries its student's nonce, in order.
$wq_posts = static function ( $html ) use ( $wr_x ) {
	$x   = $wr_x( $html );
	$out = array();
	foreach ( $x->query( '//form' ) as $form ) {
		$hidden = array();
		foreach ( $x->query( './/input[@type="hidden"]', $form ) as $in ) {
			$hidden[] = $in->getAttribute( 'name' ) . '=' . $in->getAttribute( 'value' );
		}
		$press = array();
		foreach ( $x->query( './/button[@type="submit"]', $form ) as $b ) {
			$press[] = array( $b->getAttribute( 'name' ), $b->getAttribute( 'value' ), $b->getAttribute( 'formaction' ), $b->getAttribute( 'data-wpcpm-confirm' ), trim( preg_replace( '/\s+/', ' ', $b->textContent ) ) );
		}
		$out[] = array( $form->getAttribute( 'method' ), $form->getAttribute( 'action' ), $form->getAttribute( 'data-wpcpm-busy' ), $form->hasAttribute( 'data-wpcpm-once' ), $hidden, $press );
	}
	return $out;
};
// The old form left with its branch (Task 18b): what the one form and its presses post is pinned, per state, to the
// digest of `$wq_posts` as the old card gave it at 76b5ecff, where this compared the two, with its first press and
// hidden fields written out below. Nothing in it comes from the clock.
ck( 'the card posts exactly what the handler reads, in every state: the one form\'s method, address, busy words and hidden fields, then each press, its student, its nonce in its address, no question, and its words as a screen reader hears them', array(
	md5( var_export( $wq_posts( $wr_on['quiet preview'] ), true ) ) === '8ae9942f4113c7466fde20a1461f0548',
	md5( var_export( $wq_posts( $wr_on['quiet on'] ), true ) ) === '8ae9942f4113c7466fde20a1461f0548',
	md5( var_export( $wq_posts( $wr_on['quiet said'] ), true ) ) === '82842168209be4ab59b117bb16cb5360',
	count( $wq_posts( $wr_on['quiet preview'] )[0][5] ),
	$wq_posts( $wr_on['quiet preview'] )[0][4],
	$wq_posts( $wr_on['quiet preview'] )[0][5][0],
	substr_count( $wq_on, 'name="_wpnonce"' ),
), array( true, true, true, 5, array( 'action=wpcpm_student_talked', 'wpcpm_return=dashboard', 'wpcpm_return_to=quiet-students' ), array( 'student', '901', 'https://example.test/wp-admin/admin-post.php?_wpnonce=nonce-wpcpm_student_talked_901', '', 'We\'ve already talked for Ann Student' ), 0 ) );
ck( 'no student, or no check yet: today\'s sentence as the parts\' muted line, and no form; after a press the card is drawn open, the line that says so first in its body', array(
	has( $wr_on['quiet empty'], '<div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-list__empty">No student has been quiet that long.</p></div></details></section>' ),
	has( $wr_on['quiet never'], '<div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-list__empty">No student has been quiet that long.</p></div></details></section>' ),
	strpos( $wr_on['quiet empty'] . $wr_on['quiet never'], '<form' ),
	has( $wr_on['quiet said'], '<details class="wpcpm-ui-fold" open>' ),
	has( $wr_on['quiet said'], '<div class="wpcpm-ui-fold__body"><p class="wpcpm-dashboard__message wpcpm-dashboard__message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>Noted. Ben Student is off the list until they go quiet again.</p><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">' ),
), array( true, true, false, true, true ) );

// Syncs and health.
$wh_on = $wr_on['health full'];
$wh_hx = $wr_x( $wh_on );
$wh_open = static function ( $href, $name ) { return '<a class="wpcpm-btn wpcpm-btn--quiet" href="' . $href . '">Open<span class="screen-reader-text"> ' . $name . '</span></a>'; };
ck( 'Syncs and health on: the syncs are one table through the table part, named by the card\'s title as today\'s scroll box was; the sync, its state, its last and next runs as dates, its last error, then the action for screen readers only', array(
	has( $wh_on, '<div class="wpcpm-ui-fold__body"><table class="wpcpm-ui-table wpcpm-ui-table--wide wpcpm-ui-table--pairs-4" role="table" aria-labelledby="wpcpm-health-title">' ),
	array_map( static function ( $th ) { return $th->getAttribute( 'class' ) . ' ' . trim( $th->textContent ); }, iterator_to_array( $wh_hx->query( '//table/thead//th' ) ) ),
	array_map( static function ( $td ) { return $td->getAttribute( 'data-label' ); }, iterator_to_array( $wh_hx->query( '//table/tbody/tr[1]/td' ) ) ),
), array( true, array( 'wpcpm-ui-cell--text Sync', 'wpcpm-ui-cell--text State', 'wpcpm-ui-cell--date Last run', 'wpcpm-ui-cell--date Next run', 'wpcpm-ui-cell--token Last error', 'wpcpm-ui-cell--action Action' ), array( 'State', 'Last run', 'Next run', 'Last error', '' ) ) );
$wh_when = static function ( $t ) { return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $t ) ); };
ck( 'each row: the sync as its header; its state in today\'s words, plain in the cell\'s own ink, no tag and no old class (Task 16, the first step: a tag never wraps, and a step\'s name is not a short word); a run never made or none booked in today\'s words, muted as empty values; its error escaped; and Open, the quiet row action to its screen naming its sync for a screen reader, or nothing where it has no screen', array(
	has_words( $wh_on, '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead">Students</th><td class="wpcpm-ui-cell--text" role="cell" data-label="State">Running: Reading &lt;page&gt; 2</td><td class="wpcpm-ui-cell--date" role="cell" data-label="Last run">' . $wh_when( 1756990000 ) . '</td><td class="wpcpm-ui-cell--date" role="cell" data-label="Next run">' . $wh_when( 1757000000 ) . '</td><td class="wpcpm-ui-cell--token wpcpm-ui-cell--blank" role="cell" data-label="Last error"></td><td class="wpcpm-ui-cell--action" role="cell">' . $wh_open( 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=sync', 'Students' ) . '</td></tr>' ),
	has_words( $wh_on, '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead">Mentors</th><td class="wpcpm-ui-cell--text" role="cell" data-label="State">Idle</td><td class="wpcpm-ui-cell--date wpcpm-ui-cell--empty" role="cell" data-label="Last run">Never</td><td class="wpcpm-ui-cell--date wpcpm-ui-cell--empty" role="cell" data-label="Next run">Not scheduled</td><td class="wpcpm-ui-cell--token" role="cell" data-label="Last error">HTTP 429 from Airtable &lt;b&gt;x&lt;/b&gt;</td><td class="wpcpm-ui-cell--action" role="cell">' . $wh_open( 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=sync', 'Mentors' ) . '</td></tr>' ),
	has_words( $wh_on, '<td class="wpcpm-ui-cell--token wpcpm-ui-cell--blank" role="cell" data-label="Last error"></td><td class="wpcpm-ui-cell--action wpcpm-ui-cell--blank" role="cell"></td></tr></tbody></table>' ),
	substr_count( $wh_on, '<tr role="row">' ),
	strpos( implode( '', array( $wr_on['health full'], $wr_on['health calm'], $wr_on['health unread'] ) ), 'wpcpm-status' ),
), array( true, true, true, 4, false ) );
// Three syncs, each with its screen: three Open actions, each heard with its own sync's name, as the row prints it.
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$wh_three = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_health( $data['health'], array() ); } );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
$wh_names = array_map(
	static function ( $a ) { return trim( preg_replace( '/\s+/', ' ', $a->textContent ) ); },
	iterator_to_array( $wr_x( $wh_three )->query( '//td[contains(@class,"wpcpm-ui-cell--action")]/a' ) )
);
ck( 'three syncs, three Open actions, and three different accessible names, each "Open" and the sync\'s own name as its row\'s header prints it, the name for screen readers only', array(
	$wh_names,
	count( array_unique( $wh_names ) ),
	substr_count( $wh_three, 'Open<span class="screen-reader-text"> ' ),
), array( array( 'Open Students', 'Open Mentors', 'Open Institutions' ), 3, 3 ) );
// Where each leads, read off the parts (Task 18c: the old rows' checks left in Task 18b): the students' and mentors'
// syncs to their screens' Sync tab, the institutions' to its Sync and storage tab, never the queue its screen opens on.
ck( 'each Open leads to its sync\'s own tab: Students and Mentors at their Sync tab, Institutions at the Sync and storage tab', array_map(
	static function ( $a ) { return $a->getAttribute( 'href' ); },
	iterator_to_array( $wr_x( $wh_three )->query( '//td[contains(@class,"wpcpm-ui-cell--action")]/a' ) )
), array(
	'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=sync',
	'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=sync',
	'https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=sync',
) );
// Task 18d, point 10: the card's own facts (storage, email, invitations) follow the syncs table, before the heading,
// whether or not an account is locked; under "Locked accounts" today's sentence is the muted intro, then the accounts
// as a plain list of rows (the people rows' list, facts under the name), one a line, every word as it was.
$wh_row = static function ( $name ) { return '<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">' . $name . '</span><span class="wpcpm-ui-list__facts"></span></div></li>'; };
ck( '"Locked accounts" is the heading part, today\'s words, no id today and none added, after the card\'s storage, email and invitation lines; under it today\'s sentence as the parts\' muted intro, then the accounts as a list, one a row, escaped, closing the body; none locked, today\'s sentence as the parts\' muted line', array(
	has( $wh_on, '</table><p class="wpcpm-ui-intro">Private storage: ' ),
	has( $wh_on, '</p><h4 class="wpcpm-ui-h">Locked accounts</h4><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">Each was refused more claims than the daily ceiling allows; the lock lifts by itself tomorrow.</p><ul class="wpcpm-ui-list wpcpm-ui-list--under">' . $wh_row( 'Rep One (repone)' ) . $wh_row( 'Rep &lt;b&gt;Two&lt;/b&gt; (rep&lt;b&gt;two&lt;/b&gt;)' ) . '</ul></div></details></section>' ),
	has( $wr_on['health calm'], '</table><p class="wpcpm-ui-intro">Private storage: ' ),
	has( $wr_on['health calm'], '</p><h4 class="wpcpm-ui-h">Locked accounts</h4><p class="wpcpm-ui-list__empty">No account is locked today.</p></div></details></section>' ),
	has( $wr_on['health unread'], '<h4 class="wpcpm-ui-h">Locked accounts</h4><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">Each was refused more claims than the daily ceiling allows; the lock lifts by itself tomorrow.</p><ul class="wpcpm-ui-list wpcpm-ui-list--under">' . $wh_row( 'Rep One (repone)' ) . '</ul>' ),
	has( $wh_on . $wr_on['health unread'], ') Each was refused' ),
), array( true, true, true, true, true, false ) );
// The storage, mail and invitations lines are sentences, each with its link after its words: on the parts one intro
// line per sentence (Task 17b, ruling 2), each holding exactly what today's list item held, in today's order, right
// before the "Locked accounts" heading (Task 18d); no bullets, no list.
$wh_lines = static function ( $html ) { return preg_match( '#(?:<p class="wpcpm-ui-intro">(?:(?!</p>).)*</p>)+(?=<h4 class="wpcpm-ui-h">Locked accounts</h4>)#s', $html, $m ) && preg_match_all( '#<p class="wpcpm-ui-intro">((?:(?!</p>).)*)</p>#s', $m[0], $l ) ? $l[1] : null; };
// The old list left with its branch (Task 18b); each line is still `health_lines()`'s, which drew the list items.
ck( 'the storage, email and invitation lines are one intro line each, holding today\'s list item\'s words and link, in today\'s order, in every state, right before the Locked accounts heading; no old list is left on the parts', array_map( static function ( $state ) use ( $wr_on, $wh_lines ) {
	$on = (array) $wh_lines( $wr_on[ $state ] );
	return array( count( $on ) > 1, strpos( $wr_on[ $state ], 'wpcpm-administrator__list' ) );
}, array( 'health full', 'health calm', 'health unread', 'health no syncs', 'health empty' ) ), array_fill( 0, 5, array( true, false ) ) );
// The email lines' words and links, read off the parts in every state the old list's checks covered (Task 18c: they
// left with the old list in Task 18b though the parts print the same words): the last day's failures linked to the
// Log's failed emails; nothing sent yet; the log not ready yet, never "no email has been sent"; and a log that is
// ready but empty, as the card's own data says it, drawn from `health()` with the table there and with it gone.
$wh_log    = '<a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log">Open the email log</a>';
$wh_failed = '1 email failed in the last day. <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log&status=failed">Show the failed emails</a>';
$wh_data   = static function ( $mail ) use ( $health_ready_data ) {
	$health         = $health_ready_data;
	$health['mail'] = array_merge( $health['mail'], $mail );
	WPCPM_UI::set_live( array( 'administrator' ) );
	WPCPM_UI::enter( 'administrator' );
	$html = capture( static function () use ( $health ) { WPCPM_Administrators_Cards::render_health( $health, array() ); } );
	WPCPM_UI::leave();
	WPCPM_UI::set_live( array() );
	return $html;
};
$wh_missing = $wh_data( $health_missing_data['mail'] );
$wh_ready   = $wh_data( array( 'latest' => array(), 'failed' => 0 ) );
$wh_mail    = static function ( $html ) use ( $wh_lines ) {
	return array_values( array_filter( (array) $wh_lines( $html ), static function ( $line ) { return false !== strpos( $line, 'email' ); } ) );
};
ck( 'the email lines on the parts: the last day\'s failures counted, linked to the Log\'s failed emails, after the latest email; with nothing sent yet that sentence, linked to the Log, and no failure counted; with no table the log is not ready yet, never "no email has been sent"; a log ready but empty says no email has been sent', array(
	array_slice( $wh_mail( $wr_on['health full'] ), 1 ),
	$wh_mail( $wr_on['health calm'] ),
	$wh_mail( $wr_on['health unread'] ),
	$wh_mail( $wr_on['health empty'] ),
	array( $health_missing_data['mail']['ready'], $wh_mail( $wh_missing ) ),
	array( $health_ready_data['mail']['ready'], $wh_mail( $wh_ready ) ),
	strpos( $wr_on['health calm'] . $wr_on['health unread'] . $wr_on['health empty'] . $wh_missing . $wh_ready, 'failed in the last day' ),
	strpos( $wr_on['health unread'] . $wh_missing, 'No email has been sent yet.' ),
), array(
	array( $wh_failed ),
	array( 'No email has been sent yet. ' . $wh_log ),
	array( 'The email log is not ready yet. ' . $wh_log ),
	array( 'No email has been sent yet. ' . $wh_log ),
	array( false, array( 'The email log is not ready yet. ' . $wh_log ) ),
	array( true, array( 'No email has been sent yet. ' . $wh_log ) ),
	false,
	false,
) );
ck( 'and the first email line names the latest email, its address masked, and its status in a sentence of its own, linked to the Log', 0 === strpos( (string) ( $wh_mail( $wr_on['health full'] )[0] ?? '' ), 'Last email: Semester report drafted, to Administrators, sent to m***@example.test on ' ) && '. Status: Handed to the mail server. ' . $wh_log === substr( (string) ( $wh_mail( $wr_on['health full'] )[0] ?? '' ), -strlen( '. Status: Handed to the mail server. ' . $wh_log ) ), true );
ck( 'with no sync to speak of the card draws no table, where today drew one with no row; the lines open the body and the heading follows them, as with syncs', array(
	strpos( $wr_on['health no syncs'] . $wr_on['health empty'], '<table' ),
	has( $wr_on['health no syncs'], '<div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro">Private storage: ' ),
	has( $wr_on['health empty'], '<div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro">Private storage: ' ),
	has( $wr_on['health empty'], '</p><h4 class="wpcpm-ui-h">Locked accounts</h4><p class="wpcpm-ui-list__empty">No account is locked today.</p></div></details></section>' ),
), array( false, true, true, true ) );
ck( 'the card has no action after the fold, as today: no one screen holds the three syncs', array_map( static function ( $state ) use ( $wr_on ) { return substr_count( $wr_on[ $state ], 'wpcpm-ui-fold__action' ) + substr_count( $wr_on[ $state ], 'wpcpm-ui-fold__foot' ); }, array( 'health full', 'health calm', 'health empty' ) ), array( 0, 0, 0 ) );
list( $wr_outside, $wr_parts ) = parts_outside_switch( '<div class="wpcpm-dashboard wpcpm-ui-on">' . $wr_all . '</div>' );
list( $wr_bare ) = parts_outside_switch( $wr_all );
ck( 'every part the two cards draw sits inside the switch\'s root, and none is drawn but inside one', array( $wr_parts > 0, $wr_outside, count( $wr_bare ) > 0 ), array( true, array(), true ) );
$wr_ids = array();
foreach ( $wr_x( $wr_on['quiet preview'] . $wr_on['health full'] )->query( '//*[@id]' ) as $node ) {
	$wr_ids[] = $node->getAttribute( 'id' );
}
ck( 'no id repeats on the two cards, and every table\'s name resolves to a heading drawn', array(
	count( $wr_ids ) === count( array_unique( $wr_ids ) ),
	array_values( array_diff( preg_split( '/\s+/', implode( ' ', array_map( static function ( $t ) { return $t->getAttribute( 'aria-labelledby' ); }, iterator_to_array( $wr_x( $wr_on['quiet preview'] . $wr_on['health full'] )->query( '//table' ) ) ) ) ), $wr_ids ) ),
), array( true, array() ) );

// On the Watch view itself: both cards on the parts, every part inside the switch's root.
$GLOBALS['uid']        = 3;
$GLOBALS['manage']     = array( 3 );
$GLOBALS['flags_mode'] = 'preview';
$GLOBALS['opts'][ WPCPM_Student_Flags::OPT_SNAPSHOT ] = $q_snapshot;
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_WATCH;
WPCPM_UI::set_live( array( 'administrator' ) );
$wr_page = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::set_live( array() );
unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ], $GLOBALS['flags_mode'], $GLOBALS['opts'][ WPCPM_Student_Flags::OPT_SNAPSHOT ] );
$GLOBALS['uid']    = 0;
$GLOBALS['manage'] = array();
$wr_card = static function ( $id ) use ( $wr_page ) {
	$from = strpos( $wr_page, 'id="wpcpm-' . $id . '"' );
	return false === $from ? '' : substr( $wr_page, (int) $from, (int) strpos( $wr_page, '</section>', (int) $from ) - (int) $from );
};
list( $wr_page_outside, $wr_page_parts ) = parts_outside_switch( $wr_page );
ck( 'on the Watch view, both cards are drawn on the parts, none of today\'s tables, scroll boxes or subheadings left in them, and every part sits inside the switch\'s root', array(
	array_map( static function ( $id ) use ( $wr_card ) { $html = $wr_card( $id ); return array( '' !== $html, has( $html, 'wpcpm-ui-table' ), has( $html, 'wpcpm-admin-table' ) || has( $html, 'wpcpm-administrator__scroll' ) || has( $html, 'wpcpm-administrator__subtitle' ) ); }, array( 'health', 'quiet-students' ) ),
	$wr_page_parts > 0,
	$wr_page_outside,
), array( array_fill( 0, 2, array( true, true, false ) ), true, array() ) );

echo "\n=== The program's cards on the parts, behind the switch (UI shared parts, Task 16) ===\n";

// With the switch on, the Sponsors card and the Mentor engagement card keep today's tiles, the one markup the parts
// have no part for (a figure with its label), byte for byte, and put the rest on the parts: the Sponsors card's line
// over the tier tiles as the parts' intro line, Mentor engagement's quiet line as the parts' muted line, each foot
// through render_screen_line(). Programs running holds a second line in a number cell (the status breakdown under
// "In progress"), which no part draws: it is left on today's markup for the controller's ruling.
$tp_me    = $me_summary;
$tp_draw  = static function () use ( $data, $tp_me ) {
	$raw = static function ( $fn ) {
		ob_start();
		$fn();
		return (string) ob_get_clean();
	};
	$no_tiers = $data['sponsors'];
	unset( $no_tiers['tiers'] );
	$out                       = array();
	$out['sponsors full']      = $raw( static function () use ( $data ) { WPCPM_Administrators_Cards::render_sponsors_strip( $data['sponsors'] ); } );
	$out['sponsors no tiers']  = $raw( static function () use ( $no_tiers ) { WPCPM_Administrators_Cards::render_sponsors_strip( $no_tiers ); } );
	$out['sponsors empty']     = $raw( static function () { WPCPM_Administrators_Cards::render_sponsors_strip( array() ); } );
	$out['programs full']      = $raw( static function () use ( $data ) { WPCPM_Administrators_Cards::render_programs( $data['programs'] ); } );
	$out['programs empty']     = $raw( static function () { WPCPM_Administrators_Cards::render_programs( array( 'tracks' => array(), 'finished' => 0, 'rows' => array(), 'quiet' => 0, 'read' => 0, 'semester' => '' ) ); } );
	$out['engagement full']    = $raw( static function () use ( $tp_me ) { WPCPM_Administrators_Cards::render_mentor_engagement( $tp_me ); } );
	$out['engagement zero']    = $raw( static function () use ( $tp_me ) { WPCPM_Administrators_Cards::render_mentor_engagement( array_fill_keys( array_keys( $tp_me ), 0 ) ); } );
	$out['engagement none']    = $raw( static function () { WPCPM_Administrators_Cards::render_mentor_engagement( array() ); } );
	return $out;
};
$tp_off = $tp_draw();
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'wpcpm-sponsors/sponsors' );
WPCPM_UI::leave();
WPCPM_UI::enter( 'administrator' );
$tp_on = $tp_draw();
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );

// The digests are of the three cards as they were drawn at 29b5424d, before Task 16: these same fixtures run on that
// commit's code, before any renderer was changed. The roster's read time is fixed and "ago" is the suite's own fixed
// words; the one value that moves with the day the suite runs is the first day of the current semester the Sponsors
// card names ("since 1 July 2026", `WPCPM_Cohort::current()`), blanked to "since SINCE" before the digest.
$tp_tiles = static function ( $html ) { return preg_match_all( '#<ul class="wpcpm-programs__tiles">.*?</ul>#s', $html, $m ) ? $m[0] : array(); };
ck( 'on, every tile is today\'s, byte for byte, in every state: the parts have no tile, so the card keeps its own', array_map( static function ( $state ) use ( $tp_on, $tp_off, $tp_tiles ) { return array( count( $tp_tiles( $tp_on[ $state ] ) ), $tp_tiles( $tp_on[ $state ] ) === $tp_tiles( $tp_off[ $state ] ) ); }, array( 'sponsors full', 'sponsors no tiers', 'sponsors empty', 'engagement full', 'engagement zero' ) ), array( array( 2, true ), array( 1, true ), array( 1, true ), array( 1, true ), array( 1, true ) ) );
$tp_sponsors_foot = '<p class="wpcpm-ui-fold__foot"><a href="https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=sponsors">Open on the Sponsors screen<span class="screen-reader-text"> for the Sponsors card</span></a></p></div></details>';
ck( 'Sponsors on: the line over the tier tiles is the parts\' intro line, in today\'s words, between the two runs of tiles; the body ends with the fold\'s foot, and nothing follows the fold; without the ladder neither the line nor its tiles', array(
	has( $tp_on['sponsors full'], '</ul><p class="wpcpm-ui-intro">Approved sponsors by tier, as the tiers stand today.</p><ul class="wpcpm-programs__tiles">' ),
	has( $tp_on['sponsors full'], '</ul>' . $tp_sponsors_foot . '</section>' ),
	strpos( $tp_on['sponsors no tiers'] . $tp_on['sponsors empty'], 'wpcpm-ui-intro' ),
	has( $tp_on['sponsors empty'], '</ul>' . $tp_sponsors_foot ),
), array( true, true, false, true ) );
$tp_engagement_foot = '<p class="wpcpm-ui-fold__foot"><a href="https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=engagement">Open the Mentors screen&#039;s Engagement tab</a></p></div></details>';
ck( 'Mentor engagement on: the Administrators counted apart are the parts\' muted line, in today\'s words, after the tiles; the body ends with the fold\'s foot, and nothing follows the fold; an empty summary draws nothing, as today', array(
	has( $tp_on['engagement full'], '</ul><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">Administrators who also mentor are counted apart: 1.</p>' . $tp_engagement_foot . '</section>' ),
	has( $tp_on['engagement zero'], '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">Administrators who also mentor are counted apart: 0.</p>' . $tp_engagement_foot ),
	$tp_on['engagement none'],
), array( true, true, '' ) );
ck( 'on, no card prints today\'s note, quiet line, read line, empty line, foot line, table, scroll box or breakdown (Programs running on the parts since fix round 1)', array_map( static function ( $state ) use ( $tp_on ) {
	return array_map( static function ( $needle ) use ( $tp_on, $state ) { return strpos( $tp_on[ $state ], $needle ); }, array( 'wpcpm-administrator__note', 'wpcpm-administrator__quiet', 'wpcpm-administrator__read', 'wpcpm-administrator__empty', 'wpcpm-administrator__more', 'wpcpm-admin-table', 'wpcpm-administrator__scroll', 'wpcpm-programs__table' ) );
}, array( 'sponsors full', 'sponsors empty', 'engagement full', 'programs full', 'programs empty' ) ), array_fill( 0, 5, array_fill( 0, 8, false ) ) );

// Programs running on the parts (fix round 1): the tiles today's; the table through the table part, named by the card's
// title as the scroll box was; the institution the row's header, linked as today; "In progress" a number cell with the
// students by status as its second line; the other numbers number cells; "no end date" muted as an empty value in its
// words; the quiet and read lines today's words as the parts' muted line; the empty line the shared one.
$tp_px    = static function ( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	return new DOMXPath( $doc );
};
$tp_cells = static function ( $html ) use ( $tp_px ) {
	// The parts put one space before a cell's second line so a screen reader hears it apart (Task 17, fix round 1);
	// today's markup has none. The words are compared without it.
	$x   = $tp_px( str_replace( ' <span class="wpcpm-ui-cell__sub">', '<span class="wpcpm-ui-cell__sub">', $html ) );
	$out = array( 'head' => array(), 'rows' => array() );
	foreach ( $x->query( '//table/thead//th' ) as $th ) {
		$out['head'][] = trim( $th->textContent );
	}
	foreach ( $x->query( '//table/tbody/tr' ) as $tr ) {
		$row = array();
		foreach ( $x->query( './th|./td', $tr ) as $c ) {
			$row[] = trim( $c->textContent );
		}
		$out['rows'][] = $row;
	}
	return $out;
};
$tp_varied = $data['programs'];
$tp_varied['rows'][] = array_merge( $data['programs']['rows'][0], array( 'name' => 'Escuela <b>Beta</b>', 'earliest' => '', 'latest' => '', 'by_status' => array(), 'in_progress' => 1200 ) );
$tp_varied['rows'][] = array_merge( $data['programs']['rows'][0], array( 'name' => 'Escuela Gamma', 'earliest' => '2026-12-01', 'latest' => '2027-01-31' ) );
$tp_varied['read']   = 0;
$tp_varied['quiet']  = 2;
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$tp_vn = capture( static function () use ( $tp_varied ) { WPCPM_Administrators_Cards::render_programs( $tp_varied ); } );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
// The old table left with its branch (Task 18b): every cell's words are pinned to the old table's, read at 76b5ecff,
// where this check compared the two, for the fixture and for rows with no end date, a range of dates and no breakdown.
ck( 'Programs running on, cell by cell against today\'s: the same column names in the same order, and every row\'s every cell the same words, the breakdown included, for the fixture and for rows with no end date, a range of dates and no breakdown', array( $tp_cells( $tp_on['programs full'] ), $tp_cells( $tp_vn ) ), array (
	  0 => 
	  array (
	    'head' => 
	    array (
	      0 => 'Institution',
	      1 => 'In progress',
	      2 => 'Mentors',
	      3 => 'Waiting for a mentor',
	      4 => 'End dates',
	      5 => 'Agreement',
	      6 => 'Report',
	    ),
	    'rows' => 
	    array (
	      0 => 
	      array (
	        0 => 'Uniwersytet Alpha',
	        1 => '31 WordPress Credits Program 150h, 1 Developer Track, 1 WordPress Credits Program 50h',
	        2 => '1',
	        3 => '1',
	        4 => '2026-11-30 to 2026-12-15',
	        5 => 'Accepted',
	        6 => 'Draft',
	      ),
	    ),
	  ),
	  1 => 
	  array (
	    'head' => 
	    array (
	      0 => 'Institution',
	      1 => 'In progress',
	      2 => 'Mentors',
	      3 => 'Waiting for a mentor',
	      4 => 'End dates',
	      5 => 'Agreement',
	      6 => 'Report',
	    ),
	    'rows' => 
	    array (
	      0 => 
	      array (
	        0 => 'Uniwersytet Alpha',
	        1 => '31 WordPress Credits Program 150h, 1 Developer Track, 1 WordPress Credits Program 50h',
	        2 => '1',
	        3 => '1',
	        4 => '2026-11-30 to 2026-12-15',
	        5 => 'Accepted',
	        6 => 'Draft',
	      ),
	      1 => 
	      array (
	        0 => 'Escuela <b>Beta</b>',
	        1 => '1200',
	        2 => '1',
	        3 => '1',
	        4 => 'no end date',
	        5 => 'Accepted',
	        6 => 'Draft',
	      ),
	      2 => 
	      array (
	        0 => 'Escuela Gamma',
	        1 => '31 WordPress Credits Program 150h, 1 Developer Track, 1 WordPress Credits Program 50h',
	        2 => '1',
	        3 => '1',
	        4 => '2026-12-01 to 2027-01-31',
	        5 => 'Accepted',
	        6 => 'Draft',
	      ),
	    ),
	  ),
	) );
$tp_x  = $tp_px( $tp_on['programs full'] );
$tp_r0 = $data['programs']['rows'][0];
// Task V1, ruling 15: In progress is a count read from the left (its number in tabular figures, the breakdown under
// it); the two other counts stay right-aligned numbers.
ck( 'the table is the table part, named by the card\'s title; its columns the institution, In progress as a count, two numbers, the end dates as text whose each date is whole (a range may break at "to"), the agreement and the report as short cells that never wrap (Task 18d)', array(
	// Seven columns: the table says it is wide, so the sheet stacks it in a narrow main area (Task 17, fix round 1), and it
	// needs about 936px of that area: it stacks below that (Task 18d).
	has( $tp_on['programs full'], '<table class="wpcpm-ui-table wpcpm-ui-table--wide wpcpm-ui-table--wide-936 wpcpm-ui-table--pairs-6" role="table" aria-labelledby="wpcpm-programs-title"><colgroup><col style="width:18%"><col style="width:22%"><col><col><col><col><col></colgroup>' ),
	has( $tp_on['programs full'], 'id="wpcpm-programs-title"' ),
	array_map( static function ( $th ) { return $th->getAttribute( 'class' ); }, iterator_to_array( $tp_x->query( '//table/thead//th' ) ) ),
), array( true, true, array( 'wpcpm-ui-cell--text', 'wpcpm-ui-cell--count', 'wpcpm-ui-cell--num', 'wpcpm-ui-cell--num', 'wpcpm-ui-cell--text', 'wpcpm-ui-cell--short', 'wpcpm-ui-cell--short' ) ) );
$tp_bd = array();
foreach ( $tp_r0['by_status'] as $label => $n ) {
	$tp_bd[] = $n . ' ' . $label;
}
ck( 'a row: the institution as its header, linked as today; In progress its number with the students by status as the cell\'s second line; the numbers as numbers, each under its column\'s name', array(
	$tp_x->query( '//table/tbody/tr/th[@scope="row"][@role="rowheader"][@class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead"]' )->length === count( $data['programs']['rows'] ),
	has( $tp_on['programs full'], '<td class="wpcpm-ui-cell--count" role="cell" data-label="In progress">' . $tp_r0['in_progress'] . ' <span class="wpcpm-ui-cell__sub">' . esc_html( implode( ', ', $tp_bd ) ) . '</span></td><td class="wpcpm-ui-cell--num" role="cell" data-label="Mentors">' . $tp_r0['mentors'] . '</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Waiting for a mentor">' . $tp_r0['waiting'] . '</td>' ),
	has( $tp_on['programs full'], 'wpcpm_institution_view=' ),
	$tp_x->query( '//table/tbody/tr/th/a' )->length,
), array( true, true, true, 1 ) );
ck( 'no end date keeps its words, muted as an empty value; a range keeps today\'s words; a row with no breakdown has no second line', array(
	has( $tp_vn, '<td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell" data-label="End dates">no end date</td>' ),
	// Task 18d: each date whole, the range's words between them, so it breaks at "to" and never inside a date.
	has( $tp_vn, '<td class="wpcpm-ui-cell--text" role="cell" data-label="End dates"><span class="wpcpm-ui-whole">2026-12-01</span> to <span class="wpcpm-ui-whole">2027-01-31</span></td>' ),
	has( $tp_vn, '<td class="wpcpm-ui-cell--count" role="cell" data-label="In progress">1200</td>' ),
	has( $tp_vn, 'Escuela &lt;b&gt;Beta&lt;/b&gt;' ),
), array( true, true, true, true ) );
ck( 'under the table, the quiet and read lines in today\'s words as the parts\' muted line; nothing printed with no rows but the shared empty line and those lines', array(
	has( $tp_on['programs full'], '</table><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">1 more institution with no student in progress.</p><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">Read from the program records on ' ),
	has( $tp_vn, '</table><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">2 more institutions with no student in progress.</p><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">These students have not been read from the program records yet.</p></div></details>' ),
	has( $tp_on['programs empty'], '</ul><p class="wpcpm-ui-list__empty">No institution has a student in progress.</p><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">These students have not been read from the program records yet.</p></div></details>' ),
	strpos( $tp_on['programs empty'], '<table' ),
), array( true, true, true, false ) );
list( $tp_outside, $tp_parts ) = parts_outside_switch( '<div class="wpcpm-dashboard wpcpm-ui-on">' . implode( '', $tp_on ) . '</div>' );
list( $tp_bare ) = parts_outside_switch( implode( '', $tp_on ) );
ck( 'every part the three cards draw sits inside the switch\'s root, and none is drawn but inside one', array( $tp_parts > 0, $tp_outside, count( $tp_bare ) > 0 ), array( true, array(), true ) );

// On The program itself: the three cards, then "Tools from our sponsors" (its own suite, bin/test-sponsor-tools.php,
// draws the real section on the parts; here the stand-in proves the call), every part inside the switch's root.
$GLOBALS['uid']    = 3;
$GLOBALS['manage'] = array( 3 );
$GLOBALS['tools']  = array();
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_REFERENCE;
WPCPM_UI::set_live( array( 'administrator' ) );
$tp_page = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::set_live( array() );
unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] );
$GLOBALS['uid']    = 0;
$GLOBALS['manage'] = array();
list( $tp_page_outside, $tp_page_parts ) = parts_outside_switch( $tp_page );
ck( 'on The program, the Sponsors and Mentor engagement cards print the parts\' lines, the Tools section is asked for once, for the Administrators, and every part sits inside the switch\'s root', array(
	has( $tp_page, '<p class="wpcpm-ui-intro">Approved sponsors by tier, as the tiers stand today.</p>' ) || ! isset( $data['sponsors']['tiers'] ),
	has( $tp_page, 'wpcpm-ui-intro--muted' ),
	array_map( static function ( $call ) { return $call[0]; }, $GLOBALS['tools'] ),
	$tp_page_parts > 0,
	$tp_page_outside,
), array( true, true, array( 'managers' ), true, array() ) );
// Task V1, ruling 5: on the parts the Tools section is a group of the view, after The program's numbers: the group
// title part in today's words over today's offers, with no inset or heading of the section's own and no heading for a
// screen reader alone over it (the group's heading is one); off, as before.
ck( 'V1: on The program the Tools section is the view\'s second group, its title the group title part in today\'s words, right after the numbers\' group; no screen-reader heading over it', array(
	has( $tp_page, '</section></div></div><div class="wpcpm-administrator__group wpcpm-administrator__group--tools wpcpm-ui-group"><h2 class="wpcpm-ui-group__title">Tools from our sponsors</h2><section class="wpcpm-tools" id="wpcpm-tools"></section></div>' ),
	has( $tp_page, 'From our sponsors' ),
	has( $out_ref, '<h2 class="screen-reader-text">From our sponsors</h2><section class="wpcpm-student__section wpcpm-tools" id="wpcpm-tools">' ),
), array( true, false, true ) );

// Under the real switch (Task 18a): with no set_live() at all, the page is drawn on, and is the drawing this run made
// with the Administrator Dashboard's switch turned on by hand, every part inside the switch's root.
$GLOBALS['uid']    = 3;
$GLOBALS['manage'] = array( 3 );
$_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] = WPCPM_Administrators_Dashboard::VIEW_REFERENCE;
WPCPM_UI::set_live( null );
$tp_real = WPCPM_Administrators_Dashboard::render( array() );
unset( $_GET[ WPCPM_Administrators_Dashboard::VIEW_ARG ] );
$ov_real = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::set_live( array( 'administrator' ) );
$ov_hand = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::set_live( array() );
$ov_off = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::set_live( null );
$GLOBALS['uid']    = 0;
$GLOBALS['manage'] = array();
ck( 'under the real switch the Administrator Dashboard is drawn on: The program and the Overview are this run\'s drawings with the switch turned on by hand, not the off drawing, and every part sits inside the switch\'s root', array(
	WPCPM_UI::LIVE,
	$tp_real === $tp_page,
	$ov_real === $ov_hand,
	$ov_real === $ov_off,
	has( $ov_real, 'wpcpm-administrator wpcpm-administrator--overview wpcpm-ui-on' ),
	parts_outside_switch( $ov_real )[0],
), array( array( 'administrator', 'institution' ), true, true, false, true, array() ) );

/* ---- the students waiting to graduate (1.123.93) -------------------------- */

echo "\n=== The students waiting to graduate (1.123.93) ===\n";

// The card lists the roster rows whose Students-table status is Pending graduation, oldest first by when the students
// sync first saw each pending (the account's stamp), then the end of the internship, then the name. Its own world:
// two institutions, a test one, a few accounts with their stamps, and the rest of the fixture as it stands.
$gr_kept = array( $GLOBALS['rosters'], $GLOBALS['inst_rows'], $GLOBALS['users'], $GLOBALS['umeta'] ?? array(), $GLOBALS['unlinked'] ?? null, $GLOBALS['student_page'] ?? null, $GLOBALS['uid'], $GLOBALS['manage'] );
$gr_T    = 'recTESTGRADINST01';
// The program map, the compiled tracks', for the course and its target (a check above emptied the filters).
$gr_filters = $GLOBALS['live_filters'];
foreach ( array( 'wpcpm_program_labels' => 'filter_labels', 'wpcpm_program_courses' => 'filter_courses', 'wpcpm_program_hours_targets' => 'filter_hours', 'wpcpm_program_tracks' => 'filter_tracks', 'wpcpm_program_course_ids' => 'filter_course_ids' ) as $gr_hook => $gr_filter ) {
	$GLOBALS['live_filters'][ $gr_hook ] = array( array( 'WPCPM_Tracks', $gr_filter ) );
}
$GLOBALS['inst_rows'][ $gr_T ] = array( 'record_id' => $gr_T, 'name' => 'TEST - Graduation School', 'stage' => 'Confirmed', 'country' => '', 'country_name' => '' );
$GLOBALS['student_page']       = 'https://example.test/student-report-card/';
$GLOBALS['uid']                = 3;
$GLOBALS['manage']             = array( 3 );
$gr_now                        = time();
$gr_row                        = static function ( $record, $name, $status, array $more = array() ) {
	return array_merge( array( 'record_id' => $record, 'name' => $name, 'status' => $status, 'start' => '2026-03-02', 'end' => '', 'reports' => array( 'recR' . substr( $record, 4 ) ), 'has_mentor' => false, 'mentor_name' => '', 'hours' => '', 'user_id' => 0 ), $more );
};
$gr_account                    = static function ( $id, $name, $at, $before, $course = '' ) {
	$GLOBALS['users'][ $id ] = new WP_User( $id, $name, 'u' . $id . '@example.test' );
	if ( $at > 0 ) {
		$GLOBALS['umeta'][ $id ][ WPCPM_Students_Sync::META_PENDING ] = array( 'at' => $at, 'before' => $before );
	}
	if ( '' !== $course ) {
		$GLOBALS['umeta'][ $id ][ WPCPM_Students_Sync::META_COURSE ] = $course;
	}
};
$gr_draw                       = static function ( $rows ) {
	ob_start();
	WPCPM_Administrators_Cards::render_graduation( $rows );
	return (string) ob_get_clean();
};
$gr_cells                      = static function ( $html ) {
	// Each row's cells as text, a no-break space read as a space, by the row's header: the student's name, then the
	// institution under it (the header cell's second line, fix round 2), then the other cells.
	preg_match_all( '#<tr role="row"><th scope="row" role="rowheader"[^>]*>(.*?) <span class="wpcpm-ui-cell__sub">(.*?)</span></th>(.*?)</tr>#s', (string) $html, $rows, PREG_SET_ORDER );
	$out = array();
	foreach ( $rows as $row ) {
		preg_match_all( '#<td [^>]*>(.*?)</td>#s', $row[3], $cells );
		$out[] = array_map( static function ( $cell ) { return trim( str_replace( "\u{00A0}", ' ', html_entity_decode( strip_tags( $cell ), ENT_QUOTES ) ) ); }, array_merge( array( $row[1], $row[2] ), $cells[1] ) );
	}
	return $out;
};

// Nobody pending: no card, the queue named among the empty ones with its link to the Students screen, and the counts
// as they were.
$gr_screen = 'https://example.test/wp-admin/admin.php?page=wpcpm-students&wpcpm_student_status=Pending%20graduation';
$gr_none   = WPCPM_Administrators_Dashboard::render( array() );
$gr_nd     = WPCPM_Administrators_Cards::collect();
ck( 'graduation_none: nobody pending draws no card, names the queue among the empty ones with the Students screen filtered by the status, and gives no count', array(
	has( $gr_none, 'id="wpcpm-graduation"' ),
	quiet_links( $gr_none )['Students waiting to graduate'] ?? null,
	$gr_nd['graduation'],
	array_key_exists( 'graduation', WPCPM_Administrators_Cards::counts( $gr_nd ) ),
), array( false, $gr_screen, array(), false ) );

// Two pending in two institutions, and one in a test institution whose stamp is the oldest of all; a student on a track
// and one whose program row says pending while the Students row does not: the roster's word wins both ways.
$gr_account( 961, 'Ola Older', $gr_now - 9 * DAY_IN_SECONDS, false, 'In Sensei' );
$gr_account( 962, 'Pia Newer', $gr_now - 2 * DAY_IN_SECONDS, false, 'Developer Track' );
$gr_account( 963, 'Tess Test', $gr_now - 30 * DAY_IN_SECONDS, false, 'In Sensei' );
$gr_account( 964, 'Rex Reports', $gr_now - 40 * DAY_IN_SECONDS, false, 'In Sensei' );
$GLOBALS['umeta'][964][ 'wpcpm_student_program' ] = array( 'program' => 'Pending graduation' );
$GLOBALS['rosters'][ $A ]['rows'][] = $gr_row( 'recSGRAD000000001', 'Ola Older', 'Pending graduation', array( 'user_id' => 961, 'end' => '2026-06-30', 'hours' => '152', 'has_mentor' => true, 'mentor_name' => 'Mentor <b>One</b> & Co' ) );
$GLOBALS['rosters'][ $A ]['rows'][] = $gr_row( 'recSGRAD000000004', 'Rex Reports', 'In Sensei', array( 'user_id' => 964 ) );
$GLOBALS['rosters'][ $B ]['rows'][] = $gr_row( 'recSGRAD000000002', 'Pia <i>Newer</i>', ' Pending graduation ', array( 'user_id' => 962, 'end' => '2026-07-15', 'hours' => '12' ) );
$GLOBALS['rosters'][ $gr_T ]        = array( 'v' => 4, 'read' => 1756900000, 'rows' => array( $gr_row( 'recSGRAD000000003', 'Tess Test', 'Pending graduation', array( 'user_id' => 963, 'end' => '2026-06-01', 'hours' => '150' ) ) ) );
$gr_data   = WPCPM_Administrators_Cards::collect();
$gr_counts = WPCPM_Administrators_Cards::counts( $gr_data );
$gr_card   = $gr_draw( $gr_data['graduation'] );
ck( 'graduation_two_and_test: two real students and the test one are listed, oldest stamp first, the program row\'s word never used', array_column( $gr_data['graduation'], 'name' ), array( 'Tess Test', 'Ola Older', 'Pia <i>Newer</i>' ) );
ck( 'the tile counts the two real ones, says the card\'s title and leads to it, right after the requests from institutions and before the mentor applications', array(
	$gr_counts['graduation'] ?? null,
	array_slice( array_keys( $gr_counts ), array_search( 'requests', array_keys( $gr_counts ), true ), 3 ),
), array( array( 'label' => 'Students waiting to graduate', 'n' => 2, 'card' => 'graduation' ), array( 'requests', 'graduation', 'mentor_applications' ) ) );
ck( 'the card\'s row: open while one waits, its badge the real count, saying how many wait, and after the fold the way to the Students screen filtered by the status', array( card_row( $gr_card ), card_badge( $gr_card ) ), array(
	array( 'open' => true, 'state' => '2 waiting', 'action' => '<a class="wpcpm-button wpcpm-button--secondary" href="' . $gr_screen . '">Open on the Students screen<span class="screen-reader-text"> for the Students waiting to graduate card</span></a>' ),
	2,
) );
ck( 'its body: one muted line that says what to do, the table, and the foot to the same screen, in the same words', array(
	has( $gr_card, '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">These students have finished and wait for an Administrator: read their Student Report Card, ask the mentor if something is unclear, and approve the quiz on Learn so the certificate goes out. Then set the graduation date in Airtable: the student leaves this list at the first students sync after that date.</p><table class="wpcpm-ui-table wpcpm-ui-table--wide wpcpm-ui-table--pairs-4" role="table" aria-labelledby="' . WPCPM_Dashboard_Card::title_id( 'wpcpm-graduation' ) . '">' ),
	ends_with( seen( $gr_card ), card_foot( $gr_screen, 'Open on the Students screen' ) ),
), array( true, true ) );
preg_match_all( '#<th scope="col" role="columnheader" class="([^"]*)">([^<]*)</th>#', $gr_card, $gr_heads );
ck( 'the columns: Student (the institution under the name, fix round 2), Track, Mentor, Hours and Waiting since (no Ended column, fix round 3), the date as a date and the hours whole', array_map( null, $gr_heads[2], $gr_heads[1] ), array(
	array( 'Student', 'wpcpm-ui-cell--text' ), array( 'Track', 'wpcpm-ui-cell--text' ), array( 'Mentor', 'wpcpm-ui-cell--text' ),
	array( 'Hours', 'wpcpm-ui-cell--short' ), array( 'Waiting since', 'wpcpm-ui-cell--date' ),
) );
ck( 'each row: the student, the institution, the course they wait on, the mentor, the hours against the track\'s target (none on a track with no target) and the day the sync first saw them pending', $gr_cells( $gr_card ), array(
	array( 'Tess Test', 'TEST - Graduation School Test', WPCPM_Program::label( 'In Sensei' ), 'No mentor', '150 of 150', gmdate( 'j F Y', $gr_now - 30 * DAY_IN_SECONDS ) ),
	array( 'Ola Older', 'Uniwersytet Alpha', WPCPM_Program::label( 'In Sensei' ), 'Mentor <b>One</b> & Co', '152 of 150', gmdate( 'j F Y', $gr_now - 9 * DAY_IN_SECONDS ) ),
	array( 'Pia <i>Newer</i>', 'Universidad Beta', WPCPM_Program::label( 'Developer Track' ), 'No mentor', '12 h', gmdate( 'j F Y', $gr_now - 2 * DAY_IN_SECONDS ) ),
) );
ck( 'graduation_links_and_escaping: the name links to the Student Report Card, the course to Learn, the institution under the name is plain text (fix round 3), every name escaped, the test one marked', array(
	has( $gr_card, '<th scope="row" role="rowheader" class="wpcpm-ui-cell--text wpcpm-ui-cell--rowhead"><a href="https://example.test/student-report-card/?wpcpm_student_view=962">Pia &lt;i&gt;Newer&lt;/i&gt;</a> <span class="wpcpm-ui-cell__sub">Universidad Beta</span></th>' ),
	has( $gr_card, '<a href="' . WPCPM_Program::course_url( 'Developer Track' ) . '">' . esc_html( WPCPM_Program::label( 'Developer Track' ) ) . '</a>' ),
	has( $gr_card, 'data-label="Mentor">Mentor &lt;b&gt;One&lt;/b&gt; &amp; Co</td>' ),
	has( $gr_card, 'Pia <i>' ) || has( $gr_card, '<b>One' ),
	has( $gr_card, '<span class="wpcpm-ui-cell__sub">Uniwersytet Alpha</span>' ) && ! has( $gr_card, 'wpcpm_institution_view' ),
	// The Test mark beside the test school's name, inside the line under the student.
	1 === preg_match( '#<span class="wpcpm-ui-cell__sub">TEST - Graduation School <span class="' . WPCPM_Test_Institutions::MARK_DASHBOARD . '" title="[^"]*">Test</span></span></th>#', $gr_card ) ? 1 : 0,
	has( $gr_card, '<td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell" data-label="Mentor">No mentor</td>' ),
), array( true, true, true, false, true, 1, true ) );
ck( 'graduation_dates_whole: the day first seen is whole, its day, month and year never parted; no Institution and no Ended column', array(
	has( $gr_card, 'data-label="Waiting since">' . str_replace( ' ', "\u{00A0}", gmdate( 'j F Y', $gr_now - 9 * DAY_IN_SECONDS ) ) . '</td>' ),
	has( $gr_card, '>Institution</th>' ),
	has( $gr_card, 'data-label="Ended"' ),
), array( true, false, false ) );
ck( 'nothing on the card is a form or a button: a graduation is not changed on this site', array( has( $gr_card, '<form' ), has( $gr_card, '<button' ), has( $gr_card, 'wpcpm-ui-decide' ) ), array( false, false, false ) );

// On the Overview: its place, its head like the other queues' heads, and its age from the oldest real stamp, the test
// institution's left out as it is of every count.
$gr_page = WPCPM_Administrators_Dashboard::render( array() );
$gr_mark = 1 === preg_match( '#id="wpcpm-graduation"><details class="wpcpm-ui-fold"( open)?><summary class="wpcpm-ui-fold__head">.*?<span class="wpcpm-ui-fold__phrase">([^<]*)</span><span class="wpcpm-ui-fold__end">(?:<span class="wpcpm-status wpcpm-status--red">([^<]*)</span>)?<span class="wpcpm-ui-fold__cue" aria-hidden="true">([^<]*)</span>#s', $gr_page, $gr_m )
	? array( 'open' => '' !== $gr_m[1], 'phrase' => $gr_m[2], 'age' => $gr_m[3], 'cue' => $gr_m[4] )
	: array();
ck( 'graduation_on_overview: drawn once in Waiting for you, after Requests from institutions and before the mentor applications, folded with the Review cue, nine days waiting from the oldest real stamp', array(
	substr_count( $gr_page, 'id="wpcpm-graduation"' ),
	in_order( $gr_page, array( 'id="wpcpm-requests"', 'id="wpcpm-graduation"', 'id="wpcpm-mentor-applications"' ) ),
	$gr_mark,
	array_key_exists( 'Students waiting to graduate', quiet_links( $gr_page ) ),
), array( 1, true, array( 'open' => false, 'phrase' => '2 waiting', 'age' => '9 days waiting', 'cue' => 'Review' ), false ) );
ck( 'the head counts the two among the decisions that wait', array(
	1 === preg_match( '#<p class="wpcpm-head__title">([0-9]+) decisions? waits? for you</p>#', $gr_page, $gr_head ) ? (int) $gr_head[1] : null,
), array( array_sum( array_map( static function ( $key ) use ( $gr_counts ) { return isset( $gr_counts[ $key ] ) ? (int) $gr_counts[ $key ]['n'] : 0; }, array( 'applications', 'agreements', 'reports', 'requests', 'graduation', 'mentor_applications', 'sponsor_applications', 'sponsor_posts', 'sponsor_offers', 'sponsor_events', 'sponsor_agreements', 'sponsor_reports' ) ) ) ) );
// The same page on the parts: every part inside the switch's root, and the card's head the shell's, as every queue's.
WPCPM_UI::set_live( array( 'administrator' ) );
$gr_on   = WPCPM_Administrators_Dashboard::render( array() );
WPCPM_UI::enter( 'administrator' );
$gr_card_on = $gr_draw( $gr_data['graduation'] );
WPCPM_UI::leave();
WPCPM_UI::set_live( array() );
$gr_head_of = static function ( $html, $id ) {
	return 1 === preg_match( '#<section class="wpcpm-administrator__card wpcpm-ui-fold-card" id="' . $id . '"><details class="wpcpm-ui-fold"( open)?><summary class="wpcpm-ui-fold__head">(.*?)</summary>#s', (string) $html, $m ) ? preg_replace( '#id="[^"]*"|>[^<]*<#', '><', $m[2] ) : null;
};
ck( 'on the parts every part sits inside the switch\'s root, and the card\'s head is built as the Requests from institutions card\'s', array(
	parts_outside_switch( $gr_on )[0],
	null !== $gr_head_of( $gr_on, 'wpcpm-graduation' ),
	$gr_head_of( $gr_on, 'wpcpm-graduation' ) === $gr_head_of( $gr_on, 'wpcpm-requests' ),
	has( $gr_card_on, 'wpcpm-ui-table--wide wpcpm-ui-table--pairs-4' ),
), array( array(), true, true, true ) );

// A pending roster row with no account, one whose account is gone, a mentor on the Students row whose name has not
// arrived, a student with no hours recorded, and one already pending on the first run that kept the stamps.
$gr_account( 965, 'Una Before', $gr_now - 3 * DAY_IN_SECONDS, true, 'In Sensei 50h' );
$GLOBALS['rosters'][ $B ]['rows'][] = $gr_row( 'recSGRAD000000005', 'Una Before', 'Pending graduation', array( 'user_id' => 965, 'end' => '2026-05-01', 'hours' => '50' ) );
$GLOBALS['unlinked']                = array(
	'recSGRAD000000006' => $gr_row( 'recSGRAD000000006', 'Vic Noaccount', 'Pending graduation', array( 'end' => '2026-05-20', 'has_mentor' => true ) ),
	'recSGRAD000000007' => $gr_row( 'recSGRAD000000007', 'Wes Gone', 'Pending graduation', array( 'user_id' => 999 ) ),
);
$gr_more      = WPCPM_Administrators_Cards::collect()['graduation'];
$gr_more_card = $gr_draw( $gr_more );
$gr_more_rows = $gr_cells( $gr_more_card );
ck( 'graduation_no_account: a row with no account, or whose account is gone, is drawn after the stamped ones, its name with no link and no day, the account read as none', array(
	array_column( $gr_more, 'name' ),
	array_slice( $gr_more_rows, 4 ),
	array( has( $gr_more_card, '>Vic Noaccount <span class="wpcpm-ui-cell__sub">' ), has( $gr_more_card, '>Wes Gone <span class="wpcpm-ui-cell__sub">' ), array_column( $gr_more, 'user_id' )[5] ),
), array(
	array( 'Tess Test', 'Ola Older', 'Una Before', 'Pia <i>Newer</i>', 'Vic Noaccount', 'Wes Gone' ),
	array(
		array( 'Vic Noaccount', 'Not recorded', 'Not recorded', 'Not recorded', 'Not recorded', '' ),
		array( 'Wes Gone', 'Not recorded', 'Not recorded', 'No mentor', 'Not recorded', '' ),
	),
	array( true, true, 0 ),
) );
ck( 'graduation_before: a student already pending on the first run that kept the stamps reads "Before" that run\'s day, the day whole and "Before" free to go before it', array(
	$gr_more_rows[2][5],
	has( $gr_more_card, 'data-label="Waiting since">Before ' . str_replace( ' ', "\u{00A0}", gmdate( 'j F Y', $gr_now - 3 * DAY_IN_SECONDS ) ) . '</td>' ),
), array( 'Before ' . gmdate( 'j F Y', $gr_now - 3 * DAY_IN_SECONDS ), true ) );
ck( 'an empty value keeps its words, muted, and an empty date is a blank cell', array(
	has( $gr_more_card, '>Vic Noaccount <span class="wpcpm-ui-cell__sub">Not recorded</span></th>' ),
	has( $gr_more_card, '<td class="wpcpm-ui-cell--short wpcpm-ui-cell--empty" role="cell" data-label="Hours">Not recorded</td>' ),
	has( $gr_more_card, '<td class="wpcpm-ui-cell--date wpcpm-ui-cell--blank" role="cell" data-label="Waiting since"></td>' ),
), array( true, true, true ) );
// A roster the sync wrote for an institution the pipeline index does not hold (a record gone from the Institutions table):
// its pending student is listed, counted, and the school's cell keeps the empty value's words, never the record ID.
$GLOBALS['rosters']['recGONEINSTITUT01'] = array( 'v' => 4, 'read' => 1756900000, 'rows' => array( $gr_row( 'recSGRAD000000008', 'Xia Elsewhere', 'Pending graduation', array( 'end' => '2026-05-25' ) ) ) );
$gr_gone      = WPCPM_Administrators_Cards::collect();
$gr_gone_rows = $gr_cells( $gr_draw( $gr_gone['graduation'] ) );
$gr_gone_xia  = array_values( array_filter( $gr_gone_rows, static function ( $r ) { return 'Xia Elsewhere' === $r[0]; } ) );
ck( 'graduation_unindexed_roster: a roster whose institution the pipeline index does not name is read too, its student counted, the school "Not recorded"', array(
	$gr_gone_xia,
	has( $gr_draw( $gr_gone['graduation'] ), 'recGONEINSTITUT01' ),
), array( array( array( 'Xia Elsewhere', 'Not recorded', 'Not recorded', 'No mentor', 'Not recorded', '' ) ), false ) );
// Fix round 4: a roster the index does not list whose record the test list remembers is drawn with the Test mark beside
// "Not recorded" and left out of the count; the unknown real school above has no mark and is counted.
$gr_tests_kept = $GLOBALS['opts']['wpcpm_test_institutions'] ?? null;
$GLOBALS['opts']['wpcpm_test_institutions']  = array( 'recGONETESTINST01' );
$GLOBALS['rosters']['recGONETESTINST01']     = array( 'v' => 4, 'read' => 1756900000, 'rows' => array( $gr_row( 'recSGRAD000000009', 'Yan Testgone', 'Pending graduation' ) ) );
$gr_tg      = WPCPM_Administrators_Cards::collect();
$gr_tg_card = $gr_draw( $gr_tg['graduation'] );
unset( $GLOBALS['rosters']['recGONETESTINST01'] );
$gr_tg_less = WPCPM_Administrators_Cards::collect();
ck( 'graduation_unindexed_test: a remembered test school the index does not list reads "Not recorded" with the Test mark and is not counted; an unknown real school has no mark and is counted', array(
	1 === preg_match( '#>Yan Testgone <span class="wpcpm-ui-cell__sub">Not recorded <span class="' . WPCPM_Test_Institutions::MARK_DASHBOARD . '" title="[^"]*">Test</span></span></th>#', $gr_tg_card ),
	has( $gr_tg_card, '>Xia Elsewhere <span class="wpcpm-ui-cell__sub">Not recorded</span></th>' ),
	WPCPM_Administrators_Cards::counts( $gr_tg )['graduation']['n'] === WPCPM_Administrators_Cards::counts( $gr_tg_less )['graduation']['n'],
), array( true, true, true ) );
if ( null === $gr_tests_kept ) { unset( $GLOBALS['opts']['wpcpm_test_institutions'] ); } else { $GLOBALS['opts']['wpcpm_test_institutions'] = $gr_tests_kept; }
unset( $GLOBALS['rosters']['recGONEINSTITUT01'] );
ck( 'and without that roster the count is one fewer', WPCPM_Administrators_Cards::counts( $gr_gone )['graduation']['n'] - WPCPM_Administrators_Cards::counts( WPCPM_Administrators_Cards::collect() )['graduation']['n'], 1 );
$GLOBALS['student_page'] = '';
ck( 'graduation_no_page: with the Student Report Card not published every name is plain text', array( has( $gr_draw( $gr_more ), 'wpcpm_student_view' ), has( $gr_draw( $gr_more ), '>Ola Older <span class="wpcpm-ui-cell__sub">' ) ), array( false, true ) );
$GLOBALS['student_page'] = 'https://example.test/student-report-card/';

// A long unbroken run in a name breaks at its break points, inside its link too (fix round 2): the student's, the
// school's, the track's and the mentor's; a name of twenty characters is printed as it was.
$gr_long_run = 'Abcdefghijklmnopqrstuvwxyzabcdefghijklmn';
$gr_long     = $gr_more;
$gr_long[1]  = array_merge( $gr_long[1], array( 'name' => 'Stud' . $gr_long_run, 'mentor_name' => 'Ment' . $gr_long_run ) );
$gr_long[2]  = array_merge( $gr_long[2], array( 'name' => 'Twentycharacterword1', 'mentor_name' => 'Mentor' ) );
$GLOBALS['inst_rows'][ $A ]['name']                   = 'Inst' . $gr_long_run;
$GLOBALS['live_filters']['wpcpm_program_labels'][]   = static function ( $labels ) use ( $gr_long_run ) { $labels['In Sensei'] = 'Trak' . $gr_long_run; return $labels; };
$gr_long_card = $gr_draw( $gr_long );
array_pop( $GLOBALS['live_filters']['wpcpm_program_labels'] );
$GLOBALS['inst_rows'][ $A ]['name'] = 'Uniwersytet Alpha';
ck( 'graduation_long_runs: forty-four characters break every ten in the student\'s, the school\'s, the track\'s and the mentor\'s names, inside the student\'s and the track\'s links; twenty are left as they are', array(
	has( $gr_long_card, '?wpcpm_student_view=961">StudAbcdef<wbr>ghijklmnop<wbr>qrstuvwxyz<wbr>abcdefghij<wbr>klmn</a>' ),
	has( $gr_long_card, '<span class="wpcpm-ui-cell__sub">InstAbcdef<wbr>' ),
	has( $gr_long_card, '">TrakAbcdef<wbr>ghijklmnop<wbr>' ),
	has( $gr_long_card, 'data-label="Mentor">MentAbcdef<wbr>' ),
	has( $gr_long_card, '>Twentycharacterword1</a>' ),
), array( true, true, true, true, true ) );

// Sixty pending: all drawn, by stamp, then end date, then name. Twenty share one stamp, as on the first run after the
// release; ten of those share an end date too.
$GLOBALS['unlinked'] = array();
$GLOBALS['rosters'][ $A ]['rows'] = array_slice( $GLOBALS['rosters'][ $A ]['rows'], 0, 6 );
$GLOBALS['rosters'][ $B ]['rows'] = array_slice( $GLOBALS['rosters'][ $B ]['rows'], 0, 1 );
unset( $GLOBALS['rosters'][ $gr_T ] );
$gr_want = array();
for ( $n = 1; $n <= 60; $n++ ) {
	$id    = 1000 + $n;
	$at    = $n <= 20 ? $gr_now - 50 * DAY_IN_SECONDS : $gr_now - ( 80 - $n ) * HOUR_IN_SECONDS;
	$end   = $n <= 10 ? '2026-06-30' : sprintf( '2026-%02d-%02d', 1 + ( $n % 12 ), 1 + ( $n % 27 ) );
	$name  = sprintf( 'Student %s %02d', chr( 90 - ( $n % 26 ) ), $n );
	$gr_account( $id, $name, $at, $n <= 20, 'In Sensei' );
	$GLOBALS['rosters'][ 0 === $n % 2 ? $A : $B ]['rows'][] = $gr_row( sprintf( 'recSSIXTY%08d', $n ), $name, 'Pending graduation', array( 'user_id' => $id, 'end' => $end ) );
	$gr_want[] = array( $at, $end, $name );
}
usort( $gr_want, static function ( $a, $b ) { return array( $a[0], $a[1] ) === array( $b[0], $b[1] ) ? strcasecmp( $a[2], $b[2] ) : ( array( $a[0], $a[1] ) < array( $b[0], $b[1] ) ? -1 : 1 ); } );
$GLOBALS['gr_cached'] = array();
$gr_sixty      = WPCPM_Administrators_Cards::collect()['graduation'];
$gr_sixty_card = $gr_draw( $gr_sixty );
ck( 'graduation_sixty: sixty pending are all drawn, oldest stamp first, then the earlier end, then the name', array(
	count( $gr_cells( $gr_sixty_card ) ),
	array_column( $gr_cells( $gr_sixty_card ), 0 ),
	card_badge( $gr_sixty_card ),
), array( 60, array_column( $gr_want, 2 ), 60 ) );
$gr_primed = array_values( array_filter( (array) ( $GLOBALS['gr_cached'] ?? array() ), static function ( $ids ) { return in_array( 1001, $ids, true ); } ) );
ck( 'and the sixty are read with one priming of the accounts, not one read each', array( count( $gr_primed ), isset( $gr_primed[0] ) ? count( array_intersect( range( 1001, 1060 ), $gr_primed[0] ) ) : 0 ), array( 1, 60 ) );

// A student who finished Feedback form 3 on the site while the base refused the status (1.123.95): the account owes it
// (`WPCPM_Student_Graduation::META_OWED`) and their roster row still says the track. Listed all the same, with the words
// that the status is not recorded yet, waiting since the form was sent, and counted; one the site holds no Students row
// for is drawn from the account; one the roster already holds at Pending graduation is listed once, without the words.
$GLOBALS['rosters'][ $A ]['rows'] = array();
$GLOBALS['rosters'][ $B ]['rows'] = array();
$GLOBALS['unlinked']              = array();
$gr_owe = static function ( $id, $name, $at, $course, $kind = 'retry' ) use ( $gr_account ) {
	$gr_account( $id, $name, 0, false, $course );
	$GLOBALS['umeta'][ $id ][ WPCPM_Student_Graduation::META_OWED ] = array( 'at' => $at, 'by' => $id, 'as' => 'student', 'reason' => 'Airtable request failed (HTTP 503).', 'tried' => $at, 'kind' => $kind );
};
$gr_owe( 971, 'Owen Owed', $gr_now - 2 * DAY_IN_SECONDS, 'In Sensei' );
$GLOBALS['umeta'][971][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'program' => 'In Sensei' );
$GLOBALS['rosters'][ $A ]['rows'][] = $gr_row( 'recSOWED000000001', 'Owen Owed', 'In Sensei', array( 'user_id' => 971, 'hours' => '151', 'has_mentor' => true, 'mentor_name' => 'Mentor One' ) );
$gr_owe( 972, 'Nia Norow', $gr_now - 1 * DAY_IN_SECONDS, '', 'no-row' );
$GLOBALS['umeta'][972][ WPCPM_Students_Sync::META_INSTITUTION ] = $B;
$GLOBALS['umeta'][972][ WPCPM_Students_Sync::META_PROGRAM ]     = array( 'program' => 'Developer Track', 'hours' => '40', 'end' => '2026-07-01' );
$GLOBALS['umeta'][972][ WPCPM_Students_Sync::META_MENTOR ]      = array( 'record_id' => 'recMENTOR00000001', 'name' => 'Mentor Two' );
$gr_owe( 973, 'Ray Recorded', $gr_now - 3 * DAY_IN_SECONDS, 'In Sensei' );
// N5: pending on the roster while the program row still says the track (the reports row lags): listed once, by the roster.
$GLOBALS['umeta'][973][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'program' => 'In Sensei' );
// N4: a debt for a row whose address is another's.
$gr_owe( 976, 'Zed Elsewhere', $gr_now - 12 * HOUR_IN_SECONDS, '', 'other-email' );
$GLOBALS['umeta'][976][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'program' => 'In Sensei' );
$GLOBALS['rosters'][ $A ]['rows'][] = $gr_row( 'recSOWED000000006', 'Zed Elsewhere', 'In Sensei', array( 'user_id' => 976, 'hours' => '146' ) );
// An owing account whose program status is no longer a track: a debt that can no longer be paid is not listed.
$gr_owe( 975, 'Pat Pastdone', $gr_now - 4 * DAY_IN_SECONDS, 'In Sensei' );
$GLOBALS['umeta'][975][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'program' => 'Graduate' );
$GLOBALS['rosters'][ $A ]['rows'][] = $gr_row( 'recSOWED000000005', 'Pat Pastdone', 'In Sensei', array( 'user_id' => 975 ) );
$GLOBALS['umeta'][973][ WPCPM_Students_Sync::META_PENDING ] = array( 'at' => $gr_now - 3 * DAY_IN_SECONDS, 'before' => false );
$GLOBALS['rosters'][ $B ]['rows'][] = $gr_row( 'recSOWED000000003', 'Ray Recorded', 'Pending graduation', array( 'user_id' => 973 ) );
$gr_account( 974, 'Tom Track', 0, false, 'In Sensei' );
$GLOBALS['rosters'][ $A ]['rows'][] = $gr_row( 'recSOWED000000004', 'Tom Track', 'In Sensei', array( 'user_id' => 974 ) );
unset( $GLOBALS['rosters'][ $gr_T ] );
$GLOBALS['users_asked'] = array();
$gr_owed      = WPCPM_Administrators_Cards::collect();
$gr_owed_card = $gr_draw( $gr_owed['graduation'] );
$gr_asked_once = $GLOBALS['users_asked'];
$gr_words     = 'Finished Feedback form 3 on this site. The program\'s records do not say Pending graduation yet: each students sync tries again.';
$gr_no_row    = 'Finished Feedback form 3 on this site. The site finds no Students row for this student in the program\'s records, so the status cannot be set: check the student\'s row there, and the next students sync sets it.';
$gr_other     = 'Finished Feedback form 3 on this site. The Students row on their track holds an email address other than this student\'s, so the status was not set: correct the row in the program\'s records, and the next students sync sets it.';
$gr_owed_rows = array();
foreach ( $gr_owed['graduation'] as $gr_r ) {
	$gr_owed_rows[ $gr_r['name'] ] = array( $gr_r['institution'], $gr_r['owed'] ?? null, $gr_r['at'], $gr_r['user_id'] );
}
ck( 'graduation_owed: an owed student is listed although the roster says a track, and one with no Students row from the account; one the roster holds pending once; a student on a track who owes nothing never', array(
	array_column( $gr_owed['graduation'], 'name' ),
	$gr_owed_rows,
	WPCPM_Administrators_Cards::counts( $gr_owed )['graduation']['n'] ?? null,
), array(
	array( 'Ray Recorded', 'Owen Owed', 'Nia Norow', 'Zed Elsewhere' ),
	array(
		'Ray Recorded'  => array( $B, false, $gr_now - 3 * DAY_IN_SECONDS, 973 ),
		'Owen Owed'     => array( $A, 'retry', $gr_now - 2 * DAY_IN_SECONDS, 971 ),
		'Nia Norow'     => array( $B, 'no-row', $gr_now - 1 * DAY_IN_SECONDS, 972 ),
		'Zed Elsewhere' => array( $A, 'other-email', $gr_now - 12 * HOUR_IN_SECONDS, 976 ),
	),
	4,
) );
// Printed escaped, the apostrophe an entity.
$gr_words  = esc_html( $gr_words );
$gr_no_row = esc_html( $gr_no_row );
$gr_other  = esc_html( $gr_other );
ck( 'I2 card_words: an owed row with a Students row says the sync tries again; one with no Students row says what to do instead; the recorded one says neither', array(
	substr_count( $gr_owed_card, $gr_words ),
	substr_count( $gr_owed_card, $gr_no_row ),
	has( $gr_owed_card, '?wpcpm_student_view=971">Owen Owed</a> <span class="wpcpm-ui-cell__sub">Uniwersytet Alpha</span> <span class="wpcpm-ui-cell__sub">' . $gr_words . '</span></th>' ),
	has( $gr_owed_card, '>Nia Norow</a> <span class="wpcpm-ui-cell__sub">' ) && has( substr( $gr_owed_card, (int) strpos( $gr_owed_card, '>Nia Norow</a>' ), 600 ), $gr_no_row ),
	has( substr( $gr_owed_card, (int) strpos( $gr_owed_card, 'Ray Recorded' ), 400 ), 'Finished Feedback form 3' ),
	has( $gr_owed_card, 'Pat Pastdone' ),
	substr_count( $gr_owed_card, $gr_other ),
	has( substr( $gr_owed_card, (int) strpos( $gr_owed_card, '>Zed Elsewhere</a>' ), 700 ), $gr_other ),
), array( 1, 1, true, true, false, false, 1, true ) );

// The trial's point: an owed student is still on their track, and no course is kept for them yet. Their Track and Hours
// come from the track they are on today, drawn as a recorded student's are.
$gr_cells_of = static function ( $html, $name ) use ( $gr_cells ) {
	foreach ( $gr_cells( $html ) as $cells ) {
		if ( $name === $cells[0] ) {
			return array( $cells[2], $cells[4] );
		}
	}
	return array();
};
ck( 'owed_track: an owed row\'s Track and Hours are the track it is on today, from the roster row or, with no row, the program row', array(
	$gr_cells_of( $gr_owed_card, 'Zed Elsewhere' ),
	$gr_cells_of( $gr_owed_card, 'Nia Norow' ),
), array(
	array( WPCPM_Program::label( 'In Sensei' ), WPCPM_Program::hours_text( '146', 'In Sensei' ) ),
	array( WPCPM_Program::label( 'Developer Track' ), WPCPM_Program::hours_text( '40', 'Developer Track' ) ),
) );

// N6: a row a run moved from the unlinked list onto a roster, with a copy of it still in the unlinked list: one line.
$GLOBALS['unlinked'] = array( 'recSOWED000000003' => $gr_row( 'recSOWED000000003', 'Ray Recorded', 'Pending graduation', array( 'user_id' => 973 ) ) );
ck( 'N6 listed_once: a student whose row is on a roster and still in the unlinked list is listed once', array_count_values( array_column( WPCPM_Administrators_Cards::collect()['graduation'], 'name' ) )['Ray Recorded'] ?? 0, 1 );
// m5 (j): the same for a student who owes nothing: one Students record on a roster and in the unlinked list, one line.
$gr_account( 978, 'Uma Twice', $gr_now - 5 * DAY_IN_SECONDS, false, 'In Sensei' );
$GLOBALS['rosters'][ $A ]['rows'][] = $gr_row( 'recSTWICE00000001', 'Uma Twice', 'Pending graduation', array( 'user_id' => 978 ) );
$GLOBALS['unlinked']                = array( 'recSTWICE00000001' => $gr_row( 'recSTWICE00000001', 'Uma Twice', 'Pending graduation', array( 'user_id' => 978 ) ) );
ck( 'm5 listed_once_no_debt: a student who owes nothing, their row on a roster and in the unlinked list, is listed once', array_count_values( array_column( WPCPM_Administrators_Cards::collect()['graduation'], 'name' ) )['Uma Twice'] ?? 0, 1 );
array_pop( $GLOBALS['rosters'][ $A ]['rows'] );
$GLOBALS['unlinked'] = array();
$gr_owed_asks = array_values( array_filter( $gr_asked_once, static function ( $a ) { return ( $a['meta_key'] ?? '' ) === WPCPM_Student_Graduation::META_OWED; } ) );
ck( 'M4 one_query: the owing accounts are read in one query, their meta primed with it', array( count( $gr_owed_asks ), $gr_owed_asks[0]['fields'] ?? null ), array( 1, 'all_with_meta' ) );
ck( 'the one drawn from the account names the track, the mentor, the hours and the day the form was sent', array(
	has( $gr_owed_card, '>Nia Norow</a>' ),
	has( $gr_owed_card, 'data-label="Mentor">Mentor Two</td>' ),
	has( $gr_owed_card, 'data-label="Hours">40' ),
	has( $gr_owed_card, 'data-label="Waiting since">' . str_replace( ' ', "\u{00A0}", gmdate( 'j F Y', $gr_now - 1 * DAY_IN_SECONDS ) ) . '</td>' ),
), array( true, true, true, true ) );

list( $GLOBALS['rosters'], $GLOBALS['inst_rows'], $GLOBALS['users'], $GLOBALS['umeta'], $gr_unlinked, $gr_page_kept, $GLOBALS['uid'], $GLOBALS['manage'] ) = $gr_kept;
if ( null === $gr_unlinked ) { unset( $GLOBALS['unlinked'] ); } else { $GLOBALS['unlinked'] = $gr_unlinked; }
if ( null === $gr_page_kept ) { unset( $GLOBALS['student_page'] ); } else { $GLOBALS['student_page'] = $gr_page_kept; }
unset( $GLOBALS['gr_cached'] );
$GLOBALS['live_filters'] = $gr_filters;

// Back to reading the site: the seam was set for this run only.
WPCPM_Mentor_Engagement::for_tests( null );
// The inventory (UI shared parts, Task 17b; bin/stubs/parts-inventory.php): every old class this suite's drawings on
// the Administrator Dashboard's parts print inside the switch's root is one the inventory names, and no other.
ck( 'the inventory: the old classes printed inside the parts are exactly the ones named in bin/stubs/parts-inventory.php', parts_inventory_found(), parts_inventory_pinned( 'administrators-dashboard' ) );

// Fix round 4 (Minor 2): Sponsor reports' "Term ended on" is a whole date.
ck( 'fix round 4: a sponsor report\'s Term ended on is a whole date', 1 === preg_match( '/data-label="Term ended on">[^<\s]+\x{00A0}[^<\s]+(\x{00A0}[^<\s]+)?<\/td>/u', $sr_all ), true );
// Task 18f, fix round 3: a date that may wrap in a stacked pair keeps its day, month and year together, its time free.
ck( 'fix round 3: Syncs and health\'s runs and Sponsor terms ending\'s day are whole dates', array(
	1 === preg_match( '/data-label="Last run">[^<\s]+\x{00A0}[^<\s]+\x{00A0}[^<\s]+ [^<]+</u', $wh_on ),
	1 === preg_match( '/data-label="Ends on">[^<\s]+\x{00A0}[^<\s]+\x{00A0}[^<\s]+ /u', $wl_on['tiers full'] ),
), array( true, true ) );
printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
