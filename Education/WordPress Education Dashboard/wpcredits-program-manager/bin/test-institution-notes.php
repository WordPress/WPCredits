<?php
/**
 * The two audiences of one note post type: the mentor's history, and the school's.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **An absent `_wpcpm_note_audience` means the mentor's, and only absent does.** Every
 *   note on disk before this release was a mentor's note about their student, and it keeps
 *   exactly that meaning; a value nobody wrote fails closed instead, drawn on neither card.
 * - `get_notes()` and `count_notes()` take the audience with no default, so no caller can
 *   ask for "all of them" by leaving an argument out.
 * - **Both directions, on both cards, including for a program manager.** A mentor never sees
 *   the school's notes and the school never sees the mentor's, whoever is reading: the
 *   manager passes every fence in this plugin and still gets one audience per card, because
 *   the audience is named by the renderer and not by the reader.
 * - The institution's notes ship with their write handler: the card that draws them is the
 *   card that saves them, and `init()` registers both.
 * - The nonce is keyed to the subject account, and the subject's institution comes from the
 *   account's own stamp: a member of B posting a student of A's account ID gets the policy's
 *   one refusal, byte for byte.
 * - The stamp on a new note is the decision's institution, never the form's, so a student
 *   who transfers leaves the old school's notes behind - and the card's promise is worded to
 *   the roster, because after a transfer those notes are read by neither school and not by a
 *   program manager either. The promise says what happens rather than what would be nicer.
 * - **Every outcome a handler can set is one the card can draw.** `unlinked` is refused on
 *   exactly the condition the card draws no notebook on, so that branch prints it or nobody
 *   ever does, and the flash is taken out of user meta whether or not it is printed.
 * - The agreement gate closes the notebook with the rest of the dashboard, because it lives
 *   in the policy's member ground and not in this handler.
 * - One audit row per change, carrying the ground and the evidence, and **carrying none of
 *   the note**: the log is a program manager's view, and the note is the school's.
 *
 * Run from the plugin root:  php bin/test-institution-notes.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']        = array( 'date_format' => 'Y-m-d', 'time_format' => 'H:i' );
$GLOBALS['umeta']       = array();
$GLOBALS['users']       = array();
$GLOBALS['posts']       = array();
$GLOBALS['pmeta']       = array();
$GLOBALS['uid']         = 0;
$GLOBALS['manage']      = array();
$GLOBALS['memberships'] = array();
$GLOBALS['mentees']     = array();
$GLOBALS['settled']     = array();
$GLOBALS['referer']     = array();
$GLOBALS['hooks']       = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = strtolower( str_replace( ' ', '', $name ) ); $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_content = '', $post_type = '', $post_status = 'publish', $post_author = 0, $post_date_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_kses_post( $s ) { return (string) $s; }
function wpautop( $s ) { return '<p>' . (string) $s . '</p>'; }
function sanitize_text_field( $s ) { return trim( str_replace( array( "\r", "\n" ), '', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_html_class( $s ) { return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $s ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action( $h, $c = null, $p = 10, $n = 1 ) { $GLOBALS['hooks'][] = $h; }
function add_filter() {}
function register_post_type() {}
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function human_time_diff( $a, $b = 0 ) { return '4 hours'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function date_i18n( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function user_can( $u, $c ) { $id = is_object( $u ) ? $u->ID : (int) $u; return in_array( $id, $GLOBALS['manage'], true ); }
function current_user_can( $c ) { return user_can( $GLOBALS['uid'], $c ); }
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
		if ( 'email' === $field && 0 === strcasecmp( (string) $user->user_email, (string) $value ) ) { return $user; }
	}
	return false;
}
function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
function wp_insert_post( $a, $error = false ) {
	static $next = 500;
	$post                          = new WP_Post();
	$post->ID                      = ++$next;
	$post->post_title              = $a['post_title'] ?? '';
	$post->post_content            = $a['post_content'] ?? '';
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'publish';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	$post->post_date_gmt           = gmdate( 'Y-m-d H:i:s', 1700000000 + $post->ID );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_time( $f, $gmt = false, $post = null ) { return 'U' === $f ? strtotime( $post->post_date_gmt . ' UTC' ) : gmdate( $f, strtotime( $post->post_date_gmt . ' UTC' ) ); }
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	return $single ? ( $rows ? $rows[0] : '' ) : $rows;
}
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }
function add_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ][] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ); return true; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
/**
 * `get_posts()` with one meta clause, matching *any* stored row for that key.
 *
 * A group session note carries a `META_STUDENT` row per attendee, so a stub that read only
 * the first value would hide the very rows the audience filter has to be tested against.
 * Newest first, as the real query asks for.
 */
function get_posts( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ( $a['post_type'] ?? '' ) !== $post->post_type ) { continue; }
		if ( isset( $a['post_status'] ) && 'any' !== $a['post_status'] && $a['post_status'] !== $post->post_status ) { continue; }
		if ( ! empty( $a['meta_query'] ) ) {
			$clause = $a['meta_query'][0];
			$rows   = $GLOBALS['pmeta'][ $post->ID ][ $clause['key'] ] ?? array();
			$hit    = false;
			foreach ( $rows as $value ) {
				if ( 0 === strcasecmp( (string) $value, (string) $clause['value'] ) ) { $hit = true; }
			}
			if ( ! $hit ) { continue; }
		}
		$out[] = $post;
	}
	$out = array_reverse( $out );
	$n   = (int) ( $a['numberposts'] ?? -1 );
	if ( $n > 0 ) { $out = array_slice( $out, 0, $n ); }
	return $out;
}
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['referer'][] = $a; return true; }
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function add_query_arg( $args, $url = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect:' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die:' . $m ); }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
		public static function is_record_id( $v ) { return (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $v ) ); }
	}
}
if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	/** Contract: the two stamps the policy and this card read, and the record-to-account lookup. */
	class WPCPM_Students_Sync {
		const META_RECORD_ID   = 'wpcpm_student_record_id';
		const META_INSTITUTION = 'wpcpm_student_institution';
		public static function user_for_record( $record_id ) {
			foreach ( $GLOBALS['users'] as $user ) {
				if ( 0 === strcmp( (string) get_user_meta( $user->ID, self::META_RECORD_ID, true ), (string) $record_id ) ) { return $user; }
			}
			return null;
		}
	}
}
if ( ! class_exists( 'WPCPM_Mentor_Calls' ) ) {
	/** Contract: the account's own record stamp, shape-checked. */
	class WPCPM_Mentor_Calls {
		public static function student_record( $user_id ) {
			$record = (string) get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_RECORD_ID, true );
			return WPCPM_Mentors_Sync::is_record_id( $record ) ? $record : '';
		}
	}
}
if ( ! class_exists( 'WPCPM_Mentors_Dashboard' ) ) {
	/** Contract: the mentor's synced mentee list, which is the mentor notes' whole gate. */
	class WPCPM_Mentors_Dashboard {
		public static function get_mentees( $mentor_id ) { return $GLOBALS['mentees'][ (int) $mentor_id ] ?? array(); }
		public static function page_url() { return 'https://example.test/mentors/'; }
	}
}
if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	/** Contract: the memberships the policy's member ground walks. */
	class WPCPM_Institution_Members {
		public static function memberships_of( $user ) {
			$id = is_object( $user ) ? (int) $user->ID : (int) $user;
			return $GLOBALS['memberships'][ $id ] ?? array();
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	/** Contract: the gate the member ground applies, and nothing else is read from here. */
	class WPCPM_Institution_Agreement {
		public static function is_settled( $id ) { return in_array( $id, $GLOBALS['settled'], true ); }
	}
}
if ( ! class_exists( 'WPCPM_Roster_Index' ) ) {
	/** Only what the policy's `subject_index_row()` would reach for; nothing here asks for it. */
	class WPCPM_Roster_Index {
		public static function rows( $id ) { return array(); }
	}
}
if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
	/** Contract: where a saved note returns to. */
	class WPCPM_Institutions_Dashboard {
		public static function page_url() { return 'https://example.test/institutions/'; }
	}
}
if ( ! class_exists( 'WPCPM_Institution_Roster' ) ) {
	/** Contract: the switcher's argument, so a manager comes back to the school they were on. */
	class WPCPM_Institution_Roster {
		const ARG_VIEW = 'wpcpm_institution_view';
	}
}
if ( ! class_exists( 'WPCPM_Institution_Student_View' ) ) {
	/** Contract: the card's own argument, which is the destination of every redirect here. */
	class WPCPM_Institution_Student_View {
		const ARG = 'wpcpm_institution_student';
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-notes.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-notes.php';

$fail    = 0;
$checked = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checked;
	$ok = $actual === $expected;
	++$checked;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}
function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, (string) $needle ); }
/** The body of one method, walked by brace depth: enough to read the order of two calls. */
function method_body( $source, $name ) {
	if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\([^)]*\)\s*(?::\s*[^{]+)?\{/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}
	$offset = $m[0][1] + strlen( $m[0][0] );
	$depth  = 1;
	$end    = $offset;
	$length = strlen( $source );
	while ( $end < $length && $depth > 0 ) {
		if ( '{' === $source[ $end ] ) { ++$depth; } elseif ( '}' === $source[ $end ] ) { --$depth; }
		++$end;
	}
	return substr( $source, $offset, $end - $offset - 1 );
}
/**
 * Whether one call comes before another, with both of them present.
 *
 * `strpos()` answers a missing needle with false, and `false < 12` is true in PHP, so an
 * order assertion written the obvious way goes on passing for a handler that has lost the
 * very check it was meant to pin. Absence is the answer no, whichever of the two is gone.
 */
function before( $body, $first, $second ) {
	$one = strpos( (string) $body, $first );
	$two = strpos( (string) $body, $second );
	return false !== $one && false !== $two && $one < $two;
}
/** Run a handler and report how it ended: a redirect, a wp_die, or nothing at all. */
function run( $class, $method ) {
	try {
		call_user_func( array( $class, $method ) );
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
	return '';
}
/** The institution card's notes block, as one reader sees it. */
function institution_card( $viewer, $student_id ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_Notes::render( $student_id );
	return (string) ob_get_clean();
}
/** The mentor page's notes block, as one reader sees it. */
function mentor_card( $viewer, $record, $mentor_id ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Mentor_Notes::render( $record, 'Anna Kowalska', $mentor_id );
	return (string) ob_get_clean();
}
/** Write a note straight into the store, the way a release before this one would have. */
function legacy_note( $record, $text, $author = 2 ) {
	$id = wp_insert_post( array( 'post_type' => WPCPM_Mentor_Notes::POST_TYPE, 'post_content' => $text, 'post_author' => $author ) );
	update_post_meta( $id, WPCPM_Mentor_Notes::META_STUDENT, $record );
	return $id;
}
function ids( array $posts ) { return array_map( function ( $p ) { return (int) $p->ID; }, $posts ); }
/**
 * The newest note in the store.
 *
 * Not simply the newest post: every save writes an audit row through the same
 * `wp_insert_post()`, so "the last thing inserted" is the log entry and not the note.
 */
function last_note() {
	$id = 0;
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( WPCPM_Mentor_Notes::POST_TYPE === $post->post_type && $post->ID > $id ) { $id = (int) $post->ID; }
	}
	return $id;
}

$inst_a  = 'recINSTA000000001';
$inst_b  = 'recINSTB000000001';
$rec_s   = 'recSTUDENT0000001';
$rec_t   = 'recSTUDENT0000002';
$student = 5;
$other   = 9;

$GLOBALS['users'] = array(
	1 => new WP_User( 1, 'Manager', 'manager@example.test', array( 'administrator' ) ),
	2 => new WP_User( 2, 'Dan Mentor', 'dan@example.test', array( 'wpcpm_mentor' ) ),
	3 => new WP_User( 3, 'Ana Member A', 'ana@example.test', array( 'wpcpm_institution' ) ),
	4 => new WP_User( 4, 'Bo Member B', 'bo@example.test', array( 'wpcpm_institution' ) ),
	5 => new WP_User( 5, 'Anna Kowalska', 'anna@example.test', array( 'wpcpm_student' ) ),
	6 => new WP_User( 6, 'Cleo Colleague', 'cleo@example.test', array( 'wpcpm_institution' ) ),
	9 => new WP_User( 9, 'Other Student', 'other@example.test', array( 'wpcpm_student' ) ),
);
$GLOBALS['manage']      = array( 1 );
$GLOBALS['memberships'] = array( 3 => array( $inst_a ), 4 => array( $inst_b ), 6 => array( $inst_a ) );
$GLOBALS['settled']     = array( $inst_a, $inst_b );
$GLOBALS['mentees']     = array( 2 => array( array( 'record_id' => $rec_s, 'is_past' => false ) ) );

update_user_meta( $student, WPCPM_Students_Sync::META_RECORD_ID, $rec_s );
update_user_meta( $student, WPCPM_Students_Sync::META_INSTITUTION, $inst_a );
update_user_meta( $other, WPCPM_Students_Sync::META_RECORD_ID, $rec_t );
update_user_meta( $other, WPCPM_Students_Sync::META_INSTITUTION, $inst_b );

/* ---- an absent audience is the mentor's, and only absent is ------------- */

echo "=== Absent audience meta means the mentor's ===\n";

$old = legacy_note( $rec_s, 'Called her in March.' );

ck( 'a note written before the audience existed reads as the mentor\'s', WPCPM_Mentor_Notes::audience_of( $old ), WPCPM_Mentor_Notes::AUDIENCE_MENTOR );
ck( 'and it is in the mentor\'s list', ids( WPCPM_Mentor_Notes::get_notes( $rec_s, WPCPM_Mentor_Notes::AUDIENCE_MENTOR ) ), array( $old ) );
ck( 'and in no institution\'s', WPCPM_Mentor_Notes::get_notes( $rec_s, WPCPM_Mentor_Notes::AUDIENCE_INSTITUTION ), array() );

update_post_meta( $old, WPCPM_Mentor_Notes::META_AUDIENCE, 'project' );
ck( 'an audience nobody wrote comes back as it stands', WPCPM_Mentor_Notes::audience_of( $old ), 'project' );
ck( 'and lands on neither card', array( count( WPCPM_Mentor_Notes::get_notes( $rec_s, 'mentor' ) ), count( WPCPM_Mentor_Notes::get_notes( $rec_s, 'institution' ) ) ), array( 0, 0 ) );
ck( 'and is read by nobody, not even a program manager', WPCPM_Mentor_Notes::user_can_read_note( $old, 1 ), false );
delete_post_meta( $old, WPCPM_Mentor_Notes::META_AUDIENCE );

ck( 'an audience the class does not know is answered with nothing', WPCPM_Mentor_Notes::get_notes( $rec_s, 'everything' ), array() );
ck( 'and so is an empty one', WPCPM_Mentor_Notes::get_notes( $rec_s, '' ), array() );

/* ---- the signature itself ---------------------------------------------- */

echo "\n=== The audience cannot be left out ===\n";

$notes_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-notes.php' );

ck( 'get_notes() declares the audience with no default', (bool) preg_match( '/function get_notes\(\s*\$student_record,\s*\$audience\s*\)/', $notes_src ), true );
ck( 'count_notes() does the same', (bool) preg_match( '/function count_notes\(\s*\$student_record,\s*\$audience\s*\)/', $notes_src ), true );

try {
	$args = ( new ReflectionMethod( 'WPCPM_Mentor_Notes', 'get_notes' ) )->getNumberOfRequiredParameters();
} catch ( ReflectionException $e ) {
	$args = -1;
}
ck( 'and PHP agrees that both arguments are required', $args, 2 );

// Every call site outside the mentors dashboard, which is the piece that owns its own two
// lines and gets them from this piece's handover note. Listed rather than asserted there,
// so this suite pins what it owns and reports what it does not.
$pending = array();
$outside = array();

foreach ( (array) glob( WPCPM_PLUGIN_DIR . 'includes/**/*.php' ) as $path ) {
	if ( preg_match_all( '/(?:get_notes|count_notes)\(\s*\$[A-Za-z_][A-Za-z0-9_]*\s*\)/', (string) file_get_contents( $path ), $m ) ) {
		$where = basename( $path );
		foreach ( $m[0] as $call ) {
			if ( 'class-wpcpm-mentors-dashboard.php' === $where ) { $pending[] = $where . ': ' . $call; } else { $outside[] = $where . ': ' . $call; }
		}
	}
}

ck( 'nothing outside the mentors dashboard asks for notes without an audience', $outside, array() );
printf( "     still to change, in the piece that owns that file: %s\n", $pending ? implode( ', ', $pending ) : 'none' );

/* ---- the two audiences never cross ------------------------------------- */

echo "\n=== A mentor never reads the school's notes, and the school never reads the mentor's ===\n";

$_POST          = array( 'student' => $student, 'note' => "Her tutor rang.\n\nExtension agreed to June." );
$GLOBALS['uid'] = 3;
$saved          = run( 'WPCPM_Institution_Notes', 'handle_add' );
$school_note    = last_note();

ck( 'the member\'s note saved and returned to the student\'s card',
	$saved, 'redirect:https://example.test/institutions/?wpcpm_institution_student=5#wpcpm-institution-notes' );
ck( 'the nonce was keyed to the subject account', end( $GLOBALS['referer'] ), 'wpcpm_add_institution_note_5' );
ck( 'the note names its audience', get_post_meta( $school_note, WPCPM_Mentor_Notes::META_AUDIENCE, true ), WPCPM_Mentor_Notes::AUDIENCE_INSTITUTION );
ck( 'and the notebook it is in, from the decision', get_post_meta( $school_note, WPCPM_Institution_Notes::META_INSTITUTION, true ), $inst_a );
ck( 'and it is filed against the student\'s program record', get_post_meta( $school_note, WPCPM_Mentor_Notes::META_STUDENT, true ), $rec_s );
ck( 'the paragraphs survived, which posted_text() would have flattened', has( get_post( $school_note )->post_content, "\n" ), true );

ck( 'the school\'s note is not in the mentor\'s list', ids( WPCPM_Mentor_Notes::get_notes( $rec_s, WPCPM_Mentor_Notes::AUDIENCE_MENTOR ) ), array( $old ) );
ck( 'and the mentor\'s note is not in the school\'s', ids( WPCPM_Institution_Notes::notes_for( $rec_s, $inst_a ) ), array( $school_note ) );

$page = mentor_card( 2, $rec_s, 2 );
ck( 'the mentor\'s page draws the mentor\'s note', has( $page, 'Called her in March.' ), true );
ck( 'and not a word of the school\'s', has( $page, 'Extension agreed' ), false );

$card = institution_card( 3, $student );
ck( 'the school\'s card draws the school\'s note', has( $card, 'Extension agreed' ), true );
ck( 'and not a word of the mentor\'s', has( $card, 'Called her in March.' ), false );
ck( 'and says who reads what', has( $card, 'The mentor does not see them' ), true );
// And for how long, which is the half the promise used to leave out: the transfer block
// below is what makes an unqualified "stay with your institution" untrue.
ck( 'and for how long', has( $card, 'for as long as this student is on your roster' ), true );
ck( 'and what a transfer does to them', has( $card, 'leaves these notes behind' ), true );

// The same two cards for the one reader who passes every fence in this plugin. The manager
// is not the exception to the audience: they are the exception to the membership.
$page = mentor_card( 1, $rec_s, 2 );
$card = institution_card( 1, $student );
ck( 'a program manager on the mentor page reads the mentor\'s note', array( has( $page, 'Called her in March.' ), has( $page, 'Extension agreed' ) ), array( true, false ) );
ck( 'and on the school\'s card reads the school\'s', array( has( $card, 'Extension agreed' ), has( $card, 'Called her in March.' ) ), array( true, false ) );

ck( 'the mentor of this student cannot read the school\'s note', WPCPM_Mentor_Notes::user_can_read_note( $school_note, 2 ), false );
ck( 'the member of that school cannot read the mentor\'s', WPCPM_Mentor_Notes::user_can_read_note( $old, 3 ), false );
ck( 'a member of another school reads neither', array( WPCPM_Mentor_Notes::user_can_read_note( $school_note, 4 ), WPCPM_Mentor_Notes::user_can_read_note( $old, 4 ) ), array( false, false ) );
ck( 'a program manager reads both', array( WPCPM_Mentor_Notes::user_can_read_note( $school_note, 1 ), WPCPM_Mentor_Notes::user_can_read_note( $old, 1 ) ), array( true, true ) );

// A group session note is one object on several cards, so the mentor of any attendee may
// read it; writing one still needs every attendee, which is add_for_records()' rule.
$group = legacy_note( $rec_t, 'Session notes.' );
add_post_meta( $group, WPCPM_Mentor_Notes::META_STUDENT, $rec_s );
ck( 'a group note reaches the mentor who has one of its students', WPCPM_Mentor_Notes::user_can_read_note( $group, 2 ), true );
wp_delete_post( $group, true );

/* ---- the fence, and where the subject comes from ------------------------ */

echo "\n=== The subject is the account's own stamp, never the form's ===\n";

$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-notes.php' );

ck( 'handle_add() checks the nonce before it decides', before( method_body( $src, 'handle_add' ), 'check_admin_referer(', 'decide(' ), true );
ck( 'handle_delete() does too', before( method_body( $src, 'handle_delete' ), 'check_admin_referer(', 'decide(' ), true );
ck( 'and neither reads an institution out of the request', preg_match( '/posted_(text|key)\(\s*.institution/', $src ), 0 );

$_POST          = array( 'student' => $student, 'note' => 'Not my student.' );
$GLOBALS['uid'] = 4;
ck( 'a member of another school posting this account gets the one refusal',
	run( 'WPCPM_Institution_Notes', 'handle_add' ), 'wp_die:That record is not on your roster.' );

$_POST          = array( 'student' => 4242, 'note' => 'Nobody at all.' );
$GLOBALS['uid'] = 3;
ck( 'an account that does not exist reads exactly the same way',
	run( 'WPCPM_Institution_Notes', 'handle_add' ), 'wp_die:That record is not on your roster.' );

$_POST          = array( 'student' => $student, 'note' => 'The mentor writes here.' );
$GLOBALS['uid'] = 2;
ck( 'and so does the student\'s own mentor, who is not a member of anything',
	run( 'WPCPM_Institution_Notes', 'handle_add' ), 'wp_die:That record is not on your roster.' );

ck( 'none of the three wrote anything', ids( WPCPM_Institution_Notes::notes_for( $rec_s, $inst_a ) ), array( $school_note ) );

$refused = institution_card( 4, $student );
ck( 'and the card draws nothing at all for them', $refused, '' );

/* ---- the agreement gate ------------------------------------------------- */

echo "\n=== The gate the policy holds closes the notebook too ===\n";

$GLOBALS['settled'] = array( $inst_b );
ck( 'a member whose agreement is not settled is drawn no notes', institution_card( 3, $student ), '' );

$_POST          = array( 'student' => $student, 'note' => 'Before we signed.' );
$GLOBALS['uid'] = 3;
ck( 'and cannot write one', run( 'WPCPM_Institution_Notes', 'handle_add' ), 'wp_die:That record is not on your roster.' );
ck( 'a program manager is not held by that gate', has( institution_card( 1, $student ), 'Extension agreed' ), true );
$GLOBALS['settled'] = array( $inst_a, $inst_b );

/* ---- a transfer leaves the old school's notes behind -------------------- */

echo "\n=== The notebook is the decision's institution ===\n";

update_user_meta( $student, WPCPM_Students_Sync::META_INSTITUTION, $inst_b );
ck( 'the new school sees a card', has( institution_card( 4, $student ), 'wpcpm-notes' ), true );
ck( 'and not the old school\'s notes on it', has( institution_card( 4, $student ), 'Extension agreed' ), false );
ck( 'the old school\'s member no longer reads them either', WPCPM_Mentor_Notes::user_can_read_note( $school_note, 3 ), false );
ck( 'and neither does the new school\'s', WPCPM_Mentor_Notes::user_can_read_note( $school_note, 4 ), false );
// Nor the reader who passes every other fence in this plugin: the card is decided against the
// student's own stamp, and that now names the new school. This is the fact the intro is
// worded around - "with your institution and with the program managers" is true while the
// student is on the roster and true of nobody afterwards.
ck( 'and neither does a program manager, which is why the card promises only the roster', WPCPM_Mentor_Notes::user_can_read_note( $school_note, 1 ), false );
update_user_meta( $student, WPCPM_Students_Sync::META_INSTITUTION, $inst_a );
ck( 'they are still there for the school that wrote them', ids( WPCPM_Institution_Notes::notes_for( $rec_s, $inst_a ) ), array( $school_note ) );

/* ---- the audit row ------------------------------------------------------ */

echo "\n=== One audit row, with the ground and without the note ===\n";

$entries = WPCPM_Institution_Audit::entries_for( $inst_a );

ck( 'one row for the one note', count( $entries ), 1 );
ck( 'it names the kind', $entries[0]['kind'], WPCPM_Institution_Notes::KIND_NOTE_ADDED );
ck( 'the ground the decision was allowed on', $entries[0]['ground'], WPCPM_Institution_Policy::GROUND_MEMBER );
ck( 'what the decision was made against', $entries[0]['evidence'], WPCPM_Institution_Audit::EVIDENCE_CACHE );
ck( 'who did it', $entries[0]['actor'], 3 );
ck( 'and which student', $entries[0]['subject'], $rec_s );
ck( 'the note itself is nowhere in the row', has( serialize( $entries[0] ), 'Extension agreed' ), false );
ck( 'and the row points at the note rather than repeating it', $entries[0]['data'], array( 'note' => $school_note, 'account' => $student ) );

/* ---- writing, deleting, and who may -------------------------------------- */

echo "\n=== The card ships with its write handler ===\n";

$GLOBALS['hooks'] = array();
WPCPM_Institution_Notes::init();
ck( 'both handlers are registered', $GLOBALS['hooks'], array( 'admin_post_wpcpm_add_institution_note', 'admin_post_wpcpm_delete_institution_note' ) );
ck( 'the card carries the add form for a member', has( institution_card( 3, $student ), 'name="action" value="wpcpm_add_institution_note"' ), true );
ck( 'with the nonce keyed to the account', has( institution_card( 3, $student ), 'value="nonce-wpcpm_add_institution_note_5"' ), true );

$_POST          = array( 'student' => $student, 'note' => '<script>alert(1)</script> Tutor & Co.' );
$GLOBALS['uid'] = 3;
run( 'WPCPM_Institution_Notes', 'handle_add' );
$script_note = last_note();
ck( 'a pasted tag is stripped on the way in', has( get_post( $script_note )->post_content, '<script' ), false );
// The ampersand and not the tag: the tag is gone before it reaches the page, so drawing it
// safely proves nothing. What survives the sanitiser is what the renderer has to escape.
ck( 'and what survives it is escaped on the way out', has( institution_card( 3, $student ), 'Tutor &amp; Co.' ), true );

$_POST          = array( 'student' => $student, 'note' => '   ' );
$GLOBALS['uid'] = 3;
ck( 'an empty note is refused where it was typed', run( 'WPCPM_Institution_Notes', 'handle_add' ), 'redirect:https://example.test/institutions/?wpcpm_institution_student=5#wpcpm-institution-notes' );
ck( 'and nothing was written', count( WPCPM_Institution_Notes::notes_for( $rec_s, $inst_a ) ), 2 );

// One render for this account and no other: WPCPM_Flash::take() memoizes per request, and a
// CLI run is one request, so a second reader of this channel would be answered from the memo.
$_POST          = array( 'student' => $student, 'note' => 'Saved by a colleague.' );
$GLOBALS['uid'] = 6;
run( 'WPCPM_Institution_Notes', 'handle_add' );
$colleague_note = last_note();
ck( 'the outcome is queued for the card it happened on', has( institution_card( 6, $student ), 'Note saved.' ), true );

echo "\n=== Deleting ===\n";

$_POST          = array( 'note_id' => $colleague_note );
$GLOBALS['uid'] = 3;
ck( 'a colleague cannot delete somebody else\'s note', run( 'WPCPM_Institution_Notes', 'handle_delete' ), 'wp_die:You can only delete your own notes.' );

$_POST          = array( 'note_id' => $school_note );
$GLOBALS['uid'] = 4;
ck( 'and a member of another school gets the one refusal', run( 'WPCPM_Institution_Notes', 'handle_delete' ), 'wp_die:That record is not on your roster.' );

$_POST          = array( 'note_id' => $old );
$GLOBALS['uid'] = 1;
ck( 'the school\'s handler will not delete a mentor\'s note, even for a manager', run( 'WPCPM_Institution_Notes', 'handle_delete' ), 'wp_die:That record is not on your roster.' );

$_POST          = array( 'note_id' => $school_note, 'mentor' => 2 );
$GLOBALS['uid'] = 2;
ck( 'and the mentor page will not delete the school\'s', run( 'WPCPM_Mentor_Notes', 'handle_delete' ), 'wp_die:You cannot change notes for that student.' );
ck( 'the note survived all four', get_post( $school_note ) instanceof WP_Post, true );

$_POST          = array( 'note_id' => $colleague_note );
$GLOBALS['uid'] = 6;
ck( 'its author deletes their own', run( 'WPCPM_Institution_Notes', 'handle_delete' ), 'redirect:https://example.test/institutions/?wpcpm_institution_student=5#wpcpm-institution-notes' );
ck( 'and it is gone', get_post( $colleague_note ), null );
ck( 'with a row saying so, and still no text in it',
	array( WPCPM_Institution_Audit::entries_for( $inst_a )[0]['kind'], has( serialize( WPCPM_Institution_Audit::entries_for( $inst_a ) ), 'Saved by a colleague' ) ),
	array( WPCPM_Institution_Notes::KIND_NOTE_DELETED, false ) );

$_POST          = array( 'note_id' => $school_note );
$GLOBALS['uid'] = 1;
ck( 'a program manager deletes a school\'s note', run( 'WPCPM_Institution_Notes', 'handle_delete' ), 'redirect:https://example.test/institutions/?wpcpm_institution_student=5&wpcpm_institution_view=' . $inst_a . '#wpcpm-institution-notes' );

/* ---- the outcome with nowhere else to be printed ------------------------ */

echo "\n=== A refusal the card has to draw, because nothing else can ===\n";

// A student the school can open and has no program record for: the form is only drawn where
// both halves of a notebook hold, so this is what a sync between the draw and the press
// leaves behind. `handle_add()` refuses on exactly the condition `render()` draws no notebook
// on, so unless that branch prints the outcome, the writer is told nothing and the message
// sits in user meta until it appears on some other card weeks later.
$GLOBALS['users'][10]       = new WP_User( 10, 'Unrecorded Student', 'unrecorded@example.test', array( 'wpcpm_student' ) );
$GLOBALS['users'][11]       = new WP_User( 11, 'Fen Member A', 'fen@example.test', array( 'wpcpm_institution' ) );
$GLOBALS['memberships'][11] = array( $inst_a );
update_user_meta( 10, WPCPM_Students_Sync::META_INSTITUTION, $inst_a );

$_POST          = array( 'student' => 10, 'note' => 'Nowhere to file this.' );
$GLOBALS['uid'] = 11;
ck( 'a note about a student with no program record goes back to their card',
	run( 'WPCPM_Institution_Notes', 'handle_add' ), 'redirect:https://example.test/institutions/?wpcpm_institution_student=10#wpcpm-institution-notes' );

$unlinked = institution_card( 11, 10 );

ck( 'and the card says why nothing was saved', has( $unlinked, 'there is no notebook to file a note in' ), true );
ck( 'inside the block the redirect points at', has( $unlinked, 'id="wpcpm-institution-notes"' ), true );
ck( 'with no notebook drawn around it', array(
	has( $unlinked, 'wpcpm-notes__intro' ),
	has( $unlinked, 'wpcpm-notes__list' ),
	has( $unlinked, 'wpcpm-notes__form' ),
), array( false, false, false ) );
ck( 'and the outcome is not left sitting in user meta', get_user_meta( 11, WPCPM_Flash::META, true ), '' );
ck( 'nothing was written', count( WPCPM_Institution_Notes::notes_for( $rec_s, $inst_a ) ), 1 );

/* ---- the mentor's own notes still work ---------------------------------- */

echo "\n=== Nothing about the mentor's notes changed but the argument ===\n";

$_POST          = array( 'student' => $rec_s, 'student_name' => 'Anna Kowalska', 'note' => 'Second call.', 'mentor' => 2 );
$GLOBALS['uid'] = 2;
$outcome        = run( 'WPCPM_Mentor_Notes', 'handle_add' );
$mentor_note    = last_note();

ck( 'the mentor saves a note', has( $outcome, 'redirect:https://example.test/mentors/' ), true );
ck( 'and it names its audience rather than leaning on the default', get_post_meta( $mentor_note, WPCPM_Mentor_Notes::META_AUDIENCE, true ), WPCPM_Mentor_Notes::AUDIENCE_MENTOR );
ck( 'the mentor\'s count is the mentor\'s', WPCPM_Mentor_Notes::count_notes( $rec_s, WPCPM_Mentor_Notes::AUDIENCE_MENTOR ), 2 );
ck( 'and the school\'s is the school\'s', WPCPM_Mentor_Notes::count_notes( $rec_s, WPCPM_Mentor_Notes::AUDIENCE_INSTITUTION ), 1 );

/* ---- the stand-ins agree with the real files ---------------------------- */

echo "\n=== The stand-ins agree with the files they stand in for ===\n";

$view_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-view.php' );
$roster_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster.php' );

preg_match( "/const ARG\s*=\s*'([^']+)'/", $view_src, $m );
ck( 'the student card\'s argument', $m[1] ?? '', WPCPM_Institution_Student_View::ARG );
preg_match( "/const ARG_VIEW\s*=\s*'([^']+)'/", $roster_src, $m );
ck( 'the switcher\'s argument', $m[1] ?? '', WPCPM_Institution_Roster::ARG_VIEW );

$dashes = array();

foreach ( array( 'includes/modules/class-wpcpm-institution-notes.php', 'bin/test-institution-notes.php' ) as $rel ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $rel ) ) ) {
		$dashes[] = $rel;
	}
}

ck( 'no dash but the plain hyphen in either new file', $dashes, array() );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS ($checked checks)\n" );
exit( $fail ? 1 : 0 );
