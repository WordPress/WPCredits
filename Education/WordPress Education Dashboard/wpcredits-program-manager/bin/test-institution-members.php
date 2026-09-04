<?php
/**
 * The membership stamp and the audit log behind it.
 *
 * Every assertion here stands for a way the fence could be wrong without anything on
 * screen saying so: an empty stamp matching every empty institution, a revoked member
 * still passing because only one of two flags was cleared, a manager stamped as a member
 * so the log could not say on which ground they acted, an administrator demoted to
 * Subscriber by a role change meant for someone else, a query for `recABC` returning
 * `recabc` under the database collation. And the one structural rule: nobody but
 * `WPCPM_Institution_Members` writes `wpcpm_institution_record_id`.
 *
 * Run from the plugin root:  php bin/test-institution-members.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']     = array( 'date_format' => 'Y-m-d' );
$GLOBALS['umeta']    = array();
$GLOBALS['users']    = array();
$GLOBALS['posts']    = array();
$GLOBALS['pmeta']    = array();
$GLOBALS['uid']      = 0;
$GLOBALS['manage']   = array();
$GLOBALS['index']    = array();
$GLOBALS['notified'] = array();
$GLOBALS['queries']  = 0;
$GLOBALS['set_role'] = array();
$GLOBALS['add_role'] = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
/**
 * Enough of `WP_User` to tell `add_role()` from `set_role()`.
 *
 * `set_role()` replaces every role, which is what an institution attach must never do to
 * a mentor or an editor. Both are recorded so the suite can assert which one ran.
 */
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = strtolower( str_replace( ' ', '', $name ) ); $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
	public function add_role( $r ) {
		$GLOBALS['add_role'][] = array( $this->ID, $r );
		if ( ! in_array( $r, $this->roles, true ) ) { $this->roles[] = $r; }
	}
	public function remove_role( $r ) { $this->roles = array_values( array_diff( $this->roles, array( $r ) ) ); }
	public function set_role( $r ) { $GLOBALS['set_role'][] = array( $this->ID, $r ); $this->roles = '' === $r ? array() : array( $r ); }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_content = '', $post_type = '', $post_status = 'publish', $post_author = 0, $post_date_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_text_field( $s ) { return trim( str_replace( array( "\r", "\n" ), '', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function absint( $v ) { return abs( (int) $v ); }
function add_action() {} function add_filter() {} function register_post_type() {}
function wp_specialchars_decode( $s, $q = null ) { return html_entity_decode( (string) $s, ENT_QUOTES ); }
function get_bloginfo( $k = 'name' ) { return 'WordPress Education Dashboard'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
require_once __DIR__ . '/stubs/caps.php';
/**
 * `get_users()` by meta key and value, matching the way MySQL does.
 *
 * The site's tables collate case-insensitively, so a query for `recABC` also returns the
 * account stamped `recabc`. Faked faithfully, because the class under test claims to
 * catch exactly that, and a stub that compared strictly would make the claim untestable.
 */
function get_users( $a = array() ) {
	++$GLOBALS['queries'];
	$out = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		$value = $GLOBALS['umeta'][ (int) $id ][ $a['meta_key'] ?? '' ] ?? null;
		if ( null !== $value && 0 === strcasecmp( (string) $value, (string) ( $a['meta_value'] ?? '' ) ) ) {
			$out[] = $user;
		}
	}
	return $out;
}
function wp_insert_post( $a, $error = false ) {
	static $next = 500;
	$post                = new WP_Post();
	$post->ID            = ++$next;
	$post->post_title    = $a['post_title'] ?? '';
	$post->post_content  = $a['post_content'] ?? '';
	$post->post_type     = $a['post_type'] ?? 'post';
	$post->post_status   = $a['post_status'] ?? 'publish';
	$post->post_author   = (int) ( $a['post_author'] ?? 0 );
	$post->post_date_gmt = gmdate( 'Y-m-d H:i:s', 1700000000 + $post->ID );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post_time( $f, $gmt = false, $post = null ) { return strtotime( $post->post_date_gmt . ' UTC' ); }
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	return $single ? ( $rows ? $rows[0] : '' ) : $rows;
}
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
/**
 * `get_posts()` by type and one meta clause, newest first, honouring `numberposts` and
 * `fields => ids`. Posts are numbered in insertion order, which is the only "date" the
 * harness has.
 */
function get_posts( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ( $a['post_type'] ?? '' ) !== $post->post_type ) { continue; }
		if ( ! empty( $a['meta_query'] ) ) {
			$clause = $a['meta_query'][0];
			$value  = $GLOBALS['pmeta'][ $post->ID ][ $clause['key'] ][0] ?? null;
			if ( null === $value || 0 !== strcasecmp( (string) $value, (string) $clause['value'] ) ) { continue; }
		}
		$out[] = $post;
	}
	if ( 'DESC' === ( $a['order'] ?? 'DESC' ) ) { $out = array_reverse( $out ); }
	$n = (int) ( $a['numberposts'] ?? 5 );
	if ( $n > 0 ) { $out = array_slice( $out, 0, $n ); }
	if ( 'ids' === ( $a['fields'] ?? '' ) ) { $out = array_map( function ( $p ) { return $p->ID; }, $out ); }
	return $out;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';

/* ---- the other pieces, to their contracts ------------------------------- */

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $v ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $v ) ); }
	}
}
if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	class WPCPM_Students_Sync {
		const META_RECORD_ID   = 'wpcpm_student_record_id';
		const META_INSTITUTION = 'wpcpm_student_institution';
	}
}
if ( ! class_exists( 'WPCPM_Institutions_Index' ) ) {
	class WPCPM_Institutions_Index {
		public static function has( $r ) { return isset( $GLOBALS['index'][ $r ] ); }
		public static function row( $r ) { return $GLOBALS['index'][ $r ] ?? null; }
	}
}
if ( ! class_exists( 'WPCPM_Institutions' ) ) {
	/**
	 * Contract 12: records the context and runs the builder once, the way `send()` would,
	 * so the subject and body can be looked at. Returns the number "sent".
	 */
	class WPCPM_Institutions {
		public static function notify_managers( $context, $build ) {
			$GLOBALS['notified'][] = array( 'context' => $context, 'mail' => call_user_func( $build, new WP_User( 1, 'Manager', 'm@example.test' ) ) );
			return 1;
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}
function code( $r ) { return $r instanceof WP_Error ? $r->get_error_code() : $r; }
function meta( $id, $k ) { return $GLOBALS['umeta'][ $id ][ $k ] ?? null; }
function audit_rows( $record ) { return WPCPM_Institution_Audit::entries_for( $record, 0 ); }

$A    = 'recDdomg5W6h410JT'; // the TEST institution in the seed fixture.
$B    = 'rec0IT9J93YkAYvSU';
$NONE = 'recZZZZZZZZZZZZZZ'; // well-formed, never indexed.

$GLOBALS['index'] = array(
	$A => array( 'record_id' => $A, 'name' => 'TEST - WordPress Education Dashboard (do not use) ', 'stage' => 'Confirmed', 'city' => 'Test', 'country_name' => 'Poland', 'website' => 'https://example.test', 'contact_person' => 'Test Person', 'contact_email' => 'test@example.test' ),
	$B => array( 'record_id' => $B, 'name' => 'Universidad Example', 'stage' => 'Student', 'city' => 'Example', 'country_name' => 'Costa Rica', 'website' => '', 'contact_person' => '', 'contact_email' => '' ),
);

$GLOBALS['users'] = array(
	1  => new WP_User( 1, 'Manager', 'manager@example.test', array( 'administrator' ) ),
	7  => new WP_User( 7, 'Anna Kowalska', 'anna@example.test', array( 'subscriber' ) ),
	8  => new WP_User( 8, 'Bob Mentor', 'bob@example.test', array( 'wpcpm_mentor' ) ),
	9  => new WP_User( 9, 'Cleo Student', 'cleo@example.test', array( 'wpcpm_student' ) ),
	10 => new WP_User( 10, 'Dan Second', 'dan@example.test', array( 'subscriber' ) ),
	11 => new WP_User( 11, 'Eve Editor', 'eve@example.test', array( 'editor' ) ),
	12 => new WP_User( 12, 'Frank Handmade', 'frank@example.test', array( 'wpcpm_institution' ) ),
);
$GLOBALS['manage']              = array( 1 );
$GLOBALS['umeta'][9]['wpcpm_student_record_id'] = 'recSTUDENT00000001';

/* ---- attach: every refusal, by name -------------------------------------- */

echo "=== attach() refuses, each by name ===\n";

ck( 'a malformed record ID', code( WPCPM_Institution_Members::attach( 7, 'nope', 'manager', 1 ) ), 'wpcpm_member_bad_record' );
ck( 'an empty record ID', code( WPCPM_Institution_Members::attach( 7, '', 'manager', 1 ) ), 'wpcpm_member_bad_record' );
ck( 'a well-formed record the index does not hold', code( WPCPM_Institution_Members::attach( 7, $NONE, 'manager', 1 ) ), 'wpcpm_member_not_indexed' );
ck( 'an account that does not exist', code( WPCPM_Institution_Members::attach( 999, $A, 'manager', 1 ) ), 'wpcpm_member_no_account' );
ck( 'user 0 is nobody, not the current user', code( WPCPM_Institution_Members::attach( 0, $A, 'manager', 1 ) ), 'wpcpm_member_no_account' );
ck( 'an administrator', code( WPCPM_Institution_Members::attach( 1, $A, 'manager', 1 ) ), 'wpcpm_member_is_admin' );
ck( 'an account carrying a student record', code( WPCPM_Institution_Members::attach( 9, $A, 'manager', 1 ) ), 'wpcpm_member_is_student' );
ck( 'a way of joining the list does not know', code( WPCPM_Institution_Members::attach( 7, $A, 'walked-in', 1 ) ), 'wpcpm_member_bad_how' );
ck( 'nothing was stamped by a refusal', meta( 7, 'wpcpm_institution_record_id' ), null );
ck( 'and no audit row was written by one', audit_rows( $A ), array() );

/* ---- a clean attach ------------------------------------------------------ */

echo "\n=== A clean attach ===\n";

$before = time();
ck( 'attach() returns true', WPCPM_Institution_Members::attach( 7, $A, 'manager', 1 ), true );
ck( 'the stamp', meta( 7, 'wpcpm_institution_record_id' ), $A );
ck( 'the flag', meta( 7, 'wpcpm_institution_active' ), 1 );
$facts = meta( 7, 'wpcpm_institution_membership' );
ck( 'the facts: by, how, invite', array( $facts['by'], $facts['how'], $facts['invite'] ), array( 1, 'manager', 0 ) );
ck( 'the facts: since is now', is_int( $facts['since'] ) && $facts['since'] >= $before, true );
ck( 'the profile, from the index row, name trimmed', meta( 7, 'wpcpm_institution_profile' ), array(
	'name'           => 'TEST - WordPress Education Dashboard (do not use)',
	'city'           => 'Test',
	'country_name'   => 'Poland',
	'stage'          => 'Confirmed',
	'website'        => 'https://example.test',
	'contact_person' => 'Test Person',
) );
ck( 'the profile carries no contact email', isset( meta( 7, 'wpcpm_institution_profile' )['contact_email'] ), false );
ck( 'no _was', meta( 7, 'wpcpm_institution_record_id_was' ), null );
ck( 'the role, through add_role()', $GLOBALS['add_role'], array( array( 7, 'wpcpm_institution' ) ) );
ck( 'and never through set_role()', $GLOBALS['set_role'], array() );
ck( 'a Subscriber keeps Subscriber beside it', $GLOBALS['users'][7]->roles, array( 'subscriber', 'wpcpm_institution' ) );

$rows = audit_rows( $A );
ck( 'one audit row', count( $rows ), 1 );
ck( 'member_added, about user 7, by the manager on the manager ground, against the index', array( $rows[0]['kind'], $rows[0]['subject'], $rows[0]['actor'], $rows[0]['ground'], $rows[0]['evidence'] ), array( 'member_added', '7', 1, 'manager', 'index' ) );
ck( 'the row names the institution', $rows[0]['institution'], $A );
ck( 'its data: how, invite, first time', array( $rows[0]['data']['how'], $rows[0]['data']['invite'], $rows[0]['data']['readded'] ), array( 'manager', 0, false ) );
ck( 'its message names the person', false !== strpos( $rows[0]['message'], 'Anna Kowalska' ), true );

ck( 'institution_of() answers with the record', WPCPM_Institution_Members::institution_of( 7 ), $A );
ck( 'memberships_of() is a list of one', WPCPM_Institution_Members::memberships_of( 7 ), array( $A ) );
ck( 'is_member()', WPCPM_Institution_Members::is_member( 7 ), true );
ck( 'and as a WP_User', WPCPM_Institution_Members::institution_of( $GLOBALS['users'][7] ), $A );
ck( 'null means the current user', ( function () { $GLOBALS['uid'] = 7; $r = WPCPM_Institution_Members::institution_of(); $GLOBALS['uid'] = 0; return $r; } )(), $A );

echo "\n=== The identity rules after a membership exists ===\n";

ck( 'a live member of this institution is already one', code( WPCPM_Institution_Members::attach( 7, $A, 'manager', 1 ) ), 'wpcpm_member_already' );
ck( 'a live member of another institution is refused', code( WPCPM_Institution_Members::attach( 7, $B, 'manager', 1 ) ), 'wpcpm_member_elsewhere' );
ck( 'the refusals wrote nothing', count( audit_rows( $A ) ) + count( audit_rows( $B ) ), 1 );

/* ---- the ground follows the actor ---------------------------------------- */

echo "\n=== The ground follows the actor ===\n";

ck( 'the sync attaches as the system', WPCPM_Institution_Members::attach( 10, $A, 'provisioned', 0 ), true );
$rows = audit_rows( $A );
ck( 'ground system, actor 0', array( $rows[0]['ground'], $rows[0]['actor'], $rows[0]['data']['how'] ), array( 'system', 0, 'provisioned' ) );
ck( 'newest first', array( $rows[0]['subject'], $rows[1]['subject'] ), array( '10', '7' ) );

ck( 'a member attaches on the member ground', WPCPM_Institution_Members::attach( 11, $A, 'invited', 7, 42 ), true );
$rows = audit_rows( $A );
ck( 'ground member, the invitation recorded', array( $rows[0]['ground'], $rows[0]['actor'], $rows[0]['data']['invite'] ), array( 'member', 7, 42 ) );
ck( 'an Editor keeps Editor', $GLOBALS['users'][11]->roles, array( 'editor', 'wpcpm_institution' ) );

ck( 'a mentor is allowed', WPCPM_Institution_Members::attach( 8, $B, 'approved', 1 ), true );
ck( 'and keeps the Mentor role beside the new one', $GLOBALS['users'][8]->roles, array( 'wpcpm_mentor', 'wpcpm_institution' ) );
ck( 'an account already holding the role is not given it twice', ( function () use ( $B ) {
	$n = count( $GLOBALS['add_role'] );
	WPCPM_Institution_Members::attach( 12, $B, 'legacy', 1 );
	return count( $GLOBALS['add_role'] ) - $n;
} )(), 0 );
ck( 'set_role() still never ran', $GLOBALS['set_role'], array() );

/* ---- members_of ---------------------------------------------------------- */

echo "\n=== members_of() ===\n";

$ids = function ( $users ) { return array_map( function ( $u ) { return $u->ID; }, $users ); };

ck( 'the live members of A', $ids( WPCPM_Institution_Members::members_of( $A ) ), array( 7, 10, 11 ) );
ck( 'the live members of B', $ids( WPCPM_Institution_Members::members_of( $B ) ), array( 8, 12 ) );
$q = $GLOBALS['queries'];
ck( 'a malformed ID returns nothing', WPCPM_Institution_Members::members_of( 'rec' ), array() );
ck( 'an empty ID returns nothing', WPCPM_Institution_Members::members_of( '' ), array() );
ck( 'and neither issued a query', $GLOBALS['queries'] - $q, 0 );
ck( 'a case-different ID is a different institution, whatever the collation says', WPCPM_Institution_Members::members_of( strtolower( $A ) ), array() );
ck( 'a member whose flag the sync zeroed is not live', ( function () use ( $A, $ids ) {
	$GLOBALS['umeta'][10]['wpcpm_institution_active'] = 0;
	$r = $ids( WPCPM_Institution_Members::members_of( $A ) );
	$GLOBALS['umeta'][10]['wpcpm_institution_active'] = 1;
	return $r;
} )(), array( 7, 11 ) );

/* ---- institution_of: the three conditions ------------------------------- */

echo "\n=== institution_of() checks all three conditions ===\n";

$GLOBALS['umeta'][20] = array( 'wpcpm_institution_record_id' => $A, 'wpcpm_institution_active' => 1 );
ck( 'a stamp on an account that does not exist is nobody', WPCPM_Institution_Members::institution_of( 20 ), '' );
$GLOBALS['users'][20] = new WP_User( 20, 'Ghost', 'g@example.test', array( 'wpcpm_institution' ) );
ck( 'the same stamp on an account that exists', WPCPM_Institution_Members::institution_of( 20 ), $A );
$GLOBALS['umeta'][20]['wpcpm_institution_active'] = 0;
ck( 'a well-formed stamp with the flag at 0 is nobody', WPCPM_Institution_Members::institution_of( 20 ), '' );
$GLOBALS['umeta'][20]['wpcpm_institution_active'] = '1';
ck( 'the flag as the string WordPress returns', WPCPM_Institution_Members::institution_of( 20 ), $A );
$GLOBALS['umeta'][20]['wpcpm_institution_record_id'] = '';
ck( 'an empty stamp with the flag at 1 is nobody', WPCPM_Institution_Members::institution_of( 20 ), '' );
$GLOBALS['umeta'][20]['wpcpm_institution_record_id'] = 'rec123';
ck( 'a malformed stamp with the flag at 1 is nobody', WPCPM_Institution_Members::institution_of( 20 ), '' );
ck( 'is_member() agrees', WPCPM_Institution_Members::is_member( 20 ), false );
ck( 'memberships_of() is empty, not array( "" )', WPCPM_Institution_Members::memberships_of( 20 ), array() );
ck( 'user 0 is nobody even with somebody logged in', ( function () { $GLOBALS['uid'] = 7; $r = WPCPM_Institution_Members::institution_of( 0 ); $GLOBALS['uid'] = 0; return $r; } )(), '' );
ck( 'a manager is nobody: attach() refuses administrators', WPCPM_Institution_Members::institution_of( 1 ), '' );
unset( $GLOBALS['users'][20], $GLOBALS['umeta'][20] );

/* ---- detach -------------------------------------------------------------- */

echo "\n=== detach() ===\n";

ck( 'an account that is not a member', code( WPCPM_Institution_Members::detach( 9, 'removed', 1 ) ), 'wpcpm_member_none' );
ck( 'an account that does not exist', code( WPCPM_Institution_Members::detach( 999, 'removed', 1 ) ), 'wpcpm_member_no_account' );
ck( 'a reason the list does not know', code( WPCPM_Institution_Members::detach( 7, 'vanished', 1 ) ), 'wpcpm_member_bad_reason' );
ck( 'which left the membership alone', WPCPM_Institution_Members::institution_of( 7 ), $A );

ck( 'a manager removes Anna', WPCPM_Institution_Members::detach( 7, 'removed', 1 ), true );
ck( 'the stamp is gone, not blank', array_key_exists( 'wpcpm_institution_record_id', $GLOBALS['umeta'][7] ), false );
ck( 'and lives on in _was', meta( 7, 'wpcpm_institution_record_id_was' ), $A );
ck( 'the flag is 0', meta( 7, 'wpcpm_institution_active' ), 0 );
ck( 'the facts are kept: history', meta( 7, 'wpcpm_institution_membership' )['how'], 'manager' );
ck( 'the role is gone and Subscriber remains', $GLOBALS['users'][7]->roles, array( 'subscriber' ) );
ck( 'no set_role() was needed', $GLOBALS['set_role'], array() );
ck( 'institution_of() is empty', WPCPM_Institution_Members::institution_of( 7 ), '' );
ck( 'the live members of A no longer include her', $ids( WPCPM_Institution_Members::members_of( $A ) ), array( 10, 11 ) );
ck( 'former_members_of() finds her', $ids( WPCPM_Institution_Members::former_members_of( $A ) ), array( 7 ) );
ck( 'and not under a case-different ID', WPCPM_Institution_Members::former_members_of( strtolower( $A ) ), array() );
$rows = audit_rows( $A );
ck( 'one member_removed row with the reason, on the manager ground', array( $rows[0]['kind'], $rows[0]['subject'], $rows[0]['ground'], $rows[0]['data']['reason'] ), array( 'member_removed', '7', 'manager', 'removed' ) );
ck( 'two members remain, so no last-member notice', $GLOBALS['notified'], array() );

ck( 'the sync revokes Dan', WPCPM_Institution_Members::detach( 10, 'revoked', 0 ), true );
$rows = audit_rows( $A );
ck( 'on the system ground, reason revoked', array( $rows[0]['ground'], $rows[0]['actor'], $rows[0]['data']['reason'] ), array( 'system', 0, 'revoked' ) );
ck( 'still no notice: Eve remains', $GLOBALS['notified'], array() );

ck( 'Eve leaves, the last member', WPCPM_Institution_Members::detach( 11, 'left', 11 ), true );
ck( 'an Editor keeps Editor and gets no Subscriber', $GLOBALS['users'][11]->roles, array( 'editor' ) );
ck( 'no set_role() for her either', $GLOBALS['set_role'], array() );
$rows = audit_rows( $A );
ck( 'on the member ground, reason left', array( $rows[0]['ground'], $rows[0]['actor'], $rows[0]['data']['reason'] ), array( 'member', 11, 'left' ) );
ck( 'the last-member notice fired once', count( $GLOBALS['notified'] ), 1 );
ck( 'with its context', $GLOBALS['notified'][0]['context'], 'member-last' );
$mail = $GLOBALS['notified'][0]['mail'];
ck( 'the subject names the site and the institution', $mail['subject'], '[WordPress Education Dashboard] TEST - WordPress Education Dashboard (do not use) has no members left' );
ck( 'the body names who, the record and the reason', array(
	false !== strpos( $mail['body'], 'Eve Editor' ),
	false !== strpos( $mail['body'], $A ),
	false !== strpos( $mail['body'], '(left)' ),
), array( true, true, true ) );
ck( 'nobody is left', WPCPM_Institution_Members::members_of( $A ), array() );
ck( 'three former members', $ids( WPCPM_Institution_Members::former_members_of( $A ) ), array( 7, 10, 11 ) );
ck( 'a second detach of the same account is refused: nothing to end', code( WPCPM_Institution_Members::detach( 11, 'left', 11 ) ), 'wpcpm_member_none' );
ck( 'and did not notify again', count( $GLOBALS['notified'] ), 1 );

echo "\n=== A mentor leaves an institution ===\n";

ck( 'Bob is removed from B', WPCPM_Institution_Members::detach( 8, 'removed', 1 ), true );
ck( 'and is still a mentor, nothing else', $GLOBALS['users'][8]->roles, array( 'wpcpm_mentor' ) );
ck( 'Frank, who held only the role, becomes a Subscriber', ( function () {
	WPCPM_Institution_Members::detach( 12, 'removed', 1 );
	return array( $GLOBALS['users'][12]->roles, $GLOBALS['set_role'] );
} )(), array( array( 'subscriber' ), array( array( 12, 'subscriber' ) ) ) );
ck( 'the notice fired for B once Frank, the last, was gone', array( count( $GLOBALS['notified'] ), $GLOBALS['notified'][1]['context'] ), array( 2, 'member-last' ) );

echo "\n=== An administrator's roles are never touched ===\n";

// A stamp an administrator could only carry by hand: attach() refuses them. detach()
// must still end it without demoting anyone.
$GLOBALS['umeta'][1] = array( 'wpcpm_institution_record_id' => $B, 'wpcpm_institution_active' => 1 );
$GLOBALS['users'][1]->roles = array( 'administrator', 'wpcpm_institution' );
ck( 'detach() ends it', WPCPM_Institution_Members::detach( 1, 'removed', 1 ), true );
ck( 'and the roles are as they were', $GLOBALS['users'][1]->roles, array( 'administrator', 'wpcpm_institution' ) );
ck( 'the stamp moved all the same', array( meta( 1, 'wpcpm_institution_record_id' ), meta( 1, 'wpcpm_institution_record_id_was' ) ), array( null, $B ) );
unset( $GLOBALS['umeta'][1] );
$GLOBALS['users'][1]->roles = array( 'administrator' );

/* ---- re-adding a former member ------------------------------------------- */

echo "\n=== A former member is re-added ===\n";

ck( 'Anna comes back', WPCPM_Institution_Members::attach( 7, $A, 'manager', 1 ), true );
ck( '_was is cleared', meta( 7, 'wpcpm_institution_record_id_was' ), null );
ck( 'the stamp and the flag are back', array( meta( 7, 'wpcpm_institution_record_id' ), meta( 7, 'wpcpm_institution_active' ) ), array( $A, 1 ) );
ck( 'the role too, through add_role()', $GLOBALS['users'][7]->roles, array( 'subscriber', 'wpcpm_institution' ) );
$rows = audit_rows( $A );
ck( 'the row says it was a re-add', array( $rows[0]['kind'], $rows[0]['data']['readded'] ), array( 'member_added', true ) );
ck( 'she is live again', $ids( WPCPM_Institution_Members::members_of( $A ) ), array( 7 ) );
ck( 'and no longer a former member', $ids( WPCPM_Institution_Members::former_members_of( $A ) ), array( 10, 11 ) );
ck( 'a former member of A may join B', WPCPM_Institution_Members::attach( 10, $B, 'manager', 1 ), true );
// The _was that names A survives joining B. `former_members_of( A )` promises it, a manager
// re-adds from that list in one click, and the sync's "no live member and no _was naming it"
// gate would otherwise provision A's removed contact again every night.
ck( 'and the _was that names A survives it', meta( 10, 'wpcpm_institution_record_id_was' ), $A );
ck( 'so she is still a former member of A', in_array( 10, $ids( WPCPM_Institution_Members::former_members_of( $A ) ), true ), true );
$rows_b = audit_rows( $B );
ck( 'and B\'s audit row does not call her a re-add', $rows_b[0]['data']['readded'], false );

/* ---- the audit log on its own -------------------------------------------- */

echo "\n=== The audit log ===\n";

$base = array( 'kind' => 'edit', 'institution' => $B, 'subject' => 'recSTUDENT00000001', 'actor' => 1, 'ground' => 'manager', 'evidence' => 'live', 'message' => 'ok', 'data' => array() );
ck( 'no kind', code( WPCPM_Institution_Audit::record( array_merge( $base, array( 'kind' => '' ) ) ) ), 'wpcpm_audit_kind' );
ck( 'no institution', code( WPCPM_Institution_Audit::record( array_merge( $base, array( 'institution' => '' ) ) ) ), 'wpcpm_audit_institution' );
ck( 'a malformed institution', code( WPCPM_Institution_Audit::record( array_merge( $base, array( 'institution' => 'rec' ) ) ) ), 'wpcpm_audit_institution' );
ck( 'an unknown ground', code( WPCPM_Institution_Audit::record( array_merge( $base, array( 'ground' => 'friend' ) ) ) ), 'wpcpm_audit_ground' );
ck( 'no ground', code( WPCPM_Institution_Audit::record( array_merge( $base, array( 'ground' => '' ) ) ) ), 'wpcpm_audit_ground' );
ck( 'an unknown evidence level', code( WPCPM_Institution_Audit::record( array_merge( $base, array( 'evidence' => 'hearsay' ) ) ) ), 'wpcpm_audit_evidence' );

$n  = count( audit_rows( $B ) );
$id = WPCPM_Institution_Audit::record( array_merge( $base, array(
	'message' => "  <b>Changed</b> the end date\nfor the second time  ",
	'data'    => array( 'field' => 'End Date', 'before' => '2026-06-30', 'after' => "2026-07-31\n<i>x</i>", 'days' => 31, 'ok' => true, 'obj' => new stdClass(), 'deep' => array( 'a' => array( 'b' => array( 'c' => 1 ) ) ), 'Odd Key' => 1 ),
) ) );
ck( 'a good row returns a post ID', is_int( $id ) && $id > 0, true );
$rows = audit_rows( $B );
ck( 'and is listed first', array( count( $rows ) - $n, $rows[0]['id'] ), array( 1, $id ) );
ck( 'the message is tags-stripped and trimmed', $rows[0]['message'], "Changed the end date\nfor the second time" );
ck( 'the data keeps scalars, strips tags and newlines, drops objects, nests only so far, sanitises keys', $rows[0]['data'], array(
	'field'   => 'End Date',
	'before'  => '2026-06-30',
	'after'   => '2026-07-31x',
	'days'    => 31,
	'ok'      => true,
	'deep'   => array( 'a' => array() ),
	'oddkey' => 1,
) );
ck( 'the time is the post time', $rows[0]['time'], 1700000000 + $id );
ck( 'the post carries the actor as its author', $GLOBALS['posts'][ $id ]->post_author, 1 );
ck( 'the post type', $GLOBALS['posts'][ $id ]->post_type, 'wpcpm_audit_entry' );
ck( 'the limit', count( WPCPM_Institution_Audit::entries_for( $B, 2 ) ), 2 );
ck( 'a malformed institution lists nothing', WPCPM_Institution_Audit::entries_for( 'rec', 0 ), array() );
ck( 'a case-different institution lists nothing', WPCPM_Institution_Audit::entries_for( strtolower( $B ), 0 ), array() );
ck( 'a long message is capped', strlen( ( function () use ( $base, $B ) {
	$id = WPCPM_Institution_Audit::record( array_merge( $base, array( 'message' => str_repeat( 'x', 5000 ) ) ) );
	return WPCPM_Institution_Audit::entries_for( $B, 1 )[0]['message'];
} )() ), WPCPM_Institution_Audit::MAX_MESSAGE );

$total = count( $GLOBALS['posts'] );
ck( 'there are rows to delete', $total > 5, true );
WPCPM_Institution_Audit::delete_all();
ck( 'delete_all() removes every row and its meta', array( $GLOBALS['posts'], $GLOBALS['pmeta'] ), array( array(), array() ) );
ck( 'and the log reads empty', audit_rows( $A ), array() );

/* ---- the structural rules ------------------------------------------------ */

echo "\n=== Nobody else writes the stamp ===\n";

$mine    = array( 'includes/modules/class-wpcpm-institution-members.php' );
$writers = array();
$literal = array();
$dashes  = array();
$rii     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPCPM_PLUGIN_DIR . 'includes' ) );

foreach ( $rii as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
		continue;
	}

	$rel = substr( $file->getPathname(), strlen( WPCPM_PLUGIN_DIR ) );
	$src = file_get_contents( $file->getPathname() );

	if ( in_array( $rel, $mine, true ) ) {
		continue;
	}

	if ( false !== strpos( $src, 'wpcpm_institution_record_id' ) ) {
		$literal[] = $rel;
	}

	// The constant is public so the policy and the screen can read the key; a write
	// through it from anywhere else is the same hole with a longer name.
	if ( preg_match( '/(update|add|delete)_user_meta\([^;]*WPCPM_Institution_Members::META_RECORD_ID(?!_WAS)/s', $src ) ) {
		$writers[] = $rel;
	}
}

ck( 'no other file under includes/ names wpcpm_institution_record_id', $literal, array() );
ck( 'no other file writes it through the constant', $writers, array() );

$own = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php' );
ck( 'the members class never set_role()s the Institution role', preg_match( '/set_role\(\s*WPCPM_Roles::ROLE_INSTITUTION/', $own ), 0 );
// Comment lines are skipped, as bin/check-references.php skips them: the docblock may name
// the method; the code may not call it by name until the shell exists.
$code_only = implode( "\n", array_filter( explode( "\n", $own ), function ( $l ) { $t = ltrim( $l ); return '' !== $t && 0 !== strpos( $t, '*' ) && 0 !== strpos( $t, '//' ) && 0 !== strpos( $t, '/*' ); } ) );
ck( 'and reaches notify_managers() only as an array callable', array( substr_count( $code_only, "array( 'WPCPM_Institutions', 'notify_managers' )" ), strpos( $code_only, 'WPCPM_Institutions::notify_managers(' ) ), array( 1, false ) );
ck( 'and compares no institution ID with ===', preg_match( '/===\s*\$(record_id|live|institution)\b|\$(record_id|live|institution)\s*===/', $own ), 0 );

foreach ( array(
	'includes/modules/class-wpcpm-institution-members.php',
	'includes/modules/class-wpcpm-institution-audit.php',
	'bin/test-institution-members.php',
) as $rel ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', file_get_contents( WPCPM_PLUGIN_DIR . $rel ) ) ) {
		$dashes[] = $rel;
	}
}
ck( 'no dash but the plain hyphen in these three files', $dashes, array() );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
