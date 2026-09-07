<?php
/**
 * A claim under the pool's lock: the guard that stops the ordinary double submit.
 *
 * What is pinned here and why:
 *
 * - WPCPM_Sponsor_Claims::claim() looks the person's claims up twice, once before the lock and
 *   once under it. The second lookup is the only thing between a double-clicked "Get my code"
 *   and one person holding two codes from one offer, and every other suite reaches the first
 *   lookup alone: the guard could be deleted and all eighty-one suites stayed green (deep check
 *   FOFFR-6). The stub below serves a counted number of stale reads, which is what a second
 *   request that read before the first one wrote actually sees.
 * - The guard refuses only the second press of the same person: somebody else still gets the
 *   next code.
 *
 * Run from the plugin root:  php bin/test-sponsor-claims.php
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
// The ordinary double submit, which no stub could reach before: request B read the person's
// claims before request A wrote one, so B's lookup at the top of claim() and may_claim_reason()'s
// has_claimed() both see nothing, and only the lookup under the lock stops it. $GLOBALS['stale']
// is how many of the next reads of that meta key are served the value the request started with.
function get_user_meta( $id, $k, $single = false ) {
	if ( 'wpcpm_claims' === $k && $GLOBALS['stale'] > 0 ) { --$GLOBALS['stale']; return $GLOBALS['stale_value']; }
	return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? '';
}
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

// The fixture: one Approved sponsor, its member, a manager and two students.
$G = 'recSPONSOR0000007'; $T = 'recTEAM0000000001';
WPCPM_Sponsors_Index::write( array(
	$G => array( 'name' => 'Gizmo', 'status' => 'Approved', 'website' => 'https://gizmo.example/', 'contact_person' => 'Rep', 'contact_email' => 'maciej@a8c.com', 'product_type' => 'Plugin', 'offer' => 'A year of the plugin', 'instructions' => 'Enter the code at checkout.', 'more_info' => '', 'coupon_link' => '', 'manager' => $T, 'mentors' => array() ),
), time() );
WPCPM_Sponsors_Index::write_team( array( $T => array( 'name' => 'Maciej (Matt) Pilarski', 'email' => 'maciej@a8c.com', 'calendly' => '' ) ), time() );
$GLOBALS['settings'] = array( 'sponsors_table' => 'tblSPONSORS', 'offer_low_stock' => 0, 'student_statuses' => array( 'In Sensei' ), 'past_statuses' => array( 'Graduate' ), 'tools_students' => true, 'tools_mentors' => false );
$GLOBALS['users'] = array(
	1  => new WP_User( 1, array( 'administrator' ), 'Manager', 'maciej@a8c.com' ),
	5  => new WP_User( 5, array( 'wpcpm_sponsor' ), 'Rep One', 'maciej@a8c.com' ),
	20 => new WP_User( 20, array( 'wpcpm_student' ), 'Student One', 'maciej@a8c.com' ),
	23 => new WP_User( 23, array( 'wpcpm_student' ), 'Student Two', 'maciej@a8c.com' ),
);
$GLOBALS['manage'] = array( 1 );
$GLOBALS['umeta'][5] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $G, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['program'] = array( 20 => array( 'status' => 'In Sensei' ), 23 => array( 'status' => 'In Sensei' ) );
$GLOBALS['uid'] = 5; $GLOBALS['nonce_ok'] = true; $GLOBALS['patched'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['buckets'] = array(); $GLOBALS['now'] = gmmktime( 12, 0, 0, 9, 5, 2026 );
$GLOBALS['stale'] = 0; $GLOBALS['stale_value'] = array();

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

$g1 = WPCPM_Sponsor_Offers::create( $G, array( 'title' => 'A year of the plugin', 'kind' => 'codes', 'text' => '', 'instructions' => '', 'url' => '', 'audience' => array(), 'low' => 0, 'expires' => '' ) );
WPCPM_Sponsor_Offers::add_codes( $g1, "G-1\nG-2" );
WPCPM_Sponsor_Offers::set_state( $g1, 'live' );

echo "=== One press ===\n";
$first = WPCPM_Sponsor_Claims::claim( $g1, $GLOBALS['users'][20] );
ck( 'the first press takes the first code and records it on the person', array( $first['new'], $first['code'], $first['index'], WPCPM_Sponsor_Claims::claims_of( 20 )[ $g1 ]['i'] ), array( true, 'G-1', 0, 0 ) );

echo "\n=== The ordinary double submit (FOFFR-6) ===\n";
// Request B is already in flight: it read the person's claims before request A wrote one, so
// both its lookup at the top of claim() and may_claim_reason()'s has_claimed() see nothing and
// it reaches the lock. Only the lookup taken under the lock stands between it and a second code.
$GLOBALS['stale']       = 2;
$GLOBALS['stale_value'] = array();
$second = WPCPM_Sponsor_Claims::claim( $g1, $GLOBALS['users'][20] );
ck( 'the second press takes nothing and hands back the code the first one took', array( $second['new'], $second['code'], $second['index'] ), array( false, 'G-1', 0 ) );
ck( 'the pool and its ledger are where the first press left them', array( WPCPM_Sponsor_Codes::counts( $g1 ), count( WPCPM_Sponsor_Codes::claims( $g1 ) ) ), array( array( 'available' => 1, 'claimed' => 1, 'void' => 0, 'total' => 2 ), 1 ) );
ck( 'the person still holds exactly the one claim, at the index the first press wrote', array( array_keys( WPCPM_Sponsor_Claims::claims_of( 20 ) ), WPCPM_Sponsor_Claims::claims_of( 20 )[ $g1 ]['i'] ), array( array( $g1 ), 0 ) );
ck( 'and the lock the second press took is released', isset( $GLOBALS['opts'][ WPCPM_Sponsor_Codes::LOCK_PREFIX . $g1 ] ), false );
ck( 'the guard refuses that person and nobody else: the next claimant gets the next code', array_map( static function ( $v ) { return is_array( $v ) ? array( $v['new'], $v['code'] ) : $v; }, array( WPCPM_Sponsor_Claims::claim( $g1, $GLOBALS['users'][23] ) ) ), array( array( true, 'G-2' ) ) );

echo "\n=== House rules ===\n";
$claims_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-claims.php' );
ck( 'no em or en dash in the claims class', preg_match( '/\x{2013}|\x{2014}/u', $claims_src ), 0 );
ck( 'the second lookup is taken after the lock, not before it', strpos( $claims_src, 'WPCPM_Sponsor_Codes::lock( $offer[' ) < strrpos( $claims_src, "\$claims = self::claims_of( \$user->ID );" ), true );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
