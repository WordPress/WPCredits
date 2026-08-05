<?php
/**
 * Smoke-runs every admin-post handler in the plugin.
 *
 * Run from the plugin root:  php bin/test-handlers.php
 *
 * The gap this closes: `php -l` parses, and a static scan can check that a name exists,
 * but neither *executes* a handler. The `WPCPM_Mentor_Calls::ANCHOR` fatal lived in
 * `bounce()` — which every path through booking, cancelling and setting a timezone ends
 * at — and survived four releases because nothing here ever called them.
 *
 * A handler "passes" if it reaches a redirect or a `wp_die()`. Both are normal outcomes.
 * A PHP `Error` is not.
 */

if ( 'cli' !== PHP_SAPI ) {
	// This file declares stubs for dozens of WordPress functions. Loaded inside WordPress
	// it would fatal on the first redeclare, so it refuses to run anywhere but the CLI.
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

class RedirectSignal extends Exception {}
class DieSignal extends Exception {}

$GLOBALS['opts']  = array();
$GLOBALS['umeta'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['posts'] = array();
$GLOBALS['users'] = array();
$GLOBALS['cron']  = array();
$GLOBALS['locale_stack'] = array();
$GLOBALS['trans'] = array();
$GLOBALS['mail']  = array();
$GLOBALS['caps']  = true;
$GLOBALS['uid']   = 1;

/* ---- WP classes ---------------------------------------------------------- */
class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '' ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = 'publish', $post_content = '', $post_author = 0, $post_title = '';
}
class WP_Locale {
	public function get_weekday( $i ) {
		$d = array( 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday' );
		return $d[ $i ];
	}
	public function get_weekday_initial( $n ) { return substr( $n, 0, 1 ); }
}
$GLOBALS['wp_locale'] = new WP_Locale();
$GLOBALS['wp'] = (object) array( 'request' => 'student-dashboard' );

/* ---- WP functions ------------------------------------------------------- */
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function esc_textarea( $s ) { return esc_html( $s ); }
function selected( $a, $b, $e = true ) { return (string) $a === (string) $b ? ' selected' : ''; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_html_class( $s ) { return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $s ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function wp_kses_post( $s ) { return $s; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {}
function add_filter() {}
function do_action() {}
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function get_bloginfo( $k ) { return 'Test Site'; }
function wp_specialchars_decode( $s, $q = null ) { return $s; }
function number_format_i18n( $n ) { return (string) $n; }
function date_i18n( $f, $t = null ) { return gmdate( $f, $t ? $t : time() ); }
function human_time_diff( $a, $b = null ) { return '2 hours'; }
function wp_timezone_string() { return 'UTC'; }
function wp_timezone() { return new DateTimeZone( 'UTC' ); }
function wp_date( $f, $t = null, $tz = null ) {
	$d = new DateTimeImmutable( '@' . ( null === $t ? time() : $t ) );
	return $d->setTimezone( $tz ? $tz : new DateTimeZone( 'UTC' ) )->format( $f );
}
function user_trailingslashit( $p ) { return rtrim( $p, '/' ) . '/'; }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( $u ) : parse_url( $u, $c ); }
function wp_parse_str( $s, &$a ) { parse_str( (string) $s, $a ); }
function add_query_arg( $a, $u = '' ) {
	if ( ! is_array( $a ) ) { return $u; }
	$sep = false === strpos( $u, '?' ) ? '?' : '&';
	return $u . $sep . http_build_query( $a );
}
function remove_query_arg( $k, $u ) { return $u; }
function wp_get_referer() { return $GLOBALS['referer'] ?? false; }
function wp_safe_redirect( $u ) { throw new RedirectSignal( $u ); }
function wp_die( $m = '', $c = 0 ) { throw new DieSignal( is_string( $m ) ? $m : 'died' ); }
function check_admin_referer( $a = '', $q = '' ) { return true; }
function wp_nonce_field( $a = '', $n = '', $r = true, $e = true ) { echo ''; }
function wp_verify_nonce( $n, $a = '' ) { return 1; }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function current_user_can( $c ) { return (bool) $GLOBALS['caps']; }
function user_can( $u, $c ) { return (bool) $GLOBALS['caps']; }
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }
function get_users( $a = array() ) {
	if ( isset( $a['meta_key'] ) ) {
		$out = array();
		foreach ( $GLOBALS['users'] as $id => $u ) {
			if ( ( $GLOBALS['umeta'][ $id ][ $a['meta_key'] ] ?? null ) === $a['meta_value'] ) { $out[] = $u; }
		}
		return $out;
	}
	return array_values( $GLOBALS['users'] );
}
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_metadata() { return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v; return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return $GLOBALS['trans'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['trans'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['trans'][ $k ] ); return true; }
function register_post_type( $t, $a = array() ) { return true; }
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_posts( $a = array() ) { return $GLOBALS['query_result'] ?? array(); }
function wp_insert_post( $a, $err = false ) {
	$id = count( $GLOBALS['posts'] ) + 100;
	$p = new WP_Post();
	$p->ID = $id;
	$p->post_type = $a['post_type'] ?? '';
	$p->post_content = $a['post_content'] ?? '';
	$p->post_author = $a['post_author'] ?? 0;
	$p->post_title = $a['post_title'] ?? '';
	$GLOBALS['posts'][ $id ] = $p;
	return $id;
}
function wp_trash_post( $id ) { return true; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ] ); return true; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
function get_post_time( $f, $gmt = false, $p = null ) { return time(); }
function wp_mail( $to, $subj, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['mail'][] = compact( 'to', 'subj', 'body', 'headers', 'attachments' );

	// Real `wp_mail()` fires one of these, and the plugin's log listens to them rather than
	// to the return value — so a harness that stayed silent here would exercise the send
	// path and never the recording path.
	do_action( 'wp_mail_succeeded', compact( 'to', 'subj', 'body', 'headers', 'attachments' ) );

	return true;
}

/*
 * The mail and calendar path. Booking a call now writes an `.ics` file and hands it to
 * `wp_mail()`, so these are reached by the ordinary booking test rather than by a test
 * about mail — which is how their absence was found.
 */
function get_temp_dir() { return sys_get_temp_dir() . '/'; }
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
function wp_generate_password( $len = 12, $special = true, $extra = false ) {
	return substr( str_repeat( 'abcdefghijklmnopqrstuvwxyz0123456789', 4 ), 0, (int) $len );
}
function sanitize_file_name( $name ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name ); }
function wp_delete_file( $path ) { if ( file_exists( $path ) ) { unlink( $path ); } }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function wp_login_url( $redirect = '' ) { return 'https://example.test/wp-login.php'; }
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ] : false; }
function wp_schedule_single_event( $when, $hook ) { $GLOBALS['cron'][ $hook ] = $when; return true; }

/*
 * Locale switching. Stubbed to *record* rather than to no-op: the whole point of the mail
 * rewrite is that templates are built inside the recipient's locale, and a stub that
 * silently did nothing would let a regression back in unseen.
 */
function switch_to_user_locale( $user_id ) {
	$GLOBALS['locale_stack'][] = (int) $user_id;

	return true;
}
function restore_previous_locale() {
	array_pop( $GLOBALS['locale_stack'] );

	return true;
}
function wp_style_is( $h, $l = 'enqueued' ) { return false; }
function wp_script_is( $h, $l = 'enqueued' ) { return false; }
function wp_register_style() {} function wp_register_script() {}
function wp_enqueue_style() {} function wp_enqueue_script() {}
function add_shortcode() {} function register_block_type() {}
function shortcode_atts( $d, $a, $s = '' ) { return array_merge( $d, (array) $a ); }
function has_block() { return false; } function has_shortcode() { return false; }
function get_queried_object_id() { return 0; } function is_singular() { return true; }
function timezone_identifiers_list_wp() { return timezone_identifiers_list(); }
function wp_new_user_notification() {}
function wp_clear_scheduled_hook() {}
function wp_schedule_event() {} function wp_unschedule_event() {}
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://example.test/'; }
function plugin_basename( $f ) { return basename( $f ); }
function load_plugin_textdomain() {}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );
define( 'WPCPM_PLUGIN_FILE', WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php' );

// The plugin's own require list, read from the bootstrap rather than copied. A copy is a
// second list that has to agree with the first, and it did not: adding WPCPM_Flash broke
// every handler here with "Class not found" until this was noticed. Same trap as
// uninstall.php, which also builds its dependencies by hand.
preg_match_all(
	"/require_once WPCPM_PLUGIN_DIR \. '([^']+)';/",
	file_get_contents( WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php' ),
	$requires
);

if ( empty( $requires[1] ) ) {
	echo "Could not read the plugin's require list.\n";
	exit( 1 );
}

foreach ( $requires[1] as $rel ) {
	// The CLI command class expects WP_CLI to exist; it is not part of any handler path.
	if ( false !== strpos( $rel, 'class-wpcpm-cli.php' ) ) {
		continue;
	}

	require_once WPCPM_PLUGIN_DIR . $rel;
}

/* ---- fixtures ----------------------------------------------------------- */
$GLOBALS['users'][1]  = new WP_User( 1, 'Ada Admin', 'admin@example.test' );
$GLOBALS['users'][20] = new WP_User( 20, 'Mia Mentor', 'mentor@example.test' );
$GLOBALS['users'][30] = new WP_User( 30, 'Sam Student', 'student@example.test' );
$GLOBALS['users'][20]->roles = array( WPCPM_Roles::ROLE_MENTOR );
$GLOBALS['users'][30]->roles = array( WPCPM_Roles::ROLE_STUDENT );

$mentor_rec  = 'recMENTOR12345678';
$student_rec = 'recSTUDENT1234567';

$GLOBALS['umeta'][20][ WPCPM_Mentors_Sync::META_RECORD_ID ] = $mentor_rec;
$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ]    = array(
	'timezone' => 'UTC', 'duration' => 30, 'lead_hours' => 0, 'horizon' => 14,
	'per_student' => 1, 'blocked' => array(), 'note' => '',
	'weekly' => array( 1 => array( array( 'start' => '09:00', 'end' => '12:00' ) ),
	                   2 => array( array( 'start' => '09:00', 'end' => '12:00' ) ),
	                   3 => array( array( 'start' => '09:00', 'end' => '12:00' ) ),
	                   4 => array( array( 'start' => '09:00', 'end' => '12:00' ) ),
	                   5 => array( array( 'start' => '09:00', 'end' => '12:00' ) ) ),
);
$GLOBALS['umeta'][20][ WPCPM_Mentors_Sync::META_MENTEES ] = array(
	array( 'record_id' => $student_rec, 'name' => 'Sam Student', 'is_past' => false, 'email' => 'student@example.test' ),
);
$GLOBALS['umeta'][30][ WPCPM_Students_Sync::META_RECORD_ID ] = $student_rec;
$GLOBALS['umeta'][30][ WPCPM_Students_Sync::META_MENTOR ]    = array( 'record_id' => $mentor_rec, 'name' => 'Mia Mentor' );
$GLOBALS['umeta'][30][ WPCPM_Students_Sync::META_PROGRAM ]   = array(
	'status' => 'In Sensei', 'program' => 'In Sensei', 'slack' => '@sam', 'team' => 'Core',
	'website' => 'sam.blog', 'profile' => 'https://profiles.wordpress.org/sam/', 'username' => 'sam',
);

/* ---- runner ------------------------------------------------------------- */
$fail = 0;
function run( $label, callable $fn ) {
	global $fail;
	$GLOBALS['mail'] = array();
	try {
		$fn();
		echo "FAIL $label — returned without redirecting or dying\n";
		$fail++;
	} catch ( RedirectSignal $e ) {
		printf( "ok   %-46s redirect -> %s\n", $label, $e->getMessage() );
	} catch ( DieSignal $e ) {
		printf( "ok   %-46s wp_die: %s\n", $label, substr( $e->getMessage(), 0, 40 ) );
	} catch ( Throwable $t ) {
		printf( "FAIL %-46s %s: %s\n     %s:%d\n", $label, get_class( $t ), $t->getMessage(), $t->getFile(), $t->getLine() );
		$fail++;
	}
}

echo "=== WPCPM_Mentor_Calls ===\n";

// The exact flow from the reported error: an administrator booking on a student's page.
$GLOBALS['uid'] = 1; $GLOBALS['caps'] = true;
$GLOBALS['referer'] = 'https://example.test/student-dashboard/?wpcpm_student_view=30';
$slots = WPCPM_Mentor_Availability::slots( 20 );
$_POST = array( 'student' => 30, 'start' => $slots[0]['start'], 'topic' => 'First call' );
run( 'handle_book (admin, on behalf, valid slot)', array( 'WPCPM_Mentor_Calls', 'handle_book' ) );

$_POST = array( 'student' => 30, 'start' => 1, 'topic' => '' );
run( 'handle_book (slot no longer on offer)', array( 'WPCPM_Mentor_Calls', 'handle_book' ) );

$_POST = array( 'student' => 999, 'start' => 0 );
run( 'handle_book (student with no mentor)', array( 'WPCPM_Mentor_Calls', 'handle_book' ) );

$GLOBALS['posts'][500] = new WP_Post();
$GLOBALS['posts'][500]->ID = 500;
$GLOBALS['posts'][500]->post_type = WPCPM_Mentor_Calls::POST_TYPE;
$GLOBALS['pmeta'][500] = array(
	WPCPM_Mentor_Calls::META_MENTOR => 20, WPCPM_Mentor_Calls::META_STUDENT => 30,
	WPCPM_Mentor_Calls::META_START => time() + 86400, WPCPM_Mentor_Calls::META_END => time() + 88200,
	WPCPM_Mentor_Calls::META_RECORD => $student_rec, WPCPM_Mentor_Calls::META_NAME => 'Sam Student',
	WPCPM_Mentor_Calls::META_ZONE => 'Europe/Riga',
);
$_POST = array( 'call' => 500 );
run( 'handle_cancel (existing call)', array( 'WPCPM_Mentor_Calls', 'handle_cancel' ) );

$_POST = array( 'call' => 4242 );
run( 'handle_cancel (call that does not exist)', array( 'WPCPM_Mentor_Calls', 'handle_cancel' ) );

$_POST = array( 'timezone' => 'Europe/Riga' );
run( 'handle_timezone (valid zone)', array( 'WPCPM_Mentor_Calls', 'handle_timezone' ) );

$_POST = array( 'timezone' => 'Not/AZone' );
run( 'handle_timezone (invalid zone)', array( 'WPCPM_Mentor_Calls', 'handle_timezone' ) );

echo "\n=== WPCPM_Mentor_Availability ===\n";
$GLOBALS['uid'] = 20; $GLOBALS['caps'] = false;
$_POST = array(
	'mentor' => 20,
	'availability' => array(
		'weekly' => array( 1 => array( array( 'start' => '09:00', 'end' => '12:00' ) ) ),
		'timezone' => 'Europe/Riga', 'duration' => 30, 'lead_hours' => 24,
		'horizon' => 28, 'per_student' => 1, 'blocked' => "2026-12-24\n2026-12-25", 'note' => 'Video link follows.',
	),
);
run( 'handle_save (mentor saves own schedule)', array( 'WPCPM_Mentor_Availability', 'handle_save' ) );

$_POST = array( 'mentor' => 20, 'availability' => array( 'note' => array( 'array', 'shaped' ), 'timezone' => array( 'x' ) ) );
run( 'handle_save (array-shaped values)', array( 'WPCPM_Mentor_Availability', 'handle_save' ) );

$GLOBALS['uid'] = 30;
$_POST = array( 'mentor' => 20, 'availability' => array() );
run( 'handle_save (student cannot edit a mentor)', array( 'WPCPM_Mentor_Availability', 'handle_save' ) );

echo "\n=== WPCPM_Student_Report_Form ===\n";
$GLOBALS['uid'] = 30; $GLOBALS['caps'] = false;
$GLOBALS['opts'][ WPCPM_Settings::OPTION ] = array( 'api_token' => '', 'base_id' => '' );

$_POST = array( 'student' => 30, 'report' => array() );
run( 'handle_save (nothing submitted)', array( 'WPCPM_Student_Report_Form', 'handle_save' ) );

$_POST = array( 'student' => 20, 'report' => array( 'x' => '1' ) );
run( 'handle_save (not your report)', array( 'WPCPM_Student_Report_Form', 'handle_save' ) );

// A grade that cannot be read must bounce rather than fatal, and must not be sent as zero.
$_POST = array( 'student' => 30, 'report' => array( WPCPM_Student_Report_Form::key( 'Hours' ) => 'lots' ) );
run( 'handle_save (unreadable number)', array( 'WPCPM_Student_Report_Form', 'handle_save' ) );

$_POST = array( 'student' => 30, 'report' => array( WPCPM_Student_Report_Form::key( 'Hours' ) => '120' ) );
run( 'handle_save (a readable number, no credentials)', array( 'WPCPM_Student_Report_Form', 'handle_save' ) );

// Teams post as an array, including the empty value the form always carries so that unchecking
// everything still reaches the handler. A version that read every field through `is_scalar()`
// turned this into '' and saved nothing — the bug this shape is here to keep out.
$team = WPCPM_Student_Report_Form::key( 'Main Contribution Team' );

$_POST = array( 'student' => 30, 'report' => array( $team => array( 'recTEAM0000000001', '' ) ) );
run( 'handle_save (several teams, as an array)', array( 'WPCPM_Student_Report_Form', 'handle_save' ) );

$_POST = array( 'student' => 30, 'report' => array( $team => array( '' ) ) );
run( 'handle_save (every team unchecked)', array( 'WPCPM_Student_Report_Form', 'handle_save' ) );

// A hostile shape: nested arrays and objects must not reach Airtable or trip a type error.
$_POST = array( 'student' => 30, 'report' => array( $team => array( array( 'nope' ), 'recUNKNOWN0000001' ) ) );
run( 'handle_save (junk in the team array)', array( 'WPCPM_Student_Report_Form', 'handle_save' ) );

echo "\n=== WPCPM_Group_Sessions ===\n";
$GLOBALS['uid'] = 20; $GLOBALS['caps'] = false;

// Every rejection path, because each one is a `bounce()` or a `wp_die()` and a typo in any of them
// is a fatal on a form a mentor uses.
$_POST = array( 'mentor' => 20, 'date' => '', 'time' => '', 'minutes' => 60, 'capacity' => 6 );
run( 'handle_create (no date)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );

$_POST = array( 'mentor' => 20, 'date' => '2027-01-05', 'time' => '10:00', 'minutes' => 0, 'capacity' => 6 );
run( 'handle_create (no length)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );

$_POST = array( 'mentor' => 20, 'date' => '2027-01-05', 'time' => '10:00', 'minutes' => 60, 'capacity' => 1 );
run( 'handle_create (capacity of one is a 1:1 call)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );

$_POST = array( 'mentor' => 20, 'date' => '2020-01-05', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6 );
run( 'handle_create (in the past)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );

$_POST = array( 'mentor' => 20, 'date' => '2027-01-05', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6 );
run( 'handle_create (valid)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );

$_POST = array( 'mentor' => 30, 'date' => '2027-01-05', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6 );
run( 'handle_create (not your diary)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );

$GLOBALS['uid'] = 30;
$_POST          = array( 'session' => 0 );
run( 'handle_join (no such session)', array( 'WPCPM_Group_Sessions', 'handle_join' ) );

$_POST = array( 'session' => 999999 );
run( 'handle_leave (not on it)', array( 'WPCPM_Group_Sessions', 'handle_leave' ) );

$GLOBALS['uid'] = 20;
$_POST          = array( 'session' => 0, 'note' => 'Covered the release cycle.' );
run( 'handle_note (no such session)', array( 'WPCPM_Group_Sessions', 'handle_note' ) );

echo "\n=== WPCPM_Mentor_Notes ===\n";
$GLOBALS['uid'] = 20; $GLOBALS['caps'] = false;
$_POST = array( 'student' => $student_rec, 'mentor' => 20, 'note' => 'Spoke about the project.', 'student_name' => 'Sam Student' );
run( 'handle_add (mentor adds a note)', array( 'WPCPM_Mentor_Notes', 'handle_add' ) );

$_POST = array( 'student' => $student_rec, 'mentor' => 20, 'note' => '   ' );
run( 'handle_add (empty note)', array( 'WPCPM_Mentor_Notes', 'handle_add' ) );

$_POST = array( 'note_id' => 4242 );
run( 'handle_delete (note that does not exist)', array( 'WPCPM_Mentor_Notes', 'handle_delete' ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL HANDLERS REACHED A NORMAL OUTCOME\n" );
exit( $fail ? 1 : 0 );
