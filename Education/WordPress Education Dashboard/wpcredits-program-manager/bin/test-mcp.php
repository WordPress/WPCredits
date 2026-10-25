<?php
/**
 * The MCP class (1.123.30): the log's daily sweep and its place on the clock, the plugin's own MCP server,
 * the door that says who may connect to it, and the password fence.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The sweep deletes the calls older than the retention setting and no other row.** Read through the
 *   real audit class, over stand-ins of the posts: a call just inside the days is kept, one just outside
 *   them goes, and an old institution row, sponsor row and program row stay whatever their age, since
 *   the log's other keys are other histories with other retention. The setting is read on each run, so a
 *   change of the days takes effect at the next sweep.
 * - **A run clears the log in batches of 500, oldest first, at most 20 of them,** and says how many in
 *   all: one account can write far more rows a day than one batch deletes, so a sweep that stopped at one
 *   batch would fall behind for good. It stops at the first batch that is not full, and the cutoff is
 *   worked out once for the whole run, so a long run does not chase a moving edge.
 * - **The days are floored at the point of use.** The settings do not clamp what they read, so a stored
 *   0, a negative number or text would make the cutoff now and empty the whole log; the sweep keeps at
 *   least the seven days the setting itself allows.
 * - **The sweep is a daily event, put on the clock once.** `init()` hooks the sweep to its action and the
 *   schedule to WordPress's `init`; running that twice leaves one event, daily, due in the future; and the
 *   job runs whether the switch is on or off, since a log that outlives the switch is still to be pruned.
 * - **Deactivation takes the event off the clock.** A daily event with no listener is what it would
 *   otherwise leave behind.
 * - **The loader wires it:** `WPCPM_MCP::init()` is called inside `wpcpm_bootstrap()`, after the abilities',
 *   and `WPCPM_MCP::deactivate()` inside `wpcpm_deactivate()`, read from the main file.
 * - **The adapter's default server is off whatever the switch says,** and the plugin's own server is created
 *   only with the switch on, with the id, route, name, transport, error handler and tools the design fixes,
 *   and permit() as its permission callback. The adapter's classes are named as strings, so the file loads
 *   without the adapter.
 * - **A Claude password is a name that begins with Claude** and not a longer word: " claude - laptop" and
 *   "CLAUDE" are Claude passwords; "Claudette", "My Claude" and "" are not (Review Focus 3).
 * - **The door** (what the transport's permission callback asks) refuses in a fixed order and stops at the
 *   first ground: no account, the switch off, a sign-in that is not a Claude password, an account without the
 *   capability, an account not on the list. A request with no account writes no row, and neither does the
 *   allowed one; every other refusal writes one, at most 60 of an account's an hour (Review Focus 1). A
 *   check that throws is a refusal, never an exception, and a row that cannot be written does not change the
 *   ground.
 * - **`permit()` is the callback the adapter is given, and it is a strict bool:** `true` for the allowed case
 *   and `false` for every refusal and for anything that throws, never the door's `WP_Error` (truthy in
 *   PHP). It writes the rows the door writes. A server whose adapter throws is not created and nothing
 *   escapes.
 * - **The REST fence** answers 403 to a Claude password on any route but the MCP address, the switch on or
 *   off, and passes the MCP address and another password. A result that an earlier filter set is replaced by
 *   the 403 on a fenced route, unless it is an error already, and comes back as it was elsewhere. A
 *   route that only resembles the address (a trailing segment, another case, a longer name) is not the
 *   address, and a password whose name cannot be read is treated as a Claude password (Review Focus 2 and 5).
 * - **The XML-RPC fence** adds an error to the sign-in of a Claude password during an XML-RPC request, and to no
 *   other sign-in (Review Focus 2).
 *
 * Nothing touches the network or a database: the posts and the options are stand-ins and the audit log
 * and the settings are the real classes.
 *
 * Run from the plugin root:  php bin/test-mcp.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'WPCPM_VERSION', 'test' );

require_once __DIR__ . '/stubs/temp-dir.php';

$GLOBALS['opts']    = array(); // Option => value.
$GLOBALS['actions'] = array(); // Hook => the callbacks added to it.
$GLOBALS['cron']    = array(); // Hook => the events scheduled on it: time and recurrence.
$GLOBALS['get_posts_calls'] = array(); // Every ask of get_posts(): what the sweep's batches asked for.
$GLOBALS['hold_second']     = false;   // Whether the next get_posts() waits for the next second.
$GLOBALS['uid']     = 0;       // The signed-in account.
$GLOBALS['manage']  = array(); // The accounts that hold the management capability.
$GLOBALS['filters'] = array(); // Hook => the filter callbacks added to it: callback, priority, accepted arguments.
$GLOBALS['hooked']  = array(); // Hook => the priority and accepted arguments of each action added to it.
$GLOBALS['app_uuid']  = null;  // The application password that signed the request, or null.
$GLOBALS['app_items'] = array(); // User ID => uuid => the stored application password.
$GLOBALS['insert_throws'] = false; // Whether writing a log row throws.
$GLOBALS['throw_option']  = null;  // The name of an option whose read throws, or null.
$GLOBALS['translate_throws'] = false; // Whether __() throws, as a filter on gettext could.

/* ---- WordPress, as far as the class reaches ---------------------------------- */

class WP_Error {
	private $code, $message, $data, $codes = array();
	public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; if ( '' !== $code ) { $this->codes[] = $code; } }
	public function add( $code, $message, $data = '' ) { if ( array() === $this->codes ) { $this->code = $code; $this->message = $message; $this->data = $data; } $this->codes[] = $code; }
	public function has_errors() { return array() !== $this->codes; }
	public function get_error_codes() { return $this->codes; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
/** A REST request, as far as the fence reads it: its route. */
class WP_REST_Request {
	private $route;
	public function __construct( $route = '/' ) { $this->route = $route; }
	public function get_route() { return $this->route; }
}
class WP_Post {
	public $ID; public $post_type; public $post_status; public $post_author; public $post_content; public $post_title; public $post_date_gmt;
	public function __construct( array $a ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}

/** The record-id test the audit log's institution writer asks, as the audit suite stands it in. */
class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function __( $text, $domain = null ) { if ( $GLOBALS['translate_throws'] ) { throw new RuntimeException( 'A gettext filter threw.' ); } return $text; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['actions'][ $hook ][] = $callback; $GLOBALS['hooked'][ $hook ][] = array( 'priority' => $priority, 'args' => $args ); }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['filters'][ $hook ][] = array( 'callback' => $callback, 'priority' => $priority, 'args' => $args ); return true; }
function add_option( $k, $v, $dep = '', $a = 'yes' ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function get_option( $k, $d = false ) { if ( $k === $GLOBALS['throw_option'] ) { throw new RuntimeException( 'The options table is gone.' ); } return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $n ) { return abs( (int) $n ); }
function wp_date( $f, $t = null ) { return '2026-10-24 10:00'; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
require_once __DIR__ . '/stubs/caps.php';

// The clock: an event is kept with the time and the recurrence it was scheduled with, and only one per
// hook is asked for, as the plugin's own jobs are.
function wp_next_scheduled( $hook, $args = array() ) { return isset( $GLOBALS['cron'][ $hook ][0] ) ? $GLOBALS['cron'][ $hook ][0]['time'] : false; }
function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array(), $wp_error = false ) { $GLOBALS['cron'][ $hook ][] = array( 'time' => (int) $timestamp, 'recurrence' => $recurrence ); return true; }
function wp_clear_scheduled_hook( $hook, $args = array() ) { $had = isset( $GLOBALS['cron'][ $hook ] ) ? count( $GLOBALS['cron'][ $hook ] ) : 0; unset( $GLOBALS['cron'][ $hook ] ); return $had; }

// The posts the audit log keeps, as the audit suite stands them in (bin/test-audit.php): a row's time is
// its own `post_date_gmt` when a check set one, else a second per ID after a fixed start.
$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['next']  = 100;
function wpcpm_test_when( $post ) { return ! empty( $post->post_date_gmt ) ? (int) strtotime( $post->post_date_gmt . ' UTC' ) : 1757000000 + (int) $post->ID; }
function get_post_time( $f, $gmt, $post, $translate = false ) { return wpcpm_test_when( $post ); }
function wp_insert_post( array $a, $wp_error = false ) {
	if ( $GLOBALS['insert_throws'] ) { throw new RuntimeException( 'The table is full.' ); }
	$id = $GLOBALS['next']++;
	$a = stripslashes_deep( $a );
	$a['ID'] = $id;
	$GLOBALS['posts'][ $id ] = new WP_Post( $a );
	return $id;
}
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ $id ][ $k ] = stripslashes_deep( $v ); return true; }
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function get_post_meta( $id, $k, $single = false ) { return isset( $GLOBALS['pmeta'][ $id ][ $k ] ) ? $GLOBALS['pmeta'][ $id ][ $k ] : ''; }
function wp_delete_post( $id, $force = false ) {
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) { return false; }
	$post = $GLOBALS['posts'][ $id ];
	unset( $GLOBALS['posts'][ $id ], $GLOBALS['pmeta'][ $id ] );
	return $post;
}
// Enough of get_posts() for the log's readers and its deleter: post type, status, numberposts, fields
// (`ids`), a meta_query of `key`+`value` or `key`+`compare EXISTS` clauses (AND), a date_query of `before`
// clauses on the GMT date (exclusive), and newest first (date, then ID) unless the date is asked for
// ascending.
function get_posts( array $args ) {
	// Every ask is kept, for the checks of how the sweep batches. A check can ask the first ask to wait
	// for the next second, so a sweep that works out its cutoff again for each batch is seen to differ.
	$GLOBALS['get_posts_calls'][] = array(
		'numberposts' => isset( $args['numberposts'] ) ? $args['numberposts'] : null,
		'date'        => isset( $args['orderby']['date'] ) ? $args['orderby']['date'] : null,
		'before'      => isset( $args['date_query'][0]['before'] ) ? $args['date_query'][0]['before'] : null,
	);
	if ( ! empty( $GLOBALS['hold_second'] ) ) {
		$GLOBALS['hold_second'] = false;
		time_sleep_until( floor( microtime( true ) ) + 1.01 );
	}
	$out   = array();
	$ids   = array_keys( $GLOBALS['posts'] );
	$whens = array();
	foreach ( $ids as $id ) { $whens[ $id ] = wpcpm_test_when( $GLOBALS['posts'][ $id ] ); }
	usort( $ids, function ( $a, $b ) use ( $whens ) {
		return array( $whens[ $b ], $b ) <=> array( $whens[ $a ], $a );
	} );
	if ( isset( $args['orderby']['date'] ) && 'ASC' === $args['orderby']['date'] ) { $ids = array_reverse( $ids ); }
	foreach ( $ids as $id ) {
		$post = $GLOBALS['posts'][ $id ];
		if ( $post->post_type !== $args['post_type'] ) { continue; }
		$ok = true;
		foreach ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			$have = isset( $GLOBALS['pmeta'][ $id ][ $clause['key'] ] );
			if ( isset( $clause['compare'] ) && 'EXISTS' === $clause['compare'] ) { $ok = $ok && $have; continue; }
			$ok = $ok && $have && 0 === strcasecmp( (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ], (string) $clause['value'] );
		}
		foreach ( isset( $args['date_query'] ) ? $args['date_query'] : array() as $clause ) {
			if ( is_array( $clause ) && isset( $clause['before'] ) ) { $ok = $ok && wpcpm_test_when( $post ) < strtotime( $clause['before'] . ' UTC' ); }
		}
		if ( $ok ) { $out[] = ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? $id : $post; }
		if ( isset( $args['numberposts'] ) && $args['numberposts'] > 0 && count( $out ) >= $args['numberposts'] ) { break; }
	}
	return $out;
}

// Core's: the Abilities API is there (the abilities exist when the adapter is active, and a stand-in for
// its version says so), and the id of the application password that signed this request, or null.
function wp_register_ability( $name, $args ) { return true; }
function rest_get_authenticated_app_password() { return $GLOBALS['app_uuid']; }
class WP_Application_Passwords {
	public static function get_user_application_password( $user_id, $uuid ) {
		return isset( $GLOBALS['app_items'][ (int) $user_id ][ $uuid ] ) ? $GLOBALS['app_items'][ (int) $user_id ][ $uuid ] : null;
	}
}
define( 'WP_MCP_VERSION', '0.7.1' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-abilities.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mcp.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

/** Write one log row of a call, aged by a number of seconds; its ID. */
function call_row( $age ) {
	$id = WPCPM_Institution_Audit::record_mcp(
		array(
			'actor'    => 3,
			'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
			'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
			'data'     => array( 'ability' => 'wpcpm/program-counts', 'outcome' => 'answered', 'reason' => '' ),
		)
	);

	$GLOBALS['posts'][ $id ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', time() - $age );

	return $id;
}

/** Whether a row is still there. */
function alive( $id ) {
	return isset( $GLOBALS['posts'][ $id ] );
}

/** Whether some strings occur in a text, every one, in the order given. */
function in_order_of( $text, array $needles ) {
	$last = -1;

	foreach ( $needles as $needle ) {
		$at = strpos( $text, $needle );

		if ( false === $at || $at <= $last ) {
			return false;
		}

		$last = $at;
	}

	return true;
}

echo "=== The sweep ===\n";

ck( 'the sweep\'s hook is prefixed and named for the log', WPCPM_MCP::CRON_SWEEP, 'wpcpm_mcp_log_sweep' );

WPCPM_Settings::patch( array( 'mcp_log_days' => 30 ) );

$inside  = call_row( 29 * DAY_IN_SECONDS );
$outside = call_row( 31 * DAY_IN_SECONDS );
$ancient = call_row( 200 * DAY_IN_SECONDS );
$fresh   = call_row( 60 );

$base    = array( 'ground' => WPCPM_Institution_Audit::GROUND_MANAGER, 'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX, 'actor' => 3, 'subject' => '5' );
$aged    = static function ( $id, $age ) { $GLOBALS['posts'][ $id ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', time() - $age ); return $id; };
$old_inst    = $aged( WPCPM_Institution_Audit::record( array_merge( $base, array( 'kind' => 'member_added', 'institution' => 'recINST0000000001', 'message' => 'Old institution row.' ) ) ), 400 * DAY_IN_SECONDS );
$old_sponsor = $aged( WPCPM_Institution_Audit::record_sponsor( array_merge( $base, array( 'kind' => 'member_added', 'sponsor' => 'recSPONSOR0000001', 'message' => 'Old sponsor row.' ) ) ), 400 * DAY_IN_SECONDS );
$old_program = $aged( WPCPM_Institution_Audit::record_program( array_merge( $base, array( 'kind' => 'sponsor_event_planned', 'message' => 'Old program row.' ) ) ), 400 * DAY_IN_SECONDS );

ck( 'with 30 days kept, a sweep deletes the two calls older than that and says two', WPCPM_MCP::sweep(), 2 );
ck( 'the call just inside the days and the new one stay, the two outside them go', array( alive( $inside ), alive( $fresh ), alive( $outside ), alive( $ancient ) ), array( true, true, false, false ) );
ck( 'an old institution row, an old sponsor row and an old program row stay, whatever their age', array( alive( $old_inst ), alive( $old_sponsor ), alive( $old_program ) ), array( true, true, true ) );
ck( 'a sweep with nothing to delete says zero', WPCPM_MCP::sweep(), 0 );

// The setting is read on each run.
$eighty = call_row( 80 * DAY_IN_SECONDS );
$ninety = call_row( 91 * DAY_IN_SECONDS );

WPCPM_Settings::patch( array( 'mcp_log_days' => 90 ) );
ck( 'raised to 90 days, a sweep keeps the call of 80 days and the one just inside 30, and deletes the one of 91', array( WPCPM_MCP::sweep(), alive( $eighty ), alive( $inside ), alive( $ninety ) ), array( 1, true, true, false ) );

WPCPM_Settings::patch( array( 'mcp_log_days' => 7 ) );
ck( 'lowered to its floor of 7 days, the next sweep deletes the calls older than a week and keeps the new one', array( WPCPM_MCP::sweep(), alive( $eighty ), alive( $inside ), alive( $fresh ) ), array( 2, false, false, true ) );
ck( 'and the other keys\' rows are still there', array( alive( $old_inst ), alive( $old_sponsor ), alive( $old_program ) ), array( true, true, true ) );

// The sweep needs no switch: the log outlives the switch, and is pruned all the same.
WPCPM_Settings::patch( array( 'mcp_enabled' => false, 'mcp_log_days' => 30 ) );
$off_old = call_row( 45 * DAY_IN_SECONDS );
ck( 'with the switch off a sweep still deletes an old call', array( WPCPM_MCP::sweep(), alive( $off_old ) ), array( 1, false ) );

echo "\n=== A run clears the log in batches of 500, at most 20 of them ===\n";

/** Write a number of calls all older than the cut: the first just over 40 days, each a minute older. */
function old_calls( $count ) {
	$ids = array();

	for ( $n = 0; $n < $count; $n++ ) {
		$ids[] = call_row( 40 * DAY_IN_SECONDS + $n * MINUTE_IN_SECONDS );
	}

	return $ids;
}

/** How many of some rows are still there. */
function still_there( array $ids ) {
	return count( array_filter( $ids, 'alive' ) );
}

// 30 days kept. Twelve hundred and one old calls go in one run, as 500, 500 and 201, and the run says so.
WPCPM_Settings::patch( array( 'mcp_log_days' => 30 ) );
$many = old_calls( 1201 );
$GLOBALS['get_posts_calls'] = array();
$GLOBALS['hold_second']     = true;
$started                    = time();
$total                      = WPCPM_MCP::sweep();
$clock_moved                = time() > $started;
$asked                      = $GLOBALS['get_posts_calls'];

ck( '1,201 old calls: one sweep deletes them all and says 1,201', array( $total, still_there( $many ), alive( $fresh ) ), array( 1201, 0, true ) );
ck( 'as three batches of at most 500, the oldest first', array( array_column( $asked, 'numberposts' ), array_unique( array_column( $asked, 'date' ) ) ), array( array( 500, 500, 500 ), array( 'ASC' ) ) );
ck( 'the cutoff is worked out once for the run: every batch asked for the same cut, though the clock moved a second between the first and the next', array( $clock_moved, count( array_unique( array_column( $asked, 'before' ) ) ) ), array( true, 1 ) );

// The edges of "while a batch is full".
$exactly = old_calls( 500 );
$GLOBALS['get_posts_calls'] = array();
$total = WPCPM_MCP::sweep();
ck( '500 old calls: one full batch, then one that finds nothing, and the total is 500', array( $total, still_there( $exactly ), count( $GLOBALS['get_posts_calls'] ) ), array( 500, 0, 2 ) );

$exactly = old_calls( 499 );
$GLOBALS['get_posts_calls'] = array();
$total = WPCPM_MCP::sweep();
ck( '499 old calls: one batch that is not full, and the run stops there', array( $total, still_there( $exactly ), count( $GLOBALS['get_posts_calls'] ) ), array( 499, 0, 1 ) );

$exactly = old_calls( 1000 );
$GLOBALS['get_posts_calls'] = array();
$total = WPCPM_MCP::sweep();
ck( '1,000 old calls: two full batches, a third that finds nothing, and the total is 1,000', array( $total, still_there( $exactly ), count( $GLOBALS['get_posts_calls'] ) ), array( 1000, 0, 3 ) );

$GLOBALS['get_posts_calls'] = array();
$total                      = WPCPM_MCP::sweep();
ck( 'with nothing old a sweep asks once and says zero', array( $total, count( $GLOBALS['get_posts_calls'] ) ), array( 0, 1 ) );

// A run stops at 20 batches: 10,000 rows, the 10,001st waiting for the next run.
$beyond = old_calls( 10001 );
$GLOBALS['get_posts_calls'] = array();
$total = WPCPM_MCP::sweep();
ck( '10,001 old calls: one run deletes 10,000, in 20 batches, and stops with one left, the newest of them', array( $total, still_there( $beyond ), count( $GLOBALS['get_posts_calls'] ), alive( $beyond[0] ), alive( $beyond[10000] ) ), array( 10000, 1, 20, true, false ) );
$GLOBALS['get_posts_calls'] = array();
ck( 'and the next run deletes the one that is left, in one batch', array( WPCPM_MCP::sweep(), still_there( $beyond ), count( $GLOBALS['get_posts_calls'] ) ), array( 1, 0, 1 ) );
ck( 'the new call is still there, and so are the other keys\' old rows', array( alive( $fresh ), alive( $old_inst ), alive( $old_sponsor ), alive( $old_program ) ), array( true, true, true, true ) );

echo "\n=== The days are floored where they are used ===\n";

// The settings do not clamp what they read, so a bad value is written to the option directly, the one way
// a value outside 7 to 365 can get there: patch() and the Save both hold it to the range.
foreach ( array( 0, -30, 'abc', '' ) as $stored ) {
	update_option( WPCPM_Settings::OPT_NAME, array_merge( WPCPM_Settings::get(), array( 'mcp_log_days' => $stored ) ) );

	$six   = call_row( 6 * DAY_IN_SECONDS );
	$eight = call_row( 8 * DAY_IN_SECONDS );
	$gone  = WPCPM_MCP::sweep();

	ck( 'with ' . var_export( $stored, true ) . ' stored, a sweep keeps the week: a call of 6 days stays, one of 8 goes, and the new one stays', array( $gone, alive( $six ), alive( $eight ), alive( $fresh ) ), array( 1, true, false, true ) );

	wp_delete_post( $six, true );
}

WPCPM_Settings::patch( array( 'mcp_log_days' => 30 ) );
$thirty = call_row( 29 * DAY_IN_SECONDS );
$gone   = WPCPM_MCP::sweep();
ck( 'and a setting in range is used as it is: 30 days kept, a call of 29 days stays', array( WPCPM_Settings::get_value( 'mcp_log_days' ), $gone, alive( $thirty ) ), array( 30, 0, true ) );
wp_delete_post( $thirty, true );

echo "\n=== The schedule ===\n";

$GLOBALS['actions'] = array();
WPCPM_MCP::init();

ck( 'init() hooks the sweep on its action', isset( $GLOBALS['actions'][ WPCPM_MCP::CRON_SWEEP ] ) ? $GLOBALS['actions'][ WPCPM_MCP::CRON_SWEEP ] : null, array( array( 'WPCPM_MCP', 'sweep' ) ) );
ck( 'and hooks the schedule on init, which is where the event is put on the clock', in_array( array( 'WPCPM_MCP', 'schedule' ), isset( $GLOBALS['actions']['init'] ) ? $GLOBALS['actions']['init'] : array(), true ), true );
ck( 'the event is not on the clock until init runs', isset( $GLOBALS['cron'][ WPCPM_MCP::CRON_SWEEP ] ), false );

/** What WordPress does when `init` fires: every callback hooked to it, in order. */
$fire_init = static function () {
	foreach ( isset( $GLOBALS['actions']['init'] ) ? $GLOBALS['actions']['init'] : array() as $callback ) {
		call_user_func( $callback );
	}
};

$before = time();
$fire_init();
$events = isset( $GLOBALS['cron'][ WPCPM_MCP::CRON_SWEEP ] ) ? $GLOBALS['cron'][ WPCPM_MCP::CRON_SWEEP ] : array();

ck( 'when init runs, one event is scheduled, daily', array_column( $events, 'recurrence' ), array( 'daily' ) );
ck( 'and its first run is ahead of now and within a day', isset( $events[0] ) && $events[0]['time'] > $before && $events[0]['time'] <= time() + DAY_IN_SECONDS, true );

$fire_init();
$fire_init();
ck( 'init running again, on later requests, does not schedule it twice', isset( $GLOBALS['cron'][ WPCPM_MCP::CRON_SWEEP ] ) ? count( $GLOBALS['cron'][ WPCPM_MCP::CRON_SWEEP ] ) : 0, 1 );

WPCPM_Settings::patch( array( 'mcp_enabled' => true ) );
unset( $GLOBALS['cron'][ WPCPM_MCP::CRON_SWEEP ] );
$fire_init();
ck( 'the switch on or off makes no difference to the clock: with it on the event is put back', isset( $GLOBALS['cron'][ WPCPM_MCP::CRON_SWEEP ] ) ? count( $GLOBALS['cron'][ WPCPM_MCP::CRON_SWEEP ] ) : 0, 1 );
WPCPM_Settings::patch( array( 'mcp_enabled' => false ) );
unset( $GLOBALS['cron'][ WPCPM_MCP::CRON_SWEEP ] );
$fire_init();
ck( 'and with it off too', isset( $GLOBALS['cron'][ WPCPM_MCP::CRON_SWEEP ] ) ? count( $GLOBALS['cron'][ WPCPM_MCP::CRON_SWEEP ] ) : 0, 1 );

$GLOBALS['cron']['wp_version_check'] = array( array( 'time' => time(), 'recurrence' => 'twicedaily' ) );
WPCPM_MCP::deactivate();
ck( 'deactivate() takes the event off the clock and leaves core\'s alone', array( isset( $GLOBALS['cron'][ WPCPM_MCP::CRON_SWEEP ] ), isset( $GLOBALS['cron']['wp_version_check'] ) ), array( false, true ) );

echo "\n=== The loader wires it ===\n";

$main = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php' );

// Each call is looked for inside its own function alone: the match may not run past the function's closing
// brace, at the start of a line, into a function after it (bin/test-outcome-figures.php reads the loader so).
$booted      = '/function wpcpm_bootstrap\(\) \{(?:(?!\n\}).)*?WPCPM_MCP::init\(\);/s';
$deactivated = '/function wpcpm_deactivate\(\) \{(?:(?!\n\}).)*?WPCPM_MCP::deactivate\(\);/s';

ck( 'the plugin loads the class, calls init() inside wpcpm_bootstrap() and deactivate() inside wpcpm_deactivate()',
	array(
		false !== strpos( $main, "require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mcp.php';" ),
		(bool) preg_match( $booted, $main ),
		(bool) preg_match( $deactivated, $main ),
	),
	array( true, true, true ) );
ck( 'and the boot comes after the abilities\' own',
	in_order_of( $main, array( 'WPCPM_Abilities::init();', 'WPCPM_MCP::init();' ) ),
	true );
ck( 'a call in a function after the bootstrap\'s or the deactivation\'s is not the boot or the deactivation',
	array(
		(bool) preg_match( $booted, "function wpcpm_bootstrap() {\n\tWPCPM_Overview::init();\n}\n\nfunction wpcpm_later() {\n\tWPCPM_MCP::init();\n}\n" ),
		(bool) preg_match( $deactivated, "function wpcpm_deactivate() {\n\tWPCPM_Tools::deactivate();\n}\n\nfunction wpcpm_later() {\n\tWPCPM_MCP::deactivate();\n}\n" ),
	),
	array( false, false ) );

/* ---- The server, the door and the fence ------------------------------------------------------------ */

/** What WordPress does for `apply_filters()`: the callbacks in priority order, each handed the value and the arguments it accepts. */
function run_filters( $hook, $value, ...$extra ) {
	$list  = isset( $GLOBALS['filters'][ $hook ] ) ? $GLOBALS['filters'][ $hook ] : array();
	$order = array_keys( $list );

	usort( $order, static function ( $a, $b ) use ( $list ) { return array( $list[ $a ]['priority'], $a ) <=> array( $list[ $b ]['priority'], $b ); } );

	foreach ( $order as $at ) {
		$value = call_user_func_array( $list[ $at ]['callback'], array_slice( array_merge( array( $value ), $extra ), 0, $list[ $at ]['args'] ) );
	}

	return $value;
}

/** The MCP Adapter, as far as the plugin reaches it: it keeps the arguments of each `create_server()`. */
class Stub_Adapter {
	public $calls = array();
	public function create_server( ...$args ) { $this->calls[] = $args; return $this; }
}

/** A capability check that throws: `user_can()` asks `isset( $GLOBALS['grants'][ $id ] )`. */
class Throwing_Grants implements ArrayAccess {
	public function offsetExists( $offset ): bool { throw new RuntimeException( 'The capability table is gone.' ); }
	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) { return array(); }
	public function offsetSet( $offset, $value ): void {}
	public function offsetUnset( $offset ): void {}
}

/** An adapter whose `create_server()` throws, as one with a broken registry would. */
class Throwing_Adapter {
	public $calls = 0;
	public function create_server( ...$args ) { ++$this->calls; throw new RuntimeException( 'The registry is gone.' ); }
}

/** An adapter whose `create_server()` answers with an error whose message holds a token and an address. */
class Erroring_Adapter {
	public $calls = 0;
	private $code;
	public function __construct( $code = 'mcp_server_exists' ) { $this->code = $code; }
	public function create_server( ...$args ) { ++$this->calls; return new WP_Error( $this->code, 'Token patXXXXXXXX rejected at https://api.airtable.example.test/v0/appXXXX' ); }
}

/** A REST response, as far as the fence meets one: an object that is not an error. */
class WP_REST_Response {
	public $data;
	public function __construct( $data = null ) { $this->data = $data; }
}

/** The code of an error, or '' for anything that is not one. */
function code_of( $thing ) {
	return is_wp_error( $thing ) ? $thing->get_error_code() : '';
}

/** How many log rows there are. */
function row_count() {
	return count( WPCPM_Institution_Audit::mcp_entries( 0 ) );
}

/** The latest log row, or an empty array. */
function last_row() {
	$rows = WPCPM_Institution_Audit::mcp_entries( 1 );

	return isset( $rows[0] ) ? $rows[0] : array();
}

$UUID = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

/**
 * Set the world a request arrives in: the account, whether it holds the capability, the application
 * password that signed it (a name, or null for a browser session), the switch and the list.
 */
function arrive( $uid, $manager, $name, $switch = true, array $users = array( 7 ) ) {
	global $UUID;

	WPCPM_Settings::patch( array( 'mcp_enabled' => $switch, 'mcp_users' => $users ) );

	$GLOBALS['uid']      = $uid;
	$GLOBALS['manage']   = $manager ? array( $uid ) : array();
	$GLOBALS['app_uuid'] = null === $name ? null : $UUID;

	unset( $GLOBALS['app_items'][ $uid ] );

	if ( null !== $name ) {
		$GLOBALS['app_items'][ $uid ][ $UUID ] = array( 'uuid' => $UUID, 'name' => $name );
	}
}

/** Knock at the door as the world stands: the result, how many rows it wrote and the row, if any. */
function knock() {
	$before = row_count();
	$result = WPCPM_MCP::door( new WP_REST_Request( '/mcp/wpcredits' ) );
	$wrote  = row_count() - $before;

	return array( 'result' => $result, 'wrote' => $wrote, 'row' => $wrote ? last_row() : array() );
}

// The sweep's rows carry dates of their own; the rows below are told apart by the order they were written in.
$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();

echo "\n=== The constants ===\n";

ck( 'the server\'s id', WPCPM_MCP::SERVER_ID, 'wpcredits' );
ck( 'the namespace of its route', WPCPM_MCP::ROUTE_NAMESPACE, 'mcp' );
ck( 'and the route, so the address is /wp-json/mcp/wpcredits', WPCPM_MCP::ROUTE, 'wpcredits' );

echo "\n=== init() hooks the server, the door and the fence ===\n";

$GLOBALS['actions'] = array();
$GLOBALS['hooked']  = array();
$GLOBALS['filters'] = array();
WPCPM_MCP::init();

ck( 'the server is created when the adapter says it starts', isset( $GLOBALS['actions']['mcp_adapter_init'] ) ? $GLOBALS['actions']['mcp_adapter_init'] : null, array( array( 'WPCPM_MCP', 'create_server' ) ) );
ck( 'taking the adapter', isset( $GLOBALS['hooked']['mcp_adapter_init'][0] ) ? $GLOBALS['hooked']['mcp_adapter_init'][0]['args'] : null, 1 );

$fence = isset( $GLOBALS['filters']['rest_pre_dispatch'] ) ? $GLOBALS['filters']['rest_pre_dispatch'] : array();
ck( 'the REST fence is on rest_pre_dispatch, alone, first of all (priority 1) and taking the result, the server and the request', $fence, array( array( 'callback' => array( 'WPCPM_MCP', 'fence_rest' ), 'priority' => 1, 'args' => 3 ) ) );

ck( 'the XML-RPC fence is on the action that checks an application password, taking its four arguments', array(
	isset( $GLOBALS['actions']['wp_authenticate_application_password_errors'] ) ? $GLOBALS['actions']['wp_authenticate_application_password_errors'] : null,
	isset( $GLOBALS['hooked']['wp_authenticate_application_password_errors'][0] ) ? $GLOBALS['hooked']['wp_authenticate_application_password_errors'][0]['args'] : null,
), array( array( array( 'WPCPM_MCP', 'fence_xmlrpc' ) ), 4 ) );

ck( 'the sweep\'s own hooks are still there', array( isset( $GLOBALS['actions'][ WPCPM_MCP::CRON_SWEEP ] ), in_array( array( 'WPCPM_MCP', 'schedule' ), $GLOBALS['actions']['init'], true ) ), array( true, true ) );

echo "\n=== The adapter's default server is off, whatever the switch says ===\n";

$hooked = isset( $GLOBALS['filters']['mcp_adapter_create_default_server'] ) ? $GLOBALS['filters']['mcp_adapter_create_default_server'] : array();
ck( 'one filter on mcp_adapter_create_default_server, last of all (so a later one cannot turn the server back on)', array( count( $hooked ), isset( $hooked[0] ) ? $hooked[0]['priority'] : null ), array( 1, PHP_INT_MAX ) );

foreach ( array( true, false ) as $on ) {
	WPCPM_Settings::patch( array( 'mcp_enabled' => $on ) );
	ck( 'with the switch ' . ( $on ? 'on' : 'off' ) . ' the adapter is told false, whatever it asks with', array( run_filters( 'mcp_adapter_create_default_server', true ), run_filters( 'mcp_adapter_create_default_server', false ) ), array( false, false ) );
}

echo "\n=== The server ===\n";

$EXPECTED = array(
	'wpcredits',
	'mcp',
	'wpcredits',
	'WPCredits Program',
	'Read-only answers from the WPCredits Program Manager\'s own records.',
	'test',
	array( 'WP\MCP\Transport\HttpTransport' ),
	'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler',
	null,
	array( 'wpcpm/program-counts', 'wpcpm/waiting-for-decision', 'wpcpm/mentor-engagement-summary', 'wpcpm/institution-summary' ),
	array(),
	array(),
	array( 'WPCPM_MCP', 'permit' ),
);

WPCPM_Settings::patch( array( 'mcp_enabled' => true ) );
$adapter = new Stub_Adapter();
WPCPM_MCP::create_server( $adapter );
ck( 'with the switch on, create_server() is called once', count( $adapter->calls ), 1 );
ck( 'with the id, route, name, description, version, the HTTP transport only, the error log handler, no observability, the four abilities as tools, no resources, no prompts and permit(), the strict-bool form of the door', isset( $adapter->calls[0] ) ? $adapter->calls[0] : null, $EXPECTED );
ck( 'the tools are the plugin\'s four abilities and nothing else: the list is the abilities\' own', $adapter->calls[0][9], WPCPM_Abilities::NAMES );
ck( 'the permission callback the adapter is given can be called, and is not door(), which answers with an error that is truthy in PHP', array( is_callable( $adapter->calls[0][12] ), $adapter->calls[0][12] !== array( 'WPCPM_MCP', 'door' ) ), array( true, true ) );
ck( 'the adapter\'s classes are named, not loaded: the transport and the error handler do not exist here', array( class_exists( 'WP\MCP\Transport\HttpTransport', false ), class_exists( 'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler', false ) ), array( false, false ) );

WPCPM_Settings::patch( array( 'mcp_enabled' => false ) );
$adapter = new Stub_Adapter();
WPCPM_MCP::create_server( $adapter );
ck( 'with the switch off no server is created (Review Focus 5)', count( $adapter->calls ), 0 );

WPCPM_Settings::patch( array( 'mcp_enabled' => true ) );
$survived = true;
try {
	WPCPM_MCP::create_server( null );
	WPCPM_MCP::create_server( 'not an adapter' );
	WPCPM_MCP::create_server( new stdClass() );
} catch ( Throwable $e ) {
	$survived = false;
}
ck( 'something that is not an adapter is ignored, not a fatal', $survived, true );

// A server that cannot be created leaves one line in PHP's error log, and nothing else is said. The line
// is read back through the error_log setting, pointed at a file of this run's temporary directory.
$log_file     = wpcpm_test_temp_dir() . 'php-errors.log';
$log_was      = ini_get( 'error_log' );
$lines_after  = static function ( callable $run ) use ( $log_file ) {
	file_put_contents( $log_file, '' );
	$run();
	$lines = array_values( array_filter( array_map( 'trim', explode( "\n", (string) file_get_contents( $log_file ) ) ), 'strlen' ) );

	return array_map( static function ( $line ) { return preg_replace( '/^\[[^\]]*\] /', '', $line ); }, $lines );
};
ini_set( 'error_log', $log_file );

// An adapter that throws must not take every REST request down with it while the switch is on.
$actions_before = $GLOBALS['actions'];
$filters_before = $GLOBALS['filters'];
$throwing       = new Throwing_Adapter();
$escaped        = null;
try {
	WPCPM_MCP::create_server( $throwing );
} catch ( Throwable $e ) {
	$escaped = get_class( $e );
}
ck( 'an adapter whose create_server() throws: nothing escapes, and it was asked once, not retried', array( $escaped, $throwing->calls ), array( null, 1 ) );
ck( 'and nothing else is registered because of it: no hook was added', array( $GLOBALS['actions'] === $actions_before, $GLOBALS['filters'] === $filters_before ), array( true, true ) );

$erroring = new Erroring_Adapter();
$lines    = $lines_after( static function () use ( $erroring ) { WPCPM_MCP::create_server( $erroring ); } );
ck( 'an adapter that answers with an error: one line, with the error\'s code and nothing of its message', array( $lines, $erroring->calls ), array( array( 'WPCredits MCP: the server could not be created (mcp_server_exists).' ), 1 ) );

$throwing = new Throwing_Adapter();
$lines    = $lines_after( static function () use ( $throwing ) { WPCPM_MCP::create_server( $throwing ); } );
ck( 'an adapter that throws: one line, with the exception\'s class and nothing of its message', array( $lines, $throwing->calls ), array( array( 'WPCredits MCP: the server could not be created (RuntimeException).' ), 1 ) );

ck( 'and the error\'s message, which holds a token and an address, is nowhere in the log', array( false !== strpos( (string) file_get_contents( $log_file ), 'patXXXX' ), false !== stripos( (string) file_get_contents( $log_file ), 'airtable' ) ), array( false, false ) );

// A code is cleaned like a name: a newline in it cannot add a line to the log.
$lines = $lines_after( static function () { WPCPM_MCP::create_server( new Erroring_Adapter( "bad\nWPCredits MCP: fake line" ) ); } );
ck( 'a code that holds a newline, spaces and a colon is one line, cleaned to the characters a code is made of', $lines, array( 'WPCredits MCP: the server could not be created (badWPCreditsMCPfakeline).' ) );

// A class in a namespace keeps its whole name: the backslashes are part of what is looked for.
eval( 'namespace Wpcpm\\Trial; class BrokenRegistry extends \\RuntimeException {}' );
$namespaced = new class() {
	public function create_server( ...$args ) { throw new Wpcpm\Trial\BrokenRegistry( 'Token patXXXXXXXX' ); }
};
$lines = $lines_after( static function () use ( $namespaced ) { WPCPM_MCP::create_server( $namespaced ); } );
ck( 'an exception of a class in a namespace is named whole, backslashes and all, and nothing of its message', $lines, array( 'WPCredits MCP: the server could not be created (Wpcpm\\Trial\\BrokenRegistry).' ) );

$adapter = new Stub_Adapter();
$lines   = $lines_after( static function () use ( $adapter ) { WPCPM_MCP::create_server( $adapter ); } );
ck( 'an adapter that creates the server leaves no line', array( $lines, count( $adapter->calls ) ), array( array(), 1 ) );

WPCPM_Settings::patch( array( 'mcp_enabled' => false ) );
$lines = $lines_after( static function () { WPCPM_MCP::create_server( new Erroring_Adapter() ); WPCPM_MCP::create_server( new Throwing_Adapter() ); } );
ck( 'with the switch off no server is tried for, so no line is left', $lines, array() );
WPCPM_Settings::patch( array( 'mcp_enabled' => true ) );

$lines = $lines_after( static function () { WPCPM_MCP::create_server( null ); WPCPM_MCP::create_server( new stdClass() ); } );
ck( 'something that is not an adapter leaves none either: it is not a failure of the server', $lines, array() );

ini_set( 'error_log', false === $log_was ? '' : $log_was );

echo "\n=== is_claude_password(): a name that begins with Claude (Review Focus 3) ===\n";

foreach ( array( 'Claude', 'Claude - laptop', ' claude - laptop', 'CLAUDE', "\tClaude\n", "\nclaude", 'claude', 'Claude2', 'Claude_laptop', 'Claude-laptop' ) as $name ) {
	ck( 'a Claude password: ' . var_export( $name, true ), WPCPM_MCP::is_claude_password( $name ), true );
}
foreach ( array( 'Claudette', 'My Claude', '', 'Claud', 'Cl aude', 'claudex', 'Backup', 'Not Claude - laptop', "\x01Claude", "\x00Claude", "\u{00A0}Claude" ) as $name ) {
	ck( 'not a Claude password: ' . var_export( $name, true ), WPCPM_MCP::is_claude_password( $name ), false );
}
ck( 'null is no name', WPCPM_MCP::is_claude_password( null ), false );
ck( 'nor is a number or a list, and neither is a warning', array( WPCPM_MCP::is_claude_password( 5 ), WPCPM_MCP::is_claude_password( array( 'Claude' ) ), WPCPM_MCP::is_claude_password( false ) ), array( false, false, false ) );
ck( 'the answer is a bool', is_bool( WPCPM_MCP::is_claude_password( 'Claude' ) ), true );

echo "\n=== current_password(): the application password that signed this request ===\n";

arrive( 7, true, null );
ck( 'a request signed by no application password names none', WPCPM_MCP::current_password(), array() );
arrive( 7, true, 'Claude - laptop' );
ck( 'one signed by a stored password gives its name and uuid', WPCPM_MCP::current_password(), array( 'name' => 'Claude - laptop', 'uuid' => $UUID ) );
ck( 'and it is the abilities\' own lookup, the same answer', WPCPM_MCP::current_password(), WPCPM_Abilities::password() );
unset( $GLOBALS['app_items'][7] );
ck( 'a password whose item cannot be found names none', WPCPM_MCP::current_password(), array() );

echo "\n=== The door: who is let in, and in which order the grounds are read ===\n";

// No account: refused, and no row. The switch and the list make no difference to that.
foreach ( array( true, false ) as $on ) {
	arrive( 0, false, 'Claude - laptop', $on, array( 7 ) );
	$k = knock();
	ck( 'no account (switch ' . ( $on ? 'on' : 'off' ) . '): refused as signed out, and no row is written (Review Focus 1)', array( code_of( $k['result'] ), is_wp_error( $k['result'] ), $k['wrote'] ), array( 'wpcpm_mcp_signed_out', true, 0 ) );
}
ck( 'and the knock took no place of any refusal cap', WPCPM_Ceiling::count( WPCPM_Ceiling::key( 'mcp-refusals', 0 ), HOUR_IN_SECONDS ), 0 );
ck( 'the refusal of a request with no account has a message and no exception', is_wp_error( $k['result'] ) && '' !== $k['result']->get_error_message(), true );
ck( 'a thousand strangers write no row at all', ( static function () { $before = row_count(); arrive( 0, false, null ); for ( $n = 0; $n < 1000; $n++ ) { WPCPM_MCP::door( new WP_REST_Request( '/mcp/wpcredits' ) ); } return row_count() - $before; } )(), 0 );

// A signed-in account, one refusal each.
arrive( 7, true, null );
$k = knock();
ck( 'a browser session (no application password) is refused as wrong_password, and logged', array( code_of( $k['result'] ), $k['wrote'], $k['row']['data']['outcome'], $k['row']['data']['reason'], $k['row']['actor'], $k['row']['data']['password'], $k['row']['data']['password_id'] ), array( 'wrong_password', 1, 'refused', 'wrong_password', 7, '', '' ) );
ck( 'the row names no ability and carries no route', array( $k['row']['data']['ability'], array_key_exists( 'route', $k['row']['data'] ) ), array( '', false ) );

arrive( 7, true, 'Backup script' );
$k = knock();
ck( 'a password not named Claude is wrong_password, logged with its name', array( code_of( $k['result'] ), $k['wrote'], $k['row']['data']['reason'], $k['row']['data']['password'], $k['row']['data']['password_id'] ), array( 'wrong_password', 1, 'wrong_password', 'Backup script', $UUID ) );

foreach ( array( 'Claudette', 'My Claude', '' ) as $name ) {
	arrive( 7, true, $name );
	$k = knock();
	ck( 'a password named ' . var_export( $name, true ) . ' is wrong_password (Review Focus 3)', array( code_of( $k['result'] ), $k['wrote'] ), array( 'wrong_password', 1 ) );
}

arrive( 7, true, 'Claude - laptop' );
unset( $GLOBALS['app_items'][7] );
$k = knock();
ck( 'a password whose item cannot be found is wrong_password, with an empty name in the row: fail closed', array( code_of( $k['result'] ), $k['wrote'], $k['row']['data']['password'] ), array( 'wrong_password', 1, '' ) );

arrive( 8, false, 'Claude - laptop', true, array( 7, 8 ) );
$k = knock();
ck( 'an account without the capability is not_manager, even when it is on the list, logged on the member ground', array( code_of( $k['result'] ), $k['wrote'], $k['row']['data']['reason'], $k['row']['ground'], $k['row']['actor'] ), array( 'not_manager', 1, 'not_manager', 'member', 8 ) );

arrive( 9, true, 'Claude - laptop', true, array( 7 ) );
$k = knock();
ck( 'an Administrator not on the list is not_allowed', array( code_of( $k['result'] ), $k['wrote'], $k['row']['data']['reason'], $k['row']['ground'] ), array( 'not_allowed', 1, 'not_allowed', 'manager' ) );

arrive( 9, true, 'Claude - laptop', true, array() );
$k = knock();
ck( 'and with nobody on the list nobody is allowed', array( code_of( $k['result'] ), $k['wrote'] ), array( 'not_allowed', 1 ) );

arrive( 7, true, 'Claude - laptop', false, array( 7 ) );
$k = knock();
ck( 'with the switch off, everything else right, the door says switch_off, and logs it', array( code_of( $k['result'] ), $k['wrote'], $k['row']['data']['reason'] ), array( 'switch_off', 1, 'switch_off' ) );

arrive( 7, true, 'Claude - laptop', true, array( 7 ) );
$k = knock();
ck( 'an Administrator on the list, with a Claude password, the switch on: let in, and no row (the answer\'s own row is the ability\'s)', array( $k['result'], $k['wrote'] ), array( true, 0 ) );

foreach ( array( ' claude - laptop', 'CLAUDE', 'Claude' ) as $name ) {
	arrive( 7, true, $name );
	$k = knock();
	ck( 'a password named ' . var_export( $name, true ) . ' is let in (Review Focus 3)', array( $k['result'], $k['wrote'] ), array( true, 0 ) );
}

// The order the grounds are read in.
arrive( 8, false, 'Backup script', false, array() );
$k = knock();
ck( 'the order: the switch off comes before the password, the capability and the list', code_of( $k['result'] ), 'switch_off' );
arrive( 8, false, 'Backup script', true, array() );
$k = knock();
ck( 'then the password before the capability and the list', code_of( $k['result'] ), 'wrong_password' );
arrive( 8, false, 'Claude - laptop', true, array() );
$k = knock();
ck( 'then the capability before the list', code_of( $k['result'] ), 'not_manager' );
arrive( 0, false, 'Backup script', false, array() );
$k = knock();
ck( 'and no account before all of them', code_of( $k['result'] ), 'wpcpm_mcp_signed_out' );

// The door answers a bool or an error, with or without a request, and does not take the request for anything.
arrive( 7, true, 'Claude - laptop' );
ck( 'with no request handed in the door still answers', WPCPM_MCP::door(), true );
ck( 'and with something else', array( WPCPM_MCP::door( null ), WPCPM_MCP::door( 'x' ), WPCPM_MCP::door( new stdClass() ) ), array( true, true, true ) );

echo "\n=== The door never throws ===\n";

arrive( 7, true, 'Claude - laptop' );
$GLOBALS['grants'] = new Throwing_Grants();
$thrown            = null;
$before            = row_count();
try {
	$result = WPCPM_MCP::door( new WP_REST_Request( '/mcp/wpcredits' ) );
} catch ( Throwable $e ) {
	$thrown = $e;
	$result = null;
}
unset( $GLOBALS['grants'] );
ck( 'a capability check that throws is a refusal with the code error, not an exception', array( null === $thrown, code_of( $result ) ), array( true, 'error' ) );
ck( 'its error row cannot be written then, since the row writer asks the same capability to name the ground: no row, and no exception for that either', row_count() - $before, 0 );
ck( 'and the door works again when the check does', WPCPM_MCP::door(), true );

// A check that throws while the row writer works: the settings read throws, and the error row is written.
arrive( 7, true, 'Claude - laptop' );
$GLOBALS['throw_option'] = WPCPM_Settings::OPT_NAME;
$thrown                  = null;
$before                  = row_count();
try {
	$result = WPCPM_MCP::door( new WP_REST_Request( '/mcp/wpcredits' ) );
} catch ( Throwable $e ) {
	$thrown = $e;
	$result = null;
}
$GLOBALS['throw_option'] = null;
$row                     = row_count() - $before ? last_row() : array();
ck( 'a settings read that throws is the code error too, and the writer, which does not read the settings, writes the row of it', array( null === $thrown, code_of( $result ), row_count() - $before, isset( $row['data'] ) ? $row['data']['reason'] : null ), array( true, 'error', 1, 'error' ) );

arrive( 7, true, 'Backup script' );
$GLOBALS['insert_throws'] = true;
$thrown                   = null;
try {
	$result = WPCPM_MCP::door( new WP_REST_Request( '/mcp/wpcredits' ) );
} catch ( Throwable $e ) {
	$thrown = $e;
	$result = null;
}
$GLOBALS['insert_throws'] = false;
ck( 'a row that cannot be written does not change the ground or throw: still wrong_password', array( null === $thrown, code_of( $result ) ), array( true, 'wrong_password' ) );

echo "\n=== The refusals of one account are logged at most 60 an hour (Review Focus 1) ===\n";

arrive( 21, true, null );
$before = row_count();
$codes  = array();
for ( $n = 0; $n < 61; $n++ ) {
	$codes[] = code_of( WPCPM_MCP::door( new WP_REST_Request( '/mcp/wpcredits' ) ) );
}
ck( '61 refusals of one account write 60 rows', row_count() - $before, 60 );
ck( 'all 61 are refused all the same: the cap bounds the log, not the door', array_unique( $codes ), array( 'wrong_password' ) );

arrive( 22, true, null );
$k = knock();
ck( 'another account is not held back by the first one\'s cap', $k['wrote'], 1 );

arrive( 21, true, 'Claude - laptop', true, array( 21 ) );
$k = knock();
ck( 'and the first one, let in, writes nothing', array( $k['result'], $k['wrote'] ), array( true, 0 ) );

// The fence shares the cap with the door.
$before = row_count();
$fenced = WPCPM_MCP::fence_rest( null, null, new WP_REST_Request( '/wp/v2/users' ) );
ck( 'the fence and the door count against one cap per account: the fence\'s refusal is not written either, the cap being spent', row_count() - $before, 0 );
ck( 'and the fence still answers 403: the cap bounds the log, not the fence', array( code_of( $fenced ), $fenced->get_error_data() ), array( 'wpcpm_mcp_fenced', array( 'status' => 403 ) ) );

echo "\n=== One refused request is one row (Ruling 22) ===\n";

// Core's rest_send_allow_header() runs on rest_post_dispatch for every matched route, an error response
// included, and asks the route's permission callback once for each method the route allows. The adapter
// registers one handler for POST, GET and DELETE, so the door is asked up to four times for one request.
// Everything above ran with no doing_filter() at all, as a site without it would: the guard is
// function_exists(), and the door wrote its rows then. From here a stand-in answers for it.
ck( 'doing_filter() is not defined until here: every refusal above was written without it', function_exists( 'doing_filter' ), false );
$GLOBALS['doing'] = array(); // The hooks being run, innermost last.
if ( ! function_exists( 'doing_filter' ) ) {
	function doing_filter( $hook = null ) { return null === $hook ? array() !== $GLOBALS['doing'] : in_array( $hook, $GLOBALS['doing'], true ); }
}

/** Ask the door as `rest_send_allow_header()` does: once in dispatch, then three more times inside rest_post_dispatch. */
function ask_four_times() {
	$answers = array( WPCPM_MCP::permit( new WP_REST_Request( '/mcp/wpcredits' ) ) );

	$GLOBALS['doing'] = array( 'rest_post_dispatch' );

	for ( $n = 0; $n < 3; $n++ ) {
		$answers[] = WPCPM_MCP::permit( new WP_REST_Request( '/mcp/wpcredits' ) );
	}

	$GLOBALS['doing'] = array();

	return $answers;
}

arrive( 81, true, null );
$before  = row_count();
$answers = ask_four_times();
ck( 'a refused signed-in request, asked once in dispatch and three more times on rest_post_dispatch: refused all four times, and one row', array( $answers, row_count() - $before ), array( array( false, false, false, false ), 1 ) );
ck( 'the row is the first ask\'s, and the refusal cap took one place, not four', array( last_row()['data']['reason'], WPCPM_Ceiling::count( WPCPM_Ceiling::key( 'mcp-refusals', 81 ), HOUR_IN_SECONDS ) ), array( 'wrong_password', 1 ) );

// The refusal itself is unchanged inside rest_post_dispatch: the same error, on every ground.
foreach ( array(
	'a browser session'                 => array( 82, true, null, true, array( 82 ), 'wrong_password' ),
	'a password not named Claude'       => array( 82, true, 'Backup script', true, array( 82 ), 'wrong_password' ),
	'an account without the capability' => array( 83, false, 'Claude - laptop', true, array( 83 ), 'not_manager' ),
	'an account not on the list'        => array( 84, true, 'Claude - laptop', true, array( 7 ), 'not_allowed' ),
	'the switch off'                    => array( 85, true, 'Claude - laptop', false, array( 85 ), 'switch_off' ),
) as $label => $world ) {
	call_user_func_array( 'arrive', array_slice( $world, 0, 5 ) );
	$before           = row_count();
	$GLOBALS['doing'] = array( 'rest_post_dispatch' );
	$result           = WPCPM_MCP::door( new WP_REST_Request( '/mcp/wpcredits' ) );
	$GLOBALS['doing'] = array();

	ck( "$label, on rest_post_dispatch: still refused with the ground " . $world[5] . ', and no row', array( code_of( $result ), row_count() - $before ), array( $world[5], 0 ) );
}

// The same inside a hook that is not rest_post_dispatch, and nested in one: the row is written unless the
// hook being run is rest_post_dispatch itself.
arrive( 86, true, null );
$before           = row_count();
$GLOBALS['doing'] = array( 'rest_pre_dispatch' );
WPCPM_MCP::permit( new WP_REST_Request( '/mcp/wpcredits' ) );
$GLOBALS['doing'] = array( 'init', 'rest_api_init' );
WPCPM_MCP::permit( new WP_REST_Request( '/mcp/wpcredits' ) );
$GLOBALS['doing'] = array( 'rest_post_dispatch', 'rest_send_allow_header' );
WPCPM_MCP::permit( new WP_REST_Request( '/mcp/wpcredits' ) );
$GLOBALS['doing'] = array();
ck( 'a refusal in another hook writes its row, and one nested inside rest_post_dispatch does not', row_count() - $before, 2 );

// The cap and the other writers are untouched.
arrive( 87, true, null );
$before  = row_count();
$answers = array();
foreach ( range( 1, 20 ) as $n ) {
	$answers[] = ask_four_times();
}
ck( '20 refused requests, each asked four times: 80 refusals, 20 rows and 20 places of the cap, one of each per request, well under the cap', array( count( array_filter( array_merge( ...$answers ), static function ( $answer ) { return false === $answer; } ) ), row_count() - $before, WPCPM_Ceiling::count( WPCPM_Ceiling::key( 'mcp-refusals', 87 ), HOUR_IN_SECONDS ) ), array( 80, 20, 20 ) );
foreach ( range( 1, 50 ) as $n ) {
	ask_four_times();
}
ck( 'and 70 of them write 60 rows: the cap holds as it did', array( row_count() - $before, WPCPM_Ceiling::count( WPCPM_Ceiling::key( 'mcp-refusals', 87 ), HOUR_IN_SECONDS ) ), array( 60, 60 ) );

arrive( 88, true, 'Claude - laptop', true, array( 88 ) );
$before           = row_count();
$GLOBALS['doing'] = array( 'rest_post_dispatch' );
$fenced           = fence( '/wp/v2/users' );
$GLOBALS['doing'] = array();
ck( 'the fence is not the door: it writes its row whatever hook is being run', array( code_of( $fenced ), row_count() - $before ), array( 'wpcpm_mcp_fenced', 1 ) );

$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mcp.php' );
ck( 'and the guard is function_exists() before doing_filter(), so a site without the function writes its rows as before', 1 === preg_match( "/function_exists\( 'doing_filter' \) && doing_filter\( 'rest_post_dispatch' \)/", $source ), true );

echo "\n=== The REST fence (Review Focus 2 and 5) ===\n";

/** Dispatch as the fence is called: the result so far, the server, the request. */
function fence( $route, $result = null ) {
	return WPCPM_MCP::fence_rest( $result, new stdClass(), new WP_REST_Request( $route ) );
}

foreach ( array( true, false ) as $on ) {
	$state = $on ? 'on' : 'off';

	arrive( 30 + (int) $on, true, 'Claude - laptop', $on, array( 30, 31 ) );

	foreach ( array( '/wp/v2/users', '/wp/v2/plugins', '/wp/v2/settings', '/batch/v1' ) as $route ) {
		$before = row_count();
		$result = fence( $route );

		ck( "switch $state: a Claude password on $route is fenced: wpcpm_mcp_fenced, 403, the fixed sentence", array(
			code_of( $result ),
			is_wp_error( $result ) ? $result->get_error_data() : null,
			is_wp_error( $result ) ? $result->get_error_message() : null,
		), array( 'wpcpm_mcp_fenced', array( 'status' => 403 ), 'This password opens only the WPCredits MCP address.' ) );

		$row = last_row();
		ck( "switch $state: and logged as fenced, with the route and the password, as a refusal with no ability", array( row_count() - $before, $row['data']['reason'], $row['data']['route'], $row['data']['outcome'], $row['data']['ability'], $row['data']['password'], $row['data']['password_id'], $row['actor'] ), array( 1, 'fenced', $route, 'refused', '', 'Claude - laptop', $UUID, 30 + (int) $on ) );
	}

	foreach ( array( '/mcp/wpcredits', '/mcp/wpcredits/' ) as $route ) {
		$before = row_count();
		ck( "switch $state: the MCP address $route passes the fence: null back, no row", array( fence( $route ), row_count() - $before ), array( null, 0 ) );
	}
}

echo "\n=== Routes that only look like the address are not the address ===\n";

arrive( 33, true, 'Claude - laptop', true, array( 33 ) );
foreach ( array( '/mcp/wpcredits/x', '/mcp/wpcredits//', '/mcp/wpcredits/../wp/v2/users', '/MCP/wpcredits', '/mcp/WPCredits', '/mcp/wpcredits2', '/mcp/wpcreditsx', '/mcp/wpcredit', '//mcp/wpcredits', '/mcp//wpcredits', 'mcp/wpcredits', '/wp-json/mcp/wpcredits', '/mcp/mcp-adapter-default-server', '/mcp', '/', '', '/WP/V2/USERS', '/Wp/v2/Users/me', '/wp/v2/users/me/application-passwords', ' /mcp/wpcredits', "/mcp/wpcredits\n", '/mcp/wpcredits?x=1' ) as $route ) {
	ck( 'fenced: ' . var_export( $route, true ), code_of( fence( $route ) ), 'wpcpm_mcp_fenced' );
}
ck( 'a request that cannot say its route is fenced too', array( code_of( WPCPM_MCP::fence_rest( null, null, null ) ), code_of( WPCPM_MCP::fence_rest( null, null, 'not a request' ) ), code_of( WPCPM_MCP::fence_rest( null, null, new stdClass() ) ) ), array( 'wpcpm_mcp_fenced', 'wpcpm_mcp_fenced', 'wpcpm_mcp_fenced' ) );

echo "\n=== Everybody else passes the fence ===\n";

arrive( 34, true, 'Backup script', true, array( 34 ) );
$before = row_count();
foreach ( array( '/wp/v2/users', '/wp/v2/plugins', '/batch/v1', '/mcp/wpcredits', '/mcp/wpcredits/x' ) as $route ) {
	ck( "another password on $route passes: null back", fence( $route ), null );
}
ck( 'and writes no row', row_count() - $before, 0 );

foreach ( array( 'Claudette', 'My Claude', '' ) as $name ) {
	arrive( 34, true, $name, true, array( 34 ) );
	ck( 'a password named ' . var_export( $name, true ) . ' is not a Claude password: it passes', fence( '/wp/v2/users' ), null );
}

arrive( 34, true, null, true, array( 34 ) );
ck( 'a browser session passes: the fence is about the password only', array( fence( '/wp/v2/users' ), fence( '/batch/v1' ), fence( '/mcp/wpcredits' ) ), array( null, null, null ) );

arrive( 0, false, null, true, array() );
ck( 'and so does a request with no account', fence( '/wp/v2/users' ), null );

arrive( 35, true, "\x01Claude", true, array( 35 ) );
ck( 'a name that begins with a control character is not a Claude password, so the fence leaves it alone; it is an ordinary password, which the door refuses', array( fence( '/wp/v2/users' ), code_of( WPCPM_MCP::door( new WP_REST_Request( '/mcp/wpcredits' ) ) ) ), array( null, 'wrong_password' ) );

echo "\n=== A result already set: an error stays, a response does not serve a fenced route ===\n";

arrive( 36, true, 'Claude - laptop', true, array( 36 ) );
$earlier = new WP_Error( 'earlier', 'Set by an earlier filter.' );
$before  = row_count();
ck( 'an error set by an earlier filter comes back as it was, on a fenced route', array( fence( '/wp/v2/users', $earlier ) === $earlier, row_count() - $before ), array( true, 0 ) );
$before  = row_count();
$served  = fence( '/wp/v2/users', $response = new WP_REST_Response( array( 'users' => 'cached' ) ) );
ck( 'a response set by an earlier filter (a REST cache, say) does not serve a fenced route to a Claude password: it is replaced by the 403', array( $served === $response, code_of( $served ), is_wp_error( $served ) ? $served->get_error_data() : null ), array( false, 'wpcpm_mcp_fenced', array( 'status' => 403 ) ) );
ck( 'and the replacement is logged as the fence\'s own refusal, with the route', array( row_count() - $before, last_row()['data']['reason'], last_row()['data']['route'] ), array( 1, 'fenced', '/wp/v2/users' ) );
ck( 'so is any other result that is not an error: a string, a list with something in it', array( code_of( fence( '/wp/v2/users', 'cached' ) ), code_of( fence( '/batch/v1', array( 'x' ) ) ), code_of( fence( '/wp/v2/plugins', new stdClass() ) ) ), array_fill( 0, 3, 'wpcpm_mcp_fenced' ) );
ck( 'on the MCP address an earlier error or response still comes back as it was', array( fence( '/mcp/wpcredits', $earlier ) === $earlier, fence( '/mcp/wpcredits', $response ) === $response, fence( '/mcp/wpcredits/', $response ) === $response ), array( true, true, true ) );
ck( 'and for a password that is not Claude\'s a response comes back as it was on every route', ( static function () use ( $response ) { arrive( 36, true, 'Backup script', true, array( 36 ) ); $out = fence( '/wp/v2/users', $response ) === $response; arrive( 36, true, 'Claude - laptop', true, array( 36 ) ); return $out; } )(), true );
ck( 'a result that WordPress would still dispatch past (false, 0, an empty list, an empty string) does not stop the fence either', array( code_of( fence( '/wp/v2/users', false ) ), code_of( fence( '/wp/v2/users', 0 ) ), code_of( fence( '/wp/v2/users', array() ) ), code_of( fence( '/wp/v2/users', '' ) ) ), array_fill( 0, 4, 'wpcpm_mcp_fenced' ) );
ck( 'and on the address it comes back as it was', array( fence( '/mcp/wpcredits', false ), fence( '/mcp/wpcredits', array() ) ), array( false, array() ) );

echo "\n=== A password whose item cannot be found is fenced as if it were Claude's ===\n";

arrive( 37, true, 'Claude - laptop', true, array( 37 ) );
unset( $GLOBALS['app_items'][37] );
ck( 'an application password signed the request but its name cannot be read: another route is fenced', code_of( fence( '/wp/v2/users' ) ), 'wpcpm_mcp_fenced' );
ck( 'it is logged, with an empty name and the route', array( last_row()['data']['reason'], last_row()['data']['route'], last_row()['data']['password'] ), array( 'fenced', '/wp/v2/users', '' ) );
ck( 'the MCP address passes the fence, and the door then refuses it as wrong_password', array( fence( '/mcp/wpcredits' ), code_of( WPCPM_MCP::door( new WP_REST_Request( '/mcp/wpcredits' ) ) ) ), array( null, 'wrong_password' ) );

arrive( 39, true, 'Claude - laptop', true, array( 39 ) );
unset( $GLOBALS['app_items'][39] );
$GLOBALS['app_items'][40][ $UUID ] = array( 'uuid' => $UUID, 'name' => 'Claude - laptop' );
ck( 'a password stored under another account is not this account\'s: it is found under neither, so the fence holds it to the address and the door refuses it', array( code_of( fence( '/wp/v2/users' ) ), fence( '/mcp/wpcredits' ), code_of( WPCPM_MCP::door( new WP_REST_Request( '/mcp/wpcredits' ) ) ) ), array( 'wpcpm_mcp_fenced', null, 'wrong_password' ) );
unset( $GLOBALS['app_items'][40] );

echo "\n=== The REST fence in a request with no Application Passwords at all ===\n";

$GLOBALS['app_uuid'] = null;
arrive( 38, true, null );
ck( 'with no application password in the request the fence does nothing, however many times it is asked', array( fence( '/wp/v2/users' ), fence( '/wp/v2/users' ) ), array( null, null ) );

echo "\n=== The XML-RPC fence (Review Focus 2) ===\n";

$item_claude = array( 'uuid' => $UUID, 'name' => 'Claude - laptop', 'password' => '$P$hash' );
$item_other  = array( 'uuid' => $UUID, 'name' => 'Backup script', 'password' => '$P$hash' );

$error = new WP_Error();
WPCPM_MCP::fence_xmlrpc( $error, new stdClass(), $item_claude, 'abcd efgh' );
ck( 'with no XML-RPC request defined, a Claude password\'s sign-in gets no error', $error->has_errors(), false );
ck( 'XMLRPC_REQUEST is not defined yet', defined( 'XMLRPC_REQUEST' ), false );

define( 'XMLRPC_REQUEST', true );

$error = new WP_Error();
WPCPM_MCP::fence_xmlrpc( $error, new stdClass(), $item_claude, 'abcd efgh' );
ck( 'during an XML-RPC request, a Claude password\'s sign-in gets the error wpcpm_mcp_fenced', array( $error->get_error_codes(), $error->get_error_message() ), array( array( 'wpcpm_mcp_fenced' ), 'This password opens only the WPCredits MCP address.' ) );

foreach ( array( ' claude', 'CLAUDE - old phone' ) as $name ) {
	$error = new WP_Error();
	WPCPM_MCP::fence_xmlrpc( $error, new stdClass(), array_merge( $item_claude, array( 'name' => $name ) ), '' );
	ck( 'a password named ' . var_export( $name, true ) . ' is fenced too', $error->get_error_codes(), array( 'wpcpm_mcp_fenced' ) );
}

$error = new WP_Error();
WPCPM_MCP::fence_xmlrpc( $error, new stdClass(), $item_other, 'abcd efgh' );
ck( 'another password\'s sign-in gets none', $error->has_errors(), false );

foreach ( array( 'Claudette', 'My Claude', '' ) as $name ) {
	$error = new WP_Error();
	WPCPM_MCP::fence_xmlrpc( $error, new stdClass(), array_merge( $item_claude, array( 'name' => $name ) ), '' );
	ck( 'nor does one named ' . var_export( $name, true ), $error->has_errors(), false );
}

$error = new WP_Error();
WPCPM_MCP::fence_xmlrpc( $error, new stdClass(), array( 'uuid' => $UUID ), '' );
ck( 'an item with no name gets none (and is not a notice)', $error->has_errors(), false );

$survived = true;
try {
	WPCPM_MCP::fence_xmlrpc( null, null, null, null );
	WPCPM_MCP::fence_xmlrpc( 'not an error', null, $item_claude, null );
} catch ( Throwable $e ) {
	$survived = false;
}
ck( 'arguments that are not what core passes are ignored, not a fatal', $survived, true );

$error = new WP_Error( 'incorrect_password', 'Already failing.' );
WPCPM_MCP::fence_xmlrpc( $error, new stdClass(), $item_claude, '' );
ck( 'an error already there keeps its place and the fence adds its own', $error->get_error_codes(), array( 'incorrect_password', 'wpcpm_mcp_fenced' ) );

// Review Focus 2: the switch makes no difference to the fence.
foreach ( array( true, false ) as $on ) {
	WPCPM_Settings::patch( array( 'mcp_enabled' => $on ) );

	$claude = new WP_Error();
	$other  = new WP_Error();
	WPCPM_MCP::fence_xmlrpc( $claude, new stdClass(), $item_claude, 'abcd efgh' );
	WPCPM_MCP::fence_xmlrpc( $other, new stdClass(), $item_other, 'abcd efgh' );

	ck( 'switch ' . ( $on ? 'on' : 'off' ) . ': a Claude password\'s sign-in gets wpcpm_mcp_fenced, another password\'s gets none', array( $claude->get_error_codes(), $other->has_errors() ), array( array( 'wpcpm_mcp_fenced' ), false ) );
}

echo "\n=== permit(): the strict bool the adapter's permission callback gets ===\n";

// A WP_Error is truthy in PHP, so a callback that answers with one is only as safe as the adapter's reading of it.
// The adapter is handed permit(), which says yes with true and anything else with false.
$cases = array(
	array( 'no account', array( 0, false, 'Claude - laptop', true, array( 61 ) ), false, 0 ),
	array( 'a browser session', array( 62, true, null, true, array( 62 ) ), false, 1 ),
	array( 'a password not named Claude', array( 63, true, 'Backup script', true, array( 63 ) ), false, 1 ),
	array( 'an account without the capability', array( 64, false, 'Claude - laptop', true, array( 64 ) ), false, 1 ),
	array( 'an Administrator not on the list', array( 65, true, 'Claude - laptop', true, array( 61 ) ), false, 1 ),
	array( 'the switch off', array( 66, true, 'Claude - laptop', false, array( 66 ) ), false, 1 ),
	array( 'an Administrator on the list with a Claude password, the switch on', array( 67, true, 'Claude - laptop', true, array( 67 ) ), true, 0 ),
	array( 'the same with the name " claude - laptop"', array( 68, true, ' claude - laptop', true, array( 68 ) ), true, 0 ),
);

foreach ( $cases as $case ) {
	call_user_func_array( 'arrive', $case[1] );
	$before = row_count();
	$answer = WPCPM_MCP::permit( new WP_REST_Request( '/mcp/wpcredits' ) );

	ck( 'permit(): ' . $case[0] . ' is exactly ' . var_export( $case[2], true ) . ', never an error', $answer, $case[2] );
	ck( 'and writes what the door writes: ' . $case[3] . ' row(s)', row_count() - $before, $case[3] );
}

arrive( 69, true, 'Claude - laptop', true, array( 69 ) );
ck( 'permit() takes a request, or none, or anything', array( WPCPM_MCP::permit(), WPCPM_MCP::permit( null ), WPCPM_MCP::permit( 'x' ) ), array( true, true, true ) );

$GLOBALS['grants'] = new Throwing_Grants();
$thrown            = null;
try {
	$answer = WPCPM_MCP::permit( new WP_REST_Request( '/mcp/wpcredits' ) );
} catch ( Throwable $e ) {
	$thrown = $e;
	$answer = null;
}
unset( $GLOBALS['grants'] );
ck( 'a capability check that throws: permit() says false and nothing escapes', array( null === $thrown, $answer ), array( true, false ) );

$GLOBALS['throw_option'] = WPCPM_Settings::OPT_NAME;
$thrown                  = null;
try {
	$answer = WPCPM_MCP::permit( new WP_REST_Request( '/mcp/wpcredits' ) );
} catch ( Throwable $e ) {
	$thrown = $e;
	$answer = null;
}
$GLOBALS['throw_option'] = null;
ck( 'a settings read that throws: false, and nothing escapes', array( null === $thrown, $answer ), array( true, false ) );

arrive( 70, true, 'Backup script', true, array( 70 ) );
$GLOBALS['insert_throws'] = true;
$thrown                   = null;
try {
	$answer = WPCPM_MCP::permit( new WP_REST_Request( '/mcp/wpcredits' ) );
} catch ( Throwable $e ) {
	$thrown = $e;
	$answer = null;
}
$GLOBALS['insert_throws'] = false;
ck( 'a row that cannot be written: still false, and nothing escapes', array( null === $thrown, $answer ), array( true, false ) );

// A translation filter belongs to other code and can throw too: the refusal stands, with no message.
$thrown = null;
$out    = array();
foreach ( array( array( 71, true, 'Backup script' ), array( 0, false, null ) ) as $world ) {
	arrive( $world[0], $world[1], $world[2], true, array( 71 ) );
	$GLOBALS['translate_throws'] = true;
	try {
		$out[] = array( WPCPM_MCP::permit( new WP_REST_Request( '/mcp/wpcredits' ) ), code_of( WPCPM_MCP::door( new WP_REST_Request( '/mcp/wpcredits' ) ) ) );
	} catch ( Throwable $e ) {
		$thrown = $e;
	}
	$GLOBALS['translate_throws'] = false;
}
ck( 'a translation that throws while the door refuses: permit() says false and nothing escapes; the door still answers, with the ground (or error, for a request with no account, whose sentence is built inside the checks)', array( null === $thrown, $out ), array( true, array( array( false, 'wrong_password' ), array( false, 'error' ) ) ) );

arrive( 69, true, 'Claude - laptop', true, array( 69 ) );
ck( 'and permit() says true again when the checks work', WPCPM_MCP::permit(), true );

echo "\n=== The file loads without the adapter ===\n";

$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mcp.php' );

ck( 'the adapter\'s classes are strings: no use statement and no ::class of a WP\MCP name', array( preg_match( '/^use\s/m', $source ), preg_match( '/WP\\\\MCP\\\\[A-Za-z\\\\]+::class/', $source ), preg_match( '/new\s+\\\\?WP\\\\MCP/', $source ) ), array( 0, 0, 0 ) );
ck( 'and the transport and the error handler are named in it', array( false !== strpos( $source, "'WP\\MCP\\Transport\\HttpTransport'" ), false !== strpos( $source, "'WP\\MCP\\Infrastructure\\ErrorHandling\\ErrorLogMcpErrorHandler'" ) ), array( true, true ) );
// The code alone: the class's comments name the audit writer to say that the abilities' class is the one that calls it.
$code = '';
foreach ( token_get_all( $source ) as $token ) {
	if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
		continue;
	}
	$code .= is_array( $token ) ? $token[1] : $token;
}
ck( 'the class builds no log row itself: every refusal goes through the abilities\' refusal(), and the audit writer is not called here', array( false !== strpos( $code, 'WPCPM_Abilities::refusal(' ), false !== strpos( $code, 'record_mcp' ), false !== strpos( $code, 'WPCPM_Institution_Audit::record' ) ), array( true, false, false ) );

echo "\n=== House rules ===\n";

$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mcp.php' );

ck( 'no em or en dash in the class', preg_match( '/\x{2013}|\x{2014}/u', $source ), 0 );
ck( 'and nothing in it reaches another address: no wp_remote call and no Airtable class', array( false !== strpos( $source, 'wp_remote_' ), false !== stripos( $source, 'WPCPM_Airtable' ) ), array( false, false ) );

printf( "\n%s\n", $fail ? "$fail FAILED" : 'ALL PASS' );
exit( $fail ? 1 : 0 );
