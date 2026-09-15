<?php
/**
 * Smoke-runs every admin-post handler in the plugin.
 *
 * Run from the plugin root:  php bin/test-handlers.php
 *
 * The gap this closes: `php -l` parses, and a static scan can check that a name exists,
 * but neither *executes* a handler. The `WPCPM_Mentor_Calls::ANCHOR` fatal lived in
 * `bounce()` - which every path through booking, cancelling and setting a timezone ends
 * at - and survived four releases because nothing here ever called them.
 *
 * A handler "passes" if it reaches a redirect or a `wp_die()`. Both are normal outcomes.
 * A PHP `Error` is not.
 *
 * **What the harness does not prove.** `get_posts()` here answers every query from
 * `$GLOBALS['query_result']`, whatever was asked for, so the clash and Join all checks exercise the
 * real rules and the handlers' control flow but say nothing about the queries themselves - whether
 * a reader asks for the right post type, status or meta. Those are proven in
 * `bin/test-group-sessions.php`, whose post model answers a query the way WordPress would.
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
require_once __DIR__ . '/stubs/caps.php';
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
function get_posts( $a = array() ) { $found = $GLOBALS['query_result'] ?? array(); return ( isset( $a['fields'] ) && 'ids' === $a['fields'] ) ? array_map( function ( $p ) { return $p->ID; }, $found ) : $found; }
function wp_insert_post( $a, $err = false ) {
	// An ID no post holds: the highest in the store plus one, from 1000, above every fixture keyed
	// by hand (they sit below 1000), so a deleted post's slot is never handed out again and no
	// fixture is overwritten (the review of 1.109.1).
	$id = $GLOBALS['posts'] ? max( 1000, max( array_keys( $GLOBALS['posts'] ) ) + 1 ) : 1000;
	$p = new WP_Post();
	$p->ID = $id;
	$p->post_type = $a['post_type'] ?? '';
	$p->post_status = $a['post_status'] ?? 'publish';
	$p->post_content = $a['post_content'] ?? '';
	$p->post_author = $a['post_author'] ?? 0;
	$p->post_title = $a['post_title'] ?? '';
	$GLOBALS['posts'][ $id ] = $p;
	return $id;
}
function wp_trash_post( $id ) { return true; }
function wp_update_post( $a, $err = false ) {
	$id = (int) ( $a['ID'] ?? 0 );
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) { return 0; }
	foreach ( array( 'post_content', 'post_title', 'post_status' ) as $field ) {
		if ( isset( $a[ $field ] ) ) { $GLOBALS['posts'][ $id ]->$field = $a[ $field ]; }
	}
	return $id;
}
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ] ); return true; }
function get_post_meta( $id, $k, $single = false ) { $v = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ''; if ( $single && is_array( $v ) && ! empty( $GLOBALS['pmeta_rows'][ (int) $id ][ $k ] ) ) { return $v ? $v[0] : ''; } return $v; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
// Repeated rows, as WordPress keeps them: the attendee list of a group session is one row a student.
function add_post_meta( $id, $k, $v, $unique = false ) { $rows = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? array(); $rows = is_array( $rows ) ? $rows : array(); $rows[] = $v; $GLOBALS['pmeta'][ (int) $id ][ $k ] = $rows; $GLOBALS['pmeta_rows'][ (int) $id ][ $k ] = true; return true; }
function get_post_time( $f, $gmt = false, $p = null ) { return time(); }
function wp_mail( $to, $subj, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['mail'][] = compact( 'to', 'subj', 'body', 'headers', 'attachments' );

	// Real `wp_mail()` fires one of these, and the plugin's log listens to them rather than
	// to the return value - so a harness that stayed silent here would exercise the send
	// path and never the recording path.
	do_action( 'wp_mail_succeeded', compact( 'to', 'subj', 'body', 'headers', 'attachments' ) );

	return true;
}

/*
 * The mail and calendar path. Booking a call now writes an `.ics` file and hands it to
 * `wp_mail()`, so these are reached by the ordinary booking test rather than by a test
 * about mail - which is how their absence was found.
 */
function get_temp_dir() { return sys_get_temp_dir() . '/'; }
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
function wp_generate_password( $len = 12, $special = true, $extra = false ) {
	return substr( str_repeat( 'abcdefghijklmnopqrstuvwxyz0123456789', 4 ), 0, (int) $len );
}
function wp_hash( $data, $scheme = 'auth' ) { return md5( 'handlers|' . (string) $data ); }
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

// Stubs the Institutions module's storage probe reaches. Declared with the others rather
// than at the point of use, so a handler that starts calling one does not fail here first.
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function wp_upload_dir( $t = null, $c = true ) { return array( 'basedir' => get_temp_dir() . 'wpcpm-handlers', 'baseurl' => 'https://example.test/wp-content/uploads' ); }
function wp_is_writable( $p ) { return is_writable( $p ); }
function wp_remote_head( $u, $a = array() ) { return array( 'response' => array( 'code' => 403 ) ); }
function wp_remote_retrieve_response_code( $r ) { return isset( $r['response']['code'] ) ? $r['response']['code'] : ''; }

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
// A second mentor, whose only part is to own the session another mentor's student presses Join
// all on: without an account behind that ID the handler refused because the user did not exist,
// and the comparison that decides whose series it is was never reached (the final review of
// 1.108.0).
$GLOBALS['users'][21] = new WP_User( 21, 'Noa Mentor', 'noa@example.test' );
$GLOBALS['users'][30] = new WP_User( 30, 'Sam Student', 'student@example.test' );
$GLOBALS['users'][20]->roles = array( WPCPM_Roles::ROLE_MENTOR );
$GLOBALS['users'][21]->roles = array( WPCPM_Roles::ROLE_MENTOR );
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
$mentor_schedule = $GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ];
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

// The post store mints IDs that no post already holds, whatever was deleted before: a check that
// trashes a session and plans another must not have the second overwrite a fixture (the re-review
// of 1.109.0's fix wave).
$GLOBALS['posts'][777] = new WP_Post();
$GLOBALS['posts'][777]->ID = 777;
$probe_id = wp_insert_post( array( 'post_type' => 'probe' ) );
check( 'a new post never takes an ID the store already holds, and the fixture it could have replaced is intact',
    array( $probe_id > 777, 'probe' !== ( $GLOBALS['posts'][777]->post_type ?? '' ), isset( $GLOBALS['posts'][ $probe_id ] ) ),
    array( true, true, true ) );
unset( $GLOBALS['posts'][777], $GLOBALS['posts'][ $probe_id ] );
function run( $label, callable $fn ) {
	global $fail;
	$GLOBALS['mail'] = array();
	try {
		$fn();
		echo "FAIL $label - returned without redirecting or dying\n";
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

/**
 * Assert a value, for the few outcomes a handler's flash or a rule's answer has to be read.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
function check( $label, $got, $want ) {
	global $fail;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	$fail++;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * The message a bounce left on a channel, read from the pending meta and cleared: `take()`
 * memoizes per request, so a suite that presses twice must read the raw queue.
 *
 * @param int    $user_id The user.
 * @param string $channel The channel.
 * @return mixed The value, or '' when nothing is pending.
 */
function flashed( $user_id, $channel ) {
	$pending = $GLOBALS['umeta'][ (int) $user_id ][ WPCPM_Flash::META ] ?? array();
	$value   = is_array( $pending ) && array_key_exists( $channel, $pending ) ? $pending[ $channel ] : '';
	unset( $GLOBALS['umeta'][ (int) $user_id ][ WPCPM_Flash::META ] );

	return $value;
}

/**
 * The post IDs the store gained since a count of it, in the order they were created.
 *
 * Gathered by creation rather than by topic: the store keys its posts by ID and appends them, so
 * a slice from an earlier count is exactly what the press just made, and a later check reusing
 * the same topic cannot quietly join it (the final review of 1.109.0).
 *
 * @param int $before How many posts the store held before the press.
 * @return int[] The IDs, oldest first.
 */
function created_since( $before ) {
	return array_map(
		function ( $post ) {
			return $post->ID;
		},
		array_slice( $GLOBALS['posts'], (int) $before )
	);
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
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array( 'api_token' => '', 'base_id' => '' );

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
// turned this into '' and saved nothing - the bug this shape is here to keep out.
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

// 1.107.1, a mentor's request: a student may join every session that has a place. The limit of one
// upcoming call (`per_student` above) counts one-to-one calls alone, so a joined session is neither
// refused by it nor counted against a private booking.
$GLOBALS['uid'] = 20;
$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ] = $mentor_schedule;
$_POST          = array( 'mentor' => 20, 'date' => '2027-01-12', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6 );
run( 'handle_create (a second session)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );

$sessions = array();
foreach ( $GLOBALS['posts'] as $post ) {
	if ( WPCPM_Mentor_Calls::POST_TYPE === $post->post_type && WPCPM_Mentor_Calls::capacity( $post->ID ) > 1 ) {
		$sessions[] = $post;
	}
}
check( 'two sessions stand', count( $sessions ), 2 );

$GLOBALS['uid'] = 30;
$_POST          = array( 'session' => $sessions[0]->ID );
run( 'handle_join (first session)', array( 'WPCPM_Group_Sessions', 'handle_join' ) );
check( 'the first join lands', flashed( 30, 'call' ), 'session-joined' );

// The student now holds that session as an upcoming call, as the query would answer.
$GLOBALS['query_result'] = array( $sessions[0] );
$_POST                   = array( 'session' => $sessions[1]->ID );
run( 'handle_join (second session, at the limit of one)', array( 'WPCPM_Group_Sessions', 'handle_join' ) );
check( 'a joined session does not count against the limit, so the second join lands too', flashed( 30, 'call' ), 'session-joined' );
check( 'and a private call can still be booked beside the sessions', WPCPM_Mentor_Calls::why_not_bookable( 30 ), '' );

$GLOBALS['posts'][501]            = new WP_Post();
$GLOBALS['posts'][501]->ID        = 501;
$GLOBALS['posts'][501]->post_type = WPCPM_Mentor_Calls::POST_TYPE;
$GLOBALS['pmeta'][501]            = array(
	WPCPM_Mentor_Calls::META_MENTOR => 20, WPCPM_Mentor_Calls::META_STUDENT => 30,
	WPCPM_Mentor_Calls::META_START => time() + 172800, WPCPM_Mentor_Calls::META_END => time() + 174600,
);
$GLOBALS['query_result'] = array( $GLOBALS['posts'][501] );
check( 'while a one-to-one call still counts', '' !== WPCPM_Mentor_Calls::why_not_bookable( 30 ), true );
$GLOBALS['query_result'] = array();

// 1.108.0: a series is planned from the first date and the more dates, all or nothing.
$GLOBALS['uid'] = 20;
$posts_before   = count( $GLOBALS['posts'] );
$_POST          = array( 'mentor' => 20, 'date' => '2027-02-02', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'Release cycle', 'more_dates' => array( '2027-02-09', '', '2027-02-16' ) );
run( 'handle_create (a series of three)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
check( 'the series is planned and the notice says how many', flashed( 20, 'call' ), array( 'series-planned', 3 ) );

$series = array();
foreach ( $GLOBALS['posts'] as $post ) {
	if ( WPCPM_Group_Sessions::series_of( $post->ID ) > 0 ) {
		$series[] = $post->ID;
	}
}
check( 'three sessions carry the first as their series, a week apart, the empty box ignored',
    array( count( $series ), count( $GLOBALS['posts'] ) - $posts_before, array_unique( array_map( 'WPCPM_Group_Sessions::series_of', $series ) ), array_map( function ( $id ) { return gmdate( 'Y-m-d H:i', (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_START, true ) ); }, $series ) ),
    array( 3, 3, array( $series[0] ), array( '2027-02-02 10:00', '2027-02-09 10:00', '2027-02-16 10:00' ) ) );

$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => '2027-03-02', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( '2027-03-09', '2027-03-09' ) );
run( 'handle_create (a date given twice)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$twice = flashed( 20, 'call' );
$_POST = array( 'mentor' => 20, 'date' => '2027-03-02', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( '2020-03-09' ) );
run( 'handle_create (a date that has passed)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$past = flashed( 20, 'call' );
$GLOBALS['query_result'] = array( $GLOBALS['posts'][ $series[2] ] );
$_POST                   = array( 'mentor' => 20, 'date' => '2027-03-02', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( '2027-02-16' ) );
run( 'handle_create (a date the mentor already holds)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$clash                   = flashed( 20, 'call' );
$GLOBALS['query_result'] = array();
check( 'a date given twice, a date that has passed and a clash each refuse the whole list naming the date, and nothing is created',
    array( $twice, $past, $clash, count( $GLOBALS['posts'] ) - $posts_before ),
    array( array( 'series-twice', '2027-03-09' ), array( 'series-past', '2020-03-09' ), array( 'series-clash', '2027-02-16' ), 0 ) );

// The diary is read and the sessions are written under the booking lock, so a booking on the same
// mentor cannot take one of the starts in between (the final review of 1.108.0).
$before_lock = count( $GLOBALS['posts'] );
WPCPM_Mentor_Calls::lock_for( 20 );
$_POST = array( 'mentor' => 20, 'date' => '2027-04-06', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( '2027-04-13' ) );
run( 'handle_create (the mentor\'s lock is held)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
check( 'a held lock refuses the planning and creates nothing',
    array( flashed( 20, 'call' ), count( $GLOBALS['posts'] ) - $before_lock ),
    array( 'busy', 0 ) );
WPCPM_Mentor_Calls::unlock_for( 20 );

run( 'handle_create (the same press once the lock is free)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
check( 'the same press plans the series once the lock is free, and leaves the lock released',
    array( flashed( 20, 'call' ), count( $GLOBALS['posts'] ) - $before_lock, get_option( 'wpcpm_call_lock_20', false ) ),
    array( array( 'series-planned', 2 ), 2, false ) );

// A box holding something that is not a date is a series thing, since a box is only filled for a
// series: it is refused naming what the box held, not with the lone session's "needs a date and a
// start time" (the re-review of 1.109.0's fix wave).
$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => '2027-05-04', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( 'not-a-date' ) );
run( 'handle_create (a box that is not a date)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
check( 'a box holding something that is not a date refuses the list naming what it held, and creates nothing',
    array( flashed( 20, 'call' ), count( $GLOBALS['posts'] ) - $posts_before ),
    array( array( 'series-date', 'not-a-date' ), 0 ) );

// A crafted entry that is not text at all (`more_dates[0][]=x`) can only come from a tampered form:
// it is passed over without a warning, and the boxes that are text are read as before.
$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => '2027-05-04', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( array( 'x' ), '2027-05-11' ) );
run( 'handle_create (a crafted box that is not text)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
check( 'a crafted box that is not text is passed over and the readable box beside it is planned',
    array( flashed( 20, 'call' ), count( $GLOBALS['posts'] ) - $posts_before ),
    array( array( 'series-planned', 2 ), 2 ) );

// 1.108.0: the lists group a series under one heading, the lone sessions on their own.
$lone_sessions = array();
foreach ( $GLOBALS['posts'] as $post ) {
	if ( WPCPM_Mentor_Calls::POST_TYPE === $post->post_type && WPCPM_Mentor_Calls::capacity( $post->ID ) > 1 && 0 === WPCPM_Group_Sessions::series_of( $post->ID ) ) {
		$lone_sessions[] = $post;
	}
}
$GLOBALS['query_result'] = array_merge( array( $lone_sessions[0] ), array_map( 'get_post', $series ) );

$GLOBALS['uid'] = 30;
ob_start();
WPCPM_Group_Sessions::render_student_list( $GLOBALS['users'][30], true );
$student_list = ob_get_clean();

check( 'the student\'s list draws the series under its heading with its three rows, the topic once, and the lone session as a row of its own, with Join on each row not yet joined and Leave on the one joined',
    array(
        substr_count( $student_list, 'class="wpcpm-sessions__series"' ),
        false !== strpos( $student_list, '<p class="wpcpm-sessions__series-heading"><strong>Release cycle</strong> <span class="wpcpm-sessions__series-span">3 sessions, February 2, 2027 to February 16, 2027</span></p>' ),
        substr_count( substr( $student_list, strpos( $student_list, 'wpcpm-sessions__list--series' ) ), '<li class="wpcpm-sessions__item">' ),
        substr_count( $student_list, 'wpcpm-call__topic">Release cycle' ),
        substr_count( $student_list, '<li class="wpcpm-sessions__item">' ),
        substr_count( $student_list, 'name="action" value="wpcpm_join_session"' ),
        substr_count( $student_list, 'name="action" value="wpcpm_leave_session"' ),
    ),
    array( 1, true, 3, 0, 4, 3, 1 ) );

$GLOBALS['uid'] = 20;
ob_start();
WPCPM_Group_Sessions::render_mentor_panel( $GLOBALS['users'][20] );
$mentor_panel = ob_get_clean();

check( 'the mentor\'s panel groups the same series and keeps Change and Cancel on every row',
    array(
        substr_count( $mentor_panel, 'class="wpcpm-sessions__series"' ),
        false !== strpos( $mentor_panel, '3 sessions, February 2, 2027 to February 16, 2027' ),
        substr_count( $mentor_panel, 'name="action" value="' . WPCPM_Mentor_Calls::ACTION_CANCEL . '"' ),
    ),
    array( 1, true, 4 ) );

// The eight More dates boxes carry no label of their own beyond "Date 5", so the sentence that
// says an empty box is fine has to be attached to them (the final review of 1.108.0).
ob_start();
WPCPM_Group_Sessions::render_mentor_planner( $GLOBALS['users'][20] );
$planner = ob_get_clean();

check( 'every More dates box points at the hint that explains them, and the hint carries that id once',
    array(
        substr_count( $planner, 'name="more_dates[]"' ),
        substr_count( $planner, 'aria-describedby="wpcpm-sessions-more-hint"' ),
        substr_count( $planner, 'id="wpcpm-sessions-more-hint"' ),
    ),
    array( 8, 8, 1 ) );

// A mentor may change one session's topic, and that edit used to be invisible under a series
// heading, which carries the topic for all of them (the final review of 1.108.0).
$GLOBALS['posts'][ $series[1] ]->post_content = 'Just this one: the release party';

$GLOBALS['uid'] = 30;
ob_start();
WPCPM_Group_Sessions::render_student_list( $GLOBALS['users'][30], true );
$changed_list = ob_get_clean();

check( 'the row whose topic differs from the heading\'s prints its own, and the rows that match print none',
    array(
        substr_count( $changed_list, 'wpcpm-call__topic">Just this one: the release party' ),
        substr_count( $changed_list, 'wpcpm-call__topic">Release cycle' ),
        substr_count( $changed_list, 'class="wpcpm-call__topic"' ),
        false !== strpos( $changed_list, '<strong>Release cycle</strong>' ),
    ),
    array( 1, 0, 1, true ) );

$GLOBALS['posts'][ $series[1] ]->post_content = 'Release cycle';

// 1.108.0: Join all takes every session of the series with a place, under the booking lock.
check( 'the series offers Join all while there is something to take',
    array( substr_count( $student_list, 'name="action" value="wpcpm_join_series"' ), substr_count( $student_list, '>Join all</button>' ) ),
    array( 1, 1 ) );

// What the presses below run against: the series as the query would answer it, the student pressing.
$GLOBALS['query_result'] = array_map( 'get_post', $series );
$GLOBALS['uid']          = 30;

$_POST = array( 'series' => 0 );
run( 'handle_join_series (no such series)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );
check( 'no such series is gone', flashed( 30, 'call' ), 'session-gone' );

$GLOBALS['posts'][900]            = new WP_Post();
$GLOBALS['posts'][900]->ID        = 900;
$GLOBALS['posts'][900]->post_type = WPCPM_Mentor_Calls::POST_TYPE;
$GLOBALS['pmeta'][900]            = array(
	WPCPM_Mentor_Calls::META_MENTOR => 21, WPCPM_Mentor_Calls::META_CAPACITY => 6,
	WPCPM_Mentor_Calls::META_START => time() + 864000, WPCPM_Mentor_Calls::META_END => time() + 867600,
	WPCPM_Group_Sessions::META_SERIES => 900,
);
$GLOBALS['query_result'] = array( $GLOBALS['posts'][900] );
$_POST                   = array( 'series' => 900 );
run( 'handle_join_series (another mentor\'s series)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );
check( 'another mentor\'s series is not theirs', flashed( 30, 'call' ), 'session-not-yours' );

// Somebody else's booking holds the lock at the moment of the press. Pressed here, while every
// session still has a place and the student is on none, so a `busy` answer can only be the lock
// (the final review of 1.108.0: the release assertion below named a key that never exists, so
// neither the taking nor the releasing of the lock was being read).
$GLOBALS['query_result'] = array_map( 'get_post', $series );
WPCPM_Mentor_Calls::lock_for( 20 );
$_POST = array( 'series' => $series[0] );
run( 'handle_join_series (the mentor\'s lock is held)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );
check( 'a held lock refuses the press and puts nobody on anything',
    array(
        flashed( 30, 'call' ),
        WPCPM_Group_Sessions::has_joined( $series[0], 30 ),
        WPCPM_Group_Sessions::has_joined( $series[1], 30 ),
        WPCPM_Group_Sessions::has_joined( $series[2], 30 ),
    ),
    array( 'busy', false, false, false ) );
WPCPM_Mentor_Calls::unlock_for( 20 );

// The middle session fills up before the student presses.
foreach ( array( 41, 42, 43, 44, 45, 46 ) as $other ) {
	WPCPM_Mentor_Calls::add_attendee( $series[1], $other, 'recSTUDENT' . $other . '000000' );
}
$GLOBALS['query_result'] = array_map( 'get_post', $series );
$_POST                   = array( 'series' => $series[0] );
run( 'handle_join_series (two of three, one full)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );
$outcome = flashed( 30, 'call' );

check( 'the student is put on the two sessions with a place, the full one is skipped and counted, and one message each goes to the student and the mentor with the file',
    array(
        $outcome,
        WPCPM_Group_Sessions::has_joined( $series[0], 30 ),
        WPCPM_Group_Sessions::has_joined( $series[1], 30 ),
        WPCPM_Group_Sessions::has_joined( $series[2], 30 ),
        count( $GLOBALS['mail'] ),
        false !== strpos( $GLOBALS['mail'][1]['subj'], 'Group sessions with Mia Mentor: 2 dates' ),
        basename( reset( $GLOBALS['mail'][1]['attachments'] ) ),
        get_option( 'wpcpm_call_lock_20', false ),
    ),
    array( array( 'series-joined-some', 2, 3, 1 ), true, false, true, 2, true, 'mentor-sessions.ics', false ) );

$_POST = array( 'series' => $series[0] );
run( 'handle_join_series (nothing left to take)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );
check( 'a second press finds nothing to join', flashed( 30, 'call' ), 'series-nothing' );

ob_start();
WPCPM_Group_Sessions::render_student_list( $GLOBALS['users'][30], true );
$after_list = ob_get_clean();
check( 'and the series no longer offers Join all', substr_count( $after_list, 'name="action" value="wpcpm_join_series"' ), 0 );

// The press as it usually goes: a fresh series, a place on every session, the student on none.
// A second series rather than the first, whose middle session is full and whose other two are
// taken (the final review of 1.108.0: nothing pressed the outcome the student normally sees).
$GLOBALS['uid']          = 20;
$GLOBALS['query_result'] = array();
$before_second           = array_keys( $GLOBALS['posts'] );
$_POST                   = array( 'mentor' => 20, 'date' => '2027-05-04', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'Patch review', 'more_dates' => array( '2027-05-11', '2027-05-18' ) );
run( 'handle_create (a second series of three)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
check( 'the second series is planned', flashed( 20, 'call' ), array( 'series-planned', 3 ) );

// Created in date order, so the new IDs climb with the dates and the first of them is the series.
$second_series = array_values( array_diff( array_keys( $GLOBALS['posts'] ), $before_second ) );
sort( $second_series );

$GLOBALS['uid']          = 30;
$GLOBALS['query_result'] = array_map( 'get_post', $second_series );
$_POST                   = array( 'series' => $second_series[0] );
run( 'handle_join_series (every session has a place)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );

check( 'the student is put on all three, the message says all three, and one message each goes to the student and the mentor',
    array(
        flashed( 30, 'call' ),
        WPCPM_Group_Sessions::has_joined( $second_series[0], 30 ),
        WPCPM_Group_Sessions::has_joined( $second_series[1], 30 ),
        WPCPM_Group_Sessions::has_joined( $second_series[2], 30 ),
        count( $GLOBALS['mail'] ),
        false !== strpos( $GLOBALS['mail'][1]['subj'], 'Group sessions with Mia Mentor: 3 dates' ),
    ),
    array( array( 'series-joined', 3 ), true, true, true, 2, true ) );

// 1.108.1: a session that has started draws neither Join nor Leave (the handlers refuse both through
// `session()`), says it has started, and its relative time reads "started ... ago", not "in ...".
foreach ( array( 910 => time() - 1200, 911 => time() - 1200, 912 => time() + DAY_IN_SECONDS ) as $started_id => $started_at ) {
	$GLOBALS['posts'][ $started_id ]            = new WP_Post();
	$GLOBALS['posts'][ $started_id ]->ID        = $started_id;
	$GLOBALS['posts'][ $started_id ]->post_type = WPCPM_Mentor_Calls::POST_TYPE;
	$GLOBALS['pmeta'][ $started_id ]            = array(
		WPCPM_Mentor_Calls::META_MENTOR => 20, WPCPM_Mentor_Calls::META_CAPACITY => 6,
		WPCPM_Mentor_Calls::META_START => $started_at, WPCPM_Mentor_Calls::META_END => $started_at + 3600,
	);
}
WPCPM_Mentor_Calls::add_attendee( 911, 30, $student_rec );
$GLOBALS['query_result'] = array( $GLOBALS['posts'][910], $GLOBALS['posts'][911], $GLOBALS['posts'][912] );
$GLOBALS['uid']          = 30;
ob_start();
WPCPM_Group_Sessions::render_student_list( $GLOBALS['users'][30], true );
$started_list = ob_get_clean();
check( 'a session that has started offers neither Join nor Leave and says so, its time reads "started ... ago", and a future one still offers Join',
    array(
        substr_count( $started_list, 'name="action" value="' . WPCPM_Group_Sessions::ACTION_JOIN . '"' ),
        substr_count( $started_list, 'name="action" value="' . WPCPM_Group_Sessions::ACTION_LEAVE . '"' ),
        substr_count( $started_list, 'This session has started.' ),
        substr_count( $started_list, '>started 2 hours ago<' ),
        substr_count( $started_list, '>in 2 hours<' ),
    ),
    array( 1, 0, 2, 2, 1 ) );

// 1.109.0: a repeat rule fills the dates after the first (the design's section 12).
$GLOBALS['uid']          = 20;
$GLOBALS['query_result'] = array();
$posts_before            = count( $GLOBALS['posts'] );
$_POST                   = array( 'mentor' => 20, 'date' => '2027-06-01', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'Office hours', 'repeat' => 'week', 'repeat_count' => '8' );
run( 'handle_create (every week, eight sessions)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$weekly_ids = created_since( $posts_before );
// The dates are read on the mentor's own calendar, which is what a planned session means and what
// the rule stepped through, rather than on UTC (the final review of 1.109.0).
$mentor_zone = WPCPM_Mentor_Availability::timezone( WPCPM_Mentor_Availability::get( 20 )['timezone'] );
check( 'a rule of eight weeks plans eight sessions in one series, a week apart on the mentor\'s calendar, the notice counting them',
    array(
        flashed( 20, 'call' ),
        count( $GLOBALS['posts'] ) - $posts_before,
        count( array_unique( array_map( 'WPCPM_Group_Sessions::series_of', $weekly_ids ) ) ),
        array_map( function ( $id ) use ( $mentor_zone ) { return wp_date( 'Y-m-d', (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_START, true ), $mentor_zone ); }, $weekly_ids ),
    ),
    array( array( 'series-planned', 8 ), 8, 1, array( '2027-06-01', '2027-06-08', '2027-06-15', '2027-06-22', '2027-06-29', '2027-07-06', '2027-07-13', '2027-07-20' ) ) );

$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => '2027-08-03', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => '', 'repeat_count' => '5' );
run( 'handle_create (does not repeat, a stray count)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$stray = array( flashed( 20, 'call' ), count( $GLOBALS['posts'] ) - $posts_before );

$posts_before = count( $GLOBALS['posts'] );
$outcomes     = array();
foreach ( array( 'missing' => null, 'low' => '1', 'high' => '17', 'text' => 'ten' ) as $label => $bad ) {
	$_POST = array( 'mentor' => 20, 'date' => '2027-09-07', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => '2weeks' );
	if ( null !== $bad ) {
		$_POST['repeat_count'] = $bad;
	}
	run( 'handle_create (a rule with a ' . $label . ' count)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
	$outcomes[ $label ] = flashed( 20, 'call' );
}
$_POST = array( 'mentor' => 20, 'date' => '2027-09-07', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'week', 'repeat_count' => '16', 'more_dates' => array( '2028-01-04' ) );
run( 'handle_create (sixteen by rule and one box)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$outcomes['many'] = flashed( 20, 'call' );
$_POST            = array( 'mentor' => 20, 'date' => '2027-09-07', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'daily', 'repeat_count' => '3' );
run( 'handle_create (a rule the form does not offer)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$outcomes['unknown'] = flashed( 20, 'call' );

check( '"Does not repeat" ignores a stray count and plans one; a rule with a missing, low, high or non-numeric count, sixteen by rule plus a box, and a rule the form does not offer each refuse the form and create nothing',
    array( $stray, $outcomes, count( $GLOBALS['posts'] ) - $posts_before ),
    array(
        array( 'session-created', 1 ),
        array( 'missing' => array( 'series-count', 16 ), 'low' => array( 'series-count', 16 ), 'high' => array( 'series-count', 16 ), 'text' => array( 'series-count', 16 ), 'many' => array( 'series-many', 16 ), 'unknown' => 'error' ),
        0,
    ) );

// The cap admits what it names: seventeen is refused above, and a rule that reaches sixteen on its
// own still plans every one of them (the final review of 1.109.0).
$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => '2028-03-07', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'week', 'repeat_count' => '16' );
run( 'handle_create (every week, sixteen sessions)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$sixteen = created_since( $posts_before );
check( 'a rule of sixteen with no boxes plans sixteen sessions in one series, the last of them fifteen weeks on',
    array(
        flashed( 20, 'call' ),
        count( $sixteen ),
        array_unique( array_map( 'WPCPM_Group_Sessions::series_of', $sixteen ) ),
        wp_date( 'Y-m-d', (int) get_post_meta( $sixteen[15], WPCPM_Mentor_Calls::META_START, true ), $mentor_zone ),
    ),
    array( array( 'series-planned', 16 ), 16, array( $sixteen[0] ), '2028-06-20' ) );

// A rule and a box in one press: the box's date joins the rule's dates, and a box that repeats one
// of them is the same "given twice" refusal a list of boxes gets (the final review of 1.109.0).
$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => '2028-09-05', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'week', 'repeat_count' => '3', 'more_dates' => array( '2028-09-27' ) );
run( 'handle_create (a rule of three and one odd box)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$mixed      = created_since( $posts_before );
$mixed_flag = flashed( 20, 'call' );

$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => '2028-10-03', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'week', 'repeat_count' => '3', 'more_dates' => array( '2028-10-10' ) );
run( 'handle_create (a box repeating one of the rule\'s dates)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
check( 'a rule of three and an odd box plan one series of four, and a box repeating a rule\'s date is refused as a date given twice with nothing created',
    array(
        $mixed_flag,
        count( $mixed ),
        array_unique( array_map( 'WPCPM_Group_Sessions::series_of', $mixed ) ),
        array_map( function ( $id ) use ( $mentor_zone ) { return wp_date( 'Y-m-d', (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_START, true ), $mentor_zone ); }, $mixed ),
        flashed( 20, 'call' ),
        count( $GLOBALS['posts'] ) - $posts_before,
    ),
    array(
        array( 'series-planned', 4 ),
        4,
        array( $mixed[0] ),
        array( '2028-09-05', '2028-09-12', '2028-09-19', '2028-09-27' ),
        array( 'series-twice', '2028-10-10' ),
        0,
    ) );

// A start time the clocks jump over is the only `when` `plan_dates()` can still answer by the time
// the handler calls it, since every date has passed `date_string()` first: it has to say so, and
// name the date when the press carried a list (the final review of 1.109.0). The fixture mentor
// keeps UTC, which has no such hour, so the zone is borrowed for these two presses and given back.
$mentor_settings          = $GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ];
$gap_settings             = $mentor_settings;
$gap_settings['timezone'] = 'Europe/Riga';

$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ] = $gap_settings;

$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => '2027-03-28', 'time' => '03:30', 'minutes' => 60, 'capacity' => 6 );
run( 'handle_create (one date, at an hour the clocks jump over)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$gap_alone = flashed( 20, 'call' );

// Four weeks from a week before: the second date is the day summer time begins in that zone.
$_POST = array( 'mentor' => 20, 'date' => '2027-03-21', 'time' => '03:30', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'week', 'repeat_count' => '4' );
run( 'handle_create (a rule whose second date has no such hour)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$gap_series = flashed( 20, 'call' );

$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ] = $mentor_settings;

check( 'an hour the clocks jump over is refused for what it is, named by date in a series, with nothing created and the mentor\'s zone as it was',
    array( $gap_alone, $gap_series, count( $GLOBALS['posts'] ) - $posts_before, WPCPM_Mentor_Availability::get( 20 )['timezone'] ),
    array( 'session-gap', array( 'series-when', '2027-03-28' ), 0, 'UTC' ) );

// 1.109.1: a change to one session takes the same two rules as planning one. A start the clocks
// jump over is refused rather than saved an hour late, and the diary is read and the session moved
// under the mentor's booking lock, as planning is since 1.108.0 (the re-review of 1.109.0's wave).
$moved                   = $series[2];
$moved_meta              = $GLOBALS['pmeta'][ $moved ];
$GLOBALS['query_result'] = array();
$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ] = $gap_settings;

$_POST = array( 'session' => $moved, 'date' => '2027-03-28', 'time' => '03:30', 'minutes' => 60, 'capacity' => 6, 'topic' => 'Release cycle' );
run( 'handle_edit (onto an hour the clocks jump over)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
$edit_gap = array( flashed( 20, 'call' ), (int) get_post_meta( $moved, WPCPM_Mentor_Calls::META_START, true ) === (int) $moved_meta[ WPCPM_Mentor_Calls::META_START ] );

$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ] = $mentor_settings;

WPCPM_Mentor_Calls::lock_for( 20 );
$_POST = array( 'session' => $moved, 'date' => '2027-04-20', 'time' => '11:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'Release cycle' );
run( 'handle_edit (the mentor\'s lock is held)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
$edit_busy = array( flashed( 20, 'call' ), (int) get_post_meta( $moved, WPCPM_Mentor_Calls::META_START, true ) === (int) $moved_meta[ WPCPM_Mentor_Calls::META_START ] );
WPCPM_Mentor_Calls::unlock_for( 20 );

run( 'handle_edit (the same change once the lock is free)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
$edit_done = array( flashed( 20, 'call' ), wp_date( 'Y-m-d H:i', (int) get_post_meta( $moved, WPCPM_Mentor_Calls::META_START, true ), $mentor_zone ), get_option( 'wpcpm_call_lock_20', false ) );

$GLOBALS['pmeta'][ $moved ] = $moved_meta;

check( 'a change onto an hour the clocks jump over is refused and the session stays; a held lock refuses the change; the same change lands once the lock is free and leaves it released',
    array( $edit_gap, $edit_busy, $edit_done ),
    array( array( 'session-gap', true ), array( 'busy', true ), array( 'session-updated', '2027-04-20 11:00', false ) ) );

ob_start();
WPCPM_Group_Sessions::render_mentor_planner( $GLOBALS['users'][20] );
$planner = ob_get_clean();
check( 'the form offers the five repeat rules and a count box from 2 to 16, described by its hint',
    array(
        substr_count( $planner, '<select id="wpcpm-session-repeat" name="repeat">' ),
        false !== strpos( $planner, '<option value="">Does not repeat</option>' ),
        false !== strpos( $planner, '<option value="week">Every week</option>' ),
        false !== strpos( $planner, '<option value="2weeks">Every two weeks</option>' ),
        false !== strpos( $planner, '<option value="4weeks">Every four weeks</option>' ),
        false !== strpos( $planner, '<option value="month">Every month</option>' ),
        substr_count( $planner, 'name="repeat_count" min="2" max="16" step="1" aria-describedby="wpcpm-sessions-repeat-hint" data-wpcpm-needs="repeat"' ),
        substr_count( $planner, 'id="wpcpm-sessions-repeat-hint"' ),
        false !== strpos( $planner, 'Counting the first date. Up to 16. The box opens once a rule is picked.' ),
    ),
    array( 1, true, true, true, true, true, 1, 1, true ) );

$GLOBALS['query_result'] = array();

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

echo "\n=== WPCPM_Institutions ===\n";

$institutions = new WPCPM_Institutions();

$GLOBALS['uid']  = 1;
$GLOBALS['caps'] = true;
$_POST           = array();

run( 'handle_sync (manager)', array( $institutions, 'handle_sync' ) );
run( 'handle_cancel (manager)', array( $institutions, 'handle_cancel' ) );
run( 'handle_probe (manager)', array( $institutions, 'handle_probe' ) );

// Capability before nonce: somebody without it meets wp_die(), not a nonce screen.
$GLOBALS['caps'] = false;

run( 'handle_sync (no capability)', array( $institutions, 'handle_sync' ) );
run( 'handle_cancel (no capability)', array( $institutions, 'handle_cancel' ) );
run( 'handle_probe (no capability)', array( $institutions, 'handle_probe' ) );

$GLOBALS['caps'] = true;

echo "\n=== WPCPM_Sponsors ===\n";

$sponsors = new WPCPM_Sponsors();

$GLOBALS['uid']  = 1;
$GLOBALS['caps'] = true;
$_POST           = array( 'wpcpm_sponsor' => 'recSPONSOR0000001' );

run( 'handle_provision (manager, sponsor not yet indexed)', array( $sponsors, 'handle_provision' ) );

$_POST = array( 'wpcpm_sponsor' => 'recSPONSOR0000001', 'wpcpm_op' => 'eat' );

run( 'handle_members (manager, an op that is not one)', array( $sponsors, 'handle_members' ) );

// Capability before nonce: somebody without it meets wp_die(), not a nonce screen.
$GLOBALS['caps'] = false;

run( 'handle_provision (no capability)', array( $sponsors, 'handle_provision' ) );
run( 'handle_members (no capability)', array( $sponsors, 'handle_members' ) );

$GLOBALS['caps'] = true;

echo "\n=== The Sponsors module's offers, codes, claims and usage (Phase S2) ===\n";

// None of the nine was in this net. They all end at a refusal here, because the sponsors index
// is empty in this fixture: what is being proved is that each one *reaches* its redirect or its
// wp_die() rather than fatally on the way (queued item E).
$GLOBALS['uid']  = 1;
$GLOBALS['caps'] = true;
$_POST           = array( 'wpcpm_sponsor' => 'recSPONSOR0000001', 'wpcpm_offer' => 0, 'wpcpm_title' => 'An offer' );

run( 'handle_save (sponsor not yet indexed)', array( 'WPCPM_Sponsor_Offers', 'handle_save' ) );
run( 'handle_state (sponsor not yet indexed)', array( 'WPCPM_Sponsor_Offers', 'handle_state' ) );
run( 'handle_codes_add (sponsor not yet indexed)', array( 'WPCPM_Sponsor_Offers', 'handle_codes_add' ) );
run( 'handle_codes_void (sponsor not yet indexed)', array( 'WPCPM_Sponsor_Offers', 'handle_codes_void' ) );
run( 'handle_export (sponsor not yet indexed)', array( 'WPCPM_Sponsor_Usage', 'handle_export' ) );
run( 'handle_seed (sponsor not yet indexed)', array( $sponsors, 'handle_seed' ) );

$_POST = array( 'wpcpm_offer' => 0, 'wpcpm_user' => 30 );

run( 'handle_claim_void (no such offer)', array( $sponsors, 'handle_claim_void' ) );

$GLOBALS['uid']  = 30;
$GLOBALS['caps'] = false;
$_POST           = array( 'wpcpm_offer' => 0 );

run( 'handle_claim (student, no such offer)', array( 'WPCPM_Sponsor_Tools', 'handle_claim' ) );
run( 'handle_problem (student, no such offer)', array( 'WPCPM_Sponsor_Tools', 'handle_problem' ) );

$GLOBALS['caps'] = true;

echo "\n=== WPCPM_Sponsor_Application (Phase S5) ===\n";

// The public submit as a stranger, with the form switched off (the fixture's settings hold no
// switch), which is the shortest path to its redirect; then the six decisions with no such
// application, and one without the capability. Each has to reach a redirect or a wp_die().
$GLOBALS['uid']  = 0;
$GLOBALS['caps'] = false;
$_POST           = array();

run( 'handle_submit (stranger, form switched off)', array( 'WPCPM_Sponsor_Application', 'handle_submit' ) );

$GLOBALS['uid']  = 1;
$GLOBALS['caps'] = true;
$_POST           = array( WPCPM_Sponsor_Application::FIELD_APPLICATION => 0 );

foreach ( array( 'handle_approve', 'handle_info', 'handle_reject', 'handle_spam', 'handle_reopen', 'handle_purge' ) as $decision ) {
	run( $decision . ' (manager, no such application)', array( 'WPCPM_Sponsor_Application', $decision ) );
}

// Capability before nonce: somebody without it meets wp_die(), not a nonce screen.
$GLOBALS['caps'] = false;

run( 'handle_approve (no capability)', array( 'WPCPM_Sponsor_Application', 'handle_approve' ) );

$GLOBALS['caps'] = true;

echo "\n=== WPCPM_Duplicate_Finder (1.102.0) ===\n";

// Scan now with Airtable not connected (the fixture's settings hold no token), Cancel with no scan
// running, and Delete with nothing ticked while deleting is switched off: the shortest path to
// each redirect. Then Delete without the capability, which meets wp_die() before its nonce.
$finder = new WPCPM_Duplicate_Finder();
$_POST  = array();

run( 'handle_scan (Airtable not connected)', array( $finder, 'handle_scan' ) );
run( 'handle_cancel (no scan running)', array( $finder, 'handle_cancel' ) );
run( 'handle_delete (nothing ticked, switched off)', array( $finder, 'handle_delete' ) );

$GLOBALS['caps'] = false;

run( 'handle_delete (no capability)', array( $finder, 'handle_delete' ) );

$GLOBALS['caps'] = true;

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL HANDLERS REACHED A NORMAL OUTCOME\n" );
exit( $fail ? 1 : 0 );
