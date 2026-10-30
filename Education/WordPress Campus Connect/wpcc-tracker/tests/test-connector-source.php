<?php
/**
 * The connector source: reading the in-process snapshot that
 * wordcamp-airtable-connector offers, instead of reading Airtable.
 *
 * WPCCT_Normalize::from_snapshot() is the gate between a payload this plugin
 * did not build and the rows it publishes. It is pure, so every refusal is
 * testable here rather than only on a live site with a broken provider.
 *
 * Required by run-tests.php, which must already have loaded harness.php and
 * class-wpcct-normalize.php.
 *
 * @package WPCC_Tracker
 */

/**
 * A payload shaped the way the connector's snapshot is shaped.
 *
 * @param int    $rows    Number of event rows.
 * @param int    $coords  Number of coordinate entries.
 * @param string $read_at ISO-8601 time Central was read.
 * @return array
 */
function wpcct_snapshot_payload( $rows = 3, $coords = 2, $read_at = '2026-10-30T09:00:00+00:00' ) {
	$r = array();
	for ( $i = 0; $i < $rows; $i++ ) {
		$r[] = array(
			'Start Date (YYYY-mm-dd)'         => '2026-05-14',
			'End Date (YYYY-mm-dd)'           => '',
			'Status'                          => 'Closed',
			'Name'                            => 'WordPress Campus Connect ' . $i,
			'Institution Name'                => 'Institute ' . $i,
			'City'                            => 'Kolkata',
			'Country'                         => 'India',
			'Number of Anticipated Attendees' => '80-100',
			'Actual Attendees'                => '93',
			'URL'                             => 'https://events.wordpress.org/campusconnect/' . $i . '/',
			'ID'                              => (string) ( 1000 + $i ),
		);
	}

	$c = array();
	for ( $i = 0; $i < $coords; $i++ ) {
		$c[ (string) ( 1000 + $i ) ] = array( 'lat' => 22.57, 'lng' => 88.36, 'site' => '' );
	}

	return array( 'read_at' => $read_at, 'rows' => $r, 'coords' => $c );
}

$read = WPCCT_Normalize::from_snapshot( wpcct_snapshot_payload() );

t_ok( $read['ok'], 'a well-formed snapshot is accepted' );
t_eq( $read['error'], '', 'and reports no error' );
t_eq( count( $read['rows'] ), 3, 'its rows come through' );
t_eq( count( $read['coords'] ), 2, 'its coordinates come through' );
t_eq( $read['read_at'], '2026-10-30T09:00:00+00:00', 'and the time Central was read' );

/* The rows are handed over untouched. The connector owns the mapping: it is
   where a wcpt status slug became 'Closed' and where entities were decoded,
   and re-deriving any of that here would be a second implementation of rules
   that already have tests on the other side. */
$payload = wpcct_snapshot_payload( 1, 1 );
t_eq( WPCCT_Normalize::from_snapshot( $payload )['rows'][0], $payload['rows'][0], 'a row is passed through unchanged' );

/* No provider at all. This is the case that must read clearly in the admin,
   because the cause is on the other plugin and nothing here can fix it. */
$none = WPCCT_Normalize::from_snapshot( null );
t_ok( ! $none['ok'], 'a null payload means no provider answered' );
t_ok( false !== strpos( $none['error'], 'wordcamp-airtable-connector' ), 'and the error names the plugin that provides it' );
t_eq( $none['rows'], array(), 'and yields no rows' );

t_ok( ! WPCCT_Normalize::from_snapshot( 'nope' )['ok'], 'a non-array payload is refused' );
t_ok( ! WPCCT_Normalize::from_snapshot( array() )['ok'], 'an empty payload is refused' );

$no_rows = WPCCT_Normalize::from_snapshot( array( 'coords' => array( '1' => array( 'lat' => 1.0, 'lng' => 2.0 ) ) ) );
t_ok( ! $no_rows['ok'], 'a payload with no rows key is refused' );

$bad_rows = WPCCT_Normalize::from_snapshot( array( 'rows' => 'not a list', 'coords' => array( '1' => array() ) ) );
t_ok( ! $bad_rows['ok'], 'a payload whose rows are not a list is refused' );

$empty_rows = wpcct_snapshot_payload( 0, 2 );
t_ok( ! WPCCT_Normalize::from_snapshot( $empty_rows )['ok'], 'a payload with an empty rows list is refused' );

/* Coordinates are the map. An empty map with healthy rows is the signature of
   the name match or the search breaking upstream, not of a programme without
   venues, so it is refused rather than published. */
$no_coords = WPCCT_Normalize::from_snapshot( wpcct_snapshot_payload( 3, 0 ) );
t_ok( ! $no_coords['ok'], 'a payload with no coordinates is refused' );
t_ok( false !== stripos( $no_coords['error'], 'coordinate' ), 'and the error says so' );

/* Two columns are load-bearing: without ID nothing joins to a coordinate and
   nothing is identified, and without Status nothing can be bucketed. Naming
   which one is missing is the whole value of checking here rather than
   letting is_plausible() report a generic empty result later. */
$idless = wpcct_snapshot_payload( 3, 2 );
foreach ( $idless['rows'] as $i => $row ) {
	unset( $idless['rows'][ $i ]['ID'] );
}
$no_id = WPCCT_Normalize::from_snapshot( $idless );
t_ok( ! $no_id['ok'], 'rows with no ID column are refused' );
t_ok( false !== strpos( $no_id['error'], 'ID' ), 'and the error names the ID column' );

$statusless = wpcct_snapshot_payload( 3, 2 );
foreach ( $statusless['rows'] as $i => $row ) {
	unset( $statusless['rows'][ $i ]['Status'] );
}
$no_status = WPCCT_Normalize::from_snapshot( $statusless );
t_ok( ! $no_status['ok'], 'rows with no Status column are refused' );
t_ok( false !== strpos( $no_status['error'], 'Status' ), 'and the error names the Status column' );

/* A snapshot with no read_at is still usable: the figures are real, their age
   is simply unknown, and the provenance line already has wording for that. */
$undated = wpcct_snapshot_payload( 3, 2, '' );
t_ok( WPCCT_Normalize::from_snapshot( $undated )['ok'], 'a snapshot with no read_at is still accepted' );
t_eq( WPCCT_Normalize::from_snapshot( $undated )['read_at'], '', 'and reports an empty read_at rather than inventing one' );

// The snapshot's own rows must survive the normaliser and produce real totals.
$built = WPCCT_Normalize::build( wpcct_snapshot_payload( 60, 2 )['rows'], wpcct_snapshot_payload( 60, 2 )['coords'] );
t_eq( $built['totals']['completed'], 60, 'snapshot rows normalise into completed totals' );
t_eq( $built['totals']['attendees'], 60 * 93, 'and their attendance adds up' );
t_ok( WPCCT_Normalize::is_plausible( $built ), 'and the result passes the plausibility guard' );

/*
 * The snapshot carries eleven columns. The report has thirteen: it also has
 * 'Series Event', which only a manage_options caller is sent, and 'Created',
 * which is Airtable's own arrival stamp rather than a report value. Dropping
 * them is only safe if no published figure depends on them, so that is
 * asserted here against a real export rather than assumed.
 */
$full = WPCCT_Normalize::build( wpcct_load_fixture( 'campus-connect-details-2026-10-07.tsv' ) );

$projected = array();
foreach ( wpcct_load_fixture( 'campus-connect-details-2026-10-07.tsv' ) as $row ) {
	$slim = array();
	foreach ( array(
		'Start Date (YYYY-mm-dd)',
		'End Date (YYYY-mm-dd)',
		'Status',
		'Name',
		'Institution Name',
		'City',
		'Country',
		'Number of Anticipated Attendees',
		'Actual Attendees',
		'URL',
		'ID',
	) as $column ) {
		$slim[ $column ] = isset( $row[ $column ] ) ? $row[ $column ] : '';
	}
	$projected[] = $slim;
}

$slimmed = WPCCT_Normalize::build( $projected );

t_eq( $slimmed['totals'], $full['totals'], 'the eleven snapshot columns reproduce every headline total' );
t_eq( $slimmed['regions'], $full['regions'], 'and every regional figure' );
t_eq( $slimmed['pipeline'], $full['pipeline'], 'and the whole status pipeline' );
t_eq( $slimmed['unmapped_countries'], $full['unmapped_countries'], 'and the unmapped country list' );
t_eq( count( $slimmed['events'] ), count( $full['events'] ), 'and keeps every event' );

