<?php
/**
 * WPCPM_Refusal_Meter: the twenty-a-day refusal ceiling and the once-a-day lock.
 *
 * Run: php bin/test-refusal-meter.php
 *
 * @package WPCreditsProgramManager
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

class WP_User {
	public $ID;
	public function __construct( $id ) { $this->ID = (int) $id; }
	public function exists() { return $this->ID > 0; }
}

// The ceiling as a counting map: `claim()` adds and says whether the bucket had room, `count()` reads.
// The window is ignored on purpose: this suite is about the meter's rule, not the clock.
class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) {
		$now = isset( $GLOBALS['buckets'][ $key ] ) ? (int) $GLOBALS['buckets'][ $key ] : 0;
		if ( $now + $amount > $limit ) { return false; }
		$GLOBALS['buckets'][ $key ] = $now + $amount;
		return true;
	}
	public static function count( $key, $window ) { return isset( $GLOBALS['buckets'][ $key ] ) ? (int) $GLOBALS['buckets'][ $key ] : 0; }
	public static function key( ...$parts ) { return implode( ':', $parts ); }
}
class WPCPM_Roles { const CAP_MANAGE = 'wpcpm_manage_program'; }

function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) ); }
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

$GLOBALS['buckets'] = array();
$GLOBALS['manage']  = array( 9 );
$member  = new WP_User( 5 );
$manager = new WP_User( 9 );
$nobody  = new WP_User( 0 );

echo "=== Keys ===\n";
ck( 'the key joins the scope and the stem the way the roster always did', WPCPM_Refusal_Meter::key( 'claim', WPCPM_Refusal_Meter::STEM_REFUSED, $member ), 'claim-refused:5' );
ck( 'and the lock key the same way', WPCPM_Refusal_Meter::key( 'sponsor', WPCPM_Refusal_Meter::STEM_LOCKED, $member ), 'sponsor-locked:5' );
ck( 'twenty a day, the roster\'s ceiling', WPCPM_Refusal_Meter::PER_DAY, 20 );

echo "\n=== Who is metered ===\n";
ck( 'a manager is never metered', WPCPM_Refusal_Meter::refuse( 'claim', $manager ), false );
ck( 'and never locked', WPCPM_Refusal_Meter::is_locked( 'claim', $manager ), false );
ck( 'nobody is not metered', WPCPM_Refusal_Meter::refuse( 'claim', $nobody ), false );
ck( 'nor is a non-user', WPCPM_Refusal_Meter::refuse( 'claim', null ), false );
ck( 'no bucket was touched by any of them', $GLOBALS['buckets'], array() );

echo "\n=== The day fills ===\n";
$locked_at = array();
for ( $i = 1; $i <= 22; $i++ ) {
	if ( WPCPM_Refusal_Meter::refuse( 'claim', $member ) ) {
		$locked_at[] = $i;
	}
}
ck( 'nineteen refusals do not lock; the twentieth does; the lock is recorded once', $locked_at, array( 20 ) );
ck( 'after the day is full the account reads as locked', WPCPM_Refusal_Meter::is_locked( 'claim', $member ), true );
ck( 'the refusal bucket stopped at the ceiling', $GLOBALS['buckets']['claim-refused:5'], 20 );
ck( 'the lock bucket holds one', $GLOBALS['buckets']['claim-locked:5'], 1 );

echo "\n=== Scopes are separate ===\n";
ck( 'the same account is not locked in another scope', WPCPM_Refusal_Meter::is_locked( 'sponsor', $member ), false );
ck( 'and a refusal there starts its own count', WPCPM_Refusal_Meter::refuse( 'sponsor', $member ), false );
ck( 'in its own bucket', $GLOBALS['buckets']['sponsor-refused:5'], 1 );

echo "\n=== Reading a set of accounts ===\n";
$other = new WP_User( 6 );
ck( 'locked_among() keeps the locked accounts and drops the rest', WPCPM_Refusal_Meter::locked_among( 'claim', array( $member, $other, $manager, 'not a user' ) ), array( $member ) );
ck( 'and answers nobody for an empty set', WPCPM_Refusal_Meter::locked_among( 'claim', array() ), array() );

echo "\n=== House rules ===\n";
$src = file_get_contents( __DIR__ . '/../includes/class-wpcpm-refusal-meter.php' );
ck( 'no em or en dash in the class', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'the roster delegates its meter rather than keeping a copy', preg_match_all( '/WPCPM_Refusal_Meter::/', file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-institution-roster.php' ) ) >= 3, true );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', 19 );
exit( $fail ? 1 : 0 );
