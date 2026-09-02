<?php
/**
 * Does a start date land in the semester it belongs to, and do the counts add up?
 *
 * The cohort key is read from the date string, not through a timestamp. This suite pins
 * that: the two boundary days must give the same answer whatever timezone PHP has, an
 * impossible date must be "no start date" rather than the next month, and a datetime must
 * be "no start date" so a field type change in the base shows up as an empty cohort here.
 *
 * The participation buckets are checked against the one institution the design spec has a
 * hand-written report for: Krakow University of Economics, 15 rows all in 2026-H1, 8
 * Graduate, 2 Pending graduation, 5 Not moving forward, reported as "15 signed up". So
 * signed_up counts the five who never started, the buckets sum to it, and a SPAM row or a
 * row from another semester is not in it.
 *
 * Run from the plugin root:  php bin/test-cohort.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['today'] = '2026-09-02';

function __( $s, $d = null ) { return $s; }
function apply_filters( $t, $v ) { return $v; }
function wp_date( $f, $t = null ) { return 'Y-m-d' === $f ? $GLOBALS['today'] : gmdate( $f, null === $t ? time() : $t ); }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';

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
 * A roster row with just the two fields participation() reads.
 *
 * @param string $status Airtable status.
 * @param string $start  Start date as the index stores it.
 * @return array
 */
function row( $status, $start ) {
	return array(
		'record_id' => 'rec' . substr( md5( $status . $start . mt_rand() ), 0, 14 ),
		'status'    => $status,
		'start'     => $start,
	);
}

/**
 * The same status on n rows, spread over the dates given so the cohort is not one date.
 *
 * @param string $status Airtable status.
 * @param int    $n      How many rows.
 * @param array  $dates  Dates to cycle through.
 * @return array
 */
function rows( $status, $n, array $dates ) {
	$out = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$out[] = row( $status, $dates[ $i % count( $dates ) ] );
	}
	return $out;
}

/**
 * The buckets other than signed_up, added up.
 *
 * @param array $counts A participation() result.
 * @return int
 */
function bucket_sum( array $counts ) {
	return $counts['graduated'] + $counts['pending'] + $counts['active'] + $counts['withdrawn'] + $counts['not_started'] + $counts['other'];
}

/**
 * Whether a label mentions a season, in any case.
 *
 * @param string $label What label() returned.
 * @return bool
 */
function has_season_word( $label ) {
	return 1 === preg_match( '/\b(spring|summer|autumn|fall|winter)\b/i', (string) $label );
}

$none = WPCPM_Cohort::NONE;

/* ---- key() -------------------------------------------------------------- */

echo "=== key() ===\n";

ck( '30 June is the last day of H1', WPCPM_Cohort::key( '2026-06-30' ), '2026-H1' );
ck( '1 July is the first day of H2', WPCPM_Cohort::key( '2026-07-01' ), '2026-H2' );
ck( '1 January opens H1', WPCPM_Cohort::key( '2026-01-01' ), '2026-H1' );
ck( '31 December closes H2', WPCPM_Cohort::key( '2026-12-31' ), '2026-H2' );
ck( 'the D. Y. Patil stray from 2023 keys to its own semester', WPCPM_Cohort::key( '2023-07-10' ), '2023-H2' );
ck( 'a leap day is a date', WPCPM_Cohort::key( '2024-02-29' ), '2024-H1' );

ck( '31 February is NONE, not March', WPCPM_Cohort::key( '2026-02-31' ), $none );
ck( 'a leap day in a common year is NONE', WPCPM_Cohort::key( '2023-02-29' ), $none );
ck( 'a 13th month is NONE', WPCPM_Cohort::key( '2026-13-01' ), $none );
ck( 'an ISO datetime is NONE', WPCPM_Cohort::key( '2026-07-01T00:00:00.000Z' ), $none );
ck( 'a date with a time is NONE', WPCPM_Cohort::key( '2026-07-01 09:30' ), $none );
ck( 'single-digit month and day are NONE', WPCPM_Cohort::key( '2026-7-1' ), $none );
ck( 'an empty string is NONE', WPCPM_Cohort::key( '' ), $none );
ck( 'null is NONE', WPCPM_Cohort::key( null ), $none );
ck( 'an array is NONE', WPCPM_Cohort::key( array( '2026-07-01' ) ), $none );
ck( 'a timestamp is NONE', WPCPM_Cohort::key( 1782864000 ), $none );
ck( 'surrounding spaces are not a missing date', WPCPM_Cohort::key( ' 2026-07-01 ' ), '2026-H2' );
ck( 'nor is a trailing newline', WPCPM_Cohort::key( "2026-07-01\n" ), '2026-H2' );
ck( 'but a second line after the date is', WPCPM_Cohort::key( "2026-07-01\n2026-07-02" ), $none );

// The docblock's claim: read from the string, so the site's timezone cannot move a boundary
// day into the neighbouring semester. A timestamp taken at local midnight east of UTC is
// still the previous UTC day, which is exactly the misfiling being ruled out.
$zone_before = date_default_timezone_get();
foreach ( array( 'Pacific/Kiritimati', 'America/Los_Angeles', 'Pacific/Pago_Pago' ) as $zone ) {
	date_default_timezone_set( $zone );
	ck( "1 July is H2 with PHP in $zone", WPCPM_Cohort::key( '2026-07-01' ), '2026-H2' );
	ck( "30 June is H1 with PHP in $zone", WPCPM_Cohort::key( '2026-06-30' ), '2026-H1' );
	ck( "1 January is H1 with PHP in $zone", WPCPM_Cohort::key( '2026-01-01' ), '2026-H1' );
}
date_default_timezone_set( $zone_before );

/* ---- is_key() ----------------------------------------------------------- */

echo "\n=== is_key() ===\n";

ck( 'a first-half key', WPCPM_Cohort::is_key( '2026-H1' ), true );
ck( 'a second-half key', WPCPM_Cohort::is_key( '2026-H2' ), true );
ck( 'NONE is a key', WPCPM_Cohort::is_key( $none ), true );
ck( 'a third half is not', WPCPM_Cohort::is_key( '2026-H3' ), false );
ck( 'lower-case h is not', WPCPM_Cohort::is_key( '2026-h1' ), false );
ck( 'a bare year is not', WPCPM_Cohort::is_key( '2026' ), false );
ck( 'a date is not', WPCPM_Cohort::is_key( '2026-07-01' ), false );
ck( 'the empty string is not', WPCPM_Cohort::is_key( '' ), false );
ck( 'a trailing newline is not', WPCPM_Cohort::is_key( "2026-H1\n" ), false );
ck( 'a trailing space is not', WPCPM_Cohort::is_key( '2026-H1 ' ), false );
ck( 'an array from wpcpm_cohort[] is not', WPCPM_Cohort::is_key( array( '2026-H1' ) ), false );
ck( 'an integer is not', WPCPM_Cohort::is_key( 2026 ), false );
ck( 'null is not', WPCPM_Cohort::is_key( null ), false );
ck( 'the word None in another case is not', WPCPM_Cohort::is_key( 'None' ), false );

/* ---- previous() --------------------------------------------------------- */

echo "\n=== previous() ===\n";

ck( 'before 2026-H1 is 2025-H2', WPCPM_Cohort::previous( '2026-H1' ), '2025-H2' );
ck( 'before 2026-H2 is 2026-H1', WPCPM_Cohort::previous( '2026-H2' ), '2026-H1' );
ck( 'before 2000-H1 keeps four digits', WPCPM_Cohort::previous( '2000-H1' ), '1999-H2' );
ck( 'NONE has no predecessor', WPCPM_Cohort::previous( $none ), '' );
ck( 'junk has no predecessor', WPCPM_Cohort::previous( '2026-07-01' ), '' );
ck( 'the empty string has no predecessor', WPCPM_Cohort::previous( '' ), '' );
ck( 'the predecessor is itself a key', WPCPM_Cohort::is_key( WPCPM_Cohort::previous( '2026-H1' ) ), true );

/* ---- current() ---------------------------------------------------------- */

echo "\n=== current() ===\n";

$GLOBALS['today'] = '2026-09-02';
ck( 'today on the site clock keys to 2026-H2', WPCPM_Cohort::current(), '2026-H2' );
$GLOBALS['today'] = '2026-06-30';
ck( 'and the last day of June to 2026-H1', WPCPM_Cohort::current(), '2026-H1' );
$GLOBALS['today'] = '2026-09-02';

/* ---- label() ------------------------------------------------------------ */

echo "\n=== label() ===\n";

ck( 'H1 is the months, spelled out', WPCPM_Cohort::label( '2026-H1' ), 'January to June 2026' );
ck( 'H2 likewise', WPCPM_Cohort::label( '2026-H2' ), 'July to December 2026' );
ck( 'NONE is "No start date"', WPCPM_Cohort::label( $none ), 'No start date' );
ck( 'junk has no label', WPCPM_Cohort::label( '2026-07-01' ), '' );
ck( 'the year is four plain digits, not "2,026"', false === strpos( WPCPM_Cohort::label( '2026-H1' ), ',' ), true );

// Krakow calls February to June the summer semester; a US institution calls it spring.
foreach ( array( '2026-H1', '2026-H2', $none ) as $key ) {
	ck( "no season word in the label for $key", has_season_word( WPCPM_Cohort::label( $key ) ), false );
}

/* ---- range() ------------------------------------------------------------ */

echo "\n=== range() ===\n";

ck( 'H1 runs 1 January to 30 June', WPCPM_Cohort::range( '2026-H1' ), array( 'from' => '2026-01-01', 'to' => '2026-06-30' ) );
ck( 'H2 runs 1 July to 31 December', WPCPM_Cohort::range( '2026-H2' ), array( 'from' => '2026-07-01', 'to' => '2026-12-31' ) );
ck( 'NONE has no range', WPCPM_Cohort::range( $none ), array( 'from' => '', 'to' => '' ) );
ck( 'junk has no range', WPCPM_Cohort::range( 'H1' ), array( 'from' => '', 'to' => '' ) );

// The range and the key agree with each other at both ends.
foreach ( array( '2025-H2', '2026-H1', '2026-H2' ) as $key ) {
	$range = WPCPM_Cohort::range( $key );
	ck( "the first day of $key keys back to it", WPCPM_Cohort::key( $range['from'] ), $key );
	ck( "and so does the last", WPCPM_Cohort::key( $range['to'] ), $key );
}

/* ---- compare() ---------------------------------------------------------- */

echo "\n=== compare() ===\n";

ck( 'an earlier semester sorts first', WPCPM_Cohort::compare( '2025-H2', '2026-H1' ), -1 );
ck( 'a later semester sorts after', WPCPM_Cohort::compare( '2026-H2', '2026-H1' ), 1 );
ck( 'a key equals itself', WPCPM_Cohort::compare( '2026-H1', '2026-H1' ), 0 );
ck( 'NONE sorts after any semester', WPCPM_Cohort::compare( $none, '2099-H2' ), 1 );
ck( 'and any semester before NONE', WPCPM_Cohort::compare( '1999-H1', $none ), -1 );
ck( 'NONE equals NONE', WPCPM_Cohort::compare( $none, $none ), 0 );

$keys = array( '2026-H2', $none, '2025-H2', '2026-H1', '2023-H2' );
usort( $keys, array( 'WPCPM_Cohort', 'compare' ) );
ck( 'usort() gives chronological order with NONE last', $keys, array( '2023-H2', '2025-H2', '2026-H1', '2026-H2', $none ) );

/* ---- participation(): the Krakow report ---------------------------------- */

echo "\n=== participation(): Krakow University of Economics ===\n";

// The dates a February to June semester actually produces: paperwork clears across weeks.
$krakow_dates = array( '2026-02-16', '2026-02-23', '2026-03-02', '2026-03-09', '2026-02-16' );

$krakow = array_merge(
	rows( 'Graduate', 8, $krakow_dates ),
	rows( 'Pending graduation', 2, $krakow_dates ),
	rows( 'Not moving forward', 5, $krakow_dates )
);

$counts = WPCPM_Cohort::participation( $krakow, '2026-H1' );

ck( 'the fixture has 15 rows', count( $krakow ), 15 );
ck( 'signed_up is 15, the five who never started included', $counts['signed_up'], 15 );
ck( 'graduated is 8', $counts['graduated'], 8 );
ck( 'pending is 2, printed separately', $counts['pending'], 2 );
ck( 'not_started is 5', $counts['not_started'], 5 );
ck( 'active is 0', $counts['active'], 0 );
ck( 'withdrawn is 0', $counts['withdrawn'], 0 );
ck( 'other is 0', $counts['other'], 0 );
ck( 'the six buckets sum to signed_up', bucket_sum( $counts ), $counts['signed_up'] );
ck( 'the array has the seven keys in the documented order', array_keys( $counts ), array( 'signed_up', 'graduated', 'pending', 'active', 'withdrawn', 'not_started', 'other' ) );
ck( 'and 2025-H2 holds none of them', WPCPM_Cohort::participation( $krakow, '2025-H2' )['signed_up'], 0 );

// A SPAM row and a row from the previous semester, dropped into the same list.
$with_noise   = $krakow;
$with_noise[] = row( 'SPAM', '2026-03-02' );
$with_noise[] = row( 'Duplicated', '2026-03-02' );
$with_noise[] = row( 'Interested', '2026-03-02' );
$with_noise[] = row( 'Graduate', '2025-10-06' );

$counts = WPCPM_Cohort::participation( $with_noise, '2026-H1' );

ck( 'a SPAM row is not signed up', $counts['signed_up'], 15 );
ck( 'nor is Duplicated or Interested, so the buckets still sum', bucket_sum( $counts ), 15 );
ck( 'a Graduate who started in October is not in 2026-H1', $counts['graduated'], 8 );
ck( 'but is the one row of 2025-H2', WPCPM_Cohort::participation( $with_noise, '2025-H2' ), array(
	'signed_up'   => 1,
	'graduated'   => 1,
	'pending'     => 0,
	'active'      => 0,
	'withdrawn'   => 0,
	'not_started' => 0,
	'other'       => 0,
) );

/* ---- participation(): every bucket, and the ones the base could grow ------ */

echo "\n=== participation(): the bucket rules ===\n";

$mixed = array(
	row( 'In Sensei', '2026-08-03' ),
	row( 'In Sensei 50h', '2026-08-03' ),
	row( 'Developer Track', '2026-08-03' ),
	row( 'Paused', '2026-08-03' ),
	row( 'Dropped out', '2026-08-03' ),
	row( 'Fail', '2026-08-03' ),
	row( 'In Sensei Self-onboarding', '2026-08-03' ),
	row( 'Kishoreganj Polytechnic Institute', '2026-08-03' ),
	row( 'Something the base grows next year', '2026-08-03' ),
	row( '', '2026-08-03' ),
);

$counts = WPCPM_Cohort::participation( $mixed, '2026-H2' );

ck( 'the three tracks and Paused are active', $counts['active'], 4 );
ck( 'Dropped out and Fail are withdrawn', $counts['withdrawn'], 2 );
ck( 'a status no rule names lands in other rather than vanishing', $counts['other'], 4 );
ck( 'an empty status is signed up, in other', $counts['signed_up'], 10 );
ck( 'and the buckets still sum', bucket_sum( $counts ), 10 );

// Status is matched exactly: the loose regex a screen uses for "graduate" is not this.
$loose = array(
	row( 'graduate', '2026-08-03' ),
	row( 'Graduated', '2026-08-03' ),
	row( 'Graduate ', '2026-08-03' ),
);
$counts = WPCPM_Cohort::participation( $loose, '2026-H2' );
ck( 'only "Graduate" spelled exactly is graduated; a trailing space is forgiven, a case or a suffix is not', $counts['graduated'], 1 );
ck( 'the other spellings are counted, in other', $counts['other'], 2 );

// The NONE cohort: rows with no usable date, and only those.
$dateless = array(
	row( 'Not moving forward', '' ),
	row( 'Not moving forward', '' ),
	row( 'Developer Track', '' ),
	row( 'In Sensei', '2026-02-31' ),
	row( 'SPAM', '' ),
	array( 'record_id' => 'recNOSTARTKEY01', 'status' => 'In Sensei' ),
	row( 'Graduate', '2026-03-02' ),
);
$counts = WPCPM_Cohort::participation( $dateless, $none );
ck( 'NONE counts the rows with no usable start date', $counts['signed_up'], 5 );
ck( 'including an impossible date and a row with no start key at all', $counts['active'], 3 );
ck( 'but not the SPAM row', $counts['not_started'], 2 );
ck( 'and not the dated Graduate', $counts['graduated'], 0 );

// Shapes that must not fatal: a key that is not a key, a row that is not a row, no rows.
$zero = array(
	'signed_up'   => 0,
	'graduated'   => 0,
	'pending'     => 0,
	'active'      => 0,
	'withdrawn'   => 0,
	'not_started' => 0,
	'other'       => 0,
);
ck( 'no rows gives zeros', WPCPM_Cohort::participation( array(), '2026-H1' ), $zero );
ck( 'a key that is not a key gives zeros', WPCPM_Cohort::participation( $krakow, '2026' ), $zero );
ck( 'a row that is not an array is skipped', WPCPM_Cohort::participation( array( 'junk', null, 7 ), '2026-H1' ), $zero );
ck( 'a null status is signed up, in other', WPCPM_Cohort::participation( array( row( null, '2026-03-02' ) ), '2026-H1' )['other'], 1 );

/* ---- participation() against the Status choices the base offers ---------- */

echo "\n=== participation(): every Status the base offers has a bucket ===\n";

$fixture  = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/students-table-fields.json' ), true );
$statuses = isset( $fixture['choices']['Status'] ) && is_array( $fixture['choices']['Status'] ) ? $fixture['choices']['Status'] : array();

ck( 'the fixture offers Status choices', count( $statuses ) > 0, true );
ck( 'every NOT_SIGNED_UP entry is a choice the base offers', array_values( array_diff( WPCPM_Cohort::NOT_SIGNED_UP, $statuses ) ), array() );
ck( 'every bucket rule names a choice the base offers',
	array_values( array_diff( array( 'Graduate', 'Pending graduation', 'Paused', 'Dropped out', 'Fail', 'Not moving forward', 'In Sensei', 'In Sensei 50h', 'Developer Track' ), $statuses ) ),
	array() );

$one_each = array();
foreach ( $statuses as $status ) {
	$one_each[] = row( $status, '2026-05-04' );
}

$counts = WPCPM_Cohort::participation( $one_each, '2026-H1' );
$named  = array( 'Graduate', 'Pending graduation', 'Paused', 'Dropped out', 'Fail', 'Not moving forward', 'In Sensei', 'In Sensei 50h', 'Developer Track' );
$rest   = array_values( array_diff( $statuses, $named, WPCPM_Cohort::NOT_SIGNED_UP ) );

ck( 'one row per choice: signed_up is the choices less NOT_SIGNED_UP', $counts['signed_up'], count( $statuses ) - count( WPCPM_Cohort::NOT_SIGNED_UP ) );
ck( 'and the buckets sum to it', bucket_sum( $counts ), $counts['signed_up'] );
ck( 'the choices no rule names are exactly what lands in other', $counts['other'], count( $rest ) );
ck( 'which today is the self-onboarding and the one-institution status', $rest, array( 'In Sensei Self-onboarding', 'Kishoreganj Polytechnic Institute' ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
