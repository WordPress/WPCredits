<?php
/**
 * WPCPM_Sponsor_Agreement: every transition, the one refusal, and what never leaves the site.
 *
 * The Collaboration Agreement's shape without the template kind, the generated state and the
 * gate (design spec of 4 September 2026, decision 10 and section 8.2). What is worth pinning:
 *
 * - **Every upload refusal names its reason, and nothing reaches the store.** Half these
 *   assertions are about a call that must not have happened: the ceiling is claimed before ten
 *   megabytes are read, and `WPCPM_Private_Files::store()` is reached only after the name, the
 *   magic bytes, `finfo` and the scan have all passed.
 * - **One document in review at a time**, held by a lock and not only by a read.
 * - **The download is never inline and never named by the uploader**, and a signed-out visitor
 *   meets the login form rather than a bare refusal.
 * - **Accept writes once and tells the sponsor; return needs a note; revoke takes nothing away
 *   but the document's force**, because a sponsor's dashboard was never gated on it.
 * - **The site's own posts are the whole record of every transition**, written into the
 *   sponsors index at once, with no other system to wait on or fail against.
 * - **What the cron forgets and what uninstall keeps.**
 * - **Each press checks the nonce minted for it**, keyed to the document or, for On file, to the
 *   sponsor: Upload, Accept, Withdraw, Return, Revoke, Reinstate and On file are asserted by name,
 *   and Withdraw, Return, Revoke, Reinstate and On file are each pressed once with a nonce minted
 *   for another action, which dies before anything is written. The handlers read the form through
 *   `WPCPM_Request`, where the standard's nonce sniff cannot see a nonce gone.
 * - **The Administrator Dashboard's row speaks the institution side's words**, Accept it, Return
 *   it with this note and Reinstate, and the manager's mail says the document is read on the
 *   Sponsors screen and decided on the dashboard.
 *
 * Real temporary files for the PDFs, an in-memory `WPCPM_Private_Files` that journals its
 * calls, and the real `WPCPM_Pdf_Check`, `WPCPM_Sponsor_Policy`, `WPCPM_Sponsor_Roster`,
 * `WPCPM_Sponsor_Members`, `WPCPM_Sponsors_Index` and `WPCPM_Request`.
 *
 * Run from the plugin root:  php bin/test-sponsor-agreement.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MB_IN_BYTES', 1048576 );

$GLOBALS['opts']        = array();
$GLOBALS['posts']       = array();
$GLOBALS['pmeta']       = array();
$GLOBALS['umeta']       = array();
$GLOBALS['users']       = array();
$GLOBALS['uid']         = 0;
$GLOBALS['manage']      = array( 1 );
$GLOBALS['next_post']   = 500;
$GLOBALS['next_term']   = 40;
// The site's clock, frozen for the run. It has to be the machine's own day rather than a
// literal, because `discard()` reads the real `time()` while everything the suite writes is
// dated through `wp_date()`: a fixed timestamp would drift past the retention window and start
// discarding documents this suite dated today.
$GLOBALS['clock']       = time();
$GLOBALS['files']       = array();
$GLOBALS['journal']     = array();
$GLOBALS['forgotten']   = array();
$GLOBALS['notes']       = array();
$GLOBALS['store_fails'] = false;
$GLOBALS['ceiling']     = array();
$GLOBALS['audit']       = array();
$GLOBALS['mail']        = array();
$GLOBALS['mailed_raw']  = array();
$GLOBALS['nonce']       = array();
// The action string the nonce this request carries was minted for, or null for "whatever is
// asked". Only the download's keying checks set it (FSUIT-9).
$GLOBALS['nonce_held']  = null;
$GLOBALS['hooks']       = array();
$GLOBALS['referer']     = '';
$GLOBALS['post_types']  = array();
$GLOBALS['settings']    = array(
	'agreement_max_mb'          => 10,
	// Higher than the site's own default of five. This one process runs more uploads than a
	// day allows, and what the ceiling has to prove is where it sits in the source: refusing a
	// later block here would fail it for a reason it is not about.
	'agreement_uploads_per_day' => 50,
	'agreement_review_days'     => 3,
	'agreement_discard_days'    => 30,
);

class WP_Error {
	private $c, $m, $d;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; $this->d = $d; }
	public function get_error_code() { return $this->c; }
	public function get_error_message() { return $this->m; }
	public function get_error_data() { return $this->d; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }

class WP_User {
	public $ID = 0, $roles = array(), $display_name = '', $user_email = '', $user_login = '';
	public function __construct( $id = 0, array $roles = array(), $name = 'Someone', $email = 'maciej@a8c.com' ) {
		$this->ID = (int) $id; $this->roles = $roles; $this->display_name = $name; $this->user_email = $email; $this->user_login = strtolower( str_replace( ' ', '', $name ) );
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_type = 'post', $post_title = '', $post_name = '', $post_status = 'draft', $post_author = 0, $post_date = '', $post_date_gmt = '2026-09-06 09:00:00';
	// $post_date defaults to today, not a literal: a fixture that never sets its own post_date
	// would otherwise drift with every day that passes and eventually read as long overdue.
	public function __construct( array $a ) { $this->post_date = gmdate( 'Y-m-d' ) . ' 09:00:00'; foreach ( $a as $k => $v ) { $this->$k = $v; } }
}
class WPCPM_Test_Redirect extends Exception {}

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_textarea( $s ) { return esc_html( $s ); }
function esc_url_raw( $s, $p = null ) { return (string) $s; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $n ); }
function sanitize_title( $s ) { $s = strtolower( trim( (string) $s ) ); $s = preg_replace( '/[^a-z0-9]+/', '-', $s ); return trim( $s, '-' ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function absint( $n ) { return abs( (int) $n ); }
function number_format_i18n( $n ) { return (string) $n; }
function size_format( $b, $d = 0 ) { return (int) $b . ' B'; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_unslash( $v ) { return $v; }
function wp_check_invalid_utf8( $v ) { return $v; }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( $u ) : parse_url( $u, $c ); }
function wp_date( $f, $ts = null ) { return gmdate( $f, null === $ts ? $GLOBALS['clock'] : (int) $ts ); }
function current_time( $t, $g = 0 ) { return gmdate( 'Y-m-d H:i:s', $GLOBALS['clock'] ); }
function human_time_diff( $a, $b = 0 ) { return '2 days'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function wp_login_url( $to = '' ) { return 'https://example.test/wp-login.php?redirect_to=' . rawurlencode( $to ); }
function add_query_arg( $args, $url = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function wp_get_referer() { return $GLOBALS['referer']; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $a ) . '" />'; }
function wp_nonce_url( $url, $a ) { return $url . '&_wpnonce=' . rawurlencode( $a ); }
function check_admin_referer( $a ) {
	$GLOBALS['nonce'][] = $a;
	// A nonce is only ever good for the action string it was minted for, which is what
	// keying the download's action to the document ID buys. With `nonce_held` null this
	// accepts anything, as every check but that one wants.
	if ( null !== $GLOBALS['nonce_held'] && $GLOBALS['nonce_held'] !== $a ) {
		wp_die( 'The link you followed has expired.', 403 );
	}
	return true;
}
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function get_user_by( $f, $v ) { return ( 'id' === $f && isset( $GLOBALS['users'][ (int) $v ] ) ) ? $GLOBALS['users'][ (int) $v ] : false; }
function get_userdata( $id ) { return get_user_by( 'id', $id ); }
function wp_get_current_user() { return isset( $GLOBALS['users'][ $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ $GLOBALS['uid'] ] : new WP_User( 0 ); }
function get_user_meta( $id, $k, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $k ] ) ? $GLOBALS['umeta'][ $id ][ $k ] : ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ $id ][ $k ] = $v; return true; }
function get_users( array $args ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $u ) {
		if ( isset( $args['meta_key'] ) ) {
			$have = get_user_meta( $u->ID, $args['meta_key'], true );
			if ( isset( $args['meta_value'] ) ) { if ( (string) $have !== (string) $args['meta_value'] ) { continue; } } elseif ( '' === (string) $have ) { continue; }
		}
		$out[] = $u;
	}
	return $out;
}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $a = null ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_post( $p ) { if ( $p instanceof WP_Post ) { return $p; } return isset( $GLOBALS['posts'][ (int) $p ] ) ? $GLOBALS['posts'][ (int) $p ] : null; }
function get_post_meta( $id, $k, $single = false ) {
	if ( ! $single ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ) ? (array) $GLOBALS['pmeta'][ (int) $id ][ $k ] : array(); }
	$v = isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $k ] : '';
	return is_array( $v ) && isset( $v[0] ) && ! isset( $v['path'] ) ? $v[0] : $v;
}
// As core's: what is written is unslashed first, at any depth, so words reach it as slashed copies.
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = stripslashes_deep( $v ); return true; }
function add_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ][] = stripslashes_deep( $v ); return true; }
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); return true; }
function wp_insert_post( $a, $e = false ) { if ( ! empty( $GLOBALS['insert_fails'] ) ) { return new WP_Error( 'db_insert_error', 'Could not insert post into the database.' ); } $id = $GLOBALS['next_post']++; $a['ID'] = $id; $GLOBALS['posts'][ $id ] = new WP_Post( $a ); return $id; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
function register_post_type( $type, $args ) { $GLOBALS['post_types'][ $type ] = $args; return (object) $args; }
function get_posts( array $args ) {
	$type  = isset( $args['post_type'] ) ? $args['post_type'] : 'post';
	$out   = array();
	foreach ( $GLOBALS['posts'] as $p ) {
		if ( $p->post_type !== $type ) { continue; }
		$keep = true;
		foreach ( (array) ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() ) as $clause ) {
			if ( ! is_array( $clause ) || empty( $clause['key'] ) ) { continue; }
			$have = get_post_meta( $p->ID, $clause['key'], true );
			if ( isset( $clause['compare'] ) && 'EXISTS' === $clause['compare'] ) { $keep = $keep && '' !== $have; continue; }
			if ( isset( $clause['compare'] ) && 'IN' === $clause['compare'] ) { $keep = $keep && in_array( $have, (array) $clause['value'], true ); continue; }
			$keep = $keep && (string) $have === (string) $clause['value'];
		}
		// A bare meta_key, which core reads as "this key is set", is supported here too, kept
		// for any caller that asks that way. The store (1.111.0) asks a different way, meta_key
		// with meta_value, wanting an exact match rather than mere presence: post_id_of() would
		// otherwise find whichever sponsor post happened to come first, not the one whose key
		// it was actually asked for.
		if ( $keep && isset( $args['meta_key'] ) ) {
			$have = (string) get_post_meta( $p->ID, $args['meta_key'], true );
			$keep = array_key_exists( 'meta_value', $args ) ? $have === (string) $args['meta_value'] : '' !== $have;
		}
		if ( $keep ) { $out[] = $p; }
	}
	usort( $out, static function ( $a, $b ) { return $b->ID - $a->ID; } );
	// The store also asks for order the flat way, orderby 'ID' and order 'ASC', rather than the
	// nested orderby('date') shape below.
	$asc = ( isset( $args['orderby']['date'] ) && 'ASC' === $args['orderby']['date'] )
		|| ( isset( $args['orderby'] ) && 'ID' === $args['orderby'] && isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) );
	if ( $asc ) { $out = array_reverse( $out ); }
	if ( isset( $args['offset'] ) ) { $out = array_slice( $out, (int) $args['offset'] ); }
	if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 ) { $out = array_slice( $out, 0, (int) $args['numberposts'] ); }
	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) { return array_map( static function ( $p ) { return $p->ID; }, $out ); }
	return $out;
}
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['hooks'][] = $h; }
// The PDF check sets the allowed types for its one question and takes the filter off after
// (`WPCPM_Pdf_Check::named_pdf()`); this suite's map asks no allowed types, so neither keeps one.
function add_filter( $h, $c, $p = 10, $n = 1 ) { return true; }
function remove_filter( $h, $c, $p = 10 ) { return true; }
function wp_mail( $to, $subject, $body, $headers = array() ) { $GLOBALS['mailed_raw'][] = array( $to, $subject, $body ); return true; }
function nocache_headers() {}
function wp_safe_redirect( $u ) { throw new WPCPM_Test_Redirect( $u ); }
function wp_die( $m, $t = '', $a = array() ) { throw new WPCPM_Test_Redirect( 'die:' . ( is_int( $t ) ? $t : 0 ) . ':' . $m ); }

/**
 * WordPress's own name-and-type map, over the real finfo.
 *
 * @param string     $file     Path on disk.
 * @param string     $filename The name the file came with.
 * @param array|null $mimes    The map the caller allows.
 * @return array
 */
function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
	$ext  = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
	$real = '';
	if ( function_exists( 'finfo_open' ) && is_readable( $file ) ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$real  = $finfo ? (string) finfo_file( $finfo, $file ) : '';
	}
	if ( 'pdf' !== $ext || ( '' !== $real && 'application/pdf' !== $real ) ) {
		return array( 'ext' => false, 'type' => false, 'proper_filename' => false );
	}
	return array( 'ext' => 'pdf', 'type' => 'application/pdf', 'proper_filename' => false );
}

class WPCPM_Settings {
	public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; }
	public static function get() { return $GLOBALS['settings']; }
}
class WPCPM_Roles {
	const ROLE_SPONSOR = 'wpcpm_sponsor'; const ROLE_ADMIN = 'administrator';
	const CAP_MANAGE = 'wpcpm_manage_program';
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
}
class WPCPM_Mentors_Sync { public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); } }
// The private store, in memory, journalling every call: half the assertions below are about a
// call that must not have happened.
class WPCPM_Private_Files {
	const OPT_KEY = 'wpcpm_private_key';
	public static function store( $bytes, $extension = 'pdf' ) {
		$GLOBALS['journal'][] = 'store';
		if ( $GLOBALS['store_fails'] ) { return new WP_Error( 'wpcpm_private_write', 'The file could not be written.' ); }
		$path = '2026/' . str_pad( (string) ( count( $GLOBALS['files'] ) + 1 ), 32, 'a', STR_PAD_LEFT ) . '.' . $extension;
		$GLOBALS['files'][ $path ] = $bytes;
		return array( 'path' => $path, 'sha256' => hash( 'sha256', $bytes ), 'size' => strlen( $bytes ) );
	}
	public static function read( $relative ) { return array_key_exists( $relative, $GLOBALS['files'] ) ? $GLOBALS['files'][ $relative ] : new WP_Error( 'wpcpm_private_missing', 'Not in the store.' ); }
	public static function forget( $relative ) { $GLOBALS['journal'][] = 'forget'; $GLOBALS['forgotten'][] = $relative; unset( $GLOBALS['files'][ $relative ] ); return true; }
	public static function base() { return '/srv/uploads/.wpcpm-private/'; }
	public static function write_note( $name, $text ) { $GLOBALS['notes'][ $name ] = $text; return $name; }
	public static function probe_result() { return isset( $GLOBALS['probe'] ) ? $GLOBALS['probe'] : null; }
	public static function verdict( array $result ) { return ! empty( $result['blocked'] ) ? 'blocked' : 'served'; }
}
class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) {
		$GLOBALS['ceiling'][ $key ] = isset( $GLOBALS['ceiling'][ $key ] ) ? $GLOBALS['ceiling'][ $key ] + 1 : 1;
		return $GLOBALS['ceiling'][ $key ] <= (int) $limit;
	}
}
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member';
	const EVIDENCE_INDEX = 'index'; const EVIDENCE_CACHE = 'cache'; const EVIDENCE_LIVE = 'live';
	public static function record_sponsor( array $entry ) { $GLOBALS['audit'][] = $entry; return count( $GLOBALS['audit'] ); }
}
class WPCPM_Mail {
	public static function send( $to, $context, $build ) {
		$u = $to instanceof WP_User ? $to : get_user_by( 'id', (int) $to );
		$GLOBALS['mail'][] = array( 'to' => $u ? $u->user_email : '', 'context' => $context, 'mail' => call_user_func( $build, $u ) );
		return true;
	}
	public static function send_to( $email, $context, $build, $locale = '' ) {
		$GLOBALS['mail'][] = array( 'to' => (string) $email, 'context' => $context, 'mail' => call_user_func( $build, null ) );
		return true;
	}
	public static function reply_to( $person ) { return array(); }
	public static function site_name() { return 'WordPress Education'; }
}
class WPCPM_Sponsor_Interests {
	const MAIL_CONTEXT = 'sponsor-interest';
	public static function mail_manager( $record, $build, $context = self::MAIL_CONTEXT ) {
		return WPCPM_Mail::send_to( 'maciej@a8c.com', $context, $build ) ? 1 : 0;
	}
}
class WPCPM_Refusal_Meter { public static function is_locked( $scope, $user ) { return false; } public static function refuse( $scope, $user ) { return 0; } }
/**
 * The flash: each outcome a press leaves is journalled in the order it was set, and a refused
 * form's typing waits per user and channel, as user meta keeps it, until `take()` hands it back
 * once and clears it, or `sweep()` clears it unread. bin/test-flash.php pins the real class.
 */
class WPCPM_Flash {
	public static function set( $channel, $value, $user_id = 0 ) {
		if ( 0 === strpos( (string) $channel, WPCPM_Typed_Text::KEPT_CHANNEL ) ) {
			$GLOBALS['kept_typing'][ (int) ( $user_id ? $user_id : $GLOBALS['uid'] ) ][ (string) $channel ] = $value;
			return;
		}
		$GLOBALS['flash'][] = array( $channel, $value );
	}
	public static function take( $channel, $user_id = 0 ) {
		$uid   = (int) ( $user_id ? $user_id : $GLOBALS['uid'] );
		$value = $GLOBALS['kept_typing'][ $uid ][ (string) $channel ] ?? '';
		unset( $GLOBALS['kept_typing'][ $uid ][ (string) $channel ] );
		return $value;
	}
	public static function sweep( $stale, $user_id = 0 ) {
		$uid = (int) ( $user_id ? $user_id : $GLOBALS['uid'] );
		foreach ( $GLOBALS['kept_typing'][ $uid ] ?? array() as $channel => $value ) {
			if ( $stale( (string) $channel, $value ) ) {
				unset( $GLOBALS['kept_typing'][ $uid ][ $channel ] );
			}
		}
	}
}
class WPCPM_Sponsors { const FLASH = 'sponsors_admin'; }
class WPCPM_Sponsors_Dashboard {
	const FLASH = 'sponsor_dashboard';
	public static function leave( $status, $card, $record = '', $detail = '' ) { throw new WPCPM_Test_Redirect( $status . '|' . $card . '|' . $record . '|' . $detail ); }
	public static function page_url() { return 'https://example.test/sponsor-dashboard/'; }
}

require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/stubs/posts.php';
require_once __DIR__ . '/stubs/cleaners.php';
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/class-wpcpm-request.php';
require_once __DIR__ . '/../includes/class-wpcpm-pdf-check.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-agreement.php';

$fail = 0; $checks = 0;

/**
 * One assertion.
 *
 * @param string $label    What is being asserted.
 * @param mixed  $actual   What the code answered.
 * @param mixed  $expected What it should have answered.
 */
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

/**
 * A minimal PDF every check passes.
 *
 * @param string $extra Anything to append before the trailer.
 * @return string
 */
function pdf_bytes( $extra = '' ) {
	return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n" . $extra . "trailer<</Root 1 0 R>>\n%%EOF\n";
}

/**
 * A real file on disk, in this run's own folder, which goes when the run ends.
 *
 * @param string $bytes     What to write.
 * @param string $extension The extension, without the dot.
 * @return string Path.
 */
function temp_file( $bytes, $extension = 'pdf' ) {
	$path = wpcpm_test_tempnam( 'wpcpm-sagr-', $extension );
	file_put_contents( $path, $bytes );

	return $path;
}

/**
 * Put a file in `$_FILES` the way a browser would.
 *
 * @param string $path  Path on disk.
 * @param string $name  The name it came with.
 * @param int    $size  The size PHP reports; null for the real one.
 * @param int    $error The upload error code.
 */
function post_file( $path, $name = 'agreement.pdf', $size = null, $error = UPLOAD_ERR_OK ) {
	$_FILES = array(
		'wpcpm_sponsor_agr_file' => array(
			'name'     => $name,
			'type'     => 'application/pdf',
			'tmp_name' => $path,
			'error'    => $error,
			'size'     => null === $size ? (int) filesize( $path ) : (int) $size,
		),
	);
}

/**
 * Run a handler and report where it left.
 *
 * @param string $method The handler.
 * @return string
 */
function ran( $method ) {
	try {
		call_user_func( array( 'WPCPM_Sponsor_Agreement', $method ) );
	} catch ( WPCPM_Test_Redirect $e ) {
		return $e->getMessage();
	}

	return 'no redirect';
}

/**
 * Every message sent so far, as `context to address`.
 *
 * @return string[]
 */
function mail_log() {
	return array_map(
		static function ( $row ) { return $row['context'] . ' to ' . $row['to']; },
		$GLOBALS['mail']
	);
}

/**
 * The state of every agreement post for a sponsor, oldest first.
 *
 * @param string $record Sponsor record ID.
 * @return string[]
 */
function states_of( $record ) {
	$out = array();
	foreach ( array_reverse( WPCPM_Sponsor_Agreement::posts_for( $record ) ) as $post ) {
		$out[] = (string) get_post_meta( $post->ID, WPCPM_Sponsor_Agreement::META_STATE, true );
	}
	return $out;
}

/**
 * One method's body, by brace depth, for the assertions that read source rather than behavior.
 *
 * @param string $source The file.
 * @param string $name   The method.
 * @return string
 */
function method_body( $source, $name ) {
	if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\([^)]*\)\s*\{/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		return '';
	}

	$offset = $m[0][1] + strlen( $m[0][0] );
	$depth  = 1;
	$end    = $offset;
	$length = strlen( $source );

	while ( $end < $length && $depth > 0 ) {
		if ( '{' === $source[ $end ] ) { ++$depth; } elseif ( '}' === $source[ $end ] ) { --$depth; }
		++$end;
	}

	return substr( $source, $offset, $end - $offset );
}

/**
 * One method's docblock and body together, so the sentence that says where something is read
 * counts alongside the reads themselves, and not as a stray outside the method it describes.
 *
 * @param string $source The file.
 * @param string $name   The method.
 * @return string
 */
function method_with_doc( $source, $name ) {
	$body = method_body( $source, $name );

	if ( '' === $body ) {
		return '';
	}

	$signature = strpos( $source, 'function ' . $name . '(' );
	$doc_start = strrpos( substr( $source, 0, $signature ), '/**' );

	return ( false === $doc_start ? '' : substr( $source, $doc_start, $signature - $doc_start ) ) . $body;
}

/**
 * Seed one sponsor into the store.
 *
 * 1.111.0: the index is posts now, not the option this suite used to write by hand - insert()
 * is the store's own seeding path, and using it here is what makes row() and rows() answer for
 * a sponsor this function has seeded.
 *
 * @param string $record Sponsor key.
 * @param string $name   Company name.
 * @param array  $block  The `agreement` block.
 */
function seed_index( $record, $name, array $block = array() ) {
	WPCPM_Sponsors_Index::insert(
		array_merge(
			WPCPM_Sponsors_Index::empty_row(),
			array(
				'record_id' => $record,
				'name'      => $name,
				'status'    => 'Approved',
				'agreement' => array_merge( array( 'status' => '', 'accepted_on' => '', 'has_document' => false ), $block ),
			)
		)
	);
}

$S = 'recSPN00000000001';
$T = 'recSPN00000000002';
seed_index( $S, 'TEST Sponsor' );
seed_index( $T, 'Other Sponsor' );
$GLOBALS['users'][1]  = new WP_User( 1, array( 'administrator' ), 'Manager', 'maciej@a8c.com' );
$GLOBALS['users'][20] = new WP_User( 20, array( 'wpcpm_sponsor' ), 'Member One', 'maciej@a8c.com' );
$GLOBALS['users'][21] = new WP_User( 21, array( 'wpcpm_sponsor' ), 'Member Two', 'maciej@a8c.com' );
$GLOBALS['umeta'][20] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $S, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['umeta'][21] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $S, WPCPM_Sponsor_Members::META_ACTIVE => 1 );

echo "=== The record type ===\n";
WPCPM_Sponsor_Agreement::register_post_type();
$type = $GLOBALS['post_types'][ WPCPM_Sponsor_Agreement::POST_TYPE ];
ck( 'the post type name is under twenty-one characters', strlen( WPCPM_Sponsor_Agreement::POST_TYPE ) <= 20, true );
ck( 'and invisible everywhere', array( $type['public'], $type['publicly_queryable'], $type['show_ui'], $type['show_in_rest'], $type['exclude_from_search'] ), array( false, false, false, false, true ) );
ck( 'the states are the six the spec names', array( WPCPM_Sponsor_Agreement::STATE_SUBMITTED, WPCPM_Sponsor_Agreement::STATE_ACCEPTED, WPCPM_Sponsor_Agreement::STATE_RETURNED, WPCPM_Sponsor_Agreement::STATE_WITHDRAWN, WPCPM_Sponsor_Agreement::STATE_SUPERSEDED, WPCPM_Sponsor_Agreement::STATE_REVOKED ), array( 'submitted', 'accepted', 'returned', 'withdrawn', 'superseded', 'revoked' ) );
ck( 'the kinds are two: no template exists', array( WPCPM_Sponsor_Agreement::KIND_OWN, WPCPM_Sponsor_Agreement::KIND_LEGACY ), array( 'own', 'legacy' ) );
ck( 'the six status words are spelled as the base once spelled them', array(
	WPCPM_Sponsor_Agreement::STATUS_NOT_STARTED,
	WPCPM_Sponsor_Agreement::STATUS_AWAITING,
	WPCPM_Sponsor_Agreement::STATUS_ACCEPTED,
	WPCPM_Sponsor_Agreement::STATUS_RETURNED,
	WPCPM_Sponsor_Agreement::STATUS_ON_FILE,
	WPCPM_Sponsor_Agreement::STATUS_REVOKED,
), array( 'Not started', 'Awaiting review', 'Accepted', 'Returned', 'On file', 'Revoked' ) );
ck( 'nothing is settled before anything happens', WPCPM_Sponsor_Agreement::summary( $S )['state'], WPCPM_Sponsor_Agreement::SUMMARY_NONE );
ck( 'and a malformed record answers none rather than warning', WPCPM_Sponsor_Agreement::summary( 'nonsense' )['state'], WPCPM_Sponsor_Agreement::SUMMARY_NONE );

echo "\n=== Every upload refusal, and nothing stored ===\n";
$GLOBALS['uid'] = 20;
$_POST          = array( 'wpcpm_sponsor' => $S, 'wpcpm_sponsor_agr_signed' => '1' );
$good           = temp_file( pdf_bytes() );

// Every refusal carries the record, as the logo card's do: a manager who pressed the button on
// a sponsor's behalf lands back on that sponsor and not on whichever one the page opens with.
post_file( $good );
$_POST['wpcpm_sponsor_agr_signed'] = '0';
ck( 'the declaration is required', ran( 'handle_upload' ), 'agreement-declare|agreement|' . $S . '|' );
$_POST['wpcpm_sponsor_agr_signed'] = '1';
post_file( $good, 'agreement.pdf', 11 * MB_IN_BYTES );
ck( 'a file over the setting is refused', ran( 'handle_upload' ), 'agreement-too-big|agreement|' . $S . '|' );
post_file( '', 'agreement.pdf', 0, UPLOAD_ERR_NO_FILE );
ck( 'no file at all is refused', ran( 'handle_upload' ), 'agreement-no-file|agreement|' . $S . '|' );
post_file( temp_file( 'Just some text.', 'pdf' ) );
ck( 'text named .pdf is refused by its bytes', ran( 'handle_upload' ), 'agreement-not-pdf|agreement|' . $S . '|' );
post_file( $good, 'agreement.exe' );
ck( 'and a PDF named .exe by its name', ran( 'handle_upload' ), 'agreement-not-pdf|agreement|' . $S . '|' );
post_file( temp_file( pdf_bytes( "2 0 obj<</Encrypt 3 0 R>>endobj\n" ) ) );
ck( 'a password-protected PDF is refused with the scan\'s own reason', ran( 'handle_upload' ), 'agreement-encrypted|agreement|' . $S . '|' );
post_file( temp_file( pdf_bytes( "2 0 obj<</S/Launch/F(calc.exe)>>endobj\n" ) ) );
ck( 'and one that asks to run a program', ran( 'handle_upload' ), 'agreement-launch|agreement|' . $S . '|' );
ck( 'nothing reached the store through any of them', $GLOBALS['journal'], array() );
ck( 'and no post was written', count( WPCPM_Sponsor_Agreement::posts_for( $S ) ), 0 );
ck( 'and every one of them released the lock it took', get_option( WPCPM_Sponsor_Agreement::LOCK_PREFIX . $S ), false );

echo "\n=== The ceiling ===\n";
// The world sets the limit to fifty so the refusals above spend none of a real sponsor's day;
// this block lowers it to one for its own length only, then puts both the limit and the
// ceiling back so the block below sees a fresh day again.
$held_limit = $GLOBALS['settings']['agreement_uploads_per_day'];
$GLOBALS['settings']['agreement_uploads_per_day'] = 1;
$GLOBALS['ceiling'] = array();
// The first call spends the day's one upload without leaving a document behind: undeclared,
// so it is refused after the ceiling and before anything is stored, same as the declaration
// refusal above. The second finds the ceiling already spent.
$_POST['wpcpm_sponsor_agr_signed'] = '0';
post_file( $good );
ran( 'handle_upload' );
$_POST['wpcpm_sponsor_agr_signed'] = '1';
post_file( $good );
ck( 'a second upload past the day\'s limit is refused by the ceiling', ran( 'handle_upload' ), 'agreement-busy|agreement|' . $S . '|' );
ck( 'the ceiling is keyed to the record', array_keys( $GLOBALS['ceiling'] ), array( WPCPM_Sponsor_Agreement::CEILING . $S ) );
$GLOBALS['settings']['agreement_uploads_per_day'] = $held_limit;
$GLOBALS['ceiling'] = array();

echo "\n=== The upload that works ===\n";
post_file( $good, 'Signed agreement.pdf' );
ck( 'the sponsor is told it landed', ran( 'handle_upload' ), 'agreement-uploaded|agreement|' . $S . '|' );
$listed = WPCPM_Sponsor_Agreement::posts_for( $S );
$first  = (int) $listed[0]->ID;
ck( 'one private post of this type, stamped with the sponsor and submitted', array(
	count( $listed ),
	$listed[0]->post_type,
	$listed[0]->post_status,
	(string) get_post_meta( $first, WPCPM_Sponsor_Agreement::META_SPONSOR, true ),
	(string) get_post_meta( $first, WPCPM_Sponsor_Agreement::META_STATE, true ),
	(string) get_post_meta( $first, WPCPM_Sponsor_Agreement::META_KIND, true ),
), array( 1, 'wpcpm_sponsor_agr', 'private', $S, 'submitted', 'own' ) );
ck( 'the bytes are in the private store and the post points at them by path and hash', array(
	$GLOBALS['journal'],
	isset( get_post_meta( $first, WPCPM_Sponsor_Agreement::META_FILE, true )['sha256'] ),
), array( array( 'store' ), true ) );
ck( 'the uploader\'s filename is kept for display only', (string) get_post_meta( $first, WPCPM_Sponsor_Agreement::META_ORIGINAL_NAME, true ), 'Signed-agreement.pdf' );
ck( 'the nonce was keyed to the record', end( $GLOBALS['nonce'] ), 'wpcpm_sponsor_agr_upload_' . $S );
ck( 'an upload records Awaiting review in the row at once', WPCPM_Sponsors_Index::row( $S )['agreement']['status'], 'Awaiting review' );
ck( 'the summary is submitted and names the document', array( WPCPM_Sponsor_Agreement::summary( $S )['state'], WPCPM_Sponsor_Agreement::summary( $S )['pending_id'] ), array( 'submitted', $first ) );
ck( 'the assigned manager is told once and the sponsor\'s accounts are not mailed', mail_log(), array( 'sponsor-agreement-received to maciej@a8c.com' ) );
// The document is decided on the Administrator Dashboard, the one place that accepts or returns it,
// and read first on the Sponsors screen, whose address the mail ends on: the queue tab, at the card
// that lists the signed agreements.
$received = end( $GLOBALS['mail'] );
$received = isset( $received['mail']['body'] ) ? (string) $received['mail']['body'] : '';
ck( 'the mail says where the document is decided, then where it is read first, and ends on that address, the Sponsors screen\'s queue tab at the signed agreements\' card', array(
	false !== strpos( $received, "Accept or return it on the Administrator Dashboard. Read it first on the Sponsors screen:\r\n\r\nhttps://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-agreements" ),
	'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-agreements' === substr( $received, -strlen( 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-agreements' ) ),
	strpos( $received, 'Read it on the Sponsors screen, then' ),
), array( true, true, false ) );
ck( 'an audit row names the upload with the size and the hash', array( end( $GLOBALS['audit'] )['kind'], isset( end( $GLOBALS['audit'] )['data']['sha256'] ) ), array( 'sponsor_agreement_upload', true ) );

post_file( $good );
ck( 'a second document while one waits is refused', ran( 'handle_upload' ), 'agreement-in-review|agreement|' . $S . '|' );
ck( 'and the store was not touched again', $GLOBALS['journal'], array( 'store' ) );

echo "\n=== Withdraw ===\n";
$_POST = array( 'wpcpm_sponsor_agr_post' => $first );
// Withdraw and the four decisions of a manager's below read their form through WPCPM_Request,
// where the standard's nonce sniff cannot see it, so only these checks notice a nonce gone: a page
// elsewhere could then press one for whoever is signed in. A nonce minted for another action is
// refused before the document is so much as looked at.
$GLOBALS['nonce_held'] = WPCPM_Sponsor_Agreement::ACTION_UPLOAD . '_' . $S;
$journal_before        = $GLOBALS['journal'];
$stale_withdraw        = ran( 'handle_withdraw' );
$GLOBALS['nonce_held'] = null;
ck( 'Withdraw with a nonce that is not its own dies before the file or the document is touched', array( $stale_withdraw, (string) get_post_meta( $first, WPCPM_Sponsor_Agreement::META_STATE, true ), $GLOBALS['journal'] ), array( 'die:403:The link you followed has expired.', 'submitted', $journal_before ) );
$GLOBALS['nonce'] = array();
ck( 'the sponsor takes it back', ran( 'handle_withdraw' ), 'agreement-withdrawn|agreement|' . $S . '|' );
ck( 'Withdraw checks the nonce keyed to the document', $GLOBALS['nonce'], array( 'wpcpm_sponsor_agr_withdraw_' . $first ) );
ck( 'the file goes at once rather than on the cron\'s schedule', array( (string) get_post_meta( $first, WPCPM_Sponsor_Agreement::META_STATE, true ), in_array( 'forget', $GLOBALS['journal'], true ) ), array( 'withdrawn', true ) );
ck( 'the post is kept, because the history is what the next manager reads', get_post( $first ) instanceof WP_Post, true );
ck( 'with nothing else standing, the record shows the queue entry is gone', WPCPM_Sponsors_Index::row( $S )['agreement']['status'], 'Not started' );
ck( 'nothing is mailed: nothing happened anybody else needs telling about', count( $GLOBALS['mail'] ), 1 );
ck( 'a second press finds nothing to withdraw', ran( 'handle_withdraw' ), 'agreement-gone|agreement||' );

echo "\n=== A withdrawal writes what the site holds (FAGRM-1) ===\n";
// FAGRM-1: handle_withdraw() sent the `Not started` literal whenever no accepted agreement
// stood, which moved the record backwards over a `Returned` the program had written itself,
// and left the manager screen reading "This site: the last document was returned." with
// nothing to repair it once the replacement above it was taken back too.
$Y = 'recSPN00000000007';
seed_index( $Y, 'Returned Sponsor' );
$GLOBALS['users'][23] = new WP_User( 23, array( 'wpcpm_sponsor' ), 'Member Four', 'maciej@a8c.com' );
$GLOBALS['umeta'][23] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $Y, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['uid']       = 23;
$_POST                = array( 'wpcpm_sponsor' => $Y, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$sent_back      = (int) WPCPM_Sponsor_Agreement::posts_for( $Y )[0]->ID;
$GLOBALS['uid'] = 1;
$_POST          = array( 'wpcpm_sponsor_agr_post' => $sent_back, 'wpcpm_sponsor_agr_note' => 'Page 4 is unsigned. Please sign it and upload the whole document again.' );
ran( 'handle_return' );
$GLOBALS['uid'] = 23;
$_POST          = array( 'wpcpm_sponsor' => $Y, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$replacing = (int) WPCPM_Sponsor_Agreement::posts_for( $Y )[0]->ID;
$_POST     = array( 'wpcpm_sponsor_agr_post' => $replacing );
ck( 'withdraw records the status the remaining documents give', array( ran( 'handle_withdraw' ), WPCPM_Sponsors_Index::row( $Y )['agreement']['status'] ), array( 'agreement-withdrawn|agreement|' . $Y . '|', 'Returned' ) );
ck( 'so the index block and the card agree', WPCPM_Sponsor_Agreement::summary( $Y )['state'], 'returned' );

// The other half of that sentence, and the one shape the section above cannot show, because
// what it does is nothing: with an accepted agreement standing, the withdrawal of a
// replacement never reaches the write at all. The accepted document is the one in force, so a
// write here could only record the same value or a worse one.
$Z = 'recSPN00000000008';
seed_index( $Z, 'Accepted Sponsor' );
$GLOBALS['users'][24] = new WP_User( 24, array( 'wpcpm_sponsor' ), 'Member Five', 'maciej@a8c.com' );
$GLOBALS['umeta'][24] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $Z, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['uid']       = 24;
$_POST                = array( 'wpcpm_sponsor' => $Z, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$standing       = (int) WPCPM_Sponsor_Agreement::posts_for( $Z )[0]->ID;
$GLOBALS['uid'] = 1;
$_POST          = array( 'wpcpm_sponsor_agr_post' => $standing );
ran( 'handle_accept' );
$GLOBALS['uid'] = 24;
$_POST          = array( 'wpcpm_sponsor' => $Z, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$replacement = (int) WPCPM_Sponsor_Agreement::posts_for( $Z )[0]->ID;
$_POST       = array( 'wpcpm_sponsor_agr_post' => $replacement );
ck( 'withdrawing a replacement while an accepted agreement stands leaves the record alone', array( ran( 'handle_withdraw' ), WPCPM_Sponsors_Index::row( $Z )['agreement']['status'] ), array( 'agreement-withdrawn|agreement|' . $Z . '|', 'Accepted' ) );

echo "\n=== A member of one sponsor cannot touch another's document ===\n";
$GLOBALS['uid'] = 20;
$other          = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Agreement::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Other' ) );
update_post_meta( $other, WPCPM_Sponsor_Agreement::META_SPONSOR, $T );
update_post_meta( $other, WPCPM_Sponsor_Agreement::META_STATE, WPCPM_Sponsor_Agreement::STATE_SUBMITTED );
$_POST = array( 'wpcpm_sponsor_agr_post' => $other );
ck( 'withdrawing it is the one refusal, and it names nothing', ran( 'handle_withdraw' ), 'die:403:That is not something your account can do here.' );
ck( 'and the document is untouched', (string) get_post_meta( $other, WPCPM_Sponsor_Agreement::META_STATE, true ), 'submitted' );

echo "\n=== The download ===\n";
$_POST          = array( 'wpcpm_sponsor' => $S, 'wpcpm_sponsor_agr_signed' => '1' );
$GLOBALS['uid'] = 20;
post_file( $good );
ran( 'handle_upload' );
$live  = (int) WPCPM_Sponsor_Agreement::posts_for( $S )[0]->ID;
$_GET  = array( 'post' => $live, '_wpnonce' => 'x' );
$GLOBALS['uid'] = 0;
ck( 'a signed-out visitor meets the login form with a way back, not a bare refusal', 0 === strpos( ran( 'handle_download' ), 'https://example.test/wp-login.php?redirect_to=' ), true );
$GLOBALS['uid'] = 21;
$_GET = array( 'post' => 999999, '_wpnonce' => 'x' );
ck( 'a document this site does not hold is a 404, so guessing IDs tells a stranger nothing', substr( ran( 'handle_download' ), 0, 7 ), 'die:404' );
$_GET = array( 'post' => $other, '_wpnonce' => 'x' );
ck( 'another sponsor\'s document is the one refusal', substr( ran( 'handle_download' ), 0, 7 ), 'die:403' );

// FSUIT-9: the action string is keyed to the document, so a link minted for one opens no
// other; unkeyed, a single nonce would work against every document ID on the site.
$GLOBALS['nonce_held'] = WPCPM_Sponsor_Agreement::ACTION_DOWNLOAD . '_' . $other;
ck( 'a link minted for a document is accepted for that document, and the policy is what refuses', ran( 'handle_download' ), 'die:403:That is not something your account can do here.' );
$GLOBALS['nonce_held'] = WPCPM_Sponsor_Agreement::ACTION_DOWNLOAD . '_' . $live;
ck( 'and a link minted for another document is refused before this one is read', ran( 'handle_download' ), 'die:403:The link you followed has expired.' );
$plain                 = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'A blog post' ) );
$_GET                  = array( 'post' => $plain, '_wpnonce' => 'x' );
$GLOBALS['nonce_held'] = WPCPM_Sponsor_Agreement::ACTION_DOWNLOAD . '_' . $plain;
ck( 'a post of another type, asked for with a good link of its own, is the same 404', ran( 'handle_download' ), 'die:404:That document is not one this site holds.' );
$GLOBALS['nonce_held'] = null;

$source = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-agreement.php' );
$body   = method_body( $source, 'handle_download' );
ck( 'the file is handed over as an attachment and never shown inline', array(
	false !== strpos( $body, "Content-Disposition: attachment; filename=" ),
	false !== strpos( $body, "X-Content-Type-Options: nosniff" ),
	false !== strpos( $body, "default-src 'none'; sandbox" ),
	false !== strpos( $body, 'inline' ),
), array( true, true, true, false ) );
ck( 'and the name is built from the company and the date, never from the uploaded name', array(
	false !== strpos( method_body( $source, 'download_name' ), 'META_ORIGINAL_NAME' ),
	false !== strpos( method_body( $source, 'download_name' ), 'sanitize_file_name' ),
), array( false, true ) );

echo "\n=== What a reviewer reads before opening anything ===\n";
$X = 'recSPN00000000006';
seed_index( $X, 'Flagged Sponsor' );
$flagged = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Agreement::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Flagged' ) );
update_post_meta( $flagged, WPCPM_Sponsor_Agreement::META_SPONSOR, $X );
update_post_meta( $flagged, WPCPM_Sponsor_Agreement::META_STATE, WPCPM_Sponsor_Agreement::STATE_SUBMITTED );
update_post_meta( $flagged, WPCPM_Sponsor_Agreement::META_KIND, WPCPM_Sponsor_Agreement::KIND_OWN );
// The scan's flags are one meta value that happens to be a list, which is what the upload
// writes and what the reader reads back; a bare list here would be a repeating key, whose
// single value is a string, and the reviewer would be shown nothing.
update_post_meta( $flagged, WPCPM_Sponsor_Agreement::META_FLAGS, array( array( 'flag-a', 'flag-b' ) ) );
$facts = WPCPM_Sponsor_Agreement::review_facts( $flagged );
ck( 'the facts name the company, the state and every flag the scan raised', array( $facts['sponsor_name'], $facts['state'], $facts['flags'], $facts['size'] ), array( 'Flagged Sponsor', 'submitted', array( 'flag-a', 'flag-b' ), 0 ) );

echo "\n=== The order of the checks, read off the source ===\n";
$upload = method_body( $source, 'handle_upload' );
ck( 'the fence runs before the ceiling', strpos( $upload, 'WPCPM_Sponsor_Roster::claim' ) < strpos( $upload, 'WPCPM_Ceiling::claim' ), true );
ck( 'the ceiling is claimed before the file is looked at', strpos( $upload, 'WPCPM_Ceiling::claim' ) < strpos( $upload, 'self::uploaded_file()' ), true );
ck( 'and every check runs before anything is stored', array(
	strpos( $upload, 'WPCPM_Pdf_Check::named_pdf' ) < strpos( $upload, 'WPCPM_Private_Files::store' ),
	strpos( $upload, 'WPCPM_Pdf_Check::has_magic' ) < strpos( $upload, 'WPCPM_Private_Files::store' ),
	strpos( $upload, 'WPCPM_Pdf_Check::mime_of' ) < strpos( $upload, 'WPCPM_Private_Files::store' ),
	strpos( $upload, 'WPCPM_Pdf_Check::inspect' ) < strpos( $upload, 'WPCPM_Private_Files::store' ),
), array( true, true, true, true ) );
ck( 'the upload checks that the file came from this request', false !== strpos( method_body( $source, 'arrived_by_post' ), 'is_uploaded_file( $path )' ), true );
ck( 'the lock is taken before the in-review question is asked', strpos( $upload, 'self::lock( $record )' ) < strpos( $upload, "summary( \$record )" ), true );
// Every handler writes the index inside its own critical section and says so: rebuild() takes
// no lock of its own, so a rebuild after the unlock would write the block from state another
// transition may be changing.
foreach ( array( 'handle_upload', 'handle_withdraw', 'handle_accept', 'handle_return', 'handle_revoke', 'handle_reinstate', 'handle_on_file' ) as $handler ) {
	$one = method_body( $source, $handler );
	ck( $handler . '() rebuilds under its own lock, then releases it', strpos( $one, 'self::rebuild(' ) < strrpos( $one, 'self::unlock( $record )' ), true );
}
// So rebuild() takes no lock of its own and has no flag to ask whether it should. The one caller
// that ever reached it without the lock was the sponsors sync, which went in 1.111.0; a flag left
// behind would read as a second way in that nothing uses.
$rebuild = method_body( $source, 'rebuild' );
ck( 'rebuild() takes and releases no lock of its own, and no caller passes it a lock flag',
	array(
		1 === preg_match( '/function rebuild\( \$record, array \$written \) \{/', $source ),
		strpos( $rebuild, 'self::lock(' ),
		strpos( $rebuild, 'self::unlock(' ),
		preg_match( '/self::rebuild\([^;]*,\s*true\s*\);/s', $source ),
	),
	array( true, false, false, 0 ) );

echo "\n=== Accept ===\n";
$GLOBALS['uid']     = 1;
$GLOBALS['referer'] = 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors';
$GLOBALS['mail']    = array();
$_POST              = array( 'wpcpm_sponsor_agr_post' => $live );
ck( 'the manager accepts it', ran( 'handle_accept' ), 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors' );
ck( 'the flash is on the wp-admin channel', end( $GLOBALS['flash'] ), array( 'sponsors_admin', 'agreement-accepted' ) );
ck( 'the document is accepted, with who and when', array(
	(string) get_post_meta( $live, WPCPM_Sponsor_Agreement::META_STATE, true ),
	(int) get_post_meta( $live, WPCPM_Sponsor_Agreement::META_DECIDED_BY, true ),
	(string) get_post_meta( $live, WPCPM_Sponsor_Agreement::META_DECIDED_AT, true ),
), array( 'accepted', 1, wp_date( 'Y-m-d' ) ) );
ck( 'accept records Accepted with today\'s date', array( WPCPM_Sponsors_Index::row( $S )['agreement']['status'], WPCPM_Sponsors_Index::row( $S )['agreement']['accepted_on'] ), array( 'Accepted', wp_date( 'Y-m-d' ) ) );
ck( 'the summary is accepted and names the document', array( WPCPM_Sponsor_Agreement::summary( $S )['state'], WPCPM_Sponsor_Agreement::summary( $S )['agreement_id'] ), array( 'accepted', $live ) );
ck( 'both accounts are told', mail_log(), array( 'sponsor-agreement-accepted to maciej@a8c.com', 'sponsor-agreement-accepted to maciej@a8c.com' ) );
ck( 'the nonce was keyed to the document', end( $GLOBALS['nonce'] ), 'wpcpm_sponsor_agr_accept_' . $live );
ck( 'a second press finds nothing waiting', ran( 'handle_accept' ) && 'agreement-gone' === end( $GLOBALS['flash'] )[1], true );

echo "\n=== A replacement supersedes the copy in force ===\n";
$GLOBALS['uid'] = 20;
$_POST          = array( 'wpcpm_sponsor' => $S, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$replacement = (int) WPCPM_Sponsor_Agreement::posts_for( $S )[0]->ID;
ck( 'the status is left alone while the accepted copy stands', WPCPM_Sponsors_Index::row( $S )['agreement']['status'], 'Accepted' );
$GLOBALS['uid'] = 1;
$_POST          = array( 'wpcpm_sponsor_agr_post' => $replacement );
ran( 'handle_accept' );
ck( 'accepting it retires the older one', array(
	(string) get_post_meta( $live, WPCPM_Sponsor_Agreement::META_STATE, true ),
	(string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_STATE, true ),
), array( 'superseded', 'accepted' ) );
ck( 'and one accepted document stands, never two', WPCPM_Sponsor_Agreement::summary( $S )['agreement_id'], $replacement );

echo "\n=== Return ===\n";
$GLOBALS['uid'] = 20;
$_POST          = array( 'wpcpm_sponsor' => $S, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$sent           = (int) WPCPM_Sponsor_Agreement::posts_for( $S )[0]->ID;
$GLOBALS['uid'] = 1;
$_POST          = array( 'wpcpm_sponsor_agr_post' => $sent, 'wpcpm_sponsor_agr_note' => 'Too short' );
ran( 'handle_return' );
ck( 'a note under twenty characters returns nothing', end( $GLOBALS['flash'] )[1], 'agreement-note' );
ck( 'and the document is still waiting', (string) get_post_meta( $sent, WPCPM_Sponsor_Agreement::META_STATE, true ), 'submitted' );
$GLOBALS['mail'] = array();
$_POST           = array( 'wpcpm_sponsor_agr_post' => $sent, 'wpcpm_sponsor_agr_note' => "Page 4 is unsigned.\n\nPlease sign it and upload the whole document again." );
$GLOBALS['nonce_held'] = WPCPM_Sponsor_Agreement::ACTION_ACCEPT . '_' . $sent;
$stale_return          = ran( 'handle_return' );
$GLOBALS['nonce_held'] = null;
ck( 'Return with a nonce minted for Accept dies before the document is returned or anybody is mailed', array( $stale_return, (string) get_post_meta( $sent, WPCPM_Sponsor_Agreement::META_STATE, true ), count( $GLOBALS['mail'] ) ), array( 'die:403:The link you followed has expired.', 'submitted', 0 ) );
$GLOBALS['nonce'] = array();
ran( 'handle_return' );
ck( 'a note of twenty characters or more returns it', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $sent, WPCPM_Sponsor_Agreement::META_STATE, true ) ), array( 'agreement-returned', 'returned' ) );
ck( 'Return checks the nonce keyed to the document', $GLOBALS['nonce'], array( 'wpcpm_sponsor_agr_return_' . $sent ) );
ck( 'the note is kept on the document with its line breaks', false !== strpos( (string) get_post_meta( $sent, WPCPM_Sponsor_Agreement::META_NOTE, true ), "\n" ), true );
ck( 'the accepted copy that stands keeps the record saying Accepted', WPCPM_Sponsors_Index::row( $S )['agreement']['status'], 'Accepted' );
ck( 'and both accounts get the note', mail_log(), array( 'sponsor-agreement-returned to maciej@a8c.com', 'sponsor-agreement-returned to maciej@a8c.com' ) );

seed_index( $T, 'Other Sponsor' );
$GLOBALS['umeta'][21] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $T, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['uid']       = 21;
$_POST                = array( 'wpcpm_sponsor' => $T, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$lonely         = (int) WPCPM_Sponsor_Agreement::posts_for( $T )[0]->ID;
$GLOBALS['uid'] = 1;
$_POST          = array( 'wpcpm_sponsor_agr_post' => $lonely, 'wpcpm_sponsor_agr_note' => 'The signature block is empty on every page.' );
ran( 'handle_return' );
ck( 'with nothing accepted, the record says Returned', WPCPM_Sponsors_Index::row( $T )['agreement']['status'], 'Returned' );

echo "\n=== Revoke and reinstate ===\n";
$GLOBALS['mail'] = array();
$_POST           = array( 'wpcpm_sponsor_agr_post' => $replacement );
ran( 'handle_revoke' );
ck( 'a revoke with no note revokes nothing', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_STATE, true ) ), array( 'agreement-revoke-note', 'accepted' ) );
$_POST = array( 'wpcpm_sponsor_agr_post' => $replacement, 'wpcpm_sponsor_agr_note' => 'The company asked us to put this one out of force.' );
$GLOBALS['nonce_held'] = WPCPM_Sponsor_Agreement::ACTION_REINSTATE . '_' . $replacement;
$stale_revoke          = ran( 'handle_revoke' );
$GLOBALS['nonce_held'] = null;
ck( 'Revoke with a nonce minted for Reinstate dies before the agreement leaves force or anybody is mailed', array( $stale_revoke, (string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_STATE, true ), WPCPM_Sponsors_Index::row( $S )['agreement']['status'], count( $GLOBALS['mail'] ) ), array( 'die:403:The link you followed has expired.', 'accepted', 'Accepted', 0 ) );
$GLOBALS['nonce'] = array();
ran( 'handle_revoke' );
ck( 'the accepted document is revoked', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_STATE, true ) ), array( 'agreement-revoked', 'revoked' ) );
ck( 'Revoke checks the nonce keyed to the document', $GLOBALS['nonce'], array( 'wpcpm_sponsor_agr_revoke_' . $replacement ) );
ck( 'the record says Revoked', WPCPM_Sponsors_Index::row( $S )['agreement']['status'], 'Revoked' );
ck( 'no account is closed: a sponsor\'s dashboard was never gated on an agreement', array( count( WPCPM_Sponsor_Members::members_of( $S ) ), (int) get_user_meta( 20, WPCPM_Sponsor_Members::META_ACTIVE, true ) ), array( 1, 1 ) );
ck( 'and the accounts are told, with the note', count( $GLOBALS['mail'] ), 1 );
ck( 'a second press finds nothing in force to revoke', ran( 'handle_revoke' ) && 'agreement-not-accepted' === end( $GLOBALS['flash'] )[1], true );

$_POST = array( 'wpcpm_sponsor_agr_post' => $replacement );
$GLOBALS['nonce_held'] = WPCPM_Sponsor_Agreement::ACTION_REVOKE . '_' . $replacement;
$stale_reinstate       = ran( 'handle_reinstate' );
$GLOBALS['nonce_held'] = null;
ck( 'Reinstate with a nonce minted for Revoke dies before the agreement is back in force', array( $stale_reinstate, (string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_STATE, true ), WPCPM_Sponsors_Index::row( $S )['agreement']['status'] ), array( 'die:403:The link you followed has expired.', 'revoked', 'Revoked' ) );
$GLOBALS['nonce'] = array();
ran( 'handle_reinstate' );
ck( 'reinstating puts it back in force', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_STATE, true ) ), array( 'agreement-reinstated', 'accepted' ) );
ck( 'Reinstate checks the nonce keyed to the document', $GLOBALS['nonce'], array( 'wpcpm_sponsor_agr_reinstate_' . $replacement ) );
ck( 'the record goes back to Accepted for an uploaded copy', WPCPM_Sponsors_Index::row( $S )['agreement']['status'], 'Accepted' );
ck( 'the date is today, not the day it was first accepted', (string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_DECIDED_AT, true ), wp_date( 'Y-m-d' ) );
ck( 'the revocation note went with the revocation: an agreement in force carries none', (string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_NOTE, true ), '' );
ck( 'reinstating twice is refused', ran( 'handle_reinstate' ) && 'agreement-not-revoked' === end( $GLOBALS['flash'] )[1], true );

echo "\n=== On file ===\n";
$U = 'recSPN00000000003';
seed_index( $U, 'Legacy Sponsor' );
$GLOBALS['mail'] = array();
$_POST           = array( 'wpcpm_sponsor' => $U, 'wpcpm_sponsor_agr_drive' => 'http://drive.google.com/drive/folders/abc' );
ran( 'handle_on_file' );
ck( 'a link that is not https on a Drive host records nothing', end( $GLOBALS['flash'] )[1], 'agreement-link' );
$_POST = array( 'wpcpm_sponsor' => $U, 'wpcpm_sponsor_agr_drive' => 'https://example.test/agreement.pdf' );
ran( 'handle_on_file' );
ck( 'nor does one on another host', end( $GLOBALS['flash'] )[1], 'agreement-link' );
ck( 'and nothing was recorded', WPCPM_Sponsors_Index::row( $U )['agreement']['status'], '' );
$_POST = array( 'wpcpm_sponsor' => $U, 'wpcpm_sponsor_agr_drive' => 'https://drive.google.com/drive/folders/abc' );
$GLOBALS['nonce_held'] = WPCPM_Sponsor_Agreement::ACTION_UPLOAD . '_' . $U;
$stale_on_file         = ran( 'handle_on_file' );
$GLOBALS['nonce_held'] = null;
ck( 'On file with a nonce that is not its own dies before anything is recorded', array( $stale_on_file, WPCPM_Sponsor_Agreement::posts_for( $U ), WPCPM_Sponsors_Index::row( $U )['agreement']['status'] ), array( 'die:403:The link you followed has expired.', array(), '' ) );
// Dying before the write is not the whole order: each of the five checks its nonce before it asks
// who may act, as the sponsor policy answers it, whose refusal ends the press the same way. The
// order is read in each handler itself.
$agreement_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-agreement.php' );
$nonce_order   = array();
foreach ( array( 'handle_withdraw' => 'self::may_act(', 'handle_return' => 'self::may_act(', 'handle_revoke' => 'self::may_act(', 'handle_reinstate' => 'self::may_act(', 'handle_on_file' => 'WPCPM_Sponsor_Policy::decide(' ) as $handler => $asks ) {
	$body                    = method_body( $agreement_src, $handler );
	$nonce_order[ $handler ] = strpos( $body, 'check_admin_referer(' ) > 0 && strpos( $body, 'check_admin_referer(' ) < strpos( $body, $asks );
}
ck( 'Withdraw, Return, Revoke, Reinstate and On file each check the nonce before they ask who may act', $nonce_order, array( 'handle_withdraw' => true, 'handle_return' => true, 'handle_revoke' => true, 'handle_reinstate' => true, 'handle_on_file' => true ) );
$GLOBALS['nonce'] = array();
ran( 'handle_on_file' );
ck( 'a Drive link records the agreement as on file', end( $GLOBALS['flash'] )[1], 'agreement-on-file' );
ck( 'On file checks the nonce keyed to the sponsor', $GLOBALS['nonce'], array( 'wpcpm_sponsor_agr_on_file_' . $U ) );
$legacy = (int) WPCPM_Sponsor_Agreement::posts_for( $U )[0]->ID;
ck( 'a legacy post stands for it, accepted and with the link', array(
	(string) get_post_meta( $legacy, WPCPM_Sponsor_Agreement::META_KIND, true ),
	(string) get_post_meta( $legacy, WPCPM_Sponsor_Agreement::META_STATE, true ),
	(string) get_post_meta( $legacy, WPCPM_Sponsor_Agreement::META_DRIVE_URL, true ),
), array( 'legacy', 'accepted', 'https://drive.google.com/drive/folders/abc' ) );
ck( 'the summary reads on file rather than accepted', WPCPM_Sponsor_Agreement::summary( $U )['state'], 'on_file' );
ck( 'on file records the Drive link as a document held', WPCPM_Sponsors_Index::row( $U )['agreement'], array( 'status' => 'On file', 'accepted_on' => wp_date( 'Y-m-d' ), 'has_document' => true ) );
ck( 'no mail: nobody at the company asked for this and it is not news to them', count( $GLOBALS['mail'] ), 0 );
ran( 'handle_on_file' );
ck( 'recording it twice is refused', end( $GLOBALS['flash'] )[1], 'agreement-standing' );
$_GET = array( 'post' => $legacy, '_wpnonce' => 'x' );
ck( 'a legacy row has no file, so downloading it is the same 404 as a document this site never held', substr( ran( 'handle_download' ), 0, 7 ), 'die:404' );

$V = 'recSPN00000000004';
seed_index( $V, 'Unlucky Sponsor' );
$GLOBALS['insert_fails'] = true;
$_POST                   = array( 'wpcpm_sponsor' => $V, 'wpcpm_sponsor_agr_drive' => 'https://drive.google.com/drive/folders/xyz' );
ran( 'handle_on_file' );
// Nothing is written before the post, so a failed insert leaves the company exactly as it
// stood, and the manager can simply try again; the flash names that reason on its own,
// rather than the lock-contention wording agreement-busy carries.
ck( 'a failed insert gets its own message, not the lock\'s busy one', end( $GLOBALS['flash'] )[1], 'agreement-not-saved' );
ck( 'while the site holds nothing for it', WPCPM_Sponsor_Agreement::summary( $V )['state'], 'none' );
$GLOBALS['insert_fails'] = false;
$_POST = array( 'wpcpm_sponsor' => $U, 'wpcpm_sponsor_agr_post' => $legacy, 'wpcpm_sponsor_agr_note' => 'The paper copy this row stands for was superseded by a new one.' );
ran( 'handle_revoke' );
ck( 'a legacy row can be revoked too', (string) get_post_meta( $legacy, WPCPM_Sponsor_Agreement::META_STATE, true ), 'revoked' );
$_POST = array( 'wpcpm_sponsor_agr_post' => $legacy );
ran( 'handle_reinstate' );
ck( 'and comes back as On file, not Accepted', WPCPM_Sponsors_Index::row( $U )['agreement']['status'], 'On file' );
$_POST = array( 'wpcpm_sponsor' => $U, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$signed_copy = (int) WPCPM_Sponsor_Agreement::posts_for( $U )[0]->ID;
$_POST       = array( 'wpcpm_sponsor_agr_post' => $signed_copy );
ran( 'handle_accept' );
ck( 'a signed copy accepted over an on-file row records the acceptance and clears the document flag', array(
	WPCPM_Sponsors_Index::row( $U )['agreement']['status'],
	WPCPM_Sponsors_Index::row( $U )['agreement']['accepted_on'],
	WPCPM_Sponsors_Index::row( $U )['agreement']['has_document'],
), array( 'Accepted', wp_date( 'Y-m-d' ), false ) );
ck( 'and the legacy row is superseded', (string) get_post_meta( $legacy, WPCPM_Sponsor_Agreement::META_STATE, true ), 'superseded' );

// A link is a code, not prose: the one-line cleaner removes every "%" followed by two hexadecimal
// digits, and a Drive link can carry them in its query. On this line the post holds the link and
// the row says a document is held.
$O     = 'recSPN00000000010';
$O_url = 'https://drive.google.com/drive/folders/abc?usp=sharing&q=Collaboration%20Agreement%202026';
seed_index( $O, 'Octet Sponsor' );
$_POST = array( 'wpcpm_sponsor' => $O, 'wpcpm_sponsor_agr_drive' => $O_url );
ran( 'handle_on_file' );
$O_post = WPCPM_Sponsor_Agreement::posts_for( $O )[0] ?? null;
ck( 'a Drive link with percent octets in its query is recorded with them on the post, and the row holds a document', array(
	end( $GLOBALS['flash'] )[1],
	$O_post ? (string) get_post_meta( (int) $O_post->ID, WPCPM_Sponsor_Agreement::META_DRIVE_URL, true ) : null,
	WPCPM_Sponsors_Index::row( $O )['agreement']['has_document'] ?? null,
), array( 'agreement-on-file', $O_url, true ) );

echo "\n=== Only a program manager ===\n";
$GLOBALS['uid'] = 20;
foreach ( array( 'handle_accept', 'handle_return', 'handle_revoke', 'handle_reinstate', 'handle_on_file' ) as $method ) {
	ck( $method . '() refuses a sponsor account before it reads anything', substr( ran( $method ), 0, 7 ), 'die:403' );
}
$GLOBALS['uid'] = 1;

echo "\n=== The daily discard ===\n";
$GLOBALS['clock'] = time();
$kept  = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Agreement::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Accepted, old' ) );
update_post_meta( $kept, WPCPM_Sponsor_Agreement::META_SPONSOR, $S );
update_post_meta( $kept, WPCPM_Sponsor_Agreement::META_STATE, WPCPM_Sponsor_Agreement::STATE_ACCEPTED );
update_post_meta( $kept, WPCPM_Sponsor_Agreement::META_DECIDED_AT, '2026-01-01' );
update_post_meta( $kept, WPCPM_Sponsor_Agreement::META_FILE, array( 'path' => '2026/keep.pdf', 'sha256' => str_repeat( 'a', 64 ), 'size' => 10 ) );
$GLOBALS['files']['2026/keep.pdf'] = 'x';

$old = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Agreement::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Returned, old' ) );
update_post_meta( $old, WPCPM_Sponsor_Agreement::META_SPONSOR, $S );
update_post_meta( $old, WPCPM_Sponsor_Agreement::META_STATE, WPCPM_Sponsor_Agreement::STATE_RETURNED );
update_post_meta( $old, WPCPM_Sponsor_Agreement::META_DECIDED_AT, '2026-01-01' );
update_post_meta( $old, WPCPM_Sponsor_Agreement::META_FILE, array( 'path' => '2026/old.pdf', 'sha256' => str_repeat( 'b', 64 ), 'size' => 12 ) );
$GLOBALS['files']['2026/old.pdf'] = 'y';

$fresh = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Agreement::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Returned, today' ) );
update_post_meta( $fresh, WPCPM_Sponsor_Agreement::META_SPONSOR, $S );
update_post_meta( $fresh, WPCPM_Sponsor_Agreement::META_STATE, WPCPM_Sponsor_Agreement::STATE_RETURNED );
update_post_meta( $fresh, WPCPM_Sponsor_Agreement::META_DECIDED_AT, wp_date( 'Y-m-d' ) );
update_post_meta( $fresh, WPCPM_Sponsor_Agreement::META_FILE, array( 'path' => '2026/fresh.pdf', 'sha256' => str_repeat( 'c', 64 ), 'size' => 14 ) );
$GLOBALS['files']['2026/fresh.pdf'] = 'z';

// No decided-at date at all, and a post_date nothing can read: the shape a corrupted or
// never-finished row takes. The cron must fail closed on it, not read the unknown as "old
// enough", so it is never discarded.
$unknown = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Agreement::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Returned, no known date', 'post_date' => '0000-00-00 00:00:00' ) );
update_post_meta( $unknown, WPCPM_Sponsor_Agreement::META_SPONSOR, $S );
update_post_meta( $unknown, WPCPM_Sponsor_Agreement::META_STATE, WPCPM_Sponsor_Agreement::STATE_RETURNED );
update_post_meta( $unknown, WPCPM_Sponsor_Agreement::META_FILE, array( 'path' => '2026/unknown.pdf', 'sha256' => str_repeat( 'd', 64 ), 'size' => 16 ) );
$GLOBALS['files']['2026/unknown.pdf'] = 'w';

$GLOBALS['forgotten'] = array();
ck( 'one file is past the retention setting and goes', WPCPM_Sponsor_Agreement::discard(), 1 );
ck( 'the returned one from months ago', $GLOBALS['forgotten'], array( '2026/old.pdf' ) );
ck( 'the accepted one is kept whatever its age: it is the agreement', isset( $GLOBALS['files']['2026/keep.pdf'] ), true );
ck( 'and so is one returned today', isset( $GLOBALS['files']['2026/fresh.pdf'] ), true );
ck( 'and so is one with no decided-at date and no readable post_date: the cron fails closed, not open', isset( $GLOBALS['files']['2026/unknown.pdf'] ), true );
ck( 'the post is kept in every case, because the history is what the next manager reads', array( get_post( $old ) instanceof WP_Post, get_post_meta( $old, WPCPM_Sponsor_Agreement::META_FILE, true ) ), array( true, '' ) );
ck( 'a second run has nothing left to do', WPCPM_Sponsor_Agreement::discard(), 0 );

// Activation alone never schedules this cron on a site deployed with `wp plugin install
// --force`, which never fires the activation hook: the module must put it back on the clock
// from `boot()`, on every load, the way the Institutions module's own jobs already do.
$sponsors_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'boot() hooks schedule_cron() on init, so an install through the upgrader still gets the discard job scheduled', false !== strpos( method_body( $sponsors_src, 'boot' ), "add_action( 'init', array( __CLASS__, 'schedule_cron' )" ), true );

echo "\n=== The manifest, and what uninstall keeps ===\n";
$rows   = WPCPM_Sponsor_Agreement::kept_rows();
$paths  = array_map( static function ( $row ) { return $row[4]; }, $rows );
$stored = array_keys( $GLOBALS['files'] );
sort( $paths );
sort( $stored );
// One row per file and no file without a row: an inventory that missed one would leave a
// signed document on disk that the message telling the owner about them never named.
ck( 'every document that still has a file is one row of six', array( $paths === $stored, count( $rows[0] ) ), array( true, 6 ) );
$named = array();
foreach ( $rows as $row ) { $named[ $row[4] ] = array( $row[0], $row[1], $row[2] ); }
ck( 'the row names the company, the record and the state', $named['2026/keep.pdf'], array( 'TEST Sponsor', $S, 'accepted' ) );
ck( 'and the path is the store\'s, never a public URL', 0 === strpos( $rows[0][4], '2026/' ), true );

$modules = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-modules.php' );
ck( 'Institutions uninstalls before Sponsors, so the sponsor posts still exist when the manifest is built', strpos( $modules, 'new WPCPM_Institutions()' ) < strpos( $modules, 'new WPCPM_Sponsors()' ), true );
$inst = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-institution-agreement.php' );
ck( 'and the manifest asks this class for its rows, behind a guard', array(
	false !== strpos( $inst, "class_exists( 'WPCPM_Sponsor_Agreement' )" ),
	false !== strpos( $inst, 'WPCPM_Sponsor_Agreement::kept_rows()' ),
), array( true, true ) );

WPCPM_Sponsor_Agreement::delete_all();
ck( 'delete_all() takes every post', count( get_posts( array( 'post_type' => WPCPM_Sponsor_Agreement::POST_TYPE, 'numberposts' => -1 ) ) ), 0 );
ck( 'and every option and lock of its own', array_values( array_filter( array_keys( $GLOBALS['opts'] ), static function ( $k ) { return 0 === strpos( $k, 'wpcpm_sponsor_agr_' ); } ) ), array() );
ck( 'the files stay, with the key: they are the program\'s legal records', array( isset( $GLOBALS['files']['2026/keep.pdf'] ), isset( $GLOBALS['files']['2026/fresh.pdf'] ) ), array( true, true ) );
ck( 'and the sponsors index is not this class\'s to delete', WPCPM_Sponsors_Index::has( $S ), true );

$sponsors = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'the module schedules the discard on activation and clears it twice', array(
	false !== strpos( $sponsors, 'WPCPM_Sponsor_Agreement::CRON_DISCARD' ),
	substr_count( $sponsors, 'wp_clear_scheduled_hook( WPCPM_Sponsor_Agreement::CRON_DISCARD )' ),
), array( true, 2 ) );
ck( 'and calls delete_all() on uninstall, behind a guard', false !== strpos( $sponsors, 'WPCPM_Sponsor_Agreement::delete_all()' ), true );

echo "\n=== The Administrator Dashboard's decision row (1.96.1) ===\n";
if ( ! class_exists( 'WPCPM_Return' ) ) {
	class WPCPM_Return {
		const FIELD     = 'wpcpm_return';
		const DASHBOARD = 'dashboard';
		public static function field( $return, $anchor ) { echo '<input type="hidden" name="wpcpm_return" value="' . esc_attr( $return ) . '" data-anchor="' . esc_attr( $anchor ) . '" />'; }
		public static function url( $fallback ) { return 'https://example.test/administrator-dashboard/#wpcpm-sponsor-agreements'; }
	}
}
if ( ! class_exists( 'WPCPM_Institutions' ) ) {
	class WPCPM_Institutions { const FLASH = 'institutions_dashboard'; }
}
if ( ! function_exists( 'wp_nonce_url' ) ) { function wp_nonce_url( $url, $action = -1 ) { return $url . '&_wpnonce=nonce-' . $action; } }
if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); } }

$GLOBALS['uid'] = 21;
$_POST          = array( 'wpcpm_sponsor' => $T, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$queued = (int) WPCPM_Sponsor_Agreement::posts_for( $T )[0]->ID;

$GLOBALS['uid'] = 1;
ob_start();
WPCPM_Sponsor_Agreement::render_decision( $queued, WPCPM_Return::DASHBOARD );
$row = (string) ob_get_clean();
ck( 'a document waiting for review gets Download, Accept and the folded Return, all bound for the dashboard', array(
	false !== strpos( $row, 'class="wpcpm-request__decide wpcpm-sponsor-agreement__decide"' ),
	false !== strpos( $row, 'action=wpcpm_sponsor_agr_download&post=' . $queued . '&_wpnonce=wpcpm_sponsor_agr_download_' . $queued ),
	false !== strpos( $row, 'value="wpcpm_sponsor_agr_accept"' ),
	false !== strpos( $row, 'value="wpcpm_sponsor_agr_return"' ),
	substr_count( $row, 'name="wpcpm_sponsor_agr_post" value="' . $queued . '"' ),
	substr_count( $row, 'name="wpcpm_return" value="dashboard" data-anchor="sponsor-agreements"' ),
	false !== strpos( $row, 'minlength="20" maxlength="2000" required' ),
	false !== strpos( $row, 'value="wpcpm_sponsor_agr_reinstate"' ),
), array( true, true, true, true, 2, 2, true, false ) );
// The institution side's words, which the sponsor side shares: Accept it, and Return it with this
// note under the fold, whose summary keeps its own words. Accept it asks first, as the institution
// side's Accept on the same page does, and in the same place, the button: a No posts nothing, so the
// page's double-submit guard never sees a press that went nowhere.
$accept_asks = 'data-wpcpm-confirm="Accept the signed agreement from Other Sponsor? It is recorded as accepted with today&#039;s date and the 1 person at the company is emailed. Nothing is opened or closed by this: a company&#039;s Sponsor Dashboard never depended on an agreement. It can be taken out of force on the Sponsors screen&#039;s Agreements tab."';
ck( 'its buttons read Accept it and, under the fold Return with a note, Return it with this note, as the institution side\'s do', array(
	false !== strpos( $row, '<button type="submit" class="button button-primary" ' . $accept_asks . '>Accept it</button>' ),
	false !== strpos( $row, '<summary class="button">Return with a note</summary>' ),
	false !== strpos( $row, '<button type="submit" class="button">Return it with this note</button>' ),
	strpos( $row, '>Accept<' ),
	strpos( $row, 'Send back' ),
), array( true, true, true, false, false ) );
ck( 'Accept it asks before it acts, naming the company, that it is recorded as accepted, how many people at the company are emailed and where it can be taken out of force; Return asks nothing, its note is the press', array(
	substr_count( $row, 'data-wpcpm-confirm="' ),
	false !== strpos( $row, $accept_asks ),
	false !== strpos( $row, '<form class="wpcpm-sponsor-agreement__form" method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once>' ),
), array( 1, true, true ) );
// A second account at the company, for the plural: the question counts the people the press emails.
$kept_23              = isset( $GLOBALS['umeta'][23] ) ? $GLOBALS['umeta'][23] : null;
$GLOBALS['umeta'][23] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $T, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
ob_start();
WPCPM_Sponsor_Agreement::render_decision( $queued, WPCPM_Return::DASHBOARD );
$two = (string) ob_get_clean();
if ( null === $kept_23 ) {
	unset( $GLOBALS['umeta'][23] );
} else {
	$GLOBALS['umeta'][23] = $kept_23;
}
ck( 'with two accounts at the company the question says so, in the plural', false !== strpos( $two, 'and the 2 people at the company are emailed.' ), true );

$GLOBALS['uid'] = 21;
ob_start();
WPCPM_Sponsor_Agreement::render_decision( $queued, WPCPM_Return::DASHBOARD );
ck( 'a sponsor account is shown no decision', (string) ob_get_clean(), '' );

$GLOBALS['uid'] = 1;
$gone           = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Agreement::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Out of force' ) );
update_post_meta( $gone, WPCPM_Sponsor_Agreement::META_SPONSOR, $T );
update_post_meta( $gone, WPCPM_Sponsor_Agreement::META_STATE, WPCPM_Sponsor_Agreement::STATE_REVOKED );
update_post_meta( $gone, WPCPM_Sponsor_Agreement::META_KIND, WPCPM_Sponsor_Agreement::KIND_LEGACY );
ob_start();
WPCPM_Sponsor_Agreement::render_decision( $gone, WPCPM_Return::DASHBOARD );
$row = (string) ob_get_clean();
ck( 'an agreement out of force gets the way back and, being a Drive copy, no download', array( false !== strpos( $row, 'value="wpcpm_sponsor_agr_reinstate"' ), false !== strpos( $row, 'wpcpm_sponsor_agr_download' ), false !== strpos( $row, 'value="wpcpm_sponsor_agr_accept"' ) ), array( true, false, false ) );
ck( 'and the way back reads Reinstate, the institution side\'s word for it, and asks first', array( false !== strpos( $row, '<button type="submit" class="button" data-wpcpm-confirm="Put this agreement back in force? Everybody at the company is emailed.">Reinstate</button>' ), strpos( $row, 'Put it back in force' ) ), array( true, false ) );
ck( 'the question is the one the class keeps for every page that reinstates, the Sponsors screen\'s Agreements tab included', WPCPM_Sponsor_Agreement::reinstate_question(), 'Put this agreement back in force? Everybody at the company is emailed.' );
// The words are the institution's own where the dashboard draws them: its review block's Accept it
// and Return it with this note, and the Reinstate the Collaboration Agreements card offers a
// revoked agreement. Read from the two sources, since this suite draws neither.
$institution_words = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-institution-panel.php' ) . (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-administrators-cards.php' );
ck( 'and the three are the institution\'s: Accept it and Return it with this note in its review block, Reinstate on its revoked agreements', array(
	false !== strpos( $institution_words, "esc_html__( 'Accept it', 'wpcredits-program-manager' )" ),
	false !== strpos( $institution_words, "esc_html__( 'Return it with this note', 'wpcredits-program-manager' )" ),
	false !== strpos( $institution_words, "esc_html__( 'Reinstate', 'wpcredits-program-manager' )" ),
), array( true, true, true ) );
ck( 'revoked_all() lists it', in_array( $gone, WPCPM_Sponsor_Agreement::revoked_all(), true ), true );
wp_delete_post( $gone, true );

$_POST = array( 'wpcpm_sponsor_agr_post' => $queued, 'wpcpm_return' => 'dashboard' );
// That channel is the Institutions screen's, whose own agreement outcomes share this class's keys, so
// the outcome goes there under the class's prefix and the page reads the company's words under it.
ck( 'a decision taken on the dashboard lands back there, its sentence on that page\'s channel, under the class\'s prefix', array( ran( 'handle_accept' ), end( $GLOBALS['flash'] ), WPCPM_Sponsor_Agreement::DASHBOARD_PREFIX ), array( 'https://example.test/administrator-dashboard/#wpcpm-sponsor-agreements', array( 'institutions_dashboard', 'sponsor-agreement-accepted' ), 'sponsor-' ) );
$_POST = array();

echo "\n=== A minted key is accepted everywhere a record id was ===\n";
$SPO     = 'spoAAAA0000000001';
$spo_doc = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Agreement::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Minted sponsor document' ) );
update_post_meta( $spo_doc, WPCPM_Sponsor_Agreement::META_SPONSOR, $SPO );
ck( 'posts_for() finds a spo sponsor\'s documents', array_map( static function ( $post ) { return $post->ID; }, WPCPM_Sponsor_Agreement::posts_for( $SPO ) ), array( $spo_doc ) );

echo "\n=== Notes keep their backslashes ===\n";
// Post meta unslashes what it is handed, as core's does, and a note is read from the form
// unslashed: it keeps a backslash only when it is written as a slashed copy.
$V                    = 'recSPN00000000003';
$typed_note           = 'Page 4 is in C:\drafts\scans, two \\\\ in a row, and the "signed" copy is missing.';
$GLOBALS['users'][22] = new WP_User( 22, array( 'wpcpm_sponsor' ), 'Member Three', 'maciej@a8c.com' );
$GLOBALS['umeta'][22] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $V, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
seed_index( $V, 'Third Sponsor' );

$GLOBALS['uid'] = 22;
$_POST          = array( 'wpcpm_sponsor' => $V, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$typed_doc      = (int) WPCPM_Sponsor_Agreement::posts_for( $V )[0]->ID;
$GLOBALS['uid'] = 1;
$_POST          = array( 'wpcpm_sponsor_agr_post' => $typed_doc, 'wpcpm_sponsor_agr_note' => $typed_note );
ran( 'handle_return' );
ck( 'a return note is kept exactly as typed', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $typed_doc, WPCPM_Sponsor_Agreement::META_NOTE, true ) ), array( 'agreement-returned', $typed_note ) );

$GLOBALS['uid'] = 22;
$_POST          = array( 'wpcpm_sponsor' => $V, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$typed_doc      = (int) WPCPM_Sponsor_Agreement::posts_for( $V )[0]->ID;
$GLOBALS['uid'] = 1;
$_POST          = array( 'wpcpm_sponsor_agr_post' => $typed_doc );
ran( 'handle_accept' );
$_POST = array( 'wpcpm_sponsor_agr_post' => $typed_doc, 'wpcpm_sponsor_agr_note' => $typed_note );
ran( 'handle_revoke' );
ck( 'and so is a revocation note', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $typed_doc, WPCPM_Sponsor_Agreement::META_NOTE, true ) ), array( 'agreement-revoked', $typed_note ) );
$_POST = array();

echo "\n=== Notes are counted and mailed as typed ===\n";
// A manager's words, and the form the cleaner hands them back in: "<3 our " is a "<" that opens no
// tag, written `&lt;`; the tags go; the lone ">" stays; "< 12 welcome, adults >" is kept as typed;
// and after a "<" with no ">" to close it the quote marks and ampersands are entities too.
$n_note    = "We <3 our <b>team</b> > all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 < 12. Q&A: \"blocks\" & themes, it's free.";
$n_note_cl = "We &lt;3 our team > all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 &lt; 12. Q&amp;A: &quot;blocks&quot; &amp; themes, it&#039;s free.";
$n_note_as = "We <3 our team > all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 < 12. Q&A: \"blocks\" & themes, it's free.";
/** A typing of exactly $limit characters as a person counts them (a line break is one), made of $unit and padded with "x". */
function agr_filled( $unit, $limit ) {
	$one  = mb_strlen( str_replace( "\r\n", "\n", $unit ) );
	$text = str_repeat( $unit, intdiv( $limit, $one ) );
	return $text . str_repeat( 'x', $limit - mb_strlen( str_replace( "\r\n", "\n", $text ) ) );
}
/** Upload a signed copy as the member, and answer the document waiting. */
function agr_upload( $record, $member ) {
	$GLOBALS['uid'] = $member;
	$_POST          = array( 'wpcpm_sponsor' => $record, 'wpcpm_sponsor_agr_signed' => '1' );
	post_file( $GLOBALS['good'] );
	ran( 'handle_upload' );
	$GLOBALS['uid'] = 1;
	return (int) WPCPM_Sponsor_Agreement::posts_for( $record )[0]->ID;
}
/** A sponsor of its own in the index, with one member, so each case below starts with nothing in review. */
function agr_sponsor( $record, $member, $name ) {
	$GLOBALS['users'][ $member ] = new WP_User( $member, array( 'wpcpm_sponsor' ), 'Member ' . $member, 'maciej@a8c.com' );
	$GLOBALS['umeta'][ $member ] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $record, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
	seed_index( $record, $name );
}
$GLOBALS['good'] = $good;
ck( 'the stand-in is core\'s cleaner: it hands the note back in this form', sanitize_textarea_field( $n_note ), $n_note_cl );

agr_sponsor( 'recSPN00000000009', 25, 'Sponsor Nine' );
$n_doc = agr_upload( 'recSPN00000000009', 25 );
$_POST = array( 'wpcpm_sponsor_agr_post' => $n_doc, 'wpcpm_sponsor_agr_note' => 'Page 4 < 5 & 6 too.' );
ran( 'handle_return' );
ck( 'nineteen characters as typed are under twenty, though the cleaner stores them in twenty-six: nothing is returned', array( mb_strlen( sanitize_textarea_field( 'Page 4 < 5 & 6 too.' ) ), end( $GLOBALS['flash'] )[1], (string) get_post_meta( $n_doc, WPCPM_Sponsor_Agreement::META_STATE, true ) ), array( 26, 'agreement-note', 'submitted' ) );

agr_sponsor( 'recSPN00000000010', 26, 'Sponsor Ten' );
$n_doc = agr_upload( 'recSPN00000000010', 26 );
$n_at  = agr_filled( "Ages 8 < 12. Q&A: \"blocks\" & themes, it's free.\r\n", 2000 );
$GLOBALS['mail'] = array();
$_POST = array( 'wpcpm_sponsor_agr_post' => $n_doc, 'wpcpm_sponsor_agr_note' => $n_at . 'x' );
ran( 'handle_return' );
ck( 'one character over 2,000 as typed is refused, and nothing is mailed', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $n_doc, WPCPM_Sponsor_Agreement::META_STATE, true ), $GLOBALS['mail'] ), array( 'agreement-note', 'submitted', array() ) );
$_POST = array( 'wpcpm_sponsor_agr_post' => $n_doc, 'wpcpm_sponsor_agr_note' => $n_at );
ran( 'handle_return' );
ck( 'a note of 2,000 characters as typed, with 41 line breaks and longer as the cleaner stores it, returns the document', array( substr_count( $n_at, "\r\n" ), mb_strlen( sanitize_textarea_field( $n_at ) ) > 2000, end( $GLOBALS['flash'] )[1], (string) get_post_meta( $n_doc, WPCPM_Sponsor_Agreement::META_STATE, true ), (string) get_post_meta( $n_doc, WPCPM_Sponsor_Agreement::META_NOTE, true ) === sanitize_textarea_field( $n_at ) ), array( 41, true, 'agreement-returned', 'returned', true ) );
ck( 'and the company is mailed every character of it as typed', isset( $GLOBALS['mail'][0]['mail']['body'] ) && false !== strpos( $GLOBALS['mail'][0]['mail']['body'], "\r\n\r\n" . $n_at . "\r\n\r\n" ), true );

// Both limits are counted on what was typed, as the box counts it, for a return and for Take it
// out of force alike: an entity typed out is as many characters as it was typed with, and a
// reference typed out after a "<", which the cleaner writes in core's longer form (`&#50;` as
// `&#050;`), is counted as it was typed too.
$agr_length = static function ( $n, $handler, $typed ) {
	$record = 'recSPN000000000' . ( 40 + $n );
	agr_sponsor( $record, 40 + $n, 'Sponsor Length ' . $n );
	$doc = agr_upload( $record, 40 + $n );

	if ( 'handle_revoke' === $handler ) {
		$_POST = array( 'wpcpm_sponsor_agr_post' => $doc );
		ran( 'handle_accept' );
	}

	$GLOBALS['mail']        = array();
	$GLOBALS['kept_typing'] = array();
	$_POST                  = array( 'wpcpm_sponsor_agr_post' => $doc, 'wpcpm_sponsor_agr_note' => $typed );
	ran( $handler );
	$form = WPCPM_Typed_Text::KEPT_CHANNEL . ( 'handle_revoke' === $handler ? WPCPM_Sponsor_Agreement::TYPED_REVOKE : WPCPM_Sponsor_Agreement::TYPED_RETURN ) . ':' . $doc;
	$got  = array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $doc, WPCPM_Sponsor_Agreement::META_STATE, true ), $GLOBALS['kept_typing'][1][ $form ]['typed'] ?? array() );

	$GLOBALS['mail']        = array();
	$GLOBALS['kept_typing'] = array();

	return $got;
};
$agr_at_most = str_repeat( 'Clause 8 < 12 &#9; ok. ', 86 ) . 'Please resend it soon.';
ck( 'the note below is 2,000 characters as typed, and the count of what the cleaner keeps reads it as more', array( WPCPM_Typed_Text::box_length( $agr_at_most ), WPCPM_Typed_Text::typed_length( sanitize_textarea_field( $agr_at_most ) ) > WPCPM_Sponsor_Agreement::MAX_NOTE, WPCPM_Typed_Text::cleaner_loses( $agr_at_most, 'lines' ) ), array( WPCPM_Sponsor_Agreement::MAX_NOTE, true, false ) );
ck( '"Type &lt;b&gt; for bold", 23 characters typed out, returns the document', $agr_length( 1, 'handle_return', 'Type &lt;b&gt; for bold' ), array( 'agreement-returned', 'returned', array() ) );
ck( 'a note of exactly 20 characters as typed, "Type &lt;b&gt; bold.", returns it', $agr_length( 2, 'handle_return', 'Type &lt;b&gt; bold.' ), array( 'agreement-returned', 'returned', array() ) );
ck( 'one of 19 characters as typed, "Clause 1 < &#50; ok", is refused as too short, though the cleaner writes it longer, and comes back to its box', $agr_length( 3, 'handle_return', 'Clause 1 < &#50; ok' ), array( 'agreement-note', 'submitted', array( 'note' => 'Clause 1 < &#50; ok' ) ) );
ck( 'one of exactly 2,000 characters as typed returns it', $agr_length( 4, 'handle_return', $agr_at_most ), array( 'agreement-returned', 'returned', array() ) );
ck( 'one of 2,001 characters as typed, each "&lt;" typed out, is refused as too long, the typing kept up to the box\'s room', $agr_length( 5, 'handle_return', str_repeat( '&lt;', 500 ) . '.' ), array( 'agreement-note', 'submitted', array( 'note' => str_repeat( '&lt;', 500 ) ) ) );
ck( 'Take it out of force counts its note the same way: "Type &lt;b&gt; for bold" takes the agreement out of force', $agr_length( 6, 'handle_revoke', 'Type &lt;b&gt; for bold' ), array( 'agreement-revoked', 'revoked', array() ) );
ck( 'and "Clause 1 < &#50; ok" is refused as too short, and comes back to its box', $agr_length( 7, 'handle_revoke', 'Clause 1 < &#50; ok' ), array( 'agreement-revoke-note', 'accepted', array( 'note' => 'Clause 1 < &#50; ok' ) ) );

agr_sponsor( 'recSPN00000000011', 27, 'Sponsor Eleven' );
$n_doc = agr_upload( 'recSPN00000000011', 27 );
$GLOBALS['mail'] = array();
$_POST = array( 'wpcpm_sponsor_agr_post' => $n_doc, 'wpcpm_sponsor_agr_note' => $n_note );
ran( 'handle_return' );
ck( 'a return note with "<b>team</b>" in it is refused now, because the cleaner would take "<b>" and "</b>", and the document waits as it was, nothing mailed', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $n_doc, WPCPM_Sponsor_Agreement::META_STATE, true ), (string) get_post_meta( $n_doc, WPCPM_Sponsor_Agreement::META_NOTE, true ), $GLOBALS['mail'] ), array( 'agreement-note-loss', 'submitted', '', array() ) );
// The same words without the tag: a "<3" whose "<" opens no tag, a "<" that a ">" closes after a
// space, and a "<" with no ">" after it, the quote marks and the ampersands after it entities too.
$n_sent    = "We <3 our team, all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 < 12. Q&A: \"blocks\" & themes, it's free.";
$n_sent_cl = "We &lt;3 our team, all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 &lt; 12. Q&amp;A: &quot;blocks&quot; &amp; themes, it&#039;s free.";
$_POST     = array( 'wpcpm_sponsor_agr_post' => $n_doc, 'wpcpm_sponsor_agr_note' => $n_sent );
ran( 'handle_return' );
ck( 'a returned document keeps the note as the cleaner left it', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $n_doc, WPCPM_Sponsor_Agreement::META_NOTE, true ) ), array( 'agreement-returned', $n_sent_cl ) );
ck( 'and the mail says what was typed, line by line, with no entity in it', isset( $GLOBALS['mail'][0]['mail']['body'] ) ? $GLOBALS['mail'][0]['mail']['body'] : null, "Manager read your Collaboration Agreement and sent it back, with this note:\r\n\r\n" . $n_sent . "\r\n\r\nUpload the corrected document from the agreement card on your dashboard, or reply to this message.\r\n\r\nhttps://example.test/sponsor-dashboard/" );

agr_sponsor( 'recSPN00000000012', 28, 'Sponsor Twelve' );
$n_doc = agr_upload( 'recSPN00000000012', 28 );
$_POST = array( 'wpcpm_sponsor_agr_post' => $n_doc );
ran( 'handle_accept' );
$GLOBALS['mail'] = array();
$_POST = array( 'wpcpm_sponsor_agr_post' => $n_doc, 'wpcpm_sponsor_agr_note' => $n_note );
ran( 'handle_revoke' );
ck( 'a revocation note with "<b>team</b>" in it is refused now, because the cleaner would take "<b>" and "</b>", and the agreement stays in force, nothing mailed', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $n_doc, WPCPM_Sponsor_Agreement::META_STATE, true ), $GLOBALS['mail'] ), array( 'agreement-revoke-loss', 'accepted', array() ) );
$_POST = array( 'wpcpm_sponsor_agr_post' => $n_doc, 'wpcpm_sponsor_agr_note' => $n_sent );
ran( 'handle_revoke' );
ck( 'a revocation note is mailed as typed too', array( end( $GLOBALS['flash'] )[1], isset( $GLOBALS['mail'][0]['mail']['body'] ) ? $GLOBALS['mail'][0]['mail']['body'] : null ), array( 'agreement-revoked', "Your Collaboration Agreement is no longer in force, with this note from the program:\r\n\r\n" . $n_sent . "\r\n\r\nYour dashboard, your offers and your codes are unchanged: they never depended on an agreement. Reply to this message if you would like to talk about a new one." ) );
ck( 'and kept on the document as the cleaner left it', (string) get_post_meta( $n_doc, WPCPM_Sponsor_Agreement::META_NOTE, true ), $n_sent_cl );
// A note an earlier release stored with its tag taken out, built here as the cleaner left it, is
// still mailed as it reads: the mail reads the stored form back.
$GLOBALS['mail'] = array();
$mail_revoked    = new ReflectionMethod( 'WPCPM_Sponsor_Agreement', 'mail_revoked' );
// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
if ( PHP_VERSION_ID < 80100 ) {
	$mail_revoked->setAccessible( true );
}
$mail_revoked->invoke( null, 'recSPN00000000012', $n_note_cl );
ck( 'and a note stored the way a tag-holding one was stored before is mailed as it reads, with no entity in it', isset( $GLOBALS['mail'][0]['mail']['body'] ) ? $GLOBALS['mail'][0]['mail']['body'] : null, "Your Collaboration Agreement is no longer in force, with this note from the program:\r\n\r\n" . $n_note_as . "\r\n\r\nYour dashboard, your offers and your codes are unchanged: they never depended on an agreement. Reply to this message if you would like to talk about a new one." );
$_POST = array();

echo "\n=== The return note: a typing the cleaner would take words from is refused, and kept ===\n";
/**
 * One document's Return with a note as a browser shows it to the current user: what its box holds
 * and whether its fold is open, or null when no form is drawn. The parser reads a CR LF as one LF and
 * drops the one line feed right after a text area's opening tag.
 */
function agr_note_box( $post_id ) {
	ob_start();
	WPCPM_Sponsor_Agreement::render_decision( $post_id, WPCPM_Return::DASHBOARD );
	$html = (string) ob_get_clean();
	if ( ! preg_match( '/<textarea id="wpcpm-agr-note-' . (int) $post_id . '" name="wpcpm_sponsor_agr_note"[^>]*>(.*?)<\/textarea>/s', $html, $m ) ) {
		return null;
	}
	$shown = (string) preg_replace( '/^\n/', '', str_replace( "\r\n", "\n", $m[1] ) );
	return array( 'shown' => html_entity_decode( $shown, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'open' => false !== strpos( $html, '<details class="wpcpm-sponsor-agreement__return" open>' ) );
}
$GLOBALS['users'][2] = new WP_User( 2, array( 'administrator' ), 'Manager Two', 'maciej+two@a8c.com' );
$GLOBALS['manage']   = array( 1, 2 );
$K = 'recSPN00000000013';
agr_sponsor( $K, 29, 'Sponsor Thirteen' );
agr_sponsor( 'recSPN00000000014', 30, 'Sponsor Fourteen' );
$k_doc   = agr_upload( $K, 29 );
$k_other = agr_upload( 'recSPN00000000014', 30 );
$k_note  = "Kids <12 free, adults >18 pay.\r\nA second line, \"quoted\" & C:\\drafts";
$k_back  = array( 'wpcpm_sponsor_agr_post' => $k_doc, 'wpcpm_return' => 'dashboard' );
$k_dash  = 'https://example.test/administrator-dashboard/#wpcpm-sponsor-agreements';
$k_event = count( (array) get_post_meta( $k_doc, WPCPM_Sponsor_Agreement::META_EVENT ) );
$GLOBALS['kept_typing'] = array(); $GLOBALS['mail'] = array(); $GLOBALS['audit'] = array();
$k_block = WPCPM_Sponsors_Index::row( $K )['agreement'] ?? null;
$_POST  = $k_back + array( 'wpcpm_sponsor_agr_note' => $k_note );
$landed = ran( 'handle_return' );
ck( 'a note the cleaner would cut to "Kids 18 pay." is refused, back on the Administrator Dashboard, with the sentence for a return, naming the box by its label', array( $landed, end( $GLOBALS['flash'] ), WPCPM_Sponsor_Agreement::manager_messages()['agreement-note-loss'] ?? null ), array( $k_dash, array( 'institutions_dashboard', 'sponsor-agreement-note-loss' ), array( 'error', 'Nothing was returned, because WordPress would remove part of what you typed in "A note for the company". To keep every word, put a space after each "<" (or write "less than") and after each "%", then return it again.' ) ) );
ck( 'and nothing is done: the document waits, no note or decision on it, no event, no change to the row, no mail, no audit row, and no lock left on the company', array( (string) get_post_meta( $k_doc, WPCPM_Sponsor_Agreement::META_STATE, true ), (string) get_post_meta( $k_doc, WPCPM_Sponsor_Agreement::META_NOTE, true ), (string) get_post_meta( $k_doc, WPCPM_Sponsor_Agreement::META_DECIDED_BY, true ), count( (array) get_post_meta( $k_doc, WPCPM_Sponsor_Agreement::META_EVENT ) ) - $k_event, ( WPCPM_Sponsors_Index::row( $K )['agreement'] ?? null ) === $k_block, $GLOBALS['mail'], $GLOBALS['audit'], array_key_exists( WPCPM_Sponsor_Agreement::LOCK_PREFIX . $K, $GLOBALS['opts'] ) ), array( 'submitted', '', '', 0, true, array(), array(), false ) );
ck( 'the document\'s Return with a note draws open, its box holding the note as it was typed, a line break as the box counts it', agr_note_box( $k_doc ), array( 'shown' => str_replace( "\r\n", "\n", $k_note ), 'open' => true ) );
ck( 'and only once: drawn again, the box is empty and the fold closed', agr_note_box( $k_doc ), array( 'shown' => '', 'open' => false ) );
$_POST = $k_back + array( 'wpcpm_sponsor_agr_note' => "\r\n" . $k_note );
ran( 'handle_return' );
ck( 'a kept note that starts with a line break comes back with it: the box writes a line feed of its own for the one the parser drops', agr_note_box( $k_doc )['shown'] ?? null, "\n" . str_replace( "\r\n", "\n", $k_note ) );

$_POST = $k_back + array( 'wpcpm_sponsor_agr_note' => $k_note );
ran( 'handle_return' );
$GLOBALS['uid'] = 2;
$k_seen_two     = agr_note_box( $k_doc );
$GLOBALS['uid'] = 1;
$k_seen_other   = agr_note_box( $k_other );
ck( 'another manager never sees it on the same document, nor does another document\'s box, while the one who typed it does', array( $k_seen_two, $k_seen_other, agr_note_box( $k_doc )['shown'] ?? null ), array( array( 'shown' => '', 'open' => false ), array( 'shown' => '', 'open' => false ), str_replace( "\r\n", "\n", $k_note ) ) );

$GLOBALS['referer'] = 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue';
$_POST              = array( 'wpcpm_sponsor_agr_post' => $k_doc, 'wpcpm_sponsor_agr_note' => 'Use code SAVE20<b></b> on page 4, please.' );
ck( 'posted without the way back, the refusal lands on the Sponsors screen it came from, on that screen\'s channel', array( ran( 'handle_return' ), end( $GLOBALS['flash'] ), (string) get_post_meta( $k_doc, WPCPM_Sponsor_Agreement::META_STATE, true ) ), array( $GLOBALS['referer'], array( 'sponsors_admin', 'agreement-note-loss' ), 'submitted' ) );
agr_note_box( $k_doc );

$_POST = $k_back + array( 'wpcpm_sponsor_agr_note' => 'Too short' );
ck( 'a note under its least is refused as before, and its box now comes back with it', array( ran( 'handle_return' ), end( $GLOBALS['flash'] ), agr_note_box( $k_doc ) ), array( $k_dash, array( 'institutions_dashboard', 'sponsor-agreement-note' ), array( 'shown' => 'Too short', 'open' => true ) ) );
$_POST = $k_back + array( 'wpcpm_sponsor_agr_note' => $n_at . 'xyz' );
ck( 'and so does a note over its limit, kept up to the box\'s limit of 2,000', array( ran( 'handle_return' ), end( $GLOBALS['flash'] ), agr_note_box( $k_doc ) ), array( $k_dash, array( 'institutions_dashboard', 'sponsor-agreement-note' ), array( 'shown' => str_replace( "\r\n", "\n", $n_at ), 'open' => true ) ) );
$_POST = $k_back + array( 'wpcpm_sponsor_agr_note' => '   ' );
ck( 'a note left empty keeps nothing: there is nothing to give back', array( ran( 'handle_return' ), end( $GLOBALS['flash'] ), $GLOBALS['kept_typing'][1] ?? array() ), array( $k_dash, array( 'institutions_dashboard', 'sponsor-agreement-note' ), array() ) );

// Refused and kept, then posted with the fix while another press holds the company's lock: the
// document still waits, and its box is not filled with the typing the fix replaced.
$_POST = $k_back + array( 'wpcpm_sponsor_agr_note' => $k_note );
ran( 'handle_return' );
$GLOBALS['opts'][ WPCPM_Sponsor_Agreement::LOCK_PREFIX . $K ] = time();
$_POST   = $k_back + array( 'wpcpm_sponsor_agr_note' => 'Kids under 12 free, adults over 18 pay.' );
$k_fixed = ran( 'handle_return' );
unset( $GLOBALS['opts'][ WPCPM_Sponsor_Agreement::LOCK_PREFIX . $K ] );
ck( 'a typing a refusal kept goes when the form is posted again, so a press refused for the lock draws the box empty, not with what the fix replaced', array( $k_fixed, end( $GLOBALS['flash'] ), (string) get_post_meta( $k_doc, WPCPM_Sponsor_Agreement::META_STATE, true ), agr_note_box( $k_doc ) ), array( $k_dash, array( 'institutions_dashboard', 'sponsor-agreement-busy' ), 'submitted', array( 'shown' => '', 'open' => false ) ) );

$k_keeps = "We <3 WordPress.\r\nAges 8 < 12 welcome, adults > 18 pay, 100% of them, Q&A: \"blocks\" & themes, &amp; and &copy; typed out, and \xF0\x9F\x98\x80";
$GLOBALS['mail'] = array();
$_POST           = $k_back + array( 'wpcpm_sponsor_agr_note' => $k_keeps );
ck( 'a note the cleaner keeps whole returns the document as before: kept as the cleaner left it, mailed as typed, and nothing kept for the form', array( ran( 'handle_return' ), end( $GLOBALS['flash'] ), (string) get_post_meta( $k_doc, WPCPM_Sponsor_Agreement::META_STATE, true ), (string) get_post_meta( $k_doc, WPCPM_Sponsor_Agreement::META_NOTE, true ), isset( $GLOBALS['mail'][0]['mail']['body'] ) && false !== strpos( $GLOBALS['mail'][0]['mail']['body'], "\r\n\r\n" . WPCPM_Typed_Text::mail_text( sanitize_textarea_field( $k_keeps ) ) . "\r\n\r\n" ), $GLOBALS['kept_typing'][1] ?? array() ), array( $k_dash, array( 'institutions_dashboard', 'sponsor-agreement-returned' ), 'returned', sanitize_textarea_field( $k_keeps ), true, array() ) );
$GLOBALS['manage'] = array( 1 );
$_POST             = array();

echo "\n=== Take it out of force: a note the cleaner would take words from is refused, and kept ===\n";
/** Upload and accept a signed copy for the sponsor, and answer the agreement now in force. */
function agr_in_force( $record, $member ) {
	$doc   = agr_upload( $record, $member );
	$_POST = array( 'wpcpm_sponsor_agr_post' => $doc );
	ran( 'handle_accept' );
	return $doc;
}
$GLOBALS['manage'] = array( 1, 2 );
$V2 = 'recSPN00000000015';
agr_sponsor( $V2, 31, 'Sponsor Fifteen' );
agr_sponsor( 'recSPN00000000016', 32, 'Sponsor Sixteen' );
$v_doc   = agr_in_force( $V2, 31 );
$v_other = agr_in_force( 'recSPN00000000016', 32 );
$v_tab   = 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=agreements';
$v_event = count( (array) get_post_meta( $v_doc, WPCPM_Sponsor_Agreement::META_EVENT ) );
$GLOBALS['referer']     = $v_tab;
$GLOBALS['kept_typing'] = array(); $GLOBALS['mail'] = array(); $GLOBALS['audit'] = array();
$v_block = WPCPM_Sponsors_Index::row( $V2 )['agreement'] ?? null;
$_POST  = array( 'wpcpm_sponsor_agr_post' => $v_doc, 'wpcpm_sponsor_agr_note' => $k_note );
$landed = ran( 'handle_revoke' );
ck( 'a note the cleaner would cut to "Kids 18 pay." is refused, back on the Agreements tab, with the sentence for Take it out of force, naming the box by the first words of its label', array( $landed, end( $GLOBALS['flash'] ), WPCPM_Sponsor_Agreement::manager_messages()['agreement-revoke-loss'] ?? null ), array( $v_tab, array( 'sponsors_admin', 'agreement-revoke-loss' ), array( 'error', 'Nothing was revoked, because WordPress would remove part of what you typed in "Why it is out of force, in your own words". To keep every word, put a space after each "<" (or write "less than") and after each "%", then take it out of force again.' ) ) );
ck( 'and nothing is done: the agreement stays in force, no note or decision on it, no event, no change to the row, no mail, no audit row, and no lock left on the company', array( (string) get_post_meta( $v_doc, WPCPM_Sponsor_Agreement::META_STATE, true ), (string) get_post_meta( $v_doc, WPCPM_Sponsor_Agreement::META_NOTE, true ), count( (array) get_post_meta( $v_doc, WPCPM_Sponsor_Agreement::META_EVENT ) ) - $v_event, ( WPCPM_Sponsors_Index::row( $V2 )['agreement'] ?? null ) === $v_block, $GLOBALS['mail'], $GLOBALS['audit'], array_key_exists( WPCPM_Sponsor_Agreement::LOCK_PREFIX . $V2, $GLOBALS['opts'] ), WPCPM_Sponsors_Index::row( $V2 )['agreement']['status'] ), array( 'accepted', '', 0, true, array(), array(), false, 'Accepted' ) );
ck( 'the form gets the note back as it was typed, a line break as the box counts it', WPCPM_Sponsor_Agreement::kept_revoke_note( $v_doc ), str_replace( "\r\n", "\n", $k_note ) );
ck( 'and only once: asked again, there is nothing', WPCPM_Sponsor_Agreement::kept_revoke_note( $v_doc ), null );

$_POST = array( 'wpcpm_sponsor_agr_post' => $v_doc, 'wpcpm_sponsor_agr_note' => $k_note );
ran( 'handle_revoke' );
$GLOBALS['uid']  = 2;
$v_seen_two      = WPCPM_Sponsor_Agreement::kept_revoke_note( $v_doc );
$GLOBALS['uid']  = 1;
$v_seen_other    = WPCPM_Sponsor_Agreement::kept_revoke_note( $v_other );
ck( 'another manager never gets it, nor does another agreement\'s form, while the one who typed it does', array( $v_seen_two, $v_seen_other, WPCPM_Sponsor_Agreement::kept_revoke_note( $v_doc ) ), array( null, null, str_replace( "\r\n", "\n", $k_note ) ) );
// A return refused while the document waited keeps its typing for the Return with a note box; the
// document accepted, Take it out of force's box is another form and never gets it.
agr_sponsor( 'recSPN00000000017', 33, 'Sponsor Seventeen' );
$v_waits = agr_upload( 'recSPN00000000017', 33 );
$_POST   = array( 'wpcpm_sponsor_agr_post' => $v_waits, 'wpcpm_sponsor_agr_note' => $k_note );
ran( 'handle_return' );
$_POST = array( 'wpcpm_sponsor_agr_post' => $v_waits );
ran( 'handle_accept' );
ck( 'nor does the same document\'s other form: a return\'s kept note is not handed to Take it out of force once the document is accepted', array( (string) get_post_meta( $v_waits, WPCPM_Sponsor_Agreement::META_STATE, true ), WPCPM_Sponsor_Agreement::kept_revoke_note( $v_waits ) ), array( 'accepted', null ) );
$GLOBALS['kept_typing'] = array();

$_POST = array( 'wpcpm_sponsor_agr_post' => $v_doc, 'wpcpm_sponsor_agr_note' => 'Too short' );
ck( 'a note under its least is refused as before, and the form now gets it back', array( ran( 'handle_revoke' ), end( $GLOBALS['flash'] ), WPCPM_Sponsor_Agreement::kept_revoke_note( $v_doc ) ), array( $v_tab, array( 'sponsors_admin', 'agreement-revoke-note' ), 'Too short' ) );
$_POST = array( 'wpcpm_sponsor_agr_post' => $v_doc, 'wpcpm_sponsor_agr_note' => $n_at . 'xyz' );
ck( 'and so does a note over its limit, kept up to the box\'s limit of 2,000', array( ran( 'handle_revoke' ), end( $GLOBALS['flash'] ), WPCPM_Sponsor_Agreement::kept_revoke_note( $v_doc ) ), array( $v_tab, array( 'sponsors_admin', 'agreement-revoke-note' ), str_replace( "\r\n", "\n", $n_at ) ) );
$_POST = array( 'wpcpm_sponsor_agr_post' => $v_doc, 'wpcpm_sponsor_agr_note' => '   ' );
ck( 'a note left empty keeps nothing: there is nothing to give back', array( ran( 'handle_revoke' ), end( $GLOBALS['flash'] ), $GLOBALS['kept_typing'][1] ?? array() ), array( $v_tab, array( 'sponsors_admin', 'agreement-revoke-note' ), array() ) );

// Refused and kept, then pressed with the fix while another press holds the company's lock: the
// agreement stays in force, and its form does not get back the typing the fix replaced.
$_POST = array( 'wpcpm_sponsor_agr_post' => $v_doc, 'wpcpm_sponsor_agr_note' => $k_note );
ran( 'handle_revoke' );
$GLOBALS['opts'][ WPCPM_Sponsor_Agreement::LOCK_PREFIX . $V2 ] = time();
$_POST   = array( 'wpcpm_sponsor_agr_post' => $v_doc, 'wpcpm_sponsor_agr_note' => 'Kids under 12 free, adults over 18 pay.' );
$v_fixed = ran( 'handle_revoke' );
unset( $GLOBALS['opts'][ WPCPM_Sponsor_Agreement::LOCK_PREFIX . $V2 ] );
ck( 'a typing a refusal kept goes when the form is posted again, so a press refused for the lock gets nothing back, not what the fix replaced', array( $v_fixed, end( $GLOBALS['flash'] ), (string) get_post_meta( $v_doc, WPCPM_Sponsor_Agreement::META_STATE, true ), WPCPM_Sponsor_Agreement::kept_revoke_note( $v_doc ) ), array( $v_tab, array( 'sponsors_admin', 'agreement-busy' ), 'accepted', null ) );

$GLOBALS['mail'] = array();
$_POST           = array( 'wpcpm_sponsor_agr_post' => $v_doc, 'wpcpm_sponsor_agr_note' => $k_keeps );
ck( 'a note the cleaner keeps whole takes the agreement out of force as before: kept as the cleaner left it, mailed as typed, and nothing kept for the form', array( ran( 'handle_revoke' ), end( $GLOBALS['flash'] ), (string) get_post_meta( $v_doc, WPCPM_Sponsor_Agreement::META_STATE, true ), (string) get_post_meta( $v_doc, WPCPM_Sponsor_Agreement::META_NOTE, true ), isset( $GLOBALS['mail'][0]['mail']['body'] ) && false !== strpos( $GLOBALS['mail'][0]['mail']['body'], "\r\n\r\n" . WPCPM_Typed_Text::mail_text( sanitize_textarea_field( $k_keeps ) ) . "\r\n\r\n" ), $GLOBALS['kept_typing'][1] ?? array() ), array( $v_tab, array( 'sponsors_admin', 'agreement-revoked' ), 'revoked', sanitize_textarea_field( $k_keeps ), true, array() ) );
$GLOBALS['manage'] = array( 1 );
$_POST             = array();

echo "\n=== House rules ===\n";
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $source ), 0 );
ck( 'wp_handle_upload() is never called', preg_match( '/wp_handle_upload|move_uploaded_file/', $source ), 0 );
// The sentence in the reader's own docblock that says where it is read, plus the reads inside
// the reader itself, must account for every `$_FILES` in the file: scoped to the method this
// way, a read that strayed into any other one would show up as a mismatch, which a bare total
// against a fixed number could not catch.
ck( '$_FILES is read in one helper and nowhere else', substr_count( method_with_doc( $source, 'uploaded_file' ), '$_FILES' ), substr_count( $source, '$_FILES' ) );
ck( 'the download is the only action with a nopriv arm', substr_count( $source, "'admin_post_nopriv_'" ), 1 );
ck( 'and the scan is the extraction\'s, not a second copy', preg_match( '/function (decode_names|names_contain|inflated_streams|inflate)\(/', $source ), 0 );

// 1.111.0: the site alone is the record of every transition, and the one-line retry_airtable()
// stub the sponsors sync called went with the sync, so nothing in the class carries the base's
// name any more: no pending mark, no retry, no field map.
ck( 'no pending mark, no retry and no mention of Airtable exists in the class', preg_match( '/airtable/i', $source ), 0 );
ck( 'no Airtable class exists', class_exists( 'WPCPM_Airtable' ), false );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
