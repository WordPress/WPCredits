<?php
/**
 * The preflight (Track Builder, phase T2c): what publishing would refuse, and what it would warn about.
 *
 * The preflight answers two different kinds of question. The definition's own rules are the store's
 * `check()`, the same call the editor makes, so the two can never disagree. Everything else is a
 * question about the base: does this column exist, what type is it, how many columns would the
 * table have afterwards, and is the track's status one of the `Status` choices. Airtable is stood
 * in for here, so every rule below is held without a request.
 *
 * Run from the plugin root:  php bin/test-track-publish.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $s, $d = null ) { return $s; }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function wp_remote_head( $url, $args = array() ) { return $GLOBALS['head'][ $url ] ?? array( 'response' => array( 'code' => 200 ) ); }
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int) ( $r['response']['code'] ?? 0 ) : 0; }

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

/** The store, stood in: the definition, what `check()` says of it, and where a built-in track stands. */
class WPCPM_Track_Store {
	public static $definitions = array();
	public static $errors      = array();
	public static $sources     = array();
	public static $php_diffs   = array();

	public static $published = array();
	public static $logged    = array();
	public static $refuse    = null;
	public static $compiles  = 0;

	const META_AUTOMATION = '_wpcpm_track_automation';

	public static function compile() { ++self::$compiles; return array(); }

	public static $published_copies = array();

	public static function get( $post_id ) { return self::$definitions[ $post_id ] ?? null; }
	public static function published( $post_id ) { return self::$published_copies[ $post_id ] ?? null; }
	public static function check( $post_id, array $definition ) { return self::$errors[ $post_id ] ?? array(); }
	public static function source( $post_id ) { return self::$sources[ $post_id ] ?? 'definition'; }
	public static function php_differences( $post_id, array $definition ) { return self::$php_diffs[ $post_id ] ?? array(); }

	public static $unpublished = array();

	public static function publish( $post_id, $user_id = 0 ) {
		if ( self::$refuse instanceof WP_Error ) { return self::$refuse; }
		self::$published[] = array( (int) $post_id, (int) $user_id );
		return (int) $post_id;
	}

	public static function unpublish( $post_id, $user_id = 0 ) {
		self::$unpublished[] = array( (int) $post_id, (int) $user_id );
		return (int) $post_id;
	}

	public static function log( $post_id, $did, $user_id = 0, array $detail = array() ) {
		self::$logged[] = array( (int) $post_id, (string) $did, (int) $user_id, $detail );
		return true;
	}
}

/** The settings, stood in: the two tables the `Status` choice has to exist on. */
class WPCPM_Settings {
	public static $values = array( 'reports_table' => 'tblReports', 'students_table' => 'tblStudents' );
	public static function get() { return self::$values; }
	public static function get_value( $key, $default = '' ) { return self::$values[ $key ] ?? $default; }
	public static function has_schema_token() { return ! empty( self::$values['schema_token'] ); }
}

/** The client, stood in: one canned schema, a queue of create answers, and what it was asked. */
class WPCPM_Airtable {
	public static $schema  = array();
	public static $reads   = 0;
	public static $answers = array();
	public static $created = array();

	/**
	 * The canned schema. `$later` is what a second read sees, which is how the race the resume
	 * exists for is modelled: the column was not there when the preflight looked, and was by the
	 * time creation was refused.
	 */
	public static $later = null;

	public function fetch_schema() {
		++self::$reads;

		if ( self::$reads > 1 && null !== self::$later ) {
			return self::$later;
		}

		return self::$schema;
	}

	public function create_field( $table, array $field ) {
		self::$created[] = array( $table, $field['name'] );
		$answer          = array_shift( self::$answers );

		if ( null !== $answer ) {
			return $answer;
		}

		// A real base holds the column once Airtable answers success, so a later schema read
		// must too - otherwise a resumed run looks like nothing was ever created, which is
		// exactly what hid finding 1 of the final review until this stub was fixed.
		if ( is_array( self::$schema ) && isset( self::$schema[ $table ]['columns'] ) ) {
			self::$schema[ $table ]['columns'][ $field['name'] ] = $field;
		}

		return $field;
	}
}

$GLOBALS['opts']       = array();
$GLOBALS['meta']       = array();
$GLOBALS['transients'] = array();

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function add_option( $k, $v, $deprecated = '', $autoload = 'yes' ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_post_meta( $post_id, $key, $single = false ) { return $GLOBALS['meta'][ $post_id ][ $key ] ?? ( $single ? '' : array() ); }
function update_post_meta( $post_id, $key, $value ) { $GLOBALS['meta'][ $post_id ][ $key ] = $value; return true; }
function delete_post_meta( $post_id, $key ) { unset( $GLOBALS['meta'][ $post_id ][ $key ] ); return true; }
function wp_slash( $v ) { return $v; }
function wp_unslash( $v ) { return $v; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function get_current_user_id() { return 5; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }

/** The students sync, stood in: how many people hold a status. */
class WPCPM_Students_Sync {
	public static $counts = array();
	public static function count_on_status( $status ) { return (int) ( self::$counts[ $status ] ?? 0 ); }
}

require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-columns.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-publish.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/** The codes a preflight answered, in the order it answered them. */
function codes( array $findings ) {
	return array_map(
		function ( $finding ) {
			return $finding['code'];
		},
		$findings
	);
}

/** A base with the columns named, each of the type given, plus a `Status` single select. */
function base( array $columns, array $status_choices = array( 'In Sensei', 'Developer Track' ), $extra = 0 ) {
	$reports = array();

	foreach ( $columns as $name => $type ) {
		$reports[ $name ] = array( 'type' => $type, 'options' => array() );
	}

	$choices = array();

	foreach ( $status_choices as $choice ) {
		$choices[] = array( 'name' => $choice );
	}

	$reports['Status'] = array( 'type' => 'singleSelect', 'options' => array( 'choices' => $choices ) );

	// Padding, so a table can be brought near the 500-column ceiling without naming 500 columns.
	for ( $i = 0; $i < $extra; $i++ ) {
		$reports[ 'Filler ' . $i ] = array( 'type' => 'singleLineText', 'options' => array() );
	}

	return array(
		'tblReports'  => array( 'name' => 'Students Reports', 'columns' => $reports ),
		'tblStudents' => array( 'name' => 'Students', 'columns' => array( 'Status' => $reports['Status'] ) ),
	);
}

/** A track of two questions, one already in the base and one not. */
function track( $questions = null ) {
	return array(
		'schema_version'  => 1,
		'status'          => 'Marketing Track',
		'key'             => 'marketing',
		'label'           => 'Marketing Track',
		'course_url'      => 'https://learn.wordpress.org/course/marketing/',
		'learn_course_id' => 500001,
		'hours_target'    => 0,
		'hue'             => 'rose',
		'questions'       => null === $questions ? array(
			'What you did' => array( 'label' => 'What you did', 'type' => 'textarea', 'group' => 'project' ),
			'Brand new'    => array( 'label' => 'Brand new', 'type' => 'text', 'group' => 'project' ),
		) : $questions,
	);
}

echo "=== A track that is ready to publish ===\n";

WPCPM_Track_Store::$definitions = array( 7 => track() );
WPCPM_Track_Store::$errors      = array();
WPCPM_Airtable::$schema         = base( array( 'What you did' => 'multilineText' ), array( 'In Sensei', 'Marketing Track' ) );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'nothing is refused', codes( $flight['refusals'] ), array() );
ck( 'and nothing is warned about', codes( $flight['warnings'] ), array() );
ck( 'so it is ready', $flight['ready'], true );
ck( 'the column the base already has is ready, and the other is one to create',
    array( $flight['columns']['ready'], $flight['columns']['create'] ),
    array( array( 'What you did' ), array( 'Brand new' ) ) );

// L8 (Task 9 review): the by-hand list needs the name, the type and the options, not just the
// name, so the column to create carries what WPCPM_Track_Columns::field() says of it.
ck( 'and the column to create carries its Airtable type, for the by-hand list',
    $flight['columns']['detail'], array( 'Brand new' => array( 'name' => 'Brand new', 'type' => 'singleLineText' ) ) );

ck( 'the status is a choice on both tables', $flight['choices'], array( 'reports' => 'ok', 'students' => 'ok' ) );

echo "\n=== What it refuses ===\n";

// The definition's own rules are the store's, asked once. A screen that asked its own questions
// would call a clash fine and Publish would refuse it on the next screen (T2b's decision 2).
WPCPM_Track_Store::$errors = array( 7 => array( array( 'code' => 'status_taken', 'column' => '', 'message' => 'Another track already claims that status.' ) ) );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'a definition the store refuses is refused here, in the store\'s own words',
    array( codes( $flight['refusals'] ), $flight['refusals'][0]['message'], $flight['ready'] ),
    array( array( 'status_taken' ), 'Another track already claims that status.', false ) );

WPCPM_Track_Store::$errors = array();
WPCPM_Airtable::$schema    = base( array( 'What you did' => 'formula' ), array( 'Marketing Track' ) );

ck( 'a computed column is refused: a student\'s answer sent to one is thrown away',
    codes( WPCPM_Track_Publish::preflight( 7 )['refusals'] ), array( 'column_computed' ) );

WPCPM_Airtable::$schema = base( array( 'What you did' => 'multipleRecordLinks' ), array( 'Marketing Track' ) );

ck( 'so is a link column that is not Main Contribution Team, which would reach into another table',
    codes( WPCPM_Track_Publish::preflight( 7 )['refusals'] ), array( 'column_foreign_link' ) );

WPCPM_Airtable::$schema = base( array( 'What you did' => 'singleLineText' ), array( 'Marketing Track' ) );

ck( 'and a column of the wrong type, which would take the wrong shape of answer',
    codes( WPCPM_Track_Publish::preflight( 7 )['refusals'] ), array( 'column_type_mismatch' ) );

echo "\n=== The ceiling, which Airtable enforces part-way through ===\n";

// Airtable refuses the request that passes 500 and leaves everything before it created, so the
// preflight refuses the whole publish rather than half a track (the design's 7.1).
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ), 498 );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'a table that would pass 500 is refused, and says both numbers',
    array( codes( $flight['refusals'] ), $flight['fields'] ),
    array( array( 'fields_ceiling' ), array( 'now' => 500, 'after' => 501 ) ) );

WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ), 448 );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'past 450 it is a warning, and publishing goes ahead',
    array( codes( $flight['refusals'] ), codes( $flight['warnings'] ), $flight['ready'] ),
    array( array(), array( 'fields_near_ceiling' ), true ) );

echo "\n=== The Status choice, which no token can add ===\n";

WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'In Sensei' ) );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'a status that is not a choice yet is checklist item 3, not a refusal',
    array( codes( $flight['refusals'] ), $flight['choices'] ),
    array( array(), array( 'reports' => 'missing', 'students' => 'missing' ) ) );

// A choice differing by case, spacing or an apostrophe is the shape that makes every sync miss
// silently, and the site cannot tell a typo from a deliberate rename (decision 17).
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'marketing  track' ) );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'one that differs only by case and spacing is a warning that names it',
    array( codes( $flight['warnings'] ), $flight['choices']['reports'] ),
    array( array( 'status_choice_near', 'status_choice_near' ), 'near' ) );

// Apostrophes: the curly quote (U+2019, UTF-8 0xE2 0x80 0x99) and the backtick fold to the
// straight quote, as do runs of space. Use a track with straight apostrophe and base with curly.
// Create a track with straight apostrophe in status.
$track_with_apostrophe = track();
$track_with_apostrophe['status'] = "Marketing'Track";
WPCPM_Track_Store::$definitions[7] = $track_with_apostrophe;
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( "Marketing\xe2\x80\x99Track" ), 0 );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'a choice differing by apostrophe type (curly quote) is caught as near',
    array( codes( $flight['warnings'] ), $flight['choices']['reports'] ),
    array( array( 'status_choice_near', 'status_choice_near' ), 'near' ) );

// Create a track with straight apostrophe and base with backtick.
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing`Track' ), 0 );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'a choice differing by apostrophe type (backtick) is caught as near',
    array( codes( $flight['warnings'] ), $flight['choices']['reports'] ),
    array( array( 'status_choice_near', 'status_choice_near' ), 'near' ) );

// Reset to normal track for next section.
WPCPM_Track_Store::$definitions[7] = track();

echo "\n=== The field ceiling ===\n";

// Test at exactly the limits to catch off-by-one errors. The base() function returns:
// columns_named + Status + fillers. So base(..., N) = What you did (1) + Status (1) + N = N+2 total.
// After adding 1 new column ('Brand new'), the result is N+3.
// The condition is "after > FIELD_CEILING" (> 500) so 501+ is refused.

// To land exactly at after = 501 (the first refused), we need now = 500, so extra = 498.
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ), 498 );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'at after = 501 is refused by the ceiling',
    array( codes( $flight['refusals'] ), $flight['fields'] ),
    array( array( 'fields_ceiling' ), array( 'now' => 500, 'after' => 501 ) ) );

// To land exactly at after = 500 (just below the ceiling), we need now = 499, so extra = 497.
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ), 497 );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'at after = 500 is not refused',
    array( codes( $flight['refusals'] ), $flight['fields'] ),
    array( array(), array( 'now' => 499, 'after' => 500 ) ) );

// The condition is "after > FIELD_WARNING" (> 450) so 451+ is warned.
// To land exactly at after = 451 (the first warned), we need now = 450, so extra = 448.
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ), 448 );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'at after = 451 is warned by the threshold',
    array( codes( $flight['refusals'] ), codes( $flight['warnings'] ), $flight['fields'], $flight['ready'] ),
    array( array(), array( 'fields_near_ceiling' ), array( 'now' => 450, 'after' => 451 ), true ) );

// To land exactly at after = 450 (just below the warning), we need now = 449, so extra = 447.
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ), 447 );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'at after = 450 is not warned',
    array( codes( $flight['warnings'] ), $flight['fields'], $flight['ready'] ),
    array( array(), array( 'now' => 449, 'after' => 450 ), true ) );

echo "\n=== A question that cannot have a column ===\n";

// Not every control can be made into a column: `::field()` returns `null` for those. If one is
// not in the base and cannot be created, it is refused.
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ) );

// Track with a question we'll make uncreatable. We can't easily test this without modifying the
// control class, so we stub a definition with an impossible control.
$uncreatable_track = track( array(
	'What you did'    => array( 'label' => 'What you did', 'type' => 'textarea', 'group' => 'project' ),
	'Uncreatable'     => array( 'label' => 'Uncreatable', 'type' => 'not_a_real_type', 'group' => 'project' ),
) );

WPCPM_Track_Store::$definitions[7] = $uncreatable_track;

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'a question whose control cannot be made into a column is refused',
    codes( $flight['refusals'] ), array( 'column_uncreatable' ) );

// Reset for the next section.
WPCPM_Track_Store::$definitions[7] = track();
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ) );

echo "\n=== A built-in track ===\n";

// A built-in track's definition must match its PHP. Test when it does and when it does not.
WPCPM_Track_Store::$sources = array( 7 => 'builtin' );
WPCPM_Airtable::$schema     = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ) );

ck( 'a built-in track whose definition matches its PHP publishes',
    array( codes( WPCPM_Track_Publish::preflight( 7 )['refusals'] ), WPCPM_Track_Publish::preflight( 7 )['ready'] ),
    array( array(), true ) );

// When the definition differs, the preflight refuses it.
WPCPM_Track_Store::$php_diffs = array( 7 => array( 'label', 'hours' ) );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'a built-in track whose definition has drifted is refused, and says what differs',
    array( codes( $flight['refusals'] ), $flight['refusals'][0]['code'], $flight['ready'] ),
    array( array( 'builtin_changed' ), 'builtin_changed', false ) );

WPCPM_Track_Store::$php_diffs = array();

// Those four statuses were the program's before the Track Builder existed and are edited in
// Settings, so publishing a seed never puts back one a manager took out (decision 13).
// A built-in track never adds its status.
ck( 'a built-in track never adds its status to the settings, which is said in a line',
    WPCPM_Track_Publish::preflight( 7 )['adds_status'], false );

WPCPM_Track_Store::$sources = array();

ck( 'while a track of somebody\'s own does add its status, which is what makes its students sync',
    WPCPM_Track_Publish::preflight( 7 )['adds_status'], true );

echo "\n=== The Learn course, which is only ever a warning ===\n";

// Every earlier preflight() in this file asked and cached "reachable" for this same URL, so this
// scenario needs a clear transient before it, or the cached answer would win over the 404 below;
// and the one after, or the "unreachable" answer this scenario writes would leak into every
// preflight() for the rest of the file (the answer is cached across calls - Task 9 review, M4).
$GLOBALS['transients'] = array();
$GLOBALS['head']       = array( 'https://learn.wordpress.org/course/marketing/' => array( 'response' => array( 'code' => 404 ) ) );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'a course that does not answer is a warning and nothing more',
    array( codes( $flight['warnings'] ), $flight['ready'] ), array( array( 'course_unreachable' ), true ) );

// M4 (Task 9 review): the answer is cached behind a transient, not asked fresh on every
// preflight. The course would now answer, but a second preflight before the cache expires still
// sees the warning, which is what proves the check is reading the cache and not the HEAD stub.
$GLOBALS['head'] = array( 'https://learn.wordpress.org/course/marketing/' => array( 'response' => array( 'code' => 200 ) ) );

$flight_again = WPCPM_Track_Publish::preflight( 7 );

ck( 'a second preflight reuses the cached answer rather than asking again',
    codes( $flight_again['warnings'] ), array( 'course_unreachable' ) );

$GLOBALS['head']       = array();
$GLOBALS['transients'] = array();

echo "\n=== When Airtable cannot be read at all ===\n";

WPCPM_Airtable::$schema = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 401)' );

$flight = WPCPM_Track_Publish::preflight( 7 );

ck( 'the preflight refuses rather than guessing, and passes Airtable\'s own words on',
    array( codes( $flight['refusals'] ), $flight['refusals'][0]['message'], $flight['ready'] ),
    array( array( 'schema_unreadable' ), 'Airtable request failed (HTTP 401)', false ) );

echo "\n=== The run: columns first, then the track goes live ===\n";

/** A scenario with nothing recorded, nothing locked and nothing sent. */
function fresh_run() {
	$GLOBALS['opts']              = array();
	$GLOBALS['meta']              = array();
	WPCPM_Airtable::$answers      = array();
	WPCPM_Airtable::$later        = null;
	WPCPM_Airtable::$reads        = 0;
	WPCPM_Airtable::$created      = array();
	WPCPM_Track_Store::$published   = array();
	WPCPM_Track_Store::$published_copies = array();
	WPCPM_Track_Store::$unpublished = array();
	WPCPM_Students_Sync::$counts    = array();
	WPCPM_Track_Store::$logged    = array();
	WPCPM_Track_Store::$refuse    = null;
	WPCPM_Track_Store::$errors    = array();
	WPCPM_Track_Store::$sources   = array();
	WPCPM_Track_Store::$definitions = array( 7 => track() );
	WPCPM_Airtable::$schema       = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ) );
	WPCPM_Settings::$values       = array( 'reports_table' => 'tblReports', 'students_table' => 'tblStudents', 'schema_token' => 'pat-schema' );
}

fresh_run();
$ran = WPCPM_Track_Publish::run( 7, 5 );

ck( 'the one missing column is created, on the reports table',
    WPCPM_Airtable::$created, array( array( 'tblReports', 'Brand new' ) ) );

ck( 'the track is then published, by the person who pressed the button',
    WPCPM_Track_Store::$published, array( array( 7, 5 ) ) );

ck( 'the run says what it created', $ran, array( 'created' => array( 'Brand new' ), 'published' => true ) );

ck( 'the log carries the columns, so the record survives the screen',
    WPCPM_Track_Store::$logged, array( array( 7, 'columns', 5, array( 'columns' => array( 'Brand new' ) ) ) ) );

ck( 'and the lock is let go', array_key_exists( WPCPM_Track_Publish::OPT_LOCK, $GLOBALS['opts'] ), false );

echo "\n=== One run at a time, and a killed run does not wedge it forever (final review, finding 2) ===\n";

fresh_run();
$fresh = WPCPM_Track_Publish::run( 7, 5 );

ck( 'a fresh claim succeeds: nothing was holding the lock',
    array( is_wp_error( $fresh ), array_key_exists( WPCPM_Track_Publish::OPT_LOCK, $GLOBALS['opts'] ) ),
    array( false, false ) );

fresh_run();
$GLOBALS['opts'][ WPCPM_Track_Publish::OPT_LOCK ] = time();
$busy = WPCPM_Track_Publish::run( 7, 5 );

ck( 'a second claim while the first is still fresh is refused, and creates nothing',
    array( $busy->get_error_code(), WPCPM_Airtable::$created ), array( 'wpcpm_track_publish_running', array() ) );

// `add_option()` is what makes the claim atomic, whatever value it writes: T2a's final review
// was about changing the value while the lock is held, not about storing the time it was
// claimed. Storing the time is what lets a lock this old be told apart from one a live run
// still holds.
fresh_run();
$GLOBALS['opts'][ WPCPM_Track_Publish::OPT_LOCK ] = time() - WPCPM_Track_Publish::LOCK_TIMEOUT - 5;
$stale = WPCPM_Track_Publish::run( 7, 5 );

ck( 'a claim over a lock older than the timeout succeeds, so a run a host killed does not wedge every track\'s Publish button until somebody deletes the option by hand',
    array( is_wp_error( $stale ), WPCPM_Airtable::$created ), array( false, array( array( 'tblReports', 'Brand new' ) ) ) );

echo "\n=== A refused preflight stops before anything is created ===\n";

fresh_run();
WPCPM_Track_Store::$errors = array( 7 => array( array( 'code' => 'status_taken', 'column' => '', 'message' => 'Another track already claims that status.' ) ) );
$stopped = WPCPM_Track_Publish::run( 7, 5 );

ck( 'the refusal comes back with the findings, and Airtable is never asked to create anything',
    array( $stopped->get_error_code(), $stopped->get_error_message(), WPCPM_Airtable::$created, WPCPM_Track_Store::$published ),
    array( 'wpcpm_track_preflight', 'Another track already claims that status.', array(), array() ) );

echo "\n=== A failed step stops, and the next press resumes ===\n";

fresh_run();
WPCPM_Track_Store::$definitions = array(
	7 => track(
		array(
			'One'   => array( 'label' => 'One', 'type' => 'text', 'group' => 'project' ),
			'Two'   => array( 'label' => 'Two', 'type' => 'text', 'group' => 'project' ),
			'Three' => array( 'label' => 'Three', 'type' => 'text', 'group' => 'project' ),
		)
	),
);
WPCPM_Airtable::$answers = array( null, new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 500)' ) );
$failed = WPCPM_Track_Publish::run( 7, 5 );

ck( 'it stops at the step that failed, in Airtable\'s own words, with the track still a draft',
    array( $failed->get_error_code(), $failed->get_error_message(), WPCPM_Track_Store::$published ),
    array( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 500)', array() ) );

ck( 'the column that did land is recorded on the post',
    get_post_meta( 7, WPCPM_Track_Publish::META_RUN, true ), array( 'columns' => array( 'One' ) ) );

ck( 'and the lock is let go, so the next press is not refused',
    array_key_exists( WPCPM_Track_Publish::OPT_LOCK, $GLOBALS['opts'] ), false );

WPCPM_Airtable::$created = array();
WPCPM_Airtable::$answers = array();
$resumed = WPCPM_Track_Publish::run( 7, 5 );

ck( 'the next press starts at the first step not recorded',
    WPCPM_Airtable::$created, array( array( 'tblReports', 'Two' ), array( 'tblReports', 'Three' ) ) );

ck( 'it says everything the two runs created together',
    $resumed, array( 'created' => array( 'One', 'Two', 'Three' ), 'published' => true ) );

ck( 'and the record is cleared once the track is live',
    get_post_meta( 7, WPCPM_Track_Publish::META_RUN, true ), '' );

echo "\n=== A resumed run does not trust its own record over the base (final review, finding 1) ===\n";

fresh_run();
WPCPM_Track_Store::$definitions = array(
	7 => track(
		array(
			'One'   => array( 'label' => 'One', 'type' => 'text', 'group' => 'project' ),
			'Two'   => array( 'label' => 'Two', 'type' => 'text', 'group' => 'project' ),
			'Three' => array( 'label' => 'Three', 'type' => 'text', 'group' => 'project' ),
		)
	),
);
WPCPM_Airtable::$answers = array( null, null, new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 500)' ) );
$first_of_three = WPCPM_Track_Publish::run( 7, 5 );

ck( 'two of three land before the third fails',
    array( is_wp_error( $first_of_three ), get_post_meta( 7, WPCPM_Track_Publish::META_RUN, true ) ),
    array( true, array( 'columns' => array( 'One', 'Two' ) ) ) );

// The base loses one of the two that landed - a person tidying a half-made column away in
// Airtable, which decision 18 expects them to do, or deleting the wrong one by mistake. The
// post's own record still claims it landed.
unset( WPCPM_Airtable::$schema['tblReports']['columns']['One'] );
WPCPM_Airtable::$created = array();
WPCPM_Airtable::$answers = array();
$second_of_three = WPCPM_Track_Publish::run( 7, 5 );

ck( 'the next press asks the base again rather than trusting its own record, and creates the column the base lost as well as the one it never reached',
    array( WPCPM_Airtable::$created, is_wp_error( $second_of_three ) ? $second_of_three : $second_of_three['published'] ),
    array( array( array( 'tblReports', 'One' ), array( 'tblReports', 'Three' ) ), true ) );

echo "\n=== A name already taken is read again, not treated as a failure ===\n";

fresh_run();
WPCPM_Airtable::$answers = array( new WP_Error( 'wpcpm_airtable_field_exists', 'Airtable already has a column named "Brand new" on this table.' ) );
// Somebody made it by hand between the preflight and the run, which is the documented way to work
// without a schema token, so the second read finds it with the right type (7.2 step 1).
WPCPM_Airtable::$later   = base( array( 'What you did' => 'multilineText', 'Brand new' => 'singleLineText' ), array( 'Marketing Track' ) );
$by_hand = WPCPM_Track_Publish::run( 7, 5 );

ck( 'a column somebody made by hand counts as landed and the run carries on',
    array( $by_hand, WPCPM_Track_Store::$published ), array( array( 'created' => array( 'Brand new' ), 'published' => true ), array( array( 7, 5 ) ) ) );

fresh_run();
WPCPM_Airtable::$answers = array( new WP_Error( 'wpcpm_airtable_field_exists', 'Airtable already has a column named "Brand new" on this table.' ) );
// The same name on a column of the wrong type is a different thing: writing to it would put the
// answer in the wrong shape, so the run stops and says so.
WPCPM_Airtable::$later   = base( array( 'What you did' => 'multilineText', 'Brand new' => 'checkbox' ), array( 'Marketing Track' ) );
$wrong_type = WPCPM_Track_Publish::run( 7, 5 );

ck( 'but one of the wrong type stops the run',
    array( $wrong_type->get_error_code(), WPCPM_Track_Store::$published ), array( 'wpcpm_track_column_conflict', array() ) );

echo "\n=== With no schema token the columns are a list, not a failure ===\n";

fresh_run();
WPCPM_Settings::$values = array( 'reports_table' => 'tblReports', 'students_table' => 'tblStudents' );
$no_token = WPCPM_Track_Publish::run( 7, 5 );

ck( 'the run refuses before creating anything and names the columns to make by hand',
    array( $no_token->get_error_code(), $no_token->get_error_data(), WPCPM_Airtable::$created ),
    array( 'wpcpm_track_columns_by_hand', array( 'columns' => array( 'Brand new' ) ), array() ) );

fresh_run();
WPCPM_Settings::$values         = array( 'reports_table' => 'tblReports', 'students_table' => 'tblStudents' );
WPCPM_Airtable::$schema         = base( array( 'What you did' => 'multilineText', 'Brand new' => 'singleLineText' ), array( 'Marketing Track' ) );
$nothing_to_make = WPCPM_Track_Publish::run( 7, 5 );

ck( 'and with every column already there it publishes without a schema token at all',
    array( $nothing_to_make, WPCPM_Track_Store::$published ), array( array( 'created' => array(), 'published' => true ), array( array( 7, 5 ) ) ) );

echo "\n=== The checklist: what no token can do ===\n";

fresh_run();

$list = WPCPM_Track_Publish::checklist( 7 );

ck( 'it has the three items, in the order they have to happen',
    array_keys( $list ), array( 'automation', 'welcome', 'choices' ) );

ck( 'none of them is ticked on a track nobody has touched',
    array_column( $list, 'ticked' ), array( false, false, false ) );

ck( 'and each carries the exact value somebody needs to type into Airtable',
    array( false !== strpos( $list['automation']['detail'], 'Marketing Track' ), false !== strpos( $list['choices']['detail'], 'Marketing Track' ) ),
    array( true, true ) );

WPCPM_Track_Publish::tick( 7, 'welcome', 5 );
$list = WPCPM_Track_Publish::checklist( 7 );

ck( 'ticking one records who did it, and stamps it with the time it happened',
    array( $list['welcome']['ticked'], $list['welcome']['by'], abs( $list['welcome']['at'] - time() ) < 5 ), array( true, 5, true ) );

ck( 'and it is logged, because the base cannot be asked whether it happened',
    WPCPM_Track_Store::$logged, array( array( 7, 'tick-welcome', 5, array() ) ) );

ck( 'ticking the welcome email does not recompile: it changes nothing the site reads',
    WPCPM_Track_Store::$compiles, 0 );

// Ticking item 1 is what puts the status into `confirmed_automation_statuses()`, which is the list
// the institution import and institution create read (the design's decision 10). The compiled row
// carries the flag, so the tick has to recompile or the gate stays shut.
WPCPM_Track_Publish::tick( 7, 'automation', 5 );

ck( 'ticking the reports automation writes the flag the compiled row carries',
    get_post_meta( 7, WPCPM_Track_Store::META_AUTOMATION, true ), '1' );

ck( 'and recompiles, or the institution gate would stay shut until something unrelated published',
    WPCPM_Track_Store::$compiles, 1 );

WPCPM_Track_Publish::untick( 7, 'automation', 5 );

ck( 'unticking it takes the flag away again and recompiles',
    array( get_post_meta( 7, WPCPM_Track_Store::META_AUTOMATION, true ), WPCPM_Track_Store::$compiles ), array( '', 2 ) );

ck( 'an item nobody has heard of is refused rather than recorded',
    WPCPM_Track_Publish::tick( 7, 'something', 5 )->get_error_code(), 'wpcpm_track_checklist_item' );

ck( 'unticking an item nobody has heard of is refused too',
    WPCPM_Track_Publish::untick( 7, 'something', 5 )->get_error_code(), 'wpcpm_track_checklist_item' );

// Finding 3 (final review): every other entry point checks the post is a track before writing
// to it; ticking did not. `get_error_code()` is read through a ternary, not called directly on
// the answer, so a regression that lets a non-error through fails this check instead of crashing
// the rest of the suite on a call to a member function of `true`.
$not_a_track = WPCPM_Track_Publish::tick( 99, 'welcome', 5 );

ck( 'ticking a post ID that is not a track is refused, and nothing is written',
    array(
	    is_wp_error( $not_a_track ) ? $not_a_track->get_error_code() : $not_a_track,
	    get_post_meta( 99, WPCPM_Track_Publish::META_CHECKLIST, true ),
    ),
    array( 'wpcpm_track_missing', '' ) );

$untick_not_a_track = WPCPM_Track_Publish::untick( 99, 'welcome', 5 );

ck( 'so is unticking one',
    is_wp_error( $untick_not_a_track ) ? $untick_not_a_track->get_error_code() : $untick_not_a_track,
    'wpcpm_track_missing' );

WPCPM_Track_Publish::tick( 7, 'choices', 5 );

ck( 'ticking the choices item does not recompile: it changes nothing the site reads',
    WPCPM_Track_Store::$compiles, 2 );

WPCPM_Track_Publish::tick( 7, 'automation', 5 );
$list = WPCPM_Track_Publish::checklist( 7 );
$first_at = $list['automation']['at'];

ck( 'ticking the reports automation records who and when',
    array( $list['automation']['by'], $list['automation']['at'] > 0 ), array( 5, true ) );

// Wait a moment so a re-tick would have a measurably different timestamp
sleep( 1 );

WPCPM_Track_Publish::tick( 7, 'automation', 6 );
$list = WPCPM_Track_Publish::checklist( 7 );

ck( 'ticking an already-ticked item records who did it most recently',
    array( $list['automation']['by'], $list['automation']['at'] > $first_at ), array( 6, true ) );

echo "\n=== Verify, which reads and changes nothing ===\n";

fresh_run();
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText', 'Brand new' => 'singleLineText' ), array( 'Marketing Track' ) );
WPCPM_Track_Store::$published_copies = array(
	7 => array( 'status' => 'Marketing Track', 'questions' => array( 'What you did' => array( 'label' => 'What you did', 'type' => 'textarea' ), 'Brand new' => array( 'label' => 'Brand new', 'type' => 'text', 'group' => 'project' ) ) ),
);
$seen = WPCPM_Track_Publish::verify( 7 );

ck( 'every column the definition names is still there, of the type it needs',
    array( $seen['columns']['missing'], $seen['columns']['wrong'] ), array( array(), array() ) );

ck( 'the status is a choice on both tables',
    $seen['choices'], array( 'reports' => 'ok', 'students' => 'ok' ) );

ck( 'and it changed nothing: no column created, nothing published',
    array( WPCPM_Airtable::$created, WPCPM_Track_Store::$published ), array( array(), array() ) );

// A column renamed in the base otherwise surfaces as a silently empty answer: the form keeps
// writing to a name nothing reads (7.4).
fresh_run();
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ) );
WPCPM_Track_Store::$published_copies = array(
	7 => array( 'status' => 'Marketing Track', 'questions' => array( 'What you did' => array( 'label' => 'What you did', 'type' => 'textarea' ), 'Brand new' => array( 'label' => 'Brand new', 'type' => 'text', 'group' => 'project' ) ) ),
);
$seen = WPCPM_Track_Publish::verify( 7 );

ck( 'a column that has gone is named, which is what a rename in the base looks like from here',
    $seen['columns']['missing'], array( 'Brand new' ) );

fresh_run();
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText', 'Brand new' => 'checkbox' ), array( 'Marketing Track' ) );
WPCPM_Track_Store::$published_copies = array(
	7 => array( 'status' => 'Marketing Track', 'questions' => array( 'What you did' => array( 'label' => 'What you did', 'type' => 'textarea' ), 'Brand new' => array( 'label' => 'Brand new', 'type' => 'text', 'group' => 'project' ) ) ),
);
$seen = WPCPM_Track_Publish::verify( 7 );

ck( 'and one whose type has changed under the track is named too',
    $seen['columns']['wrong'], array( 'Brand new' ) );

fresh_run();
WPCPM_Airtable::$schema = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 401)' );
WPCPM_Track_Store::$published_copies = array(
	7 => array( 'status' => 'Marketing Track', 'questions' => array( 'What you did' => array( 'label' => 'What you did', 'type' => 'textarea' ), 'Brand new' => array( 'label' => 'Brand new', 'type' => 'text', 'group' => 'project' ) ) ),
);
$seen = WPCPM_Track_Publish::verify( 7 );

ck( 'and when the base cannot be read it says so rather than reporting everything missing',
    array( is_wp_error( $seen ), $seen->get_error_message() ), array( true, 'Airtable request failed (HTTP 401)' ) );

// Verify reads the published copy for a published track, so it checks what the live form
// writes to, not what the editor currently has. A question added to the draft but not yet
// published would otherwise report a missing column the live form never writes to.
fresh_run();
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText', 'Brand new' => 'singleLineText' ), array( 'Marketing Track' ) );
WPCPM_Track_Store::$published_copies = array(
	7 => array( 'status' => 'Marketing Track', 'questions' => array( 'What you did' => array( 'label' => 'What you did', 'type' => 'textarea' ) ) ),
);
$seen = WPCPM_Track_Publish::verify( 7 );

ck( 'verify() reads the published copy for a published track',
    array( $seen['columns']['missing'], $seen['columns']['wrong'] ), array( array(), array() ) );

// A track that has never been published has no published copy, so checking the draft is
// all there is. But if the draft drifts from a published copy, verify should say so.
fresh_run();
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText', 'Brand new' => 'singleLineText' ), array( 'Marketing Track' ) );
WPCPM_Track_Store::$definitions = array( 7 => track( array( 'What you did' => array( 'label' => 'What you did', 'type' => 'textarea' ), 'Brand new' => array( 'label' => 'Brand new', 'type' => 'text', 'group' => 'project' ) ) ) );
WPCPM_Track_Store::$published_copies = array();
$seen = WPCPM_Track_Publish::verify( 7 );

ck( 'with no published copy, verify says so plainly rather than checking the draft',
    array( is_wp_error( $seen ), $seen->get_error_code() ), array( true, 'wpcpm_track_not_published' ) );

// A published track whose draft has drifted: the live form still writes to "What you did",
// but the draft wants "Brand new" instead. Verify must report on the published copy only.
fresh_run();
WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ) );
WPCPM_Track_Store::$definitions = array( 7 => track( array( 'Brand new' => array( 'label' => 'Brand new', 'type' => 'text', 'group' => 'project' ) ) ) );
WPCPM_Track_Store::$published_copies = array(
	7 => array( 'status' => 'Marketing Track', 'questions' => array( 'What you did' => array( 'label' => 'What you did', 'type' => 'textarea' ) ) ),
);
$seen = WPCPM_Track_Publish::verify( 7 );

ck( 'when the draft drifts from the published copy, verify reports on what is live',
    array( $seen['columns']['missing'], $seen['columns']['wrong'] ), array( array(), array() ) );

echo "\n=== A re-read error is not a conflict ===\n";

fresh_run();
WPCPM_Airtable::$answers = array( new WP_Error( 'wpcpm_airtable_field_exists', 'Airtable already has a column named "Brand new" on this table.' ) );
WPCPM_Airtable::$later   = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 401)' );
$re_read_failed = WPCPM_Track_Publish::run( 7, 5 );

ck( 'a schema re-read that fails is returned, not a false conflict',
    array( $re_read_failed->get_error_code(), $re_read_failed->get_error_message() ),
    array( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 401)' ) );

echo "\n=== The log records the columns ===\n";

fresh_run();
$logged = WPCPM_Track_Publish::run( 7, 5 );

ck( 'a run records the columns in the log entry',
    WPCPM_Track_Store::$logged,
    array( array( 7, 'columns', 5, array( 'columns' => array( 'Brand new' ) ) ) ) );

WPCPM_Track_Store::$logged = array();
WPCPM_Track_Store::log( 8, 'test', 6 );

ck( 'a log call with no detail omits the detail field',
    WPCPM_Track_Store::$logged,
    array( array( 8, 'test', 6, array() ) ) );

echo "\n=== Unpublishing, and the students it would strand ===\n";

fresh_run();
WPCPM_Students_Sync::$counts = array( 'Marketing Track' => 3 );
$refused_down = WPCPM_Track_Publish::take_down( 7, 5 );

ck( 'a track students are on is not taken down, and the message counts them',
    array( $refused_down->get_error_code(), $refused_down->get_error_data(), WPCPM_Track_Store::$unpublished ),
    array( 'wpcpm_track_in_use', array( 'students' => 3 ), array() ) );

ck( 'and the count is in the sentence a person reads',
    false !== strpos( $refused_down->get_error_message(), '3 students are on this track' ), true );

fresh_run();
WPCPM_Students_Sync::$counts = array( 'Marketing Track' => 1 );

ck( 'one student reads as one student, not "1 students"',
    false !== strpos( WPCPM_Track_Publish::take_down( 7, 5 )->get_error_message(), '1 student is on this track' ), true );

fresh_run();

ck( 'with nobody on it the store takes it down',
    array( WPCPM_Track_Publish::take_down( 7, 5 ), WPCPM_Track_Store::$unpublished ), array( 7, array( array( 7, 5 ) ) ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
