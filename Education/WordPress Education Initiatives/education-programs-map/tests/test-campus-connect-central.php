<?php
/**
 * Reading Campus Connect events straight from central.wordcamp.org.
 *
 * The Airtable "WordCamps" table was only ever a copy of this route, and the
 * route is public: an anonymous request returns every public Campus Connect
 * event with its venue coordinates. prepare_central_event() is the whole
 * translation, so it is where the rules are tested.
 *
 * @package Education_Programs_Map
 */

require __DIR__ . '/../includes/class-epm-campus-connect.php';

/**
 * A post payload shaped the way wp/v2/wordcamps shapes one.
 *
 * @param array $overrides Fields to replace or add.
 * @return array
 */
function epm_cc_post( array $overrides = array() ) {
	return array_merge(
		array(
			'id'                      => 16429735,
			'title'                   => array( 'rendered' => 'WordPress Campus Connect Presidency University, Dhaka' ),
			'link'                    => 'https://central.wordcamp.org/wordcamps/wordpress-campus-connect-presidency-university/',
			'URL'                     => 'https://events.wordpress.org/campusconnect/2026/presidency-university-dhaka/',
			'Venue Name'              => 'Presidency University',
			'_venue_city'             => 'Dhaka',
			'_venue_country_name'     => 'Bangladesh',
			'Start Date (YYYY-mm-dd)' => '1792972800',
			'End Date (YYYY-mm-dd)'   => '',
			'Organizer Name'          => 'Nobody At All',
			'_venue_coordinates'      => array( 'latitude' => 23.796389, 'longitude' => 90.4173821 ),
		),
		$overrides
	);
}

$e = EPM_Campus_Connect::prepare_central_event( epm_cc_post() );

t_ok( is_array( $e ), 'a Campus Connect post becomes an event' );
t_eq( $e['id'], '16429735', 'the WordCamp post ID identifies the event' );
t_eq( $e['city'], 'Dhaka', 'city comes from the venue city' );
t_eq( $e['country'], 'Bangladesh', 'country comes from the venue country name' );
t_eq( $e['latitude'], 23.796389, 'latitude is a float' );
t_eq( $e['longitude'], 90.4173821, 'longitude is a float' );
t_eq( $e['venue'], 'Presidency University', 'venue comes from the venue name' );
t_eq( $e['name'], 'WordPress Campus Connect Presidency University, Dhaka', 'the title is the event name' );
t_eq( $e['url'], 'https://events.wordpress.org/campusconnect/2026/presidency-university-dhaka/', 'the event site is preferred over the Central page' );
t_eq( $e['organizer'], 'Nobody At All', 'the organiser is carried, as the Airtable path carried it' );

/* Central stores the dates as unix timestamps in a field whose name claims
   otherwise. The map wants Y-m-d, and a raw timestamp would render as a
   ten-digit number in a marker popup. */
t_eq( $e['date'], '2026-10-26', 'a timestamp start date becomes a Y-m-d date' );
t_eq( $e['endDate'], '', 'a missing end date stays empty rather than becoming 1 January 1970' );
t_eq(
	EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( 'End Date (YYYY-mm-dd)' => '1793059200' ) ) )['endDate'],
	'2026-10-27',
	'an end date that is present is converted too'
);

/* Already a date rather than a timestamp, which is what the hand-exported
   report gives and what a future Central change might give. */
t_eq(
	EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( 'Start Date (YYYY-mm-dd)' => '2026-05-14' ) ) )['date'],
	'2026-05-14',
	'a date that is already a date is left alone'
);

// Default-deny on the name: the route is ~94% regular WordCamps.
t_eq( EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( 'title' => array( 'rendered' => 'WordCamp Europe 2027' ) ) ) ), null, 'a regular WordCamp is not a Campus Connect event' );
t_ok( is_array( EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( 'title' => array( 'rendered' => 'wordpress CAMPUS connect Somewhere' ) ) ) ) ), 'the name match ignores case' );
t_ok( is_array( EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( 'title' => 'Campus Connect St. Nobody&#039;s' ) ) ) ), 'a plain-string title works too' );
t_eq(
	EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( 'title' => array( 'rendered' => 'Campus Connect KIST College &amp; SS' ) ) ) )['name'],
	'Campus Connect KIST College & SS',
	'the name is decoded, not left entity-encoded'
);

/* No coordinates means no marker. This sync never geocodes, so a post without
   them cannot be rescued and is skipped, exactly as the Airtable path skipped
   a record with no Latitude. */
t_eq( EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( '_venue_coordinates' => array() ) ) ), null, 'a post with no coordinates is skipped' );
t_eq( EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( '_venue_coordinates' => array( 'latitude' => '', 'longitude' => '' ) ) ) ), null, 'blank coordinates are skipped' );
t_eq( EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( '_venue_coordinates' => array( 'latitude' => 'n/a', 'longitude' => 'n/a' ) ) ) ), null, 'non-numeric coordinates are skipped, never cast to 0.0' );

// Kampala is at 0.3 and Quito at -0.2; a falsy check would drop the equator.
$equator = EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( '_venue_coordinates' => array( 'latitude' => 0, 'longitude' => 32.58 ) ) ) );
t_eq( $equator['latitude'], 0.0, 'a latitude of exactly 0 is kept' );

// 20 of the 92 live events have no venue city, so these fallbacks are load-bearing.
$nocity = EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( '_venue_city' => '' ) ) );
t_eq( $nocity['city'], '', 'a missing city is empty, not invented' );
t_eq( $nocity['venue'], 'Presidency University', 'and the venue still names the marker' );

$novenue = EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( 'Venue Name' => '' ) ) );
t_eq( $novenue['venue'], 'WordPress Campus Connect Presidency University, Dhaka', 'with no venue name the event title names the marker' );

$nosite = EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( 'URL' => '' ) ) );
t_eq( $nosite['url'], 'https://central.wordcamp.org/wordcamps/wordpress-campus-connect-presidency-university/', 'with no event site the Central page is linked instead' );

t_eq( EPM_Campus_Connect::prepare_central_event( array() ), null, 'an empty payload is not an event' );
t_eq( EPM_Campus_Connect::prepare_central_event( epm_cc_post( array( 'id' => 0 ) ) ), null, 'a post with no usable ID is skipped' );

/* The event shape must match what group_events(), group_key() and the marker
   writer already consume, or the Central path would need a second writer. */
t_eq(
	array_keys( EPM_Campus_Connect::prepare_central_event( epm_cc_post() ) ),
    array( 'id', 'city', 'country', 'latitude', 'longitude', 'venue', 'name', 'date', 'endDate', 'url', 'organizer' ),
	'the event carries exactly the keys the Airtable path produced'
);

/* run_sync() has to tell "not a Campus Connect event" apart from "a Campus
   Connect event with no coordinates": the first is the overwhelming majority
   of the route and is silent, the second is a real skip worth reporting. Both
   return null from prepare_central_event(), so the name rule is exposed on its
   own rather than being written out a second time at the call site. */
t_ok( EPM_Campus_Connect::is_campus_connect( epm_cc_post() ), 'a Campus Connect post is recognised' );
t_ok( ! EPM_Campus_Connect::is_campus_connect( epm_cc_post( array( 'title' => array( 'rendered' => 'WordCamp Europe 2027' ) ) ) ), 'a regular WordCamp is not' );
t_ok( EPM_Campus_Connect::is_campus_connect( epm_cc_post( array( '_venue_coordinates' => array() ) ) ), 'a Campus Connect post with no coordinates is still a Campus Connect post' );
t_ok( ! EPM_Campus_Connect::is_campus_connect( array() ), 'an empty payload is not one' );
t_ok( ! EPM_Campus_Connect::is_campus_connect( 'nope' ), 'a non-array is not one' );

