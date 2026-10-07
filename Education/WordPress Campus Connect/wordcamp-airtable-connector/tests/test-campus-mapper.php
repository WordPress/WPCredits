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

printf( "\n%d passed, %d failed\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
