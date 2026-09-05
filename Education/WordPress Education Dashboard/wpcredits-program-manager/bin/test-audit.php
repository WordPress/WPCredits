<?php
/**
 * WPCPM_Institution_Audit: one log, two keys (institution and sponsor).
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
function get_post_time( $f, $gmt, $post ) { return 1757000000 + (int) $post->ID; }
function add_action() {}
function register_post_type() {}

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['next']  = 100;
function wp_insert_post( array $a, $wp_error = false ) {
	$id = $GLOBALS['next']++;
	$a['ID'] = $id;
	$GLOBALS['posts'][ $id ] = new WP_Post( $a );
	return $id;
}
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ $id ][ $k ] = $v; return true; }
function get_post_meta( $id, $k, $single = false ) { return isset( $GLOBALS['pmeta'][ $id ][ $k ] ) ? $GLOBALS['pmeta'][ $id ][ $k ] : ''; }
function wp_delete_post( $id ) { unset( $GLOBALS['posts'][ $id ], $GLOBALS['pmeta'][ $id ] ); }
// Enough of get_posts() for the readers: post type, status, numberposts, a meta_query of
// `key`+`value` or `key`+`compare EXISTS` clauses (AND), newest ID first (the readers order by
// date then ID, and here every row shares the date).
function get_posts( array $args ) {
	$out = array();
	$ids = array_keys( $GLOBALS['posts'] );
	rsort( $ids );
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
		if ( $ok ) { $out[] = $post; }
		if ( isset( $args['numberposts'] ) && $args['numberposts'] > 0 && count( $out ) >= $args['numberposts'] ) { break; }
	}
	return $out;
}

require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-audit.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
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

echo "\n=== House rules ===\n";
ck( 'no em or en dash in the class', preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-institution-audit.php' ) ), 0 );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', 21 );
exit( $fail ? 1 : 0 );
