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
// The real class (class-wpcpm-sponsor-members.php), not a hand copy of its own three
// questions: a hand copy can drift from what sponsor_of() actually reads, which is exactly
// the shape of bug FSUIT-7's own refusal check exists to catch. Nothing more is needed for
// it: this suite only ever calls sponsor_of(), memberships_of() and live_accounts(), never
// attach(), detach() or maybe_repair_detached() (named only in the grep checks below), so
// none of their own collaborators - WPCPM_Sponsor_Posts, absint(), the meta writers - run.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
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

$fail   = 0;
$checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}
function allowed_as( $ground, $sponsor ) { return array( 'allowed' => true, 'ground' => $ground, 'sponsor' => $sponsor, 'why' => '' ); }
function refused_why( $why ) { return array( 'allowed' => false, 'ground' => '', 'sponsor' => '', 'why' => $why ); }
/**
 * One method's source, from its `function` line to the docblock of the next one: enough to ask
 * which of them names a meta key, and no PHP parser needed for the question.
 *
 * @param string $src  The whole file.
 * @param string $name The method.
 * @return string
 */
function body_of( $src, $name ) {
	$start = strpos( $src, 'function ' . $name . '(' );
	if ( false === $start ) { return ''; }
	$end = strpos( $src, "\n\t/**", $start );
	return false === $end ? substr( $src, $start ) : substr( $src, $start, $end - $start );
}

$A = 'recSPONSOR0000001';
$B = 'recSPONSOR0000002';
$GLOBALS['index']   = array( $A => array( 'record_id' => $A, 'name' => 'Sponsor A ' ), $B => array( 'record_id' => $B, 'name' => '' ) );
$GLOBALS['umeta']   = array(
	5 => array( 'wpcpm_sponsor_record_id' => $A, 'wpcpm_sponsor_active' => 1 ),
	6 => array( 'wpcpm_sponsor_record_id' => $B, 'wpcpm_sponsor_active' => 1 ),
	// FSUIT-7: detached from A, so the history column holds A and the live stamp is gone. Every
	// refusal this account collects below is the meaning of "history, no power".
	8 => array( 'wpcpm_sponsor_record_id_was' => $A, 'wpcpm_sponsor_active' => 0 ),
);
$GLOBALS['users'][8] = new WP_User( 8, 'Former Rep' );
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
$GLOBALS['umeta'][1] = array( 'wpcpm_sponsor_record_id' => $A, 'wpcpm_sponsor_active' => 1 );
ck( 'a manager who is also a member passes as a manager, the first ground', $P::decide( $P::ACT_VIEW_DASHBOARD, $subject_a, 1 ), allowed_as( $M, $A ) );
unset( $GLOBALS['umeta'][1] );
ck( 'an action not in the map fails closed', $P::decide( 'delete_everything', $subject_a, 1 ), refused_why( 'unknown-action' ) );
ck( 'no user is refused', $P::decide( $P::ACT_VIEW_DASHBOARD, $subject_a, 404 ), refused_why( 'no-user' ) );
ck( 'a malformed ID in the subject is filtered before any ground sees it', $P::decide( $P::ACT_VIEW_DASHBOARD, array( 'sponsor_ids' => array( 'garbage', $A ) ), 5 ), allowed_as( $m, $A ) );
$one = $P::refusal();
ck( 'the one refusal', array( $one->get_error_code(), $one->get_error_message() ), array( 'wpcpm_sponsor_unknown', 'That is not something your account can do here.' ) );
$detached = array();
foreach ( array_keys( $expected_map ) as $action ) {
	$detached[ $action ] = $P::decide( $action, $subject_a, 8 )['allowed'];
}
ck( 'a detached account, its history naming A, is refused every action on A', $detached, array_fill_keys( array_keys( $expected_map ), false ) );

echo "\n=== The history column grants nothing (FSUIT-7) ===\n";
// The mutation that stayed green: a `_was` fallback in sponsor_of(), a plausible edit because
// former_members_of() reads the same column two methods below, would hand every account ever
// detached its Sponsor Dashboard, its offers and its codes back.
$members_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php' );
$readers     = array();
foreach ( array( 'sponsor_of', 'memberships_of', 'is_member', 'members_of', 'live_accounts' ) as $method ) {
	if ( false !== strpos( body_of( $members_src, $method ), 'META_RECORD_ID_WAS' ) ) { $readers[] = $method; }
}
ck( 'no method that answers who a member is names the history column', $readers, array() );
preg_match_all( '/\t(?:public|private) static function ([a-z_]+)\(/', $members_src, $names );
$naming = array();
foreach ( $names[1] as $method ) {
	if ( false !== strpos( body_of( $members_src, $method ), 'META_RECORD_ID_WAS' ) ) { $naming[] = $method; }
}
ck( 'and exactly four name it: the lookup that promises it, the two that move it, and the repair', $naming, array( 'former_members_of', 'attach', 'detach', 'maybe_repair_detached' ) );
$elsewhere = array();
foreach ( (array) glob( __DIR__ . '/../includes/{,*/,*/*/}*.php', GLOB_BRACE ) as $path ) {
	if ( false !== strpos( (string) file_get_contents( $path ), 'WPCPM_Sponsor_Members::META_RECORD_ID_WAS' ) ) { $elsewhere[] = basename( $path ); }
}
ck( 'and no other class names it at all but the module\'s uninstall list', $elsewhere, array( 'class-wpcpm-sponsors.php' ) );

echo "\n=== The claim ===\n";
$R = 'WPCPM_Sponsor_Roster';
$got = $R::claim( 'anything', $P::ACT_VIEW_DASHBOARD, 5 );
ck( 'for a member the stamp wins and the argument is ignored', array( $got['record'], $got['decision']['ground'], $got['row']['name'] ), array( $A, $m, 'Sponsor A ' ) );
// A member whose stamp names a sponsor the index does not hold (left the index since the
// account was attached): sponsor_of() only checks the stamp's shape and the active flag,
// never the index, so the claim still allows on the stamp; row() answers null, and it is
// every caller's job to refuse on that rather than write back to nothing.
$GLOBALS['umeta'][6]['wpcpm_sponsor_record_id'] = 'recNOTINDEXED0002';
$got                                            = $R::claim( 'anything', $P::ACT_VIEW_DASHBOARD, 6 );
ck( 'a member stamped for a sponsor the index does not hold still claims, with row null', array( $got['record'], $got['decision']['allowed'], $got['row'] ), array( 'recNOTINDEXED0002', true, null ) );
$GLOBALS['umeta'][6]['wpcpm_sponsor_record_id'] = $B;
$got                                            = $R::claim( $B, $P::ACT_VIEW_DASHBOARD, 1 );
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

echo "\n=== One account that both manages and represents (FSPON-2) ===\n";
// A rep attached to A and promoted to administrator afterwards holds CAP_MANAGE and a live
// stamp at once. The claim used to read the stamp before anything else, so the switcher drew
// B and every save landed on A. The page and the claim answer the same question, in the same
// order, or the person cannot see where their writing goes.
$GLOBALS['users'][7] = new WP_User( 7, 'Manager and rep' );
$GLOBALS['umeta'][7] = array( 'wpcpm_sponsor_record_id' => $A, 'wpcpm_sponsor_active' => 1 );
$GLOBALS['manage'][] = 7;
$GLOBALS['get']      = array( $R::ARG_VIEW => $B );
ck( 'the page such an account sees is the sponsor the switcher names', $R::resolve_sponsor( 7, true ), $B );
$got = $R::claim( $B, $P::ACT_EDIT_PROFILE, 7 );
ck( 'and the save lands on that same sponsor, on the manager ground', is_wp_error( $got ) ? $got->get_error_code() : array( $got['record'], $got['decision']['ground'] ), array( $B, $M ) );
$got = $R::claim( '', $P::ACT_EDIT_PROFILE, 7 );
ck( 'a request naming no sponsor falls back to the stamp', is_wp_error( $got ) ? $got->get_error_code() : $got['record'], $A );
$got = $R::claim( 'recNOTINDEXED0003', $P::ACT_EDIT_PROFILE, 7 );
ck( 'and one the index does not hold is refused, never swapped for the stamp', is_wp_error( $got ) ? $got->get_error_code() : 'allowed', 'wpcpm_sponsor_unknown' );
ck( 'a plain member is still held to the stamp, whatever the request names', $R::claim( $B, $P::ACT_EDIT_PROFILE, 5 )['record'], $A );
$GLOBALS['get'] = array();

echo "\n=== House rules ===\n";
$offenders = array();
foreach ( (array) glob( __DIR__ . '/../includes/modules/class-wpcpm-sponsor*.php' ) as $path ) {
	if ( 'class-wpcpm-sponsor-policy.php' === basename( $path ) ) { continue; }
	if ( preg_match( '/wpcpm_sponsor(_record_id)?[^;]*===/', (string) file_get_contents( $path ), $mm ) ) { $offenders[ basename( $path ) ] = substr( $mm[0], 0, 80 ); }
}
ck( 'no sponsor file but the policy compares a sponsor ID with ===', $offenders, array() );
ck( 'no em or en dash in the two classes', preg_match( '/\x{2013}|\x{2014}/u', file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php' ) . file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php' ) ), 0 );

// Counted by ck() itself: a literal here is arithmetic nobody maintains.
printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', (int) $checks );
exit( $fail ? 1 : 0 );
