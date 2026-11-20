<?php
/**
 * Need help? notes in the notes list (AI Mentor R2, 1.123.103).
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **A note without the source meta is the mentor's.** `is_mentors()` is the one place that sentence is
 *   written for the source, as `audience_of()` is for the audience: absent is the mentor's, `need_help` is the
 *   AI Mentor's, and a value nothing here wrote is neither, so it fails closed.
 * - **No count reads a Need help? note as the mentor's.** `count_notes()` and `get_notes()` leave it out
 *   unless asked for it, and the notes heading's count is the mentor's alone.
 * - **The markup stays out of the card's script.** A Need help? note is a `li.wpcpm-need-help-note` in its own
 *   `ol.wpcpm-need-help-notes`, labelled "Need help?", never carrying the classes `wpcpm-note` or
 *   `wpcpm-note__meta` that `assets/js/triage.js` counts and reads (Ruling 10). Only an Administrator sees a
 *   delete control on one.
 * - **`add_need_help_note()` writes the one kind of row.** It refuses with `mentor_of()`'s code, writes the
 *   student as the post's author, the audience `mentor`, the source and the student's record, cuts at 2,000,
 *   answers `limit` past ten a UTC day without claiming, and answers `duplicate` for the same words within
 *   24 hours.
 *
 * Nothing touches the network or a database: posts, options and users are stand-ins; the ceiling and the
 * typed-text reader are the real classes.
 *
 * Run from the plugin root:  php bin/test-mentor-notes.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_VERSION', 'test' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['opts']    = array( 'date_format' => 'Y-m-d', 'time_format' => 'H:i' );
$GLOBALS['umeta']   = array();
$GLOBALS['users']   = array();
$GLOBALS['uid']     = 0;
$GLOBALS['manage']  = array();
$GLOBALS['posts']   = array();
$GLOBALS['pmeta']   = array();
$GLOBALS['next']    = 100;
$GLOBALS['accounts'] = array();
$GLOBALS['mentors']  = array();
$GLOBALS['mentees']  = array();
$GLOBALS['clock']    = time();

class WP_Error {
	private $code;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; }
	public function get_error_code() { return $this->code; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = 'private', $post_author = 0, $post_content = '', $post_title = '', $post_date_gmt = '';
	public function __construct( array $a = array() ) {
		foreach ( $a as $k => $v ) {
			$this->$k = $v;
		}
	}
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr__( $s, $d = null ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function number_format_i18n( $n ) { return (string) $n; }
function wpautop( $s ) { return '<p>' . $s . '</p>'; }
function wp_kses_post( $s ) { return $s; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_nonce_field( $a = -1 ) { echo '<input type="hidden" name="_wpnonce" value="nonce" />'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? $GLOBALS['clock'] : $t ); }
function apply_filters( $t, $v ) { return $v; }
function add_action( $hook, $cb, $p = 10, $a = 1 ) {}
function add_filter( $hook, $cb, $p = 10, $a = 1 ) {}
function sanitize_html_class( $s ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $s ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_unslash( $v ) { return $v; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_option( $k, $v, $dep = '', $auto = true ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function update_option( $k, $v, $auto = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }
function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_current_user_id() { return $GLOBALS['uid']; }
function user_can( $user, $cap ) {
	$id = is_object( $user ) ? $user->ID : (int) $user;
	return WPCPM_Roles::CAP_MANAGE === $cap && in_array( $id, $GLOBALS['manage'], true );
}
function current_user_can( $cap ) { return user_can( $GLOBALS['uid'], $cap ); }
function register_post_type( $type, $args = array() ) {}
function check_admin_referer( $a = -1 ) { return true; }
function wp_die( $m = '', $code = 0 ) { throw new Exception( 'denied ' . $code ); }

function wp_insert_post( array $a, $wp_error = false ) {
	$id                      = $GLOBALS['next']++;
	$a                       = stripslashes_deep( $a );
	$a['ID']                 = $id;
	$a['post_date_gmt']      = gmdate( 'Y-m-d H:i:s', $GLOBALS['clock'] );
	$GLOBALS['posts'][ $id ] = new WP_Post( $a );
	return $id;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = array( stripslashes_deep( $v ) ); return true; }
function add_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ][] = stripslashes_deep( $v ); return true; }
function get_post_meta( $id, $k, $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? array();
	return $single ? ( $rows[0] ?? '' ) : $rows;
}
function get_post_time( $f, $gmt, $post, $translate = false ) {
	return 'U' === $f ? strtotime( $post->post_date_gmt . ' UTC' ) : gmdate( $f, strtotime( $post->post_date_gmt . ' UTC' ) );
}
function get_posts( array $args ) {
	$out = array();
	$ids = array_keys( $GLOBALS['posts'] );
	rsort( $ids );
	foreach ( $ids as $id ) {
		$post = $GLOBALS['posts'][ $id ];
		if ( $post->post_type !== $args['post_type'] ) { continue; }
		if ( isset( $args['author'] ) && (int) $post->post_author !== (int) $args['author'] ) { continue; }
		$ok = true;
		foreach ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			$ok = $ok && in_array( (string) $clause['value'], array_map( 'strval', get_post_meta( $id, $clause['key'] ) ), true );
		}
		if ( $ok ) { $out[] = $post; }
		if ( isset( $args['numberposts'] ) && $args['numberposts'] > 0 && count( $out ) >= $args['numberposts'] ) { break; }
	}
	return $out;
}

class WPCPM_Flash {
	public static function take( $c ) { return ''; }
	public static function set( $c, $v, $u = 0 ) {}
}
class WPCPM_Request {
	public static function text( $k ) { return ''; }
}
class WPCPM_Mentors_Sync {
	const META_RECORD_ID = 'wpcpm_mentor_record_id';
	public static function is_record_id( $s ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $s ) ); }
}
class WPCPM_Mentor_Calls {
	public static function student_record( $user_id ) { return $GLOBALS['accounts'][ (int) $user_id ] ?? ''; }
	public static function mentor_for_student( $user_id ) {
		$id = $GLOBALS['mentors'][ (int) $user_id ] ?? 0;
		return $id ? ( $GLOBALS['users'][ $id ] ?? null ) : null;
	}
}
class WPCPM_Mentors_Dashboard {
	public static function get_mentees( $mentor_id ) { return $GLOBALS['mentees'][ (int) $mentor_id ] ?? array(); }
	public static function page_url() { return 'https://example.test/mentors/'; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-questions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-notes.php';

WPCPM_Ceiling::for_tests( function () { return $GLOBALS['clock']; } );

$fail = 0;
function ck( $label, $actual, $expected = true ) {
	global $fail;
	$ok = $actual === $expected;
	echo ( $ok ? 'PASS' : 'FAIL' ) . ': ' . $label . "\n";
	if ( ! $ok ) {
		$fail++;
		echo '  got:      ' . var_export( $actual, true ) . "\n  expected: " . var_export( $expected, true ) . "\n";
	}
}
function code_of( $r ) { return is_wp_error( $r ) ? $r->get_error_code() : 'ok'; }

// Student A (10) has Mentor A (30), who holds them; Student B (11) is past; Student C (12) was lost from the
// list; Administrator A (50) manages.
$GLOBALS['users'][10] = new WP_User( 10, 'Student A', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][11] = new WP_User( 11, 'Student B', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][12] = new WP_User( 12, 'Student C', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][30] = new WP_User( 30, 'Mentor A', array( WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['users'][50] = new WP_User( 50, 'Administrator A', array( 'administrator' ) );
$GLOBALS['umeta'][30][ WPCPM_Mentors_Sync::META_RECORD_ID ] = 'recMEN00000000001';
$GLOBALS['manage']  = array( 50 );
$GLOBALS['accounts'] = array( 10 => 'recSTU00000000010', 11 => 'recSTU00000000011', 12 => 'recSTU00000000012' );
$GLOBALS['mentors']  = array( 10 => 30, 11 => 30, 12 => 30 );
$GLOBALS['mentees']  = array(
	30 => array(
		array( 'record_id' => 'recSTU00000000010', 'name' => 'Student A', 'is_past' => false ),
		array( 'record_id' => 'recSTU00000000011', 'name' => 'Student B', 'is_past' => true ),
	),
);
$record = 'recSTU00000000010';

function switch_to( $on ) { $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array( 'handbook_to_mentors' => $on ); }

/** A note row as it stood before this release, or with a source. */
function seed_note( $author, $record, $source = null, $text = 'A note.', $at = null ) {
	$id = wp_insert_post( array( 'post_type' => WPCPM_Mentor_Notes::POST_TYPE, 'post_status' => 'private', 'post_author' => $author, 'post_content' => $text ) );
	if ( null !== $at ) {
		$GLOBALS['posts'][ $id ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', $at );
	}
	update_post_meta( $id, WPCPM_Mentor_Notes::META_STUDENT, $record );
	if ( null !== $source ) {
		update_post_meta( $id, WPCPM_Mentor_Notes::META_SOURCE, $source );
	}
	return $id;
}

echo "\n=== The constants ===\n";

ck( 'the source meta', WPCPM_Mentor_Notes::META_SOURCE, '_wpcpm_note_source' );
ck( 'the source value', WPCPM_Mentor_Notes::SOURCE_NEED_HELP, 'need_help' );
ck( 'the length', WPCPM_Mentor_Notes::NEED_HELP_MAX, 2000 );
ck( 'the daily limit', WPCPM_Mentor_Notes::NEED_HELP_PER_DAY, 10 );

echo "\n=== Whose note it is ===\n";

$plain  = seed_note( 30, $record, null, 'Plain mentor note.' );
$named  = seed_note( 30, $record, '', 'Empty source.' );
$helped = seed_note( 10, $record, 'need_help', 'Need help? note.' );
$odd    = seed_note( 30, $record, 'someone_else', 'Hand edited.' );

ck( 'a note without the meta is the mentor\'s', WPCPM_Mentor_Notes::is_mentors( $plain ), true );
ck( 'an empty source is the mentor\'s too', WPCPM_Mentor_Notes::is_mentors( $named ), true );
ck( 'a Need help? note is not the mentor\'s', WPCPM_Mentor_Notes::is_mentors( $helped ), false );
ck( 'a source nothing here wrote is not the mentor\'s: it fails closed', WPCPM_Mentor_Notes::is_mentors( $odd ), false );
ck( 'it takes the post as well as its ID', WPCPM_Mentor_Notes::is_mentors( $GLOBALS['posts'][ $helped ] ), false );

echo "\n=== Counts leave a Need help? note out ===\n";

$ids = function ( array $posts ) { return array_map( function ( $p ) { return $p->ID; }, $posts ); };
ck( 'get_notes() by default is the mentor\'s notes: a Need help? note and a hand-edited one are left out',
	$ids( WPCPM_Mentor_Notes::get_notes( $record, WPCPM_Mentor_Notes::AUDIENCE_MENTOR ) ), array( $named, $plain ) );
ck( 'count_notes() is the mentor\'s alone', WPCPM_Mentor_Notes::count_notes( $record, WPCPM_Mentor_Notes::AUDIENCE_MENTOR ), 2 );
ck( 'asked for them, get_notes() adds the Need help? note and still not the hand-edited one',
	$ids( WPCPM_Mentor_Notes::get_notes( $record, WPCPM_Mentor_Notes::AUDIENCE_MENTOR, true ) ), array( $helped, $named, $plain ) );
ck( 'a Need help? note of the same student does not make the institution audience any fuller',
	$ids( WPCPM_Mentor_Notes::get_notes( $record, WPCPM_Mentor_Notes::AUDIENCE_INSTITUTION, true ) ), array() );

echo "\n=== The notes list ===\n";

$GLOBALS['uid'] = 30;
ob_start();
WPCPM_Mentor_Notes::render( $record, 'Student A', 30 );
$mentor_html = ob_get_clean();

ck( 'the heading counts the mentor\'s notes alone', false !== strpos( $mentor_html, '<span class="wpcpm-notes__count">2</span>' ) );
ck( 'the Need help? note is in its own ol.wpcpm-need-help-notes as an li.wpcpm-need-help-note',
	1 === preg_match( '#<ol class="wpcpm-need-help-notes">\s*<li class="wpcpm-need-help-note">#', $mentor_html ) );
ck( 'it is labelled Need help?', false !== strpos( $mentor_html, 'wpcpm-need-help-note__label">Need help?<' ) );
ck( 'it holds the words', false !== strpos( $mentor_html, 'Need help? note.' ) );
ck( 'it comes after the mentor\'s own list', strpos( $mentor_html, 'wpcpm-need-help-notes' ) > strpos( $mentor_html, 'wpcpm-notes__list' ) );

$need_help_part = substr( $mentor_html, strpos( $mentor_html, 'wpcpm-need-help-notes' ) );
$need_help_part = substr( $need_help_part, 0, strpos( $need_help_part, '</ol>' ) );
ck( 'none of the Need help? markup carries the class wpcpm-note or wpcpm-note__meta (Ruling 10)',
	0, preg_match( '/class="[^"]*\bwpcpm-note(__[a-z-]+)?\b[^"]*"/', $need_help_part ) );
ck( 'and holds no time[datetime] for the card\'s script to read', false === strpos( $need_help_part, 'datetime=' ) );
ck( 'the mentor\'s own notes list holds the two mentor notes as li.wpcpm-note and nothing else',
	2, preg_match_all( '#<li class="wpcpm-note">#', $mentor_html ) );
ck( 'the mentor sees no delete control on a Need help? note', false === strpos( $need_help_part, '<form' ) && false === strpos( $need_help_part, 'wpcpm_delete_note' ) );

$GLOBALS['uid'] = 50;
ob_start();
WPCPM_Mentor_Notes::render( $record, 'Student A', 30 );
$admin_html = ob_get_clean();
$admin_part = substr( $admin_html, strpos( $admin_html, 'wpcpm-need-help-notes' ) );
$admin_part = substr( $admin_part, 0, strpos( $admin_part, '</ol>' ) );
ck( 'an Administrator sees a delete control on it, using the existing action and nonce',
	false !== strpos( $admin_part, 'value="wpcpm_delete_note"' ) && false !== strpos( $admin_part, 'name="note_id" value="' . $helped . '"' ) && false !== strpos( $admin_part, '_wpnonce' ) );
ck( 'and that control is not drawn with the mentor notes\' delete classes',
	false === strpos( $admin_part, 'wpcpm-note__delete' ) );

// A student with no Need help? note draws no second list.
$GLOBALS['uid'] = 30;
$other = 'recSTU00000000011';
seed_note( 30, $other, null, 'Only a mentor note.' );
ob_start();
WPCPM_Mentor_Notes::render( $other, 'Student B', 30 );
$none_html = ob_get_clean();
ck( 'with no Need help? note there is no second list', false === strpos( $none_html, 'wpcpm-need-help-notes' ) );

// The $before argument: markup already escaped by its maker, printed first inside the container.
$GLOBALS['uid'] = 30;
ob_start();
WPCPM_Mentor_Notes::render( $other, 'Student B', 30, '<ol class="wpcpm-questions"><li class="wpcpm-question">Q?</li></ol>' );
$before_html = ob_get_clean();
ck( 'the questions list is the first child inside the notes container, before the notes heading',
	0 === strpos( $before_html, '<section class="wpcpm-notes"><ol class="wpcpm-questions"><li class="wpcpm-question">Q?</li></ol><h4 class="wpcpm-notes__title">' ) );
ck( 'nothing else in the notes changes: with the list taken out, the markup is the same as with none',
	$none_html === str_replace( '<ol class="wpcpm-questions"><li class="wpcpm-question">Q?</li></ol>', '', $before_html ) );
$GLOBALS['uid'] = 99;
ob_start();
WPCPM_Mentor_Notes::render( $other, 'Student B', 30, '<ol class="wpcpm-questions"><li>Q?</li></ol>' );
ck( 'a viewer who may not read the student sees neither the notes nor the questions', '', ob_get_clean() );
$GLOBALS['uid'] = 30;

echo "\n=== Deleting one ===\n";

ck( 'a mentor, who is not its author, cannot delete a Need help? note by the existing handler',
	( function () use ( $helped ) {
		$GLOBALS['uid'] = 30;
		$_POST          = array( 'note_id' => (string) $helped, '_wpnonce' => 'x' );
		try {
			WPCPM_Mentor_Notes::handle_delete();
		} catch ( Exception $e ) {
			return $e->getMessage();
		}
		return 'returned';
	} )() === 'denied 403' && isset( $GLOBALS['posts'][ $helped ] ) );

echo "\n=== Writing one ===\n";

switch_to( false );
$GLOBALS['uid'] = 10;
ck( 'with the switch off, the refusal is mentor_of\'s', code_of( WPCPM_Mentor_Notes::add_need_help_note( 10, 'Words about the student.' ) ), 'wpcpm_send_off' );
switch_to( true );
ck( 'an account that is not a student', code_of( WPCPM_Mentor_Notes::add_need_help_note( 30, 'Words about the student.' ) ), 'wpcpm_not_student' );
ck( 'a student whose mentor\'s list lost them', code_of( WPCPM_Mentor_Notes::add_need_help_note( 12, 'Words about the student.' ) ), 'wpcpm_mentor_gone' );
ck( 'a past student', code_of( WPCPM_Mentor_Notes::add_need_help_note( 11, 'Words about the student.' ) ), 'wpcpm_past' );
ck( 'words of fewer than three characters', code_of( WPCPM_Mentor_Notes::add_need_help_note( 10, ' a ' ) ), 'wpcpm_note_short' );

$before = count( $GLOBALS['posts'] );
$got    = WPCPM_Mentor_Notes::add_need_help_note( 10, "  Asked about the final project twice.\nStill unsure.  " );
ck( 'a student with a mentor who holds them writes a note', is_wp_error( $got ), false );
ck( 'the answer has exactly the four keys', is_array( $got ) ? array_keys( $got ) : null, array( 'note_id', 'student_record_id', 'state', 'written_at' ) );
ck( 'its state is written and its record is the student\'s', is_array( $got ) ? array( $got['state'], $got['student_record_id'] ) : null, array( 'written', $record ) );
ck( 'its time is now', is_array( $got ) ? abs( $got['written_at'] - time() ) <= 2 : null, true );
ck( 'one row more', count( $GLOBALS['posts'] ), $before + 1 );

$note = is_array( $got ) ? get_post( $got['note_id'] ) : null;
ck( 'the row is a private mentor note whose author is the student',
	$note ? array( $note->post_type, $note->post_status, (int) $note->post_author ) : null, array( WPCPM_Mentor_Notes::POST_TYPE, 'private', 10 ) );
ck( 'its words are trimmed', $note ? $note->post_content : null, "Asked about the final project twice.\nStill unsure." );
ck( 'its audience is the mentor\'s, written out', $note ? get_post_meta( $note->ID, WPCPM_Mentor_Notes::META_AUDIENCE, true ) : null, 'mentor' );
ck( 'its source is need_help', $note ? get_post_meta( $note->ID, WPCPM_Mentor_Notes::META_SOURCE, true ) : null, 'need_help' );
ck( 'it is about the student\'s record', $note ? get_post_meta( $note->ID, WPCPM_Mentor_Notes::META_STUDENT, false ) : null, array( $record ) );
ck( 'it is not the mentor\'s and so not counted', $note ? WPCPM_Mentor_Notes::is_mentors( $note ) : null, false );
ck( 'the mentor\'s count has not moved', WPCPM_Mentor_Notes::count_notes( $record, WPCPM_Mentor_Notes::AUDIENCE_MENTOR ), 2 );
ck( 'and the mentor may read it', $note ? WPCPM_Mentor_Notes::user_can_read_note( $note, 30 ) : null, true );

echo "\n=== The cut at 2,000 ===\n";

$long = WPCPM_Mentor_Notes::add_need_help_note( 10, str_repeat( 'é', 2400 ) );
$post = is_array( $long ) ? get_post( $long['note_id'] ) : null;
ck( 'a longer note is cut at 2,000 characters and not refused', $post ? array( 'written' === $long['state'], mb_strlen( $post->post_content ) ) : null, array( true, 2000 ) );

echo "\n=== The same words twice ===\n";

$again = WPCPM_Mentor_Notes::add_need_help_note( 10, "Asked about the final project twice.\r\nStill unsure." );
ck( 'the same words within 24 hours are a duplicate, not an error', is_array( $again ) ? $again['state'] : code_of( $again ), 'duplicate' );
ck( 'and write no row', count( $GLOBALS['posts'] ), $before + 2 );
ck( 'the duplicate points at the first note', is_array( $again ) ? $again['note_id'] : null, is_array( $got ) ? $got['note_id'] : null );

// Move the first note and the long one to 25 hours ago: the words may be written again.
foreach ( array( $got['note_id'], $long['note_id'] ) as $old ) {
	$GLOBALS['posts'][ $old ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', $GLOBALS['clock'] - DAY_IN_SECONDS - 3600 );
}
$later = WPCPM_Mentor_Notes::add_need_help_note( 10, 'Asked about the final project twice. Still unsure.' );
ck( 'different words are written', is_array( $later ) ? $later['state'] : code_of( $later ), 'written' );
$again = WPCPM_Mentor_Notes::add_need_help_note( 10, "Asked about the final project twice.\nStill unsure." );
ck( 'the same words after 24 hours are written again', is_array( $again ) ? $again['state'] : code_of( $again ), 'written' );

echo "\n=== Ten a day ===\n";

// Start a clean UTC day: the counters are options, so clear them and the earlier rows.
foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
	if ( 0 === strpos( $name, WPCPM_Ceiling::PREFIX ) ) {
		unset( $GLOBALS['opts'][ $name ] );
	}
}
$GLOBALS['clock'] = time() + 5 * DAY_IN_SECONDS;
$states           = array();
for ( $i = 1; $i <= 10; $i++ ) {
	$r        = WPCPM_Mentor_Notes::add_need_help_note( 10, 'Note number ' . $i . ' of the day.' );
	$states[] = is_array( $r ) ? $r['state'] : code_of( $r );
}
ck( 'ten notes of a day are written', array_unique( $states ), array( 'written' ) );
$key = WPCPM_Ceiling::key( 'need_help_day_10' );
ck( 'the day\'s count is ten', WPCPM_Ceiling::count( $key, DAY_IN_SECONDS ), 10 );
$rows   = count( $GLOBALS['posts'] );
$eleven = WPCPM_Mentor_Notes::add_need_help_note( 10, 'Note number eleven of the day.' );
ck( 'the eleventh answers limit, as an answer and not an error', is_array( $eleven ) ? $eleven['state'] : code_of( $eleven ), 'limit' );
ck( 'it writes no row and claims nothing', array( count( $GLOBALS['posts'] ), WPCPM_Ceiling::count( $key, DAY_IN_SECONDS ) ), array( $rows, 10 ) );
ck( 'its note id is nothing', is_array( $eleven ) ? $eleven['note_id'] : null, 0 );
$GLOBALS['clock'] += DAY_IN_SECONDS;
$next_day = WPCPM_Mentor_Notes::add_need_help_note( 10, 'Note number twelve, the next day.' );
ck( 'the next UTC day writes again', is_array( $next_day ) ? $next_day['state'] : code_of( $next_day ), 'written' );

echo "\n" . ( $fail ? "FAILURES: $fail\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
