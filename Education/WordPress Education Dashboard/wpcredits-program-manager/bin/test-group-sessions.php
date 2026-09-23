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
// The viewer's roles come from `$GLOBALS['roles']`, so a mentor who is not a program manager can be
// the one writing a note (the deep check of 1.109.1, SESSIONS-7).
function wp_get_current_user() { $user = new WP_User( $GLOBALS['uid'], 'Viewer' ); $user->roles = $GLOBALS['roles'][ $GLOBALS['uid'] ] ?? array(); return $user; }
/**
 * Users by exact meta match, as `WPCPM_Mentor_Calls::mentor_for_student()` asks for a mentor by the
 * record the student's card names, answering IDs.
 *
 * @param array $args Query arguments.
 * @return int[]
 */
function get_users( $args = array() ) {
	$out = array();

	foreach ( $GLOBALS['umeta'] as $id => $meta ) {
		if ( isset( $args['meta_key'] ) && ( $meta[ $args['meta_key'] ] ?? null ) !== ( $args['meta_value'] ?? null ) ) {
			continue;
		}

		$out[] = (int) $id;
	}

	return $out;
}
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
require_once __DIR__ . '/stubs/caps.php';
function get_user_by( $f, $v ) { return new WP_User( (int) $v, 'User ' . (int) $v ); }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_post( $id = null ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_time( $f, $gmt = false, $post = null ) { return time() - DAY_IN_SECONDS; }
function get_post_status( $p = null ) { $post = get_post( is_object( $p ) ? $p->ID : $p ); return $post ? $post->post_status : false; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
/**
 * Posts as WordPress would query them, for the shapes the calls module asks: a post type, a status,
 * an exclusion, meta clauses that are equal, at least or between, and an order by a numeric meta.
 * Faithful because the series readers are queries, and a stub that answered nothing would prove
 * nothing about them. A clause holds when any of the post's rows for its key satisfies it, as the
 * join WordPress writes does: a session's attendees are one row each, and a student who joined
 * second is found by the same query as the first (the deep check of 1.109.1, SESSIONS-7).
 */
function get_posts( $a = array() ) {
	$out = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( isset( $a['post_type'] ) && $post->post_type !== $a['post_type'] ) { continue; }
		if ( isset( $a['post_status'] ) && $post->post_status !== $a['post_status'] ) { continue; }
		if ( isset( $a['exclude'] ) && in_array( $post->ID, array_map( 'intval', (array) $a['exclude'] ), true ) ) { continue; }

		$ok = true;

		foreach ( isset( $a['meta_query'] ) ? $a['meta_query'] : array() as $k => $clause ) {
			if ( 'relation' === $k ) { continue; }
			$compare = isset( $clause['compare'] ) ? $clause['compare'] : '=';
			$ok      = false;
			foreach ( get_post_meta( $post->ID, $clause['key'], false ) as $value ) {
				if ( 'BETWEEN' === $compare ) { $ok = (int) $value >= (int) $clause['value'][0] && (int) $value <= (int) $clause['value'][1]; }
				elseif ( '>=' === $compare ) { $ok = (int) $value >= (int) $clause['value']; }
				else { $ok = (string) $value === (string) $clause['value']; }
				if ( $ok ) { break; }
			}
			if ( ! $ok ) { break; }
		}

		if ( $ok ) { $out[] = $post; }
	}

	if ( isset( $a['meta_key'] ) ) {
		$key  = $a['meta_key'];
		$desc = isset( $a['order'] ) && 'DESC' === $a['order'];
		usort( $out, function ( $x, $y ) use ( $key, $desc ) {
			$d = (int) get_post_meta( $x->ID, $key, true ) - (int) get_post_meta( $y->ID, $key, true );
			return $desc ? -$d : $d;
		} );
	}

	if ( isset( $a['fields'] ) && 'ids' === $a['fields'] ) { return array_map( function ( $p ) { return $p->ID; }, $out ); }

	return $out;
}
function wp_insert_post( $a, $error = false ) {
	static $next = 500;
	$post               = new WP_Post();
	$post->ID           = ++$next;
	$post->post_title   = $a['post_title'] ?? '';
	$post->post_content = $a['post_content'] ?? '';
	$post->post_type    = $a['post_type'] ?? 'post';
	$post->post_status  = $a['post_status'] ?? 'publish';
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
// Real, for the same reason as the mentors sync: whose student somebody is decides what their list
// holds and whose notes may name them, and a stub would answer that question itself (SESSIONS-7).
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-dashboard.php';
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
	$id = wp_insert_post( array( 'post_type' => WPCPM_Mentor_Calls::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Call' ) );

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
// value, while 61 and 56 went through. Reported by a mentor in prerelease testing
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
    false !== strpos( $body, 'self::clashes_with_another( $mentor_id, $start_ts, $end_ts, $call->ID )' ), true );
ck( 'a start in the past is refused, as it is when creating one',
    false !== strpos( $body, "bounce( 'session-past' )" ), true );
ck( 'the revision climbs only when the time actually moved',
    false !== strpos( $body, '$start_ts !== $was_start || $end_ts !== $was_end' ), true );
ck( 'and that is the only path that emails everybody on it',
    substr_count( $body, 'notify_session_moved' ), 1 );

// The query behind the clash test has to leave this session out of its own answer: the clash test
// hands the session to the diary read, whose query excludes it (SESSIONS-4 moved the query there;
// the read itself is proved against the post model below).
$clash = substr( $edit, strpos( $edit, 'private static function clashes_with_another' ) );
$clash = substr( $clash, 0, strpos( $clash, "\n\t}\n" ) );
$calls_src = file_get_contents( dirname( __DIR__ ) . '/includes/modules/class-wpcpm-mentor-calls.php' );
$spans     = substr( $calls_src, strpos( $calls_src, 'public static function taken_spans' ) );
$spans = substr( $spans, 0, strpos( $spans, "\n\t}\n" ) );
ck( 'the exclusion is in the query rather than filtered afterwards',
    false !== strpos( $clash, 'taken_spans( $mentor_id, $start_ts, $end_ts, (int) $except )' ) && false !== strpos( $spans, "'exclude'" ) && false !== strpos( $spans, '(int) $except' ), true );

// The form the mentor sees.
$form = substr( $edit, strpos( $edit, 'private static function render_edit_form' ) );
$form = substr( $form, 0, strpos( $form, "\n\t}\n" ) );
// Single-quoted: in double quotes PHP read `$session->ID` as this suite's own variable and
// looked for a needle ending in the empty string, which passed for the wrong reason.
ck( 'the form posts the edit action with the session it belongs to',
    false !== strpos( $form, 'self::ACTION_EDIT . \'_\' . $session->ID' ) && false !== strpos( $form, 'name="session"' ), true );
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


echo "\n=== A series is a tag on ordinary sessions (1.108.0) ===\n";

// Three sessions planned together, a week apart, and a lone one between them.
$first  = make_call( 4 );
$second = make_call( 4 );
$third  = make_call( 4 );
$alone  = make_call( 4 );
update_post_meta( $second, WPCPM_Mentor_Calls::META_START, time() + 8 * DAY_IN_SECONDS );
update_post_meta( $third, WPCPM_Mentor_Calls::META_START, time() + 15 * DAY_IN_SECONDS );
update_post_meta( $alone, WPCPM_Mentor_Calls::META_START, time() + 3 * DAY_IN_SECONDS );
foreach ( array( $first, $second, $third ) as $member ) {
	update_post_meta( $member, WPCPM_Group_Sessions::META_SERIES, $first );
}

ck( 'a member names its series by the first session, and a lone session names none',
    array( WPCPM_Group_Sessions::series_of( $first ), WPCPM_Group_Sessions::series_of( $third ), WPCPM_Group_Sessions::series_of( $alone ) ),
    array( $first, $first, 0 ) );

ck( 'the members of a series come soonest first, the lone session left out',
    array_map( function ( $p ) { return $p->ID; }, WPCPM_Group_Sessions::series_members( $first ) ),
    array( $first, $second, $third ) );

ck( 'a series that is not one answers nothing',
    array( WPCPM_Group_Sessions::series_members( 0 ), WPCPM_Group_Sessions::series_members( 424242 ) ),
    array( array(), array() ) );

// Canceling a session trashes the post rather than deleting it, and every reader asks for the
// private ones, so this is the drop-out that actually happens (the final review of 1.108.0).
$GLOBALS['posts'][ $second ]->post_status = 'trash';

ck( 'a canceled member drops out by itself, since a trashed call is no longer a private one',
    array_map( function ( $p ) { return $p->ID; }, WPCPM_Group_Sessions::series_members( $first ) ),
    array( $first, $third ) );

// A one-to-one call carrying the tag is not a member: nothing writes one, and a series is sessions
// (the deep check of 1.109.1, SESSIONS-13: dropping the capacity test passed every suite).
$single = make_call();
update_post_meta( $single, WPCPM_Group_Sessions::META_SERIES, $first );
update_post_meta( $single, WPCPM_Mentor_Calls::META_START, time() + 9 * DAY_IN_SECONDS );

ck( 'a call of one place carrying the series tag is left out of the series',
    array_map( function ( $p ) { return $p->ID; }, WPCPM_Group_Sessions::series_members( $first ) ),
    array( $first, $third ) );

wp_delete_post( $single, true );
wp_delete_post( $second, true );

ck( 'and one deleted outright drops out too, since it is no longer a call post at all',
    array_map( function ( $p ) { return $p->ID; }, WPCPM_Group_Sessions::series_members( $first ) ),
    array( $first, $third ) );

$mentor_sessions = WPCPM_Group_Sessions::for_mentor( 20 );
$groups          = WPCPM_Group_Sessions::grouped( $mentor_sessions );

ck( 'the lists group a series under its first upcoming session, in date order, a lone session on its own',
    array_map( function ( $g ) { return array( $g['series'], array_map( function ( $p ) { return $p->ID; }, $g['sessions'] ) ); }, $groups ),
    array( array( 0, array( $group ) ), array( $first, array( $first, $third ) ), array( 0, array( $alone ) ) ) );

echo "\n=== An outcome that carries its detail (1.108.0) ===\n";

$GLOBALS['uid'] = 31;
WPCPM_Flash::set( 'call', array( 'series-past', '2026-10-06' ) );

ck( 'the flag and its detail are read apart, and the sentence names the detail',
    array( WPCPM_Mentor_Calls::status(), WPCPM_Mentor_Calls::args(), WPCPM_Mentor_Calls::message( 'series-past', array( '2026-10-06' ) ) ),
    array( 'series-past', array( '2026-10-06' ), array( 'error', 'One of the dates has passed: 2026-10-06.' ) ) );

ck( 'a flag with no detail reads as it always did',
    array( WPCPM_Mentor_Calls::message( 'session-full' ), WPCPM_Mentor_Calls::message( 'series-joined-some', array( 5, 6, 1 ) ), WPCPM_Mentor_Calls::message( 'nonsense' ) ),
    array( array( 'error', 'That session filled up while you were reading it.' ), array( 'success', 'You are on 5 of the 6 sessions; 1 had no place left.' ), array() ) );

// A flash is user meta, and one holding fewer arguments than its sentence takes is a malformed
// flash rather than an impossible one. On PHP 8 `vsprintf()` throws where 7.4 warned, so this
// used to take the whole dashboard down with it.
$short = WPCPM_Mentor_Calls::message( 'series-joined-some', array( 1 ) );

ck( 'a flag whose arguments are short of its placeholders still answers a sentence',
    array( $short[0], false !== strpos( $short[1], 'You are on' ) ),
    array( 'success', true ) );

echo "\n=== Planning a series: the dates, all or nothing (1.108.0) ===\n";

$riga = new DateTimeZone( 'Europe/Riga' );
$now  = strtotime( '2026-10-01 12:00:00 UTC' );

$three = WPCPM_Group_Sessions::plan_dates( array( '2026-10-13', '2026-10-06', '2026-10-20' ), '18:00', $riga, $now );

ck( 'three dates at one time become three starts, soonest first, on the mentor\'s clock',
    array( $three['refused'], array_map( function ( $ts ) { return gmdate( 'Y-m-d H:i', $ts ); }, $three['starts'] ) ),
    array( '', array( '2026-10-06 15:00', '2026-10-13 15:00', '2026-10-20 15:00' ) ) );

$across = WPCPM_Group_Sessions::plan_dates( array( '2026-10-24', '2026-10-31' ), '10:00', $riga, $now );

ck( 'a series keeps its clock time across the change from summer time, so a week apart is 169 hours, not 168',
    array( $across['refused'], ( $across['starts'][1] - $across['starts'][0] ) / HOUR_IN_SECONDS ),
    array( '', 169 ) );

// Riga jumps from 03:00 to 04:00 on 28 March 2027, so 03:30 is an hour that does not happen there
// that day. Silently rolled forward, the session would start at 04:30 and nobody would be told.
ck( 'a time the clocks jump over on one of the dates refuses the whole list, naming that date',
    WPCPM_Group_Sessions::plan_dates( array( '2027-03-28' ), '03:30', $riga, $now ),
    array( 'starts' => array(), 'refused' => 'when', 'date' => '2027-03-28' ) );

ck( 'and the hour after the jump, which does happen, is planned',
    array(
        WPCPM_Group_Sessions::plan_dates( array( '2027-03-28' ), '05:30', $riga, $now )['refused'],
        gmdate( 'Y-m-d H:i', WPCPM_Group_Sessions::plan_dates( array( '2027-03-28' ), '05:30', $riga, $now )['starts'][0] ),
    ),
    array( '', '2027-03-28 02:30' ) );

ck( 'one date is one start, as a lone session',
    array( WPCPM_Group_Sessions::plan_dates( array( '2026-10-06' ), '18:00', $riga, $now )['refused'], count( WPCPM_Group_Sessions::plan_dates( array( '2026-10-06' ), '18:00', $riga, $now )['starts'] ) ),
    array( '', 1 ) );

ck( 'a date that is not a date, a date that has passed and a date given twice each refuse the whole list, naming the date',
    array(
        WPCPM_Group_Sessions::plan_dates( array( '2026-10-06', 'not-a-date' ), '18:00', $riga, $now ),
        WPCPM_Group_Sessions::plan_dates( array( '2026-10-06', '2026-09-29' ), '18:00', $riga, $now ),
        WPCPM_Group_Sessions::plan_dates( array( '2026-10-06', '2026-10-13', '2026-10-06' ), '18:00', $riga, $now ),
    ),
    array(
        array( 'starts' => array(), 'refused' => 'when', 'date' => 'not-a-date' ),
        array( 'starts' => array(), 'refused' => 'past', 'date' => '2026-09-29' ),
        array( 'starts' => array(), 'refused' => 'twice', 'date' => '2026-10-06' ),
    ) );

$seventeen = array();
for ( $i = 1; $i <= 17; ++$i ) {
	$seventeen[] = sprintf( '2026-11-%02d', $i );
}

ck( 'seventeen dates are refused before any is read, and sixteen are planned: a series holds sixteen (the design\'s decision 8)',
    array(
        WPCPM_Group_Sessions::plan_dates( $seventeen, '18:00', $riga, $now ),
        count( WPCPM_Group_Sessions::plan_dates( array_slice( $seventeen, 0, 16 ), '18:00', $riga, $now )['starts'] ),
        false !== strpos( WPCPM_Mentor_Calls::message( 'series-many', array( WPCPM_Group_Sessions::MAX_SERIES ) )[1], '16 sessions at most' ),
        false !== strpos( WPCPM_Mentor_Calls::message( 'series-count', array( WPCPM_Group_Sessions::MAX_SERIES ) )[1], 'from 2 to 16.' ),
    ),
    array( array( 'starts' => array(), 'refused' => 'many', 'date' => '' ), 16, true, true ) );

echo "\n=== A repeat rule fills the dates after the first (1.109.0) ===\n";

ck( 'every week, every two weeks and every four weeks step by days from the first date, which is not repeated',
    array(
        WPCPM_Group_Sessions::repeat_dates( '2026-10-06', 'week', 4, $riga ),
        WPCPM_Group_Sessions::repeat_dates( '2026-10-06', '2weeks', 3, $riga ),
        WPCPM_Group_Sessions::repeat_dates( '2026-10-06', '4weeks', 2, $riga ),
    ),
    array(
        array( '2026-10-13', '2026-10-20', '2026-10-27' ),
        array( '2026-10-20', '2026-11-03' ),
        array( '2026-11-03' ),
    ) );

ck( 'every month keeps the weekday and its place in the month across a year end: the second Tuesday stays the second Tuesday',
    WPCPM_Group_Sessions::repeat_dates( '2026-10-13', 'month', 5, $riga ),
    array( '2026-11-10', '2026-12-08', '2027-01-12', '2027-02-09' ) );

ck( 'a first date in a fifth week takes the last such weekday of a month that has no fifth',
    WPCPM_Group_Sessions::repeat_dates( '2026-10-30', 'month', 4, $riga ),
    array( '2026-11-27', '2026-12-25', '2027-01-29' ) );

$sixteen_weeks = WPCPM_Group_Sessions::repeat_dates( '2026-10-06', 'week', 16, $riga );

ck( 'sixteen in all is fifteen more, two is one more; an unknown rule, a count of one and a first date that is not a date make nothing',
    array(
        count( $sixteen_weeks ),
        end( $sixteen_weeks ),
        WPCPM_Group_Sessions::repeat_dates( '2026-10-06', 'week', 2, $riga ),
        WPCPM_Group_Sessions::repeat_dates( '2026-10-06', 'daily', 3, $riga ),
        WPCPM_Group_Sessions::repeat_dates( '2026-10-06', 'week', 1, $riga ),
        WPCPM_Group_Sessions::repeat_dates( 'not-a-date', 'week', 3, $riga ),
    ),
    array( 15, '2027-01-19', array( '2026-10-13' ), array(), array(), array() ) );

$weekly = WPCPM_Group_Sessions::plan_dates( array_merge( array( '2026-10-06' ), $sixteen_weeks ), '18:00', $riga, $now );

ck( 'the dates a rule makes go through plan_dates() like any list: sixteen starts, none refused',
    array( $weekly['refused'], count( $weekly['starts'] ) ),
    array( '', 16 ) );

// A session clashes with whatever it overlaps, not only with what starts where it starts (the deep
// check of 1.109.1, SESSIONS-4): a call from half past to the hour inside the second session is the
// clash, and one that starts as the first session ends is none.
ck( 'the first start whose session would overlap something the mentor holds is the clash; a span that only touches it, and none at all, are no clash',
    array(
        WPCPM_Group_Sessions::first_clash( $three['starts'], HOUR_IN_SECONDS, array( array( 'start' => $three['starts'][1] + 1800, 'end' => $three['starts'][1] + 3600 ), array( 'start' => 12345, 'end' => 12346 ) ) ),
        WPCPM_Group_Sessions::first_clash( $three['starts'], HOUR_IN_SECONDS, array( array( 'start' => $three['starts'][0] + HOUR_IN_SECONDS, 'end' => $three['starts'][0] + 2 * HOUR_IN_SECONDS ) ) ),
        WPCPM_Group_Sessions::first_clash( $three['starts'], HOUR_IN_SECONDS, array() ),
    ),
    array( $three['starts'][1], 0, 0 ) );

$before  = count( $GLOBALS['posts'] );
$planned = WPCPM_Group_Sessions::create_sessions( 20, $three['starts'], 60, 5, 'Release cycle', $riga );

ck( 'three sessions are created in date order, each as a session is created alone',
    array(
        count( $planned ),
        count( $GLOBALS['posts'] ) - $before,
        array_map( function ( $id ) { return (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_START, true ); }, $planned ),
        array_map( function ( $id ) { return (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_END, true ) - (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_START, true ); }, $planned ),
        array_unique( array_map( function ( $id ) { return array( (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_MENTOR, true ), WPCPM_Mentor_Calls::capacity( $id ), get_post_meta( $id, WPCPM_Mentor_Calls::META_ZONE, true ), get_post( $id )->post_content, get_post( $id )->post_status ); }, $planned ), SORT_REGULAR ),
    ),
    array( 3, 3, $three['starts'], array( 3600, 3600, 3600 ), array( array( 20, 5, 'Europe/Riga', 'Release cycle', 'private' ) ) ) );

ck( 'and every one of them carries the first as its series',
    array_map( function ( $id ) { return WPCPM_Group_Sessions::series_of( $id ); }, $planned ),
    array( $planned[0], $planned[0], $planned[0] ) );

$lone = WPCPM_Group_Sessions::create_sessions( 20, array( $three['starts'][0] + 100 ), 60, 5, '', $riga );

ck( 'a session created alone carries no series',
    array( count( $lone ), WPCPM_Group_Sessions::series_of( $lone[0] ) ), array( 1, 0 ) );

echo "\n=== The diary read as spans (SESSIONS-4) ===\n";

// What the slot generator, planning and a change are tested against: the mentor's calls that run
// into a window, each with its start and its end, read as WordPress would query them.
$window = time() + 30 * DAY_IN_SECONDS;
$made   = array();

foreach ( array(
	'running into it' => array( 20, $window - 2 * HOUR_IN_SECONDS, $window + 1800, 'private' ),
	'ended before it' => array( 20, $window - 3 * HOUR_IN_SECONDS, $window - HOUR_IN_SECONDS, 'private' ),
	'inside it'       => array( 20, $window + HOUR_IN_SECONDS, $window + 2 * HOUR_IN_SECONDS, 'private' ),
	'after it'        => array( 20, $window + 5 * HOUR_IN_SECONDS, $window + 6 * HOUR_IN_SECONDS, 'private' ),
	'another mentor'  => array( 21, $window + HOUR_IN_SECONDS, $window + 2 * HOUR_IN_SECONDS, 'private' ),
	'canceled'        => array( 20, $window + HOUR_IN_SECONDS, $window + 2 * HOUR_IN_SECONDS, 'trash' ),
	'being moved'     => array( 20, $window + 3 * HOUR_IN_SECONDS, $window + 4 * HOUR_IN_SECONDS, 'private' ),
) as $which => $shape ) {
	$made[ $which ] = make_call();
	update_post_meta( $made[ $which ], WPCPM_Mentor_Calls::META_MENTOR, $shape[0] );
	update_post_meta( $made[ $which ], WPCPM_Mentor_Calls::META_START, $shape[1] );
	update_post_meta( $made[ $which ], WPCPM_Mentor_Calls::META_END, $shape[2] );
	$GLOBALS['posts'][ $made[ $which ] ]->post_status = $shape[3];
}

ck( 'the diary read answers the mentor\'s live calls that run into the window, one that started before it included, the one being moved left out',
    WPCPM_Mentor_Calls::taken_spans( 20, $window, $window + 4 * HOUR_IN_SECONDS, $made['being moved'] ),
    array(
        array( 'start' => $window - 2 * HOUR_IN_SECONDS, 'end' => $window + 1800 ),
        array( 'start' => $window + HOUR_IN_SECONDS, 'end' => $window + 2 * HOUR_IN_SECONDS ),
    ) );

ck( 'two stretches overlap when each starts before the other ends, and one that starts as the other ends does not',
    array(
        WPCPM_Mentor_Calls::overlaps( $window, $window + 1800, array( array( 'start' => $window - 600, 'end' => $window + 600 ) ) ),
        WPCPM_Mentor_Calls::overlaps( $window, $window + 1800, array( array( 'start' => $window + 1800, 'end' => $window + 3600 ) ) ),
        WPCPM_Mentor_Calls::overlaps( $window, $window + 1800, array( array( 'start' => $window - 1800, 'end' => $window ) ) ),
    ),
    array( true, false, false ) );

foreach ( $made as $call_id ) {
	wp_delete_post( $call_id, true );
}

echo "\n=== A series' heading (1.108.0) ===\n";

$utc = new DateTimeZone( 'UTC' );

ck( 'the heading counts the sessions and spans the first and last date on the viewer\'s clock',
    array(
        WPCPM_Group_Sessions::series_heading( array_map( 'get_post', $planned ), $utc ),
        WPCPM_Group_Sessions::series_heading( array( get_post( $planned[2] ) ), $utc ),
    ),
    array( '3 sessions, October 6, 2026 to October 20, 2026', '1 session, on October 20, 2026' ) );

echo "\n=== Join all: what a student may still take (1.108.0) ===\n";

$open = make_call( 2 );
$on   = make_call( 2 );
$fill = make_call( 2 );
WPCPM_Mentor_Calls::add_attendee( $on, 31, 'recSTUDENT0000001' );
WPCPM_Mentor_Calls::add_attendee( $fill, 32, 'recSTUDENT0000002' );
WPCPM_Mentor_Calls::add_attendee( $fill, 33, 'recSTUDENT0000003' );

$plan = WPCPM_Group_Sessions::joinable( array_map( 'get_post', array( $open, $on, $fill ) ), 31 );

ck( 'a session the student is on and a full one are passed over, counted apart; the open one is taken',
    array( array_map( function ( $p ) { return $p->ID; }, $plan['take'] ), $plan['on'], $plan['full'] ),
    array( array( $open ), 1, 1 ) );

ck( 'with nothing left to take, the plan says so',
    WPCPM_Group_Sessions::joinable( array_map( 'get_post', array( $on, $fill ) ), 31 ),
    array( 'take' => array(), 'full' => 1, 'on' => 1 ) );

// The upcoming query keeps a session for an hour after it starts, so one under way is still in
// the series a student presses Join all on. The single Join refuses it; so must this.
$started = make_call( 2 );
update_post_meta( $started, WPCPM_Mentor_Calls::META_START, time() - 600 );

$under_way = WPCPM_Group_Sessions::joinable( array_map( 'get_post', array( $open, $started ) ), 31 );

ck( 'a session that has already started is a place nobody can take, counted with the full ones',
    array( array_map( function ( $p ) { return $p->ID; }, $under_way['take'] ), $under_way['full'], $under_way['on'] ),
    array( array( $open ), 1, 0 ) );

// Join all reads its list before the lock and this under it, so a session canceled in between is
// still in the list it is handed (the deep check of 1.109.1, SESSIONS-5).
$canceled = make_call( 2 );
$GLOBALS['posts'][ $canceled ]->post_status = 'trash';

ck( 'a session canceled since the list was read is neither taken nor counted as full',
    WPCPM_Group_Sessions::joinable( array_map( 'get_post', array( $open, $canceled ) ), 31 ),
    array( 'take' => array( get_post( $open ) ), 'full' => 0, 'on' => 0 ) );

echo "\n=== A student moved to another mentor (SESSIONS-7) ===\n";

// The deep check of 1.109.1, SESSIONS-7: a student re-paired with another mentor stayed on the old
// mentor's sessions with no way off them, since their list held their new mentor's sessions alone,
// and the old mentor's note on any of those sessions was refused for everybody on it. Mia (20) and
// Noa (21) are mentors; Lee (34) was Mia's and is now Noa's, on both sides of the pairing.
$GLOBALS['umeta'][20][ WPCPM_Mentors_Sync::META_RECORD_ID ] = 'recMENTORMIAONEXX';
$GLOBALS['umeta'][21][ WPCPM_Mentors_Sync::META_RECORD_ID ] = 'recMENTORNOAONEXX';
$GLOBALS['umeta'][20][ WPCPM_Mentors_Sync::META_MENTEES ]   = array( array( 'record_id' => 'recSTUDENT0000001', 'name' => 'User 31' ) );
$GLOBALS['umeta'][21][ WPCPM_Mentors_Sync::META_MENTEES ]   = array( array( 'record_id' => 'recSTUDENT3400000', 'name' => 'User 34' ) );
$GLOBALS['umeta'][34][ WPCPM_Students_Sync::META_RECORD_ID ] = 'recSTUDENT3400000';
$GLOBALS['umeta'][34][ WPCPM_Students_Sync::META_MENTOR ]    = array( 'record_id' => 'recMENTORNOAONEXX', 'name' => 'User 21' );
$GLOBALS['roles'][20]                                        = array( WPCPM_Roles::ROLE_MENTOR );

$on_old    = make_call( 4 ); // Mia's, with Lee on it.
$not_on    = make_call( 4 ); // Mia's, without Lee.
$new_one   = make_call( 4 ); // Noa's.
$old_past  = make_call( 4 ); // Mia's, with Lee on it, two days ago.
update_post_meta( $new_one, WPCPM_Mentor_Calls::META_MENTOR, 21 );
update_post_meta( $new_one, WPCPM_Mentor_Calls::META_START, time() + 2 * DAY_IN_SECONDS );
update_post_meta( $old_past, WPCPM_Mentor_Calls::META_START, time() - 2 * DAY_IN_SECONDS );
WPCPM_Mentor_Calls::add_attendee( $on_old, 31, 'recSTUDENT0000001' );
WPCPM_Mentor_Calls::add_attendee( $on_old, 34, 'recSTUDENT3400000' );
WPCPM_Mentor_Calls::add_attendee( $old_past, 34, 'recSTUDENT3400000' );

ck( 'the re-paired student\'s list holds their new mentor\'s sessions and the old mentor\'s upcoming one they are on, and no other of the old mentor\'s',
    array_map( function ( $p ) { return $p->ID; }, WPCPM_Group_Sessions::for_student( 34 ) ),
    array( $on_old, $new_one ) );

// Mia writes the note after the session: Lee was on it, though he is no longer hers.
$GLOBALS['uid']  = 20;
$GLOBALS['caps'] = false;
$old_note        = WPCPM_Mentor_Notes::add_for_records( $on_old, 'Walked through the release.', WPCPM_Mentor_Calls::attendee_records( $on_old ) );

ck( 'the old mentor\'s note on her own session lands on everybody who was on it, the re-paired student included',
    array( is_wp_error( $old_note ) ? $old_note->get_error_code() : 'saved', is_wp_error( $old_note ) ? array() : get_post_meta( (int) $old_note, WPCPM_Mentor_Notes::META_STUDENT, false ) ),
    array( 'saved', array( 'recSTUDENT0000001', 'recSTUDENT3400000' ) ) );

// Attendance is what grants it, and only on the writer's own session.
$GLOBALS['uid']   = 21;
$GLOBALS['roles'][21] = array( WPCPM_Roles::ROLE_MENTOR );
$not_hers         = WPCPM_Mentor_Notes::add_for_records( $on_old, 'Not my session.', WPCPM_Mentor_Calls::attendee_records( $on_old ) );
$GLOBALS['uid']   = 20;
$stranger         = WPCPM_Mentor_Notes::add_for_records( $on_old, 'Somebody else.', array( 'recSTUDENT0000009' ) );

ck( 'another mentor may not note a session that is not theirs, nor the session\'s mentor a student who was not on it',
    array( is_wp_error( $not_hers ) ? $not_hers->get_error_code() : 'saved', is_wp_error( $stranger ) ? $stranger->get_error_code() : 'saved' ),
    array( 'wpcpm_note_denied', 'wpcpm_note_denied' ) );

$GLOBALS['uid'] = 0;

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
