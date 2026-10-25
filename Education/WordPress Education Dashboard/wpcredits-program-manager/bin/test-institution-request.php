<?php
/**
 * The request queue, and its one shipped kind: a mentor is wanted.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - Section 13 is the boundary: the queue records that a mentor is wanted and does not
 *   negotiate one, so nothing here accepts, matches, or writes to Airtable. The suite reads
 *   the class for an Airtable call and expects none.
 * - **The institution is never read from the form.** A member of B posting a member of A's
 *   student is decided against A, is not a member of A, and gets the one refusal, byte for
 *   byte the same one an unknown record gets. That is the whole fence for raising.
 * - The nonce is keyed to the student the request is about, so a token for asking after one
 *   student is not a token for asking after another.
 * - One open row per student per kind: a second press finds the open one and says so, and
 *   the store refuses a duplicate at `raise()` as well, because the import comes in that way.
 *   **Including behind a ceiling's worth of other open rows**, because the lookup that holds
 *   that invariant is the only thing bounding what a member can raise.
 * - `open_for()` answers about one kind, one student and one institution, and about the open
 *   ones: each of those four filters has a row it must not return.
 * - The manager's handler runs the capability, then the nonce, then `decide()` (spec 5.4),
 *   and the order assertions fail when one of the three is missing rather than passing on
 *   `false < 12`. Both handlers refuse on the decision's own answer, which is read out of the
 *   file because a refused decision carries no institution and the check under it would
 *   refuse the same request in the same words.
 * - A row is overdue after `OVERDUE_DAYS`, which is the design spec's fourteen and not the
 *   review queue's three, and a closed row is never overdue.
 * - Both closed states are terminal. Two managers pressing the same button seconds apart
 *   means the second is told the row is already closed rather than overwriting the first.
 * - Every state change writes one audit row carrying the decision's ground.
 * - `open_requests()` is oldest first, because that is the order a queue is worked in, and
 *   bounded whatever it is asked for, because it is read by a card on every screen load.
 * - The store holds three kinds from the day it exists, so `add` and `format` are a handler
 *   and a label later and never a migration.
 * - A closing note the cleaner would take words from is refused before the row or its audit
 *   entry is written, in the words of the button pressed, Mark as handled or Decline. The note
 *   is counted as typed, so one that fits its box is kept whole and one over the limit is
 *   refused, never cut, and the box gets back what was typed, for that request and that manager,
 *   once. Core's cleaners stand in as 7.1.2 writes them (bin/stubs/cleaners.php).
 *
 * - **A school's details request** (Institutions release 2) is a fourth kind, `details`, about no
 *   student, with a fourth state, `withdrawn`. One is open per institution at a time, and raising it
 *   audits, and emails "Who is emailed about institutions" with a link to the Administrator Dashboard's
 *   requests card. The Administrator's Approve writes the asked values once, through the details
 *   class (Airtable first, the site after), closes the request, audits and tells the asking account;
 *   an Airtable refusal leaves it open, with its sentence. Decline needs a note of 20 to 2000
 *   characters, writes nothing to Airtable, and tells the asking account the note. The school's
 *   Withdraw closes it as withdrawn, and a decision on a withdrawn request is refused.
 * - **A decision is made once.** Capability, then the nonce, then the policy, and only then the
 *   request's lock, with the request's state read again under it (this request's copy of its meta
 *   dropped first). Approve and Decline posted together leave one Airtable write, one email and one
 *   closing row, and a lock another request holds is a refusal that does nothing.
 * - **"On record now" is read at draw time.** The facts of an open details request hold the value the
 *   index has now beside the value asked for, so a detail the program team changed in Airtable since
 *   the school asked shows as it is, and Approve still writes what was asked.
 * - The real details class, the application's cleaners, the templates and the lock run here; stood in
 *   for at their contracts are the settings, the Airtable client (journaled), the mail layer (every
 *   send kept with its built subject and body), the index, the institution's record, and the two
 *   dashboards' addresses.
 *
 * Run from the plugin root:  php bin/test-institution-request.php
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
$GLOBALS['referer']     = array();
$GLOBALS['back']        = '';
$GLOBALS['queries']     = array();
$GLOBALS['settings']    = array();
$GLOBALS['records']     = array();
$GLOBALS['patched']     = array();
$GLOBALS['airtable']    = true;
$GLOBALS['mail']        = array();
$GLOBALS['events']      = array();

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
	// post_modified_gmt is declared, not left dynamic: PHP deprecates an undeclared property
	// created by assignment, and the closed-requests fixtures set this one directly on the
	// stored post object.
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
// Core's two cleaners and its decoder as 7.1.2 writes them, which the typed-text rules read: a
// closing note the cleaner would take words from is refused, and a stand-in on `strip_tags()` alone
// writes no `&lt;` and would pass every check made with it.
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
// What the application's cleaners reach for, which the details class answers a typed answer and a stored
// one with.
function sanitize_email( $s ) { return trim( (string) $s ); }
function is_email( $s ) { return (bool) preg_match( '/^[^@\s]+@[^@\s.]+\.[^@\s]+$/', (string) $s ); }
function esc_url_raw( $u, $protocols = null ) { return preg_match( '#^https?://[^\s<>"]+$#i', (string) $u ) ? (string) $u : ''; }
// The request's own copy of a post's meta, which a decision drops once it holds the lock; kept in the
// order of events so a check can read that it was dropped there.
function wp_cache_delete( $key, $group = '' ) { $GLOBALS['events'][] = 'cache ' . $group . ' ' . $key; return true; }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action( $h, $c = null, $p = 10, $n = 1 ) { $GLOBALS['hooks'][] = $h; }
function add_filter() {}
function register_post_type( $type, $args = array() ) { $GLOBALS['registered'] = array( $type, $args ); }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function human_time_diff( $a, $b = 0 ) { return '4 days'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = stripslashes_deep( $v ); return true; }
// Core's own, beside meta stand-ins that unslash what they are handed as core's do: the flash and
// every write of words hand them a slashed copy, and it reads back as it was given.
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function stripslashes_deep( $v ) { return is_array( $v ) ? array_map( 'stripslashes_deep', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_current_user_id() { return $GLOBALS['uid']; }
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
	// The words unslashed, as core's insert unslashes them: the audit row's message arrives slashed.
	$post->post_title              = stripslashes( (string) ( $a['post_title'] ?? '' ) );
	$post->post_content            = stripslashes( (string) ( $a['post_content'] ?? '' ) );
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'publish';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	// One second apart, in the order they were written, so "oldest first" is a question the
	// stub can actually answer and a test can move a row's date to disturb.
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
// As core's: what is written is unslashed first, at any depth.
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( stripslashes_deep( $value ) ); return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ); return true; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
/**
 * One post against a meta query: `IN`, `EXISTS`, `NOT EXISTS` and `=`, ANDed.
 *
 * Every meta query this class builds is one flat AND, the closed-requests reader's two
 * included: it asks for the stamped rows and the unstamped ones apart rather than in one
 * `OR` group, because a group is what let an unstamped row be ordered by an alias nothing
 * had pinned (deep check FADMN-3, and `closed_sort_key()` below).
 *
 * @param int   $post_id The post.
 * @param array $query   A meta query.
 * @return bool
 */
function meta_matches( $post_id, array $query ) {
	foreach ( $query as $key => $clause ) {
		if ( 'relation' === $key || ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
		$value   = $GLOBALS['pmeta'][ (int) $post_id ][ $clause['key'] ][0] ?? null;
		$compare = isset( $clause['compare'] ) ? strtoupper( (string) $clause['compare'] ) : '=';
		if ( 'EXISTS' === $compare ) {
			$match = null !== $value;
		} elseif ( 'NOT EXISTS' === $compare ) {
			$match = null === $value;
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

/**
 * What MySQL compares when a query orders by the `closed` meta clause.
 *
 * The SQL is `CAST( alias.meta_value AS SIGNED )`, and the alias is pinned to the closing
 * stamp only while the clause is ANDed at the top level. Put it in an `OR` group beside a
 * `NOT EXISTS` branch and the join is left unconstrained, so a row with no stamp brings
 * every meta row it has into the group and MySQL casts whichever one it hands back - a
 * manager's note that opens with a long number is enough to sort a request closed last year
 * above this morning's work (deep check FADMN-3).
 *
 * So: the stamp when the row has one, and otherwise the largest number any of that post's
 * meta values casts to, which is one of the answers MySQL is free to give. A query that
 * orders unstamped rows by this clause is sorted here the way its worst load would sort it.
 *
 * @param int $post_id The post.
 * @return int
 */
function closed_sort_key( $post_id ) {
	$meta = $GLOBALS['pmeta'][ (int) $post_id ] ?? array();

	if ( isset( $meta['_wpcpm_req_closed_at'][0] ) ) {
		return (int) $meta['_wpcpm_req_closed_at'][0];
	}

	$worst = 0;

	foreach ( $meta as $values ) {
		$worst = max( $worst, (int) ( $values[0] ?? 0 ) );
	}

	return $worst;
}

/**
 * `get_posts()` for one post type, with a meta query of one or more clauses.
 *
 * Matched the way MySQL collates - case-insensitively - so that the class's own `strcmp()`
 * re-check has something to catch. Ordered by date and then ID, in the direction the caller
 * asked for, because "oldest first" is one of the things being asserted. Every call is kept
 * in `$GLOBALS['queries']`, so a check can say that a second query was not made.
 */
function get_posts( $a = array() ) {
	$GLOBALS['queries'][] = $a;
	$out                  = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ( $a['post_type'] ?? '' ) !== $post->post_type ) { continue; }
		$status = $a['post_status'] ?? 'publish';
		if ( 'any' !== $status && $status !== $post->post_status ) { continue; }
		if ( ! meta_matches( $post->ID, (array) ( $a['meta_query'] ?? array() ) ) ) { continue; }
		$out[] = $post;
	}
	// `date` is the ordinary order; `closed` is the named meta clause the stamped half of the
	// closed-requests reader orders by, and `closed_sort_key()` is what the join really hands
	// MySQL to cast (deep check FADMN-3). The legacy half asks for `date` instead, because a
	// row with no stamp has no other date. The ID breaks a tie in both.
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
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['referer'][] = $a; return true; }
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function wp_get_referer() { return $GLOBALS['back']; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect:' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die:' . $m ); }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
		public static function is_record_id( $v ) { return (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $v ) ); }
	}
}
if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	/** Only what the roster's `cached_subject()` reaches for on the report branch. */
	class WPCPM_Students_Sync {
		const META_INSTITUTION = 'wpcpm_student_institution';
		public static function user_for_record( $record ) { return null; }
	}
}
if ( ! class_exists( 'WPCPM_Institutions_Index' ) ) {
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
	}
}
if ( ! class_exists( 'WPCPM_Roster_Index' ) ) {
	/** Contract: the per-institution rows, and the counts the roster walks to find one. */
	class WPCPM_Roster_Index {
		public static function rows( $id ) { return $GLOBALS['rosters'][ trim( (string) $id ) ] ?? array(); }
		public static function read( $id ) { return array( 'v' => 1, 'read' => 1756000000, 'rows' => self::rows( $id ) ); }
		public static function counts() {
			$institutions = array();
			foreach ( $GLOBALS['rosters'] as $record => $rows ) { $institutions[ $record ] = array(); }
			return array( 'v' => 1, 'read' => 1756000000, 'institutions' => $institutions, 'reconciliation' => array() );
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	/** Contract: the only thing the policy's member ground asks of the members module. */
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
}
if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	/** Contract: the gate the member ground applies, and nothing else is read from here. */
	class WPCPM_Institution_Agreement {
		public static function is_settled( $id ) { return in_array( $id, $GLOBALS['settled'], true ); }
	}
}
if ( ! class_exists( 'WPCPM_Institutions' ) ) {
	/**
	 * Contract: the flash channel the manager screen reads its outcomes from, and the one way the
	 * program managers are told something: the addresses of "Who is emailed about institutions", an
	 * account's own send for one that belongs to an account and a bare address's for the rest.
	 */
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
}
if ( ! class_exists( 'WPCPM_Settings' ) ) {
	/** Contract: the settings array and one value of it. */
	class WPCPM_Settings {
		public static function get() { return $GLOBALS['settings']; }
		public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
	}
}
if ( ! class_exists( 'WPCPM_Mail' ) ) {
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
}
if ( ! class_exists( 'WPCPM_Airtable' ) ) {
	/** The client, journaled: what it was asked to PATCH, and what it answers (the global `airtable`). */
	class WPCPM_Airtable {
		public function __construct( $settings = null ) {}
		public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
		public function update_records( $table, array $records ) {
			$GLOBALS['events'][]  = 'airtable';
			$GLOBALS['patched'][] = array( $table, $records );
			if ( true !== $GLOBALS['airtable'] ) { return $GLOBALS['airtable']; }
			return array( $records[0]['id'] => true );
		}
	}
}
if ( ! class_exists( 'WPCPM_Institution_Record' ) ) {
	/** Contract: the site's own answers per institution, which the checked details never touch. */
	class WPCPM_Institution_Record {
		public static function get( $record ) { return $GLOBALS['records'][ trim( (string) $record ) ] ?? array(); }
		public static function set( $record, array $answers ) { $GLOBALS['records'][ trim( (string) $record ) ] = array_merge( self::get( $record ), $answers ); return true; }
	}
}
if ( ! class_exists( 'WPCPM_Administrators_Dashboard' ) ) {
	/** Contract: the Administrator Dashboard's page, which an email links to. */
	class WPCPM_Administrators_Dashboard {
		public static $url = 'https://example.test/administrator-dashboard/';
		public static function page_url() { return self::$url; }
	}
}
if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
	/** Contract: the Institution Dashboard's page, which a decline's email sends a school back to. */
	class WPCPM_Institutions_Dashboard {
		public static $url = 'https://example.test/institution-dashboard/';
		public static function page_url() { return self::$url; }
	}
}

// The lock a decision is made under, over the options table the suites keep in `$GLOBALS['opts']`.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';
require_once __DIR__ . '/stubs/options-lock-wpdb.php';
// The details class and what it cleans with, and the registry the emails are filled from.
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
/** Run a handler and report how it ended: a redirect, a wp_die, or nothing at all. */
function run( $method ) {
	try {
		call_user_func( array( 'WPCPM_Institution_Request', $method ) );
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
	return '';
}
/** Every request post now in the store, oldest first, as arrays of their meta. */
function stored() {
	$rows = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( 'wpcpm_inst_request' !== $post->post_type ) { continue; }
		$rows[ $id ] = array(
			'kind'        => get_post_meta( $id, '_wpcpm_req_kind', true ),
			'institution' => get_post_meta( $id, '_wpcpm_req_institution', true ),
			'student'     => get_post_meta( $id, '_wpcpm_req_student', true ),
			'state'       => get_post_meta( $id, '_wpcpm_req_state', true ),
			'note'        => get_post_meta( $id, '_wpcpm_req_note', true ),
			'actor'       => get_post_meta( $id, '_wpcpm_req_actor', true ),
		);
	}
	return $rows;
}
/** The audit rows about one institution, newest first, as the log stores them. */
function log_for( $record ) { return WPCPM_Institution_Audit::entries_for( $record ); }
/**
 * One open row written straight into the store, without `raise()`'s audit entry.
 *
 * The invariant block below needs more open rows than any window the lookup could carry, and
 * two hundred audit entries would drown the log the assertions above read. What is under test
 * there is the lookup rather than the writer, and the writer has its own block.
 *
 * @param string $kind        One of `kinds()`.
 * @param string $institution Institutions record ID.
 * @param string $student     Students record ID.
 * @return int The post ID.
 */
function seed_open( $kind, $institution, $student ) {
	$id = wp_insert_post(
		array(
			'post_type'   => WPCPM_Institution_Request::POST_TYPE,
			'post_status' => WPCPM_Institution_Request::POST_STATUS,
			'post_author' => 0,
		)
	);

	update_post_meta( $id, WPCPM_Institution_Request::META_KIND, $kind );
	update_post_meta( $id, WPCPM_Institution_Request::META_INSTITUTION, $institution );
	update_post_meta( $id, WPCPM_Institution_Request::META_STUDENT, $student );
	update_post_meta( $id, WPCPM_Institution_Request::META_STATE, WPCPM_Institution_Request::STATE_OPEN );
	update_post_meta( $id, WPCPM_Institution_Request::META_NOTE, '' );
	update_post_meta( $id, WPCPM_Institution_Request::META_ACTOR, 0 );

	return (int) $id;
}
/** How many rows the store holds for one institution, of any kind and any state. */
function stored_for( $institution ) {
	$rows = array_filter(
		stored(),
		static function ( $row ) use ( $institution ) {
			return $row['institution'] === $institution;
		}
	);

	return count( $rows );
}
function flash_for( $uid, $channel ) { return $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash'][ $channel ] ?? null; }
function clear_flash( $uid ) { unset( $GLOBALS['umeta'][ (int) $uid ]['wpcpm_flash'] ); }
/** One queue row's decisions, drawn for one reader. */
function render_decisions( $viewer, $post_id ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_Request::render_decisions( $post_id );
	return (string) ob_get_clean();
}
/**
 * Whether one call comes before another in a method body, with both of them present.
 *
 * `strpos()` answers a missing needle with false, and `false < 12` is true in PHP, so an
 * order assertion written the obvious way goes on saying ok for a method that has lost the
 * very check it was meant to pin. Absence is the answer no, whichever of the two is missing.
 */
function before( $body, $first, $second ) {
	$one = strpos( (string) $body, $first );
	$two = strpos( (string) $body, $second );

	return false !== $one && false !== $two && $one < $two;
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

$A  = 'recSEED0000000001'; // the TEST institution in the seed fixture.
$B  = 'recSEED0000000002';
$S1 = 'recS0000000000001'; // A's student, no mentor.
$S2 = 'recS0000000000002'; // A's student, with a mentor.
$S3 = 'recS0000000000003'; // B's student, no mentor.
$S9 = 'recS0000000000009'; // well-formed, on nobody's roster.

$GLOBALS['index'] = array(
	$A => array( 'record_id' => $A, 'name' => 'TEST - Institution 20 ', 'stage' => 'Confirmed', 'country' => 'recPL000000000001', 'country_name' => 'Poland' ),
	$B => array( 'record_id' => $B, 'name' => 'Universidad Example', 'stage' => 'Confirmed', 'country' => 'recCR000000000001', 'country_name' => 'Costa Rica' ),
);

$GLOBALS['rosters'] = array(
	$A => array(
		$S1 => array( 'record_id' => $S1, 'name' => 'Anna Nowak', 'email' => 'anna@example.test', 'status' => 'Accepted', 'institution' => $A, 'has_mentor' => false, 'reports' => array(), 'user_id' => 21 ),
		$S2 => array( 'record_id' => $S2, 'name' => 'Bea Kowal', 'email' => 'bea@example.test', 'status' => 'Accepted', 'institution' => $A, 'has_mentor' => true, 'reports' => array( 'recR0000000000001' ), 'user_id' => 22 ),
	),
	$B => array(
		$S3 => array( 'record_id' => $S3, 'name' => 'Carla Vega', 'email' => 'carla@example.test', 'status' => 'Accepted', 'institution' => $B, 'has_mentor' => false, 'reports' => array(), 'user_id' => 23 ),
	),
);

$GLOBALS['users'] = array(
	1 => new WP_User( 1, 'Manager', 'manager@example.test', array( 'administrator' ) ),
	2 => new WP_User( 2, 'Second Manager', 'manager2@example.test', array( 'administrator' ) ),
	7 => new WP_User( 7, 'Anna Kowalska', 'anna.k@example.test', array( 'subscriber' ) ),
	9 => new WP_User( 9, 'Cleo Beta', 'cleo@example.test', array( 'subscriber' ) ),
);
$GLOBALS['manage']      = array( 1, 2 );
$GLOBALS['memberships'] = array( 7 => array( $A ), 9 => array( $B ) );
$GLOBALS['settled']     = array( $A, $B );
$GLOBALS['back']        = 'https://example.test/institution-dashboard/?wpcpm_institution_student=21';

/* ---- the store ----------------------------------------------------------- */

echo "=== The store holds three kinds and one open row per student ===\n";

ck( 'the three kinds exist from the day the store does, and a school\'s details request is the fourth', WPCPM_Institution_Request::kinds(), array( 'add', 'mentor', 'format', 'details' ) );
ck( 'and four states: a school may withdraw what it asked', WPCPM_Institution_Request::states(), array( 'open', 'done', 'declined', 'withdrawn' ) );
ck( 'all three closed states are terminal', WPCPM_Institution_Request::transitions(), array(
	'open'      => array( 'done', 'declined', 'withdrawn' ),
	'done'      => array(),
	'declined'  => array(),
	'withdrawn' => array(),
) );
ck( 'an open row may be handled, declined or withdrawn', array(
	WPCPM_Institution_Request::can_settle( 'open', 'done' ),
	WPCPM_Institution_Request::can_settle( 'open', 'declined' ),
	WPCPM_Institution_Request::can_settle( 'open', 'withdrawn' ),
), array( true, true, true ) );
ck( 'and nothing may follow a closed one, or precede an open one', array(
	WPCPM_Institution_Request::can_settle( 'done', 'open' ),
	WPCPM_Institution_Request::can_settle( 'declined', 'done' ),
	WPCPM_Institution_Request::can_settle( 'withdrawn', 'done' ),
	WPCPM_Institution_Request::can_settle( 'withdrawn', 'declined' ),
	WPCPM_Institution_Request::can_settle( 'done', 'withdrawn' ),
	WPCPM_Institution_Request::can_settle( 'open', 'open' ),
	WPCPM_Institution_Request::can_settle( '', 'done' ),
), array( false, false, false, false, false, false, false ) );
ck( 'the kinds are named for a person, and the new one for what it asks', array(
	WPCPM_Institution_Request::kind_label( 'details' ),
	WPCPM_Institution_Request::kind_label( 'mentor' ),
	WPCPM_Institution_Request::kind_label( 'reenrol' ),
), array( 'A change to its details', 'A mentor is wanted', 'An unrecorded kind of request' ) );

$bad = array(
	WPCPM_Institution_Request::raise( 'reenrol', $A, $S1, 7 ),
	WPCPM_Institution_Request::raise( 'mentor', 'not-a-record', $S1, 7 ),
	WPCPM_Institution_Request::raise( 'mentor', $A, '', 7 ),
);
ck( 'raise() refuses an unknown kind, an unknown institution and a missing student', array_map( static function ( $e ) {
	return is_wp_error( $e ) ? $e->get_error_code() : $e;
}, $bad ), array( 'wpcpm_request_kind', 'wpcpm_request_institution', 'wpcpm_request_student' ) );
ck( 'and none of them wrote a row', stored(), array() );

/* ---- the school's side is gone ------------------------------------------ */

echo "=== A school can no longer ask for a mentor ===\n";

// Institutions release 2 retires the school's side of "Ask for a mentor": the block on a student's
// card, its form and its handler. A mentor is optional in the program now, and a card offering one
// to every student without one was a question the program is not sure it can answer.
ck( 'the class has none of the four methods the school side was made of', array_map( static function ( $method ) {
	return method_exists( 'WPCPM_Institution_Request', $method );
}, array( 'render_student', 'render_form', 'render_waiting', 'handle_request' ) ), array( false, false, false, false ) );
ck( 'nor the action it posted to, nor the channel it flashed on', array(
	defined( 'WPCPM_Institution_Request::ACTION_REQUEST' ),
	defined( 'WPCPM_Institution_Request::FLASH' ),
), array( false, false ) );
ck( 'nor the two methods that only that flow used', array(
	method_exists( 'WPCPM_Institution_Request', 'message_for' ),
	method_exists( 'WPCPM_Institution_Request', 'bounce' ),
), array( false, false ) );

$src_all = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-request.php' );
ck( 'and the file never names the hook or the button', array(
	has( $src_all, 'wpcpm_request_mentor' ),
	has( $src_all, 'Ask for a mentor' ),
	has( $src_all, 'Asking for a mentor' ),
), array( false, false, false ) );

echo "\n=== An open mentor request already on record is still there to be closed ===\n";

// The Administrator's side stays for every kind, so a request a school made before this release is
// closed the way it always was. It is raised here through the store, which is how the import raises
// its own kind, since the school's form that used to raise it is gone.
$GLOBALS['uid']     = 7;
$GLOBALS['referer'] = array();

$raised = WPCPM_Institution_Request::raise( 'mentor', $A, $S1, 7, 'member' );
$rows   = stored();
$one    = array_values( $rows );

ck( 'the store still takes the kind', is_wp_error( $raised ) ? $raised->get_error_code() : (int) $raised > 0, true );
ck( 'one row was written', count( $rows ), 1 );
ck( 'with the kind, the institution, the student, the state and who raised it', $one[0], array(
	'kind'        => 'mentor',
	'institution' => $A,
	'student'     => $S1,
	'state'       => 'open',
	'note'        => '',
	'actor'       => 7,
) );

$entries = log_for( $A );
ck( 'and one audit row was written', count( $entries ), 1 );
ck( 'naming the kind, the student, the actor and the ground it was allowed on', array(
	$entries[0]['kind'],
	$entries[0]['subject'],
	$entries[0]['actor'],
	$entries[0]['ground'],
	$entries[0]['evidence'],
	$entries[0]['data'],
), array( 'request_raised', $S1, 7, 'member', 'cache', array( 'request' => (int) array_keys( $rows )[0], 'kind' => 'mentor' ) ) );

// One open row per student per kind is a property of the store, so a second raise is refused at the
// door whoever calls it.
$again = WPCPM_Institution_Request::raise( 'mentor', $A, $S1, 7 );
ck( 'raise() refuses a second open one for the same student', is_wp_error( $again ) ? $again->get_error_code() : $again, 'wpcpm_request_open' );
ck( 'and writes neither a row nor an audit entry', array( count( stored() ), count( log_for( $A ) ) ), array( 1, 1 ) );

// What the Administrator Dashboard's "Requests from institutions" card draws and posts for it.
$mentor_row = array_keys( stored() )[0];
$shown      = render_decisions( 1, $mentor_row );

ck( 'the Administrator\'s card still draws both decisions for it', array(
	has( $shown, 'value="done"' ),
	has( $shown, 'value="declined"' ),
	has( $shown, 'name="request" value="' . (int) $mentor_row . '"' ),
), array( true, true, true ) );
ck( 'and a member is drawn none of it', render_decisions( 7, $mentor_row ), '' );
ck( 'its facts say what it is and who asked', array(
	WPCPM_Institution_Request::facts( $mentor_row )['kind'],
	WPCPM_Institution_Request::facts( $mentor_row )['kind_label'],
	WPCPM_Institution_Request::facts( $mentor_row )['student_name'],
	WPCPM_Institution_Request::facts( $mentor_row )['state'],
), array( 'mentor', 'A mentor is wanted', 'Anna Nowak', 'open' ) );
ck( 'and it is in the open queue', WPCPM_Institution_Request::open_requests( 10 ), array( (int) $mentor_row ) );

/* ---- the queue ----------------------------------------------------------- */

echo "\n=== The queue is oldest first and bounded ===\n";

$second = WPCPM_Institution_Request::raise( 'mentor', $B, $S3, 9, 'member' );
$third  = WPCPM_Institution_Request::raise( 'add', $A, $S2, 0 );
$first  = array_keys( stored() )[0];

// The first row is made oldest by hand rather than by waiting a second, which is also the
// case the ID tiebreak cannot answer: newer ID, older date.
$GLOBALS['posts'][ $first ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', 1755000000 );

ck( 'every open row, oldest first', WPCPM_Institution_Request::open_requests( 10 ), array( (int) $first, (int) $second, (int) $third ) );
ck( 'bounded by what the caller asked for', WPCPM_Institution_Request::open_requests( 2 ), array( (int) $first, (int) $second ) );
ck( 'and by the ceiling when the caller asks for nothing sensible', count( WPCPM_Institution_Request::open_requests( 0 ) ), 3 );
ck( 'the ceiling is what a limit above it is clamped to', WPCPM_Institution_Request::open_requests( 5000 ), WPCPM_Institution_Request::open_requests( 200 ) );

$facts = WPCPM_Institution_Request::facts( $second );
ck( 'the facts a queue row needs', array_keys( $facts ), array(
	'id', 'kind', 'kind_label', 'state', 'institution', 'institution_name', 'country', 'country_name',
	'student', 'student_name', 'actor', 'actor_name', 'note', 'at', 'closed_at', 'overdue', 'changes',
) );
ck( 'and a request of another kind has no changes to decide on', $facts['changes'], array() );
ck( 'named from the two indexes, with the country for routing', array(
	$facts['institution_name'],
	$facts['country_name'],
	$facts['student_name'],
	$facts['actor_name'],
	$facts['state'],
), array( 'Universidad Example', 'Costa Rica', 'Carla Vega', 'Cleo Beta', 'open' ) );
ck( 'no address anywhere in a queue row', preg_match( '/@example\.test/', serialize( $facts ) ), 0 );

// The threshold, from open question 3 in the design spec, answered on 2 September 2026:
// fourteen days, and not the review queue's three, which measures a different wait. The ages
// are set by hand because the stub dates its rows in 2025 and what is under test is the
// threshold rather than how long ago the fixture was written.
ck( 'the threshold is on the class rather than in prose', WPCPM_Institution_Request::OVERDUE_DAYS, 14 );

$GLOBALS['posts'][ $second ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', time() - ( 15 * DAY_IN_SECONDS ) );
ck( 'a row that has waited longer than that is overdue', WPCPM_Institution_Request::facts( $second )['overdue'], true );

$GLOBALS['posts'][ $second ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', time() - ( 13 * DAY_IN_SECONDS ) );
ck( 'and one still inside it is not', WPCPM_Institution_Request::facts( $second )['overdue'], false );

ck( 'an unknown post has no facts at all', WPCPM_Institution_Request::facts( 999999 ), array() );
ck( 'and neither has a post of another type', WPCPM_Institution_Request::facts( wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'private' ) ) ), array() );

$decisions = render_decisions( 1, $second );
ck( 'the queue row carries both decisions', array(
	has( $decisions, 'value="done"' ),
	has( $decisions, 'value="declined"' ),
	has( $decisions, 'name="request" value="' . (int) $second . '"' ),
), array( true, true, true ) );
ck( 'with the nonce keyed to the request', has( $decisions, 'value="nonce-wpcpm_resolve_request_' . (int) $second . '"' ), true );
ck( 'and a member is drawn none of it', render_decisions( 9, $second ), '' );

/* ---- resolving ----------------------------------------------------------- */

echo "\n=== A manager resolves one, and declines another ===\n";

$GLOBALS['uid']     = 1;
$GLOBALS['referer'] = array();
$_POST              = array( 'request' => (int) $first, 'state' => 'done', 'wpcpm_request_note' => "Assigned Dana Mentor in Airtable.\nThe sync will carry it." );

// The first of the two is the mentor request that was already on record, raised above: the Administrator's
// side of that kind is what is closed here, with the capability, the nonce and the policy as for any other.
ck( 'the request closed first is the mentor request already on record', stored()[ $first ]['kind'], 'mentor' );
ck( 'it ends on the queue', run( 'handle_resolve' ), 'redirect:https://example.test/wp-admin/admin.php?page=wpcpm-institutions#wpcpm-queue' );
ck( 'the nonce it checked was keyed to the request', $GLOBALS['referer'], array( 'wpcpm_resolve_request_' . (int) $first ) );
ck( 'the row is closed as handled, with the note on it', array(
	stored()[ $first ]['state'],
	stored()[ $first ]['note'],
), array( 'done', "Assigned Dana Mentor in Airtable.\nThe sync will carry it." ) );
ck( 'who raised it is not overwritten by who closed it', stored()[ $first ]['actor'], 7 );
// When it was closed, which is a different fact from when it was raised and the one the
// Administrator Dashboard's "Recently closed" card is about (deep check FADMN-3).
ck( 'and the row carries the moment it was closed', abs( WPCPM_Institution_Request::facts( $first )['closed_at'] - time() ) <= 2, true );
ck( 'while an open row has no such moment', WPCPM_Institution_Request::facts( $third )['closed_at'], 0 );
ck( 'the manager is told, on the screen\'s own channel', flash_for( 1, 'institutions' ), 'request-done' );

$entries = log_for( $A );
ck( 'a second audit row, under the manager ground', array(
	count( $entries ),
	$entries[0]['kind'],
	$entries[0]['ground'],
	$entries[0]['actor'],
	$entries[0]['data'],
), array( 3, 'request_resolved', 'manager', 1, array( 'request' => (int) $first, 'kind' => 'mentor', 'state' => 'done' ) ) );
ck( 'carrying the manager\'s words', has( $entries[0]['message'], 'Assigned Dana Mentor in Airtable.' ), true );

$GLOBALS['uid'] = 2;
$_POST          = array( 'request' => (int) $second, 'state' => 'declined' );
run( 'handle_resolve' );

ck( 'a declined row is closed too', stored()[ $second ]['state'], 'declined' );
ck( 'and says so', flash_for( 2, 'institutions' ), 'request-declined' );
ck( 'the declined row leaves the queue', WPCPM_Institution_Request::open_requests( 10 ), array( (int) $third ) );
ck( 'and its closed row draws no decisions', render_decisions( 1, $second ), '' );

$GLOBALS['posts'][ $second ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', time() - ( 60 * DAY_IN_SECONDS ) );
ck( 'and however long it waited, a closed row is never marked overdue', WPCPM_Institution_Request::facts( $second )['overdue'], false );

echo "\n=== A closed row cannot be moved again ===\n";

$closed = WPCPM_Institution_Request::settle( $first, 'declined', '', 1, 'manager' );
ck( 'the store refuses the transition', is_wp_error( $closed ) ? $closed->get_error_code() : $closed, 'wpcpm_request_state' );
ck( 'and left the row as it was', stored()[ $first ]['state'], 'done' );

clear_flash( 2 );
$GLOBALS['uid'] = 2;
$_POST          = array( 'request' => (int) $first, 'state' => 'done' );
run( 'handle_resolve' );
ck( 'the second manager to press it is told it was already closed', flash_for( 2, 'institutions' ), 'request-closed' );

clear_flash( 2 );
$_POST = array( 'request' => (int) $third, 'state' => 'open' );
run( 'handle_resolve' );
ck( 'and nothing reopens a row', array( flash_for( 2, 'institutions' ), stored()[ $third ]['state'] ), array( 'request-state', 'open' ) );

clear_flash( 2 );
$_POST = array( 'request' => 999999, 'state' => 'done' );
run( 'handle_resolve' );
ck( 'a request that is not there says so', flash_for( 2, 'institutions' ), 'request-gone' );

echo "\n=== Only a program manager resolves ===\n";

$GLOBALS['uid'] = 7;
$_POST          = array( 'request' => (int) $third, 'state' => 'done' );
ck( 'a member of the institution cannot', run( 'handle_resolve' ), 'wp_die:You do not have permission to manage the program.' );
ck( 'and the row is untouched', stored()[ $third ]['state'], 'open' );

/* ---- the invariant, and what may bound the lookup that holds it ---------- */

echo "\n=== One open row per student per kind, whatever else is open ===\n";

// The invariant is only as good as the lookup: `open_for()` answering 0 is the whole of what
// lets a second row be written, so a busy institution must not be able to hide the first one
// behind other people's rows. Two hundred and one of them, with the row under test written
// last so that any oldest-first window would leave it out.
$C  = 'recWINDOW00000001'; // an institution with more open rows than any window.
$S4 = 'recS0000000000004'; // its student, asked after last of all.

for ( $i = 1; $i <= WPCPM_Institution_Request::QUEUE_MAX; $i++ ) {
	seed_open( 'mentor', $C, sprintf( 'recWFILL%09d', $i ) );
}

$late = seed_open( 'mentor', $C, $S4 );

ck( 'the newest open row is found behind a ceiling\'s worth of others', WPCPM_Institution_Request::open_for( 'mentor', $C, $S4 ), $late );

$duplicate = WPCPM_Institution_Request::raise( 'mentor', $C, $S4, 7 );

ck( 'so the store still refuses a second for that student', is_wp_error( $duplicate ) ? $duplicate->get_error_code() : $duplicate, 'wpcpm_request_open' );
ck( 'and wrote nothing', stored_for( $C ), WPCPM_Institution_Request::QUEUE_MAX + 1 );

echo "\n=== The lookup answers about one kind, one student and one institution ===\n";

$D    = 'recFILTER00000001';
$S5   = 'recS0000000000005';
$mine = seed_open( 'mentor', $D, $S5 );

ck( 'the open row is found', WPCPM_Institution_Request::open_for( 'mentor', $D, $S5 ), $mine );
ck( 'a different kind is not that row', WPCPM_Institution_Request::open_for( 'add', $D, $S5 ), 0 );
ck( 'and neither is a different student', WPCPM_Institution_Request::open_for( 'mentor', $D, $S4 ), 0 );
ck( 'nor the same student at a different institution', WPCPM_Institution_Request::open_for( 'mentor', $C, $S5 ), 0 );

// The database collates `recABC` and `recabc` as one string and Airtable does not, which is
// what the two `strcmp()` re-checks under the query are for. The stub above matches meta the
// way MySQL would, so a missing re-check shows up here.
ck( 'and neither ID is matched the way the database would match it', array(
	WPCPM_Institution_Request::open_for( 'mentor', strtolower( $D ), $S5 ),
	WPCPM_Institution_Request::open_for( 'mentor', $D, strtolower( $S5 ) ),
), array( 0, 0 ) );

update_post_meta( $mine, WPCPM_Institution_Request::META_STATE, WPCPM_Institution_Request::STATE_DONE );
ck( 'and a closed row is not an open one', WPCPM_Institution_Request::open_for( 'mentor', $D, $S5 ), 0 );

/* ---- the shape of the file ---------------------------------------------- */

echo "\n=== The order of the checks, and the boundary ===\n";

$src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-request.php' );

ck( 'the file has no handler for a school to raise a mentor request with', method_body( $src, 'handle_request' ), null );

$resolve_body = method_body( $src, 'handle_resolve' );
ck( 'resolving checks the capability before the nonce', before( $resolve_body, 'current_user_can', 'check_admin_referer' ), true );
ck( 'and the nonce before the policy', before( $resolve_body, 'check_admin_referer', 'WPCPM_Institution_Policy::decide' ), true );
ck( 'with the subject read from the post and never from the form', has( $resolve_body, 'subject_post( $post, self::META_INSTITUTION )' ), true );
// The same, for the same reason: a manager passes `decide()` on every institution, so no
// account this suite can sign in as would behave differently with the refusal deleted.
ck( 'and refusing on the decision\'s own answer there too', (bool) preg_match( '/if \( empty\( \$decision\[\'allowed\'\] \) \) \{\s*wp_die\(/', (string) $resolve_body ), true );

// Section 13: this records that a mentor is wanted, it does not negotiate one. Nothing in the
// file may reach Airtable, and nothing may write a mentor anywhere. A school's details are written to
// the base by the details class and by nothing here.
ck( 'nothing here talks to Airtable', preg_match( '/WPCPM_Airtable|update_records|create_records/', $src ), 0 );
// Mail is the details kind's alone (a mentor request is told to nobody): the four emails about a
// school's details, sent from the two helpers that send, through the template registry, and from
// nowhere else in the file.
$mail_managers = (string) method_body( $src, 'mail_managers' );
$mail_account  = (string) method_body( $src, 'mail_account' );
$without_mail  = str_replace( array( $mail_managers, $mail_account ), '', $src );
ck( 'and nothing here sends mail but the two helpers that send the details emails', array(
	'' !== $mail_managers && '' !== $mail_account,
	preg_match( '/WPCPM_Mail::|notify_managers/', $without_mail ),
	preg_match_all( '/WPCPM_Mail_Templates::mail\(/', $src ),
), array( true, 0, 2 ) );

ck( 'no institution ID is compared with === anywhere in the file', preg_match( '/\$institution\s*===|===\s*\$institution/', $src ), 0 );
// The same lookup decides under the school's lock, so it must see an open row another request
// made a moment ago and not the answer this request's own query cache filled before the lock.
$matching_body = (string) method_body( $src, 'open_matching' );
$matching_call = strpos( $matching_body, 'get_posts(' );
$matching_args = '';
if ( false !== $matching_call ) {
	$depth = 1;
	$end   = $matching_call + strlen( 'get_posts(' );
	$begin = $end;
	while ( $end < strlen( $matching_body ) && $depth > 0 ) {
		if ( '(' === $matching_body[ $end ] ) { ++$depth; } elseif ( ')' === $matching_body[ $end ] ) { --$depth; }
		++$end;
	}
	$matching_args = substr( $matching_body, $begin, $end - $begin - 1 );
}
ck( 'the lookup behind an open row reads fresh: its get_posts() asks for no cached results', false !== $matching_call && 1 === preg_match( "/'cache_results'\s*=>\s*false\b/", $matching_args ), true );
ck( 'every state change goes through the two writers', preg_match_all( '/update_post_meta\(\s*\$post_id,\s*self::META_STATE/', $src ), 2 );

$GLOBALS['hooks'] = array();
WPCPM_Institution_Request::init();
ck( 'the post type and the three handlers are registered, and nothing posts a school\'s mentor request', $GLOBALS['hooks'], array(
	'init',
	'admin_post_wpcpm_resolve_request',
	'admin_post_wpcpm_decide_details',
	'admin_post_wpcpm_withdraw_details',
) );

WPCPM_Institution_Request::register_post_type();
ck( 'the post type is invisible everywhere', array(
	$GLOBALS['registered'][0],
	$GLOBALS['registered'][1]['public'],
	$GLOBALS['registered'][1]['show_ui'],
	$GLOBALS['registered'][1]['show_in_rest'],
	$GLOBALS['registered'][1]['publicly_queryable'],
	$GLOBALS['registered'][1]['map_meta_cap'],
), array( 'wpcpm_inst_request', false, false, false, false, true ) );

ck( 'and nothing is granted its capability type', $GLOBALS['registered'][1]['capability_type'], array( 'wpcpm_inst_request', 'wpcpm_inst_requests' ) );

WPCPM_Institution_Request::delete_all();
ck( 'uninstall removes every request', stored(), array() );
ck( 'and the audit rows are left for the audit log to remove', count( log_for( $A ) ) > 0, true );

$dashes = array();

foreach ( array(
	'includes/modules/class-wpcpm-institution-request.php',
	'bin/test-institution-request.php',
) as $rel ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $rel ) ) ) {
		$dashes[] = $rel;
	}
}

ck( 'no dash but the plain hyphen in either file', $dashes, array() );

echo "\n=== Closed requests ===\n";

// One handled and one declined, on top of the open fixture rows; the closed reader lists
// only those two, newest edit first, and the open reader still ignores them.
$done = wp_insert_post( array( 'post_type' => WPCPM_Institution_Request::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Handled' ) );
update_post_meta( $done, WPCPM_Institution_Request::META_STATE, WPCPM_Institution_Request::STATE_DONE );
$GLOBALS['posts'][ $done ]->post_modified_gmt = '2026-09-01 10:00:00';
$declined = wp_insert_post( array( 'post_type' => WPCPM_Institution_Request::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Declined' ) );
update_post_meta( $declined, WPCPM_Institution_Request::META_STATE, WPCPM_Institution_Request::STATE_DECLINED );
$GLOBALS['posts'][ $declined ]->post_modified_gmt = '2026-09-02 10:00:00';

ck( 'closed requests, the one closed last on top', WPCPM_Institution_Request::closed_requests(), array( $declined, $done ) );
ck( 'capped', WPCPM_Institution_Request::closed_requests( 1 ), array( $declined ) );
ck( 'and the open reader does not list them', array_intersect( WPCPM_Institution_Request::open_requests( 200 ), array( $done, $declined ) ), array() );

// The order is the closing, not the raising: nothing ever moves post_modified off post_date
// here (settle() writes meta only), so the list used to be in the order the rows were opened
// and a request raised in January and handled today sank to the bottom of it, or off the cap
// (deep check FADMN-3). This row was raised last and closed first, so it comes last.
$late = wp_insert_post( array( 'post_type' => WPCPM_Institution_Request::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Raised last, closed first' ) );
update_post_meta( $late, WPCPM_Institution_Request::META_STATE, WPCPM_Institution_Request::STATE_DONE );
update_post_meta( $late, WPCPM_Institution_Request::META_CLOSED_AT, 1756000000 );
update_post_meta( $done, WPCPM_Institution_Request::META_CLOSED_AT, 1756100000 );
update_post_meta( $declined, WPCPM_Institution_Request::META_CLOSED_AT, 1756200000 );

ck( 'a row raised last and closed first sits under both of them', WPCPM_Institution_Request::closed_requests(), array( $declined, $done, $late ) );

// And a row closed before 1.99.0, which has no stamp at all, is still listed rather than
// dropped by the join that orders the others.
delete_post_meta( $done, WPCPM_Institution_Request::META_CLOSED_AT );
ck( 'a row with no stamp is still in the list, at the end of it', WPCPM_Institution_Request::closed_requests(), array( $declined, $late, $done ) );

// Two queries, because one cannot order both halves. Asked together in an `OR` group, the
// unstamped rows were ordered by an alias the WHERE had not pinned: whatever meta value
// MySQL handed back per group, then the ID (deep check FADMN-3, fix round). Asked apart, the
// legacy half is ordered by the only date it has, its post date - so a row raised long ago
// sits under a row raised last week however their IDs run.
$ancient = wp_insert_post( array( 'post_type' => WPCPM_Institution_Request::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Closed years ago' ) );
update_post_meta( $ancient, WPCPM_Institution_Request::META_STATE, WPCPM_Institution_Request::STATE_DECLINED );
$GLOBALS['posts'][ $ancient ]->post_date_gmt = '2024-03-01 09:00:00';

ck( 'the stamped rows first, and the legacy ones under them in the order they were raised, newest first', WPCPM_Institution_Request::closed_requests(), array( $declined, $late, $done, $ancient ) );

// The note is the manager's own words, and nothing stops one opening with a number. Under
// the OR group that number was a candidate sort key for the row it belongs to, so a request
// closed before 1.99.0 could be handed the top of a card headed "closed last".
update_post_meta( $done, WPCPM_Institution_Request::META_NOTE, '9000000000 is the ticket they quoted' );

ck( 'and a legacy row whose note opens with a long number is still under the stamped ones', WPCPM_Institution_Request::closed_requests(), array( $declined, $late, $done, $ancient ) );

// The cap is the card's, not each query's: the legacy half is asked for what is left of it.
ck( 'the cap counts both halves together', WPCPM_Institution_Request::closed_requests( 3 ), array( $declined, $late, $done ) );

$GLOBALS['queries'] = array();

ck( 'and when the stamped rows fill the cap the legacy half is not asked for at all', array( WPCPM_Institution_Request::closed_requests( 2 ), count( $GLOBALS['queries'] ) ), array( array( $declined, $late ), 1 ) );

echo "\n=== A closing note keeps its backslashes ===\n";

// Post meta unslashes what it is handed, as core's does, and the note is read from the form
// unslashed: it keeps a backslash only when it is written as a slashed copy.
$typed_note = 'Filed under C:\drafts\mentors, two \\\\ in a row, as "reassigned".';
$typed_req  = WPCPM_Institution_Request::raise( 'mentor', $A, $S1, 7 );
$GLOBALS['uid'] = 1;
$_POST          = array( 'request' => (int) $typed_req, 'state' => 'done', 'wpcpm_request_note' => $typed_note );
run( 'handle_resolve' );

ck( 'a request is raised for the check', is_int( $typed_req ) && $typed_req > 0, true );
ck( 'and its closing note is kept exactly as typed', get_post_meta( (int) $typed_req, WPCPM_Institution_Request::META_NOTE, true ), $typed_note );

echo "\n=== A closing note the cleaner would take words from is refused, and kept as typed ===\n";

// "Kids <12 free, adults >18 pay": core's cleaner takes "<12 free, adults >" for a tag and drops
// it, words and all. The note goes on the row and on its audit entry, the record somebody reads a
// year later, so such a note is refused before either is written, in the words of the button that
// was pressed, and the box gets back what was typed: for that request, for that manager, once.
$GLOBALS['users'][3] = new WP_User( 3, 'Third Manager', 'maciej+third@a8c.com', array( 'administrator' ) );
$GLOBALS['users'][4] = new WP_User( 4, 'Fourth Manager', 'maciej+fourth@a8c.com', array( 'administrator' ) );
$GLOBALS['manage'][] = 3;
$GLOBALS['manage'][] = 4;
$lossy_close         = "Assigned Dana: kids <12 free, adults >18 pay.\r\nThe sync will carry it.";
$lossy_kept          = str_replace( "\r\n", "\n", $lossy_close );
$open_for_loss       = seed_open( 'mentor', $A, 'recS0000000000101' );
$open_for_decline    = seed_open( 'mentor', $A, 'recS0000000000102' );
$logged              = count( log_for( $A ) );
$queue_back          = 'redirect:https://example.test/wp-admin/admin.php?page=wpcpm-institutions#wpcpm-queue';

$GLOBALS['uid'] = 1;
clear_flash( 1 );
$_POST          = array( 'request' => $open_for_loss, 'state' => 'done', 'wpcpm_request_note' => $lossy_close );
$went           = run( 'handle_resolve' );
$left           = $GLOBALS['umeta'][1]['wpcpm_flash'] ?? array();
ck( 'Mark as handled with such a note is refused, and the row is left open: no note, no closing moment, no audit entry', array(
	$went,
	$left['institutions'] ?? '',
	stored()[ $open_for_loss ]['state'],
	stored()[ $open_for_loss ]['note'],
	WPCPM_Institution_Request::facts( $open_for_loss )['closed_at'],
	count( log_for( $A ) ),
), array( $queue_back, 'request-done-loss', 'open', '', 0, $logged ) );
ck( 'what was typed waits for that request\'s box, a line break read as the one LF the box counts', $left[ WPCPM_Typed_Text::KEPT_CHANNEL . 'request-close:' . $open_for_loss ]['typed'] ?? null, array( 'note' => $lossy_kept ) );

clear_flash( 2 );
$GLOBALS['uid'] = 2;
$_POST          = array( 'request' => $open_for_decline, 'state' => 'declined', 'wpcpm_request_note' => $lossy_close );
$went           = run( 'handle_resolve' );
ck( 'and so is Decline, in its own words', array( $went, flash_for( 2, 'institutions' ), stored()[ $open_for_decline ]['state'], count( log_for( $A ) ) ), array( $queue_back, 'request-declined-loss', 'open', $logged ) );
clear_flash( 2 );
$GLOBALS['uid'] = 1;

ck( 'each sentence names the box by its label and says to press that button again', array(
	WPCPM_Institution_Request::messages()['request-done-loss'] ?? null,
	WPCPM_Institution_Request::messages()['request-declined-loss'] ?? null,
), array(
	array( 'error', 'Nothing was marked as handled, because WordPress would remove part of what you typed in "What you did about it". To keep every word, put a space after each "<" (or write "less than") and after each "%", then mark it as handled again.' ),
	array( 'error', 'Nothing was declined, because WordPress would remove part of what you typed in "What you did about it". To keep every word, put a space after each "<" (or write "less than") and after each "%", then decline it again.' ),
) );

// The next page is a new request, which reads the flash afresh. `WPCPM_Flash::take()` memoizes per
// person and per channel for the life of a request, and the press above took this channel once
// already, so a manager of their own holding what the press left stands in for that next page.
$other_manager = render_decisions( 2, $open_for_loss );
$GLOBALS['umeta'][3]['wpcpm_flash'] = $left;
clear_flash( 1 );
$elsewhere     = render_decisions( 3, $open_for_decline );
$given_back    = render_decisions( 3, $open_for_loss );
$taken         = isset( $GLOBALS['umeta'][3]['wpcpm_flash'][ WPCPM_Typed_Text::KEPT_CHANNEL . 'request-close:' . $open_for_loss ] );
$box           = static function ( $id ) {
	return '<textarea id="wpcpm-request-note-' . (int) $id . '" name="wpcpm_request_note" rows="2" maxlength="' . WPCPM_Institution_Request::MAX_NOTE . '" placeholder="What you did about it, for the log. Optional, and not sent to the institution.">';
};
ck( 'on the Administrator Dashboard the next page gives the typing back exactly, in that request\'s box, once', array(
	substr_count( $given_back, $box( $open_for_loss ) . "\n" . esc_textarea( $lossy_kept ) . '</textarea>' ),
	substr_count( $given_back, '<textarea' ),
	$taken,
), array( 1, 1, false ) );
ck( 'another request\'s box and another manager never see it', array(
	substr_count( $elsewhere, $box( $open_for_decline ) . '</textarea>' ),
	substr_count( $other_manager, $box( $open_for_loss ) . '</textarea>' ),
), array( 1, 1 ) );
unset( $GLOBALS['umeta'][3] );
$GLOBALS['uid'] = 1;

// Counted as typed and refused rather than cut: a note that fits its box is kept whole, though the
// cleaner writes a "<" that opens no tag, and each quote mark and ampersand after it, as an entity
// and so stores it longer than the box counted; one over the limit as typed is refused.
$fits = mb_substr( str_repeat( 'Asked Dana < 12 & "up": ', 100 ), 0, WPCPM_Institution_Request::MAX_NOTE );
ck( 'the note below fits its box as typed while the cleaner stores it longer', array( WPCPM_Typed_Text::typed_length( $fits ), mb_strlen( sanitize_textarea_field( $fits ) ) > WPCPM_Institution_Request::MAX_NOTE, WPCPM_Typed_Text::cleaner_loses( $fits, 'lines' ) ), array( WPCPM_Institution_Request::MAX_NOTE, true, false ) );
$_POST = array( 'request' => $open_for_loss, 'state' => 'done', 'wpcpm_request_note' => $fits );
run( 'handle_resolve' );
ck( 'so the row is closed with every word of it, none cut', array( flash_for( 1, 'institutions' ), stored()[ $open_for_loss ]['state'], stored()[ $open_for_loss ]['note'] === trim( sanitize_textarea_field( $fits ) ) ), array( 'request-done', 'done', true ) );
clear_flash( 1 );

$_POST = array( 'request' => $open_for_decline, 'state' => 'declined', 'wpcpm_request_note' => str_repeat( 'a', WPCPM_Institution_Request::MAX_NOTE + 1 ) );
run( 'handle_resolve' );
ck( 'a note over the limit as typed is refused, the row left open, the typing kept up to the box\'s room', array(
	flash_for( 1, 'institutions' ),
	stored()[ $open_for_decline ]['state'],
	mb_strlen( (string) ( $GLOBALS['umeta'][1]['wpcpm_flash'][ WPCPM_Typed_Text::KEPT_CHANNEL . 'request-close:' . $open_for_decline ]['typed']['note'] ?? '' ) ),
), array( 'request-declined-long', 'open', WPCPM_Institution_Request::MAX_NOTE ) );
clear_flash( 1 );
ck( 'and each length sentence names the box and the button', array(
	WPCPM_Institution_Request::messages()['request-done-long'] ?? null,
	WPCPM_Institution_Request::messages()['request-declined-long'] ?? null,
), array(
	array( 'error', 'Nothing was marked as handled. What you typed in "What you did about it" is longer than 2000 characters: shorten it and mark it as handled again.' ),
	array( 'error', 'Nothing was declined. What you typed in "What you did about it" is longer than 2000 characters: shorten it and decline it again.' ),
) );

// The limit is counted on what was typed, as the box counts it, in the handler and in `settle()`
// alike: a reference typed out after a "<", which the cleaner writes in core's longer form (`&#9;`
// as `&#009;`), is counted as it was typed, and so is an entity typed out.
$at_most_typed = str_repeat( 'Asked Dana < 12 &#9; ok. ', 79 ) . 'Our next sync carries it.';
ck( 'the note below is 2,000 characters as typed, and the count of what the cleaner keeps reads it as more', array( WPCPM_Typed_Text::box_length( $at_most_typed ), WPCPM_Typed_Text::typed_length( sanitize_textarea_field( $at_most_typed ) ) > WPCPM_Institution_Request::MAX_NOTE, WPCPM_Typed_Text::cleaner_loses( $at_most_typed, 'lines' ) ), array( WPCPM_Institution_Request::MAX_NOTE, true, false ) );
$open_at_most = seed_open( 'mentor', $A, 'recS0000000000103' );
$_POST        = array( 'request' => $open_at_most, 'state' => 'done', 'wpcpm_request_note' => $at_most_typed );
run( 'handle_resolve' );
ck( 'so the row is closed with every word of it, none cut on the way to the store', array( flash_for( 1, 'institutions' ), stored()[ $open_at_most ]['state'], stored()[ $open_at_most ]['note'] === trim( sanitize_textarea_field( $at_most_typed ) ) ), array( 'request-done', 'done', true ) );
clear_flash( 1 );

$open_over = seed_open( 'mentor', $A, 'recS0000000000104' );
$_POST     = array( 'request' => $open_over, 'state' => 'declined', 'wpcpm_request_note' => str_repeat( '&lt;', 500 ) . '.' );
run( 'handle_resolve' );
ck( 'a note of 2,001 characters as typed, each "&lt;" typed out, is refused, the row left open, the typing kept up to the box\'s room', array(
	flash_for( 1, 'institutions' ),
	stored()[ $open_over ]['state'],
	$GLOBALS['umeta'][1]['wpcpm_flash'][ WPCPM_Typed_Text::KEPT_CHANNEL . 'request-close:' . $open_over ]['typed']['note'] ?? null,
), array( 'request-declined-long', 'open', str_repeat( '&lt;', 500 ) ) );
clear_flash( 1 );

// For a caller that refused nothing, the cut still stands, counted the same way: on the typing it
// hands, or on the note when it hands none.
$open_cut = seed_open( 'mentor', $A, 'recS0000000000105' );
ck( 'settle() handed no typing cuts a note over the limit at the limit', array( WPCPM_Institution_Request::settle( $open_cut, 'done', str_repeat( 'a', WPCPM_Institution_Request::MAX_NOTE + 5 ), 1, 'manager' ), mb_strlen( stored()[ $open_cut ]['note'] ) ), array( true, WPCPM_Institution_Request::MAX_NOTE ) );
$open_kept = seed_open( 'mentor', $A, 'recS0000000000106' );
ck( 'and handed the typing, keeps whole a note that fits as typed', array( WPCPM_Institution_Request::settle( $open_kept, 'done', trim( sanitize_textarea_field( $at_most_typed ) ), 1, 'manager', $at_most_typed ), stored()[ $open_kept ]['note'] === trim( sanitize_textarea_field( $at_most_typed ) ) ), array( true, true ) );

// A clean note closes the row as before, and its press drops what an earlier refusal of the form
// kept, pressed by a manager whose flash this run has not read for that request yet.
$GLOBALS['uid'] = 4;
WPCPM_Typed_Text::keep_box( WPCPM_Typed_Text::box_form( WPCPM_Institution_Request::TYPED_NOTE, $open_for_decline ), 'note', 'An earlier refused note', WPCPM_Institution_Request::MAX_NOTE );
$was_kept = isset( $GLOBALS['umeta'][4]['wpcpm_flash'][ WPCPM_Typed_Text::KEPT_CHANNEL . 'request-close:' . $open_for_decline ] );
$_POST    = array( 'request' => $open_for_decline, 'state' => 'declined', 'wpcpm_request_note' => 'Declined: the student has left.' );
run( 'handle_resolve' );
ck( 'a clean note closes the row as before, and the press dropped what the refusal before it kept', array(
	$was_kept,
	flash_for( 4, 'institutions' ),
	stored()[ $open_for_decline ]['state'],
	stored()[ $open_for_decline ]['note'],
	isset( $GLOBALS['umeta'][4]['wpcpm_flash'][ WPCPM_Typed_Text::KEPT_CHANNEL . 'request-close:' . $open_for_decline ] ),
), array( true, 'request-declined', 'declined', 'Declined: the student has left.', false ) );
unset( $GLOBALS['umeta'][4] );
$GLOBALS['uid'] = 1;

/* ---- a school's details request (Institutions release 2) ---------------- */

const DET_COSTA  = 'recCR000000000001';
const DET_POLAND = 'recPL000000000001';

$GLOBALS['users'][30]   = new WP_User( 30, 'School Representative', 'rep@example.test', array( 'subscriber' ) );
$GLOBALS['users'][31]   = new WP_User( 31, 'Another Representative', 'colleague@example.test', array( 'subscriber' ) );
$GLOBALS['users'][32]   = new WP_User( 32, 'Nameless', 'nameless@example.test', array( 'subscriber' ) );
$GLOBALS['users'][32]->display_name = '';

/**
 * One school the details request is about: the pipeline index row an Administrator reads "On record
 * now" from, its agreement settled, and two accounts that act for it, 30 and 31. Every school is the
 * same school under another record ID, so each scenario starts from the same facts.
 *
 * @param int $n Which school.
 * @return string Its Institutions record ID.
 */
function det_school( $n ) {
	$id = sprintf( 'recDETAILS%07d', $n );

	$GLOBALS['index'][ $id ]    = array(
		'record_id'      => $id,
		'name'           => 'Example University ',
		'stage'          => 'Confirmed',
		'country'        => DET_COSTA,
		'country_name'   => 'Costa Rica',
		'city'           => 'San Jose',
		'website'        => 'https://example.edu',
		'contact_person' => 'School Contact',
		'contact_email'  => 'contact@example.test',
	);
	$GLOBALS['settled'][]       = $id;
	$GLOBALS['memberships'][30] = array( $id );
	$GLOBALS['memberships'][31] = array( $id );

	return $id;
}

/** What a school's form changes: the name and the website. */
function det_asked( $id, array $values = array() ) {
	return WPCPM_Institution_Details::changes(
		$id,
		array_merge(
			array(
				'name'    => 'Example University of Valencia',
				'website' => 'https://uev.example.edu',
			),
			$values
		)
	);
}

/** Start a scenario clean: no mail, no PATCH, no lock, no event, the settings and the pages in place. */
function det_fresh() {
	$GLOBALS['mail']     = array();
	$GLOBALS['patched']  = array();
	$GLOBALS['airtable'] = true;
	$GLOBALS['events']   = array();
	$GLOBALS['settings'] = array(
		'institutions_table'      => 'tblInstitutions1',
		'institutions_name_field' => '',
		'agreement_notify'        => 'admins@example.test, manager@example.test',
	);

	foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
		if ( 0 === strpos( (string) $name, 'wpcpm_req_lock_' ) ) {
			unset( $GLOBALS['opts'][ $name ] );
		}
	}

	$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ] = array(
		'v'    => WPCPM_Countries::VERSION,
		'read' => 1756000000,
		'rows' => array(
			DET_COSTA  => array( 'name' => 'Costa Rica', 'manager' => '', 'email' => '', 'calendly' => '' ),
			DET_POLAND => array( 'name' => 'Poland', 'manager' => '', 'email' => '', 'calendly' => '' ),
		),
	);

	WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';
	WPCPM_Institutions_Dashboard::$url   = 'https://example.test/institution-dashboard/';

	foreach ( array( 1, 2, 3, 4, 30, 31 ) as $uid ) {
		clear_flash( $uid );
	}
}

/** Raise a school's request as its account 30, the way the school's card will. */
function det_raise( $id, array $changes = array(), $actor = 30, $ground = 'member' ) {
	return WPCPM_Institution_Request::raise_details( $id, array() === $changes ? det_asked( $id ) : $changes, $actor, $ground );
}

/** Post the Administrator's decision on a details request, as one account. */
function det_decide( $uid, $request, $decision, $note = '' ) {
	$GLOBALS['uid']     = $uid;
	$GLOBALS['referer'] = array();
	$_POST              = array( 'request' => $request, 'decision' => $decision, 'wpcpm_request_note' => $note );

	return run( 'handle_details_decide' );
}

/** Post the school's withdrawal of a details request, as one account. */
function det_withdraw( $uid, $request, array $extra = array() ) {
	$GLOBALS['uid']     = $uid;
	$GLOBALS['referer'] = array();
	$_POST              = array_merge( array( 'request' => $request ), $extra );

	return run( 'handle_details_withdraw' );
}

/** The mails sent under one email id, as the mail layer was handed them. */
function det_mails( $context ) {
	return array_values(
		array_filter(
			$GLOBALS['mail'],
			static function ( $mail ) use ( $context ) {
				return $context === $mail['context'];
			}
		)
	);
}

/** The audit rows of one kind about one school, newest first. */
function det_rows( $id, $kind ) {
	return array_values(
		array_filter(
			log_for( $id ),
			static function ( $row ) use ( $kind ) {
				return $kind === $row['kind'];
			}
		)
	);
}

/** What a request holds in the store, whatever its state. */
function det_state( $request ) {
	return get_post_meta( (int) $request, '_wpcpm_req_state', true );
}

$DET   = det_school( 1 );
$queue = 'redirect:https://example.test/wp-admin/admin.php?page=wpcpm-institutions#wpcpm-queue';

echo "\n=== A school's details request: the changes it holds, and raising it ===\n";

det_fresh();
$asked = det_asked( $DET );

ck( 'the changes a form made are the two details, each with what the program has and what was asked', $asked, array(
	'name'    => array( 'from' => 'Example University', 'to' => 'Example University of Valencia' ),
	'website' => array( 'from' => 'https://example.edu', 'to' => 'https://uev.example.edu' ),
) );

$GLOBALS['uid'] = 30;
$raised         = WPCPM_Institution_Request::raise_details( $DET, $asked, 30, 'member' );

ck( 'raising it answers the new request\'s post', is_int( $raised ) && $raised > 0, true );
ck( 'the request holds its kind, the school, no student, the state, no note, who asked and the changes as given', array(
	get_post_meta( $raised, '_wpcpm_req_kind', true ),
	get_post_meta( $raised, '_wpcpm_req_institution', true ),
	get_post_meta( $raised, '_wpcpm_req_student', true ),
	get_post_meta( $raised, '_wpcpm_req_state', true ),
	get_post_meta( $raised, '_wpcpm_req_note', true ),
	(int) get_post_meta( $raised, '_wpcpm_req_actor', true ),
	get_post_meta( $raised, WPCPM_Institution_Request::META_CHANGES, true ),
), array( 'details', $DET, '', 'open', '', 30, $asked ) );
ck( 'the new names are the ones the plan gives them', array(
	WPCPM_Institution_Request::KIND_DETAILS,
	WPCPM_Institution_Request::STATE_WITHDRAWN,
	WPCPM_Institution_Request::META_CHANGES,
	WPCPM_Institution_Request::ACTION_DETAILS_DECIDE,
	WPCPM_Institution_Request::ACTION_DETAILS_WITHDRAW,
), array( 'details', 'withdrawn', '_wpcpm_req_changes', 'wpcpm_decide_details', 'wpcpm_withdraw_details' ) );
ck( 'it is private, like every request', $GLOBALS['posts'][ $raised ]->post_status, 'private' );

$entries = log_for( $DET );
ck( 'one request_raised row is written, naming the kind and the details by name and none of their values', array(
	count( $entries ),
	$entries[0]['kind'],
	$entries[0]['subject'],
	$entries[0]['actor'],
	$entries[0]['ground'],
	$entries[0]['evidence'],
	$entries[0]['data'],
	has( $entries[0]['message'] . serialize( $entries[0]['data'] ), 'Valencia' ),
), array( 1, 'request_raised', $DET, 30, 'member', 'cache', array( 'request' => $raised, 'kind' => 'details', 'fields' => array( 'name', 'website' ) ), false ) );
ck( 'and it says what was asked, in a sentence the school\'s history can show', $entries[0]['message'], 'A change to its details. Raised by the institution and waiting for an Administrator.' );

$sent = det_mails( 'institution-details-request' );
ck( 'the people the setting names are emailed once each, an account by its own send and an address by a bare one', array(
	count( $GLOBALS['mail'] ),
	array_column( $sent, 'to' ),
), array( 2, array( 'admins@example.test', 'manager@example.test' ) ) );
ck( 'the email says who asked, for which school, what, and where an Administrator decides', array(
	$sent[0]['subject'],
	$sent[0]['body'],
), array(
	'[Example Site] Example University asks to correct its details',
	"School Representative asks to correct Example University's details:\r\n\r\nName: Example University, asked to be Example University of Valencia\r\nWebsite: https://example.edu, asked to be https://uev.example.edu\r\n\r\nNothing is changed until an Administrator approves it. Approve or decline it on the Administrator Dashboard: https://example.test/administrator-dashboard/#wpcpm-requests",
) );

ck( 'nothing was written to Airtable, the index or the school\'s record: a request writes nothing but itself', array(
	$GLOBALS['patched'],
	$GLOBALS['index'][ $DET ]['name'],
	$GLOBALS['records'],
), array( array(), 'Example University ', array() ) );

echo "\n=== raise() is not the door for a details request ===\n";

// A details request is about no student, and has its own door, `raise_details()`, with the school's lock and
// its one-open check. `raise()` knew the kind (it is in `kinds()`) and a caller that gave it a student would
// have made a details request with none of those: a row with a student on it, no changes to approve, and
// a second open one beside the school's first.
det_fresh();
$door_school  = det_school( 70 );
$door_before  = array( stored_for( $door_school ), count( log_for( $door_school ) ), $GLOBALS['mail'] );
$through_raise = WPCPM_Institution_Request::raise( WPCPM_Institution_Request::KIND_DETAILS, $door_school, $S1, 30, 'member' );
ck( 'raise() refuses the details kind, with the code of a kind it does not take', array( is_wp_error( $through_raise ), is_wp_error( $through_raise ) ? $through_raise->get_error_code() : null ), array( true, 'wpcpm_request_kind' ) );
ck( 'and says so in a sentence of its own, not "not a kind this site keeps", which details is', is_wp_error( $through_raise ) ? $through_raise->get_error_message() : null, 'A request to correct a school\'s details is raised from its details card, and not here.' );
ck( 'and made no row, wrote no history and sent no email', array( stored_for( $door_school ), count( log_for( $door_school ) ), $GLOBALS['mail'] ), $door_before );
ck( 'whatever student it is given, and even for a school with nothing open', array(
	is_wp_error( WPCPM_Institution_Request::raise( 'details', $door_school, '', 1 ) ),
	is_wp_error( WPCPM_Institution_Request::raise( 'details', $door_school, $S9, 1, 'manager' ) ),
	stored_for( $door_school ),
), array( true, true, $door_before[0] ) );

echo "\n=== One request is open per school ===\n";

det_fresh();
$posts_before = count( stored() );
$again        = WPCPM_Institution_Request::raise_details( $DET, det_asked( $DET, array( 'city' => 'Heredia' ) ), 31, 'member' );

ck( 'a second raise for the same school is refused, in the sentence a school reads', array(
	is_wp_error( $again ) ? $again->get_error_code() : $again,
	is_wp_error( $again ) ? $again->get_error_message() : '',
), array( 'wpcpm_request_open', 'One change is already waiting for an Administrator.' ) );
ck( 'and no second request, row of history or email was made', array( count( stored() ), count( det_rows( $DET, 'request_raised' ) ), $GLOBALS['mail'] ), array( $posts_before, 1, array() ) );
ck( 'the sentence is the class\'s one, which the details card tells a post it can see will be refused in the same words', array( WPCPM_Institution_Request::open_message(), is_wp_error( $again ) ? $again->get_error_message() : '' ), array( 'One change is already waiting for an Administrator.', WPCPM_Institution_Request::open_message() ) );

$other_school = det_school( 2 );
$GLOBALS['memberships'][30] = array( $DET );
$other_raised = WPCPM_Institution_Request::raise_details( $other_school, det_asked( $other_school ), 30, 'member' );
ck( 'another school has its own', is_int( $other_raised ) && $other_raised !== $raised, true );

ck( 'the open request is found by its school alone', array(
	WPCPM_Institution_Request::open_details_for( $DET ),
	WPCPM_Institution_Request::open_details_for( $other_school ),
	WPCPM_Institution_Request::open_details_for( det_school( 3 ) ),
	WPCPM_Institution_Request::open_details_for( 'not-a-record' ),
), array( $raised, $other_raised, 0, 0 ) );
ck( 'whatever the database makes of the case of an ID, which Airtable does not', WPCPM_Institution_Request::open_details_for( strtolower( $DET ) ), 0 );
ck( 'a mentor\'s request for the same school is not a details request', array(
	WPCPM_Institution_Request::open_details_for( $A ),
	WPCPM_Institution_Request::open_for( 'details', $DET, $S1 ),
), array( 0, 0 ) );
ck( 'and the open ones are in the queue with the rest, oldest first', array_slice( WPCPM_Institution_Request::open_requests( 200 ), -2 ), array( $raised, $other_raised ) );

det_fresh();
$bad_school = det_school( 4 );
$refusals   = array(
	'a school that is not a record ID'  => array( 'not-a-record', det_asked( $bad_school ), 'wpcpm_request_institution' ),
	'no change'                         => array( $bad_school, array(), 'wpcpm_request_changes' ),
	'a detail that saves at once'       => array( $bad_school, array( 'contact_person' => array( 'from' => 'School Contact', 'to' => 'Someone Else' ) ), 'wpcpm_request_changes' ),
	'a detail the table does not have'  => array( $bad_school, array( 'stage' => array( 'from' => 'A', 'to' => 'B' ) ), 'wpcpm_request_changes' ),
	'a change with no value to become'  => array( $bad_school, array( 'name' => array( 'from' => 'A' ) ), 'wpcpm_request_changes' ),
	'a change that is not a pair'       => array( $bad_school, array( 'name' => 'Example University of Valencia' ), 'wpcpm_request_changes' ),
);
$posts_before = count( stored() );
foreach ( $refusals as $why => $refusal ) {
	$refused = WPCPM_Institution_Request::raise_details( $refusal[0], $refusal[1], 30, 'member' );
	ck( sprintf( 'raising for %s is refused', $why ), is_wp_error( $refused ) ? $refused->get_error_code() : $refused, $refusal[2] );
}
ck( 'and none of them made a request, a row of history or an email', array( count( stored() ), log_for( $bad_school ), $GLOBALS['mail'] ), array( $posts_before, array(), array() ) );

echo "\n=== The lock keeps two raises of one school apart ===\n";

det_fresh();
$lock_school = det_school( 5 );
$GLOBALS['opts'][ WPCPM_Institution_Request::LOCK_PREFIX . 'details_' . $lock_school ] = (string) ( time() + 300 );
$held = WPCPM_Institution_Request::raise_details( $lock_school, det_asked( $lock_school ), 30, 'member' );
// The lock is held by a request raising the school's one or by a save of its details at once, and neither has
// made a request that waits, so "One change is already waiting" would be untrue: the answer says someone is
// saving the details, and only a request truly open is told one waits (above).
ck( 'a raise made while the school\'s lock is held is told someone else is saving the details, and writes nothing', array(
	is_wp_error( $held ) ? $held->get_error_code() : $held,
	is_wp_error( $held ) ? $held->get_error_message() : '',
	WPCPM_Institution_Request::open_details_for( $lock_school ),
	count( det_rows( $lock_school, 'request_raised' ) ),
	$GLOBALS['mail'],
), array( 'wpcpm_request_busy', 'Someone else is saving your institution\'s details at this moment, so nothing was done. Try again in a moment.', 0, 0, array() ) );
ck( 'in the class\'s own sentence, which the details card says of a save that finds the lock held, the lock left to its holder', array(
	method_exists( 'WPCPM_Institution_Request', 'saving_message' ) ? WPCPM_Institution_Request::saving_message() : null,
	$GLOBALS['opts'][ WPCPM_Institution_Request::LOCK_PREFIX . 'details_' . $lock_school ] ?? null,
), array( 'Someone else is saving your institution\'s details at this moment, so nothing was done. Try again in a moment.', (string) ( time() + 300 ) ) );
ck( 'while deciding and withdrawing keep their own busy sentence', array( WPCPM_Institution_Request::messages()['details-busy'][1] ?? null, WPCPM_Institution_Request::school_messages()['busy'][1] ?? null ), array( 'Someone else is deciding this request at this moment, so nothing was done. Try again in a moment.', 'Someone else is deciding this request at this moment, so nothing was done. Try again in a moment.' ) );
unset( $GLOBALS['opts'][ WPCPM_Institution_Request::LOCK_PREFIX . 'details_' . $lock_school ] );
$taken = WPCPM_Institution_Request::raise_details( $lock_school, det_asked( $lock_school ), 30, 'member' );
ck( 'and once it is let go the school raises its own, and the lock is let go again', array(
	is_int( $taken ),
	array_filter( array_keys( $GLOBALS['opts'] ), static function ( $name ) { return 0 === strpos( (string) $name, 'wpcpm_req_lock_' ); } ),
), array( true, array() ) );

echo "\n=== On record now is read when the request is drawn ===\n";

det_fresh();
$focus_school = det_school( 6 );
$focus        = det_raise( $focus_school );
$facts        = WPCPM_Institution_Request::facts( $focus );

ck( 'the facts of an open details request hold the changes as rows: the detail, what the program has now, and what was asked', array(
	$facts['kind'],
	$facts['kind_label'],
	$facts['student'],
	$facts['student_name'],
	$facts['actor_name'],
	$facts['changes'],
), array(
	'details',
	'A change to its details',
	'',
	'',
	'School Representative',
	array(
		array( 'key' => 'name', 'label' => 'Name', 'now' => 'Example University', 'asked' => 'Example University of Valencia' ),
		array( 'key' => 'website', 'label' => 'Website', 'now' => 'https://example.edu', 'asked' => 'https://uev.example.edu' ),
	),
) );

// The program team renames the school in Airtable while the request waits, and the sync brings it in.
$GLOBALS['index'][ $focus_school ]['name']    = 'Example University of Costa Rica';
$GLOBALS['index'][ $focus_school ]['website'] = '';
$facts                                        = WPCPM_Institution_Request::facts( $focus );
ck( 'a detail changed in the index since the school asked shows as it is now, and what was asked has not moved', array(
	array_column( $facts['changes'], 'now' ),
	array_column( $facts['changes'], 'asked' ),
	$facts['institution_name'],
), array( array( 'Example University of Costa Rica', 'not given' ), array( 'Example University of Valencia', 'https://uev.example.edu' ), 'Example University of Costa Rica' ) );
ck( 'while the stored request still holds what was on record when it was asked', get_post_meta( $focus, WPCPM_Institution_Request::META_CHANGES, true ), det_asked( $DET ) );

$country_school  = det_school( 7 );
$country_request = det_raise( $country_school, det_asked( $country_school, array( 'country' => DET_POLAND, 'city' => '' ) ) );
$country_facts   = WPCPM_Institution_Request::facts( $country_request );
ck( 'a country is read by its name on both sides, and an emptied city as not given', array(
	array_column( $country_facts['changes'], 'label' ),
	array_column( $country_facts['changes'], 'now' ),
	array_column( $country_facts['changes'], 'asked' ),
), array(
	array( 'Name', 'Website', 'City', 'Country' ),
	array( 'Example University', 'https://example.edu', 'San Jose', 'Costa Rica' ),
	array( 'Example University of Valencia', 'https://uev.example.edu', 'not given', 'Poland' ),
) );

ck( 'a request that is closed has no table to decide on', array(
	WPCPM_Institution_Request::settle( $country_request, 'declined', 'No.', 1, 'manager' ),
	WPCPM_Institution_Request::facts( $country_request )['changes'],
), array( true, array() ) );

ck( 'a details request is drawn no Mark as handled form: its decision is the requests card\'s own', render_decisions( 1, $focus ), '' );

echo "\n=== Approve: capability, nonce, policy, then the request alone, once ===\n";

det_fresh();
$approve_school = det_school( 8 );
$approve        = det_raise( $approve_school );
$GLOBALS['mail'] = array();

ck( 'an account that may not manage the program is refused before the nonce is read', array(
	det_decide( 30, $approve, 'approve' ),
	$GLOBALS['referer'],
	det_state( $approve ),
), array( 'wp_die:You do not have permission to manage the program.', array(), 'open' ) );

// The program team changed the name in Airtable while the request waited; Approve writes what was asked.
$GLOBALS['index'][ $approve_school ]['name'] = 'Example University of Costa Rica ';
det_fresh();
$went = det_decide( 1, $approve, 'approve' );

ck( 'an Administrator\'s Approve ends on the queue, having checked the nonce keyed to that request', array( $went, $GLOBALS['referer'] ), array( $queue, array( 'wpcpm_decide_details_' . $approve ) ) );
ck( 'one PATCH holds exactly the asked values, under the sync\'s column names, in the table the setting names', $GLOBALS['patched'], array(
	array(
		'tblInstitutions1',
		array(
			array(
				'id'     => $approve_school,
				'fields' => array( 'Name' => 'Example University of Valencia', 'Website' => 'https://uev.example.edu' ),
			),
		),
	),
) );
ck( 'the index follows the base, so the dashboard shows the asked values at once', array( $GLOBALS['index'][ $approve_school ]['name'], $GLOBALS['index'][ $approve_school ]['website'] ), array( 'Example University of Valencia', 'https://uev.example.edu' ) );
ck( 'the request is closed as done with its closing moment, and its note stays empty', array(
	det_state( $approve ),
	get_post_meta( $approve, '_wpcpm_req_note', true ),
	abs( WPCPM_Institution_Request::facts( $approve )['closed_at'] - time() ) <= 2,
	(int) get_post_meta( $approve, '_wpcpm_req_actor', true ),
), array( 'done', '', true, 30 ) );

$closing = det_rows( $approve_school, 'request_resolved' );
ck( 'one request_resolved row is written, under the Administrator\'s ground, holding the state', array(
	count( $closing ),
	$closing[0]['actor'],
	$closing[0]['ground'],
	$closing[0]['data'],
	$closing[0]['message'],
), array( 1, 1, 'manager', array( 'request' => $approve, 'kind' => 'details', 'state' => 'done' ), 'An Administrator approved this request, and the details on record were updated.' ) );
ck( 'beside the details class\'s own row of the correction', count( det_rows( $approve_school, 'details_saved' ) ), 1 );

$told = det_mails( 'institution-details-approved' );
ck( 'the asking account is told, once, and only it', array( count( $GLOBALS['mail'] ), array_column( $told, 'to' ) ), array( 1, array( 'rep@example.test' ) ) );
ck( 'in the words of the approval, with what the program had when the Administrator approved it and what it reads now', array( $told[0]['subject'], $told[0]['body'] ), array(
	'[Example Site] Your institution\'s details are updated',
	"An Administrator approved the change you asked for, and the program's records now read:\r\n\r\nName: Example University of Costa Rica, now Example University of Valencia\r\nWebsite: https://example.edu, now https://uev.example.edu",
) );
ck( 'the Administrator is told, on the screen\'s own channel', flash_for( 1, 'institutions' ), 'details-approved' );
ck( 'and the request\'s lock is let go', array_filter( array_keys( $GLOBALS['opts'] ), static function ( $name ) { return 0 === strpos( (string) $name, 'wpcpm_req_lock_' ); } ), array() );
ck( 'the state was read under the lock, this request\'s copy of the meta dropped first: the lock, the cache, the base, the index, the closing, the mail, the lock let go', array_values( array_filter(
	$GLOBALS['events'],
	static function ( $event ) { return 0 !== strpos( (string) $event, 'lock select' ); }
) ), array( 'lock insert', 'cache post_meta ' . $approve, 'airtable', 'index', 'mail institution-details-approved', 'lock delete' ) );

echo "\n=== Approve: Airtable refuses, and the request stays open ===\n";

det_fresh();
$refused_school = det_school( 9 );
$refused_req    = det_raise( $refused_school );
$GLOBALS['mail'] = array();
$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'Airtable said no.' );
$logged = count( log_for( $refused_school ) );
$went   = det_decide( 1, $refused_req, 'approve' );

ck( 'the Administrator is told Airtable could not be updated and the request stays open', array( $went, flash_for( 1, 'institutions' ), det_state( $refused_req ) ), array( $queue, 'details-airtable', 'open' ) );
ck( 'nothing was written on the site: the index as it was, no closing, no row of history, no email', array(
	$GLOBALS['index'][ $refused_school ]['name'],
	WPCPM_Institution_Request::facts( $refused_req )['closed_at'],
	count( log_for( $refused_school ) ),
	$GLOBALS['mail'],
), array( 'Example University ', 0, $logged, array() ) );
ck( 'and the lock is let go, so the next press can decide it', array_filter( array_keys( $GLOBALS['opts'] ), static function ( $name ) { return 0 === strpos( (string) $name, 'wpcpm_req_lock_' ); } ), array() );

$GLOBALS['airtable'] = array();
clear_flash( 1 );
det_decide( 1, $refused_req, 'approve' );
ck( 'a base that answers with nothing written is a refusal too', array( flash_for( 1, 'institutions' ), det_state( $refused_req ), $GLOBALS['index'][ $refused_school ]['name'] ), array( 'details-airtable', 'open', 'Example University ' ) );

$GLOBALS['airtable'] = true;
clear_flash( 1 );
det_decide( 1, $refused_req, 'approve' );
ck( 'and once Airtable takes it, the same request is approved', array( flash_for( 1, 'institutions' ), det_state( $refused_req ), $GLOBALS['index'][ $refused_school ]['name'] ), array( 'details-approved', 'done', 'Example University of Valencia' ) );

echo "\n=== Approve: what the details class refuses leaves the request open too ===\n";

det_fresh();
$orphan_school = 'recDETAILS0000010';
$GLOBALS['settled'][] = $orphan_school;
$orphan_req = det_raise( $orphan_school, array( 'name' => array( 'from' => 'Example University', 'to' => 'Example University of Valencia' ) ) );
clear_flash( 1 );
$GLOBALS['mail'] = array();
det_decide( 1, $orphan_req, 'approve' );
ck( 'a school the index does not hold is not written to Airtable, and the Administrator is told why', array( flash_for( 1, 'institutions' ), det_state( $orphan_req ), $GLOBALS['patched'], $GLOBALS['mail'] ), array( 'details-index', 'open', array(), array() ) );

$stale_school = det_school( 11 );
$stale_req    = det_raise( $stale_school );
update_post_meta( $stale_req, WPCPM_Institution_Request::META_CHANGES, array( 'website' => array( 'from' => 'https://example.edu', 'to' => 'ftp://uev.example.edu' ) ) );
clear_flash( 1 );
det_decide( 1, $stale_req, 'approve' );
ck( 'a stored value that no longer meets its detail\'s rules is refused before anything is written', array( flash_for( 1, 'institutions' ), det_state( $stale_req ), $GLOBALS['patched'] ), array( 'details-refused', 'open', array() ) );

update_post_meta( $stale_req, WPCPM_Institution_Request::META_CHANGES, 'garbage' );
clear_flash( 1 );
det_decide( 1, $stale_req, 'approve' );
ck( 'and so is a request whose changes are gone', array( flash_for( 1, 'institutions' ), det_state( $stale_req ), $GLOBALS['patched'] ), array( 'details-refused', 'open', array() ) );

echo "\n=== Decline: a note of 20 to 2000 characters, kept when it is refused ===\n";

det_fresh();
$decline_school = det_school( 12 );
$decline        = det_raise( $decline_school );
$GLOBALS['mail'] = array();
$kept            = static function ( $uid, $request ) {
	return $GLOBALS['umeta'][ $uid ]['wpcpm_flash'][ WPCPM_Typed_Text::KEPT_CHANNEL . 'request-close:' . $request ]['typed'] ?? null;
};
$logged          = count( log_for( $decline_school ) );

// A box left empty, or holding only white space, has nothing to give back: nothing is kept for it.
foreach ( array(
	'no note at all'      => array( '', null ),
	'nineteen characters' => array( str_repeat( 'a', 19 ), array( 'note' => str_repeat( 'a', 19 ) ) ),
	'a note of spaces'    => array( str_repeat( ' ', 30 ), null ),
) as $why => $case ) {
	clear_flash( 1 );
	$went = det_decide( 1, $decline, 'decline', $case[0] );
	ck( sprintf( 'Decline with %s is refused with the sentence that says why, and keeps what was typed', $why ), array( $went, flash_for( 1, 'institutions' ), det_state( $decline ), $kept( 1, $decline ) ), array( $queue, 'details-note', 'open', $case[1] ) );
	unset( $GLOBALS['umeta'][1]['wpcpm_flash'] );
}

$over = str_repeat( 'a', WPCPM_Institution_Request::MAX_NOTE + 1 );
clear_flash( 1 );
det_decide( 1, $decline, 'decline', $over );
ck( 'a note over 2000 characters is refused too, and the typing is kept up to the box\'s room', array(
	flash_for( 1, 'institutions' ),
	det_state( $decline ),
	mb_strlen( (string) ( $kept( 1, $decline )['note'] ?? '' ) ),
), array( 'details-note', 'open', WPCPM_Institution_Request::MAX_NOTE ) );
unset( $GLOBALS['umeta'][1]['wpcpm_flash'] );

$lossy = 'Kids <12 free, adults >18 pay, so please say which one you mean.';
det_decide( 1, $decline, 'decline', $lossy );
ck( 'a note WordPress\'s cleaner would take words from is refused in its own sentence, and kept as typed', array( flash_for( 1, 'institutions' ), det_state( $decline ), $kept( 1, $decline ) ), array( 'details-declined-loss', 'open', array( 'note' => $lossy ) ) );
unset( $GLOBALS['umeta'][1]['wpcpm_flash'] );

ck( 'none of the refusals wrote a row, sent an email or touched Airtable', array( count( log_for( $decline_school ) ), $GLOBALS['mail'], $GLOBALS['patched'], WPCPM_Institution_Request::facts( $decline )['closed_at'] ), array( $logged, array(), array(), 0 ) );
ck( 'and the sentences are the plan\'s', array(
	WPCPM_Institution_Request::messages()['details-note'] ?? null,
	WPCPM_Institution_Request::messages()['details-declined-loss'] ?? null,
), array(
	array( 'error', 'A note of 20 to 2000 characters is needed to decline: the school reads it.' ),
	array( 'error', 'Nothing was declined, because WordPress would remove part of what you typed in "A note for the school". To keep every word, put a space after each "<" (or write "less than") and after each "%", then decline it again.' ),
) );
ck( 'whose numbers are the constants\'', array( WPCPM_Institution_Request::MIN_NOTE, WPCPM_Institution_Request::MAX_NOTE ), array( 20, 2000 ) );

$note = 'The name must match the one on your website.';
unset( $GLOBALS['umeta'][1]['wpcpm_flash'] );
$went = det_decide( 1, $decline, 'decline', $note );

ck( 'a note of the right length declines it: closed as declined, with the note, ending on the queue', array( $went, det_state( $decline ), get_post_meta( $decline, '_wpcpm_req_note', true ), flash_for( 1, 'institutions' ) ), array( $queue, 'declined', $note, 'details-declined' ) );
ck( 'nothing was written to Airtable or the index, and the school\'s details are as they were', array( $GLOBALS['patched'], $GLOBALS['index'][ $decline_school ]['name'], $GLOBALS['index'][ $decline_school ]['website'] ), array( array(), 'Example University ', 'https://example.edu' ) );
$closing = det_rows( $decline_school, 'request_resolved' );
ck( 'one request_resolved row holds the state and the Administrator\'s words, and no details row was written', array(
	count( $closing ),
	$closing[0]['ground'],
	$closing[0]['data'],
	$closing[0]['message'],
	count( det_rows( $decline_school, 'details_saved' ) ),
), array( 1, 'manager', array( 'request' => $decline, 'kind' => 'details', 'state' => 'declined' ), "An Administrator declined this request.\n" . $note, 0 ) );
$told = det_mails( 'institution-details-declined' );
ck( 'the asking account is told, once, the note in its words and the way back to ask again', array( count( $GLOBALS['mail'] ), array_column( $told, 'to' ), $told[0]['subject'], $told[0]['body'] ), array(
	1,
	array( 'rep@example.test' ),
	'[Example Site] Your request to correct your institution\'s details',
	"An Administrator did not make the change you asked for, and wrote:\r\n\r\n" . $note . "\r\n\r\nThe details on record are unchanged. You can ask again from the Institution Dashboard: https://example.test/institution-dashboard/",
) );
ck( 'the school may ask again once it is declined', is_int( det_raise( $decline_school ) ), true );

foreach ( array( 20, WPCPM_Institution_Request::MAX_NOTE ) as $length ) {
	$edge_school = det_school( 13 + $length );
	$edge        = det_raise( $edge_school );
	clear_flash( 2 );
	det_decide( 2, $edge, 'decline', str_repeat( 'b', $length ) );
	ck( sprintf( 'a note of %d characters is enough, and the whole of it is kept', $length ), array( flash_for( 2, 'institutions' ), strlen( (string) get_post_meta( $edge, '_wpcpm_req_note', true ) ) ), array( 'details-declined', $length ) );
}

det_fresh();
WPCPM_Institutions_Dashboard::$url = '';
$no_page_school = det_school( 40 );
$no_page        = det_raise( $no_page_school );
det_decide( 1, $no_page, 'decline', $note );
ck( 'while the Institution Dashboard\'s page is missing the decline names no place to ask again, and says only that the details are unchanged', array( det_mails( 'institution-details-declined' )[0]['body'] ), array( "An Administrator did not make the change you asked for, and wrote:\r\n\r\n" . $note . "\r\n\r\nThe details on record are unchanged." ) );

det_fresh();
WPCPM_Administrators_Dashboard::$url = '';
$no_admin_page = det_raise( det_school( 41 ) );
ck( 'and while the Administrator Dashboard\'s page is missing, the request email points to the queue instead', has( det_mails( 'institution-details-request' )[0]['body'], 'Approve or decline it on the Administrator Dashboard: https://example.test/wp-admin/admin.php?page=wpcpm-institutions' ), true );

echo "\n=== The school withdraws it ===\n";

det_fresh();
$withdraw_school = det_school( 20 );
$withdraw        = det_raise( $withdraw_school );
$GLOBALS['mail'] = array();

ck( 'an account that acts for another school is refused with the policy\'s one refusal, and the request stays open', array(
	det_withdraw( 9, $withdraw ),
	det_state( $withdraw ),
), array( 'wp_die:That record is not on your roster.', 'open' ) );
ck( 'so is an account that acts for none, a request that is not a details request, and one that is not there', array(
	det_withdraw( 8, $withdraw ),
	det_withdraw( 30, $typed_req ),
	det_withdraw( 30, 999999 ),
), array( 'wp_die:That record is not on your roster.', 'wp_die:That record is not on your roster.', 'wp_die:That record is not on your roster.' ) );
$GLOBALS['settled'] = array_values( array_diff( $GLOBALS['settled'], array( $withdraw_school ) ) );
ck( 'and so is a school whose agreement is not settled', det_withdraw( 30, $withdraw ), 'wp_die:That record is not on your roster.' );
$GLOBALS['settled'][] = $withdraw_school;

ck( 'a member of the school withdraws it, having checked the nonce keyed to that request, and is sent back where the press was made', array(
	det_withdraw( 31, $withdraw ),
	$GLOBALS['referer'],
), array( 'redirect:' . $GLOBALS['back'], array( 'wpcpm_withdraw_details_' . $withdraw ) ) );
ck( 'the request is closed as withdrawn with its closing moment, and the school hears of it on the school\'s own channel', array(
	det_state( $withdraw ),
	abs( WPCPM_Institution_Request::facts( $withdraw )['closed_at'] - time() ) <= 2,
	flash_for( 31, 'institution_details' ),
	WPCPM_Institution_Request::school_messages()['withdrawn'] ?? null,
), array( 'withdrawn', true, array( 'status' => 'withdrawn' ), array( 'success', 'The request is withdrawn.' ) ) );
$closing = det_rows( $withdraw_school, 'request_resolved' );
ck( 'one request_resolved row is written under the member ground, by the member, holding the state', array(
	count( $closing ),
	$closing[0]['actor'],
	$closing[0]['ground'],
	$closing[0]['data'],
	$closing[0]['message'],
), array( 1, 31, 'member', array( 'request' => $withdraw, 'kind' => 'details', 'state' => 'withdrawn' ), 'The institution withdrew this request.' ) );
$told = det_mails( 'institution-details-withdrawn' );
ck( 'the people the setting names are emailed, once each, who withdrew it and for which school', array( count( $GLOBALS['mail'] ), array_column( $told, 'to' ), $told[0]['subject'], $told[0]['body'] ), array(
	2,
	array( 'admins@example.test', 'manager@example.test' ),
	'[Example Site] Example University withdrew its request',
	"Another Representative withdrew the request to correct Example University's details. Nothing waits for you.",
) );
ck( 'nothing was written to Airtable or the index', array( $GLOBALS['patched'], $GLOBALS['index'][ $withdraw_school ]['name'] ), array( array(), 'Example University ' ) );
ck( 'it is not open any more and is among the recently closed, and the school may ask again', array(
	WPCPM_Institution_Request::open_details_for( $withdraw_school ),
	in_array( $withdraw, WPCPM_Institution_Request::closed_requests(), true ),
	in_array( $withdraw, WPCPM_Institution_Request::open_requests( 200 ), true ),
	WPCPM_Institution_Request::facts( $withdraw )['state'],
), array( 0, true, false, 'withdrawn' ) );

// A withdrawal reads by who withdrew it. `settle()` is handed the actor's ground and stores it on the
// closing row, which is where the history reads it (`WPCPM_Institution_History`), so the row's own sentence
// says it too: an Administrator on the manager ground, the school on any other.
det_fresh();
$admin_school  = det_school( 21 );
$admin_request = det_raise( $admin_school );
$GLOBALS['mail'] = array();
det_withdraw( 1, $admin_request );
$closing = det_rows( $admin_school, 'request_resolved' );
ck( 'an Administrator\'s withdrawal is written under the manager ground, by the Administrator, in the Administrator\'s words', array(
	det_state( $admin_request ),
	count( $closing ),
	$closing[0]['actor'],
	$closing[0]['ground'],
	$closing[0]['data'],
	$closing[0]['message'],
), array( 'withdrawn', 1, 1, 'manager', array( 'request' => $admin_request, 'kind' => 'details', 'state' => 'withdrawn' ), 'An Administrator withdrew this request.' ) );

$ground_cases = array(
	'a member\'s ground'         => array( 31, 'member', 'The institution withdrew this request.' ),
	'the system\'s ground'       => array( 0, 'system', 'The institution withdrew this request.' ),
	'a ground that is not one'   => array( 31, 'not-a-ground', 'The institution withdrew this request.' ),
	'no ground, by a member'     => array( 31, '', 'The institution withdrew this request.' ),
	'no ground, by the system'   => array( 0, '', 'The institution withdrew this request.' ),
	'no ground, by a manager'    => array( 1, '', 'An Administrator withdrew this request.' ),
	'the manager ground'         => array( 31, 'manager', 'An Administrator withdrew this request.' ),
);
foreach ( $ground_cases as $label => $case ) {
	det_fresh();
	$ground_school  = det_school( 80 + count( $GLOBALS['index'] ) );
	$ground_request = det_raise( $ground_school );
	$closed         = WPCPM_Institution_Request::settle( $ground_request, WPCPM_Institution_Request::STATE_WITHDRAWN, '', $case[0], $case[1] );
	$closing        = det_rows( $ground_school, 'request_resolved' );
	ck( sprintf( 'settle() withdrawn on %s says "%s"', $label, $case[2] ), array( $closed, $closing[0]['message'] ), array( true, $case[2] ) );
}
ck( 'the ground changes the withdrawal\'s words and no other state\'s', array(
	( static function () {
		det_fresh();
		$school  = det_school( 99 );
		$request = det_raise( $school );
		WPCPM_Institution_Request::settle( $request, WPCPM_Institution_Request::STATE_DECLINED, str_repeat( 'n', 25 ), 1, 'manager' );
		return det_rows( $school, 'request_resolved' )[0]['message'];
	} )(),
	( static function () {
		det_fresh();
		$school  = det_school( 98 );
		$request = det_raise( $school );
		WPCPM_Institution_Request::settle( $request, WPCPM_Institution_Request::STATE_DONE, '', 31, 'member' );
		return det_rows( $school, 'request_resolved' )[0]['message'];
	} )(),
), array( "An Administrator declined this request.\n" . str_repeat( 'n', 25 ), 'An Administrator approved this request, and the details on record were updated.' ) );

// A decision on a request an Administrator withdrew is told so: "the school withdrew it" would be untrue. The
// ground it was closed on is kept on the request (`META_CLOSED_GROUND`, the ground `settle()` resolved and wrote
// to the row), so the early Decline check and the locked one read it with the state and cost no search of the
// history. A request withdrawn before the ground was kept reads as the school's.
ck( 'the sentences for a withdrawn request are the school\'s and the Administrator\'s', array(
	WPCPM_Institution_Request::messages()['details-withdrawn'] ?? null,
	WPCPM_Institution_Request::messages()['details-withdrawn-admin'] ?? null,
), array(
	array( 'info', 'Nothing was changed: the school withdrew this request.' ),
	array( 'info', 'Nothing was changed: an Administrator withdrew this request.' ),
) );
$by_whom = array(
	'an Administrator'       => array( 1, 'details-withdrawn-admin', 'manager' ),
	'a member of the school' => array( 31, 'details-withdrawn', 'member' ),
);
foreach ( $by_whom as $who => $case ) {
	foreach ( array( 'approve' => '', 'decline' => $note, 'decline with no note' => '' ) as $decision => $text ) {
		det_fresh();
		$by_school  = det_school( 400 + count( $GLOBALS['posts'] ) );
		$by_request = det_raise( $by_school );
		det_withdraw( $case[0], $by_request );
		$GLOBALS['mail'] = array();
		clear_flash( 2 );
		det_decide( 2, $by_request, 0 === strpos( $decision, 'approve' ) ? 'approve' : 'decline', $text );
		ck( sprintf( '%s after %s withdrew it is told so, and does nothing', $decision, $who ), array(
			flash_for( 2, 'institutions' ),
			get_post_meta( $by_request, '_wpcpm_req_closed_ground', true ),
			det_state( $by_request ),
			$GLOBALS['patched'],
			$GLOBALS['mail'],
		), array( $case[1], $case[2], 'withdrawn', array(), array() ) );
	}
}
det_fresh();
$old_school  = det_school( 480 );
$old_request = det_raise( $old_school );
det_withdraw( 1, $old_request );
delete_post_meta( $old_request, '_wpcpm_req_closed_ground' );
clear_flash( 2 );
det_decide( 2, $old_request, 'approve' );
ck( 'a request withdrawn before the ground was kept reads as the school\'s', flash_for( 2, 'institutions' ), 'details-withdrawn' );

det_fresh();
$GLOBALS['mail'] = array();
$withdraw_body_src = (string) method_body( $src, 'closing_message' );
ck( 'the words are chosen from the ground settle() derives, which is the one the row is written under', array(
	has( $withdraw_body_src, 'GROUND_MANAGER' ),
	has( (string) method_body( $src, 'settle' ), 'closing_message( $kind, $state, $ground )' ),
), array( true, true ) );

foreach ( array( 'approve' => array( 'details-withdrawn', '' ), 'decline' => array( 'details-withdrawn', $note ) ) as $decision => $case ) {
	det_fresh();
	$GLOBALS['mail'] = array();
	$logged          = count( log_for( $withdraw_school ) );
	det_decide( 2, $withdraw, $decision, $case[1] );
	ck( sprintf( '%s afterwards is refused in the sentence that says the school withdrew it, and does nothing', ucfirst( $decision ) ), array(
		flash_for( 2, 'institutions' ),
		det_state( $withdraw ),
		$GLOBALS['patched'],
		$GLOBALS['mail'],
		count( log_for( $withdraw_school ) ),
	), array( $case[0], 'withdrawn', array(), array(), $logged ) );
}
ck( 'that sentence is the plan\'s', WPCPM_Institution_Request::messages()['details-withdrawn'] ?? null, array( 'info', 'Nothing was changed: the school withdrew this request.' ) );

det_fresh();

// The card draws Withdraw it with the card it came from (`WPCPM_Institution_Request::RETURN_FIELD`), so a
// withdrawal lands back on the card and not at the top of the page. Only that one anchor is followed: a
// posted value is a claim, and the handler's own list of places decides.
ck( 'the names of the return are the request class\'s', array( WPCPM_Institution_Request::RETURN_FIELD, WPCPM_Institution_Request::DETAILS_ANCHOR ), array( 'wpcpm_details_return', 'wpcpm-details' ) );
$return_school  = det_school( 60 );
$return_request = det_raise( $return_school );
ck( 'a withdrawal posted with the card it came from lands back on that card, on the page it was pressed on', det_withdraw( 31, $return_request, array( 'wpcpm_details_return' => 'wpcpm-details' ) ), 'redirect:' . $GLOBALS['back'] . '#wpcpm-details' );
$return_second = det_raise( det_school( 61 ), array(), 30 );
$GLOBALS['back'] = '';
ck( 'with no page to return to it lands on the Institution Dashboard\'s card', det_withdraw( 31, $return_second, array( 'wpcpm_details_return' => 'wpcpm-details' ) ), 'redirect:https://example.test/institution-dashboard/#wpcpm-details' );
$GLOBALS['back'] = 'https://example.test/institution-dashboard/?wpcpm_institution_student=21';
$return_third = det_raise( det_school( 62 ) );
ck( 'a press that finds the request already decided lands on the card too', array( det_withdraw( 30, $return_third, array( 'wpcpm_details_return' => 'wpcpm-details' ) ), WPCPM_Institution_Request::STATE_WITHDRAWN === det_state( $return_third ) ), array( 'redirect:' . $GLOBALS['back'] . '#wpcpm-details', true ) );
$return_fourth = det_raise( det_school( 63 ) );
ck( 'a place the handler does not list, or none, is not followed: the page it was pressed on, as before', array(
	det_withdraw( 30, $return_fourth, array( 'wpcpm_details_return' => 'wpcpm-people' ) ),
	det_withdraw( 30, $return_fourth ),
	det_withdraw( 30, $return_fourth, array( 'wpcpm_details_return' => 'https://elsewhere.example/#wpcpm-details' ) ),
), array( 'redirect:' . $GLOBALS['back'], 'redirect:' . $GLOBALS['back'], 'redirect:' . $GLOBALS['back'] ) );
$GLOBALS['back'] = 'https://example.test/institution-dashboard/?wpcpm_institution_student=21#old';
$return_fifth = det_raise( det_school( 64 ) );
ck( 'a page that already names a place has it replaced, never doubled', det_withdraw( 30, $return_fifth, array( 'wpcpm_details_return' => 'wpcpm-details' ) ), 'redirect:https://example.test/institution-dashboard/?wpcpm_institution_student=21#wpcpm-details' );
$GLOBALS['back'] = 'https://example.test/institution-dashboard/?wpcpm_institution_student=21';


det_fresh();
$closed_school = det_school( 21 );
$closed_req    = det_raise( $closed_school );
det_decide( 1, $closed_req, 'decline', $note );
clear_flash( 30 );
$GLOBALS['mail'] = array();
det_withdraw( 30, $closed_req );
ck( 'a withdrawal of a request an Administrator has decided is told so, and changes nothing', array(
	flash_for( 30, 'institution_details' ),
	det_state( $closed_req ),
	$GLOBALS['mail'],
	WPCPM_Institution_Request::school_messages()['closed'] ?? null,
), array( array( 'status' => 'closed' ), 'declined', array(), array( 'info', 'Nothing was changed: an Administrator had already decided that request.' ) ) );

// A second Withdraw it, from another account of the school or a second tab, finds the request already
// withdrawn. Nobody decided it, so the sentence says it had been withdrawn, and nothing is written twice.
det_fresh();
$twice_school = det_school( 24 );
$twice_req    = det_raise( $twice_school );
det_withdraw( 31, $twice_req );
clear_flash( 30 );
$GLOBALS['mail'] = array();
$closing_rows    = count( det_rows( $twice_school, 'request_resolved' ) );
det_withdraw( 30, $twice_req );
ck( 'a second withdrawal of a request already withdrawn says it had been withdrawn, not that an Administrator decided it, and changes nothing', array(
	flash_for( 30, 'institution_details' ),
	det_state( $twice_req ),
	$GLOBALS['mail'],
	count( det_rows( $twice_school, 'request_resolved' ) ) - $closing_rows,
	WPCPM_Institution_Request::school_messages()['withdrawn-already'] ?? null,
), array( array( 'status' => 'withdrawn-already' ), 'withdrawn', array(), 0, array( 'info', 'Nothing was changed: that request had already been withdrawn.' ) ) );
clear_flash( 30 );
det_withdraw( 1, $twice_req );
ck( 'and so is an Administrator\'s press on it', flash_for( 1, 'institution_details' ), array( 'status' => 'withdrawn-already' ) );

det_fresh();
$admin_withdraw_school = det_school( 22 );
$admin_withdraw        = det_raise( $admin_withdraw_school );
det_withdraw( 1, $admin_withdraw );
ck( 'an Administrator viewing the school may withdraw it too, under the manager ground', array( det_state( $admin_withdraw ), det_rows( $admin_withdraw_school, 'request_resolved' )[0]['ground'] ), array( 'withdrawn', 'manager' ) );

det_fresh();
$nameless_school = det_school( 23 );
$GLOBALS['memberships'][32] = array( $nameless_school );
$nameless_req               = det_raise( $nameless_school, array(), 32 );
ck( 'an account that holds no name is "a member of the institution" in the emails that name it', has( det_mails( 'institution-details-request' )[0]['body'], 'A member of the institution asks to correct Example University\'s details:' ), true );
$GLOBALS['mail'] = array();
det_withdraw( 32, $nameless_req );
ck( 'and when it withdraws', has( det_mails( 'institution-details-withdrawn' )[0]['body'], 'A member of the institution withdrew the request to correct' ), true );

echo "\n=== Two Administrators decide one request at once: only the first does ===\n";

det_fresh();
$race_school = det_school( 30 );
$race        = det_raise( $race_school );
$GLOBALS['mail'] = array();
det_decide( 1, $race, 'approve' );
clear_flash( 2 );
det_decide( 2, $race, 'decline', $note );

ck( 'Approve in one tab and Decline in the other leave one Airtable write, one email and one closing row', array(
	count( $GLOBALS['patched'] ),
	count( det_mails( 'institution-details-approved' ) ),
	count( det_mails( 'institution-details-declined' ) ),
	count( $GLOBALS['mail'] ),
	count( det_rows( $race_school, 'request_resolved' ) ),
	det_state( $race ),
), array( 1, 1, 0, 1, 1, 'done' ) );
ck( 'and the second is refused by the state it reads under the lock: nothing was changed, the request had already been closed', array( flash_for( 2, 'institutions' ), WPCPM_Institution_Request::messages()['request-closed'] ), array( 'request-closed', array( 'info', 'Nothing was changed: that request had already been closed.' ) ) );
ck( 'its note was not stored', get_post_meta( $race, '_wpcpm_req_note', true ), '' );

det_fresh();
$race_school = det_school( 31 );
$race        = det_raise( $race_school );
$GLOBALS['mail'] = array();
det_decide( 2, $race, 'decline', $note );
clear_flash( 1 );
det_decide( 1, $race, 'approve' );
ck( 'the other way round, Decline first, Approve is refused: no Airtable write, one email, one closing row', array(
	flash_for( 1, 'institutions' ),
	count( $GLOBALS['patched'] ),
	count( $GLOBALS['mail'] ),
	count( det_rows( $race_school, 'request_resolved' ) ),
	det_state( $race ),
	$GLOBALS['index'][ $race_school ]['name'],
), array( 'request-closed', 0, 1, 1, 'declined', 'Example University ' ) );

det_fresh();
$busy_school = det_school( 32 );
$busy        = det_raise( $busy_school );
$GLOBALS['mail'] = array();
$GLOBALS['opts'][ WPCPM_Institution_Request::lock_name( $busy ) ] = (string) ( time() + 300 );
$logged = count( log_for( $busy_school ) );

clear_flash( 1 );
det_decide( 1, $busy, 'approve' );
$approve_busy = flash_for( 1, 'institutions' );
clear_flash( 1 );
$went_busy = det_decide( 1, $busy, 'decline', $note );
ck( 'while another request holds the request\'s lock, Approve and Decline each do nothing and say so', array(
	$approve_busy,
	flash_for( 1, 'institutions' ),
	$went_busy,
	det_state( $busy ),
	$GLOBALS['patched'],
	$GLOBALS['mail'],
	count( log_for( $busy_school ) ),
), array( 'details-busy', 'details-busy', $queue, 'open', array(), array(), $logged ) );
ck( 'and a declined note is given back to its box, to try again', $kept( 1, $busy ), array( 'note' => $note ) );
ck( 'the lock another request holds is not let go by the one that was refused', isset( $GLOBALS['opts'][ WPCPM_Institution_Request::lock_name( $busy ) ] ), true );
ck( 'the lock is one per request, under the prefix the uninstaller sweeps, held five minutes at most', array(
	WPCPM_Institution_Request::lock_name( $busy ),
	WPCPM_Institution_Request::LOCK_PREFIX,
	WPCPM_Institution_Request::LOCK_TTL,
), array( 'wpcpm_req_lock_' . $busy, 'wpcpm_req_lock_', 300 ) );
ck( 'the sentence for it is the plan\'s kind: nothing was done, and what to do', WPCPM_Institution_Request::messages()['details-busy'] ?? null, array( 'error', 'Someone else is deciding this request at this moment, so nothing was done. Try again in a moment.' ) );
unset( $GLOBALS['opts'][ WPCPM_Institution_Request::lock_name( $busy ) ] );

echo "\n=== Decline reads the request's state before the note ===\n";

// A request already closed gets the sentence for its state, whatever the note posted with Decline: a tab left
// open on a request the school withdrew, or another Administrator closed, would otherwise be told its note
// was too short, and rewrite it for a request nothing can be done with.
$closed_by = array(
	'withdrawn by the school'      => array( 'details-withdrawn', static function ( $request ) { det_withdraw( 31, $request ); } ),
	'approved by an Administrator' => array( 'request-closed', static function ( $request ) { det_decide( 1, $request, 'approve' ); } ),
	'declined by an Administrator' => array( 'request-closed', static function ( $request ) use ( $note ) { det_decide( 1, $request, 'decline', $note ); } ),
);
$bad_notes = array(
	'no note at all'       => '',
	'nineteen characters'  => str_repeat( 'a', 19 ),
	'a note of spaces'     => str_repeat( ' ', 30 ),
	'a note over the limit' => str_repeat( 'a', WPCPM_Institution_Request::MAX_NOTE + 1 ),
	'a note the cleaner would take words from' => 'Kids <12 free, adults >18 pay, so please say which one you mean.',
);
foreach ( $closed_by as $how => $case ) {
	foreach ( $bad_notes as $why => $text ) {
		det_fresh();
		$state_school  = det_school( 200 + count( $GLOBALS['posts'] ) );
		$state_request = det_raise( $state_school );
		call_user_func( $case[1], $state_request );
		$GLOBALS['mail'] = array();
		$state_before    = array( det_state( $state_request ), count( log_for( $state_school ) ), $GLOBALS['patched'] );
		clear_flash( 2 );
		det_decide( 2, $state_request, 'decline', $text );
		ck(
			sprintf( 'Decline with %s on a request %s says what its state says, and does nothing', $why, $how ),
			array( flash_for( 2, 'institutions' ), array( det_state( $state_request ), count( log_for( $state_school ) ), $GLOBALS['patched'] ), $GLOBALS['mail'], $kept( 2, $state_request ) ),
			array( $case[0], $state_before, array(), null )
		);
	}
}
ck( 'the note\'s own refusals still come first on a request that is open', ( static function () use ( $note ) {
	det_fresh();
	$open_school  = det_school( 300 );
	$open_request = det_raise( $open_school );
	clear_flash( 2 );
	det_decide( 2, $open_request, 'decline', 'too short' );
	return array( flash_for( 2, 'institutions' ), det_state( $open_request ) );
} )(), array( 'details-note', 'open' ) );
ck( 'and the state is read after the policy and before the note, in the handler', array(
	before( $decide_body_early = (string) method_body( $src, 'handle_details_decide' ), 'WPCPM_Institution_Policy::decide', 'self::decided_status(' ),
	before( $decide_body_early, 'self::decided_status(', 'cleaner_loses' ),
	before( $decide_body_early, 'self::decided_status(', 'MIN_NOTE' ),
), array( true, true, true ) );

echo "\n=== The other kinds are decided by their own handler, and a details request by its own ===\n";

det_fresh();
$own_school = det_school( 33 );
$own        = det_raise( $own_school );
clear_flash( 1 );
$GLOBALS['uid'] = 1;
$_POST          = array( 'request' => $own, 'state' => 'done', 'wpcpm_request_note' => 'Handled by hand.' );
run( 'handle_resolve' );
ck( 'Mark as handled cannot close a details request: it would close it without writing a word to the base', array( flash_for( 1, 'institutions' ), det_state( $own ), $GLOBALS['patched'] ), array( 'request-state', 'open', array() ) );

clear_flash( 1 );
$mentor_req = seed_open( 'mentor', $A, 'recS0000000000201' );
det_decide( 1, $mentor_req, 'approve' );
ck( 'and the details decision cannot close a request of another kind', array( flash_for( 1, 'institutions' ), det_state( $mentor_req ) ), array( 'request-state', 'open' ) );
clear_flash( 1 );
det_withdraw( 1, $mentor_req );
ck( 'nor can the withdrawal', array( det_state( $mentor_req ), flash_for( 1, 'institutions' ) ), array( 'open', null ) );

clear_flash( 1 );
det_decide( 1, $own, 'reopen' );
ck( 'a decision that is neither Approve nor Decline is told so, and nothing reopens a row', array( flash_for( 1, 'institutions' ), det_state( $own ) ), array( 'request-state', 'open' ) );
clear_flash( 1 );
det_decide( 1, 999999, 'approve' );
ck( 'and a request that is not there says so', flash_for( 1, 'institutions' ), 'request-gone' );

echo "\n=== What a school was last told ===\n";

det_fresh();
$outcome_school = det_school( 50 );
ck( 'a school that has had no request has no outcome', WPCPM_Institution_Request::latest_details_outcome( $outcome_school ), array() );

$old = det_raise( $outcome_school );
ck( 'nor has one whose request still waits', WPCPM_Institution_Request::latest_details_outcome( $outcome_school ), array() );
det_decide( 1, $old, 'approve' );
update_post_meta( $old, WPCPM_Institution_Request::META_CLOSED_AT, time() - 100 );
$outcome = WPCPM_Institution_Request::latest_details_outcome( $outcome_school );
ck( 'an approval reads as done, with the moment it was closed and no note', array_keys( $outcome ) === array( 'state', 'closed_at', 'note' ) ? array( $outcome['state'], $outcome['closed_at'], $outcome['note'] ) : $outcome, array( 'done', time() - 100, '' ) );

$new = det_raise( $outcome_school, det_asked( $outcome_school, array( 'city' => 'Heredia' ) ) );
det_decide( 1, $new, 'decline', $note );
$outcome = WPCPM_Institution_Request::latest_details_outcome( $outcome_school );
ck( 'the latest of two is the one closed last, and a decline carries the Administrator\'s note', array( $outcome['state'], $outcome['note'] ), array( 'declined', $note ) );

update_post_meta( $new, WPCPM_Institution_Request::META_CLOSED_AT, time() - 200 );
ck( 'by the moment it was closed and not by the order it was raised', WPCPM_Institution_Request::latest_details_outcome( $outcome_school )['state'], 'done' );

$third = det_raise( $outcome_school, det_asked( $outcome_school, array( 'city' => 'Cartago' ) ) );
det_withdraw( 30, $third );
ck( 'a withdrawal is the latest outcome too, so the school is shown nothing after it', WPCPM_Institution_Request::latest_details_outcome( $outcome_school )['state'], 'withdrawn' );
ck( 'another school\'s outcome is its own, and an ID that is not a record has none', array(
	WPCPM_Institution_Request::latest_details_outcome( det_school( 51 ) ),
	WPCPM_Institution_Request::latest_details_outcome( 'not-a-record' ),
	WPCPM_Institution_Request::latest_details_outcome( strtolower( $outcome_school ) ),
), array( array(), array(), array() ) );

echo "\n=== The order of the checks in the details handlers ===\n";

$decide_body   = (string) method_body( $src, 'handle_details_decide' );
$withdraw_body = (string) method_body( $src, 'handle_details_withdraw' );
$locked_body   = (string) method_body( $src, 'locked' );

ck( 'Approve and Decline check the capability before the nonce, the nonce before the policy, and the policy before the lock', array(
	before( $decide_body, 'current_user_can', 'check_admin_referer' ),
	before( $decide_body, 'check_admin_referer', 'WPCPM_Institution_Policy::decide' ),
	before( $decide_body, 'WPCPM_Institution_Policy::decide', 'self::locked(' ),
	has( $decide_body, 'ACT_EDIT_DETAILS' ),
	has( $decide_body, 'subject_post( $post, self::META_INSTITUTION )' ),
), array( true, true, true, true, true ) );
ck( 'the withdrawal checks the nonce, then the policy, then the lock, and asks no capability: it is the school\'s', array(
	before( $withdraw_body, 'check_admin_referer', 'WPCPM_Institution_Policy::decide' ),
	before( $withdraw_body, 'WPCPM_Institution_Policy::decide', 'self::locked(' ),
	has( $withdraw_body, 'ACT_EDIT_DETAILS' ),
	has( $withdraw_body, 'current_user_can' ),
), array( true, true, true, false ) );
ck( 'both refuse on the decision\'s own answer, and read the institution from the request\'s meta, never from the form', array(
	(bool) preg_match( '/if \( empty\( \$decision\[\'allowed\'\] \) \) \{\s*wp_die\(/', $decide_body ),
	(bool) preg_match( '/if \( empty\( \$decision\[\'allowed\'\] \) \) \{\s*wp_die\(/', $withdraw_body ),
	preg_match( '/posted_text\(\s*.(record|institution)/', $decide_body . $withdraw_body ),
), array( true, true, 0 ) );
ck( 'the lock is taken, this request\'s copy of the meta dropped, and only then the work done and the lock let go in a finally', array(
	before( $locked_body, 'WPCPM_Lock::take', 'wp_cache_delete' ),
	before( $locked_body, 'wp_cache_delete', 'call_user_func' ),
	before( $locked_body, 'try {', 'finally {' ),
	before( $locked_body, 'finally {', 'WPCPM_Lock::release' ),
), array( true, true, true, true ) );
ck( 'and the outcome is left with after the lock is let go, never inside the try: the redirect ends the request and runs no finally', array(
	before( $decide_body, 'self::locked(', 'self::finish( $status )' ),
	before( $withdraw_body, 'self::locked(', 'self::bounce_details(' ),
	has( $locked_body, 'finish(' ),
	has( $locked_body, 'bounce_details(' ),
), array( true, true, false, false ) );
ck( 'the state is read inside the work and not before the lock', array(
	has( $decide_body, 'META_STATE' ),
	has( $withdraw_body, 'META_STATE' ),
	substr_count( $src, 'get_post_meta( $post_id, self::META_STATE, true )' ) >= 2,
), array( false, false, true ) );

echo "\n=== The words ===\n";

ck( 'the Administrator\'s sentences for a details request are the plan\'s', array(
	WPCPM_Institution_Request::messages()['details-approved'] ?? null,
	WPCPM_Institution_Request::messages()['details-declined'] ?? null,
	WPCPM_Institution_Request::messages()['details-airtable'] ?? null,
), array(
	array( 'success', 'The records are updated and the school is told.' ),
	array( 'success', 'The request is declined and the school is told.' ),
	array( 'error', 'Airtable could not be updated, so nothing was changed here either. The request stays open.' ),
) );
ck( 'and the two the details class\'s refusals get are their own', array(
	WPCPM_Institution_Request::messages()['details-index'] ?? null,
	WPCPM_Institution_Request::messages()['details-refused'] ?? null,
), array(
	array( 'error', 'This institution is not in the program records yet, so nothing was changed. The request stays open.' ),
	array( 'error', 'A value in this request no longer meets the rules for its detail, so nothing was changed. The request stays open: decline it with a note.' ),
) );
ck( 'the school\'s busy sentence is the Administrator\'s', WPCPM_Institution_Request::school_messages()['busy'] ?? null, array( 'error', 'Someone else is deciding this request at this moment, so nothing was done. Try again in a moment.' ) );

echo "\n=== A details request is closed with a plain history row ===\n";

$history_words = array();
foreach ( array_merge( log_for( $race_school ), log_for( $withdraw_school ), log_for( $approve_school ), log_for( $decline_school ) ) as $row ) {
	if ( in_array( $row['kind'], array( 'request_raised', 'request_resolved' ), true ) ) {
		$history_words[] = $row['message'];
	}
}
ck( 'no row names a record ID, a slug or the plugin, so the school\'s history can show it as written', array_values( array_filter( $history_words, static function ( $words ) {
	return 1 === preg_match( '/rec[A-Za-z0-9]{14}|wpcpm|_/', $words );
} ) ), array() );


echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
