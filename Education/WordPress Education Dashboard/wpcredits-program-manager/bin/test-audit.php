<?php
/**
 * WPCPM_Institution_Audit: one log, four keys (institution, sponsor, program and Claude's calls).
 *
 * Run: php bin/test-audit.php
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
class WP_Post {
	public $ID; public $post_type; public $post_status; public $post_author; public $post_content; public $post_title; public $post_date_gmt;
	public function __construct( array $a ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}
class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); }
}

function __( $t ) { return $t; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_date( $f, $t = null ) { return '2026-09-05 10:00'; }
// A row's time: its own `post_date_gmt` when a check set one, else a second per ID after a fixed start, so
// the rows written one after another are in order.
function wpcpm_test_when( $post ) { return ! empty( $post->post_date_gmt ) ? (int) strtotime( $post->post_date_gmt . ' UTC' ) : 1757000000 + (int) $post->ID; }
function get_post_time( $f, $gmt, $post, $translate = false ) { return wpcpm_test_when( $post ); }
function add_action() {}
function register_post_type() {}

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['next']  = 100;
// As core's insert: the fields unslashed, so words reach it as slashed copies.
function wp_insert_post( array $a, $wp_error = false ) {
	$id = $GLOBALS['next']++;
	$a = stripslashes_deep( $a );
	$a['ID'] = $id;
	$GLOBALS['posts'][ $id ] = new WP_Post( $a );
	return $id;
}
// As core's: what is written is unslashed first, at any depth.
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ $id ][ $k ] = stripslashes_deep( $v ); return true; }
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function get_post_meta( $id, $k, $single = false ) { return isset( $GLOBALS['pmeta'][ $id ][ $k ] ) ? $GLOBALS['pmeta'][ $id ][ $k ] : ''; }
// As core's: the post it deleted, or false.
function wp_delete_post( $id, $force = false ) {
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) { return false; }
	$post = $GLOBALS['posts'][ $id ];
	unset( $GLOBALS['posts'][ $id ], $GLOBALS['pmeta'][ $id ] );
	return $post;
}
// Enough of get_posts() for the readers: post type, status, numberposts, fields (`ids`), a meta_query of
// `key`+`value` or `key`+`compare EXISTS` clauses (AND), a date_query of `before` clauses on the GMT date
// (exclusive), and newest first (date, then ID) unless the date is asked for ascending.
function get_posts( array $args ) {
	$out = array();
	$ids = array_keys( $GLOBALS['posts'] );
	usort( $ids, function ( $a, $b ) {
		return array( wpcpm_test_when( $GLOBALS['posts'][ $b ] ), $b ) <=> array( wpcpm_test_when( $GLOBALS['posts'][ $a ] ), $a );
	} );
	if ( isset( $args['orderby']['date'] ) && 'ASC' === $args['orderby']['date'] ) { $ids = array_reverse( $ids ); }
	foreach ( $ids as $id ) {
		$post = $GLOBALS['posts'][ $id ];
		if ( $post->post_type !== $args['post_type'] ) { continue; }
		$ok = true;
		foreach ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			$have = isset( $GLOBALS['pmeta'][ $id ][ $clause['key'] ] );
			if ( isset( $clause['compare'] ) && 'EXISTS' === $clause['compare'] ) { $ok = $ok && $have; continue; }
			$ok = $ok && $have && 0 === strcasecmp( (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ], (string) $clause['value'] );
		}
		foreach ( isset( $args['date_query'] ) ? $args['date_query'] : array() as $clause ) {
			if ( is_array( $clause ) && isset( $clause['before'] ) ) { $ok = $ok && wpcpm_test_when( $post ) < strtotime( $clause['before'] . ' UTC' ); }
		}
		if ( $ok ) { $out[] = ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? $id : $post; }
		if ( isset( $args['numberposts'] ) && $args['numberposts'] > 0 && count( $out ) >= $args['numberposts'] ) { break; }
	}
	return $out;
}

require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-audit.php';

$fail   = 0;
$checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

// rec + 14 alphanumeric characters, the shape WPCPM_Airtable::RECORD_ID_PATTERN and this
// stub both require; a mnemonic string one character over that length would be refused by
// is_record_id() and every write below would fail before the behaviour under test ran.
$A = 'recSPONSOR0000001';
$B = 'recSPONSOR0000002';
$I = 'recINSTITUTION001';
$base = array( 'ground' => WPCPM_Institution_Audit::GROUND_MANAGER, 'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX, 'actor' => 3, 'subject' => '5' );

echo "=== Writing on the sponsor key ===\n";
$row = WPCPM_Institution_Audit::record_sponsor( array_merge( $base, array( 'kind' => 'member_added', 'sponsor' => $A, 'message' => 'Rep One was added.', 'data' => array( 'user' => 5 ) ) ) );
ck( 'a sponsor row is written', is_int( $row ) && $row > 0, true );
ck( 'under the sponsor key', get_post_meta( $row, WPCPM_Institution_Audit::META_SPONSOR, true ), $A );
ck( 'and not under the institution key', isset( $GLOBALS['pmeta'][ $row ][ WPCPM_Institution_Audit::META_INSTITUTION ] ), false );
ck( 'the key is the log stem\'s', WPCPM_Institution_Audit::META_SPONSOR, '_wpcpm_log_sponsor' );
ck( 'the title names the sponsor', $GLOBALS['posts'][ $row ]->post_title, 'member_added on ' . $A . ' - 2026-09-05 10:00' );
ck( 'private, like every row', $GLOBALS['posts'][ $row ]->post_status, 'private' );
$bad = WPCPM_Institution_Audit::record_sponsor( array_merge( $base, array( 'kind' => 'x', 'sponsor' => 'not-a-record' ) ) );
ck( 'a malformed sponsor is refused with its own code', is_wp_error( $bad ) ? $bad->get_error_code() : '', 'wpcpm_audit_sponsor' );
$bad = WPCPM_Institution_Audit::record_sponsor( array_merge( $base, array( 'kind' => 'x', 'sponsor' => $A, 'ground' => 'nobody' ) ) );
ck( 'and the ground is still checked', is_wp_error( $bad ) ? $bad->get_error_code() : '', 'wpcpm_audit_ground' );

echo "\n=== The institution writer is unchanged ===\n";
$inst = WPCPM_Institution_Audit::record( array_merge( $base, array( 'kind' => 'member_added', 'institution' => $I, 'message' => 'x' ) ) );
ck( 'an institution row carries the institution key', get_post_meta( $inst, WPCPM_Institution_Audit::META_INSTITUTION, true ), $I );
ck( 'and no sponsor key', isset( $GLOBALS['pmeta'][ $inst ][ WPCPM_Institution_Audit::META_SPONSOR ] ), false );
$inst_bad = WPCPM_Institution_Audit::record( array_merge( $base, array( 'kind' => 'x', 'institution' => '' ) ) );
ck( 'and still refuses a row with no institution', is_wp_error( $inst_bad ) ? $inst_bad->get_error_code() : '', 'wpcpm_audit_institution' );

echo "\n=== Reading ===\n";
WPCPM_Institution_Audit::record_sponsor( array_merge( $base, array( 'kind' => 'sponsor_interest', 'sponsor' => $A, 'message' => 'Interest one.' ) ) );
WPCPM_Institution_Audit::record_sponsor( array_merge( $base, array( 'kind' => 'sponsor_interest', 'sponsor' => $B, 'message' => 'Interest two.' ) ) );
WPCPM_Institution_Audit::record_sponsor( array_merge( $base, array( 'kind' => 'member_added', 'sponsor' => strtolower( $B ), 'message' => 'Case-different twin.' ) ) );
$for_a = WPCPM_Institution_Audit::entries_for_sponsor( $A );
ck( 'entries_for_sponsor() lists the sponsor\'s rows, newest first', array_column( $for_a, 'kind' ), array( 'sponsor_interest', 'member_added' ) );
ck( 'each carrying the sponsor and an empty institution', array( $for_a[0]['sponsor'], $for_a[0]['institution'] ), array( $A, '' ) );
ck( 'and never an institution\'s rows', WPCPM_Institution_Audit::entries_for_sponsor( $I ), array() );
$for_b = WPCPM_Institution_Audit::entries_for_sponsor( $B );
ck( 'the database matched the case-different twin; the reader drops it', count( $for_b ), 1 );
ck( 'entries_for() carries the new key too, empty', WPCPM_Institution_Audit::entries_for( $I )[0]['sponsor'], '' );
$interests = WPCPM_Institution_Audit::sponsor_entries( 'sponsor_interest' );
ck( 'sponsor_entries() lists one kind across every sponsor, newest first', array_column( $interests, 'sponsor' ), array( $B, $A ) );
ck( 'and every kind when none is named', count( WPCPM_Institution_Audit::sponsor_entries() ), 4 );
ck( 'capped', count( WPCPM_Institution_Audit::sponsor_entries( '', 2 ) ), 2 );
ck( 'and none of them is the institution row', in_array( $I, array_column( WPCPM_Institution_Audit::sponsor_entries(), 'institution' ), true ), false );

echo "\n=== A minted key is accepted everywhere a record id was ===\n";
// WPCPM_Sponsors_Index is never loaded in this suite, so this also proves the fallback pattern
// works with the real class absent, not only the real is_key().
$SPO     = 'spoAAAA0000000001';
$spo_row = WPCPM_Institution_Audit::record_sponsor( array_merge( $base, array( 'kind' => 'member_added', 'sponsor' => $SPO, 'message' => 'Minted sponsor.', 'data' => array() ) ) );
ck( 'record_sponsor() writes a row for a spo key', is_int( $spo_row ) && $spo_row > 0, true );

echo "\n=== Writing a row about the program's own work ===\n";

// An event the program plans itself is about no institution and no sponsor. Asked through a guard,
// so a writer that is not there fails these checks rather than ending the run.
$program_write = static function ( array $entry ) {
	return method_exists( 'WPCPM_Institution_Audit', 'record_program' ) ? WPCPM_Institution_Audit::record_program( $entry ) : new WP_Error( 'no_writer', 'record_program() is not there' );
};
$prog = $program_write( array_merge( $base, array( 'kind' => 'sponsor_event_planned', 'sponsor' => $A, 'institution' => $I, 'message' => 'A program event was planned for August 20, 2027.', 'data' => array( 'start' => 1818514800, 'joined' => 0 ) ) ) );
ck( 'a row about the program\'s own work is written', is_int( $prog ) && $prog > 0, true );
ck( 'under the program\'s key, which holds program, and the key is the log stem\'s', array( is_int( $prog ) ? get_post_meta( $prog, defined( 'WPCPM_Institution_Audit::META_PROGRAM' ) ? constant( 'WPCPM_Institution_Audit::META_PROGRAM' ) : '_wpcpm_log_program', true ) : 'no row', defined( 'WPCPM_Institution_Audit::META_PROGRAM' ) ? constant( 'WPCPM_Institution_Audit::META_PROGRAM' ) : 'undefined' ), array( 'program', '_wpcpm_log_program' ) );
ck( 'and under neither the sponsor key nor the institution key, whatever the entry named', array( is_int( $prog ), is_int( $prog ) && isset( $GLOBALS['pmeta'][ $prog ][ WPCPM_Institution_Audit::META_SPONSOR ] ), is_int( $prog ) && isset( $GLOBALS['pmeta'][ $prog ][ WPCPM_Institution_Audit::META_INSTITUTION ] ) ), array( true, false, false ) );
ck( 'the row as every row keeps it: the kind, the subject, the actor, the ground, the evidence and the facts; private, its title naming the program', is_int( $prog ) ? array( get_post_meta( $prog, WPCPM_Institution_Audit::META_KIND, true ), get_post_meta( $prog, WPCPM_Institution_Audit::META_SUBJECT, true ), get_post_meta( $prog, WPCPM_Institution_Audit::META_ACTOR, true ), get_post_meta( $prog, WPCPM_Institution_Audit::META_GROUND, true ), get_post_meta( $prog, WPCPM_Institution_Audit::META_EVIDENCE, true ), get_post_meta( $prog, WPCPM_Institution_Audit::META_DATA, true ), $GLOBALS['posts'][ $prog ]->post_status, $GLOBALS['posts'][ $prog ]->post_title ) : 'no row', array( 'sponsor_event_planned', '5', 3, 'manager', 'index', array( 'start' => 1818514800, 'joined' => 0 ), 'private', 'sponsor_event_planned on program - 2026-09-05 10:00' ) );
$bad = $program_write( array_merge( $base, array( 'kind' => 'sponsor_event_planned', 'ground' => 'nobody' ) ) );
ck( 'the ground is checked as for every row', is_wp_error( $bad ) ? $bad->get_error_code() : '', 'wpcpm_audit_ground' );
$bad = $program_write( array_merge( $base, array( 'kind' => '' ) ) );
ck( 'and the kind', is_wp_error( $bad ) ? $bad->get_error_code() : '', 'wpcpm_audit_kind' );
ck( 'no reader of a sponsor\'s or an institution\'s rows lists it', array( is_int( $prog ), in_array( $prog, array_column( WPCPM_Institution_Audit::sponsor_entries(), 'id' ), true ), in_array( $prog, array_column( WPCPM_Institution_Audit::entries_for_sponsor( $A ), 'id' ), true ), in_array( $prog, array_column( WPCPM_Institution_Audit::entries_for( $I ), 'id' ), true ) ), array( true, false, false, false ) );

echo "\n=== A row's words keep their backslashes ===\n";
// The message goes into `wp_insert_post()` and the data into post meta, which unslash what they
// are handed, as core's do: a typed note or a name a row carries keeps a backslash only when both
// are written as slashed copies.
$typed = 'Filed in C:\drafts\log, two \\\\ in a row, under "kept".';
$typed_row = WPCPM_Institution_Audit::record( array_merge( $base, array( 'kind' => 'note_added', 'institution' => $I, 'message' => $typed, 'data' => array( 'name' => $typed ) ) ) );
$typed_entry = WPCPM_Institution_Audit::entries_for( $I )[0];
ck( 'a row\'s message is kept exactly as written', $typed_entry['message'], $typed );
ck( 'and so are the words in its data', $typed_entry['data']['name'] ?? null, $typed );

echo "\n=== Writing a row about a call to an ability (Claude access) ===\n";

// The fourth key, in the same shape as the program's. Asked through a guard, so a writer that is not there
// fails these checks rather than ending the run.
$mcp_write = static function ( array $entry ) {
	return method_exists( 'WPCPM_Institution_Audit', 'record_mcp' ) ? WPCPM_Institution_Audit::record_mcp( $entry ) : new WP_Error( 'no_writer', 'record_mcp() is not there' );
};
$mcp_read = static function ( $limit = 100 ) {
	return method_exists( 'WPCPM_Institution_Audit', 'mcp_entries' ) ? WPCPM_Institution_Audit::mcp_entries( $limit ) : array();
};
$mcp_key = defined( 'WPCPM_Institution_Audit::META_MCP' ) ? constant( 'WPCPM_Institution_Audit::META_MCP' ) : '_wpcpm_log_mcp';
$call    = array( 'ability' => 'wpcpm/institution-summary', 'outcome' => 'answered', 'reason' => '', 'input' => array( 'institution' => 'Example' ), 'rows' => 1, 'records' => array( $I ), 'ms' => 12, 'password' => 'Claude - laptop', 'password_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee' );
$mcp = $mcp_write( array( 'actor' => 3, 'ground' => 'manager', 'evidence' => 'index', 'data' => $call ) );
ck( 'a row about a call is written', is_int( $mcp ) && $mcp > 0, true );
ck( 'the fourth key is the log stem\'s, and the row holds mcp under it', array( $mcp_key, is_int( $mcp ) ? get_post_meta( $mcp, $mcp_key, true ) : 'no row' ), array( '_wpcpm_log_mcp', 'mcp' ) );
ck( 'the row is of kind mcp_call, with the subject empty and the actor and ground and evidence as given', is_int( $mcp ) ? array( get_post_meta( $mcp, WPCPM_Institution_Audit::META_KIND, true ), get_post_meta( $mcp, WPCPM_Institution_Audit::META_SUBJECT, true ), get_post_meta( $mcp, WPCPM_Institution_Audit::META_ACTOR, true ), get_post_meta( $mcp, WPCPM_Institution_Audit::META_GROUND, true ), get_post_meta( $mcp, WPCPM_Institution_Audit::META_EVIDENCE, true ) ) : 'no row', array( 'mcp_call', '', 3, 'manager', 'index' ) );
ck( 'its facts are kept', is_int( $mcp ) ? get_post_meta( $mcp, WPCPM_Institution_Audit::META_DATA, true ) : 'no row', $call );
ck( 'the row is private and its title names the call', is_int( $mcp ) ? array( $GLOBALS['posts'][ $mcp ]->post_status, $GLOBALS['posts'][ $mcp ]->post_title, $GLOBALS['posts'][ $mcp ]->post_content ) : 'no row', array( 'private', 'mcp_call on mcp - 2026-09-05 10:00', '' ) );
ck( 'under none of the other three keys, whatever the entry named', is_int( $mcp ) ? array( isset( $GLOBALS['pmeta'][ $mcp ][ WPCPM_Institution_Audit::META_INSTITUTION ] ), isset( $GLOBALS['pmeta'][ $mcp ][ WPCPM_Institution_Audit::META_SPONSOR ] ), isset( $GLOBALS['pmeta'][ $mcp ][ WPCPM_Institution_Audit::META_PROGRAM ] ) ) : 'no row', array( false, false, false ) );
$named = $mcp_write( array( 'institution' => $I, 'sponsor' => $A, 'kind' => 'member_added', 'actor' => 4, 'ground' => 'member', 'evidence' => 'live', 'message' => 'A message', 'data' => array( 'outcome' => 'refused', 'reason' => 'hour_limit' ) ) );
ck( 'an institution or a sponsor in the entry is not written, and the kind is always mcp_call', is_int( $named ) ? array( isset( $GLOBALS['pmeta'][ $named ][ WPCPM_Institution_Audit::META_INSTITUTION ] ), isset( $GLOBALS['pmeta'][ $named ][ WPCPM_Institution_Audit::META_SPONSOR ] ), get_post_meta( $named, WPCPM_Institution_Audit::META_KIND, true ), get_post_meta( $named, $mcp_key, true ) ) : 'no row', array( false, false, 'mcp_call', 'mcp' ) );
$bad = $mcp_write( array( 'actor' => 3, 'ground' => 'nobody', 'evidence' => 'index' ) );
ck( 'the ground is checked as for every row', is_wp_error( $bad ) ? $bad->get_error_code() : '', 'wpcpm_audit_ground' );
$bad = $mcp_write( array( 'actor' => 3, 'ground' => 'manager', 'evidence' => 'nowhere' ) );
ck( 'and the evidence', is_wp_error( $bad ) ? $bad->get_error_code() : '', 'wpcpm_audit_evidence' );
$typed_call = $mcp_write( array( 'actor' => 3, 'ground' => 'manager', 'evidence' => 'index', 'data' => array( 'input' => array( 'institution' => 'C:\drafts "x"' ) ) ) );
ck( 'a name looked up keeps its backslash, like every row\'s words', is_int( $typed_call ) ? get_post_meta( $typed_call, WPCPM_Institution_Audit::META_DATA, true ) : 'no row', array( 'input' => array( 'institution' => 'C:\drafts "x"' ) ) );

echo "\n=== No other reader lists a call; the call reader lists only calls ===\n";
$prog_row = $program_write( array_merge( $base, array( 'kind' => 'sponsor_event_planned', 'message' => 'Program row for the call reader.' ) ) );
ck( 'no reader of a sponsor\'s or an institution\'s rows lists a call', array( is_int( $mcp ), in_array( $mcp, array_column( WPCPM_Institution_Audit::sponsor_entries(), 'id' ), true ), in_array( $mcp, array_column( WPCPM_Institution_Audit::entries_for_sponsor( $A ), 'id' ), true ), in_array( $mcp, array_column( WPCPM_Institution_Audit::entries_for( $I ), 'id' ), true ), in_array( $named, array_column( WPCPM_Institution_Audit::sponsor_entries(), 'id' ), true ), in_array( $named, array_column( WPCPM_Institution_Audit::entries_for_sponsor( $A ), 'id' ), true ), in_array( $named, array_column( WPCPM_Institution_Audit::entries_for( $I ), 'id' ), true ) ), array( true, false, false, false, false, false, false ) );
ck( 'and the sponsor reader still lists its own rows', count( WPCPM_Institution_Audit::sponsor_entries() ) > 0, true );
$listed = $mcp_read();
ck( 'mcp_entries() lists the three calls, newest first', array_column( $listed, 'id' ), is_int( $typed_call ) ? array( $typed_call, $named, $mcp ) : array() );
ck( 'none of them an institution\'s, a sponsor\'s or the program\'s row', array_unique( array_column( $listed, 'kind' ) ), array( 'mcp_call' ) );
ck( 'each in the shape entries_for() returns', array_keys( $listed[0] ?? array() ), array_keys( WPCPM_Institution_Audit::entries_for( $I )[0] ) );
ck( 'with the call\'s facts under data', $listed[2]['data'] ?? null, $call );
ck( 'capped by the limit', array_column( $mcp_read( 2 ), 'id' ), is_int( $typed_call ) ? array( $typed_call, $named ) : array() );
ck( 'all of them for a limit of zero', count( $mcp_read( 0 ) ), 3 );
ck( 'and the program\'s row is in none of it', in_array( $prog_row, array_column( $mcp_read( 0 ), 'id' ), true ), false );

echo "\n=== Deleting old calls, and only calls ===\n";
$delete = static function ( $before, $limit = null ) {
	if ( ! method_exists( 'WPCPM_Institution_Audit', 'delete_mcp_before' ) ) { return 'no deleter'; }
	return null === $limit ? WPCPM_Institution_Audit::delete_mcp_before( $before ) : WPCPM_Institution_Audit::delete_mcp_before( $before, $limit );
};
$dated = static function ( $id, $when ) { if ( is_int( $id ) ) { $GLOBALS['posts'][ $id ]->post_date_gmt = $when; } return $id; };
$cut   = strtotime( '2026-09-01 00:00:00 UTC' );
// The three calls written above are recent, so they are not among the old ones.
$dated( $mcp, '2026-10-21 08:00:00' );
$dated( $named, '2026-10-21 09:00:00' );
$dated( $typed_call, '2026-10-21 10:00:00' );
$old_a = $dated( $mcp_write( array( 'actor' => 3, 'ground' => 'manager', 'evidence' => 'index', 'data' => array( 'n' => 1 ) ) ), '2026-07-01 08:00:00' );
$old_b = $dated( $mcp_write( array( 'actor' => 3, 'ground' => 'manager', 'evidence' => 'index', 'data' => array( 'n' => 2 ) ) ), '2026-07-02 08:00:00' );
$old_c = $dated( $mcp_write( array( 'actor' => 3, 'ground' => 'manager', 'evidence' => 'index', 'data' => array( 'n' => 3 ) ) ), '2026-07-03 08:00:00' );
$edge  = $dated( $mcp_write( array( 'actor' => 3, 'ground' => 'manager', 'evidence' => 'index', 'data' => array( 'n' => 4 ) ) ), '2026-09-01 00:00:00' );
$new_a = $dated( $mcp_write( array( 'actor' => 3, 'ground' => 'manager', 'evidence' => 'index', 'data' => array( 'n' => 5 ) ) ), '2026-10-20 08:00:00' );
$old_inst    = $dated( WPCPM_Institution_Audit::record( array_merge( $base, array( 'kind' => 'member_added', 'institution' => $I, 'message' => 'Old institution row.' ) ) ), '2026-06-01 08:00:00' );
$old_sponsor = $dated( WPCPM_Institution_Audit::record_sponsor( array_merge( $base, array( 'kind' => 'member_added', 'sponsor' => $A, 'message' => 'Old sponsor row.' ) ) ), '2026-06-02 08:00:00' );
$old_prog    = $dated( $program_write( array_merge( $base, array( 'kind' => 'sponsor_event_planned', 'message' => 'Old program row.' ) ) ), '2026-06-03 08:00:00' );
$alive = static function ( $id ) { return is_int( $id ) && isset( $GLOBALS['posts'][ $id ] ); };

ck( 'a limit of two deletes the two oldest calls and says two', array( $delete( $cut, 2 ), $alive( $old_a ), $alive( $old_b ), $alive( $old_c ) ), array( 2, false, false, true ) );
ck( 'the next run deletes the one that is left, and says one', array( $delete( $cut ), $alive( $old_c ) ), array( 1, false ) );
ck( 'a call written at the very time is not older than it, and a newer one stays', array( $alive( $edge ), $alive( $new_a ) ), array( true, true ) );
ck( 'an old institution row, an old sponsor row and an old program row are left, whatever their age', array( $alive( $old_inst ), $alive( $old_sponsor ), $alive( $old_prog ) ), array( true, true, true ) );
ck( 'nothing left to delete says zero', $delete( $cut ), 0 );
ck( 'a time of zero deletes nothing', array( $delete( 0 ), $alive( $edge ), $alive( $new_a ) ), array( 0, true, true ) );
ck( 'a limit of zero deletes nothing', array( $delete( strtotime( '2027-01-01 00:00:00 UTC' ), 0 ), $alive( $edge ), $alive( $new_a ) ), array( 0, true, true ) );
ck( 'a later cut deletes the rest of the calls, five in all, and still no row of another key', array( $delete( strtotime( '2026-11-01 00:00:00 UTC' ) ), $alive( $edge ), $alive( $new_a ), $alive( $mcp ), $alive( $named ), $alive( $typed_call ), $alive( $old_inst ), $alive( $old_sponsor ), $alive( $old_prog ), $alive( $prog_row ) ), array( 5, false, false, false, false, false, true, true, true, true ) );
ck( 'and the readers of the other keys list their rows as before', array( count( WPCPM_Institution_Audit::entries_for( $I ) ) > 0, count( WPCPM_Institution_Audit::sponsor_entries() ) > 0, $mcp_read( 0 ) ), array( true, true, array() ) );

echo "\n=== House rules ===\n";
ck( 'no em or en dash in the class', preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-institution-audit.php' ) ), 0 );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
