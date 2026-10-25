<?php
/**
 * Offers, codes, claims and usage: the data behind "Tools from our sponsors" (Phase S2).
 *
 * What is pinned here and why:
 *
 * - An offer is a private post whose sponsor is meta the site wrote, never a form value; the
 *   seeded first offer copies the base's Offer, Brief instructions and More info link, and only
 *   a link that is not the coupon sheet becomes a shared code (the sheet names students).
 * - Codes are sealed with the site key and base64 for the option row, found again by a keyed
 *   fingerprint, never unsealed to compare, and the option is not autoloaded. A paste is all or
 *   nothing, refused by line number.
 * - The state machine: a pool cannot go live empty, ended is final, the kind is fixed once the
 *   pool holds anything. Since 1.123.18 no tier caps live offers: the partner page offers products
 *   at any level, separately from the levels, so with the tiers announced a Friend, a Supporter
 *   and a Champion alike switch on as many as they like, and a Save that reopens an expired live
 *   offer goes through; the New offer form and a dormant offer say so in those words. Nothing puts
 *   an offer to dormant now (1.123.23), and one the daily run parked before 1.123.18 wakes.
 * - The primary offer's text, instructions and link live on the offer post alone now;
 *   a save never reaches Airtable, and seed() takes what the migration held for the four legacy
 *   fields as an argument, never a row it reads for itself.
 * - Two claims under the lock take two codes; the same person twice gets the same
 *   code; a manager's void frees the person; low stock mails once and re-arms on adding; the
 *   stats carry no name and no address.
 * - The cards and their handlers claim through WPCPM_Sponsor_Roster::claim() and check
 *   the offer's sponsor before anything else.
 * - An Administrator's Approve and Return decide what the item showed (1.123.23): the forms carry
 *   a fingerprint of what waits, and a form drawn before a code was held, the words changed or the
 *   state moved, or one with none, decides nothing, keeping a typed note.
 *
 * Run from the plugin root:  php bin/test-sponsor-offers.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']      = array();
$GLOBALS['autoload']  = array();
$GLOBALS['umeta']     = array();
$GLOBALS['users']     = array();
$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['next_post'] = 900;
$GLOBALS['flash']     = array();
$GLOBALS['settings']  = array();
$GLOBALS['program']   = array();

class WP_Error {
	private $c, $m, $d;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; $this->d = $d; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
	public function get_error_data() { return $this->d; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, array $roles = array(), $name = '', $email = '' ) {
		$this->ID = (int) $id; $this->roles = $roles; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = strtolower( str_replace( ' ', '', $name ) );
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_title = '', $post_name = '', $post_status = 'private', $post_author = 0;
	public function __construct( array $a ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}
class WPCPM_Test_Die extends Exception {}
class WPCPM_Test_Redirect extends Exception {}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function wp_hash( $data, $scheme = 'auth' ) { return md5( 'test-salt|' . (string) $data ); }
function wp_json_encode( $data, $options = 0, $depth = 512 ) { return json_encode( $data, $options, $depth ); }
// Core's escapes leave an entity they recognize as it is: `esc_html( 'a &gt; b' )` and
// `esc_attr( 'a &gt; b' )` are `a &gt; b`, which a browser reads back as `a > b`. Only
// `esc_textarea()` escapes the ampersand again. A stand-in that escaped every ampersand would
// show a "&gt;" drawn through `esc_attr()` as four characters and hide the box that loses words.
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $url, $protocols = null ) { return preg_match( '#^https?://#i', (string) $url ) ? $url : ''; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
// Core's two cleaners as 7.1.2 writes them, from the one copy the sponsor suites share
// (bin/stubs/cleaners.php). A stand-in on `strip_tags()` alone writes no `&lt;` and would pass every
// rule whatever it did, and `WPCPM_Typed_Text::cleaner_loses()` would refuse typings core keeps,
// "We <3 WordPress" among them.
require_once __DIR__ . '/stubs/cleaners.php';
/**
 * What WordPress does to the title of a post a person without `unfiltered_html` saves
 * (`wp_filter_kses()` on `title_save_pre`), for the text that reaches it after the cleaner: a "&"
 * that opens no entity kses knows is written `&amp;`; a "<" with the words up to the next ">" (or
 * the end) is a tag and is dropped; a ">" on its own is written `&gt;`. Compared with core 7.1.2
 * on 100,000 random texts of the kind this suite types, and the three forms of each (as typed,
 * cleaned, cleaned and handed over by `insert_text()`): 0 differ. Two things of core's are not
 * modeled. kses knows every entity name in `$allowedentitynames` and pads a numeric one (`&#62;`
 * as `&#062;`), where this knows six names and leaves numbers as they are, so a typed-out
 * `&eacute;` or `&#62;` would differ; no typing here holds one. And a tag kses allows (`<b>`,
 * `<a>`, `<q>`) would be kept by core: the cleaner has taken every such tag out before a title
 * gets here.
 */
function wpcpm_test_kses_title( $s ) {
	$s = preg_replace( '/&(?!(?:lt|gt|amp|quot|copy|nbsp|#[0-9]{1,7}|#[xX][0-9A-Fa-f]{1,6});)/', '&amp;', (string) $s );
	return preg_replace_callback( '%<[^>]*(?:>|$)|>%', function ( $m ) { return '>' === $m[0] ? '&gt;' : ''; }, $s );
}
function wp_check_invalid_utf8( $s, $strip = false ) { return (string) $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_delete_file_stub( $f ) { if ( is_file( $f ) ) { unlink( $f ); } }
function sanitize_file_name( $s ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $s ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['actions'][] = $h; }
function register_post_type( $type, $args ) { $GLOBALS['post_types'][ $type ] = $args; }
// The options as core reads them (1.123.23): $GLOBALS['opts'] is the table, and this request keeps
// its own copy of every option it has read, as core's object cache does. A value read once is
// answered from $GLOBALS['opt_cache'] until this request writes it or wp_cache_delete() drops it,
// and one found missing is remembered as missing in $GLOBALS['notoptions'], which delete_option()
// writes too and add_option() trusts: an option this request believes missing is upserted, whatever
// the table holds. Another request's write reaches the table alone (`in_another_request()`).
$GLOBALS['opt_cache']  = array();
$GLOBALS['notoptions'] = array();
function get_option( $k, $d = false ) {
	if ( isset( $GLOBALS['notoptions'][ $k ] ) ) { return $d; }
	if ( array_key_exists( $k, $GLOBALS['opt_cache'] ) ) { return $GLOBALS['opt_cache'][ $k ]; }
	if ( ! array_key_exists( $k, $GLOBALS['opts'] ) ) { $GLOBALS['notoptions'][ $k ] = true; return $d; }
	$GLOBALS['opt_cache'][ $k ] = $GLOBALS['opts'][ $k ];
	return $GLOBALS['opt_cache'][ $k ];
}
// The autoload argument is recorded, not ignored: only the add_option() branch of
// WPCPM_Sponsor_Codes::write() used to prove the pool is not autoloaded, so a later write that
// dropped the argument would have gone unseen (queued item C). No third argument means "leave
// it as it is", which is what core does. As core's, it compares with this request's copy: the
// same value writes nothing, and an option this request reads as missing is added.
function update_option( $k, $v, $a = null ) {
	$old = get_option( $k );
	if ( $v === $old ) { return false; }
	if ( false === $old ) { return add_option( $k, $v, '', null === $a ? 'yes' : $a ); }
	$GLOBALS['opts'][ $k ] = $v; $GLOBALS['opt_cache'][ $k ] = $v; unset( $GLOBALS['notoptions'][ $k ] );
	if ( 3 <= func_num_args() ) { $GLOBALS['autoload'][ $k ] = ( 'yes' === $a || true === $a ); }
	return true;
}
function delete_option( $k ) {
	$had = array_key_exists( $k, $GLOBALS['opts'] );
	unset( $GLOBALS['opts'][ $k ], $GLOBALS['autoload'][ $k ], $GLOBALS['opt_cache'][ $k ] );
	$GLOBALS['notoptions'][ $k ] = true;
	return $had;
}
// $GLOBALS['before_add'], `array( option name, step )`, is another request's step run once just
// before this one adds that option: a press landing just before this request takes a pool's lock.
function add_option( $k, $v, $deprecated = '', $autoload = 'yes' ) {
	if ( isset( $GLOBALS['before_add'] ) && $k === $GLOBALS['before_add'][0] ) { $step = $GLOBALS['before_add'][1]; unset( $GLOBALS['before_add'] ); $step(); }
	if ( ! isset( $GLOBALS['notoptions'][ $k ] ) && false !== get_option( $k ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v; $GLOBALS['opt_cache'][ $k ] = $v; unset( $GLOBALS['notoptions'][ $k ] );
	$GLOBALS['autoload'][ $k ] = ( 'yes' === $autoload || true === $autoload ); return true;
}
function wp_cache_get( $key, $group = '' ) {
	if ( 'options' !== $group ) { return false; }
	if ( 'notoptions' === $key ) { return $GLOBALS['notoptions']; }
	return array_key_exists( $key, $GLOBALS['opt_cache'] ) ? $GLOBALS['opt_cache'][ $key ] : false;
}
function wp_cache_set( $key, $value, $group = '' ) {
	if ( 'options' === $group && 'notoptions' === $key ) { $GLOBALS['notoptions'] = (array) $value; } elseif ( 'options' === $group ) { $GLOBALS['opt_cache'][ $key ] = $value; }
	return true;
}
function wp_cache_delete( $key, $group = '' ) {
	if ( 'options' === $group && 'notoptions' === $key ) { $GLOBALS['notoptions'] = array(); } elseif ( 'options' === $group ) { unset( $GLOBALS['opt_cache'][ $key ] ); }
	return true;
}
/** A press's refusal code, or 'went through' when it was not refused: a lock check that let a press through says so instead of stopping the suite. */
function err_of( $r ) { return is_wp_error( $r ) ? $r->get_error_code() : 'went through'; }
/** Run a step as another request: with a cache of its own, so its writes reach the table and this request's copies stay as they were. */
function in_another_request( callable $step ) {
	$mine                  = array( $GLOBALS['opt_cache'], $GLOBALS['notoptions'] );
	$GLOBALS['opt_cache']  = array();
	$GLOBALS['notoptions'] = array();
	$out                   = $step();
	list( $GLOBALS['opt_cache'], $GLOBALS['notoptions'] ) = $mine;
	return $out;
}
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = stripslashes_deep( $v ); return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function delete_metadata( $type, $id, $k, $v = '', $all = false ) { foreach ( $GLOBALS['umeta'] as $uid => $m ) { unset( $GLOBALS['umeta'][ $uid ][ $k ] ); } return true; }
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) { return $user; }
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
	}
	return false;
}
function get_users( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( ! isset( $args['meta_key'] ) ) { $out[] = $user; continue; }
		$value = $GLOBALS['umeta'][ (int) $id ][ $args['meta_key'] ] ?? null;
		if ( null !== $value && 0 === strcasecmp( (string) $value, (string) ( $args['meta_value'] ?? '' ) ) ) { $out[] = $user; }
	}
	return $out;
}
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) { if ( ! $GLOBALS['nonce_ok'] ) { wp_die( 'The link you followed has expired.' ); } $GLOBALS['nonce_checked'][] = $action; return true; }
function wp_die( $message = '', $code = 0 ) { throw new WPCPM_Test_Die( (string) $message . ( $code ? ' [' . (int) $code . ']' : '' ) ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . rawurlencode( (string) $value ); }
function wp_nonce_field( $a = '', $n = '', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function selected( $a, $b = true, $echo = true ) { $r = ( (string) $a === (string) $b ) ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }
function checked( $a, $b = true, $echo = true ) { $r = ( (string) $a === (string) $b ) ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function wp_date( $format, $ts = null, $zone = null ) { return gmdate( $format, null === $ts ? ( $GLOBALS['now'] ?? time() ) : (int) $ts ); }
function wp_safe_redirect( $url, $status = 302 ) { $GLOBALS['redirected'] = $url; throw new WPCPM_Test_Redirect( $url ); }
function wp_get_referer() { return $GLOBALS['referer'] ?? false; }
function nocache_headers() {}
function human_time_diff( $from, $to = 0 ) { return max( 1, (int) round( ( (int) $to - (int) $from ) / 60 ) ) . ' mins'; }
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { return 'https://example.test/logo-' . (int) $id . '.png'; }

// The post store: enough of posts, meta and get_posts() for the offers to live in.
// A title is unslashed, as core's insert unslashes it, and for a person without `unfiltered_html`
// it first meets kses (`kses_init()` adds `wp_filter_kses()` on `title_save_pre` for them only).
function wpcpm_test_title( $slashed ) { $title = stripslashes( (string) $slashed ); return current_user_can( 'unfiltered_html' ) ? $title : wpcpm_test_kses_title( $title ); }
function wp_insert_post( array $args, $wp_error = false ) {
	$id = $GLOBALS['next_post']++;
	// The words unslashed, as core's insert unslashes them; the title through wpcpm_test_title().
	$GLOBALS['posts'][ $id ] = new WP_Post( array( 'ID' => $id, 'post_type' => $args['post_type'] ?? 'post', 'post_title' => wpcpm_test_title( $args['post_title'] ?? '' ), 'post_name' => stripslashes( (string) ( $args['post_name'] ?? '' ) ), 'post_status' => $args['post_status'] ?? 'publish', 'post_author' => $args['post_author'] ?? 0 ) );
	return $id;
}
// The words unslashed, as core's update unslashes them through its insert; the title through
// wpcpm_test_title(), each title write counted.
function wp_update_post( array $args ) {
	$id = (int) $args['ID'];
	if ( isset( $GLOBALS['posts'][ $id ] ) ) {
		if ( isset( $args['post_title'] ) ) { $GLOBALS['posts'][ $id ]->post_title = wpcpm_test_title( $args['post_title'] ); $GLOBALS['title_writes'] = ( $GLOBALS['title_writes'] ?? 0 ) + 1; }
		foreach ( array( 'post_name', 'post_status' ) as $field ) { if ( isset( $args[ $field ] ) ) { $GLOBALS['posts'][ $id ]->$field = stripslashes( (string) $args[ $field ] ); } }
	}
	return $id;
}
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ''; }
// As core's, what is written is unslashed first, at any depth, so words reach it as slashed copies.
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = stripslashes_deep( $v ); return true; }
// Another request's step, run once when this one reaches the moment `$GLOBALS[ $when ]` names.
function another_request( $when, $id, $k ) {
	if ( isset( $GLOBALS[ $when ] ) && (int) $GLOBALS[ $when ][0] === (int) $id && $k === $GLOBALS[ $when ][1] ) {
		$step = $GLOBALS[ $when ][2];
		unset( $GLOBALS[ $when ] );
		$step();
	}
}
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
// $GLOBALS['before_delete'], `array( offer ID, meta key, step )`, is another request's step run once
// just before this one deletes that key of the named offer (1.123.20): a member's press landing
// between an Administrator's release of the pool's lock and the mark that release clears.
function delete_post_meta( $id, $k ) { another_request( 'before_delete', $id, $k ); unset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); return true; }
function get_posts( array $args ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) { continue; }
		// post_status can be a single status or a list, as core's own get_posts() takes it (the
		// store's post_id_of() and delete_all() pass a list, naming every status, in place of
		// 'any').
		if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] && ! in_array( $post->post_status, (array) $args['post_status'], true ) ) { continue; }
		$ok = true;
		foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			$have = array_key_exists( $clause['key'], $GLOBALS['pmeta'][ $id ] ?? array() );
			if ( isset( $clause['compare'] ) && 'EXISTS' === $clause['compare'] ) { if ( ! $have ) { $ok = false; } continue; }
			if ( ! $have || (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ] !== (string) $clause['value'] ) { $ok = false; }
		}
		// The store (1.111.0) asks a bare meta_key/meta_value pair, not meta_query.
		if ( $ok && isset( $args['meta_key'] ) && array_key_exists( 'meta_value', $args ) ) {
			$have = get_post_meta( $id, $args['meta_key'], true );
			if ( (string) $have !== (string) $args['meta_value'] ) { $ok = false; }
		}
		if ( $ok ) { $out[] = $post; }
	}
	usort( $out, static function ( $a, $b ) { return $a->ID - $b->ID; } );
	if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 ) { $out = array_slice( $out, 0, (int) $args['numberposts'] ); }
	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) { return array_map( static function ( $p ) { return $p->ID; }, $out ); }
	return $out;
}

// Enough of $wpdb for the pool lock's conditional takeover (FOFFR-3): an UPDATE that changes the
// row only while its value is still the one the caller read. $GLOBALS['db_race'] is the other
// request's write landing first, which is the case the WHERE exists for.
class WPCPM_Test_DB {
	public $options = 'wp_options';
	public function update( $table, array $data, array $where ) {
		$name = (string) $where['option_name'];
		if ( isset( $GLOBALS['db_race'] ) ) { $GLOBALS['opts'][ $name ] = $GLOBALS['db_race']; unset( $GLOBALS['db_race'] ); }
		if ( ! array_key_exists( $name, $GLOBALS['opts'] ) || (string) $GLOBALS['opts'][ $name ] !== (string) $where['option_value'] ) { return 0; }
		$GLOBALS['opts'][ $name ] = $data['option_value'];
		return 1;
	}
}
$wpdb = new WPCPM_Test_DB();

class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student'; const ROLE_MENTOR = 'wpcpm_mentor'; const ROLE_INSTITUTION = 'wpcpm_institution'; const ROLE_SPONSOR = 'wpcpm_sponsor'; const ROLE_ADMIN = 'administrator'; const CAP_MANAGE = 'wpcpm_manage_program';
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
}
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const GROUND_SYSTEM = 'system'; const EVIDENCE_INDEX = 'index'; const EVIDENCE_CACHE = 'cache';
	public static function record_sponsor( array $e ) { $GLOBALS['audit'][] = $e; return count( $GLOBALS['audit'] ); }
}
class WPCPM_Request {
	public static function posted_text( $n, $f = '' ) { return isset( $_POST[ $n ] ) && is_scalar( $_POST[ $n ] ) ? trim( sanitize_text_field( $_POST[ $n ] ) ) : $f; }
	public static function posted_key( $n, $f = '' ) { return isset( $_POST[ $n ] ) ? sanitize_key( $_POST[ $n ] ) : $f; }
	public static function posted_id( $n ) { return isset( $_POST[ $n ] ) && is_scalar( $_POST[ $n ] ) ? absint( $_POST[ $n ] ) : 0; }
	public static function posted_lines( $n, $f = '' ) { return isset( $_POST[ $n ] ) && is_scalar( $_POST[ $n ] ) ? trim( sanitize_textarea_field( $_POST[ $n ] ) ) : $f; }
	public static function posted_verbatim( $n, $f = '' ) { return isset( $_POST[ $n ] ) && is_scalar( $_POST[ $n ] ) ? trim( (string) $_POST[ $n ] ) : $f; }
	public static function posted_raw( $n, $f = '' ) { return isset( $_POST[ $n ] ) && is_scalar( $_POST[ $n ] ) ? (string) wp_unslash( $_POST[ $n ] ) : $f; }
	public static function posted_verbatim_lines( $n, $f = '' ) {
		$v = self::posted_verbatim( $n, $f );
		if ( $v === $f ) { return $f; }
		$lines = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $v ) as $line ) { $line = trim( $line ); if ( '' !== $line ) { $lines[] = $line; } }
		return implode( "\n", $lines );
	}
}
class WPCPM_Settings { public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; } }
class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) { $n = $GLOBALS['buckets'][ $key ] ?? 0; if ( $n + $amount > $limit ) { return false; } $GLOBALS['buckets'][ $key ] = $n + $amount; return true; }
	public static function count( $key, $window ) { return $GLOBALS['buckets'][ $key ] ?? 0; }
	public static function key( ...$p ) { return implode( ':', $p ); }
}
class WPCPM_Mail {
	public static function send( $user, $context, $build ) { $GLOBALS['sent'][] = array( 'user', $user->ID, $context, call_user_func( $build, $user ) ); return true; }
	public static function send_to( $email, $context, $build, $locale = '' ) { $GLOBALS['sent'][] = array( 'to', $email, $context, call_user_func( $build, null ) ); return true; }
	public static function site_name() { return 'WP Credits'; }
	public static function reply_to( $person ) { return $person instanceof WP_User ? array( 'Reply-To: ' . $person->user_email ) : array(); }
}
class WPCPM_Institutions { public static function notify_managers( $context, $build, $key = 'agreement_notify' ) { $GLOBALS['sent'][] = array( 'managers', $key, $context, call_user_func( $build, null ) ); return 1; } }
class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); }
	public static function sponsorship() { return array(); }
}
class WPCPM_Mentors_Dashboard { public static function get_mentees( $user_id ) { return array(); } }
class WPCPM_Students_Sync { public static function get_program( $user_id ) { return $GLOBALS['program'][ (int) $user_id ] ?? array(); } }
class WPCPM_Students_Dashboard { public static function page_url() { return 'https://example.test/student-report-card/'; } }
class WPCPM_Sponsors_Dashboard {
	const FLASH = 'sponsor_dashboard';
	public static function page_url() { return 'https://example.test/sponsor-dashboard/'; }
	public static function leave( $status, $card, $record = '', $detail = '' ) { $GLOBALS['left'] = array( $status, $card, $record, $detail ); throw new WPCPM_Test_Redirect( $status ); }
}
class WPCPM_Flash {
	public static function set( $channel, $value, $user_id = 0 ) { $GLOBALS['flash'][ $channel ][ $user_id ?: $GLOBALS['uid'] ] = $value; }
	public static function take( $channel, $user_id = 0 ) { $uid = $user_id ?: $GLOBALS['uid']; $v = $GLOBALS['flash'][ $channel ][ $uid ] ?? ''; unset( $GLOBALS['flash'][ $channel ][ $uid ] ); return $v; }
	public static function sweep( $stale, $user_id = 0 ) { $uid = $user_id ?: $GLOBALS['uid']; foreach ( $GLOBALS['flash'] ?? array() as $channel => $by ) { if ( is_array( $by ) && array_key_exists( $uid, $by ) && $stale( (string) $channel, $by[ $uid ] ) ) { unset( $GLOBALS['flash'][ $channel ][ $uid ] ); } } }
}
/** The real writer's contract: a BOM, one line per row, cells joined by commas, a leading formula character neutralized. */
class WPCPM_Institution_Export {
	public static function cell( $value ) { $text = is_scalar( $value ) ? (string) $value : ''; $lead = ltrim( $text ); return ( '' !== $lead && in_array( substr( $lead, 0, 1 ), array( '=', '+', '-', '@' ), true ) ) ? "'" . $text : $text; }
	public static function csv( array $matrix ) { $out = "\xEF\xBB\xBF"; foreach ( $matrix as $row ) { $out .= implode( ',', array_map( array( __CLASS__, 'cell' ), $row ) ) . "\r\n"; } return $out; }
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/stubs/posts.php';
require_once __DIR__ . '/../includes/class-wpcpm-secret.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-codes.php';
// The shell the Offers and Usage cards are drawn in.
require_once __DIR__ . '/../includes/class-wpcpm-dashboard-card.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-offers.php';
// The review of a sponsor member's offers and changes (1.123.18), and the fields that bring its
// decisions back to the Administrator Dashboard.
require_once __DIR__ . '/../includes/class-wpcpm-return.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-offer-review.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-claims.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tools.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-usage.php';

// The fixture: two Approved sponsors, a manager, a member of each, and the people who claim.
// 1.111.0: the manager is a site user id now, not a Team Members record.
$A = 'recSPONSOR0000001'; $B = 'recSPONSOR0000002';
WPCPM_Sponsors_Index::write( array(
	$A => array( 'name' => 'Mango Example', 'status' => 'Approved', 'website' => 'https://plugins.mango-example.com/', 'contact_person' => 'Rep One', 'contact_email' => 'administrator@example.test', 'product_type' => 'Plugin', 'offer' => 'One year of the premium plugin', 'instructions' => 'Enter the code at checkout.', 'more_info' => 'https://plugins.mango-example.com/wpcredits', 'coupon_link' => 'https://docs.google.com/spreadsheets/d/abc/edit', 'manager' => '1', 'mentors' => array() ),
	$B => array( 'name' => 'Cirrus Example', 'status' => 'Approved', 'website' => 'https://cirrus-example.example/', 'contact_person' => 'Rep Two', 'contact_email' => 'administrator@example.test', 'product_type' => 'Hosting', 'offer' => 'A year of hosting', 'instructions' => 'Use the link.', 'more_info' => '', 'coupon_link' => 'https://cirrus-example.example/checkout?code=WPCREDITS', 'manager' => '', 'mentors' => array() ),
), time() );
$GLOBALS['settings'] = array( 'offer_low_stock' => 10, 'student_statuses' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' ), 'past_statuses' => array( 'Graduate', 'Dropped out' ), 'tools_students' => true, 'tools_mentors' => false );
$GLOBALS['users'] = array(
	1  => new WP_User( 1, array( 'administrator' ), 'Manager', 'administrator@example.test' ),
	5  => new WP_User( 5, array( 'wpcpm_sponsor' ), 'Rep One', 'administrator@example.test' ),
	6  => new WP_User( 6, array( 'wpcpm_sponsor' ), 'Rep Two', 'administrator@example.test' ),
	20 => new WP_User( 20, array( 'wpcpm_student' ), 'Student Current', 'administrator@example.test' ),
	21 => new WP_User( 21, array( 'wpcpm_student' ), 'Student Graduate', 'administrator@example.test' ),
	22 => new WP_User( 22, array( 'wpcpm_student' ), 'Student Paused', 'administrator@example.test' ),
	23 => new WP_User( 23, array( 'wpcpm_student' ), 'Student Second', 'administrator@example.test' ),
	24 => new WP_User( 24, array( 'wpcpm_student' ), 'Student Third', 'administrator@example.test' ),
	30 => new WP_User( 30, array( 'wpcpm_mentor' ), 'Mentor One', 'administrator@example.test' ),
	31 => new WP_User( 31, array( 'wpcpm_student' ), 'Student Unsynced', 'administrator@example.test' ),
);
$GLOBALS['manage'] = array( 1 );
// The administrator holds `unfiltered_html`; a sponsor member does not, so kses meets what the member saves.
$GLOBALS['grants'] = array( 1 => array( 'unfiltered_html' ) );
$GLOBALS['umeta'][5] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $A, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['umeta'][6] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $B, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
// The shape the students sync writes: the status under `program`, with `is_past` beside it.
$GLOBALS['program'] = array( 20 => array( 'program' => 'In Sensei', 'is_past' => false ), 21 => array( 'program' => 'Graduate', 'is_past' => true ), 22 => array( 'program' => 'Paused', 'is_past' => false ), 23 => array( 'program' => 'Developer Track', 'is_past' => false ), 24 => array( 'program' => 'In Sensei', 'is_past' => false ) );
$GLOBALS['uid'] = 5; $GLOBALS['nonce_ok'] = true; $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['buckets'] = array(); $GLOBALS['now'] = gmmktime( 12, 0, 0, 9, 5, 2026 );

function post( array $fields, $action ) { $_POST = $fields; $GLOBALS['left'] = null; $GLOBALS['redirected'] = null; try { call_user_func( $action ); } catch ( WPCPM_Test_Redirect $e ) { return $GLOBALS['left'] ?? array( 'redirect', $GLOBALS['redirected'] ); } catch ( WPCPM_Test_Die $e ) { return array( 'die', $e->getMessage() ); } return array( 'fell-through' ); }
function card( $class, $record, $context ) { ob_start(); call_user_func( array( $class, 'render' ), $record, $context ); return ob_get_clean(); }
/** Every string and key of a nested array, flattened, for the privacy walks. */
function walk( $value, &$out, $key = '' ) { if ( is_array( $value ) ) { foreach ( $value as $k => $v ) { $out['keys'][] = (string) $k; walk( $v, $out, $k ); } } elseif ( is_string( $value ) ) { $out['values'][] = $value; } }

$fail = 0; $checks = 0;
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

echo "=== The post type ===\n";
ck( 'the post type name is short enough to exist', strlen( WPCPM_Sponsor_Offers::POST_TYPE ) <= 20, true );
WPCPM_Sponsor_Offers::register_post_type();
$args = $GLOBALS['post_types'][ WPCPM_Sponsor_Offers::POST_TYPE ];
ck( 'private, invisible, no REST, a capability type nothing is granted', array( $args['public'], $args['show_ui'], $args['show_in_rest'], $args['capability_type'][0], $args['map_meta_cap'] ), array( false, false, false, 'wpcpm_offer', true ) );
ck( 'an offer that is not one reads as null', WPCPM_Sponsor_Offers::read( 999999 ), null );

echo "\n=== Seeding from the index ===\n";
$a1 = WPCPM_Sponsor_Offers::seed( $A );
ck( 'the sheet link makes a pool of codes', is_int( $a1 ), true );
// 1.111.0: offer, instructions and more_info left the row's shape too (the primary offer is
// the record now); the row that comes back from the store never carries them, so seed() titles
// the draft with the sponsor's name and leaves the rest for a manager to fill in, rather than
// copying text the store no longer has anywhere to read from.
$offer = WPCPM_Sponsor_Offers::read( $a1 );
ck( 'titled with the sponsor name, the rest left for a manager to fill in', array( $offer['title'], $offer['text'], $offer['instructions'], $offer['url'] ), array( 'Mango Example', '', '', '' ) );
ck( 'a draft, primary, kind codes, students only, the default threshold', array( $offer['state'], $offer['primary'], $offer['kind'], $offer['audience'], $offer['low'] ), array( 'draft', true, 'codes', array(), 10 ) );
// The old index option, which only the migration reads, held the raw coupon_link as Airtable's
// copy of the field (written by the sponsors sync before 1.111.0, never by seed()), so it is
// excluded here on purpose: this check is for what seed() itself might copy into new storage.
$opts_without_index = $GLOBALS['opts'];
unset( $opts_without_index[ WPCPM_Sponsors_Index::OPT_NAME ] );
ck( 'the sheet address is not stored anywhere', strpos( serialize( $opts_without_index ) . serialize( $GLOBALS['pmeta'] ), 'docs.google.com' ), false );
ck( 'seeding again does nothing', WPCPM_Sponsor_Offers::seed( $A ), false );
// 1.111.0: coupon_link left the row's shape the instant it is stored (empty_row() never carries
// it, so insert()/shape() drop it on the way in, not only on the way out) - seed() cannot read
// a link back from the store to decide "shared" any more for a sponsor coming through it, so
// every newly seeded offer is a pool now, same as A's. Shared-offer behavior itself (claiming,
// the ledger, the low-stock exemption) is still covered below, on an offer built to be shared
// on purpose rather than inferred from a link the store no longer keeps.
$b1 = WPCPM_Sponsor_Offers::seed( $B );
ck( 'a checkout link is no longer read back from the store, so B also seeds a pool of codes', WPCPM_Sponsor_Offers::read( $b1 )['kind'], 'codes' );
ck( 'with nothing added yet, its pool is empty', WPCPM_Sponsor_Codes::counts( $b1 )['available'], 0 );
// A manager turns B's offer into a shared one from the Offers card (kind_is_fixed() only
// refuses this once the pool holds something, and nothing has been added yet): the rest of
// this suite's shared-offer coverage is built on that offer, not on seed()'s now-impossible
// detection.
update_post_meta( $b1, WPCPM_Sponsor_Offers::META_KIND, WPCPM_Sponsor_Offers::KIND_SHARED );
$D = 'recSPONSOR0000004';
WPCPM_Sponsors_Index::write( array_merge( WPCPM_Sponsors_Index::rows(), array( $D => array( 'name' => 'Drive Sheet', 'status' => 'Approved', 'website' => '', 'contact_person' => 'Rep Four', 'contact_email' => 'administrator@example.test', 'product_type' => 'Plugin', 'offer' => 'A sheet of codes', 'instructions' => '', 'more_info' => '', 'coupon_link' => 'https://drive.google.com/open?id=x', 'manager' => '', 'mentors' => array() ) ) ), time() );
$d_seed = WPCPM_Sponsor_Offers::seed( $D );
ck( 'a coupon sheet shared as a Drive link is the sheet too, so it seeds a pool and no shared code', array( WPCPM_Sponsor_Offers::read( $d_seed )['kind'], WPCPM_Sponsor_Codes::shared( $d_seed ) ), array( 'codes', '' ) );
ck( 'an unknown sponsor cannot be seeded', WPCPM_Sponsor_Offers::seed( 'recNOTINDEXED0001' )->get_error_code(), 'wpcpm_offer_sponsor' );
ck( 'offers_of() lists by sponsor', array( array_keys( WPCPM_Sponsor_Offers::offers_of( $A ) ), array_keys( WPCPM_Sponsor_Offers::offers_of( $B ) ) ), array( array( $a1 ), array( $b1 ) ) );
ck( 'find() with the wrong sponsor is null', array( WPCPM_Sponsor_Offers::find( $a1, $B ), WPCPM_Sponsor_Offers::find( $a1, $A )['id'] ), array( null, $a1 ) );

// 1.111.0: the store's row never carries offer, instructions, more_info or coupon_link at all
// (they left empty_row() the same release), unlike the four fixtures above, which still set them
// because they were written before that shape changed. seed() must read a row like this one
// without a notice and hand back an empty draft rather than the base's old text.
$E = 'recSPONSOR0000005';
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $E, 'name' => 'No Legacy Fields', 'status' => 'Approved' ) ) );
// The bare cast a pre-isset() seed() used to do (`(string) $row['offer']`) also lands on '' for
// a missing key - PHP warns "Undefined array key" and then treats the null as '' - so the three
// empty strings below hold either way and prove nothing about the isset() guard on their own.
// Capturing every warning raised during the call is what actually tells the two apart.
$e_warnings = array();
set_error_handler(
	static function ( $errno, $errstr ) use ( &$e_warnings ) {
		$e_warnings[] = $errstr;
		return true;
	}
);
$e_seed = WPCPM_Sponsor_Offers::seed( $E );
restore_error_handler();
$e_offer = WPCPM_Sponsor_Offers::read( $e_seed );
ck( 'seed() tolerates a row without the four legacy keys, raising no warning',
	array( is_int( $e_seed ), $e_offer['text'], $e_offer['instructions'], $e_offer['url'], $e_warnings ),
	array( true, '', '', '', array() )
);

echo "\n=== Seeding with legacy values ===\n";
// The migration (class-wpcpm-sponsors-migration.php, seed_offer()) is the one caller left that
// ever has these four: it reads them off the row it held before the store dropped them, and
// hands them to seed() as $legacy, never as a row seed() goes and reads for itself. This is the
// only way left to prove seed() still applies today's rules to them, sheet-hosts rule included.
$F        = 'recSPONSOR0000006';
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $F, 'name' => 'Mango Legacy', 'status' => 'Approved' ) ) );
$f_legacy = array( 'offer' => 'One year of the premium plugin', 'instructions' => 'Enter the code at checkout.', 'more_info' => 'https://plugins.mango-example.com/wpcredits', 'coupon_link' => 'https://docs.google.com/spreadsheets/d/abc/edit' );
$f1       = WPCPM_Sponsor_Offers::seed( $F, $f_legacy );
$f_offer  = WPCPM_Sponsor_Offers::read( $f1 );
ck( 'titled with the sponsor name, the text, the instructions and the link taken from $legacy', array( $f_offer['title'], $f_offer['text'], $f_offer['instructions'], $f_offer['url'] ), array( 'Mango Legacy', 'One year of the premium plugin', 'Enter the code at checkout.', 'https://plugins.mango-example.com/wpcredits' ) );
ck( 'a draft, primary, and the sheet link is still exempt: a pool of codes, not shared', array( $f_offer['state'], $f_offer['primary'], $f_offer['kind'] ), array( 'draft', true, 'codes' ) );
ck( 'the sheet address is not stored anywhere', strpos( serialize( $GLOBALS['pmeta'] ) . serialize( $GLOBALS['opts'] ), 'docs.google.com' ), false );
ck( 'seeding again does nothing, legacy values or not', WPCPM_Sponsor_Offers::seed( $F, $f_legacy ), false );
$G  = 'recSPONSOR0000007';
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $G, 'name' => 'Cirrus Legacy', 'status' => 'Approved' ) ) );
$g1 = WPCPM_Sponsor_Offers::seed( $G, array( 'offer' => 'A year of hosting', 'instructions' => 'Use the link.', 'more_info' => '', 'coupon_link' => 'https://cirrus-example.example/checkout?code=WPCREDITS2' ) );
ck( 'a checkout link that is not the sheet still makes a shared offer', WPCPM_Sponsor_Offers::read( $g1 )['kind'], 'shared' );
ck( 'holding that link as the shared code', WPCPM_Sponsor_Codes::shared( $g1 ), 'https://cirrus-example.example/checkout?code=WPCREDITS2' );
ck( 'and not in the clear', strpos( serialize( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::option_name( $g1 ) ] ), 'WPCREDITS2' ), false );

echo "\n=== Cleaning a posted offer ===\n";
$raw = array( 'title' => ' Premium plugin ', 'text' => 'A year', 'instructions' => 'Checkout', 'url' => 'plugins.mango-example.com/x', 'kind' => 'codes', 'audience' => array( 'mentors', 'students', 'bogus' ), 'low' => '2000', 'expires' => '2026-12-31' );
$clean = WPCPM_Sponsor_Offers::clean( $raw );
ck( 'a title is trimmed, a URL completed, the audience filtered, the threshold clamped, the day kept as a string', array( $clean['ok'], $clean['fields']['title'], $clean['fields']['url'], $clean['fields']['audience'], $clean['fields']['low'], $clean['fields']['expires'] ), array( true, 'Premium plugin', 'https://plugins.mango-example.com/x', array( 'mentors' ), 1000, '2026-12-31' ) );
ck( 'no title, no offer', WPCPM_Sponsor_Offers::clean( array( 'title' => '  ' ) )['reason'], 'title' );
ck( 'a link with a user part is refused', WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'url' => 'https://name@host.test' ) )['reason'], 'url' );
ck( 'a kind that is not one is refused', WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'kind' => 'gift' ) )['reason'], 'kind' );
ck( 'an impossible day is refused', WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'expires' => '2026-02-30' ) )['reason'], 'expires' );
ck( 'and so is a day in another format', WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'expires' => '31/12/2026' ) )['reason'], 'expires' );
ck( 'an empty threshold takes the setting', WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'low' => '' ) )['fields']['low'], 10 );
ck( 'a title, a text and instructions at their limits are cleaned whole; one character more is refused, not cut, and the reason names the field', array( WPCPM_Sponsor_Offers::clean( array( 'title' => str_repeat( 'a', 120 ) ) )['ok'], WPCPM_Sponsor_Offers::clean( array( 'title' => str_repeat( 'a', 121 ) ) )['reason'], WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'text' => str_repeat( 'a', 500 ) ) )['ok'], WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'text' => str_repeat( 'a', 501 ) ) )['reason'], WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'instructions' => str_repeat( 'a', 4000 ) ) )['ok'], WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'instructions' => str_repeat( 'a', 4001 ) ) )['reason'] ), array( true, 'title_long', true, 'text_long', true, 'instructions_long' ) );

echo "\n=== The pool ===\n";
$parsed = WPCPM_Sponsor_Codes::parse( "CODE-1\r\n  CODE-2  \n\nCODE-3,unused column\nhttps://shop.example/buy?a=1,b=2\n" . str_repeat( 'x', 201 ) . "\nCODE-1\n" );
ck( 'lines are trimmed, blank lines skipped, a CSV row gives its first column, a URL with a comma is kept whole', $parsed['codes'], array( 'CODE-1', 'CODE-2', 'CODE-3', 'https://shop.example/buy?a=1,b=2' ) );
ck( 'the long line and the repeat are named by line number', $parsed['errors'], array( 'Line 6 is longer than 200 characters.', 'Line 7 repeats line 1.' ) );
// FOFFR-1: the lines are counted before a single one is parsed, so a paste too long ever to be
// an offer costs one sentence and no work at all, rather than one sentence per line.
$too_many = WPCPM_Sponsor_Codes::parse( str_repeat( "a\n", 6000 ) );
ck( 'a paste with more lines than an offer holds is refused by the count, unparsed', array( $too_many['errors'], $too_many['codes'] ), array( array( 'This list has 6000 codes; an offer takes at most 5000.' ), array() ) );
// A blank line is not a code, and an uploaded .txt of codes separated by blank lines is an
// ordinary shape. Counting every line refused a file of 5,000 codes with the sentence "This
// list has 9999 lines", which was true and useless: the ceiling is on codes (the whole-branch
// review of 1.99.0). The textarea path never saw this, because posted_verbatim_lines() drops
// blank lines before parse() is reached; only the upload path carries them through.
$spaced = '';
for ( $i = 1; $i <= WPCPM_Sponsor_Codes::CODES_MAX; $i++ ) {
	$spaced .= sprintf( "WPCE-%04d\n\n  \n", $i );
}
$spaced = WPCPM_Sponsor_Codes::parse( "\n" . $spaced );
ck( 'a full pool with a blank and a whitespace-only line after every code is taken whole', array( $spaced['errors'], count( $spaced['codes'] ) ), array( array(), WPCPM_Sponsor_Codes::CODES_MAX ) );
$over = WPCPM_Sponsor_Codes::parse( "\n" . str_repeat( "WPCE-X\n\n", WPCPM_Sponsor_Codes::CODES_MAX + 1 ) );
ck( 'and one code past it is refused by the count, unparsed', array( $over['errors'], $over['codes'] ), array( array( 'This list has 5001 codes; an offer takes at most 5000.' ), array() ) );
// FOFFR-5: the escape argument is given, so a quoted cell parses the same on PHP 7.4 and 8.4.
ck( 'a CSV cell with a backslash before its quote parses the same on every PHP', WPCPM_Sponsor_Codes::parse( '"WP\"20",note' )['codes'], array( 'WP\20"' ) );
$refused = WPCPM_Sponsor_Codes::add( $a1, "CODE-1\nCODE-1" );
ck( 'a paste with a fault adds nothing and says which line', array( $refused->get_error_code(), WPCPM_Sponsor_Codes::counts( $a1 )['total'], $refused->get_error_data() ), array( 'wpcpm_codes_refused', 0, array( 'Line 2 repeats line 1.' ) ) );
ck( 'an empty paste is its own answer', err_of( WPCPM_Sponsor_Codes::add( $a1, "\n\n" ) ), 'wpcpm_codes_none' );
ck( 'twenty codes go in', WPCPM_Sponsor_Codes::add( $a1, implode( "\n", array_map( static function ( $i ) { return sprintf( 'WPCE-%04d', $i ); }, range( 1, 20 ) ) ) ), 20 );
$pool_option = $GLOBALS['opts'][ WPCPM_Sponsor_Codes::option_name( $a1 ) ];
ck( 'the option is not autoloaded', $GLOBALS['autoload'][ WPCPM_Sponsor_Codes::option_name( $a1 ) ], false );
WPCPM_Sponsor_Codes::set_shared( $a1, '' );
ck( 'and stays that way after a write that goes through update_option() rather than add_option()', $GLOBALS['autoload'][ WPCPM_Sponsor_Codes::option_name( $a1 ) ], false );
ck( 'and holds no code in the clear, and no bare hash of one', array( strpos( serialize( $pool_option ), 'WPCE-0001' ), strpos( serialize( $pool_option ), hash( 'sha256', 'WPCE-0001' ) ) ), array( false, false ) );
ck( 'a code already in the offer is refused by line number', WPCPM_Sponsor_Codes::add( $a1, "NEW-1\nWPCE-0007" )->get_error_data(), array( 'Line 2 is already in this offer.' ) );
ck( 'so nothing was added', WPCPM_Sponsor_Codes::counts( $a1 ), array( 'available' => 20, 'claimed' => 0, 'void' => 0, 'total' => 20 ) );
// FOFFR-1: and the sentences are capped, so a hundred repeats of one code cost eleven strings.
$repeats   = WPCPM_Sponsor_Codes::add( $a1, implode( "\n", array_fill( 0, 100, 'SAME-1' ) ) );
$sentences = $repeats->get_error_data();
ck( 'ninety-nine bad lines make ten sentences and a closing count, and add nothing', array( count( $sentences ), $sentences[0], end( $sentences ), WPCPM_Sponsor_Codes::counts( $a1 )['total'] ), array( 11, 'Line 2 repeats line 1.', 'and 89 more lines have problems.', 20 ) );
// The closing sentence once used sprintf() for a count, so exactly eleven faulty
// lines (ten named, one more) printed the ungrammatical "and 1 more lines have problems"; _n()
// must choose the singular there, and the plural must still read right at every other count.
$eleven = WPCPM_Sponsor_Codes::add( $a1, implode( "\n", array_fill( 0, 12, 'ELEVEN-1' ) ) )->get_error_data();
ck( 'exactly eleven faulty lines close with the singular sentence', end( $eleven ), 'and 1 more line has a problem.' );
$twelve = WPCPM_Sponsor_Codes::add( $a1, implode( "\n", array_fill( 0, 13, 'TWELVE-1' ) ) )->get_error_data();
ck( 'and twelve faulty lines close with the plural sentence', end( $twelve ), 'and 2 more lines have problems.' );
$big = WPCPM_Sponsor_Codes::read( $a1 );
$big['codes'] = array_fill( 0, 4999, array( 's' => 'x', 'h' => 'h', 'st' => 'available', 'by' => 0, 'at' => 0 ) );
WPCPM_Sponsor_Codes::write( 777, $big );
ck( 'the five-thousandth code is the last one an offer takes', array( err_of( WPCPM_Sponsor_Codes::add( 777, "ONE\nTWO" ) ), WPCPM_Sponsor_Codes::add( 777, 'ONE' ) ), array( 'wpcpm_codes_max', 1 ) );
WPCPM_Sponsor_Codes::delete( 777 );
ck( 'take() hands out the first available code and records who', array( WPCPM_Sponsor_Codes::take( $a1, 20 ), WPCPM_Sponsor_Codes::read( $a1 )['codes'][0]['st'], WPCPM_Sponsor_Codes::read( $a1 )['codes'][0]['by'] ), array( 0, 'claimed', 20 ) );
ck( 'the next take is the next code', WPCPM_Sponsor_Codes::take( $a1, 23 ), 1 );
ck( 'code_at() unseals', WPCPM_Sponsor_Codes::code_at( $a1, 1 ), 'WPCE-0002' );
ck( 'and the ledger has both', array_map( static function ( $c ) { return array( $c['u'], $c['i'] ); }, WPCPM_Sponsor_Codes::claims( $a1 ) ), array( array( 20, 0 ), array( 23, 1 ) ) );
ck( 'a manager voiding a claimed code flags its ledger row', array( WPCPM_Sponsor_Codes::void_index( $a1, 1 ), WPCPM_Sponsor_Codes::read( $a1 )['codes'][1]['st'], WPCPM_Sponsor_Codes::claims( $a1 )[1]['v'] > 0 ), array( true, 'void', true ) );
ck( 'and cannot void what is not claimed', WPCPM_Sponsor_Codes::void_index( $a1, 2 ), false );
ck( 'the sponsor voids what nobody holds', array( WPCPM_Sponsor_Codes::void_unclaimed( $a1 ), WPCPM_Sponsor_Codes::counts( $a1 ) ), array( 18, array( 'available' => 0, 'claimed' => 1, 'void' => 19, 'total' => 20 ) ) );
ck( 'take() on an empty pool says so', err_of( WPCPM_Sponsor_Codes::take( $a1, 22 ) ), 'wpcpm_codes_empty' );
WPCPM_Sponsor_Codes::add( $a1, "MORE-1\nMORE-2\nMORE-3" );

echo "\n=== The state machine ===\n";
$empty = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Empty pool', 'kind' => 'codes', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
ck( 'a pool cannot go live empty', WPCPM_Sponsor_Offers::set_state( $empty, 'live' )->get_error_code(), 'wpcpm_offer_empty' );
ck( 'nor can a shared offer with nothing to share', array( WPCPM_Sponsor_Codes::set_shared( $b1, '' ), WPCPM_Sponsor_Offers::set_state( $b1, 'live' )->get_error_code() ), array( true, 'wpcpm_offer_empty' ) );
WPCPM_Sponsor_Codes::set_shared( $b1, 'https://cirrus-example.example/checkout?code=WPCREDITS' );
ck( 'holding that link as the shared code', WPCPM_Sponsor_Codes::shared( $b1 ), 'https://cirrus-example.example/checkout?code=WPCREDITS' );
ck( 'and not in the clear', strpos( serialize( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::option_name( $b1 ) ] ), 'WPCREDITS' ), false );
ck( 'with codes it goes live', WPCPM_Sponsor_Offers::set_state( $a1, 'live' ), true );
ck( 'a state that is not one is refused', WPCPM_Sponsor_Offers::set_state( $a1, 'gone' )->get_error_code(), 'wpcpm_offer_state' );
ck( 'live cannot go back to draft', WPCPM_Sponsor_Offers::set_state( $a1, 'draft' )->get_error_code(), 'wpcpm_offer_transition' );
ck( 'live pauses, paused resumes, live ends', array( WPCPM_Sponsor_Offers::set_state( $a1, 'paused' ), WPCPM_Sponsor_Offers::set_state( $a1, 'live' ), WPCPM_Sponsor_Offers::set_state( $empty, 'ended' ) ), array( true, true, true ) );
ck( 'ended is final', WPCPM_Sponsor_Offers::set_state( $empty, 'live' )->get_error_code(), 'wpcpm_offer_transition' );
ck( 'an offer that does not exist cannot change state', WPCPM_Sponsor_Offers::set_state( 424242, 'live' )->get_error_code(), 'wpcpm_offer_missing' );
ck( 'the kind is fixed once the pool holds anything', array( WPCPM_Sponsor_Offers::kind_is_fixed( WPCPM_Sponsor_Offers::read( $a1 ) ), WPCPM_Sponsor_Offers::kind_is_fixed( WPCPM_Sponsor_Offers::read( $empty ) ), WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'kind' => 'shared' ), WPCPM_Sponsor_Offers::read( $a1 ) )['reason'] ), array( true, false, 'kind' ) );
$live = WPCPM_Sponsor_Offers::read( $a1 );
$live['expires'] = '2026-09-05';
ck( 'the last day is inclusive, compared as a string', array( WPCPM_Sponsor_Offers::is_live( $live, '2026-09-05' ), WPCPM_Sponsor_Offers::is_live( $live, '2026-09-06' ), WPCPM_Sponsor_Offers::is_live( $live, '2026-08-31' ) ), array( true, false, true ) );
$live['expires'] = '';
ck( 'no last day, live for good', WPCPM_Sponsor_Offers::is_live( $live, '2030-01-01' ), true );
$live['state'] = 'paused';
ck( 'paused is not live whatever the day', WPCPM_Sponsor_Offers::is_live( $live ), false );
ck( 'live() lists the live ones', array_keys( WPCPM_Sponsor_Offers::live() ), array( $a1 ) );
ck( 'and all() lists every offer', array_keys( WPCPM_Sponsor_Offers::all() ), array( $a1, $b1, $d_seed, $e_seed, $f1, $g1, $empty ) );

echo "\n=== No tier caps live offers, and the dormant state ===\n";
ck(
	'states() has six states, Waiting for review among them, and a sponsor\'s only move out of dormant is to end it',
	array(
		WPCPM_Sponsor_Offers::states(),
		WPCPM_Sponsor_Offers::transitions()[ WPCPM_Sponsor_Offers::STATE_DORMANT ],
		array_filter(
			WPCPM_Sponsor_Offers::transitions(),
			static function ( $moves ) {
				return in_array( WPCPM_Sponsor_Offers::STATE_DORMANT, $moves, true );
			}
		),
	),
	array(
		array( 'draft', 'review', 'live', 'paused', 'ended', 'dormant' ),
		array( 'ended' ),
		array(),
	)
);

// The contexts the card is rendered with for the rest of this section.
$a_ctx = array( 'can_manage' => false, 'open' => '', 'viewer' => $GLOBALS['users'][5] );
$b_ctx = array( 'can_manage' => false, 'open' => '', 'viewer' => $GLOBALS['users'][6] );

/**
 * Park a live offer as the daily run did while a tier capped live offers, before 1.123.18: nothing
 * in the plugin puts an offer to dormant now (1.123.23), but offers it parked then may still be.
 */
function park_offer( $offer_id ) { update_post_meta( $offer_id, WPCPM_Sponsor_Offers::META_STATE, WPCPM_Sponsor_Offers::STATE_DORMANT ); }

// Throwaway offers under B, dedicated to proving the fifth state: $dorm_main is created, brought
// live, then parked as dormant, and left dormant for the card-render checks further down; the
// other three prove wake() touches nothing it should not.
$dorm_main = WPCPM_Sponsor_Offers::create( $B, array( 'title' => 'Dormant test offer', 'kind' => 'shared', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
WPCPM_Sponsor_Codes::set_shared( $dorm_main, 'DORM-MAIN-CODE' );
WPCPM_Sponsor_Offers::set_state( $dorm_main, 'live' );
park_offer( $dorm_main );
ck( 'read() keeps the dormant state instead of reading it as a draft', WPCPM_Sponsor_Offers::read( $dorm_main )['state'], 'dormant' );
ck( 'a sponsor cannot resume a dormant offer: the move is refused as a transition', WPCPM_Sponsor_Offers::set_state( $dorm_main, 'live' )->get_error_code(), 'wpcpm_offer_transition' );

$dorm_draft = WPCPM_Sponsor_Offers::create( $B, array( 'title' => 'Draft, never live', 'kind' => 'shared', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
$dorm_paused = WPCPM_Sponsor_Offers::create( $B, array( 'title' => 'Paused offer', 'kind' => 'shared', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
WPCPM_Sponsor_Codes::set_shared( $dorm_paused, 'DORM-PAUSED-CODE' );
WPCPM_Sponsor_Offers::set_state( $dorm_paused, 'live' );
WPCPM_Sponsor_Offers::set_state( $dorm_paused, 'paused' );
// dormant is never a target anywhere in transitions(), whatever the offer's own state - not just
// from live, which the refusal above already covers on its own (review finding, test gap).
ck(
	'a sponsor cannot post dormant: set_state() refuses it as a transition from every state',
	array(
		WPCPM_Sponsor_Offers::set_state( $a1, 'dormant' )->get_error_code(),
		WPCPM_Sponsor_Offers::set_state( $dorm_draft, 'dormant' )->get_error_code(),
		WPCPM_Sponsor_Offers::set_state( $dorm_paused, 'dormant' )->get_error_code(),
	),
	array( 'wpcpm_offer_transition', 'wpcpm_offer_transition', 'wpcpm_offer_transition' )
);

$dorm_live2 = WPCPM_Sponsor_Offers::create( $B, array( 'title' => 'Second live, for wake()', 'kind' => 'shared', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
WPCPM_Sponsor_Codes::set_shared( $dorm_live2, 'DORM-LIVE2-CODE' );
WPCPM_Sponsor_Offers::set_state( $dorm_live2, 'live' );
park_offer( $dorm_live2 );
ck(
	'wake() brings a dormant offer back to live, the daily run\'s move, and touches no other state',
	array(
		WPCPM_Sponsor_Offers::wake( $dorm_draft ),
		WPCPM_Sponsor_Offers::read( $dorm_draft )['state'],
		WPCPM_Sponsor_Offers::wake( $dorm_paused ),
		WPCPM_Sponsor_Offers::read( $dorm_paused )['state'],
		WPCPM_Sponsor_Offers::read( $dorm_live2 )['state'],
		WPCPM_Sponsor_Offers::wake( $dorm_live2 ),
		WPCPM_Sponsor_Offers::read( $dorm_live2 )['state'],
		WPCPM_Sponsor_Offers::read( $dorm_live2 )['event']['what'],
	),
	array( false, 'draft', false, 'paused', 'dormant', true, 'live', 'woken' )
);
ck( 'state_label() knows Dormant', WPCPM_Sponsor_Offers::state_label( WPCPM_Sponsor_Offers::STATE_DORMANT ), 'Dormant' );

// The switch is still off here (the suite's settings never set sponsor_tiers_live by default):
// nothing about the tiers reaches a sponsor, a dormant offer's own reason included, so $dorm_main
// reads as held back by the program, naming no tier.
$card_b_off = card( 'WPCPM_Sponsor_Offers', $B, $b_ctx );
ck(
	'with the switch off a dormant offer reads that the program holds it back, and names no tier',
	array(
		false !== strpos( $card_b_off, 'Dormant: held back by the program. You can end it.' ),
		false !== strpos( $card_b_off, 'your tier' ),
	),
	array( true, false )
);

// The switch is off here still: B, tier '', brings a second offer live too, while the tiers are
// unannounced as once they are.
$cap_b2 = WPCPM_Sponsor_Offers::create( $B, array( 'title' => 'Second offer, cap check', 'kind' => 'shared', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
WPCPM_Sponsor_Codes::set_shared( $cap_b2, 'CAP-B2-CODE' );
$first_on  = WPCPM_Sponsor_Offers::set_state( $b1, 'live' );
$second_on = WPCPM_Sponsor_Offers::set_state( $cap_b2, 'live' );
ck(
	'with the switch off a Friend switches a second offer on',
	array(
		WPCPM_Sponsors_Index::row( $B )['tier'],
		$first_on,
		$second_on,
	),
	array( '', true, true )
);

// The New offer form's note: nothing while the switch is off; once it is on, the rule the partner
// page gives, the same at every tier, naming none.
$note_off = card( 'WPCPM_Sponsor_Offers', $B, $b_ctx );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$note_on = card( 'WPCPM_Sponsor_Offers', $B, $b_ctx );
ck(
	'the New offer form says offers are separate from the tiers while the switch is on, and nothing while it is off',
	array(
		false !== strpos( $note_off, 'separate from the tiers' ),
		false !== strpos( $note_on, '<p class="wpcpm-student__note">Offers are separate from the tiers: your company may have any number of live offers, whatever its tier.</p>' ),
		false !== strpos( $note_on, 'Your tier, ' ),
	),
	array( false, true, false )
);

// With the switch on, a Supporter switches a second offer on beside the first: the partner page
// offers products at any level, separately from the levels, and the handler lands the move.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'supporter' ) );
$cap_a2 = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Second live attempt', 'kind' => 'shared', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
WPCPM_Sponsor_Codes::set_shared( $cap_a2, 'CAP-A2-CODE' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $cap_a2, 'wpcpm_state' => 'live' ), array( 'WPCPM_Sponsor_Offers', 'handle_state' ) );
$approved_a2 = WPCPM_Sponsor_Offers::approve( $cap_a2 );
ck(
	'with the switch on a Supporter\'s second offer is sent for review through the card\'s press and, approved, goes live beside the first: no tier stops it',
	array(
		$r[0],
		$approved_a2,
		WPCPM_Sponsor_Offers::is_live( WPCPM_Sponsor_Offers::read( $cap_a2 ) ),
		WPCPM_Sponsor_Offers::is_live( WPCPM_Sponsor_Offers::read( $a1 ) ),
	),
	array( 'offer-sent', true, true, true )
);
// No tier caps live offers, so the code that counted a tier's slots and its sentences are gone
// (1.123.18): nothing counts the slots before a switch or after it, and no refusal names a cap.
// And what was left of it went in 1.123.23: the move that put a live offer to dormant when a cap
// stopped covering it, which nothing calls since the daily run stopped, and the two arguments of
// live_count() that only the cap's count passed, the offer to leave out and the day.
$offers_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-offers.php' );
ck( 'no cap on live offers is left to count or to word: cap_reason(), the recount, its revert, the cap\'s sentences, the move to dormant and live_count()\'s arguments for the count are gone', array(
	method_exists( 'WPCPM_Sponsor_Offers', 'cap_reason' ),
	method_exists( 'WPCPM_Sponsor_Offers', 'reopen' ),
	array_key_exists( 'offer-cap', WPCPM_Sponsor_Offers::messages() ),
	preg_match_all( '/wpcpm_offer_cap|offer_cap\(|includes no live offers|live offers? at a time|includes one live offer|includes %2\$d live offer|Your tier, %/', $offers_src ),
	method_exists( 'WPCPM_Sponsor_Offers', 'set_dormant' ),
	( new ReflectionMethod( 'WPCPM_Sponsor_Offers', 'live_count' ) )->getNumberOfParameters(),
	preg_match_all( '/\bcap\b|\bslots?\b/', $offers_src ),
), array( false, false, false, 0, false, 1, 0 ) );

// A Friend switches an offer on too: the switch is on, B's tier is still unset, and B already has
// three live, the one woken above among them. Ended right after, so B carries a past offer for the card's "Active" vs. "Ended and
// expired" split further down.
$cap_b3 = WPCPM_Sponsor_Offers::create( $B, array( 'title' => 'Third attempt', 'kind' => 'shared', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
WPCPM_Sponsor_Codes::set_shared( $cap_b3, 'CAP-B3-CODE' );
ck(
	'a Friend switches a fourth offer on while the switch is on',
	array( WPCPM_Sponsor_Offers::set_state( $cap_b3, 'live' ), WPCPM_Sponsor_Offers::read( $cap_b3 )['state'], WPCPM_Sponsor_Offers::live_count( $B ) ),
	array( true, 'live', 4 )
);
WPCPM_Sponsor_Offers::set_state( $cap_b3, 'ended' );

// A Champion switches on as many: a third beside the two, as any tier would.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion' ) );
$cap_a3   = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Third live attempt', 'kind' => 'shared', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
WPCPM_Sponsor_Codes::set_shared( $cap_a3, 'CAP-A3-CODE' );
$third_live = WPCPM_Sponsor_Offers::set_state( $cap_a3, 'live' );
ck(
	'a Champion switches on any number',
	array(
		true,
		$third_live,
		WPCPM_Sponsor_Offers::is_live( WPCPM_Sponsor_Offers::read( $a1 ) ),
		WPCPM_Sponsor_Offers::is_live( WPCPM_Sponsor_Offers::read( $cap_a2 ) ),
		WPCPM_Sponsor_Offers::is_live( WPCPM_Sponsor_Offers::read( $cap_a3 ) ),
	),
	array( true, true, true, true, true )
);
ck( 'live_count() counts one sponsor\'s live offers today', WPCPM_Sponsor_Offers::live_count( $A ), 3 );

// An offer past its last day is still, technically, in state live - but is_live() says no: with the
// other two ended, A is a Supporter again with a1 alone, whose last day is yesterday, and a fresh
// offer goes live beside it.
WPCPM_Sponsor_Offers::set_state( $cap_a2, 'ended' );
WPCPM_Sponsor_Offers::set_state( $cap_a3, 'ended' );
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'supporter' ) );
WPCPM_Sponsor_Offers::save( $a1, array( 'expires' => '2026-09-04' ) );
$cap_a4 = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Past the expired one', 'kind' => 'shared', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
WPCPM_Sponsor_Codes::set_shared( $cap_a4, 'CAP-A4-CODE' );
ck(
	'an offer past its last day is not live, and a fresh offer goes live beside it',
	array(
		WPCPM_Sponsor_Offers::is_live( WPCPM_Sponsor_Offers::read( $a1 ) ),
		WPCPM_Sponsor_Offers::set_state( $cap_a4, 'live' ),
		WPCPM_Sponsor_Offers::is_live( WPCPM_Sponsor_Offers::read( $cap_a4 ) ),
	),
	array( false, true, true )
);

// Editing a live-but-expired offer's last day back into the open, with the switch on and cap_a4
// live beside it: no tier caps live offers, so the save goes through.
$title_a1 = WPCPM_Sponsor_Offers::read( $a1 )['title'];
$r        = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $a1, 'wpcpm_title' => $title_a1, 'wpcpm_expires' => '' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck(
	'moving an expired live offer\'s last day back into the open goes through, another offer live beside it',
	array( $r[0], WPCPM_Sponsor_Offers::read( $a1 )['expires'], WPCPM_Sponsor_Offers::live_count( $A ) ),
	array( 'offer-saved', '', 2 )
);
// cap_a4 was only ever in play for the two checks above; ended now, so A is back to a1 alone,
// live and unexpired, which is what nothing after this point assumes otherwise.
WPCPM_Sponsor_Offers::set_state( $cap_a4, 'ended' );

// No tier caps live offers, so nothing puts an offer to dormant now, but an offer the daily run
// parked under the caps before 1.123.18 stays dormant until the next daily run,
// WPCPM_Sponsor_Tiers_Run, wakes it - the sentence must say so, not print "0 live offers".
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => 'champion' ) );
$dorm_champion = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Dormant under an unlimited tier', 'kind' => 'shared', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
WPCPM_Sponsor_Codes::set_shared( $dorm_champion, 'DORM-CHAMPION-CODE' );
WPCPM_Sponsor_Offers::set_state( $dorm_champion, 'live' );
park_offer( $dorm_champion );
$card_a_champion = card( 'WPCPM_Sponsor_Offers', $A, $a_ctx );
ck(
	'a dormant offer reads that offers are separate from the tiers and that the next daily run brings it back',
	false !== strpos( $card_a_champion, 'Dormant: offers are separate from the tiers now, so it resumes by itself on the next daily run. You can end it.' ),
	true
);

// The New offer form's note, self-contained: nothing while the switch is off, and the one rule at a
// Supporter and a Champion alike.
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
WPCPM_Sponsors_Index::patch( $B, array( 'tier' => 'supporter' ) );
$shape_none = card( 'WPCPM_Sponsor_Offers', $B, $b_ctx );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$shape_one = card( 'WPCPM_Sponsor_Offers', $B, $b_ctx );
WPCPM_Sponsors_Index::patch( $B, array( 'tier' => 'champion' ) );
$shape_any = card( 'WPCPM_Sponsor_Offers', $B, $b_ctx );
$offers_rule = 'Offers are separate from the tiers: your company may have any number of live offers, whatever its tier.';
ck(
	'the New offer form\'s note is the one rule at every tier, and nothing while the switch is off',
	array(
		false !== strpos( $shape_none, $offers_rule ),
		false !== strpos( $shape_one, $offers_rule ),
		false !== strpos( $shape_any, $offers_rule ),
		strpos( $shape_one . $shape_any, 'at a time' ),
	),
	array( false, true, true, false )
);

// The card, for the dormant offer minted at the top of this section: it sits between the Active
// offers heading and the Ended and expired offers heading (cap_b3 is what puts that second heading
// on the card at all), its sentence says the next daily run brings it back, and its only move is to
// end it.
WPCPM_Sponsors_Index::patch( $B, array( 'tier' => 'supporter' ) );
$card_b          = card( 'WPCPM_Sponsor_Offers', $B, $b_ctx );
$active_pos      = strpos( $card_b, '<h4 class="wpcpm-sponsor__subheading">Active offers</h4>' );
$dorm_pos        = strpos( $card_b, 'id="wpcpm-offer-' . $dorm_main . '"' );
$past_pos        = strpos( $card_b, '<h4 class="wpcpm-sponsor__subheading">Ended and expired offers</h4>' );
$dorm_form_start = strpos( $card_b, 'id="wpcpm-offer-state-' . $dorm_main . '"' );
$dorm_form_end   = strpos( $card_b, '</form>', $dorm_form_start );
$dorm_form       = substr( $card_b, $dorm_form_start, $dorm_form_end - $dorm_form_start );
ck(
	'the card draws a dormant offer under Active offers with its sentence and End this offer alone',
	array(
		false !== strpos( $card_b, 'wpcpm-offer wpcpm-offer--dormant' ),
		false !== strpos( $card_b, 'Dormant: offers are separate from the tiers now, so it resumes by itself on the next daily run. You can end it.' ),
		false !== $active_pos && false !== $dorm_pos && false !== $past_pos && $active_pos < $dorm_pos && $dorm_pos < $past_pos,
		false !== strpos( $dorm_form, 'value="live"' ),
		false !== strpos( $dorm_form, 'value="paused"' ),
		false !== strpos( $dorm_form, 'value="ended"' ),
	),
	array( true, true, true, false, false, true )
);

// Cleanup: every setting and every sponsor's tier return to how the rest of the file finds them.
// The throwaway offers made only for this section under $A go with them, so offers_of( $A )
// still counts what the rest of the file expects; B keeps its offers (nothing later counts them
// exactly), but the extra ones this section brought live are ended first.
WPCPM_Sponsors_Index::patch( $A, array( 'tier' => '' ) );
WPCPM_Sponsors_Index::patch( $B, array( 'tier' => '' ) );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
foreach ( array( $cap_a2, $cap_a3, $cap_a4, $dorm_champion ) as $throwaway ) {
	WPCPM_Sponsor_Codes::delete( $throwaway );
	wp_delete_post( $throwaway, true );
}
foreach ( array( $dorm_live2, $cap_b2 ) as $extra_live ) {
	WPCPM_Sponsor_Offers::set_state( $extra_live, 'ended' );
}

echo "\n=== A minted key is accepted everywhere a record id was ===\n";
$SPO = 'spoAAAA0000000001';
WPCPM_Sponsors_Index::write( array_merge( WPCPM_Sponsors_Index::rows(), array( $SPO => array( 'name' => 'Minted Sponsor', 'status' => 'Approved', 'website' => '', 'contact_person' => 'Rep Five', 'contact_email' => 'administrator@example.test', 'product_type' => 'Plugin', 'offer' => 'A minted offer', 'instructions' => '', 'more_info' => '', 'coupon_link' => '', 'manager' => '', 'mentors' => array() ) ) ), time() );
$spo_seed = WPCPM_Sponsor_Offers::seed( $SPO );
ck( 'offers_of() lists a spo sponsor', array_keys( WPCPM_Sponsor_Offers::offers_of( $SPO ) ), array( $spo_seed ) );

echo "\n=== No more mirror ===\n";
// mirror() is gone: the primary offer's text, instructions and link live on the offer post
// alone now. WPCPM_Sponsor_Offers::mirror() no longer exists to call, so the proof is what is
// left: save() writes only the offer post, never the index's now-absent offer/more_info keys.
WPCPM_Sponsor_Offers::save( $a1, array( 'text' => 'Two years of the premium plugin', 'url' => 'https://plugins.mango-example.com/wpcredits2' ) );
ck( 'save() writes the offer post and nothing else', WPCPM_Sponsor_Offers::read( $a1 )['text'], 'Two years of the premium plugin' );
ck( 'the index carries no offer or more_info key to have mirrored into', array( array_key_exists( 'offer', WPCPM_Sponsors_Index::row( $A ) ), array_key_exists( 'more_info', WPCPM_Sponsors_Index::row( $A ) ) ), array( false, false ) );
ck( 'and mirror() itself is gone', method_exists( 'WPCPM_Sponsor_Offers', 'mirror' ), false );

echo "\n=== Claims: under the lock ===\n";
// What the checks above left in the live offer: index 0 claimed by 20 (ledger only), index 1 void, 2 to 19 void, MORE-1 to MORE-3 available at 20 to 22.
$GLOBALS['sent'] = array();
$first = WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][22] );
ck( 'a claim takes the first available code and says it is new', array( $first['new'], $first['code'], $first['index'] ), array( true, 'MORE-1', 20 ) );
ck( 'and records it on the person', WPCPM_Sponsor_Claims::claims_of( 22 )[ $a1 ]['i'], 20 );
$again = WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][22] );
ck( 'the same person again gets the same code back, and nothing is taken', array( $again['new'], $again['code'], WPCPM_Sponsor_Codes::counts( $a1 )['available'] ), array( false, 'MORE-1', 2 ) );
ck( 'code_for() reads it from their own record', WPCPM_Sponsor_Claims::code_for( 22, WPCPM_Sponsor_Offers::read( $a1 ) ), 'MORE-1' );
// FSUIT-6: the one ownership check the function has. Written so that replacing the guard with
// a default index of 0 fails here, which is what the whole battery used to miss.
ck( 'and nothing for somebody who never claimed', WPCPM_Sponsor_Claims::code_for( 23, WPCPM_Sponsor_Offers::read( $a1 ) ), '' );
$GLOBALS['opts'][ WPCPM_Sponsor_Claims::LOCK_PREFIX . $a1 ] = time();
ck( 'a held lock turns a new claimant away with "reload", and takes nothing', array( err_of( WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][23] ) ), WPCPM_Sponsor_Codes::counts( $a1 )['available'] ), array( 'wpcpm_claim_busy', 2 ) );
$GLOBALS['opts'][ WPCPM_Sponsor_Claims::LOCK_PREFIX . $a1 ] = time() - WPCPM_Sponsor_Claims::LOCK_TIMEOUT - 1;
$stale = WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][23] );
ck( 'a lock older than the timeout belonged to a dead request and is taken over', array( $stale['new'], $stale['code'] ), array( true, 'MORE-2' ) );
ck( 'the lock is released after the claim', isset( $GLOBALS['opts'][ WPCPM_Sponsor_Claims::LOCK_PREFIX . $a1 ] ), false );
ck( 'a person may_claim() refuses gets the one refusal, and nothing moves', array( err_of( WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][21] ) ), WPCPM_Sponsor_Codes::counts( $a1 )['available'] ), array( 'wpcpm_claim_refused', 1 ) );
ck( 'an offer that is not one is refused the same way', err_of( WPCPM_Sponsor_Claims::claim( 424242, $GLOBALS['users'][20] ) ), 'wpcpm_claim_refused' );
$last = WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][24] );
ck( 'the last code goes', $last['code'], 'MORE-3' );
ck( 'and the next claimant meets an empty pool', err_of( WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][20] ) ), 'wpcpm_claim_empty' );

echo "\n=== Low stock ===\n";
$low = static function () { return array_values( array_filter( $GLOBALS['sent'], static function ( $m ) { return 'offer-low-stock' === $m[2]; } ) ); };
ck( 'the claim that took the pool under its threshold mailed the sponsor account and the manager, once each', array_map( static function ( $m ) { return array( $m[0], $m[1] ); }, $low() ), array( array( 'user', 5 ), array( 'user', 1 ) ) );
ck( 'and stamped the offer', WPCPM_Sponsor_Offers::read( $a1 )['low_sent'] > 0, true );
$body = $low()[0][3]['body'];
ck( 'the mail names the offer, the count and the Offers card, and no code', array( false !== strpos( $body, 'Mango Example' ), false !== strpos( $body, '#wpcpm-sponsor-offers' ), false !== strpos( $body, WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A ), strpos( $body, 'MORE-' ) ), array( true, true, true, false ) );
ck( 'adding codes through the offer re-arms the warning', array( WPCPM_Sponsor_Offers::add_codes( $a1, "LATE-1\nLATE-2" ), WPCPM_Sponsor_Offers::read( $a1 )['low_sent'] ), array( 2, 0 ) );
WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][20] );
ck( 'so the next crossing mails again', count( $low() ), 4 );

echo "\n=== Problems and voids ===\n";
$GLOBALS['sent']  = array();
$GLOBALS['audit'] = array();
$report = WPCPM_Sponsor_Claims::report_problem( $a1, $GLOBALS['users'][22] );
ck( 'a claimant reports a problem: the manager is mailed the name, the offer and the last four characters, never the code', array( $report['mailed'], $GLOBALS['sent'][0][2], false !== strpos( $GLOBALS['sent'][0][3]['body'], 'Student Paused' ), false !== strpos( $GLOBALS['sent'][0][3]['body'], 'RE-1' ), strpos( $GLOBALS['sent'][0][3]['body'], 'MORE-1' ) ), array( 1, 'claim-problem', true, true, false ) );
ck( 'with a Reply-To at the claimant', $GLOBALS['sent'][0][3]['headers'], array( 'Reply-To: administrator@example.test' ) );
ck( 'and the way to the Sponsors screen at its Offers and codes tab, where the offers and their claimants are', false !== strpos( $GLOBALS['sent'][0][3]['body'], 'are on the Sponsors screen: https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=offers' . "\n" ), true );
ck( 'and it is logged on the sponsor with the claimant as the subject', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['subject'], end( $GLOBALS['audit'] )['ground'] ), array( 'claim_problem', '22', 'system' ) );
ck( 'somebody without a claim cannot report one', err_of( WPCPM_Sponsor_Claims::report_problem( $a1, $GLOBALS['users'][21] ) ), 'wpcpm_problem_refused' );
WPCPM_Sponsor_Claims::report_problem( $a1, $GLOBALS['users'][22] );
WPCPM_Sponsor_Claims::report_problem( $a1, $GLOBALS['users'][22] );
ck( 'three a day, then the ceiling', err_of( WPCPM_Sponsor_Claims::report_problem( $a1, $GLOBALS['users'][22] ) ), 'wpcpm_problem_limit' );
ck( 'a manager voids a claim: the code is void, the person is free, the log says so', array( WPCPM_Sponsor_Claims::void_claim( $a1, 22, 1 ), WPCPM_Sponsor_Claims::has_claimed( 22, $a1 ), WPCPM_Sponsor_Codes::read( $a1 )['codes'][20]['st'], end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['ground'] ), array( true, false, 'void', 'claim_voided', 'manager' ) );
$reclaim = WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][22] );
ck( 'and they may claim again, getting a different code', array( $reclaim['new'], $reclaim['code'] ), array( true, 'LATE-2' ) );
ck( 'voiding somebody with no claim does nothing', WPCPM_Sponsor_Claims::void_claim( $a1, 21, 1 ), false );

echo "\n=== A shared offer ===\n";
WPCPM_Sponsor_Offers::set_state( $b1, 'live' );
$s1 = WPCPM_Sponsor_Claims::claim( $b1, $GLOBALS['users'][20] );
$s2 = WPCPM_Sponsor_Claims::claim( $b1, $GLOBALS['users'][23] );
ck( 'everyone gets the same link, and each claim is in the ledger once', array( $s1['code'], $s2['code'], $s1['index'], count( WPCPM_Sponsor_Codes::claims( $b1 ) ) ), array( 'https://cirrus-example.example/checkout?code=WPCREDITS', 'https://cirrus-example.example/checkout?code=WPCREDITS', WPCPM_Sponsor_Codes::SHARED_INDEX, 2 ) );
ck( 'no low-stock mail for a shared offer', count( array_filter( $GLOBALS['sent'], static function ( $m ) { return 'offer-low-stock' === $m[2]; } ) ), 0 );
ck( 'a manager frees a person from a shared claim too', array( WPCPM_Sponsor_Claims::void_claim( $b1, 20, 1 ), WPCPM_Sponsor_Codes::claims( $b1 )[0]['v'] > 0, WPCPM_Sponsor_Claims::has_claimed( 20, $b1 ) ), array( true, true, false ) );
ck( 'count_for_user() counts what stands', array( WPCPM_Sponsor_Claims::count_for_user( 20 ), WPCPM_Sponsor_Claims::count_for_user( 22 ), WPCPM_Sponsor_Claims::count_for_user( 21 ) ), array( 1, 1, 0 ) );

echo "\n=== Usage: numbers, never names ===\n";
WPCPM_Sponsor_Offers::create( $A, array( 'title' => '=SUM(1)', 'kind' => 'shared', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
$stats = WPCPM_Sponsor_Claims::stats( $A );
ck( 'twelve months ending with the current one', array( count( $stats['months'] ), end( $stats['months'] ) ), array( 12, gmdate( 'Y-m' ) ) );
$a_stats = $stats['offers'][ $a1 ];
ck( 'the live offer: claims that stand, this month, and the pool by state', array( $a_stats['total'], $a_stats['month'], $a_stats['available'], $a_stats['claimed'], $a_stats['void'] ), array( 5, 5, 0, 5, 20 ) );
ck( 'the series puts them in this month', $a_stats['series'][ gmdate( 'Y-m' ) ], 5 );
ck( 'and the totals add the offers up', array( $stats['totals']['total'], $stats['totals']['available'], $stats['totals']['claimed'], $stats['totals']['void'] ), array( 5, 0, 5, 20 ) );
// The general form the annual sponsor report reads: the months a caller hands over, not the
// trailing twelve. A fixed list of two months long gone, then the same two with the current month
// last. The series is keyed by the list as given, `month` is the list's last month, and `total`
// stays every claim that stands, whatever the list; stats() is this over month_keys().
$fixed_past = WPCPM_Sponsor_Claims::stats_for_months( $A, array( '2020-01', '2020-02' ) );
$fixed_now  = WPCPM_Sponsor_Claims::stats_for_months( $A, array( '2020-01', '2020-02', gmdate( 'Y-m' ) ) );
ck( 'stats_for_months() counts over the months it is handed: the list as given, the series keyed by it, its last month the month',
	array(
		$fixed_past['months'],
		$fixed_past['offers'][ $a1 ]['series'],
		array( $fixed_past['offers'][ $a1 ]['month'], $fixed_past['offers'][ $a1 ]['total'], $fixed_past['totals']['month'], $fixed_past['totals']['total'] ),
		array_keys( $fixed_now['offers'][ $a1 ]['series'] ),
		array( $fixed_now['offers'][ $a1 ]['series'][ gmdate( 'Y-m' ) ], $fixed_now['offers'][ $a1 ]['month'], $fixed_now['offers'][ $a1 ]['total'] ),
		WPCPM_Sponsor_Claims::stats_for_months( $A, WPCPM_Sponsor_Claims::month_keys() ) === $stats,
	),
	array(
		array( '2020-01', '2020-02' ),
		array( '2020-01' => 0, '2020-02' => 0 ),
		array( 0, 5, 0, 5 ),
		array( '2020-01', '2020-02', gmdate( 'Y-m' ) ),
		array( 5, 5, 5 ),
		true,
	)
);
$flat = array( 'keys' => array(), 'values' => array() );
walk( $stats, $flat );
ck( 'no value in the stats is an address', array_values( array_filter( $flat['values'], 'is_email' ) ), array() );
ck( 'and no key names a person', array_values( array_intersect( array_map( 'strtolower', $flat['keys'] ), array( 'name', 'email', 'user', 'u', 'by', 'display_name', 'user_email', 'claimant', 'claimants', 'user_id' ) ) ), array() );
$csv = WPCPM_Sponsor_Claims::csv( $stats );
ck( 'the CSV has a header, a row per offer and a totals row', substr_count( $csv, "\r\n" ), 5 );
ck( 'carries the titles and the month columns, and neutralises a title that starts like a formula', array( false !== strpos( $csv, 'Mango Example' ), false !== strpos( $csv, gmdate( 'Y-m' ) ), false !== strpos( $csv, "'=SUM(1)" ) ), array( true, true, true ) );
ck( 'and no address', preg_match( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/', $csv ), 0 );

echo "\n=== The claimants, for a manager ===\n";
$who = WPCPM_Sponsor_Claims::claimants( $a1 );
ck( 'the list names the people whose claims stand, oldest first, with the last four characters', array( count( $who ), $who[0]['name'], $who[0]['email'], $who[0]['last4'], $who[0]['index'] ), array( 5, 'Student Current', 'administrator@example.test', '0001', 0 ) );
ck( 'a voided claim is not in it', in_array( 20, array_map( static function ( $c ) { return $c['index']; }, $who ), true ), false );

echo "\n=== The pool's lock guards every rewrite ===\n";
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ] = time();
$before = WPCPM_Sponsor_Codes::read( $a1 );
ck( 'a paste while the pool is locked is told to try again, and adds nothing', array( err_of( WPCPM_Sponsor_Codes::add( $a1, 'Z-1' ) ), WPCPM_Sponsor_Codes::read( $a1 ) === $before ), array( 'wpcpm_codes_busy', true ) );
ck( 'so is a void of the unclaimed codes', err_of( WPCPM_Sponsor_Codes::void_unclaimed( $a1 ) ), 'wpcpm_codes_busy' );
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $b1 ] = time();
ck( 'and a change of the shared code', array( err_of( WPCPM_Sponsor_Codes::set_shared( $b1, 'OTHER' ) ), WPCPM_Sponsor_Codes::shared( $b1 ) ), array( 'wpcpm_codes_busy', 'https://cirrus-example.example/checkout?code=WPCREDITS' ) );
ck( 'and a manager\'s void of a claim, which leaves the claim standing', array( err_of( WPCPM_Sponsor_Claims::void_claim( $b1, 23, 1 ) ), WPCPM_Sponsor_Claims::has_claimed( 23, $b1 ) ), array( 'wpcpm_codes_busy', true ) );
unset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ], $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $b1 ] );
ck( 'with the lock gone the paste goes in and the lock is released after it', array( WPCPM_Sponsor_Codes::add( $a1, 'Z-1' ), isset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ] ) ), array( 1, false ) );
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ] = time() - WPCPM_Sponsor_Codes::LOCK_TIMEOUT - 1;
ck( 'a stale lock is taken over by a void', is_int( WPCPM_Sponsor_Codes::void_unclaimed( $a1 ) ), true );
ck( 'and released', isset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ] ), false );
// FOFFR-3: the takeover is conditional on the stamp it read. Two requests that read the same
// stale stamp are the race the docblock did not name: with the winner's write landing first,
// the loser's UPDATE matches no row and it does not get the lock.
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ] = time() - WPCPM_Sponsor_Codes::LOCK_TIMEOUT - 1;
$GLOBALS['db_race'] = time();
ck( 'a stale lock two requests read at once is taken by one of them only', WPCPM_Sponsor_Codes::lock( $a1 ), false );
ck( 'and the winner\'s fresh stamp is left alone', ( time() - (int) $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ] ) < WPCPM_Sponsor_Codes::LOCK_TIMEOUT, true );
unset( $GLOBALS['db_race'], $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ] );

echo "\n=== The Offers card ===\n";
$GLOBALS['uid'] = 5;
$context = array( 'can_manage' => false, 'open' => '', 'viewer' => $GLOBALS['users'][5] );
$html    = card( 'WPCPM_Sponsor_Offers', $A, $context );
ck( 'the card is the canonical section around a disclosure, with the count of offers', array( false !== strpos( $html, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-offers" class="wpcpm-group wpcpm-group__disclosure"' ), false !== strpos( $html, '<span class="wpcpm-group__count">3</span>' ) ), array( true, true ) );
// What the company offers is live state, so the card is open whatever a flash says (the design the
// owner approved on 27 September 2026). Its row says how many offers are live today and how many
// claims stand this month, which the At a glance strip no longer shows. New offer follows its fold, a link
// to the card, whose body opens on the form for a new offer: the stylesheets hide it while the card is
// open, as they hide every card's action then, so it shows once the sponsor folds the card. A claim
// from an earlier month counts in all, on the Usage card's row, and not this month: one of the five
// is moved two months back for these two rows.
$backdated = array();
foreach ( WPCPM_Sponsor_Offers::offers_of( $A ) as $offer ) {
	$pool_key = WPCPM_Sponsor_Codes::option_name( $offer['id'] );
	if ( empty( $backdated ) && ! empty( $GLOBALS['opts'][ $pool_key ]['claims'] ) ) {
		$backdated = array( $pool_key, $GLOBALS['opts'][ $pool_key ] );
		$first     = array_keys( $GLOBALS['opts'][ $pool_key ]['claims'] )[0];
		$GLOBALS['opts'][ $pool_key ]['claims'][ $first ]['at'] = time() - 62 * DAY_IN_SECONDS;
		// Written to the table for the fixture: this request reads it there, not its own copy.
		wp_cache_delete( $pool_key, 'options' );
	}
}
$offers_row = card( 'WPCPM_Sponsor_Offers', $A, $context );
$usage_row  = card( 'WPCPM_Sponsor_Usage', $A, $context );
$flashed    = card( 'WPCPM_Sponsor_Usage', $A, array_merge( $context, array( 'open' => 'usage' ) ) );
if ( ! empty( $backdated ) ) {
	$GLOBALS['opts'][ $backdated[0] ] = $backdated[1];
	wp_cache_delete( $backdated[0], 'options' );
}
$new_offer = '</div></div></div></details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-offers">New offer<span class="screen-reader-text"> for the program&#039;s students and mentors</span></a></p></section>';
ck( 'the Offers card is open, its row the offers live and the claims this month, its body opening on the form for a new offer, and New offer after the fold, naming what it makes for a screen reader and hidden by the stylesheets while the card is open; the Usage card folds until a flash names it, its row every claim that stands, with no action', array(
	0 === strpos( $offers_row, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-offers" class="wpcpm-group wpcpm-group__disclosure" open><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Offers and codes <span class="wpcpm-group__count">3</span></h3><span class="wpcpm-sponsor__state">1 live, 4 claims this month</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary><div class="wpcpm-group__body"><div class="wpcpm-offers__cols"><div class="wpcpm-offers__col wpcpm-offers__col--new">' ),
	substr( $offers_row, -strlen( $new_offer ) ) === $new_offer,
	1 === substr_count( $offers_row, 'wpcpm-sponsor__row-action' ),
	0 === strpos( $usage_row, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-usage" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title"><span id="wpcpm-sponsor-usage-title">Usage</span> <span class="wpcpm-group__count">5</span></h3><span class="wpcpm-sponsor__state">5 claims in all</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>' ),
	substr( $usage_row, -strlen( '</form></div></details></section>' ) ),
	false !== strpos( $flashed, '<details id="wpcpm-sponsor-usage" class="wpcpm-group wpcpm-group__disclosure" open>' ),
	count( $backdated ),
), array( true, true, true, true, '</form></div></details></section>', true, 2 ) );
ck( 'each offer has an edit form with its own nonce, the state buttons it may press, and the codes box for a pool', array( false !== strpos( $html, 'nonce-' . WPCPM_Sponsor_Offers::ACTION_SAVE . '_' . $a1 ), false !== strpos( $html, 'name="wpcpm_state" value="paused"' ), false !== strpos( $html, 'name="wpcpm_codes"' ), false !== strpos( $html, 'nonce-' . WPCPM_Sponsor_Offers::ACTION_CODES_ADD . '_' . $a1 ) ), array( true, true, true, true ) );
ck( 'the counts are drawn, and the pool never shows a code', array( false !== strpos( $html, '0 available' ), strpos( $html, 'LATE-' ), strpos( $html, 'WPCE-' ) ), array( true, false, false ) );
ck( 'a live pool below its threshold warns', false !== strpos( $html, 'wpcpm-offer__warning' ), true );
ck( 'the kind is fixed once the pool holds anything: the live pool shows it fixed, the two empty offers and the new form still choose, with two radios each', array( false !== strpos( $html, 'wpcpm-offer__kind-fixed' ), substr_count( $html, 'type="radio" name="wpcpm_kind"' ), strpos( $html, '<select id="wpcpm-offer-new-kind"' ) ), array( true, 6, false ) );
ck( 'the kind-fixed block\'s note is a plain note on the kind shown, not a description spliced onto it', array( false !== strpos( $html, '<span class="wpcpm-student__note">Fixed once the offer holds codes or claims.</span>' ), strpos( $html, '<strong aria-describedby' ) ), array( true, false ) );
ck( 'the new-offer form takes a file, shows the shared field for a shared offer only and the codes box for a pool only', array( false !== strpos( $html, 'wpcpm-offer__form--new" enctype="multipart/form-data"' ), false !== strpos( $html, 'data-wpcpm-shows-for="shared"' ), false !== strpos( $html, 'data-wpcpm-shows-for="codes"' ), false !== strpos( $html, 'name="wpcpm_codes_file"' ), false !== strpos( $html, 'accept=".txt,.csv,text/plain,text/csv"' ) ), array( true, true, true, true, true ) );
ck( 'the codes box is described by the line rules, the file field by the file rule', array( preg_match( '/<textarea id="wpcpm-offer-new-codes" name="wpcpm_codes"[^>]*aria-describedby="wpcpm-offer-new-codes-hint"/', $html ) === 1, preg_match( '/<span class="wpcpm-student__note" id="wpcpm-offer-new-codes-hint">One code per line/', $html ) === 1, preg_match( '/name="wpcpm_codes_file"[^>]*aria-describedby="wpcpm-offer-new-codes-file-hint"/', $html ) === 1, preg_match( '/<span class="wpcpm-student__note" id="wpcpm-offer-new-codes-file-hint">A \.txt or \.csv file/', $html ) === 1 ), array( true, true, true, true ) );
ck( 'and the codes box of an existing pool takes a file too', substr_count( $html, 'name="wpcpm_codes_file"' ) >= 2, true );
ck( 'and the codes box says a voided code cannot come back to the same offer', substr_count( $html, 'A voided code cannot be added to the same offer again.' ) >= 1, true );
ck( 'and the new-offer form is at the end with the "new" nonce and a kind to choose', array( false !== strpos( $html, 'nonce-' . WPCPM_Sponsor_Offers::ACTION_SAVE . '_new' ), substr_count( $html, 'name="wpcpm_kind"' ) >= 1 ), array( true, true ) );
ck( 'the one required field of the offer form says so in its label', preg_match( '/<label for="wpcpm-offer-new-title">Title <span class="wpcpm-field__required">Required<\/span><\/label><input type="text" id="wpcpm-offer-new-title"[^>]* required/', $html ) === 1, true );
$html_b = card( 'WPCPM_Sponsor_Offers', $B, $context );
ck( 'a shared offer shows its own link in the form and no codes box of its own: the one codes box on the page is the new-offer form\'s', array( false !== strpos( $html_b, 'value="https://cirrus-example.example/checkout?code=WPCREDITS"' ), substr_count( $html_b, 'name="wpcpm_codes"' ), false !== strpos( $html_b, 'id="wpcpm-offer-new-codes"' ) ), array( true, 1, true ) );

echo "\n=== The Offers card: saving ===\n";
$GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $a1, 'wpcpm_title' => 'Premium plugin, one year', 'wpcpm_text' => 'One year free', 'wpcpm_instructions' => 'Enter the code at checkout.', 'wpcpm_url' => 'https://plugins.mango-example.com/wpcredits2', 'wpcpm_audience' => array( 'mentors' ), 'wpcpm_low' => '5', 'wpcpm_expires' => '' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a member saves a live offer and lands on the Offers card, the changed words waiting for review (1.123.18)', array( $r[0], $r[1], $r[2] ), array( 'offer-changes-waiting', 'offers', $A ) );
$saved = WPCPM_Sponsor_Offers::read( $a1 );
ck( 'the audience and the warning are written at once, and the title and the link wait beside the offer', array( $saved['audience'], $saved['low'], $saved['proposed']['title'], $saved['proposed']['url'], 'Premium plugin, one year' === $saved['title'] ), array( array( 'mentors' ), 5, 'Premium plugin, one year', 'https://plugins.mango-example.com/wpcredits2', false ) );
WPCPM_Sponsor_Offers::approve( $a1 );
$saved = WPCPM_Sponsor_Offers::read( $a1 );
ck( 'approved, the fields were written', array( $saved['title'], $saved['text'], $saved['instructions'], $saved['url'], $saved['audience'], $saved['low'], $saved['proposed'] ), array( 'Premium plugin, one year', 'One year free', 'Enter the code at checkout.', 'https://plugins.mango-example.com/wpcredits2', array( 'mentors' ), 5, array() ) );
ck( 'a save never mirrors: the audit datum is gone', array_key_exists( 'mirrored', end( $GLOBALS['audit'] )['data'] ), false );
ck( 'and logged with the field names, never the values', array( end( $GLOBALS['audit'] )['kind'], in_array( 'title', end( $GLOBALS['audit'] )['data']['fields'], true ), strpos( serialize( end( $GLOBALS['audit'] ) ), 'One year free' ) ), array( 'offer_saved', true, false ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $a1, 'wpcpm_title' => '', 'wpcpm_text' => 'x' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a save without a title is rejected, with the reason as the detail', array( $r[0], $r[3] ), array( 'offer-rejected', 'Give the offer a title.' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $b1, 'wpcpm_title' => 'Theirs' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'an offer of another sponsor is the one refusal, before anything is read', array( $r[0], WPCPM_Sponsor_Offers::read( $b1 )['title'] ), array( 'refused', 'Cirrus Example' ) );
$GLOBALS['uid'] = 1;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 0, 'wpcpm_title' => 'Second offer', 'wpcpm_kind' => 'shared', 'wpcpm_shared' => 'TEAM-2026', 'wpcpm_text' => '', 'wpcpm_instructions' => '', 'wpcpm_url' => '', 'wpcpm_low' => '', 'wpcpm_expires' => '2026-12-31' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
$created = WPCPM_Sponsor_Offers::offers_of( $A );
$new_id  = max( array_keys( $created ) );
ck( 'a manager creates a second offer through the switcher, not primary, with its shared code sealed', array( $r[0], $created[ $new_id ]['primary'], $created[ $new_id ]['kind'], WPCPM_Sponsor_Codes::shared( $new_id ) ), array( 'offer-created', false, 'shared', 'TEAM-2026' ) );
$offers_before = count( WPCPM_Sponsor_Offers::offers_of( $A ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 0, 'wpcpm_title' => 'Too long', 'wpcpm_kind' => 'shared', 'wpcpm_shared' => str_repeat( 'x', WPCPM_Sponsor_Codes::LINE_MAX + 1 ), 'wpcpm_text' => '', 'wpcpm_instructions' => '', 'wpcpm_url' => '', 'wpcpm_low' => '', 'wpcpm_expires' => '' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a new shared offer with a code that is too long is refused before anything is created', array( $r[0], $r[3], count( WPCPM_Sponsor_Offers::offers_of( $A ) ) ), array( 'offer-rejected', 'The shared code or link is longer than 200 characters.', $offers_before ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $new_id, 'wpcpm_title' => 'Second offer', 'wpcpm_kind' => 'shared', 'wpcpm_shared' => str_repeat( 'y', WPCPM_Sponsor_Codes::LINE_MAX + 1 ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'and an existing one keeps its title and its code when the new code is too long', array( $r[0], WPCPM_Sponsor_Offers::read( $new_id )['title'], WPCPM_Sponsor_Codes::shared( $new_id ) ), array( 'offer-rejected', 'Second offer', 'TEAM-2026' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $new_id, 'wpcpm_title' => 'Second offer', 'wpcpm_kind' => 'shared', 'wpcpm_shared' => 'TEAM%2F2026' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a shared code is stored exactly as it was typed, percent-encoding and all', array( $r[0], WPCPM_Sponsor_Codes::shared( $new_id ) ), array( 'offer-saved', 'TEAM%2F2026' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 0, 'wpcpm_title' => 'Pool with codes', 'wpcpm_kind' => 'codes', 'wpcpm_codes' => "N-1\nN-2", 'wpcpm_text' => '', 'wpcpm_instructions' => '', 'wpcpm_url' => '', 'wpcpm_low' => '', 'wpcpm_expires' => '' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
$with_codes = max( array_keys( WPCPM_Sponsor_Offers::offers_of( $A ) ) );
ck( 'a pool can be created with its codes in one step, and the flash says how many went in', array( $r[0], $r[3], WPCPM_Sponsor_Codes::counts( $with_codes )['available'] ), array( 'offer-created', '2 codes added.', 2 ) );
// FOFFR-1: the paste is parsed before the offer post is created, so a refused list leaves no
// orphan draft behind for the sponsor to find and not be able to explain.
$offers_now = count( WPCPM_Sponsor_Offers::offers_of( $A ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 0, 'wpcpm_title' => 'Pool with a bad paste', 'wpcpm_kind' => 'codes', 'wpcpm_codes' => "R-1\nR-1", 'wpcpm_text' => '', 'wpcpm_instructions' => '', 'wpcpm_url' => '', 'wpcpm_low' => '', 'wpcpm_expires' => '' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a bad paste at creation names the line and creates no offer at all', array( $r[0], $r[3], count( WPCPM_Sponsor_Offers::offers_of( $A ) ) ), array( 'offer-rejected', 'Line 2 repeats line 1.', $offers_now ) );
$_FILES = array( 'wpcpm_codes_file' => array( 'name' => 'codes.csv', 'type' => 'text/csv', 'tmp_name' => wpcpm_test_tempnam( 'wpcpm' ), 'error' => UPLOAD_ERR_OK, 'size' => 12 ) );
$upload = WPCPM_Sponsor_Offers::uploaded_codes_text();
ck( 'a file PHP did not receive as an upload is refused, not read', array( is_wp_error( $upload ), $upload->get_error_code() ), array( true, 'wpcpm_codes_upload' ) );
$_FILES['wpcpm_codes_file']['name'] = 'codes.pdf';
ck( 'only .txt and .csv are taken', WPCPM_Sponsor_Offers::uploaded_codes_text()->get_error_code(), 'wpcpm_codes_upload' );
$_FILES['wpcpm_codes_file']['name'] = 'codes.txt'; $_FILES['wpcpm_codes_file']['size'] = WPCPM_Sponsor_Offers::UPLOAD_MAX + 1;
ck( 'and nothing over a megabyte', WPCPM_Sponsor_Offers::uploaded_codes_text()->get_error_code(), 'wpcpm_codes_upload' );
$_FILES['wpcpm_codes_file']['error'] = UPLOAD_ERR_NO_FILE;
ck( 'no file chosen is simply no text', WPCPM_Sponsor_Offers::uploaded_codes_text(), '' );
wp_delete_file_stub( $_FILES['wpcpm_codes_file']['tmp_name'] );
$_FILES = array();
ck( 'an uploaded file is cleaned like a paste: the BOM and control characters go, the percent signs stay', WPCPM_Sponsor_Offers::clean_upload_text( "\xEF\xBB\xBFA-1\r\nB%202\x07\n" ), "A-1\r\nB%202\n" );
$GLOBALS['uid'] = 5;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $a1, 'wpcpm_title' => 'Premium plugin, one year', 'wpcpm_text' => 'Two years free' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a save never mirrors: an ordinary save is all that happens now, whatever the base is doing, the member\'s change waiting for review', array( $r[0], WPCPM_Sponsor_Offers::read( $a1 )['proposed']['text'] ), array( 'offer-changes-waiting', 'Two years free' ) );
WPCPM_Sponsor_Offers::approve( $a1 );
ck( 'offer-mirror-failed is not a status any more', isset( WPCPM_Sponsor_Offers::messages()['offer-mirror-failed'] ), false );

echo "\n=== The Offers card: states and codes ===\n";
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $new_id, 'wpcpm_state' => 'live' ), array( 'WPCPM_Sponsor_Offers', 'handle_state' ) );
ck( 'a shared offer with its code goes live', array( $r[0], WPCPM_Sponsor_Offers::read( $new_id )['state'], end( $GLOBALS['audit'] )['kind'] ), array( 'offer-state-saved', 'live', 'offer_state' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $new_id, 'wpcpm_state' => 'draft' ), array( 'WPCPM_Sponsor_Offers', 'handle_state' ) );
ck( 'a move the machine refuses is said', $r[0], 'offer-transition' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $new_id, 'wpcpm_title' => 'Second offer', 'wpcpm_kind' => 'shared', 'wpcpm_shared' => '' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a live shared offer cannot have its code taken away by a save', array( $r[0], $r[3], WPCPM_Sponsor_Codes::shared( $new_id ) ), array( 'offer-rejected', 'Pause the offer before removing its code: students see it right now.', 'TEAM%2F2026' ) );
$blank = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Blank pool', 'kind' => 'codes', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $blank, 'wpcpm_state' => 'live' ), array( 'WPCPM_Sponsor_Offers', 'handle_state' ) );
ck( 'an empty pool cannot go live, and the detail says why', array( $r[0], $r[3] ), array( 'offer-empty', 'Add at least one code before switching this offer on.' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $blank, 'wpcpm_codes' => "P-1\nP-2\nP-1" ), array( 'WPCPM_Sponsor_Offers', 'handle_codes_add' ) );
ck( 'a paste with a repeat adds nothing and names the line', array( $r[0], $r[3], WPCPM_Sponsor_Codes::counts( $blank )['total'] ), array( 'codes-refused', 'Line 3 repeats line 1.', 0 ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $blank, 'wpcpm_codes' => "P-1\nP-2" ), array( 'WPCPM_Sponsor_Offers', 'handle_codes_add' ) );
ck( 'a clean paste adds, says how many, and is logged', array( $r[0], $r[3], WPCPM_Sponsor_Codes::counts( $blank )['available'], end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data']['added'] ), array( 'codes-added', '2 codes added.', 2, 'offer_codes', 2 ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $new_id, 'wpcpm_codes' => 'X-1' ), array( 'WPCPM_Sponsor_Offers', 'handle_codes_add' ) );
ck( 'codes cannot be pasted into a shared offer', $r[0], 'offer-rejected' );
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $blank ] = time();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $blank, 'wpcpm_codes' => 'Q-1' ), array( 'WPCPM_Sponsor_Offers', 'handle_codes_add' ) );
unset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $blank ] );
ck( 'a paste that meets a held pool lock is told to try again, and adds nothing', array( $r[0], WPCPM_Sponsor_Codes::counts( $blank )['total'] ), array( 'offer-busy', 2 ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $blank ), array( 'WPCPM_Sponsor_Offers', 'handle_codes_void' ) );
ck( 'the sponsor voids what nobody holds', array( $r[0], $r[3], WPCPM_Sponsor_Codes::counts( $blank ) ), array( 'codes-voided', '2 codes voided.', array( 'available' => 0, 'claimed' => 0, 'void' => 2, 'total' => 2 ) ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $blank, 'wpcpm_codes' => "https://shop.example/?code=WP%20CREDITS\nPLAIN-1" ), array( 'WPCPM_Sponsor_Offers', 'handle_codes_add' ) );
ck( 'a pasted checkout link keeps every percent-encoded character', array( $r[0], WPCPM_Sponsor_Codes::code_at( $blank, 2 ), WPCPM_Sponsor_Codes::code_at( $blank, 3 ) ), array( 'codes-added', 'https://shop.example/?code=WP%20CREDITS', 'PLAIN-1' ) );
$GLOBALS['nonce_ok'] = false;
ck( 'a bad nonce dies before the claim', post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $a1, 'wpcpm_state' => 'paused' ), array( 'WPCPM_Sponsor_Offers', 'handle_state' ) )[0], 'die' );
$GLOBALS['nonce_ok'] = true;
$GLOBALS['uid'] = 20;
ck( 'a student posting to the sponsor handlers is refused', post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $a1, 'wpcpm_state' => 'paused' ), array( 'WPCPM_Sponsor_Offers', 'handle_state' ) )[0], 'refused' );
$GLOBALS['uid'] = 5;

echo "\n=== The Usage card ===\n";
$html = card( 'WPCPM_Sponsor_Usage', $A, $context );
ck( 'the card draws a table with a row per offer and a totals row, the twelve-month series, and the export form', array( false !== strpos( $html, 'id="wpcpm-sponsor-usage"' ), substr_count( $html, '<tr class="wpcpm-usage__offer">' ), false !== strpos( $html, 'wpcpm-usage__totals' ), false !== strpos( $html, gmdate( 'Y-m' ) ), false !== strpos( $html, 'name="action" value="' . WPCPM_Sponsor_Usage::ACTION_EXPORT . '"' ) ), array( true, count( WPCPM_Sponsor_Offers::offers_of( $A ) ), true, true, true ) );
ck( 'and says that nobody is named here', false !== strpos( $html, 'Nobody is named here' ), true );
// The answer to the export is a file, so the page stays where it is: a form that locked itself at its first press
// and read "Preparing" would stay locked and reading it, with the download already on the disk. The export only reads,
// so a second press does no harm, and the form is left without the once-mark and its busy word.
$export_at   = (int) strpos( $html, 'name="action" value="' . WPCPM_Sponsor_Usage::ACTION_EXPORT . '"' );
$export_open = (int) strrpos( substr( $html, 0, $export_at ), '<form ' );
$export_form = substr( $html, $export_open, (int) strpos( $html, '</form>', $export_at ) - $export_open );
ck( 'the export form is a plain post: no once-guard and no busy word, because its answer is a file and the page never unloads', array( 0 === strpos( $export_form, '<form method="post"' ), false === strpos( $export_form, 'data-wpcpm-once' ), false === strpos( $export_form, 'data-wpcpm-busy' ), false !== strpos( $export_form, 'Download as CSV' ) ), array( true, true, true, true ) );
// A sponsor with no offer: the card stops after its sentence, and still ends its body, its
// disclosure and its section, the one card that ends them in two places.
$no_offer = card( 'WPCPM_Sponsor_Usage', 'recNOOFFERS000001', $context );
ck( 'a sponsor with no offer: the numbers are promised for later, and the card ends there, whole', array( false !== strpos( $no_offer, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-usage" class="wpcpm-group wpcpm-group__disclosure">' ), substr( $no_offer, -strlen( '<p>Numbers appear here once you have an offer.</p></div></details></section>' ) ), strpos( $no_offer, '<table' ) ), array( true, '<p>Numbers appear here once you have an offer.</p></div></details></section>', false ) );
// The table sits in a box of its own (1.123.17), as the Administrator Dashboard's tables with
// column headers do since 1.122.35: a tab stop, so a keyboard reaches it and scrolls it on a phone,
// and a region named by the card's heading words, which carry an id for it, not the count beside
// them. A card with no offer has no table, so no box.
$usage_box = preg_match_all( '#<div class="wpcpm-usage__scroll"([^>]*)>(.*?)</div>#s', $html, $usage_found, PREG_SET_ORDER ) ? $usage_found : array();
ck( 'the Usage table sits alone in a box that is a tab stop and a region named by the card\'s heading words, not its count', array(
	count( $usage_box ),
	isset( $usage_box[0] ) ? trim( $usage_box[0][1] ) : null,
	isset( $usage_box[0] ) ? ( 0 === strpos( $usage_box[0][2], '<table class="wpcpm-table wpcpm-usage">' ) && '</table>' === substr( $usage_box[0][2], -8 ) ) : null,
	false !== strpos( $html, '<h3 class="wpcpm-group__title"><span id="wpcpm-sponsor-usage-title">Usage</span> <span class="wpcpm-group__count">' ),
	substr_count( $html, 'id="wpcpm-sponsor-usage-title"' ),
	false !== strpos( $html, '</tr></tfoot></table></div><form method="post"' ),
), array( 1, 'tabindex="0" role="region" aria-labelledby="wpcpm-sponsor-usage-title"', true, true, 1, true ) );
ck( 'with no offer there is no table and no box, and the heading reads as it does with one', array( substr_count( $no_offer, 'wpcpm-usage__scroll' ), false !== strpos( $no_offer, '<h3 class="wpcpm-group__title"><span id="wpcpm-sponsor-usage-title">Usage</span> <span class="wpcpm-group__count">0</span></h3>' ) ), array( 0, true ) );
$no_offer_row = card( 'WPCPM_Sponsor_Offers', 'recNOOFFERS000001', $context );
$one_claim    = array( card( 'WPCPM_Sponsor_Offers', $B, $context ), card( 'WPCPM_Sponsor_Usage', $B, $context ) );
ck( 'one claim is one, on the Offers row and on the Usage row', array( false !== strpos( $one_claim[0], '<span class="wpcpm-sponsor__state">1 live, 1 claim this month</span>' ), false !== strpos( $one_claim[1], '<span class="wpcpm-sponsor__state">1 claim in all</span>' ) ), array( true, true ) );
ck( 'and its rows count nothing and say so: no offer live and no claim, and nothing claimed in all', array( false !== strpos( $no_offer_row, '<span class="wpcpm-sponsor__state">0 live, 0 claims this month</span>' ), false !== strpos( $no_offer, '<span class="wpcpm-sponsor__state">0 claims in all</span>' ) ), array( true, true ) );

echo "\n=== The Offers and codes card in two columns (1.97.1) ===\n";
$card_html = card( 'WPCPM_Sponsor_Offers', $A, $context );
$col_new   = (int) strpos( $card_html, 'wpcpm-offers__col--new' );
$col_list  = (int) strpos( $card_html, 'wpcpm-offers__col--list' );
$form_new  = (int) strpos( $card_html, 'wpcpm-offer__form--new' );
ck( 'the new-offer form is first and on the left, the offers on the right under Active offers, each a folded disclosure', array( $col_new > 0 && $col_new < $form_new && $form_new < $col_list, substr_count( $card_html, '<h4 class="wpcpm-sponsor__subheading">Active offers</h4>' ), substr_count( $card_html, '<details class="wpcpm-offer wpcpm-offer--' ), substr_count( $card_html, '<summary class="wpcpm-offer__summary">' ), substr_count( $card_html, '<div class="wpcpm-offer__body">' ) ), array( true, 1, count( WPCPM_Sponsor_Offers::offers_of( $A ) ), count( WPCPM_Sponsor_Offers::offers_of( $A ) ), count( WPCPM_Sponsor_Offers::offers_of( $A ) ) ) );
$past_n = count( array_filter( WPCPM_Sponsor_Offers::offers_of( $A ), array( 'WPCPM_Sponsor_Offers', 'is_past' ) ) );
ck( 'the counts line is phrasing inside the summary, not a paragraph, and the ended list is drawn exactly when an offer is over', array( substr_count( $card_html, '<span class="wpcpm-offer__counts">' ) > 0, strpos( $card_html, '<p class="wpcpm-offer__counts">' ), false !== strpos( $card_html, 'Ended and expired offers' ) ), array( true, false, $past_n > 0 ) );
$first = substr( $card_html, (int) strpos( $card_html, '<div class="wpcpm-offer__body">' ) );
$next  = strpos( $first, '<details class="wpcpm-offer wpcpm-offer--', 10 );
$first = false !== $next ? substr( $first, 0, $next ) : $first;
$needles = array( '<dl class="wpcpm-offer__details">', '<details class="wpcpm-offer__edit"', 'wpcpm-offer__edit-toggle">Edit this offer</summary>', 'class="wpcpm-sponsor__form wpcpm-offer__form"', '<div class="wpcpm-offer__moves">', '<h5 class="wpcpm-offer__subtitle">State</h5>', 'wpcpm-offer__state-note', 'name="wpcpm_state"' );
$found   = array_map( static function ( $n ) use ( $first ) { return strpos( $first, $n ); }, $needles );
$ordered = ! in_array( false, $found, true );
for ( $i = 1; $ordered && $i < count( $found ); $i++ ) { $ordered = $found[ $i ] > $found[ $i - 1 ]; }
ck( 'an open offer reads as text first (1.97.4): the details list, then Edit this offer over the form, then the state block with its title, its sentence and its moves, in that order', array( $ordered, substr_count( $first, '<div class="wpcpm-offer__detail"><dt>' ) >= 5, false !== strpos( $first, '<dt>Open to</dt><dd>Current students' ), false === strpos( $first, ' form="wpcpm-offer-state-' ) ), array( true, true, true, true ) );
// An offer switched on but past its last day is over: it sits under "Ended and expired offers", which
// the row's count of live offers leaves it out of, so its pill says Expired, not Live.
$a1_expires = WPCPM_Sponsor_Offers::read( $a1 )['expires'];
WPCPM_Sponsor_Offers::save( $a1, array( 'expires' => '2020-01-01' ) );
$expired_card = card( 'WPCPM_Sponsor_Offers', $A, $context );
$expired_at   = strpos( $expired_card, 'id="wpcpm-offer-' . $a1 . '"' );
$expired_head = false === $expired_at ? '' : substr( $expired_card, $expired_at, (int) strpos( $expired_card, '</summary>', $expired_at ) - $expired_at );
WPCPM_Sponsor_Offers::save( $a1, array( 'expires' => $a1_expires ) );
// An offer the sponsor ended is ended, whatever its last day: under the same heading, it keeps its
// own pill. B's third offer was ended in the tiers section above.
$ended_card = card( 'WPCPM_Sponsor_Offers', $B, $context );
$ended_at   = strpos( $ended_card, 'id="wpcpm-offer-' . $cap_b3 . '"' );
$ended_head = false === $ended_at ? '' : substr( $ended_card, $ended_at, (int) strpos( $ended_card, '</summary>', $ended_at ) - $ended_at );
ck( 'an offer switched on but past its last day is listed under Ended and expired offers wearing Expired, not Live, and an ended one keeps Ended', array(
	WPCPM_Sponsor_Offers::read( $a1 )['state'],
	false !== $expired_at && $expired_at > (int) strpos( $expired_card, '<h4 class="wpcpm-sponsor__subheading">Ended and expired offers</h4>' ),
	false !== strpos( $expired_head, '<span class="wpcpm-offer__state">Expired</span>' ),
	strpos( $expired_head, '<span class="wpcpm-offer__state">Live</span>' ),
	WPCPM_Sponsor_Offers::read( $cap_b3 )['state'],
	false !== strpos( $ended_head, '<span class="wpcpm-offer__state">Ended</span>' ),
), array( 'live', true, true, false, 'ended', true ) );
ck( 'is_past(): ended, or past the last day named; a draft or a live offer with days left is in play', array( WPCPM_Sponsor_Offers::is_past( array( 'state' => 'ended', 'expires' => '' ) ), WPCPM_Sponsor_Offers::is_past( array( 'state' => 'live', 'expires' => '2026-09-05' ), '2026-09-06' ), WPCPM_Sponsor_Offers::is_past( array( 'state' => 'live', 'expires' => '2026-09-06' ), '2026-09-06' ), WPCPM_Sponsor_Offers::is_past( array( 'state' => 'paused', 'expires' => '2026-12-31' ), '2026-09-06' ), WPCPM_Sponsor_Offers::is_past( array( 'state' => 'draft', 'expires' => '' ) ) ), array( true, true, false, false, false ) );
$chart = substr( $html, (int) strpos( $html, '<figure class="wpcpm-chart wpcpm-usage__chart">' ) );
$chart = substr( $chart, 0, (int) strpos( $chart, '</figure>' ) );
ck( 'the chart sits above the table (1.96.8): a caption naming the first and last month, twelve month cells, a bar per offer in each, a legend entry per offer', array( strpos( $html, 'wpcpm-usage__chart' ) < strpos( $html, '<table class="wpcpm-table wpcpm-usage">' ), substr_count( $chart, '<div class="wpcpm-chart__month">' ), substr_count( $chart, 'class="wpcpm-chart__bar ' ), substr_count( $chart, 'wpcpm-chart__swatch ' ), false !== strpos( $chart, 'Claims by month, ' ) ), array( true, 12, 12 * count( WPCPM_Sponsor_Offers::offers_of( $A ) ), count( WPCPM_Sponsor_Offers::offers_of( $A ) ), true ) );
ck( 'the plot carries the twelve-month sentence per offer for a screen reader, and the sentences left the page body', array( preg_match( '/role="img" aria-label="[^"]*last twelve months: [^"]*' . preg_quote( gmdate( 'Y-m' ), '/' ) . '/', $chart ), substr_count( $html, 'wpcpm-usage__series' ) ), array( 1, 0 ) );
ck( 'the scale reads 1, 2 or 5 times a power of ten and tops out at most four steps up', array( WPCPM_Sponsor_Usage::scale( 0 ), WPCPM_Sponsor_Usage::scale( 7 ), WPCPM_Sponsor_Usage::scale( 13 ), WPCPM_Sponsor_Usage::scale( 450 ) ), array( array( 'max' => 1, 'step' => 1 ), array( 'max' => 8, 'step' => 2 ), array( 'max' => 15, 'step' => 5 ), array( 'max' => 600, 'step' => 200 ) ) );
ck( 'no address and no code on the card', array( preg_match( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/', strip_tags( $html ) ), strpos( $html, 'LATE-' ) ), array( 0, false ) );
$shared_row = substr( $html, (int) strpos( $html, '<th scope="row">Second offer</th>' ) );
$shared_row = substr( $shared_row, 0, (int) strpos( $shared_row, '</tr>' ) );
ck( 'a shared offer has no pool, so its available, claimed and void cells are left blank', array( false !== strpos( $shared_row, '<td></td><td></td><td></td>' ), substr_count( $shared_row, '<td>' ) ), array( true, 6 ) );
$GLOBALS['uid'] = 20;
ck( 'the export refuses everyone but the sponsor and a manager', post( array( 'wpcpm_sponsor' => $A ), array( 'WPCPM_Sponsor_Usage', 'handle_export' ) )[0], 'refused' );
$GLOBALS['uid'] = 5;
$usage_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-usage.php' );
ck( 'the export claims ACT_VIEW_STATS before it builds anything, and sends a CSV attachment', array( strpos( $usage_src, 'ACT_VIEW_STATS' ) < strpos( $usage_src, 'WPCPM_Sponsor_Claims::csv(' ), false !== strpos( $usage_src, 'Content-Disposition: attachment' ) ), array( true, true ) );

echo "\n=== A voided code, and what the sponsor is told (FOFFR-4) ===\n";
$voided = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Reissued pool', 'kind' => 'codes', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
WPCPM_Sponsor_Codes::add( $voided, "V-1\nV-2" );
WPCPM_Sponsor_Codes::void_unclaimed( $voided );
$back = WPCPM_Sponsor_Codes::add( $voided, "V-1\nV-3" );
ck( 'the vendor reissues a voided code and the refusal names the state, not "already in this offer"', $back->get_error_data(), array( 'Line 1 was voided in this offer earlier.' ) );
ck( 'a code the offer never held goes in beside the void rows', array( WPCPM_Sponsor_Codes::add( $voided, 'V-4' ), WPCPM_Sponsor_Codes::counts( $voided ) ), array( 1, array( 'available' => 1, 'claimed' => 0, 'void' => 2, 'total' => 3 ) ) );

echo "\n=== The questions the card asks first ===\n";
// End this offer and Void unclaimed codes are the two presses the card cannot take back, so each asks
// through a data-wpcpm-confirm mark with its sentence escaped for the attribute. The offer's other moves
// ask nothing, and the void button sits in the Add codes form and posts the void form named in its
// `form` attribute, so the sentence is on that form.
$asking = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Asking pool', 'kind' => 'codes', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
WPCPM_Sponsor_Codes::add( $asking, "Q-1\nQ-2" );
$html     = card( 'WPCPM_Sponsor_Offers', $A, $context );
$end_mark = ' data-wpcpm-confirm="' . esc_attr( 'End this offer for good? Codes already claimed stay with the people who hold them.' ) . '"';
$ends     = preg_match_all( '#<button\b[^>]*\bvalue="ended"[^>]*>End this offer</button>#', $html, $found ) ? $found[0] : array();
$moves    = preg_match_all( '#<button\b[^>]*\bname="wpcpm_state"[^>]*\bvalue="(?:live|paused)"[^>]*>#', $html, $found ) ? $found[0] : array();
ck( 'every End this offer button asks "End this offer for good?"', array(
	count( $ends ) >= 1,
	count( array_filter( $ends, static function ( $button ) use ( $end_mark ) { return false !== strpos( $button, $end_mark ); } ) ),
), array( true, count( $ends ) ) );
ck( 'and the offer\'s other moves, Switch on, Resume and Pause, ask nothing', array(
	count( $moves ) >= 1,
	substr_count( implode( '', $moves ), 'data-wpcpm-confirm' ),
), array( true, 0 ) );
$void_form = preg_match( '#<form\b[^>]*\bid="wpcpm-offer-void-' . $asking . '"[^>]*>#', $html, $found ) ? $found[0] : '';
ck( 'the void form asks "Void every code nobody has claimed yet?" on its form tag',
    false !== strpos( $void_form, ' data-wpcpm-confirm="' . esc_attr( 'Void every code nobody has claimed yet? They cannot be brought back.' ) . '"' ), true );
ck( 'and Void unclaimed codes is a submit button that names the void form in its form attribute and carries no mark of its own',
    preg_match( '#<button type="submit" form="wpcpm-offer-void-' . $asking . '" class="[^"]*">Void unclaimed codes</button>#', $html ), 1 );

echo "\n=== Uninstall ===\n";
// In the order uninstall uses: the offers and their pools first, then the claims meta. A lock
// a dead request left behind has to go with its pool, because by the time the claims class
// runs there is no offer left to iterate (finding 6).
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ] = time();
$pool_name = WPCPM_Sponsor_Codes::option_name( $a1 );
WPCPM_Sponsor_Offers::delete_all();
WPCPM_Sponsor_Claims::delete_all();
ck( 'uninstall forgets every claim, every pool and every lock', array( isset( $GLOBALS['umeta'][22][ WPCPM_Sponsor_Claims::META_CLAIMS ] ), isset( $GLOBALS['opts'][ $pool_name ] ), count( array_filter( array_keys( $GLOBALS['opts'] ), static function ( $k ) { return 0 === strpos( $k, WPCPM_Sponsor_Claims::LOCK_PREFIX ); } ) ) ), array( false, false, 0 ) );

echo "\n=== House rules ===\n";
// The guides say the rule the card says: offers are separate from the tiers, and no tier caps them
// (1.123.18). The sponsors' guide and the Administrators' say it, and neither names a cap by tier.
$offer_guides = (string) file_get_contents( __DIR__ . '/../docs/sections/41-sponsor-offers.md' ) . (string) file_get_contents( __DIR__ . '/../docs/sections/34-admin-operations.md' ) . (string) file_get_contents( __DIR__ . '/../docs/sections/31-admin-settings.md' );
ck( 'the guides say offers are separate from the tiers and name no cap on live offers by tier', array(
	false !== strpos( $offer_guides, 'Above the form, a line says that offers are separate from the tiers: as the program\'s partner page says, partners at any level may offer free access to their products, so your company may have any number of live offers, whatever its tier.' ),
	false !== strpos( $offer_guides, 'since 1.123.18 no tier caps live offers' ),
	preg_match_all( '/one live offer at a time|includes no live offers?|live offers? (?:your|its) tier includes|caps a tier puts on live offers|one at a time for a Supporter/i', $offer_guides ),
), array( true, true, 0 ) );
foreach ( array( 'class-wpcpm-sponsor-codes.php', 'class-wpcpm-sponsor-offers.php' ) as $file ) {
	$src = (string) file_get_contents( __DIR__ . '/../includes/modules/' . $file );
	ck( 'no em or en dash in ' . $file, preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
}
$codes_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-codes.php' );
ck( 'the pool never hashes a code without the key', preg_match( '/\bhash\(|\bmd5\(|\bsha1\(|\bcrc32\(/', $codes_src ), 0 );
$offers_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-offers.php' );
ck( 'an uploaded file is read only after is_uploaded_file() vouched for it', strpos( $offers_src, 'is_uploaded_file( $tmp )' ) < strpos( $offers_src, 'file_get_contents( $tmp )' ), true );
$forms_js = (string) file_get_contents( __DIR__ . '/../assets/js/forms.js' );
ck( 'forms.js shows the shared field or the codes box for the kind chosen', false !== strpos( $forms_js, 'data-wpcpm-shows-for' ), true );
ck( 'and never writes its option autoloaded', preg_match( '/add_option\([^;]*(\'yes\'|true)\s*\)/', $codes_src ), 0 );
$claims_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-claims.php' );
ck( 'no em or en dash in the claims class', preg_match( '/\x{2013}|\x{2014}/u', $claims_src ), 0 );
ck( 'nothing about a claim ever reaches Airtable', preg_match( '/WPCPM_Airtable|update_records|WPCPM_Sponsors_Index::patch/', $claims_src ), 0 );
ck( 'the claim looks the person up before it takes the lock', strpos( $claims_src, 'self::claims_of( $user->ID )' ) < strpos( $claims_src, 'WPCPM_Sponsor_Codes::lock( $offer[' ), true );
ck( 'every rewrite of the pool but the two claim() makes under its lock takes the lock itself', substr_count( $codes_src, 'self::lock( $offer_id )' ) >= 5, true );
ck( 'and the claims class no longer keeps a lock of its own', preg_match( '/function (un)?lock\(/', $claims_src ), 0 );

echo "\n=== Words keep their backslashes ===\n";
// `wp_insert_post()`, `wp_update_post()` and post meta unslash what they are handed, as core's do,
// and an offer's title, text and instructions reach them unslashed: each keeps a backslash only
// when it is written as a slashed copy.
$typed      = 'Keep C:\drafts\codes.csv, two \\\\ in a row, and say "thanks"';
$typed_edit = 'Edited in C:\drafts\later, two more \\\\ of them, "again"';
$typed_id   = WPCPM_Sponsor_Offers::create( $A, array( 'title' => $typed, 'kind' => 'shared', 'text' => $typed, 'instructions' => $typed, 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
$typed_got  = WPCPM_Sponsor_Offers::read( $typed_id );
ck( 'a new offer keeps its title, text and instructions exactly as typed', array( $typed_got['title'] ?? null, $typed_got['text'] ?? null, $typed_got['instructions'] ?? null ), array( $typed, $typed, $typed ) );
WPCPM_Sponsor_Offers::save( $typed_id, array( 'title' => $typed_edit, 'text' => $typed_edit, 'instructions' => $typed_edit ) );
$typed_got = WPCPM_Sponsor_Offers::read( $typed_id );
ck( 'and a saved one the new words', array( $typed_got['title'] ?? null, $typed_got['text'] ?? null, $typed_got['instructions'] ?? null ), array( $typed_edit, $typed_edit, $typed_edit ) );

// The scan covers the whole module, with no file excused: the sponsor record lives on the site
// since 1.111.0, and the sponsors sync, the last file that reached the base, went with it.
$module_files  = glob( __DIR__ . '/../includes/modules/class-wpcpm-sponsor*.php' );
$still_reaches = array();
foreach ( $module_files as $module_file ) {
	if ( false !== strpos( (string) file_get_contents( $module_file ), 'WPCPM_Airtable' ) ) {
		$still_reaches[] = basename( $module_file );
	}
}
sort( $still_reaches );
ck( 'no file of the sponsors module names WPCPM_Airtable any more', array( count( $module_files ) > 20, $still_reaches ), array( true, array() ) );
ck( 'this suite defines no WPCPM_Airtable class at all', class_exists( 'WPCPM_Airtable' ), false );

echo "\n=== Text people type keeps every word ===\n";

// What the box shows is the stored text read back by typed_text(), and what a person types is not
// what the cleaner hands back: `<` becomes `&lt;` and the quotes and ampersands after it entities
// too, and kses writes every other `>` as `&gt;`. These are the phrases that lose words if a box,
// a count or a save forgets that.
$t_team     = 'Save <3 on <b>hosting</b> > plans';
$t_team_cl  = 'Save &lt;3 on hosting > plans';
$t_team_mem = 'Save &lt;3 on hosting &gt; plans';
$t_team_box = 'Save <3 on hosting &gt; plans';
$t_ages     = 'Ages 8 < 12 welcome, adults > 18 pay';
$t_ages_mem = 'Ages 8 &lt; 12 welcome, adults &gt; 18 pay';
$t_qa       = 'Q&A: "blocks" & themes, it\'s free.';
$t_qa_after = 'Ages 8 < 12. ' . $t_qa;
// Instructions with a held ">" in the first line and a plain one in the second, and a line break.
$t_instr    = $t_team . "\r\n" . $t_ages . "\r\n" . $t_qa;
$t_instr_cl = $t_team_cl . "\r\n" . $t_ages . "\r\n" . $t_qa;

/** The edit form of an offer as a browser shows it and posts it: each box's markup, what it shows, what it posts, its maxlength. */
function wpcpm_test_boxes( $html, $offer_id ) {
	$start = strpos( $html, 'id="wpcpm-offer-form-' . (int) $offer_id . '"' );
	if ( false === $start ) { return array(); }
	$form  = substr( $html, $start, strpos( $html, '</form>', $start ) - $start );
	$out   = array();
	// The parser reads CR LF in the markup as LF, then each entity once.
	$shows = static function ( $markup ) { return html_entity_decode( str_replace( "\r\n", "\n", $markup ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); };
	if ( preg_match( '/<input type="text" id="wpcpm-offer-' . (int) $offer_id . '-title" name="wpcpm_title" value="([^"]*)" maxlength="(\d+)"/', $form, $m ) ) {
		// A text input drops every line break from its value, so a break in the markup is not in the box.
		$line         = str_replace( array( "\r", "\n" ), '', $shows( $m[1] ) );
		$out['title'] = array( 'markup' => $m[1], 'shown' => $line, 'posts' => $line, 'maxlength' => (int) $m[2] );
	}
	foreach ( array( 'text', 'instructions' ) as $key ) {
		if ( preg_match( '/<textarea id="wpcpm-offer-' . (int) $offer_id . '-' . $key . '" name="wpcpm_' . $key . '" rows="\d+" maxlength="(\d+)">(.*?)<\/textarea>/s', $form, $m ) ) {
			// And it drops the one line feed that comes right after a text area's opening tag.
			$shown       = $shows( (string) preg_replace( '/^(?:\r\n|\r|\n)/', '', $m[2] ) );
			$out[ $key ] = array( 'markup' => $m[2], 'shown' => $shown, 'posts' => str_replace( "\n", "\r\n", $shown ), 'maxlength' => (int) $m[1] );
		}
	}
	return $out;
}
/** The edit form posted back as it is drawn, with the fields in $change replaced. */
function wpcpm_test_unedited( $record, $offer_id, array $change = array() ) {
	$html  = card( 'WPCPM_Sponsor_Offers', $record, array( 'can_manage' => false, 'open' => '', 'viewer' => $GLOBALS['users'][ $GLOBALS['uid'] ] ) );
	$boxes = wpcpm_test_boxes( $html, $offer_id );
	$offer = WPCPM_Sponsor_Offers::read( $offer_id );
	return array_merge( array( 'wpcpm_sponsor' => $record, 'wpcpm_offer' => $offer_id, 'wpcpm_title' => $boxes['title']['posts'], 'wpcpm_text' => $boxes['text']['posts'], 'wpcpm_instructions' => $boxes['instructions']['posts'], 'wpcpm_url' => $offer['url'], 'wpcpm_kind' => $offer['kind'], 'wpcpm_audience' => $offer['audience'], 'wpcpm_low' => (string) $offer['low'], 'wpcpm_expires' => $offer['expires'] ), $change );
}
/** A typing of exactly $limit characters as a person counts them (a line break is one), made of $unit and padded with "x". */
function wpcpm_test_filled( $unit, $limit ) {
	$one  = mb_strlen( str_replace( "\r\n", "\n", $unit ) );
	$text = str_repeat( $unit, intdiv( $limit, $one ) );
	return $text . str_repeat( 'x', $limit - mb_strlen( str_replace( "\r\n", "\n", $text ) ) );
}
/** The newest offer of a sponsor. */
function wpcpm_test_newest( $record ) { return max( array_keys( WPCPM_Sponsor_Offers::offers_of( $record ) ) ); }
$ctx = array( 'can_manage' => false, 'open' => '', 'viewer' => $GLOBALS['users'][5] );

ck( 'the stand-ins are core\'s: the cleaner leaves "<3 on" as an entity and the lone ">" as typed, kses drops "< 12 welcome, adults >" and writes the lone ">" as an entity, and only esc_textarea() escapes an ampersand again',
	array( sanitize_text_field( $t_team ), wpcpm_test_kses_title( $t_ages ), wpcpm_test_kses_title( $t_team_cl ), wpcpm_test_kses_title( $t_qa ), esc_attr( 'a &gt; b' ), esc_textarea( 'a &gt; b' ) ),
	array( $t_team_cl, 'Ages 8  18 pay', $t_team_mem, 'Q&amp;A: "blocks" &amp; themes, it\'s free.', 'a &gt; b', 'a &amp;gt; b' ) );

echo "\n--- A member's words: the title is a post title, so kses meets it; the text and the instructions are post meta, so it does not ---\n";
$GLOBALS['uid'] = 5; $GLOBALS['audit'] = array(); $GLOBALS['patched'] = array();
$t_count = count( WPCPM_Sponsor_Offers::offers_of( $A ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 0, 'wpcpm_title' => $t_team, 'wpcpm_kind' => 'shared', 'wpcpm_shared' => 'TYPED-1', 'wpcpm_text' => $t_team, 'wpcpm_instructions' => $t_instr, 'wpcpm_url' => '', 'wpcpm_low' => '', 'wpcpm_expires' => '' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a member\'s new offer titled "Save <3 on <b>hosting</b> > plans" is refused now, because the cleaner would take "<b>" and "</b>", and nothing is created', array( $r[0], $r[3], count( WPCPM_Sponsor_Offers::offers_of( $A ) ) ), array( 'offer-loss', WPCPM_Typed_Text::loss_message( 'Title' ), $t_count ) );
$t_new = wpcpm_test_form( card( 'WPCPM_Sponsor_Offers', $A, $ctx ), 'new' );
ck( 'and the page it lands on draws the new-offer form with what was typed', array( wpcpm_test_value( $t_new, 'wpcpm_title' ), false !== strpos( $t_new, ">\n" . esc_textarea( str_replace( "\r\n", "\n", $t_instr ) ) . '</textarea>' ) ), array( $t_team, true ) );
// Such a text is still what an offer holds when an earlier release stored it, and the boxes below
// draw it and save it back. The offer is made as that release's handler made it: the posted fields
// cleaned by clean(), then create(), which hands a member's title to kses through insert_text().
$t_made = WPCPM_Sponsor_Offers::clean( array( 'title' => sanitize_text_field( $t_team ), 'text' => sanitize_textarea_field( $t_team ), 'instructions' => sanitize_textarea_field( $t_instr ), 'kind' => 'shared', 'url' => '', 'low' => '', 'expires' => '' ) );
$mem_id = WPCPM_Sponsor_Offers::create( $A, $t_made['fields'] );
WPCPM_Sponsor_Codes::set_shared( $mem_id, 'TYPED-1' );
$mem    = WPCPM_Sponsor_Offers::read( $mem_id );
ck( 'stored so by an earlier release, it keeps every word: the title as the post holds it after kses, the text as the cleaner left it', array( $mem['title'], $mem['text'] ), array( $t_team_mem, $t_team_cl ) );
ck( 'the instructions, with the same words and a "<" and ">" that stay as typed, quotes, "&" and line breaks, are stored as the cleaner left them', $mem['instructions'], $t_instr_cl );
$html  = card( 'WPCPM_Sponsor_Offers', $A, $ctx );
ck( 'the new-offer form takes the plain limits, 120, 500 and 4,000 characters', array( false !== strpos( $html, 'id="wpcpm-offer-new-title" name="wpcpm_title" value="" maxlength="120" required' ), false !== strpos( $html, 'id="wpcpm-offer-new-text" name="wpcpm_text" rows="2" maxlength="500"></textarea>' ), false !== strpos( $html, 'id="wpcpm-offer-new-instructions" name="wpcpm_instructions" rows="4" maxlength="4000"></textarea>' ) ), array( true, true, true ) );
$boxes = wpcpm_test_boxes( $html, $mem_id );
ck( 'its title box shows the title as typed with the held "&gt;" and no tag, and takes 3 characters more than the limit', array( $boxes['title']['shown'], $boxes['title']['maxlength'] ), array( $t_team_box, 123 ) );
ck( 'and is escaped in full, so a browser shows the held "&gt;" as four characters: esc_attr() would show ">" and post back "Save plans"', array( $boxes['title']['markup'], sanitize_text_field( $boxes['title']['posts'] ) ), array( 'Save &lt;3 on hosting &amp;gt; plans', $t_team_mem ) );
ck( 'its text box shows the same words, its instructions box shows what was typed, line break and all', array( $boxes['text']['shown'], $boxes['text']['maxlength'], $boxes['instructions']['shown'], $boxes['instructions']['maxlength'] ), array( $t_team_box, 503, $t_team_box . "\n" . $t_ages . "\n" . $t_qa, 4003 ) );
$GLOBALS['title_writes'] = 0; $GLOBALS['audit'] = array(); $GLOBALS['patched'] = array();
$r = post( wpcpm_test_unedited( $A, $mem_id, array( 'wpcpm_expires' => '2026-12-31' ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
$after = WPCPM_Sponsor_Offers::read( $mem_id );
ck( 'saved again with only the last day changed, nothing is lost: the title, the text and the instructions are the bytes they were', array( $r[0], $after['title'], $after['text'], $after['instructions'], $after['expires'] ), array( 'offer-saved', $t_team_mem, $t_team_cl, $t_instr_cl, '2026-12-31' ) );
ck( 'and the unchanged title is not written to the post again', $GLOBALS['title_writes'], 0 );
$r = post( wpcpm_test_unedited( $A, $mem_id, array( 'wpcpm_expires' => '2026-12-31' ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
$again = WPCPM_Sponsor_Offers::read( $mem_id );
ck( 'and again: the same bytes', array( $again['title'], $again['text'], $again['instructions'] ), array( $t_team_mem, $t_team_cl, $t_instr_cl ) );

$r = post( wpcpm_test_unedited( $A, $mem_id, array( 'wpcpm_title' => $t_ages ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
$ages_got = WPCPM_Sponsor_Offers::read( $mem_id );
ck( 'a member retitles it "Ages 8 < 12 welcome, adults > 18 pay": kses would have taken "< 12 welcome, adults >" and the words in it, and the post holds every word', array( $r[0], $ages_got['title'], mb_strlen( $ages_got['title'] ) ), array( 'offer-saved', $t_ages_mem, 42 ) );
$boxes = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $A, $ctx ), $mem_id );
ck( 'its box shows it as typed and takes the limit, nothing held', array( $boxes['title']['shown'], $boxes['title']['maxlength'] ), array( $t_ages, 120 ) );
$GLOBALS['title_writes'] = 0;
$r = post( wpcpm_test_unedited( $A, $mem_id, array( 'wpcpm_low' => '7' ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'saved unedited, the title is the bytes it was and is not written', array( WPCPM_Sponsor_Offers::read( $mem_id )['title'], $GLOBALS['title_writes'] ), array( $t_ages_mem, 0 ) );

echo "\n--- An administrator holds unfiltered_html: kses never meets the words, so the save is as it was ---\n";
$GLOBALS['uid'] = 1;
$ctx_admin = array( 'can_manage' => true, 'open' => '', 'viewer' => $GLOBALS['users'][1] );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 0, 'wpcpm_title' => $t_ages, 'wpcpm_kind' => 'shared', 'wpcpm_shared' => 'TYPED-2', 'wpcpm_text' => $t_team, 'wpcpm_instructions' => '', 'wpcpm_url' => '', 'wpcpm_low' => '', 'wpcpm_expires' => '' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'an administrator\'s text with "<b>" in it is refused the same way, named by its box', array( $r[0], $r[3] ), array( 'offer-loss', WPCPM_Typed_Text::loss_message( 'What you get, in a sentence or two' ) ) );
card( 'WPCPM_Sponsor_Offers', $A, $ctx_admin );
// A typing with no tag that the cleaner still rewrites, a "<" with no ">" after it and the quote
// marks and the ampersands after that, saved by a member and by an administrator. The text is post
// meta, which kses never reads, so each save stores it as the cleaner left it, the same bytes.
$t_qa_cl = 'Ages 8 &lt; 12. Q&amp;A: &quot;blocks&quot; &amp; themes, it&#039;s free.';
$GLOBALS['uid'] = 5;
$m_r = post( wpcpm_test_unedited( $A, $mem_id, array( 'wpcpm_text' => $t_qa_after ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
$GLOBALS['uid'] = 1;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 0, 'wpcpm_title' => $t_ages, 'wpcpm_kind' => 'shared', 'wpcpm_shared' => 'TYPED-2', 'wpcpm_text' => $t_qa_after, 'wpcpm_instructions' => '', 'wpcpm_url' => '', 'wpcpm_low' => '', 'wpcpm_expires' => '' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
$adm_id = wpcpm_test_newest( $A );
$adm    = WPCPM_Sponsor_Offers::read( $adm_id );
ck( 'the title is what the cleaner left, the "<" and ">" as typed, and the text is the same as a member\'s save of the same typing stores', array( $m_r[0], $r[0], $adm['title'], $adm['text'], WPCPM_Sponsor_Offers::read( $mem_id )['text'] ), array( 'offer-saved', 'offer-created', $t_ages, $t_qa_cl, $t_qa_cl ) );
$boxes     = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $A, $ctx_admin ), $adm_id );
ck( 'its title box shows it as typed and takes the limit', array( $boxes['title']['shown'], $boxes['title']['maxlength'] ), array( $t_ages, 120 ) );
$GLOBALS['title_writes'] = 0;
$r = post( wpcpm_test_unedited( $A, $adm_id, array( 'wpcpm_expires' => '2026-11-30' ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'saved unedited it keeps its bytes', array( WPCPM_Sponsor_Offers::read( $adm_id )['title'], WPCPM_Sponsor_Offers::read( $adm_id )['text'], $GLOBALS['title_writes'] ), array( $t_ages, $t_qa_cl, 0 ) );
$GLOBALS['uid'] = 5;
$r = post( wpcpm_test_unedited( $A, $adm_id, array( 'wpcpm_expires' => '2026-11-29' ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'and a member who saves it unedited does not rewrite it in the entity form either', array( WPCPM_Sponsor_Offers::read( $adm_id )['title'], $GLOBALS['title_writes'] ), array( $t_ages, 0 ) );

echo "\n--- The limits are counted as the person typed: at the limit it is kept whole, one more is refused ---\n";
$line      = $t_qa_after . "\r\n" . $t_ages . "\r\n";
$text_at   = wpcpm_test_filled( $line, WPCPM_Sponsor_Offers::MAX_OFFER );
$instr_at  = wpcpm_test_filled( $line, WPCPM_Sponsor_Offers::MAX_TEXT );
$title_at  = wpcpm_test_filled( $t_qa_after . ' ' . $t_ages . ' ', WPCPM_Sponsor_Offers::MAX_TITLE );
$typed_len = static function ( $v ) { return mb_strlen( str_replace( "\r\n", "\n", $v ) ); };
ck( 'the typings are exactly 500, 4,000 and 120 characters, with "<", ">", quotes, "&" and line breaks', array( $typed_len( $text_at ), $typed_len( $instr_at ), $typed_len( $title_at ), substr_count( $text_at, "\r\n" ) > 5, substr_count( $instr_at, "\r\n" ) > 40 ), array( 500, 4000, 120, true, true ) );
$GLOBALS['uid'] = 5; $GLOBALS['audit'] = array(); $GLOBALS['patched'] = array();
$r = post( wpcpm_test_unedited( $A, $mem_id, array( 'wpcpm_title' => $title_at, 'wpcpm_text' => $text_at, 'wpcpm_instructions' => $instr_at ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
$full = WPCPM_Sponsor_Offers::read( $mem_id );
ck( 'a member saves all three at their limits and the save is accepted', $r[0], 'offer-saved' );
ck( 'each is stored longer than its limit and counted at it: nothing was cut', array( mb_strlen( $full['title'] ) > 120, mb_strlen( $full['text'] ) > 500, mb_strlen( $full['instructions'] ) > 4000, WPCPM_Typed_Text::typed_length( $full['title'] ), WPCPM_Typed_Text::typed_length( $full['text'] ), WPCPM_Typed_Text::typed_length( $full['instructions'] ) ), array( true, true, true, 120, 500, 4000 ) );
$boxes = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $A, $ctx ), $mem_id );
ck( 'and each comes back to its box exactly as typed, with the box\'s own limit', array( $boxes['title']['shown'] === $title_at, $boxes['text']['shown'] === str_replace( "\r\n", "\n", $text_at ), $boxes['instructions']['shown'] === str_replace( "\r\n", "\n", $instr_at ), $boxes['title']['maxlength'], $boxes['text']['maxlength'], $boxes['instructions']['maxlength'] ), array( true, true, true, 120, 500, 4000 ) );
$before = array( $full['title'], $full['text'], $full['instructions'] );
$r = post( wpcpm_test_unedited( $A, $mem_id, array( 'wpcpm_expires' => '2027-01-31' ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
$full = WPCPM_Sponsor_Offers::read( $mem_id );
ck( 'saved again with only the last day changed, the three keep their bytes', array( $r[0], array( $full['title'], $full['text'], $full['instructions'] ), $full['expires'] ), array( 'offer-saved', $before, '2027-01-31' ) );

$GLOBALS['audit'] = array(); $GLOBALS['patched'] = array();
$r = post( wpcpm_test_unedited( $A, $mem_id, array( 'wpcpm_title' => $title_at . 'x' ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a title one character over is refused with the field, the limit and what to do', array( $r[0], $r[3] ), array( 'offer-rejected', 'The title is 1 character over the limit of 120. Shorten it and save again.' ) );
$r = post( wpcpm_test_unedited( $A, $mem_id, array( 'wpcpm_text' => $text_at . 'x' ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a text one character over is refused the same way', array( $r[0], $r[3] ), array( 'offer-rejected', 'What you get is 1 character over the limit of 500. Shorten it and save again.' ) );
$r = post( wpcpm_test_unedited( $A, $mem_id, array( 'wpcpm_instructions' => $instr_at . 'xyz' ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'and instructions three over, in the plural', array( $r[0], $r[3] ), array( 'offer-rejected', 'How to redeem it is 3 characters over the limit of 4000. Shorten it and save again.' ) );
$left_as = WPCPM_Sponsor_Offers::read( $mem_id );
ck( 'a refused save writes nothing: the offer, the log and the program records are as they were', array( array( $left_as['title'], $left_as['text'], $left_as['instructions'] ), $GLOBALS['audit'], $GLOBALS['patched'] ), array( $before, array(), array() ) );
$offers_before = count( WPCPM_Sponsor_Offers::offers_of( $A ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 0, 'wpcpm_title' => 'Too wordy', 'wpcpm_kind' => 'shared', 'wpcpm_shared' => 'TYPED-3', 'wpcpm_text' => $text_at . 'x', 'wpcpm_instructions' => '', 'wpcpm_url' => '', 'wpcpm_low' => '', 'wpcpm_expires' => '' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a new offer with a text one over creates nothing', array( $r[0], count( WPCPM_Sponsor_Offers::offers_of( $A ) ) ), array( 'offer-rejected', $offers_before ) );
$r = post( wpcpm_test_unedited( $A, $mem_id, array( 'wpcpm_title' => str_repeat( 'é', 120 ) . "\xF0\x9F\x98\x80" ) ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'an emoji is one character to the count, as typed: 121 characters is refused by one', array( $r[0], $r[3] ), array( 'offer-rejected', 'The title is 1 character over the limit of 120. Shorten it and save again.' ) );

echo "\n--- A row imported from the program records keeps its stored form ---\n";
// On this line the migration hands seed() what the records held for the legacy fields, `$legacy`
// below; the rows give the sponsor's name, which is the offer's title.
$E = 'recSPONSOR0000005'; $F = 'recSPONSOR0000006'; $G = 'recSPONSOR0000007';
$legacy = static function ( $offer, $instructions ) { return array( 'offer' => $offer, 'instructions' => $instructions, 'more_info' => '', 'coupon_link' => '' ); };
$row = static function ( $name, $offer, $instructions ) { return array( 'name' => $name, 'status' => 'Approved', 'website' => '', 'contact_person' => 'Rep', 'contact_email' => 'administrator@example.test', 'product_type' => 'Plugin', 'offer' => $offer, 'instructions' => $instructions, 'more_info' => '', 'coupon_link' => '', 'manager' => '', 'mentors' => array() ); };
$whole_text  = str_repeat( '&amp;', 200 ) . str_repeat( 'x', 300 );
$whole_instr = str_repeat( '&quot;', 800 ) . str_repeat( 'x', 3200 );
WPCPM_Sponsors_Index::write( array_merge( WPCPM_Sponsors_Index::rows(), array(
	$E => $row( 'Entity Example', $whole_text, $whole_instr ),
	$F => $row( 'Held Example', $t_team_mem, $t_ages_mem ),
	$G => $row( str_repeat( 'n', 130 ), str_repeat( 'x', 600 ), str_repeat( 'y', 5000 ) ),
) ), time() );
$GLOBALS['uid'] = 1;
$e_id = WPCPM_Sponsor_Offers::seed( $E, $legacy( $whole_text, $whole_instr ) );
$e    = WPCPM_Sponsor_Offers::read( $e_id );
ck( 'a text of 500 characters and instructions of 4,000, each longer than that in the form the records hold them, are imported whole, byte for byte', array( $e['text'] === $whole_text, $e['instructions'] === $whole_instr, WPCPM_Typed_Text::typed_length( $e['text'] ), WPCPM_Typed_Text::typed_length( $e['instructions'] ) ), array( true, true, 500, 4000 ) );
$f_id = WPCPM_Sponsor_Offers::seed( $F, $legacy( $t_team_mem, $t_ages_mem ) );
$f    = WPCPM_Sponsor_Offers::read( $f_id );
ck( 'the form the records hold a "<3" and a ">" in is imported as it is, and its boxes draw it as typed', array( $f['text'], $f['instructions'] ), array( $t_team_mem, $t_ages_mem ) );
$GLOBALS['uid'] = 5;
$f_boxes = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $F, $ctx ), $f_id );
ck( 'the imported text\'s box shows what was meant and takes the limit and the 3 held', array( $f_boxes['text']['shown'], $f_boxes['text']['maxlength'], $f_boxes['instructions']['shown'], $f_boxes['instructions']['maxlength'] ), array( $t_team_box, 503, $t_ages, 4000 ) );
$GLOBALS['uid'] = 1;
$g_id = WPCPM_Sponsor_Offers::seed( $G, $legacy( str_repeat( 'x', 600 ), str_repeat( 'y', 5000 ) ) );
$g    = WPCPM_Sponsor_Offers::read( $g_id );
ck( 'an import over the limit is cut to it, as it always was: nobody is there to refuse it to', array( mb_strlen( $g['title'] ), mb_strlen( $g['text'] ), mb_strlen( $g['instructions'] ) ), array( 120, 500, 4000 ) );
// Cut on a whole character as typed: an entity or a line break that straddles the limit is kept or
// left out whole, never split into a stray "&q" or a lone CR.
$K = 'recSPONSOR0000008';
WPCPM_Sponsors_Index::write( array_merge( WPCPM_Sponsors_Index::rows(), array( $K => $row( 'Cut Example', str_repeat( 'x', 498 ) . '&quot;yy', str_repeat( 'x', 3999 ) . "\r\nyy" ) ) ), time() );
$k = WPCPM_Sponsor_Offers::read( WPCPM_Sponsor_Offers::seed( $K, $legacy( str_repeat( 'x', 498 ) . '&quot;yy', str_repeat( 'x', 3999 ) . "\r\nyy" ) ) );
ck( 'an import over the limit is cut on a whole character as typed: an entity and a line break at the limit stay whole', array( $k['text'], WPCPM_Typed_Text::typed_length( $k['text'] ), $k['instructions'] === str_repeat( 'x', 3999 ) . "\r\n", WPCPM_Typed_Text::typed_length( $k['instructions'] ) ), array( str_repeat( 'x', 498 ) . '&quot;y', 500, true, 4000 ) );
// The seeded title is the sponsor's name, cut the same way. An application stores the name as the
// cleaner leaves it, a "<" that opens no tag as `&lt;` and the quote marks after it as `&quot;`, so
// the stored form is longer than the name as typed: counted as typed, it fits the title's box whole.
$L = 'recSPONSOR0000009'; $N = 'recSPONSOR0000010'; $O = 'recSPONSOR0000011';
$l_typed = 'Kids <12 "free" and "fun" ';
$l_typed .= str_repeat( 'n', 110 - mb_strlen( $l_typed ) );
$l_name  = sanitize_text_field( $l_typed );
// 86 characters as typed, whose stored form cut at 120 characters ends in the first five of a `&quot;`.
$n_typed = str_repeat( 'n', 75 ) . '<' . str_repeat( '"', 7 ) . 'nnn';
$n_name  = sanitize_text_field( $n_typed );
// 123 as typed, with an entity at the limit and one past it.
$o_typed = str_repeat( 'n', 119 ) . '<"x"';
$o_name  = sanitize_text_field( $o_typed );
WPCPM_Sponsors_Index::write( array_merge( WPCPM_Sponsors_Index::rows(), array( $L => $row( $l_name, 'x', '' ), $N => $row( $n_name, 'x', '' ), $O => $row( $o_name, 'x', '' ) ) ), time() );
$l = WPCPM_Sponsor_Offers::read( WPCPM_Sponsor_Offers::seed( $L ) );
ck( 'a name of 110 characters as typed, with a "<" and quote marks and over 120 in the form it is stored in, seeds the whole name as the title', array( mb_strlen( $l_typed ), mb_strlen( $l_name ) > 120, $l['title'] === $l_name, WPCPM_Typed_Text::typed_length( $l['title'] ) ), array( 110, true, true, 110 ) );
$n = WPCPM_Sponsor_Offers::read( WPCPM_Sponsor_Offers::seed( $N ) );
ck( 'and so does one of 86 that a cut on its stored form would have left ending in "&quot"', array( mb_strlen( $n_typed ), '&quot' === mb_substr( mb_substr( $n_name, 0, 120 ), -5 ), $n['title'] === $n_name, WPCPM_Typed_Text::typed_length( $n['title'] ) ), array( 86, true, true, 86 ) );
$o = WPCPM_Sponsor_Offers::read( WPCPM_Sponsor_Offers::seed( $O ) );
ck( 'a name over the limit as typed is cut to 120 as typed, on a whole entity', array( $o['title'], WPCPM_Typed_Text::typed_length( $o['title'] ) ), array( str_repeat( 'n', 119 ) . '&lt;', 120 ) );

echo "\n--- The post write: only the title meets kses, and only a member's needs insert_text() ---\n";
$GLOBALS['uid'] = 5;
$made = WPCPM_Sponsor_Offers::create( $A, array( 'title' => $t_ages, 'kind' => 'shared', 'text' => $t_ages, 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
ck( 'create() hands a member\'s title to the post write as entities, and keeps the text in meta as it was given', array( WPCPM_Sponsor_Offers::read( $made )['title'], WPCPM_Sponsor_Offers::read( $made )['text'] ), array( $t_ages_mem, $t_ages ) );
$GLOBALS['uid'] = 1;
$made_admin = WPCPM_Sponsor_Offers::create( $A, array( 'title' => $t_ages, 'kind' => 'shared', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
ck( 'and an administrator\'s as it was given', WPCPM_Sponsor_Offers::read( $made_admin )['title'], $t_ages );
$GLOBALS['uid'] = 5; $GLOBALS['title_writes'] = 0;
WPCPM_Sponsor_Offers::save( $made, array( 'title' => $t_ages_mem ) );
$no_write = $GLOBALS['title_writes'];
WPCPM_Sponsor_Offers::save( $made, array( 'title' => 'Ages 9 < 13 welcome, adults > 19 pay' ) );
ck( 'save() writes a title only when it is not the stored one, and then as entities for a member', array( $no_write, $GLOBALS['title_writes'], WPCPM_Sponsor_Offers::read( $made )['title'] ), array( 0, 1, 'Ages 9 &lt; 13 welcome, adults &gt; 19 pay' ) );
$GLOBALS['uid'] = 5;

echo "\n=== A save the cleaner would take words from is refused, and the form gets back what was typed ===\n";
/** One offer form, by its id or "new", as the page draws it. */
function wpcpm_test_form( $html, $offer_id ) {
	$start = 'new' === $offer_id ? strpos( $html, 'wpcpm-offer__form--new' ) : strpos( $html, 'id="wpcpm-offer-form-' . (int) $offer_id . '"' );
	return false === $start ? '' : substr( $html, $start, strpos( $html, '</form>', $start ) - $start );
}
/** What a browser shows in one input of a form, by its name. */
function wpcpm_test_value( $form, $name ) {
	return preg_match( '/name="' . preg_quote( $name, '/' ) . '" value="([^"]*)"/', $form, $m ) ? html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : null;
}
$GLOBALS['uid'] = 5; $GLOBALS['audit'] = array(); $GLOBALS['patched'] = array(); $GLOBALS['flash'] = array();
$k_id    = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Kept offer', 'kind' => 'codes', 'text' => 'One year free', 'instructions' => "Enter the code\r\nat checkout.", 'url' => 'https://plugins.mango-example.com/kept', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
$k_saved = WPCPM_Sponsor_Offers::read( $k_id );
$k_post  = static function ( array $change = array() ) use ( $A, $k_id ) {
	return array_merge( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $k_id, 'wpcpm_title' => 'Kept offer', 'wpcpm_text' => 'One year free', 'wpcpm_instructions' => "Enter the code\r\nat checkout.", 'wpcpm_url' => 'https://plugins.mango-example.com/kept', 'wpcpm_kind' => 'codes', 'wpcpm_low' => '10', 'wpcpm_expires' => '' ), $change );
};
$k_save  = array( 'WPCPM_Sponsor_Offers', 'handle_save' );
$k_lossy = 'Kids <12 free, adults >18 pay';
$k_text  = "Two years free\r\nfor \"every\" student & mentor, C:\\drafts";
$k_link  = 'https://plugins.mango-example.com/new?a=1&amp;b=2';

$k_buckets = $GLOBALS['buckets']; $GLOBALS['title_writes'] = 0;
$r = post( $k_post( array( 'wpcpm_title' => $k_lossy, 'wpcpm_text' => $k_text, 'wpcpm_url' => $k_link, 'wpcpm_audience' => array( 'mentors', 'bogus' ), 'wpcpm_low' => '5', 'wpcpm_expires' => '2026-12-31' ) ), $k_save );
ck( 'a title the cleaner would cut to "Kids 18 pay" is refused, with the sentence that names the box by its label and says how to keep every word', $r, array( 'offer-loss', 'offers', $A, WPCPM_Typed_Text::loss_message( 'Title' ) ) );
ck( 'and nothing is stored: the offer, its post title, the log and the program records are as they were', array( WPCPM_Sponsor_Offers::read( $k_id ) === $k_saved, $GLOBALS['title_writes'], $GLOBALS['audit'], $GLOBALS['patched'] ), array( true, 0, array(), array() ) );
ck( 'and the refusal spends nothing of a daily ceiling or of the refusal meter', $GLOBALS['buckets'], $k_buckets );
ck( 'the messages map has the refusal, its whole sentence the detail', WPCPM_Sponsor_Offers::messages()['offer-loss'] ?? null, array( 'error', '' ) );
$k_html  = card( 'WPCPM_Sponsor_Offers', $A, $ctx );
$k_boxes = wpcpm_test_boxes( $k_html, $k_id );
$k_form  = wpcpm_test_form( $k_html, $k_id );
ck( 'the card draws the offer and its edit form open, every box as it was typed, a line break as the box counts it', array(
	false !== strpos( $k_html, '<details class="wpcpm-offer wpcpm-offer--draft" id="wpcpm-offer-' . $k_id . '" open>' ),
	false !== strpos( $k_html, '<details class="wpcpm-offer__edit" id="wpcpm-offer-edit-' . $k_id . '" open>' ),
	$k_boxes['title']['shown'] ?? null,
	$k_boxes['text']['shown'] ?? null,
	$k_boxes['instructions']['shown'] ?? null,
), array( true, true, $k_lossy, str_replace( "\r\n", "\n", $k_text ), "Enter the code\nat checkout." ) );
ck( 'and every other field as it was left: the link with its "&amp;", the audience, the threshold, the last day', array(
	wpcpm_test_value( $k_form, 'wpcpm_url' ),
	false !== strpos( $k_form, 'name="wpcpm_audience[]" value="mentors" checked="checked"' ),
	false !== strpos( $k_form, 'name="wpcpm_audience[]" value="managers" checked' ),
	wpcpm_test_value( $k_form, 'wpcpm_low' ),
	wpcpm_test_value( $k_form, 'wpcpm_expires' ),
), array( $k_link, true, false, '5', '2026-12-31' ) );
ck( 'with the room each box had when it was typed in', array( $k_boxes['title']['maxlength'] ?? null, $k_boxes['text']['maxlength'] ?? null, $k_boxes['instructions']['maxlength'] ?? null ), array( 120, 500, 4000 ) );
$k_again = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $A, $ctx ), $k_id );
ck( 'and only once: drawn again, the form shows the stored offer, folded as before', array( $k_again['title']['shown'] ?? null, $k_again['text']['shown'] ?? null, false !== strpos( card( 'WPCPM_Sponsor_Offers', $A, $ctx ), 'id="wpcpm-offer-' . $k_id . '">' ) ), array( 'Kept offer', 'One year free', true ) );

$r = post( $k_post( array( 'wpcpm_title' => 'Q&amp;A <b>x</b>' ) ), $k_save );
$k_boxes = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $A, $ctx ), $k_id );
ck( '"Q&amp;A <b>x</b>", whose "<b>" the cleaner takes, is refused and comes back in the text input exactly as typed, the "&amp;" five characters', array( $r[0], $k_boxes['title']['shown'] ?? null, $k_boxes['title']['markup'] ?? null ), array( 'offer-loss', 'Q&amp;A <b>x</b>', 'Q&amp;amp;A &lt;b&gt;x&lt;/b&gt;' ) );
$r1 = post( $k_post( array( 'wpcpm_text' => 'Get 10%cashback' ) ), $k_save );
$r2 = post( $k_post( array( 'wpcpm_instructions' => "Use it\r\n<b>today</b>" ) ), $k_save );
$k_boxes = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $A, $ctx ), $k_id );
ck( 'a text or instructions the cleaner would take from are refused the same way, each named by its own label', array( $r1[0], $r1[3], $r2[0], $r2[3] ), array( 'offer-loss', WPCPM_Typed_Text::loss_message( 'What you get, in a sentence or two' ), 'offer-loss', WPCPM_Typed_Text::loss_message( 'How to redeem it' ) ) );
ck( 'and the form gets the last one\'s typing: posting it again dropped what the first kept', array( $k_boxes['text']['shown'] ?? null, $k_boxes['instructions']['shown'] ?? null ), array( 'One year free', "Use it\n<b>today</b>" ) );

$r = post( $k_post( array( 'wpcpm_title' => $k_lossy ) ), $k_save );
$GLOBALS['uid'] = 1;
$k_manager = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $A, $ctx_admin ), $k_id );
$GLOBALS['uid'] = 5;
$k_html = card( 'WPCPM_Sponsor_Offers', $A, $ctx );
ck( 'another person never sees it, a manager drawing the same offer included, while the one who typed it does', array( $k_manager['title']['shown'] ?? null, wpcpm_test_boxes( $k_html, $k_id )['title']['shown'] ?? null ), array( 'Kept offer', $k_lossy ) );
$r = post( $k_post( array( 'wpcpm_title' => $k_lossy ) ), $k_save );
$k_html = card( 'WPCPM_Sponsor_Offers', $A, $ctx );
ck( 'and another form never does: the new-offer form and every other offer\'s form are drawn as stored', array( wpcpm_test_value( wpcpm_test_form( $k_html, 'new' ), 'wpcpm_title' ), wpcpm_test_boxes( $k_html, $mem_id )['title']['shown'] ?? null, wpcpm_test_boxes( $k_html, $k_id )['title']['shown'] ?? null ), array( '', WPCPM_Typed_Text::typed_text( WPCPM_Sponsor_Offers::read( $mem_id )['title'] ), $k_lossy ) );

$k_count = count( WPCPM_Sponsor_Offers::offers_of( $A ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 0, 'wpcpm_title' => 'A <b>bold</b> offer', 'wpcpm_kind' => 'shared', 'wpcpm_shared' => 'SEALED-CODE-1', 'wpcpm_text' => 'Plain words', 'wpcpm_instructions' => '', 'wpcpm_url' => '', 'wpcpm_low' => '', 'wpcpm_expires' => '' ), $k_save );
ck( 'a new offer the cleaner would take words from creates nothing', array( $r[0], $r[3], count( WPCPM_Sponsor_Offers::offers_of( $A ) ) ), array( 'offer-loss', WPCPM_Typed_Text::loss_message( 'Title' ), $k_count ) );
ck( 'and its shared code is not kept: the pool holds a code sealed, and a flash would hold it in the clear', strpos( serialize( $GLOBALS['flash'] ), 'SEALED-CODE-1' ), false );
$k_html = card( 'WPCPM_Sponsor_Offers', $A, $ctx );
$k_new  = wpcpm_test_form( $k_html, 'new' );
ck( 'its typing comes back in the new-offer form, the kind chosen with it, and in no offer\'s form', array( wpcpm_test_value( $k_new, 'wpcpm_title' ), false !== strpos( $k_new, 'name="wpcpm_kind" value="shared" checked="checked"' ), wpcpm_test_value( $k_new, 'wpcpm_shared' ), wpcpm_test_boxes( $k_html, $k_id )['title']['shown'] ?? null ), array( 'A <b>bold</b> offer', true, '', 'Kept offer' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 0, 'wpcpm_title' => 'A <b>bold</b> pool', 'wpcpm_kind' => 'codes', 'wpcpm_codes' => "SEALED-2\nSEALED-3", 'wpcpm_text' => '', 'wpcpm_instructions' => '', 'wpcpm_url' => '', 'wpcpm_low' => '', 'wpcpm_expires' => '' ), $k_save );
ck( 'nor are pasted codes', array( $r[0], strpos( serialize( $GLOBALS['flash'] ), 'SEALED-2' ) ), array( 'offer-loss', false ) );
$GLOBALS['uid'] = 1;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 0, 'wpcpm_title' => 'For <b>A</b> only', 'wpcpm_kind' => 'codes', 'wpcpm_text' => '', 'wpcpm_instructions' => '', 'wpcpm_url' => '', 'wpcpm_low' => '', 'wpcpm_expires' => '' ), $k_save );
$k_other = wpcpm_test_value( wpcpm_test_form( card( 'WPCPM_Sponsor_Offers', $B, $ctx_admin ), 'new' ), 'wpcpm_title' );
$k_own   = wpcpm_test_value( wpcpm_test_form( card( 'WPCPM_Sponsor_Offers', $A, $ctx_admin ), 'new' ), 'wpcpm_title' );
ck( 'a manager\'s typing on one sponsor\'s new-offer form never reaches another sponsor\'s', array( $r[0], $k_other, $k_own ), array( 'offer-loss', '', 'For <b>A</b> only' ) );
$GLOBALS['uid'] = 5;

$k_long = str_repeat( 'Long title ', 11 ) . 'and more words';
$r = post( $k_post( array( 'wpcpm_title' => $k_long, 'wpcpm_text' => 'Kept with it' ) ), $k_save );
$k_boxes = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $A, $ctx ), $k_id );
ck( 'a title over its limit is refused as before, and its form now comes back with the typing, the title kept up to the box\'s limit of 120', array( $r[0], $r[3], $k_boxes['title']['shown'] ?? null, $k_boxes['text']['shown'] ?? null ), array( 'offer-rejected', 'The title is 15 characters over the limit of 120. Shorten it and save again.', mb_substr( $k_long, 0, 120 ), 'Kept with it' ) );
$r = post( $k_post( array( 'wpcpm_text' => str_repeat( 'x', 501 ) ) ), $k_save );
$k_boxes = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $A, $ctx ), $k_id );
ck( 'and so does a text over its limit', array( $r[0], $r[3], $k_boxes['text']['shown'] ?? null ), array( 'offer-rejected', 'What you get is 1 character over the limit of 500. Shorten it and save again.', str_repeat( 'x', 500 ) ) );

$r = post( $k_post( array( 'wpcpm_title' => "Bad \xC3\x28 bytes", 'wpcpm_text' => 'Kept beside it' ) ), $k_save );
$k_boxes = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $A, $ctx ), $k_id );
ck( 'a title that is not valid UTF-8, which the cleaner empties, is refused as a loss and not kept: its box shows the stored title, and the text beside it is kept', array( $r[0], $k_boxes['title']['shown'] ?? null, $k_boxes['text']['shown'] ?? null ), array( 'offer-loss', 'Kept offer', 'Kept beside it' ) );

$r = post( $k_post( array( 'wpcpm_title' => $k_lossy ) ), $k_save );
$r = post( $k_post( array( 'wpcpm_low' => '7' ) ), $k_save );
$k_html = card( 'WPCPM_Sponsor_Offers', $A, $ctx );
ck( 'a typing a refusal kept goes when the form is posted again, so a save that went through before the page drew it is drawn as saved', array( $r[0], wpcpm_test_boxes( $k_html, $k_id )['title']['shown'] ?? null, wpcpm_test_value( wpcpm_test_form( $k_html, $k_id ), 'wpcpm_low' ) ), array( 'offer-saved', 'Kept offer', '7' ) );

$k_keeps = array( 'wpcpm_title' => 'We <3 WordPress', 'wpcpm_text' => "Ages 8 < 12 welcome, adults > 18 pay\r\nQ&A: \"blocks\" & themes, it's free, 100% off", 'wpcpm_instructions' => "a <\r\nline, &amp; and &copy; typed out, and \xF0\x9F\x98\x80" );
$r = post( $k_post( $k_keeps ), $k_save );
$k_got = WPCPM_Sponsor_Offers::read( $k_id );
ck( 'a typing the cleaner keeps whole saves as before: a "<3", a "<" before a space or a line break, quotes, "&", typed-out entities, a "%" and an emoji', array( $r[0], $k_got['title'], $k_got['text'], $k_got['instructions'] ), array( 'offer-saved', 'We &lt;3 WordPress', sanitize_textarea_field( $k_keeps['wpcpm_text'] ), sanitize_textarea_field( $k_keeps['wpcpm_instructions'] ) ) );
ck( 'and keeps nothing for the form', isset( $GLOBALS['flash'][ 'typed:offer:' . $A . ':' . $k_id ][5] ), false );
$r = post( $k_post( array( 'wpcpm_title' => $k_lossy, 'wpcpm_instructions' => "\r\nleading break" ) ), $k_save );
$k_boxes = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $A, $ctx ), $k_id );
ck( 'a kept typing that starts with a line break comes back with it: the parser drops the line feed right after a text area\'s opening tag, so the box writes one of its own', array( $r[0], $k_boxes['instructions']['shown'] ?? null ), array( 'offer-loss', "\nleading break" ) );

echo "\n=== A box posted back as it was drawn is the stored text, whatever is in it ===\n";
// This card's own save and the import store what the cleaner keeps. Another writer need not: here a
// tag and a percent octet the cleaner would take are held as they were written.
$GLOBALS['uid'] = 5; $GLOBALS['flash'] = array(); $GLOBALS['patched'] = array(); $GLOBALS['audit'] = array();
$s_text  = 'Use code <SAVE20>';
$s_instr = "Get 10%cashback\r\nat checkout.";
$s_id    = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Stored by another writer', 'kind' => 'codes', 'text' => $s_text, 'instructions' => $s_instr, 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
$r     = post( wpcpm_test_unedited( $A, $s_id, array( 'wpcpm_expires' => '2026-12-31' ) ), $k_save );
$s_got = WPCPM_Sponsor_Offers::read( $s_id );
ck( 'an offer whose text holds "<SAVE20>" and whose instructions hold "10%cashback", posted back as drawn with only the last day changed, saves', array( $r[0], $s_got['expires'] ), array( 'offer-saved', '2026-12-31' ) );
ck( 'and the two keep their bytes: a box posted back as drawn is neither asked about losses nor rewritten', array( $s_got['text'] === $s_text, $s_got['instructions'] === $s_instr ), array( true, true ) );
$r = post( wpcpm_test_unedited( $A, $s_id, array( 'wpcpm_text' => 'Use code <SAVE20> now' ) ), $k_save );
ck( 'the same box edited to "Use code <SAVE20> now" is refused, and nothing is stored', array( $r[0], $r[3], WPCPM_Sponsor_Offers::read( $s_id )['text'] === $s_text ), array( 'offer-loss', WPCPM_Typed_Text::loss_message( 'What you get, in a sentence or two' ), true ) );
card( 'WPCPM_Sponsor_Offers', $A, $ctx );
$r = post( wpcpm_test_unedited( $A, $s_id, array( 'wpcpm_instructions' => "Get 10%cashback\r\nat checkout, now." ) ), $k_save );
ck( 'and so are the instructions, edited', array( $r[0], $r[3], WPCPM_Sponsor_Offers::read( $s_id )['instructions'] === $s_instr ), array( 'offer-loss', WPCPM_Typed_Text::loss_message( 'How to redeem it' ), true ) );
card( 'WPCPM_Sponsor_Offers', $A, $ctx );
// The unedited test reads what was typed, never what the cleaner makes of it: a typing the cleaner
// cuts back to the stored text is an edit, and the words it would take are the person's.
$s_plain = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Stored plain', 'kind' => 'codes', 'text' => 'Use code SAVE20', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
$r       = post( wpcpm_test_unedited( $A, $s_plain, array( 'wpcpm_text' => 'Use code SAVE20<b></b>' ) ), $k_save );
ck( 'a stored "Use code SAVE20" typed as "Use code SAVE20<b></b>", which the cleaner cuts back to the stored text, is refused, and the stored text is unchanged', array( $r[0], $r[3], WPCPM_Sponsor_Offers::read( $s_plain )['text'] ), array( 'offer-loss', WPCPM_Typed_Text::loss_message( 'What you get, in a sentence or two' ), 'Use code SAVE20' ) );
card( 'WPCPM_Sponsor_Offers', $A, $ctx );

echo "\n=== A title drawn from a stored value with a line break inside it ===\n";
// A title can hold a line break: the first offer is seeded with the sponsor's name, which can be
// written on two lines in the base's grid. A text input drops the break from its value, so "Free
// hosting" and "for students" drawn as stored would post back as "Free hostingfor students". The
// break is drawn as a space, and a title posted back as it was drawn is the stored title.
$GLOBALS['uid'] = 5; $GLOBALS['flash'] = array(); $GLOBALS['patched'] = array(); $GLOBALS['audit'] = array();
$h_title = "Free hosting\nfor students";
$h_id    = WPCPM_Sponsor_Offers::create( $A, array( 'title' => $h_title, 'kind' => 'codes', 'text' => 'One year free', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
$h_post  = wpcpm_test_unedited( $A, $h_id, array( 'wpcpm_expires' => '2026-12-31' ) );
$GLOBALS['title_writes'] = 0;
$r     = post( $h_post, $k_save );
$h_got = WPCPM_Sponsor_Offers::read( $h_id );
ck( 'a title stored as "Free hosting" and "for students" on two lines is drawn as "Free hosting for students"', $h_post['wpcpm_title'], 'Free hosting for students' );
ck( 'posted back as drawn with only the last day changed, it saves the day and keeps the title\'s stored bytes, with no title write', array( $r[0], $h_got['expires'], $h_got['title'], $GLOBALS['title_writes'] ), array( 'offer-saved', '2026-12-31', $h_title, 0 ) );
$GLOBALS['title_writes'] = 0;
$r = post( wpcpm_test_unedited( $A, $h_id, array( 'wpcpm_title' => 'Free hosting for students ', 'wpcpm_expires' => '2027-01-31' ) ), $k_save );
ck( 'and with a space typed at its end, which the cleaner takes off, it is still the stored title', array( $r[0], WPCPM_Sponsor_Offers::read( $h_id )['expires'], WPCPM_Sponsor_Offers::read( $h_id )['title'], $GLOBALS['title_writes'] ), array( 'offer-saved', '2027-01-31', $h_title, 0 ) );
// The unedited test is asked of what was typed, before the loss check: a stored title with a "%"
// the cleaner would take, drawn with its break as a space, is not refused for a box nobody touched.
$h_pct = "Get 10%cashback\nfor students";
$h_pid = WPCPM_Sponsor_Offers::create( $A, array( 'title' => $h_pct, 'kind' => 'codes', 'text' => 'One year free', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
$r     = post( wpcpm_test_unedited( $A, $h_pid, array( 'wpcpm_expires' => '2026-12-31' ) ), $k_save );
ck( 'a stored "Get 10%cashback" and "for students" on two lines, posted back as drawn with only the last day changed, saves and keeps its bytes', array( $r[0], WPCPM_Sponsor_Offers::read( $h_pid )['expires'], WPCPM_Sponsor_Offers::read( $h_pid )['title'] ), array( 'offer-saved', '2026-12-31', $h_pct ) );
card( 'WPCPM_Sponsor_Offers', $A, $ctx );
$GLOBALS['title_writes'] = 0;
$r = post( wpcpm_test_unedited( $A, $h_id, array( 'wpcpm_title' => 'Free hosting for all students' ) ), $k_save );
ck( 'edited, the title is written as the person left it', array( $r[0], WPCPM_Sponsor_Offers::read( $h_id )['title'], $GLOBALS['title_writes'] ), array( 'offer-saved', 'Free hosting for all students', 1 ) );

echo "\n=== The review: a member's offer, and a change to a live one, wait for an Administrator (1.123.18) ===\n";
// The partner page offers products at any level, and the Foundation decides how they are shared:
// so an offer a sponsor member makes, and a change a member makes to what students see of a live
// one, wait for an Administrator's review, the way a sponsor's post does. The Sponsor tiers switch
// is off here, as before the launch: the review is not a tier's and names none.
$Rv = 'WPCPM_Sponsor_Offer_Review';
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$GLOBALS['uid'] = 5; $GLOBALS['audit'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['flash'] = array();
$ctx_m   = array( 'can_manage' => false, 'open' => '', 'viewer' => $GLOBALS['users'][5] );
$ctx_mgr = array( 'can_manage' => true, 'open' => '', 'viewer' => $GLOBALS['users'][1] );
$k_state   = array( 'WPCPM_Sponsor_Offers', 'handle_state' );
$k_discard = array( 'WPCPM_Sponsor_Offers', 'handle_discard' );
$k_approve = array( $Rv, 'handle_approve' );
$k_return  = array( $Rv, 'handle_return' );
$rv_new = array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => '0', 'wpcpm_title' => 'Reviewed hosting', 'wpcpm_text' => 'A year of hosting.', 'wpcpm_instructions' => 'Use the link.', 'wpcpm_url' => 'https://plugins.mango-example.com/reviewed', 'wpcpm_kind' => 'shared', 'wpcpm_shared' => 'https://plugins.mango-example.com/checkout?c=REVIEW1', 'wpcpm_low' => '10', 'wpcpm_expires' => '' );
/** Whether a current student is shown an offer, and the words they are shown of it. */
function rv_student_sees( $offer_id ) {
	foreach ( WPCPM_Sponsor_Tools::offers_for( WPCPM_Sponsor_Tools::AUDIENCE_STUDENTS, 20 ) as $shown ) {
		if ( (int) $shown['id'] === (int) $offer_id ) {
			return array( $shown['title'], $shown['text'], $shown['url'], WPCPM_Sponsor_Codes::shared( $offer_id ) );
		}
	}
	return null;
}
/** The ids of what waits for review, oldest first. */
function rv_waiting() { return array_map( static function ( $row ) { return array( (int) $row['id'], $row['change'] ); }, WPCPM_Sponsor_Offer_Review::waiting_all() ); }
/** The fingerprint of what waits on an offer now, as its item's decision forms carry it (1.123.23); '' before there was one. */
function fp_now( $offer_id ) { return method_exists( 'WPCPM_Sponsor_Offer_Review', 'fingerprint' ) ? (string) WPCPM_Sponsor_Offer_Review::fingerprint( WPCPM_Sponsor_Offers::read( $offer_id ) ) : ''; }
/** A decision's fields as an item drawn now posts them: the offer, the fingerprint of what the item shows, and any more. */
function rv_fields( $offer_id, array $more = array() ) { return array_merge( array( 'wpcpm_offer' => $offer_id, 'wpcpm_seen' => fp_now( $offer_id ) ), $more ); }

ck( 'the review has its own state, Waiting for review, from which a sponsor may take the offer back or end it, and nobody but the program puts it live', array( WPCPM_Sponsor_Offers::STATE_REVIEW, in_array( 'review', WPCPM_Sponsor_Offers::states(), true ), WPCPM_Sponsor_Offers::transitions()['review'], WPCPM_Sponsor_Offers::state_label( 'review' ) ), array( 'review', true, array( 'draft', 'ended' ), 'Waiting for review' ) );
ck( 'approving and returning are an Administrator\'s alone, as publishing a post is', WPCPM_Sponsor_Policy::grounds()[ WPCPM_Sponsor_Policy::ACT_REVIEW_OFFER ], array( 'manager' ) );

$r     = post( $rv_new, $k_save );
$rv1   = wpcpm_test_newest( $A );
$rv1_o = WPCPM_Sponsor_Offers::read( $rv1 );
ck( 'a member\'s new offer is a draft that is not approved, and the card says it is reviewed before anybody sees it', array( $r[0], $rv1_o['state'], $rv1_o['approved'], WPCPM_Sponsor_Offers::messages()['offer-created-review'] ), array( 'offer-created-review', 'draft', false, array( 'success', 'Your offer was created as a draft. Send it for review when it is ready: students and mentors see it once an Administrator approves it.' ) ) );
$html = card( 'WPCPM_Sponsor_Offers', $A, $ctx_m );
$form = substr( $html, (int) strpos( $html, 'id="wpcpm-offer-state-' . $rv1 . '"' ), 900 );
ck( 'its state says it goes to the program when it is sent, and its button says Send for review', array( false !== strpos( $html, 'A draft: nobody but you sees it. Send it for review when it is ready: students and mentors see it once an Administrator approves it.' ), false !== strpos( $form, 'name="wpcpm_state" value="live">Send for review</button>' ) ), array( true, true ) );
ck( 'the New offer form says plainly that a new offer and a change wait for review, and names no tier while the switch is off', array( false !== strpos( $html, '<p class="wpcpm-student__note">A new offer, and every change to what students see of a live one, waits for an Administrator&#039;s review before students and mentors see it.</p>' ), preg_match( '/\btiers?\b/i', substr( $html, 0, (int) strpos( $html, '<form' ) ) ) ), array( true, 0 ) );

$r     = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $rv1, 'wpcpm_state' => 'live' ), $k_state );
$rv1_o = WPCPM_Sponsor_Offers::read( $rv1 );
ck( 'Send for review puts it in Waiting for review, stamped with who sent it and when, and nobody sees it: no student, no live count', array( $r[0], $rv1_o['state'], $rv1_o['submitted']['by'], $rv1_o['submitted']['at'] > 0, rv_student_sees( $rv1 ), WPCPM_Sponsor_Offers::is_live( $rv1_o ), WPCPM_Sponsor_Offers::messages()['offer-sent'][1] ), array( 'offer-sent', 'review', 5, true, null, false, 'Sent for review. Students and mentors see the offer once an Administrator approves it.' ) );
ck( 'and it is in the queue, as a new offer', rv_waiting(), array( array( $rv1, 'new' ) ) );
$html = card( 'WPCPM_Sponsor_Offers', $A, $ctx_m );
$form = substr( $html, (int) strpos( $html, 'id="wpcpm-offer-state-' . $rv1 . '"' ), 900 );
ck( 'the card shows it waiting, with Take it back and End this offer', array( false !== strpos( $html, '<span class="wpcpm-offer__state">Waiting for review</span>' ), false !== strpos( $html, 'Waiting for review: nobody but you sees it until an Administrator approves it.' ), false !== strpos( $form, 'value="draft">Take it back</button>' ), false !== strpos( $form, 'value="ended"' ), strpos( $form, 'value="live"' ) ), array( true, true, true, true, false ) );

/** The Offers card's line, as its fold shows it. */
function rv_line( $html ) { return preg_match( '#<span class="wpcpm-sponsor__state">([^<]*)</span>#', $html, $m ) ? $m[1] : null; }
ck( 'and the card\'s line, folded, says one offer waits for review beside the live ones and the claims', (bool) preg_match( '#^\d+ live, 1 waiting for review, \d+ claims? this month$#', (string) rv_line( $html ) ), true );

$r = post( rv_fields( $rv1 ), $k_approve );
ck( 'a member\'s own Approve is refused, and the offer still waits', array( $r[0], WPCPM_Sponsor_Offers::read( $rv1 )['state'] ), array( 'refused', 'review' ) );

$GLOBALS['uid'] = 1;
$r     = post( rv_fields( $rv1 ), $k_approve );
$rv1_o = WPCPM_Sponsor_Offers::read( $rv1 );
ck( 'an Administrator approves it: live and approved, out of the queue, and a current student sees it', array( $r[0], $rv1_o['state'], $rv1_o['approved'], $rv1_o['submitted'], rv_waiting(), rv_student_sees( $rv1 ) ), array( 'offer-approved', 'live', true, array(), array(), array( 'Reviewed hosting', 'A year of hosting.', 'https://plugins.mango-example.com/reviewed', 'https://plugins.mango-example.com/checkout?c=REVIEW1' ) ) );
ck( 'the approval is on the sponsor\'s trail, naming the offer and nothing it says', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['subject'], end( $GLOBALS['audit'] )['ground'], end( $GLOBALS['audit'] )['message'] ), array( 'offer_approved', (string) $rv1, 'manager', 'An offer was approved.' ) );
ck( 'once approved, the card\'s line says nothing waits', (bool) preg_match( '#^\d+ live, \d+ claims? this month$#', (string) rv_line( card( 'WPCPM_Sponsor_Offers', $A, $ctx_m ) ) ), true );
$r = post( rv_fields( $rv1 ), $k_approve );
ck( 'an offer with nothing waiting cannot be approved again', $r[0], 'offer-not-waiting' );

// A change to what students see of the live offer: its words, its link or its shared code.
$GLOBALS['uid'] = 5;
$r     = post( wpcpm_test_unedited( $A, $rv1, array( 'wpcpm_text' => 'Two years of hosting.', 'wpcpm_shared' => 'https://plugins.mango-example.com/checkout?c=REVIEW2' ) ), $k_save );
$rv1_o = WPCPM_Sponsor_Offers::read( $rv1 );
ck( 'a member\'s change to a live offer waits: the offer students see keeps its words and its code, and the change is held beside it', array( $r[0], $rv1_o['state'], $rv1_o['text'], WPCPM_Sponsor_Codes::shared( $rv1 ), $rv1_o['proposed']['text'], $rv1_o['proposed']['shared'], $rv1_o['submitted']['by'], rv_student_sees( $rv1 )[1] ), array( 'offer-changes-waiting', 'live', 'A year of hosting.', 'https://plugins.mango-example.com/checkout?c=REVIEW1', 'Two years of hosting.', 'https://plugins.mango-example.com/checkout?c=REVIEW2', 5, 'A year of hosting.' ) );
ck( 'the held shared code is sealed at rest, as the one students see is, and reads back as typed', array( false === strpos( serialize( get_post_meta( $rv1, WPCPM_Sponsor_Offers::META_PROPOSED, true ) ), 'REVIEW2' ), $rv1_o['proposed']['shared'] ), array( true, 'https://plugins.mango-example.com/checkout?c=REVIEW2' ) );
ck( 'the change is in the queue, as changes, each field with what students see now and what it would be', array( rv_waiting(), WPCPM_Sponsor_Offer_Review::waiting_all()[0]['fields'] ), array( array( array( $rv1, 'changes' ) ), array( array( 'What you get', 'A year of hosting.', 'Two years of hosting.' ), array( 'The shared code or link', 'https://plugins.mango-example.com/checkout?c=REVIEW1', 'https://plugins.mango-example.com/checkout?c=REVIEW2' ) ) ) );
$html  = card( 'WPCPM_Sponsor_Offers', $A, $ctx_m );
$boxes = wpcpm_test_boxes( $html, $rv1 );
ck( 'the card says the change waits and students see the offer as it reads, its form draws the change, and Discard the changes is offered', array( false !== strpos( $html, 'Your changes wait for an Administrator&#039;s review. Until they are approved, students and mentors see the offer as it reads above.' ), $boxes['text']['shown'], false !== strpos( $html, 'value="wpcpm_offer_discard"' ), false !== strpos( $html, '>Discard the changes</button>' ) ), array( true, 'Two years of hosting.', true, true ) );
ck( 'the card\'s line counts the waiting change as one waiting for review', (bool) preg_match( '#^\d+ live, 1 waiting for review, #', (string) rv_line( $html ) ), true );
$r = post( wpcpm_test_unedited( $A, $rv1, array( 'wpcpm_expires' => '2027-06-30' ) ), $k_save );
ck( 'a change of the last day alone takes effect at once, and the waiting change stays as it was', array( $r[0], WPCPM_Sponsor_Offers::read( $rv1 )['expires'], WPCPM_Sponsor_Offers::read( $rv1 )['proposed']['text'], rv_waiting() ), array( 'offer-changes-waiting', '2027-06-30', 'Two years of hosting.', array( array( $rv1, 'changes' ) ) ) );

// Returned with a note: the offer stays as students see it, the change stays the member's to mend.
$GLOBALS['uid'] = 1; $GLOBALS['sent'] = array();
$r = post( rv_fields( $rv1, array( 'wpcpm_note' => '' ) ), $k_return );
ck( 'a return needs a note, and nothing moves without one', array( $r[0], rv_waiting() ), array( 'offer-note-missing', array( array( $rv1, 'changes' ) ) ) );
$r = post( rv_fields( $rv1, array( 'wpcpm_note' => str_repeat( 'n', 2001 ) ) ), $k_return );
ck( 'and one longer than 2,000 characters as typed is refused, nothing moved', array( $r[0], rv_waiting() ), array( 'offer-note-long', array( array( $rv1, 'changes' ) ) ) );
$r     = post( rv_fields( $rv1, array( 'wpcpm_note' => 'Say how long the two years run.' ) ), $k_return );
$rv1_o = WPCPM_Sponsor_Offers::read( $rv1 );
ck( 'returned: the offer is live as it was, the change kept for the member with the note, out of the queue', array( $r[0], $rv1_o['state'], $rv1_o['text'], $rv1_o['proposed']['text'], $rv1_o['note'], $rv1_o['returned'] > 0, rv_waiting() ), array( 'offer-returned', 'live', 'A year of hosting.', 'Two years of hosting.', 'Say how long the two years run.', true, array() ) );
$mail = end( $GLOBALS['sent'] );
ck( 'the member who sent it is emailed the note, from the template registry, with the way to the Offers card', array( $mail[0], $mail[1], $mail[2], $mail[3]['subject'], false !== strpos( $mail[3]['body'], "Hello Rep One,\n\nAn Administrator read your offer \"Reviewed hosting\" and sent it back with this note:\n\nSay how long the two years run." ), false !== strpos( $mail[3]['body'], 'https://example.test/sponsor-dashboard/#wpcpm-sponsor-offers' ) ), array( 'user', 5, 'sponsor-offer-returned', 'Your offer "Reviewed hosting" needs a change before students see it', true, true ) );
ck( 'the return is on the trail, with the note\'s length and never the note', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data'], false !== strpos( (string) json_encode( end( $GLOBALS['audit'] ) ), 'two years' ) ), array( 'offer_returned', array( 'offer' => $rv1, 'change' => 'changes', 'note_length' => 31 ), false ) );
$GLOBALS['uid'] = 5;
$html = card( 'WPCPM_Sponsor_Offers', $A, $ctx_m );
ck( 'the card shows the returned change with its note, escaped, as a returned post shows', array( false !== strpos( $html, 'An Administrator returned your changes with a note. Students and mentors still see the offer as it reads above.' ), false !== strpos( $html, '<p class="wpcpm-offer__note">Say how long the two years run.</p>' ) ), array( true, true ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $rv1 ), $k_discard );
ck( 'Discard the changes drops the change and its note at once, and the form draws the offer as approved', array( $r[0], WPCPM_Sponsor_Offers::read( $rv1 )['proposed'], WPCPM_Sponsor_Offers::read( $rv1 )['note'], wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $A, $ctx_m ), $rv1 )['text']['shown'] ), array( 'offer-changes-discarded', array(), '', 'A year of hosting.' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $rv1 ), $k_discard );
ck( 'with no change waiting there is nothing to discard', $r[0], 'offer-no-changes' );
$r = post( wpcpm_test_unedited( $A, $rv1, array( 'wpcpm_text' => 'Two years of hosting, from the day you claim.' ) ), $k_save );
$GLOBALS['uid'] = 1;
$r = post( rv_fields( $rv1 ), $k_approve );
ck( 'sent again and approved, the change is what students see now, and nothing is held any more', array( $r[0], WPCPM_Sponsor_Offers::read( $rv1 )['text'], WPCPM_Sponsor_Offers::read( $rv1 )['proposed'], rv_student_sees( $rv1 )[1] ), array( 'offer-approved', 'Two years of hosting, from the day you claim.', array(), 'Two years of hosting, from the day you claim.' ) );

// A new offer returned with a note goes back to the member as a draft.
$GLOBALS['uid'] = 5;
post( array_merge( $rv_new, array( 'wpcpm_title' => 'Second reviewed offer', 'wpcpm_shared' => 'SECOND-CODE' ) ), $k_save );
$rv2 = wpcpm_test_newest( $A );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $rv2, 'wpcpm_state' => 'live' ), $k_state );
$GLOBALS['uid'] = 1; $GLOBALS['sent'] = array();
$r     = post( rv_fields( $rv2, array( 'wpcpm_note' => 'Add how to redeem it & a "link".' ) ), $k_return );
$rv2_o = WPCPM_Sponsor_Offers::read( $rv2 );
ck( 'a new offer returned goes back to the member as a draft with the note, unapproved, and the member is emailed', array( $r[0], $rv2_o['state'], $rv2_o['approved'], $rv2_o['note'], end( $GLOBALS['sent'] )[2] ), array( 'offer-returned', 'draft', false, 'Add how to redeem it & a "link".', 'sponsor-offer-returned' ) );
$GLOBALS['uid'] = 5;
$html = card( 'WPCPM_Sponsor_Offers', $A, $ctx_m );
ck( 'its card says it was returned and shows the note, escaped', array( false !== strpos( $html, 'Returned by the program with a note: change the offer, then send it for review again.' ), false !== strpos( $html, '<p class="wpcpm-offer__note">Add how to redeem it &amp; a &quot;link&quot;.</p>' ) ), array( true, true ) );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $rv2, 'wpcpm_state' => 'live' ), $k_state );
ck( 'sent for review again, it waits once more, and the old note is gone', array( WPCPM_Sponsor_Offers::read( $rv2 )['state'], WPCPM_Sponsor_Offers::read( $rv2 )['note'], rv_waiting() ), array( 'review', '', array( array( $rv2, 'new' ) ) ) );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $rv2, 'wpcpm_state' => 'draft' ), $k_state );
ck( 'Take it back makes it a draft again at once, out of the queue', array( WPCPM_Sponsor_Offers::read( $rv2 )['state'], rv_waiting() ), array( 'draft', array() ) );
// A waiting offer whose codes were voided has nothing to give: it cannot be approved.
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $rv2, 'wpcpm_state' => 'live' ), $k_state );
WPCPM_Sponsor_Codes::set_shared( $rv2, '' );
$GLOBALS['uid'] = 1;
$r = post( rv_fields( $rv2 ), $k_approve );
ck( 'a waiting offer with nothing left to give is not approved, and still waits', array( $r[0], WPCPM_Sponsor_Offers::read( $rv2 )['state'] ), array( 'offer-approve-empty', 'review' ) );
WPCPM_Sponsor_Offers::set_state( $rv2, 'ended' );

// Switching off and ending take effect at once, with no review.
$GLOBALS['uid'] = 5;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $rv1, 'wpcpm_state' => 'paused' ), $k_state );
ck( 'a member pauses a live offer at once: nobody sees it, nothing waits', array( $r[0], WPCPM_Sponsor_Offers::read( $rv1 )['state'], rv_student_sees( $rv1 ), rv_waiting() ), array( 'offer-state-saved', 'paused', null, array() ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $rv1, 'wpcpm_state' => 'live' ), $k_state );
ck( 'and resumes it at once: what was approved needs no second review', array( $r[0], WPCPM_Sponsor_Offers::read( $rv1 )['state'], rv_student_sees( $rv1 )[1] ), array( 'offer-state-saved', 'live', 'Two years of hosting, from the day you claim.' ) );

// The program's own staff need no review, here as on the Sponsors screen.
$GLOBALS['uid'] = 1;
$r    = post( array_merge( $rv_new, array( 'wpcpm_title' => 'Made by the program', 'wpcpm_shared' => 'PROGRAM-CODE' ) ), $k_save );
$rv3  = wpcpm_test_newest( $A );
$r2   = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $rv3, 'wpcpm_state' => 'live' ), $k_state );
$r3   = post( wpcpm_test_unedited( $A, $rv3, array( 'wpcpm_text' => 'Changed by the program.' ) ), $k_save );
ck( 'an Administrator\'s offer is approved as it is made, goes live at the press, and a change of theirs is what students see at once', array( $r[0], $r2[0], $r3[0], WPCPM_Sponsor_Offers::read( $rv3 )['state'], WPCPM_Sponsor_Offers::read( $rv3 )['proposed'], rv_student_sees( $rv3 )[1], rv_waiting() ), array( 'offer-created', 'offer-state-saved', 'offer-saved', 'live', array(), 'Changed by the program.', array() ) );
WPCPM_Sponsor_Offers::set_state( $rv3, 'ended' );
$seeded = WPCPM_Sponsor_Offers::create( $B, array( 'title' => 'Seeded by the program', 'kind' => 'codes', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ), false, true );
ck( 'what the program seeds is approved as it is made', WPCPM_Sponsor_Offers::read( $seeded )['approved'], true );
wp_delete_post( $seeded, true );

// An offer that was live before this release has no approval stamp: it reads as approved and stays
// live, and a member's change to it waits like any other. A draft from before reads as not approved.
$GLOBALS['uid'] = 5;
$legacy = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Live before the review', 'kind' => 'shared', 'text' => 'Old words.', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
WPCPM_Sponsor_Codes::set_shared( $legacy, 'LEGACY-CODE' );
update_post_meta( $legacy, WPCPM_Sponsor_Offers::META_STATE, WPCPM_Sponsor_Offers::STATE_LIVE );
delete_post_meta( $legacy, WPCPM_Sponsor_Offers::META_APPROVED );
$legacy_draft = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Draft before the review', 'kind' => 'codes', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
delete_post_meta( $legacy_draft, WPCPM_Sponsor_Offers::META_APPROVED );
ck( 'an offer live before this release reads as approved and a student sees it; a draft from before reads as not approved', array( WPCPM_Sponsor_Offers::read( $legacy )['approved'], rv_student_sees( $legacy )[1], WPCPM_Sponsor_Offers::read( $legacy_draft )['approved'] ), array( true, 'Old words.', false ) );
$r = post( wpcpm_test_unedited( $A, $legacy, array( 'wpcpm_text' => 'New words.' ) ), $k_save );
ck( 'and a member\'s change to it waits for review like any other, students still reading the old words', array( $r[0], rv_student_sees( $legacy )[1], WPCPM_Sponsor_Offers::read( $legacy )['proposed']['text'] ), array( 'offer-changes-waiting', 'Old words.', 'New words.' ) );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $legacy ), $k_discard );
// A paused or a dormant offer from before reads as approved too: resumed by its sponsor, or woken by
// the daily run, it goes live with no review, as it would have before the update.
$legacy_paused  = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Paused before the review', 'kind' => 'codes', 'text' => 'Paused words.', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
$legacy_dormant = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Dormant before the review', 'kind' => 'codes', 'text' => 'Dormant words.', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
update_post_meta( $legacy_paused, WPCPM_Sponsor_Offers::META_STATE, WPCPM_Sponsor_Offers::STATE_PAUSED );
update_post_meta( $legacy_dormant, WPCPM_Sponsor_Offers::META_STATE, WPCPM_Sponsor_Offers::STATE_DORMANT );
delete_post_meta( $legacy_paused, WPCPM_Sponsor_Offers::META_APPROVED );
delete_post_meta( $legacy_dormant, WPCPM_Sponsor_Offers::META_APPROVED );
ck( 'a paused and a dormant offer from before the release read as approved, and nothing of either waits', array( WPCPM_Sponsor_Offers::read( $legacy_paused )['approved'], WPCPM_Sponsor_Offers::read( $legacy_dormant )['approved'], WPCPM_Sponsor_Offers::is_waiting( WPCPM_Sponsor_Offers::read( $legacy_paused ) ), WPCPM_Sponsor_Offers::is_waiting( WPCPM_Sponsor_Offers::read( $legacy_dormant ) ) ), array( true, true, false, false ) );
foreach ( array( $legacy, $legacy_draft, $legacy_paused, $legacy_dormant, $rv1, $rv2, $rv3 ) as $rv_id ) {
	WPCPM_Sponsor_Codes::delete( $rv_id );
	wp_delete_post( $rv_id, true );
}

// The decision's forms, as the Administrator Dashboard draws them.
$GLOBALS['uid'] = 5;
post( array_merge( $rv_new, array( 'wpcpm_title' => 'Drawn & "decided"' ) ), $k_save );
$rv4 = wpcpm_test_newest( $A );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $rv4, 'wpcpm_state' => 'live' ), $k_state );
$GLOBALS['uid'] = 1;
ob_start();
WPCPM_Sponsor_Offer_Review::render_item( WPCPM_Sponsor_Offer_Review::waiting_all()[0], 'dashboard' );
$item = ob_get_clean();
ck( 'an item reads the offer as students would, then Approve and the folded Return with a note, in the posts\' own words', array(
	false !== strpos( $item, '<article class="wpcpm-administrator__item wpcpm-sponsor-offer" id="wpcpm-sponsor-offer-' . $rv4 . '">' ),
	false !== strpos( $item, 'Drawn &amp; &quot;decided&quot;' ),
	false !== strpos( $item, 'A new offer' ),
	false !== strpos( $item, '<dt>What you get</dt><dd>A year of hosting.</dd>' ),
	false !== strpos( $item, 'value="wpcpm_offer_approve"' ) && false !== strpos( $item, '>Approve</button>' ),
	false !== strpos( $item, '<summary class="button">Return with a note</summary>' ),
	false !== strpos( $item, '>Return it with this note</button>' ),
	false !== strpos( $item, 'name="wpcpm_return" value="dashboard"' ),
), array( true, true, true, true, true, true, true, true ) );
WPCPM_Sponsor_Codes::delete( $rv4 );
wp_delete_post( $rv4, true );
$GLOBALS['uid'] = 5;

echo "\n=== Codes a member adds to an approved offer wait for review (1.123.20) ===\n";
// A code can be a whole checkout link, so the codes a sponsor's member adds to an offer students can
// see, live, paused or dormant, wait for an Administrator's review, as a change to its words does:
// sealed and fingerprinted beside the pool and counted toward its ceiling, but handed to nobody,
// counted in nothing students see as left, and re-arming no low-stock warning until approved. Codes
// added to a draft or to an offer sent for review are read with the offer, and an Administrator's
// apply at once. The pool's shape gains a list of its own for them and keeps its version: a pool
// written by 1.123.19 reads as it did.
$k_codes = array( 'WPCPM_Sponsor_Offers', 'handle_codes_add' );
/** One offer's own block of the Offers card, from its disclosure to the next offer's. */
function hc_block( $html, $offer_id ) {
	$start = strpos( $html, 'id="wpcpm-offer-' . (int) $offer_id . '"' );
	if ( false === $start ) { return ''; }
	$next = strpos( $html, '<details class="wpcpm-offer wpcpm-offer--', $start + 10 );
	return false === $next ? substr( $html, $start ) : substr( $html, $start, $next - $start );
}
/** The review item an Administrator reads for one offer, drawn as the Administrator Dashboard draws it. */
function hc_item( $offer_id ) {
	foreach ( WPCPM_Sponsor_Offer_Review::waiting_all() as $row ) {
		if ( (int) $row['id'] === (int) $offer_id ) {
			ob_start();
			WPCPM_Sponsor_Offer_Review::render_item( $row, 'dashboard' );
			return (string) ob_get_clean();
		}
	}
	return '';
}
/** The Codes added block of an item, or '' when it has none. */
function hc_codes_block( $item ) { return preg_match( '#<div class="wpcpm-offer__detail wpcpm-sponsor-offer__codes-added">.*?</dd></div>#s', $item, $m ) ? $m[0] : ''; }

// The pool's own rules first.
ck( 'the pool keeps its version: held codes are a list beside the others, which a pool written by 1.123.19 reads as empty', array( WPCPM_Sponsor_Codes::VERSION, WPCPM_Sponsor_Codes::empty_pool()['held'] ), array( 1, array() ) );
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::option_name( 778 ) ] = array( 'v' => 1, 'shared' => '', 'codes' => array( array( 's' => WPCPM_Secret::seal_for_option( 'OLD-1' ), 'h' => WPCPM_Secret::fingerprint( 'OLD-1' ), 'st' => 'available', 'by' => 0, 'at' => 0 ) ), 'claims' => array() );
$old_pool = WPCPM_Sponsor_Codes::read( 778 );
ck( 'a pool written by 1.123.19, with no held list, reads whole: its code, its counts and nothing held', array( WPCPM_Sponsor_Codes::code_at( 778, 0 ), WPCPM_Sponsor_Codes::counts( 778 ), $old_pool['held'], WPCPM_Sponsor_Codes::held_count( 778 ) ), array( 'OLD-1', array( 'available' => 1, 'claimed' => 0, 'void' => 0, 'total' => 1 ), array(), 0 ) );
ck( 'codes held for review go in beside it, sealed and fingerprinted: nothing in the clear, the pool still version 1, and the old code still the only one available', array( WPCPM_Sponsor_Codes::add( 778, "HOLD-1\nhttps://shop.example/hold?c=2", true ), strpos( serialize( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::option_name( 778 ) ] ), 'HOLD-1' ), $GLOBALS['opts'][ WPCPM_Sponsor_Codes::option_name( 778 ) ]['v'], WPCPM_Sponsor_Codes::counts( 778 ), WPCPM_Sponsor_Codes::held_count( 778 ), WPCPM_Sponsor_Codes::held_codes( 778 ), WPCPM_Sponsor_Codes::available_codes( 778 ) ), array( 2, false, 1, array( 'available' => 1, 'claimed' => 0, 'void' => 0, 'total' => 1 ), 2, array( 'HOLD-1', 'https://shop.example/hold?c=2' ), array( 'OLD-1' ) ) );
ck( 'a held code is in the offer for the duplicate check, held or not, by line', array( WPCPM_Sponsor_Codes::add( 778, "NEW-1\nHOLD-1" )->get_error_data(), WPCPM_Sponsor_Codes::add( 778, 'OLD-1', true )->get_error_data() ), array( array( 'Line 2 is already in this offer.' ), array( 'Line 1 is already in this offer.' ) ) );
ck( 'take() hands out the available code and never a held one, and then the pool is empty', array( WPCPM_Sponsor_Codes::take( 778, 20 ), err_of( WPCPM_Sponsor_Codes::take( 778, 23 ) ), WPCPM_Sponsor_Codes::held_count( 778 ) ), array( 0, 'wpcpm_codes_empty', 2 ) );
ck( 'and Void unclaimed codes voids nothing held: a held code is not in the pool yet', array( WPCPM_Sponsor_Codes::void_unclaimed( 778 ), WPCPM_Sponsor_Codes::held_count( 778 ) ), array( 0, 2 ) );
$big = WPCPM_Sponsor_Codes::read( 778 );
$big['codes'] = array_fill( 0, 4997, array( 's' => 'x', 'h' => 'h', 'st' => 'void', 'by' => 0, 'at' => 0 ) );
WPCPM_Sponsor_Codes::write( 779, $big );
ck( 'held codes count toward the five thousand an offer holds', array( err_of( WPCPM_Sponsor_Codes::add( 779, "ONE\nTWO" ) ), WPCPM_Sponsor_Codes::add( 779, 'ONE', true ) ), array( 'wpcpm_codes_max', 1 ) );
WPCPM_Sponsor_Codes::delete( 779 );
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . 778 ] = time();
ck( 'releasing, dropping and holding wait for the pool\'s lock like every rewrite, and move nothing while it is held', array( err_of( WPCPM_Sponsor_Codes::release_held( 778 ) ), err_of( WPCPM_Sponsor_Codes::drop_held( 778 ) ), err_of( WPCPM_Sponsor_Codes::add( 778, 'LOCKED-1', true ) ), WPCPM_Sponsor_Codes::held_count( 778 ) ), array( 'wpcpm_codes_busy', 'wpcpm_codes_busy', 'wpcpm_codes_busy', 2 ) );
unset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . 778 ] );
ck( 'released, the held codes join the pool as available, in the order they were added, and the lock is let go', array( WPCPM_Sponsor_Codes::release_held( 778 ), WPCPM_Sponsor_Codes::counts( 778 ), WPCPM_Sponsor_Codes::code_at( 778, 1 ), WPCPM_Sponsor_Codes::code_at( 778, 2 ), WPCPM_Sponsor_Codes::held_count( 778 ), isset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . 778 ] ) ), array( 2, array( 'available' => 2, 'claimed' => 1, 'void' => 0, 'total' => 3 ), 'HOLD-1', 'https://shop.example/hold?c=2', 0, false ) );
ck( 'a second release finds nothing held, says so with a 0 and writes nothing', array( WPCPM_Sponsor_Codes::release_held( 778 ), WPCPM_Sponsor_Codes::counts( 778 )['total'] ), array( 0, 3 ) );
WPCPM_Sponsor_Codes::add( 778, "DROP-1\nDROP-2", true );
ck( 'dropped, held codes leave the pool entirely, not as void, so the same codes can be added again', array( WPCPM_Sponsor_Codes::drop_held( 778 ), WPCPM_Sponsor_Codes::counts( 778 ), WPCPM_Sponsor_Codes::held_count( 778 ), WPCPM_Sponsor_Codes::drop_held( 778 ), WPCPM_Sponsor_Codes::add( 778, 'DROP-1', true ) ), array( 2, array( 'available' => 2, 'claimed' => 1, 'void' => 0, 'total' => 3 ), 0, 0, 1 ) );
WPCPM_Sponsor_Codes::delete( 778 );

// Through the Offers card: an approved pool, live, with its low-stock warning already sent.
$GLOBALS['uid'] = 1; $GLOBALS['audit'] = array(); $GLOBALS['sent'] = array();
$hc_fields = array( 'title' => 'Held codes pool', 'kind' => 'codes', 'text' => 'A seat in the course.', 'instructions' => 'Use the code at checkout.', 'url' => '', 'audience' => array(), 'low' => 3, 'expires' => '' );
$hc        = WPCPM_Sponsor_Offers::create( $A, $hc_fields, false, true );
WPCPM_Sponsor_Codes::add( $hc, "HC-1\nHC-2" );
WPCPM_Sponsor_Offers::set_state( $hc, 'live' );
update_post_meta( $hc, WPCPM_Sponsor_Offers::META_LOW_SENT, 12345 );
$GLOBALS['uid'] = 5;
ck( 'who waits: a member\'s codes added to an offer students see or could see again, live, paused or dormant, and nobody else\'s', array(
	WPCPM_Sponsor_Offers::codes_wait( array_merge( WPCPM_Sponsor_Offers::read( $hc ), array( 'state' => 'live' ) ), false ),
	WPCPM_Sponsor_Offers::codes_wait( array_merge( WPCPM_Sponsor_Offers::read( $hc ), array( 'state' => 'paused' ) ), false ),
	WPCPM_Sponsor_Offers::codes_wait( array_merge( WPCPM_Sponsor_Offers::read( $hc ), array( 'state' => 'dormant' ) ), false ),
	WPCPM_Sponsor_Offers::codes_wait( array_merge( WPCPM_Sponsor_Offers::read( $hc ), array( 'state' => 'draft' ) ), false ),
	WPCPM_Sponsor_Offers::codes_wait( array_merge( WPCPM_Sponsor_Offers::read( $hc ), array( 'state' => 'review' ) ), false ),
	WPCPM_Sponsor_Offers::codes_wait( array_merge( WPCPM_Sponsor_Offers::read( $hc ), array( 'state' => 'ended' ) ), false ),
	WPCPM_Sponsor_Offers::codes_wait( WPCPM_Sponsor_Offers::read( $hc ), true ),
), array( true, true, true, false, false, false, false ) );
$r    = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc, 'wpcpm_codes' => "https://shop.example/checkout?c=HELD1\nHC-3" ), $k_codes );
$hc_o = WPCPM_Sponsor_Offers::read( $hc );
ck( 'a member adds codes to the live offer: the press says they wait and how many, and the pool hands out what it had before', array( $r[0], $r[3], WPCPM_Sponsor_Codes::counts( $hc ), WPCPM_Sponsor_Codes::held_count( $hc ), $hc_o['codes_held'], $hc_o['submitted']['by'] ), array( 'codes-waiting', '2 codes wait for review.', array( 'available' => 2, 'claimed' => 0, 'void' => 0, 'total' => 2 ), 2, 2, 5 ) );
ck( 'the message says they wait for an Administrator\'s review instead of saying they were added', WPCPM_Sponsor_Offers::messages()['codes-waiting'], array( 'success', 'Your codes were saved and wait for an Administrator\'s review. Until they are approved, nobody can claim them.' ) );
ck( 'the low-stock stamp stays until they are approved, and the offer is in the queue, as changes', array( $hc_o['low_sent'], in_array( array( $hc, 'changes' ), rv_waiting(), true ), WPCPM_Sponsor_Offers::is_waiting( $hc_o ) ), array( 12345, true, true ) );
ck( 'the press is on the trail as codes that wait, naming no code', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data'], end( $GLOBALS['audit'] )['message'], strpos( serialize( $GLOBALS['audit'] ), 'HELD1' ) ), array( 'offer_codes', array( 'offer' => $hc, 'added' => 2, 'waiting' => true ), '2 codes were added to an offer and wait for review.', false ) );
$html  = card( 'WPCPM_Sponsor_Offers', $A, $ctx_m );
$block = hc_block( $html, $hc );
ck( 'the offer on the card says how many codes wait for review, the counts keep to what can be claimed, and Discard the changes is offered with a question that names the codes', array(
	false !== strpos( $block, '<div class="wpcpm-offer__changes"><p class="wpcpm-student__note">2 codes you added wait for an Administrator&#039;s review. Until they are approved, nobody can claim them.</p>' ),
	false !== strpos( $block, '<span class="wpcpm-offer__counts">2 available, 0 claimed, 0 void</span>' ),
	false !== strpos( $block, 'data-wpcpm-confirm="Discard your changes and the codes that wait for review? The offer stays as it reads above."' ),
	false !== strpos( $block, '>Discard the changes</button>' ),
	strpos( $block, 'HELD1' ),
), array( true, true, true, true, false ) );

// Nobody is handed a held code, and nothing counts them as left.
$hc_first  = WPCPM_Sponsor_Claims::claim( $hc, $GLOBALS['users'][20] );
$hc_second = WPCPM_Sponsor_Claims::claim( $hc, $GLOBALS['users'][23] );
ck( 'students claim the two codes the pool had, and the third claimant meets an empty pool while two wait for review', array( $hc_first['code'], $hc_second['code'], err_of( WPCPM_Sponsor_Claims::claim( $hc, $GLOBALS['users'][24] ) ), WPCPM_Sponsor_Codes::counts( $hc )['available'], WPCPM_Sponsor_Codes::held_count( $hc ), WPCPM_Sponsor_Offers::read( $hc )['low_sent'] ), array( 'HC-1', 'HC-2', 'wpcpm_claim_empty', 0, 2, 12345 ) );

// What the Administrator reads: the codes themselves, web addresses first, as text.
$GLOBALS['uid'] = 1;
$item = hc_item( $hc );
ck( 'the review item says it is changes to a live offer and lists the codes added under Codes added: how many, how many are web addresses, and the codes, web addresses first, as text and never as a link', array(
	false !== strpos( $item, 'Changes to a live offer' ),
	hc_codes_block( $item ),
), array( true, '<div class="wpcpm-offer__detail wpcpm-sponsor-offer__codes-added"><dt>Codes added</dt><dd><p>2 codes in all. Of these, 1 is a web address, listed first.</p><ul class="wpcpm-sponsor-offer__codes"><li><code>https://shop.example/checkout?c=HELD1</code></li><li><code>HC-3</code></li></ul></dd></div>' ) );
ck( 'the fold for a return says the codes added are taken out of the offer', false !== strpos( $item, 'The codes added are taken out of the offer.' ), true );
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $hc ] = time();
$r = post( rv_fields( $hc ), $k_approve );
unset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $hc ] );
ck( 'an Approve that meets the pool\'s lock is told to try again, and the codes still wait', array( $r[0], WPCPM_Sponsor_Codes::held_count( $hc ), WPCPM_Sponsor_Offers::is_waiting( WPCPM_Sponsor_Offers::read( $hc ) ) ), array( 'offer-review-failed', 2, true ) );
$r    = post( rv_fields( $hc ), $k_approve );
$hc_o = WPCPM_Sponsor_Offers::read( $hc );
ck( 'approved: the codes join the pool as available, the low-stock stamp is cleared as an addition clears it, and nothing waits', array( $r[0], WPCPM_Sponsor_Codes::counts( $hc ), WPCPM_Sponsor_Codes::held_count( $hc ), $hc_o['codes_held'], $hc_o['low_sent'], $hc_o['submitted'], WPCPM_Sponsor_Offers::is_waiting( $hc_o ), in_array( array( $hc, 'changes' ), rv_waiting(), true ) ), array( 'offer-approved', array( 'available' => 2, 'claimed' => 2, 'void' => 0, 'total' => 4 ), 0, 0, 0, array(), false, false ) );
ck( 'the approval is on the trail with how many codes it released', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data'] ), array( 'offer_approved', array( 'offer' => $hc, 'change' => 'changes', 'codes' => 2 ) ) );
ck( 'and the next claimant is handed one of them', WPCPM_Sponsor_Claims::claim( $hc, $GLOBALS['users'][24] )['code'], 'https://shop.example/checkout?c=HELD1' );
$r = post( rv_fields( $hc ), $k_approve );
ck( 'a second Approve, another Administrator\'s that found it already approved, does nothing and says so', array( $r[0], WPCPM_Sponsor_Codes::counts( $hc )['total'] ), array( 'offer-not-waiting', 4 ) );

// Returned with a note: the codes leave the pool entirely, and the email goes as for any return.
$GLOBALS['uid'] = 5;
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc, 'wpcpm_codes' => "RET-1\nRET-2\nRET-3" ), $k_codes );
$GLOBALS['uid'] = 1; $GLOBALS['sent'] = array();
$r    = post( rv_fields( $hc, array( 'wpcpm_note' => 'Use single-use codes, not the shop link.' ) ), $k_return );
$hc_o = WPCPM_Sponsor_Offers::read( $hc );
$mail = end( $GLOBALS['sent'] );
ck( 'returned: the held codes are dropped, not kept as void, the offer is live as it was and out of the queue, and the member is mailed the note', array( $r[0], WPCPM_Sponsor_Codes::counts( $hc ), WPCPM_Sponsor_Codes::held_count( $hc ), $hc_o['state'], $hc_o['codes_held'], $hc_o['codes_returned'], WPCPM_Sponsor_Offers::is_waiting( $hc_o ), $mail[1], $mail[2], false !== strpos( $mail[3]['body'], 'Use single-use codes, not the shop link.' ) ), array( 'offer-returned', array( 'available' => 1, 'claimed' => 3, 'void' => 0, 'total' => 4 ), 0, 'live', 0, 3, false, 5, 'sponsor-offer-returned', true ) );
ck( 'the return is on the trail with how many codes it took out', end( $GLOBALS['audit'] )['data'], array( 'offer' => $hc, 'change' => 'changes', 'note_length' => 40, 'codes' => 3 ) );
$GLOBALS['uid'] = 5;
$block = hc_block( card( 'WPCPM_Sponsor_Offers', $A, $ctx_m ), $hc );
ck( 'the card says the codes were returned and taken out, with the note, escaped, and offers nothing to discard', array(
	false !== strpos( $block, '<p class="wpcpm-student__note">An Administrator returned the codes you added with a note. Students and mentors still see the offer as it reads above.</p><p class="wpcpm-student__note">3 codes you added were taken out of the offer.</p><p class="wpcpm-offer__note">Use single-use codes, not the shop link.</p>' ),
	strpos( $block, 'Discard the changes' ),
), array( true, false ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc, 'wpcpm_codes' => 'RET-1' ), $k_codes );
ck( 'a returned code can be added again, and it waits once more, the old note gone', array( $r[0], WPCPM_Sponsor_Codes::held_count( $hc ), WPCPM_Sponsor_Offers::read( $hc )['note'], WPCPM_Sponsor_Offers::read( $hc )['codes_returned'], WPCPM_Sponsor_Offers::is_waiting( WPCPM_Sponsor_Offers::read( $hc ) ) ), array( 'codes-waiting', 1, '', 0, true ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc ), $k_discard );
ck( 'the member\'s Discard the changes drops the held codes too, at once', array( $r[0], WPCPM_Sponsor_Codes::held_count( $hc ), WPCPM_Sponsor_Codes::counts( $hc )['total'], WPCPM_Sponsor_Offers::is_waiting( WPCPM_Sponsor_Offers::read( $hc ) ), WPCPM_Sponsor_Offers::read( $hc )['submitted'] ), array( 'offer-changes-discarded', 0, 4, false, array() ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc ), $k_discard );
ck( 'and with nothing held there is nothing to discard', $r[0], 'offer-no-changes' );
$GLOBALS['uid'] = 1;
$r = post( rv_fields( $hc, array( 'wpcpm_note' => 'Too late.' ) ), $k_return );
ck( 'a return that finds nothing waiting does nothing and says so', array( $r[0], WPCPM_Sponsor_Offers::read( $hc )['note'] ), array( 'offer-not-waiting', '' ) );

// A change to the words and codes added are one item, and one decision.
$GLOBALS['uid'] = 5;
post( wpcpm_test_unedited( $A, $hc, array( 'wpcpm_text' => 'Two seats in the course.' ) ), $k_save );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc, 'wpcpm_codes' => "BOTH-1\nBOTH-2" ), $k_codes );
$GLOBALS['uid'] = 1;
$item = hc_item( $hc );
ck( 'an offer with a held change and held codes is one item in the queue, showing both', array( count( array_filter( rv_waiting(), static function ( $w ) use ( $hc ) { return $w[0] === $hc; } ) ), false !== strpos( $item, '<dt>What you get</dt>' ), false !== strpos( $item, '<li><code>BOTH-1</code></li><li><code>BOTH-2</code></li>' ) ), array( 1, true, true ) );
$r    = post( rv_fields( $hc ), $k_approve );
$hc_o = WPCPM_Sponsor_Offers::read( $hc );
ck( 'one Approve puts both in place: the words students see and the codes they can claim', array( $r[0], $hc_o['text'], $hc_o['proposed'], WPCPM_Sponsor_Codes::counts( $hc )['available'], WPCPM_Sponsor_Codes::held_count( $hc ), rv_student_sees( $hc )[1] ), array( 'offer-approved', 'Two seats in the course.', array(), 3, 0, 'Two seats in the course.' ) );

// What waits, and what does not: an Administrator's codes, and codes for a draft or a new offer.
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc, 'wpcpm_codes' => 'STAFF-1' ), $k_codes );
ck( 'an Administrator\'s codes added on the Sponsor Dashboard apply at once, as everything an Administrator changes there does', array( $r[0], $r[3], WPCPM_Sponsor_Codes::counts( $hc )['available'], WPCPM_Sponsor_Codes::held_count( $hc ), WPCPM_Sponsor_Offers::is_waiting( WPCPM_Sponsor_Offers::read( $hc ) ), end( $GLOBALS['audit'] )['data']['waiting'] ), array( 'codes-added', '1 code added.', 4, 0, false, false ) );
$GLOBALS['uid'] = 5;
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => '0', 'wpcpm_title' => 'New pool, reviewed whole', 'wpcpm_text' => 'A plugin license.', 'wpcpm_instructions' => 'Paste it in.', 'wpcpm_url' => '', 'wpcpm_kind' => 'codes', 'wpcpm_codes' => "NEW-A\nNEW-B", 'wpcpm_low' => '1', 'wpcpm_expires' => '' ), $k_save );
$hc_new = wpcpm_test_newest( $A );
$r      = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc_new, 'wpcpm_codes' => 'NEW-C' ), $k_codes );
ck( 'a member\'s codes added to a draft apply at once: they are read with the offer', array( $r[0], WPCPM_Sponsor_Codes::counts( $hc_new )['available'], WPCPM_Sponsor_Codes::held_count( $hc_new ) ), array( 'codes-added', 3, 0 ) );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc_new, 'wpcpm_state' => 'live' ), $k_state );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc_new, 'wpcpm_codes' => 'www.shop.example/new-d' ), $k_codes );
ck( 'and so do codes added while the new offer waits for review', array( $r[0], WPCPM_Sponsor_Offers::read( $hc_new )['state'], WPCPM_Sponsor_Codes::counts( $hc_new )['available'], WPCPM_Sponsor_Codes::held_count( $hc_new ) ), array( 'codes-added', 'review', 4, 0 ) );
$GLOBALS['uid'] = 1;
ck( 'a new pool\'s review shows its codes under the same Codes added', hc_codes_block( hc_item( $hc_new ) ), '<div class="wpcpm-offer__detail wpcpm-sponsor-offer__codes-added"><dt>Codes added</dt><dd><p>4 codes in all. Of these, 1 is a web address, listed first.</p><ul class="wpcpm-sponsor-offer__codes"><li><code>www.shop.example/new-d</code></li><li><code>NEW-A</code></li><li><code>NEW-B</code></li><li><code>NEW-C</code></li></ul></dd></div>' );
WPCPM_Sponsor_Offers::set_state( $hc_new, 'ended' );

// Twenty at most, web addresses first, every code escaped.
$GLOBALS['uid'] = 5;
$many = array();
for ( $i = 1; $i <= 21; $i++ ) {
	$many[] = sprintf( 'MANY-%02d', $i );
}
$many[] = 'HTTP://SHOP.EXAMPLE/UPPER';
$many[] = 'B<b>"&1';
$many[] = 'WWW.shop.example/w';
$many[] = 'https://shop.example/<script>';
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc, 'wpcpm_codes' => implode( "\n", $many ) ), $k_codes );
$GLOBALS['uid'] = 1;
$block = hc_codes_block( hc_item( $hc ) );
ck( 'of twenty-five codes twenty are shown, the three web addresses first, whatever their case, and a line counts the five more', array(
	false !== strpos( $block, '<p>25 codes in all. Of these, 3 are web addresses, listed first.</p>' ),
	substr_count( $block, '<li>' ),
	0 === strpos( substr( $block, (int) strpos( $block, '<ul' ) ), '<ul class="wpcpm-sponsor-offer__codes"><li><code>HTTP://SHOP.EXAMPLE/UPPER</code></li><li><code>WWW.shop.example/w</code></li><li><code>https://shop.example/&lt;script&gt;</code></li><li><code>MANY-01</code></li>' ),
	false !== strpos( $block, '</ul><p>5 more codes are not shown.</p></dd></div>' ),
	strpos( $block, '<a ' ),
	strpos( $block, '<script>' ),
), array( true, 20, true, true, false, false ) );
$GLOBALS['uid'] = 5;
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc ), $k_discard );

// Paused, dormant, and from before the review: all approved, so a member's codes wait.
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc, 'wpcpm_state' => 'paused' ), $k_state );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc, 'wpcpm_codes' => 'PAUSED-1' ), $k_codes );
ck( 'a paused offer\'s codes wait too, and one code is one on the card and in the press', array( $r[0], $r[3], WPCPM_Sponsor_Codes::held_count( $hc ), false !== strpos( hc_block( card( 'WPCPM_Sponsor_Offers', $A, $ctx_m ), $hc ), '<p class="wpcpm-student__note">1 code you added waits for an Administrator&#039;s review. Until it is approved, nobody can claim it.</p>' ) ), array( 'codes-waiting', '1 code waits for review.', 1, true ) );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc ), $k_discard );
$hc_legacy = WPCPM_Sponsor_Offers::create( $A, array_merge( $hc_fields, array( 'title' => 'Pool from before the review' ) ) );
WPCPM_Sponsor_Codes::add( $hc_legacy, 'LEG-1' );
update_post_meta( $hc_legacy, WPCPM_Sponsor_Offers::META_STATE, WPCPM_Sponsor_Offers::STATE_DORMANT );
delete_post_meta( $hc_legacy, WPCPM_Sponsor_Offers::META_APPROVED );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc_legacy, 'wpcpm_codes' => 'LEG-2' ), $k_codes );
ck( 'and so do a dormant offer\'s from before 1.123.18, which carries no mark and reads as approved', array( WPCPM_Sponsor_Offers::read( $hc_legacy )['approved'], $r[0], WPCPM_Sponsor_Codes::held_count( $hc_legacy ), in_array( array( $hc_legacy, 'changes' ), rv_waiting(), true ) ), array( true, 'codes-waiting', 1, true ) );
// A paused pool that ran out, with codes waiting, is told why it cannot be resumed yet.
$hc_dry = WPCPM_Sponsor_Offers::create( $A, array_merge( $hc_fields, array( 'title' => 'Dry pool' ) ), false, true );
WPCPM_Sponsor_Codes::add( $hc_dry, 'DRY-1' );
WPCPM_Sponsor_Offers::set_state( $hc_dry, 'live' );
WPCPM_Sponsor_Claims::claim( $hc_dry, $GLOBALS['users'][20] );
WPCPM_Sponsor_Offers::set_state( $hc_dry, 'paused' );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc_dry, 'wpcpm_codes' => 'DRY-2' ), $k_codes );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $hc_dry, 'wpcpm_state' => 'live' ), $k_state );
ck( 'a paused pool with nothing to give but codes waiting is not resumed, and is told they wait for review', array( $r[0], $r[3], WPCPM_Sponsor_Offers::read( $hc_dry )['state'] ), array( 'offer-empty', 'The codes you added wait for an Administrator\'s review: switch the offer on once they are approved.', 'paused' ) );

// The mark an offer carries is only where the queue finds it: one left with nothing behind it is
// cleared by the press that finds nothing, which says so.
$GLOBALS['uid'] = 1;
post( rv_fields( $hc_dry ), $k_approve );
update_post_meta( $hc_dry, WPCPM_Sponsor_Offers::META_CODES_HELD, 2 );
$r = post( rv_fields( $hc_dry ), $k_approve );
ck( 'an offer marked as holding codes the pool no longer holds is not approved, the press says nothing waits, and the mark goes', array( $r[0], WPCPM_Sponsor_Offers::read( $hc_dry )['codes_held'], WPCPM_Sponsor_Offers::is_waiting( WPCPM_Sponsor_Offers::read( $hc_dry ) ) ), array( 'offer-not-waiting', 0, false ) );
foreach ( array( $hc, $hc_new, $hc_legacy, $hc_dry ) as $hc_id ) {
	WPCPM_Sponsor_Codes::delete( $hc_id );
	wp_delete_post( $hc_id, true );
}
$codes_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-codes.php' ) . (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-offer-review.php' );
ck( 'no em or en dash in the codes and the review', preg_match( '/\x{2013}|\x{2014}/u', $codes_src ), 0 );
$GLOBALS['uid'] = 5;

echo "\n=== Codes added: the review's own reading (1.123.20, a second look) ===\n";
// A draft that already carries the program's OK, the seeded first offer of every sponsor or one an
// Administrator drafts for it, is switched on with no review: so a member's codes added to it take
// that OK away, as a member's change to its words does, and the draft is sent for review with its
// codes under Codes added.
$GLOBALS['uid'] = 1;
$K = 'recSPONSOR0000099';
WPCPM_Sponsors_Index::insert( array( 'record_id' => $K, 'name' => 'Seeded Example', 'status' => 'Approved' ) );
$GLOBALS['users'][9] = new WP_User( 9, array( 'wpcpm_sponsor' ), 'Rep Nine', 'rep.nine@example.test' );
$GLOBALS['umeta'][9] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $K, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$ctx_9  = array( 'can_manage' => false, 'open' => '', 'viewer' => $GLOBALS['users'][9] );
/** What one person's claim of an offer gives them: the code, or the refusal's code. */
function hc_claimed( $offer_id, $uid ) { $c = WPCPM_Sponsor_Claims::claim( $offer_id, $GLOBALS['users'][ $uid ] ); return is_wp_error( $c ) ? $c->get_error_code() : $c['code']; }
$seeded = WPCPM_Sponsor_Offers::seed( $K );
ck( 'the seeded first offer is a draft pool that carries the program\'s OK', array( WPCPM_Sponsor_Offers::read( $seeded )['state'], WPCPM_Sponsor_Offers::read( $seeded )['kind'], WPCPM_Sponsor_Offers::read( $seeded )['approved'] ), array( 'draft', 'codes', true ) );
$GLOBALS['uid'] = 9;
$r = post( array( 'wpcpm_sponsor' => $K, 'wpcpm_offer' => $seeded, 'wpcpm_codes' => 'https://evil.example/checkout' ), $k_codes );
$seed_form = substr( (string) card( 'WPCPM_Sponsor_Offers', $K, $ctx_9 ), (int) strpos( (string) card( 'WPCPM_Sponsor_Offers', $K, $ctx_9 ), 'id="wpcpm-offer-state-' . $seeded . '"' ), 900 );
ck( 'a member\'s codes added to it go in, read with the offer, and take its OK away: the card offers Send for review, not Switch on', array( $r[0], WPCPM_Sponsor_Codes::counts( $seeded )['available'], WPCPM_Sponsor_Offers::read( $seeded )['approved'], false !== strpos( $seed_form, 'value="live">Send for review</button>' ), strpos( $seed_form, 'Switch on' ) ), array( 'codes-added', 1, false, true, false ) );
$r = post( array( 'wpcpm_sponsor' => $K, 'wpcpm_offer' => $seeded, 'wpcpm_state' => 'live' ), $k_state );
ck( 'the press sends it for review, and nobody can claim the link meanwhile', array( $r[0], WPCPM_Sponsor_Offers::read( $seeded )['state'], hc_claimed( $seeded, 20 ), in_array( array( $seeded, 'new' ), rv_waiting(), true ) ), array( 'offer-sent', 'review', 'wpcpm_claim_refused', true ) );
$GLOBALS['uid'] = 1;
ck( 'the review lists the code under Codes added', hc_codes_block( hc_item( $seeded ) ), '<div class="wpcpm-offer__detail wpcpm-sponsor-offer__codes-added"><dt>Codes added</dt><dd><p>1 code in all. Of these, 1 is a web address, listed first.</p><ul class="wpcpm-sponsor-offer__codes"><li><code>https://evil.example/checkout</code></li></ul></dd></div>' );
$r = post( rv_fields( $seeded ), $k_approve );
ck( 'and only once it is approved can a student claim it', array( $r[0], WPCPM_Sponsor_Offers::read( $seeded )['state'], hc_claimed( $seeded, 20 ) ), array( 'offer-approved', 'live', 'https://evil.example/checkout' ) );
$seed_staff = WPCPM_Sponsor_Offers::create( $K, array( 'title' => 'Drafted by the program', 'kind' => 'codes', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ), false, true );
$r = post( array( 'wpcpm_sponsor' => $K, 'wpcpm_offer' => $seed_staff, 'wpcpm_codes' => 'STAFF-DRAFT-1' ), $k_codes );
ck( 'an Administrator\'s codes added to the program\'s own draft keep its OK', array( $r[0], WPCPM_Sponsor_Offers::read( $seed_staff )['approved'] ), array( 'codes-added', true ) );
$GLOBALS['uid'] = 9;
post( array( 'wpcpm_sponsor' => $K, 'wpcpm_offer' => $seed_staff, 'wpcpm_codes' => 'MEMBER-DRAFT-1' ), $k_codes );
ck( 'and a member\'s take it away there too', WPCPM_Sponsor_Offers::read( $seed_staff )['approved'], false );

// The queue's mark is the pool's own count after a release or a drop, so a member's codes held
// while an Administrator's press lets go of the lock are never left out of the queue.
$GLOBALS['uid'] = 1;
$race = WPCPM_Sponsor_Offers::create( $A, array_merge( $hc_fields, array( 'title' => 'Raced pool' ) ), false, true );
WPCPM_Sponsor_Codes::add( $race, 'RACE-0' );
WPCPM_Sponsor_Offers::set_state( $race, 'live' );
WPCPM_Sponsor_Offers::add_codes( $race, 'RACE-A', 5 );
$GLOBALS['before_delete'] = array( $race, WPCPM_Sponsor_Offers::META_CODES_HELD, static function () use ( $race ) { WPCPM_Sponsor_Offers::add_codes( $race, 'RACE-B', 5 ); } );
$approved_race = WPCPM_Sponsor_Offers::approve( $race );
$race_o        = WPCPM_Sponsor_Offers::read( $race );
ck( 'a member\'s codes held while an Approve lets go of the pool\'s lock stay marked and stamped: the queue still lists them', array( $approved_race, WPCPM_Sponsor_Codes::available_codes( $race ), WPCPM_Sponsor_Codes::held_codes( $race ), $race_o['codes_held'], isset( $race_o['submitted']['by'] ) ? $race_o['submitted']['by'] : 0, in_array( array( $race, 'changes' ), rv_waiting(), true ) ), array( true, array( 'RACE-0', 'RACE-A' ), array( 'RACE-B' ), 1, 5, true ) );
$GLOBALS['before_delete'] = array( $race, WPCPM_Sponsor_Offers::META_CODES_HELD, static function () use ( $race ) { WPCPM_Sponsor_Offers::add_codes( $race, 'RACE-C', 5 ); } );
$returned_race = WPCPM_Sponsor_Offers::return_with_note( $race, 'Not those.' );
$race_o        = WPCPM_Sponsor_Offers::read( $race );
ck( 'and so do codes held while a return lets go of it', array( is_array( $returned_race ) ? $returned_race['codes'] : $returned_race->get_error_code(), WPCPM_Sponsor_Codes::held_codes( $race ), $race_o['codes_held'], WPCPM_Sponsor_Offers::is_waiting( $race_o ) ), array( 1, array( 'RACE-C' ), 1, true ) );
unset( $GLOBALS['before_delete'] );

// A return the pool's lock refuses keeps the note as it was typed, as every other refusal does.
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $race ] = time();
$r = post( rv_fields( $race, array( 'wpcpm_note' => "Keep this\nnote." ) ), $k_return );
unset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $race ] );
$race_item = hc_item( $race );
ck( 'a return the pool\'s lock refuses is told to try again, nothing moves, and the fold opens on the note as it was typed', array( $r[0], WPCPM_Sponsor_Codes::held_count( $race ), false !== strpos( $race_item, '<details class="wpcpm-sponsor-offer__return" open>' ), false !== strpos( $race_item, ">\nKeep this\nnote.</textarea>" ) ), array( 'offer-review-failed', 1, true, true ) );

// An offer that ends takes the codes waiting on it along: nothing can claim them again, nobody can
// discard them from the card, and the queue would find the mark for ever.
$GLOBALS['uid'] = 5;
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $race ] = time();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $race, 'wpcpm_state' => 'ended' ), $k_state );
unset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $race ] );
ck( 'an End the pool\'s lock refuses is told to try again, and the offer is as it was', array( $r[0], WPCPM_Sponsor_Offers::read( $race )['state'], WPCPM_Sponsor_Codes::held_count( $race ) ), array( 'offer-busy', 'live', 1 ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $race, 'wpcpm_state' => 'ended' ), $k_state );
ck( 'ended, the offer drops its held codes and the mark, and the queue no longer finds it', array( $r[0], WPCPM_Sponsor_Offers::read( $race )['state'], WPCPM_Sponsor_Codes::held_count( $race ), get_post_meta( $race, WPCPM_Sponsor_Offers::META_CODES_HELD, true ), array_key_exists( $race, WPCPM_Sponsor_Offers::waiting() ), WPCPM_Sponsor_Codes::counts( $race )['available'] ), array( 'offer-state-saved', 'ended', 0, '', false, 2 ) );
// The End's row on the trail counts the codes it dropped (1.123.23), as a Discard's and a Return's
// rows count theirs: set_state() hands back how many, and an End that drops none says nothing of
// codes.
ck( 'the End is on the trail with how many held codes it dropped, never a code', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data'], strpos( serialize( end( $GLOBALS['audit'] ) ), 'RACE-C' ) ), array( 'offer_state', array( 'offer' => $race, 'from' => 'live', 'to' => 'ended', 'codes' => 1 ), false ) );
$end_plain = WPCPM_Sponsor_Offers::create( $A, array_merge( $hc_fields, array( 'title' => 'Ended with nothing held' ) ), false, true );
WPCPM_Sponsor_Codes::add( $end_plain, 'PLAIN-1' );
WPCPM_Sponsor_Offers::set_state( $end_plain, 'live' );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $end_plain, 'wpcpm_state' => 'ended' ), $k_state );
ck( 'an End that drops nothing writes no count of codes', end( $GLOBALS['audit'] )['data'], array( 'offer' => $end_plain, 'from' => 'live', 'to' => 'ended' ) );
$end_held = WPCPM_Sponsor_Offers::create( $A, array_merge( $hc_fields, array( 'title' => 'Ended by the program' ) ), false, true );
WPCPM_Sponsor_Codes::add( $end_held, 'HELD-END-0' );
WPCPM_Sponsor_Offers::set_state( $end_held, 'live' );
WPCPM_Sponsor_Offers::add_codes( $end_held, "HELD-END-1\nHELD-END-2", 5 );
$end_dropped = -1;
$end_none    = -1;
ck( 'set_state() hands back how many held codes an End dropped, and 0 for a move that drops none', array( WPCPM_Sponsor_Offers::set_state( $end_held, 'paused', $end_none ), $end_none, WPCPM_Sponsor_Codes::held_count( $end_held ), WPCPM_Sponsor_Offers::set_state( $end_held, 'ended', $end_dropped ), $end_dropped, WPCPM_Sponsor_Codes::held_count( $end_held ) ), array( true, 0, 2, true, 2, 0 ) );
foreach ( array( $end_plain, $end_held ) as $end_id ) {
	WPCPM_Sponsor_Codes::delete( $end_id );
	wp_delete_post( $end_id, true );
}
foreach ( array( $seeded, $seed_staff, $race ) as $hc_id ) {
	WPCPM_Sponsor_Codes::delete( $hc_id );
	wp_delete_post( $hc_id, true );
}
$GLOBALS['uid'] = 5;

echo "\n=== Approve and Return decide what the Administrator saw (1.123.23) ===\n";
// An item is drawn first and pressed later, and a sponsor's member may add codes or change the held
// words in between: Approve would then put in front of students what nobody read, and a Return
// drop codes nobody read and still stamp its note. So the decision forms carry a fingerprint of what
// the item showed, and both presses compute it again before they change anything. A page drawn
// before something changed, or one a release before 1.123.23 drew, which carries none, decides
// nothing and says so, and a typed note is kept for the item drawn again.
$GLOBALS['uid'] = 1; $GLOBALS['audit'] = array(); $GLOBALS['sent'] = array();
$fp = WPCPM_Sponsor_Offers::create( $A, array_merge( $hc_fields, array( 'title' => 'Seen pool' ) ), false, true );
WPCPM_Sponsor_Codes::add( $fp, "SEEN-1\nSEEN-2\nSEEN-0" );
WPCPM_Sponsor_Offers::set_state( $fp, 'live' );
$fp_0 = fp_now( $fp );
WPCPM_Sponsor_Claims::claim( $fp, $GLOBALS['users'][20] );
$fp_claimed = fp_now( $fp );
$GLOBALS['sent'] = array();
WPCPM_Sponsor_Offers::add_codes( $fp, 'SEEN-3', 5 );
$fp_held = fp_now( $fp );
WPCPM_Sponsor_Offers::propose( $fp, array( 'title' => 'Seen pool', 'text' => 'Two seats in the course.', 'instructions' => 'Use the code at checkout.', 'url' => '' ), 5 );
$fp_words = fp_now( $fp );
WPCPM_Sponsor_Offers::propose( $fp, array( 'title' => 'Seen pool', 'text' => 'Three seats in the course.', 'instructions' => 'Use the code at checkout.', 'url' => '' ), 5 );
$fp_words_2 = fp_now( $fp );
WPCPM_Sponsor_Offers::set_state( $fp, 'paused' );
$fp_paused = fp_now( $fp );
WPCPM_Sponsor_Offers::set_state( $fp, 'live' );
ck( 'the fingerprint follows what waits: a student\'s claim leaves it, and a held code, a held word change, another word change and the state each move it; put back, it reads as before', array(
	32 === strlen( $fp_0 ),
	$fp_claimed === $fp_0,
	$fp_held !== $fp_claimed,
	$fp_words !== $fp_held,
	$fp_words_2 !== $fp_words,
	$fp_paused !== $fp_words_2,
	fp_now( $fp ) === $fp_words_2,
), array( true, true, true, true, true, true, true ) );
$GLOBALS['uid'] = 5;
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => '0', 'wpcpm_title' => 'Seen new pool', 'wpcpm_text' => 'A plugin license.', 'wpcpm_instructions' => 'Paste it in.', 'wpcpm_url' => '', 'wpcpm_kind' => 'codes', 'wpcpm_codes' => 'SEEN-NEW-1', 'wpcpm_low' => '1', 'wpcpm_expires' => '' ), $k_save );
$fp_new = wpcpm_test_newest( $A );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $fp_new, 'wpcpm_state' => 'live' ), $k_state );
$fp_new_0 = fp_now( $fp_new );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $fp_new, 'wpcpm_codes' => 'SEEN-NEW-2' ), $k_codes );
$fp_new_1 = fp_now( $fp_new );
WPCPM_Sponsor_Offers::save( $fp_new, array( 'audience' => array( 'mentors' ) ) );
ck( 'for a new offer the codes it holds and the people it is open to are in it too', array( WPCPM_Sponsor_Offers::read( $fp_new )['state'], '' !== $fp_new_0 && $fp_new_1 !== $fp_new_0, fp_now( $fp_new ) !== $fp_new_1 ), array( 'review', true, true ) );
WPCPM_Sponsor_Offers::set_state( $fp_new, 'ended' );

$GLOBALS['uid'] = 1;
$fp_item = hc_item( $fp );
ck( 'the item\'s Approve and Return forms both carry the fingerprint of what it shows', array( substr_count( $fp_item, '<input type="hidden" name="wpcpm_seen" value="' . fp_now( $fp ) . '" />' ), false !== strpos( $fp_item, '<li><code>SEEN-3</code></li>' ) ), array( 2, true ) );
ck( 'and the refusal says so, in its own words', isset( WPCPM_Sponsor_Offer_Review::messages()['offer-changed'] ) ? WPCPM_Sponsor_Offer_Review::messages()['offer-changed'] : null, array( 'error', 'This offer changed after you opened it, so nothing was decided. Read it again, then decide.' ) );

// Drawn, then a member adds a code before the press.
$fp_seen   = fp_now( $fp );
$fp_before = array( WPCPM_Sponsor_Codes::counts( $fp ), WPCPM_Sponsor_Codes::held_codes( $fp ), WPCPM_Sponsor_Offers::read( $fp )['text'], WPCPM_Sponsor_Offers::read( $fp )['proposed'] );
WPCPM_Sponsor_Offers::add_codes( $fp, 'https://shop.example.test/unseen', 5 );
$fp_trail = count( $GLOBALS['audit'] );
/** What an offer holds and says, for "nothing changed": counts, held codes, words, held change, note, returned stamp. */
function fp_state( $offer_id ) { return in_another_request( static function () use ( $offer_id ) { $o = WPCPM_Sponsor_Offers::read( $offer_id ); return array( WPCPM_Sponsor_Codes::counts( $offer_id ), WPCPM_Sponsor_Codes::held_codes( $offer_id ), $o['text'], $o['proposed'], $o['note'], $o['returned'] ); } ); }
$fp_unseen = fp_state( $fp );
$r = post( array( 'wpcpm_offer' => $fp, 'wpcpm_seen' => $fp_seen ), $k_approve );
ck( 'an Approve drawn before a member added a code decides nothing: the change and both codes still wait, and nothing is on the trail', array( $r[0], fp_state( $fp ) === $fp_unseen, WPCPM_Sponsor_Offers::is_waiting( WPCPM_Sponsor_Offers::read( $fp ) ), count( $GLOBALS['audit'] ) - $fp_trail ), array( 'offer-changed', true, true, 0 ) );
$r = post( array( 'wpcpm_offer' => $fp, 'wpcpm_seen' => $fp_seen, 'wpcpm_note' => "Not the shop link.\nSingle codes only." ), $k_return );
$fp_redrawn = hc_item( $fp );
ck( 'a Return drawn before it decides nothing either: no code dropped, no note, no stamp, no email, nothing on the trail', array( $r[0], fp_state( $fp ) === $fp_unseen, $GLOBALS['sent'], count( $GLOBALS['audit'] ) - $fp_trail ), array( 'offer-changed', true, array(), 0 ) );
ck( 'and the item drawn again shows what waits now, with the new fingerprint, and its fold open on the note as it was typed', array(
	false !== strpos( $fp_redrawn, '<li><code>https://shop.example.test/unseen</code></li>' ),
	substr_count( $fp_redrawn, 'name="wpcpm_seen" value="' . fp_now( $fp ) . '"' ),
	fp_now( $fp ) !== $fp_seen,
	false !== strpos( $fp_redrawn, '<details class="wpcpm-sponsor-offer__return" open>' ),
	false !== strpos( $fp_redrawn, ">\nNot the shop link.\nSingle codes only.</textarea>" ),
), array( true, 2, true, true, true ) );

// A form a release before 1.123.23 drew, still open at the update, carries no fingerprint.
$r  = post( array( 'wpcpm_offer' => $fp ), $k_approve );
$r2 = post( array( 'wpcpm_offer' => $fp, 'wpcpm_seen' => '' ), $k_approve );
ck( 'an Approve with no fingerprint, or an empty one, is refused the same way, nothing moved', array( $r[0], $r2[0], fp_state( $fp ) === $fp_unseen, count( $GLOBALS['audit'] ) - $fp_trail ), array( 'offer-changed', 'offer-changed', true, 0 ) );
$r = post( array( 'wpcpm_offer' => $fp, 'wpcpm_note' => 'Typed on an old page.' ), $k_return );
ck( 'and so is a Return, keeping its note for the item drawn again', array( $r[0], fp_state( $fp ) === $fp_unseen, $GLOBALS['sent'], false !== strpos( hc_item( $fp ), ">\nTyped on an old page.</textarea>" ) ), array( 'offer-changed', true, array(), true ) );

// The pool's lock reads the codes again: an add landing between the press's check and its move,
// made by another request, so this one's copy of the pool, read for the check, is not told of it.
$fp_held_seen = method_exists( 'WPCPM_Sponsor_Codes', 'held_prints' ) ? WPCPM_Sponsor_Codes::held_prints( $fp ) : array();
in_another_request( static function () use ( $fp ) { WPCPM_Sponsor_Offers::add_codes( $fp, 'LATE-1', 5 ); } );
$fp_late     = fp_state( $fp );
$fp_approved = WPCPM_Sponsor_Offers::approve( $fp, $fp_held_seen );
$fp_returned = WPCPM_Sponsor_Offers::return_with_note( $fp, 'Not these.', $fp_held_seen );
ck( 'a code held after the press read the pool is seen under the pool\'s lock: neither the approval nor the return moves anything', array( is_wp_error( $fp_approved ) ? $fp_approved->get_error_code() : $fp_approved, is_wp_error( $fp_returned ) ? $fp_returned->get_error_code() : 'returned', fp_state( $fp ) === $fp_late, isset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $fp ] ) ), array( 'wpcpm_codes_changed', 'wpcpm_codes_changed', true, false ) );

// Drawn now and pressed: both go through, and decide what was drawn.
$r    = post( rv_fields( $fp ), $k_approve );
$fp_o = WPCPM_Sponsor_Offers::read( $fp );
ck( 'an Approve drawn from what waits goes through: the words and every code it showed', array( $r[0], $fp_o['text'], $fp_o['proposed'], WPCPM_Sponsor_Codes::held_count( $fp ), WPCPM_Sponsor_Codes::counts( $fp )['available'], end( $GLOBALS['audit'] )['data'] ), array( 'offer-approved', 'Three seats in the course.', array(), 0, 5, array( 'offer' => $fp, 'change' => 'changes', 'codes' => 3 ) ) );
$GLOBALS['uid'] = 5;
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $fp, 'wpcpm_codes' => "BACK-1\nBACK-2" ), $k_codes );
$GLOBALS['uid'] = 1; $GLOBALS['sent'] = array();
$r = post( rv_fields( $fp, array( 'wpcpm_note' => 'Single codes, please.' ) ), $k_return );
ck( 'and so does a Return: the codes it showed are taken out, the note stamped and mailed', array( $r[0], WPCPM_Sponsor_Codes::held_count( $fp ), WPCPM_Sponsor_Offers::read( $fp )['note'], WPCPM_Sponsor_Offers::read( $fp )['codes_returned'], count( $GLOBALS['sent'] ), end( $GLOBALS['audit'] )['data']['codes'] ), array( 'offer-returned', 0, 'Single codes, please.', 2, 1, 2 ) );

// The press reads the pool before it takes the lock, for its check, and core keeps that read in the
// request's own cache: under the lock the pool is read again from the table (1.123.23, fix round 1),
// so another request's write landing just before the lock is neither decided unseen nor undone by
// this request's write of its own copy.
/** Another request's step, run once just before this one takes the pool's lock. */
function before_lock( $offer_id, callable $step ) { $GLOBALS['before_add'] = array( WPCPM_Sponsor_Codes::LOCK_PREFIX . (int) $offer_id, static function () use ( $step ) { in_another_request( $step ); } ); }
/** The pool as the table holds it, past this request's copy. */
function pool_in_table( $offer_id ) { return in_another_request( static function () use ( $offer_id ) { return WPCPM_Sponsor_Codes::read( $offer_id ); } ); }
/** Who holds a claim on a pool, as its ledger in the table names them. */
function ledger_users( $offer_id ) { return array_values( array_map( static function ( $c ) { return (int) $c['u']; }, pool_in_table( $offer_id )['claims'] ) ); }
$GLOBALS['uid'] = 5;
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $fp, 'wpcpm_codes' => "WIN-1\nWIN-2" ), $k_codes );
$GLOBALS['uid'] = 1; $GLOBALS['sent'] = array();
before_lock( $fp, static function () use ( $fp ) { WPCPM_Sponsor_Offers::add_codes( $fp, 'WIN-LATE', 5 ); } );
$r = post( rv_fields( $fp, array( 'wpcpm_note' => 'Not those.' ) ), $k_return );
ck( 'a code a member holds just before the Return takes the pool\'s lock is not dropped unseen: nothing is returned, and every code still waits', array( $r[0], isset( $GLOBALS['before_add'] ), in_another_request( static function () use ( $fp ) { return WPCPM_Sponsor_Codes::held_codes( $fp ); } ), fp_state( $fp )[4], $GLOBALS['sent'] ), array( 'offer-changed', false, array( 'WIN-1', 'WIN-2', 'WIN-LATE' ), '', array() ) );
unset( $GLOBALS['before_add'] );
$GLOBALS['uid'] = 5;
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $fp, 'wpcpm_codes' => 'WIN-B' ), $k_codes );
$GLOBALS['uid'] = 1;
$fp_ledger = ledger_users( $fp );
before_lock( $fp, static function () use ( $fp ) { $GLOBALS['fp_window_claim'] = WPCPM_Sponsor_Claims::claim( $fp, $GLOBALS['users'][23] ); } );
$r       = post( rv_fields( $fp, array( 'wpcpm_note' => 'Single codes only.' ) ), $k_return );
unset( $GLOBALS['before_add'] );
$fp_pool = pool_in_table( $fp );
ck( 'a student\'s claim landing just before a Return takes the pool\'s lock stands: the Return drops what it showed and keeps the claim and the code it took', array( $r[0], isset( $GLOBALS['fp_window_claim']['new'] ) && $GLOBALS['fp_window_claim']['new'], ledger_users( $fp ), $fp_pool['codes'][ $GLOBALS['fp_window_claim']['index'] ]['st'], count( $fp_pool['held'] ) ), array( 'offer-returned', true, array_merge( $fp_ledger, array( 23 ) ), 'claimed', 0 ) );
$GLOBALS['uid'] = 5;
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $fp, 'wpcpm_codes' => 'WIN-3' ), $k_codes );
$GLOBALS['uid'] = 1;
$fp_ledger = ledger_users( $fp );
before_lock( $fp, static function () use ( $fp ) { $GLOBALS['fp_window_claim'] = WPCPM_Sponsor_Claims::claim( $fp, $GLOBALS['users'][24] ); } );
$r       = post( rv_fields( $fp ), $k_approve );
unset( $GLOBALS['before_add'] );
$fp_pool = pool_in_table( $fp );
ck( 'and so does one landing just before an Approve takes it: the code it took stays claimed, and the codes the Approve showed join the pool', array( $r[0], ledger_users( $fp ), $fp_pool['codes'][ $GLOBALS['fp_window_claim']['index'] ]['st'], count( $fp_pool['held'] ), WPCPM_Sponsor_Codes::code_at( $fp, count( $fp_pool['codes'] ) - 1 ) ), array( 'offer-approved', array_merge( $fp_ledger, array( 24 ) ), 'claimed', 0, 'WIN-3' ) );
// Two students claim at once: the second's own read of the pool, before its lock, for whether any
// code is left, must not be the copy it takes a code from.
$fp_two = WPCPM_Sponsor_Offers::create( $A, array_merge( $hc_fields, array( 'title' => 'Two at once' ) ), false, true );
WPCPM_Sponsor_Codes::add( $fp_two, "TWO-1\nTWO-2" );
WPCPM_Sponsor_Offers::set_state( $fp_two, 'live' );
$GLOBALS['opt_cache'] = array(); $GLOBALS['notoptions'] = array();
WPCPM_Sponsor_Codes::counts( $fp_two );
before_lock( $fp_two, static function () use ( $fp_two ) { $GLOBALS['fp_window_claim'] = WPCPM_Sponsor_Claims::claim( $fp_two, $GLOBALS['users'][24] ); } );
$fp_second = WPCPM_Sponsor_Claims::claim( $fp_two, $GLOBALS['users'][23] );
ck( 'two students claiming at once are handed two codes, never one code twice, and the ledger keeps both', array( $GLOBALS['fp_window_claim']['code'], is_wp_error( $fp_second ) ? $fp_second->get_error_code() : $fp_second['code'], ledger_users( $fp_two ) ), array( 'TWO-1', 'TWO-2', array( 24, 23 ) ) );
WPCPM_Sponsor_Codes::delete( $fp_two );
wp_delete_post( $fp_two, true );
unset( $GLOBALS['fp_window_claim'], $GLOBALS['before_add'] );
foreach ( array( $fp, $fp_new ) as $fp_id ) {
	WPCPM_Sponsor_Codes::delete( $fp_id );
	wp_delete_post( $fp_id, true );
}
$GLOBALS['uid'] = 5;

echo "\n=== What waits on a sponsor's people: the readers the At a glance strip counts through (Institutions release 3) ===\n";
// The Sponsor Dashboard's strip shows one tile for each kind of thing waiting on a company's people,
// and each tile counts through the reader its card draws from, so a tile and its card never
// disagree. The three offer kinds are exclusive, in this order: an offer an Administrator returned
// with a note, as the card shows it; one running low on codes, live today; one not switched on yet.
// A new offer an Administrator returned sits in the draft state and counts once, as returned; a live
// offer past its last day that the card lists under ended and expired draws no warning and counts
// nothing; a stamp a return left on an offer that was then switched on is no note the card shows.
$GLOBALS['uid'] = 1; $GLOBALS['audit'] = array(); $GLOBALS['sent'] = array();
$WK = 'recSPONSOR0000098';
WPCPM_Sponsors_Index::insert( array( 'record_id' => $WK, 'name' => 'Waiting Example', 'status' => 'Approved' ) );
$wk_fields = array( 'title' => 'Waiting pool', 'kind' => 'codes', 'text' => 'A seat in the course.', 'instructions' => 'Use the code at checkout.', 'url' => '', 'audience' => array(), 'low' => 3, 'expires' => '' );
/** A pool of the sponsor's (this section's, or the one named last) with its codes in, taken to a state: approved, as the program's own staff make one. */
function wk_pool( $title, $codes, $state = 'live', array $more = array(), $approved = true, $record = '' ) {
	global $WK, $wk_fields;
	$id = WPCPM_Sponsor_Offers::create( '' !== $record ? $record : $WK, array_merge( $wk_fields, array( 'title' => $title ), $more ), false, $approved );
	if ( $codes > 0 ) {
		WPCPM_Sponsor_Codes::add( $id, implode( "\n", array_map( static function ( $n ) use ( $title ) { return sanitize_title( $title ) . '-' . $n; }, range( 1, $codes ) ) ) );
	}
	if ( in_array( $state, array( 'live', 'paused', 'ended' ), true ) ) {
		WPCPM_Sponsor_Offers::set_state( $id, 'live' );
	}
	if ( in_array( $state, array( 'paused', 'ended' ), true ) ) {
		WPCPM_Sponsor_Offers::set_state( $id, $state );
	}
	return $id;
}
/** The words of a change a member holds beside a live offer. */
$wk_change = array( 'title' => 'Waiting pool', 'text' => 'Two seats in the course.', 'instructions' => 'Use the code at checkout.', 'url' => '' );
$wk = array();
// Returned: a new offer a member sent for review and an Administrator sent back (a draft with a note) ...
$wk['new'] = wk_pool( 'Returned new', 1, 'draft', array(), false );
WPCPM_Sponsor_Offers::submit( $wk['new'], 5 );
WPCPM_Sponsor_Offers::return_with_note( $wk['new'], 'Say what the code unlocks.' );
// ... a change to a live offer, sent back (a pool with plenty of codes, and one under its low mark) ...
$wk['change'] = wk_pool( 'Returned change', 5 );
WPCPM_Sponsor_Offers::propose( $wk['change'], $wk_change, 5 );
WPCPM_Sponsor_Offers::return_with_note( $wk['change'], 'Not those words.' );
$wk['change_low'] = wk_pool( 'Returned change, low', 1 );
WPCPM_Sponsor_Offers::propose( $wk['change_low'], $wk_change, 5 );
WPCPM_Sponsor_Offers::return_with_note( $wk['change_low'], 'Not those words either.' );
// ... and codes a member added to a live offer that the return took out, with no change held.
$wk['codes_out'] = wk_pool( 'Returned codes', 5 );
WPCPM_Sponsor_Offers::add_codes( $wk['codes_out'], "HELD-1\nHELD-2", 5 );
WPCPM_Sponsor_Offers::return_with_note( $wk['codes_out'], 'Single codes only.' );
// A returned new offer that the program's staff then switched on keeps the stamp of the return, and the card shows no note.
$wk['switched'] = wk_pool( 'Returned, then switched on', 5, 'draft', array(), false );
WPCPM_Sponsor_Offers::submit( $wk['switched'], 5 );
WPCPM_Sponsor_Offers::return_with_note( $wk['switched'], 'Add more codes.' );
WPCPM_Sponsor_Offers::set_state( $wk['switched'], 'live' );
// Running low: live today with fewer than its low mark (3) left; at the mark it is not low.
$wk['low']     = wk_pool( 'Low', 2 );
$wk['at_mark'] = wk_pool( 'At the mark', 3 );
// Live but past its last day (the site's today is 5 September 2026): listed under ended and expired.
$wk['expired'] = wk_pool( 'Expired and low', 1, 'live', array( 'expires' => '2026-09-01' ) );
// Not switched on yet, and the ones that count nothing: waiting for review, paused and ended below the mark, a shared offer, which has no pool.
$wk['draft']   = wk_pool( 'Not switched on', 0, 'draft' );
$wk['review']  = wk_pool( 'Waiting for review', 1, 'draft', array(), false );
WPCPM_Sponsor_Offers::submit( $wk['review'], 5 );
$wk['paused']  = wk_pool( 'Paused and low', 1, 'paused' );
$wk['ended']   = wk_pool( 'Ended and low', 1, 'ended' );
$wk['shared']  = WPCPM_Sponsor_Offers::create( $WK, array_merge( $wk_fields, array( 'title' => 'Shared link', 'kind' => 'shared', 'low' => 3 ) ), false, true );
WPCPM_Sponsor_Codes::set_shared( $wk['shared'], 'https://shop.example.test/checkout?c=SHARED' );
WPCPM_Sponsor_Offers::set_state( $wk['shared'], 'live' );
$wk_states = array_map( static function ( $id ) { return WPCPM_Sponsor_Offers::read( $id )['state']; }, $wk );
ck( 'the fixtures stand where the checks below need them: the returned ones in draft or live with a stamp, the stamp left on the switched-on one, the review, the paused and the ended', array( $wk_states, WPCPM_Sponsor_Offers::read( $wk['switched'] )['returned'] > 0, WPCPM_Sponsor_Offers::read( $wk['new'] )['returned'] > 0, WPCPM_Sponsor_Offers::read( $wk['codes_out'] )['codes_returned'] ), array( array( 'new' => 'draft', 'change' => 'live', 'change_low' => 'live', 'codes_out' => 'live', 'switched' => 'live', 'low' => 'live', 'at_mark' => 'live', 'expired' => 'live', 'draft' => 'draft', 'review' => 'review', 'paused' => 'paused', 'ended' => 'ended', 'shared' => 'live' ), true, true, 2 ) );

ck( 'waiting_counts() counts each offer once, under the first kind it matches: returned (a new offer sent back, a change sent back live or low, codes taken out), then low, then not switched on', WPCPM_Sponsor_Offers::waiting_counts( $WK ), array( 'returned' => 4, 'low' => 1, 'draft' => 1 ) );
ck( 'a new offer an Administrator returned sits in the draft state and counts as returned, never also as not switched on yet', array( WPCPM_Sponsor_Offers::read( $wk['new'] )['state'], WPCPM_Sponsor_Offers::is_returned( WPCPM_Sponsor_Offers::read( $wk['new'] ) ) ), array( 'draft', true ) );
ck( 'a live offer with a returned change counts as returned, under its low mark or not, and never also as low', array( WPCPM_Sponsor_Offers::is_returned( WPCPM_Sponsor_Offers::read( $wk['change'] ) ), WPCPM_Sponsor_Offers::is_returned( WPCPM_Sponsor_Offers::read( $wk['change_low'] ) ), WPCPM_Sponsor_Offers::is_low( WPCPM_Sponsor_Offers::read( $wk['change_low'] ), WPCPM_Sponsor_Codes::counts( $wk['change_low'] ) ) ), array( true, true, true ) );
ck( 'codes a return took out of a live offer count as returned, as the card says so beside the note', array( WPCPM_Sponsor_Offers::is_returned( WPCPM_Sponsor_Offers::read( $wk['codes_out'] ) ), WPCPM_Sponsor_Offers::read( $wk['codes_out'] )['proposed'] ), array( true, array() ) );
ck( 'a stamp a return left on an offer that was then switched on is no returned offer: the card shows no note, so nothing counts it', array( WPCPM_Sponsor_Offers::is_returned( WPCPM_Sponsor_Offers::read( $wk['switched'] ) ), WPCPM_Sponsor_Offers::is_returned( WPCPM_Sponsor_Offers::read( $wk['at_mark'] ) ) ), array( false, false ) );

$wk_low = static function ( $label, $today = '' ) use ( $wk ) { return WPCPM_Sponsor_Offers::is_low( WPCPM_Sponsor_Offers::read( $wk[ $label ] ), WPCPM_Sponsor_Codes::counts( $wk[ $label ] ), $today ); };
ck( 'is_low(): live today and fewer codes available than its mark; at the mark it is not; a paused, an ended, a draft and a waiting offer are not, however few codes they hold', array( $wk_low( 'low' ), $wk_low( 'at_mark' ), $wk_low( 'paused' ), $wk_low( 'ended' ), $wk_low( 'draft' ), $wk_low( 'review' ) ), array( true, false, false, false, false, false ) );
ck( 'and a live offer past its last day is not low, the last day itself inclusive: on 31 August and on its last day, 1 September, it is; on the 2nd it is not', array( $wk_low( 'expired', '2026-08-31' ), $wk_low( 'expired', '2026-09-01' ), $wk_low( 'expired', '2026-09-02' ), $wk_low( 'expired' ) ), array( true, true, false, false ) );
ck( 'is_low() speaks of a pool: a live shared offer with a low mark answers false, though no codes are available in it', array( $wk_states['shared'], WPCPM_Sponsor_Codes::counts( $wk['shared'] )['available'], WPCPM_Sponsor_Offers::read( $wk['shared'] )['low'], $wk_low( 'shared' ) ), array( 'live', 0, 3, false ) );
ck( 'waiting_counts() takes the day too: an offer whose last day has not passed is live, and low', array( WPCPM_Sponsor_Offers::waiting_counts( $WK, '2026-08-31' ), WPCPM_Sponsor_Offers::waiting_counts( $WK, '2026-09-02' ) ), array( array( 'returned' => 4, 'low' => 2, 'draft' => 1 ), array( 'returned' => 4, 'low' => 1, 'draft' => 1 ) ) );
ck( 'a shared offer has no pool to run low: it counts nothing, though no codes are available in it', array( $wk_states['shared'], WPCPM_Sponsor_Codes::counts( $wk['shared'] )['available'], WPCPM_Sponsor_Offers::waiting_counts( $WK )['low'] ), array( 'live', 0, 1 ) );
ck( 'a sponsor with no offer, and a key that is none, wait for nothing', array( WPCPM_Sponsor_Offers::waiting_counts( 'recSPONSOR0000097' ), WPCPM_Sponsor_Offers::waiting_counts( 'not a key' ) ), array( array( 'returned' => 0, 'low' => 0, 'draft' => 0 ), array( 'returned' => 0, 'low' => 0, 'draft' => 0 ) ) );

// The tiles count the offers the card lists under its active offers: a paused one, whose returned
// note the company can still answer, waits; an offer that ended, or is past its last day, is listed
// under ended and expired and waits for nothing, however its note stands, since nothing the company
// presses puts the note of a codes-only return away once the offer is over and the tile would never
// clear. One sponsor for each, so each count answers for its offer alone (the site's today is
// 5 September 2026).
$wp_none = array( 'returned' => 0, 'low' => 0, 'draft' => 0 );
$wp_key  = array( 'paused' => 'recSPONSOR0000096', 'ended' => 'recSPONSOR0000095', 'expired' => 'recSPONSOR0000094', 'old_draft' => 'recSPONSOR0000093' );
foreach ( $wp_key as $label => $record ) {
	WPCPM_Sponsors_Index::insert( array( 'record_id' => $record, 'name' => 'Over Example ' . $label, 'status' => 'Approved' ) );
}
$wp = array();
// A live pool with a held change an Administrator returned, then paused ...
$wp['paused'] = wk_pool( 'Paused with a returned change', 5, 'live', array(), true, $wp_key['paused'] );
WPCPM_Sponsor_Offers::propose( $wp['paused'], $wk_change, 5 );
WPCPM_Sponsor_Offers::return_with_note( $wp['paused'], 'Not those words.' );
WPCPM_Sponsor_Offers::set_state( $wp['paused'], 'paused' );
// ... the same, ended ...
$wp['ended'] = wk_pool( 'Ended with a returned change', 5, 'live', array(), true, $wp_key['ended'] );
WPCPM_Sponsor_Offers::propose( $wp['ended'], $wk_change, 5 );
WPCPM_Sponsor_Offers::return_with_note( $wp['ended'], 'Not those words.' );
WPCPM_Sponsor_Offers::set_state( $wp['ended'], 'ended' );
// ... a live pool past its last day whose added codes a return took out, with no change held ...
$wp['expired'] = wk_pool( 'Expired with returned codes', 5, 'live', array( 'expires' => '2026-09-01' ), true, $wp_key['expired'] );
WPCPM_Sponsor_Offers::add_codes( $wp['expired'], "OVER-HELD-1\nOVER-HELD-2", 5 );
WPCPM_Sponsor_Offers::return_with_note( $wp['expired'], 'Single codes only.' );
// ... and a draft past its last day, which was never switched on.
$wp['old_draft'] = wk_pool( 'Draft past its last day', 0, 'draft', array( 'expires' => '2026-09-01' ), true, $wp_key['old_draft'] );
$wp_offer  = static function ( $label ) use ( $wp ) { return WPCPM_Sponsor_Offers::read( $wp[ $label ] ); };
$wp_counts = static function ( $label, $today = '' ) use ( $wp_key ) { return WPCPM_Sponsor_Offers::waiting_counts( $wp_key[ $label ], $today ); };
ck( 'the fixtures stand where the checks below need them: paused, ended, live past its last day and draft past its last day, three of them with a return the readers still see', array( $wp_offer( 'paused' )['state'], $wp_offer( 'ended' )['state'], $wp_offer( 'expired' )['state'], $wp_offer( 'old_draft' )['state'], WPCPM_Sponsor_Offers::is_past( $wp_offer( 'paused' ) ), WPCPM_Sponsor_Offers::is_past( $wp_offer( 'ended' ) ), WPCPM_Sponsor_Offers::is_past( $wp_offer( 'expired' ) ), WPCPM_Sponsor_Offers::is_past( $wp_offer( 'old_draft' ) ), WPCPM_Sponsor_Offers::is_returned( $wp_offer( 'paused' ) ), WPCPM_Sponsor_Offers::is_returned( $wp_offer( 'ended' ) ), WPCPM_Sponsor_Offers::is_returned( $wp_offer( 'expired' ) ), $wp_offer( 'expired' )['codes_returned'] ), array( 'paused', 'ended', 'live', 'draft', false, true, true, true, true, true, true, 2 ) );
ck( 'a paused offer with a returned change counts as returned: the card lists it with its active offers and the company can still answer the note', $wp_counts( 'paused' ), array( 'returned' => 1, 'low' => 0, 'draft' => 0 ) );
ck( 'an ended offer with a returned change counts nothing: the card lists it under ended and expired', $wp_counts( 'ended' ), $wp_none );
ck( 'an offer past its last day with a codes-only return counts nothing, and counts as returned through its last day: on 1 September it does, on the 2nd it does not', array( $wp_counts( 'expired' ), $wp_counts( 'expired', '2026-09-01' ), $wp_counts( 'expired', '2026-09-02' ) ), array( $wp_none, array( 'returned' => 1, 'low' => 0, 'draft' => 0 ), $wp_none ) );
ck( 'a draft past its last day is no draft not switched on yet, as the card lists it under ended and expired; through its last day it is', array( $wp_counts( 'old_draft' ), $wp_counts( 'old_draft', '2026-09-01' ), $wp_counts( 'old_draft', '2026-09-02' ) ), array( $wp_none, array( 'returned' => 0, 'low' => 0, 'draft' => 1 ), $wp_none ) );

// The card and the readers agree, offer by offer: the Running low line is drawn exactly when is_low()
// says so, and a returned note exactly when is_returned() does (the changes block and the state block).
$wk_html   = card( 'WPCPM_Sponsor_Offers', $WK, array( 'can_manage' => true, 'open' => '', 'viewer' => $GLOBALS['users'][1] ) );
$wk_drawn  = array( 'warning' => array(), 'returned' => array(), 'low' => array(), 'is_returned' => array() );
foreach ( $wk as $label => $id ) {
	$block = hc_block( $wk_html, $id );
	$offer = WPCPM_Sponsor_Offers::read( $id );
	if ( false !== strpos( $block, 'wpcpm-offer__warning' ) ) { $wk_drawn['warning'][] = $label; }
	if ( 1 === preg_match( '/Returned by the program with a note|An Administrator returned (?:your changes|the codes you added) with a note/', $block ) ) { $wk_drawn['returned'][] = $label; }
	if ( WPCPM_Sponsor_Offers::KIND_CODES === $offer['kind'] && WPCPM_Sponsor_Offers::is_low( $offer, WPCPM_Sponsor_Codes::counts( $id ) ) ) { $wk_drawn['low'][] = $label; }
	if ( WPCPM_Sponsor_Offers::is_returned( $offer, WPCPM_Sponsor_Offers::KIND_CODES === $offer['kind'] ? WPCPM_Sponsor_Codes::held_count( $id ) : 0 ) ) { $wk_drawn['is_returned'][] = $label; }
}
ck( 'the card draws Running low on exactly the offers is_low() names: the one live and under its mark, the live one past its last day not among them', array( $wk_drawn['warning'], $wk_drawn['low'] ), array( array( 'change_low', 'low' ), array( 'change_low', 'low' ) ) );
ck( 'and a returned note on exactly the offers is_returned() names: the new offer sent back, the two changes, the codes taken out, and not the one switched on after its return', array( $wk_drawn['returned'], $wk_drawn['is_returned'] ), array( array( 'new', 'change', 'change_low', 'codes_out' ), array( 'new', 'change', 'change_low', 'codes_out' ) ) );
ck( 'the line itself is the card\'s own words, with the offer\'s mark in them', false !== strpos( hc_block( $wk_html, $wk['low'] ), '<span class="wpcpm-offer__warning">Running low: fewer than 3 codes left. Add more, or pause the offer.</span>' ), true );
$GLOBALS['uid'] = 5;

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
