<?php
/**
 * The school's details card (Institutions release 2): what the program has on record for an
 * institution, read as two lists, and the two ways a school corrects it.
 *
 * What is pinned here and why:
 *
 * - **The read view is the chosen mockup B, as the People card is drawn.** A section around an open
 *   group, a title and a lead, two small headings and the details as rows in the order of the details
 *   table with its labels, an empty one reading "Not given yet". The two forms are closed disclosures
 *   under their group, "Correct these" (saved at once) and "Ask to correct these" (an Administrator
 *   checks first), each holding the application's own labels and help.
 * - **Who gets it is the policy's answer, not the card's.** `ACT_EDIT_DETAILS` for the members of the
 *   school whose agreement is settled and for an Administrator; a school whose agreement is not
 *   settled, a member of another school and a stranger are drawn nothing at all.
 * - **A first-group post saves at once** (the contact person to Airtable and the index, the rest to the
 *   record), audits, emails "Who is emailed about institutions" what changed, and tells the member. A
 *   second-group post from a member raises a request and writes nothing; from an Administrator it saves
 *   at once, in both groups, since they are the checker.
 * - **While a request waits** each changed row carries its line, "Waiting for an Administrator: ..., asked
 *   on ...", with Withdraw it, and the group offers no form. **A second account of the school, or a
 *   second tab, that asks meanwhile is refused with the store's own sentence and no second request is
 *   made** (Review Focus 2). Afterwards every account reads the latest outcome until the next request.
 * - **A refusal gives back what was typed.** A field the application's rules refuse shows its problem
 *   under it, a text the cleaner would take words from is refused whole, and **an Airtable refusal of
 *   the contact person's save writes nothing on the site, keeps every typed value and says so** (Review
 *   Focus 4).
 * - **A post that changes nothing does nothing** (Review Focus 5): no write, no request, no email, and
 *   the sentence "Nothing was changed.". An answer posted back as it was drawn is not a change, even one
 *   the cleaner would have altered.
 * - **The handlers check the nonce and the policy before any write**, against the institution the form
 *   names and never trust it: a member of another school gets the policy's one refusal.
 * - **Two tabs posting the same correction save it once.** A save at once is worked out again under the
 *   school's lock, so the second post finds nothing left to change: one PATCH, one history row, one email,
 *   and "Nothing was changed."; a save, or a request, that finds the lock held is told someone else is
 *   saving the details and keeps its typing, and the lock is let go on every path.
 * - **An Administrator's second form reads as the save it is**, "Correct these" and "Save the details",
 *   and every form on the card carries `novalidate`: the server holds every rule.
 *
 * The real details class, the application's cleaners, the request store with its lock, the policy, the
 * audit log, the typed-text rules, the roster's argument names and the email templates run here. Stood in
 * for at their contracts: the settings, the Airtable client (journaled), the mail layer (every send kept
 * with its built subject and body), the index, the institution's record, the members, the agreement's
 * gate, the combobox and the two dashboards' addresses. The flash is a copy of the real one without its
 * once-per-request memory, since this suite plays many requests in one process.
 *
 * Run from the plugin root:  php bin/test-institution-details-card.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']        = array( 'date_format' => 'Y-m-d' );
$GLOBALS['umeta']       = array();
$GLOBALS['users']       = array();
$GLOBALS['posts']       = array();
$GLOBALS['pmeta']       = array();
$GLOBALS['uid']         = 0;
$GLOBALS['manage']      = array();
$GLOBALS['index']       = array();
$GLOBALS['rosters']     = array();
$GLOBALS['memberships'] = array();
$GLOBALS['settled']     = array();
$GLOBALS['back']        = '';
$GLOBALS['settings']    = array();
$GLOBALS['records']     = array();
$GLOBALS['patched']     = array();
$GLOBALS['airtable']    = true;
$GLOBALS['mail']        = array();
$GLOBALS['events']      = array();
$GLOBALS['combos']      = array();
$GLOBALS['nonce_ok']    = true;
$GLOBALS['hooks']       = array();
$GLOBALS['labels']      = array();
$GLOBALS['base_words'] = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = strtolower( str_replace( ' ', '', $name ) );
		$this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_content = '', $post_type = '', $post_status = 'publish', $post_author = 0, $post_date_gmt = '', $post_modified_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $s, $p, $n, $d = null ) { return 1 === (int) $n ? $s : $p; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_textarea( $s ) { return esc_html( $s ); }
function checked( $a, $b = true, $echo = true ) { $out = (string) $a === (string) $b ? ' checked="checked"' : ''; if ( $echo ) { echo $out; } return $out; }
function selected( $a, $b = true, $echo = true ) { $out = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) { echo $out; } return $out; }
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';
// The words a school is not meant to read, one list with the Institution Dashboard's suite.
require_once __DIR__ . '/stubs/internal-words.php';
require_once __DIR__ . '/stubs/view-faults.php';
function wp_kses( $s, $allowed ) { return (string) $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $s ) { return trim( (string) $s ); }
function is_email( $s ) { return (bool) preg_match( '/^[^@\s]+@[^@\s.]+\.[^@\s]+$/', (string) $s ); }
function esc_url_raw( $u, $protocols = null ) { return preg_match( '#^https?://[^\s<>"]+$#i', (string) $u ) ? (string) $u : ''; }
function wp_cache_delete( $key, $group = '' ) { return true; }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action( $h, $c = null, $p = 10, $n = 1 ) { $GLOBALS['hooks'][] = $h; }
function add_filter() {}
function register_post_type( $type, $args = array() ) {}
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function human_time_diff( $a, $b = 0 ) { return '4 days'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = stripslashes_deep( $v ); return true; }
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_current_user_id() { return $GLOBALS['uid']; }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
require_once __DIR__ . '/stubs/caps.php';
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
		if ( 'email' === $field && 0 === strcasecmp( (string) $user->user_email, (string) $value ) ) { return $user; }
	}
	return false;
}
function wp_insert_post( $a, $error = false ) {
	static $next = 500;
	$post                          = new WP_Post();
	$post->ID                      = ++$next;
	$post->post_title              = stripslashes( (string) ( $a['post_title'] ?? '' ) );
	$post->post_content            = stripslashes( (string) ( $a['post_content'] ?? '' ) );
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'publish';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	$post->post_date_gmt           = gmdate( 'Y-m-d H:i:s', 1756000000 + $post->ID );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_time( $f, $gmt = false, $post = null, $translate = false ) { return strtotime( $post->post_date_gmt . ' UTC' ); }
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	return $single ? ( $rows ? $rows[0] : '' ) : $rows;
}
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( stripslashes_deep( $value ) ); return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ); return true; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
/** One post against a flat AND meta query of `=`, `IN` and `EXISTS`, matched case-insensitively as MySQL does. */
function meta_matches( $post_id, array $query ) {
	foreach ( $query as $key => $clause ) {
		if ( 'relation' === $key || ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
		$value   = $GLOBALS['pmeta'][ (int) $post_id ][ $clause['key'] ][0] ?? null;
		$compare = isset( $clause['compare'] ) ? strtoupper( (string) $clause['compare'] ) : '=';
		if ( 'EXISTS' === $compare ) {
			$match = null !== $value;
		} elseif ( null === $value ) {
			$match = false;
		} elseif ( 'IN' === $compare ) {
			$match = false;
			foreach ( (array) $clause['value'] as $candidate ) {
				if ( 0 === strcasecmp( (string) $value, (string) $candidate ) ) { $match = true; break; }
			}
		} else {
			$match = 0 === strcasecmp( (string) $value, (string) $clause['value'] );
		}
		if ( ! $match ) { return false; }
	}
	return true;
}
function closed_sort_key( $post_id ) { return (int) ( $GLOBALS['pmeta'][ (int) $post_id ]['_wpcpm_req_closed_at'][0] ?? 0 ); }
function get_posts( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ( $a['post_type'] ?? '' ) !== $post->post_type ) { continue; }
		$status = $a['post_status'] ?? 'publish';
		if ( 'any' !== $status && $status !== $post->post_status ) { continue; }
		if ( ! meta_matches( $post->ID, (array) ( $a['meta_query'] ?? array() ) ) ) { continue; }
		$out[] = $post;
	}
	$order = (array) ( $a['orderby'] ?? array( 'date' => 'DESC' ) );
	usort(
		$out,
		static function ( $one, $two ) use ( $order ) {
			foreach ( $order as $field => $direction ) {
				if ( 'closed' === $field ) {
					$cmp = closed_sort_key( $one->ID ) <=> closed_sort_key( $two->ID );
				} elseif ( 'ID' === $field ) {
					$cmp = $one->ID <=> $two->ID;
				} else {
					$cmp = strcmp( $one->post_date_gmt, $two->post_date_gmt );
				}
				if ( 0 !== $cmp ) { return 'ASC' === strtoupper( (string) $direction ) ? $cmp : -$cmp; }
			}
			return $one->ID - $two->ID;
		}
	);
	$n = (int) ( $a['numberposts'] ?? -1 );
	if ( $n > 0 ) { $out = array_slice( $out, 0, $n ); }
	if ( 'ids' === ( $a['fields'] ?? '' ) ) { return array_map( static function ( $p ) { return $p->ID; }, $out ); }
	return $out;
}
/** Core's nonce check refuses with a screen of its own; here it is an exception, and `nonce_ok` says whether it is thrown. */
function check_admin_referer( $a = -1, $q = '_wpnonce' ) {
	$GLOBALS['events'][] = 'nonce ' . $a;
	if ( ! $GLOBALS['nonce_ok'] ) { throw new Exception( 'nonce:' . $a ); }
	return true;
}
// Core's shape (an id, then the referer field) while `core_nonce` is set (Stage B, Task 19), so the old branch draws the
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
function is_admin() { return false; }
function _doing_it_wrong( $f, $m, $v ) { $GLOBALS['wrong'][] = $f; }
function wp_script_is( $h, $l = 'enqueued' ) { return 'registered' === $l ? isset( $GLOBALS['registered_scripts'][ $h ] ) : in_array( $h, $GLOBALS['enqueued_scripts'] ?? array(), true ); }
function wp_register_script( $h, $src = '', $deps = array(), $ver = false, $footer = false ) { $GLOBALS['registered_scripts'][ $h ] = $src; }
function wp_enqueue_script( $h = '' ) { $GLOBALS['enqueued_scripts'][] = $h; }
function sanitize_html_class( $c, $f = '' ) { $c = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); return '' === $c ? (string) $f : $c; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function add_query_arg( $args, $url_or_value, $url = null ) { if ( ! is_array( $args ) ) { $args = array( $args => $url_or_value ); } else { $url = $url_or_value; } return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function wp_get_referer() { return $GLOBALS['back']; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect:' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die:' . $m ); }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';

/** The flash as the real one reads and writes it, without its memory of what a request took: many requests are played here. */
class WPCPM_Flash {
	const META = 'wpcpm_flash';
	private static function pending( $user_id ) { $p = get_user_meta( (int) $user_id, self::META, true ); return is_array( $p ) ? $p : array(); }
	public static function set( $channel, $value, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) { return; }
		$pending                      = self::pending( $user_id );
		$pending[ (string) $channel ] = $value;
		update_user_meta( $user_id, self::META, wp_slash( $pending ) );
	}
	public static function take( $channel, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) { return ''; }
		$pending = self::pending( $user_id );
		if ( ! array_key_exists( (string) $channel, $pending ) ) { return ''; }
		$value = $pending[ (string) $channel ];
		unset( $pending[ (string) $channel ] );
		if ( empty( $pending ) ) { delete_user_meta( $user_id, self::META ); } else { update_user_meta( $user_id, self::META, wp_slash( $pending ) ); }
		return $value;
	}
	public static function sweep( callable $stale, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) { return; }
		$pending = self::pending( $user_id );
		$left    = array();
		foreach ( $pending as $channel => $value ) {
			if ( ! call_user_func( $stale, (string) $channel, $value ) ) { $left[ $channel ] = $value; }
		}
		if ( count( $left ) === count( $pending ) ) { return; }
		if ( empty( $left ) ) { delete_user_meta( $user_id, self::META ); } else { update_user_meta( $user_id, self::META, wp_slash( $left ) ); }
	}
}

/* ---- the other pieces, stubbed to their contracts ----------------------- */

class WPCPM_Mentors_Sync {
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
	public static function is_record_id( $v ) { return (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $v ) ); }
}
class WPCPM_Students_Sync {
	const META_INSTITUTION = 'wpcpm_student_institution';
	public static function user_for_record( $record ) { return null; }
}
class WPCPM_Institutions_Index {
	public static function rows() { return $GLOBALS['index']; }
	public static function row( $r ) { return $GLOBALS['index'][ $r ] ?? null; }
	public static function has( $r ) { return isset( $GLOBALS['index'][ $r ] ); }
	public static function patch( $r, array $fields ) {
		if ( ! isset( $GLOBALS['index'][ $r ] ) ) { return false; }
		$GLOBALS['index'][ $r ] = array_merge( $GLOBALS['index'][ $r ], $fields );
		$GLOBALS['events'][]    = 'index';
		return true;
	}
	/** Contract: this request's copy of the index is dropped, so the next read is the table's. */
	public static function forget_cached() { $GLOBALS['events'][] = 'forget index'; }
}
class WPCPM_Roster_Index {
	public static function rows( $id ) { return $GLOBALS['rosters'][ trim( (string) $id ) ] ?? array(); }
}
class WPCPM_Institution_Members {
	const LEVEL_REPRESENTATIVE = 'representative';
	const LEVEL_TUTOR          = 'tutor';
	/** The level the account holds: a tutor where the fixture says so (`$GLOBALS['levels']`), a representative otherwise, as the real class reads it. */
	public static function level_of( $user = null ) {
		$id = is_object( $user ) ? (int) $user->ID : (int) $user;
		return isset( $GLOBALS['levels'][ $id ] ) && 'tutor' === $GLOBALS['levels'][ $id ] ? 'tutor' : 'representative';
	}
	public static function memberships_of( $user = null ) {
		$id = is_object( $user ) ? (int) $user->ID : (int) $user;
		return $GLOBALS['memberships'][ $id ] ?? array();
	}
	public static function institution_of( $user = null ) {
		$mine = self::memberships_of( $user );
		return $mine ? $mine[0] : '';
	}
}
class WPCPM_Institution_Agreement {
	public static function is_settled( $id ) { return in_array( $id, $GLOBALS['settled'], true ); }
}
/** The one way the people "Who is emailed about institutions" names are told something. */
class WPCPM_Institutions {
	const FLASH = 'institutions';
	public static function notify_managers( $context, $build, $setting_key = 'agreement_notify' ) {
		$sent = 0;
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) WPCPM_Settings::get_value( $setting_key, '' ) ) ) ) as $address ) {
			$user  = get_user_by( 'email', $address );
			$sent += ( $user instanceof WP_User ? WPCPM_Mail::send( $user, $context, $build ) : WPCPM_Mail::send_to( $address, $context, $build ) ) ? 1 : 0;
		}
		return $sent;
	}
}
class WPCPM_Settings {
	public static function get() { return $GLOBALS['settings']; }
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}
/** Every send, with the message its builder made for that recipient, so "one email" is countable. */
class WPCPM_Mail {
	public static function send( $recipient, $context, $build ) {
		// A mail host that takes nothing, when a check says so (1.123.84).
		if ( ! empty( $GLOBALS['mail_fails'] ) ) { return false; }
		// As the real one: an account's ID is as good as the account.
		$recipient = $recipient instanceof WP_User ? $recipient : get_userdata( (int) $recipient );
		if ( ! $recipient instanceof WP_User || ! $recipient->exists() || '' === $recipient->user_email ) { return false; }
		return self::record( $recipient->user_email, $context, call_user_func( $build, $recipient ) );
	}
	public static function send_to( $email, $context, $build, $locale = '' ) {
		return self::record( (string) $email, $context, call_user_func( $build, $email ) );
	}
	public static function site_name() { return 'Example Site'; }
	private static function record( $to, $context, $mail ) {
		$mail                = (array) $mail;
		$GLOBALS['mail'][]   = array( 'to' => $to, 'context' => $context, 'subject' => (string) ( $mail['subject'] ?? '' ), 'body' => (string) ( $mail['body'] ?? '' ) );
		$GLOBALS['events'][] = 'mail ' . $context;
		return true;
	}
}
/** The client, journaled: what it was asked to PATCH, and what it answers (the global `airtable`). */
class WPCPM_Airtable {
	public function __construct( $settings = null ) {}
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
	public function update_records( $table, array $records ) {
		// Another request a check plants, played once in the moment before this PATCH is sent.
		if ( isset( $GLOBALS['before_write'] ) && is_callable( $GLOBALS['before_write'] ) ) { call_user_func( $GLOBALS['before_write'] ); }
		$GLOBALS['events'][]  = 'airtable';
		$GLOBALS['patched'][] = array( $table, $records );
		if ( true !== $GLOBALS['airtable'] ) { return $GLOBALS['airtable']; }
		return array( $records[0]['id'] => true );
	}
}
/** The site's own answers per institution. */
class WPCPM_Institution_Record {
	public static function get( $record ) { return $GLOBALS['records'][ trim( (string) $record ) ] ?? array(); }
	public static function set( $record, array $answers ) {
		// Another request a check plants, played once in the moment before this write.
		if ( isset( $GLOBALS['before_write'] ) && is_callable( $GLOBALS['before_write'] ) ) { call_user_func( $GLOBALS['before_write'] ); }
		$GLOBALS['events'][]                             = 'record';
		$GLOBALS['records'][ trim( (string) $record ) ] = array_merge( self::get( $record ), $answers );
		return true;
	}
	/** Contract: this request's copy of the institution's answers is dropped, so the next read is the table's. */
	public static function forget_cached( $record ) { $GLOBALS['events'][] = 'forget record'; }
}
/** The tracks the site runs: the Track Builder's published tracks, status => name, in its order. */
class WPCPM_Program {
	public static function labels() { return $GLOBALS['labels']; }
}
class WPCPM_Student_Registration_Form {
	const CONTACT = 'education@wordpressfoundation.org';
}
class WPCPM_Administrators_Dashboard {
	public static $url = 'https://example.test/administrator-dashboard/';
	public static function page_url() { return self::$url; }
}
require_once __DIR__ . '/stubs/institution-card-url.php';
class WPCPM_Institutions_Dashboard {
	public static $url = 'https://example.test/institution-dashboard/';
	public static function page_url() { return self::$url; }
	// The page's addresses, as the real class builds them (bin/stubs/institution-card-url.php, checked against it
	// in bin/test-institution-ways-back.php).
	public static function view_url( $view = 'overview', $record = '', array $args = array() ) { return wpcpm_test_institution_view_url( self::page_url(), $view, $record, $args ); }
	public static function view_of( $card ) { return wpcpm_test_institution_view_of( $card ); }
	public static function card_url( $card, $record = '', $item = '', array $args = array() ) { return wpcpm_test_institution_card_url( self::page_url(), $card, $record, $item, $args ); }
	public static function requested_record() { return wpcpm_test_institution_requested_record(); }
	public static function way_back( $card ) { return wpcpm_test_institution_way_back( self::page_url(), $card ); }
}
/** The combobox the country is drawn with, as far as its arguments: what the card hands it is what is read. */
class WPCPM_Dashboards {
	public static function render_combo( array $args ) {
		$GLOBALS['combos'][] = $args;
		// Stage B (Task 19): the real field's markup, the class's own method (loaded below under another name), while
		// `real_combo` is set; the earlier sections read the arguments and this short form.
		if ( ! empty( $GLOBALS['real_combo'] ) && class_exists( 'WPCPM_Dashboards_Real', false ) ) {
			WPCPM_Dashboards_Real::render_combo( $args );
			return;
		}
		printf( '<label for="%1$s">%2$s</label><select name="%3$s" id="%1$s">', esc_attr( $args['id'] ), esc_html( $args['label'] ), esc_attr( $args['name'] ) );
		foreach ( (array) $args['options'] as $value => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $value, $args['current'], false ), esc_html( $label ) );
		}
		echo '</select>';
	}
}

/** The assignment's contracts: who holds the manage capability (the suite's `manage` list), the names, the booking link and the picker's accounts. */
function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
class WPCPM_Manager_Booking {
	public static function url_for( $id ) { return ''; }
}
class WPCPM_Settings_Choices {
	public static function manager_accounts() { $o = array(); foreach ( $GLOBALS['manage'] as $id ) { $o[ $id ] = $GLOBALS['users'][ $id ]->display_name; } asort( $o ); return $o; }
}

// The lock a request is raised and decided under, over the options table the suite keeps in `$GLOBALS['opts']`.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';
require_once __DIR__ . '/stubs/options-lock-wpdb.php';

/**
 * The suite's options table, with one moment more to play another request in: just before the next INSERT
 * IGNORE, once, which is the moment a request is about to take a lock.
 */
class Details_Card_Wpdb extends WPCPM_Test_Options_Wpdb {
	/** @var callable|null Another request, played once before the next INSERT IGNORE. */
	public $before_insert = null;
	public function query( $sql ) {
		if ( is_callable( $this->before_insert ) && 0 === strpos( (string) $sql, 'INSERT IGNORE' ) ) { call_user_func( $this->before_insert ); }
		return parent::query( $sql );
	}
}
$GLOBALS['wpdb'] = new Details_Card_Wpdb();
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-details.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-roster.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-tracks.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-assignment.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-details-card.php';

// The parts and the card's shell, the real ones; the combobox's own markup, the class's own method under another name,
// so the stand-in keeps reading its arguments for the sections above (`real_combo`); core's nonce field with its id and
// its referer (`core_nonce`), so the drawn markup of both branches holds the referer the browser posts.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-card.php';
eval( (string) preg_replace( '/^<\?php/', '', str_replace( 'class WPCPM_Dashboards {', 'class WPCPM_Dashboards_Real {', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php' ) ), 1 ) );
$GLOBALS['real_combo'] = true;
$GLOBALS['core_nonce'] = true;

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
function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, $needle ); }

/* ---- the world ----------------------------------------------------------- */

const A      = 'recINSTITUTION001'; // Settled, with members 7 and 8.
const B      = 'recINSTITUTION002'; // Settled, member 9, nothing recorded but a name.
const C      = 'recINSTITUTION003'; // Agreement not settled, member 10.
const COSTA  = 'recCR000000000001';
const POLAND = 'recPL000000000002';
const NOTIFY = 'program@example.test';

/** Start every scenario clean: the same three schools, the same people, no mail, no PATCH, no lock, no request, no flash. */
function fresh() {
	$GLOBALS['mail']     = array();
	$GLOBALS['patched']  = array();
	$GLOBALS['airtable'] = true;
	$GLOBALS['events']   = array();
	$GLOBALS['combos']   = array();
	$GLOBALS['posts']    = array();
	$GLOBALS['pmeta']    = array();
	$GLOBALS['umeta']    = array();
	$GLOBALS['nonce_ok'] = true;
	$GLOBALS['back']     = '';
	$GLOBALS['uid']      = 0;
	$_POST               = array();
	// No other request is waiting to be played.
	$GLOBALS['before_write']        = null;
	$GLOBALS['wpdb']->before_insert = null;
	$GLOBALS['opts']     = array(
		'date_format'                => 'Y-m-d',
		WPCPM_Countries::OPT_NAME    => array(
			'v'    => WPCPM_Countries::VERSION,
			'read' => 1756000000,
			'rows' => array(
				COSTA  => array( 'name' => 'Costa Rica', 'manager' => '', 'email' => '', 'calendly' => '' ),
				POLAND => array( 'name' => 'Poland', 'manager' => '', 'email' => '', 'calendly' => '' ),
			),
		),
	);
	$GLOBALS['settings'] = array(
		'institutions_table'      => 'tblInstitutions1',
		'institutions_name_field' => '',
		'agreement_notify'        => NOTIFY,
	);
	$GLOBALS['index']    = array(
		A => array(
			'record_id'      => A,
			'name'           => 'Universidad Ejemplo ',
			'stage'          => 'Confirmed',
			'country'        => COSTA,
			'country_name'   => 'Costa Rica',
			'city'           => 'San Jose',
			'website'        => 'https://ejemplo.edu',
			'contact_person' => 'Contact One',
			'contact_email'  => 'contact@example.test',
		),
		B => array( 'record_id' => B, 'name' => 'Another School', 'stage' => 'Confirmed' ),
		C => array( 'record_id' => C, 'name' => 'Unsigned School', 'stage' => 'Agreement Sent', 'city' => 'Lima', 'contact_person' => 'Contact Three' ),
	);
	$GLOBALS['records']  = array(
		A => array(
			'contact_role'      => 'Head of the international office',
			'signer_name'       => 'Signer One',
			'signer_title'      => 'Dean of Engineering',
			'student_languages' => array( 'Spanish', 'English' ),
			'credits_page'      => 'https://ejemplo.edu/credits',
		),
	);
	$GLOBALS['settled']     = array( A, B );
	$GLOBALS['memberships'] = array( 7 => array( A ), 8 => array( A ), 9 => array( B ), 10 => array( C ) );
	$GLOBALS['manage']      = array( 1, 12, 13 );
	$GLOBALS['users']       = array(
		1  => new WP_User( 1, 'Manager', 'manager@example.test', array( 'administrator' ) ),
		5  => new WP_User( 5, 'Stranger', 'stranger@example.test', array( 'subscriber' ) ),
		7  => new WP_User( 7, 'Member One', 'member1@example.test', array( 'subscriber' ) ),
		8  => new WP_User( 8, 'Member Two', 'member2@example.test', array( 'subscriber' ) ),
		9  => new WP_User( 9, 'Member Three', 'member3@example.test', array( 'subscriber' ) ),
		10 => new WP_User( 10, 'Member Four', 'member4@example.test', array( 'subscriber' ) ),
		12 => new WP_User( 12, 'Bo Boss', 'bo@example.test', array( 'administrator' ) ),
		13 => new WP_User( 13, 'Cy Chief', 'cy@example.test', array( 'administrator' ) ),
	);
	WPCPM_Institutions_Dashboard::$url = 'https://example.test/institution-dashboard/';
}

/** The card, drawn for one reader. */
function draw( $uid, $record = A ) {
	$GLOBALS['uid'] = (int) $uid;
	ob_start();
	WPCPM_Institution_Details_Card::render( $record, array( 'can_manage' => in_array( (int) $uid, $GLOBALS['manage'], true ) ) );
	$html = (string) ob_get_clean();
	words_in( $html );

	return $html;
}
/** Hold a page, or a sentence it can say, to the words a school is not meant to read; the offenders are kept for the last check. */
function words_in( $text ) {
	foreach ( internal_words( $text ) as $word ) {
		$GLOBALS['base_words'][ $word ] = $word;
	}
}
/** A page's words: tags gone, entities read, space folded. */
function plain( $html ) { return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( str_replace( '<', ' <', (string) $html ) ), ENT_QUOTES ) ) ); }
/** The rows of the read view, label to value, in the order drawn. */
function rows( $html ) {
	// The two groups' rows: the Tracks offered section below them has its own checks.
	$cut  = strpos( $html, '<h4 class="wpcpm-ui-h">Tracks offered</h4>' );
	$html = false === $cut ? $html : substr( $html, 0, $cut );
	preg_match_all( '#<th scope="row" role="rowheader" class="wpcpm-ui-cell--text">(.*?)</th><td class="[^"]*" role="cell">(.*?)</td>#s', $html, $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $row ) { $out[ html_entity_decode( $row[1], ENT_QUOTES ) ] = plain( $row[2] ); }
	return $out;
}
/** One form's disclosure, by group: `now` or `checked`; '' when it is not drawn. */
function disclosure( $html, $group ) {
	return 1 === preg_match( '#<details class="wpcpm-ui-fold wpcpm-ui-fold--inner"(?: open)? id="wpcpm-details-' . $group . '">.*?</details>#s', $html, $m ) ? $m[0] : '';
}
/** Whether a disclosure is drawn open. */
function is_open( $disclosure ) { return 1 === preg_match( '#^<details [^>]*\bopen\b#', $disclosure ); }
/** Whether every needle is on the page and they come in the order given. */
function in_order( $html, array $needles ) {
	$last = -1;
	foreach ( $needles as $needle ) {
		$at = strpos( $html, $needle );
		if ( false === $at || $at <= $last ) { return false; }
		$last = $at;
	}
	return true;
}
/** The value a text box is drawn with. */
function box( $html, $name ) {
	return 1 === preg_match( '#<input\b[^>]* name="' . preg_quote( $name, '#' ) . '"[^>]* value="([^"]*)"#', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : null;
}
/** The languages a form has ticked. */
function ticked( $html ) {
	preg_match_all( '#name="wpcpm_details_student_languages\[\]" value="([^"]*)" checked#', $html, $m );
	return $m[1];
}
/** The mails sent under one email id. */
function mails( $context ) { return array_values( array_filter( $GLOBALS['mail'], static function ( $mail ) use ( $context ) { return $context === $mail['context']; } ) ); }
/** The audit rows of one kind about a school, newest first. */
function audit( $record, $kind ) { return array_values( array_filter( WPCPM_Institution_Audit::entries_for( $record ), static function ( $row ) use ( $kind ) { return $kind === $row['kind']; } ) ); }
/** Every details request in the store, oldest first, by ID. */
function requests() {
	$out = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( 'wpcpm_inst_request' === $post->post_type ) { $out[ $id ] = array( 'state' => get_post_meta( $id, '_wpcpm_req_state', true ), 'actor' => (int) get_post_meta( $id, '_wpcpm_req_actor', true ), 'kind' => get_post_meta( $id, '_wpcpm_req_kind', true ), 'changes' => get_post_meta( $id, '_wpcpm_req_changes', true ) ); }
	}
	return $out;
}
function flash_of( $uid ) { return $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash']['institution_details'] ?? null; }
/** The hidden fields of the first form on a page that posts the given action, as the browser would post them (the nonce and the referer apart). */
function hidden_fields( $html, $action ) {
	$form = array( '', '' );
	foreach ( preg_match_all( '#<form\b[^>]*>(.*?)</form>#s', $html, $forms ) ? $forms[1] : array() as $body ) {
		if ( false !== strpos( $body, 'name="action" value="' . $action . '"' ) ) { $form = array( '', $body ); break; }
	}
	if ( '' === $form[1] ) { return array(); }
	preg_match_all( '#<input type="hidden" name="([^"]*)" value="([^"]*)" />#', $form[1], $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $field ) { if ( ! in_array( $field[1], array( '_wpnonce', '_wp_http_referer' ), true ) ) { $out[ $field[1] ] = html_entity_decode( $field[2], ENT_QUOTES ); } }
	return $out;
}
/** Run one of the card's handlers and report how it ended: a redirect, a wp_die, a refused nonce, or nothing at all. */
function run( $method ) {
	try {
		call_user_func( array( 'WPCPM_Institution_Details_Card', $method ) );
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
	return '';
}
/** Run the request class's Withdraw it handler, the way a school's Withdraw it form reaches it. */
function withdraw_as( $uid, $request, array $extra = array() ) {
	$GLOBALS['uid'] = (int) $uid;
	$_POST          = array_merge( array( 'request' => $request ), $extra );

	try {
		WPCPM_Institution_Request::handle_details_withdraw();
	} catch ( Exception $e ) {
		return $e->getMessage();
	}

	return '';
}
/** What a form posts for one group when nothing is edited: every field as it is drawn. */
function as_drawn( $record, $group ) {
	$out = array();
	foreach ( WPCPM_Institution_Details::current( $record ) as $key => $value ) {
		if ( $group === WPCPM_Institution_Details::fields()[ $key ]['group'] ) { $out[ 'wpcpm_details_' . $key ] = $value; }
	}
	return $out;
}
/** Post one group's form as one account. */
function post_as( $uid, $group, array $edits = array(), $record = A, array $extra = array() ) {
	$GLOBALS['uid'] = (int) $uid;
	$_POST          = array_merge( array( 'record' => $record, 'action' => 'now' === $group ? 'wpcpm_save_details' : 'wpcpm_ask_details' ), as_drawn( $record, $group ), $edits, $extra );

	return run( 'now' === $group ? 'handle_save' : 'handle_ask' );
}
// Your institution, the view that holds the card (Task 9).
function member_back() { return 'redirect:https://example.test/institution-dashboard/?wpcpm_view=institution#wpcpm-details'; }
/** A refusal that kept a group's typing lands on that group's fold, where it is drawn (Gate B round 2, F3). */
function fold_back( $group ) { return 'redirect:https://example.test/institution-dashboard/?wpcpm_view=institution#wpcpm-details-' . $group; }
function tracks_back( $record = A ) { return 'redirect:https://example.test/institution-dashboard/?wpcpm_view=institution&wpcpm_institution_view=' . $record . '#wpcpm-details-tracks-outcome'; }
function manager_back( $record = A ) { return 'redirect:https://example.test/institution-dashboard/?wpcpm_view=institution&wpcpm_institution_view=' . $record . '#wpcpm-details'; }
/** Whether one call comes before another in a method body, with both present (absence is not "before"). */
function before( $body, $first, $second ) {
	$one = strpos( (string) $body, $first );
	$two = strpos( (string) $body, $second );
	return false !== $one && false !== $two && $one < $two;
}
/** The body of one method, by brace depth. */
function method_body( $source, $name ) {
	if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\([^)]*\)\s*(?::\s*[^{]+)?\{/', $source, $m, PREG_OFFSET_CAPTURE ) ) { return null; }
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

fresh();

ck( 'the fixture\'s record IDs are the shape Airtable uses', array( WPCPM_Mentors_Sync::is_record_id( A ), WPCPM_Mentors_Sync::is_record_id( B ), WPCPM_Mentors_Sync::is_record_id( C ) ), array( true, true, true ) );

/* ---- the names ----------------------------------------------------------- */

echo "=== The card's names ===\n";

ck( 'two actions, one for each group', array( WPCPM_Institution_Details_Card::ACTION_SAVE, WPCPM_Institution_Details_Card::ACTION_ASK ), array( 'wpcpm_save_details', 'wpcpm_ask_details' ) );
ck( 'the anchor the dashboard and the handlers come back to', WPCPM_Institution_Details_Card::ANCHOR, 'wpcpm-details' );

WPCPM_Institution_Details_Card::init();
ck( 'both handlers are registered for a signed-in account, and the tracks and the assignment ones after them', $GLOBALS['hooks'], array( 'admin_post_wpcpm_save_details', 'admin_post_wpcpm_ask_details', 'admin_post_wpcpm_institution_tracks_save', 'admin_post_wpcpm_institution_assign' ) );

/* ---- the read view ------------------------------------------------------- */

echo "\n=== The read view: two lists under a title ===\n";

$GLOBALS['combos'] = array();
$html              = draw( 7 );
$first_combos      = $GLOBALS['combos'];

ck( 'closed again after its body', substr( $html, -strlen( '</div></details></section>' ) ), '</div></details></section>' );
preg_match_all( '#<h4 class="wpcpm-details__subtitle">(.*?)</h4>#', $html, $headings );
ck(
	'the rows follow the details table, with its labels and in its order, the languages joined by a comma',
	rows( $html ),
	array(
		'Contact person'               => 'Contact One',
		'Their role'                   => 'Head of the international office',
		'Signs the agreement'          => 'Signer One',
		'Their title'                  => 'Dean of Engineering',
		'Students study in'            => 'Spanish, English',
		'Page about WordPress Credits' => 'https://ejemplo.edu/credits',
		'Name'                         => 'Universidad Ejemplo',
		'Website'                      => 'https://ejemplo.edu',
		'City'                         => 'San Jose',
		'Country'                      => 'Costa Rica',
		'Contact\'s email'             => 'contact@example.test',
	)
);
ck( 'the labels and the order are the table\'s, not a list of the card\'s own', array_keys( rows( $html ) ), array_values( array_map( function ( $spec ) { return $spec['label']; }, WPCPM_Institution_Details::fields() ) ) );

$empty = rows( draw( 9, B ) );
ck( 'an empty detail reads "Not given yet"', array_values( array_unique( array_diff_key( $empty, array( 'Name' => 1 ) ) ) ), array( 'Not given yet' ) );
ck( 'and a filled one still reads as it is', $empty['Name'], 'Another School' );

$GLOBALS['index'][ A ]['name'] = 'Schule <script>alert(1)</script> & Co';
$escaped                       = draw( 7 );
ck( 'what the records hold is escaped on the page', array( has( $escaped, '<script>' ), has( $escaped, 'Schule &lt;script&gt;alert(1)&lt;/script&gt; &amp; Co' ) ), array( false, true ) );
fresh();

/* ---- the two disclosures ------------------------------------------------- */

echo "\n=== The two forms, closed under their groups ===\n";

$now     = disclosure( $html, 'now' );
$checked = disclosure( $html, 'checked' );

ck( 'each group has its own disclosure, closed', array( '' !== $now, '' !== $checked, is_open( $now ), is_open( $checked ) ), array( true, true, false, false ) );
ck( 'the first sits under its list and the second under its own', array( strpos( $html, 'Page about WordPress Credits' ) < strpos( $html, 'id="wpcpm-details-now"' ), strpos( $html, 'id="wpcpm-details-now"' ) < strpos( $html, 'Name and address' ), strpos( $html, 'Contact\'s email' ) < strpos( $html, 'id="wpcpm-details-checked"' ) ), array( true, true, true ) );
ck( 'a form to each, posted to the admin handler', array( substr_count( $now, '<form ' ), substr_count( $checked, '<form ' ), has( $now, 'method="post" action="https://example.test/wp-admin/admin-post.php"' ), has( $checked, 'method="post" action="https://example.test/wp-admin/admin-post.php"' ) ), array( 1, 1, true, true ) );
ck(
	'each form names its action, the institution, and a nonce keyed to that institution',
	array(
		has( $now, '<input type="hidden" name="action" value="wpcpm_save_details" />' ),
		has( $now, '<input type="hidden" name="record" value="' . A . '" />' ),
		has( $now, 'value="nonce-wpcpm_save_details_' . A . '"' ),
		has( $checked, '<input type="hidden" name="action" value="wpcpm_ask_details" />' ),
		has( $checked, '<input type="hidden" name="record" value="' . A . '" />' ),
		has( $checked, 'value="nonce-wpcpm_ask_details_' . A . '"' ),
	),
	array( true, true, true, true, true, true )
);
ck( 'and the buttons say what they do', array( has( $now, '>Save the details</button>' ), has( $checked, '>Send to an Administrator</button>' ) ), array( true, true ) );
ck( 'each guards against a double press', array( has( $now, 'data-wpcpm-once' ), has( $checked, 'data-wpcpm-once' ) ), array( true, true ) );

$labels = array( 'Name of the person we should contact', 'Their role at the institution', 'Who will sign the agreement for the institution?', 'Their title', 'In which languages do your students study?', 'Your page about WordPress Credits' );
ck( 'the first form asks the application\'s own questions, in the table\'s order', in_order( $now, array_map( 'esc_html', $labels ) ), true );
ck(
	'with their help',
	array(
		has( $now, 'For example: internship coordinator, or head of the computer science department.' ),
		has( $now, 'Somebody who can commit the institution, such as a dean. Leave it empty if you do not know yet.' ),
		has( $now, 'For example: Dean of the Faculty of Engineering.' ),
		has( $now, 'Tick every language your students study in.' ),
		has( $now, 'The agreement asks the institution to publish a page about the program on its website.' ),
	),
	array( true, true, true, true, true )
);
$labels2 = array( 'Name of your institution', 'Website', 'City', 'Country', 'Their email address' );
ck( 'the second asks the five checked questions', array_map( function ( $label ) use ( $checked ) { return has( $checked, '>' . esc_html( $label ) ); }, $labels2 ), array( true, true, true, true, true ) );
ck( 'with the application\'s help for the website and the email', array( has( $checked, 'For example: example.edu' ), has( $checked, 'We send the acknowledgement here, and the account when the program says yes.' ) ), array( true, true ) );
ck( 'and the first form does not carry a question of the second, nor the second one of the first', array( has( $now, 'name="wpcpm_details_name"' ), has( $now, 'name="wpcpm_details_contact_email"' ), has( $checked, 'name="wpcpm_details_contact_person"' ), has( $checked, 'name="wpcpm_details_credits_page"' ) ), array( false, false, false, false ) );

ck(
	'the boxes hold what the program has, the languages ticked in the application\'s order',
	array(
		box( $now, 'wpcpm_details_contact_person' ),
		box( $now, 'wpcpm_details_contact_role' ),
		box( $now, 'wpcpm_details_signer_name' ),
		box( $now, 'wpcpm_details_signer_title' ),
		box( $now, 'wpcpm_details_credits_page' ),
		ticked( $now ),
		box( $checked, 'wpcpm_details_name' ),
		box( $checked, 'wpcpm_details_website' ),
		box( $checked, 'wpcpm_details_city' ),
		box( $checked, 'wpcpm_details_contact_email' ),
	),
	array( 'Contact One', 'Head of the international office', 'Signer One', 'Dean of Engineering', 'https://ejemplo.edu/credits', array( 'English', 'Spanish' ), 'Universidad Ejemplo', 'https://ejemplo.edu', 'San Jose', 'contact@example.test' )
);
$combo = isset( $first_combos[0] ) ? $first_combos[0] : array( 'options' => array() );

echo "\n=== Who is drawn the card ===\n";

fresh();
ck( 'a member of a school whose agreement is settled', has( draw( 7 ), 'id="wpcpm-details"' ), true );
ck( 'another member of the same school', has( draw( 8 ), 'id="wpcpm-details"' ), true );
ck( 'an Administrator viewing the school', has( draw( 1 ), 'id="wpcpm-details"' ), true );
ck( 'and the form they are drawn names the school they are viewing', has( disclosure( draw( 1 ), 'now' ), 'name="record" value="' . A . '"' ), true );
ck( 'a member of another school is drawn nothing for this one', draw( 9, A ), '' );
ck( 'a school whose agreement is not settled gets no card, whoever asks', array( draw( 10, C ), has( draw( 1, C ), 'id="wpcpm-details"' ) ), array( '', true ) );
ck( 'a stranger gets nothing', draw( 5, A ), '' );
ck( 'nor does anybody who is signed out', draw( 0, A ), '' );
ck( 'a value that is no record gets nothing', array( draw( 7, 'not-a-record' ), draw( 7, '' ) ), array( '', '' ) );

echo "\n=== Saving the first group: at once ===\n";

fresh();
$out = post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact Two', 'wpcpm_details_contact_role' => 'Dean of Studies', 'wpcpm_details_student_languages' => array( 'English', 'French' ) ) );

ck( 'a member\'s save lands back on the card', $out, member_back() );
ck( 'the contact person is written to Airtable alone, under the sync\'s column, and the site follows', array( count( $GLOBALS['patched'] ), $GLOBALS['patched'][0][0], $GLOBALS['patched'][0][1] ), array( 1, 'tblInstitutions1', array( array( 'id' => A, 'fields' => array( WPCPM_Institutions_Sync::fields()['contact_person'] => 'Contact Two' ) ) ) ) );
ck( 'the index row has it too, so the page shows it without waiting for the sync', $GLOBALS['index'][ A ]['contact_person'], 'Contact Two' );
ck( 'the other answers go to the institution\'s record', array( $GLOBALS['records'][ A ]['contact_role'], $GLOBALS['records'][ A ]['student_languages'], $GLOBALS['records'][ A ]['signer_name'] ), array( 'Dean of Studies', array( 'English', 'French' ), 'Signer One' ) );
ck( 'nothing was asked of an Administrator: no request was made', requests(), array() );
$sent = mails( 'institution-details-saved' );
ck( 'one email goes to the people "Who is emailed about institutions" names', array( count( $GLOBALS['mail'] ), count( $sent ), $sent[0]['to'] ), array( 1, 1, NOTIFY ) );
ck( 'saying who changed whose details', array( $sent[0]['subject'] === '[Example Site] Universidad Ejemplo\'s details were corrected', has( $sent[0]['body'], 'Member One changed Universidad Ejemplo\'s details on the Institution Dashboard:' ) ), array( true, true ) );
ck(
	'and each change, from what to what, in the order of the table',
	array(
		has( $sent[0]['body'], "Contact person: Contact One, now Contact Two\r\nTheir role: Head of the international office, now Dean of Studies\r\nStudents study in: Spanish, English, now English, French" ),
		has( $sent[0]['body'], 'Signs the agreement' ),
	),
	array( true, false )
);
$rows = audit( A, 'details_saved' );
ck( 'one history row is written, naming the details and none of their values', array( count( $rows ), $rows[0]['actor'], $rows[0]['ground'], $rows[0]['data'], has( $rows[0]['message'] . serialize( $rows[0]['data'] ), 'Nueva' ) ), array( 1, 7, 'member', array( 'fields' => array( 'contact_person', 'contact_role', 'student_languages' ) ), false ) );
ck( 'the member is told on the card it was posted from', array( flash_of( 7 )['status'], flash_of( 7 )['record'] ), array( 'saved', A ) );

$after = draw( 7 );
ck( 'above the lists, which already read the new values', array( strpos( $after, 'are saved.' ) < strpos( $after, 'Contact and agreement' ), rows( $after )['Contact person'], rows( $after )['Students study in'] ), array( true, 'Contact Two', 'English, French' ) );
ck( 'and once only', array( has( draw( 7 ), 'are saved.' ), flash_of( 7 ) ), array( false, null ) );

echo "\n=== Asking to correct the second group: a request ===\n";

fresh();
$out = post_as( 7, 'checked', array( 'wpcpm_details_name' => 'Universidad Ejemplo de Valencia', 'wpcpm_details_city' => 'Valencia' ) );

ck( 'a member\'s ask lands back on the card', $out, member_back() );
$made = requests();
$id   = (int) array_key_first( $made );
ck( 'one request is made, open, about no student, by the account that asked', array( count( $made ), $made[ $id ]['kind'], $made[ $id ]['state'], $made[ $id ]['actor'] ), array( 1, 'details', 'open', 7 ) );
ck( 'holding each changed detail with what the program had and what was asked, and nothing else', $made[ $id ]['changes'], array( 'name' => array( 'from' => 'Universidad Ejemplo', 'to' => 'Universidad Ejemplo de Valencia' ), 'city' => array( 'from' => 'San Jose', 'to' => 'Valencia' ) ) );
ck( 'nothing is written to Airtable, the index or the record', array( $GLOBALS['patched'], $GLOBALS['index'][ A ]['name'], $GLOBALS['index'][ A ]['city'], $GLOBALS['records'][ A ]['contact_role'], in_array( 'index', $GLOBALS['events'], true ), in_array( 'record', $GLOBALS['events'], true ) ), array( array(), 'Universidad Ejemplo ', 'San Jose', 'Head of the international office', false, false ) );
$sent = mails( 'institution-details-request' );
ck( 'one email asks the people the setting names, with what was asked', array( count( $GLOBALS['mail'] ), $sent[0]['to'], has( $sent[0]['body'], "Name: Universidad Ejemplo, asked to be Universidad Ejemplo de Valencia\r\nCity: San Jose, asked to be Valencia" ), has( $sent[0]['body'], 'Member One asks to correct Universidad Ejemplo\'s details:' ) ), array( 1, NOTIFY, true, true ) );
ck( 'and no "saved" email, for nothing was saved', mails( 'institution-details-saved' ), array() );
ck( 'the request is in the history, the save is not', array( count( audit( A, 'request_raised' ) ), count( audit( A, 'details_saved' ) ) ), array( 1, 0 ) );
ck( 'the member is told what happens next', flash_of( 7 )['status'], 'asked' );

echo "\n=== While it waits ===\n";

$waiting = draw( 8 );
$day     = WPCPM_Dates::day( (int) get_post_time( 'U', true, get_post( $id ) ) );
preg_match_all( '#<form class="wpcpm-details__withdraw"[^>]*>(.*?)</form>#s', $waiting, $withdraw );
ck( 'and names the card it was pressed on, so the withdrawal comes back to it', array( hidden_fields( $waiting, 'wpcpm_withdraw_details' ), WPCPM_Institution_Details_Card::ANCHOR === WPCPM_Institution_Request::DETAILS_ANCHOR ), array( array( 'action' => 'wpcpm_withdraw_details', 'request' => (string) $id, 'wpcpm_details_return' => 'wpcpm-details' ), true ) );
ck( 'the second group offers no form while a request waits', array( disclosure( $waiting, 'checked' ), has( $waiting, 'Ask to correct these' ), has( $waiting, 'wpcpm_ask_details' ) ), array( '', false, false ) );
ck( 'the first group still does', array( has( disclosure( $waiting, 'now' ), 'Correct these' ), has( $waiting, 'wpcpm_save_details' ) ), array( true, true ) );
$admin_view = draw( 1 );

echo "\n=== Review Focus 2: a second account, or a second tab, asking while one waits ===\n";

$mails_before = count( $GLOBALS['mail'] );
$out          = post_as( 8, 'checked', array( 'wpcpm_details_website' => 'https://another.example.edu' ) );
$refused      = draw( 8 );
ck( 'another account of the school is sent back to the card', $out, member_back() );
ck( 'and no second request was made', count( requests() ), 1 );
ck( 'the first one is as it was asked', requests()[ $id ]['changes'], array( 'name' => array( 'from' => 'Universidad Ejemplo', 'to' => 'Universidad Ejemplo de Valencia' ), 'city' => array( 'from' => 'San Jose', 'to' => 'Valencia' ) ) );
ck( 'no email went, and no history row, and nothing was written', array( count( $GLOBALS['mail'] ) - $mails_before, count( audit( A, 'request_raised' ) ), $GLOBALS['patched'], $GLOBALS['index'][ A ]['website'] ), array( 0, 1, array(), 'https://ejemplo.edu' ) );
$out = post_as( 7, 'checked', array( 'wpcpm_details_city' => 'Madrid' ) );
ck( 'the same account in a second tab is refused the same way', array( $out, has( plain( draw( 7 ) ), 'One change is already waiting for an Administrator.' ), count( requests() ), requests()[ $id ]['changes']['city']['to'] ), array( member_back(), true, 1, 'Valencia' ) );

// A second tab's post that the rules would refuse is told what is true, that one is waiting, and not to correct
// what is marked in a form the page does not draw. The store stays the guard under its lock (below).
$one_waiting  = 'One change is already waiting for an Administrator.';
$mails_before = count( $GLOBALS['mail'] );
$out          = post_as( 8, 'checked', array( 'wpcpm_details_city' => '', 'wpcpm_details_contact_email' => 'not-an-email' ) );
$typing_kept  = isset( $GLOBALS['umeta'][8]['wpcpm_flash']['typed:details:' . A . ':checked'] );
$late         = draw( 8 );
$out         = post_as( 8, 'checked', array( 'wpcpm_details_city' => 'Valencia <3 and >' ) );
$typing_kept = $typing_kept || isset( $GLOBALS['umeta'][8]['wpcpm_flash']['typed:details:' . A . ':checked'] );
$late        = draw( 8 );
ck( 'so is a text the cleaner would take words from', array( $out, has( $late, $one_waiting ), has( $late, 'WordPress would remove' ) ), array( member_back(), true, false ) );
ck( 'nothing is kept for a form that is not drawn, and nothing is made, sent or written', array( $typing_kept, count( requests() ), count( $GLOBALS['mail'] ) - $mails_before, $GLOBALS['patched'] ), array( false, 1, 0, array() ) );

fresh();
$token = WPCPM_Lock::take( 'wpcpm_req_lock_details_' . A, 300 );
$out   = post_as( 7, 'checked', array( 'wpcpm_details_name' => 'Pressed Together' ) );
// Nothing waits yet while the first press holds the lock, so the second is told someone is saving the details,
// and gets its typing back to send again; "One change is already waiting" is for a request that truly waits.
$pressed = draw( 7 );
ck( 'a request being raised at that moment, under the school\'s lock, refuses the second press: someone is saving the details, and nothing is made', array( '' !== $token, $out, has( plain( $pressed ), 'Someone else is saving your institution\'s details at this moment, so nothing was done. Try again in a moment.' ), has( plain( $pressed ), 'One change is already waiting' ), box( disclosure( $pressed, 'checked' ), 'wpcpm_details_name' ), requests(), $GLOBALS['mail'] ), array( true, fold_back( 'checked' ), true, false, 'Pressed Together', array(), array() ) );

echo "\n=== After a decision ===\n";

fresh();
post_as( 7, 'checked', array( 'wpcpm_details_name' => 'Universidad Ejemplo de Valencia' ) );
$id = (int) array_key_first( requests() );
draw( 7 );
WPCPM_Institution_Request::settle( $id, 'done', '', 1, 'manager' );
$done = draw( 8 );
$when = WPCPM_Dates::day( (int) get_post_meta( $id, '_wpcpm_req_closed_at', true ) );
ck( 'an approval reads "Updated on <day>." to every account', array( has( plain( $done ), 'Updated on ' . $when . '.' ), has( plain( draw( 7 ) ), 'Updated on ' . $when . '.' ), has( plain( draw( 1 ) ), 'Updated on ' . $when . '.' ) ), array( true, true, true ) );
ck( 'under the second group, which the request was about', strpos( $done, 'Updated on' ) > strpos( $done, 'Name and address' ), true );
ck( 'and the form to ask again is back', has( disclosure( $done, 'checked' ), 'Send to an Administrator' ), true );

fresh();
post_as( 7, 'checked', array( 'wpcpm_details_name' => 'Universidad Ejemplo de Valencia' ) );
$id = (int) array_key_first( requests() );
WPCPM_Institution_Request::settle( $id, 'declined', 'Please send the letter from the registrar & the dean.', 1, 'manager' );
$declined = draw( 8 );
ck( 'a decline gives the Administrator\'s note, escaped, to every account', array( has( plain( $declined ), 'An Administrator did not make the change you asked for: Please send the letter from the registrar & the dean.' ), has( $declined, 'registrar &amp; the dean' ), has( plain( draw( 7 ) ), 'An Administrator did not make the change you asked for:' ), has( plain( draw( 1 ) ), 'An Administrator did not make the change you asked for:' ) ), array( true, true, true, true ) );
ck( 'and says nothing of an approval', has( $declined, 'Updated on' ), false );

fresh();
post_as( 7, 'checked', array( 'wpcpm_details_name' => 'Universidad Ejemplo de Valencia' ) );
$id = (int) array_key_first( requests() );
WPCPM_Institution_Request::settle( $id, 'withdrawn', '', 7, 'member' );
$withdrawn = draw( 8 );
ck( 'a withdrawal says nothing at all', array( has( $withdrawn, 'wpcpm-details__outcome' ), has( $withdrawn, 'Updated on' ), has( $withdrawn, 'did not make the change' ), has( $withdrawn, 'wpcpm-details__waiting' ) ), array( false, false, false, false ) );
ck( 'and the form to ask is back', has( disclosure( $withdrawn, 'checked' ), 'Send to an Administrator' ), true );

fresh();
post_as( 7, 'checked', array( 'wpcpm_details_name' => 'Universidad Ejemplo de Valencia' ) );
$id = (int) array_key_first( requests() );
WPCPM_Institution_Request::settle( $id, 'declined', 'Please send the letter from the registrar.', 1, 'manager' );
// The earlier request closed long ago, so the next one's closing is the latest by any clock.
if ( isset( $GLOBALS['posts'][ $id ] ) ) {
	$GLOBALS['posts'][ $id ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', 1000 );
}
$GLOBALS['pmeta'][ $id ]['_wpcpm_req_closed_at'] = array( 1000 );
$second = WPCPM_Institution_Request::raise_details( A, WPCPM_Institution_Details::changes( A, array( 'city' => 'Valencia' ) ), 7, 'member' );
WPCPM_Institution_Request::settle( $second, 'withdrawn', '', 7, 'member' );
ck( 'and a withdrawal replaces it, so the decline of the request before is not shown again', has( draw( 8 ), 'did not make the change' ), false );

echo "\n=== The outcome\'s sentence, one method for the card and the Overview ===\n";

// Institution Dashboard frame, Task 4: `outcome_sentence()` is the sentence `render_outcome()` prints, and the
// Overview's news row reads it as its why. The card prints the same bytes: each outcome's whole card is pinned by
// an md5 digest of today's output (the code at c0dfdf0a), the request closed on a fixed day so the date holds still.
$outcome_sentence = static function ( array $outcome ) {
	return method_exists( 'WPCPM_Institution_Details_Card', 'outcome_sentence' ) ? WPCPM_Institution_Details_Card::outcome_sentence( $outcome ) : 'no outcome_sentence()';
};
$fixed_day = 1767225600;
ck( 'outcome_sentence(): an approval is "Updated on" its day', $outcome_sentence( array( 'state' => 'done', 'closed_at' => $fixed_day, 'note' => '' ) ), 'Updated on ' . WPCPM_Dates::day( $fixed_day ) . '.' );
ck( 'outcome_sentence(): an approval with no day says nothing', $outcome_sentence( array( 'state' => 'done', 'closed_at' => 0, 'note' => '' ) ), '' );
ck( 'outcome_sentence(): a decline without a note', $outcome_sentence( array( 'state' => 'declined', 'closed_at' => $fixed_day, 'note' => '  ' ) ), 'An Administrator did not make the change you asked for.' );
ck( 'outcome_sentence(): a decline with a note, trimmed and unescaped', $outcome_sentence( array( 'state' => 'declined', 'closed_at' => $fixed_day, 'note' => ' Use the full name & the city. ' ) ), 'An Administrator did not make the change you asked for: Use the full name & the city.' );
ck( 'outcome_sentence(): a withdrawal, and no outcome at all, say nothing', array( $outcome_sentence( array( 'state' => 'withdrawn', 'closed_at' => $fixed_day, 'note' => '' ) ), $outcome_sentence( array() ) ), array( '', '' ) );

$outcome_pages = array();
foreach ( array(
	'done'           => array( 'done', '' ),
	'declined'       => array( 'declined', '' ),
	'declined-noted' => array( 'declined', 'Please send the letter from the registrar & the dean.' ),
	'withdrawn'      => array( 'withdrawn', '' ),
) as $outcome_key => $outcome_case ) {
	fresh();
	post_as( 7, 'checked', array( 'wpcpm_details_name' => 'Universidad Ejemplo de Valencia' ) );
	$id = (int) array_key_first( requests() );
	WPCPM_Institution_Request::settle( $id, $outcome_case[0], $outcome_case[1], 'withdrawn' === $outcome_case[0] ? 7 : 1, 'withdrawn' === $outcome_case[0] ? 'member' : 'manager' );
	$GLOBALS['pmeta'][ $id ]['_wpcpm_req_closed_at'] = array( $fixed_day );
	$outcome_pages[ $outcome_key ] = draw( 8 );
}
ck( 'each outcome\'s sentence is in its card, escaped as the card escapes it', array(
	has( $outcome_pages['done'], esc_html( $outcome_sentence( array( 'state' => 'done', 'closed_at' => $fixed_day, 'note' => '' ) ) ) ),
	has( $outcome_pages['declined-noted'], esc_html( $outcome_sentence( array( 'state' => 'declined', 'closed_at' => $fixed_day, 'note' => 'Please send the letter from the registrar & the dean.' ) ) ) ),
), array( true, true ) );

echo "\n=== The withdrawal's outcome, on the channel Task 2 flashes ===\n";

fresh();
foreach ( WPCPM_Institution_Request::school_messages() as $status => $message ) {
	WPCPM_Flash::set( WPCPM_Institution_Request::FLASH_DETAILS, array( 'status' => $status ), 7 );
	$page = draw( 7 );
	ck( 'a ' . $status . ' withdrawal reads in the words the request class gives it, as the shared message', has( $page, '<p class="wpcpm-details__message wpcpm-details__message--' . $message[0] . ' is-' . $message[0] . '" role="' . ( 'error' === $message[0] ? 'alert' : 'status' ) . '" tabindex="-1" data-wpcpm-card-message>' . esc_html( $message[1] ) . '</p>' ), true );
}
WPCPM_Flash::set( WPCPM_Institution_Request::FLASH_DETAILS, array( 'status' => 'saved', 'record' => B ), 7 );
ck( 'what was saved for another institution is not said on this one\'s card, and is taken all the same', array( has( draw( 7 ), 'wpcpm-details__message' ), flash_of( 7 ) ), array( false, null ) );
WPCPM_Flash::set( WPCPM_Institution_Request::FLASH_DETAILS, array( 'status' => 'saved', 'record' => A ), 7 );
ck( 'and what was saved for this one is', has( draw( 7 ), 'Your institution&#039;s details are saved.' ), true );
WPCPM_Flash::set( WPCPM_Institution_Request::FLASH_DETAILS, array( 'status' => 'nonsense' ), 7 );
ck( 'a status nobody knows draws nothing, and is taken all the same', array( has( draw( 7 ), 'wpcpm-details__message' ), flash_of( 7 ) ), array( false, null ) );

echo "\n=== An Administrator corrects at once, in both groups ===\n";

fresh();
$out = post_as( 1, 'now', array( 'wpcpm_details_signer_title' => 'Rector' ) );
ck( 'an Administrator\'s save lands on the school they are viewing', $out, manager_back() );
ck( 'and writes at once', array( $GLOBALS['records'][ A ]['signer_title'], $GLOBALS['patched'], requests() ), array( 'Rector', array(), array() ) );
$sent = mails( 'institution-details-saved' );
ck( 'and tells the people the setting names, by the Administrator\'s name', array( count( $sent ), $sent[0]['to'], has( $sent[0]['body'], 'Manager changed Universidad Ejemplo\'s details on the Institution Dashboard:' ), has( $sent[0]['body'], 'Their title: Dean of Engineering, now Rector' ) ), array( 1, NOTIFY, true, true ) );
ck( 'in a subject that does not say the institution did it, since the school corrected nothing', $sent[0]['subject'], '[Example Site] Universidad Ejemplo\'s details were corrected' );
ck( 'in the history as an Administrator\'s own', array( audit( A, 'details_saved' )[0]['ground'], audit( A, 'details_saved' )[0]['actor'] ), array( 'manager', 1 ) );
ck( 'and is told it is saved', has( draw( 1 ), 'Your institution&#039;s details are saved.' ), true );

fresh();
$out = post_as( 1, 'checked', array( 'wpcpm_details_name' => 'Universidad Ejemplo de Valencia', 'wpcpm_details_country' => POLAND, 'wpcpm_details_contact_email' => 'NEW@example.test' ) );
ck( 'an Administrator\'s post of the second group writes at once too', array( $out, requests() ), array( manager_back(), array() ) );
ck(
	'to Airtable in one update, the country as a list of one record, and to the index',
	array(
		count( $GLOBALS['patched'] ),
		$GLOBALS['patched'][0][1][0]['fields'],
		$GLOBALS['index'][ A ]['name'],
		$GLOBALS['index'][ A ]['country'],
		$GLOBALS['index'][ A ]['country_name'],
		$GLOBALS['index'][ A ]['contact_email'],
	),
	array( 1, array( 'Name' => 'Universidad Ejemplo de Valencia', 'Country' => array( POLAND ), 'Contact Email' => 'NEW@example.test' ), 'Universidad Ejemplo de Valencia', POLAND, 'Poland', 'new@example.test' )
);
$sent = mails( 'institution-details-saved' );
ck( 'one "saved" email, no "request" one, and the history says so', array( count( $GLOBALS['mail'] ), count( $sent ), count( audit( A, 'details_saved' ) ), count( audit( A, 'request_raised' ) ) ), array( 1, 1, 1, 0 ) );
ck( 'the email names the country by its name', has( $sent[0]['body'], 'Country: Costa Rica, now Poland' ), true );

echo "\n=== An Administrator's second form is worded as the save it is ===\n";

// An Administrator's post of the second group is saved at once, so its form reads as the first group's does;
// the school's accounts, whose post is checked first, keep the words of a request.
fresh();
$admin_checked  = disclosure( draw( 1 ), 'checked' );
$member_checked = disclosure( draw( 7 ), 'checked' );
ck( 'and still posts the second group\'s action, under the note that it is saved at once', array( has( $admin_checked, '<input type="hidden" name="action" value="wpcpm_ask_details" />' ), has( $admin_checked, 'You are an Administrator, so these are saved at once and nobody approves them.' ) ), array( true, true ) );

echo "\n=== The card's forms leave every rule to the server ===\n";

// A box the browser would refuse (a stored contact email of two addresses, say) must not stop a post of the
// rest: the handlers hold every rule, and say what is wrong in the application's words.
fresh();
post_as( 7, 'checked', array( 'wpcpm_details_city' => 'Valencia' ) );
$forms = array();
foreach ( array( draw( 7 ), draw( 8 ), draw( 1 ), draw( 9, B ) ) as $page ) {
	preg_match_all( '#<form [^>]*>#', $page, $found );
	$forms = array_merge( $forms, $found[0] );
}
$classes = array();
foreach ( $forms as $tag ) {
	if ( preg_match( '#class="([^"]+)"#', $tag, $class ) ) { $classes[ $class[1] ] = true; }
}
ck( 'and every one of them carries novalidate', array_values( array_filter( $forms, static function ( $tag ) { return 1 !== preg_match( '#\snovalidate[\s>]#', $tag ); } ) ), array() );

echo "\n=== An Administrator, while the school's request waits ===\n";

// One request is decided in the one place it is decided. An Administrator's direct correction of the same
// details would leave the school's request open over a value it was not asked against, so the second group
// offers the Administrator no form either and says where to decide; the first group stays open to everyone.
$decide = 'A request from the school is waiting. Decide it in Requests from institutions on the Administrator Dashboard.';

fresh();
post_as( 7, 'checked', array( 'wpcpm_details_city' => 'Valencia' ) );
$id    = (int) array_key_first( requests() );
$admin = draw( 1 );
ck( 'an Administrator viewing the school is not offered the second group\'s form while its request waits, and the first group\'s is open to them', array( disclosure( $admin, 'checked' ), has( $admin, 'wpcpm_ask_details' ), has( disclosure( $admin, 'now' ), 'Correct these' ) ), array( '', false, true ) );
WPCPM_Administrators_Dashboard::$url = '';
$plain_admin = draw( 1 );
WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';
ck( 'the school\'s own accounts are told nothing of the kind: they read the waiting lines and Withdraw it', array( has( draw( 8 ), 'wpcpm-details__decide' ), has( draw( 8 ), 'Withdraw it' ) ), array( false, true ) );
ck( 'nor is a school with no request waiting: the Administrator is offered the form', array( has( disclosure( draw( 1, B ), 'checked' ), '<input type="hidden" name="action" value="wpcpm_ask_details" />' ), has( draw( 1, B ), 'wpcpm-details__decide' ) ), array( true, false ) );

$mails_before = count( $GLOBALS['mail'] );
$logged       = count( audit( A, 'details_saved' ) );
$out          = post_as( 1, 'checked', array( 'wpcpm_details_name' => 'Written By An Administrator' ) );
$page         = draw( 1 );
ck( 'nothing is written, sent or made, and the school\'s request is as it was asked', array( $GLOBALS['patched'], $GLOBALS['index'][ A ]['name'], count( $GLOBALS['mail'] ) - $mails_before, count( audit( A, 'details_saved' ) ) - $logged, count( requests() ), requests()[ $id ]['state'], requests()[ $id ]['changes']['city']['to'] ), array( array(), 'Universidad Ejemplo ', 0, 0, 1, 'open', 'Valencia' ) );
$out = post_as( 1, 'now', array( 'wpcpm_details_signer_title' => 'Rector' ) );
ck( 'while the first group still saves at once for them', array( $out, $GLOBALS['records'][ A ]['signer_title'], count( mails( 'institution-details-saved' ) ) ), array( manager_back(), 'Rector', 1 ) );
WPCPM_Institution_Request::settle( $id, 'withdrawn', '', 7, 'member' );
$out = post_as( 1, 'checked', array( 'wpcpm_details_name' => 'Written By An Administrator' ) );
ck( 'and once the request is closed the second group is theirs again', array( $out, $GLOBALS['index'][ A ]['name'], requests()[ $id ]['state'] ), array( manager_back(), 'Written By An Administrator', 'withdrawn' ) );

echo "\n=== Refusals give back what was typed ===\n";

fresh();
$out = post_as( 7, 'checked', array( 'wpcpm_details_name' => 'Valencia University', 'wpcpm_details_city' => '', 'wpcpm_details_contact_email' => 'not-an-email' ) );
$page = draw( 7 );
$form = disclosure( $page, 'checked' );
ck( 'a refused ask is sent back to the card', $out, fold_back( 'checked' ) );
ck( 'nothing was made, written or sent', array( requests(), $GLOBALS['patched'], $GLOBALS['mail'], audit( A, 'request_raised' ) ), array( array(), array(), array(), array() ) );
ck( 'the form is open again, holding what was typed, the refused fields included', array( is_open( $form ), box( $form, 'wpcpm_details_name' ), box( $form, 'wpcpm_details_city' ), box( $form, 'wpcpm_details_contact_email' ) ), array( true, 'Valencia University', '', 'not-an-email' ) );
ck( 'and only those two are marked invalid, so a screen reader is told', substr_count( $form, 'aria-invalid="true"' ), 2 );
$again = disclosure( draw( 7 ), 'checked' );
ck( 'and the typing is given back once', array( has( $again, 'Valencia University' ), is_open( $again ), box( $again, 'wpcpm_details_name' ) ), array( false, false, 'Universidad Ejemplo' ) );

fresh();
$out  = post_as( 7, 'now', array( 'wpcpm_details_contact_role' => '', 'wpcpm_details_contact_person' => 'Contact Two', 'wpcpm_details_student_languages' => array( 'French' ) ) );
$page = draw( 7 );
$form = disclosure( $page, 'now' );
ck( 'a refused save keeps every typed value of the first group too', array( $out, box( $form, 'wpcpm_details_contact_person' ), box( $form, 'wpcpm_details_contact_role' ), ticked( $form ) ), array( fold_back( 'now' ), 'Contact Two', '', array( 'French' ) ) );
ck( 'the line says nothing was saved', has( $page, 'Nothing was saved' ), true );
ck( 'and nothing is written', array( $GLOBALS['patched'], $GLOBALS['records'][ A ]['contact_role'], $GLOBALS['mail'], audit( A, 'details_saved' ) ), array( array(), 'Head of the international office', array(), array() ) );

fresh();
post_as( 7, 'now', array( 'wpcpm_details_contact_role' => '', 'wpcpm_details_student_languages' => array( 'French', 'Klingon' ) ) );
ck( 'a language the application does not offer is not kept with the typing', $GLOBALS['umeta'][7]['wpcpm_flash']['typed:details:' . A . ':now']['typed']['student_languages'] ?? null, array( 'French' ) );

fresh();
$out = post_as( 7, 'now', array(), A, array( 'wpcpm_details_student_languages' => array() ) );
ck( 'the languages are required: none ticked is refused', array( $out, has( disclosure( draw( 7 ), 'now' ), 'id="wpcpm-details-student_languages-problem"' ), $GLOBALS['patched'], $GLOBALS['mail'] ), array( fold_back( 'now' ), true, array(), array() ) );

fresh();
$out = post_as( 7, 'now', array( 'wpcpm_details_signer_name' => '', 'wpcpm_details_signer_title' => 'Dean of Engineering' ) );
ck( 'a title with no name beside it is refused as the application refuses it', array( $out, has( disclosure( draw( 7 ), 'now' ), 'id="wpcpm-details-signer_name-problem"' ), $GLOBALS['records'][ A ]['signer_name'] ), array( fold_back( 'now' ), true, 'Signer One' ) );

fresh();
$out  = post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact <12 and >18' ) );
$page = draw( 7 );
ck( 'a text the cleaner would take words from is refused whole, in the sentence for the box', array( $out, has( $page, esc_html( WPCPM_Typed_Text::loss_message( 'Name of the person we should contact' ) ) ), $GLOBALS['patched'], $GLOBALS['index'][ A ]['contact_person'], $GLOBALS['mail'] ), array( fold_back( 'now' ), true, array(), 'Contact One', array() ) );
ck( 'and the box gets back what was typed, exactly', box( disclosure( $page, 'now' ), 'wpcpm_details_contact_person' ), 'Contact <12 and >18' );
fresh();
post_as( 7, 'checked', array( 'wpcpm_details_city' => 'Valencia <3 and >' ) );
ck( 'the second form says its refusal in the words of a send', has( draw( 7 ), esc_html( WPCPM_Typed_Text::loss_message( 'City', 'send' ) ) ), true );

fresh();
post_as( 7, 'checked', array( 'wpcpm_details_website' => 'javascript:alert(1)', 'wpcpm_details_country' => 'recXXXXXXXXXXXXXX' ) );
$form = disclosure( draw( 7 ), 'checked' );
ck( 'a web address of another scheme and a country the map does not offer are refused with the application\'s sentences', array( has( $form, 'id="wpcpm-details-website-problem"' ), has( $form, 'id="wpcpm-details-country-problem">Choose one of the options offered.' ), requests() ), array( true, true, array() ) );

fresh();
post_as( 7, 'now', array( 'wpcpm_details_name' => 'Smuggled', 'wpcpm_details_contact_role' => 'Dean of Studies' ) );
ck( 'a key of the other group posted to a form is not read', array( $GLOBALS['index'][ A ]['name'], $GLOBALS['records'][ A ]['contact_role'] ), array( 'Universidad Ejemplo ', 'Dean of Studies' ) );

echo "\n=== Review Focus 4: Airtable refuses a contact person's save ===\n";

fresh();
$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'Airtable said 422' );
$out                 = post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact Two', 'wpcpm_details_contact_role' => 'Dean of Studies', 'wpcpm_details_student_languages' => array( 'French', 'Spanish' ) ) );
$page                = draw( 7 );
$form                = disclosure( $page, 'now' );
ck( 'the member is sent back to the card', $out, fold_back( 'now' ) );
ck( 'what the base answered is not said to the school', has( $page, 'Airtable said 422' ), false );
ck( 'nothing was written on the site: not the index, not the record', array( $GLOBALS['index'][ A ]['contact_person'], $GLOBALS['records'][ A ]['contact_role'], $GLOBALS['records'][ A ]['student_languages'], in_array( 'index', $GLOBALS['events'], true ), in_array( 'record', $GLOBALS['events'], true ) ), array( 'Contact One', 'Head of the international office', array( 'Spanish', 'English' ), false, false ) );
ck( 'no history row and no email', array( audit( A, 'details_saved' ), $GLOBALS['mail'] ), array( array(), array() ) );
ck( 'every typed value comes back, the ones that were not Airtable\'s too', array( has( $form, ' open' ), box( $form, 'wpcpm_details_contact_person' ), box( $form, 'wpcpm_details_contact_role' ), ticked( $form ) ), array( true, 'Contact Two', 'Dean of Studies', array( 'French', 'Spanish' ) ) );
ck( 'and the lists above still read what the program has', array( rows( $page )['Contact person'], rows( $page )['Their role'] ), array( 'Contact One', 'Head of the international office' ) );

fresh();
$GLOBALS['airtable'] = array();
post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact Two' ) );
ck( 'an answer of nothing written is a refusal too', array( has( draw( 7 ), 'The program records could not be updated, so nothing was saved. Try again in a while.' ), $GLOBALS['index'][ A ]['contact_person'], $GLOBALS['mail'] ), array( true, 'Contact One', array() ) );

fresh();
$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'Airtable said 422' );
post_as( 1, 'checked', array( 'wpcpm_details_name' => 'Written By An Administrator' ) );
$page = draw( 1 );
ck( 'an Administrator\'s direct correction is refused the same way, and keeps their typing', array( has( $page, 'The program records could not be updated, so nothing was saved.' ), box( disclosure( $page, 'checked' ), 'wpcpm_details_name' ), $GLOBALS['index'][ A ]['name'] ), array( true, 'Written By An Administrator', 'Universidad Ejemplo ' ) );

fresh();
unset( $GLOBALS['index'][ A ] );
post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact Two' ) );
ck( 'an institution the program records do not hold yet is said in the details class\'s own words, and keeps the typing', array( has( plain( draw( 7 ) ), 'This institution is not in the program records yet, so nothing was saved.' ), $GLOBALS['patched'] ), array( true, array() ) );
fresh();
unset( $GLOBALS['index'][ A ] );
post_as( 7, 'checked', array( 'wpcpm_details_name' => 'Valencia University' ) );
ck( 'and a request for it is not made, since it could not be approved', array( requests(), has( plain( draw( 7 ) ), 'This institution is not in the program records yet, so nothing was sent.' ) ), array( array(), true ) );

echo "\n=== Review Focus 5: a post that changes nothing ===\n";

fresh();
$out = post_as( 7, 'now' );
ck( 'the first form posted as it was drawn is sent back to the card', $out, member_back() );
ck( 'nothing was written, made or sent', array( $GLOBALS['patched'], $GLOBALS['events'], $GLOBALS['mail'], audit( A, 'details_saved' ), requests() ), array( array(), array( 'nonce wpcpm_save_details_' . A ), array(), array(), array() ) );

fresh();
$out = post_as( 7, 'checked' );
ck( 'the second form posted as it was drawn raises no request and sends no email', array( $out, requests(), $GLOBALS['mail'], audit( A, 'request_raised' ), has( draw( 7 ), 'Nothing was changed.' ) ), array( member_back(), array(), array(), array(), true ) );

fresh();
post_as( 1, 'now' );
post_as( 1, 'checked' );
ck( 'an Administrator\'s untouched posts do nothing either', array( $GLOBALS['patched'], $GLOBALS['mail'], audit( A, 'details_saved' ), has( draw( 1 ), 'Nothing was changed.' ) ), array( array(), array(), array(), true ) );

fresh();
post_as( 7, 'checked', array( 'wpcpm_details_name' => '  Universidad Ejemplo  ', 'wpcpm_details_contact_email' => 'CONTACT@EXAMPLE.TEST' ) );
ck( 'space around a value and the case of an email are no difference', array( requests(), $GLOBALS['mail'], has( draw( 7 ), 'Nothing was changed.' ) ), array( array(), array(), true ) );
fresh();
post_as( 7, 'now', array( 'wpcpm_details_student_languages' => array( 'English', 'Spanish' ) ) );
ck( 'nor are the same languages in another order', array( $GLOBALS['mail'], $GLOBALS['records'][ A ]['student_languages'], has( draw( 7 ), 'Nothing was changed.' ) ), array( array(), array( 'Spanish', 'English' ), true ) );

fresh();
$GLOBALS['index'][ A ]['contact_person'] = 'Contact %20 One';
$out                                     = post_as( 7, 'now', array( 'wpcpm_details_contact_role' => 'Dean of Studies' ) );
ck( 'a value the cleaner would alter, posted back as it was drawn, is left as it is and is no reason to refuse the save', array( $out, $GLOBALS['patched'], $GLOBALS['index'][ A ]['contact_person'], $GLOBALS['records'][ A ]['contact_role'], count( mails( 'institution-details-saved' ) ) ), array( member_back(), array(), 'Contact %20 One', 'Dean of Studies', 1 ) );
ck( 'and the email names only what changed', has( mails( 'institution-details-saved' )[0]['body'], 'Contact person' ), false );
fresh();
$GLOBALS['index'][ A ]['contact_person'] = 'Contact %20 One';
post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact %20 Two' ) );
ck( 'a person who does edit it is held to the rules, and told when the cleaner would take words from it', array( has( draw( 7 ), esc_html( WPCPM_Typed_Text::loss_message( 'Name of the person we should contact' ) ) ), $GLOBALS['patched'] ), array( true, array() ) );

echo "\n=== Two tabs post the same correction: it is saved once ===\n";

/**
 * Plant another request (`$post`): a second tab posting the same correction, played once, at the first of
 * two moments in the request under test. Just before that request takes a lock, which is where a request
 * that waits for the school's lock lets the other finish first; or, should it take none, just before its
 * write to Airtable or to the record, which is where a request that read the same values before the other
 * wrote them makes its own.
 */
function other_tab( callable $post ) {
	$played = false;
	$play   = static function () use ( &$played, $post ) {
		if ( $played ) { return; }
		$played                         = true;
		$GLOBALS['before_write']        = null;
		$GLOBALS['wpdb']->before_insert = null;
		$uid                            = $GLOBALS['uid'];
		$posted                         = $_POST;
		$GLOBALS['other_tab_out']       = $post();
		$GLOBALS['uid']                 = $uid;
		$_POST                          = $posted;
	};
	$GLOBALS['before_write']        = $play;
	$GLOBALS['wpdb']->before_insert = $play;
}
/** Whether the school's lock row is in the options table. */
function school_lock_held( $record = A ) { return array_key_exists( 'wpcpm_req_lock_details_' . $record, $GLOBALS['opts'] ); }

fresh();
$edit = array( 'wpcpm_details_contact_person' => 'Contact Two' );
other_tab( static function () use ( $edit ) { return post_as( 7, 'now', $edit ); } );
$out = post_as( 7, 'now', $edit );
ck( 'the first tab\'s post saves, and lands on the card', $GLOBALS['other_tab_out'] ?? null, member_back() );
ck( 'the second, posted with the same change before the first had saved, writes nothing to Airtable, the history or anybody\'s mail', array( count( $GLOBALS['patched'] ), count( audit( A, 'details_saved' ) ), count( mails( 'institution-details-saved' ) ), $GLOBALS['index'][ A ]['contact_person'] ), array( 1, 1, 1, 'Contact Two' ) );
ck( 'the school\'s lock is let go', school_lock_held(), false );

fresh();
$edit = array( 'wpcpm_details_contact_role' => 'Dean of Studies' );
other_tab( static function () use ( $edit ) { return post_as( 8, 'now', $edit ); } );
$out = post_as( 7, 'now', $edit );
ck( 'so is a colleague\'s same change of a detail kept on the site, with no sentence that it could not be kept', array( count( audit( A, 'details_saved' ) ), count( mails( 'institution-details-saved' ) ), $GLOBALS['records'][ A ]['contact_role'], has( draw( 7 ), 'Nothing was changed.' ), school_lock_held() ), array( 1, 1, 'Dean of Studies', true, false ) );

fresh();
$edit = array( 'wpcpm_details_name' => 'Universidad Ejemplo de Valencia' );
other_tab( static function () use ( $edit ) { return post_as( 1, 'checked', $edit ); } );
$out = post_as( 1, 'checked', $edit );
ck( 'and an Administrator\'s own correction of the second group, posted twice, is written once', array( count( $GLOBALS['patched'] ), count( audit( A, 'details_saved' ) ), count( mails( 'institution-details-saved' ) ), $GLOBALS['index'][ A ]['name'], $out, has( draw( 1 ), 'Nothing was changed.' ), school_lock_held() ), array( 1, 1, 1, 'Universidad Ejemplo de Valencia', manager_back(), true, false ) );

fresh();
post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact Two' ) );
ck( 'what is on record is read again under the school\'s lock, and written and let go inside it', in_order( implode( '|', $GLOBALS['events'] ), array( 'lock insert', 'forget index', 'forget record', 'airtable', 'lock delete' ) ), true );
ck( 'and the email goes once the lock is let go', in_order( implode( '|', $GLOBALS['events'] ), array( 'lock delete', 'mail institution-details-saved' ) ), true );

fresh();
$GLOBALS['opts']['wpcpm_req_lock_details_' . A ] = (string) ( time() + 300 );
$out     = post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact Two' ) );
$flashed = flash_of( 7 );
$page    = draw( 7 );
$saving = 'Someone else is saving your institution&#039;s details at this moment, so nothing was done. Try again in a moment.';
ck( 'its typing comes back, and the lock it found is left to its holder', array( box( disclosure( $page, 'now' ), 'wpcpm_details_contact_person' ), $GLOBALS['opts'][ 'wpcpm_req_lock_details_' . A ] ?? null ), array( 'Contact Two', (string) ( time() + 300 ) ) );
ck( 'in a status of its own, not the deciding sentence of a request', array( $flashed['status'] ?? null, has( $page, 'deciding this request' ) ), array( 'saving-busy', false ) );
ck( 'a save that finds the school\'s lock held saves, records and sends nothing', array( $out, has( plain( $page ), html_entity_decode( $saving, ENT_QUOTES ) ), $GLOBALS['patched'], $GLOBALS['index'][ A ]['contact_person'], audit( A, 'details_saved' ), $GLOBALS['mail'] ), array( fold_back( 'now' ), true, array(), 'Contact One', array(), array() ) );

fresh();
$GLOBALS['opts']['wpcpm_req_lock_details_' . A ] = (string) ( time() + 300 );
$out  = post_as( 7, 'checked', array( 'wpcpm_details_city' => 'Valencia' ) );
$page = draw( 7 );
$form = disclosure( $page, 'checked' );
ck( 'and its typing comes back in the open form, the lock left to its holder', array( is_open( $form ), box( $form, 'wpcpm_details_city' ), $GLOBALS['opts'][ 'wpcpm_req_lock_details_' . A ] ?? null ), array( true, 'Valencia', (string) ( time() + 300 ) ) );

$released = array();
fresh();
post_as( 7, 'now' );
$released['a post that changes nothing'] = school_lock_held();
fresh();
$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'Airtable said 422' );
post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact Two' ) );
$released['an Airtable refusal'] = school_lock_held();
fresh();
unset( $GLOBALS['index'][ A ] );
post_as( 1, 'now', array( 'wpcpm_details_contact_person' => 'Contact Two' ) );
$released['a refusal of the details class'] = school_lock_held();
fresh();
post_as( 1, 'now', array( 'wpcpm_details_signer_title' => 'Rector' ) );
$released['an Administrator\'s save'] = school_lock_held();
ck( 'the lock is let go on every path', $released, array( 'a post that changes nothing' => false, 'an Airtable refusal' => false, 'a refusal of the details class' => false, 'an Administrator\'s save' => false ) );

echo "\n=== The handlers check the nonce and the policy before any write ===\n";

fresh();
$GLOBALS['nonce_ok'] = false;
$out                 = post_as( 7, 'now', array( 'wpcpm_details_contact_role' => 'Dean of Studies' ) );
ck( 'a form whose nonce fails writes nothing, and is stopped at the nonce, which is keyed to the institution', array( $out, $GLOBALS['records'][ A ]['contact_role'], $GLOBALS['patched'], $GLOBALS['mail'] ), array( 'nonce:wpcpm_save_details_' . A, 'Head of the international office', array(), array() ) );
$out = post_as( 7, 'checked', array( 'wpcpm_details_name' => 'Valencia University' ) );
ck( 'and so does the second', array( $out, requests() ), array( 'nonce:wpcpm_ask_details_' . A, array() ) );
$GLOBALS['nonce_ok'] = true;

$refusal = 'wp_die:' . WPCPM_Institution_Policy::refusal()->get_error_message();
ck( 'a member of another school is refused with the policy\'s one refusal, in both groups', array( post_as( 9, 'now', array( 'wpcpm_details_contact_role' => 'Hijack' ), A ), post_as( 9, 'checked', array( 'wpcpm_details_name' => 'Hijack' ), A ) ), array( $refusal, $refusal ) );
ck( 'a stranger and a record that is no record are refused the same, byte for byte', array( post_as( 5, 'now', array(), A ), post_as( 7, 'now', array(), 'not-a-record' ), post_as( 7, 'checked', array(), 'not-a-record' ), post_as( 7, 'now', array(), 'recINSTITUTION999' ) ), array( $refusal, $refusal, $refusal, $refusal ) );
ck( 'a member whose agreement is not settled is refused too', array( post_as( 10, 'now', array( 'wpcpm_details_contact_role' => 'Hijack' ), C ), post_as( 10, 'checked', array( 'wpcpm_details_name' => 'Hijack' ), C ) ), array( $refusal, $refusal ) );
ck( 'none of them wrote, made or sent anything', array( $GLOBALS['records'][ A ]['contact_role'], $GLOBALS['index'][ A ]['name'], $GLOBALS['patched'], requests(), $GLOBALS['mail'], count( audit( A, 'details_saved' ) ) ), array( 'Head of the international office', 'Universidad Ejemplo ', array(), array(), array(), 0 ) );
// Institutions release 1 (Review Focus 4): a tutor of the school, whose agreement is settled, posts each form
// with a valid nonce (`nonce_ok` is true here) and every field filled.
fresh();
$GLOBALS['users'][14]   = new WP_User( 14, 'Tess Tutor', 'tess@example.test', array( 'subscriber' ) );
$GLOBALS['memberships'][14] = array( A );
$GLOBALS['levels']      = array( 14 => 'tutor' );
$opts_before            = serialize( $GLOBALS['opts'] );
$records_before         = serialize( $GLOBALS['records'] );
ck( 'a tutor of the school is refused with the one refusal, in both groups', array( post_as( 14, 'now', array( 'wpcpm_details_contact_role' => 'Hijack', 'wpcpm_details_student_languages' => array( 'French' ) ), A ), post_as( 14, 'checked', array( 'wpcpm_details_name' => 'Hijack' ), A ) ), array( $refusal, $refusal ) );
ck( 'and nothing was written, asked, mailed, logged, stored or sent to Airtable', array( serialize( $GLOBALS['records'] ) === $records_before, $GLOBALS['index'][ A ]['name'], $GLOBALS['patched'], requests(), $GLOBALS['mail'], count( audit( A, 'details_saved' ) ), $GLOBALS['posts'], serialize( $GLOBALS['opts'] ) === $opts_before ), array( true, 'Universidad Ejemplo ', array(), array(), array(), 0, array(), true ) );
ck( 'and the card is not drawn for a tutor: no read view, no form, no request to approve', draw( 14, A ), '' );
$GLOBALS['levels'] = array();
ck( 'while a representative of the same school is drawn it', has( draw( 7, A ), 'id="wpcpm-details"' ), true );
fresh();
ck( 'a signed-out request is refused', array( post_as( 0, 'now', array(), A ) ), array( $refusal ) );
ck( 'an Administrator may act on any school, the unsettled one included', array( post_as( 1, 'now', array( 'wpcpm_details_contact_role' => 'Set By An Administrator', 'wpcpm_details_student_languages' => array( 'English' ) ), C ), $GLOBALS['records'][ C ]['contact_role'] ), array( manager_back( C ), 'Set By An Administrator' ) );

$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-details-card.php' );
$run = (string) method_body( $src, 'handle' );
$now = (string) method_body( $src, 'save_now' );
ck( 'the one shared handler checks the nonce, then asks the policy, then writes or raises, in that order', array(
	before( $run, 'check_admin_referer(', 'WPCPM_Institution_Policy::decide(' ),
	before( $run, 'WPCPM_Institution_Policy::decide(', 'self::save_now(' ),
	before( $run, 'WPCPM_Institution_Policy::decide(', 'WPCPM_Institution_Request::raise_details(' ),
), array( true, true, true ) );
ck( 'and writes under the school\'s lock, reading again what is on record first, letting it go in a finally', array(
	before( $now, 'WPCPM_Lock::take( $lock', 'WPCPM_Institution_Details::forget_cached(' ),
	before( $now, 'WPCPM_Institution_Details::forget_cached(', 'WPCPM_Institution_Details::changes(' ),
	before( $now, 'WPCPM_Institution_Details::changes(', 'WPCPM_Institution_Details::write(' ),
	before( $now, '} finally {', 'WPCPM_Lock::release( $lock, $token )' ),
	has( $now, 'WPCPM_Institution_Request::school_lock_name( $record )' ),
	substr_count( $src, 'WPCPM_Institution_Details::write( $record' ),
), array( true, true, true, true, true, 1 ) );
ck( 'the policy it asks is the details action, in the card and in the handler', array( substr_count( $src, 'WPCPM_Institution_Policy::ACT_EDIT_DETAILS' ) >= 2, has( $run, 'ACT_EDIT_DETAILS' ) ), array( true, true ) );
ck( 'the card writes through the details class and never to Airtable or the posts itself', array( substr_count( $src, '->update_records(' ), substr_count( $src, 'wp_insert_post(' ), substr_count( $src, 'update_post_meta(' ) ), array( 0, 0, 0 ) );
ck( 'the one email it sends goes through the request class\'s helper for the details emails, which builds it from the template registry', array( substr_count( $src, 'WPCPM_Institution_Request::mail_managers(' ), substr_count( $src, 'WPCPM_Mail::' ), substr_count( $src, 'self::MAIL_SAVED' ) >= 1 ), array( 1, 0, true ) );


echo "\n=== Tracks offered: the section and its handler ===\n";

$GLOBALS['labels'] = array(
	'In Sensei'       => 'WordPress Credits Program 150h',
	'In Sensei 50h'   => 'WordPress Credits Program 50h',
	'Designer Track'  => 'Designer Track',
	'Developer Track' => 'Developer Track',
);
const TRACK_FIELD = 'wpcpm_tracks_offered';
/** The tracks section of a page: from its small heading to the end of the card's body. */
function tracks_part( $html ) {
	$at = section_at( $html, 'Tracks offered' );
	$to = section_at( $html, 'Assigned Administrator' );
	return false === $at ? '' : ( false === $to ? substr( $html, $at ) : substr( $html, $at, $to - $at ) );
}
/** Where the parts' section heading with these words opens (`<h4 class="wpcpm-ui-h"...>words</h4>`), or false. */
function section_at( $html, $words ) {
	return preg_match( '#<h4 class="wpcpm-ui-h"[^>]*>' . preg_quote( $words, '#' ) . '</h4>#', (string) $html, $m, PREG_OFFSET_CAPTURE ) ? $m[0][1] : false;
}
/** Post the Tracks offered form as one account. */
function post_tracks( $uid, array $statuses, $record = A, array $extra = array() ) {
	$GLOBALS['uid'] = (int) $uid;
	$_POST          = array_merge( array( 'record' => $record, 'action' => 'wpcpm_institution_tracks_save', TRACK_FIELD => $statuses ), $extra );
	try {
		WPCPM_Institution_Tracks::handle_save();
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
	return '';
}

ck( 'the actions and the field are named', array( WPCPM_Institution_Tracks::ACTION_SAVE, WPCPM_Institution_Tracks::FIELD ), array( 'wpcpm_institution_tracks_save', 'wpcpm_tracks_offered' ) );

fresh();
$GLOBALS['records'][ A ]['tracks_offered'] = array( 'Developer Track', 'In Sensei', 'Retired Track' );

$member = tracks_part( draw( 7 ) );
ck( 'and is drawn no form, no checkbox and no action to post, under the section\'s heading', array( has( $member, '>Tracks offered</h4>' ), has( $member, '<form' ), has( $member, 'checkbox' ), has( $member, 'wpcpm_institution_tracks_save' ), has( $member, TRACK_FIELD ) ), array( true, false, false, false, false ) );
$GLOBALS['settings']['mail_contact'] = 'help@example.test';
ck( 'the contact address is the setting while it holds one', has( plain( draw( 7 ) ), 'help@example.test' ), true );
$GLOBALS['settings']['mail_contact'] = '';

$GLOBALS['records'][ A ]['tracks_offered'] = array();
$GLOBALS['records'][ A ]['tracks_offered'] = array( 'Developer Track', 'In Sensei', 'Retired Track' );

ck( 'the form is not drawn to a reader the policy refuses', draw( 9, A ), '' );

$GLOBALS['records'][ A ]['tracks_offered'] = array();
$GLOBALS['records'][ A ]['tracks_offered'] = array( 'Developer Track', 'In Sensei', 'Retired Track' );

echo "\n-- the handler --\n";

fresh();
$GLOBALS['records'][ A ]['tracks_offered'] = array( 'In Sensei', 'Retired Track' );
$before                                    = $GLOBALS['records'][ A ];
$refusal                                   = 'wp_die:' . WPCPM_Institution_Policy::refusal()->get_error_message();
ck( 'a member\'s hand-made post with a valid nonce is refused with the policy\'s 403', post_tracks( 7, array( 'Designer Track' ) ), $refusal );
ck( 'and nothing was kept or audited', array( $GLOBALS['records'][ A ], count( audit( A, 'tracks_saved' ) ), $GLOBALS['umeta'] ), array( $before, 0, array() ) );
ck( 'another school\'s member, a stranger, a signed-out post and a bad record are refused alike', array( post_tracks( 9, array( 'Designer Track' ) ), post_tracks( 5, array( 'Designer Track' ) ), post_tracks( 0, array( 'Designer Track' ) ), post_tracks( 1, array( 'Designer Track' ), 'not-a-record' ) ), array( $refusal, $refusal, $refusal, $refusal ) );
ck( 'still nothing written', $GLOBALS['records'][ A ], $before );

$GLOBALS['nonce_ok'] = false;
ck( 'a nonce for another record is refused by check_admin_referer, keyed to the posted record', post_tracks( 1, array( 'Designer Track' ), B ), 'nonce:wpcpm_institution_tracks_save_' . B );
$GLOBALS['nonce_ok'] = true;
ck( 'and nothing was written', array( $GLOBALS['records'][ A ], isset( $GLOBALS['records'][ B ]['tracks_offered'] ) ), array( $before, false ) );

$GLOBALS['events'] = array();
ck( 'an Administrator\'s post saves, and goes back to the card on the school they were viewing', post_tracks( 1, array( 'Developer Track', 'In Sensei 50h' ) ), tracks_back( A ) );
ck( 'the list is kept in the Track Builder\'s order, with the retired track kept', $GLOBALS['records'][ A ]['tracks_offered'], array( 'In Sensei 50h', 'Developer Track', 'Retired Track' ) );
$flash = flash_of( 1 );
ck( 'the outcome is flashed on the card\'s channel, for the tracks section', array( $flash['status'], $flash['record'], $flash['section'] ), array( 'tracks-saved', A, 'tracks' ) );
ck( 'one audit row', count( audit( A, 'tracks_saved' ) ), 1 );
ck( 'the nonce was checked first, then the policy let it through', $GLOBALS['events'][0], 'nonce wpcpm_institution_tracks_save_' . A );
$page = draw( 1 );
ck( 'the line is read once, under the Tracks heading and not at the top of the card', array( has( $page, 'The tracks offered are saved.' ), strpos( $page, 'The tracks offered are saved.' ) > strpos( $page, '>Tracks</h4>' ), has( draw( 1 ), 'The tracks offered are saved.' ) ), array( true, true, false ) );

ck( 'the same list again changes nothing, and says so', array( post_tracks( 1, array( 'In Sensei 50h', 'Developer Track' ) ), flash_of( 1 )['status'], count( audit( A, 'tracks_saved' ) ) ), array( tracks_back( A ), 'nothing', 1 ) );
draw( 1 );
ck( 'a status the site does not run is not saved', array( post_tracks( 1, array( 'In Sensei 50h', 'Made Up Track' ) ), $GLOBALS['records'][ A ]['tracks_offered'] ), array( tracks_back( A ), array( 'In Sensei 50h', 'Retired Track' ) ) );
draw( 1 );
ck( 'no list posted clears the runnable ones', array( post_tracks( 1, array() ), $GLOBALS['records'][ A ]['tracks_offered'] ), array( tracks_back( A ), array( 'Retired Track' ) ) );
draw( 1 );

// The school's lock: the tracks save is one more writer of the record's whole answers.
fresh();
$GLOBALS['records'][ A ]['tracks_offered'] = array( 'In Sensei' );
$before = $GLOBALS['records'][ A ];
$token  = WPCPM_Lock::take( 'wpcpm_req_lock_details_' . A, 300 );
$out    = post_tracks( 1, array( 'In Sensei', 'Developer Track' ) );
$busy   = flash_of( 1 );
ck( 'a save that finds the school\'s lock held writes nothing, audits nothing and says someone is saving', array( '' !== $token, $out, $GLOBALS['records'][ A ], count( audit( A, 'tracks_saved' ) ), $busy['status'] ), array( true, tracks_back( A ), $before, 0, 'saving-busy' ) );
$page = draw( 1 );
ck( 'and the card reads the busy sentence', has( plain( $page ), WPCPM_Institution_Request::saving_message() ), true );
WPCPM_Lock::release( 'wpcpm_req_lock_details_' . A, $token );
ck( 'the lock free, the same post saves', array( post_tracks( 1, array( 'In Sensei', 'Developer Track' ) ), $GLOBALS['records'][ A ]['tracks_offered'], count( audit( A, 'tracks_saved' ) ), WPCPM_Lock::take( 'wpcpm_req_lock_details_' . A, 300 ) !== '' ), array( tracks_back( A ), array( 'In Sensei', 'Developer Track' ), 1, true ) );

$tracks_src                                = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-tracks.php' );
$handler                                   = (string) method_body( $tracks_src, 'handle_save' );
ck( 'the handler checks the nonce, the record, the policy, the manager ground, and only then saves', array(
	before( $handler, 'check_admin_referer(', 'is_record_id(' ),
	before( $handler, 'is_record_id(', 'WPCPM_Institution_Policy::decide(' ),
	before( $handler, 'WPCPM_Institution_Policy::decide(', 'GROUND_MANAGER' ),
	before( $handler, 'GROUND_MANAGER', 'self::save(' ),
), array( true, true, true, true ) );

echo "\n=== Assigned Administrator: the section and its handler ===\n";

const ASSIGN_FIELD = 'wpcpm_assigned';
/** The assignment section of a page: from its small heading to the end of the card's body. */
function assign_part( $html ) {
	$at = section_at( $html, 'Assigned Administrator' );
	return false === $at ? '' : substr( $html, $at );
}
function assign_back( $record = A ) { return 'redirect:https://example.test/institution-dashboard/?wpcpm_view=institution&wpcpm_institution_view=' . $record . '#wpcpm-details-assignment-outcome'; }
/** Post the assignment form as one account. */
function post_assign( $uid, $value, $record = A, array $extra = array() ) {
	$GLOBALS['uid'] = (int) $uid;
	$_POST          = array_merge( array( 'record' => $record, 'action' => 'wpcpm_institution_assign', ASSIGN_FIELD => $value ), $extra );
	try {
		WPCPM_Institution_Assignment::handle_save();
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
	return '';
}
ck( 'the action, the field and the outcome id are named', array( WPCPM_Institution_Assignment::ACTION_SAVE, WPCPM_Institution_Assignment::FIELD, WPCPM_Institution_Assignment::OUTCOME_ID ), array( 'wpcpm_institution_assign', 'wpcpm_assigned', 'wpcpm-details-assignment-outcome' ) );

echo "\n-- the section --\n";

fresh();
$GLOBALS['records'][ A ]['assigned'] = 12;
$member_page = draw( 7 );
ck( 'a member\'s card draws no Assigned Administrator heading, name or form', array( has( $member_page, 'Assigned Administrator' ), has( $member_page, 'Bo Boss' ), has( $member_page, 'wpcpm_institution_assign' ), has( $member_page, ASSIGN_FIELD ) ), array( false, false, false, false ) );

$admin = assign_part( draw( 1 ) );
ck( 'after the Tracks section, holding the assignment form', array( false !== section_at( draw( 1 ), 'Tracks offered' ) && section_at( draw( 1 ), 'Tracks offered' ) < section_at( draw( 1 ), 'Assigned Administrator' ), has( $admin, 'name="action" value="wpcpm_institution_assign"' ) ), array( true, true ) );
$combo = end( $GLOBALS['combos'] );
ck( 'one combobox: named wpcpm_assigned, Nobody first and every Administrator A to Z, the stored one chosen', array( $combo['name'], $combo['options'], (int) $combo['current'] ), array( 'wpcpm_assigned', array( 0 => 'Nobody', 12 => 'Bo Boss', 13 => 'Cy Chief', 1 => 'Manager' ), 12 ) );
$GLOBALS['records'][ A ]['assigned'] = 0;
$GLOBALS['records'][ A ]['assigned'] = 13;
$GLOBALS['manage'] = array( 1, 12 );
$gone = assign_part( draw( 1 ) );
ck( 'an account that lost the capability reads so, and is not offered or chosen again', array( has( plain( $gone ), 'Cy Chief (not an Administrator now)' ), array_keys( end( $GLOBALS['combos'] )['options'] ), end( $GLOBALS['combos'] )['current'] ), array( true, array( 0, 12, 1 ), 0 ) );
$GLOBALS['manage'] = array( 1, 12, 13 );

echo "\n-- the handler --\n";

fresh();
$GLOBALS['records'][ A ]['assigned'] = 12;
$before  = $GLOBALS['records'][ A ];
$refusal = 'wp_die:' . WPCPM_Institution_Policy::refusal()->get_error_message();
ck( 'a member\'s hand-made post with a valid nonce is refused with the policy\'s 403', post_assign( 7, '13' ), $refusal );
ck( 'and nothing was kept, audited or mailed', array( $GLOBALS['records'][ A ], count( audit( A, 'assignment_changed' ) ), $GLOBALS['mail'] ), array( $before, 0, array() ) );
ck( 'another school\'s member, a stranger, a signed-out post and a bad record are refused alike', array( post_assign( 9, '13' ), post_assign( 5, '13' ), post_assign( 0, '13' ), post_assign( 1, '13', 'not-a-record' ) ), array( $refusal, $refusal, $refusal, $refusal ) );
ck( 'still nothing written', array( $GLOBALS['records'][ A ], $GLOBALS['mail'] ), array( $before, array() ) );
$GLOBALS['nonce_ok'] = false;
ck( 'a nonce for another record is refused by check_admin_referer, keyed to the posted record', post_assign( 1, '13', B ), 'nonce:wpcpm_institution_assign_' . B );
$GLOBALS['nonce_ok'] = true;
ck( 'and nothing was written', array( $GLOBALS['records'][ A ], isset( $GLOBALS['records'][ B ]['assigned'] ) ), array( $before, false ) );

$GLOBALS['events'] = array();
ck( 'an Administrator assigns 13, and lands on the outcome line', post_assign( 1, '13' ), assign_back( A ) );
$flash = flash_of( 1 );
ck( 'kept, one audit row, flashed for the assignment section', array( $GLOBALS['records'][ A ]['assigned'], count( audit( A, 'assignment_changed' ) ), $flash['status'], $flash['record'], $flash['section'] ), array( 13, 1, 'assignment-emailed', A, 'assignment' ) );
ck( 'the new Administrator was emailed once, naming the school', array( count( $GLOBALS['mail'] ), $GLOBALS['mail'][0]['to'], $GLOBALS['mail'][0]['context'], has( $GLOBALS['mail'][0]['body'], 'Universidad Ejemplo, in Costa Rica, is now assigned to you.' ) ), array( 1, 'cy@example.test', 'institution-assigned', true ) );
ck( 'the nonce was checked first', $GLOBALS['events'][0], 'nonce wpcpm_institution_assign_' . A );
$page = draw( 1 );
ck( 'and the top of the card does not repeat it', has( substr( $page, 0, (int) strpos( $page, '<h4' ) ), 'emailed' ), false );

$GLOBALS['mail'] = array();
ck( 'the same Administrator again changes nothing, and says so', array( post_assign( 1, '13' ), flash_of( 1 )['status'], count( audit( A, 'assignment_changed' ) ), $GLOBALS['mail'] ), array( assign_back( A ), 'nothing', 1, array() ) );
ck( 'an Administrator assigning themselves is saved and not emailed', array( post_assign( 1, '1' ), $GLOBALS['records'][ A ]['assigned'], flash_of( 1 )['status'], $GLOBALS['mail'] ), array( assign_back( A ), 1, 'assignment-saved', array() ) );
ck( 'Nobody is saved and not emailed', array( post_assign( 1, '0' ), $GLOBALS['records'][ A ]['assigned'], flash_of( 1 )['status'], $GLOBALS['mail'] ), array( assign_back( A ), 0, 'assignment-saved', array() ) );
// The notice says "emailed" only when the email went (1.123.84): a mail host that refused it leaves the
// change saved and the sentence without that clause.
$GLOBALS['mail_fails'] = true;
ck( 'an assignment whose email the mail host refuses is saved, and the notice does not say emailed', array( post_assign( 1, '13' ), $GLOBALS['records'][ A ]['assigned'], flash_of( 1 )['status'], $GLOBALS['mail'] ), array( assign_back( A ), 13, 'assignment-saved', array() ) );
$GLOBALS['mail_fails'] = false;
$words = WPCPM_Institution_Details_Card::messages();
ck( 'the two sentences are the plan\'s', array( $words['assignment-emailed'], $words['assignment-saved'] ), array( array( 'success', 'The assigned Administrator is saved, and they have been emailed.' ), array( 'success', 'The assigned Administrator is saved.' ) ) );

$GLOBALS['records'][ A ]['assigned'] = 12;
$before = $GLOBALS['records'][ A ];
$count  = count( audit( A, 'assignment_changed' ) );
ck( 'an account that is no Administrator is refused with the reason, and nothing is written', array( post_assign( 1, '7' ), flash_of( 1 )['text'], $GLOBALS['records'][ A ], $GLOBALS['mail'] ), array( assign_back( A ), 'Only an Administrator can be assigned an institution.', $before, array() ) );
ck( 'so is a value that is no number: it does not clear the assignment', array( post_assign( 1, 'abc' ), flash_of( 1 )['text'], $GLOBALS['records'][ A ], count( audit( A, 'assignment_changed' ) ) ), array( assign_back( A ), 'Only an Administrator can be assigned an institution.', $before, $count ) );
ck( 'and a post with no field at all', array( post_assign( 1, '', A, array( ASSIGN_FIELD => null ) ), $GLOBALS['records'][ A ] ), array( assign_back( A ), $before ) );

// The school's lock: one more writer of the record's whole answers.
$token = WPCPM_Lock::take( 'wpcpm_req_lock_details_' . A, 300 );
$out   = post_assign( 1, '13' );
$busy  = flash_of( 1 );
ck( 'a save that finds the school\'s lock held writes nothing and says someone is saving', array( '' !== $token, $out, $GLOBALS['records'][ A ], $busy['status'], $busy['section'] ), array( true, assign_back( A ), $before, 'saving-busy', 'assignment' ) );
WPCPM_Lock::release( 'wpcpm_req_lock_details_' . A, $token );
ck( 'the lock free, the same post saves, and the lock is let go', array( post_assign( 1, '13' ), $GLOBALS['records'][ A ]['assigned'], WPCPM_Lock::take( 'wpcpm_req_lock_details_' . A, 300 ) !== '' ), array( assign_back( A ), 13, true ) );

$assign_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-assignment.php' );
$handler    = (string) method_body( $assign_src, 'handle_save' );
ck( 'the handler checks the nonce, the record, the policy, the manager ground, and only then assigns', array(
	before( $handler, 'check_admin_referer(', 'is_record_id(' ),
	before( $handler, 'is_record_id(', 'WPCPM_Institution_Policy::decide(' ),
	before( $handler, 'WPCPM_Institution_Policy::decide(', 'GROUND_MANAGER' ),
	before( $handler, 'GROUND_MANAGER', 'self::assign(' ),
), array( true, true, true, true ) );

echo "\n=== The words the plan gives ===\n";

$words = WPCPM_Institution_Details_Card::messages();
ck( 'the outcome sentences are the plan\'s', array( $words['saved'], $words['asked'], $words['nothing'], $words['airtable'] ), array(
	array( 'success', 'Your institution\'s details are saved.' ),
	array( 'success', 'Sent: an Administrator checks the change, and you will see it here once it is decided.' ),
	array( 'info', 'Nothing was changed.' ),
	array( 'error', 'The program records could not be updated, so nothing was saved. Try again in a while.' ),
) );
ck( 'and none of them holds a dash', preg_match( '/[\x{2013}\x{2014}]/u', json_encode( $words, JSON_UNESCAPED_UNICODE ) ), 0 );

echo "\n=== The stylesheet draws the card with the page's own rules ===\n";

$css_raw = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' );
$css     = (string) preg_replace( '#/\*.*?\*/#s', '', $css_raw );
preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER );
$headed = array_values( array_filter( $rules, function ( $rule ) { return false !== strpos( $rule[1], '.wpcpm-details__subtitle' ); } ) );
$notice = array_values( array_filter( $rules, function ( $rule ) { return '.wpcpm-details__row .wpcpm-details__waiting' === trim( $rule[1] ); } ) );
// The card's own section of the sheet went with its old form's rules (Task 23, fix rounds 1 and 2): the section that
// closes the sheet is the refusal every card's form shares, and it is in US English.
$css_refusal = (string) strstr( $css_raw, 'A refused form, on every card of the page.' );
ck( 'and the card has no section of its own left in the stylesheet; the shared refusal section that closes it is in US English', array( false !== strpos( $css_raw, 'The school\'s details card (Institutions release 2)' ), '' !== $css_refusal, preg_match( '/colour/i', $css_refusal ) ), array( false, true, 0 ) );

/* ---- Stage B, Task 19: the card on the shared parts ------------------------------------------------------------------ */

echo "\n=== Stage B (Task 19): the card on the shared parts ===\n";


const T19_COMBO_BASE = array( '8c7fa34f32e122754a7ecb5f3f1fd409', 'fd51bddfc3390044873877e55f504143', '59b57d1e0365329cf6738b1c07e7ad9f' );
const T19_LIVE = array( 'administrator', 'institution' );
const T19_BAD  = '<script>alert(1)</script> "q" & Co';

/** The four tracks the site runs, as the Tracks section above names them. */
function t19_tracks() {
	$GLOBALS['labels'] = array(
		'In Sensei'       => 'WordPress Credits Program 150h',
		'In Sensei 50h'   => 'WordPress Credits Program 50h',
		'Designer Track'  => 'Designer Track',
		'Developer Track' => 'Developer Track',
	);
	$GLOBALS['levels'] = array();
}
/** A fresh world with the tracks and two of them offered, the assignment to Bo Boss. */
function t19_fresh() {
	fresh();
	t19_tracks();
	$GLOBALS['records'][ A ]['tracks_offered'] = array( 'In Sensei', 'Developer Track' );
	$GLOBALS['records'][ A ]['assigned']       = 12;
}
/** The long fixture: a 41-character name, a 48-character email, long addresses, six languages and four tracks. */
function t19_long() {
	$GLOBALS['index'][ A ]['name']                 = 'Exampleinstituteforappliedwebengineerings';
	$GLOBALS['index'][ A ]['contact_email']        = 'averyveryveryverylongmailboxname1234@example.org';
	$GLOBALS['index'][ A ]['contact_person']       = 'Exampleunbrokenpersonnamewithfortychars1';
	$GLOBALS['index'][ A ]['website']              = 'https://exampleinstituteforappliedwebengineerings.example.org';
	$GLOBALS['index'][ A ]['city']                 = 'Examplecitywithaveryveryverylongnamesofar';
	$GLOBALS['records'][ A ]['credits_page']       = 'https://exampleinstituteforappliedwebengineerings.example.org/wordpress-credits-programme';
	$GLOBALS['records'][ A ]['student_languages']  = array( 'English', 'Spanish', 'Polish', 'Portuguese', 'Mandarin Chinese', 'Tagalog (Filipino)' );
	$GLOBALS['records'][ A ]['tracks_offered']     = array_keys( $GLOBALS['labels'] );
	$GLOBALS['users'][12]->display_name            = 'Exampleadministratornamewithfortycharsxyz';
}
/** A request raised by member 7 for the second group, its flash taken. */
function t19_request( array $edits ) {
	post_as( 7, 'checked', $edits );
	draw( 7 );
	return (int) array_key_first( requests() );
}
/** A request settled, closed on a fixed day. */
function t19_settle( $state, $note = '' ) {
	$id = t19_request( array( 'wpcpm_details_name' => 'Universidad Ejemplo de Valencia' ) );
	WPCPM_Institution_Request::settle( $id, $state, $note, 1, 'manager' );
	$GLOBALS['pmeta'][ $id ]['_wpcpm_req_closed_at'] = array( 1767225600 );
	return $id;
}
/** A flash for one account, as a handler sets it. */
function t19_flash( $uid, array $flash ) {
	WPCPM_Flash::set( WPCPM_Institution_Request::FLASH_DETAILS, array_merge( array( 'record' => A ), $flash ), $uid );
}

/** Every state the card draws, each a scene built afresh: the reader and the school. */
$t19_states = array(
	'representative, filled'                     => static function () { t19_fresh(); return array( 7, A ); },
	'representative, nothing recorded'           => static function () { t19_fresh(); return array( 9, B ); },
	'representative, long words, six languages, four tracks' => static function () { t19_fresh(); t19_long(); return array( 7, A ); },
	'representative, the first group refused, typing kept' => static function () {
		t19_fresh();
		post_as( 7, 'now', array( 'wpcpm_details_contact_person' => '', 'wpcpm_details_contact_role' => 'Kept role, as typed', 'wpcpm_details_student_languages' => array() ) );
		return array( 7, A );
	},
	'representative, the second group refused, typing kept' => static function () {
		t19_fresh();
		post_as( 7, 'checked', array( 'wpcpm_details_name' => 'Valencia University', 'wpcpm_details_city' => '', 'wpcpm_details_contact_email' => 'not-an-email', 'wpcpm_details_country' => 'recXX000000000009' ) );
		return array( 7, A );
	},
	'representative, a request waiting'          => static function () { t19_fresh(); t19_request( array( 'wpcpm_details_name' => 'Universidad Ejemplo de Valencia', 'wpcpm_details_city' => 'Valencia' ) ); return array( 8, A ); },
	'representative, long words, a request waiting' => static function () { t19_fresh(); t19_long(); t19_request( array( 'wpcpm_details_name' => 'Exampleinstituteforappliedwebengineeringsandmore', 'wpcpm_details_contact_email' => 'averyveryveryverylongmailboxname5678@example.org' ) ); return array( 8, A ); },
	'Administrator, a request waiting'           => static function () { t19_fresh(); t19_request( array( 'wpcpm_details_name' => 'Universidad Ejemplo de Valencia', 'wpcpm_details_city' => 'Valencia' ) ); return array( 1, A ); },
	'Administrator, a request waiting, no Administrator Dashboard' => static function () {
		t19_fresh();
		t19_request( array( 'wpcpm_details_city' => 'Valencia' ) );
		WPCPM_Administrators_Dashboard::$url = '';
		return array( 1, A );
	},
	'representative, an approved request'        => static function () { t19_fresh(); t19_settle( 'done' ); return array( 8, A ); },
	'representative, a declined request with a note' => static function () { t19_fresh(); t19_settle( 'declined', 'Please send the letter from the registrar & the dean.' ); return array( 8, A ); },
	'representative, a declined request'         => static function () { t19_fresh(); t19_settle( 'declined' ); return array( 8, A ); },
	'Administrator, filled'                      => static function () { t19_fresh(); return array( 1, A ); },
	'Administrator, long words, four tracks'     => static function () { t19_fresh(); t19_long(); return array( 1, A ); },
	'Administrator, nothing recorded, nobody assigned, no track' => static function () { t19_fresh(); return array( 1, B ); },
	'Administrator, an account no longer an Administrator' => static function () { t19_fresh(); $GLOBALS['records'][ A ]['assigned'] = 13; $GLOBALS['manage'] = array( 1, 12 ); return array( 1, A ); },
	'Administrator, the second group refused'    => static function () { t19_fresh(); post_as( 1, 'checked', array( 'wpcpm_details_website' => 'ftp://example.org', 'wpcpm_details_name' => 'Kept name' ) ); return array( 1, A ); },
	'Administrator, a school whose agreement is not settled' => static function () { t19_fresh(); return array( 1, C ); },
	'Administrator, the tracks saved'            => static function () { t19_fresh(); t19_flash( 1, array( 'status' => 'tracks-saved', 'section' => 'tracks' ) ); return array( 1, A ); },
	'Administrator, the tracks refused'          => static function () { t19_fresh(); t19_flash( 1, array( 'status' => 'said', 'text' => 'The tracks could not be kept on the site, so nothing was saved. Try again in a while.', 'section' => 'tracks' ) ); return array( 1, A ); },
	'Administrator, the assignment saved'        => static function () { t19_fresh(); t19_flash( 1, array( 'status' => 'assignment-emailed', 'section' => 'assignment' ) ); return array( 1, A ); },
	'Administrator, the assignment refused'      => static function () { t19_fresh(); t19_flash( 1, array( 'status' => 'saving-busy', 'section' => 'assignment' ) ); return array( 1, A ); },
	'representative, a sentence said'            => static function () { t19_fresh(); t19_flash( 7, array( 'status' => 'said', 'text' => 'One change is already waiting for an Administrator.' ) ); return array( 7, A ); },
	'tutor'                                      => static function () {
		t19_fresh();
		$GLOBALS['users'][14]       = new WP_User( 14, 'Tess Tutor', 'tess@example.test', array( 'subscriber' ) );
		$GLOBALS['memberships'][14] = array( A );
		$GLOBALS['levels']          = array( 14 => 'tutor' );
		return array( 14, A );
	},
	'representative of a school whose agreement is not settled' => static function () { t19_fresh(); return array( 10, C ); },
	'member of another school'                   => static function () { t19_fresh(); return array( 9, A ); },
	'hostile, an Administrator'                  => static function () { t19_fresh(); t19_hostile(); return array( 1, A ); },
	'hostile, a representative'                  => static function () { t19_fresh(); t19_hostile(); return array( 7, A ); },
	'hostile, a declined request'                => static function () { t19_fresh(); t19_settle( 'declined', 'Note ' . T19_BAD ); return array( 8, A ); },
	'hostile, a request waiting'                 => static function () { t19_fresh(); t19_request( array( 'wpcpm_details_city' => 'Ort "q" & Co' ) ); return array( 8, A ); },
	'hostile, a request waiting with a tag'      => static function () {
		t19_fresh();
		WPCPM_Institution_Request::raise_details( A, WPCPM_Institution_Details::changes( A, array( 'city' => 'Ort ' . T19_BAD ) ), 7, 'member' );
		return array( 8, A );
	},
);
// Each message the card says, as a handler flashes it.
foreach ( array_merge( WPCPM_Institution_Details_Card::messages(), WPCPM_Institution_Request::school_messages() ) as $t19_status => $t19_message ) {
	$t19_section = 0 === strpos( $t19_status, 'tracks' ) ? 'tracks' : ( 0 === strpos( $t19_status, 'assignment' ) ? 'assignment' : '' );
	$t19_states[ 'message: ' . $t19_status ] = static function () use ( $t19_status, $t19_section ) {
		t19_fresh();
		t19_flash( '' !== $t19_section ? 1 : 7, array_filter( array( 'status' => $t19_status, 'section' => $t19_section ) ) );
		return array( '' !== $t19_section ? 1 : 7, A );
	};
}
/** Every value a person can type that the card prints, hostile. */
function t19_hostile() {
	foreach ( array( 'name' => 'Schule ', 'city' => 'Ort ', 'contact_person' => 'Kontakt ' ) as $key => $word ) {
		$GLOBALS['index'][ A ][ $key ] = $word . T19_BAD;
	}
	$GLOBALS['index'][ A ]['website']       = 'https://example.org/?a="b"&c=<script>';
	$GLOBALS['index'][ A ]['contact_email'] = 'x"&<y>@example.org';
	foreach ( array( 'contact_role' => 'Rolle ', 'signer_name' => 'Signer ', 'signer_title' => 'Titel ' ) as $key => $word ) {
		$GLOBALS['records'][ A ][ $key ] = $word . T19_BAD;
	}
	$GLOBALS['records'][ A ]['credits_page'] = 'https://example.org/credits?x="y"&z=<b>';
	$GLOBALS['users'][12]->display_name      = 'Admin ' . T19_BAD;
	$GLOBALS['labels']['Hostile Track']      = 'Track ' . T19_BAD;
	$GLOBALS['records'][ A ]['tracks_offered'][] = 'Hostile Track';
}
/** The values the hostile states print, as a reader reads them. */
$t19_hostile_values = array(
	'Schule ' . T19_BAD, 'Ort ' . T19_BAD, 'Kontakt ' . T19_BAD, 'Rolle ' . T19_BAD, 'Signer ' . T19_BAD, 'Titel ' . T19_BAD,
	'https://example.org/?a="b"&c=<script>', 'x"&<y>@example.org', 'https://example.org/credits?x="y"&z=<b>', 'Track ' . T19_BAD,
);

/** One state drawn: the old branch (no surface, or a named one with a live list), or the parts' (the Institution Dashboard on). */
function t19_play( callable $state, $parts, $surface = null, $live = null ) {
	list( $uid, $record ) = $state();
	WPCPM_UI::set_live( $parts ? T19_LIVE : $live );
	$named = $parts || null !== $surface;
	if ( $named ) {
		WPCPM_UI::enter( $parts ? 'institution' : (string) $surface );
	}
	$html = draw( $uid, $record );
	if ( $named ) {
		WPCPM_UI::leave();
	}
	WPCPM_UI::set_live( null );
	WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';
	return $html;
}
/** The request's ID and every day, masked, so a digest holds whatever the run's post IDs and clock. */
function t19_mask( $html ) {
	$html = (string) preg_replace( '/(withdraw_details_|name="request" value=")\d+/', '$1{id}', (string) $html );
	return (string) preg_replace( '/\b\d{4}-\d{2}-\d{2}\b/', '{day}', $html );
}
function t19_dom( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="t19-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	return $doc;
}
/** Every form as the browser posts it: its own attributes, the question it asks, and each name=value it sends, in order. */
function t19_forms( $html ) {
	$doc  = t19_dom( $html );
	$path = new DOMXPath( $doc );
	$out  = array();
	foreach ( $doc->getElementsByTagName( 'form' ) as $form ) {
		$one = array(
			'method'     => $form->getAttribute( 'method' ),
			'action'     => $form->getAttribute( 'action' ),
			'enctype'    => $form->getAttribute( 'enctype' ),
			'novalidate' => $form->hasAttribute( 'novalidate' ),
			'once'       => $form->hasAttribute( 'data-wpcpm-once' ),
			'busy'       => $form->getAttribute( 'data-wpcpm-busy' ),
			'confirm'    => $form->getAttribute( 'data-wpcpm-confirm' ),
			'fields'     => array(),
			'submits'    => array(),
		);
		foreach ( $path->query( './/input|.//select|.//textarea|.//button', $form ) as $control ) {
			$name = $control->getAttribute( 'name' );
			$tag  = $control->nodeName;
			$type = strtolower( $control->getAttribute( 'type' ) );
			if ( 'button' === $tag || 'submit' === $type ) {
				if ( 'button' !== $type ) {
					$one['submits'][] = array( trim( $control->firstChild ? $control->firstChild->textContent : '' ), $name, $control->getAttribute( 'value' ), $control->getAttribute( 'data-wpcpm-confirm' ) );
				}
				continue;
			}
			if ( '' === $name ) {
				continue;
			}
			if ( 'select' === $tag ) {
				$value = null;
				foreach ( $control->getElementsByTagName( 'option' ) as $option ) {
					if ( null === $value || $option->hasAttribute( 'selected' ) ) {
						$value = $option->hasAttribute( 'selected' ) ? '!' . $option->getAttribute( 'value' ) : $option->getAttribute( 'value' );
					}
					if ( $option->hasAttribute( 'selected' ) ) {
						break;
					}
				}
				$one['fields'][] = $name . '=' . ltrim( (string) $value, '!' );
				continue;
			}
			if ( 'textarea' === $tag ) {
				$one['fields'][] = $name . '=' . preg_replace( '/^\n/', '', $control->textContent );
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
/** The value a box of the parts is drawn with, by its name; null when there is none. */
function t19_box( $html, $name ) {
	foreach ( t19_dom( $html )->getElementsByTagName( 'input' ) as $input ) {
		if ( $name === $input->getAttribute( 'name' ) ) {
			return $input->getAttribute( 'value' );
		}
	}
	return null;
}
/** The words a page says (tags gone, entities read), as a sorted set. */
function t19_words( $html ) {
	preg_match_all( '/[\p{L}\p{N}]+/u', plain( str_replace( '<wbr>', '', (string) $html ) ), $m );
	$words = array_values( array_unique( $m[0] ) );
	sort( $words );
	return $words;
}
/** The words of one stretch of a page, between two needles. */
function t19_between( $html, $from, $to = '' ) {
	$at = strpos( (string) $html, $from );
	if ( false === $at ) {
		return '';
	}
	$end = '' !== $to ? strpos( (string) $html, $to, $at + strlen( $from ) ) : false;
	return false === $end ? substr( (string) $html, $at ) : substr( (string) $html, $at, $end - $at );
}
/** One inner fold of the parts, by id, from its tag to its close; '' when it is not drawn. */
function t19_fold( $html, $id ) {
	return 1 === preg_match( '#<details class="wpcpm-ui-fold wpcpm-ui-fold--inner"( open)? id="' . preg_quote( $id, '#' ) . '">.*?</form></div></details>#s', (string) $html, $m ) ? $m[0] : '';
}
/** The rows of the parts' key and value tables, label to value text, in order. */
function t19_rows( $html ) {
	preg_match_all( '#<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text">(.*?)</th><td class="([^"]*)" role="cell">(.*?)</td></tr>#s', (string) $html, $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $row ) {
		$value = (string) preg_replace( '#<span class="wpcpm-ui-cell__sub">.*$#s', '', $row[3] );
		$out[ html_entity_decode( $row[1], ENT_QUOTES ) ] = array( plain( str_replace( '<wbr>', '', $value ) ), $row[2] );
	}
	return $out;
}

// Draw every state.
$t19_new = array();
foreach ( $t19_states as $t19_name => $t19_state ) {
	$t19_new[ $t19_name ] = t19_play( $t19_state, true );
}

if ( getenv( 'T19_DUMP' ) ) { file_put_contents( getenv( 'T19_DUMP' ), json_encode( array( 'new' => $t19_new ) ) ); }

// The whole view on the real card (Task 23, fix round 1): every state of every reader under the Your institution
// view's own head (`bin/stubs/view-faults.php`), each fold open as the reader can open it.
$t19_open = array_map( static function ( $html ) { return str_replace( '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id=', '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id=', (string) $html ); }, $t19_new );
ck( 'the whole Your institution view on the real details card, every state: one H1, the headings in order, no id twice, the lede once, no title again', array( wpcpm_view_faults_of( 'Your institution', $t19_open ), count( array_filter( array_map( 'trim', $t19_open ) ) ) >= 40 ), array( array(), true ) );

// The view holds three cards at once, the details card first, then the people card with its Tutors section: an id
// repeated between them is the fault to catch. The people card's real drawings come from its own suite, run as it
// always runs with its dump on (`T20_DUMP`, every one of its states); each is drawn after each details state.
$t19_people_file = tempnam( sys_get_temp_dir(), 'wpcpm-t23-people-' );
putenv( 'T20_DUMP=' . $t19_people_file );
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/test-institution-people.php' ) . ' > /dev/null 2>&1', $t19_ignored, $t19_people_rc );
putenv( 'T20_DUMP' );
$t19_people = json_decode( (string) file_get_contents( $t19_people_file ), true );
unlink( $t19_people_file );
$t19_people = is_array( $t19_people ) && isset( $t19_people['new'] ) ? array_filter( array_map( 'strval', $t19_people['new'] ), 'strlen' ) : array();
$t19_three  = array();
foreach ( $t19_open as $t19_name => $t19_html ) {
	foreach ( $t19_people as $t19_pname => $t19_phtml ) {
		$t19_faults = wpcpm_view_faults( wpcpm_view_page( 'Your institution', $t19_html . str_replace( '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id=', '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id=', $t19_phtml ) ) );
		if ( $t19_faults && count( $t19_three ) < 5 ) {
			$t19_three[ $t19_name . ' + ' . $t19_pname ] = $t19_faults;
		}
	}
}
ck( 'and the view\'s three cards together, every details state with every people and tutors state: no id twice, the headings in order, one H1', array( $t19_three, $t19_people_rc, count( $t19_people ) >= 50 ), array( array(), 0, true ) );

// What the old card posted and said in every state, read from the class at db850661 (the last commit that drew it)
// before it was deleted (Task 23): digests of `t19_forms()` and of `t19_words()` (the old lead left out, as the view's
// lede says it), each of the drawing with the request ID and the days masked. The parts are held to them.
$t19_old_pins = array(
	'forms' => array(
		'representative, filled' => '06c1491e857dc09ac600cde5a3795c4a',
		'representative, nothing recorded' => 'da86ac67f33cd9bd310452eb0cce7965',
		'representative, long words, six languages, four tracks' => '3c7bb56a9554c49ca833ffeb78dbe3b0',
		'representative, the first group refused, typing kept' => 'ea3e9ae3a787b9827bbce25f7ef163e7',
		'representative, the second group refused, typing kept' => '711ce9becd8bb55c3227b6301bd4ca7b',
		'representative, a request waiting' => '2e5338a7098f933351148db9d2e31090',
		'representative, long words, a request waiting' => '9497039477453ecb4a6ff4ef08c827b9',
		'Administrator, a request waiting' => '5f7863510d8d621cb61c004d821975f5',
		'Administrator, a request waiting, no Administrator Dashboard' => '5f7863510d8d621cb61c004d821975f5',
		'representative, an approved request' => '06c1491e857dc09ac600cde5a3795c4a',
		'representative, a declined request with a note' => '06c1491e857dc09ac600cde5a3795c4a',
		'representative, a declined request' => '06c1491e857dc09ac600cde5a3795c4a',
		'Administrator, filled' => 'dea811c778645465528c7c61accc40da',
		'Administrator, long words, four tracks' => 'd0bec213fecee451f961b6bd3470e197',
		'Administrator, nothing recorded, nobody assigned, no track' => '038e2db0ad2d356ec7f15e3c7fb79ad5',
		'Administrator, an account no longer an Administrator' => '67639193cd7774f611011da306adfda3',
		'Administrator, the second group refused' => '6dc8ea2850ca8491ba56f9befcb132b1',
		'Administrator, a school whose agreement is not settled' => '245e9bdc540791e1c759b960c77f1e0d',
		'Administrator, the tracks saved' => 'dea811c778645465528c7c61accc40da',
		'Administrator, the tracks refused' => 'dea811c778645465528c7c61accc40da',
		'Administrator, the assignment saved' => 'dea811c778645465528c7c61accc40da',
		'Administrator, the assignment refused' => 'dea811c778645465528c7c61accc40da',
		'representative, a sentence said' => '06c1491e857dc09ac600cde5a3795c4a',
		'tutor' => '40cd750bba9870f18aada2478b24840a',
		'representative of a school whose agreement is not settled' => '40cd750bba9870f18aada2478b24840a',
		'member of another school' => '40cd750bba9870f18aada2478b24840a',
		'hostile, an Administrator' => '17fa58ca17a1a87e5f64a6fa1cc0a607',
		'hostile, a representative' => 'a6b0284f81a1ad64f761c3f6a8300334',
		'hostile, a declined request' => '06c1491e857dc09ac600cde5a3795c4a',
		'hostile, a request waiting' => '09f990e6e4eac46a9cd638d7eae3e03f',
		'hostile, a request waiting with a tag' => '09f990e6e4eac46a9cd638d7eae3e03f',
		'message: saved' => '06c1491e857dc09ac600cde5a3795c4a',
		'message: asked' => '06c1491e857dc09ac600cde5a3795c4a',
		'message: nothing' => '06c1491e857dc09ac600cde5a3795c4a',
		'message: airtable' => '06c1491e857dc09ac600cde5a3795c4a',
		'message: refused-now' => '06c1491e857dc09ac600cde5a3795c4a',
		'message: refused-checked' => '06c1491e857dc09ac600cde5a3795c4a',
		'message: tracks-saved' => 'dea811c778645465528c7c61accc40da',
		'message: assignment-emailed' => 'dea811c778645465528c7c61accc40da',
		'message: assignment-saved' => 'dea811c778645465528c7c61accc40da',
		'message: saving-busy' => '06c1491e857dc09ac600cde5a3795c4a',
		'message: withdrawn' => '06c1491e857dc09ac600cde5a3795c4a',
		'message: withdrawn-already' => '06c1491e857dc09ac600cde5a3795c4a',
		'message: closed' => '06c1491e857dc09ac600cde5a3795c4a',
		'message: busy' => '06c1491e857dc09ac600cde5a3795c4a',
	),
	'words' => array(
		'representative, filled' => 'c419c9d350a83cd4bbe06c978d7788ff',
		'representative, nothing recorded' => '40f9460410fc70220140e0b636280328',
		'representative, long words, six languages, four tracks' => '811143ed93643735649c5aabfecc912b',
		'representative, the first group refused, typing kept' => 'e974db702a579f6ed28e9f030b3ba61b',
		'representative, the second group refused, typing kept' => '58c4ffe920fb3fc632171f2b47e89012',
		'representative, a request waiting' => '47ca4e0e4f8ec2fbcc4319313077db80',
		'representative, long words, a request waiting' => '482d32c81f74d28cdf48b49936b3d04a',
		'Administrator, a request waiting' => 'c09b8e7224c167e653b8bfc39dd5e444',
		'Administrator, a request waiting, no Administrator Dashboard' => '076075ac3213cdd6fee3e441268176c8',
		'representative, an approved request' => 'e6a0c6e63ffa54a528a8dfc4691df793',
		'representative, a declined request with a note' => 'e7e4d398065d6bd4e0dbbb6e721756ba',
		'representative, a declined request' => '7f42618bc1986de3530ddc68bac8baf4',
		'Administrator, filled' => 'fbc49e6fb611d283f453ab6026cb1c34',
		'Administrator, long words, four tracks' => '55b86ad06969b3f778431e9c124ecb20',
		'Administrator, nothing recorded, nobody assigned, no track' => 'e6ffde8422c3f89cd100d2bff61ab263',
		'Administrator, an account no longer an Administrator' => 'a352740f82b51990d62a3a451ee46c1f',
		'Administrator, the second group refused' => 'cfec0e1f7e4b1b2ee30ee28b570b1813',
		'Administrator, a school whose agreement is not settled' => '72733dda5528fb6e970e35aabbe3b023',
		'Administrator, the tracks saved' => 'fbc49e6fb611d283f453ab6026cb1c34',
		'Administrator, the tracks refused' => '7ecf6e2ae9a83b1999bb2b571f065e96',
		'Administrator, the assignment saved' => '758aacc4fb96a2d70d8bdac1893f4241',
		'Administrator, the assignment refused' => '7c06bd9709e497586f55b0b770313052',
		'representative, a sentence said' => '5d256f0073998722ecb470512d9c10da',
		'tutor' => '40cd750bba9870f18aada2478b24840a',
		'representative of a school whose agreement is not settled' => '40cd750bba9870f18aada2478b24840a',
		'member of another school' => '40cd750bba9870f18aada2478b24840a',
		'hostile, an Administrator' => '6b764c2fde51e421ab058c9dbea450c2',
		'hostile, a representative' => '798abd325b3fe975bac0fb97276bbe70',
		'hostile, a declined request' => 'ab4e2f4a5e28f99e218ce788a32880f8',
		'hostile, a request waiting' => '75825f9e07b56440d89ac96f40c16063',
		'hostile, a request waiting with a tag' => 'ecc325127143833c6feb116314a4facf',
		'message: saved' => 'a892563930d9f3cc940982bc07cba1fc',
		'message: asked' => 'cc708b7a08cef13d25064d8d7a54aca7',
		'message: nothing' => '9d27f5567ef0067198a33810616db76e',
		'message: airtable' => '889e777b23ebc2f5fe1dfdd97d73528e',
		'message: refused-now' => '1b009692be91be01b00bc37f15175cf6',
		'message: refused-checked' => 'f10ba48877dff76278210d6f0778b327',
		'message: tracks-saved' => 'fbc49e6fb611d283f453ab6026cb1c34',
		'message: assignment-emailed' => '758aacc4fb96a2d70d8bdac1893f4241',
		'message: assignment-saved' => '4f304c818155adfd09656c9afc9daf9f',
		'message: saving-busy' => '31c4b560f4cb43b1395a9de788af541b',
		'message: withdrawn' => 'c7572a058a29efdc4c88e763d4d56078',
		'message: withdrawn-already' => 'c41b311aa5fb5e1e75f7a12593a13f6e',
		'message: closed' => 'e0fe63bdc2c31288b04e69d5e154b11a',
		'message: busy' => '99d3ac219469114eb588f1dd173641c8',
	),
);

// ---- 2. Who is drawn the card: the same readers on both branches (standing rule 3) ----

ck( 'a tutor, a locked school\'s representative and another school\'s member are drawn nothing, as the old card drew them nothing', array(
	$t19_new['tutor'],
	$t19_new['representative of a school whose agreement is not settled'],
	$t19_new['member of another school'],
), array( '', '', '' ) );
$t19_card_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-details-card.php' );
$t19_render   = (string) method_body( $t19_card_src, 'render' );
ck( 'the policy is asked once, before the card is drawn, and the data is read before it too', array( substr_count( $t19_render, 'WPCPM_Institution_Policy::decide(' ), before( $t19_render, 'WPCPM_Institution_Policy::decide(', 'self::render_parts(' ), before( $t19_render, 'self::take_flash(', 'self::render_parts(' ), before( $t19_render, 'WPCPM_Institution_Request::open_details_for(', 'self::render_parts(' ) ), array( 1, true, true, true ) );

// Fix round 1: which outcome goes to Tracks offered or to Assigned Administrator, and who reads the decide line, are one
// helper each.
ck( 'the sections\' outcomes and the decide line are each one rule', array(
	substr_count( $t19_card_src, 'self::section_message( $flash, \'tracks\' )' ),
	substr_count( $t19_card_src, 'self::section_message( $flash, \'assignment\' )' ),
	substr_count( $t19_card_src, 'self::shows_decide( $group, $open, $checker )' ),
	// The one comparison is section_message()'s own.
	substr_count( $t19_card_src, '=== $flash[\'section\']' ),
	1 === preg_match( '/if \( \$checker \) \{\s*(self::render_decide|echo WPCPM_UI::intro)/', $t19_card_src ),
	has( (string) method_body( $t19_card_src, 'render_parts' ), 'self::shows_decide(' ),
), array( 1, 1, 1, 1, false, true ) );

// ---- 3. The card's frame on the parts ----

$t19_filled = $t19_new['representative, filled'];
// Fix round 1: the view's lede already says what the program has on record, so the card's head is its name alone.
ck( 'the card is the shell\'s card fold, open, its anchor on the section, its head the card\'s name alone (no phrase: the view\'s lede says it)', array( 0 === strpos( $t19_filled, '<section class="wpcpm-ui-fold-card" id="wpcpm-details"><details class="wpcpm-ui-fold" open><summary class="wpcpm-ui-fold__head"><h3 class="wpcpm-ui-fold__title">Your institution&#039;s details</h3><span class="wpcpm-ui-fold__end">' ), has( implode( '', $t19_new ), 'What the program has on record' ) ), array( true, false ) );
ck( 'and ends as the shell ends it', substr( $t19_filled, -strlen( '</div></details></section>' ) ), '</div></details></section>' );
ck( 'no class of today\'s card on the parts, but the message\'s own (wpcpm-details__message, with its tone)', array_values( array_unique( preg_match_all( '/wpcpm-(details__(?!message\b)[a-z-]+|institution__card|group__[a-z]+|field--[a-z]+|warning|button)\b/', implode( '', $t19_new ), $t19_m ) ? $t19_m[0] : array() ) ), array() );
ck( 'one name per page: the card\'s name is not the view\'s title, and nothing on the card repeats it', array( has( $t19_filled, '>Your institution<' ), substr_count( plain( $t19_filled ), 'Your institution\'s details' ) ), array( false, 1 ) );
preg_match_all( '#<h4 class="wpcpm-ui-h"[^>]*>(.*?)</h4>#', $t19_filled, $t19_h );
ck( 'a representative\'s in-card headings: the two groups, then Tracks offered', $t19_h[1], array( 'Contact and agreement', 'Name and address', 'Tracks offered' ) );
preg_match_all( '#<h4 class="wpcpm-ui-h"[^>]*>(.*?)</h4>#', $t19_new['Administrator, filled'], $t19_h );
ck( 'an Administrator\'s, then Assigned Administrator', $t19_h[1], array( 'Contact and agreement', 'Name and address', 'Tracks offered', 'Assigned Administrator' ) );

// ---- 4. The two tables ----

ck( 'two key and value tables for a representative, each named by its heading; a third, the assignment, for an Administrator', array(
	substr_count( $t19_filled, '<table class="wpcpm-ui-table wpcpm-ui-table--keyvalue" role="table"' ),
	has( $t19_filled, '<h4 class="wpcpm-ui-h" id="wpcpm-details-now-heading">Contact and agreement</h4><table class="wpcpm-ui-table wpcpm-ui-table--keyvalue" role="table" aria-labelledby="wpcpm-details-now-heading">' ),
	has( $t19_filled, '<h4 class="wpcpm-ui-h" id="wpcpm-details-checked-heading">Name and address</h4><table class="wpcpm-ui-table wpcpm-ui-table--keyvalue" role="table" aria-labelledby="wpcpm-details-checked-heading">' ),
	substr_count( $t19_new['Administrator, filled'], '<table class="wpcpm-ui-table wpcpm-ui-table--keyvalue"' ),
	has( $t19_filled, 'wpcpm-ui-table--wide' ),
), array( 2, true, true, 3, false ) );
$t19_rows = t19_rows( t19_between( $t19_filled, 'Contact and agreement', 'Tracks offered' ) );
ck( 'six rows and five, with the details table\'s labels, in its order, holding what the old rows held', array_map( static function ( $row ) { return $row[0]; }, $t19_rows ), array(
	'Contact person'               => 'Contact One',
	'Their role'                   => 'Head of the international office',
	'Signs the agreement'          => 'Signer One',
	'Their title'                  => 'Dean of Engineering',
	'Students study in'            => 'Spanish, English',
	'Page about WordPress Credits' => 'https://ejemplo.edu/credits',
	'Name'                         => 'Universidad Ejemplo',
	'Website'                      => 'https://ejemplo.edu',
	'City'                         => 'San Jose',
	'Country'                      => 'Costa Rica',
	'Contact\'s email'             => 'contact@example.test',
) );
ck( 'the page address, the website and the contact\'s email are token cells; every other value ordinary text', array_map( static function ( $row ) { return $row[1]; }, $t19_rows ), array(
	'Contact person'               => 'wpcpm-ui-cell--text',
	'Their role'                   => 'wpcpm-ui-cell--text',
	'Signs the agreement'          => 'wpcpm-ui-cell--text',
	'Their title'                  => 'wpcpm-ui-cell--text',
	'Students study in'            => 'wpcpm-ui-cell--text',
	'Page about WordPress Credits' => 'wpcpm-ui-cell--token',
	'Name'                         => 'wpcpm-ui-cell--text',
	'Website'                      => 'wpcpm-ui-cell--token',
	'City'                         => 'wpcpm-ui-cell--text',
	'Country'                      => 'wpcpm-ui-cell--text',
	'Contact\'s email'             => 'wpcpm-ui-cell--token',
) );
$t19_empty = t19_rows( t19_between( $t19_new['representative, nothing recorded'], 'Contact and agreement', 'Tracks offered' ) );
ck( 'an empty detail reads "Not given yet", in words, of the empty kind', array( array_values( array_unique( array_map( static function ( $row ) { return $row[0] . ' / ' . $row[1]; }, array_diff_key( $t19_empty, array( 'Name' => 1 ) ) ) ) ), $t19_empty['Name'] ), array( array( 'Not given yet / wpcpm-ui-cell--text wpcpm-ui-cell--empty' ), array( 'Another School', 'wpcpm-ui-cell--text' ) ) );
$t19_long_html = $t19_new['representative, long words, six languages, four tracks'];
ck( 'a 40-character name and a long contact name are plain words the key and value table breaks where they would overflow, no `<wbr>` (Task 22b); the languages read as the list they are', array(
	has( $t19_long_html, '<td class="wpcpm-ui-cell--text" role="cell">Exampleinstituteforappliedwebengineerings' ),
	has( $t19_long_html, '<td class="wpcpm-ui-cell--text" role="cell">Exampleunbrokenpersonnamewithfortychars1</td>' ),
	substr_count( implode( '', $t19_new ), '<wbr>' ),
	t19_rows( $t19_long_html )['Students study in'][0],
), array( true, true, 0, 'English, Spanish, Polish, Portuguese, Mandarin Chinese, Tagalog (Filipino)' ) );

// ---- 5. The two folds and their forms (standing rule 2) ----

$t19_now     = t19_fold( $t19_filled, 'wpcpm-details-now' );
$t19_checked = t19_fold( $t19_filled, 'wpcpm-details-checked' );
ck( 'each table is followed by its inner fold, closed: "Correct these" with "(saved at once)", "Ask to correct these" with "(an Administrator checks first)"', array(
	'' !== $t19_now, '' !== $t19_checked,
	has( $t19_now, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id="wpcpm-details-now"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Correct these</span><span class="wpcpm-ui-fold__second">(saved at once)</span></span>' ),
	has( $t19_checked, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id="wpcpm-details-checked"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Ask to correct these</span><span class="wpcpm-ui-fold__second">(an Administrator checks first)</span></span>' ),
	strpos( $t19_filled, 'wpcpm-details-now-heading' ) < strpos( $t19_filled, 'id="wpcpm-details-now"' ) && strpos( $t19_filled, 'id="wpcpm-details-now"' ) < strpos( $t19_filled, 'wpcpm-details-checked-heading' ),
	strpos( $t19_filled, 'wpcpm-details-checked-heading' ) < strpos( $t19_filled, 'id="wpcpm-details-checked"' ),
), array( true, true, true, true, true, true ) );
ck( 'an Administrator\'s second fold reads as the save it is, under the note that says so', array(
	has( $t19_new['Administrator, filled'], '<span class="wpcpm-ui-fold__title">Correct these</span><span class="wpcpm-ui-fold__second">(saved at once)</span></span><span class="wpcpm-ui-fold__chev" aria-hidden="true">' ),
	substr_count( $t19_new['Administrator, filled'], '(saved at once)' ),
	has( t19_fold( $t19_new['Administrator, filled'], 'wpcpm-details-checked' ), '<p class="wpcpm-ui-intro">You are an Administrator, so these are saved at once and nobody approves them.</p>' ),
	has( $t19_new['Administrator, filled'], 'Ask to correct these' ),
), array( true, 2, true, false ) );
ck( 'one primary button to each form: Save the details, Send to an Administrator', array(
	has( $t19_now, '<div class="wpcpm-ui-actions"><button type="submit" class="wpcpm-btn">Save the details</button></div></form>' ),
	has( $t19_checked, '<div class="wpcpm-ui-actions"><button type="submit" class="wpcpm-btn">Send to an Administrator</button></div></form>' ),
	substr_count( $t19_now, '<button' ), substr_count( $t19_checked, '<button' ),
), array( true, true, 1, 1 ) );
$t19_post_diff = array();
foreach ( $t19_states as $t19_name => $t19_state ) {
	// Each drawing plays its scene afresh, so a waiting request is a new post each time: its ID is masked.
	if ( md5( serialize( t19_forms( t19_mask( $t19_new[ $t19_name ] ) ) ) ) !== $t19_old_pins['forms'][ $t19_name ] ) {
		$t19_post_diff[] = $t19_name;
	}
}
ck( 'every form posts what it posts today, in every state: method, address, guard, busy words, question, every name and value, the nonce and the referer, the button', $t19_post_diff, array() );
$t19_kinds = array();
foreach ( t19_forms( $t19_new['representative, a request waiting'] . $t19_new['Administrator, filled'] ) as $t19_form ) {
	$t19_kinds[] = preg_replace( '/^action=/', '', (string) current( array_filter( $t19_form['fields'], static function ( $f ) { return 0 === strpos( $f, 'action=' ); } ) ) );
}
ck( 'the five forms the card draws: save, withdraw (one to each waiting row), ask, tracks, assignment', array_values( array_unique( $t19_kinds ) ), array( 'wpcpm_save_details', 'wpcpm_withdraw_details', 'wpcpm_ask_details', 'wpcpm_institution_tracks_save', 'wpcpm_institution_assign' ) );
$t19_referer = array();
foreach ( array( 'representative, a request waiting', 'Administrator, filled' ) as $t19_name ) {
	$t19_referer[] = substr_count( $t19_new[ $t19_name ], 'name="_wp_http_referer"' ) === substr_count( $t19_new[ $t19_name ], '<form ' );
}
ck( 'the referer is drawn once in each form', $t19_referer, array( true, true ) );
ck( 'and the parts\' nonce carries no id, so no id repeats on the page', substr_count( implode( '', $t19_new ), 'id="_wpnonce"' ), 0 );
$t19_dup = array();
foreach ( $t19_new as $t19_name => $t19_html ) {
	preg_match_all( '/\sid="([^"]+)"/', $t19_html, $t19_ids );
	if ( count( $t19_ids[1] ) !== count( array_unique( $t19_ids[1] ) ) ) {
		$t19_dup[] = $t19_name;
	}
}
ck( 'no id repeats in any state on the parts', $t19_dup, array() );
ck( 'every form keeps novalidate and the double-press guard, and works with no script (a plain post with its own submit)', array_values( array_unique( array_map( static function ( $form ) { return array( $form['method'], $form['novalidate'], $form['once'], count( $form['submits'] ) ); }, t19_forms( implode( '', $t19_new ) ) ), SORT_REGULAR ) ), array( array( 'post', true, true, 1 ) ) );

// The fields: the application's own questions, the languages a choice group, Country the one combobox.
ck( 'the first form asks the application\'s questions, with their labels, their Required in words and their help tied to each box', array(
	has( $t19_now, '<label class="wpcpm-ui-field__label" for="wpcpm-details-contact_person">Name of the person we should contact <span class="wpcpm-ui-field__need">Required</span></label><input type="text" class="wpcpm-ui-field__control" id="wpcpm-details-contact_person" name="wpcpm_details_contact_person" required maxlength="120" autocomplete="name" value="Contact One">' ),
	has( $t19_now, 'id="wpcpm-details-contact_role" name="wpcpm_details_contact_role" required maxlength="120" aria-describedby="wpcpm-details-contact_role-hint" value="Head of the international office"><p class="wpcpm-ui-field__hint" id="wpcpm-details-contact_role-hint">For example: internship coordinator, or head of the computer science department.</p>' ),
	has( $t19_now, '<label class="wpcpm-ui-field__label" for="wpcpm-details-signer_name">Who will sign the agreement for the institution?</label>' ),
	has( $t19_now, 'id="wpcpm-details-credits_page" name="wpcpm_details_credits_page" maxlength="200" inputmode="url" aria-describedby="wpcpm-details-credits_page-hint" value="https://ejemplo.edu/credits">' ),
), array( true, true, true, true ) );
ck( 'the languages are a choice group of the application\'s list, in columns, its legend the question, its help tied to the group', array(
	has( $t19_now, '<fieldset class="wpcpm-ui-choices wpcpm-ui-choices--columns" aria-describedby="wpcpm-details-student_languages-hint"><legend class="wpcpm-ui-choices__legend">In which languages do your students study? <span class="wpcpm-ui-field__need">Required</span></legend><p class="wpcpm-ui-field__hint" id="wpcpm-details-student_languages-hint">Tick every language your students study in.</p><div class="wpcpm-ui-choices__grid">' ),
	substr_count( $t19_now, 'name="wpcpm_details_student_languages[]"' ),
	has( $t19_now, '<input type="checkbox" class="wpcpm-ui-choice__input" id="wpcpm-details-student_languages-Spanish" name="wpcpm_details_student_languages[]" value="Spanish" checked>' ),
), array( true, count( WPCPM_Mentor_Application_Form::languages() ), true ) );
$t19_country = t19_between( $t19_checked, '<div class="wpcpm-ui-field wpcpm-ui-field--combo">', '</div> </div>' );
ck( 'Country is the one combobox, the field the application draws: a label, the select it sends, and beside it, hidden, the field that takes typing', array(
	has( $t19_country, '<label class="wpcpm-ui-field__label" id="wpcpm-details-country-label" for="wpcpm-details-country">Country <span class="wpcpm-ui-field__need">Required</span></label> <select class="wpcpm-ui-field__control wpcpm-ui-field__control--select" name="wpcpm_details_country" id="wpcpm-details-country" autocomplete="off" aria-required="true">' ),
	has( $t19_country, '<option value="' . COSTA . '" selected="selected">Costa Rica</option>' ),
	has( $t19_country, '<div class="wpcpm-dashboard__switcher-combo" hidden><input type="text" id="wpcpm-details-country-input" class="wpcpm-dashboard__switcher-input wpcpm-ui-field__control wpcpm-ui-field__control--select" role="combobox"' ),
	has( $t19_country, 'placeholder="Type to find your country"' ),
	has( $t19_country, 'data-wpcpm-count="Countries in the list: %s" data-wpcpm-none="No country has that name."' ),
	in_array( 'wpcpm-switcher', $GLOBALS['enqueued_scripts'] ?? array(), true ),
	substr_count( $t19_checked, '<select' ),
), array( true, true, true, true, true, true, 1 ) );
ck( 'Country, Required in words with no required on its select, says so to a screen reader on both of its boxes (field()\'s promise)', array(
	has( $t19_country, 'name="wpcpm_details_country" id="wpcpm-details-country" autocomplete="off" aria-required="true">' ),
	1 === preg_match( '#id="wpcpm-details-country-input" class="[^"]*" role="combobox"[^>]*aria-required="true"#', $t19_country ),
	has( $t19_country, ' required' ),
), array( true, true, false ) );
ck( 'the email box is an email box and the website a box of text that brings the address keyboard, as today', array(
	has( $t19_checked, '<input type="email" class="wpcpm-ui-field__control" id="wpcpm-details-contact_email" name="wpcpm_details_contact_email" required maxlength="200" autocomplete="email" inputmode="email"' ),
	has( $t19_checked, '<input type="text" class="wpcpm-ui-field__control" id="wpcpm-details-website" name="wpcpm_details_website" required maxlength="200" inputmode="url"' ),
), array( true, true ) );

// ---- 6. A refusal: the typing kept, the sentence tied to its box, the fold open ----

$t19_ref1 = t19_fold( $t19_new['representative, the first group refused, typing kept'], 'wpcpm-details-now' );
$t19_ref2 = t19_fold( $t19_new['representative, the second group refused, typing kept'], 'wpcpm-details-checked' );
ck( 'the refused form\'s fold is open, the other closed', array(
	0 === strpos( $t19_ref1, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="wpcpm-details-now">' ),
	0 === strpos( t19_fold( $t19_new['representative, the first group refused, typing kept'], 'wpcpm-details-checked' ), '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id="wpcpm-details-checked">' ),
	0 === strpos( $t19_ref2, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="wpcpm-details-checked">' ),
), array( true, true, true ) );
ck( 'what was typed is kept, as the old branch keeps it', array(
	t19_box( $t19_ref1, 'wpcpm_details_contact_role' ), t19_box( $t19_ref1, 'wpcpm_details_contact_person' ),
	t19_box( $t19_ref2, 'wpcpm_details_name' ), t19_box( $t19_ref2, 'wpcpm_details_city' ), t19_box( $t19_ref2, 'wpcpm_details_contact_email' ),
), array( 'Kept role, as typed', '', 'Valencia University', '', 'not-an-email' ) );
ck( 'each refused box says why under it, tied to it (aria-invalid, then its sentence before its hint)', array(
	has( $t19_ref1, 'id="wpcpm-details-contact_person" name="wpcpm_details_contact_person" required maxlength="120" autocomplete="name" aria-invalid="true" aria-describedby="wpcpm-details-contact_person-problem" value=""><p class="wpcpm-field__problem" id="wpcpm-details-contact_person-problem">' ),
	has( $t19_ref2, 'aria-invalid="true" aria-describedby="wpcpm-details-contact_email-problem wpcpm-details-contact_email-hint"' ),
	has( $t19_ref2, '<div class="wpcpm-ui-field wpcpm-ui-field--combo"><label class="wpcpm-ui-field__label" id="wpcpm-details-country-label" for="wpcpm-details-country">Country <span class="wpcpm-ui-field__need">Required</span></label> <select class="wpcpm-ui-field__control wpcpm-ui-field__control--select" name="wpcpm_details_country" id="wpcpm-details-country" autocomplete="off" aria-required="true" aria-invalid="true" aria-describedby="wpcpm-details-country-problem">' ),
	has( $t19_ref2, '<p class="wpcpm-field__problem" id="wpcpm-details-country-problem">Choose one of the options offered.</p></div>' ),
), array( true, true, true, true ) );
ck( 'a refused group of languages says why under its options, named by the group', array(
	has( $t19_ref1, '<fieldset class="wpcpm-ui-choices wpcpm-ui-choices--columns wpcpm-ui-field" aria-describedby="wpcpm-details-student_languages-problem wpcpm-details-student_languages-hint">' ),
	1 === preg_match( '#</div><p class="wpcpm-field__problem" id="wpcpm-details-student_languages-problem">[^<]+</p></fieldset>#', $t19_ref1 ),
), array( true, true ) );
$t19_msg = WPCPM_Institution_Details_Card::messages();
// Gate B round 2, F2: a refusal is said at the form it refused, the open fold's first block, directly over the form; a
// success keeps the card's message place at the head of the body.
ck( 'the outcome is the shell\'s message, its tone from the card\'s map: a refusal the refused fold\'s first block, over its form, once; a success at the head of the body', array(
	0 === strpos( substr( $t19_ref1, (int) strpos( $t19_ref1, '<div class="wpcpm-ui-fold__body">' ) ), '<div class="wpcpm-ui-fold__body">' . WPCPM_Dashboard_Card::message( 'error', $t19_msg['refused-now'][1], 'wpcpm-details__message' ) . '<form ' ),
	0 === strpos( substr( $t19_ref2, (int) strpos( $t19_ref2, '<div class="wpcpm-ui-fold__body">' ) ), '<div class="wpcpm-ui-fold__body">' . WPCPM_Dashboard_Card::message( 'error', $t19_msg['refused-checked'][1], 'wpcpm-details__message' ) . '<form ' ),
	substr_count( $t19_new['representative, the first group refused, typing kept'], 'data-wpcpm-card-message' ),
	has( $t19_new['message: saved'] ?? '', '<div class="wpcpm-ui-fold__body">' . WPCPM_Dashboard_Card::message( 'success', $t19_msg['saved'][1], 'wpcpm-details__message' ) . '<h4' ),
), array( true, true, 1, true ) );

// Gate B round 2, F2: a section's refusal is said in its open fold, over its form, its way back's id with it; a success
// under the section's heading.
$f2_assign = $t19_new['Administrator, the assignment refused'];
$f2_fold   = substr( $f2_assign, (int) strpos( $f2_assign, 'id="wpcpm-details-assignment"' ) );
ck( 'the assignment refused: its outcome, with the way back\'s id, is the open fold\'s first block, directly over the form', array(
	1 === preg_match( '#^id="wpcpm-details-assignment"><summary.*?</summary><div class="wpcpm-ui-fold__body"><div id="wpcpm-details-assignment-outcome"><p class="wpcpm-details__message wpcpm-details__message--error[^>]*>[^<]*</p></div><form #s', $f2_fold ),
	1 === preg_match( '#<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="wpcpm-details-assignment"#', $f2_assign ),
	substr_count( $f2_assign, 'id="wpcpm-details-assignment-outcome"' ),
), array( true, true, 1 ) );

// Gate B round 3, R3: Tracks offered's refusal, the assigned Administrator's twin: in its open fold, over its form, its
// way back's id with it; for a member, who is drawn no form, no refusal.
$r3_tracks = t19_play( static function () { t19_fresh(); t19_flash( 1, array( 'status' => 'saving-busy', 'section' => 'tracks' ) ); return array( 1, A ); }, true );
$r3_fold   = substr( $r3_tracks, (int) strpos( $r3_tracks, 'id="wpcpm-details-tracks"' ) );
ck( 'Tracks offered refused: its outcome, with the way back\'s id, is the open fold\'s first block, directly over the form, once', array(
	1 === preg_match( '#^id="wpcpm-details-tracks"><summary.*?</summary><div class="wpcpm-ui-fold__body"><div id="wpcpm-details-tracks-outcome"><p class="wpcpm-details__message wpcpm-details__message--error[^>]*>[^<]*</p></div><form #s', $r3_fold ),
	1 === preg_match( '#<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="wpcpm-details-tracks"#', $r3_tracks ),
	substr_count( $r3_tracks, 'id="wpcpm-details-tracks-outcome"' ),
), array( true, true, 1 ) );

// ---- 7. A request waiting, and its outcome ----

$t19_wait  = $t19_new['representative, a request waiting'];
$t19_wrows = t19_rows( $t19_wait );
ck( 'the waiting line sits under each row it changes, in words, and under no other', array(
	1 === preg_match( '#<th scope="row" role="rowheader" class="wpcpm-ui-cell--text">Name</th><td class="wpcpm-ui-cell--text" role="cell">Universidad Ejemplo <span class="wpcpm-ui-cell__sub">Waiting for an Administrator: Universidad Ejemplo de Valencia, asked on <span class="wpcpm-ui-whole">[0-9-]+</span>\.</span><form #', $t19_wait ),
	1 === preg_match( '#>City</th><td class="wpcpm-ui-cell--text" role="cell">San Jose <span class="wpcpm-ui-cell__sub">Waiting for an Administrator: Valencia, asked on #', $t19_wait ),
	substr_count( $t19_wait, 'Waiting for an Administrator:' ),
	has( $t19_wait, 'wpcpm-warning' ),
	array( $t19_wrows['Name'][0], $t19_wrows['City'][0] ),
), array( true, true, 2, false, array( 'Universidad Ejemplo', 'San Jose' ) ) );
ck( 'with Withdraw it, quiet red, today\'s form (no question today), the row named for a screen reader', array(
	1 === preg_match( '#<form method="post" action="https://example.test/wp-admin/admin-post.php" novalidate data-wpcpm-once data-wpcpm-busy="Withdrawing"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_withdraw_details_\d+"><input type="hidden" name="_wp_http_referer" value="[^"]*" /><input type="hidden" name="action" value="wpcpm_withdraw_details" /><input type="hidden" name="request" value="\d+" /><input type="hidden" name="wpcpm_details_return" value="wpcpm-details" /><button type="submit" class="wpcpm-btn wpcpm-btn--quiet wpcpm-btn--danger">Withdraw it<span class="screen-reader-text"> Name</span></button></form>#', $t19_wait ),
	substr_count( $t19_wait, '>Withdraw it<' ),
	has( $t19_wait, 'data-wpcpm-confirm' ),
), array( true, 2, false ) );
ck( 'while it waits the second group offers no form; the first does', array( t19_fold( $t19_wait, 'wpcpm-details-checked' ), '' !== t19_fold( $t19_wait, 'wpcpm-details-now' ) ), array( '', true ) );
$t19_aw = $t19_new['Administrator, a request waiting'];
ck( 'an Administrator reads the lines, is offered no Withdraw it, and is told where the request is decided, linked', array(
	substr_count( $t19_aw, 'Waiting for an Administrator:' ),
	has( $t19_aw, 'Withdraw it' ),
	has( $t19_aw, '<p class="wpcpm-ui-intro">A request from the school is waiting. Decide it in <a href="https://example.test/administrator-dashboard/#wpcpm-requests">Requests from institutions on the Administrator Dashboard</a>.</p>' ),
	strpos( $t19_aw, 'wpcpm-details-checked-heading' ) < strpos( $t19_aw, 'A request from the school is waiting.' ),
	t19_fold( $t19_aw, 'wpcpm-details-checked' ),
	has( $t19_new['Administrator, a request waiting, no Administrator Dashboard'], '<p class="wpcpm-ui-intro">A request from the school is waiting. Decide it in Requests from institutions on the Administrator Dashboard.</p>' ),
), array( 2, false, true, true, '', true ) );
ck( 'the outcome line sits under the second table, through outcome_sentence(), before the fold', array(
	1 === preg_match( '#</tbody></table><p class="wpcpm-ui-intro">Updated on [0-9-]+\.</p><details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id="wpcpm-details-checked">#', $t19_new['representative, an approved request'] ),
	has( $t19_new['representative, a declined request with a note'], '<p class="wpcpm-ui-intro">' . esc_html( WPCPM_Institution_Details_Card::outcome_sentence( array( 'state' => 'declined', 'note' => 'Please send the letter from the registrar & the dean.' ) ) ) . '</p>' ),
	has( $t19_new['representative, a declined request'], '<p class="wpcpm-ui-intro">An Administrator did not make the change you asked for.</p>' ),
	strpos( $t19_new['representative, an approved request'], 'Updated on' ) > strpos( $t19_new['representative, an approved request'], 'wpcpm-details-checked-heading' ),
), array( true, true, true, true ) );

// ---- 8. The messages (standing rule 6) ----

$t19_said = array();
foreach ( array_merge( WPCPM_Institution_Details_Card::messages(), WPCPM_Institution_Request::school_messages() ) as $t19_status => $t19_message ) {
	$t19_html  = $t19_new[ 'message: ' . $t19_status ];
	$t19_shown = WPCPM_Dashboard_Card::message( $t19_message[0], $t19_message[1], 'wpcpm-details__message' );
	if ( 0 === strpos( $t19_status, 'tracks' ) ) {
		$t19_shown = '<div id="wpcpm-details-tracks-outcome">' . $t19_shown . '</div>';
	} elseif ( 0 === strpos( $t19_status, 'assignment' ) ) {
		$t19_shown = '<div id="wpcpm-details-assignment-outcome">' . $t19_shown . '</div>';
	}
	if ( 1 !== substr_count( $t19_html, $t19_shown ) || 1 !== substr_count( $t19_html, 'data-wpcpm-card-message' ) ) {
		$t19_said[] = $t19_status;
	}
}
ck( 'each message is printed once, by the shell\'s message(), in its tone; a section\'s under its heading, where its way back lands', $t19_said, array() );
ck( 'a sentence a handler said is an error, alerted', has( $t19_new['representative, a sentence said'], '<p class="wpcpm-details__message wpcpm-details__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>One change is already waiting for an Administrator.</p>' ), true );
ck( 'the tracks\' and the assignment\'s are not printed at the card\'s head, and their folds are open', array(
	strpos( $t19_new['message: tracks-saved'], 'data-wpcpm-card-message' ) > strpos( $t19_new['message: tracks-saved'], '>Tracks offered</h4>' ),
	0 === strpos( t19_fold( $t19_new['message: tracks-saved'], 'wpcpm-details-tracks' ), '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="wpcpm-details-tracks">' ),
	strpos( $t19_new['message: assignment-saved'], 'data-wpcpm-card-message' ) > strpos( $t19_new['message: assignment-saved'], '>Assigned Administrator</h4>' ),
	0 === strpos( t19_fold( $t19_new['message: assignment-saved'], 'wpcpm-details-assignment' ), '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id="wpcpm-details-assignment">' ),
), array( true, true, true, true ) );

// ---- 9. Tracks offered, and who follows it up ----

$t19_tr_member = t19_between( $t19_filled, '>Tracks offered</h4>' );
ck( 'Tracks offered: a heading and a list of the runnable tracks; a representative is told who sets them, and drawn no form', array(
	has( $t19_tr_member, '<ul class="wpcpm-ui-list"><li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">WordPress Credits Program 150h</span><span class="wpcpm-ui-list__facts"></span></div></li><li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">Developer Track</span><span class="wpcpm-ui-list__facts"></span></div></li></ul>' ),
	has( $t19_tr_member, '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">The program sets the tracks your institution offers. To change them, write to education@wordpressfoundation.org.</p>' ),
	has( $t19_tr_member, '<form' ),
	has( $t19_tr_member, 'Assigned Administrator' ),
), array( true, true, false, false ) );
ck( 'four tracks, four rows; none, the one muted line', array( substr_count( t19_between( $t19_long_html, '>Tracks offered</h4>' ), 'wpcpm-ui-list__row' ), has( $t19_new['Administrator, nothing recorded, nobody assigned, no track'], '<p class="wpcpm-ui-list__empty">None listed yet.</p>' ) ), array( 4, true ) );
$t19_tr_fold = t19_fold( $t19_new['Administrator, filled'], 'wpcpm-details-tracks' );
ck( 'an Administrator\'s Change the tracks: an inner fold, closed, a choice group of every track the site runs, its hint, one primary', array(
	0 === strpos( $t19_tr_fold, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id="wpcpm-details-tracks"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Change the tracks</span></span>' ),
	has( $t19_tr_fold, '<fieldset class="wpcpm-ui-choices" aria-describedby="wpcpm-details-tracks-offered-hint"><legend class="wpcpm-ui-choices__legend">Tracks offered</legend><p class="wpcpm-ui-field__hint" id="wpcpm-details-tracks-offered-hint">Students are asked which track they want when two or more are ticked.</p>' ),
	substr_count( $t19_tr_fold, 'name="wpcpm_tracks_offered[]"' ),
	substr_count( $t19_tr_fold, 'name="wpcpm_tracks_offered[]" value="In Sensei" checked>' ) + substr_count( $t19_tr_fold, 'name="wpcpm_tracks_offered[]" value="Developer Track" checked>' ),
	has( $t19_tr_fold, '<div class="wpcpm-ui-actions"><button type="submit" class="wpcpm-btn">Save tracks</button></div>' ),
), array( true, true, 4, 2, true ) );
$t19_as = t19_between( $t19_new['Administrator, filled'], '>Assigned Administrator</h4>' );
$t19_as_fold = t19_fold( $t19_as, 'wpcpm-details-assignment' );
ck( 'an Administrator\'s Assigned Administrator: the one row, then Change who follows it up, the combobox, one primary', array(
	has( $t19_as, '<th scope="row" role="rowheader" class="wpcpm-ui-cell--text">Administrator</th><td class="wpcpm-ui-cell--text" role="cell">Bo Boss</td>' ),
	0 === strpos( $t19_as_fold, '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id="wpcpm-details-assignment"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Change who follows it up</span></span>' ),
	has( $t19_as_fold, '<label class="wpcpm-ui-field__label" id="wpcpm-details-assigned-label" for="wpcpm-details-assigned">Administrator</label> <select class="wpcpm-ui-field__control wpcpm-ui-field__control--select" name="wpcpm_assigned" id="wpcpm-details-assigned" autocomplete="off"><option value="0">Nobody</option><option value="12" selected="selected">Bo Boss</option><option value="13">Cy Chief</option><option value="1">Manager</option></select>' ),
	has( $t19_as_fold, '<div class="wpcpm-ui-actions"><button type="submit" class="wpcpm-btn">Save</button></div>' ),
	has( $t19_new['Administrator, nothing recorded, nobody assigned, no track'], '>Administrator</th><td class="wpcpm-ui-cell--text wpcpm-ui-cell--empty" role="cell">Nobody yet</td>' ),
	has( $t19_new['Administrator, an account no longer an Administrator'], '<td class="wpcpm-ui-cell--text" role="cell">Cy Chief (not an Administrator now)</td>' ),
), array( true, true, true, true, true, true ) );

// ---- 10. Every reader is drawn the same facts and offered the same actions on both branches (standing rule 3) ----

$t19_facts = array();
foreach ( $t19_states as $t19_name => $t19_state ) {
	if ( md5( serialize( t19_words( t19_mask( $t19_new[ $t19_name ] ) ) ) ) !== $t19_old_pins['words'][ $t19_name ] ) {
		$t19_facts[] = $t19_name;
	}
}
ck( 'every state says the words the old card said, the lead the view\'s lede now says aside: no fact added, none dropped', $t19_facts, array() );
ck( 'the contact\'s email and the page address are drawn to exactly the readers that see them today', array_map( static function ( $name ) use ( $t19_new ) {
	return array( has( $t19_new[ $name ], 'contact@example.test' ), has( $t19_new[ $name ], 'ejemplo.edu/credits' ) );
}, array( 'representative, filled', 'Administrator, filled', 'tutor', 'member of another school' ) ), array( array( true, true ), array( true, true ), array( false, false ), array( false, false ) ) );

// ---- 11. Hostile words (standing rule 10) ----

$t19_hostile = array();
foreach ( array( 'hostile, an Administrator', 'hostile, a representative', 'hostile, a declined request', 'hostile, a request waiting', 'hostile, a request waiting with a tag' ) as $t19_name ) {
	foreach ( array( 'parts' => $t19_new[ $t19_name ] ) as $t19_branch => $t19_html ) {
		$t19_flat = str_replace( '<wbr>', '', $t19_html );
		$t19_bad  = array();
		if ( false !== stripos( $t19_flat, '<script' ) || false !== stripos( $t19_flat, '<b>' ) || false !== strpos( $t19_flat, '<y>' ) ) {
			$t19_bad[] = 'a raw tag';
		}
		// A box's value is drawn by today's typed-text rules, which keep an entity a stored text holds as the words it reads
		// (`WPCPM_Typed_Text::attr_text()`): both branches draw it so, and the forms' check above holds them equal.
		if ( preg_match( '/&amp;(amp|lt|gt|quot|#0?39);/', (string) preg_replace( '/\svalue="[^"]*"/', '', $t19_flat ) ) ) {
			$t19_bad[] = 'escaped twice';
		}
		foreach ( t19_dom( $t19_html )->getElementsByTagName( '*' ) as $t19_el ) {
			foreach ( $t19_el->attributes as $t19_attr ) {
				if ( 1 !== preg_match( '/^(class|id|href|method|action|novalidate|role|type|name|value|for|maxlength|inputmode|autocomplete|required|checked|selected|open|hidden|tabindex|placeholder|spellcheck|scope|disabled|data-[a-z-]+|aria-[a-z]+|width|height|viewbox|fill|focusable|d|xmlns)$/', $t19_attr->name ) ) {
					$t19_bad[] = 'attribute ' . $t19_attr->name;
				}
			}
		}
		$t19_values = 'hostile, an Administrator' === $t19_name ? $GLOBALS['t19_hostile_values'] : ( 'hostile, a representative' === $t19_name ? array_slice( $GLOBALS['t19_hostile_values'], 0, 9 ) : array( 'hostile, a declined request' === $t19_name ? 'Note ' . T19_BAD : ( 'hostile, a request waiting with a tag' === $t19_name ? 'Waiting for an Administrator: Ort ' . T19_BAD : 'Ort "q" & Co' ) ) );
		if ( 'hostile, an Administrator' === $t19_name ) {
			$t19_values[] = 'Admin ' . T19_BAD;
		}
		foreach ( $t19_values as $t19_value ) {
			if ( ! has( $t19_flat, esc_html( $t19_value ) ) ) {
				$t19_bad[] = 'missing ' . $t19_value;
			}
		}
		if ( $t19_bad ) {
			$t19_hostile[ $t19_name . ' (' . $t19_branch . ')' ] = array_values( array_unique( $t19_bad ) );
		}
	}
}
ck( 'a waiting request asking for a tag: the line holds it escaped once', array_map( static function ( $html ) {
	$html = str_replace( '<wbr>', '', $html );
	return array( substr_count( $html, 'Waiting for an Administrator: Ort &lt;script&gt;alert(1)&lt;/script&gt; &quot;q&quot; &amp; Co, asked on ' ), false !== stripos( $html, '<script' ) );
}, array( $t19_new['hostile, a request waiting with a tag'] ) ), array( array( 1, false ) ) );
ck( 'every value a person types that the card prints, hostile, is printed escaped once: no raw tag, no broken attribute', $t19_hostile, array() );

// ---- 12. The parts extended for this card print the base's bytes without the new argument (standing rule 5) ----

$t19_combo = static function ( array $args ) {
	ob_start();
	WPCPM_Dashboards_Real::render_combo( $args );
	return md5( (string) ob_get_clean() );
};
ck( 'render_combo() with no need_html: the base\'s bytes (the application\'s country, the Viewing as switcher, the compact control)', array(
	$t19_combo( array( 'id' => 'wpcpm-app-country', 'name' => 'Country', 'options' => array( '' => 'Choose a country', COSTA => 'Costa Rica', POLAND => 'Poland' ), 'current' => '', 'label' => 'Country', 'mark' => 'Required', 'describedby' => 'wpcpm-app-country-problem', 'invalid' => true, 'required' => true, 'find' => 'Type to find your country', 'none' => 'No country has that name.', 'count' => 'Countries in the list: %s' ) ),
	$t19_combo( array( 'id' => 'wpcpm-institution-switcher', 'name' => 'wpcpm_institution_view', 'options' => array( A => 'Universidad Ejemplo', B => 'Another School' ), 'current' => A, 'label' => 'Viewing as institution', 'find' => 'Type to find an institution', 'none' => 'No institution has that name.' ) ),
	$t19_combo( array( 'id' => 'wpcpm-institution-switcher', 'name' => 'wpcpm_institution_view', 'options' => array( A => 'Universidad Ejemplo' ), 'current' => '', 'label' => 'Viewing as institution', 'label_class' => WPCPM_UI::VIEWING_LABEL, 'box_class' => WPCPM_UI::VIEWING_BOX, 'blank' => 'Choose an institution', 'describedby' => 'x-note' ) ),
), T19_COMBO_BASE );

/* ---- the layout harness ---- */

if ( function_exists( 'wpcpm_layout_keep' ) ) {
	foreach ( array(
		'representative, long words, six languages, four tracks',
		'representative, the first group refused, typing kept',
		'representative, the second group refused, typing kept',
		'representative, long words, a request waiting',
		'representative, a declined request with a note',
		'Administrator, long words, four tracks',
		'Administrator, a request waiting',
		'Administrator, the assignment refused',
		'representative, nothing recorded',
		'message: saved',
	) as $t19_name ) {
		// Every fold open: both correction folds, and an Administrator's two.
		wpcpm_layout_keep( 'Your institution\'s details: ' . $t19_name, str_replace( '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id=', '<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open id=', $t19_new[ $t19_name ] ) );
	}
}

$GLOBALS['real_combo'] = false;
$GLOBALS['core_nonce'] = false;
fresh();

echo "\n=== A school reads none of the base's words ===\n";

// Every sentence the card can say to a school, the withdrawal's and the Administrator-facing note included.
foreach ( array_merge( WPCPM_Institution_Details_Card::messages(), WPCPM_Institution_Request::school_messages() ) as $status => $message ) {
	words_in( $message[1] );
}
words_in( $decide );
ck( 'the card holds none of them, in any state this suite draws it in or any sentence it can say', array_values( $GLOBALS['base_words'] ), array() );
ck( 'and the refusal a school reads when the base turns a save down names the program\'s records instead', WPCPM_Institution_Details_Card::messages()['airtable'], array( 'error', 'The program records could not be updated, so nothing was saved. Try again in a while.' ) );

// The Institution Dashboard's suite holds this card to its rule by running this suite once more and reading the
// last line it prints (`bin/test-institutions-dashboard.php`).
if ( isset( $argv[1] ) && 'words' === $argv[1] ) {
	echo 'WORDS:' . json_encode( array_values( $GLOBALS['base_words'] ) ) . "\n";
}

echo $fail ? "\n{$fail} FAILURE(S)\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
