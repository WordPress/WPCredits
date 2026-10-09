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
function get_post_time( $f, $gmt = false, $post = null ) { return strtotime( $post->post_date_gmt . ' UTC' ); }
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
	/** Contract: the flash channel the manager screen reads its outcomes from. */
	class WPCPM_Institutions {
		const FLASH = 'institutions';
	}
}

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
/** One student's card block, drawn for one reader. */
function render_block( $viewer, $record, $student ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_Request::render_student( $record, $student );
	return (string) ob_get_clean();
}
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

ck( 'all three kinds exist from the day the store does', WPCPM_Institution_Request::kinds(), array( 'add', 'mentor', 'format' ) );
ck( 'and three states', WPCPM_Institution_Request::states(), array( 'open', 'done', 'declined' ) );
ck( 'both closed states are terminal', WPCPM_Institution_Request::transitions(), array(
	'open'     => array( 'done', 'declined' ),
	'done'     => array(),
	'declined' => array(),
) );
ck( 'an open row may be handled or declined', array(
	WPCPM_Institution_Request::can_settle( 'open', 'done' ),
	WPCPM_Institution_Request::can_settle( 'open', 'declined' ),
), array( true, true ) );
ck( 'and nothing may follow a closed one, or precede an open one', array(
	WPCPM_Institution_Request::can_settle( 'done', 'open' ),
	WPCPM_Institution_Request::can_settle( 'declined', 'done' ),
	WPCPM_Institution_Request::can_settle( 'open', 'open' ),
	WPCPM_Institution_Request::can_settle( '', 'done' ),
), array( false, false, false, false ) );

$bad = array(
	WPCPM_Institution_Request::raise( 'reenrol', $A, $S1, 7 ),
	WPCPM_Institution_Request::raise( 'mentor', 'not-a-record', $S1, 7 ),
	WPCPM_Institution_Request::raise( 'mentor', $A, '', 7 ),
);
ck( 'raise() refuses an unknown kind, an unknown institution and a missing student', array_map( static function ( $e ) {
	return is_wp_error( $e ) ? $e->get_error_code() : $e;
}, $bad ), array( 'wpcpm_request_kind', 'wpcpm_request_institution', 'wpcpm_request_student' ) );
ck( 'and none of them wrote a row', stored(), array() );

/* ---- the card ------------------------------------------------------------ */

echo "\n=== The card offers the control to the school whose student it is ===\n";

$card = render_block( 7, $A, $S1 );
ck( 'a member sees the control for their own student with no mentor', has( $card, 'Ask for a mentor' ), true );
ck( 'the form posts the action and the student and nothing else', array(
	has( $card, 'name="action" value="wpcpm_request_mentor"' ),
	has( $card, 'name="student" value="' . $S1 . '"' ),
	has( $card, $A ),
), array( true, true, false ) );
ck( 'and the nonce is keyed to that student', has( $card, 'value="nonce-wpcpm_request_mentor_' . $S1 . '"' ), true );

ck( 'a student who has a mentor is drawn nothing at all', render_block( 7, $A, $S2 ), '' );
ck( 'another institution\'s member is drawn nothing at all', render_block( 9, $A, $S1 ), '' );
ck( 'and neither is a reader looking at a record that is not a record', render_block( 7, $A, 'not-a-record' ), '' );

// A manager passes the fence for any institution, and the control they get is the same one:
// this is the school's control, drawn on the school's card, and a manager reading over their
// shoulder should see what the school sees.
ck( 'a program manager sees it too', has( render_block( 1, $A, $S1 ), 'Ask for a mentor' ), true );

/* ---- raising ------------------------------------------------------------- */

echo "\n=== A member raises one for their own student ===\n";

$GLOBALS['uid']     = 7;
$GLOBALS['referer'] = array();
$_POST              = array( 'student' => $S1 );

ck( 'the handler ends in a redirect back to the card', run( 'handle_request' ), 'redirect:' . $GLOBALS['back'] );
ck( 'the nonce it checked was keyed to the student', $GLOBALS['referer'], array( 'wpcpm_request_mentor_' . $S1 ) );

$rows = stored();
$one  = array_values( $rows );
ck( 'one row was written', count( $rows ), 1 );
ck( 'with the kind, the institution, the student, the state and who raised it', $one[0], array(
	'kind'        => 'mentor',
	'institution' => $A,
	'student'     => $S1,
	'state'       => 'open',
	'note'        => '',
	'actor'       => 7,
) );
ck( 'the member is told', flash_for( 7, 'institution_request' ), array( 'status' => 'raised', 'student' => $S1 ) );

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

echo "\n=== And cannot raise one for another institution's student ===\n";

$GLOBALS['uid'] = 9;
$_POST          = array( 'student' => $S1 );
$refused        = run( 'handle_request' );

ck( 'it is refused with the one message', $refused, 'wp_die:That record is not on your roster.' );

$GLOBALS['uid'] = 9;
$_POST          = array( 'student' => $S9 );
ck( 'and a record on nobody\'s roster gets the same message, byte for byte', run( 'handle_request' ), $refused );

$GLOBALS['uid'] = 9;
$_POST          = array( 'student' => 'not-a-record' );
ck( 'as does a value that is not a record ID', run( 'handle_request' ), $refused );

// The manager path through the same door: the fence passes, and there is still nothing to
// file the request under, so it is refused rather than filed against an empty institution.
$GLOBALS['uid'] = 1;
$_POST          = array( 'student' => $S9 );
ck( 'a manager acting on a student no roster holds is refused too', run( 'handle_request' ), $refused );

ck( 'none of the four wrote anything', count( stored() ), 1 );

echo "\n=== A second press finds the open one rather than making a second ===\n";

$GLOBALS['uid'] = 7;
clear_flash( 7 );
$_POST = array( 'student' => $S1 );
run( 'handle_request' );

ck( 'still one row', count( stored() ), 1 );
ck( 'and the member is told there already is one', flash_for( 7, 'institution_request' )['status'], 'already' );
ck( 'with no second audit row', count( log_for( $A ) ), 1 );

// The store refuses it too, not only the handler: the import calls `raise()` directly and
// one open row per student is a property of the store rather than of one form.
$again = WPCPM_Institution_Request::raise( 'mentor', $A, $S1, 7 );
ck( 'raise() refuses the duplicate on its own', is_wp_error( $again ) ? $again->get_error_code() : $again, 'wpcpm_request_open' );

echo "\n=== A student who already has a mentor is not asked about ===\n";

$GLOBALS['uid'] = 7;
clear_flash( 7 );
$_POST = array( 'student' => $S2 );
run( 'handle_request' );

ck( 'nothing was written', count( stored() ), 1 );
ck( 'and the member is told why', flash_for( 7, 'institution_request' )['status'], 'has-mentor' );

echo "\n=== The outcome is shown on the card it happened on ===\n";

// A fresh reader: `WPCPM_Flash::take()` memoizes per request, and the accounts above have
// read theirs already.
$GLOBALS['users'][8]       = new WP_User( 8, 'Dora Colleague', 'dora@example.test', array( 'subscriber' ) );
$GLOBALS['memberships'][8] = array( $A );

WPCPM_Flash::set( 'institution_request', array( 'status' => 'raised', 'student' => $S1 ), 8 );

$elsewhere = render_block( 8, $A, $S2 );
$here      = render_block( 8, $A, $S1 );

ck( 'another student\'s card says nothing about it', has( $elsewhere, 'wpcpm-request__message' ), false );
ck( 'the card it happened on says it', has( $here, 'A program manager has been asked for a mentor' ), true );
ck( 'once', substr_count( $here, 'wpcpm-request__message' ), 1 );
ck( 'and the card now shows the date it was asked for instead of the control', array(
	has( $here, 'A mentor was asked for on' ),
	has( $here, 'Ask for a mentor' ),
), array( true, false ) );

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
	'student', 'student_name', 'actor', 'actor_name', 'note', 'at', 'closed_at', 'overdue',
) );
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

$raise_body = method_body( $src, 'handle_request' );
ck( 'raising checks the nonce before it decides anything', before( $raise_body, 'check_admin_referer', 'WPCPM_Institution_Roster::owns' ), true );
ck( 'and reads no institution from the request', preg_match( '/posted_text\(\s*.(record|institution)/', $raise_body ), 0 );
ck( 'the only thing it reads from the request is the student', preg_match_all( '/WPCPM_Request::posted_/', $raise_body ), 1 );

// The one thing in this handler that no behaviour can pin, and it is the fence itself. A
// refused decision carries no institution at all, so with these two lines deleted the "there
// is nothing to file it under" check below refuses the same request in the same words, and
// every assertion in this file goes on saying ok. Two overlapping checks is the right shape -
// it is why nothing was ever filed against '' - but it means the fence has to be read out of
// the file, and read as the decision's own answer rather than as the shape of what a refusal
// happens to contain.
ck( 'raising refuses on the decision\'s own answer', (bool) preg_match( '/if \( empty\( \$decision\[\'allowed\'\] \) \) \{\s*wp_die\(/', (string) $raise_body ), true );
ck( 'and does that before it reads the institution out of it', before( $raise_body, "empty( \$decision['allowed'] )", "\$decision['institution']" ), true );

$resolve_body = method_body( $src, 'handle_resolve' );
ck( 'resolving checks the capability before the nonce', before( $resolve_body, 'current_user_can', 'check_admin_referer' ), true );
ck( 'and the nonce before the policy', before( $resolve_body, 'check_admin_referer', 'WPCPM_Institution_Policy::decide' ), true );
ck( 'with the subject read from the post and never from the form', has( $resolve_body, 'subject_post( $post, self::META_INSTITUTION )' ), true );
// The same, for the same reason: a manager passes `decide()` on every institution, so no
// account this suite can sign in as would behave differently with the refusal deleted.
ck( 'and refusing on the decision\'s own answer there too', (bool) preg_match( '/if \( empty\( \$decision\[\'allowed\'\] \) \) \{\s*wp_die\(/', (string) $resolve_body ), true );

// Section 13: this records that a mentor is wanted, it does not negotiate one. Nothing in the
// file may reach Airtable, and nothing may write a mentor anywhere.
ck( 'nothing here talks to Airtable', preg_match( '/WPCPM_Airtable|update_records|create_records/', $src ), 0 );
ck( 'and nothing here sends mail', preg_match( '/WPCPM_Mail::/', $src ), 0 );

ck( 'no institution ID is compared with === anywhere in the file', preg_match( '/\$institution\s*===|===\s*\$institution/', $src ), 0 );
ck( 'every state change goes through the two writers', preg_match_all( '/update_post_meta\(\s*\$post_id,\s*self::META_STATE/', $src ), 2 );

$GLOBALS['hooks'] = array();
WPCPM_Institution_Request::init();
ck( 'the post type and both handlers are registered', $GLOBALS['hooks'], array(
	'init',
	'admin_post_wpcpm_request_mentor',
	'admin_post_wpcpm_resolve_request',
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

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
