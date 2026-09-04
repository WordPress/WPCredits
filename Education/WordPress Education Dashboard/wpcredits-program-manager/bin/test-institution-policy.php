<?php
/**
 * The one fence, and the two things a grep can prove about the rest of the module.
 *
 * `WPCPM_Institution_Policy::decide()` is the only place the Institutions module compares
 * an institution ID with anything. This suite pins the map of grounds literally, so adding
 * a ground is a failing assertion until the expected map is updated in the same commit;
 * walks every action through a manager, a member, a member of the wrong institution, a
 * stranger and nobody; and locks and unlocks the agreement gate around the member ground.
 * The shape that produced every fence bug in this module's history, an empty stamp meeting
 * an empty subject, is asserted refused.
 *
 * The last two sections read source, not behaviour: every `admin_post_` handler the
 * institution classes register must decide before it touches Airtable, and no module file
 * but the policy may compare institution IDs with `===`. Both scan whatever files exist
 * when the suite runs, so a handler added in a later phase is covered the day it lands.
 *
 * Run from the plugin root:  php bin/test-institution-policy.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['users']        = array();
$GLOBALS['uid']          = 0;
$GLOBALS['manage']       = array();
$GLOBALS['memberships']  = array();
$GLOBALS['settled']      = array();
$GLOBALS['umeta']        = array();
$GLOBALS['pmeta']        = array();
$GLOBALS['roster']       = array();
$GLOBALS['roster_reads'] = 0;

class WP_Error {
	private $code;
	private $message;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID = 0, $roles = array(), $display_name = '', $user_email = '';
	public function __construct( $id = 0, $name = '' ) { $this->ID = $id; $this->display_name = $name; }
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = 'private';
	public function __construct( $id = 0, $type = '' ) { $this->ID = $id; $this->post_type = $type; }
}

function __( $s, $d = null ) { return $s; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
/**
 * Grants exactly one capability, the one the policy is meant to ask for. A policy that asked
 * for any other, or for none, would fail the manager assertions rather than pass by accident.
 */
function user_can( $u, $c ) {
	$id = is_object( $u ) ? $u->ID : (int) $u;
	return WPCPM_Roles::CAP_MANAGE === $c && in_array( $id, $GLOBALS['manage'], true );
}
function current_user_can( $c ) { return user_can( $GLOBALS['uid'], $c ); }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function get_post_meta( $id, $k = '', $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ''; }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';

/*
 * Stand-ins for the other pieces, to their contracts. Each is guarded so that a suite which
 * loads the real class first keeps it; none of the real files is loaded here, because the
 * policy is meant to work from these five calls and nothing else.
 */
if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	/** Stands in for the mentors sync: the one record-ID shape check the plugin has. */
	class WPCPM_Mentors_Sync {
		const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
		public static function is_record_id( $value ) { return (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) ); }
	}
}
if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	/** Stands in for the students sync: the meta key carrying a student's institution. */
	class WPCPM_Students_Sync { const META_INSTITUTION = 'wpcpm_student_institution'; }
}
if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	/** Stands in for the members module: the institutions an account acts for right now. */
	class WPCPM_Institution_Members {
		public static function memberships_of( $user = null ) {
			$id = is_object( $user ) ? $user->ID : (int) $user;
			return $GLOBALS['memberships'][ $id ] ?? array();
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	/** Stands in for the agreement module: whether that institution's agreement is settled. */
	class WPCPM_Institution_Agreement {
		public static function is_settled( $record_id ) { return in_array( $record_id, $GLOBALS['settled'], true ); }
	}
}
if ( ! class_exists( 'WPCPM_Roster_Index' ) ) {
	/** Stands in for the roster index: the rows filed under one institution, and a read counter. */
	class WPCPM_Roster_Index {
		public static function rows( $record_id ) {
			++$GLOBALS['roster_reads'];
			return $GLOBALS['roster'][ $record_id ] ?? array();
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';

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

/**
 * The decision decide() returns when a ground carries the action.
 *
 * @param string $ground      'manager' or 'member'.
 * @param string $institution The institution the decision names.
 * @return array
 */
function allowed_as( $ground, $institution ) {
	return array( 'allowed' => true, 'ground' => $ground, 'institution' => $institution, 'fields' => null, 'why' => '' );
}

/**
 * The decision decide() returns when nothing carries the action.
 *
 * @param string $why The reason, for the log.
 * @return array
 */
function refused_for( $why ) {
	return array( 'allowed' => false, 'ground' => '', 'institution' => '', 'fields' => array(), 'why' => $why );
}

/**
 * The body of one method, found by name and walked by brace depth.
 *
 * Enough to tell what a handler mentions and in which order, without parsing PHP properly;
 * the same heuristic bin/check-references.php uses for its handler pass.
 *
 * @param string $source The file.
 * @param string $name   The method.
 * @return string|null Null when the file has no such method.
 */
function method_body( $source, $name ) {
	if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\([^)]*\)\s*(?::\s*[^{]+)?\{/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}

	$offset = $m[0][1] + strlen( $m[0][0] );
	$depth  = 1;
	$end    = $offset;
	$length = strlen( $source );

	while ( $end < $length && $depth > 0 ) {
		if ( '{' === $source[ $end ] ) {
			++$depth;
		} elseif ( '}' === $source[ $end ] ) {
			--$depth;
		}

		++$end;
	}

	return substr( $source, $offset, $end - $offset );
}

/* ---- the fixture ---------------------------------------------------------- */

$A    = 'rec' . str_repeat( 'A', 14 );
$B    = 'rec' . str_repeat( 'B', 14 );
$TEST = 'recDdomg5W6h410JT';      // The TEST institution record, the one the Phase 1 demonstration is run against.
$S1   = 'recS' . str_repeat( '1', 13 );
$S2   = 'recS' . str_repeat( '2', 13 );

$GLOBALS['users'] = array(
	1 => new WP_User( 1, 'Manager' ),
	2 => new WP_User( 2, 'Member of A' ),
	3 => new WP_User( 3, 'Member of B' ),
	4 => new WP_User( 4, 'Stranger' ),
	5 => new WP_User( 5, 'Empty stamp' ),
	6 => new WP_User( 6, 'Manager who is also a member of A' ),
	7 => new WP_User( 7, 'Member of TEST' ),
	8 => new WP_User( 8, 'Student at A' ),
	9 => new WP_User( 9, 'Student nowhere' ),
);
$GLOBALS['manage']      = array( 1, 6 );
$GLOBALS['memberships'] = array(
	2 => array( $A ),
	3 => array( $B ),
	5 => array( '' ),       // The bug shape: a membership that is present and empty.
	6 => array( $A ),
	7 => array( $TEST ),
);
$GLOBALS['settled'] = array( $A, $B );
$GLOBALS['umeta']   = array(
	8 => array( WPCPM_Students_Sync::META_INSTITUTION => $A ),
);
$GLOBALS['roster']  = array(
	$A => array(
		$S1 => array( 'record_id' => $S1, 'name' => 'Ada', 'status' => 'In Sensei', 'institution' => $A, 'start' => '2026-02-01' ),
	),
);

$P = 'WPCPM_Institution_Policy';

/* ---- the stand-ins agree with the real files where those exist ------------ */

echo "=== The stand-ins ===\n";

ck( 'every fixture ID is well-formed',
    array_map( array( 'WPCPM_Mentors_Sync', 'is_record_id' ), array( $A, $B, $TEST, $S1, $S2 ) ),
    array( true, true, true, true, true ) );

// The stubs copy two things from the real classes: the record-ID pattern and the meta key.
// A stub that drifted from the real file would prove nothing, so each is read back from the
// real source and compared. When the real file does not declare the member yet, that is
// reported rather than failed: the students sync's meta key lands in the same phase.
// The pattern is the Airtable client's now (WPCPM_Airtable::RECORD_ID_PATTERN); the Mentors sync
// keeps a one-release alias, so the stub is compared with the client's declaration.
$airtable_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php' );
preg_match( "/const RECORD_ID_PATTERN\s*=\s*'([^']+)'/", $airtable_src, $m );
ck( 'the stub record-ID pattern is the real one', $m[1] ?? '', WPCPM_Mentors_Sync::RECORD_ID_PATTERN );

$students_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php' );
if ( preg_match( "/const META_INSTITUTION\s*=\s*'([^']+)'/", $students_src, $m ) ) {
	ck( 'the stub META_INSTITUTION is the real one', $m[1], WPCPM_Students_Sync::META_INSTITUTION );
} else {
	echo "note the students sync does not declare META_INSTITUTION yet; the stub carries the contract value\n";
}

/* ---- the map ------------------------------------------------------------- */

echo "\n=== The map of grounds ===\n";

// Literally, with the strings and not the constants, so that renaming a value or adding a
// ground to a row is a diff here as well as in the class. That is how a project clause is
// meant to land: on two rows, with this assertion updated in the same commit.
$expected_map = array(
	'view_roster'          => array( 'manager', 'member' ),
	'view_student'         => array( 'manager', 'member' ),
	'view_report'          => array( 'manager', 'member' ),
	'edit_student'         => array( 'manager', 'member' ),
	'change_status'        => array( 'manager', 'member' ),
	'add_student'          => array( 'manager', 'member' ),
	'export'               => array( 'manager', 'member' ),
	'view_semester_report' => array( 'manager', 'member' ),
	// Design of 4 September 2026, decision 1: the institution reads the report and never
	// writes it. The member ground stays on viewing, behind the agreement gate as before.
	'edit_semester_report' => array( 'manager' ),
	'manage_members'       => array( 'manager', 'member' ),
	'agreement'            => array( 'manager', 'member' ),
);

ck( 'grounds() is exactly the expected map, in order', $P::grounds(), $expected_map );
ck( 'ungated() is the agreement alone', $P::ungated(), array( 'agreement' ) );
ck( 'the two grounds and the refusal code', array( $P::GROUND_MANAGER, $P::GROUND_MEMBER, $P::REFUSAL_CODE ), array( 'manager', 'member', 'wpcpm_inst_unknown' ) );

// Every ACT_* constant the class declares is a row, and every row is a constant, so an action
// cannot be added to the class without being placed in the map, nor the other way round.
$constants = ( new ReflectionClass( $P ) )->getConstants();
$actions   = array();
foreach ( $constants as $name => $value ) {
	if ( 0 === strpos( $name, 'ACT_' ) ) {
		$actions[] = $value;
	}
}
ck( 'every ACT_* constant is a row of the map, and nothing else is', $actions, array_keys( $P::grounds() ) );
ck( 'the ungated action is one the map knows', array_values( array_diff( $P::ungated(), $actions ) ), array() );

/* ---- subjects ------------------------------------------------------------ */

echo "\n=== Subjects carry their own evidence ===\n";

ck( 'an institution subject names itself, from the index',
    $P::subject_institution( $A ),
    array( 'type' => 'institution', 'id' => $A, 'institution_ids' => array( $A ), 'evidence' => 'index' ) );

ck( 'a student account is placed by its stamp, from cache',
    $P::subject_student_account( 8 ),
    array( 'type' => 'student', 'id' => 8, 'institution_ids' => array( $A ), 'evidence' => 'cache' ) );

ck( 'an account with no stamp is institution-less, not empty-stamped',
    $P::subject_student_account( 9 )['institution_ids'], array() );

$before = $GLOBALS['roster_reads'];
ck( 'an index row is placed by its own institution value',
    $P::subject_index_row( $A, $S1 ),
    array( 'type' => 'student', 'id' => $S1, 'institution_ids' => array( $A ), 'evidence' => 'cache' ) );
ck( 'a row the index does not hold is institution-less', $P::subject_index_row( $A, $S2 )['institution_ids'], array() );
ck( 'and those two opened the index', $GLOBALS['roster_reads'] - $before, 2 );

$before = $GLOBALS['roster_reads'];
ck( 'a malformed institution ID opens nothing', $P::subject_index_row( 'pasted', $S1 )['institution_ids'], array() );
ck( 'nor does a malformed student ID', $P::subject_index_row( $A, '../../etc' )['institution_ids'], array() );
ck( 'no index was read for either', $GLOBALS['roster_reads'] - $before, 0 );

$GLOBALS['pmeta'] = array(
	501 => array( '_wpcpm_agr_institution' => $A ),
	502 => array(),
	503 => array( '_wpcpm_sr_institution' => array( $A, $B ) ),
	504 => array( '_wpcpm_batch_institution' => $B ),
);

ck( 'a post is placed by its own meta, never the form',
    $P::subject_post( new WP_Post( 501, 'wpcpm_agreement' ), '_wpcpm_agr_institution' ),
    array( 'type' => 'agreement', 'id' => 501, 'institution_ids' => array( $A ), 'evidence' => 'cache' ) );
ck( 'a post with no institution meta is institution-less',
    $P::subject_post( new WP_Post( 502, 'wpcpm_agreement' ), '_wpcpm_agr_institution' )['institution_ids'], array() );
ck( 'a list in the meta places the post under each',
    $P::subject_post( new WP_Post( 503, 'wpcpm_inst_report' ), '_wpcpm_sr_institution' ),
    array( 'type' => 'semester_report', 'id' => 503, 'institution_ids' => array( $A, $B ), 'evidence' => 'cache' ) );
ck( 'the batch post type reads as batch',
    $P::subject_post( new WP_Post( 504, 'wpcpm_import_batch' ), '_wpcpm_batch_institution' )['type'], 'batch' );
ck( 'a post type the map does not know passes through as itself',
    $P::subject_post( new WP_Post( 502, 'post' ), '_wpcpm_agr_institution' )['type'], 'post' );

ck( 'a live subject is what claim() handed over, marked live',
    $P::subject_live( 'report', $S1, array( $A ) ),
    array( 'type' => 'report', 'id' => $S1, 'institution_ids' => array( $A ), 'evidence' => 'live' ) );

// The builders reduce the list to strings and drop what cannot be one. What is well-formed is
// decide()'s question, so an odd but scalar value survives here and is filtered there.
ck( 'a builder drops empties and nested values but keeps every scalar as a string',
    $P::subject_live( 'student', $S1, array( '', null, array( $A ), 42, 'rec', $A ) )['institution_ids'],
    array( '42', 'rec', $A ) );

/* ---- decide(): who, on what, with what result ----------------------------- */

echo "\n=== decide() ===\n";

$inst_a     = $P::subject_institution( $A );
$inst_b     = $P::subject_institution( $B );
$nowhere    = $P::subject_live( 'student', $S2, array() );
$junk_only  = $P::subject_live( 'student', $S2, array( 'rec', 'recTOOSHORT', 'REC' . str_repeat( 'A', 14 ), 'rec' . str_repeat( 'A', 15 ), '42' ) );
$junk_and_a = $P::subject_live( 'student', $S2, array( 'rec', 'REC' . str_repeat( 'A', 14 ), $A, 'recTOOSHORT' ) );

ck( 'an action the map does not know is refused, before the user is looked at', $P::decide( 'delete_everything', $inst_a, 1 ), refused_for( 'unknown-action' ) );
ck( 'even for a manager, and even when the action is not a string', $P::decide( array( 'view_roster' ), $inst_a, 1 ), refused_for( 'unknown-action' ) );
ck( 'a case-shifted action is not the action', $P::decide( 'View_Roster', $inst_a, 1 ), refused_for( 'unknown-action' ) );

$GLOBALS['uid'] = 0;
ck( 'nobody logged in is no user', $P::decide( $P::ACT_VIEW_ROSTER, $inst_a, null ), refused_for( 'no-user' ) );
ck( 'an account that does not exist is no user', $P::decide( $P::ACT_VIEW_ROSTER, $inst_a, 99 ), refused_for( 'no-user' ) );
ck( 'a WP_User with no ID is no user', $P::decide( $P::ACT_VIEW_ROSTER, $inst_a, new WP_User( 0 ) ), refused_for( 'no-user' ) );
ck( 'and no user is decided before the subject is examined', $P::decide( $P::ACT_VIEW_ROSTER, $junk_only, 99 ), refused_for( 'no-user' ) );

// Every action, every kind of actor. The gate is open for A and B here and shut for TEST.
foreach ( $expected_map as $action => $grounds ) {
	ck( "a manager passes $action on A, as a manager", $P::decide( $action, $inst_a, 1 ), allowed_as( 'manager', $A ) );
	ck( "a manager passes $action on an institution-less subject, naming no institution", $P::decide( $action, $nowhere, 1 ), allowed_as( 'manager', '' ) );
	// Test member pass only if member is in the grounds for this action.
	if ( in_array( 'member', $grounds, true ) ) {
		ck( "a member of A passes $action on A, as a member", $P::decide( $action, $inst_a, 2 ), allowed_as( 'member', $A ) );
	} else {
		ck( "a member of A is refused $action on A with no ground", $P::decide( $action, $inst_a, 2 ), refused_for( 'no-ground' ) );
	}
	ck( "a member of B is refused $action on A with no ground", $P::decide( $action, $inst_a, 3 ), refused_for( 'no-ground' ) );
	ck( "a member of A is refused $action on an institution-less subject", $P::decide( $action, $nowhere, 2 ), refused_for( 'no-ground' ) );
	ck( "an account with no membership is refused $action", $P::decide( $action, $inst_a, 4 ), refused_for( 'no-ground' ) );
	ck( "a manager who is also a member passes $action as a manager, the first ground", $P::decide( $action, $inst_a, 6 ), allowed_as( 'manager', $A ) );
}

// The gate. Lock A: every member action but the agreement's own is refused; a manager passes.
$GLOBALS['settled'] = array( $B );
foreach ( $expected_map as $action => $grounds ) {
	$expected = 'agreement' === $action ? allowed_as( 'member', $A ) : refused_for( 'no-ground' );
	ck( "while A's agreement is not settled, a member gets $action: " . ( $expected['allowed'] ? 'allowed' : 'refused' ), $P::decide( $action, $inst_a, 2 ), $expected );
	ck( "while A's agreement is not settled, a manager passes $action", $P::decide( $action, $inst_a, 1 ), allowed_as( 'manager', $A ) );
}
ck( 'a manager who is also a member of the locked institution passes as a manager', $P::decide( $P::ACT_VIEW_ROSTER, $inst_a, 6 ), allowed_as( 'manager', $A ) );
ck( 'a member of B, still settled, is unaffected by the lock on A', $P::decide( $P::ACT_VIEW_ROSTER, $inst_b, 3 ), allowed_as( 'member', $B ) );
$GLOBALS['settled'] = array( $A, $B );

// The shape: malformed IDs are dropped on the subject's side before any ground looks.
ck( 'a subject carrying only malformed IDs is institution-less to a member', $P::decide( $P::ACT_VIEW_STUDENT, $junk_only, 2 ), refused_for( 'no-ground' ) );
ck( 'and to a manager, who passes naming no institution', $P::decide( $P::ACT_VIEW_STUDENT, $junk_only, 1 ), allowed_as( 'manager', '' ) );
ck( 'the well-formed ID among junk is the one that counts, for a member', $P::decide( $P::ACT_VIEW_STUDENT, $junk_and_a, 2 ), allowed_as( 'member', $A ) );
ck( 'and for a manager, who names it rather than the first junk entry', $P::decide( $P::ACT_VIEW_STUDENT, $junk_and_a, 1 ), allowed_as( 'manager', $A ) );

// Two empties never meet.
ck( 'an empty membership never matches an empty subject', $P::decide( $P::ACT_VIEW_ROSTER, $P::subject_institution( '' ), 5 ), refused_for( 'no-ground' ) );
ck( 'nor a subject whose one ID is empty', $P::decide( $P::ACT_VIEW_ROSTER, array( 'type' => 'student', 'id' => 9, 'institution_ids' => array( '' ), 'evidence' => 'cache' ), 5 ), refused_for( 'no-ground' ) );
ck( 'nor a well-formed subject', $P::decide( $P::ACT_VIEW_ROSTER, $inst_a, 5 ), refused_for( 'no-ground' ) );
ck( 'a subject with no ID list at all is institution-less, not fatal', $P::decide( $P::ACT_VIEW_ROSTER, array( 'type' => 'student', 'id' => 9, 'evidence' => 'cache' ), 2 ), refused_for( 'no-ground' ) );

// How the user may be named.
$GLOBALS['uid'] = 2;
ck( 'null is the current user', $P::decide( $P::ACT_VIEW_ROSTER, $inst_a ), allowed_as( 'member', $A ) );
ck( 'a WP_User is taken as given', $P::decide( $P::ACT_VIEW_ROSTER, $inst_a, $GLOBALS['users'][3] ), refused_for( 'no-ground' ) );
ck( 'an ID is resolved', $P::decide( $P::ACT_VIEW_ROSTER, $inst_b, '3' ), allowed_as( 'member', $B ) );
$GLOBALS['uid'] = 0;

// The subjects the handlers build, decided.
ck( "a student's account at A is a member of A's to see", $P::decide( $P::ACT_VIEW_STUDENT, $P::subject_student_account( 8 ), 2 ), allowed_as( 'member', $A ) );
ck( "and not a member of B's", $P::decide( $P::ACT_VIEW_STUDENT, $P::subject_student_account( 8 ), 3 ), refused_for( 'no-ground' ) );
ck( 'a student with no stamp is nobody\'s but a manager\'s', array( $P::decide( $P::ACT_VIEW_STUDENT, $P::subject_student_account( 9 ), 2 )['allowed'], $P::decide( $P::ACT_VIEW_STUDENT, $P::subject_student_account( 9 ), 1 )['allowed'] ), array( false, true ) );
ck( 'an index row under A is a member of A\'s to edit', $P::decide( $P::ACT_EDIT_STUDENT, $P::subject_index_row( $A, $S1 ), 2 ), allowed_as( 'member', $A ) );
ck( 'a member of B posting A\'s agreement post is decided against A', $P::decide( $P::ACT_AGREEMENT, $P::subject_post( new WP_Post( 501, 'wpcpm_agreement' ), '_wpcpm_agr_institution' ), 3 ), refused_for( 'no-ground' ) );
ck( 'while a member of A passes on the same post', $P::decide( $P::ACT_AGREEMENT, $P::subject_post( new WP_Post( 501, 'wpcpm_agreement' ), '_wpcpm_agr_institution' ), 2 ), allowed_as( 'member', $A ) );
ck( 'a post placed under two institutions is either\'s', $P::decide( $P::ACT_VIEW_SEMESTER_REPORT, $P::subject_post( new WP_Post( 503, 'wpcpm_inst_report' ), '_wpcpm_sr_institution' ), 3 ), allowed_as( 'member', $B ) );
ck( 'a live subject is decided on the link claim() read', $P::decide( $P::ACT_VIEW_REPORT, $P::subject_live( 'report', $S1, array( $B ) ), 2 ), refused_for( 'no-ground' ) );

/* ---- the Phase 1 demonstration, against the TEST record ------------------- */

echo "\n=== The TEST record: refuses a member while locked, passes a manager ===\n";

$test = $P::subject_institution( $TEST );

$GLOBALS['settled'] = array( $A, $B );
ck( 'locked: a member of TEST is refused the roster', $P::decide( $P::ACT_VIEW_ROSTER, $test, 7 ), refused_for( 'no-ground' ) );
ck( 'locked: a member of TEST is refused an export', $P::decide( $P::ACT_EXPORT, $test, 7 ), refused_for( 'no-ground' ) );
ck( 'locked: a member of TEST may still work on the agreement', $P::decide( $P::ACT_AGREEMENT, $test, 7 ), allowed_as( 'member', $TEST ) );
ck( 'locked: a manager passes the roster, as a manager, naming TEST', $P::decide( $P::ACT_VIEW_ROSTER, $test, 1 ), allowed_as( 'manager', $TEST ) );

// Typing On file and a Drive link into the grid row and pressing Refresh settles it.
$GLOBALS['settled'] = array( $A, $B, $TEST );
ck( 'settled: the member passes the roster', $P::decide( $P::ACT_VIEW_ROSTER, $test, 7 ), allowed_as( 'member', $TEST ) );

// Typing Revoked locks it on the next rebuild.
$GLOBALS['settled'] = array( $A, $B );
ck( 'revoked: the member is refused again', $P::decide( $P::ACT_VIEW_ROSTER, $test, 7 ), refused_for( 'no-ground' ) );
ck( 'revoked: the manager still passes', $P::decide( $P::ACT_VIEW_ROSTER, $test, 1 ), allowed_as( 'manager', $TEST ) );

/* ---- refusal() and scope() ------------------------------------------------ */

echo "\n=== refusal() and scope() ===\n";

$refusal = $P::refusal();
ck( 'refusal() is a WP_Error', is_wp_error( $refusal ), true );
ck( 'with the one code', $refusal->get_error_code(), 'wpcpm_inst_unknown' );
ck( 'and the one message, byte for byte', $refusal->get_error_message(), 'That record is not on your roster.' );
$again = $P::refusal();
ck( 'the same every time: no oracle in the wording', array( $again->get_error_code(), $again->get_error_message() ), array( $refusal->get_error_code(), $refusal->get_error_message() ) );

$keyed = array(
	'students|name'   => 'Name',
	'students|email'  => 'Email',
	'students|status' => 'Status',
	'reports|hours'   => 'Hours',
);

ck( 'a null scope permits everything, in the caller\'s order', $P::scope( allowed_as( 'member', $A ), $keyed ), $keyed );
ck( 'a manager\'s scope is null too', $P::scope( $P::decide( $P::ACT_VIEW_ROSTER, $inst_a, 1 ), $keyed ), $keyed );
ck( 'a list keeps only what it names, in the caller\'s order',
    $P::scope( array( 'allowed' => true, 'ground' => 'project', 'institution' => $A, 'fields' => array( 'reports|hours', 'students|name', 'students|nope' ), 'why' => '' ), $keyed ),
    array( 'students|name' => 'Name', 'reports|hours' => 'Hours' ) );
ck( 'an empty list permits nothing', $P::scope( array( 'allowed' => true, 'ground' => 'project', 'institution' => $A, 'fields' => array(), 'why' => '' ), $keyed ), array() );
ck( 'a refusal permits nothing', $P::scope( refused_for( 'no-ground' ), $keyed ), array() );
ck( 'even a refusal claiming a null scope', $P::scope( array( 'allowed' => false, 'ground' => '', 'institution' => '', 'fields' => null, 'why' => 'no-ground' ), $keyed ), array() );
ck( 'a decision with no fields key permits nothing', $P::scope( array( 'allowed' => true, 'ground' => 'manager', 'institution' => $A ), $keyed ), array() );
ck( 'nothing to narrow stays nothing', $P::scope( allowed_as( 'manager', '' ), array() ), array() );

/* ---- the class reads nothing it is not handed ----------------------------- */

echo "\n=== The policy has no state, no HTTP and no request ===\n";

$policy_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php' );

foreach ( array( '$_GET', '$_POST', '$_REQUEST', '$_SERVER', 'WPCPM_Request::', 'WPCPM_Airtable', 'wp_remote_', 'get_option(', 'update_option(', 'delete_option(', 'update_user_meta(', 'update_post_meta(', 'wp_insert_', 'static $' ) as $needle ) {
	ck( sprintf( 'the policy never touches %s', $needle ), false !== strpos( $policy_src, $needle ), false );
}
ck( 'it reads the memberships through the members module', substr_count( $policy_src, 'WPCPM_Institution_Members::memberships_of(' ), 1 );
ck( 'and the gate through the agreement module', substr_count( $policy_src, 'WPCPM_Institution_Agreement::is_settled(' ), 1 );
ck( 'the refusals are built with array_merge(), never +',
    array( substr_count( $policy_src, 'array_merge( $refused, array( \'why\' =>' ), false !== strpos( $policy_src, '$refused +' ), false !== strpos( $policy_src, '+ $refused' ) ),
    array( 3, false, false ) );

/* ---- source level: every handler decides before it touches Airtable ------- */

echo "\n=== Every admin_post_ handler decides before it touches Airtable ===\n";

$module_dir = WPCPM_PLUGIN_DIR . 'includes/modules/';
$inst_files = array_unique( array_merge(
	(array) glob( $module_dir . 'class-wpcpm-institution*.php' ),
	(array) glob( $module_dir . 'class-wpcpm-institutions*.php' )
) );
sort( $inst_files );

$scanned    = array();
$unresolved = array();

/**
 * Where a handler body first reaches WPCPM_Airtable: directly, or through one call into a
 * method of the same file whose body reaches it. Returns array( 'at' => offset in $body,
 * 'via' => the helper's name or '' ) or false.
 *
 * @param string $source The whole class file.
 * @param string $body   The handler's body.
 * @return array|false
 */
function airtable_reach( $source, $body ) {
	$best = false;
	$direct = strpos( $body, 'WPCPM_Airtable' );
	if ( false !== $direct ) {
		$best = array( 'at' => $direct, 'via' => '' );
	}
	if ( preg_match_all( '/(?:self|static)::([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $body, $calls, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $calls[1] as $call ) {
			$callee = $call[0];
			if ( in_array( $callee, array( 'bounce', 'leave', 'unknown' ), true ) ) { continue; }
			$callee_body = method_body( $source, $callee );
			if ( null === $callee_body || false === strpos( $callee_body, 'WPCPM_Airtable' ) ) { continue; }
			$at = $call[1];
			if ( false === $best || $at < $best['at'] ) {
				$best = array( 'at' => $at, 'via' => $callee );
			}
		}
	}
	return $best;
}

foreach ( $inst_files as $path ) {
	$source = (string) file_get_contents( $path );
	$class  = preg_match( '/^(?:final |abstract )?class ([A-Za-z_]+)/m', $source, $cm ) ? $cm[1] : basename( $path );

	// A registration is add_action( 'admin_post_...' [. CONSTANT], <callback> [, priority] );
	// the callback is either array( <object or class>, 'method' ) or 'Class::method'.
	if ( ! preg_match_all( '/add_action\(\s*[\'"]admin_post_[^\'"]*[\'"][^,]*,\s*(.+?)\s*\)\s*;/s', $source, $regs, PREG_SET_ORDER ) ) {
		continue;
	}

	foreach ( $regs as $reg ) {
		$callback = trim( $reg[1] );
		$method   = '';

		if ( preg_match( '/array\(\s*[^,]+,\s*[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]\s*\)/', $callback, $m ) ) {
			$method = $m[1];
		} elseif ( preg_match( '/^[\'"](?:[A-Za-z_]+::)?([A-Za-z_][A-Za-z0-9_]*)[\'"]/', $callback, $m ) ) {
			$method = $m[1];
		}

		if ( '' === $method ) {
			$unresolved[] = $class . ': ' . $callback;
			continue;
		}

		$body = method_body( $source, $method );

		// The three sync handlers are WPCPM_Sync_Module's since 1.90.0, one copy for the three
		// modules that own a sync; a registration that names one resolves through that file.
		if ( null === $body ) {
			$body = method_body( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php' ), $method );
		}

		if ( null === $body ) {
			$unresolved[] = $class . '::' . $method . '() is registered but not defined in the same file';
			continue;
		}

		// Transitive one hop: a handler that reaches Airtable through a helper it calls is
		// treated as reaching it at the call. Without this, 36 of 46 handlers passed on the
		// short-circuit "no Airtable use" because the literal class name sat one method away.
		$airtable = airtable_reach( $source, $body );
		$fences   = array_filter( array( strpos( $body, 'decide(' ), strpos( $body, 'claim(' ) ), 'is_int' );
		$fence    = $fences ? min( $fences ) : false;
		$ok       = false === $airtable ? true : ( false !== $fence && $fence < $airtable['at'] );

		$scanned[] = $class . '::' . $method . '()';
		ck( sprintf( '%s::%s() decides before it touches Airtable%s', $class, $method, false === $airtable ? ' (no Airtable use, directly or one call away)' : ( '' !== $airtable['via'] ? ' (through ' . $airtable['via'] . '())' : '' ) ), $ok, true );
	}
}

ck( 'every admin_post_ registration in the institution classes resolved to a method body', $unresolved, array() );
printf(
	"     files scanned: %s\n     handlers scanned: %s\n",
	$inst_files ? implode( ', ', array_map( 'basename', $inst_files ) ) : 'none',
	$scanned ? implode( ', ', $scanned ) : 'none registered yet'
);

/* ---- source level: nobody else compares institution IDs ------------------- */

echo "\n=== No module file but the policy compares institution IDs ===\n";

// The regex from section 5.4 of the design spec, applied to every module file but this
// class's own. A match is a handler carrying a second copy of a check the policy makes,
// which is the shape of every fence bug in this module's history.
$offenders = array();
$others    = 0;

foreach ( (array) glob( $module_dir . '*.php' ) as $path ) {
	if ( 'class-wpcpm-institution-policy.php' === basename( $path ) ) {
		continue;
	}

	++$others;

	if ( preg_match( '/wpcpm_(student_)?institution(_record_id)?[^;]*===/', (string) file_get_contents( $path ), $m ) ) {
		$offenders[ basename( $path ) ] = substr( $m[0], 0, 80 );
	}
}

ck( 'no file under includes/modules/ but the policy matches the comparison regex', $offenders, array() );
ck( 'and there were module files to scan', $others > 10, true );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
