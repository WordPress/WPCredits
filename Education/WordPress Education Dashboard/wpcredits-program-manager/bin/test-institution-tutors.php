<?php
/**
 * Each school's tutor list, kept on the site (Institutions release 1, task 7).
 *
 * What is pinned here and why:
 *
 * - **The list is the school's own, in its record.** `add()` keeps a name and an address, trimmed and lowercase for
 *   the address, which is how a duplicate is told; `list_for()` is by name, A to Z, and `names()` is the sorted names,
 *   each once, which is what the enrollment form's tutor picker offers.
 * - **One row of history for each change that happened.** A refusal, a repeat and a remove of nobody write nothing.
 * - **Who may change it is the policy's, asked for managing members.** A representative or an Administrator may; a
 *   tutor, a member of another school, a stranger and nobody are refused with the one refusal, before anything is
 *   written or any lock is taken. A tutor reads the list and is drawn no form and no Remove.
 * - **Every write runs under the school's lock**, the one the details and the requests use.
 * - **Accepting a tutor invitation lists the account**, once, under its display name.
 *
 * Run from the plugin root:  php bin/test-institution-tutors.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']       = array( 'date_format' => 'F j, Y', 'time_format' => 'g:i a' );
$GLOBALS['umeta']      = array();
$GLOBALS['users']      = array();
$GLOBALS['posts']      = array();
$GLOBALS['pmeta']      = array();
$GLOBALS['uid']        = 0;
$GLOBALS['manage']     = array();
$GLOBALS['index']      = array();
$GLOBALS['index_read'] = 0;
$GLOBALS['settled']    = array();
$GLOBALS['notified']   = array();
$GLOBALS['queued']     = array();
$GLOBALS['inserted']   = array();
$GLOBALS['referer']    = array();
$GLOBALS['add_role']   = array();
$GLOBALS['set_role']   = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
/**
 * Enough of `WP_User` to tell `add_role()` from `set_role()`.
 *
 * `set_role()` replaces every role, which is what adopting a mentor's account must never
 * do. Both are recorded so the suite can assert which one ran.
 */
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array(), $login = '' ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = '' !== $login ? $login : strtolower( str_replace( ' ', '', $name ) );
		$this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
	public function add_role( $r ) {
		$GLOBALS['add_role'][] = array( $this->ID, $r );
		if ( ! in_array( $r, $this->roles, true ) ) { $this->roles[] = $r; }
	}
	public function remove_role( $r ) { $this->roles = array_values( array_diff( $this->roles, array( $r ) ) ); }
	public function set_role( $r ) { $GLOBALS['set_role'][] = array( $this->ID, $r ); $this->roles = '' === $r ? array() : array( $r ); }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_content = '', $post_type = '', $post_status = 'publish', $post_author = 0, $post_date_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $s, $p, $n, $d = null ) { return 1 === (int) $n ? $s : $p; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function sanitize_text_field( $s ) { return trim( str_replace( array( "\r", "\n" ), '', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function sanitize_user( $u, $strict = false ) { return preg_replace( '/[^a-zA-Z0-9 _.\-@]/', '', (string) $u ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action( $h, $c = null, $p = 10, $n = 1 ) { $GLOBALS['hooks'][] = $h; }
function remove_filter() { return true; }
function add_filter() {}
function register_post_type() {}
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function human_time_diff( $a, $b = 0 ) { return '4 hours'; }
function wp_specialchars_decode( $s, $q = null ) { return html_entity_decode( (string) $s, ENT_QUOTES ); }
function get_bloginfo( $k = 'name' ) { return 'WordPress Education Dashboard'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
// The flash slashes what it writes for core's user meta, which unslashes it; this one keeps what it
// is handed, so the slash is the identity here (bin/test-flash.php holds the flash to core's).
function wp_slash( $v ) { return $v; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
require_once __DIR__ . '/stubs/caps.php';
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && 0 === strcasecmp( (string) $user->user_email, (string) $value ) ) { return $user; }
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
		if ( 'login' === $field && $user->user_login === (string) $value ) { return $user; }
	}
	return false;
}
/** Core's link to a profile, '' for a reader who may not edit that account: the suite grants `edit_user` by name. */
function get_edit_user_link( $user_id = 0 ) { return current_user_can( 'edit_user', (int) $user_id ) ? 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $user_id : ''; }
function username_exists( $login ) { $u = get_user_by( 'login', $login ); return $u ? $u->ID : false; }
function wp_generate_password( $l = 12, $s = true, $e = false ) { return str_repeat( 'x', (int) $l ); }
/** `get_users()` by meta key and value, matching the way MySQL collates: case-insensitively. */
function get_users( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		$value = $GLOBALS['umeta'][ (int) $id ][ $a['meta_key'] ?? '' ] ?? null;
		if ( null !== $value && 0 === strcasecmp( (string) $value, (string) ( $a['meta_value'] ?? '' ) ) ) { $out[] = $user; }
	}
	return $out;
}
function wp_insert_user( $args ) {
	static $next = 100;
	$GLOBALS['inserted'][] = $args;
	if ( get_user_by( 'email', $args['user_email'] ?? '' ) ) {
		return new WP_Error( 'existing_user_email', 'That address already has an account.' );
	}
	$id   = ++$next;
	$user = new WP_User( $id, (string) ( $args['display_name'] ?? '' ), (string) ( $args['user_email'] ?? '' ), array(), (string) ( $args['user_login'] ?? '' ) );
	if ( ! empty( $args['role'] ) ) { $user->roles = array( $args['role'] ); }
	$GLOBALS['users'][ $id ] = $user;
	return $id;
}
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
function get_post_time( $f, $gmt = false, $post = null, $translate = false ) { return strtotime( $post->post_date_gmt . ' UTC' ); }
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	return $single ? ( $rows ? $rows[0] : '' ) : $rows;
}
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
function get_posts( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ( $a['post_type'] ?? '' ) !== $post->post_type ) { continue; }
		if ( ! empty( $a['meta_query'] ) ) {
			$clause = $a['meta_query'][0];
			$value  = $GLOBALS['pmeta'][ $post->ID ][ $clause['key'] ][0] ?? null;
			if ( null === $value || 0 !== strcasecmp( (string) $value, (string) $clause['value'] ) ) { continue; }
		}
		if ( ! empty( $a['meta_key'] ) ) {
			$value = $GLOBALS['pmeta'][ $post->ID ][ $a['meta_key'] ][0] ?? null;
			if ( null === $value || (string) $value !== (string) $a['meta_value'] ) { continue; }
		}
		$out[] = $post;
	}
	$out = array_reverse( $out );
	$n   = (int) ( $a['numberposts'] ?? -1 );
	if ( $n > 0 ) { $out = array_slice( $out, 0, $n ); }
	if ( 'ids' === ( $a['fields'] ?? '' ) ) { $out = array_map( function ( $p ) { return $p->ID; }, $out ); }
	return $out;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function wp_update_post( $a, $e = false ) { return (int) ( $a['ID'] ?? 0 ); }
function wp_cache_delete( $k, $g = '' ) { return true; }
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['referer'][] = $a; return true; }
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect:' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die:' . $m ); }
function add_query_arg( $args, $url = '' ) {
	return (string) $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . http_build_query( (array) $args );
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once __DIR__ . '/stubs/stamps.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

if ( ! class_exists( 'WPCPM_Institution_Roster' ) ) {
	/** Only the switcher argument, copied and checked against the real class by a test below. */
	class WPCPM_Institution_Roster {
		const ARG_VIEW = 'wpcpm_institution_view';
	}
}
if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
		public static function is_record_id( $v ) { return (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $v ) ); }
	}
}
if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	class WPCPM_Students_Sync {
		const META_RECORD_ID   = 'wpcpm_student_record_id';
		const META_INSTITUTION = 'wpcpm_student_institution';
	}
}
if ( ! class_exists( 'WPCPM_Institutions_Index' ) ) {
	class WPCPM_Institutions_Index {
		public static function read() { return array( 'v' => 1, 'read' => $GLOBALS['index_read'], 'rows' => $GLOBALS['index'] ); }
		public static function rows() { return $GLOBALS['index']; }
		public static function row( $r ) { return $GLOBALS['index'][ $r ] ?? null; }
		public static function has( $r ) { return isset( $GLOBALS['index'][ $r ] ); }
	}
}
if ( ! class_exists( 'WPCPM_Roster_Index' ) ) {
	/** Only what the policy's `subject_index_row()` would reach for; this card asks for none of it. */
	class WPCPM_Roster_Index {
		public static function rows( $id ) { return array(); }
	}
}
if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	/** Contract: the gate the member ground applies, and nothing else is read from here. */
	class WPCPM_Institution_Agreement {
		public static function is_settled( $id ) { return in_array( $id, $GLOBALS['settled'], true ); }
	}
}
if ( ! class_exists( 'WPCPM_Institutions' ) ) {
	/** Contract 12: records the context and runs the builder once, the way `send()` would. */
	class WPCPM_Institutions {
		public static function notify_managers( $context, $build ) {
			$GLOBALS['notified'][] = array( 'context' => $context, 'mail' => call_user_func( $build, new WP_User( 1, 'Manager', 'm@example.test' ) ) );
			return 1;
		}
	}
}
if ( ! class_exists( 'WPCPM_Mail' ) ) {
	/**
	 * The invitation queue: recorded, never sent, exactly as the real one is asked to behave.
	 *
	 * **Including the drop.** The real `queue_invites()` skips anybody already carrying any of
	 * the four invited stamps (bin/stubs/stamps.php, the map every stand-in mail class reads),
	 * whatever it holds, and `attach()` never clears one, so an adopted mentor and a re-added
	 * former member are queued nothing at all. A stub that queued everybody let the card
	 * promise an invitation that would never be sent, which is the whole of what these
	 * assertions are for. It returns how many were actually queued, as the real one does.
	 */
	class WPCPM_Mail {
		/** @var bool Whether the last queueing found the queue's lock held, as the real one says. */
		public static $busy = false;
		public static function queue_invites( array $user_ids ) {
			// The queue's lock, its row in the options table, held by another request: nothing is
			// queued, and `queue_was_busy()` says so (the fix wave of 1.123.58).
			self::$busy = isset( $GLOBALS['opts']['wpcpm_invite_queue_lock'] );
			if ( self::$busy ) {
				return 0;
			}
			$fresh = 0;
			foreach ( $user_ids as $id ) {
				// Any of the four stamps, there whatever it holds, as the real one reads them.
				foreach ( WPCPM_STUB_STAMPS as $meta ) {
					if ( isset( $GLOBALS['umeta'][ (int) $id ] ) && array_key_exists( $meta, $GLOBALS['umeta'][ (int) $id ] ) ) {
						continue 2;
					}
				}
				$GLOBALS['queued'][] = (int) $id;
				++$fresh;
			}
			return $fresh;
		}
		public static function queue_was_busy() {
			return self::$busy;
		}
	}
}


class WPCPM_Institutions_Dashboard { public static function page_url() { return 'https://example.test/institution-dashboard/'; } }
require_once __DIR__ . '/stubs/tutor-list.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-record.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-invite.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-people.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-tutors.php';

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}
function has( $haystack, $needle ) { return false !== strpos( $haystack, $needle ); }
function flash_status() {
	$f = $GLOBALS['umeta'][ $GLOBALS['uid'] ][ WPCPM_Flash::META ]['institution_tutors'] ?? null;
	return is_array( $f ) ? $f['status'] : $f;
}
function clear_flash() { unset( $GLOBALS['umeta'][ $GLOBALS['uid'] ][ WPCPM_Flash::META ] ); }
/** Post to a handler as a user, and report how it ended: a redirect, a wp_die, or nothing. */
function press( $uid, $method, array $post ) {
	$GLOBALS['uid'] = (int) $uid;
	$_POST          = $post;
	try {
		call_user_func( array( 'WPCPM_Institution_Tutors', $method ) );
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
	return '';
}
function card( $viewer, $record ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_People::render( $record, array( 'can_manage' => false, 'cohort' => '', 'filters' => array(), 'read' => 123 ) );
	return (string) ob_get_clean();
}
function section( $viewer, $record ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_Tutors::render( $record, array() );
	return (string) ob_get_clean();
}
function rows_of( $record, $kind ) {
	return array_values( array_filter( WPCPM_Institution_Audit::entries_for( $record, 0 ), function ( $e ) use ( $kind ) { return $kind === $e['kind']; } ) );
}
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

$A = 'recSEED0000000001';
$B = 'recSEED0000000002';

$GLOBALS['index'] = array(
	$A => array( 'record_id' => $A, 'name' => 'TEST - Institution 20 ', 'stage' => 'Confirmed', 'city' => 'Test', 'country_name' => 'Poland', 'website' => '', 'contact_person' => '', 'contact_email' => '' ),
	$B => array( 'record_id' => $B, 'name' => 'Universidad Example', 'stage' => 'Confirmed', 'city' => 'Example', 'country_name' => 'Costa Rica', 'website' => '', 'contact_person' => '', 'contact_email' => '' ),
);
$GLOBALS['index_read'] = 1756000000;
$GLOBALS['settled']    = array( $A, $B );
$GLOBALS['users']      = array(
	1  => new WP_User( 1, 'Manager', 'manager@example.test', array( 'administrator' ) ),
	7  => new WP_User( 7, 'Anna Kowalska', 'anna@example.test', array( 'subscriber' ) ),
	8  => new WP_User( 8, 'Rob Rep', 'rob@example.test', array( 'subscriber' ) ),
	9  => new WP_User( 9, 'Cleo Beta', 'cleo@example.test', array( 'subscriber' ) ),
	50 => new WP_User( 50, 'Tess Tutor', 'tess@example.test', array( 'subscriber' ) ),
	60 => new WP_User( 60, 'Sam Stranger', 'sam@example.test', array( 'subscriber' ) ),
);
$GLOBALS['manage'] = array( 1 );

WPCPM_Institution_Members::attach( 7, $A, 'manager', 1 );
WPCPM_Institution_Members::attach( 50, $A, 'invited', 7, 0, 'tutor' );
WPCPM_Institution_Members::attach( 8, $A, 'manager', 1 );
WPCPM_Institution_Members::attach( 9, $B, 'manager', 1 );

echo "=== The constants and the list ===\n";

ck( 'the names the brief fixes', array( WPCPM_Institution_Tutors::RECORD_KEY, WPCPM_Institution_Tutors::ACTION_ADD, WPCPM_Institution_Tutors::ACTION_REMOVE, WPCPM_Institution_Tutors::LOG_ADDED, WPCPM_Institution_Tutors::LOG_REMOVED ), array( 'tutors', 'wpcpm_institution_tutor_add', 'wpcpm_institution_tutor_remove', 'tutor_added', 'tutor_removed' ) );
ck( 'a school with no list has no tutors', array( WPCPM_Institution_Tutors::list_for( $A ), WPCPM_Institution_Tutors::names( $A ) ), array( array(), array() ) );
ck( 'and an ID that is not a record ID has none either', array( WPCPM_Institution_Tutors::list_for( 'nope' ), WPCPM_Institution_Tutors::names( '' ) ), array( array(), array() ) );

$GLOBALS['uid'] = 7;
ck( 'a tutor is added', WPCPM_Institution_Tutors::add( $A, 'Zofia Zielinska', 'zofia@example.test', 7, 'member' ), true );
ck( 'a second is added', WPCPM_Institution_Tutors::add( $A, '  Adam Adamski ', ' Adam@Example.test ', 7, 'member' ), true );
ck( 'the list is by name, A to Z, address trimmed and lowercase', WPCPM_Institution_Tutors::list_for( $A ), array(
	array( 'name' => 'Adam Adamski', 'email' => 'adam@example.test' ),
	array( 'name' => 'Zofia Zielinska', 'email' => 'zofia@example.test' ),
) );
ck( 'names() is the names, sorted', WPCPM_Institution_Tutors::names( $A ), array( 'Adam Adamski', 'Zofia Zielinska' ) );
ck( 'the same address in another case is not added again', WPCPM_Institution_Tutors::add( $A, 'Adam Again', 'ADAM@example.TEST', 7, 'member' ), false );
ck( 'and the list is as it was', WPCPM_Institution_Tutors::names( $A ), array( 'Adam Adamski', 'Zofia Zielinska' ) );
ck( 'two tutors of one name are each listed, and the name is offered once', array( WPCPM_Institution_Tutors::add( $A, 'Adam Adamski', 'adam.two@example.test', 7, 'member' ), WPCPM_Institution_Tutors::names( $A ), count( WPCPM_Institution_Tutors::list_for( $A ) ) ), array( true, array( 'Adam Adamski', 'Zofia Zielinska' ), 3 ) );
ck( 'another school\'s list is its own', WPCPM_Institution_Tutors::names( $B ), array() );

$empty = WPCPM_Institution_Tutors::add( $A, '   ', 'x@example.test', 7, 'member' );
$long  = WPCPM_Institution_Tutors::add( $A, str_repeat( 'n', 101 ), 'x@example.test', 7, 'member' );
$edge  = WPCPM_Institution_Tutors::add( $A, str_repeat( 'n', 100 ), 'edge@example.test', 7, 'member' );
$bad   = WPCPM_Institution_Tutors::add( $A, 'Bad Address', 'not an address', 7, 'member' );
$none  = WPCPM_Institution_Tutors::add( $A, 'No Address', '', 7, 'member' );
ck( 'an empty name is refused', array( is_wp_error( $empty ), is_wp_error( $empty ) ? $empty->get_error_code() : '' ), array( true, 'wpcpm_tutor_name' ) );
ck( 'a name over 100 characters is refused, and one of exactly 100 is not', array( is_wp_error( $long ) ? $long->get_error_code() : '', $edge ), array( 'wpcpm_tutor_name', true ) );
ck( 'an address that is not one is refused, and so is none', array( is_wp_error( $bad ) ? $bad->get_error_code() : '', is_wp_error( $none ) ? $none->get_error_code() : '' ), array( 'wpcpm_tutor_email', 'wpcpm_tutor_email' ) );
ck( 'the refusals added nothing', count( WPCPM_Institution_Tutors::list_for( $A ) ), 4 );
ck( 'the multibyte name is counted in characters, not bytes', WPCPM_Institution_Tutors::add( $A, str_repeat( 'ł', 100 ), 'multi@example.test', 7, 'member' ), true );

ck( 'removing is by address, in any case', WPCPM_Institution_Tutors::remove( $A, ' ADAM.TWO@example.test ', 7, 'member' ), true );
ck( 'and the one with the same name stays', in_array( 'adam@example.test', array_column( WPCPM_Institution_Tutors::list_for( $A ), 'email' ), true ), true );
ck( 'removing an address that is not listed is false', WPCPM_Institution_Tutors::remove( $A, 'ghost@example.test', 7, 'member' ), false );
WPCPM_Institution_Tutors::remove( $A, 'edge@example.test', 7, 'member' );
WPCPM_Institution_Tutors::remove( $A, 'multi@example.test', 7, 'member' );

echo "\n=== Each change is one row of history ===\n";

function row_for( $record, $kind, $email ) {
	foreach ( rows_of( $record, $kind ) as $row ) {
		if ( ( $row['data']['email'] ?? '' ) === $email ) { return $row; }
	}
	return null;
}
ck( 'five adds that worked and three removals that did (before the handlers), one row each, and none for a refusal or a repeat', array( count( rows_of( $A, 'tutor_added' ) ), count( rows_of( $A, 'tutor_removed' ) ) ), array( 5, 3 ) );
$row = row_for( $A, 'tutor_added', 'zofia@example.test' );
ck( 'an add names the person and the address in its facts, on the actor\'s ground', array( $row['data']['name'], $row['actor'], $row['ground'], $row['subject'] ), array( 'Zofia Zielinska', 7, 'member', $A ) );
ck( 'and says so in the words of the brief', $row['message'], 'Zofia Zielinska was added to the tutors.' );
$gone = row_for( $A, 'tutor_removed', 'adam.two@example.test' );
ck( 'a removal names the person and the address as they were listed', array( $gone['data']['name'], $gone['message'] ), array( 'Adam Adamski', 'Adam Adamski was removed from the tutors.' ) );

echo "\n=== The People card draws the section ===\n";

$rep = card( 7, $A );
ck( 'a representative sees the Tutors subtitle after the members', array( has( $rep, '<h4 class="wpcpm-people__subtitle">Tutors</h4>' ), strpos( $rep, 'Tess Tutor' ) < strpos( $rep, '>Tutors</h4>' ) ), array( true, true ) );
ck( 'each tutor with name and address', array( has( $rep, 'Adam Adamski' ), has( $rep, 'adam@example.test' ), has( $rep, 'Zofia Zielinska' ), has( $rep, 'zofia@example.test' ) ), array( true, true, true, true ) );
ck( 'a Remove control on each, asking the sentence of the brief', array(
	substr_count( $rep, 'name="action" value="wpcpm_institution_tutor_remove"' ),
	has( $rep, 'data-wpcpm-confirm="Remove Zofia Zielinska from the tutors? Their account, if they have one, stays until you remove it above."' ),
), array( 2, true ) );
ck( 'the remove form posts the address and carries a nonce keyed to the school', array( has( $rep, 'name="email" value="zofia@example.test"' ), has( $rep, 'value="nonce-wpcpm_institution_tutor_remove_' . $A . '"' ), has( $rep, 'name="record" value="' . $A . '"' ) ), array( true, true, true ) );
ck( 'and an Add a tutor form: Name, Email, Add', array(
	has( $rep, 'Add a tutor' ),
	has( $rep, 'name="action" value="wpcpm_institution_tutor_add"' ),
	has( $rep, 'value="nonce-wpcpm_institution_tutor_add_' . $A . '"' ),
	1 === preg_match( '/<label for="wpcpm-tutor-name-' . $A . '">Name<\/label>/', $rep ),
	1 === preg_match( '/<label for="wpcpm-tutor-email-' . $A . '">Email<\/label>/', $rep ),
	has( $rep, 'name="name"' ),
	has( $rep, 'type="email"' ),
	1 === preg_match( '/<button type="submit"[^>]*>Add<\/button>/', $rep ),
), array( true, true, true, true, true, true, true, true ) );
ck( 'an Administrator sees the same, on the manager ground', array( has( card( 1, $A ), 'wpcpm_institution_tutor_add' ), has( card( 1, $A ), 'wpcpm_institution_tutor_remove' ) ), array( true, true ) );

$tutor = card( 50, $A );
ck( 'a tutor sees the list, each with name and address, and no form and no Remove', array(
	has( $tutor, '>Tutors</h4>' ),
	has( $tutor, 'Adam Adamski' ),
	has( $tutor, 'zofia@example.test' ),
	has( $tutor, 'wpcpm_institution_tutor_add' ),
	has( $tutor, 'wpcpm_institution_tutor_remove' ),
	has( $tutor, 'Add a tutor' ),
), array( true, true, true, false, false, false ) );
ck( 'a stranger is drawn nothing of the card, the section included', array( card( 60, $A ), section( 60, $A ) ), array( '', '' ) );
ck( 'a member of another school sees nothing of this school\'s list', array( has( card( 9, $B ), 'Adam Adamski' ), section( 9, $A ) ), array( false, '' ) );
ck( 'a school with none listed says so, and still offers the form to a representative', array( has( card( 9, $B ), 'No tutors are listed yet.' ), has( card( 9, $B ), 'wpcpm_institution_tutor_add' ) ), array( true, true ) );
ck( 'the section names no Airtable', has( $rep, 'Airtable' ), false );

echo "\n=== A representative adds and removes through the handlers ===\n";

$GLOBALS['locks'] = array();
$GLOBALS['released'] = array();
$lock_name        = WPCPM_Institution_Request::school_lock_name( $A );
$ret              = 'redirect:https://example.test/institution-dashboard/#wpcpm-tutors';
ck( 'an add ends on the card', press( 7, 'handle_add', array( 'record' => $A, 'name' => 'Ewa Nowak', 'email' => 'Ewa@example.test' ) ), $ret );
ck( 'the tutor is listed and the add ran under the school\'s lock', array( in_array( 'ewa@example.test', array_column( WPCPM_Institution_Tutors::list_for( $A ), 'email' ), true ), $GLOBALS['locks'], $GLOBALS['released'] ), array( true, array( $lock_name ), array( $lock_name ) ) );
ck( 'and the card says so', flash_status(), 'added' );
clear_flash();
// The flash is read once for a user in a request, so the sentence is read on a representative's first draw of the card.
press( 8, 'handle_add', array( 'record' => $A, 'name' => 'Rob Tutor', 'email' => 'robtutor@example.test' ) );
ck( 'in the words of the card, on the card of that school only', array( has( card( 8, $A ), 'The tutor was added to the list.' ), has( card( 9, $B ), 'was added to the list' ) ), array( true, false ) );
WPCPM_Institution_Tutors::remove( $A, 'robtutor@example.test', 8, 'member' );
ck( 'the row\'s ground is the one the policy gave', row_for( $A, 'tutor_added', 'ewa@example.test' )['ground'], 'member' );
ck( 'an add of a listed address is told so, and writes nothing', array( press( 7, 'handle_add', array( 'record' => $A, 'name' => 'Ewa Again', 'email' => 'ewa@example.test' ) ), flash_status(), count( rows_of( $A, 'tutor_added' ) ) ), array( $ret, 'already', 7 ) );
ck( 'an empty name is told so', array( press( 7, 'handle_add', array( 'record' => $A, 'name' => '', 'email' => 'e2@example.test' ) ), flash_status() ), array( $ret, 'name' ) );
ck( 'a bad address is told so', array( press( 7, 'handle_add', array( 'record' => $A, 'name' => 'E Two', 'email' => 'nope' ) ), flash_status() ), array( $ret, 'email' ) );
clear_flash();
$GLOBALS['lock_held'] = $lock_name;
ck( 'with the school\'s lock held by another request nothing is added, and it is said', array( press( 7, 'handle_add', array( 'record' => $A, 'name' => 'Late Comer', 'email' => 'late@example.test' ) ), flash_status(), in_array( 'late@example.test', array_column( WPCPM_Institution_Tutors::list_for( $A ), 'email' ), true ) ), array( $ret, 'busy', false ) );
// A remove with the lock held is not "nothing to remove" (1.123.84): the tutor is still listed, so the card
// is given the busy outcome, the one an add with the lock held is given, and the tutor stays.
clear_flash();
ck( 'with the school\'s lock held by another request a listed tutor is not removed, and the card says the list is busy', array( press( 7, 'handle_remove', array( 'record' => $A, 'email' => 'ewa@example.test' ) ), flash_status(), in_array( 'ewa@example.test', array_column( WPCPM_Institution_Tutors::list_for( $A ), 'email' ), true ) ), array( $ret, 'busy', true ) );
unset( $GLOBALS['lock_held'] );
ck( 'a remove ends on the card and the tutor is gone', array( press( 7, 'handle_remove', array( 'record' => $A, 'email' => 'ewa@example.test' ) ), in_array( 'ewa@example.test', array_column( WPCPM_Institution_Tutors::list_for( $A ), 'email' ), true ), flash_status() ), array( $ret, false, 'removed' ) );
ck( 'a second remove of the same address is told so', array( press( 7, 'handle_remove', array( 'record' => $A, 'email' => 'ewa@example.test' ) ), flash_status() ), array( $ret, 'gone' ) );
clear_flash();
ck( 'an Administrator adds too, on the manager ground, and returns to the school\'s view', array(
	press( 1, 'handle_add', array( 'record' => $A, 'name' => 'Ola Admin', 'email' => 'ola@example.test' ) ),
	row_for( $A, 'tutor_added', 'ola@example.test' )['ground'],
), array( 'redirect:https://example.test/institution-dashboard/?wpcpm_institution_view=' . $A . '#wpcpm-tutors', 'manager' ) );
WPCPM_Institution_Tutors::remove( $A, 'ola@example.test', 1, 'manager' );

echo "\n=== Hand-made posts are refused, and nothing is written ===\n";

$refusal = 'wp_die:' . WPCPM_Institution_Policy::refusal()->get_error_message();
$before  = array( serialize( WPCPM_Institution_Tutors::list_for( $A ) ), serialize( $GLOBALS['pmeta'] ), serialize( $GLOBALS['posts'] ), serialize( $GLOBALS['opts'] ) );
$GLOBALS['locks'] = array();
$out = array(
	press( 50, 'handle_add', array( 'record' => $A, 'name' => 'Sneaky', 'email' => 'sneaky@example.test' ) ),
	press( 50, 'handle_remove', array( 'record' => $A, 'email' => 'zofia@example.test' ) ),
	press( 9, 'handle_add', array( 'record' => $A, 'name' => 'Sneaky', 'email' => 'sneaky@example.test' ) ),
	press( 9, 'handle_remove', array( 'record' => $A, 'email' => 'zofia@example.test' ) ),
	press( 60, 'handle_add', array( 'record' => $A, 'name' => 'Sneaky', 'email' => 'sneaky@example.test' ) ),
	press( 0, 'handle_remove', array( 'record' => $A, 'email' => 'zofia@example.test' ) ),
	press( 7, 'handle_add', array( 'record' => 'not-a-record', 'name' => 'Sneaky', 'email' => 'sneaky@example.test' ) ),
	press( 7, 'handle_remove', array( 'email' => 'zofia@example.test' ) ),
);
ck( 'a tutor, a member of another school, a stranger, nobody and a post with no school are each refused with the one refusal', $out, array_fill( 0, 8, $refusal ) );
ck( 'the list, every meta, every post and every option are as they were, and no lock was taken', array(
	serialize( WPCPM_Institution_Tutors::list_for( $A ) ) === $before[0],
	serialize( $GLOBALS['pmeta'] ) === $before[1],
	serialize( $GLOBALS['posts'] ) === $before[2],
	serialize( $GLOBALS['opts'] ) === $before[3],
	$GLOBALS['locks'],
), array( true, true, true, true, array() ) );
ck( 'a member of B posting B\'s own school adds to B only', array( press( 9, 'handle_add', array( 'record' => $B, 'name' => 'Bea Beta', 'email' => 'bea@example.test' ) ), WPCPM_Institution_Tutors::names( $B ), count( WPCPM_Institution_Tutors::list_for( $A ) ) ), array( $ret, array( 'Bea Beta' ), 2 ) );

echo "\n=== A tutor invitation accepted adds the address to the list ===\n";

ck( 'accepting as a tutor adds the account\'s address under its display name', array( WPCPM_Institution_Tutors::accepted( $A, 51 ), WPCPM_Institution_Tutors::accepted( $A, 0 ) ), array( false, false ) );
$GLOBALS['users'][51] = new WP_User( 51, 'Tara Tutor', 'Tara@Example.test', array( 'subscriber' ) );
ck( 'an account that is not there adds nothing', array( WPCPM_Institution_Tutors::accepted( $A, 51 ), in_array( 'tara@example.test', array_column( WPCPM_Institution_Tutors::list_for( $A ), 'email' ), true ) ), array( true, true ) );
ck( 'and the row is on the system ground, with the account as the actor', array( row_for( $A, 'tutor_added', 'tara@example.test' )['ground'], row_for( $A, 'tutor_added', 'tara@example.test' )['actor'], row_for( $A, 'tutor_added', 'tara@example.test' )['data']['name'] ), array( 'system', 51, 'Tara Tutor' ) );
ck( 'the address already listed is not added again', array( WPCPM_Institution_Tutors::accepted( $A, 51 ), count( rows_of( $A, 'tutor_added' ) ) ), array( false, 9 ) );
$GLOBALS['users'][52] = new WP_User( 52, '', 'nameless@example.test', array( 'subscriber' ), 'nameless' );
ck( 'an account with no display name is listed under its login', array( WPCPM_Institution_Tutors::accepted( $A, 52 ), in_array( 'nameless', array_column( WPCPM_Institution_Tutors::list_for( $A ), 'name' ), true ) ), array( true, true ) );

echo "\n=== The shape of the file ===\n";

$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-tutors.php' );
foreach ( array( 'handle_add', 'handle_remove' ) as $name ) {
	$body = method_body( $src, $name );
	ck( $name . '() runs the nonce, then the policy for managing members, and writes through add or remove', array(
		false !== strpos( $body, 'check_admin_referer' ),
		1 === substr_count( $body, 'WPCPM_Institution_Policy::ACT_MANAGE_MEMBERS' ),
		false !== strpos( $body, 'check_admin_referer' ) && false !== strpos( $body, 'WPCPM_Institution_Policy::decide(' ) && strpos( $body, 'check_admin_referer' ) < strpos( $body, 'WPCPM_Institution_Policy::decide(' ),
		false === strpos( $body, 'current_user_can' ),
	), array( true, true, true, true ) );
}
ck( 'writes are made under the school\'s lock', array( has( $src, 'WPCPM_Institution_Request::school_lock_name(' ), has( $src, 'WPCPM_Lock::take(' ), has( $src, 'WPCPM_Lock::release(' ) ), array( true, true, true ) );
$GLOBALS['hooks'] = array();
WPCPM_Institution_People::init();
ck( 'the two handlers are registered from the People card\'s init, after its own three', array_slice( $GLOBALS['hooks'], 3 ), array( 'admin_post_wpcpm_institution_tutor_add', 'admin_post_wpcpm_institution_tutor_remove' ) );
ck( 'the file reads nothing from Airtable', array( has( $src, 'WPCPM_Airtable' ), has( $src, 'fetch_all' ) ), array( false, false ) );
ck( 'and holds no dash but the plain hyphen', preg_match( '/\x{2013}|\x{2014}/u', $src . (string) file_get_contents( __FILE__ ) ), 0 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
