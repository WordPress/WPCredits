<?php
/**
 * WPCPM_Sponsor_Members: the stamp, attach and detach, and each refusal in the spec's order.
 *
 * Run: php bin/test-sponsor-members.php
 *
 * @package WPCreditsProgramManager
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	public $code; public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }

class WP_User {
	public $ID; public $roles = array(); public $display_name; public $user_email;
	public function __construct( $id, $roles = array(), $name = 'Someone', $email = 'maciej@a8c.com' ) { $this->ID = (int) $id; $this->roles = (array) $roles; $this->display_name = $name; $this->user_email = $email; }
	public function exists() { return $this->ID > 0; }
	public function add_role( $r ) { if ( ! in_array( $r, $this->roles, true ) ) { $this->roles[] = $r; } }
	public function remove_role( $r ) { $this->roles = array_values( array_diff( $this->roles, array( $r ) ) ); }
	public function set_role( $r ) { $this->roles = array( $r ); }
}

$GLOBALS['users'] = array();
$GLOBALS['umeta'] = array();
$GLOBALS['audit'] = array();

function get_user_by( $field, $value ) { return ( 'id' === $field && isset( $GLOBALS['users'][ (int) $value ] ) ) ? $GLOBALS['users'][ (int) $value ] : false; }
function get_userdata( $id ) { return get_user_by( 'id', $id ); }
function wp_get_current_user() { return isset( $GLOBALS['uid'] ) ? get_user_by( 'id', $GLOBALS['uid'] ) : new WP_User( 0 ); }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['umeta'][ $id ][ $key ] = $value; return true; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['umeta'][ $id ][ $key ] ); return true; }
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
function __( $t ) { return $t; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function absint( $n ) { return abs( (int) $n ); }

class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student'; const ROLE_MENTOR = 'wpcpm_mentor'; const ROLE_INSTITUTION = 'wpcpm_institution'; const ROLE_SPONSOR = 'wpcpm_sponsor'; const ROLE_ADMIN = 'administrator'; const CAP_MANAGE = 'wpcpm_manage_program';
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
}
class WPCPM_Mentors_Sync { public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); } }
class WPCPM_Students_Sync { const META_RECORD_ID = 'wpcpm_student_record_id'; }
class WPCPM_Institution_Members {
	const META_RECORD_ID = 'wpcpm_institution_record_id'; const META_ACTIVE = 'wpcpm_institution_active';
	public static function institution_of( $user = null ) { $user = WPCPM_Roles::resolve_user( $user ); return ( $user instanceof WP_User && 1 === (int) get_user_meta( $user->ID, self::META_ACTIVE, true ) ) ? (string) get_user_meta( $user->ID, self::META_RECORD_ID, true ) : ''; }
}
class WPCPM_Sponsors_Index {
	public static function has( $record ) { return isset( $GLOBALS['index'][ $record ] ); }
	public static function row( $record ) { return isset( $GLOBALS['index'][ $record ] ) ? $GLOBALS['index'][ $record ] : null; }
}
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const GROUND_SYSTEM = 'system'; const EVIDENCE_INDEX = 'index';
	public static function record_sponsor( array $entry ) { $GLOBALS['audit'][] = $entry; return count( $GLOBALS['audit'] ); }
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}
function code( $r ) { return is_wp_error( $r ) ? $r->get_error_code() : $r; }

$S = 'recSPONSOR0000000';
$T = 'recSPONSOR0000001';
$GLOBALS['index']  = array( $S => array( 'record_id' => $S, 'name' => 'Sponsor One ', 'website' => 'https://one.test', 'product_type' => 'Plugin', 'status' => 'Approved' ), $T => array( 'record_id' => $T, 'name' => 'Sponsor Two', 'website' => '', 'product_type' => '', 'status' => 'Approved' ) );
$GLOBALS['manage'] = array( 1 );
$GLOBALS['users']  = array(
	1 => new WP_User( 1, array( 'administrator' ), 'Manager' ),
	5 => new WP_User( 5, array( 'subscriber' ), 'Rep One' ),
	6 => new WP_User( 6, array( 'wpcpm_student' ), 'Student' ),
	7 => new WP_User( 7, array( 'wpcpm_institution' ), 'Institution Rep' ),
	8 => new WP_User( 8, array( 'wpcpm_mentor' ), 'Mentor' ),
	9 => new WP_User( 9, array( 'subscriber' ), 'Rep Two' ),
);
$GLOBALS['umeta'][6][ WPCPM_Students_Sync::META_RECORD_ID ] = 'recSTUDENT0000000';
$GLOBALS['umeta'][7][ WPCPM_Institution_Members::META_RECORD_ID ] = 'recINSTITUTION000';
$GLOBALS['umeta'][7][ WPCPM_Institution_Members::META_ACTIVE ]    = 1;

echo "=== The refusals, in the spec's order ===\n";
ck( '1. a malformed record ID', code( WPCPM_Sponsor_Members::attach( 5, 'nope', 'manager', 1 ) ), 'wpcpm_sponsor_bad_record' );
ck( '2. a record not in the index', code( WPCPM_Sponsor_Members::attach( 5, 'recNOTINDEXED0000', 'manager', 1 ) ), 'wpcpm_sponsor_not_indexed' );
ck( '3. no such account', code( WPCPM_Sponsor_Members::attach( 404, $S, 'manager', 1 ) ), 'wpcpm_sponsor_no_account' );
ck( '4. an administrator', code( WPCPM_Sponsor_Members::attach( 1, $S, 'manager', 1 ) ), 'wpcpm_sponsor_is_admin' );
ck( '5. a student', code( WPCPM_Sponsor_Members::attach( 6, $S, 'manager', 1 ) ), 'wpcpm_sponsor_is_student' );
ck( '6. an institution representative', code( WPCPM_Sponsor_Members::attach( 7, $S, 'manager', 1 ) ), 'wpcpm_sponsor_is_institution' );
ck( 'a way that is not one', code( WPCPM_Sponsor_Members::attach( 5, $S, 'walked-in', 1 ) ), 'wpcpm_sponsor_bad_how' );

echo "\n=== Attaching ===\n";
ck( 'a plain account is attached', WPCPM_Sponsor_Members::attach( 5, $S, WPCPM_Sponsor_Members::HOW_MANAGER, 1 ), true );
ck( 'with the stamp, active', array( get_user_meta( 5, WPCPM_Sponsor_Members::META_RECORD_ID, true ), get_user_meta( 5, WPCPM_Sponsor_Members::META_ACTIVE, true ) ), array( $S, 1 ) );
ck( 'the membership facts', array_intersect_key( get_user_meta( 5, WPCPM_Sponsor_Members::META_MEMBERSHIP, true ), array( 'by' => 1, 'how' => 1 ) ), array( 'by' => 1, 'how' => 'manager' ) );
ck( 'the profile stamp, trimmed', get_user_meta( 5, WPCPM_Sponsor_Members::META_PROFILE, true ), array( 'name' => 'Sponsor One', 'website' => 'https://one.test', 'product_type' => 'Plugin', 'status' => 'Approved' ) );
ck( 'the sponsor role added, the first role kept', $GLOBALS['users'][5]->roles, array( 'subscriber', 'wpcpm_sponsor' ) );
ck( 'and one audit row on the sponsor key', array( $GLOBALS['audit'][0]['kind'], $GLOBALS['audit'][0]['sponsor'], $GLOBALS['audit'][0]['ground'] ), array( 'member_added', $S, 'manager' ) );
ck( '7. already a member here', code( WPCPM_Sponsor_Members::attach( 5, $S, 'manager', 1 ) ), 'wpcpm_sponsor_member_already' );
ck( '8. a member of another sponsor', code( WPCPM_Sponsor_Members::attach( 5, $T, 'manager', 1 ) ), 'wpcpm_sponsor_member_elsewhere' );
ck( 'a mentor may be attached: sponsored mentors are often the sponsor\'s own staff', WPCPM_Sponsor_Members::attach( 8, $S, WPCPM_Sponsor_Members::HOW_PROVISIONED, 0 ), true );
ck( 'and keeps the mentor role beside the new one', $GLOBALS['users'][8]->roles, array( 'wpcpm_mentor', 'wpcpm_sponsor' ) );
ck( 'the system ground when nobody pressed', $GLOBALS['audit'][1]['ground'], 'system' );

echo "\n=== Reading ===\n";
$GLOBALS['uid'] = 5;
ck( 'sponsor_of() reads the current user by default', WPCPM_Sponsor_Members::sponsor_of(), $S );
ck( 'and needs the active flag', ( function () { $GLOBALS['umeta'][5][ WPCPM_Sponsor_Members::META_ACTIVE ] = 0; $r = WPCPM_Sponsor_Members::sponsor_of( 5 ); $GLOBALS['umeta'][5][ WPCPM_Sponsor_Members::META_ACTIVE ] = 1; return $r; } )(), '' );
ck( 'memberships_of() is the one-element list', WPCPM_Sponsor_Members::memberships_of( 5 ), array( $S ) );
ck( 'is_member() for a record, and for any', array( WPCPM_Sponsor_Members::is_member( 5, $S ), WPCPM_Sponsor_Members::is_member( 5, $T ), WPCPM_Sponsor_Members::is_member( 5 ), WPCPM_Sponsor_Members::is_member( 9 ) ), array( true, false, true, false ) );
ck( 'members_of() lists the two live accounts', array_map( function ( $u ) { return $u->ID; }, WPCPM_Sponsor_Members::members_of( $S ) ), array( 5, 8 ) );
ck( 'and nobody for the other sponsor', WPCPM_Sponsor_Members::members_of( $T ), array() );
ck( 'live_accounts() lists the active accounts and not a detached one', array_map( function ( $u ) { return $u->ID; }, WPCPM_Sponsor_Members::live_accounts() ), array( 5, 8 ) );

echo "\n=== Detaching ===\n";
ck( 'a reason that is not one', code( WPCPM_Sponsor_Members::detach( 5, 'bored', 1 ) ), 'wpcpm_sponsor_bad_reason' );
ck( 'an account that is no member', code( WPCPM_Sponsor_Members::detach( 9, 'removed', 1 ) ), 'wpcpm_sponsor_member_none' );
ck( 'detach records the reason and the history', WPCPM_Sponsor_Members::detach( 5, WPCPM_Sponsor_Members::REASON_REMOVED, 1 ), true );
ck( 'the stamp moved to _was, active is 0', array( isset( $GLOBALS['umeta'][5][ WPCPM_Sponsor_Members::META_RECORD_ID ] ), get_user_meta( 5, WPCPM_Sponsor_Members::META_RECORD_ID_WAS, true ), get_user_meta( 5, WPCPM_Sponsor_Members::META_ACTIVE, true ) ), array( false, $S, 0 ) );
ck( 'the sponsor role removed, subscriber left', $GLOBALS['users'][5]->roles, array( 'subscriber' ) );
ck( 'the audit row names the reason', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data']['reason'] ), array( 'member_removed', 'removed' ) );
ck( 'former_members_of() finds the account', array_map( function ( $u ) { return $u->ID; }, WPCPM_Sponsor_Members::former_members_of( $S ) ), array( 5 ) );
ck( 'a former member comes back to the same sponsor, and the history is cleared', WPCPM_Sponsor_Members::attach( 5, $S, 'manager', 1 ) === true && ! isset( $GLOBALS['umeta'][5][ WPCPM_Sponsor_Members::META_RECORD_ID_WAS ] ), true );
$GLOBALS['users'][8]->roles = array( 'wpcpm_mentor', 'wpcpm_sponsor' );
ck( 'detaching a mentor leaves the mentor role', WPCPM_Sponsor_Members::detach( 8, WPCPM_Sponsor_Members::REASON_REVOKED, 0 ) === true ? $GLOBALS['users'][8]->roles : null, array( 'wpcpm_mentor' ) );
$only = new WP_User( 10, array( 'wpcpm_sponsor' ), 'Only Sponsor' );
$GLOBALS['users'][10] = $only;
WPCPM_Sponsor_Members::attach( 10, $T, 'manager', 1 );
WPCPM_Sponsor_Members::detach( 10, 'removed', 1 );
ck( 'an account with no other role falls back to subscriber, never to nothing', $only->roles, array( 'subscriber' ) );

echo "\n=== House rules ===\n";
$src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php' );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'nothing here calls wp_insert_user()', strpos( $src, 'wp_insert_user(' ), false );
ck( 'and nothing compares a sponsor ID with === outside the policy', preg_match( '/wpcpm_sponsor(_record_id)?[^;]*===/', $src ), 0 );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', 38 );
exit( $fail ? 1 : 0 );
