<?php
/**
 * The three outcome figures the site counts for its homepage (1.122.24): the definitions, the read,
 * the guards, what may be shown, the weekly run and the syncs row.
 *
 * The figures must equal the public impact dashboard's, so the definitions are pinned here on
 * fixture rows shaped as Airtable answers with fields by ID: which records count, how a cell is read,
 * how each share is rounded. Nothing touches the network: Airtable and the dashboard's page are
 * answered by stand-ins, and the clock is the suite's own.
 *
 * Every record ID is invented, uppercase letters and digits after `rec`, so the release scan passes
 * it.
 *
 * Run from the plugin root:  php bin/test-outcome-figures.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );

$GLOBALS['opts']     = array(); // Option => value.
$GLOBALS['autoload'] = array(); // Option => whether it was written autoloaded.
$GLOBALS['filters']  = array(); // Hook => callback.
$GLOBALS['now']      = 1791788400; // Monday 12 October 2026, 07:00 UTC.
$GLOBALS['tables']   = array(); // Table ID => its records, as Airtable holds them.
$GLOBALS['sent']     = array(); // Every request the client sent: its address.
$GLOBALS['methods']  = array(); // Every request the client sent: its HTTP method, beside its address.
$GLOBALS['refuse']   = null;    // A canned answer for the request named by `refuse_at`, or null.
$GLOBALS['endless']  = false;   // Whether a table keeps paging for ever.
$GLOBALS['cron']     = array(); // Hook => the event: `at`, and `recurrence`, false for a single event.
$GLOBALS['actions']  = array(); // Hook => the callbacks added to it.
$GLOBALS['page']     = null;    // What the public dashboard's page answers.
$GLOBALS['visits']   = array(); // Every read of that page: its address and arguments.
$GLOBALS['meta']     = null;    // What the base's table metadata answers.
$GLOBALS['refused']  = array(); // Options the database will not write: update_option() says false and keeps the old value.
$GLOBALS['stop']     = null;    // A request, named as `refuse` names one, at which the process is stopped inside the read.
$GLOBALS['asked']    = array(); // Every hook wp_next_scheduled() was asked about.
$GLOBALS['transients'] = array(); // Transient => its value: the schema copy the track editor reads.

/* ---- WordPress, as far as the figures reach ---------------------------------- */

class WP_Error {
	private $code, $message, $data;
	public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, $decimals ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function trailingslashit( $text ) { return rtrim( (string) $text, '/' ) . '/'; }
function wp_doing_cron() { return false; }
function apply_filters( $hook, $value ) { return isset( $GLOBALS['filters'][ $hook ] ) ? call_user_func( $GLOBALS['filters'][ $hook ], $value ) : $value; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) {
	// As WordPress does: false for a write the database refused, and for a value already there.
	if ( in_array( $key, $GLOBALS['refused'], true ) || ( array_key_exists( $key, $GLOBALS['opts'] ) && $GLOBALS['opts'][ $key ] === $value ) ) {
		return false;
	}

	$GLOBALS['opts'][ $key ] = $value;

	if ( null !== $autoload ) {
		$GLOBALS['autoload'][ $key ] = (bool) $autoload;
	}

	return true;
}
function add_option( $key, $value = '', $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $key, $GLOBALS['opts'] ) ) {
		return false;
	}

	return update_option( $key, $value, $autoload );
}
function delete_option( $key ) { unset( $GLOBALS['opts'][ $key ], $GLOBALS['autoload'][ $key ] ); return true; }
function get_transient( $key ) { return array_key_exists( $key, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $key ] : false; }
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['actions'][ $hook ][] = $callback; return true; }
function wp_next_scheduled( $hook ) { $GLOBALS['asked'][] = $hook; return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ]['at'] : false; }
function wp_schedule_event( $at, $recurrence, $hook ) { $GLOBALS['cron'][ $hook ] = array( 'at' => (int) $at, 'recurrence' => $recurrence ); return true; }
function wp_schedule_single_event( $at, $hook ) { $GLOBALS['cron'][ $hook ] = array( 'at' => (int) $at, 'recurrence' => false ); return true; }
function wp_clear_scheduled_hook( $hook ) { $GLOBALS['cleared'][] = $hook; unset( $GLOBALS['cron'][ $hook ] ); return 0; }
function wp_safe_remote_get( $url, $args = array() ) {
	$GLOBALS['visits'][] = array( $url, $args );
	$page                = $GLOBALS['page'];

	// As WordPress's Requests does: a body past the limit is cut there, and no error is said.
	if ( is_array( $page ) && isset( $args['limit_response_size'] ) ) {
		$page['body'] = substr( $page['body'], 0, (int) $args['limit_response_size'] );
	}

	return $page;
}
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }

/**
 * Airtable, answering from `$GLOBALS['tables']`, a hundred records a page, as it pages: an offset
 * while records remain. `$GLOBALS['refuse']` answers one request in its place, named by table and
 * page; `$GLOBALS['endless']` makes every page of every table end with an offset. Each request's
 * address and HTTP method are recorded, so a check can say a read never wrote.
 *
 * @param string $url  The request's address.
 * @param array  $args Its arguments.
 * @return array|WP_Error
 */
function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['sent'][]    = $url;
	$GLOBALS['methods'][] = isset( $args['method'] ) ? (string) $args['method'] : 'GET';

	if ( false !== strpos( $url, '/meta/bases/' ) ) {
		return $GLOBALS['meta'];
	}

	$path  =(string) parse_url( $url, PHP_URL_PATH );
	$table = substr( $path, strrpos( $path, '/' ) + 1 );
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
	$page  = isset( $query['offset'] ) ? (int) substr( $query['offset'], 3 ) : 0;

	if ( is_array( $GLOBALS['refuse'] ) && $GLOBALS['refuse']['table'] === $table && $GLOBALS['refuse']['page'] === $page ) {
		return $GLOBALS['refuse']['answer'];
	}

	// A fatal, a memory or time limit, or the host's runner stopping the process: nothing after it runs.
	if ( is_array( $GLOBALS['stop'] ) && $GLOBALS['stop']['table'] === $table && $GLOBALS['stop']['page'] === $page ) {
		throw new RuntimeException( 'stopped inside the read' );
	}

	$records = isset( $GLOBALS['tables'][ $table ] ) ? $GLOBALS['tables'][ $table ] : array();
	$body    = array( 'records' => array_slice( $records, 100 * $page, 100 ) );

	if ( $GLOBALS['endless'] || count( $records ) > 100 * ( $page + 1 ) ) {
		$body['offset'] = 'itr' . ( $page + 1 );
	}

	return array(
		'response' => array( 'code' => 200 ),
		'headers'  => array(),
		'body'     => json_encode( $body ),
	);
}
function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? $response['response']['code'] : 0; }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? $response['body'] : ''; }
function wp_remote_retrieve_header( $response, $header ) { return ''; }

/** The mentors sync's column names, the status column the figures' formula filters on among them. */
class WPCPM_Mentors_Sync {
	public static $status = 'Status';
	public static function fields() { return array( 'report_status' => self::$status ); }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-outcome-figures.php';

// No request really waits: Airtable's pacing runs on the suite's clock.
WPCPM_Airtable::for_tests(
	static function () { return (float) $GLOBALS['now']; },
	static function ( $seconds ) { $GLOBALS['now'] += (int) ceil( $seconds ); },
	static function () { return true; }
);
WPCPM_Outcome_Figures::for_tests( static function () { return $GLOBALS['now']; } );

$fail  = 0;
$total = 0;

/**
 * One check.
 *
 * @param string $label    What is asked.
 * @param mixed  $actual   What happened.
 * @param mixed  $expected What should have happened.
 */
function ck( $label, $actual, $expected ) {
	global $fail, $total;

	++$total;

	$ok = $actual === $expected;

	if ( ! $ok ) {
		++$fail;
	}

	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";

	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n";
		echo '       actual:   ' . var_export( $actual, true ) . "\n";
	}
}

/**
 * A Students Reports record as Airtable sends it with fields by ID.
 *
 * @param int   $i      Which record, for its invented ID.
 * @param mixed $name   The name cell, or the string 'absent' for a record without one.
 * @param mixed $status The status cell, or the string 'absent'.
 * @return array
 */
function report( $i, $name, $status ) {
	$fields = array();

	if ( 'absent' !== $name ) {
		$fields[ WPCPM_Outcome_Figures::FIELD_NAME ] = $name;
	}

	if ( 'absent' !== $status ) {
		$fields[ WPCPM_Outcome_Figures::FIELD_STATUS ] = $status;
	}

	return array(
		'id'     => sprintf( 'recREPORT%08d', $i ),
		'fields' => $fields,
	);
}

/**
 * A Feedback record with the two answers, each a cell or 'absent'.
 *
 * @param int   $i         Which record.
 * @param mixed $recommend The recommend answer.
 * @param mixed $keep      The keep-contributing answer.
 * @return array
 */
function answer( $i, $recommend, $keep = 'absent' ) {
	$fields = array();

	if ( 'absent' !== $recommend ) {
		$fields[ WPCPM_Outcome_Figures::FIELD_RECOMMEND ] = $recommend;
	}

	if ( 'absent' !== $keep ) {
		$fields[ WPCPM_Outcome_Figures::FIELD_KEEP ] = $keep;
	}

	return array(
		'id'     => sprintf( 'recFEEDBK%08d', $i ),
		'fields' => $fields,
	);
}

/**
 * Records of one shape, many times over.
 *
 * @param int      $times How many.
 * @param callable $make  Builds record $i.
 * @return array[]
 */
function many( $times, $make ) {
	$records = array();

	for ( $i = 0; $i < $times; $i++ ) {
		$records[] = $make( $i );
	}

	return $records;
}

echo "=== The definitions, the public dashboard's ===\n";

ck( 'the four field IDs are the dashboard\'s own',
	array( WPCPM_Outcome_Figures::FIELD_NAME, WPCPM_Outcome_Figures::FIELD_STATUS, WPCPM_Outcome_Figures::FIELD_RECOMMEND, WPCPM_Outcome_Figures::FIELD_KEEP ),
	array( 'fldyXVlkChJaO9Q47', 'fldCMdqqJGAUQ9nbV', 'fldZXbvOTIGad6r90', 'fld8RZMdCLYKtwcLN' ) );

$reports = array(
	report( 1, 'Ann Example', 'Graduate' ),
	report( 2, 'Ben Example', ' graduate ' ),
	report( 3, 'Cy Example', 'GRADUATE' ),
	report( 4, 'Di Example', array( 'id' => 'selGRAD', 'name' => 'Graduate', 'color' => 'greenLight2' ) ),
	report( 5, ' ', 'Graduate' ),
	report( 6, array( 'Lookup Example' ), 'Graduate' ),
	report( 7, 'Ed Example', 'Dropped out' ),
	report( 8, 'Flo Example', ' dropped OUT ' ),
	report( 9, 'absent', 'Graduate' ),
	report( 10, '', 'Graduate' ),
	report( 11, 0, 'Dropped out' ),
	report( 12, array(), 'Graduate' ),
	report( 13, null, 'Graduate' ),
	report( 14, 'Gil Example', 'Not moving forward' ),
	report( 15, 'Hal Example', 'Paused' ),
	report( 16, 'Ida Example', 'Fail' ),
	report( 17, 'Jo Example', 'Interested' ),
	report( 18, 'Kit Example', 'SPAM' ),
	report( 19, 'Lou Example', 'In Sensei' ),
	report( 20, 'Mo Example', '' ),
	report( 21, 'Ned Example', 'absent' ),
	report( 22, 'Oz Example', array( 'Graduate' ) ),
	report( 23, 'Pia Example', 1 ),
	report( 24, 'Quin Example', 'Graduated' ),
);

$figures = WPCPM_Outcome_Figures::compute( $reports, array() );

ck( 'graduates are the records with a name whose status, trimmed and lowercased, is graduate: as a string, in any case, with stray spaces, or as a select object; a name of one space counts, and so does a lookup list holding a name',
	$figures['graduation']['count'], 6 );

ck( 'dropouts are those whose status is dropped out, the same way, and n is the two together',
	$figures['graduation']['n'], 8 );

ck( 'a missing, empty, zero, empty-list or null name leaves the record out; every other status, a blank or missing one, a list or a number counts in neither',
	$figures['graduation'], array( 'share' => 75, 'count' => 6, 'n' => 8 ) );

$feedback = array(
	answer( 1, 'Likely', 'Likely' ),
	answer( 2, ' likely ', 'Unlikely' ),
	answer( 3, 'LIKELY', 'Neither likely nor unlikely' ),
	answer( 4, array( 'name' => 'Likely' ), '' ),
	answer( 5, 'Neither likely nor unlikely', '   ' ),
	answer( 6, 'Unlikely', 'absent' ),
	answer( 7, '', null ),
	answer( 8, '   ', array( 'Likely' ) ),
	answer( 9, 'absent', 3 ),
	answer( 10, null, 'likely' ),
	answer( 11, array( 'Likely' ), array( 'name' => ' LIKELY' ) ),
	answer( 12, 3, 'absent' ),
);

$figures = WPCPM_Outcome_Figures::compute( array(), $feedback );

ck( 'would recommend: the answers that are not empty once trimmed are N, and those equal to likely, in any case or shape, are P; neither and unlikely count in N only',
	$figures['recommend'], array( 'share' => 67, 'count' => 4, 'n' => 6 ) );

ck( 'keep contributing: the same over its own question, blank, missing, a list and a number outside N',
	$figures['keep'], array( 'share' => 60, 'count' => 3, 'n' => 5 ) );

ck( 'a Feedback row is never the denominator: twelve rows, six and five answers',
	array( count( $feedback ), $figures['recommend']['n'], $figures['keep']['n'] ), array( 12, 6, 5 ) );

ck( 'with nothing counted, each figure says so: no share, a zero count and a zero n',
	WPCPM_Outcome_Figures::compute( array(), array() ),
	array(
		'graduation' => array( 'share' => null, 'count' => 0, 'n' => 0 ),
		'recommend'  => array( 'share' => null, 'count' => 0, 'n' => 0 ),
		'keep'       => array( 'share' => null, 'count' => 0, 'n' => 0 ),
	) );

ck( 'a record that is not the shape Airtable sends, or whose fields are not a list, is passed over',
	WPCPM_Outcome_Figures::compute( array( 'recBROKEN00000001', array( 'id' => 'recBROKEN00000002' ), array( 'fields' => 'Graduate' ), report( 1, 'Ann Example', 'Graduate' ) ), array( null, 7, answer( 1, 'Likely' ) ) ),
	array(
		'graduation' => array( 'share' => 100, 'count' => 1, 'n' => 1 ),
		'recommend'  => array( 'share' => 100, 'count' => 1, 'n' => 1 ),
		'keep'       => array( 'share' => null, 'count' => 0, 'n' => 0 ),
	) );

ck( 'a name is there as Python\'s `if not name` decides: false and 0.0 leave a record out, the string "0" does not',
	WPCPM_Outcome_Figures::compute( array( report( 1, false, 'Graduate' ), report( 2, 0.0, 'Dropped out' ), report( 3, '0', 'Graduate' ), report( 4, 'Ann Example', 'Graduate' ) ), array() )['graduation'],
	array( 'share' => 100, 'count' => 2, 'n' => 2 ) );

// The dashboard strips a cell with Python's str.strip(), which removes every Unicode space, not
// only the ASCII ones. The fixtures spell the characters as escapes: U+00A0 is a no-break space (a
// pasted option name), U+3000 an ideographic space, U+2028 a line separator, U+200B a zero-width
// space, which Python does not count as a space, and "\xFF" a byte that is not UTF-8.
$unicode_reports = array(
	report( 1, 'Ann Example', "Graduate\u{A0}" ),
	report( 2, 'Ben Example', "\u{3000}Graduate" ),
	report( 3, 'Cy Example', "\u{3000}dropped OUT\u{A0}" ),
	report( 4, 'Di Example', array( 'id' => 'selGRAD', 'name' => "\u{A0}Graduate\u{3000}", 'color' => 'greenLight2' ) ),
	report( 5, 'Ed Example', "Gradu\u{A0}ate" ),
	report( 6, 'Flo Example', "Graduate\u{200B}" ),
	report( 7, 'Gil Example', "Graduate\xFF" ),
	report( 8, 'Hal Example', "\u{A0}\u{3000}" ),
);

ck( 'a status cell is stripped as Python strips it: a no-break space at the end and an ideographic space at the start, as a string or in a select object, count; one inside the word, a zero-width space and a byte that is not UTF-8 do not, and a cell of spaces alone is empty',
	WPCPM_Outcome_Figures::compute( $unicode_reports, array() )['graduation'],
	array( 'share' => 75, 'count' => 3, 'n' => 4 ) );

$unicode_feedback = array(
	answer( 1, "Likely\u{A0}", "\u{3000}likely\u{A0}" ),
	answer( 2, "\u{3000}LIKELY", "Neither likely nor unlikely\u{A0}" ),
	answer( 3, "\u{3000}Unlikely\u{A0}", array( 'name' => "\u{A0}Likely" ) ),
	answer( 4, "\u{A0}\u{3000}", "\u{2028}" ),
	answer( 5, "Likely\u{200B}", 'absent' ),
	answer( 6, "Likely\xFF", "Lik\u{A0}ely" ),
);

$unicode_figures = WPCPM_Outcome_Figures::compute( array(), $unicode_feedback );

ck( 'an answer cell is stripped the same way: a no-break space at the end and an ideographic space at the start count, a cell of spaces alone is outside N, and a zero-width space or a byte that is not UTF-8 makes an answer that is not likely',
	array( $unicode_figures['recommend'], $unicode_figures['keep'] ),
	array(
		array( 'share' => 40, 'count' => 2, 'n' => 5 ),
		array( 'share' => 50, 'count' => 2, 'n' => 4 ),
	) );

echo "\n=== Rounding, as each half of the dashboard rounds ===\n";

$one_of_eight = WPCPM_Outcome_Figures::compute(
	array_merge( array( report( 1, 'Ann Example', 'Graduate' ) ), many( 7, function ( $i ) { return report( 100 + $i, 'Example', 'Dropped out' ); } ) ),
	array_merge( array( answer( 1, 'Likely', 'Likely' ) ), many( 7, function ( $i ) { return answer( 100 + $i, 'Unlikely', 'Unlikely' ); } ) )
);

ck( '12.5 gives 13 for the graduation share (half up, the page\'s Math.round) and 12 for a survey share (half to even, Python\'s round)',
	array( $one_of_eight['graduation']['share'], $one_of_eight['recommend']['share'], $one_of_eight['keep']['share'] ), array( 13, 12, 12 ) );

$seven_of_eight = WPCPM_Outcome_Figures::compute(
	array_merge( many( 7, function ( $i ) { return report( 200 + $i, 'Example', 'Graduate' ); } ), array( report( 1, 'Ann Example', 'Dropped out' ) ) ),
	array_merge( many( 7, function ( $i ) { return answer( 200 + $i, 'Likely', 'Likely' ); } ), array( answer( 1, 'Unlikely', 'Unlikely' ) ) )
);

ck( '87.5 gives 88 for both',
	array( $seven_of_eight['graduation']['share'], $seven_of_eight['recommend']['share'], $seven_of_eight['keep']['share'] ), array( 88, 88, 88 ) );

$today = WPCPM_Outcome_Figures::compute(
	array_merge( many( 361, function ( $i ) { return report( 1000 + $i, 'Example', 'Graduate' ); } ), many( 52, function ( $i ) { return report( 2000 + $i, 'Example', 'Dropped out' ); } ) ),
	array_merge(
		many( 245, function ( $i ) { return answer( 3000 + $i, 'Likely', 'Likely' ); } ),
		many( 32, function ( $i ) { return answer( 4000 + $i, 'Likely', 'Neither likely nor unlikely' ); } ),
		many( 33, function ( $i ) { return answer( 5000 + $i, 'Neither likely nor unlikely', 'Unlikely' ); } ),
		many( 5, function ( $i ) { return answer( 6000 + $i, 'Unlikely', '' ); } ),
		many( 519, function ( $i ) { return answer( 7000 + $i, '', '' ); } )
	)
);

ck( 'counts that give the dashboard\'s figures of 12 October 2026: 361 of 413 is 87%, and 277 of 315 and 245 of 310 are numerators its 88% and 79% admit',
	$today,
	array(
		'graduation' => array( 'share' => 87, 'count' => 361, 'n' => 413 ),
		'recommend'  => array( 'share' => 88, 'count' => 277, 'n' => 315 ),
		'keep'       => array( 'share' => 79, 'count' => 245, 'n' => 310 ),
	) );

$shares = array();

foreach ( array( array( 1, 3 ), array( 2, 3 ), array( 1, 200 ), array( 3, 8 ), array( 5, 8 ), array( 199, 200 ), array( 0, 5 ), array( 5, 5 ) ) as $pair ) {
	list( $count, $n ) = $pair;

	$rows     = array_merge( many( $count, function ( $i ) { return answer( 8000 + $i, 'Likely' ); } ), many( $n - $count, function ( $i ) { return answer( 9000 + $i, 'Unlikely' ); } ) );
	$students = array_merge( many( $count, function ( $i ) { return report( 8000 + $i, 'Example', 'Graduate' ); } ), many( $n - $count, function ( $i ) { return report( 9000 + $i, 'Example', 'Dropped out' ); } ) );
	$counted  = WPCPM_Outcome_Figures::compute( $students, $rows );
	$shares[] = $count . '/' . $n . ' ' . $counted['graduation']['share'] . ' ' . $counted['recommend']['share'];
}

ck( 'every other share rounds to the nearest whole number, both halves alike: 33.3, 66.7, 0.5 (half up 1, half to even 0), 37.5 (38 and 38), 62.5 (63 and 62), 99.5 (100 and 100), 0 and 100',
	$shares, array( '1/3 33 33', '2/3 67 67', '1/200 1 0', '3/8 38 38', '5/8 63 62', '199/200 100 100', '0/5 0 0', '5/5 100 100' ) );

$twenty_three_of_forty = WPCPM_Outcome_Figures::compute(
	array_merge( many( 23, function ( $i ) { return report( 300 + $i, 'Example', 'Graduate' ); } ), many( 17, function ( $i ) { return report( 400 + $i, 'Example', 'Dropped out' ); } ) ),
	array()
);

ck( '23 of 40 gives 57, as the page\'s own Math.round( 23 / 40 * 100 ) does: the division comes first, and 57.49999999999999 is under the half',
	$twenty_three_of_forty['graduation'], array( 'share' => 57, 'count' => 23, 'n' => 40 ) );

/* ---- the read, the guards and what is stored ------------------------------------ */

/**
 * A site connected to Airtable, holding what a check says, at Monday 07:00 UTC, nothing sent yet.
 *
 * The base holds `$graduates` and `$dropouts` students who graduated or dropped out (the read's
 * formula has already narrowed it to those), and a Feedback row per student, answered as asked:
 * `$recommend` and `$keep` are each the likely answers and the other answers to that question, and
 * every row past both is blank.
 *
 * @param int   $graduates Students who graduated.
 * @param int   $dropouts  Students who dropped out.
 * @param int[] $recommend Likely, then other answers, to the recommend question.
 * @param int[] $keep      Likely, then other answers, to the keep question.
 * @param int   $rows      Feedback rows in all.
 */
function base( $graduates = 361, $dropouts = 52, $recommend = array( 277, 38 ), $keep = array( 245, 65 ), $rows = 834 ) {
	$GLOBALS['now']     = 1791788400;
	$GLOBALS['sent']    = array();
	$GLOBALS['methods'] = array();
	$GLOBALS['refuse']  = null;
	$GLOBALS['endless'] = false;
	$GLOBALS['filters'] = array();
	$GLOBALS['cron']    = array();
	$GLOBALS['visits']  = array();
	$GLOBALS['refused'] = array();
	$GLOBALS['stop']    = null;
	$GLOBALS['page']    = dashboard( '2026-10-12' );
	$GLOBALS['opts']    = array_intersect_key( $GLOBALS['opts'], array( WPCPM_Outcome_Figures::OPTION => true, WPCPM_Outcome_Figures::RUN_OPTION => true ) );

	$GLOBALS['transients'] = array();

	$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array(
		'api_token' => 'patSTANDIN',
		'base_id'   => 'appSTANDIN',
	);

	$reports = array();

	for ( $i = 0; $i < $graduates + $dropouts; $i++ ) {
		$reports[] = report( $i, 'Student Example', $i < $graduates ? 'Graduate' : 'Dropped out' );
	}

	$feedback = array();

	for ( $i = 0; $i < $rows; $i++ ) {
		$feedback[] = answer(
			$i,
			$i < $recommend[0] ? 'Likely' : ( $i < $recommend[0] + $recommend[1] ? 'Unlikely' : 'absent' ),
			$i < $keep[0] ? 'Likely' : ( $i < $keep[0] + $keep[1] ? 'Neither likely nor unlikely' : 'absent' )
		);
	}

	$GLOBALS['tables'] = array(
		'tbljYkkVGbeoaWEtY' => $reports,
		'tblx3TH6fp4edQJDm' => $feedback,
	);

	WPCPM_Outcome_Figures::for_tests( static function () { return $GLOBALS['now']; } );
}

/**
 * The public dashboard's page as GitHub Pages serves it, its data in `const D`, built on a date.
 *
 * @param string $built `builtAt`, or '' for a page whose data carries none.
 * @param int    $code  The status.
 * @return array
 */
function dashboard( $built, $code = 200 ) {
	$data = '' === $built ? '{"global":{"graduates":361,"dropouts":52}}' : '{"builtAt":"' . $built . '","global":{"graduates":361,"dropouts":52}}';

	return array(
		'response' => array( 'code' => $code ),
		'headers'  => array(),
		'body'     => "<div class=\"footer\"><span id=\"asOf\"></span>Data sourced from Airtable</div>\n<script>\nconst D = " . $data . ";\n</script>",
	);
}

/** A site that has stored nothing yet. */
function forget() {
	unset( $GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ], $GLOBALS['opts'][ WPCPM_Outcome_Figures::RUN_OPTION ], $GLOBALS['opts'][ WPCPM_Outcome_Figures::LOCK_OPTION ] );
}

/**
 * A stored set, as a run writes it.
 *
 * @param int   $read     When it was read.
 * @param int[] $counts   Each figure's share, count and n, in the order graduation, recommend, keep.
 * @return array
 */
function stored( $read, array $counts = array( 87, 361, 413, 88, 277, 315, 79, 245, 310 ) ) {
	return array(
		'read'       => $read,
		'graduation' => array( 'share' => $counts[0], 'count' => $counts[1], 'n' => $counts[2] ),
		'recommend'  => array( 'share' => $counts[3], 'count' => $counts[4], 'n' => $counts[5] ),
		'keep'       => array( 'share' => $counts[6], 'count' => $counts[7], 'n' => $counts[8] ),
	);
}

/**
 * A canned Airtable answer for one request, in the HTTP API's shape.
 *
 * @param int   $code The status.
 * @param array $body The body.
 * @return array
 */
function airtable_answer( $code, array $body = array() ) {
	return array(
		'response' => array( 'code' => $code ),
		'headers'  => array(),
		'body'     => json_encode( $body ),
	);
}

echo "\n=== One run: two reads, every page, fields by ID ===\n";

base();
forget();

$run  = WPCPM_Outcome_Figures::refresh();
$sent = $GLOBALS['sent'];
parse_str( (string) parse_url( $sent[0], PHP_URL_QUERY ), $first );
parse_str( (string) parse_url( $sent[5], PHP_URL_QUERY ), $sixth );

ck( 'Students Reports first, in five pages of 413 rows, then Feedback, in nine of 834: fourteen requests, every one a GET, so a read never writes',
	array( count( $sent ), count( preg_grep( '#/tbljYkkVGbeoaWEtY\?#', $sent ) ), count( preg_grep( '#/tblx3TH6fp4edQJDm\?#', $sent ) ), $run['rows'], array_count_values( $GLOBALS['methods'] ) ),
	array( 14, 5, 9, array( 'reports' => 413, 'feedback' => 834 ), array( 'GET' => 14 ) ) );

ck( 'the first read asks for the name and the status by ID, filtered on the status column, trimmed and lowercased, to the two statuses counted',
	array( $first['returnFieldsByFieldId'] ?? null, $first['fields'] ?? null, $first['filterByFormula'] ?? null ),
	array( 'true', array( 'fldyXVlkChJaO9Q47', 'fldCMdqqJGAUQ9nbV' ), "OR(TRIM(LOWER({Status})) = 'graduate',TRIM(LOWER({Status})) = 'dropped out')" ) );

ck( 'the second asks for the two answers by ID, every row, with no formula, so no column name is depended on',
	array( $sixth['returnFieldsByFieldId'] ?? null, $sixth['fields'] ?? null, array_key_exists( 'filterByFormula', $sixth ) ),
	array( 'true', array( 'fldZXbvOTIGad6r90', 'fld8RZMdCLYKtwcLN' ), false ) );

ck( 'the run stores the three figures',
	array( $run['result'], $run['reason'], $run['message'], $run['figures'] ),
	array(
		'stored',
		'',
		'',
		array(
			'graduation' => array( 'share' => 87, 'count' => 361, 'n' => 413 ),
			'recommend'  => array( 'share' => 88, 'count' => 277, 'n' => 315 ),
			'keep'       => array( 'share' => 79, 'count' => 245, 'n' => 310 ),
		),
	) );

ck( 'the set: each figure\'s share, numerator and n, and the one time all three were read, autoloaded',
	array( get_option( WPCPM_Outcome_Figures::OPTION ), $GLOBALS['autoload'][ WPCPM_Outcome_Figures::OPTION ] ?? null ),
	array( stored( 1791788400 ), true ) );

ck( 'the attempt: its time, its result, a reason and a sentence, and the counts it saw, not autoloaded',
	array( WPCPM_Outcome_Figures::status(), $GLOBALS['autoload'][ WPCPM_Outcome_Figures::RUN_OPTION ] ?? null ),
	array(
		array(
			'time'    => 1791788400,
			'result'  => 'stored',
			'reason'  => '',
			'message' => '',
			'counts'  => array(
				'graduation' => array( 'count' => 361, 'n' => 413 ),
				'recommend'  => array( 'count' => 277, 'n' => 315 ),
				'keep'       => array( 'count' => 245, 'n' => 310 ),
			),
			'monday'  => '2026-10-12',
			'tries'   => 1,
		),
		false,
	) );

$kept = serialize( get_option( WPCPM_Outcome_Figures::OPTION ) ) . serialize( get_option( WPCPM_Outcome_Figures::RUN_OPTION ) ) . serialize( $run ) . serialize( get_option( WPCPM_Outcome_Figures::LOCK_OPTION, 'none' ) );

ck( 'nothing but counts in either option, the result the run returns or the lock: no record ID, no name, no answer, no status, and neither the token nor the word Bearer',
	array( (bool) preg_match( '/rec[A-Z0-9]/', $kept ), false !== stripos( $kept, 'example' ), false !== stripos( $kept, 'likely' ), false !== stripos( $kept, 'graduate"' ), false !== stripos( $kept, 'dropped' ), false !== strpos( $kept, 'patSTANDIN' ), false !== stripos( $kept, 'Bearer' ) ),
	array( false, false, false, false, false, false, false ) );

ck( 'and the lock is given back',
	array_key_exists( WPCPM_Outcome_Figures::LOCK_OPTION, $GLOBALS['opts'] ), false );

base();
forget();
WPCPM_Mentors_Sync::$status = 'Student status';
WPCPM_Outcome_Figures::refresh();
parse_str( (string) parse_url( $GLOBALS['sent'][0], PHP_URL_QUERY ), $renamed );
WPCPM_Mentors_Sync::$status = 'Status';

ck( 'the formula filters on the column the mentors and students syncs filter on, as the site names it',
	$renamed['filterByFormula'] ?? null, "OR(TRIM(LOWER({Student status})) = 'graduate',TRIM(LOWER({Student status})) = 'dropped out')" );

echo "\n=== A failed read stores nothing ===\n";

$failures = array(
	'a 503'                 => array( 'tblx3TH6fp4edQJDm', 3, airtable_answer( 503 ), 'unreachable', 'Failed: Airtable request failed (HTTP 503): no further detail' ),
	'a timeout'             => array( 'tbljYkkVGbeoaWEtY', 0, new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 20001 milliseconds' ), 'unreachable', 'Failed: cURL error 28: Operation timed out after 20001 milliseconds' ),
	'a 401'                 => array( 'tbljYkkVGbeoaWEtY', 0, airtable_answer( 401, array( 'error' => array( 'type' => 'AUTHENTICATION_REQUIRED', 'message' => 'Authentication required' ) ) ), 'refused', 'Failed: Airtable refused the token.' ),
	'a 403'                 => array( 'tblx3TH6fp4edQJDm', 0, airtable_answer( 403, array( 'error' => array( 'type' => 'INVALID_PERMISSIONS_OR_MODEL_NOT_FOUND' ) ) ), 'refused', 'Failed: Airtable refused the token.' ),
	'a 404'                 => array( 'tblx3TH6fp4edQJDm', 0, airtable_answer( 404, array( 'error' => array( 'type' => 'TABLE_NOT_FOUND', 'message' => 'Could not find table tblx3TH6fp4edQJDm in application appSTANDIN' ) ) ), 'airtable', 'Failed: Airtable request failed (HTTP 404): Could not find table tblx3TH6fp4edQJDm in application appSTANDIN' ),
	'a 422'                 => array( 'tbljYkkVGbeoaWEtY', 0, airtable_answer( 422, array( 'error' => array( 'type' => 'INVALID_FILTER_BY_FORMULA', 'message' => 'The formula for filtering records is invalid: Unknown field names: status' ) ) ), 'airtable', 'Failed: Airtable request failed (HTTP 422): The formula for filtering records is invalid: Unknown field names: status' ),
	'a 429'                 => array( 'tbljYkkVGbeoaWEtY', 2, airtable_answer( 429 ), 'rate_limited', 'Failed: Airtable asked the site to wait.' ),
	'an answer not in JSON' => array( 'tblx3TH6fp4edQJDm', 8, array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '<html>' ), 'unreachable', 'Failed: Airtable returned an unexpected response.' ),
);

$said = array();

foreach ( $failures as $what => $failure ) {
	base();
	$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = stored( 1791183600 );
	unset( $GLOBALS['opts'][ WPCPM_Airtable::BACKOFF_OPTION ] );
	WPCPM_Airtable::for_tests(
		static function () { return (float) $GLOBALS['now']; },
		static function ( $seconds ) { $GLOBALS['now'] += (int) ceil( $seconds ); },
		static function () { return true; }
	);
	$GLOBALS['refuse'] = array(
		'table'  => $failure[0],
		'page'   => $failure[1],
		'answer' => $failure[2],
	);

	$run    = WPCPM_Outcome_Figures::refresh();
	$status = WPCPM_Outcome_Figures::status();
	$text   = serialize( array( $run, get_option( WPCPM_Outcome_Figures::RUN_OPTION ), get_option( WPCPM_Outcome_Figures::LOCK_OPTION, 'none' ) ) );

	$said[ $what ] = array(
		$run['result'],
		$run['reason'],
		$run['message'],
		get_option( WPCPM_Outcome_Figures::OPTION ) === stored( 1791183600 ),
		array( $status['result'], $status['reason'], $status['message'], $status['counts'] ),
		array_key_exists( WPCPM_Outcome_Figures::LOCK_OPTION, $GLOBALS['opts'] ),
		false !== strpos( $text, 'patSTANDIN' ) || false !== stripos( $text, 'Bearer' ),
	);
}

$want = array();

foreach ( $failures as $what => $failure ) {
	$want[ $what ] = array( 'failed', $failure[3], $failure[4], true, array( 'failed', $failure[3], $failure[4], array() ), false, false );
}

ck( 'any request that fails ends the run: it is recorded as failed, with its reason and the sentence for it, the stored set is untouched, the lock is given back, and neither the token nor the word Bearer is in the result, the attempt or the lock',
	$said, $want );

base();
forget();
$GLOBALS['endless'] = true;
$run                = WPCPM_Outcome_Figures::refresh();

ck( 'a table still paging after fifty pages is never counted in part: the run fails and stores nothing',
	array( $run['result'], $run['reason'], $run['message'], count( $GLOBALS['sent'] ), get_option( WPCPM_Outcome_Figures::OPTION, 'none' ) ),
	array( 'failed', 'pages', 'Failed: Airtable was still paging after 50 pages, so nothing was counted.', 50, 'none' ) );

base();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = stored( 1791183600 );
$GLOBALS['refuse'] = array( 'table' => 'tbljYkkVGbeoaWEtY', 'page' => 0, 'answer' => airtable_answer( 422, array( 'error' => array( 'type' => 'INVALID_REQUEST_UNKNOWN', 'message' => str_repeat( 'Unknown field names: status. ', 200 ) ) ) ) );
$long         = WPCPM_Outcome_Figures::refresh();
$long_message = WPCPM_Outcome_Figures::status()['message'];

ck( 'an error\'s own sentence is kept to 300 characters, and three dots say it was cut: an odd answer never fills the attempt or the syncs row',
	array( $long['reason'], 0 === strpos( $long_message, 'Failed: Airtable request failed (HTTP 422): Unknown field names: status. Unknown' ), mb_strlen( $long_message ) <= 8 + 300 + 3, mb_strlen( $long_message ) > 300, substr( $long_message, -3 ), $long['message'] === $long_message ),
	array( 'airtable', true, true, true, '...', true ) );

base();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = stored( 1791183600 );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['reports_table'] = '';
$no_table = WPCPM_Outcome_Figures::refresh();

ck( 'a table setting left empty has its own reason and says where to set it: nothing is sent and nothing stored',
	array( $no_table['result'], $no_table['reason'], $no_table['message'], count( $GLOBALS['sent'] ), get_option( WPCPM_Outcome_Figures::OPTION ) === stored( 1791183600 ), WPCPM_Outcome_Figures::status()['reason'] ),
	array( 'failed', 'no_table', 'Failed: the Students Reports table or the Feedback table is not set in Settings.', 0, true, 'no_table' ) );

base();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = stored( 1791183600 );
$GLOBALS['refused'] = array( WPCPM_Outcome_Figures::OPTION );
$unsaved = WPCPM_Outcome_Figures::refresh();
$GLOBALS['refused'] = array();

ck( 'a set the site did not keep is never told as stored: update_option() said false, so the run fails with its own reason and the set is as it was',
	array( $unsaved['result'], $unsaved['reason'], $unsaved['message'], get_option( WPCPM_Outcome_Figures::OPTION ) === stored( 1791183600 ), WPCPM_Outcome_Figures::status()['result'], WPCPM_Outcome_Figures::status()['reason'] ),
	array( 'failed', 'not_saved', 'Failed: the site could not save the figures.', true, 'failed', 'not_saved' ) );

base();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = stored( 1791788400 );
$same = WPCPM_Outcome_Figures::refresh();

ck( 'and a set equal to the one stored, which update_option() also says false for, is stored',
	array( $same['result'], WPCPM_Outcome_Figures::status()['result'] ), array( 'stored', 'stored' ) );

echo "\n=== The guards: what is never stored ===\n";

/**
 * One run against a stored set, and what it did.
 *
 * @param array      $base   The arguments of base(): what the base holds now.
 * @param array|null $before The stored set, or null for a first run.
 * @param array      $args   What refresh() is asked.
 * @return array The result, the reason, the sentence, and whether the stored set is what it was.
 */
function against( array $base, $before, array $args = array() ) {
	base( ...$base );

	if ( null === $before ) {
		forget();
	} else {
		$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $before;
	}

	$run = WPCPM_Outcome_Figures::refresh( $args );

	return array( $run['result'], $run['reason'], $run['message'], get_option( WPCPM_Outcome_Figures::OPTION, null ) === $before );
}

$week_ago = stored( 1791183600 );

ck( 'a zero numerator is held, against a stored set or on a site\'s first run, and with --accept',
	array(
		against( array( 0, 52 ), $week_ago ),
		against( array( 361, 52, array( 0, 315 ) ), null ),
		against( array( 361, 52, array( 277, 38 ), array( 0, 310 ) ), $week_ago, array( 'accept' => true ) ),
	),
	array(
		array( 'held', 'zero', 'Held: no graduates were counted.', true ),
		array( 'held', 'zero', 'Held: no answers of likely to recommend were counted.', true ),
		array( 'held', 'zero', 'Held: no answers of likely to keep contributing were counted.', true ),
	) );

ck( 'a numerator or an n that fell by more than 10% is held; one that fell by 10% or less is stored, 310 to 279 being exactly 10%',
	array(
		against( array( 324, 46 ), $week_ago ),
		against( array( 325, 47 ), $week_ago ),
		against( array( 361, 52, array( 277, 38 ), array( 245, 33 ) ), $week_ago ),
		against( array( 361, 52, array( 277, 38 ), array( 245, 34 ) ), $week_ago ),
		against( array( 361, 52, array( 250, 34 ) ), $week_ago ),
	),
	array(
		array( 'held', 'fell', 'Held: graduates fell from 361 to 324, by more than 10%.', true ),
		array( 'stored', '', '', false ),
		array( 'held', 'fell', 'Held: answers to the keep-contributing question fell from 310 to 278, by more than 10%.', true ),
		array( 'stored', '', '', false ),
		array( 'stored', '', '', false ),
	) );

ck( 'a share that moved by more than 10 points is held; one that moved by 10 is stored',
	array(
		against( array( 361, 114 ), $week_ago ),
		against( array( 361, 107 ), $week_ago ),
	),
	array(
		array( 'held', 'moved', 'Held: the graduation share moved from 87% to 76%, by more than 10 points.', true ),
		array( 'stored', '', '', false ),
	) );

ck( 'guards 3 and 4 apply from the second run on: a site\'s first run has nothing to compare with',
	against( array( 100, 400 ), null ), array( 'stored', '', '', false ) );

ck( 'and --accept stores a run they hold, once a person has checked it',
	array( against( array( 361, 114 ), $week_ago, array( 'accept' => true ) ), get_option( WPCPM_Outcome_Figures::OPTION )['graduation'] ),
	array( array( 'stored', '', '', false ), array( 'share' => 76, 'count' => 361, 'n' => 475 ) ) );

ck( 'accept is true and nothing else: the string "false", "yes" or a 1 is not a person\'s yes, and the run the guard holds stays held',
	array(
		against( array( 361, 114 ), $week_ago, array( 'accept' => 'false' ) ),
		against( array( 361, 114 ), $week_ago, array( 'accept' => 'yes' ) ),
		against( array( 361, 114 ), $week_ago, array( 'accept' => 1 ) ),
	),
	array_fill( 0, 3, array( 'held', 'moved', 'Held: the graduation share moved from 87% to 76%, by more than 10 points.', true ) ) );

// The held sentences name the thresholds the guards hold at, whatever they are: a copy of the class
// with both at 20 says 20, as the guards it runs hold at 20, and stores what the class holds at 10.
$wider_source  = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-outcome-figures.php' );
$wider_anchors = array( '<?php', 'final class WPCPM_Outcome_Figures {', 'const MAX_FALL = 10;', 'const MAX_SHIFT = 10;' );
$wider_found   = array_map( static function ( $anchor ) use ( $wider_source ) { return substr_count( $wider_source, $anchor ); }, $wider_anchors );

eval( str_replace( $wider_anchors, array( '', 'final class WPCPM_Outcome_Figures_Wider {', 'const MAX_FALL = 20;', 'const MAX_SHIFT = 20;' ), $wider_source ) );

$wider = array();

foreach ( array( array( 280, 52 ), array( 361, 183 ), array( 361, 114 ) ) as $counts ) {
	base( ...$counts );
	$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $week_ago;
	WPCPM_Outcome_Figures_Wider::for_tests( static function () { return $GLOBALS['now']; } );

	$wide    = WPCPM_Outcome_Figures_Wider::refresh();
	$wider[] = array( $wide['result'], $wide['reason'], $wide['message'] );
}

ck( 'the held sentences take the thresholds from the guards\' own constants: with both at 20, a fall of 22% and a move of 21 points say "more than 20", and a move of 11 is stored',
	array( $wider_found, $wider ),
	array(
		array( 1, 1, 1, 1 ),
		array(
			array( 'held', 'fell', 'Held: graduates fell from 361 to 280, by more than 20%.' ),
			array( 'held', 'moved', 'Held: the graduation share moved from 87% to 66%, by more than 20 points.' ),
			array( 'stored', '', '' ),
		),
	) );

against( array( 324, 46 ), $week_ago );

ck( 'a held run keeps the stored set and records why, with the counts it saw',
	array( get_option( WPCPM_Outcome_Figures::OPTION ) === $week_ago, WPCPM_Outcome_Figures::status()['result'], WPCPM_Outcome_Figures::status()['counts']['graduation'] ),
	array( true, 'held', array( 'count' => 324, 'n' => 370 ) ) );

echo "\n=== A dry run, the lock and a site with no connection ===\n";

base( 400, 50 );
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ]     = $week_ago;
$GLOBALS['opts'][ WPCPM_Outcome_Figures::RUN_OPTION ] = array( 'time' => 1791183600, 'result' => 'stored', 'reason' => '', 'message' => '', 'counts' => array() );
$dry = WPCPM_Outcome_Figures::refresh( array( 'dry_run' => true ) );

ck( 'a dry run reads and counts and stores nothing, neither the set nor the attempt',
	array( $dry['result'], $dry['figures']['graduation'], $dry['rows'], get_option( WPCPM_Outcome_Figures::OPTION ) === $week_ago, WPCPM_Outcome_Figures::status()['time'] ),
	array( 'dry_run', array( 'share' => 89, 'count' => 400, 'n' => 450 ), array( 'reports' => 450, 'feedback' => 834 ), true, 1791183600 ) );

base();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ]     = $week_ago;
$GLOBALS['opts'][ WPCPM_Outcome_Figures::RUN_OPTION ] = array( 'time' => 1791183600, 'result' => 'stored', 'reason' => '', 'message' => '', 'counts' => array() );
$GLOBALS['refuse'] = array( 'table' => 'tblx3TH6fp4edQJDm', 'page' => 0, 'answer' => airtable_answer( 503 ) );
$failed_dry = WPCPM_Outcome_Figures::refresh( array( 'dry_run' => true ) );

ck( 'a dry run that fails reports the failure and records nothing: the last attempt is as it was',
	array( $failed_dry['result'], WPCPM_Outcome_Figures::status()['time'], WPCPM_Outcome_Figures::status()['result'], get_option( WPCPM_Outcome_Figures::OPTION ) === $week_ago ),
	array( 'failed', 1791183600, 'stored', true ) );

base();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::LOCK_OPTION ] = 1791788400 - 5 * MINUTE_IN_SECONDS;
$busy = WPCPM_Outcome_Figures::refresh();

ck( 'a run while another holds the lock reads nothing and leaves the lock where it is',
	array( $busy['result'], $busy['message'], count( $GLOBALS['sent'] ), get_option( WPCPM_Outcome_Figures::LOCK_OPTION ) ),
	array( 'busy', 'Another read of the outcome figures is running. Try again in a few minutes.', 0, 1791788400 - 300 ) );

base();
forget();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::LOCK_OPTION ] = 1791788400 - 11 * MINUTE_IN_SECONDS;
$stale = WPCPM_Outcome_Figures::refresh();

ck( 'a lock older than ten minutes was left by a run that never finished: it is taken over, and given back',
	array( $stale['result'], count( $GLOBALS['sent'] ), array_key_exists( WPCPM_Outcome_Figures::LOCK_OPTION, $GLOBALS['opts'] ) ),
	array( 'stored', 14, false ) );

base();
forget();
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array( 'api_token' => '', 'base_id' => 'appSTANDIN' );
$off = WPCPM_Outcome_Figures::refresh();

ck( 'with no token in Settings the run does not start: nothing read, nothing recorded',
	array( $off['result'], $off['message'], count( $GLOBALS['sent'] ), WPCPM_Outcome_Figures::status(), get_option( WPCPM_Outcome_Figures::LOCK_OPTION, 'none' ) ),
	array( 'not_connected', 'Not connected.', 0, array(), 'none' ) );

echo "\n=== What may be shown ===\n";

/**
 * What published() says of a stored set, on a fresh request at a given time.
 *
 * @param mixed $set     The option's value, or 'none' for no option.
 * @param int   $now     The request's time.
 * @param array $filters Hook => callback.
 * @return array
 */
function shown( $set, $now = 1791788400, array $filters = array() ) {
	$GLOBALS['opts']    = array();
	$GLOBALS['filters'] = $filters;
	$GLOBALS['now']     = $now;

	if ( 'none' !== $set ) {
		$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $set;
	}

	WPCPM_Outcome_Figures::for_tests( static function () { return $GLOBALS['now']; } );

	return WPCPM_Outcome_Figures::published();
}

ck( 'the stored set, with the time it was read, each figure its share, numerator and n, in the homepage\'s order',
	shown( stored( 1791788400 ) ),
	array(
		'read'    => 1791788400,
		'figures' => array(
			'graduation' => array( 'share' => 87, 'count' => 361, 'n' => 413 ),
			'recommend'  => array( 'share' => 88, 'count' => 277, 'n' => 315 ),
			'keep'       => array( 'share' => 79, 'count' => 245, 'n' => 310 ),
		),
	) );

ck( 'a figure whose n is under 30 is left out and the others are shown; 30 is enough',
	array( array_keys( shown( stored( 1791788400, array( 87, 361, 413, 90, 26, 29, 80, 24, 30 ) ) )['figures'] ) ),
	array( array( 'graduation', 'keep' ) ) );

ck( 'with every figure under the minimum, nothing at all',
	shown( stored( 1791788400, array( 80, 20, 25, 90, 26, 29, 80, 23, 29 ) ) ), array() );

ck( 'a figure whose numerator is zero is left out, however the set was written, and the others are shown',
	array_keys( shown( stored( 1791788400, array( 87, 361, 413, 0, 0, 315, 79, 245, 310 ) ) )['figures'] ),
	array( 'graduation', 'keep' ) );

ck( 'the minimum is filtered by wpcpm_outcome_figures_min_sample',
	array_keys( shown( stored( 1791788400 ), 1791788400, array( 'wpcpm_outcome_figures_min_sample' => static function () { return 312; } ) )['figures'] ),
	array( 'graduation', 'recommend' ) );

ck( 'a set 29 days old is shown; a second older and nothing is',
	array( shown( stored( 1791788400 ), 1791788400 + 29 * DAY_IN_SECONDS )['read'] ?? null, shown( stored( 1791788400 ), 1791788400 + 29 * DAY_IN_SECONDS + 1 ) ),
	array( 1791788400, array() ) );

ck( 'which is four Mondays without a good read: the set read on 12 October is shown on the fourth Monday after, 9 November, at its last try at 12:00 UTC, and gone the next day',
	array( gmdate( 'D j M H:i', 1791788400 + 28 * DAY_IN_SECONDS + 5 * HOUR_IN_SECONDS ), array_key_exists( 'read', shown( stored( 1791788400 ), 1791788400 + 28 * DAY_IN_SECONDS + 5 * HOUR_IN_SECONDS ) ), shown( stored( 1791788400 ), 1791788400 + 30 * DAY_IN_SECONDS ) ),
	array( 'Mon 9 Nov 12:00', true, array() ) );

ck( 'the age is filtered by wpcpm_outcome_figures_max_age, in days',
	shown( stored( 1791788400 ), 1791788400 + 8 * DAY_IN_SECONDS, array( 'wpcpm_outcome_figures_max_age' => static function () { return 7; } ) ), array() );

$broken = array(
	'no option'               => 'none',
	'a string'                => 'a:0:{}',
	'no read time'            => array_diff_key( stored( 1791788400 ), array( 'read' => true ) ),
	'a read time as a string' => array_merge( stored( 1791788400 ), array( 'read' => '1791788400' ) ),
	'a figure missing'        => array_diff_key( stored( 1791788400 ), array( 'keep' => true ) ),
	'a share over 100'        => stored( 1791788400, array( 120, 361, 413, 88, 277, 315, 79, 245, 310 ) ),
	'a count over its n'      => stored( 1791788400, array( 87, 414, 413, 88, 277, 315, 79, 245, 310 ) ),
	'a negative n'            => stored( 1791788400, array( 87, 361, 413, 88, 277, -315, 79, 245, 310 ) ),
	'a share as a string'     => stored( 1791788400, array( '87', 361, 413, 88, 277, 315, 79, 245, 310 ) ),
	'a share left null'       => stored( 1791788400, array( null, 361, 413, 88, 277, 315, 79, 245, 310 ) ),
);

$seen = array();

foreach ( $broken as $what => $set ) {
	$seen[ $what ] = shown( $set );
}

ck( 'a set in any other shape is no set: nothing is shown from it, rather than a figure made of whatever the option holds',
	$seen, array_fill_keys( array_keys( $broken ), array() ) );

shown( stored( 1791788400 ) );
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = stored( 1791183600, array( 50, 50, 100, 50, 50, 100, 50, 50, 100 ) );

ck( 'the option is read once a request',
	WPCPM_Outcome_Figures::published()['read'], 1791788400 );

// base() empties the request's memory, so the set is read, and so remembered, after it.
base();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = stored( 1791183600 );
$primed = WPCPM_Outcome_Figures::published()['read'];
$again  = WPCPM_Outcome_Figures::refresh();

ck( 'a stored run makes published() read the set it just wrote, not the one this request read before',
	array( $primed, $again['result'], WPCPM_Outcome_Figures::published()['read'] ), array( 1791183600, 'stored', 1791788400 ) );

ck( 'and before any run there is no attempt to report',
	array( shown( 'none' ), WPCPM_Outcome_Figures::status() ), array( array(), array() ) );

echo "\n=== The weekly event, on the dashboard's Monday ===\n";

/**
 * Where schedule() puts the weekly event, called at a time, with the event as a check leaves it.
 *
 * @param string     $when  A UTC time, `Y-m-d H:i:s`.
 * @param string|int $event Its time as `Y-m-d H:i:s` UTC, or 0 for none.
 * @return string `Y-m-d H:i:s recurrence`, in UTC.
 */
function scheduled_at( $when, $event = 0 ) {
	$GLOBALS['now']  = strtotime( $when . ' UTC' );
	$GLOBALS['cron'] = array();

	if ( $event ) {
		$GLOBALS['cron'][ WPCPM_Outcome_Figures::HOOK ] = array( 'at' => strtotime( $event . ' UTC' ), 'recurrence' => 'weekly' );
	}

	WPCPM_Outcome_Figures::schedule();

	$at = $GLOBALS['cron'][ WPCPM_Outcome_Figures::HOOK ];

	return gmdate( 'Y-m-d H:i:s', $at['at'] ) . ' ' . $at['recurrence'];
}

ck( 'with none booked, the next Monday at 07:00 UTC, on the weekly recurrence: from a Wednesday, a Sunday, a Monday before seven, at seven and after it',
	array(
		scheduled_at( '2026-10-14 18:30:00' ),
		scheduled_at( '2026-10-18 23:59:59' ),
		scheduled_at( '2026-10-12 06:59:59' ),
		scheduled_at( '2026-10-12 07:00:00' ),
		scheduled_at( '2026-10-12 07:00:01' ),
	),
	array(
		'2026-10-19 07:00:00 weekly',
		'2026-10-19 07:00:00 weekly',
		'2026-10-12 07:00:00 weekly',
		'2026-10-12 07:00:00 weekly',
		'2026-10-19 07:00:00 weekly',
	) );

ck( 'set in UTC, so the site\'s clock changes never move it: across the end and the start of summer time in Warsaw',
	array( scheduled_at( '2026-10-24 12:00:00' ), scheduled_at( '2027-03-27 12:00:00' ) ),
	array( '2026-10-26 07:00:00 weekly', '2027-03-29 07:00:00 weekly' ) );

ck( 'an event already at 07:00 UTC on a Monday is left as it is, overdue or not',
	array( scheduled_at( '2026-10-14 18:30:00', '2026-10-19 07:00:00' ), scheduled_at( '2026-10-14 18:30:00', '2026-10-12 07:00:00' ) ),
	array( '2026-10-19 07:00:00 weekly', '2026-10-12 07:00:00 weekly' ) );

ck( 'one that has drifted is put back on the next Monday at 07:00 UTC',
	array( scheduled_at( '2026-10-14 18:30:00', '2026-10-15 07:00:00' ), scheduled_at( '2026-10-14 18:30:00', '2026-10-19 09:00:00' ), scheduled_at( '2026-10-14 18:30:00', '2026-10-19 07:00:01' ) ),
	array( '2026-10-19 07:00:00 weekly', '2026-10-19 07:00:00 weekly', '2026-10-19 07:00:00 weekly' ) );

$GLOBALS['cleared'] = array();
scheduled_at( '2026-10-14 18:30:00', '2026-10-19 09:00:00' );
$cleared_drifted = $GLOBALS['cleared'];
$GLOBALS['cleared'] = array();
scheduled_at( '2026-10-14 18:30:00', '2026-10-19 07:00:00' );
$cleared_kept = $GLOBALS['cleared'];

ck( 'a drifted event is cleared before it is booked again, so core never holds two; one on its Monday at 07:00 UTC is not touched',
	array( $cleared_drifted, $cleared_kept ), array( array( WPCPM_Outcome_Figures::HOOK ), array() ) );

$GLOBALS['actions'] = array();
WPCPM_Outcome_Figures::init();

ck( 'init() hooks the weekly event and its retry to the scheduled run, and the check of the event to init',
	array( $GLOBALS['actions'][ WPCPM_Outcome_Figures::HOOK ] ?? null, $GLOBALS['actions'][ WPCPM_Outcome_Figures::RETRY_HOOK ] ?? null, $GLOBALS['actions']['init'] ?? null ),
	array( array( array( 'WPCPM_Outcome_Figures', 'run_scheduled' ) ), array( array( 'WPCPM_Outcome_Figures', 'run_scheduled' ) ), array( array( 'WPCPM_Outcome_Figures', 'schedule' ) ) ) );

$GLOBALS['cron'] = array(
	WPCPM_Outcome_Figures::HOOK       => array( 'at' => 1792393200, 'recurrence' => 'weekly' ),
	WPCPM_Outcome_Figures::RETRY_HOOK => array( 'at' => 1791792000, 'recurrence' => false ),
	'wpcpm_mail_log_purge'            => array( 'at' => 1791792000, 'recurrence' => 'daily' ),
);
WPCPM_Outcome_Figures::deactivate();

ck( 'deactivate() clears the weekly event and any retry, and nothing else',
	array_keys( $GLOBALS['cron'] ), array( 'wpcpm_mail_log_purge' ) );

$main = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php' );

// The boot's call is looked for inside wpcpm_bootstrap() alone: the match may not run past the
// function's closing brace, at the start of a line, into a function after it.
$booted = '/function wpcpm_bootstrap\(\) \{(?:(?!\n\}).)*?WPCPM_Outcome_Figures::init\(\);/s';

ck( 'the plugin loads the class with the client it reads through, boots it, schedules the event on activation and clears it on deactivation',
	array(
		false !== strpos( $main, "require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';\nrequire_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-outcome-figures.php';" ),
		(bool) preg_match( $booted, $main ),
		(bool) preg_match( '/function wpcpm_activate\(\) \{[^}]*WPCPM_Outcome_Figures::schedule\(\);/', $main ),
		(bool) preg_match( '/function wpcpm_deactivate\(\) \{[^}]*WPCPM_Outcome_Figures::deactivate\(\);/', $main ),
	),
	array( true, true, true, true ) );

ck( 'and the boot is found in wpcpm_bootstrap() only: a call in a function after it is not the boot',
	array(
		(bool) preg_match( $booted, "function wpcpm_bootstrap() {\n\tWPCPM_Overview::init();\n}\n\nfunction wpcpm_later() {\n\tWPCPM_Outcome_Figures::init();\n}\n" ),
		(bool) preg_match( $booted, "function wpcpm_bootstrap() {\n\tif ( true ) {\n\t\tWPCPM_Overview::init();\n\t}\n\tWPCPM_Outcome_Figures::init();\n}\n" ),
	),
	array( false, true ) );

echo "\n=== Waiting for the dashboard's Monday build, and the retries ===\n";

/**
 * One scheduled run at a UTC time, the dashboard's page answering as a check says.
 *
 * @param string $when The run's time, `Y-m-d H:i:s` UTC.
 * @param mixed  $page What the page answers: a dashboard() page, a WP_Error.
 * @return array The result, the reason, the requests sent to Airtable, the reads of the page, and
 *               the retry booked, as `H:i` UTC or '' for none.
 */
function scheduled_run( $when, $page ) {
	$GLOBALS['now']    = strtotime( $when . ' UTC' );
	$GLOBALS['sent']   = array();
	$GLOBALS['visits'] = array();
	$GLOBALS['page']   = $page;

	// WordPress takes a single event off the schedule before it runs it.
	if ( isset( $GLOBALS['cron'][ WPCPM_Outcome_Figures::RETRY_HOOK ] ) && $GLOBALS['cron'][ WPCPM_Outcome_Figures::RETRY_HOOK ]['at'] <= $GLOBALS['now'] ) {
		unset( $GLOBALS['cron'][ WPCPM_Outcome_Figures::RETRY_HOOK ] );
	}

	$run   = WPCPM_Outcome_Figures::refresh( array( 'wait' => true ) );
	$retry = wp_next_scheduled( WPCPM_Outcome_Figures::RETRY_HOOK );

	return array( $run['result'], $run['reason'], count( $GLOBALS['sent'] ), count( $GLOBALS['visits'] ), $retry ? gmdate( 'D H:i', $retry ) : '' );
}

base();
forget();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $week_ago;

ck( 'the 07:00 run reads the page once, ten seconds and a megabyte at most, and finds the dashboard built that Monday: it reads Airtable and stores',
	array( scheduled_run( '2026-10-12 07:00:05', dashboard( '2026-10-12' ) ), $GLOBALS['visits'][0] ?? null, get_option( WPCPM_Outcome_Figures::OPTION )['read'] ),
	array( array( 'stored', '', 14, 1, '' ), array( 'https://wordpress.github.io/WPCredits/', array( 'timeout' => 10, 'limit_response_size' => 1048576 ) ), strtotime( '2026-10-12 07:00:05 UTC' ) ) );

base();
forget();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $week_ago;

$tries = array(
	scheduled_run( '2026-10-12 07:00:05', dashboard( '2026-10-05' ) ),
	scheduled_run( '2026-10-12 08:00:40', dashboard( '2026-10-05' ) ),
	scheduled_run( '2026-10-12 09:00:12', dashboard( '2026-10-05' ) ),
	scheduled_run( '2026-10-12 10:01:00', dashboard( '2026-10-05' ) ),
	scheduled_run( '2026-10-12 11:00:30', dashboard( '2026-10-05' ) ),
);

ck( 'a page still showing last Monday\'s build: nothing is read from Airtable and the run tries again at the next whole hour, up to the try at 12:00 UTC',
	$tries,
	array(
		array( 'waiting', 'dashboard', 0, 1, 'Mon 08:00' ),
		array( 'waiting', 'dashboard', 0, 1, 'Mon 09:00' ),
		array( 'waiting', 'dashboard', 0, 1, 'Mon 10:00' ),
		array( 'waiting', 'dashboard', 0, 1, 'Mon 11:00' ),
		array( 'waiting', 'dashboard', 0, 1, 'Mon 12:00' ),
	) );

$waiting = WPCPM_Outcome_Figures::status();

ck( 'each wait is recorded, with its sentence, and the tries that Monday has made; the stored set stays',
	array( $waiting['result'], $waiting['message'], $waiting['monday'], $waiting['tries'], get_option( WPCPM_Outcome_Figures::OPTION ) === $week_ago ),
	array( 'waiting', 'Waiting for the dashboard\'s Monday build.', '2026-10-12', 5, true ) );

ck( 'the try at 12:00 UTC reads Airtable anyway, without reading the page, and books nothing more',
	array( scheduled_run( '2026-10-12 12:00:20', dashboard( '2026-10-05' ) ), WPCPM_Outcome_Figures::status()['tries'] ),
	array( array( 'stored', '', 14, 0, '' ), 6 ) );

$unreadable = array(
	'a request that fails'          => new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host: wordpress.github.io' ),
	'a 404'                         => dashboard( '2026-10-05', 404 ),
	'a 503'                         => dashboard( '2026-10-05', 503 ),
	'a page with no builtAt'        => dashboard( '' ),
	'a builtAt that is not a date'  => dashboard( 'Monday' ),
	'a builtAt with a time'         => array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => 'const D = {"builtAt":"2026-10-05T06:18:51Z"};' ),
	'a page that is not the board'  => array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '<html><body>Site not found</body></html>' ),
	'a build date past a megabyte'  => array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '<script>' . str_repeat( ' ', 1048576 ) . 'const D = {"builtAt":"2026-10-05"};</script>' ),
);

$read = array();

// Not `$page`: at the top level that is `$GLOBALS['page']`, the stand-in's answer, which base() resets.
foreach ( $unreadable as $what => $served ) {
	base();
	forget();
	$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $week_ago;
	$read[ $what ] = scheduled_run( '2026-10-12 07:00:05', $served );
}

ck( 'a page that cannot be read, or holds no build date in its first megabyte, is no reason to wait: the run reads Airtable at once',
	$read, array_fill_keys( array_keys( $unreadable ), array( 'stored', '', 14, 1, '' ) ) );

base();
forget();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $week_ago;

ck( 'a build dated after the run\'s Monday counts as built, as on a page a day ahead of the site\'s clock; a late run on Tuesday reads at once without reading the page, and so does a run past 12:00 UTC',
	array( scheduled_run( '2026-10-12 07:00:05', dashboard( '2026-10-13' ) ), scheduled_run( '2026-10-13 07:10:00', dashboard( '2026-10-05' ) ), scheduled_run( '2026-10-12 15:00:00', dashboard( '2026-10-05' ) ) ),
	array( array( 'stored', '', 14, 1, '' ), array( 'stored', '', 14, 0, '' ), array( 'stored', '', 14, 0, '' ) ) );

base();
forget();
$GLOBALS['refuse'] = array( 'table' => 'tbljYkkVGbeoaWEtY', 'page' => 0, 'answer' => airtable_answer( 503 ) );
$failed_tries      = array( scheduled_run( '2026-10-12 07:00:05', dashboard( '2026-10-12' ) ), scheduled_run( '2026-10-12 11:00:09', dashboard( '2026-10-12' ) ) );
$GLOBALS['cron']   = array();
$failed_tries[]    = scheduled_run( '2026-10-12 12:00:09', dashboard( '2026-10-12' ) );

ck( 'a scheduled run that fails tries again an hour later, the last try at 12:00 UTC; after that, the next Monday',
	$failed_tries,
	array(
		array( 'failed', 'unreachable', 1, 1, 'Mon 08:00' ),
		array( 'failed', 'unreachable', 1, 1, 'Mon 12:00' ),
		array( 'failed', 'unreachable', 1, 0, '' ),
	) );

base();
forget();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $week_ago;
$before_noon = scheduled_run( '2026-10-12 11:59:59', dashboard( '2026-10-05' ) );
$at_noon     = scheduled_run( '2026-10-12 12:00:00', dashboard( '2026-10-05' ) );

ck( 'the last try is at 12:00:00 UTC exactly: a second before it the run still waits, at it Airtable is read without reading the page',
	array( $before_noon, $at_noon ), array( array( 'waiting', 'dashboard', 0, 1, 'Mon 12:00' ), array( 'stored', '', 14, 0, '' ) ) );

base();
forget();
$GLOBALS['page'] = dashboard( '2026-10-05' );
WPCPM_Outcome_Figures::run_scheduled();

ck( 'run_scheduled(), which both hooks call, is the waiting run: it reads the page, reads nothing from Airtable and books the retry',
	array( WPCPM_Outcome_Figures::status()['result'], count( $GLOBALS['visits'] ), count( $GLOBALS['sent'] ), wp_next_scheduled( WPCPM_Outcome_Figures::RETRY_HOOK ) ),
	array( 'waiting', 1, 0, strtotime( '2026-10-12 08:00:00 UTC' ) ) );

base();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $week_ago;
$GLOBALS['cron'][ WPCPM_Outcome_Figures::RETRY_HOOK ] = array( 'at' => strtotime( '2026-10-12 09:00:00 UTC' ), 'recurrence' => false );
$GLOBALS['tables']['tbljYkkVGbeoaWEtY']             = array_slice( $GLOBALS['tables']['tbljYkkVGbeoaWEtY'], 0, 300 );

ck( 'a held run does not retry: a person decides, and a retry already booked is taken off',
	scheduled_run( '2026-10-12 08:00:05', dashboard( '2026-10-12' ) ), array( 'held', 'fell', 12, 1, '' ) );

base();
forget();
$GLOBALS['page'] = dashboard( '2026-10-05' );
$manual          = WPCPM_Outcome_Figures::refresh();

ck( 'a run from WP-CLI never reads the page and never books a retry',
	array( $manual['result'], count( $GLOBALS['visits'] ), $GLOBALS['cron'] ), array( 'stored', 0, array() ) );

$dry_waits = array();

foreach ( array( 'waiting for a build' => 'built', 'failing' => 'failed', 'finding the lock held' => 'busy' ) as $what => $case ) {
	base();
	forget();
	$GLOBALS['page'] = dashboard( '2026-10-05' );

	if ( 'failed' === $case ) {
		$GLOBALS['page']   = dashboard( '2026-10-12' );
		$GLOBALS['refuse'] = array( 'table' => 'tbljYkkVGbeoaWEtY', 'page' => 0, 'answer' => airtable_answer( 503 ) );
	} elseif ( 'busy' === $case ) {
		$GLOBALS['page']                                       = dashboard( '2026-10-12' );
		$GLOBALS['opts'][ WPCPM_Outcome_Figures::LOCK_OPTION ] = $GLOBALS['now'];
	}

	$dry = WPCPM_Outcome_Figures::refresh( array( 'dry_run' => true, 'wait' => true ) );

	$dry_waits[ $what ] = array( $dry['result'], count( $GLOBALS['visits'] ), WPCPM_Outcome_Figures::status(), $GLOBALS['cron'] );
}

ck( 'a dry run never waits, whatever it is asked: it does not read the page, records no attempt and books no retry, whether the dashboard is behind, the read fails or another read is running',
	$dry_waits,
	array(
		'waiting for a build'   => array( 'dry_run', 0, array(), array() ),
		'failing'               => array( 'failed', 0, array(), array() ),
		'finding the lock held' => array( 'busy', 0, array(), array() ),
	) );

base();
forget();
$GLOBALS['cron'][ WPCPM_Outcome_Figures::RETRY_HOOK ] = array( 'at' => strtotime( '2026-10-12 09:00:00 UTC' ), 'recurrence' => false );
$stored_run                                           = WPCPM_Outcome_Figures::refresh();

ck( 'a run that stores takes a retry already booked off: the week\'s figures are read',
	array( $stored_run['result'], wp_next_scheduled( WPCPM_Outcome_Figures::RETRY_HOOK ) ), array( 'stored', false ) );

base();
forget();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::LOCK_OPTION ] = strtotime( '2026-10-12 07:00:00 UTC' );
$held_lock                                             = array( scheduled_run( '2026-10-12 07:00:05', dashboard( '2026-10-12' ) ) );
$GLOBALS['cron']                                       = array();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::LOCK_OPTION ] = strtotime( '2026-10-12 12:00:00 UTC' );
$held_lock[]                                           = scheduled_run( '2026-10-12 12:00:05', dashboard( '2026-10-12' ) );

ck( 'a scheduled run that finds another read running, a parity check from WP-CLI say, tries again an hour later, the last try at 12:00 UTC',
	$held_lock, array( array( 'busy', 'busy', 0, 0, 'Mon 08:00' ), array( 'busy', 'busy', 0, 0, '' ) ) );

$busy_edges = array();

foreach ( array( '2026-10-12 11:59:59', '2026-10-12 12:00:00' ) as $when ) {
	base();
	forget();
	$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ]      = $week_ago;
	$GLOBALS['opts'][ WPCPM_Outcome_Figures::LOCK_OPTION ] = strtotime( $when . ' UTC' ) - 60;
	$busy_edges[]                                          = scheduled_run( $when, dashboard( '2026-10-05' ) );
}

ck( 'the busy run and the run share one 12:00:00 boundary: a lock found at 11:59:59 books the try at 12:00, one found at 12:00:00 books nothing, as the run at 12:00:00 reads without waiting',
	$busy_edges, array( array( 'busy', 'busy', 0, 0, 'Mon 12:00' ), array( 'busy', 'busy', 0, 0, '' ) ) );

base();
forget();
scheduled_run( '2026-10-12 07:00:05', dashboard( '2026-10-05' ) );
scheduled_run( '2026-10-19 07:00:05', dashboard( '2026-10-12' ) );

ck( 'the next Monday counts its own tries',
	array( WPCPM_Outcome_Figures::status()['monday'], WPCPM_Outcome_Figures::status()['tries'] ), array( '2026-10-19', 1 ) );

$stopped = array();

foreach ( array( '2026-10-12 07:00:05', '2026-10-12 11:00:05', '2026-10-12 12:00:05' ) as $when ) {
	base();
	$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ]     = $week_ago;
	$GLOBALS['opts'][ WPCPM_Outcome_Figures::RUN_OPTION ] = array( 'time' => 1791183600, 'result' => 'stored', 'reason' => '', 'message' => '', 'counts' => array(), 'monday' => '2026-10-05', 'tries' => 1 );
	$GLOBALS['stop']                                      = array( 'table' => 'tblx3TH6fp4edQJDm', 'page' => 4 );

	try {
		scheduled_run( $when, dashboard( '2026-10-12' ) );
		$how = 'finished';
	} catch ( RuntimeException $e ) {
		$how = $e->getMessage();
	}

	$retry     = wp_next_scheduled( WPCPM_Outcome_Figures::RETRY_HOOK );
	$stopped[] = array( $how, $retry ? gmdate( 'D H:i', $retry ) : '', get_option( WPCPM_Outcome_Figures::OPTION ) === $week_ago, WPCPM_Outcome_Figures::status()['time'] );
}

ck( 'a scheduled run stopped inside the read still has its next try, booked before the read: an hour on, the 11:00 run\'s at 12:00, and none after the last try; nothing is stored or recorded',
	$stopped,
	array(
		array( 'stopped inside the read', 'Mon 08:00', true, 1791183600 ),
		array( 'stopped inside the read', 'Mon 12:00', true, 1791183600 ),
		array( 'stopped inside the read', '', true, 1791183600 ),
	) );

base();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $week_ago;
$GLOBALS['refused']                               = array( WPCPM_Outcome_Figures::OPTION );
$unsaved_monday                                   = scheduled_run( '2026-10-12 07:00:05', dashboard( '2026-10-12' ) );
$GLOBALS['refused']                               = array();

ck( 'a scheduled run whose set the site did not keep has failed, and tries again an hour later',
	$unsaved_monday, array( 'failed', 'not_saved', 14, 1, 'Mon 08:00' ) );

base();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ]             = $week_ago;
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['reports_table'] = '';

ck( 'a scheduled run with a table setting left empty is told and never tried again each hour: an hour leaves the setting as empty',
	scheduled_run( '2026-10-12 07:00:05', dashboard( '2026-10-12' ) ), array( 'failed', 'no_table', 0, 1, '' ) );

// The first set is a person's to store, after the parity check with the dashboard (spec 10.2): a
// wrong first set would be what every later read is compared with. The weekly run holds it, books
// nothing, and the attempt, so the syncs row and WP-CLI, says how to check it and store it.
$first_sentence = 'Held: the first figures are stored by a person, not by the weekly read. Run wp wpcredits outcome_figures --refresh --dry-run and compare its counts with the WordPress Credits impact dashboard\'s, then store them with wp wpcredits outcome_figures --refresh.';

base();
forget();
$GLOBALS['cron'][ WPCPM_Outcome_Figures::HOOK ] = array( 'at' => strtotime( '2026-10-19 07:00:00 UTC' ), 'recurrence' => 'weekly' );
$first_monday                                   = scheduled_run( '2026-10-12 07:00:05', dashboard( '2026-10-12' ) );
$first_status                                   = WPCPM_Outcome_Figures::status();
$first_row                                      = WPCPM_Outcome_Figures::sync_row();

ck( 'a first Monday is held: the weekly run reads and counts, stores nothing, books no retry, and the attempt and the syncs row say to compare the counts with the dashboard\'s with --refresh --dry-run and store them with --refresh',
	array( $first_monday, get_option( WPCPM_Outcome_Figures::OPTION, 'none' ), $first_status['result'], $first_status['reason'], $first_status['message'], $first_status['counts']['graduation'], $first_row['note'], $first_row['last'], WPCPM_Outcome_Figures::published() ),
	array( array( 'held', 'first', 14, 1, '' ), 'none', 'held', 'first', $first_sentence, array( 'count' => 361, 'n' => 413 ), $first_sentence, 0, array() ) );

$GLOBALS['now'] = strtotime( '2026-10-12 07:30:00 UTC' );
$person         = WPCPM_Outcome_Figures::refresh();

ck( 'a person\'s --refresh stores the first set, as before',
	array( $person['result'], get_option( WPCPM_Outcome_Figures::OPTION )['read'] ?? null, WPCPM_Outcome_Figures::status()['result'], WPCPM_Outcome_Figures::sync_row()['note'] ),
	array( 'stored', strtotime( '2026-10-12 07:30:00 UTC' ), 'stored', '' ) );

ck( 'and the Monday after, the weekly run stores as on any Monday',
	array( scheduled_run( '2026-10-19 07:00:05', dashboard( '2026-10-19' ) ), get_option( WPCPM_Outcome_Figures::OPTION )['read'] ),
	array( array( 'stored', '', 14, 1, '' ), strtotime( '2026-10-19 07:00:05 UTC' ) ) );

$first_others = array();

base();
forget();
$first_others['the retry after a wait'] = array( scheduled_run( '2026-10-12 07:00:05', dashboard( '2026-10-05' ) ), scheduled_run( '2026-10-12 08:00:05', dashboard( '2026-10-12' ) ) );
base();
forget();
$first_others['the last try at 12:00 UTC'] = scheduled_run( '2026-10-12 12:00:05', dashboard( '2026-10-05' ) );
base();
forget();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = array( 'read' => 1791183600 );
$first_others['a set in another shape']           = scheduled_run( '2026-10-12 07:00:05', dashboard( '2026-10-12' ) );
base( 0, 52 );
forget();
$first_others['a first read that counts no graduate'] = array( scheduled_run( '2026-10-12 07:00:05', dashboard( '2026-10-12' ) ), WPCPM_Outcome_Figures::status()['message'] );

ck( 'held for the first set too: the retry that ends a wait, the last try at 12:00 UTC and a set in another shape, which is no set; a first read with a zero is held for the zero, since --refresh would hold that one too',
	$first_others,
	array(
		'the retry after a wait'              => array( array( 'waiting', 'dashboard', 0, 1, 'Mon 08:00' ), array( 'held', 'first', 14, 1, '' ) ),
		'the last try at 12:00 UTC'           => array( 'held', 'first', 14, 0, '' ),
		'a set in another shape'              => array( 'held', 'first', 14, 1, '' ),
		'a first read that counts no graduate' => array( array( 'held', 'zero', 10, 1, '' ), 'Held: no graduates were counted.' ),
	) );

echo "\n=== The syncs row ===\n";

/**
 * The row, on a site holding a stored set and a last attempt as a check says.
 *
 * @param mixed      $set     The stored set, or 'none'.
 * @param array|null $run     The last attempt's result, reason and sentence, or null for none.
 * @param array      $cron    Hook => when it next runs.
 * @param bool       $token   Whether Settings hold a token.
 * @return array
 */
function row( $set, $run = null, array $cron = array(), $token = true ) {
	base();
	forget();
	$GLOBALS['now'] = strtotime( '2026-10-14 10:00:00 UTC' );

	if ( 'none' !== $set ) {
		$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $set;
	}

	if ( null !== $run ) {
		$GLOBALS['opts'][ WPCPM_Outcome_Figures::RUN_OPTION ] = array( 'time' => strtotime( '2026-10-12 12:00:09 UTC' ), 'result' => $run[0], 'reason' => $run[1], 'message' => $run[2], 'counts' => array(), 'monday' => '2026-10-12', 'tries' => 6 );
	}

	foreach ( $cron as $hook => $at ) {
		$GLOBALS['cron'][ $hook ] = array( 'at' => $at, 'recurrence' => 'weekly' );
	}

	if ( ! $token ) {
		$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['api_token'] = '';
	}

	return WPCPM_Outcome_Figures::sync_row();
}

$weekly = array( WPCPM_Outcome_Figures::HOOK => strtotime( '2026-10-19 07:00:00 UTC' ) );

ck( 'a site that stored this Monday: the row in the shape every sync has, idle, its last run the time the set was read, its next the weekly event, its name opening the Connection tab of Settings, and nothing to note',
	row( stored( 1791788400 ), array( 'stored', '', '' ), $weekly ),
	array(
		'label'    => 'Outcome figures',
		'progress' => array( 'running' => false, 'phase' => '', 'label' => 'Reading Airtable', 'error' => '' ),
		'last'     => 1791788400,
		'next'     => strtotime( '2026-10-19 07:00:00 UTC' ),
		'screen'   => 'https://example.test/wp-admin/admin.php?page=wpcpm-settings&tab=connection',
		'note'     => '',
	) );

$noted = array(
	row( stored( 1791183600 ), array( 'held', 'fell', 'Held: graduates fell from 361 to 324, by more than 10%.' ), $weekly ),
	row( stored( 1791183600 ), array( 'failed', 'refused', 'Failed: Airtable refused the token.' ), $weekly ),
	row( stored( 1791183600 ), array( 'failed', 'airtable', 'Failed: Airtable request failed (HTTP 422): Unknown field names: status' ), $weekly ),
	row( stored( 1791183600 ), array( 'waiting', 'dashboard', 'Waiting for the dashboard\'s Monday build.' ), $weekly + array( WPCPM_Outcome_Figures::RETRY_HOOK => strtotime( '2026-10-12 12:00:00 UTC' ) ) ),
	row( stored( 1791183600 ), array( 'stored', '', '' ), $weekly, false ),
	row( stored( 1791788400, array( 87, 361, 413, 92, 11, 12, 75, 9, 12 ) ), array( 'stored', '', '' ), $weekly ),
);

ck( 'held, failed or waiting, the note is the attempt\'s own sentence and the last run is still when the stored set was read; with no token, Not connected; a figure under the minimum, its name and n',
	array_map( static function ( $row ) { return array( $row['note'], $row['progress']['error'], gmdate( 'D j M H:i', $row['last'] ), gmdate( 'D j M H:i', $row['next'] ) ); }, $noted ),
	array(
		array( 'Held: graduates fell from 361 to 324, by more than 10%.', 'Held: graduates fell from 361 to 324, by more than 10%.', 'Mon 5 Oct 07:00', 'Mon 19 Oct 07:00' ),
		array( 'Failed: Airtable refused the token.', 'Failed: Airtable refused the token.', 'Mon 5 Oct 07:00', 'Mon 19 Oct 07:00' ),
		array( 'Failed: Airtable request failed (HTTP 422): Unknown field names: status', 'Failed: Airtable request failed (HTTP 422): Unknown field names: status', 'Mon 5 Oct 07:00', 'Mon 19 Oct 07:00' ),
		array( 'Waiting for the dashboard\'s Monday build.', 'Waiting for the dashboard\'s Monday build.', 'Mon 5 Oct 07:00', 'Mon 12 Oct 12:00' ),
		array( 'Not connected.', 'Not connected.', 'Mon 5 Oct 07:00', 'Mon 19 Oct 07:00' ),
		array( 'Not shown on the homepage, with fewer than 30 answers or outcomes: the would-recommend share (12), the keep-contributing share (12).', 'Not shown on the homepage, with fewer than 30 answers or outcomes: the would-recommend share (12), the keep-contributing share (12).', 'Mon 12 Oct 07:00', 'Mon 19 Oct 07:00' ),
	) );

$never = row( 'none' );

ck( 'a site that never ran: Never and Not scheduled, as every sync says them',
	array( $never['last'], $never['next'], $never['note'] ), array( 0, 0, '' ) );

row( stored( 1791788400 ) );
$GLOBALS['opts'][ WPCPM_Outcome_Figures::LOCK_OPTION ] = $GLOBALS['now'] - 60;
$running = WPCPM_Outcome_Figures::sync_row();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::LOCK_OPTION ] = $GLOBALS['now'] - 11 * MINUTE_IN_SECONDS;
$left = WPCPM_Outcome_Figures::sync_row();

ck( 'while a run holds the lock the row is running, Reading Airtable; a lock left behind by a run that never finished is not a run',
	array( $running['progress']['running'], $left['progress']['running'] ), array( true, false ) );

$lock_edges = array();

foreach ( array( 599, 600 ) as $age ) {
	row( stored( 1791788400 ) );
	$GLOBALS['opts'][ WPCPM_Outcome_Figures::LOCK_OPTION ] = $GLOBALS['now'] - $age;
	$lock_edges[ $age ]                                    = array( WPCPM_Outcome_Figures::sync_row()['progress']['running'], WPCPM_Outcome_Figures::refresh()['result'] );
}

$class_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-outcome-figures.php' );

ck( 'the row and a run read the lock\'s age with one test, is_locked(), which lock() asks: at 599 seconds the row says running and a run is busy, at 600 the row does not and a run takes the lock over',
	array( $lock_edges, preg_match( '/private static function lock\( \$now \) \{(?:(?!\n\t\}).)*?self::is_locked\( \$now \)/s', $class_source ), substr_count( $class_source, '< self::LOCK_TTL' ) ),
	array( array( 599 => array( true, 'busy' ), 600 => array( false, 'stored' ) ), 1, 1 ) );

// The wait, as the weekly run itself records it and not as a check writes it: at 08:00 the dashboard
// is a week behind, so the run reads nothing from Airtable, records that it waits and books the try
// an hour on. The row tells it in the attempt's own sentence, and the set it shows is still the one
// read the Monday before.
base();
forget();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $week_ago;
$GLOBALS['cron'][ WPCPM_Outcome_Figures::HOOK ]   = array( 'at' => strtotime( '2026-10-19 07:00:00 UTC' ), 'recurrence' => 'weekly' );
scheduled_run( '2026-10-12 08:00:40', dashboard( '2026-10-05' ) );
$row_waits = WPCPM_Outcome_Figures::sync_row();
scheduled_run( '2026-10-12 09:00:12', dashboard( '2026-10-12' ) );
$row_after = WPCPM_Outcome_Figures::sync_row();

ck( 'a wait the weekly run recorded: the row says it in the attempt\'s own sentence, as its note and as its last error; its last run is still when the stored set was read, its next the try an hour on, and it is not running',
	array( $row_waits['note'], $row_waits['progress']['error'], $row_waits['progress']['running'], gmdate( 'D j M H:i', $row_waits['last'] ), gmdate( 'D j M H:i', $row_waits['next'] ) ),
	array( 'Waiting for the dashboard\'s Monday build.', 'Waiting for the dashboard\'s Monday build.', false, 'Mon 5 Oct 07:00', 'Mon 12 Oct 09:00' ) );

ck( 'the try that reads ends it: once the run stores, the row says nothing of a wait, its last run is the time it read and its next the weekly event',
	array( $row_after['note'], $row_after['progress']['error'], gmdate( 'D j M H:i', $row_after['last'] ), gmdate( 'D j M H:i', $row_after['next'] ) ),
	array( '', '', 'Mon 12 Oct 09:00', 'Mon 19 Oct 07:00' ) );

// The try is gone, the plugin deactivated and activated again say: the record still says waiting, but
// nothing waits, and a sentence in the present tense would go on saying so until the next Monday.
base();
forget();
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ] = $week_ago;
scheduled_run( '2026-10-12 08:00:40', dashboard( '2026-10-05' ) );
WPCPM_Outcome_Figures::deactivate();
WPCPM_Outcome_Figures::schedule();
$row_gone = WPCPM_Outcome_Figures::sync_row();

ck( 'a wait whose try is gone is not told as one: the attempt is still recorded as waiting, the row has no sentence for it, and its next is the weekly event',
	array( WPCPM_Outcome_Figures::status()['result'], $row_gone['note'], $row_gone['progress']['error'], gmdate( 'D j M H:i', $row_gone['next'] ) ),
	array( 'waiting', '', '', 'Mon 19 Oct 07:00' ) );

// An attempt recorded before the Monday and its tries were kept: time, result, reason, sentence and
// counts, nothing else. The row reads only the result and the sentence, so each is told as it was
// recorded, and no notice or warning comes of the fields that are not there.
$old_shape = array();
$warnings  = array();

set_error_handler( static function ( $number, $message ) use ( &$warnings ) { $warnings[] = $message; return true; } );

foreach ( array(
	'failed' => array( 'failed', 'refused', 'Failed: Airtable refused the token.' ),
	'held'   => array( 'held', 'fell', 'Held: graduates fell from 361 to 324, by more than 10%.' ),
	'stored' => array( 'stored', '', '' ),
) as $what => $attempt ) {
	row( stored( 1791183600 ), null, $weekly );
	$GLOBALS['opts'][ WPCPM_Outcome_Figures::RUN_OPTION ] = array( 'time' => strtotime( '2026-10-12 12:00:09 UTC' ), 'result' => $attempt[0], 'reason' => $attempt[1], 'message' => $attempt[2], 'counts' => array() );

	$old_row            = WPCPM_Outcome_Figures::sync_row();
	$old_shape[ $what ] = array( $old_row['note'], $old_row['progress']['error'], gmdate( 'D j M H:i', $old_row['last'] ), gmdate( 'D j M H:i', $old_row['next'] ) );
}

restore_error_handler();

ck( 'an attempt recorded without its Monday and its tries is told as any other: failed and held with their sentences, stored with none, the last run still when the set was read, and no warning for the fields it lacks',
	array( $old_shape, $warnings ),
	array(
		array(
			'failed' => array( 'Failed: Airtable refused the token.', 'Failed: Airtable refused the token.', 'Mon 5 Oct 07:00', 'Mon 19 Oct 07:00' ),
			'held'   => array( 'Held: graduates fell from 361 to 324, by more than 10%.', 'Held: graduates fell from 361 to 324, by more than 10%.', 'Mon 5 Oct 07:00', 'Mon 19 Oct 07:00' ),
			'stored' => array( '', '', 'Mon 5 Oct 07:00', 'Mon 19 Oct 07:00' ),
		),
		array(),
	) );

// The row reads the stored option as published() does: a set in any other shape is no set.
$broken_rows = array();
$warnings    = array();

set_error_handler( static function ( $number, $message ) use ( &$warnings ) { $warnings[] = $message; return true; } );

foreach ( $broken as $what => $set ) {
	$broken_row           = row( $set, array( 'stored', '', '' ), $weekly );
	$broken_rows[ $what ] = array( $broken_row['last'], $broken_row['note'] );
}

restore_error_handler();

ck( 'a stored option in any other shape than a run writes has no last run on the row and nothing to note, and no warning comes of it',
	array( $broken_rows, $warnings ), array( array_fill_keys( array_keys( $broken ), array( 0, '' ) ), array() ) );

// An attempt with no sentence, or one that is not a string, is never written by record(): an option
// edited by hand, say. The row still says what the attempt did, in a sentence of its own.
$unsaid   = array();
$warnings = array();

set_error_handler( static function ( $number, $message ) use ( &$warnings ) { $warnings[] = $message; return true; } );

foreach ( array(
	'held, its sentence a list'      => array( 'held', array( 'Held: graduates fell.' ) ),
	'failed, with no sentence'       => array( 'failed', null ),
	'held, its sentence empty'       => array( 'held', '' ),
	'waiting, its sentence a number' => array( 'waiting', 7 ),
	'failed, its sentence a string'  => array( 'failed', 'Failed: Airtable refused the token.' ),
) as $what => $attempt ) {
	row( stored( 1791183600 ), null, $weekly + array( WPCPM_Outcome_Figures::RETRY_HOOK => strtotime( '2026-10-14 11:00:00 UTC' ) ) );

	$record = array( 'time' => strtotime( '2026-10-14 10:00:00 UTC' ), 'result' => $attempt[0], 'reason' => '', 'counts' => array() );

	if ( null !== $attempt[1] ) {
		$record['message'] = $attempt[1];
	}

	$GLOBALS['opts'][ WPCPM_Outcome_Figures::RUN_OPTION ] = $record;
	$unsaid[ $what ]                                      = WPCPM_Outcome_Figures::sync_row()['note'];
}

restore_error_handler();

ck( 'an attempt held or failed with no sentence, or one that is not a string, is still told, in the row\'s own words, with no warning; a sentence that is a string is told as it was recorded',
	array( $unsaid, $warnings ),
	array(
		array(
			'held, its sentence a list'      => 'Held: the last read was not stored.',
			'failed, with no sentence'       => 'Failed: the last read stored nothing.',
			'held, its sentence empty'       => 'Held: the last read was not stored.',
			'waiting, its sentence a number' => 'Waiting for the dashboard\'s Monday build.',
			'failed, its sentence a string'  => 'Failed: Airtable refused the token.',
		),
		array(),
	) );

row( stored( 1791183600 ), array( 'failed', 'refused', 'Failed: Airtable refused the token.' ), $weekly );
$GLOBALS['asked'] = array();
WPCPM_Outcome_Figures::sync_row();

ck( 'the row asks the schedule once for each event: the retry, then the weekly event',
	$GLOBALS['asked'], array( WPCPM_Outcome_Figures::RETRY_HOOK, WPCPM_Outcome_Figures::HOOK ) );

// A set past the maximum age shows nothing on the homepage, and a weekly event that never fired, or
// a run stopped inside the read, leaves no attempt to say why: the row says it, after the attempt's
// own sentence when there is one, and agrees with published() to the second.
$aged_rows = array();

foreach ( array(
	'29 days after the read'          => array( 1791788400 + 29 * DAY_IN_SECONDS, stored( 1791788400 ), array( 'stored', '', '' ), array() ),
	'a second more'                   => array( 1791788400 + 29 * DAY_IN_SECONDS + 1, stored( 1791788400 ), array( 'stored', '', '' ), array() ),
	'four Mondays failed, on 10 Nov'  => array( strtotime( '2026-11-10 10:00:00 UTC' ), stored( 1791788400 ), array( 'failed', 'refused', 'Failed: Airtable refused the token.' ), array() ),
	'a figure under 30, aged too'     => array( 1791788400 + 30 * DAY_IN_SECONDS, stored( 1791788400, array( 87, 361, 413, 92, 11, 12, 75, 9, 12 ) ), array( 'stored', '', '' ), array() ),
	'seven days, by the filter'       => array( 1791788400 + 8 * DAY_IN_SECONDS, stored( 1791788400 ), array( 'stored', '', '' ), array( 'wpcpm_outcome_figures_max_age' => static function () { return 7; } ) ),
	'one day, by the filter'          => array( 1791788400 + 2 * DAY_IN_SECONDS, stored( 1791788400 ), array( 'stored', '', '' ), array( 'wpcpm_outcome_figures_max_age' => static function () { return 1; } ) ),
) as $what => $case ) {
	row( $case[1], $case[2], $weekly );
	$GLOBALS['now']     = $case[0];
	$GLOBALS['filters'] = $case[3];

	$aged_row           = WPCPM_Outcome_Figures::sync_row();
	$aged_rows[ $what ] = array( $aged_row['note'], $aged_row['progress']['error'], array() === WPCPM_Outcome_Figures::published() );
}

$aged_sentence = 'Not shown on the homepage: the figures stored are more than 29 days old.';

ck( 'a set past the maximum age: the row says the homepage shows nothing, after a failed attempt\'s sentence, in place of the note on a figure under 30, in the filter\'s days, and not a second before published() stops showing it',
	$aged_rows,
	array(
		'29 days after the read'         => array( '', '', false ),
		'a second more'                  => array( $aged_sentence, $aged_sentence, true ),
		'four Mondays failed, on 10 Nov' => array( 'Failed: Airtable refused the token. ' . $aged_sentence, 'Failed: Airtable refused the token. ' . $aged_sentence, true ),
		'a figure under 30, aged too'    => array( $aged_sentence, $aged_sentence, true ),
		'seven days, by the filter'      => array( 'Not shown on the homepage: the figures stored are more than 7 days old.', 'Not shown on the homepage: the figures stored are more than 7 days old.', true ),
		'one day, by the filter'         => array( 'Not shown on the homepage: the figures stored are more than 1 day old.', 'Not shown on the homepage: the figures stored are more than 1 day old.', true ),
	) );

// The row is read on every load of the Overview and of the Administrator Dashboard: it reads the two
// options and the schedule and does nothing else.
base();
forget();
$GLOBALS['now']                                       = strtotime( '2026-10-14 10:00:00 UTC' );
$GLOBALS['opts'][ WPCPM_Outcome_Figures::OPTION ]     = stored( 1791183600 );
$GLOBALS['opts'][ WPCPM_Outcome_Figures::RUN_OPTION ] = array( 'time' => strtotime( '2026-10-12 12:00:09 UTC' ), 'result' => 'failed', 'reason' => 'refused', 'message' => 'Failed: Airtable refused the token.', 'counts' => array(), 'monday' => '2026-10-12', 'tries' => 6 );
$GLOBALS['cron'][ WPCPM_Outcome_Figures::HOOK ]       = array( 'at' => strtotime( '2026-10-19 07:00:00 UTC' ), 'recurrence' => 'weekly' );
$state_before = array( $GLOBALS['opts'], $GLOBALS['autoload'], $GLOBALS['cron'], $GLOBALS['sent'], $GLOBALS['visits'], $GLOBALS['cleared'] ?? array() );
$read_only    = WPCPM_Outcome_Figures::sync_row();
$state_after  = array( $GLOBALS['opts'], $GLOBALS['autoload'], $GLOBALS['cron'], $GLOBALS['sent'], $GLOBALS['visits'], $GLOBALS['cleared'] ?? array() );

ck( 'the row only reads: no option written, no event booked or cleared, no request to Airtable or to the dashboard\'s page, and neither the token nor Bearer in what it returns',
	array( $state_after === $state_before, false === strpos( json_encode( $read_only ), 'patSTANDIN' ), false === stripos( json_encode( $read_only ), 'Bearer' ) ),
	array( true, true, true ) );

echo "\n=== For WP-CLI: the stored set, and the names the base gives the four fields ===\n";

row( stored( 1789110000, array( 87, 361, 413, 92, 11, 12, 75, 9, 12 ) ) );
$whole = WPCPM_Outcome_Figures::stored();
row( array( 'read' => 1789110000 ) );
$part = WPCPM_Outcome_Figures::stored();

ck( 'stored() is the set when it is whole, whatever its age or samples, and nothing otherwise',
	array( $whole, $part ), array( stored( 1789110000, array( 87, 361, 413, 92, 11, 12, 75, 9, 12 ) ), array() ) );

base();
$GLOBALS['meta'] = $meta_answer = airtable_answer(
	200,
	array(
		'tables' => array(
			array(
				'id'     => 'tbljYkkVGbeoaWEtY',
				'name'   => 'Students Reports',
				'fields' => array(
					array( 'id' => 'fldyXVlkChJaO9Q47', 'name' => 'Name', 'type' => 'formula' ),
					array( 'id' => 'fldCMdqqJGAUQ9nbV', 'name' => 'Status', 'type' => 'singleSelect' ),
				),
			),
			array(
				'id'     => 'tblx3TH6fp4edQJDm',
				'name'   => 'Feedback',
				'fields' => array(
					array( 'id' => 'fldZXbvOTIGad6r90', 'name' => 'F4 How likely are you to recommend the program?', 'type' => 'singleSelect' ),
				),
			),
		),
	)
);

ck( 'field_names() asks the base\'s metadata once and names each pinned ID from its own table, an ID its table does not hold as empty',
	array( WPCPM_Outcome_Figures::field_names(), count( $GLOBALS['sent'] ), false !== strpos( $GLOBALS['sent'][0], '/meta/bases/appSTANDIN/tables' ) ),
	array(
		array(
			'fldyXVlkChJaO9Q47' => 'Name',
			'fldCMdqqJGAUQ9nbV' => 'Status',
			'fldZXbvOTIGad6r90' => 'F4 How likely are you to recommend the program?',
			'fld8RZMdCLYKtwcLN' => '',
		),
		1,
		true,
	) );

base();
$GLOBALS['meta'] = airtable_answer( 403, array( 'error' => array( 'type' => 'INVALID_PERMISSIONS_OR_MODEL_NOT_FOUND' ) ) );
$refused         = WPCPM_Outcome_Figures::field_names();

ck( 'and hands back the error when the token may not read the metadata',
	array( is_wp_error( $refused ), is_wp_error( $refused ) ? $refused->get_error_data()['status'] : null ), array( true, 403 ) );

// The track editor's copy of the schema, as a build before 1.122.24 kept it, with no names by ID:
// read from it, the four lines would say (not in its table) for fifteen minutes after the deploy.
base();
$GLOBALS['meta'] = $meta_answer;
$GLOBALS['transients'][ WPCPM_Airtable::SCHEMA_TRANSIENT ] = array(
	'read'   => $GLOBALS['now'] - 60,
	'schema' => array(
		'tbljYkkVGbeoaWEtY' => array( 'name' => 'Students Reports', 'primary' => 'Name', 'fields' => array( 'Name' => '', 'Status' => '' ), 'columns' => array() ),
		'tblx3TH6fp4edQJDm' => array( 'name' => 'Feedback', 'primary' => '', 'fields' => array(), 'columns' => array() ),
	),
);
$fresh_names = WPCPM_Outcome_Figures::field_names();

ck( 'field_names() reads the base\'s metadata afresh, never the copy the track editor keeps, which a build before 1.122.24 wrote with no names by ID',
	array( $fresh_names['fldyXVlkChJaO9Q47'], $fresh_names['fldCMdqqJGAUQ9nbV'], count( $GLOBALS['sent'] ) ), array( 'Name', 'Status', 1 ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
