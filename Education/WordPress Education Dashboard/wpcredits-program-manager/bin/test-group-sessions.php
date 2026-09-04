<?php
/**
 * Group sessions: capacity, attendees, and the note that lands on all of them.
 *
 * The interesting property is that **a session is the same post type as a one-to-one call**, with a
 * capacity and repeated attendee rows. That is what made the feature small - the diary, the reminder
 * sweep and the slot blocking all needed no changes - and it is also what could break the calls that
 * came before it. So the first thing asserted here is that an unmarked call still reads as a
 * one-to-one call with one attendee.
 *
 * Run from the plugin root:  php bin/test-group-sessions.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['pmeta'] = array();
$GLOBALS['posts'] = array();
$GLOBALS['umeta'] = array();
$GLOBALS['caps']  = array();
$GLOBALS['uid']   = 0;

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $roles = array();
	public function __construct( $id = 0, $name = '' ) { $this->ID = $id; $this->display_name = $name; }
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_content = '', $post_type = 'wpcpm_mentor_call', $post_status = 'publish';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {} function register_post_type() {}
function wp_json_encode( $v ) { return json_encode( $v ); }
function number_format_i18n( $n, $d = 0 ) { return (string) round( $n, $d ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function add_option( $k, $v, $x = '', $a = 'yes' ) {
	if ( isset( $GLOBALS['opts'][ $k ] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return new WP_User( $GLOBALS['uid'], 'Viewer' ); }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
require_once __DIR__ . '/stubs/caps.php';
function get_user_by( $f, $v ) { return new WP_User( (int) $v, 'User ' . (int) $v ); }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function get_post( $id = null ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_time( $f, $gmt = false, $post = null ) { return time() - DAY_IN_SECONDS; }
function get_posts( $a = array() ) { return array(); }
function wp_insert_post( $a, $error = false ) {
	static $next = 500;
	$post               = new WP_Post();
	$post->ID           = ++$next;
	$post->post_title   = $a['post_title'] ?? '';
	$post->post_content = $a['post_content'] ?? '';
	$post->post_type    = $a['post_type'] ?? 'post';
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}

/**
 * Post meta that behaves like WordPress's: repeated rows, and `true` returns the first.
 *
 * The whole attendee model rests on this distinction, so faking it faithfully is the point of the
 * harness. A stub that stored one value per key would make every assertion below meaningless.
 */
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();

	if ( $single ) {
		return $rows ? $rows[0] : '';
	}

	return $rows;
}
function add_post_meta( $id, $key, $value, $unique = false ) {
	$GLOBALS['pmeta'][ (int) $id ][ $key ][] = $value;
	return true;
}
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value );
	return true;
}
function delete_post_meta( $id, $key, $value = '' ) {
	if ( '' === $value ) {
		unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] );
		return true;
	}

	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();

	foreach ( $rows as $i => $row ) {
		// Loose, like WordPress: meta comes back from the database as strings.
		if ( (string) $row === (string) $value ) {
			unset( $rows[ $i ] );
		}
	}

	$GLOBALS['pmeta'][ (int) $id ][ $key ] = array_values( $rows );

	return true;
}

$GLOBALS['opts'] = array();

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-settings.php';
require_once __DIR__ . '/../includes/class-wpcpm-flash.php';
require_once __DIR__ . '/../includes/class-wpcpm-airtable.php';
require_once __DIR__ . '/../includes/class-wpcpm-wporg-profile.php';
require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
// `user_can_access()` validates a record ID through the mentors sync, so the real one is loaded
// rather than stubbed - a stub would decide the access question this suite is asking about.
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-notes.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
// Loaded for its MIN_MINUTES / MAX_MINUTES, which are what the length field's grid is built from.
require_once __DIR__ . '/../includes/modules/class-wpcpm-group-sessions.php';

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
 * A call, one-to-one or group.
 *
 * @param int $capacity 1 for a one-to-one call, more for a session.
 * @return int Post ID.
 */
function make_call( $capacity = 1 ) {
	$id = wp_insert_post( array( 'post_type' => WPCPM_Mentor_Calls::POST_TYPE, 'post_title' => 'Call' ) );

	update_post_meta( $id, WPCPM_Mentor_Calls::META_START, time() + DAY_IN_SECONDS );
	update_post_meta( $id, WPCPM_Mentor_Calls::META_END, time() + DAY_IN_SECONDS + 1800 );
	update_post_meta( $id, WPCPM_Mentor_Calls::META_MENTOR, 20 );

	if ( $capacity > 1 ) {
		update_post_meta( $id, WPCPM_Mentor_Calls::META_CAPACITY, $capacity );
	}

	return $id;
}

echo "=== A call from before group sessions is unchanged ===\n";

$one = make_call();
update_post_meta( $one, WPCPM_Mentor_Calls::META_STUDENT, 31 );
update_post_meta( $one, WPCPM_Mentor_Calls::META_RECORD, 'recSTUDENT1234567' );

ck( 'an unmarked call has a capacity of one', WPCPM_Mentor_Calls::capacity( $one ), 1 );
ck( 'its one student reads as one attendee', WPCPM_Mentor_Calls::attendees( $one ), array( 31 ) );
ck( 'and it is not a group', WPCPM_Mentor_Calls::details( get_post( $one ) )['is_group'], false );
ck( 'the single-value read still returns that student',
    (int) get_post_meta( $one, WPCPM_Mentor_Calls::META_STUDENT, true ), 31 );
ck( 'a full one-to-one call has no room', WPCPM_Mentor_Calls::has_room( $one ), false );

echo "\n=== Capacity and joining ===\n";

$group = make_call( 3 );

ck( 'an empty session has room', WPCPM_Mentor_Calls::has_room( $group ), true );
ck( 'and reports its places', WPCPM_Mentor_Calls::details( get_post( $group ) )['places'], 3 );

WPCPM_Mentor_Calls::add_attendee( $group, 31, 'recSTUDENT0000001' );
WPCPM_Mentor_Calls::add_attendee( $group, 32, 'recSTUDENT0000002' );

ck( 'attendees accumulate rather than replacing each other',
    WPCPM_Mentor_Calls::attendees( $group ), array( 31, 32 ) );
ck( 'their records accumulate too',
    WPCPM_Mentor_Calls::attendee_records( $group ),
    array( 'recSTUDENT0000001', 'recSTUDENT0000002' ) );
ck( 'places left counts down', WPCPM_Mentor_Calls::details( get_post( $group ) )['places'], 1 );

ck( 'joining twice is refused rather than double-counted',
    array( WPCPM_Mentor_Calls::add_attendee( $group, 31, 'recSTUDENT0000001' ), count( WPCPM_Mentor_Calls::attendees( $group ) ) ),
    array( false, 2 ) );

WPCPM_Mentor_Calls::add_attendee( $group, 33, 'recSTUDENT0000003' );

ck( 'a full session has no room', WPCPM_Mentor_Calls::has_room( $group ), false );
ck( 'and reports no places', WPCPM_Mentor_Calls::details( get_post( $group ) )['places'], 0 );

echo "\n=== Leaving frees exactly one place ===\n";

WPCPM_Mentor_Calls::remove_attendee( $group, 32, 'recSTUDENT0000002' );

ck( 'the one who left is gone and the others stay',
    WPCPM_Mentor_Calls::attendees( $group ), array( 31, 33 ) );
ck( 'their record went with them',
    WPCPM_Mentor_Calls::attendee_records( $group ),
    array( 'recSTUDENT0000001', 'recSTUDENT0000003' ) );
ck( 'and there is room again', WPCPM_Mentor_Calls::has_room( $group ), true );

// `details()` keeps `student_id` for every caller written before sessions existed.
ck( 'student_id is the first attendee, for older callers',
    WPCPM_Mentor_Calls::details( get_post( $group ) )['student_id'], 31 );

echo "\n=== One note, on every attendee ===\n";

$GLOBALS['uid']  = 20;
$GLOBALS['caps'] = true; // A program manager, so access to every record is granted.

$note = WPCPM_Mentor_Notes::add_for_records(
	$group,
	'We went through the release cycle.',
	WPCPM_Mentor_Calls::attendee_records( $group )
);

ck( 'the note saved', is_wp_error( $note ), false );

// The property the whole design rests on: one post, one meta row per attendee, which is what makes
// it show on every card and count in every triage.
ck( 'it is one post carrying one record row per attendee',
    get_post_meta( (int) $note, WPCPM_Mentor_Notes::META_STUDENT, false ),
    array( 'recSTUDENT0000001', 'recSTUDENT0000003' ) );

ck( 'and it remembers which session it came from',
    (int) get_post_meta( (int) $note, WPCPM_Mentor_Notes::META_SESSION, true ), $group );

$empty = WPCPM_Mentor_Notes::add_for_records( $group, '   ', array( 'recSTUDENT0000001' ) );
ck( 'an empty note is refused', is_wp_error( $empty ) ? $empty->get_error_code() : '', 'wpcpm_note_empty' );

$nobody = WPCPM_Mentor_Notes::add_for_records( $group, 'Nobody came.', array() );
ck( 'a note with no attendees is refused',
    is_wp_error( $nobody ) ? $nobody->get_error_code() : '', 'wpcpm_note_nobody' );

// Partial permission is not something one shared note can honour, so it is refused outright.
$GLOBALS['caps'] = false;
$denied          = WPCPM_Mentor_Notes::add_for_records( $group, 'Not mine.', array( 'recSTUDENT0000009' ) );

ck( 'a writer without access to everybody is refused',
    is_wp_error( $denied ) ? $denied->get_error_code() : '', 'wpcpm_note_denied' );

// ---------------------------------------------------------------------------------------------
// The length field's grid.
//
// A number input's `step` counts from its `min`, not from zero. With `min="1" step="5"` the valid
// lengths were 1, 6, 11 … 56, 61 - so a browser refused **60**, which was the field's own default
// value, while 61 and 56 went through. Reported by Celi Garoe in prerelease testing
// (WordPress/WPCredits#166). Asserting the grid rather than the attributes, because the property
// that matters is which numbers a mentor can actually type.

/**
 * Would a browser accept this length, given the field's min/max/step?
 *
 * @param int $minutes Length a mentor typed.
 * @return bool
 */
function grid_accepts( $minutes ) {
	if ( $minutes < WPCPM_Group_Sessions::MIN_MINUTES || $minutes > WPCPM_Group_Sessions::MAX_MINUTES ) {
		return false;
	}

	// `step` is the floor here, which is what puts every multiple of it on the grid.
	return 0 === ( $minutes - WPCPM_Group_Sessions::MIN_MINUTES ) % WPCPM_Group_Sessions::MIN_MINUTES;
}

foreach ( array( 15, 30, 45, 60, 90, 120 ) as $length ) {
	ck( sprintf( 'a %d-minute session is on the grid', $length ), grid_accepts( $length ), true );
}

ck( 'the shortest allowed length is on the grid', grid_accepts( WPCPM_Group_Sessions::MIN_MINUTES ), true );
ck( 'so is the longest', grid_accepts( WPCPM_Group_Sessions::MAX_MINUTES ), true );
ck( 'a length off the grid is refused', grid_accepts( 61 ), false );
ck( 'nothing shorter than the floor', grid_accepts( 1 ), false );
ck( 'nothing past the ceiling', grid_accepts( WPCPM_Group_Sessions::MAX_MINUTES + 5 ), false );

// The form's default has to be a length the form itself accepts - that was the whole of the bug.
ck( 'the default length the form offers is one it accepts', grid_accepts( 60 ), true );

// The checks above only hold while the field's `min` and `step` are the *same* number, so that is
// asserted on the markup itself - the grid maths cannot see a template edited back to two literals.
$field = '';

if ( preg_match( '/<input type="number" id="wpcpm-session-minutes"[^>]*>/', file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-group-sessions.php' ), $m ) ) {
	$field = $m[0];
}

preg_match( '/ min="([^"]+)"/', $field, $min );
preg_match( '/ step="([^"]+)"/', $field, $step );

ck( 'the length field takes its floor and its step from one value',
    isset( $min[1], $step[1] ) && $min[1] === $step[1], true );

/* ---- changing a session that has already been announced ------------------ */

echo "\n=== Editing a session ===\n";

// Source-level, because the handler reads $_POST and redirects and this suite has no harness for
// that. Each of these stands for a rule that is invisible from outside until it is wrong.
$edit = file_get_contents( dirname( __DIR__ ) . '/includes/modules/class-wpcpm-group-sessions.php' );
$body = substr( $edit, strpos( $edit, 'public static function handle_edit()' ) );
$body = substr( $body, 0, strpos( $body, "\n\t}\n" ) );

ck( 'the nonce is keyed to the session, not shared by every session on the page',
    false !== strpos( $body, 'check_admin_referer( self::ACTION_EDIT . \'_\' . $call_id )' ), true );
ck( 'the mentor is read from the session rather than from the form',
    false !== strpos( $body, 'get_post_meta( $call->ID, WPCPM_Mentor_Calls::META_MENTOR, true )' ), true );
ck( 'and the same gate as creating one decides who may',
    false !== strpos( $body, 'WPCPM_Mentor_Availability::user_can_edit( $mentor_id )' ), true );
ck( 'the places cannot fall below the students already on it',
    false !== strpos( $body, '$capacity < $taken' ) && false !== strpos( $body, "bounce( 'session-shrink' )" ), true );
ck( 'the clash test is told which session is being moved, or it would clash with itself',
    false !== strpos( $body, 'self::clashes_with_another( $mentor_id, $start_ts, $call->ID )' ), true );
ck( 'a start in the past is refused, as it is when creating one',
    false !== strpos( $body, "bounce( 'session-past' )" ), true );
ck( 'the revision climbs only when the time actually moved',
    false !== strpos( $body, '$start_ts !== $was_start || $end_ts !== $was_end' ), true );
ck( 'and that is the only path that emails everybody on it',
    substr_count( $body, 'notify_session_moved' ), 1 );

// The query behind the clash test has to leave this session out of its own answer.
$clash = substr( $edit, strpos( $edit, 'private static function clashes_with_another' ) );
$clash = substr( $clash, 0, strpos( $clash, "\n\t}\n" ) );
ck( 'the exclusion is in the query rather than filtered afterwards',
    false !== strpos( $clash, "'exclude'" ) && false !== strpos( $clash, '(int) $except' ), true );

// The form the mentor sees.
$form = substr( $edit, strpos( $edit, 'private static function render_edit_form' ) );
$form = substr( $form, 0, strpos( $form, "\n\t}\n" ) );
ck( 'the form posts the edit action with the session it belongs to',
    false !== strpos( $form, "self::ACTION_EDIT . '_' . $session->ID" ) && false !== strpos( $form, 'name="session"' ), true );
ck( 'the places field will not let the mentor drag below what is taken',
    false !== strpos( $form, 'max( (int) self::MIN_CAPACITY, $taken )' ), true );
ck( 'the length field keeps the same grid the create form uses',
    false !== strpos( $form, 'self::MIN_MINUTES' ) && false !== strpos( $form, 'step=' ), true );
ck( 'the topic is escaped for a textarea rather than for an attribute',
    false !== strpos( $form, 'esc_textarea(' ), true );
ck( 'and a session with students on it says the email will go out',
    false !== strpos( $form, 'emailed a new invitation' ), true );

// Both new outcomes have something to say to the mentor.
$calls = file_get_contents( dirname( __DIR__ ) . '/includes/modules/class-wpcpm-mentor-calls.php' );
foreach ( array( 'session-updated', 'session-shrink' ) as $wpcpm_status ) {
	ck( sprintf( 'the %s outcome has a message', $wpcpm_status ), false !== strpos( $calls, "'" . $wpcpm_status . "'" ), true );
}
ck( 'the moved notice sends a REQUEST carrying the revision, not a cancellation',
    false !== strpos( $calls, 'WPCPM_ICS::METHOD_REQUEST, $mentor, $student, $recipient, $revision' ), true );
ck( 'and skips whoever moved it, who already knows',
    false !== strpos( $calls, '(int) $student->ID === (int) $actor' ), true );


printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
