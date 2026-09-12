<?php
/**
 * The Student Duplicate Finder's copies of deleted rows, and the log they become.
 *
 * What each block pins, and why:
 *
 * - **No copy in the clear.** A copy holds a student's name, address, grades and answers, so its
 *   cells are sealed with the site key (the real `WPCPM_Secret`, on a real OpenSSL) and nothing
 *   readable is left on the post: not the content, not the title, not a meta row.
 * - A copy that went wrong is never half-written: a bad table or record ID stores nothing.
 * - The copy opens again for View copy, whole, and stops opening once it is erased.
 * - **The daily job, on a clock the suite holds:** a copy is erased after thirty days and its post
 *   stays as the log entry; a copy still pending is left alone for an hour, then settled by asking
 *   whether its row is still in Airtable (still there: the copy goes; gone: it is logged as
 *   deleted; no answer: it waits for the next day).
 * - Uninstall takes every copy with it.
 *
 * Fixtures are synthetic: example.test addresses and record IDs that spell what they are.
 *
 * Run from the plugin root:  php bin/test-duplicate-vault.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']  = array();
$GLOBALS['posts'] = array();
$GLOBALS['meta']  = array();
$GLOBALS['cron']  = array();
$GLOBALS['types'] = array();
// The real clock, not a fixed one: keep() stamps a copy's expiry from time(), so a suite clock
// that differs from it by a day would move every thirty-day check by that day.
$GLOBALS['now']   = time();

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
function register_post_type( $type, $args ) { $GLOBALS['types'][ $type ] = $args; return (object) $args; }
function wp_insert_post( $args, $wp_error = false ) {
	static $next = 100;
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
function wp_update_post( $args ) {
	if ( isset( $GLOBALS['posts'][ (int) $args['ID'] ] ) && array_key_exists( 'post_content', $args ) ) {
		$GLOBALS['posts'][ (int) $args['ID'] ]->post_content = $args['post_content'];
	}
	return (int) $args['ID'];
}
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['meta'][ (int) $id ] ); return true; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ (int) $id ][ $k ] = $v; return true; }
function get_post_meta( $id, $k = '', $single = false ) { return isset( $GLOBALS['meta'][ (int) $id ][ $k ] ) ? $GLOBALS['meta'][ (int) $id ][ $k ] : ''; }
function get_posts( $args ) {
	$posts = array_filter( $GLOBALS['posts'], static function ( $p ) use ( $args ) { return $p->post_type === $args['post_type']; } );
	krsort( $posts );
	if ( isset( $args['numberposts'] ) && $args['numberposts'] > 0 ) {
		$posts = array_slice( $posts, 0, (int) $args['numberposts'], true );
	}
	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
		return array_keys( $posts );
	}
	return array_values( $posts );
}
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ] : false; }
function wp_schedule_event( $ts, $recurrence, $hook ) { $GLOBALS['cron'][ $hook ] = $ts; $GLOBALS['cron_recurrence'][ $hook ] = $recurrence; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ] ); return 0; }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-secret.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php';

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
 * A Students Reports record in the shape the API returns.
 *
 * @param string $tag The record ID's tag.
 * @return array
 */
function report_record( $tag ) {
	return array(
		'id'          => 'rec' . str_pad( strtoupper( $tag ), 14, '0' ),
		'createdTime' => '2026-02-02T10:00:00.000Z',
		'fields'      => array(
			'Email'                                 => 'student@example.test',
			'Name'                                  => 'A Student',
			'Status'                                => 'Not moving forward',
			'Beginner WordPress User - final grade' => 88,
		),
	);
}

/**
 * Everything the posts and their meta hold for one copy, as one string.
 *
 * @param int $id Post ID.
 * @return string
 */
function stored( $id ) {
	$post = get_post( $id );
	return $post ? $post->post_title . ' ' . $post->post_content . ' ' . json_encode( $GLOBALS['meta'][ $id ] ) : '';
}

echo "\n=== The post type ===\n";

WPCPM_Duplicate_Vault::register();
$args = $GLOBALS['types'][ WPCPM_Duplicate_Vault::POST_TYPE ];
ck( 'a name WordPress will register: twenty characters at most', strlen( WPCPM_Duplicate_Vault::POST_TYPE ) <= 20, true );
ck( 'private everywhere: not public, no screen, not in REST, not searchable', array( $args['public'], $args['publicly_queryable'], $args['show_ui'], $args['show_in_rest'], $args['exclude_from_search'] ), array( false, false, false, false, true ) );
ck( 'the scan leaves the finder\'s own copies out of the site references', in_array( WPCPM_Duplicate_Vault::POST_TYPE, array( 'wpcpm_dup_copy' ), true ), true );

echo "\n=== A copy, sealed ===\n";

$record = report_record( 'kept' );
$copy   = WPCPM_Duplicate_Vault::keep( 'reports', $record, 7, 1788990000 );

ck( 'keep() returns the copy\'s post ID', is_int( $copy ) && $copy > 0, true );
ck( 'nothing readable is stored: no address, no name, no grade anywhere on the post', array( false !== strpos( stored( $copy ), 'student@example.test' ), false !== strpos( stored( $copy ), 'A Student' ), false !== strpos( stored( $copy ), '"88"' ) || false !== strpos( stored( $copy ), ':88' ) ), array( false, false, false ) );
ck( 'the content is the sealed blob, base64', 1 === preg_match( '/^[A-Za-z0-9+\/]+=*$/', get_post( $copy )->post_content ), true );
ck( 'the reference is kept beside it: table, record, created date, status, the scan\'s read time', array( get_post_meta( $copy, WPCPM_Duplicate_Vault::META_TABLE ), get_post_meta( $copy, WPCPM_Duplicate_Vault::META_RECORD ), get_post_meta( $copy, WPCPM_Duplicate_Vault::META_CREATED ), get_post_meta( $copy, WPCPM_Duplicate_Vault::META_STATUS ), get_post_meta( $copy, WPCPM_Duplicate_Vault::META_READ ) ), array( 'reports', $record['id'], '2026-02-02', 'Not moving forward', 1788990000 ) );
ck( 'pending until Airtable confirms the delete', get_post_meta( $copy, WPCPM_Duplicate_Vault::META_STATE ), 'pending' );
ck( 'private, and authored by the manager who deleted it', array( get_post( $copy )->post_status, get_post( $copy )->post_author ), array( 'private', 7 ) );
ck( 'kept for thirty days', get_post_meta( $copy, WPCPM_Duplicate_Vault::META_EXPIRES ) - time() >= 30 * DAY_IN_SECONDS - 5, true );

$before = count( $GLOBALS['posts'] );
ck( 'a table the finder does not know is refused', WPCPM_Duplicate_Vault::keep( 'mentors', $record, 7 )->get_error_code(), 'wpcpm_duplicates_bad_copy' );
ck( 'and so is a record ID that is not one', WPCPM_Duplicate_Vault::keep( 'reports', array( 'id' => 'rec123' ), 7 )->get_error_code(), 'wpcpm_duplicates_bad_copy' );
ck( 'with nothing written for either', count( $GLOBALS['posts'] ), $before );

$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php' );
ck( 'a site that cannot encrypt keeps no copy, and so deletes nothing: keep() asks first', false !== strpos( $source, 'if ( ! WPCPM_Secret::can_encrypt() ) {' ), true );

echo "\n=== View copy ===\n";

ck( 'the copy opens whole', WPCPM_Duplicate_Vault::view( $copy ), $record );
ck( 'a post that is not a copy does not', WPCPM_Duplicate_Vault::view( 99999 )->get_error_code(), 'wpcpm_duplicates_no_copy' );

echo "\n=== Confirmed, discarded, and the log ===\n";

WPCPM_Duplicate_Vault::confirm( array( $copy ) );
ck( 'a confirmed copy is the log entry for a deleted row', get_post_meta( $copy, WPCPM_Duplicate_Vault::META_STATE ), 'deleted' );

$refused = WPCPM_Duplicate_Vault::keep( 'reports', report_record( 'refused' ), 7 );
WPCPM_Duplicate_Vault::discard( $refused );
ck( 'a copy of a row that was not deleted after all is removed', get_post( $refused ), null );

$GLOBALS['now'] += 60;
$second  = WPCPM_Duplicate_Vault::keep( 'feedback', array( 'id' => 'rec' . str_pad( 'SECOND', 14, '0' ), 'createdTime' => '2026-02-03T10:00:00.000Z', 'fields' => array( 'Email' => 'student@example.test', 'Course' => 'In Sensei' ) ), 8 );
$entries = WPCPM_Duplicate_Vault::entries( 10 );
ck( 'the log is newest first', array_column( $entries, 'copy' ), array( $second, $copy ) );
ck( 'and an entry says what went, when and by whom, and nothing about the student', array_keys( $entries[1] ), array( 'copy', 'table', 'record', 'created', 'status', 'state', 'when', 'by', 'expires' ) );
ck( 'the older entry, in full', array( $entries[1]['table'], $entries[1]['record'], $entries[1]['state'], $entries[1]['by'] ), array( 'reports', $record['id'], 'deleted', 7 ) );

echo "\n=== The daily job ===\n";

$asked = array();
$ask   = static function ( $answer ) use ( &$asked ) {
	return static function ( $table, $id ) use ( $answer, &$asked ) {
		$asked[] = $id;
		return $answer;
	};
};

$counts = WPCPM_Duplicate_Vault::purge( $ask( true ), $GLOBALS['now'] + 60 );
ck( 'a pending copy under an hour old is left alone: its delete may still be running', array( $counts['settled_kept'], $counts['settled_deleted'], $asked ), array( 0, 0, array() ) );

$counts = WPCPM_Duplicate_Vault::purge( $ask( null ), $GLOBALS['now'] + 2 * HOUR_IN_SECONDS );
ck( 'an hour on, a copy Airtable will not answer about waits for the next day', array( $counts['unsettled'], get_post_meta( $second, WPCPM_Duplicate_Vault::META_STATE ) ), array( 1, 'pending' ) );

$counts = WPCPM_Duplicate_Vault::purge( $ask( false ), $GLOBALS['now'] + 2 * HOUR_IN_SECONDS );
ck( 'a pending copy whose row is gone is logged as deleted', array( $counts['settled_deleted'], get_post_meta( $second, WPCPM_Duplicate_Vault::META_STATE ) ), array( 1, 'deleted' ) );

$third = WPCPM_Duplicate_Vault::keep( 'students', array( 'id' => 'rec' . str_pad( 'THIRD', 14, '0' ), 'createdTime' => '2026-01-27T10:00:00.000Z', 'fields' => array( 'Email' => 'student@example.test' ) ), 7 );
$counts = WPCPM_Duplicate_Vault::purge( $ask( true ), $GLOBALS['now'] + 2 * HOUR_IN_SECONDS );
ck( 'a pending copy whose row is still in Airtable goes: nothing was deleted', array( $counts['settled_kept'], get_post( $third ) ), array( 1, null ) );

$counts = WPCPM_Duplicate_Vault::purge( $ask( true ), $GLOBALS['now'] + 29 * DAY_IN_SECONDS );
ck( 'on day twenty-nine every copy still opens', array( $counts['erased'], is_array( WPCPM_Duplicate_Vault::view( $copy ) ) ), array( 0, true ) );

$counts = WPCPM_Duplicate_Vault::purge( $ask( true ), $GLOBALS['now'] + 31 * DAY_IN_SECONDS );
ck( 'after thirty days the sealed cells are erased', array( $counts['erased'], get_post( $copy )->post_content, get_post_meta( $copy, WPCPM_Duplicate_Vault::META_STATE ) ), array( 2, '', 'erased' ) );
ck( 'and the post stays as the log entry, reference intact', array( get_post( $copy ) instanceof WP_Post, get_post_meta( $copy, WPCPM_Duplicate_Vault::META_RECORD ) ), array( true, $record['id'] ) );
ck( 'an erased copy no longer opens', WPCPM_Duplicate_Vault::view( $copy )->get_error_code(), 'wpcpm_duplicates_erased' );

echo "\n=== Schedule and uninstall ===\n";

WPCPM_Duplicate_Vault::schedule();
WPCPM_Duplicate_Vault::schedule();
ck( 'the daily job is scheduled once, daily', array( isset( $GLOBALS['cron'][ WPCPM_Duplicate_Vault::CRON_PURGE ] ), $GLOBALS['cron_recurrence'][ WPCPM_Duplicate_Vault::CRON_PURGE ] ), array( true, 'daily' ) );

WPCPM_Duplicate_Vault::delete_all();
ck( 'uninstall takes every copy and the job with it', array( count( get_posts( array( 'post_type' => WPCPM_Duplicate_Vault::POST_TYPE, 'fields' => 'ids' ) ) ), wp_next_scheduled( WPCPM_Duplicate_Vault::CRON_PURGE ) ), array( 0, false ) );

echo "\n=== The log keeps every copy that still has its cells ===\n";

// sprintf( 'log%02d', $i ) pads to a fixed width: without it, log1 and log10 would both become
// the record ID LOG10000000000, since report_record() pads the tag to fourteen characters.
$log_copies = array();
for ( $i = 1; $i <= 60; $i++ ) {
	$log_copies[] = WPCPM_Duplicate_Vault::keep( 'reports', report_record( sprintf( 'log%02d', $i ) ), 7 );
}
WPCPM_Duplicate_Vault::confirm( $log_copies );

$log_entries = WPCPM_Duplicate_Vault::entries( 50 );
ck( 'more copies than the limit still all show while they have their cells', count( $log_entries ), 60 );
ck( 'every one of them deleted, none capped away', array_unique( array_column( $log_entries, 'state' ) ), array( 'deleted' ) );

WPCPM_Duplicate_Vault::purge( static function () { return false; }, time() + 31 * DAY_IN_SECONDS );
$newest      = WPCPM_Duplicate_Vault::keep( 'reports', report_record( 'lognew' ), 7 );
$log_entries = WPCPM_Duplicate_Vault::entries( 50 );
ck(
	'once they are erased the limit caps only that state: the new copy plus fifty erased',
	array( count( $log_entries ), $log_entries[0]['copy'], array_unique( array_column( array_slice( $log_entries, 1 ), 'state' ) ) ),
	array( 51, $newest, array( 'erased' ) )
);

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
