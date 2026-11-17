<?php
/**
 * The import form, and the two handlers behind it.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The institution is never read from the request.** It comes from the reader's own stamp,
 *   or from a manager's switcher, through `resolve_institution()`. A post carrying another
 *   school's record ID changes nothing about whose import it is.
 * - **The order is the module's rule.** The cheap decision refuses before anything else; the
 *   nonce is checked before a single request reaches Airtable; the ceilings are claimed before
 *   a byte is parsed. A cross-site post therefore costs this site nothing at all.
 * - **Cancel reads the institution off the batch.** A member of one school posting another
 *   school's batch ID is decided against the school that batch belongs to, and refused.
 * - **The blocked sentence is one sentence.** Whatever the real reason a row hit something
 *   outside this school, the school reads the same words, and the reason never reaches the
 *   page. Otherwise a preview is a lookup service for anybody who can paste addresses.
 * - The file is read and never moved: `wp_handle_upload()` would put a list of names and
 *   addresses into the uploads directory, which this site serves over the web.
 * - The single-student route becomes the same CSV the file route produces, so every cleaning
 *   rule and every duplicate check is written once and covers both.
 * - A start date more than a year away is a typo, and a whole cohort filed under 2036 is a
 *   mess somebody unpicks a record at a time.
 *
 * Run from the plugin root:  php bin/test-institution-import-form.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['opts']     = array();
$GLOBALS['posts']    = array();
$GLOBALS['pmeta']    = array();
$GLOBALS['flash']    = array();
$GLOBALS['referer']  = array();
$GLOBALS['redirect'] = '';
$GLOBALS['uid']      = 7;
$GLOBALS['manage']   = false;
$GLOBALS['resolved'] = '';
$GLOBALS['allowed']  = true;
$GLOBALS['staged']   = 0;
$GLOBALS['calls']    = array();
$GLOBALS['next_id']  = 900;
$GLOBALS['checked']  = null;
$GLOBALS['settled']  = true;
$GLOBALS['audit']    = array();
$GLOBALS['requests'] = array();
$GLOBALS['inserted'] = array();
$GLOBALS['cron']     = array();
$GLOBALS['created']  = array();
$GLOBALS['indexed']  = array();
$GLOBALS['sample_calls'] = array();

function __( $t, $d = null ) { return $t; }
function esc_html__( $t, $d = null ) { return $t; }
function esc_attr__( $t, $d = null ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function _n( $one, $many, $n, $d = null ) { return 1 === (int) $n ? $one : $many; }
function size_format( $bytes, $decimals = 0 ) { return (string) round( (int) $bytes / 1024 ) . ' KB'; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_url( $u ) { return $u; }
function esc_url_raw( $u ) { return $u; }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
// The real one keeps the line breaks and cleans everything else. A stub that flattened them
// too would have hidden the bug this suite exists to prevent coming back.
function sanitize_textarea_field( $s ) { return str_replace( array( "\r\n", "\r" ), "\n", strip_tags( (string) $s ) ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function number_format_i18n( $n ) { return (string) $n; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $k, $v = null, $url = '' ) {
	if ( is_array( $k ) ) { $url = $v; $pairs = $k; } else { $pairs = array( $k => $v ); }
	$join = false === strpos( (string) $url, '?' ) ? '?' : '&';
	$bits = array();
	foreach ( $pairs as $key => $value ) { $bits[] = rawurlencode( $key ) . '=' . rawurlencode( (string) $value ); }
	return $url . $join . implode( '&', $bits );
}
// Core's shape (Institution Dashboard, Task 17): the token under its id and name, then the page the form is drawn on, so
// a drawn form is asserted with the referer field it posts, on both branches.
function wp_referer_field( $display = true ) {
	$field = '<input type="hidden" name="_wp_http_referer" value="/institution-dashboard/?wpcpm_view=enroll" />';
	if ( $display ) { echo $field; }
	return $field;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	$GLOBALS['calls'][] = array( 'nonce_field', $action );
	$field              = '<input type="hidden" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="nonce-' . esc_attr( $action ) . '" />' . ( $referer ? wp_referer_field( false ) : '' );
	if ( $display ) { echo $field; }
	return $field;
}
function wp_create_nonce( $action = -1 ) { $GLOBALS['calls'][] = array( 'nonce_field', $action ); return 'nonce-' . $action; }
function is_admin() { return false; }
function wp_style_is( $handle, $list = 'enqueued' ) { return false; }
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {}
function wp_script_is( $handle, $list = 'enqueued' ) { return false; }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) {}
function wp_enqueue_script( $handle ) {}
function _doing_it_wrong( $function_name, $message, $version ) {}
function check_admin_referer( $action = -1, $q = '_wpnonce' ) { $GLOBALS['referer'][] = $action; return true; }
function wp_safe_redirect( $url ) { $GLOBALS['redirect'] = $url; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function wp_get_current_user() { return (object) array( 'ID' => (int) $GLOBALS['uid'] ); }
require_once __DIR__ . '/stubs/caps.php';
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $a = null ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_next_scheduled( $hook, $args = array() ) { return isset( $GLOBALS['cron'][ $hook . ':' . json_encode( $args ) ] ) ? $GLOBALS['cron'][ $hook . ':' . json_encode( $args ) ] : false; }
function wp_schedule_single_event( $when, $hook, $args = array() ) { $GLOBALS['cron'][ $hook . ':' . json_encode( $args ) ] = (int) $when; return true; }
function wp_date( $f ) { return gmdate( $f ); }
function add_action() {}
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function wp_hash( $v ) { return md5( (string) $v ); }
$GLOBALS['transients'] = array();
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
function sanitize_email( $e ) { return filter_var( trim( (string) $e ), FILTER_SANITIZE_EMAIL ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u ); }
function absint( $v ) { return abs( (int) $v ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
function do_action() {}

/** The handlers end in `exit`; here they end in an exception the runner can catch. */
class Left extends Exception {}
function wpcpm_test_exit() { throw new Left( $GLOBALS['redirect'] ); }

// Declares add_filter() and apply_filters(), which run what is hooked: the program map is made of filters.
require_once __DIR__ . '/stubs/compiled-seeds.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';

/**
 * The institutions module, stood in for its one question here: which programs may be offered.
 *
 * The four built-in statuses are always offered; a Track Builder track joins them only once
 * somebody ticks its reports automation item (the design's decision 10).
 */
class WPCPM_Institutions {
	public static $gated = array();

	public static function offered_programs() {
		$out = array();

		foreach ( WPCPM_Program::labels() as $status => $label ) {
			if ( ! in_array( (string) $status, self::$gated, true ) ) {
				$out[ (string) $status ] = (string) $label;
			}
		}

		return $out;
	}
}


/** Only what the form calls, so the suite stays about the form. */
class WPCPM_Mentors_Sync {
	public static function is_record_id( $id ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $id ); }
	public static function wporg_username( $raw ) {
		$v = strtolower( trim( ltrim( trim( (string) $raw ), '@' ) ) );
		$v = preg_replace( '#^https?://#', '', $v );
		$v = trim( (string) preg_replace( '#^profiles\.wordpress\.org/#', '', $v ), '/' );
		return preg_match( '/^[a-z0-9][a-z0-9._\-]{0,59}$/', $v ) ? $v : '';
	}
	public static function fields() {
		return array(
			'student_record_name' => 'Full Name', 'student_email' => 'Email', 'student_status' => 'Status',
			'student_institution' => 'Educational Institutions', 'student_start' => 'Start Date', 'student_profile' => 'WP Profile',
			'student_end' => 'End Date', 'student_import_key' => 'Site import key',
			'report_name' => 'Name', 'report_email' => 'Email', 'report_status' => 'Status',
			'report_instituton' => 'Educational institution', 'report_profile' => 'WordPress Profile',
		);
	}
}
class WPCPM_Airtable {
	public function __construct( $s = null ) {}
	public function formula_in( $f, array $v, $l = false ) { return empty( $v ) ? '' : 'in'; }
	public function formula_contains( $f, array $v, $l = true ) { return empty( $v ) ? '' : 'has'; }
	public function fetch_all( $t, array $a = array() ) { $GLOBALS['fetched'][] = $t; return array(); }
	/** Every create is recorded whole, so the handlers' cells can be read back and asserted. */
	public function create_records( $t, array $records ) {
		$ids = array();
		foreach ( $records as $record ) {
			$GLOBALS['created'][] = $record['fields'];
			$ids[]                = sprintf( 'rec%014d', count( $GLOBALS['created'] ) );
		}
		return $ids;
	}
	public static function flatten( $v, $g = ', ' ) { return is_array( $v ) ? implode( $g, $v ) : (string) $v; }
	public static function link_ids( $v ) { return array_values( array_filter( (array) $v, 'strlen' ) ); }
}
class WPCPM_Roster_Index {
	public static function rows( $r ) { return array(); }
	public static function insert( $r, array $row ) { $GLOBALS['inserted'][] = array( $r, $row ); }
}
/** The creation loop's collaborators, no further than the form needs to reach them. */
class WPCPM_Institution_Agreement { public static function is_settled( $r ) { return (bool) $GLOBALS['settled']; } }
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager';
	const GROUND_MEMBER  = 'member';
	const GROUND_SYSTEM  = 'system';
	const EVIDENCE_CACHE = 'cache';
	const EVIDENCE_LIVE  = 'live';
	public static function grounds() { return array( 'manager', 'member', 'system' ); }
	public static function record( array $entry ) { $GLOBALS['audit'][] = $entry; return 1; }
}
class WPCPM_Institution_Request {
	const KIND_ADD = 'add';
	public static function raise( $k, $i, $s, $a, $g = '' ) { $GLOBALS['requests'][] = array( $k, $i, $s, $a, $g ); return 1; }
}
class WPCPM_Roles { const CAP_MANAGE = 'wpcpm_manage_program'; }
class WPCPM_Settings {
	public static function get() { return array( 'students_table' => 'tblS', 'reports_table' => 'tblR' ); }
	public static function get_value( $k ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : false; }
}
/**
 * The flash, with a refused form's kept values kept as handed over: what the form keeps and drops is
 * the form's to decide here, and `bin/test-flash.php` holds the real store to its own rules.
 */
class WPCPM_Flash {
	const KEPT_MAX = 60000;
	public static function set( $c, $v, $u = 0 ) { $GLOBALS['flash'][ $c ] = $v; }
	public static function take( $c, $u = 0 ) { $v = isset( $GLOBALS['flash'][ $c ] ) ? $GLOBALS['flash'][ $c ] : null; unset( $GLOBALS['flash'][ $c ] ); return $v; }
	public static function keep( $form, array $values, array $problems = array() ) { $GLOBALS['flash'][ 'kept:' . $form ] = array( 'values' => $values, 'problems' => $problems ); }
	public static function kept( $form ) { $v = self::take( 'kept:' . $form ); return is_array( $v ) ? $v : array( 'values' => array(), 'problems' => array() ); }
	public static function forget( $form ) { self::take( 'kept:' . $form ); }
}
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-card.php';
// The shared parts (Institution Dashboard, Task 17): the card's parts' branch draws with them, and only while the
// Institution Dashboard's surface is on.
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
class WPCPM_Request {
	public static function text( $n, $f = '' ) { return isset( $_GET[ $n ] ) ? (string) $_GET[ $n ] : $f; }
	// **These two clean differently, and that difference is the point.** The suite used to
	// return the posted value untouched for both, so a handler reading a pasted CSV through
	// the text reader looked fine here and arrived as a single line in production: the header
	// and the first student ran together and the import refused the file for having no email
	// column. It took a live import to find.
	public static function posted_text( $n, $f = '' ) { return isset( $_POST[ $n ] ) ? trim( sanitize_text_field( (string) $_POST[ $n ] ) ) : $f; }
	public static function posted_lines( $n, $f = '' ) { return isset( $_POST[ $n ] ) ? trim( sanitize_textarea_field( (string) $_POST[ $n ] ) ) : $f; }
}
class WPCPM_Institution_Policy {
	const ACT_ADD_STUDENT = 'add_student';
	public static function subject_institution( $id ) { $GLOBALS['calls'][] = array( 'subject', $id ); return array( 'institution' => $id ); }
	public static function decide( $action, $subject, $user = null ) {
		$GLOBALS['calls'][] = array( 'decide', $action, isset( $subject['institution'] ) ? $subject['institution'] : '', $user );
		return array(
			'allowed'     => (bool) $GLOBALS['allowed'],
			'ground'      => 'member',
			'institution' => isset( $subject['institution'] ) ? $subject['institution'] : '',
		);
	}
}
class WPCPM_Institution_Roster {
	// The switcher argument, copied and checked against the real class below: a manager's way back
	// from a press carries it, and without it they land on whichever institution is their fallback.
	const ARG_VIEW = 'wpcpm_institution_view';
	public static function resolve_institution( $viewer, $can_manage ) { $GLOBALS['calls'][] = array( 'resolve' ); return (string) $GLOBALS['resolved']; }
}
require_once __DIR__ . '/stubs/institution-card-url.php';
require_once __DIR__ . '/stubs/view-faults.php';
class WPCPM_Institutions_Dashboard {
	const VIEW_ARG = 'wpcpm_view';
	public static function page_url() { return 'https://example.test/institution-dashboard/'; }
	// The page's addresses, as the real class builds them (bin/stubs/institution-card-url.php, checked against it
	// in bin/test-institution-ways-back.php).
	public static function view_url( $view = 'overview', $record = '', array $args = array() ) { return wpcpm_test_institution_view_url( self::page_url(), $view, $record, $args ); }
	public static function view_of( $card ) { return wpcpm_test_institution_view_of( $card ); }
	public static function card_url( $card, $record = '', $item = '', array $args = array() ) { return wpcpm_test_institution_card_url( self::page_url(), $card, $record, $item, $args ); }
	public static function requested_record() { return wpcpm_test_institution_requested_record(); }
	public static function way_back( $card ) { return wpcpm_test_institution_way_back( self::page_url(), $card ); }
}
/** The pipeline index, as far as the form asks it: whether it holds a record. */
class WPCPM_Institutions_Index {
	public static function has( $record_id ) { return in_array( (string) $record_id, $GLOBALS['indexed'], true ); }
}
class WPCPM_Institution_Student_Form {
	public static function choices( $n ) { return 'field_of_study' === $n ? array( 'Technology & Engineering' ) : array(); }
}
/**
 * The sample list's link, to the one contract the form has to satisfy: it is handed the institution the
 * form is for, and nothing else. The nonce keyed to that institution, the switcher for a manager and the
 * file are `bin/test-institution-export.php`'s.
 */
class WPCPM_Institution_Export {
	public static function sample_url( $record ) {
		$GLOBALS['sample_calls'][] = $record;
		return 'https://example.test/wp-admin/admin-post.php?action=wpcpm_import_sample&_wpnonce=nonce-wpcpm_import_sample_' . $record;
	}
}

function wp_insert_post( $args, $e = false ) {
	$id = ++$GLOBALS['next_id'];
	$GLOBALS['posts'][ $id ] = (object) array_merge( array( 'ID' => $id, 'post_type' => '', 'post_author' => 0, 'post_status' => '', 'post_title' => '' ), $args );
	return $id;
}
function wp_delete_post( $id, $f = false ) { $g = isset( $GLOBALS['posts'][ (int) $id ] ); unset( $GLOBALS['posts'][ (int) $id ] ); return $g; }
function get_post( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
// Post meta here keeps what it is handed, so the slash is the identity (bin/test-institution-import.php
// holds the batch's writes to core's, whose meta unslashes).
function wp_slash( $v ) { return $v; }
function get_post_meta( $id, $k, $s = false ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $k ] : ''; }
function get_posts( $args ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( $post->post_type !== $args['post_type'] ) { continue; }
		$ok = true;
		foreach ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() as $c ) {
			if ( get_post_meta( $id, $c['key'], true ) !== $c['value'] ) { $ok = false; break; }
		}
		if ( $ok ) { $out[] = $id; }
	}
	return $out;
}
function register_post_type( $t, $a ) { return true; }
class WP_Error { public $c; public $m; public function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; } public function get_error_code() { return $this->c; } public function get_error_message() { return $this->m; } }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function get_user_by( $by, $v ) { return false; }

/**
 * The institution's record, as far as the tutor list asks it: the answers kept for a school, `$GLOBALS['kept']`.
 * The picker reads the real list (`WPCPM_Institution_Tutors`) over it, so what is offered is what the list holds.
 */
class WPCPM_Institution_Record {
	public static function get( $record_id ) { return isset( $GLOBALS['kept'][ $record_id ] ) ? $GLOBALS['kept'][ $record_id ] : array(); }
}
$GLOBALS['kept']    = array();
$GLOBALS['fetched'] = array();

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-tutors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-create.php';

// The program map is the compiled tracks' (bin/stubs/compiled-seeds.php).
WPCPM_Tracks::init();
wpcpm_seed_compiled_options( $GLOBALS['opts'] );

// The handlers call `exit`. Loaded through a rewrite so the runner can catch the end instead.
$form_source = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import-form.php' );
$form_source = preg_replace( '/^\s*exit;\s*$/m', "\t\twpcpm_test_exit();", $form_source );
$form_source = preg_replace( '/^<\?php/', '', $form_source, 1 );
$form_source = str_replace( "defined( 'ABSPATH' ) || exit;", '', $form_source );
eval( $form_source );

$fails = 0;
$total = 0;

function ck( $label, $actual, $expected ) {
	global $fails, $total;
	++$total;
	if ( $actual === $expected ) { printf( "ok   %s\n", $label ); return; }
	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $actual, true ), var_export( $expected, true ) );
}

$HERE  = 'recHERE0000000001';
$THERE = 'recTHERE00000001';

/** Post the form and report where it left the reader, and what it said. */
function post_check( array $fields ) {
	$GLOBALS['referer']  = array();
	$GLOBALS['calls']    = array();
	$GLOBALS['redirect'] = '';
	$GLOBALS['flash']    = array();
	$_POST               = $fields;
	$_GET                = array();
	$_FILES              = isset( $fields['__files'] ) ? $fields['__files'] : array();
	unset( $_POST['__files'] );

	try {
		WPCPM_Institution_Import_Form::handle_check();
	} catch ( Left $e ) {
		// The handler ended where it meant to.
	}

	$flash = isset( $GLOBALS['flash']['institution_import'] ) ? $GLOBALS['flash']['institution_import'] : array();

	return isset( $flash['status'] ) ? $flash['status'] : '';
}

/** The batch-wide answers a valid post carries, so a case can change one of them. */
function batch_fields( array $over = array() ) {
	return array_merge(
		array(
			'program' => 'In Sensei',
			'start'   => gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ),
			'end'     => '',
			'notified' => '1',
			'paste'   => "Name,Email\nAnna Kowalska,anna@institution-3.example\n",
		),
		$over
	);
}

$GLOBALS['settings'] = array( 'import_enabled' => true );
$GLOBALS['resolved'] = $HERE;

echo "=== The institution is the reader's, never the form's ===\n";

$status = post_check( batch_fields( array( 'institution' => $THERE, 'record' => $THERE ) ) );

ck( 'a valid list is checked and staged', $status, 'checked' );
// The check was logged for a manager card that was never drawn, and nothing read the log.
ck( 'and checking it writes no log of checks', array_key_exists( 'wpcpm_import_log', $GLOBALS['opts'] ), false );

// **The lines survived the request, which is what failed in production.** A pasted CSV read
// through the text sanitizer arrives as one line, so the header and the first student run
// together and the file is refused for having no email column. Three lines in, three rows out.
fresh_world();
$status = post_check( batch_fields( array( 'paste' => "Name,Email\nAnna Kowalska,anna@institution-3.example\nBartek Zielinski,bartek@institution-3.example\nCecylia Nowak,cecylia@institution-3.example\n" ) ) );
$staged = WPCPM_Institution_Import::batch( WPCPM_Institution_Import::active_for( $HERE ) );

ck( 'a three line paste is checked', $status, 'checked' );
ck( 'and arrives as three rows, not one', count( $staged['rows'] ), 3 );
ck( 'the first row is the first student and not the header plus them', $staged['rows'][0]['name'], 'Anna Kowalska' );

$decided = array();
foreach ( $GLOBALS['calls'] as $call ) { if ( 'decide' === $call[0] ) { $decided[] = $call[2]; } }

// The form can say whatever it likes; the answer comes from `resolve_institution()`.
ck( 'the decision was made about the reader\'s own institution', $decided, array( $HERE ) );
ck( 'and never about the one the form named', in_array( $THERE, $decided, true ), false );

$staged_id = WPCPM_Institution_Import::active_for( $HERE );
ck( 'the batch belongs to the reader\'s institution', WPCPM_Institution_Import::batch( $staged_id )['institution'], $HERE );
ck( 'and the reader is its author', WPCPM_Institution_Import::batch( $staged_id )['author'], 7 );

echo "\n=== The order, which is what makes a forged post free ===\n";

// The nonce is keyed to the institution, so a token for one school is not a token for another.
ck( 'the nonce names the institution', $GLOBALS['referer'], array( WPCPM_Institution_Import_Form::ACTION_CHECK . '_' . $HERE ) );

$order = array();
foreach ( $GLOBALS['calls'] as $call ) { $order[] = $call[0]; }

// Resolve, then decide, then the nonce. `decide()` costs nothing; the network comes after.
ck( 'the institution is resolved first', $order[0], 'resolve' );
ck( 'and decided before the nonce is even read', array_search( 'decide', $order, true ) < count( $order ), true );

$GLOBALS['allowed'] = false;
$GLOBALS['referer'] = array();
$status             = post_check( batch_fields() );
ck( 'a reader the policy refuses is refused', $status, 'refused' );
// The cheap decision comes first, so a refused reader never causes a nonce check or a request.
ck( 'and no nonce was checked for them', $GLOBALS['referer'], array() );
$GLOBALS['allowed'] = true;

$GLOBALS['resolved'] = '';
ck( 'a reader with no institution at all is refused', post_check( batch_fields() ), 'refused' );
$GLOBALS['resolved'] = $HERE;

$GLOBALS['settings'] = array( 'import_enabled' => false );
ck( 'and nothing happens at all while the feature is off', post_check( batch_fields() ), 'off' );
$GLOBALS['settings'] = array( 'import_enabled' => true );

echo "\n=== One list at a time ===\n";

// A school cannot stage six lists and confirm them in an order nobody intended.
ck( 'a second list is refused while one is waiting', post_check( batch_fields() ), 'already-staged' );

echo "\n=== The batch-wide answers ===\n";

/**
 * A world with no staged batch and no spent ceiling.
 *
 * Each of these cases is one check, and the hourly ceiling admits five: without the reset the
 * sixth case would be refused for being the sixth rather than for the thing it is testing, and
 * the suite would go green on the wrong answer.
 */
function fresh_world() {
	$GLOBALS['posts'] = array();
	$GLOBALS['pmeta'] = array();
	$GLOBALS['opts']  = array();
	$GLOBALS['flash'] = array();

	// The spent ceilings go; the site's four tracks stay, as they would on the site.
	wpcpm_seed_compiled_options( $GLOBALS['opts'] );
}

$cases = array(
	array( 'a program this site does not offer is refused', array( 'program' => 'Astronaut Track' ), 'bad-program' ),
	array( 'a start date that is not a date is refused', array( 'start' => 'next term' ), 'bad-start' ),
	// Pattern and checkdate() both, so this is refused rather than becoming the first of March.
	array( 'the thirty-first of February is refused', array( 'start' => '2026-02-31' ), 'bad-start' ),
	// A term starting next autumn is ordinary; one starting in 2036 is a finger on the wrong
	// digit, and a whole cohort filed under it is a mess somebody unpicks a record at a time.
	array( 'a start date ten years out is refused as a typo', array( 'start' => '2036-09-01' ), 'start-far' ),
	array( 'an end date before the start is refused', array( 'end' => '2020-01-01' ), 'bad-end' ),
	array( 'and one more than a year after it', array( 'end' => gmdate( 'Y-m-d', time() + 800 * DAY_IN_SECONDS ) ), 'bad-end' ),
	// The school's own statement, and the form does not proceed without it.
	array( 'the list is refused until the school confirms it has notified them', array( 'notified' => '' ), 'not-notified' ),
	array( 'a post with nothing in it at all is refused', array( 'paste' => '' ), 'nothing-sent' ),
);

foreach ( $cases as $case ) {
	fresh_world();
	ck( $case[0], post_check( batch_fields( $case[1] ) ), $case[2] );
}

echo "\n=== One shape reaches the checking, not two ===\n";

fresh_world();

// The five boxes become the file they describe, so every cleaning rule and every duplicate
// check is written once and covers both routes.
$status = post_check(
	batch_fields(
		array(
			'paste'          => '',
			'name'           => 'Bartek Zielinski',
			'email'          => 'bartek@institution-3.example',
			'profile'        => '@student-two',
			// Posted as a forged field would be: the form no longer offers it.
			'field_of_study' => 'Technology & Engineering',
			'tutor'          => 'Dr Nowak',
		)
	)
);

ck( 'one student sent through the boxes is checked the same way', $status, 'checked' );

$rows = WPCPM_Institution_Import::batch( WPCPM_Institution_Import::active_for( $HERE ) )['rows'];

ck( 'and arrives as one cleaned row', count( $rows ), 1 );
ck( 'and the address lowercased for comparing', $rows[0]['email_key'], 'bartek@institution-3.example' );
ck( 'and one posted anyway is not carried', $rows[0]['profile'], '' );

// Drawn with nothing staged, or the section shows the preview and every assertion about the
// form's controls would be asking the wrong markup and passing for the wrong reason.
fresh_world();
$form = draw_section( $HERE );

ck( 'the form is the form and not a preview', false !== strpos( $form, 'name="paste"' ), true );
// Getting a WordPress.org profile is the student's own first step of onboarding, after they
// are enrolled, so at the moment a school fills this in nobody has one to give.
ck( 'the form asks for no profile', false !== strpos( $form, 'name="profile"' ), false );

// A single-select in Airtable, and create_records() sends no typecast, so a value spelled any
// other way is a 422 for the whole record: a text box here is one a school can only get wrong.
ck( 'the field of study is a picker', 1 === preg_match( '#<select [^>]*id="wpcpm-import-field_of_study"#', $form ), true );
ck( 'holding the base\'s own nine', substr_count( $form, '<option value="Technology &amp; Engineering"' ), 1 );
// Neither field is required, and a picker that opens on the first real answer files half a
// cohort under it.
ck( 'opening on a blank answer', false !== strpos( $form, '<option value="" selected>Not recorded</option>' ), true );

// Tutors are a table in the base, and a free-text box invites a name that matches nothing in
// it, which is how that column filled with spellings. So it is a picker even when this
// institution has nobody in the table yet, with a sentence saying who to ask rather than a box
// implying the site will take whatever is typed.
ck( 'the tutor is a picker even with none recorded', 1 === preg_match( '#<select [^>]*id="wpcpm-import-tutor"#', $form ), true );
ck( 'and says where to add them instead of pretending otherwise', false !== strpos( $form, 'No tutors are listed for your institution yet. Add them under Tutors on Your institution, and they will appear here.' ), true );
ck( 'the old sentence, which sent the school to an Administrator, is gone', false !== strpos( $form, 'Ask an Administrator to add them' ), false );
ck( 'no field is left as a free-text box that the base has options for', substr_count( $form, 'id="wpcpm-import-tutor" name="tutor" /' ), 0 );

// The picker offers the school's own list, kept on the site: the names, each once, and nothing of another school's,
// and the base is not asked for them (a request to any table is recorded, and there are none).
$GLOBALS['kept'][ $HERE ]  = array( 'tutors' => array(
	array( 'name' => 'Zofia Zielinska', 'email' => 'zofia@example.test' ),
	array( 'name' => 'Adam Adamski', 'email' => 'adam@example.test' ),
	array( 'name' => 'Adam Adamski', 'email' => 'adam.two@example.test' ),
) );
$GLOBALS['kept'][ $THERE ] = array( 'tutors' => array( array( 'name' => 'Other School Tutor', 'email' => 'other@example.test' ) ) );
$GLOBALS['fetched']        = array();
$listed                    = draw_section( $HERE );
ck( 'with tutors listed the picker offers each name once, A to Z', array(
	substr_count( $listed, '<option value="Adam Adamski">' ),
	strpos( $listed, 'Adam Adamski' ) < strpos( $listed, 'Zofia Zielinska' ),
	false !== strpos( $listed, '<option value="Zofia Zielinska">' ),
), array( 1, true, true ) );
ck( 'and not another school\'s, and no hint', array( false !== strpos( $listed, 'Other School Tutor' ), false !== strpos( $listed, 'No tutors are listed' ) ), array( false, false ) );
ck( 'and no request was made to the base to draw it', $GLOBALS['fetched'], array() );
$GLOBALS['kept'] = array();

echo "\n=== Cancel decides against the batch's institution ===\n";

// Its own batch, staged here rather than borrowed from a block above: the form assertions
// clear the world between them, and a cancel test reading whatever survived that is a test
// that passes or fails on the order of the file.
fresh_world();
post_check( batch_fields() );

$batch_id = WPCPM_Institution_Import::active_for( $HERE );

ck( 'there is a batch to cancel', $batch_id > 0, true );

/** Press Cancel as somebody, and report what the screen said. */
function post_cancel( $batch_id ) {
	$GLOBALS['referer']  = array();
	$GLOBALS['calls']    = array();
	$GLOBALS['flash']    = array();
	$_POST               = array( 'batch' => (string) (int) $batch_id );

	try {
		WPCPM_Institution_Import_Form::handle_cancel();
	} catch ( Left $e ) {
		// Ended where it meant to.
	}

	$flash = isset( $GLOBALS['flash']['institution_import'] ) ? $GLOBALS['flash']['institution_import'] : array();

	return isset( $flash['status'] ) ? $flash['status'] : '';
}

// The reader may be anybody; the batch says whose it is, and that is what is decided about.
$GLOBALS['resolved'] = $THERE;
$GLOBALS['allowed']  = false;
ck( 'somebody the policy refuses cannot cancel it', post_cancel( $batch_id ), 'refused' );
$decided = array();
foreach ( $GLOBALS['calls'] as $call ) { if ( 'decide' === $call[0] ) { $decided[] = $call[2]; } }
ck( 'and the decision was about the batch\'s institution', $decided, array( $HERE ) );
ck( 'the batch is still there', WPCPM_Institution_Import::active_for( $HERE ) > 0, true );

$GLOBALS['allowed']  = true;
$GLOBALS['resolved'] = $HERE;
ck( 'its own institution can throw it away', post_cancel( $batch_id ), 'cancelled' );
ck( 'and then there is none waiting', WPCPM_Institution_Import::active_for( $HERE ), 0 );
ck( 'cancelling a batch that is gone says so', post_cancel( $batch_id ), 'no-batch' );

echo "\n=== Confirm: the order, the nonce, and what a second press does ===\n";

/** Press a batch control as somebody, and report what the screen said. */
function post_batch( $handler, $batch_id ) {
	$GLOBALS['referer']  = array();
	$GLOBALS['calls']    = array();
	$GLOBALS['flash']    = array();
	$GLOBALS['redirect'] = '';
	$_POST               = array( 'batch' => (string) (int) $batch_id );
	$_GET                = array();
	$_FILES              = array();

	try {
		call_user_func( array( 'WPCPM_Institution_Import_Form', $handler ) );
	} catch ( Left $e ) {
		// Ended where it meant to.
	}

	$flash = isset( $GLOBALS['flash']['institution_import'] ) ? $GLOBALS['flash']['institution_import'] : array();

	return isset( $flash['status'] ) ? $flash['status'] : '';
}

fresh_world();
$GLOBALS['created'] = array();
post_check( batch_fields() );
$batch_id = WPCPM_Institution_Import::active_for( $HERE );

// Keyed to the batch, so a token minted for one school's list is not a token for another's,
// and to a digest of what that list says, so a token minted for the preview somebody read is
// not a token for a list that has changed since.
$action = WPCPM_Institution_Create::confirm_action( $batch_id );
ck( 'the confirm action names the batch', 0 === strpos( $action, WPCPM_Institution_Import::ACTION_CONFIRM . '_' . $batch_id . '_' ), true );
ck( 'and another batch would get another action', WPCPM_Institution_Create::confirm_action( $batch_id + 1 ) === $action, false );
// The Continue button is keyed to the batch alone: a batch part way through changes on every
// slice, so a digest of its rows would invalidate the button as soon as one student existed.
ck( 'the continue action is the batch and nothing else', WPCPM_Institution_Create::continue_action( $batch_id ), WPCPM_Institution_Import::ACTION_CONTINUE . '_' . $batch_id );

// The reader may be anybody; the batch says whose list it is, and that is what is decided
// about. A member of one school posting another's batch ID is refused.
$GLOBALS['resolved'] = $THERE;
$GLOBALS['allowed']  = false;
ck( 'somebody the policy refuses cannot confirm it', post_batch( 'handle_confirm', $batch_id ), 'refused' );
$decided = array();
foreach ( $GLOBALS['calls'] as $call ) { if ( 'decide' === $call[0] ) { $decided[] = $call[2]; } }
ck( 'and the decision was about the batch\'s institution', $decided, array( $HERE ) );
// The cheap decision comes first, so a refused reader costs one post read and no nonce check.
ck( 'and no nonce was checked for them', $GLOBALS['referer'], array() );
ck( 'and nothing was created', count( $GLOBALS['created'] ), 0 );

$GLOBALS['allowed']  = true;
$GLOBALS['resolved'] = $HERE;
$status              = post_batch( 'handle_confirm', $batch_id );

ck( 'its own institution can create the list', $status, 'created' );
ck( 'and the nonce that was checked was the batch\'s', $GLOBALS['referer'], array( $action ) );
ck( 'and the one clean row was created', count( $GLOBALS['created'] ), 1 );
// Read off the batch, never off the form, on this and on every later slice.
ck( 'against the institution the batch was staged for', $GLOBALS['created'][0]['Educational Institutions'], array( $HERE ) );
// So the account that pressed this can be re-asked by a slice with no user of its own.
ck( 'and the account that pressed it is on the batch', WPCPM_Institution_Create::actor_of( $batch_id ), 7 );

$made   = count( $GLOBALS['created'] );
$status = post_batch( 'handle_confirm', $batch_id );

// The browser's back button, and it has to be free.
ck( 'a second confirm of a done batch creates nothing', count( $GLOBALS['created'] ), $made );
ck( 'and says so', $status, 'not-staged-now' );

// Nothing is being created, so there is nothing to carry on with.
ck( 'continue refuses a batch that is not running', post_batch( 'handle_continue', $batch_id ), 'not-creating-now' );
ck( 'and creates nothing either', count( $GLOBALS['created'] ), $made );

// A finished batch is not "in hand": the school has to be able to send the rest of the list,
// and the students already created are caught by the ladder because each went on this roster
// the moment it was made.
ck( 'and the school may send another list', WPCPM_Institution_Import::active_for( $HERE ), 0 );

echo "\n=== What the three states of a batch put on the screen ===\n";

fresh_world();
$GLOBALS['created'] = array();
post_check( batch_fields() );
$batch_id = WPCPM_Institution_Import::active_for( $HERE );
$staged   = draw_section( $HERE );

// Everything that is about to happen, and everything that is not, read before the button:
// the count, the program, the date, that no mail goes out now, and the one instruction that
// prevents the duplicate this plugin cannot close.
ck( 'a staged list offers the create control', false !== strpos( $staged, 'Create these student records' ), true );
ck( 'saying no email is sent now', false !== strpos( $staged, 'No email is sent now' ), true );
// The welcome goes when the student's place is set up, which no longer waits for a mentor (a mentor is
// optional in every design), so the confirmation promises none.
ck( 'and when the program emails each student: when their place is set up, and the login when their account is created', false !== strpos( $staged, 'The program emails each student when their place is set up, and they receive their login when their account is created.' ), true );
ck( 'saying nothing of a mentor being assigned', array( false !== stripos( $staged, 'mentor' ), false !== strpos( $staged, 'once a mentor is assigned' ) ), array( false, false ) );
ck( 'and warning against the registration form', false !== strpos( $staged, 'would create a second record' ), true );
ck( 'and it can still be thrown away', false !== strpos( $staged, 'Throw this list away' ), true );

update_post_meta( $batch_id, WPCPM_Institution_Import::META_STATE, WPCPM_Institution_Import::STATE_CREATING );
$running = draw_section( $HERE );

// A batch with records behind it cannot be thrown away: deleting the post would leave those
// students with a `Site import key` pointing at a batch that no longer exists.
ck( 'a running import offers Continue instead', false !== strpos( $running, '>Continue<' ), true );
ck( 'and no way to throw it away', false !== strpos( $running, 'Throw this list away' ), false );
ck( 'and does not offer to create it again', false !== strpos( $running, 'Create these student records' ), false );
// Cron on a quiet site runs when somebody visits, which for a school's dashboard may be
// tomorrow; the person watching should not have to wait for a visitor.
ck( 'and says it carries on by itself', false !== strpos( $running, 'carries on by itself' ), true );

echo "\n=== What the preview says, and what it does not ===\n";

$said = WPCPM_Institution_Import_Form::messages();

// The one sentence every outside hit gets. If this ever branches, a preview becomes a lookup
// service: paste three hundred addresses, read three hundred answers about who is in the
// program. The reason lives on the batch, for a program manager.
$source = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import-form.php' );
ck( 'the blocked sentence is written once', substr_count( $source, 'This student cannot be imported from here' ), 1 );

/**
 * The module with its comments removed.
 *
 * Scanned instead of the raw text, because the comments explain why the module does not do
 * these things and name them to do it. Built here rather than three hundred lines down, where
 * it used to be: the next assertion read `$code` before anything had assigned it, so it was
 * asking `strpos( null, ... )` and could not fail whatever the module said.
 */
$code = '';

foreach ( token_get_all( '<?php ' . $source ) as $token ) {
	if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
		continue;
	}

	$code .= is_array( $token ) ? $token[1] : $token;
}

ck( 'and the manager-only reason never reaches the screen', false !== strpos( $code, 'manager_reason' ), false );

// A school that pasted thirty students and got twenty-eight has to be able to fix the two.
foreach ( array( 'parse-not_utf8', 'parse-no_columns', 'parse-too_many_rows', 'parse-batch_mismatch', 'too_often', 'rows_today' ) as $key ) {
	ck( sprintf( '"%s" has a sentence somebody can act on', $key ), isset( $said[ $key ] ) && strlen( $said[ $key ] ) > 20, true );
}

echo "\n=== The section folds, and opens when it is asking for something ===\n";

/** Draw the section as the reader would meet it, and hand back the markup. */
function draw_section( $record ) {
	ob_start();
	WPCPM_Institution_Import_Form::render( $record, array( 'can_manage' => false ) );
	return (string) ob_get_clean();
}

fresh_world();
$GLOBALS['sample_calls'] = array();
$closed                  = draw_section( $HERE );

// The card is the Enroll students view's content, drawn on the parts (Institution Dashboard, Task 17): no fold, the
// view's head names it, and its block carries the anchor every link and way back lands on.
ck( 'the card is a block under its anchor, with no fold and no head of its own', array( 0 === strpos( $closed, '<section class="wpcpm-ui-block" id="wpcpm-import">' ), false !== strpos( $closed, '<details' ), false !== strpos( $closed, '<h2' ) ), array( true, false, false ) );

echo "\n=== The sample list's example row is refused in words ===\n";

// 1.123.41: the sample list's one student is at example.test, and the import refuses that address so an
// unchanged sample creates nobody. The school is told why in the sentence on the row, and the row beside it,
// a student of theirs, is ready to create as it always was.
fresh_world();
post_check( batch_fields( array( 'paste' => "name,email,field_of_study,tutor\nAna Example,ana@example.test,Technology & Engineering,Tutor Example\nBartek Zielinski,bartek@institution-3.example,,\n" ) ) );
$sample_preview = html_entity_decode( draw_section( $HERE ), ENT_QUOTES );
ck( 'the example row is told it is the sample list\'s, and what to do', false !== strpos( $sample_preview, '<td class="wpcpm-ui-cell--text" role="cell" data-label="What happens">This is the sample list\'s example student. Replace it with one of your students, or delete the row.</td>' ), true );
ck( 'and the sentence is drawn once, for that row only', substr_count( $sample_preview, 'This is the sample list\'s example student.' ), 1 );
ck( 'the student beside it is ready to create', false !== strpos( $sample_preview, '<td class="wpcpm-ui-cell--text" role="cell" data-label="What happens">Ready to create.</td>' ), true );
ck( 'one of the two rows can be created', false !== strpos( $sample_preview, '1 of 2 rows can be created.' ), true );
ck( 'and the sentence is not the one for an address that is not one', false !== strpos( $sample_preview, 'That is not an email address.' ), false );

// The unchanged sample on its own, as a school meets it when they download the file and send it straight back:
// the one row is told what it is, nothing can be created, and so the page offers no create control.
fresh_world();
post_check( batch_fields( array( 'paste' => "name,email,field_of_study,tutor\nAna Example,ana@example.test,Technology & Engineering,Tutor Example\n" ) ) );
$sample_alone = html_entity_decode( draw_section( $HERE ), ENT_QUOTES );
ck( 'the unchanged sample on its own is read, and none of its rows can be created', false !== strpos( $sample_alone, '0 of 1 rows can be created.' ), true );
ck( 'its row is the sample list\'s, with what to do', false !== strpos( $sample_alone, '<td class="wpcpm-ui-cell--text" role="cell" data-label="What happens">This is the sample list\'s example student. Replace it with one of your students, or delete the row.</td>' ), true );
ck( 'and no create control is printed, so it creates nobody', array( false !== strpos( $sample_alone, 'Create these student records' ), false !== strpos( $sample_alone, 'wpcpm_import_confirm' ) ), array( false, false ) );
ck( 'while the list can still be thrown away', false !== strpos( $sample_alone, 'Throw this list away' ), true );

echo "\n=== Required fields say so ===\n";

// The form itself is drawn into $closed above: nothing is staged and fresh_world() left no
// flash, so render() takes the render_form() branch rather than the preview or the outcome.
ck( 'the start date label carries the mark, and the input keeps the attribute', false !== strpos( $closed, '<label class="wpcpm-ui-field__label" for="wpcpm-import-start">Start date <span class="wpcpm-ui-field__need">Required</span></label><input type="date" class="wpcpm-ui-field__control wpcpm-ui-field__control--date" id="wpcpm-import-start" name="start" required' ), true );
// The sentence and the mark share a `<span>` so the flex label sets them as one text run rather
// than as two columns; asserted here because the wrapper is the whole of that fix.
ck( 'so does the notified confirmation, right after its sentence and inside its own run', false !== strpos( $closed, 'for the WordPress Credits Program. <span class="wpcpm-ui-field__need">Required</span></span></span></label>' ), true );
ck( 'and every other field is left alone: the program picker is not marked', false !== strpos( $closed, '<label class="wpcpm-ui-field__label" for="wpcpm-import-program">Program</label>' ), true );

echo "\n=== A sample list is one link away ===\n";

// Institutions release 2: a school that has never seen the format is shown one. The link follows the file
// box's hint, so it is read right after the sentence that describes the file, and it is the nonced handler
// of the export class, which makes the file; no sample is stored anywhere in the repository.
$sample_href = 'https://example.test/wp-admin/admin-post.php?action=wpcpm_import_sample&_wpnonce=nonce-wpcpm_import_sample_' . $HERE;

ck( 'the file box\'s hint is followed by "Download a sample list", linking the nonced handler',
	false !== strpos( $closed, 'The file is read and never stored.</p></div><p class="wpcpm-ui-intro"><a href="' . $sample_href . '">Download a sample list</a></p>' ), true );
ck( 'it is drawn once', substr_count( $closed, 'Download a sample list' ), 1 );
ck( 'and it is the file box\'s own: after the file input, before the paste box',
	1 === preg_match( '#name="list" accept="\.csv,text/csv"[^>]*>.*Download a sample list.*<label class="wpcpm-ui-field__label" for="wpcpm-import-paste">#s', $closed ), true );
ck( 'the link is made for the institution the form is for and for no other', $GLOBALS['sample_calls'], array( $HERE ) );

// A staged list, or an outcome, replaces the form; the link belongs to the form alone.
post_check( batch_fields() );
$GLOBALS['sample_calls'] = array();
$with_batch_link = draw_section( $HERE );
ck( 'a waiting list shows its preview and not the link', array( false !== strpos( $with_batch_link, 'Download a sample list' ), $GLOBALS['sample_calls'] ), array( false, array() ) );
fresh_world();

// A list waiting to be looked at is the page asking the reader for something.
post_check( batch_fields() );
$with_batch = draw_section( $HERE );
ck( 'a waiting list is drawn in the card\'s block, in place of the form', array( 0 === strpos( $with_batch, '<section class="wpcpm-ui-block" id="wpcpm-import">' ), false !== strpos( $with_batch, 'Check the list</button>' ) ), array( true, false ) );

// So is a message from the last attempt, which folded away unread before this.
fresh_world();
post_check( batch_fields( array( 'notified' => '' ) ) );
$with_message = draw_section( $HERE );
// The message is taken once, when the card renders.
ck( 'something left to say prints', false !== strpos( $with_message, 'Confirm that these students have been notified' ), true );

fresh_world();
$GLOBALS['settings'] = array( 'import_enabled' => false );
ck( 'and none of it is drawn while the setting is off', draw_section( $HERE ), '' );
$GLOBALS['settings'] = array( 'import_enabled' => true );

// The one class of its own the form prints on the parts, its outcome's, has a rule in the page's stylesheet. The
// old form's fields (`.wpcpm-field--check`, `.wpcpm-field__hint`) and its section's rule above it
// (`.wpcpm-roster, .wpcpm-import, .wpcpm-people, .wpcpm-details`) went with the old form and its rules: the
// fields are the parts', and the view's block is the parts' (Task 23, fix round 1).
$css = file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' );
ck( '.wpcpm-import__message is styled on this page, and no rule is left for the old form\'s section', array( false !== strpos( $css, '.wpcpm-import__message' ), 1 === preg_match( '/^\.wpcpm-import\b/m', $css ) ), array( true, false ) );

echo "\n=== The file is read, never stored ===\n";

// The comment-free source built above. Reading the raw text made "nothing here moves an
// upload" fail on the sentence saying wp_handle_upload() would put a list of names into a
// web-served directory, which is the assertion punishing the documentation for being specific.
//
// `wp_handle_upload()` would move a list of names and addresses into the uploads directory,
// which this site serves over the web.
ck( 'nothing here moves an upload', false !== strpos( $code, 'wp_handle_upload' ), false );
// The size is checked before the read, so a large file costs the memory of nothing.
ck( 'the size is checked before the contents are read', strpos( $code, 'self::MAX_BYTES' ) < strpos( $code, 'file_get_contents' ), true );
ck( 'and only a real upload is read at all', false !== strpos( $code, 'is_uploaded_file' ), true );

// Never in this project, chat, code or comment alike.
foreach ( array( 'module' => $source, 'suite' => file_get_contents( __FILE__ ) ) as $what => $text ) {
	ck( sprintf( 'no dash but the plain hyphen in the %s', $what ), preg_match( '/[\x{2013}\x{2014}]/u', $text ), 0 );
}

echo "\n=== A tutor's hand-made posts are refused (Institutions release 1) ===\n";

// The policy here is this suite's stand-in, so a tutor is the one it refuses: the real rule, that a tutor holds the
// four view grounds and the add_student row is not among them, is `bin/test-institution-policy.php`'s. What this
// suite holds is the handlers' half: every one of the four asks for the add_student action, refuses on the
// policy's no with its one status, and writes no batch, no option and no record.
fresh_world();
post_check( batch_fields() );
$batch_id = WPCPM_Institution_Import::active_for( $HERE );
$GLOBALS['created'] = array();
$posts_before       = serialize( array( $GLOBALS['posts'], $GLOBALS['pmeta'] ) );
$opts_before        = serialize( $GLOBALS['opts'] );
$GLOBALS['allowed'] = false;

ck( 'a tutor\'s check posts a valid list and is refused', post_check( batch_fields() ), 'refused' );
ck( 'a tutor\'s cancel of the school\'s waiting batch is refused', post_batch( 'handle_cancel', $batch_id ), 'refused' );
ck( 'a tutor\'s confirm of it is refused', post_batch( 'handle_confirm', $batch_id ), 'refused' );
ck( 'a tutor\'s continue of it is refused', post_batch( 'handle_continue', $batch_id ), 'refused' );
$asked = array();
foreach ( array( 'handle_cancel', 'handle_confirm', 'handle_continue' ) as $handler ) {
	post_batch( $handler, $batch_id );
	foreach ( $GLOBALS['calls'] as $call ) { if ( 'decide' === $call[0] ) { $asked[ $handler ] = $call[1]; } }
}
ck( 'each asked the policy for add_student', $asked, array( 'handle_cancel' => 'add_student', 'handle_confirm' => 'add_student', 'handle_continue' => 'add_student' ) );
ck( 'and nothing was written: no batch, no meta, no option, no student', array( serialize( array( $GLOBALS['posts'], $GLOBALS['pmeta'] ) ) === $posts_before, serialize( $GLOBALS['opts'] ) === $opts_before, $GLOBALS['created'], WPCPM_Institution_Import::active_for( $HERE ) === $batch_id ), array( true, true, array(), true ) );
ck( 'and a tutor is drawn no Enroll students section at all', draw_section( $HERE ), '' );
$GLOBALS['allowed'] = true;

echo "\n=== The institution gate (the design's decision 10) ===\n";

// A student put on a Track Builder track before its status is in the reports automation never
// gets a report row, so the track is not offered until somebody ticks checklist item 1. The four
// built-in statuses are in the pinned list and are never gated.
fresh_world();
WPCPM_Institutions::$gated = array( WPCPM_Program::STATUS_DEV );
$gated_form                = draw_section( $HERE );

ck( 'a track whose automation item is not ticked is not in the picker',
    false !== strpos( $gated_form, 'value="' . WPCPM_Program::STATUS_DEV . '"' ), false );

ck( 'while the programs that are stay in it',
    false !== strpos( $gated_form, 'value="' . WPCPM_Program::STATUS_150H . '"' ), true );

fresh_world();
WPCPM_Institutions::$gated = array( WPCPM_Program::STATUS_DEV );

ck( 'and posting the gated one is refused by the handler, whatever the form said',
    post_check( batch_fields( array( 'program' => WPCPM_Program::STATUS_DEV ) ) ), 'bad-program' );

WPCPM_Institutions::$gated = array();

// If a program becomes gated between staging and confirming, the confirm handler catches it.
// This gives a specific message rather than the generic "This row could not be turned into a
// record" that every unvalidatable row would get if we let create_slice proceed.
fresh_world();
post_check( batch_fields( array( 'program' => WPCPM_Program::STATUS_150H ) ) );
$batch_id = WPCPM_Institution_Import::active_for( $HERE );

WPCPM_Institutions::$gated = array( WPCPM_Program::STATUS_150H );

ck( 'a batch staged with a program that is later ungated produces program-ungated, not the generic error',
    post_batch( 'handle_confirm', $batch_id ), 'program-ungated' );

WPCPM_Institutions::$gated = array();

echo "\n=== An administrator's press comes back to the institution they were viewing ===\n";

// The switcher is what puts an administrator on an institution's page, and the page they are sent
// back to has to carry it again: without it `resolve_institution()` falls through to the first
// institution with a member. The institution is the one the press acted on - the one the page
// resolved, or the one the batch was staged for - and never the switcher the form posted, which
// these presses post as another school. Every address is compared whole, a member's included, so
// a change to where a member lands cannot pass for being "no switcher".
$OTHER = 'recOTHER000000002';
$GHOST = 'recGHOST000000009';
$VIEW  = WPCPM_Institution_Roster::ARG_VIEW;

// The index holds both schools; it does not hold $GHOST.
$GLOBALS['indexed'] = array( $HERE, $OTHER );

ck( 'the argument the suite copies is the real class\'s', false !== strpos( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster.php' ), "const ARG_VIEW = '" . $VIEW . "';" ), true );

/**
 * Press one control and report where it left the reader.
 *
 * @param string $handler The handler's method name.
 * @param array  $post    What the form posts.
 * @param array  $get     What the address carries.
 * @return string The redirect.
 */
function press_to( $handler, array $post, array $get = array() ) {
	$GLOBALS['referer']  = array();
	$GLOBALS['calls']    = array();
	$GLOBALS['flash']    = array();
	$GLOBALS['redirect'] = '';
	$_POST               = $post;
	$_GET                = $get;
	$_FILES              = array();

	try {
		call_user_func( array( 'WPCPM_Institution_Import_Form', $handler ) );
	} catch ( Left $e ) {
		// Ended where it meant to.
	}

	return (string) $GLOBALS['redirect'];
}

/** The status the last press flashed. */
function said_now() {
	$flash = isset( $GLOBALS['flash']['institution_import'] ) ? $GLOBALS['flash']['institution_import'] : array();

	return isset( $flash['status'] ) ? $flash['status'] : '';
}

/**
 * The address a press leaves to: the dashboard's Enroll students view, the batch to open when there is one, the
 * institution when the reader is to come back to one, and the card.
 *
 * @param string $record Institutions record ID the address names, or ''.
 * @param int    $batch  The batch the address opens, or 0.
 * @return string
 */
function went_to( $record = '', $batch = 0 ) {
	// The Enroll students view, which holds the form (Task 9).
	$args = array( WPCPM_Institutions_Dashboard::VIEW_ARG . '=enroll' );

	if ( $batch > 0 ) {
		$args[] = WPCPM_Institution_Import_Form::ARG_BATCH . '=' . $batch;
	}

	if ( '' !== $record ) {
		$args[] = WPCPM_Institution_Roster::ARG_VIEW . '=' . $record;
	}

	return 'https://example.test/institution-dashboard/' . ( empty( $args ) ? '' : '?' . implode( '&', $args ) ) . '#wpcpm-import';
}

$other_view = array( $VIEW => $OTHER );

foreach ( array( 'an administrator' => true, 'a member' => false ) as $who => $manager ) {
	$GLOBALS['manage']   = $manager;
	$GLOBALS['resolved'] = $HERE;
	$GLOBALS['allowed']  = true;
	$GLOBALS['created']  = array();
	$GLOBALS['settings'] = array( 'import_enabled' => true );
	fresh_world();

	// The record an address names for this reader: the institution acted on for an administrator,
	// and none at all for a member, whose own membership places them.
	$named = $manager ? $HERE : '';

	$went = press_to( 'handle_check', batch_fields() + $other_view, $other_view );

	ck( $who . ': checking a list stages it', said_now(), 'checked' );
	ck( $who . ': and the address ' . ( $manager ? 'is the institution they were viewing, with the batch to open' : 'is the dashboard and the batch to open, nothing more' ), $went, went_to( $named, WPCPM_Institution_Import::active_for( $HERE ) ) );

	$went = press_to( 'handle_check', batch_fields() + $other_view, $other_view );

	ck( $who . ': a second list is refused while one is waiting', said_now(), 'already-staged' );
	ck( $who . ': and the refusal ' . ( $manager ? 'comes back to the same institution' : 'is the Enroll students view, with no school' ), $went, went_to( $named ) );

	// Every control on a staged list reads its institution off the batch, whoever is resolved.
	$GLOBALS['resolved'] = $OTHER;
	$batch_id            = WPCPM_Institution_Import::active_for( $HERE );

	ck( $who . ': there is a list to act on', $batch_id > 0, true );

	$went = press_to( 'handle_confirm', array( 'batch' => (string) $batch_id ) + $other_view, $other_view );

	ck( $who . ': confirming creates the list', said_now(), 'created' );
	ck( $who . ': and the address ' . ( $manager ? 'is the batch\'s own institution, never the posted one' : 'is the Enroll students view, with no school' ), $went, went_to( $named ) );

	$went = press_to( 'handle_confirm', array( 'batch' => (string) $batch_id ) + $other_view, $other_view );

	ck( $who . ': confirming twice is refused', said_now(), 'not-staged-now' );
	ck( $who . ': and the refusal ' . ( $manager ? 'comes back to the same institution' : 'is the Enroll students view, with no school' ), $went, went_to( $named ) );

	$went = press_to( 'handle_continue', array( 'batch' => (string) $batch_id ) + $other_view, $other_view );

	ck( $who . ': carrying on with a list that is not running is refused', said_now(), 'not-creating-now' );
	ck( $who . ': and the refusal ' . ( $manager ? 'comes back to the batch\'s institution' : 'is the Enroll students view, with no school' ), $went, went_to( $named ) );

	// A list that is part way through is carried on from the batch too.
	fresh_world();
	$GLOBALS['resolved'] = $HERE;
	press_to( 'handle_check', batch_fields(), array() );
	$running = WPCPM_Institution_Import::active_for( $HERE );
	WPCPM_Institution_Create::claim( $running, 7, 'member' );
	update_post_meta( $running, WPCPM_Institution_Import::META_STATE, WPCPM_Institution_Import::STATE_CREATING );
	$GLOBALS['resolved'] = $OTHER;

	$went = press_to( 'handle_continue', array( 'batch' => (string) $running ) + $other_view, $other_view );

	ck( $who . ': carrying on with a running list creates the rest', said_now(), 'created' );
	ck( $who . ': and the address ' . ( $manager ? 'is the batch\'s own institution, never the posted one' : 'is the Enroll students view, with no school' ), $went, went_to( $named ) );

	// Throwing a list away.
	fresh_world();
	$GLOBALS['resolved'] = $HERE;
	press_to( 'handle_check', batch_fields(), array() );
	$staged_now = WPCPM_Institution_Import::active_for( $HERE );
	$GLOBALS['resolved'] = $OTHER;

	$went = press_to( 'handle_cancel', array( 'batch' => (string) $staged_now ) + $other_view, $other_view );

	ck( $who . ': throwing a list away says so', said_now(), 'cancelled' );
	ck( $who . ': and the address ' . ( $manager ? 'is the batch\'s own institution, never the posted one' : 'is the Enroll students view, with no school' ), $went, went_to( $named ) );

	// A list that is already gone: the press acted on nothing, so an administrator comes back to
	// the institution the form said they were viewing, and a member to the dashboard.
	$went = press_to( 'handle_cancel', array( 'batch' => (string) $staged_now ) + $other_view, $other_view );

	ck( $who . ': throwing away a list that is gone says so', said_now(), 'no-batch' );
	ck( $who . ': and the address ' . ( $manager ? 'is the institution the switcher named' : 'is the Enroll students view, with no school, whatever they posted' ), $went, went_to( $manager ? $OTHER : '' ) );
}

$GLOBALS['manage']   = false;
$GLOBALS['resolved'] = $HERE;

echo "\n=== A press that acted on nothing comes back to the institution they were viewing ===\n";

// The batch is gone, the feature is off, or the reader is refused before anything is read: there
// is no institution the press acted on, so the address is navigation, and an administrator is
// sent back to the one the form says they were looking at. `resolve_institution()` checks it
// again on the next load, so the value only has to be well formed, held by the index and posted
// by someone who holds the capability.
$GLOBALS['manage']   = true;
$GLOBALS['resolved'] = $HERE;
$GLOBALS['allowed']  = true;
$GLOBALS['settings'] = array( 'import_enabled' => true );
fresh_world();

$gone = array( 'batch' => '987654' );

ck( 'there is no such batch', is_array( WPCPM_Institution_Import::batch( 987654 ) ), false );

foreach ( array( 'handle_cancel', 'handle_confirm', 'handle_continue' ) as $handler ) {
	$went = press_to( $handler, $gone + $other_view, $other_view );
	ck( $handler . ': a gone list says so', said_now(), 'no-batch' );
	ck( $handler . ': an administrator comes back to the institution the switcher named', $went, went_to( $OTHER ) );

	$went = press_to( $handler, $gone + $other_view );
	ck( $handler . ': read from the form alone', $went, went_to( $OTHER ) );

	$went = press_to( $handler, $gone, $other_view );
	ck( $handler . ': read from the address alone', $went, went_to( $OTHER ) );

	$went = press_to( $handler, $gone + array( $VIEW => $GHOST ), array( $VIEW => $GHOST ) );
	ck( $handler . ': an institution the index does not hold adds nothing', $went, went_to() );

	$GLOBALS['indexed'][] = 'recno';
	$went = press_to( $handler, $gone + array( $VIEW => 'recno' ), array( $VIEW => 'recno' ) );
	ck( $handler . ': a value that is not a record ID adds nothing, even one the index holds', $went, went_to() );
	array_pop( $GLOBALS['indexed'] );

	$went = press_to( $handler, $gone );
	ck( $handler . ': no switcher at all adds nothing', $went, went_to() );
}

// Both places at once: the address is read before the form, the order `requested_view()` reads
// them in, so a request that carries two schools comes back to the one in its address.
$THIRD                = 'recTHIRD000000003';
$GLOBALS['indexed'][] = $THIRD;

foreach ( array( 'handle_cancel', 'handle_confirm', 'handle_continue' ) as $handler ) {
	$went = press_to( $handler, $gone + array( $VIEW => $THIRD ), $other_view );
	ck( $handler . ': a request with one school in its address and another in its form comes back to the address\'s', $went, went_to( $OTHER ) );
}

array_pop( $GLOBALS['indexed'] );

// The feature switched off between the page being drawn and the press.
$GLOBALS['settings'] = array( 'import_enabled' => false );

foreach ( array( 'handle_check', 'handle_confirm', 'handle_continue' ) as $handler ) {
	$went = press_to( $handler, $gone + $other_view, $other_view );
	ck( $handler . ': switched off says so', said_now(), 'off' );
	ck( $handler . ': and an administrator comes back to the institution the switcher named', $went, went_to( $OTHER ) );
}

$GLOBALS['settings'] = array( 'import_enabled' => true );

// An administrator no institution resolves for is refused before anything is read.
$GLOBALS['resolved'] = '';
$went                = press_to( 'handle_check', batch_fields() + $other_view, $other_view );
ck( 'checking with no institution to act for is refused', said_now(), 'refused' );
ck( 'and an administrator comes back to the institution the switcher named', $went, went_to( $OTHER ) );
$GLOBALS['resolved'] = $HERE;

// A press the policy refuses names no institution either.
$GLOBALS['allowed'] = false;
$went               = press_to( 'handle_check', batch_fields() + $other_view, $other_view );
ck( 'a refused check says so', said_now(), 'refused' );
ck( 'and comes back to the institution the switcher named', $went, went_to( $OTHER ) );
$GLOBALS['allowed'] = true;

// A press that did act keeps the rule that a press takes its institution from what it acted on: the
// posted switcher is not consulted, even though it is well formed and the index holds it.
fresh_world();
$went = press_to( 'handle_check', batch_fields() + $other_view, $other_view );
ck( 'checking a list is still the institution acted on', $went, went_to( $HERE, WPCPM_Institution_Import::active_for( $HERE ) ) );

$staged_now = WPCPM_Institution_Import::active_for( $HERE );
$went       = press_to( 'handle_cancel', array( 'batch' => (string) $staged_now ) + $other_view, $other_view );
ck( 'and so is throwing it away', $went, went_to( $HERE ) );

// A list stamped with something that is no record ID has an institution of its own, only an unusable
// one, so the press adds nothing: the switcher the form carried is not asked in its place.
fresh_world();
press_to( 'handle_check', batch_fields(), array() );
$malformed = WPCPM_Institution_Import::active_for( $HERE );
update_post_meta( $malformed, WPCPM_Institution_Import::META_INSTITUTION, 'recno' );
$went = press_to( 'handle_cancel', array( 'batch' => (string) $malformed ) + $other_view, $other_view );
ck( 'throwing away a list stamped with no record ID cancels it', said_now(), 'cancelled' );
ck( 'and an administrator comes back to the dashboard, not to the institution the switcher named', $went, went_to() );

// A member, whatever the form says, goes where they always did.
$GLOBALS['manage'] = false;

foreach ( array( 'handle_cancel', 'handle_confirm', 'handle_continue' ) as $handler ) {
	$went = press_to( $handler, $gone + $other_view, $other_view );
	ck( $handler . ': a member who pressed on a gone list goes to the dashboard', $went, went_to() );
}

$GLOBALS['settings'] = array( 'import_enabled' => false );
$went                = press_to( 'handle_check', batch_fields() + $other_view, $other_view );
ck( 'a member who pressed while the feature is off goes to the dashboard', $went, went_to() );
$GLOBALS['settings'] = array( 'import_enabled' => true );

$GLOBALS['resolved'] = '';
$went                = press_to( 'handle_check', batch_fields() + $other_view, $other_view );
ck( 'and a member refused before anything is read goes to the dashboard', $went, went_to() );

$GLOBALS['manage']   = false;
$GLOBALS['resolved'] = $HERE;
$GLOBALS['indexed']  = array();

// The check form itself: its handler works out the institution from the request alone, and a
// post to `admin-post.php` carries the form's fields and none of the page's query string, so an
// administrator's form names the institution on the action address, as the panel's do.
fresh_world();
$GLOBALS['manage'] = true;
$mine              = draw_section( $HERE );
$GLOBALS['manage'] = false;
$theirs            = draw_section( $HERE );

ck( 'an administrator\'s form posts to the institution they are viewing', false !== strpos( $mine, 'admin-post.php?' . $VIEW . '=' . $HERE ), true );
ck( 'a member\'s form posts to admin-post.php as it did', false !== strpos( $theirs, 'action="https://example.test/wp-admin/admin-post.php"' ), true );
ck( 'and carries no switcher argument anywhere', false === strpos( $theirs, $VIEW ), true );

echo "\n=== Every form on a staged list names the institution an administrator is viewing ===\n";

// A press that acted on nothing, such as Cancel on a list another tab already threw away, comes
// back to the institution the request says was being viewed, and a post to `admin-post.php`
// carries the form's fields and none of the page's query string. So every control on a list
// names the institution on its action address, for an administrator and for nobody else, and the
// handlers still take the institution from the batch whenever there is one.

/** The markup of the form that posts one action, from its `<form` on. */
function form_chunk( $html, $action ) {
	foreach ( explode( '<form', (string) $html ) as $chunk ) {
		if ( false !== strpos( $chunk, 'value="' . $action . '"' ) ) { return $chunk; }
	}

	return '';
}

/** The address a form posts to. */
function action_of( $chunk ) {
	return preg_match( '/\baction="([^"]*)"/', (string) $chunk, $found ) ? html_entity_decode( $found[1], ENT_QUOTES ) : '';
}

/** What a browser puts in the query string when this form is pressed: the query of its action address. */
function query_of( $chunk ) {
	parse_str( (string) parse_url( action_of( $chunk ), PHP_URL_QUERY ), $query );

	return $query;
}

/** The hidden fields of a form, name to value. */
function fields_of( $chunk ) {
	$fields = array();

	if ( preg_match_all( '/<input\b[^>]*>/i', (string) $chunk, $inputs ) ) {
		foreach ( $inputs[0] as $input ) {
			if ( preg_match( '/name="([^"]+)"/', $input, $name ) && preg_match( '/value="([^"]*)"/', $input, $value ) ) {
				$fields[ $name[1] ] = html_entity_decode( $value[1], ENT_QUOTES );
			}
		}
	}

	return $fields;
}

$POSTS_TO = 'https://example.test/wp-admin/admin-post.php';

foreach ( array( 'an administrator' => true, 'a member' => false ) as $who => $manager ) {
	$GLOBALS['manage']   = $manager;
	$GLOBALS['resolved'] = $HERE;
	$GLOBALS['allowed']  = true;
	$GLOBALS['settings'] = array( 'import_enabled' => true );
	$GLOBALS['indexed']  = array( $HERE, $OTHER );

	$posts_to = $manager ? $POSTS_TO . '?' . $VIEW . '=' . $HERE : $POSTS_TO;

	// A list waiting to be looked at: Confirm and throw away.
	fresh_world();
	post_check( batch_fields() );
	$waiting = draw_section( $HERE );
	$cancel  = form_chunk( $waiting, WPCPM_Institution_Import_Form::ACTION_CANCEL );
	$confirm = form_chunk( $waiting, WPCPM_Institution_Import_Form::ACTION_CONFIRM );

	ck( $who . ': the list can be thrown away', '' !== $cancel, true );
	ck( $who . ': the throw-away form posts to ' . ( $manager ? 'the institution they are viewing' : 'admin-post.php as it did' ), action_of( $cancel ), $posts_to );
	ck( $who . ': the Confirm form posts to ' . ( $manager ? 'the institution they are viewing' : 'admin-post.php as it did' ), action_of( $confirm ), $posts_to );

	// The other tab throws the list away; this page still holds its forms.
	WPCPM_Institution_Import::cancel( WPCPM_Institution_Import::active_for( $HERE ) );

	ck( $who . ': the list is gone', WPCPM_Institution_Import::active_for( $HERE ), 0 );

	$went = press_to( 'handle_cancel', fields_of( $cancel ), query_of( $cancel ) );
	ck( $who . ': throwing away a list that is gone says so', said_now(), 'no-batch' );
	ck( $who . ': and comes back to ' . ( $manager ? 'the institution they were viewing' : 'the Enroll students view, with no school' ), $went, went_to( $manager ? $HERE : '' ) );

	$went = press_to( 'handle_confirm', fields_of( $confirm ), query_of( $confirm ) );
	ck( $who . ': confirming a list that is gone says so', said_now(), 'no-batch' );
	ck( $who . ': and comes back to ' . ( $manager ? 'the institution they were viewing' : 'the Enroll students view, with no school' ), $went, went_to( $manager ? $HERE : '' ) );

	// A list part way through: Continue.
	fresh_world();
	post_check( batch_fields() );
	$running = WPCPM_Institution_Import::active_for( $HERE );
	WPCPM_Institution_Create::claim( $running, 7, 'member' );
	update_post_meta( $running, WPCPM_Institution_Import::META_STATE, WPCPM_Institution_Import::STATE_CREATING );
	$progress = draw_section( $HERE );
	$continue = form_chunk( $progress, WPCPM_Institution_Import_Form::ACTION_CONTINUE );

	ck( $who . ': a list part way through can be carried on', '' !== $continue, true );
	ck( $who . ': the Continue form posts to ' . ( $manager ? 'the institution they are viewing' : 'admin-post.php as it did' ), action_of( $continue ), $posts_to );

	wp_delete_post( $running, true );

	$went = press_to( 'handle_continue', fields_of( $continue ), query_of( $continue ) );
	ck( $who . ': carrying on with a list that is gone says so', said_now(), 'no-batch' );
	ck( $who . ': and comes back to ' . ( $manager ? 'the institution they were viewing' : 'the Enroll students view, with no school' ), $went, went_to( $manager ? $HERE : '' ) );

	// The forms are the same forms with the switcher on their address: a press that did act is
	// still the batch's own institution, whatever the address says.
	fresh_world();
	post_check( batch_fields() );
	$waiting = draw_section( $HERE );
	$cancel  = form_chunk( $waiting, WPCPM_Institution_Import_Form::ACTION_CANCEL );
	$query   = $manager ? array( $VIEW => $OTHER ) : query_of( $cancel );
	$went    = press_to( 'handle_cancel', fields_of( $cancel ), $query );
	ck( $who . ': throwing a list away that is there says so', said_now(), 'cancelled' );
	ck( $who . ': and comes back to ' . ( $manager ? 'the batch\'s institution, not the one the address named' : 'the Enroll students view, with no school' ), $went, went_to( $manager ? $HERE : '' ) );
}

$GLOBALS['manage']   = false;
$GLOBALS['resolved'] = $HERE;
$GLOBALS['indexed']  = array();

echo "\n=== A refused check keeps everything, and marks the answer at its field (1.123.58) ===\n";

/** The one-student fields and a list of as many rows as asked, as a school would post them. */
function full_post( array $over = array(), $rows = 300 ) {
	$lines = array( 'Name,Email' );
	for ( $i = 1; $i <= $rows; ++$i ) {
		$lines[] = sprintf( 'Student %1$d,student-%1$d@institution-3.example', $i );
	}

	return batch_fields(
		array_merge(
			array(
				'program'        => 'In Sensei',
				'start'          => gmdate( 'Y-m-d', time() + 400 * DAY_IN_SECONDS ),
				'end'            => gmdate( 'Y-m-d', time() + 500 * DAY_IN_SECONDS ),
				'notified'       => '1',
				'paste'          => implode( "\n", $lines ),
				'name'           => 'Dora Example',
				'email'          => 'dora@institution-3.example',
				'field_of_study' => 'Technology & Engineering',
			),
			$over
		)
	);
}

/** The markup of one control, from its opening tag to the end of the tag. */
function control_of( $html, $id ) {
	return preg_match( '#<(?:input|select|textarea)\b[^>]*\bid="' . preg_quote( $id, '#' ) . '"[^>]*>#', (string) $html, $found ) ? $found[0] : '';
}

fresh_world();
$posted = full_post();
$status = post_check( $posted );
$again  = draw_section( $HERE );
// A browser drops the one line break the box's markup opens with.
$paste  = preg_match( '#<textarea [^>]*id="wpcpm-import-paste"[^>]*>\n?(.*?)</textarea>#s', $again, $box ) ? html_entity_decode( $box[1], ENT_QUOTES ) : '';

ck(
	'a start date a year away keeps the program, the dates, the tick, a pasted list of 300 rows and the one-student fields, and marks the start date',
	array(
		$status,
		false !== strpos( $again, '<option value="In Sensei" selected>' ),
		false !== strpos( control_of( $again, 'wpcpm-import-start' ), 'value="' . $posted['start'] . '"' ),
		false !== strpos( control_of( $again, 'wpcpm-import-end' ), 'value="' . $posted['end'] . '"' ),
		1 === preg_match( '/\schecked\b/', control_of( $again, 'wpcpm-import-notified' ) ),
		count( explode( "\n", $paste ) ),
		$paste === $posted['paste'],
		false !== strpos( control_of( $again, 'wpcpm-import-name' ), 'value="Dora Example"' ),
		false !== strpos( control_of( $again, 'wpcpm-import-email' ), 'value="dora@institution-3.example"' ),
		false !== strpos( $again, '<option value="Technology &amp; Engineering" selected>' ),
		false !== strpos( control_of( $again, 'wpcpm-import-start' ), 'aria-invalid="true"' ),
		false !== strpos( $again, '<p class="wpcpm-field__problem" id="wpcpm-import-start-problem">That start date is more than a year away. Check the year.</p>' ),
		false !== strpos( $again, 'One answer needs a change' ),
		false !== strpos( control_of( $again, 'wpcpm-import-end' ), 'aria-invalid' ),
	),
	array( 'start-far', true, true, true, true, 301, true, true, true, true, true, true, true, false )
);
ck( 'the summary is inside the form, over its first field, linking the start date', 1 === preg_match( '#<form method="post" enctype="multipart/form-data".*<div class="wpcpm-errors" role="alert" tabindex="-1">.*<a href="\#wpcpm-import-start">Start date</a>.*<label class="wpcpm-ui-field__label" for="wpcpm-import-program">#s', $again ), true );
ck( 'the refusal at the top is an error in its tone, as an alert, focusable', false !== strpos( $again, '<p class="wpcpm-import__message wpcpm-import__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>That start date is more than a year away. Check the year.</p>' ), true );
ck( 'and what was kept is drawn once: the next drawing is the empty form', false !== strpos( control_of( draw_section( $HERE ), 'wpcpm-import-start' ), 'value=""' ), true );

foreach ( array(
	'an end date before the start is marked at the end date' => array( array( 'start' => gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ), 'end' => '2020-01-01' ), 'bad-end', 'wpcpm-import-end' ),
	'the tick left unticked is marked at the tick'             => array( array( 'start' => gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ), 'end' => '', 'notified' => '' ), 'not-notified', 'wpcpm-import-notified' ),
	'a program this site does not offer is marked at the program' => array( array( 'program' => 'Astronaut Track' ), 'bad-program', 'wpcpm-import-program' ),
) as $label => $case ) {
	fresh_world();
	$status = post_check( full_post( $case[0], 3 ) );
	$again  = draw_section( $HERE );
	ck( $label, array( $status, false !== strpos( control_of( $again, $case[2] ), 'aria-invalid="true"' ), substr_count( $again, 'aria-invalid="true"' ) ), array( $case[1], true, 1 ) );
}

fresh_world();
$status = post_check( full_post( array( 'start' => gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ), 'end' => '', 'paste' => "Name\nAnna Kowalska" ), 0 ) );
$again  = draw_section( $HERE );
ck( 'a pasted list the reader cannot read is marked at the paste box', array( $status, false !== strpos( control_of( $again, 'wpcpm-import-paste' ), 'aria-invalid="true"' ), false !== strpos( $again, 'id="wpcpm-import-paste-problem">That list needs a name column and an email column.</p>' ) ), array( 'parse-no_columns', true, true ) );

fresh_world();
$status = post_check( full_post( array( '__files' => array( 'list' => array( 'name' => 'class.csv', 'tmp_name' => '/tmp/not-an-upload', 'size' => 120, 'error' => UPLOAD_ERR_OK ) ) ), 3 ) );
$again  = draw_section( $HERE );
ck( 'a chosen file is asked for again at the file input', array( $status, false !== strpos( $again, '<p class="wpcpm-field__problem" id="wpcpm-import-file-problem">A chosen file cannot be kept when the form comes back. Choose the file again.</p>' ), false !== strpos( control_of( $again, 'wpcpm-import-file' ), 'aria-invalid="true"' ) ), array( 'start-far', true, true ) );
ck( 'and the summary counts it beside the start date', false !== strpos( $again, 'Two answers need a change' ), true );

$big = tempnam( sys_get_temp_dir(), 'wpcpm-import-' );
file_put_contents( $big, "Name,Email\n" . str_repeat( "Anna Kowalska,anna@institution-3.example\n", (int) ceil( 300 * 1024 / 41 ) ) );
fresh_world();
$status = post_check( batch_fields( array( 'paste' => '', '__files' => array( 'list' => array( 'name' => 'class.csv', 'tmp_name' => $big, 'size' => 300 * 1024, 'error' => UPLOAD_ERR_OK ) ) ) ) );
$again  = draw_section( $HERE );
ck( 'a file over the limit is too large, never nothing was sent', array( $status, false !== strpos( $again, 'Nothing was sent' ), false !== strpos( $again, 'id="wpcpm-import-file-problem">That file is too big. Send it in two halves.</p>' ) ), array( 'parse-too_large', false, true ) );
unlink( $big );

fresh_world();
$status = post_check( batch_fields( array( 'paste' => '', '__files' => array( 'list' => array( 'name' => 'class.csv', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_INI_SIZE ) ) ) ) );
ck( 'and a file PHP refused for its size is too large too', $status, 'parse-too_large' );
fresh_world();
$status = post_check( batch_fields( array( 'paste' => '', '__files' => array( 'list' => array( 'name' => 'class.csv', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_FORM_SIZE ) ) ) ) );
ck( 'and so is one over the form\'s own size', $status, 'parse-too_large' );

foreach ( array( 'a partial upload' => UPLOAD_ERR_PARTIAL, 'no temporary directory' => UPLOAD_ERR_NO_TMP_DIR, 'a failed write' => UPLOAD_ERR_CANT_WRITE, 'an extension' => UPLOAD_ERR_EXTENSION, 'a file read as nothing' => UPLOAD_ERR_OK ) as $why => $code ) {
	fresh_world();
	$status = post_check( batch_fields( array( '__files' => array( 'list' => array( 'name' => 'class.csv', 'tmp_name' => UPLOAD_ERR_OK === $code ? '/tmp/not-an-upload' : '', 'size' => 0, 'error' => $code ) ) ) ) );
	$again  = draw_section( $HERE );
	ck(
		sprintf( 'a chosen file with a non-size upload error (%s) is refused at the file field and the kept paste is not checked', $why ),
		array( $status, WPCPM_Institution_Import::active_for( $HERE ), false !== strpos( $again, 'id="wpcpm-import-file-problem">That file could not be read. Choose it again.</p>' ), false !== strpos( control_of( $again, 'wpcpm-import-file' ), 'aria-invalid="true"' ), false !== strpos( $again, 'anna@institution-3.example' ) ),
		array( 'file-unreadable', 0, true, true, true )
	);
}

// Five presses refused for a date in one hour spend none of the five checks an hour allows.
fresh_world();
$wrong = array();
for ( $i = 0; $i < 5; ++$i ) {
	$wrong[] = post_check( batch_fields( array( 'start' => '2036-09-01' ) ) );
}
ck( 'five presses refused for a wrong date in one hour leave the sixth, correct press checked (not too_often)', array( $wrong, post_check( batch_fields() ) ), array( array_fill( 0, 5, 'start-far' ), 'checked' ) );

ck( 'the file input\'s hint says the limit', false !== strpos( $closed, '<p class="wpcpm-ui-field__hint" id="wpcpm-import-file-hint">A CSV file of 256 KB at most. A header row,' ), true );

$lines = array( 'Name,Email' );
for ( $i = 1; strlen( implode( "\n", $lines ) ) <= WPCPM_Flash::KEPT_MAX; ++$i ) {
	$lines[] = sprintf( 'Student %1$d,student-%1$d@institution-3.example', $i );
}
$long = substr( implode( "\n", $lines ), 0, WPCPM_Flash::KEPT_MAX + 1 );
fresh_world();
$status = post_check( batch_fields( array( 'paste' => $long ) ) );
$kept   = isset( $GLOBALS['flash'][ 'kept:import-batch:' . $HERE ] ) ? $GLOBALS['flash'][ 'kept:import-batch:' . $HERE ] : array();
$again  = draw_section( $HERE );
ck( 'a pasted list too long to keep is asked for again at the paste box', array( strlen( $long ), $status, array_key_exists( 'paste', isset( $kept['values'] ) ? $kept['values'] : array( 'paste' => 1 ) ), isset( $kept['problems']['wpcpm-import-paste'] ) ? $kept['problems']['wpcpm-import-paste'] : '', 1 === preg_match( '#<textarea [^>]*id="wpcpm-import-paste"[^>]*>\n?</textarea>#', $again ) ), array( WPCPM_Flash::KEPT_MAX + 1, 'parse-too_many_rows', false, 'The pasted list was too long to keep here: paste it again.', true ) );

// The four forms the card draws in its three states: the form, a waiting list, a list part way through.
fresh_world();
$forms = draw_section( $HERE );
post_check( batch_fields() );
$forms  .= draw_section( $HERE );
$running = WPCPM_Institution_Import::active_for( $HERE );
WPCPM_Institution_Create::claim( $running, 7, 'member' );
update_post_meta( $running, WPCPM_Institution_Import::META_STATE, WPCPM_Institution_Import::STATE_CREATING );
$forms .= draw_section( $HERE );
preg_match_all( '#<form\b[^>]*>#', $forms, $tags );
$busy = array();
foreach ( $tags[0] as $tag ) {
	$busy[] = array( false !== strpos( $tag, ' data-wpcpm-once' ), preg_match( '#data-wpcpm-busy="([^"]*)"#', $tag, $word ) ? $word[1] : '' );
}
ck( 'the four import forms are guarded with their busy words', $busy, array( array( true, 'Checking the list' ), array( true, 'Creating the records' ), array( true, 'Canceling' ), array( true, 'Creating the records' ) ) );

echo "\n=== The waiting list's sentence, one method for the card and the Overview ===\n";

// Institution Dashboard frame, Task 4: `waiting_sentence()` is the sentence the card prints above a checked list
// not yet created and above a creation in progress, and the Overview's waiting row reads it as its why: the card prints
// that same sentence.
$waiting_sentence = static function ( $batch_id, $record = null ) use ( $HERE ) {
	return method_exists( 'WPCPM_Institution_Import_Form', 'waiting_sentence' ) ? WPCPM_Institution_Import_Form::waiting_sentence( (int) $batch_id, null === $record ? $HERE : $record ) : 'no waiting_sentence()';
};
$wait_fields = batch_fields( array( 'paste' => "Name,Email\nAnna Kowalska,anna@institution-3.example\nBartek Zielinski,bartek@institution-3.example\nAna Example,ana@example.test\n" ) );
fresh_world();
$wait_pages = array( 'form' => draw_section( $HERE ) );
ck( 'waiting_sentence(): no list, nothing', $waiting_sentence( WPCPM_Institution_Import::active_for( $HERE ) ), '' );
post_check( $wait_fields );
$waiting             = WPCPM_Institution_Import::active_for( $HERE );
$wait_pages['staged'] = draw_section( $HERE );
ck( 'waiting_sentence(): a checked list says how many of its rows can be created', $waiting_sentence( $waiting ), '2 of 3 rows can be created.' );
WPCPM_Institution_Create::claim( $waiting, 7, 'member' );
update_post_meta( $waiting, WPCPM_Institution_Import::META_STATE, WPCPM_Institution_Import::STATE_CREATING );
$wait_pages['creating'] = draw_section( $HERE );
ck( 'waiting_sentence(): a creation in progress says how far it is', $waiting_sentence( $waiting ), '0 created so far. 2 still to create.' );
ck( 'waiting_sentence(): a number that is no batch says nothing', $waiting_sentence( 999999 ), '' );
// Fix round 1, Minor 4: the sentence answers only for the school the batch belongs to.
ck( 'waiting_sentence(): another school\'s batch says nothing', array( $waiting_sentence( $waiting, $THERE ), $waiting_sentence( $waiting, '' ) ), array( '', '' ) );
ck( 'and each sentence is in its card, escaped as the card escapes it', array(
	false !== strpos( $wait_pages['staged'], '<p class="wpcpm-ui-intro">' . esc_html( '2 of 3 rows can be created.' ) . '</p>' ),
	false !== strpos( $wait_pages['creating'], '<p class="wpcpm-ui-intro">' . esc_html( '0 created so far. 2 still to create.' ) . '</p>' ),
), array( true, true ) );

echo "\n=== Enroll students on the parts (Institution Dashboard, Task 17) ===\n";

/** The long list of the layout fixture: thirteen rows, a 40-character word in a name, a 48-character address, five refused. */
function long_list() {
	return implode(
		"\n",
		array(
			'Name,Email',
			'Ama Exampleunbrokenstudentnamewithfortychars,averyveryveryverylongmailboxname1234@example.org',
			'Ben Example,ben@example.org',
			'Cara Example,cara@example.org',
			'Dan Example,not-an-address',
			'Eva Example,eva@example.org',
			'Eva Example,eva@example.org',
			',nobody@example.org',
			'Ana Example,ana@example.test',
			'Finn Example,finn@example.org',
			'Gia Example,gia@example.org',
			'Hugo Example,hugo@example.org',
			'Ida Example,ida@example.org',
			'Jon Example,jon@example.org',
		)
	) . "\n";
}

/** A world for one case: fresh, the batch numbers from the same start, nothing recorded yet. */
function case_world() {
	fresh_world();
	$GLOBALS['next_id']      = 900;
	$GLOBALS['manage']       = false;
	$GLOBALS['allowed']      = true;
	$GLOBALS['kept']         = array();
	$GLOBALS['calls']        = array();
	$GLOBALS['sample_calls'] = array();
	WPCPM_Institutions::$gated = array();
}

/** Draw the card as it is set up, keeping the policy's calls, the nonces asked for and the sample link's. */
function draw_case( $record, array &$calls ) {
	$GLOBALS['calls']        = array();
	$GLOBALS['sample_calls'] = array();
	$html                    = draw_section( $record );
	$calls                   = array( $GLOBALS['calls'], $GLOBALS['sample_calls'] );
	return $html;
}

/**
 * Every state the card draws, set up the same way each time it is asked for, drawn on whichever branch the switch says.
 *
 * @return array{html: string[], calls: array[]}
 */
function import_cases() {
	global $HERE;
	$html  = array();
	$calls = array();
	$one   = array();

	case_world();
	$html['form'] = draw_case( $HERE, $one );
	$calls['form'] = $one;

	case_world();
	$GLOBALS['manage']   = true;
	$html['form, an Administrator'] = draw_case( $HERE, $one );
	$calls['form, an Administrator'] = $one;

	case_world();
	$GLOBALS['kept'][ $HERE ] = array( 'tutors' => array( array( 'name' => 'Tutor Exampleunbrokentutornamewithfortycharss', 'email' => 'tutor@example.org' ), array( 'name' => 'Ola Example', 'email' => 'ola@example.org' ) ) );
	$html['form, tutors listed'] = draw_case( $HERE, $one );
	$calls['form, tutors listed'] = $one;

	case_world();
	post_check( full_post( array( '__files' => array( 'list' => array( 'name' => 'class.csv', 'tmp_name' => '/tmp/not-an-upload', 'size' => 120, 'error' => UPLOAD_ERR_OK ) ) ), 3 ) );
	$html['refused, typing kept'] = draw_case( $HERE, $one );
	$calls['refused, typing kept'] = $one;

	case_world();
	post_check( full_post( array( 'start' => gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ), 'end' => '', 'notified' => '' ), 3 ) );
	$html['refused at the tick'] = draw_case( $HERE, $one );
	$calls['refused at the tick'] = $one;

	case_world();
	post_check( full_post( array( 'start' => gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ), 'end' => '', 'paste' => "Name\nAnna Kowalska" ), 0 ) );
	$html['refused at the paste'] = draw_case( $HERE, $one );
	$calls['refused at the paste'] = $one;

	case_world();
	post_check( full_post( array( 'start' => gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ), 'end' => '2020-01-01' ), 3 ) );
	$html['refused at the end'] = draw_case( $HERE, $one );
	$calls['refused at the end'] = $one;

	case_world();
	post_check( batch_fields( array( 'paste' => long_list() ) ) );
	$html['check, thirteen rows'] = draw_case( $HERE, $one );
	$calls['check, thirteen rows'] = $one;

	case_world();
	$GLOBALS['manage'] = true;
	post_check( batch_fields( array( 'paste' => "Name,Email,Phone\nBen Example,ben@example.org,123\nCara Example,cara@example.org,456\n" ) ) );
	$html['check, ignored columns, an Administrator'] = draw_case( $HERE, $one );
	$calls['check, ignored columns, an Administrator'] = $one;

	case_world();
	post_check( batch_fields( array( 'paste' => "name,email\nAna Example,ana@example.test\n" ) ) );
	$html['check, nothing to create'] = draw_case( $HERE, $one );
	$calls['check, nothing to create'] = $one;

	case_world();
	post_check( batch_fields( array( 'paste' => long_list() ) ) );
	$running = WPCPM_Institution_Import::active_for( $HERE );
	WPCPM_Institution_Create::claim( $running, 7, 'member' );
	update_post_meta( $running, WPCPM_Institution_Import::META_STATE, WPCPM_Institution_Import::STATE_CREATING );
	$GLOBALS['flash']['institution_import'] = array( 'status' => 'progress', 'detail' => array( 'batch' => $running, 'created' => 0, 'remaining' => 8 ) );
	$html['progress'] = draw_case( $HERE, $one );
	$calls['progress'] = $one;

	case_world();
	post_check( batch_fields( array( 'paste' => long_list() ) ) );
	$done = WPCPM_Institution_Import::active_for( $HERE );
	$rows = get_post_meta( $done, WPCPM_Institution_Import::META_ROWS, true );
	$made = 0;
	foreach ( $rows as $i => $row ) {
		if ( WPCPM_Institution_Create::ROW_PENDING !== WPCPM_Institution_Create::state_of( $row ) ) {
			continue;
		}
		if ( 0 === $made++ ) {
			$rows[ $i ]['state'] = WPCPM_Institution_Create::ROW_FAILED;
			$rows[ $i ]['error'] = 'INVALID_MULTIPLE_CHOICE_OPTIONS: Insufficient permissions to create new select option "Exampleunbrokenselectoptionwithfortychars"';
			continue;
		}
		$rows[ $i ]['state']     = WPCPM_Institution_Create::ROW_CREATED;
		$rows[ $i ]['record_id'] = sprintf( 'rec%014d', $i );
	}
	update_post_meta( $done, WPCPM_Institution_Import::META_ROWS, $rows );
	update_post_meta( $done, WPCPM_Institution_Import::META_STATE, WPCPM_Institution_Import::STATE_DONE );
	$GLOBALS['flash']['institution_import'] = array( 'status' => 'created', 'detail' => array( 'batch' => $done, 'created' => 7, 'blocked' => 5, 'failed' => 1, 'remaining' => 0 ) );
	$html['outcome, with exceptions'] = draw_case( $HERE, $one );
	$calls['outcome, with exceptions'] = $one;

	// Fix round 1, Minor 1: hostile words in a row, as the base or a list may hold them: a tag, a double quote, an ampersand,
	// put on the rows after the check, so the cleaning does not take them out first.
	$hostile = array(
		'name'  => 'Eve <script>alert(1)</script> "Q" & Co',
		'email' => 'eve"><b>x</b>&@example.org',
		'error' => 'Refused <i>"x"</i> & <script>y</script>',
	);
	case_world();
	post_check( batch_fields( array( 'paste' => "Name,Email,<i>Col</i>\"&\nBen Example,ben@example.org,1\nCara Example,cara@example.org,2\n" ) ) );
	$bad  = WPCPM_Institution_Import::active_for( $HERE );
	$rows = get_post_meta( $bad, WPCPM_Institution_Import::META_ROWS, true );
	$rows[0]['name']  = $hostile['name'];
	$rows[0]['email'] = $hostile['email'];
	update_post_meta( $bad, WPCPM_Institution_Import::META_ROWS, $rows );
	$html['check, hostile words'] = draw_case( $HERE, $one );
	$calls['check, hostile words'] = $one;
	$rows[1]['state'] = WPCPM_Institution_Create::ROW_FAILED;
	$rows[1]['error'] = $hostile['error'];
	$rows[1]['name']  = $hostile['name'];
	$rows[1]['email'] = $hostile['email'];
	$rows[0]['verdict'] = WPCPM_Institution_Import::INVALID;
	update_post_meta( $bad, WPCPM_Institution_Import::META_ROWS, $rows );
	update_post_meta( $bad, WPCPM_Institution_Import::META_STATE, WPCPM_Institution_Import::STATE_DONE );
	$GLOBALS['flash']['institution_import'] = array( 'status' => 'created', 'detail' => array( 'batch' => $bad, 'created' => 0, 'blocked' => 1, 'failed' => 1, 'remaining' => 0 ) );
	$html['outcome, hostile words'] = draw_case( $HERE, $one );
	$calls['outcome, hostile words'] = $one;

	// Each sentence the card can say, over the form it says it on.
	foreach ( array_keys( WPCPM_Institution_Import_Form::messages() ) as $key ) {
		case_world();
		$GLOBALS['flash']['institution_import'] = array( 'status' => $key, 'detail' => array( 'created' => 2, 'blocked' => 1, 'failed' => 0, 'remaining' => 3 ) );
		$html[ 'message: ' . $key ] = draw_case( $HERE, $one );
		$calls[ 'message: ' . $key ] = $one;
	}

	case_world();
	$GLOBALS['allowed'] = false;
	$html['a tutor'] = draw_case( $HERE, $one );
	$calls['a tutor'] = $one;

	case_world();
	return array( 'html' => $html, 'calls' => $calls );
}

/** A case's digest, every date masked: the suite's lists start from the clock. */
function case_digest( $html ) {
	return md5( (string) preg_replace( '/\d{4}-\d{2}-\d{2}/', '{date}', (string) $html ) );
}

/** The digests of the cases that are not messages, by case, and one digest of every message's card in order. */
function digests_of( array $html ) {
	$out  = array();
	$said = '';
	foreach ( $html as $name => $page ) {
		if ( 0 === strpos( $name, 'message: ' ) ) {
			$said .= $page;
			continue;
		}
		$out[ $name ] = case_digest( $page );
	}
	$out['every message'] = case_digest( $said );
	return $out;
}

/**
 * What each drawn form posts, as a browser posts it: its method, address and encoding, then every control that sends a
 * value (a box, a ticked checkbox, a select by its selected option or its first, a text area by its words, a named
 * button), sorted; the lock at its press and its words, and the question it asks first.
 *
 * @param string $html Drawn markup.
 * @return array[]
 */
function posted_forms( $html ) {
	$attr  = static function ( $tag, $name ) {
		return preg_match( '/\s' . $name . '="([^"]*)"/', $tag, $m ) ? $m[1] : null;
	};
	$forms = array();
	preg_match_all( '#<form\b([^>]*)>(.*?)</form>#s', (string) $html, $found, PREG_SET_ORDER );
	foreach ( $found as $form ) {
		$fields = array();
		preg_match_all( '#<(input|button)\b([^>]*)>#', $form[2], $controls, PREG_SET_ORDER );
		foreach ( $controls as $control ) {
			if ( null === $attr( $control[2], 'name' ) || 1 === preg_match( '/\sdisabled\b/', $control[2] ) ) {
				continue;
			}
			if ( 'checkbox' === $attr( $control[2], 'type' ) && 1 !== preg_match( '/\schecked\b/', $control[2] ) ) {
				continue;
			}
			$fields[] = $attr( $control[2], 'name' ) . '=' . (string) $attr( $control[2], 'value' );
		}
		preg_match_all( '#<select\b([^>]*)>(.*?)</select>#s', $form[2], $selects, PREG_SET_ORDER );
		foreach ( $selects as $select ) {
			preg_match_all( '#<option\b([^>]*)>#', $select[2], $options, PREG_SET_ORDER );
			$chosen = isset( $options[0] ) ? (string) $attr( $options[0][1], 'value' ) : '';
			foreach ( $options as $option ) {
				if ( 1 === preg_match( '/\sselected\b/', $option[1] ) ) {
					$chosen = (string) $attr( $option[1], 'value' );
					break;
				}
			}
			$fields[] = $attr( $select[1], 'name' ) . '=' . $chosen;
		}
		preg_match_all( '#<textarea\b([^>]*)>(.*?)</textarea>#s', $form[2], $areas, PREG_SET_ORDER );
		foreach ( $areas as $area ) {
			// A browser drops the one line break that opens a text area's words.
			$fields[] = $attr( $area[1], 'name' ) . '=' . (string) preg_replace( '/^\r?\n/', '', $area[2] );
		}
		sort( $fields );
		$forms[] = array(
			'method'  => (string) $attr( $form[1], 'method' ),
			'action'  => (string) $attr( $form[1], 'action' ),
			'enctype' => (string) $attr( $form[1], 'enctype' ),
			'once'    => 1 === preg_match( '/\sdata-wpcpm-once\b/', $form[1] ),
			'busy'    => (string) $attr( $form[1], 'data-wpcpm-busy' ),
			'confirm' => (string) $attr( $form[1], 'data-wpcpm-confirm' ),
			'fields'  => $fields,
		);
	}
	return $forms;
}

/** Each refused control: whether it says so and names its sentence, the sentence, and whether the summary links to it. */
function refused_controls( $html ) {
	$out = array();
	preg_match_all( '#<p class="wpcpm-field__problem" id="([^"]+)-problem">([^<]*)</p>#', (string) $html, $found, PREG_SET_ORDER );
	foreach ( $found as $problem ) {
		$out[ $problem[1] ] = array(
			1 === preg_match( '#<(?:input|select|textarea)\b[^>]*\sid="' . preg_quote( $problem[1], '#' ) . '"[^>]*\saria-invalid="true"[^>]*\saria-describedby="' . preg_quote( $problem[1], '#' ) . '-problem[ "]#', (string) $html ),
			$problem[2],
			false !== strpos( (string) $html, 'href="#' . $problem[1] . '"' ),
		);
	}
	ksort( $out );
	return $out;
}

/** The rows a check shows on today's branch: line, name, address and verdict, read from its list. */
function old_rows( $html ) {
	preg_match_all( '#<li class="wpcpm-import__row[^"]*"><span class="wpcpm-import__line">Line (\d+)</span> <span class="wpcpm-import__name">([^<]*)</span> <span class="wpcpm-import__email">([^<]*)</span> <span class="wpcpm-import__verdict">([^<]*)</span></li>#', (string) $html, $found, PREG_SET_ORDER );
	return array_map(
		static function ( $m ) {
			return array( $m[1], $m[2], $m[3], $m[4] );
		},
		$found
	);
}

/** The rows a check shows on the parts: each table row's four cells, their words with the break points taken out. */
function new_rows( $html ) {
	$out = array();
	preg_match_all( '#<tr role="row">(.*?)</tr>#s', (string) $html, $rows );
	foreach ( $rows[1] as $row ) {
		if ( false !== strpos( $row, '<th ' ) ) {
			continue;
		}
		preg_match_all( '#<td\b[^>]*>(.*?)</td>#s', $row, $cells );
		$out[] = array_map(
			static function ( $cell ) {
				return str_replace( '<wbr>', '', $cell );
			},
			$cells[1]
		);
	}
	return $out;
}

/** The decisions asked of the policy and the nonces asked for, in order. */
function asked( array $calls ) {
	return array_values(
		array_map(
			static function ( $call ) {
				return implode( ' ', array_map( 'strval', array_filter( $call, 'is_scalar' ) ) );
			},
			array_filter(
				$calls[0],
				static function ( $call ) {
					return in_array( $call[0], array( 'decide', 'subject', 'nonce_field' ), true );
				}
			)
		)
	);
}

function has( $html, $needle ) {
	return false !== strpos( (string) $html, (string) $needle );
}

/**
 * A digest of what a drawing posts, asks or says, the run's dates and the confirmation's content hash masked: the
 * suite's lists start thirty days from the clock, and the confirmation's nonce is keyed to what the question says.
 */
function t23_pin( $value ) {
	return md5( (string) preg_replace( array( '/\d{4}-\d{2}-\d{2}/', '/_[0-9a-f]{32}/' ), array( '{date}', '_{hash}' ), serialize( $value ) ) );
}

// What the old card posted, asked, refused, listed and said, read from the class at db850661 (the last commit that drew
// it) before it was deleted (Task 23), each as `t23_pin()` digests it. The parts are held to them.
$t23_old = array(
	'posts' => array(
		'form' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'form, an Administrator' => 'c327c38ce7f1b48034a9d4b364fb502a',
		'form, tutors listed' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'refused, typing kept' => '53daa316f3397f3c2b5718c810327df8',
		'refused at the tick' => 'a6597a67570cf19e5d36a798b26d85e1',
		'refused at the paste' => '4cc58f44533c5077c010156ffa5d0af5',
		'refused at the end' => '53daa316f3397f3c2b5718c810327df8',
		'check, thirteen rows' => '375a0baa6968134549d05c14478e5574',
		'check, ignored columns, an Administrator' => '7a7e96e2a0e5e6d78e17219d0f58165e',
		'check, nothing to create' => '3d6ad23bd96772ef2572e4f670a7f726',
		'progress' => '0991dae17fa6e403db2e7551367b6689',
		'outcome, with exceptions' => '40cd750bba9870f18aada2478b24840a',
		'check, hostile words' => 'b2f0bea2a96a37654e06cf0fe7a51714',
		'outcome, hostile words' => '40cd750bba9870f18aada2478b24840a',
		'message: checked' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: created' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: progress' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: not-staged-now' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: not-creating-now' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: locked' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: wrong-state' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: agreement' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: not-allowed' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: no-actor' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: no-institution' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: cancelled' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: not-cancelled' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: off' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: refused' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: no-batch' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: already-staged' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: too_often' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: rows_today' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: bad-program' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: program-ungated' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: bad-start' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: start-far' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: bad-end' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: not-notified' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: nothing-sent' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: unreadable' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: not-staged' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: paste-too-long' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: parse-empty' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: parse-too_large' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: file-unreadable' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: parse-not_utf8' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: parse-no_header' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: parse-no_columns' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: parse-no_rows' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: parse-too_many_rows' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: parse-batch_mismatch' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'message: unknown' => '54b75d9e44c06bd0c885e4e6c0522dc4',
		'a tutor' => '40cd750bba9870f18aada2478b24840a',
	),
	'calls' => array(
		'form' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'form, an Administrator' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'form, tutors listed' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'refused, typing kept' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'refused at the tick' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'refused at the paste' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'refused at the end' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'check, thirteen rows' => '7d3d27c090b428a60d3d14699f98645c',
		'check, ignored columns, an Administrator' => '7d3d27c090b428a60d3d14699f98645c',
		'check, nothing to create' => '9a31b5d0739a0fc762460fd01d08b5f7',
		'progress' => 'd152a84e61e8ef3250c0839d97738758',
		'outcome, with exceptions' => 'f0bd55fb6faf07113dcca93ed55eab58',
		'check, hostile words' => '7d3d27c090b428a60d3d14699f98645c',
		'outcome, hostile words' => 'f0bd55fb6faf07113dcca93ed55eab58',
		'message: checked' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: created' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: progress' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: not-staged-now' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: not-creating-now' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: locked' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: wrong-state' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: agreement' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: not-allowed' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: no-actor' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: no-institution' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: cancelled' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: not-cancelled' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: off' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: refused' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: no-batch' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: already-staged' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: too_often' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: rows_today' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: bad-program' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: program-ungated' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: bad-start' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: start-far' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: bad-end' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: not-notified' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: nothing-sent' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: unreadable' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: not-staged' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: paste-too-long' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: parse-empty' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: parse-too_large' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: file-unreadable' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: parse-not_utf8' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: parse-no_header' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: parse-no_columns' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: parse-no_rows' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: parse-too_many_rows' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: parse-batch_mismatch' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'message: unknown' => '24d64e0735c4d52fcfd93c9679d34ad0',
		'a tutor' => 'f0bd55fb6faf07113dcca93ed55eab58',
	),
	'refused' => array(
		'refused, typing kept' => 'b374ea85d46c71c1da6ea83009a11c39',
		'refused at the tick' => '603158906a59a872aded8e42d4a2a16f',
		'refused at the paste' => '2c5b0eb2837133bfa7baf5fe031ed7a6',
		'refused at the end' => '6647455b21fde7185509b9e597e0503a',
	),
	'rows' => array(
		'check, thirteen rows' => '6f4130bd4dfcafa67599ba550cd69424',
		'progress' => '5f491185444f10f9f548fe2cd84030f6',
		'outcome, with exceptions' => 'd4c6bdf49248e2201c989c01e2fc3bd1',
		'check, hostile words' => 'e3fd8358dc09d9aedf7b794630bb0696',
		'outcome, hostile words' => 'fc8bc717d561e99b058b8836f736f74b',
	),
	'messages' => array(
		'checked' => 'c06c28711e09f96a248fca194618dbec',
		'created' => '279b62c28eebd3f57a7b736dbd898b78',
		'progress' => 'f59ce6f3cc0e87b99f7aa4297795e3f7',
		'not-staged-now' => '2f013c762fd2db7c01674d306691e787',
		'not-creating-now' => 'b0ef8d18e7ba0b98823c7fbc5d8ee5a0',
		'locked' => 'b5a987bd31d4f0ef49686bdfe2b6672f',
		'wrong-state' => '1073c1f09bbd9cd8a7bb41b282fc297c',
		'agreement' => '4e6ffb0f5cfb27ee5d08dc3af359fd38',
		'not-allowed' => '7d5e996fab0143a8a451cde71fa44b55',
		'no-actor' => '4bc81a3c9554b410912b21fcd021983c',
		'no-institution' => 'f13ea3ed7d73831baaf2846c64856cfb',
		'cancelled' => 'dc5a463056a51cf7583260f365bb34ad',
		'not-cancelled' => '88001a9c74a0c899a425bd1b47263415',
		'off' => '3a299004f741bcdb06dccd3c7b38a984',
		'refused' => 'dd8a2b1e63fab23ff438cb4bb3d78172',
		'no-batch' => '12d3182839f498e3784d5132c2df0bb1',
		'already-staged' => '65118f7396c604ce2318e6ea4b9a9dfa',
		'too_often' => '894bfdea2b9a302e209211efb6e6f111',
		'rows_today' => 'e51e6ddfe62418d6cde9b38154ef5723',
		'bad-program' => 'c4dd7cf7758018c4132cdf2622de96f3',
		'program-ungated' => 'ed6c2995e53d8c652f9a207196d4f40c',
		'bad-start' => '106aedab4aaf236e03a8946113776877',
		'start-far' => 'a6d1707d3bffd974e89a7854a559d1bf',
		'bad-end' => '29c5c9a68492a0aaef3e335ae2d6b9ac',
		'not-notified' => '889920302c95f7586fc913693f6d3094',
		'nothing-sent' => '38f76229a6b3f1a2d9f1a900eb57461e',
		'unreadable' => '50d7a70055f849c4f6061348124745c6',
		'not-staged' => '51fba291bf12e9186db97e1e09f9c85f',
		'paste-too-long' => 'e3ca6bb640e55ea58f3d9136033cf1b2',
		'parse-empty' => 'f82014bc7c1046090491890606f122ca',
		'parse-too_large' => 'bbe40a34f5baf533fe08b0b9636d7b63',
		'file-unreadable' => '3774cd4292bccbb413ca8e27e537d1e2',
		'parse-not_utf8' => '2609e14f3bf11c18ecb5ebd51560726a',
		'parse-no_header' => '921c7d39eef0253220f495633373c3b0',
		'parse-no_columns' => 'c1e8daa9f0ff1e286a2a2bec80ae5cff',
		'parse-no_rows' => '3d55375ae63bcc3ad4dc913c60d0ea12',
		'parse-too_many_rows' => 'c96c836f0ba97f4a0ae949cbe26ed8bb',
		'parse-batch_mismatch' => '746dc852d5de3882602fd599a8940314',
		'unknown' => 'f8c6becd066e3ae306736744fe528d88',
	),
);
WPCPM_UI::set_live( array( 'administrator', 'institution' ) );
WPCPM_UI::enter( 'institution' );
$new = import_cases();
WPCPM_UI::leave();
$nh  = $new['html'];

foreach ( $nh as $name => $page ) {
	if ( 'a tutor' === $name ) {
		continue;
	}
	if ( function_exists( 'wpcpm_layout_keep' ) && in_array( $name, array( 'form', 'refused, typing kept', 'refused at the tick', 'check, thirteen rows', 'check, ignored columns, an Administrator', 'check, hostile words', 'progress', 'outcome, with exceptions' ), true ) ) {
		wpcpm_layout_keep( 'Enroll students: ' . $name, $page );
	}
}

// The whole view on the real card (Task 23, fix round 1): every case the card draws on the parts, each reader's and
// each state's, under the Enroll students view's own head (`bin/stubs/view-faults.php`).
ck( 'the whole Enroll students view on the real card, every case: one H1, the headings in order, no id twice, the lede once, no title again', wpcpm_view_faults_of( 'Enroll students', $nh ), array() );

$nf = $nh['form'];
ck( 'on the parts: one block that starts at the view\'s edge, its anchor kept, no fold, no head saying the view\'s title again, no class of the old card', array(
	0 === strpos( $nf, '<section class="wpcpm-ui-block" id="wpcpm-import">' ),
	substr( $nf, -strlen( '</section>' ) ),
	has( $nf, '<details' ),
	has( $nf, '<summary' ),
	has( $nf, 'Enroll students' ),
	has( $nf, 'wpcpm-institution__card' ),
	has( $nf, 'class="wpcpm-import' ),
	has( $nf, 'wpcpm-button' ),
	has( $nf, 'wpcpm-field"' ),
), array( true, '</section>', false, false, false, false, false, false, false ) );
ck( 'the lede\'s fact alone as the intro (the view\'s head says the rest), then the one form, posting the file', array(
	has( $nf, '<section class="wpcpm-ui-block" id="wpcpm-import"><p class="wpcpm-ui-intro">Nothing is created until you have seen what the list was understood to say.</p><form method="post" enctype="multipart/form-data" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Checking the list">' ),
	substr_count( $nf, '<form' ),
), array( true, 1 ) );
ck( 'Program a select of the parts, at most 320px, today\'s id, name and programs', array(
	1 === preg_match( '#<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="wpcpm-import-program">Program</label><select class="wpcpm-ui-field__control wpcpm-ui-field__control--select" id="wpcpm-import-program" name="program">(<option value="[^"]*">[^<]*</option>)+</select></div>#', $nf ),
	preg_match_all( '#<option value="([^"]*)"#', (string) ( preg_match( '#<select[^>]*id="wpcpm-import-program".*?</select>#s', $nf, $m ) ? $m[0] : '' ) ),
	count( WPCPM_Institutions::offered_programs() ),
), array( true, count( WPCPM_Institutions::offered_programs() ), count( WPCPM_Institutions::offered_programs() ) ) );
ck( 'Start date and End date 200px date boxes: Start Required with today\'s hint, End Optional with "If you know it." and today\'s hint', array(
	has( $nf, '<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="wpcpm-import-start">Start date <span class="wpcpm-ui-field__need">Required</span></label><input type="date" class="wpcpm-ui-field__control wpcpm-ui-field__control--date" id="wpcpm-import-start" name="start" required aria-describedby="wpcpm-import-start-hint" value=""><p class="wpcpm-ui-field__hint" id="wpcpm-import-start-hint">The term these students begin. A date more than a year either side of today is refused as a typo.</p></div>' ),
	has( $nf, '<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="wpcpm-import-end">End date <span class="wpcpm-ui-field__need">Optional</span></label><input type="date" class="wpcpm-ui-field__control wpcpm-ui-field__control--date" id="wpcpm-import-end" name="end" aria-describedby="wpcpm-import-end-hint" value=""><p class="wpcpm-ui-field__hint" id="wpcpm-import-end-hint">If you know it. After the start date, and at most a year after it.</p></div>' ),
), array( true, true ) );
ck( 'the notified tick is its own question, Required, today\'s words, id, name and value', has( $nf, '<div class="wpcpm-ui-choices wpcpm-ui-choices--alone"><label class="wpcpm-ui-choice" for="wpcpm-import-notified"><input type="checkbox" class="wpcpm-ui-choice__input" id="wpcpm-import-notified" name="notified" value="1" required><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="wpcpm-import-notified-words">These students have been notified that their name and email address are shared with the WordPress Foundation for the WordPress Credits Program. <span class="wpcpm-ui-field__need">Required</span></span></span></label></div>' ), true );
ck( 'One student a field set: Full name, Email address, Field of study (Not recorded first) and Tutor (a picker, saying where tutors are added when none is listed: under Tutors on Your institution, Task 20)', array(
	1 === preg_match( '#<fieldset class="wpcpm-ui-choices wpcpm-ui-choices--set"><legend class="wpcpm-ui-h">One student</legend><div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="wpcpm-import-name">Full name</label><input type="text" class="wpcpm-ui-field__control" id="wpcpm-import-name" name="name" value=""></div><div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="wpcpm-import-email">Email address</label><input type="text" class="wpcpm-ui-field__control" id="wpcpm-import-email" name="email" value=""></div><div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="wpcpm-import-field_of_study">Field of study</label><select class="wpcpm-ui-field__control wpcpm-ui-field__control--select" id="wpcpm-import-field_of_study" name="field_of_study"><option value="" selected>Not recorded</option><option value="Technology &amp; Engineering">Technology &amp; Engineering</option></select></div><div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="wpcpm-import-tutor">Tutor</label><select class="wpcpm-ui-field__control wpcpm-ui-field__control--select" id="wpcpm-import-tutor" name="tutor" aria-describedby="wpcpm-import-tutor-hint"><option value="" selected>Not recorded</option></select><p class="wpcpm-ui-field__hint" id="wpcpm-import-tutor-hint">No tutors are listed for your institution yet. Add them under Tutors on Your institution, and they will appear here.</p></div></fieldset>#', $nf ),
), array( true ) );
ck( 'Or a list a field set: the file box with today\'s label, accept and hint, "Download a sample list" by it, then the paste box at the card\'s width', array(
	has( $nf, '<fieldset class="wpcpm-ui-choices wpcpm-ui-choices--set"><legend class="wpcpm-ui-h">Or a list</legend><div class="wpcpm-ui-field wpcpm-ui-file"><label class="wpcpm-ui-field__label" for="wpcpm-import-file">A CSV file</label><input class="wpcpm-ui-file__input" type="file" id="wpcpm-import-file" name="list" accept=".csv,text/csv" aria-describedby="wpcpm-import-file-hint">' ),
	has( $nf, '<p class="wpcpm-ui-field__hint" id="wpcpm-import-file-hint">A CSV file of 256 KB at most. A header row, then one student per line. Name and email are needed; field of study and tutor are read when they are there. Up to 300 students. The file is read and never stored.</p></div><p class="wpcpm-ui-intro"><a href="https://example.test/wp-admin/admin-post.php?action=wpcpm_import_sample&_wpnonce=nonce-wpcpm_import_sample_' . $HERE . '">Download a sample list</a></p>' ),
	has( $nf, '<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="wpcpm-import-paste">Or paste the same thing here</label><textarea class="wpcpm-ui-field__control wpcpm-ui-field__control--textarea" id="wpcpm-import-paste" name="paste" aria-describedby="wpcpm-import-paste-hint" rows="6">' . "\n" . '</textarea><p class="wpcpm-ui-field__hint" id="wpcpm-import-paste-hint">Useful when the list is in a spreadsheet you would rather not download.</p></div></fieldset>' ),
	$new['calls']['form'][1],
), array( true, true, true, array( $HERE ) ) );
ck( 'Check the list, primary, ends the form', has( $nf, '<div class="wpcpm-ui-actions"><button type="submit" class="wpcpm-btn">Check the list</button></div></form></section>' ), true );
ck( 'every id the old form carried is kept', array_map(
	static function ( $id ) use ( $nf ) {
		return substr_count( $nf, 'id="' . $id . '"' );
	},
	array( 'wpcpm-import', 'wpcpm-import-program', 'wpcpm-import-start', 'wpcpm-import-end', 'wpcpm-import-notified', 'wpcpm-import-file', 'wpcpm-import-paste' )
), array( 1, 1, 1, 1, 1, 1, 1 ) );

// Rule 2 of the dispatch: every form posts what it posted.
$same_posts = array();
foreach ( $nh as $name => $page ) {
	$same_posts[ $name ] = t23_pin( posted_forms( $page ) );
}
ck( 'every state posts what the old card posted, form by form: method, address, encoding, every control\'s name and value, the nonce\'s action, the referer, the lock and its words, the question', $same_posts, $t23_old['posts'] );
ck( 'and the forms are there to compare: the check, the confirmation and the throwing away, Continue', array(
	count( posted_forms( $nh['form'] ) ),
	count( posted_forms( $nh['check, thirteen rows'] ) ),
	count( posted_forms( $nh['check, nothing to create'] ) ),
	count( posted_forms( $nh['progress'] ) ),
	count( posted_forms( $nh['outcome, with exceptions'] ) ),
), array( 1, 2, 1, 1, 0 ) );
$sorted = static function ( array $list ) {
	sort( $list );
	return $list;
};
$check_post = posted_forms( $nh['check, thirteen rows'] );
ck( 'the check posts the action, the nonce keyed to the school, the referer and every answer', posted_forms( $nf )[0]['fields'], $sorted( array(
	'_wp_http_referer=/institution-dashboard/?wpcpm_view=enroll',
	'_wpnonce=nonce-wpcpm_import_check_' . $HERE,
	'action=wpcpm_import_check',
	'email=',
	'end=',
	'field_of_study=',
	'list=',
	'name=',
	'paste=',
	'program=' . array_keys( WPCPM_Institutions::offered_programs() )[0],
	'start=',
	'tutor=',
) ) );
// The confirmation's nonce is keyed to the batch and to what it says (`confirm_action()`), so it is read off the old form.
ck( 'the confirmation and the throwing away post the batch, their nonces and the referer', array( $check_post[0]['fields'], 1 === preg_match( '/^_wpnonce=nonce-wpcpm_import_confirm_901_[0-9a-f]{32}$/', $check_post[0]['fields'][1] ), $check_post[1]['fields'] ), array(
	$sorted( array( '_wp_http_referer=/institution-dashboard/?wpcpm_view=enroll', $check_post[0]['fields'][1], 'action=wpcpm_import_confirm', 'batch=901' ) ),
	true,
	$sorted( array( '_wp_http_referer=/institution-dashboard/?wpcpm_view=enroll', '_wpnonce=nonce-wpcpm_import_cancel_901', 'action=wpcpm_import_cancel', 'batch=901' ) ),
) );
$referers = array();
foreach ( array( 'form', 'check, thirteen rows', 'progress' ) as $name ) {
	$referers[ $name ] = array( substr_count( $nh[ $name ], '<form' ), substr_count( $nh[ $name ], 'name="_wp_http_referer"' ) );
}
ck( 'the referer is in the markup it draws, once a form, as the old card\'s was', $referers, array( 'form' => array( 1, 1 ), 'check, thirteen rows' => array( 2, 2 ), 'progress' => array( 1, 1 ) ) );
ck( 'two forms side by side carry no repeated id', substr_count( $nh['check, thirteen rows'], 'id="_wpnonce"' ), 0 );
ck( 'an Administrator\'s forms post to the school they view', array(
	array_column( posted_forms( $nh['form, an Administrator'] ), 'action' ),
	array_column( posted_forms( $nh['check, ignored columns, an Administrator'] ), 'action' ),
), array(
	array( 'https://example.test/wp-admin/admin-post.php?wpcpm_institution_view=' . $HERE ),
	array( 'https://example.test/wp-admin/admin-post.php?wpcpm_institution_view=' . $HERE, 'https://example.test/wp-admin/admin-post.php?wpcpm_institution_view=' . $HERE ),
) );

// Rule 3: the same questions asked of the policy, the same nonces, the same sample link, in the same order, for every
// reader and state, as the old card asked; a tutor is drawn nothing.
$same_calls = array();
foreach ( $new['calls'] as $name => $calls ) {
	$same_calls[ $name ] = t23_pin( array( asked( $calls ), $calls[1] ) );
}
ck( 'every state asks the policy what the old card asked, in the same order, before it draws', $same_calls, $t23_old['calls'] );
ck( 'a tutor is drawn no Enroll students', $nh['a tutor'], '' );

// A refused check keeps the typing and ties each sentence to its box.
$nr = $nh['refused, typing kept'];
// Gate B round 2, F2: the refusal is said at the form it refused, after the form's own intro and directly over it.
ck( 'a refused check: the refusal as the shared message, alerting, after the form\'s intro and directly over the form, the summary over the fields, each refusal under its box as the old card ties it', array(
	1 === preg_match( '#<section class="wpcpm-ui-block" id="wpcpm-import"><p class="wpcpm-ui-intro">[^<]*</p><p class="wpcpm-import__message wpcpm-import__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>That start date is more than a year away\. Check the year\.</p><form #', $nr ),
	1 === preg_match( '#<form [^>]*>.*<div class="wpcpm-errors" role="alert" tabindex="-1">.*<a href="\#wpcpm-import-start">Start date</a>.*<label class="wpcpm-ui-field__label" for="wpcpm-import-program">#s', $nr ),
	t23_pin( refused_controls( $nr ) ),
	array_keys( refused_controls( $nr ) ),
), array( true, true, $t23_old['refused']['refused, typing kept'], array( 'wpcpm-import-file', 'wpcpm-import-start' ) ) );
ck( 'and keeps what was typed: the program, the dates, the tick, the one student and the pasted list', array(
	has( $nr, 'value="' . full_post()['start'] . '"' ),
	has( $nr, 'value="' . full_post()['end'] . '"' ),
	has( $nr, 'name="notified" value="1" checked required' ),
	has( $nr, 'value="Dora Example"' ),
	has( $nr, 'value="dora@institution-3.example"' ),
	has( $nr, '<option value="Technology &amp; Engineering" selected>' ),
	has( $nr, 'Student 3,student-3@institution-3.example</textarea>' ),
), array( true, true, true, true, true, true, true ) );
ck( 'the tick refused: its sentence under it, tied to it, and the summary links to it', array(
	refused_controls( $nh['refused at the tick'] ),
	t23_pin( refused_controls( $nh['refused at the tick'] ) ) === $t23_old['refused']['refused at the tick'],
	has( $nh['refused at the tick'], '<input type="checkbox" class="wpcpm-ui-choice__input" id="wpcpm-import-notified" name="notified" value="1" required aria-invalid="true" aria-describedby="wpcpm-import-notified-problem">' ),
), array( array( 'wpcpm-import-notified' => array( true, 'Confirm that these students have been notified of what is shared, before sending the list.', true ) ), true, true ) );
ck( 'the paste refused: its sentence under it, tied to it', array( refused_controls( $nh['refused at the paste'] ), t23_pin( refused_controls( $nh['refused at the paste'] ) ) === $t23_old['refused']['refused at the paste'] ), array( array( 'wpcpm-import-paste' => array( true, 'That list needs a name column and an email column.', true ) ), true ) );
ck( 'tutors listed: each name once, as the old picker offers them, and no sentence', array(
	has( $nh['form, tutors listed'], '<option value="Ola Example">Ola Example</option><option value="Tutor Exampleunbrokentutornamewithfortycharss">Tutor Exampleunbrokentutornamewithfortycharss</option></select></div>' ),
	has( $nh['form, tutors listed'], 'No tutors are listed' ),
), array( true, false ) );

// The check: the count sentence, the table, the question, then the two forms in one row.
$nc = $nh['check, thirteen rows'];
ck( 'the check: today\'s count sentence, then a table of four columns (headers, kinds), no wide table', array(
	has( $nc, '<p class="wpcpm-ui-intro">8 of 13 rows can be created.</p><table class="wpcpm-ui-table wpcpm-ui-table--pairs-4" role="table">' ),
	has( $nc, '<thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader" class="wpcpm-ui-cell--num">Line</th><th scope="col" role="columnheader" class="wpcpm-ui-cell--text">Name</th><th scope="col" role="columnheader" class="wpcpm-ui-cell--token">Email address</th><th scope="col" role="columnheader" class="wpcpm-ui-cell--text">What happens</th></tr></thead>' ),
	has( $nc, 'wpcpm-ui-table--wide' ),
), array( true, true, false ) );
$cells = new_rows( $nc );
ck( 'thirteen rows, each with its four cells named for the phone', array(
	count( $cells ),
	substr_count( $nc, '<td class="wpcpm-ui-cell--num" role="cell" data-label="Line">' ),
	substr_count( $nc, ' role="cell" data-label="Name">' ),
	substr_count( $nc, '<td class="wpcpm-ui-cell--token" role="cell" data-label="Email address">' ) + substr_count( $nc, '<td class="wpcpm-ui-cell--token wpcpm-ui-cell--blank" role="cell" data-label="Email address">' ),
	substr_count( $nc, '<td class="wpcpm-ui-cell--text" role="cell" data-label="What happens">' ),
), array( 13, 13, 13, 13, 13 ) );
ck( 'the same rows as the old list, line for line: the line, the name, the address and what happens, in words', t23_pin( $cells ), $t23_old['rows']['check, thirteen rows'] );
ck( 'the 40-character word in a name breaks inside its cell, the 48-character address is a token cell, a missing name is said in words', array(
	has( $nc, '<td class="wpcpm-ui-cell--text" role="cell" data-label="Name">Ama Exampleunb<wbr>rokenstude<wbr>ntnamewith<wbr>fortychars</td><td class="wpcpm-ui-cell--token" role="cell" data-label="Email address">averyveryveryverylongmailboxname1234@example.org</td>' ),
	has( $nc, '<td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell" data-label="Name">(no name)</td>' ),
	count( $cells ),
), array( true, true, 13 ) );
$says = 'Create 8 student records on the ' . WPCPM_Program::label( 'In Sensei' ) . ' program, starting ' . batch_fields()['start'] . '. No email is sent now. The program emails each student when their place is set up, and they receive their login when their account is created. Do not send these students to the registration form; it would create a second record.';
ck( 'then today\'s question as the intro, its date whole, and one row: Create these student records (primary, asking it first), Throw this list away (danger)', array(
	has( $nc, '</table><p class="wpcpm-ui-intro">' . str_replace( batch_fields()['start'], '<span class="wpcpm-ui-whole">' . batch_fields()['start'] . '</span>', esc_html( $says ) ) . '</p><div class="wpcpm-ui-actions"><form method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-confirm="' . esc_attr( $says ) . '" data-wpcpm-once data-wpcpm-busy="Creating the records">' ),
	has( $nc, '<button type="submit" class="wpcpm-btn">Create these student records</button></form><form method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Canceling">' ),
	has( $nc, '<button type="submit" class="wpcpm-btn wpcpm-btn--danger">Throw this list away</button></form></div></section>' ),
	html_entity_decode( (string) $check_post[0]['confirm'], ENT_QUOTES ) === $says,
), array( true, true, true, true ) );
ck( 'a check that can create nobody offers no Create, only the throwing away', array(
	has( $nh['check, nothing to create'], 'Create these student records' ),
	has( $nh['check, nothing to create'], '<p class="wpcpm-ui-intro">0 of 1 rows can be created.</p>' ),
	has( $nh['check, nothing to create'], '</table><div class="wpcpm-ui-actions"><form ' ),
), array( false, true, true ) );
ck( 'the ignored columns are today\'s line, muted, under the count', has( $nh['check, ignored columns, an Administrator'], '<p class="wpcpm-ui-intro">2 of 2 rows can be created.</p><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">Columns this import does not read, and ignored: Phone</p><table ' ), true );

// The progress and the outcome: today's sentences, the rows that did not simply get created, Continue.
$np = $nh['progress'];
ck( 'the progress: the shared message, today\'s two sentences, the rows not created as the table, Continue (secondary)', array(
	has( $np, '<section class="wpcpm-ui-block" id="wpcpm-import"><p class="wpcpm-import__message wpcpm-import__message--info is-info" role="status" tabindex="-1" data-wpcpm-card-message>' ),
	has( $np, '<p class="wpcpm-ui-intro">0 created so far. 8 still to create.</p><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">This import has already created records, so it cannot be thrown away. It carries on by itself, and Continue picks it up now.</p><table ' ),
	t23_pin( new_rows( $np ) ) === $t23_old['rows']['progress'],
	count( new_rows( $np ) ),
	has( $np, '<div class="wpcpm-ui-actions"><button type="submit" class="wpcpm-btn wpcpm-btn--secondary">Continue</button></div></form></section>' ),
), array( true, true, true, 5, true ) );
$no = $nh['outcome, with exceptions'];
ck( 'the outcome: the shared message, polite, today\'s tally, the rows not created, the refusal in the records\' own words', array(
	has( $no, '<p class="wpcpm-import__message wpcpm-import__message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>Done: 7 students created, 5 blocked, 1 failed. Nobody has been emailed.</p><p class="wpcpm-ui-intro">7 created, 5 blocked, 1 failed.</p><table ' ),
	t23_pin( new_rows( $no ) ) === $t23_old['rows']['outcome, with exceptions'],
	count( new_rows( $no ) ),
	has( $no, 'The program records refused this row: INVALID_MULTIPLE_CHOICE_OPTIONS' ),
	has( $no, '<form' ),
), array( true, true, 6, true, false ) );

// Rule 6: every outcome is the shared message in the module's own class, its tone the class's own: a refusal alerts, the
// rest is polite; and it is the paragraph the old card printed.
$messages = array();
foreach ( array_keys( WPCPM_Institution_Import_Form::messages() ) as $key ) {
	$found_new = preg_match( '#<p class="wpcpm-import__message[^>]*>.*?</p>#', $nh[ 'message: ' . $key ], $mn ) ? $mn[0] : '';
	// Gate B round 2, F2: a refusal drawn with the form is said at the form, after its intro and directly over it.
	$at_form          = false !== strpos( $nh[ 'message: ' . $key ], 'role="alert"' ) && false !== strpos( $nh[ 'message: ' . $key ], '<form ' );
	$place            = $at_form
		? 1 === preg_match( '#<section class="wpcpm-ui-block" id="wpcpm-import"><p class="wpcpm-ui-intro">[^<]*</p>' . preg_quote( $found_new, '#' ) . '<form #', $nh[ 'message: ' . $key ] )
		: 0 === strpos( $nh[ 'message: ' . $key ], '<section class="wpcpm-ui-block" id="wpcpm-import">' . $found_new );
	$messages[ $key ] = array( '' !== $found_new && t23_pin( $found_new ) === $t23_old['messages'][ $key ], $place );
}
ck( 'every message is the old card\'s paragraph, first in the block, or, a refusal drawn with the form, directly over the form after its intro', $messages, array_fill_keys( array_keys( WPCPM_Institution_Import_Form::messages() ), array( true, true ) ) );
ck( 'a refusal alerts and a success is polite', array(
	has( $nh['message: bad-start'], 'is-error" role="alert" tabindex="-1" data-wpcpm-card-message>' ),
	has( $nh['message: checked'], 'is-success" role="status" tabindex="-1" data-wpcpm-card-message>' ),
	has( $nh['message: locked'], 'is-info" role="status" tabindex="-1" data-wpcpm-card-message>' ),
), array( true, true, true ) );

// Rule 4: a rule the card needs is written once.
ck( 'the hidden fields of every form, the question before creating, the rows a list shows and the rows not created are written once', array(
	substr_count( $source, 'WPCPM_UI::nonce( (string) $nonce )' ),
	substr_count( $source, 'name="action" value="%s"' ),
	substr_count( $source, 'name="batch" value="%d"' ),
	substr_count( $source, 'Do not send these students to the registration form' ),
	substr_count( $source, "__( '(no name)'" ),
	substr_count( $source, 'ROW_FAILED, WPCPM_Institution_Create::ROW_BLOCKED' ),
	substr_count( $source, 'WPCPM_Institutions::offered_programs()' ),
	substr_count( $source, 'self::options_for(' ),
), array( 1, 1, 1, 1, 1, 1, 3, 1 ) );

echo "\n=== Fix round 1 (the review's Minors 1 to 5) ===\n";

/** Whether markup holds hostile words only escaped: no raw tag of theirs, every tag well formed, nothing escaped twice. */
function only_escaped( $html ) {
	preg_match_all( '#<[^>]*>#', (string) $html, $tags );
	$bad = array();
	foreach ( $tags[0] as $tag ) {
		if ( 1 !== preg_match( '#^</?[a-z0-9]+(?:\s+[a-z0-9-]+(?:="[^"<>]*")?)*\s*/?>$#i', $tag ) || 1 === preg_match( '#^</?(script|b|i)\b#i', $tag ) ) {
			$bad[] = $tag;
		}
	}
	return array(
		'tags'          => $bad,
		'double escape' => 1 === preg_match( '/&amp;(amp|lt|gt|quot|#0?39);/', (string) $html ),
		'escaped'       => has( $html, '&lt;script&gt;' ),
	);
}
$clean = array( 'tags' => array(), 'double escape' => false, 'escaped' => true );
ck( 'hostile words in a row, the base\'s refusal and a column heading: only escaped, in the check (its table and count sentence) and the outcome', array(
	'check'   => only_escaped( $nh['check, hostile words'] ),
	'outcome' => only_escaped( $nh['outcome, hostile words'] ),
), array( 'check' => $clean, 'outcome' => $clean ) );
ck( 'and the parts show the same rows as the old list, the hostile one among them, with the count sentence before the table', array(
	t23_pin( new_rows( $nh['check, hostile words'] ) ) === $t23_old['rows']['check, hostile words'],
	t23_pin( new_rows( $nh['outcome, hostile words'] ) ) === $t23_old['rows']['outcome, hostile words'],
	has( str_replace( '<wbr>', '', $nh['check, hostile words'] ), '<td class="wpcpm-ui-cell--text" role="cell" data-label="Name">Eve &lt;script&gt;alert(1)&lt;/script&gt; &quot;Q&quot; &amp; Co</td><td class="wpcpm-ui-cell--token" role="cell" data-label="Email address">eve&quot;&gt;&lt;b&gt;x&lt;/b&gt;&amp;@example.org</td>' ),
	1 === preg_match( '#<p class="wpcpm-ui-intro">\d+ of 2 rows can be created\.</p>#', $nh['check, hostile words'] ),
	has( $nh['outcome, hostile words'], 'The program records refused this row: Refused &lt;i&gt;&quot;x&quot;&lt;/i&gt; &amp; &lt;script&gt;y&lt;/script&gt;' ),
), array( true, true, true, true, true ) );

// Minor 2: the summary names the end date by its label on each branch, from one entry of one map, and links to its box.
ck( 'a refused end date: the summary says "End date", as its label does, and still links to its box', array(
	has( $nh['refused at the end'], '<li><a href="#wpcpm-import-end">End date</a>: ' ),
	t23_pin( refused_controls( $nh['refused at the end'] ) ) === $t23_old['refused']['refused at the end'],
	array_keys( refused_controls( $nh['refused at the end'] ) ),
	substr_count( $source, 'self::FIELD_END      =>' ),
), array( true, true, array( 'wpcpm-import-end' ), 1 ) );

// Minor 3: the tutor sentence and the rule for when it is said are written once. Task 20: the sentence names where
// tutors are added (no card on the page is called People); the old card's wording went with it (Task 23).
ck( 'the tutor sentence and its rule are written once: one method, one wording', array(
	substr_count( $source, 'No tutors are listed for your institution yet.' ),
	substr_count( $source, "'tutor' === \$name && empty( \$options )" ),
	substr_count( $source, 'self::tutor_hint(' ) > 0 && 1 === preg_match( '/function tutor_hint\([^)]*\) \{.*?No tutors are listed for your institution yet\. Add them under Tutors on Your institution, and they will appear here\..*?\n\t\}/s', $source ),
	substr_count( $source, 'the Tutors section of the People card' ),
), array( 1, 1, true, 0 ) );

// Minor 4, as ruled in fix round 2: on the parts the form's first line is the lede's fact alone, the one the view's head
// lacks; its first half repeats the head and is not printed.
ck( 'the lede on the parts is its fact alone, once, first in the block', array(
	substr_count( $nh['form'], 'Nothing is created until you have seen what the list was understood to say.' ),
	has( $nh['form'], '<section class="wpcpm-ui-block" id="wpcpm-import"><p class="wpcpm-ui-intro">Nothing is created until you have seen what the list was understood to say.</p><form ' ),
	has( $nh['form'], 'Add one student, or a list of them.' ),
), array( 1, true, false ) );


printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
