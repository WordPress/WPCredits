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
 *   pool holds anything.
 * - The primary offer writes exactly three Airtable fields and never the coupon link.
 * - (Task 4) Two claims under the lock take two codes; the same person twice gets the same
 *   code; a manager's void frees the person; low stock mails once and re-arms on adding; the
 *   stats carry no name and no address.
 * - (Task 6) The cards and their handlers claim through WPCPM_Sponsor_Roster::claim() and check
 *   the offer's sponsor before anything else.
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
	public $ID = 0, $post_type = '', $post_title = '', $post_status = 'private', $post_author = 0;
	public function __construct( array $a ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}
class WPCPM_Test_Die extends Exception {}
class WPCPM_Test_Redirect extends Exception {}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
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
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
// The autoload argument is recorded, not ignored: only the add_option() branch of
// WPCPM_Sponsor_Codes::write() used to prove the pool is not autoloaded, so a later write that
// dropped the argument would have gone unseen (queued item C). No third argument means "leave
// it as it is", which is what core does.
function update_option( $k, $v, $a = null ) {
	$changed               = ! array_key_exists( $k, $GLOBALS['opts'] ) || $GLOBALS['opts'][ $k ] !== $v;
	$GLOBALS['opts'][ $k ] = $v;
	if ( 3 <= func_num_args() ) { $GLOBALS['autoload'][ $k ] = ( 'yes' === $a || true === $a ); }
	return $changed;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ], $GLOBALS['autoload'][ $k ] ); return true; }
function add_option( $k, $v, $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = ( 'yes' === $autoload || true === $autoload ); return true;
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
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { return 'https://example.test/logo-' . (int) $id . '.png'; }

// The post store: enough of posts, meta and get_posts() for the offers to live in.
// A title is unslashed, as core's insert unslashes it, and for a person without `unfiltered_html`
// it first meets kses (`kses_init()` adds `wp_filter_kses()` on `title_save_pre` for them only).
function wpcpm_test_title( $slashed ) { $title = stripslashes( (string) $slashed ); return current_user_can( 'unfiltered_html' ) ? $title : wpcpm_test_kses_title( $title ); }
function wp_insert_post( array $args, $wp_error = false ) {
	$id = $GLOBALS['next_post']++;
	$GLOBALS['posts'][ $id ] = new WP_Post( array( 'ID' => $id, 'post_type' => $args['post_type'] ?? 'post', 'post_title' => wpcpm_test_title( $args['post_title'] ?? '' ), 'post_status' => $args['post_status'] ?? 'publish', 'post_author' => $args['post_author'] ?? 0 ) );
	return $id;
}
function wp_update_post( array $args ) {
	$id = (int) $args['ID'];
	if ( isset( $GLOBALS['posts'][ $id ] ) && isset( $args['post_title'] ) ) { $GLOBALS['posts'][ $id ]->post_title = wpcpm_test_title( $args['post_title'] ); $GLOBALS['title_writes'] = ( $GLOBALS['title_writes'] ?? 0 ) + 1; }
	return $id;
}
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ''; }
// As core's: what is written is unslashed first, at any depth, so words reach it as slashed copies.
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = stripslashes_deep( $v ); return true; }
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); return true; }
function get_posts( array $args ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) { continue; }
		if ( isset( $args['post_status'] ) && $post->post_status !== $args['post_status'] ) { continue; }
		$ok = true;
		foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			$have = array_key_exists( $clause['key'], $GLOBALS['pmeta'][ $id ] ?? array() );
			if ( isset( $clause['compare'] ) && 'EXISTS' === $clause['compare'] ) { if ( ! $have ) { $ok = false; } continue; }
			if ( ! $have || (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ] !== (string) $clause['value'] ) { $ok = false; }
		}
		if ( $ok ) { $out[] = $post; }
	}
	usort( $out, static function ( $a, $b ) { return $a->ID - $b->ID; } );
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
function wp_cache_delete( $key, $group = '' ) { return true; }

class WPCPM_Airtable {
	public function update_records( $table, array $records ) { $GLOBALS['patched'][] = array( $table, $records ); return isset( $GLOBALS['airtable_fail'] ) ? new WP_Error( 'x', 'Airtable said no' ) : array( $records[0]['id'] => true ); }
}
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
/** The real writer's contract: a BOM, one line per row, cells joined by commas, a leading formula character neutralised. */
class WPCPM_Institution_Export {
	public static function cell( $value ) { $text = is_scalar( $value ) ? (string) $value : ''; $lead = ltrim( $text ); return ( '' !== $lead && in_array( substr( $lead, 0, 1 ), array( '=', '+', '-', '@' ), true ) ) ? "'" . $text : $text; }
	public static function csv( array $matrix ) { $out = "\xEF\xBB\xBF"; foreach ( $matrix as $row ) { $out .= implode( ',', array_map( array( __CLASS__, 'cell' ), $row ) ) . "\r\n"; } return $out; }
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/../includes/class-wpcpm-secret.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-codes.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-offers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-claims.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tools.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-usage.php';

// The fixture: two Approved sponsors, a manager, a member of each, and the people who claim.
$A = 'recSPONSOR0000001'; $B = 'recSPONSOR0000002'; $T = 'recTEAM0000000001';
WPCPM_Sponsors_Index::write( array(
	$A => array( 'name' => 'Mango Example', 'status' => 'Approved', 'website' => 'https://plugins.mango-example.com/', 'contact_person' => 'Rep One', 'contact_email' => 'maciej@a8c.com', 'product_type' => 'Plugin', 'offer' => 'One year of the premium plugin', 'instructions' => 'Enter the code at checkout.', 'more_info' => 'https://plugins.mango-example.com/wpcredits', 'coupon_link' => 'https://docs.google.com/spreadsheets/d/abc/edit', 'manager' => $T, 'mentors' => array() ),
	$B => array( 'name' => 'Cirrus Example', 'status' => 'Approved', 'website' => 'https://cirrus-example.example/', 'contact_person' => 'Rep Two', 'contact_email' => 'maciej@a8c.com', 'product_type' => 'Hosting', 'offer' => 'A year of hosting', 'instructions' => 'Use the link.', 'more_info' => '', 'coupon_link' => 'https://cirrus-example.example/checkout?code=WPCREDITS', 'manager' => '', 'mentors' => array() ),
), time() );
WPCPM_Sponsors_Index::write_team( array( $T => array( 'name' => 'Maciej (Matt) Pilarski', 'email' => 'maciej@a8c.com', 'calendly' => '' ) ), time() );
$GLOBALS['settings'] = array( 'sponsors_table' => 'tblSPONSORS', 'offer_low_stock' => 10, 'student_statuses' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' ), 'past_statuses' => array( 'Graduate', 'Dropped out' ), 'tools_students' => true, 'tools_mentors' => false );
$GLOBALS['users'] = array(
	1  => new WP_User( 1, array( 'administrator' ), 'Manager', 'maciej@a8c.com' ),
	5  => new WP_User( 5, array( 'wpcpm_sponsor' ), 'Rep One', 'maciej@a8c.com' ),
	6  => new WP_User( 6, array( 'wpcpm_sponsor' ), 'Rep Two', 'maciej@a8c.com' ),
	20 => new WP_User( 20, array( 'wpcpm_student' ), 'Student Current', 'maciej@a8c.com' ),
	21 => new WP_User( 21, array( 'wpcpm_student' ), 'Student Graduate', 'maciej@a8c.com' ),
	22 => new WP_User( 22, array( 'wpcpm_student' ), 'Student Paused', 'maciej@a8c.com' ),
	23 => new WP_User( 23, array( 'wpcpm_student' ), 'Student Second', 'maciej@a8c.com' ),
	24 => new WP_User( 24, array( 'wpcpm_student' ), 'Student Third', 'maciej@a8c.com' ),
	30 => new WP_User( 30, array( 'wpcpm_mentor' ), 'Mentor One', 'maciej@a8c.com' ),
	31 => new WP_User( 31, array( 'wpcpm_student' ), 'Student Unsynced', 'maciej@a8c.com' ),
);
$GLOBALS['manage'] = array( 1 );
// The administrator holds `unfiltered_html`; a sponsor member does not, so kses meets what the member saves.
$GLOBALS['grants'] = array( 1 => array( 'unfiltered_html' ) );
$GLOBALS['umeta'][5] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $A, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['umeta'][6] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $B, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
// The shape the students sync writes: the status under `program`, with `is_past` beside it.
$GLOBALS['program'] = array( 20 => array( 'program' => 'In Sensei', 'is_past' => false ), 21 => array( 'program' => 'Graduate', 'is_past' => true ), 22 => array( 'program' => 'Paused', 'is_past' => false ), 23 => array( 'program' => 'Developer Track', 'is_past' => false ), 24 => array( 'program' => 'In Sensei', 'is_past' => false ) );
$GLOBALS['uid'] = 5; $GLOBALS['nonce_ok'] = true; $GLOBALS['patched'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['buckets'] = array(); $GLOBALS['now'] = gmmktime( 12, 0, 0, 9, 5, 2026 );

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
$offer = WPCPM_Sponsor_Offers::read( $a1 );
ck( 'titled with the sponsor name, the text from Offer, the instructions and the link from the base', array( $offer['title'], $offer['text'], $offer['instructions'], $offer['url'] ), array( 'Mango Example', 'One year of the premium plugin', 'Enter the code at checkout.', 'https://plugins.mango-example.com/wpcredits' ) );
ck( 'a draft, primary, kind codes, students only, the default threshold', array( $offer['state'], $offer['primary'], $offer['kind'], $offer['audience'], $offer['low'] ), array( 'draft', true, 'codes', array(), 10 ) );
// The synced sponsors index already holds the raw coupon_link (it is a cache of Airtable's own
// field, written by WPCPM_Sponsors_Sync, not by seed()), so it is excluded here on purpose: this
// check is for what seed() itself might copy into new storage, not for the index's own read of
// the base.
$opts_without_index = $GLOBALS['opts'];
unset( $opts_without_index[ WPCPM_Sponsors_Index::OPT_NAME ] );
ck( 'the sheet address is not stored anywhere', strpos( serialize( $opts_without_index ) . serialize( $GLOBALS['pmeta'] ), 'docs.google.com' ), false );
ck( 'seeding again does nothing', WPCPM_Sponsor_Offers::seed( $A ), false );
$b1 = WPCPM_Sponsor_Offers::seed( $B );
ck( 'a checkout link that is not the sheet makes a shared offer', WPCPM_Sponsor_Offers::read( $b1 )['kind'], 'shared' );
ck( 'holding that link as the shared code', WPCPM_Sponsor_Codes::shared( $b1 ), 'https://cirrus-example.example/checkout?code=WPCREDITS' );
ck( 'and not in the clear', strpos( serialize( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::option_name( $b1 ) ] ), 'WPCREDITS' ), false );
$D = 'recSPONSOR0000004';
WPCPM_Sponsors_Index::write( array_merge( WPCPM_Sponsors_Index::rows(), array( $D => array( 'name' => 'Drive Sheet', 'status' => 'Approved', 'website' => '', 'contact_person' => 'Rep Four', 'contact_email' => 'maciej@a8c.com', 'product_type' => 'Plugin', 'offer' => 'A sheet of codes', 'instructions' => '', 'more_info' => '', 'coupon_link' => 'https://drive.google.com/open?id=x', 'manager' => '', 'mentors' => array() ) ) ), time() );
$d_seed = WPCPM_Sponsor_Offers::seed( $D );
ck( 'a coupon sheet shared as a Drive link is the sheet too, so it seeds a pool and no shared code', array( WPCPM_Sponsor_Offers::read( $d_seed )['kind'], WPCPM_Sponsor_Codes::shared( $d_seed ) ), array( 'codes', '' ) );
ck( 'an unknown sponsor cannot be seeded', WPCPM_Sponsor_Offers::seed( 'recNOTINDEXED0001' )->get_error_code(), 'wpcpm_offer_sponsor' );
ck( 'offers_of() lists by sponsor', array( array_keys( WPCPM_Sponsor_Offers::offers_of( $A ) ), array_keys( WPCPM_Sponsor_Offers::offers_of( $B ) ) ), array( array( $a1 ), array( $b1 ) ) );
ck( 'find() with the wrong sponsor is null', array( WPCPM_Sponsor_Offers::find( $a1, $B ), WPCPM_Sponsor_Offers::find( $a1, $A )['id'] ), array( null, $a1 ) );

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
ck( 'an empty paste is its own answer', WPCPM_Sponsor_Codes::add( $a1, "\n\n" )->get_error_code(), 'wpcpm_codes_none' );
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
// Task 3 fix round 1: the closing sentence used sprintf() for a count, so exactly eleven faulty
// lines (ten named, one more) printed the ungrammatical "and 1 more lines have problems"; _n()
// must choose the singular there, and the plural must still read right at every other count.
$eleven = WPCPM_Sponsor_Codes::add( $a1, implode( "\n", array_fill( 0, 12, 'ELEVEN-1' ) ) )->get_error_data();
ck( 'exactly eleven faulty lines close with the singular sentence', end( $eleven ), 'and 1 more line has a problem.' );
$twelve = WPCPM_Sponsor_Codes::add( $a1, implode( "\n", array_fill( 0, 13, 'TWELVE-1' ) ) )->get_error_data();
ck( 'and twelve faulty lines close with the plural sentence', end( $twelve ), 'and 2 more lines have problems.' );
$big = WPCPM_Sponsor_Codes::read( $a1 );
$big['codes'] = array_fill( 0, 4999, array( 's' => 'x', 'h' => 'h', 'st' => 'available', 'by' => 0, 'at' => 0 ) );
WPCPM_Sponsor_Codes::write( 777, $big );
ck( 'the five-thousandth code is the last one an offer takes', array( WPCPM_Sponsor_Codes::add( 777, "ONE\nTWO" )->get_error_code(), WPCPM_Sponsor_Codes::add( 777, 'ONE' ) ), array( 'wpcpm_codes_max', 1 ) );
WPCPM_Sponsor_Codes::delete( 777 );
ck( 'take() hands out the first available code and records who', array( WPCPM_Sponsor_Codes::take( $a1, 20 ), WPCPM_Sponsor_Codes::read( $a1 )['codes'][0]['st'], WPCPM_Sponsor_Codes::read( $a1 )['codes'][0]['by'] ), array( 0, 'claimed', 20 ) );
ck( 'the next take is the next code', WPCPM_Sponsor_Codes::take( $a1, 23 ), 1 );
ck( 'code_at() unseals', WPCPM_Sponsor_Codes::code_at( $a1, 1 ), 'WPCE-0002' );
ck( 'and the ledger has both', array_map( static function ( $c ) { return array( $c['u'], $c['i'] ); }, WPCPM_Sponsor_Codes::claims( $a1 ) ), array( array( 20, 0 ), array( 23, 1 ) ) );
ck( 'a manager voiding a claimed code flags its ledger row', array( WPCPM_Sponsor_Codes::void_index( $a1, 1 ), WPCPM_Sponsor_Codes::read( $a1 )['codes'][1]['st'], WPCPM_Sponsor_Codes::claims( $a1 )[1]['v'] > 0 ), array( true, 'void', true ) );
ck( 'and cannot void what is not claimed', WPCPM_Sponsor_Codes::void_index( $a1, 2 ), false );
ck( 'the sponsor voids what nobody holds', array( WPCPM_Sponsor_Codes::void_unclaimed( $a1 ), WPCPM_Sponsor_Codes::counts( $a1 ) ), array( 18, array( 'available' => 0, 'claimed' => 1, 'void' => 19, 'total' => 20 ) ) );
ck( 'take() on an empty pool says so', WPCPM_Sponsor_Codes::take( $a1, 22 )->get_error_code(), 'wpcpm_codes_empty' );
WPCPM_Sponsor_Codes::add( $a1, "MORE-1\nMORE-2\nMORE-3" );

echo "\n=== The state machine ===\n";
$empty = WPCPM_Sponsor_Offers::create( $A, array( 'title' => 'Empty pool', 'kind' => 'codes', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 10, 'expires' => '' ) );
ck( 'a pool cannot go live empty', WPCPM_Sponsor_Offers::set_state( $empty, 'live' )->get_error_code(), 'wpcpm_offer_empty' );
ck( 'nor can a shared offer with nothing to share', array( WPCPM_Sponsor_Codes::set_shared( $b1, '' ), WPCPM_Sponsor_Offers::set_state( $b1, 'live' )->get_error_code() ), array( true, 'wpcpm_offer_empty' ) );
WPCPM_Sponsor_Codes::set_shared( $b1, 'https://cirrus-example.example/checkout?code=WPCREDITS' );
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
ck( 'and all() lists every offer', array_keys( WPCPM_Sponsor_Offers::all() ), array( $a1, $b1, $d_seed, $empty ) );

echo "\n=== The mirror ===\n";
WPCPM_Sponsor_Offers::save( $a1, array( 'text' => 'Two years of the premium plugin', 'url' => 'https://plugins.mango-example.com/wpcredits2' ) );
$GLOBALS['patched'] = array();
ck( 'the primary offer writes exactly three fields, spelled as the base spells them', array( WPCPM_Sponsor_Offers::mirror( WPCPM_Sponsor_Offers::read( $a1 ) ), $GLOBALS['patched'][0][1][0]['fields'] ), array( true, array( 'Offer' => 'Two years of the premium plugin', 'Brief instructions' => 'Enter the code at checkout.', 'More info link' => 'https://plugins.mango-example.com/wpcredits2' ) ) );
ck( 'to the sponsor\'s record in the sponsors table', array( $GLOBALS['patched'][0][0], $GLOBALS['patched'][0][1][0]['id'] ), array( 'tblSPONSORS', $A ) );
ck( 'and the index at once', array( WPCPM_Sponsors_Index::row( $A )['offer'], WPCPM_Sponsors_Index::row( $A )['more_info'] ), array( 'Two years of the premium plugin', 'https://plugins.mango-example.com/wpcredits2' ) );
ck( 'the coupon link is never written', strpos( serialize( $GLOBALS['patched'] ), 'Coupon' ), false );
ck( 'a second offer is not mirrored and says nothing', array( WPCPM_Sponsor_Offers::mirror( WPCPM_Sponsor_Offers::read( $empty ) ), count( $GLOBALS['patched'] ) ), array( true, 1 ) );
$GLOBALS['airtable_fail'] = true;
ck( 'a failed PATCH is reported, and the index is left as it was', array( WPCPM_Sponsor_Offers::mirror( WPCPM_Sponsor_Offers::read( $a1 ) ), WPCPM_Sponsors_Index::row( $A )['offer'] ), array( false, 'Two years of the premium plugin' ) );
unset( $GLOBALS['airtable_fail'] );

echo "\n=== Claims: under the lock ===\n";
// What Task 3 left in the live offer: index 0 claimed by 20 (ledger only), index 1 void, 2 to 19 void, MORE-1 to MORE-3 available at 20 to 22.
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
ck( 'a held lock turns a new claimant away with "reload", and takes nothing', array( WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][23] )->get_error_code(), WPCPM_Sponsor_Codes::counts( $a1 )['available'] ), array( 'wpcpm_claim_busy', 2 ) );
$GLOBALS['opts'][ WPCPM_Sponsor_Claims::LOCK_PREFIX . $a1 ] = time() - WPCPM_Sponsor_Claims::LOCK_TIMEOUT - 1;
$stale = WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][23] );
ck( 'a lock older than the timeout belonged to a dead request and is taken over', array( $stale['new'], $stale['code'] ), array( true, 'MORE-2' ) );
ck( 'the lock is released after the claim', isset( $GLOBALS['opts'][ WPCPM_Sponsor_Claims::LOCK_PREFIX . $a1 ] ), false );
ck( 'a person may_claim() refuses gets the one refusal, and nothing moves', array( WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][21] )->get_error_code(), WPCPM_Sponsor_Codes::counts( $a1 )['available'] ), array( 'wpcpm_claim_refused', 1 ) );
ck( 'an offer that is not one is refused the same way', WPCPM_Sponsor_Claims::claim( 424242, $GLOBALS['users'][20] )->get_error_code(), 'wpcpm_claim_refused' );
$last = WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][24] );
ck( 'the last code goes', $last['code'], 'MORE-3' );
ck( 'and the next claimant meets an empty pool', WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][20] )->get_error_code(), 'wpcpm_claim_empty' );

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
ck( 'with a Reply-To at the claimant', $GLOBALS['sent'][0][3]['headers'], array( 'Reply-To: maciej@a8c.com' ) );
ck( 'and the way to the Sponsors screen at its Offers and codes tab, where the offers and their claimants are', false !== strpos( $GLOBALS['sent'][0][3]['body'], 'are on the Sponsors screen: https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=offers' . "\n" ), true );
ck( 'and it is logged on the sponsor with the claimant as the subject', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['subject'], end( $GLOBALS['audit'] )['ground'] ), array( 'claim_problem', '22', 'system' ) );
ck( 'somebody without a claim cannot report one', WPCPM_Sponsor_Claims::report_problem( $a1, $GLOBALS['users'][21] )->get_error_code(), 'wpcpm_problem_refused' );
WPCPM_Sponsor_Claims::report_problem( $a1, $GLOBALS['users'][22] );
WPCPM_Sponsor_Claims::report_problem( $a1, $GLOBALS['users'][22] );
ck( 'three a day, then the ceiling', WPCPM_Sponsor_Claims::report_problem( $a1, $GLOBALS['users'][22] )->get_error_code(), 'wpcpm_problem_limit' );
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
ck( 'the list names the people whose claims stand, oldest first, with the last four characters', array( count( $who ), $who[0]['name'], $who[0]['email'], $who[0]['last4'], $who[0]['index'] ), array( 5, 'Student Current', 'maciej@a8c.com', '0001', 0 ) );
ck( 'a voided claim is not in it', in_array( 20, array_map( static function ( $c ) { return $c['index']; }, $who ), true ), false );

echo "\n=== The pool's lock guards every rewrite ===\n";
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ] = time();
$before = WPCPM_Sponsor_Codes::read( $a1 );
ck( 'a paste while the pool is locked is told to try again, and adds nothing', array( WPCPM_Sponsor_Codes::add( $a1, 'Z-1' )->get_error_code(), WPCPM_Sponsor_Codes::read( $a1 ) === $before ), array( 'wpcpm_codes_busy', true ) );
ck( 'so is a void of the unclaimed codes', WPCPM_Sponsor_Codes::void_unclaimed( $a1 )->get_error_code(), 'wpcpm_codes_busy' );
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $b1 ] = time();
ck( 'and a change of the shared code', array( WPCPM_Sponsor_Codes::set_shared( $b1, 'OTHER' )->get_error_code(), WPCPM_Sponsor_Codes::shared( $b1 ) ), array( 'wpcpm_codes_busy', 'https://cirrus-example.example/checkout?code=WPCREDITS' ) );
ck( 'and a manager\'s void of a claim, which leaves the claim standing', array( WPCPM_Sponsor_Claims::void_claim( $b1, 23, 1 )->get_error_code(), WPCPM_Sponsor_Claims::has_claimed( 23, $b1 ) ), array( 'wpcpm_codes_busy', true ) );
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
ck( 'the card is the canonical section around a disclosure, with the count of offers', array( false !== strpos( $html, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-offers" class="wpcpm-group wpcpm-group__disclosure">' ), false !== strpos( $html, '<span class="wpcpm-group__count">3</span>' ) ), array( true, true ) );
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
$GLOBALS['patched'] = array(); $GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $a1, 'wpcpm_title' => 'Premium plugin, one year', 'wpcpm_text' => 'One year free', 'wpcpm_instructions' => 'Enter the code at checkout.', 'wpcpm_url' => 'https://plugins.mango-example.com/wpcredits2', 'wpcpm_audience' => array( 'mentors' ), 'wpcpm_low' => '5', 'wpcpm_expires' => '' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a member saves an offer and lands on the Offers card', array( $r[0], $r[1], $r[2] ), array( 'offer-saved', 'offers', $A ) );
$saved = WPCPM_Sponsor_Offers::read( $a1 );
ck( 'the fields were written', array( $saved['title'], $saved['text'], $saved['audience'], $saved['low'] ), array( 'Premium plugin, one year', 'One year free', array( 'mentors' ), 5 ) );
ck( 'the primary offer was mirrored: exactly the three fields', $GLOBALS['patched'][0][1][0]['fields'], array( 'Offer' => 'One year free', 'Brief instructions' => 'Enter the code at checkout.', 'More info link' => 'https://plugins.mango-example.com/wpcredits2' ) );
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
$GLOBALS['airtable_fail'] = true;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $a1, 'wpcpm_title' => 'Premium plugin, one year', 'wpcpm_text' => 'Two years free' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
unset( $GLOBALS['airtable_fail'] );
ck( 'when the base cannot be reached the save stands here and the flash says the records lag', array( $r[0], WPCPM_Sponsor_Offers::read( $a1 )['text'] ), array( 'offer-mirror-failed', 'Two years free' ) );

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
		$out['title'] = array( 'markup' => $m[1], 'shown' => $shows( $m[1] ), 'posts' => $shows( $m[1] ), 'maxlength' => (int) $m[2] );
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
$E = 'recSPONSOR0000005'; $F = 'recSPONSOR0000006'; $G = 'recSPONSOR0000007';
$row = static function ( $name, $offer, $instructions ) { return array( 'name' => $name, 'status' => 'Approved', 'website' => '', 'contact_person' => 'Rep', 'contact_email' => 'maciej@a8c.com', 'product_type' => 'Plugin', 'offer' => $offer, 'instructions' => $instructions, 'more_info' => '', 'coupon_link' => '', 'manager' => '', 'mentors' => array() ); };
$whole_text  = str_repeat( '&amp;', 200 ) . str_repeat( 'x', 300 );
$whole_instr = str_repeat( '&quot;', 800 ) . str_repeat( 'x', 3200 );
WPCPM_Sponsors_Index::write( array_merge( WPCPM_Sponsors_Index::rows(), array(
	$E => $row( 'Entity Example', $whole_text, $whole_instr ),
	$F => $row( 'Held Example', $t_team_mem, $t_ages_mem ),
	$G => $row( str_repeat( 'n', 130 ), str_repeat( 'x', 600 ), str_repeat( 'y', 5000 ) ),
) ), time() );
$GLOBALS['uid'] = 1;
$e_id = WPCPM_Sponsor_Offers::seed( $E );
$e    = WPCPM_Sponsor_Offers::read( $e_id );
ck( 'a text of 500 characters and instructions of 4,000, each longer than that in the form the records hold them, are imported whole, byte for byte', array( $e['text'] === $whole_text, $e['instructions'] === $whole_instr, WPCPM_Typed_Text::typed_length( $e['text'] ), WPCPM_Typed_Text::typed_length( $e['instructions'] ) ), array( true, true, 500, 4000 ) );
$f_id = WPCPM_Sponsor_Offers::seed( $F );
$f    = WPCPM_Sponsor_Offers::read( $f_id );
ck( 'the form the records hold a "<3" and a ">" in is imported as it is, and its boxes draw it as typed', array( $f['text'], $f['instructions'] ), array( $t_team_mem, $t_ages_mem ) );
$GLOBALS['uid'] = 5;
$f_boxes = wpcpm_test_boxes( card( 'WPCPM_Sponsor_Offers', $F, $ctx ), $f_id );
ck( 'the imported text\'s box shows what was meant and takes the limit and the 3 held', array( $f_boxes['text']['shown'], $f_boxes['text']['maxlength'], $f_boxes['instructions']['shown'], $f_boxes['instructions']['maxlength'] ), array( $t_team_box, 503, $t_ages, 4000 ) );
$GLOBALS['uid'] = 1;
$g_id = WPCPM_Sponsor_Offers::seed( $G );
$g    = WPCPM_Sponsor_Offers::read( $g_id );
ck( 'an import over the limit is cut to it, as it always was: nobody is there to refuse it to', array( mb_strlen( $g['title'] ), mb_strlen( $g['text'] ), mb_strlen( $g['instructions'] ) ), array( 120, 500, 4000 ) );
// Cut on a whole character as typed: an entity or a line break that straddles the limit is kept or
// left out whole, never split into a stray "&q" or a lone CR.
$K = 'recSPONSOR0000008';
WPCPM_Sponsors_Index::write( array_merge( WPCPM_Sponsors_Index::rows(), array( $K => $row( 'Cut Example', str_repeat( 'x', 498 ) . '&quot;yy', str_repeat( 'x', 3999 ) . "\r\nyy" ) ) ), time() );
$k = WPCPM_Sponsor_Offers::read( WPCPM_Sponsor_Offers::seed( $K ) );
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

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
