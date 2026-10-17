<?php
/**
 * A student's welcome invitation, sent again from their card on the Institution Dashboard.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The press sends the site's own invitation, once.** `WPCPM_Students_Sync::send_invite()`
 *   runs for real here: one email handed to WordPress, the account's invitation stamped, one
 *   history row naming the institution, the student, who pressed and on which ground, and one
 *   place of the institution's day taken. The notice names the address masked and says the new
 *   link replaces the old one, or, for a first invitation, only that it is on its way.
 * - **Every refusal, in order, sends nothing, stamps nothing, records nothing and spends
 *   nothing.** The nonce (keyed to the account the form names), the policy (a member of another
 *   institution, a student, a stranger, nobody signed in, a locked agreement, an account with no
 *   institution stamp even for an Administrator), an account that is not a student, a student
 *   who has signed in, an account with no address, the fifteen minutes, and the day's twenty.
 *   The day's place is claimed only after all of them, so none of them costs the school one.
 * - **A send that answers an error gives the place back.** The two ways it can, another route
 *   inviting the student in the same instant and the account losing the Student role, are
 *   played between the claim and the send, on a day with places already taken.
 * - **An email the host refuses is no send.** The stand-in for WordPress's notification fires
 *   `wp_mail_failed`: the notice is the shared error sentence, no history row is written, the
 *   day's place is given back, and no stamp is written, so the card's "Invitation last sent"
 *   stays the last one that went and a press straight after sends. Only a refusal addressed to
 *   the student counts, however the address is written: another plugin's email failing on the way
 *   leaves the invitation sent, and a failure naming no recipient counts as the invitation's.
 * - **Two presses for one student at the same moment send one email.** The second, made while the
 *   first is sending, is refused at the send by the student's lock as the fifteen minutes refuse
 *   it, and gives its place back; a lock a dead request left holds the student until it ages out.
 * - **The day's twenty are counted per institution**, under a key that keeps the record ID's
 *   case, and an Administrator pressing through the switcher counts against the institution.
 * - **Every outcome's sentence**, one for each outcome and none for any other, printed on the
 *   card it is about and on no other, in its tone, escaped.
 * - **The card's line**: nothing for a student with a sign-in on record, for an account that is
 *   not a student, for an account with no address the press could send to, for a reader the
 *   policy refuses, or while the site keeps no sign-in record;
 *   the two sentences, naming the record's first day and the last invitation in the site's date
 *   format; the two button labels; a one-press form whose nonce is keyed to the student on
 *   screen; and the stylesheet's rules for all of it.
 *
 * Run from the plugin root:  php bin/test-institution-resend.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']        = array( 'date_format' => 'F j, Y' );
$GLOBALS['umeta']       = array();
$GLOBALS['users']       = array();
$GLOBALS['posts']       = array();
$GLOBALS['pmeta']       = array();
$GLOBALS['uid']         = 0;
$GLOBALS['manage']      = array();
$GLOBALS['members']     = array();
$GLOBALS['settled']     = array();
$GLOBALS['hooks']       = array();
$GLOBALS['invited']     = array();
$GLOBALS['referer']     = array();
$GLOBALS['bad_nonce']   = false;
$GLOBALS['no_insert']   = false;
$GLOBALS['after_claim'] = null;
// Whether the host refuses the invitation, which WordPress reports on `wp_mail_failed`: true for a
// refusal as core reports it, naming the account's address as `to`; a `to` of the check's own; or
// 'unnamed' for a failure that names no recipient.
$GLOBALS['mail_fails'] = false;
// The recipients of other emails, sent by another plugin's filter while the invitation is on its
// way, that the host refuses.
$GLOBALS['other_fails'] = array();
// Another request a check plays once while an email is on its way.
$GLOBALS['during_send'] = null;
// The page the browser says the form was posted from, and the Institution Dashboard's own page.
$GLOBALS['http_referer']   = 'https://example.test/institution-dashboard/?wpcpm_institution_student=10';
$GLOBALS['dashboard_page'] = 'https://example.test/institution-dashboard/';
// Every write to a ceiling row, so a refusal can be shown to have claimed nothing at all rather
// than to have claimed a place and given it back.
$GLOBALS['ceiling_writes'] = 0;

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email; $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_content = '', $post_type = '', $post_status = 'private', $post_author = 0, $post_date_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( str_replace( array( "\r", "\n" ), '', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function is_email( $e ) { return filter_var( (string) $e, FILTER_VALIDATE_EMAIL ) ? (string) $e : false; }
function wp_unslash( $v ) { return $v; }
function wp_slash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action( $h, $c = null, $p = 10, $n = 1 ) { $GLOBALS['hooks'][] = array( $h, $c ); }
function remove_action( $h, $c = null, $p = 10 ) {
	foreach ( $GLOBALS['hooks'] as $i => $hook ) {
		if ( $h === $hook[0] && $c === $hook[1] ) {
			unset( $GLOBALS['hooks'][ $i ] );
			$GLOBALS['hooks'] = array_values( $GLOBALS['hooks'] );
			return true;
		}
	}
	return false;
}
function do_action( $h, ...$args ) {
	foreach ( $GLOBALS['hooks'] as $hook ) {
		if ( $h === $hook[0] && is_callable( $hook[1] ) ) { call_user_func_array( $hook[1], $args ); }
	}
}
function register_post_type() {}
function wp_date( $f, $t = null ) { return gmdate( (string) $f, null === $t ? time() : (int) $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }

/**
 * Something another request does between this press's claim and its send, played once, when the
 * ceiling's row is written. It is how the suite reaches the two errors a send can still answer
 * after every check before it has passed.
 */
function after_claim( $k ) {
	if ( 0 === strpos( (string) $k, 'wpcpm_ceiling_' ) ) {
		++$GLOBALS['ceiling_writes'];
	}
	if ( 0 === strpos( (string) $k, 'wpcpm_ceiling_' ) && is_callable( $GLOBALS['after_claim'] ) ) {
		$run                    = $GLOBALS['after_claim'];
		$GLOBALS['after_claim'] = null;
		$run();
	}
}
function add_option( $k, $v, $dep = '', $a = 'yes' ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v;
	after_claim( $k );
	return true;
}
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; after_claim( $k ); return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_cache_delete( $key, $group = '' ) { return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_user_by( $f, $v ) { return 'id' === $f ? ( $GLOBALS['users'][ (int) $v ] ?? false ) : false; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
require_once __DIR__ . '/stubs/caps.php';
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
// The page the form was posted from, as core's wp_get_referer() reads it, or false for a browser that
// sent none.
function wp_get_referer() { return $GLOBALS['http_referer']; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect:' . $to ); }
/** Records the action the token was checked against, and dies as core does on a bad token. */
function check_admin_referer( $a = -1, $q = '_wpnonce' ) {
	$GLOBALS['referer'][] = $a;
	if ( $GLOBALS['bad_nonce'] ) { throw new Exception( 'wp_die:nonce' ); }
	return 1;
}
/**
 * The fields core prints, as it prints them: the token, its id and name the field's name, and the
 * page the form is drawn on, the request's address without a referer of its own, which
 * `wp_get_referer()` reads when the press comes back. The action is left readable in the token so
 * the keying can be asserted.
 */
function wp_referer_field( $display = true ) {
	$field = '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( remove_query_arg( '_wp_http_referer', $_SERVER['REQUEST_URI'] ) ) . '" />';
	if ( $display ) { echo $field; }
	return $field;
}
function wp_nonce_field( $a = -1, $n = '_wpnonce', $r = true, $e = true ) {
	$field = '<input type="hidden" id="' . esc_attr( $n ) . '" name="' . esc_attr( $n ) . '" value="nonce-' . esc_attr( $a ) . '" />' . ( $r ? wp_referer_field( false ) : '' );
	if ( $e ) { echo $field; }
	return $field;
}
/** Core's, for the one argument the referer field takes off its address. */
function remove_query_arg( $key, $url ) {
	$parts = explode( '?', (string) $url, 2 );
	if ( ! isset( $parts[1] ) ) { return $parts[0]; }
	parse_str( $parts[1], $query );
	unset( $query[ $key ] );
	return $parts[0] . ( $query ? '?' . http_build_query( $query ) : '' );
}
// The card's page, the address the line is drawn on.
$_SERVER['REQUEST_URI'] = '/institution-dashboard/?wpcpm_institution_student=10';
/**
 * The email WordPress would send: recorded by account, as core's own function is called. An email
 * another plugin's filter sends on the way, which the host refuses, is reported on `wp_mail_failed`
 * first, naming its own recipients. When the suite says the host refuses the invitation,
 * `wp_mail()` reports so on `wp_mail_failed` as core's does, with the recipients it was given as
 * `to`, and the function answers nothing either way, as core's does.
 */
function wp_new_user_notification( $id, $dep = null, $notify = '' ) {
	$GLOBALS['invited'][] = array( (int) $id, (string) $notify );
	if ( is_callable( $GLOBALS['during_send'] ) ) {
		$run                    = $GLOBALS['during_send'];
		$GLOBALS['during_send'] = null;
		$run();
	}
	foreach ( $GLOBALS['other_fails'] as $to ) {
		do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'Could not instantiate mail function.', array( 'to' => $to, 'subject' => 'Another plugin\'s email' ) ) );
	}
	if ( 'unnamed' === $GLOBALS['mail_fails'] ) {
		do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'Could not instantiate mail function.' ) );
	} elseif ( $GLOBALS['mail_fails'] ) {
		$to = true === $GLOBALS['mail_fails'] ? array( $GLOBALS['users'][ (int) $id ]->user_email ) : $GLOBALS['mail_fails'];
		do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'Could not instantiate mail function.', array( 'to' => $to, 'subject' => '[Example] Login Details', 'phpmailer_exception_code' => 2 ) ) );
	}
}
function wp_insert_post( $a, $error = false ) {
	static $next = 500;
	if ( $GLOBALS['no_insert'] ) { return new WP_Error( 'db_insert_error', 'Could not insert post into the database.' ); }
	$post                          = new WP_Post();
	$post->ID                      = ++$next;
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'private';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	$post->post_content            = $a['post_content'] ?? '';
	$post->post_date_gmt           = gmdate( 'Y-m-d H:i:s', 1700000000 + $post->ID );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = array( $v ); return true; }
function get_post_meta( $id, $k = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? array();
	return $single ? ( $rows ? $rows[0] : '' ) : $rows;
}
function get_post_time( $f, $gmt = false, $post = null ) { return strtotime( $post->post_date_gmt . ' UTC' ); }
function get_posts( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ( $a['post_type'] ?? '' ) !== $post->post_type ) { continue; }
		$clause = $a['meta_query'][0] ?? null;
		if ( $clause ) {
			$value = $GLOBALS['pmeta'][ $post->ID ][ $clause['key'] ][0] ?? null;
			if ( null === $value || 0 !== strcasecmp( (string) $value, (string) $clause['value'] ) ) { continue; }
		}
		$out[] = $post;
	}
	return array_reverse( $out );
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once __DIR__ . '/stubs/options-lock-wpdb.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-sign-ins.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';

/* ---- the pieces around the press, each to its contract -------------------- */

/** The one record-ID shape check the plugin has. */
class WPCPM_Mentors_Sync {
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
	public static function is_record_id( $v ) { return is_scalar( $v ) && (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $v ) ); }
}
/** The institutions an account acts for right now. */
class WPCPM_Institution_Members {
	public static function memberships_of( $user ) {
		$id = is_object( $user ) ? (int) $user->ID : (int) $user;
		return $GLOBALS['members'][ $id ] ?? array();
	}
}
/** Whether an institution's agreement gate is open. */
class WPCPM_Institution_Agreement {
	public static function is_settled( $id ) { return in_array( $id, $GLOBALS['settled'], true ); }
}
/** Where a press goes back to when the browser sent no referer: '' while no page holds the dashboard. */
class WPCPM_Institutions_Dashboard {
	public static function page_url() { return $GLOBALS['dashboard_page']; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-resend.php';

$fail  = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
function ck( $label, $got, $want ) {
	global $fail, $total;
	++$total;
	if ( $got === $want ) {
		echo 'ok   ' . $label . "\n";
		return;
	}
	++$fail;
	echo 'FAIL ' . $label . "\n";
	echo '       expected: ' . var_export( $want, true ) . "\n";
	echo '       actual:   ' . var_export( $got, true ) . "\n";
}
function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, (string) $needle ); }
/** A well-formed record ID of uppercase letters and digits. */
function rid( $name ) { return 'rec' . str_pad( $name, 14, '0' ); }

$R     = 'WPCPM_Institution_Resend';
$A     = rid( 'INSTA' );
$B     = rid( 'INSTB' );
$NW    = time();
$SINCE = gmmktime( 8, 0, 0, 10, 15, 2026 );

/**
 * One world: institutions A and B, both settled; a member of each, an Administrator; and the
 * students of A and B, each in the state one check below needs.
 */
function reset_world() {
	global $A, $B, $NW, $SINCE;

	// The day the site began recording sign-ins, which the card's line names.
	$GLOBALS['opts']        = array(
		'date_format'             => 'F j, Y',
		WPCPM_Sign_Ins::OPT_SINCE => $SINCE,
	);
	$GLOBALS['umeta']       = array();
	$GLOBALS['posts']       = array();
	$GLOBALS['pmeta']       = array();
	$GLOBALS['invited']     = array();
	$GLOBALS['referer']     = array();
	$GLOBALS['bad_nonce']   = false;
	$GLOBALS['no_insert']   = false;
	$GLOBALS['after_claim'] = null;
	$GLOBALS['mail_fails']  = false;
	$GLOBALS['other_fails'] = array();
	$GLOBALS['during_send'] = null;

	$GLOBALS['http_referer']   = 'https://example.test/institution-dashboard/?wpcpm_institution_student=10';
	$GLOBALS['dashboard_page'] = 'https://example.test/institution-dashboard/';
	$GLOBALS['manage']      = array( 30 );
	$GLOBALS['settled']     = array( $A, $B );

	$student = array( WPCPM_Roles::ROLE_STUDENT );

	$GLOBALS['users'] = array(
		10 => new WP_User( 10, 'Ana Lopez', 'ana@example.test', $student ),
		11 => new WP_User( 11, 'Ben Okafor', 'ben@example.test', $student ),
		12 => new WP_User( 12, 'Cleo Silva', 'cleo@example.test', $student ),
		13 => new WP_User( 13, 'Dara Novak', 'dara@example.test', $student ),
		14 => new WP_User( 14, 'Eli Moreau', 'eli@example.test', array( WPCPM_Roles::ROLE_MENTOR ) ),
		15 => new WP_User( 15, 'Fay Brandt', '', $student ),
		16 => new WP_User( 16, 'Gus Ferraro', 'gus@example.test', $student ),
		17 => new WP_User( 17, 'Hal Weiss', 'hal.example.test', $student ),
		20 => new WP_User( 20, 'Ines Ruiz', 'ines@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) ),
		21 => new WP_User( 21, 'Bruno Bianchi', 'bruno@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) ),
		30 => new WP_User( 30, 'An Administrator', 'admin@example.test', array( 'administrator' ) ),
		40 => new WP_User( 40, 'Nobody Here', 'nobody@example.test', array( 'subscriber' ) ),
	);

	$GLOBALS['members'] = array(
		20 => array( $A ),
		21 => array( $B ),
	);

	$stamp = WPCPM_Students_Sync::META_INSTITUTION;
	$sign  = WPCPM_Sign_Ins::META_SIGN_IN;
	$sent  = WPCPM_Mail::STAMPS[ WPCPM_Roles::ROLE_STUDENT ];

	// 10 invited nine days ago, no sign-in on record; 11 signed in; 12 never invited; 13 at B; 14
	// at A but a mentor now; 15 with no address; 16 a student whose account carries no stamp; 17
	// with an address that is no address.
	$GLOBALS['umeta'] = array(
		10 => array( $stamp => $A, $sent => $NW - 9 * DAY_IN_SECONDS ),
		11 => array( $stamp => $A, $sent => $NW - 9 * DAY_IN_SECONDS, $sign => $NW - DAY_IN_SECONDS ),
		12 => array( $stamp => $A ),
		13 => array( $stamp => $B, $sent => $NW - 9 * DAY_IN_SECONDS ),
		14 => array( $stamp => $A ),
		15 => array( $stamp => $A ),
		16 => array( $sent => $NW - 9 * DAY_IN_SECONDS ),
		17 => array( $stamp => $A ),
	);
}

/**
 * Press the button as one user, for one student's account, and say how the request ended.
 *
 * @param int $viewer  Who presses.
 * @param int $student The account the form names.
 * @return string The redirect or the death, as the stand-ins report them.
 */
function press( $viewer, $student ) {
	$GLOBALS['uid']     = (int) $viewer;
	$GLOBALS['invited'] = array();
	$GLOBALS['referer'] = array();
	unset( $GLOBALS['umeta'][ (int) $viewer ]['wpcpm_flash'] );
	$_POST = array(
		'action' => WPCPM_Institution_Resend::ACTION,
		'user'   => (string) $student,
	);

	try {
		WPCPM_Institution_Resend::handle_resend();
	} catch ( Exception $e ) {
		return $e->getMessage();
	}

	return '';
}

/** The flash the last press left for this user, or null. */
function flash_of( $viewer ) { return $GLOBALS['umeta'][ (int) $viewer ]['wpcpm_flash'][ WPCPM_Institution_Resend::FLASH ] ?? null; }
function outcome_of( $viewer ) { $f = flash_of( $viewer ); return is_array( $f ) ? $f['outcome'] : ''; }
/** How many of the institution's places today are taken. */
function spent( $institution ) { return WPCPM_Ceiling::count( WPCPM_Institution_Resend::ceiling_key( $institution ), DAY_IN_SECONDS ); }
function rows( $institution ) { return WPCPM_Institution_Audit::entries_for( $institution, 0 ); }

/**
 * A reader no card has been drawn for yet.
 *
 * `WPCPM_Flash::take()` keeps its first answer for the rest of a request, so a reader who has
 * drawn a card once would read their empty channel again; every render that should print a
 * notice is made by a new account of the same standing.
 *
 * @param string $institution The institution they are a member of, or '' for an Administrator.
 * @return int
 */
function fresh_reader( $institution = '' ) {
	static $next = 100;
	$id                      = ++$next;
	$GLOBALS['users'][ $id ] = new WP_User( $id, 'Reader ' . $id, 'reader' . $id . '@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
	if ( '' === $institution ) {
		$GLOBALS['manage'][] = $id;
	} else {
		$GLOBALS['members'][ $id ] = array( $institution );
	}
	return $id;
}

/* ---- 1. the hook ---------------------------------------------------------- */

echo "=== The press is an admin-post action of its own ===\n";

reset_world();
WPCPM_Institution_Resend::init();

ck( 'init() hooks the handler on its own admin-post action', $GLOBALS['hooks'], array( array( 'admin_post_wpcpm_institution_resend_invite', array( $R, 'handle_resend' ) ) ) );
ck( 'and nothing for a signed-out visitor', in_array( 'admin_post_nopriv_wpcpm_institution_resend_invite', array_column( $GLOBALS['hooks'], 0 ), true ), false );

/* ---- 2. a press that sends ------------------------------------------------ */

echo "\n=== A member resends the invitation of a student with no sign-in on record ===\n";

reset_world();
$before = WPCPM_Mail::last_invited( 10 );
$ended  = press( 20, 10 );

ck( 'the token is checked against the account the form names', $GLOBALS['referer'], array( 'wpcpm_institution_resend_invite_10' ) );
ck( 'one email is handed to WordPress, the site\'s own invitation to that one account', $GLOBALS['invited'], array( array( 10, 'user' ) ) );
ck( 'the invitation is stamped now', WPCPM_Mail::last_invited( 10 ) >= $NW && WPCPM_Mail::last_invited( 10 ) > $before, true );
ck( 'the notice says it is on its way and replaces the earlier link', outcome_of( 20 ), 'invite-resent' );
ck( 'naming the address masked, and the student it is about', array( flash_of( 20 )['detail'], flash_of( 20 )['user'] ), array( 'a***@example.test', 10 ) );
ck( 'and the press goes back to the card, at the line', $ended, 'redirect:https://example.test/institution-dashboard/?wpcpm_institution_student=10#wpcpm-student-invite' );
ck( 'one place of the institution\'s day is taken', spent( $A ), 1 );

$row = rows( $A );

ck( 'one history row, under the institution', count( $row ), 1 );
ck( 'saying what happened, to whom, by whom and on what ground',
	array( $row[0]['kind'], $row[0]['institution'], $row[0]['subject'], $row[0]['actor'], $row[0]['ground'], $row[0]['evidence'] ),
	array( 'student-invite-resent', $A, '10', 20, 'member', 'cache' ) );
ck( 'and that it was not the account\'s first', $row[0]['data'], array( 'student' => 10, 'first' => false ) );
ck( 'no other institution gets a row', rows( $B ), array() );

// A second press inside the fifteen minutes: the new link would cancel the one just mailed.
$ended = press( 20, 10 );

ck( 'a second press within fifteen minutes sends nothing', $GLOBALS['invited'], array() );
ck( 'and says why', outcome_of( 20 ), 'invite-too-soon' );
ck( 'and costs no second place', spent( $A ), 1 );
ck( 'and writes no second row', count( rows( $A ) ), 1 );

echo "\n=== A first invitation ===\n";

reset_world();
press( 20, 12 );

ck( 'a student never invited is sent one', $GLOBALS['invited'], array( array( 12, 'user' ) ) );
ck( 'and the notice does not speak of an earlier email', array( outcome_of( 20 ), flash_of( 20 )['detail'] ), array( 'invite-sent', 'c***@example.test' ) );
ck( 'the row says it was the first', rows( $A )[0]['data'], array( 'student' => 12, 'first' => true ) );

/* ---- 3. every refusal, in order ------------------------------------------- */

echo "\n=== Every refusal sends nothing, stamps nothing, records nothing and spends nothing ===\n";

/**
 * Press as one user and report everything a refusal must leave as it was.
 *
 * @param int $viewer  Who presses.
 * @param int $student The account the form names.
 * @return array{outcome: string, sent: array, stamp: bool, rows: int, spent: int, claimed: int, ended: string}
 */
function refused( $viewer, $student ) {
	global $A, $B;
	$stamp                     = WPCPM_Mail::last_invited( $student );
	$GLOBALS['ceiling_writes'] = 0;
	$ended                     = press( $viewer, $student );
	return array(
		'outcome' => outcome_of( $viewer ),
		'sent'    => $GLOBALS['invited'],
		'stamp'   => WPCPM_Mail::last_invited( $student ) === $stamp,
		'rows'    => count( rows( $A ) ) + count( rows( $B ) ),
		'spent'   => spent( $A ) + spent( $B ),
		'claimed' => $GLOBALS['ceiling_writes'],
		'ended'   => 0 === strpos( $ended, 'redirect:' ) ? 'redirect' : $ended,
	);
}
function nothing( $outcome, $ended = 'redirect' ) {
	return array( 'outcome' => $outcome, 'sent' => array(), 'stamp' => true, 'rows' => 0, 'spent' => 0, 'claimed' => 0, 'ended' => $ended );
}

reset_world();
$GLOBALS['bad_nonce'] = true;
ck( 'a token that does not match dies at the token: nothing is sent, stamped, recorded or spent', refused( 20, 10 ), nothing( '', 'wp_die:nonce' ) );
$GLOBALS['bad_nonce'] = false;

reset_world();
ck( 'a member of another institution gets the one refusal', refused( 21, 10 ), nothing( 'invite-refused' ) );
ck( 'an account that is nobody\'s member gets it too', refused( 40, 10 ), nothing( 'invite-refused' ) );
ck( 'and so does the student, for their own invitation', refused( 10, 10 ), nothing( 'invite-refused' ) );
ck( 'and an account that does not exist', refused( 20, 999 ), nothing( 'invite-refused' ) );
ck( 'nobody signed in sends nothing either, and is left no notice', refused( 0, 10 ), nothing( '' ) );
ck( 'an Administrator, for a student whose account names no institution, gets the one refusal', refused( 30, 16 ), nothing( 'invite-refused' ) );

$GLOBALS['settled'] = array( $B );
ck( 'a member whose agreement is not settled gets the one refusal', refused( 20, 10 ), nothing( 'invite-refused' ) );
$GLOBALS['settled'] = array( $A, $B );

ck( 'an account on the roster that is not a student is not sent one', refused( 20, 14 ), nothing( 'invite-error' ) );
ck( 'a student who has signed in is not sent one, and is told so', refused( 20, 11 ), nothing( 'invite-signed-in' ) );
ck( 'a student with no address is not sent one', refused( 20, 15 ), nothing( 'invite-error' ) );
ck( 'nor one whose address is not an address', refused( 20, 17 ), nothing( 'invite-error' ) );

update_user_meta( 10, WPCPM_Mail::STAMPS[ WPCPM_Roles::ROLE_STUDENT ], time() - 5 * MINUTE_IN_SECONDS );
ck( 'a student invited five minutes ago is not sent another', refused( 20, 10 ), nothing( 'invite-too-soon' ) );

// The order, read from the source: the policy before the student, the student before the gap, the
// gap before the day's place, the place before the send, the send before the row.
$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-resend.php' );
$body   = substr( $source, (int) strpos( $source, 'public static function handle_resend()' ) );
$body   = substr( $body, 0, (int) strpos( $body, 'public static function ceiling_key(' ) );
$at     = array();

foreach ( array( 'check_admin_referer(', 'self::decision(', 'self::student(', 'WPCPM_Sign_Ins::has_signed_in(', 'is_email(', 'WPCPM_Mail::may_invite(', 'WPCPM_Ceiling::claim(', 'WPCPM_Students_Sync::send_invite(', 'self::log(' ) as $call ) {
	$at[ $call ] = strpos( $body, $call );
}

$found = array_filter( $at, 'is_int' );
asort( $found );

ck( 'the handler asks in the order the refusals above are listed', array_keys( $found ), array_keys( $at ) );
ck( 'and reads the account from the posted form, never from the address', array( has( $body, 'WPCPM_Request::posted_id( self::FIELD_USER )' ), has( $body, 'WPCPM_Request::id(' ) ), array( true, false ) );
ck( 'the press and the line ask the policy for ACT_RESEND_INVITE, and for nothing else',
	array( substr_count( $source, 'WPCPM_Institution_Policy::ACT_RESEND_INVITE' ), preg_match_all( '/WPCPM_Institution_Policy::ACT_(?!RESEND_INVITE)/', $source ) ),
	array( 1, 0 ) );

/* ---- 4. the day's twenty -------------------------------------------------- */

echo "\n=== Twenty a day for each institution ===\n";

reset_world();
WPCPM_Ceiling::claim( WPCPM_Institution_Resend::ceiling_key( $A ), WPCPM_Institution_Resend::PER_DAY, DAY_IN_SECONDS, 19 );

press( 20, 10 );
ck( 'the twentieth of the day is sent', array( outcome_of( 20 ), spent( $A ) ), array( 'invite-resent', 20 ) );

$at_ceiling = refused( 20, 12 );
ck( 'the twenty-first is refused, sends nothing and records nothing', array( $at_ceiling['outcome'], $at_ceiling['sent'], $at_ceiling['stamp'] ), array( 'invite-ceiling', array(), true ) );
ck( 'and the day stays at twenty', spent( $A ), 20 );
ck( 'and the one row is the twentieth\'s', count( rows( $A ) ), 1 );

press( 21, 13 );
ck( 'another institution\'s day is its own', array( outcome_of( 21 ), spent( $B ) ), array( 'invite-resent', 1 ) );

$admin = fresh_reader();
press( $admin, 12 );
ck( 'an Administrator through the switcher is held to the institution\'s day too', outcome_of( $admin ), 'invite-ceiling' );

ck( 'the key is the institution\'s record ID as written',
	array( WPCPM_Institution_Resend::ceiling_key( $A ), WPCPM_Institution_Resend::ceiling_key( ' ' . $A . ' ' ) ),
	array( 'institution-resend:' . $A, 'institution-resend:' . $A ) );
ck( 'and keeps its case, so two records that differ only in case are two days',
	WPCPM_Institution_Resend::ceiling_key( $A ) === WPCPM_Institution_Resend::ceiling_key( strtolower( $A ) ), false );
ck( 'the figure is twenty', WPCPM_Institution_Resend::PER_DAY, 20 );

// At the edge of the day: nineteen places taken, then a send the host refuses, which gives its
// place back, so the next press is still the twentieth and sends, and the one after it is refused.
reset_world();
$GLOBALS['users'][18]                                            = new WP_User( 18, 'Ivo Petrov', 'ivo@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['umeta'][18][ WPCPM_Students_Sync::META_INSTITUTION ] = $A;
WPCPM_Ceiling::claim( WPCPM_Institution_Resend::ceiling_key( $A ), WPCPM_Institution_Resend::PER_DAY, DAY_IN_SECONDS, 19 );

$GLOBALS['mail_fails'] = true;
press( 20, 10 );
$GLOBALS['mail_fails'] = false;
ck( 'nineteen in, a send the host refuses says so and gives its place back, one place and no more', array( outcome_of( 20 ), spent( $A ) ), array( 'invite-mail-refused', 19 ) );

press( 20, 12 );
ck( 'so the next press is the twentieth, and sends', array( outcome_of( 20 ), $GLOBALS['invited'], spent( $A ) ), array( 'invite-sent', array( array( 12, 'user' ) ), 20 ) );

$edge = refused( 20, 18 );
ck( 'and the twenty-first is refused, sending, stamping and recording nothing', array( $edge['outcome'], $edge['sent'], $edge['stamp'], count( rows( $A ) ), spent( $A ) ), array( 'invite-ceiling', array(), true, 1, 20 ) );

/* ---- 5. a send that answers an error gives the place back ----------------- */

echo "\n=== A send that answers an error gives the day's place back ===\n";

reset_world();

// A day three places in, so the place given back is one place and not the whole day.
WPCPM_Ceiling::claim( WPCPM_Institution_Resend::ceiling_key( $A ), WPCPM_Institution_Resend::PER_DAY, DAY_IN_SECONDS, 3 );

// Another route invites the student in the instant between this press's claim and its send.
$GLOBALS['after_claim'] = function () {
	update_user_meta( 10, WPCPM_Mail::STAMPS[ WPCPM_Roles::ROLE_STUDENT ], time() );
};
press( 20, 10 );

ck( 'a send that finds the student invited a moment ago sends nothing', $GLOBALS['invited'], array() );
ck( 'says the fifteen minutes', outcome_of( 20 ), 'invite-too-soon' );
ck( 'gives the place back', spent( $A ), 3 );
ck( 'and records nothing', rows( $A ), array() );

reset_world();

// The account loses the Student role in the same instant: the send refuses it.
$GLOBALS['after_claim'] = function () {
	$GLOBALS['users'][12]->roles = array( WPCPM_Roles::ROLE_MENTOR );
};
press( 20, 12 );

ck( 'a send the sync refuses sends nothing', $GLOBALS['invited'], array() );
ck( 'says the invitation could not be sent', outcome_of( 20 ), 'invite-error' );
ck( 'gives the place back', spent( $A ), 0 );
ck( 'and records nothing', rows( $A ), array() );

echo "\n=== An email the host refuses is no send ===\n";

reset_world();
WPCPM_Ceiling::claim( WPCPM_Institution_Resend::ceiling_key( $A ), WPCPM_Institution_Resend::PER_DAY, DAY_IN_SECONDS, 3 );
$GLOBALS['mail_fails'] = true;
$before                = WPCPM_Mail::last_invited( 10 );
press( 20, 10 );

ck( 'the email was handed to WordPress, and the host refused it', $GLOBALS['invited'], array( array( 10, 'user' ) ) );
ck( 'the notice is the refused email\'s own', outcome_of( 20 ), 'invite-mail-refused' );
ck( 'with no address quoted', flash_of( 20 )['detail'], '' );
ck( 'the day\'s place is given back', spent( $A ), 3 );
ck( 'no history row is written', rows( $A ), array() );
ck( 'no stamp is written: the last invitation is still the one that went nine days ago', WPCPM_Mail::last_invited( 10 ), $before );
ck( 'and the refusal is listened for only around the send', in_array( 'wp_mail_failed', array_column( $GLOBALS['hooks'], 0 ), true ), false );

// The card the press comes back to: the notice, and the line naming the last invitation that went.
$reader = fresh_reader( $A );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, flash_of( 20 ), $reader );
$after = draw( $reader, 10 );
ck( 'the card after a refusal says the invitation could not be sent and that the earlier link no longer works, and its line names the last one that went, not the refused attempt',
	array( has( $after, 'The invitation could not be sent, and the link in any earlier invitation no longer works.' ), has( $after, 'Invitation last sent ' . gmdate( 'F j, Y', $before ) . '.' ), $before < time() - DAY_IN_SECONDS ),
	array( true, true, true ) );

$GLOBALS['mail_fails'] = false;
press( 20, 10 );
ck( 'so a press straight after sends, the fifteen minutes not having started', array( outcome_of( 20 ), $GLOBALS['invited'], spent( $A ), WPCPM_Mail::last_invited( 10 ) >= $NW ), array( 'invite-resent', array( array( 10, 'user' ) ), 4, true ) );
press( 20, 10 );
ck( 'while one that went holds the next press to the fifteen minutes', array( outcome_of( 20 ), $GLOBALS['invited'], spent( $A ) ), array( 'invite-too-soon', array(), 4 ) );

// A student never sent one, whose first invitation the host refuses: the card still says none went.
reset_world();
$GLOBALS['mail_fails'] = true;
press( 20, 12 );
$GLOBALS['mail_fails'] = false;
$reader = fresh_reader( $A );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, flash_of( 20 ), $reader );
$after = draw( $reader, 12 );
ck( 'a first invitation the host refuses leaves the card saying none was sent yet, with the button to send the first',
	array( outcome_of( 20 ), WPCPM_Mail::last_invited( 12 ), has( $after, 'No invitation sent yet.' ), has( $after, '>Send invitation</button>' ) ),
	array( 'invite-mail-refused', 0, true, true ) );

echo "\n=== Only a refusal of the invitation itself counts ===\n";

// Another plugin's filter sends an email of its own while the invitation is on its way, and the
// host refuses that one: the invitation went.
reset_world();
$GLOBALS['other_fails'] = array( array( 'notify@example.test' ), 'Program Desk <desk@example.test>' );
press( 20, 10 );

ck( 'other emails the host refuses while the invitation goes out are not the invitation: the notice says it is on its way', array( outcome_of( 20 ), $GLOBALS['invited'] ), array( 'invite-resent', array( array( 10, 'user' ) ) ) );
ck( 'and the place stays taken, and the history row is written', array( spent( $A ), count( rows( $A ) ) ), array( 1, 1 ) );

// The invitation's own refusal, however its recipient is written.
$own = array(
	'as core reports it, the address the account holds' => true,
	'with a name, in another case'                      => array( 'Ana Lopez <ANA@Example.TEST>' ),
	'in a comma-separated list'                         => 'someone@example.test, ana@example.test',
);

foreach ( $own as $how => $to ) {
	reset_world();
	$GLOBALS['mail_fails'] = $to;
	press( 20, 10 );
	ck( sprintf( 'the invitation\'s own refusal counts, %s: the refused email\'s notice, the place given back, no row', $how ), array( outcome_of( 20 ), spent( $A ), count( rows( $A ) ) ), array( 'invite-mail-refused', 0, 0 ) );
}

reset_world();
$GLOBALS['other_fails'] = array( array( 'notify@example.test' ) );
$GLOBALS['mail_fails']  = true;
press( 20, 10 );
ck( 'another email failing first does not hide the invitation failing after it', array( outcome_of( 20 ), spent( $A ), count( rows( $A ) ) ), array( 'invite-mail-refused', 0, 0 ) );

// A failure that names no recipient, which core's wp_mail() never reports, cannot be told apart
// from the invitation's own.
reset_world();
$GLOBALS['mail_fails'] = 'unnamed';
press( 20, 10 );
ck( 'a failure that names no recipient counts as the invitation\'s', array( outcome_of( 20 ), spent( $A ), count( rows( $A ) ) ), array( 'invite-mail-refused', 0, 0 ) );

/* ---- 6. two presses at the same moment ------------------------------------ */

echo "\n=== Two presses for one student at the same moment ===\n";

/**
 * A press another request makes while the first one is sending: its own reader and its own form,
 * the first press's reader and form put back once it has ended.
 *
 * @param int $viewer  Who presses.
 * @param int $student The account the form names.
 * @return string The redirect or the death, as the stand-ins report them.
 */
function press_meanwhile( $viewer, $student ) {
	$uid            = $GLOBALS['uid'];
	$post           = $_POST;
	$ended          = '';
	$GLOBALS['uid'] = (int) $viewer;
	$_POST          = array(
		'action' => WPCPM_Institution_Resend::ACTION,
		'user'   => (string) $student,
	);

	try {
		WPCPM_Institution_Resend::handle_resend();
	} catch ( Exception $e ) {
		$ended = $e->getMessage();
	}

	$GLOBALS['uid'] = $uid;
	$_POST          = $post;

	return $ended;
}

// Two members press for Ana in the same moment: both pass the fifteen minutes before either has
// stamped, and the second reaches the send while the first is sending.
reset_world();
$second                 = fresh_reader( $A );
$GLOBALS['during_send'] = function () use ( $second ) {
	press_meanwhile( $second, 10 );
};
press( 20, 10 );

ck( 'two members pressing for the same student at the same moment send one email', $GLOBALS['invited'], array( array( 10, 'user' ) ) );
ck( 'the first is told it is on its way, the second that it was refused as the fifteen minutes refuse it', array( outcome_of( 20 ), outcome_of( $second ) ), array( 'invite-resent', 'invite-too-soon' ) );
ck( 'the second press\'s place is given back, so the day counts one', spent( $A ), 1 );
ck( 'and the history holds one row, the first press\'s', array( count( rows( $A ) ), rows( $A )[0]['actor'] ?? 0 ), array( 1, 20 ) );

// A request that took Ana's lock and died before it sent: the press waits out the lock's lifetime.
reset_world();
$lock = 'wpcpm_invite_lock_10';
$died = class_exists( 'WPCPM_Lock' ) ? WPCPM_Lock::take( $lock, 600 ) : '';

$held = refused( 20, 10 );
ck( 'a press while a request that died holds the student\'s lock is refused as too soon, and sends, records and spends nothing',
	array( '' !== $died, $held['outcome'], $held['sent'], $held['stamp'], $held['rows'], $held['spent'] ),
	array( true, 'invite-too-soon', array(), true, 0, 0 ) );

// Ten minutes on: the row's time has passed.
if ( isset( $GLOBALS['opts'][ $lock ] ) ) {
	$GLOBALS['opts'][ $lock ] = (string) ( time() - 1 );
}
press( 20, 10 );
ck( 'once the lock has aged out the press sends, and lets the lock go', array( outcome_of( 20 ), $GLOBALS['invited'], spent( $A ), isset( $GLOBALS['opts'][ $lock ] ) ), array( 'invite-resent', array( array( 10, 'user' ) ), 1, false ) );

echo "\n=== A press whose browser names no page to go back to ===\n";

reset_world();
$GLOBALS['http_referer'] = false;
ck( 'goes back to the Institution Dashboard\'s page, at the line', press( 20, 10 ), 'redirect:https://example.test/institution-dashboard/#wpcpm-student-invite' );
$GLOBALS['dashboard_page'] = '';
ck( 'and to the site\'s home while no page holds the dashboard', press( 20, 12 ), 'redirect:https://example.test/#wpcpm-student-invite' );

/* ---- 7. a row that will not insert ---------------------------------------- */

echo "\n=== The invitation went, and its row would not insert ===\n";

reset_world();
$GLOBALS['no_insert'] = true;
press( 20, 10 );

ck( 'the invitation is still sent', $GLOBALS['invited'], array( array( 10, 'user' ) ) );
ck( 'and the notice says the history is missing it, naming the address', array( outcome_of( 20 ), flash_of( 20 )['detail'] ), array( 'invite-unlogged', 'a***@example.test' ) );
ck( 'the place stays taken, since the email went', spent( $A ), 1 );

/* ---- 8. an Administrator through the switcher ----------------------------- */

echo "\n=== An Administrator through the switcher ===\n";

reset_world();
press( 30, 10 );

ck( 'an Administrator may send it', $GLOBALS['invited'], array( array( 10, 'user' ) ) );
ck( 'the row names them, on the manager ground, under the student\'s institution',
	array( rows( $A )[0]['actor'], rows( $A )[0]['ground'], rows( $A )[0]['institution'] ),
	array( 30, 'manager', $A ) );
ck( 'and the send counts against that institution\'s day', spent( $A ), 1 );

$GLOBALS['settled'] = array( $B );
press( 30, 12 );
ck( 'an unsettled agreement does not stop an Administrator', outcome_of( 30 ), 'invite-sent' );
$GLOBALS['settled'] = array( $A, $B );

/* ---- 9. what each outcome says -------------------------------------------- */

echo "\n=== What each outcome says ===\n";

reset_world();

$expected = array(
	'invite-resent'       => array( 'success', 'The invitation is on its way to a***@example.test. The new link replaces the one in the earlier email.' ),
	'invite-sent'         => array( 'success', 'The invitation is on its way to a***@example.test.' ),
	'invite-too-soon'     => array( 'warning', 'This person was sent an invitation less than 15 minutes ago. Another one now would cancel the link in it, so nothing was sent.' ),
	'invite-ceiling'      => array( 'error', 'Your institution has sent 20 invitations today, the most a day allows, so nothing was sent. Try again tomorrow, or write to the program team.' ),
	'invite-signed-in'    => array( 'info', 'This student has signed in since the page was drawn, so nothing was sent.' ),
	'invite-error'        => array( 'error', 'The invitation could not be sent.' ),
	'invite-mail-refused' => array( 'error', 'The invitation could not be sent, and the link in any earlier invitation no longer works. Try again in a few minutes; if it fails again, write to your program contact.' ),
	'invite-refused'      => array( 'error', 'That record is not on your roster.' ),
	'invite-unlogged'     => array( 'error', 'The invitation is on its way to a***@example.test, but the entry saying who sent it could not be written, so it is not in this institution\'s history. Tell your program contact.' ),
);

ck( 'every outcome has its sentence, and no other outcome has one', WPCPM_Institution_Resend::messages( 'a***@example.test' ), $expected );
ck( 'the fifteen minutes are the sentence every route refuses with', WPCPM_Institution_Resend::messages()['invite-too-soon'][1], WPCPM_Mail::too_soon_message() );
ck( 'and a send that failed is the sentence the Resend invite in wp-admin prints, worded in the mail layer', WPCPM_Institution_Resend::messages()['invite-error'][1], method_exists( 'WPCPM_Mail', 'invite_failed_message' ) ? WPCPM_Mail::invite_failed_message() : 'no invite_failed_message()' );
ck( 'and a refused email the mail layer\'s sentence for an institution\'s members, with what to do next', WPCPM_Institution_Resend::messages()['invite-mail-refused'][1] ?? null, method_exists( 'WPCPM_Mail', 'invite_refused_message' ) ? WPCPM_Mail::invite_refused_message( 'member' ) : 'no invite_refused_message()' );
ck( 'which of the two a failed send says is the mail layer\'s reading of its answer, as wp-admin\'s Resend reads it: the press names no error code of its own',
	array( has( $body, 'WPCPM_Mail::invite_outcome( $result )' ), has( $body, "'wpcpm_invite_too_soon'" ) ),
	array( true, false ) );

/** Draw one student's line as one reader. */
function draw( $viewer, $student ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_Resend::render( $GLOBALS['users'][ (int) $student ] );
	return (string) ob_get_clean();
}

/* ---- 10. the line on the card --------------------------------------------- */

echo "\n=== The line on the card ===\n";

reset_world();

$line  = draw( 20, 10 );
$date  = gmdate( 'F j, Y', $NW - 9 * DAY_IN_SECONDS );
$since = gmdate( 'F j, Y', $SINCE );

ck( 'a student invited before reads the day the record began and when the last invitation went, in the site\'s date format',
	has( $line, '<p class="wpcpm-institution__invite-note">No sign-in on record since ' . $since . '. Invitation last sent ' . $date . '.</p>' ), true );
ck( 'the record\'s day is the sign-in record\'s own start', $since, 'October 15, 2026' );
ck( 'with a button to send it again', has( $line, '<button type="submit" class="wpcpm-button">Resend invitation</button>' ), true );
ck( 'in a one-press form that says it is sending, posting to admin-post.php',
	has( $line, '<form class="wpcpm-institution__invite-form" method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Sending...">' ), true );
ck( 'carrying the action and the student\'s account beside the two fields core\'s nonce field prints, the token and the page the press comes back to, and nothing else to read',
	array(
		has( $line, '<input type="hidden" name="action" value="wpcpm_institution_resend_invite" />' ),
		has( $line, '<input type="hidden" name="user" value="10" />' ),
		has( $line, '<input type="hidden" id="_wpnonce" name="_wpnonce" value="nonce-wpcpm_institution_resend_invite_10" /><input type="hidden" name="_wp_http_referer" value="/institution-dashboard/?wpcpm_institution_student=10" />' ),
		substr_count( $line, '<input' ),
	),
	array( true, true, true, 4 ) );
ck( 'its nonce keyed to the student\'s account', has( $line, 'value="nonce-wpcpm_institution_resend_invite_10"' ), true );
ck( 'under the line\'s anchor', has( $line, '<div class="wpcpm-institution__invite" id="wpcpm-student-invite">' ), true );

$first = draw( 20, 12 );

ck( 'a student never invited reads that none was sent', has( $first, '<p class="wpcpm-institution__invite-note">No sign-in on record since ' . $since . '. No invitation sent yet.</p>' ), true );
ck( 'and the button sends the first', has( $first, '>Send invitation</button>' ), true );
ck( 'keyed to that student', has( $first, 'value="nonce-wpcpm_institution_resend_invite_12"' ), true );

ck( 'a student who has signed in has no line and no button', draw( 20, 11 ), '' );
ck( 'nor does an account on the roster that is not a student', draw( 20, 14 ), '' );
ck( 'nor an account with no address the press could send to', draw( 20, 15 ), '' );
ck( 'nor an account whose address is not an address', draw( 20, 17 ), '' );
ck( 'a member of another institution is drawn nothing', draw( 21, 10 ), '' );
ck( 'nor is an account that is nobody\'s member', draw( 40, 10 ), '' );
ck( 'nor anybody signed out', draw( 0, 10 ), '' );

$GLOBALS['settled'] = array( $B );
ck( 'nor a member whose agreement is not settled', draw( 20, 10 ), '' );
$GLOBALS['settled'] = array( $A, $B );

$by_admin = draw( 30, 10 );
ck( 'an Administrator through the switcher sees the same line, keyed to the student and not to them', $by_admin, $line );
ck( 'but not for a student whose account names no institution, whom the press would refuse', draw( 30, 16 ), '' );

$GLOBALS['opts']['date_format'] = '<\b>Y';
ck( 'the note is escaped', has( draw( 20, 10 ), 'Invitation last sent &lt;b&gt;' ), true );

$GLOBALS['opts']['date_format'] = 'Y-m-d';
ck( 'both dates follow the site\'s own format',
	has( draw( 20, 10 ), 'No sign-in on record since ' . gmdate( 'Y-m-d', $SINCE ) . '. Invitation last sent ' . gmdate( 'Y-m-d', $NW - 9 * DAY_IN_SECONDS ) . '.' ), true );
$GLOBALS['opts']['date_format'] = 'F j, Y';

$GLOBALS['opts'][ WPCPM_Sign_Ins::OPT_SINCE ] = 0;
ck( 'while the site keeps no sign-in record there is no day to name, and no line', draw( 20, 10 ), '' );
$GLOBALS['opts'][ WPCPM_Sign_Ins::OPT_SINCE ] = $SINCE;

// What the card is asked about the student: only whether they have signed in, never when.
update_user_meta( 10, WPCPM_Sign_Ins::META_SIGN_IN, '' );
ck( 'a sign-in key holding no time reads as no sign-in on record', has( draw( 20, 10 ), 'No sign-in on record since ' ), true );
update_user_meta( 10, WPCPM_Sign_Ins::META_SIGN_IN, gmmktime( 12, 0, 0, 3, 3, 2026 ) );
ck( 'and once a time is on record the card prints nothing, so no time of a sign-in reaches the page', draw( 20, 10 ), '' );

/* ---- 11. the notices on the card ----------------------------------------- */

echo "\n=== What the card says after a press ===\n";

reset_world();

foreach ( $expected as $outcome => $message ) {
	$reader = fresh_reader( $A );
	WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => $outcome, 'user' => 10, 'detail' => 'a***@example.test' ), $reader );
	ck( sprintf( 'the card prints %s, in its tone, above the line', $outcome ),
		has( draw( $reader, 10 ), '<div class="wpcpm-institution__invite" id="wpcpm-student-invite"><p class="wpcpm-institution__invite-message is-' . $message[0] . '" role="status">' . esc_html( $message[1] ) . '</p><p class="wpcpm-institution__invite-note">' ), true );
}

$reader = fresh_reader( $A );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-resent', 'user' => 12, 'detail' => 'c***@example.test' ), $reader );
ck( 'a notice about another student is not printed on this card', has( draw( $reader, 10 ), 'is-success' ), false );
ck( 'and it is spent by that render, not left waiting for the card it was about', isset( $GLOBALS['umeta'][ $reader ]['wpcpm_flash'][ WPCPM_Institution_Resend::FLASH ] ), false );

$reader = fresh_reader( $A );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-error', 'user' => 14, 'detail' => '' ), $reader );
ck( 'an account that no longer holds the Student role gets the notice and no line',
	draw( $reader, 14 ),
	'<div class="wpcpm-institution__invite" id="wpcpm-student-invite"><p class="wpcpm-institution__invite-message is-error" role="status">The invitation could not be sent.</p></div>' );

$reader                                       = fresh_reader( $A );
$GLOBALS['opts'][ WPCPM_Sign_Ins::OPT_SINCE ] = 0;
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-resent', 'user' => 10, 'detail' => 'a***@example.test' ), $reader );
ck( 'while the site keeps no sign-in record, a notice is printed and no line',
	draw( $reader, 10 ),
	'<div class="wpcpm-institution__invite" id="wpcpm-student-invite"><p class="wpcpm-institution__invite-message is-success" role="status">The invitation is on its way to a***@example.test. The new link replaces the one in the earlier email.</p></div>' );
$GLOBALS['opts'][ WPCPM_Sign_Ins::OPT_SINCE ] = $SINCE;

$reader = fresh_reader( $A );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-signed-in', 'user' => 11, 'detail' => '' ), $reader );
ck( 'a student who signed in meanwhile gets the notice and no line',
	draw( $reader, 11 ),
	'<div class="wpcpm-institution__invite" id="wpcpm-student-invite"><p class="wpcpm-institution__invite-message is-info" role="status">This student has signed in since the page was drawn, so nothing was sent.</p></div>' );

$reader = fresh_reader( $A );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-error', 'user' => 15, 'detail' => '' ), $reader );
ck( 'an account with no address to send to gets the notice and no line',
	draw( $reader, 15 ),
	'<div class="wpcpm-institution__invite" id="wpcpm-student-invite"><p class="wpcpm-institution__invite-message is-error" role="status">The invitation could not be sent.</p></div>' );

$reader = fresh_reader( $A );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-resent', 'user' => 10, 'detail' => '<b>x</b>@example.test' ), $reader );
ck( 'what a notice quotes is escaped', has( draw( $reader, 10 ), '&lt;b&gt;x&lt;/b&gt;@example.test' ), true );

$reader = fresh_reader( $A );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-unknown', 'user' => 10, 'detail' => '' ), $reader );
ck( 'an outcome the card does not know prints nothing', has( draw( $reader, 10 ), 'role="status"' ), false );

$reader = fresh_reader( $B );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-resent', 'user' => 10, 'detail' => 'a***@example.test' ), $reader );
ck( 'a reader the policy refuses is printed nothing, notice or not', draw( $reader, 10 ), '' );
ck( 'and the notice is spent, not left waiting', isset( $GLOBALS['umeta'][ $reader ]['wpcpm_flash'][ WPCPM_Institution_Resend::FLASH ] ), false );

/* ---- 12. the stylesheet dresses what the line prints ---------------------- */

echo "\n=== The stylesheet dresses the line ===\n";

$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' ) );

foreach ( array( 'wpcpm-institution__invite', 'wpcpm-institution__invite-note', 'wpcpm-institution__invite-form', 'wpcpm-institution__invite-message' ) as $class ) {
	ck( sprintf( 'institution.css has a rule for .%s', $class ), 1 === preg_match( '/\.' . preg_quote( $class, '/' ) . '(?![a-zA-Z0-9_-])/', $css ), true );
}

ck( 'the notice takes the shape every flash on this page has', 1 === preg_match( '/\.wpcpm-agreement-panel__message,\s*\.wpcpm-institution__invite-message,\s*\.wpcpm-people__message\s*\{/', $css ), true );
ck( 'and the line sits close under the name it is about', 1 === preg_match( '/\.wpcpm-institution__student-identity:has\(\s*\+\s*\.wpcpm-institution__invite\s*\)\s*\{\s*margin-bottom:\s*0\.5em;/', $css ), true );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
