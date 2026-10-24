<?php
/**
 * Does a date print in the site's own format, on the day it names, whatever the site's clock?
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **A stored day is the day it names.** `2026-09-03` carries no time zone, so the reading must
 *   not give it one: turned into a time at midnight and printed on a site west of UTC it is
 *   the 2nd, and on a site east of it a day can start early. The suite runs the day under
 *   Los Angeles and under Auckland, on the clock changes that fall in each place, and with a
 *   stand-in for `wp_date()` that moves the time as core does, so the wrong reading would be
 *   seen failing here and not only in a browser on the other side of the date line.
 * - A post's date (`2026-09-03 23:59:59`) is the day its first ten characters name.
 * - An integer is a moment, and a moment is shown on the site's clock: that is a different
 *   day from the stored one for the same number, and the suite says so.
 * - Whatever cannot be read gives an empty string, so a caller can fall back to its own words
 *   instead of printing a date it made up.
 * - The format is the site's `date_format`, and `time_format` after it for a moment.
 *
 * Run from the plugin root:  php bin/test-dates.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

// As WordPress sets it before any plugin loads: a bare `strtotime()` reads in UTC.
date_default_timezone_set( 'UTC' );

$GLOBALS['opts']    = array( 'date_format' => 'F j, Y', 'time_format' => 'g:i a' );
$GLOBALS['site_tz'] = 'UTC';

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }

/** The site's time zone, as the Settings screen holds it. */
function wp_timezone() { return new DateTimeZone( $GLOBALS['site_tz'] ); }

/**
 * Core's `wp_date()` minus the translation of month names: a time shown on a clock.
 *
 * @param string            $format   A PHP date format.
 * @param int|null          $time     Unix time; now when null.
 * @param DateTimeZone|null $timezone The clock; the site's when null.
 * @return string
 */
function wp_date( $format, $time = null, $timezone = null ) {
	$timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
	$moment   = new DateTimeImmutable( '@' . ( null === $time ? time() : (int) $time ) );

	return $moment->setTimezone( $timezone )->format( $format );
}

/**
 * Core's `date_i18n()` for a timestamp that is not a moment: the wall clock a UTC `strtotime()`
 * read off a date with no zone, set down again on the site's own clock. Core's comment on this
 * branch calls the input "timestamp with offset"; the mentor card's date range relies on it.
 *
 * @param string   $format A PHP date format.
 * @param int|bool $stamp  The timestamp.
 * @return string
 */
function date_i18n( $format, $stamp = false ) {
	$zone  = wp_timezone();
	$local = date_create( gmdate( 'Y-m-d H:i:s', (int) $stamp ), $zone );

	return wp_date( $format, $local->getTimestamp(), $zone );
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';

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

/** Run one reading under a site time zone and put the zone back. */
function under( $zone, $call ) {
	$was                = $GLOBALS['site_tz'];
	$GLOBALS['site_tz'] = $zone;
	$out                = $call();
	$GLOBALS['site_tz'] = $was;

	return $out;
}

echo "=== The stand-in moves a time as core does ===\n";

// If the stand-in could not show the wrong reading, the next section would pass for any code.
$naive = static function () {
	return wp_date( 'F j, Y', strtotime( '2026-09-03' ) );
};

ck( 'a stored day turned into a time shows the day before in Los Angeles', under( 'America/Los_Angeles', $naive ), 'September 2, 2026' );
ck( 'and the same day in Auckland', under( 'Pacific/Auckland', $naive ), 'September 3, 2026' );
ck( 'while the one core route for a timestamp with no zone keeps it in Los Angeles', under( 'America/Los_Angeles', static function () { return date_i18n( 'F j, Y', strtotime( '2026-09-03' ) ); } ), 'September 3, 2026' );

echo "\n=== A stored day is the day it names, west of UTC and east of it ===\n";

foreach ( array( 'UTC', 'America/Los_Angeles', 'Pacific/Auckland', 'Pacific/Kiritimati', 'Pacific/Pago_Pago' ) as $zone ) {
	ck( "2026-09-03 reads September 3, 2026 on a site set to $zone", under( $zone, static function () { return WPCPM_Dates::day( '2026-09-03' ); } ), 'September 3, 2026' );
}

// The days a clock changes in the places above: the day Los Angeles springs forward and falls
// back, and the day Auckland springs forward.
ck( 'the day Los Angeles springs forward', under( 'America/Los_Angeles', static function () { return WPCPM_Dates::day( '2026-03-08' ); } ), 'March 8, 2026' );
ck( 'the day Los Angeles falls back', under( 'America/Los_Angeles', static function () { return WPCPM_Dates::day( '2026-11-01' ); } ), 'November 1, 2026' );
ck( 'the day Auckland springs forward', under( 'Pacific/Auckland', static function () { return WPCPM_Dates::day( '2026-09-27' ); } ), 'September 27, 2026' );
ck( 'the last day of a leap year', under( 'America/Los_Angeles', static function () { return WPCPM_Dates::day( '2028-12-31' ); } ), 'December 31, 2028' );
ck( 'a leap day', under( 'Pacific/Auckland', static function () { return WPCPM_Dates::day( '2028-02-29' ); } ), 'February 29, 2028' );

echo "\n=== A post's date is the day its first ten characters name ===\n";

ck( 'late on the day, west of UTC', under( 'America/Los_Angeles', static function () { return WPCPM_Dates::day( '2026-09-03 23:59:59' ); } ), 'September 3, 2026' );
ck( 'early on the day, east of UTC', under( 'Pacific/Auckland', static function () { return WPCPM_Dates::day( '2026-09-03 00:00:01' ); } ), 'September 3, 2026' );
ck( 'an ISO stamp is the day it starts with', under( 'America/Los_Angeles', static function () { return WPCPM_Dates::day( '2026-09-03T23:30:00+00:00' ); } ), 'September 3, 2026' );
ck( 'a day with a space around it', WPCPM_Dates::day( " 2026-09-03 \n" ), 'September 3, 2026' );

echo "\n=== An integer is a moment on the site's clock ===\n";

$moment = gmmktime( 3, 30, 0, 9, 3, 2026 );

ck( '03:30 UTC on September 3 is the 2nd in Los Angeles', under( 'America/Los_Angeles', static function () use ( $moment ) { return WPCPM_Dates::day( $moment ); } ), 'September 2, 2026' );
ck( 'and the 3rd in Auckland, in the afternoon', under( 'Pacific/Auckland', static function () use ( $moment ) { return WPCPM_Dates::day( $moment ); } ), 'September 3, 2026' );
ck( 'and the 3rd in UTC', WPCPM_Dates::day( $moment ), 'September 3, 2026' );
ck( 'a moment with its time, in the site\'s two formats, joined by a space', under( 'America/Los_Angeles', static function () use ( $moment ) { return WPCPM_Dates::moment( $moment ); } ), 'September 2, 2026 8:30 pm' );
ck( 'the same moment in Auckland', under( 'Pacific/Auckland', static function () use ( $moment ) { return WPCPM_Dates::moment( $moment ); } ), 'September 3, 2026 3:30 pm' );
ck( 'for the same evening the stored day does not move and the moment does', array(
	under( 'America/Los_Angeles', static function () { return WPCPM_Dates::day( '2026-09-03' ); } ),
	under( 'America/Los_Angeles', static function () use ( $moment ) { return WPCPM_Dates::day( $moment ); } ),
), array( 'September 3, 2026', 'September 2, 2026' ) );

echo "\n=== What cannot be read gives nothing ===\n";

foreach ( array(
	'an empty string'                => '',
	'spaces'                         => '   ',
	'not-a-date'                     => 'not-a-date',
	'an impossible day, not the next month' => '2026-02-31',
	'a month that is not one'        => '2026-13-01',
	'zeros'                          => '0000-00-00',
	'a date with a digit too many'   => '2026-09-031',
	'a day written the other way'    => '03-09-2026',
	'null'                           => null,
	'zero'                           => 0,
	'a negative time'                => -86400,
	'an array'                       => array( '2026-09-03' ),
	'false'                          => false,
) as $what => $value ) {
	ck( "$what reads as nothing", WPCPM_Dates::day( $value ), '' );
}

ck( 'a moment of zero is nothing', WPCPM_Dates::moment( 0 ), '' );
ck( 'and so is one that is not a number', WPCPM_Dates::moment( '' ), '' );
ck( 'and a negative one', WPCPM_Dates::moment( -5 ), '' );

echo "\n=== The format is the site's ===\n";

$GLOBALS['opts']['date_format'] = 'd/m/Y';
$GLOBALS['opts']['time_format'] = 'H:i';

ck( 'a day follows date_format', WPCPM_Dates::day( '2026-09-03' ), '03/09/2026' );
ck( 'a moment follows date_format and then time_format', under( 'Pacific/Auckland', static function () use ( $moment ) { return WPCPM_Dates::moment( $moment ); } ), '03/09/2026 15:30' );

$GLOBALS['opts']['date_format'] = 'j M Y';

ck( 'a changed setting is read again on the next call', WPCPM_Dates::day( '2026-09-03' ), '3 Sep 2026' );

// A site whose options were deleted still prints a date in WordPress's own defaults.
unset( $GLOBALS['opts']['date_format'], $GLOBALS['opts']['time_format'] );

ck( 'with no date_format the day is WordPress\'s default format', WPCPM_Dates::day( '2026-09-03' ), 'September 3, 2026' );
ck( 'and a moment is WordPress\'s two defaults', under( 'Pacific/Auckland', static function () use ( $moment ) { return WPCPM_Dates::moment( $moment ); } ), 'September 3, 2026 3:30 pm' );

echo "\n=== The plugin loads it ===\n";

$main = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php' );
ck( 'the main file requires the class beside the cohort', false !== strpos( $main, "require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';" ), true );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
