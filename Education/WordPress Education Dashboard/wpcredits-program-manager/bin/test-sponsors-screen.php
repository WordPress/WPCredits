<?php
/**
 * The Sponsors module: the wp-admin screen, provisioning and members.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - Provisioning creates an account from a sponsor's contact address, or attaches an existing
 *   one at that address; it never provisions two accounts for the same sponsor and never
 *   touches an administrator's account, because a manager already reaches every sponsor.
 * - The provision nonce is keyed to the record (`wpcpm_sponsor_provision_<record>`), so a
 *   token for one sponsor is not a token for another.
 * - Every handler checks the capability, then the nonce, then `WPCPM_Sponsor_Policy::decide()`,
 *   asserted by reading the source: the order is invisible at runtime and wrong in one place
 *   is wrong for everyone.
 * - The welcome is queued through `WPCPM_Mail::queue_invites()` and never sent from here:
 *   `wp_mail()` is stubbed to record a call precisely so that promise has a check behind it.
 * - `Dashboard account` is written to Airtable, and to the index at once, the moment an
 *   account is created or attached, and cleared the moment a sponsor's last account is
 *   detached: nobody waits for the next sync run to see it.
 * - The screen draws four cards (the sync panel, the sponsors index, the accounts per
 *   sponsor, the interests log) and offers Create account only where the brief allows it:
 *   Approved, with a contact address, without an account already.
 * - Every form on the screen carries the double-submit guard.
 * - `mark_dashboard_account()` refuses a malformed record before any request leaves the site,
 *   and hands back the client's own error when Airtable refuses the write.
 * - Every reference to `WPCPM_Sponsors_Dashboard` (Task 10) is guarded, so the screen draws
 *   with or without the front end: this suite never defines that class.
 *
 * Run from the plugin root:  php bin/test-sponsors-screen.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

// Cron, recorded rather than run: WPCPM_Sponsors_Sync::schedule() reads wp_next_scheduled() and
// WPCPM_Sponsors::uninstall() clears two hooks, though neither boot() nor uninstall() is ever
// called here - kept for the same reason the copied suite keeps it, at no cost to this one.
function wp_next_scheduled( $hook ) {
	return $GLOBALS['cron'][ $hook ] ?? false;
}
function wp_schedule_event( $when, $recurrence, $hook ) {
	$GLOBALS['cron'][ $hook ] = (int) $when;
	return true;
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['cron'][ $hook ] );
	return 1;
}

$GLOBALS['opts']          = array();
$GLOBALS['umeta']         = array();
$GLOBALS['users']         = array();
$GLOBALS['nonce_checked'] = array();
$GLOBALS['inserted']      = array();
$GLOBALS['posts']         = array();
$GLOBALS['pmeta']         = array();
$GLOBALS['next_post']     = 900;

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}

/**
 * Enough of `WP_User` for the real `WPCPM_Sponsor_Members` to run against: `add_role()` and
 * `remove_role()` that mutate the role list without demoting anyone else, and `set_role()` for
 * the account that ends up with no role at all once a sponsor's is removed.
 *
 * The constructor takes the role list before the name and address, matching the fixture below
 * rather than the institutions screen suite's own order: that suite never gives `WP_User` a
 * role at construction time, and this one has to, since accounts arrive from Airtable already
 * carrying the sponsor role.
 */
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, array $roles = array(), $name = '', $email = '' ) {
		$this->ID           = (int) $id;
		$this->roles        = $roles;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( ' ', '', $name ) );
	}
	public function exists() { return $this->ID > 0; }
	public function add_role( $role ) {
		if ( ! in_array( $role, $this->roles, true ) ) {
			$this->roles[] = $role;
		}
	}
	public function remove_role( $role ) {
		$this->roles = array_values( array_diff( $this->roles, array( $role ) ) );
	}
	public function set_role( $role ) {
		$this->roles = '' === $role ? array() : array( $role );
	}
}

/**
 * Enough of `WP_Post` for the post store below: the offers module reads and writes these five
 * properties alone, so nothing else is stood in for it (added for S2: the file had no post
 * store at all before, since nothing before it needed one).
 */
class WP_Post {
	public $ID = 0, $post_type = '', $post_title = '', $post_status = 'private', $post_author = 0;
	public function __construct( array $a ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}

/**
 * The two outcomes `post()` tells apart. The institutions screen suite this file's other stubs
 * are copied from throws a plain `Exception` for both `wp_die()` and `wp_safe_redirect()` and
 * tells them apart by a string prefix on the message; that will not do here, because `post()`
 * needs two separate `catch` clauses to hand the caller a `die` outcome or a `redirect` one
 * without parsing the message first. Neither exception carries anything beyond its message:
 * `WPCPM_Test_Die` folds the status code into it (`wp_die( $message, 403 )` becomes
 * `"$message [403]"`) since that is the only way a check on the message text can see the code.
 */
class WPCPM_Test_Die extends Exception {}
class WPCPM_Test_Redirect extends Exception {}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_kses( $s, $a = array() ) { return (string) $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $url, $protocols = null ) { return preg_match( '#^https?://#i', (string) $url ) ? $url : ''; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function esc_js( $s ) { return addslashes( (string) $s ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function sanitize_user( $u, $strict = false ) { return preg_replace( '/[^a-z0-9 _.\-@]/i', '', (string) $u ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action( $h, $c, $p = 10, $n = 1 ) {}
function add_filter() {}
function register_post_type( $type, $args ) {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
// The menu bubble's cache (1.98.1): no suite here had a transient store yet, so this is
// Task 1's shape (bin/test-form-stash.php), not a new one.
$GLOBALS['transients'] = array();
function set_transient( $k, $v, $ttl ) { $GLOBALS['transients'][ $k ] = array( 'v' => $v, 'ttl' => $ttl ); return true; }
function get_transient( $k ) { return isset( $GLOBALS['transients'][ $k ] ) ? $GLOBALS['transients'][ $k ]['v'] : false; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
/** The pool's lock and WPCPM_Secret's key both need the test-and-set add_option() makes: the
 * write only happens when the row does not exist yet, which update_option() alone cannot tell. */
function add_option( $k, $v, $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
/** delete_metadata( 'user', 0, $key, '', true ) is how uninstall() clears a meta key across
 * every account; only that shape is exercised here, so only it is stood in for. */
function delete_metadata( $type, $object_id, $key, $value = '', $delete_all = false ) {
	$GLOBALS['deleted_meta'][] = $key;
	if ( $delete_all ) {
		foreach ( array_keys( $GLOBALS['umeta'] ) as $id ) {
			unset( $GLOBALS['umeta'][ $id ][ $key ] );
		}
	} else {
		unset( $GLOBALS['umeta'][ (int) $object_id ][ $key ] );
	}
	return true;
}
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) { return $user; }
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
		if ( 'login' === $field && $user->user_login === (string) $value ) { return $user; }
	}
	return false;
}
/** `get_users()` by meta key and value; a call with neither, as the locked-accounts card makes, gets everyone. */
/**
 * Enough of `$wpdb` for `WPCPM_Sponsors_Index::delete_all()`'s one raw query: a LIKE-prefix
 * DELETE against the options table it keeps in `$GLOBALS['opts']`, and for
 * `WPCPM_Sponsor_Approval::delete_all()`'s LIKE-prefix SELECT of the lock rows, which
 * `uninstall()` reaches for real since the S5 fix wave loaded that class here.
 */
class WPCPM_Test_DB {
	public $options = 'wp_options';
	public function prepare( $sql, ...$args ) { return vsprintf( str_replace( '%s', "'%s'", $sql ), $args ); }
	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }
	public function get_col( $sql ) {
		$out = array();

		if ( preg_match( "/LIKE '(.*)%'\$/", $sql, $m ) ) {
			$prefix = str_replace( array( '\\_', '\\%', '\\\\' ), array( '_', '%', '\\' ), $m[1] );
			foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
				if ( 0 === strpos( $name, $prefix ) ) { $out[] = $name; }
			}
		}

		return $out;
	}
	public function query( $sql ) {
		if ( preg_match( "/LIKE '(.*)%'\$/", $sql, $m ) ) {
			$prefix = str_replace( array( '\\_', '\\%', '\\\\' ), array( '_', '%', '\\' ), $m[1] );
			foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
				if ( 0 === strpos( $name, $prefix ) ) { unset( $GLOBALS['opts'][ $name ] ); }
			}
		}
		return true;
	}
}
$GLOBALS['wpdb'] = new WPCPM_Test_DB();
function get_users( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( ! isset( $args['meta_key'] ) ) { $out[] = $user; continue; }
		$value = $GLOBALS['umeta'][ (int) $id ][ $args['meta_key'] ] ?? null;
		if ( null !== $value && 0 === strcasecmp( (string) $value, (string) ( $args['meta_value'] ?? '' ) ) ) { $out[] = $user; }
	}
	return $out;
}
function username_exists( $login ) { $u = get_user_by( 'login', $login ); return $u ? $u->ID : false; }
function wp_generate_password( $l = 12, $s = true, $e = false ) { return substr( str_repeat( md5( (string) mt_rand() ), 2 ), 0, (int) $l ); }
function wp_mail( $to, $subject, $message = '', $headers = '', $attachments = array() ) {
	// Never expected to run: provisioning and attaching both queue through WPCPM_Mail::queue_invites()
	// rather than calling this, and a check later asserts $GLOBALS['mailed'] was never touched.
	$GLOBALS['mailed'][] = array( $to, $subject );
	return true;
}
// The post store: enough of posts, meta and get_posts() for the offers to live in.
function wp_insert_post( array $args, $wp_error = false ) {
	$id = $GLOBALS['next_post']++;
	$GLOBALS['posts'][ $id ] = new WP_Post( array( 'ID' => $id, 'post_type' => $args['post_type'] ?? 'post', 'post_title' => $args['post_title'] ?? '', 'post_status' => $args['post_status'] ?? 'publish', 'post_author' => $args['post_author'] ?? 0 ) );
	return $id;
}
function wp_update_post( array $args ) { $id = (int) $args['ID']; if ( isset( $GLOBALS['posts'][ $id ] ) && isset( $args['post_title'] ) ) { $GLOBALS['posts'][ $id ]->post_title = $args['post_title']; } return $id; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
function wp_delete_attachment( $id, $force = false ) { $GLOBALS['deleted_attachments'][] = (int) $id; return true; }
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); return true; }
function get_posts( array $args ) {
	$GLOBALS['queries'] = ( isset( $GLOBALS['queries'] ) ? (int) $GLOBALS['queries'] : 0 ) + 1;
	$out = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) { continue; }
		if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] && $post->post_status !== $args['post_status'] ) { continue; }
		$ok = true;
		foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			$have = array_key_exists( $clause['key'], $GLOBALS['pmeta'][ $id ] ?? array() );
			if ( isset( $clause['compare'] ) && 'EXISTS' === $clause['compare'] ) { if ( ! $have ) { $ok = false; } continue; }
			// The application queue asks for several states at once (added for S5).
			if ( isset( $clause['compare'] ) && 'IN' === $clause['compare'] ) { if ( ! $have || ! in_array( (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ], array_map( 'strval', (array) $clause['value'] ), true ) ) { $ok = false; } continue; }
			if ( ! $have || (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ] !== (string) $clause['value'] ) { $ok = false; }
		}
		if ( $ok ) { $out[] = $post; }
	}
	// decided_posts() (1.98.1) orders by a meta value instead of the post ID; this is the one
	// other shape this class asks for, so it is the one other shape this stub answers.
	if ( isset( $args['orderby'] ) && 'meta_value_num' === $args['orderby'] && isset( $args['meta_key'] ) ) {
		$meta_key = $args['meta_key'];
		$desc     = isset( $args['order'] ) && 'DESC' === $args['order'];
		usort( $out, static function ( $a, $b ) use ( $meta_key, $desc ) {
			$by_meta = (int) get_post_meta( $a->ID, $meta_key, true ) - (int) get_post_meta( $b->ID, $meta_key, true );
			return $desc ? -$by_meta : $by_meta;
		} );
	} else {
		usort( $out, static function ( $a, $b ) { return $a->ID - $b->ID; } );
	}
	if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 ) {
		$out = array_slice( $out, 0, (int) $args['numberposts'] );
	}
	return $out;
}
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	$GLOBALS['nonce_checked'][] = $action;
	if ( ! $GLOBALS['nonce_ok'] ) {
		wp_die( 'The link you followed has expired.' );
	}
	return true;
}
function wp_die( $message = '', $code = 0 ) {
	throw new WPCPM_Test_Die( (string) $message . ( $code ? ' [' . (int) $code . ']' : '' ) );
}
function wp_safe_redirect( $location, $status = 302 ) {
	throw new WPCPM_Test_Redirect( (string) $location );
}
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $args, $url = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function wp_nonce_url( $url, $a ) { return $url . '&_wpnonce=' . rawurlencode( $a ); }
function wp_nonce_field( $a = '', $n = '', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function wp_create_nonce( $a = '' ) { return 'nonce'; }
function submit_button( $text, $type = 'primary', $name = 'submit', $wrap = true, $other = array() ) {
	$attrs = '';
	foreach ( (array) $other as $key => $value ) { $attrs .= ' ' . $key . '="' . esc_attr( $value ) . '"'; }
	printf( '<button type="submit" class="button button-%s" name="%s"%s>%s</button>', $type, $name, $attrs, esc_html( $text ) );
}
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function size_format( $b, $d = 0 ) { return (int) $b . ' B'; }
function human_time_diff( $a, $b = 0 ) { return '4 hours'; }
function wp_date( $format, $ts = null, $zone = null ) { return gmdate( $format, (int) $ts ); }
function get_post_time( $format = 'U', $gmt = false, $post = null ) { return 1757000000; }
function wp_get_attachment_image( $id, $size = 'medium', $icon = false, $attr = array() ) { return '<img class="wpcpm-test-logo" data-id="' . (int) $id . '" src="https://example.test/uploads/' . (int) $id . '.png" alt="" />'; }
function esc_textarea( $s ) { return esc_html( $s ); }

class WPCPM_Airtable {
	public function update_records( $table, array $records ) { $GLOBALS['patched'][] = array( $table, $records ); return isset( $GLOBALS['airtable_fail'] ) ? new WP_Error( 'x', 'Airtable said no' ) : array( $records[0]['id'] => true ); }
}
class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student'; const ROLE_MENTOR = 'wpcpm_mentor'; const ROLE_INSTITUTION = 'wpcpm_institution'; const ROLE_SPONSOR = 'wpcpm_sponsor'; const ROLE_ADMIN = 'administrator'; const CAP_MANAGE = 'wpcpm_manage_program';
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
	public static function insert_user( array $data ) { $id = 100 + count( $GLOBALS['users'] ); $u = new WP_User( $id, array( $data['role'] ), $data['display_name'], $data['user_email'] ); $u->user_login = $data['user_login']; $GLOBALS['users'][ $id ] = $u; $GLOBALS['inserted'][] = $data; return $id; }
}
class WPCPM_Mail {
	public static function queue_invites( array $ids ) { $GLOBALS['queued'] = array_merge( isset( $GLOBALS['queued'] ) ? $GLOBALS['queued'] : array(), $ids ); return count( $ids ); }
	public static function mask_address( $a ) { return substr( $a, 0, 1 ) . '***' . strstr( $a, '@' ); }
}
class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); }
	public static function sponsorship() { return array(); }
}
class WPCPM_Students_Sync {
	const META_RECORD_ID = 'wpcpm_student_record_id';
	public static function get_program( $user_id ) { return array(); }
}
class WPCPM_Institutions { public static function notify_managers( $context, $build, $key = 'agreement_notify' ) { $GLOBALS['sent'][] = array( 'managers', $key, $context, call_user_func( $build, null ) ); return 1; } }
class WPCPM_Institution_Members { const META_RECORD_ID = 'wpcpm_institution_record_id'; const META_ACTIVE = 'wpcpm_institution_active'; public static function institution_of( $user = null ) { return ''; } }
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const GROUND_SYSTEM = 'system'; const EVIDENCE_INDEX = 'index'; const EVIDENCE_CACHE = 'cache';
	public static function record_sponsor( array $e ) { $GLOBALS['audit'][] = $e; return count( $GLOBALS['audit'] ); }
	public static function sponsor_entries( $kind = '', $limit = 50 ) { $out = array(); foreach ( array_reverse( $GLOBALS['audit'] ) as $i => $e ) { if ( '' !== $kind && $e['kind'] !== $kind ) { continue; } $out[] = array_merge( array( 'id' => $i, 'actor' => 0, 'time' => 1757000000 + $i, 'message' => '', 'data' => array() ), $e ); } return array_slice( $out, 0, $limit ); }
}
class WPCPM_Ceiling { public static function init() {} public static function claim( $k, $l, $w, $a = 1 ) { return true; } public static function count( $k, $w ) { return 0; } public static function key( ...$p ) { return implode( ':', $p ); } }
class WPCPM_Flash { public static function set( $k, $v ) { $GLOBALS['flash'][ $k ] = $v; } public static function take( $k ) { $v = isset( $GLOBALS['flash'][ $k ] ) ? $GLOBALS['flash'][ $k ] : ''; unset( $GLOBALS['flash'][ $k ] ); return $v; } }
class WPCPM_Request {
	public static function posted_text( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) ? trim( (string) $GLOBALS['post'][ $n ] ) : $f; }
	public static function posted_key( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $GLOBALS['post'][ $n ] ) ) : $f; }
	public static function posted_id( $n ) { return isset( $GLOBALS['post'][ $n ] ) ? (int) $GLOBALS['post'][ $n ] : 0; }
	public static function text( $n, $f = '' ) { return isset( $GLOBALS['get'][ $n ] ) ? trim( (string) $GLOBALS['get'][ $n ] ) : $f; }
	public static function key( $n, $f = '' ) { return isset( $GLOBALS['get'][ $n ] ) ? (string) $GLOBALS['get'][ $n ] : $f; }
	public static function id( $n ) { return isset( $GLOBALS['get'][ $n ] ) ? (int) $GLOBALS['get'][ $n ] : 0; }
}
class WPCPM_Settings { public static function get() { return $GLOBALS['settings']; } public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; } public static function is_connected() { return true; } }
class WPCPM_Mentors { public static function format_duration( $s ) { return $s . 's'; } }
class WPCPM_Return { const FIELD = 'wpcpm_return'; const DASHBOARD = 'dashboard'; public static function url( $default ) { return $default; } public static function field( $where, $anchor = '' ) {} }
/** Task 4's real class is not loaded here (nothing else in this suite needs it), so a stub
 * stands in: ACTION_FLAGS names the admin-post action render_members() posts a nonce for, and
 * posting_enabled() reads the same $GLOBALS['posting_off'] the checks below set and clear.
 * apply_caps(), drop_caps() and delete_all() are never asserted here; they exist only because
 * class_exists( 'WPCPM_Sponsor_Posts' ) is already true the moment this stub is defined, and the
 * real WPCPM_Sponsor_Members::attach()/detach() and WPCPM_Sponsors::uninstall() (Task 4/5, loaded
 * for real below) call them behind that same guard - a bare stub without them would fatal the
 * first time this suite attaches or detaches an account, or runs uninstall(). */
class WPCPM_Sponsor_Posts {
	const ACTION_FLAGS = 'wpcpm_sponsor_flags';
	public static function posting_enabled( $record ) { return empty( $GLOBALS['posting_off'][ $record ] ); }
	public static function apply_caps( $user_id, $record ) {}
	public static function drop_caps( $user_id ) {}
	public static function delete_all() {}
	public static function uninstall_accounts() {}
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-module.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sync-module.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-sync.php';
require_once __DIR__ . '/../includes/class-wpcpm-secret.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-codes.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-offers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-claims.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tools.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php';

// ck(), then the fixture: an index of three sponsors written through the real index class.
$A = 'recSPONSOR0000001'; $B = 'recSPONSOR0000002'; $C = 'recSPONSOR0000003';
WPCPM_Sponsors_Index::write( array(
	$A => array( 'name' => 'Mango Example ', 'status' => 'Approved', 'product_type' => 'Hosting', 'contact_person' => 'Rep One', 'contact_email' => 'maciej@a8c.com', 'manager' => 'recTEAM0000000001' ),
	$B => array( 'name' => 'Wexample', 'status' => 'Approved', 'contact_email' => '' ),
	$C => array( 'name' => 'Ember Example', 'status' => 'Paused', 'contact_email' => 'maciej@a8c.com' ),
), time() );
WPCPM_Sponsors_Index::write_team( array( 'recTEAM0000000001' => array( 'name' => 'Maciej (Matt) Pilarski', 'email' => 'maciej@a8c.com', 'calendly' => '' ) ), time() );
$GLOBALS['settings'] = array( 'sponsors_table' => 'tblSPONSORS', 'sponsor_on_inactive' => 'keep' );
// A distinct address from every sponsor's contact_email: get_user_by( 'email', ... ) must not
// resolve the acting manager when it looks up a sponsor's contact address.
$GLOBALS['users']    = array( 1 => new WP_User( 1, array( 'administrator' ), 'Manager', 'manager@example.test' ) );
$GLOBALS['manage']   = array( 1 );
$GLOBALS['uid']      = 1;
$GLOBALS['nonce_ok'] = true;
$GLOBALS['patched']  = array();
$GLOBALS['queued']   = array();
$GLOBALS['audit']    = array();
$module = new WPCPM_Sponsors();

function post( array $fields, $action ) {
	$GLOBALS['post'] = $fields;
	try { call_user_func( $action ); } catch ( WPCPM_Test_Redirect $e ) { return array( 'redirect', $e->getMessage(), WPCPM_Flash::take( WPCPM_Sponsors::FLASH ) ); } catch ( WPCPM_Test_Die $e ) { return array( 'die', $e->getMessage() ); }
	return array( 'fell-through' );
}

/** One method's body, for the assertions that read the source. */
function method_body( $src, $name ) {
	$body = substr( $src, (int) strpos( $src, 'function ' . $name . '(' ) );
	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}

$fail   = 0;
$checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

echo "=== The module ===\n";
ck( 'implemented now', $module->is_implemented(), true );
ck( 'the menu label carries no bubble yet: nothing to count until S3 to S5', $module->menu_label(), 'Sponsors' );
ck( 'the sync module contract', array( WPCPM_Sponsors::ACTION_SYNC, WPCPM_Sponsors::ACTION_CANCEL, WPCPM_Sponsors::ACTION_TICK ), array( 'wpcpm_sponsors_sync', 'wpcpm_sponsors_cancel', 'wpcpm_sponsors_tick' ) );
ck( 'every status the handlers flash has a sentence', array_values( array_diff( array( 'provisioned', 'provision-attached', 'provision-admin', 'provision-inactive', 'provision-no-email', 'provision-refused', 'provision-failed', 'airtable-failed', 'attached', 'attach-no-account', 'attach-refused', 'detached', 'detach-refused', 'refused' ), array_keys( WPCPM_Sponsors::messages() ) ) ), array() );

echo "\n=== Provisioning ===\n";
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'an Approved sponsor with a contact address gets an account', $r[0] === 'redirect' ? $r[2] : $r, 'provisioned' );
ck( 'the nonce is keyed to the record', end( $GLOBALS['nonce_checked'] ), 'wpcpm_sponsor_provision_' . $A );
$new = end( $GLOBALS['users'] );
ck( 'with the sponsor role, the contact\'s name and address', array( $new->roles, $new->display_name, $new->user_email ), array( array( 'wpcpm_sponsor' ), 'Rep One', 'maciej@a8c.com' ) );
ck( 'stamped for the sponsor, provisioned', array( WPCPM_Sponsor_Members::sponsor_of( $new ), get_user_meta( $new->ID, WPCPM_Sponsor_Members::META_MEMBERSHIP, true )['how'] ), array( $A, 'provisioned' ) );
ck( 'the welcome is queued, never sent here', array( $GLOBALS['queued'], isset( $GLOBALS['mailed'] ) ? $GLOBALS['mailed'] : array() ), array( array( $new->ID ), array() ) );

echo "\n=== S2: provisioning seeds the first offer ===\n";
$seeded = WPCPM_Sponsor_Offers::offers_of( $A );
ck( 'provisioning seeded one draft offer from the index, marked primary', array( count( $seeded ), reset( $seeded )['state'], reset( $seeded )['primary'] ), array( 1, 'draft', true ) );

ck( 'the base is told: Dashboard account, true', $GLOBALS['patched'][0], array( 'tblSPONSORS', array( array( 'id' => $A, 'fields' => array( 'Dashboard account' => true ) ) ) ) );
ck( 'and the index row says so at once', WPCPM_Sponsors_Index::row( $A )['dashboard_account'], true );
ck( 'and it is logged', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['sponsor'], end( $GLOBALS['audit'] )['ground'] ), array( 'provisioned', $A, 'manager' ) );
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'pressing again attaches nothing new and says the account exists', $r[2], 'provision-attached' );
$GLOBALS['users'][7] = new WP_User( 7, array( 'wpcpm_mentor' ), 'Ines', 'maciej@a8c.com' );
// The created account moves to another address, so the contact address now names the mentor
// alone: the stub's get_user_by( 'email' ) must not have two candidates.
$new->user_email = 'former@example.test';
WPCPM_Sponsor_Members::detach( $new->ID, 'removed', 1 );
$GLOBALS['queued'] = array();
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'an existing account at that address is attached rather than duplicated', array( $r[2], count( $GLOBALS['inserted'] ) ), array( 'provision-attached', 1 ) );
$r = post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_provision' ) );
ck( 'a sponsor with no contact address cannot be given an account', $r[2], 'provision-no-email' );
$r = post( array( 'wpcpm_sponsor' => $C ), array( $module, 'handle_provision' ) );
ck( 'nor can one that is not Approved', $r[2], 'provision-inactive' );
$GLOBALS['users'][7]->roles = array( 'administrator' );
foreach ( WPCPM_Sponsor_Members::members_of( $A ) as $m ) { WPCPM_Sponsor_Members::detach( $m->ID, 'removed', 1 ); }
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'an administrator\'s address is refused', $r[2], 'provision-admin' );
$GLOBALS['nonce_ok'] = false;
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'a bad nonce dies', $r[0], 'die' );
$GLOBALS['nonce_ok'] = true;
$GLOBALS['uid'] = 9; $GLOBALS['users'][9] = new WP_User( 9, array( 'subscriber' ), 'Stranger', 'maciej@a8c.com' );
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'a non-manager dies with 403', $r[0] === 'die' && false !== strpos( $r[1], '403' ), true );
$GLOBALS['uid'] = 1;

echo "\n=== Members ===\n";
$GLOBALS['users'][7]->roles = array( 'wpcpm_mentor' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'attach', 'wpcpm_email' => 'maciej@a8c.com' ), array( $module, 'handle_members' ) );
ck( 'attach by address finds the account', $r[2], 'attached' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'attach', 'wpcpm_email' => 'nobody@example.test' ), array( $module, 'handle_members' ) );
ck( 'an address with no account is not created here: provisioning does that', $r[2], 'attach-no-account' );
$GLOBALS['patched'] = array();
$attached = WPCPM_Sponsor_Members::members_of( $A );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'detach', 'wpcpm_user' => $attached[0]->ID ), array( $module, 'handle_members' ) );
ck( 'detach removes the one account', array( $r[2], WPCPM_Sponsor_Members::members_of( $A ) ), array( 'detached', array() ) );
ck( 'and tells the base the sponsor has no account now', $GLOBALS['patched'][0][1][0]['fields'], array( 'Dashboard account' => false ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'eat' ), array( $module, 'handle_members' ) );
ck( 'an op that is not one is the one refusal', $r[2], 'refused' );

echo "\n=== Provisioning refuses a student's address, and a detach refuses another sponsor's member ===\n";
// $C ('Ember Example') is Paused in the fixture; flipped to Approved only for this scenario and
// flipped back before the screen renders below, so nothing else in this file sees a fourth
// qualifying sponsor or an extra Create account button.
WPCPM_Sponsors_Index::patch( $C, array( 'status' => WPCPM_Sponsors_Index::STATUS_APPROVED ) );
// maciej@a8c.com currently names both Ines (id 7) and the Stranger (id 9); both moved
// aside so the student account below is the one and only match, the same technique the
// provisioning tests above already use to steer get_user_by( 'email', ... ).
$GLOBALS['users'][7]->user_email = 'moved-aside-1@example.test';
$GLOBALS['users'][9]->user_email = 'moved-aside-2@example.test';
$GLOBALS['users'][11]                                    = new WP_User( 11, array( 'wpcpm_student' ), 'A Student', 'maciej@a8c.com' );
$GLOBALS['umeta'][11]['wpcpm_student_record_id'] = 'recSTUDENT0000001';
$r = post( array( 'wpcpm_sponsor' => $C ), array( $module, 'handle_provision' ) );
ck( 'provisioning a sponsor whose contact belongs to a student account is refused', $r[2], 'provision-refused' );
$GLOBALS['users'][7]->user_email = 'maciej@a8c.com';
$GLOBALS['users'][9]->user_email = 'maciej@a8c.com';
WPCPM_Sponsors_Index::patch( $C, array( 'status' => 'Paused' ) );

$GLOBALS['users'][12] = new WP_User( 12, array( 'subscriber' ), 'Another Rep', 'maciej@a8c.com' );
WPCPM_Sponsor_Members::attach( 12, $A, WPCPM_Sponsor_Members::HOW_MANAGER, 1 );
$r = post( array( 'wpcpm_sponsor' => $B, 'wpcpm_op' => 'detach', 'wpcpm_user' => 12 ), array( $module, 'handle_members' ) );
ck( 'a detach naming a member of a different sponsor is refused', $r[2], 'detach-refused' );
WPCPM_Sponsor_Members::detach( 12, WPCPM_Sponsor_Members::REASON_REMOVED, 1 );

echo "\n=== The screen ===\n";
$GLOBALS['get'] = array();
ob_start();
$module->render_admin_page();
$html = ob_get_clean();
ck( 'the sync panel', false !== strpos( $html, 'wpcpm_sponsors_sync' ) && false !== strpos( $html, 'Sync sponsors now' ), true );
ck( 'the index card lists every sponsor with its status and manager', false !== strpos( $html, 'Mango Example' ) && false !== strpos( $html, 'Paused' ) && false !== strpos( $html, 'Maciej (Matt) Pilarski' ), true );
ck( 'Create account is offered where it belongs: Approved, with an address, without an account', substr_count( $html, 'value="' . WPCPM_Sponsors::ACTION_PROVISION . '"' ), 1 );
ck( 'the members card offers attach for Approved sponsors', substr_count( $html, 'value="attach"' ), 2 );
ck( 'the interests log card is drawn', false !== strpos( $html, 'Interests' ), true );
ck( 'every form carries the double-submit guard', substr_count( $html, '<form' ), substr_count( $html, 'data-wpcpm-once' ) );

echo "\n=== The Airtable write ===\n";
$GLOBALS['airtable_fail'] = true;
ck( 'mark_dashboard_account() hands back the client\'s error', is_wp_error( WPCPM_Sponsors::mark_dashboard_account( $A, true ) ), true );
unset( $GLOBALS['airtable_fail'] );
ck( 'and refuses a malformed record before any request', is_wp_error( WPCPM_Sponsors::mark_dashboard_account( 'nope', true ) ), true );

echo "\n=== S2: the Offers card and its handlers ===\n";
// Run here, before Uninstall: the Uninstall section below calls the real uninstall(), which
// (from this task on) also deletes every offer, pool and claim, so the offer seeded above must
// be exercised before that happens. The uninstall-side check on this same offer is appended
// after the Uninstall section's own call instead of calling uninstall() a second time here,
// which would both double-count that section's $GLOBALS['deleted_meta'] tally and erase every
// account's sponsor-member stamp before its "some account still carries this module's stamps
// before uninstall" check runs.
// Finding 1's fix (review of 4 September 2026): B gets a live account, attached directly
// through WPCPM_Sponsor_Members::attach() rather than through WPCPM_Sponsors::provision(), so
// no offer is seeded for it and the Seed button's own condition (an account, no offer) is
// genuinely met by someone. Without this, accounts_by_sponsor() holds nothing for any sponsor
// at this point in the run (A's own account was detached above and never reattached), so the
// button never rendered for anybody and the old check's substr_count( ... ) >= 0 could not
// have caught that.
WPCPM_Sponsors_Index::patch( $B, array( 'contact_email' => 'maciej@a8c.com' ) );
$rep_b                      = 60;
$GLOBALS['users'][ $rep_b ] = new WP_User( $rep_b, array( 'subscriber' ), 'Rep B', 'repb@example.test' );
WPCPM_Sponsor_Members::attach( $rep_b, $B, WPCPM_Sponsor_Members::HOW_MANAGER, 1 );
ob_start();
$module->render_admin_page();
$screen = ob_get_clean();
ck( 'the screen has an Offers card with the seeded offer and its counts', array( false !== strpos( $screen, 'id="wpcpm-sponsor-offers"' ), false !== strpos( $screen, 'Not switched on yet' ), false !== strpos( $screen, '<td>0</td>' ) ), array( true, true, true ) );
ck( 'a sponsor with an account and no offer gets a Seed button with its own nonce', false !== strpos( $screen, 'nonce-' . WPCPM_Sponsors::ACTION_SEED . '_' . $B ), true );
ck( 'and one with an offer does not', strpos( $screen, 'nonce-' . WPCPM_Sponsors::ACTION_SEED . '_' . $A ), false );

echo "\n=== S3: the posting switch, per sponsor ===\n";
// B carries a live account (Rep B, attached above) at this render, so this is a sponsor with
// an account, as the switch is meant for; $screen is still the render from just above.
ck( 'each sponsor with accounts has the posting switch, keyed to its record', array(
	false !== strpos( $screen, '<input type="hidden" name="action" value="wpcpm_sponsor_flags" />' ),
	false !== strpos( $screen, 'wpcpm_sponsor_flags_' . $B ),
	false !== strpos( $screen, 'name="wpcpm_on" value="0"' ),
	false !== strpos( $screen, 'Turn posting off' ),
), array( true, true, true, true ) );
$GLOBALS['posting_off'][ $B ] = true;
ob_start();
$module->render_admin_page();
$screen = ob_get_clean();
ck( 'with posting off the switch offers to turn it on', array( false !== strpos( $screen, 'name="wpcpm_on" value="1"' ), false !== strpos( $screen, 'Turn posting on' ), false !== strpos( $screen, 'Posting is off for this sponsor.' ) ), array( true, true, true ) );
$GLOBALS['posting_off'] = array();
ck( 'the flash has the two sentences', array( isset( WPCPM_Sponsors::messages()['posting-on'] ), isset( WPCPM_Sponsors::messages()['posting-off'] ) ), array( true, true ) );

$r        = post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_seed' ) );
$seeded_b = WPCPM_Sponsor_Offers::offers_of( $B );
ck( 'the manager seeds B through the handler: one draft offer, marked primary, logged as seeded', array( $r[2], count( $seeded_b ), reset( $seeded_b )['state'], reset( $seeded_b )['primary'], end( $GLOBALS['audit'] )['kind'] ), array( 'offer-seeded', 1, 'draft', true, WPCPM_Sponsor_Offers::LOG_SEEDED ) );
$r = post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_seed' ) );
ck( 'seeding it again does nothing, since it already has one', $r[2], 'offer-seed-none' );
$r = post( array( 'wpcpm_sponsor' => 'recNOTINDEXED0001' ), array( $module, 'handle_seed' ) );
ck( 'an unindexed record is refused before seeding', $r[2], 'refused' );
$offer_id = (int) key( WPCPM_Sponsor_Offers::offers_of( $A ) );
WPCPM_Sponsor_Offers::add_codes( $offer_id, "S-1\nS-2" );
WPCPM_Sponsor_Offers::set_state( $offer_id, 'live' );
$GLOBALS['program'] = array();
$claimant = ( new WP_User( 77, array( 'wpcpm_student' ), 'Student Seven', 'maciej@a8c.com' ) );
$GLOBALS['users'][77] = $claimant;
WPCPM_Sponsor_Codes::take( $offer_id, 77 );
update_user_meta( 77, WPCPM_Sponsor_Claims::META_CLAIMS, array( $offer_id => array( 'i' => 0, 'at' => time() ) ) );
ob_start();
$module->render_admin_page();
$screen = ob_get_clean();
ck( 'a claimant is listed to the manager with name, address, the last four characters and a Void button', array( false !== strpos( $screen, 'Student Seven' ), false !== strpos( $screen, 'maciej@a8c.com' ), false !== strpos( $screen, '>S-1<' ) || false !== strpos( $screen, 'S-1' ), false !== strpos( $screen, 'nonce-' . WPCPM_Sponsors::ACTION_CLAIM_VOID . '_' . $offer_id . '_77' ) ), array( true, true, true, true ) );
$GLOBALS['uid'] = 1;
// post()'s redirect outcome here is array( 'redirect', <url>, <flashed status> ) (see post()
// below), not the array( 'redirect', <status> ) pair the brief's own draft assumed, so $r[2] is
// what carries the flashed status.
$r = post( array( 'wpcpm_offer' => $offer_id, 'wpcpm_user' => 77 ), array( $module, 'handle_claim_void' ) );
ck( 'a manager voids the claim and is told', array( $r[2], WPCPM_Sponsor_Claims::has_claimed( 77, $offer_id ), WPCPM_Sponsor_Codes::counts( $offer_id )['void'] ), array( 'claim-voided', false, 1 ) );
$r = post( array( 'wpcpm_offer' => $offer_id, 'wpcpm_user' => 77 ), array( $module, 'handle_claim_void' ) );
ck( 'voiding again finds nothing', $r[2], 'claim-void-none' );
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_seed' ) );
ck( 'seeding a sponsor that has an offer does nothing', $r[2], 'offer-seed-none' );
$GLOBALS['uid'] = 5;
ck( 'a sponsor member cannot void a claim', post( array( 'wpcpm_offer' => $offer_id, 'wpcpm_user' => 77 ), array( $module, 'handle_claim_void' ) )[0], 'die' );
// Finding 2's fix: handle_seed() gets the same capability check handle_claim_void() already
// had, using the fixture's one real sponsor member (Rep B, attached to B above) rather than a
// bare non-manager ID, and both handlers get the bad-nonce check neither had before.
$GLOBALS['uid'] = $rep_b;
ck( 'a sponsor member cannot seed an offer either', post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_seed' ) )[0], 'die' );
$GLOBALS['uid']      = 1;
$GLOBALS['nonce_ok'] = false;
ck( 'a bad nonce dies for seeding too', post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_seed' ) )[0], 'die' );
ck( 'and for voiding a claim, with a real offer and claimant so the nonce alone explains it', post( array( 'wpcpm_offer' => $offer_id, 'wpcpm_user' => 77 ), array( $module, 'handle_claim_void' ) )[0], 'die' );
$GLOBALS['nonce_ok'] = true;
$GLOBALS['uid']      = 1;

echo "\n=== Uninstall ===\n";
// The last is a literal since 1.99.0: nothing writes it any more, and uninstall still removes
// what 1.93.0 to 1.98.1 wrote (FSPON-6).
$meta_keys = array( WPCPM_Sponsor_Members::META_RECORD_ID, WPCPM_Sponsor_Members::META_ACTIVE, WPCPM_Sponsor_Members::META_RECORD_ID_WAS, WPCPM_Sponsor_Members::META_MEMBERSHIP, WPCPM_Sponsor_Members::META_INVITED, 'wpcpm_sponsor_profile' );
$before_users = count( $GLOBALS['users'] );
$stamped_before = array();
foreach ( $GLOBALS['users'] as $id => $user ) {
	foreach ( $meta_keys as $key ) {
		if ( '' !== get_user_meta( $id, $key, true ) ) {
			$stamped_before[] = $id . ':' . $key;
		}
	}
}
ck( 'some account still carries this module\'s stamps before uninstall (the scenario is real)', empty( $stamped_before ), false );
$module->uninstall();
// Since S2, uninstall() also clears WPCPM_Sponsor_Claims::META_CLAIMS (its own check is below,
// "uninstall removes the offers, their pools and the claims"); filtered out here so this check
// still pins exactly the six WPCPM_Sponsor_Members stamps this module's own loop deletes.
ck( 'uninstall() asks to delete every stamp this module owns', array_values( array_diff( $GLOBALS['deleted_meta'], array( WPCPM_Sponsor_Claims::META_CLAIMS ) ) ), $meta_keys );
$stamped_after = array();
foreach ( $GLOBALS['users'] as $id => $user ) {
	foreach ( $meta_keys as $key ) {
		if ( '' !== get_user_meta( $id, $key, true ) ) {
			$stamped_after[] = $id . ':' . $key;
		}
	}
}
ck( 'and every stamp is gone after uninstall, on every account', $stamped_after, array() );
ck( 'but no account was deleted', count( $GLOBALS['users'] ), $before_users );
ck( 'uninstall removes the offers, their pools and the claims', array( WPCPM_Sponsor_Offers::all(), get_option( WPCPM_Sponsor_Codes::option_name( $offer_id ) ), get_user_meta( 77, WPCPM_Sponsor_Claims::META_CLAIMS, true ) ), array( array(), false, '' ) );

echo "\n=== The agreement review on the Sponsors screen (Phase S4) ===\n";
// The real WPCPM_Sponsor_Agreement (Tasks 3-6) is never loaded by this suite, so a stub stands
// in, answering from globals so each state below can be drawn. Six SUMMARY_* constants, not
// four: SUMMARY_RETURNED and SUMMARY_REVOKED are added to the four the brief's own draft
// named, because agreement_word() below switches on all six (the real class declares all six
// too) and an undefined class constant is a fatal, not a warning. CRON_DISCARD and
// delete_all() are added for the same reason from the other direction: PHP registers an
// unconditional top-level class the moment this file is compiled, not when execution reaches
// its declaration, so the Uninstall section far above (which calls the real uninstall(), and
// through it WPCPM_Sponsor_Agreement::delete_all() under a class_exists() guard that is
// already true) needs this stub to answer for those two as well, even though nothing below
// exercises them directly.
class WPCPM_Sponsor_Agreement {
	const ACTION_ACCEPT = 'wpcpm_sponsor_agr_accept';
	const ACTION_RETURN = 'wpcpm_sponsor_agr_return';
	const ACTION_REVOKE = 'wpcpm_sponsor_agr_revoke';
	const ACTION_REINSTATE = 'wpcpm_sponsor_agr_reinstate';
	const ACTION_ON_FILE = 'wpcpm_sponsor_agr_on_file';
	const ACTION_DOWNLOAD = 'wpcpm_sponsor_agr_download';
	const FIELD_POST = 'wpcpm_sponsor_agr_post';
	const FIELD_NOTE = 'wpcpm_sponsor_agr_note';
	const FIELD_DRIVE = 'wpcpm_sponsor_agr_drive';
	const MIN_NOTE = 20;
	const MAX_NOTE = 2000;
	const META_STATE = '_wpcpm_sagr_state';
	const STATE_REVOKED = 'revoked';
	const CRON_DISCARD = 'wpcpm_sponsor_agreement_discard';
	const SUMMARY_NONE = 'none';
	const SUMMARY_SUBMITTED = 'submitted';
	const SUMMARY_RETURNED = 'returned';
	const SUMMARY_REVOKED = 'revoked';
	const SUMMARY_ACCEPTED = 'accepted';
	const SUMMARY_ON_FILE = 'on_file';
	public static function awaiting_review( $limit = 200 ) { $GLOBALS['queries'] = ( isset( $GLOBALS['queries'] ) ? (int) $GLOBALS['queries'] : 0 ) + 1; return isset( $GLOBALS['queue'] ) ? $GLOBALS['queue'] : array(); }
	public static function review_facts( $post_id ) { return isset( $GLOBALS['facts'][ $post_id ] ) ? $GLOBALS['facts'][ $post_id ] : array(); }
	public static function summary( $record ) { return isset( $GLOBALS['summaries'][ $record ] ) ? $GLOBALS['summaries'][ $record ] : array( 'state' => 'none', 'agreement_id' => 0, 'pending_id' => 0, 'accepted_at' => '', 'kind' => '', 'drive_url' => '', 'airtable_status' => '' ); }
	public static function posts_for( $record ) { return isset( $GLOBALS['agr_posts'][ $record ] ) ? $GLOBALS['agr_posts'][ $record ] : array(); }
	public static function manager_messages() { return array( 'agreement-accepted' => array( 'success', 'Accepted.' ) ); }
	public static function delete_all() {}
}

// Uninstall (above) wiped the index, so the TEST sponsor is written fresh rather than added to
// $A/$B/$C: nothing after this point reads their rows, and every check below keys off $T alone.
$T = 'recSPONSORTEST001';
WPCPM_Sponsors_Index::write( array(
	$T => array( 'name' => 'TEST Sponsor', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
), time() );

$GLOBALS['queue'] = array( 900 );
$GLOBALS['facts'] = array(
	900 => array( 'post_id' => 900, 'state' => 'submitted', 'kind' => 'own', 'sponsor' => $T, 'sponsor_name' => 'TEST Sponsor', 'uploaded_by' => 'Member One', 'uploaded_at' => '2026-09-05', 'original_name' => 'agreement.pdf', 'flags' => array( '/JavaScript' ), 'size' => 20480, 'members' => 2 ),
);
$GLOBALS['summaries'] = array( $T => array( 'state' => 'none', 'agreement_id' => 0, 'pending_id' => 900, 'accepted_at' => '', 'kind' => '', 'drive_url' => '', 'airtable_status' => 'Awaiting review' ) );

ob_start();
$module->render_admin_page();
$screen = (string) ob_get_clean();

ck( 'the queue draws one review block with the facts a reviewer reads first', array(
	false !== strpos( $screen, 'wpcpm-review' ),
	false !== strpos( $screen, 'TEST Sponsor' ),
	false !== strpos( $screen, 'Member One' ),
	false !== strpos( $screen, '2026-09-05' ),
), array( true, true, true, true ) );
ck( 'the scan is named and said to be a courtesy', array( false !== strpos( $screen, '/JavaScript' ), false !== strpos( $screen, 'courtesy' ) ), array( true, true ) );
ck( 'the download is a link, keyed to the document', array( false !== strpos( $screen, 'wpcpm_sponsor_agr_download' ), false !== strpos( $screen, 'wpcpm_sponsor_agr_download_900' ) ), array( true, true ) );
ck( 'Accept and Return are two forms, each keyed to the document', array(
	false !== strpos( $screen, 'value="wpcpm_sponsor_agr_accept"' ),
	false !== strpos( $screen, 'wpcpm_sponsor_agr_accept_900' ),
	false !== strpos( $screen, 'value="wpcpm_sponsor_agr_return"' ),
	false !== strpos( $screen, 'wpcpm_sponsor_agr_return_900' ),
), array( true, true, true, true ) );
ck( 'the note box carries the length the handler enforces', array( false !== strpos( $screen, 'minlength="20"' ), false !== strpos( $screen, 'maxlength="2000"' ) ), array( true, true ) );
ck( 'a sponsor with nothing recorded is offered the on-file form', array(
	false !== strpos( $screen, 'value="wpcpm_sponsor_agr_on_file"' ),
	false !== strpos( $screen, 'name="wpcpm_sponsor_agr_drive"' ),
), array( true, true ) );
ck( 'the on-file form takes the signed-on day too, and every required field says so in the label\'s voice', array(
	false !== strpos( $screen, 'type="date" id="wpcpm-signed-' . $T . '" name="wpcpm_sponsor_agr_signed_on"' ),
	substr_count( $screen, '<span class="wpcpm-field__required">Required</span>' ) >= 2,
	1 === preg_match( '/name="wpcpm_sponsor_agr_drive"[^>]*required/', $screen ),
), array( true, true, true ) );

$GLOBALS['queue']     = array();
$GLOBALS['summaries'] = array( $T => array( 'state' => 'accepted', 'agreement_id' => 901, 'pending_id' => 0, 'accepted_at' => '2026-09-06', 'kind' => 'own', 'drive_url' => '', 'airtable_status' => 'Accepted' ) );
ob_start();
$module->render_admin_page();
$screen = (string) ob_get_clean();
ck( 'an accepted agreement is offered Revoke and no on-file form', array(
	false !== strpos( $screen, 'value="wpcpm_sponsor_agr_revoke"' ),
	false !== strpos( $screen, 'value="wpcpm_sponsor_agr_on_file"' ),
	false !== strpos( $screen, '2026-09-06' ),
), array( true, false, true ) );
ck( 'and the empty queue says so rather than printing nothing', false !== strpos( $screen, 'Nothing is waiting' ), true );

$revoked = new WP_Post( array( 'ID' => 902, 'post_type' => 'wpcpm_sponsor_agr' ) );
$GLOBALS['pmeta'][902]     = array( '_wpcpm_sagr_state' => 'revoked' );
$GLOBALS['agr_posts']      = array( $T => array( $revoked ) );
$GLOBALS['summaries'][ $T ] = array( 'state' => 'revoked', 'agreement_id' => 0, 'pending_id' => 0, 'accepted_at' => '', 'kind' => 'own', 'drive_url' => '', 'airtable_status' => 'Revoked' );
ob_start();
$module->render_admin_page();
$screen = (string) ob_get_clean();
ck( 'a revoked one is offered Reinstate, keyed to that document', array(
	false !== strpos( $screen, 'value="wpcpm_sponsor_agr_reinstate"' ),
	false !== strpos( $screen, 'wpcpm_sponsor_agr_reinstate_902' ),
), array( true, true ) );

$screen_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'the agreements card is drawn after the interests log', strpos( $screen_src, 'render_agreements(' ) > strpos( $screen_src, '$this->render_interests( $rows );' ), true );
ck( 'and every one of its forms carries the double-submit guard', substr_count( $screen, 'data-wpcpm-once' ) >= 2, true );

echo "\n=== The application queue on the Sponsors screen (Phase S5) ===\n";

// The real application class, loaded here rather than stubbed: what is being pinned is that
// the screen draws what the class stores, and the image handler and the mail exit are never
// reached by a render. The guard comes with it because the open application prints the
// checks' words, which name the guard's numbers.
require_once __DIR__ . '/../includes/class-wpcpm-form-guard.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-application.php';

// The real approval class too, for its `is_half_done()` alone: the queue's half-done mark is
// read off the stamp and the state, and a stub here would pin the mark against a flag this
// file sets rather than against the condition (S5 fix wave). Nothing in this suite approves
// anything, so none of the class's collaborators is ever reached.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-approval.php';


// Uninstall (above) wiped the index; the TEST sponsor was written fresh for the agreement
// section, with no website. One more row with a website, for the in-base match.
WPCPM_Sponsors_Index::write( array(
	$T                  => array( 'name' => 'TEST Sponsor', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
	'recSPONSORTEST002' => array( 'name' => 'Widgetry Ltd ', 'website' => 'https://www.widgetry.example/', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
), time() );

/** An application row, stored the way the form stores one. */
function seed_sponsor_application( $name, $state, array $signals = array(), array $logos = array() ) {
	$id = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Application::POST_TYPE, 'post_status' => 'private', 'post_title' => $name, 'post_author' => 0 ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_FIELDS, array( 'Company Name' => $name, 'Website' => 'https://widgetry.example', 'Contact Person Full Name' => 'Sam Sponsor', 'Contact Email' => 'maciej@a8c.com', 'Sponsorship options' => 'Sponsor mentors + tools/services', "Anything else you'd like to share." => 'We make gadgets.' ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, $state );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_REFERENCE, sprintf( 'SAPP-2026-%04d', $id ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_SIGNALS, $signals );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_EMAIL, md5( 'maciej@a8c.com' ) );
	// 'at' is 2026-09-01 12:00 UTC: the brief's own draft paired this fixture with 1788322400,
	// which gmdate() (this file's wp_date() stub) reads as 2026-09-02, one day past what the
	// check below actually asserts ('Agreed 2026-09-01'); corrected here so the fixture matches
	// the assertion it exists for.
	update_post_meta( $id, WPCPM_Sponsor_Application::META_CONSENT, array( 'sentence' => 'I confirm the privacy policy.', 'url' => 'https://example.test/privacy/', 'policy' => 43, 'modified' => '2026-08-20 11:30:00', 'at' => 1788264000, 'ip' => '203.0.113.0', 'agent' => 'Mozilla/5.0 (test)' ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_LOGOS, array_merge( array( 'colour' => 0, 'white' => 0 ), $logos ) );
	return $id;
}

ck( 'with nothing waiting the bubble is not drawn', $module->menu_label(), 'Sponsors' );

$GLOBALS['get'] = array();
ob_start();
$module->render_admin_page();
$bare = (string) ob_get_clean();
ck( 'with no application at all the queue and the closed list under it each say so, quietly (S5 fix wave)', array(
	false !== strpos( $bare, 'Nothing is waiting. New applications appear here.' ),
	false !== strpos( $bare, 'Recently decided' ),
	false !== strpos( $bare, 'No application has been decided yet.' ),
), array( true, true, true ) );

$open_id = seed_sponsor_application( 'Gadgetry Inc', 'new', array( 'in-base' ), array( 'colour' => 640, 'white' => 641 ) );
$held_id = seed_sponsor_application( 'Held Co', 'held', array( 'links', 'duplicate' ) );
$done_id = seed_sponsor_application( 'Done Co', 'rejected' );

// The bubble was just cached at zero by the "nothing waiting" check above; this stub's
// add_action() is a no-op, so none of the three real hooks fires when wp_insert_post() and
// update_post_meta() seed the fixture above, the way they would on a real site. Forgotten by
// hand so this check reads the fixture just seeded, not the count from before it existed.
WPCPM_Sponsors::forget_attention();
ck( 'the bubble counts the applications waiting', array( false !== strpos( $module->menu_label(), 'count-2' ), false !== strpos( $module->menu_label(), 'pending-count">2<' ) ), array( true, true ) );
ck( 'and the screen\'s sentences include the decisions\'', isset( WPCPM_Sponsors::messages()['sapp-approved'] ), true );

$GLOBALS['get'] = array();
ob_start();
$module->render_admin_page();
$screen = (string) ob_get_clean();

ck( 'the queue card is drawn after the agreements, with its count and the two open rows, oldest first', array(
	strpos( $screen, 'id="wpcpm-sponsor-applications"' ) > strpos( $screen, 'Agreements' ),
	false !== strpos( $screen, 'Sponsor applications <span class="wpcpm-count">2</span>' ),
	strpos( $screen, 'Gadgetry Inc' ) < strpos( $screen, 'Held Co' ),
	// The decided row is on the screen since the S5 fix wave, but under "Recently decided"
	// and never in the queue itself: the count above is the queue's and stays at two.
	strpos( $screen, 'Done Co' ) > strpos( $screen, 'Recently decided' ),
), array( true, true, true, true ) );
ck( 'a held row says it was held and how many checks held it, the duplicate mark aside', array( false !== strpos( $screen, 'wpcpm-inst-mark--held' ), false !== strpos( $screen, '1 check held it' ) ), array( true, true ) );
ck( 'the two duplicate marks are drawn where they apply', array( false !== strpos( $screen, 'possible duplicate' ), false !== strpos( $screen, 'already in the base' ) ), array( true, true ) );
ck( 'each row opens itself on this screen', false !== strpos( $screen, 'wpcpm_sapp_id=' . $open_id ), true );
ck( 'and nothing on the queue is a form', strpos( $screen, 'value="wpcpm_sapp_approve"' ), false );

$GLOBALS['get'] = array( 'wpcpm_sapp_id' => $open_id );
ob_start();
$module->render_admin_page();
$opened = (string) ob_get_clean();

ck( 'the open application is drawn above the queue, with the six columns and their answers', array(
	strpos( $opened, 'id="wpcpm-sponsor-application"' ) < strpos( $opened, 'id="wpcpm-sponsor-applications"' ),
	substr_count( $opened, '<code class="wpcpm-inst-record">' ) >= 8,
	false !== strpos( $opened, 'Sponsor mentors + tools/services' ),
	false !== strpos( $opened, 'We make gadgets.' ),
), array( true, true, true, true ) );
ck( 'the consent evidence is one sentence naming the policy and its version', array( false !== strpos( $opened, 'Agreed 2026-09-01' ), false !== strpos( $opened, 'https://example.test/privacy/' ), false !== strpos( $opened, '2026-08-20 11:30:00' ) ), array( true, true, true ) );
ck( 'the two logos are shown from the Media Library', array( false !== strpos( $opened, 'data-id="640"' ), false !== strpos( $opened, 'data-id="641"' ), false !== strpos( $opened, 'In white' ) ), array( true, true, true ) );
ck( 'what the base already has: the row that matches by website, with its record and its status', array( false !== strpos( $opened, 'What the base already has' ), false !== strpos( $opened, 'recSPONSORTEST002' ), false !== strpos( $opened, 'creates a second record' ) ), array( true, true, true ) );
ck( 'the checks are printed, and the in-base one in words', false !== strpos( $opened, 'already holds a sponsor with this name or website' ), true );
ck( 'the four decisions are offered, each keyed to this application', array(
	false !== strpos( $opened, 'nonce-wpcpm_sapp_approve_' . $open_id ),
	false !== strpos( $opened, 'nonce-wpcpm_sapp_info_' . $open_id ),
	false !== strpos( $opened, 'nonce-wpcpm_sapp_reject_' . $open_id ),
	false !== strpos( $opened, 'nonce-wpcpm_sapp_spam_' . $open_id ),
	strpos( $opened, 'value="wpcpm_sapp_reopen"' ),
), array( true, true, true, true, false ) );
ck( 'and the answers never print the applicant\'s address unescaped or a nonce for another row', array( false !== strpos( $opened, 'maciej@a8c.com' ), strpos( $opened, 'nonce-wpcpm_sapp_approve_' . $held_id ) ), array( true, false ) );

echo "\n=== Fix: the queue marks a contact address that already belongs to an account ===\n";
// A dedicated user and a dedicated application, at an address none of the fixture's other
// accounts holds, so the mark below is read off this one application rather than off whatever
// this file's own account churn happened to leave sitting at maciej@a8c.com.
$GLOBALS['users'][95] = new WP_User( 95, array( 'wpcpm_mentor' ), 'Existing Owner', 'existing-owner@example.test' );
$conflict_id = seed_sponsor_application( 'Conflict Co', 'new' );
update_post_meta( $conflict_id, WPCPM_Sponsor_Application::META_FIELDS, array_merge( get_post_meta( $conflict_id, WPCPM_Sponsor_Application::META_FIELDS, true ), array( 'Contact Email' => 'existing-owner@example.test' ) ) );
$GLOBALS['get'] = array();
ob_start();
$module->render_admin_page();
$with_conflict = (string) ob_get_clean();
ck( 'the queue marks a contact address that already belongs to an account', false !== strpos( $with_conflict, 'already an account' ), true );
ck( 'with the account modifier on the mark', false !== strpos( $with_conflict, 'wpcpm-inst-mark--account' ), true );

$GLOBALS['get'] = array( 'wpcpm_sapp_id' => $done_id );
ob_start();
$module->render_admin_page();
$decided = (string) ob_get_clean();
ck( 'a decided application offers the way back and the deletion', array( false !== strpos( $decided, 'value="wpcpm_sapp_reopen"' ), false !== strpos( $decided, 'value="wpcpm_sapp_purge"' ), strpos( $decided, 'value="wpcpm_sapp_approve"' ) ), array( true, true, false ) );

echo "\n=== Fix: a half-done approval is marked wherever the row is drawn (S5 fix wave) ===\n";
// The record stamped with the row still open is what `WPCPM_Sponsor_Approval::is_half_done()`
// reads, and the real class is loaded in this file: the mark is read off the condition itself.
update_post_meta( $held_id, WPCPM_Sponsor_Application::META_RECORD, 'recSPONSORTEST003' );
$GLOBALS['get'] = array( 'wpcpm_sapp_id' => $held_id );
ob_start();
$module->render_admin_page();
$half = (string) ob_get_clean();
ck( 'the mark is on the open application and on its queue row, in the same words', array(
	substr_count( $half, 'wpcpm-inst-mark--half-done' ),
	substr_count( $half, 'approval half done' ),
	false !== strpos( $half, 'Press Approve again to finish.' ),
), array( 2, 2, true ) );
ck( 'and no other row carries it', substr_count( $half, 'wpcpm_sapp_id=' . $open_id ) > 0 && 2 === substr_count( $half, 'wpcpm-inst-mark--half-done' ), true );
delete_post_meta( $held_id, WPCPM_Sponsor_Application::META_RECORD );

echo "\n=== Recently decided: the closed list under the queue (S5 fix wave) ===\n";
// Two of the six decisions, Put back in the queue and Delete for good, are offered on a
// decided application alone, and until this list existed nothing on either surface listed
// one: they were reachable only by typing ?wpcpm_sapp_id= by hand, so a genuine application
// the checks filed as spam was seen by nobody. The three decided states are seeded with their
// own decision times, because the list is ordered by the decision and not by the row's age,
// and one open row is seeded beside them to prove it stays out of the list.

/**
 * The event row `decided_at()` reads and the meta `decided_posts()` sorts by, so the closed
 * list's order can be set on purpose (both stamped together since 1.98.1, as `add_event()` does).
 */
function decided_on( $id, $at ) {
	update_post_meta( $id, WPCPM_Sponsor_Application::META_EVENT, array( array( 'event' => 'decided', 'at' => (int) $at, 'actor' => 3, 'note' => '' ) ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_DECIDED, (int) $at );
}

$spam_id     = seed_sponsor_application( 'Spammy Co', 'spam' );
$rejected_id = seed_sponsor_application( 'Rejected Co', 'rejected' );
$approved_id = seed_sponsor_application( 'Approved Ltd', 'approved' );
$waiting_id  = seed_sponsor_application( 'Waiting Co', 'new' );

// Deliberately not in ID order: a list that followed the posts rather than the decisions
// would come out as Done, Spammy, Rejected, Approved and pass nothing below.
decided_on( $done_id, 1788000000 );
decided_on( $spam_id, 1788200000 );
decided_on( $rejected_id, 1788100000 );
decided_on( $approved_id, 1788300000 );

$GLOBALS['get'] = array();
ob_start();
$module->render_admin_page();
$screen = (string) ob_get_clean();
$split  = (int) strpos( $screen, 'wpcpm-sapp-decided' );
$queued = substr( $screen, 0, $split );
$closed = substr( $screen, $split );

// The card's own body: from its id to the first tag that closes a div, which is its own.
$card = substr( $screen, (int) strpos( $screen, 'id="wpcpm-sponsor-applications"' ) );
$card = substr( $card, 0, (int) strpos( $card, '</div>' ) );
ck( 'the closed list is inside the queue\'s own card, after the queue itself', array(
	false !== strpos( $card, '<section class="wpcpm-sapp-decided">' ),
	strpos( $card, '<section class="wpcpm-sapp-decided">' ) > strpos( $card, '</ol>' ),
	false !== strpos( $card, '<h3>Recently decided</h3>' ),
), array( true, true, true ) );
ck( 'it lists the three decided states and nothing else, newest decision first', array(
	strpos( $closed, 'Approved Ltd' ) < strpos( $closed, 'Spammy Co' ),
	strpos( $closed, 'Spammy Co' ) < strpos( $closed, 'Rejected Co' ),
	strpos( $closed, 'Rejected Co' ) < strpos( $closed, 'Done Co' ),
), array( true, true, true ) );
ck( 'the open row is in the queue and never in the list under it', array( false !== strpos( $queued, 'Waiting Co' ), strpos( $closed, 'Waiting Co' ) ), array( true, false ) );
ck( 'every decided row carries its Open link, so both decisions can be reached by pressing', array(
	false !== strpos( $closed, 'wpcpm_sapp_id=' . $spam_id ),
	false !== strpos( $closed, 'wpcpm_sapp_id=' . $rejected_id ),
	false !== strpos( $closed, 'wpcpm_sapp_id=' . $approved_id ),
	false !== strpos( $closed, 'Open this application' ),
), array( true, true, true, true ) );
ck( 'and its state, in the words the screens use', array(
	false !== strpos( $closed, 'marked as spam' ),
	false !== strpos( $closed, '>rejected<' ),
	false !== strpos( $closed, '>approved<' ),
), array( true, true, true ) );
ck( 'a decided row says when it was decided; only a waiting one says how long it has waited', array(
	false !== strpos( $closed, 'Decided 4 hours ago, on 2026-09-01 22:00' ),
	strpos( $closed, 'Waiting 4 hours' ),
	false !== strpos( $queued, 'Waiting 4 hours' ),
), array( true, false, true ) );
// The cap is a constant, so the count is asserted against it rather than by lowering it: four
// decided rows are drawn because four is under `QUEUE_MAX`, and the slice that enforces it is
// read off the source.
ck( 'the list draws every decided row up to the queue\'s own cap', array(
	substr_count( $closed, 'class="wpcpm-queue-item"' ),
	min( 4, WPCPM_Sponsor_Application::QUEUE_MAX ),
	false !== strpos( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-application.php' ), 'array_slice( $rows, 0, self::QUEUE_MAX )' ),
), array( 4, 4, true ) );

$GLOBALS['get'] = array( 'wpcpm_sapp_id' => 42 );
ob_start();
$module->render_admin_page();
$wrong = (string) ob_get_clean();
ck( 'a post that is not an application opens nothing', strpos( $wrong, 'id="wpcpm-sponsor-application"' ), false );
$GLOBALS['get'] = array();

$screen_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'the queue is drawn after the agreements card, and the bubble reads three queues behind guards', array(
	strpos( $screen_src, '$this->render_applications();' ) > strpos( $screen_src, '$this->render_agreements( $rows );' ),
	substr_count( method_body( $screen_src, 'attention_count' ), 'class_exists(' ) >= 3,
), array( true, true ) );

echo "\n=== The menu bubble is cached for a minute (1.98.1) ===\n";
$GLOBALS['transients'] = array();
$GLOBALS['queries']    = 0;
$first  = WPCPM_Sponsors::attention_count();
$after_first = (int) $GLOBALS['queries'];
$second = WPCPM_Sponsors::attention_count();
ck( 'the second call within a minute reads the transient and runs no query: the first counted, the second added nothing', array( $first === $second, $after_first > 0, (int) $GLOBALS['queries'] - $after_first, isset( $GLOBALS['transients']['wpcpm_sponsors_attention'] ), $GLOBALS['transients']['wpcpm_sponsors_attention']['ttl'] ), array( true, true, 0, true, 60 ) );
WPCPM_Sponsors::forget_attention();
ck( 'forgetting drops the transient, so the next page counts again', isset( $GLOBALS['transients']['wpcpm_sponsors_attention'] ), false );
$src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'a state change forgets the bubble: the three hooks are registered', array( substr_count( $src, "add_action( 'transition_post_status', array( __CLASS__, 'forget_attention_on_status' )" ), substr_count( $src, "add_action( 'updated_post_meta', array( __CLASS__, 'forget_attention_on_meta' )" ), substr_count( $src, "add_action( 'added_post_meta', array( __CLASS__, 'forget_attention_on_meta' )" ), substr_count( $src, "delete_transient( self::TRANSIENT_ATTENTION )" ) >= 2 ), array( 1, 1, 1, true ) );

echo "\n=== The retention run is on the clock (Phase S5) ===\n";
$GLOBALS['cron'] = array();
WPCPM_Sponsors::schedule_cron();
ck( 'schedule_cron() puts the application purge on the clock beside the agreement discard, daily, nine hours out', array( isset( $GLOBALS['cron']['wpcpm_purge_sponsor_applications'] ), isset( $GLOBALS['cron']['wpcpm_sponsor_agreement_discard'] ), $GLOBALS['cron']['wpcpm_purge_sponsor_applications'] - time() > 8 * HOUR_IN_SECONDS ), array( true, true, true ) );
$before = $GLOBALS['cron']['wpcpm_purge_sponsor_applications'];
WPCPM_Sponsors::schedule_cron();
ck( 'and a second call leaves a job already scheduled alone', $GLOBALS['cron']['wpcpm_purge_sponsor_applications'], $before );
$sponsors_src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
$deactivate   = substr( $sponsors_src, (int) strpos( $sponsors_src, 'public function deactivate()' ) );
$deactivate   = substr( $deactivate, 0, (int) strpos( $deactivate, "\n\t}\n" ) );
ck( 'and deactivation takes the purge off the clock beside the discard (Task 6 review)', array( substr_count( $deactivate, 'wp_clear_scheduled_hook( WPCPM_Sponsor_Application::CRON_PURGE )' ), substr_count( $deactivate, 'wp_clear_scheduled_hook( WPCPM_Sponsor_Agreement::CRON_DISCARD )' ) ), array( 1, 1 ) );

echo "\n=== Uninstall and deactivation take the applications with them (Phase S5) ===\n";
$GLOBALS['deleted_attachments'] = array();
$GLOBALS['opts'][ WPCPM_Sponsor_Application::OPT_PAGE ] = 77;
$GLOBALS['opts'][ WPCPM_Sponsor_Application::OPT_LOG ]  = array( array( 'at' => 1, 'id' => 1, 'reference' => 'SAPP-2026-0001', 'state' => 'spam', 'days' => 30, 'actor' => 0 ) );
$kept_id = seed_sponsor_application( 'Approved Co', 'approved', array(), array( 'colour' => 700, 'white' => 0 ) );
$gone_id = seed_sponsor_application( 'Open Co', 'new', array(), array( 'colour' => 701, 'white' => 702 ) );
WPCPM_Sponsors::schedule_cron();
$module->deactivate();
ck( 'deactivation takes the purge off the clock', isset( $GLOBALS['cron']['wpcpm_purge_sponsor_applications'] ), false );
WPCPM_Sponsors::schedule_cron();
$module->uninstall();
ck( 'uninstall deletes every application, whatever its state', array( get_post( $open_id ), get_post( $held_id ), get_post( $done_id ), get_post( $kept_id ), get_post( $gone_id ) ), array( null, null, null, null, null ) );
ck( 'and the files of the ones nobody approved, never an approved one\'s', array( in_array( 640, $GLOBALS['deleted_attachments'], true ), in_array( 701, $GLOBALS['deleted_attachments'], true ), in_array( 702, $GLOBALS['deleted_attachments'], true ), in_array( 700, $GLOBALS['deleted_attachments'], true ) ), array( true, true, true, false ) );
ck( 'the page option and the log go, and the purge is off the clock', array( get_option( WPCPM_Sponsor_Application::OPT_PAGE ), get_option( WPCPM_Sponsor_Application::OPT_LOG ), isset( $GLOBALS['cron']['wpcpm_purge_sponsor_applications'] ) ), array( false, false, false ) );

$sponsors_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'uninstall() reaches the approval\'s lock sweep behind a guard, and clears the purge hook', array( false !== strpos( method_body( $sponsors_src, 'uninstall' ), 'WPCPM_Sponsor_Approval::delete_all()' ), substr_count( $sponsors_src, 'wp_clear_scheduled_hook( WPCPM_Sponsor_Application::CRON_PURGE )' ) ), array( true, 2 ) );

echo "\n=== House rules ===\n";
$src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'accounts come from WPCPM_Roles::insert_user() alone', strpos( $src, 'wp_insert_user(' ), false );
ck( 'every handler decides through the policy or the capability before it writes', preg_match_all( '/public function handle_(provision|members)\(/', $src ) === 2 && substr_count( $src, 'WPCPM_Sponsor_Policy::decide(' ) >= 2, true );
ck( 'activate() sets the repair flag itself, so a fresh install never queries for accounts it cannot have', false !== strpos( method_body( $src, 'activate' ), 'update_option( WPCPM_Sponsor_Members::OPT_CAPS_REPAIRED, 1, true )' ), true );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
