<?php
/**
 * The Talks view of the Administrator Dashboard (Institutions off Airtable, release 2, Task 16): every school's talks in
 * the dashboards' frame, Add a lead, and a lead's page.
 *
 * What is pinned here and why:
 *
 * - **What is due (PROVISIONAL, owner question 5):** a follow-up on or before today only for a school whose talks go on
 *   (`WPCPM_Institution_Talks::DUE_STAGES`, the one list): a lead not closed, First Contact Made to Agreement Sent,
 *   Revisit Later and no stage. Confirmed, Student, Not Moving Forward, SPAM, a closed lead and an unknown stage never.
 * - **Mine and All:** Mine is the viewer's schools and the unassigned ones in their area (every unassigned one for a
 *   viewer with no area); the default is Mine for a viewer with an area, All for one without; the menu's count is
 *   Mine's.
 * - **The head** says the count once, in words, and the oldest; **Follow-ups due** is a table, oldest first; **by
 *   stage**, Lead to Confirmed open as headings with their count in words, No stage after them, Revisit Later, the
 *   closed stages and an unknown stage folded; no Student group; a closed lead in none; over 20 rows, "Show all N".
 * - **Find** finds a name without regard to case or Polish letters, at any stage, a closed lead tagged Closed.
 * - **Add a lead:** the fold's form on the parts; a post makes the lead (and its note) and lands on its page; each
 *   refusal over the form, linked to its box, the typing kept; a member's post and a post outside site mode answer 403
 *   with nothing written.
 * - **A lead's page:** the lead's name as the title and its Talks card; no back link; an unknown lead said so.
 * - **Layout:** the cases the harness draws, on the `administrator/talks` surface.
 *
 * Built on the Talks card suite's world (bin/test-institution-talks-card.php): the real talks, leads, notes, record,
 * index, agreement, assignment, audit, policy and the parts run here.
 *
 * Run from the plugin root:  php bin/test-institution-talks-view.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['posts']   = array();
$GLOBALS['pmeta']   = array();
$GLOBALS['opts']    = array();
$GLOBALS['umeta']   = array();
$GLOBALS['next']    = 100;
$GLOBALS['mails']   = array();
$GLOBALS['sent']    = array();
$GLOBALS['held']    = array();
$GLOBALS['users']   = array();
$GLOBALS['manage']  = array( 7, 11, 12 );
$GLOBALS['uid']     = 0;
$GLOBALS['members'] = array();
$GLOBALS['detach']  = array();
$GLOBALS['on_site'] = true;
$GLOBALS['remote']  = array();

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_author;
	public $post_content  = '';
	public $post_title    = '';
	public $post_date_gmt = '';
	public $post_date     = '';
	public $post_name     = '';
	public function __construct( array $a ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}
class WP_User {
	public $ID;
	public $display_name;
	public $user_email;
	public $user_login;
	public function __construct( $id = 0, $name = '', $email = '' ) {
		$this->ID           = (int) $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( ' ', '', $name ) );
	}
	public function exists() { return $this->ID > 0; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $one, $many, $n, $d = null ) { return 1 === (int) $n ? $one : $many; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url_raw( $s ) { return (string) $s; }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function selected( $a, $b = true, $echo = true ) { $out = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) { echo $out; } return $out; }
function wp_kses( $s, $allowed ) { return (string) $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_html_class( $c, $f = '' ) { $c = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); return '' === $c ? (string) $f : $c; }
function wp_check_invalid_utf8( $s ) { return mb_check_encoding( (string) $s, 'UTF-8' ) ? (string) $s : ''; }
function absint( $n ) { return abs( (int) $n ); }
function is_email( $e ) { return false !== filter_var( (string) $e, FILTER_VALIDATE_EMAIL ) ? $e : false; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : (int) $t ); }
function date_i18n( $f, $t = null ) { return gmdate( $f, null === $t ? time() : (int) $t ); }
function current_time( $type, $gmt = false ) { return gmdate( 'Y-m-d H:i:s' ); }
function get_date_from_gmt( $date, $format = 'Y-m-d H:i:s' ) { return $date; }
function get_post_time( $format, $gmt, $post, $translate = false ) { return 1763546400 + (int) $post->ID; }
function update_meta_cache( $type, $ids ) {}
function add_action( $hook, $cb = null, $p = 10, $n = 1 ) {}
function add_filter() {}
function register_post_type( $type, $args = array() ) {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v = '', $dep = '', $a = null ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { $had = array_key_exists( $k, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $k ] ); return $had; }
function wp_cache_delete( $k, $g = '' ) { return true; }
function wp_cache_get( $k, $g = '' ) { return false; }
function wp_cache_set( $k, $v, $g = '' ) { return true; }
function wp_next_scheduled( $hook, $args = array() ) { return false; }
function wp_schedule_single_event( $ts, $hook, $args = array() ) { return true; }
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
function get_user_by( $field, $value ) { return 'id' === $field ? get_userdata( (int) $value ) : false; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ (int) $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/specialchars.php';
function wp_insert_post( $a, $wp_error = false ) {
	$id            = $GLOBALS['next']++;
	$a             = stripslashes_deep( $a );
	$a['ID']       = $id;
	$a['post_date_gmt'] = isset( $a['post_date_gmt'] ) ? $a['post_date_gmt'] : gmdate( 'Y-m-d H:i:s' );
	$GLOBALS['posts'][ $id ] = new WP_Post( $a );
	return $id;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
// The commit of a note or a lead (Task 18 fix round 1): the one update that moves it from its unfinished draft.
function wp_update_post( $a, $wp_error = false ) { $id = (int) $a['ID']; if ( ! isset( $GLOBALS['posts'][ $id ] ) ) { return 0; } foreach ( stripslashes_deep( $a ) as $k => $v ) { $GLOBALS['posts'][ $id ]->$k = $v; } return $id; }
function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? ''; }
// A check that makes one meta key's write fail names it in `meta_fails` (the record's answers, for L1).
function update_post_meta( $id, $key, $value ) { if ( ( $GLOBALS['meta_fails'] ?? '' ) === $key ) { return false; } $GLOBALS['pmeta'][ (int) $id ][ $key ] = stripslashes_deep( $value ); return true; }
function wp_sprintf_l( $pattern, $args ) { $args = array_values( (array) $args ); if ( count( $args ) < 2 ) { return implode( '', $args ); } $last = array_pop( $args ); return implode( ', ', $args ) . ( count( $args ) > 1 ? ', and ' : ' and ' ) . $last; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ); return true; }
// WordPress's Trash, as core keeps it: the status the post had, then `trash` (the owner's wave, X4).
function wp_trash_post( $id ) { $p = $GLOBALS['posts'][ (int) $id ] ?? null; if ( ! $p || 'trash' === $p->post_status ) { return false; } $GLOBALS['pmeta'][ (int) $id ]['_wp_trash_meta_status'] = $p->post_status; $p->post_status = 'trash'; return $p; }
function wp_delete_post( $id, $force = false ) { $p = $GLOBALS['posts'][ (int) $id ] ?? false; unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return $p; }
// The leads suite's: the type, the statuses, one meta key and value, a meta query of key and value or EXISTS clauses,
// newest first by date, then by ID, numberposts and `ids`.
function get_posts( $args = array() ) {
	$ids = array_keys( $GLOBALS['posts'] );
	sort( $ids );
	if ( isset( $args['orderby']['date'] ) ) { $ids = array_reverse( $ids ); }
	$out = array();
	foreach ( $ids as $id ) {
		$post = $GLOBALS['posts'][ $id ];
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) { continue; }
		if ( isset( $args['post_status'] ) ) {
			if ( 'any' === $args['post_status'] ) {
				if ( in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) { continue; }
			} elseif ( ! in_array( $post->post_status, (array) $args['post_status'], true ) ) { continue; }
		}
		if ( isset( $args['meta_key'] ) ) {
			$have = get_post_meta( $id, $args['meta_key'], true );
			if ( '' === $have || (string) $have !== (string) ( $args['meta_value'] ?? '' ) ) { continue; }
		}
		$ok = true;
		foreach ( $args['meta_query'] ?? array() as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			$have = isset( $GLOBALS['pmeta'][ $id ][ $clause['key'] ] );
			if ( isset( $clause['compare'] ) && 'EXISTS' === $clause['compare'] ) { $ok = $ok && $have; continue; }
			$ok = $ok && $have && 0 === strcasecmp( (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ], (string) $clause['value'] );
		}
		if ( ! $ok ) { continue; }
		$out[] = ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? $id : $post;
		if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 && count( $out ) >= (int) $args['numberposts'] ) { break; }
	}
	return $out;
}
function wp_remote_request( $url, $args = array() ) { $GLOBALS['remote'][] = $url; return new WP_Error( 'blocked', 'No request leaves a suite.' ); }
function wp_remote_get( $url, $args = array() ) { return wp_remote_request( $url, $args ); }
function wp_remote_post( $url, $args = array() ) { return wp_remote_request( $url, $args ); }
function check_admin_referer( $action = -1, $name = '_wpnonce' ) {
	$GLOBALS['nonces'][] = array( $action, $name );
	if ( ( $_POST[ $name ] ?? '' ) !== 'nonce-' . $action ) { throw new Exception( 'nonce:' . $action ); }
	return true;
}
function wp_create_nonce( $a = -1 ) { return 'nonce-' . $a; }
function wp_referer_field( $display = true ) { $f = '<input type="hidden" name="_wp_http_referer" value="/institution-dashboard/">'; if ( $display ) { echo $f; } return $f; }
function is_admin() { return false; }
function wp_script_is( $h, $l = 'enqueued' ) { return false; }
function wp_register_script( $h, $src = '', $deps = array(), $ver = false, $footer = false ) {}
function wp_enqueue_script( $h = '' ) {}
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function add_query_arg( $args, $url_or_value, $url = null ) { if ( ! is_array( $args ) ) { $args = array( $args => $url_or_value ); } else { $url = $url_or_value; } return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect:' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die:' . $m ); }

/** The flash as the real one reads and writes it, without its memory of what a request took: many requests are played here. */
class WPCPM_Flash {
	const META = 'wpcpm_flash';
	private static function pending( $uid ) { return is_array( $GLOBALS['umeta'][ $uid ][ self::META ] ?? null ) ? $GLOBALS['umeta'][ $uid ][ self::META ] : array(); }
	public static function set( $channel, $value, $uid = 0 ) {
		$uid = $uid ? (int) $uid : get_current_user_id();
		if ( ! $uid ) { return; }
		$p                                    = self::pending( $uid );
		$p[ (string) $channel ]               = $value;
		$GLOBALS['umeta'][ $uid ][ self::META ] = $p;
	}
	public static function take( $channel, $uid = 0 ) {
		$uid = $uid ? (int) $uid : get_current_user_id();
		$p   = self::pending( $uid );
		if ( ! array_key_exists( (string) $channel, $p ) ) { return ''; }
		$v = $p[ (string) $channel ];
		unset( $p[ (string) $channel ] );
		$GLOBALS['umeta'][ $uid ][ self::META ] = $p;
		return $v;
	}
	public static function sweep( callable $stale, $uid = 0 ) {}
}
class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return is_scalar( $v ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $v ) ); }
}
class WPCPM_Test_Institutions {
	public static function is_test( $r ) { return false; }
	public static function label( $n, $f ) { return $f; }
}
/** The areas, as a check sets them: country record ID => the Administrator who holds it. */
class WPCPM_Institution_Areas {
	public static function administrator_for( $country ) { return (int) ( $GLOBALS['areas'][ (string) $country ] ?? 0 ); }
	public static function has_area( $user_id ) { return in_array( (int) $user_id, array_map( 'intval', $GLOBALS['areas'] ?? array() ), true ); }
}
function remove_accents( $text, $locale = '' ) {
	return strtr( (string) $text, array( 'Ł' => 'L', 'ł' => 'l', 'ó' => 'o', 'ź' => 'z', 'ż' => 'z', 'Ż' => 'Z', 'ą' => 'a', 'ę' => 'e', 'ś' => 's', 'ć' => 'c', 'ń' => 'n', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't' ) );
}
class WPCPM_Settings {
	public static $values = array();
	public static function get() { return self::$values; }
	public static function get_value( $key, $default = null ) {
		if ( 'institutions_on_site' === $key ) { return $GLOBALS['on_site']; }
		return array_key_exists( $key, self::$values ) ? self::$values[ $key ] : $default;
	}
}
const PL = 'recCTY0000000PL01';
const JP = 'recCTY0000000JP01';
const RO = 'recCTY0000000RO01';
class WPCPM_Countries {
	public static function options() { return array( JP => '日本', PL => 'Polska', RO => 'România' ); }
	public static function name_of( $id ) { return self::options()[ (string) $id ] ?? ''; }
}
/** The copy, recorded: every send, and the moment a check plants in it (inside the save's locks). */
class WPCPM_Institution_Copy {
	const COLUMNS = array( 'name' => 'Name', 'stage' => 'Current Stage', 'country' => 'Country', 'city' => 'City', 'website' => 'Website', 'contact_person' => 'Contact Person', 'contact_email' => 'Contact Email', 'confirmed_on' => 'Confirmed on', 'next_step' => 'Next Step', 'follow_up' => 'Follow-Up Date', 'last_contact' => 'Last Contact Date' );
	public static function send( $record, array $keys ) {
		if ( isset( $GLOBALS['during_send'] ) && is_callable( $GLOBALS['during_send'] ) ) { call_user_func( $GLOBALS['during_send'], $record ); }
		$GLOBALS['sent'][] = array( $record, $keys );
		return true;
	}
	public static function create( array $row ) { $GLOBALS['created'][] = $row; return $GLOBALS['answer'] ?? 'recNEW00000000001'; }
}
/** The lock, by name: one a check holds (`held`) is refused; every one taken is noted. */
class WPCPM_Lock {
	public static function take( $name, $ttl ) { $GLOBALS['taken'][] = $name; return empty( $GLOBALS['held'][ $name ] ) ? 'token' : ''; }
	public static function release( $name, $token ) { return true; }
	public static function until( $name ) { return 0; }
}
class WPCPM_Mail {
	public static function site_name() { return 'Education Directory'; }
	public static function send( $user_id, $id, $build ) {
		// The moment a check plants inside the save's locks: the assignee's email is sent under them.
		if ( isset( $GLOBALS['during_mail'] ) && is_callable( $GLOBALS['during_mail'] ) ) { call_user_func( $GLOBALS['during_mail'] ); }
		$GLOBALS['mails'][] = array( (int) $user_id, $id, $build() );
		return true;
	}
}
class WPCPM_Mail_Templates {
	public static function mail( $id, array $args ) { return $args; }
}
class WPCPM_Manager_Booking {
	public static function url_for( $id ) { return ''; }
}
class WPCPM_Settings_Choices {
	public static function manager_accounts() { $o = array(); foreach ( $GLOBALS['manage'] as $id ) { $o[ $id ] = $GLOBALS['users'][ $id ]->display_name; } asort( $o ); return $o; }
}
/** The members of each school (`$GLOBALS['members']`, user ID => record ID), and every detach. */
class WPCPM_Institution_Members {
	const LEVEL_TUTOR = 'tutor';
	public static function memberships_of( $user = null ) { $id = is_object( $user ) ? (int) $user->ID : (int) $user; return isset( $GLOBALS['members'][ $id ] ) ? array( $GLOBALS['members'][ $id ] ) : array(); }
	public static function institution_of( $user = null ) { $m = self::memberships_of( $user ); return $m ? $m[0] : ''; }
	public static function level_of( $user = null ) { return 'representative'; }
	public static function members_of( $record ) { $out = array(); foreach ( $GLOBALS['members'] as $id => $r ) { if ( $r === $record ) { $out[] = new WP_User( $id ); } } return $out; }
	public static function detach( $user_id, $reason, $actor ) { $GLOBALS['detach'][] = array( (int) $user_id, $reason, (int) $actor ); unset( $GLOBALS['members'][ (int) $user_id ] ); return true; }
}
require_once __DIR__ . '/stubs/institution-card-url.php';
class WPCPM_Institutions_Dashboard {
	public static $page = 'https://example.test/institution-dashboard/';
	public static function card_url( string $card, string $record = '', string $item = '', array $args = array() ): string { return wpcpm_test_institution_card_url( self::$page, $card, $record, $item, $args ); }
}
class WPCPM_Administrators_Dashboard {
	const VIEW_ARG = 'wpcpm_view';
	public static function view_url( $view = 'overview' ) { return 'https://example.test/administrator-dashboard/?wpcpm_view=' . $view; }
	public static function page_url() { return 'https://example.test/administrator-dashboard/'; }
}
class WPCPM_Institution_Request {
	const LOCK_TTL    = 300;
	const LOCK_PREFIX = 'wpcpm_req_lock_';
	public static function school_lock_name( $institution ) { return 'wpcpm_req_lock_details_' . trim( (string) $institution ); }
}
class WPCPM_Institution_Details_Card {
	public static function hidden_html( array $fields ) {
		$out = '';
		foreach ( $fields as $name => $value ) { $out .= sprintf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( (string) $name ), esc_attr( (string) $value ) ); }
		return $out;
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-record.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-assignment.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talk-notes.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talks.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-leads.php';
if ( is_file( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talks-view.php' ) ) {
	require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talks-view.php';
}
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-card.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once __DIR__ . '/stubs/guide-jobs.php';

$total = 0;
$fails = 0;
function ck( $label, $actual, $expected ) {
	global $total, $fails;
	++$total;
	if ( $actual === $expected ) { echo "  ok   {$label}\n"; return; }
	++$fails;
	echo "  FAIL {$label}\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
}
function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, $needle ); }
function plain( $html ) { return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( str_replace( array( '<wbr>', '<' ), array( '', ' <' ), (string) $html ) ), ENT_QUOTES ) ) ); }

if ( ! class_exists( 'WPCPM_Institution_Talks_View' ) || ! method_exists( 'WPCPM_Institution_Talks', 'due' ) || ! method_exists( 'WPCPM_Institution_Leads', 'handle_add' ) ) {
	ck( 'the Talks view, due() and Add a lead are built', false, true );
	printf( "\n%d FAILED (%d checks)\n", $fails, $total );
	exit( 1 );
}

/* ---- the world ---------------------------------------------------------- */

const S    = 'recEXA00000000001'; // Waiting on Reply, two members.
const LONG = 'recEXA00000000002'; // A 60-character name with a 40-character word, Info Sent.
const DONE = 'recEXA00000000003'; // Confirmed.

$GLOBALS['long_name'] = 'Universitatea Câmpiei Rîușițeni ' . str_repeat( 'Ș', 40 ) . ' X';

foreach ( array( 7 => 'Ana Admin', 11 => 'Piotr Admin', 12 => 'Mei Admin', 13 => 'Administratorka ' . str_repeat( 'Ż', 40 ) . ' Długa', 30 => 'Not Staff', 40 => 'Member One', 41 => 'Member Two', 42 => 'Member Three', 43 => 'Member Four' ) as $id => $name ) {
	$GLOBALS['users'][ $id ] = new WP_User( $id, $name, strtolower( str_replace( ' ', '.', $name ) ) . '@example.test' );
}

/** An index row, shaped as the index keeps one. */
function row( $id, $name, $stage, array $more = array() ) {
	return array_merge( WPCPM_Institutions_Index::empty_row(), array( 'record_id' => $id, 'name' => $name, 'stage' => $stage ), $more );
}

/** Start every scenario clean. */
function fresh() {
	$GLOBALS['posts']        = array();
	$GLOBALS['pmeta']        = array();
	$GLOBALS['umeta']        = array();
	$GLOBALS['mails']        = array();
	$GLOBALS['sent']         = array();
	$GLOBALS['held']         = array();
	$GLOBALS['taken']        = array();
	$GLOBALS['detach']       = array();
	$GLOBALS['nonces']       = array();
	$GLOBALS['created']      = array();
	$GLOBALS['during_send']  = null;
	$GLOBALS['during_mail']  = null;
	$GLOBALS['meta_fails']   = '';
	$GLOBALS['on_site']      = true;
	$GLOBALS['manage']       = array( 7, 11, 12 );
	$GLOBALS['members']      = array( 40 => S, 41 => S );
	$GLOBALS['uid']          = 7;
	$_POST                   = array();
	WPCPM_Settings::$values  = array(
		'institution_active_stages' => array( 'First Contact Made', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Call Scheduled', 'Agreement Sent', 'Confirmed', 'Student' ),
		'institution_on_inactive'   => 'revoke',
	);
	$GLOBALS['opts'] = array(
		'date_format'                         => 'j F Y',
		'time_format'                         => 'H:i',
		WPCPM_Institutions_Index::OPT_NAME    => array(
			'v'    => WPCPM_Institutions_Index::VERSION,
			'read' => 1763546400,
			'rows' => array(
				S    => row( S, 'Wyższa Szkoła Górnołąćka', 'Confirmed', array( 'country' => PL, 'country_name' => 'Polska', 'contact_person' => 'Kontakt Jeden', 'contact_email' => 'kontakt@example.test', 'agreement' => array_merge( WPCPM_Institutions_Index::empty_row()['agreement'], array( 'status' => 'Accepted', 'kind' => 'Template', 'accepted_on' => '2026-10-01' ) ) ) ),
				LONG => row( LONG, $GLOBALS['long_name'], 'Info Sent', array( 'country' => RO, 'country_name' => 'România' ) ),
				DONE => row( DONE, 'جامعة الواحة الخضراء المتخيلة', 'Confirmed', array( 'country' => JP, 'country_name' => '日本' ) ),
			),
		),
	);
	WPCPM_Institutions_Index::forget_cached();
	// The school's agreement stands, accepted on the site and in its row: its gate is open.
	$agreement = wp_insert_post( array( 'post_type' => WPCPM_Institution_Agreement::POST_TYPE, 'post_status' => WPCPM_Institution_Agreement::POST_STATUS ) );
	update_post_meta( $agreement, WPCPM_Institution_Agreement::META_INSTITUTION, S );
	update_post_meta( $agreement, WPCPM_Institution_Agreement::META_STATE, WPCPM_Institution_Agreement::STATE_ACCEPTED );
	update_post_meta( $agreement, WPCPM_Institution_Agreement::META_KIND, 'template' );
	update_post_meta( $agreement, WPCPM_Institution_Agreement::META_DECIDED_AT, '2026-10-01' );
	WPCPM_Institution_Record::set( S, array( WPCPM_Institution_Agreement::RECORD_DOCUMENT => 'https://drive.google.com/file/d/TALKSDOC001/view', 'next_step' => 'Send the brochure', 'follow_up' => '2026-11-01', 'estimated_students' => '40', 'assigned' => 11 ) );
	WPCPM_Institution_Agreement::rebuild( S, WPCPM_Institutions_Index::rebuild_block( WPCPM_Institutions_Index::row( S ), WPCPM_Institution_Agreement::record_document( S ) ) );
	$GLOBALS['sent']  = array();
	$GLOBALS['taken'] = array();
}

/* ---- the view's world ------------------------------------------------------ */

/** A day so many days from today, `Y-m-d`. */
function day_in( $days ) { return gmdate( 'Y-m-d', time() + (int) $days * DAY_IN_SECONDS ); }

const A1 = 'recEXA00000000011'; // First Contact Made, PL, Ana's, 10 days past.
const A2 = 'recEXA00000000012'; // Info Sent, PL, nobody's (Ana's area), 3 days past.
const A3 = 'recEXA00000000013'; // Info Sent, RO, Piotr's, ahead.
const A4 = 'recEXA00000000014'; // Agreement Sent, JP, Piotr's, 5 days past.
const A5 = 'recEXA00000000015'; // Confirmed, JP, Ana's, 40 days past: never due.
const A6 = 'recEXA00000000016'; // Confirmed, RO, nobody's, no date, an empty city.
const A7 = 'recEXA00000000017'; // Revisit Later, PL, Ana's, ahead.
const A8 = 'recEXA00000000018'; // No stage, no country, nobody's, 1 day past.
const A9 = 'recEXA00000000019'; // "Rejected", a stage the site does not know, Ana's, 2 days past: never due.
const A10 = 'recEXA00000000020'; // Student, Ana's, 7 days past: never due, no group.

$GLOBALS['long_school'] = 'Uniwersytet Przykładowy Imienia Bardzo Długiej Nazwy Testowej W'; // 60 characters... and more.

/** The nine schools, two leads (one closed) and the areas: Ana (7) holds Poland, Piotr (11) has no area. */
function world() {
	fresh();
	$GLOBALS['areas'] = array( PL => 7 );
	$rows             = array(
		A1  => row( A1, 'Łódzka Szkoła Przykładowa', 'First Contact Made', array( 'country' => PL, 'country_name' => 'Polska', 'city' => 'Łódź' ) ),
		A2  => row( A2, 'Wyższa Szkoła Górnołąćka', 'Info Sent', array( 'country' => PL, 'country_name' => 'Polska', 'city' => 'Kraków' ) ),
		A3  => row( A3, 'Universitatea Câmpiei Rîușițeni', 'Info Sent', array( 'country' => RO, 'country_name' => 'România', 'city' => 'Iași' ) ),
		A4  => row( A4, 'جامعة الواحة الخضراء المتخيلة', 'Agreement Sent', array( 'country' => JP, 'country_name' => '日本', 'city' => 'Ōsaka' ) ),
		A5  => row( A5, '架空山学園大学', 'Confirmed', array( 'country' => JP, 'country_name' => '日本', 'city' => 'Kyōto' ) ),
		A6  => row( A6, $GLOBALS['long_school'], 'Confirmed', array( 'country' => RO, 'country_name' => 'România' ) ),
		A7  => row( A7, 'Szkoła Odłożona', 'Revisit Later', array( 'country' => PL, 'country_name' => 'Polska', 'city' => 'Gdańsk' ) ),
		A8  => row( A8, 'Instytut Bez Etapu', '' ),
		A9  => row( A9, 'Hochschule Abgelehnt', 'Rejected', array( 'country' => PL, 'country_name' => 'Polska' ) ),
		A10 => row( A10, 'Szkoła Studencka', 'Student', array( 'country' => PL, 'country_name' => 'Polska' ) ),
	);
	$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $rows;
	WPCPM_Institutions_Index::forget_cached();
	$talks = array(
		A1  => array( 'assigned' => 7, 'follow_up' => day_in( -10 ), 'next_step' => 'Call the dean ' . str_repeat( 'Ś', 40 ) ),
		A2  => array( 'follow_up' => day_in( -3 ), 'next_step' => 'Send the brochure' ),
		A3  => array( 'assigned' => 11, 'follow_up' => day_in( 12 ), 'next_step' => 'Wait for the board' ),
		A4  => array( 'assigned' => 11, 'follow_up' => day_in( -5 ) ),
		A5  => array( 'assigned' => 7, 'follow_up' => day_in( -40 ) ),
		A7  => array( 'assigned' => 7, 'follow_up' => day_in( 30 ) ),
		A8  => array( 'follow_up' => day_in( -1 ), 'next_step' => 'Find out who runs it' ),
		A9  => array( 'assigned' => 7, 'follow_up' => day_in( -2 ) ),
		A10 => array( 'assigned' => 7, 'follow_up' => day_in( -7 ) ),
	);
	foreach ( $talks as $id => $values ) {
		WPCPM_Institution_Record::set( $id, $values );
	}
	$GLOBALS['lead_open']   = WPCPM_Institution_Leads::add( array( 'name' => 'Akademia Prowadzona', 'country' => PL, 'city' => 'Poznań', 'assigned' => 7, 'follow_up' => day_in( -20 ), 'next_step' => 'Write to the rector' ), 7, false );
	$GLOBALS['lead_closed'] = WPCPM_Institution_Leads::add( array( 'name' => 'Zamknięta Szkoła', 'country' => PL, 'assigned' => 7, 'follow_up' => day_in( -9 ) ), 7, false );
	WPCPM_Institution_Leads::close( $GLOBALS['lead_closed'], 7 );
	$_GET = array();
}

/** The view, drawn for one reader with these GET arguments. */
function view( $uid, array $get = array() ) {
	$GLOBALS['uid'] = (int) $uid;
	$_GET           = $get;
	// Each drawing is a request of its own: the facts read once per request are read again.
	WPCPM_Institution_Talks_View::forget();
	WPCPM_UI::enter( 'administrator' );
	ob_start();
	WPCPM_Institution_Talks_View::render( $GLOBALS['users'][ (int) $uid ], array( 'page_name' => '<h1 class="wpcpm-head__eyebrow">Administrator Dashboard</h1>' ) );
	$html = (string) ob_get_clean();
	WPCPM_UI::leave();
	$_GET = array();
	return '<div class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--talks wpcpm-ui-on">' . $html . '</div>';
}
function flash_of( $uid ) { return $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash']['institution_talks'] ?? null; }
function keys_of( array $rows ) { return array_map( static function ( $r ) { return $r['key']; }, $rows ); }
/** The stage headings and folds, in order, as the reader reads them. */
function groups_of( $html ) {
	preg_match_all( '#<h2 class="wpcpm-ui-h" id="wpcpm-talks-stage-[a-z0-9-]+">([^<]*)</h2>|<section class="wpcpm-ui-fold-card" id="wpcpm-talks-stage-[a-z0-9-]+"><details class="wpcpm-ui-fold"( open)?><summary class="wpcpm-ui-fold__head"><h3 class="wpcpm-ui-fold__title">([^<]*)</h3><span class="wpcpm-ui-fold__phrase">([^<]*)</span>#', $html, $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $g ) {
		$out[] = '' !== $g[1] ? html_entity_decode( $g[1], ENT_QUOTES ) : 'folded ' . html_entity_decode( $g[3], ENT_QUOTES ) . ': ' . $g[4];
	}
	return $out;
}
/** The names of the due table's rows, in order. */
function due_names( $html ) {
	$table = (string) strstr( (string) strstr( $html, 'aria-labelledby="wpcpm-talks-due-heading"' ), '</table>', true );
	preg_match_all( '#<th scope="row"[^>]*><a href="[^"]*">(.*?)</a></th>#', $table, $m );
	return array_map( static function ( $n ) { return html_entity_decode( str_replace( '<wbr>', '', $n ), ENT_QUOTES ); }, $m[1] );
}
/** A list row's markup, by the school's name. */
function row_of( $html, $name ) {
	foreach ( array_slice( explode( '<li class="wpcpm-ui-list__row', $html ), 1 ) as $piece ) {
		if ( has( str_replace( '<wbr>', '', $piece ), '>' . $name . '</a>' ) ) {
			return (string) strstr( $piece, '</li>', true );
		}
	}
	return '';
}
function add_post( $uid, array $fields ) {
	$GLOBALS['uid'] = (int) $uid;
	$_POST          = array_merge( array( 'action' => 'wpcpm_talks_add_lead', '_wpnonce' => 'nonce-wpcpm_talks_add_lead' ), $fields );
	try {
		WPCPM_Institution_Leads::handle_add();
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
	return '';
}

$view_url = 'https://example.test/administrator-dashboard/?wpcpm_view=talks';
$refusal  = 'wp_die:' . WPCPM_Institution_Policy::refusal()->get_error_message();
$cases    = array();

/* ---- what is due ------------------------------------------------------------ */

echo "=== What is due (PROVISIONAL, owner question 5) ===\n";

ck( 'the one list of stages that count, in WPCPM_Institution_Talks::DUE_STAGES', WPCPM_Institution_Talks::DUE_STAGES, array( '', 'Lead', 'First Contact Made', 'Call Scheduled', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Agreement Sent', 'Revisit Later' ) );
$counts = array();
foreach ( array( '', 'Lead', 'First Contact Made', 'Agreement Sent', 'Revisit Later', 'Confirmed', 'Student', 'Not Moving Forward', 'SPAM', 'Rejected' ) as $stage ) {
	$counts[ '' === $stage ? '(none)' : $stage ] = WPCPM_Institution_Talks::counts_as_due( array( 'stage' => $stage, 'closed' => false ) );
}
ck( 'counts_as_due(): the talks that go on count; Confirmed, Student, the two closed stages and an unknown one never', $counts, array( '(none)' => true, 'Lead' => true, 'First Contact Made' => true, 'Agreement Sent' => true, 'Revisit Later' => true, 'Confirmed' => false, 'Student' => false, 'Not Moving Forward' => false, 'SPAM' => false, 'Rejected' => false ) );
ck( 'a closed lead never counts', WPCPM_Institution_Talks::counts_as_due( array( 'stage' => 'Lead', 'closed' => true ) ), false );
world();
$all = WPCPM_Institution_Talks::all_facts();
ck( 'due(): every follow-up on or before today of the talks that go on, oldest first', keys_of( WPCPM_Institution_Talks::due( $all, day_in( 0 ) ) ), array( $GLOBALS['lead_open'], A1, A4, A2, A8 ) );
ck( 'a follow-up today is due; one tomorrow is not', array( count( WPCPM_Institution_Talks::due( array( array_merge( $all[ A3 ], array( 'follow_up' => day_in( 0 ) ) ) ), day_in( 0 ) ) ), count( WPCPM_Institution_Talks::due( array( array_merge( $all[ A3 ], array( 'follow_up' => day_in( 1 ) ) ) ), day_in( 0 ) ) ) ), array( 1, 0 ) );
ck( 'a Revisit Later school past its date is due', count( WPCPM_Institution_Talks::due( array( array_merge( $all[ A7 ], array( 'follow_up' => day_in( -1 ) ) ) ), day_in( 0 ) ) ), 1 );

/* ---- Mine and All ------------------------------------------------------------- */

echo "\n=== Mine and All (fix round 1, V2 and V3) ===\n";

ck( 'Mine for Ana (Poland): her schools, the unassigned ones in Poland, and the unassigned ones in nobody\'s area (no country; Romania); no closed lead', keys_of( WPCPM_Institution_Talks_View::rows( $all, 'mine', 7, '' ) ), array( A5, $GLOBALS['lead_open'], A1, A10, A2, A9, A8, A7, A6 ) );
ck( 'Mine for Piotr (no area): his schools and the unassigned ones in nobody\'s area, never Ana\'s unassigned Polish school', keys_of( WPCPM_Institution_Talks_View::rows( $all, 'mine', 11, '' ) ), array( A4, A8, A3, A6 ) );
ck( 'All: every school but the closed lead', count( WPCPM_Institution_Talks_View::rows( $all, 'all', 7, '' ) ), 11 );
ck( 'the menu\'s count is Mine\'s due, nobody\'s school in every one: Ana 4, Piotr 2, Mei 1', array( WPCPM_Institution_Talks_View::due_count( 7 ), WPCPM_Institution_Talks_View::due_count( 11 ), WPCPM_Institution_Talks_View::due_count( 12 ) ), array( 4, 2, 1 ) );
WPCPM_Institution_Record::set( A3, array( 'assigned' => 30 ) );
WPCPM_Institution_Talks_View::forget();
$all = WPCPM_Institution_Talks::all_facts();
ck( 'a school assigned to an account that no longer manages the program is nobody\'s: in every Mine (Mei\'s too), tagged Not assigned', array( in_array( A3, keys_of( WPCPM_Institution_Talks_View::rows( $all, 'mine', 12, '' ) ), true ), in_array( A3, keys_of( WPCPM_Institution_Talks_View::rows( $all, 'mine', 7, '' ) ), true ), has( row_of( view( 12 ), 'Universitatea Câmpiei Rîușițeni' ), '<span class="wpcpm-status">Not assigned</span>' ), has( plain( row_of( view( 12 ), 'Universitatea Câmpiei Rîușițeni' ) ), 'Not Staff' ) ), array( true, true, true, false ) );
world();
$GLOBALS['on_site'] = false;
ck( 'outside site mode the view is not shown and the count is 0', array( WPCPM_Institution_Talks_View::shown(), WPCPM_Institution_Talks_View::due_count( 7 ) ), array( false, 0 ) );
$GLOBALS['on_site'] = true;
WPCPM_Institution_Talks_View::forget();
$reads = WPCPM_Institution_Talks_View::reads();
WPCPM_Institution_Talks_View::due_count( 7 );
$_GET = array();
ob_start();
WPCPM_Institution_Talks_View::render( $GLOBALS['users'][7] );
ob_end_clean();
ck( 'one request reads the talks once: the menu\'s count and the view share them (fix round 1, G2)', WPCPM_Institution_Talks_View::reads() - $reads, 1 );

/* ---- the view ----------------------------------------------------------------- */

echo "\n=== The view ===\n";

$ana  = view( 7 );
$text = plain( $ana );
ck( 'Ana opens on Mine: the head says the count once, in words, and the oldest', array( has( $ana, '<p class="wpcpm-head__title">4 follow-ups are due</p>' ), has( $text, 'The oldest, Akademia Prowadzona, has been due since ' . WPCPM_Dates::day( day_in( -20 ) ) . '.' ), substr_count( $text, '4 follow-ups' ) ), array( true, true, 1 ) );
ck( 'Mine or All is one small control in the head\'s first row: the shown one in the muted label, the other a quiet link (V2)', has( $ana, '<div class="wpcpm-ui-head-row"><h1 class="wpcpm-head__eyebrow">Administrator Dashboard</h1><div class="wpcpm-ui-viewing"><span class="wpcpm-ui-viewing__label">Showing your schools</span><a class="wpcpm-btn wpcpm-btn--quiet" href="' . $view_url . '&amp;wpcpm_talks_who=all">Show all schools</a></div></div>' ), true );
ck( 'the head\'s one action is Add a lead, secondary, a link to the fold (V6)', array( has( $ana, '<div class="wpcpm-head__actions"><a class="wpcpm-btn wpcpm-btn--secondary" href="#wpcpm-add-lead" data-wpcpm-focus-card>Add a lead</a></div></div></header>' ), substr_count( (string) strstr( $ana, '</header>', true ), '<a class="wpcpm-btn' ) ), array( true, 2 ) );
ck( 'Follow-ups due: a table, oldest first, the school, the next step and the day', array( due_names( $ana ), has( $ana, '<th scope="col" role="columnheader" class="wpcpm-ui-cell--text">School</th>' ), has( $ana, '<span class="wpcpm-ui-whole">' . WPCPM_Dates::day( day_in( -20 ) ) . '</span>' ) ), array( array( 'Akademia Prowadzona', 'Łódzka Szkoła Przykładowa', 'Wyższa Szkoła Górnołąćka', 'Instytut Bez Etapu' ), true, true ) );
ck( 'a lead in the table links to its page on this view; a school to its Talks card', array( has( $ana, 'href="' . $view_url . '&amp;wpcpm_lead=' . $GLOBALS['lead_open'] . '"' ), has( $ana, 'wpcpm_institution_view=' . A1 . '#wpcpm-talks' ) ), array( true, true ) );
ck( 'by stage, Mine: no "By stage" heading; Lead to Confirmed open, each an h2 with its count in words; No stage; Revisit Later and the unknown stage folded, last; no Student group (V4)', array( groups_of( $ana ), has( $ana, 'By stage' ), has( $ana, '<h2 class="wpcpm-ui-h" id="wpcpm-talks-due-heading">Follow-ups due</h2>' ) ), array( array( 'Lead: 1 school', 'First Contact Made: 1 school', 'Info Sent: 1 school', 'Confirmed: 2 schools', 'No stage: 1 school', 'folded Revisit Later: 1 school', 'folded Rejected: 1 school' ), false, true ) );
ck( 'no two headings stand in a row', 1 === preg_match( '#</h[23]>\s*<h[23]#', $ana ), false );
$piotr = view( 11 );
ck( 'Piotr (no area) opens on Mine too, his two due said in the head as in the menu (V2)', array( has( $piotr, '<p class="wpcpm-head__title">2 follow-ups are due</p>' ), has( $piotr, 'Showing your schools' ) ), array( true, true ) );
$piotr_all = view( 11, array( 'wpcpm_talks_who' => 'all' ) );
ck( 'All: five due, and every group', array( has( $piotr_all, '<p class="wpcpm-head__title">5 follow-ups are due</p>' ), has( $piotr_all, '<span class="wpcpm-ui-viewing__label">Showing all schools</span><a class="wpcpm-btn wpcpm-btn--quiet" href="' . $view_url . '&amp;wpcpm_talks_who=mine">Show your schools</a>' ), groups_of( $piotr_all ) ), array( true, true, array( 'Lead: 1 school', 'First Contact Made: 1 school', 'Info Sent: 2 schools', 'Agreement Sent: 1 school', 'Confirmed: 2 schools', 'No stage: 1 school', 'folded Revisit Later: 1 school', 'folded Rejected: 1 school' ) ) );
ck( 'a row: the name (its talks\' page), no red tag, the place, the Administrator, the next step (breakable) and the day whole (V5)', array( has( row_of( $ana, 'Łódzka Szkoła Przykładowa' ), 'wpcpm-status' ), has( plain( row_of( $ana, 'Łódzka Szkoła Przykładowa' ) ), 'Łódź, Polska' ), has( plain( row_of( $ana, 'Łódzka Szkoła Przykładowa' ) ), 'Ana Admin' ), has( row_of( $ana, 'Łódzka Szkoła Przykładowa' ), '<wbr>' ), has( row_of( $ana, 'Łódzka Szkoła Przykładowa' ), '<span class="wpcpm-ui-whole">Follow up on ' . WPCPM_Dates::day( day_in( -10 ) ) . '</span>' ) ), array( false, true, true, true, true ) );
ck( 'red once: no Past due anywhere in the view', array( has( $ana, 'Past due' ), has( $piotr_all, 'Past due' ), has( $ana, 'wpcpm-status--red' ) ), array( false, false, false ) );
ck( 'an unassigned school is tagged Not assigned, neutral, and says it once', array( has( row_of( $ana, 'Wyższa Szkoła Górnołąćka' ), '<span class="wpcpm-status">Not assigned</span>' ), substr_count( plain( row_of( $ana, 'Wyższa Szkoła Górnołąćka' ) ), 'Not assigned' ), substr_count( plain( row_of( $ana, 'Instytut Bez Etapu' ) ), 'Not assigned' ) ), array( true, 1, 1 ) );
ck( 'the closed lead and the Student school are in no group', array( has( $piotr_all, 'Zamknięta Szkoła' ), has( $piotr_all, 'Szkoła Studencka' ) ), array( false, false ) );
ck( 'no colored running text, no "Back to Talks", no physical side, no Airtable', array( 1 === preg_match( '#style="color#', $piotr_all ), has( $piotr_all, 'Back to Talks' ), 1 === preg_match( '/(margin|padding)-(left|right)/', $piotr_all ), has( $piotr_all, 'Airtable' ) ), array( false, false, false, false ) );
$cases['the view, Mine, nobody\'s schools in it'] = $ana;
$cases['the view, All'] = $piotr_all;

world();
foreach ( array( A1, A2, A4, A8, A9, A10, A5 ) as $id ) { WPCPM_Institution_Record::set( $id, array( 'follow_up' => day_in( 5 ) ) ); }
WPCPM_Institution_Leads::update( $GLOBALS['lead_open'], array( 'follow_up' => '' ), 7 );
$quiet = view( 7 );
ck( 'with none due: "No follow-up is due", "No school has a follow-up date today or earlier.", and no table (V8)', array( has( $quiet, '<p class="wpcpm-head__title">No follow-up is due</p>' ), has( $quiet, '<p class="wpcpm-head__lede">No school has a follow-up date today or earlier.</p>' ), has( $quiet, 'Follow-ups due' ), has( $quiet, '>Add a lead</a></div></div></header>' ) ), array( true, true, false, true ) );
$cases['nothing due'] = $quiet;

world();
$GLOBALS['areas'] = array( PL => 7, RO => 11 );
$rows             = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'];
unset( $rows[ A8 ] );
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $rows;
WPCPM_Institutions_Index::forget_cached();
$mei = view( 12 );
ck( 'none of mine: "You have no school in the talks yet." and the switch to all schools (V8)', array( has( $mei, '<p class="wpcpm-ui-list__empty">You have no school in the talks yet.</p>' ), has( $mei, '>Show all schools</a>' ), has( $mei, '<p class="wpcpm-head__title">No follow-up is due</p>' ) ), array( true, true, true ) );
$cases['none of mine'] = $mei;
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = array();
WPCPM_Institutions_Index::forget_cached();
foreach ( WPCPM_Institution_Leads::all() as $key => $lead ) { wp_delete_post( (int) substr( $key, 5 ), true ); }
$empty = view( 7, array( 'wpcpm_talks_who' => 'all' ) );
ck( 'no school at all: "No school is in the talks yet."', has( $empty, '<p class="wpcpm-ui-list__empty">No school is in the talks yet.</p>' ), true );
$cases['no school at all'] = $empty;

/* ---- Find ----------------------------------------------------------------------- */

echo "\n=== Find (V1, V5) ===\n";

world();
$found = view( 7, array( 'wpcpm_talks_find' => 'lodz' ) );
ck( 'Find "lodz" finds Łódzka Szkoła Przykładowa without regard to case or Polish letters, in all the talks, its stage its first fact as text', array( has( $found, '<h2 class="wpcpm-ui-h">1 school in all the talks matches that name</h2>' ), has( row_of( $found, 'Łódzka Szkoła Przykładowa' ), '<span class="wpcpm-ui-list__facts"><span class="wpcpm-ui-item__fact">First Contact Made</span>' ), has( row_of( $found, 'Łódzka Szkoła Przykładowa' ), 'wpcpm-status' ), groups_of( $found ) ), array( true, true, false, array() ) );
ck( 'Find is a GET form: the view, the switch and one 320px search box in a row of fields, then one secondary Find, as the Students view\'s Show, and a quiet Clear beside it while a query stands (fix round 2, F1)', array( has( $found, '<input type="hidden" name="wpcpm_view" value="talks" />' ), has( $found, '<div class="wpcpm-ui-fields"><div class="wpcpm-ui-field' ), has( $found, 'type="search"' ), has( $found, 'for="wpcpm-talks-find">Find a school</label>' ), has( $found, '</div><div class="wpcpm-ui-actions"><button type="submit" class="wpcpm-btn wpcpm-btn--secondary">Find</button><a class="wpcpm-btn wpcpm-btn--quiet" href="' ), has( $found, '>Clear</a>' ), has( view( 7 ), '>Clear</a>' ) ), array( true, true, true, true, true, true, false ) );
ck( 'Find stands directly over what it filters', 1 === preg_match( '#role="search">.*?</form><section class="wpcpm-talks-view__found">#s', $found ), true );
$two = view( 7, array( 'wpcpm_talks_find' => 'szkoła' ) );
ck( 'more than one: "N schools in all the talks match that name", whatever Mine says (Piotr finds Ana\'s)', array( 1 === preg_match( '#<h2 class="wpcpm-ui-h">\d+ schools in all the talks match that name</h2>#', $two ), has( view( 11, array( 'wpcpm_talks_find' => 'lodz' ) ), 'Łódzka' ) ), array( true, true ) );
$closed = view( 7, array( 'wpcpm_talks_find' => 'ZAMK' ) );
ck( 'Find finds a closed lead, tagged Closed, its stage Lead its first fact; and a Student school at its stage', array( has( row_of( $closed, 'Zamknięta Szkoła' ), '<span class="wpcpm-status">Closed</span>' ), has( row_of( $closed, 'Zamknięta Szkoła' ), '<span class="wpcpm-ui-item__fact">Lead</span>' ), has( row_of( view( 7, array( 'wpcpm_talks_find' => 'studencka' ) ), 'Szkoła Studencka' ), '<span class="wpcpm-ui-item__fact">Student</span>' ) ), array( true, true, true ) );
$none = view( 7, array( 'wpcpm_talks_find' => 'nothing like it' ) );
ck( 'no match says "No school matches that name."', array( has( $none, '<p class="wpcpm-ui-list__empty">No school matches that name.</p>' ), has( $none, 'Follow-ups due' ) ), array( true, false ) );
$cases['Find, an ordinary match'] = $found;
$cases['Find with no match'] = $none;
$cases['Find, a closed lead'] = $closed;

/* ---- long groups ---------------------------------------------------------------- */

echo "\n=== A group over 20 ===\n";

world();
$rows = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'];
for ( $n = 1; $n <= 23; $n++ ) {
	$id          = sprintf( 'recEXA0000000%04d', 100 + $n );
	$rows[ $id ] = row( $id, sprintf( 'Szkoła Potwierdzona %02d', $n ), 'Confirmed', array( 'country' => RO, 'country_name' => 'România' ) );
}
$GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ]['rows'] = $rows;
WPCPM_Institutions_Index::forget_cached();
$long = view( 11, array( 'wpcpm_talks_who' => 'all' ) );
preg_match( '#id="wpcpm-talks-stage-confirmed">.*?</ul>#s', $long, $confirmed );
ck( 'Confirmed holds 25: 20 rows and one quiet "Show all 25" that opens the group whole', array( substr_count( $confirmed[0] ?? '', '<li class="wpcpm-ui-list__row' ), has( $long, '<a class="wpcpm-btn wpcpm-btn--quiet" href="' . $view_url . '&amp;wpcpm_talks_who=all&amp;wpcpm_talks_whole=confirmed#wpcpm-talks-stage-confirmed">Show all 25</a>' ) ), array( 20, true ) );
$whole = view( 11, array( 'wpcpm_talks_who' => 'all', 'wpcpm_talks_whole' => 'confirmed' ) );
preg_match( '#id="wpcpm-talks-stage-confirmed">.*?</ul>#s', $whole, $confirmed );
ck( 'asked whole, it shows all 25 and no link', array( substr_count( $confirmed[0] ?? '', '<li class="wpcpm-ui-list__row' ), has( $whole, 'Show all 25' ) ), array( 25, false ) );
$cases['a group of 25, Show all'] = $long;
$cases['the group shown whole'] = $whole;

/* ---- Add a lead ------------------------------------------------------------------ */

echo "\n=== Add a lead ===\n";

world();
// G7b: the fold Add a lead carries How to do this as the Administrator Dashboard's other cards do: in the fold head's help
// slot, its phone copy the body's first line, to the job Add a lead; a site with no published guide draws none. The cases
// of this part, the fold closed, open and with a refusal, are drawn with a published guide.
$no_guide                    = view( 7 );
$GLOBALS['wpcpm_guide_page'] = new WP_Post( array( 'ID' => 0, 'post_status' => 'publish', 'post_name' => 'administrator-guide' ) );
WPCPM_Guide_Jobs::forget();
$page = view( 7 );
preg_match( '#<section class="wpcpm-ui-fold-card" id="wpcpm-add-lead">.*?</section>#s', $page, $help_fold );
$help_fold = $help_fold[0] ?? '';
ck( 'G7b: Add a lead carries one help link in its head\'s slot and its phone copy first in the body, to the job Add a lead; nothing else on the view, and none without a guide', array(
	substr_count( $help_fold, 'href="https://example.test/administrator-guide/#add-a-lead"' ),
	1 === preg_match( '#</details><p class="wpcpm-ui-fold__help"><a [^>]*href="https://example.test/administrator-guide/\#add-a-lead"#', $help_fold ),
	1 === preg_match( '#<div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-fold__help wpcpm-ui-fold__help--phone"><a [^>]*href="https://example.test/administrator-guide/\#add-a-lead"#', $help_fold ),
	substr_count( $page, 'administrator-guide/#' ),
	substr_count( $no_guide, 'administrator-guide/#' ),
), array( 2, true, true, 2, 0 ) );
preg_match( '#<section class="wpcpm-ui-fold-card" id="wpcpm-add-lead">.*?</section>#s', $page, $fold );
$fold = $fold[0] ?? '';
ck( 'Add a lead is a fold, folded, the view\'s last block, after the folded stages (V6): labels above the boxes, the one country combobox, one primary "Add the lead"', array( '' !== $fold, has( $fold, '<details class="wpcpm-ui-fold" open' ), strpos( $page, 'id="wpcpm-talks-stage-rejected"' ) < strpos( $page, 'id="wpcpm-add-lead"' ), substr( rtrim( $page ), -strlen( '</section></div>' ) ) === '</section></div>', substr_count( $fold, 'class="wpcpm-btn"' ), has( $fold, '>Add the lead</button>' ), has( $fold, 'name="country"' ) && has( $fold, 'Type to find a country' ) ), array( true, false, true, true, 1, true, true ) );
preg_match_all( '#<label class="wpcpm-ui-field__label"(?: id="[^"]*")? for="wpcpm-add-lead-([a-z-]+)">([^<]*)#', $fold, $labels );
ck( 'its fields in order: Name (required), City, Country, Contact person, Contact\'s email (V6), Help desk link, Next step, Follow up on, Note', array_map( static function ( $l ) { return trim( html_entity_decode( $l, ENT_QUOTES ) ); }, $labels[2] ), array( 'Name', 'City', 'Country', 'Contact person', 'Contact\'s email', 'Help desk link', 'Next step', 'Follow up on', 'Note' ) );
ck( 'Name carries the Required mark', 1 === preg_match( '#for="wpcpm-add-lead-name">Name <span class="wpcpm-ui-field__need">Required</span>#', $fold ), true );
$cases['Add a lead open, at the end'] = str_replace( '<section class="wpcpm-ui-fold-card" id="wpcpm-add-lead"><details class="wpcpm-ui-fold"', '<section class="wpcpm-ui-fold-card" id="wpcpm-add-lead"><details class="wpcpm-ui-fold" open', $page );

$leads_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-leads.php' );
ck( 'handle_add() decides through the policy with ACT_TALKS, as the card\'s handlers do (G3)', 1 === preg_match( '/function handle_add\(\) \{.*?WPCPM_Institution_Policy::decide\( WPCPM_Institution_Policy::ACT_TALKS/s', $leads_src ) && false === strpos( (string) strstr( (string) strstr( $leads_src, 'function handle_add()' ), 'function added_just_now', true ), 'current_user_can' ), true );
$before = array( $GLOBALS['posts'], $GLOBALS['pmeta'] );
ck( 'a member\'s post with a valid nonce answers 403, nothing written', array( add_post( 40, array( 'name' => 'Szkoła Członka' ) ), $GLOBALS['posts'] === $before[0], $GLOBALS['pmeta'] === $before[1] ), array( $refusal, true, true ) );
$GLOBALS['on_site'] = false;
ck( 'an Administrator\'s post outside site mode answers 403, nothing written', array( add_post( 7, array( 'name' => 'Szkoła Poza Trybem' ) ), $GLOBALS['posts'] === $before[0] ), array( $refusal, true ) );
$GLOBALS['on_site'] = true;
ck( 'a post without the nonce is refused by the nonce', add_post( 7, array( '_wpnonce' => 'other', 'name' => 'X' ) ), 'nonce:wpcpm_talks_add_lead' );

WPCPM_Institution_Talks_View::facts();
$went = add_post( 7, array( 'name' => 'Nowa Szkoła Testowa', 'city' => 'Lublin', 'country' => PL, 'next_step' => 'Send the letter', 'follow_up' => day_in( 4 ), 'note' => 'Met them at the fair.' ) );
$new  = WPCPM_Institution_Leads::name_taken( 'Nowa Szkoła Testowa' );
ck( 'F2: after the add the request has forgotten the view\'s memo, so a drawing in it holds the new lead', array_key_exists( $new, WPCPM_Institution_Talks_View::facts() ), true );
$talks_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talks.php' );
ck( 'F2: every outcome of the card\'s handlers (save, note, close, reopen) and of an add lands through finish(), which forgets the memo', 1 === preg_match( '/private static function finish\(.*?WPCPM_Institution_Talks_View::forget\(\);.*?wp_safe_redirect/s', $talks_src ), true );
ck( 'a post makes the lead, assigned by its area, with its first note, and lands on its page at the card', array( $went, WPCPM_Institution_Leads::get( $new )['city'] ?? null, WPCPM_Institution_Leads::get( $new )['assigned'] ?? null, array_column( WPCPM_Institution_Talk_Notes::for_key( $new ), 'text' ) ), array( 'redirect:' . $view_url . '&wpcpm_lead=' . $new . '#wpcpm-talks', 'Lublin', 7, array( 'Met them at the fair.' ) ) );
$count = count( WPCPM_Institution_Leads::all() );
$again = add_post( 7, array( 'name' => 'Nowa Szkoła Testowa', 'note' => 'Met them at the fair.' ) );
ck( 'a second press by the same account a moment later lands on that lead with "The lead is added." and makes nothing (G4)', array( $again, count( WPCPM_Institution_Leads::all() ), flash_of( 7 )['status'] ?? null, count( WPCPM_Institution_Talk_Notes::for_key( $new ) ) ), array( 'redirect:' . $view_url . '&wpcpm_lead=' . $new . '#wpcpm-talks', $count, 'lead-added', 1 ) );
view( 7, array( 'wpcpm_lead' => $new ) );
ck( 'the same name pressed by another Administrator is refused as taken', array( add_post( 11, array( 'name' => 'Nowa Szkoła Testowa' ) ), WPCPM_Flash::take( WPCPM_Institution_Leads::ADD_FLASH, 11 )['text'] ?? null ), array( 'redirect:' . $view_url . '#wpcpm-add-lead', 'A school of that name is already in the talks.' ) );
$GLOBALS['posts'][ (int) substr( $new, 5 ) ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', time() - 120 );
ck( 'and by the same account two minutes later, refused as taken too', add_post( 7, array( 'name' => 'Nowa Szkoła Testowa' ) ), 'redirect:' . $view_url . '#wpcpm-add-lead' );
// 1.123.102, round 5, C3: a refused Add a lead keeps every field as typed: a one-line field over its limit by a typed &amp;,
// and the note with round 4's case, 4,999 characters and a typed &amp;.
$c3_city = str_repeat( 'x', WPCPM_Institution_Leads::MAX_LENGTHS['city'] - 1 ) . '&amp;';
$c3_note = str_repeat( 'x', WPCPM_Institution_Talk_Notes::MAX_LENGTH - 1 ) . '&amp;';
add_post( 7, array( 'name' => 'Nowa Szkoła Testowa', 'city' => $c3_city, 'note' => $c3_note ) );
$GLOBALS['uid'] = 7;
$c3_kept        = WPCPM_Typed_Text::kept( WPCPM_Institution_Leads::ADD_FORM );
ck( 'c3_add_kept_whole: the refused form keeps the city and the note exactly as typed', array( ( $c3_kept['city'] ?? '' ) === $c3_city, ( $c3_kept['note'] ?? '' ) === $c3_note, $c3_kept['name'] ?? '' ), array( true, true, 'Nowa Szkoła Testowa' ) );
view( 7 );
$GLOBALS['posts'][ (int) substr( $new, 5 ) ]->post_date_gmt = gmdate( 'Y-m-d H:i:s' );
$lead_page = view( 7, array( 'wpcpm_lead' => $new ) );
add_post( 7, array( 'name' => 'Nowa Szkoła Testowa' ) );
$lead_page = view( 7, array( 'wpcpm_lead' => $new ) );
ck( 'its page: the lead\'s name the view\'s title and not again on the card (V7), "The lead is added." over the card\'s form, no back link', array( has( $lead_page, '<p class="wpcpm-head__title">Nowa Szkoła Testowa</p>' ), substr_count( $lead_page, 'Nowa Szkoła Testowa' ), has( $lead_page, 'wpcpm-ui-item__title' ), 1 === preg_match( '#<form[^>]*>.*?<p class="wpcpm-talks__message wpcpm-talks__message--success is-success"[^>]*>The lead is added\.</p>#s', $lead_page ), has( $lead_page, 'id="wpcpm-talks"' ), has( $lead_page, 'Back to Talks' ) ), array( true, 1, false, true, true, false ) );
$cases['the lead\'s page, just added, its name once'] = $lead_page;

$went = add_post( 7, array( 'name' => 'wyższa szkoła górnołąćka ', 'city' => 'Kraków ' . str_repeat( 'Ł', 40 ), 'note' => 'Kept note' ) );
$back = view( 7 );
preg_match( '#<section class="wpcpm-ui-fold-card" id="wpcpm-add-lead">.*?</section>#s', $back, $fold );
$fold = $fold[0] ?? '';
ck( 'a name already in the talks is refused: back at the fold, open, the sentence over the form linked to Name, the typing kept', array( $went, has( $fold, '<details class="wpcpm-ui-fold" open' ), has( $fold, '<a href="#wpcpm-add-lead-name">A school of that name is already in the talks.</a>' ), has( $fold, 'id="wpcpm-add-lead-name-problem"' ), has( $fold, 'value="Kraków ' . str_repeat( 'Ł', 40 ) . '"' ), has( $fold, 'Kept note' ) ), array( 'redirect:' . $view_url . '#wpcpm-add-lead', true, true, true, true, true ) );
$cases['Add a lead, a refusal kept'] = $back;
unset( $GLOBALS['wpcpm_guide_page'] );
WPCPM_Guide_Jobs::forget();
add_post( 7, array( 'name' => 'Szkoła Z Adresem', 'contact_email' => 'not an address' ) );
ck( 'a bad address is linked to Contact\'s email', has( view( 7 ), '<a href="#wpcpm-add-lead-contact-email">That contact email is not an email address.</a>' ), true );
add_post( 7, array( 'name' => '' ) );
ck( 'no name: "Give the school\'s name.", linked to Name', has( view( 7 ), '<a href="#wpcpm-add-lead-name">Give the school&#039;s name.</a>' ), true );
$GLOBALS['held'][ WPCPM_Institution_Leads::LOCK_ADD ] = true;
$count = count( WPCPM_Institution_Leads::all() );
add_post( 7, array( 'name' => 'Szkoła W Kolejce' ) );
ck( 'while another add holds the lock: "Another lead is being saved just now. Try again in a moment.", over the form, nothing made', array( has( view( 7 ), 'Another lead is being saved just now. Try again in a moment.</p>' ), count( WPCPM_Institution_Leads::all() ) ), array( true, $count ) );
$GLOBALS['held'] = array();
$went = add_post( 7, array( 'name' => 'Szkoła Z Długą Notatką', 'note' => str_repeat( 'n', 5001 ) ) );
$made = WPCPM_Institution_Leads::name_taken( 'Szkoła Z Długą Notatką' );
$page = view( 7, array( 'wpcpm_lead' => $made ) );
ck( 'a note refused: the lead is still made, and its page says so in one warning over Add a note (V8), the note kept, its refusal under its box', array( '' !== $made, $went, WPCPM_Institution_Talk_Notes::for_key( $made ), 1 === preg_match( '#<div id="wpcpm-talks-note"><p class="wpcpm-talks__message wpcpm-talks__message--warning is-warning"[^>]*>The lead is added, but its note is not\. Correct the note below and add it again\.</p>#', $page ), has( $page, 'id="wpcpm-talks-note-text-problem"' ), has( $page, str_repeat( 'n', 5001 ) ) ), array( true, 'redirect:' . $view_url . '&wpcpm_lead=' . $made . '#wpcpm-talks-note', array(), true, true, true ) );
$cases['the lead\'s page, its note refused'] = $page;

// The whole-branch wave, W7: Add a lead reads its fields as the Talks card does, so markup typed is kept whole and
// drawn escaped, never stripped.
world();
add_post( 7, array( 'name' => 'Lab <North> Unit', 'city' => 'Lab <North> Unit', 'contact_person' => 'Lab <North> Unit' ) );
$typed_key   = WPCPM_Institution_Leads::name_taken( 'Lab <North> Unit' );
$typed_lead  = '' !== $typed_key ? WPCPM_Institution_Leads::get( $typed_key ) : array();
$typed_page  = '' !== $typed_key ? view( 7, array( 'wpcpm_lead' => $typed_key ) ) : '';
// The card's save carries the hash of the values its card was drawn with (the owner's wave, X1).
$card_drawn  = preg_match( '#name="' . WPCPM_Institution_Talks::FIELD_DRAWN . '" value="([0-9a-f]{64})"#', view( 7, array( 'wpcpm_lead' => $GLOBALS['lead_open'] ) ), $drawn_m ) ? $drawn_m[1] : '';
$card_saved  = WPCPM_Institution_Talks::save( $GLOBALS['lead_open'], array( 'city' => 'Lab <North> Unit', 'drawn' => $card_drawn ), 7 );
$card_lead   = WPCPM_Institution_Leads::get( $GLOBALS['lead_open'] );
ck( 'lead_fields_kept_whole: "Lab <North> Unit" typed in Add a lead is stored whole and drawn escaped, as typed on the card',
	array( $typed_lead['name'] ?? null, $typed_lead['city'] ?? null, $typed_lead['contact_person'] ?? null, has( $typed_page, 'Lab &lt;North&gt; Unit' ), has( $typed_page, '<North>' ), is_wp_error( $card_saved ), $card_lead['city'] ),
	array( 'Lab <North> Unit', 'Lab <North> Unit', 'Lab <North> Unit', true, false, false, 'Lab <North> Unit' ) );

// Round 2, point 2: a lead's name, city and contact person are cleaned one way on both paths: every run of whitespace,
// tabs and line breaks included, one space, then trimmed. The name is typed in Add a lead alone (the card has no Name box).
world();
$spaced = " Lab\t<North>  Unit\nWest\r\n ";
add_post( 7, array( 'name' => $spaced, 'city' => $spaced, 'contact_person' => $spaced ) );
$spaced_key  = WPCPM_Institution_Leads::name_taken( 'Lab <North> Unit West' );
$spaced_lead = '' !== $spaced_key ? WPCPM_Institution_Leads::get( $spaced_key ) : array();
$spaced_page = '' !== $spaced_key ? view( 7, array( 'wpcpm_lead' => $spaced_key ) ) : '';
$card_drawn  = preg_match( '#name="' . WPCPM_Institution_Talks::FIELD_DRAWN . '" value="([0-9a-f]{64})"#', view( 7, array( 'wpcpm_lead' => $GLOBALS['lead_open'] ) ), $drawn_m ) ? $drawn_m[1] : '';
$card_spaced = WPCPM_Institution_Talks::save( $GLOBALS['lead_open'], array( 'city' => $spaced, 'contact_person' => $spaced, 'drawn' => $card_drawn ), 7 );
$card_lead   = WPCPM_Institution_Leads::get( $GLOBALS['lead_open'] );
ck( 'lead_fields_one_line: a tab, a double space and a line break in Add a lead and on the card store the same single-spaced value; markup in the name still whole and drawn escaped',
	array( $spaced_lead['name'] ?? null, $spaced_lead['city'] ?? null, $spaced_lead['contact_person'] ?? null, is_wp_error( $card_spaced ), $card_lead['city'], $card_lead['contact_person'], has( $spaced_page, 'Lab &lt;North&gt; Unit West' ) ),
	array( 'Lab <North> Unit West', 'Lab <North> Unit West', 'Lab <North> Unit West', false, 'Lab <North> Unit West', 'Lab <North> Unit West', true ) );

/* ---- the lead's page -------------------------------------------------------------- */

echo "\n=== A lead's page ===\n";

world();
$page = view( 7, array( 'wpcpm_lead' => $GLOBALS['lead_open'] ) );
ck( 'the lead\'s page draws its Talks card, its City a box of the card (V7), and nothing of the list', array( has( $page, 'id="wpcpm-talks"' ), has( $page, 'id="wpcpm-talks-city" name="city"' ), has( $page, 'Follow-ups due' ), has( $page, 'Find a school' ) ), array( true, true, false, false ) );
$cases['a lead\'s page'] = $page;
$closed_page = view( 7, array( 'wpcpm_lead' => $GLOBALS['lead_closed'] ) );
ck( 'a closed lead\'s card keeps its tag and its muted line, without the name', array( has( $closed_page, '<p class="wpcpm-ui-item__facts"><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact"><span class="wpcpm-status">Closed</span></span><span class="wpcpm-ui-item__fact">Reopen this lead to move it on.</span></span></p>' ), substr_count( $closed_page, 'Zamknięta Szkoła' ) ), array( true, 1 ) );
$cases['a closed lead\'s page'] = $closed_page;
// The whole-branch wave, W5: a lead whose move into the talks did not finish (Airtable's record made, the index's
// insert refused) says so on its card, one warning above the form, and Close is refused with the same sentence.
$unfinished      = 'This lead\'s move into the talks as an institution did not finish. Choose the stage again and save to finish it.';
$unfinished_post = (int) substr( $GLOBALS['lead_open'], strlen( WPCPM_Institution_Talks::LEAD_PREFIX ) );
$plain_page      = $page;
update_post_meta( $unfinished_post, WPCPM_Institution_Leads::META_PROMOTED_STAGE, 'Info Sent' );
update_post_meta( $unfinished_post, WPCPM_Institution_Leads::META_PROMOTED, 'recEXA00000000099' );
$half_page = view( 7, array( 'wpcpm_lead' => $GLOBALS['lead_open'] ) );
ck( 'lead_unfinished_warns: a lead carrying its record ID and still a lead shows one amber warning, its card\'s message part, as the form\'s first block where the outcome stands (the final round, F1); a lead whose move never began shows none',
	array( substr_count( html_entity_decode( $half_page, ENT_QUOTES ), $unfinished ), 1 === preg_match( '#<form [^>]*>(?:<input [^>]*>)*<p class="wpcpm-talks__message wpcpm-talks__message--warning is-warning" role="status" tabindex="-1" data-wpcpm-card-message>This lead&\#039;s move into the talks as an institution did not finish\. Choose the stage again and save to finish it\.</p>#', $half_page ), substr_count( $plain_page, 'did not finish' ) ),
	array( 1, true, 0 ) );
$cases['a lead whose move did not finish'] = $half_page;
$half_before = serialize( array( $GLOBALS['pmeta'][ $unfinished_post ] ?? array(), count( WPCPM_Institution_Audit::entries_for_lead( $GLOBALS['lead_open'], 200 ) ) ) );
$half_close  = WPCPM_Institution_Leads::close( $GLOBALS['lead_open'], 7 );
ck( 'lead_unfinished_close_refused: close() refuses such a lead with the same sentence and writes nothing',
	array( is_wp_error( $half_close ) ? $half_close->get_error_message() : $half_close, WPCPM_Institution_Leads::get( $GLOBALS['lead_open'] )['closed'], serialize( array( $GLOBALS['pmeta'][ $unfinished_post ] ?? array(), count( WPCPM_Institution_Audit::entries_for_lead( $GLOBALS['lead_open'], 200 ) ) ) ) === $half_before ),
	array( $unfinished, '', true ) );
$gone = view( 7, array( 'wpcpm_lead' => 'lead-99999' ) );
ck( 'a key that is no lead says "This lead is not in the talks." (V8)', array( has( $gone, '<p class="wpcpm-head__lede">This lead is not in the talks.</p>' ), has( $gone, 'id="wpcpm-talks"' ) ), array( true, false ) );
ck( 'a record ID asked for as a lead is no lead', has( view( 7, array( 'wpcpm_lead' => strtolower( A1 ) ) ), 'This lead is not in the talks.' ), true );
WPCPM_Institution_Record::set( A1, array( WPCPM_Institution_Leads::RECORD_LEAD_KEY => 'lead-77777' ) );
$became = view( 7, array( 'wpcpm_lead' => 'lead-77777' ) );
ck( 'a lead that became an institution: "This lead is an institution now: <school>.", the name a link to its Talks card (G1)', has( $became, '<p class="wpcpm-head__lede">This lead is an institution now: <a href="' . esc_url( WPCPM_Institution_Talks::url( A1 ) ) . '">Łódzka Szkoła Przykładowa</a>.</p>' ), true );

// Round 3, P2: a note refused for a lead whose page has no card: ONE message in the error tone, in the view's own column,
// directly above the page's own line, which follows unchanged; nothing else of the refusal.
$p2_message = '<p class="wpcpm-talks__message wpcpm-talks__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>The note was not added.</p>';
WPCPM_Flash::set( 'institution_talks', array( 'status' => 'note-refused', 'key' => 'lead-77777', 'section' => 'note', 'problems' => array( 'note' => 'This lead is an institution now. Add the note on its Talks card.' ) ), 7 );
$p2_became = view( 7, array( 'wpcpm_lead' => 'lead-77777' ) );
ck( 'p2_became_refused: the lead that is an institution now draws the one message, then its own line, and nothing else of the refusal',
	array( 1 === preg_match( '#</header>' . preg_quote( $p2_message, '#' ) . '<p class="wpcpm-head__lede">This lead is an institution now: <a href="[^"]+">Łódzka Szkoła Przykładowa</a>\.</p>#u', $p2_became ), substr_count( $p2_became, 'data-wpcpm-card-message' ), has( $p2_became, 'Add the note on its Talks card' ), flash_of( 7 ) ),
	array( true, 1, false, null ) );
$cases['a refused note on the page of a lead that is an institution now'] = $p2_became;
WPCPM_Flash::set( 'institution_talks', array( 'status' => 'note-refused', 'key' => 'lead-99999', 'section' => 'note', 'problems' => array( 'note' => 'That lead is not in the talks any more.' ) ), 7 );
$p2_gone = view( 7, array( 'wpcpm_lead' => 'lead-99999' ) );
ck( 'p2_gone_refused: the lead that is gone draws the one message, then "This lead is not in the talks."',
	array( has( $p2_gone, '</header>' . $p2_message . '<p class="wpcpm-head__lede">This lead is not in the talks.</p>' ), substr_count( $p2_gone, 'data-wpcpm-card-message' ), has( $p2_gone, 'any more' ) ),
	array( true, 1, false ) );
$cases['a refused note on the page of a lead that is gone'] = $p2_gone;
WPCPM_Flash::set( 'institution_talks', array( 'status' => 'note-refused', 'key' => 'lead-55555', 'section' => 'note', 'problems' => array( 'note' => 'x' ) ), 7 );
ck( 'p2_other_key: a refusal about another key draws nothing here, and the page is as before', array( substr_count( view( 7, array( 'wpcpm_lead' => 'lead-99999' ) ), 'data-wpcpm-card-message' ), has( view( 7, array( 'wpcpm_lead' => 'lead-99999' ) ), '<p class="wpcpm-head__lede">This lead is not in the talks.</p></div></header>' ) ), array( 0, true ) );
$cases['a promoted lead\'s old link'] = $became;

/* ---- the sources -------------------------------------------------------------------- */

echo "\n=== The page, the menu, the sheet ===\n";

$dashboard = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-dashboard.php' );
$sheet     = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/administrator.css' );
ck( 'the view is the page\'s VIEW_TALKS, routed by card_url() for Add a lead, and handle_add() is hooked', array( has( $dashboard, "const VIEW_TALKS = 'talks';" ), has( $dashboard, "'talks'     => array( 'add-lead' )," ), has( $leads_src, "add_action( 'admin_post_' . self::ACTION_ADD, array( __CLASS__, 'handle_add' ) );" ), WPCPM_Institution_Leads::ACTION_ADD ), array( true, true, true, 'wpcpm_talks_add_lead' ) );
ck( 'the icon chat is the brief\'s path', has( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php' ), "'chat'         => '<path d=\"M4.5 5.5h15v10h-8l-4.5 3.5v-3.5h-2.5z\"/>'," ), true );
ck( 'the Administrator Dashboard\'s sheet gives the Talks view\'s messages their success, error and warning tones, on the logical start', array(
	1 === preg_match( '/\.wpcpm-dashboard\.wpcpm-administrator--talks\.wpcpm-ui-on \[data-wpcpm-card-message\] \{[^}]*border-inline-start: 4px solid currentColor;/', $sheet ),
	1 === preg_match( '/\.wpcpm-dashboard\.wpcpm-administrator--talks\.wpcpm-ui-on \[data-wpcpm-card-message\]\.is-success \{\s*border-inline-start-color: var\( --wpc-green, #1a7f37 \);\s*\}/', $sheet ),
	1 === preg_match( '/\.wpcpm-dashboard\.wpcpm-administrator--talks\.wpcpm-ui-on \[data-wpcpm-card-message\]\.is-error \{\s*border-inline-start-color: var\( --wpc-red, #b32d2e \);\s*\}/', $sheet ),
	1 === preg_match( '/\.wpcpm-dashboard\.wpcpm-administrator--talks\.wpcpm-ui-on \[data-wpcpm-card-message\]\.is-warning \{\s*border-inline-start-color: var\( --wpcpm-f-amber \);\s*\}/', $sheet ),
), array( true, true, true, true ) );
ck( 'I1: a message that is a direct child of the view\'s root carries no bottom margin of its own, so it stands on the view\'s 28px rhythm both ways', 1 === preg_match( '/\.wpcpm-dashboard\.wpcpm-administrator--talks\.wpcpm-ui-on > \[data-wpcpm-card-message\] \{\s*margin-block-end: 0;\s*\}/', $sheet ), true );

/* ---- a lead deleted (the owner's wave, X4) ----------------------------------------- */

echo "\n=== A lead deleted (X4) ===\n";

world();
$x4_key   = $GLOBALS['lead_closed'];
$x4_count = WPCPM_Institution_Talks_View::due_count( 7 );
ck( 'X4: before: Find finds the closed lead', '' !== row_of( view( 7, array( 'wpcpm_talks_find' => 'zamknięta' ) ), 'Zamknięta Szkoła' ), true );
$x4_ask = WPCPM_Institution_Leads::trash( $x4_key, 7, '' );
$GLOBALS['uid'] = 7;
$_POST          = array( 'action' => 'wpcpm_talks_delete', 'key' => $x4_key, '_wpnonce' => 'nonce-wpcpm_talks_delete_' . $x4_key, 'confirm' => is_array( $x4_ask ) ? (string) $x4_ask['confirm'] : '' );
try {
	WPCPM_Institution_Talks::handle_delete();
	$x4_went = '';
} catch ( Exception $e ) {
	$x4_went = $e->getMessage();
}
$_POST     = array();
$x4_landed = view( 7 );
ck( 'X4: the delete lands on the Talks view, which says so once, in the success tone, where it shows an outcome', array( $x4_went, 1 === preg_match( '#</header><p class="wpcpm-talks__message wpcpm-talks__message--success is-success"[^>]*>The lead Zamknięta Szkoła was deleted\.</p>#', $x4_landed ), has( view( 7 ), 'was deleted.' ) ), array( 'redirect:' . $view_url, true, false ) );
$cases['a lead deleted, said over the view'] = $x4_landed;
ck( 'X4: a lead in the Trash is in no list, no Find, no due table, no Monday email line, and the menu\'s count is as it was', array( has( view( 7, array( 'wpcpm_talks_who' => 'all' ) ), 'Zamknięta Szkoła' ), row_of( view( 7, array( 'wpcpm_talks_find' => 'zamknięta' ) ), 'Zamknięta Szkoła' ), in_array( $x4_key, keys_of( WPCPM_Institution_Talks_View::due_for( 7 ) ), true ), WPCPM_Institution_Talks_View::due_count( 7 ), array_key_exists( $x4_key, WPCPM_Institution_Talks_View::facts() ) ), array( false, '', false, $x4_count, false ) );
ck( 'X4: its address answers as a lead that does not exist', array( has( view( 7, array( 'wpcpm_lead' => $x4_key ) ), 'This lead is not in the talks.' ), has( view( 7, array( 'wpcpm_lead' => $x4_key ) ), 'id="wpcpm-talks"' ) ), array( true, false ) );
ck( 'X4: and Add a lead with its name works', array( add_post( 7, array( 'name' => 'Zamknięta Szkoła' ) ) !== 'redirect:' . $view_url . '#wpcpm-add-lead', '' !== WPCPM_Institution_Leads::name_taken( 'Zamknięta Szkoła' ) && WPCPM_Institution_Leads::name_taken( 'Zamknięta Szkoła' ) !== $x4_key ), array( true, true ) );

/* ---- layout ------------------------------------------------------------------------- */

echo "\n=== Layout cases ===\n";

ck( 'twenty-one cases, each on the Talks view (round 3, P2: the two pages of a refused note)', count( $cases ), 21 );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	foreach ( $cases as $name => $html ) {
		wpcpm_layout_keep( 'Talks view: ' . $name, $html, 'administrator/talks' );
	}
}

echo "\n=== Program details stay on the card ===\n";

fresh();
foreach ( array_keys( WPCPM_Institution_Talks::all_facts() ) as $id ) {
	if ( WPCPM_Institution_Talks::is_lead_key( $id ) ) {
		WPCPM_Institution_Leads::update( $id, array( 'program_details' => "PROGRAMDETAILS-SECRET lead\nhours" ), 7 );
	} else {
		WPCPM_Institution_Record::set( $id, array( 'program_details' => "PROGRAMDETAILS-SECRET\nhours" ) );
	}
}
WPCPM_Institution_Talks_View::forget();
$pd_all = WPCPM_Institution_Talks_View::facts();
ck( 'Program details: the view\'s rows, the due table and due_for() hold nothing of it', array( false !== strpos( view( 7 ), 'PROGRAMDETAILS-SECRET' ), false !== strpos( view( 7, array( 'wpcpm_talks_who' => 'all' ) ), 'PROGRAMDETAILS-SECRET' ), false !== strpos( (string) json_encode( WPCPM_Institution_Talks_View::rows( $pd_all, 'all', 7, '' ) ), 'PROGRAMDETAILS-SECRET' ), false !== strpos( (string) json_encode( WPCPM_Institution_Talks_View::due_for( 7 ) ), 'PROGRAMDETAILS-SECRET' ) ), array( false, false, false, false ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
