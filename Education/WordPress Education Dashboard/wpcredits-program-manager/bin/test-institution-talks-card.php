<?php
/**
 * The Talks card and its handlers (Institutions off Airtable, release 2, Task 15): where one school's talks are
 * changed, by the Administrators, once the site keeps the record of institutions.
 *
 * What is pinned here and why:
 *
 * - **Who:** the card is drawn for an Administrator in site mode alone; a member never sees it, and a member's
 *   hand-made post to any of the four actions answers 403 with nothing written; outside site mode nothing is drawn and
 *   an Administrator's post is refused alike.
 * - **The card:** on the parts in the shell (key `talks`, anchor on the section): the school's name, a status tag when
 *   one is due (Past due, red; Closed, neutral), the stage and "Since <day>, by <name>."; the fields form with its one
 *   Save; the stage a select of every stage but Confirmed (Confirmed only as the value it is at), Lead for a lead
 *   alone; the assigned Administrator and the country, each the one combobox; what is on record and not edited here,
 *   with the sentence that names the details card; the notes newest first; Add a note, a form of its own.
 * - **Confirmed** is refused unless the school is at it, and nothing is saved.
 * - **Review Focus 1:** a move out of the active stages saves nothing until it is confirmed (the sentence names the
 *   accounts it takes the access of, or says the page locks), keeps what was typed, and once confirmed saves the stage,
 *   closes the gate (option deleted, members detached as the sync's revoke does) and copies `Current Stage`; the move
 *   back opens the gate again from the site's own record, the Drive link included, and the members' gated actions are
 *   allowed again.
 * - **The locks:** the save takes the school's lock and its agreement lock, the one acceptance and recording on file
 *   decide the stage under; with either held it answers busy and writes nothing, and while it holds the agreement lock
 *   no other path can take it.
 * - **Revisit Later** proposes the follow-up 90 days ahead when the date was left as drawn; a date typed wins.
 * - **Rows:** one `talks_stage`, one `talks_saved` ("The talks were updated: ..."), one `assignment_changed` with its
 *   email; an unchanged save writes nothing and says "Nothing was changed."
 * - **The copy:** exactly the changed copy columns, in one send.
 * - **A lead:** its fields through the leads' update, a stage through promote, which lands on the new school's card;
 *   each refusal in the method's own sentence, over the form, the typing kept; Close this lead and Reopen this lead.
 * - **Notes:** one note, signed and dated, and no history row; an empty one refused; a second press within a minute
 *   adds nothing.
 * - **Layout:** the cases the harness draws (`wpcpm_layout_keep()`, surface `institution` by the file name).
 *
 * The real talks, leads, notes, record, index, agreement, assignment, audit, policy, sync, roles and the parts run here.
 * Stood in for at their contracts: the members, the copy, the mail, the countries, the two dashboards' addresses, the
 * flash (without its once-per-request memory) and the lock (`held`).
 *
 * Run from the plugin root:  php bin/test-institution-talks-card.php
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
// Each hook's callbacks, noted (the outside review, O10: `wp_untrash_post()` below runs `untrashed_post`'s).
function add_action( $hook, $cb = null, $p = 10, $n = 1 ) { $GLOBALS['actions'][ $hook ][] = $cb; }
function add_filter() {}
function register_post_type( $type, $args = array() ) {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v = '', $dep = '', $a = null ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { $had = array_key_exists( $k, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $k ] ); return $had; }
function wp_cache_delete( $k, $g = '' ) { $GLOBALS['cache_dropped'][] = array( $k, $g ); if ( 'post_meta' === $g ) { unset( $GLOBALS['mcache'][ (int) $k ] ); } if ( 'posts' === $g ) { unset( $GLOBALS['pcache'][ (int) $k ] ); } return true; }
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
// This request's cache of posts and their meta, as WordPress keeps one, while `$GLOBALS['post_cache']` is on (the outside
// review, O3): a post or a meta row read once is read from the cache after that, until `wp_cache_delete()` drops it, and
// this request's own writes land in it. Another request's write is a write to `$GLOBALS['posts']` or `$GLOBALS['pmeta']`
// alone, which the cache never sees. Off, as every check before O3 runs, both read the table.
function get_post( $id ) {
	$id = (int) $id;
	if ( ! empty( $GLOBALS['post_cache'] ) ) {
		if ( ! array_key_exists( $id, $GLOBALS['pcache'] ?? array() ) ) { $GLOBALS['pcache'][ $id ] = isset( $GLOBALS['posts'][ $id ] ) ? clone $GLOBALS['posts'][ $id ] : null; }
		return $GLOBALS['pcache'][ $id ];
	}
	return $GLOBALS['posts'][ $id ] ?? null;
}
// The commit of a note or a lead (Task 18 fix round 1): the one update that moves it from its unfinished draft.
function wp_update_post( $a, $wp_error = false ) { $id = (int) $a['ID']; if ( ! isset( $GLOBALS['posts'][ $id ] ) ) { return 0; } foreach ( stripslashes_deep( $a ) as $k => $v ) { $GLOBALS['posts'][ $id ]->$k = $v; } unset( $GLOBALS['pcache'][ $id ] ); return $id; }
function get_post_meta( $id, $key = '', $single = false ) {
	if ( ! empty( $GLOBALS['post_cache'] ) ) {
		if ( ! isset( $GLOBALS['mcache'][ (int) $id ] ) ) { $GLOBALS['mcache'][ (int) $id ] = $GLOBALS['pmeta'][ (int) $id ] ?? array(); }
		return $GLOBALS['mcache'][ (int) $id ][ $key ] ?? '';
	}
	return $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? '';
}
// A check that makes one meta key's write fail names it in `meta_fails` (the record's answers, for L1).
function update_post_meta( $id, $key, $value ) { if ( ( $GLOBALS['meta_fails'] ?? '' ) === $key ) { return false; } $GLOBALS['pmeta'][ (int) $id ][ $key ] = stripslashes_deep( $value ); if ( isset( $GLOBALS['mcache'][ (int) $id ] ) ) { $GLOBALS['mcache'][ (int) $id ][ $key ] = stripslashes_deep( $value ); } return true; }
function wp_sprintf_l( $pattern, $args ) { $args = array_values( (array) $args ); if ( count( $args ) < 2 ) { return implode( '', $args ); } $last = array_pop( $args ); return implode( ', ', $args ) . ( count( $args ) > 1 ? ', and ' : ' and ' ) . $last; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $key ], $GLOBALS['mcache'][ (int) $id ][ $key ] ); return true; }
// `wp_untrash_post()` as core runs it since 5.6 (the outside review, O10): the status the filter answers (the leads'
// `untrash_status()`) from the one kept, else a draft; the kept status removed; then the `untrashed_post` callbacks.
function wp_untrash_post( $id ) {
	$p = $GLOBALS['posts'][ (int) $id ] ?? null;
	if ( ! $p || 'trash' !== $p->post_status ) { return false; }
	$previous       = (string) ( $GLOBALS['pmeta'][ (int) $id ]['_wp_trash_meta_status'] ?? '' );
	$p->post_status = WPCPM_Institution_Leads::untrash_status( 'draft', (int) $id, $previous );
	unset( $GLOBALS['pmeta'][ (int) $id ]['_wp_trash_meta_status'] );
	foreach ( $GLOBALS['actions']['untrashed_post'] ?? array() as $cb ) { call_user_func( $cb, (int) $id, $previous ); }
	return $p;
}
// WordPress's Trash, as core keeps it: the status the post had, then `trash` (the owner's wave, X4).
function wp_trash_post( $id ) { $p = $GLOBALS['posts'][ (int) $id ] ?? null; if ( ! empty( $GLOBALS['trash_fails'] ) || ! $p || 'trash' === $p->post_status ) { return false; } $GLOBALS['pmeta'][ (int) $id ]['_wp_trash_meta_status'] = $p->post_status; $p->post_status = 'trash'; unset( $GLOBALS['pcache'][ (int) $id ], $GLOBALS['mcache'][ (int) $id ] ); return $p; }
function wp_delete_post( $id, $force = false ) { $p = $GLOBALS['posts'][ (int) $id ] ?? false; unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ], $GLOBALS['pcache'][ (int) $id ], $GLOBALS['mcache'][ (int) $id ] ); return $p; }
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
class WPCPM_Institution_Areas {
	public static function administrator_for( $country ) { return 0; }
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
	const COLUMNS = array( 'name' => 'Name', 'stage' => 'Current Stage', 'country' => 'Country', 'city' => 'City', 'website' => 'Website', 'contact_person' => 'Contact Person', 'contact_email' => 'Contact Email', 'confirmed_on' => 'Confirmed on', 'next_step' => 'Next Step', 'follow_up' => 'Follow-Up Date', 'last_contact' => 'Last Contact Date', 'estimated_students' => 'Estimated number of students who may be interested', 'help_desk' => 'FreeScout' );
	public static function send( $record, array $keys ) {
		if ( isset( $GLOBALS['during_send'] ) && is_callable( $GLOBALS['during_send'] ) ) { call_user_func( $GLOBALS['during_send'], $record ); }
		$GLOBALS['sent'][] = array( $record, $keys );
		return true;
	}
	public static function create( array $row ) { $GLOBALS['created'][] = $row; return $GLOBALS['answer'] ?? 'recNEW00000000001'; }
	// Each mark noted, and beside it the locks held as it was made (the outside review, O5).
	public static function mark( $record, array $keys ) { $GLOBALS['marked'][] = array( $record, $keys ); $GLOBALS['marked_holding'][] = array_keys( array_filter( (array) ( $GLOBALS['holding'] ?? array() ) ) ); $GLOBALS['marked_agreement_lock'][] = array_key_exists( WPCPM_Institution_Agreement::lock_name( $record ), $GLOBALS['opts'] ); return true; }
}
/** The lock, by name: one a check holds (`held`) is refused; every one taken is noted. */
class WPCPM_Lock {
	public static function take( $name, $ttl ) {
		$GLOBALS['taken'][] = $name;
		if ( ! empty( $GLOBALS['held'][ $name ] ) ) { return ''; }
		$GLOBALS['holding'][ $name ] = true;
		return 'token';
	}
	public static function release( $name, $token ) { unset( $GLOBALS['holding'][ $name ] ); return true; }
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
	const REASON_REVOKED = 'revoked';
	// As the real detach, the history row that keeps why the membership ended (T4 reads it).
	public static function detach( $user_id, $reason, $actor ) { $GLOBALS['detach'][] = array( (int) $user_id, $reason, (int) $actor ); if ( isset( $GLOBALS['members'][ (int) $user_id ] ) ) { $record = $GLOBALS['members'][ (int) $user_id ]; $GLOBALS['former'][ $record ][ (int) $user_id ] = true; WPCPM_Institution_Audit::record( array( 'kind' => WPCPM_Institution_Audit::KIND_MEMBER_REMOVED, 'institution' => $record, 'subject' => (string) (int) $user_id, 'actor' => (int) $actor, 'ground' => WPCPM_Institution_Audit::GROUND_MANAGER, 'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX, 'message' => 'Removed.', 'data' => array( 'user' => (int) $user_id, 'reason' => (string) $reason ) ) ); } unset( $GLOBALS['members'][ (int) $user_id ] ); return true; }
	public static function former_members_of( $record ) { $out = array(); foreach ( array_keys( $GLOBALS['former'][ $record ] ?? array() ) as $id ) { if ( ( $GLOBALS['members'][ $id ] ?? '' ) !== $record ) { $out[] = new WP_User( $id ); } } return $out; }
}
require_once __DIR__ . '/stubs/institution-card-url.php';
class WPCPM_Institutions_Dashboard {
	public static $page = 'https://example.test/institution-dashboard/';
	public static function card_url( string $card, string $record = '', string $item = '', array $args = array() ): string { return wpcpm_test_institution_card_url( self::$page, $card, $record, $item, $args ); }
}
class WPCPM_Administrators_Dashboard {
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
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-card.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';

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

if ( ! method_exists( 'WPCPM_Institution_Talks', 'render' ) || ! method_exists( 'WPCPM_Institution_Talks', 'save' ) ) {
	ck( 'the Talks card is built', false, true );
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
	$GLOBALS['former']       = array();
	$GLOBALS['nonces']       = array();
	$GLOBALS['created']      = array();
	$GLOBALS['during_send']  = null;
	$GLOBALS['during_mail']  = null;
	$GLOBALS['meta_fails']   = '';
	$GLOBALS['post_cache']   = false;
	$GLOBALS['pcache']       = array();
	$GLOBALS['mcache']       = array();
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

/** The card, drawn for one reader, on the parts. */
function draw( $uid, $key = S ) {
	$GLOBALS['uid'] = (int) $uid;
	WPCPM_UI::enter( 'institution' );
	ob_start();
	WPCPM_Institution_Talks::render( $key, array( 'can_manage' => in_array( (int) $uid, $GLOBALS['manage'], true ) ) );
	$html = (string) ob_get_clean();
	WPCPM_UI::leave();
	return $html;
}

/** What the drawn form posts, every field as drawn, with edits over it. */
function as_drawn( $html ) {
	$out = array();
	if ( preg_match( '#<form[^>]*>(.*?)</form>#s', $html, $form ) ) {
		preg_match_all( '#<input type="hidden" name="([^"]+)" value="([^"]*)"#', $form[1], $hidden, PREG_SET_ORDER );
		foreach ( $hidden as $h ) { $out[ $h[1] ] = html_entity_decode( $h[2], ENT_QUOTES ); }
		preg_match_all( '#<input type="(?:text|email|date|number)"[^>]*name="([^"]+)"[^>]*value="([^"]*)"#', $form[1], $boxes, PREG_SET_ORDER );
		foreach ( $boxes as $b ) { $out[ $b[1] ] = html_entity_decode( $b[2], ENT_QUOTES ); }
		preg_match_all( '#<select[^>]*name="([^"]+)"[^>]*>(.*?)</select>#s', $form[1], $selects, PREG_SET_ORDER );
		foreach ( $selects as $sel ) {
			$out[ $sel[1] ] = preg_match( '#<option value="([^"]*)"[^>]*selected#', $sel[2], $o ) ? html_entity_decode( $o[1], ENT_QUOTES ) : '';
		}
	}
	return $out;
}

/** Post one of the card's handlers as one account: how it ended (a redirect, a wp_die, a refused nonce). */
function post_as( $uid, $method, array $post ) {
	$GLOBALS['uid'] = (int) $uid;
	$_POST          = $post;
	try {
		call_user_func( array( 'WPCPM_Institution_Talks', $method ) );
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
	return '';
}
/** The fields form as drawn for 7, edited, posted. */
function save_as( $uid, array $edits, $key = S ) {
	$drawn = as_drawn( draw( 7, $key ) );
	return post_as( $uid, 'handle_save', array_merge( $drawn, $edits ) );
}
/** A drawn textarea's text, as the browser reads it (the first line break after the tag is not the text's). */
function as_drawn_text( $html, $name ) {
	return preg_match( '#<textarea[^>]*name="' . $name . '"[^>]*>\n(.*?)</textarea>#s', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : '';
}
function rows_of( $key, $kind ) { return array_values( array_filter( WPCPM_Institution_Audit::entries_for( $key, 0 ), static function ( $r ) use ( $kind ) { return $kind === $r['kind']; } ) ); }
/** The forms whose typing a refusal kept for this account, by name (1.123.102, Part C2). */
function kept_forms_of( $uid ) {
	return array_values( array_map( static function ( $channel ) { return substr( (string) $channel, strlen( WPCPM_Typed_Text::KEPT_CHANNEL ) ); }, array_filter( array_keys( (array) ( $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash'] ?? array() ) ), static function ( $channel ) { return 0 === strpos( (string) $channel, WPCPM_Typed_Text::KEPT_CHANNEL ); } ) ) );
}
function flash_of( $uid ) { return $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash']['institution_talks'] ?? null; }
function back( $key = S, $anchor = 'wpcpm-talks' ) { return 'redirect:https://example.test/institution-dashboard/?wpcpm_view=administrators&wpcpm_institution_view=' . $key . '#' . $anchor; }
function stage_of( $key ) { WPCPM_Institutions_Index::forget_cached(); return WPCPM_Institutions_Index::row( $key )['stage']; }

$refusal = 'wp_die:' . WPCPM_Institution_Policy::refusal()->get_error_message();
$cases   = array();

/* ---- the names ------------------------------------------------------------ */

echo "=== The names ===\n";

ck( 'the anchor, the four actions, the flash channel and the 90 days', array( WPCPM_Institution_Talks::ANCHOR, WPCPM_Institution_Talks::ACTION_SAVE, WPCPM_Institution_Talks::ACTION_NOTE, WPCPM_Institution_Talks::ACTION_CLOSE, WPCPM_Institution_Talks::ACTION_REOPEN, WPCPM_Institution_Talks::FLASH, WPCPM_Institution_Talks::REVISIT_DAYS ), array( 'wpcpm-talks', 'wpcpm_talks_save', 'wpcpm_talks_note', 'wpcpm_talks_close', 'wpcpm_talks_reopen', 'institution_talks', 90 ) );
ck( 'the policy action, for the Administrators alone', array( WPCPM_Institution_Policy::ACT_TALKS, WPCPM_Institution_Policy::grounds()['talks'] ), array( 'talks', array( 'manager' ) ) );
$module = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php' );
$talks  = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talks.php' );
ck( 'init() hooks the four handlers, and the module boots it', array( 4 === preg_match_all( "/add_action\\( 'admin_post_' \\. self::ACTION_(SAVE|NOTE|CLOSE|REOPEN), array\\( __CLASS__, 'handle_(save|note|close|reopen)' \\) \\);/", $talks ), 1 === preg_match( '/public function boot\\(\\) \\{.*?WPCPM_Institution_Talks::init\\(\\);/s', $module ) ), array( true, true ) );
$dashboard = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-dashboard.php' );
ck( 'the card is the first of the For Administrators view, before the History, and card_url() routes there', array( 1 === preg_match( "/case self::VIEW_ADMINISTRATORS:.*?self::card\( 'WPCPM_Institution_Talks'.*?WPCPM_Institution_History::render/s", $dashboard ), has( $dashboard, "'administrators' => array( 'talks', 'for-administrators'" ), WPCPM_Institution_Talks::url( S ) ), array( true, true, 'https://example.test/institution-dashboard/?wpcpm_view=administrators&wpcpm_institution_view=' . S . '#wpcpm-talks' ) );

/* ---- who ------------------------------------------------------------------ */

echo "\n=== Who ===\n";

fresh();
ck( 'a member, another account and nobody are drawn nothing', array( draw( 40 ), draw( 30 ), draw( 0 ) ), array( '', '', '' ) );
$GLOBALS['on_site'] = false;
ck( 'outside site mode an Administrator is drawn nothing', draw( 7 ), '' );
$GLOBALS['on_site'] = true;
$page = draw( 7 );
ck( 'an Administrator in site mode is drawn the card in the shell, its anchor on the section', array( has( $page, '<section class="wpcpm-ui-fold-card" id="wpcpm-talks">' ), has( $page, 'Talks' ) ), array( true, true ) );

$drawn  = as_drawn( $page );
$before = array( $GLOBALS['opts'], $GLOBALS['posts'], $GLOBALS['pmeta'] );
$note   = array( 'action' => 'wpcpm_talks_note', 'key' => S, '_wpnonce' => 'nonce-wpcpm_talks_note_' . S, 'note' => 'A member wrote this' );
$close  = array( 'action' => 'wpcpm_talks_close', 'key' => 'lead-5', 'wpcpm_talks_lead_nonce' => 'nonce-wpcpm_talks_close_lead-5' );
ck( 'a member\'s hand-made post with a valid nonce to each action answers 403', array( post_as( 40, 'handle_save', array_merge( $drawn, array( 'stage' => 'SPAM', 'leave' => '1' ) ) ), post_as( 40, 'handle_note', $note ), post_as( 40, 'handle_close', $close ), post_as( 40, 'handle_reopen', array_merge( $close, array( 'wpcpm_talks_lead_nonce' => 'nonce-wpcpm_talks_reopen_lead-5' ) ) ) ), array( $refusal, $refusal, $refusal, $refusal ) );
ck( 'and nothing was written', array( $GLOBALS['opts'], $GLOBALS['posts'], $GLOBALS['pmeta'], $GLOBALS['sent'], $GLOBALS['detach'] ), array_merge( $before, array( array(), array() ) ) );
$GLOBALS['on_site'] = false;
ck( 'outside site mode an Administrator\'s post is refused alike, nothing written', array( post_as( 7, 'handle_save', array_merge( $drawn, array( 'next_step' => 'Call' ) ) ), post_as( 7, 'handle_note', $note ), $GLOBALS['posts'] === $before[1] ), array( $refusal, $refusal, true ) );
$GLOBALS['on_site'] = true;
ck( 'a nonce for another school is refused, keyed to the posted key', post_as( 7, 'handle_save', array_merge( $drawn, array( 'key' => LONG ) ) ), 'nonce:wpcpm_talks_save_' . LONG );
ck( 'a key of neither shape is refused with the policy\'s 403', post_as( 7, 'handle_save', array( 'key' => 'nope', '_wpnonce' => 'nonce-wpcpm_talks_save_nope' ) ), $refusal );

/* ---- the card ------------------------------------------------------------- */

echo "\n=== The card ===\n";

/** The values a select offers, in order. */
function options_of( $html, $name ) {
	preg_match( '#<select[^>]*name="' . $name . '"[^>]*>(.*?)</select>#s', $html, $select );
	preg_match_all( '#<option value="([^"]*)"#', $select[1] ?? '', $values );
	return $values[1];
}
/** The ids of the form's fields, in the order drawn. */
function field_order( $html ) {
	preg_match( '#<form[^>]*>(.*?)</form>#s', $html, $form );
	preg_match_all( '#<label class="wpcpm-ui-field__label"(?: id="[^"]*")? for="wpcpm-talks-([a-z-]+)"#', $form[1] ?? '', $ids );
	return $ids[1];
}

fresh();
WPCPM_Institution_Audit::record( array( 'kind' => 'talks_stage', 'institution' => S, 'subject' => S, 'actor' => 11, 'ground' => 'manager', 'evidence' => 'index', 'message' => 'The stage of the talks moved from Agreement Sent to Confirmed.', 'data' => array( 'from' => 'Agreement Sent', 'to' => 'Confirmed' ) ) );
WPCPM_Institution_Talk_Notes::add( S, 'Spoke with the dean.', 11, array( 'date' => '2026-10-02 09:00:00' ) );
WPCPM_Institution_Talk_Notes::add( S, 'Sent the brochure.', 7, array( 'date' => '2026-10-05 09:00:00' ) );
$page = draw( 7 );
$text = plain( $page );
ck( 'the school\'s name heads the card, and the stage is said once, in its box, "Since <day>, by <name>." its hint (U3)', array( has( $text, 'Wyższa Szkoła Górnołąćka' ), 1 === preg_match( '#id="wpcpm-talks-stage-hint">Since \d{1,2} \w+ \d{4}, by Piotr Admin\.</p>#', $page ), substr_count( $text, 'Confirmed' ) - substr_count( plain( preg_replace( '#<select.*?</select>#s', '', $page ) ), 'Confirmed' ) > 0, has( $page, '<span class="wpcpm-ui-item__fact">Confirmed</span>' ) ), array( true, true, true, false ) );
ck( 'a follow-up before today is the red status tag Past due, beside the name', array( has( $page, '<p class="wpcpm-ui-item__facts"><span class="wpcpm-status wpcpm-status--red">Past due</span></p>' ), substr_count( $text, 'Past due' ) ), array( true, 1 ) );
ck( 'the fields in their order: Stage, Next step, Follow up on, Assigned Administrator, Country, Estimated students, First students begin (U4)', field_order( $page ), array( 'stage', 'next-step', 'follow-up', 'assigned', 'country', 'estimated-students', 'first-students', 'program-details' ) );
ck( 'Stage and Country carry the Required mark, with no required on the box (U5)', array( 1 === preg_match( '#for="wpcpm-talks-stage">Stage <span class="wpcpm-ui-field__need">Required</span></label><select[^>]*aria-required="true"#', $page ), 1 === preg_match( '#for="wpcpm-talks-country">Country <span class="wpcpm-ui-field__need">Required</span>#', $page ), has( $page, 'id="wpcpm-talks-stage" name="stage" required' ) ), array( true, true, false ) );
ck( 'a settled school is offered Confirmed, Student and the three that close the talks (L2)', options_of( $page, 'stage' ), array( 'Confirmed', 'Student', 'Not Moving Forward', 'SPAM', 'Revisit Later' ) );
ck( 'a school not settled is offered First Contact Made to Agreement Sent and the three, never Confirmed or Student', options_of( draw( 7, LONG ), 'stage' ), array( 'First Contact Made', 'Call Scheduled', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Agreement Sent', 'Not Moving Forward', 'SPAM', 'Revisit Later' ) );
ck( 'a school at a stage its agreement does not lead to keeps it offered, first', options_of( draw( 7, DONE ), 'stage' ), array( 'Confirmed', 'First Contact Made', 'Call Scheduled', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Agreement Sent', 'Not Moving Forward', 'SPAM', 'Revisit Later' ) );
ck( 'the assigned Administrator and the country are each the one combobox, the stored values chosen', array( has( $page, 'name="wpcpm_talks_assigned"' ), as_drawn( $page )['wpcpm_talks_assigned'] ?? null, as_drawn( $page )['country'] ?? null ), array( true, '11', PL ) );
ck( 'labels over the boxes, and one Save, a primary', array( substr_count( $page, '>Save</button>' ), substr_count( $page, 'class="wpcpm-btn"' ) ), array( 1, 2 ) );
ck( 'the contact person and their email shown, with the sentence that names the details card and links to it; last contact and help desk shown, not edited', array( has( $text, 'Contact person Kontakt Jeden' ), has( $text, "Contact's email kontakt@example.test" ), has( $page, '#wpcpm-details">Your institution&#039;s details</a>' ), has( html_entity_decode( strip_tags( $page ), ENT_QUOTES ), 'Change the contact person and their email in Your institution\'s details, on the Your institution view.' ), has( $page, 'name="contact_person"' ), has( $page, 'name="last_contact"' ) ), array( true, true, true, true, false, false ) );
ck( 'a note\'s row: its author the name, its moment the fact, its words after in the note\'s weight, newest first (U7)', array( 1 === preg_match( '#<span class="wpcpm-ui-list__name">Ana Admin</span><span class="wpcpm-ui-list__facts">.*?5 October 2026 09:00.*?<div class="wpcpm-ui-list__more"><p class="wpcpm-ui-note">Sent the brochure\.</p>#s', $page ), strpos( $text, 'Sent the brochure.' ) < strpos( $text, 'Spoke with the dean.' ) ), array( true, true ) );
ck( 'and Add a note, a form of its own, with its own primary; novalidate on both', array( substr_count( $page, '<form ' ), substr_count( $page, ' novalidate ' ), has( $page, '>Add the note</button>' ) ), array( 2, 2, true ) );
// Restored in fix round 2 (R7): the two checks the first round dropped.
ck( 'estimated students a number, first students begin and follow up on dates, next step one line of at most 200', array( has( $page, 'type="number" class="wpcpm-ui-field__control wpcpm-ui-field__control--number" id="wpcpm-talks-estimated-students"' ), has( $page, 'type="date" class="wpcpm-ui-field__control wpcpm-ui-field__control--date" id="wpcpm-talks-first-students"' ), has( $page, 'type="date" class="wpcpm-ui-field__control wpcpm-ui-field__control--date" id="wpcpm-talks-follow-up"' ), has( $page, 'id="wpcpm-talks-next-step" name="next_step" maxlength="200"' ) ), array( true, true, true, true ) );
ck( 'every form carries novalidate, and the page names no physical side', array( substr_count( $page, '<form ' ), substr_count( $page, ' novalidate ' ), 1 === preg_match( '/(margin|padding)-(left|right)/', $page ) ), array( 2, 2, false ) );

/* ---- the stages follow the agreement ------------------------------------ */

echo "\n=== The stages follow the agreement (L2) ===\n";

fresh();
$before = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ];
ck( 'Confirmed asked for a school not settled is refused under its box, nothing saved', array( save_as( 7, array( 'stage' => 'Confirmed', 'next_step' => 'Celebrate' ), LONG ), flash_of( 7 )['problems']['stage'] ?? null, $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] === $before, $GLOBALS['sent'] ), array( back( LONG ), 'Confirmed is set when the Collaboration Agreement is accepted or recorded as on file.', true, array() ) );
$refused = draw( 7, LONG );
ck( 'the redraw says so as the form\'s first block, its sentence a link to the box (U6), the box tied, the typing kept, and the box shows the school\'s stage, not one it does not offer (U1)', array( has( $refused, '<a href="#wpcpm-talks-stage">Nothing was saved. Correct what is marked below and save again.</a>' ), has( $refused, 'id="wpcpm-talks-stage-problem"' ), as_drawn( $refused )['next_step'] ?? null, as_drawn( $refused )['stage'] ?? null ), array( true, true, 'Celebrate', 'Info Sent' ) );
ck( 'Student asked for a school not settled: the same sentence', array( save_as( 7, array( 'stage' => 'Student' ), LONG ), flash_of( 7 )['problems']['stage'] ?? null ), array( back( LONG ), 'Confirmed is set when the Collaboration Agreement is accepted or recorded as on file.' ) );
ck( 'an earlier stage asked for a settled school is refused in its own words', array( save_as( 7, array( 'stage' => 'Info Sent' ) ), flash_of( 7 )['problems']['stage'] ?? null, stage_of( S ) ), array( back(), 'This institution\'s Collaboration Agreement is settled, so its talks are at Confirmed or Student.', 'Confirmed' ) );
ck( 'an institution is never moved back to Lead', array( save_as( 7, array( 'stage' => 'Lead' ) ), flash_of( 7 )['problems']['stage'] ?? null ), array( back(), 'Lead is only for a school the program has not talked to yet.' ) );
ck( 'the stage it is at, saved unchanged, is always fine, even one its agreement does not lead to', array( save_as( 7, array( 'next_step' => 'Ask for the dean' ), DONE ), flash_of( 7 )['status'] ?? null, stage_of( DONE ) ), array( back( DONE ), 'saved', 'Confirmed' ) );
ck( 'a settled school moves from Confirmed to Student', array( save_as( 7, array( 'stage' => 'Student' ) ), stage_of( S ) ), array( back(), 'Student' ) );

/* ---- an acceptance the index still owes (R1) ----------------------------- */

echo "\n=== An acceptance the index still owes (fix round 2, R1) ===\n";

/** Owe the index of a school an acceptance, as the agreement path keeps a change its patch could not make. */
function owe_accepted( $key ) {
	$GLOBALS['opts'][ WPCPM_Institution_Agreement::OPT_INDEX_OWED ] = array( $key => array( 'block' => array( 'status' => 'Accepted', 'kind' => 'Template', 'accepted_on' => '2026-11-18' ), 'confirm' => '', 'ground' => '', 'actor' => 0 ) );
}
fresh();
owe_accepted( LONG );
$before = $GLOBALS['opts'];
ck( 'the drawing counts the owed acceptance as settled for the stages, and writes nothing', array( options_of( draw( 7, LONG ), 'stage' ), $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] === $before[ WPCPM_Institutions_Index::OPT_NAME ], $GLOBALS['opts'][ WPCPM_Institution_Agreement::OPT_INDEX_OWED ] === $before[ WPCPM_Institution_Agreement::OPT_INDEX_OWED ] ), array( array( 'Info Sent', 'Confirmed', 'Student', 'Not Moving Forward', 'SPAM', 'Revisit Later' ), true, true ) );
$GLOBALS['held'][ WPCPM_Institutions_Index::OPT_LOCK ] = true;
ck( 'a save while the change cannot be paid answers busy and writes nothing', array( save_as( 7, array( 'stage' => 'Confirmed' ), LONG ), flash_of( 7 )['status'] ?? null, stage_of( LONG ), WPCPM_Institution_Agreement::owes_index( LONG ) ), array( back( LONG ), 'busy', 'Info Sent', true ) );
$GLOBALS['held'] = array();
ck( 'a save pays it first, then follows it: Confirmed saves', array( save_as( 7, array( 'stage' => 'Confirmed' ), LONG ), stage_of( LONG ), WPCPM_Institution_Agreement::owes_index( LONG ), WPCPM_Institutions_Index::row( LONG )['agreement']['status'] ), array( back( LONG ), 'Confirmed', false, 'Accepted' ) );
// Fix wave, D2: an owed acceptance that moves the stage, paid by the save, leaves no request inside the save's locks.
fresh();
$GLOBALS['opts'][ WPCPM_Institution_Agreement::OPT_INDEX_OWED ] = array( LONG => array( 'block' => array( 'status' => 'Accepted', 'kind' => 'Template', 'accepted_on' => '2026-11-18' ), 'confirm' => '2026-11-18', 'ground' => 'manager', 'actor' => 7 ) );
$GLOBALS['at_sends']    = array();
$GLOBALS['school_free'] = array();
$GLOBALS['holding']     = array();
$GLOBALS['marked']      = array();
$GLOBALS['during_send'] = static function ( $record ) {
	$free = WPCPM_Institution_Agreement::take_lock( $record );
	if ( $free ) {
		WPCPM_Institution_Agreement::release_lock( $record );
	}
	$GLOBALS['at_sends'][] = $free;
	// And the Talks save's own school lock is let go too.
	$GLOBALS['school_free'][] = empty( $GLOBALS['holding'][ WPCPM_Institution_Request::school_lock_name( $record ) ] );
};
$paid_save              = save_as( 7, array( 'stage' => 'Confirmed', 'next_step' => 'Book the signing' ), LONG );
$GLOBALS['during_send'] = null;
ck( 'D2: a save that pays an owed move to Confirmed marks the stage for the copy, and every send leaves after both locks are let go', array( $paid_save, stage_of( LONG ), $GLOBALS['marked'] ?? array(), $GLOBALS['at_sends'], array_map( static function ( $sent ) { return $sent[0]; }, $GLOBALS['sent'] ) ), array( back( LONG ), 'Confirmed', array( array( LONG, array( 'stage', 'confirmed_on' ) ), array( LONG, array( 'next_step' ) ) ), array( true ), array( LONG ) ) );
ck( 'D2: and the school\'s own lock, the Talks save\'s, is free when the send leaves', $GLOBALS['school_free'], array( true ) );

// The outside review, O5: the copy's mark is written inside the locks, right after the local write, so a request that
// dies before its send() leaves the keys owed for the hourly retry.
fresh();
$GLOBALS['marked']         = array();
$GLOBALS['marked_holding'] = array();
$GLOBALS['marked_agreement_lock'] = array();
$GLOBALS['during_send']    = static function ( $record ) {
	throw new Exception( 'died before the send' );
};
$o5_died                   = save_as( 7, array( 'next_step' => 'Marked before the send' ) );
$GLOBALS['during_send']    = null;
ck( 'o5_talks_marked_in_locks: a save whose send() dies leaves its keys marked, the mark made while the school\'s lock and its agreement lock were held, the change saved',
	array( $o5_died, $GLOBALS['marked'], array_values( array_intersect( array( WPCPM_Institution_Request::school_lock_name( S ) ), $GLOBALS['marked_holding'][0] ?? array() ) ), $GLOBALS['marked_agreement_lock'], WPCPM_Institution_Talks::facts( S )['next_step'] ),
	array( 'died before the send', array( array( S, array( 'next_step' ) ) ), array( WPCPM_Institution_Request::school_lock_name( S ) ), array( true ), 'Marked before the send' ) );
fresh();
owe_accepted( LONG );
ck( 'and an earlier stage is refused for it in the settled sentence', array( save_as( 7, array( 'stage' => 'Under Review' ), LONG ), flash_of( 7 )['problems']['stage'] ?? null, stage_of( LONG ) ), array( back( LONG ), 'This institution\'s Collaboration Agreement is settled, so its talks are at Confirmed or Student.', 'Info Sent' ) );
$sheet = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' );
ck( 'the message part\'s warning tone is the status tags\' amber, on the logical start (R3)', 1 === preg_match( '/\.wpcpm-dashboard\.wpcpm-institution\.wpcpm-ui-on \[data-wpcpm-card-message\]\.is-warning \{\s*border-inline-start-color: var\( --wpcpm-f-amber \);\s*\}/', $sheet ), true );

/* ---- Review Focus 1 ----------------------------------------------------- */

echo "\n=== Review Focus 1: out of the active stages, and back ===\n";

fresh();
$rows_before = count( WPCPM_Institution_Audit::entries_for( S, 0 ) );
ck( 'a settled school, Confirmed to Not Moving Forward without the question answered, saves nothing and asks', array( save_as( 7, array( 'stage' => 'Not Moving Forward', 'next_step' => 'Ask once more in spring' ) ), stage_of( S ), count( WPCPM_Institution_Audit::entries_for( S, 0 ) ), WPCPM_Institution_Agreement::is_settled( S ), $GLOBALS['detach'] ), array( back(), 'Confirmed', $rows_before, true, array() ) );
$asked = draw( 7 );
ck( 'with the sentence for its 2 accounts, the typing kept and the stage asked about in the hidden field (L6)', array( has( plain( $asked ), 'Moving Wyższa Szkoła Górnołąćka to Not Moving Forward removes the access of its 2 accounts. Save again to confirm.' ), as_drawn( $asked )['stage'], as_drawn( $asked )['next_step'], as_drawn( $asked )['leave'] ?? null ), array( true, 'Not Moving Forward', 'Ask once more in spring', 'Not Moving Forward' ) );
ck( 'the answer confirms that stage alone: SPAM posted with it asks again, nothing saved (L6)', array( post_as( 7, 'handle_save', array_merge( as_drawn( $asked ), array( 'stage' => 'SPAM' ) ) ), flash_of( 7 )['status'] ?? null, stage_of( S ) ), array( back(), 'leave', 'Confirmed' ) );
draw( 7 );
save_as( 7, array( 'stage' => 'Not Moving Forward', 'next_step' => 'Ask once more in spring' ) );
$asked = draw( 7 );
$GLOBALS['during_send'] = static function ( $record ) {
	$GLOBALS['at_send'] = WPCPM_Institution_Agreement::take_lock( $record );
	WPCPM_Institution_Agreement::release_lock( $record );
};
ck( 'saved again with the question answered: the stage is saved', array( post_as( 7, 'handle_save', as_drawn( $asked ) ), stage_of( S ) ), array( back(), 'Not Moving Forward' ) );
$GLOBALS['during_send'] = null;
ck( 'close_gate() deleted the option and detached both, revoked, by the Administrator', array( array_key_exists( WPCPM_Institution_Agreement::option_name( S ), $GLOBALS['opts'] ), $GLOBALS['detach'] ), array( false, array( array( 40, 'revoked', 7 ), array( 41, 'revoked', 7 ) ) ) );
ck( 'the copy sent Current Stage and the next step, in one send, once both locks were let go (L4)', array( $GLOBALS['sent'], $GLOBALS['at_send'] ?? null ), array( array( array( S, array( 'stage', 'next_step' ) ) ), true ) );
$closed_text = plain( draw( 7 ) );
ck( 'the outcome says the talks are saved and the access of the 2 accounts was removed', array( has( $closed_text, 'The talks are saved.' ), has( $closed_text, 'The access of its 2 accounts was removed.' ) ), array( true, true ) );
$GLOBALS['members'] = array( 40 => S, 41 => S );
ck( 'back to Confirmed, the settled school\'s gate is rebuilt from its own record, the Drive link there', array( save_as( 7, array( 'stage' => 'Confirmed' ) ), stage_of( S ), WPCPM_Institution_Agreement::option( S )['drive_url'] ?? null, WPCPM_Institution_Agreement::is_settled( S ) ), array( back(), 'Confirmed', 'https://drive.google.com/file/d/TALKSDOC001/view', true ) );
ck( 'and its members\' gated actions are allowed again, once re-added, and the outcome says it is open', array( WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_VIEW_ROSTER, WPCPM_Institution_Policy::subject_institution( S ), 40 )['allowed'], has( plain( draw( 7 ) ), 'Its Institution Dashboard is open again.' ) ), array( true, true ) );

fresh();
$GLOBALS['members'] = array( 42 => LONG, 43 => LONG );
save_as( 7, array( 'stage' => 'Not Moving Forward' ), LONG );
save_as( 7, array( 'stage' => 'Not Moving Forward', 'leave' => 'Not Moving Forward' ), LONG );
ck( 'a school not settled, with two accounts: out to Not Moving Forward, its two detached', array( stage_of( LONG ), $GLOBALS['detach'] ), array( 'Not Moving Forward', array( array( 42, 'revoked', 7 ), array( 43, 'revoked', 7 ) ) ) );
ck( 'and back to Info Sent with no question, its gate rebuilt, still not settled', array( save_as( 7, array( 'stage' => 'Info Sent' ), LONG ), stage_of( LONG ), is_array( WPCPM_Institution_Agreement::option( LONG ) ), WPCPM_Institution_Agreement::is_settled( LONG ) ), array( back( LONG ), 'Info Sent', true, false ) );

// T4 (the trial's round, after the review): back in the active stages, the card speaks only of the accounts the leaving
// removed (reason revoked) that are a member of no school now, with their count.
$t4_one  = 'The account removed when it left does not come back by itself: add it again with Re-add, under Former members on Manage members.';
$t4_many = 'The 2 accounts removed when it left do not come back by themselves: add them again with Re-add, under Former members on Manage members.';
$t4_old  = 'Its former members do not come back by themselves';
$t4_any  = 'removed when it left';
fresh();
save_as( 7, array( 'stage' => 'Not Moving Forward', 'leave' => 'Not Moving Forward' ) );
save_as( 7, array( 'stage' => 'Confirmed' ) );
$t4_two = plain( draw( 7 ) );
fresh();
$GLOBALS['members'] = array( 42 => LONG );
save_as( 7, array( 'stage' => 'Not Moving Forward', 'leave' => 'Not Moving Forward' ), LONG );
save_as( 7, array( 'stage' => 'Info Sent' ), LONG );
$t4_case    = draw( 7, LONG );
$t4_single  = plain( $t4_case );
fresh();
$GLOBALS['members'] = array( 42 => LONG, 43 => LONG );
WPCPM_Institution_Members::detach( 42, 'removed', 7 );
WPCPM_Institution_Members::detach( 43, 'left', 43 );
save_as( 7, array( 'stage' => 'Not Moving Forward', 'leave' => 'Not Moving Forward' ), LONG );
save_as( 7, array( 'stage' => 'Info Sent' ), LONG );
$t4_on_purpose = plain( draw( 7, LONG ) );
fresh();
$GLOBALS['members'] = array( 42 => LONG, 43 => LONG );
save_as( 7, array( 'stage' => 'Not Moving Forward', 'leave' => 'Not Moving Forward' ), LONG );
$GLOBALS['members'][43] = S;
save_as( 7, array( 'stage' => 'Info Sent' ), LONG );
$t4_moved = plain( draw( 7, LONG ) );
fresh();
WPCPM_Settings::$values['institution_on_inactive'] = 'keep';
save_as( 7, array( 'stage' => 'Not Moving Forward', 'leave' => 'Not Moving Forward' ) );
save_as( 7, array( 'stage' => 'Confirmed' ) );
$t4_kept = plain( draw( 7 ) );
ck( 'T4: back in the talks: two accounts the leaving revoked, the plural with the count after "open again"; one, the singular; removed or left on purpose, nothing; one now another school\'s, not counted; members kept, nothing', array(
	has( $t4_two, 'Its Institution Dashboard is open again. ' . $t4_many ),
	has( $t4_single, $t4_one ),
	has( $t4_on_purpose, $t4_any ),
	has( $t4_moved, $t4_one ),
	has( $t4_kept, $t4_any ),
	has( $t4_two . $t4_single . $t4_on_purpose . $t4_moved . $t4_kept, $t4_old ),
), array( true, true, false, true, false, false ) );
$cases['back in the talks, its former members'] = $t4_case;
// T6: a move between two active stages says nothing about accounts, even with a former member the leaving revoked who
// is a member nowhere now (T2's pin, in T4's terms).
fresh();
$GLOBALS['members'] = array( 42 => LONG );
save_as( 7, array( 'stage' => 'Not Moving Forward', 'leave' => 'Not Moving Forward' ), LONG );
save_as( 7, array( 'stage' => 'First Contact Made' ), LONG );
draw( 7, LONG );
$t6_former = count( WPCPM_Institution_Members::former_members_of( LONG ) );
save_as( 7, array( 'stage' => 'Info Sent' ), LONG );
$t6_between = plain( draw( 7, LONG ) );
ck( 'T6: between two active stages, First Contact Made to Info Sent, with a revoked former member: no removed-when-it-left sentence in either form', array( $t6_former, has( $t6_between, 'The talks are saved.' ), has( $t6_between, $t4_any ) ), array( 1, true, false ) );

fresh();
WPCPM_Settings::$values['institution_on_inactive'] = 'keep';
save_as( 7, array( 'stage' => 'Not Moving Forward' ) );
ck( 'with institution_on_inactive keep, the sentence says the page locks', has( plain( draw( 7 ) ), 'Moving Wyższa Szkoła Górnołąćka to Not Moving Forward locks its Institution Dashboard. Save again to confirm.' ), true );
save_as( 7, array( 'stage' => 'Not Moving Forward', 'leave' => 'Not Moving Forward' ) );
ck( 'and confirmed, the gate closes and nobody is detached; the members\' gated actions are refused', array( stage_of( S ), WPCPM_Institution_Agreement::is_settled( S ), $GLOBALS['detach'], WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_VIEW_ROSTER, WPCPM_Institution_Policy::subject_institution( S ), 40 )['allowed'] ), array( 'Not Moving Forward', false, array(), false ) );
ck( 'moving it back to Confirmed: the option is rebuilt with its Drive link, and the members\' gated actions are allowed again', array( save_as( 7, array( 'stage' => 'Confirmed' ) ), WPCPM_Institution_Agreement::option( S )['drive_url'] ?? null, WPCPM_Institution_Policy::decide( WPCPM_Institution_Policy::ACT_VIEW_ROSTER, WPCPM_Institution_Policy::subject_institution( S ), 40 )['allowed'] ), array( back(), 'https://drive.google.com/file/d/TALKSDOC001/view', true ) );

fresh();
$GLOBALS['members'] = array();
save_as( 7, array( 'stage' => 'SPAM' ) );
ck( 'a school with no accounts: the page locks, in words', has( plain( draw( 7 ) ), 'Moving Wyższa Szkoła Górnołąćka to SPAM locks its Institution Dashboard. Save again to confirm.' ), true );

/* ---- the locks ---------------------------------------------------------- */

echo "\n=== The locks ===\n";

fresh();
$GLOBALS['opts'][ WPCPM_Institution_Agreement::lock_name( S ) ] = time();
$before = $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ];
ck( 'with the school\'s agreement lock held (an acceptance, a recording on file), the save answers busy and writes nothing', array( save_as( 7, array( 'stage' => 'Student', 'next_step' => 'Wait' ) ), flash_of( 7 )['status'] ?? null, $GLOBALS['opts'][ WPCPM_Institutions_Index::OPT_NAME ] === $before, WPCPM_Institution_Talks::facts( S )['next_step'], $GLOBALS['sent'] ), array( back(), 'busy', true, 'Send the brochure', array() ) );
$busy_page = draw( 7 );
ck( 'it says so in words, and keeps the typing', array( has( plain( $busy_page ), 'Someone else is changing these talks just now, so nothing was saved. Try again in a moment.' ), as_drawn( $busy_page )['next_step'] ?? null, as_drawn( $busy_page )['stage'] ?? null ), array( true, 'Wait', 'Student' ) );
unset( $GLOBALS['opts'][ WPCPM_Institution_Agreement::lock_name( S ) ] );
$GLOBALS['held'][ WPCPM_Institution_Request::school_lock_name( S ) ] = true;
ck( 'with the school\'s lock held (the details card saving), busy too, nothing written', array( save_as( 7, array( 'next_step' => 'Wait' ) ), flash_of( 7 )['status'] ?? null, WPCPM_Institution_Talks::facts( S )['next_step'] ), array( back(), 'busy', 'Send the brochure' ) );
$GLOBALS['held'] = array();
$GLOBALS['during_mail'] = static function () {
	$GLOBALS['inside'] = array( WPCPM_Institution_Agreement::take_lock( S ), WPCPM_Institution_Agreement::rebuild( S, array( 'status' => 'Accepted' ) ) );
};
save_as( 7, array( 'next_step' => 'Wait for the senate', 'wpcpm_talks_assigned' => '12' ) );
$GLOBALS['during_mail'] = null;
ck( 'and the reverse: while the save holds the agreement lock, no other path takes it and no rebuild runs', $GLOBALS['inside'] ?? null, array( false, array() ) );
ck( 'and it is let go after the save', array( WPCPM_Institution_Agreement::take_lock( S ), array_key_exists( WPCPM_Institution_Agreement::lock_name( S ), $GLOBALS['opts'] ) ), array( true, true ) );
WPCPM_Institution_Agreement::release_lock( S );
$GLOBALS['held'][ WPCPM_Institutions_Index::OPT_LOCK ] = true;
ck( 'a patch the index lock refuses saves nothing and says so', array( save_as( 7, array( 'stage' => 'Student', 'next_step' => 'Never' ) ), flash_of( 7 )['text'] ?? null, stage_of( S ), WPCPM_Institution_Talks::facts( S )['next_step'] ), array( back(), 'The change could not be saved on the site, so nothing was changed. Try again in a while.', 'Confirmed', 'Wait for the senate' ) );
$GLOBALS['held'] = array();

/* ---- a record that could not be kept ----------------------------------- */

echo "\n=== A record that could not be kept (L1) ===\n";

fresh();
$GLOBALS['meta_fails'] = WPCPM_Institution_Record::META_ANSWERS;
ck( 'the record\'s keys alone: refused in the details card\'s sentence (U9)', array( save_as( 7, array( 'next_step' => 'Lost' ) ), flash_of( 7 )['text'] ?? null ), array( back(), 'The change could not be saved on the site, so nothing was changed. Try again in a while.' ) );
ck( 'with the stage too: the stage stands, the record\'s keys are named as not saved', array( save_as( 7, array( 'stage' => 'Student', 'next_step' => 'Lost', 'follow_up' => '2027-03-01' ) ), stage_of( S ), WPCPM_Institution_Talks::facts( S )['next_step'], flash_of( 7 )['status'] ?? null ), array( back(), 'Student', 'Send the brochure', 'partial' ) );
ck( 'no talks_saved row and no copy names them', array( count( rows_of( S, 'talks_saved' ) ), $GLOBALS['sent'] ), array( 0, array( array( S, array( 'stage' ) ) ) ) );
$GLOBALS['meta_fails'] = '';
$partial = draw( 7 );
ck( 'the outcome is ONE warning naming what was saved and what was not, and the typing stays in the form (R2)', array( substr_count( $partial, 'data-wpcpm-card-message' ), has( $partial, 'is-warning' ), has( plain( $partial ), 'Not everything was saved. Saved: Stage. Not saved: Next step and Follow up on. Save again.' ), has( plain( $partial ), 'The talks are saved.' ), as_drawn( $partial )['next_step'] ?? null, as_drawn( $partial )['follow_up'] ?? null ), array( 1, true, true, false, 'Lost', '2027-03-01' ) );
fresh();
$GLOBALS['members']    = array();
$GLOBALS['meta_fails'] = WPCPM_Institution_Record::META_ANSWERS;
save_as( 7, array( 'stage' => 'Revisit Later', 'leave' => 'Revisit Later' ) );
$GLOBALS['meta_fails'] = '';
$revisit_partial       = plain( draw( 7 ) );
ck( 'a move to Revisit Later whose follow-up could not be kept says nothing of the 90 days, and names the stage as saved (R2)', array( has( $revisit_partial, 'days ahead' ), has( $revisit_partial, 'Not everything was saved. Saved: Stage. Not saved: Follow up on. Save again. Its Institution Dashboard is locked now.' ) ), array( false, true ) );
$cases['a warning outcome, part of a save kept'] = $partial;

/* ---- Revisit Later ----------------------------------------------------- */

echo "\n=== Revisit Later ===\n";

fresh();
$ahead = gmdate( 'Y-m-d', time() + 90 * DAY_IN_SECONDS );
save_as( 7, array( 'stage' => 'Revisit Later', 'leave' => 'Revisit Later' ) );
$revisit = draw( 7 );
ck( 'a move into it, the date left as drawn, sets the follow-up 90 days ahead and says so, then that the page is locked, two messages', array( stage_of( S ), WPCPM_Institution_Talks::facts( S )['follow_up'], has( plain( $revisit ), 'Follow up on is set to ' . gmdate( 'j F Y', strtotime( $ahead ) ) . ', 90 days ahead. Change it if you like.' ) ), array( 'Revisit Later', $ahead, true ) );
fresh();
WPCPM_Institution_Record::set( S, array( 'follow_up' => $ahead ) );
save_as( 7, array( 'stage' => 'Revisit Later', 'leave' => 'Revisit Later' ) );
ck( 'when the follow-up already is that day, the save does not change it and says nothing of it (L7)', has( plain( draw( 7 ) ), 'days ahead' ), false );
fresh();
save_as( 7, array( 'stage' => 'Revisit Later', 'leave' => 'Revisit Later', 'follow_up' => '2027-01-15' ) );
ck( 'a date typed in the same save wins', array( WPCPM_Institution_Talks::facts( S )['follow_up'], has( plain( draw( 7 ) ), 'days ahead' ) ), array( '2027-01-15', false ) );
fresh();
$GLOBALS['members'] = array();
save_as( 7, array( 'stage' => 'Revisit Later', 'leave' => 'Revisit Later' ) );
$two = draw( 7 );
ck( 'one save, one message: the outcome, then the 90 days and the page locked as running text in its block (R4)', array( substr_count( $two, 'data-wpcpm-card-message' ), 1 === preg_match( '#is-success[^>]*>The talks are saved\. Follow up on is set to [^<]*, 90 days ahead\. Change it if you like\. Its Institution Dashboard is locked now\.</p>#', $two ) ), array( 1, true ) );

/* ---- rows, the copy and the assignment --------------------------------- */

echo "\n=== The rows and the copy ===\n";

fresh();
$GLOBALS['mails'] = array();
ck( 'a save changing the stage, the next step, the follow-up and the assignee lands on the card', save_as( 7, array( 'stage' => 'Student', 'next_step' => 'Wait for the senate', 'follow_up' => '2026-12-01', 'wpcpm_talks_assigned' => '12' ) ), back() );
$stage_rows = rows_of( S, 'talks_stage' );
$saved_rows = rows_of( S, 'talks_saved' );
ck( 'one talks_stage row, from and to', array( count( $stage_rows ), $stage_rows[0]['data'] ?? null, $stage_rows[0]['actor'] ?? null ), array( 1, array( 'from' => 'Confirmed', 'to' => 'Student' ), 7 ) );
ck( 'one talks_saved row naming the fields as the lead\'s save does', array( count( $saved_rows ), $saved_rows[0]['message'] ?? null, $saved_rows[0]['data'] ?? null ), array( 1, 'The talks were updated: Next step and Follow up on.', array( 'changed' => array( 'next_step', 'follow_up' ) ) ) );
ck( 'its History sentence', has( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-history.php' ), "'talks_saved'           => __( 'The talks with the institution were updated.'" ), true );
ck( 'one assignment_changed row, and the email to the new assignee with the Talks card\'s address', array( count( rows_of( S, 'assignment_changed' ) ), count( $GLOBALS['mails'] ), $GLOBALS['mails'][0][0] ?? null, $GLOBALS['mails'][0][2]['institution_link'] ?? null ), array( 1, 1, 12, 'https://example.test/institution-dashboard/?wpcpm_view=administrators&wpcpm_institution_view=' . S . '#wpcpm-talks' ) );
ck( 'the copy sent exactly the changed copy columns, once', $GLOBALS['sent'], array( array( S, array( 'stage', 'next_step', 'follow_up' ) ) ) );
ck( 'the outcome says the assignee was emailed', has( plain( draw( 7 ) ), 'The talks are saved, and the assigned Administrator has been emailed.' ), true );
$GLOBALS['sent'] = array();
$count = count( WPCPM_Institution_Audit::entries_for( S, 0 ) );
ck( 'an unchanged save writes nothing and says Nothing was changed.', array( save_as( 7, array() ), count( WPCPM_Institution_Audit::entries_for( S, 0 ) ), $GLOBALS['sent'], has( plain( draw( 7 ) ), 'Nothing was changed.' ) ), array( back(), $count, array(), true ) );
save_as( 7, array( 'country' => RO, 'estimated_students' => '120', 'first_students' => '2027-02-01' ) );
WPCPM_Institutions_Index::forget_cached();
ck( 'the country goes to the index row with its name, and the copy sends Country and Estimated students, never First students begin (the outside review, O9)', array( WPCPM_Institutions_Index::row( S )['country'], WPCPM_Institutions_Index::row( S )['country_name'], $GLOBALS['sent'], WPCPM_Institution_Talks::facts( S )['estimated_students'], WPCPM_Institution_Talks::facts( S )['first_students'] ), array( RO, 'România', array( array( S, array( 'country', 'estimated_students' ) ) ), '120', '2027-02-01' ) );
$GLOBALS['sent'] = array();
ck( 'a field the rules refuse is refused under its box, nothing saved, the sentence a link to the first box marked', array( save_as( 7, array( 'estimated_students' => 'many', 'first_students' => '2027-02-31', 'next_step' => str_repeat( 'x', 201 ) ) ), array_keys( flash_of( 7 )['problems'] ?? array() ), $GLOBALS['sent'], has( draw( 7 ), '<a href="#wpcpm-talks-next-step">Nothing was saved.' ) ), array( back(), array( 'next_step', 'estimated_students', 'first_students' ), array(), true ) );

/* ---- a lead ------------------------------------------------------------- */

echo "\n=== A lead ===\n";

fresh();
$lead = WPCPM_Institution_Leads::add( array( 'name' => '架空山学園大学', 'country' => JP, 'contact_person' => 'Kōhō Tantō', 'contact_email' => 'koho@example.test', 'assigned' => 7, 'source' => 'test' ), 7, false );
$lead_page = draw( 7, $lead );
ck( 'a lead\'s stage offers Lead and the promotion stages, nothing else', options_of( $lead_page, 'stage' ), array_merge( array( 'Lead' ), WPCPM_Institution_Leads::promotion_stages() ) );
ck( 'its own fields after Country, its City first (Task 16 fix round 1, V7), Contact\'s email so named (U9), and Close this lead a quiet press of its own, with its own nonce', array( field_order( $lead_page ), has( $lead_page, ">Contact's email</label>" ) || has( $lead_page, '>Contact&#039;s email</label>' ), has( $lead_page, 'class="wpcpm-btn wpcpm-btn--quiet" name="action" value="wpcpm_talks_close">Close this lead' ), has( $lead_page, 'name="wpcpm_talks_lead_nonce" value="nonce-wpcpm_talks_close_' . $lead . '"' ) ), array( array( 'stage', 'next-step', 'follow-up', 'assigned', 'country', 'city', 'contact-person', 'contact-email', 'help-desk', 'estimated-students', 'first-students', 'program-details' ), true, true, true ) );
$lead_back = 'redirect:https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=' . $lead . '#wpcpm-talks';
ck( 'a lead\'s City is saved on its card (Task 16 fix round 1, V7); an institution\'s card has no City box', array( save_as( 7, array( 'city' => 'Nagoya' ), $lead ), WPCPM_Institution_Talks::facts( $lead )['city'], has( draw( 7 ), 'name="city"' ) ), array( 'redirect:https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=' . $lead . '#wpcpm-talks', 'Nagoya', false ) );
ck( 'its fields go through the leads\' update', array( save_as( 7, array( 'next_step' => 'Write to the rector', 'contact_email' => 'Rector@Example.test' ), $lead ), WPCPM_Institution_Talks::facts( $lead )['next_step'], WPCPM_Institution_Talks::facts( $lead )['contact_email'], $GLOBALS['sent'] ), array( $lead_back, 'Write to the rector', 'rector@example.test', array() ) );
ck( 'Close this lead pressed with nothing typed closes it and says so as before', array( post_as( 7, 'handle_close', array_merge( as_drawn( draw( 7, $lead ) ), array( 'action' => 'wpcpm_talks_close' ) ) ), WPCPM_Institution_Talks::facts( $lead )['closed'], flash_of( 7 )['status'] ?? null ), array( $lead_back, true, 'closed' ) );
$closed_page = draw( 7, $lead );
ck( 'a closed lead: the neutral tag Closed with its line beside it, Lead alone offered, Reopen this lead (U8)', array( has( $closed_page, '<span class="wpcpm-status">Closed</span><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact">Reopen this lead to move it on.</span>' ), options_of( $closed_page, 'stage' ), has( $closed_page, 'Reopen this lead</button>' ) ), array( true, array( 'Lead' ), true ) );
$cases['a closed lead, its line'] = $closed_page;
ck( 'Close on a closed lead with only its stage differing is no typed work (R6)', array( post_as( 7, 'handle_reopen', array_merge( as_drawn( $closed_page ), array( 'stage' => 'First Contact Made', 'action' => 'wpcpm_talks_reopen', 'wpcpm_talks_lead_nonce' => 'nonce-wpcpm_talks_reopen_' . $lead ) ) ), flash_of( 7 )['status'] ?? null ), array( $lead_back, 'reopened' ) );
WPCPM_Institution_Leads::close( $lead, 7 );
ck( 'a closed lead\'s fields stay editable, as update() allows', array( save_as( 7, array( 'next_step' => 'Later' ), $lead ), WPCPM_Institution_Talks::facts( $lead )['next_step'] ), array( $lead_back, 'Later' ) );
ck( 'promoting a closed lead with other changes: the promotion\'s sentence, then The other changes are saved. (L3)', array( save_as( 7, array( 'stage' => 'First Contact Made', 'next_step' => 'Even later' ), $lead ), flash_of( 7 )['text'] ?? null, flash_of( 7 )['said'] ?? null, WPCPM_Institution_Talks::facts( $lead )['next_step'] ), array( $lead_back, 'This lead is closed. Reopen it before you move it on.', array( 'The other changes are saved.' ), 'Even later' ) );
ck( 'and with no other change, the promotion\'s sentence alone', array( save_as( 7, array( 'stage' => 'First Contact Made' ), $lead ), flash_of( 7 )['said'] ?? null ), array( $lead_back, array() ) );
ck( 'Reopen this lead over a change typed and not saved: reopened, the typing kept, and said so (U2)', array( post_as( 7, 'handle_reopen', array_merge( as_drawn( draw( 7, $lead ) ), array( 'next_step' => 'Typed, not saved', 'action' => 'wpcpm_talks_reopen', 'wpcpm_talks_lead_nonce' => 'nonce-wpcpm_talks_reopen_' . $lead ) ) ), WPCPM_Institution_Talks::facts( $lead )['closed'], WPCPM_Institution_Talks::facts( $lead )['next_step'], has( plain( draw( 7, $lead ) ), 'The lead is reopened. What you typed is not saved yet: press Save to keep it.' ) ), array( $lead_back, false, 'Even later', true ) );
post_as( 7, 'handle_close', array_merge( as_drawn( draw( 7, $lead ) ), array( 'next_step' => 'Typed again', 'action' => 'wpcpm_talks_close' ) ) );
$typed_close = draw( 7, $lead );
ck( 'and Close this lead alike, the typing back in its box', array( has( plain( $typed_close ), 'The lead is closed. What you typed is not saved yet: press Save to keep it.' ), as_drawn( $typed_close )['next_step'] ?? null ), array( true, 'Typed again' ) );
post_as( 7, 'handle_reopen', array_merge( as_drawn( draw( 7, $lead ) ), array( 'action' => 'wpcpm_talks_reopen', 'wpcpm_talks_lead_nonce' => 'nonce-wpcpm_talks_reopen_' . $lead ) ) );
ck( 'a close posted with the save\'s nonce is refused by its own', post_as( 7, 'handle_close', array( 'key' => $lead, '_wpnonce' => 'nonce-wpcpm_talks_save_' . $lead ) ), 'nonce:wpcpm_talks_close_' . $lead );
$GLOBALS['answer'] = 'recNEW00000000001';
ck( 'a lead saved at First Contact Made is promoted, and lands on the new school\'s Talks card', save_as( 7, array( 'stage' => 'First Contact Made' ), $lead ), back( 'recNEW00000000001' ) );
ck( 'the new school is in the index at the stage, the lead is gone, and the card says so in its new words (U9)', array( stage_of( 'recNEW00000000001' ), WPCPM_Institution_Talks::facts( $lead ), has( plain( draw( 7, 'recNEW00000000001' ) ), 'The lead is an institution now. Its talks go on here.' ) ), array( 'First Contact Made', null, true ) );

// Round 2, point 1: a lead whose move did not finish shows its standing warning only while the card has no outcome to
// show, so a refused Close says the sentence once, as its outcome, and a save's outcome is the card's first message.
fresh();
$half      = WPCPM_Institution_Leads::add( array( 'name' => 'Szkoła W Połowie Drogi', 'country' => JP, 'assigned' => 7, 'source' => 'test' ), 7, false );
$half_post = (int) substr( $half, strlen( WPCPM_Institution_Talks::LEAD_PREFIX ) );
update_post_meta( $half_post, WPCPM_Institution_Leads::META_PROMOTED_STAGE, 'Info Sent' );
update_post_meta( $half_post, WPCPM_Institution_Leads::META_PROMOTED, 'recEXA00000000098' );
$half_said   = 'This lead\'s move into the talks as an institution did not finish. Choose the stage again and save to finish it.';
$first_said  = static function ( $html ) { return preg_match( '#<p class="[^"]*" role="[a-z]+" tabindex="-1" data-wpcpm-card-message>(.*?)</p>#s', $html, $m ) ? html_entity_decode( strip_tags( $m[1] ), ENT_QUOTES ) : ''; };
$half_loaded = draw( 7, $half );
post_as( 7, 'handle_close', array_merge( as_drawn( $half_loaded ), array( 'action' => 'wpcpm_talks_close' ) ) );
$half_closed = draw( 7, $half );
save_as( 7, array( 'next_step' => 'Ask Airtable' ), $half );
$half_saved  = draw( 7, $half );
$half_again  = draw( 7, $half );

ck( 'unfinished_warning_once: as loaded the warning stands once; after a refused Close the sentence stands once, as the outcome; after a save the outcome is the card\'s first message, and the warning is back on the next load',
	array(
		substr_count( html_entity_decode( $half_loaded, ENT_QUOTES ), $half_said ),
		substr_count( html_entity_decode( $half_closed, ENT_QUOTES ), $half_said ),
		has( $half_closed, 'wpcpm-talks__message--error' ),
		WPCPM_Institution_Talks::facts( $half )['closed'],
		$first_said( $half_saved ) !== $half_said && '' !== $first_said( $half_saved ),
		substr_count( html_entity_decode( $half_saved, ENT_QUOTES ), $half_said ),
		substr_count( html_entity_decode( $half_again, ENT_QUOTES ), $half_said ),
	),
	array( 1, 1, true, false, true, 0, 1 ) );

// F1 (final round): on its page, the standing warning of a lead whose move did not finish stands where the card's outcome
// does, inside the form, first, so it takes the outcome's place under the line of the tag and over the Stage field.
$half_page = static function ( $key ) {
	WPCPM_UI::enter( 'administrator' );
	ob_start();
	WPCPM_Institution_Talks::render( $key, array( 'named' => false ) );
	$h = (string) ob_get_clean();
	WPCPM_UI::leave();
	return $h;
};
$half_plain = $half_page( $half );
update_post_meta( $half_post, WPCPM_Institution_Leads::META_FIELDS, array_merge( (array) get_post_meta( $half_post, WPCPM_Institution_Leads::META_FIELDS, true ), array( 'follow_up' => '2020-01-06' ) ) );
$half_due = $half_page( $half );
ck( 'F1: the standing warning is the form\'s first block, where the outcome stands, with the tag line above it and without', array(
	1 === preg_match( '#<form [^>]*>(?:<input [^>]*>)*<p class="[^"]*wpcpm-talks__message[^"]*"[^>]*>[^<]*' . preg_quote( esc_html( $half_said ), '#' ) . '#', $half_plain ),
	1 === preg_match( '#<form [^>]*>(?:<input [^>]*>)*<p class="[^"]*wpcpm-talks__message[^"]*"[^>]*>[^<]*' . preg_quote( esc_html( $half_said ), '#' ) . '#', $half_due ),
	has( $half_due, 'Past due' ),
), array( true, true, true ) );
$GLOBALS['admin_cases']['a lead whose move did not finish, its follow-up past due'] = $half_due;
$GLOBALS['admin_cases']['a lead whose move did not finish, no tag'] = $half_plain;

/* ---- notes -------------------------------------------------------------- */

echo "\n=== O9: Estimated students reaches the copy ===\n";

fresh();
save_as( 7, array( 'estimated_students' => '55' ) );
ck( 'o9_talks_estimated_sent: a save that changes Estimated students sends it to the copy', array( WPCPM_Institution_Talks::facts( S )['estimated_students'], $GLOBALS['sent'] ), array( '55', array( array( S, array( 'estimated_students' ) ) ) ) );
ck( 'and the stand-in\'s columns are the copy\'s', array_keys( WPCPM_Institution_Copy::COLUMNS ), array( 'name', 'stage', 'country', 'city', 'website', 'contact_person', 'contact_email', 'confirmed_on', 'next_step', 'follow_up', 'last_contact', 'estimated_students', 'help_desk' ) );

echo "\n=== Notes ===\n";

fresh();
$rows_before = count( WPCPM_Institution_Audit::entries_for( S, 0 ) );
$note_post   = array( 'action' => 'wpcpm_talks_note', 'key' => S, '_wpnonce' => 'nonce-wpcpm_talks_note_' . S, 'note' => "Called the dean.\nShe will write." );
ck( 'Add the note adds one note, by the poster, today, and no history row', array( post_as( 7, 'handle_note', $note_post ), count( WPCPM_Institution_Talk_Notes::for_key( S ) ), WPCPM_Institution_Talk_Notes::for_key( S )[0]['author'], substr( WPCPM_Institution_Talk_Notes::for_key( S )[0]['date'], 0, 10 ), count( WPCPM_Institution_Audit::entries_for( S, 0 ) ) ), array( back( S, 'wpcpm-talks-note' ), 1, 'Ana Admin', gmdate( 'Y-m-d' ), $rows_before ) );
ck( 'its outcome is directly over the note form', 1 === preg_match( '#<div id="wpcpm-talks-note"><p class="wpcpm-talks__message[^>]*>The note is added\.</p><form #', draw( 7 ) ), true );
ck( 'a second press of the same words within a minute adds nothing, in the new words (U9)', array( post_as( 7, 'handle_note', $note_post ), count( WPCPM_Institution_Talk_Notes::for_key( S ) ), has( plain( draw( 7 ) ), 'That note is already added.' ) ), array( back( S, 'wpcpm-talks-note' ), 1, true ) );
$GLOBALS['held'][ WPCPM_Institution_Request::LOCK_PREFIX . 'talks_note_' . S ] = true;
ck( 'the guard and the add run under one short lock: a press while another holds it adds nothing (L8)', array( post_as( 7, 'handle_note', array_merge( $note_post, array( 'note' => 'At the same moment' ) ) ), count( WPCPM_Institution_Talk_Notes::for_key( S ) ), flash_of( 7 )['status'] ?? null, in_array( WPCPM_Institution_Request::LOCK_PREFIX . 'talks_note_' . S, $GLOBALS['taken'], true ) ), array( back( S, 'wpcpm-talks-note' ), 1, 'note-busy', true ) );
$GLOBALS['held'] = array();
// 1.123.102, Part C2: the card is drawn again after that refusal, and the note typed comes back in its box.
ck( 'c2_busy_kept: a note refused because another press held the lock comes back in its box, as typed', as_drawn_text( draw( 7 ), 'note' ), 'At the same moment' );
$GLOBALS['posts'][ WPCPM_Institution_Talk_Notes::for_key( S )[0]['id'] ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', time() - 120 );
ck( 'the same words two minutes later are a note of their own', array( post_as( 7, 'handle_note', $note_post ), count( WPCPM_Institution_Talk_Notes::for_key( S ) ) ), array( back( S, 'wpcpm-talks-note' ), 2 ) );
ck( 'an empty one says Write the note first., under its box', array( post_as( 7, 'handle_note', array_merge( $note_post, array( 'note' => '   ' ) ) ), flash_of( 7 )['problems']['note'] ?? null, count( WPCPM_Institution_Talk_Notes::for_key( S ) ) ), array( back( S, 'wpcpm-talks-note' ), 'Write the note first.', 2 ) );
ck( 'and the redraw\'s refusal over the note form links to the box (U6)', has( draw( 7 ), '<a href="#wpcpm-talks-note-text">The note was not added. Correct what is marked below and add it again.</a>' ), true );
// 1.123.102, Part C3: a note refused for its length comes back whole, every character typed, as the leads' add keeps one.
$c3_long = str_repeat( 'ą', WPCPM_Institution_Talk_Notes::MAX_LENGTH + 1 );
// Round 4, C1: a note over the limit that holds a literal entity, typed out, comes back exactly as typed: at its end, and in
// its middle.
$c1_end = str_repeat( 'x', WPCPM_Institution_Talk_Notes::MAX_LENGTH - 1 ) . '&amp;';
$c1_mid = str_repeat( 'x', 2500 ) . '&amp;' . str_repeat( 'y', 2500 );
$c1_end_post = post_as( 7, 'handle_note', array_merge( $note_post, array( 'note' => $c1_end ) ) );
$c1_end_back = array( $c1_end_post, flash_of( 7 )['status'] ?? null, as_drawn_text( draw( 7 ), 'note' ) === $c1_end );
$c1_mid_post = post_as( 7, 'handle_note', array_merge( $note_post, array( 'note' => $c1_mid ) ) );
$c1_mid_back = array( $c1_mid_post, flash_of( 7 )['status'] ?? null, as_drawn_text( draw( 7 ), 'note' ) === $c1_mid );
ck( 'c4_entity_whole: 4,999 characters and a typed &amp; (5,004 as typed) are refused and come back exactly as typed, and so does one with &amp; in the middle', array( $c1_end_back, $c1_mid_back ), array( array( back( S, 'wpcpm-talks-note' ), 'note-refused', true ), array( back( S, 'wpcpm-talks-note' ), 'note-refused', true ) ) );
// And the leads' add, which lands with its refused first note through land_added(): the same note comes back whole.
$c1_land = '';
try {
	WPCPM_Institution_Talks::land_added( S, 'A note can be at most 5000 characters long.', $c1_end );
} catch ( Exception $e ) {
	$c1_land = $e->getMessage();
}
ck( 'c4_land_whole: land_added() keeps the same note exactly as typed', array( $c1_land, as_drawn_text( draw( 7 ), 'note' ) === $c1_end ), array( back( S, 'wpcpm-talks-note' ), true ) );
ck( 'c3_long_whole: a note one character over the limit is refused, nothing added, and comes back whole in its box', array( post_as( 7, 'handle_note', array_merge( $note_post, array( 'note' => $c3_long ) ) ), flash_of( 7 )['status'] ?? null, count( WPCPM_Institution_Talk_Notes::for_key( S ) ), mb_strlen( as_drawn_text( draw( 7 ), 'note' ), 'UTF-8' ) ), array( back( S, 'wpcpm-talks-note' ), 'note-refused', 2, WPCPM_Institution_Talk_Notes::MAX_LENGTH + 1 ) );

// The outside review, O8: a note for a LEAD is added under the lead's own lock, and the lead is read from the table
// under it: held, the lead's busy sentence; gone, or moved into the talks, the leads' gone sentence; nothing written.
fresh();
$o8_lead  = WPCPM_Institution_Leads::add( array( 'name' => 'Example Note Lead', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
$o8_post  = (int) substr( $o8_lead, 5 );
$o8_lock  = WPCPM_Institution_Leads::LOCK_PREFIX . $o8_post;
$o8_note  = array( 'action' => 'wpcpm_talks_note', 'key' => $o8_lead, '_wpnonce' => 'nonce-wpcpm_talks_note_' . $o8_lead, 'note' => 'A note for the lead.' );
$o8_count = static function ( $key ) {
	return count( array_filter( $GLOBALS['pmeta'], static function ( $meta ) use ( $key ) { return ( $meta[ WPCPM_Institution_Talk_Notes::META_FOR ] ?? '' ) === $key; } ) );
};
$GLOBALS['held'][ $o8_lock ] = true;
post_as( 7, 'handle_note', $o8_note );
$GLOBALS['held'] = array();
ck( 'c2_lead_busy_kept: the lead\'s card is drawn again after its busy refusal, so that note is kept', in_array( 'talks-note:' . $o8_lead, kept_forms_of( 7 ), true ), true );
ck( 'o8_lead_lock_held: with the lead\'s lock held the note is refused with the lead\'s busy sentence, nothing written',
	array( flash_of( 7 )['status'] ?? null, flash_of( 7 )['problems']['note'] ?? null, $o8_count( $o8_lead ) ),
	array( 'note-refused', 'Somebody else is changing this lead. Try again in a while.', 0 ) );
ck( 'and with it free the note is added under the lead\'s lock',
	array( post_as( 7, 'handle_note', $o8_note ), flash_of( 7 )['status'] ?? null, $o8_count( $o8_lead ), in_array( $o8_lock, $GLOBALS['taken'], true ) ),
	array( 'redirect:https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=' . $o8_lead . '#wpcpm-talks-note', 'note-added', 1, true ) );

// Gone: another request deleted it after this one read it (the table changed, this request's cache did not).
fresh();
$o8_gone      = WPCPM_Institution_Leads::add( array( 'name' => 'Example Gone Lead', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
$GLOBALS['post_cache'] = true;
WPCPM_Institution_Talks::facts( $o8_gone );
$GLOBALS['posts'][ (int) substr( $o8_gone, 5 ) ]->post_status = 'trash';
post_as( 7, 'handle_note', array( 'action' => 'wpcpm_talks_note', 'key' => $o8_gone, '_wpnonce' => 'nonce-wpcpm_talks_note_' . $o8_gone, 'note' => 'Too late.' ) );
$GLOBALS['post_cache'] = false;
ck( 'o8_lead_gone: a lead deleted meanwhile takes no note, and the Administrator is told it is gone, not that it was added',
	array( flash_of( 7 )['status'] ?? null, flash_of( 7 )['problems']['note'] ?? null, $o8_count( $o8_gone ) ),
	array( 'note-refused', 'That lead is not in the talks any more.', 0 ) );

// Moved into the talks meanwhile: the promotion deleted the lead and keyed its notes to the record.
fresh();
$o8_moved     = WPCPM_Institution_Leads::add( array( 'name' => 'Example Moved Lead', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
// The institution it became keeps its old key, as a promotion leaves it (round 2, R3).
WPCPM_Institution_Record::set( LONG, array( WPCPM_Institution_Leads::RECORD_LEAD_KEY => $o8_moved ) );
$GLOBALS['post_cache'] = true;
WPCPM_Institution_Talks::facts( $o8_moved );
unset( $GLOBALS['posts'][ (int) substr( $o8_moved, 5 ) ], $GLOBALS['pmeta'][ (int) substr( $o8_moved, 5 ) ] );
post_as( 7, 'handle_note', array( 'action' => 'wpcpm_talks_note', 'key' => $o8_moved, '_wpnonce' => 'nonce-wpcpm_talks_note_' . $o8_moved, 'note' => 'Under a key no lead holds.' ) );
$GLOBALS['post_cache'] = false;
ck( 'o8_lead_moved: a lead moved into the talks meanwhile takes no note under its old key, and nobody is told it was added; it is told the lead is an institution now (R3)',
	array( flash_of( 7 )['status'] ?? null, $o8_count( $o8_moved ), $o8_count( LONG ), flash_of( 7 )['problems']['note'] ?? null ),
	array( 'note-refused', 0, 0, 'This lead is an institution now. Add the note on its Talks card.' ) );

// Round 3, P1: as on a real site, the lead's post really gone from the table (a promotion deletes it) and nothing of it
// in this request's cache, so its lock has no name.
fresh();
$p1_moved = WPCPM_Institution_Leads::add( array( 'name' => 'Example Promoted Lead', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
WPCPM_Institution_Record::set( LONG, array( WPCPM_Institution_Leads::RECORD_LEAD_KEY => $p1_moved ) );
unset( $GLOBALS['posts'][ (int) substr( $p1_moved, 5 ) ], $GLOBALS['pmeta'][ (int) substr( $p1_moved, 5 ) ] );
post_as( 7, 'handle_note', array( 'action' => 'wpcpm_talks_note', 'key' => $p1_moved, '_wpnonce' => 'nonce-wpcpm_talks_note_' . $p1_moved, 'note' => 'After the move.' ) );
ck( 'c2_moved_not_kept: no card draws that lead\'s note box again, so the note typed is not kept', in_array( 'talks-note:' . $p1_moved, kept_forms_of( 7 ), true ), false );
ck( 'p1_moved_post_gone: a note for a lead whose post is gone and which became an institution says so, nothing written',
	array( flash_of( 7 )['status'] ?? null, flash_of( 7 )['problems']['note'] ?? null, $o8_count( $p1_moved ), $o8_count( LONG ), WPCPM_Institution_Leads::add_note( $p1_moved, 'Directly.', 7 ) instanceof WP_Error ? WPCPM_Institution_Leads::add_note( $p1_moved, 'Directly.', 7 )->get_error_code() : null ),
	array( 'note-refused', 'This lead is an institution now. Add the note on its Talks card.', 0, 0, 'wpcpm_lead_promoted' ) );
$p1_gone = WPCPM_Institution_Leads::add( array( 'name' => 'Example Deleted Lead', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
unset( $GLOBALS['posts'][ (int) substr( $p1_gone, 5 ) ], $GLOBALS['pmeta'][ (int) substr( $p1_gone, 5 ) ] );
post_as( 7, 'handle_note', array( 'action' => 'wpcpm_talks_note', 'key' => $p1_gone, '_wpnonce' => 'nonce-wpcpm_talks_note_' . $p1_gone, 'note' => 'After the delete.' ) );
ck( 'c2_gone_not_kept: nor for a lead that is gone', in_array( 'talks-note:' . $p1_gone, kept_forms_of( 7 ), true ), false );
ck( 'p1_deleted_post_gone: a note for a lead whose post is gone and which became nothing says it is gone, nothing written',
	array( flash_of( 7 )['status'] ?? null, flash_of( 7 )['problems']['note'] ?? null, $o8_count( $p1_gone ) ),
	array( 'note-refused', 'That lead is not in the talks any more.', 0 ) );
// Round 4, C2: a press while the note lock is held for a lead that is gone meanwhile is still told it is busy, and keeps nothing.
$c2_gone = WPCPM_Institution_Leads::add( array( 'name' => 'Example Busy Gone Lead', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
unset( $GLOBALS['posts'][ (int) substr( $c2_gone, 5 ) ], $GLOBALS['pmeta'][ (int) substr( $c2_gone, 5 ) ] );
$GLOBALS['held'][ WPCPM_Institution_Request::LOCK_PREFIX . 'talks_note_' . $c2_gone ] = true;
post_as( 7, 'handle_note', array( 'action' => 'wpcpm_talks_note', 'key' => $c2_gone, '_wpnonce' => 'nonce-wpcpm_talks_note_' . $c2_gone, 'note' => 'While it was held.' ) );
$GLOBALS['held'] = array();
ck( 'c5_busy_gone_not_kept: busy for a lead that is gone, the reader is told busy as before, and the note is not kept', array( flash_of( 7 )['status'] ?? null, in_array( 'talks-note:' . $c2_gone, kept_forms_of( 7 ), true ) ), array( 'note-busy', false ) );

/* ---- words -------------------------------------------------------------- */

echo "\n=== Words (U9) ===\n";

$talks_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talks.php' );
ck( 'the four sentences of fix round 1 are gone and their new words are there', array( has( $talks_source, 'The talks could not be kept on the site' ), has( $talks_source, 'opens again with the next sync.\', \'wpcredits' ) && ! has( $talks_source, 'is still locked. It opens again with the next sync.' ), has( $talks_source, 'Given as %s' ), has( $talks_source, 'The lead is in the talks now' ), has( $talks_source, 'That note is added already.' ), has( $talks_source, 'The country was given as %s, which is not in the program\\\'s list. Choose one from the list.' ) ), array( false, false, false, false, false, true ) );

ck( 'fix round 2: "These could not be saved: %s. Save again." is gone, the one warning in its place (R2)', array( has( $talks_source, 'These could not be saved' ), has( $talks_source, "'Not everything was saved. Saved: %1\$s. Not saved: %2\$s. Save again.'" ) ), array( false, true ) );

/* ---- Program details (the owner's yes of 19 Nov 2026) --------------------- */

echo "\n=== Program details ===\n";

$details_text = "Hours: 120 a semester.\nReports: monthly, to the dean.\n" . str_repeat( 'Ł', 40 ) . ' one long word';
fresh();
$pd_page = draw( 7 );
ck( 'Program details is the last field before the action row, a textarea of four rows with its label and hint, for an institution', array( 1 === preg_match( '#<label class="wpcpm-ui-field__label" for="wpcpm-talks-program-details">Program details</label><textarea class="wpcpm-ui-field__control wpcpm-ui-field__control--textarea" id="wpcpm-talks-program-details" name="program_details" maxlength="5000" aria-describedby="wpcpm-talks-program-details-hint" rows="4">#', $pd_page ), 1 === preg_match( '#id="wpcpm-talks-program-details-hint">What this school asks for: hours, dates, reports\. Only Administrators see it\.</p></div><div class="wpcpm-ui-actions">#', $pd_page ), 1 === preg_match( '#<textarea[^>]*placeholder#', $pd_page ) ), array( true, true, false ) );
$pd_lead      = WPCPM_Institution_Leads::add( array( 'name' => 'Wyższa Szkoła Programowa', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
$pd_lead_page = draw( 7, $pd_lead );
ck( 'and for a lead, after its own fields and the two that follow them', array( array_slice( field_order( $pd_lead_page ), -3 ), 1 === preg_match( '#id="wpcpm-talks-program-details-hint">[^<]*</p></div>(?:<input type="hidden"[^>]*>)*<div class="wpcpm-ui-actions">#', $pd_lead_page ) ), array( array( 'estimated-students', 'first-students', 'program-details' ), true ) );
ck( 'a member of a school and a signed-out visitor never get it; outside site mode it is not drawn', array( has( draw( 40 ), 'Program details' ), has( draw( 0 ), 'Program details' ) ), array( false, false ) );
$GLOBALS['on_site'] = false;
ck( 'outside site mode nothing is drawn and a post is refused', array( draw( 7 ), post_as( 7, 'handle_save', array( 'action' => 'wpcpm_talks_save', 'key' => S, '_wpnonce' => 'nonce-wpcpm_talks_save_' . S, 'program_details' => 'x' ) ), WPCPM_Institution_Talks::facts( S )['program_details'] ), array( '', $refusal, '' ) );
$GLOBALS['on_site'] = true;
$GLOBALS['sent']    = array();
ck( 'a save with text stores it with its line breaks, the history row names the field and not the text, the copy holds none of it', array( save_as( 7, array( 'program_details' => str_replace( "\n", "\r\n", $details_text ) ) ), WPCPM_Institution_Talks::facts( S )['program_details'], rows_of( S, 'talks_saved' )[0]['message'] ?? null, false !== strpos( (string) json_encode( WPCPM_Institution_Audit::entries_for( S, 0 ) ), 'Hours: 120' ), $GLOBALS['sent'] ), array( back(), $details_text, 'The talks were updated: Program details.', false, array() ) );
ck( 'printed escaped, with its line breaks, inside the card\'s form', array( has( draw( 7 ), "rows=\"4\">\nHours: 120 a semester.\nReports: monthly, to the dean.\n" ), has( draw( 7 ), '<script' ) ), array( true, false ) );
$pd_rows = count( WPCPM_Institution_Audit::entries_for( S, 0 ) );
ck( 'a second identical save writes nothing', array( save_as( 7, array( 'program_details' => $details_text ) ), count( WPCPM_Institution_Audit::entries_for( S, 0 ) ), flash_of( 7 )['status'] ?? null ), array( back(), $pd_rows, 'nothing' ) );
ck( 'an empty save clears it', array( save_as( 7, array( 'program_details' => '   ' ) ), WPCPM_Institution_Talks::facts( S )['program_details'] ), array( back(), '' ) );
ck( '5,001 characters are refused at the field, the typing kept', array( save_as( 7, array( 'program_details' => str_repeat( 'ą', 5001 ) ) ), flash_of( 7 )['problems']['program_details'] ?? null, mb_strlen( as_drawn_text( draw( 7 ), 'program_details' ), 'UTF-8' ) ), array( back(), 'Program details can be at most 5000 characters long.', 5001 ) );
ck( 'invalid UTF-8 is refused at the field', array( save_as( 7, array( 'program_details' => "Hours \xC3\x28" ) ), flash_of( 7 )['problems']['program_details'] ?? null, WPCPM_Institution_Talks::facts( S )['program_details'] ), array( back(), 'Program details holds characters the site cannot read.', '' ) );
foreach ( array( 'Wyższa Szkoła Górnołąćka: 120 godzin', 'Universitatea Câmpiei Rîușițeni: rapoarte lunare', 'جامعة الواحة الخضراء المتخيلة: تقارير شهرية', '架空山学園大学: 月次報告' ) as $script ) {
	save_as( 7, array( 'program_details' => $script ) );
	ck( 'byte for byte: ' . bin2hex( substr( $script, 0, 6 ) ), bin2hex( WPCPM_Institution_Talks::facts( S )['program_details'] ), bin2hex( $script ) );
}
// Fix wave, K1: a value that is not a scalar is not posted, and the stored text stays.
save_as( 7, array( 'program_details' => $details_text ) );
ck( 'K1: program_details[] posted as an array leaves the stored text as it is', array( save_as( 7, array( 'program_details' => array( 'x' ) ) ), WPCPM_Institution_Talks::facts( S )['program_details'] ), array( back(), $details_text ) );
// Fix wave, K2: format characters are the text's: a zero-width non-joiner (Persian), an emoji joined by a zero-width
// joiner and a soft hyphen survive; only control characters other than a line break and a tab are dropped.
$pd_format = "می\u{200C}خواهیم گزارش\nTeam: \u{1F469}\u{200D}\u{1F4BB}\tHoch\u{00AD}schule";
save_as( 7, array( 'program_details' => wp_slash( "\x07" . $pd_format . "\x1B" ) ) );
ck( 'K2: a ZWNJ, a ZWJ sequence and a soft hyphen read back byte for byte, the bell and the escape dropped', bin2hex( WPCPM_Institution_Talks::facts( S )['program_details'] ), bin2hex( $pd_format ) );
// Fix wave, K3: markup, entities, a backslash and 0 are drawn as typed and save back identical.
$pd_awkward = 'Ends </textarea> early? <script>alert(1)</script> Fees &amp; rooms, the dean&#039;s \ office: C:\drafts\scans';
save_as( 7, array( 'program_details' => wp_slash( $pd_awkward ) ) );
$pd_drawn = draw( 7 );
$pd_back  = as_drawn_text( $pd_drawn, 'program_details' );
$pd_rows  = count( WPCPM_Institution_Audit::entries_for( S, 0 ) );
ck( 'K3: </textarea>, a script tag, &amp;, &#039; and backslashes are stored and drawn as typed, never as markup', array( WPCPM_Institution_Talks::facts( S )['program_details'] === $pd_awkward, $pd_back === $pd_awkward, has( $pd_drawn, '</textarea> early' ), has( $pd_drawn, '<script>' ) ), array( true, true, false, false ) );
ck( 'K3: and posted back as drawn it saves identical: nothing changes, no row', array( post_as( 7, 'handle_save', array_merge( as_drawn( $pd_drawn ), array( 'program_details' => wp_slash( $pd_back ) ) ) ), flash_of( 7 )['status'] ?? null, WPCPM_Institution_Talks::facts( S )['program_details'] === $pd_awkward, count( WPCPM_Institution_Audit::entries_for( S, 0 ) ) ), array( back(), 'nothing', true, $pd_rows ) );
save_as( 7, array( 'program_details' => '0' ) );
$pd_zero = draw( 7 );
ck( 'K3: the value 0 is kept, drawn, and saves back identical', array( WPCPM_Institution_Talks::facts( S )['program_details'], as_drawn_text( $pd_zero, 'program_details' ), post_as( 7, 'handle_save', array_merge( as_drawn( $pd_zero ), array( 'program_details' => '0' ) ) ), flash_of( 7 )['status'] ?? null ), array( '0', '0', back(), 'nothing' ) );
save_as( 7, array( 'program_details' => $details_text ), $pd_lead );
ck( 'a lead keeps it as its own field', WPCPM_Institution_Talks::facts( $pd_lead )['program_details'], $details_text );
$GLOBALS['answer'] = 'recNEW00000000009';
save_as( 7, array( 'stage' => 'First Contact Made' ), $pd_lead );
ck( 'and a lead\'s text survives promotion, on the new school\'s record', array( WPCPM_Institution_Talks::facts( 'recNEW00000000009' )['program_details'] ?? null, WPCPM_Institution_Record::get( 'recNEW00000000009' )['program_details'] ?? null ), array( $details_text, $details_text ) );
ck( 'never among the copy\'s columns', array_key_exists( 'program_details', WPCPM_Institution_Copy::COLUMNS ), false );
$cases['Program details, several lines and a 40-character word'] = draw( 7, 'recNEW00000000009' );
$pd_lead2 = WPCPM_Institution_Leads::add( array( 'name' => 'Universitatea Programului', 'country' => RO, 'assigned' => 7, 'source' => 'test', 'program_details' => $details_text ), 7, false );
$cases['a lead\'s Program details, several lines and a 40-character word'] = draw( 7, $pd_lead2 );
WPCPM_UI::enter( 'administrator' );
ob_start();
WPCPM_Institution_Talks::render( $pd_lead2, array( 'named' => false ) );
$GLOBALS['admin_cases']['a lead\'s Program details, on its page'] = (string) ob_get_clean();
ob_start();
WPCPM_Institution_Talks::render( 'recNEW00000000009', array( 'named' => false ) );
$GLOBALS['admin_cases']['a school\'s Program details, on the Administrator Dashboard'] = (string) ob_get_clean();
WPCPM_UI::leave();

/* ---- a save made on values that changed meanwhile (the owner's wave, X1) ---- */

echo "\n=== A save made on values that changed meanwhile (X1) ===\n";

$changed_sentence = 'Nothing was saved: these talks changed after you opened the card. The card shows them as they are now. Make your change again and save.';
fresh();
$first_form  = as_drawn( draw( 7 ) );
$second_form = as_drawn( draw( 11 ) );
ck( 'X1: the form carries the hash of the stored values it saves, the same for two readers', array( isset( $first_form[ WPCPM_Institution_Talks::FIELD_DRAWN ] ), 1 === preg_match( '/^[0-9a-f]{64}$/D', (string) ( $first_form[ WPCPM_Institution_Talks::FIELD_DRAWN ] ?? '' ) ), ( $first_form[ WPCPM_Institution_Talks::FIELD_DRAWN ] ?? 'a' ) === ( $second_form[ WPCPM_Institution_Talks::FIELD_DRAWN ] ?? 'b' ) ), array( true, true, true ) );
ck( 'X1: the first of two cards opened at once saves', array( post_as( 7, 'handle_save', array_merge( $first_form, array( 'next_step' => 'The first one\'s step', 'follow_up' => '2027-01-10' ) ) ), flash_of( 7 )['status'] ?? null ), array( back(), 'saved' ) );
$x1_rows = count( WPCPM_Institution_Audit::entries_for( S, 0 ) );
$x1_sent = $GLOBALS['sent'];
ck( 'X1: the second is refused with the sentence, nothing saved, no request, no row', array( post_as( 11, 'handle_save', array_merge( $second_form, array( 'next_step' => 'The second one\'s step', 'follow_up' => '2027-02-20' ) ) ), flash_of( 11 )['status'] ?? null, WPCPM_Institution_Talks::facts( S )['next_step'], WPCPM_Institution_Talks::facts( S )['follow_up'], count( WPCPM_Institution_Audit::entries_for( S, 0 ) ), $GLOBALS['sent'] === $x1_sent, $GLOBALS['remote'] ), array( back(), 'changed', 'The first one\'s step', '2027-01-10', $x1_rows, true, array() ) );
$x1_page = draw( 11 );
ck( 'X1: the card is drawn with the values as stored now, what was typed not kept, under one error message', array( has( plain( $x1_page ), $changed_sentence ), substr_count( $x1_page, 'data-wpcpm-card-message' ), has( $x1_page, 'is-error' ), as_drawn( $x1_page )['next_step'] ?? null, as_drawn( $x1_page )['follow_up'] ?? null ), array( true, 1, true, 'The first one\'s step', '2027-01-10' ) );
$cases['a save refused: the talks changed meanwhile'] = $x1_page;
ck( 'X1: and its redraw carries the hash of now, so saving again works', array( post_as( 11, 'handle_save', array_merge( as_drawn( draw( 11 ) ), array( 'next_step' => 'The second one\'s step' ) ) ), WPCPM_Institution_Talks::facts( S )['next_step'] ), array( back(), 'The second one\'s step' ) );
$no_hash = as_drawn( draw( 7 ) );
unset( $no_hash[ WPCPM_Institution_Talks::FIELD_DRAWN ] );
ck( 'X1: a save with no hash is refused the same way, nothing written', array( post_as( 7, 'handle_save', array_merge( $no_hash, array( 'next_step' => 'Hand-made' ) ) ), flash_of( 7 )['status'] ?? null, WPCPM_Institution_Talks::facts( S )['next_step'] ), array( back(), 'changed', 'The second one\'s step' ) );
ck( 'X1: a hash of something else is refused too', array( post_as( 7, 'handle_save', array_merge( as_drawn( draw( 7 ) ), array( WPCPM_Institution_Talks::FIELD_DRAWN => str_repeat( 'a', 64 ), 'next_step' => 'Forged' ) ) ), flash_of( 7 )['status'] ?? null, WPCPM_Institution_Talks::facts( S )['next_step'] ), array( back(), 'changed', 'The second one\'s step' ) );
ck( 'X1: an unchanged card saves as before', array( save_as( 7, array( 'next_step' => 'Unchanged card' ) ), flash_of( 7 )['status'] ?? null, WPCPM_Institution_Talks::facts( S )['next_step'] ), array( back(), 'saved', 'Unchanged card' ) );
fresh();
$x1_long = as_drawn( draw( 7, LONG ) );
WPCPM_Institutions_Index::patch( LONG, array( 'stage' => 'Agreement Sent' ) );
ck( 'X1: a stage the agreement path moved meanwhile refuses the save, the stage it moved to kept', array( post_as( 7, 'handle_save', array_merge( $x1_long, array( 'next_step' => 'Over the move' ) ) ), flash_of( 7 )['status'] ?? null, stage_of( LONG ), WPCPM_Institution_Talks::facts( LONG )['next_step'] ), array( back( LONG ), 'changed', 'Agreement Sent', '' ) );
fresh();
WPCPM_Institution_Record::set( S, array( 'assigned' => 12 ) );
$x1_assigned = as_drawn( draw( 7 ) );
WPCPM_Institution_Record::set( S, array( 'assigned' => 11 ) );
ck( 'X1: an assignment changed meanwhile refuses it too', array( post_as( 7, 'handle_save', $x1_assigned ), flash_of( 7 )['status'] ?? null, WPCPM_Institution_Talks::facts( S )['assigned'] ), array( back(), 'changed', 11 ) );
fresh();
save_as( 7, array( 'stage' => 'Not Moving Forward' ) );
$x1_asked = draw( 7 );
ck( 'X1: the confirmation of a move out of the talks still works across its two saves', array( flash_of( 7 ), post_as( 7, 'handle_save', as_drawn( $x1_asked ) ), stage_of( S ) ), array( null, back(), 'Not Moving Forward' ) );
fresh();
$x1_lead   = WPCPM_Institution_Leads::add( array( 'name' => 'Wyższa Szkoła Zmienna', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
$x1_lead_a = as_drawn( draw( 7, $x1_lead ) );
$x1_lead_b = as_drawn( draw( 11, $x1_lead ) );
$x1_lead_back = 'redirect:https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=' . $x1_lead . '#wpcpm-talks';
ck( 'X1: a lead\'s card the same: the first saves, the second is refused, the first one\'s values kept', array( post_as( 7, 'handle_save', array_merge( $x1_lead_a, array( 'next_step' => 'Lead, first' ) ) ), post_as( 11, 'handle_save', array_merge( $x1_lead_b, array( 'next_step' => 'Lead, second', 'city' => 'Gdańsk' ) ) ), flash_of( 11 )['status'] ?? null, WPCPM_Institution_Talks::facts( $x1_lead )['next_step'], WPCPM_Institution_Talks::facts( $x1_lead )['city'] ), array( $x1_lead_back, $x1_lead_back, 'changed', 'Lead, first', '' ) );
ck( 'X1: a lead\'s save is checked under the lead\'s lock', in_array( WPCPM_Institution_Leads::LOCK_PREFIX . substr( $x1_lead, 5 ), $GLOBALS['taken'], true ), true );
$GLOBALS['held'][ WPCPM_Institution_Leads::LOCK_PREFIX . substr( $x1_lead, 5 ) ] = true;
ck( 'X1: with the lead\'s lock held, the save answers busy and writes nothing', array( post_as( 7, 'handle_save', array_merge( as_drawn( draw( 7, $x1_lead ) ), array( 'next_step' => 'Held' ) ) ), flash_of( 7 )['status'] ?? null, WPCPM_Institution_Talks::facts( $x1_lead )['next_step'] ), array( $x1_lead_back, 'busy', 'Lead, first' ) );
$GLOBALS['held'] = array();
post_as( 11, 'handle_save', array_merge( $x1_lead_b, array( 'next_step' => 'Lead, second' ) ) );
$GLOBALS['uid'] = 11;
WPCPM_UI::enter( 'administrator' );
ob_start();
WPCPM_Institution_Talks::render( $x1_lead, array( 'named' => false ) );
$GLOBALS['admin_cases']['a lead\'s save refused: the talks changed meanwhile'] = (string) ob_get_clean();
WPCPM_UI::leave();
ck( 'X1: the lead\'s page says it too', has( plain( $GLOBALS['admin_cases']['a lead\'s save refused: the talks changed meanwhile'] ), $changed_sentence ), true );

echo "\n=== A lead is read from the table once its lock is held (the outside review, O3) ===\n";

// The handler's first read fills this request's cache; another request then saves the lead, a write to the table the
// cache never sees; the read under the lead's lock is the table's, and the stale-save check refuses.
fresh();
$o3_lead              = WPCPM_Institution_Leads::add( array( 'name' => 'Example Lead Cached', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
$o3_post              = (int) substr( $o3_lead, 5 );
$GLOBALS['post_cache'] = true;
$o3_form              = as_drawn( draw( 7, $o3_lead ) );
$GLOBALS['pmeta'][ $o3_post ][ WPCPM_Institution_Leads::META_FIELDS ]['next_step'] = 'Saved by another request';
$o3_back              = 'redirect:https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=' . $o3_lead . '#wpcpm-talks';
ck( 'o3_lead_save_reads_table: a lead saved by another request after this one read it is refused as changed, the other request\'s value kept',
	array( post_as( 7, 'handle_save', array_merge( $o3_form, array( 'next_step' => 'Over the other request' ) ) ), flash_of( 7 )['status'] ?? null, $GLOBALS['pmeta'][ $o3_post ][ WPCPM_Institution_Leads::META_FIELDS ]['next_step'] ),
	array( $o3_back, 'changed', 'Saved by another request' ) );
ck( 'and the copy was dropped through the post and meta caches of that lead',
	array( in_array( array( $o3_post, 'posts' ), $GLOBALS['cache_dropped'] ?? array(), true ), in_array( array( $o3_post, 'post_meta' ), $GLOBALS['cache_dropped'] ?? array(), true ) ),
	array( true, true ) );
$GLOBALS['post_cache'] = false;

// The delete: a closed lead another request reopened meanwhile is refused as open, nothing moved to the Trash.
fresh();
$o3_del  = WPCPM_Institution_Leads::add( array( 'name' => 'Example Lead Reopened', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
WPCPM_Institution_Leads::close( $o3_del, 7 );
$GLOBALS['post_cache'] = true;
WPCPM_Institution_Leads::get( $o3_del );
$GLOBALS['pmeta'][ (int) substr( $o3_del, 5 ) ][ WPCPM_Institution_Leads::META_FIELDS ]['closed'] = '';
$o3_ask  = WPCPM_Institution_Leads::trash( $o3_del, 7 );
ck( 'o3_lead_delete_reads_table: a delete of a lead another request reopened meanwhile is refused as open, the lead kept',
	array( is_wp_error( $o3_ask ) ? $o3_ask->get_error_code() : $o3_ask, $GLOBALS['posts'][ (int) substr( $o3_del, 5 ) ]->post_status ),
	array( 'wpcpm_lead_open', 'private' ) );
$GLOBALS['post_cache'] = false;
ck( 'X1: the note form carries no hash', 1 === preg_match( '#<div id="wpcpm-talks-note">.*?' . WPCPM_Institution_Talks::FIELD_DRAWN . '#s', draw( 7 ) ), false );

/* ---- an Administrator can delete a lead (the owner's wave, X4) ------------ */

echo "\n=== Deleting a lead (X4) ===\n";

/** Delete this lead's own form as drawn: its hidden fields. */
function delete_form( $html ) {
	$out = array();
	if ( preg_match( '#<form id="wpcpm-talks-delete"[^>]*>(.*?)</form>#s', $html, $form ) ) {
		preg_match_all( '#<input type="hidden" name="([^"]+)" value="([^"]*)"#', $form[1], $hidden, PREG_SET_ORDER );
		foreach ( $hidden as $h ) { $out[ $h[1] ] = html_entity_decode( $h[2], ENT_QUOTES ); }
	}
	return $out;
}
function lead_post( $key ) { return $GLOBALS['posts'][ (int) substr( $key, 5 ) ] ?? null; }

fresh();
$x4_log = tempnam( sys_get_temp_dir(), 'wpcpm-x4-' );
ini_set( 'error_log', $x4_log );
$x4_name = 'Școala de Șters Imaginară';
$x4      = WPCPM_Institution_Leads::add( array( 'name' => $x4_name, 'country' => RO, 'contact_person' => 'Persoana Ștearsă', 'contact_email' => 'sters@example.test', 'assigned' => 7, 'next_step' => 'Nothing more', 'follow_up' => '2026-01-01', 'source' => 'outreach:recEXA0000000X401' ), 7, false );
WPCPM_Institution_Talk_Notes::add( $x4, 'First note.', 7 );
WPCPM_Institution_Talk_Notes::add( $x4, 'Second note.', 11 );
$x4_back  = 'redirect:https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=' . $x4 . '#wpcpm-talks';
$x4_view  = 'redirect:https://example.test/administrator-dashboard/?wpcpm_view=talks';
$x4_open  = draw( 7, $x4 );
ck( 'X4: an open lead\'s card offers no delete', array( has( $x4_open, 'Delete this lead' ), has( $x4_open, 'wpcpm-talks-delete' ) ), array( false, false ) );
ck( 'X4: a lead that is not closed is refused in words, nothing written', array( post_as( 7, 'handle_delete', array( 'action' => 'wpcpm_talks_delete', 'key' => $x4, '_wpnonce' => 'nonce-wpcpm_talks_delete_' . $x4 ) ), flash_of( 7 )['text'] ?? null, lead_post( $x4 )->post_status ), array( $x4_back, 'Close this lead before you delete it.', 'private' ) );
WPCPM_Institution_Leads::close( $x4, 7 );
$x4_page = draw( 7, $x4 );
ck( 'X4: a closed lead\'s action row: Save, Reopen this lead, then Delete this lead in the People card\'s quiet red, submitting its own form', array( 1 === preg_match( '#<div class="wpcpm-ui-actions">.*?>Save</button>.*?>Reopen this lead</button><button type="submit" class="wpcpm-btn wpcpm-btn--quiet wpcpm-btn--danger" form="wpcpm-talks-delete">Delete this lead</button>#s', $x4_page ), delete_form( $x4_page ), 1 === preg_match( '#</form><form id="wpcpm-talks-delete" method="post" action="https://example.test/wp-admin/admin-post.php" novalidate hidden#', $x4_page ) ), array( true, array( '_wpnonce' => 'nonce-wpcpm_talks_delete_' . $x4, 'action' => 'wpcpm_talks_delete', 'key' => $x4 ), true ) );
$GLOBALS['admin_cases']['a closed lead, its three actions'] = (string) ( static function () use ( $x4 ) { WPCPM_UI::enter( 'administrator' ); ob_start(); WPCPM_Institution_Talks::render( $x4, array( 'named' => false ) ); $h = (string) ob_get_clean(); WPCPM_UI::leave(); return $h; } )();
$x4_before = array( $GLOBALS['posts'], $GLOBALS['pmeta'] );
ck( 'X4: the first press deletes nothing and lands back on the lead', array( post_as( 7, 'handle_delete', delete_form( $x4_page ) ), flash_of( 7 )['status'] ?? null, lead_post( $x4 )->post_status, count( WPCPM_Institution_Talk_Notes::for_key( $x4 ) ) ), array( $x4_back, 'delete-ask', 'private', 2 ) );
$x4_asked = draw( 7, $x4 );
ck( 'X4: the card is drawn again with the question, one warning, and the button\'s form carries the confirmation', array( has( plain( $x4_asked ), 'Deleting removes ' . $x4_name . ', its contact and its 2 notes from the talks. Press Delete this lead again to confirm.' ), substr_count( $x4_asked, 'data-wpcpm-card-message' ), has( $x4_asked, 'is-warning' ), 1 === preg_match( '/^[0-9a-f]{64}$/D', delete_form( $x4_asked )['confirm'] ?? '' ) ), array( true, 1, true, true ) );
WPCPM_UI::enter( 'administrator' );
ob_start();
WPCPM_Flash::set( 'institution_talks', array( 'status' => 'delete-ask', 'key' => $x4, 'text' => 'Deleting removes ' . $x4_name . ', its contact and its 2 notes from the talks. Press Delete this lead again to confirm.', 'delete' => delete_form( $x4_asked )['confirm'] ?? '' ) );
WPCPM_Institution_Talks::render( $x4, array( 'named' => false ) );
$GLOBALS['admin_cases']['a closed lead, the question before it is deleted'] = (string) ob_get_clean();
WPCPM_UI::leave();
ck( 'X4: a second press without the confirmation asks again, deleting nothing', array( post_as( 7, 'handle_delete', delete_form( $x4_page ) ), flash_of( 7 )['status'] ?? null, lead_post( $x4 )->post_status ), array( $x4_back, 'delete-ask', 'private' ) );
$x4_asked = draw( 7, $x4 );
post_as( 7, 'handle_note', array( 'action' => 'wpcpm_talks_note', 'key' => $x4, '_wpnonce' => 'nonce-wpcpm_talks_note_' . $x4, 'note' => 'A third note, after the question.' ) );
ck( 'X4: the confirmation is used up by any other action on the lead: after a note it asks again, with the new count', array( post_as( 7, 'handle_delete', delete_form( $x4_asked ) ), flash_of( 7 )['status'] ?? null, lead_post( $x4 )->post_status, has( plain( draw( 7, $x4 ) ), 'its contact and its 3 notes from the talks.' ) ), array( $x4_back, 'delete-ask', 'private', true ) );
$x4_other = WPCPM_Institution_Leads::add( array( 'name' => 'Wyższa Szkoła Druga', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
WPCPM_Institution_Leads::close( $x4_other, 7 );
$x4_asked = draw( 7, $x4 );
ck( 'X4: and it holds for that lead only', array( post_as( 7, 'handle_delete', array_merge( delete_form( $x4_asked ), array( 'key' => $x4_other, '_wpnonce' => 'nonce-wpcpm_talks_delete_' . $x4_other ) ) ), flash_of( 7 )['status'] ?? null, lead_post( $x4_other )->post_status ), array( 'redirect:https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=' . $x4_other . '#wpcpm-talks', 'delete-ask', 'private' ) );
post_as( 7, 'handle_delete', delete_form( draw( 7, $x4 ) ) );
$x4_asked = draw( 7, $x4 );
$x4_notes = array_map( static function ( $note ) { return (int) $note['id']; }, WPCPM_Institution_Talk_Notes::for_key( $x4 ) );
$GLOBALS['remote'] = array();
$GLOBALS['sent']   = array();
ck( 'X4: the second press with the confirmation lands on the Talks view', post_as( 7, 'handle_delete', delete_form( $x4_asked ) ), $x4_view );
ck( 'X4: the lead and each of its notes are in WordPress\'s Trash, their own previous status kept by WordPress, nothing deleted for good', array( lead_post( $x4 )->post_status, $GLOBALS['pmeta'][ (int) substr( $x4, 5 ) ]['_wp_trash_meta_status'] ?? null, array_map( static function ( $id ) { return $GLOBALS['posts'][ $id ]->post_status . '/' . ( $GLOBALS['pmeta'][ $id ]['_wp_trash_meta_status'] ?? '' ); }, $x4_notes ) ), array( 'trash', 'private', array( 'trash/private', 'trash/private', 'trash/private' ) ) );
ck( 'X4: under the lead\'s lock, nothing sent to Airtable', array( in_array( WPCPM_Institution_Leads::LOCK_PREFIX . substr( $x4, 5 ), $GLOBALS['taken'], true ), $GLOBALS['remote'], $GLOBALS['sent'] ), array( true, array(), array() ) );
$x4_logged = (string) file_get_contents( $x4_log );
ck( 'X4: one line in the site\'s error log, with the lead\'s key and the account\'s ID, no name', array( substr_count( $x4_logged, 'WPCredits Program Manager: the lead ' . $x4 . ' was moved to the Trash with 3 notes by account 7.' ), false !== strpos( $x4_logged, 'Școala' ) || false !== strpos( $x4_logged, 'sters@' ) ), array( 1, false ) );
ck( 'X4: the lead\'s name is left for the Talks view to say', $GLOBALS['umeta'][7]['wpcpm_flash']['institution_talks_deleted'] ?? null, array( 'name' => $x4_name ) );
ck( 'X4: in words, once', array( WPCPM_Institution_Talks::take_deleted(), WPCPM_Institution_Talks::take_deleted() ), array( 'The lead ' . $x4_name . ' was deleted.', '' ) );
ck( 'X4: a lead in the Trash has no card and no facts, is in no list, and its notes are read by no one', array( draw( 7, $x4 ), WPCPM_Institution_Talks::facts( $x4 ), array_key_exists( $x4, WPCPM_Institution_Talks::all_facts() ), WPCPM_Institution_Talk_Notes::for_key( $x4 ) ), array( '', null, false, array() ) );
ck( 'X4: its name is free again: name_taken() does not see it, and Add a lead with that name works', array( WPCPM_Institution_Leads::name_taken( $x4_name ), WPCPM_Institution_Leads::matching( $x4_name, 'sters@example.test' ), is_wp_error( WPCPM_Institution_Leads::add( array( 'name' => $x4_name ), 7, false ) ) ), array( '', array(), false ) );
ck( 'X4: the import\'s own search by source still sees it, so a second import does not bring it back; its notes\' sources too', array( WPCPM_Institution_Leads::by_source( 'outreach:recEXA0000000X401' ) ), array( $x4 ) );
ck( 'X4: a deleted lead\'s post refuses a press again, as gone', array( post_as( 7, 'handle_delete', delete_form( $x4_asked ) ), flash_of( 7 )['text'] ?? null ), array( $x4_back, 'That lead is not in the talks any more.' ) );

// The refusals, each writing nothing.
fresh();
$x4_n1 = WPCPM_Institution_Leads::add( array( 'name' => 'Universitatea Câmpiei Ștergerii', 'country' => RO, 'assigned' => 7, 'source' => 'test' ), 7, false );
WPCPM_Institution_Talk_Notes::add( $x4_n1, 'Only note.', 7 );
WPCPM_Institution_Leads::close( $x4_n1, 7 );
post_as( 7, 'handle_delete', delete_form( draw( 7, $x4_n1 ) ) );
ck( 'X4: one note, said in the singular', has( plain( draw( 7, $x4_n1 ) ), 'Deleting removes Universitatea Câmpiei Ștergerii, its contact and its 1 note from the talks. Press Delete this lead again to confirm.' ), true );
$x4_n0 = WPCPM_Institution_Leads::add( array( 'name' => 'جامعة الحذف المتخيلة', 'country' => JP, 'assigned' => 7, 'source' => 'test' ), 7, false );
WPCPM_Institution_Leads::close( $x4_n0, 7 );
post_as( 7, 'handle_delete', delete_form( draw( 7, $x4_n0 ) ) );
ck( 'X4: no note, its own sentence', has( plain( draw( 7, $x4_n0 ) ), 'Deleting removes جامعة الحذف المتخيلة and its contact from the talks. Press Delete this lead again to confirm.' ), true );
$x4_form   = delete_form( draw( 7, $x4_n0 ) );
$x4_before = array( $GLOBALS['posts'], $GLOBALS['pmeta'] );
$GLOBALS['held'][ WPCPM_Institution_Leads::LOCK_PREFIX . substr( $x4_n0, 5 ) ] = true;
ck( 'X4: the lead\'s lock held: the busy sentence, nothing written', array( post_as( 7, 'handle_delete', $x4_form ), flash_of( 7 )['text'] ?? null, array( $GLOBALS['posts'], $GLOBALS['pmeta'] ) === $x4_before ), array( 'redirect:https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=' . $x4_n0 . '#wpcpm-talks', 'Somebody else is changing this lead. Try again in a while.', true ) );
$GLOBALS['held'] = array();
ck( 'X4: a member\'s hand-made post, and a school\'s key, answer the policy\'s 403, nothing written', array( post_as( 40, 'handle_delete', $x4_form ), post_as( 7, 'handle_delete', array( 'action' => 'wpcpm_talks_delete', 'key' => S, '_wpnonce' => 'nonce-wpcpm_talks_delete_' . S ) ), array( $GLOBALS['posts'], $GLOBALS['pmeta'] ) === $x4_before ), array( $refusal, $refusal, true ) );
ck( 'X4: a nonce for another lead is refused, keyed to the posted key', post_as( 7, 'handle_delete', array_merge( $x4_form, array( 'key' => $x4_n1 ) ) ), 'nonce:wpcpm_talks_delete_' . $x4_n1 );
$GLOBALS['on_site'] = false;
ck( 'X4: outside site mode a post is refused alike', array( post_as( 7, 'handle_delete', $x4_form ), lead_post( $x4_n0 )->post_status ), array( $refusal, 'private' ) );
$GLOBALS['on_site'] = true;
update_post_meta( (int) substr( $x4_n0, 5 ), WPCPM_Institution_Leads::META_PROMOTED, 'recEXA0000000X402' );
ck( 'X4: a lead whose move into the talks did not finish is refused with the whole-branch wave\'s sentence', array( post_as( 7, 'handle_delete', delete_form( draw( 7, $x4_n0 ) ) ), flash_of( 7 )['text'] ?? null, lead_post( $x4_n0 )->post_status ), array( 'redirect:https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=' . $x4_n0 . '#wpcpm-talks', 'This lead\'s move into the talks as an institution did not finish. Choose the stage again and save to finish it.', 'private' ) );
ck( 'X4: init() hooks the delete handler, with no nopriv', array( 1 === preg_match( "/add_action\\( 'admin_post_' \\. self::ACTION_DELETE, array\\( __CLASS__, 'handle_delete' \\) \\);/", (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talks.php' ) ), has( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-talks.php' ), 'admin_post_nopriv_' ), WPCPM_Institution_Talks::ACTION_DELETE ), array( true, false, 'wpcpm_talks_delete' ) );
// Uninstall still removes leads and notes whatever their status, the Trash's too.
$x4_t = WPCPM_Institution_Leads::add( array( 'name' => '架空削除学園', 'country' => JP, 'assigned' => 7, 'source' => 'test' ), 7, false );
WPCPM_Institution_Talk_Notes::add( $x4_t, 'A note to go.', 7 );
WPCPM_Institution_Leads::close( $x4_t, 7 );
post_as( 7, 'handle_delete', delete_form( draw( 7, $x4_t ) ) );
post_as( 7, 'handle_delete', delete_form( draw( 7, $x4_t ) ) );
ck( 'X4: uninstall\'s delete_all() removes leads and notes, the Trash\'s among them', array( lead_post( $x4_t )->post_status ?? null, WPCPM_Institution_Leads::delete_all() > 0, WPCPM_Institution_Talk_Notes::delete_all() > 0, count( array_filter( $GLOBALS['posts'], static function ( $post ) { return in_array( $post->post_type, array( 'wpcpm_inst_lead', 'wpcpm_inst_note' ), true ); } ) ) ), array( 'trash', true, true, 0 ) );
@unlink( $x4_log );
ini_restore( 'error_log' );

/* ---- layout ------------------------------------------------------------- */

echo "\n=== Layout cases ===\n";

fresh();
for ( $n = 1; $n <= 5; $n++ ) {
	WPCPM_Institution_Talk_Notes::add( S, 'Note ' . $n . ': ' . ( 5 === $n ? str_repeat( 'Ą', 40 ) . ' a long word in a note' : 'the dean will answer next week.' ), 3 === $n ? 13 : 7, array( 'date' => '2026-10-0' . $n . ' 09:00:00' ) );
}
$cases['the card open, five notes'] = draw( 7 );
WPCPM_Institution_Audit::record( array( 'kind' => 'talks_stage', 'institution' => S, 'subject' => S, 'actor' => 13, 'ground' => 'manager', 'evidence' => 'index', 'message' => 'The stage of the talks moved from Agreement Sent to Confirmed.', 'data' => array( 'from' => 'Agreement Sent', 'to' => 'Confirmed' ) ) );
WPCPM_Institution_Record::set( S, array( 'assigned' => 13 ) );
$cases['the since line, a 60-character Administrator'] = draw( 7 );
save_as( 7, array( 'stage' => 'Not Moving Forward' ) );
$cases['the leave confirmation'] = draw( 7 );
fresh();
save_as( 7, array( 'stage' => 'Confirmed', 'estimated_students' => 'many', 'next_step' => str_repeat( 'Ł', 40 ) ), LONG );
$cases['a refusal kept, a kept stage outside the list'] = draw( 7, LONG );
ck( 'the kept stage outside the list is not drawn as chosen (U1)', as_drawn( $cases['a refusal kept, a kept stage outside the list'] )['stage'] ?? null, 'Info Sent' );
fresh();
$cases['40-character names, nothing recorded'] = draw( 7, LONG );
$cases['one message with its follow-on sentences'] = $two;
$lead = WPCPM_Institution_Leads::add( array( 'name' => 'Școala ' . str_repeat( 'Ț', 40 ) . ' Imaginară de Test', 'country_text' => 'Atlantis', 'contact_person' => str_repeat( 'Ž', 40 ), 'assigned' => 7, 'lead_owner' => 'Owner From The Import', 'source' => 'test' ), 7, false );
$cases['a lead, its country as text'] = draw( 7, $lead );
WPCPM_Institution_Leads::close( $lead, 7 );
$cases['a closed lead'] = draw( 7, $lead );
post_as( 7, 'handle_note', array( 'action' => 'wpcpm_talks_note', 'key' => S, '_wpnonce' => 'nonce-wpcpm_talks_note_' . S, 'note' => '' ) );
$cases['the notes list empty, a note refused'] = draw( 7 );
ck( 'fifteen cases on the Institution Dashboard and seven on the Administrator Dashboard, each the card', array( count( $cases ), count( array_filter( $cases, static function ( $html ) { return has( $html, 'id="wpcpm-talks"' ); } ) ), count( array_filter( $GLOBALS['admin_cases'] ?? array(), static function ( $html ) { return has( $html, 'id="wpcpm-talks"' ) && has( $html, 'Program details' ); } ) ) ), array( 15, 15, 7 ) );
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	foreach ( $cases as $name => $html ) {
		wpcpm_layout_keep( 'Talks card: ' . $name, $html );
	}
	foreach ( $GLOBALS['admin_cases'] as $name => $html ) {
		wpcpm_layout_keep( 'Talks card: ' . $name, $html, 'administrator/talks' );
	}
}

/* ---- the owner's wave, round 2: I3, L4, L5 ----------------------------------- */

echo "\n=== Deleting a lead, round 2 (I3, L4, L5) ===\n";

fresh();
$r2 = WPCPM_Institution_Leads::add( array( 'name' => 'Wyższa Szkoła Powrotna', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
WPCPM_Institution_Talk_Notes::add( $r2, 'One.', 7 );
WPCPM_Institution_Talk_Notes::add( $r2, 'Two.', 7 );
WPCPM_Institution_Leads::close( $r2, 7 );
$r2_back = 'redirect:https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=' . $r2 . '#wpcpm-talks';
post_as( 7, 'handle_delete', delete_form( draw( 7, $r2 ) ) );
$r2_asked = draw( 7, $r2 );
$GLOBALS['trash_fails'] = true;
ck( 'I3: a move to the Trash that fails says so in its words, and the lead stays', array( post_as( 7, 'handle_delete', delete_form( $r2_asked ) ), flash_of( 7 )['text'] ?? null, lead_post( $r2 )->post_status ), array( $r2_back, 'The lead could not be deleted. Try again in a moment.', 'private' ) );
$GLOBALS['trash_fails'] = false;
post_as( 7, 'handle_delete', delete_form( draw( 7, $r2 ) ) );
post_as( 7, 'handle_delete', delete_form( draw( 7, $r2 ) ) );
$r2_ids = array_merge( array( (int) substr( $r2, 5 ) ), array_keys( array_filter( $GLOBALS['posts'], static function ( $post ) use ( $r2 ) { return 'wpcpm_inst_note' === $post->post_type && ( $GLOBALS['pmeta'][ $post->ID ][ WPCPM_Institution_Talk_Notes::META_FOR ] ?? '' ) === $r2; } ) ) );
ck( 'L4: the lead and its two notes are in the Trash', array_map( static function ( $id ) { return $GLOBALS['posts'][ $id ]->post_status; }, $r2_ids ), array( 'trash', 'trash', 'trash' ) );
// What `wp post untrash` does since WordPress 5.6: the status the filter answers, else a draft.
foreach ( $r2_ids as $id ) {
	$GLOBALS['posts'][ $id ]->post_status = WPCPM_Institution_Leads::untrash_status( 'draft', $id, (string) ( $GLOBALS['pmeta'][ $id ]['_wp_trash_meta_status'] ?? '' ) );
}
ck( 'L4: untrashed, the lead is back in the talks as it was, closed, with its two notes', array( null !== WPCPM_Institution_Talks::facts( $r2 ), WPCPM_Institution_Talks::facts( $r2 )['closed'] ?? null, count( WPCPM_Institution_Talk_Notes::for_key( $r2 ) ), has( draw( 7, $r2 ), 'Reopen this lead' ) ), array( true, true, 2, true ) );
$r2_other = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'trash' ) );
ck( 'L4: another post type is left to WordPress', WPCPM_Institution_Leads::untrash_status( 'draft', $r2_other, 'publish' ), 'draft' );
// The outside review, O10: the LEAD alone untrashed brings its notes back from the Trash with it.
$o10 = WPCPM_Institution_Leads::add( array( 'name' => 'Example Lead Brought Back', 'country' => PL, 'assigned' => 7, 'source' => 'test' ), 7, false );
WPCPM_Institution_Talk_Notes::add( $o10, 'One.', 7 );
WPCPM_Institution_Talk_Notes::add( $o10, 'Two.', 7 );
WPCPM_Institution_Leads::close( $o10, 7 );
post_as( 7, 'handle_delete', delete_form( draw( 7, $o10 ) ) );
post_as( 7, 'handle_delete', delete_form( draw( 7, $o10 ) ) );
$o10_notes = array_keys( array_filter( $GLOBALS['posts'], static function ( $post ) use ( $o10 ) { return 'wpcpm_inst_note' === $post->post_type && ( $GLOBALS['pmeta'][ $post->ID ][ WPCPM_Institution_Talk_Notes::META_FOR ] ?? '' ) === $o10; } ) );
$GLOBALS['actions']['untrashed_post'] = array();
WPCPM_Institution_Leads::init();
wp_untrash_post( (int) substr( $o10, 5 ) );
ck( 'o10_lead_untrashed_brings_notes: the lead alone untrashed is in the talks again, closed, with its two notes, each back to its own status',
	array( lead_post( $o10 )->post_status, WPCPM_Institution_Talks::facts( $o10 )['closed'] ?? null, count( WPCPM_Institution_Talk_Notes::for_key( $o10 ) ), array_map( static function ( $id ) { return $GLOBALS['posts'][ $id ]->post_status; }, $o10_notes ) ),
	array( 'private', true, 2, array( 'private', 'private' ) ) );
ck( 'L4: the filter is hooked by the leads\' init', has( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-leads.php' ), "add_filter( 'wp_untrash_post_status', array( __CLASS__, 'untrash_status' ), 10, 3 );" ), true );
// L5, last of the suite: the constant stays defined for the rest of the run.
define( 'EMPTY_TRASH_DAYS', 0 );
$r2_before = array( $GLOBALS['posts'], $GLOBALS['pmeta'] );
ck( 'L5: on a site that keeps no Trash the first press is refused, nothing written', array( post_as( 7, 'handle_delete', delete_form( draw( 7, $r2 ) ) ), flash_of( 7 )['text'] ?? null, array( $GLOBALS['posts'], $GLOBALS['pmeta'] ) === $r2_before ), array( $r2_back, 'This site keeps no Trash, so a lead cannot be deleted here.', true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
