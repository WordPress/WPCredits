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
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );

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
function checked( $a, $b = true, $e = true ) { $r = (string) $a === (string) $b ? " checked='checked'" : ''; if ( $e ) { echo $r; } return $r; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_html_class( $s ) { return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $s ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
// Valid UTF-8 comes back as it went in, as core's does; the request reader's column names need it.
function wp_check_invalid_utf8( $s, $strip = false ) { return (string) $s; }
function wp_unslash( $v ) { return $v; }
// Slashing is not modeled here, either way: `update_post_meta()` below keeps what it is handed.
function wp_slash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function wp_kses_post( $s ) { return $s; }
// The two the Settings screen needs as well, drawn whole for the BUILDER-3 check below.
function wp_kses( $s, $allowed = array() ) { return $s; }
function submit_button( $text = null ) { echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html( (string) $text ) . '</button></p>'; }
function wp_json_encode( $v ) { return json_encode( $v ); }
// A JSON answer ends the request as core's does, recorded rather than echoed: what was answered, and
// with which status, for the background move's checks (BUILDER-4).
function wp_send_json_success( $data = null, $status = null ) { $GLOBALS['json_sent'] = array( 'success' => true, 'data' => $data, 'status' => $status ); throw new DieSignal( 'json' ); }
function wp_send_json_error( $data = null, $status = null ) { $GLOBALS['json_sent'] = array( 'success' => false, 'data' => $data, 'status' => $status ); throw new DieSignal( 'json' ); }
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
/**
 * Core's two forms: `add_query_arg( $args, $url )` and `add_query_arg( $key, $value, $url )`. This
 * stand-in knew the first alone, so a handler that names its screen the second way was sent to the
 * bare value, "4040" (the fix round of TESTS-DOCS-2). The array form's values are encoded as they
 * always were here; the key and value form inserts its value as core does, unencoded; and nothing
 * to add leaves the address as it was, as core's does, rather than ending it in "&".
 *
 * @param mixed ...$args The arguments, in either form.
 * @return string
 */
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$query = $args[0] ? http_build_query( $args[0] ) : '';
		$u     = (string) ( $args[1] ?? '' );
	} else {
		$query = (string) $args[0] . '=' . (string) ( $args[1] ?? '' );
		$u     = (string) ( $args[2] ?? '' );
	}

	if ( '' === $query ) {
		return $u;
	}

	$sep = false === strpos( $u, '?' ) ? '?' : '&';
	return $u . $sep . $query;
}
function remove_query_arg( $k, $u ) { return $u; }
function wp_get_referer() { return $GLOBALS['referer'] ?? false; }
function wp_safe_redirect( $u ) { throw new RedirectSignal( $u ); }
function wp_die( $m = '', $c = 0 ) { throw new DieSignal( is_string( $m ) ? $m : 'died' ); }
// A nonce that fails on demand, dying with WordPress's own sentence, so a press made without the
// right shows whether the handler asked about the right first (the deep check of 1.109.1,
// SESSIONS-14 and SURFACES-4): asked second, the person meets the nonce screen instead.
function check_admin_referer( $a = '', $q = '' ) {
	if ( ! empty( $GLOBALS['nonce_fails'] ) ) {
		throw new DieSignal( 'The link you followed has expired.' );
	}

	return true;
}
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
/**
 * `get_option()`, with a one-shot hook on reading a mentor's booking lock.
 *
 * `$GLOBALS['on_lock_read']` runs once, at the instant a press reads the lock key it is about to
 * take: what another request did just before this one took the lock. That is the interleaving the
 * deep check of 1.109.1 proved its races with (SESSIONS-5, SESSIONS-9), and the only way a suite
 * that runs one request at a time can show that a handler reads under the lock what it decides on.
 *
 * @param string $k Option name.
 * @param mixed  $d Default.
 * @return mixed
 */
function get_option( $k, $d = false ) {
	if ( 0 === strpos( (string) $k, 'wpcpm_call_lock_' ) && isset( $GLOBALS['on_lock_read'] ) ) {
		$hook = $GLOBALS['on_lock_read'];
		unset( $GLOBALS['on_lock_read'] );
		$hook();
	}

	return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d;
}
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
// Trashing moves the post out of `private`, as WordPress does, so a reader that asks for the status
// sees the cancellation (the deep check of 1.109.1, SESSIONS-13: a no-op here let every reader pass).
function wp_trash_post( $id ) { if ( isset( $GLOBALS['posts'][ (int) $id ] ) ) { $GLOBALS['posts'][ (int) $id ]->post_status = 'trash'; } return true; }
function get_post_status( $p = null ) { $post = get_post( is_object( $p ) ? $p->ID : $p ); return $post ? $post->post_status : false; }
// The statuses core registers, which the Track Builder's store asks for when it lists every track.
function get_post_stati() { return array( 'publish' => 'publish', 'future' => 'future', 'draft' => 'draft', 'pending' => 'pending', 'private' => 'private', 'trash' => 'trash', 'auto-draft' => 'auto-draft', 'inherit' => 'inherit' ); }
/**
 * `wp_cache_delete()`, recording what was dropped and which booking locks were held at the time.
 *
 * WordPress answers a post and its meta from the copy it read earlier in the request, so a read
 * under the lock is only a read under the lock once that copy is dropped (SESSIONS-5, SESSIONS-9).
 * The harness keeps no cache; what it can show is that the copy was dropped while the lock was held.
 *
 * @param mixed  $key   Cache key.
 * @param string $group Cache group.
 * @return bool
 */
function wp_cache_delete( $key, $group = '' ) {
	$held = array();

	foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
		if ( 0 === strpos( (string) $name, 'wpcpm_call_lock_' ) ) {
			$held[] = $name;
		}
	}

	$GLOBALS['cache_dropped'][] = array( (int) $key, (string) $group, $held );

	return true;
}
function wp_update_post( $a, $err = false ) {
	$id = (int) ( $a['ID'] ?? 0 );
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) { return 0; }
	foreach ( array( 'post_content', 'post_title', 'post_status' ) as $field ) {
		if ( isset( $a[ $field ] ) ) { $GLOBALS['posts'][ $id ]->$field = $a[ $field ]; }
	}
	return $id;
}
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ] ); return true; }
// A read of every row answers a list, as WordPress does: one row written by `update_post_meta()` is a
// list of one, and a key never written an empty list. Answering the bare value made a one-to-one
// call's student no attendee at all, so its cancellation reached the mentor alone (HOTFIX-2).
function get_post_meta( $id, $k, $single = false ) {
	$v    = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? '';
	$rows = ! empty( $GLOBALS['pmeta_rows'][ (int) $id ][ $k ] );

	if ( $single ) {
		return ( $rows && is_array( $v ) ) ? ( $v ? $v[0] : '' ) : $v;
	}

	if ( $rows ) {
		return is_array( $v ) ? $v : array( $v );
	}

	return '' === $v ? array() : array( $v );
}
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
// Repeated rows, as WordPress keeps them: the attendee list of a group session is one row a student.
function add_post_meta( $id, $k, $v, $unique = false ) { $rows = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? array(); $rows = is_array( $rows ) ? $rows : array(); $rows[] = $v; $GLOBALS['pmeta'][ (int) $id ][ $k ] = $rows; $GLOBALS['pmeta_rows'][ (int) $id ][ $k ] = true; return true; }
// When a call was booked: now, unless a check says otherwise in `$GLOBALS['booked_at']`, as the
// reminder sweep's checks do - a call booked inside the reminder window is not reminded at all.
function get_post_time( $f, $gmt = false, $p = null ) { return ( is_object( $p ) && isset( $GLOBALS['booked_at'][ $p->ID ] ) ) ? $GLOBALS['booked_at'][ $p->ID ] : time(); }
// Repeated rows go one at a time, the others staying, as WordPress deletes them: taking one student
// off a session must not take the rest with them.
function delete_post_meta( $id, $k, $v = '' ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? null;

	if ( null === $rows ) {
		return false;
	}

	if ( '' === $v || ! is_array( $rows ) ) {
		if ( '' === $v || (string) $rows === (string) $v ) {
			unset( $GLOBALS['pmeta'][ (int) $id ][ $k ], $GLOBALS['pmeta_rows'][ (int) $id ][ $k ] );
		}

		return true;
	}

	$GLOBALS['pmeta'][ (int) $id ][ $k ] = array_values( array_filter( $rows, function ( $row ) use ( $v ) { return (string) $row !== (string) $v; } ) );

	return true;
}
function wp_mail( $to, $subj, $body, $headers = array(), $attachments = array() ) {
	// What each attachment holds at send time, since the builder removes the file right after.
	$contents = array();
	foreach ( (array) $attachments as $path ) {
		$contents[ $path ] = is_file( $path ) ? (string) file_get_contents( $path ) : '';
	}
	$GLOBALS['mail'][] = compact( 'to', 'subj', 'body', 'headers', 'attachments', 'contents' );

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
 *
 * The temporary directory is this run's own, made the first time it is asked for and removed
 * with what it holds when the run ends. `wp_generate_password()` below answers the same
 * characters every time, so every calendar file lands in one directory named after them: in
 * the system temp directory, which every run at the same time shares, a second run of this
 * suite wrote, read and deleted the same files and the calendar checks read the other run's
 * or none (the final fix wave, item 1, found by its proof).
 */
function get_temp_dir() {
	$dir = sys_get_temp_dir() . '/wpcpm-handlers-tmp-' . getmypid() . '/';

	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0700, true );
		register_shutdown_function( 'remove_temp_tree', $dir );
	}

	return $dir;
}

/**
 * Remove a directory and everything in it.
 *
 * @param string $dir The directory.
 */
function remove_temp_tree( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $entry ) {
		if ( $entry->isDir() ) {
			rmdir( $entry->getPathname() );
		} else {
			unlink( $entry->getPathname() );
		}
	}

	rmdir( $dir );
}
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
	'status' => 'In Sensei', 'program' => 'In Sensei', 'slack' => '@student-one', 'team' => 'Core',
	'website' => 'sam.blog', 'profile' => 'https://profiles.wordpress.org/student-one/', 'username' => 'student-one',
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
	// What the press died with, for the checks that read it; '' when it did not die. Where it was
	// sent, likewise; '' when it was not.
	$GLOBALS['died_with']     = '';
	$GLOBALS['redirected_to'] = '';
	try {
		$fn();
		echo "FAIL $label - returned without redirecting or dying\n";
		$fail++;
	} catch ( RedirectSignal $e ) {
		$GLOBALS['redirected_to'] = $e->getMessage();
		printf( "ok   %-46s redirect -> %s\n", $label, $e->getMessage() );
	} catch ( DieSignal $e ) {
		$GLOBALS['died_with'] = $e->getMessage();
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

/**
 * A calendar file as the plugin writes one around one event, unfolded and without its stamp: what a
 * sent file is compared with line for line (the deep check of 1.109.1, HOTFIX-2).
 *
 * @param string   $method `REQUEST` or `CANCEL`.
 * @param string[] $event  The event's lines between `BEGIN:VEVENT` and `END:VEVENT`, the stamp left out.
 * @return string
 */
function expected_ics( $method, array $event ) {
	$head = array( 'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//WordPress Credits Program//WPCredits Program Manager//EN', 'CALSCALE:GREGORIAN', 'METHOD:' . $method, 'BEGIN:VEVENT' );

	return implode( "\r\n", array_merge( $head, $event, array( 'END:VEVENT', 'END:VCALENDAR' ) ) ) . "\r\n";
}

/**
 * A sent calendar file unfolded and without its stamp, the one line that changes by the second.
 *
 * @param string $ics The file as it was sent.
 * @return string
 */
function as_sent( $ics ) {
	return (string) preg_replace( '/^DTSTAMP:[0-9]{8}T[0-9]{6}Z\r\n/m', '', str_replace( "\r\n ", '', (string) $ics ) );
}

/**
 * The attendee line a calendar file writes for one person.
 *
 * @param string $name  Their name.
 * @param string $email Their address.
 * @return string
 */
function attendee_line( $name, $email ) {
	return 'ATTENDEE;CN="' . $name . '";ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=FALSE:mailto:' . $email;
}

/**
 * A Tuesday some weeks after the first Tuesday more than a week from today, as `Y-m-d`.
 *
 * Every session this suite plans is dated from it, and so is every date a check expects back, so
 * the suite answers the same whatever day it runs: `handle_create()` refuses a start that has
 * passed, and dates written out would turn the suite red as each one went by (the deep check of
 * 1.109.1, TESTS-DOCS-6). More than a week ahead, so the soonest session is days away; a Tuesday,
 * inside the fixture mentor's weekday hours, because the checks keep the shapes they were written
 * with - a series a week apart, a box a day off the rule's weekday. Fixed for the whole run, so a
 * run that crosses midnight cannot move the dates between a press and the check that reads it.
 * The fixture mentor keeps UTC, and so does this.
 *
 * @param int $weeks Weeks after the first Tuesday.
 * @param int $days  Days on top, for a date meant to fall off the weekday.
 * @return string
 */
function weeks_on( $weeks, $days = 0 ) {
	static $first = null;

	if ( null === $first ) {
		$first = ( new DateTimeImmutable( 'today', new DateTimeZone( 'UTC' ) ) )->modify( '+7 days' )->modify( 'next tuesday' );
	}

	return $first->modify( '+' . ( 7 * (int) $weeks + (int) $days ) . ' days' )->format( 'Y-m-d' );
}

/**
 * A date as the session lists print it: WordPress's default date format, which the harness keeps.
 *
 * @param string $ymd The date, `Y-m-d`.
 * @return string
 */
function long_date( $ymd ) {
	return gmdate( 'F j, Y', strtotime( $ymd . ' 00:00:00 UTC' ) );
}

/**
 * The next day summer time begins in a zone, more than a week from today, and a start time the
 * clocks jump over on it.
 *
 * Read from the zone's own transitions rather than written out, for the reason `weeks_on()`
 * gives (TESTS-DOCS-6). More than a week ahead because one check plans a series from a week
 * before that day, and its first date has to be in the future too.
 *
 * @param string $zone_name A zone that keeps summer time.
 * @return string[] The date, `Y-m-d`, and a time half an hour into the hour that does not exist,
 *                  `H:i`; two empty strings when the zone moves no clock forward within the year.
 */
function summer_gap( $zone_name ) {
	$from  = time() + 8 * DAY_IN_SECONDS;
	$prior = null;

	foreach ( ( new DateTimeZone( $zone_name ) )->getTransitions( $from, $from + 400 * DAY_IN_SECONDS ) as $transition ) {
		// Clocks go forward where the offset grows. Up to that instant the wall clock reads the old
		// offset, so the hour that is skipped starts there.
		if ( null !== $prior && $transition['offset'] > $prior['offset'] ) {
			$wall = $transition['ts'] + $prior['offset'];

			return array( gmdate( 'Y-m-d', $wall ), gmdate( 'H:i', $wall + 30 * MINUTE_IN_SECONDS ) );
		}

		$prior = $transition;
	}

	return array( '', '' );
}

// No date the calendar has yet to reach is written into this suite. `handle_create()` refuses a
// start that has passed, so a session planned on a date written out turns the suite red the day
// that date goes by, with nothing wrong in the plugin: the first of them would have done it on
// 5 January 2027 (the deep check of 1.109.1, TESTS-DOCS-6). A date already past stays past, so
// the checks that want one may still write it out.
preg_match_all( '/(?<![0-9])(20[0-9]{2}-[0-9]{2}-[0-9]{2})(?![0-9])/', (string) file_get_contents( __FILE__ ), $written_dates );
check( 'every date still to come is counted from today, none written into this suite',
    array_values( array_unique( array_filter( $written_dates[1], function ( $ymd ) { return $ymd >= gmdate( 'Y-m-d' ); } ) ) ),
    array() );

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

// Read now, and not only after the second press below: left pending, this press's message stood in
// for whatever the second press did, so that check could not fail (the fix round of SESSIONS-5).
check( 'a one-to-one cancellation is flashed as canceled', flashed( 1, 'call' ), 'cancelled' );

// HOTFIX-2: a one-to-one cancellation's two files, line for line. Canceled by a program manager, so
// both the mentor and the student are written to, and each file names the two of them.
$one_to_one = array();

foreach ( $GLOBALS['mail'] as $index => $sent ) {
	$one_to_one[ $sent['to'] ] = as_sent( file_of( $index ) );
}

$one_to_one_file = expected_ics(
	'CANCEL',
	array(
		'UID:' . WPCPM_ICS::uid( 500 ),
		'SEQUENCE:1',
		'DTSTART:' . gmdate( 'Ymd\THis\Z', $GLOBALS['pmeta'][500][ WPCPM_Mentor_Calls::META_START ] ),
		'DTEND:' . gmdate( 'Ymd\THis\Z', $GLOBALS['pmeta'][500][ WPCPM_Mentor_Calls::META_END ] ),
		'SUMMARY:Mentor call: Mia Mentor and Sam Student',
		'DESCRIPTION:A mentor call on the WordPress Credits Program with Mia Mentor.',
		'STATUS:CANCELLED',
		'TRANSP:OPAQUE',
		'ORGANIZER;CN="Mia Mentor":mailto:mentor@example.test',
		attendee_line( 'Mia Mentor', 'mentor@example.test' ),
		attendee_line( 'Sam Student', 'student@example.test' ),
	)
);

check( 'a one-to-one cancellation sends the mentor and the student the same file, line for line, naming the two of them',
    $one_to_one,
    array( 'mentor@example.test' => $one_to_one_file, 'student@example.test' => $one_to_one_file ) );

// The same guard as a session's second Cancel (the fix round of SESSIONS-5): the call is in the trash,
// so nobody is written to again, and the one who pressed is told it is canceled.
run( 'handle_cancel (the same call again)', array( 'WPCPM_Mentor_Calls', 'handle_cancel' ) );
check( 'a second Cancel of a one-to-one call already canceled writes to nobody and says it is canceled',
    array( flashed( 1, 'call' ), count( $GLOBALS['mail'] ) ),
    array( 'cancelled', 0 ) );

$_POST = array( 'call' => 4242 );
run( 'handle_cancel (call that does not exist)', array( 'WPCPM_Mentor_Calls', 'handle_cancel' ) );

// 1.109.2: a group session is the mentor's or a manager's to cancel, never a student's, and a
// cancellation's calendar file names each student alone (the deep check of 1.109.1, SESSIONS-1
// and SESSIONS-2). Sam joined first, Pat second.
$GLOBALS['users'][41]        = new WP_User( 41, 'Pat Student', 'pat@example.test' );
$GLOBALS['users'][41]->roles = array( WPCPM_Roles::ROLE_STUDENT );
$GLOBALS['posts'][600]            = new WP_Post();
$GLOBALS['posts'][600]->ID        = 600;
$GLOBALS['posts'][600]->post_type = WPCPM_Mentor_Calls::POST_TYPE;
$GLOBALS['pmeta'][600]            = array(
	WPCPM_Mentor_Calls::META_MENTOR => 20, WPCPM_Mentor_Calls::META_CAPACITY => 6,
	WPCPM_Mentor_Calls::META_START => time() + 172800, WPCPM_Mentor_Calls::META_END => time() + 176400,
	WPCPM_Mentor_Calls::META_ZONE => 'UTC',
);
WPCPM_Mentor_Calls::add_attendee( 600, 30, $student_rec );
WPCPM_Mentor_Calls::add_attendee( 600, 41, 'recSTUDENT4100000' );

$GLOBALS['caps'] = false;
check( 'a group session may be canceled by its mentor and by nobody who joined it, first or second; a one-to-one call still by its student',
    array(
        WPCPM_Mentor_Calls::user_can_cancel( $GLOBALS['posts'][600], $GLOBALS['users'][30] ),
        WPCPM_Mentor_Calls::user_can_cancel( $GLOBALS['posts'][600], $GLOBALS['users'][41] ),
        WPCPM_Mentor_Calls::user_can_cancel( $GLOBALS['posts'][600], $GLOBALS['users'][20] ),
        WPCPM_Mentor_Calls::user_can_cancel( $GLOBALS['posts'][500], $GLOBALS['users'][30] ),
    ),
    array( false, false, true, true ) );

$GLOBALS['uid']  = 30;
$GLOBALS['mail'] = array();
$_POST           = array( 'call' => 600 );
run( 'handle_cancel (the first student on a group session)', array( 'WPCPM_Mentor_Calls', 'handle_cancel' ) );
check( 'the first student\'s press is refused and mails nobody',
    array( count( $GLOBALS['mail'] ), get_post_meta( 600, WPCPM_Mentor_Calls::META_CANCELLED_BY, true ) ),
    array( 0, '' ) );

$GLOBALS['uid'] = 20;
$_POST          = array( 'call' => 600 );
run( 'handle_cancel (the mentor cancels the group session)', array( 'WPCPM_Mentor_Calls', 'handle_cancel' ) );
$to_files = array();
foreach ( $GLOBALS['mail'] as $sent ) {
	$to_files[ $sent['to'] ] = reset( $sent['contents'] );
}
check( 'each student\'s cancellation file names that student and the mentor only, and the mentor is not written to about their own press',
    array(
        array_keys( $to_files ),
        false !== strpos( $to_files['pat@example.test'], 'mailto:pat@example.test' ),
        false === strpos( $to_files['pat@example.test'], 'student@example.test' ),
        false === strpos( $to_files['pat@example.test'], 'Sam Student' ),
        false !== strpos( $to_files['student@example.test'], 'mailto:student@example.test' ),
        false === strpos( $to_files['student@example.test'], 'pat@example.test' ),
        false !== strpos( $to_files['pat@example.test'], 'METHOD:CANCEL' ),
    ),
    array( array( 'student@example.test', 'pat@example.test' ), true, true, true, true, true, true ) );
// A session's Cancel is told in the session's words, not the call-and-slot sentence a one-to-one
// call gets, which the session's mail no longer uses either (the fix round 2 of SESSIONS-5).
check( 'a session\'s cancellation is flashed in the session\'s own words', flashed( 20, 'call' ), 'session-cancelled' );
// HOTFIX-2: the press of the second student to join, which the check above never made, and a
// manager's, the one press that writes to the mentor. Sam joined first and Pat second again.
session_fixture( 601, time() + 3 * DAY_IN_SECONDS );
WPCPM_Mentor_Calls::add_attendee( 601, 30, $student_rec );
WPCPM_Mentor_Calls::add_attendee( 601, 41, 'recSTUDENT4100000' );

$GLOBALS['uid'] = 41;
$_POST          = array( 'call' => 601 );
run( 'handle_cancel (the second student on a group session)', array( 'WPCPM_Mentor_Calls', 'handle_cancel' ) );
check( 'the second student\'s press is refused as the first student\'s is, and writes to nobody',
    array( $GLOBALS['died_with'], count( $GLOBALS['mail'] ), get_post_status( 601 ) ),
    array( 'You cannot cancel that call.', 0, 'private' ) );

$GLOBALS['uid']  = 1;
$GLOBALS['caps'] = true;
run( 'handle_cancel (a manager cancels the group session)', array( 'WPCPM_Mentor_Calls', 'handle_cancel' ) );
$by_manager    = array();
$manager_mails = array();

foreach ( $GLOBALS['mail'] as $index => $sent ) {
	$by_manager[ $sent['to'] ]    = as_sent( file_of( $index ) );
	$manager_mails[ $sent['to'] ] = $sent;
}

// The mail around those files was still a one-to-one call's: "The call on ... was canceled", an
// offer to book another time to the mentor and to each student, and the mentor's copy replying to
// the first student (the fix round of SESSIONS-12).
$all_bodies = implode( "\n", array_column( $manager_mails, 'body' ) );
check( 'a session\'s cancellation is worded as the session\'s, offers nobody another booking, and the mentor\'s copy has no one student to reply to',
    array(
        0 === strpos( $manager_mails['pat@example.test']['body'] ?? '', 'The group session on ' ),
        substr_count( $all_bodies, 'was canceled by a program manager.' ),
        substr_count( $all_bodies, 'The call on ' ),
        substr_count( $all_bodies, 'book another time' ),
        0 === strpos( $manager_mails['mentor@example.test']['subj'] ?? '', '[Test Site] Group session on ' ),
        $manager_mails['mentor@example.test']['headers'] ?? null,
        $manager_mails['pat@example.test']['headers'] ?? null,
    ),
    array( true, 3, 0, 0, true, array(), array( 'Reply-To: "Mia Mentor" <mentor@example.test>' ) ) );

/**
 * Session 601's cancellation file, naming the mentor and one student.
 *
 * @param string $name  The student it names.
 * @param string $email Their address.
 * @return string
 */
function cancel_601( $name, $email ) {
	return expected_ics(
		'CANCEL',
		array(
			'UID:' . WPCPM_ICS::uid( 601 ),
			'SEQUENCE:' . (int) get_post_meta( 601, WPCPM_Group_Sessions::META_REVISION, true ),
			'DTSTART:' . gmdate( 'Ymd\THis\Z', $GLOBALS['pmeta'][601][ WPCPM_Mentor_Calls::META_START ] ),
			'DTEND:' . gmdate( 'Ymd\THis\Z', $GLOBALS['pmeta'][601][ WPCPM_Mentor_Calls::META_END ] ),
			'SUMMARY:Group session with Mia Mentor',
			'DESCRIPTION:A group session on the WordPress Credits Program with Mia Mentor.',
			'STATUS:CANCELLED',
			'TRANSP:OPAQUE',
			'ORGANIZER;CN="Mia Mentor":mailto:mentor@example.test',
			attendee_line( 'Mia Mentor', 'mentor@example.test' ),
			attendee_line( $name, $email ),
		)
	);
}

check( 'a manager\'s cancellation writes to the mentor and both students: the mentor\'s file names the first student, whom the mentor knows, and each student\'s names that student alone, line for line',
    array( get_post_status( 601 ), $by_manager ),
    array(
        'trash',
        array(
            'mentor@example.test'  => cancel_601( 'Sam Student', 'student@example.test' ),
            'student@example.test' => cancel_601( 'Sam Student', 'student@example.test' ),
            'pat@example.test'     => cancel_601( 'Pat Student', 'pat@example.test' ),
        ),
    ) );

// The session and the second student were this block's alone.
unset( $GLOBALS['posts'][600], $GLOBALS['pmeta'][600], $GLOBALS['pmeta_rows'][600], $GLOBALS['posts'][601], $GLOBALS['pmeta'][601], $GLOBALS['pmeta_rows'][601], $GLOBALS['users'][41] );
$GLOBALS['mail'] = array();
$GLOBALS['caps'] = true;
$GLOBALS['uid']  = 1;

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
		'horizon' => 28, 'per_student' => 1, 'blocked' => weeks_on( 11 ) . "\n" . weeks_on( 11, 1 ), 'note' => 'Video link follows.',
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

$_POST = array( 'mentor' => 20, 'date' => weeks_on( 0 ), 'time' => '10:00', 'minutes' => 0, 'capacity' => 6 );
run( 'handle_create (no length)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );

$_POST = array( 'mentor' => 20, 'date' => weeks_on( 0 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 1 );
run( 'handle_create (capacity of one is a 1:1 call)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );

$_POST = array( 'mentor' => 20, 'date' => '2020-01-05', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6 );
run( 'handle_create (in the past)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );

$_POST = array( 'mentor' => 20, 'date' => weeks_on( 0 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6 );
run( 'handle_create (valid)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );

$_POST = array( 'mentor' => 30, 'date' => weeks_on( 0 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6 );
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
$_POST          = array( 'mentor' => 20, 'date' => weeks_on( 1 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6 );
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
$_POST          = array( 'mentor' => 20, 'date' => weeks_on( 4 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'Release cycle', 'more_dates' => array( weeks_on( 5 ), '', weeks_on( 6 ) ) );
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
    array( 3, 3, array( $series[0] ), array( weeks_on( 4 ) . ' 10:00', weeks_on( 5 ) . ' 10:00', weeks_on( 6 ) . ' 10:00' ) ) );

$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => weeks_on( 8 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( weeks_on( 9 ), weeks_on( 9 ) ) );
run( 'handle_create (a date given twice)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$twice = flashed( 20, 'call' );
$_POST = array( 'mentor' => 20, 'date' => weeks_on( 8 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( '2020-03-09' ) );
run( 'handle_create (a date that has passed)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$past = flashed( 20, 'call' );
$GLOBALS['query_result'] = array( $GLOBALS['posts'][ $series[2] ] );
$_POST                   = array( 'mentor' => 20, 'date' => weeks_on( 8 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( weeks_on( 6 ) ) );
run( 'handle_create (a date the mentor already holds)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$clash                   = flashed( 20, 'call' );
$GLOBALS['query_result'] = array();
check( 'a date given twice, a date that has passed and a clash each refuse the whole list naming the date, and nothing is created',
    array( $twice, $past, $clash, count( $GLOBALS['posts'] ) - $posts_before ),
    array( array( 'series-twice', weeks_on( 9 ) ), array( 'series-past', '2020-03-09' ), array( 'series-clash', weeks_on( 6 ) ), 0 ) );

// The diary is read and the sessions are written under the booking lock, so a booking on the same
// mentor cannot take one of the starts in between (the final review of 1.108.0).
$before_lock = count( $GLOBALS['posts'] );
WPCPM_Mentor_Calls::lock_for( 20 );
$_POST = array( 'mentor' => 20, 'date' => weeks_on( 13 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( weeks_on( 14 ) ) );
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
$_POST        = array( 'mentor' => 20, 'date' => weeks_on( 17 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( 'not-a-date' ) );
run( 'handle_create (a box that is not a date)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
check( 'a box holding something that is not a date refuses the list naming what it held, and creates nothing',
    array( flashed( 20, 'call' ), count( $GLOBALS['posts'] ) - $posts_before ),
    array( array( 'series-date', 'not-a-date' ), 0 ) );

// A crafted entry that is not text at all (`more_dates[0][]=x`) can only come from a tampered form:
// it is passed over without a warning, and the boxes that are text are read as before.
$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => weeks_on( 17 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( array( 'x' ), weeks_on( 18 ) ) );
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
        false !== strpos( $student_list, '<p class="wpcpm-sessions__series-heading"><strong>Release cycle</strong> <span class="wpcpm-sessions__series-span">3 sessions, ' . long_date( weeks_on( 4 ) ) . ' to ' . long_date( weeks_on( 6 ) ) . '</span></p>' ),
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
        false !== strpos( $mentor_panel, '3 sessions, ' . long_date( weeks_on( 4 ) ) . ' to ' . long_date( weeks_on( 6 ) ) ),
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
$_POST                   = array( 'mentor' => 20, 'date' => weeks_on( 17 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'Patch review', 'more_dates' => array( weeks_on( 18 ), weeks_on( 19 ) ) );
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
$_POST                   = array( 'mentor' => 20, 'date' => weeks_on( 21 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'Office hours', 'repeat' => 'week', 'repeat_count' => '8' );
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
    array( array( 'series-planned', 8 ), 8, 1, array_map( 'weeks_on', range( 21, 28 ) ) ) );

$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => weeks_on( 30 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => '', 'repeat_count' => '5' );
run( 'handle_create (does not repeat, a stray count)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$stray = array( flashed( 20, 'call' ), count( $GLOBALS['posts'] ) - $posts_before );

$posts_before = count( $GLOBALS['posts'] );
$outcomes     = array();
foreach ( array( 'missing' => null, 'low' => '1', 'high' => '17', 'text' => 'ten' ) as $label => $bad ) {
	$_POST = array( 'mentor' => 20, 'date' => weeks_on( 35 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => '2weeks' );
	if ( null !== $bad ) {
		$_POST['repeat_count'] = $bad;
	}
	run( 'handle_create (a rule with a ' . $label . ' count)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
	$outcomes[ $label ] = flashed( 20, 'call' );
}
$_POST = array( 'mentor' => 20, 'date' => weeks_on( 35 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'week', 'repeat_count' => '16', 'more_dates' => array( weeks_on( 52 ) ) );
run( 'handle_create (sixteen by rule and one box)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$outcomes['many'] = flashed( 20, 'call' );
$_POST            = array( 'mentor' => 20, 'date' => weeks_on( 35 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'daily', 'repeat_count' => '3' );
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
$_POST        = array( 'mentor' => 20, 'date' => weeks_on( 61 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'week', 'repeat_count' => '16' );
run( 'handle_create (every week, sixteen sessions)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$sixteen = created_since( $posts_before );
check( 'a rule of sixteen with no boxes plans sixteen sessions in one series, the last of them fifteen weeks on',
    array(
        flashed( 20, 'call' ),
        count( $sixteen ),
        array_unique( array_map( 'WPCPM_Group_Sessions::series_of', $sixteen ) ),
        wp_date( 'Y-m-d', (int) get_post_meta( $sixteen[15], WPCPM_Mentor_Calls::META_START, true ), $mentor_zone ),
    ),
    array( array( 'series-planned', 16 ), 16, array( $sixteen[0] ), weeks_on( 76 ) ) );

// A rule and a box in one press: the box's date joins the rule's dates, and a box that repeats one
// of them is the same "given twice" refusal a list of boxes gets (the final review of 1.109.0).
$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => weeks_on( 87 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'week', 'repeat_count' => '3', 'more_dates' => array( weeks_on( 90, 1 ) ) );
run( 'handle_create (a rule of three and one odd box)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$mixed      = created_since( $posts_before );
$mixed_flag = flashed( 20, 'call' );

$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => weeks_on( 91 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'week', 'repeat_count' => '3', 'more_dates' => array( weeks_on( 92 ) ) );
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
        array( weeks_on( 87 ), weeks_on( 88 ), weeks_on( 89 ), weeks_on( 90, 1 ) ),
        array( 'series-twice', weeks_on( 92 ) ),
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

list( $gap_date, $gap_time ) = summer_gap( 'Europe/Riga' );
$gap_week_before            = ( new DateTimeImmutable( $gap_date, new DateTimeZone( 'UTC' ) ) )->modify( '-7 days' )->format( 'Y-m-d' );
check( 'the zone the gap checks borrow moves its clocks forward within the year, so they have a day to plan on', '' !== $gap_date, true );

$posts_before = count( $GLOBALS['posts'] );
$_POST        = array( 'mentor' => 20, 'date' => $gap_date, 'time' => $gap_time, 'minutes' => 60, 'capacity' => 6 );
run( 'handle_create (one date, at an hour the clocks jump over)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$gap_alone = flashed( 20, 'call' );

// Four weeks from a week before: the second date is the day summer time begins in that zone.
$_POST = array( 'mentor' => 20, 'date' => $gap_week_before, 'time' => $gap_time, 'minutes' => 60, 'capacity' => 6, 'repeat' => 'week', 'repeat_count' => '4' );
run( 'handle_create (a rule whose second date has no such hour)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$gap_series = flashed( 20, 'call' );

$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ] = $mentor_settings;

check( 'an hour the clocks jump over is refused for what it is, named by date in a series, with nothing created and the mentor\'s zone as it was',
    array( $gap_alone, $gap_series, count( $GLOBALS['posts'] ) - $posts_before, WPCPM_Mentor_Availability::get( 20 )['timezone'] ),
    array( 'session-gap', array( 'series-when', $gap_date ), 0, 'UTC' ) );

// 1.109.1: a change to one session takes the same two rules as planning one. A start the clocks
// jump over is refused rather than saved an hour late, and the diary is read and the session moved
// under the mentor's booking lock, as planning is since 1.108.0 (the re-review of 1.109.0's wave).
$moved                   = $series[2];
$moved_meta              = $GLOBALS['pmeta'][ $moved ];
$GLOBALS['query_result'] = array();
$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ] = $gap_settings;

$_POST = array( 'session' => $moved, 'date' => $gap_date, 'time' => $gap_time, 'minutes' => 60, 'capacity' => 6, 'topic' => 'Release cycle' );
run( 'handle_edit (onto an hour the clocks jump over)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
$edit_gap = array( flashed( 20, 'call' ), (int) get_post_meta( $moved, WPCPM_Mentor_Calls::META_START, true ) === (int) $moved_meta[ WPCPM_Mentor_Calls::META_START ] );

$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ] = $mentor_settings;

WPCPM_Mentor_Calls::lock_for( 20 );
$_POST = array( 'session' => $moved, 'date' => weeks_on( 15 ), 'time' => '11:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'Release cycle' );
run( 'handle_edit (the mentor\'s lock is held)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
$edit_busy = array( flashed( 20, 'call' ), (int) get_post_meta( $moved, WPCPM_Mentor_Calls::META_START, true ) === (int) $moved_meta[ WPCPM_Mentor_Calls::META_START ] );
WPCPM_Mentor_Calls::unlock_for( 20 );

run( 'handle_edit (the same change once the lock is free)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
$edit_done = array( flashed( 20, 'call' ), wp_date( 'Y-m-d H:i', (int) get_post_meta( $moved, WPCPM_Mentor_Calls::META_START, true ), $mentor_zone ), get_option( 'wpcpm_call_lock_20', false ) );

$GLOBALS['pmeta'][ $moved ] = $moved_meta;

check( 'a change onto an hour the clocks jump over is refused and the session stays; a held lock refuses the change; the same change lands once the lock is free and leaves it released',
    array( $edit_gap, $edit_busy, $edit_done ),
    array( array( 'session-gap', true ), array( 'busy', true ), array( 'session-updated', weeks_on( 15 ) . ' 11:00', false ) ) );

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

/**
 * A group session of the fixture mentor's, written as `create_sessions()` writes one.
 *
 * Keyed by hand below 1000, so the post store never mints the same ID for something else.
 *
 * @param int $id       Post ID.
 * @param int $start    Start, a timestamp.
 * @param int $capacity Places.
 * @param int $minutes  Length.
 */
function session_fixture( $id, $start, $capacity = 6, $minutes = 60 ) {
	$GLOBALS['posts'][ $id ]              = new WP_Post();
	$GLOBALS['posts'][ $id ]->ID          = $id;
	$GLOBALS['posts'][ $id ]->post_type   = WPCPM_Mentor_Calls::POST_TYPE;
	$GLOBALS['posts'][ $id ]->post_status = 'private';
	$GLOBALS['pmeta'][ $id ]              = array(
		WPCPM_Mentor_Calls::META_MENTOR   => 20,
		WPCPM_Mentor_Calls::META_CAPACITY => $capacity,
		WPCPM_Mentor_Calls::META_START    => $start,
		WPCPM_Mentor_Calls::META_END      => $start + $minutes * MINUTE_IN_SECONDS,
		WPCPM_Mentor_Calls::META_ZONE     => 'UTC',
	);
	unset( $GLOBALS['pmeta_rows'][ $id ] );
}

/**
 * The calendar file one sent mail carried, or '' when there is no such mail or file.
 *
 * @param int $index Which mail of the last press, from 0.
 * @return string
 */
function file_of( $index ) {
	$contents = $GLOBALS['mail'][ $index ]['contents'] ?? array();

	return $contents ? (string) reset( $contents ) : '';
}

/**
 * The UIDs a calendar file names, in the order its events come.
 *
 * @param string $ics The file.
 * @return string[]
 */
function uids_in( $ics ) {
	preg_match_all( '/^UID:(\S+)\r$/m', (string) $ics, $found );

	return $found[1];
}

/**
 * Whether the last press dropped its copies of a post, the post and its meta, with exactly these
 * booking locks held as it did: a read under the lock comes from the table only once the copy made
 * before the lock is gone (SESSIONS-5, SESSIONS-9).
 *
 * @param int      $post_id The post.
 * @param string[] $held    The lock options held at the drop; none for the reminder sweep, which
 *                          takes no lock.
 * @return bool[] Whether the post's copy was dropped, and whether its meta's was.
 */
function dropped( $post_id, array $held ) {
	return array(
		in_array( array( (int) $post_id, 'posts', $held ), $GLOBALS['cache_dropped'], true ),
		in_array( array( (int) $post_id, 'post_meta', $held ), $GLOBALS['cache_dropped'], true ),
	);
}

echo "\n=== What a press decides on is read under the lock (SESSIONS-5, SESSIONS-9) ===\n";

// The deep check of 1.109.1, SESSIONS-5: a cancellation landing between the page and the lock put
// the student on the canceled session with an invitation and no cancellation, and the reminder
// sweep reminded everybody of it. SESSIONS-9: a join landing between the page and a change of the
// places left more students than places. Each press below meets the other request at the instant
// it reads the lock key, through `on_lock_read`.
$GLOBALS['uid']          = 30;
$GLOBALS['caps']         = false;
$GLOBALS['query_result'] = array();
$locked_at               = strtotime( weeks_on( 2 ) . ' 14:00:00 UTC' );

session_fixture( 700, $locked_at );
$GLOBALS['cache_dropped'] = array();
$GLOBALS['on_lock_read']  = function () {
	wp_trash_post( 700 );
};
$_POST = array( 'session' => 700 );
run( 'handle_join (canceled as the join takes the lock)', array( 'WPCPM_Group_Sessions', 'handle_join' ) );
check( 'a join whose session is canceled as it takes the lock puts nobody on it, sends no invitation, says the session is gone and leaves the lock released',
    array( flashed( 30, 'call' ), WPCPM_Group_Sessions::has_joined( 700, 30 ), count( $GLOBALS['mail'] ), get_option( 'wpcpm_call_lock_20', false ) ),
    array( 'session-gone', false, 0, false ) );
check( 'the join dropped its copy of the session while it held the lock, so what it read under the lock came from the table',
    array(
        in_array( array( 700, 'posts', array( 'wpcpm_call_lock_20' ) ), $GLOBALS['cache_dropped'], true ),
        in_array( array( 700, 'post_meta', array( 'wpcpm_call_lock_20' ) ), $GLOBALS['cache_dropped'], true ),
    ),
    array( true, true ) );

// Join all: the middle session of three is canceled as the press takes the lock. The list was read
// before it, as `series_members()` read it, so the canceled one is still in it.
foreach ( array( 701, 702, 703 ) as $week => $member ) {
	session_fixture( $member, strtotime( weeks_on( 3 + $week ) . ' 14:00:00 UTC' ) );
	$GLOBALS['pmeta'][ $member ][ WPCPM_Group_Sessions::META_SERIES ] = 701;
}
$GLOBALS['query_result'] = array( $GLOBALS['posts'][701], $GLOBALS['posts'][702], $GLOBALS['posts'][703] );
$GLOBALS['on_lock_read'] = function () {
	wp_trash_post( 702 );
};
$_POST                    = array( 'series' => 701 );
$GLOBALS['cache_dropped'] = array();
run( 'handle_join_series (a member canceled as it takes the lock)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );
$series_file = file_of( 1 );
check( 'Join all passes over a session canceled as it takes the lock: the student is put on the two that stand, the message counts those, the file names only them, and every session was read again from the table under the lock',
    array(
        flashed( 30, 'call' ),
        WPCPM_Group_Sessions::has_joined( 701, 30 ),
        WPCPM_Group_Sessions::has_joined( 702, 30 ),
        WPCPM_Group_Sessions::has_joined( 703, 30 ),
        uids_in( $series_file ),
        dropped( 701, array( 'wpcpm_call_lock_20' ) ),
        dropped( 702, array( 'wpcpm_call_lock_20' ) ),
        dropped( 703, array( 'wpcpm_call_lock_20' ) ),
    ),
    array( array( 'series-joined', 2 ), true, false, true, array( WPCPM_ICS::uid( 701 ), WPCPM_ICS::uid( 703 ) ), array( true, true ), array( true, true ), array( true, true ) ) );
$GLOBALS['query_result'] = array();

// Leaving and canceling take the mentor's lock as joining does: each changes who is on a session
// that a join, a change or the other of them may be reading at that moment.
session_fixture( 704, $locked_at + DAY_IN_SECONDS );
WPCPM_Mentor_Calls::add_attendee( 704, 30, $student_rec );
WPCPM_Mentor_Calls::lock_for( 20 );
$_POST = array( 'session' => 704, 'student' => 30 );
run( 'handle_leave (the mentor\'s lock is held)', array( 'WPCPM_Group_Sessions', 'handle_leave' ) );
$leave_busy = array( flashed( 30, 'call' ), WPCPM_Group_Sessions::has_joined( 704, 30 ), count( $GLOBALS['mail'] ) );
WPCPM_Mentor_Calls::unlock_for( 20 );
$GLOBALS['cache_dropped'] = array();
run( 'handle_leave (the lock is free)', array( 'WPCPM_Group_Sessions', 'handle_leave' ) );
check( 'a held lock refuses a leave and keeps the student on; once it is free the session is read again from the table under the lock, the student taken off, sent the file that removes it, and the lock released',
    array( $leave_busy, flashed( 30, 'call' ), WPCPM_Group_Sessions::has_joined( 704, 30 ), count( $GLOBALS['mail'] ), false !== strpos( file_of( 0 ), 'METHOD:CANCEL' ), get_option( 'wpcpm_call_lock_20', false ), dropped( 704, array( 'wpcpm_call_lock_20' ) ) ),
    array( array( 'busy', true, 0 ), 'session-left', false, 1, true, false, array( true, true ) ) );

session_fixture( 705, $locked_at + 2 * DAY_IN_SECONDS );
WPCPM_Mentor_Calls::add_attendee( 705, 30, $student_rec );
$GLOBALS['uid'] = 20;
WPCPM_Mentor_Calls::lock_for( 20 );
$_POST = array( 'call' => 705 );
run( 'handle_cancel (the mentor\'s lock is held)', array( 'WPCPM_Mentor_Calls', 'handle_cancel' ) );
$cancel_busy = array( flashed( 20, 'call' ), get_post_status( 705 ), count( $GLOBALS['mail'] ) );
WPCPM_Mentor_Calls::unlock_for( 20 );
$GLOBALS['cache_dropped'] = array();
run( 'handle_cancel (the lock is free)', array( 'WPCPM_Mentor_Calls', 'handle_cancel' ) );
check( 'a held lock refuses a cancellation and trashes nothing; once it is free the session is read again from the table under the lock, canceled, its student told, and the lock released',
    array( $cancel_busy, flashed( 20, 'call' ), get_post_status( 705 ), array_column( $GLOBALS['mail'], 'to' ), get_option( 'wpcpm_call_lock_20', false ), dropped( 705, array( 'wpcpm_call_lock_20' ) ) ),
    array( array( 'busy', 'private', 0 ), 'session-cancelled', 'trash', array( 'student@example.test' ), false, array( true, true ) ) );

// A second press of Cancel finds the session in the trash under the lock, and tells nobody again: it
// wrote to everybody a second time and raised the version once more (the fix round of SESSIONS-5).
$revision_705 = (int) get_post_meta( 705, WPCPM_Group_Sessions::META_REVISION, true );
run( 'handle_cancel (the same session again)', array( 'WPCPM_Mentor_Calls', 'handle_cancel' ) );
check( 'a second Cancel of a session already canceled writes to nobody, raises no version, says the session is gone and releases the lock',
    array( flashed( 20, 'call' ), count( $GLOBALS['mail'] ), (int) get_post_meta( 705, WPCPM_Group_Sessions::META_REVISION, true ), get_option( 'wpcpm_call_lock_20', false ) ),
    array( 'session-gone', 0, $revision_705, false ) );

// The reminder sweep reads its list once and then sends for as long as the mail host takes. The
// second session here was canceled after the list was read, so it is still in it.
session_fixture( 706, time() + 20 * HOUR_IN_SECONDS );
session_fixture( 707, time() + 21 * HOUR_IN_SECONDS );
foreach ( array( 706, 707 ) as $reminded ) {
	WPCPM_Mentor_Calls::add_attendee( $reminded, 30, $student_rec );
	$GLOBALS['booked_at'][ $reminded ] = time() - 3 * DAY_IN_SECONDS;
}
wp_trash_post( 707 );
$GLOBALS['query_result']  = array( $GLOBALS['posts'][706], $GLOBALS['posts'][707] );
$GLOBALS['mail']          = array();
$GLOBALS['cache_dropped'] = array();
WPCPM_Mentor_Calls::send_reminders();
check( 'the reminder sweep reads each session again from the table, holding no lock, and passes over one canceled after it read its list: the standing one is reminded to its mentor and student, the canceled one to nobody and left unmarked',
    array( array_column( $GLOBALS['mail'], 'to' ), '' !== get_post_meta( 706, WPCPM_Mentor_Calls::META_REMINDED, true ), get_post_meta( 707, WPCPM_Mentor_Calls::META_REMINDED, true ), dropped( 706, array() ), dropped( 707, array() ) ),
    array( array( 'mentor@example.test', 'student@example.test' ), true, '', array( true, true ), array( true, true ) ) );
$GLOBALS['query_result'] = array();

// SESSIONS-9: five places, three students, and the mentor lowers the places to three while a
// fourth student joins in between.
session_fixture( 708, $locked_at + 3 * DAY_IN_SECONDS, 5 );
foreach ( array( 30, 51, 52 ) as $on ) {
	WPCPM_Mentor_Calls::add_attendee( 708, $on, 'recSTUDENT' . $on . '000000' );
}
$GLOBALS['on_lock_read'] = function () {
	WPCPM_Mentor_Calls::add_attendee( 708, 53, 'recSTUDENT53000000' );
};
$_POST                    = array( 'session' => 708, 'date' => wp_date( 'Y-m-d', $locked_at + 3 * DAY_IN_SECONDS ), 'time' => '14:00', 'minutes' => 60, 'capacity' => 3, 'topic' => '' );
$GLOBALS['cache_dropped'] = array();
run( 'handle_edit (a join lands as the change takes the lock)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
check( 'the places are compared with the students on the session read again from the table under the lock, so a join landing in between refuses the change and the places stay',
    array( flashed( 20, 'call' ), (int) get_post_meta( 708, WPCPM_Mentor_Calls::META_CAPACITY, true ), count( WPCPM_Mentor_Calls::attendees( 708 ) ), get_option( 'wpcpm_call_lock_20', false ), dropped( 708, array( 'wpcpm_call_lock_20' ) ) ),
    array( 'session-shrink', 5, 4, false, array( true, true ) ) );

// And a change read before a cancellation must not move the canceled session: its invitation would
// put the session back in every student's calendar.
session_fixture( 709, $locked_at + 4 * DAY_IN_SECONDS );
WPCPM_Mentor_Calls::add_attendee( 709, 30, $student_rec );
$GLOBALS['on_lock_read'] = function () {
	wp_trash_post( 709 );
};
$_POST                    = array( 'session' => 709, 'date' => wp_date( 'Y-m-d', $locked_at + 5 * DAY_IN_SECONDS ), 'time' => '15:00', 'minutes' => 60, 'capacity' => 6, 'topic' => '' );
$GLOBALS['cache_dropped'] = array();
run( 'handle_edit (canceled as the change takes the lock)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
check( 'a change whose session is canceled as it takes the lock reads it again from the table under the lock, moves nothing and sends nobody an invitation',
    array( flashed( 20, 'call' ), (int) get_post_meta( 709, WPCPM_Mentor_Calls::META_START, true ), count( $GLOBALS['mail'] ), get_option( 'wpcpm_call_lock_20', false ), dropped( 709, array( 'wpcpm_call_lock_20' ) ) ),
    array( 'session-gone', $locked_at + 4 * DAY_IN_SECONDS, 0, false, array( true, true ) ) );

foreach ( range( 700, 709 ) as $fixture ) {
	unset( $GLOBALS['posts'][ $fixture ], $GLOBALS['pmeta'][ $fixture ], $GLOBALS['pmeta_rows'][ $fixture ] );
}
$GLOBALS['mail'] = array();

echo "\n=== Every calendar file outranks the one before it (SESSIONS-3) ===\n";

/**
 * Note what each calendar file the last press sent says of its events, by recipient.
 *
 * @param array $ledger Recipient to a list of `array( METHOD, SEQUENCE )`, filled by reference.
 */
function note_sequences( array &$ledger ) {
	foreach ( $GLOBALS['mail'] as $sent ) {
		foreach ( $sent['contents'] as $ics ) {
			if ( ! preg_match( '/^METHOD:(\w+)\r$/m', (string) $ics, $method ) || ! preg_match_all( '/^SEQUENCE:(\d+)\r$/m', (string) $ics, $sequences ) ) {
				continue;
			}

			foreach ( $sequences[1] as $sequence ) {
				$ledger[ $sent['to'] ][] = array( $method[1], (int) $sequence );
			}
		}
	}
}

/**
 * Whether every sequence in a recipient's list is higher than the one before it.
 *
 * @param array $sent `array( METHOD, SEQUENCE )` pairs, in the order they were sent.
 * @return bool
 */
function rising( array $sent ) {
	$last = -1;

	foreach ( $sent as $one ) {
		if ( $one[1] <= $last ) {
			return false;
		}

		$last = $one[1];
	}

	return true;
}

// The deep check of 1.109.1, SESSIONS-3: only a move carried the session's revision, so after two
// moves a leave and a cancellation went out below the version the calendars held, and a student who
// left and joined again was sent a first version after their own cancellation. A compliant calendar
// ignores a file that does not outrank what it holds, so the session stayed in it, or never came back.
$GLOBALS['users'][32]        = new WP_User( 32, 'Kim Student', 'kim@example.test' );
$GLOBALS['users'][32]->roles = array( WPCPM_Roles::ROLE_STUDENT );
$GLOBALS['umeta'][32]        = array(
	WPCPM_Students_Sync::META_RECORD_ID => 'recSTUDENT3200000',
	WPCPM_Students_Sync::META_MENTOR    => array( 'record_id' => $mentor_rec, 'name' => 'Mia Mentor' ),
);
$GLOBALS['query_result']     = array();
$GLOBALS['caps']             = false;
$versioned_at                = strtotime( weeks_on( 6 ) . ' 16:00:00 UTC' );

session_fixture( 720, $versioned_at );

$ledger = array();
$presses = array(
	array( 30, 'WPCPM_Group_Sessions', 'handle_join', array( 'session' => 720 ) ),
	array( 32, 'WPCPM_Group_Sessions', 'handle_join', array( 'session' => 720 ) ),
	array( 20, 'WPCPM_Group_Sessions', 'handle_edit', array( 'session' => 720, 'date' => weeks_on( 6 ), 'time' => '17:00', 'minutes' => 60, 'capacity' => 6, 'topic' => '' ) ),
	array( 20, 'WPCPM_Group_Sessions', 'handle_edit', array( 'session' => 720, 'date' => weeks_on( 6 ), 'time' => '18:00', 'minutes' => 60, 'capacity' => 6, 'topic' => '' ) ),
	array( 32, 'WPCPM_Group_Sessions', 'handle_leave', array( 'session' => 720 ) ),
	array( 32, 'WPCPM_Group_Sessions', 'handle_join', array( 'session' => 720 ) ),
	array( 20, 'WPCPM_Mentor_Calls', 'handle_cancel', array( 'call' => 720 ) ),
);

foreach ( $presses as $press ) {
	$GLOBALS['uid'] = $press[0];
	$_POST          = $press[3];
	run( $press[2] . ' (session 720, as user ' . $press[0] . ')', array( $press[1], $press[2] ) );
	flashed( $press[0], 'call' );
	note_sequences( $ledger );
}

check( 'after two moves, a leave, a join again and a cancellation, every file each person was sent outranks the one before it',
    array(
        array_column( $ledger['student@example.test'] ?? array(), 0 ),
        rising( $ledger['student@example.test'] ?? array() ),
        array_column( $ledger['kim@example.test'] ?? array(), 0 ),
        rising( $ledger['kim@example.test'] ?? array() ),
        array_column( $ledger['mentor@example.test'] ?? array(), 0 ),
        rising( $ledger['mentor@example.test'] ?? array() ),
    ),
    array(
        array( 'REQUEST', 'REQUEST', 'REQUEST', 'CANCEL' ), true,
        array( 'REQUEST', 'REQUEST', 'REQUEST', 'CANCEL', 'REQUEST', 'CANCEL' ), true,
        array( 'REQUEST', 'REQUEST', 'REQUEST' ), true,
    ) );

// With no move at all: the student's own cancellation and the invitation after it.
session_fixture( 721, $versioned_at + DAY_IN_SECONDS );

$ledger = array();

foreach ( array( 'handle_join', 'handle_leave', 'handle_join' ) as $step ) {
	$GLOBALS['uid'] = 30;
	$_POST          = array( 'session' => 721 );
	run( $step . ' (session 721)', array( 'WPCPM_Group_Sessions', $step ) );
	flashed( 30, 'call' );
	note_sequences( $ledger );
}

check( 'a student who leaves and joins again with nothing moved is sent a join, a cancellation and a join, each outranking the last, and so is the mentor',
    array(
        array_column( $ledger['student@example.test'] ?? array(), 0 ),
        rising( $ledger['student@example.test'] ?? array() ),
        rising( $ledger['mentor@example.test'] ?? array() ),
    ),
    array( array( 'REQUEST', 'CANCEL', 'REQUEST' ), true, true ) );

// Join all puts each session's own version on its event: the second session here was moved twice.
foreach ( array( 722, 723 ) as $week => $member ) {
	session_fixture( $member, $versioned_at + ( 7 + 7 * $week ) * DAY_IN_SECONDS );
	$GLOBALS['pmeta'][ $member ][ WPCPM_Group_Sessions::META_SERIES ] = 722;
}
$GLOBALS['pmeta'][723][ WPCPM_Group_Sessions::META_REVISION ] = 2;
$GLOBALS['query_result'] = array( $GLOBALS['posts'][722], $GLOBALS['posts'][723] );
$GLOBALS['uid']          = 30;
$_POST                   = array( 'series' => 722 );
run( 'handle_join_series (722 and 723)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );
flashed( 30, 'call' );
preg_match_all( '/^SEQUENCE:(\d+)\r$/m', file_of( 1 ), $series_versions );
check( 'Join all\'s file carries each session\'s own version, raised by the join, on that session\'s event',
    array( uids_in( file_of( 1 ) ), $series_versions[1], (int) get_post_meta( 722, WPCPM_Group_Sessions::META_REVISION, true ), (int) get_post_meta( 723, WPCPM_Group_Sessions::META_REVISION, true ) ),
    array( array( WPCPM_ICS::uid( 722 ), WPCPM_ICS::uid( 723 ) ), array( '1', '3' ), 1, 3 ) );

// A second press of the same Join, landing after the first took the place, raises nothing and
// sends no second invitation: only a join that puts somebody on the session is a new version.
session_fixture( 724, $versioned_at + 21 * DAY_IN_SECONDS );
$GLOBALS['on_lock_read'] = function () use ( $student_rec ) {
	WPCPM_Mentor_Calls::add_attendee( 724, 30, $student_rec );
};
$GLOBALS['uid'] = 30;
$_POST          = array( 'session' => 724 );
run( 'handle_join (the same press twice)', array( 'WPCPM_Group_Sessions', 'handle_join' ) );
check( 'a join that finds the student already on under the lock says so, raises no version and sends nothing',
    array( flashed( 30, 'call' ), (int) get_post_meta( 724, WPCPM_Group_Sessions::META_REVISION, true ), count( $GLOBALS['mail'] ), count( WPCPM_Mentor_Calls::attendees( 724 ) ) ),
    array( 'session-already', 0, 0, 1 ) );

// And when the first press took the last place: the second is told the student is on it, not that
// the session is full (the fix round of SESSIONS-3: the room was read before the student was).
session_fixture( 725, $versioned_at + 28 * DAY_IN_SECONDS, 2 );
WPCPM_Mentor_Calls::add_attendee( 725, 51, 'recSTUDENT51000000' );
$GLOBALS['on_lock_read'] = function () use ( $student_rec ) {
	WPCPM_Mentor_Calls::add_attendee( 725, 30, $student_rec );
};
$_POST = array( 'session' => 725 );
run( 'handle_join (the same press twice, the first taking the last place)', array( 'WPCPM_Group_Sessions', 'handle_join' ) );
check( 'a join that finds the student already on the session\'s last place says they are on it, not that it is full',
    array( flashed( 30, 'call' ), count( $GLOBALS['mail'] ), WPCPM_Mentor_Calls::attendees( 725 ) ),
    array( 'session-already', 0, array( 51, 30 ) ) );

foreach ( range( 720, 725 ) as $fixture ) {
	unset( $GLOBALS['posts'][ $fixture ], $GLOBALS['pmeta'][ $fixture ], $GLOBALS['pmeta_rows'][ $fixture ] );
}
$GLOBALS['query_result'] = array();
$GLOBALS['mail']         = array();
$GLOBALS['uid']          = 20;

echo "\n=== A session blocks its whole length, not its start (SESSIONS-4) ===\n";

// The deep check of 1.109.1, SESSIONS-4: the diary answered instants, so a sixty-minute session at
// 10:00 over thirty-minute slots left 10:30 bookable, a session at 10:15 blocked nothing at all,
// and a session planned over a booked call at another minute was accepted. The fixture mentor
// offers 09:00 to 12:00 UTC in thirty-minute slots, and `weeks_on( 0 )` is inside their horizon.
$GLOBALS['uid']  = 30;
$GLOBALS['caps'] = false;
$span_day        = weeks_on( 0 );
$at              = function ( $hm ) use ( $span_day ) {
	return strtotime( $span_day . ' ' . $hm . ':00 UTC' );
};
$offered_on_day  = function () use ( $span_day ) {
	$times = array();

	foreach ( WPCPM_Mentor_Availability::slots( 20 ) as $slot ) {
		if ( gmdate( 'Y-m-d', $slot['start'] ) === $span_day ) {
			$times[] = gmdate( 'H:i', $slot['start'] );
		}
	}

	return $times;
};

session_fixture( 730, $at( '10:00' ) );
$GLOBALS['query_result'] = array( $GLOBALS['posts'][730] );
$on_the_hour             = $offered_on_day();

session_fixture( 731, $at( '10:15' ) );
$GLOBALS['query_result'] = array( $GLOBALS['posts'][731] );
$off_the_grid            = $offered_on_day();

check( 'a slot that starts inside a session is not offered, whether the session starts on the slot grid or between its slots',
    array( $on_the_hour, $off_the_grid ),
    array( array( '09:00', '09:30', '11:00', '11:30' ), array( '09:00', '09:30', '11:30' ) ) );

// And a booking for such a slot is refused under the lock, as a slot somebody else took is.
$GLOBALS['query_result'] = array( $GLOBALS['posts'][730] );
$posts_before            = count( $GLOBALS['posts'] );
$_POST                   = array( 'start' => $at( '10:30' ), 'topic' => '' );
run( 'handle_book (a slot inside a session)', array( 'WPCPM_Mentor_Calls', 'handle_book' ) );
check( 'booking a slot that starts inside a session is refused and books nothing',
    array( flashed( 30, 'call' ), count( $GLOBALS['posts'] ) - $posts_before ),
    array( 'taken', 0 ) );

// A one-to-one call from 10:15 to 10:45 stands, and the mentor plans a session from 10:00 to 11:00.
$GLOBALS['posts'][732]              = new WP_Post();
$GLOBALS['posts'][732]->ID          = 732;
$GLOBALS['posts'][732]->post_type   = WPCPM_Mentor_Calls::POST_TYPE;
$GLOBALS['posts'][732]->post_status = 'private';
$GLOBALS['pmeta'][732]              = array(
	WPCPM_Mentor_Calls::META_MENTOR  => 20,
	WPCPM_Mentor_Calls::META_STUDENT => 30,
	WPCPM_Mentor_Calls::META_START   => $at( '10:15' ),
	WPCPM_Mentor_Calls::META_END     => $at( '10:45' ),
);
$GLOBALS['query_result'] = array( $GLOBALS['posts'][732] );
$GLOBALS['uid']          = 20;
$posts_before            = count( $GLOBALS['posts'] );
$_POST                   = array( 'mentor' => 20, 'date' => $span_day, 'time' => '10:00', 'minutes' => 60, 'capacity' => 6 );
run( 'handle_create (over a booked call at another minute)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
check( 'a session planned over a booked call that starts at another minute is refused, and nothing is created',
    array( flashed( 20, 'call' ), count( $GLOBALS['posts'] ) - $posts_before ),
    array( 'session-clash', 0 ) );

// And a session moved onto a time another of the mentor's calls runs through is refused, while one
// moved to start as that call ends lands: the harness answers every query with the same list, so it
// is the second press that shows the change is judged by the spans and not by the list's being there.
$GLOBALS['query_result'] = array( $GLOBALS['posts'][732] );
$_POST                   = array( 'session' => 730, 'date' => $span_day, 'time' => '10:30', 'minutes' => 60, 'capacity' => 6, 'topic' => '' );
run( 'handle_edit (onto a time a call runs through)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
$over_a_call = array( flashed( 20, 'call' ), (int) get_post_meta( 730, WPCPM_Mentor_Calls::META_START, true ) );
$_POST       = array( 'session' => 730, 'date' => $span_day, 'time' => '10:45', 'minutes' => 60, 'capacity' => 6, 'topic' => '' );
run( 'handle_edit (to start as a call ends)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
check( 'a session moved onto a time another call of the mentor\'s runs through is refused and stays, and moved to start as that call ends it lands',
    array( $over_a_call, flashed( 20, 'call' ), (int) get_post_meta( 730, WPCPM_Mentor_Calls::META_START, true ) ),
    array( array( 'session-clash', $at( '10:00' ) ), 'session-updated', $at( '10:45' ) ) );

// Only a change of the time is tested for overlap: a session that already overlaps a call, as one
// planned before the span rule may, is still the mentor's to retitle or to give more places (the
// fix round of SESSIONS-4). A call from 10:30 to 11:00 stands beside the session from 10:00 to 11:00.
session_fixture( 733, $at( '10:00' ) );
$GLOBALS['posts'][734]              = new WP_Post();
$GLOBALS['posts'][734]->ID          = 734;
$GLOBALS['posts'][734]->post_type   = WPCPM_Mentor_Calls::POST_TYPE;
$GLOBALS['posts'][734]->post_status = 'private';
$GLOBALS['pmeta'][734]              = array(
	WPCPM_Mentor_Calls::META_MENTOR  => 20,
	WPCPM_Mentor_Calls::META_STUDENT => 30,
	WPCPM_Mentor_Calls::META_START   => $at( '10:30' ),
	WPCPM_Mentor_Calls::META_END     => $at( '11:00' ),
);
$GLOBALS['query_result'] = array( $GLOBALS['posts'][734] );
$_POST                   = array( 'session' => 733, 'date' => $span_day, 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'A better title' );
run( 'handle_edit (the topic alone, beside an overlap from before)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
$retitled = array( flashed( 20, 'call' ), $GLOBALS['posts'][733]->post_content );
$_POST    = array( 'session' => 733, 'date' => $span_day, 'time' => '10:00', 'minutes' => 60, 'capacity' => 9, 'topic' => 'A better title' );
run( 'handle_edit (the places alone, beside an overlap from before)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
check( 'a change of the topic or of the places alone is saved beside an overlap the session already had, and the time stays',
    array( $retitled, flashed( 20, 'call' ), (int) get_post_meta( 733, WPCPM_Mentor_Calls::META_CAPACITY, true ), (int) get_post_meta( 733, WPCPM_Mentor_Calls::META_START, true ) ),
    array( array( 'session-updated', 'A better title' ), 'session-updated', 9, $at( '10:00' ) ) );

foreach ( range( 730, 734 ) as $fixture ) {
	unset( $GLOBALS['posts'][ $fixture ], $GLOBALS['pmeta'][ $fixture ], $GLOBALS['pmeta_rows'][ $fixture ] );
}
$GLOBALS['query_result'] = array();
$GLOBALS['mail']         = array();

echo "\n=== A student moved to another mentor (SESSIONS-7) ===\n";

// The deep check of 1.109.1, SESSIONS-7: nothing took a re-paired student off the old mentor's
// sessions, their page drew no way off them, and the old mentor's note on any of them was refused
// for everybody. Lee (33) was Mia's (20) and is now Noa's (21): his card names Noa, and Noa's list
// holds him while Mia's does not, so the two syncs agree.
$noa_rec = 'recMENTORNOAONEXX';
$lee_rec = 'recSTUDENT3300000';

$GLOBALS['users'][33]        = new WP_User( 33, 'Lee Student', 'lee@example.test' );
$GLOBALS['users'][33]->roles = array( WPCPM_Roles::ROLE_STUDENT );
$GLOBALS['umeta'][21][ WPCPM_Mentors_Sync::META_RECORD_ID ] = $noa_rec;
$GLOBALS['umeta'][21][ WPCPM_Mentors_Sync::META_MENTEES ]   = array( array( 'record_id' => $lee_rec, 'name' => 'Lee Student', 'is_past' => false ) );
$GLOBALS['umeta'][21][ WPCPM_Mentor_Availability::META ]    = $mentor_schedule;
$GLOBALS['umeta'][33]                                       = array(
	WPCPM_Students_Sync::META_RECORD_ID => $lee_rec,
	WPCPM_Students_Sync::META_MENTOR    => array( 'record_id' => $noa_rec, 'name' => 'Noa Mentor' ),
);
$repaired_at = strtotime( weeks_on( 7 ) . ' 16:00:00 UTC' );

session_fixture( 740, $repaired_at );
WPCPM_Mentor_Calls::add_attendee( 740, 33, $lee_rec );

/**
 * Hand the re-paired student to the sync's step, as it runs once the card is written.
 *
 * @param WP_Post[] $on The upcoming calls the student's query answers.
 * @return int How many sessions they were taken off.
 */
function leave_former_of( array $on ) {
	$GLOBALS['query_result'] = $on;
	$GLOBALS['mail']         = array();
	$left                    = WPCPM_Group_Sessions::leave_former( 33 );
	$GLOBALS['query_result'] = array();

	return $left;
}

// Not settled: Noa's list does not hold Lee yet, since the mentors sync has not run since his card
// was rewritten. Then settled on the new side, but Mia's list still holds him.
$GLOBALS['umeta'][21][ WPCPM_Mentors_Sync::META_MENTEES ] = array();
$unsettled_new = array( leave_former_of( array( $GLOBALS['posts'][740] ) ), WPCPM_Group_Sessions::has_joined( 740, 33 ), count( $GLOBALS['mail'] ) );
$GLOBALS['umeta'][21][ WPCPM_Mentors_Sync::META_MENTEES ] = array( array( 'record_id' => $lee_rec, 'name' => 'Lee Student', 'is_past' => false ) );
$mia_mentees = $GLOBALS['umeta'][20][ WPCPM_Mentors_Sync::META_MENTEES ];
$GLOBALS['umeta'][20][ WPCPM_Mentors_Sync::META_MENTEES ][] = array( 'record_id' => $lee_rec, 'name' => 'Lee Student', 'is_past' => false );
$unsettled_old = array( leave_former_of( array( $GLOBALS['posts'][740] ) ), WPCPM_Group_Sessions::has_joined( 740, 33 ), count( $GLOBALS['mail'] ) );
$GLOBALS['umeta'][20][ WPCPM_Mentors_Sync::META_MENTEES ] = $mia_mentees;

check( 'while either side of the pairing still disagrees, the sync leaves the student on the old mentor\'s session and writes to nobody',
    array( $unsettled_new, $unsettled_old ),
    array( array( 0, true, 0 ), array( 0, true, 0 ) ) );

// Settled: the sync takes him off, with the file that takes it out of his calendar.
$settled = leave_former_of( array( $GLOBALS['posts'][740] ) );
check( 'once the pairing has settled, the sync takes the student off the old mentor\'s upcoming session, tells him why, and sends the cancellation for his calendar',
    array(
        $settled,
        WPCPM_Group_Sessions::has_joined( 740, 33 ),
        array_column( $GLOBALS['mail'], 'to' ),
        false !== strpos( $GLOBALS['mail'][0]['subj'] ?? '', 'is released' ),
        false !== strpos( $GLOBALS['mail'][0]['body'] ?? '', 'You have a new mentor' ),
        uids_in( file_of( 0 ) ),
        false !== strpos( file_of( 0 ), 'METHOD:CANCEL' ),
        get_option( 'wpcpm_call_lock_20', false ),
    ),
    array( 1, false, array( 'lee@example.test' ), true, true, array( WPCPM_ICS::uid( 740 ) ), true, false ) );

// A session under way is left alone, as a Leave is refused on one.
session_fixture( 741, time() - 600 );
WPCPM_Mentor_Calls::add_attendee( 741, 33, $lee_rec );
check( 'a session of the old mentor\'s that has already started is left as it is',
    array( leave_former_of( array( $GLOBALS['posts'][741] ) ), WPCPM_Group_Sessions::has_joined( 741, 33 ), count( $GLOBALS['mail'] ) ),
    array( 0, true, 0 ) );

// Until the sync runs, his page has to offer the way off: Leave on the old mentor's session, saying
// whose it is, and no Join on one of hers he is not on.
session_fixture( 742, $repaired_at + DAY_IN_SECONDS );
$GLOBALS['pmeta'][742][ WPCPM_Mentor_Calls::META_MENTOR ] = 21;
session_fixture( 743, $repaired_at + 2 * DAY_IN_SECONDS );
WPCPM_Mentor_Calls::add_attendee( 740, 33, $lee_rec );
$GLOBALS['query_result'] = array( $GLOBALS['posts'][740], $GLOBALS['posts'][742], $GLOBALS['posts'][743] );
$GLOBALS['uid']          = 33;
ob_start();
WPCPM_Group_Sessions::render_student_list( $GLOBALS['users'][33], true );
$repaired_list = ob_get_clean();
check( 'the re-paired student\'s list offers Leave on the old mentor\'s session he is on and says whose it is, Join on his new mentor\'s, and no Join on the old mentor\'s he is not on',
    array(
        substr_count( $repaired_list, 'name="action" value="' . WPCPM_Group_Sessions::ACTION_LEAVE . '"' ),
        substr_count( $repaired_list, 'name="action" value="' . WPCPM_Group_Sessions::ACTION_JOIN . '"' ),
        substr_count( $repaired_list, '<input type="hidden" name="session" value="742" />' ),
        substr_count( $repaired_list, 'With Mia Mentor' ),
        false !== strpos( $repaired_list, 'Group sessions with your mentor' ),
    ),
    array( 1, 1, 1, 2, false ) );

$_POST = array( 'session' => 740 );
run( 'handle_leave (the re-paired student leaves the old mentor\'s session)', array( 'WPCPM_Group_Sessions', 'handle_leave' ) );
check( 'and his Leave there takes him off it', array( flashed( 33, 'call' ), WPCPM_Group_Sessions::has_joined( 740, 33 ) ), array( 'session-left', false ) );

// The old mentor notes the session afterwards: Sam is still hers, Lee no longer is.
session_fixture( 744, $repaired_at + 3 * DAY_IN_SECONDS );
WPCPM_Mentor_Calls::add_attendee( 744, 30, $student_rec );
WPCPM_Mentor_Calls::add_attendee( 744, 33, $lee_rec );
$GLOBALS['uid']          = 20;
$GLOBALS['caps']         = false;
$GLOBALS['query_result'] = array();
$_POST                   = array( 'session' => 744, 'note' => 'Went through the release.' );
run( 'handle_note (the old mentor, a re-paired student on the session)', array( 'WPCPM_Group_Sessions', 'handle_note' ) );
$old_mentor_note = flashed( 20, 'call' );

// A record on a session that nobody may note, which only a broken row can be, is refused in the
// words the refusal carries rather than the one flash every failure shared.
session_fixture( 745, $repaired_at + 4 * DAY_IN_SECONDS );
WPCPM_Mentor_Calls::add_attendee( 745, 30, $student_rec );
WPCPM_Mentor_Calls::add_attendee( 745, 46, 'not-a-record' );
$_POST = array( 'session' => 745, 'note' => 'Went through the release.' );
run( 'handle_note (a record nobody may note)', array( 'WPCPM_Group_Sessions', 'handle_note' ) );
check( 'the old mentor\'s note on a session with a re-paired student is saved, and a note refused for somebody on the session says so in its own words',
    array( $old_mentor_note, flashed( 20, 'call' ), WPCPM_Mentor_Calls::message( 'session-note-denied' ) ),
    array( 'session-noted', 'session-note-denied', array( 'error', 'You cannot add notes for everybody on that session.' ) ) );

foreach ( range( 740, 745 ) as $fixture ) {
	unset( $GLOBALS['posts'][ $fixture ], $GLOBALS['pmeta'][ $fixture ], $GLOBALS['pmeta_rows'][ $fixture ] );
}
$GLOBALS['query_result'] = array();
$GLOBALS['mail']         = array();

echo "\n=== A moved session is reminded again (SESSIONS-10) ===\n";

// The deep check of 1.109.1, SESSIONS-10: the sweep marks a session it reminded and never looks at
// it again, and a move left the mark, so a session pushed a week on after its reminder went out was
// never reminded before the new time. A change of the topic alone moves nothing and keeps the mark.
$GLOBALS['uid']          = 20;
$GLOBALS['caps']         = false;
$GLOBALS['query_result'] = array();
$reminded_at             = strtotime( weeks_on( 8 ) . ' 16:00:00 UTC' );

session_fixture( 750, $reminded_at );
WPCPM_Mentor_Calls::add_attendee( 750, 30, $student_rec );
$GLOBALS['pmeta'][750][ WPCPM_Mentor_Calls::META_REMINDED ] = time() - HOUR_IN_SECONDS;

$_POST = array( 'session' => 750, 'date' => weeks_on( 8 ), 'time' => '16:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'A new topic' );
run( 'handle_edit (the topic alone)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
$topic_only = array( flashed( 20, 'call' ), '' !== get_post_meta( 750, WPCPM_Mentor_Calls::META_REMINDED, true ) );

$_POST = array( 'session' => 750, 'date' => weeks_on( 9 ), 'time' => '16:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'A new topic' );
run( 'handle_edit (a week on)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
check( 'a change of the topic keeps the reminder mark, and a move clears it, so the sweep reminds the session again before its new time',
    array( $topic_only, flashed( 20, 'call' ), get_post_meta( 750, WPCPM_Mentor_Calls::META_REMINDED, true ) ),
    array( array( 'session-updated', true ), 'session-updated', '' ) );

// And a session moved out of the window while the sweep is sending: the list was read when it was
// twenty hours away, and by the time the loop reaches it the mentor has moved it nine days on. Reminded
// then and marked, it lost the reminder before its new time (the fix round of SESSIONS-10).
session_fixture( 751, time() + 9 * DAY_IN_SECONDS );
WPCPM_Mentor_Calls::add_attendee( 751, 30, $student_rec );
$GLOBALS['booked_at'][751] = time() - 3 * DAY_IN_SECONDS;
$GLOBALS['query_result']   = array( $GLOBALS['posts'][751] );
$GLOBALS['mail']           = array();
WPCPM_Mentor_Calls::send_reminders();
check( 'the sweep passes over a session moved out of its window after it read its list: nobody is reminded early, and it is left unmarked for the reminder before its new time',
    array( array_column( $GLOBALS['mail'], 'to' ), get_post_meta( 751, WPCPM_Mentor_Calls::META_REMINDED, true ) ),
    array( array(), '' ) );
$GLOBALS['query_result'] = array();

unset( $GLOBALS['posts'][750], $GLOBALS['pmeta'][750], $GLOBALS['pmeta_rows'][750], $GLOBALS['posts'][751], $GLOBALS['pmeta'][751], $GLOBALS['pmeta_rows'][751] );
$GLOBALS['mail'] = array();

echo "\n=== A manager's move is told to the mentor (SESSIONS-11) ===\n";

// The deep check of 1.109.1, SESSIONS-11: the move notice went to the students alone, on the premise
// that the mentor is the one who moved it, so a session a program manager moved for a mentor kept its
// old time in the mentor's calendar. The mentor moving it themselves is still told nothing.
$GLOBALS['query_result'] = array();
$moved_at                = strtotime( weeks_on( 10 ) . ' 16:00:00 UTC' );

session_fixture( 760, $moved_at );
WPCPM_Mentor_Calls::add_attendee( 760, 30, $student_rec );
WPCPM_Mentor_Calls::add_attendee( 760, 32, 'recSTUDENT3200000' );

$GLOBALS['uid']  = 1;
$GLOBALS['caps'] = true;
$_POST           = array( 'session' => 760, 'date' => weeks_on( 10 ), 'time' => '17:00', 'minutes' => 60, 'capacity' => 6, 'topic' => '' );
run( 'handle_edit (a program manager moves a mentor\'s session)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
flashed( 1, 'call' );
$manager_move = array();

foreach ( $GLOBALS['mail'] as $index => $sent ) {
	$manager_move[ $sent['to'] ] = file_of( $index );
}

$revision_760 = (int) get_post_meta( 760, WPCPM_Group_Sessions::META_REVISION, true );

$GLOBALS['uid']  = 20;
$GLOBALS['caps'] = false;
$_POST           = array( 'session' => 760, 'date' => weeks_on( 10 ), 'time' => '18:00', 'minutes' => 60, 'capacity' => 6, 'topic' => '' );
run( 'handle_edit (the mentor moves it again)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
flashed( 20, 'call' );

check( 'a program manager\'s move is sent to the mentor as well as the students, with the invitation that moves it in the mentor\'s calendar; the mentor\'s own move is sent to the students alone',
    array(
        array_keys( $manager_move ),
        false !== strpos( $manager_move['mentor@example.test'] ?? '', 'METHOD:REQUEST' ),
        false !== strpos( $manager_move['mentor@example.test'] ?? '', 'SEQUENCE:' . $revision_760 . "\r\n" ),
        false !== strpos( $manager_move['mentor@example.test'] ?? '', 'DTSTART:' . gmdate( 'Ymd\THis\Z', $moved_at + HOUR_IN_SECONDS ) ),
        array_column( $GLOBALS['mail'], 'to' ),
    ),
    array( array( 'student@example.test', 'kim@example.test', 'mentor@example.test' ), true, true, true, array( 'student@example.test', 'kim@example.test' ) ) );

unset( $GLOBALS['posts'][760], $GLOBALS['pmeta'][760], $GLOBALS['pmeta_rows'][760] );
$GLOBALS['mail'] = array();

echo "\n=== A session in its own words, and a manager's Leave for a student (SESSIONS-12, HOTFIX-1) ===\n";

// The deep check of 1.109.1, SESSIONS-12: a session passed through the one-to-one call list, so the
// mentor's diary listed each as "Unnamed student" and the student's booked column drew it with the
// call's Cancel. HOTFIX-1: a manager reading a student's page got that Cancel, worded for one
// student's call, which canceled the session for everybody, and no Leave for the student.
$GLOBALS['query_result'] = array();
$worded_at               = strtotime( weeks_on( 11 ) . ' 16:00:00 UTC' );
$_GET                    = array();

session_fixture( 770, $worded_at );
WPCPM_Mentor_Calls::add_attendee( 770, 30, $student_rec );
$GLOBALS['query_result'] = array( $GLOBALS['posts'][770] );

$GLOBALS['uid']  = 1;
$GLOBALS['caps'] = true;
ob_start();
WPCPM_Call_Calendar::render_student( $GLOBALS['users'][30], true );
$managed_page = ob_get_clean();
$booked_col   = (string) strstr( $managed_page, 'wpcpm-calls__col--pick', true );
$sessions_col = (string) strstr( $managed_page, 'wpcpm-sessions--student' );

check( 'on a student\'s page a manager sees no session in the booked column, and Leave for the student in the sessions list',
    array(
        substr_count( $booked_col, 'name="call" value="770"' ),
        substr_count( $sessions_col, 'name="action" value="' . WPCPM_Group_Sessions::ACTION_LEAVE . '"' ),
        substr_count( $sessions_col, '<input type="hidden" name="student" value="30" />' ),
        substr_count( $sessions_col, 'Take them off the session' ),
    ),
    array( 0, 1, 1, 1 ) );

$GLOBALS['uid']  = 20;
$GLOBALS['caps'] = false;
ob_start();
WPCPM_Call_Calendar::render_mentor( $GLOBALS['users'][20] );
$mentor_page = ob_get_clean();
$diary       = (string) strstr( $mentor_page, 'wpcpm-calls__col--sessions', true );

check( 'the mentor\'s diary names a session a group session with its places, never "Unnamed student", and its Cancel asks about the session',
    array(
        substr_count( $diary, 'Unnamed student' ),
        substr_count( $diary, '<p class="wpcpm-call__who">Group session</p>' ),
        substr_count( $diary, '1 of 6 places taken' ),
        substr_count( $diary, esc_attr( wp_json_encode( 'Cancel this session for everybody on it?' ) ) ),
        substr_count( $diary, 'Cancel this call? The slot goes back on the calendar.' ),
    ),
    array( 0, 1, 1, 1, 0 ) );

// The reminder of a session says so, and the mentor's copy replies to nobody in particular rather
// than to whoever joined first.
session_fixture( 771, time() + 20 * HOUR_IN_SECONDS );
WPCPM_Mentor_Calls::add_attendee( 771, 30, $student_rec );
$GLOBALS['posts'][771]->post_content = 'Release cycle';
$GLOBALS['booked_at'][771]           = time() - 3 * DAY_IN_SECONDS;
$GLOBALS['query_result']             = array( $GLOBALS['posts'][771] );
$GLOBALS['mail']                     = array();
WPCPM_Mentor_Calls::send_reminders();
$reminders = array();

foreach ( $GLOBALS['mail'] as $sent ) {
	$reminders[ $sent['to'] ] = $sent;
}

check( 'a session\'s reminder calls it a group session in the subject and the first line, labels the topic as the session\'s, and the mentor\'s copy has no student to reply to',
    array(
        $reminders['mentor@example.test']['subj'] ?? '',
        $reminders['student@example.test']['subj'] ?? '',
        0 === strpos( $reminders['student@example.test']['body'] ?? '', 'Your group session with Mia Mentor is ' ),
        false !== strpos( $reminders['student@example.test']['body'] ?? '', "What the session is about:\nRelease cycle" ),
        $reminders['mentor@example.test']['headers'] ?? null,
    ),
    array( '[Test Site] Reminder: your group session with 1 student', '[Test Site] Reminder: your group session with Mia Mentor', true, true, array() ) );

foreach ( array( 770, 771 ) as $fixture ) {
	unset( $GLOBALS['posts'][ $fixture ], $GLOBALS['pmeta'][ $fixture ], $GLOBALS['pmeta_rows'][ $fixture ] );
}
$GLOBALS['query_result'] = array();
$GLOBALS['mail']         = array();

echo "\n=== The right is asked about before the nonce (SESSIONS-14, SURFACES-4) ===\n";

// The deep check of 1.109.1, SESSIONS-14 and SURFACES-4: the session handlers checked the nonce
// first, so somebody without the right, or a student whose login had lapsed while the page sat
// open, met WordPress's "The link you followed has expired" rather than the handler's own answer.
// Each press here fails its nonce as well, and must be answered about the right first.
session_fixture( 780, strtotime( weeks_on( 12 ) . ' 16:00:00 UTC' ) );
WPCPM_Mentor_Calls::add_attendee( 780, 30, $student_rec );
$GLOBALS['query_result'] = array( $GLOBALS['posts'][780] );
$GLOBALS['nonce_fails']  = true;
$GLOBALS['caps']         = false;
$answers                 = array();

$GLOBALS['uid'] = 30;
foreach ( array(
	'handle_create' => array( 'WPCPM_Group_Sessions', array( 'mentor' => 20, 'date' => weeks_on( 12 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6 ) ),
	'handle_edit'   => array( 'WPCPM_Group_Sessions', array( 'session' => 780, 'date' => weeks_on( 12 ), 'time' => '17:00', 'minutes' => 60, 'capacity' => 6 ) ),
	'handle_note'   => array( 'WPCPM_Group_Sessions', array( 'session' => 780, 'note' => 'Mine now.' ) ),
	'handle_cancel' => array( 'WPCPM_Mentor_Calls', array( 'call' => 780 ) ),
) as $handler => $press ) {
	$_POST = $press[1];
	run( $handler . ' (a student, and the nonce fails too)', array( $press[0], $handler ) );
	$answers[ $handler ] = $GLOBALS['died_with'];
}

$GLOBALS['uid'] = 0;
foreach ( array(
	'handle_join'        => array( 'session' => 780 ),
	'handle_join_series' => array( 'series' => 780 ),
	'handle_leave'       => array( 'session' => 780 ),
) as $handler => $press ) {
	$_POST = $press;
	run( $handler . ' (logged out, and the nonce fails too)', array( 'WPCPM_Group_Sessions', $handler ) );
	$answers[ $handler ] = $GLOBALS['died_with'];
}

// And the nonce is still checked for somebody who has the right.
$GLOBALS['uid'] = 20;
$_POST          = array( 'mentor' => 20, 'date' => weeks_on( 12 ), 'time' => '10:00', 'minutes' => 60, 'capacity' => 6 );
run( 'handle_create (the mentor, and the nonce fails)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
$answers['the mentor'] = $GLOBALS['died_with'];

$GLOBALS['nonce_fails'] = false;

check( 'every session handler answers the question of who may press it before it checks the nonce, and still checks the nonce for somebody who may',
    $answers,
    array(
        'handle_create'      => 'You cannot create a session for that mentor.',
        'handle_edit'        => 'You cannot change that session.',
        'handle_note'        => 'You cannot add notes for that session.',
        'handle_cancel'      => 'You cannot cancel that call.',
        'handle_join'        => 'Please log in to join a session.',
        'handle_join_series' => 'Please log in to join a session.',
        'handle_leave'       => 'Please log in to leave a session.',
        'the mentor'         => 'The link you followed has expired.',
    ) );

unset( $GLOBALS['posts'][780], $GLOBALS['pmeta'][780], $GLOBALS['pmeta_rows'][780] );
$GLOBALS['query_result'] = array();
$GLOBALS['mail']         = array();
$GLOBALS['uid']          = 20;

echo "\n=== The room is read under the lock, and two students' files name each alone (SESSIONS-13) ===\n";

// The deep check of 1.109.1, SESSIONS-13: the suites proved the lock is taken, not that the room is
// read under it (reading Join all's plan before the lock passed every suite), and no suite sent a
// move or a leave of a session with two students on it.
$GLOBALS['uid']          = 30;
$GLOBALS['caps']         = false;
$GLOBALS['query_result'] = array();
$roomy_at                = strtotime( weeks_on( 13 ) . ' 16:00:00 UTC' );

// Two places and one taken; the last is taken as this join takes the lock.
session_fixture( 790, $roomy_at, 2 );
WPCPM_Mentor_Calls::add_attendee( 790, 51, 'recSTUDENT51000000' );
$GLOBALS['on_lock_read'] = function () {
	WPCPM_Mentor_Calls::add_attendee( 790, 52, 'recSTUDENT52000000' );
};
$_POST = array( 'session' => 790 );
run( 'handle_join (the last place goes as it takes the lock)', array( 'WPCPM_Group_Sessions', 'handle_join' ) );
check( 'a join reads the room under the lock: the place taken as it took the lock is seen, and nobody is put over the places',
    array( flashed( 30, 'call' ), WPCPM_Mentor_Calls::attendees( 790 ), count( $GLOBALS['mail'] ) ),
    array( 'session-full', array( 51, 52 ), 0 ) );

// Join all: the second session of two fills up as the press takes the lock.
foreach ( array( 791, 792 ) as $week => $member ) {
	session_fixture( $member, $roomy_at + ( 1 + $week ) * WEEK_IN_SECONDS, 2 );
	$GLOBALS['pmeta'][ $member ][ WPCPM_Group_Sessions::META_SERIES ] = 791;
}
$GLOBALS['query_result'] = array( $GLOBALS['posts'][791], $GLOBALS['posts'][792] );
$GLOBALS['on_lock_read'] = function () {
	WPCPM_Mentor_Calls::add_attendee( 792, 51, 'recSTUDENT51000000' );
	WPCPM_Mentor_Calls::add_attendee( 792, 52, 'recSTUDENT52000000' );
};
$_POST = array( 'series' => 791 );
run( 'handle_join_series (a session fills as it takes the lock)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );
check( 'Join all reads each session\'s room under the lock: the one that filled as it took the lock is skipped and counted full, the other taken',
    array( flashed( 30, 'call' ), WPCPM_Group_Sessions::has_joined( 791, 30 ), WPCPM_Mentor_Calls::attendees( 792 ) ),
    array( array( 'series-joined-some', 1, 2, 1 ), true, array( 51, 52 ) ) );
$GLOBALS['query_result'] = array();

// Two students on one session: the mentor moves it, then Kim leaves. Each student's file names that
// student and the mentor, and nobody else on the session.
session_fixture( 793, $roomy_at + 3 * WEEK_IN_SECONDS );
WPCPM_Mentor_Calls::add_attendee( 793, 30, $student_rec );
WPCPM_Mentor_Calls::add_attendee( 793, 32, 'recSTUDENT3200000' );
$GLOBALS['uid'] = 20;
$_POST          = array( 'session' => 793, 'date' => wp_date( 'Y-m-d', $roomy_at + 3 * WEEK_IN_SECONDS ), 'time' => '17:00', 'minutes' => 60, 'capacity' => 6, 'topic' => '' );
run( 'handle_edit (a session with two students on it)', array( 'WPCPM_Group_Sessions', 'handle_edit' ) );
flashed( 20, 'call' );
$moved_files = array();

foreach ( $GLOBALS['mail'] as $index => $sent ) {
	$moved_files[ $sent['to'] ] = as_sent( file_of( $index ) );
}

$GLOBALS['uid'] = 32;
$_POST          = array( 'session' => 793 );
run( 'handle_leave (the second of two students)', array( 'WPCPM_Group_Sessions', 'handle_leave' ) );
flashed( 32, 'call' );
$left_file = as_sent( file_of( 0 ) );

/**
 * The people a calendar file names, as the addresses on its organizer and attendee lines.
 *
 * @param string $ics The file, unfolded.
 * @return string[]
 */
function named_in( $ics ) {
	preg_match_all( '/^(?:ORGANIZER|ATTENDEE)[^\r\n]*:mailto:(\S+)\r$/m', (string) $ics, $found );

	return $found[1];
}

check( 'a move sends each of two students a file naming them and the mentor only, and the one who leaves is sent a file naming them and the mentor only',
    array(
        array_keys( $moved_files ),
        named_in( $moved_files['student@example.test'] ?? '' ),
        named_in( $moved_files['kim@example.test'] ?? '' ),
        array_column( $GLOBALS['mail'], 'to' ),
        named_in( $left_file ),
    ),
    array(
        array( 'student@example.test', 'kim@example.test' ),
        array( 'mentor@example.test', 'mentor@example.test', 'student@example.test' ),
        array( 'mentor@example.test', 'mentor@example.test', 'kim@example.test' ),
        array( 'kim@example.test' ),
        array( 'mentor@example.test', 'mentor@example.test', 'kim@example.test' ),
    ) );

foreach ( range( 790, 793 ) as $fixture ) {
	unset( $GLOBALS['posts'][ $fixture ], $GLOBALS['pmeta'][ $fixture ], $GLOBALS['pmeta_rows'][ $fixture ] );
}
$GLOBALS['mail'] = array();
$GLOBALS['uid']  = 20;

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

echo "\n=== The Settings screen carries Currently mentoring as it drew it, and says why a save was refused (BUILDER-3) ===\n";

// The deep check of 1.109.1, BUILDER-3: the save now tells a list somebody changed from one the
// page only carried back, which it can do only if the page carries the list it drew, one hidden
// field a status beside the textarea; and a save refused for taking a live track's status out
// comes back to this screen, which has to say why. The whole screen is drawn, as a person sees it.
$settings_before = $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] ?? null;

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array( 'student_statuses' => array( 'In Sensei', 'Mentor "Track" <b>' ) );
$GLOBALS['uid']                              = 1;
$GLOBALS['caps']                             = true;
update_user_meta( 1, WPCPM_Flash::META, array( 'settings-refused' => array( 'Mentor "Track" <b>' => 'Mentor <Track>' ) ) );

ob_start();
( new WPCPM_Admin() )->render_settings();
$settings_page = ob_get_clean();

check( 'the page carries the list it drew beside the textarea, each status escaped, and says, once and escaped, that the rest was saved and which track kept the list as it was (the fix round\'s ruling)',
    array(
        substr_count( $settings_page, '<input type="hidden" name="student_statuses_drawn[]" value="In Sensei" />' ),
        substr_count( $settings_page, '<input type="hidden" name="student_statuses_drawn[]" value="Mentor &quot;Track&quot; &lt;b&gt;" />' ),
        substr_count( $settings_page, 'name="student_statuses_drawn[]"' ),
        substr_count( $settings_page, '<div class="notice notice-warning is-dismissible"><p>Everything else was saved. &quot;Currently mentoring&quot; was left as it was, because Mentor &lt;Track&gt; runs on &quot;Mentor &quot;Track&quot; &lt;b&gt;&quot;: taking a status out while its track is live would make the next students sync treat everybody on the track as having left the program. Take the track off the live site in the Track Builder first, then remove its status here.</p></div>' ),
        substr_count( $settings_page, 'Mentor &lt;Track&gt; runs on' ),
        substr_count( $settings_page, '<b>' ),
        flashed( 1, 'settings-refused' ),
    ),
    array( 1, 1, 2, 1, 1, 0, '' ) );

if ( null === $settings_before ) {
	unset( $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] );
} else {
	$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = $settings_before;
}

echo "\n=== The Track Builder's seventeen handlers (BUILDER-8, TESTS-DOCS-2) ===\n";

// This file says it smoke-runs every admin-post handler in the plugin, and until the deep check of
// 1.109.1 it pressed none of the Track Builder's (BUILDER-8, TESTS-DOCS-2). Each is found by name,
// so a handler added later is pressed too, and pressed twice: by the manager, on a track that does
// not exist, the shortest path to its redirect, with the refusal read from the raw flash meta the
// real WPCPM_Flash writes; and by a student, who holds `read` and not the program's capability,
// with the nonce failing too, who must meet the capability sentence and not the nonce's.
$track_builder  = new WPCPM_Track_Builder();
$track_editor   = new WPCPM_Track_Editor( $track_builder );
$track_handlers = array();

foreach ( array( $track_builder, $track_editor ) as $owner ) {
	foreach ( get_class_methods( $owner ) as $method ) {
		if ( 0 === strpos( $method, 'handle_' ) ) {
			$track_handlers[ get_class( $owner ) . '::' . $method ] = array( $owner, $method );
		}
	}
}

$GLOBALS['uid']          = 1;
$GLOBALS['caps']         = true;
$GLOBALS['query_result'] = array();
$_POST                   = array( 'track' => 4040, 'wpcpm_question' => 'Hours', 'wpcpm_direction' => 'up', 'item' => 'automation' );
$track_flashes           = array();

$track_targets          = array();

foreach ( $track_handlers as $name => $handler ) {
	run( $name . ' (manager, no such track)', $handler );

	$flash                  = flashed( 1, WPCPM_Track_Builder::FLASH );
	$track_flashes[ $name ] = is_array( $flash ) && isset( $flash['status'] ) ? $flash['status'] : '';
	$track_targets[ $name ] = $GLOBALS['redirected_to'];
}

check( 'all seventeen reach their redirect with an error queued in the raw flash meta, the real WPCPM_Flash\'s',
    array( count( $track_handlers ), array_unique( array_values( $track_flashes ) ) ),
    array( 17, array( 'error' ) ) );

// Where each is sent, read right (the fix round of TESTS-DOCS-2): the builder's handlers name their
// screen with add_query_arg( key, value, url ), which this harness's stand-in did not know, so the
// section printed "redirect -> 4040", and the editor's add nothing to the list's address.
$list_screen    = 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder';
$publish_screen = $list_screen . '&wpcpm_publish=4040';

check( 'and each is sent to the screen it names: the list, the new track form, or the track\'s publish screen',
    $track_targets,
    array(
        'WPCPM_Track_Builder::handle_save'              => $list_screen,
        'WPCPM_Track_Builder::handle_duplicate'         => $list_screen,
        'WPCPM_Track_Builder::handle_new'               => $list_screen . '&wpcpm_new=1',
        'WPCPM_Track_Builder::handle_course'            => $list_screen,
        'WPCPM_Track_Builder::handle_switch_definition' => $list_screen,
        'WPCPM_Track_Builder::handle_switch_builtin'    => $list_screen,
        'WPCPM_Track_Builder::handle_refresh'           => $list_screen,
        'WPCPM_Track_Builder::handle_publish'           => $publish_screen,
        'WPCPM_Track_Builder::handle_unpublish'         => $publish_screen,
        'WPCPM_Track_Builder::handle_verify'            => $publish_screen,
        'WPCPM_Track_Builder::handle_tick'              => $publish_screen,
        'WPCPM_Track_Builder::handle_untick'            => $publish_screen,
        'WPCPM_Track_Editor::handle_add'                => $list_screen,
        'WPCPM_Track_Editor::handle_save'               => $list_screen,
        'WPCPM_Track_Editor::handle_move'               => $list_screen,
        'WPCPM_Track_Editor::handle_remove'             => $list_screen,
        'WPCPM_Track_Editor::handle_delete'             => $list_screen,
    ) );

$GLOBALS['uid']         = 30;
$GLOBALS['caps']        = false;
$GLOBALS['grants'][30]  = array( 'read' );
$GLOBALS['nonce_fails'] = true;
$track_answers          = array();

foreach ( $track_handlers as $name => $handler ) {
	run( $name . ' (a student, and the nonce fails too)', $handler );

	$track_answers[ $name ] = $GLOBALS['died_with'];
}

check( 'and every one refuses a student, who holds read, on the capability before the nonce, with nothing queued for them',
    array( array_unique( array_values( $track_answers ) ), flashed( 30, WPCPM_Track_Builder::FLASH ) ),
    array( array( 'You do not have permission to manage the program.' ), '' ) );

unset( $GLOBALS['grants'][30] );
$GLOBALS['nonce_fails'] = false;
$GLOBALS['caps']        = true;
$GLOBALS['uid']         = 1;
$_POST                  = array();

echo "\n=== A move the page asked for in the background is answered, refusal and all (BUILDER-4) ===\n";

// The deep check of 1.109.1, BUILDER-4: a background move the store refused, or one on a track gone
// since the page was drawn, was answered with a redirect. The script followed it to the Track
// Builder's page, read HTML where it wanted JSON and kept the row where it had moved it, and the
// page it followed took the refusal's notice, so nobody ever read it. Through the real store and
// the real WPCPM_Flash: the track here was switched back to its hand-written form in another tab.
$moved_track = WPCPM_Track_Store::create(
	array(
		'schema_version' => 1,
		'key'            => 'moving',
		'status'         => 'Moving Track',
		'label'          => 'Moving Track',
		'questions'      => array(
			'First'  => array( 'type' => 'text', 'label' => 'First question', 'group' => 'onboarding' ),
			'Second' => array( 'type' => 'text', 'label' => 'Second question', 'group' => 'onboarding' ),
		),
	)
);
update_post_meta( $moved_track, WPCPM_Track_Store::META_SOURCE, 'builtin' );

$background = array();

foreach ( array( 'refused by the store' => $moved_track, 'on a track since deleted' => 4041 ) as $case => $moved_id ) {
	$GLOBALS['json_sent'] = null;
	$_POST                = array( 'track' => $moved_id, 'wpcpm_question' => 'Second', 'wpcpm_direction' => 'up', 'wpcpm_async' => '1' );
	run( 'handle_move (in the background, ' . $case . ')', array( $track_editor, 'handle_move' ) );
	$background[ $case ] = array( $GLOBALS['json_sent'], flashed( 1, WPCPM_Track_Builder::FLASH ) );
}

check( 'each is answered as a refusal with its reason and a status the script reads as one, and leaves no notice for a page nobody sees',
    array( $background, array_keys( WPCPM_Track_Store::get( $moved_track )['questions'] ) ),
    array(
        array(
            'refused by the store'     => array( array( 'success' => false, 'data' => array( 'message' => 'A built-in track runs from its PHP, so it cannot be edited until it switches to its definition. It can be duplicated.' ), 'status' => 409 ), '' ),
            'on a track since deleted' => array( array( 'success' => false, 'data' => array( 'message' => 'That track does not exist.' ), 'status' => 404 ), '' ),
        ),
        array( 'First', 'Second' ),
    ) );

$_POST = array( 'track' => $moved_track, 'wpcpm_question' => 'Second', 'wpcpm_direction' => 'up' );
run( 'handle_move (without the script, refused by the store)', array( $track_editor, 'handle_move' ) );
$without_script = flashed( 1, WPCPM_Track_Builder::FLASH );

check( 'and the same press without the script still comes back with the refusal in the notice, since that is all the person reads',
    array( is_array( $without_script ) ? $without_script['status'] : '', is_array( $without_script ) ? $without_script['message'] : '' ),
    array( 'error', 'A built-in track runs from its PHP, so it cannot be edited until it switches to its definition. It can be duplicated.' ) );

unset( $GLOBALS['posts'][ $moved_track ], $GLOBALS['pmeta'][ $moved_track ] );
$GLOBALS['json_sent'] = null;
$_POST                = array();

echo "\n=== A draft holding both hours errors is put right one question at a time (the final fix wave, item 16) ===\n";

// The hours rule became a refusal in 1.110.0 (TRACKS-3), and a draft saved before it may hold both
// of its errors: Hours outside Total hours, and another question inside it. The editor refused a
// press on the first error of the whole definition, and each of the two presses that would put the
// draft right left the other question's error standing, so every press was refused. Through the
// real editor, the real store and the real WPCPM_Flash; the draft is planted through create(),
// which checks nothing, as a draft written before the rule was.
$hours_repair = array(
	'schema_version' => 1,
	'key'            => 'repair',
	'status'         => 'Repair Track',
	'label'          => 'Repair Track',
	'hue'            => 'teal',
	'questions'      => array(
		'Hours'    => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'onboarding', 'min' => 0, 'max' => 1000, 'step' => 1, 'airtable_type' => 'number' ),
		'Intruder' => array( 'type' => 'text', 'label' => 'A question in Total hours', 'group' => 'hours', 'airtable_type' => 'singleLineText' ),
	),
);

/**
 * What the store's check() refuses a track's stored draft with, one `code where` line an error.
 *
 * @param int $track_id The track.
 * @return string[]
 */
function planted_errors( $track_id ) {
	return array_map(
		function ( $error ) {
			return $error['code'] . ' ' . $error['where'];
		},
		WPCPM_Track_Store::check( $track_id, (array) WPCPM_Track_Store::get( $track_id ) )
	);
}

$repair_saved   = WPCPM_Track_Store::create( $hours_repair );
$repair_removed = WPCPM_Track_Store::create( $hours_repair );

check( 'planted, each draft holds both hours errors and nothing else',
    array( planted_errors( $repair_saved ), planted_errors( $repair_removed ) ),
    array( array( 'hours_group Hours', 'hours_only Intruder' ), array( 'hours_group Hours', 'hours_only Intruder' ) ) );

$hours_back = array( 'track' => $repair_saved, 'wpcpm_question' => 'Hours', 'wpcpm_column' => 'Hours', 'wpcpm_type' => 'number', 'wpcpm_label' => 'Hours', 'wpcpm_group' => 'hours', 'wpcpm_min' => '0', 'wpcpm_max' => '1000', 'wpcpm_step' => '1' );
$_POST      = $hours_back;
run( 'handle_save (Hours back into Total hours)', array( $track_editor, 'handle_save' ) );
$saved_flash = flashed( 1, WPCPM_Track_Builder::FLASH );

$_POST = array( 'track' => $repair_removed, 'wpcpm_question' => 'Intruder' );
run( 'handle_remove (the question in Total hours)', array( $track_editor, 'handle_remove' ) );
$removed_flash = flashed( 1, WPCPM_Track_Builder::FLASH );

check( 'saving Hours back into Total hours lands, and so does removing the other question, each leaving the other question\'s error alone for Publish to refuse',
    array(
        is_array( $saved_flash ) ? $saved_flash['status'] : '',
        WPCPM_Track_Store::get( $repair_saved )['questions']['Hours']['group'] ?? '',
        planted_errors( $repair_saved ),
        is_array( $removed_flash ) ? $removed_flash['status'] : '',
        array_keys( WPCPM_Track_Store::get( $repair_removed )['questions'] ?? array() ),
        planted_errors( $repair_removed ),
    ),
    array( 'success', 'hours', array( 'hours_only Intruder' ), 'success', array( 'Hours' ), array( 'hours_group Hours' ) ) );

// The question a press is about is still held to the rule: the other question, saved where it
// stands in Total hours, is refused on its own error; saved into Project, the draft is whole.
$intruder_press = array( 'track' => $repair_saved, 'wpcpm_question' => 'Intruder', 'wpcpm_column' => 'Intruder', 'wpcpm_type' => 'text', 'wpcpm_label' => 'A question in Total hours', 'wpcpm_group' => 'hours' );
$_POST          = $intruder_press;
run( 'handle_save (the other question, left in Total hours)', array( $track_editor, 'handle_save' ) );
$kept_flash = flashed( 1, WPCPM_Track_Builder::FLASH );

$_POST = array_merge( $intruder_press, array( 'wpcpm_group' => 'project' ) );
run( 'handle_save (the other question, into Project)', array( $track_editor, 'handle_save' ) );
$moved_flash = flashed( 1, WPCPM_Track_Builder::FLASH );

check( 'while the question pressed is still refused on its own hours error, and once it is moved too the draft is whole',
    array( is_array( $kept_flash ) ? $kept_flash['status'] : '', is_array( $kept_flash ) ? $kept_flash['message'] : '', is_array( $moved_flash ) ? $moved_flash['status'] : '', planted_errors( $repair_saved ) ),
    array( 'error', 'Intruder: Total hours holds the Hours question alone. The Student Report Card draws that group as the hours box, which shows nothing else, so this question would reach no student. Put it in Onboarding, Project or Wrap-up.', 'success', array() ) );

unset( $GLOBALS['posts'][ $repair_saved ], $GLOBALS['pmeta'][ $repair_saved ], $GLOBALS['posts'][ $repair_removed ], $GLOBALS['pmeta'][ $repair_removed ] );
$_POST = array();

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL HANDLERS REACHED A NORMAL OUTCOME\n" );
exit( $fail ? 1 : 0 );
