<?php
/**
 * The first-week checklist card (student automation, Release B).
 *
 * What this pins, and why:
 *
 * - The audience (ruling 13): a student made after the switch first went on, or whose track began in
 *   the 28 days before it, sees the card; a student whose track began 60 days before does not; with
 *   the switch off, or no on-since time, nobody does.
 * - Each item the site ticks is done from its own source: the program row's profile and team, a
 *   recorded sign-in, either report stamp, any call ever booked.
 * - The student ticks only slack, channel and learn: for themself or for a CAP_MANAGE viewer, with the
 *   nonce bound to the student; another student, a bad nonce and any other key write nothing.
 * - Every item done folds the card to one summary line with the list inside a closed details.
 * - The Learn item links the course of the student's status, and the team item links the Team Matcher
 *   page once `WPCPM_Start_Pages::matcher_url()` answers.
 * - The on-since time is written once, the first time the switch is saved on.
 *
 * Run from the plugin root:  php bin/test-student-checklist.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['umeta']    = array();
$GLOBALS['opts']     = array();
$GLOBALS['settings'] = array();
$GLOBALS['hooks']    = array();
$GLOBALS['uid']      = 0;
$GLOBALS['caps']     = array();   // user id => capabilities.
$GLOBALS['program']  = array();
$GLOBALS['signed']   = array();
$GLOBALS['calls']    = array();
$GLOBALS['matcher_url'] = '';

class WP_User {
	public $ID = 0, $user_registered = '', $display_name = '';
	public function __construct( $id, $registered ) { $this->ID = $id; $this->user_registered = $registered; }
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $callback; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['opts'][ $key ] = $value; return true; }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['umeta'][ $id ][ $key ] = $value; return true; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function user_can( $user, $cap ) { return in_array( $cap, isset( $GLOBALS['caps'][ $user->ID ] ) ? $GLOBALS['caps'][ $user->ID ] : array(), true ); }
function current_user_can( $cap ) { return in_array( $cap, isset( $GLOBALS['caps'][ $GLOBALS['uid'] ] ) ? $GLOBALS['caps'][ $GLOBALS['uid'] ] : array(), true ); }
function check_admin_referer( $action ) { if ( empty( $GLOBALS['nonce_ok'] ) || $GLOBALS['nonce_ok'] !== $action ) { throw new Exception( 'nonce' ); } }
function wp_die( $message = '', $title = '', $args = array() ) { throw new Exception( 'die' . ( is_array( $args ) && isset( $args['response'] ) ? $args['response'] : '' ) ); }
function wp_safe_redirect( $url ) { throw new Exception( 'redirect:' . $url ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function wp_create_nonce( $action ) { return 'nonce-' . $action; }
function wp_referer_field( $display = true ) { return '<input type="hidden" name="_wp_http_referer" value="/here/" />'; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s ); }
function esc_attr__( $s ) { return htmlspecialchars( $s ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s ); }
function esc_url( $s ) { return (string) $s; }

class WPCPM_Settings {
	const OPT_NAME = 'wpcpm_settings';
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}
class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}
class WPCPM_Request {
	public static function posted_id( $name ) { return isset( $_POST[ $name ] ) ? (int) $_POST[ $name ] : 0; }
	public static function posted_key( $name, $fallback = '' ) { return isset( $_POST[ $name ] ) ? (string) $_POST[ $name ] : $fallback; }
	public static function back( ...$fallbacks ) { return (string) reset( $fallbacks ); }
}
class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student/'; }
}
class WPCPM_Students_Sync {
	public static function get_program( $id ) { return $GLOBALS['program']; }
}
class WPCPM_Sign_Ins {
	public static function has_signed_in( $id ) { return in_array( $id, $GLOBALS['signed'], true ); }
}
class WPCPM_Mentor_Calls {
	public static function for_student( $id, $upcoming = true ) { return isset( $GLOBALS['calls'][ $id ] ) ? $GLOBALS['calls'][ $id ] : array(); }
}
class WPCPM_Start_Pages {
	public static function matcher_url() { return (string) $GLOBALS['matcher_url']; }
}
class WPCPM_Program {
	public static function course_url( $status ) { return 'WordPress Credits Program 150h' === $status ? 'https://learn.example/course' : ''; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-activity.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-checklist.php';

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

function done_of( $student ) {
	$out = array();
	foreach ( WPCPM_Student_Checklist::items( $student ) as $item ) {
		$out[ $item['key'] ] = $item['done'];
	}
	return $out;
}

function run( $callable ) {
	try {
		$callable();
		return 'returned';
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
}

$on_since = 1800000000;
$GLOBALS['opts'][ WPCPM_Student_Checklist::OPT_ON_SINCE ] = $on_since;
$GLOBALS['settings']['student_checklist_enabled']         = true;

$new_account = new WP_User( 10, gmdate( 'Y-m-d H:i:s', $on_since + 3600 ) );
$old_account = new WP_User( 11, gmdate( 'Y-m-d H:i:s', $on_since - 400 * DAY_IN_SECONDS ) );

echo "=== The audience ===\n";
$GLOBALS['program'] = array();
ck( 'applies_new_account: made after the switch went on', WPCPM_Student_Checklist::applies( $new_account ), true );
$GLOBALS['program'] = array( 'start' => gmdate( 'Y-m-d', $on_since - 20 * DAY_IN_SECONDS ) );
ck( 'applies_recent_start: a track that began 20 days before', WPCPM_Student_Checklist::applies( $old_account ), true );
$GLOBALS['program'] = array( 'start' => gmdate( 'Y-m-d', $on_since - 60 * DAY_IN_SECONDS ) );
ck( 'not_applies_old_student: a track that began 60 days before', WPCPM_Student_Checklist::applies( $old_account ), false );
$GLOBALS['program'] = array();
ck( 'and an old account with no start does not see it', WPCPM_Student_Checklist::applies( $old_account ), false );
$GLOBALS['settings']['student_checklist_enabled'] = false;
ck( 'not_applies_switch_off: a new account sees nothing with the switch off', WPCPM_Student_Checklist::applies( $new_account ), false );
$GLOBALS['settings']['student_checklist_enabled'] = true;
unset( $GLOBALS['opts'][ WPCPM_Student_Checklist::OPT_ON_SINCE ] );
ck( 'and nothing without an on-since time', WPCPM_Student_Checklist::applies( $new_account ), false );
$GLOBALS['opts'][ WPCPM_Student_Checklist::OPT_ON_SINCE ] = $on_since;

echo "\n=== The items the site ticks ===\n";
$student = new WP_User( 20, gmdate( 'Y-m-d H:i:s', $on_since + 10 ) );
ck( 'the keys, in the card\'s order', array_column( WPCPM_Student_Checklist::items( $student ), 'key' ), array( 'profile', 'signed_in', 'report', 'call', 'team', 'slack', 'channel', 'learn' ) );
ck( 'and who ticks each', array_column( WPCPM_Student_Checklist::items( $student ), 'by' ), array( 'site', 'site', 'site', 'site', 'site', 'student', 'student', 'student' ) );
ck( 'a new student has nothing done', array_filter( done_of( $student ) ), array() );
$GLOBALS['program'] = array( 'profile' => 'https://profiles.wordpress.org/x/', 'team' => 'Training', 'program' => 'WordPress Credits Program 150h' );
ck( 'profile_done: the program row holds a profile', done_of( $student )['profile'], true );
ck( 'team_done: the program row holds a team', done_of( $student )['team'], true );
$GLOBALS['signed'] = array( 20 );
ck( 'signed_in_done: a recorded sign-in', done_of( $student )['signed_in'], true );
$GLOBALS['umeta'][20][ WPCPM_Student_Activity::META_REPORT ] = 1800000100;
ck( 'report_done_by_stamp_or_edited: the site\'s save stamp', done_of( $student )['report'], true );
unset( $GLOBALS['umeta'][20][ WPCPM_Student_Activity::META_REPORT ] );
ck( 'and not with neither', done_of( $student )['report'], false );
$GLOBALS['umeta'][20][ WPCPM_Student_Activity::META_EDITED ] = 1800000200;
ck( 'report_done_by_stamp_or_edited: Airtable\'s edit time', done_of( $student )['report'], true );
ck( 'call_done_any_call: none booked', done_of( $student )['call'], false );
$GLOBALS['calls'][20] = array( 501 );
ck( 'call_done_any_call: one booked, whenever', done_of( $student )['call'], true );

echo "\n=== The links ===\n";
$by_key = array();
foreach ( WPCPM_Student_Checklist::items( $student ) as $item ) {
	$by_key[ $item['key'] ] = $item['link'];
}
ck( 'learn_links_course: the Learn item links the course of the status', $by_key['learn'], 'https://learn.example/course' );
ck( 'the team item links nothing while the Team Matcher page does not exist', $by_key['team'], '' );
$GLOBALS['matcher_url'] = 'https://example.test/find-your-team/';
$with_page = array();
foreach ( WPCPM_Student_Checklist::items( $student ) as $item ) {
	$with_page[ $item['key'] ] = $item['link'];
}
ck( 'the team item links the Team Matcher page once it exists', $with_page['team'], 'https://example.test/find-your-team/' );
$GLOBALS['matcher_url'] = '';
ck( 'the channel item links nothing', $by_key['channel'], '' );

echo "\n=== The ticks ===\n";
$tick = function ( $key, $uid, $student_id, $nonce_ok = true ) {
	$GLOBALS['uid']      = $uid;
	$_POST               = array( 'student' => (string) $student_id, 'item' => $key );
	$GLOBALS['nonce_ok'] = $nonce_ok ? WPCPM_Student_Checklist::ACTION_TICK . '_' . $student_id : 'other';
	return run( array( 'WPCPM_Student_Checklist', 'handle_tick' ) );
};
$GLOBALS['caps'][20] = array();
$GLOBALS['caps'][90] = array( WPCPM_Roles::CAP_MANAGE );
ck( 'student_ticks_slack_channel_learn: the student ticks slack', array( $tick( 'slack', 20, 20 ), get_user_meta( 20, WPCPM_Student_Checklist::META_TICKS ) ), array( 'redirect:https://example.test/student/', array( 'slack' ) ) );
$tick( 'channel', 20, 20 );
$tick( 'learn', 20, 20 );
ck( 'and the other two', get_user_meta( 20, WPCPM_Student_Checklist::META_TICKS ), array( 'slack', 'channel', 'learn' ) );
$tick( 'slack', 20, 20 );
ck( 'a second tick of one key adds nothing', get_user_meta( 20, WPCPM_Student_Checklist::META_TICKS ), array( 'slack', 'channel', 'learn' ) );
$GLOBALS['umeta'][21] = array();
ck( 'a key the site ticks is refused quietly and writes nothing', array( $tick( 'profile', 21, 21 ), get_user_meta( 21, WPCPM_Student_Checklist::META_TICKS ) ), array( 'redirect:https://example.test/student/', '' ) );
ck( 'another student cannot tick for this one', array( $tick( 'slack', 22, 21 ), get_user_meta( 21, WPCPM_Student_Checklist::META_TICKS ) ), array( 'die403', '' ) );
ck( 'a bad nonce stops first', array( $tick( 'slack', 21, 21, false ), get_user_meta( 21, WPCPM_Student_Checklist::META_TICKS ) ), array( 'nonce', '' ) );
ck( 'a holder of the manage capability ticks for the student', array( $tick( 'channel', 90, 21 ), get_user_meta( 21, WPCPM_Student_Checklist::META_TICKS ) ), array( 'redirect:https://example.test/student/', array( 'channel' ) ) );

echo "\n=== The card ===\n";
$viewer_self = $student;
$GLOBALS['umeta'][20][ WPCPM_Student_Checklist::META_TICKS ] = array();
$html = WPCPM_Student_Checklist::render( $student, $viewer_self );
ck( 'the heading', false !== strpos( $html, 'Your first weeks' ), true );
ck( 'the card is a student section, as the others', 0 === strpos( $html, '<section class="wpcpm-student__section wpcpm-checklist"' ), true );
ck( 'done and to do show in words and in form', array( substr_count( $html, 'is-done' ), substr_count( $html, 'is-todo' ), substr_count( $html, '>Done<' ), substr_count( $html, '>To do<' ) ), array( 5, 3, 5, 3 ) );
ck( 'three tick buttons, one for each item the student ticks, each posting the action', array( substr_count( $html, 'name="action" value="wpcpm_checklist_tick"' ), substr_count( $html, 'value="nonce-wpcpm_checklist_tick_20"' ) ), array( 3, 3 ) );
ck( 'unfinished: no fold', false === strpos( $html, '<details' ), true );
$other = new WP_User( 77, gmdate( 'Y-m-d H:i:s' ) );
ck( 'a reader who is neither the student nor a manager gets no buttons', false === strpos( WPCPM_Student_Checklist::render( $student, $other ), '<form' ), true );
$manager = new WP_User( 90, gmdate( 'Y-m-d H:i:s' ) );
ck( 'a manager viewing as the student gets the buttons', substr_count( WPCPM_Student_Checklist::render( $student, $manager ), '<form' ), 3 );
ck( 'a student the card does not apply to gets an empty string', WPCPM_Student_Checklist::render( $old_account, $old_account ), '' );

$GLOBALS['umeta'][20][ WPCPM_Student_Checklist::META_TICKS ] = array( 'slack', 'channel', 'learn' );
$done = WPCPM_Student_Checklist::render( $student, $viewer_self );
ck( 'all_done_folds: one summary line, then the list inside a closed details', array( 1 === substr_count( $done, 'wpcpm-checklist__summary' ), 1 === preg_match( '#<details class="wpcpm-checklist__fold"><summary>[^<]+</summary><ul.*</ul></details>#s', $done ), false === strpos( $done, '<details open' ), false === strpos( $done, '<form' ), 8 === substr_count( $done, 'is-done' ) ), array( true, true, true, true, true ) );

echo "\n=== The switch ===\n";
$GLOBALS['hooks'] = array();
WPCPM_Student_Checklist::init();
ck( 'init hangs the tick on admin-post and follows the settings save', array( isset( $GLOBALS['hooks']['admin_post_wpcpm_checklist_tick'] ), isset( $GLOBALS['hooks']['update_option_wpcpm_settings'] ) ), array( true, true ) );
unset( $GLOBALS['opts'][ WPCPM_Student_Checklist::OPT_ON_SINCE ] );
WPCPM_Student_Checklist::follow_switch( array( 'student_checklist_enabled' => false ), array( 'student_checklist_enabled' => false ) );
ck( 'a save with the switch off writes no time', isset( $GLOBALS['opts'][ WPCPM_Student_Checklist::OPT_ON_SINCE ] ), false );
WPCPM_Student_Checklist::follow_switch( array( 'student_checklist_enabled' => false ), array( 'student_checklist_enabled' => true ) );
$first = $GLOBALS['opts'][ WPCPM_Student_Checklist::OPT_ON_SINCE ];
ck( 'the first save on writes the time', $first > 0, true );
$GLOBALS['opts'][ WPCPM_Student_Checklist::OPT_ON_SINCE ] = 5;
WPCPM_Student_Checklist::follow_switch( array( 'student_checklist_enabled' => false ), array( 'student_checklist_enabled' => true ) );
ck( 'and a later save on keeps it', $GLOBALS['opts'][ WPCPM_Student_Checklist::OPT_ON_SINCE ], 5 );

echo "\n" . ( $fail ? "FAILED ({$fail})\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
