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
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
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
class WPCPM_Administrators_Dashboard {
	public static $url = 'https://example.test/administrator-dashboard/';
	public static function page_url() { return self::$url; }
}
class WPCPM_Institutions_Dashboard {
	public static $url = 'https://example.test/institution-dashboard/';
	public static function page_url() { return self::$url; }
}
/** The combobox the country is drawn with, as far as its arguments: what the card hands it is what is read. */
class WPCPM_Dashboards {
	public static function render_combo( array $args ) {
		$GLOBALS['combos'][] = $args;
		printf( '<label for="%1$s">%2$s</label><select name="%3$s" id="%1$s">', esc_attr( $args['id'] ), esc_html( $args['label'] ), esc_attr( $args['name'] ) );
		foreach ( (array) $args['options'] as $value => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $value, $args['current'], false ), esc_html( $label ) );
		}
		echo '</select>';
	}
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
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-details-card.php';

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
	$GLOBALS['manage']      = array( 1 );
	$GLOBALS['users']       = array(
		1  => new WP_User( 1, 'Manager', 'manager@example.test', array( 'administrator' ) ),
		5  => new WP_User( 5, 'Stranger', 'stranger@example.test', array( 'subscriber' ) ),
		7  => new WP_User( 7, 'Member One', 'member1@example.test', array( 'subscriber' ) ),
		8  => new WP_User( 8, 'Member Two', 'member2@example.test', array( 'subscriber' ) ),
		9  => new WP_User( 9, 'Member Three', 'member3@example.test', array( 'subscriber' ) ),
		10 => new WP_User( 10, 'Member Four', 'member4@example.test', array( 'subscriber' ) ),
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
	preg_match_all( '#<div class="wpcpm-details__row"><dt>(.*?)</dt><dd>(.*?)</dd>#s', $html, $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $row ) { $out[ html_entity_decode( $row[1], ENT_QUOTES ) ] = plain( $row[2] ); }
	return $out;
}
/** One form's disclosure, by group: `now` or `checked`; '' when it is not drawn. */
function disclosure( $html, $group ) {
	return 1 === preg_match( '#<details class="wpcpm-details__more" id="wpcpm-details-' . $group . '"[^>]*>.*?</details>#s', $html, $m ) ? $m[0] : '';
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
	return 1 === preg_match( '#name="' . preg_quote( $name, '#' ) . '" value="([^"]*)"#', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : null;
}
/** The languages a form has ticked. */
function ticked( $html ) {
	preg_match_all( '#name="wpcpm_details_student_languages\[\]" value="([^"]*)" checked="checked"#', $html, $m );
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
/** The hidden fields of the first form of a class on a page, as the browser would post them (the nonce apart). */
function hidden_fields( $html, $class ) {
	if ( 1 !== preg_match( '#<form class="' . preg_quote( $class, '#' ) . '"[^>]*>(.*?)</form>#s', $html, $form ) ) { return array(); }
	preg_match_all( '#<input type="hidden" name="([^"]*)" value="([^"]*)" />#', $form[1], $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $field ) { if ( '_wpnonce' !== $field[1] ) { $out[ $field[1] ] = html_entity_decode( $field[2], ENT_QUOTES ); } }
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
function member_back() { return 'redirect:https://example.test/institution-dashboard/#wpcpm-details'; }
function manager_back( $record = A ) { return 'redirect:https://example.test/institution-dashboard/?wpcpm_institution_view=' . $record . '#wpcpm-details'; }
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
ck( 'both handlers are registered for a signed-in account', $GLOBALS['hooks'], array( 'admin_post_wpcpm_save_details', 'admin_post_wpcpm_ask_details' ) );

/* ---- the read view ------------------------------------------------------- */

echo "\n=== The read view: two lists under a title ===\n";

$GLOBALS['combos'] = array();
$html              = draw( 7 );
$first_combos      = $GLOBALS['combos'];

ck( 'a section around an open details group, as the people card draws it', 0 === strpos( $html, '<section class="wpcpm-institution__card wpcpm-details" id="wpcpm-details"><details class="wpcpm-group wpcpm-group__disclosure" open><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Your institution&#039;s details</h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary><div class="wpcpm-group__body">' ), true );
ck( 'closed again after its body', substr( $html, -strlen( '</div></details></section>' ) ), '</div></details></section>' );
ck( 'the lead says what the list is', has( $html, '<p class="wpcpm-details__lead">What the program has on record.</p>' ), true );
preg_match_all( '#<h4 class="wpcpm-details__subtitle">(.*?)</h4>#', $html, $headings );
ck( 'the two small headings, in order', $headings[1], array( 'Contact and agreement', 'Name and address' ) );
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
ck( 'the first group\'s rows come before the second group\'s heading', strpos( $html, 'Their title' ) < strpos( $html, 'Name and address' ) && strpos( $html, 'Name and address' ) < strpos( $html, '<dt>Name</dt>' ), true );

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
ck( 'the first reads "Correct these (saved at once)"', has( $now, '<summary class="wpcpm-details__toggle">Correct these <span class="wpcpm-details__when">(saved at once)</span></summary>' ), true );
ck( 'the second reads "Ask to correct these (an Administrator checks first)"', has( $checked, '<summary class="wpcpm-details__toggle">Ask to correct these <span class="wpcpm-details__when">(an Administrator checks first)</span></summary>' ), true );
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
ck( 'what is required is marked so, as on the application', array( has( $now, 'Name of the person we should contact <span class="wpcpm-field__required">Required</span>' ), has( $now, 'Who will sign the agreement for the institution?</label>' ), has( $checked, 'Their email address <span class="wpcpm-field__required">Required</span>' ) ), array( true, true, true ) );

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
ck( 'the languages are the application\'s list as boxes, in columns', array( has( $now, 'wpcpm-field--columns' ), substr_count( $now, 'type="checkbox"' ) > 10, has( $now, '<span>Spanish</span>' ) ), array( true, true, true ) );
$combo = isset( $first_combos[0] ) ? $first_combos[0] : array( 'options' => array() );
ck( 'the country is the one type-to-find field, handed the application\'s words and the school\'s country', array( count( $first_combos ), $combo['name'] ?? null, $combo['current'] ?? null, $combo['label'] ?? null, $combo['mark'] ?? null, $combo['find'] ?? null, reset( $combo['options'] ), isset( $combo['options'][ POLAND ] ) ), array( 1, 'wpcpm_details_country', COSTA, 'Country', 'Required', 'Type to find your country', 'Choose a country', true ) );
ck( 'and not as a search box above a select: one field, whose select carries the answer', array( has( $checked, '<select name="wpcpm_details_country"' ), has( $checked, '<option value="' . COSTA . '" selected="selected">Costa Rica</option>' ) ), array( true, true ) );
ck( 'a box carries the limit the application gives it', array( has( $now, 'name="wpcpm_details_contact_person" value="Contact One" maxlength="120"' ), has( $checked, 'name="wpcpm_details_name" value="Universidad Ejemplo" maxlength="200"' ) ), array( true, true ) );

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
ck( 'in the words the plan gives', has( $after, '<p class="wpcpm-details__message is-success" role="status">Your institution&#039;s details are saved.</p>' ), true );
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
ck( 'in the words the plan gives', has( draw( 7 ), '<p class="wpcpm-details__message is-success" role="status">Sent: an Administrator checks the change, and you will see it here once it is decided.</p>' ), true );

echo "\n=== While it waits ===\n";

$waiting = draw( 8 );
$day     = WPCPM_Dates::day( (int) get_post_time( 'U', true, get_post( $id ) ) );
ck( 'every account of the school reads the wait under the rows that changed, with what was asked and the day', array( has( plain( $waiting ), 'Waiting for an Administrator: Universidad Ejemplo de Valencia, asked on ' . $day . '.' ), has( plain( $waiting ), 'Waiting for an Administrator: Valencia, asked on ' . $day . '.' ) ), array( true, true ) );
ck( 'a line to each row that changed and none to the others', substr_count( $waiting, 'class="wpcpm-details__waiting wpcpm-warning"' ), 2 );
ck( 'right under the row of the detail it changes', array( has( $waiting, '<div class="wpcpm-details__row"><dt>Name</dt><dd>Universidad Ejemplo</dd><dd class="wpcpm-details__waiting wpcpm-warning">' ), has( $waiting, '<div class="wpcpm-details__row"><dt>City</dt><dd>San Jose</dd><dd class="wpcpm-details__waiting wpcpm-warning">' ), has( $waiting, '<div class="wpcpm-details__row"><dt>Website</dt><dd>https://ejemplo.edu</dd></div>' ) ), array( true, true, true ) );
ck( 'the rows still read what the program has on record', array( rows( $waiting )['Name'], rows( $waiting )['City'] ), array( 'Universidad Ejemplo', 'San Jose' ) );
preg_match_all( '#<form class="wpcpm-details__withdraw"[^>]*>(.*?)</form>#s', $waiting, $withdraw );
ck( 'each line ends with Withdraw it, a form that posts the withdrawal of this request', array( count( $withdraw[1] ), has( $withdraw[1][0], 'value="wpcpm_withdraw_details"' ), has( $withdraw[1][0], 'name="request" value="' . $id . '"' ), has( $withdraw[1][0], 'value="nonce-wpcpm_withdraw_details_' . $id . '"' ), has( $withdraw[1][0], '>Withdraw it</button>' ), has( $waiting, 'action="https://example.test/wp-admin/admin-post.php"' ) ), array( 2, true, true, true, true, true ) );
ck( 'and names the card it was pressed on, so the withdrawal comes back to it', array( hidden_fields( $waiting, 'wpcpm-details__withdraw' ), WPCPM_Institution_Details_Card::ANCHOR === WPCPM_Institution_Request::DETAILS_ANCHOR ), array( array( 'action' => 'wpcpm_withdraw_details', 'request' => (string) $id, 'wpcpm_details_return' => 'wpcpm-details' ), true ) );
ck( 'the second group offers no form while a request waits', array( disclosure( $waiting, 'checked' ), has( $waiting, 'Ask to correct these' ), has( $waiting, 'wpcpm_ask_details' ) ), array( '', false, false ) );
ck( 'the first group still does', array( has( disclosure( $waiting, 'now' ), 'Correct these' ), has( $waiting, 'wpcpm_save_details' ) ), array( true, true ) );
$admin_view = draw( 1 );
ck( 'an Administrator reads the same lines', has( plain( $admin_view ), 'Waiting for an Administrator: Valencia, asked on ' . $day . '.' ), true );
ck( 'but is not offered the school\'s own Withdraw it: that is the school\'s, and the request is decided where requests are', array( substr_count( $admin_view, 'class="wpcpm-details__waiting wpcpm-warning"' ), substr_count( $admin_view, 'wpcpm-details__withdraw' ), has( $admin_view, 'Withdraw it' ) ), array( 2, 0, false ) );
ck( 'while the school\'s accounts are, a form to each line', array( substr_count( $waiting, 'class="wpcpm-details__withdraw"' ), substr_count( draw( 7 ), 'class="wpcpm-details__withdraw"' ) ), array( 2, 2 ) );

echo "\n=== Review Focus 2: a second account, or a second tab, asking while one waits ===\n";

$mails_before = count( $GLOBALS['mail'] );
$out          = post_as( 8, 'checked', array( 'wpcpm_details_website' => 'https://another.example.edu' ) );
$refused      = draw( 8 );
ck( 'another account of the school is sent back to the card', $out, member_back() );
ck( 'with the store\'s own sentence, as an error', has( $refused, '<p class="wpcpm-details__message is-error" role="status">One change is already waiting for an Administrator.</p>' ), true );
ck( 'and no second request was made', count( requests() ), 1 );
ck( 'the first one is as it was asked', requests()[ $id ]['changes'], array( 'name' => array( 'from' => 'Universidad Ejemplo', 'to' => 'Universidad Ejemplo de Valencia' ), 'city' => array( 'from' => 'San Jose', 'to' => 'Valencia' ) ) );
ck( 'no email went, and no history row, and nothing was written', array( count( $GLOBALS['mail'] ) - $mails_before, count( audit( A, 'request_raised' ) ), $GLOBALS['patched'], $GLOBALS['index'][ A ]['website'] ), array( 0, 1, array(), 'https://ejemplo.edu' ) );
$out = post_as( 7, 'checked', array( 'wpcpm_details_city' => 'Madrid' ) );
ck( 'the same account in a second tab is refused the same way', array( $out, has( plain( draw( 7 ) ), 'One change is already waiting for an Administrator.' ), count( requests() ), requests()[ $id ]['changes']['city']['to'] ), array( member_back(), true, 1, 'Valencia' ) );
ck( 'and the page offers no form to type into again: the waiting request is what the second group shows', array( disclosure( $refused, 'checked' ), substr_count( $refused, 'class="wpcpm-details__waiting wpcpm-warning"' ) ), array( '', 2 ) );

// A second tab's post that the rules would refuse is told what is true, that one is waiting, and not to correct
// what is marked in a form the page does not draw. The store stays the guard under its lock (below).
$one_waiting  = 'One change is already waiting for an Administrator.';
$mails_before = count( $GLOBALS['mail'] );
$out          = post_as( 8, 'checked', array( 'wpcpm_details_city' => '', 'wpcpm_details_contact_email' => 'not-an-email' ) );
$typing_kept  = isset( $GLOBALS['umeta'][8]['wpcpm_flash']['typed:details:' . A . ':checked'] );
$late         = draw( 8 );
ck( 'an invalid post of the second group is refused with the store\'s sentence and no sentence about fields that are not drawn', array( $out, has( $late, '<p class="wpcpm-details__message is-error" role="status">' . $one_waiting . '</p>' ), has( $late, 'Nothing was sent' ), has( $late, 'wpcpm-field__problem' ) ), array( member_back(), true, false, false ) );
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
ck( 'a request being raised at that moment, under the school\'s lock, refuses the second press: someone is saving the details, and nothing is made', array( '' !== $token, $out, has( plain( $pressed ), 'Someone else is saving your institution\'s details at this moment, so nothing was done. Try again in a moment.' ), has( plain( $pressed ), 'One change is already waiting' ), box( disclosure( $pressed, 'checked' ), 'wpcpm_details_name' ), requests(), $GLOBALS['mail'] ), array( true, member_back(), true, false, 'Pressed Together', array(), array() ) );

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
ck( 'once the school sends its next request the earlier outcome is not shown, only the wait', array( has( draw( 8 ), 'did not make the change' ), has( draw( 8 ), 'wpcpm-details__waiting' ) ), array( false, true ) );
WPCPM_Institution_Request::settle( $second, 'withdrawn', '', 7, 'member' );
ck( 'and a withdrawal replaces it, so the decline of the request before is not shown again', has( draw( 8 ), 'did not make the change' ), false );

echo "\n=== The withdrawal's outcome, on the channel Task 2 flashes ===\n";

fresh();
foreach ( WPCPM_Institution_Request::school_messages() as $status => $message ) {
	WPCPM_Flash::set( WPCPM_Institution_Request::FLASH_DETAILS, array( 'status' => $status ), 7 );
	$page = draw( 7 );
	ck( 'a ' . $status . ' withdrawal reads in the words the request class gives it', has( $page, '<p class="wpcpm-details__message is-' . $message[0] . '" role="status">' . esc_html( $message[1] ) . '</p>' ), true );
}
WPCPM_Flash::set( WPCPM_Institution_Request::FLASH_DETAILS, array( 'status' => 'saved', 'record' => B ), 7 );
ck( 'what was saved for another institution is not said on this one\'s card, and is taken all the same', array( has( draw( 7 ), 'wpcpm-details__message' ), flash_of( 7 ) ), array( false, null ) );
WPCPM_Flash::set( WPCPM_Institution_Request::FLASH_DETAILS, array( 'status' => 'saved', 'record' => A ), 7 );
ck( 'and what was saved for this one is', has( draw( 7 ), 'Your institution&#039;s details are saved.' ), true );
WPCPM_Flash::set( WPCPM_Institution_Request::FLASH_DETAILS, array( 'status' => 'nonsense' ), 7 );
ck( 'a status nobody knows draws nothing, and is taken all the same', array( has( draw( 7 ), 'wpcpm-details__message' ), flash_of( 7 ) ), array( false, null ) );

post_as( 7, 'checked', array( 'wpcpm_details_name' => 'Universidad Ejemplo de Valencia' ) );
$id    = (int) array_key_first( requests() );
$form  = hidden_fields( draw( 8 ), 'wpcpm-details__withdraw' );
$out   = withdraw_as( 8, $id, array_diff_key( $form, array( 'action' => 1, 'request' => 1 ) ) );
$page  = draw( 8 );
ck( 'the school\'s own Withdraw it, posted by a colleague as the form posts it, closes the request, lands back on the card and says so there', array( $out, requests()[ $id ]['state'], has( $page, '<p class="wpcpm-details__message is-success" role="status">The request is withdrawn.</p>' ), has( disclosure( $page, 'checked' ), 'Send to an Administrator' ), has( $page, 'wpcpm-details__waiting' ) ), array( 'redirect:https://example.test/institution-dashboard/#wpcpm-details', 'withdrawn', true, true, false ) );

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
ck(
	'an Administrator\'s second form reads "Correct these (saved at once)" and its button "Save the details", as the first form does',
	array(
		has( $admin_checked, '<summary class="wpcpm-details__toggle">Correct these <span class="wpcpm-details__when">(saved at once)</span></summary>' ),
		has( $admin_checked, '>Save the details</button>' ),
		has( $admin_checked, 'data-wpcpm-busy="Saving"' ),
		has( $admin_checked, 'Ask to correct these' ),
		has( $admin_checked, 'an Administrator checks first' ),
		has( $admin_checked, 'Send to an Administrator' ),
	),
	array( true, true, true, false, false, false )
);
ck( 'and still posts the second group\'s action, under the note that it is saved at once', array( has( $admin_checked, '<input type="hidden" name="action" value="wpcpm_ask_details" />' ), has( $admin_checked, 'You are an Administrator, so these are saved at once and nobody approves them.' ) ), array( true, true ) );
ck(
	'the school\'s accounts keep "Ask to correct these (an Administrator checks first)" and "Send to an Administrator"',
	array(
		has( $member_checked, '<summary class="wpcpm-details__toggle">Ask to correct these <span class="wpcpm-details__when">(an Administrator checks first)</span></summary>' ),
		has( $member_checked, '>Send to an Administrator</button>' ),
		has( $member_checked, 'data-wpcpm-busy="Sending"' ),
		has( $member_checked, 'Save the details' ),
		has( $member_checked, 'You are an Administrator' ),
	),
	array( true, true, true, false, false )
);

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
ck( 'the forms drawn are the two groups\' and the withdrawal\'s', array_keys( $classes ), array( 'wpcpm-details__form', 'wpcpm-details__withdraw' ) );
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
ck( 'they read, under the second group, that the request waits and where it is decided, in the plan\'s words', array( has( html_entity_decode( strip_tags( $admin ), ENT_QUOTES ), $decide ), strpos( $admin, 'Name and address' ) < strpos( $admin, 'wpcpm-details__decide' ) ), array( true, true ) );
ck( 'the place is linked to the requests card of the Administrator Dashboard', has( $admin, '<p class="wpcpm-details__decide">A request from the school is waiting. Decide it in <a href="https://example.test/administrator-dashboard/#wpcpm-requests">Requests from institutions on the Administrator Dashboard</a>.</p>' ), true );
WPCPM_Administrators_Dashboard::$url = '';
$plain_admin = draw( 1 );
ck( 'and plain while that page is not published', array( has( $plain_admin, '<p class="wpcpm-details__decide">' . $decide . '</p>' ), has( $plain_admin, '<a href=' ) ), array( true, false ) );
WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';
ck( 'the school\'s own accounts are told nothing of the kind: they read the waiting lines and Withdraw it', array( has( draw( 8 ), 'wpcpm-details__decide' ), has( draw( 8 ), 'Withdraw it' ) ), array( false, true ) );
ck( 'nor is a school with no request waiting: the Administrator is offered the form', array( has( disclosure( draw( 1, B ), 'checked' ), '<input type="hidden" name="action" value="wpcpm_ask_details" />' ), has( draw( 1, B ), 'wpcpm-details__decide' ) ), array( true, false ) );

$mails_before = count( $GLOBALS['mail'] );
$logged       = count( audit( A, 'details_saved' ) );
$out          = post_as( 1, 'checked', array( 'wpcpm_details_name' => 'Written By An Administrator' ) );
$page         = draw( 1 );
ck( 'an Administrator\'s post of the second group while it waits is refused, in the same words, and sent back to the card', array( $out, has( $page, '<p class="wpcpm-details__message is-error" role="status">' . $decide . '</p>' ) ), array( manager_back(), true ) );
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
ck( 'a refused ask is sent back to the card', $out, member_back() );
ck( 'nothing was made, written or sent', array( requests(), $GLOBALS['patched'], $GLOBALS['mail'], audit( A, 'request_raised' ) ), array( array(), array(), array(), array() ) );
ck( 'the form is open again, holding what was typed, the refused fields included', array( is_open( $form ), box( $form, 'wpcpm_details_name' ), box( $form, 'wpcpm_details_city' ), box( $form, 'wpcpm_details_contact_email' ) ), array( true, 'Valencia University', '', 'not-an-email' ) );
ck( 'each refused field shows its problem under it, in the application\'s words', array( has( $form, '<span class="wpcpm-field__problem" id="wpcpm-details-city-problem">Please fill this in.</span>' ), has( $form, '<span class="wpcpm-field__problem" id="wpcpm-details-contact_email-problem">That does not look like an email address.</span>' ), has( $form, 'wpcpm-details-name-problem' ) ), array( true, true, false ) );
ck( 'and only those two are marked invalid, so a screen reader is told', substr_count( $form, 'aria-invalid="true"' ), 2 );
ck( 'the line above says nothing was sent', has( $page, '<p class="wpcpm-details__message is-error" role="status">Nothing was sent' ), true );
$again = disclosure( draw( 7 ), 'checked' );
ck( 'and the typing is given back once', array( has( $again, 'Valencia University' ), is_open( $again ), box( $again, 'wpcpm_details_name' ) ), array( false, false, 'Universidad Ejemplo' ) );

fresh();
$out  = post_as( 7, 'now', array( 'wpcpm_details_contact_role' => '', 'wpcpm_details_contact_person' => 'Contact Two', 'wpcpm_details_student_languages' => array( 'French' ) ) );
$page = draw( 7 );
$form = disclosure( $page, 'now' );
ck( 'a refused save keeps every typed value of the first group too', array( $out, box( $form, 'wpcpm_details_contact_person' ), box( $form, 'wpcpm_details_contact_role' ), ticked( $form ) ), array( member_back(), 'Contact Two', '', array( 'French' ) ) );
ck( 'with the required role refused under its box, and nothing written', array( has( $form, '<span class="wpcpm-field__problem" id="wpcpm-details-contact_role-problem">Please fill this in.</span>' ), $GLOBALS['patched'], $GLOBALS['records'][ A ]['contact_role'], $GLOBALS['mail'], audit( A, 'details_saved' ) ), array( true, array(), 'Head of the international office', array(), array() ) );
ck( 'the line says nothing was saved', has( $page, 'Nothing was saved' ), true );

fresh();
post_as( 7, 'now', array( 'wpcpm_details_contact_role' => '', 'wpcpm_details_student_languages' => array( 'French', 'Klingon' ) ) );
ck( 'a language the application does not offer is not kept with the typing', $GLOBALS['umeta'][7]['wpcpm_flash']['typed:details:' . A . ':now']['typed']['student_languages'] ?? null, array( 'French' ) );

fresh();
$out = post_as( 7, 'now', array(), A, array( 'wpcpm_details_student_languages' => array() ) );
ck( 'the languages are required: none ticked is refused', array( $out, has( disclosure( draw( 7 ), 'now' ), 'id="wpcpm-details-student_languages-problem"' ), $GLOBALS['patched'], $GLOBALS['mail'] ), array( member_back(), true, array(), array() ) );

fresh();
$out = post_as( 7, 'now', array( 'wpcpm_details_signer_name' => '', 'wpcpm_details_signer_title' => 'Dean of Engineering' ) );
ck( 'a title with no name beside it is refused as the application refuses it', array( $out, has( disclosure( draw( 7 ), 'now' ), 'id="wpcpm-details-signer_name-problem"' ), $GLOBALS['records'][ A ]['signer_name'] ), array( member_back(), true, 'Signer One' ) );

fresh();
$out  = post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact <12 and >18' ) );
$page = draw( 7 );
ck( 'a text the cleaner would take words from is refused whole, in the sentence for the box', array( $out, has( $page, esc_html( WPCPM_Typed_Text::loss_message( 'Name of the person we should contact' ) ) ), $GLOBALS['patched'], $GLOBALS['index'][ A ]['contact_person'], $GLOBALS['mail'] ), array( member_back(), true, array(), 'Contact One', array() ) );
ck( 'and the box gets back what was typed, exactly', box( disclosure( $page, 'now' ), 'wpcpm_details_contact_person' ), 'Contact <12 and >18' );
fresh();
post_as( 7, 'checked', array( 'wpcpm_details_city' => 'Valencia <3 and >' ) );
ck( 'the second form says its refusal in the words of a send', has( draw( 7 ), esc_html( WPCPM_Typed_Text::loss_message( 'City', 'send' ) ) ), true );

fresh();
post_as( 7, 'checked', array( 'wpcpm_details_website' => 'javascript:alert(1)', 'wpcpm_details_country' => 'recXXXXXXXXXXXXXX' ) );
$form = disclosure( draw( 7 ), 'checked' );
ck( 'a web address of another scheme and a country the map does not offer are refused with the application\'s sentences', array( has( $form, 'id="wpcpm-details-website-problem"' ), has( $form, 'id="wpcpm-details-country-problem">Please choose one of the options offered.' ), requests() ), array( true, true, array() ) );

fresh();
post_as( 7, 'now', array( 'wpcpm_details_name' => 'Smuggled', 'wpcpm_details_contact_role' => 'Dean of Studies' ) );
ck( 'a key of the other group posted to a form is not read', array( $GLOBALS['index'][ A ]['name'], $GLOBALS['records'][ A ]['contact_role'] ), array( 'Universidad Ejemplo ', 'Dean of Studies' ) );

echo "\n=== Review Focus 4: Airtable refuses a contact person's save ===\n";

fresh();
$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'Airtable said 422' );
$out                 = post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact Two', 'wpcpm_details_contact_role' => 'Dean of Studies', 'wpcpm_details_student_languages' => array( 'French', 'Spanish' ) ) );
$page                = draw( 7 );
$form                = disclosure( $page, 'now' );
ck( 'the member is sent back to the card', $out, member_back() );
ck( 'what the base answered is not said to the school', has( $page, 'Airtable said 422' ), false );
ck( 'the sentence says so', has( $page, '<p class="wpcpm-details__message is-error" role="status">The program records could not be updated, so nothing was saved. Please try again in a while.</p>' ), true );
ck( 'nothing was written on the site: not the index, not the record', array( $GLOBALS['index'][ A ]['contact_person'], $GLOBALS['records'][ A ]['contact_role'], $GLOBALS['records'][ A ]['student_languages'], in_array( 'index', $GLOBALS['events'], true ), in_array( 'record', $GLOBALS['events'], true ) ), array( 'Contact One', 'Head of the international office', array( 'Spanish', 'English' ), false, false ) );
ck( 'no history row and no email', array( audit( A, 'details_saved' ), $GLOBALS['mail'] ), array( array(), array() ) );
ck( 'every typed value comes back, the ones that were not Airtable\'s too', array( has( $form, ' open' ), box( $form, 'wpcpm_details_contact_person' ), box( $form, 'wpcpm_details_contact_role' ), ticked( $form ) ), array( true, 'Contact Two', 'Dean of Studies', array( 'French', 'Spanish' ) ) );
ck( 'and the lists above still read what the program has', array( rows( $page )['Contact person'], rows( $page )['Their role'] ), array( 'Contact One', 'Head of the international office' ) );

fresh();
$GLOBALS['airtable'] = array();
post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact Two' ) );
ck( 'an answer of nothing written is a refusal too', array( has( draw( 7 ), 'The program records could not be updated, so nothing was saved. Please try again in a while.' ), $GLOBALS['index'][ A ]['contact_person'], $GLOBALS['mail'] ), array( true, 'Contact One', array() ) );

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
ck( 'and the page says "Nothing was changed."', has( draw( 7 ), '<p class="wpcpm-details__message is-info" role="status">Nothing was changed.</p>' ), true );

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
ck( 'and says "Nothing was changed."', array( $out, has( draw( 7 ), '<p class="wpcpm-details__message is-info" role="status">Nothing was changed.</p>' ) ), array( member_back(), true ) );
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
ck( 'a save that finds the school\'s lock held is told someone else is saving the details, and saves, records and sends nothing', array( $out, has( $page, '<p class="wpcpm-details__message is-error" role="status">' . $saving . '</p>' ), $GLOBALS['patched'], $GLOBALS['index'][ A ]['contact_person'], audit( A, 'details_saved' ), $GLOBALS['mail'] ), array( member_back(), true, array(), 'Contact One', array(), array() ) );
ck( 'its typing comes back, and the lock it found is left to its holder', array( box( disclosure( $page, 'now' ), 'wpcpm_details_contact_person' ), $GLOBALS['opts'][ 'wpcpm_req_lock_details_' . A ] ?? null ), array( 'Contact Two', (string) ( time() + 300 ) ) );
ck( 'in a status of its own, not the deciding sentence of a request', array( $flashed['status'] ?? null, has( $page, 'deciding this request' ) ), array( 'saving-busy', false ) );

fresh();
$GLOBALS['opts']['wpcpm_req_lock_details_' . A ] = (string) ( time() + 300 );
$out  = post_as( 7, 'checked', array( 'wpcpm_details_city' => 'Valencia' ) );
$page = draw( 7 );
$form = disclosure( $page, 'checked' );
ck( 'a request sent while the school\'s lock is held is told the same, and nothing is made or sent', array( $out, has( $page, '<p class="wpcpm-details__message is-error" role="status">' . $saving . '</p>' ), requests(), $GLOBALS['mail'], audit( A, 'request_raised' ), has( $page, 'One change is already waiting' ) ), array( member_back(), true, array(), array(), array(), false ) );
ck( 'and its typing comes back in the open form, the lock left to its holder', array( is_open( $form ), box( $form, 'wpcpm_details_city' ), $GLOBALS['opts'][ 'wpcpm_req_lock_details_' . A ] ?? null ), array( true, 'Valencia', (string) ( time() + 300 ) ) );

fresh();
post_as( 7, 'checked', array( 'wpcpm_details_city' => 'Valencia' ) );
$out  = post_as( 8, 'checked', array( 'wpcpm_details_name' => 'Valencia University' ) );
$page = draw( 8 );
ck( 'a request that truly waits is still what a colleague\'s ask is told, with nothing made', array( $out, has( $page, '<p class="wpcpm-details__message is-error" role="status">One change is already waiting for an Administrator.</p>' ), has( $page, 'saving your institution' ), count( requests() ) ), array( member_back(), true, false, 1 ) );

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

echo "\n=== The words the plan gives ===\n";

$words = WPCPM_Institution_Details_Card::messages();
ck( 'the outcome sentences are the plan\'s', array( $words['saved'], $words['asked'], $words['nothing'], $words['airtable'] ), array(
	array( 'success', 'Your institution\'s details are saved.' ),
	array( 'success', 'Sent: an Administrator checks the change, and you will see it here once it is decided.' ),
	array( 'info', 'Nothing was changed.' ),
	array( 'error', 'The program records could not be updated, so nothing was saved. Please try again in a while.' ),
) );
ck( 'and none of them holds a dash', preg_match( '/[\x{2013}\x{2014}]/u', json_encode( $words, JSON_UNESCAPED_UNICODE ) ), 0 );

echo "\n=== The stylesheet draws the card with the page's own rules ===\n";

$css_raw = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' );
$css     = (string) preg_replace( '#/\*.*?\*/#s', '', $css_raw );
preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER );
$headed = array_values( array_filter( $rules, function ( $rule ) { return false !== strpos( $rule[1], '.wpcpm-details__subtitle' ); } ) );
ck( 'the card\'s small headings take the sub-heading rule the People card draws its own with, and have no rule of their own', array( count( $headed ), 1 === count( $headed ) && false !== strpos( $headed[0][1], '.wpcpm-people__subtitle' ) ), array( 1, true ) );
$notice = array_values( array_filter( $rules, function ( $rule ) { return '.wpcpm-details__row .wpcpm-details__waiting' === trim( $rule[1] ); } ) );
ck( 'the waiting line has the page\'s warning tone from its class and draws no border or color of its own', array( count( $notice ), isset( $notice[0] ) ? preg_match( '/border|background|color|#[0-9a-f]{3,6}/i', $notice[0][2] ) : null ), array( 1, 0 ) );
ck( 'and the card\'s own section of the stylesheet is in US English', preg_match( '/colour/i', substr( $css_raw, (int) strpos( $css_raw, 'The school\'s details card (Institutions release 2)' ) ) ), 0 );

echo "\n=== A school reads none of the base's words ===\n";

// Every sentence the card can say to a school, the withdrawal's and the Administrator-facing note included.
foreach ( array_merge( WPCPM_Institution_Details_Card::messages(), WPCPM_Institution_Request::school_messages() ) as $status => $message ) {
	words_in( $message[1] );
}
words_in( $decide );
ck( 'the card holds none of them, in any state this suite draws it in or any sentence it can say', array_values( $GLOBALS['base_words'] ), array() );
ck( 'and the refusal a school reads when the base turns a save down names the program\'s records instead', WPCPM_Institution_Details_Card::messages()['airtable'], array( 'error', 'The program records could not be updated, so nothing was saved. Please try again in a while.' ) );

// The Institution Dashboard's suite holds this card to its rule by running this suite once more and reading the
// last line it prints (`bin/test-institutions-dashboard.php`).
if ( isset( $argv[1] ) && 'words' === $argv[1] ) {
	echo 'WORDS:' . json_encode( array_values( $GLOBALS['base_words'] ) ) . "\n";
}

echo $fail ? "\n{$fail} FAILURE(S)\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
