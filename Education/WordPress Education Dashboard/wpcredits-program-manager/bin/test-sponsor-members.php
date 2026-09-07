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

/**
 * WP_User on core's own semantics (wp-includes/class-wp-user.php), because FSPON-1 is a bug
 * only a faithful capability store can show: the array is read from the `wp_capabilities` meta
 * once when the object is built, `get_user_by()` builds a fresh object every call, and
 * `add_cap()`, `remove_cap()`, `add_role()`, `remove_role()` and `set_role()` each write that
 * whole array back. The stand-in this suite carried until 1.99.0 held no capability array at
 * all and moved only `$this->roles`, so a `detach()` that handed three posting capabilities
 * back passed all thirty-nine checks.
 */
class WP_User {
	public $ID = 0; public $caps = array(); public $roles = array(); public $display_name = ''; public $user_email = '';
	public $cap_key = 'wp_capabilities';
	public function __construct( $id = 0 ) {
		$this->ID           = (int) $id;
		$row                = isset( $GLOBALS['users'][ $this->ID ] ) ? $GLOBALS['users'][ $this->ID ] : array();
		$this->display_name = isset( $row['display_name'] ) ? $row['display_name'] : '';
		$this->user_email   = isset( $row['user_email'] ) ? $row['user_email'] : '';
		$caps               = get_user_meta( $this->ID, $this->cap_key, true );
		$this->caps         = is_array( $caps ) ? $caps : array();
		$this->read_roles();
	}
	public function exists() { return $this->ID > 0 && isset( $GLOBALS['users'][ $this->ID ] ); }
	public function add_cap( $cap, $grant = true ) { $this->caps[ $cap ] = (bool) $grant; $this->store(); }
	public function remove_cap( $cap ) { if ( ! isset( $this->caps[ $cap ] ) ) { return; } unset( $this->caps[ $cap ] ); $this->store(); }
	public function add_role( $role ) { if ( '' === (string) $role ) { return; } $this->caps[ $role ] = true; $this->store(); }
	public function remove_role( $role ) { if ( ! in_array( $role, $this->roles, true ) ) { return; } unset( $this->caps[ $role ] ); $this->store(); }
	public function set_role( $role ) {
		if ( 1 === count( $this->roles ) && current( $this->roles ) === $role ) { return; }
		foreach ( $this->roles as $old ) { unset( $this->caps[ $old ] ); }
		$this->caps[ $role ] = true;
		$this->store();
	}
	private function store() { update_user_meta( $this->ID, $this->cap_key, $this->caps ); $this->read_roles(); }
	private function read_roles() { $this->roles = array_values( array_filter( array_keys( $this->caps ), function ( $key ) { return in_array( $key, $GLOBALS['known_roles'], true ); } ) ); }
}

// `users` holds the account records get_user_by() builds an object from; the roles and the
// capabilities live in the meta store beside every other stamp, which is where WordPress
// itself keeps them.
$GLOBALS['users']       = array();
$GLOBALS['umeta']       = array();
$GLOBALS['audit']       = array();
$GLOBALS['opts']        = array();
$GLOBALS['known_roles'] = array( 'administrator', 'subscriber', 'wpcpm_sponsor', 'wpcpm_student', 'wpcpm_mentor', 'wpcpm_institution' );

// A NEW object every call, as core's own get_user_by() does it: two objects for one account is
// the shape FSPON-1 turns on.
function get_user_by( $field, $value ) { return ( 'id' === $field && isset( $GLOBALS['users'][ (int) $value ] ) ) ? new WP_User( (int) $value ) : false; }
function get_userdata( $id ) { return get_user_by( 'id', $id ); }
function wp_get_current_user() { $user = isset( $GLOBALS['uid'] ) ? get_user_by( 'id', $GLOBALS['uid'] ) : false; return $user instanceof WP_User ? $user : new WP_User( 0 ); }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['umeta'][ $id ][ $key ] = $value; return true; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['umeta'][ $id ][ $key ] ); return true; }
function get_option( $key, $fallback = false ) { return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $fallback; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['opts'][ $key ] = $value; return true; }
function get_users( array $args ) {
	$out = array();
	foreach ( array_keys( $GLOBALS['users'] ) as $id ) {
		if ( isset( $args['meta_key'] ) ) {
			$have = get_user_meta( $id, $args['meta_key'], true );
			if ( isset( $args['meta_value'] ) ) {
				if ( (string) $have !== (string) $args['meta_value'] ) { continue; }
			} elseif ( '' === (string) $have ) { continue; }
		}
		$user = new WP_User( $id );
		if ( isset( $args['role'] ) && ! in_array( $args['role'], $user->roles, true ) ) { continue; }
		$out[] = $user;
	}
	return $out;
}

/**
 * Put an account in the fixture: the record `get_user_by()` builds from, and its roles, which
 * go into the capability array because that is the only place WordPress keeps them.
 *
 * @param int      $id    The account.
 * @param string[] $roles Its roles.
 * @param string   $name  Display name.
 */
function person( $id, array $roles, $name ) {
	$GLOBALS['users'][ (int) $id ] = array( 'display_name' => $name, 'user_email' => 'maciej@a8c.com' );
	$caps                          = array();

	foreach ( $roles as $role ) {
		$caps[ $role ] = true;
	}

	update_user_meta( (int) $id, 'wp_capabilities', $caps );
}

/**
 * The roles the store holds for an account, read the way a fresh WP_User reads them.
 *
 * @param int $id The account.
 * @return string[]
 */
function roles_of( $id ) { $user = get_user_by( 'id', $id ); return $user instanceof WP_User ? $user->roles : array(); }

/**
 * Everything the stored capability array holds, roles and capabilities alike, in order. The
 * detach checks read this and not `roles_of()`: a leftover `edit_posts` is invisible in the
 * roles and is the whole of FSPON-1.
 *
 * @param int $id The account.
 * @return string[]
 */
function caps_of( $id ) { $caps = get_user_meta( (int) $id, 'wp_capabilities', true ); return is_array( $caps ) ? array_keys( $caps ) : array(); }
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

$fail   = 0;
$checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}
function code( $r ) { return is_wp_error( $r ) ? $r->get_error_code() : $r; }

$S = 'recSPONSOR0000000';
$T = 'recSPONSOR0000001';
$GLOBALS['index']  = array( $S => array( 'record_id' => $S, 'name' => 'Sponsor One ', 'website' => 'https://one.test', 'product_type' => 'Plugin', 'status' => 'Approved' ), $T => array( 'record_id' => $T, 'name' => 'Sponsor Two', 'website' => '', 'product_type' => '', 'status' => 'Approved' ) );
$GLOBALS['manage'] = array( 1 );
person( 1, array( 'administrator' ), 'Manager' );
person( 5, array( 'subscriber' ), 'Rep One' );
person( 6, array( 'wpcpm_student' ), 'Student' );
person( 7, array( 'wpcpm_institution' ), 'Institution Rep' );
person( 8, array( 'wpcpm_mentor' ), 'Mentor' );
person( 9, array( 'subscriber' ), 'Rep Two' );
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
ck( 'the sponsor role added, the first role kept', roles_of( 5 ), array( 'subscriber', 'wpcpm_sponsor' ) );
ck( 'and one audit row on the sponsor key', array( $GLOBALS['audit'][0]['kind'], $GLOBALS['audit'][0]['sponsor'], $GLOBALS['audit'][0]['ground'] ), array( 'member_added', $S, 'manager' ) );
ck( '7. already a member here', code( WPCPM_Sponsor_Members::attach( 5, $S, 'manager', 1 ) ), 'wpcpm_sponsor_member_already' );
ck( '8. a member of another sponsor', code( WPCPM_Sponsor_Members::attach( 5, $T, 'manager', 1 ) ), 'wpcpm_sponsor_member_elsewhere' );
ck( 'a mentor may be attached: sponsored mentors are often the sponsor\'s own staff', WPCPM_Sponsor_Members::attach( 8, $S, WPCPM_Sponsor_Members::HOW_PROVISIONED, 0 ), true );
ck( 'and keeps the mentor role beside the new one', roles_of( 8 ), array( 'wpcpm_mentor', 'wpcpm_sponsor' ) );
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
ck( 'the sponsor role removed, subscriber left', roles_of( 5 ), array( 'subscriber' ) );
// FSPON-1: detach() used to load its WP_User before drop_caps() stripped the three posting
// capabilities through a second object, so remove_role() wrote the first object's stale array
// back whole and handed all three back. Nothing on the site would have taken them off again.
ck( 'and no posting capability survives the removal', caps_of( 5 ), array( 'subscriber' ) );
// FSUIT-7: the class header calls the _was column history, no power. This is the consequence
// the suite asserted the meta state of but never the meaning of.
ck( 'and the history carries no power', array( WPCPM_Sponsor_Members::sponsor_of( 5 ), WPCPM_Sponsor_Members::memberships_of( 5 ) ), array( '', array() ) );
ck( 'the audit row names the reason', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data']['reason'] ), array( 'member_removed', 'removed' ) );
ck( 'former_members_of() finds the account', array_map( function ( $u ) { return $u->ID; }, WPCPM_Sponsor_Members::former_members_of( $S ) ), array( 5 ) );
ck( 'a former member comes back to the same sponsor, and the history is cleared', WPCPM_Sponsor_Members::attach( 5, $S, 'manager', 1 ) === true && ! isset( $GLOBALS['umeta'][5][ WPCPM_Sponsor_Members::META_RECORD_ID_WAS ] ), true );
ck( 'detaching a mentor leaves the mentor role', WPCPM_Sponsor_Members::detach( 8, WPCPM_Sponsor_Members::REASON_REVOKED, 0 ) === true ? roles_of( 8 ) : null, array( 'wpcpm_mentor' ) );
person( 10, array( 'wpcpm_sponsor' ), 'Only Sponsor' );
WPCPM_Sponsor_Members::attach( 10, $T, 'manager', 1 );
WPCPM_Sponsor_Members::detach( 10, 'removed', 1 );
ck( 'an account with no other role falls back to subscriber, never to nothing', roles_of( 10 ), array( 'subscriber' ) );
// The second shape FSPON-1 leaks through: set_role( 'subscriber' ) writes the same stale array.
ck( 'and that account keeps no posting capability either', caps_of( 10 ), array( 'subscriber' ) );

echo "\n=== The posting flag travels with membership (Phase S3) ===\n";
/**
 * The flag's real effect, not only that it was asked for: both methods move the three
 * capabilities through their own WP_User exactly as
 * includes/modules/class-wpcpm-sponsor-posts.php does, because that second object is half of
 * FSPON-1. The flag itself is on, which is the shipped default (`flags()`).
 */
class WPCPM_Sponsor_Posts {
	public static $calls = array();
	public static function caps() { return array( 'edit_posts', 'delete_posts', 'upload_files' ); }
	public static function apply_caps( $user_id, $record ) {
		self::$calls[] = array( 'apply', (int) $user_id, $record );
		$user          = get_user_by( 'id', (int) $user_id );

		if ( $user instanceof WP_User ) {
			foreach ( self::caps() as $cap ) { $user->add_cap( $cap ); }
		}
	}
	public static function drop_caps( $user_id ) {
		self::$calls[] = array( 'drop', (int) $user_id );
		$user          = get_user_by( 'id', (int) $user_id );

		if ( $user instanceof WP_User ) {
			foreach ( self::caps() as $cap ) { $user->remove_cap( $cap ); }
		}
	}
}
// An unconditional class is compiled before line one runs, so this stub's apply_caps()/drop_caps()
// already caught every earlier attach()/detach() call above; cleared here so the check below sees
// only what this block itself does.
WPCPM_Sponsor_Posts::$calls = array();
person( 12, array(), 'Poster' );
WPCPM_Sponsor_Members::attach( 12, $T, 'manager', 1 );
WPCPM_Sponsor_Members::detach( 12, 'removed', 1 );
ck( 'attach applies the flag and detach drops the caps', WPCPM_Sponsor_Posts::$calls, array( array( 'apply', 12, $T ), array( 'drop', 12 ) ) );

echo "\n=== The one-time repair of the accounts an older detach left holding capabilities ===\n";
// Two detached accounts, one still holding the three capabilities and one already clean, the
// mentor detached above (whose leftover edit_posts the widened repair now also strips, since
// no plugin role can have granted it), and a detached administrator who legitimately holds the
// three and must stay untouched.
person( 20, array( 'subscriber' ), 'Left Holding' );
person( 21, array( 'subscriber' ), 'Left Clean' );
person( 22, array( 'administrator' ), 'Admin Was Rep' );
foreach ( array( 20, 21, 22 ) as $left ) {
	$GLOBALS['umeta'][ $left ][ WPCPM_Sponsor_Members::META_RECORD_ID_WAS ] = $S;
	$GLOBALS['umeta'][ $left ][ WPCPM_Sponsor_Members::META_ACTIVE ]        = 0;
}
foreach ( WPCPM_Sponsor_Posts::caps() as $cap ) { get_user_by( 'id', 20 )->add_cap( $cap ); }
foreach ( WPCPM_Sponsor_Posts::caps() as $cap ) { get_user_by( 'id', 22 )->add_cap( $cap ); }
get_user_by( 'id', 8 )->add_cap( 'edit_posts' );
$GLOBALS['audit'] = array();
WPCPM_Sponsor_Members::maybe_repair_detached();
ck( 'the account left holding them is clean', caps_of( 20 ), array( 'subscriber' ) );
ck( 'the one that was already clean is untouched', caps_of( 21 ), array( 'subscriber' ) );
ck( 'a detached mentor is repaired too: the role survives, the capability does not', caps_of( 8 ), array( 'wpcpm_mentor' ) );
ck( 'a detached administrator is never repaired: the three are legitimately theirs', caps_of( 22 ), array( 'administrator', 'edit_posts', 'delete_posts', 'upload_files' ) );
ck( 'a live member is never repaired', caps_of( 5 ), array( 'subscriber', 'wpcpm_sponsor', 'edit_posts', 'delete_posts', 'upload_files' ) );
// A subject-to-how map, not $GLOBALS['audit'][0]: the row order follows get_users(), which
// puts account 8 before account 20, so a fixed index would pin an accident of fixture order.
$rows = array();
foreach ( $GLOBALS['audit'] as $entry ) {
	$rows[ $entry['subject'] ] = array( $entry['kind'], $entry['sponsor'], $entry['ground'], $entry['data']['how'] );
}
ck(
	'two audit rows, one for the mentor and one for the plain account',
	$rows,
	array(
		'8'  => array( 'member_removed', $S, 'system', 'caps-repaired' ),
		'20' => array( 'member_removed', $S, 'system', 'caps-repaired' ),
	)
);
ck( 'and the flag is set', get_option( WPCPM_Sponsor_Members::OPT_CAPS_REPAIRED ), 1 );
foreach ( WPCPM_Sponsor_Posts::caps() as $cap ) { get_user_by( 'id', 20 )->add_cap( $cap ); }
$GLOBALS['audit'] = array();
WPCPM_Sponsor_Members::maybe_repair_detached();
ck( 'a second call does nothing at all: the repair runs once per site', array( caps_of( 20 ), $GLOBALS['audit'] ), array( array( 'subscriber', 'edit_posts', 'delete_posts', 'upload_files' ), array() ) );

echo "\n=== House rules ===\n";
$src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php' );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'nothing here calls wp_insert_user()', strpos( $src, 'wp_insert_user(' ), false );
ck( 'and nothing compares a sponsor ID with === outside the policy', preg_match( '/wpcpm_sponsor(_record_id)?[^;]*===/', $src ), 0 );

// Counted by ck() itself: a literal here read 39 while the checks moved under it, and a count
// nobody maintains is a count nobody should trust.
printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', (int) $checks );
exit( $fail ? 1 : 0 );
