<?php
/**
 * The People card and the three membership handlers.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The card lists every live member with how they came by their access and since when,
 *   because "who can see our students" is the first question a school asks and a list
 *   without provenance cannot answer the second one.
 * - The member whose address is the one Airtable holds is marked, and the mark takes nothing
 *   away: it is information about who the program writes to, not a rank. It used to suppress
 *   that row's control on both surfaces, which left an institution whose contact had gone
 *   unrepairable by their colleagues, by a program manager and by themselves alike.
 * - The nonce on every Remove is keyed to the subject account, so a token for removing one
 *   person is not a token for removing another.
 * - Every manager-side handler runs the capability, then the nonce, then `decide()` (spec
 *   5.4), and the order assertions fail when one of the three is missing rather than passing
 *   on `false < 12`.
 * - One outcome message, on the card of the institution it happened to, and none at all for
 *   somebody the card will no longer draw.
 * - The card claims an invitation only when one was queued: `WPCPM_Mail::queue_invites()`
 *   drops an account that has been invited before, and `attach()` never clears that stamp.
 * - **The subject's institution is read from the subject's own stamp and never from the
 *   form.** A member of B posting a member of A's user ID is decided against A and gets the
 *   one refusal, byte for byte, whatever the form says the institution is.
 * - A member removing themselves as the last member is allowed and warned rather than
 *   refused, and `detach()` tells the program managers there is nobody left.
 * - The manager backstop refuses any existing account except a former member of this
 *   institution or a mentor, and a mentor keeps the Mentor role: `add_role()`, never
 *   `set_role()`, or the backstop would quietly demote somebody's mentor account.
 * - The backstop is one institution's Manage members view on the Institutions screen's
 *   Accounts tab, and a press made there comes back to it: every form on the block posts
 *   the screen's flag, the block carries the anchor the handlers land on, and the address
 *   is the institution's own (`manager_url()`), which the People card links a manager to
 *   as well. A press made on the Institution Dashboard comes back to the dashboard.
 *
 * Run from the plugin root:  php bin/test-institution-people.php
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
require_once __DIR__ . '/stubs/view-faults.php';
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
		$out[] = $post;
	}
	$out = array_reverse( $out );
	$n   = (int) ( $a['numberposts'] ?? -1 );
	if ( $n > 0 ) { $out = array_slice( $out, 0, $n ); }
	return $out;
}
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['referer'][] = $a; return true; }
// Core's shape (an id, then the referer field) while `core_nonce` is set (Stage B, Task 20), so the old branch draws the
// referer the parts' nonce draws; the earlier sections of this suite read the short one.
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) {
	if ( ! empty( $GLOBALS['core_nonce'] ) ) {
		echo '<input type="hidden" id="' . esc_attr( $n ) . '" name="' . esc_attr( $n ) . '" value="nonce-' . esc_attr( $a ) . '" />';
		if ( $r ) { wp_referer_field(); }
		return;
	}
	echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />';
}
function wp_referer_field( $display = true ) {
	$field = '<input type="hidden" name="_wp_http_referer" value="/institution-dashboard/?wpcpm_view=institution" />';
	if ( $display ) { echo $field; }
	return $field;
}
function wp_create_nonce( $a = -1 ) { return 'nonce-' . $a; }
function sanitize_html_class( $c, $f = '' ) { $c = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); return '' === $c ? (string) $f : $c; }
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
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

// The template registry, which every email's builder fills its words from since 1.122.22.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
// The real class, not a stub: the card now draws its three renderers directly, so a stand-in
// that answered every call would let the card drift from what `render_form()` actually checks.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-invite.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-people.php';
// The tutor list the card draws after its members, over an in-memory record: bin/test-institution-tutors.php holds it to the real one.
require_once __DIR__ . '/stubs/tutor-list.php';
class WPCPM_Institution_Record {
	public static function get( $id ) { return $GLOBALS['kept'][ $id ] ?? array(); }
	public static function set( $id, array $answers ) { $GLOBALS['kept'][ $id ] = array_merge( $GLOBALS['kept'][ $id ] ?? array(), $answers ); return true; }
	public static function forget_cached( $id ) {}
}
$GLOBALS['kept'] = array();
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-tutors.php';
// The parts every card of this page is drawn with (Institution Dashboard, Task 23: the old markup is gone).
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-card.php';

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
function meta( $id, $k ) { return $GLOBALS['umeta'][ $id ][ $k ] ?? null; }
function flash() { return $GLOBALS['umeta'][ $GLOBALS['uid'] ][ WPCPM_Flash::META ]['institution_people'] ?? null; }
function flash_status() { $f = flash(); return is_array( $f ) ? $f['status'] : $f; }
function clear_flash() { unset( $GLOBALS['umeta'][ $GLOBALS['uid'] ][ WPCPM_Flash::META ] ); }
/** Run a handler and report how it ended: a redirect, a wp_die, or nothing at all. */
function run( $method ) {
	try {
		call_user_func( array( 'WPCPM_Institution_People', $method ) );
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
	return '';
}
function render_card( $viewer, $record, $can_manage = false ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_People::render( $record, array( 'can_manage' => $can_manage, 'cohort' => '', 'filters' => array(), 'read' => 123 ) );
	return (string) ob_get_clean();
}
/** The manager backstop as one institution's block on the Institutions screen. */
function render_manager_block( $viewer, $record ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_People::render_manager( $record );
	return (string) ob_get_clean();
}
/**
 * Each form some markup draws: the admin-post action it posts and the `wpcpm_from` it posts with
 * it, '' for none, in document order.
 *
 * @param string $html Markup.
 * @return array[] Pairs of action and flag.
 */
function returns_of( $html ) {
	preg_match_all( '#<form\b[^>]*>(.*?)</form>#s', (string) $html, $found );
	$forms = array();
	foreach ( $found[1] as $form ) {
		$forms[] = array(
			preg_match( '#name="action" value="([^"]*)"#', $form, $action ) ? $action[1] : '',
			preg_match( '#name="wpcpm_from" value="([^"]*)"#', $form, $from ) ? $from[1] : '',
		);
	}
	return $forms;
}
/**
 * Whether one call comes before another in a method body, with both of them present.
 *
 * `strpos()` answers a missing needle with false, and `false < 12` is true in PHP, so the
 * order assertions here used to pass for a method that had lost the very check they were
 * meant to pin: the capability check could be deleted and "checks the capability before the
 * nonce" went on saying ok. Absence is the answer no, whichever of the two is missing.
 */
function before( $body, $first, $second ) {
	$one = strpos( (string) $body, $first );
	$two = strpos( (string) $body, $second );

	return false !== $one && false !== $two && $one < $two;
}
/** The body of one method, by brace depth: enough to read the order of two calls. */
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

$A = 'recSEED0000000001'; // the TEST institution in the seed fixture.
$B = 'recSEED0000000002';
$C = 'recZZZZZZZZZZZZZZ'; // well-formed, never indexed.
// A contact in the base and no account at all, which is what forty of the forty-two
// confirmed institutions looked like when the contact row was written.
$D = 'rec1D1D1D1D1D1D1D';

$GLOBALS['index'] = array(
	$A => array( 'record_id' => $A, 'name' => 'TEST - Institution 20 ', 'stage' => 'Confirmed', 'city' => 'Test', 'country_name' => 'Poland', 'website' => '', 'contact_person' => 'Bob Contact', 'contact_email' => 'Contact@example.test' ),
	$B => array( 'record_id' => $B, 'name' => 'Universidad Example', 'stage' => 'Confirmed', 'city' => 'Example', 'country_name' => 'Costa Rica', 'website' => '', 'contact_person' => '', 'contact_email' => 'rector@example.test' ),
	$D => array( 'record_id' => $D, 'name' => 'Politechnika Example', 'stage' => 'Confirmed', 'city' => 'Example', 'country_name' => 'Poland', 'website' => '', 'contact_person' => 'Dana Dean', 'contact_email' => 'dana@example.test' ),
);
$GLOBALS['index_read'] = 1756000000;
$GLOBALS['settled']    = array( $A, $B, $D );

$GLOBALS['users'] = array(
	1  => new WP_User( 1, 'Manager', 'manager@example.test', array( 'administrator' ) ),
	7  => new WP_User( 7, 'Anna Kowalska', 'anna@example.test', array( 'subscriber' ) ),
	8  => new WP_User( 8, 'Bob Contact', 'contact@example.test', array( 'subscriber' ) ),
	9  => new WP_User( 9, 'Cleo Beta', 'cleo@example.test', array( 'subscriber' ) ),
	10 => new WP_User( 10, 'Dan Mentor', 'dan@example.test', array( 'wpcpm_mentor' ) ),
	11 => new WP_User( 11, 'Eve Editor', 'eve@example.test', array( 'editor' ) ),
	12 => new WP_User( 12, 'Frank Former', 'frank@example.test', array( 'subscriber' ) ),
	13 => new WP_User( 13, 'Grace Third', 'grace@example.test', array( 'subscriber' ) ),
	// A program manager by capability and not by role, which is a person the site can have:
	// `attach()` refuses an administrator and refuses nobody else for holding CAP_MANAGE.
	14 => new WP_User( 14, 'Pia Program', 'pia@example.test', array( 'subscriber' ) ),
);
$GLOBALS['manage'] = array( 1, 14 );

// Three of these accounts have been invited before, which is what makes the adoption and
// re-add paths worth pinning: the mentor and two of the members carry a stamp, so the queue
// drops them and the card must not claim an invitation it is not going to send.
$GLOBALS['umeta'][10]['wpcpm_mentor_invited']      = 1756000001;
$GLOBALS['umeta'][12]['wpcpm_inst_invited'] = 1756000002;
$GLOBALS['umeta'][13]['wpcpm_inst_invited'] = 1756000003;

// Frank was a member of A and is not one now, which is what makes him re-addable.
WPCPM_Institution_Members::attach( 12, $A, 'manager', 1 );
WPCPM_Institution_Members::detach( 12, 'removed', 1 );

WPCPM_Institution_Members::attach( 7, $A, 'manager', 1 );
WPCPM_Institution_Members::attach( 8, $A, 'provisioned', 0 );
WPCPM_Institution_Members::attach( 13, $A, 'invited', 7 );
WPCPM_Institution_Members::attach( 9, $B, 'manager', 1 );

/* ---- the card ------------------------------------------------------------ */

echo "=== The card lists every live member, with how and since ===\n";

$card = render_card( 7, $A );

ck( 'it names the three live members', array( has( $card, 'Anna Kowalska' ), has( $card, 'Bob Contact' ), has( $card, 'Grace Third' ) ), array( true, true, true ) );
ck( 'and not the former member', has( $card, 'Frank Former' ), false );
ck( 'and not a member of another institution', has( $card, 'Cleo Beta' ), false );
ck( 'each address is printed', array( has( $card, 'anna@example.test' ), has( $card, 'contact@example.test' ) ), array( true, true ) );
ck( 'how: added by an Administrator', has( $card, 'Added by an Administrator' ), true );
ck( 'how: added by the institutions sync', has( $card, 'Added by the institutions sync' ), true );
ck( 'how: joined by invitation', has( $card, 'Joined by invitation' ), true );
ck( 'since: the date the stamp was written, in the site\'s format', has( $card, gmdate( 'F j, Y' ) ), true );
ck( 'and not as year-month-day', has( $card, gmdate( 'Y-m-d' ) ), false );
ck( 'the institution name prints trimmed', has( $card, 'TEST - Institution 20&#039;s students' ), true );
ck( 'the read time of the facts that are not live, in the site\'s date and time formats', has( $card, 'The institution&#039;s details were read on ' . gmdate( 'F j, Y g:i a', 1756000000 ) . ' (4 hours ago). The people above are as they are right now.' ), true );
ck( 'and not as year-month-day and a clock', has( $card, gmdate( 'Y-m-d H:i', 1756000000 ) ), false );
ck( 'and it never renders accessibility', has( $card, 'accessibility' ), false );

// A site with another format reads its own, and the time after the date.
$GLOBALS['opts']['date_format'] = 'd/m/Y';
$GLOBALS['opts']['time_format'] = 'H:i';
ck( 'a site with another date and time format reads its own', has( render_card( 7, $A ), 'were read on ' . gmdate( 'd/m/Y H:i', 1756000000 ) . ' (4 hours ago).' ), true );
$GLOBALS['opts']['date_format'] = 'F j, Y';
$GLOBALS['opts']['time_format'] = 'g:i a';

// Nothing read yet: the sentence says so, and it is the card's own to say.
$GLOBALS['index_read'] = 0;
$unread = render_card( 7, $A );
$GLOBALS['index_read'] = 1756000000;
ck( 'before the first read the card says the details have not been read from the program records yet', has( $unread, 'The institution&#039;s details have not been read from the program records yet. The people above are as they are right now.' ), true );
ck( 'and prints no date for it', has( $unread, 'were read' ), false );

// The card is read by the school's own people, so none of it names the base: the rows, the
// marks, their tooltips, every Remove confirm and both read lines.
ck( 'a member\'s card names no Airtable at all, its read line included', array( has( $card, 'Airtable' ), has( $unread, 'Airtable' ) ), array( false, false ) );
ck( 'nor does the card of a contact with no account, where the tooltip and the row are the card\'s', has( render_card( 1, $D, true ), 'Airtable' ), false );
ck( 'nor a program table or the program\'s contact', array( has( $card, 'Students table' ), has( $card, 'the program&#039;s contact' ) ), array( false, false ) );

echo "\n=== The invite flow the module already had is what the card draws now ===\n";

// Viewer 7 is a live member of A, so `ACT_VIEW_ROSTER` (drawing the card at all) and
// `ACT_MANAGE_MEMBERS` (drawing the invite form) both allow them: this is the member who
// may manage members, the same fixture the section above already renders.
ck( 'the stale sentence about a later release is gone', has( $card, 'Inviting a colleague from this page ships with a later release' ), false );
ck( 'the invite form is drawn in its place', has( $card, 'name="action" value="' . WPCPM_Institution_Invite::ACTION_INVITE . '"' ), true );

echo "\n=== The contact Airtable names is a representative, account or no account ===\n";

// The card used to count accounts and list accounts, so an institution whose contact has
// never signed in read "Institution representatives 0" three lines under a header naming
// that very person. Two of the forty-two confirmed institutions have an account.
$named = render_card( 1, $D, true );

ck( 'the contact is listed', has( $named, 'Dana Dean' ), true );
ck( 'with the address the base holds', has( $named, 'dana@example.test' ), true );
ck( 'the row says the account is what is missing', has( $named, 'no account on this site yet' ), true );
ck( 'and it is marked as the institution\'s contact', has( $named, 'the institution&#039;s contact' ), true );
ck( 'whose tooltip says who the program writes to, about whom', has( $named, 'title="The program writes to this person about the institution."' ), true );
ck( 'and names no Airtable', has( $named, 'Airtable holds' ), false );
// The row is a fact about the program's records; there is no membership under it to end.
ck( 'the row carries no Remove control', has( $named, 'wpcpm-people__remove' ), false );
// Two different questions: who the program writes to, and who can open this page.
ck( 'and who can act is still answered separately', has( $named, 'Nobody can act for this institution on this site right now.' ), true );

// The row goes away by itself once the account exists, because the address then matches a
// member and `holds_address()` suppresses it - no second place to keep in step.
ck( 'no contact row where a member holds the address', has( $card, 'no account on this site yet' ), false );

// A base row with an address and no name still names somebody rather than printing a blank.
$unnamed = render_card( 9, $B );
ck( 'an address with no name is still listed', has( $unnamed, 'rector@example.test' ), true );
ck( 'under a stated absence rather than an empty line', has( $unnamed, 'Name not recorded' ), true );

// One heading, one meaning: the manager screen counts and lists the same way.
$block = render_manager_block( 1, $D );
ck( 'the manager screen lists the contact too', has( $block, 'Dana Dean' ), true );
// 1.123.98, A9: and counts the same way as the card, the members only: the contact with no account is listed, not counted.
ck( 'and counts it the same way, the members only', has( $block, '<span class="wpcpm-people__count">0</span>' ), true );
ck( 'and still names the gap it can close', has( $block, 'no member holds that address' ), true );
ck( 'a manager\'s card links Add or re-add to the institution\'s own Manage members view on the Institutions screen', has( $named, '<a href="https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts&wpcpm_institution=' . $D . '">Add or re-add an account on the Institutions screen</a>' ), true );

echo "\n=== The institution's contact is marked, and the mark takes nothing away ===\n";

// The blocker this block exists for: the mark used to suppress the row's control on both
// surfaces, so a contact who left the school could not be removed by a colleague, by a
// program manager or by themselves, and the institution could not be repaired from anywhere.
// The mark is information about who the program writes to; the control is on every row.
ck( 'the contact is marked, once', substr_count( $card, 'the institution&#039;s contact<' ), 1 );
ck( 'and never as the program\'s', has( $card, 'the program&#039;s contact' ), false );
ck( 'with a tooltip saying who the program writes to', has( $card, 'title="The program writes to this address about the institution."' ), true );
ck( 'the contact\'s row carries the same control as every other', has( $card, 'nonce-wpcpm_remove_member_8' ), true );
ck( 'so all three rows carry one', substr_count( $card, 'name="action" value="wpcpm_remove_member"' ), 3 );
ck( 'the other two among them', array( has( $card, 'nonce-wpcpm_remove_member_7' ), has( $card, 'nonce-wpcpm_remove_member_13' ) ), array( true, true ) );
ck( 'and no row says a manager is the only one who can', has( $card, 'is removed by an Administrator' ), false );
ck( 'what the mark does instead is warn, in that row\'s confirm', has( $card, 'The program will go on writing to this address as the institution&#039;s contact until an Administrator changes it.' ), true );
ck( 'once, on that row and no other', substr_count( $card, 'The program will go on writing to this address' ), 1 );
ck( 'in no word of the base\'s', has( $card, 'Airtable still names' ), false );

$own = render_card( 8, $A );
ck( 'the contact may leave, like anybody else', has( $own, 'nonce-wpcpm_remove_member_8' ), true );
ck( 'their confirm warns them too', has( $own, 'The program will go on writing to this address as the institution&#039;s contact until an Administrator changes it.' ), true );
ck( 'and they see the controls on everybody else', substr_count( $own, 'name="action" value="wpcpm_remove_member"' ), 3 );

$backstop_card = render_manager_block( 1, $A );
ck( 'the manager backstop offers it too', has( $backstop_card, 'nonce-wpcpm_remove_member_8' ), true );
ck( 'on every live row it draws', substr_count( $backstop_card, 'name="action" value="wpcpm_remove_member"' ), 3 );

echo "\n=== The nonce is keyed to the subject, and the confirm names everybody ===\n";

ck( 'the confirm names the person and the institution', has( $card, "Remove Grace Third&#039;s access to TEST - Institution 20&#039;s students" ), true );
ck( 'it says the others keep access', has( $card, 'The other 2 members keep access' ), true );
ck( 'and that the account is kept', has( $card, 'and the account is kept.' ), true );
ck( 'leaving says the same about your own account', has( $card, 'your account is kept' ), true );

echo "\n=== A member whose agreement is not settled sees no card at all ===\n";

$GLOBALS['settled'] = array( $B );
$refused             = render_card( 7, $A );
ck( 'nothing is drawn', $refused, '' );
// `render_form()` decides the same action on the same subject, so a viewer this policy
// refuses never reaches it either; asked directly here rather than trusted from the empty
// string above, since the two gates are meant to agree and not merely to happen to.
ck( 'the invite form is not among what is not drawn', has( $refused, 'name="action" value="' . WPCPM_Institution_Invite::ACTION_INVITE . '"' ), false );
ck( 'and a manager still sees it', has( render_card( 1, $A, true ), 'Representatives and tutors' ), true );
$GLOBALS['settled'] = array( $A, $B );

/* ---- removal: the fence -------------------------------------------------- */

echo "\n=== A member of B cannot remove a member of A ===\n";

$GLOBALS['uid']     = 9;
$GLOBALS['referer'] = array();
// The form names B, because that is what a forged one would name. It is not consulted.
$_POST  = array( 'member' => 7, 'record' => $B, 'wpcpm_from' => 'admin' );
$ending = run( 'handle_remove_member' );

ck( 'it is refused with the one message', $ending, 'wp_die:That record is not on your roster.' );
ck( 'the nonce was keyed to the subject account, not to the form\'s institution', $GLOBALS['referer'], array( 'wpcpm_remove_member_7' ) );
ck( 'and the member of A is untouched', WPCPM_Institution_Members::institution_of( 7 ), $A );

$GLOBALS['uid'] = 9;
$_POST          = array( 'member' => 999 );
ck( 'an account that does not exist is the same refusal', run( 'handle_remove_member' ), 'wp_die:That record is not on your roster.' );

$GLOBALS['uid'] = 0;
$_POST          = array( 'member' => 7 );
ck( 'and so is a request from nobody', run( 'handle_remove_member' ), 'wp_die:That record is not on your roster.' );

echo "\n=== A member removes a colleague of their own institution ===\n";

$GLOBALS['uid']     = 7;
$GLOBALS['referer'] = array();
$_POST              = array( 'member' => 13 );
$ending             = run( 'handle_remove_member' );

ck( 'it ends on a redirect to the card', $ending, 'redirect:https://example.test/#wpcpm-people' );
ck( 'the colleague is no longer a member', WPCPM_Institution_Members::institution_of( 13 ), '' );
ck( 'their account is kept', $GLOBALS['users'][13] instanceof WP_User, true );
ck( 'the stamp moved to _was', meta( 13, 'wpcpm_institution_record_id_was' ), $A );
ck( 'the outcome says so once', flash_status(), 'removed' );
$rows = WPCPM_Institution_Audit::entries_for( $A, 1 );
ck( 'one audit row, on the member ground, naming the reason', array( $rows[0]['kind'], $rows[0]['subject'], $rows[0]['actor'], $rows[0]['ground'], $rows[0]['data']['reason'] ), array( 'member_removed', '13', 7, 'member', 'removed' ) );
clear_flash();

echo "\n=== Removing a membership that has already ended ===\n";

// Two people pressing Remove on the same row is ordinary. The second finds no live stamp, so
// the subject is the institution the person LEFT, from `_was`, and the fence is asked about
// that: a manager is answered where they pressed, and a stranger learns nothing.
$GLOBALS['uid'] = 1;
$_POST          = array( 'member' => 13, 'wpcpm_from' => 'admin' );
ck( 'a manager pressing Remove on it on the Institutions screen is answered on that institution\'s Manage members view, not silenced', run( 'handle_remove_member' ), 'redirect:https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts&wpcpm_institution=' . $A . '#wpcpm-people' );
ck( 'with the outcome filed against the institution it left', array( flash_status(), flash()['record'] ), array( 'ended', $A ) );
ck( 'and nothing detached, because nothing was attached', WPCPM_Institution_Members::institution_of( 13 ), '' );
clear_flash();

$GLOBALS['uid'] = 9;
$_POST          = array( 'member' => 13, 'wpcpm_from' => 'admin' );
ck( 'a member of B asking the same is refused, so nobody learns whose access ended', run( 'handle_remove_member' ), 'wp_die:That record is not on your roster.' );
clear_flash();

echo "\n=== The last member may leave, and is warned ===\n";

// The contact goes first, by the manager, so the viewer is the only one left.
$GLOBALS['uid'] = 1;
$_POST          = array( 'member' => 8, 'wpcpm_from' => 'admin' );
ck( 'a manager removes the contact on the Institutions screen and lands back on that institution\'s Manage members view', run( 'handle_remove_member' ), 'redirect:https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts&wpcpm_institution=' . $A . '#wpcpm-people' );
clear_flash();

$card = render_card( 7, $A );
ck( 'the confirm warns that nobody will be left', has( $card, 'You are the last member of' ), true );
ck( 'and that the program will be told', has( $card, 'the Administrators will be told' ), true );

$GLOBALS['notified'] = array();
$GLOBALS['uid']      = 7;
$_POST               = array( 'member' => 7 );
$ending              = run( 'handle_remove_member' );

ck( 'leaving is allowed', $ending, 'redirect:https://example.test/#wpcpm-people' );
ck( 'the reason recorded is that they left', WPCPM_Institution_Audit::entries_for( $A, 1 )[0]['data']['reason'], 'left' );
ck( 'nobody is left', WPCPM_Institution_Members::members_of( $A ), array() );
ck( 'and the program managers were told', $GLOBALS['notified'][0]['context'] ?? '', 'member-last' );
ck( 'in a message naming the institution', has( $GLOBALS['notified'][0]['mail']['subject'], 'TEST - Institution 20' ), true );

echo "\n=== And is not left holding a message nobody can show them ===\n";

// Leaving ends the viewer's membership, so the card refuses to draw for them on the next
// request. An outcome queued anyway would sit in user meta until it surprised somebody.
ck( 'nothing is queued for a leaver who can no longer read the card', flash(), null );

WPCPM_Institution_Members::attach( 7, $A, 'manager', 1 );
$back = render_card( 7, $A );
// Not asserted here: whether the old message greets the new membership. `WPCPM_Flash::take()`
// memoizes per user and channel for the whole process, so a second render for user 7 in this
// run can print nothing whatever is queued, and an assertion on it passes by construction. The
// guard is the one above: nothing was queued for the leaver in the first place.
ck( 'the card itself is drawn again', has( $back, 'Representatives and tutors' ), true );
// Back to nobody, which is the state the blocks below were written against.
WPCPM_Institution_Members::detach( 7, 'removed', 1 );

echo "\n=== A leaver who can still see the card is still told ===\n";

// A program manager by capability who is also a member: the one leaver the card still draws,
// on the manager ground, and the one who should still read the sentence.
WPCPM_Institution_Members::attach( 14, $A, 'manager', 1 );

$GLOBALS['uid'] = 14;
$_POST          = array( 'member' => 14 );

ck( 'they may leave, and come back to the institution they were viewing', run( 'handle_remove_member' ), 'redirect:https://example.test/?wpcpm_institution_view=' . $A . '#wpcpm-people' );
ck( 'they stop being a member', WPCPM_Institution_Members::institution_of( 14 ), '' );
ck( 'and the outcome is queued for them', flash_status(), 'left' );
ck( 'on the card of the institution they left', has( render_card( 14, $A, true ), 'You have left this institution' ), true );
clear_flash();

/* ---- the manager backstop ------------------------------------------------ */

echo "\n=== Add account: an existing account is adopted only in two cases ===\n";

$GLOBALS['uid']      = 1;
$GLOBALS['inserted'] = array();
$GLOBALS['queued']   = array();
$GLOBALS['referer']  = array();
$_POST               = array( 'record' => $A, 'name' => 'Eve Editor', 'email' => 'eve@example.test', 'wpcpm_from' => 'admin' );
$ending              = run( 'handle_add_account' );

// Keyed to the institution, as Re-add's and Remove's are to the member, so a nonce taken from one
// institution's form is no use on another's.
ck( 'the nonce it checks is keyed to the institution posted', $GLOBALS['referer'], array( 'wpcpm_add_institution_account_' . $A ) );

ck( 'an editor with an account is refused', flash_status(), 'conflict' );
ck( 'the refusal names the account, so a manager can go and look', flash()['detail'], 'eveeditor' );
ck( 'nothing was created', $GLOBALS['inserted'], array() );
ck( 'and nothing was attached', WPCPM_Institution_Members::institution_of( 11 ), '' );
ck( 'it returns to the institution\'s Manage members view it was pressed on', $ending, 'redirect:https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts&wpcpm_institution=' . $A . '#wpcpm-people' );
clear_flash();

$_POST = array( 'record' => $A, 'name' => 'Frank Former', 'email' => 'frank@example.test', 'wpcpm_from' => 'admin' );
run( 'handle_add_account' );

// `queue_invites()` drops an account that already carries an invited stamp and `attach()`
// never clears one, so a re-adopted former member is sent nothing. The outcome says which of
// the two happened rather than promising mail that will not go out.
ck( 'a former member of this institution is adopted', flash_status(), 'added-known' );
ck( 'without creating a second account', $GLOBALS['inserted'], array() );
ck( 'they act for it again', WPCPM_Institution_Members::institution_of( 12 ), $A );
ck( 'the _was stamp is cleared, so they are no longer a former member', meta( 12, 'wpcpm_institution_record_id_was' ), null );
ck( 'and nothing is queued for an account that has been invited before', $GLOBALS['queued'], array() );
clear_flash();

$GLOBALS['add_role'] = array();
$GLOBALS['set_role'] = array();
$GLOBALS['queued']   = array();
$_POST               = array( 'record' => $A, 'name' => 'Dan Mentor', 'email' => 'dan@example.test', 'wpcpm_from' => 'admin' );
run( 'handle_add_account' );

ck( 'a mentor is adopted', flash_status(), 'added-known' );
ck( 'without creating a second account', $GLOBALS['inserted'], array() );
ck( 'they act for the institution', WPCPM_Institution_Members::institution_of( 10 ), $A );
ck( 'the role went on with add_role()', $GLOBALS['add_role'], array( array( 10, 'wpcpm_institution' ) ) );
ck( 'never with set_role()', $GLOBALS['set_role'], array() );
ck( 'and they are still a mentor', $GLOBALS['users'][10]->roles, array( 'wpcpm_mentor', 'wpcpm_institution' ) );
// A mentor mid-program holds a working password, and `wp_new_user_notification()` is a
// set-your-password message. Nothing is sent, and nothing claims it was.
ck( 'a mentor who has been invited as a mentor is not invited again', $GLOBALS['queued'], array() );
clear_flash();

echo "\n=== Add account: a fresh address gets a fresh account ===\n";

$GLOBALS['inserted'] = array();
$GLOBALS['queued']   = array();
$_POST               = array( 'record' => $B, 'name' => 'Hana Nowa', 'email' => 'hana@example.test', 'wpcpm_from' => 'admin' );
$ending              = run( 'handle_add_account' );

ck( 'one account was created', count( $GLOBALS['inserted'] ), 1 );
ck( 'and the press lands on that institution\'s Manage members view, where it was made', $ending, 'redirect:https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts&wpcpm_institution=' . $B . '#wpcpm-people' );
ck( 'with the Institution role and the name that was typed', array( $GLOBALS['inserted'][0]['role'], $GLOBALS['inserted'][0]['display_name'] ), array( 'wpcpm_institution', 'Hana Nowa' ) );
ck( 'the username is the local part of the address', $GLOBALS['inserted'][0]['user_login'], 'hana' );
ck( 'the password is not one anybody chose', 24, strlen( $GLOBALS['inserted'][0]['user_pass'] ) );
$fresh = get_user_by( 'email', 'hana@example.test' );
ck( 'the new account acts for the institution', WPCPM_Institution_Members::institution_of( $fresh->ID ), $B );
ck( 'the membership says a manager did it', meta( $fresh->ID, 'wpcpm_institution_membership' )['how'], 'manager' );
ck( 'and one invitation is queued for it', $GLOBALS['queued'], array( $fresh->ID ) );
ck( 'which is the outcome that may claim one', flash_status(), 'added' );
clear_flash();

// The queue's lock held by another request (the fix wave of 1.123.58): the account is created and
// attached, nothing is queued, and the card says the queue was busy, not that it was invited before.
$GLOBALS['inserted']                        = array();
$GLOBALS['queued']                          = array();
$GLOBALS['opts']['wpcpm_invite_queue_lock'] = (string) ( time() + 30 );
$_POST                                      = array( 'record' => $B, 'name' => 'Ines Busy', 'email' => 'ines@example.test', 'wpcpm_from' => 'admin' );
run( 'handle_add_account' );
unset( $GLOBALS['opts']['wpcpm_invite_queue_lock'] );
$busy_account = get_user_by( 'email', 'ines@example.test' );
$busy_said    = render_manager_block( next_manager( 1, 61 ), $B );

$GLOBALS['uid'] = 1;
ck( 'an account added while the queue\'s lock is held by another request is created and attached, queued nothing, and the card says the queue was busy',
	array( count( $GLOBALS['inserted'] ), WPCPM_Institution_Members::institution_of( $busy_account->ID ), $GLOBALS['queued'], flash_status(), has( $busy_said, 'The account was added. The invitation could not be queued: the queue was busy. Send it from the account&#039;s row.' ), has( $busy_said, 'already waiting in the queue' ) ),
	array( 1, $B, array(), 'added-busy', true, false ) );
clear_flash();

echo "\n=== Add account: what it refuses before it creates anything ===\n";

$GLOBALS['inserted'] = array();
$_POST               = array( 'record' => $C, 'name' => 'Nobody', 'email' => 'nobody@example.test', 'wpcpm_from' => 'admin' );
run( 'handle_add_account' );
ck( 'a record the index does not hold', flash_status(), 'unknown-record' );
clear_flash();

// Shape first: the hidden field is written by the block the button sits in, so a value that
// is not a record ID at all is a forged or a broken form, and gets the one refusal rather
// than a message about the pipeline index.
$_POST = array( 'record' => 'not-a-record', 'name' => 'Nobody', 'email' => 'nobody@example.test' );
ck( 'a record ID that is not one is refused, not answered', run( 'handle_add_account' ), 'wp_die:That record is not on your roster.' );
ck( 'and says nothing to anybody', flash(), null );

$_POST = array( 'record' => $B, 'name' => 'Nobody', 'email' => 'not an address' );
run( 'handle_add_account' );
ck( 'an address WordPress cannot use', flash_status(), 'bad-email' );
clear_flash();

$_POST = array( 'record' => $B, 'name' => 'Cleo Beta', 'email' => 'cleo@example.test' );
run( 'handle_add_account' );
ck( 'an account that is already on the list is told so, not called a conflict', flash_status(), 'already' );
clear_flash();

$_POST = array( 'record' => $A, 'name' => 'Cleo Beta', 'email' => 'cleo@example.test' );
run( 'handle_add_account' );
ck( 'a live member of another institution is the conflict', flash_status(), 'conflict' );
ck( 'and is not moved', WPCPM_Institution_Members::institution_of( 9 ), $B );
ck( 'nothing was created by any of them', $GLOBALS['inserted'], array() );
clear_flash();

$GLOBALS['uid'] = 7;
$_POST          = array( 'record' => $A, 'name' => 'Nobody', 'email' => 'nobody@example.test' );
ck( 'and a member cannot use the backstop at all', run( 'handle_add_account' ), 'wp_die:You do not have permission to manage the program.' );

echo "\n=== Add account shows its labels, keeps what was typed, and names the account in the way (1.123.58) ===\n";

/**
 * The next request of a manager who just pressed something: the same user meta under a fresh account,
 * since the real flash reads each channel once per account and request.
 */
function next_manager( $from, $to ) {
	$GLOBALS['users'][ $to ] = new WP_User( $to, 'Next Manager ' . $to, 'next-' . $to . '@example.test', array( 'administrator' ) );
	$GLOBALS['manage'][]     = $to;
	$GLOBALS['umeta'][ $to ] = isset( $GLOBALS['umeta'][ $from ] ) ? $GLOBALS['umeta'][ $from ] : array();
	return $to;
}

$drawn = render_manager_block( next_manager( 1, 41 ), $A );
ck( 'the name and address labels are visible', array( has( $drawn, '<label for="wpcpm-people-name-' . $A . '">Name</label>' ), has( $drawn, '<label for="wpcpm-people-email-' . $A . '">Email address</label>' ), has( $drawn, '<label class="screen-reader-text" for="wpcpm-people-' ) ), array( true, true, false ) );
ck( 'and the placeholders stay, as examples', array( has( $drawn, 'placeholder="Name"' ), has( $drawn, 'placeholder="Email address"' ) ), array( true, true ) );

$GLOBALS['uid'] = 1;
$_POST          = array( 'record' => $B, 'name' => 'Nora Typed', 'email' => 'not an address' );
run( 'handle_add_account' );
$refused = flash_status();
$drawn   = render_manager_block( next_manager( 1, 42 ), $B );
ck( 'a refused address keeps the typed name and address', array( $refused, has( $drawn, 'name="name" value="Nora Typed"' ), has( $drawn, 'name="email" value="not an address"' ) ), array( 'bad-email', true, true ) );
ck( 'once: the next drawing is the empty form', has( render_manager_block( next_manager( 42, 45 ), $B ), 'value="Nora Typed"' ), false );
clear_flash();

$GLOBALS['uid']         = 1;
$GLOBALS['grants'][43]  = array( 'edit_user' );
$_POST                  = array( 'record' => $A, 'name' => 'Eve Typed', 'email' => 'eve@example.test', 'wpcpm_from' => 'admin' );
run( 'handle_add_account' );
next_manager( 1, 44 );
$admin_view  = render_manager_block( next_manager( 1, 43 ), $A );
$plain_view  = render_manager_block( 44, $A );
ck( 'the conflict names the account, linked to its profile for an Administrator', has( $admin_view, 'An account already uses that address: <a href="https://example.test/wp-admin/user-edit.php?user_id=11">Eve Editor</a>. Nothing was created.' ), true );
ck( 'and by its name alone otherwise', has( $plain_view, 'An account already uses that address: Eve Editor. Nothing was created.' ), true );
ck( 'and the conflict keeps what was typed too', array( has( $admin_view, 'name="name" value="Eve Typed"' ), has( $admin_view, 'name="email" value="eve@example.test"' ) ), array( true, true ) );
clear_flash();

echo "\n=== Re-add puts a former member back, and only a former member ===\n";

$GLOBALS['uid']     = 1;
$GLOBALS['queued']  = array();
$GLOBALS['referer'] = array();
$_POST              = array( 'member' => 8, 'record' => $A, 'wpcpm_from' => 'admin' );
$ending             = run( 'handle_readd' );

ck( 'the contact who was removed is back', WPCPM_Institution_Members::institution_of( 8 ), $A );
ck( 'and the press lands on that institution\'s Manage members view, where it was made', $ending, 'redirect:https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts&wpcpm_institution=' . $A . '#wpcpm-people' );
ck( 'the nonce was keyed to the subject account', $GLOBALS['referer'], array( 'wpcpm_readd_institution_member_8' ) );
ck( 'the outcome says so', flash_status(), 'readded' );
ck( 'and an invitation is queued for an account that has never had one', $GLOBALS['queued'], array( 8 ) );
clear_flash();

// The commoner case: a former member has been invited before, so `queue_invites()` sends
// nothing and the outcome does not say it did.
$GLOBALS['queued'] = array();
$_POST             = array( 'member' => 13, 'record' => $A, 'wpcpm_from' => 'admin' );
run( 'handle_readd' );

ck( 'a former member who has been invited before is put back', WPCPM_Institution_Members::institution_of( 13 ), $A );
ck( 'with no second invitation, and no claim of one', array( flash_status(), $GLOBALS['queued'] ), array( 'readded-known', array() ) );
clear_flash();
// And put back while the queue's lock is held by another request: the outcome says the queue was busy.
WPCPM_Institution_Members::detach( 13, 'removed', 1 );
$GLOBALS['opts']['wpcpm_invite_queue_lock'] = (string) ( time() + 30 );
$_POST                                      = array( 'member' => 13, 'record' => $A, 'wpcpm_from' => 'admin' );
run( 'handle_readd' );
unset( $GLOBALS['opts']['wpcpm_invite_queue_lock'] );
ck( 'a former member put back while the queue\'s lock is held by another request is put back, and the outcome says the queue was busy',
	array( WPCPM_Institution_Members::institution_of( 13 ), flash_status(), $GLOBALS['queued'] ), array( $A, 'readded-busy', array() ) );
$readded_said = render_manager_block( next_manager( 1, 62 ), $A );

$GLOBALS['uid'] = 1;
ck( 'and the card says the invitation could not be queued, to be sent from the account\'s row',
	has( $readded_said, 'That former member has access again. The invitation could not be queued: the queue was busy. Send it from the account&#039;s row.' ), true );
clear_flash();
// Back to a former member, which is the state the manager block at the foot is written for.
WPCPM_Institution_Members::detach( 13, 'removed', 1 );

$_POST = array( 'member' => 11, 'record' => $A, 'wpcpm_from' => 'admin' );
run( 'handle_readd' );
ck( 'an account that was never a member of it is refused', flash_status(), 'not-former' );
ck( 'and is not attached', WPCPM_Institution_Members::institution_of( 11 ), '' );
clear_flash();

// Frank's `_was` names A and he is a live member of A: a re-add naming B must not move him.
$_POST = array( 'member' => 12, 'record' => $B, 'wpcpm_from' => 'admin' );
run( 'handle_readd' );
ck( 'a re-add naming another institution is refused', flash_status(), 'not-former' );
ck( 'and the member stays where they are', WPCPM_Institution_Members::institution_of( 12 ), $A );
clear_flash();

$GLOBALS['uid'] = 7;
$_POST          = array( 'member' => 12, 'record' => $A );
ck( 'a member cannot re-add either', run( 'handle_readd' ), 'wp_die:You do not have permission to manage the program.' );

echo "\n=== One outcome, on the card of the institution it happened to ===\n";

// `WPCPM_Flash::take()` memoizes per request, so a screen drawing the backstop under every
// pipeline row printed one manager's outcome under all 106 of them, each reading as a fact
// about that school. The outcome names its institution and only that block prints it. A
// fresh manager account, because a flash is read once per reader and the managers above have
// already read theirs.
$GLOBALS['users'][15]                         = new WP_User( 15, 'Quinn Manager', 'quinn@example.test', array( 'subscriber' ) );
$GLOBALS['users'][16]                         = new WP_User( 16, 'Rita Mentor', 'rita@example.test', array( 'wpcpm_mentor' ) );
$GLOBALS['umeta'][16]['wpcpm_mentor_invited'] = 1756000004;
$GLOBALS['manage'][]                          = 15;

$GLOBALS['uid']    = 15;
$GLOBALS['queued'] = array();
$_POST             = array( 'record' => $A, 'name' => 'Rita Mentor', 'email' => 'rita@example.test', 'wpcpm_from' => 'admin' );
run( 'handle_add_account' );

ck( 'the outcome is recorded against the institution it happened to', flash()['record'], $A );

$elsewhere = render_manager_block( 15, $B );
$here      = render_manager_block( 15, $A );

ck( 'another institution\'s block says nothing about it', has( $elsewhere, 'wpcpm-people__message' ), false );
ck( 'the institution it happened to says it', has( $here, 'The account was added.' ), true );
ck( 'once', substr_count( $here, 'wpcpm-people__message' ), 1 );
ck( 'and says no invitation was queued, because none was', array( has( $here, 'No new invitation was queued' ), $GLOBALS['queued'] ), array( true, array() ) );
clear_flash();

/* ---- the manager block --------------------------------------------------- */

echo "\n=== The manager backstop draws what a manager needs ===\n";

$GLOBALS['uid'] = 1;
ob_start();
WPCPM_Institution_People::render_manager( $B );
$block = (string) ob_get_clean();

ck( 'it lists the live members', has( $block, 'Hana Nowa' ), true );
ck( 'it says when Airtable\'s contact is nobody here', has( $block, 'and no member holds that address' ), true );
ck( 'naming the address', has( $block, 'rector@example.test' ), true );
ck( 'it offers the Add account form, its nonce keyed to the institution', array( has( $block, 'name="action" value="wpcpm_add_institution_account"' ), has( $block, 'name="_wpnonce" value="nonce-wpcpm_add_institution_account_' . $B . '"' ) ), array( true, true ) );
ck( 'and says what that refuses', has( $block, 'only adopted when that account was a member of this institution or is a mentor' ), true );

ob_start();
WPCPM_Institution_People::render_manager( $A );
$block = (string) ob_get_clean();

ck( 'former members are listed with a Re-add', array( has( $block, 'Grace Third' ), has( $block, 'name="action" value="wpcpm_readd_institution_member"' ) ), array( true, true ) );
ck( 'and the read time is printed here too, in the site\'s formats', has( $block, 'were read on ' . gmdate( 'F j, Y g:i a', 1756000000 ) . ' (4 hours ago).' ), true );

// Each form, by the action it posts, with what its `wpcpm_from` says ('' for none): the flag
// `finish()` rebuilds the way back from.
$returns = array();
foreach ( returns_of( $block ) as $form ) {
	$returns[ $form[0] ][ $form[1] ] = true;
}

ck( 'every form on the block comes back to the Institutions screen: Remove, Re-add and Add account each post its flag', array_map( 'array_keys', $returns ), array(
	'wpcpm_remove_member'            => array( 'admin' ),
	'wpcpm_readd_institution_member' => array( 'admin' ),
	'wpcpm_add_institution_account'  => array( 'admin' ),
) );
ck( 'and the block carries the anchor every one of them lands on, once', substr_count( $block, 'id="wpcpm-people"' ), 1 );
ck( 'while every form on the dashboard\'s card comes back to the dashboard: none posts the flag', array_values( array_unique( array_column( returns_of( render_card( 1, $A, true ) ), 1 ) ) ), array( '' ) );

// The block is drawn in wp-admin, where only core's button classes dress a button: Remove takes
// them there as Re-add and Add account already do, and the dashboard's card keeps its own class,
// which the dashboard's stylesheet dresses.
$dash_card = render_card( 1, $A, true );


// A record the pipeline index no longer holds draws no block, so the outcome of a press that
// landed on its view is printed on its own: both channels, in the block's box and on the anchor
// the landing names, and taken either way rather than left waiting in user meta.
$GLOBALS['users'][18] = new WP_User( 18, 'Tao Manager', 'tao@example.test', array( 'subscriber' ) );
$GLOBALS['manage'][]  = 18;
WPCPM_Flash::set( 'institution_people', array( 'status' => 'unknown-record', 'detail' => '', 'record' => $C ), 18 );
WPCPM_Flash::set( WPCPM_Institution_Invite::FLASH, array( 'status' => 'invite-unknown', 'detail' => '', 'record' => $C ), 18 );
$flash_waiting  = isset( $GLOBALS['umeta'][18][ WPCPM_Flash::META ] );
$GLOBALS['uid'] = 18;
ob_start();
if ( method_exists( 'WPCPM_Institution_People', 'render_outcome' ) ) {
	WPCPM_Institution_People::render_outcome( $C );
}
$outcome = (string) ob_get_clean();

ck( 'the outcome of a press on a view that draws no block is printed on its own, both channels, in the block\'s box on its anchor, and taken', array(
	0 === strpos( $outcome, '<div class="wpcpm-people wpcpm-people--admin" id="wpcpm-people">' ),
	has( $outcome, 'That institution is not in the pipeline index. Run the institutions sync, then try again.' ),
	has( $outcome, 'This institution is not in the program&#039;s index on this site yet' ),
	$flash_waiting,
	isset( $GLOBALS['umeta'][18][ WPCPM_Flash::META ] ),
), array( true, true, true, true, false ) );

ob_start();
if ( method_exists( 'WPCPM_Institution_People', 'render_outcome' ) ) {
	WPCPM_Institution_People::render_outcome( $B );
}
ck( 'and with no outcome to print, nothing at all, not an empty box', array( method_exists( 'WPCPM_Institution_People', 'render_outcome' ), (string) ob_get_clean() ), array( true, '' ) );

// An invitation's outcome lands on the same view when its control was pressed there, so the
// block prints that channel too, as the card does.
$GLOBALS['users'][17] = new WP_User( 17, 'Sol Manager', 'sol@example.test', array( 'subscriber' ) );
$GLOBALS['manage'][]  = 17;
WPCPM_Flash::set( WPCPM_Institution_Invite::FLASH, array( 'status' => 'invite-resent', 'detail' => 'colleague@example.test', 'record' => $A ), 17 );
$resent_block = render_manager_block( 17, $A );

ck( 'an invitation\'s outcome that lands on the view is printed in the block, once, as the card prints it', array( has( $resent_block, 'The invitation was sent again, with a new link.' ), substr_count( $resent_block, 'wpcpm-people__message' ) ), array( true, 1 ) );

$manager_url = new ReflectionMethod( 'WPCPM_Institution_People', 'manager_url' );

ck( 'the view\'s address is one public method, the institution in it; a value that is no record ID answers the Accounts tab\'s list', array(
	$manager_url->isPublic() && $manager_url->isStatic(),
	$manager_url->isPublic() ? WPCPM_Institution_People::manager_url( ' ' . $A . ' ' ) : 'not public',
	$manager_url->isPublic() ? WPCPM_Institution_People::manager_url( 'not-a-record' ) : 'not public',
), array(
	true,
	'https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts&wpcpm_institution=' . $A,
	'https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts',
) );

$GLOBALS['uid'] = 7;
ob_start();
WPCPM_Institution_People::render_manager( $A );
ck( 'a member is drawn nothing by it', (string) ob_get_clean(), '' );

/* ---- structure ----------------------------------------------------------- */

echo "\n=== An administrator's press comes back to the institution they were viewing ===\n";

// The switcher is what puts an administrator on an institution's dashboard, and the address a
// press returns to has to carry it again: without it `resolve_institution()` falls through to
// the first institution with a member. The institution is the subject's own, read off the
// member's stamp, and never the switcher the form happened to post.
$VIEW = WPCPM_Institution_Roster::ARG_VIEW;

ck( 'the argument the suite copies is the real class\'s', has( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster.php' ), "const ARG_VIEW = '" . $VIEW . "';" ), true );

WPCPM_Institution_Members::attach( 13, $A, 'invited', 7 );
WPCPM_Institution_Members::attach( 7, $A, 'manager', 1 );

$GLOBALS['uid'] = 1;
$_POST          = array( 'member' => 13, $VIEW => $B );
$_GET           = array( $VIEW => $B );

ck( 'an administrator removing a member comes back to that member\'s institution', run( 'handle_remove_member' ), 'redirect:https://example.test/?' . $VIEW . '=' . $A . '#wpcpm-people' );
ck( 'having removed them', WPCPM_Institution_Members::institution_of( 13 ), '' );
clear_flash();

$_POST = array( 'member' => 13, $VIEW => $B );
ck( 'and pressing Remove on the row that has already ended comes back to the institution it left', run( 'handle_remove_member' ), 'redirect:https://example.test/?' . $VIEW . '=' . $A . '#wpcpm-people' );
clear_flash();

$_POST = array( 'record' => $A, 'email' => 'not an address', $VIEW => $B );
ck( 'the backstop posted without the Institutions screen\'s flag comes back to the institution it names', run( 'handle_add_account' ), 'redirect:https://example.test/?' . $VIEW . '=' . $A . '#wpcpm-people' );
clear_flash();

$_POST = array( 'member' => 99999, 'record' => $A, $VIEW => $B );
ck( 'and so does a re-add of somebody who was never a member', run( 'handle_readd' ), 'redirect:https://example.test/?' . $VIEW . '=' . $A . '#wpcpm-people' );
clear_flash();

// A member's address is what it always was, whatever they post.
WPCPM_Institution_Members::attach( 13, $A, 'invited', 7 );
$GLOBALS['uid'] = 7;
$_POST          = array( 'member' => 13, $VIEW => $B );
$_GET           = array( $VIEW => $B );

ck( 'a member removing a colleague comes back to the card, with no switcher argument', run( 'handle_remove_member' ), 'redirect:https://example.test/#wpcpm-people' );
clear_flash();

$_POST = array( 'member' => 13, $VIEW => $B );
ck( 'and so does the press that finds the row already ended', run( 'handle_remove_member' ), 'redirect:https://example.test/#wpcpm-people' );
clear_flash();

$_POST = array();
$_GET  = array();

/* ---- the Tutor level (Institutions release 1) ------------------------------ */

echo "\n=== The card names each member's level, and draws a tutor no control ===\n";

$GLOBALS['users'][50] = new WP_User( 50, 'Tess Tutor', 'tess@example.test', array( 'subscriber' ) );
ck( 'a tutor is attached to A, as an invitation to a tutor attaches them', WPCPM_Institution_Members::attach( 50, $A, 'invited', 7, 0, 'tutor' ), true );
if ( ! WPCPM_Institution_Members::institution_of( 7 ) ) { WPCPM_Institution_Members::attach( 7, $A, 'manager', 1 ); }

$rep_card   = render_card( 7, $A );
$tutor_card = render_card( 50, $A );

ck( 'but is drawn no Remove or Leave, no invitation form and no waiting list', array( has( $tutor_card, 'wpcpm_remove_member' ), has( $tutor_card, 'wpcpm_invite_member' ), has( $tutor_card, 'Invite a colleague' ), has( $tutor_card, 'wpcpm_cancel_invite' ), has( $tutor_card, 'name="level"' ) ), array( false, false, false, false, false ) );
ck( 'where a representative is drawn them', array( has( $rep_card, 'wpcpm_remove_member' ), has( $rep_card, 'wpcpm_invite_member' ), has( $rep_card, 'name="level" value="tutor"' ) ), array( true, true, true ) );
ck( 'and the sentence about removing anyone is left out of a tutor\'s card, which cannot', array( has( $rep_card, 'Representatives can remove anyone, including themselves, and tutors change nothing. A removal keeps the account and tells the people who are left.' ), has( $tutor_card, 'Representatives can remove anyone' ), has( $tutor_card, 'can see TEST - Institution 20&#039;s students on this site.' ) ), array( true, false, true ) );
ck( 'the level words are the card\'s own, one list: Representative for every level but the tutor\'s', array( WPCPM_Institution_People::level_label( 'tutor' ), WPCPM_Institution_People::level_label( 'representative' ), WPCPM_Institution_People::level_label( 'nonsense' ) ), array( 'Tutor', 'Representative', 'Representative' ) );

echo "\n=== A removed tutor re-added by a manager is a tutor, never a representative ===\n";

WPCPM_Institution_Members::detach( 50, 'removed', 1 );
$GLOBALS['uid']     = 1;
$GLOBALS['queued']  = array();
$_POST              = array( 'member' => 50, 'record' => $A, 'wpcpm_from' => 'admin' );
run( 'handle_readd' );
ck( 'a removed tutor put back by a manager is a tutor again', array( WPCPM_Institution_Members::institution_of( 50 ), WPCPM_Institution_Members::level_of( 50 ) ), array( $A, 'tutor' ) );
ck( 'and the policy grounds them as a tutor, so they can see and change nothing more', WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_MANAGE_MEMBERS, WPCPM_Institution_Policy::subject_institution( $A ), 50 )['allowed'] ?? false, false );
clear_flash();

echo "\n=== A tutor's hand-made posts to the members' handlers are refused, and nothing is written ===\n";

// Tess (50) is a tutor of A, which is settled. Every post carries a valid nonce (the stub grants it); what
// refuses is the policy. A former member of A is posted for the re-add, a live one for the remove.
$GLOBALS['uid']      = 50;
$GLOBALS['inserted'] = array();
$GLOBALS['queued']   = array();
$GLOBALS['referer']  = array();
$refusal             = 'wp_die:That record is not on your roster.';
$members_before      = array_map( function ( $u ) { return $u->ID; }, WPCPM_Institution_Members::members_of( $A ) );
$former_before       = array_map( function ( $u ) { return $u->ID; }, WPCPM_Institution_Members::former_members_of( $A ) );
$rows_before         = count( WPCPM_Institution_Audit::entries_for( $A, 0 ) );
$umeta_before        = serialize( $GLOBALS['umeta'] );
$opts_before         = serialize( $GLOBALS['opts'] );

$_POST = array( 'member' => 7, 'record' => $A );
ck( 'a tutor removing a colleague is refused with the one message', run( 'handle_remove_member' ), $refusal );
$_POST = array( 'member' => 50, 'record' => $A );
ck( 'and removing themselves, which is Leave, which they are not drawn', run( 'handle_remove_member' ), $refusal );
$_POST = array( 'record' => $A, 'name' => 'New Person', 'email' => 'new.person@example.test' );
ck( 'adding an account is refused, as it is for everybody without the program\'s capability', run( 'handle_add_account' ), 'wp_die:You do not have permission to manage the program.' );
$former = $former_before ? $former_before[0] : 12;
$_POST  = array( 'member' => $former, 'record' => $A );
ck( 'and so is putting a former member back', run( 'handle_readd' ), 'wp_die:You do not have permission to manage the program.' );

ck( 'nothing was written: the members, the former members, the log, every meta and option, no account, no mail queued', array(
	array_map( function ( $u ) { return $u->ID; }, WPCPM_Institution_Members::members_of( $A ) ) === $members_before,
	array_map( function ( $u ) { return $u->ID; }, WPCPM_Institution_Members::former_members_of( $A ) ) === $former_before,
	count( WPCPM_Institution_Audit::entries_for( $A, 0 ) ) === $rows_before,
	serialize( $GLOBALS['umeta'] ) === $umeta_before,
	serialize( $GLOBALS['opts'] ) === $opts_before,
	$GLOBALS['inserted'],
	$GLOBALS['queued'],
), array( true, true, true, true, true, array(), array() ) );
$GLOBALS['uid'] = 0;
$_POST          = array();



$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-people.php' );

$remove = method_body( $src, 'handle_remove_member' );
ck( 'the removal reads the institution from the subject\'s own stamp', has( $remove, 'WPCPM_Institution_Members::institution_of( $member_id )' ), true );
ck( 'and never from the form', has( $remove, 'posted_text( \'record\' )' ), false );
ck( 'its nonce is keyed to the subject account', has( $remove, "self::ACTION_REMOVE_MEMBER . '_' . \$member_id" ), true );
ck( 'it asks the policy for the removal itself, once', substr_count( $remove, 'ACT_MANAGE_MEMBERS' ), 1 );
ck( 'and asks again only whether the leaver will be shown the outcome', substr_count( $remove, 'ACT_VIEW_ROSTER' ), 1 );
ck( 'never carrying a copy of the fence', substr_count( $remove, 'current_user_can' ), 0 );

// Spec 5.4: a manager-side handler runs the capability, then the nonce, then decide(), so the
// row the log writes says `manager` rather than nothing. All three, in that order, in both.
foreach ( array( 'handle_add_account', 'handle_readd' ) as $name ) {
	$body = method_body( $src, $name );

	ck(
		$name . '() carries all three checks',
		array(
			has( $body, 'current_user_can' ),
			has( $body, 'check_admin_referer' ),
			has( $body, 'WPCPM_Institution_Policy::decide(' ),
		),
		array( true, true, true )
	);
	ck(
		$name . '() runs them capability, nonce, policy',
		array(
			before( $body, 'current_user_can', 'check_admin_referer' ),
			before( $body, 'check_admin_referer', 'WPCPM_Institution_Policy::decide(' ),
		),
		array( true, true )
	);
	ck( $name . '() asks about the action the card asks about', has( $body, 'ACT_MANAGE_MEMBERS' ), true );
	ck( $name . '() refuses with the one refusal', has( $body, 'WPCPM_Institution_Policy::refusal()' ), true );
}

// The order assertion above used to be `strpos( $body, 'a' ) < strpos( $body, 'b' )`, which
// passes with the first check deleted, because a missing needle is false and `false < 12` is
// true. Both directions are pinned here so nobody writes the passing-when-broken form again.
$only_nonce = 'nonce only: check_admin_referer';
$only_cap   = 'capability only: current_user_can';
ck(
	'an order assertion says no when either check is missing, where strpos() said yes',
	array(
		before( $only_nonce, 'current_user_can', 'check_admin_referer' ),
		before( $only_cap, 'current_user_can', 'check_admin_referer' ),
		strpos( $only_nonce, 'current_user_can' ) < strpos( $only_nonce, 'check_admin_referer' ),
	),
	array( false, false, true )
);

// The backstop is drawn on a manager's screen, so the capability says which screen this is;
// the policy then says whether the record may be acted on, and on what ground. Asking the
// capability alone was how the three manager paths came to be the only ones in the module
// that never reached decide().
$backstop = method_body( $src, 'render_manager' );
ck(
	'the backstop asks the policy as well as the capability',
	array( has( $backstop, 'current_user_can' ), has( $backstop, 'WPCPM_Institution_Policy::decide(' ) ),
	array( true, true )
);
ck( 'about the action its own controls are served by', has( $backstop, 'ACT_MANAGE_MEMBERS' ), true );

// An outcome queued without an institution is drawn by no card at all, so it would be a
// message written and never read. Every call names one; the third argument is that name.
$arities = array();

foreach ( explode( "\n", $src ) as $line ) {
	$open = strpos( $line, 'self::finish(' );

	if ( false === $open ) {
		continue;
	}

	$arities[] = substr_count( substr( $line, $open ), ',' ) + 1;
}

ck( 'every outcome is queued with the institution it is about', array_values( array_unique( $arities ) ), array( 3 ) );
ck( 'and there were outcomes to count', count( $arities ) > 8, true );

ck( 'no institution ID is compared with === anywhere in the file', preg_match( '/wpcpm_(student_)?institution(_record_id)?[^;]*===/', $src ), 0 );
ck( 'and the stamp keys are named through the members module', has( $src, 'WPCPM_Institution_Members::META_MEMBERSHIP' ), true );

$GLOBALS['hooks'] = array();
WPCPM_Institution_People::init();
ck( 'the three handlers are registered, and the tutor list\'s two after them', $GLOBALS['hooks'], array(
	'admin_post_wpcpm_remove_member',
	'admin_post_wpcpm_add_institution_account',
	'admin_post_wpcpm_readd_institution_member',
	'admin_post_wpcpm_institution_tutor_add',
	'admin_post_wpcpm_institution_tutor_remove',
) );

/* ---- Stage B, Task 20: representatives, invitations and tutors on the shared parts ---------------------------------- */

echo "\n=== Stage B (Task 20): representatives, invitations and tutors on the shared parts ===\n";

// The parts and the card's shell, the real ones; core's nonce field with its id and its referer (`core_nonce`), so the
// drawn markup of both branches holds the referer the browser posts.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-card.php';
$GLOBALS['core_nonce'] = true;

const T20_LIVE = array( 'administrator', 'institution' );
const T20_BAD  = '<script>alert(1)</script> "q" & Co';
const T20_LONG = 'Exampleinstituteforappliedwebengineerings';
const T20_MAIL = 'averyveryveryverylongmailboxname1234@example.org';
const T20_A    = 'recT20A0000000001';
const T20_B    = 'recT20B0000000001';
const T20_C    = 'recT20C0000000001';
const T20_D    = 'recT20D0000000001';

// The old branch's digests, taken from the classes at 6b0a7fdc (the base) with this suite: T20_PRINT=1 prints them.
// Manage members on the Institutions screen, at the base: T20_PRINT=1 prints them too.
const T20_MANAGER_BASE = array(
	'five people, three invitations' => 'af6e8aa949c3ce13a0442e7ca5e6138a',
	'outcome: five people, three invitations' => 'd41d8cd98f00b204e9800998ecf8427e',
	// 1.123.98, A9: the heading counts the members only, 0 here where the base counted the contact's row as 1; that digit is
	// the whole change (held below: the drawing with the base's digit is the base's digest, 29361bc52f9fac89318ffc16c3653961).
	'a named contact with no account' => 'c88b81580c16b34c0964bde6eb3fa198',
	'outcome: a named contact with no account' => 'd41d8cd98f00b204e9800998ecf8427e',
	'a removal said' => '21d9d27fb329231b32e62f671207ccc9',
	'outcome: a removal said' => 'cc119aae4a96197058e1898448700ff6',
	'an address already in use' => '31d2961faa0798891fb0f35251a32aad',
	'outcome: an address already in use' => '3c167e566953f191b6faae4d22b6c1cc',
	'an invitation resent' => 'db3ebd27b3c15d3f908e1cd829e03219',
	'outcome: an invitation resent' => '6231ab343e27a71ef3a32605fbe2b021',
	// 1.123.98, A9: the hostile contact has no account either: the heading's count is one less than the base's (held below).
	'hostile' => 'bd6eb44f6ce0492d5aafe35c110e5b1e',
	'outcome: hostile' => 'd41d8cd98f00b204e9800998ecf8427e',
);
// The shell's outcome and the parts' status tag as the Administrator Dashboard calls them, at the base.
const T20_SHARED_BASE = array(
	'message, a saved card' => 'f6970727c3378aafab3da53a88243a1a',
	'message, a refusal led to a box' => 'a184c5ea82fd34d80e1e224d7e5da180',
	'message, an unknown tone' => 'd528b43b30d609561023e155a67d6e2a',
	'tag, Revoked' => 'e12a10377a275b9c3887458281407bd9',
	'tag, Waits' => '5ec244b41d112b146cedeb7b91efa318',
	'row, an agreement out of force' => '687ff3cda3cb5d6b01e237be5efacf4b',
);

/** A new request: what the flash read in the last one is forgotten, as a page load forgets it. */
function t20_new_request() {
	$taken = new ReflectionProperty( 'WPCPM_Flash', 'taken' );
	if ( PHP_VERSION_ID < 80100 ) {
		$taken->setAccessible( true );
	}
	$taken->setValue( null, array() );
}
/** One waiting invitation of school A, as the invitation class stores one. */
function t20_invite( $id, $email, $actor, $sent ) {
	$post              = new WP_Post();
	$post->ID          = (int) $id;
	$post->post_type   = WPCPM_Institution_Invite::POST_TYPE;
	$post->post_status = WPCPM_Institution_Invite::POST_STATUS;
	$GLOBALS['posts'][ (int) $id ] = $post;
	foreach ( array(
		WPCPM_Institution_Invite::META_INSTITUTION => T20_A,
		WPCPM_Institution_Invite::META_EMAIL       => $email,
		WPCPM_Institution_Invite::META_STATE       => WPCPM_Institution_Invite::STATE_PENDING,
		WPCPM_Institution_Invite::META_ACTOR       => (int) $actor,
		WPCPM_Institution_Invite::META_SENT        => (int) $sent,
	) as $key => $value ) {
		$GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value );
	}
}
/** The fixture every state starts from: school A with four people, a tutor and its contact holding an account. */
function t20_fresh() {
	$GLOBALS['umeta']      = array();
	$GLOBALS['posts']      = array();
	$GLOBALS['pmeta']      = array();
	$GLOBALS['kept']       = array();
	$GLOBALS['grants']     = array();
	$GLOBALS['index_read'] = 1756000000;
	$GLOBALS['index']      = array(
		T20_A => array( 'record_id' => T20_A, 'name' => 'Universidad Example', 'stage' => 'Confirmed', 'city' => 'Example', 'country_name' => 'Spain', 'website' => '', 'contact_person' => 'Bob Example', 'contact_email' => 'Bob@example.org' ),
		T20_B => array( 'record_id' => T20_B, 'name' => 'Other Example', 'stage' => 'Confirmed', 'city' => 'Example', 'country_name' => 'Spain', 'website' => '', 'contact_person' => '', 'contact_email' => '' ),
		T20_C => array( 'record_id' => T20_C, 'name' => 'Locked Example', 'stage' => 'Confirmed', 'city' => 'Example', 'country_name' => 'Spain', 'website' => '', 'contact_person' => '', 'contact_email' => '' ),
		T20_D => array( 'record_id' => T20_D, 'name' => 'Contact Example', 'stage' => 'Confirmed', 'city' => 'Example', 'country_name' => 'Spain', 'website' => '', 'contact_person' => 'Dana Example', 'contact_email' => 'dana@example.org' ),
	);
	$GLOBALS['settled'] = array( T20_A, T20_B, T20_D );
	$GLOBALS['manage']  = array( 1 );
	$GLOBALS['users']   = array(
		1   => new WP_User( 1, 'Manager Example', 'manager@example.org', array( 'administrator' ) ),
		201 => new WP_User( 201, 'Anna Example', 'anna@example.org', array( 'subscriber' ) ),
		202 => new WP_User( 202, 'Bob Example', 'bob@example.org', array( 'subscriber' ) ),
		203 => new WP_User( 203, 'Cleo Example', 'cleo@example.org', array( 'subscriber' ) ),
		204 => new WP_User( 204, 'Tess Example', 'tess@example.org', array( 'subscriber' ) ),
		209 => new WP_User( 209, 'Otto Example', 'otto@example.org', array( 'subscriber' ) ),
		210 => new WP_User( 210, 'Lena Example', 'lena@example.org', array( 'subscriber' ) ),
	);
	WPCPM_Institution_Members::attach( 201, T20_A, 'manager', 1 );
	WPCPM_Institution_Members::attach( 202, T20_A, 'provisioned', 0 );
	WPCPM_Institution_Members::attach( 203, T20_A, 'invited', 201 );
	WPCPM_Institution_Members::attach( 204, T20_A, 'invited', 201, 0, 'tutor' );
	WPCPM_Institution_Members::attach( 209, T20_B, 'manager', 1 );
	WPCPM_Institution_Members::attach( 210, T20_C, 'manager', 1 );
	// Fixed days, so the digests hold whatever the run's clock.
	foreach ( array( 201 => 1754000000, 202 => 1754100000, 203 => 1754200000, 204 => 1754300000 ) as $id => $since ) {
		$GLOBALS['umeta'][ $id ][ WPCPM_Institution_Members::META_MEMBERSHIP ]['since'] = $since;
	}
	$GLOBALS['kept'][ T20_A ] = array( 'tutors' => array( array( 'name' => 'Tom Example', 'email' => 'tom@example.org' ) ) );
	t20_new_request();
}
/** The long fixture: five people (one a 40-character word at a 48-character address, one with no date), three invitations, two tutors. */
function t20_long() {
	$GLOBALS['users'][205] = new WP_User( 205, T20_LONG, T20_MAIL, array( 'subscriber' ) );
	WPCPM_Institution_Members::attach( 205, T20_A, 'legacy', 0 );
	unset( $GLOBALS['umeta'][205][ WPCPM_Institution_Members::META_MEMBERSHIP ]['since'] );
	$sent = time() - 3600;
	t20_invite( 901, 'colleague@example.org', 201, $sent );
	t20_invite( 902, 'averyveryveryverylongmailboxname5678@example.org', 205, $sent );
	t20_invite( 903, 'third@example.org', 0, $sent );
	$GLOBALS['kept'][ T20_A ]['tutors'][] = array( 'name' => T20_LONG, 'email' => 'averyveryveryverylongmailboxname9012@example.org' );
}
/** A flash for one reader, as a handler leaves it. */
function t20_flash( $uid, $channel, array $value ) {
	WPCPM_Flash::set( $channel, $value, $uid );
}
/** A refused invitation: its typing kept and its outcome left, as `refuse()` leaves them. */
function t20_refuse_invite( $uid, $status, $email, $level, $detail = '' ) {
	$GLOBALS['uid'] = (int) $uid;
	WPCPM_Flash::keep( WPCPM_Institution_Invite::KEPT_FORM . T20_A, array( 'email' => $email, 'level' => $level ) );
	t20_flash( $uid, WPCPM_Institution_Invite::FLASH, array( 'status' => $status, 'detail' => $detail, 'record' => T20_A ) );
}
function t20_hostile() {
	$GLOBALS['index'][ T20_A ]['name']           = 'Schule ' . T20_BAD;
	$GLOBALS['index'][ T20_A ]['contact_person'] = 'Kontakt ' . T20_BAD;
	$GLOBALS['index'][ T20_A ]['contact_email']  = 'x"&<y>@example.org';
	$GLOBALS['users'][201]->display_name         = 'Anna ' . T20_BAD;
	$GLOBALS['users'][203]->display_name         = 'Cleo ' . T20_BAD;
	$GLOBALS['users'][203]->user_email           = 'c"&<z>@example.org';
	t20_invite( 904, 'i"&<w>@example.org', 201, time() - 3600 );
	$GLOBALS['kept'][ T20_A ]['tutors'][] = array( 'name' => 'Tutor ' . T20_BAD, 'email' => 't"&<v>@example.org' );
}

$t20_states = array(
	'representative, five people, three invitations, two tutors, long words' => static function () { t20_fresh(); t20_long(); return array( 201, T20_A, false ); },
	'representative, four people'                     => static function () { t20_fresh(); return array( 201, T20_A, false ); },
	'representative, no tutor listed'                 => static function () { t20_fresh(); $GLOBALS['kept'][ T20_A ] = array(); return array( 201, T20_A, false ); },
	'representative, nothing read yet'                => static function () { t20_fresh(); $GLOBALS['index_read'] = 0; return array( 201, T20_A, false ); },
	'tutor'                                           => static function () { t20_fresh(); t20_long(); return array( 204, T20_A, false ); },
	'Administrator, five people, three invitations'   => static function () { t20_fresh(); t20_long(); return array( 1, T20_A, true ); },
	'Administrator, a named contact with no account'  => static function () { t20_fresh(); return array( 1, T20_D, true ); },
	'Administrator, the contact named by address alone' => static function () { t20_fresh(); $GLOBALS['index'][ T20_D ]['contact_person'] = ''; return array( 1, T20_D, true ); },
	'representative of a school whose agreement is not settled' => static function () { t20_fresh(); return array( 210, T20_C, false ); },
	'member of another school'                        => static function () { t20_fresh(); return array( 209, T20_A, false ); },
	'representative, the invitation refused: not an address' => static function () { t20_fresh(); t20_refuse_invite( 201, 'invite-bad-email', 'anna at example', 'tutor' ); return array( 201, T20_A, false ); },
	'representative, the invitation refused: already a member' => static function () { t20_fresh(); t20_refuse_invite( 201, 'invite-member', 'cleo@example.org', 'representative', 'cleo@example.org' ); return array( 201, T20_A, false ); },
	'representative, the invitation refused: already waiting' => static function () { t20_fresh(); t20_long(); t20_refuse_invite( 201, 'invite-waiting', 'third@example.org', 'tutor', 'third@example.org' ); return array( 201, T20_A, false ); },
	'representative, the invitation refused: a level nobody knows' => static function () { t20_fresh(); t20_refuse_invite( 201, 'invite-bad-level', 'new@example.org', 'nonsense' ); return array( 201, T20_A, false ); },
	'representative, the invitation refused: the day\'s sends used up' => static function () { t20_fresh(); t20_refuse_invite( 201, 'invite-ceiling', T20_MAIL, 'tutor' ); return array( 201, T20_A, false ); },
	'representative, the tutor refused: no name'      => static function () { t20_fresh(); t20_flash( 201, WPCPM_Institution_Tutors::FLASH, array( 'status' => 'name', 'record' => T20_A ) ); return array( 201, T20_A, false ); },
	'representative, the tutor refused: no address'   => static function () { t20_fresh(); t20_flash( 201, WPCPM_Institution_Tutors::FLASH, array( 'status' => 'email', 'record' => T20_A ) ); return array( 201, T20_A, false ); },
	'representative, the tutor refused: not kept'     => static function () { t20_fresh(); t20_flash( 201, WPCPM_Institution_Tutors::FLASH, array( 'status' => 'error', 'record' => T20_A ) ); return array( 201, T20_A, false ); },
	'Administrator, an account already using the address' => static function () { t20_fresh(); $GLOBALS['grants'][1] = array( 'edit_user' ); t20_flash( 1, WPCPM_Institution_People::FLASH, array( 'status' => 'conflict', 'detail' => 'annaexample', 'record' => T20_A ) ); return array( 1, T20_A, true ); },
	'hostile, a representative'                       => static function () { t20_fresh(); t20_hostile(); return array( 201, T20_A, false ); },
	'hostile, an Administrator'                       => static function () { t20_fresh(); t20_hostile(); t20_flash( 1, WPCPM_Institution_People::FLASH, array( 'status' => 'removed', 'detail' => '', 'record' => T20_A ) ); return array( 1, T20_A, true ); },
	'hostile, the invitation refused with its typing'  => static function () { t20_fresh(); t20_hostile(); t20_refuse_invite( 201, 'invite-bad-email', 'x"&<script>@example.org', 'tutor' ); return array( 201, T20_A, false ); },
);
// Each outcome of the three classes, on the card of the school it is about.
foreach ( array( 'removed', 'left', 'ended', 'added', 'added-known', 'readded', 'readded-known', 'added-busy', 'readded-busy', 'already', 'conflict', 'bad-email', 'unknown-record', 'not-former', 'error' ) as $t20_status ) {
	$t20_states[ 'message, people: ' . $t20_status ] = static function () use ( $t20_status ) {
		t20_fresh();
		t20_flash( 201, WPCPM_Institution_People::FLASH, array( 'status' => $t20_status, 'detail' => 'conflict' === $t20_status ? 'annaexample' : '', 'record' => T20_A ) );
		return array( 201, T20_A, false );
	};
}
foreach ( array_keys( WPCPM_Institution_Invite::messages() ) as $t20_status ) {
	$t20_states[ 'message, invitations: ' . $t20_status ] = static function () use ( $t20_status ) {
		t20_fresh();
		t20_flash( 201, WPCPM_Institution_Invite::FLASH, array( 'status' => $t20_status, 'detail' => in_array( $t20_status, array( 'invite-sent', 'invite-resent', 'invite-cancelled', 'invite-member', 'invite-waiting' ), true ) ? 'colleague@example.org' : '', 'record' => T20_A ) );
		return array( 201, T20_A, false );
	};
}
foreach ( array( 'added', 'removed', 'already', 'gone', 'name', 'email', 'busy', 'error' ) as $t20_status ) {
	$t20_states[ 'message, tutors: ' . $t20_status ] = static function () use ( $t20_status ) {
		t20_fresh();
		t20_flash( 201, WPCPM_Institution_Tutors::FLASH, array( 'status' => $t20_status, 'record' => T20_A ) );
		return array( 201, T20_A, false );
	};
}

/** One state drawn: the old branch (no surface, or a named one with a live list), or the parts' (the Institution Dashboard on). */
function t20_play( callable $state, $parts, $surface = null, $live = null ) {
	list( $uid, $record, $manage ) = $state();
	WPCPM_UI::set_live( $parts ? T20_LIVE : $live );
	$named = $parts || null !== $surface;
	if ( $named ) {
		WPCPM_UI::enter( $parts ? 'institution' : (string) $surface );
	}
	$html = render_card( $uid, $record, $manage );
	if ( $named ) {
		WPCPM_UI::leave();
	}
	WPCPM_UI::set_live( null );
	return $html;
}
/** Every day masked, so a digest holds whatever the run's clock. */
function t20_mask( $html ) {
	return (string) preg_replace( '/(January|February|March|April|May|June|July|August|September|October|November|December) \d{1,2}, \d{4}/', '{day}', (string) $html );
}
function t20_dom( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="t20-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	return $doc;
}
/** Every form as the browser posts it: its own attributes, the question it asks, and each name=value it sends, in order. */
function t20_forms( $html ) {
	$doc  = t20_dom( $html );
	$path = new DOMXPath( $doc );
	$out  = array();
	foreach ( $doc->getElementsByTagName( 'form' ) as $form ) {
		$one = array(
			'method'     => $form->getAttribute( 'method' ),
			'action'     => $form->getAttribute( 'action' ),
			'enctype'    => $form->getAttribute( 'enctype' ),
			'novalidate' => $form->hasAttribute( 'novalidate' ),
			'confirm'    => $form->getAttribute( 'data-wpcpm-confirm' ),
			'fields'     => array(),
			'submits'    => array(),
		);
		foreach ( $path->query( './/input|.//select|.//textarea|.//button', $form ) as $control ) {
			$name = $control->getAttribute( 'name' );
			$type = strtolower( $control->getAttribute( 'type' ) );
			if ( 'button' === $control->nodeName || 'submit' === $type ) {
				if ( 'button' !== $type ) {
					$one['submits'][] = array( $name, $control->getAttribute( 'value' ), $control->getAttribute( 'data-wpcpm-confirm' ) );
				}
				continue;
			}
			if ( '' === $name ) {
				continue;
			}
			if ( in_array( $type, array( 'checkbox', 'radio' ), true ) && ! $control->hasAttribute( 'checked' ) ) {
				continue;
			}
			$one['fields'][] = $name . '=' . $control->getAttribute( 'value' );
		}
		$out[] = $one;
	}
	return $out;
}
/** What each submit button says, in order, its screen-reader words left out. */
function t20_labels( $html ) {
	$out = array();
	foreach ( t20_dom( $html )->getElementsByTagName( 'button' ) as $button ) {
		if ( 'button' !== strtolower( $button->getAttribute( 'type' ) ) ) {
			$out[] = trim( $button->firstChild ? $button->firstChild->textContent : '' );
		}
	}
	return $out;
}
/** Each control a browser checks before it posts: its name, type, `required` and `maxlength`. */
function t20_checks( $html ) {
	$out = array();
	foreach ( t20_dom( $html )->getElementsByTagName( 'input' ) as $input ) {
		if ( 'hidden' !== $input->getAttribute( 'type' ) ) {
			$out[] = array( $input->getAttribute( 'name' ), $input->getAttribute( 'type' ), $input->hasAttribute( 'required' ), $input->getAttribute( 'maxlength' ) );
		}
	}
	return $out;
}
/** The text a page reads (tags gone, entities read, break opportunities gone). */
function t20_plain( $html ) {
	return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( str_replace( '<wbr>', '', (string) $html ) ), ENT_QUOTES ) ) );
}
/** The words a page says, as a sorted set. */
function t20_words( $html ) {
	$html = str_replace( '<wbr>', '', (string) preg_replace( '#<span class="screen-reader-text">.*?</span>#s', '', (string) $html ) );
	preg_match_all( '/[\p{L}\p{N}]+/u', html_entity_decode( (string) preg_replace( '/<[^>]*>/', ' ', $html ), ENT_QUOTES ), $m );
	$words = array_values( array_unique( $m[0] ) );
	sort( $words );
	return $words;
}
/** Every id on a page that is printed more than once. */
function t20_repeated_ids( $html ) {
	preg_match_all( '/\bid="([^"]+)"/', (string) $html, $m );
	return array_values( array_unique( array_diff_assoc( $m[1], array_unique( $m[1] ) ) ) );
}
/** The stretch of a page from one needle to the next, '' when the first is not there. */
function t20_between( $html, $from, $to = '' ) {
	$at = strpos( (string) $html, $from );
	if ( false === $at ) {
		return '';
	}
	$end = '' !== $to ? strpos( (string) $html, $to, $at + strlen( $from ) ) : false;
	return false === $end ? substr( (string) $html, $at ) : substr( (string) $html, $at, $end - $at );
}
/** The rows of the parts' lists, each as its name, its tag, its facts and its note, read as text. */
function t20_rows( $html ) {
	$out  = array();
	$doc  = t20_dom( $html );
	$path = new DOMXPath( $doc );
	foreach ( $path->query( '//li[contains(concat(" ", @class, " "), " wpcpm-ui-list__row ")]' ) as $row ) {
		$name  = $path->query( './/*[contains(@class, "wpcpm-ui-list__name")]', $row )->item( 0 );
		$tag   = $path->query( './div/span[contains(concat(" ", @class, " "), " wpcpm-status ")]', $row )->item( 0 );
		$facts = array();
		foreach ( $path->query( './/span[contains(@class, "wpcpm-ui-item__fact")]', $row ) as $fact ) {
			$facts[] = trim( $fact->textContent );
		}
		$note  = $path->query( './p[contains(@class, "wpcpm-ui-list__note")]', $row )->item( 0 );
		$out[] = array( $name ? trim( $name->textContent ) : '', $tag ? trim( $tag->textContent ) : '', $facts, $note ? trim( $note->textContent ) : '' );
	}
	return $out;
}

// Draw every state.
$t20_new = array();
foreach ( $t20_states as $t20_name => $t20_state ) {
	$t20_new[ $t20_name ] = t20_play( $t20_state, true );
}
if ( getenv( 'T20_DUMP' ) ) {
	file_put_contents( getenv( 'T20_DUMP' ), json_encode( array( 'new' => $t20_new ) ) );
}

// A fact on the right person's line (Task 23, fix round 1, S5): each row of the members, the waiting invitations and
// the tutors, as the reader reads it, name, tag, facts and actions together. The word sets above cannot tell which
// line holds a level, a day or a button; these can. Read off the fixture by hand: Anna Example is the reader, so Leave
// is on her line and Remove on the others'; Tess Example is the one Tutor; Bob Example is the school's contact; the
// waiting invitations were sent today and are good for fourteen days; a tutor is offered no action on any line.
$t20_rows_day   = array( '{sent}' => gmdate( 'F j, Y' ), '{until}' => gmdate( 'F j, Y', time() + 14 * DAY_IN_SECONDS ) );
$t20_rows_want  = array(
	'representative, five people, three invitations, two tutors, long words' => array(
	'Anna Example | anna@example.org; Representative; Added by an Administrator, July 31, 2025. | Leave',
	'Bob Example [the institution\'s contact] | bob@example.org; Representative; Added by the institutions sync, August 2, 2025. | Remove',
	'Cleo Example | cleo@example.org; Representative; Joined by invitation, August 3, 2025. | Remove',
	'Tess Example | tess@example.org; Tutor; Joined by invitation, August 4, 2025. | Remove',
	'Exampleinstituteforappliedwebengineerings | averyveryveryverylongmailboxname1234@example.org; Representative; Added before memberships were recorded; the date was not recorded. | Remove',
	'third@example.org | Invited by a colleague on {sent}, good until {until}. | Resend, Cancel invitation',
	'averyveryveryverylongmailboxname5678@example.org | Invited by Exampleinstituteforappliedwebengineerings on {sent}, good until {until}. | Resend, Cancel invitation',
	'colleague@example.org | Invited by Anna Example on {sent}, good until {until}. | Resend, Cancel invitation',
	'Exampleinstituteforappliedwebengineerings | averyveryveryverylongmailboxname9012@example.org | Remove',
	'Tom Example | tom@example.org | Remove',
	),
	'Administrator, five people, three invitations' => array(
	'Anna Example | anna@example.org; Representative; Added by an Administrator, July 31, 2025. | Remove',
	'Bob Example [the institution\'s contact] | bob@example.org; Representative; Added by the institutions sync, August 2, 2025. | Remove',
	'Cleo Example | cleo@example.org; Representative; Joined by invitation, August 3, 2025. | Remove',
	'Tess Example | tess@example.org; Tutor; Joined by invitation, August 4, 2025. | Remove',
	'Exampleinstituteforappliedwebengineerings | averyveryveryverylongmailboxname1234@example.org; Representative; Added before memberships were recorded; the date was not recorded. | Remove',
	'third@example.org | Invited by a colleague on {sent}, good until {until}. | Resend, Cancel invitation',
	'averyveryveryverylongmailboxname5678@example.org | Invited by Exampleinstituteforappliedwebengineerings on {sent}, good until {until}. | Resend, Cancel invitation',
	'colleague@example.org | Invited by Anna Example on {sent}, good until {until}. | Resend, Cancel invitation',
	'Exampleinstituteforappliedwebengineerings | averyveryveryverylongmailboxname9012@example.org | Remove',
	'Tom Example | tom@example.org | Remove',
	),
	'tutor' => array(
	'Anna Example | anna@example.org; Representative; Added by an Administrator, July 31, 2025. | ',
	'Bob Example [the institution\'s contact] | bob@example.org; Representative; Added by the institutions sync, August 2, 2025. | ',
	'Cleo Example | cleo@example.org; Representative; Joined by invitation, August 3, 2025. | ',
	'Tess Example | tess@example.org; Tutor; Joined by invitation, August 4, 2025. | ',
	'Exampleinstituteforappliedwebengineerings | averyveryveryverylongmailboxname1234@example.org; Representative; Added before memberships were recorded; the date was not recorded. | ',
	'Exampleinstituteforappliedwebengineerings | averyveryveryverylongmailboxname9012@example.org | ',
	'Tom Example | tom@example.org | ',
	),
);
$t20_rows_got = array();
foreach ( $t20_rows_want as $t20_name => $t20_rows ) {
	$t20_rows_want[ $t20_name ] = array_map( static function ( $row ) use ( $t20_rows_day ) { return strtr( $row, $t20_rows_day ); }, $t20_rows );
	$t20_rows_got[ $t20_name ]  = wpcpm_list_rows( $t20_new[ $t20_name ] );
}
ck( 'each member, waiting invitation and tutor reads its own facts and its own actions on its own line, for a representative, an Administrator and a tutor', $t20_rows_got, $t20_rows_want );

// The whole view on the real card (Task 23, fix round 1): every state of every reader, its Tutors section included and
// each fold open, under the Your institution view's own head (`bin/stubs/view-faults.php`). The view's three cards
// together are drawn in `bin/test-institution-details-card.php`.
$t20_open = array_map( static function ( $html ) { return str_replace( '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id=', '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id=', (string) $html ); }, $t20_new );
ck( 'the whole Your institution view on the real people card, every state: one H1, the headings in order, no id twice, the lede once, no title again', array( wpcpm_view_faults_of( 'Your institution', $t20_open ), count( array_filter( array_map( 'trim', $t20_open ) ) ) >= 50 ), array( array(), true ) );

// What the old card posted, said and checked in every state, read from the classes at db850661 (the last commit that
// drew it) before its own branch was deleted (Task 23), the days masked: digests of `t20_forms()`, of `t20_labels()`
// (its Cancel read as "Cancel invitation"), of `t20_checks()` and of the sorted words `t20_words()` reads.
$t20_old_pins = array(
	'forms' => array(
		'representative, five people, three invitations, two tutors, long words' => 'c6df4e6ee7167e383eaedce14d5ad0d2',
		'representative, four people' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'representative, no tutor listed' => 'cd27a472d8b4afa38455d85162c7a251',
		'representative, nothing read yet' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'tutor' => '40cd750bba9870f18aada2478b24840a',
		'Administrator, five people, three invitations' => 'd75fdd772fd6669f5ab5008042a97e2a',
		'Administrator, a named contact with no account' => '6c9f0fa1316e1e2818807a0503bf3b41',
		'Administrator, the contact named by address alone' => '6c9f0fa1316e1e2818807a0503bf3b41',
		'representative of a school whose agreement is not settled' => '40cd750bba9870f18aada2478b24840a',
		'member of another school' => '40cd750bba9870f18aada2478b24840a',
		'representative, the invitation refused: not an address' => '64b94e451b349099ba933eee5ba95762',
		'representative, the invitation refused: already a member' => '92e1a20a4b47fe73d567ab668413d565',
		'representative, the invitation refused: already waiting' => '3658861da58744701b6001759cbf8ae0',
		'representative, the invitation refused: a level nobody knows' => 'b519880508f108e9c103e337241ef5aa',
		'representative, the invitation refused: the day\'s sends used up' => '08f0b899c4c8a55793692dfdc233609b',
		'representative, the tutor refused: no name' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'representative, the tutor refused: no address' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'representative, the tutor refused: not kept' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'Administrator, an account already using the address' => '1b7218f2284d0ccfb890694c0020d61b',
		'hostile, a representative' => '0131e2e3f7a517a4a0218b437e2cf15a',
		'hostile, an Administrator' => '7b5e5817175ec0143c125ec191b63eb3',
		'hostile, the invitation refused with its typing' => 'a6a74366266647294039f8623b3e60eb',
		'message, people: removed' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: left' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: ended' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: added' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: added-known' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: readded' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: readded-known' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: added-busy' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: readded-busy' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: already' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: conflict' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: bad-email' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: unknown-record' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: not-former' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, people: error' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-sent' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-unsent' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-resent' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-cancelled' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-member' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-waiting' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-full' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-ceiling' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-bad-level' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-bad-email' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-unknown' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-gone' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-joined' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, invitations: invite-error' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, tutors: added' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, tutors: removed' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, tutors: already' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, tutors: gone' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, tutors: name' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, tutors: email' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, tutors: busy' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
		'message, tutors: error' => '593d7d6d5b0bdea7cd60a69fa4d61c57',
	),
	'labels' => array(
		'representative, five people, three invitations, two tutors, long words' => 'e8feca51f963783d66e1580c57c50d8f',
		'representative, four people' => '9ae6c68319767359772fa2cd8dae244a',
		'representative, no tutor listed' => '1a6981a82b24bcc8f489bafa66098740',
		'representative, nothing read yet' => '9ae6c68319767359772fa2cd8dae244a',
		'tutor' => '40cd750bba9870f18aada2478b24840a',
		'Administrator, five people, three invitations' => 'c5eae6c3bfa0e56719b96b104fb5ff4b',
		'Administrator, a named contact with no account' => '50c302aec60e2f3832656c6ed65a0177',
		'Administrator, the contact named by address alone' => '50c302aec60e2f3832656c6ed65a0177',
		'representative of a school whose agreement is not settled' => '40cd750bba9870f18aada2478b24840a',
		'member of another school' => '40cd750bba9870f18aada2478b24840a',
		'representative, the invitation refused: not an address' => '9ae6c68319767359772fa2cd8dae244a',
		'representative, the invitation refused: already a member' => '9ae6c68319767359772fa2cd8dae244a',
		'representative, the invitation refused: already waiting' => 'e8feca51f963783d66e1580c57c50d8f',
		'representative, the invitation refused: a level nobody knows' => '9ae6c68319767359772fa2cd8dae244a',
		'representative, the invitation refused: the day\'s sends used up' => '9ae6c68319767359772fa2cd8dae244a',
		'representative, the tutor refused: no name' => '9ae6c68319767359772fa2cd8dae244a',
		'representative, the tutor refused: no address' => '9ae6c68319767359772fa2cd8dae244a',
		'representative, the tutor refused: not kept' => '9ae6c68319767359772fa2cd8dae244a',
		'Administrator, an account already using the address' => '832d5100948cb4a6876cee92dff2167f',
		'hostile, a representative' => 'e5bfb1581d9bfd5677cd20ca3671cecd',
		'hostile, an Administrator' => 'a1b582bf218131283ec01efc8a9d5feb',
		'hostile, the invitation refused with its typing' => 'e5bfb1581d9bfd5677cd20ca3671cecd',
		'message, people: removed' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: left' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: ended' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: added' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: added-known' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: readded' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: readded-known' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: added-busy' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: readded-busy' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: already' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: conflict' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: bad-email' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: unknown-record' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: not-former' => '9ae6c68319767359772fa2cd8dae244a',
		'message, people: error' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-sent' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-unsent' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-resent' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-cancelled' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-member' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-waiting' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-full' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-ceiling' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-bad-level' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-bad-email' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-unknown' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-gone' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-joined' => '9ae6c68319767359772fa2cd8dae244a',
		'message, invitations: invite-error' => '9ae6c68319767359772fa2cd8dae244a',
		'message, tutors: added' => '9ae6c68319767359772fa2cd8dae244a',
		'message, tutors: removed' => '9ae6c68319767359772fa2cd8dae244a',
		'message, tutors: already' => '9ae6c68319767359772fa2cd8dae244a',
		'message, tutors: gone' => '9ae6c68319767359772fa2cd8dae244a',
		'message, tutors: name' => '9ae6c68319767359772fa2cd8dae244a',
		'message, tutors: email' => '9ae6c68319767359772fa2cd8dae244a',
		'message, tutors: busy' => '9ae6c68319767359772fa2cd8dae244a',
		'message, tutors: error' => '9ae6c68319767359772fa2cd8dae244a',
	),
	'checks' => array(
		'representative, five people, three invitations, two tutors, long words' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'representative, four people' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'representative, no tutor listed' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'representative, nothing read yet' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'tutor' => '40cd750bba9870f18aada2478b24840a',
		'Administrator, five people, three invitations' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'Administrator, a named contact with no account' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'Administrator, the contact named by address alone' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'representative of a school whose agreement is not settled' => '40cd750bba9870f18aada2478b24840a',
		'member of another school' => '40cd750bba9870f18aada2478b24840a',
		'representative, the invitation refused: not an address' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'representative, the invitation refused: already a member' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'representative, the invitation refused: already waiting' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'representative, the invitation refused: a level nobody knows' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'representative, the invitation refused: the day\'s sends used up' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'representative, the tutor refused: no name' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'representative, the tutor refused: no address' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'representative, the tutor refused: not kept' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'Administrator, an account already using the address' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'hostile, a representative' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'hostile, an Administrator' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'hostile, the invitation refused with its typing' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: removed' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: left' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: ended' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: added' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: added-known' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: readded' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: readded-known' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: added-busy' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: readded-busy' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: already' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: conflict' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: bad-email' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: unknown-record' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: not-former' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, people: error' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-sent' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-unsent' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-resent' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-cancelled' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-member' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-waiting' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-full' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-ceiling' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-bad-level' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-bad-email' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-unknown' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-gone' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-joined' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, invitations: invite-error' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, tutors: added' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, tutors: removed' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, tutors: already' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, tutors: gone' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, tutors: name' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, tutors: email' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, tutors: busy' => 'dd8b42927bfb852e3e5354ffdd516afc',
		'message, tutors: error' => 'dd8b42927bfb852e3e5354ffdd516afc',
	),
	'words' => array(
		'representative, five people, three invitations, two tutors, long words' => '91240354ce6a65831cc4503c71945fbf',
		'representative, four people' => 'a973063f3fe73157691a50e2ca557a11',
		'representative, no tutor listed' => 'fad0c55606fcefb55ec67906bb2ee5a1',
		'representative, nothing read yet' => '120b2c1f04e20a3cfeeb202d045f79da',
		'tutor' => '74da154f60c28c0c39617fb2e3e98e39',
		'Administrator, five people, three invitations' => 'c536c9e7e218289f3b586af51d761f0b',
		'Administrator, a named contact with no account' => 'd66ddadac0a9b6e33d1b815b46afb937',
		'Administrator, the contact named by address alone' => '8fd8d38ce4bbc3c3e0b550217386cfc1',
		'representative of a school whose agreement is not settled' => '40cd750bba9870f18aada2478b24840a',
		'member of another school' => '40cd750bba9870f18aada2478b24840a',
		'representative, the invitation refused: not an address' => 'f3bcaccc6177a5dd4191af75984d0627',
		'representative, the invitation refused: already a member' => '1fd4f8a3e408def9dc16e451ddd71184',
		'representative, the invitation refused: already waiting' => 'a7a582fb726ef14a4c70d503c0cd0060',
		'representative, the invitation refused: a level nobody knows' => '90a9bcbf85c3c666fd5a699e1c748990',
		'representative, the invitation refused: the day\'s sends used up' => '62ff45d1340c519d1e8e62e88c470c4e',
		'representative, the tutor refused: no name' => '81d7ca332a404d211d0a2368993f9803',
		'representative, the tutor refused: no address' => '9daf0115d3be1cf7111e68f299a62c47',
		'representative, the tutor refused: not kept' => 'acb7bcd8d5fa1de9e87eefeed8f3a5cc',
		'Administrator, an account already using the address' => '097b2ce079c78d1d77a3d95781bd9070',
		'hostile, a representative' => 'ae8261a4367447d125c6e1719f95691c',
		'hostile, an Administrator' => '8964caf0757656a04b4f13c22ffe7609',
		'hostile, the invitation refused with its typing' => '2f3a0ce599fe65844dc3d0e973148007',
		'message, people: removed' => '3d579b73013f907062bab77bcffd608a',
		'message, people: left' => '7fb739ca895f5823f7d61974a5b112bb',
		'message, people: ended' => 'd75f4730bfdd5f785a483a2065a8ef6c',
		'message, people: added' => 'f366220409b7e533d030cd1d936b1e35',
		'message, people: added-known' => 'a8af156c1be81aa91ae40d5287451889',
		'message, people: readded' => 'd1ad4a36b0aa4e393ea4b6e684616517',
		'message, people: readded-known' => '5b80756a37c04475073e9384eae43a47',
		'message, people: added-busy' => 'fbf74c4b49d4ed65349a1d296f7a3ffb',
		'message, people: readded-busy' => '3296a6653a2cf556cf32f0657f4681fc',
		'message, people: already' => '6deae185ed36cef626213a8ad52bb9fc',
		'message, people: conflict' => '8b8029871420dc09b231960e081e256f',
		'message, people: bad-email' => '313f10cbdbbf7d2a8debb9860a6d7f78',
		'message, people: unknown-record' => 'e8b28c5dc6723973f45cc832e881b943',
		'message, people: not-former' => 'c5c83302f3419eda61992b3147cabcd2',
		'message, people: error' => 'acb7bcd8d5fa1de9e87eefeed8f3a5cc',
		'message, invitations: invite-sent' => 'd54183c4d69af1540828c11d6d73890f',
		'message, invitations: invite-unsent' => 'd6c84b204566e214c36fd72536407447',
		'message, invitations: invite-resent' => '3d9d02a6fc68400fcd03a7b7e4fbccea',
		'message, invitations: invite-cancelled' => 'c68a0a5dcf8ac7ad7dc7d12269ccdabd',
		'message, invitations: invite-member' => '1fd4f8a3e408def9dc16e451ddd71184',
		'message, invitations: invite-waiting' => 'ba80b6c91fd3a8b60130e55a9b2c944c',
		'message, invitations: invite-full' => '276827ca4c9cf528557dba36ebfb0237',
		'message, invitations: invite-ceiling' => '62ff45d1340c519d1e8e62e88c470c4e',
		'message, invitations: invite-bad-level' => '90a9bcbf85c3c666fd5a699e1c748990',
		'message, invitations: invite-bad-email' => 'f3bcaccc6177a5dd4191af75984d0627',
		'message, invitations: invite-unknown' => '83279360554f2d8bd67fedfd0acba51a',
		'message, invitations: invite-gone' => 'ac5cca21053ff4e16209479eacdd57c9',
		'message, invitations: invite-joined' => '9ce1da4153a33997d1930a50cb5e8752',
		'message, invitations: invite-error' => 'acb7bcd8d5fa1de9e87eefeed8f3a5cc',
		'message, tutors: added' => '18fa951c8ae4e5c3fec56eef6d26bff2',
		'message, tutors: removed' => 'c13ed1dad4598860415f4be79e109ac3',
		'message, tutors: already' => 'df555f1eb3b3638f62ce8bc8577f4452',
		'message, tutors: gone' => 'afd0b06eea7cf41e96d2aa8b1fbc10c9',
		'message, tutors: name' => '81d7ca332a404d211d0a2368993f9803',
		'message, tutors: email' => '9daf0115d3be1cf7111e68f299a62c47',
		'message, tutors: busy' => '02fe237e3c3ba72e72a0d566c6c00355',
		'message, tutors: error' => 'acb7bcd8d5fa1de9e87eefeed8f3a5cc',
	),
);
/** A drawing's words, once each, sorted, the days masked: what `$t20_old_pins['words']` holds a digest of. */
function t20_word_set( $html, array $gone = array(), array $added = array() ) {
	$words = array_values( array_unique( array_merge( array_diff( t20_words( t20_mask( $html ) ), $added ), $gone ) ) );
	sort( $words );
	return md5( serialize( $words ) );
}

// Manage members on the Institutions screen: the same rows, drawn by render_member() under the screen's own surface.
$t20_manager_states = array(
	'five people, three invitations' => static function () { t20_fresh(); t20_long(); return T20_A; },
	'a named contact with no account' => static function () { t20_fresh(); return T20_D; },
	'a removal said'                  => static function () { t20_fresh(); t20_flash( 1, WPCPM_Institution_People::FLASH, array( 'status' => 'removed', 'detail' => '', 'record' => T20_A ) ); return T20_A; },
	'an address already in use'       => static function () { t20_fresh(); $GLOBALS['grants'][1] = array( 'edit_user' ); t20_flash( 1, WPCPM_Institution_People::FLASH, array( 'status' => 'conflict', 'detail' => 'annaexample', 'record' => T20_A ) ); return T20_A; },
	'an invitation resent'            => static function () { t20_fresh(); t20_flash( 1, WPCPM_Institution_Invite::FLASH, array( 'status' => 'invite-resent', 'detail' => 'colleague@example.org', 'record' => T20_A ) ); return T20_A; },
	'hostile'                         => static function () { t20_fresh(); t20_hostile(); return T20_A; },
);
function t20_manager( callable $state, $surface = null, $live = null, $outcome = false ) {
	$record = $state();
	WPCPM_UI::set_live( $live );
	if ( null !== $surface ) {
		WPCPM_UI::enter( (string) $surface );
	}
	$GLOBALS['uid'] = 1;
	ob_start();
	if ( $outcome ) {
		WPCPM_Institution_People::render_outcome( $record );
	} else {
		WPCPM_Institution_People::render_manager( $record );
	}
	$html = (string) ob_get_clean();
	if ( null !== $surface ) {
		WPCPM_UI::leave();
	}
	WPCPM_UI::set_live( null );
	return $html;
}
$t20_manager_old = array();
foreach ( $t20_manager_states as $t20_name => $t20_state ) {
	$t20_manager_old[ $t20_name ]                 = t20_manager( $t20_state );
	$t20_manager_old[ 'outcome: ' . $t20_name ] = t20_manager( $t20_state, null, null, true );
}

// The shell's outcome and the parts' status tag with the Administrator Dashboard's own arguments.
$t20_shared = array(
	'message, a saved card'           => WPCPM_Dashboard_Card::message( 'success', 'The settings were saved.' ),
	'message, a refusal led to a box' => WPCPM_Dashboard_Card::message( 'error', 'Give a reason.', 'wpcpm-dashboard__message', 'wpcpm-reason-12' ),
	'message, an unknown tone'        => WPCPM_Dashboard_Card::message( 'nonsense', 'A <b> & "c"', 'wpcpm-details__message' ),
	'tag, Revoked'                    => WPCPM_UI::tag( 'Revoked' ),
	'tag, Waits'                      => WPCPM_UI::tag( 'Waits', 'red' ),
	'row, an agreement out of force'  => WPCPM_UI::row( array( 'id' => 'wpcpm-sponsor-agreement-7', 'name' => 'Example Co', 'tag' => array( 'words' => 'Revoked', 'tone' => '' ), 'facts' => array( 'Uploaded by Ana on 2 March 2026' ), 'actions' => array( '<form></form>' ) ) ),
);

$t20_manager_digests = array_map( static function ( $html ) { return md5( t20_mask( $html ) ); }, $t20_manager_old );
$t20_shared_digests  = array_map( 'md5', $t20_shared );
if ( getenv( 'T20_PRINT' ) ) {
	foreach ( array( 'T20_MANAGER_BASE' => $t20_manager_digests, 'T20_SHARED_BASE' => $t20_shared_digests ) as $t20_const => $t20_list ) {
		echo $t20_const . "\n";
		foreach ( $t20_list as $t20_name => $t20_md5 ) {
			echo "\t'" . str_replace( "'", "\\'", $t20_name ) . "' => '" . $t20_md5 . "',\n";
		}
	}
}

// ---- 1. The old branch is today's, byte for byte, under every surface (standing rule 1) ----

ck( 'Manage members (render_manager(), render_outcome()) draws what the base drew', $t20_manager_digests, T20_MANAGER_BASE );
$a9_base_count = static function ( $html ) {
	return md5( t20_mask( (string) preg_replace_callback( '#<span class="wpcpm-people__count">(\d+)</span>#', static function ( $m ) { return '<span class="wpcpm-people__count">' . ( (int) $m[1] + 1 ) . '</span>'; }, $html, 1 ) ) );
};
ck( 'A9: and the two drawings with a contact who has no account differ from the base\'s by the heading\'s count alone, one less', array( $a9_base_count( $t20_manager_old['a named contact with no account'] ), $a9_base_count( $t20_manager_old['hostile'] ) ), array( '29361bc52f9fac89318ffc16c3653961', 'b5dddd7dfcffd4eff93a0189cbb4a499' ) );
$t20_manager_moved = array();
foreach ( $t20_manager_states as $t20_name => $t20_state ) {
	foreach ( array( false, true ) as $t20_outcome ) {
		$t20_key = ( $t20_outcome ? 'outcome: ' : '' ) . $t20_name;
		foreach ( array( array( 'wpcpm-institutions/accounts', T20_LIVE ), array( 'wpcpm-institutions/accounts', null ) ) as $t20_pair ) {
			if ( t20_mask( t20_manager( $t20_state, $t20_pair[0], $t20_pair[1], $t20_outcome ) ) !== t20_mask( $t20_manager_old[ $t20_key ] ) ) {
				$t20_manager_moved[] = $t20_key . ' under ' . $t20_pair[0] . ( null === $t20_pair[1] ? '' : ', the Institution Dashboard on' );
			}
		}
	}
}
ck( 'and the same bytes under its own surface with the Institution Dashboard on (the screen prints the base\'s markup)', $t20_manager_moved, array() );
ck( 'the shell\'s outcome and the status tag print what the base printed for the Administrator Dashboard\'s arguments', $t20_shared_digests, T20_SHARED_BASE );

// ---- 2. Who sees what does not move (standing rule 3, ruling 6) ----

ck( 'a locked school\'s representative and another school\'s member are drawn nothing, as the old card drew them nothing', array(
	$t20_new['representative of a school whose agreement is not settled'],
	$t20_new['member of another school'],
), array( '', '' ) );
$t20_forms_moved  = array();
$t20_labels_moved = array();
$t20_checks_moved = array();
foreach ( $t20_states as $t20_name => $t20_state ) {
	$t20_masked = t20_mask( $t20_new[ $t20_name ] );
	if ( md5( serialize( t20_forms( $t20_masked ) ) ) !== $t20_old_pins['forms'][ $t20_name ] ) {
		$t20_forms_moved[] = $t20_name;
	}
	if ( md5( serialize( t20_labels( $t20_masked ) ) ) !== $t20_old_pins['labels'][ $t20_name ] ) {
		$t20_labels_moved[] = $t20_name;
	}
	if ( md5( serialize( t20_checks( $t20_masked ) ) ) !== $t20_old_pins['checks'][ $t20_name ] ) {
		$t20_checks_moved[] = $t20_name;
	}
}
ck( 'every form posts what it posts today, in every state: method, action, each name=value (the nonce, the referer, wpcpm_from where today\'s carries it), the submit and the question', $t20_forms_moved, array() );
ck( 'and every button says today\'s words but one: Cancel says "Cancel invitation"', $t20_labels_moved, array() );
ck( 'and the browser checks the same boxes before it posts (type, required, maxlength)', $t20_checks_moved, array() );

$t20_long_new = $t20_new['representative, five people, three invitations, two tutors, long words'];
$t20_forms_long = t20_forms( $t20_long_new );
ck( 'the seven forms of a representative\'s card, in today\'s order: Remove or Leave on each of five people, Resend and Cancel on each of three invitations, Send invitation, Remove on each of two tutors, Add', array_map( static function ( $form ) { return preg_replace( '/^action=/', '', (string) current( array_filter( $form['fields'], static function ( $f ) { return 0 === strpos( $f, 'action=' ); } ) ) ); }, $t20_forms_long ), array(
	'wpcpm_remove_member', 'wpcpm_remove_member', 'wpcpm_remove_member', 'wpcpm_remove_member', 'wpcpm_remove_member',
	'wpcpm_resend_invite', 'wpcpm_cancel_invite', 'wpcpm_resend_invite', 'wpcpm_cancel_invite', 'wpcpm_resend_invite', 'wpcpm_cancel_invite',
	'wpcpm_invite_member',
	'wpcpm_institution_tutor_remove', 'wpcpm_institution_tutor_remove',
	'wpcpm_institution_tutor_add',
) );
ck( 'the referer is drawn once in every form, and no form on the card carries wpcpm_from (only Manage members\' forms do)', array(
	array_values( array_unique( array_map( static function ( $form ) { return count( preg_grep( '/^_wp_http_referer=/', $form['fields'] ) ); }, $t20_forms_long ) ) ),
	count( preg_grep( '/^wpcpm_from=/', array_merge( ...array_column( $t20_forms_long, 'fields' ) ) ) ),
	count( preg_grep( '/^wpcpm_from=admin$/', array_merge( ...array_column( t20_forms( $t20_manager_old['five people, three invitations'] ), 'fields' ) ) ) ) > 0,
), array( array( 1 ), 0, true ) );
ck( 'the parts\' nonces carry no id', preg_match( '/<input type="hidden" id="_wpnonce"/', $t20_long_new ), 0 );
ck( 'the questions are today\'s: Remove and Leave name the person and the school, Cancel invitation the address, Remove a tutor the tutor', array(
	md5( serialize( t20_forms( t20_mask( $t20_long_new ) ) ) ) === $t20_old_pins['forms']['representative, five people, three invitations, two tutors, long words'],
	has( $t20_long_new, 'data-wpcpm-confirm="Cancel the invitation to colleague@example.org? The link in their email stops working straight away."' ),
	has( $t20_long_new, 'data-wpcpm-confirm="Leave Universidad Example on this site? You will lose access to its students, the other 4 members keep access, and your account is kept."' ),
	has( $t20_long_new, 'data-wpcpm-confirm="Remove Tom Example from the tutors? Their account, if they have one, stays until you remove it above."' ),
), array( true, true, true, true ) );

// The facts each reader is drawn, and the actions each is offered, as the old card drew them.
$t20_readers = array(
	'a representative'                    => 'representative, five people, three invitations, two tutors, long words',
	'a tutor'                             => 'tutor',
	'an Administrator viewing the school' => 'Administrator, five people, three invitations',
	'an Administrator, a contact alone'   => 'Administrator, a named contact with no account',
	'a locked school\'s representative'   => 'representative of a school whose agreement is not settled',
);
$t20_moves = array(
	'a representative'                    => array( array( 'Their' ), array( 'Required' ) ),
	'a tutor'                             => array( array(), array() ),
	'an Administrator viewing the school' => array( array( 'Their' ), array( 'Required' ) ),
	// 1.123.98, A9: the head counts no one here, "0 people" ("people" is in today's intro already).
	'an Administrator, a contact alone'   => array( array( 'Their' ), array( 'Required', '0' ) ),
	'a locked school\'s representative'   => array( array(), array() ),
);
$t20_words_moved = array();
foreach ( $t20_readers as $t20_reader => $t20_name ) {
	if ( t20_word_set( $t20_new[ $t20_name ], $t20_moves[ $t20_reader ][0], $t20_moves[ $t20_reader ][1] ) !== $t20_old_pins['words'][ $t20_name ] ) {
		$t20_words_moved[] = $t20_reader;
	}
}
ck( 'per reader, the facts the old card said: no word goes but "Their" (the invite box is "Email address"), and none comes but the parts\' "Required" and the head\'s count in words ("people" is in today\'s intro already)', $t20_words_moved, array() );
$t20_tutor_new = $t20_new['tutor'];
ck( 'a tutor\'s card on the parts: the head, the short intro, the five people with no Remove or Leave, the tutors with no Remove and no Add, the read line; no invitation, no form at all', array(
	count( t20_forms( $t20_tutor_new ) ),
	count( t20_rows( $t20_tutor_new ) ),
	has( $t20_tutor_new, 'Invitations waiting' ) || has( $t20_tutor_new, 'Invite a colleague' ) || has( $t20_tutor_new, 'Add a tutor' ),
	has( $t20_tutor_new, '<p class="wpcpm-ui-intro">Everyone here with an account can see Universidad Example&#039;s students on this site.</p>' ),
	has( $t20_tutor_new, 'Representatives can remove anyone' ),
	has( $t20_tutor_new, 'The institution&#039;s details were read on ' ),
), array( 0, 7, false, true, false, true ) );

// ---- 3. The card's frame on the parts ----

ck( 'the card is the shell\'s card fold, open, its anchor on the section, its head the name and the count in words', array(
	0 === strpos( $t20_long_new, '<section class="wpcpm-ui-fold-card" id="wpcpm-people"><details class="wpcpm-ui-fold" open><summary class="wpcpm-ui-fold__head"><h3 class="wpcpm-ui-fold__title">Representatives and tutors</h3><span class="wpcpm-ui-fold__phrase">5 people</span>' ),
	// 1.123.98, A9: the head counts the members only, the people with an account here; the contact's row with no account
	// is drawn and not counted, so a school with that row alone reads 0.
	has( $t20_new['Administrator, a named contact with no account'], '<span class="wpcpm-ui-fold__phrase">0 people</span>' ),
	substr( $t20_long_new, -strlen( '</div></details></section>' ) ),
), array( true, true, '</div></details></section>' ) );
$a9_two = t20_play( static function () {
	t20_fresh();
	$GLOBALS['users'][220] = new WP_User( 220, 'Eve Example', 'eve@example.org', array( 'subscriber' ) );
	$GLOBALS['users'][221] = new WP_User( 221, 'Finn Example', 'finn@example.org', array( 'subscriber' ) );
	WPCPM_Institution_Members::attach( 220, T20_D, 'manager', 1 );
	WPCPM_Institution_Members::attach( 221, T20_D, 'manager', 1 );
	return array( 1, T20_D, true );
}, true );
ck( 'A9: two members and the contact\'s row with no account count two, and the contact\'s row is still drawn', array(
	has( $a9_two, '<span class="wpcpm-ui-fold__phrase">2 people</span>' ),
	has( $a9_two, '>Dana Example<' ),
	has( $t20_new['Administrator, a named contact with no account'], '>Dana Example<' ),
	has( $t20_new['Administrator, a named contact with no account'], 'Nobody can act for this institution on this site right now.' ),
), array( true, true, true, true ) );
ck( 'today\'s intro sentence first in the body, the whole one where the reader may change the list', has( $t20_long_new, '<div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro">Everyone here with an account can see Universidad Example&#039;s students on this site. Representatives can remove anyone, including themselves, and tutors change nothing. A removal keeps the account and tells the people who are left.</p>' ), true );
ck( 'no class of today\'s card on the parts but the message\'s own (wpcpm-people__message, with its tone)', array_values( array_unique( preg_match_all( '/wpcpm-(people__(?!message\b)[a-z_-]+|tutors__[a-z-]+|institution__[a-z]+|group__[a-z]+|mentee__toggle)\b|button-(primary|secondary|link)|class="(button|description)"/', implode( '', $t20_new ), $t20_m ) ? $t20_m[0] : array() ) ), array() );
ck( 'no id printed twice on the card, in any state', array_keys( array_filter( array_map( 't20_repeated_ids', $t20_new ) ) ), array() );
ck( 'never two titles in a row: the card\'s own head, then the intro; Tutors is a heading of this card, on its anchor', array(
	1 === substr_count( $t20_long_new, '<h3 ' ),
	has( $t20_long_new, '<h4 class="wpcpm-ui-h" id="wpcpm-tutors">Tutors</h4>' ),
	has( $t20_long_new, 'id="wpcpm-people"' ),
), array( true, true, true ) );

// ---- 4. The people rows (parts design 2.8) ----

$t20_rows = t20_rows( t20_between( $t20_long_new, '<ul class="wpcpm-ui-list', '</ul>' ) );
ck( 'the people are a list of rows with their facts under the name: the name, the contact\'s neutral tag, the address, the level, how and since', array( has( $t20_long_new, '<ul class="wpcpm-ui-list wpcpm-ui-list--under"><li class="wpcpm-ui-list__row">' ), $t20_rows ), array( true, array(
	array( 'Anna Example', '', array( 'anna@example.org', 'Representative', 'Added by an Administrator, ' . gmdate( 'F j, Y', 1754000000 ) . '.' ), '' ),
	array( 'Bob Example', 'the institution\'s contact', array( 'bob@example.org', 'Representative', 'Added by the institutions sync, ' . gmdate( 'F j, Y', 1754100000 ) . '.' ), '' ),
	array( 'Cleo Example', '', array( 'cleo@example.org', 'Representative', 'Joined by invitation, ' . gmdate( 'F j, Y', 1754200000 ) . '.' ), '' ),
	array( 'Tess Example', '', array( 'tess@example.org', 'Tutor', 'Joined by invitation, ' . gmdate( 'F j, Y', 1754300000 ) . '.' ), '' ),
	array( T20_LONG, '', array( T20_MAIL, 'Representative', 'Added before memberships were recorded; the date was not recorded.' ), '' ),
) ) );
ck( 'the contact\'s tag keeps today\'s tooltip, the long name and address are plain words the sheet breaks (no `<wbr>`, Task 22b), the day is whole', array(
	has( $t20_long_new, '<span class="wpcpm-status" title="The program writes to this address about the institution.">the institution&#039;s contact</span>' ),
	has( $t20_long_new, '>' . T20_LONG . '<' ),
	has( $t20_long_new, '>' . T20_MAIL . '<' ) && ! has( $t20_long_new, '<wbr>' ),
	has( $t20_long_new, '<span class="wpcpm-ui-whole">' . gmdate( 'F j, Y', 1754000000 ) . '</span>' ),
), array( true, true, true, true ) );
ck( 'Leave on the reader\'s own row and Remove on every other, quiet red, the person named for a screen reader', array(
	substr_count( $t20_long_new, '<button type="submit" class="wpcpm-btn wpcpm-btn--quiet wpcpm-btn--danger" data-wpcpm-confirm="Leave ' ),
	substr_count( $t20_long_new, '<button type="submit" class="wpcpm-btn wpcpm-btn--quiet wpcpm-btn--danger" data-wpcpm-confirm="Remove ' ),
	has( $t20_long_new, '>Remove<span class="screen-reader-text"> Bob Example</span></button>' ),
), array( 1, 4 + 2, true ) );
$t20_contact = $t20_new['Administrator, a named contact with no account'];
ck( 'the named contact with no account: a row with its tag, its address as its fact, today\'s sentence as its note, and no action; the empty line after the list', array(
	t20_rows( $t20_contact ),
	has( $t20_contact, 'title="The program writes to this person about the institution."' ),
	has( $t20_contact, '</ul><p class="wpcpm-ui-list__empty">Nobody can act for this institution on this site right now.</p>' ),
	count( t20_forms( t20_between( $t20_contact, '<ul class="wpcpm-ui-list', '</ul>' ) ) ),
), array( array( array( 'Dana Example', 'the institution\'s contact', array( 'dana@example.org' ), 'Named in the program records, with no account on this site yet. An Administrator can add one so they can see this page.' ) ), true, true, 0 ) );
ck( 'a contact named by address alone is "Name not recorded", as today', t20_rows( $t20_new['Administrator, the contact named by address alone'] )[0][0], 'Name not recorded' );

// S1 (1.123.99): a contact whose address belongs to a former member of THIS school is not told they have no account.
// Dana's account was a member of D and was removed; it still exists. The note says so, in both drawings, and nothing
// else on the row or in the head moves.
const S1_GONE = 'Named in the program records. Their account is no longer among this institution\'s people. An Administrator can add them again.';
const S1_NONE = 'Named in the program records, with no account on this site yet. An Administrator can add one so they can see this page.';
$s1_removed = static function ( $address = 'dana@example.org', $school = T20_D ) {
	t20_fresh();
	$GLOBALS['users'][230] = new WP_User( 230, 'Dana Account', $address, array( 'subscriber' ) );
	WPCPM_Institution_Members::attach( 230, $school, 'manager', 1 );
	WPCPM_Institution_Members::detach( 230, WPCPM_Institution_Members::REASON_REMOVED, 1 );
	return array( 1, T20_D, true );
};
$s1_card        = t20_play( $s1_removed, true );
$s1_card_upper  = t20_play( static function () use ( $s1_removed ) { return $s1_removed( 'Dana@Example.org' ); }, true );
$s1_card_other  = t20_play( static function () use ( $s1_removed ) { return $s1_removed( 'dana@example.org', T20_B ); }, true );
$s1_admin       = t20_manager( static function () use ( $s1_removed ) { $s1_removed(); return T20_D; } );
$s1_admin_other = t20_manager( static function () use ( $s1_removed ) { $s1_removed( 'dana@example.org', T20_B ); return T20_D; } );
ck( 'S1: a contact whose address a removed member of this school holds: the row as before, the note the new sentence', t20_rows( $s1_card ), array( array( 'Dana Example', 'the institution\'s contact', array( 'dana@example.org' ), S1_GONE ) ) );
ck( 'S1: the address is matched whatever its case', t20_rows( $s1_card_upper )[0][3], S1_GONE );
ck( 'S1: a former member of another school leaves today\'s sentence', t20_rows( $s1_card_other )[0][3], S1_NONE );
ck( 'S1: still not counted in the head, the empty line still after the list, no action, the account\'s own name never printed', array(
	has( $s1_card, '<span class="wpcpm-ui-fold__phrase">0 people</span>' ),
	has( $s1_card, '</ul><p class="wpcpm-ui-list__empty">Nobody can act for this institution on this site right now.</p>' ),
	count( t20_forms( t20_between( $s1_card, '<ul class="wpcpm-ui-list', '</ul>' ) ) ),
	has( $s1_card, 'Dana Account' ),
), array( true, true, 0, false ) );
ck( 'S1: the card differs from the no-account card by the note alone', str_replace( esc_html( S1_GONE ), esc_html( S1_NONE ), $s1_card ), $t20_new['Administrator, a named contact with no account'] );
ck( 'S1: on the Institutions screen\'s Accounts tab the contact\'s row says the same, and a former member of another school does not', array(
	has( $s1_admin, '<span class="wpcpm-people__facts wpcpm-people__status">' . esc_html( S1_GONE ) . '</span>' ),
	has( $s1_admin, esc_html( S1_NONE ) ),
	has( $s1_admin_other, '<span class="wpcpm-people__facts wpcpm-people__status">' . esc_html( S1_NONE ) . '</span>' ),
), array( true, false, true ) );
// Fix round F1: the full sentence is for a reader who may change the list or manages the program; any other reads the
// first sentence alone. F2: a former member now live at another school cannot be added again, so every reader reads
// the first sentence alone. Both drawings.
const S1_SHORT = 'Named in the program records.';
$s1_readers = static function ( $viewer, $manage, $elsewhere = false ) use ( $s1_removed ) {
	return static function () use ( $s1_removed, $viewer, $manage, $elsewhere ) {
		$s1_removed();
		$GLOBALS['users'][240] = new WP_User( 240, 'Rita Example', 'rita@example.org', array( 'subscriber' ) );
		$GLOBALS['users'][241] = new WP_User( 241, 'Theo Example', 'theo@example.org', array( 'subscriber' ) );
		WPCPM_Institution_Members::attach( 240, T20_D, 'manager', 1 );
		WPCPM_Institution_Members::attach( 241, T20_D, 'invited', 240, 0, 'tutor' );
		if ( $elsewhere ) {
			WPCPM_Institution_Members::attach( 230, T20_B, 'manager', 1 );
		}
		return array( $viewer, T20_D, $manage );
	};
};
$s1_note = static function ( $html ) {
	foreach ( t20_rows( $html ) as $row ) {
		if ( 'Dana Example' === $row[0] ) {
			return $row[3];
		}
	}
	return null;
};
ck( 'F1: an Administrator and a representative read the full sentence, a tutor the first sentence alone', array(
	'Administrator'  => $s1_note( t20_play( $s1_readers( 1, true ), true ) ),
	'representative' => $s1_note( t20_play( $s1_readers( 240, false ), true ) ),
	'tutor'          => $s1_note( t20_play( $s1_readers( 241, false ), true ) ),
), array( 'Administrator' => S1_GONE, 'representative' => S1_GONE, 'tutor' => S1_SHORT ) );
ck( 'F2: the account now live at another school: every reader reads the first sentence alone', array(
	'Administrator'  => $s1_note( t20_play( $s1_readers( 1, true, true ), true ) ),
	'representative' => $s1_note( t20_play( $s1_readers( 240, false, true ), true ) ),
	'tutor'          => $s1_note( t20_play( $s1_readers( 241, false, true ), true ) ),
), array( 'Administrator' => S1_SHORT, 'representative' => S1_SHORT, 'tutor' => S1_SHORT ) );
$s1_admin_live = t20_manager( static function () use ( $s1_readers ) { $s1_readers( 1, true, true )(); return T20_D; } );
ck( 'F2: and on the Accounts tab the same', has( $s1_admin_live, '<span class="wpcpm-people__facts wpcpm-people__status">' . esc_html( S1_SHORT ) . '</span>' ), true );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Representatives and tutors: Administrator, the contact\'s account removed from this school (S1)', str_replace( '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id=', '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id=', $s1_card ) );
}

// ---- 5. Invitations waiting and Invite a colleague ----

$t20_invites = t20_between( $t20_long_new, '<h4 class="wpcpm-ui-h">Invitations waiting</h4>', '<details' );
ck( 'Invitations waiting: an in-card heading, then a row per invitation with who sent it, when and the day it stops working', array_map( static function ( $row ) { return array( $row[0], preg_replace( '/on .*?, good until .*?\./', 'on {day}, good until {day}.', $row[2][0] ) ); }, t20_rows( $t20_invites ) ), array(
	array( 'third@example.org', 'Invited by a colleague on {day}, good until {day}.' ),
	array( 'averyveryveryverylongmailboxname5678@example.org', 'Invited by ' . T20_LONG . ' on {day}, good until {day}.' ),
	array( 'colleague@example.org', 'Invited by Anna Example on {day}, good until {day}.' ),
) );
ck( 'each with Resend (quiet) and Cancel invitation (quiet red), the address named for a screen reader, both in the row\'s action cell', array(
	substr_count( $t20_invites, '<button type="submit" class="wpcpm-btn wpcpm-btn--quiet">Resend<span class="screen-reader-text"> ' ),
	substr_count( $t20_invites, '<button type="submit" class="wpcpm-btn wpcpm-btn--quiet wpcpm-btn--danger" data-wpcpm-confirm="Cancel the invitation to ' ),
	substr_count( $t20_invites, '>Cancel invitation<span class="screen-reader-text"> ' ),
	substr_count( $t20_invites, '<div class="wpcpm-ui-list__act"><form method="post" action="https://example.test/wp-admin/admin-post.php">' ),
	1 === preg_match( '#<span class="wpcpm-ui-whole">[^<]+</span>, good until <span class="wpcpm-ui-whole">#', $t20_invites ),
), array( 3, 3, 3, 3, true ) );
$t20_fold = t20_between( $t20_long_new, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id="wpcpm-people-invite">', '</details>' );
ck( 'Invite a colleague: the parts\' inner fold, closed, its paragraph the form\'s intro, Email address Required with no placeholder, Level a choice group with each option\'s hint, Send invitation the one primary', array(
	'' !== $t20_fold,
	has( $t20_fold, '<span class="wpcpm-ui-fold__title">Invite a colleague</span>' ),
	has( $t20_fold, '<p class="wpcpm-ui-intro">They get one email with a link that is good for 14 days. Whoever accepts it sees the same students you do, and a representative can remove anybody, including you. Up to 10 invitations can be waiting at a time.</p>' ),
	has( $t20_fold, '<label class="wpcpm-ui-field__label" for="wpcpm-invite-email-' . T20_A . '">Email address <span class="wpcpm-ui-field__need">Required</span></label><input type="email" class="wpcpm-ui-field__control" id="wpcpm-invite-email-' . T20_A . '" name="email" required value="">' ),
	has( $t20_fold, 'placeholder' ),
	has( $t20_fold, '<legend class="wpcpm-ui-choices__legend">Level</legend>' ),
	has( $t20_fold, 'id="wpcpm-invite-level-' . T20_A . '-representative" name="level" value="representative" checked aria-labelledby=' ),
	has( $t20_fold, '<span class="wpcpm-ui-choice__hint" id="wpcpm-invite-level-' . T20_A . '-tutor-hint">Sees the students, their Student Report Cards and the semester reports, and changes nothing.</span>' ),
	has( $t20_fold, '<span class="wpcpm-ui-choice__hint" id="wpcpm-invite-level-' . T20_A . '-representative-hint">Adds and changes students, invites colleagues, corrects the details.</span>' ),
	substr_count( $t20_fold, '<button type="submit" class="wpcpm-btn">Send invitation</button>' ),
), array( true, true, true, true, false, true, true, true, true, 1 ) );
$t20_bad_mail = $t20_new['representative, the invitation refused: not an address'];
ck( 'a refused invitation: the fold drawn open, the typing kept, the problem under Email address and tied to it, the level as it was chosen, and the outcome said once as the shell says it, a link to the box', array(
	has( $t20_bad_mail, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="wpcpm-people-invite">' ),
	has( $t20_bad_mail, 'name="email" required aria-invalid="true" aria-describedby="wpcpm-invite-email-' . T20_A . '-problem" value="anna at example">' ),
	has( $t20_bad_mail, 'id="wpcpm-invite-email-' . T20_A . '-problem">Nothing was sent. That is not an address an invitation can go to.</' ),
	has( $t20_bad_mail, 'value="tutor" checked' ),
	substr_count( $t20_bad_mail, 'data-wpcpm-card-message><a href="#wpcpm-invite-email-' . T20_A . '">Nothing was sent. That is not an address an invitation can go to.</a></p>' ),
), array( true, true, true, true, 1 ) );
ck( 'already a member and already waiting are said under Email address too; a level nobody knows under Level; the day\'s sends used up is the outcome alone, the fold open with the typing kept', array(
	has( $t20_new['representative, the invitation refused: already a member'], 'id="wpcpm-invite-email-' . T20_A . '-problem">Nothing was sent. That address already has access to this institution and is on the list above. cleo@example.org</' ),
	has( $t20_new['representative, the invitation refused: already waiting'], 'id="wpcpm-invite-email-' . T20_A . '-problem">Nothing was sent. That address already has an invitation waiting. Resend it if it has not arrived. third@example.org</' ),
	has( $t20_new['representative, the invitation refused: a level nobody knows'], 'id="wpcpm-invite-level-' . T20_A . '-problem">Nothing was sent. Choose Representative or Tutor.</' ),
	has( $t20_new['representative, the invitation refused: the day\'s sends used up'], '-problem"' ),
	has( $t20_new['representative, the invitation refused: the day\'s sends used up'], 'value="' . T20_MAIL . '"' ) && has( $t20_new['representative, the invitation refused: the day\'s sends used up'], 'open id="wpcpm-people-invite"' ),
), array( true, true, true, false, true ) );

// ---- 6. Tutors ----

$t20_tutors = t20_between( $t20_long_new, '<h4 class="wpcpm-ui-h" id="wpcpm-tutors">Tutors</h4>' );
ck( 'Tutors: its heading on today\'s anchor, a row per tutor (name, address), Remove quiet red, the tutor named for a screen reader', array(
	array_map( static function ( $row ) { return array( $row[0], $row[2] ); }, t20_rows( $t20_tutors ) ),
	substr_count( $t20_tutors, '<button type="submit" class="wpcpm-btn wpcpm-btn--quiet wpcpm-btn--danger" data-wpcpm-confirm="Remove ' ),
	has( $t20_tutors, '>Remove<span class="screen-reader-text"> Tom Example</span></button>' ),
), array( array( array( T20_LONG, array( 'averyveryveryverylongmailboxname9012@example.org' ) ), array( 'Tom Example', array( 'tom@example.org' ) ) ), 2, true ) );
$t20_add = t20_between( $t20_tutors, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id="wpcpm-tutors-add">', '</details>' );
ck( 'Add a tutor: an inner fold, closed, Name and Email Required (Name at most 100 characters), Add the one primary', array(
	'' !== $t20_add,
	has( $t20_add, '<span class="wpcpm-ui-fold__title">Add a tutor</span>' ),
	has( $t20_add, '<label class="wpcpm-ui-field__label" for="wpcpm-tutor-name-' . T20_A . '">Name <span class="wpcpm-ui-field__need">Required</span></label><input type="text" class="wpcpm-ui-field__control" id="wpcpm-tutor-name-' . T20_A . '" name="name" required maxlength="100" value="">' ),
	has( $t20_add, '<label class="wpcpm-ui-field__label" for="wpcpm-tutor-email-' . T20_A . '">Email <span class="wpcpm-ui-field__need">Required</span></label><input type="email" class="wpcpm-ui-field__control" id="wpcpm-tutor-email-' . T20_A . '" name="email" required value="">' ),
	substr_count( $t20_add, '<button type="submit" class="wpcpm-btn">Add</button>' ),
), array( true, true, true, true, 1 ) );
ck( 'an empty tutor list says so in today\'s words, of the empty kind', has( $t20_new['representative, no tutor listed'], '<h4 class="wpcpm-ui-h" id="wpcpm-tutors">Tutors</h4><p class="wpcpm-ui-list__empty">No tutors are listed yet.</p>' ), true );
ck( 'a refused tutor: the fold drawn open, the problem under Name or Email and tied to it; a refusal of no field opens the fold with the outcome alone (the handler keeps no typing today, so the boxes are empty)', array(
	has( $t20_new['representative, the tutor refused: no name'], 'open id="wpcpm-tutors-add"' ) && has( $t20_new['representative, the tutor refused: no name'], 'id="wpcpm-tutor-name-' . T20_A . '-problem">Give the tutor&#039;s name, up to 100 characters.</' ),
	has( $t20_new['representative, the tutor refused: no address'], 'open id="wpcpm-tutors-add"' ) && has( $t20_new['representative, the tutor refused: no address'], 'id="wpcpm-tutor-email-' . T20_A . '-problem">Give an email address for the tutor.</' ),
	has( $t20_new['representative, the tutor refused: not kept'], 'open id="wpcpm-tutors-add"' ) && ! has( $t20_new['representative, the tutor refused: not kept'], '-problem"' ),
	has( $t20_new['message, tutors: busy'], 'open id="wpcpm-tutors-add"' ),
), array( true, true, true, false ) );

// ---- 7. The Administrator's link, the read line ----

$t20_admin = $t20_new['Administrator, five people, three invitations'];
ck( 'for an Administrator, "Add or re-add an account on the Institutions screen" as a quiet link in a line of its own (the student card\'s export link\'s way), to the school\'s Manage members, after Tutors; nobody else is drawn it', array(
	has( $t20_admin, '<p class="wpcpm-ui-intro"><a href="https://example.test/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts&wpcpm_institution=' . T20_A . '">Add or re-add an account on the Institutions screen</a></p>' ),
	before( $t20_admin, 'id="wpcpm-tutors"', 'Add or re-add an account' ),
	has( $t20_long_new, 'Add or re-add an account' ),
	has( $t20_tutor_new, 'Add or re-add an account' ),
), array( true, true, false, false ) );
ck( 'the read line ends the body, muted, its moment whole; before the first read, today\'s other sentence', array(
	has( $t20_long_new, '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">The institution&#039;s details were read on <span class="wpcpm-ui-whole">' . gmdate( 'F j, Y g:i a', 1756000000 ) . '</span> (4 hours ago). The people above are as they are right now.</p></div></details></section>' ),
	has( $t20_new['representative, nothing read yet'], '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">The institution&#039;s details have not been read from the program records yet. The people above are as they are right now.</p></div></details></section>' ),
), array( true, true ) );

// ---- 8. Every outcome through the shell, in the section it belongs to today (rule 6, ruling 4) ----

$t20_misplaced = array();
foreach ( $t20_new as $t20_name => $t20_html ) {
	if ( 0 !== strpos( $t20_name, 'message, ' ) ) {
		continue;
	}
	$t20_count = substr_count( $t20_html, 'data-wpcpm-card-message' );
	$t20_at    = strpos( $t20_html, 'data-wpcpm-card-message' );
	$t20_where = false === $t20_at ? 'none' : ( $t20_at < strpos( $t20_html, '<ul class="wpcpm-ui-list' ) ? 'people' : ( $t20_at < strpos( $t20_html, 'id="wpcpm-tutors"' ) ? 'invitations' : 'tutors' ) );
	$t20_want  = 0 === strpos( $t20_name, 'message, people' ) ? 'people' : ( 0 === strpos( $t20_name, 'message, invitations' ) ? 'invitations' : 'tutors' );
	if ( 1 !== $t20_count || $t20_where !== $t20_want || has( $t20_html, '<p class="wpcpm-people__message is-' ) ) {
		$t20_misplaced[] = $t20_name . ' (' . $t20_count . ', ' . $t20_where . ')';
	}
}
ck( 'each outcome of the three classes is said once, by the shell\'s message, in the section today\'s says it in (the people\'s under the intro, the invitations\' after the list, the tutors\' under Tutors)', $t20_misplaced, array() );
ck( 'its tone the class\'s own: a removal is a success heard politely, a refusal an alert, a repeat information', array(
	has( $t20_new['message, people: removed'], '<p class="wpcpm-people__message wpcpm-people__message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>That person no longer has access to this institution. Their account is kept.</p>' ),
	has( $t20_new['message, invitations: invite-full'], '<p class="wpcpm-people__message wpcpm-people__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>Nothing was sent. This institution already has 10 invitations waiting.' ),
	has( $t20_new['message, tutors: already'], '<p class="wpcpm-people__message wpcpm-people__message--info is-info" role="status" tabindex="-1" data-wpcpm-card-message>That address is already on the list.</p>' ),
	has( $t20_new['message, invitations: invite-sent'], 'and you can cancel or resend it from the list above. colleague@example.org</p>' ),
), array( true, true, true, true ) );
ck( 'an address already in use names the account in its sentence: linked to its profile for an Administrator, its name alone for anybody else, as today', array(
	has( $t20_new['Administrator, an account already using the address'], 'data-wpcpm-card-message>An account already uses that address: <a href="https://example.test/wp-admin/user-edit.php?user_id=201">Anna Example</a>. Nothing was created.</p>' ),
	has( $t20_new['message, people: conflict'], 'data-wpcpm-card-message>An account already uses that address: Anna Example. Nothing was created.</p>' ),
), array( true, true ) );

// ---- 9. Hostile words (rule 10) ----

$t20_hostile_bad = array();
foreach ( array( 'hostile, a representative', 'hostile, an Administrator', 'hostile, the invitation refused with its typing' ) as $t20_name ) {
	foreach ( array( 'parts' => $t20_new[ $t20_name ] ) as $t20_branch => $t20_html ) {
		if ( has( $t20_html, '<script' ) || has( $t20_html, '&amp;amp;' ) || has( $t20_html, '&amp;lt;' ) || has( $t20_html, '&amp;quot;' ) || has( $t20_html, '&amp;#039;' ) ) {
			$t20_hostile_bad[] = $t20_name . ' (' . $t20_branch . ')';
		}
	}
}
ck( 'a tag, a double quote and an ampersand in every name, address and tutor print escaped once: no raw tag, nothing escaped twice', $t20_hostile_bad, array() );
ck( 'and every form still parses as the old card\'s forms, so no attribute broke', md5( serialize( t20_forms( t20_mask( $t20_new['hostile, an Administrator'] ) ) ) ), $t20_old_pins['forms']['hostile, an Administrator'] );
ck( 'the hostile values are read back whole on the parts', array(
	has( t20_plain( $t20_new['hostile, a representative'] ), 'Anna ' . T20_BAD ),
	has( t20_plain( $t20_new['hostile, a representative'] ), 'Tutor ' . T20_BAD ),
	has( t20_plain( $t20_new['hostile, a representative'] ), 'i"&<w>@example.org' ),
	has( t20_plain( $t20_new['hostile, a representative'] ), 'Kontakt ' ) || has( t20_plain( $t20_new['hostile, a representative'] ), 'Schule ' . T20_BAD ),
), array( true, true, true, true ) );

// ---- 10. One helper for a rule both branches need (rule 4) ----

$t20_src = array(
	'people'  => (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-people.php' ),
	'invite'  => (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-invite.php' ),
	'tutors'  => (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-tutors.php' ),
);
// The tutors' list asks no switch of its own: it is drawn inside the People card, which asks the surface (its own
// `parts()`, read by nothing in production, went in Task 23, fix round 1).
ck( 'one parts() per class, the Institution Dashboard\'s surface on the shared parts; the invitations\' asks the People card\'s, and the tutors\' list, drawn inside the People card, asks none', array(
	has( (string) method_body( $t20_src['people'], 'parts' ), "WPCPM_UI::on() && 'institution' === WPCPM_UI::surface()" ),
	has( (string) method_body( $t20_src['invite'], 'parts' ), 'WPCPM_Institution_People::parts()' ),
	method_exists( 'WPCPM_Institution_Tutors', 'parts' ),
), array( true, true, false ) );
ck( 'the policy is asked before anything is drawn, once a decision, and the data read before it too', array(
	before( (string) method_body( $t20_src['people'], 'render' ), 'ACT_MANAGE_MEMBERS', 'self::render_parts(' ),
	before( (string) method_body( $t20_src['people'], 'render' ), 'self::contact_person(', 'self::render_parts(' ),
	before( (string) method_body( $t20_src['invite'], 'render_form' ), 'WPCPM_Institution_Policy::decide(', 'self::render_form_parts(' ),
	before( (string) method_body( $t20_src['invite'], 'render_pending' ), 'self::pending_for(', 'WPCPM_UI::list_open(' ),
	before( t20_between( $t20_src['tutors'], 'public static function render(', 'private static function render_parts(' ), 'self::list_for(', 'self::render_parts(' ),
), array( true, true, true, true, true ) );
// The rules the card's rows share with Manage members' (`render_member()`, which keeps today's markup there) are still
// one helper both call: the hidden fields, the questions, the facts.
ck( 'the rules the card reads are one helper each (an outcome and the kept typing read once, before the form): the intro, the count, the outcome, the hidden fields, the questions, the facts, the level options', array(
	substr_count( $t20_src['people'], 'self::intro_sentence(' ),
	substr_count( $t20_src['people'], 'self::head_count(' ),
	substr_count( $t20_src['people'], 'self::message_for(' ) . ( has( (string) method_body( $t20_src['people'], 'render_parts' ), 'self::render_message( $record_id )' ) ? ' and drawn by render_message() on both' : '' ),
	substr_count( $t20_src['people'], 'self::remove_fields(' ),
	substr_count( $t20_src['people'], 'self::confirm_text(' ),
	substr_count( $t20_src['people'], 'self::facts_parts(' ),
	substr_count( $t20_src['invite'], 'self::message_for(' ) >= 2,
	substr_count( $t20_src['invite'], 'self::form_fields(' ),
	substr_count( $t20_src['invite'], 'self::control_fields(' ),
	// The form's two drawings, and the level refusal's lead to the group's first option (fix round 1).
	substr_count( $t20_src['invite'], 'self::level_options(' ),
	substr_count( $t20_src['invite'], 'self::kept_form(' ) . ( before( (string) method_body( $t20_src['invite'], 'render_form' ), 'self::kept_form(', 'self::render_form_parts(' ) ? ', before the form' : '' ),
	substr_count( $t20_src['tutors'], 'self::message_for(' ) >= 2,
	substr_count( $t20_src['tutors'], 'self::remove_fields(' ),
	substr_count( $t20_src['tutors'], 'self::add_fields(' ),
// 1.123.98, A9: the count is one helper the card and Manage members both call, two calls.
), array( 1, 2, '1 and drawn by render_message() on both', 2, 2, 2, true, 1, 1, 2, '1, before the form', true, 1, 1 ) );

// ---- 11. The labels the Enroll students hint names (ruling 7) ----

$t20_dashboard_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php' );
// ---- Fix round 1 ----

echo "\n=== Task 20, fix round 1 ===\n";

// 1. The shell's outcome takes no markup: a name and an optional address, which it escapes itself and puts at the sentence's
// one marked spot; with neither it prints the base's bytes.
ck( 'message() with no name prints what the base printed, a sentence holding a percent sign among them (md5 from 6b0a7fdc)', array(
	md5( WPCPM_Dashboard_Card::message( 'warning', 'Over 50% of the seats are taken: %s left.' ) ),
	md5( WPCPM_Dashboard_Card::message( 'error', 'An account already uses that address: %s. Nothing was created.', 'wpcpm-people__message' ) ),
	md5( WPCPM_Dashboard_Card::message( 'info', 'Saved for 100% of the rows.', 'wpcpm-dashboard__message', 'wpcpm-note-3' ) ),
), array( '61d39b2ed60ae093688998c6204eac3a', 'a1f7965b8a68038609a30af677ecbd86', 'ce5ba49a318c594cc3391eba3568d010' ) );
$t20f_signature = new ReflectionMethod( 'WPCPM_Dashboard_Card', 'message' );
ck( 'its fifth argument is a name, not markup', array_map( static function ( $p ) { return $p->getName(); }, $t20f_signature->getParameters() ), array( 'tone', 'text', 'base', 'lead', 'named' ) );
$t20f_sentence = 'An account already uses that address: %s. Nothing was created.';
// The address goes through esc_url() (core's escapes an attribute; this suite's stand-in hands it back as it is).
ck( 'a name and an address: the renderer escapes both and links the name at the marked spot; a name alone prints plain', array(
	WPCPM_Dashboard_Card::message( 'error', $t20f_sentence, 'wpcpm-people__message', '', array( 'words' => 'Anna ' . T20_BAD, 'url' => ' https://example.test/wp-admin/user-edit.php?user_id=201 ' ) ),
	WPCPM_Dashboard_Card::message( 'error', $t20f_sentence, 'wpcpm-people__message', '', array( 'words' => 'Anna ' . T20_BAD ) ),
), array(
	'<p class="wpcpm-people__message wpcpm-people__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>An account already uses that address: <a href="https://example.test/wp-admin/user-edit.php?user_id=201">Anna &lt;script&gt;alert(1)&lt;/script&gt; &quot;q&quot; &amp; Co</a>. Nothing was created.</p>',
	'<p class="wpcpm-people__message wpcpm-people__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>An account already uses that address: Anna &lt;script&gt;alert(1)&lt;/script&gt; &quot;q&quot; &amp; Co. Nothing was created.</p>',
) );
ck( 'an address that is not http or https prints no link; a stray percent sign neither breaks the sentence nor takes the name; the sentence\'s own markup is escaped', array(
	has( WPCPM_Dashboard_Card::message( 'error', $t20f_sentence, 'x', '', array( 'words' => 'Anna', 'url' => 'javascript:alert(1)' ) ), '<a' ),
	has( WPCPM_Dashboard_Card::message( 'error', $t20f_sentence, 'x', '', array( 'words' => 'Anna', 'url' => 'data:text/html,x' ) ), '<a' ),
	WPCPM_Dashboard_Card::message( 'info', '100% <b>done</b>: %s, then %d and %s again.', 'x', '', array( 'words' => 'Anna' ) ),
	WPCPM_Dashboard_Card::message( 'info', 'Nothing marked, 50% only.', 'x', '', array( 'words' => 'Anna' ) ),
), array(
	false,
	false,
	'<p class="x x--info is-info" role="status" tabindex="-1" data-wpcpm-card-message>100% &lt;b&gt;done&lt;/b&gt;: Anna, then %d and %s again.</p>',
	'<p class="x x--info is-info" role="status" tabindex="-1" data-wpcpm-card-message>Nothing marked, 50% only.</p>',
) );
ck( 'the card passes raw values: no markup is built for the outcome in the People class', array(
	has( (string) method_body( $t20_src['people'] = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-people.php' ), 'render_message' ), 'self::account_parts(' ),
	has( (string) method_body( $t20_src['people'], 'render_message' ), 'self::account_named(' ),
), array( true, true ) );
ck( 'the conflict reads today\'s words to an Administrator and a representative', array(
	has( $t20_new['Administrator, an account already using the address'], 'An account already uses that address: <a href="https://example.test/wp-admin/user-edit.php?user_id=201">Anna Example</a>. Nothing was created.' ),
	has( $t20_new['message, people: conflict'], 'An account already uses that address: Anna Example. Nothing was created.' ),
), array( true, true ) );

// 2. Every outcome that leads to a box leads to an element the drawn page holds.
$t20f_dead = array();
foreach ( $t20_new as $t20_name => $t20_html ) {
	if ( preg_match_all( '#data-wpcpm-card-message><a href="\#([^"]+)">#', $t20_html, $t20f_m ) ) {
		foreach ( $t20f_m[1] as $t20f_id ) {
			if ( 1 !== substr_count( $t20_html, 'id="' . $t20f_id . '"' ) ) {
				$t20f_dead[] = $t20_name . ': #' . $t20f_id;
			}
		}
	}
}
ck( 'every outcome that is a link to a box names an id exactly one element holds', $t20f_dead, array() );
ck( 'a level nobody knows leads to the group\'s first option, its id from the one builder choices() uses', array(
	has( $t20_new['representative, the invitation refused: a level nobody knows'], '<a href="#wpcpm-invite-level-' . T20_A . '-representative">' ),
	WPCPM_UI::choice_id( 'wpcpm-invite-level-' . T20_A, 'representative' ),
	has( (string) method_body( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php' ), 'choices' ), 'self::choice_id(' ),
), array( true, 'wpcpm-invite-level-' . T20_A . '-representative', true ) );

// 3. One helper per rule: Leave or Remove, and the three conditions decided once before the branch.
$t20f_render = (string) method_body( $t20_src['people'], 'render' );
ck( 'Leave or Remove is one is_self() both rows call (the card\'s and Manage members\'); the list, the empty line and the link are decided once, before the card is drawn', array(
	substr_count( $t20_src['people'], 'self::is_self(' ),
	substr_count( $t20_src['people'], '=== (int) $member->ID' ),
	before( $t20f_render, '$shows = self::shows(', 'self::render_parts(' ),
	substr_count( $t20_src['people'], 'self::shows(' ),
	// shows() and Manage members' own render_manager(), which keeps today's markup.
	substr_count( $t20_src['people'], 'empty( $members )' ),
	substr_count( $t20_src['people'], '$members || $contact_shown' ),
	substr_count( $t20_src['people'], "\$shows['list']" ),
	substr_count( $t20_src['people'], "\$shows['empty']" ),
	substr_count( $t20_src['people'], "\$shows['link']" ),
), array( 2, 1, true, 1, 2, 2, 1, 1, 1 ) );

// 4. The parts' forms pass the caller's origin on, as the old branch does.
$t20f_member = new ReflectionMethod( 'WPCPM_Institution_People', 'remove_form_parts' );
if ( PHP_VERSION_ID < 80100 ) {
	$t20f_member->setAccessible( true );
}
t20_fresh();
$GLOBALS['uid'] = 201;
ck( 'a Remove drawn for a caller with an origin carries wpcpm_from on the parts, and the card\'s own forms carry none', array(
	has( (string) $t20f_member->invoke( null, $GLOBALS['users'][202], array( $GLOBALS['users'][201], $GLOBALS['users'][202] ), 'Universidad Example', false, false, WPCPM_Institution_People::RETURN_ADMIN ), '<input type="hidden" name="wpcpm_from" value="admin" />' ),
	has( (string) $t20f_member->invoke( null, $GLOBALS['users'][202], array( $GLOBALS['users'][201], $GLOBALS['users'][202] ), 'Universidad Example', false, false, '' ), 'wpcpm_from' ),
	count( preg_grep( '/^wpcpm_from=/', array_merge( ...array_column( $t20_forms_long, 'fields' ) ) ) ),
), array( true, false, 0 ) );

// ---- Follow-up: a refused Add a tutor keeps its typing on the parts ----

$t20k_state = static function () {
	t20_fresh();
	$GLOBALS['uid'] = 201;
	WPCPM_Flash::keep( 'tutor-add:' . T20_A, array( 'name' => T20_LONG, 'email' => T20_MAIL ) );
	t20_flash( 201, WPCPM_Institution_Tutors::FLASH, array( 'status' => 'error', 'record' => T20_A ) );
	return array( 201, T20_A, false );
};
$t20k_new = t20_play( $t20k_state, true );
ck( 'and the parts\' Add a tutor comes back open with the 40-character name and the 48-character address', array(
	has( $t20k_new, 'open id="wpcpm-tutors-add"' ),
	has( $t20k_new, 'name="name" required maxlength="100" value="' . T20_LONG . '">' ),
	has( $t20k_new, 'name="email" required value="' . T20_MAIL . '">' ),
), array( true, true, true ) );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Representatives and tutors: the tutor refused, its typing kept, long words', $t20k_new );
}

ck( '"Your institution" is the view\'s label in the menu, and "Tutors" the section\'s title on the parts', array(
	has( (string) method_body( $t20_dashboard_src, 'frame_args' ), "self::VIEW_INSTITUTION    => array( __( 'Your institution', 'wpcredits-program-manager' ), 'building' )" ),
	has( $t20_long_new, '>Tutors</h4>' ),
), array( true, true ) );

/* ---- the layout harness ---- */

if ( function_exists( 'wpcpm_layout_keep' ) ) {
	foreach ( array(
		'representative, five people, three invitations, two tutors, long words',
		'Administrator, five people, three invitations',
		'Administrator, a named contact with no account',
		'representative, no tutor listed',
		'representative, the invitation refused: not an address',
		'representative, the tutor refused: no name',
		'representative, the tutor refused: no address',
		'tutor',
		'representative, nothing read yet',
		'Administrator, an account already using the address',
		'message, people: removed',
		'message, invitations: invite-sent',
		'message, invitations: invite-full',
		'message, tutors: added',
		'hostile, an Administrator',
	) as $t20_name ) {
		// Every fold open: Invite a colleague and Add a tutor.
		wpcpm_layout_keep( 'Representatives and tutors: ' . $t20_name, str_replace( '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id=', '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id=', $t20_new[ $t20_name ] ) );
	}
}

// ---- Gate B, B7: an outcome sits at the form or the rows it is about ----

echo "\n=== Gate B, B7: an outcome at its form or its rows ===\n";

// The invite form's outcomes (a send, a refusal of the address or the level, the limits) are said inside Invite a colleague,
// its first block, and the fold is drawn open for them; a waiting invitation's (resent, canceled, gone) under the heading of
// the invitations waiting, over their rows; with no row left, where the invitations' outcome was said before (after the
// people). The page's own (joined) and one with no subject (error) stay there too.
$b7_states = array(
	'refused, not an address' => static function () { t20_fresh(); t20_refuse_invite( 201, 'invite-bad-email', 'anna at example', 'tutor' ); return array( 201, T20_A, false ); },
	'sent'                    => static function () { t20_fresh(); t20_invite( 905, 'colleague@example.org', 201, time() - 60 ); t20_flash( 201, WPCPM_Institution_Invite::FLASH, array( 'status' => 'invite-sent', 'detail' => 'colleague@example.org', 'record' => T20_A ) ); return array( 201, T20_A, false ); },
	'full, with rows'         => static function () { t20_fresh(); t20_long(); t20_flash( 201, WPCPM_Institution_Invite::FLASH, array( 'status' => 'invite-full', 'record' => T20_A ) ); return array( 201, T20_A, false ); },
	'resent, with rows'       => static function () { t20_fresh(); t20_long(); t20_flash( 201, WPCPM_Institution_Invite::FLASH, array( 'status' => 'invite-resent', 'detail' => 'colleague@example.org', 'record' => T20_A ) ); return array( 201, T20_A, false ); },
	'canceled, no row left'   => static function () { t20_fresh(); t20_flash( 201, WPCPM_Institution_Invite::FLASH, array( 'status' => 'invite-cancelled', 'detail' => 'colleague@example.org', 'record' => T20_A ) ); return array( 201, T20_A, false ); },
	// Task 28, W2: Resend's refusals are a row's outcome, decided by the press that left them.
	'Resend refused, the day\'s sends spent' => static function () { t20_fresh(); t20_long(); t20_flash( 201, WPCPM_Institution_Invite::FLASH, array( 'status' => 'invite-ceiling', 'from' => 'row', 'record' => T20_A ) ); return array( 201, T20_A, false ); },
	'Resend refused, the mail not handed over' => static function () { t20_fresh(); t20_long(); t20_flash( 201, WPCPM_Institution_Invite::FLASH, array( 'status' => 'invite-unsent', 'from' => 'row', 'record' => T20_A ) ); return array( 201, T20_A, false ); },
	'the form refused, the day\'s sends spent' => static function () { t20_fresh(); t20_long(); t20_flash( 201, WPCPM_Institution_Invite::FLASH, array( 'status' => 'invite-ceiling', 'record' => T20_A ) ); return array( 201, T20_A, false ); },
);
$b7 = array();
foreach ( $b7_states as $b7_name => $b7_state ) {
	$b7_html = t20_play( $b7_state, true );
	$b7_at   = strpos( $b7_html, 'data-wpcpm-card-message' );
	$b7_fold = strpos( $b7_html, 'id="wpcpm-people-invite"' );
	$b7_form = false === $b7_fold ? false : strpos( $b7_html, '<form', $b7_fold );
	$b7_wait = strpos( $b7_html, 'Invitations waiting' );
	$b7_row  = false === $b7_wait ? false : strpos( $b7_html, '<li', $b7_wait );
	$b7_fold_open = 1 === preg_match( '/<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="wpcpm-people-invite"/', $b7_html );
	$b7_intro = false === $b7_form ? false : strpos( $b7_html, '<p class="wpcpm-ui-intro">They get one email', $b7_form );
	$b7_field = false === $b7_form ? false : strpos( $b7_html, '<div class="wpcpm-ui-field', $b7_form );
	if ( false === $b7_at ) {
		$b7[ $b7_name ] = 'none';
	} elseif ( false !== $b7_intro && false !== $b7_field && $b7_at > $b7_intro && $b7_at < $b7_field ) {
		// S4 (1.123.99): the intro sentence, then the outcome, then the form's first field.
		$b7[ $b7_name ] = 'in the invite fold, after its intro, over its first field' . ( $b7_fold_open ? ', open' : ', closed' );
	} elseif ( false !== $b7_fold && $b7_at > $b7_fold && false !== $b7_form && $b7_at < $b7_form ) {
		$b7[ $b7_name ] = 'in the invite fold, before its form' . ( $b7_fold_open ? ', open' : ', closed' );
	} elseif ( false !== $b7_wait && $b7_at > $b7_wait && false !== $b7_row && $b7_at < $b7_row ) {
		$b7[ $b7_name ] = 'under Invitations waiting, over the rows';
	} elseif ( $b7_at > strpos( $b7_html, '</ul>' ) && ( false === $b7_fold || $b7_at < $b7_fold ) ) {
		$b7[ $b7_name ] = 'after the people';
	} else {
		$b7[ $b7_name ] = 'elsewhere';
	}
	$b7[ $b7_name ] .= ' (' . substr_count( $b7_html, 'data-wpcpm-card-message' ) . ')';
}
ck( 'each invitation outcome is said once, at the form or the rows it is about', $b7, array(
	'refused, not an address' => 'in the invite fold, after its intro, over its first field, open (1)',
	'sent'                    => 'in the invite fold, after its intro, over its first field, open (1)',
	'full, with rows'         => 'in the invite fold, after its intro, over its first field, open (1)',
	'resent, with rows'       => 'under Invitations waiting, over the rows (1)',
	'canceled, no row left'   => 'after the people (1)',
	'Resend refused, the day\'s sends spent' => 'under Invitations waiting, over the rows (1)',
	'Resend refused, the mail not handed over' => 'under Invitations waiting, over the rows (1)',
	'the form refused, the day\'s sends spent' => 'in the invite fold, after its intro, over its first field, open (1)',
) );

// S4 (1.123.99): the outcome stands directly over the form it answers: the fold's intro sentence, the message right after
// it, then the first field; the focus target and the place a refusal lands (the fold's id) as they were.
$s4_refused = t20_play( $b7_states['refused, not an address'], true );
$s4_sent    = t20_play( $b7_states['sent'], true );
$s4_order   = static function ( $html ) {
	$fold = (string) t20_between( $html, 'id="wpcpm-people-invite"', '</details>' );
	return 1 === preg_match( '#<p class="wpcpm-ui-intro">They get one email[^<]*</p><p class="wpcpm-people__message[^"]*"[^>]*data-wpcpm-card-message[^>]*>.*?</p><div class="wpcpm-ui-field#s', $fold );
};
ck( 'S4: in Invite a colleague the intro, then the message, then the first field, for a success and for a refusal', array( $s4_order( $s4_sent ), $s4_order( $s4_refused ) ), array( true, true ) );
ck( 'S4: the refusal still leads to the box it refused, and its message is still the one block focus goes to', array(
	has( $s4_refused, 'data-wpcpm-card-message><a href="#wpcpm-invite-email-' . T20_A . '">Nothing was sent.' ),
	substr_count( $s4_refused, 'data-wpcpm-card-message' ),
	1 === preg_match( '/<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="wpcpm-people-invite"/', $s4_refused ),
), array( true, 1, true ) );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	wpcpm_layout_keep( 'Representatives and tutors: Invite a colleague after a send, the message over the form (S4)', $s4_sent );
	wpcpm_layout_keep( 'Representatives and tutors: Invite a colleague refused, the message over the form (S4)', $s4_refused );
}

// Gate B round 2, F6: a waiting row's outcome is said even when the rows are not drawn for this reader (a tutor now, the
// permission changed between the press and the page): at the card's message place, after the people.
$f6_html = t20_play( static function () { t20_fresh(); t20_long(); t20_flash( 204, WPCPM_Institution_Invite::FLASH, array( 'status' => 'invite-resent', 'detail' => 'colleague@example.org', 'record' => T20_A ) ); return array( 204, T20_A, false ); }, true );
ck( 'a waiting row\'s outcome with no rows drawn for the reader is said once, at the card\'s message place', array( has( $f6_html, 'Invitations waiting' ), substr_count( $f6_html, 'The invitation was sent again, with a new link.' ) ), array( false, 1 ) );

// The tutors' Add: its own refusals (a name, an address, a tutor the site could not keep) inside Add a tutor, before its
// form; the list's outcomes (added, removed, already, gone, busy) under Tutors, over the rows, as before.
$b7t = array();
foreach ( array( 'name', 'email', 'error', 'added', 'removed' ) as $b7_status ) {
	$b7_html = t20_play( static function () use ( $b7_status ) { t20_fresh(); t20_flash( 201, WPCPM_Institution_Tutors::FLASH, array( 'status' => $b7_status, 'record' => T20_A ) ); return array( 201, T20_A, false ); }, true );
	$b7_at   = strpos( $b7_html, 'data-wpcpm-card-message' );
	$b7_fold = strpos( $b7_html, 'id="wpcpm-tutors-add"' );
	$b7_form = false === $b7_fold ? false : strpos( $b7_html, '<form', $b7_fold );
	$b7_head = strpos( $b7_html, 'id="wpcpm-tutors"' );
	$b7t[ $b7_status ] = false === $b7_at ? 'none' : ( false !== $b7_fold && $b7_at > $b7_fold && $b7_at < $b7_form ? 'in Add a tutor, before its form' : ( $b7_at > $b7_head && ( false === $b7_fold || $b7_at < $b7_fold ) ? 'under Tutors' : 'elsewhere' ) );
}
ck( 'a tutor outcome is said at the form or the list it is about', $b7t, array(
	'name'    => 'in Add a tutor, before its form',
	'email'   => 'in Add a tutor, before its form',
	'error'   => 'in Add a tutor, before its form',
	'added'   => 'under Tutors',
	'removed' => 'under Tutors',
) );

$GLOBALS['core_nonce'] = false;

$dashes = array();

foreach ( array(
	'includes/modules/class-wpcpm-institution-people.php',
	'bin/test-institution-people.php',
) as $rel ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $rel ) ) ) {
		$dashes[] = $rel;
	}
}

ck( 'no dash but the plain hyphen in either file', $dashes, array() );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
