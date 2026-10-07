<?php
/**
 * Test entry point. Run: php tests/run-tests.php
 *
 * @package WPCC_Tracker
 */

require __DIR__ . '/harness.php';

$rows = wpcct_load_fixture();

t_eq( count( $rows ), 142, 'fixture has 142 events' );

t_eq(
	array_keys( $rows[0] ),
	array(
		'Start Date (YYYY-mm-dd)',
		'End Date (YYYY-mm-dd)',
		'Status',
		'Name',
		'Institution Name',
		'City',
		'Country',
		'Number of Anticipated Attendees',
		'Actual Attendees',
		'Series Event',
		'Created',
		'URL',
		'ID',
	),
	'fixture columns match the report field order'
);

$actual = 0;
foreach ( $rows as $r ) {
	$actual += (int) preg_replace( '/[^0-9]/', '', $r['Actual Attendees'] );
}
t_eq( $actual, 6490, 'actual attendees sum matches the Stats sheet' );

$ids = array_column( $rows, 'ID' );
t_eq( count( array_unique( $ids ) ), 142, 'ID is unique across every row' );

require __DIR__ . '/../includes/class-wpcct-normalize.php';

$expect = array(
	'Closed'                                      => 'completed',
	'Scheduled'                                   => 'scheduled',
	'In Pre-Planning'                             => 'planning',
	'Needs Vetting'                               => 'planning',
	'Needs Orientation/Interview'                 => 'planning',
	'Interview/Orientation Scheduled'             => 'planning',
	'Approved for Pre-Planning Pending Agreement' => 'planning',
	'Needs to Fill Out Listing'                   => 'planning',
	'On Hold'                                     => 'planning',
	'Cancelled'                                   => 'excluded',
	'Declined'                                    => 'excluded',
);
foreach ( $expect as $status => $bucket ) {
	t_eq( WPCCT_Normalize::status_bucket( $status ), $bucket, "status '$status' buckets as $bucket" );
}
t_eq( WPCCT_Normalize::status_bucket( 'Something New' ), 'excluded', 'unknown status is excluded, not counted' );

$counts = array( 'completed' => 0, 'scheduled' => 0, 'planning' => 0, 'excluded' => 0 );
foreach ( wpcct_load_fixture() as $r ) {
	$counts[ WPCCT_Normalize::status_bucket( $r['Status'] ) ]++;
}
t_eq( $counts['completed'], 56, 'completed count' );
t_eq( $counts['scheduled'], 15, 'scheduled count' );
t_eq( $counts['planning'], 46, 'planning count matches the sheet regional total' );
t_eq( $counts['excluded'], 25, 'cancelled + declined count' );
t_eq( array_sum( $counts ), 142, 'buckets partition every row' );

t_eq( WPCCT_Normalize::canonical_country( 'Maharashtra, India' ), 'India', 'state-in-country-field aliases to India' );
t_eq( WPCCT_Normalize::canonical_country( '  Spain ' ), 'Spain', 'country is trimmed' );

t_eq( WPCCT_Normalize::region_for( 'India' ), 'Asia', 'India is Asia' );
t_eq( WPCCT_Normalize::region_for( 'Maharashtra, India' ), 'Asia', 'alias resolves before region lookup' );
t_eq( WPCCT_Normalize::region_for( 'Spain' ), 'Europe', 'Spain is Europe' );
t_eq( WPCCT_Normalize::region_for( 'Costa Rica' ), 'Latin America and Caribbean', 'Costa Rica is LatAm' );
t_eq( WPCCT_Normalize::region_for( 'Uganda' ), 'Africa', 'Uganda is Africa' );
t_eq( WPCCT_Normalize::region_for( 'United States' ), 'North America', 'US is North America' );
t_eq( WPCCT_Normalize::region_for( 'Narnia' ), '', 'unmapped country returns empty, never a guess' );
t_eq( WPCCT_Normalize::region_for( '' ), '', 'blank country returns empty' );

t_ok( WPCCT_Normalize::is_denied( array( 'Country' => 'Tomorrowland', 'Institution Name' => 'Tomorrow Campus' ) ), 'Tomorrowland is denied' );
t_ok( WPCCT_Normalize::is_denied( array( 'Country' => 'Atlantis', 'Institution Name' => 'Atlantis Atlantide' ) ), 'Atlantis is denied' );
t_ok( WPCCT_Normalize::is_denied( array( 'Country' => 'Wonderland', 'Institution Name' => '' ) ), 'Wonderland is denied' );
t_ok( ! WPCCT_Normalize::is_denied( array( 'Country' => 'India', 'Institution Name' => 'Aryabhatta College' ) ), 'a real event is not denied' );

// Region roll-up over live (non-excluded, non-denied) events.
$live_by_region = array();
$unmapped       = 0;
foreach ( wpcct_load_fixture() as $r ) {
	if ( WPCCT_Normalize::is_denied( $r ) ) {
		continue;
	}
	if ( 'excluded' === WPCCT_Normalize::status_bucket( $r['Status'] ) ) {
		continue;
	}
	$region = WPCCT_Normalize::region_for( $r['Country'] );
	if ( '' === $region ) {
		$unmapped++;
		continue;
	}
	$live_by_region[ $region ] = ( isset( $live_by_region[ $region ] ) ? $live_by_region[ $region ] : 0 ) + 1;
}
t_eq( $live_by_region['Asia'], 67, 'Asia live events match the sheet' );
t_eq( $live_by_region['Europe'], 19, 'Europe live events match the sheet' );
t_eq( $live_by_region['Latin America and Caribbean'], 11, 'LatAm live events match the sheet' );
t_eq( $live_by_region['North America'], 2, 'North America live events match the sheet' );
t_eq( $live_by_region['Africa'], 15, 'Africa live events (sheet says 16; the extra is a blank-country row)' );
t_eq( array_sum( $live_by_region ), 114, 'mapped live events' );
t_eq( $unmapped, 1, 'exactly one live row has an unmappable country' );

t_eq( WPCCT_Normalize::attendees( array( 'Actual Attendees' => '610' ) ), 610, 'plain integer parses' );
t_eq( WPCCT_Normalize::attendees( array( 'Actual Attendees' => '1,293' ) ), 1293, 'thousands separator parses' );
t_eq( WPCCT_Normalize::attendees( array( 'Actual Attendees' => '' ) ), 0, 'blank attendance is zero' );

// Anticipated is free text and is returned verbatim, never coerced to a number.
t_eq( WPCCT_Normalize::anticipated( array( 'Number of Anticipated Attendees' => '80-100' ) ), '80-100', 'range kept verbatim' );
t_eq( WPCCT_Normalize::anticipated( array( 'Number of Anticipated Attendees' => 'Ideally dozens.' ) ), 'Ideally dozens.', 'prose kept verbatim' );
t_eq( WPCCT_Normalize::anticipated( array( 'Number of Anticipated Attendees' => ' 400 ' ) ), '400', 'number kept as a trimmed string' );
t_ok( ! method_exists( 'WPCCT_Normalize', 'total_anticipated' ), 'no method exists to total anticipated attendees' );

// Attendance is only ever recorded on completed events.
$total = 0;
$non_completed_with_attendance = 0;
foreach ( wpcct_load_fixture() as $r ) {
	$n = WPCCT_Normalize::attendees( $r );
	$total += $n;
	if ( $n > 0 && 'completed' !== WPCCT_Normalize::status_bucket( $r['Status'] ) ) {
		$non_completed_with_attendance++;
	}
}
t_eq( $total, 6490, 'total attendance matches the Stats sheet exactly' );
t_eq( $non_completed_with_attendance, 0, 'attendance only ever appears on completed events' );

t_eq( WPCCT_Normalize::institution_key( array( 'Institution Name' => '  Sophia Girls College ' ) ), 'sophia girls college', 'institution key is trimmed and lowercased' );
t_eq( WPCCT_Normalize::institution_key( array( 'Institution Name' => '' ) ), '', 'blank institution has no key' );


$blob = WPCCT_Normalize::build(
	wpcct_load_fixture(),
	array( '11067837' => array( 'lat' => 0.4478, 'lng' => 33.2026, 'site' => 'https://events.wordpress.org/campusconnect/2025/jinja/' ) )
);

t_eq( $blob['totals']['completed'], 56, 'blob completed total' );
t_eq( $blob['totals']['scheduled'], 15, 'blob scheduled total' );
t_eq( $blob['totals']['planning'], 44, 'blob planning total' );
t_eq( $blob['totals']['attendees'], 6490, 'blob attendance total' );
t_eq( $blob['totals']['completed_this_year'], 36, 'blob completed in 2026' );
t_eq( $blob['totals']['countries'], 25, 'blob distinct mapped countries with live events' );

t_eq( count( $blob['regions'] ), 5, 'five regions are always present, even at zero' );
t_ok( isset( $blob['regions']['North America'] ), 'a region with no completed events is still present' );

// Regional buckets must account for every mapped live event. Per-region
// completed/scheduled splits are NOT asserted against the sheet's published
// figures: the sheet is a 2026-08-25 snapshot and the fixture is 2026-08-27,
// and two events changed state in between. Only the invariants hold.
$region_live = 0;
foreach ( $blob['regions'] as $r ) {
	$region_live += $r['completed'] + $r['scheduled'] + $r['planning'];
}
t_eq( $region_live, 114, 'regions account for every mapped live event' );
t_eq( $region_live + array_sum( $blob['unmapped_countries'] ), 115, 'mapped + unmapped = all live events after the denylist' );

// Attendance on the one unmapped row is unknown, so derive the expected
// regional total from the primitives rather than hardcoding it. This checks
// that build() agrees with attendees()/region_for(), which is the actual risk.
$region_attendees = 0;
foreach ( $blob['regions'] as $r ) {
	$region_attendees += $r['attendees'];
}
$expected_region_attendees = 0;
foreach ( wpcct_load_fixture() as $r ) {
	if ( WPCCT_Normalize::is_denied( $r ) ) {
		continue;
	}
	if ( 'excluded' === WPCCT_Normalize::status_bucket( $r['Status'] ) ) {
		continue;
	}
	if ( '' === WPCCT_Normalize::region_for( $r['Country'] ) ) {
		continue;
	}
	$expected_region_attendees += WPCCT_Normalize::attendees( $r );
}
t_eq( $region_attendees, $expected_region_attendees, 'no attendance is lost in the regional roll-up' );

// The pipeline keeps every raw status, including the excluded ones.
t_eq( $blob['pipeline']['Cancelled'], 11, 'pipeline exposes cancelled' );
t_eq( $blob['pipeline']['Declined'], 13, 'pipeline exposes declined' );
t_eq( array_sum( $blob['pipeline'] ), 139, 'pipeline covers every non-denied row' );

t_eq( count( $blob['markers'] ), 1, 'only events with coordinates become markers' );
t_eq( $blob['markers'][0]['id'], '11067837', 'marker carries its WordCamp ID' );

t_eq( $blob['unmapped_countries'], array( '' => 1 ), 'unmapped countries are reported for the admin screen' );

// No personal data may reach the blob.
$json = json_encode( $blob );
foreach ( array( 'Organizer', 'organizer', 'E-mail', 'Email', '@' ) as $needle ) {
	t_ok( false === strpos( $json, $needle ), "blob contains no '$needle'" );
}

t_eq( WPCCT_Normalize::decode( 'KIST College &amp; SS' ), 'KIST College & SS', 'named entity decodes' );
t_eq( WPCCT_Normalize::decode( 'St. Edward&#039;s' ), "St. Edward's", 'numeric entity decodes' );
t_eq( WPCCT_Normalize::decode( 'Plain Name' ), 'Plain Name', 'plain text is untouched' );
t_eq( WPCCT_Normalize::decode( '&amp;amp;' ), '&amp;', 'decodes exactly one round, not repeatedly' );
t_eq( WPCCT_Normalize::decode( '' ), '', 'empty stays empty' );

// The blob must carry no HTML entities in any free-text field.
$decoded = WPCCT_Normalize::build( wpcct_load_fixture() );
$found   = array();
foreach ( $decoded['events'] as $e ) {
	foreach ( array( 'name', 'institution', 'city', 'country', 'anticipated' ) as $f ) {
		if ( preg_match( '/&(amp|lt|gt|quot|#0*39|#x27);/i', (string) $e[ $f ] ) ) {
			$found[] = $f . ': ' . $e[ $f ];
		}
	}
}
t_eq( $found, array(), 'no HTML entities survive into the blob' );

$iem = '';
foreach ( $decoded['events'] as $e ) {
	if ( false !== strpos( $e['institution'], 'Institute of Engineering' ) ) {
		$iem = $e['institution'];
		break;
	}
}
t_eq( $iem, 'Institute of Engineering & Management (IEM), Kolkata', 'the ampersand institution reads correctly' );

t_eq( WPCCT_Normalize::attendees( array( 'Actual Attendees' => '12.5' ) ), 0, 'a decimal is not a headcount - 12.5 must not read as 125' );
t_eq( WPCCT_Normalize::attendees( array( 'Actual Attendees' => '80-100' ) ), 0, 'a range is not a headcount' );
t_eq( WPCCT_Normalize::attendees( array( 'Actual Attendees' => 'Ideally dozens.' ) ), 0, 'prose is not a headcount' );
t_eq( WPCCT_Normalize::attendees( array( 'Actual Attendees' => 610 ) ), 610, 'an Airtable JSON number parses' );

// The plausibility gate the sync consults before it publishes a blob. This is
// the seam that lets the guard be tested at all: WPCCT_Sync itself needs
// WordPress, so a field rename upstream could otherwise only be caught in
// production.
t_ok( WPCCT_Normalize::is_plausible( $blob ), 'the real fixture builds a plausible blob' );

$blanked = array();
foreach ( wpcct_load_fixture() as $r ) {
	$r['Status'] = '';
	$blanked[]   = $r;
}
$blanked_blob = WPCCT_Normalize::build( $blanked );
t_eq( count( $blanked ), 142, 'blanking Status leaves every row in place - the row-count guard cannot see it' );
t_ok( ! WPCCT_Normalize::is_plausible( $blanked_blob ), 'a blob whose rows all lost their Status is not plausible' );
t_ok( ! WPCCT_Normalize::is_plausible( WPCCT_Normalize::build( array() ) ), 'a blob built from no rows at all is not plausible' );

// A lost Country column is the other rename that empties the report, and it
// slips past the live-event check entirely: every row keeps its status and its
// bucket, so the headline totals still look healthy while the map, the region
// table and the country count are all empty.
$countryless = array();
foreach ( wpcct_load_fixture() as $r ) {
	$r['Country'] = '';
	$countryless[] = $r;
}
$countryless_blob = WPCCT_Normalize::build( $countryless );
t_eq( $countryless_blob['totals']['completed'], 56, 'blanking Country leaves the live-event totals untouched' );
t_eq( $countryless_blob['totals']['countries'], 0, '...while no country maps to a region' );
t_ok( ! WPCCT_Normalize::is_plausible( $countryless_blob ), 'a blob whose rows all lost their Country is not plausible' );

/* Source dates: the blob must carry the age of the SOURCE data, because the
   provenance line reports that and not this plugin's own run time. They live
   in the blob so that a failed sync, which keeps the previous blob, keeps the
   matching dates with it. */
$dated = WPCCT_Normalize::build(
	wpcct_load_fixture(),
	array(),
	array(
		'synced'  => '2026-10-07T12:00:00+00:00',
		'created' => '2026-08-27T10:22:35+00:00',
	)
);
t_eq( $dated['source_synced'], '2026-10-07T12:00:00+00:00', 'build() carries source_synced into the blob' );
t_eq( $dated['source_created'], '2026-08-27T10:22:35+00:00', 'build() carries source_created into the blob' );

$undated = WPCCT_Normalize::build( wpcct_load_fixture() );
t_eq( $undated['source_synced'], '', 'a caller that supplies no source dates gets an empty string, not a missing key' );
t_eq( $undated['source_created'], '', 'and the same for source_created' );
t_ok( array_key_exists( 'source_synced', $undated ), 'source_synced is always present so the renderer need not guess' );
t_ok( WPCCT_Normalize::is_plausible( $dated ), 'carrying source dates does not disturb the plausibility guard' );

/* The later export, loaded after the 2026-08-27 assertions above so that a
   drift failure reads as drift rather than as a broken normaliser. */
require __DIR__ . '/test-october-export.php';

/* Rendering, loaded last: it stubs WordPress functions, and nothing above
   should be able to see those stubs. */
require __DIR__ . '/test-provenance.php';

t_report();
