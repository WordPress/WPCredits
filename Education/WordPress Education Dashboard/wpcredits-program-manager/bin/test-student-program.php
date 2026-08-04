<?php
/**
 * The student row's render-time healing.
 *
 * Two syncs write two caches of the same student and are not run together, so whichever has not
 * run since a field was added is a page showing "Not set" for data sitting in the other cache.
 * `WPCPM_Students_Sync::get_program()` borrows the missing value from the mentor's copy.
 *
 * What matters here is the direction of precedence: the student's own row must always win, and the
 * mentor's copy must only ever fill a blank. A healer that overwrote would quietly undo a student's
 * own correction the moment a mentor's cache went stale.
 *
 * Run from the plugin root:  php bin/test-student-program.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['umeta']   = array();
$GLOBALS['users']   = array();
$GLOBALS['opts']    = array();
$GLOBALS['queries'] = 0;

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $roles = array();
	public function __construct( $id = 0 ) { $this->ID = $id; }
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_type = '', $post_status = 'publish';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function esc_url_raw( $s, $p = null ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_email( $s ) { return (string) $s; }
function sanitize_user( $s, $strict = false ) { return (string) $s; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {} function do_action() {}
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $e = 0 ) { return true; }
function delete_transient( $k ) { return true; }
function wp_next_scheduled( $h ) { return false; }
function wp_schedule_single_event() {} function wp_schedule_event() {}
function wp_clear_scheduled_hook() {}
function current_user_can( $c ) { return false; }
function get_current_user_id() { return 0; }
function is_admin() { return false; }
function number_format_i18n( $n, $d = 0 ) { return (string) round( $n, $d ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }

function get_user_meta( $id, $key, $single = false ) {
	return $GLOBALS['umeta'][ (int) $id ][ $key ] ?? '';
}
function update_user_meta( $id, $key, $value ) {
	$GLOBALS['umeta'][ (int) $id ][ $key ] = $value;
	return true;
}
function delete_user_meta( $id, $key ) { unset( $GLOBALS['umeta'][ (int) $id ][ $key ] ); return true; }

/**
 * Users by exact meta match, counting how often it is asked.
 *
 * The count is asserted: the healer must not run this lookup for a row that is already complete,
 * and must not run it twice for the same student in one request.
 */
function get_users( $args = array() ) {
	++$GLOBALS['queries'];

	$key   = $args['meta_key'] ?? '';
	$value = $args['meta_value'] ?? '';
	$out   = array();

	foreach ( $GLOBALS['umeta'] as $id => $meta ) {
		if ( ! isset( $meta[ $key ] ) || $meta[ $key ] !== $value ) {
			continue;
		}

		// `fields` decides the shape, the way WordPress does it: `'ID'` gives a flat list of IDs,
		// `array( 'ID' )` gives rows, anything else gives users. Honoured rather than ignored
		// because the production code asks for `'ID'` specifically — to keep core's user-meta
		// cache from warning — and a stub that always returned objects would pass whichever shape
		// the code used and prove nothing.
		if ( 'ID' === ( $args['fields'] ?? 'all' ) ) {
			$out[] = (int) $id;
		} elseif ( array( 'ID' ) === ( $args['fields'] ?? null ) ) {
			$row     = new stdClass();
			$row->ID = (int) $id;
			$out[]   = $row;
		} else {
			$out[] = new WP_User( $id );
		}
	}

	return $out;
}

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-airtable.php';
require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/class-wpcpm-wporg-profile.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php';
require_once __DIR__ . '/../includes/class-wpcpm-contribution-teams.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-profile.php';

$fails = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
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

/**
 * Build the two caches for one student, and return their user ID.
 *
 * **A fresh ID per scenario, deliberately.** `mentor_side_row()` memoizes per user for the life of
 * the request, which is right in production and wrong for a test that reuses an ID — the second
 * scenario would silently read the first one's answer. Getting this wrong is what made two of these
 * assertions fail on first run.
 *
 * @param array $student_row What this student's own row holds.
 * @param array $mentor_row  What the mentor's copy of them holds.
 * @return int Student user ID.
 */
function seed( array $student_row, array $mentor_row ) {
	static $next = 100;

	$student = ++$next;
	$mentor  = ++$next;

	$GLOBALS['umeta']   = array();
	$GLOBALS['queries'] = 0;

	// The student: their record, their mentor's record, and their own row.
	$GLOBALS['umeta'][ $student ] = array(
		WPCPM_Students_Sync::META_RECORD_ID => 'recSTUDENT1234567',
		WPCPM_Students_Sync::META_MENTOR    => array( 'record_id' => 'recMENTOR12345678', 'name' => 'Kel' ),
		WPCPM_Students_Sync::META_PROGRAM   => $student_row,
	);

	// The mentor: stamped with their Airtable record, holding their copy of the student.
	if ( ! empty( $mentor_row ) ) {
		$GLOBALS['umeta'][ $mentor ] = array(
			WPCPM_Mentors_Sync::META_RECORD_ID => 'recMENTOR12345678',
			WPCPM_Mentors_Sync::META_MENTEES   => array(
				array( 'record_id' => 'recOTHER123456789', 'name' => 'Somebody else', 'field_of_study' => 'Wrong answer' ),
				array( 'record_id' => 'recSTUDENT1234567' ) + $mentor_row,
			),
		);
	}

	return $student;
}

echo "=== Borrowing a missing value ===\n";

$id      = seed(
	array( 'name' => 'Moldir', 'tutor' => 'Simona Beccone' ),
	array( 'field_of_study' => 'Humanities & Social Sciences' )
);
$program = WPCPM_Students_Sync::get_program( $id );

ck( 'a field absent from the student row is filled from the mentor row',
    $program['field_of_study'] ?? '(absent)', 'Humanities & Social Sciences' );
ck( 'and the row it already had is untouched', $program['tutor'], 'Simona Beccone' );
ck( 'the right student was matched, not the first row in the list',
    false !== strpos( wp_json_encode( $program ), 'Wrong answer' ), false );

$id = seed(
	array( 'name' => 'Moldir', 'field_of_study' => '' ),
	array( 'field_of_study' => 'Humanities & Social Sciences' )
);

ck( 'an empty string counts as missing',
    WPCPM_Students_Sync::get_program( $id )['field_of_study'], 'Humanities & Social Sciences' );

echo "\n=== The student's own row always wins ===\n";

// The direction that matters: a mentor's stale cache must never overwrite this student's own value.
$id = seed(
	array( 'field_of_study' => 'Arts & Architecture' ),
	array( 'field_of_study' => 'Something stale' )
);

ck( 'a value this sync wrote is not overwritten by the mentor copy',
    WPCPM_Students_Sync::get_program( $id )['field_of_study'], 'Arts & Architecture' );

echo "\n=== Costs nothing when there is nothing to do ===\n";

$id = seed(
	array( 'field_of_study' => 'Health & Medicine', 'accessibility' => 'None' ),
	array( 'field_of_study' => 'Ignored' )
);
WPCPM_Students_Sync::get_program( $id );

ck( 'a complete row does not look the mentor up at all', $GLOBALS['queries'], 0 );

$id = seed( array(), array( 'field_of_study' => 'Education & Learning' ) );
WPCPM_Students_Sync::get_program( $id );
WPCPM_Students_Sync::get_program( $id );

ck( 'and the lookup is memoized, so a second call is free', $GLOBALS['queries'], 1 );

echo "\n=== Nothing to borrow from ===\n";

$id      = seed( array( 'name' => 'Moldir' ), array() );
$program = WPCPM_Students_Sync::get_program( $id );

ck( 'a student whose mentor has no account still gets their own row',
    array( $program['name'], trim( (string) ( $program['field_of_study'] ?? '' ) ) ),
    array( 'Moldir', '' ) );

$GLOBALS['umeta']   = array();
$GLOBALS['queries'] = 0;

ck( 'a student with no row at all gets an empty array, not a warning',
    WPCPM_Students_Sync::get_program( 999 ), array() );

echo "\n=== Which teams may be written to Airtable ===\n";

// `clean_teams()` is the only thing standing between a posted checkbox value and a linked-record
// write. An unknown record ID is not merely ignored by Airtable — it either errors or links the
// wrong record — so this is tested directly rather than through the handler, which cannot show
// what the payload ended up being.
$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ] = array(
	'v'     => WPCPM_Mentors_Sync::LOOKUPS_VERSION,
	'teams' => array(
		'recTEAM0000000001' => 'Core',
		'recTEAM0000000002' => 'Documentation',
		'recTEAM0000000003' => 'Polyglots',
	),
);

ck( 'the fixture record IDs are the shape Airtable actually uses',
    array_values( array_unique( array_map( 'WPCPM_Mentors_Sync::is_record_id', array_keys( $GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ]['teams'] ) ) ) ),
    array( true ) );

$clean = new ReflectionMethod( 'WPCPM_Student_Profile', 'clean_teams' );

// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1 and deprecated in 8.5.
if ( PHP_VERSION_ID < 80100 ) {
	$clean->setAccessible( true );
}

/**
 * Run the validator.
 *
 * @param mixed $posted What the browser sent.
 * @return string[]
 */
function teams( $posted ) {
	global $clean;

	return $clean->invoke( null, $posted );
}

ck( 'several known teams all survive',
    teams( array( 'recTEAM0000000001', 'recTEAM0000000003' ) ),
    array( 'recTEAM0000000001', 'recTEAM0000000003' ) );

ck( 'the empty value the form always posts is dropped',
    teams( array( 'recTEAM0000000002', '' ) ), array( 'recTEAM0000000002' ) );

ck( 'unchecking everything clears the field rather than refusing the save',
    teams( array( '' ) ), array() );

// The security-relevant case: a hand-edited form must not reach Airtable.
ck( 'an ID that is not in the catalog is dropped, and the rest still save',
    teams( array( 'recTEAM0000000001', 'recNOTACATALOG001', 'recTEAM0000000002' ) ),
    array( 'recTEAM0000000001', 'recTEAM0000000002' ) );

ck( 'nested arrays and other junk are dropped without a type error',
    teams( array( array( 'recTEAM0000000001' ), null, true, 'recTEAM0000000002' ) ),
    array( 'recTEAM0000000002' ) );

ck( 'the same team twice is stored once',
    teams( array( 'recTEAM0000000001', 'recTEAM0000000001' ) ), array( 'recTEAM0000000001' ) );

ck( 'a single scalar still works, for anything posting the old shape',
    teams( 'recTEAM0000000003' ), array( 'recTEAM0000000003' ) );

// With no catalog reaching Airtable would be guesswork, so nothing is written at all.
$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ] = array();

ck( 'with no team catalog, nothing is accepted', teams( array( 'recTEAM0000000001' ) ), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
