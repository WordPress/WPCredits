<?php
/**
 * The Institutions manager screen, the private-files probe and notify_managers().
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The screen reads the pipeline index, the roster counts, the countries map and the
 *   per-institution agreement summaries, never Airtable. Rendered here against the seed
 *   fixture with every other piece stubbed to its contract, so the group counts, the
 *   agreement-gap count and the consent sentence are the fixture's numbers and nothing else.
 * - Names print trimmed and the row says when the stored one was not; ten names in the base
 *   end in a space and a manager searching the grid should know why a match is not exact.
 * - The consent card never says "lost". The brief read a date boundary as consent dropping
 *   between form and record; the sentence here is the one the design spec fixes.
 * - Every handler checks the capability before the nonce, asserted by reading the source,
 *   because the order is invisible at runtime and wrong in one place is wrong for everyone.
 * - The probe records what the host does, and the storage card says it in the right words:
 *   a 403 is "blocked", a 200 is the warning naming the directory, a failed request is neither.
 * - notify_managers() reaches every manager when the setting is empty and only the listed
 *   addresses when it is set, through send() for accounts and send_to() for bare addresses.
 *
 * Run from the plugin root:  php bin/test-institutions-screen.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MONTH_IN_SECONDS', 2592000 );

$GLOBALS['opts']    = array();
$GLOBALS['umeta']   = array();
$GLOBALS['users']   = array();
$GLOBALS['manage']  = array();
$GLOBALS['caps']    = true;
$GLOBALS['uid']     = 1;
$GLOBALS['mail']    = array();
$GLOBALS['head']    = array( 'response' => array( 'code' => 403 ) );
$GLOBALS['referer'] = array();
$GLOBALS['calls']   = array();
$GLOBALS['uploads'] = sys_get_temp_dir() . '/wpcpm-screen-test-' . getmypid();

// The live membership half of the backstop counts: who acts for each institution, and every
// institution the screen asked about.
$GLOBALS['members_of']   = array();
$GLOBALS['member_reads'] = array();

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = strtolower( str_replace( ' ', '', $name ) ); $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post { public $ID = 0, $post_content = '', $post_type = '', $post_status = 'publish'; }
class WP_Role {}

/**
 * The one query the screen makes itself: tracked student accounts with no institution stamp.
 *
 * Answers over the fixture users the way the real query would: the role, then every
 * `meta_query` clause under an AND relation, `NOT EXISTS` and `EXISTS` on presence and
 * anything else on the value as a string. Only what the screen asks for is implemented; a
 * clause shape it does not use would pass here and must not be written without extending
 * this stub, which is why the args of every call are recorded for the assertions below.
 */
class WP_User_Query {
	private $results = array();
	public function __construct( $args = array() ) {
		$GLOBALS['calls'][] = array( 'WP_User_Query', $args );
		$role    = isset( $args['role'] ) ? $args['role'] : '';
		$clauses = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array();
		foreach ( $GLOBALS['users'] as $id => $user ) {
			if ( '' !== $role && ! in_array( $role, $user->roles, true ) ) { continue; }
			$keep = true;
			foreach ( $clauses as $name => $clause ) {
				if ( 'relation' === $name || ! is_array( $clause ) ) { continue; }
				$present = isset( $GLOBALS['umeta'][ $id ][ $clause['key'] ] );
				$compare = isset( $clause['compare'] ) ? $clause['compare'] : '=';
				if ( 'NOT EXISTS' === $compare ) { $keep = $keep && ! $present; continue; }
				if ( 'EXISTS' === $compare ) { $keep = $keep && $present; continue; }
				$keep = $keep && $present && (string) $GLOBALS['umeta'][ $id ][ $clause['key'] ] === (string) $clause['value'];
			}
			if ( $keep ) { $this->results[] = $id; }
		}
	}
	public function get_results() { return $this->results; }
	public function get_total() { return count( $this->results ); }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $e ) { return (string) $e; }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_action', $h ); }
function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['calls'][] = array( 'update_option', $k, $a ); return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); $GLOBALS['calls'][] = array( 'delete_option', $k ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function delete_metadata( $type, $id, $key, $value = '', $all = false ) { $GLOBALS['calls'][] = array( 'delete_metadata', $key ); return true; }
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) { return $user; }
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
	}
	return false;
}
function get_users( $args = array() ) {
	if ( isset( $args['capability'] ) ) {
		$out = array();
		foreach ( $GLOBALS['users'] as $id => $user ) {
			if ( in_array( $id, $GLOBALS['manage'], true ) ) { $out[] = $user; }
		}
		return $out;
	}
	return array_values( $GLOBALS['users'] );
}
function user_can( $u, $c ) { $id = is_object( $u ) ? $u->ID : (int) $u; return in_array( $id, $GLOBALS['manage'], true ); }
function current_user_can( $c ) { return (bool) $GLOBALS['caps']; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['referer'][] = $a; return true; }
function check_ajax_referer( $a = -1, $q = false ) { $GLOBALS['referer'][] = $a; return true; }
function wp_send_json_error( $d = null, $code = null ) { throw new Exception( 'json_error:' . (int) $code ); }
function wp_send_json_success( $d = null ) { $GLOBALS['json'] = $d; throw new Exception( 'json_success' ); }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect: ' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die: ' . $m ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $k, $v = '', $u = '' ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function wp_nonce_field( $a = '', $n = '', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function wp_create_nonce( $a = '' ) { return 'nonce'; }
function submit_button( $text, $type = 'primary', $name = 'submit', $wrap = true ) { printf( '<button type="submit" class="button button-%s" name="%s">%s</button>', $type, $name, esc_html( $text ) ); }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function human_time_diff( $a, $b = 0 ) { return '4 hours'; }
function wp_date( $format, $ts = null, $zone = null ) { return gmdate( $format, (int) $ts ); }
function wp_timezone_string() { return 'UTC'; }
function get_role( $r ) { return new WP_Role(); }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( (string) $u ) : parse_url( (string) $u, $c ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function wp_upload_dir( $time = null, $create = true ) { return array( 'basedir' => $GLOBALS['uploads'], 'baseurl' => 'https://example.test/wp-content/uploads' ); }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
function wp_is_writable( $p ) { return is_writable( $p ); }
function wp_generate_password( $l = 12, $s = true, $e = false ) { return substr( str_repeat( md5( (string) mt_rand() ), 2 ), 0, (int) $l ); }
function wp_delete_file( $p ) { if ( file_exists( $p ) ) { unlink( $p ); } }
function wp_remote_head( $url, $args = array() ) {
	$GLOBALS['calls'][] = array( 'wp_remote_head', $url, $args );
	// The host as it was measured on 2 September 2026: any path with a dot-prefixed segment is
	// refused, and everything else under uploads is served. `$GLOBALS['head']` is what a
	// scenario wants the served case to answer.
	if ( is_wp_error( $GLOBALS['head'] ) ) {
		return $GLOBALS['head'];
	}
	if ( false !== strpos( (string) wp_parse_url( $url, PHP_URL_PATH ), '/.' ) ) {
		return array( 'response' => array( 'code' => 403 ) );
	}
	return $GLOBALS['head'];
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) && isset( $r['response']['code'] ) ? (int) $r['response']['code'] : ''; }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-agreement-template.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-private-files.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

if ( ! class_exists( 'WPCPM_Institutions_Index' ) ) {
	class WPCPM_Institutions_Index {
		const OPTION  = 'wpcpm_institutions_index';
		const VERSION = 1;
		public static function read() {
			$o = get_option( self::OPTION );
			return ( is_array( $o ) && isset( $o['v'] ) && self::VERSION === $o['v'] ) ? $o : array( 'v' => 1, 'read' => 0, 'rows' => array() );
		}
		public static function rows() { $r = self::read(); return $r['rows']; }
		public static function row( $id ) { $rows = self::rows(); return isset( $rows[ $id ] ) ? $rows[ $id ] : null; }
		public static function has( $id ) { return null !== self::row( $id ); }
		public static function write( array $rows, $read ) { update_option( self::OPTION, array( 'v' => 1, 'read' => (int) $read, 'rows' => $rows ), false ); }
		public static function insert( array $row ) { $r = self::read(); $r['rows'][ $row['record_id'] ] = $row; update_option( self::OPTION, $r, false ); }
		public static function stage_counts() {
			$counts = array();
			foreach ( self::rows() as $row ) { $counts[ $row['stage'] ] = ( $counts[ $row['stage'] ] ?? 0 ) + 1; }
			return $counts;
		}
		public static function by_stage() {
			$order  = array_merge( WPCPM_Institution_Agreement::STAGE_ORDER, WPCPM_Institution_Agreement::TERMINAL_STAGES, array( '' ) );
			$groups = array_fill_keys( $order, array() );
			foreach ( self::rows() as $id => $row ) {
				$stage = in_array( $row['stage'], $order, true ) ? $row['stage'] : '';
				$groups[ $stage ][ $id ] = $row;
			}
			return $groups;
		}
	}
}

if ( ! class_exists( 'WPCPM_Countries' ) ) {
	class WPCPM_Countries {
		const OPTION  = 'wpcpm_countries';
		const VERSION = 1;
		public static function read() {
			$o = get_option( self::OPTION );
			return ( is_array( $o ) && isset( $o['v'] ) && self::VERSION === $o['v'] ) ? $o : array( 'v' => 1, 'read' => 0, 'rows' => array() );
		}
		public static function all() { $r = self::read(); return $r['rows']; }
		public static function name_of( $id ) { $all = self::all(); return isset( $all[ $id ] ) ? $all[ $id ]['name'] : ''; }
		public static function routing( $id ) {
			$all = self::all();
			if ( ! isset( $all[ $id ] ) || ( '' === $all[ $id ]['manager'] && '' === $all[ $id ]['email'] ) ) { return null; }
			return $all[ $id ];
		}
		public static function gaps() { return array_filter( self::all(), function ( $r ) { return '' === $r['manager'] && '' === $r['email']; } ); }
		public static function refresh( $airtable = null ) { $GLOBALS['calls'][] = array( 'WPCPM_Countries::refresh' ); return true; }
	}
}

if ( ! class_exists( 'WPCPM_Roster_Index' ) ) {
	class WPCPM_Roster_Index {
		const OPTION_PREFIX   = 'wpcpm_roster_';
		const OPTION_UNLINKED = 'wpcpm_roster_unlinked';
		const OPTION_COUNTS   = 'wpcpm_roster_counts';
		const VERSION         = 1;
		public static function option_name( $id ) { return self::OPTION_PREFIX . $id; }
		public static function read( $id ) { $o = get_option( self::option_name( $id ) ); return is_array( $o ) ? $o : array( 'v' => 1, 'read' => 0, 'rows' => array() ); }
		public static function rows( $id ) { $r = self::read( $id ); return $r['rows']; }
		public static function unlinked() { $o = get_option( self::OPTION_UNLINKED ); return is_array( $o ) && isset( $o['rows'] ) ? $o['rows'] : array(); }
		public static function counts() {
			$o = get_option( self::OPTION_COUNTS );
			return is_array( $o ) ? $o : array( 'v' => 1, 'read' => 0, 'institutions' => array(), 'reconciliation' => array() );
		}
		public static function write_all( array $b, array $u, array $c, array $r, $read ) {}
		public static function insert( $id, array $row ) {}
		public static function delete_all() { $GLOBALS['calls'][] = array( 'WPCPM_Roster_Index::delete_all' ); }
	}
}

if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	/** Stands in for the members module: the screen names its meta keys through it. */
	class WPCPM_Institution_Members {
		const META_RECORD_ID     = 'wpcpm_institution_record_id';
		const META_ACTIVE        = 'wpcpm_institution_active';
		const META_RECORD_ID_WAS = 'wpcpm_institution_record_id_was';
		const META_MEMBERSHIP    = 'wpcpm_institution_membership';
		const META_INVITED       = 'wpcpm_institution_invited';
		const META_PROFILE       = 'wpcpm_institution_profile';
		public static function members_of( $record_id ) {
			// Recorded, so the backstop counts can be shown to ask each institution once:
			// the real call is a user query apiece, on a screen that draws 106 of them.
			$GLOBALS['member_reads'][] = $record_id;
			return isset( $GLOBALS['members_of'][ $record_id ] ) ? $GLOBALS['members_of'][ $record_id ] : array();
		}
		public static function former_members_of( $record_id ) {
			return isset( $GLOBALS['former_members_of'][ $record_id ] ) ? $GLOBALS['former_members_of'][ $record_id ] : array();
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	class WPCPM_Institution_Agreement {
		const POST_TYPE         = 'wpcpm_agreement';
		const STATE_GENERATED   = 'generated';
		const STATE_SUBMITTED   = 'submitted';
		const STATE_ACCEPTED    = 'accepted';
		const STATE_RETURNED    = 'returned';
		const STATE_WITHDRAWN   = 'withdrawn';
		const STATE_SUPERSEDED  = 'superseded';
		const STATE_REVOKED     = 'revoked';
		const KIND_TEMPLATE     = 'template';
		const KIND_OWN          = 'own';
		const KIND_LEGACY       = 'legacy';
		const SUMMARY_NONE      = 'none';
		const SUMMARY_GENERATED = 'generated';
		const SUMMARY_SUBMITTED = 'submitted';
		const SUMMARY_RETURNED  = 'returned';
		const SUMMARY_REVOKED   = 'revoked';
		const SUMMARY_ACCEPTED  = 'accepted';
		const SUMMARY_ON_FILE   = 'on_file';
		const STAGE_ORDER       = array( 'First Contact Made', 'Call Scheduled', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Agreement Sent', 'Confirmed', 'Student' );
		const TERMINAL_STAGES   = array( 'Not Moving Forward', 'SPAM', 'Revisit Later' );
		const AIRTABLE_SETTLED  = array( 'Accepted', 'On file' );
		const OPTION_PREFIX     = 'wpcpm_agreement_';
		const LOCK_PREFIX       = 'wpcpm_agreement_lock_';
		const VERSION           = 1;
		public static function init() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Agreement::init' ); }
		public static function register_post_type() {}
		public static function is_settled( $id ) { $s = self::summary( $id ); return in_array( $s['state'], array( self::SUMMARY_ACCEPTED, self::SUMMARY_ON_FILE ), true ); }
		public static function option( $id ) { return null; }
		public static function option_name( $id ) { return self::OPTION_PREFIX . $id; }
		public static function summary( $id ) {
			$GLOBALS['summary_reads'][] = $id;
			$none = array( 'state' => self::SUMMARY_NONE, 'kind' => '', 'accepted_at' => '', 'agreement_id' => 0, 'pending_id' => 0, 'generated_id' => 0, 'airtable_status' => '', 'route' => '' );
			return isset( $GLOBALS['summaries'][ $id ] ) ? array_merge( $none, $GLOBALS['summaries'][ $id ] ) : $none;
		}
		public static function rebuild( $id, array $airtable ) { return array(); }
		public static function rebuild_all( array $by_record ) { return 0; }
		public static function discrepancies() { return $GLOBALS['discrepancies'] ?? array(); }
		public static function posts_for( $id ) { return array(); }
		public static function delete_all() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Agreement::delete_all' ); }
	}
}

if ( ! class_exists( 'WPCPM_Institution_Audit' ) ) {
	class WPCPM_Institution_Audit {
		const POST_TYPE = 'wpcpm_audit_entry';
		public static function init() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Audit::init' ); }
		public static function register_post_type() {}
		public static function record( array $entry ) { return 1; }
		public static function entries_for( $institution, $limit = 50 ) { return array(); }
		public static function delete_all() { $GLOBALS['calls'][] = array( 'WPCPM_Institution_Audit::delete_all' ); }
	}
}

if ( ! class_exists( 'WPCPM_Institutions_Sync' ) ) {
	class WPCPM_Institutions_Sync {
		const CRON_DAILY = 'wpcpm_institutions_sync_daily';
		const CRON_TICK  = 'wpcpm_institutions_sync_tick';
		const OPT_STATE  = 'wpcpm_institutions_state';
		const OPT_REPORT = 'wpcpm_institutions_report';
		const OPT_LAST   = 'wpcpm_institutions_last_sync';
		const OPT_ERROR  = 'wpcpm_institutions_last_error';
		const OPT_LOCK   = 'wpcpm_institutions_lock';
		const BUDGET_AJAX = 8;
		public static function register_cron() { $GLOBALS['calls'][] = array( 'WPCPM_Institutions_Sync::register_cron' ); }
		public static function start() { $GLOBALS['calls'][] = array( 'WPCPM_Institutions_Sync::start' ); return empty( $GLOBALS['sync_refuses'] ) ? true : new WP_Error( 'wpcpm_not_connected', 'not connected' ); }
		public static function tick( $budget = null ) { $GLOBALS['calls'][] = array( 'WPCPM_Institutions_Sync::tick', $budget ); }
		public static function cancel() { $GLOBALS['calls'][] = array( 'WPCPM_Institutions_Sync::cancel' ); }
		public static function is_running() { return ! empty( $GLOBALS['sync_running'] ); }
		public static function progress() {
			return array_merge(
				array( 'running' => false, 'phase' => '', 'label' => '', 'detail' => '', 'percent' => 100, 'step' => 4, 'step_total' => 4, 'step_label' => '', 'stats' => array(), 'elapsed' => 0, 'idle' => 0, 'error' => '', 'stalled' => false ),
				$GLOBALS['sync_progress'] ?? array()
			);
		}
		public static function activate() { $GLOBALS['calls'][] = array( 'WPCPM_Institutions_Sync::activate' ); }
		public static function deactivate() { $GLOBALS['calls'][] = array( 'WPCPM_Institutions_Sync::deactivate' ); }
		public static function last_read() { return $GLOBALS['sync_last'] ?? 0; }
	}
}

if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	/** The three keys the reconciliation card reads: the stamp, the active flag, the program. */
	class WPCPM_Students_Sync {
		const META_INSTITUTION = 'wpcpm_student_institution';
		const META_ACTIVE      = 'wpcpm_student_active';
		const META_PROGRAM     = 'wpcpm_student_program';
	}
}

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $v ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $v ) ); }
	}
}

if ( ! class_exists( 'WPCPM_Mentors' ) ) {
	class WPCPM_Mentors {
		public static function format_duration( $s ) { return sprintf( '%d:%02d', intdiv( (int) $s, 60 ), (int) $s % 60 ); }
	}
}

/**
 * The mail layer, recording which door each message left by.
 *
 * `send()` takes an account and builds in its language; `send_to()` takes a bare address.
 * The assertion that matters is which one notify_managers() chose, so the stub records the
 * method and not only the recipient.
 */
if ( ! class_exists( 'WPCPM_Mail' ) ) {
	class WPCPM_Mail {
		public static function send( $recipient, $context, $build ) {
			$user = $recipient instanceof WP_User ? $recipient : get_user_by( 'id', (int) $recipient );
			if ( ! $user instanceof WP_User || ! $user->exists() || '' === $user->user_email ) { return false; }
			$GLOBALS['mail'][] = array( 'send', $user->ID, $context, call_user_func( $build, $user ) );
			return true;
		}
		public static function send_to( $email, $context, $build, $locale = '' ) {
			if ( ! is_email( $email ) ) { return false; }
			$GLOBALS['mail'][] = array( 'send_to', $email, $context, call_user_func( $build, $email ) );
			return true;
		}
	}
}

/* ---- runner ------------------------------------------------------------- */

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

/**
 * The screen's HTML, captured.
 *
 * @param array $get Query arguments the render should see.
 * @return string
 */
function render_screen( array $get = array() ) {
	$_GET = $get;
	$GLOBALS['summary_reads'] = array();
	ob_start();
	( new WPCPM_Institutions() )->render_admin_page();
	return ob_get_clean();
}

/**
 * Every `<h3 class="wpcpm-inst-stage">` heading with its count, in document order.
 *
 * @param string $html Rendered screen.
 * @return array<string, int>
 */
function stage_headings( $html ) {
	preg_match_all( '#<h3 class="wpcpm-inst-stage">(.*?) <span class="wpcpm-count">(\d+)</span></h3>#', $html, $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $h ) { $out[ html_entity_decode( $h[1], ENT_QUOTES ) ] = (int) $h[2]; }
	return $out;
}

/**
 * The body of one function in a source file, by brace depth.
 *
 * @param string $src  File contents.
 * @param string $name Function name.
 * @return string
 */
function function_body( $src, $name ) {
	if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\([^)]*\)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE ) ) { return ''; }
	$offset = $m[0][1] + strlen( $m[0][0] );
	$depth  = 1;
	$end    = $offset;
	while ( $end < strlen( $src ) && $depth > 0 ) {
		if ( '{' === $src[ $end ] ) { $depth++; } elseif ( '}' === $src[ $end ] ) { $depth--; }
		$end++;
	}
	return substr( $src, $offset, $end - $offset );
}

/* ---- fixtures ----------------------------------------------------------- */

$seed = json_decode( file_get_contents( __DIR__ . '/fixtures/institutions-index-seed.json' ), true );

if ( ! is_array( $seed ) || empty( $seed['institutions'] ) ) {
	echo "Could not read bin/fixtures/institutions-index-seed.json\n";
	exit( 1 );
}

$read_at   = 1756800000; // 2025-09-02 08:00 UTC, a fixed instant so the read line is deterministic.
$countries = array();

foreach ( $seed['countries'] as $country ) {
	$countries[ $country['id'] ] = array(
		'name'     => $country['name'],
		'manager'  => $country['has_contact'] ? 'A Manager' : '',
		'email'    => $country['has_email'] ? 'manager@example.test' : '',
		'calendly' => $country['has_calendly'] ? 'https://calendly.com/example' : '',
	);
}

$rows = array();

foreach ( $seed['institutions'] as $institution ) {
	$country = ! empty( $institution['country'] ) ? (string) $institution['country'][0] : '';

	$rows[ $institution['id'] ] = array(
		'record_id'      => $institution['id'],
		'name'           => $institution['name'],
		'stage'          => $institution['stage'],
		'country'        => $country,
		'country_name'   => '' !== $country && isset( $countries[ $country ] ) ? $countries[ $country ]['name'] : '',
		'city'           => $institution['city'],
		'website'        => $institution['website'],
		'contact_person' => $institution['has_contact_person'] ? 'A Person' : '',
		'contact_email'  => $institution['has_contact_email'] ? strtolower( $institution['id'] ) . '@example.test' : '',
		'created'        => substr( $institution['createdTime'], 0, 10 ),
		'consent'        => (bool) $institution['consent'],
		'confirmed_on'   => $institution['confirmed_on'],
		'agreement'      => $institution['agreement'],
	);
}

$GLOBALS['opts'][ WPCPM_Settings::OPTION ]        = array_merge( WPCPM_Settings::defaults(), array( 'api_token' => 'pat', 'base_id' => 'appIzQKfwTn5dyPVp' ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPTION ] = array( 'v' => 1, 'read' => $read_at, 'rows' => $rows );
$GLOBALS['opts'][ WPCPM_Countries::OPTION ]          = array( 'v' => 1, 'read' => $read_at - 600, 'rows' => $countries );
$GLOBALS['opts'][ WPCPM_Roster_Index::OPTION_COUNTS ] = array(
	'v'              => 1,
	'read'           => $read_at - 3600,
	'institutions'   => array(),
	'reconciliation' => array(
		'students_without_reports' => array( 'Not moving forward' => 15, '' => 7, 'Graduate' => 6, 'In Sensei' => 2, 'SPAM' => 1 ),
		'reports_without_students' => array( 'In Sensei' => 7, 'Graduate' => 6, 'Not moving forward' => 4, '' => 1, 'SPAM' => 1 ),
		'status_disagreements'     => 10,
		'duplicate_emails'         => array( 'rec1ZgEtczDKjRNP4' => 5, 'recUNKNOWN0000001' => 4 ),
		'no_institution'           => 3,
		'no_start_date'            => array( 'Not moving forward' => 4, '' => 2, 'Developer Track' => 1 ),
	),
);
$GLOBALS['opts'][ WPCPM_Roster_Index::OPTION_UNLINKED ] = array(
	'v'    => 1,
	'read' => $read_at - 3600,
	'rows' => array(
		'recS1' => array( 'record_id' => 'recS1', 'name' => 'Unlinked One', 'email' => 'u1@example.test', 'email_key' => 'u1@example.test', 'status' => 'In Sensei', 'institution' => '', 'start' => '', 'end' => '', 'has_mentor' => false, 'username' => '', 'field_of_study' => '', 'tutor' => '', 'import_key' => '', 'reports' => array(), 'user_id' => 0 ),
		'recS2' => array( 'record_id' => 'recS2', 'name' => 'Unlinked Two', 'email' => 'u2@example.test', 'email_key' => 'u2@example.test', 'status' => '', 'institution' => '', 'start' => '', 'end' => '', 'has_mentor' => false, 'username' => '', 'field_of_study' => '', 'tutor' => '', 'import_key' => '', 'reports' => array(), 'user_id' => 0 ),
		'recS3' => array( 'record_id' => 'recS3', 'name' => ' Unlinked Three ', 'email' => '', 'email_key' => '', 'status' => 'Graduate', 'institution' => '', 'start' => '', 'end' => '', 'has_mentor' => false, 'username' => '', 'field_of_study' => '', 'tutor' => '', 'import_key' => '', 'reports' => array(), 'user_id' => 0 ),
	),
);

$GLOBALS['users'][1]  = new WP_User( 1, 'Ada Admin', 'admin@example.test', array( 'administrator' ) );
$GLOBALS['users'][2]  = new WP_User( 2, 'Max Manager', 'max@example.test', array( 'administrator' ) );
$GLOBALS['users'][3]  = new WP_User( 3, 'No Address', '', array( 'administrator' ) );
$GLOBALS['users'][30] = new WP_User( 30, 'Sam Student', 'sam@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][31] = new WP_User( 31, 'Sue Student', 'sue@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['manage']    = array( 1, 2, 3 );

// Two students the way a finished sync leaves them: stamped, flagged active, and with the
// program meta naming which table's word the stamp is on.
$GLOBALS['umeta'][30][ WPCPM_Students_Sync::META_INSTITUTION ] = 'rec1ZgEtczDKjRNP4';
$GLOBALS['umeta'][30][ WPCPM_Students_Sync::META_ACTIVE ]      = 1;
$GLOBALS['umeta'][30][ WPCPM_Students_Sync::META_PROGRAM ]     = array( 'institution_source' => 'students' );
$GLOBALS['umeta'][31][ WPCPM_Students_Sync::META_INSTITUTION ] = 'rec2JYIDewxi6iftq';
$GLOBALS['umeta'][31][ WPCPM_Students_Sync::META_ACTIVE ]      = 1;
$GLOBALS['umeta'][31][ WPCPM_Students_Sync::META_PROGRAM ]     = array( 'institution_source' => 'reports' );

/* ---- source: the skeleton and the order of checks ----------------------- */

echo "=== Source: the skeleton and the handler order ===\n";

$src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' );

ck( 'the screen keeps div.wrap.wpcpm-wrap > h1 > p.wpcpm-lede', array(
	false !== strpos( $src, "echo '<div class=\"wrap wpcpm-wrap\">';" ),
	false !== strpos( $src, "echo '<h1>' . esc_html( \$this->label() ) . '</h1>';" ),
	false !== strpos( $src, "echo '<p class=\"wpcpm-lede\">' . esc_html( \$this->description() ) . '</p>';" ),
), array( true, true, true ) );
ck( 'and draws its cards as .wpcpm-card', substr_count( $src, "'<div class=\"wpcpm-card\">'" ) >= 6, true );
ck( 'it no longer falls through to the placeholder', strpos( $src, 'render_placeholder' ), false );
ck( 'is_implemented() is true', ( new WPCPM_Institutions() )->is_implemented(), true );

// Every handler: the capability is decided before the nonce is read, so an anonymous request
// gets the 403 the design names rather than a nonce failure that tells it the handler exists.
preg_match_all( '/public function (handle_[a-z_]+)\s*\(/', $src, $handlers );
ck( 'the four handlers exist', $handlers[1], array( 'handle_tick', 'handle_sync', 'handle_cancel', 'handle_probe' ) );

foreach ( $handlers[1] as $handler ) {
	$body  = function_body( $src, $handler );
	$cap   = strpos( $body, 'current_user_can' );
	$via   = strpos( $body, '$this->verify(' );
	$nonce = strpos( $body, 'check_admin_referer' );
	$ajax  = strpos( $body, 'check_ajax_referer' );
	$first = false !== $cap ? $cap : $via;
	$check = false !== $nonce ? $nonce : $ajax;

	ck( sprintf( '%s decides the capability before reading a nonce', $handler ), array(
		false !== $first,
		false === $check || $first < $check,
		false === $nonce || false !== $via,
	), array( true, true, true ) );
}

$verify = function_body( $src, 'verify' );
ck( 'verify() itself checks the capability first', array(
	false !== strpos( $verify, 'current_user_can( WPCPM_Roles::CAP_MANAGE )' ),
	strpos( $verify, 'current_user_can' ) < strpos( $verify, 'check_admin_referer( $action )' ),
	false !== strpos( $verify, "wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 )" ),
), array( true, true, true ) );

ck( 'no institution id is compared with === in the screen',
	preg_match( '/\$record_id\s*===|===\s*\$record_id|\$country\s*===\s*\$|\$institution\s*===/', $src ), 0 );
ck( 'nothing on the screen reads Airtable', array( strpos( $src, 'WPCPM_Airtable' ), strpos( $src, 'fetch_all' ) ), array( false, false ) );
ck( 'the screen never renders the option key of a file URL', strpos( $src, 'base_url' ), false );
ck( 'no em or en dash anywhere in the module', preg_match( "/\xE2\x80\x93|\xE2\x80\x94/", $src ), 0 );

$files_src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-private-files.php' );
ck( 'nor in the private-files class', preg_match( "/\xE2\x80\x93|\xE2\x80\x94/", $files_src ), 0 );
ck( 'nor in the CSS section', preg_match( "/\xE2\x80\x93|\xE2\x80\x94/", substr( file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/admin.css' ), strpos( file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/admin.css' ), 'Institutions screen' ) ) ), 0 );
// Comment lines stripped first: both writers explain themselves in prose that names the
// function, and counting those would make this assertion pass or fail for the wrong reason.
$files_code = implode( "\n", array_filter( explode( "\n", $files_src ), function ( $line ) {
	$t = ltrim( $line );
	return '' !== $t && 0 !== strpos( $t, '*' ) && 0 !== strpos( $t, '//' ) && 0 !== strpos( $t, '/*' );
} ) );
ck( 'every option the store writes is kept out of the autoloaded set', array(
	preg_match_all( '/update_option\(/', $files_code ),
	preg_match_all( '/update_option\([^;]*,\s*false\s*\);/s', $files_code ),
	// `add_option()` takes the flag in a fourth argument; the key must not be loaded on every
	// request of every page either.
	preg_match_all( '/add_option\(/', $files_code ),
	preg_match_all( '/add_option\([^;]*,\s*false\s*\)/s', $files_code ),
), array( 3, 3, 1, 1 ) );

/* ---- the screen, rendered from the seed fixture ------------------------- */

echo "\n=== The screen, rendered from the seed fixture ===\n";

$html = render_screen();

ck( 'the page opens with the skeleton', array(
	false !== strpos( $html, '<div class="wrap wpcpm-wrap"><h1>Institutions</h1><p class="wpcpm-lede">' ),
	substr_count( $html, '<div class="wpcpm-card">' ),
), array( true, 7 ) );

ck( 'the pipeline counts every fixture row', false !== strpos( $html, 'Pipeline <span class="wpcpm-count">' . $seed['counts']['institutions'] . '</span>' ), true );

$headings = stage_headings( $html );
$expected = array();
foreach ( array_merge( WPCPM_Institution_Agreement::STAGE_ORDER, WPCPM_Institution_Agreement::TERMINAL_STAGES ) as $stage ) {
	if ( isset( $seed['counts']['by_stage'][ $stage ] ) ) { $expected[ $stage ] = (int) $seed['counts']['by_stage'][ $stage ]; }
}
ck( 'the groups are the fixture\'s stage counts, in STAGE_ORDER then the terminal stages', $headings, $expected );
ck( 'and no group is drawn for the empty stage the fixture does not have', isset( $headings['No stage'] ), false );

// The read time, once per card that reads the index (the pipeline, the consent report, the
// discrepancies and the template versions) and once for the countries map and the roster
// counts. A stale count must never look fresh.
ck( 'the index read time is printed with the date and the age', substr_count( $html, 'Pipeline index: read ' . gmdate( 'Y-m-d H:i', $read_at ) . ' (4 hours ago).' ), 4 );
ck( 'so is the roster counts\' read time', substr_count( $html, 'Roster counts: read ' . gmdate( 'Y-m-d H:i', $read_at - 3600 ) . ' (4 hours ago).' ), 1 );
ck( 'and the countries map\'s', substr_count( $html, 'Countries map: read ' . gmdate( 'Y-m-d H:i', $read_at - 600 ) ), 1 );

// Names: trimmed, with the mark where the stored one was not.
$trailing = array();
foreach ( $seed['institutions'] as $i ) { if ( $i['name'] !== rtrim( $i['name'] ) ) { $trailing[] = $i['name']; } }
ck( 'the fixture has ten names ending in a space', count( $trailing ), $seed['counts']['trailing_space_names'] );

$trimmed_ok = true;
foreach ( $trailing as $name ) {
	$trimmed_ok = $trimmed_ok
		&& false !== strpos( $html, '<span class="wpcpm-inst-name__text">' . esc_html( trim( $name ) ) . '</span>' )
		&& false === strpos( $html, esc_html( $name ) . '</span>' );
}
ck( 'every such name prints trimmed', $trimmed_ok, true );
ck( 'and carries the whitespace mark', substr_count( $html, 'wpcpm-inst-mark--space' ), $seed['counts']['trailing_space_names'] );
ck( 'the two nameless records are marked, not printed blank', substr_count( $html, 'wpcpm-inst-mark--empty' ), $seed['counts']['nameless'] );
// Proven with a row the suite empties: the two records that had no name on 2 September were
// deleted by a program manager the same day, and this has to keep working for the next one.
$blank_id   = $seed['institutions'][0]['id'];
$kept       = get_option( WPCPM_Institutions_Index::OPTION );
$with_blank = $kept;
$with_blank['rows'][ $blank_id ]['name'] = '';
update_option( WPCPM_Institutions_Index::OPTION, $with_blank, false );
$blank_html = render_screen();
update_option( WPCPM_Institutions_Index::OPTION, $kept, false );
ck( 'a nameless record is marked rather than printed blank', substr_count( $blank_html, 'wpcpm-inst-mark--empty' ), 1 );
ck( 'and shows its record id so it can be found in the grid', false !== strpos( $blank_html, '<code class="wpcpm-inst-record">' . $blank_id . '</code>' ), true );

// Countries: every one an institution names resolves; the ones with no contact are marked.
$named_without_contact = 0;
foreach ( $rows as $row ) {
	if ( '' !== $row['country'] && null === WPCPM_Countries::routing( $row['country'] ) ) { $named_without_contact++; }
}
ck( 'the fixture names three countries with no contact', count( array_unique( array_map( function ( $r ) { return $r['country']; }, array_filter( $rows, function ( $r ) { return '' !== $r['country'] && null === WPCPM_Countries::routing( $r['country'] ); } ) ) ) ), $seed['counts']['countries_used_without_contact'] );
ck( 'each row naming one carries the no-contact mark', substr_count( $html, 'wpcpm-inst-mark--routing' ), $named_without_contact );
ck( 'and the routing gaps are listed by name', preg_match( '/Countries named by institutions with no program manager contact in the Countries table: ([^<]+)</', $html, $m ), 1 );
$gap_names = isset( $m[1] ) ? array_map( function ( $p ) { return preg_replace( '/ \(\d+\)$/', '', $p ); }, explode( ', ', html_entity_decode( $m[1], ENT_QUOTES ) ) ) : array();
sort( $gap_names );
ck( 'which are Cambodia, Nigeria and Thailand', $gap_names, array( 'Cambodia', 'Nigeria', 'Thailand' ) );
ck( 'no row prints an unresolved country', strpos( $html, 'unknown country' ), false );
ck( 'the rows with no country say so', substr_count( $html, '>no country<' ), count( array_filter( $rows, function ( $r ) { return '' === $r['country']; } ) ) );

// Contact and consent columns.
ck( 'the one Confirmed record with no email is marked', substr_count( $html, '<span class="wpcpm-warning">no email</span>' ), count( array_filter( $rows, function ( $r ) { return '' === $r['contact_email']; } ) ) );
ck( 'no address is printed on the screen', preg_match( '/@example\.test/', $html ), 0 );

// The agreement column reads the summary, once per row.
ck( 'one summary is read per row', count( array_unique( $GLOBALS['summary_reads'] ) ), count( $rows ) );
ck( 'with nothing recorded every row reads Not started', substr_count( $html, '<td class="wpcpm-inst-agreement">Not started</td>' ), count( $rows ) );

/* ---- the agreement-gap filter ------------------------------------------- */

echo "\n=== The agreement-gap filter ===\n";

ck( 'on day one the link counts every Confirmed row', false !== strpos( $html, 'Confirmed with no agreement recorded <span class="wpcpm-count">' . $seed['counts']['by_stage']['Confirmed'] . '</span></a>' ), true );
ck( 'and points at the sanitised filter argument', false !== strpos( $html, '?page=wpcpm-institutions&wpcpm_filter=agreement_gap' ), true );

// Three Confirmed institutions settle: two recorded in the grid, one on the site.
$confirmed = array_keys( array_filter( $rows, function ( $r ) { return 'Confirmed' === $r['stage']; } ) );
$GLOBALS['summaries'] = array(
	$confirmed[0] => array( 'state' => 'on_file', 'kind' => 'legacy', 'accepted_at' => '2026-09-02', 'airtable_status' => 'On file', 'route' => 'grid' ),
	$confirmed[1] => array( 'state' => 'on_file', 'kind' => 'legacy', 'accepted_at' => '2026-09-02', 'airtable_status' => 'On file', 'route' => 'grid' ),
	$confirmed[2] => array( 'state' => 'accepted', 'kind' => 'template', 'accepted_at' => '2026-09-01', 'airtable_status' => 'Accepted', 'route' => 'site' ),
	// A submitted one does not settle, and neither does an accepted post Airtable disagrees with.
	$confirmed[3] => array( 'state' => 'submitted', 'kind' => 'own', 'airtable_status' => 'Awaiting review', 'route' => 'site' ),
);

$html = render_screen();

ck( 'three settled rows leave 39 in the gap', false !== strpos( $html, 'Confirmed with no agreement recorded <span class="wpcpm-count">' . ( $seed['counts']['by_stage']['Confirmed'] - 3 ) . '</span></a>' ), true );
ck( 'the agreement column names state, kind, date and route', array(
	false !== strpos( $html, '<td class="wpcpm-inst-agreement wpcpm-inst-agreement--settled">On file, legacy, accepted 2026-09-02, recorded in the Airtable grid</td>' ),
	false !== strpos( $html, '<td class="wpcpm-inst-agreement wpcpm-inst-agreement--settled">Accepted, program template, accepted 2026-09-01, recorded on the site</td>' ),
	false !== strpos( $html, '<td class="wpcpm-inst-agreement">Awaiting review, institution-specific, recorded on the site</td>' ),
), array( true, true, true ) );

$filtered = render_screen( array( 'wpcpm_filter' => 'agreement_gap' ) );
$headings = stage_headings( $filtered );

ck( 'the filtered view draws one group of 39 Confirmed rows', $headings, array( 'Confirmed' => $seed['counts']['by_stage']['Confirmed'] - 3 ) );
ck( 'none of which is settled', strpos( $filtered, 'wpcpm-inst-agreement--settled' ), false );
ck( 'and the way back is offered', false !== strpos( $filtered, 'Showing the 39 Confirmed institutions with no agreement recorded. <a href="https://example.test/wp-admin/admin.php?page=wpcpm-institutions">Show every stage</a>' ), true );

$junk = render_screen( array( 'wpcpm_filter' => '<script>agreement_gap' ) );
ck( 'a filter value that is not the one offered shows every stage', count( stage_headings( $junk ) ), count( $expected ) );

/* ---- the consent report ------------------------------------------------- */

echo "\n=== The consent report ===\n";

$sentence = sprintf(
	'%d institution records were collected before the consent question was added on 20 July 2026, %d of them at Confirmed.',
	$seed['counts']['created_before_consent_question'],
	$seed['counts']['created_before_consent_question_confirmed']
);
ck( 'the sentence carries the fixture\'s 84 and 38', false !== strpos( $html, '<p class="wpcpm-inst-consent">' . $sentence . '</p>' ), true );

$since = 0;
foreach ( $rows as $row ) { if ( ! $row['consent'] && strcmp( $row['created'], '2026-07-20' ) >= 0 ) { $since++; } }
ck( 'and the count of hand-entered records since', false !== strpos( $html, 'Since then, ' . $since . ' records have been created without the tick' ), true );
ck( 'the word "lost" appears nowhere on the screen', stripos( $html, 'lost' ), false );
ck( 'nor in the module source', stripos( $src, 'lost' ), false );

// A record created on the boundary day counts as after it, whatever timezone the site is in.
$boundary = $rows;
$boundary['recBOUNDARY0000001'] = array_merge( reset( $rows ), array( 'record_id' => 'recBOUNDARY0000001', 'name' => 'Boundary', 'stage' => 'Confirmed', 'created' => '2026-07-20', 'consent' => false ) );
$boundary['recEVE000000000001'] = array_merge( reset( $rows ), array( 'record_id' => 'recEVE000000000001', 'name' => 'Eve', 'stage' => 'Confirmed', 'created' => '2026-07-19', 'consent' => false ) );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPTION ]['rows'] = $boundary;
$edge = render_screen();
ck( '20 July itself is after the question, 19 July before it', false !== strpos( $edge, sprintf( '%d institution records were collected before the consent question was added on 20 July 2026, %d of them at Confirmed.', $seed['counts']['created_before_consent_question'] + 1, $seed['counts']['created_before_consent_question_confirmed'] + 1 ) ), true );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPTION ]['rows'] = $rows;

/* ---- the reconciliation card -------------------------------------------- */

echo "\n=== The reconciliation card ===\n";

ck( 'the card reads 31 / 19 / 10 / 9 / 3', array(
	false !== strpos( $html, '<th scope="row">Students rows with no reports row</th><td>31 <span class="wpcpm-inst-muted">(Not moving forward 15, (empty) 7, Graduate 6, In Sensei 2, SPAM 1)</span></td>' ),
	false !== strpos( $html, '<th scope="row">Reports rows with no Students row</th><td>19 <span class="wpcpm-inst-muted">(In Sensei 7, Graduate 6, Not moving forward 4, (empty) 1, SPAM 1)</span></td>' ),
	false !== strpos( $html, '<th scope="row">Status disagreements on joined rows</th><td>10</td>' ),
	false !== strpos( $html, '<th scope="row">Duplicate emails in the Students table</th><td>9 <span class="wpcpm-inst-muted">(Università di Pisa (5), recUNKNOWN0000001 (4))</span></td>' ),
	false !== strpos( $html, '<th scope="row">Students rows with no institution</th><td>3</td>' ),
), array( true, true, true, true, true ) );
ck( 'the no-start-date count is split by status', false !== strpos( $html, '<th scope="row">Students rows with no start date</th><td>7 <span class="wpcpm-inst-muted">(Not moving forward 4, (empty) 2, Developer Track 1)</span></td>' ), true );
ck( 'the no-stamp count reads zero, counted now', false !== strpos( $html, '<th scope="row">Tracked student accounts with no institution stamp</th><td>0 <span class="wpcpm-inst-muted">(counted now)</span></td>' ), true );
ck( 'and says "tracked", which is what it counts', false !== strpos( $src, "'Tracked student accounts with no institution stamp'" ), true );

$query = null;
foreach ( $GLOBALS['calls'] as $call ) { if ( 'WP_User_Query' === $call[0] ) { $query = $call[1]; } }
ck( 'through a NOT EXISTS query on the stamp AND the live flag, over the student role', array(
	$query['role'],
	$query['meta_query']['relation'],
	$query['meta_query'][0]['key'], $query['meta_query'][0]['compare'],
	$query['meta_query'][1]['key'], $query['meta_query'][1]['value'],
	$query['count_total'],
), array( WPCPM_Roles::ROLE_STUDENT, 'AND', 'wpcpm_student_institution', 'NOT EXISTS', 'wpcpm_student_active', '1', false ) );

ck( 'the unlinked rows are listed by name and status only', array(
	false !== strpos( $html, '<li>Unlinked One <span class="wpcpm-inst-muted">In Sensei</span></li>' ),
	false !== strpos( $html, '<li>Unlinked Two <span class="wpcpm-inst-muted">(no status)</span></li>' ),
	false !== strpos( $html, '<li>Unlinked Three <span class="wpcpm-inst-muted">Graduate</span></li>' ),
	false === strpos( $html, 'u1@example.test' ),
), array( true, true, true, true ) );
ck( 'with the note that linking ships next phase', false !== strpos( $html, 'Linking a row to an institution from here ships with the next phase.' ), true );

// A stamp the sync deleted on purpose is not a broken sync: it wrote `institution_source`,
// which is its word that it looked. Account 31 is one of those, and unstamping it must leave
// the row at zero.
unset( $GLOBALS['umeta'][31][ WPCPM_Students_Sync::META_INSTITUTION ] );
$deliberate = render_screen();
ck( 'an account the sync unstamped on purpose is not reported as a broken sync', false !== strpos( $deliberate, '<td>0 <span class="wpcpm-inst-muted">(counted now)</span></td>' ), true );
ck( 'and the row is not a warning', false === strpos( $deliberate, '<td class="wpcpm-warning">1 <span class="wpcpm-inst-muted">(counted now; should be 0' ), true );

// A broken sync is an account the sync has never described: tracked, live, no stamp and no
// `institution_source` at all.
$GLOBALS['umeta'][32][ WPCPM_Students_Sync::META_ACTIVE ] = 1;
$GLOBALS['umeta'][32][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'status' => 'In Sensei' );
$GLOBALS['users'][32] = new WP_User( 32, 'Never Described', 'nd@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$broken = render_screen();
ck( 'an account no run has ever described turns the row into a warning', false !== strpos( $broken, '<td class="wpcpm-warning">1 <span class="wpcpm-inst-muted">(counted now; should be 0, anything else is a broken sync)</span></td>' ), true );
unset( $GLOBALS['umeta'][32], $GLOBALS['users'][32] );

$GLOBALS['umeta'][31][ WPCPM_Students_Sync::META_INSTITUTION ] = 'rec2JYIDewxi6iftq';

// The two accounts the sync leaves without a stamp on purpose. Neither is a broken sync, and
// counting them by role alone reported both: the first for as long as the account exists.
$GLOBALS['users'][32] = new WP_User( 32, 'Dee Departed', 'dee@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['umeta'][32][ WPCPM_Students_Sync::META_ACTIVE ]  = 0;
$GLOBALS['umeta'][32][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'institution_source' => 'students' );

$GLOBALS['users'][33] = new WP_User( 33, 'Dan Duplicate', 'dan@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['umeta'][33][ WPCPM_Students_Sync::META_ACTIVE ]  = 1;
$GLOBALS['umeta'][33][ WPCPM_Students_Sync::META_PROGRAM ] = array( 'institution_source' => '' );

$narrowed = render_screen();
ck( 'a departed account that kept the role is not one, whatever student_on_inactive says', false !== strpos( $narrowed, '<th scope="row">Tracked student accounts with no institution stamp</th><td>0 <span class="wpcpm-inst-muted">(counted now)</span></td>' ), true );
// Which of the two the query excluded and which the card did: the departed account never
// reaches PHP, the duplicate does and is skipped on its program meta, and the count is 0.
$candidates = null;
foreach ( $GLOBALS['calls'] as $call ) { if ( 'WP_User_Query' === $call[0] ) { $candidates = $call[1]; } }
$candidates = ( new WP_User_Query( $candidates ) )->get_results();
ck( 'nor is the duplicate email the sync unstamped on purpose, which the duplicates row already counts', array(
	in_array( 32, $candidates, true ),
	in_array( 33, $candidates, true ),
), array( false, true ) );

// An account holding the role and the live flag that the sync has never described is still
// counted: nothing says it was left unstamped on purpose.
$GLOBALS['users'][34] = new WP_User( 34, 'Una Undescribed', 'una@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['umeta'][34][ WPCPM_Students_Sync::META_ACTIVE ] = 1;

ck( 'an account with the live flag the sync has never described is a broken sync', false !== strpos( render_screen(), '<td class="wpcpm-warning">1 <span class="wpcpm-inst-muted">(counted now; should be 0, anything else is a broken sync)</span></td>' ), true );

unset( $GLOBALS['users'][32], $GLOBALS['users'][33], $GLOBALS['users'][34], $GLOBALS['umeta'][32], $GLOBALS['umeta'][33], $GLOBALS['umeta'][34] );

/* ---- the manager backstop counts ---------------------------------------- */

echo "\n=== The manager backstop counts ===\n";

// Day one, with nothing provisioned: every institution is missing a member, and every
// address the base names for one belongs to nobody on this site.
$emailed = array_keys( array_filter( $rows, function ( $r ) { return '' !== $r['contact_email']; } ) );
ck( 'the fixture names a contact address for 102 of the 106 institutions', count( $emailed ), 102 );
ck( 'with nobody provisioned, both counts are the whole pipeline', array(
	false !== strpos( $html, 'Institutions with no live member <span class="wpcpm-count">' . count( $rows ) . '</span>' ),
	false !== strpos( $html, '<th scope="row">Contacts who are not members</th><td>' . count( $emailed ) . ' ' ),
), array( true, true ) );

// The one that pages a manager prints inside the pipeline card, above the stage tables; the
// contacts count prints on the reconciliation card, where the design puts it.
ck( 'the no-member count is in the pipeline card and the contacts count on the reconciliation card', array(
	false !== strpos( $html, 'Institutions with no live member' ) && strpos( $html, 'Institutions with no live member' ) < strpos( $html, '<h3 class="wpcpm-inst-stage">' ),
	strpos( $html, 'Contacts who are not members' ) > strpos( $html, 'Students rows with no institution' ),
), array( true, true ) );

$provenance = '(from the pipeline index read ' . gmdate( 'Y-m-d H:i', $read_at ) . '; memberships counted now)';
ck( 'each count says which half is as old as the sync and which was read now', substr_count( $html, $provenance ), 2 );

ck( 'the two pieces this phase does not build are named, not printed as a zero', array(
	false !== strpos( $html, 'Invitations ship with a later phase, so the third backstop count, invitations older than seven days, is not shown: there are none to count yet.' ),
	false !== strpos( $html, 'Adding that person in one click ships with the accounts phase' ),
	false !== strpos( $html, 'Adding an account by hand ships with the accounts phase' ),
), array( true, true, true ) );

// Two institutions acquire a member: one is the contact herself, recorded in another case
// and with the spaces a form leaves behind; the other is somebody else entirely.
$GLOBALS['members_of'] = array(
	$emailed[0] => array( new WP_User( 40, 'Contact One', ' ' . strtoupper( $rows[ $emailed[0] ]['contact_email'] ) . ' ', array( WPCPM_Roles::ROLE_INSTITUTION ) ) ),
	$emailed[1] => array( new WP_User( 41, 'Someone Else', 'someone@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) ) ),
);
$GLOBALS['member_reads'] = array();
$members                 = render_screen();

ck( 'two institutions with a live member leave 104 with none', false !== strpos( $members, 'Institutions with no live member <span class="wpcpm-count">' . ( count( $rows ) - 2 ) . '</span>' ), true );
ck( 'and only the one whose member is the contact leaves the address counted', false !== strpos( $members, '<th scope="row">Contacts who are not members</th><td>' . ( count( $emailed ) - 1 ) . ' ' ), true );
ck( 'and the card says what a contact who is not a member means, and what is not built for it yet', false !== strpos( $members, 'A Contact Email that belongs to no member is the address Airtable names for the institution' ), true );
ck( 'each institution is asked for its members exactly once, for both counts together', array(
	count( $GLOBALS['member_reads'] ),
	count( array_unique( $GLOBALS['member_reads'] ) ),
), array( count( $rows ), count( $rows ) ) );
ck( 'no address reaches the screen, the member\'s least of all', preg_match( '/@example\.test/i', $members ), 0 );

$GLOBALS['members_of']   = array();
$GLOBALS['member_reads'] = array();

/* ---- discrepancies and the template card -------------------------------- */

echo "\n=== Discrepancies and the template card ===\n";

ck( 'with none, the card says the two sides agree', false !== strpos( $html, 'Agreement discrepancies <span class="wpcpm-count">0</span></h2>' ) && false !== strpos( $html, 'The site and Airtable agree on every agreement.' ), true );

$GLOBALS['discrepancies'] = array(
	'rec1ZgEtczDKjRNP4' => array( 'site_state' => 'accepted', 'airtable_status' => 'Revoked' ),
	'recNOTINDEXED0001' => array( 'site_state' => '', 'airtable_status' => 'On file' ),
);
$with = render_screen();
ck( 'each discrepancy is listed by name with both sides', array(
	false !== strpos( $with, '<tr><td>Università di Pisa<br /><code>rec1ZgEtczDKjRNP4</code></td><td>accepted</td><td>Revoked</td></tr>' ),
	false !== strpos( $with, '<tr><td>recNOTINDEXED0001<br /><code>recNOTINDEXED0001</code></td><td>(nothing recorded)</td><td>On file</td></tr>' ),
), array( true, true ) );
$GLOBALS['discrepancies'] = array();

$template = WPCPM_Agreement_Template::load( 'en' );
ck( 'the template card shows the version, the read date, the source and the checksum prefix', array(
	false !== strpos( $html, '<th scope="row">Version</th><td>' . $template['version'] . '</td>' ),
	false !== strpos( $html, '<th scope="row">Copied from the Doc on</th><td>' . $template['read'] . '</td>' ),
	false !== strpos( $html, esc_html( $template['source'] ) ),
	false !== strpos( $html, '<code>' . substr( WPCPM_Agreement_Template::checksum( $template ), 0, 12 ) . '</code>' ),
), array( true, true, true, true ) );

// The wording's address is a setting rather than a value in the code: the document is editable
// by anyone holding its link, and this plugin's source is public. With no address given the card
// says so; given one, it links it.
ck( 'with no address given, the card says where the address lives and prints none', array(
	false !== strpos( $html, '(its address is a setting, not carried in the code)' ),
	false !== strpos( $html, 'docs.google.com' ),
), array( true, false ) );

$GLOBALS['opts'][ WPCPM_Settings::OPTION ]['agreement_doc_url'] = 'https://docs.google.com/document/d/EXAMPLEDOCID/edit';
$with_doc = render_screen();
unset( $GLOBALS['opts'][ WPCPM_Settings::OPTION ]['agreement_doc_url'] );

ck( 'and links it once the site has been given one', array(
	false !== strpos( $with_doc, 'href="https://docs.google.com/document/d/EXAMPLEDOCID/edit"' ),
	false !== strpos( $with_doc, '>Open it</a>' ),
), array( true, true ) );
ck( 'and offers no drift button', array( stripos( $html, 'drift' ), stripos( $html, 'Check against the Doc' ) ), array( false, false ) );

// Step four of keeping the copy in step with the Doc: who signed which version. The version
// the site generates is named first, so "an earlier version" has something to be earlier than.
ck( 'the card names the version the site generates today', false !== strpos( $html, 'The site generates ' . $template['version'] . ' (en) today, so anything listed below it was signed against wording the program has changed since.' ), true );
ck( 'and with no agreement recorded anywhere, says no version has been signed', array(
	false !== strpos( $html, '<h3>Institutions per template version</h3>' ),
	false !== strpos( $html, 'No institution has an agreement recorded, so no template version has been signed yet.' ),
), array( true, true ) );

// Four institutions with an agreement: three from the template at two versions, one legacy
// copy with none. The other 102 rows carry an empty agreement block and are not listed at
// all, because a record nobody has asked an agreement from has not signed an old one.
$signed = $rows;
$ids    = array_keys( $signed );

$signed[ $ids[0] ]['agreement'] = array_merge( $signed[ $ids[0] ]['agreement'], array( 'status' => 'Accepted', 'kind' => 'Program template', 'template_version' => '2025-06-12' ) );
$signed[ $ids[1] ]['agreement'] = array_merge( $signed[ $ids[1] ]['agreement'], array( 'status' => 'Accepted', 'kind' => 'Program template', 'template_version' => $template['version'] ) );
$signed[ $ids[2] ]['agreement'] = array_merge( $signed[ $ids[2] ]['agreement'], array( 'status' => 'Accepted', 'kind' => 'Program template', 'template_version' => '2025-06-12' ) );
$signed[ $ids[3] ]['agreement'] = array_merge( $signed[ $ids[3] ]['agreement'], array( 'status' => 'On file', 'kind' => 'Legacy' ) );

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPTION ]['rows'] = $signed;
$versioned = render_screen();

preg_match_all(
	'#<tr><th scope="row">([^<]+)</th><td>(\d+) <span class="wpcpm-inst-muted">\(([^<]*)\)</span></td></tr>#',
	substr( $versioned, strpos( $versioned, 'Institutions per template version' ) ),
	$vm,
	PREG_SET_ORDER
);

$listed = array();
foreach ( $vm as $row ) { $listed[] = array( html_entity_decode( $row[1], ENT_QUOTES ), (int) $row[2], html_entity_decode( $row[3], ENT_QUOTES ) ); }

ck( 'each version is listed newest first with its count and its institutions', $listed, array(
	array( $template['version'], 1, trim( $rows[ $ids[1] ]['name'] ) ),
	array( '2025-06-12', 2, trim( $rows[ $ids[0] ]['name'] ) . ', ' . trim( $rows[ $ids[2] ]['name'] ) ),
	array( 'No version recorded (the bespoke and legacy agreements)', 1, trim( $rows[ $ids[3] ]['name'] ) ),
) );
ck( 'the rows with no agreement at all are not listed as having signed nothing', array_sum( array_column( $listed, 1 ) ), 4 );
ck( 'and the ones with no version are named for what they are, never called unknown', array(
	stripos( implode( ' ', array_column( $listed, 0 ) ), 'unknown' ),
	false !== strpos( $listed[2][0], 'the bespoke and legacy agreements' ),
), array( false, true ) );

$GLOBALS['opts'][ WPCPM_Institutions_Index::OPTION ]['rows'] = $rows;

/* ---- the storage card --------------------------------------------------- */

// The store's own behaviour, its directory and its encryption belong to
// bin/test-private-files.php. What is checked here is only what this screen says about it.

echo "\n=== The storage card ===\n";

$base = $GLOBALS['uploads'] . '/' . WPCPM_Private_Files::DIRECTORY . '/';

// What this host does: the dot path is refused by its own rule, a plain uploads path is served.
$GLOBALS['head'] = array( 'response' => array( 'code' => 200 ) );
$result          = WPCPM_Private_Files::probe();
$html            = render_screen();

ck( 'the card says the host refuses direct requests', false !== strpos( $html, 'The host refuses direct requests to the private directory (HTTP 403 on ' . gmdate( 'Y-m-d H:i', $result['time'] ) . ').' ), true );
ck( 'and says the files are encrypted, which is the control that does not need the host', false !== strpos( $html, 'Stored files are encrypted with AES-256-GCM.' ), true );
ck( 'with a Run probe button posting the probe action', false !== strpos( $html, '<input type="hidden" name="action" value="wpcpm_institutions_probe" />' ) && false !== strpos( $html, '>Run probe</button>' ), true );

// The control is what makes the refusal attributable to the leading dot rather than to a host
// that refuses everything under uploads.
ck( 'the control path was measured and served', array( $result['control_status'] >= 200 && $result['control_status'] < 300 ), array( true ) );
ck( 'so the card explains what the dot is doing', false !== strpos( $html, 'so the dot is what makes the difference' ), true );

// A record from before the control existed must not make the card claim something it did not measure.
$GLOBALS['opts']['wpcpm_private_probe'] = array( 'status' => 403, 'time' => $result['time'], 'blocked' => true, 'error' => '' );
ck( 'an older record leaves the explanation out rather than inventing it', false === strpos( render_screen(), 'so the dot is what makes the difference' ), true );

// The host changing its mind: the card must still be honest, and must say the bytes are useless.
$GLOBALS['head'] = array( 'response' => array( 'code' => 200 ) );
$result          = WPCPM_Private_Files::probe_result();
$result          = array( 'status' => 200, 'time' => $result['time'], 'blocked' => false, 'error' => '', 'control_status' => 200, 'encrypted' => true );
$GLOBALS['opts']['wpcpm_private_probe'] = $result;
$html = render_screen();
ck( 'a served verdict warns, names the path and says what is exposed', array(
	false !== strpos( $html, 'The host hands out files in the private directory to anyone who asks (HTTP 200 on ' . gmdate( 'Y-m-d H:i', $result['time'] ) . ').' ),
	false !== strpos( $html, 'What it hands over is encrypted' ),
	false !== strpos( $html, '/wp-content/uploads/.wpcpm-private/ should not be reachable' ),
	false !== strpos( $html, 'wpcpm-warning' ),
), array( true, true, true, true ) );

$GLOBALS['opts']['wpcpm_private_probe'] = array( 'status' => 0, 'time' => $result['time'], 'blocked' => false, 'error' => 'cURL error 28', 'control_status' => 0, 'encrypted' => true );
ck( 'a failed probe says it could not tell', false !== strpos( render_screen(), 'The probe could not tell what the host does (on ' . gmdate( 'Y-m-d H:i', $result['time'] ) . '): cURL error 28' ), true );

$GLOBALS['opts']['wpcpm_private_probe'] = array( 'status' => 503, 'time' => $result['time'], 'blocked' => false, 'error' => '', 'control_status' => 0, 'encrypted' => true );
ck( 'and a 5xx is neither verdict', false !== strpos( render_screen(), 'it answered HTTP 503 on ' ), true );

delete_option( 'wpcpm_private_probe' );
ck( 'with no record probe_result() is null', WPCPM_Private_Files::probe_result(), null );
ck( 'and the card says the probe has not run', false !== strpos( render_screen(), 'The probe has not run yet.' ), true );

update_option( 'wpcpm_private_probe', 'garbage', false );
ck( 'a malformed record is null too', WPCPM_Private_Files::probe_result(), null );

// path(): inside only, files only, resolved through realpath.
mkdir( $base . 'agreements/2026', 0777, true );
file_put_contents( $base . 'agreements/2026/abc.pdf', '%PDF-' );
file_put_contents( $GLOBALS['uploads'] . '/outside.txt', 'secret' );
$real = realpath( $base . 'agreements/2026/abc.pdf' );

ck( 'a stored relative path resolves to the file', WPCPM_Private_Files::path( 'agreements/2026/abc.pdf' ), $real );
ck( 'a leading slash is tolerated', WPCPM_Private_Files::path( '/agreements/2026/abc.pdf' ), $real );
ck( 'dot-dot out of the base is refused', WPCPM_Private_Files::path( '../outside.txt' ), false );
ck( 'so is a longer climb', WPCPM_Private_Files::path( 'agreements/../../outside.txt' ), false );
ck( 'the base itself is refused', WPCPM_Private_Files::path( '.' ), false );
ck( 'a directory inside is refused', WPCPM_Private_Files::path( 'agreements/2026' ), false );
ck( 'a file that does not exist is refused', WPCPM_Private_Files::path( 'agreements/2026/nope.pdf' ), false );
ck( 'an empty path is refused', WPCPM_Private_Files::path( '' ), false );
ck( 'a NUL byte is refused', WPCPM_Private_Files::path( "agreements/2026/abc.pdf\0" ), false );

if ( function_exists( 'symlink' ) && @symlink( $GLOBALS['uploads'] . '/outside.txt', $base . 'agreements/link.txt' ) ) {
	ck( 'a symlink pointing outside is refused', WPCPM_Private_Files::path( 'agreements/link.txt' ), false );
}

/* ---- notify_managers() -------------------------------------------------- */

echo "\n=== notify_managers() ===\n";

$build = function ( $who ) {
	return array( 'subject' => 'Signed agreement waiting', 'body' => is_object( $who ) ? 'Hello ' . $who->display_name : 'Hello ' . $who );
};

$GLOBALS['mail'] = array();
$sent = WPCPM_Institutions::notify_managers( 'agreement-landed', $build );
ck( 'with the setting empty, every manager with an address is reached through send()', array( $sent, array_map( function ( $s ) { return array( $s[0], $s[1] ); }, $GLOBALS['mail'] ) ), array( 2, array( array( 'send', 1 ), array( 'send', 2 ) ) ) );
ck( 'the manager with no address is skipped, not failed', in_array( 3, array_column( $GLOBALS['mail'], 1 ), true ), false );
ck( 'and the builder saw the account', $GLOBALS['mail'][0][3]['body'], 'Hello Ada Admin' );
ck( 'the context reaches the log', $GLOBALS['mail'][0][2], 'agreement-landed' );

$GLOBALS['opts'][ WPCPM_Settings::OPTION ]['agreement_notify'] = 'one@example.org,two@example.org';
$GLOBALS['mail'] = array();
$sent = WPCPM_Institutions::notify_managers( 'agreement-landed', $build );
ck( 'with the setting set, only the listed addresses are reached, through send_to()', array( $sent, array_map( function ( $s ) { return array( $s[0], $s[1] ); }, $GLOBALS['mail'] ) ), array( 2, array( array( 'send_to', 'one@example.org' ), array( 'send_to', 'two@example.org' ) ) ) );
ck( 'and the builder saw the bare address', $GLOBALS['mail'][0][3]['body'], 'Hello one@example.org' );

$GLOBALS['opts'][ WPCPM_Settings::OPTION ]['agreement_notify'] = 'max@example.test, stranger@example.org';
$GLOBALS['mail'] = array();
WPCPM_Institutions::notify_managers( 'agreement-landed', $build );
ck( 'a listed address that belongs to an account goes through send(), so it is built in their language', array_map( function ( $s ) { return array( $s[0], $s[1] ); }, $GLOBALS['mail'] ), array( array( 'send', 2 ), array( 'send_to', 'stranger@example.org' ) ) );

$GLOBALS['opts'][ WPCPM_Settings::OPTION ]['agreement_notify'] = 'not-an-address';
$GLOBALS['mail'] = array();
ck( 'junk in the setting sends nothing and does not fall back to every manager', array( WPCPM_Institutions::notify_managers( 'agreement-landed', $build ), $GLOBALS['mail'] ), array( 0, array() ) );

$GLOBALS['opts'][ WPCPM_Settings::OPTION ]['agreement_notify'] = '';
ck( 'a builder that is not callable sends nothing', WPCPM_Institutions::notify_managers( 'agreement-landed', 'nope' ), 0 );

/* ---- handlers ----------------------------------------------------------- */

echo "\n=== Handlers ===\n";

$module = new WPCPM_Institutions();

/**
 * Run a handler and report how it ended.
 *
 * @param callable $fn The handler.
 * @return string The exception message, or 'returned'.
 */
function outcome( callable $fn ) {
	$GLOBALS['referer'] = array();
	try { $fn(); return 'returned'; } catch ( Exception $e ) { return $e->getMessage(); }
}

$GLOBALS['caps'] = false;
ck( 'handle_sync without the capability dies 403 before any nonce is read', array( outcome( array( $module, 'handle_sync' ) ), $GLOBALS['referer'] ), array( 'wp_die: You do not have permission to manage the program.', array() ) );
ck( 'so does handle_cancel', array( outcome( array( $module, 'handle_cancel' ) ), $GLOBALS['referer'] ), array( 'wp_die: You do not have permission to manage the program.', array() ) );
ck( 'and handle_probe', array( outcome( array( $module, 'handle_probe' ) ), $GLOBALS['referer'] ), array( 'wp_die: You do not have permission to manage the program.', array() ) );
ck( 'handle_tick answers a 403 JSON error', array( outcome( array( $module, 'handle_tick' ) ), $GLOBALS['referer'] ), array( 'json_error:403', array() ) );

$GLOBALS['caps'] = true;
$GLOBALS['calls'] = array();
ck( 'handle_sync starts the sync and redirects to the screen', array( outcome( array( $module, 'handle_sync' ) ), $GLOBALS['referer'], in_array( array( 'WPCPM_Institutions_Sync::start' ), $GLOBALS['calls'], true ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions', array( 'wpcpm_institutions_sync' ), true ) );
// Read from the pending meta rather than through take(): take() memoises per request, and
// the renders above already consumed this channel for this process.
ck( 'leaving a one-shot flash for the screen to show', get_user_meta( 1, WPCPM_Flash::META ), array( 'institutions' => 'started' ) );
delete_user_meta( 1, WPCPM_Flash::META );

$GLOBALS['sync_refuses'] = true;
outcome( array( $module, 'handle_sync' ) );
ck( 'a refused start flashes error', get_user_meta( 1, WPCPM_Flash::META ), array( 'institutions' => 'error' ) );
$GLOBALS['sync_refuses'] = false;
delete_user_meta( 1, WPCPM_Flash::META );

$GLOBALS['calls'] = array();
ck( 'handle_cancel cancels and flashes', array( outcome( array( $module, 'handle_cancel' ) ), $GLOBALS['referer'], in_array( array( 'WPCPM_Institutions_Sync::cancel' ), $GLOBALS['calls'], true ), get_user_meta( 1, WPCPM_Flash::META ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions', array( 'wpcpm_institutions_cancel' ), true, array( 'institutions' => 'cancelled' ) ) );
delete_user_meta( 1, WPCPM_Flash::META );

$GLOBALS['head'] = array( 'response' => array( 'code' => 403 ) );
ck( 'handle_probe runs the probe and flashes', array( outcome( array( $module, 'handle_probe' ) ), $GLOBALS['referer'], get_option( 'wpcpm_private_probe' )['status'], get_user_meta( 1, WPCPM_Flash::META ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-institutions', array( 'wpcpm_institutions_probe' ), 403, array( 'institutions' => 'probed' ) ) );
delete_user_meta( 1, WPCPM_Flash::META );

$GLOBALS['head'] = new WP_Error( 'http_request_failed', 'no route' );
outcome( array( $module, 'handle_probe' ) );
ck( 'a probe that could not ask flashes probe-failed', get_user_meta( 1, WPCPM_Flash::META ), array( 'institutions' => 'probe-failed' ) );
delete_user_meta( 1, WPCPM_Flash::META );

$GLOBALS['sync_running'] = true;
$GLOBALS['calls'] = array();
ck( 'handle_tick advances a running sync and answers with progress', array( outcome( array( $module, 'handle_tick' ) ), $GLOBALS['referer'], in_array( array( 'WPCPM_Institutions_Sync::tick', 8 ), $GLOBALS['calls'], true ), $GLOBALS['json']['running'] ), array( 'json_success', array( 'wpcpm_institutions_tick' ), true, false ) );
$GLOBALS['sync_running'] = false;
$GLOBALS['calls'] = array();
outcome( array( $module, 'handle_tick' ) );
ck( 'and leaves an idle one alone', in_array( array( 'WPCPM_Institutions_Sync::tick', 8 ), $GLOBALS['calls'], true ), false );

// The running panel, with the attributes admin.js reads.
$GLOBALS['sync_progress'] = array( 'running' => true, 'label' => 'Reading institution records…', 'step_label' => 'Step 2 of 4', 'percent' => 40, 'detail' => '53 of 106', 'elapsed' => 75, 'stalled' => false );
$running = render_screen();
ck( 'a running sync draws the progress panel admin.js polls', array(
	false !== strpos( $running, '<div class="wpcpm-progress" data-wpcpm-progress data-action="wpcpm_institutions_tick" data-nonce="nonce" data-poll="3">' ),
	false !== strpos( $running, '<strong data-wpcpm-label>Reading institution records…</strong>' ),
	false !== strpos( $running, 'aria-valuenow="40"' ),
	false !== strpos( $running, '<span data-wpcpm-elapsed data-label="running for %s">running for 1:15</span>' ),
	false !== strpos( $running, '<input type="hidden" name="action" value="wpcpm_institutions_cancel" />' ),
	false === strpos( $running, 'value="wpcpm_institutions_sync"' ),
), array( true, true, true, true, true, true ) );
$GLOBALS['sync_progress'] = array( 'error' => 'Airtable said no' );
$idle = render_screen();
ck( 'an idle sync offers the start button and the last error', array(
	false !== strpos( $idle, '<input type="hidden" name="action" value="wpcpm_institutions_sync" />' ),
	false !== strpos( $idle, '<strong>Last sync error:</strong> Airtable said no' ),
	false !== strpos( $idle, 'No sync has run yet.' ),
), array( true, true, true ) );
$GLOBALS['sync_progress'] = array();
$GLOBALS['sync_last'] = $read_at;
ck( 'a completed run prints when', false !== strpos( render_screen(), 'Last completed ' . gmdate( 'Y-m-d H:i', $read_at ) . ' (4 hours ago).' ), true );

/* ---- lifecycle ---------------------------------------------------------- */

echo "\n=== Lifecycle ===\n";

$GLOBALS['calls'] = array();
$module->boot();
$hooks = array_map( function ( $c ) { return $c[1]; }, array_filter( $GLOBALS['calls'], function ( $c ) { return 'add_action' === $c[0]; } ) );
ck( 'boot() wires the four handlers', array_values( $hooks ), array(
	'admin_post_wpcpm_institutions_sync', 'admin_post_wpcpm_institutions_cancel', 'admin_post_wpcpm_institutions_probe', 'wp_ajax_wpcpm_institutions_tick',
) );
ck( 'boots the two post types and hands the cron events to the sync, as the Students module does', array_slice( $GLOBALS['calls'], 0, 3 ), array( array( 'WPCPM_Institution_Agreement::init' ), array( 'WPCPM_Institution_Audit::init' ), array( 'WPCPM_Institutions_Sync::register_cron' ) ) );

$GLOBALS['calls'] = array();
$GLOBALS['head']  = array( 'response' => array( 'code' => 403 ) );
$module->activate();
$names = array_map( function ( $c ) { return $c[0]; }, $GLOBALS['calls'] );
ck( 'activate() probes, refreshes the countries and schedules the sync', array(
	in_array( 'wp_remote_head', $names, true ),
	in_array( 'WPCPM_Countries::refresh', $names, true ),
	in_array( 'WPCPM_Institutions_Sync::activate', $names, true ),
), array( true, true, true ) );

$GLOBALS['opts'][ WPCPM_Settings::OPTION ]['api_token'] = '';
$GLOBALS['calls'] = array();
$module->activate();
ck( 'but does not touch Airtable when nothing is connected', in_array( 'WPCPM_Countries::refresh', array_map( function ( $c ) { return $c[0]; }, $GLOBALS['calls'] ), true ), false );
$GLOBALS['opts'][ WPCPM_Settings::OPTION ]['api_token'] = 'pat';

$GLOBALS['calls'] = array();
$module->deactivate();
ck( 'deactivate() delegates to the sync', $GLOBALS['calls'], array( array( 'WPCPM_Institutions_Sync::deactivate' ) ) );

$GLOBALS['calls'] = array();
$module->uninstall();
$names = array_map( function ( $c ) { return $c[0] . ( isset( $c[1] ) ? ':' . $c[1] : '' ); }, $GLOBALS['calls'] );
ck( 'uninstall() drops the three options, every delete_all() and the membership stamps', array(
	in_array( 'delete_option:wpcpm_institutions_index', $names, true ),
	in_array( 'delete_option:wpcpm_countries', $names, true ),
	in_array( 'delete_option:wpcpm_private_probe', $names, true ),
	in_array( 'WPCPM_Roster_Index::delete_all', $names, true ),
	in_array( 'WPCPM_Institution_Agreement::delete_all', $names, true ),
	in_array( 'WPCPM_Institution_Audit::delete_all', $names, true ),
	in_array( 'delete_option:wpcpm_institutions_state', $names, true ) && in_array( 'delete_option:wpcpm_institutions_report', $names, true ) && in_array( 'delete_option:wpcpm_institutions_last_sync', $names, true ) && in_array( 'delete_option:wpcpm_institutions_last_error', $names, true ) && in_array( 'delete_option:wpcpm_institutions_lock', $names, true ),
	in_array( 'delete_metadata:wpcpm_institution_record_id', $names, true ),
	in_array( 'delete_metadata:wpcpm_institution_record_id_was', $names, true ),
	in_array( 'delete_metadata:wpcpm_institution_profile', $names, true ),
), array( true, true, true, true, true, true, true, true, true, true ) );
ck( 'and leaves the signed files where they are', is_file( $base . 'agreements/2026/abc.pdf' ), true );

/* ---- clean up ----------------------------------------------------------- */

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $GLOBALS['uploads'], FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $item ) {
	$item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
}
rmdir( $GLOBALS['uploads'] );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
