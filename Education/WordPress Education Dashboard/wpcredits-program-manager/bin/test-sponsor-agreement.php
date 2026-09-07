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
 * - **An Airtable failure never fails the site action** on the upload path: the document is
 *   stored, the post carries the pending mark, and the sponsor is not asked to upload again.
 * - **What the cron forgets and what uninstall keeps.**
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
$GLOBALS['patched']     = array();
$GLOBALS['patch_fails'] = false;
$GLOBALS['ceiling']     = array();
$GLOBALS['audit']       = array();
$GLOBALS['mail']        = array();
$GLOBALS['mailed_raw']  = array();
$GLOBALS['nonce']       = array();
$GLOBALS['hooks']       = array();
$GLOBALS['referer']     = '';
$GLOBALS['temp_files']  = array();
$GLOBALS['post_types']  = array();
$GLOBALS['settings']    = array(
	'sponsors_table'            => 'tbluji8wknOZr55fa',
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
	public $ID = 0, $post_type = 'post', $post_title = '', $post_status = 'draft', $post_author = 0, $post_date = '', $post_date_gmt = '2026-09-06 09:00:00';
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
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
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
function check_admin_referer( $a ) { $GLOBALS['nonce'][] = $a; return true; }
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
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
function add_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ][] = $v; return true; }
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
		// A bare meta_key, which core reads as "this key is set". retry_airtable() asks this way.
		if ( $keep && isset( $args['meta_key'] ) ) { $keep = '' !== (string) get_post_meta( $p->ID, $args['meta_key'], true ); }
		if ( $keep ) { $out[] = $p; }
	}
	usort( $out, static function ( $a, $b ) { return $b->ID - $a->ID; } );
	if ( isset( $args['orderby']['date'] ) && 'ASC' === $args['orderby']['date'] ) { $out = array_reverse( $out ); }
	if ( isset( $args['offset'] ) ) { $out = array_slice( $out, (int) $args['offset'] ); }
	if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 ) { $out = array_slice( $out, 0, (int) $args['numberposts'] ); }
	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) { return array_map( static function ( $p ) { return $p->ID; }, $out ); }
	return $out;
}
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['hooks'][] = $h; }
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
class WPCPM_Sponsors_Sync {
	public static function fields() {
		return array( 'name' => 'Company Name', 'logo' => 'Logo', 'agr_status' => 'Agreement Status', 'agr_accepted_on' => 'Agreement Accepted On', 'agr_document' => 'Agreement Document' );
	}
}
class WPCPM_Airtable {
	public function update_records( $table, array $records ) {
		$GLOBALS['patched'][] = array( $table, $records );
		return $GLOBALS['patch_fails'] ? new WP_Error( 'wpcpm_airtable_http', 'Airtable said no.' ) : $records;
	}
}
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
class WPCPM_Flash { public static function set( $channel, $value, $user_id = 0 ) { $GLOBALS['flash'][] = array( $channel, $value ); } }
class WPCPM_Sponsors { const FLASH = 'sponsors_admin'; }
class WPCPM_Sponsors_Dashboard {
	const FLASH = 'sponsor_dashboard';
	public static function leave( $status, $card, $record = '', $detail = '' ) { throw new WPCPM_Test_Redirect( $status . '|' . $card . '|' . $record . '|' . $detail ); }
	public static function page_url() { return 'https://example.test/sponsor-dashboard/'; }
}

require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-request.php';
require_once __DIR__ . '/../includes/class-wpcpm-pdf-check.php';
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
 * A real file on disk, removed at the end of the run.
 *
 * @param string $bytes     What to write.
 * @param string $extension The extension, without the dot.
 * @return string Path.
 */
function temp_file( $bytes, $extension = 'pdf' ) {
	$path = tempnam( sys_get_temp_dir(), 'wpcpm-sagr-' ) . '.' . $extension;
	file_put_contents( $path, $bytes );
	$GLOBALS['temp_files'][] = $path;

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
 * The cells one PATCH carried.
 *
 * @param int $which Which PATCH; 0 for the first.
 * @return array
 */
function patched_cells( $which = 0 ) {
	return isset( $GLOBALS['patched'][ $which ][1][0]['fields'] ) ? $GLOBALS['patched'][ $which ][1][0]['fields'] : array();
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
 * Seed one sponsor into the index.
 *
 * @param string $record Sponsor record ID.
 * @param string $name   Company name.
 * @param array  $block  The `agreement` block.
 */
function seed_index( $record, $name, array $block = array() ) {
	$rows = isset( $GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'] ) ? $GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ]['rows'] : array();

	$rows[ $record ] = array_merge(
		WPCPM_Sponsors_Index::empty_row(),
		array(
			'record_id' => $record,
			'name'      => $name,
			'status'    => 'Approved',
			'agreement' => array_merge( array( 'status' => '', 'accepted_on' => '', 'has_document' => false ), $block ),
		)
	);

	$GLOBALS['opts'][ WPCPM_Sponsors_Index::OPT_NAME ] = array( 'v' => WPCPM_Sponsors_Index::VERSION, 'read' => $GLOBALS['clock'], 'rows' => $rows );
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
ck( 'the Airtable choices are spelled as the base spells them', array(
	WPCPM_Sponsor_Agreement::AIRTABLE_NOT_STARTED,
	WPCPM_Sponsor_Agreement::AIRTABLE_AWAITING,
	WPCPM_Sponsor_Agreement::AIRTABLE_ACCEPTED,
	WPCPM_Sponsor_Agreement::AIRTABLE_RETURNED,
	WPCPM_Sponsor_Agreement::AIRTABLE_ON_FILE,
	WPCPM_Sponsor_Agreement::AIRTABLE_REVOKED,
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
$GLOBALS['patched'] = array();
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
ck( 'Airtable is told it is awaiting review, and told nothing else', patched_cells( 0 ), array( 'Agreement Status' => 'Awaiting review' ) );
ck( 'the index block is in step at once, without waiting for a sync', WPCPM_Sponsors_Index::row( $S )['agreement']['status'], 'Awaiting review' );
ck( 'the summary is submitted and names the document', array( WPCPM_Sponsor_Agreement::summary( $S )['state'], WPCPM_Sponsor_Agreement::summary( $S )['pending_id'] ), array( 'submitted', $first ) );
ck( 'the assigned manager is told once and the sponsor\'s accounts are not mailed', mail_log(), array( 'sponsor-agreement-received to maciej@a8c.com' ) );
ck( 'an audit row names the upload with the size and the hash', array( end( $GLOBALS['audit'] )['kind'], isset( end( $GLOBALS['audit'] )['data']['sha256'] ) ), array( 'sponsor_agreement_upload', true ) );

post_file( $good );
ck( 'a second document while one waits is refused', ran( 'handle_upload' ), 'agreement-in-review|agreement|' . $S . '|' );
ck( 'and the store was not touched again', $GLOBALS['journal'], array( 'store' ) );

echo "\n=== Airtable refusing an upload ===\n";
$GLOBALS['patch_fails'] = true;
$GLOBALS['uid']         = 21;
$_POST                  = array( 'wpcpm_sponsor' => $S, 'wpcpm_sponsor_agr_signed' => '1' );
update_post_meta( $first, WPCPM_Sponsor_Agreement::META_STATE, WPCPM_Sponsor_Agreement::STATE_WITHDRAWN );
post_file( $good );
ck( 'the upload still succeeds: a base that is down does not cost the sponsor its document', ran( 'handle_upload' ), 'agreement-uploaded|agreement|' . $S . '|' );
$second = (int) WPCPM_Sponsor_Agreement::posts_for( $S )[0]->ID;
ck( 'and the post is marked for the next sync to finish', (int) get_post_meta( $second, WPCPM_Sponsor_Agreement::META_AIRTABLE_PENDING, true ), 1 );
$GLOBALS['patch_fails'] = false;

echo "\n=== Withdraw ===\n";
$_POST = array( 'wpcpm_sponsor_agr_post' => $second );
ck( 'the sponsor takes it back', ran( 'handle_withdraw' ), 'agreement-withdrawn|agreement|' . $S . '|' );
ck( 'the file goes at once rather than on the cron\'s schedule', array( (string) get_post_meta( $second, WPCPM_Sponsor_Agreement::META_STATE, true ), in_array( 'forget', $GLOBALS['journal'], true ) ), array( 'withdrawn', true ) );
ck( 'the post is kept, because the history is what the next manager reads', get_post( $second ) instanceof WP_Post, true );
ck( 'the base is told the queue entry is gone', patched_cells( count( $GLOBALS['patched'] ) - 1 ), array( 'Agreement Status' => 'Not started' ) );
ck( 'nothing is mailed: nothing happened anybody else needs telling about', count( $GLOBALS['mail'] ), 2 );
ck( 'a second press finds nothing to withdraw', ran( 'handle_withdraw' ), 'agreement-gone|agreement||' );

echo "\n=== The sync finishes a write the base refused, on the next run ===\n";
// The upload the base refused above is still owed its cell, and this is the run that owes it:
// the mark means nothing unless something later reads it.
$GLOBALS['patched'] = array();
ck( 'the marked upload is written from the state the site holds now, not the one that failed', array( WPCPM_Sponsor_Agreement::retry_airtable(), patched_cells( 0 ) ), array( 1, array( 'Agreement Status' => 'Not started' ) ) );
ck( 'and the mark is gone, so the next night reads nothing', (string) get_post_meta( $second, WPCPM_Sponsor_Agreement::META_AIRTABLE_PENDING, true ), '' );

$W = 'recSPN00000000005';
seed_index( $W, 'Retry Sponsor' );
$GLOBALS['users'][22] = new WP_User( 22, array( 'wpcpm_sponsor' ), 'Member Three', 'maciej@a8c.com' );
$GLOBALS['umeta'][22] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $W, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['uid']       = 22;
$_POST                = array( 'wpcpm_sponsor' => $W, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$owed                   = (int) WPCPM_Sponsor_Agreement::posts_for( $W )[0]->ID;
$_POST                  = array( 'wpcpm_sponsor_agr_post' => $owed );
$GLOBALS['patch_fails'] = true;
ck( 'a withdrawal the base refused still withdraws, and marks the document', array( ran( 'handle_withdraw' ), (int) get_post_meta( $owed, WPCPM_Sponsor_Agreement::META_AIRTABLE_PENDING, true ) ), array( 'agreement-withdrawn|agreement|' . $W . '|', 1 ) );
ck( 'a night the base is still down clears nothing and keeps the mark', array( WPCPM_Sponsor_Agreement::retry_airtable(), (int) get_post_meta( $owed, WPCPM_Sponsor_Agreement::META_AIRTABLE_PENDING, true ) ), array( 0, 1 ) );
$GLOBALS['patch_fails'] = false;
$GLOBALS['patched']     = array();
ck( 'the next night writes Not started, clears the mark and answers with the count', array( WPCPM_Sponsor_Agreement::retry_airtable(), patched_cells( 0 ), (string) get_post_meta( $owed, WPCPM_Sponsor_Agreement::META_AIRTABLE_PENDING, true ) ), array( 1, array( 'Agreement Status' => 'Not started' ), '' ) );
ck( 'the index block is in step with what was written', WPCPM_Sponsors_Index::row( $W )['agreement']['status'], 'Not started' );
ck( 'and a night with nothing owed writes nothing at all', array( WPCPM_Sponsor_Agreement::retry_airtable(), count( $GLOBALS['patched'] ) ), array( 0, 1 ) );
$sync_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-sync.php' );
ck( 'the sponsors sync, which runs every three hours, is what calls it, behind a guard', array(
	false !== strpos( $sync_src, "class_exists( 'WPCPM_Sponsor_Agreement' )" ),
	false !== strpos( $sync_src, "'retry_airtable'" ),
	false !== strpos( method_body( $sync_src, 'run_tick' ), 'self::phase_agreements( $state )' ),
), array( true, true, true ) );

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
// Every handler writes the index inside its own critical section and says so: a rebuild after
// the unlock would take the lock again, and skip silently whenever anything else held it.
foreach ( array( 'handle_upload', 'handle_withdraw', 'handle_accept', 'handle_return', 'handle_revoke', 'handle_reinstate', 'handle_on_file' ) as $handler ) {
	$one = method_body( $source, $handler );
	ck( $handler . '() rebuilds under its own lock, then releases it', strpos( $one, 'self::rebuild(' ) < strrpos( $one, 'self::unlock( $record )' ), true );
}
ck( 'and the retry, run every three hours with the sponsors sync, which holds no lock, lets rebuild() take it', false !== strpos( method_body( $source, 'retry_airtable' ), "self::rebuild( \$record, array( 'status' => \$status ) )" ), true );

echo "\n=== Accept ===\n";
$GLOBALS['uid']     = 1;
$GLOBALS['referer'] = 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors';
$GLOBALS['patched'] = array();
$GLOBALS['mail']    = array();
$_POST              = array( 'wpcpm_sponsor_agr_post' => $live );
ck( 'the manager accepts it', ran( 'handle_accept' ), 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors' );
ck( 'the flash is on the wp-admin channel', end( $GLOBALS['flash'] ), array( 'sponsors_admin', 'agreement-accepted' ) );
ck( 'the document is accepted, with who and when', array(
	(string) get_post_meta( $live, WPCPM_Sponsor_Agreement::META_STATE, true ),
	(int) get_post_meta( $live, WPCPM_Sponsor_Agreement::META_DECIDED_BY, true ),
	(string) get_post_meta( $live, WPCPM_Sponsor_Agreement::META_DECIDED_AT, true ),
), array( 'accepted', 1, wp_date( 'Y-m-d' ) ) );
ck( 'one PATCH carries the status and the date, and nothing else', patched_cells( 0 ), array( 'Agreement Status' => 'Accepted', 'Agreement Accepted On' => wp_date( 'Y-m-d' ) ) );
ck( 'and one PATCH, not two', count( $GLOBALS['patched'] ), 1 );
ck( 'the index block says accepted at once', array( WPCPM_Sponsors_Index::row( $S )['agreement']['status'], WPCPM_Sponsors_Index::row( $S )['agreement']['accepted_on'] ), array( 'Accepted', wp_date( 'Y-m-d' ) ) );
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
$GLOBALS['mail']    = array();
$GLOBALS['patched'] = array();
$_POST              = array( 'wpcpm_sponsor_agr_post' => $sent, 'wpcpm_sponsor_agr_note' => "Page 4 is unsigned.\n\nPlease sign it and upload the whole document again." );
ran( 'handle_return' );
ck( 'a note of twenty characters or more returns it', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $sent, WPCPM_Sponsor_Agreement::META_STATE, true ) ), array( 'agreement-returned', 'returned' ) );
ck( 'the note is kept on the document with its line breaks', false !== strpos( (string) get_post_meta( $sent, WPCPM_Sponsor_Agreement::META_NOTE, true ), "\n" ), true );
ck( 'the accepted copy that stands keeps the base saying Accepted', array( count( $GLOBALS['patched'] ), WPCPM_Sponsors_Index::row( $S )['agreement']['status'] ), array( 0, 'Accepted' ) );
ck( 'and both accounts get the note', mail_log(), array( 'sponsor-agreement-returned to maciej@a8c.com', 'sponsor-agreement-returned to maciej@a8c.com' ) );

seed_index( $T, 'Other Sponsor' );
$GLOBALS['umeta'][21] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $T, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['uid']       = 21;
$_POST                = array( 'wpcpm_sponsor' => $T, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$lonely             = (int) WPCPM_Sponsor_Agreement::posts_for( $T )[0]->ID;
$GLOBALS['uid']     = 1;
$GLOBALS['patched'] = array();
$_POST              = array( 'wpcpm_sponsor_agr_post' => $lonely, 'wpcpm_sponsor_agr_note' => 'The signature block is empty on every page.' );
ran( 'handle_return' );
ck( 'with nothing accepted, the base is told Returned', patched_cells( 0 ), array( 'Agreement Status' => 'Returned' ) );

echo "\n=== Revoke and reinstate ===\n";
$GLOBALS['patched'] = array();
$GLOBALS['mail']    = array();
$_POST              = array( 'wpcpm_sponsor_agr_post' => $replacement );
ran( 'handle_revoke' );
ck( 'a revoke with no note revokes nothing', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_STATE, true ) ), array( 'agreement-revoke-note', 'accepted' ) );
ck( 'and the base was never asked', count( $GLOBALS['patched'] ), 0 );
$_POST              = array( 'wpcpm_sponsor_agr_post' => $replacement, 'wpcpm_sponsor_agr_note' => 'The company asked us to put this one out of force.' );
ran( 'handle_revoke' );
ck( 'the accepted document is revoked', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_STATE, true ) ), array( 'agreement-revoked', 'revoked' ) );
ck( 'the base is told the status and nothing else', patched_cells( 0 ), array( 'Agreement Status' => 'Revoked' ) );
ck( 'no account is closed: a sponsor\'s dashboard was never gated on an agreement', array( count( WPCPM_Sponsor_Members::members_of( $S ) ), (int) get_user_meta( 20, WPCPM_Sponsor_Members::META_ACTIVE, true ) ), array( 1, 1 ) );
ck( 'and the accounts are told, with the note', count( $GLOBALS['mail'] ), 1 );
ck( 'a second press finds nothing in force to revoke', ran( 'handle_revoke' ) && 'agreement-not-accepted' === end( $GLOBALS['flash'] )[1], true );

$GLOBALS['patched'] = array();
$_POST              = array( 'wpcpm_sponsor_agr_post' => $replacement );
ran( 'handle_reinstate' );
ck( 'reinstating puts it back in force', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_STATE, true ) ), array( 'agreement-reinstated', 'accepted' ) );
ck( 'the base goes back to Accepted for an uploaded copy', patched_cells( 0 ), array( 'Agreement Status' => 'Accepted' ) );
ck( 'the date is today, not the day it was first accepted', (string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_DECIDED_AT, true ), wp_date( 'Y-m-d' ) );
ck( 'the revocation note went with the revocation: an agreement in force carries none', (string) get_post_meta( $replacement, WPCPM_Sponsor_Agreement::META_NOTE, true ), '' );
ck( 'reinstating twice is refused', ran( 'handle_reinstate' ) && 'agreement-not-revoked' === end( $GLOBALS['flash'] )[1], true );

echo "\n=== On file ===\n";
$U = 'recSPN00000000003';
seed_index( $U, 'Legacy Sponsor' );
$GLOBALS['patched'] = array();
$GLOBALS['mail']    = array();
$_POST              = array( 'wpcpm_sponsor' => $U, 'wpcpm_sponsor_agr_drive' => 'http://drive.google.com/drive/folders/abc' );
ran( 'handle_on_file' );
ck( 'a link that is not https on a Drive host records nothing', end( $GLOBALS['flash'] )[1], 'agreement-link' );
$_POST = array( 'wpcpm_sponsor' => $U, 'wpcpm_sponsor_agr_drive' => 'https://example.test/agreement.pdf' );
ran( 'handle_on_file' );
ck( 'nor does one on another host', end( $GLOBALS['flash'] )[1], 'agreement-link' );
ck( 'and nothing was written', count( $GLOBALS['patched'] ), 0 );
$_POST = array( 'wpcpm_sponsor' => $U, 'wpcpm_sponsor_agr_drive' => 'https://drive.google.com/drive/folders/abc' );
ran( 'handle_on_file' );
ck( 'a Drive link records the agreement as on file', end( $GLOBALS['flash'] )[1], 'agreement-on-file' );
ck( 'the base gets the status, the date and the link in one PATCH', patched_cells( 0 ), array( 'Agreement Status' => 'On file', 'Agreement Accepted On' => wp_date( 'Y-m-d' ), 'Agreement Document' => 'https://drive.google.com/drive/folders/abc' ) );
$legacy = (int) WPCPM_Sponsor_Agreement::posts_for( $U )[0]->ID;
ck( 'a legacy post stands for it, accepted and with the link', array(
	(string) get_post_meta( $legacy, WPCPM_Sponsor_Agreement::META_KIND, true ),
	(string) get_post_meta( $legacy, WPCPM_Sponsor_Agreement::META_STATE, true ),
	(string) get_post_meta( $legacy, WPCPM_Sponsor_Agreement::META_DRIVE_URL, true ),
), array( 'legacy', 'accepted', 'https://drive.google.com/drive/folders/abc' ) );
ck( 'the summary reads on file rather than accepted', WPCPM_Sponsor_Agreement::summary( $U )['state'], 'on_file' );
ck( 'the index says On file and holds a document', array( WPCPM_Sponsors_Index::row( $U )['agreement']['status'], WPCPM_Sponsors_Index::row( $U )['agreement']['has_document'] ), array( 'On file', true ) );
ck( 'no mail: nobody at the company asked for this and it is not news to them', count( $GLOBALS['mail'] ), 0 );
ran( 'handle_on_file' );
ck( 'recording it twice is refused', end( $GLOBALS['flash'] )[1], 'agreement-standing' );
$_GET = array( 'post' => $legacy, '_wpnonce' => 'x' );
ck( 'a legacy row has no file, so downloading it is the same 404 as a document this site never held', substr( ran( 'handle_download' ), 0, 7 ), 'die:404' );

$V = 'recSPN00000000004';
seed_index( $V, 'Unlucky Sponsor' );
$GLOBALS['patched']      = array();
$GLOBALS['insert_fails'] = true;
$_POST                   = array( 'wpcpm_sponsor' => $V, 'wpcpm_sponsor_agr_drive' => 'https://drive.google.com/drive/folders/xyz' );
ran( 'handle_on_file' );
ck( 'when the site cannot write the row after the base was told, the flash says exactly that', end( $GLOBALS['flash'] )[1], 'agreement-not-saved' );
ck( 'and the base was indeed told', array( count( $GLOBALS['patched'] ), patched_cells( 0 )['Agreement Status'] ), array( 1, 'On file' ) );
ck( 'while the site holds nothing for it', WPCPM_Sponsor_Agreement::summary( $V )['state'], 'none' );
$GLOBALS['insert_fails'] = false;
$_POST = array( 'wpcpm_sponsor' => $U, 'wpcpm_sponsor_agr_post' => $legacy, 'wpcpm_sponsor_agr_note' => 'The paper copy this row stands for was superseded by a new one.' );
ran( 'handle_revoke' );
ck( 'a legacy row can be revoked too', (string) get_post_meta( $legacy, WPCPM_Sponsor_Agreement::META_STATE, true ), 'revoked' );
$GLOBALS['patched'] = array();
$_POST              = array( 'wpcpm_sponsor_agr_post' => $legacy );
ran( 'handle_reinstate' );
ck( 'and comes back as On file, not Accepted', patched_cells( 0 ), array( 'Agreement Status' => 'On file' ) );
$_POST = array( 'wpcpm_sponsor' => $U, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$signed_copy        = (int) WPCPM_Sponsor_Agreement::posts_for( $U )[0]->ID;
$GLOBALS['patched'] = array();
$_POST              = array( 'wpcpm_sponsor_agr_post' => $signed_copy );
ran( 'handle_accept' );
ck( 'a signed copy accepted over an on-file row clears the base\'s link to the paper one', patched_cells( 0 ), array( 'Agreement Status' => 'Accepted', 'Agreement Accepted On' => wp_date( 'Y-m-d' ), 'Agreement Document' => '' ) );
ck( 'and the index stops saying the base holds a document, while the legacy row is superseded', array(
	WPCPM_Sponsors_Index::row( $U )['agreement']['has_document'],
	(string) get_post_meta( $legacy, WPCPM_Sponsor_Agreement::META_STATE, true ),
), array( false, 'superseded' ) );

echo "\n=== Only a program manager ===\n";
$GLOBALS['uid'] = 20;
foreach ( array( 'handle_accept', 'handle_return', 'handle_revoke', 'handle_reinstate', 'handle_on_file' ) as $method ) {
	ck( $method . '() refuses a sponsor account before it reads anything', substr( ran( $method ), 0, 7 ), 'die:403' );
}
$GLOBALS['uid'] = 1;

echo "\n=== Airtable refusing a manager transition ===\n";
$GLOBALS['patch_fails'] = true;
$GLOBALS['uid']         = 21;
$_POST                  = array( 'wpcpm_sponsor' => $T, 'wpcpm_sponsor_agr_signed' => '1' );
post_file( $good );
ran( 'handle_upload' );
$pending        = (int) WPCPM_Sponsor_Agreement::posts_for( $T )[0]->ID;
$GLOBALS['uid'] = 1;
$_POST          = array( 'wpcpm_sponsor_agr_post' => $pending );
ran( 'handle_accept' );
ck( 'an acceptance the base refused changes nothing here either', array( end( $GLOBALS['flash'] )[1], (string) get_post_meta( $pending, WPCPM_Sponsor_Agreement::META_STATE, true ) ), array( 'agreement-airtable', 'submitted' ) );
ck( 'and the lock is released, so the next press can try again', get_option( 'wpcpm_sponsor_agr_lock_' . $T, 'free' ), 'free' );
$GLOBALS['patch_fails'] = false;

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
ck( 'revoked_all() lists it', in_array( $gone, WPCPM_Sponsor_Agreement::revoked_all(), true ), true );
wp_delete_post( $gone, true );

$GLOBALS['patched'] = array();
$_POST              = array( 'wpcpm_sponsor_agr_post' => $queued, 'wpcpm_return' => 'dashboard' );
ck( 'a decision taken on the dashboard lands back there, its sentence on that page\'s channel', array( ran( 'handle_accept' ), end( $GLOBALS['flash'] ) ), array( 'https://example.test/administrator-dashboard/#wpcpm-sponsor-agreements', array( 'institutions_dashboard', 'agreement-accepted' ) ) );
$_POST = array();

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

foreach ( $GLOBALS['temp_files'] as $temp ) {
	if ( is_file( $temp ) ) { unlink( $temp ); }
}

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
