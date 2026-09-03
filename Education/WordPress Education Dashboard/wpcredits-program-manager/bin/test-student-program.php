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
/**
 * Filters that actually run, which a pass-through cannot test.
 *
 * With nothing registered this behaves exactly as the pass-through it replaces, so the rest of
 * the file is unaffected; what it adds is the ability to register one and see it take effect.
 * Every filter this plugin exposes is a promise to somebody outside it, and a promise nothing
 * exercises is one that can quietly stop being kept.
 */
$GLOBALS['filters'] = array();
function apply_filters( $t, $v ) {
	foreach ( isset( $GLOBALS['filters'][ $t ] ) ? $GLOBALS['filters'][ $t ] : array() as $callback ) {
		$v = call_user_func( $callback, $v );
	}

	return $v;
}
function add_filter( $t, $callback, $priority = 10, $args = 1 ) { $GLOBALS['filters'][ $t ][] = $callback; }
function add_action() {} function do_action() {}
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $e = 0 ) { return true; }
function delete_transient( $k ) { return true; }

/*
 * A cron array good enough to schedule against: what is registered, on what recurrence, and what
 * was cleared. `$GLOBALS['cron']` holds one entry per hook.
 */
function wp_next_scheduled( $h ) { return isset( $GLOBALS['cron'][ $h ] ) ? $GLOBALS['cron'][ $h ]['timestamp'] : false; }
function wp_get_scheduled_event( $h ) {
	return isset( $GLOBALS['cron'][ $h ] ) ? (object) $GLOBALS['cron'][ $h ] : false;
}
function wp_schedule_event( $when, $recurrence, $hook ) {
	$GLOBALS['cron'][ $hook ] = array( 'hook' => $hook, 'timestamp' => (int) $when, 'schedule' => $recurrence );
	return true;
}
function wp_schedule_single_event() {}
function wp_clear_scheduled_hook( $h = '' ) { unset( $GLOBALS['cron'][ $h ] ); return 1; }
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
require_once __DIR__ . '/../includes/class-wpcpm-settings.php';
require_once __DIR__ . '/../includes/class-wpcpm-airtable.php';
require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/class-wpcpm-wporg-profile.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php';
require_once __DIR__ . '/../includes/class-wpcpm-contribution-teams.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php';

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

// The validator moved with the control: the contribution team is asked for on the report form
// since 1.46.0, not in the profile editor. The assertions below are unchanged — what has to hold
// about a hand-edited linked-record field does not depend on which form posted it.
$clean = new ReflectionMethod( 'WPCPM_Student_Report_Form', 'clean' );

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

	list( , $ids ) = $clean->invoke( null, $posted, array( 'type' => 'team' ) );

	return $ids;
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

echo "\n=== What a saved report carries back to the cards ===\n";

/*
 * **The bug this exists to prevent.** Four of the report form's answers — profile, Slack, team,
 * website — are also rows on the cards, and the cards read the copy the sync left behind. Saving
 * wrote them to Airtable and stopped there, so a student who had just chosen their team was shown
 * *Not set* on their own card until the next weekly sync. Both cached copies are asserted, because
 * there are two of them and updating one is the failure that looks fixed.
 */
$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ] = array(
	'v'     => WPCPM_Mentors_Sync::LOOKUPS_VERSION,
	'teams' => array(
		'recTEAM0000000001' => 'Core',
		'recTEAM0000000002' => 'Documentation',
	),
);

$fields = WPCPM_Mentors_Sync::fields();

$id = seed(
	array( 'name' => 'Celi', 'team' => '', 'website' => '', 'slack' => '' ),
	array( 'name' => 'Celi', 'team' => '', 'website' => '' )
);

$saved = WPCPM_Students_Sync::apply_report(
	$id,
	array(
		$fields['report_team']    => array( 'recTEAM0000000002' ),
		$fields['report_website'] => 'https://celigaroe.com',
		$fields['report_slack']   => '@Celi Garoe',
		$fields['report_profile'] => 'https://profiles.wordpress.org/celigaroe/',
	)
);

$program = $GLOBALS['umeta'][ $id ][ WPCPM_Students_Sync::META_PROGRAM ];

ck( 'something was carried over', $saved, true );
ck( 'the team is stored as its name, not its record ID', $program['team'], 'Documentation' );
ck( 'the website lands on the card row', $program['website'], 'https://celigaroe.com' );
ck( 'so does the Slack name', $program['slack'], '@Celi Garoe' );
ck( 'and the username is derived from the profile URL', $program['username'], 'celigaroe' );
ck( 'the rest of the row is left alone', $program['name'], 'Celi' );

// The mentor's copy is a second cache of the same student, and their card reads it.
$mentees = $GLOBALS['umeta'][ $id + 1 ][ WPCPM_Mentors_Sync::META_MENTEES ];

ck( 'the mentor\'s copy is updated too', $mentees[1]['team'], 'Documentation' );
ck( 'and the right row in it — not the first', $mentees[0]['record_id'], 'recOTHER123456789' );
ck( 'the other student is untouched', isset( $mentees[0]['team'] ), false );

// Clearing every box posts an empty array, and "" is the answer, not a reason to skip the write.
WPCPM_Students_Sync::apply_report( $id, array( $fields['report_team'] => array() ) );

ck( 'unchecking every team clears the card row',
    $GLOBALS['umeta'][ $id ][ WPCPM_Students_Sync::META_PROGRAM ]['team'], '' );
ck( 'and does not disturb the answers beside it',
    $GLOBALS['umeta'][ $id ][ WPCPM_Students_Sync::META_PROGRAM ]['website'], 'https://celigaroe.com' );

// Hours arrives as a number and is kept as the string every other cell on the row is: the
// two clocked tracks divide it by a target, the Developer Track prints it on its own, and
// both of those are formatting decisions made where the value is printed rather than here.
WPCPM_Students_Sync::apply_report( $id, array( $fields['report_hours'] => 42 ) );

ck( 'hours land on the card row', $GLOBALS['umeta'][ $id ][ WPCPM_Students_Sync::META_PROGRAM ]['hours'], '42' );
ck( 'and on the mentor\'s copy of that student',
    $GLOBALS['umeta'][ $id + 1 ][ WPCPM_Mentors_Sync::META_MENTEES ][1]['hours'], '42' );
// Zero is an answer. A student who has logged nothing is not a student whose hours are unknown,
// and `array_key_exists` in the loop is what keeps the two apart.
WPCPM_Students_Sync::apply_report( $id, array( $fields['report_hours'] => 0 ) );
ck( 'zero hours are written rather than skipped',
    $GLOBALS['umeta'][ $id ][ WPCPM_Students_Sync::META_PROGRAM ]['hours'], '0' );

// A save of nothing this touches — a grade, say — must not write user meta at all.
$id = seed( array( 'name' => 'Moldir', 'team' => 'Core' ), array( 'name' => 'Moldir' ) );

ck( 'a report with none of these five columns changes nothing',
    WPCPM_Students_Sync::apply_report( $id, array( 'Community meeting etiquette - final grade' => 90 ) ),
    false );
ck( 'and leaves the row as it was',
    $GLOBALS['umeta'][ $id ][ WPCPM_Students_Sync::META_PROGRAM ]['team'], 'Core' );

echo "\n=== When the sync runs ===\n";

/*
 * **A recurring event keeps the schedule it was created with.** Changing the interval in the code
 * changes nothing for a site that already has the event — `wp_next_scheduled()` answers "yes, it
 * exists" and the old recurrence stays. So the upgrade path is the thing worth asserting: an
 * existing daily event has to be replaced, not left alone.
 */
$GLOBALS['cron'] = array();

WPCPM_Students_Sync::schedule();

$event = wp_get_scheduled_event( WPCPM_Students_Sync::CRON_AUTO );

ck( 'a fresh site gets the three-hour schedule', $event->schedule, WPCPM_Students_Sync::EVERY_THREE_HOURS );
ck( 'and the interval behind that name is three hours',
    WPCPM_Students_Sync::cron_interval( array() )[ WPCPM_Students_Sync::EVERY_THREE_HOURS ]['interval'], 3 * HOUR_IN_SECONDS );

// The upgrade: what every site running an older version actually has.
$GLOBALS['cron'] = array(
	WPCPM_Students_Sync::CRON_AUTO => array(
		'hook'      => WPCPM_Students_Sync::CRON_AUTO,
		'timestamp' => time() + DAY_IN_SECONDS,
		'schedule'  => 'daily',
	),
);

WPCPM_Students_Sync::schedule();

ck( 'an existing daily event is moved onto the new interval',
    wp_get_scheduled_event( WPCPM_Students_Sync::CRON_AUTO )->schedule, WPCPM_Students_Sync::EVERY_THREE_HOURS );

// And having been moved, it stays put: rescheduling on every request would push the next run
// further away each time and the sync would never fire at all.
$when = wp_get_scheduled_event( WPCPM_Students_Sync::CRON_AUTO )->timestamp;

WPCPM_Students_Sync::schedule();
WPCPM_Students_Sync::schedule();

ck( 'a correct event is left where it is',
    wp_get_scheduled_event( WPCPM_Students_Sync::CRON_AUTO )->timestamp, $when );

echo "\n=== A run in progress is not restarted ===\n";

// Connected, or `start()` refuses before it gets as far as the guard being tested.
$GLOBALS['opts'][ WPCPM_Settings::OPTION ] = array( 'auto_sync' => true, 'api_token' => 'patTEST', 'base_id' => 'appTEST' );

/**
 * Put the sync into a given state and report whether `cron_auto()` started a new run.
 *
 * @param array $state Sync state to seed.
 * @return bool
 */
function restarted( array $state ) {
	$GLOBALS['opts'][ WPCPM_Students_Sync::OPT_STATE ] = $state;

	WPCPM_Students_Sync::cron_auto();

	$now = $GLOBALS['opts'][ WPCPM_Students_Sync::OPT_STATE ];

	// `start()` writes a state of its own, from the first phase with an empty cursor.
	return 'reports' === $now['phase'] && 0 === $now['cursor'] && $now !== $state;
}

$mid = array( 'phase' => 'mentors', 'cursor' => 40, 'started' => time() - 600, 'touched' => time() - 5, 'stats' => array() );

ck( 'a run part-way through is left to finish', restarted( $mid ), false );
ck( 'and its progress is untouched', $GLOBALS['opts'][ WPCPM_Students_Sync::OPT_STATE ]['cursor'], 40 );

// A run whose ticks stopped is not going to finish on its own.
$dead = array( 'phase' => 'mentors', 'cursor' => 40, 'started' => time() - 7200, 'touched' => time() - 7200, 'stats' => array() );

ck( 'a stalled run is started again', restarted( $dead ), true );

$done = array( 'phase' => 'done', 'cursor' => 0, 'started' => time() - 600, 'touched' => time() - 600, 'stats' => array() );

ck( 'a finished run does not block the next one', restarted( $done ), true );

echo "\n=== The badge a status is painted with ===\n";

ck( 'the 150-hour track keeps the sensei badge', WPCPM_Program::badge( WPCPM_Program::STATUS_150H ), 'sensei' );
ck( 'the 50-hour track has its own', WPCPM_Program::badge( WPCPM_Program::STATUS_50H ), '50h' );
ck( 'and so does the Developer Track', WPCPM_Program::badge( WPCPM_Program::STATUS_DEV ), 'dev' );
// The two statuses decision 21 added. Before this they fell through to the sensei colour, which
// told a mentor that a paused student was still working.
ck( 'Paused is its own badge, not the sensei one', WPCPM_Program::badge( 'Paused' ), 'paused' );
ck( 'Pending graduation is its own too', WPCPM_Program::badge( 'Pending graduation' ), 'pending' );
ck( 'a finished student keeps the plain badge', array( WPCPM_Program::badge( 'Graduate' ), WPCPM_Program::badge( 'Dropped out' ), WPCPM_Program::badge( '' ) ), array( '', '', '' ) );
ck( 'and the label is the status itself for both', array( WPCPM_Program::label( 'Paused' ), WPCPM_Program::label( 'Pending graduation' ) ), array( 'Paused', 'Pending graduation' ) );
// Every modifier the class can return has a rule, or the badge is painted with nothing.
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/dashboard.css' );
foreach ( array( 'sensei', '50h', 'dev', 'paused', 'pending' ) as $modifier ) {
	ck( sprintf( 'dashboard.css styles wpcpm-badge--%s', $modifier ), false !== strpos( $css, '.wpcpm-badge--' . $modifier . ' {' ), true );
}

echo "\n=== The three tracks ===\n";

// `is_50h` was a boolean carried on every synced row until 1.61.0. Three tracks do not fit one, and
// a second flag beside it would have made "both true" representable. The track is derived from the
// status both syncs already store, so these are the only three places it can be wrong.

foreach ( array(
	'In Sensei'     => '150h',
	'In Sensei 50h' => '50h',
	'Developer Track' => 'dev',
) as $status => $want ) {
	ck( sprintf( '%s is the %s track', $status, $want ), WPCPM_Program::track( $status ), $want );
	ck( sprintf( '%s is a track', $status ), WPCPM_Program::is_track( $status ), true );
	ck( sprintf( '%s has a course', $status ), '' !== WPCPM_Program::course_url( $status ), true );
}

// A finished student is in a *state*, not on a track, and putting a course button on somebody who
// has graduated is the reason that distinction exists.
foreach ( array( 'Graduate', 'Dropped out', 'Paused', 'Fail', '', 'Something new in Airtable' ) as $status ) {
	ck( sprintf( '%s is on no track', '' === $status ? '(empty)' : $status ), WPCPM_Program::track( $status ), '' );
	ck( sprintf( '%s is not a track', '' === $status ? '(empty)' : $status ), WPCPM_Program::is_track( $status ), false );
}

// The status passes through as its own label rather than being invented, so an unknown status shows
// what Airtable says instead of a blank.
ck( 'an unknown status is its own label', WPCPM_Program::label( 'Something new' ), 'Something new' );

// Whitespace is what a copy-paste into Airtable leaves behind.
ck( 'a padded status still resolves', WPCPM_Program::track( '  Developer Track  ' ), 'dev' );

// The developer track shows the name the base uses, so screen and base agree.
ck( 'the developer track is named after its status',
    WPCPM_Program::label( 'Developer Track' ), 'Developer Track' );

// **The labels map is what `is_track()` tests**, which is what gates the feedback surveys. Losing
// the entry would turn them off for this track without any other symptom.
ck( 'and is in the labels map, which is what gates the surveys',
    isset( WPCPM_Program::labels()['Developer Track'] ), true );

// Reading the wrong formula column gives a working link to the wrong form — a failure that looks
// like success until a student fills in another track's questions.
ck( 'each track reads its own reporting-form link',
    array(
        WPCPM_Mentors_Sync::link_field( '150h' ),
        WPCPM_Mentors_Sync::link_field( '50h' ),
        WPCPM_Mentors_Sync::link_field( 'dev' ),
    ),
    array( 'report_link', 'report_link_50h', 'report_link_dev' ) );

ck( 'and a finished student keeps the 150-hour one', WPCPM_Mentors_Sync::link_field( '' ), 'report_link' );

// The three link columns have to be named the same way in both places, or the sync asks Airtable
// for a field that does not exist and the link comes back empty.
$names = WPCPM_Mentors_Sync::fields();

ck( 'and all three link fields are named',
    array( $names['report_link'], $names['report_link_50h'], $names['report_link_dev'] ),
    array( 'Personal link', '50h personal link', 'Dev Track ONLY personal link' ) );

// Without the status in this list the sync's Airtable formula never asks for those students, and
// the whole track looks broken while every line of code is right.
ck( 'the developer track is one of the statuses the sync fetches',
    in_array( 'Developer Track', WPCPM_Settings::defaults()['student_statuses'], true ), true );

echo "\n=== The hours a status is worked towards ===\n";

ck( 'the 150-hour track is 150', WPCPM_Program::hours_target( 'In Sensei' ), 150 );
ck( 'the 50-hour track is 50', WPCPM_Program::hours_target( 'In Sensei 50h' ), 50 );
// Not a gap in the map. The track is worked to merged contributions rather than to a clock,
// so a denominator here would be one the program does not have.
ck( 'the developer track has no target', WPCPM_Program::hours_target( 'Developer Track' ), 0 );
ck( 'and says so rather than being asked to prove it', WPCPM_Program::has_hours_target( 'Developer Track' ), false );
ck( 'the two clocked tracks say yes', array( WPCPM_Program::has_hours_target( 'In Sensei' ), WPCPM_Program::has_hours_target( 'In Sensei 50h' ) ), array( true, true ) );

// A finished state and a typo answer the same way, which is the answer a caller wants: print
// no denominator. Telling one from the other is what `is_track()` is for.
ck( 'a status the map never heard of is 0', WPCPM_Program::hours_target( 'Graduated' ), 0 );
ck( 'and an empty status is 0 rather than a notice', WPCPM_Program::hours_target( '' ), 0 );
ck( 'padding is trimmed, as it is everywhere else a status is read', WPCPM_Program::hours_target( '  In Sensei  ' ), 150 );

// Every track `labels()` knows has a row here, or a fourth track added to one map and not the
// other would silently lose its denominator on every page that prints one.
$missing = array_diff( array_keys( WPCPM_Program::labels() ), array_keys( WPCPM_Program::hours_targets() ) );
ck( 'every track in labels() has a row in the hours map', $missing, array() );

// The filter is what an institution with its own target reaches for, so it has to be able to
// add a status as well as change one.
add_filter( 'wpcpm_program_hours_targets', function ( $targets ) { $targets['In Sensei 25h'] = 25; return $targets; } );
ck( 'the filter can add a status', WPCPM_Program::hours_target( 'In Sensei 25h' ), 25 );
ck( 'and the added status has a target', WPCPM_Program::has_hours_target( 'In Sensei 25h' ), true );
$GLOBALS['filters']['wpcpm_program_hours_targets'] = array();

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
