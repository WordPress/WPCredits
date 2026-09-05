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
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $url, $protocols = null ) { return preg_match( '#^https?://#i', (string) $url ) ? $url : ''; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_js( $s ) { return addslashes( (string) $s ); }
/** Core's own last step in _sanitize_text_fields(): every `%XX` is removed (finding 1). */
function wpcpm_test_strip_percent( $s ) { while ( preg_match( '/%[a-f0-9]{2}/i', $s, $m ) ) { $s = str_replace( $m[0], '', $s ); } return $s; }
function sanitize_text_field( $s ) { return wpcpm_test_strip_percent( trim( strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return wpcpm_test_strip_percent( trim( strip_tags( (string) $s ) ) ); }
function wp_check_invalid_utf8( $s, $strip = false ) { return (string) $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
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
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
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
function wp_insert_post( array $args, $wp_error = false ) {
	$id = $GLOBALS['next_post']++;
	$GLOBALS['posts'][ $id ] = new WP_Post( array( 'ID' => $id, 'post_type' => $args['post_type'] ?? 'post', 'post_title' => $args['post_title'] ?? '', 'post_status' => $args['post_status'] ?? 'publish', 'post_author' => $args['post_author'] ?? 0 ) );
	return $id;
}
function wp_update_post( array $args ) { $id = (int) $args['ID']; if ( isset( $GLOBALS['posts'][ $id ] ) && isset( $args['post_title'] ) ) { $GLOBALS['posts'][ $id ]->post_title = $args['post_title']; } return $id; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
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
}
/** The real writer's contract: a BOM, one line per row, cells joined by commas, a leading formula character neutralised. */
class WPCPM_Institution_Export {
	public static function cell( $value ) { $text = is_scalar( $value ) ? (string) $value : ''; $lead = ltrim( $text ); return ( '' !== $lead && in_array( substr( $lead, 0, 1 ), array( '=', '+', '-', '@' ), true ) ) ? "'" . $text : $text; }
	public static function csv( array $matrix ) { $out = "\xEF\xBB\xBF"; foreach ( $matrix as $row ) { $out .= implode( ',', array_map( array( __CLASS__, 'cell' ), $row ) ) . "\r\n"; } return $out; }
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-secret.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-codes.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-offers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-claims.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tools.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-usage.php';

// The fixture: two Approved sponsors, a manager, a member of each, and the people who claim.
$A = 'recSPONSOR0000001'; $B = 'recSPONSOR0000002'; $T = 'recTEAM0000000001';
WPCPM_Sponsors_Index::write( array(
	$A => array( 'name' => 'miniOrange', 'status' => 'Approved', 'website' => 'https://plugins.miniorange.com/', 'contact_person' => 'Rep One', 'contact_email' => 'maciej@a8c.com', 'product_type' => 'Plugin', 'offer' => 'One year of the premium plugin', 'instructions' => 'Enter the code at checkout.', 'more_info' => 'https://plugins.miniorange.com/wpcredits', 'coupon_link' => 'https://docs.google.com/spreadsheets/d/abc/edit', 'manager' => $T, 'mentors' => array() ),
	$B => array( 'name' => 'Cloud86', 'status' => 'Approved', 'website' => 'https://cloud86.example/', 'contact_person' => 'Rep Two', 'contact_email' => 'maciej@a8c.com', 'product_type' => 'Hosting', 'offer' => 'A year of hosting', 'instructions' => 'Use the link.', 'more_info' => '', 'coupon_link' => 'https://cloud86.example/checkout?code=WPCREDITS', 'manager' => '', 'mentors' => array() ),
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
$GLOBALS['umeta'][5] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $A, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['umeta'][6] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $B, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['program'] = array( 20 => array( 'status' => 'In Sensei' ), 21 => array( 'status' => 'Graduate' ), 22 => array( 'status' => 'Paused' ), 23 => array( 'status' => 'Developer Track' ), 24 => array( 'status' => 'In Sensei' ) );
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
ck( 'titled with the sponsor name, the text from Offer, the instructions and the link from the base', array( $offer['title'], $offer['text'], $offer['instructions'], $offer['url'] ), array( 'miniOrange', 'One year of the premium plugin', 'Enter the code at checkout.', 'https://plugins.miniorange.com/wpcredits' ) );
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
ck( 'holding that link as the shared code', WPCPM_Sponsor_Codes::shared( $b1 ), 'https://cloud86.example/checkout?code=WPCREDITS' );
ck( 'and not in the clear', strpos( serialize( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::option_name( $b1 ) ] ), 'WPCREDITS' ), false );
$D = 'recSPONSOR0000004';
WPCPM_Sponsors_Index::write( array_merge( WPCPM_Sponsors_Index::rows(), array( $D => array( 'name' => 'Drive Sheet', 'status' => 'Approved', 'website' => '', 'contact_person' => 'Rep Four', 'contact_email' => 'maciej@a8c.com', 'product_type' => 'Plugin', 'offer' => 'A sheet of codes', 'instructions' => '', 'more_info' => '', 'coupon_link' => 'https://drive.google.com/open?id=x', 'manager' => '', 'mentors' => array() ) ) ), time() );
$d_seed = WPCPM_Sponsor_Offers::seed( $D );
ck( 'a coupon sheet shared as a Drive link is the sheet too, so it seeds a pool and no shared code', array( WPCPM_Sponsor_Offers::read( $d_seed )['kind'], WPCPM_Sponsor_Codes::shared( $d_seed ) ), array( 'codes', '' ) );
ck( 'an unknown sponsor cannot be seeded', WPCPM_Sponsor_Offers::seed( 'recNOTINDEXED0001' )->get_error_code(), 'wpcpm_offer_sponsor' );
ck( 'offers_of() lists by sponsor', array( array_keys( WPCPM_Sponsor_Offers::offers_of( $A ) ), array_keys( WPCPM_Sponsor_Offers::offers_of( $B ) ) ), array( array( $a1 ), array( $b1 ) ) );
ck( 'find() with the wrong sponsor is null', array( WPCPM_Sponsor_Offers::find( $a1, $B ), WPCPM_Sponsor_Offers::find( $a1, $A )['id'] ), array( null, $a1 ) );

echo "\n=== Cleaning a posted offer ===\n";
$raw = array( 'title' => ' Premium plugin ', 'text' => 'A year', 'instructions' => 'Checkout', 'url' => 'plugins.miniorange.com/x', 'kind' => 'codes', 'audience' => array( 'mentors', 'students', 'bogus' ), 'low' => '2000', 'expires' => '2026-12-31' );
$clean = WPCPM_Sponsor_Offers::clean( $raw );
ck( 'a title is trimmed, a URL completed, the audience filtered, the threshold clamped, the day kept as a string', array( $clean['ok'], $clean['fields']['title'], $clean['fields']['url'], $clean['fields']['audience'], $clean['fields']['low'], $clean['fields']['expires'] ), array( true, 'Premium plugin', 'https://plugins.miniorange.com/x', array( 'mentors' ), 1000, '2026-12-31' ) );
ck( 'no title, no offer', WPCPM_Sponsor_Offers::clean( array( 'title' => '  ' ) )['reason'], 'title' );
ck( 'a link with a user part is refused', WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'url' => 'https://name@host.test' ) )['reason'], 'url' );
ck( 'a kind that is not one is refused', WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'kind' => 'gift' ) )['reason'], 'kind' );
ck( 'an impossible day is refused', WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'expires' => '2026-02-30' ) )['reason'], 'expires' );
ck( 'and so is a day in another format', WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'expires' => '31/12/2026' ) )['reason'], 'expires' );
ck( 'an empty threshold takes the setting', WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'low' => '' ) )['fields']['low'], 10 );
ck( 'text is capped at MAX_OFFER and instructions at MAX_TEXT', array( mb_strlen( WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'text' => str_repeat( 'a', 600 ) ) )['fields']['text'] ), mb_strlen( WPCPM_Sponsor_Offers::clean( array( 'title' => 'x', 'instructions' => str_repeat( 'a', 5000 ) ) )['fields']['instructions'] ) ), array( 500, 4000 ) );

echo "\n=== The pool ===\n";
$parsed = WPCPM_Sponsor_Codes::parse( "CODE-1\r\n  CODE-2  \n\nCODE-3,unused column\nhttps://shop.example/buy?a=1,b=2\n" . str_repeat( 'x', 201 ) . "\nCODE-1\n" );
ck( 'lines are trimmed, blank lines skipped, a CSV row gives its first column, a URL with a comma is kept whole', $parsed['codes'], array( 'CODE-1', 'CODE-2', 'CODE-3', 'https://shop.example/buy?a=1,b=2' ) );
ck( 'the long line and the repeat are named by line number', $parsed['errors'], array( 'Line 6 is longer than 200 characters.', 'Line 7 repeats line 1.' ) );
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
WPCPM_Sponsor_Codes::set_shared( $b1, 'https://cloud86.example/checkout?code=WPCREDITS' );
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
WPCPM_Sponsor_Offers::save( $a1, array( 'text' => 'Two years of the premium plugin', 'url' => 'https://plugins.miniorange.com/wpcredits2' ) );
$GLOBALS['patched'] = array();
ck( 'the primary offer writes exactly three fields, spelled as the base spells them', array( WPCPM_Sponsor_Offers::mirror( WPCPM_Sponsor_Offers::read( $a1 ) ), $GLOBALS['patched'][0][1][0]['fields'] ), array( true, array( 'Offer' => 'Two years of the premium plugin', 'Brief instructions' => 'Enter the code at checkout.', 'More info link' => 'https://plugins.miniorange.com/wpcredits2' ) ) );
ck( 'to the sponsor\'s record in the sponsors table', array( $GLOBALS['patched'][0][0], $GLOBALS['patched'][0][1][0]['id'] ), array( 'tblSPONSORS', $A ) );
ck( 'and the index at once', array( WPCPM_Sponsors_Index::row( $A )['offer'], WPCPM_Sponsors_Index::row( $A )['more_info'] ), array( 'Two years of the premium plugin', 'https://plugins.miniorange.com/wpcredits2' ) );
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
ck( 'the mail names the offer, the count and the Offers card, and no code', array( false !== strpos( $body, 'miniOrange' ), false !== strpos( $body, '#wpcpm-sponsor-offers' ), false !== strpos( $body, WPCPM_Sponsor_Roster::ARG_VIEW . '=' . $A ), strpos( $body, 'MORE-' ) ), array( true, true, true, false ) );
ck( 'adding codes through the offer re-arms the warning', array( WPCPM_Sponsor_Offers::add_codes( $a1, "LATE-1\nLATE-2" ), WPCPM_Sponsor_Offers::read( $a1 )['low_sent'] ), array( 2, 0 ) );
WPCPM_Sponsor_Claims::claim( $a1, $GLOBALS['users'][20] );
ck( 'so the next crossing mails again', count( $low() ), 4 );

echo "\n=== Problems and voids ===\n";
$GLOBALS['sent']  = array();
$GLOBALS['audit'] = array();
$report = WPCPM_Sponsor_Claims::report_problem( $a1, $GLOBALS['users'][22] );
ck( 'a claimant reports a problem: the manager is mailed the name, the offer and the last four characters, never the code', array( $report['mailed'], $GLOBALS['sent'][0][2], false !== strpos( $GLOBALS['sent'][0][3]['body'], 'Student Paused' ), false !== strpos( $GLOBALS['sent'][0][3]['body'], 'RE-1' ), strpos( $GLOBALS['sent'][0][3]['body'], 'MORE-1' ) ), array( 1, 'claim-problem', true, true, false ) );
ck( 'with a Reply-To at the claimant', $GLOBALS['sent'][0][3]['headers'], array( 'Reply-To: maciej@a8c.com' ) );
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
ck( 'everyone gets the same link, and each claim is in the ledger once', array( $s1['code'], $s2['code'], $s1['index'], count( WPCPM_Sponsor_Codes::claims( $b1 ) ) ), array( 'https://cloud86.example/checkout?code=WPCREDITS', 'https://cloud86.example/checkout?code=WPCREDITS', WPCPM_Sponsor_Codes::SHARED_INDEX, 2 ) );
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
ck( 'carries the titles and the month columns, and neutralises a title that starts like a formula', array( false !== strpos( $csv, 'miniOrange' ), false !== strpos( $csv, gmdate( 'Y-m' ) ), false !== strpos( $csv, "'=SUM(1)" ) ), array( true, true, true ) );
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
ck( 'and a change of the shared code', array( WPCPM_Sponsor_Codes::set_shared( $b1, 'OTHER' )->get_error_code(), WPCPM_Sponsor_Codes::shared( $b1 ) ), array( 'wpcpm_codes_busy', 'https://cloud86.example/checkout?code=WPCREDITS' ) );
ck( 'and a manager\'s void of a claim, which leaves the claim standing', array( WPCPM_Sponsor_Claims::void_claim( $b1, 23, 1 )->get_error_code(), WPCPM_Sponsor_Claims::has_claimed( 23, $b1 ) ), array( 'wpcpm_codes_busy', true ) );
unset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ], $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $b1 ] );
ck( 'with the lock gone the paste goes in and the lock is released after it', array( WPCPM_Sponsor_Codes::add( $a1, 'Z-1' ), isset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ] ) ), array( 1, false ) );
$GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ] = time() - WPCPM_Sponsor_Codes::LOCK_TIMEOUT - 1;
ck( 'a stale lock is taken over by a void', is_int( WPCPM_Sponsor_Codes::void_unclaimed( $a1 ) ), true );
ck( 'and released', isset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $a1 ] ), false );

echo "\n=== The Offers card ===\n";
$GLOBALS['uid'] = 5;
$context = array( 'can_manage' => false, 'open' => '', 'viewer' => $GLOBALS['users'][5] );
$html    = card( 'WPCPM_Sponsor_Offers', $A, $context );
ck( 'the card is the canonical section around a disclosure, with the count of offers', array( false !== strpos( $html, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-offers" class="wpcpm-group wpcpm-group__disclosure">' ), false !== strpos( $html, '<span class="wpcpm-group__count">3</span>' ) ), array( true, true ) );
ck( 'each offer has an edit form with its own nonce, the state buttons it may press, and the codes box for a pool', array( false !== strpos( $html, 'nonce-' . WPCPM_Sponsor_Offers::ACTION_SAVE . '_' . $a1 ), false !== strpos( $html, 'name="wpcpm_state" value="paused"' ), false !== strpos( $html, 'name="wpcpm_codes"' ), false !== strpos( $html, 'nonce-' . WPCPM_Sponsor_Offers::ACTION_CODES_ADD . '_' . $a1 ) ), array( true, true, true, true ) );
ck( 'the counts are drawn, and the pool never shows a code', array( false !== strpos( $html, '0 available' ), strpos( $html, 'LATE-' ), strpos( $html, 'WPCE-' ) ), array( true, false, false ) );
ck( 'a live pool below its threshold warns', false !== strpos( $html, 'wpcpm-offer__warning' ), true );
ck( 'the kind is fixed once the pool holds anything: the live pool shows it fixed, the two empty offers and the new form still choose', array( false !== strpos( $html, 'wpcpm-offer__kind-fixed' ), substr_count( $html, 'name="wpcpm_kind"' ) ), array( true, 3 ) );
ck( 'and the new-offer form is at the end with the "new" nonce and a kind to choose', array( false !== strpos( $html, 'nonce-' . WPCPM_Sponsor_Offers::ACTION_SAVE . '_new' ), substr_count( $html, 'name="wpcpm_kind"' ) >= 1 ), array( true, true ) );
$html_b = card( 'WPCPM_Sponsor_Offers', $B, $context );
ck( 'a shared offer shows its own link in the form and no codes box', array( false !== strpos( $html_b, 'value="https://cloud86.example/checkout?code=WPCREDITS"' ), strpos( $html_b, 'name="wpcpm_codes"' ) ), array( true, false ) );

echo "\n=== The Offers card: saving ===\n";
$GLOBALS['patched'] = array(); $GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $a1, 'wpcpm_title' => 'Premium plugin, one year', 'wpcpm_text' => 'One year free', 'wpcpm_instructions' => 'Enter the code at checkout.', 'wpcpm_url' => 'https://plugins.miniorange.com/wpcredits2', 'wpcpm_audience' => array( 'mentors' ), 'wpcpm_low' => '5', 'wpcpm_expires' => '' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a member saves an offer and lands on the Offers card', array( $r[0], $r[1], $r[2] ), array( 'offer-saved', 'offers', $A ) );
$saved = WPCPM_Sponsor_Offers::read( $a1 );
ck( 'the fields were written', array( $saved['title'], $saved['text'], $saved['audience'], $saved['low'] ), array( 'Premium plugin, one year', 'One year free', array( 'mentors' ), 5 ) );
ck( 'the primary offer was mirrored: exactly the three fields', $GLOBALS['patched'][0][1][0]['fields'], array( 'Offer' => 'One year free', 'Brief instructions' => 'Enter the code at checkout.', 'More info link' => 'https://plugins.miniorange.com/wpcredits2' ) );
ck( 'and logged with the field names, never the values', array( end( $GLOBALS['audit'] )['kind'], in_array( 'title', end( $GLOBALS['audit'] )['data']['fields'], true ), strpos( serialize( end( $GLOBALS['audit'] ) ), 'One year free' ) ), array( 'offer_saved', true, false ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $a1, 'wpcpm_title' => '', 'wpcpm_text' => 'x' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'a save without a title is rejected, with the reason as the detail', array( $r[0], $r[3] ), array( 'offer-rejected', 'Give the offer a title.' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $b1, 'wpcpm_title' => 'Theirs' ), array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
ck( 'an offer of another sponsor is the one refusal, before anything is read', array( $r[0], WPCPM_Sponsor_Offers::read( $b1 )['title'] ), array( 'refused', 'Cloud86' ) );
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
ck( 'no address and no code on the card', array( preg_match( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/', strip_tags( $html ) ), strpos( $html, 'LATE-' ) ), array( 0, false ) );
$shared_row = substr( $html, (int) strpos( $html, '<th scope="row">Second offer</th>' ) );
$shared_row = substr( $shared_row, 0, (int) strpos( $shared_row, '</tr>' ) );
ck( 'a shared offer has no pool, so its available, claimed and void cells are left blank', array( false !== strpos( $shared_row, '<td></td><td></td><td></td>' ), substr_count( $shared_row, '<td>' ) ), array( true, 6 ) );
$GLOBALS['uid'] = 20;
ck( 'the export refuses everyone but the sponsor and a manager', post( array( 'wpcpm_sponsor' => $A ), array( 'WPCPM_Sponsor_Usage', 'handle_export' ) )[0], 'refused' );
$GLOBALS['uid'] = 5;
$usage_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-usage.php' );
ck( 'the export claims ACT_VIEW_STATS before it builds anything, and sends a CSV attachment', array( strpos( $usage_src, 'ACT_VIEW_STATS' ) < strpos( $usage_src, 'WPCPM_Sponsor_Claims::csv(' ), false !== strpos( $usage_src, 'Content-Disposition: attachment' ) ), array( true, true ) );

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
ck( 'and never writes its option autoloaded', preg_match( '/add_option\([^;]*(\'yes\'|true)\s*\)/', $codes_src ), 0 );
$claims_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-claims.php' );
ck( 'no em or en dash in the claims class', preg_match( '/\x{2013}|\x{2014}/u', $claims_src ), 0 );
ck( 'nothing about a claim ever reaches Airtable', preg_match( '/WPCPM_Airtable|update_records|WPCPM_Sponsors_Index::patch/', $claims_src ), 0 );
ck( 'the claim looks the person up before it takes the lock', strpos( $claims_src, 'self::claims_of( $user->ID )' ) < strpos( $claims_src, 'WPCPM_Sponsor_Codes::lock( $offer[' ), true );
ck( 'every rewrite of the pool but the two claim() makes under its lock takes the lock itself', substr_count( $codes_src, 'self::lock( $offer_id )' ) >= 5, true );
ck( 'and the claims class no longer keeps a lock of its own', preg_match( '/function (un)?lock\(/', $claims_src ), 0 );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
