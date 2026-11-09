<?php
/**
 * When a student was last active, and how (student automation, first line).
 *
 * What this pins, and why:
 *
 * - One answer for "when was this student last active": the latest of sign-in, visit, any live call
 *   or group session (booking time, or its start once that has passed), the site's report-save stamp,
 *   Airtable's report-edited time and the "we've already talked" mark.
 * - A trashed call is not activity; a cancelled call must not keep a student looking busy.
 * - The clock start, the later of the account's registration and the program's start date, answers
 *   whenever it is later than every activity, not only when there is none (spec 2.1: "so nobody is
 *   flagged in their first two weeks"): a sign-in before a track that starts in 3 days reads 0 days,
 *   and a student back on an old account reads the days since the new track started. An unparsable
 *   start is ignored; a start still ahead holds the clock at 0 days.
 * - Whole days from a time are one rule (`days_since()`), so a caller that already holds `last()`
 *   never reads it a second time.
 *
 * Run from the plugin root:  php bin/test-student-activity.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['umeta']    = array();
$GLOBALS['users']    = array();
$GLOBALS['calls']    = array();
$GLOBALS['programs'] = array();

function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['umeta'][ $id ][ $key ] = $value; return true; }
function get_userdata( $id ) { return isset( $GLOBALS['users'][ $id ] ) ? (object) array( 'user_registered' => $GLOBALS['users'][ $id ] ) : false; }

// Stand-ins for the two classes whose answers this class reads.
class WPCPM_Sign_Ins {
	const META_SIGN_IN = 'wpcpm_last_sign_in';
	const META_VISIT   = 'wpcpm_last_visit';
}
class WPCPM_Mentor_Calls {
	public static function for_student( $id, $upcoming = true ) { return isset( $GLOBALS['calls'][ $id ] ) ? $GLOBALS['calls'][ $id ] : array(); }
	public static function details( $call ) { return array( 'booked' => $call->booked, 'start' => $call->start ); }
}
class WPCPM_Students_Sync {
	public static function get_program( $id ) { return isset( $GLOBALS['programs'][ $id ] ) ? $GLOBALS['programs'][ $id ] : array(); }
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-activity.php';

$fail = 0;
function ck( $label, $actual, $expected = true ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		++$fail;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
	}
}

function call( $booked, $start, $status = 'private' ) {
	return (object) array( 'post_status' => $status, 'booked' => $booked, 'start' => $start );
}
function reset_all() {
	$GLOBALS['umeta']    = array();
	$GLOBALS['users']    = array();
	$GLOBALS['calls']    = array();
	$GLOBALS['programs'] = array();
}

$T = 1800000000;
$D = DAY_IN_SECONDS;

echo "=== The latest of everything ===\n";
reset_all();
$GLOBALS['umeta'][1] = array(
	WPCPM_Sign_Ins::META_SIGN_IN         => (string) ( $T - 20 * $D ),
	WPCPM_Sign_Ins::META_VISIT           => (string) ( $T - 3 * $D ),
	WPCPM_Student_Activity::META_REPORT  => (string) ( $T - 10 * $D ),
);
ck( 'latest_wins', WPCPM_Student_Activity::last( 1, $T ), array( 'at' => $T - 3 * $D, 'kind' => 'visit' ) );

reset_all();
$GLOBALS['calls'][1] = array( call( $T - 30 * $D, $T - 2 * $D ) );
ck( 'call_counts_booking_and_held: a held call counts at its start', WPCPM_Student_Activity::last( 1, $T ), array( 'at' => $T - 2 * $D, 'kind' => 'call' ) );
$GLOBALS['calls'][1] = array( call( $T - 1 * $D, $T + 5 * $D ) );
ck( 'call_counts_booking_and_held: a call still ahead counts at its booking', WPCPM_Student_Activity::last( 1, $T ), array( 'at' => $T - 1 * $D, 'kind' => 'call' ) );

$GLOBALS['calls'][1] = array( call( $T - 1 * $D, $T - 1 * $D, 'trash' ) );
$GLOBALS['users'][1] = gmdate( 'Y-m-d H:i:s', $T - 40 * $D );
ck( 'trashed_call_ignored', WPCPM_Student_Activity::last( 1, $T )['kind'], 'start' );

reset_all();
$GLOBALS['umeta'][1][ WPCPM_Student_Activity::META_TALKED ] = array( 'at' => $T - 1 * $D, 'by' => 7 );
ck( 'talked_counts', WPCPM_Student_Activity::last( 1, $T ), array( 'at' => $T - 1 * $D, 'kind' => 'talked' ) );

reset_all();
$GLOBALS['umeta'][1] = array(
	WPCPM_Sign_Ins::META_VISIT           => (string) ( $T - 9 * $D ),
	WPCPM_Student_Activity::META_EDITED  => (string) ( $T - 4 * $D ),
);
ck( 'edited_counts', WPCPM_Student_Activity::last( 1, $T ), array( 'at' => $T - 4 * $D, 'kind' => 'edited' ) );

echo "\n=== Nothing at all: the clock start ===\n";
reset_all();
$registered          = $T - 40 * $D;
$GLOBALS['users'][1] = gmdate( 'Y-m-d H:i:s', $registered );
$GLOBALS['programs'][1] = array( 'start' => '2026-10-01' );
$parsed              = strtotime( '2026-10-01 UTC' );
ck( 'nothing_gives_start: the later of registration and the start date', WPCPM_Student_Activity::last( 1, $T ), array( 'at' => max( $registered, $parsed ), 'kind' => 'start' ) );

$GLOBALS['programs'][1] = array( 'start' => 'soon' );
ck( 'start_unparsable_ignored', WPCPM_Student_Activity::clock_start( 1 ), $registered );

$GLOBALS['programs'][1] = array( 'start' => gmdate( 'Y-m-d', $T + 3 * $D ) );
ck( 'start_in_future_holds_clock', WPCPM_Student_Activity::quiet_days( 1, $T ), 0 );

echo "\n=== The clock start is a floor, not only a fallback (final review, Critical 1) ===\n";
// A cohort student: account made and signed in once 20 days ago, when invited; the track starts in 3 days.
reset_all();
$GLOBALS['users'][1]    = gmdate( 'Y-m-d H:i:s', $T - 20 * $D );
$GLOBALS['umeta'][1]    = array( WPCPM_Sign_Ins::META_SIGN_IN => (string) ( $T - 20 * $D ) );
$GLOBALS['programs'][1] = array( 'start' => gmdate( 'Y-m-d', $T + 3 * $D ) );
ck( 'start_after_activity_wins: a sign-in before a start still ahead gives 0 quiet days', WPCPM_Student_Activity::quiet_days( 1, $T ), 0 );
ck( 'and the answer is the start, still ahead', WPCPM_Student_Activity::last( 1, $T ), array( 'at' => strtotime( gmdate( 'Y-m-d', $T + 3 * $D ) . ' UTC' ), 'kind' => 'start' ) );

// A student back on an old account: last signed in 200 days ago, the new track started 2 days ago.
reset_all();
$GLOBALS['users'][2]    = gmdate( 'Y-m-d H:i:s', $T - 400 * $D );
$GLOBALS['umeta'][2]    = array( WPCPM_Sign_Ins::META_SIGN_IN => (string) ( $T - 200 * $D ) );
$GLOBALS['programs'][2] = array( 'start' => gmdate( 'Y-m-d', $T - 2 * $D ) );
ck( 'returning_student_counts_from_new_start: the days since the new track started, not since the old sign-in', array( WPCPM_Student_Activity::quiet_days( 2, $T ), WPCPM_Student_Activity::last( 2, $T )['kind'] ), array( 2, 'start' ) );

// Activity after the start is the answer, as before.
$GLOBALS['umeta'][2][ WPCPM_Sign_Ins::META_VISIT ] = (string) ( $T - 1 * $D );
ck( 'activity after the start still wins', WPCPM_Student_Activity::last( 2, $T ), array( 'at' => $T - 1 * $D, 'kind' => 'visit' ) );

// An account made after the last activity it carries (an Airtable edit before the account existed).
reset_all();
$GLOBALS['users'][3] = gmdate( 'Y-m-d H:i:s', $T - 5 * $D );
$GLOBALS['umeta'][3] = array( WPCPM_Student_Activity::META_EDITED => (string) ( $T - 30 * $D ) );
ck( 'an account made after its last activity counts from the account', array( WPCPM_Student_Activity::quiet_days( 3, $T ), WPCPM_Student_Activity::last( 3, $T )['kind'] ), array( 5, 'start' ) );

echo "\n=== Whole days from a time (one rule for every caller) ===\n";
ck( 'days_since counts whole days, and 0 for a time at or after now', array(
	WPCPM_Student_Activity::days_since( $T - 14 * $D - HOUR_IN_SECONDS, $T ),
	WPCPM_Student_Activity::days_since( $T - 1, $T ),
	WPCPM_Student_Activity::days_since( $T, $T ),
	WPCPM_Student_Activity::days_since( $T + 3 * $D, $T ),
), array( 14, 0, 0, 0 ) );

echo "\n=== Each kind's time, and the answer from them (Release B, the notice's recheck) ===\n";
// The Student Report Card's notice asks the same question for one student with the sign-in and the visit of
// this very return left out, so the kinds are handed out one by one and folded by the one rule.
reset_all();
$GLOBALS['users'][1] = gmdate( 'Y-m-d H:i:s', $T - 400 * $D );
$GLOBALS['umeta'][1] = array(
	WPCPM_Sign_Ins::META_SIGN_IN        => (string) ( $T - 1 * $D ),
	WPCPM_Sign_Ins::META_VISIT          => (string) ( $T - 1 * $D ),
	WPCPM_Student_Activity::META_REPORT => (string) ( $T - 20 * $D ),
);
$GLOBALS['calls'][1] = array( call( $T - 30 * $D, $T - 25 * $D ) );
$has_times = method_exists( 'WPCPM_Student_Activity', 'times' ) && method_exists( 'WPCPM_Student_Activity', 'last_of' );
ck( 'times_lists_each_kind: every kind of activity, each at its own time, 0 for none', $has_times ? WPCPM_Student_Activity::times( 1, $T ) : 'missing', array(
	'sign_in' => $T - 1 * $D,
	'visit'   => $T - 1 * $D,
	'report'  => $T - 20 * $D,
	'edited'  => 0,
	'talked'  => 0,
	'call'    => $T - 25 * $D,
) );
ck( 'last_of_is_last: folded by the one rule they give what last() gives, and without the sign-in and the visit, the next latest', $has_times ? array(
	WPCPM_Student_Activity::last_of( 1, WPCPM_Student_Activity::times( 1, $T ) ) === WPCPM_Student_Activity::last( 1, $T ),
	WPCPM_Student_Activity::last_of( 1, array_merge( WPCPM_Student_Activity::times( 1, $T ), array( 'sign_in' => 0, 'visit' => 0 ) ) ),
) : 'missing', array( true, array( 'at' => $T - 20 * $D, 'kind' => 'report' ) ) );

echo "\n=== Where the clock start came from (Release B, the card's label) ===\n";
reset_all();
$GLOBALS['users'][1]    = gmdate( 'Y-m-d H:i:s', $T - 40 * $D );
$GLOBALS['programs'][1] = array( 'start' => gmdate( 'Y-m-d', $T - 20 * $D ) );
$GLOBALS['users'][2]    = gmdate( 'Y-m-d H:i:s', $T - 40 * $D );
$GLOBALS['programs'][2] = array( 'start' => gmdate( 'Y-m-d', $T - 60 * $D ) );
$GLOBALS['users'][3]    = gmdate( 'Y-m-d H:i:s', $T - 40 * $D );
$GLOBALS['programs'][3] = array( 'start' => 'soon' );
$GLOBALS['users'][4]    = gmdate( 'Y-m-d H:i:s', $T - 40 * $D );
ck( 'start_from: the track when its start is the later date, the account otherwise (an earlier start, an unparsable one, none)', method_exists( 'WPCPM_Student_Activity', 'clock_start_from' ) ? array(
	WPCPM_Student_Activity::clock_start_from( 1 ),
	WPCPM_Student_Activity::clock_start_from( 2 ),
	WPCPM_Student_Activity::clock_start_from( 3 ),
	WPCPM_Student_Activity::clock_start_from( 4 ),
) : 'missing', array( 'track', 'account', 'account', 'account' ) );

echo "\n=== Whole days, and the stamp ===\n";
reset_all();
$GLOBALS['umeta'][1][ WPCPM_Sign_Ins::META_SIGN_IN ] = (string) ( $T - 14 * $D - HOUR_IN_SECONDS );
ck( 'quiet_days_whole', WPCPM_Student_Activity::quiet_days( 1, $T ), 14 );

WPCPM_Student_Activity::stamp_report( 5, 1000 );
ck( 'stamp_report_writes_meta', (string) $GLOBALS['umeta'][5][ WPCPM_Student_Activity::META_REPORT ], '1000' );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
