<?php
/**
 * Mentor engagement (1.122.26): every row and figure the Engagement tab, the Administrator
 * Dashboard's card and `wp wpcredits mentor_engagement` print, from plain arrays.
 *
 * The rules are the ones `analyze.py` used for the owner's question of 15 October 2026, so the
 * first numbers the site prints match that answer: who counts as an active mentor (Airtable says
 * Active, not an Administrator), when a held call counts as noted (a note by its mentor about one
 * of its students, dated no earlier than a day before the call), what a program action is (a note,
 * a saved schedule, a group session created, a call cancelled by the mentor; never a student's
 * booking), and that the call figures count every call on the site.
 *
 * Run from the plugin root:  php bin/test-mentor-engagement.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );

// Every warning, notice and deprecation of the run is kept; the last check names any.
$warnings = array();
set_error_handler( static function ( $number, $message, $file = '', $line = 0 ) use ( &$warnings ) { $warnings[] = $message . ' (line ' . $line . ')'; return true; } );

define( 'ARRAY_A', 'ARRAY_A' );
require __DIR__ . '/stubs/sqlite-wpdb.php';
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();
$GLOBALS['umeta'] = array();
$GLOBALS['users'] = array();

class WP_User {
	public $ID; public $display_name; public $user_login; public $user_email; public $roles;
	public function __construct( $id, $name, array $roles ) {
		$this->ID = $id; $this->display_name = $name; $this->user_login = 'u' . $id; $this->user_email = 'u' . $id . '@example.test'; $this->roles = $roles;
	}
}
// As this program's site answers: `fields => ID` hands back row objects, not integers (see
// WPCPM_Roles::id_of()). A user is listed once per call, by the role asked for or by a clause,
// read as WordPress reads it. A `meta_query` clause with `!=` matches an account whose stored value
// is not the clause's. The plain `meta_key` form is read the way WordPress reads it too: an empty
// `meta_value` is dropped (WP_Meta_Query::parse_query_vars), so the key alone decides and an
// account holding the key with an empty value is listed.
function get_users( $args ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $u ) {
		$hit = isset( $args['role'] ) && in_array( $args['role'], $u->roles, true );
		foreach ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() as $clause ) {
			$stored = get_user_meta( $u->ID, $clause['key'], true );
			if ( '!=' === $clause['compare'] && '' !== (string) $stored && (string) $stored !== (string) $clause['value'] ) {
				$hit = true;
			}
		}
		if ( isset( $args['meta_key'] ) && '' === (string) ( isset( $args['meta_value'] ) ? $args['meta_value'] : '' ) && isset( $GLOBALS['umeta'][ $u->ID ] ) && array_key_exists( $args['meta_key'], $GLOBALS['umeta'][ $u->ID ] ) ) {
			$hit = true;
		}
		if ( $hit ) {
			$out[] = (object) array( 'ID' => $u->ID );
		}
	}
	return $out;
}
function get_userdata( $id ) { return isset( $GLOBALS['users'][ $id ] ) ? $GLOBALS['users'][ $id ] : false; }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : ''; }
function cache_users( $ids ) { $GLOBALS['cached'] = $ids; }

// The schedule's reader, stood in: its own rules are pinned by the mentors dashboard's suite, and
// this reader's contract is only that it asks them.
class WPCPM_Mentor_Availability {
	const META = 'wpcpm_mentor_availability';
	public static function get( $id ) {
		$s = get_user_meta( $id, self::META, true );
		return array( 'weekly' => is_array( $s ) && isset( $s['weekly'] ) ? $s['weekly'] : array(), 'meeting_url' => is_array( $s ) && isset( $s['meeting_url'] ) ? (string) $s['meeting_url'] : '' );
	}
	public static function is_published( $id ) {
		foreach ( self::get( $id )['weekly'] as $w ) {
			if ( ! empty( $w ) ) {
				return true;
			}
		}
		return false;
	}
}

function __( $s, $d = null ) { return $s; }
function _n( $one, $many, $n, $d = null ) { return 1 === (int) $n ? $one : $many; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function gmdate_utc( $t ) { return gmdate( 'Y-m-d H:i', $t ); }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-sign-ins.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-notes.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';

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

$NOW = gmmktime( 12, 0, 0, 10, 15, 2026 );
$DAY = DAY_IN_SECONDS;

function account( $id, array $more = array() ) {
	return array_merge(
		array(
			'id'             => $id,
			'name'           => 'Mentor ' . $id,
			'login'          => 'mentor' . $id,
			'email'          => 'mentor' . $id . '@example.test',
			'roles'          => array( 'wpcpm_mentor' ),
			'active'         => true,
			'students_now'   => 0,
			'students_past'  => 0,
			'last_sign_in'   => 0,
			'last_visit'     => 0,
			'schedule_saved' => false,
			'weekly_hours'   => false,
			'meeting_link'   => false,
		),
		$more
	);
}
function note( $id, $author, $created, array $records = array( 'recSTUDENTA' ), $session = 0 ) {
	return array( 'id' => $id, 'author' => $author, 'created' => $created, 'records' => $records, 'session' => $session );
}
function call( $id, $mentor, $start, array $more = array() ) {
	return array_merge(
		array( 'id' => $id, 'author' => 900, 'mentor' => $mentor, 'start' => $start, 'cancelled_by' => 0, 'status' => 'private', 'group' => false, 'records' => array( 'recSTUDENTA' ) ),
		$more
	);
}
function row_of( array $out, $id ) {
	foreach ( $out['rows'] as $row ) {
		if ( $id === $row['id'] ) {
			return $row;
		}
	}
	return null;
}

$accounts = array(
	// 1: active, students, signed in 3 days ago, hours and a link, notes, calls.
	account( 1, array( 'students_now' => 4, 'students_past' => 2, 'last_sign_in' => $NOW - 3 * $DAY, 'last_visit' => $NOW - 3 * $DAY, 'schedule_saved' => true, 'weekly_hours' => true, 'meeting_link' => true ) ),
	// 2: active, students, signed in 40 days ago by sign-in, visited 10 days ago, saved schedule with no hours.
	account( 2, array( 'students_now' => 1, 'last_sign_in' => $NOW - 40 * $DAY, 'last_visit' => $NOW - 10 * $DAY, 'schedule_saved' => true ) ),
	// 3: active, never signed in, no students.
	account( 3 ),
	// 4: active, signed in 50 days ago, no action.
	account( 4, array( 'last_sign_in' => $NOW - 50 * $DAY ) ),
	// 5: an Administrator who mentors.
	account( 5, array( 'roles' => array( 'administrator', 'wpcpm_mentor' ), 'students_now' => 2, 'last_sign_in' => $NOW - $DAY ) ),
	// 6: not active in Airtable.
	account( 6, array( 'active' => false, 'last_sign_in' => $NOW - $DAY ) ),
	// 7: an Administrator who mentors, whom Airtable does not hold as Active: in no figure, in no follow-up view.
	account( 7, array( 'roles' => array( 'administrator', 'wpcpm_mentor' ), 'active' => false ) ),
);

$notes = array(
	note( 101, 1, $NOW - 20 * $DAY ),                          // Before both calls of mentor 1.
	note( 102, 1, $NOW - 9 * $DAY - 3600 ),                    // 23 hours before call 202 starts: noted.
	note( 103, 1, $NOW - 40 * $DAY, array( 'recSTUDENTB' ) ),  // Old, another student.
	note( 104, 900, $NOW - $DAY, array( 'recSTUDENTA' ), 0 ),  // An Administrator's note: not mentor 1's.
	note( 105, 777, $NOW - $DAY ),                             // A deleted author: no row, no warning.
	note( 106, 2, $NOW - 2 * $DAY, array( 'recSTUDENTZ' ), 303 ), // Mentor 2's note on group session 303.
	note( 107, 1, $NOW - 8 * $DAY, array( 'recSTUDENTD' ) ),   // Two days before call 209: too early to count.
);

$calls = array(
	call( 201, 1, $NOW - 25 * $DAY, array( 'records' => array( 'recSTUDENTC' ) ) ), // Held; no note on that student: not noted.
	call( 202, 1, $NOW - 8 * $DAY - 2 * 3600 ),                         // Held; note 102 a day before: noted.
	call( 203, 1, $NOW + 2 * $DAY ),                                    // Upcoming.
	call( 204, 1, $NOW - 5 * $DAY, array( 'status' => 'trash', 'cancelled_by' => 1 ) ),  // Cancelled by the mentor.
	call( 205, 2, $NOW - 4 * $DAY, array( 'status' => 'trash', 'cancelled_by' => 55 ) ), // Cancelled by the student.
	call( 206, 1, 0 ),                                                  // No start: booked, neither held nor upcoming.
	call( 207, 0, $NOW - 3 * $DAY ),                                    // No mentor: no row, still a held call.
	call( 208, 888, $NOW - 3 * $DAY ),                                  // A deleted mentor: no row, still a held call.
	call( 209, 1, $NOW - 6 * $DAY, array( 'records' => array( 'recSTUDENTD' ) ) ), // Held; note 107 is two days before: not noted.
	call( 301, 2, $NOW - 6 * $DAY, array( 'group' => true, 'author' => 2 ) ),           // Created and held.
	call( 302, 2, $NOW + 6 * $DAY, array( 'group' => true, 'author' => 900 ) ),         // Upcoming; a manager made it.
	call( 303, 2, $NOW - 2 * $DAY - 3600, array( 'group' => true, 'author' => 2 ) ),    // Held, noted by note 106.
	call( 304, 2, $NOW + 9 * $DAY, array( 'group' => true, 'author' => 2, 'status' => 'trash', 'cancelled_by' => 2 ) ), // Created, cancelled by the mentor.
);

$out = WPCPM_Mentor_Engagement::compute( $accounts, $notes, $calls, $NOW );
$r1  = row_of( $out, 1 );
$r2  = row_of( $out, 2 );

echo "=== Rows ===\n";

ck( 'one row per account, in the accounts\' order, and none for a deleted author or mentor', array_map( function ( $r ) { return $r['id']; }, $out['rows'] ), array( 1, 2, 3, 4, 5, 6, 7 ) );
ck( 'staff is an account that also holds administrator', array( $r1['staff'], row_of( $out, 5 )['staff'] ), array( false, true ) );
ck( 'last seen is the later of the sign-in and the visit', array( $r1['last_seen'], $r2['last_seen'], row_of( $out, 3 )['last_seen'] ), array( $NOW - 3 * $DAY, $NOW - 10 * $DAY, 0 ) );
ck( 'seen in 7 and 30 days read last seen', array( $r1['seen_7'], $r1['seen_30'], $r2['seen_7'], $r2['seen_30'], row_of( $out, 4 )['seen_30'] ), array( true, true, false, true, false ) );
ck( 'notes are the mentor\'s own: count, in 30 days, the last one', array( $r1['notes'], $r1['notes_30'], $r1['last_note'] ), array( 4, 3, $NOW - 8 * $DAY ) );
ck( 'one-to-one calls: booked (not trashed), held, upcoming; a call with no start is booked only', array( $r1['calls_booked'], $r1['calls_held'], $r1['calls_upcoming'] ), array( 5, 3, 1 ) );
ck( 'a held call is noted by a note about its student dated from a day before it on: 202 is, 201 (no note) and 209 (two days early) are not', $r1['calls_noted'], 1 );
ck( 'a cancellation counts as the mentor\'s only when the mentor cancelled', array( $r1['cancelled_by_mentor'], $r2['cancelled_by_mentor'] ), array( 1, 1 ) );
ck( 'group sessions: created by the mentor (a cancelled one too), held, upcoming, noted', array( $r2['sessions_created'], $r2['sessions_held'], $r2['sessions_upcoming'], $r2['sessions_noted'] ), array( 3, 2, 1, 1 ) );
ck( 'a session an Administrator created on the mentor\'s calendar counts as upcoming, never as created', array( $r2['sessions_created'], $r2['sessions_upcoming'] ), array( 3, 1 ) );
ck( 'acted: a note, a saved schedule, a session created or a cancellation; never a student\'s booking', array( $r1['acted'], $r2['acted'], row_of( $out, 3 )['acted'], row_of( $out, 4 )['acted'] ), array( true, true, false, false ) );

echo "\n=== The summary ===\n";

$s = $out['summary'];
ck( 'active is Airtable Active and not an Administrator', $s['active'], 4 );
ck( 'signed in, never, seen in 30 and 7 days', array( $s['signed_in'], $s['never'], $s['seen_30'], $s['seen_7'] ), array( 3, 1, 2, 1 ) );
ck( 'acted, and signed in without acting', array( $s['acted'], $s['idle'] ), array( 2, 1 ) );
ck( 'with students now: hours, hours and a link, notes, seen in 30 days', array( $s['with_students'], $s['with_students_hours'], $s['with_students_link'], $s['with_students_notes'], $s['with_students_seen_30'] ), array( 2, 1, 1, 2, 2 ) );
ck( 'calls count every call on the site, a deleted mentor\'s and a mentorless one included', array( $s['calls_held'], $s['calls_upcoming'], $s['calls_noted'] ), array( 5, 1, 1 ) );
ck( 'sessions: held, upcoming, noted', array( $s['sessions_held'], $s['sessions_upcoming'], $s['sessions_noted'] ), array( 2, 1, 1 ) );
ck( 'Administrators who mentor are counted apart, and only active ones (account 7 is not Active)', $s['staff'], 1 );
ck( 'every figure is an int', array_unique( array_map( 'gettype', array_values( $s ) ) ), array( 'integer' ) );

echo "\n=== Malformed input (Review Focus 2 and 3) ===\n";

$odd = WPCPM_Mentor_Engagement::compute(
	array( account( 9, array( 'last_sign_in' => 'x', 'last_visit' => null, 'students_now' => '3' ) ) ),
	array( note( 1, 9, 'not a time', array() ) ),
	array( call( 2, 9, '', array( 'records' => array() ) ) ),
	$NOW
);
ck( 'a value that is not a number reads as 0 and nothing warns', array( row_of( $odd, 9 )['last_seen'], row_of( $odd, 9 )['students_now'], row_of( $odd, 9 )['calls_held'], row_of( $odd, 9 )['calls_booked'] ), array( 0, 3, 0, 1 ) );
ck( 'no accounts, no notes, no calls: zeros', array_sum( WPCPM_Mentor_Engagement::compute( array(), array(), array(), $NOW )['summary'] ), 0 );

echo "\n=== Views ===\n";

$count = function ( $view ) use ( $out ) {
	return count( array_filter( $out['rows'], function ( $row ) use ( $view ) { return WPCPM_Mentor_Engagement::in_view( $row, $view ); } ) );
};
ck( 'the eight views, in the tab\'s order', array_keys( WPCPM_Mentor_Engagement::views() ), array( 'all', 'active', 'students', 'never', 'quiet', 'idle', 'no-hours', 'staff' ) );
ck( 'each follow-up view counts what its figure counts', array( $count( 'all' ), $count( 'active' ), $count( 'students' ), $count( 'never' ), $count( 'quiet' ), $count( 'idle' ), $count( 'no-hours' ), $count( 'staff' ) ), array( 7, $s['active'], $s['with_students'], $s['never'], $s['signed_in'] - $s['seen_30'], $s['active'] - $s['acted'], $s['with_students'] - $s['with_students_hours'], $s['staff'] ) );
ck( 'a view the tab does not have is All', $count( 'nope' ), 7 );

echo "\n=== Order and search ===\n";

$ids = function ( array $rows ) { return array_map( function ( $r ) { return $r['id']; }, $rows ); };
ck( 'the default order is students now, most first, then by name', $ids( WPCPM_Mentor_Engagement::sorted( $out['rows'] ) ), array( 1, 5, 2, 3, 4, 6, 7 ) );
ck( 'by name, A to Z', $ids( WPCPM_Mentor_Engagement::sorted( $out['rows'], 'mentor', 'asc' ) ), array( 1, 2, 3, 4, 5, 6, 7 ) );
ck( 'by last visit, oldest first, the never-visited by name', $ids( WPCPM_Mentor_Engagement::sorted( $out['rows'], 'last_visit', 'asc' ) ), array( 3, 4, 5, 6, 7, 2, 1 ) );
ck( 'an unknown column is the default order', $ids( WPCPM_Mentor_Engagement::sorted( $out['rows'], 'email', 'asc' ) ), array( 1, 5, 2, 3, 4, 6, 7 ) );
ck( 'a search reads the name, the username and the email, in any case', array( WPCPM_Mentor_Engagement::matches( $r1, 'MENTOR 1' ), WPCPM_Mentor_Engagement::matches( $r1, 'mentor1@' ), WPCPM_Mentor_Engagement::matches( $r1, 'zzz' ) ), array( true, true, false ) );

echo "\n=== Words ===\n";

$tiles = WPCPM_Mentor_Engagement::tiles( $s );
ck( 'six tiles in the card\'s order', array_map( function ( $t ) { return $t['name']; }, $tiles ), array( 'Active mentors', 'Seen in the last 30 days', 'Took a program action', 'Never signed in', 'With students now', 'Calls held' ) );
ck( 'each with its number', array_map( function ( $t ) { return $t['n']; }, $tiles ), array( 4, 2, 2, 1, 2, 5 ) );
ck( 'and its line', array_map( function ( $t ) { return $t['line']; }, $tiles ), array( 'not counting Administrators', '1 in the last 7 days', '1 signed in without one', '', '1 with weekly hours, 2 wrote notes', '1 with a note, 1 upcoming; 2 group sessions held' ) );
// Review finding D: the session noun agrees with its number.
ck( 'the calls tile\'s line reads "1 group session held" for one, and keeps the plural for none and many', array_map( function ( $held ) use ( $s ) { return WPCPM_Mentor_Engagement::tiles( array_merge( $s, array( 'sessions_held' => $held ) ) )[5]['line']; }, array( 0, 1, 2, 1234 ) ), array( '1 with a note, 1 upcoming; 0 group sessions held', '1 with a note, 1 upcoming; 1 group session held', '1 with a note, 1 upcoming; 2 group sessions held', '1 with a note, 1 upcoming; 1,234 group sessions held' ) );
ck( 'the sentence for the Administrators who mentor', WPCPM_Mentor_Engagement::apart( $s ), 'Administrators who also mentor are counted apart: 1.' );
ck( 'the four schedule states', array( WPCPM_Mentor_Engagement::schedule_label( $r1 ), WPCPM_Mentor_Engagement::schedule_label( array_merge( $r1, array( 'meeting_link' => false ) ) ), WPCPM_Mentor_Engagement::schedule_label( $r2 ), WPCPM_Mentor_Engagement::schedule_label( row_of( $out, 3 ) ) ), array( 'Weekly hours, meeting link', 'Weekly hours, no link', 'Saved, no hours', 'Not set' ) );
ck( 'an exported row has the export fields, dates in UTC, no email', array( array_keys( WPCPM_Mentor_Engagement::export_row( $r1 ) ), WPCPM_Mentor_Engagement::export_row( $r1 )['last_sign_in'] ), array( WPCPM_Mentor_Engagement::EXPORT_FIELDS, gmdate_utc( $NOW - 3 * $DAY ) ) );

echo "\n=== Reading the site ===\n";

$db = $GLOBALS['wpdb'];
$db->query( 'CREATE TABLE wp_posts ( ID INTEGER PRIMARY KEY, post_type TEXT, post_status TEXT, post_author INTEGER, post_date_gmt TEXT )' );
$db->query( 'CREATE TABLE wp_postmeta ( meta_id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, meta_key TEXT, meta_value TEXT )' );
$post = function ( $id, $type, $status, $author, $date, array $meta ) use ( $db ) {
	$db->insert( 'wp_posts', array( 'ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_author' => $author, 'post_date_gmt' => $date ) );
	foreach ( $meta as $pair ) {
		$db->insert( 'wp_postmeta', array( 'post_id' => $id, 'meta_key' => $pair[0], 'meta_value' => $pair[1] ) );
	}
};
$post( 11, 'wpcpm_mentor_note', 'private', 1, '2026-10-10 08:00:00', array( array( '_wpcpm_student_record', 'recSTUDENTA' ), array( '_wpcpm_note_session', '22' ), array( '_wpcpm_note_text_never_read', 'x' ) ) );
$post( 12, 'wpcpm_mentor_note', 'auto-draft', 1, '2026-10-10 08:00:00', array() );
$post( 21, 'wpcpm_mentor_call', 'private', 900, '2026-10-01 08:00:00', array( array( '_wpcpm_call_mentor', '1' ), array( '_wpcpm_call_start', '1791000000' ), array( '_wpcpm_call_student_record', 'recSTUDENTA' ) ) );
$post( 22, 'wpcpm_mentor_call', 'trash', 1, '2026-10-01 08:00:00', array( array( '_wpcpm_call_mentor', '1' ), array( '_wpcpm_call_start', '1791100000' ), array( '_wpcpm_call_capacity', '10' ), array( '_wpcpm_call_cancelled_by', '1' ), array( '_wpcpm_call_student_record', 'recSTUDENTA' ), array( '_wpcpm_call_student_record', 'recSTUDENTB' ) ) );
$post( 31, 'post', 'publish', 1, '2026-10-01 08:00:00', array( array( '_wpcpm_call_mentor', '1' ) ) );

$GLOBALS['users'] = array( 1 => new WP_User( 1, 'Ada', array( 'wpcpm_mentor' ) ), 2 => new WP_User( 2, 'Bo', array( 'editor' ) ), 3 => new WP_User( 3, 'Cy', array( 'subscriber' ) ), 4 => new WP_User( 4, 'Di', array( 'subscriber' ) ) );
$GLOBALS['umeta'] = array(
	1 => array( 'wpcpm_mentor_record_id' => 'recMENTORA', 'wpcpm_mentor_active' => '1', 'wpcpm_mentee_count' => '4', 'wpcpm_mentee_past_count' => '1', 'wpcpm_last_sign_in' => '1792000000', 'wpcpm_last_visit' => '1792050000', 'wpcpm_mentor_availability' => array( 'weekly' => array( 1 => array( array( '09:00', '10:00' ) ) ), 'meeting_url' => 'https://meet.example.test/a' ) ),
	2 => array( 'wpcpm_mentor_record_id' => 'recMENTORB', 'wpcpm_mentor_active' => '0' ),
	4 => array( 'wpcpm_mentor_record_id' => '' ),
);

$before = count( $db->queries );
$read   = WPCPM_Mentor_Engagement::read();
$asked  = array_slice( $db->queries, $before );
ck( 'the accounts are the Mentor role and any account holding a non-empty mentor record, once each (account 1 is both; account 4 holds an empty record and no role: no row; the IDs arrive as row objects)', array_map( function ( $a ) { return $a['id']; }, $read['accounts'] ), array( 1, 2 ) );
ck( 'their users and meta are read in one go', $GLOBALS['cached'], array( 1, 2 ) );
ck( 'an account\'s facts', $read['accounts'][0], array( 'id' => 1, 'name' => 'Ada', 'login' => 'u1', 'email' => 'u1@example.test', 'roles' => array( 'wpcpm_mentor' ), 'active' => true, 'students_now' => 4, 'students_past' => 1, 'last_sign_in' => 1792000000, 'last_visit' => 1792050000, 'schedule_saved' => true, 'weekly_hours' => true, 'meeting_link' => true ) );
ck( 'an account with nothing stored reads as not active, nothing saved', array( $read['accounts'][1]['active'], $read['accounts'][1]['schedule_saved'], $read['accounts'][1]['last_sign_in'] ), array( false, false, 0 ) );
ck( 'the notes: no auto-draft, the student and the session, the time in UTC, nothing else', $read['notes'], array( array( 'id' => 11, 'author' => 1, 'created' => gmmktime( 8, 0, 0, 10, 10, 2026 ), 'records' => array( 'recSTUDENTA' ), 'session' => 22 ) ) );
ck( 'the calls: a one-to-one call and a cancelled group session with both its students; no other post type', $read['calls'], array(
	array( 'id' => 21, 'author' => 900, 'mentor' => 1, 'start' => 1791000000, 'cancelled_by' => 0, 'status' => 'private', 'group' => false, 'records' => array( 'recSTUDENTA' ) ),
	array( 'id' => 22, 'author' => 1, 'mentor' => 1, 'start' => 1791100000, 'cancelled_by' => 1, 'status' => 'trash', 'group' => true, 'records' => array( 'recSTUDENTA', 'recSTUDENTB' ) ),
) );
ck( 'two statements through the site\'s database object, neither a GROUP_CONCAT nor a read of a post\'s content', array( count( $asked ), count( array_filter( $asked, function ( $q ) { return false !== stripos( $q, 'GROUP_CONCAT' ) || false !== stripos( $q, 'post_content' ); } ) ) ), array( 2, 0 ) );

WPCPM_Mentor_Engagement::for_tests( array( 'accounts' => array( account( 1 ) ), 'notes' => array(), 'calls' => array() ) );
ck( 'report() runs compute() on what read() returns', WPCPM_Mentor_Engagement::report( $NOW )['summary']['active'], 1 );
WPCPM_Mentor_Engagement::for_tests( null );

echo "\n=== The run ===\n";

ck( 'no PHP warning, notice or deprecation anywhere in the run', $warnings, array() );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
