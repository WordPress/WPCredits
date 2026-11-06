<?php
/**
 * The check the mentors and students syncs make before they take anybody's role (LOG-01, 1.123.58).
 *
 * A read of no records, or of under half as many as there are linked accounts, stops the run before
 * its revoke step; exactly half goes on; nothing linked is nothing to take; a run an Administrator
 * accepted goes on. The stop's sentence names both counts, each with its own plural.
 *
 * Run from the plugin root:  php bin/test-sync-guard.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code, $message, $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals ); }

require_once dirname( __DIR__ ) . '/includes/modules/class-wpcpm-sync-guard.php';

$fail = 0;
function ck( $l, $a, $e ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $l . "\n";
	if ( ! $ok ) { echo '       exp: ' . var_export( $e, true ) . '  got: ' . var_export( $a, true ) . "\n"; }
}

function outcome( $result ) {
	return true === $result ? 'goes on' : ( is_wp_error( $result ) ? $result->get_error_code() : 'something else' );
}

ck( 'a read of as many as are linked goes on', outcome( WPCPM_Sync_Guard::check( 'mentors', 140, 140 ) ), 'goes on' );
ck( 'a read of more than are linked goes on', outcome( WPCPM_Sync_Guard::check( 'mentors', 150, 140 ) ), 'goes on' );
ck( 'a read of exactly half goes on', outcome( WPCPM_Sync_Guard::check( 'mentors', 70, 140 ) ), 'goes on' );
ck( 'one under half stops', outcome( WPCPM_Sync_Guard::check( 'mentors', 69, 140 ) ), WPCPM_Sync_Guard::CODE_FELL );
ck( 'and half of an odd count is held to the same rule: 50 of 101 stops, 51 goes on', array( outcome( WPCPM_Sync_Guard::check( 'students', 50, 101 ) ), outcome( WPCPM_Sync_Guard::check( 'students', 51, 101 ) ) ), array( WPCPM_Sync_Guard::CODE_FELL, 'goes on' ) );
ck( 'a read of nothing stops, with its own code', outcome( WPCPM_Sync_Guard::check( 'students', 0, 1 ) ), WPCPM_Sync_Guard::CODE_EMPTY );
ck( 'nothing linked is nothing to take: a first run, or a site with no accounts yet, goes on whatever it read', array( outcome( WPCPM_Sync_Guard::check( 'mentors', 0, 0 ) ), outcome( WPCPM_Sync_Guard::check( 'mentors', 5, 0 ) ) ), array( 'goes on', 'goes on' ) );
ck( 'a run an Administrator accepted goes on, even on a read of nothing', array( outcome( WPCPM_Sync_Guard::check( 'mentors', 0, 140, true ) ), outcome( WPCPM_Sync_Guard::check( 'students', 3, 300, true ) ) ), array( 'goes on', 'goes on' ) );
ck( 'a negative count, which no query answers, reads as none', outcome( WPCPM_Sync_Guard::check( 'mentors', -4, 10 ) ), WPCPM_Sync_Guard::CODE_EMPTY );

$fell = WPCPM_Sync_Guard::check( 'mentors', 12, 140 );
ck( 'a stop carries whose sync it is and both counts, for the Sync tab', $fell->get_error_data(), array( 'who' => 'mentors', 'read' => 12, 'linked' => 140 ) );
ck( 'and its sentence names both counts and says every account was kept',
	$fell->get_error_message(),
	'Airtable returned 12 mentors. That is under half of the 140 accounts linked to a mentor\'s record, so the mentors sync stopped before taking anyone\'s Mentor role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.' );

ck( 'a read of nothing says so, and the singular reads as one',
	WPCPM_Sync_Guard::check( 'students', 0, 1 )->get_error_message(),
	'Airtable returned no current students. That is under half of the 1 account linked to a student\'s record, so the students sync stopped before taking anyone\'s Student role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.' );

ck( 'one record read is said in the singular', 0 === strpos( WPCPM_Sync_Guard::check( 'students', 1, 300 )->get_error_message(), 'Airtable returned 1 current student. That is under half of the 300 accounts' ), true );

ck( 'the two stops are known as stops, and nothing else is', array( WPCPM_Sync_Guard::is_stop( $fell ), WPCPM_Sync_Guard::is_stop( WPCPM_Sync_Guard::check( 'students', 0, 9 ) ), WPCPM_Sync_Guard::is_stop( new WP_Error( 'wpcpm_airtable_error', 'x' ) ), WPCPM_Sync_Guard::is_stop( true ) ), array( true, true, false, false ) );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
