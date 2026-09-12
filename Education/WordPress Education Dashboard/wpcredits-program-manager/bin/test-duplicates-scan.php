<?php
/**
 * The Student Duplicate Finder's scan: three tables read every three hours, duplicates kept.
 *
 * What each block pins, and why:
 *
 * - **It runs where the four syncs do not.** The shared three-hour recurrence, registered before
 *   the event is placed (an event on a recurrence WordPress cannot name is dropped without a word),
 *   150 minutes into the cycle.
 * - A run reads Students, Students Reports and Feedback page by page and keeps **only** the
 *   addresses with more than one row in some table, each classified by the real rules. A row with
 *   no address is counted and left out: it cannot be grouped.
 * - What the site points at is read by value from user and post meta, so a row a site account uses
 *   is locked; the finder's own copies are not a reason to keep a row.
 * - **A failed or an empty read ends the run and keeps the last good list** (spec decision 3.4),
 *   instead of the sync behaviour of staying "running" with no next tick.
 * - Cancel keeps the list too, and a deletion takes its rows out of the stored list at once.
 *
 * Fixtures are synthetic: example.test addresses and record IDs that spell what they are.
 *
 * Run from the plugin root:  php bin/test-duplicates-scan.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['opts']       = array();
$GLOBALS['cron']       = array();
$GLOBALS['recurrence'] = array();
$GLOBALS['pages']      = array();
$GLOBALS['fail_page']  = array();
$GLOBALS['usermeta']   = array();
$GLOBALS['postmeta']   = array();
$GLOBALS['connected']  = true;
$GLOBALS['interval']   = false;

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function add_action( $hook, $callback = null, $priority = 10, $args = 1 ) { $GLOBALS['actions'][ $hook ] = $callback; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $a; return true; }
function add_option( $k, $v, $dep = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ] : false; }
function wp_get_schedules() {
	$schedules = array( 'daily' => array( 'interval' => DAY_IN_SECONDS ) );
	if ( $GLOBALS['interval'] ) {
		$schedules['wpcpm_three_hours'] = array( 'interval' => 3 * HOUR_IN_SECONDS );
	}
	return $schedules;
}
function wp_schedule_event( $ts, $recurrence, $hook ) {
	if ( ! isset( wp_get_schedules()[ $recurrence ] ) ) {
		return false;
	}
	$GLOBALS['cron'][ $hook ]       = $ts;
	$GLOBALS['recurrence'][ $hook ] = $recurrence;
	return true;
}
function wp_schedule_single_event( $ts, $hook ) { $GLOBALS['cron'][ $hook ] = $ts; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ], $GLOBALS['recurrence'][ $hook ] ); return 0; }
function wp_get_scheduled_event( $hook ) {
	if ( ! isset( $GLOBALS['cron'][ $hook ] ) ) {
		return false;
	}
	return (object) array(
		'hook'      => $hook,
		'timestamp' => $GLOBALS['cron'][ $hook ],
		'schedule'  => isset( $GLOBALS['recurrence'][ $hook ] ) ? $GLOBALS['recurrence'][ $hook ] : false,
	);
}

/* ---- the other pieces, stubbed to their contracts ----------------------- */

/** The client: pages handed out from $GLOBALS['pages'][ table ], the offset being the page index. */
class WPCPM_Airtable {
	public function __construct( $settings = null ) {}
	public function fetch_page( $table, array $args = array() ) {
		$GLOBALS['fetched'][] = $table;
		$index = empty( $args['offset'] ) ? 0 : (int) $args['offset'];
		if ( isset( $GLOBALS['fail_page'][ $table ] ) && $GLOBALS['fail_page'][ $table ] === $index ) {
			return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 503): no further detail' );
		}
		$pages = isset( $GLOBALS['pages'][ $table ] ) ? $GLOBALS['pages'][ $table ] : array();
		return array(
			'records' => isset( $pages[ $index ] ) ? $pages[ $index ] : array(),
			'offset'  => isset( $pages[ $index + 1 ] ) ? (string) ( $index + 1 ) : null,
		);
	}
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}
class WPCPM_Settings {
	public static function get() { return array( 'students_table' => 'tblSTUDENTS', 'reports_table' => 'tblREPORTS', 'feedback_table' => 'tblFEEDBACK' ); }
	public static function is_connected() { return $GLOBALS['connected']; }
}
/** The three-hour recurrence is the students sync's; this stand-in only records that it was asked for. */
class WPCPM_Students_Sync {
	const EVERY_THREE_HOURS = 'wpcpm_three_hours';
	public static function register_interval() { $GLOBALS['interval'] = true; }
	public static function cycle_start() { return 1789000000; }
}
class WPCPM_Mentors_Sync {
	public static function tracked_statuses( $settings = null ) {
		$active = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' );
		return array( 'active' => $active, 'past' => array( 'Graduate', 'Dropped out' ), 'all' => $active );
	}
}
class WPCPM_Program {
	public static function labels() { return array( 'In Sensei' => '150h', 'Developer Track' => 'Developer Track' ); }
	public static function track( $status ) { return 'In Sensei' === $status ? '150h' : 'dev'; }
}
class WPCPM_Student_Report_Form {
	public static function fields( $track ) {
		return '150h' === $track
			? array( 'Beginner WordPress User - final grade' => array(), 'Post Reflection: Building Your Personal Website' => array() )
			: array( 'Beginner WordPress Developer' => array(), 'Post Reflection: Building Your Personal Website' => array() );
	}
}

/** Reads by value from user meta and post meta, the two queries `refs_for()` makes. */
class Test_WPDB {
	public $usermeta = 'wp_usermeta', $postmeta = 'wp_postmeta', $posts = 'wp_posts';
	public function prepare( $sql, $args ) { return array( $sql, (array) $args ); }
	public function get_results( $prepared, $output = null ) {
		list( $sql, $ids ) = $prepared;
		$rows = array();
		$from = false !== strpos( $sql, 'wp_usermeta' ) ? $GLOBALS['usermeta'] : $GLOBALS['postmeta'];
		foreach ( $from as $row ) {
			if ( in_array( $row['meta_value'], $ids, true ) ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}
$GLOBALS['wpdb'] = new Test_WPDB();

require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php';

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
 * A record ID that says what it is.
 *
 * @param string $tag Up to fourteen letters and digits.
 * @return string
 */
function rid( $tag ) {
	return 'rec' . str_pad( strtoupper( $tag ), 14, '0' );
}

/**
 * A record in the shape the API returns.
 *
 * @param string $tag     Record ID tag.
 * @param string $created ISO 8601.
 * @param array  $fields  Cells.
 * @return array
 */
function record( $tag, $created, array $fields ) {
	return array(
		'id'          => rid( $tag ),
		'createdTime' => $created,
		'fields'      => $fields,
	);
}

/** Run ticks until the scan stops, the way the screen's poll does. */
function run_to_end() {
	for ( $i = 0; $i < 50 && WPCPM_Duplicates_Scan::is_running(); $i++ ) {
		WPCPM_Duplicates_Scan::run_tick( WPCPM_Duplicates_Scan::BUDGET_AJAX );
	}
}

/**
 * The base the scan reads: one student applied twice (every table twice), one student once, a
 * Students row with no address, and the same address spelled once in capitals.
 */
function base() {
	$GLOBALS['pages'] = array(
		'tblSTUDENTS' => array(
			array(
				record( 'stuold', '2026-01-27T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Full Name' => 'A Student', 'Status' => 'Not moving forward' ) ),
				record( 'stuonce', '2026-03-01T10:00:00.000Z', array( 'Email' => 'once@example.test', 'Full Name' => 'Once Only', 'Status' => 'In Sensei' ) ),
			),
			array(
				record( 'stunomail', '2026-03-02T10:00:00.000Z', array( 'Full Name' => 'No Address', 'Status' => 'Interested' ) ),
			),
			array(
				record( 'stunew', '2026-09-09T10:00:00.000Z', array( 'Email' => 'Student@Example.test', 'Full Name' => 'A Student', 'Status' => 'In Sensei' ) ),
			),
		),
		'tblREPORTS'  => array(
			array(
				record( 'repold', '2026-02-02T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Status' => 'Not moving forward' ) ),
				record( 'reponce', '2026-03-01T10:00:00.000Z', array( 'Email' => 'once@example.test', 'Name' => 'Once Only', 'Status' => 'In Sensei', 'Beginner WordPress User - final grade' => 90 ) ),
			),
			array(
				record( 'repnew', '2026-09-09T10:00:05.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Status' => 'In Sensei' ) ),
			),
		),
		'tblFEEDBACK' => array(
			array(
				record( 'fbold', '2026-02-02T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student' ) ),
				record( 'fbnew', '2026-09-09T10:00:05.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Course' => 'In Sensei' ) ),
				record( 'fbonce', '2026-03-01T10:00:00.000Z', array( 'Email' => 'once@example.test', 'Name' => 'Once Only', 'Course' => 'In Sensei' ) ),
			),
		),
	);
}

$key = WPCPM_Duplicate_Rules::key( 'student@example.test' );

/* ---- the schedule ------------------------------------------------------- */

echo "\n=== The schedule ===\n";

WPCPM_Duplicates_Scan::register_cron();
ck( 'the recurring run is on the shared three-hour recurrence, registered before it was placed', array( $GLOBALS['interval'], $GLOBALS['recurrence'][ WPCPM_Duplicates_Scan::CRON_SCAN ] ), array( true, 'wpcpm_three_hours' ) );
ck( '150 minutes into the cycle, inside it', array( $GLOBALS['cron'][ WPCPM_Duplicates_Scan::CRON_SCAN ] - 1789000000, WPCPM_Duplicates_Scan::SCHEDULE_OFFSET_MINUTES < 180 ), array( 150 * 60, true ) );
ck( 'both events hooked', array( isset( $GLOBALS['actions'][ WPCPM_Duplicates_Scan::CRON_SCAN ] ), isset( $GLOBALS['actions'][ WPCPM_Duplicates_Scan::CRON_TICK ] ) ), array( true, true ) );

$GLOBALS['cron'][ WPCPM_Duplicates_Scan::CRON_SCAN ]       = 123;
$GLOBALS['recurrence'][ WPCPM_Duplicates_Scan::CRON_SCAN ] = 'daily';
WPCPM_Duplicates_Scan::schedule();
ck( 'an event on another recurrence is moved onto this one', $GLOBALS['recurrence'][ WPCPM_Duplicates_Scan::CRON_SCAN ], 'wpcpm_three_hours' );

/* ---- starting ----------------------------------------------------------- */

echo "\n=== Starting ===\n";

$GLOBALS['connected'] = false;
ck( 'without an Airtable connection it will not start, and says why', array( WPCPM_Duplicates_Scan::start()->get_error_code(), WPCPM_Duplicates_Scan::is_running(), '' !== get_option( WPCPM_Duplicates_Scan::OPT_ERROR, '' ) ), array( 'wpcpm_not_connected', false, true ) );
$GLOBALS['connected'] = true;

base();
ck( 'a run starts on Students', array( WPCPM_Duplicates_Scan::start(), WPCPM_Duplicates_Scan::is_running(), get_option( WPCPM_Duplicates_Scan::OPT_STATE )['phase'] ), array( true, true, 'students' ) );
$state = get_option( WPCPM_Duplicates_Scan::OPT_STATE );
ck( 'with the context read once: the active statuses, and every track\'s report-form columns', array( $state['context']['live'][0], $state['context']['work_columns'] ), array( 'In Sensei', array( 'Beginner WordPress User - final grade', 'Post Reflection: Building Your Personal Website', 'Beginner WordPress Developer' ) ) );
ck( 'the working state is not autoloaded', $GLOBALS['autoload'][ WPCPM_Duplicates_Scan::OPT_STATE ], false );
ck( 'and the next tick is on the clock', false !== wp_next_scheduled( WPCPM_Duplicates_Scan::CRON_TICK ), true );

/* ---- a run -------------------------------------------------------------- */

echo "\n=== A run ===\n";

$GLOBALS['usermeta'] = array( array( 'user_id' => 9, 'meta_key' => 'wpcpm_student_record_id', 'meta_value' => rid( 'repnew' ) ) );
$GLOBALS['postmeta'] = array(
	array( 'ID' => 501, 'post_type' => 'wpcpm_mentor_note', 'post_status' => 'private', 'meta_key' => '_wpcpm_student_record', 'meta_value' => rid( 'repnew' ) ),
	array( 'ID' => 502, 'post_type' => 'wpcpm_dup_copy', 'post_status' => 'private', 'meta_key' => '_wpcpm_dup_record', 'meta_value' => rid( 'fbold' ) ),
);

run_to_end();
$report = WPCPM_Duplicates_Scan::report();

ck( 'it read the three tables, page by page', $GLOBALS['fetched'], array( 'tblSTUDENTS', 'tblSTUDENTS', 'tblSTUDENTS', 'tblREPORTS', 'tblREPORTS', 'tblFEEDBACK' ) );
ck( 'and finished: no working state, no lock, no tick, the time recorded', array( WPCPM_Duplicates_Scan::is_running(), get_option( WPCPM_Duplicates_Scan::OPT_LOCK, 'none' ), wp_next_scheduled( WPCPM_Duplicates_Scan::CRON_TICK ), WPCPM_Duplicates_Scan::last_read() > 0 ), array( false, 'none', false, true ) );
ck( 'the report counts every row read, and the one with no address', array( $report['totals'], $report['no_email'] ), array( array( 'students' => 4, 'reports' => 3, 'feedback' => 3 ), 1 ) );
ck( 'it keeps only the address with more than one row, whatever its capitals', array_keys( $report['groups'] ), array( $key ) );
ck( 'the student is headed with a name and an address', array( $report['groups'][ $key ]['name'], strtolower( $report['groups'][ $key ]['email'] ) ), array( 'A Student', 'student@example.test' ) );
ck( 'and flagged for the two spellings', $report['groups'][ $key ]['flags'], array( 'spelling' ) );

$by_id = array();
foreach ( $report['groups'][ $key ]['rows'] as $rows ) {
	foreach ( $rows as $row ) {
		$by_id[ $row['id'] ] = $row;
	}
}
ck( 'each row carries the rules\' proposal', array( $by_id[ rid( 'stuold' ) ]['proposal'], $by_id[ rid( 'repold' ) ]['proposal'], $by_id[ rid( 'fbold' ) ]['proposal'] ), array( 'delete', 'delete', 'delete' ) );
ck( 'the report row a site account and a mentor note point at is locked', array( $by_id[ rid( 'repnew' ) ]['locked'], count( $report['groups'][ $key ]['refs'][ rid( 'repnew' ) ] ) ), array( true, 2 ) );
ck( 'the finder\'s own copy of a row is no reason to keep it', array( $by_id[ rid( 'fbold' ) ]['locked'], isset( $report['groups'][ $key ]['refs'][ rid( 'fbold' ) ] ) ), array( false, false ) );
ck( 'the counts the tiles read', $report['counts'], array( 'addresses' => 1, 'ready' => 1, 'decide' => 0, 'candidates' => array( 'students' => 1, 'reports' => 1, 'feedback' => 1 ), 'held' => array( 'students' => 0, 'reports' => 0, 'feedback' => 0 ) ) );
ck( 'the report is not autoloaded', $GLOBALS['autoload'][ WPCPM_Duplicates_Scan::OPT_REPORT ], false );

/* ---- failing closed ------------------------------------------------------- */

echo "\n=== A failed or an empty read ends the run and keeps the last list ===\n";

$good               = $report;
$GLOBALS['fetched'] = array();
$GLOBALS['fail_page'] = array( 'tblREPORTS' => 1 );
WPCPM_Duplicates_Scan::start();
run_to_end();
ck( 'a page Airtable refused ends the run: not running, no state, no tick', array( WPCPM_Duplicates_Scan::is_running(), get_option( WPCPM_Duplicates_Scan::OPT_STATE, 'none' ), wp_next_scheduled( WPCPM_Duplicates_Scan::CRON_TICK ) ), array( false, 'none', false ) );
ck( 'the error is kept for the screen', get_option( WPCPM_Duplicates_Scan::OPT_ERROR ), 'Airtable request failed (HTTP 503): no further detail' );
ck( 'and the last good list stands', WPCPM_Duplicates_Scan::report(), $good );
ck( 'so the next run can start', WPCPM_Duplicates_Scan::start(), true );
$GLOBALS['fail_page'] = array();
WPCPM_Duplicates_Scan::cancel();

$GLOBALS['pages']['tblFEEDBACK'] = array( array() );
WPCPM_Duplicates_Scan::start();
run_to_end();
ck( 'a table that answers with no rows at all is an error, not a base without students', get_option( WPCPM_Duplicates_Scan::OPT_ERROR ), 'Airtable returned no rows for feedback_table, so the last list is kept.' );
ck( 'and the last good list stands', WPCPM_Duplicates_Scan::report(), $good );
base();

WPCPM_Duplicates_Scan::start();
WPCPM_Duplicates_Scan::cancel();
ck( 'Cancel stops the run and keeps the list', array( WPCPM_Duplicates_Scan::is_running(), WPCPM_Duplicates_Scan::report() === $good ), array( false, true ) );

/* ---- the lock and progress ------------------------------------------------ */

echo "\n=== The lock, and what the progress bar reads ===\n";

WPCPM_Duplicates_Scan::start();
$GLOBALS['opts'][ WPCPM_Duplicates_Scan::OPT_LOCK ] = time();
WPCPM_Duplicates_Scan::run_tick( 1 );
ck( 'a tick another request holds the lock for does nothing', get_option( WPCPM_Duplicates_Scan::OPT_STATE )['phase'], 'students' );
$progress = WPCPM_Duplicates_Scan::progress();
ck( 'the progress carries every key assets/js/admin.js reads', array_keys( $progress ), array( 'running', 'phase', 'label', 'detail', 'percent', 'step', 'step_total', 'step_label', 'stats', 'elapsed', 'idle', 'error', 'stalled' ) );
ck( 'the first phase, as the bar words it', array( $progress['label'], $progress['step_label'], $progress['percent'] ), array( 'Reading Students…', 'Step 1 of 4', 0 ) );
$GLOBALS['opts'][ WPCPM_Duplicates_Scan::OPT_LOCK ] = time() - WPCPM_Duplicates_Scan::LOCK_TIMEOUT - 5;
run_to_end();
ck( 'a stale lock is taken over and the run finishes', array( WPCPM_Duplicates_Scan::is_running(), array_keys( WPCPM_Duplicates_Scan::report()['groups'] ) ), array( false, array( $key ) ) );

/* ---- a delete, reflected at once ------------------------------------------ */

echo "\n=== Deleted rows leave the stored list at once ===\n";

WPCPM_Duplicates_Scan::drop_deleted( array( array( 'key' => $key, 'table' => 'feedback', 'id' => rid( 'fbold' ) ) ) );
$after = WPCPM_Duplicates_Scan::report();
ck( 'one row gone: the student stays, with two tables still doubled', array( array_keys( $after['groups'] ), $after['groups'][ $key ]['counts'], $after['totals']['feedback'] ), array( array( $key ), array( 'students' => 2, 'reports' => 2, 'feedback' => 1 ), 2 ) );

WPCPM_Duplicates_Scan::drop_deleted(
	array(
		array( 'key' => $key, 'table' => 'students', 'id' => rid( 'stuold' ) ),
		array( 'key' => $key, 'table' => 'reports', 'id' => rid( 'repold' ) ),
	)
);
$after = WPCPM_Duplicates_Scan::report();
ck( 'the rest gone: the student leaves the list and the counts say so', array( $after['groups'], $after['counts']['addresses'], $after['counts']['ready'] ), array( array(), 0, 0 ) );

/* ---- uninstall ------------------------------------------------------------ */

echo "\n=== Uninstall ===\n";

WPCPM_Duplicates_Scan::uninstall();
ck( 'uninstall takes the list, the times, the error and both events', array( get_option( WPCPM_Duplicates_Scan::OPT_REPORT, 'none' ), get_option( WPCPM_Duplicates_Scan::OPT_LAST, 'none' ), wp_next_scheduled( WPCPM_Duplicates_Scan::CRON_SCAN ), wp_next_scheduled( WPCPM_Duplicates_Scan::CRON_TICK ) ), array( 'none', 'none', false, false ) );

$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php' );
ck( 'no dash but the plain hyphen in the class', 1 === preg_match( '/\x{2013}|\x{2014}/u', $source ), false );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
