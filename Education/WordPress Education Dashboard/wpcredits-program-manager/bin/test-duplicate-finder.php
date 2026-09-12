<?php
/**
 * The Student Duplicate Finder's screen and handlers.
 *
 * `WPCPM_Duplicate_Finder` and `WPCPM_Duplicate_Finder_Screen` against a report the real scan
 * wrote from a base this suite holds, with the real rules, vault, seal, request readers and
 * flash. What each block pins, and why:
 *
 * - **The capability first, then the nonce, on every door**: the list, the confirmation, View
 *   copy, Scan now, Cancel, the progress tick and Delete. Somebody without the capability meets
 *   wp_die() or a 403, and no nonce is even looked at (spec 3.9 and 7.3).
 * - **The Delete button's nonce is tied to exactly the rows the confirmation listed** (spec
 *   7.2): the same rows in any order are the same token, one row more is another, a posted
 *   value that is neither a student key nor a table and record pair never reaches the hash, and a scan that rewrites the report between the two presses changes the token.
 * - The list ticks nothing by itself, gives a Ready student one checkbox and a student who needs
 *   a decision one per row that may go, shows a row the site points at as a disabled checkbox
 *   that points at its reason, and says why nothing can be ticked while deleting is switched off.
 *   While a scan runs, the list stays and Review selection waits.
 * - The confirmation lists what a press of Delete removes and what was left out, and Back to the
 *   list returns with the same ticks.
 * - The notice after a delete says what went, what was refused and why, and when to try again.
 * - The log holds no name and no address, and offers View copy behind a nonce keyed to the copy;
 *   a copy opens whole for a manager, and not at all once it is erased.
 *
 * Fixtures are synthetic: example.test addresses and record IDs that spell what they are.
 *
 * Run from the plugin root:  php bin/test-duplicate-finder.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );

class RedirectSignal extends Exception {}
class DieSignal extends Exception {}
class JsonSignal extends Exception {
	public $payload;
	public function __construct( array $payload ) { parent::__construct( 'json' ); $this->payload = $payload; }
}

$GLOBALS['opts']      = array();
$GLOBALS['umeta']     = array();
$GLOBALS['posts']     = array();
$GLOBALS['meta']      = array();
$GLOBALS['cron']      = array();
$GLOBALS['hooks']     = array();
$GLOBALS['enqueued']  = array();
$GLOBALS['usermeta']  = array();
$GLOBALS['postmeta']  = array();
$GLOBALS['nonces']    = array();
$GLOBALS['caps']      = true;
$GLOBALS['uid']       = 1;
$GLOBALS['switch']    = false;
$GLOBALS['connected'] = true;
$GLOBALS['now']       = time();

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
class WP_User {
	public $ID = 0, $display_name = '';
	public function __construct( $id, $name ) { $this->ID = $id; $this->display_name = $name; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function wp_kses( $s, $allowed ) { return $s; }
function number_format_i18n( $n ) { return (string) $n; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : (int) $t ); }
function human_time_diff( $a, $b = null ) { return '2 hours'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . rawurlencode( (string) $value ); }
function wp_create_nonce( $action ) { return 'n-' . $action; }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=' . wp_create_nonce( $action ); }
function wp_nonce_field( $action ) { printf( '<input type="hidden" name="_wpnonce" value="%s" />', esc_attr( wp_create_nonce( $action ) ) ); }
function check_admin_referer( $action = -1, $arg = '_wpnonce' ) { $GLOBALS['nonces'][] = $action; return 1; }
function check_ajax_referer( $action = -1, $arg = false ) { $GLOBALS['nonces'][] = $action; return 1; }
function wp_die( $m = '', $c = 0 ) { throw new DieSignal( (string) $m ); }
function wp_safe_redirect( $u ) { throw new RedirectSignal( $u ); }
function wp_send_json_error( $d = null, $c = null ) { throw new JsonSignal( array( 'success' => false, 'data' => $d, 'code' => $c ) ); }
function wp_send_json_success( $d = null ) { throw new JsonSignal( array( 'success' => true, 'data' => $d ) ); }
function submit_button( $text, $type = '', $name = '', $wrap = true ) { printf( '<button type="submit" class="button">%s</button>', esc_html( $text ) ); }
function get_userdata( $id ) { return 7 === (int) $id ? new WP_User( 7, 'Pat Manager' ) : false; }
function get_current_user_id() { return $GLOBALS['uid']; }
function get_user_meta( $id, $k, $single = false ) { return isset( $GLOBALS['umeta'][ (int) $id ][ $k ] ) ? $GLOBALS['umeta'][ (int) $id ][ $k ] : ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $callback; return true; }
function wp_enqueue_style( $handle, $src = '', $deps = array() ) { $GLOBALS['enqueued'][] = array( 'style', $handle, $deps ); }
function wp_enqueue_script( $handle, $src = '', $deps = array() ) { $GLOBALS['enqueued'][] = array( 'script', $handle, $deps ); }
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
function wp_get_scheduled_event( $hook ) { return false; }
function wp_schedule_event( $ts, $recurrence, $hook ) { $GLOBALS['cron'][ $hook ] = $ts; return true; }
function wp_schedule_single_event( $ts, $hook ) { $GLOBALS['cron'][ $hook ] = $ts; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ] ); return 0; }
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
	return ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? array_keys( $posts ) : array_values( $posts );
}
require_once __DIR__ . '/stubs/caps.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}
/** Airtable, as the suite holds it in $GLOBALS['base']. The scan reads whole tables. */
class WPCPM_Airtable {
	public function __construct( $settings = null ) {}
	public function fetch_page( $table, array $args = array() ) {
		return array( 'records' => isset( $GLOBALS['base'][ $table ] ) ? $GLOBALS['base'][ $table ] : array(), 'offset' => null );
	}
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}
class WPCPM_Settings {
	public static function get() { return array( 'base_id' => 'appTEST', 'students_table' => 'tblSTUDENTS', 'reports_table' => 'tblREPORTS', 'feedback_table' => 'tblFEEDBACK', 'duplicate_delete_enabled' => $GLOBALS['switch'] ); }
	public static function is_connected() { return $GLOBALS['connected']; }
}
class WPCPM_Students_Sync {
	const EVERY_THREE_HOURS = 'wpcpm_three_hours';
	public static function register_interval() {}
	public static function cycle_start() { return 1788998400; }
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
class WPCPM_Mentors {
	public static function format_duration( $seconds ) { return gmdate( 'H:i:s', (int) $seconds ); }
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
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-rules.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicates-scan.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-delete.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder.php';

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
 * Knock on one door: run a handler or the screen, and say how it ended.
 *
 * @param callable $fn The handler.
 * @return mixed 'returned', 'die', 'redirect <url>', or the JSON a tick answered with.
 */
function door( callable $fn ) {
	$GLOBALS['nonces'] = array();
	ob_start();

	try {
		$fn();
		$out = 'returned';
	} catch ( RedirectSignal $e ) {
		$out = 'redirect ' . $e->getMessage();
	} catch ( DieSignal $e ) {
		$out = 'die';
	} catch ( JsonSignal $e ) {
		$out = $e->payload;
	}

	$GLOBALS['html'] = (string) ob_get_clean();

	return $out;
}

/**
 * The screen, as the current user sees it with this request.
 *
 * @param array $get  Query arguments.
 * @param array $post Posted fields.
 * @return string The markup.
 */
function page( array $get = array(), array $post = array() ) {
	global $finder;

	$_GET  = $get;
	$_POST = $post;
	door( array( $finder, 'render_admin_page' ) );
	$_GET  = array();
	$_POST = array();

	return $GLOBALS['html'];
}

/**
 * Whether the markup holds this text.
 *
 * @param string $html   Markup.
 * @param string $needle Text.
 * @return bool
 */
function has( $html, $needle ) {
	return false !== strpos( $html, $needle );
}

/*
 * The base: Ada is Ready (an older row in each table with nothing attached); Bo needs a decision
 * (the newest Students row did not move forward while the older one is live), and his name is
 * markup, which the screen must print as text; Cy needs a decision because the site's account
 * points at his older report row.
 */
$GLOBALS['base'] = array(
	'tblSTUDENTS' => array(
		record( 'stuold', '2026-01-27T10:00:00.000Z', array( 'Email' => 'ada@example.test', 'Full Name' => 'Ada Example', 'Status' => 'Not moving forward' ) ),
		record( 'stunew', '2026-09-09T10:00:00.000Z', array( 'Email' => 'ada@example.test', 'Full Name' => 'Ada Example', 'Status' => 'In Sensei' ) ),
		record( 'othold', '2026-02-01T10:00:00.000Z', array( 'Email' => 'bo@example.test', 'Full Name' => 'Bo <b>Example</b>', 'Status' => 'In Sensei' ) ),
		record( 'othnew', '2026-08-01T10:00:00.000Z', array( 'Email' => 'bo@example.test', 'Full Name' => 'Bo <b>Example</b>', 'Status' => 'Not moving forward' ) ),
		record( 'lkstu', '2026-03-01T10:00:00.000Z', array( 'Email' => 'cy@example.test', 'Full Name' => 'Cy Example', 'Status' => 'In Sensei' ) ),
	),
	'tblREPORTS'  => array(
		record( 'repold', '2026-02-02T10:00:00.000Z', array( 'Email' => 'ada@example.test', 'Name' => 'Ada Example', 'Status' => 'Not moving forward' ) ),
		record( 'repnew', '2026-09-09T10:00:05.000Z', array( 'Email' => 'ada@example.test', 'Name' => 'Ada Example', 'Status' => 'In Sensei' ) ),
		record( 'lkold', '2026-03-02T10:00:00.000Z', array( 'Email' => 'cy@example.test', 'Name' => 'Cy Example', 'Status' => 'Not moving forward' ) ),
		record( 'lknew', '2026-06-02T10:00:00.000Z', array( 'Email' => 'cy@example.test', 'Name' => 'Cy Example', 'Status' => 'In Sensei' ) ),
	),
	'tblFEEDBACK' => array(
		record( 'fbold', '2026-02-02T10:00:00.000Z', array( 'Email' => 'ada@example.test', 'Name' => 'Ada Example' ) ),
		record( 'fbnew', '2026-09-09T10:00:05.000Z', array( 'Email' => 'ada@example.test', 'Name' => 'Ada Example', 'Course' => 'In Sensei' ) ),
	),
);
$GLOBALS['usermeta'] = array( array( 'user_id' => 9, 'meta_key' => 'wpcpm_student_record_id', 'meta_value' => rid( 'lkold' ) ) );

$finder = new WPCPM_Duplicate_Finder();
$ada    = WPCPM_Duplicate_Rules::key( 'ada@example.test' );
$url    = 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder';

/* ---- the tool ------------------------------------------------------------ */

echo "\n=== A tool with a page of its own ===\n";

ck( 'the Student Duplicate Finder, at its own page slug', array( $finder->id(), $finder->label(), $finder->page_slug() ), array( 'duplicate-finder', 'Student Duplicate Finder', 'wpcpm-tool-duplicate-finder' ) );
ck( 'the Modules card says when nothing has been scanned yet', $finder->status_line(), 'No scan has run yet.' );

$finder->boot();
ck( 'boot hooks Scan now, Cancel, Delete, the progress tick, the scan, the copies and the assets', array_values( array_diff( array( 'admin_post_wpcpm_duplicates_scan_now', 'admin_post_wpcpm_duplicates_cancel', 'admin_post_wpcpm_duplicates_delete', 'wp_ajax_wpcpm_duplicates_progress', 'wpcpm_duplicates_scan', 'wpcpm_duplicates_tick', 'wpcpm_duplicates_purge', 'init', 'admin_enqueue_scripts' ), array_keys( $GLOBALS['hooks'] ) ) ), array() );
ck( 'and no admin-post door for the confirmation or View copy, which are pages and change nothing', array( isset( $GLOBALS['hooks']['admin_post_wpcpm_duplicates_review'] ), isset( $GLOBALS['hooks']['admin_post_wpcpm_duplicates_view'] ) ), array( false, false ) );
ck( 'the scan is on the clock from the first boot, 150 minutes into the cycle', isset( $GLOBALS['cron']['wpcpm_duplicates_scan'] ) ? $GLOBALS['cron']['wpcpm_duplicates_scan'] : 0, 1788998400 + 150 * MINUTE_IN_SECONDS );

$finder->enqueue_assets( 'wpcredits-program_page_wpcpm-tool-duplicate-finder' );
ck( 'its stylesheet builds on the plugin\'s admin sheet, and its script loads, on its own screen', $GLOBALS['enqueued'], array( array( 'style', 'wpcpm-duplicates', array( 'wpcpm-admin' ) ), array( 'script', 'wpcpm-duplicates', array() ) ) );
$GLOBALS['enqueued'] = array();
$finder->enqueue_assets( 'wpcredits-program_page_wpcpm-settings' );
ck( 'and on no other', $GLOBALS['enqueued'], array() );

/* ---- the doors -------------------------------------------------------------- */

echo "\n=== The capability first, then the nonce, on every door ===\n";

$GLOBALS['caps'] = false;
$shut            = array();
foreach ( array( 'handle_scan', 'handle_cancel', 'handle_delete', 'render_admin_page' ) as $method ) {
	$_POST           = array( 'wpcpm_review' => '1', 'wpcpm_students' => array( $ada ) );
	$shut[ $method ] = array( door( array( $finder, $method ) ), $GLOBALS['nonces'] );
}
$_POST              = array();
$_GET               = array( 'wpcpm_copy' => '101' );
$shut['View copy'] = array( door( array( $finder, 'render_admin_page' ) ), $GLOBALS['nonces'] );
$_GET               = array();
ck( 'without the capability every door is wp_die(), and no nonce is looked at', $shut, array_fill_keys( array( 'handle_scan', 'handle_cancel', 'handle_delete', 'render_admin_page', 'View copy' ), array( 'die', array() ) ) );

$tick = door( array( $finder, 'handle_tick' ) );
ck( 'and the progress tick answers 403 before its nonce', array( $tick['success'], $tick['code'], $GLOBALS['nonces'] ), array( false, 403, array() ) );
$GLOBALS['caps'] = true;

echo "\n=== Scan now, the progress tick and Cancel ===\n";

$GLOBALS['connected'] = false;
ck( 'Scan now with Airtable not connected: back to the screen, and no scan', array( door( array( $finder, 'handle_scan' ) ), $GLOBALS['nonces'], WPCPM_Duplicates_Scan::is_running() ), array( 'redirect ' . $url, array( 'wpcpm_duplicates_scan_now' ), false ) );
$GLOBALS['connected'] = true;

ck( 'Scan now: under its own nonce, a scan starts and the screen comes back', array( door( array( $finder, 'handle_scan' ) ), $GLOBALS['nonces'], WPCPM_Duplicates_Scan::is_running() ), array( 'redirect ' . $url, array( 'wpcpm_duplicates_scan_now' ), true ) );

$state            = get_option( WPCPM_Duplicates_Scan::OPT_STATE );
$state['started'] = 1;
update_option( WPCPM_Duplicates_Scan::OPT_STATE, $state );
$GLOBALS['uid']   = 24;
ck( 'Scan now while a scan runs: back to the screen, and the running scan left as it was', array( door( array( $finder, 'handle_scan' ) ), get_option( WPCPM_Duplicates_Scan::OPT_STATE )['started'] ), array( 'redirect ' . $url, 1 ) );
ck( 'with a notice that says so', has( page(), 'A scan is already running; its progress is shown below.' ), true );
$GLOBALS['uid']   = 1;

ck( 'Cancel: under its own nonce, the scan stops', array( door( array( $finder, 'handle_cancel' ) ), $GLOBALS['nonces'], WPCPM_Duplicates_Scan::is_running() ), array( 'redirect ' . $url, array( 'wpcpm_duplicates_cancel' ), false ) );

door( array( $finder, 'handle_scan' ) );
$tick = door( array( $finder, 'handle_tick' ) );
ck( 'the tick runs a slice under its own nonce and answers with progress; this base takes one', array( $tick['success'], $GLOBALS['nonces'], $tick['data']['running'] ), array( true, array( 'wpcpm_duplicates_progress' ), false ) );
ck( 'and the list is written: three duplicated students, one of them Ready', array( WPCPM_Duplicates_Scan::report()['counts']['addresses'], WPCPM_Duplicates_Scan::report()['counts']['ready'] ), array( 3, 1 ) );
ck( 'which the Modules card now reads', 0 === strpos( $finder->status_line(), '3 duplicated students, read ' ), true );

/* ---- the list ------------------------------------------------------------------- */

echo "\n=== The list ===\n";

$GLOBALS['switch'] = true;
$html              = page();
$ada_box           = '<input type="checkbox" name="wpcpm_students[]" value="' . $ada . '" data-rows="students reports feedback" data-ready />';

ck( 'nothing is ticked by itself', has( $html, ' checked' ), false );
ck( 'a Ready student has one checkbox, for their older row in each table, named for what it does', array( has( $html, $ada_box ), has( $html, 'Delete the 3 older rows of Ada Example' ) ), array( true, true ) );
ck( 'and no checkbox on a row of their own', has( $html, 'value="students:' . rid( 'stuold' ) . '"' ), false );
ck( 'a student who needs a decision has a checkbox on each row that may go, named for screen readers', array( has( $html, 'name="wpcpm_rows[]" value="students:' . rid( 'othold' ) . '"' ), has( $html, 'name="wpcpm_rows[]" value="students:' . rid( 'othnew' ) . '"' ), has( $html, 'aria-label="Delete the Students row ' . rid( 'othold' ) . ' of Bo &lt;b&gt;Example&lt;/b&gt;"' ) ), array( true, true, true ) );
ck( 'a row the site points at: a disabled checkbox that points at its reason', array( has( $html, '<input type="checkbox" disabled aria-describedby="wpcpm-dup-why-' . rid( 'lkold' ) . '"' ), has( $html, '<td id="wpcpm-dup-why-' . rid( 'lkold' ) . '">The site points at it: a site account. Delete the other one instead.</td>' ), has( $html, 'aria-label="The Students Reports row ' . rid( 'lkold' ) . ' of Cy Example cannot be deleted"' ) ), array( true, true, true ) );
ck( 'and the On the site column says what', has( $html, '<td>a site account</td>' ), true );
ck( 'every row links to its record in Airtable', has( $html, 'href="https://airtable.com/appTEST/tblREPORTS/' . rid( 'repold' ) . '"' ), true );
ck( 'the tiles: students, ready, needing a decision, and rows per table', array( has( $html, '<li>3 duplicated students</li>' ), has( $html, '<li>1 ready: every older row is a clean delete candidate</li>' ), has( $html, '<li>2 need a decision</li>' ), has( $html, '<li>Feedback: 1 proposed for deletion, 0 held</li>' ) ), array( true, true, true, true ) );
ck( 'a name is printed as text, never as markup', array( has( $html, 'Bo &lt;b&gt;Example&lt;/b&gt;' ), has( $html, '<b>Example</b>' ) ), array( true, false ) );
ck( 'Review selection posts the ticks back to this screen under the review nonce', array( has( $html, '<form method="post" action="' . $url . '" class="wpcpm-duplicates__form" data-wpcpm-duplicates>' ), has( $html, 'value="n-wpcpm_duplicates_review"' ), has( $html, '<input type="hidden" name="wpcpm_review" value="1" />' ) ), array( true, true, true ) );
ck( 'Select all ready and Clear ship hidden, for the script to show', array( has( $html, 'data-wpcpm-select-ready hidden' ), has( $html, 'data-wpcpm-clear hidden' ) ), array( true, true ) );
ck( 'and they stay hidden without the script, over core\'s display: inline-block for every .button', 1 === preg_match( '/\.wpcpm-duplicates__bar \.button\[hidden\] \{\s*display: none;\s*\}/', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/duplicate-finder.css' ) ), true );

$dup_js = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/js/duplicate-finder.js' );
ck( 'the script disables the red Delete button on submit, and leaves a running scan\'s list alone without Select all ready', array( has( $dup_js, "querySelectorAll( '.wpcpm-duplicates__delete' )" ), 1 === preg_match( '/if \( ! ready \) \{\s*return;\s*\}/', $dup_js ) ), array( true, true ) );

ck( 'the log starts empty', has( $html, 'Nothing has been deleted yet.' ), true );

$GLOBALS['switch'] = false;
$html              = page();
ck( 'deleting switched off: no checkbox at all, the list says why, and Review selection is disabled', array( has( $html, 'name="wpcpm_students[]"' ), has( $html, 'name="wpcpm_rows[]"' ), has( $html, 'Deleting is switched off, so this list is read-only.' ), has( $html, ' disabled>Review selection</button>' ) ), array( false, false, true, true ) );
$GLOBALS['switch'] = true;

WPCPM_Duplicates_Scan::start();
$html = page();
WPCPM_Duplicates_Scan::cancel();
ck( 'while a scan runs: its progress bar, the list still there, and Review selection waiting', array( has( $html, 'data-wpcpm-progress data-action="wpcpm_duplicates_progress" data-nonce="n-wpcpm_duplicates_progress"' ), has( $html, $ada_box ), has( $html, ' disabled>Review selection</button>' ) ), array( true, true, true ) );

/* ---- the confirmation --------------------------------------------------------------- */

echo "\n=== The confirmation, and Back to the list ===\n";

$chosen = array( 'wpcpm_review' => '1', 'wpcpm_students' => array( $ada ), 'wpcpm_rows' => array( 'students:' . rid( 'othold' ), 'students:' . rid( 'stunew' ) ) );
$token  = 'wpcpm_duplicates_delete_' . WPCPM_Duplicate_Finder::rows_hash( WPCPM_Duplicate_Rules::expand( WPCPM_Duplicates_Scan::report(), array( $ada ), array( 'students:' . rid( 'othold' ), 'students:' . rid( 'stunew' ) ) )['rows'] );
$html   = page( array(), $chosen );

ck( 'it is read under the review nonce', $GLOBALS['nonces'], array( 'wpcpm_duplicates_review' ) );
ck( 'it says what a press of Delete removes', has( $html, '4 rows will be deleted from Airtable: Students 2, Students Reports 1, Feedback 1. A sealed copy of each row is kept on this site for 30 days.' ), true );
ck( 'and what was left out, and why', has( $html, 'Ada Example: Students ' . rid( 'stunew' ) . ': This row cannot be deleted from the finder.' ), true );
ck( 'the red button posts to admin-post under a nonce tied to this selection', array( has( $html, 'value="n-' . $token . '"' ), has( $html, 'name="action" value="wpcpm_duplicates_delete"' ), has( $html, '<button type="submit" class="button button-primary wpcpm-duplicates__delete">Delete 4 rows from Airtable</button>' ) ), array( true, true, true ) );
ck( 'Back to the list carries the same ticks', array( has( $html, '<input type="hidden" name="wpcpm_back" value="1" />' ), substr_count( $html, '<input type="hidden" name="wpcpm_students[]" value="' . $ada . '" />' ) ), array( true, 2 ) );

$GLOBALS['switch'] = false;
$html              = page( array(), $chosen );
$GLOBALS['switch'] = true;
ck( 'deleting switched off: the button is there, disabled, with the reason', array( has( $html, 'wpcpm-duplicates__delete" disabled>' ), has( $html, 'Deleting is switched off under WPCredits Program &gt; Settings.' ) ), array( true, true ) );

WPCPM_Duplicates_Scan::start();
$html = page( array(), $chosen );
WPCPM_Duplicates_Scan::cancel();
ck( 'a scan running during the confirmation: the button is there, disabled, with the reason', array( has( $html, 'wpcpm-duplicates__delete" disabled>' ), has( $html, 'A scan is running and is about to write a new list.' ) ), array( true, true ) );

$html = page( array(), array( 'wpcpm_back' => '1', 'wpcpm_students' => array( $ada ), 'wpcpm_rows' => array( 'students:' . rid( 'othold' ) ) ) );
ck( 'Back to the list: read under the review nonce, with the same boxes ticked and no others', array( $GLOBALS['nonces'], has( $html, 'data-ready checked />' ), has( $html, 'value="students:' . rid( 'othold' ) . '" data-rows="students" checked' ), has( $html, 'value="students:' . rid( 'othnew' ) . '" data-rows="students" checked' ) ), array( array( 'wpcpm_duplicates_review' ), true, true, false ) );

/* ---- delete ------------------------------------------------------------------------------ */

echo "\n=== Delete: the nonce is tied to exactly the rows listed ===\n";

$pick = static function ( $table, $tag ) {
	return array( 'table' => $table, 'id' => rid( $tag ) );
};
$two  = WPCPM_Duplicate_Finder::rows_hash( array( $pick( 'students', 'othold' ), $pick( 'reports', 'lknew' ) ) );
ck( 'the same rows in any order are the same token', WPCPM_Duplicate_Finder::rows_hash( array( $pick( 'reports', 'lknew' ), $pick( 'students', 'othold' ) ) ), $two );
ck( 'one row more is another', WPCPM_Duplicate_Finder::rows_hash( array( $pick( 'students', 'othold' ), $pick( 'reports', 'lknew' ), $pick( 'students', 'othnew' ) ) ) === $two, false );
ck( 'a student\'s key stands for the rows the report gives it: her three older rows', WPCPM_Duplicate_Finder::rows_hash( WPCPM_Duplicate_Rules::expand( WPCPM_Duplicates_Scan::report(), array( $ada ), array() )['rows'] ), WPCPM_Duplicate_Finder::rows_hash( array( $pick( 'feedback', 'fbold' ), $pick( 'reports', 'repold' ), $pick( 'students', 'stuold' ) ) ) );

// A user of their own, because a flash is read once per user and request.
$GLOBALS['uid']    = 21;
$GLOBALS['switch'] = false;
$_POST             = array(
	'wpcpm_students' => array( $ada, 'not-a-key', array( 'nested' ) ),
	'wpcpm_rows'     => array( 'students:' . rid( 'othold' ), 'wp_users:' . rid( 'othnew' ), 'students:recSHORT', 'students:' . rid( 'othold' ) ),
);
$out               = door( array( $finder, 'handle_delete' ) );
$_POST             = array();
ck( 'Delete checks the nonce of exactly the rows its student keys and pairs stand for, and of nothing else', $GLOBALS['nonces'], array( 'wpcpm_duplicates_delete_' . WPCPM_Duplicate_Finder::rows_hash( WPCPM_Duplicate_Rules::expand( WPCPM_Duplicates_Scan::report(), array( $ada ), array( 'students:' . rid( 'othold' ) ) )['rows'] ) ) );
ck( 'then hands them to the delete and comes back to the screen', $out, 'redirect ' . $url );
ck( 'where the notice says what the delete answered', has( page(), 'Nothing was deleted: deleting is switched off under WPCredits Program &gt; Settings.' ), true );

// A scan that finishes between the two presses can change what a student's key stands for; the
// token then no longer matches, so the confirmation's token cannot delete the new set.
$GLOBALS['uid'] = 23;
$saved          = get_option( WPCPM_Duplicates_Scan::OPT_REPORT );
WPCPM_Duplicates_Scan::drop_deleted( array( array( 'key' => $ada, 'table' => 'feedback', 'id' => rid( 'fbold' ) ) ) );
$_POST = array( 'wpcpm_students' => array( $ada ), 'wpcpm_rows' => array( 'students:' . rid( 'othold' ), 'students:' . rid( 'stunew' ) ) );
door( array( $finder, 'handle_delete' ) );
$_POST = array();
update_option( WPCPM_Duplicates_Scan::OPT_REPORT, $saved );
ck( 'a report rewritten between the confirmation and the delete gives another token, so the confirmation\'s no longer opens the delete', array( count( $GLOBALS['nonces'] ), $GLOBALS['nonces'][0] === $token ), array( 1, false ) );

$GLOBALS['uid'] = 22;
WPCPM_Flash::set(
	WPCPM_Duplicate_Finder::FLASH,
	array(
		'status'  => 'stopped',
		'deleted' => array( 'students' => 0, 'reports' => 0, 'feedback' => 1 ),
		'refused' => array( array( 'key' => $ada, 'table' => 'reports', 'id' => rid( 'repold' ), 'code' => 'site' ) ),
		'detail'  => 'Airtable asked us to wait 30 seconds before sending more requests.',
		'until'   => gmmktime( 12, 0, 0, 10, 11, 2026 ),
	)
);
$html = page();
ck( 'a stopped delete: what Airtable said, which is when to try again, what went, and until when its copy stays', has( $html, 'Airtable stopped the delete part of the way through: Airtable asked us to wait 30 seconds before sending more requests. Deleted 1 row: Students 0, Students Reports 0, Feedback 1. A sealed copy of each is kept until 11 October 2026. Rows it did not confirm are checked again by the daily run.' ), true );
ck( 'and each row it left out, with why', has( $html, '<li>Students Reports ' . rid( 'repold' ) . ': The site points at it now.</li>' ), true );
$GLOBALS['uid']    = 1;
$GLOBALS['switch'] = true;

/* ---- the log and View copy ------------------------------------------------------------------ */

echo "\n=== The log and View copy ===\n";

$kept = WPCPM_Duplicate_Vault::keep( 'students', record( 'gone', '2026-01-05T10:00:00.000Z', array( 'Email' => 'gone@example.test', 'Full Name' => 'Gone Example', 'Status' => 'Not moving forward' ) ), 7, time() );
WPCPM_Duplicate_Vault::confirm( array( $kept ) );
WPCPM_Duplicate_Vault::keep( 'feedback', record( 'wait', '2026-01-06T10:00:00.000Z', array( 'Email' => 'wait@example.test', 'Name' => 'Wait Example' ) ), 7, time() );

$html = page();
$log  = substr( $html, (int) strpos( $html, '<h2>Deleted rows</h2>' ) );
ck( 'the log: when, who, the table, the record, the created date and the status', has( $log, '<td>Pat Manager</td><td>Students</td><td><code>' . rid( 'gone' ) . '</code></td><td>2026-01-05</td><td>Not moving forward</td>' ), true );
ck( 'View copy, behind a nonce keyed to the copy', has( $log, 'href="' . $url . '&wpcpm_copy=' . $kept . '&_wpnonce=n-wpcpm_duplicates_view_' . $kept . '"' ), true );
ck( 'a delete Airtable did not confirm says so', has( $log, 'Airtable did not confirm this delete; the daily run checks it again.' ), true );
ck( 'and the log holds no name and no address', array( has( $log, 'Gone Example' ), has( $log, 'gone@example.test' ), has( $log, 'wait@example.test' ) ), array( false, false, false ) );

$html = page( array( 'wpcpm_copy' => (string) $kept ) );
ck( 'View copy: under its own nonce, the row whole, for typing back by hand', array( $GLOBALS['nonces'], has( $html, '<th scope="row">Email</th><td>gone@example.test</td>' ), has( $html, 'There is no automatic restore' ) ), array( array( 'wpcpm_duplicates_view_' . $kept ), true, true ) );

WPCPM_Duplicate_Vault::purge( static function () { return null; }, time() + 31 * DAY_IN_SECONDS );
$html = page( array( 'wpcpm_copy' => (string) $kept ) );
ck( 'and once erased, it opens no more', array( has( $html, 'This copy has been erased: copies are kept for 30 days.' ), has( $html, 'gone@example.test' ) ), array( true, false ) );
ck( 'while the log keeps the entry', has( page(), 'Erased after 30 days.' ), true );

foreach ( array( 'includes/tools/class-wpcpm-duplicate-finder.php', 'includes/tools/class-wpcpm-duplicate-finder-screen.php', 'assets/js/duplicate-finder.js', 'assets/css/duplicate-finder.css' ) as $file ) {
	ck( 'no dash but the plain hyphen in ' . $file, 1 === preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $file ) ), false );
}

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
