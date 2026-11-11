<?php
/**
 * Quiet students, the daily job and the switch (student automation, first line).
 *
 * What this pins, and why:
 *
 * - A student is quiet at the set number of days (14 by default, the setting's value otherwise):
 *   one row at 15 days, none at 13, none at 15 when the setting says 20.
 * - Only followed students are listed: an active account on a track. Graduate, Paused, Pending
 *   graduation and Dropped out students, and an account with no program row, are never listed.
 * - The program manager comes from the institution's country contact, and only when that address
 *   belongs to an account that may manage the program; any break in the chain gives 0.
 * - A student of a test institution is listed and marked, and never counts toward a reminder.
 * - Off writes nothing; Preview writes the snapshot and notes on each row whether a reminder would
 *   go out (real student, not reminded for this spell) and mails nobody.
 * - The on-since time is the latest move into On (final review ruling): Preview or Off to On writes
 *   it, a save that leaves the switch On keeps it; a stretch may be mailed only when it reached the
 *   threshold at or after it (`reached_since_on()`). Saving Off deletes the stored list.
 * - A student who signed in before a track that starts later, one back on an old account whose new
 *   track started since, and one with a live call or group session booked ahead are not quiet; a
 *   trashed call ahead does not count and a held call counts at its start. rows() reads each
 *   student's activity once. After last activity moves back, the row keeps the stretch's reminder.
 * - "We've already talked" drops one student's row from the snapshot at once.
 * - The press is allowed for the student, their mentor and an Administrator, refused for any
 *   other mentor and for a bad nonce; it writes the talked stamp and drops the snapshot row.
 * - Release B: a row's reminder is its stretch's while no activity came after the reminder went; a
 *   student who is their own mentor has none; a never-seen row says where its clock started; a press
 *   leaves a one-shot line for its page; the form prints its nonce without core's repeated id; and
 *   the three senders are private, reached through the daily job.
 *
 * Run from the plugin root:  php bin/test-student-flags.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['umeta']    = array();
$GLOBALS['opts']     = array();
$GLOBALS['users']    = array();   // id => stdClass( ID, user_registered, user_email, display_name, caps ).
$GLOBALS['programs'] = array();
$GLOBALS['mentors']  = array();
$GLOBALS['settings'] = array();
$GLOBALS['hooks']    = array();
$GLOBALS['cron']     = array();
$GLOBALS['mails']    = array();

function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $callback; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['opts'][ $key ] = $value; $GLOBALS['autoload'][ $key ] = $autoload; return true; }
function delete_option( $key ) { unset( $GLOBALS['opts'][ $key ] ); return true; }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['umeta'][ $id ][ $key ] = $value; return true; }
function get_userdata( $id ) { return isset( $GLOBALS['users'][ $id ] ) ? $GLOBALS['users'][ $id ] : false; }
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( ( 'email' === $field && $user->user_email === $value ) || ( 'id' === $field && $user->ID === (int) $value ) ) {
			return $user;
		}
	}
	return false;
}
function user_can( $user, $cap ) {
	$user = is_object( $user ) ? $user : get_userdata( (int) $user );
	return $user && in_array( $cap, $user->caps, true );
}
function get_users( $args ) {
	$ids = array();
	foreach ( $GLOBALS['users'] as $user ) {
		if ( in_array( $args['role'], $user->roles, true ) ) {
			$ids[] = $user->ID;
		}
	}
	return $ids;
}
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ]['at'] : false; }
function wp_schedule_event( $at, $recurrence, $hook ) { $GLOBALS['cron'][ $hook ] = array( 'at' => $at, 'recurrence' => $recurrence ); return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ] ); }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function current_user_can( $cap ) { return isset( $GLOBALS['users'][ $GLOBALS['uid'] ] ) && user_can( $GLOBALS['users'][ $GLOBALS['uid'] ], $cap ); }
// As core's: the nonce is read from $_REQUEST, which WordPress builds as GET merged with POST. The press() helper below
// sets a flag instead and compares the action, which is all most checks here need.
function check_admin_referer( $action ) {
	if ( ! empty( $GLOBALS['nonce_from_request'] ) ) {
		if ( ! isset( $_REQUEST['_wpnonce'] ) || wp_create_nonce( $action ) !== $_REQUEST['_wpnonce'] ) {
			throw new Exception( 'nonce' );
		}
		return 1;
	}
	if ( empty( $GLOBALS['nonce_ok'] ) || $GLOBALS['nonce_ok'] !== $action ) { throw new Exception( 'nonce' ); }
}
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }
function wp_die( $message = '', $code = 0 ) { throw new Exception( 'die' . $code ); }
function wp_safe_redirect( $url ) { throw new Exception( 'redirect:' . $url ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
// As core prints them: the nonce field carries an id, the same on every form of a page, and a referer field.
function wp_nonce_field( $action ) { echo '<input type="hidden" id="_wpnonce" name="_wpnonce" value="nonce-' . $action . '" /><input type="hidden" name="_wp_http_referer" value="/here/" />'; }
function wp_create_nonce( $action ) { return 'nonce-' . $action; }
function wp_referer_field( $display = true ) { $field = '<input type="hidden" name="_wp_http_referer" value="/here/" />'; if ( $display ) { echo $field; } return $field; }
function wp_cache_delete( $key, $group = '' ) { return true; }
function wp_cache_set_posts_last_changed() {}
function maybe_unserialize( $data ) { return is_string( $data ) && preg_match( '/^(a|s|i|b|d|N):/', $data ) ? unserialize( $data ) : $data; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s ); }
function esc_attr__( $s ) { return htmlspecialchars( $s ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s ); }
function esc_url( $s ) { return (string) $s; }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, (int) $timestamp ); }
function wp_mail() { $GLOBALS['mails'][] = func_get_args(); return true; }

// Stand-ins for the classes whose answers the flags read.
class WPCPM_Flash {
	public static function set( $channel, $value, $user_id = 0 ) { $GLOBALS['flash'][ $channel ] = $value; }
	public static function take( $channel, $user_id = 0 ) { return isset( $GLOBALS['flash'][ $channel ] ) ? $GLOBALS['flash'][ $channel ] : ''; }
}
class WPCPM_Lock {
	public static function take( $name, $ttl ) { return '123'; }
	public static function release( $name, $token ) { return true; }
}
class WPCPM_Settings {
	const OPT_NAME = 'wpcpm_settings';
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}
class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student';
	const CAP_MANAGE   = 'wpcpm_manage_program';
	public static function id_of( $entry ) { return is_object( $entry ) ? (int) $entry->ID : (int) $entry; }
}
class WPCPM_Request {
	public static function posted_id( $name ) { return isset( $_POST[ $name ] ) ? (int) $_POST[ $name ] : 0; }
	public static function posted_key( $name, $fallback = '' ) { return isset( $_POST[ $name ] ) ? (string) $_POST[ $name ] : $fallback; }
	public static function back( ...$fallbacks ) { return isset( $GLOBALS['referer'] ) ? $GLOBALS['referer'] : (string) reset( $fallbacks ); }
}
class WPCPM_Return {
	const FIELD     = 'wpcpm_return';
	const DASHBOARD = 'dashboard';
	public static function field( $where, $anchor = '' ) { echo '<input type="hidden" name="wpcpm_return" value="' . $where . '" /><input type="hidden" name="wpcpm_return_to" value="' . $anchor . '" />'; }
	public static function url( $default ) { return 'https://example.test/admin/#wpcpm-' . $_POST['wpcpm_return_to']; }
}
class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student/'; }
	public static function is_student( $id ) { return isset( $GLOBALS['users'][ $id ] ) && in_array( WPCPM_Roles::ROLE_STUDENT, $GLOBALS['users'][ $id ]->roles, true ); }
}
class WPCPM_Students_Sync {
	const META_RECORD_ID   = 'wpcpm_student_record_id';
	const META_ACTIVE      = 'wpcpm_student_active';
	const META_INSTITUTION = 'wpcpm_student_institution';
	public static function get_program( $id ) { return isset( $GLOBALS['programs'][ $id ] ) ? $GLOBALS['programs'][ $id ] : array(); }
	public static function status_of( $program ) { return isset( $program['program'] ) ? trim( $program['program'] ) : ''; }
}
class WPCPM_Program {
	public static function track( $status ) { return in_array( $status, array( 'In Sensei', 'In Sensei 50h', 'Developer Track' ), true ) ? 'track' : ''; }
}
class WPCPM_Mentor_Calls {
	// Every call of the student, as the real query gives them with $upcoming false; the flags must
	// read a call's start themselves to tell one still ahead. Each read is counted.
	public static function for_student( $id, $upcoming = true ) { $GLOBALS['call_reads'][] = array( $id, $upcoming ); return isset( $GLOBALS['calls'][ $id ] ) ? $GLOBALS['calls'][ $id ] : array(); }
	public static function details( $call ) { return array( 'booked' => $call->booked, 'start' => $call->start ); }
	public static function mentor_for_student( $id ) { return isset( $GLOBALS['mentors'][ $id ] ) ? get_userdata( $GLOBALS['mentors'][ $id ] ) : null; }
}
class WPCPM_Sign_Ins {
	const META_SIGN_IN = 'wpcpm_last_sign_in';
	const META_VISIT   = 'wpcpm_last_visit';
}

// The institutions and countries of the seed fixture: an institution's country, a country's contact.
$seed = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'bin/fixtures/institutions-index-seed.json' ), true );
$GLOBALS['seed_inst']      = array();
$GLOBALS['seed_countries'] = array();
foreach ( $seed['institutions'] as $row ) {
	$GLOBALS['seed_inst'][ $row['id'] ] = array( 'record_id' => $row['id'], 'name' => $row['name'], 'country' => isset( $row['country'][0] ) ? $row['country'][0] : '' );
}
foreach ( $seed['countries'] as $row ) {
	$GLOBALS['seed_countries'][ $row['id'] ] = $row;
}
class WPCPM_Institutions_Index {
	public static function row( $id ) { return isset( $GLOBALS['seed_inst'][ $id ] ) ? $GLOBALS['seed_inst'][ $id ] : null; }
}
class WPCPM_Countries {
	public static function routing( $id ) {
		if ( empty( $GLOBALS['seed_countries'][ $id ]['has_email'] ) ) {
			return null;
		}
		return array( 'name' => $GLOBALS['seed_countries'][ $id ]['name'], 'manager' => '', 'email' => 'contact-' . $id . '@example.test', 'calendly' => isset( $GLOBALS['calendly'] ) ? $GLOBALS['calendly'] : '' );
	}
}
class WPCPM_Test_Institutions {
	public static function is_test( $id ) { return isset( $GLOBALS['seed_inst'][ $id ] ) && 0 === strpos( $GLOBALS['seed_inst'][ $id ]['name'], 'TEST' ); }
}

// The Administrator an institution is assigned to (Institutions off Airtable, R1): its record, at the one read the
// assignment makes, and the booking link on the Administrator's own account.
// An account as WordPress holds it: the assignment reads a `WP_User`.
class WP_User {
	public $ID = 0, $user_email = '', $user_registered = '', $display_name = '', $user_login = '', $roles = array(), $caps = array();
	public function __construct( $id, $email, $name, $caps ) { $this->ID = $id; $this->user_email = $email; $this->display_name = $name; $this->user_login = $name; $this->caps = $caps; }
}
class WPCPM_Institution_Record {
	public static function get( $record_id ) { return isset( $GLOBALS['kept'][ $record_id ] ) ? $GLOBALS['kept'][ $record_id ] : array(); }
}
class WPCPM_Manager_Booking {
	public static function url_for( $user_id ) { return isset( $GLOBALS['booking'][ (int) $user_id ] ) ? $GLOBALS['booking'][ (int) $user_id ] : ''; }
}
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-assignment.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-activity.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-flags.php';

$fail = 0;
function ck( $label, $actual, $expected = true ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		++$fail;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
	}
}

$T = 1800000000;
$D = DAY_IN_SECONDS;

function reset_all() {
	$GLOBALS['umeta']      = array();
	$GLOBALS['opts']       = array();
	$GLOBALS['users']      = array();
	$GLOBALS['programs']   = array();
	$GLOBALS['mentors']    = array();
	$GLOBALS['calls']      = array();
	$GLOBALS['call_reads'] = array();
	$GLOBALS['settings']   = array( 'student_flags_mode' => 'preview', 'student_quiet_days' => 14 );
	$GLOBALS['cron']       = array();
	$GLOBALS['mails']      = array();
	$GLOBALS['uid']        = 0;
	$GLOBALS['nonce_ok']   = '';
	$GLOBALS['flash']      = array();
	unset( $GLOBALS['calendly'] );
	$GLOBALS['kept']    = array();
	$GLOBALS['booking'] = array();
	unset( $GLOBALS['referer'] );
	$_POST = array();
}

/**
 * A followed student whose last sign-in was $quiet days before $T. Returns the user ID.
 */
function student( $id, $quiet, $status = 'In Sensei', $institution = '', $active = 1 ) {
	global $T, $D;
	$GLOBALS['users'][ $id ] = (object) array(
		'ID'              => $id,
		'user_registered' => gmdate( 'Y-m-d H:i:s', $T - 400 * $D ),
		'user_email'      => 'student' . $id . '@example.test',
		'display_name'    => 'Student ' . $id,
		'roles'           => array( WPCPM_Roles::ROLE_STUDENT ),
		'caps'            => array(),
	);
	$GLOBALS['umeta'][ $id ] = array(
		WPCPM_Sign_Ins::META_SIGN_IN              => (string) ( $T - $quiet * $D ),
		WPCPM_Students_Sync::META_ACTIVE          => $active,
		WPCPM_Students_Sync::META_RECORD_ID       => 'recSTUDENT' . str_pad( (string) $id, 5, '0', STR_PAD_LEFT ),
	);
	if ( '' !== $institution ) {
		$GLOBALS['umeta'][ $id ][ WPCPM_Students_Sync::META_INSTITUTION ] = $institution;
	}
	$GLOBALS['programs'][ $id ] = array( 'program' => $status, 'name' => 'Student ' . $id );
	return $id;
}

function ids_of( $rows ) { return array_map( static function ( $row ) { return $row['user_id']; }, $rows ); }
function call( $booked, $start, $status = 'private' ) { return (object) array( 'post_status' => $status, 'booked' => $booked, 'start' => $start ); }

// A real institution, and the test institution of the seed.
$real_inst = 'recSEED0000000002';
$test_inst = 'recSEED0000000001';

echo "=== The quiet threshold ===\n";
reset_all();
student( 1, 15 );
$rows = WPCPM_Student_Flags::rows( $T );
ck( 'quiet_after_14: a followed student with 15 quiet days is one row', array( count( $rows ), $rows[0]['user_id'], $rows[0]['days'], $rows[0]['last_kind'] ), array( 1, 1, 15, 'sign_in' ) );

reset_all();
student( 1, 13 );
ck( 'not_quiet_at_13: none', WPCPM_Student_Flags::rows( $T ), array() );

reset_all();
$GLOBALS['settings']['student_quiet_days'] = 20;
student( 1, 15 );
ck( 'days_setting_used: 15 days is not quiet when the setting says 20', WPCPM_Student_Flags::rows( $T ), array() );
student( 2, 21 );
ck( 'and 21 days is', ids_of( WPCPM_Student_Flags::rows( $T ) ), array( 2 ) );

reset_all();
student( 1, 15 );
student( 2, 40 );
student( 3, 20 );
ck( 'rows are the most quiet first', ids_of( WPCPM_Student_Flags::rows( $T ) ), array( 2, 3, 1 ) );

echo "\n=== Who is followed ===\n";
reset_all();
student( 1, 30, 'Graduate' );
student( 2, 30, 'Paused' );
student( 3, 30, 'Pending graduation' );
student( 4, 30, 'Dropped out' );
ck( 'closed_status_not_followed: Graduate, Paused, Pending graduation and Dropped out give no rows', WPCPM_Student_Flags::rows( $T ), array() );

reset_all();
student( 1, 30 );
unset( $GLOBALS['programs'][1] );
ck( 'no_program_row_not_followed: an account with no program row', array( WPCPM_Student_Flags::is_followed( 1 ), WPCPM_Student_Flags::rows( $T ) ), array( false, array() ) );

reset_all();
student( 1, 30, 'In Sensei', '', 0 );
student( 2, 30 );
ck( 'an inactive account is not followed, an active one is', array( WPCPM_Student_Flags::is_followed( 1 ), WPCPM_Student_Flags::is_followed( 2 ), WPCPM_Student_Flags::is_followed( 99 ) ), array( false, true, false ) );

echo "\n=== A start still ahead, a new track, a call booked ahead (final review) ===\n";
reset_all();
student( 1, 20 ); // Signed in once, when the account was made 20 days ago; the track starts in 3 days.
$GLOBALS['users'][1]->user_registered = gmdate( 'Y-m-d H:i:s', $T - 20 * $D );
$GLOBALS['programs'][1]['start']      = gmdate( 'Y-m-d', $T + 3 * $D );
student( 2, 200 ); // Back on an old account: the new track started 2 days ago.
$GLOBALS['programs'][2]['start'] = gmdate( 'Y-m-d', $T - 2 * $D );
student( 3, 20 ); // Quiet for 20 days, no start date: the one row.
ck( 'track_start_not_flagged: a student who signed in before a start still ahead, and one back on an old account whose new track started 2 days ago, are not quiet', ids_of( WPCPM_Student_Flags::rows( $T ) ), array( 3 ) );

reset_all();
student( 1, 20 );
$GLOBALS['calls'][1] = array( call( $T - 16 * $D, $T + 10 * $D ) ); // Booked 16 days ago, for 10 days ahead.
student( 2, 20 );
$GLOBALS['calls'][2] = array( call( $T - 16 * $D, $T + 10 * $D, 'trash' ) ); // The same, canceled.
student( 3, 30 );
$GLOBALS['calls'][3] = array( call( $T - 25 * $D, $T - 20 * $D ) ); // Held 20 days ago.
$rows = WPCPM_Student_Flags::rows( $T );
ck( 'call_ahead_not_quiet: a call or group session booked 16 days ago for 10 days ahead keeps the student off the list', in_array( 1, ids_of( $rows ), true ), false );
ck( 'a trashed call ahead does not, and a call already held counts at its start', array( ids_of( $rows ), $rows[1]['last_kind'], $rows[1]['days'] ), array( array( 2, 3 ), 'call', 20 ) );
ck( 'has_call_ahead answers for a live call that has not started only', array( WPCPM_Student_Flags::has_call_ahead( 1, $T ), WPCPM_Student_Flags::has_call_ahead( 2, $T ), WPCPM_Student_Flags::has_call_ahead( 3, $T ), WPCPM_Student_Flags::has_call_ahead( 4, $T ) ), array( true, false, false, false ) );

reset_all();
student( 1, 20 );
student( 2, 30 );
student( 3, 5 );
WPCPM_Student_Flags::rows( $T );
$activity_reads = array();
foreach ( $GLOBALS['call_reads'] as $read ) {
	if ( false === $read[1] ) {
		$activity_reads[] = $read[0];
	}
}
ck( 'rows_reads_activity_once: rows() reads each followed student\'s last activity once, quiet or not', array_count_values( $activity_reads ), array( 1 => 1, 2 => 1, 3 => 1 ) );

echo "\n=== The program manager ===\n";
reset_all();
$country = $GLOBALS['seed_inst'][ $real_inst ]['country'];
$email   = 'contact-' . $country . '@example.test';
student( 1, 30, 'In Sensei', $real_inst );
// The Countries table names account 51 for the country; the institution is assigned to account 50.
$GLOBALS['users'][50] = new WP_User( 50, 'assigned@example.test', 'Assigned', array( WPCPM_Roles::CAP_MANAGE ) );
$GLOBALS['users'][51] = (object) array( 'ID' => 51, 'user_email' => $email, 'user_registered' => '', 'display_name' => 'Country Contact', 'roles' => array(), 'caps' => array( WPCPM_Roles::CAP_MANAGE ) );
$GLOBALS['kept'][ $real_inst ] = array( 'assigned' => 50 );
ck( 'manager_by_assignee: the Administrator the student\'s institution is assigned to, not the country\'s contact', WPCPM_Student_Flags::manager_of( 1 ), 50 );
$GLOBALS['users'][50]->caps = array();
ck( 'an assignee who may no longer manage the program gives 0, and the country\'s contact is not the fallback', WPCPM_Student_Flags::manager_of( 1 ), 0 );
$GLOBALS['users'][50]->caps = array( WPCPM_Roles::CAP_MANAGE );
unset( $GLOBALS['users'][50] );
ck( 'an assignee whose account is gone gives 0', WPCPM_Student_Flags::manager_of( 1 ), 0 );
$GLOBALS['kept'] = array();
ck( 'an institution nobody is assigned to gives 0, whoever the Countries table names', WPCPM_Student_Flags::manager_of( 1 ), 0 );
student( 2, 30 );
ck( 'a student with no institution gives 0', WPCPM_Student_Flags::manager_of( 2 ), 0 );
student( 3, 30, 'In Sensei', 'recSEED9999999999' );
ck( 'an institution the index does not hold gives 0', WPCPM_Student_Flags::manager_of( 3 ), 0 );

echo "\n=== Test institutions ===\n";
reset_all();
$GLOBALS['seed_inst'][ $test_inst ]['name'] = 'TEST Institution';
student( 1, 30, 'In Sensei', $test_inst );
student( 2, 30, 'In Sensei', $real_inst );
$by_id = array();
foreach ( WPCPM_Student_Flags::rows( $T ) as $row ) {
	$by_id[ $row['user_id'] ] = $row;
}
ck( 'test_institution_marked: a test institution\'s student is listed with test true, a real one false', array( $by_id[1]['test'], $by_id[2]['test'] ), array( true, false ) );

echo "\n=== The switch and the daily job ===\n";
reset_all();
$GLOBALS['settings']['student_flags_mode'] = 'off';
student( 1, 30 );
WPCPM_Student_Flags::run_daily( $T );
ck( 'off_writes_nothing: mode off leaves the snapshot unset', array_key_exists( WPCPM_Student_Flags::OPT_SNAPSHOT, $GLOBALS['opts'] ), false );
ck( 'and the snapshot reads empty', WPCPM_Student_Flags::snapshot(), array( 'at' => 0, 'rows' => array() ) );

reset_all();
student( 1, 30, 'In Sensei', $real_inst );
student( 2, 30, 'In Sensei', $test_inst );
student( 3, 30, 'In Sensei', $real_inst );
student( 4, 30, 'In Sensei', $real_inst );
$GLOBALS['umeta'][3][ WPCPM_Student_Flags::META_REMINDED ] = array( 'for' => $T - 30 * $D, 'at' => $T - 29 * $D );
$GLOBALS['umeta'][4][ WPCPM_Student_Flags::META_REMINDED ] = array( 'for' => $T - 50 * $D, 'at' => $T - 49 * $D );
WPCPM_Student_Flags::run_daily( $T );
$snapshot = WPCPM_Student_Flags::snapshot();
$by_id    = array();
foreach ( $snapshot['rows'] as $row ) {
	$by_id[ $row['user_id'] ] = $row;
}
ck( 'preview_snapshot_and_would_remind: the snapshot is written with its time and every quiet student', array( $snapshot['at'], count( $snapshot['rows'] ), $GLOBALS['autoload'][ WPCPM_Student_Flags::OPT_SNAPSHOT ] ), array( $T, 4, false ) );
ck( 'a real student not yet reminded would be reminded', $by_id[1]['would_remind'], true );
ck( 'a test institution\'s student would not', $by_id[2]['would_remind'], false );
ck( 'a student already reminded for this spell would not', array( $by_id[3]['would_remind'], $by_id[3]['reminded_at'] ), array( false, $T - 29 * $D ) );
ck( 'a student reminded for an earlier spell would be again', $by_id[4]['would_remind'], true );
ck( 'and nothing is mailed', $GLOBALS['mails'], array() );
ck( 'the row has exactly the shape the card and the mail read', array_keys( $by_id[1] ),
	array( 'user_id', 'record', 'name', 'days', 'last_at', 'last_kind', 'mentor_id', 'manager_id', 'test', 'reminded_at', 'would_remind', 'start_from' ) );

reset_all();
$GLOBALS['settings']['student_flags_mode'] = 'on';
student( 1, 30, 'In Sensei', $real_inst );
WPCPM_Student_Flags::run_daily( $T );
ck( 'in on mode the snapshot stores would_remind false: the sending decides for itself (bin/test-student-reminders.php)', WPCPM_Student_Flags::snapshot()['rows'][0]['would_remind'], false );

reset_all();
$GLOBALS['mentors'][1] = 77;
$GLOBALS['users'][77]  = (object) array( 'ID' => 77, 'user_email' => 'mentor@example.test', 'user_registered' => '', 'display_name' => 'Mentor', 'roles' => array(), 'caps' => array() );
student( 1, 30 );
ck( 'a student\'s mentor is on the row, optional: 0 when there is none', array( WPCPM_Student_Flags::rows( $T )[0]['mentor_id'], ( function () use ( $T ) { unset( $GLOBALS['mentors'][1] ); return WPCPM_Student_Flags::rows( $T )[0]['mentor_id']; } )() ), array( 77, 0 ) );

reset_all();
student( 1, 30 );
$spell = (int) ( $T - 30 * $D );
$GLOBALS['umeta'][1][ WPCPM_Student_Flags::META_REMINDED ] = array( 'for' => $spell - 20 * $D, 'at' => $spell - 19 * $D );
$old = WPCPM_Student_Flags::rows( $T )[0];
ck( 'a reminder of an earlier spell is not this spell\'s: reminded_at 0, would_remind true in preview', array( $old['reminded_at'], $old['would_remind'] ), array( 0, true ) );
$GLOBALS['umeta'][1][ WPCPM_Student_Flags::META_REMINDED ] = array( 'for' => $spell, 'at' => $spell + 14 * $D );
$cur = WPCPM_Student_Flags::rows( $T )[0];
ck( 'a reminder of this spell is on the row', array( $cur['reminded_at'], $cur['would_remind'] ), array( $spell + 14 * $D, false ) );

reset_all();
student( 1, 30 );
$GLOBALS['umeta'][1][ WPCPM_Student_Flags::META_REMINDED ] = array( 'for' => $T - 30 * $D + 3600, 'at' => $T - 20 * $D );
ck( 'would_remind is false when last activity moved back before the reminded spell (same stretch)', WPCPM_Student_Flags::rows( $T )[0]['would_remind'], false );
ck( 'reminded_after_activity_moved_back: and the row still says when that reminder went, by the rule the sending skips by', WPCPM_Student_Flags::rows( $T )[0]['reminded_at'], $T - 20 * $D );

echo "\n=== A new stretch needs activity after the reminder went (Release B, the release review's 4) ===\n";
reset_all();
student( 1, 30 ); // Last active 30 days ago.
// Reminded 25 days ago for a stretch from 40 days ago: the activity learned since (30 days ago) is
// older than the reminder, so it is the same stretch.
$GLOBALS['umeta'][1][ WPCPM_Student_Flags::META_REMINDED ] = array( 'for' => $T - 40 * $D, 'at' => $T - 25 * $D );
$late = WPCPM_Student_Flags::rows( $T )[0];
ck( 'reminded_rule_uses_sent_time: activity older than the reminder, though newer than the stretch it was for, keeps the reminder on the row and would remind nobody', array( $late['reminded_at'], $late['would_remind'] ), array( $T - 25 * $D, false ) );
$GLOBALS['umeta'][1][ WPCPM_Student_Flags::META_REMINDED ] = array( 'for' => $T - 40 * $D, 'at' => $T - 35 * $D );
$fresh = WPCPM_Student_Flags::rows( $T )[0];
ck( 'and activity after the reminder went is a new stretch: no reminder on the row, one would go', array( $fresh['reminded_at'], $fresh['would_remind'] ), array( 0, true ) );

echo "\n=== A student who is their own mentor has no mentor (Release B, the trial's F3) ===\n";
reset_all();
student( 1, 30 );
$GLOBALS['users'][1]->roles[] = 'wpcpm_mentor';
$GLOBALS['mentors'][1]        = 1; // Their student row names their own mentor record.
ck( 'self_mentor_is_no_mentor: the row names no mentor', WPCPM_Student_Flags::rows( $T )[0]['mentor_id'], 0 );

echo "\n=== Where a student can book a call (fix round 1) ===\n";
reset_all();
student( 1, 30, 'In Sensei', $real_inst ); // A mentor.
person( 77 );
$GLOBALS['mentors'][1] = 77;
student( 2, 30, 'In Sensei', $real_inst ); // No mentor; the assigned Administrator's booking page, once set below.
student( 3, 30, 'In Sensei', $real_inst ); // No mentor, and no booking page.
$none_yet             = method_exists( 'WPCPM_Student_Flags', 'can_book' ) ? array( WPCPM_Student_Flags::can_book( 2 ), WPCPM_Student_Flags::booking_link( 2 ) ) : 'missing';
// The Countries table's booking page is another one, and nothing reads it: the assigned Administrator's is offered.
$GLOBALS['calendly'] = 'https://calendly.test/country';
$GLOBALS['users'][50] = new WP_User( 50, 'assigned@example.test', 'Assigned', array( WPCPM_Roles::CAP_MANAGE ) );
$GLOBALS['kept'][ $real_inst ] = array( 'assigned' => 50 );
$GLOBALS['booking']            = array( 50 => 'https://calendly.test/admin' );
ck( 'can_book_three_cases: with a mentor the calls section, with the assigned Administrator\'s booking page only that page, with neither nothing to book, and the link falls back to the Student Report Card, which offers none', method_exists( 'WPCPM_Student_Flags', 'can_book' ) ? array(
	array( WPCPM_Student_Flags::can_book( 1 ), WPCPM_Student_Flags::booking_link( 1 ) ),
	array( WPCPM_Student_Flags::can_book( 2 ), WPCPM_Student_Flags::booking_link( 2 ) ),
	$none_yet,
) : 'missing', array(
	array( true, 'https://example.test/student/#wpcpm-module-calls' ),
	array( true, 'https://calendly.test/admin' ),
	array( false, 'https://example.test/student/' ),
) );

echo "\n=== Where a never-seen student's clock started (Release B, the trial's F6) ===\n";
reset_all();
student( 1, 30 );
unset( $GLOBALS['umeta'][1][ WPCPM_Sign_Ins::META_SIGN_IN ] );
$GLOBALS['users'][1]->user_registered = gmdate( 'Y-m-d H:i:s', $T - 40 * $D );
$GLOBALS['programs'][1]['start']      = gmdate( 'Y-m-d', $T - 20 * $D ); // The track began after the account.
student( 2, 30 );
unset( $GLOBALS['umeta'][2][ WPCPM_Sign_Ins::META_SIGN_IN ] );
$GLOBALS['users'][2]->user_registered = gmdate( 'Y-m-d H:i:s', $T - 40 * $D );
$GLOBALS['programs'][2]['start']      = gmdate( 'Y-m-d', $T - 60 * $D ); // The account was made after the track began.
student( 3, 30 ); // Seen: signed in 30 days ago.
$by_id = array();
foreach ( WPCPM_Student_Flags::rows( $T ) as $row ) {
	$by_id[ $row['user_id'] ] = $row;
}
ck( 'start_from_on_row: a never-seen student\'s row says whether the clock started with the track or the account, and a seen one\'s says nothing', array(
	isset( $by_id[1]['start_from'] ) ? array( $by_id[1]['last_kind'], $by_id[1]['start_from'] ) : 'missing',
	isset( $by_id[2]['start_from'] ) ? array( $by_id[2]['last_kind'], $by_id[2]['start_from'] ) : 'missing',
	isset( $by_id[3]['start_from'] ) ? array( $by_id[3]['last_kind'], $by_id[3]['start_from'] ) : 'missing',
), array( array( 'start', 'track' ), array( 'start', 'account' ), array( 'sign_in', '' ) ) );

echo "\n=== The on-since time: the latest move into On (final review ruling) ===\n";
reset_all();
WPCPM_Student_Flags::follow_switch( array(), array( 'student_flags_mode' => 'preview' ) );
ck( 'saving preview writes no on-since time', array_key_exists( WPCPM_Student_Flags::OPT_ON_SINCE, $GLOBALS['opts'] ), false );
$t0 = time();
WPCPM_Student_Flags::follow_switch( array( 'student_flags_mode' => 'preview' ), array( 'student_flags_mode' => 'on' ) );
$first = get_option( WPCPM_Student_Flags::OPT_ON_SINCE );
ck( 'on_since_written_on_each_move_into_on: Preview to On writes the time of the save', is_int( $first ) && $first >= $t0 && $first <= time(), true );
$GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] = $first - 1000;
WPCPM_Student_Flags::follow_switch( array( 'student_flags_mode' => 'on' ), array( 'student_flags_mode' => 'on', 'student_quiet_days' => 20 ) );
ck( 'a save that leaves the switch On keeps the time', get_option( WPCPM_Student_Flags::OPT_ON_SINCE ), $first - 1000 );
WPCPM_Student_Flags::follow_switch( array( 'student_flags_mode' => 'on' ), array( 'student_flags_mode' => 'off' ) );
ck( 'a move to Off keeps it too', get_option( WPCPM_Student_Flags::OPT_ON_SINCE ), $first - 1000 );
$t1 = time();
WPCPM_Student_Flags::follow_switch( array( 'student_flags_mode' => 'off' ), array( 'student_flags_mode' => 'on' ) );
ck( 'and On again after Off writes the new time, so the second On mails nobody already quiet', get_option( WPCPM_Student_Flags::OPT_ON_SINCE ) >= $t1, true );
$GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] = $first - 1000;
WPCPM_Student_Flags::follow_switch( array(), array( 'student_flags_mode' => 'on' ) );
ck( 'a save from settings that held no switch yet counts as a move into On', get_option( WPCPM_Student_Flags::OPT_ON_SINCE ) >= $t1, true );

reset_all();
$GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] = $T;
ck( 'reached_since_on: a stretch that reaches the threshold at or after the switch went On may be mailed, one already past it may not', array(
	WPCPM_Student_Flags::reached_since_on( $T - 14 * $D ),
	WPCPM_Student_Flags::reached_since_on( $T - 10 * $D ),
	WPCPM_Student_Flags::reached_since_on( $T - 14 * $D - 1 ),
	WPCPM_Student_Flags::reached_since_on( $T - 20 * $D, $T - 7 * $D ),
), array( true, true, false, true ) );
$GLOBALS['settings']['student_quiet_days'] = 30;
ck( 'and the threshold is the setting\'s', WPCPM_Student_Flags::reached_since_on( $T - 20 * $D ), true );
unset( $GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] );
ck( 'with no on-since time stored, nothing is held back by it', WPCPM_Student_Flags::reached_since_on( $T - 400 * $D ), true );

echo "\n=== Off forgets the list (final review, Minor 2) ===\n";
reset_all();
student( 1, 30 );
WPCPM_Student_Flags::run_daily( $T );
WPCPM_Student_Flags::follow_switch( array( 'student_flags_mode' => 'preview' ), array( 'student_flags_mode' => 'preview', 'student_quiet_days' => 20 ) );
ck( 'a save that keeps Preview keeps the stored list', array_key_exists( WPCPM_Student_Flags::OPT_SNAPSHOT, $GLOBALS['opts'] ), true );
WPCPM_Student_Flags::follow_switch( array( 'student_flags_mode' => 'preview' ), array( 'student_flags_mode' => 'off' ) );
ck( 'snapshot_deleted_when_off: saving Off deletes the stored list', array_key_exists( WPCPM_Student_Flags::OPT_SNAPSHOT, $GLOBALS['opts'] ), false );
ck( 'so switching back shows no list from before Off until the next run', WPCPM_Student_Flags::snapshot(), array( 'at' => 0, 'rows' => array() ) );

echo "\n=== Forgetting a student ===\n";
reset_all();
student( 1, 30 );
student( 2, 31 );
WPCPM_Student_Flags::run_daily( $T );
WPCPM_Student_Flags::forget_student( 2 );
ck( 'forget_student_drops_row: one row is gone and the other stays', ids_of( WPCPM_Student_Flags::snapshot()['rows'] ), array( 1 ) );
WPCPM_Student_Flags::forget_student( 99 );
ck( 'forgetting a student the snapshot does not hold changes nothing', ids_of( WPCPM_Student_Flags::snapshot()['rows'] ), array( 1 ) );

echo "\n=== We've already talked ===\n";
/**
 * Press the button as a person: the outcome is 'redirect:<url>' on success, 'die403', 'nonce'.
 */
function press( $as, $student_id, $nonce = true, $post = array() ) {
	$GLOBALS['uid']      = $as;
	$GLOBALS['nonce_ok'] = $nonce ? WPCPM_Student_Flags::ACTION_TALKED . '_' . $student_id : 'wrong';
	$_POST               = array_merge( array( 'student' => $student_id ), $post );
	try {
		WPCPM_Student_Flags::handle_talked();
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
	return 'returned';
}
function person( $id, $caps = array() ) {
	$GLOBALS['users'][ $id ] = (object) array( 'ID' => $id, 'user_email' => 'p' . $id . '@example.test', 'user_registered' => '', 'display_name' => 'Person ' . $id, 'roles' => array(), 'caps' => $caps );
}
function talked_setup() {
	reset_all();
	student( 1, 30 );
	student( 2, 31 );
	person( 50 );
	person( 51 );
	person( 60, array( WPCPM_Roles::CAP_MANAGE ) );
	$GLOBALS['mentors'][1] = 50;
	WPCPM_Student_Flags::run_daily( $GLOBALS['T'] );
}
$GLOBALS['T'] = $T;

talked_setup();
ck( 'talked_by_student: the student presses for themself and goes back to the Student Report Card', press( 1, 1 ), 'redirect:https://example.test/student/' );
ck( 'and the press writes the talked stamp with who pressed it', array( array_keys( $GLOBALS['umeta'][1]['wpcpm_student_talked'] ), $GLOBALS['umeta'][1]['wpcpm_student_talked']['by'] ), array( array( 'at', 'by' ), 1 ) );
ck( 'and drops the student\'s snapshot row at once, leaving the other', ids_of( WPCPM_Student_Flags::snapshot()['rows'] ), array( 2 ) );
$said = isset( $GLOBALS['flash']['student-talked'] ) ? $GLOBALS['flash']['student-talked'] : null;
ck( 'talked_press_leaves_a_line: the press leaves a one-shot note for the page it lands on, naming the student it was made for', is_array( $said ) ? array( $said['student'], $said['name'], is_int( $said['at'] ) ) : 'missing', array( 1, 'Student 1', true ) );
talked_setup();
press( 51, 1 );
ck( 'and a refused press leaves none', isset( $GLOBALS['flash']['student-talked'] ), false );

talked_setup();
ck( 'talked_by_mentor: the student\'s mentor may', press( 50, 1 ), 'redirect:https://example.test/student/' );
ck( 'and it records the mentor as the one who pressed', $GLOBALS['umeta'][1]['wpcpm_student_talked']['by'], 50 );

talked_setup();
ck( 'talked_by_admin: a holder of the manage capability may', press( 60, 2 ), 'redirect:https://example.test/student/' );
ck( 'and the Administrator is recorded', $GLOBALS['umeta'][2]['wpcpm_student_talked']['by'], 60 );

talked_setup();
ck( 'talked_by_other_mentor: a mentor of nobody here is refused', press( 51, 1 ), 'die403' );
ck( 'and nothing was written or dropped', array( isset( $GLOBALS['umeta'][1]['wpcpm_student_talked'] ), ids_of( WPCPM_Student_Flags::snapshot()['rows'] ) ), array( false, array( 2, 1 ) ) );
ck( 'another student may not press for a student', press( 2, 1 ), 'die403' );
ck( 'a student\'s mentor may not press for another mentor\'s student', press( 50, 2 ), 'die403' );
ck( 'a person signed out is refused', press( 0, 1 ), 'die403' );

talked_setup();
ck( 'talked_bad_nonce: a press with the wrong nonce stops before anything else', press( 1, 1, false ), 'nonce' );
ck( 'and writes nothing', isset( $GLOBALS['umeta'][1]['wpcpm_student_talked'] ), false );

talked_setup();
$GLOBALS['referer'] = 'https://example.test/mentor-dashboard/';
ck( 'the press lands back on the page it was made on', press( 50, 1 ), 'redirect:https://example.test/mentor-dashboard/' );
talked_setup();
ck( 'from the Administrator Dashboard\'s card it lands on that card', press( 60, 1, true, array( 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'quiet-students' ) ), 'redirect:https://example.test/admin/#wpcpm-quiet-students' );
ck( 'init hangs the handler on admin-post', ( function () { reset_all(); $GLOBALS['hooks'] = array(); WPCPM_Student_Flags::init(); return isset( $GLOBALS['hooks']['admin_post_wpcpm_student_talked'] ); } )(), true );

// The Administrator Dashboard card's one form (Release B, the final review): each row's button carries that
// row's nonce in its formaction address and the student in its value, and a press posts the form with the
// button's formaction. WordPress builds $_REQUEST as GET merged with POST, so the stand-in is built the same way,
// from the markup the button really prints.
function card_press( $as, $button_markup, $student_override = null ) {
	preg_match( '/value="(\d+)" formaction="([^"]*)"/', $button_markup, $found );
	parse_str( (string) parse_url( $found[2], PHP_URL_QUERY ), $get );
	$GLOBALS['uid']                = $as;
	$GLOBALS['nonce_from_request'] = true;
	$_GET                          = $get;
	$_POST                         = array( 'action' => WPCPM_Student_Flags::ACTION_TALKED, 'student' => null === $student_override ? (int) $found[1] : $student_override, 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'quiet-students' );
	$_REQUEST                      = array_merge( $_GET, $_POST );
	$outcome                       = 'returned';
	try {
		WPCPM_Student_Flags::handle_talked();
	} catch ( Exception $e ) {
		$outcome = $e->getMessage();
	}
	$GLOBALS['nonce_from_request'] = false;
	$_GET = $_POST = $_REQUEST = array();
	return $outcome;
}
talked_setup();
$button_one = WPCPM_Student_Flags::talked_button( 1, 'Student 1' );
$button_two = WPCPM_Student_Flags::talked_button( 2, 'Student 2' );
ck( 'talked_card_form_button: a row\'s button posts the student in its value and that student\'s nonce in its formaction address', array(
	false !== strpos( $button_one, 'name="student" value="1"' ),
	false !== strpos( $button_one, 'formaction="https://example.test/wp-admin/admin-post.php?_wpnonce=nonce-wpcpm_student_talked_1"' ),
), array( true, true ) );
ck( 'and the card\'s one form carries the action and the way back, with no nonce or student of its own', array(
	false !== strpos( WPCPM_Student_Flags::talked_card_form( 'quiet-students' ), 'name="action" value="wpcpm_student_talked"' ),
	false !== strpos( WPCPM_Student_Flags::talked_card_form( 'quiet-students' ), '_wpnonce' ),
	false !== strpos( WPCPM_Student_Flags::talked_card_form( 'quiet-students' ), 'name="student"' ),
), array( true, false, false ) );
ck( 'talked_card_press: a press of student 1\'s button is accepted for student 1 and lands on the card', card_press( 60, $button_one ), 'redirect:https://example.test/admin/#wpcpm-quiet-students' );
ck( 'and writes the stamp for student 1 only, and drops that row', array( isset( $GLOBALS['umeta'][1]['wpcpm_student_talked'] ), isset( $GLOBALS['umeta'][2]['wpcpm_student_talked'] ), ids_of( WPCPM_Student_Flags::snapshot()['rows'] ) ), array( true, false, array( 2 ) ) );
talked_setup();
ck( 'talked_card_wrong_student: student 1\'s nonce with student 2 in the POST is refused', card_press( 60, $button_one, 2 ), 'nonce' );
ck( 'and student 2\'s nonce with student 1 in the POST is refused as well', card_press( 60, $button_two, 1 ), 'nonce' );
ck( 'and nothing was written or dropped', array( isset( $GLOBALS['umeta'][1]['wpcpm_student_talked'] ), isset( $GLOBALS['umeta'][2]['wpcpm_student_talked'] ), ids_of( WPCPM_Student_Flags::snapshot()['rows'] ) ), array( false, false, array( 2, 1 ) ) );
ck( 'a press with no nonce in the address is refused', ( function () use ( $button_one ) { return card_press( 60, str_replace( '?_wpnonce=nonce-wpcpm_student_talked_1', '', $button_one ) ); } )(), 'nonce' );

reset_all();
person( 1 );
$form = WPCPM_Student_Flags::talked_form( 1 );
ck( 'talked_form posts admin-post with the action, the student, the nonce and the default words', array(
	false !== strpos( $form, 'action="https://example.test/wp-admin/admin-post.php"' ),
	false !== strpos( $form, 'name="action" value="wpcpm_student_talked"' ),
	false !== strpos( $form, 'name="student" value="1"' ),
	false !== strpos( $form, 'nonce-wpcpm_student_talked_1' ),
	false !== strpos( $form, 'We&#039;ve already talked' ),
), array( true, true, true, true, true ) );
ck( 'a given label is used and escaped; no card means no return fields', array( false !== strpos( WPCPM_Student_Flags::talked_form( 1, 'Talked <b>' ), 'Talked &lt;b&gt;' ), false === strpos( $form, 'wpcpm_return' ) ), array( true, true ) );
ck( 'a card key adds the dashboard return fields', false !== strpos( WPCPM_Student_Flags::talked_form( 1, '', 'quiet-students' ), 'name="wpcpm_return_to" value="quiet-students"' ), true );
ck( 'talked_form_no_repeated_id: the nonce goes without the id core gives it, which every such form on a page would repeat, and the way back stays', array(
	substr_count( $form, 'id="_wpnonce"' ),
	substr_count( $form, 'name="_wpnonce" value="nonce-wpcpm_student_talked_1"' ),
	substr_count( $form, 'name="_wp_http_referer"' ),
), array( 0, 1, 1 ) );

echo "\n=== Only the daily job sends (Release B, parked item 14) ===\n";
$senders = array();
foreach ( array( 'remind', 'digest', 'weekly' ) as $sender ) {
	$method              = new ReflectionMethod( 'WPCPM_Student_Flags', $sender );
	$senders[ $sender ] = array( $method->isPrivate(), array_map( static function ( $p ) { return $p->getName(); }, $method->getParameters() ) );
}
ck( 'senders_private: the three senders are the class\'s own, reached through the daily job alone, and the digest takes no time it does not read', array( $senders['remind'][0], $senders['digest'][0], $senders['weekly'][0], in_array( 'now', $senders['digest'][1], true ) ), array( true, true, true, false ) );

echo "\n=== The settings, as the flags read them ===\n";
reset_all();
$GLOBALS['settings']['student_flags_mode'] = 'loud';
ck( 'an unknown mode reads as off', WPCPM_Student_Flags::mode(), 'off' );
foreach ( array( 'off', 'preview', 'on' ) as $mode ) {
	$GLOBALS['settings']['student_flags_mode'] = $mode;
	$modes[]                                   = WPCPM_Student_Flags::mode();
}
ck( 'off, preview and on read as themselves', $modes, array( 'off', 'preview', 'on' ) );
$GLOBALS['settings']['student_quiet_days'] = 3;
$low = WPCPM_Student_Flags::threshold();
$GLOBALS['settings']['student_quiet_days'] = 99;
ck( 'the days are held to 7 to 60 even if the stored value is not', array( $low, WPCPM_Student_Flags::threshold() ), array( 7, 60 ) );

echo "\n=== The clock ===\n";
reset_all();
WPCPM_Student_Flags::init();
ck( 'init hangs the daily job, the self-scheduling and the switch\'s follower', array( isset( $GLOBALS['hooks'][ WPCPM_Student_Flags::CRON_DAILY ] ), isset( $GLOBALS['hooks']['init'] ), isset( $GLOBALS['hooks']['update_option_wpcpm_settings'] ) ), array( true, true, true ) );
$noon = 1800000000 - ( 1800000000 % DAY_IN_SECONDS ) + 12 * HOUR_IN_SECONDS;
ck( 'the next run is the next 06:00 UTC', array( WPCPM_Student_Flags::next_run( $noon ), WPCPM_Student_Flags::next_run( $noon - 8 * HOUR_IN_SECONDS ) ), array( $noon - 6 * HOUR_IN_SECONDS + DAY_IN_SECONDS, $noon - 6 * HOUR_IN_SECONDS ) );
WPCPM_Student_Flags::schedule();
$at = $GLOBALS['cron'][ WPCPM_Student_Flags::CRON_DAILY ]['at'];
WPCPM_Student_Flags::schedule();
ck( 'schedule puts a daily event on the clock once', array( $GLOBALS['cron'][ WPCPM_Student_Flags::CRON_DAILY ]['recurrence'], $GLOBALS['cron'][ WPCPM_Student_Flags::CRON_DAILY ]['at'] === $at, 0 === $at % HOUR_IN_SECONDS ), array( 'daily', true, true ) );
WPCPM_Student_Flags::unschedule();
ck( 'unschedule takes it off', isset( $GLOBALS['cron'][ WPCPM_Student_Flags::CRON_DAILY ] ), false );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
