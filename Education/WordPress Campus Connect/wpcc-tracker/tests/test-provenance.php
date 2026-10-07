<?php
/**
 * Branch coverage for WPCCT_Render::provenance(), the line that tells a reader
 * how old the numbers are.
 *
 * This is the one piece of rendering worth testing in isolation, because it has
 * four outcomes and the wrong one is not a visible error: it is a plausible
 * date over stale data. WordPress is stubbed rather than loaded, so this still
 * runs as plain PHP from a clean checkout.
 *
 * Required by run-tests.php, which must already have loaded harness.php.
 *
 * @package WPCC_Tracker
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/fake-wp/' );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['wpcct_stub_blob'] = array();

if ( ! function_exists( '__' ) ) {
	/**
	 * Stub: return the string unchanged.
	 *
	 * @param string $text   Text.
	 * @param string $domain Unused.
	 * @return string
	 */
	function __( $text, $domain = null ) { // phpcs:ignore
		return $text;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stub: only date_format is read by provenance().
	 *
	 * @param string $key     Option name.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) { // phpcs:ignore
		return 'date_format' === $key ? 'j F Y' : $default;
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	/**
	 * Stub: format in UTC, so the assertions do not depend on a site timezone.
	 *
	 * @param string $format Date format.
	 * @param int    $ts     Timestamp.
	 * @return string
	 */
	function wp_date( $format, $ts = null ) { // phpcs:ignore
		return gmdate( $format, null === $ts ? time() : $ts );
	}
}

if ( ! class_exists( 'WPCCT_Sync' ) ) {
	/**
	 * Stub standing in for the real sync, which provenance() only asks for data.
	 */
	class WPCCT_Sync { // phpcs:ignore
		/**
		 * Return whatever the current test case set.
		 *
		 * @return array
		 */
		public static function data() {
			return $GLOBALS['wpcct_stub_blob'];
		}
	}
}

require __DIR__ . '/../includes/class-wpcct-render.php';

/**
 * Set the blob provenance() will see and return the rendered line.
 *
 * @param array $blob Stored data blob.
 * @return string
 */
function wpcct_prov( array $blob ) {
	$GLOBALS['wpcct_stub_blob'] = $blob;
	return WPCCT_Render::provenance();
}

$iso = function ( $days_ago ) {
	return gmdate( 'c', time() - ( $days_ago * DAY_IN_SECONDS ) );
};

// 1. The seed file renders before any sync has run.
t_eq(
	wpcct_prov( array( 'seed' => true ) ),
	'Seed data - sync has not run yet.',
	'seed data says so and claims no date'
);

// 2. A blob with no source dates must not borrow this plugin's own run date.
//    This is the regression: the old line read "synced <today>" here.
$line = wpcct_prov( array( 'generated' => gmdate( 'c' ) ) );
t_eq(
	$line,
	'WordCamp Central via Airtable · age of source data unknown',
	'a blob with no source dates admits the age is unknown'
);
t_ok( false === strpos( $line, 'synced' ), 'and does not use the word "synced" at all' );

// 3. Rows exist but none was ever written from Central: the state the live
//    dashboard was actually in, showing a 1-day-old date over 41-day-old data.
$line = wpcct_prov(
	array(
		'generated'      => gmdate( 'c' ),
		'source_synced'  => '',
		'source_created' => $iso( 41 ),
	)
);
t_ok( false !== strpos( $line, 'never refreshed from Central' ), 'never-synced rows are flagged as never refreshed' );
t_ok( false !== strpos( $line, '41 days old' ), 'and carry the real age of the data' );
t_ok( false !== strpos( $line, gmdate( 'j F Y', time() - ( 41 * DAY_IN_SECONDS ) ) ), 'and the load date, not today' );

// 4. Central read recently: a plain, unflagged line.
$line = wpcct_prov(
	array(
		'generated'      => gmdate( 'c' ),
		'source_synced'  => $iso( 2 ),
		'source_created' => $iso( 41 ),
	)
);
t_eq(
	$line,
	'WordCamp Central via Airtable · data from Central ' . gmdate( 'j F Y', time() - ( 2 * DAY_IN_SECONDS ) ),
	'a recent Central read reports that date plainly'
);
t_ok( false === strpos( $line, 'days old' ), 'and is not flagged as old' );

// 5. Central read, but long ago: flagged with the age.
$line = wpcct_prov(
	array(
		'generated'      => gmdate( 'c' ),
		'source_synced'  => $iso( 30 ),
		'source_created' => $iso( 90 ),
	)
);
t_ok( false !== strpos( $line, '30 days old' ), 'a Central read older than 14 days is flagged with its age' );
t_ok( false === strpos( $line, 'never refreshed' ), 'and is not confused with never having synced' );

// 6. Precedence: a sync stamp always wins over createdTime.
$line = wpcct_prov(
	array(
		'source_synced'  => $iso( 3 ),
		'source_created' => $iso( 400 ),
	)
);
t_ok( false !== strpos( $line, gmdate( 'j F Y', time() - ( 3 * DAY_IN_SECONDS ) ) ), 'Synced At takes precedence over createdTime' );
t_ok( false === strpos( $line, '400' ), 'and createdTime is not reported when a sync stamp exists' );

// 7. Boundary: 14 days is not yet flagged, 15 is.
t_ok(
	false === strpos( wpcct_prov( array( 'source_synced' => $iso( 14 ) ) ), 'days old' ),
	'14 days is within tolerance'
);
t_ok(
	false !== strpos( wpcct_prov( array( 'source_synced' => $iso( 15 ) ) ), '15 days old' ),
	'15 days is flagged'
);

// 8. Garbage in a source field must not be rendered as a date.
t_eq(
	wpcct_prov( array( 'source_synced' => 'not a date', 'source_created' => '' ) ),
	'WordCamp Central via Airtable · age of source data unknown',
	'an unparseable source date reads as unknown, not as 1 January 1970'
);
