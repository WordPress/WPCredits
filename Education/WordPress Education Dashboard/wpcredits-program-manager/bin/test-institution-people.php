<?php
/**
 * The People card and the three membership handlers.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The card lists every live member with how they came by their access and since when,
 *   because "who can see our students" is the first question a school asks and a list
 *   without provenance cannot answer the second one.
 * - The member whose address is the one Airtable holds is marked, and the mark takes nothing
 *   away: it is information about who the program writes to, not a rank. It used to suppress
 *   that row's control on both surfaces, which left an institution whose contact had gone
 *   unrepairable by their colleagues, by a program manager and by themselves alike.
 * - The nonce on every Remove is keyed to the subject account, so a token for removing one
 *   person is not a token for removing another.
 * - Every manager-side handler runs the capability, then the nonce, then `decide()` (spec
 *   5.4), and the order assertions fail when one of the three is missing rather than passing
 *   on `false < 12`.
 * - One outcome message, on the card of the institution it happened to, and none at all for
 *   somebody the card will no longer draw.
 * - The card claims an invitation only when one was queued: `WPCPM_Mail::queue_invites()`
 *   drops an account that has been invited before, and `attach()` never clears that stamp.
 * - **The subject's institution is read from the subject's own stamp and never from the
 *   form.** A member of B posting a member of A's user ID is decided against A and gets the
 *   one refusal, byte for byte, whatever the form says the institution is.
 * - A member removing themselves as the last member is allowed and warned rather than
 *   refused, and `detach()` tells the program managers there is nobody left.
 * - The manager backstop refuses any existing account except a former member of this
 *   institution or a mentor, and a mentor keeps the Mentor role: `add_role()`, never
 *   `set_role()`, or the backstop would quietly demote somebody's mentor account.
 *
 * Run from the plugin root:  php bin/test-institution-people.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']       = array( 'date_format' => 'Y-m-d' );
$GLOBALS['umeta']      = array();
$GLOBALS['users']      = array();
$GLOBALS['posts']      = array();
$GLOBALS['pmeta']      = array();
$GLOBALS['uid']        = 0;
$GLOBALS['manage']     = array();
$GLOBALS['index']      = array();
$GLOBALS['index_read'] = 0;
$GLOBALS['settled']    = array();
$GLOBALS['notified']   = array();
$GLOBALS['queued']     = array();
$GLOBALS['inserted']   = array();
$GLOBALS['referer']    = array();
$GLOBALS['add_role']   = array();
$GLOBALS['set_role']   = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
/**
 * Enough of `WP_User` to tell `add_role()` from `set_role()`.
 *
 * `set_role()` replaces every role, which is what adopting a mentor's account must never
 * do. Both are recorded so the suite can assert which one ran.
 */
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array(), $login = '' ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = '' !== $login ? $login : strtolower( str_replace( ' ', '', $name ) );
		$this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
	public function add_role( $r ) {
		$GLOBALS['add_role'][] = array( $this->ID, $r );
		if ( ! in_array( $r, $this->roles, true ) ) { $this->roles[] = $r; }
	}
	public function remove_role( $r ) { $this->roles = array_values( array_diff( $this->roles, array( $r ) ) ); }
	public function set_role( $r ) { $GLOBALS['set_role'][] = array( $this->ID, $r ); $this->roles = '' === $r ? array() : array( $r ); }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_content = '', $post_type = '', $post_status = 'publish', $post_author = 0, $post_date_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $s, $p, $n, $d = null ) { return 1 === (int) $n ? $s : $p; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function sanitize_text_field( $s ) { return trim( str_replace( array( "\r", "\n" ), '', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function sanitize_user( $u, $strict = false ) { return preg_replace( '/[^a-zA-Z0-9 _.\-@]/', '', (string) $u ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action( $h, $c = null, $p = 10, $n = 1 ) { $GLOBALS['hooks'][] = $h; }
function add_filter() {}
function register_post_type() {}
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function human_time_diff( $a, $b = 0 ) { return '4 hours'; }
function wp_specialchars_decode( $s, $q = null ) { return html_entity_decode( (string) $s, ENT_QUOTES ); }
function get_bloginfo( $k = 'name' ) { return 'WordPress Education Dashboard'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function user_can( $u, $c ) { $id = is_object( $u ) ? $u->ID : (int) $u; return in_array( $id, $GLOBALS['manage'], true ); }
function current_user_can( $c ) { return user_can( $GLOBALS['uid'], $c ); }
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && 0 === strcasecmp( (string) $user->user_email, (string) $value ) ) { return $user; }
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
		if ( 'login' === $field && $user->user_login === (string) $value ) { return $user; }
	}
	return false;
}
function username_exists( $login ) { $u = get_user_by( 'login', $login ); return $u ? $u->ID : false; }
function wp_generate_password( $l = 12, $s = true, $e = false ) { return str_repeat( 'x', (int) $l ); }
/** `get_users()` by meta key and value, matching the way MySQL collates: case-insensitively. */
function get_users( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		$value = $GLOBALS['umeta'][ (int) $id ][ $a['meta_key'] ?? '' ] ?? null;
		if ( null !== $value && 0 === strcasecmp( (string) $value, (string) ( $a['meta_value'] ?? '' ) ) ) { $out[] = $user; }
	}
	return $out;
}
function wp_insert_user( $args ) {
	static $next = 100;
	$GLOBALS['inserted'][] = $args;
	if ( get_user_by( 'email', $args['user_email'] ?? '' ) ) {
		return new WP_Error( 'existing_user_email', 'That address already has an account.' );
	}
	$id   = ++$next;
	$user = new WP_User( $id, (string) ( $args['display_name'] ?? '' ), (string) ( $args['user_email'] ?? '' ), array(), (string) ( $args['user_login'] ?? '' ) );
	if ( ! empty( $args['role'] ) ) { $user->roles = array( $args['role'] ); }
	$GLOBALS['users'][ $id ] = $user;
	return $id;
}
function wp_insert_post( $a, $error = false ) {
	static $next = 500;
	$post                          = new WP_Post();
	$post->ID                      = ++$next;
	$post->post_title              = $a['post_title'] ?? '';
	$post->post_content            = $a['post_content'] ?? '';
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'publish';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	$post->post_date_gmt           = gmdate( 'Y-m-d H:i:s', 1700000000 + $post->ID );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post_time( $f, $gmt = false, $post = null ) { return strtotime( $post->post_date_gmt . ' UTC' ); }
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	return $single ? ( $rows ? $rows[0] : '' ) : $rows;
}
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
function get_posts( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ( $a['post_type'] ?? '' ) !== $post->post_type ) { continue; }
		if ( ! empty( $a['meta_query'] ) ) {
			$clause = $a['meta_query'][0];
			$value  = $GLOBALS['pmeta'][ $post->ID ][ $clause['key'] ][0] ?? null;
			if ( null === $value || 0 !== strcasecmp( (string) $value, (string) $clause['value'] ) ) { continue; }
		}
		$out[] = $post;
	}
	$out = array_reverse( $out );
	$n   = (int) ( $a['numberposts'] ?? -1 );
	if ( $n > 0 ) { $out = array_slice( $out, 0, $n ); }
	return $out;
}
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['referer'][] = $a; return true; }
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect:' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die:' . $m ); }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
		public static function is_record_id( $v ) { return (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $v ) ); }
	}
}
if ( ! class_exists( 'WPCPM_Students_Sync' ) ) {
	class WPCPM_Students_Sync {
		const META_RECORD_ID   = 'wpcpm_student_record_id';
		const META_INSTITUTION = 'wpcpm_student_institution';
	}
}
if ( ! class_exists( 'WPCPM_Institutions_Index' ) ) {
	class WPCPM_Institutions_Index {
		public static function read() { return array( 'v' => 1, 'read' => $GLOBALS['index_read'], 'rows' => $GLOBALS['index'] ); }
		public static function rows() { return $GLOBALS['index']; }
		public static function row( $r ) { return $GLOBALS['index'][ $r ] ?? null; }
		public static function has( $r ) { return isset( $GLOBALS['index'][ $r ] ); }
	}
}
if ( ! class_exists( 'WPCPM_Roster_Index' ) ) {
	/** Only what the policy's `subject_index_row()` would reach for; this card asks for none of it. */
	class WPCPM_Roster_Index {
		public static function rows( $id ) { return array(); }
	}
}
if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	/** Contract: the gate the member ground applies, and nothing else is read from here. */
	class WPCPM_Institution_Agreement {
		public static function is_settled( $id ) { return in_array( $id, $GLOBALS['settled'], true ); }
	}
}
if ( ! class_exists( 'WPCPM_Institutions' ) ) {
	/** Contract 12: records the context and runs the builder once, the way `send()` would. */
	class WPCPM_Institutions {
		public static function notify_managers( $context, $build ) {
			$GLOBALS['notified'][] = array( 'context' => $context, 'mail' => call_user_func( $build, new WP_User( 1, 'Manager', 'm@example.test' ) ) );
			return 1;
		}
	}
}
if ( ! class_exists( 'WPCPM_Mail' ) ) {
	/**
	 * The invitation queue: recorded, never sent, exactly as the real one is asked to behave.
	 *
	 * **Including the drop.** The real `queue_invites()` skips anybody already carrying any
	 * invited stamp, and `attach()` never clears one, so an adopted mentor and a re-added
	 * former member are queued nothing at all. A stub that queued everybody let the card
	 * promise an invitation that would never be sent, which is the whole of what these
	 * assertions are for. It returns how many were actually queued, as the real one does.
	 */
	class WPCPM_Mail {
		public static function queue_invites( array $user_ids ) {
			$fresh = 0;
			foreach ( $user_ids as $id ) {
				if (
					get_user_meta( $id, 'wpcpm_student_invited', true )
					|| get_user_meta( $id, 'wpcpm_mentor_invited', true )
					|| get_user_meta( $id, 'wpcpm_inst_invited', true )
				) {
					continue;
				}
				$GLOBALS['queued'][] = (int) $id;
				++$fresh;
			}
			return $fresh;
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-people.php';

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
function has( $haystack, $needle ) { return false !== strpos( $haystack, $needle ); }
function meta( $id, $k ) { return $GLOBALS['umeta'][ $id ][ $k ] ?? null; }
function flash() { return $GLOBALS['umeta'][ $GLOBALS['uid'] ]['wpcpm_flash']['institution_people'] ?? null; }
function flash_status() { $f = flash(); return is_array( $f ) ? $f['status'] : $f; }
function clear_flash() { unset( $GLOBALS['umeta'][ $GLOBALS['uid'] ]['wpcpm_flash'] ); }
/** Run a handler and report how it ended: a redirect, a wp_die, or nothing at all. */
function run( $method ) {
	try {
		call_user_func( array( 'WPCPM_Institution_People', $method ) );
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
	return '';
}
function render_card( $viewer, $record, $can_manage = false ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_People::render( $record, array( 'can_manage' => $can_manage, 'cohort' => '', 'filters' => array(), 'read' => 123 ) );
	return (string) ob_get_clean();
}
/** The manager backstop as one institution's block on the Institutions screen. */
function render_manager_block( $viewer, $record ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_People::render_manager( $record );
	return (string) ob_get_clean();
}
/**
 * Whether one call comes before another in a method body, with both of them present.
 *
 * `strpos()` answers a missing needle with false, and `false < 12` is true in PHP, so the
 * order assertions here used to pass for a method that had lost the very check they were
 * meant to pin: the capability check could be deleted and "checks the capability before the
 * nonce" went on saying ok. Absence is the answer no, whichever of the two is missing.
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

$A = 'recDdomg5W6h410JT'; // the TEST institution in the seed fixture.
$B = 'rec0IT9J93YkAYvSU';
$C = 'recZZZZZZZZZZZZZZ'; // well-formed, never indexed.
// A contact in the base and no account at all, which is what forty of the forty-two
// confirmed institutions looked like when the contact row was written.
$D = 'rec1D1D1D1D1D1D1D';

$GLOBALS['index'] = array(
	$A => array( 'record_id' => $A, 'name' => 'TEST - WordPress Education Dashboard (do not use) ', 'stage' => 'Confirmed', 'city' => 'Test', 'country_name' => 'Poland', 'website' => '', 'contact_person' => 'Bob Contact', 'contact_email' => 'Contact@example.test' ),
	$B => array( 'record_id' => $B, 'name' => 'Universidad Example', 'stage' => 'Confirmed', 'city' => 'Example', 'country_name' => 'Costa Rica', 'website' => '', 'contact_person' => '', 'contact_email' => 'rector@example.test' ),
	$D => array( 'record_id' => $D, 'name' => 'Politechnika Example', 'stage' => 'Confirmed', 'city' => 'Example', 'country_name' => 'Poland', 'website' => '', 'contact_person' => 'Dana Dean', 'contact_email' => 'dana@example.test' ),
);
$GLOBALS['index_read'] = 1756000000;
$GLOBALS['settled']    = array( $A, $B, $D );

$GLOBALS['users'] = array(
	1  => new WP_User( 1, 'Manager', 'manager@example.test', array( 'administrator' ) ),
	7  => new WP_User( 7, 'Anna Kowalska', 'anna@example.test', array( 'subscriber' ) ),
	8  => new WP_User( 8, 'Bob Contact', 'contact@example.test', array( 'subscriber' ) ),
	9  => new WP_User( 9, 'Cleo Beta', 'cleo@example.test', array( 'subscriber' ) ),
	10 => new WP_User( 10, 'Dan Mentor', 'dan@example.test', array( 'wpcpm_mentor' ) ),
	11 => new WP_User( 11, 'Eve Editor', 'eve@example.test', array( 'editor' ) ),
	12 => new WP_User( 12, 'Frank Former', 'frank@example.test', array( 'subscriber' ) ),
	13 => new WP_User( 13, 'Grace Third', 'grace@example.test', array( 'subscriber' ) ),
	// A program manager by capability and not by role, which is a person the site can have:
	// `attach()` refuses an administrator and refuses nobody else for holding CAP_MANAGE.
	14 => new WP_User( 14, 'Pia Program', 'pia@example.test', array( 'subscriber' ) ),
);
$GLOBALS['manage'] = array( 1, 14 );

// Three of these accounts have been invited before, which is what makes the adoption and
// re-add paths worth pinning: the mentor and two of the members carry a stamp, so the queue
// drops them and the card must not claim an invitation it is not going to send.
$GLOBALS['umeta'][10]['wpcpm_mentor_invited']      = 1756000001;
$GLOBALS['umeta'][12]['wpcpm_inst_invited'] = 1756000002;
$GLOBALS['umeta'][13]['wpcpm_inst_invited'] = 1756000003;

// Frank was a member of A and is not one now, which is what makes him re-addable.
WPCPM_Institution_Members::attach( 12, $A, 'manager', 1 );
WPCPM_Institution_Members::detach( 12, 'removed', 1 );

WPCPM_Institution_Members::attach( 7, $A, 'manager', 1 );
WPCPM_Institution_Members::attach( 8, $A, 'provisioned', 0 );
WPCPM_Institution_Members::attach( 13, $A, 'invited', 7 );
WPCPM_Institution_Members::attach( 9, $B, 'manager', 1 );

/* ---- the card ------------------------------------------------------------ */

echo "=== The card lists every live member, with how and since ===\n";

$card = render_card( 7, $A );

ck( 'it names the three live members', array( has( $card, 'Anna Kowalska' ), has( $card, 'Bob Contact' ), has( $card, 'Grace Third' ) ), array( true, true, true ) );
ck( 'and not the former member', has( $card, 'Frank Former' ), false );
ck( 'and not a member of another institution', has( $card, 'Cleo Beta' ), false );
ck( 'the count is the live members', has( $card, '<span class="wpcpm-people__count">3</span>' ), true );
ck( 'each address is printed', array( has( $card, 'anna@example.test' ), has( $card, 'contact@example.test' ) ), array( true, true ) );
ck( 'how: added by a program manager', has( $card, 'Added by a program manager' ), true );
ck( 'how: added by the institutions sync', has( $card, 'Added by the institutions sync' ), true );
ck( 'how: joined by invitation', has( $card, 'Joined by invitation' ), true );
ck( 'since: the date the stamp was written', has( $card, gmdate( 'Y-m-d' ) ), true );
ck( 'the institution name prints trimmed', has( $card, 'TEST - WordPress Education Dashboard (do not use)&#039;s students' ), true );
ck( 'the read time of the facts that are not live', has( $card, 'were read ' . gmdate( 'Y-m-d H:i', 1756000000 ) ), true );
ck( 'and it never renders accessibility', has( $card, 'accessibility' ), false );

echo "\n=== The contact Airtable names is a representative, account or no account ===\n";

// The card used to count accounts and list accounts, so an institution whose contact has
// never signed in read "Institution representatives 0" three lines under a header naming
// that very person. Two of the forty-two confirmed institutions have an account.
$named = render_card( 1, $D, true );

ck( 'the contact is listed', has( $named, 'Dana Dean' ), true );
ck( 'with the address the base holds', has( $named, 'dana@example.test' ), true );
ck( 'and the count is one, not zero', has( $named, '<span class="wpcpm-people__count">1</span>' ), true );
ck( 'the row says the account is what is missing', has( $named, 'no account on this site yet' ), true );
ck( 'and it is marked as the program\'s contact', has( $named, 'the program&#039;s contact' ), true );
// The row is a fact about the program's records; there is no membership under it to end.
ck( 'the row carries no Remove control', has( $named, 'wpcpm-people__remove' ), false );
// Two different questions: who the program writes to, and who can open this page.
ck( 'and who can act is still answered separately', has( $named, 'Nobody can act for this institution on this site right now.' ), true );

// The row goes away by itself once the account exists, because the address then matches a
// member and `holds_address()` suppresses it - no second place to keep in step.
ck( 'no contact row where a member holds the address', has( $card, 'no account on this site yet' ), false );
ck( 'and that card still counts three', has( $card, '<span class="wpcpm-people__count">3</span>' ), true );

// A base row with an address and no name still names somebody rather than printing a blank.
$unnamed = render_card( 9, $B );
ck( 'an address with no name is still listed', has( $unnamed, 'rector@example.test' ), true );
ck( 'under a stated absence rather than an empty line', has( $unnamed, 'Name not recorded' ), true );
ck( 'and it counts alongside the member', has( $unnamed, '<span class="wpcpm-people__count">2</span>' ), true );

// One heading, one meaning: the manager screen counts and lists the same way.
$block = render_manager_block( 1, $D );
ck( 'the manager screen lists the contact too', has( $block, 'Dana Dean' ), true );
ck( 'and counts it the same way', has( $block, '<span class="wpcpm-people__count">1</span>' ), true );
ck( 'and still names the gap it can close', has( $block, 'no member holds that address' ), true );

echo "\n=== The program's contact is marked, and the mark takes nothing away ===\n";

// The blocker this block exists for: the mark used to suppress the row's control on both
// surfaces, so a contact who left the school could not be removed by a colleague, by a
// program manager or by themselves, and the institution could not be repaired from anywhere.
// The mark is information about who the program writes to; the control is on every row.
ck( 'the contact is marked, once', substr_count( $card, 'the program&#039;s contact<' ), 1 );
ck( 'the mark is on the account holding the address Airtable names, whatever its case', has( $card, 'contact@example.test</span> <span class="wpcpm-people__mark"' ), true );
ck( 'the contact\'s row carries the same control as every other', has( $card, 'nonce-wpcpm_remove_member_8' ), true );
ck( 'so all three rows carry one', substr_count( $card, 'name="action" value="wpcpm_remove_member"' ), 3 );
ck( 'the other two among them', array( has( $card, 'nonce-wpcpm_remove_member_7' ), has( $card, 'nonce-wpcpm_remove_member_13' ) ), array( true, true ) );
ck( 'and no row says a manager is the only one who can', has( $card, 'is removed by a program manager' ), false );
ck( 'what the mark does instead is warn, in that row\'s confirm', has( $card, 'Airtable still names this address as the institution&#039;s contact' ), true );
ck( 'once, on that row and no other', substr_count( $card, 'Airtable still names this address' ), 1 );

$own = render_card( 8, $A );
ck( 'the contact may leave, like anybody else', has( $own, 'nonce-wpcpm_remove_member_8' ), true );
ck( 'their own row is Leave', has( $own, '>Leave</button>' ), true );
ck( 'their confirm warns them too', has( $own, 'Airtable still names this address as the institution&#039;s contact' ), true );
ck( 'and they see the controls on everybody else', substr_count( $own, 'name="action" value="wpcpm_remove_member"' ), 3 );

$backstop_card = render_manager_block( 1, $A );
ck( 'the manager backstop offers it too', has( $backstop_card, 'nonce-wpcpm_remove_member_8' ), true );
ck( 'on every live row it draws', substr_count( $backstop_card, 'name="action" value="wpcpm_remove_member"' ), 3 );

echo "\n=== The nonce is keyed to the subject, and the confirm names everybody ===\n";

ck( 'the viewer\'s own row is Leave', has( $card, '>Leave</button>' ), true );
ck( 'somebody else\'s is Remove', has( $card, '>Remove</button>' ), true );
ck( 'the confirm names the person and the institution', has( $card, "Remove Grace Third&#039;s access to TEST - WordPress Education Dashboard (do not use)&#039;s students" ), true );
ck( 'it says who else is emailed', has( $card, 'and so will the other 2 members' ), true );
ck( 'and that the account is kept', has( $card, 'The account is kept.' ), true );
ck( 'leaving says the same about your own account', has( $card, 'your account is kept' ), true );

echo "\n=== A member whose agreement is not settled sees no card at all ===\n";

$GLOBALS['settled'] = array( $B );
ck( 'nothing is drawn', render_card( 7, $A ), '' );
ck( 'and a manager still sees it', has( render_card( 1, $A, true ), 'Institution representatives' ), true );
$GLOBALS['settled'] = array( $A, $B );

/* ---- removal: the fence -------------------------------------------------- */

echo "\n=== A member of B cannot remove a member of A ===\n";

$GLOBALS['uid']     = 9;
$GLOBALS['referer'] = array();
// The form names B, because that is what a forged one would name. It is not consulted.
$_POST  = array( 'member' => 7, 'record' => $B, 'wpcpm_from' => 'admin' );
$ending = run( 'handle_remove_member' );

ck( 'it is refused with the one message', $ending, 'wp_die:That record is not on your roster.' );
ck( 'the nonce was keyed to the subject account, not to the form\'s institution', $GLOBALS['referer'], array( 'wpcpm_remove_member_7' ) );
ck( 'and the member of A is untouched', WPCPM_Institution_Members::institution_of( 7 ), $A );

$GLOBALS['uid'] = 9;
$_POST          = array( 'member' => 999 );
ck( 'an account that does not exist is the same refusal', run( 'handle_remove_member' ), 'wp_die:That record is not on your roster.' );

$GLOBALS['uid'] = 0;
$_POST          = array( 'member' => 7 );
ck( 'and so is a request from nobody', run( 'handle_remove_member' ), 'wp_die:That record is not on your roster.' );

echo "\n=== A member removes a colleague of their own institution ===\n";

$GLOBALS['uid']     = 7;
$GLOBALS['referer'] = array();
$_POST              = array( 'member' => 13 );
$ending             = run( 'handle_remove_member' );

ck( 'it ends on a redirect to the card', $ending, 'redirect:https://example.test/#wpcpm-people' );
ck( 'the colleague is no longer a member', WPCPM_Institution_Members::institution_of( 13 ), '' );
ck( 'their account is kept', $GLOBALS['users'][13] instanceof WP_User, true );
ck( 'the stamp moved to _was', meta( 13, 'wpcpm_institution_record_id_was' ), $A );
ck( 'the outcome says so once', flash_status(), 'removed' );
$rows = WPCPM_Institution_Audit::entries_for( $A, 1 );
ck( 'one audit row, on the member ground, naming the reason', array( $rows[0]['kind'], $rows[0]['subject'], $rows[0]['actor'], $rows[0]['ground'], $rows[0]['data']['reason'] ), array( 'member_removed', '13', 7, 'member', 'removed' ) );
clear_flash();

echo "\n=== Removing a membership that has already ended ===\n";

// Two people pressing Remove on the same row is ordinary. The second finds no live stamp, so
// the subject is the institution the person LEFT, from `_was`, and the fence is asked about
// that: a manager is answered where they pressed, and a stranger learns nothing.
$GLOBALS['uid'] = 1;
$_POST          = array( 'member' => 13, 'wpcpm_from' => 'admin' );
ck( 'a manager pressing Remove on it is answered, not silenced', run( 'handle_remove_member' ), 'redirect:https://example.test/wp-admin/admin.php?page=wpcpm-institutions#wpcpm-people' );
ck( 'with the outcome filed against the institution it left', array( flash_status(), flash()['record'] ), array( 'ended', $A ) );
ck( 'and nothing detached, because nothing was attached', WPCPM_Institution_Members::institution_of( 13 ), '' );
clear_flash();

$GLOBALS['uid'] = 9;
$_POST          = array( 'member' => 13, 'wpcpm_from' => 'admin' );
ck( 'a member of B asking the same is refused, so nobody learns whose access ended', run( 'handle_remove_member' ), 'wp_die:That record is not on your roster.' );
clear_flash();

echo "\n=== The last member may leave, and is warned ===\n";

// The contact goes first, by the manager, so the viewer is the only one left.
$GLOBALS['uid'] = 1;
$_POST          = array( 'member' => 8, 'wpcpm_from' => 'admin' );
ck( 'a manager removes the contact from the admin screen', run( 'handle_remove_member' ), 'redirect:https://example.test/wp-admin/admin.php?page=wpcpm-institutions#wpcpm-people' );
clear_flash();

$card = render_card( 7, $A );
ck( 'the confirm warns that nobody will be left', has( $card, 'You are the last member of' ), true );
ck( 'and that the program will be told', has( $card, 'the program managers will be told' ), true );
ck( 'and it is still a control, not a refusal', has( $card, '>Leave</button>' ), true );

$GLOBALS['notified'] = array();
$GLOBALS['uid']      = 7;
$_POST               = array( 'member' => 7 );
$ending              = run( 'handle_remove_member' );

ck( 'leaving is allowed', $ending, 'redirect:https://example.test/#wpcpm-people' );
ck( 'the reason recorded is that they left', WPCPM_Institution_Audit::entries_for( $A, 1 )[0]['data']['reason'], 'left' );
ck( 'nobody is left', WPCPM_Institution_Members::members_of( $A ), array() );
ck( 'and the program managers were told', $GLOBALS['notified'][0]['context'] ?? '', 'member-last' );
ck( 'in a message naming the institution', has( $GLOBALS['notified'][0]['mail']['subject'], 'TEST - WordPress Education Dashboard (do not use)' ), true );

echo "\n=== And is not left holding a message nobody can show them ===\n";

// Leaving ends the viewer's membership, so the card refuses to draw for them on the next
// request. An outcome queued anyway would sit in user meta until it surprised somebody.
ck( 'nothing is queued for a leaver who can no longer read the card', flash(), null );

WPCPM_Institution_Members::attach( 7, $A, 'manager', 1 );
$back = render_card( 7, $A );
// Not asserted here: whether the old message greets the new membership. `WPCPM_Flash::take()`
// memoises per user and channel for the whole process, so a second render for user 7 in this
// run can print nothing whatever is queued, and an assertion on it passes by construction. The
// guard is the one above: nothing was queued for the leaver in the first place.
ck( 'the card itself is drawn again', has( $back, 'Institution representatives' ), true );
// Back to nobody, which is the state the blocks below were written against.
WPCPM_Institution_Members::detach( 7, 'removed', 1 );

echo "\n=== A leaver who can still see the card is still told ===\n";

// A program manager by capability who is also a member: the one leaver the card still draws,
// on the manager ground, and the one who should still read the sentence.
WPCPM_Institution_Members::attach( 14, $A, 'manager', 1 );

$GLOBALS['uid'] = 14;
$_POST          = array( 'member' => 14 );

ck( 'they may leave', run( 'handle_remove_member' ), 'redirect:https://example.test/#wpcpm-people' );
ck( 'they stop being a member', WPCPM_Institution_Members::institution_of( 14 ), '' );
ck( 'and the outcome is queued for them', flash_status(), 'left' );
ck( 'on the card of the institution they left', has( render_card( 14, $A, true ), 'You have left this institution' ), true );
clear_flash();

/* ---- the manager backstop ------------------------------------------------ */

echo "\n=== Add account: an existing account is adopted only in two cases ===\n";

$GLOBALS['uid']      = 1;
$GLOBALS['inserted'] = array();
$GLOBALS['queued']   = array();
$_POST               = array( 'record' => $A, 'name' => 'Eve Editor', 'email' => 'eve@example.test', 'wpcpm_from' => 'admin' );
$ending              = run( 'handle_add_account' );

ck( 'an editor with an account is refused', flash_status(), 'conflict' );
ck( 'the refusal names the account, so a manager can go and look', flash()['detail'], 'eveeditor' );
ck( 'nothing was created', $GLOBALS['inserted'], array() );
ck( 'and nothing was attached', WPCPM_Institution_Members::institution_of( 11 ), '' );
ck( 'it returns to the screen it was pressed on', $ending, 'redirect:https://example.test/wp-admin/admin.php?page=wpcpm-institutions#wpcpm-people' );
clear_flash();

$_POST = array( 'record' => $A, 'name' => 'Frank Former', 'email' => 'frank@example.test', 'wpcpm_from' => 'admin' );
run( 'handle_add_account' );

// `queue_invites()` drops an account that already carries an invited stamp and `attach()`
// never clears one, so a re-adopted former member is sent nothing. The outcome says which of
// the two happened rather than promising mail that will not go out.
ck( 'a former member of this institution is adopted', flash_status(), 'added-known' );
ck( 'without creating a second account', $GLOBALS['inserted'], array() );
ck( 'they act for it again', WPCPM_Institution_Members::institution_of( 12 ), $A );
ck( 'the _was stamp is cleared, so they are no longer a former member', meta( 12, 'wpcpm_institution_record_id_was' ), null );
ck( 'and nothing is queued for an account that has been invited before', $GLOBALS['queued'], array() );
clear_flash();

$GLOBALS['add_role'] = array();
$GLOBALS['set_role'] = array();
$GLOBALS['queued']   = array();
$_POST               = array( 'record' => $A, 'name' => 'Dan Mentor', 'email' => 'dan@example.test', 'wpcpm_from' => 'admin' );
run( 'handle_add_account' );

ck( 'a mentor is adopted', flash_status(), 'added-known' );
ck( 'without creating a second account', $GLOBALS['inserted'], array() );
ck( 'they act for the institution', WPCPM_Institution_Members::institution_of( 10 ), $A );
ck( 'the role went on with add_role()', $GLOBALS['add_role'], array( array( 10, 'wpcpm_institution' ) ) );
ck( 'never with set_role()', $GLOBALS['set_role'], array() );
ck( 'and they are still a mentor', $GLOBALS['users'][10]->roles, array( 'wpcpm_mentor', 'wpcpm_institution' ) );
// A mentor mid-program holds a working password, and `wp_new_user_notification()` is a
// set-your-password message. Nothing is sent, and nothing claims it was.
ck( 'a mentor who has been invited as a mentor is not invited again', $GLOBALS['queued'], array() );
clear_flash();

echo "\n=== Add account: a fresh address gets a fresh account ===\n";

$GLOBALS['inserted'] = array();
$GLOBALS['queued']   = array();
$_POST               = array( 'record' => $B, 'name' => 'Hana Nowa', 'email' => 'hana@example.test', 'wpcpm_from' => 'admin' );
run( 'handle_add_account' );

ck( 'one account was created', count( $GLOBALS['inserted'] ), 1 );
ck( 'with the Institution role and the name that was typed', array( $GLOBALS['inserted'][0]['role'], $GLOBALS['inserted'][0]['display_name'] ), array( 'wpcpm_institution', 'Hana Nowa' ) );
ck( 'the username is the local part of the address', $GLOBALS['inserted'][0]['user_login'], 'hana' );
ck( 'the password is not one anybody chose', 24, strlen( $GLOBALS['inserted'][0]['user_pass'] ) );
$fresh = get_user_by( 'email', 'hana@example.test' );
ck( 'the new account acts for the institution', WPCPM_Institution_Members::institution_of( $fresh->ID ), $B );
ck( 'the membership says a manager did it', meta( $fresh->ID, 'wpcpm_institution_membership' )['how'], 'manager' );
ck( 'and one invitation is queued for it', $GLOBALS['queued'], array( $fresh->ID ) );
ck( 'which is the outcome that may claim one', flash_status(), 'added' );
clear_flash();

echo "\n=== Add account: what it refuses before it creates anything ===\n";

$GLOBALS['inserted'] = array();
$_POST               = array( 'record' => $C, 'name' => 'Nobody', 'email' => 'nobody@example.test', 'wpcpm_from' => 'admin' );
run( 'handle_add_account' );
ck( 'a record the index does not hold', flash_status(), 'unknown-record' );
clear_flash();

// Shape first: the hidden field is written by the block the button sits in, so a value that
// is not a record ID at all is a forged or a broken form, and gets the one refusal rather
// than a message about the pipeline index.
$_POST = array( 'record' => 'not-a-record', 'name' => 'Nobody', 'email' => 'nobody@example.test' );
ck( 'a record ID that is not one is refused, not answered', run( 'handle_add_account' ), 'wp_die:That record is not on your roster.' );
ck( 'and says nothing to anybody', flash(), null );

$_POST = array( 'record' => $B, 'name' => 'Nobody', 'email' => 'not an address' );
run( 'handle_add_account' );
ck( 'an address WordPress cannot use', flash_status(), 'bad-email' );
clear_flash();

$_POST = array( 'record' => $B, 'name' => 'Cleo Beta', 'email' => 'cleo@example.test' );
run( 'handle_add_account' );
ck( 'an account that is already on the list is told so, not called a conflict', flash_status(), 'already' );
clear_flash();

$_POST = array( 'record' => $A, 'name' => 'Cleo Beta', 'email' => 'cleo@example.test' );
run( 'handle_add_account' );
ck( 'a live member of another institution is the conflict', flash_status(), 'conflict' );
ck( 'and is not moved', WPCPM_Institution_Members::institution_of( 9 ), $B );
ck( 'nothing was created by any of them', $GLOBALS['inserted'], array() );
clear_flash();

$GLOBALS['uid'] = 7;
$_POST          = array( 'record' => $A, 'name' => 'Nobody', 'email' => 'nobody@example.test' );
ck( 'and a member cannot use the backstop at all', run( 'handle_add_account' ), 'wp_die:You do not have permission to manage the program.' );

echo "\n=== Re-add puts a former member back, and only a former member ===\n";

$GLOBALS['uid']     = 1;
$GLOBALS['queued']  = array();
$GLOBALS['referer'] = array();
$_POST              = array( 'member' => 8, 'record' => $A, 'wpcpm_from' => 'admin' );
run( 'handle_readd' );

ck( 'the contact who was removed is back', WPCPM_Institution_Members::institution_of( 8 ), $A );
ck( 'the nonce was keyed to the subject account', $GLOBALS['referer'], array( 'wpcpm_readd_institution_member_8' ) );
ck( 'the outcome says so', flash_status(), 'readded' );
ck( 'and an invitation is queued for an account that has never had one', $GLOBALS['queued'], array( 8 ) );
clear_flash();

// The commoner case: a former member has been invited before, so `queue_invites()` sends
// nothing and the outcome does not say it did.
$GLOBALS['queued'] = array();
$_POST             = array( 'member' => 13, 'record' => $A, 'wpcpm_from' => 'admin' );
run( 'handle_readd' );

ck( 'a former member who has been invited before is put back', WPCPM_Institution_Members::institution_of( 13 ), $A );
ck( 'with no second invitation, and no claim of one', array( flash_status(), $GLOBALS['queued'] ), array( 'readded-known', array() ) );
clear_flash();
// Back to a former member, which is the state the manager block at the foot is written for.
WPCPM_Institution_Members::detach( 13, 'removed', 1 );

$_POST = array( 'member' => 11, 'record' => $A, 'wpcpm_from' => 'admin' );
run( 'handle_readd' );
ck( 'an account that was never a member of it is refused', flash_status(), 'not-former' );
ck( 'and is not attached', WPCPM_Institution_Members::institution_of( 11 ), '' );
clear_flash();

// Frank's `_was` names A and he is a live member of A: a re-add naming B must not move him.
$_POST = array( 'member' => 12, 'record' => $B, 'wpcpm_from' => 'admin' );
run( 'handle_readd' );
ck( 'a re-add naming another institution is refused', flash_status(), 'not-former' );
ck( 'and the member stays where they are', WPCPM_Institution_Members::institution_of( 12 ), $A );
clear_flash();

$GLOBALS['uid'] = 7;
$_POST          = array( 'member' => 12, 'record' => $A );
ck( 'a member cannot re-add either', run( 'handle_readd' ), 'wp_die:You do not have permission to manage the program.' );

echo "\n=== One outcome, on the card of the institution it happened to ===\n";

// `WPCPM_Flash::take()` memoizes per request, so a screen drawing the backstop under every
// pipeline row printed one manager's outcome under all 106 of them, each reading as a fact
// about that school. The outcome names its institution and only that block prints it. A
// fresh manager account, because a flash is read once per reader and the managers above have
// already read theirs.
$GLOBALS['users'][15]                         = new WP_User( 15, 'Quinn Manager', 'quinn@example.test', array( 'subscriber' ) );
$GLOBALS['users'][16]                         = new WP_User( 16, 'Rita Mentor', 'rita@example.test', array( 'wpcpm_mentor' ) );
$GLOBALS['umeta'][16]['wpcpm_mentor_invited'] = 1756000004;
$GLOBALS['manage'][]                          = 15;

$GLOBALS['uid']    = 15;
$GLOBALS['queued'] = array();
$_POST             = array( 'record' => $A, 'name' => 'Rita Mentor', 'email' => 'rita@example.test', 'wpcpm_from' => 'admin' );
run( 'handle_add_account' );

ck( 'the outcome is recorded against the institution it happened to', flash()['record'], $A );

$elsewhere = render_manager_block( 15, $B );
$here      = render_manager_block( 15, $A );

ck( 'another institution\'s block says nothing about it', has( $elsewhere, 'wpcpm-people__message' ), false );
ck( 'the institution it happened to says it', has( $here, 'The account was added.' ), true );
ck( 'once', substr_count( $here, 'wpcpm-people__message' ), 1 );
ck( 'and says no invitation was queued, because none was', array( has( $here, 'No new invitation was queued' ), $GLOBALS['queued'] ), array( true, array() ) );
clear_flash();

/* ---- the manager block --------------------------------------------------- */

echo "\n=== The manager backstop draws what a manager needs ===\n";

$GLOBALS['uid'] = 1;
ob_start();
WPCPM_Institution_People::render_manager( $B );
$block = (string) ob_get_clean();

ck( 'it lists the live members', has( $block, 'Hana Nowa' ), true );
ck( 'it says when Airtable\'s contact is nobody here', has( $block, 'and no member holds that address' ), true );
ck( 'naming the address', has( $block, 'rector@example.test' ), true );
ck( 'it offers the Add account form', has( $block, 'name="action" value="wpcpm_add_institution_account"' ), true );
ck( 'and says what that refuses', has( $block, 'only adopted when that account was a member of this institution or is a mentor' ), true );

ob_start();
WPCPM_Institution_People::render_manager( $A );
$block = (string) ob_get_clean();

ck( 'former members are listed with a Re-add', array( has( $block, 'Grace Third' ), has( $block, 'name="action" value="wpcpm_readd_institution_member"' ) ), array( true, true ) );
ck( 'and the read time is printed here too', has( $block, 'were read ' . gmdate( 'Y-m-d H:i', 1756000000 ) ), true );

$GLOBALS['uid'] = 7;
ob_start();
WPCPM_Institution_People::render_manager( $A );
ck( 'a member is drawn nothing by it', (string) ob_get_clean(), '' );

/* ---- structure ----------------------------------------------------------- */

echo "\n=== The rules that are invisible at runtime ===\n";

$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-people.php' );

$remove = method_body( $src, 'handle_remove_member' );
ck( 'the removal reads the institution from the subject\'s own stamp', has( $remove, 'WPCPM_Institution_Members::institution_of( $member_id )' ), true );
ck( 'and never from the form', has( $remove, 'posted_text( \'record\' )' ), false );
ck( 'its nonce is keyed to the subject account', has( $remove, "self::ACTION_REMOVE_MEMBER . '_' . \$member_id" ), true );
ck( 'it asks the policy for the removal itself, once', substr_count( $remove, 'ACT_MANAGE_MEMBERS' ), 1 );
ck( 'and asks again only whether the leaver will be shown the outcome', substr_count( $remove, 'ACT_VIEW_ROSTER' ), 1 );
ck( 'never carrying a copy of the fence', substr_count( $remove, 'current_user_can' ), 0 );

// Spec 5.4: a manager-side handler runs the capability, then the nonce, then decide(), so the
// row the log writes says `manager` rather than nothing. All three, in that order, in both.
foreach ( array( 'handle_add_account', 'handle_readd' ) as $name ) {
	$body = method_body( $src, $name );

	ck(
		$name . '() carries all three checks',
		array(
			has( $body, 'current_user_can' ),
			has( $body, 'check_admin_referer' ),
			has( $body, 'WPCPM_Institution_Policy::decide(' ),
		),
		array( true, true, true )
	);
	ck(
		$name . '() runs them capability, nonce, policy',
		array(
			before( $body, 'current_user_can', 'check_admin_referer' ),
			before( $body, 'check_admin_referer', 'WPCPM_Institution_Policy::decide(' ),
		),
		array( true, true )
	);
	ck( $name . '() asks about the action the card asks about', has( $body, 'ACT_MANAGE_MEMBERS' ), true );
	ck( $name . '() refuses with the one refusal', has( $body, 'WPCPM_Institution_Policy::refusal()' ), true );
}

// The order assertion above used to be `strpos( $body, 'a' ) < strpos( $body, 'b' )`, which
// passes with the first check deleted, because a missing needle is false and `false < 12` is
// true. Both directions are pinned here so nobody writes the passing-when-broken form again.
$only_nonce = 'nonce only: check_admin_referer';
$only_cap   = 'capability only: current_user_can';
ck(
	'an order assertion says no when either check is missing, where strpos() said yes',
	array(
		before( $only_nonce, 'current_user_can', 'check_admin_referer' ),
		before( $only_cap, 'current_user_can', 'check_admin_referer' ),
		strpos( $only_nonce, 'current_user_can' ) < strpos( $only_nonce, 'check_admin_referer' ),
	),
	array( false, false, true )
);

// The backstop is drawn on a manager's screen, so the capability says which screen this is;
// the policy then says whether the record may be acted on, and on what ground. Asking the
// capability alone was how the three manager paths came to be the only ones in the module
// that never reached decide().
$backstop = method_body( $src, 'render_manager' );
ck(
	'the backstop asks the policy as well as the capability',
	array( has( $backstop, 'current_user_can' ), has( $backstop, 'WPCPM_Institution_Policy::decide(' ) ),
	array( true, true )
);
ck( 'about the action its own controls are served by', has( $backstop, 'ACT_MANAGE_MEMBERS' ), true );

// An outcome queued without an institution is drawn by no card at all, so it would be a
// message written and never read. Every call names one; the third argument is that name.
$arities = array();

foreach ( explode( "\n", $src ) as $line ) {
	$open = strpos( $line, 'self::finish(' );

	if ( false === $open ) {
		continue;
	}

	$arities[] = substr_count( substr( $line, $open ), ',' ) + 1;
}

ck( 'every outcome is queued with the institution it is about', array_values( array_unique( $arities ) ), array( 3 ) );
ck( 'and there were outcomes to count', count( $arities ) > 8, true );

ck( 'no institution ID is compared with === anywhere in the file', preg_match( '/wpcpm_(student_)?institution(_record_id)?[^;]*===/', $src ), 0 );
ck( 'and the stamp keys are named through the members module', has( $src, 'WPCPM_Institution_Members::META_MEMBERSHIP' ), true );

$GLOBALS['hooks'] = array();
WPCPM_Institution_People::init();
ck( 'the three handlers are registered', $GLOBALS['hooks'], array(
	'admin_post_wpcpm_remove_member',
	'admin_post_wpcpm_add_institution_account',
	'admin_post_wpcpm_readd_institution_member',
) );

$dashes = array();

foreach ( array(
	'includes/modules/class-wpcpm-institution-people.php',
	'bin/test-institution-people.php',
) as $rel ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $rel ) ) ) {
		$dashes[] = $rel;
	}
}

ck( 'no dash but the plain hyphen in either file', $dashes, array() );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
