<?php
/**
 * Bare-PHP checks over WCAC_Mapper::campus_event(), the one mapper whose
 * payload must never clobber a hand-curated Airtable cell.
 *
 * No PHPUnit and no WordPress: the five WP functions the mapper touches are
 * stubbed below, which keeps this runnable as `php tests/test-campus-mapper.php`
 * from a clean checkout.
 *
 * @package WordCamp_Airtable_Connector
 */
define( 'ABSPATH', '/tmp/fake/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'YEAR_IN_SECONDS', 31536000 );

function wp_strip_all_tags( $s, $break = false ) { return strip_tags( (string) $s ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $s ) ) ); }
function wp_check_invalid_utf8( $s, $strip = false ) { return (string) $s; }
function esc_url_raw( $u ) { return (string) $u; }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function wp_http_validate_url( $u ) { return filter_var( $u, FILTER_VALIDATE_URL ) ? $u : false; }

/**
 * Minimal stand-in for WordPress's error object.
 */
class WP_Error { // phpcs:ignore
	/** @var string */ private $code;
	/** @var string */ private $message;
	/** @var mixed */  private $data;

	/**
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @param mixed  $data    Data.
	 */
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	/** @return string */
	public function get_error_code() {
		return $this->code;
	}

	/** @return string */
	public function get_error_message() {
		return $this->message;
	}

	/** @return mixed */
	public function get_error_data() {
		return $this->data;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) { // phpcs:ignore
		return $thing instanceof WP_Error;
	}
}

require __DIR__ . '/../includes/class-wcac-mapper.php';

// The 11 writable Status labels, as they exist on the Airtable singleSelect.
$allowed = array(
    'Closed', 'Scheduled', 'In Pre-Planning', 'Needs Vetting',
    'Needs Orientation/Interview', 'Interview/Orientation Scheduled',
    'Approved for Pre-Planning Pending Agreement', 'Needs to Fill Out Listing',
    'On Hold', 'Cancelled', 'Declined',
);

// A representative report row, shaped like Central's Campus Connect Details output.
$row = array(
    'ID'                              => '15821491',
    'Name'                            => 'WordCamp Campus Connect Example',
    'Status'                          => 'Closed',
    'Start Date (YYYY-mm-dd)'         => '2026-05-14',
    'End Date (YYYY-mm-dd)'           => '2026-05-14',
    'Venue Name'                      => 'Institute of Engineering &amp; Management',
    '_venue_city'                     => 'Kolkata',
    '_venue_country_name'             => 'India',
    'Number of Anticipated Attendees' => '80-100',
    'Actual Attendees'                => '93',
    'Created'                         => '2026-01-02',
    'URL'                             => 'https://example.wordcamp.org/2026/',
);

$before = time();
$out    = WCAC_Mapper::campus_event( $row, $allowed );
$after  = time();

$f = $out['fields'];

$pass = 0; $fail = 0;
function t( $cond, $label, &$pass, &$fail ) {
    if ( $cond ) { ++$pass; printf( "  ok   %s\n", $label ); }
    else         { ++$fail; printf( "  FAIL %s\n", $label ); }
}

echo "campus_event() field keys:\n  " . implode( ', ', array_keys( $f ) ) . "\n\n";

t( array_key_exists( 'Synced At', $f ), 'Synced At is present in the payload', $pass, $fail );
t( is_string( $f['Synced At'] ), 'Synced At is a string', $pass, $fail );
t( (bool) preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $f['Synced At'] ), 'Synced At is ISO-8601 UTC with a Z suffix', $pass, $fail );

$ts = strtotime( $f['Synced At'] );
t( $ts >= $before && $ts <= $after + 1, 'Synced At is the current time, not a stored value', $pass, $fail );

// The guarantee that matters: the stamp must not disturb anything else.
$expected = array( 'WordCamp ID', 'Synced At', 'Name', 'Status', 'Start Date', 'End Date',
                   'Institution Name', 'City', 'Country', 'Anticipated Attendees',
                   'Actual Attendees', 'Created', 'URL' );
t( array_keys( $f ) === $expected, 'the other 12 keys are unchanged and in order', $pass, $fail );
t( 15821491 === $f['WordCamp ID'], 'WordCamp ID still the merge value', $pass, $fail );
t( '80-100' === $f['Anticipated Attendees'], 'Anticipated Attendees still free text', $pass, $fail );
t( 93 === $f['Actual Attendees'], 'Actual Attendees still an int', $pass, $fail );

// A row with no usable ID must stay empty: no stamp on a row we will not write.
$dropped = WCAC_Mapper::campus_event( array( 'ID' => 'n/a', 'Status' => 'Closed' ), $allowed );
t( array() === $dropped['fields'], 'a dropped row carries no Synced At (and no fields at all)', $pass, $fail );
t( 0 === $dropped['id'], 'a dropped row reports id 0', $pass, $fail );


// ---------------------------------------------------------------- status alias
echo "\nstatus spelling bridge:\n";

$r = WCAC_Mapper::campus_status( 'Canceled', $allowed );
t( 'Cancelled' === $r['label'], "the report's 'Canceled' resolves to Airtable's 'Cancelled'", $pass, $fail );
t( '' === $r['why'], 'and reports no problem', $pass, $fail );

// The bridge renames; it must not be a way past default-deny.
$without = array_values( array_diff( $allowed, array( 'Cancelled' ) ) );
$r = WCAC_Mapper::campus_status( 'Canceled', $without );
t( null === $r['label'], 'an alias whose target is not an Airtable choice still resolves to null', $pass, $fail );
t( 'absent' === $r['why'], 'and is reported as absent, not silently dropped', $pass, $fail );

$r = WCAC_Mapper::campus_status( 'Cancelled', $allowed );
t( 'Cancelled' === $r['label'], 'the original spelling still resolves', $pass, $fail );

$r = WCAC_Mapper::campus_status( 'Compleately Made Up', $allowed );
t( null === $r['label'] && 'unmapped' === $r['why'], 'an unknown label is still unmapped', $pass, $fail );

$r = WCAC_Mapper::campus_status( 'wcpt-cancelled', $allowed );
t( 'Cancelled' === $r['label'], 'the slug form is unaffected by the alias', $pass, $fail );

// ----------------------------------------------------------------- file source
echo "\nexported report file:\n";

require __DIR__ . '/../includes/class-wcac-source.php';

$tmp = sys_get_temp_dir() . '/wcac-test-' . getmypid() . '.tsv';

$write = function ( $text ) use ( $tmp ) {
    file_put_contents( $tmp, $text );
    return $tmp;
};

$head = "Start Date (YYYY-mm-dd)\tStatus\tName\tInstitution Name\tCity\tCountry\tActual Attendees\tURL\tID";
$body = "2026-05-14\tClosed\tWordCamp Example\tExample Institute\tKolkata\tIndia\t93\thttps://e.wordcamp.org/\t15821491";

$rows = WCAC_Source::campus_connect_from_file( $write( $head . "\n" . $body . "\n" ) );
t( is_array( $rows ) && 1 === count( $rows ), 'a well-formed export parses to one row', $pass, $fail );

if ( is_array( $rows ) && $rows ) {
    $r = $rows[0];
    // The three renamed columns are the whole point: the export calls them
    // one thing and the mapper reads another.
    t( isset( $r['Venue Name'] ) && 'Example Institute' === $r['Venue Name'], "'Institution Name' is read back as 'Venue Name'", $pass, $fail );
    t( isset( $r['_venue_city'] ) && 'Kolkata' === $r['_venue_city'], "'City' is read back as '_venue_city'", $pass, $fail );
    t( isset( $r['_venue_country_name'] ) && 'India' === $r['_venue_country_name'], "'Country' is read back as '_venue_country_name'", $pass, $fail );
    t( isset( $r['ID'] ) && '15821491' === $r['ID'], 'ID survives as the merge value', $pass, $fail );

    // And the parsed row must survive the mapper unchanged.
    $out = WCAC_Mapper::campus_event( $r, $allowed );
    t( 15821491 === $out['id'], 'a file-sourced row maps to the right merge id', $pass, $fail );
    t( isset( $out['fields']['Institution Name'] ) && 'Example Institute' === $out['fields']['Institution Name'], 'and writes the institution through', $pass, $fail );
    t( isset( $out['fields']['Synced At'] ), 'and is stamped like any other row', $pass, $fail );
}

// A comma-separated export with quoted cells containing commas.
$csv = "Status,Name,City,ID\nClosed,\"Example, with comma\",Kolkata,99\n";
$rows = WCAC_Source::campus_connect_from_file( $write( $csv ) );
t( is_array( $rows ) && 'Example, with comma' === $rows[0]['Name'], 'a quoted comma inside a CSV cell is not a column break', $pass, $fail );

// Refusals: each would otherwise corrupt the destination quietly.
$e = WCAC_Source::campus_connect_from_file( $write( "Status\tName\nClosed\tNo id column here\n" ) );
t( is_wp_error( $e ) && 'wcac_file_no_id' === $e->get_error_code(), 'an export with no ID column is refused', $pass, $fail );

$e = WCAC_Source::campus_connect_from_file( $write( $head . "\n" . "too\tfew\tcells\n" ) );
t( is_wp_error( $e ) && 'wcac_file_ragged' === $e->get_error_code(), 'a ragged row is refused rather than padded', $pass, $fail );

$e = WCAC_Source::campus_connect_from_file( $write( $head . "\n" ) );
t( is_wp_error( $e ) && 'wcac_file_empty' === $e->get_error_code(), 'a header with no rows is refused', $pass, $fail );

$e = WCAC_Source::campus_connect_from_file( '/no/such/file.tsv' );
t( is_wp_error( $e ) && 'wcac_file_unreadable' === $e->get_error_code(), 'an unreadable path is refused', $pass, $fail );

// A BOM must not ride along on the first header name.
$rows = WCAC_Source::campus_connect_from_file( $write( "\xEF\xBB\xBF" . "Status\tID\nClosed\t7\n" ) );
t( is_array( $rows ) && isset( $rows[0]['Status'] ), 'a UTF-8 BOM does not break the first column name', $pass, $fail );

// The override is process-local and starts empty, so cron can never inherit it.
t( '' === WCAC_Source::report_file(), 'no file override is set by default', $pass, $fail );
WCAC_Source::set_report_file( $tmp );
t( $tmp === WCAC_Source::report_file(), 'an override can be set', $pass, $fail );
WCAC_Source::set_report_file( '' );
t( '' === WCAC_Source::report_file(), 'and cleared', $pass, $fail );

@unlink( $tmp );


// --------------------------------------------------------- HTML entity decoding
//
// Invented institution names only. This file is mirrored to a public
// repository, and most Campus Connect rows are applications Central does not
// list, so a real name borrowed from the report publishes somebody's pending
// or declined application. bin/check-mirror-safe.php in wpcc-tracker refuses
// the push when one appears; it caught a real name here on 9 Oct 2026.
echo "\nentity decoding:\n";

// The report encodes what it returns. Stored raw, these sat in Airtable looking
// like markup to anyone reading the table.
t( "St. Nobody's College" === WCAC_Mapper::campus_text( 'St. Nobody&#039;s College' ), "&#039; becomes an apostrophe", $pass, $fail );
t( 'Wonderland Library & Institute' === WCAC_Mapper::campus_text( 'Wonderland Library &amp; Institute' ), '&amp; becomes an ampersand', $pass, $fail );
t( 'A "quoted" name' === WCAC_Mapper::campus_text( 'A &quot;quoted&quot; name' ), '&quot; becomes a quote mark', $pass, $fail );

// Idempotent, which is the point: an encoded value never matched the decoded
// cell beside it, so every run reported the same cell as changed.
$once = WCAC_Mapper::campus_text( 'St. Nobody&#039;s College' );
t( $once === WCAC_Mapper::campus_text( $once ), 'decoding an already-decoded value changes nothing', $pass, $fail );

// Decode must precede the limit, or a truncation lands mid-entity.
t(
    'AAAA&' === WCAC_Mapper::campus_text( 'AAAA&amp;BBBB', 5 ),
    'a length limit cannot cut an entity in half',
    $pass,
    $fail
);

// And precede the control-character strip, so an entity decoding to one is caught.
t( false === strpos( WCAC_Mapper::campus_text( 'before&#09;after' ), "\t" ), 'an entity decoding to a control character is still stripped', $pass, $fail );

// A cell holding only an encoded space is empty, and the omit rule should keep
// whatever is already in Airtable.
t( '' === WCAC_Mapper::campus_text( '&nbsp;' ), 'a non-breaking space alone decodes to nothing', $pass, $fail );

// URLs carry entities in query strings, where they break the link.
t(
    'https://events.wordpress.org/x/?a=1&b=2' === WCAC_Mapper::campus_url( 'https://events.wordpress.org/x/?a=1&amp;b=2' ),
    'an &amp; in a query string is decoded, not stored',
    $pass,
    $fail
);
t( '' === WCAC_Mapper::campus_url( 'not a url &amp; not one after decoding' ), 'decoding does not turn rubbish into a URL', $pass, $fail );

// End to end, through the mapper the sync actually calls.
$row = array( 'ID' => '99', 'Status' => 'Closed', 'Venue Name' => 'St. Nobody&#039;s College, Atlantis' );
$out = WCAC_Mapper::campus_event( $row, $allowed );
t(
    isset( $out['fields']['Institution Name'] ) && "St. Nobody's College, Atlantis" === $out['fields']['Institution Name'],
    'campus_event writes the decoded institution name',
    $pass,
    $fail
);


/*
 * campus_coords(): the public-route half of the Campus Connect snapshot.
 *
 * central.wordcamp.org/wp-json/wp/v2/wordcamps is unauthenticated and returns
 * every public WordCamp, Campus Connect events among them, each carrying
 * _venue_coordinates. The Airtable base reached the same subset through a
 * formula field, IF(FIND("campus connect", LOWER({Name})), ...), so the name
 * match here is a deliberate copy of that rule and not a new invention.
 */
$posts = array(
    array(
        'id'                 => 11067837,
        'title'              => 'WordPress Campus Connect Jinja',
        'URL'                => 'https://events.wordpress.org/campusconnect/2025/jinja/',
        'link'               => 'https://central.wordcamp.org/wordcamps/jinja/',
        '_venue_coordinates' => array( 'latitude' => 0.4478, 'longitude' => 33.2026 ),
        'Organizer Name'     => 'Nobody At All',
    ),
);
$coords = WCAC_Mapper::campus_coords( $posts );

/* PHP canonicalises an integer-like string key to int, so the assertion that
   matters is not the key's type but that a lookup by the ID exactly as the
   Campus Connect report spells it -- a string -- resolves. That is the join
   the tracker performs. */
t( 1 === count( $coords ) && isset( $coords['11067837'] ), 'a Campus Connect post is found by its WordCamp ID as the report spells it', $pass, $fail );
t( 0.4478 === $coords['11067837']['lat'], 'latitude is carried as a float', $pass, $fail );
t( 33.2026 === $coords['11067837']['lng'], 'longitude is carried as a float', $pass, $fail );
t( 'https://events.wordpress.org/campusconnect/2025/jinja/' === $coords['11067837']['site'], 'site comes from the URL field', $pass, $fail );
t( array( 'lat', 'lng', 'site' ) === array_keys( $coords['11067837'] ), 'nothing but lat, lng and site is carried', $pass, $fail );
t( false === strpos( wp_json_encode( $coords ), 'Nobody' ), 'organiser data never reaches the coordinate map', $pass, $fail );

// Default-deny on the name: the WordCamps route is 94% regular WordCamps.
$mixed = WCAC_Mapper::campus_coords(
    array(
        array( 'id' => 1, 'title' => 'WordCamp Europe 2026', '_venue_coordinates' => array( 'latitude' => 1.0, 'longitude' => 2.0 ) ),
        array( 'id' => 2, 'title' => 'WordPress Campus Connect Puttur', '_venue_coordinates' => array( 'latitude' => 1.0, 'longitude' => 2.0 ) ),
    )
);
t( 1 === count( $mixed ) && isset( $mixed['2'] ), 'a regular WordCamp is excluded; only Campus Connect is kept', $pass, $fail );

t(
    isset(
        WCAC_Mapper::campus_coords(
            array( array( 'id' => 3, 'title' => 'wordpress CAMPUS connect Somewhere', '_venue_coordinates' => array( 'latitude' => 1.0, 'longitude' => 2.0 ) ) )
        )['3']
    ),
    'the name match ignores case, as LOWER() did in the formula',
    $pass,
    $fail
);

t(
    isset(
        WCAC_Mapper::campus_coords(
            array( array( 'id' => 4, 'title' => array( 'rendered' => 'Campus Connect St. Nobody&#039;s' ), '_venue_coordinates' => array( 'latitude' => 1.0, 'longitude' => 2.0 ) ) )
        )['4']
    ),
    'a rendered title object with an entity still matches',
    $pass,
    $fail
);

// Coordinates are the whole point of this fetch, so a post without usable
// ones is dropped rather than carried as a marker at (0,0) off Ghana.
t( array() === WCAC_Mapper::campus_coords( array( array( 'id' => 5, 'title' => 'Campus Connect Nowhere' ) ) ), 'a post with no coordinates at all is dropped', $pass, $fail );
t(
    array() === WCAC_Mapper::campus_coords(
        array( array( 'id' => 6, 'title' => 'Campus Connect Nowhere', '_venue_coordinates' => array( 'latitude' => '', 'longitude' => '' ) ) )
    ),
    'blank coordinates are dropped',
    $pass,
    $fail
);
t(
    array() === WCAC_Mapper::campus_coords(
        array( array( 'id' => 7, 'title' => 'Campus Connect Nowhere', '_venue_coordinates' => array( 'latitude' => 'n/a', 'longitude' => 'n/a' ) ) )
    ),
    'non-numeric coordinates are dropped, never cast to 0.0',
    $pass,
    $fail
);

// 0 is a real latitude. Kampala sits at 0.3, Quito at -0.2, and a falsy check
// would quietly drop the equator.
$equator = WCAC_Mapper::campus_coords(
    array( array( 'id' => 8, 'title' => 'Campus Connect Equator', '_venue_coordinates' => array( 'latitude' => 0, 'longitude' => 32.58 ) ) )
);
t( isset( $equator['8'] ) && 0.0 === $equator['8']['lat'], 'a latitude of exactly 0 is kept, not treated as missing', $pass, $fail );

$fallback = WCAC_Mapper::campus_coords(
    array(
        array(
            'id'                 => 9,
            'title'              => 'Campus Connect No Site Yet',
            'URL'                => '',
            'link'               => 'https://central.wordcamp.org/wordcamps/nosite/',
            '_venue_coordinates' => array( 'latitude' => 1.0, 'longitude' => 2.0 ),
        ),
    )
);
t( 'https://central.wordcamp.org/wordcamps/nosite/' === $fallback['9']['site'], 'site falls back to the Central link when the event has no site of its own', $pass, $fail );

t( array() === WCAC_Mapper::campus_coords( array() ), 'no posts yields no coordinates', $pass, $fail );
t( array() === WCAC_Mapper::campus_coords( array( 'not an array' ) ), 'a malformed entry is skipped, not fatal', $pass, $fail );
t(
    array() === WCAC_Mapper::campus_coords(
        array( array( 'id' => 0, 'title' => 'Campus Connect No ID', '_venue_coordinates' => array( 'latitude' => 1.0, 'longitude' => 2.0 ) ) )
    ),
    'a post with no usable ID is dropped',
    $pass,
    $fail
);


// ------------------------------------------------- public coordinates fetch
echo "\npublic WordCamps route:\n";

if ( ! defined( 'WCAC_VERSION' ) ) {
    define( 'WCAC_VERSION', 'test' );
}
if ( ! function_exists( 'untrailingslashit' ) ) {
    function untrailingslashit( $s ) { return rtrim( (string) $s, '/\\' ); } // phpcs:ignore
}
if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '' ) { return 'https://example.test' . $path; } // phpcs:ignore
}
if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $args, $url ) { // phpcs:ignore
        return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
    }
}
if ( ! class_exists( 'WCAC_Settings' ) ) {
    class WCAC_Settings { // phpcs:ignore
        public static function source_root() { return 'https://central.wordcamp.org'; }
    }
}

$GLOBALS['wcac_http'] = array( 'queue' => array(), 'calls' => array() );

if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = array() ) { // phpcs:ignore
        $GLOBALS['wcac_http']['calls'][] = array( 'url' => $url, 'args' => $args );
        if ( empty( $GLOBALS['wcac_http']['queue'] ) ) {
            return new WP_Error( 'wcac_test_no_stub', 'no queued response for ' . $url );
        }
        return array_shift( $GLOBALS['wcac_http']['queue'] );
    }
    function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) && isset( $r['code'] ) ? (int) $r['code'] : 0; } // phpcs:ignore
    function wp_remote_retrieve_body( $r ) { return is_array( $r ) && isset( $r['body'] ) ? (string) $r['body'] : ''; } // phpcs:ignore
    function wp_remote_retrieve_header( $r, $h ) { // phpcs:ignore
        $h = strtolower( $h );
        return is_array( $r ) && isset( $r['headers'][ $h ] ) ? $r['headers'][ $h ] : '';
    }
}

/**
 * Queue one stub HTTP response.
 *
 * @param array $posts Post payloads for the body.
 * @param int   $pages Value for the X-WP-TotalPages header.
 * @param int   $code  HTTP status.
 */
function wcac_queue_page( array $posts, $pages = 1, $code = 200 ) {
    $GLOBALS['wcac_http']['queue'][] = array(
        'code'    => $code,
        'body'    => wp_json_encode( $posts ),
        'headers' => array( 'x-wp-totalpages' => (string) $pages ),
    );
}

/** Reset the stub transport between cases. */
function wcac_reset_http() {
    $GLOBALS['wcac_http'] = array( 'queue' => array(), 'calls' => array() );
}

/**
 * One Campus Connect post payload.
 *
 * @param int    $id   WordCamp ID.
 * @param string $name Event title.
 * @return array
 */
function wcac_cc_post( $id, $name = 'WordPress Campus Connect Somewhere' ) {
    return array(
        'id'                 => $id,
        'title'              => array( 'rendered' => $name ),
        'URL'                => 'https://events.wordpress.org/campusconnect/' . $id . '/',
        'link'               => 'https://central.wordcamp.org/wordcamps/' . $id . '/',
        '_venue_coordinates' => array( 'latitude' => 12.5, 'longitude' => 77.5 ),
        'Organizer Name'     => 'Nobody At All',
    );
}

$source = new WCAC_Source();

wcac_reset_http();
wcac_queue_page( array( wcac_cc_post( 101 ), wcac_cc_post( 102 ) ), 1 );
$got = $source->campus_coords();
t( is_array( $got ) && 2 === count( $got ), 'a single page of results is mapped', $pass, $fail );
t( isset( $got['101'], $got['102'] ), 'and keyed by WordCamp ID', $pass, $fail );
t( 1 === count( $GLOBALS['wcac_http']['calls'] ), 'one page means one request', $pass, $fail );

/* The whole reason the map can leave Airtable: this route is public, so the
   request must not depend on a credential. If an Authorization header ever
   appears here, a 401 on the report would start taking the map down with it. */
$sent = $GLOBALS['wcac_http']['calls'][0]['args'];
$hdrs = isset( $sent['headers'] ) && is_array( $sent['headers'] ) ? array_change_key_case( $sent['headers'], CASE_LOWER ) : array();
t( ! isset( $hdrs['authorization'] ), 'the coordinate request sends no Authorization header', $pass, $fail );
t( false !== strpos( $GLOBALS['wcac_http']['calls'][0]['url'], '/wp-json/wp/v2/wordcamps' ), 'it asks the public wordcamps route', $pass, $fail );

/* Today the search returns 99 of a 100-row page, one short of spilling over.
   Following X-WP-TotalPages is therefore not a hypothetical: the next handful
   of events will make this two pages. */
wcac_reset_http();
wcac_queue_page( array( wcac_cc_post( 201 ) ), 2 );
wcac_queue_page( array( wcac_cc_post( 202 ) ), 2 );
$got = $source->campus_coords();
t( is_array( $got ) && 2 === count( $got ), 'a second page is followed and merged', $pass, $fail );
t( 2 === count( $GLOBALS['wcac_http']['calls'] ), 'exactly two requests were made', $pass, $fail );
t( isset( $got['202'] ), 'the second page contributes its events', $pass, $fail );

wcac_reset_http();
wcac_queue_page( array( wcac_cc_post( 301 ), array( 'id' => 302, 'title' => array( 'rendered' => 'WordCamp Europe 2027' ), '_venue_coordinates' => array( 'latitude' => 1.0, 'longitude' => 2.0 ) ) ), 1 );
$got = $source->campus_coords();
t( is_array( $got ) && 1 === count( $got ) && isset( $got['301'] ), 'a regular WordCamp in the search results is filtered out', $pass, $fail );

wcac_reset_http();
$e = $source->campus_coords();
t( is_wp_error( $e ), 'a transport error is returned, not swallowed into an empty map', $pass, $fail );

wcac_reset_http();
$GLOBALS['wcac_http']['queue'][] = array( 'code' => 200, 'body' => wp_json_encode( array( 'code' => 'rest_no_route' ) ), 'headers' => array() );
$e = $source->campus_coords();
t( is_wp_error( $e ) && 'wcac_coords_shape' === $e->get_error_code(), 'a body that is not a list of posts is refused', $pass, $fail );

wcac_reset_http();
$GLOBALS['wcac_http']['queue'][] = array( 'code' => 500, 'body' => '', 'headers' => array() );
$e = $source->campus_coords();
t( is_wp_error( $e ), 'an HTTP failure is returned', $pass, $fail );

/* A route that reports an absurd page count must not be followed forever. */
wcac_reset_http();
for ( $i = 0; $i < 40; $i++ ) {
    wcac_queue_page( array( wcac_cc_post( 400 + $i ) ), 999 );
}
$got = $source->campus_coords();
t( is_array( $got ) && count( $GLOBALS['wcac_http']['calls'] ) <= 20, 'runaway pagination is capped', $pass, $fail );


// ------------------------------------------------------------------ snapshot
echo "\nin-process snapshot:\n";

/*
 * The snapshot is what the tracker reads instead of Airtable. Its rows must
 * arrive in the shape WPCCT_Normalize already consumes -- the Campus Connect
 * report's own column names -- because that is the shape its fixtures are in
 * and the shape the Airtable path hand-translated to. Building it from
 * campus_event()'s mapped fields rather than from the raw report is
 * deliberate: that is where status slugs become labels, entities are decoded
 * and Anticipated Attendees is kept as free text.
 */
$report_row = array(
    'ID'                              => '15821491',
    'Status'                          => 'wcpt-closed',
    'Name'                            => 'WordPress Campus Connect Example',
    'Venue Name'                      => 'Example Institute &amp; College',
    '_venue_city'                     => 'Kolkata',
    '_venue_country_name'             => 'India',
    'Start Date (YYYY-mm-dd)'         => '1747180800',
    'Number of Anticipated Attendees' => '80-100',
    'Actual Attendees'                => '93',
    'URL'                             => 'https://events.wordpress.org/campusconnect/2026/example/',
    'Organizer Name'                  => 'Nobody At All',
);

$mapped   = WCAC_Mapper::campus_event( $report_row, $allowed );
$snapshot = WCAC_Mapper::campus_snapshot(
    array( $mapped['fields'] ),
    array( '15821491' => array( 'lat' => 22.57, 'lng' => 88.36, 'site' => 'https://events.wordpress.org/campusconnect/2026/example/' ) ),
    '2026-10-30T09:00:00+00:00'
);

t( isset( $snapshot['rows'], $snapshot['coords'], $snapshot['read_at'] ), 'a snapshot carries rows, coords and read_at', $pass, $fail );
t( '2026-10-30T09:00:00+00:00' === $snapshot['read_at'], 'read_at is carried verbatim', $pass, $fail );
t( 1 === count( $snapshot['rows'] ), 'one mapped event makes one row', $pass, $fail );

$row = $snapshot['rows'][0];

/* Exactly the report's own column order, so the tracker's normaliser and its
   frozen fixtures see the same shape from either source. */
t(
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
        'URL',
        'ID',
    ) === array_keys( $row ),
    'a snapshot row carries the report column names, in the report order',
    $pass,
    $fail
);

t( 'Closed' === $row['Status'], 'the status slug has become the label the tracker buckets on', $pass, $fail );
t( '15821491' === $row['ID'], 'ID is a string, as the report gives it', $pass, $fail );
t( 'Example Institute & College' === $row['Institution Name'], 'the institution name is decoded, not entity-encoded', $pass, $fail );
t( '80-100' === $row['Number of Anticipated Attendees'], 'anticipated attendees stays free text', $pass, $fail );
t( '93' === $row['Actual Attendees'] || 93 === $row['Actual Attendees'], 'actual attendees is carried', $pass, $fail );
t( '' === $row['End Date (YYYY-mm-dd)'], 'a key the report omitted is present and empty, never missing', $pass, $fail );

/* Synced At is the connector's own stamp on the Airtable row. The snapshot
   reports its age through read_at instead, so the column must not ride along
   and be mistaken for report data. */
t( ! array_key_exists( 'Synced At', $row ), "the connector's own Synced At stamp does not leak into a snapshot row", $pass, $fail );

t( false === strpos( wp_json_encode( $snapshot ), 'Nobody' ), 'no organiser data reaches the snapshot', $pass, $fail );
t( 0 === preg_match( '/organi/i', wp_json_encode( $snapshot ) ), 'no organiser key reaches the snapshot', $pass, $fail );
t( isset( $snapshot['coords']['15821491']['lat'] ), 'coordinates are carried under the WordCamp ID', $pass, $fail );

// A row that never got an ID cannot be joined or counted, so it is dropped.
$dropped = WCAC_Mapper::campus_snapshot( array( array( 'Status' => 'Closed', 'Name' => 'No id' ) ), array(), '2026-10-30T09:00:00+00:00' );
t( array() === $dropped['rows'], 'a mapped row with no WordCamp ID is dropped from the snapshot', $pass, $fail );

t( array() === WCAC_Mapper::campus_snapshot( array(), array(), '' )['rows'], 'no events makes an empty row list, not a fatal', $pass, $fail );


// --------------------------------------------------------- snapshot refusals
echo "\nsnapshot refusals:\n";

require __DIR__ . '/../includes/class-wcac-campus-snapshot.php';

/**
 * Build a snapshot of a given size for the guard tests.
 *
 * @param int $rows   Row count.
 * @param int $coords Coordinate count.
 * @return array
 */
function wcac_snap( $rows, $coords ) {
    $r = array();
    for ( $i = 0; $i < $rows; $i++ ) {
        $r[] = array( 'ID' => (string) ( 1000 + $i ), 'Status' => 'Closed' );
    }
    $c = array();
    for ( $i = 0; $i < $coords; $i++ ) {
        $c[ (string) ( 1000 + $i ) ] = array( 'lat' => 1.0, 'lng' => 2.0, 'site' => '' );
    }
    return array( 'read_at' => '2026-10-30T09:00:00+00:00', 'rows' => $r, 'coords' => $c );
}

t( '' === WCAC_Campus_Snapshot::refuse_reason( wcac_snap( 177, 92 ), array() ), 'a healthy first snapshot is accepted', $pass, $fail );
t( '' === WCAC_Campus_Snapshot::refuse_reason( wcac_snap( 177, 92 ), wcac_snap( 171, 90 ) ), 'normal growth is accepted', $pass, $fail );

/* The report carries 177 rows. A handful means a broken query or a
   maintenance page, and storing it would blank the dashboard. */
t( '' !== WCAC_Campus_Snapshot::refuse_reason( wcac_snap( 3, 92 ), array() ), 'a snapshot with almost no rows is refused', $pass, $fail );
t( '' !== WCAC_Campus_Snapshot::refuse_reason( wcac_snap( 0, 92 ), array() ), 'a snapshot with no rows is refused', $pass, $fail );

/* Coordinates are the map. Empty means the name match or the search stopped
   working, not that the programme lost its venues. */
t( '' !== WCAC_Campus_Snapshot::refuse_reason( wcac_snap( 177, 0 ), array() ), 'a snapshot with no coordinates is refused', $pass, $fail );

/* A halving cannot be legitimate: public events do not get unpublished in
   bulk. A smaller wobble can be, so it must not be refused -- refusing means
   serving staler data, which is the worse failure. */
t( '' !== WCAC_Campus_Snapshot::refuse_reason( wcac_snap( 177, 40 ), wcac_snap( 177, 92 ) ), 'coordinates collapsing to under half the stored map is refused', $pass, $fail );
t( '' === WCAC_Campus_Snapshot::refuse_reason( wcac_snap( 177, 85 ), wcac_snap( 177, 92 ) ), 'a small coordinate dip is accepted rather than serving stale data', $pass, $fail );

/* The same rule for rows, in case the connector's own volume latch is ever
   waived with --accept-growth in the downward direction. */
t( '' !== WCAC_Campus_Snapshot::refuse_reason( wcac_snap( 80, 92 ), wcac_snap( 177, 92 ) ), 'rows collapsing to under half the stored count is refused', $pass, $fail );

t( '' !== WCAC_Campus_Snapshot::refuse_reason( array(), array() ), 'a malformed snapshot is refused', $pass, $fail );
t( '' !== WCAC_Campus_Snapshot::refuse_reason( array( 'rows' => 'nope', 'coords' => array() ), array() ), 'a snapshot whose rows are not a list is refused', $pass, $fail );

printf( "\n%d passed, %d failed\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
