<?php
/**
 * WPCPM_Sponsor_Policy and WPCPM_Sponsor_Roster: the one fence and the one claim.
 *
 * Run: php bin/test-sponsor-policy.php
 *
 * @package WPCreditsProgramManager
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

class WP_Error {
	public $code; public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
class WP_User {
	public $ID; public $display_name;
	public function __construct( $id, $name = 'Someone' ) { $this->ID = (int) $id; $this->display_name = $name; }
	public function exists() { return $this->ID > 0; }
}
class WP_Post { public $ID; public $post_type = 'post'; public function __construct( $id ) { $this->ID = (int) $id; } }

$GLOBALS['users'] = array( 1 => new WP_User( 1, 'Manager' ), 5 => new WP_User( 5, 'Rep A' ), 6 => new WP_User( 6, 'Rep B' ), 9 => new WP_User( 9, 'Nobody' ) );
function get_user_by( $f, $v ) { return isset( $GLOBALS['users'][ (int) $v ] ) ? $GLOBALS['users'][ (int) $v ] : false; }
function wp_get_current_user() { return isset( $GLOBALS['uid'] ) ? get_user_by( 'id', $GLOBALS['uid'] ) : new WP_User( 0 ); }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : ''; }
function get_post_meta( $id, $key, $single = false ) { return isset( $GLOBALS['pmeta'][ $id ][ $key ] ) ? $GLOBALS['pmeta'][ $id ][ $key ] : ''; }
function get_users( array $args ) { $out = array(); foreach ( $GLOBALS['users'] as $u ) { if ( isset( $args['meta_key'] ) && (string) get_user_meta( $u->ID, $args['meta_key'], true ) !== (string) $args['meta_value'] ) { continue; } $out[] = $u; } return $out; }
function __( $t ) { return $t; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return trim( (string) $t ); }

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
}
class WPCPM_Mentors_Sync { public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); } }
class WPCPM_Sponsor_Members {
	const META_ACTIVE = 'wpcpm_sponsor_active';
	public static function sponsor_of( $user = null ) { $user = WPCPM_Roles::resolve_user( $user ); return ( $user instanceof WP_User && isset( $GLOBALS['stamps'][ $user->ID ] ) ) ? $GLOBALS['stamps'][ $user->ID ] : ''; }
	public static function memberships_of( $user ) { $r = self::sponsor_of( $user ); return '' === $r ? array() : array( $r ); }
	public static function live_accounts() { return get_users( array( 'number' => -1, 'meta_key' => self::META_ACTIVE, 'meta_value' => 1 ) ); }
}
class WPCPM_Sponsors_Index {
	public static function rows() { return $GLOBALS['index']; }
	public static function has( $r ) { return isset( $GLOBALS['index'][ $r ] ); }
	public static function row( $r ) { return isset( $GLOBALS['index'][ $r ] ) ? $GLOBALS['index'][ $r ] : null; }
}
class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) { $n = isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0; if ( $n + $amount > $limit ) { return false; } $GLOBALS['buckets'][ $key ] = $n + $amount; return true; }
	public static function count( $key, $window ) { return isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0; }
	public static function key( ...$parts ) { return implode( ':', $parts ); }
}
class WPCPM_Institution_Audit {
	const GROUND_MEMBER = 'member'; const EVIDENCE_CACHE = 'cache';
	public static function record_sponsor( array $e ) { $GLOBALS['audit'][] = $e; return 1; }
}
class WPCPM_Request {
	public static function text( $name, $fallback = '' ) { return isset( $GLOBALS['get'][ $name ] ) ? trim( (string) $GLOBALS['get'][ $name ] ) : $fallback; }
	public static function posted_text( $name, $fallback = '' ) { return isset( $GLOBALS['post'][ $name ] ) ? trim( (string) $GLOBALS['post'][ $name ] ) : $fallback; }
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}
function allowed_as( $ground, $sponsor ) { return array( 'allowed' => true, 'ground' => $ground, 'sponsor' => $sponsor, 'why' => '' ); }
function refused_why( $why ) { return array( 'allowed' => false, 'ground' => '', 'sponsor' => '', 'why' => $why ); }

$A = 'recSPONSOR0000001';
$B = 'recSPONSOR0000002';
$GLOBALS['index']   = array( $A => array( 'record_id' => $A, 'name' => 'Sponsor A ' ), $B => array( 'record_id' => $B, 'name' => '' ) );
$GLOBALS['stamps']  = array( 5 => $A, 6 => $B );
$GLOBALS['umeta']   = array( 5 => array( 'wpcpm_sponsor_active' => 1 ), 6 => array( 'wpcpm_sponsor_active' => 1 ) );
$GLOBALS['manage']  = array( 1 );
$GLOBALS['buckets'] = array();
$GLOBALS['audit']   = array();
$P = 'WPCPM_Sponsor_Policy';
$M = $P::GROUND_MANAGER;
$m = $P::GROUND_MEMBER;

echo "=== The map, row by row ===\n";
$expected_map = array(
	$P::ACT_VIEW_DASHBOARD  => array( $M, $m ),
	$P::ACT_EDIT_PROFILE    => array( $M, $m ),
	$P::ACT_MANAGE_OFFERS   => array( $M, $m ),
	$P::ACT_VIEW_STATS      => array( $M, $m ),
	$P::ACT_VIEW_CLAIMANTS  => array( $M ),
	$P::ACT_WRITE_POSTS     => array( $M, $m ),
	$P::ACT_PUBLISH_POST    => array( $M ),
	$P::ACT_UPLOAD_LOGO     => array( $M, $m ),
	$P::ACT_AGREEMENT       => array( $M, $m ),
	$P::ACT_REVIEW_AGREEMENT => array( $M ),
	$P::ACT_EXPRESS_INTEREST => array( $M, $m ),
	$P::ACT_MANAGE_MEMBERS  => array( $M ),
	$P::ACT_PROVISION       => array( $M ),
);
ck( 'grounds() is exactly the spec\'s table, in order, the manager ground first on every row', $P::grounds(), $expected_map );
ck( 'ungated() is every action: the sponsor agreement is not a gate', $P::ungated(), array_keys( $expected_map ) );
$subject_a = $P::subject_sponsor( $A );
ck( 'subject_sponsor() carries the ID under sponsor_ids', $subject_a, array( 'type' => 'sponsor', 'sponsor_ids' => array( $A ) ) );
$GLOBALS['pmeta'][77]['_wpcpm_sponsor'] = $B;
ck( 'subject_post() reads the post\'s sponsor meta', $P::subject_post( new WP_Post( 77 ) ), array( 'type' => 'post', 'sponsor_ids' => array( $B ), 'post_id' => 77 ) );
foreach ( array_keys( $expected_map ) as $action ) {
	$member_ok = in_array( $m, $expected_map[ $action ], true );
	ck( "a manager passes $action on A, as a manager", $P::decide( $action, $subject_a, 1 ), allowed_as( $M, $A ) );
	ck( "a manager passes $action on a sponsor-less subject, naming no sponsor", $P::decide( $action, array( 'type' => 'sponsor', 'sponsor_ids' => array() ), 1 ), allowed_as( $M, '' ) );
	ck( $member_ok ? "a member of A passes $action on A, as a member" : "a member of A is refused $action with no ground", $P::decide( $action, $subject_a, 5 ), $member_ok ? allowed_as( $m, $A ) : refused_why( 'no-ground' ) );
	ck( "a member of B is refused $action on A", $P::decide( $action, $subject_a, 6 ), refused_why( 'no-ground' ) );
	ck( "an account with no membership is refused $action", $P::decide( $action, $subject_a, 9 ), refused_why( 'no-ground' ) );
}
$GLOBALS['stamps'][1] = $A;
ck( 'a manager who is also a member passes as a manager, the first ground', $P::decide( $P::ACT_VIEW_DASHBOARD, $subject_a, 1 ), allowed_as( $M, $A ) );
unset( $GLOBALS['stamps'][1] );
ck( 'an action not in the map fails closed', $P::decide( 'delete_everything', $subject_a, 1 ), refused_why( 'unknown-action' ) );
ck( 'no user is refused', $P::decide( $P::ACT_VIEW_DASHBOARD, $subject_a, 404 ), refused_why( 'no-user' ) );
ck( 'a malformed ID in the subject is filtered before any ground sees it', $P::decide( $P::ACT_VIEW_DASHBOARD, array( 'sponsor_ids' => array( 'garbage', $A ) ), 5 ), allowed_as( $m, $A ) );
$one = $P::refusal();
ck( 'the one refusal', array( $one->get_error_code(), $one->get_error_message() ), array( 'wpcpm_sponsor_unknown', 'That is not something your account can do here.' ) );

echo "\n=== The claim ===\n";
$R = 'WPCPM_Sponsor_Roster';
$got = $R::claim( 'anything', $P::ACT_VIEW_DASHBOARD, 5 );
ck( 'for a member the stamp wins and the argument is ignored', array( $got['record'], $got['decision']['ground'], $got['row']['name'] ), array( $A, $m, 'Sponsor A ' ) );
// A member whose stamp names a sponsor the index does not hold (left the index since the
// account was attached): sponsor_of() only checks the stamp's shape and the active flag,
// never the index, so the claim still allows on the stamp; row() answers null, and it is
// every caller's job to refuse on that rather than write back to nothing.
$GLOBALS['stamps'][6] = 'recNOTINDEXED0002';
$got                  = $R::claim( 'anything', $P::ACT_VIEW_DASHBOARD, 6 );
ck( 'a member stamped for a sponsor the index does not hold still claims, with row null', array( $got['record'], $got['decision']['allowed'], $got['row'] ), array( 'recNOTINDEXED0002', true, null ) );
$GLOBALS['stamps'][6] = $B;
$got = $R::claim( $B, $P::ACT_VIEW_DASHBOARD, 1 );
ck( 'a manager claims the sponsor the argument names', array( $got['record'], $got['decision']['ground'] ), array( $B, $M ) );
$got = $R::claim( 'recNOTINDEXED0001', $P::ACT_VIEW_DASHBOARD, 1 );
ck( 'a manager naming a sponsor the index does not hold is refused', is_wp_error( $got ) ? $got->get_error_code() : 'allowed', 'wpcpm_sponsor_unknown' );
ck( 'and not metered', $GLOBALS['buckets'], array() );
$got = $R::claim( $A, $P::ACT_PUBLISH_POST, 5 );
ck( 'a member asking for a manager\'s action is refused with the one message', is_wp_error( $got ) ? $got->get_error_message() : 'allowed', 'That is not something your account can do here.' );
ck( 'and metered in the sponsor scope', $GLOBALS['buckets']['sponsor-refused:5'], 1 );
for ( $i = 0; $i < 19; $i++ ) { $R::claim( $A, $P::ACT_PUBLISH_POST, 5 ); }
ck( 'the twentieth refusal locks the account and logs it once', array( $GLOBALS['buckets']['sponsor-refused:5'], count( $GLOBALS['audit'] ), $GLOBALS['audit'][0]['kind'], $GLOBALS['audit'][0]['sponsor'] ), array( 20, 1, 'claim_locked', $A ) );
$got = $R::claim( $A, $P::ACT_VIEW_DASHBOARD, 5 );
ck( 'a locked account is refused an action it would otherwise pass, uncounted', array( is_wp_error( $got ), $GLOBALS['buckets']['sponsor-refused:5'] ), array( true, 20 ) );
ck( 'locked_today() names it', array_map( function ( $u ) { return $u->ID; }, $R::locked_today() ), array( 5 ) );
$GLOBALS['buckets'] = array();

echo "\n=== Resolving the sponsor a page is about ===\n";
$GLOBALS['get'] = array( $R::ARG_VIEW => $B );
ck( 'a manager asking for B gets B', $R::resolve_sponsor( 1, true ), $B );
ck( 'a member asking for B gets their own sponsor', $R::resolve_sponsor( 5, false ), $A );
ck( 'a member whose caller lies about can_manage still gets their own', $R::resolve_sponsor( 5, true ), $A );
$GLOBALS['get'] = array( $R::ARG_VIEW => 'recNOTINDEXED0001' );
ck( 'a manager asking for an unknown sponsor gets the first with a live account', $R::resolve_sponsor( 1, true ), $A );
$GLOBALS['get'] = array();
ck( 'nobody with no membership gets nothing', $R::resolve_sponsor( 9, false ), '' );
ck( 'the switcher lists every index row, the nameless one by its ID', $R::switcher_options(), array( $A => 'Sponsor A', $B => $B ) );
$GLOBALS['post'] = array( $R::ARG_VIEW => $A );
ck( 'the view argument is read from POST as well, for the forms', $R::requested_view(), $A );
$GLOBALS['post'] = array();

echo "\n=== House rules ===\n";
$offenders = array();
foreach ( (array) glob( __DIR__ . '/../includes/modules/class-wpcpm-sponsor*.php' ) as $path ) {
	if ( 'class-wpcpm-sponsor-policy.php' === basename( $path ) ) { continue; }
	if ( preg_match( '/wpcpm_sponsor(_record_id)?[^;]*===/', (string) file_get_contents( $path ), $mm ) ) { $offenders[ basename( $path ) ] = substr( $mm[0], 0, 80 ); }
}
ck( 'no sponsor file but the policy compares a sponsor ID with ===', $offenders, array() );
ck( 'no em or en dash in the two classes', preg_match( '/\x{2013}|\x{2014}/u', file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php' ) . file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php' ) ), 0 );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', 13 * 5 + 28 );
exit( $fail ? 1 : 0 );
