<?php
/**
 * Quiet students: the reminders, the mentor digest and the Monday list (student automation, first line).
 *
 * What this pins, and why:
 *
 * - On, one quiet student with a mentor gets one `student-quiet-student` and the mentor one
 *   `student-quiet-mentor` naming the student; the reminder is stamped with the spell's time.
 * - When reminders start (final review ruling): the on-since time is the latest move into On, and a
 *   stretch is mailed only when it reached the threshold at or after it. A stretch already past the
 *   threshold at launch, or at an On after Off, is listed and never mailed; a student last active 10
 *   days before launch is reminded 4 days after it.
 * - A student who signed in before a track that starts later, one back on an old account whose new
 *   track started since, and one with a call or group session booked ahead, are neither listed nor
 *   mailed; remind() checks a call ahead again at send time.
 * - After a call is canceled and last activity moves back, the row still says when the stretch was
 *   reminded. A student closed since the morning's list sees no notice, and a list stored before the
 *   switch went Off does not come back with it.
 * - A second run the same day mails nobody (Review Focus 2); a new spell, after fresh activity and
 *   another quiet stretch, is reminded again.
 * - A mentor with three quiet students gets one digest listing three; a student whose mentor has no
 *   account is reminded alone (Review Focus 3); a mentor who is also an Administrator gets each mail once.
 * - The booking link is the calls section with a mentor, else the country's booking page, else the
 *   Student Report Card.
 * - A test institution's student is never mailed, counted or listed in the Monday list.
 * - A student closed since the snapshot is skipped at send time (Review Focus 1).
 * - The Monday list goes once per ISO week, only on a Monday, to each Administrator with real rows.
 * - Preview and Off mail nothing. The reminder is stamped before its mail, so a run that stops in
 *   the mail layer never sends twice, and the run's lock is let go either way.
 * - What the three emails say: no em or en dash, no sentence starting with "Please", no old role name.
 *
 * Run from the plugin root:  php bin/test-student-reminders.php
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
function wp_mail() { $GLOBALS['mails'][] = func_get_args(); return true; }

// Stand-ins for the classes whose answers the flags read.
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
	// Every call of the student, as the real query gives them with $upcoming false; the flags read a
	// call's start themselves to tell one still ahead.
	public static function for_student( $id, $upcoming = true ) { return isset( $GLOBALS['calls'][ $id ] ) ? $GLOBALS['calls'][ $id ] : array(); }
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



function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, (int) $timestamp ); }
function apply_filters( $hook, $value ) { return $value; }
function wp_specialchars_decode( $s ) { return htmlspecialchars_decode( (string) $s, ENT_QUOTES ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '';
	public function __construct( $id = 0, $name = '' ) { $this->ID = $id; $this->display_name = $name; }
}

class WPCPM_Lock {
	public static $held = array(), $released = 0;
	public static function take( $name, $ttl ) {
		if ( isset( self::$held[ $name ] ) ) {
			return '';
		}
		self::$held[ $name ] = true;
		return '123';
	}
	public static function release( $name, $token ) { unset( self::$held[ $name ] ); ++self::$released; return true; }
}
class WPCPM_Mail {
	public static function site_name() { return 'Credits'; }
	public static function send( $recipient, $context, $build ) {
		$user = is_object( $recipient ) ? $recipient : get_userdata( (int) $recipient );
		if ( ! $user || '' === (string) $user->user_email ) {
			return false;
		}
		if ( ! empty( $GLOBALS['mail_throws'] ) ) {
			throw new Exception( 'mail layer stopped' );
		}
		$mail = $build( $user );
		$GLOBALS['mails'][] = array( 'to' => (int) $user->ID, 'id' => $context, 'subject' => $mail['subject'], 'body' => $mail['body'] );
		return true;
	}
}
class WPCPM_Mentors_Dashboard {
	public static function page_url() { return 'https://example.test/mentor/'; }
}
class WPCPM_Mentor_Notes {
	public static function anchor( $record ) { return 'wpcpm-student-' . $record; }
}
class WPCPM_Administrators_Dashboard {
	public static function page_url() { return 'https://example.test/administrators/'; }
}
class WPCPM_Typed_Text {
	public static function mail_text( $s ) { return $s; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
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

$T = 1800000000; // Friday 15 January 2027, 08:00 UTC.
$D = DAY_IN_SECONDS;
$M = $T + 3 * $D; // The Monday after.

$real_inst = 'recSEED0000000002';
$test_inst = 'recSEED0000000001';
$GLOBALS['seed_inst'][ $test_inst ]['name'] = 'TEST Institution';
$country = $GLOBALS['seed_inst'][ $real_inst ]['country'];

function reset_all() {
	global $T, $D;
	$GLOBALS['umeta']       = array();
	$GLOBALS['opts']        = array( WPCPM_Student_Flags::OPT_ON_SINCE => 1000 );
	$GLOBALS['users']       = array();
	$GLOBALS['programs']    = array();
	$GLOBALS['mentors']     = array();
	$GLOBALS['calls']       = array();
	$GLOBALS['settings']    = array( 'student_flags_mode' => 'on', 'student_quiet_days' => 14 );
	$GLOBALS['cron']        = array();
	$GLOBALS['mails']       = array();
	$GLOBALS['uid']         = 0;
	$GLOBALS['mail_throws'] = false;
	unset( $GLOBALS['calendly'] );
	WPCPM_Lock::$held     = array();
	WPCPM_Lock::$released = 0;
}

function student( $id, $quiet, $institution = '', $status = 'In Sensei' ) {
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
		WPCPM_Sign_Ins::META_SIGN_IN        => (string) ( $T - $quiet * $D ),
		WPCPM_Students_Sync::META_ACTIVE    => 1,
		WPCPM_Students_Sync::META_RECORD_ID => 'recSTUDENT' . str_pad( (string) $id, 5, '0', STR_PAD_LEFT ),
	);
	if ( '' !== $institution ) {
		$GLOBALS['umeta'][ $id ][ WPCPM_Students_Sync::META_INSTITUTION ] = $institution;
	}
	$GLOBALS['programs'][ $id ] = array( 'program' => $status, 'name' => 'Student ' . $id );
	return $id;
}

function person( $id, $caps = array(), $email = null ) {
	$GLOBALS['users'][ $id ] = (object) array( 'ID' => $id, 'user_email' => null === $email ? 'p' . $id . '@example.test' : $email, 'user_registered' => '', 'display_name' => 'Person ' . $id, 'roles' => array(), 'caps' => $caps );
}

function mentor_of( $student, $mentor ) {
	$GLOBALS['mentors'][ $student ] = $mentor;
}

function sent( $id = null ) {
	return array_values( array_filter( $GLOBALS['mails'], static function ( $m ) use ( $id ) { return null === $id || $m['id'] === $id; } ) );
}

function to_of( $mails ) { return array_map( static function ( $m ) { return $m['to']; }, $mails ); }

function call( $booked, $start, $status = 'private' ) { return (object) array( 'post_status' => $status, 'booked' => $booked, 'start' => $start ); }

/**
 * Save the switch as the Settings screen does: the settings are written, then the option's update
 * action hands follow_switch() the value before and the value after.
 */
function follow( $mode ) {
	$before = isset( $GLOBALS['settings']['student_flags_mode'] ) ? $GLOBALS['settings']['student_flags_mode'] : 'off';

	$GLOBALS['settings']['student_flags_mode'] = $mode;
	WPCPM_Student_Flags::follow_switch( array( 'student_flags_mode' => $before ), array( 'student_flags_mode' => $mode ) );
}

/** A student whose last sign-in was $quiet days before $at (not $T), with an account made 400 days before it. */
function student_at( $id, $at, $quiet ) {
	global $D;
	student( $id, 0 );
	$GLOBALS['users'][ $id ]->user_registered               = gmdate( 'Y-m-d H:i:s', $at - 400 * $D );
	$GLOBALS['umeta'][ $id ][ WPCPM_Sign_Ins::META_SIGN_IN ] = (string) ( $at - $quiet * $D );
}

// The Administrator of the real institution's country.
function manager( $id = 60 ) {
	global $country;
	person( $id, array( WPCPM_Roles::CAP_MANAGE ), 'contact-' . $country . '@example.test' );
}

echo "=== On: the reminder and the mentor digest ===\n";
reset_all();
student( 1, 20 );
person( 77 );
mentor_of( 1, 77 );
WPCPM_Student_Flags::run_daily( $T );
$to_student = sent( 'student-quiet-student' );
$to_mentor  = sent( 'student-quiet-mentor' );
ck( 'on_reminds_student_and_mentor: one reminder to the student and one digest to the mentor', array( to_of( $to_student ), to_of( $to_mentor ), count( $GLOBALS['mails'] ) ), array( array( 1 ), array( 77 ), 2 ) );
ck( 'the digest names the student, with how long and a link to them on the Mentor Report Card', false !== strpos( $to_mentor[0]['body'], 'Student 1, 20 days: https://example.test/mentor/?wpcpm_student=recSTUDENT00001#wpcpm-student-recSTUDENT00001' ), true );
ck( 'the stamp holds the spell\'s time (the last sign-in) and the time sent', get_user_meta( 1, WPCPM_Student_Flags::META_REMINDED ), array( 'for' => $T - 20 * $D, 'at' => $T ) );
ck( 'the lock was taken and let go', array( WPCPM_Lock::$held, WPCPM_Lock::$released ), array( array(), 1 ) );
ck( 'the snapshot is stored again with the reminder\'s time on the row', WPCPM_Student_Flags::snapshot()['rows'][0]['reminded_at'], $T );

echo "\n=== The spell and the switch ===\n";
reset_all();
$GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] = $T - 5 * $D;
student( 1, 20 ); // Reached the 14 days 6 days ago, before the switch went On.
student( 2, 30 ); // Reached them 16 days ago.
WPCPM_Student_Flags::run_daily( $T );
ck( 'stretch_past_threshold_at_on_not_reminded: a stretch already past the threshold when the switch went On is listed, and gets no mail', array( count( WPCPM_Student_Flags::snapshot()['rows'] ), $GLOBALS['mails'] ), array( 2, array() ) );

reset_all();
$GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] = $T - 10 * $D;
student( 1, 20 ); // Reached the 14 days 6 days ago, after the switch went On 10 days ago.
student( 2, 30 ); // Reached them 16 days ago, before it.
WPCPM_Student_Flags::run_daily( $T );
ck( 'and of two quiet students, the one whose stretch reached the threshold after the switch went On is mailed, the other not', to_of( sent( 'student-quiet-student' ) ), array( 1 ) );

echo "\n=== When reminders start: the latest move into On (final review ruling) ===\n";
reset_all();
unset( $GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] );
$GLOBALS['settings']['student_flags_mode'] = 'preview'; // Launch is a move from Preview into On.
$now = time();
follow( 'on' );
$GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] = $now - 60 * $D; // The first On was 60 days ago.
follow( 'off' );
for ( $i = 1; $i <= 10; $i++ ) {
	student_at( $i, $now, 15 + 3 * $i ); // Went quiet while the switch was Off: 18 to 45 days.
}
follow( 'on' ); // On again, now.
WPCPM_Student_Flags::run_daily( $now + HOUR_IN_SECONDS );
ck( 'on_off_on_no_wave: On, Off and On again mails nobody whose stretch was already past the threshold at the second On', array( count( WPCPM_Student_Flags::snapshot()['rows'] ), $GLOBALS['mails'] ), array( 10, array() ) );

reset_all();
unset( $GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] );
$GLOBALS['settings']['student_flags_mode'] = 'preview'; // Launch is a move from Preview into On.
$now = time();
student_at( 1, $now, 10 ); // Last active 10 days before launch.
follow( 'on' ); // Launch.
WPCPM_Student_Flags::run_daily( $now + 3 * $D + HOUR_IN_SECONDS );
$early = count( sent( 'student-quiet-student' ) );
WPCPM_Student_Flags::run_daily( $now + 4 * $D + HOUR_IN_SECONDS );
ck( 'quiet_after_launch_reminded: with 14 days, a student last active 10 days before launch is reminded 4 days after it, not before', array( $early, to_of( sent( 'student-quiet-student' ) ) ), array( 0, array( 1 ) ) );

reset_all();
unset( $GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] );
$GLOBALS['settings']['student_flags_mode'] = 'preview'; // Launch is a move from Preview into On.
$now = time();
student_at( 1, $now, 20 ); // Past the threshold at launch.
follow( 'on' );
foreach ( array( 1, 7, 30 ) as $later ) {
	WPCPM_Student_Flags::run_daily( $now + $later * $D );
}
ck( 'past_threshold_at_launch_never_mailed: a student 20 days quiet at launch stays on the list and is never mailed', array( count( WPCPM_Student_Flags::snapshot()['rows'] ), $GLOBALS['mails'] ), array( 1, array() ) );

echo "\n=== A start still ahead and a call booked ahead are not quiet (final review) ===\n";
reset_all();
student( 1, 20 ); // Signed in when invited, the day the account was made; the track starts in 5 days.
$GLOBALS['users'][1]->user_registered = gmdate( 'Y-m-d H:i:s', $T - 20 * $D );
$GLOBALS['programs'][1]['start']      = gmdate( 'Y-m-d', $T + 5 * $D );
student( 2, 200 ); // Back on an old account; the new track started 2 days ago.
$GLOBALS['programs'][2]['start'] = gmdate( 'Y-m-d', $T - 2 * $D );
person( 77 );
mentor_of( 1, 77 );
WPCPM_Student_Flags::run_daily( $T );
ck( 'track_start_not_reminded: a student who signed in before a track that starts in 5 days, and one whose new track started 2 days ago, are neither listed nor mailed', array( count( WPCPM_Student_Flags::snapshot()['rows'] ), $GLOBALS['mails'] ), array( 0, array() ) );

reset_all();
student( 1, 20 );
person( 77 );
mentor_of( 1, 77 );
$GLOBALS['calls'][1] = array( call( $T - 16 * $D, $T + 10 * $D ) ); // Booked 16 days ago for 10 days ahead.
WPCPM_Student_Flags::run_daily( $T );
ck( 'call_ahead_not_reminded: a student with a call booked ahead is not listed, not told to book a call, and their mentor gets no digest', array( count( WPCPM_Student_Flags::snapshot()['rows'] ), sent( 'student-quiet-student' ), sent( 'student-quiet-mentor' ) ), array( 0, array(), array() ) );

reset_all();
student( 1, 20 );
$rows = WPCPM_Student_Flags::rows( $T );
$GLOBALS['calls'][1] = array( call( $T - 25 * $D, $T + 3 * $D ) ); // Booked before the last sign-in: the spell's time is unchanged.
ck( 'and remind() checks again at send time: a call ahead found then skips the student', array( WPCPM_Student_Flags::remind( $rows, $T ), $GLOBALS['mails'], get_user_meta( 1, WPCPM_Student_Flags::META_REMINDED ) ), array( array(), array(), '' ) );

echo "\n=== The card keeps \"Reminded\" when activity moves back (final review, Minor 1) ===\n";
reset_all();
student( 1, 30 );
$GLOBALS['calls'][1] = array( call( $T - 20 * $D, $T - 20 * $D ) ); // A call held 20 days ago.
WPCPM_Student_Flags::run_daily( $T );
$GLOBALS['calls'][1][0]->post_status = 'trash'; // The call is canceled: last activity moves back to the sign-in.
WPCPM_Student_Flags::run_daily( $T + $D );
$moved = WPCPM_Student_Flags::snapshot()['rows'][0];
ck( 'reminded_label_after_move_back: the row still says the stretch was reminded, and nothing more is sent', array( count( sent( 'student-quiet-student' ) ), $moved['last_at'], $moved['reminded_at'] ), array( 1, $T - 30 * $D, $T ) );

reset_all();
student( 1, 20 );
person( 77 );
mentor_of( 1, 77 );
WPCPM_Student_Flags::run_daily( $T );
$first = count( $GLOBALS['mails'] );
WPCPM_Student_Flags::run_daily( $T + 3600 );
WPCPM_Student_Flags::run_daily( $T + $D );
ck( 'second_run_sends_nothing: the same spell is mailed once, student and mentor, however often the job runs', array( $first, count( $GLOBALS['mails'] ) ), array( 2, 2 ) );

reset_all();
student( 1, 20 );
person( 77 );
mentor_of( 1, 77 );
WPCPM_Student_Flags::run_daily( $T );
// Last activity moves one hour BACK (a booked call was canceled): the same quiet stretch.
$GLOBALS['umeta'][1][ WPCPM_Sign_Ins::META_SIGN_IN ] = (string) ( $T - 20 * $D - 3600 );
$GLOBALS['umeta'][1][ WPCPM_Student_Flags::META_REMINDED ] = array( 'for' => $T - 20 * $D, 'at' => $T );
WPCPM_Student_Flags::run_daily( $T + $D );
ck( 'activity_moved_back_sends_nothing: last activity one hour earlier than the reminded spell is the same stretch, no second reminder or digest', array( count( sent( 'student-quiet-student' ) ), count( sent( 'student-quiet-mentor' ) ) ), array( 1, 1 ) );

reset_all();
student( 1, 20 );
WPCPM_Student_Flags::run_daily( $T );
// The student signs in, then goes quiet again for 15 days.
$GLOBALS['umeta'][1][ WPCPM_Sign_Ins::META_SIGN_IN ] = (string) ( $T + $D );
WPCPM_Student_Flags::run_daily( $T + 5 * $D );
$none_yet = count( $GLOBALS['mails'] );
WPCPM_Student_Flags::run_daily( $T + 16 * $D + 1 );
ck( 'new_spell_reminds_again: quiet again after fresh activity, the student is reminded once more, not before', array( $none_yet, count( sent( 'student-quiet-student' ) ), get_user_meta( 1, WPCPM_Student_Flags::META_REMINDED )['for'] ), array( 1, 2, $T + $D ) );

reset_all();
$GLOBALS['settings']['student_flags_mode'] = 'preview';
student( 1, 20 );
person( 77 );
mentor_of( 1, 77 );
manager();
student( 2, 20, $real_inst );
WPCPM_Student_Flags::run_daily( $M );
ck( 'preview_mails_nothing: Preview stores the snapshot, sends nothing, not even on a Monday, and stamps nobody', array( $GLOBALS['mails'], count( WPCPM_Student_Flags::snapshot()['rows'] ), get_user_meta( 1, WPCPM_Student_Flags::META_REMINDED ), array_key_exists( WPCPM_Student_Flags::OPT_WEEKLY, $GLOBALS['opts'] ) ), array( array(), 2, '', false ) );
$GLOBALS['settings']['student_flags_mode'] = 'off';
WPCPM_Lock::$released = 0;
WPCPM_Student_Flags::run_daily( $M );
ck( 'and Off does nothing at all', array( $GLOBALS['mails'], WPCPM_Lock::$released ), array( array(), 0 ) );

reset_all();
unset( $GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] );
student( 1, 20 );
WPCPM_Student_Flags::run_daily( $T );
ck( 'an On switch with no on-since time notes it as now and mails nobody who was already quiet', array( $GLOBALS['mails'], $GLOBALS['opts'][ WPCPM_Student_Flags::OPT_ON_SINCE ] ), array( array(), $T ) );

echo "\n=== The mentor digest ===\n";
reset_all();
person( 77 );
student( 1, 20 );
student( 2, 25 );
student( 3, 30 );
student( 4, 22 );
person( 78 );
foreach ( array( 1, 2, 3 ) as $id ) {
	mentor_of( $id, 77 );
}
mentor_of( 4, 78 );
WPCPM_Student_Flags::run_daily( $T );
$digests = sent( 'student-quiet-mentor' );
ck( 'mentor_digest_one_per_mentor: one digest for each mentor, one student to the second', to_of( $digests ), array( 77, 78 ) );
ck( 'the first lists all three, the most quiet first', array( strpos( $digests[0]['body'], 'Student 3, 30 days' ) < strpos( $digests[0]['body'], 'Student 2, 25 days' ), strpos( $digests[0]['body'], 'Student 2, 25 days' ) < strpos( $digests[0]['body'], 'Student 1, 20 days' ), false === strpos( $digests[0]['body'], 'Student 4' ) ), array( true, true, true ) );
ck( 'and each student also had their own reminder', to_of( sent( 'student-quiet-student' ) ), array( 3, 2, 4, 1 ) );

reset_all();
student( 1, 20 );
student( 2, 25 );
mentor_of( 1, 99 ); // The mentor has no account.
WPCPM_Student_Flags::run_daily( $T );
ck( 'mentor_missing_student_alone: a student whose mentor has no account, and one with none, are reminded alone', array( to_of( sent( 'student-quiet-student' ) ), sent( 'student-quiet-mentor' ) ), array( array( 2, 1 ), array() ) );
ck( 'and the reminder names no mentor', false === strpos( sent( 'student-quiet-student' )[1]['body'], 'Your mentor' ), true );

reset_all();
manager( 60 );
student( 1, 20, $real_inst );
mentor_of( 1, 60 );
WPCPM_Student_Flags::run_daily( $M );
ck( 'a mentor who is also the country\'s Administrator gets the digest and the weekly list, each once', array( to_of( sent( 'student-quiet-mentor' ) ), to_of( sent( 'student-quiet-weekly' ) ), to_of( sent( 'student-quiet-student' ) ) ), array( array( 60 ), array( 60 ), array( 1 ) ) );

echo "\n=== The booking link ===\n";
reset_all();
manager();
student( 1, 20, $real_inst );
person( 77 );
ck( 'booking_link_choice: no mentor and no booking page, the Student Report Card', WPCPM_Student_Flags::booking_link( 1 ), 'https://example.test/student/' );
$GLOBALS['calendly'] = 'https://calendly.test/admin';
ck( 'no mentor and a country with a booking page, that page', WPCPM_Student_Flags::booking_link( 1 ), 'https://calendly.test/admin' );
mentor_of( 1, 77 );
ck( 'a mentor, the calls section of the Student Report Card, whatever the country has', WPCPM_Student_Flags::booking_link( 1 ), 'https://example.test/student/#wpcpm-module-calls' );
WPCPM_Student_Flags::run_daily( $T );
$body = sent( 'student-quiet-student' )[0]['body'];
ck( 'and it is in the reminder, with how long, the dashboard link and the button\'s name', array(
	false !== strpos( $body, 'https://example.test/student/#wpcpm-module-calls' ),
	false !== strpos( $body, '20 days' ),
	false !== strpos( $body, 'Student Report Card:' . "\n" . 'https://example.test/student/' ),
	false !== strpos( $body, 'We\'ve already talked' ),
	false !== strpos( $body, 'Your mentor, Person 77, is there to help' ),
), array( true, true, true, true, true ) );

echo "\n=== Who is never mailed ===\n";
reset_all();
student( 1, 20, $test_inst );
student( 2, 20, $real_inst );
person( 77 );
mentor_of( 1, 77 );
WPCPM_Student_Flags::run_daily( $T );
ck( 'test_institution_never_mailed: a test institution\'s student gets no reminder and their mentor no digest; the real one does', array( to_of( sent( 'student-quiet-student' ) ), sent( 'student-quiet-mentor' ), get_user_meta( 1, WPCPM_Student_Flags::META_REMINDED ) ), array( array( 2 ), array(), '' ) );

reset_all();
student( 1, 20 );
student( 2, 20 );
student( 3, 20 );
student( 4, 20 );
$rows = WPCPM_Student_Flags::rows( $T );
$GLOBALS['programs'][1]['program'] = 'Graduate';
$GLOBALS['programs'][2]['program'] = 'Paused';
$GLOBALS['umeta'][3][ WPCPM_Students_Sync::META_ACTIVE ] = 0;
$GLOBALS['umeta'][4][ WPCPM_Sign_Ins::META_SIGN_IN ] = (string) ( $T - 1 * $D ); // Back since the snapshot.
$out = WPCPM_Student_Flags::remind( $rows, $T );
ck( 'reminder_skips_closed_since_snapshot: a student graduated, paused, deactivated or back since the snapshot is skipped at send time', array( $out, $GLOBALS['mails'], get_user_meta( 1, WPCPM_Student_Flags::META_REMINDED ) ), array( array(), array(), '' ) );

echo "\n=== Stamped before sent, and the lock ===\n";
reset_all();
student( 1, 20 );
student( 2, 25 );
$GLOBALS['mail_throws'] = true;
$thrown = '';
try {
	WPCPM_Student_Flags::run_daily( $T );
} catch ( Exception $e ) {
	$thrown = $e->getMessage();
}
ck( 'a run that stops in the mail layer has stamped the student first, and let go of its lock', array( $thrown, get_user_meta( 2, WPCPM_Student_Flags::META_REMINDED )['for'], WPCPM_Lock::$held, WPCPM_Lock::$released ), array( 'mail layer stopped', $T - 25 * $D, array(), 1 ) );
$GLOBALS['mail_throws'] = false;
WPCPM_Student_Flags::run_daily( $T + 60 );
ck( 'and the next run does not mail that student again, only the other one', to_of( sent( 'student-quiet-student' ) ), array( 1 ) );

reset_all();
student( 1, 20 );
WPCPM_Lock::$held[ WPCPM_Student_Flags::LOCK ] = true;
WPCPM_Student_Flags::run_daily( $T );
ck( 'a run that finds the lock held does nothing, and does not let go of it', array( $GLOBALS['mails'], array_key_exists( WPCPM_Student_Flags::OPT_SNAPSHOT, $GLOBALS['opts'] ), WPCPM_Lock::$released ), array( array(), false, 0 ) );

echo "\n=== The Monday list ===\n";
reset_all();
manager( 60 );
person( 61, array( WPCPM_Roles::CAP_MANAGE ), 'nobody@example.test' ); // Holds the capability, is nobody's country contact.
student( 1, 20, $real_inst );
student( 2, 30, $real_inst );
student( 3, 40, $test_inst );
student( 4, 50 ); // No institution, no Administrator.
WPCPM_Student_Flags::run_daily( $M );
$weekly = sent( 'student-quiet-weekly' );
ck( 'weekly_on_monday_once: on a Monday, the country\'s Administrator gets one list; the test student and the one with no Administrator are not on it', array( to_of( $weekly ), false !== strpos( $weekly[0]['subject'], 'Students gone quiet this week: 2' ), false !== strpos( $weekly[0]['body'], 'Students gone quiet in your country: 2.' ), false === strpos( $weekly[0]['body'], 'Student 3' ), false === strpos( $weekly[0]['body'], 'Student 4' ) ), array( array( 60 ), true, true, true, true ) );
ck( 'the lines are most quiet first, each linking to the student on the Student Report Card, and the card is linked', array( strpos( $weekly[0]['body'], 'Student 2, 33 days: https://example.test/student/?wpcpm_student_view=2' ) < strpos( $weekly[0]['body'], 'Student 1, 23 days: https://example.test/student/?wpcpm_student_view=1' ), false !== strpos( $weekly[0]['body'], "https://example.test/administrators/#wpcpm-quiet-students" ) ), array( true, true ) );
ck( 'the week is noted', $GLOBALS['opts'][ WPCPM_Student_Flags::OPT_WEEKLY ], gmdate( 'o-W', $M ) );
WPCPM_Student_Flags::run_daily( $M + 3600 );
ck( 'a second run that Monday sends no second list', count( sent( 'student-quiet-weekly' ) ), 1 );
WPCPM_Student_Flags::run_daily( $M + 8 * 3600 );
$tuesday = count( sent( 'student-quiet-weekly' ) );
WPCPM_Student_Flags::run_daily( $M + $D );
ck( 'a Tuesday run sends none', array( $tuesday, count( sent( 'student-quiet-weekly' ) ) ), array( 1, 1 ) );
WPCPM_Student_Flags::run_daily( $M + 7 * $D );
ck( 'the next Monday sends the next list', count( sent( 'student-quiet-weekly' ) ), 2 );

reset_all();
manager( 60 );
student( 1, 20, $test_inst );
$GLOBALS['seed_inst'][ $test_inst ]['country'] = $country;
WPCPM_Student_Flags::run_daily( $M );
ck( 'an Administrator with only test students gets no list', sent( 'student-quiet-weekly' ), array() );
$GLOBALS['seed_inst'][ $test_inst ]['country'] = isset( $seed['institutions'][0]['country'][0] ) ? $seed['institutions'][0]['country'][0] : '';

echo "\n=== What the emails say ===\n";
reset_all();
manager( 60 );
person( 77 );
student( 1, 20, $real_inst );
mentor_of( 1, 77 );
WPCPM_Student_Flags::run_daily( $M );
$all_text = '';
foreach ( $GLOBALS['mails'] as $m ) {
	$all_text .= $m['subject'] . "\n" . $m['body'] . "\n";
}
ck( 'the three went', array_map( static function ( $m ) { return $m['id']; }, $GLOBALS['mails'] ), array( 'student-quiet-student', 'student-quiet-mentor', 'student-quiet-weekly' ) );
ck( 'no dash that is not a hyphen, no sentence starting with "Please", no old role name, no placeholder left', array(
	1 === preg_match( '/[\x{2013}\x{2014}]/u', $all_text ),
	1 === preg_match( '/(^|[.!?]\s+)Please\b/m', $all_text ),
	false !== stripos( $all_text, 'program manager' ),
	1 === preg_match( '/\{[a-z_]+\}/', $all_text ),
), array( false, false, false, false ) );
ck( 'the subjects carry the site\'s name and no student\'s name', array(
	$GLOBALS['mails'][0]['subject'], $GLOBALS['mails'][1]['subject'], $GLOBALS['mails'][2]['subject'],
), array( '[Credits] Checking in, it has been 23 days', '[Credits] Some of your students have gone quiet', '[Credits] Students gone quiet this week: 1' ) );
ck( 'the reminder\'s body, in full', $GLOBALS['mails'][0]['body'], implode( "\n\n", array(
	'Hello,',
	'It has been 23 days since we last saw you on Credits, so we wanted to check in and see how you are doing.',
	'Your mentor, Person 77, is there to help, whatever stage you are at.',
	'The Administrator for your country is there to help too. Questions are always welcome.',
	"A short call is the easiest way to get going again. You can book one here:\nhttps://example.test/student/#wpcpm-module-calls",
	"If you have already talked with your mentor or with us, tell us by using the \"We've already talked\" button on your Student Report Card:\nhttps://example.test/student/",
) ) );

echo "\n=== The notice on the Report Cards ===\n";
reset_all();
student( 1, 20 );
person( 77 );
mentor_of( 1, 77 );
WPCPM_Student_Flags::run_daily( $T );
$student_user = new WP_User( 1, 'Student 1' );
$GLOBALS['uid'] = 1;
$notice = WPCPM_Student_Flags::student_notice( $student_user, 1 );
ck( 'the student sees how long it has been, the way to book and the button', array(
	false !== strpos( $notice, 'It has been 20 days since we last saw you here.' ),
	false !== strpos( $notice, 'href="https://example.test/student/#wpcpm-module-calls"' ),
	false !== strpos( $notice, 'name="action" value="wpcpm_student_talked"' ),
), array( true, true, true ) );
$GLOBALS['uid'] = 5;
ck( 'a person who may not press sees the paragraph and no button', array( false !== strpos( WPCPM_Student_Flags::student_notice( $student_user, 5 ), 'Student 1 has not been seen here for 20 days.' ), false === strpos( WPCPM_Student_Flags::student_notice( $student_user, 5 ), '<form' ) ), array( true, true ) );
$GLOBALS['uid'] = 77;
ck( 'the mentor\'s line on the Mentor Report Card carries the button too', array( false !== strpos( WPCPM_Student_Flags::mentee_line( 1 ), 'Not seen on the site for 20 days.' ), false !== strpos( WPCPM_Student_Flags::mentee_line( 1 ), '<form' ) ), array( true, true ) );
ck( 'a student not in the snapshot, or a mentee with no account, has neither', array( WPCPM_Student_Flags::student_notice( new WP_User( 2, 'Other' ), 2 ), WPCPM_Student_Flags::mentee_line( 2 ), WPCPM_Student_Flags::mentee_line( 0 ) ), array( '', '', '' ) );
$GLOBALS['settings']['student_flags_mode'] = 'preview';
ck( 'in Preview a Report Card says nothing', array( WPCPM_Student_Flags::student_notice( $student_user, 1 ), WPCPM_Student_Flags::mentee_line( 1 ) ), array( '', '' ) );

echo "\n=== A closed student and a list from before Off (final review, Minor 2) ===\n";
reset_all();
student( 1, 20 );
person( 77 );
mentor_of( 1, 77 );
WPCPM_Student_Flags::run_daily( $T );
$GLOBALS['programs'][1]['program'] = 'Graduate'; // Graduated since the morning's list.
$GLOBALS['uid'] = 1;
ck( 'notice_skips_closed_student: a student closed since the morning\'s list sees no notice, and their mentor no line', array( WPCPM_Student_Flags::student_notice( new WP_User( 1, 'Student 1' ), 1 ), WPCPM_Student_Flags::mentee_line( 1 ) ), array( '', '' ) );

reset_all();
$GLOBALS['settings']['student_flags_mode'] = 'preview';
student( 1, 50 );
WPCPM_Student_Flags::run_daily( $T - 30 * $D ); // The list made 30 days ago, in Preview.
follow( 'off' );
$GLOBALS['umeta'][1][ WPCPM_Sign_Ins::META_SIGN_IN ] = (string) ( $T - 1 * $D ); // Back, signed in yesterday.
follow( 'on' );
$GLOBALS['uid'] = 1;
ck( 'stale_snapshot_not_back: a list stored before the switch went Off does not come back with On, on the card or on the Student Report Card', array( WPCPM_Student_Flags::snapshot(), WPCPM_Student_Flags::student_notice( new WP_User( 1, 'Student 1' ), 1 ) ), array( array( 'at' => 0, 'rows' => array() ), '' ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
