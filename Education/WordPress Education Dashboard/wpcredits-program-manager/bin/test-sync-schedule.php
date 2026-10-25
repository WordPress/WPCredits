<?php
/**
 * The three-hour cycle the Airtable syncs and the Student Duplicate Finder's scan share, and the
 * mentors sync's part in it.
 *
 * These checks lived in the sponsors sync's suite until that sync went in 1.111.0, when the
 * sponsor record moved onto the site. That suite was the one place where every class holding a
 * minute of the cycle was loaded for real, so it was the one place two of them sharing a minute
 * could be caught; the cycle outlived the sync, so the checks have a suite of their own:
 *
 * - **Four distinct minutes.** The students (30), mentors (60) and institutions (90) syncs and the
 *   duplicate scan (150) each take a minute of the one recurrence, so no two of them start in the
 *   same WP-Cron request or against the same Airtable rate limit. Each class's own suite compares
 *   itself with the students run alone; this is the only place all four meet.
 * - **The mentors sync's schedule.** It has no suite of its own, so its recurrence, its minute and
 *   the move off the daily cadence an older version left behind are pinned here, starting from a
 *   request where nothing has registered the recurrence yet.
 * - **The mentors sync's sponsorship row**, the shape the Sponsor Dashboard's mentors card reads,
 *   and nothing more: since 1.111.0 the site holds which mentors a sponsor sponsors, so the sync
 *   reads neither the Mentors table's link to the Sponsors table nor the Sponsors table itself.
 * - **The sponsors module's own dailies stay off the cycle**: the agreement's discard, the
 *   application purge and the sponsor tiers' daily run each keep the plain `daily` recurrence
 *   this rotation never touches; only the syncs moved onto it. The agreement discard's `daily`
 *   recurrence is pinned here (below), the tier run's in bin/test-sponsors-screen.php, and the
 *   purge's wiring (not its recurrence, which is pinned nowhere by name) in
 *   bin/test-sponsor-application.php.
 *
 * Nothing here talks to Airtable: the real client is loaded for the two cell readers the
 * sponsorship row goes through, `flatten()` and `link_ids()`, and nothing else of it is called.
 *
 * Run from the plugin root:  php bin/test-sync-schedule.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']            = array();
$GLOBALS['cron']            = array();
$GLOBALS['cron_recurrence'] = array();
$GLOBALS['filters']         = array();

function __( $s, $d = null ) { return $s; }
function apply_filters( $t, $v ) { return $v; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }

// **Recorded, not dropped.** `wp_schedule_event()` below refuses a recurrence nothing has
// registered, and this plugin's three-hour recurrence exists only while
// `WPCPM_Students_Sync::cron_interval()` is on the `cron_schedules` filter. A stub that threw the
// callback away could not tell a sync that registers the interval before it schedules from one
// that leaves the site with no event at all. Keyed the way core keys a callback, so the same
// static method added twice is one registration.
function add_filter( $hook, $callback = null ) {
	$id = is_array( $callback ) ? $callback[0] . '::' . $callback[1] : (string) $callback;
	$GLOBALS['filters'][ $hook ][ $id ] = $callback;
}

function wp_next_scheduled( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ] : false; }

/**
 * Core's four recurrences plus whatever the `cron_schedules` filter adds.
 *
 * This is the whole reason the filter matters: a recurrence WordPress cannot name here does not
 * exist, however carefully a constant spells it.
 */
function wp_get_schedules() {
	$schedules = array(
		'hourly'     => array( 'interval' => HOUR_IN_SECONDS ),
		'twicedaily' => array( 'interval' => 12 * HOUR_IN_SECONDS ),
		'daily'      => array( 'interval' => DAY_IN_SECONDS ),
		'weekly'     => array( 'interval' => 7 * DAY_IN_SECONDS ),
	);

	$filters = isset( $GLOBALS['filters']['cron_schedules'] ) ? $GLOBALS['filters']['cron_schedules'] : array();
	foreach ( $filters as $callback ) {
		$schedules = call_user_func( $callback, $schedules );
	}

	return $schedules;
}

// **Refuses an unregistered recurrence, the way core does**: `wp_schedule_event()` returns false
// and nothing is queued, which is what the activation request would get from a sync that
// scheduled before it registered the interval.
function wp_schedule_event( $ts, $rec, $hook ) {
	$schedules = wp_get_schedules();

	if ( ! isset( $schedules[ $rec ] ) ) {
		return false;
	}

	$GLOBALS['cron'][ $hook ]            = $ts;
	$GLOBALS['cron_recurrence'][ $hook ] = $rec;
	return true;
}

// Clearing drops the recurrence with the event. Leaving it behind would let a schedule() that only
// cleared, and never rescheduled, still read as being on the right recurrence.
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ], $GLOBALS['cron_recurrence'][ $hook ] ); return 0; }
function wp_get_schedule( $hook ) { return isset( $GLOBALS['cron_recurrence'][ $hook ] ) ? $GLOBALS['cron_recurrence'][ $hook ] : false; }

/**
 * The event as core reports it, which is how a recurrence is read back.
 *
 * An event scheduled before the recurrence was recorded reads as `daily`: that is the state a site
 * upgrading from an earlier version is in, and it is the state the move onto the cycle has to
 * notice.
 */
function wp_get_scheduled_event( $hook ) {
	if ( ! isset( $GLOBALS['cron'][ $hook ] ) ) {
		return false;
	}

	return (object) array(
		'hook'      => $hook,
		'timestamp' => $GLOBALS['cron'][ $hook ],
		'schedule'  => isset( $GLOBALS['cron_recurrence'][ $hook ] ) ? $GLOBALS['cron_recurrence'][ $hook ] : 'daily',
	);
}

$fail  = 0;
$total = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $total;
	++$total;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

// The real classes, every one that holds a minute of the cycle: the offsets only mean anything
// against the real values, and a copied constant would let two of them drift into one minute
// with this suite still green.
require_once __DIR__ . '/../includes/class-wpcpm-airtable.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institutions-sync.php';
require_once __DIR__ . '/../includes/tools/class-wpcpm-duplicates-scan.php';

echo "=== One cycle, four minutes ===\n";

// Since 1.98.2 every Airtable sync runs on the one three-hour recurrence the students sync
// registers, staggered by the minute of the cycle each takes, and the Student Duplicate Finder's
// scan joined it in 1.102.0. Two of them sharing a minute is the failure the offsets exist to
// prevent, and it would show up nowhere else.
$offsets = array(
	WPCPM_Students_Sync::SCHEDULE_OFFSET_MINUTES,
	WPCPM_Mentors_Sync::SCHEDULE_OFFSET_MINUTES,
	WPCPM_Institutions_Sync::SCHEDULE_OFFSET_MINUTES,
	WPCPM_Duplicates_Scan::SCHEDULE_OFFSET_MINUTES,
);

ck( 'the three syncs and the duplicate scan take four distinct minutes inside one three-hour cycle',
	array( count( array_unique( $offsets ) ), max( $offsets ) < 180 ),
	array( 4, true ) );

echo "\n=== The mentors sync's schedule ===\n";

// The mentors sync joined the three-hour cycle in 1.98.2, at +1:00, and it has no suite of its
// own: its schedule was pinned nowhere at all, which is how it stayed daily while four docblocks
// said otherwise. What is held here: the recurrence, the minute, and that the move off daily runs
// once. The cycle's origin is the students sync's next run less its own offset, so every position
// is measured from one point rather than chained off a neighbor.
//
// **With nothing on the `cron_schedules` filter**, which is the activation request exactly:
// `register_activation_hook()` fires where `plugins_loaded` has already run, so nothing has
// registered the interval, and core drops an event whose recurrence it cannot name.
$GLOBALS['cron'][ WPCPM_Students_Sync::CRON_AUTO ]            = 2000000000;
$GLOBALS['cron_recurrence'][ WPCPM_Students_Sync::CRON_AUTO ] = WPCPM_Students_Sync::EVERY_THREE_HOURS;
$cycle = 2000000000 - WPCPM_Students_Sync::SCHEDULE_OFFSET_MINUTES * MINUTE_IN_SECONDS;

WPCPM_Mentors_Sync::schedule();

ck( 'the mentors sync registers the recurrence itself before it schedules', array_key_exists( WPCPM_Students_Sync::EVERY_THREE_HOURS, wp_get_schedules() ), true );
ck( 'the mentors run sits at +1:00 of the three-hour cycle', $GLOBALS['cron'][ WPCPM_Mentors_Sync::CRON_DAILY ], $cycle + 60 * MINUTE_IN_SECONDS );
ck( 'on the three-hour recurrence the students sync registers', wp_get_schedule( WPCPM_Mentors_Sync::CRON_DAILY ), WPCPM_Students_Sync::EVERY_THREE_HOURS );
ck( 'and no daily mentors event is left behind', 'daily' === wp_get_schedule( WPCPM_Mentors_Sync::CRON_DAILY ), false );
ck( 'the mentors offset is read from the class rather than copied', WPCPM_Mentors_Sync::SCHEDULE_OFFSET_MINUTES, 60 );

$placed = $GLOBALS['cron'][ WPCPM_Mentors_Sync::CRON_DAILY ];
WPCPM_Mentors_Sync::schedule();
ck( 'a second call leaves the mentors event where it is', $GLOBALS['cron'][ WPCPM_Mentors_Sync::CRON_DAILY ], $placed );

// **The upgrade path**, and the one every site was on: the mentors run was daily from the day it
// was written, and an event already in the cron array keeps the schedule it was created with.
$GLOBALS['cron'][ WPCPM_Mentors_Sync::CRON_DAILY ]            = 1234;
$GLOBALS['cron_recurrence'][ WPCPM_Mentors_Sync::CRON_DAILY ] = 'daily';

WPCPM_Mentors_Sync::schedule();

ck( 'a daily mentors event left by an earlier version moves onto the three-hour recurrence', wp_get_schedule( WPCPM_Mentors_Sync::CRON_DAILY ), WPCPM_Students_Sync::EVERY_THREE_HOURS );
ck( 'and takes the cycle offset rather than keeping its old moment', $GLOBALS['cron'][ WPCPM_Mentors_Sync::CRON_DAILY ], $cycle + 60 * MINUTE_IN_SECONDS );

$placed = $GLOBALS['cron'][ WPCPM_Mentors_Sync::CRON_DAILY ];
WPCPM_Mentors_Sync::schedule();
ck( 'and the run after the upgrade changes nothing again', array( $GLOBALS['cron'][ WPCPM_Mentors_Sync::CRON_DAILY ], wp_get_schedule( WPCPM_Mentors_Sync::CRON_DAILY ) ), array( $placed, WPCPM_Students_Sync::EVERY_THREE_HOURS ) );

// The tick hook is not on the cycle: it is the resumable run scheduling its own next slice,
// single-event and minutes away, and putting it on a recurrence would start a second run.
$GLOBALS['cron'][ WPCPM_Mentors_Sync::CRON_TICK ] = 2000000030;
WPCPM_Mentors_Sync::unschedule();
ck( 'unschedule() drops both mentors events', array( isset( $GLOBALS['cron'][ WPCPM_Mentors_Sync::CRON_DAILY ] ), isset( $GLOBALS['cron'][ WPCPM_Mentors_Sync::CRON_TICK ] ) ), array( false, false ) );

$GLOBALS['cron']            = array();
$GLOBALS['cron_recurrence'] = array();

echo "\n=== The mentors sync's sponsorship row ===\n";

$mf  = WPCPM_Mentors_Sync::fields();
$row = WPCPM_Mentors_Sync::sponsorship_row( array( $mf['mentor_name'] => 'Ines Example', $mf['mentor_profile'] => 'https://profiles.wordpress.org/ines-example/', $mf['mentor_sponsored'] => 'Yes', $mf['mentor_wants_sponsor'] => 'No', 'Sponsor Company Name' => array( 'recSEED0000000021' ), $mf['mentor_expertise'] => array( 'Core', 'Polyglots' ) ), $mf, 42, 'Active' );
// The link cell is still in the record, as the base still has the column: the row leaves it out
// all the same, because nothing on the site reads it (1.111.0).
ck( 'a sponsored mentor\'s row, with no sponsor link in it', $row, array( 'name' => 'Ines Example', 'profile' => 'https://profiles.wordpress.org/ines-example/', 'status' => 'Active', 'user_id' => 42, 'sponsored' => true, 'wants' => false, 'expertise' => array( 'Core', 'Polyglots' ) ) );
ck( 'and an unsponsored one who wants a sponsor, with nothing else filled', WPCPM_Mentors_Sync::sponsorship_row( array( $mf['mentor_name'] => 'Sam', $mf['mentor_wants_sponsor'] => 'Yes' ), $mf, 0, 'Active' ), array( 'name' => 'Sam', 'profile' => '', 'status' => 'Active', 'user_id' => 0, 'sponsored' => false, 'wants' => true, 'expertise' => array() ) );
ck( 'the option name', WPCPM_Mentors_Sync::OPT_SPONSORSHIP, 'wpcpm_mentors_sponsorship' );

// The two reads of the sponsor record the mentors sync still made: the Mentors table's Sponsor
// Company Name link, requested with every Active mentor and read by nothing, and the Sponsors
// table, read whole in the lookup phase for a map of sponsor names nothing read either. The record
// lives on the site since 1.111.0 and the table is kept only as an archive, so both went.
$mentors_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php' );
ck( 'the sync asks the Mentors table for no sponsor link, and keeps no map of sponsor names',
	array( array_key_exists( 'mentor_sponsor_company', $mf ), strpos( $mentors_src, 'mentor_sponsor_company' ), array_keys( WPCPM_Mentors_Sync::lookups() ), strpos( $mentors_src, "'companies'" ) ),
	array( false, false, array( 'institutions', 'teams' ), false ) );

echo "\n=== Only the syncs moved onto the cycle ===\n";

// The sponsors module's own housekeeping sweeps the site's posts and options rather than reading
// Airtable, so it has no reason to run eight times a day. Read from the source: the class that
// schedules it is not loaded here, and what is pinned is that the recurrence in the code did not
// change.
ck( 'the sponsor agreement discard is still daily',
	false !== strpos( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' ), "'daily', WPCPM_Sponsor_Agreement::CRON_DISCARD" ), true );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
