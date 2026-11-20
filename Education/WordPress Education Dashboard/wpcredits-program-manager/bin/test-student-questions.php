<?php
/**
 * A student's question to their mentor: the switch, and who may send (AI Mentor R2, 1.123.103).
 *
 * `WPCPM_Student_Questions::switched_on()` reads the one switch, **Send to mentors**, which ships off.
 * `mentor_of()` decides for every later caller whether a signed-in account may send, and returns the
 * student, the mentor and the row, or the first refusal in the plan's order: the switch off, not a student,
 * no Students record, no mentor, a mentor whose list lost the student, a past student.
 *
 * Sending (Task 3): `send()` saves the question as a private post, emails the mentor through the template
 * registry with the student's address as Reply-To, keeps three a day per student through `WPCPM_Ceiling`, and
 * answers a repeat within 24 hours with `duplicate` before it spends a place of the day. `for_student()` lists
 * the waiting questions and the answered ones of the last 30 days, newest first.
 *
 * Run from the plugin root:  php bin/test-student-questions.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['opts']       = array();
$GLOBALS['umeta']      = array();
$GLOBALS['users']      = array();
$GLOBALS['post_types'] = array();
$GLOBALS['actions']    = array();
$GLOBALS['filters']    = array();
$GLOBALS['accounts']   = array();
$GLOBALS['mentors']    = array();
$GLOBALS['mentees']    = array();
$GLOBALS['posts']      = array();
$GLOBALS['pmeta']      = array();
$GLOBALS['next']       = 500;
$GLOBALS['clock']      = time();
$GLOBALS['mail']       = array();
$GLOBALS['mail_ok']    = true;
$GLOBALS['locales']    = array();
$GLOBALS['me']         = 0;
$GLOBALS['managers']   = array();
$GLOBALS['nonce_ok']   = true;
$GLOBALS['flash']      = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array(), $first_name = '';
	public function __construct( $id = 0, $name = '', $roles = array(), $email = '', $first = '' ) {
		$this->ID = $id; $this->display_name = $name; $this->roles = $roles; $this->user_email = $email; $this->first_name = $first;
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
function apply_filters( $t, $v ) { return $v; }
function add_action( $hook, $cb, $p = 10, $a = 1 ) { $GLOBALS['actions'][] = array( $hook, $cb ); }
function add_filter( $hook, $cb, $p = 10, $a = 1 ) { $GLOBALS['filters'][] = array( $hook, $cb ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_option( $k, $v, $dep = '', $auto = true ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function update_option( $k, $v, $auto = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_html_class( $s ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $s ); }
function esc_url_raw( $s ) { return preg_match( '#^https?://#', (string) $s ) ? (string) $s : ''; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function get_bloginfo( $k = 'name' ) { return 'WordPress Credits'; }
function get_locale() { return 'en_US'; }
function get_current_user_id() { return (int) $GLOBALS['me']; }
function wp_get_current_user() { return $GLOBALS['users'][ (int) $GLOBALS['me'] ] ?? new WP_User( 0 ); }
function user_can( $u, $c ) { return 'wpcpm_manage_program' === $c && in_array( (int) $u, $GLOBALS['managers'], true ); }
function current_user_can( $c ) { return user_can( get_current_user_id(), $c ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function wp_unslash( $v ) { return stripslashes_deep( $v ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_create_nonce( $a = -1 ) { return 'nonce-' . $a; }
function wp_referer_field( $echo = true ) { return ''; }
function check_admin_referer( $a = -1 ) { if ( ! $GLOBALS['nonce_ok'] ) { throw new Exception( 'bad nonce' ); } $GLOBALS['nonce_for'] = $a; return true; }
function wp_die( $m = '', $code = 0 ) { throw new Exception( 'denied ' . $code ); }
function wp_safe_redirect( $url ) { throw new Exception( 'redirect ' . $url ); }
function is_email( $s ) { return false !== filter_var( (string) $s, FILTER_VALIDATE_EMAIL ) ? $s : false; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? $GLOBALS['clock'] : $t ); }
function wp_timezone_string() { return 'UTC'; }
function wp_timezone() { return new DateTimeZone( 'UTC' ); }
function date_i18n( $f, $t = null ) { return gmdate( $f, null === $t ? time() : (int) $t ); }
function switch_to_locale( $l ) { $GLOBALS['locales'][] = $l; return true; }
function restore_previous_locale() { $GLOBALS['locales'][] = 'restored'; return true; }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function add_query_arg( ...$args ) {
	$pairs = is_array( $args[0] ) ? $args[0] : array( $args[0] => $args[1] );
	$url   = (string) ( is_array( $args[0] ) ? $args[1] : $args[2] );
	foreach ( $pairs as $k => $v ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
	}
	return $url;
}
// The posts store: ids from 500, the stand-in clock for a row's date, meta by id and key.
function wp_insert_post( array $a, $wp_error = false ) {
	if ( ! empty( $GLOBALS['insert_fails'] ) ) { return new WP_Error( 'db', 'no' ); }
	$id                      = $GLOBALS['next']++;
	$a                       = stripslashes_deep( $a );
	$a['ID']                 = $id;
	$a['post_date_gmt']      = gmdate( 'Y-m-d H:i:s', $GLOBALS['clock'] );
	$GLOBALS['posts'][ $id ] = new WP_Post( $a );
	return $id;
}
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = array( stripslashes_deep( $v ) ); return true; }
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
	if ( isset( $args['order'] ) && 'ASC' === $args['order'] && isset( $args['orderby'] ) && 'ID' === $args['orderby'] ) { sort( $ids ); }
	$skip = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
	foreach ( $ids as $id ) {
		$post = $GLOBALS['posts'][ $id ];
		if ( $post->post_type !== $args['post_type'] ) { continue; }
		if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] && $post->post_status !== $args['post_status'] ) { continue; }
		if ( isset( $args['author'] ) && (int) $post->post_author !== (int) $args['author'] ) { continue; }
		$ok = true;
		foreach ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			$ok = $ok && in_array( (string) $clause['value'], array_map( 'strval', get_post_meta( $id, $clause['key'] ) ), true );
		}
		if ( $ok && $skip > 0 ) { $skip--; continue; }
		if ( $ok ) { $out[] = ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? $id : $post; }
		if ( isset( $args['numberposts'] ) && $args['numberposts'] > 0 && count( $out ) >= $args['numberposts'] ) { break; }
	}
	return $out;
}
// What the mail layer hands WordPress: the stand-in mailer, which can be told to fail.
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['mail'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body, 'headers' => $headers, 'context' => WPCPM_Mail::take_context() );
	return (bool) $GLOBALS['mail_ok'];
}
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function get_user_by( $f, $v ) {
	if ( 'email' === $f ) {
		foreach ( $GLOBALS['users'] as $u ) {
			if ( '' !== $u->user_email && $u->user_email === $v ) { return $u; }
		}
		return false;
	}
	return $GLOBALS['users'][ (int) $v ] ?? false;
}
function wp_delete_post( $id, $force = false ) {
	$post = get_post( $id );
	if ( ! $post || ! $force ) { return false; }
	if ( array_key_exists( 'delete_result', $GLOBALS ) ) { return $GLOBALS['delete_result']; }
	unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] );
	return $post;
}
function register_post_type( $type, $args = array() ) { $GLOBALS['post_types'][ $type ] = $args; }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_VERSION', 'test' );

class WPCPM_Mentors_Sync {
	const META_RECORD_ID = 'wpcpm_mentor_record_id';
	public static function is_record_id( $s ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $s ); }
}
class WPCPM_Students_Sync {
	const META_RECORD_ID = 'wpcpm_student_record_id';
}
/** The two lookups `mentor_of()` rides on, answering from `$GLOBALS['accounts']` and `$GLOBALS['mentors']`. */
class WPCPM_Mentor_Calls {
	public static function student_record( $user_id ) { return $GLOBALS['accounts'][ (int) $user_id ] ?? ''; }
	public static function mentor_for_student( $user_id ) {
		$id = $GLOBALS['mentors'][ (int) $user_id ] ?? 0;
		return $id ? ( $GLOBALS['users'][ $id ] ?? null ) : null;
	}
}
class WPCPM_Flash {
	public static function set( $channel, $value, $user_id = 0 ) { $GLOBALS['flash'][ $channel ] = $value; }
	public static function take( $channel, $user_id = 0 ) { $v = $GLOBALS['flash'][ $channel ] ?? ''; unset( $GLOBALS['flash'][ $channel ] ); return $v; }
}
class WPCPM_Mentors_Dashboard {
	public static function get_mentees( $mentor_id ) { return $GLOBALS['mentees'][ (int) $mentor_id ] ?? array(); }
	public static function page_url() { return 'https://example.test/mentor-report-card/'; }
}
/** The dashboards the mail registry's examples link, and the sentence-case words it reads. */
class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student-report-card/'; }
}
/** The one limit the question box has. */
class WPCPM_Handbook_Assistant {
	const MAX_LENGTH = 400;
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
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

function number_format_i18n( $n ) { return number_format( (float) $n ); }
function wp_json_flat( $v ) { return json_encode( $v ); }
function code_of( $r ) { return is_wp_error( $r ) ? $r->get_error_code() : 'ok'; }
function switch_to( $on ) { $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array( 'handbook_to_mentors' => $on ); }

echo "\n=== The post type ===\n";

ck( 'its name is wpcpm_question', WPCPM_Student_Questions::POST_TYPE, 'wpcpm_question' );
WPCPM_Student_Questions::init();
ck( 'init() hooks the post type on init, and the one logged-in post of Mark as answered', $GLOBALS['actions'], array( array( 'init', array( 'WPCPM_Student_Questions', 'register_post_type' ) ), array( 'admin_post_wpcpm_question_answered', array( 'WPCPM_Student_Questions', 'handle_answered' ) ) ) );

WPCPM_Student_Questions::register_post_type();
$args = $GLOBALS['post_types']['wpcpm_question'] ?? array();
ck( 'it is never public, never in REST, never searched, never exported, with no rewrite and no query var',
	array( $args['public'] ?? null, $args['show_ui'] ?? null, $args['show_in_rest'] ?? null, $args['rewrite'] ?? null, $args['query_var'] ?? null, $args['exclude_from_search'] ?? null, $args['can_export'] ?? null ),
	array( false, false, false, false, false, true, false ) );
ck( 'it has capabilities nothing is granted, mapped, and supports the author alone',
	array( $args['capability_type'] ?? null, $args['map_meta_cap'] ?? null, $args['supports'] ?? null ),
	array( array( 'wpcpm_question', 'wpcpm_questions' ), true, array( 'author' ) ) );

echo "\n=== The switch ===\n";

ck( 'it ships off', WPCPM_Settings::defaults()['handbook_to_mentors'] ?? null, false );
ck( 'and reads off with nothing saved', WPCPM_Student_Questions::switched_on(), false );
switch_to( true );
ck( 'on when the setting is on', WPCPM_Student_Questions::switched_on(), true );
switch_to( false );
ck( 'off when the setting is off', WPCPM_Student_Questions::switched_on(), false );

echo "\n=== Who may send ===\n";

// A current student (10) with a mentor (30) whose list holds them, a past student (11), a student the
// mentor's list lost (12), a student with no mentor (13), a student with no record (14), a mentor
// account (30), an Administrator (50).
$GLOBALS['users'][10] = new WP_User( 10, 'Student A', array( WPCPM_Roles::ROLE_STUDENT ), 'student-a@example.test' );
$GLOBALS['users'][11] = new WP_User( 11, 'Student B', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][12] = new WP_User( 12, 'Student C', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][13] = new WP_User( 13, 'Student D', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][14] = new WP_User( 14, 'Student E', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][30] = new WP_User( 30, 'Mentor A', array( WPCPM_Roles::ROLE_MENTOR ), 'mentor-a@example.test', 'Mentor' );
$GLOBALS['users'][50] = new WP_User( 50, 'Administrator A', array( 'administrator' ) );
$GLOBALS['umeta'][30][ WPCPM_Mentors_Sync::META_RECORD_ID ] = 'recMEN00000000001';

$GLOBALS['accounts'] = array( 10 => 'recSTU00000000010', 11 => 'recSTU00000000011', 12 => 'recSTU00000000012', 13 => 'recSTU00000000013', 30 => 'recSTU00000000030', 50 => 'recSTU00000000050' );
$GLOBALS['mentors']  = array( 10 => 30, 11 => 30, 12 => 30, 14 => 30, 50 => 30 );
$GLOBALS['mentees']  = array(
	30 => array(
		array( 'record_id' => 'recSTU00000000010', 'name' => 'Student A', 'is_past' => false ),
		array( 'record_id' => 'recSTU00000000011', 'name' => 'Student B', 'is_past' => true ),
	),
);

switch_to( false );
ck( 'with the switch off, a student who could send is refused first', code_of( WPCPM_Student_Questions::mentor_of( 10 ) ), 'wpcpm_send_off' );
ck( 'and so is an Administrator: the switch comes before every other check', code_of( WPCPM_Student_Questions::mentor_of( 50 ) ), 'wpcpm_send_off' );

switch_to( true );
ck( 'a mentor account is not a student', code_of( WPCPM_Student_Questions::mentor_of( 30 ) ), 'wpcpm_not_student' );
ck( 'an Administrator is not a student', code_of( WPCPM_Student_Questions::mentor_of( 50 ) ), 'wpcpm_not_student' );
ck( 'nobody is not a student', code_of( WPCPM_Student_Questions::mentor_of( 0 ) ), 'wpcpm_not_student' );
ck( 'a student with no Students record is refused', code_of( WPCPM_Student_Questions::mentor_of( 14 ) ), 'wpcpm_no_record' );
ck( 'a student with no mentor is refused', code_of( WPCPM_Student_Questions::mentor_of( 13 ) ), 'wpcpm_no_mentor' );
ck( 'a student the mentor\'s list lost is refused: no email to a mentor who no longer holds them', code_of( WPCPM_Student_Questions::mentor_of( 12 ) ), 'wpcpm_mentor_gone' );
ck( 'a past student is refused', code_of( WPCPM_Student_Questions::mentor_of( 11 ) ), 'wpcpm_past' );

$got = WPCPM_Student_Questions::mentor_of( 10 );
ck( 'a current student with a mentor who holds them is let through', is_wp_error( $got ), false );
ck( 'with exactly the five keys', is_array( $got ) ? array_keys( $got ) : null, array( 'student', 'mentor', 'student_record', 'mentor_record', 'row' ) );
ck( 'the student and the mentor are the accounts',
	is_array( $got ) ? array( $got['student']->ID, $got['mentor']->ID ) : null, array( 10, 30 ) );
ck( 'the records are the student\'s and the mentor\'s, the mentor\'s from the account\'s link',
	is_array( $got ) ? array( $got['student_record'], $got['mentor_record'] ) : null, array( 'recSTU00000000010', 'recMEN00000000001' ) );
ck( 'and the row is the mentor\'s list\'s own', is_array( $got ) ? $got['row']['name'] : null, 'Student A' );

echo "\n=== The email is in the catalog and the registry ===\n";

$entry = WPCPM_Mail_Catalog::get( 'student-question-mentor' );
ck( 'the catalog holds student-question-mentor in the module need-help, for a mentor, named Need help?\'s question',
	$entry ? array( $entry['module'], $entry['audience'] ) : null, array( 'need-help', 'mentor' ) );
ck( 'the module is labelled Need help?', WPCPM_Mail_Catalog::modules()['need-help'] ?? null, 'Need help?' );
ck( 'its area file is need-help.php', WPCPM_Mail_Templates::AREAS['need-help'] ?? null, 'need-help' );
$tpl = WPCPM_Mail_Templates::get( 'student-question-mentor' );
ck( 'the registry holds its template', is_array( $tpl ), true );
ck( 'with the six placeholders', is_array( $tpl ) ? array_keys( $tpl['placeholders'] ) : null,
	array( 'student_first_name', 'mentor_first_name', 'question', 'student_note', 'links', 'student_card_url' ) );

echo "\n=== Sending: the refusals ===\n";

$GLOBALS['accounts'][10] = 'recSTU00000000010';
$posts_before            = count( $GLOBALS['posts'] );
switch_to( false );
ck( 'with the switch off, send() returns mentor_of\'s refusal unchanged', code_of( WPCPM_Student_Questions::send( 10, 'How do I start the final project?' ) ), 'wpcpm_send_off' );
switch_to( true );
ck( 'an account that is not a student', code_of( WPCPM_Student_Questions::send( 30, 'How do I start the final project?' ) ), 'wpcpm_not_student' );
ck( 'a student with no Students record', code_of( WPCPM_Student_Questions::send( 14, 'How do I start the final project?' ) ), 'wpcpm_no_record' );
ck( 'a student with no mentor', code_of( WPCPM_Student_Questions::send( 13, 'How do I start the final project?' ) ), 'wpcpm_no_mentor' );
ck( 'a student the mentor\'s list lost', code_of( WPCPM_Student_Questions::send( 12, 'How do I start the final project?' ) ), 'wpcpm_mentor_gone' );
ck( 'a past student', code_of( WPCPM_Student_Questions::send( 11, 'How do I start the final project?' ) ), 'wpcpm_past' );
ck( 'a question of fewer than three characters', code_of( WPCPM_Student_Questions::send( 10, ' a ' ) ), 'wpcpm_question_short' );
ck( 'and one that is only a tag', code_of( WPCPM_Student_Questions::send( 10, '<b>a</b>' ) ), 'wpcpm_question_short' );
ck( 'no refusal saves a question, sends an email or claims a place',
	array( count( $GLOBALS['posts'] ) - $posts_before, count( $GLOBALS['mail'] ), WPCPM_Ceiling::count( WPCPM_Ceiling::key( 'question_day_10' ), DAY_IN_SECONDS ) ), array( 0, 0, 0 ) );
ck( 'the numbers the plan names', array( WPCPM_Student_Questions::PER_DAY, WPCPM_Student_Questions::NOTE_MAX ), array( 3, 600 ) );

echo "\n=== Sending: the record ===\n";

$links = array(
	'https://example.test/guides/first/',
	'https://example.test/guides/second/',
	'https://evil.example.org/phish/',
	'javascript:alert(1)',
	'https://make.wordpress.org/community/handbook/education/credits/third/',
	'https://make.wordpress.org/community/handbook/other/',
	'https://example.test/guides/fourth/',
	'https://example.test/guides/fifth/',
	'https://example.test/guides/sixth/',
);
$sent = WPCPM_Student_Questions::send( 10, "  How do I start\nthe final project?  ", '  I read the guide twice.  ', $links );
ck( 'a question is sent', is_array( $sent ) ? $sent['state'] : code_of( $sent ), 'sent' );
ck( 'the answer has six keys', is_array( $sent ) ? array_keys( $sent ) : null, array( 'question_id', 'student_record_id', 'mentor_record_id', 'state', 'emailed', 'sent_at' ) );
ck( 'with the records, the email sent and the time', is_array( $sent ) ? array( $sent['student_record_id'], $sent['mentor_record_id'], $sent['emailed'], is_int( $sent['sent_at'] ) && $sent['sent_at'] >= $GLOBALS['clock'] ) : null,
	array( 'recSTU00000000010', 'recMEN00000000001', true, true ) );
$post = $sent ? ( $GLOBALS['posts'][ $sent['question_id'] ] ?? null ) : null;
ck( 'the post is a private wpcpm_question of the student, its words the question as typed', $post ? array( $post->post_type, $post->post_status, $post->post_author, $post->post_content ) : null,
	array( 'wpcpm_question', 'private', 10, "How do I start\nthe final project?" ) );
ck( 'titled Question - and the date', $post ? $post->post_title : null, 'Question - ' . gmdate( 'Y-m-d H:i', $GLOBALS['clock'] ) );
$meta = function ( $k ) use ( $sent ) { return get_post_meta( $sent['question_id'], $k, true ); };
ck( 'its student and mentor', array( $meta( '_wpcpm_question_student' ), $meta( '_wpcpm_question_mentor' ), $meta( '_wpcpm_question_mentor_record' ) ), array( 'recSTU00000000010', 30, 'recMEN00000000001' ) );
ck( 'the added line, cut and clean, and the state waiting', array( $meta( '_wpcpm_question_note' ), $meta( '_wpcpm_question_state' ) ), array( 'I read the guide twice.', 'waiting' ) );
ck( 'the links: this site\'s and the handbook\'s only, in order, five at most', $meta( '_wpcpm_question_links' ),
	array( 'https://example.test/guides/first/', 'https://example.test/guides/second/', 'https://make.wordpress.org/community/handbook/education/credits/third/', 'https://example.test/guides/fourth/', 'https://example.test/guides/fifth/' ) );
ck( 'it is answered by nobody yet', array( $meta( '_wpcpm_question_answered_at' ), $meta( '_wpcpm_question_answered_by' ) ), array( '', '' ) );

$cut = WPCPM_Student_Questions::send( 10, str_repeat( 'é', 450 ), str_repeat( 'ü', 700 ) );
ck( 'a long question is cut to 400 and its line to 600, never refused', array( mb_strlen( $GLOBALS['posts'][ $cut['question_id'] ]->post_content ), mb_strlen( get_post_meta( $cut['question_id'], '_wpcpm_question_note', true ) ) ), array( 400, 600 ) );
ck( 'clean_question() is the cleaning send() keeps a question by, which the AI Mentor\'s preview shows: what it gives is what send() kept',
	is_callable( array( 'WPCPM_Student_Questions', 'clean_question' ) ) ? array( WPCPM_Student_Questions::clean_question( "  How do I start\nthe final project?  " ) === $post->post_content, WPCPM_Student_Questions::clean_question( str_repeat( 'é', 450 ) ) === $GLOBALS['posts'][ $cut['question_id'] ]->post_content, WPCPM_Student_Questions::clean_question( ' <b>a</b> ' ) ) : null,
	array( true, true, 'a' ) );

echo "\n=== Sending: the email ===\n";

$mail = $GLOBALS['mail'][0] ?? array();
ck( 'it goes to the mentor\'s address, under the catalog id', array( $mail['to'] ?? null, $mail['context'] ?? null ), array( 'mentor-a@example.test', 'student-question-mentor' ) );
ck( 'with the student\'s address as Reply-To', array_values( array_filter( (array) ( $mail['headers'] ?? array() ), function ( $h ) { return 0 === strpos( $h, 'Reply-To:' ); } ) ), array( 'Reply-To: Student A <student-a@example.test>' ) );
ck( 'the subject is the spec\'s, no site name before it', $mail['subject'] ?? null, 'Student A sent you a question from Need help?' );
$want_body = "Hi Mentor,\n\nStudent A asked Need help? a question it could not fully answer:\n\nHow do I start\nthe final project?\n\nI read the guide twice.\n\nNeed help? showed them:\n"
	. "https://example.test/guides/first/\nhttps://example.test/guides/second/\nhttps://make.wordpress.org/community/handbook/education/credits/third/\nhttps://example.test/guides/fourth/\nhttps://example.test/guides/fifth/\n\n"
	. "Reply to this email to answer. Your reply goes to Student A.\n\n"
	. "The question is also on your Mentor Report Card: https://example.test/mentor-report-card/?wpcpm_student=recSTU00000000010#wpcpm-student-recSTU00000000010";
ck( 'the body is the spec\'s words, filled: the mentor\'s first name, the student\'s display name as the fallback, the question, the line, the links, the card', substr( (string) ( $mail['body'] ?? '' ), 0, strlen( $want_body ) ), $want_body );
$GLOBALS['mail_dump'] = $mail['body'] ?? '';

// A day later, so the day's counter starts clean, and a student with neither a note nor links.
$GLOBALS['clock'] += 2 * DAY_IN_SECONDS;
$GLOBALS['mail'] = array();
$bare = WPCPM_Student_Questions::send( 10, 'Where is the checklist?' );
$mail = $GLOBALS['mail'][0] ?? array();
$want_bare = "Hi Mentor,\n\nStudent A asked Need help? a question it could not fully answer:\n\nWhere is the checklist?\n\nReply to this email to answer. Your reply goes to Student A.\n\n"
	. "The question is also on your Mentor Report Card: https://example.test/mentor-report-card/?wpcpm_student=recSTU00000000010#wpcpm-student-recSTU00000000010";
ck( 'with no line and no links, their lines and "Need help? showed them:" are gone', substr( (string) ( $mail['body'] ?? '' ), 0, strlen( $want_bare ) ), $want_bare );
ck( 'and the record holds an empty line and no links', array( get_post_meta( $bare['question_id'], '_wpcpm_question_note', true ), get_post_meta( $bare['question_id'], '_wpcpm_question_links', true ) ), array( '', array() ) );

echo "\n=== A failed email ===\n";

$GLOBALS['mail_ok'] = false;
$failed             = WPCPM_Student_Questions::send( 10, 'What if the email fails?' );
$GLOBALS['mail_ok'] = true;
ck( 'the answer is sent with emailed false', is_array( $failed ) ? array( $failed['state'], $failed['emailed'] ) : code_of( $failed ), array( 'sent', false ) );
ck( 'and the question is saved, waiting', is_array( $failed ) ? array( $GLOBALS['posts'][ $failed['question_id'] ]->post_status, get_post_meta( $failed['question_id'], '_wpcpm_question_state', true ) ) : null, array( 'private', 'waiting' ) );

echo "\n=== The same question twice ===\n";

$rows  = count( $GLOBALS['posts'] );
$sends = count( $GLOBALS['mail'] );
$key   = WPCPM_Ceiling::key( 'question_day_10' );
$spent = WPCPM_Ceiling::count( $key, DAY_IN_SECONDS );
$dupe  = WPCPM_Student_Questions::send( 10, 'Where is the checklist?' );
ck( 'the same words within 24 hours are a duplicate', is_array( $dupe ) ? $dupe['state'] : code_of( $dupe ), 'duplicate' );
ck( 'a duplicate points at the first, saves nothing, emails nobody and spends no place of the day', array( $dupe['question_id'], $dupe['emailed'], count( $GLOBALS['posts'] ), count( $GLOBALS['mail'] ), WPCPM_Ceiling::count( $key, DAY_IN_SECONDS ) ),
	array( $bare['question_id'], false, $rows, $sends, $spent ) );
$GLOBALS['posts'][ $bare['question_id'] ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS - 3600 );
$again = WPCPM_Student_Questions::send( 10, 'Where is the checklist?' );
ck( 'after 24 hours the same words are sent again', is_array( $again ) ? $again['state'] : code_of( $again ), 'sent' );

echo "\n=== Three a day ===\n";

$GLOBALS['clock'] += 3 * DAY_IN_SECONDS;
$GLOBALS['mail']   = array();
$states            = array();
for ( $i = 1; $i <= 3; $i++ ) {
	$r        = WPCPM_Student_Questions::send( 10, 'Question number ' . $i . ' of the day.' );
	$states[] = is_array( $r ) ? $r['state'] : code_of( $r );
}
ck( 'three questions of a day are sent', $states, array( 'sent', 'sent', 'sent' ) );
$rows   = count( $GLOBALS['posts'] );
$fourth = WPCPM_Student_Questions::send( 10, 'Question number four of the day.' );
ck( 'the fourth answers limit, as an answer and not an error', is_array( $fourth ) ? $fourth['state'] : code_of( $fourth ), 'limit' );
ck( 'it saves nothing, emails nobody and claims nothing', array( $fourth['question_id'], $fourth['emailed'], count( $GLOBALS['posts'] ), count( $GLOBALS['mail'] ), WPCPM_Ceiling::count( WPCPM_Ceiling::key( 'question_day_10' ), DAY_IN_SECONDS ) ), array( 0, false, $rows, 3, 3 ) );
$GLOBALS['clock'] += DAY_IN_SECONDS;
$tomorrow = WPCPM_Student_Questions::send( 10, 'Question number four, the next day.' );
ck( 'the next UTC day sends again', is_array( $tomorrow ) ? $tomorrow['state'] : code_of( $tomorrow ), 'sent' );

echo "\n=== A failed save gives the place back ===\n";

$GLOBALS['clock'] += DAY_IN_SECONDS;
$GLOBALS['insert_fails'] = true;
$lost                    = WPCPM_Student_Questions::send( 10, 'A question the database loses.' );
$GLOBALS['insert_fails'] = false;
ck( 'the error is wp_insert_post\'s', code_of( $lost ), 'db' );
ck( 'and the place claimed is released', WPCPM_Ceiling::count( WPCPM_Ceiling::key( 'question_day_10' ), DAY_IN_SECONDS ), 0 );

echo "\n=== The questions of a student ===\n";

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['clock'] = time();
$GLOBALS['opts']  = array( WPCPM_Settings::OPT_NAME => array( 'handbook_to_mentors' => true ) );
$mk = function ( $text, $state, $age_days, $student = 'recSTU00000000010', $status = 'private', $answered_days = null ) {
	$id = wp_insert_post( array( 'post_type' => 'wpcpm_question', 'post_status' => $status, 'post_author' => 10, 'post_content' => $text ) );
	$GLOBALS['posts'][ $id ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', $GLOBALS['clock'] - (int) ( $age_days * DAY_IN_SECONDS ) );
	update_post_meta( $id, '_wpcpm_question_student', $student );
	update_post_meta( $id, '_wpcpm_question_state', $state );
	if ( null !== $answered_days ) {
		update_post_meta( $id, '_wpcpm_question_answered_at', $GLOBALS['clock'] - (int) ( $answered_days * DAY_IN_SECONDS ) );
	}
	return $id;
};
// Seeded oldest first: the stand-in store lists by id, newest first, where the real one lists by date.
$a_old    = $mk( 'Answered forty days ago', 'answered', 60, 'recSTU00000000010', 'private', 40 );
$a_recent = $mk( 'Answered ten days ago', 'answered', 50, 'recSTU00000000010', 'private', 10 );
$w_old    = $mk( 'Waiting, forty days old', 'waiting', 40 );
$w_new    = $mk( 'Waiting, today', 'waiting', 0.1 );
$other    = $mk( 'Another student\'s', 'waiting', 1, 'recSTU00000000011' );
$draft    = $mk( 'Not private', 'waiting', 1, 'recSTU00000000010', 'publish' );
$list     = WPCPM_Student_Questions::for_student( 'recSTU00000000010' );
ck( 'for_student() holds every waiting question and the answered ones of the last 30 days, newest first, one student\'s, private only',
	array_map( function ( $p ) { return $p->ID; }, $list ), array( $w_new, $w_old, $a_recent ) );
ck( 'every item is a post', count( array_filter( $list, function ( $p ) { return $p instanceof WP_Post; } ) ), 3 );
ck( 'a student with none has an empty list', WPCPM_Student_Questions::for_student( 'recSTU00000000099' ), array() );


echo "\n=== The questions on the Mentor Report Card ===\n";

// Student A (record ...10) is on Mentor A's (30) list; Mentor B (31) has another list; an Administrator (50).
$GLOBALS['users'][31] = new WP_User( 31, 'Mentor B', array( WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['managers']  = array( 50 );
$GLOBALS['mentees'][31] = array( array( 'record_id' => 'recSTU00000000077', 'name' => 'Student F', 'is_past' => false ) );
$GLOBALS['opts']['date_format'] = 'Y-m-d';
$GLOBALS['opts']['time_format'] = 'H:i';
$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['clock'] = time();
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array( 'handbook_to_mentors' => true );

ck( 'no question: no tag count and no list', array( WPCPM_Student_Questions::waiting_count( 'recSTU00000000010' ), $GLOBALS['me'] ), array( 0, 0 ) );
$GLOBALS['me'] = 30;
ck( 'no question: nothing is drawn', WPCPM_Student_Questions::render_for( 'recSTU00000000010', 30 ), '' );

$mk2 = function ( $text, $state, $age_days, $note = '', $links = array(), $answered_days = null ) use ( $mk ) {
	$id = $mk( $text, $state, $age_days, 'recSTU00000000010', 'private', $answered_days );
	update_post_meta( $id, '_wpcpm_question_note', $note );
	update_post_meta( $id, '_wpcpm_question_links', $links );
	return $id;
};
$q_old   = $mk2( 'Answered forty days ago', 'answered', 60, '', array(), 40 );
$q_done  = $mk2( 'How do I log hours?', 'answered', 5, '', array(), 2 );
// Seeded oldest first, as above.
$q_wait2 = $mk2( 'An older waiting one', 'waiting', 3 );
$q_wait  = $mk2( 'Which course <b>first</b>?', 'waiting', 0.5, 'I am on a phone', array( 'https://example.test/guide/', 'https://make.wordpress.org/community/handbook/education/credits/start/' ) );

ck( 'waiting_count() counts the waiting ones of the student, not the answered', WPCPM_Student_Questions::waiting_count( 'recSTU00000000010' ), 2 );
ck( 'and another student has none', WPCPM_Student_Questions::waiting_count( 'recSTU00000000011' ), 0 );

$html = WPCPM_Student_Questions::render_for( 'recSTU00000000010', 30 );
ck( 'the list has its heading, an h4', false !== strpos( $html, '<h4 class="wpcpm-questions__title">Questions sent from Need help?</h4>' ) );
ck( 'in an ol.wpcpm-questions of li.wpcpm-question', 1 === substr_count( $html, '<ol class="wpcpm-questions">' ) && 3 === substr_count( $html, '<li class="wpcpm-question' ) );
ck( 'the answer of forty days ago is gone', false === strpos( $html, 'forty days' ) );
ck( 'newest first: waiting, older waiting, answered', strpos( $html, 'Which course' ) < strpos( $html, 'An older waiting' ) && strpos( $html, 'An older waiting' ) < strpos( $html, 'How do I log hours?' ) );
ck( 'the question is escaped', false !== strpos( $html, 'Which course &lt;b&gt;first&lt;/b&gt;?' ) && false === strpos( $html, '<b>first' ) );
ck( 'the student\'s added line is shown', false !== strpos( $html, 'I am on a phone' ) );
ck( 'each link is a plain link opening in a new tab', 2 === substr_count( $html, 'target="_blank" rel="noopener noreferrer"' ) && false !== strpos( $html, '<a href="https://example.test/guide/"' ) );
ck( 'the date sits in a time element', 1 <= preg_match( '#<time datetime="[^"]+">\d{4}-\d{2}-\d{2} \d{2}:\d{2}</time>#', $html ) );
ck( 'a waiting question has Mark as answered: one small secondary button in a once-only form, the nonce of its ID',
	2 === substr_count( $html, 'Mark as answered' ) && 2 === substr_count( $html, 'data-wpcpm-once' ) && 2 === substr_count( $html, 'wpcpm-button wpcpm-button--secondary' )
	&& false !== strpos( $html, 'value="nonce-wpcpm_question_answered_' . $q_wait . '"' ) && false !== strpos( $html, 'name="action" value="wpcpm_question_answered"' ) && false !== strpos( $html, 'name="question" value="' . $q_wait . '"' ) );
ck( 'an answered question is marked and greyed, with "Answered" and its date and no button', 1 === substr_count( $html, 'wpcpm-question--answered' ) && 1 === substr_count( $html, 'Answered ' ) );
ck( 'Ruling 10: nothing carries wpcpm-note', false === strpos( $html, 'wpcpm-note' ) );
ck( 'the mentor\'s own page names no mentor field', false === strpos( $html, 'name="mentor"' ) );

$GLOBALS['me'] = 50;
$admin = WPCPM_Student_Questions::render_for( 'recSTU00000000010', 30 );
ck( 'an Administrator reads it too, and the form names the mentor whose page it is, to come back to', false !== strpos( $admin, 'Which course' ) && false !== strpos( $admin, 'name="mentor" value="30"' ) );
$GLOBALS['me'] = 31;
ck( 'another mentor reads nothing', WPCPM_Student_Questions::render_for( 'recSTU00000000010', 31 ), '' );
$GLOBALS['me'] = 10;
ck( 'the student reads nothing', WPCPM_Student_Questions::render_for( 'recSTU00000000010', 10 ), '' );
$GLOBALS['me'] = 0;
ck( 'nobody signed in reads nothing', WPCPM_Student_Questions::render_for( 'recSTU00000000010', 0 ), '' );

echo "\n=== Mark as answered ===\n";

$press = function ( $me, $id, $nonce_ok = true, $extra = array() ) {
	$GLOBALS['me']       = $me;
	$GLOBALS['nonce_ok'] = $nonce_ok;
	$_POST               = array_merge( array( 'question' => (string) $id ), $extra );
	try {
		WPCPM_Student_Questions::handle_answered();
		return 'returned';
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
};
$state = function ( $id ) { return array( get_post_meta( $id, '_wpcpm_question_state', true ), get_post_meta( $id, '_wpcpm_question_answered_by', true ) ); };

ck( 'another mentor is refused with 403 and nothing changes', array( $press( 31, $q_wait ), $state( $q_wait ) ), array( 'denied 403', array( 'waiting', '' ) ) );
ck( 'the student is refused with 403', array( $press( 10, $q_wait ), $state( $q_wait )[0] ), array( 'denied 403', 'waiting' ) );
ck( 'a bad nonce stops, and nothing changes', array( $press( 30, $q_wait, false ), $state( $q_wait )[0] ), array( 'bad nonce', 'waiting' ) );
ck( 'a question that does not exist is refused', $press( 30, 99999 ), 'denied 403' );
ck( 'a post of another type is refused', ( function () use ( $press ) { $id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'private' ) ); return $press( 30, $id ); } )(), 'denied 403' );

$GLOBALS['flash'] = array();
$GLOBALS['clock'] = time();
$res = $press( 30, $q_wait );
ck( 'the nonce checked is the one of this question', $GLOBALS['nonce_for'], 'wpcpm_question_answered_' . $q_wait );
ck( 'the student\'s mentor marks it: answered, by them, now',
	array( $state( $q_wait ), abs( (int) get_post_meta( $q_wait, '_wpcpm_question_answered_at', true ) - time() ) < 5 ), array( array( 'answered', 30 ), true ) );
ck( 'and goes back to the card with the student reopened, to its anchor, with a short flash',
	array( $res, $GLOBALS['flash'] ), array( 'redirect https://example.test/mentor-report-card/?wpcpm_student=recSTU00000000010#wpcpm-student-recSTU00000000010', array( 'question' => 'answered' ) ) );
ck( 'the waiting count drops', WPCPM_Student_Questions::waiting_count( 'recSTU00000000010' ), 1 );

$at = get_post_meta( $q_wait, '_wpcpm_question_answered_at', true );
$GLOBALS['clock'] += 100;
ck( 'a question already answered is left as it is, and the redirect still happens',
	array( 0 === strpos( $press( 50, $q_wait ), 'redirect ' ), get_post_meta( $q_wait, '_wpcpm_question_answered_at', true ), get_post_meta( $q_wait, '_wpcpm_question_answered_by', true ) ), array( true, $at, 30 ) );

$adm = $press( 50, $q_wait2, true, array( 'mentor' => '30' ) );
ck( 'an Administrator marks one, and returns to the mentor they were inspecting', array( $state( $q_wait2 ), $adm ),
	array( array( 'answered', 50 ), 'redirect https://example.test/mentor-report-card/?wpcpm_student=recSTU00000000010&wpcpm_mentor=30#wpcpm-student-recSTU00000000010' ) );
ck( 'with none waiting, the tag count is 0', WPCPM_Student_Questions::waiting_count( 'recSTU00000000010' ), 0 );

$GLOBALS['me'] = 30;
$GLOBALS['flash'] = array( 'question' => 'answered' );
$_GET['wpcpm_student'] = 'recSTU00000000010';
$after = WPCPM_Student_Questions::render_for( 'recSTU00000000010', 30 );
ck( 'the opened card shows the flash once, and the answered ones greyed', array( false !== strpos( $after, 'Marked as answered.' ), 3 === substr_count( $after, 'wpcpm-question--answered' ) ), array( true, true ) );
ck( 'and it is gone the next time', false === strpos( WPCPM_Student_Questions::render_for( 'recSTU00000000010', 30 ), 'Marked as answered.' ) );

echo "\n=== Privacy: export, erase, uninstall ===\n";

ck( 'the exporter is registered under wpcpm-student-questions, with its friendly name',
	WPCPM_Student_Questions::register_exporter( array() ),
	array( 'wpcpm-student-questions' => array( 'exporter_friendly_name' => 'WPCredits questions to a mentor', 'callback' => array( 'WPCPM_Student_Questions', 'export' ) ) ) );
ck( 'and keeps the exporters already there',
	array_keys( WPCPM_Student_Questions::register_exporter( array( 'other' => array() ) ) ), array( 'other', 'wpcpm-student-questions' ) );
ck( 'the eraser is registered under the same ID, with its friendly name',
	WPCPM_Student_Questions::register_eraser( array() ),
	array( 'wpcpm-student-questions' => array( 'eraser_friendly_name' => 'WPCredits questions to a mentor', 'callback' => array( 'WPCPM_Student_Questions', 'erase' ) ) ) );
ck( 'init() hooks both privacy filters',
	array_values( array_filter( $GLOBALS['filters'], function ( $f ) { return 0 === strpos( $f[0], 'wp_privacy_personal_data_' ); } ) ),
	array( array( 'wp_privacy_personal_data_exporters', array( 'WPCPM_Student_Questions', 'register_exporter' ) ), array( 'wp_privacy_personal_data_erasers', array( 'WPCPM_Student_Questions', 'register_eraser' ) ) ) );

$GLOBALS['posts']  = array();
$GLOBALS['pmeta']  = array();
$GLOBALS['next']   = 900;
$GLOBALS['clock']  = gmmktime( 9, 30, 0, 11, 18, 2026 );
$GLOBALS['users'][10]->user_email = 'student-a@example.test';
$GLOBALS['users'][12]->user_email = 'student-c@example.test';

$mk = function ( $author, $type, $content, $meta = array() ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'private', 'post_author' => $author, 'post_content' => $content, 'post_title' => 'x' ) );
	foreach ( $meta as $k => $v ) { update_post_meta( $id, $k, $v ); }
	return $id;
};
$qa = $mk( 10, 'wpcpm_question', 'How do I start the final project?', array( '_wpcpm_question_note' => 'I am stuck on week 3.', '_wpcpm_question_links' => array( 'https://example.test/guide/', 'https://example.test/two/' ), '_wpcpm_question_state' => 'waiting', '_wpcpm_question_mentor' => 30, '_wpcpm_question_mentor_record' => 'recMEN00000000001' ) );
$qb = $mk( 10, 'wpcpm_question', 'Where do I find the rubric?', array( '_wpcpm_question_state' => 'answered', '_wpcpm_question_mentor' => 30 ) );
$qc = $mk( 12, 'wpcpm_question', 'A question of another student', array( '_wpcpm_question_state' => 'waiting' ) );
$na = $mk( 10, 'wpcpm_mentor_note', 'Need help? showed me nothing about deadlines.', array( '_wpcpm_note_source' => 'need_help' ) );
$nm = $mk( 30, 'wpcpm_mentor_note', 'Mentor own note about Student A', array( '_wpcpm_note_student' => 'recSTU00000000010' ) );
$nx = $mk( 10, 'wpcpm_mentor_note', 'A post by the student account without the source', array() );
$nc = $mk( 12, 'wpcpm_mentor_note', 'Need help? note of another student', array( '_wpcpm_note_source' => 'need_help' ) );

$out = WPCPM_Student_Questions::export( 'student-a@example.test', 1 );
ck( 'the export is one item per question and per Need help? note, done on one page', array( count( $out['data'] ), $out['done'] ), array( 3, true ) );
$groups = array();
foreach ( $out['data'] as $item ) { $groups[ $item['group_id'] ][] = $item['item_id']; }
ck( 'in two groups, with the questions first, each item named by its post',
	$groups, array( 'wpcpm-student-questions' => array( 'wpcpm-student-question-' . $qa, 'wpcpm-student-question-' . $qb ), 'wpcpm-student-need-help-notes' => array( 'wpcpm-student-need-help-note-' . $na ) ) );
ck( 'the group labels are the spec\'s', array( $out['data'][0]['group_label'], $out['data'][2]['group_label'] ), array( 'Questions sent from Need help?', 'Need help? notes' ) );
$first = null;
foreach ( $out['data'] as $item ) { if ( 'wpcpm-student-question-' . $qa === $item['item_id'] ) { $first = $item['data']; } }
ck( 'a question lists the date, the question, the added line, the links and its state',
	$first,
	array(
		array( 'name' => 'Date', 'value' => '2026-11-18 09:30:00 UTC' ),
		array( 'name' => 'Question', 'value' => 'How do I start the final project?' ),
		array( 'name' => 'Added line', 'value' => 'I am stuck on week 3.' ),
		array( 'name' => 'Links', 'value' => "https://example.test/guide/\nhttps://example.test/two/" ),
		array( 'name' => 'State', 'value' => 'Waiting' ),
	) );
$flat = wp_json_flat( $out );
ck( 'no mentor name and no mentor email in it', array( false !== strpos( $flat, 'Mentor A' ), false !== strpos( $flat, 'mentor-a@example.test' ), false !== strpos( $flat, 'recMEN' ) ), array( false, false, false ) );
ck( 'a mentor\'s own note, an unmarked post of the account and another student\'s rows are not in it',
	array( false !== strpos( $flat, 'Mentor own note' ), false !== strpos( $flat, 'without the source' ), false !== strpos( $flat, 'another student' ) ), array( false, false, false ) );
// kses keeps an ampersand as &amp; in a stored question and note: the export reads the words as typed. The
// added line is stored as typed, so it is left alone.
$GLOBALS['posts']  = array();
$GLOBALS['pmeta']  = array();
$qamp = $mk( 10, 'wpcpm_question', 'Is R&amp;D part of the &quot;final&quot; project?', array( '_wpcpm_question_note' => 'Tom &amp; Jerry', '_wpcpm_question_state' => 'waiting' ) );
$namp = $mk( 10, 'wpcpm_mentor_note', 'Need help? could not tell me about Q&amp;A.', array( '_wpcpm_note_source' => 'need_help' ) );
$amp  = WPCPM_Student_Questions::export( 'student-a@example.test', 1 );
$amp_values = array();
foreach ( $amp['data'] as $item ) { foreach ( $item['data'] as $row ) { if ( in_array( $row['name'], array( 'Question', 'Added line', 'Note' ), true ) ) { $amp_values[ $row['name'] ] = $row['value']; } } }
ck( 'a question and a Need help? note hold the ampersand and quotes as typed in the export; the added line is as stored',
	$amp_values, array( 'Question' => 'Is R&D part of the "final" project?', 'Added line' => 'Tom &amp; Jerry', 'Note' => 'Need help? could not tell me about Q&A.' ) );
$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$qa = $mk( 10, 'wpcpm_question', 'How do I start the final project?', array( '_wpcpm_question_note' => 'I am stuck on week 3.', '_wpcpm_question_links' => array( 'https://example.test/guide/', 'https://example.test/two/' ), '_wpcpm_question_state' => 'waiting', '_wpcpm_question_mentor' => 30, '_wpcpm_question_mentor_record' => 'recMEN00000000001' ) );
$qb = $mk( 10, 'wpcpm_question', 'Where do I find the rubric?', array( '_wpcpm_question_state' => 'answered', '_wpcpm_question_mentor' => 30 ) );
$qc = $mk( 12, 'wpcpm_question', 'A question of another student', array( '_wpcpm_question_state' => 'waiting' ) );
$na = $mk( 10, 'wpcpm_mentor_note', 'Need help? showed me nothing about deadlines.', array( '_wpcpm_note_source' => 'need_help' ) );
$nm = $mk( 30, 'wpcpm_mentor_note', 'Mentor own note about Student A', array( '_wpcpm_note_student' => 'recSTU00000000010' ) );
$nx = $mk( 10, 'wpcpm_mentor_note', 'A post by the student account without the source', array() );
$nc = $mk( 12, 'wpcpm_mentor_note', 'Need help? note of another student', array( '_wpcpm_note_source' => 'need_help' ) );
$none = WPCPM_Student_Questions::export( 'nobody@example.test', 1 );
ck( 'an address with no account exports nothing, and is done', $none, array( 'data' => array(), 'done' => true ) );
ck( 'an account with nothing sent exports nothing', WPCPM_Student_Questions::export( 'student-c@example.test', 2 )['data'], array() );

// Paging: 50 at a time.
for ( $i = 0; $i < 52; $i++ ) { $mk( 12, 'wpcpm_question', 'Bulk question ' . $i, array( '_wpcpm_question_state' => 'waiting' ) ); }
$p1 = WPCPM_Student_Questions::export( 'student-c@example.test', 1 );
$p2 = WPCPM_Student_Questions::export( 'student-c@example.test', 2 );
ck( '53 questions and a note page as 50 questions and the note, then the other three, done only on the last', array( count( $p1['data'] ), $p1['done'], count( $p2['data'] ), $p2['done'] ), array( 51, false, 3, true ) );

$er = WPCPM_Student_Questions::erase( 'student-a@example.test', 1 );
ck( 'the erasure answers items removed, none retained, no messages, done',
	$er, array( 'items_removed' => true, 'items_retained' => false, 'messages' => array(), 'done' => true ) );
ck( 'it deletes the student\'s questions and Need help? notes, meta too',
	array( null === get_post( $qa ), null === get_post( $qb ), null === get_post( $na ), isset( $GLOBALS['pmeta'][ $qa ] ), isset( $GLOBALS['pmeta'][ $na ] ) ), array( true, true, true, false, false ) );
ck( 'and leaves a mentor\'s note about them, the unmarked post, and another student\'s rows',
	array( null !== get_post( $nm ), null !== get_post( $nx ), null !== get_post( $qc ), null !== get_post( $nc ) ), array( true, true, true, true ) );
ck( 'a second erasure has nothing to remove',
	WPCPM_Student_Questions::erase( 'student-a@example.test', 1 ), array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ) );
ck( 'an address with no account erases nothing, and is done',
	WPCPM_Student_Questions::erase( 'nobody@example.test', 1 ), array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ) );
$e1 = WPCPM_Student_Questions::erase( 'student-c@example.test', 1 );
ck( 'a student with 53 questions and a note is erased in batches of 50: not done after the first', array( $e1['items_removed'], $e1['done'] ), array( true, false ) );
$e2 = WPCPM_Student_Questions::erase( 'student-c@example.test', 2 );
ck( 'and done after the second, with none left', array( $e2['done'], count( get_posts( array( 'post_type' => 'wpcpm_question', 'post_status' => 'any', 'author' => 12 ) ) ), count( get_posts( array( 'post_type' => 'wpcpm_mentor_note', 'post_status' => 'any', 'author' => 12 ) ) ) ), array( true, 0, 0 ) );
ck( 'the eraser never touched the mentor\'s own note', null !== get_post( $nm ), true );

// A delete WordPress refuses: null and false both mean the row is still stored.
$r1 = $mk( 10, 'wpcpm_question', 'Stuck question one', array() );
$r2 = $mk( 10, 'wpcpm_mentor_note', 'Stuck Need help? note', array( '_wpcpm_note_source' => 'need_help' ) );
foreach ( array( null, false ) as $refused ) {
	$GLOBALS['delete_result'] = $refused;
	$res = WPCPM_Student_Questions::erase( 'student-a@example.test', 1 );
	ck( 'a delete that answers ' . var_export( $refused, true ) . ' is retained: nothing removed, retained, one message, and done',
		array( $res['items_removed'], $res['items_retained'], count( $res['messages'] ), $res['done'] ), array( false, true, 1, true ) );
	ck( 'and the message names the count', false !== strpos( $res['messages'][0], '2 ' ), true );
	ck( 'the rows are still there', array( null !== get_post( $r1 ), null !== get_post( $r2 ) ), array( true, true ) );
}
// A full batch that cannot be deleted must not loop the tool.
for ( $i = 0; $i < 50; $i++ ) { $mk( 10, 'wpcpm_question', 'Stuck bulk ' . $i, array() ); }
$GLOBALS['delete_result'] = null;
$a = WPCPM_Student_Questions::erase( 'student-a@example.test', 1 );
$b = WPCPM_Student_Questions::erase( 'student-a@example.test', 2 );
ck( 'a full batch that cannot be deleted ends on the first call, retained, and does not loop on the second', array( $a['done'], $a['items_retained'], $b['done'] ), array( true, true, true ) );
unset( $GLOBALS['delete_result'] );
$ok = WPCPM_Student_Questions::erase( 'student-a@example.test', 1 );
ck( 'when deleting works again the erasure removes them with nothing retained', array( $ok['items_removed'], $ok['items_retained'], $ok['messages'] ), array( true, false, array() ) );

$mk( 10, 'wpcpm_question', 'Left for uninstall', array( '_wpcpm_question_state' => 'waiting' ) );
$gone = $mk( 12, 'wpcpm_question', 'Trashed one', array( '_wpcpm_question_state' => 'answered' ) );
$GLOBALS['posts'][ $gone ]->post_status = 'trash';
WPCPM_Student_Questions::delete_all();
ck( 'delete_all() removes every question of any status with its meta, and no other post type',
	array( count( get_posts( array( 'post_type' => 'wpcpm_question', 'post_status' => 'any' ) ) ), isset( $GLOBALS['pmeta'][ $gone ] ), null !== get_post( $nm ) ), array( 0, false, true ) );


echo "\n" . ( $fail ? "FAILURES: $fail\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
