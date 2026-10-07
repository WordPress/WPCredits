<?php
/**
 * The 2026-10-07 export: a second, later pull of the same Campus Connect
 * Details report.
 *
 * Its job is not to re-prove the normaliser, which the 2026-08-27 fixture
 * already does. It is to make upstream drift visible. Between the two exports
 * the report gained 23 events, dropped a Status value, and changed the spelling
 * of another. None of that announced itself, and the first two were only
 * noticed because somebody ran the numbers by hand.
 *
 * Required by run-tests.php, which must already have loaded harness.php and
 * the normaliser.
 *
 * @package WPCC_Tracker
 */

$oct = wpcct_load_fixture( 'campus-connect-details-2026-10-07.tsv' );
$aug = wpcct_load_fixture();

/**
 * Count rows by their raw Status value.
 *
 * @param array $rows Report rows.
 * @return array Status => count.
 */
function wpcct_status_tally( array $rows ) {
	$out = array();

	foreach ( $rows as $row ) {
		$out[ $row['Status'] ] = ( isset( $out[ $row['Status'] ] ) ? $out[ $row['Status'] ] : 0 ) + 1;
	}

	return $out;
}

// ------------------------------------------------------------------- shape
t_eq( count( $oct ), 165, 'the October export has 165 events' );
t_eq( array_keys( $oct[0] ), array_keys( $aug[0] ), 'both exports carry the same 13 columns in the same order' );
t_eq( count( $oct ) - count( $aug ), 23, 'the programme grew by 23 events between the two exports' );

// ----------------------------------------------------------- vocabulary drift
$a = wpcct_status_tally( $aug );
$o = wpcct_status_tally( $oct );

// This is the whole point of keeping a second fixture. In August the report
// said "Cancelled"; in October it says "Canceled". One L. Nothing warned.
t_ok( isset( $a['Cancelled'] ) && ! isset( $a['Canceled'] ), 'August spelled it Cancelled' );
t_ok( isset( $o['Canceled'] ) && ! isset( $o['Cancelled'] ), 'October spells it Canceled, with one L' );
t_eq( $o['Canceled'], 14, 'and 14 rows carry that spelling' );

t_ok( isset( $a['Needs to Fill Out Listing'] ), 'August had a Needs to Fill Out Listing row' );
t_ok( ! isset( $o['Needs to Fill Out Listing'] ), 'October has none, so the value can vanish between exports' );

// -------------------------------------------------- what the blob comes out as
$blob     = WPCCT_Normalize::build( $oct );
$aug_blob = WPCCT_Normalize::build( $aug );

t_ok( WPCCT_Normalize::is_plausible( $blob ), 'the October blob passes the plausibility guard' );
t_eq( $blob['totals']['completed'], 72, 'completed is 72' );
t_eq( $blob['totals']['scheduled'], 13, 'scheduled is 13' );
t_eq( $blob['totals']['planning'], 48, 'in planning is 48' );
t_eq( $blob['totals']['attendees'], 6969, 'attendees total 6,969' );
t_eq( $blob['totals']['institutions'], 125, 'institutions is 125' );
t_eq( $blob['totals']['countries'], 25, 'countries is 25' );
t_eq( count( $blob['events'] ), 133, 'the event list holds the 133 non-excluded events' );
t_eq(
	$blob['totals']['completed'] + $blob['totals']['scheduled'] + $blob['totals']['planning'],
	count( $blob['events'] ),
	'and the three headline buckets account for exactly those events'
);

// August left one row with a blank country, which the design document records
// as the reason Africa was one short. October filled it in.
t_eq( $blob['unmapped_countries'], array(), 'no country is unmapped in the October export' );

// ------------------------------------------------------------- the denylist
// Three rows name fictional countries. They are still caught, and still sit in
// the three statuses the design document names, so each of those is one lower
// in the pipeline than in the raw tally.
$deny = 0;
foreach ( $blob['pipeline'] as $status => $n ) {
	if ( isset( $o[ $status ] ) && $o[ $status ] > $n ) {
		$deny += $o[ $status ] - $n;
	}
}
t_eq( $deny, 3, 'the denylist still drops exactly three rows' );
t_eq( array_sum( $blob['pipeline'] ), 162, 'leaving 162 of the 165 in the pipeline' );

// --------------------------------------------- the hazard this fixture exposes
// status_bucket() has no "unrecognised" answer. Anything it does not know
// becomes excluded, so the October spelling lands in the right bucket by the
// direction of the fallback rather than by a rule about it.
t_eq( WPCCT_Normalize::status_bucket( 'Cancelled' ), WPCCT_Normalize::STATUS_EXCLUDED, 'Cancelled is excluded by rule' );
t_eq( WPCCT_Normalize::status_bucket( 'Canceled' ), WPCCT_Normalize::STATUS_EXCLUDED, 'Canceled is excluded too' );
t_eq(
	WPCCT_Normalize::status_bucket( 'Totally Invented Status' ),
	WPCCT_Normalize::STATUS_EXCLUDED,
	'but so is a value nobody has ever seen, by the same fallback'
);

// That fallback is still the right direction, but it is no longer silent.
// is_known_status() answers what status_bucket() cannot, and build() reports
// the answer, the way unmapped_countries has always reported regions.
t_ok( WPCCT_Normalize::is_known_status( 'Cancelled' ), 'Cancelled is a known status' );
t_ok( ! WPCCT_Normalize::is_known_status( 'Canceled' ), 'Canceled is not' );
t_ok( ! WPCCT_Normalize::is_known_status( 'Totally Invented Status' ), 'nor is an invented one' );

// The drift is now visible in the blob instead of having to be found by hand.
// Thirteen, not fourteen: one of the Canceled rows is denylisted.
t_eq( $blob['unmapped_statuses'], array( 'Canceled' => 13 ), 'the October blob reports Canceled as unmapped' );
t_eq( $aug_blob['unmapped_statuses'], array(), 'the August blob reports none, its vocabulary being fully known' );

// Reporting only. An unrecognised status must not move an event anywhere.
t_eq( $blob['totals']['completed'], 72, 'reporting an unmapped status does not change completed' );
t_eq( $blob['totals']['planning'], 48, 'nor planning' );
t_eq( $blob['totals']['attendees'], 6969, 'nor the attendee total' );
t_ok( WPCCT_Normalize::is_plausible( $blob ), 'nor does it make a healthy blob implausible' );

// The failure this exists to catch: a planning stage renamed upstream. The
// event still leaves the totals, because guessing would be worse, but the
// shortfall is now accounted for rather than unexplained.
$invented              = $oct;
$invented[0]['Status'] = 'Needs Budget Review';
$shrunk                = WPCCT_Normalize::build( $invented );
t_eq(
	$shrunk['totals']['completed'],
	$blob['totals']['completed'] - 1,
	'an event given an unknown Status still leaves the totals'
);
t_eq(
	isset( $shrunk['unmapped_statuses']['Needs Budget Review'] ) ? $shrunk['unmapped_statuses']['Needs Budget Review'] : 0,
	1,
	'but the blob now names the status that took it'
);

// Blanks are reported too, exactly as a blank Country is.
$blanked              = $oct;
$blanked[0]['Status'] = '';
$blank_blob           = WPCCT_Normalize::build( $blanked );
t_eq(
	isset( $blank_blob['unmapped_statuses'][''] ) ? $blank_blob['unmapped_statuses'][''] : 0,
	1,
	'a blank Status is reported under the blank key, as a blank Country is'
);

// ------------------------------------------------- free text is still not summed
$sum = 0;
foreach ( $oct as $row ) {
	if ( is_numeric( $row['Number of Anticipated Attendees'] ) ) {
		$sum += (int) $row['Number of Anticipated Attendees'];
	}
}
t_ok( $sum > 0, 'the October export does carry numeric-looking anticipated figures' );
t_ok( $blob['totals']['attendees'] !== $sum, 'and the attendee total is not them' );
