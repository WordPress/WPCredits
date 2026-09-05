<?php
/**
 * "Tools from our sponsors": who may claim, on whose card the section is drawn, and what it
 * shows to whom (Phase S2, spec section 6.5 and the five clauses of 6.3).
 *
 * What is pinned here and why:
 *
 * - may_claim() clause by clause: a Graduate is refused, a Paused student allowed, a mentor only
 *   when the offer opens to mentors, a manager only when it opens to managers, a second claim
 *   refused, an empty pool refused, an expired or paused offer refused, a shared offer with
 *   nothing to share refused.
 * - The section is drawn on the person's own card and never on somebody else's view of it: the
 *   caller passes the viewer, and a manager gets one muted count line instead (Task 8 wires it).
 * - The switches, the sort (sponsor name, then title), the empty-pool rule per viewer, "Your
 *   codes" listing a claim from an ended offer, and the claim form appearing only when
 *   may_claim() says yes.
 * - The handlers flash on the viewer's own channel and send them back where they came from.
 *
 * Run from the plugin root:  php bin/test-sponsor-tools.php
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
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['actions'][] = $h; }
function register_post_type( $type, $args ) { $GLOBALS['post_types'][ $type ] = $args; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $changed = ! array_key_exists( $k, $GLOBALS['opts'] ) || $GLOBALS['opts'][ $k ] !== $v; $GLOBALS['opts'][ $k ] = $v; return $changed; }
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
	30 => new WP_User( 30, array( 'wpcpm_mentor' ), 'Mentor One', 'maciej@a8c.com' ),
	31 => new WP_User( 31, array( 'wpcpm_student' ), 'Student Unsynced', 'maciej@a8c.com' ),
);
$GLOBALS['manage'] = array( 1 );
$GLOBALS['umeta'][5] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $A, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['umeta'][6] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $B, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['program'] = array( 20 => array( 'status' => 'In Sensei' ), 21 => array( 'status' => 'Graduate' ), 22 => array( 'status' => 'Paused' ), 23 => array( 'status' => 'Developer Track' ) );
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

// The offers: A is a pool for students, B a shared link for students, C a pool open to mentors
// too, D an ended pool user 20 claimed from, E a pool whose last day has passed, F open to
// managers. All live except D and E.
$GLOBALS['uid'] = 5;
$a1 = WPCPM_Sponsor_Offers::seed( $A );
WPCPM_Sponsor_Offers::add_codes( $a1, "A-1\nA-2\nA-3" );
WPCPM_Sponsor_Offers::set_state( $a1, 'live' );
$b1 = WPCPM_Sponsor_Offers::seed( $B );
WPCPM_Sponsor_Offers::set_state( $b1, 'live' );
$mk = static function ( $record, $title, array $audience, $codes, $state, $expires = '' ) {
	$id = WPCPM_Sponsor_Offers::create( $record, array( 'title' => $title, 'kind' => 'codes', 'text' => 'What you get', 'instructions' => 'How', 'url' => '', 'audience' => $audience, 'low' => 10, 'expires' => $expires ) );
	WPCPM_Sponsor_Offers::add_codes( $id, $codes );
	if ( 'draft' !== $state ) { WPCPM_Sponsor_Offers::set_state( $id, 'live' ); }
	if ( 'ended' === $state ) { WPCPM_Sponsor_Offers::set_state( $id, 'ended' ); }
	return $id;
};
$c1 = $mk( $A, 'For mentors too', array( 'mentors' ), "C-1\nC-2", 'live' );
$d1 = $mk( $A, 'Old offer', array(), 'D-1', 'live' );
WPCPM_Sponsor_Claims::claim( $d1, $GLOBALS['users'][20] );
WPCPM_Sponsor_Offers::set_state( $d1, 'ended' );
$e1 = $mk( $A, 'Expired', array(), 'E-1', 'live', '2020-01-01' );
$f1 = $mk( $B, 'For the program team', array( 'managers' ), 'F-1', 'live' );
$offer = static function ( $id ) { return WPCPM_Sponsor_Offers::read( $id ); };

echo "=== Who is who ===\n";
ck( 'a manager is a manager whatever else the account holds', WPCPM_Sponsor_Tools::kind_of( $GLOBALS['users'][1] ), 'managers' );
ck( 'a student whose status is current is a student', WPCPM_Sponsor_Tools::kind_of( $GLOBALS['users'][20] ), 'students' );
ck( 'a Paused student is current', WPCPM_Sponsor_Tools::is_current_student( $GLOBALS['users'][22] ), true );
ck( 'a Graduate is not', WPCPM_Sponsor_Tools::is_current_student( $GLOBALS['users'][21] ), false );
ck( 'and neither is a student the sync has not reached', WPCPM_Sponsor_Tools::is_current_student( $GLOBALS['users'][31] ), false );
ck( 'a mentor is a mentor', WPCPM_Sponsor_Tools::kind_of( $GLOBALS['users'][30] ), 'mentors' );
ck( 'a graduate is nobody here', WPCPM_Sponsor_Tools::kind_of( $GLOBALS['users'][21] ), '' );

echo "\n=== may_claim(), clause by clause ===\n";
ck( '1. a current student may claim a live offer', WPCPM_Sponsor_Tools::may_claim( $GLOBALS['users'][20], $offer( $a1 ) ), true );
ck( '2. a Graduate may not', WPCPM_Sponsor_Tools::may_claim( $GLOBALS['users'][21], $offer( $a1 ) ), false );
ck( '2. a Paused student may', WPCPM_Sponsor_Tools::may_claim( $GLOBALS['users'][22], $offer( $a1 ) ), true );
ck( '2. a mentor may not claim an offer for students only', WPCPM_Sponsor_Tools::may_claim( $GLOBALS['users'][30], $offer( $a1 ) ), false );
ck( '2. but may when the offer opens to mentors', WPCPM_Sponsor_Tools::may_claim( $GLOBALS['users'][30], $offer( $c1 ) ), true );
ck( '2. a manager may not claim an offer for students', WPCPM_Sponsor_Tools::may_claim( $GLOBALS['users'][1], $offer( $a1 ) ), false );
ck( '2. but may when the offer opens to managers', WPCPM_Sponsor_Tools::may_claim( $GLOBALS['users'][1], $offer( $f1 ) ), true );
ck( '1. an offer past its last day is closed', WPCPM_Sponsor_Tools::may_claim( $GLOBALS['users'][20], $offer( $e1 ) ), false );
$paused = $offer( $a1 );
$paused['state'] = 'paused';
ck( '1. and so is a paused one', WPCPM_Sponsor_Tools::may_claim( $GLOBALS['users'][20], $paused ), false );
ck( '3. a second claim is refused', WPCPM_Sponsor_Tools::may_claim( $GLOBALS['users'][20], $offer( $d1 ) ), false );
$empty = $mk( $A, 'Empty', array(), 'X-1', 'live' );
WPCPM_Sponsor_Claims::claim( $empty, $GLOBALS['users'][23] );
ck( '4. an empty pool is refused', WPCPM_Sponsor_Tools::may_claim( $GLOBALS['users'][20], $offer( $empty ) ), false );
WPCPM_Sponsor_Codes::set_shared( $b1, '' );
ck( '5. a shared offer with nothing to share is refused', WPCPM_Sponsor_Tools::may_claim( $GLOBALS['users'][20], $offer( $b1 ) ), false );
WPCPM_Sponsor_Codes::set_shared( $b1, 'https://cloud86.example/checkout?code=WPCREDITS' );
ck( 'and nobody at all may claim', WPCPM_Sponsor_Tools::may_claim( new WP_User( 0 ), $offer( $a1 ) ), false );

echo "\n=== may_claim_reason(): which clause said no ===\n";
ck( 'a current student on the live pool has nothing against them', WPCPM_Sponsor_Tools::may_claim_reason( $GLOBALS['users'][20], $offer( $a1 ) ), '' );
ck( 'an offer past its last day is closed', WPCPM_Sponsor_Tools::may_claim_reason( $GLOBALS['users'][20], $offer( $e1 ) ), 'closed' );
// The ended offer user 20 holds fails clause 1 before clause 3 is reached, so it reads as
// closed, not claimed: the clauses answer in may_claim()'s own order.
ck( 'and so is the ended one, before the claim on it is even looked at', WPCPM_Sponsor_Tools::may_claim_reason( $GLOBALS['users'][20], $offer( $d1 ) ), 'closed' );
ck( 'a Graduate is the wrong kind of account', WPCPM_Sponsor_Tools::may_claim_reason( $GLOBALS['users'][21], $offer( $a1 ) ), 'kind' );
ck( 'a live offer the person already holds is claimed', WPCPM_Sponsor_Tools::may_claim_reason( $GLOBALS['users'][23], $offer( $empty ) ), 'claimed' );
ck( 'a pool with nothing left is empty, which is not about them', WPCPM_Sponsor_Tools::may_claim_reason( $GLOBALS['users'][20], $offer( $empty ) ), 'empty' );
WPCPM_Sponsor_Codes::set_shared( $b1, '' );
ck( 'and a shared offer with nothing to show is unshared', WPCPM_Sponsor_Tools::may_claim_reason( $GLOBALS['users'][20], $offer( $b1 ) ), 'unshared' );
WPCPM_Sponsor_Codes::set_shared( $b1, 'https://cloud86.example/checkout?code=WPCREDITS' );

echo "\n=== The section on a student's own card ===\n";
function section( $audience, $uid ) { $GLOBALS['uid'] = $uid; ob_start(); WPCPM_Sponsor_Tools::render( $audience, $GLOBALS['users'][ $uid ] ); return ob_get_clean(); }
$html = section( 'students', 20 );
ck( 'it is a student section with the anchor and the heading', array( false !== strpos( $html, '<section class="wpcpm-student__section wpcpm-tools" id="wpcpm-tools">' ), false !== strpos( $html, 'Tools from our sponsors' ) ), array( true, true ) );
$pos = static function ( $id ) use ( $html ) { return strpos( $html, 'name="wpcpm_offer" value="' . $id . '"' ); };
ck( 'the live offers open to students are listed, sorted by sponsor name then title', false !== $pos( $b1 ) && $pos( $b1 ) < $pos( $f1 ) && $pos( $f1 ) < $pos( $c1 ) && $pos( $c1 ) < $pos( $a1 ), true );
ck( 'an offer that also opens to mentors, or to the program team, is still open to students', array( false !== $pos( $c1 ), false !== $pos( $f1 ) ), array( true, true ) );
ck( 'nor an expired one, nor the ended one, nor the empty one', array( strpos( $html, 'Expired' ), strpos( $html, 'name="wpcpm_offer" value="' . $d1 . '"' ), strpos( $html, 'name="wpcpm_offer" value="' . $empty . '"' ) ), array( false, false, false ) );
ck( 'each open offer carries a claim form with its nonce, and no code', array( substr_count( $html, 'name="action" value="' . WPCPM_Sponsor_Tools::ACTION_CLAIM . '"' ), false !== strpos( $html, 'nonce-' . WPCPM_Sponsor_Tools::ACTION_CLAIM . '_' . $a1 ), strpos( $html, 'A-1' ) ), array( 4, true, false ) );
ck( 'the sponsor logo, name and website are drawn', array( false !== strpos( $html, 'wpcpm-tools__logo' ), false !== strpos( $html, 'https://plugins.miniorange.com/' ) ), array( true, true ) );
ck( '"Your codes" lists the claim from the ended offer, with the code', array( false !== strpos( $html, 'Your codes' ), false !== strpos( $html, 'Old offer' ), false !== strpos( $html, '>D-1<' ) ), array( true, true, true ) );
$_POST = array( 'wpcpm_offer' => $a1 );
$GLOBALS['referer'] = 'https://example.test/student-report-card/';
$r = post( $_POST, array( 'WPCPM_Sponsor_Tools', 'handle_claim' ) );
ck( 'claiming flashes on the viewer\'s own channel and returns them to the page they were on, at the section', array( $r, WPCPM_Flash::take( WPCPM_Sponsor_Tools::FLASH, 20 ) ), array( array( 'redirect', 'https://example.test/student-report-card/#wpcpm-tools' ), array( 'status' => 'claimed' ) ) );
WPCPM_Flash::set( WPCPM_Sponsor_Tools::FLASH, array( 'status' => 'claimed' ), 20 );
$html = section( 'students', 20 );
ck( 'the flash prints once with its tone, and the claimed offer shows the code, selectable, with the date and a problem form', array( false !== strpos( $html, 'wpcpm-dashboard__message--success' ), false !== strpos( $html, '<code class="wpcpm-tools__code" data-wpcpm-select tabindex="0">A-1</code>' ), false !== strpos( $html, 'name="action" value="' . WPCPM_Sponsor_Tools::ACTION_PROBLEM . '"' ), substr_count( $html, 'name="action" value="' . WPCPM_Sponsor_Tools::ACTION_CLAIM . '"' ) ), array( true, true, true, 3 ) );
ck( 'and the flash is gone the second time', strpos( section( 'students', 20 ), 'wpcpm-dashboard__message' ), false );
$r = post( array( 'wpcpm_offer' => $a1 ), array( 'WPCPM_Sponsor_Tools', 'handle_claim' ) );
ck( 'claiming again is told so, not given another code', array( WPCPM_Flash::take( WPCPM_Sponsor_Tools::FLASH, 20 )['status'], WPCPM_Sponsor_Codes::counts( $a1 )['claimed'] ), array( 'claimed-again', 1 ) );
$GLOBALS['uid'] = 21;
$r = post( array( 'wpcpm_offer' => $a1 ), array( 'WPCPM_Sponsor_Tools', 'handle_claim' ) );
ck( 'a Graduate pressing by hand is refused, with nothing taken', array( WPCPM_Flash::take( WPCPM_Sponsor_Tools::FLASH, 21 )['status'], WPCPM_Sponsor_Codes::counts( $a1 )['claimed'] ), array( 'claim-refused', 1 ) );
$GLOBALS['uid'] = 0;
ck( 'a logged-out press is refused', post( array( 'wpcpm_offer' => $a1 ), array( 'WPCPM_Sponsor_Tools', 'handle_claim' ) )[0], 'redirect' );
$GLOBALS['nonce_ok'] = false;
ck( 'a bad nonce dies', post( array( 'wpcpm_offer' => $a1 ), array( 'WPCPM_Sponsor_Tools', 'handle_claim' ) )[0], 'die' );
$GLOBALS['nonce_ok'] = true;
$GLOBALS['uid'] = 20;
$GLOBALS['sent'] = array();
post( array( 'wpcpm_offer' => $a1 ), array( 'WPCPM_Sponsor_Tools', 'handle_problem' ) );
ck( 'reporting a problem thanks the person and mails the manager', array( WPCPM_Flash::take( WPCPM_Sponsor_Tools::FLASH, 20 )['status'], $GLOBALS['sent'][0][2] ), array( 'problem-sent', 'claim-problem' ) );
post( array( 'wpcpm_offer' => $a1 ), array( 'WPCPM_Sponsor_Tools', 'handle_problem' ) );
post( array( 'wpcpm_offer' => $a1 ), array( 'WPCPM_Sponsor_Tools', 'handle_problem' ) );
post( array( 'wpcpm_offer' => $a1 ), array( 'WPCPM_Sponsor_Tools', 'handle_problem' ) );
ck( 'three reports a day, and the fourth press is told the limit', WPCPM_Flash::take( WPCPM_Sponsor_Tools::FLASH, 20 )['status'], 'problem-limit' );
post( array( 'wpcpm_offer' => $empty ), array( 'WPCPM_Sponsor_Tools', 'handle_claim' ) );
ck( 'a pool that emptied since the page was drawn says so, not "not open to your account"', WPCPM_Flash::take( WPCPM_Sponsor_Tools::FLASH, 20 )['status'], 'claim-empty' );
$GLOBALS['referer'] = false;
$r = post( array( 'wpcpm_offer' => $c1 ), array( 'WPCPM_Sponsor_Tools', 'handle_claim' ) );
ck( 'without a referer the person lands on the site front page, at the section', $r, array( 'redirect', 'https://example.test/#wpcpm-tools' ) );

echo "\n=== Not on somebody else's card ===\n";
$GLOBALS['settings']['tools_students'] = false;
ck( 'the switch off draws nothing', section( 'students', 20 ), '' );
$GLOBALS['settings']['tools_students'] = true;
ck( 'a person with nothing open and nothing claimed gets no section at all', section( 'students', 21 ), '' );
ob_start(); WPCPM_Sponsor_Tools::render_count_line( $GLOBALS['users'][20] ); $line = ob_get_clean();
ck( 'the manager\'s view of a student is one muted line with the count', $line, '<p class="wpcpm-tools__count wpcpm-student__note">3 tools claimed</p>' );
ob_start(); WPCPM_Sponsor_Tools::render_count_line( $GLOBALS['users'][21] ); $none = ob_get_clean();
ck( 'and nothing for somebody with no claim', $none, '' );

echo "\n=== Mentors and managers ===\n";
ck( 'mentors see nothing until the program switches them on', section( 'mentors', 30 ), '' );
$GLOBALS['settings']['tools_mentors'] = true;
$html = section( 'mentors', 30 );
ck( 'then the offers open to mentors, and only those', array( false !== strpos( $html, 'For mentors too' ), strpos( $html, 'name="wpcpm_offer" value="' . $a1 . '"' ), strpos( $html, 'Cloud86' ) ), array( true, false, false ) );
$html = section( 'managers', 1 );
ck( 'a manager sees every live offer, labelled with its audience', array( false !== strpos( $html, 'For the program team' ), false !== strpos( $html, 'miniOrange' ), false !== strpos( $html, 'Open to: students, mentors' ) ), array( true, true, true ) );
ck( 'with a claim form only where the offer opens to managers, whose audience reads as the settings name them', array( substr_count( $html, 'name="action" value="' . WPCPM_Sponsor_Tools::ACTION_CLAIM . '"' ), false !== strpos( $html, 'name="wpcpm_offer" value="' . $f1 . '"' ), false !== strpos( $html, 'Open to: students, the program team' ) ), array( 1, true, true ) );
ck( 'and the empty pool with a warning where a student who holds nothing from it sees nothing', array( false !== strpos( $html, 'No codes left' ), strpos( section( 'students', 22 ), 'Empty' ) ), array( true, false ) );
ck( 'the expired offer is shown to nobody', strpos( $html, 'Expired' ), false );

echo "\n=== The call sites ===\n";
$students = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-students-dashboard.php' );
$own      = strpos( $students, "WPCPM_Sponsor_Tools::render( WPCPM_Sponsor_Tools::AUDIENCE_STUDENTS, \$viewer )" );
$count    = strpos( $students, 'WPCPM_Sponsor_Tools::render_count_line( $student )' );
ck( 'the Student Report Card draws the section after the report form and before the calendar', $own > strpos( $students, 'self::render_report_form( $program, $student );' ) && $own < strpos( $students, 'WPCPM_Call_Calendar::render_student(' ), true );
ck( 'on the student\'s own card only, with the count line for a manager viewing somebody else', $count > 0 && false !== strpos( $students, '$viewer->ID === $student->ID' ) && $count > $own, true );
$mentors = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-mentors-dashboard.php' );
$call    = strpos( $mentors, "WPCPM_Sponsor_Tools::render( WPCPM_Sponsor_Tools::AUDIENCE_MENTORS, \$viewer )" );
ck( 'the Mentor Report Card draws it after the past students and before the resources, for the mentor themself', $call > strpos( $mentors, "esc_html__( 'Past students', 'wpcredits-program-manager' )" ) && $call < strpos( $mentors, "WPCPM_Handbook_Assistant::render_resources( 'mentor' )" ) && false !== strpos( $mentors, '$viewer->ID === $mentor->ID' ), true );
$admin = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-administrators-dashboard.php' );
$acall = strpos( $admin, "WPCPM_Sponsor_Tools::render( WPCPM_Sponsor_Tools::AUDIENCE_MANAGERS, \$viewer )" );
ck( 'the Administrator Dashboard draws it after the syncs card and before the help', $acall > strpos( $admin, 'WPCPM_Administrators_Cards::render_health(' ) && $acall < strpos( $admin, 'self::render_help();' ), true );
ck( 'every call site is guarded by class_exists(), so a dashboard renders without the Sponsors module', array( substr_count( $students, "class_exists( 'WPCPM_Sponsor_Tools' )" ) >= 1, substr_count( $mentors, "class_exists( 'WPCPM_Sponsor_Tools' )" ) >= 1, substr_count( $admin, "class_exists( 'WPCPM_Sponsor_Tools' )" ) >= 1 ), array( true, true, true ) );

echo "\n=== House rules ===\n";
$src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tools.php' );
ck( 'no em or en dash in the class', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'the claim form is drawn only after may_claim() said yes', strpos( $src, 'self::may_claim( $viewer, $offer )' ) < strpos( $src, 'self::render_claim_form(' ), true );
ck( 'the section never asks the sponsor policy: a claimant is not a member', strpos( $src, 'WPCPM_Sponsor_Policy' ), false );
ck( 'nothing here writes to Airtable', preg_match( '/WPCPM_Airtable|update_records/', $src ), 0 );
$js = (string) file_get_contents( __DIR__ . '/../assets/js/forms.js' );
ck( 'forms.js selects a code on click without the clipboard API', array( false !== strpos( $js, 'data-wpcpm-select' ), false !== strpos( $js, 'selectNodeContents' ), strpos( $js, 'navigator.clipboard' ) ), array( true, true, false ) );
ck( 'and by keyboard too, through the one selection routine both listeners call', array( false !== strpos( $js, "'keydown'" ), false !== strpos( $js, 'selectContents' ) ), array( true, true ) );
$css = (string) file_get_contents( __DIR__ . '/../assets/css/dashboard.css' );
ck( 'the section has a base look in the stylesheet every dashboard loads', array( false !== strpos( $css, '.wpcpm-tools__list' ), false !== strpos( $css, '.wpcpm-tools__code' ) ), array( true, true ) );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
