<?php
/**
 * The Student Duplicate Finder's delete: a posted selection in, the outcome the screen prints out.
 *
 * `WPCPM_Duplicate_Delete::run()` against a base this suite holds, with the real rules, the real
 * scan's report, the real vault and the real seal. What each block pins, and why:
 *
 * - **Nothing reaches Airtable before the guards say yes**: the switch, a scan in progress, a
 *   selection that stands for nothing. No read, no copy, no delete.
 * - The Slack request's example goes in one confirmation, read again from the base first by its
 *   lowercased address, children first (Feedback, Students Reports, Students), each row sealed
 *   before it goes and logged once Airtable says it went, and the student leaves the stored list.
 * - **The stored report is the menu, not the authority**: a row the site began pointing at since
 *   the scan, or a row that gained answers, is refused, and nothing is kept or written for it.
 * - Never the last row an address has in a table, however the ticks fell.
 * - Failing closed: a re-read Airtable refuses deletes nothing; a batch Airtable refuses stops the
 *   run, leaves the copies of the rows it was sent pending for the daily job, and removes the
 *   copies of rows never sent.
 *
 * Fixtures are synthetic: example.test addresses and record IDs that spell what they are.
 *
 * Run from the plugin root:  php bin/test-duplicate-delete.php
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

$GLOBALS['opts']            = array();
$GLOBALS['posts']           = array();
$GLOBALS['meta']            = array();
$GLOBALS['cron']            = array();
$GLOBALS['calls']           = array();
$GLOBALS['usermeta']        = array();
$GLOBALS['postmeta']        = array();
$GLOBALS['switch']          = true;
$GLOBALS['insert_calls']    = 0;
$GLOBALS['insert_fails_at'] = 0;
$GLOBALS['now']             = time();

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = '', $post_title = '', $post_content = '', $post_author = 0, $post_date_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function add_action() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_option( $k, $v, $dep = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ] : false; }
function wp_schedule_single_event( $ts, $hook ) { $GLOBALS['cron'][ $hook ] = $ts; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ] ); return 0; }
function wp_insert_post( $args, $wp_error = false ) {
	static $next = 100;

	++$GLOBALS['insert_calls'];

	if ( ! empty( $GLOBALS['insert_fails_at'] ) && $GLOBALS['insert_calls'] === $GLOBALS['insert_fails_at'] ) {
		return new WP_Error( 'db_insert_error', 'Could not insert post into the database.' );
	}

	$post                = new WP_Post();
	$post->ID            = ++$next;
	$post->post_type     = $args['post_type'];
	$post->post_status   = $args['post_status'];
	$post->post_title    = $args['post_title'];
	$post->post_content  = $args['post_content'];
	$post->post_author   = (int) $args['post_author'];
	$post->post_date_gmt = gmdate( 'Y-m-d H:i:s', $GLOBALS['now'] );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null; }
function wp_update_post( $args ) { return (int) $args['ID']; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['meta'][ (int) $id ] ); return true; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ (int) $id ][ $k ] = $v; return true; }
function get_post_meta( $id, $k = '', $single = false ) { return isset( $GLOBALS['meta'][ (int) $id ][ $k ] ) ? $GLOBALS['meta'][ (int) $id ][ $k ] : ''; }
function get_posts( $args ) {
	$posts = array_filter( $GLOBALS['posts'], static function ( $p ) use ( $args ) { return $p->post_type === $args['post_type']; } );
	krsort( $posts );
	return ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? array_keys( $posts ) : array_values( $posts );
}

/* ---- the other pieces, stubbed to their contracts ----------------------- */

/**
 * Airtable, as the suite holds it in $GLOBALS['base']: reads answer from it, deletes take from it.
 *
 * A read with a formula answers only the rows whose lowercased Email the formula names, which is
 * what `formula_in()` with lowercasing asks Airtable for.
 */
class WPCPM_Airtable {
	public function __construct( $settings = null ) {}
	public function fetch_page( $table, array $args = array() ) {
		$formula            = isset( $args['formula'] ) ? (string) $args['formula'] : '';
		$GLOBALS['calls'][] = array( 'read', $table, $formula );
		if ( '' !== $formula && ! empty( $GLOBALS['read_fails'] ) ) {
			return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 503): no further detail' );
		}
		$records = isset( $GLOBALS['base'][ $table ] ) ? $GLOBALS['base'][ $table ] : array();
		if ( '' !== $formula ) {
			preg_match_all( "/= '([^']*)'/", $formula, $named );
			$records = array_values(
				array_filter(
					$records,
					static function ( $record ) use ( $named ) {
						return in_array( strtolower( trim( isset( $record['fields']['Email'] ) ? (string) $record['fields']['Email'] : '' ) ), $named[1], true );
					}
				)
			);
		}
		return array( 'records' => $records, 'offset' => null );
	}
	public function formula_in( $field, array $values, $lower = false ) {
		$tests = array();
		foreach ( $values as $value ) {
			$tests[] = sprintf( "LOWER({%s}) = '%s'", $field, strtolower( $value ) );
		}
		return 1 === count( $tests ) ? $tests[0] : 'OR(' . implode( ',', $tests ) . ')';
	}
	public function delete_records( $table, array $ids ) {
		$GLOBALS['calls'][] = array( 'delete', $table, $ids );
		if ( isset( $GLOBALS['refuse'][ $table ] ) ) {
			return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 422): INVALID_REQUEST_UNKNOWN', array( 'status' => 422, 'deleted' => array() ) );
		}
		$GLOBALS['base'][ $table ] = array_values(
			array_filter(
				$GLOBALS['base'][ $table ],
				static function ( $record ) use ( $ids ) {
					return ! in_array( $record['id'], $ids, true );
				}
			)
		);
		return array_fill_keys( $ids, true );
	}
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}
class WPCPM_Settings {
	public static function get() { return array( 'base_id' => 'appTEST', 'students_table' => 'tblSTUDENTS', 'reports_table' => 'tblREPORTS', 'feedback_table' => 'tblFEEDBACK', 'duplicate_delete_enabled' => $GLOBALS['switch'] ); }
	public static function is_connected() { return true; }
}
class WPCPM_Mentors_Sync {
	public static function tracked_statuses( $settings = null ) {
		$active = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused', 'Pending graduation' );
		return array( 'active' => $active, 'past' => array( 'Graduate', 'Dropped out' ), 'all' => $active );
	}
}
class WPCPM_Program {
	public static function labels() { return array( 'In Sensei' => '150h' ); }
	public static function track( $status ) { return '150h'; }
}
class WPCPM_Student_Report_Form {
	public static function fields( $track ) { return array( 'Beginner WordPress User - final grade' => array() ); }
}
class Test_WPDB {
	public $usermeta = 'wp_usermeta', $postmeta = 'wp_postmeta', $posts = 'wp_posts';
	public function prepare( $sql, $args ) { return array( $sql, (array) $args ); }
	public function get_results( $prepared, $output = null ) {
		list( $sql, $ids ) = $prepared;
		$rows = array();
		foreach ( false !== strpos( $sql, 'wp_usermeta' ) ? $GLOBALS['usermeta'] : $GLOBALS['postmeta'] as $row ) {
			if ( in_array( $row['meta_value'], $ids, true ) ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}
$GLOBALS['wpdb'] = new Test_WPDB();

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-secret.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-delete.php';

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

/**
 * The base: the Slack request's example, Ready, and a second student whose newest Students row did
 * not move forward while the older one is live, which is a decision.
 */
function base() {
	$GLOBALS['base'] = array(
		'tblSTUDENTS' => array(
			record( 'stuold', '2026-01-27T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Full Name' => 'A Student', 'Status' => 'Not moving forward' ) ),
			record( 'stunew', '2026-09-09T10:00:00.000Z', array( 'Email' => 'Student@example.test', 'Full Name' => 'A Student', 'Status' => 'In Sensei' ) ),
			record( 'othold', '2026-02-01T10:00:00.000Z', array( 'Email' => 'other@example.test', 'Full Name' => 'Other Student', 'Status' => 'In Sensei' ) ),
			record( 'othnew', '2026-08-01T10:00:00.000Z', array( 'Email' => 'other@example.test', 'Full Name' => 'Other Student', 'Status' => 'Not moving forward' ) ),
		),
		'tblREPORTS'  => array(
			record( 'repold', '2026-02-02T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Status' => 'Not moving forward' ) ),
			record( 'repnew', '2026-09-09T10:00:05.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Status' => 'In Sensei' ) ),
		),
		'tblFEEDBACK' => array(
			record( 'fbold', '2026-02-02T10:00:00.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student' ) ),
			record( 'fbnew', '2026-09-09T10:00:05.000Z', array( 'Email' => 'student@example.test', 'Name' => 'A Student', 'Course' => 'In Sensei' ) ),
		),
	);
}

/** A fresh base, a fresh scan of it, and nothing recorded yet. */
function fresh() {
	base();
	$GLOBALS['posts']           = array();
	$GLOBALS['meta']            = array();
	$GLOBALS['usermeta']        = array();
	$GLOBALS['postmeta']        = array();
	$GLOBALS['refuse']          = array();
	$GLOBALS['read_fails']      = false;
	$GLOBALS['switch']          = true;
	$GLOBALS['insert_calls']    = 0;
	$GLOBALS['insert_fails_at'] = 0;
	WPCPM_Duplicates_Scan::start();
	for ( $i = 0; $i < 20 && WPCPM_Duplicates_Scan::is_running(); $i++ ) {
		WPCPM_Duplicates_Scan::run_tick( WPCPM_Duplicates_Scan::BUDGET_AJAX );
	}
	$GLOBALS['calls'] = array();
}

/**
 * What was asked of Airtable since the scan: each call's kind and table, and for a delete its IDs.
 *
 * @param string $kind 'read' or 'delete'.
 * @return array
 */
function asked( $kind ) {
	$out = array();
	foreach ( $GLOBALS['calls'] as $call ) {
		if ( $kind === $call[0] ) {
			$out[] = 'delete' === $kind ? array( $call[1], $call[2] ) : $call[1];
		}
	}
	return $out;
}

/**
 * The copies kept, by record ID, with their state.
 *
 * @return array<string, string>
 */
function copies() {
	$out = array();
	foreach ( array_keys( $GLOBALS['posts'] ) as $id ) {
		$out[ get_post_meta( $id, WPCPM_Duplicate_Vault::META_RECORD ) ] = get_post_meta( $id, WPCPM_Duplicate_Vault::META_STATE );
	}
	ksort( $out );
	return $out;
}

$ready = WPCPM_Duplicate_Rules::key( 'student@example.test' );
$other = WPCPM_Duplicate_Rules::key( 'other@example.test' );

/* ---- the guards --------------------------------------------------------- */

echo "\n=== Nothing reaches Airtable before the guards say yes ===\n";

fresh();
ck( 'the scan found the two students, one Ready', array( array_keys( WPCPM_Duplicates_Scan::report()['groups'] ), WPCPM_Duplicates_Scan::report()['groups'][ $ready ]['verdict'] ), array( array( $ready, $other ), 'ready' ) );

$GLOBALS['switch'] = false;
ck( 'deleting switched off: refused', WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 ), array( 'status' => 'switched-off' ) );
ck( 'and nothing was read, deleted or copied', array( $GLOBALS['calls'], copies() ), array( array(), array() ) );
$GLOBALS['switch'] = true;

WPCPM_Duplicates_Scan::start();
ck( 'a scan in progress: refused, because it is about to write a new list', WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 ), array( 'status' => 'scan-running' ) );
WPCPM_Duplicates_Scan::cancel();

$outcome = WPCPM_Duplicate_Delete::run( array( 'ffffffffffffffff' ), array( 'students:' . rid( 'stunew' ) ), 7 );
ck( 'a selection that stands for nothing: nothing, with why', array( $outcome['status'], array_column( $outcome['refused'], 'code' ) ), array( 'nothing', array( 'unknown', 'not-selectable' ) ) );
ck( 'and still nothing asked of Airtable', array( $GLOBALS['calls'], copies() ), array( array(), array() ) );

/* ---- the Slack example ---------------------------------------------------- */

echo "\n=== The Slack example goes in one confirmation ===\n";

$outcome = WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 );
ck( 'deleted: one row from each table, nothing refused', array( $outcome['status'], $outcome['deleted'], $outcome['refused'] ), array( 'deleted', array( 'students' => 1, 'reports' => 1, 'feedback' => 1 ), array() ) );
ck( 'and the copies are kept for thirty days', abs( $outcome['until'] - ( time() + 30 * DAY_IN_SECONDS ) ) < 5, true );
ck( 'the base was read again first, in the three tables a row was chosen from', asked( 'read' ), array( 'tblSTUDENTS', 'tblREPORTS', 'tblFEEDBACK' ) );
ck( 'by the address, lowercased', $GLOBALS['calls'][0][2], "LOWER({Email}) = 'student@example.test'" );
ck( 'then children first: Feedback, Students Reports, Students, the older row of each', asked( 'delete' ), array( array( 'tblFEEDBACK', array( rid( 'fbold' ) ) ), array( 'tblREPORTS', array( rid( 'repold' ) ) ), array( 'tblSTUDENTS', array( rid( 'stuold' ) ) ) ) );
ck( 'each row has its copy, confirmed, and the copies name who deleted them', array( copies(), array_values( array_unique( array_map( static function ( $post ) { return $post->post_author; }, $GLOBALS['posts'] ) ) ) ), array( array( rid( 'fbold' ) => 'deleted', rid( 'repold' ) => 'deleted', rid( 'stuold' ) => 'deleted' ), array( 7 ) ) );
ck( 'the newest rows are still in the base', array_column( array_merge( $GLOBALS['base']['tblSTUDENTS'], $GLOBALS['base']['tblREPORTS'], $GLOBALS['base']['tblFEEDBACK'] ), 'id' ), array( rid( 'stunew' ), rid( 'othold' ), rid( 'othnew' ), rid( 'repnew' ), rid( 'fbnew' ) ) );
ck( 'and the student leaves the stored list at once', array_keys( WPCPM_Duplicates_Scan::report()['groups'] ), array( $other ) );

/* ---- the re-check ----------------------------------------------------------- */

echo "\n=== The stored report is the menu, not the authority ===\n";

fresh();
$GLOBALS['usermeta'] = array( array( 'user_id' => 9, 'meta_key' => 'wpcpm_student_record_id', 'meta_value' => rid( 'repold' ) ) );
$outcome             = WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 );
ck( 'a row the site began pointing at since the scan is refused', array_column( $outcome['refused'], 'code', 'id' ), array( rid( 'repold' ) => 'site' ) );
ck( 'the other two go', array( $outcome['deleted'], asked( 'delete' ) ), array( array( 'students' => 1, 'reports' => 0, 'feedback' => 1 ), array( array( 'tblFEEDBACK', array( rid( 'fbold' ) ) ), array( 'tblSTUDENTS', array( rid( 'stuold' ) ) ) ) ) );
ck( 'and nothing is kept for the refused row', copies(), array( rid( 'fbold' ) => 'deleted', rid( 'stuold' ) => 'deleted' ) );

fresh();
$GLOBALS['base']['tblFEEDBACK'][0]['fields']['F1 - How easy was it to get started?'] = 4;
$outcome = WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 );
ck( 'a row that gained answers since the scan is refused as changed', array_column( $outcome['refused'], 'code', 'id' ), array( rid( 'fbold' ) => 'changed' ) );

fresh();
$outcome = WPCPM_Duplicate_Delete::run( array(), array( 'students:' . rid( 'othold' ), 'students:' . rid( 'othnew' ) ), 7 );
ck( 'ticking every row a table has refuses them all as the last row', array( $outcome['status'], array_column( $outcome['refused'], 'code', 'id' ) ), array( 'nothing', array( rid( 'othold' ) => 'last-row', rid( 'othnew' ) => 'last-row' ) ) );
ck( 'with nothing deleted and nothing kept', array( asked( 'delete' ), copies() ), array( array(), array() ) );

/* ---- failing closed ----------------------------------------------------------- */

echo "\n=== Failing closed ===\n";

fresh();
$GLOBALS['read_fails'] = true;
$outcome               = WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 );
ck( 'a re-read Airtable refuses deletes nothing and keeps nothing', array( $outcome['status'], asked( 'delete' ), copies() ), array( 'read-failed', array(), array() ) );

fresh();
$GLOBALS['refuse'] = array( 'tblREPORTS' => true );
$outcome           = WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 );
ck( 'a batch Airtable refuses stops the run and says so', array( $outcome['status'], $outcome['deleted'], $outcome['detail'] ), array( 'stopped', array( 'students' => 0, 'reports' => 0, 'feedback' => 1 ), 'Airtable request failed (HTTP 422): INVALID_REQUEST_UNKNOWN' ) );
ck( 'Students was never sent', asked( 'delete' ), array( array( 'tblFEEDBACK', array( rid( 'fbold' ) ) ), array( 'tblREPORTS', array( rid( 'repold' ) ) ) ) );
ck( 'so its copy is removed, the refused row\'s copy waits for the daily job, and the deleted one is logged', copies(), array( rid( 'fbold' ) => 'deleted', rid( 'repold' ) => 'pending' ) );
ck( 'and the stored list drops only the row that went', WPCPM_Duplicates_Scan::report()['groups'][ $ready ]['counts'], array( 'students' => 2, 'reports' => 2, 'feedback' => 1 ) );

fresh();
$GLOBALS['insert_fails_at'] = 2;
$outcome                    = WPCPM_Duplicate_Delete::run( array( $ready ), array(), 7 );
ck( 'a copy that cannot be kept stops the delete before anything is sent', array( $outcome['status'], '' !== $outcome['detail'] ), array( 'copy-failed', true ) );
ck( 'nothing reaches Airtable', asked( 'delete' ), array() );
ck( 'and nothing is left behind: the copy already made for the first row is removed', copies(), array() );

$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-delete.php' );
ck( 'no dash but the plain hyphen in the class', 1 === preg_match( '/\x{2013}|\x{2014}/u', $source ), false );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
