<?php
/**
 * A member inviting a colleague, without a program manager in the loop.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The token is stored as a hash and never in the clear.** The secret exists in the mail
 *   and nowhere else, so a database read, a backup or an export cannot accept an invitation.
 *   The suite reads the token out of the mail body, the only place it is, and then asserts
 *   it appears nowhere in the post or its meta.
 * - **The link in the mail only asks.** Following it draws a page and changes nothing: no
 *   account, no membership, no settled row, no audit entry. The acceptance is the POST that
 *   page's button makes, carrying a nonce minted for that one token. A link that changes
 *   state is followed by mail gateways, security scanners and prefetchers, and the suite
 *   asserts the world is untouched after a GET rather than trusting the reading.
 * - **Every failure on the accept path gives one message, byte for byte, on both arms.** An
 *   expired row, a cancelled one, an unknown token, a token that has already been used, and a
 *   signed-in visitor whose address is not the invited one: five different situations, one
 *   sentence, whether the reader met the link or the button. Telling them apart would let a
 *   stranger walking tokens learn which addresses this program has invited.
 * - A resend mints a new secret, because this site cannot read the old one back, and the
 *   earlier link stops working. That is a property of the storage, not a preference.
 * - The lapse is read from the row and not from the state, so an invitation past its
 *   fourteen days is dead whether or not the nightly job has run.
 * - The eleventh pending invitation is refused, naming the limit; the sixth send in a day is
 *   refused by the ceiling; an address that is already a member is refused before anything
 *   is sent, and no refusal uses up one of the day's five sends.
 * - **The five sends a day are per member, not per institution** (spec 14.13), so a colleague
 *   who has sent none can still invite somebody on a day another member has spent theirs.
 * - **Retention runs from the moment a row settled**, not from the day it was written: a row
 *   sent forty days ago and cancelled last night has a month to run, and one that has been
 *   settled for thirty-one days is gone.
 * - **Cancel and resend are decided against the institution the invitation names**, read
 *   from the post's own meta, so a member of B posting A's invitation ID gets the one
 *   refusal. The nonce on each is keyed to the invitation, so a token for cancelling one is
 *   not a token for cancelling another.
 * - Accepting attaches with `invited` and the inviter as the actor, so the People card can
 *   say who let this person in.
 *
 * Run from the plugin root:  php bin/test-institution-invite.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']       = array( 'date_format' => 'Y-m-d' );
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
$GLOBALS['sent']       = array();
$GLOBALS['mail_ok']    = true;
$GLOBALS['referer']    = array();
$GLOBALS['settings']   = array( 'invite_retention_days' => 30 );

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array(), $login = '' ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = '' !== $login ? $login : strtolower( str_replace( ' ', '', $name ) );
		$this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
	public function add_role( $r ) { if ( ! in_array( $r, $this->roles, true ) ) { $this->roles[] = $r; } }
	public function remove_role( $r ) { $this->roles = array_values( array_diff( $this->roles, array( $r ) ) ); }
	public function set_role( $r ) { $this->roles = '' === $r ? array() : array( $r ); }
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
function get_bloginfo( $k = 'name' ) { return 'language' === $k ? 'en-US' : 'WordPress Education Dashboard'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
/** The ceiling's test-and-set: one INSERT that fails when the row is already there. */
function add_option( $k, $v, $x = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_current_user_id() { return $GLOBALS['uid']; }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
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
function username_exists( $login ) { $u = get_user_by( 'login', $login ); return $u ? $u->ID : false; }
/** Distinct every time, and alphanumeric, which is what the token's own shape test demands. */
function wp_generate_password( $l = 12, $s = true, $e = false ) {
	static $n = 0;
	++$n;
	return substr( str_pad( 'tok' . $n, (int) $l, 'abcdefghijklmnopqrstuvwxyz0123456789' ), 0, (int) $l );
}
function wp_hash( $data, $scheme = 'auth' ) { return hash_hmac( 'md5', (string) $data, 'a-test-salt' ); }
function add_query_arg( $args, $url = '' ) {
	return (string) $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . http_build_query( (array) $args );
}
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
	$post->post_date_gmt           = gmdate( 'Y-m-d H:i:s' );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_time( $f, $gmt = false, $post = null ) { return strtotime( $post->post_date_gmt . ' UTC' ); }
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
	if ( 'ids' === ( $a['fields'] ?? '' ) ) { $out = array_map( function ( $p ) { return $p->ID; }, $out ); }
	return $out;
}
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['referer'][] = $a; return true; }
/** A real binding, so a nonce minted for one action does not verify for another. */
function wp_create_nonce( $a = -1 ) { return 'nonce-' . (string) $a; }
function wp_verify_nonce( $n, $a = -1 ) { return 'nonce-' . (string) $a === (string) $n ? 1 : false; }
function nocache_headers() {}
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect:' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die:' . $m ); }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

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
if ( ! class_exists( 'WPCPM_Settings' ) ) {
	class WPCPM_Settings {
		public static function get_value( $key, $fallback = null ) {
			return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
		}
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
	/** Only what the policy's `subject_index_row()` would reach for; nothing here asks for it. */
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
if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
	class WPCPM_Institutions_Dashboard {
		public static function page_url() { return 'https://example.test/institution-dashboard/'; }
	}
}
if ( ! class_exists( 'WPCPM_Mail' ) ) {
	/**
	 * The one exit for mail, recorded rather than sent.
	 *
	 * `send_to()` runs the builder exactly as the real one does, so the body the invitation
	 * would arrive with - and the link inside it - is what the suite reads the token out of.
	 */
	class WPCPM_Mail {
		public static function send_to( $email, $context, $build, $locale = '' ) {
			if ( ! is_email( $email ) || ! is_callable( $build ) ) { return false; }
			$GLOBALS['sent'][] = array( 'to' => $email, 'context' => $context, 'mail' => call_user_func( $build, $email ) );
			return $GLOBALS['mail_ok'];
		}
		public static function send( $recipient, $context, $build ) {
			$user = $recipient instanceof WP_User ? $recipient : get_user_by( 'id', (int) $recipient );
			if ( ! $user instanceof WP_User ) { return false; }
			$GLOBALS['sent'][] = array( 'to' => $user->user_email, 'context' => $context, 'mail' => call_user_func( $build, $user ) );
			return true;
		}
		public static function queue_invites( array $user_ids ) {
			$fresh = 0;
			foreach ( $user_ids as $id ) {
				if (
					get_user_meta( $id, 'wpcpm_student_invited', true )
					|| get_user_meta( $id, 'wpcpm_mentor_invited', true )
					|| get_user_meta( $id, 'wpcpm_inst_invited', true )
				) {
					continue;
				}
				$GLOBALS['queued'][] = (int) $id;
				++$fresh;
			}
			return $fresh;
		}
		public static function reply_to( $person ) {
			return $person instanceof WP_User ? array( sprintf( 'Reply-To: "%1$s" <%2$s>', $person->display_name, $person->user_email ) ) : array();
		}
		public static function site_name() { return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ); }
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-people.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-invite.php';

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
function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, (string) $needle ); }
/** Run a handler and report how it ended: a redirect, a wp_die, or nothing at all. */
function run( $method ) {
	try {
		call_user_func( array( 'WPCPM_Institution_Invite', $method ) );
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
	return '';
}
function invite_as( $uid, $record, $email, $from = '' ) {
	$GLOBALS['uid'] = (int) $uid;
	$_POST          = array( 'record' => $record, 'email' => $email );
	if ( '' !== $from ) { $_POST['wpcpm_from'] = $from; }
	return run( 'handle_invite' );
}
function press( $uid, $method, $post_id ) {
	$GLOBALS['uid'] = (int) $uid;
	$_POST          = array( 'invite' => $post_id );
	return run( $method );
}
/**
 * Follow the link from the mail: a GET that must only ask.
 *
 * The page itself rather than the two-line route, because the route ends in `exit` on the way
 * out and there is no seam in one process for that. The route is asserted below by its shape,
 * and by `route_get_as()`, which reaches it on the arm that refuses.
 */
function ask_as( $uid, $token ) {
	$GLOBALS['uid']            = (int) $uid;
	$_GET                      = array( 't' => $token );
	$_POST                     = array();
	$_SERVER['REQUEST_METHOD'] = 'GET';
	try {
		return (string) WPCPM_Institution_Invite::accept_page();
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
}
/** The handler itself on a GET. Only ever called where the answer is a refusal. */
function route_get_as( $uid, $token ) {
	$GLOBALS['uid']            = (int) $uid;
	$_GET                      = array( 't' => $token );
	$_POST                     = array();
	$_SERVER['REQUEST_METHOD'] = 'GET';
	return run( 'handle_accept' );
}
/**
 * Press the button on that page: the POST that accepts.
 *
 * The nonce defaults to the one the page would have carried for this token, so a caller that
 * wants a wrong one has to say so.
 */
function accept_as( $uid, $token, $nonce = null ) {
	$GLOBALS['uid']            = (int) $uid;
	$_GET                      = array();
	$_POST                     = array( 't' => $token, '_wpnonce' => null === $nonce ? 'nonce-wpcpm_accept_invite_' . $token : $nonce );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	return run( 'handle_accept' );
}
function flash( $uid ) { return $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash']['institution_invite'] ?? null; }
function flash_status( $uid ) { $f = flash( $uid ); return is_array( $f ) ? $f['status'] : (string) $f; }
function last_mail() { $mails = $GLOBALS['sent']; return $mails ? end( $mails ) : array(); }
/** The secret, read out of the mail body: the one place it exists. */
function token_from_mail() {
	$mail = last_mail();
	$body = $mail['mail']['body'] ?? '';
	return preg_match( '/[?&]t=([A-Za-z0-9]{32})/', (string) $body, $m ) ? $m[1] : '';
}
function invites( $record ) { return WPCPM_Institution_Invite::invites_for( $record ); }
function pmeta( $id, $key ) { return $GLOBALS['pmeta'][ (int) $id ][ $key ][0] ?? null; }
function newest_invite() {
	$ids = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( WPCPM_Institution_Invite::POST_TYPE === $post->post_type ) { $ids[] = (int) $post->ID; }
	}
	return $ids ? (int) end( $ids ) : 0;
}
function invite_count() {
	$n = 0;
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( WPCPM_Institution_Invite::POST_TYPE === $post->post_type ) { ++$n; }
	}
	return $n;
}
/** Everything the site stored, as one string, for "the token is nowhere in here". */
function stored() { return serialize( $GLOBALS['posts'] ) . serialize( $GLOBALS['pmeta'] ) . serialize( $GLOBALS['opts'] ); }
function audit_kinds( $record ) {
	$kinds = array();
	foreach ( WPCPM_Institution_Audit::entries_for( $record ) as $entry ) { $kinds[] = $entry['kind']; }
	return $kinds;
}
function last_entry( $record ) {
	$entries = WPCPM_Institution_Audit::entries_for( $record );
	return $entries ? $entries[0] : array();
}
/** Seed a pending invitation without going through the handler, for the limits. */
function seed_invite( $record, $email, $actor, $sent ) {
	$post_id = wp_insert_post(
		array(
			'post_type'   => WPCPM_Institution_Invite::POST_TYPE,
			'post_status' => WPCPM_Institution_Invite::POST_STATUS,
			'post_author' => $actor,
			'post_title'  => 'Invitation',
		)
	);
	update_post_meta( $post_id, WPCPM_Institution_Invite::META_INSTITUTION, $record );
	update_post_meta( $post_id, WPCPM_Institution_Invite::META_EMAIL, $email );
	update_post_meta( $post_id, WPCPM_Institution_Invite::META_STATE, WPCPM_Institution_Invite::STATE_PENDING );
	update_post_meta( $post_id, WPCPM_Institution_Invite::META_ACTOR, $actor );
	update_post_meta( $post_id, WPCPM_Institution_Invite::META_SENT, $sent );
	update_post_meta( $post_id, WPCPM_Institution_Invite::META_TOKEN, wp_hash( 'wpcpm-institution-invite|seed' . $post_id ) );
	return (int) $post_id;
}
/** Forget every post and every ceiling row, keeping the people and the memberships. */
function reset_world() {
	$GLOBALS['posts'] = array();
	$GLOBALS['pmeta'] = array();
	$GLOBALS['sent']  = array();
	foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
		if ( 0 === strpos( (string) $name, WPCPM_Ceiling::PREFIX ) ) { unset( $GLOBALS['opts'][ $name ] ); }
	}
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
function before( $body, $first, $second ) {
	$one = strpos( (string) $body, $first );
	$two = strpos( (string) $body, $second );
	return false !== $one && false !== $two && $one < $two;
}

$A = 'recDdomg5W6h410JT'; // the TEST institution in the seed fixture.
$B = 'rec0IT9J93YkAYvSU';
$C = 'recZZZZZZZZZZZZZZ'; // well-formed, never indexed.

$GLOBALS['index'] = array(
	$A => array( 'record_id' => $A, 'name' => 'TEST - WordPress Education Dashboard (do not use) ', 'stage' => 'Confirmed', 'city' => 'Test', 'country_name' => 'Poland', 'website' => '', 'contact_person' => 'Bob Contact', 'contact_email' => 'contact@example.test' ),
	$B => array( 'record_id' => $B, 'name' => 'Universidad Example', 'stage' => 'Confirmed', 'city' => 'Example', 'country_name' => 'Costa Rica', 'website' => '', 'contact_person' => '', 'contact_email' => 'rector@example.test' ),
);
$GLOBALS['index_read'] = 1756000000;
$GLOBALS['settled']    = array( $A, $B );

$GLOBALS['users'] = array(
	1  => new WP_User( 1, 'Manager', 'manager@example.test', array( 'administrator' ) ),
	7  => new WP_User( 7, 'Anna Kowalska', 'anna@example.test', array( 'subscriber' ) ),
	8  => new WP_User( 8, 'Bob Contact', 'contact@example.test', array( 'subscriber' ) ),
	9  => new WP_User( 9, 'Cleo Beta', 'cleo@example.test', array( 'subscriber' ) ),
	20 => new WP_User( 20, 'Ola Existing', 'ola@example.test', array( 'subscriber' ) ),
	21 => new WP_User( 21, 'Piotr Elsewhere', 'piotr@example.test', array( 'subscriber' ) ),
);
$GLOBALS['manage'] = array( 1 );

WPCPM_Institution_Members::attach( 7, $A, 'manager', 1 );
WPCPM_Institution_Members::attach( 8, $A, 'provisioned', 0 );
WPCPM_Institution_Members::attach( 9, $B, 'manager', 1 );

reset_world();

/* ---- one invitation, and the token that is never written down ----------- */

echo "=== A member invites a colleague ===\n";

$end = invite_as( 7, $A, 'Colleague@Example.test' );
$one = newest_invite();

ck( 'the member is sent back to the People card', $end, 'redirect:https://example.test/institution-dashboard/#wpcpm-people' );
ck( 'and told the invitation is on its way', flash_status( 7 ), 'invite-sent' );
ck( 'the nonce was keyed to the institution', end( $GLOBALS['referer'] ), 'wpcpm_invite_member_' . $A );
ck( 'one invitation exists, pending, for this institution', array(
	pmeta( $one, '_wpcpm_inv_state' ),
	pmeta( $one, '_wpcpm_inv_institution' ),
	pmeta( $one, '_wpcpm_inv_actor' ),
), array( 'pending', $A, 7 ) );
ck( 'the address is stored lowercased', pmeta( $one, '_wpcpm_inv_email' ), 'colleague@example.test' );
ck( 'and it is waiting', WPCPM_Institution_Invite::pending_for( $A ), array( $one ) );

$mail  = last_mail();
$token = token_from_mail();

ck( 'one message went to the address, through the mail layer', array( $mail['to'], $mail['context'] ), array( 'colleague@example.test', 'institution-invite' ) );
ck( 'it names the institution and who invited them', array(
	has( $mail['mail']['subject'], 'TEST - WordPress Education Dashboard (do not use)' ),
	has( $mail['mail']['body'], 'Anna Kowalska' ),
), array( true, true ) );
ck( 'it can be replied to', $mail['mail']['headers'], array( 'Reply-To: "Anna Kowalska" <anna@example.test>' ) );
ck( 'the link is the accept action with a token', has( $mail['mail']['body'], 'action=wpcpm_accept_invite' ), true );
ck( 'and the message says the link asks rather than accepts', array(
	has( $mail['mail']['body'], 'press Accept on the page it opens' ),
	has( $mail['mail']['body'], 'nothing happens until you press Accept' ),
), array( true, true ) );
ck( 'and the token is 32 alphanumeric characters', 1, preg_match( '/^[A-Za-z0-9]{32}$/', $token ) );

echo "\n=== The token is stored hashed, and nowhere in the clear ===\n";

ck( 'the token itself is in nothing this site stored', has( stored(), $token ), false );
ck( 'what the row holds is a hash of it', pmeta( $one, '_wpcpm_inv_token' ), wp_hash( 'wpcpm-institution-invite|' . $token ) );
ck( 'the hash is not the token', pmeta( $one, '_wpcpm_inv_token' ) === $token, false );
ck( 'and no line in the class stores the clear token', preg_match( '/META_TOKEN,\s*\$token/', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-invite.php' ) ), 0 );

ck( 'one audit row names the invitation, on the member ground', array(
	last_entry( $A )['kind'],
	last_entry( $A )['actor'],
	last_entry( $A )['ground'],
	has( last_entry( $A )['message'], 'colleague@example.test' ),
), array( 'invite_sent', 7, 'member', true ) );

/* ---- what the form refuses ---------------------------------------------- */

echo "\n=== An address that is already a member, and the other refusals ===\n";

$before = invite_count();

invite_as( 7, $A, 'ANNA@example.test' );
ck( 'a live member is refused', flash_status( 7 ), 'invite-member' );
ck( 'and nothing was recorded', invite_count(), $before );

invite_as( 7, $A, 'colleague@example.test' );
ck( 'a second invitation to the same address is refused', flash_status( 7 ), 'invite-waiting' );
ck( 'and nothing was recorded', invite_count(), $before );

invite_as( 7, $A, 'not an address' );
ck( 'an address WordPress cannot mail is refused', flash_status( 7 ), 'invite-bad-email' );

$end = invite_as( 9, $A, 'stranger@example.test' );
ck( 'a member of another institution gets the one refusal', $end, 'wp_die:' . WPCPM_Institution_Policy::refusal()->get_error_message() );
ck( 'and nothing was recorded', invite_count(), $before );

$end = invite_as( 7, 'not-a-record', 'stranger@example.test' );
ck( 'so does a record ID that is not one', $end, 'wp_die:' . WPCPM_Institution_Policy::refusal()->get_error_message() );

$end = invite_as( 0, $A, 'stranger@example.test' );
ck( 'and a signed-out request is turned away', has( $end, 'wp_die:You need to be signed in' ), true );

$end = invite_as( 1, $C, 'stranger@example.test' );
ck( 'a manager inviting to an institution the index has never read is told so', flash_status( 1 ), 'invite-unknown' );
ck( 'and nothing was recorded', invite_count(), $before );

/* ---- the two limits ------------------------------------------------------ */

echo "\n=== Five sends a day per member, and ten waiting per institution ===\n";

reset_world();

$outcomes = array();

for ( $n = 1; $n <= 6; $n++ ) {
	invite_as( 7, $A, 'ceiling' . $n . '@example.test' );
	$outcomes[] = flash_status( 7 );
}

ck( 'the first five are sent and the sixth is refused', $outcomes, array( 'invite-sent', 'invite-sent', 'invite-sent', 'invite-sent', 'invite-sent', 'invite-ceiling' ) );
ck( 'and the sixth recorded nothing', count( WPCPM_Institution_Invite::pending_for( $A ) ), 5 );

// Spec 14.13: five a day per *member*, the figure the product owner answered and the one the
// drafted privacy notice states. Counted per institution instead, Anna's five would have shut
// her colleague out of inviting anybody until tomorrow.
invite_as( 8, $A, 'bobs-colleague@example.test' );

ck( 'a colleague still has their own five', flash_status( 8 ), 'invite-sent' );
ck( 'and the day is counted against each of them, not against the institution', array(
	WPCPM_Ceiling::count( 'invite:' . $A . ':7', DAY_IN_SECONDS ),
	WPCPM_Ceiling::count( 'invite:' . $A . ':8', DAY_IN_SECONDS ),
), array( 5, 1 ) );
ck( 'the refusal names the number and whose day it was', array(
	has( WPCPM_Institution_Invite::messages()['invite-ceiling'][1], '5' ),
	has( WPCPM_Institution_Invite::messages()['invite-ceiling'][1], 'You have sent' ),
), array( true, true ) );

reset_world();

for ( $n = 1; $n <= 10; $n++ ) {
	seed_invite( $A, 'waiting' . $n . '@example.test', 7, time() );
}

ck( 'ten invitations are waiting', count( WPCPM_Institution_Invite::pending_for( $A ) ), 10 );

invite_as( 7, $A, 'eleventh@example.test' );

ck( 'the eleventh is refused', flash_status( 7 ), 'invite-full' );
ck( 'the refusal names the limit', has( WPCPM_Institution_Invite::messages()['invite-full'][1], '10' ), true );
ck( 'and still ten are waiting', count( WPCPM_Institution_Invite::pending_for( $A ) ), 10 );
ck( 'a refusal did not use up one of the day\'s sends', WPCPM_Ceiling::count( 'invite:' . $A . ':7', DAY_IN_SECONDS ), 0 );

// A lapsed row is not somebody waiting, so it does not hold one of the ten either.
$lapsed = seed_invite( $A, 'lapsed@example.test', 7, time() - ( 15 * DAY_IN_SECONDS ) );
ck( 'a lapsed row is not counted as waiting', count( WPCPM_Institution_Invite::pending_for( $A ) ), 10 );
ck( 'though it is still there, still pending', pmeta( $lapsed, '_wpcpm_inv_state' ), 'pending' );

/* ---- accepting ----------------------------------------------------------- */

echo "\n=== The link in the mail asks, and changes nothing ===\n";

reset_world();

invite_as( 7, $A, 'newcomer@example.test' );
$invite  = newest_invite();
$token   = token_from_mail();
$members = count( WPCPM_Institution_Members::members_of( $A ) );
$people  = count( $GLOBALS['users'] );

$page = ask_as( 0, $token );

ck( 'the link draws a page rather than doing anything', has( $page, '<form' ), true );
ck( 'and the only control on it is a POST back to the accept action', array(
	has( $page, 'method="post"' ),
	has( $page, 'name="action" value="wpcpm_accept_invite"' ),
	has( $page, 'name="t" value="' . $token . '"' ),
	has( $page, 'name="_wpnonce" value="nonce-wpcpm_accept_invite_' . $token . '"' ),
	has( $page, '<button type="submit"' ),
), array( true, true, true, true, true ) );
ck( 'it names the institution and who invited them, as the mail did', array(
	has( $page, 'TEST - WordPress Education Dashboard (do not use)' ),
	has( $page, 'Anna Kowalska' ),
), array( true, true ) );
ck( 'and says nothing the mail did not: the invited address is not printed', has( $page, 'newcomer@example.test' ), false );
ck( 'the token stays out of an index and out of a referer', array(
	has( $page, 'name="robots" content="noindex, nofollow"' ),
	has( $page, 'name="referrer" content="no-referrer"' ),
), array( true, true ) );

// The whole of it: a mail gateway, a security scanner or a link prefetcher that follows this
// link must leave the world exactly as it found it. Every line below is a thing the handler
// used to do to somebody who had not clicked anything yet.
ck( 'the invitation is still waiting, with its token still live', array(
	pmeta( $invite, '_wpcpm_inv_state' ),
	'' !== (string) pmeta( $invite, '_wpcpm_inv_token' ),
), array( 'pending', true ) );
ck( 'no account was made for the address', get_user_by( 'email', 'newcomer@example.test' ) instanceof WP_User, false );
ck( 'no account was made at all', count( $GLOBALS['users'] ), $people );
ck( 'nobody became a member', count( WPCPM_Institution_Members::members_of( $A ) ), $members );
ck( 'and nothing was written to the log', in_array( 'invite_accepted', audit_kinds( $A ), true ), false );

ask_as( 0, $token );

ck( 'following the link twice is still only a question', pmeta( $invite, '_wpcpm_inv_state' ), 'pending' );

echo "\n=== The button accepts: once, and by the person it was sent to ===\n";

ck( 'a POST with no nonce is refused', has( accept_as( 0, $token, '' ), 'wp_die:That invitation cannot be used' ), true );
ck( 'so is one carrying a nonce minted for another invitation', has( accept_as( 0, $token, 'nonce-wpcpm_accept_invite_' . str_pad( 'other', 32, 'z' ) ), 'wp_die:That invitation cannot be used' ), true );
ck( 'and the invitation is untouched by either', array(
	pmeta( $invite, '_wpcpm_inv_state' ),
	get_user_by( 'email', 'newcomer@example.test' ) instanceof WP_User,
), array( 'pending', false ) );

$end = accept_as( 0, $token );

$created = get_user_by( 'email', 'newcomer@example.test' );

ck( 'a signed-out visitor lands on the dashboard', $end, 'redirect:https://example.test/institution-dashboard/' );
ck( 'an account was made for the address', $created instanceof WP_User, true );
ck( 'it holds the Institution role', $created->roles, array( 'wpcpm_institution' ) );
ck( 'and it was sent the set-your-password message', in_array( (int) $created->ID, $GLOBALS['queued'], true ), true );
ck( 'it is a member of the institution now', WPCPM_Institution_Members::institution_of( $created->ID ), $A );

$membership = $GLOBALS['umeta'][ $created->ID ]['wpcpm_institution_membership'];

ck( 'attached as invited, with the inviter as the actor and the invitation named', array(
	$membership['how'],
	$membership['by'],
	$membership['invite'],
), array( 'invited', 7, $invite ) );

ck( 'the row is accepted, and names the account', array(
	pmeta( $invite, '_wpcpm_inv_state' ),
	(int) pmeta( $invite, '_wpcpm_inv_user' ),
	pmeta( $invite, '_wpcpm_inv_accepted' ) > 0,
), array( 'accepted', (int) $created->ID, true ) );
ck( 'and its token is forgotten, so the link is dead whatever the state says', pmeta( $invite, '_wpcpm_inv_token' ), '' );
ck( 'an audit row records the acceptance', array( last_entry( $A )['kind'], last_entry( $A )['actor'] ), array( 'invite_accepted', (int) $created->ID ) );
ck( 'and the member card would show it', array( count( invites( $A ) ), invites( $A )[0]['state'], invites( $A )[0]['waiting'] ), array( 1, 'accepted', false ) );

$second = accept_as( 0, $token );

ck( 'the same link a second time is refused', has( $second, 'wp_die:That invitation cannot be used' ), true );
ck( 'and nobody else was attached', count( WPCPM_Institution_Members::members_of( $A ) ), 3 );

echo "\n=== An account that already exists is adopted, not duplicated ===\n";

reset_world();

invite_as( 7, $A, 'ola@example.test' );
$token   = token_from_mail();
$accounts = count( $GLOBALS['users'] );

accept_as( 0, $token );

ck( 'no second account was made', count( $GLOBALS['users'] ), $accounts );
ck( 'the existing one is the member', WPCPM_Institution_Members::institution_of( 20 ), $A );

echo "\n=== Signed in as somebody else, refused with the same message ===\n";

reset_world();

invite_as( 7, $A, 'guest@example.test' );
$token = token_from_mail();

$wrong = accept_as( 21, $token );

ck( 'a signed-in visitor with another address is refused', has( $wrong, 'wp_die:That invitation cannot be used' ), true );
ck( 'and the page itself refuses them, so they are never offered a button', has( ask_as( 21, $token ), 'wp_die:That invitation cannot be used' ), true );
ck( 'their account was not attached to anything', WPCPM_Institution_Members::institution_of( 21 ), '' );
ck( 'the invitation is still waiting for the person it was sent to', pmeta( newest_invite(), '_wpcpm_inv_state' ), 'pending' );

// The invited address, signed in: the one case a signed-in acceptance is allowed.
$GLOBALS['users'][22] = new WP_User( 22, 'Guest Person', 'guest@example.test', array( 'subscriber' ) );

accept_as( 22, $token );

ck( 'the invited address, signed in, joins', WPCPM_Institution_Members::institution_of( 22 ), $A );

/* ---- one message for every failure --------------------------------------- */

echo "\n=== Expired, cancelled, unknown and used are one message ===\n";

reset_world();

// An unknown token, well formed and never issued.
$unknown = accept_as( 0, str_pad( 'never', 32, 'z' ) );

// A row past its fourteen days that the nightly job has not seen yet: the lapse is read
// from the row, so the link is dead before the cron runs.
invite_as( 7, $A, 'stale@example.test' );
$stale_token = token_from_mail();
update_post_meta( newest_invite(), '_wpcpm_inv_sent', time() - ( 15 * DAY_IN_SECONDS ) );
$expired = accept_as( 0, $stale_token );

// A cancelled one.
invite_as( 7, $A, 'dropped@example.test' );
$dropped_id    = newest_invite();
$dropped_token = token_from_mail();
press( 7, 'handle_cancel', $dropped_id );
$cancelled = accept_as( 0, $dropped_token );

// One that has already been used.
invite_as( 7, $A, 'twice@example.test' );
$used_token = token_from_mail();
accept_as( 0, $used_token );
$used = accept_as( 0, $used_token );

// And one followed while signed in as another address.
invite_as( 7, $A, 'other@example.test' );
$other_token = token_from_mail();
$other       = accept_as( 21, $other_token );

$answers = array( $unknown, $expired, $cancelled, $used, $other );

ck( 'five different failures, one answer', count( array_unique( $answers ) ), 1 );
ck( 'and it is the one message', has( $unknown, 'wp_die:That invitation cannot be used' ), true );
ck( 'a shape that is not a token opens no query at all', accept_as( 0, 'short' ), $unknown );

// The same five on the arm the link reaches. The page must not be more helpful than the
// button: a stranger walking tokens gets one sentence whichever half of accepting they meet.
$asked = array(
	ask_as( 0, str_pad( 'never', 32, 'z' ) ),
	ask_as( 0, $stale_token ),
	ask_as( 0, $dropped_token ),
	ask_as( 0, $used_token ),
	ask_as( 21, $other_token ),
);

ck( 'the link answers the same five the same way', array_unique( $asked ), array( $unknown ) );
ck( 'and the handler routes a GET to that same answer', route_get_as( 0, str_pad( 'never', 32, 'z' ) ), $unknown );

/* ---- cancel and resend --------------------------------------------------- */

echo "\n=== Cancel and resend are decided against the invitation's institution ===\n";

reset_world();

invite_as( 7, $A, 'colleague@example.test' );
$mine       = newest_invite();
$mine_token = token_from_mail();

$end = press( 9, 'handle_cancel', $mine );

ck( 'a member of B cancelling A\'s invitation gets the one refusal', $end, 'wp_die:' . WPCPM_Institution_Policy::refusal()->get_error_message() );
ck( 'the nonce was keyed to the invitation, not to the form', end( $GLOBALS['referer'] ), 'wpcpm_cancel_invite_' . $mine );
ck( 'and the invitation is untouched', pmeta( $mine, '_wpcpm_inv_state' ), 'pending' );

$end = press( 9, 'handle_resend', $mine );

ck( 'nor can they resend it', $end, 'wp_die:' . WPCPM_Institution_Policy::refusal()->get_error_message() );
ck( 'and no second message went out', count( $GLOBALS['sent'] ), 1 );

$end = press( 7, 'handle_resend', $mine );
$new_token = token_from_mail();

ck( 'the institution\'s own member may resend', flash_status( 7 ), 'invite-resent' );
ck( 'a resend mints a new secret, because the old one cannot be read back', $new_token !== $mine_token, true );
ck( 'the new link works', has( accept_as( 0, $new_token ), 'redirect:' ), true );

reset_world();

invite_as( 7, $A, 'gone@example.test' );
$doomed     = newest_invite();
$old_token  = token_from_mail();
press( 7, 'handle_resend', $doomed );

ck( 'and the earlier link stops working', has( accept_as( 0, $old_token ), 'wp_die:That invitation cannot be used' ), true );

$end = press( 7, 'handle_cancel', $doomed );

ck( 'cancelling sends the member back to the card', has( $end, 'redirect:' ), true );
ck( 'the row is cancelled and its token forgotten', array( pmeta( $doomed, '_wpcpm_inv_state' ), pmeta( $doomed, '_wpcpm_inv_token' ) ), array( 'cancelled', '' ) );
ck( 'an audit row says who cancelled it', array( last_entry( $A )['kind'], last_entry( $A )['actor'] ), array( 'invite_cancelled', 7 ) );
ck( 'cancelling it again is answered, not repeated', array( flash_status( 7 ), press( 7, 'handle_cancel', $doomed ) === '' ), array( 'invite-cancelled', false ) );
ck( 'the second press says it is no longer waiting', flash_status( 7 ), 'invite-gone' );
ck( 'a post ID that is not an invitation gets the one refusal', press( 7, 'handle_cancel', 999999 ), 'wp_die:' . WPCPM_Institution_Policy::refusal()->get_error_message() );

echo "\n=== A program manager passes, and a lost record stops an acceptance ===\n";

reset_world();

invite_as( 7, $A, 'backstop@example.test' );
$managed = newest_invite();

press( 1, 'handle_cancel', $managed );

ck( 'a program manager may cancel any institution\'s invitation', pmeta( $managed, '_wpcpm_inv_state' ), 'cancelled' );
ck( 'and the row says it was the manager ground', last_entry( $A )['ground'], 'manager' );

reset_world();

invite_as( 7, $A, 'orphan@example.test' );
$token = token_from_mail();
$rows  = $GLOBALS['index'];
unset( $GLOBALS['index'][ $A ] );

$lost = accept_as( 0, $token );

ck( 'an institution the index has lost cannot be joined', has( $lost, 'wp_die:That invitation cannot be used' ), true );
ck( 'and no account was made for somebody with nothing to see', get_user_by( 'email', 'orphan@example.test' ) instanceof WP_User, false );

$GLOBALS['index'] = $rows;

echo "\n=== When the mail does not go out, the invitation is still there to resend ===\n";

reset_world();

$GLOBALS['mail_ok'] = false;
invite_as( 7, $A, 'unreachable@example.test' );
$GLOBALS['mail_ok'] = true;

ck( 'the member is told the message did not go', flash_status( 7 ), 'invite-unsent' );
ck( 'and the invitation is waiting, so Resend is the way out', count( WPCPM_Institution_Invite::pending_for( $A ) ), 1 );

ck( 'facts about a post that is not an invitation are nothing at all', WPCPM_Institution_Invite::facts( 999999 ), array() );

WPCPM_Institution_Invite::delete_all();

ck( 'uninstall removes every invitation', invite_count(), 0 );

/* ---- the jobs nobody presses --------------------------------------------- */

echo "\n=== Expiry, retention, and the two rules that cancel by themselves ===\n";

reset_world();

$fresh = seed_invite( $A, 'fresh@example.test', 7, time() - DAY_IN_SECONDS );
$stale = seed_invite( $A, 'stale@example.test', 7, time() - ( 15 * DAY_IN_SECONDS ) );

ck( 'the job closes the one that ran out', WPCPM_Institution_Invite::expire(), 1 );
ck( 'and leaves the one that has not', array( pmeta( $fresh, '_wpcpm_inv_state' ), pmeta( $stale, '_wpcpm_inv_state' ) ), array( 'pending', 'expired' ) );
ck( 'the expiry is in the log', last_entry( $A )['kind'], 'invite_expired' );
ck( 'and running it again changes nothing', WPCPM_Institution_Invite::expire(), 0 );

// Retention: the address goes, the audit row stays, and the thirty days run from the day the
// row settled rather than from the day it was written. The two are the same day for an
// invitation that runs its fourteen out, and a month apart for one cancelled long after it
// was sent - which is why `settle()` stamps the moment and this reads the stamp.
ck( 'settling stamped the moment', pmeta( $stale, '_wpcpm_inv_settled' ) > 0, true );

// Sent forty days ago and settled last night. Measured from the post date it is a month past
// the window; measured from the moment it settled it has a month to run.
$late                                     = seed_invite( $A, 'late@example.test', 7, time() - ( 40 * DAY_IN_SECONDS ) );
$GLOBALS['posts'][ $late ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', time() - ( 40 * DAY_IN_SECONDS ) );

WPCPM_Institution_Invite::expire();

ck( 'a row that settled last night is kept, however old the invitation was', array(
	isset( $GLOBALS['posts'][ $late ] ),
	pmeta( $late, '_wpcpm_inv_state' ),
), array( true, 'expired' ) );

// The same two rows, thirty-one days after they settled.
update_post_meta( $late, '_wpcpm_inv_settled', time() - ( 31 * DAY_IN_SECONDS ) );
update_post_meta( $stale, '_wpcpm_inv_settled', time() - ( 31 * DAY_IN_SECONDS ) );
WPCPM_Institution_Invite::expire();

ck( 'a settled row past the retention window is deleted', array( isset( $GLOBALS['posts'][ $stale ] ), isset( $GLOBALS['posts'][ $late ] ) ), array( false, false ) );
ck( 'the fresh one is kept', isset( $GLOBALS['posts'][ $fresh ] ), true );
ck( 'and the log still says what happened', in_array( 'invite_expired', audit_kinds( $A ), true ), true );

// A row settled by a version that did not stamp the moment. Its window starts at the sweep
// that first saw it: guessing one from the post date is the defect the stamp exists to fix.
$legacy                                     = seed_invite( $A, 'legacy@example.test', 7, time() - ( 40 * DAY_IN_SECONDS ) );
$GLOBALS['posts'][ $legacy ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', time() - ( 90 * DAY_IN_SECONDS ) );
update_post_meta( $legacy, '_wpcpm_inv_state', 'cancelled' );

WPCPM_Institution_Invite::expire();

ck( 'an unstamped settled row is stamped and kept rather than guessed at', array(
	isset( $GLOBALS['posts'][ $legacy ] ),
	pmeta( $legacy, '_wpcpm_inv_settled' ) > 0,
), array( true, true ) );

reset_world();

seed_invite( $A, 'one@example.test', 7, time() );
seed_invite( $A, 'two@example.test', 8, time() );
seed_invite( $B, 'three@example.test', 9, time() );

ck( 'the last member leaving cancels this institution\'s invitations', WPCPM_Institution_Invite::cancel_for_institution( $A ), 2 );
ck( 'and leaves another institution\'s alone', count( WPCPM_Institution_Invite::pending_for( $B ) ), 1 );

reset_world();

seed_invite( $A, 'one@example.test', 7, time() );
seed_invite( $A, 'two@example.test', 8, time() );

ck( 'a member losing their membership cancels the invitations they sent', WPCPM_Institution_Invite::cancel_by_actor( 7 ), 1 );
ck( 'and not their colleague\'s', count( WPCPM_Institution_Invite::pending_for( $A ) ), 1 );
ck( 'both are recorded as the site\'s own act', last_entry( $A )['ground'], 'system' );

/* ---- what is drawn -------------------------------------------------------- */

echo "\n=== The form is drawn for a member, and for nobody else ===\n";

reset_world();

function render_form_as( $uid, $record, $origin = '' ) {
	$GLOBALS['uid'] = (int) $uid;
	ob_start();
	WPCPM_Institution_Invite::render_form( $record, $origin );
	return (string) ob_get_clean();
}
function render_pending_as( $uid, $record ) {
	$GLOBALS['uid'] = (int) $uid;
	ob_start();
	WPCPM_Institution_Invite::render_pending( $record );
	return (string) ob_get_clean();
}

$form = render_form_as( 7, $A );

ck( 'the member gets a form', array( has( $form, 'name="action" value="wpcpm_invite_member"' ), has( $form, 'name="email"' ) ), array( true, true ) );
ck( 'with the nonce keyed to the institution', has( $form, 'value="nonce-wpcpm_invite_member_' . $A . '"' ), true );
ck( 'and the record it is for', has( $form, 'name="record" value="' . $A . '"' ), true );
ck( 'it says how long an invitation lasts and how many may wait', array( has( $form, '14 days' ), has( $form, '10 invitations' ) ), array( true, true ) );
ck( 'no origin flag on the dashboard', has( $form, 'wpcpm_from' ), false );
ck( 'and one on the manager screen', has( render_form_as( 1, $A, 'admin' ), 'name="wpcpm_from" value="admin"' ), true );
ck( 'a member of another institution is drawn nothing at all', render_form_as( 9, $A ), '' );
ck( 'and a record that is not one draws nothing', render_form_as( 7, 'not-a-record' ), '' );

// The gate is the policy's, and it closes this card with everything else.
$GLOBALS['settled'] = array( $B );
ck( 'an institution whose agreement is not settled cannot invite anybody', render_form_as( 7, $A ), '' );
$GLOBALS['settled'] = array( $A, $B );

ck( 'nothing is drawn when nothing is waiting', render_pending_as( 7, $A ), '' );

seed_invite( $A, 'waiting@example.test', 7, time() );
$list = render_pending_as( 7, $A );

ck( 'a waiting invitation is listed with its address', has( $list, 'waiting@example.test' ), true );
ck( 'with Cancel and Resend, each keyed to the invitation', array(
	has( $list, 'value="wpcpm_cancel_invite"' ),
	has( $list, 'value="wpcpm_resend_invite"' ),
	has( $list, 'nonce-wpcpm_cancel_invite_' . newest_invite() ),
), array( true, true, true ) );
ck( 'no token is ever rendered', has( $list, 'inv_token' ), false );
ck( 'and a stranger is drawn nothing', render_pending_as( 9, $A ), '' );

echo "\n=== The outcome is printed on the card it happened to ===\n";

WPCPM_Flash::set( WPCPM_Institution_Invite::FLASH, array( 'status' => 'invite-sent', 'detail' => 'colleague@example.test', 'record' => $A ), 20 );
$GLOBALS['uid'] = 20;

ob_start();
WPCPM_Institution_Invite::render_message( $B );
$elsewhere = (string) ob_get_clean();

ob_start();
WPCPM_Institution_Invite::render_message( $A );
$here = (string) ob_get_clean();

ck( 'another institution\'s card prints nothing', $elsewhere, '' );
ck( 'and this one prints the message with the address', array( has( $here, 'on its way' ), has( $here, 'colleague@example.test' ) ), array( true, true ) );

/* ---- the shape of the file ------------------------------------------------ */

echo "\n=== The handlers, the hooks and the order ===\n";

$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-invite.php' );

$GLOBALS['hooks'] = array();
WPCPM_Institution_Invite::init();

ck( 'the four handlers, the accept arm for a signed-out visitor, and the cron', $GLOBALS['hooks'], array(
	'init',
	'admin_post_wpcpm_invite_member',
	'admin_post_wpcpm_accept_invite',
	'admin_post_nopriv_wpcpm_accept_invite',
	'admin_post_wpcpm_cancel_invite',
	'admin_post_wpcpm_resend_invite',
	'wpcpm_invite_expire',
) );

$init = method_body( $src, 'init' );

ck( 'accept is the only action a signed-out request may reach', array(
	substr_count( $init, "'admin_post_nopriv_'" ),
	has( $init, "'admin_post_nopriv_' . self::ACTION_ACCEPT" ),
), array( 1, true ) );
ck( 'the cron is hooked here and not scheduled here', array( has( $init, 'self::CRON_EXPIRE' ), has( $src, 'wp_schedule_event' ) ), array( true, false ) );

$invite = method_body( $src, 'handle_invite' );

ck( 'the invite handler checks the nonce before it decides', before( $invite, 'check_admin_referer', 'WPCPM_Institution_Policy::decide' ), true );
ck( 'and decides before it claims a send', before( $invite, 'WPCPM_Institution_Policy::decide', 'WPCPM_Ceiling::claim' ), true );
ck( 'and claims a send before it writes or mails anything', array(
	before( $invite, 'WPCPM_Ceiling::claim', 'self::create(' ),
	before( $invite, 'WPCPM_Ceiling::claim', 'self::mail_invite(' ),
), array( true, true ) );

$shared = method_body( $src, 'decide_on_invite' );

ck( 'cancel and resend share one nonce-then-decide, keyed to the invitation', array(
	before( $shared, 'check_admin_referer', 'WPCPM_Institution_Policy::decide' ),
	has( $shared, 'subject_post( $post, self::META_INSTITUTION )' ),
), array( true, true ) );
ck( 'and neither reads the institution off the form', preg_match( '/posted_text\(\s*\'record\'/', $shared ), 0 );

$route   = (string) method_body( $src, 'handle_accept' );
$asks    = (string) method_body( $src, 'accept_page' ) . (string) method_body( $src, 'confirm_document' );
$accepts = (string) method_body( $src, 'accept_invitation' );

ck( 'the handler sends a POST to the acceptance and everything else to the page', array(
	before( $route, 'self::is_post()', 'self::accept_invitation()' ),
	before( $route, 'self::accept_invitation()', 'self::accept_page()' ),
), array( true, true ) );
ck( 'the page the link lands on writes nothing at all', array(
	has( $asks, 'attach(' ),
	has( $asks, 'account_for(' ),
	has( $asks, 'settle(' ),
	has( $asks, 'update_post_meta(' ),
	has( $asks, 'wp_insert_post(' ),
	has( $asks, 'wp_insert_user(' ),
), array( false, false, false, false, false, false ) );
ck( 'and the state change has exactly one call site, on the POST arm', array(
	substr_count( $src, 'self::complete_accept(' ),
	has( $accepts, 'self::complete_accept(' ),
), array( 1, true ) );
ck( 'the acceptance checks its nonce before it looks a token up', before( $accepts, 'self::nonce_ok(', 'self::invitation_for(' ), true );
ck( 'nothing on the accept path reads an institution off the request', preg_match(
	'/(posted_text|posted_key|posted_id|text|key|id)\(\s*\'record\'/',
	$route . $asks . $accepts . (string) method_body( $src, 'complete_accept' )
), 0 );
ck( 'no institution ID is compared with === in the class', preg_match( '/wpcpm_(student_)?institution(_record_id)?[^;]*===/', $src ), 0 );
ck( 'the refusal on the accept path is written once', substr_count( $src, 'That invitation cannot be used' ), 1 );

$dashes = array();

foreach ( array(
	'includes/modules/class-wpcpm-institution-invite.php',
	'bin/test-institution-invite.php',
) as $rel ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $rel ) ) ) {
		$dashes[] = $rel;
	}
}

ck( 'no dash but the plain hyphen in either file', $dashes, array() );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
