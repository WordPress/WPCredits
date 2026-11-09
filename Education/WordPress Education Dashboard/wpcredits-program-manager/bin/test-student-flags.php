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
function user_can( $user, $cap ) { return in_array( $cap, $user->caps, true ); }
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
function check_admin_referer( $action ) { if ( empty( $GLOBALS['nonce_ok'] ) || $GLOBALS['nonce_ok'] !== $action ) { throw new Exception( 'nonce' ); } }
function wp_die( $message = '', $code = 0 ) { throw new Exception( 'die' . $code ); }
function wp_safe_redirect( $url ) { throw new Exception( 'redirect:' . $url ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . $action . '" />'; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s ); }
function esc_attr__( $s ) { return htmlspecialchars( $s ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s ); }
function esc_url( $s ) { return (string) $s; }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, (int) $timestamp ); }
function wp_mail() { $GLOBALS['mails'][] = func_get_args(); return true; }

// Stand-ins for the classes whose answers the flags read.
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
		return array( 'name' => $GLOBALS['seed_countries'][ $id ]['name'], 'manager' => '', 'email' => 'contact-' . $id . '@example.test', 'calendly' => '' );
	}
}
class WPCPM_Test_Institutions {
	public static function is_test( $id ) { return isset( $GLOBALS['seed_inst'][ $id ] ) && 0 === strpos( $GLOBALS['seed_inst'][ $id ]['name'], 'TEST' ); }
}

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
$GLOBALS['users'][50] = (object) array( 'ID' => 50, 'user_email' => $email, 'user_registered' => '', 'display_name' => 'Manager', 'roles' => array(), 'caps' => array( WPCPM_Roles::CAP_MANAGE ) );
ck( 'manager_by_country: the account behind the country contact who may manage the program', WPCPM_Student_Flags::manager_of( 1 ), 50 );
$GLOBALS['users'][50]->caps = array();
ck( 'an account without the capability gives 0', WPCPM_Student_Flags::manager_of( 1 ), 0 );
unset( $GLOBALS['users'][50] );
ck( 'a contact with no account gives 0', WPCPM_Student_Flags::manager_of( 1 ), 0 );
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
	array( 'user_id', 'record', 'name', 'days', 'last_at', 'last_kind', 'mentor_id', 'manager_id', 'test', 'reminded_at', 'would_remind' ) );

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
