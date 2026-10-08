<?php
/**
 * The Sponsor Dashboard's three acting cards: profile, interests, sponsored mentors.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The profile is an allowlist: eight fields, spelled exactly as the base spells them and
 *   checked against bin/fixtures/sponsors-table-fields.json, because update_records() sends no
 *   typecast and one field spelled wrong is a 422 for the whole PATCH - so one rejected field
 *   rejects the whole save, and the audit row names which fields changed, never their values.
 * - The interests card writes the six choices, an events list and a note in one PATCH, appends
 *   one dated line to the base's own history column, mails the assigned manager (or every
 *   manager when none is assigned, through WPCPM_Institutions::notify_managers()), and is
 *   ceilinged at five a day per account, the same as the mentor-interest form.
 * - The sponsored-mentors card never names a student: only mentors and their counts, read
 *   through WPCPM_Mentors_Sync::sponsorship() so a mentor who is not Active is counted and not
 *   named. The one press to say "I would like to sponsor this mentor" goes to the same mailer
 *   and the same ceiling.
 * - Every handler claims through WPCPM_Sponsor_Roster::claim() before it reads or writes
 *   anything else, asserted by counting the calls in the source.
 * - WPCPM_Sponsors_Dashboard (Task 10) is a stub here, called by array callable exactly as the
 *   cards call it, so bin/check-references.php keeps failing on a direct call to a method that
 *   is not declared yet rather than being fooled by this suite's own stand-in.
 *
 * Run from the plugin root:  php bin/test-sponsor-cards.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']  = array();
$GLOBALS['umeta'] = array();
$GLOBALS['users'] = array();

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}

/**
 * Enough of `WP_User` for the real sponsor classes to run against.
 *
 * The constructor's argument order (id, roles, name, email) is Task 8's own
 * (bin/test-sponsors-screen.php), kept unchanged here because the fixture below already
 * constructs every account in that order: `new WP_User( 1, array( 'administrator' ), 'Manager',
 * 'maciej@a8c.com' )` reads correctly against it with nothing to adjust.
 */
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, array $roles = array(), $name = '', $email = '' ) {
		$this->ID           = (int) $id;
		$this->roles        = $roles;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( ' ', '', $name ) );
	}
	public function exists() { return $this->ID > 0; }
}

/**
 * The two outcomes `post()` tells apart, Task 8's own shape: `wp_die()` raises one, and here
 * `WPCPM_Sponsors_Dashboard::leave()` (below) raises the other directly, never through
 * `wp_safe_redirect()` - the cards hand it their flash and their record and it throws itself.
 */
class WPCPM_Test_Die extends Exception {}
class WPCPM_Test_Redirect extends Exception {}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
// Core's escapes leave an entity they recognize as it is: `esc_attr( 'a &gt; b' )` is `a &gt; b`,
// which a browser reads back as `a > b`. Only `esc_textarea()` escapes the ampersand again.
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
// The real WPCPM_Field_Value::clean_url() (required below) needs both of these at call time.
function esc_url_raw( $url, $protocols = null ) { return preg_match( '#^https?://#i', (string) $url ) ? $url : ''; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action( $h, $c, $p = 10, $n = 1 ) {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) { return $user; }
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
	}
	return false;
}
/** `get_users()` by meta key and value; a call with neither gets everyone. */
function get_users( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( ! isset( $args['meta_key'] ) ) { $out[] = $user; continue; }
		$value = $GLOBALS['umeta'][ (int) $id ][ $args['meta_key'] ] ?? null;
		if ( null !== $value && 0 === strcasecmp( (string) $value, (string) ( $args['meta_value'] ?? '' ) ) ) { $out[] = $user; }
	}
	return $out;
}
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	if ( ! $GLOBALS['nonce_ok'] ) {
		wp_die( 'The link you followed has expired.' );
	}
	return true;
}
function wp_die( $message = '', $code = 0 ) {
	throw new WPCPM_Test_Die( (string) $message . ( $code ? ' [' . (int) $code . ']' : '' ) );
}
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_nonce_field( $a = '', $n = '', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function selected( $a, $b = true, $echo = true ) {
	$r = ( (string) $a === (string) $b ) ? ' selected="selected"' : '';
	if ( $echo ) { echo $r; }
	return $r;
}
function checked( $a, $b = true, $echo = true ) {
	$r = ( (string) $a === (string) $b ) ? ' checked="checked"' : '';
	if ( $echo ) { echo $r; }
	return $r;
}
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function wp_date( $format, $ts = null, $zone = null ) { return gmdate( $format, (int) $ts ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function wp_nonce_url( $url, $a ) { return $url . '&_wpnonce=' . rawurlencode( $a ); }
function add_query_arg( $args, $url = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
/** Enough of `WP_Post` for `post_date()`'s `instanceof` check and the fixture below. */
class WP_Post {
	public $ID = 0;
	public $post_date = '';
	public function __construct( $id, $post_date = '' ) {
		$this->ID        = (int) $id;
		$this->post_date = $post_date;
	}
}
// The agreement card's own two direct reads, keyed like the real functions: `false` (not `true`
// or an array) for a post nobody staged, and every value of one meta key for `$single = false`.
function get_post( $p ) { if ( $p instanceof WP_Post ) { return $p; } return isset( $GLOBALS['agr_posts'][ (int) $p ] ) ? $GLOBALS['agr_posts'][ (int) $p ] : null; }
function get_post_meta( $id, $k, $single = false ) {
	$rows = isset( $GLOBALS['agr_pmeta'][ (int) $id ][ $k ] ) ? $GLOBALS['agr_pmeta'][ (int) $id ][ $k ] : array();
	return $single ? ( isset( $rows[0] ) ? $rows[0] : '' ) : $rows;
}

class WPCPM_Airtable {
	public function update_records( $table, array $records ) { $GLOBALS['patched'][] = array( $table, $records ); return isset( $GLOBALS['airtable_fail'] ) ? new WP_Error( 'x', 'Airtable said no' ) : array( $records[0]['id'] => true ); }
	public function get_record( $table, $id ) { $GLOBALS['read'][] = $id; if ( isset( $GLOBALS['record_fail'] ) ) { return new WP_Error( 'x', 'no' ); } return array( 'id' => $id, 'fields' => isset( $GLOBALS['live_fields'] ) ? $GLOBALS['live_fields'] : array() ); }
	/* Copied from the real client (includes/class-wpcpm-airtable.php): the interests card's
	   live read passes its history cell through this before comparing it. */
	public static function flatten( $value, $glue = ', ' ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) { return (string) $value['name']; }
			$parts = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) { $parts[] = (string) $item; }
				elseif ( is_array( $item ) && isset( $item['name'] ) && is_scalar( $item['name'] ) ) { $parts[] = (string) $item['name']; }
			}
			return implode( $glue, array_filter( $parts, 'strlen' ) );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}
}
class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student'; const ROLE_MENTOR = 'wpcpm_mentor'; const ROLE_INSTITUTION = 'wpcpm_institution'; const ROLE_SPONSOR = 'wpcpm_sponsor'; const ROLE_ADMIN = 'administrator'; const CAP_MANAGE = 'wpcpm_manage_program';
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
}
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const GROUND_SYSTEM = 'system'; const EVIDENCE_INDEX = 'index'; const EVIDENCE_CACHE = 'cache';
	public static function record_sponsor( array $e ) { $GLOBALS['audit'][] = $e; return count( $GLOBALS['audit'] ); }
}
class WPCPM_Request {
	// Both methods read $_POST directly, exactly as the real class does (includes/class-wpcpm-request.php):
	// the cards' own direct `isset( $_POST[...] )` presence checks (verbatim from the brief, matching
	// the codebase's established pattern of checking $_POST before reading it through this class) must
	// see the same field this class reads, which only holds if both read the one superglobal. Each
	// body is the real one: the one-line cleaner folds a line break into a space, and the text area
	// reader keeps every break as it was posted, CR LF included, and every line as it was written.
	public static function posted_text( $n, $f = '' ) { return isset( $_POST[ $n ] ) && is_scalar( $_POST[ $n ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $n ] ) ) ) : $f; }
	public static function posted_lines( $n, $f = '' ) { return isset( $_POST[ $n ] ) && is_scalar( $_POST[ $n ] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST[ $n ] ) ) ) : $f; }
	public static function posted_raw( $n, $f = '' ) { return isset( $_POST[ $n ] ) && is_scalar( $_POST[ $n ] ) ? (string) wp_unslash( $_POST[ $n ] ) : $f; }
	// The reader a link is read with: valid UTF-8, control characters dropped, trimmed, and every "%" kept.
	public static function posted_verbatim( $n, $f = '' ) { return isset( $_POST[ $n ] ) && is_scalar( $_POST[ $n ] ) ? trim( (string) preg_replace( '/[^\P{C}\n\r\t]+/u', '', (string) wp_unslash( $_POST[ $n ] ) ) ) : $f; }
}
/**
 * The flash a refused form's typing waits in: one value per user and channel, which `take()` hands
 * back once and clears, or `sweep()` clears unread. The real class also remembers within a request what it handed back, and
 * writes a slashed copy that core's user meta unslashes; bin/test-flash.php pins both on it.
 */
class WPCPM_Flash {
	public static function set( $channel, $value, $user_id = 0 ) { $GLOBALS['flash'][ (int) ( $user_id ?: $GLOBALS['uid'] ) ][ (string) $channel ] = $value; }
	public static function take( $channel, $user_id = 0 ) { $uid = (int) ( $user_id ?: $GLOBALS['uid'] ); $value = $GLOBALS['flash'][ $uid ][ (string) $channel ] ?? ''; unset( $GLOBALS['flash'][ $uid ][ (string) $channel ] ); return $value; }
	public static function sweep( $stale, $user_id = 0 ) { $uid = (int) ( $user_id ?: $GLOBALS['uid'] ); foreach ( $GLOBALS['flash'][ $uid ] ?? array() as $channel => $value ) { if ( $stale( (string) $channel, $value ) ) { unset( $GLOBALS['flash'][ $uid ][ $channel ] ); } } }
}
class WPCPM_Settings { public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; } }

class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) { $n = isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0; if ( $n + $amount > $limit ) { return false; } $GLOBALS['buckets'][ $key ] = $n + $amount; return true; }
	public static function count( $key, $window ) { return isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0; }
	public static function key( ...$p ) { return implode( ':', $p ); }
}
class WPCPM_Mail {
	public static function send( $user, $context, $build ) { $GLOBALS['sent'][] = array( 'user', $user->ID, $context, call_user_func( $build, $user ) ); return true; }
	public static function send_to( $email, $context, $build, $locale = '' ) { $GLOBALS['sent'][] = array( 'to', $email, $context, call_user_func( $build, null ) ); return true; }
}
class WPCPM_Institutions { public static function notify_managers( $context, $build, $key = 'agreement_notify' ) { $GLOBALS['sent'][] = array( 'managers', $key, $context, call_user_func( $build, null ) ); return isset( $GLOBALS['managers_reachable'] ) ? (int) $GLOBALS['managers_reachable'] : 2; } }
class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); }
	public static function sponsorship() { return $GLOBALS['sponsorship']; }
}
class WPCPM_Mentors_Dashboard {
	public static function get_mentees( $user_id ) { return isset( $GLOBALS['mentees'][ $user_id ] ) ? $GLOBALS['mentees'][ $user_id ] : array(); }
	/* The real helper's shape (includes/modules/class-wpcpm-mentors-dashboard.php): the WordPress.org photo redirect by username. */
	public static function avatar_url( $username, $email, $size = 64 ) { return 'https://wordpress.org/grav-redirect.php?user=' . rawurlencode( (string) $username ) . '&s=' . (int) $size . '&d=mm'; }
}
class WPCPM_Sponsor_Offers { public static function offers_of( $record ) { return isset( $GLOBALS['offers'][ $record ] ) ? $GLOBALS['offers'][ $record ] : array(); } }
class WPCPM_Sponsors_Dashboard {
	const FLASH = 'sponsor_dashboard';
	public static function page_url() { return 'https://example.test/sponsor-dashboard/'; }
	// The sentence after the status's own is kept apart, so every check of where a press landed reads as it did.
	public static function leave( $status, $card, $record = '', $detail = '' ) { $GLOBALS['left'] = array( $status, $card, $record ); $GLOBALS['left_detail'] = $detail; throw new WPCPM_Test_Redirect( $status ); }
	/* The real class's own merge loop (class-wpcpm-sponsors-dashboard.php, messages()), scoped to
	   the one card this file stubs in below: enough to prove the loop finds this card's words
	   without pulling in every other card's own suite. */
	public static function messages() {
		$messages = array( 'refused' => array( 'error', 'refused' ) );
		foreach ( array( 'WPCPM_Sponsor_Agreement_Card' ) as $card ) {
			if ( class_exists( $card ) && method_exists( $card, 'messages' ) ) {
				$messages = array_merge( $messages, (array) call_user_func( array( $card, 'messages' ) ) );
			}
		}
		return $messages;
	}
}
/**
 * A stand-in for the real `WPCPM_Sponsor_Agreement` (includes/modules/class-wpcpm-sponsor-agreement.php):
 * its own suite is bin/test-sponsor-agreement.php, and this file's job is the card's rendering,
 * not the model's upload/accept/return machinery, so `summary()` and `posts_for()` answer fixed
 * fixtures rather than the real Airtable-and-post-type logic. The constants are copied from the
 * real class, byte for byte, since the card's markup and this suite's assertions both key off them.
 */
class WPCPM_Sponsor_Agreement {
	const META_STATE = '_wpcpm_sagr_state';
	const META_NOTE  = '_wpcpm_sagr_note';
	const META_EVENT = '_wpcpm_sagr_event';

	const STATE_RETURNED = 'returned';
	const STATE_REVOKED  = 'revoked';

	const SUMMARY_NONE      = 'none';
	const SUMMARY_SUBMITTED = 'submitted';
	const SUMMARY_RETURNED  = 'returned';
	const SUMMARY_REVOKED   = 'revoked';
	const SUMMARY_ACCEPTED  = 'accepted';
	const SUMMARY_ON_FILE   = 'on_file';

	const ACTION_UPLOAD   = 'wpcpm_sponsor_agr_upload';
	const ACTION_DOWNLOAD = 'wpcpm_sponsor_agr_download';
	const ACTION_WITHDRAW = 'wpcpm_sponsor_agr_withdraw';

	const FIELD_FILE   = 'wpcpm_sponsor_agr_file';
	const FIELD_POST   = 'wpcpm_sponsor_agr_post';
	const FIELD_SIGNED = 'wpcpm_sponsor_agr_signed';

	public static function messages() {
		return array(
			'agreement-uploaded'  => array( 'success', 'Your signed agreement is uploaded. A program manager reads it and you will get an email either way.' ),
			'agreement-withdrawn' => array( 'success', 'The agreement is withdrawn and its file is deleted. Upload another whenever you are ready.' ),
			'refused'             => array( 'error', 'That is not something your account can do here.' ),
		);
	}

	public static function summary( $record ) {
		return isset( $GLOBALS['summary'] ) ? $GLOBALS['summary'] : array(
			'state'           => self::SUMMARY_NONE,
			'kind'            => '',
			'accepted_at'     => '',
			'agreement_id'    => 0,
			'pending_id'      => 0,
			'drive_url'       => '',
			'airtable_status' => '',
		);
	}

	public static function posts_for( $record ) {
		return isset( $GLOBALS['agr_fixture_posts'] ) ? $GLOBALS['agr_fixture_posts'] : array();
	}
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/cleaners.php';
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-profile.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-mentors.php';

// The fixture: one sponsor with a member (user 5), a manager (user 1), the team, three mentors.
$A = 'recSPONSOR0000001'; $M1 = 'recMENTOR00000001'; $M2 = 'recMENTOR00000002'; $M3 = 'recMENTOR00000003'; $M4 = 'recMENTOR00000004';
WPCPM_Sponsors_Index::write( array( $A => array( 'name' => 'Mango Example ', 'status' => 'Approved', 'website' => 'https://plugins.mango-example.test/', 'contact_person' => 'Rep One', 'contact_email' => 'maciej@a8c.com', 'product_type' => 'Hosting', 'offer' => 'One year free', 'instructions' => 'Use the code.', 'more_info' => '', 'support' => array( 'Sponsor tools or services' ), 'interests' => '2026-09-01 by Rep One: Sponsor tools or services', 'manager' => 'recTEAM0000000001', 'mentors' => array( $M1, $M2, $M4 ) ) ), time() );
WPCPM_Sponsors_Index::write_team( array( 'recTEAM0000000001' => array( 'name' => 'Maciej (Matt) Pilarski', 'email' => 'maciej@a8c.com', 'calendly' => 'https://calendly.com/matt' ) ), time() );
$GLOBALS['settings'] = array( 'sponsors_table' => 'tblSPONSORS' );
$GLOBALS['users'] = array( 1 => new WP_User( 1, array( 'administrator' ), 'Manager', 'maciej@a8c.com' ), 5 => new WP_User( 5, array( 'wpcpm_sponsor' ), 'Rep One', 'maciej@a8c.com' ), 42 => new WP_User( 42, array( 'wpcpm_mentor' ), 'Ines', 'maciej@a8c.com' ) );
$GLOBALS['manage'] = array( 1 );
$GLOBALS['umeta'][5] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $A, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['sponsorship'] = array(
	$M1 => array( 'name' => 'Ines Example', 'profile' => 'https://profiles.wordpress.org/ines-example/', 'status' => 'Active', 'user_id' => 42, 'sponsored' => true, 'wants' => false, 'company' => array( $A ), 'expertise' => array( 'Core' ) ),
	$M2 => array( 'name' => 'Sam Example', 'profile' => 'https://profiles.wordpress.org/sam-example/', 'status' => 'Active', 'user_id' => 0, 'sponsored' => true, 'wants' => false, 'company' => array( $A ), 'expertise' => array() ),
	$M3 => array( 'name' => 'Ana Looking', 'profile' => 'https://profiles.wordpress.org/mentor-one/', 'status' => 'Active', 'user_id' => 0, 'sponsored' => false, 'wants' => true, 'company' => array(), 'expertise' => array( 'Polyglots', 'Community' ) ),
);
$GLOBALS['mentees'][42] = array( array( 'name' => 'Student One', 'is_past' => false ), array( 'name' => 'Student Two', 'is_past' => false ), array( 'name' => 'Old Student', 'is_past' => true ) );
$GLOBALS['uid'] = 5; $GLOBALS['nonce_ok'] = true; $GLOBALS['patched'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['buckets'] = array();
$context = array( 'can_manage' => false, 'open' => '', 'viewer' => $GLOBALS['users'][5] );

// $_POST itself, not a side channel: both new cards check `isset( $_POST[...] )` directly
// before reading a field (the established pattern for a presence check, matching how the real
// WPCPM_Request::posted_text() reads $_POST directly too), so a helper that populated anything
// else would leave that check always false.
function post( array $fields, $action ) { $_POST = $fields; $GLOBALS['left'] = null; try { call_user_func( $action ); } catch ( WPCPM_Test_Redirect $e ) { return $GLOBALS['left']; } catch ( WPCPM_Test_Die $e ) { return array( 'die', $e->getMessage() ); } return array( 'fell-through' ); }
function card( $class, $record, $context ) { ob_start(); call_user_func( array( $class, 'render' ), $record, $context ); return ob_get_clean(); }

$fail   = 0;
$checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

echo "=== Profile: the allowlist ===\n";
ck( 'the eight fields, spelled as the base spells them', array_column( WPCPM_Sponsor_Profile::FIELDS, 'name' ), array( 'Website', 'Contact Person Full Name', 'Contact Email', 'Type of product', 'Offer', 'Brief instructions', 'More info link', "Anything else you'd like to share." ) );
$fixture = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/sponsors-table-fields.json' ), true );
ck( 'every one is a field of the table', array_values( array_diff( array_column( WPCPM_Sponsor_Profile::FIELDS, 'name' ), $fixture['fields'] ) ), array() );
ck( 'the product choices are the fixture\'s, byte for byte', WPCPM_Sponsor_Profile::CHOICES['Type of product'], $fixture['choices']['Type of product'] );
ck( 'and the support choices are too', WPCPM_Sponsor_Interests::CHOICES, $fixture['choices']['How would you like to support WP Credits?'] );
ck( 'a URL without a scheme is completed', WPCPM_Sponsor_Profile::clean( 'website', 'plugins.mango-example.test' ), array( 'ok' => true, 'value' => 'https://plugins.mango-example.test' ) );
ck( 'a URL with a user is refused', WPCPM_Sponsor_Profile::clean( 'website', 'https://name@host.test' )['ok'], false );
ck( 'and a URL with a password too', WPCPM_Sponsor_Profile::clean( 'more_info', 'https://user:pw@host.test/x' )['ok'], false );
ck( 'a choice spelled another way is refused', WPCPM_Sponsor_Profile::clean( 'product_type', 'hosting' )['ok'], false );
ck( 'an empty choice clears the cell', WPCPM_Sponsor_Profile::clean( 'product_type', '' ), array( 'ok' => true, 'value' => null ) );
ck( 'a text over MAX_TEXT is refused, with how far over it is, not cut', WPCPM_Sponsor_Profile::clean( 'instructions', str_repeat( 'x', 5000 ) ), array( 'ok' => false, 'value' => str_repeat( 'x', 5000 ), 'over' => 1000 ) );
ck( 'an address that is not one is refused', WPCPM_Sponsor_Profile::clean( 'contact_email', 'not-an-address' )['ok'], false );
ck( 'a field outside the allowlist is refused', WPCPM_Sponsor_Profile::clean( 'status', 'Approved' )['ok'], false );

echo "\n=== Profile: saving ===\n";
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://plugins.mango-example.test/', 'wpcpm_contact_person' => 'Rep One', 'wpcpm_contact_email' => 'maciej@a8c.com', 'wpcpm_product_type' => 'Plugin', 'wpcpm_offer' => 'One year free', 'wpcpm_instructions' => 'Use the code.', 'wpcpm_more_info' => '', 'wpcpm_anything' => '' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a member saves, and lands on the profile card', $r, array( 'profile-saved', 'profile', $A ) );
ck( 'only the changed cell is written, spelled as the base spells it', $GLOBALS['patched'][0][1][0]['fields'], array( 'Type of product' => 'Plugin' ) );
ck( 'the index says so at once', WPCPM_Sponsors_Index::row( $A )['product_type'], 'Plugin' );
ck( 'and an audit row names the fields, not their values', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data']['fields'], end( $GLOBALS['audit'] )['ground'] ), array( 'profile_saved', array( 'product_type' ), 'member' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://plugins.mango-example.test/', 'wpcpm_contact_person' => 'Rep One', 'wpcpm_contact_email' => 'maciej@a8c.com', 'wpcpm_product_type' => 'Plugin', 'wpcpm_offer' => 'One year free', 'wpcpm_instructions' => 'Use the code.' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'nothing changed is said, and nothing is written', array( $r[0], count( $GLOBALS['patched'] ) ), array( 'profile-unchanged', 1 ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => 'We can offer licences.' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'the free-text field saves', array( $r[0], end( $GLOBALS['patched'] )[1][0]['fields'] ), array( 'profile-saved', array( "Anything else you'd like to share." => 'We can offer licences.' ) ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => 'We can offer licences.' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'and is remembered, so the same text again is no change', array( $r[0], count( $GLOBALS['patched'] ) ), array( 'profile-unchanged', 2 ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://x@y', 'wpcpm_product_type' => 'Plugin' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'one rejected field rejects the whole save', array( $r[0], count( $GLOBALS['patched'] ) ), array( 'profile-rejected', 2 ) );
$GLOBALS['airtable_fail'] = true;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 'Two years free' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'the base refusing is said, and the index is untouched', array( $r[0], WPCPM_Sponsors_Index::row( $A )['offer'] ), array( 'profile-failed', 'One year free' ) );
unset( $GLOBALS['airtable_fail'] );
$GLOBALS['uid'] = 9; $GLOBALS['users'][9] = new WP_User( 9, array( 'subscriber' ), 'Stranger', 'maciej@a8c.com' );
$before_patched = count( $GLOBALS['patched'] );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 'Hijack' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a stranger is refused with the one message, and metered', array( $r[0], $GLOBALS['buckets']['sponsor-refused:9'], count( $GLOBALS['patched'] ) ), array( 'refused', 1, $before_patched ) );
$GLOBALS['uid'] = 5;
$html = card( 'WPCPM_Sponsor_Profile', $A, $context );
ck( 'the card is the canonical disclosure with the eight fields prefilled', substr_count( $html, 'name="wpcpm_' ) >= 9 && false !== strpos( $html, 'id="wpcpm-sponsor-profile"' ) && false !== strpos( $html, 'wpcpm-group__disclosure' ) && false !== strpos( $html, 'value="Plugin"' ), true );
ck( 'closed until a flash names it', strpos( $html, '<details' ) !== false && strpos( $html, ' open>' ) === false, true );
ck( 'and open when one does', strpos( card( 'WPCPM_Sponsor_Profile', $A, array_merge( $context, array( 'open' => 'profile' ) ) ), ' open>' ) !== false, true );
ck( 'the form says what changing the address does, and carries the guard', false !== strpos( $html, 'changes the address Airtable holds' ) && false !== strpos( $html, 'data-wpcpm-once' ), true );

echo "\n=== Profile: the three fields the primary offer owns ===\n";
$GLOBALS['offers'][ $A ] = array( 900 => array( 'id' => 900, 'primary' => true ) );
$owned = card( 'WPCPM_Sponsor_Profile', $A, $context );
ck( 'with a primary offer the three fields it owns are not inputs here', array( strpos( $owned, 'name="wpcpm_offer"' ), strpos( $owned, 'name="wpcpm_instructions"' ), strpos( $owned, 'name="wpcpm_more_info"' ) ), array( false, false, false ) );
ck( 'and the card says where they are edited instead', false !== strpos( $owned, 'edited on the Offers card' ), true );
$before_patched = count( $GLOBALS['patched'] );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://plugins.mango-example.test/', 'wpcpm_contact_person' => 'Rep One', 'wpcpm_contact_email' => 'maciej@a8c.com', 'wpcpm_product_type' => 'Plugin', 'wpcpm_anything' => 'We can offer licences.', 'wpcpm_offer' => 'Changed by the profile' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a posted offer is ignored, so the save writes nothing', array( $r[0], count( $GLOBALS['patched'] ), WPCPM_Sponsors_Index::row( $A )['offer'] ), array( 'profile-unchanged', $before_patched, 'One year free' ) );
unset( $GLOBALS['offers'] );
$unowned = card( 'WPCPM_Sponsor_Profile', $A, $context );
ck( 'and without an offer the three inputs are back', array( false !== strpos( $unowned, 'name="wpcpm_offer"' ), false !== strpos( $unowned, 'name="wpcpm_instructions"' ), false !== strpos( $unowned, 'name="wpcpm_more_info"' ) ), array( true, true, true ) );

echo "\n=== Interests ===\n";
// The append now reads the base's own current value first (item 1 of the final review fix
// wave): Airtable starts in agreement with the index's fixture, the one line already there.
$GLOBALS['live_fields'] = array( 'Sponsorship interests' => '2026-09-01 by Rep One: Sponsor tools or services' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_support' => array( 'Sponsor tools or services', 'Made up' ), 'wpcpm_events' => "WordCamp Europe 2027\nWordCamp Asia 2027", 'wpcpm_note' => 'Happy to help.' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'an interest is sent', $r, array( 'interest-sent', 'interests', $A ) );
$cells = end( $GLOBALS['patched'] )[1][0]['fields'];
ck( 'the multiple select is written with the known choices only', $cells['How would you like to support WP Credits?'], array( 'Sponsor tools or services' ) );
ck( 'and one dated line is appended to the history', substr_count( $cells['Sponsorship interests'], "\n" ) === 1 && false !== strpos( $cells['Sponsorship interests'], 'by Rep One: Sponsor tools or services; events: WordCamp Europe 2027, WordCamp Asia 2027; note: Happy to help.' ), true );
ck( 'the assigned manager is mailed, once, in the interest context', array( count( $GLOBALS['sent'] ), $GLOBALS['sent'][0][0], $GLOBALS['sent'][0][1], $GLOBALS['sent'][0][2] ), array( 1, 'user', 1, 'sponsor-interest' ) );
ck( 'the mail names the sponsor and what it said', false !== strpos( $GLOBALS['sent'][0][3]['body'], 'Mango Example' ) && false !== strpos( $GLOBALS['sent'][0][3]['body'], 'WordCamp Europe 2027' ), true );
ck( 'the audit row carries the line', end( $GLOBALS['audit'] )['kind'] === 'sponsor_interest' && false !== strpos( end( $GLOBALS['audit'] )['message'], 'Happy to help.' ), true );
$r = post( array( 'wpcpm_sponsor' => $A ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'nothing ticked and nothing written is said', $r[0], 'interest-empty' );
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => '' ) );
$GLOBALS['sent'] = array();
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_support' => array( 'Other (please specify)' ), 'wpcpm_note' => 'x' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'with no manager assigned, every manager is told, through the sponsor_notify setting', array( $GLOBALS['sent'][0][0], $GLOBALS['sent'][0][1] ), array( 'managers', 'sponsor_notify' ) );
$GLOBALS['managers_reachable'] = 0;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'Please reach out.' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'when nobody could be mailed, the sponsor is not told that somebody was', $r[0], 'interest-unsent' );
unset( $GLOBALS['managers_reachable'] );
for ( $i = 0; $i < 3; $i++ ) { post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'again' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) ); }
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'sixth' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'five a day per account', $r[0], 'interest-ceiling' );
$html = card( 'WPCPM_Sponsor_Interests', $A, $context );
ck( 'the card lists the six choices, the events box, the note and the history', substr_count( $html, 'name="wpcpm_support[]"' ) === 6 && false !== strpos( $html, 'wpcpm_events' ) && false !== strpos( $html, '2026-09-01 by Rep One' ), true );

echo "\n=== The live history is read right before the append ===\n";
// User 5's ceiling is already at five for today (the loop above); this scenario is about
// the read-before-append, not the ceiling, so it gets a fresh bucket like the mentors
// section below already does.
$GLOBALS['buckets']     = array();
$GLOBALS['live_fields'] = array( 'Sponsorship interests' => "2026-09-01 by Rep One: old line\n2026-09-02 by Rep One: grid edit" );
$GLOBALS['patched']     = array();
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'fresh note' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
$cells        = end( $GLOBALS['patched'] )[1][0]['fields'];
$new_line     = WPCPM_Sponsor_Interests::line( array(), array(), 'fresh note', 'Rep One', '1970-01-01' );
ck( 'the history is read live before the append, so a grid edit survives', $cells['Sponsorship interests'], $GLOBALS['live_fields']['Sponsorship interests'] . "\n" . $new_line );
unset( $GLOBALS['live_fields'] );

$GLOBALS['record_fail'] = true;
$GLOBALS['patched']     = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'unread history' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a history that cannot be read is not appended to', array( $r[0], $GLOBALS['patched'] ), array( 'interest-failed', array() ) );
unset( $GLOBALS['record_fail'] );

echo "\n=== A member whose sponsor left the index cannot be written back to ===\n";
// A stamp naming a record the index no longer holds: the roster's claim() still allows the
// member (the stamp is well-formed and the ground is ungated), but WPCPM_Sponsors_Index::row()
// answers null. A different account from every other check in this file, so its own ceiling
// is untouched by anything above or below.
$GLOBALS['users'][6] = new WP_User( 6, array( 'wpcpm_sponsor' ), 'Departed Rep', 'maciej@a8c.com' );
$GLOBALS['umeta'][6] = array( WPCPM_Sponsor_Members::META_RECORD_ID => 'recSPONSOR0000009', WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['uid']      = 6;
$GLOBALS['patched']  = array();
$r = post( array( 'wpcpm_sponsor' => 'recSPONSOR0000009', 'wpcpm_offer' => 'Anything' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a sponsor the index does not hold cannot be written back to (profile)', array( $r[0], $GLOBALS['patched'] ), array( 'profile-failed', array() ) );
$r = post( array( 'wpcpm_sponsor' => 'recSPONSOR0000009', 'wpcpm_note' => 'hello' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a sponsor the index does not hold cannot be written back to (interests)', array( $r[0], $GLOBALS['patched'] ), array( 'interest-failed', array() ) );
$GLOBALS['uid'] = 5;

echo "\n=== Sponsored mentors ===\n";
$linked = WPCPM_Sponsor_Mentors::linked( $A );
ck( 'the linked mentors are named with their student counts, never a student\'s name', array( array_column( $linked['mentors'], 'name' ), $linked['mentors'][0]['current'], $linked['mentors'][0]['past'], $linked['others'] ), array( array( 'Ines Example', 'Sam Example' ), 2, 1, 1 ) );
ck( 'linked() carries user_id, 0 for a mentor with no site account', array( $linked['mentors'][0]['user_id'], $linked['mentors'][1]['user_id'] ), array( 42, 0 ) );
ck( 'linked() carries the expertise for the chips', $linked['mentors'][0]['expertise'], array( 'Core' ) );
// The looking list carries the same counts the linked list does (owner request of 5 September 2026): give Ana a site account with one student.
$GLOBALS['sponsorship'][ $M3 ]['user_id'] = 43;
$GLOBALS['mentees'][43]                   = array( array( 'name' => 'Student Three', 'is_past' => false ) );
$looking = WPCPM_Sponsor_Mentors::looking();
ck( 'the mentors looking for a sponsor: Active, wanting, unsponsored', array_column( $looking, 'name' ), array( 'Ana Looking' ) );
ck( 'and each carries their account and their student counts', array( $looking[0]['user_id'], $looking[0]['current'], $looking[0]['past'], $looking[0]['expertise'] ), array( 43, 1, 0, array( 'Polyglots', 'Community' ) ) );
$html = card( 'WPCPM_Sponsor_Mentors', $A, $context );
ck( 'two cards: Your mentors and Mentors looking for a sponsor, each its own section and disclosure', array( substr_count( $html, '<section class="wpcpm-sponsor__card">' ), false !== strpos( $html, 'id="wpcpm-sponsor-mentors"' ), false !== strpos( $html, 'id="wpcpm-sponsor-looking"' ), strpos( $html, 'id="wpcpm-sponsor-mentors"' ) < strpos( $html, 'id="wpcpm-sponsor-looking"' ), strpos( $html, 'wpcpm-sponsor__subheading' ) ), array( 2, true, true, true, false ) );
ck( 'the summaries count the linked records and the mentors looking', array( false !== strpos( $html, 'Your mentors <span class="wpcpm-group__count">3</span>' ), false !== strpos( $html, 'Mentors looking for a sponsor <span class="wpcpm-group__count">1</span>' ) ), array( true, true ) );
ck( 'every mentor is a card in a grid: two linked, one looking', array( substr_count( $html, '<article class="wpcpm-mentor-tile">' ), substr_count( $html, '<div class="wpcpm-mentor-tiles">' ) ), array( 3, 2 ) );
ck( 'the photo comes from the WordPress.org profile, by the username in the profile address', array( false !== strpos( $html, 'grav-redirect.php?user=ines-example&#038;s=116' ) || false !== strpos( $html, 'grav-redirect.php?user=ines-example&s=116' ), false !== strpos( $html, 'Profile photo of Ines Example' ) ), array( true, true ) );
ck( 'the name links to the profile, the expertise is chips, the profile link is spelled out', array( false !== strpos( $html, '<h4 class="wpcpm-mentor-tile__name"><a href="https://profiles.wordpress.org/ines-example/" rel="external noopener">Ines Example</a></h4>' ), false !== strpos( $html, '<span class="wpcpm-mentor-tile__tag">Core</span>' ), false !== strpos( $html, '<span class="wpcpm-mentor-tile__tag">Polyglots</span>' ), substr_count( $html, 'WordPress.org profile' ) ), array( true, true, true, 3 ) );
ck( 'the one looking has the form, the linked ones do not', array( substr_count( $html, 'value="' . WPCPM_Sponsor_Mentors::ACTION_INTEREST_MENTOR . '"' ), strpos( $html, 'value="' . WPCPM_Sponsor_Mentors::ACTION_INTEREST_MENTOR . '"' ) > strpos( $html, 'id="wpcpm-sponsor-looking"' ) ), array( 1, true ) );
ck( 'and no student is named anywhere on it', strpos( $html, 'Student One' ) === false && strpos( $html, 'Old Student' ) === false && strpos( $html, 'Student Three' ) === false, true );
ck( 'a linked mentor with no site account yet says so, instead of a false zero', substr_count( $html, 'No site account yet' ), 1 );
ck( 'a linked mentor with an account shows real counts, and so does the one looking', array( false !== strpos( $html, '2 students now, 1 before' ), false !== strpos( $html, '1 student now, 0 before' ) ), array( true, true ) );
ck( 'the linked mentors who are not active are still counted under the grid', false !== strpos( $html, 'And 1 mentor who is not currently active.' ), true );
$saved_sponsorship      = $GLOBALS['sponsorship'];
$GLOBALS['sponsorship'] = array();
$stale = card( 'WPCPM_Sponsor_Mentors', $A, $context );
ck( 'before the mentors sync has filled the index the card says so, instead of calling every linked mentor inactive', array( false !== strpos( $stale, 'The mentor list refreshes with the next program sync.' ), strpos( $stale, 'not currently active' ) ), array( true, false ) );
ck( 'and the summary still counts the linked records', false !== strpos( $stale, '<span class="wpcpm-group__count">3</span>' ), true );
$GLOBALS['sponsorship'] = $saved_sponsorship;
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array();
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => 'recTEAM0000000001' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_mentor' => $M3 ), array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
ck( 'interest in a mentor is sent to the manager and logged, and lands on the looking card', array( $r[0], $r[1], $GLOBALS['sent'][0][2], end( $GLOBALS['audit'] )['kind'] ), array( 'mentor-interest-sent', 'looking', 'sponsor-interest', 'sponsor_interest_mentor' ) );
ck( 'the mail names the mentor', false !== strpos( $GLOBALS['sent'][0][3]['body'], 'Ana Looking' ), true );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_mentor' => $M1 ), array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
ck( 'a mentor who is not looking cannot be asked for', $r[0], 'mentor-interest-unknown' );
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => '' ) );
$GLOBALS['managers_reachable'] = 0;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_mentor' => $M3 ), array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
ck( 'nobody could be told, so the sentence says so', $r[0], 'mentor-interest-failed' );
ck( 'but the interest is on record either way, with the count', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data']['mailed'] ), array( 'sponsor_interest_mentor', 0 ) );
unset( $GLOBALS['managers_reachable'] );

echo "\n=== The agreement card (Phase S4) ===\n";
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-agreement-card.php';

/**
 * The agreement card's HTML for one state.
 *
 * @param string $record Sponsor record ID.
 * @param bool   $manage Whether the viewer manages the program.
 * @return string
 */
function agreement_card( $record, $manage = false ) {
	ob_start();
	WPCPM_Sponsor_Agreement_Card::render( $record, array( 'can_manage' => $manage, 'open' => '', 'viewer' => wp_get_current_user() ) );

	return (string) ob_get_clean();
}

// One document on file, standing in for this sponsor's whole history regardless of which
// current state $GLOBALS['summary'] takes on below: a returned agreement with two events, so
// the note and the history both have something real to print.
$GLOBALS['agr_posts']         = array( 501 => new WP_Post( 501, '2026-09-03 09:00:00' ) );
$GLOBALS['agr_pmeta']         = array(
	501 => array(
		WPCPM_Sponsor_Agreement::META_STATE => array( WPCPM_Sponsor_Agreement::STATE_RETURNED ),
		WPCPM_Sponsor_Agreement::META_NOTE  => array( 'Please have the country director countersign page 2 and resend.' ),
		WPCPM_Sponsor_Agreement::META_EVENT => array(
			array( 'event' => 'signed copy uploaded', 'at' => strtotime( '2026-09-03 09:00:00' ) ),
			array( 'event' => 'returned for changes', 'at' => strtotime( '2026-09-04 10:00:00' ) ),
		),
	),
);
$GLOBALS['agr_fixture_posts'] = array( $GLOBALS['agr_posts'][501] );

$GLOBALS['summary'] = array( 'state' => 'none', 'kind' => '', 'accepted_at' => '', 'agreement_id' => 0, 'pending_id' => 0, 'drive_url' => '', 'airtable_status' => '' );
$html               = agreement_card( $A );
ck( 'with nothing on file the card offers the upload form and says what it is for', array(
	false !== strpos( $html, 'enctype="multipart/form-data"' ),
	false !== strpos( $html, 'name="wpcpm_sponsor_agr_file"' ),
	false !== strpos( $html, 'name="wpcpm_sponsor_agr_signed" value="1"' ),
	false !== strpos( $html, 'accept="application/pdf,.pdf"' ),
), array( true, true, true, true ) );
ck( 'the unticked box still posts, so a dropped field and a forgotten tick read differently', false !== strpos( $html, 'name="wpcpm_sponsor_agr_signed" value="0"' ), true );

$GLOBALS['summary'] = array_merge( $GLOBALS['summary'], array( 'state' => 'submitted', 'pending_id' => 777 ) );
$html               = agreement_card( $A );
ck( 'a document in review says so, offers the copy back and offers Withdraw', array(
	false !== strpos( $html, 'wpcpm-agreement__state' ),
	false !== strpos( $html, 'wpcpm_sponsor_agr_download' ),
	false !== strpos( $html, 'wpcpm_sponsor_agr_withdraw' ),
	false !== strpos( $html, 'wpcpm_sponsor_agr_upload' ),
), array( true, true, true, false ) );
// Withdraw it deletes the file at once, so it asks first: the sentence rides on its form as a
// data-wpcpm-confirm mark, escaped for the attribute.
$withdraw_form = '';

foreach ( explode( '<form', $html ) as $chunk ) {
	if ( false !== strpos( $chunk, 'value="wpcpm_sponsor_agr_withdraw"' ) ) {
		$withdraw_form = '<form' . $chunk;
	}
}

ck( 'Withdraw it asks "Withdraw the agreement you uploaded?" on its form, and that is the only question on the page', array(
	false !== strpos( (string) strstr( $withdraw_form, '>', true ), ' data-wpcpm-confirm="' . esc_attr( 'Withdraw the agreement you uploaded? The file is deleted from this site at once, and you can upload another whenever you are ready.' ) . '"' ),
	false !== strpos( $withdraw_form, '>Withdraw it</button>' ),
	substr_count( $html, 'data-wpcpm-confirm' ),
), array( true, true, 1 ) );
ck( 'and says nothing about the review beyond how long it has been waiting', array(
	false !== strpos( $html, 'waiting for the program' ),
	false !== strpos( $html, 'Accept' ),
	false !== strpos( $html, 'Return' ),
), array( true, false, false ) );

$GLOBALS['summary'] = array_merge( $GLOBALS['summary'], array( 'state' => 'returned', 'pending_id' => 0 ) );
ck( 'a returned document prints the note and the upload form again', array(
	false !== strpos( agreement_card( $A ), 'wpcpm-agreement__note' ),
	false !== strpos( agreement_card( $A ), 'name="wpcpm_sponsor_agr_file"' ),
), array( true, true ) );

$GLOBALS['summary'] = array_merge( $GLOBALS['summary'], array( 'state' => 'accepted', 'agreement_id' => 778, 'accepted_at' => '2026-09-06' ) );
$html               = agreement_card( $A );
ck( 'an accepted agreement names the date, offers the download and offers a replacement', array(
	false !== strpos( $html, '2026-09-06' ),
	false !== strpos( $html, 'wpcpm_sponsor_agr_download' ),
	false !== strpos( $html, 'name="wpcpm_sponsor_agr_file"' ),
), array( true, true, true ) );

$GLOBALS['summary'] = array_merge( $GLOBALS['summary'], array( 'state' => 'on_file', 'kind' => 'legacy', 'drive_url' => 'https://drive.google.com/drive/folders/abc' ) );
$html               = agreement_card( $A );
ck( 'an on-file agreement says the program holds it and offers no download of a file it has not got', array(
	false !== strpos( $html, 'wpcpm-agreement__state' ),
	false !== strpos( $html, 'wpcpm_sponsor_agr_download' ),
), array( true, false ) );
ck( 'and never prints the Drive link to a sponsor: it is a program folder', false !== strpos( $html, 'drive.google.com' ), false );

ck( 'the history lists the document\'s events, newest first', false !== strpos( agreement_card( $A ), 'wpcpm-agreement__history' ), true );
ck( 'the dashboard\'s message map now finds this card\'s words', isset( WPCPM_Sponsors_Dashboard::messages()['agreement-uploaded'] ), true );

$card_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-agreement-card.php' );
ck( 'no em or en dash in the card', preg_match( '/\x{2013}|\x{2014}/u', $card_src ), 0 );
ck( 'the card decides nothing: no policy call, no write', preg_match( '/update_post_meta|update_option|WPCPM_Airtable/', $card_src ), 0 );

echo "\n=== House rules ===\n";
$all = '';
foreach ( array( 'profile', 'interests', 'mentors' ) as $f ) { $all .= file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-' . $f . '.php' ); }
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $all ), 0 );
ck( 'every handler claims before it writes', substr_count( $all, 'WPCPM_Sponsor_Roster::claim(' ) >= 3, true );
ck( 'the messages maps cover every status the handlers flash', array_values( array_diff( array( 'profile-saved', 'profile-unchanged', 'profile-rejected', 'profile-long', 'profile-failed', 'interest-sent', 'interest-unsent', 'interest-empty', 'interest-long', 'interest-ceiling', 'interest-failed', 'mentor-interest-sent', 'mentor-interest-unknown', 'mentor-interest-ceiling', 'mentor-interest-failed', 'refused' ), array_merge( array_keys( WPCPM_Sponsor_Profile::messages() ), array_keys( WPCPM_Sponsor_Interests::messages() ), array_keys( WPCPM_Sponsor_Mentors::messages() ) ) ) ), array() );


$GLOBALS['summary'] = array_merge( $GLOBALS['summary'], array( 'state' => 'none', 'agreement_id' => 0, 'pending_id' => 0, 'accepted_at' => '' ) );
$none               = agreement_card( $A );
ck( 'the card names the product, and both upload fields carry the Required mark in the label\'s voice', array(
	substr_count( $none, '>Collaboration Agreement<' ),
	substr_count( $none, '<span class="wpcpm-field__required">Required</span>' ),
), array( 1, 2 ) );

echo "\n=== Text people type: what the cleaner hands back, and what was meant ===\n";
// A person's words, and the form core's cleaners hand them back in: "<3 our " is a "<" that opens
// no tag, written `&lt;`; "<b>" and "</b>" go; the lone ">" stays; "< 12 welcome, adults >" is
// kept as typed, because white space follows its "<"; and after a "<" with no ">" to close it,
// the quote marks and the ampersands are entities too. These lose words, or read as entities in
// a mail, if a count, a box or a mail forgets that.
$t_team    = 'We <3 our <b>team</b> > all';
$t_team_cl = 'We &lt;3 our team > all';
$t_note    = "We <3 our <b>team</b> > all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 < 12. Q&A: \"blocks\" & themes, it's free.";
$t_note_cl = "We &lt;3 our team > all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 &lt; 12. Q&amp;A: &quot;blocks&quot; &amp; themes, it&#039;s free.";
$t_note_as = "We <3 our team > all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 < 12. Q&A: \"blocks\" & themes, it's free.";
/** A typing of exactly $limit characters as a person counts them (a line break is one), made of $unit and padded with "x". */
function cards_filled( $unit, $limit ) {
	$one  = mb_strlen( str_replace( "\r\n", "\n", $unit ) );
	$text = str_repeat( $unit, intdiv( $limit, $one ) );
	return $text . str_repeat( 'x', $limit - mb_strlen( str_replace( "\r\n", "\n", $text ) ) );
}
/** The Contact person's box as the card draws it: its markup, what a browser shows in it, and its maxlength. */
function cards_contact_box( $html ) {
	if ( ! preg_match( '/<input type="text" id="wpcpm-profile-contact_person" name="wpcpm_contact_person" value="([^"]*)" maxlength="(\d+)" \/>/', $html, $m ) ) {
		return array();
	}
	return array( 'markup' => $m[1], 'shown' => html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'maxlength' => (int) $m[2] );
}
ck( 'the stand-ins are core\'s: the cleaner hands back these forms, and only esc_textarea() escapes an ampersand again', array( sanitize_text_field( $t_team ), sanitize_textarea_field( $t_note ), esc_attr( 'a &gt; b' ), esc_textarea( 'a &gt; b' ) ), array( $t_team_cl, $t_note_cl, 'a &gt; b', 'a &amp;gt; b' ) );

echo "\n=== Anything else: every word and every line, counted as typed ===\n";
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['patched'] = array(); $GLOBALS['audit'] = array();
unset( $GLOBALS['managers_reachable'] );
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => 'recTEAM0000000001' ) );
$GLOBALS['live_fields'] = array( 'Sponsorship interests' => '2026-09-01 by Rep One: Sponsor tools or services' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $t_note ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a note with "<b>team</b>" in it is refused now, because the cleaner would take "<b>" and "</b>", and nothing is sent or written', array( $r, $GLOBALS['sent'], $GLOBALS['patched'] ), array( array( 'interest-loss', 'interests', $A ), array(), array() ) );
// The same words without the tag: a "<3" whose "<" opens no tag, a "<" that a ">" closes after a
// space, and a "<" with no ">" after it, the quote marks and the ampersands after it entities too.
$t_sent    = "We <3 our team, all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 < 12. Q&A: \"blocks\" & themes, it's free.";
$t_sent_cl = "We &lt;3 our team, all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 &lt; 12. Q&amp;A: &quot;blocks&quot; &amp; themes, it&#039;s free.";
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $t_sent ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a note with line breaks, a "<3", a "<" and the ampersands after one is sent', $r, array( 'interest-sent', 'interests', $A ) );
ck( 'and its mail says what was typed, line by line, with no entity in it', isset( $GLOBALS['sent'][0][3]['body'] ) ? $GLOBALS['sent'][0][3]['body'] : null, "Mango Example said on the Sponsor Dashboard:\n\n1970-01-01 by Rep One: note: " . $t_sent . "\n\nThe full history is in the Sponsorship interests column of the Sponsors table." );
$i_line = '1970-01-01 by Rep One: note: ' . str_replace( "\r\n", ' ', $t_sent_cl );
$cells  = end( $GLOBALS['patched'] )[1][0]['fields'];
ck( 'the program records keep one dated line for it, in the form the cleaner stored it, its line breaks one space each: the history is read line by line', array( $cells['Sponsorship interests'], substr_count( $cells['Sponsorship interests'], "\n" ) ), array( $GLOBALS['live_fields']['Sponsorship interests'] . "\n" . $i_line, 1 ) );
ck( 'the audit row carries that one line', end( $GLOBALS['audit'] )['message'], $i_line );
ck( 'and the card lists it as one entry, under the one before it', substr_count( card( 'WPCPM_Sponsor_Interests', $A, $context ), '<li>' ), 2 );

$n_at = cards_filled( "Ages 8 < 12. Q&A: \"blocks\" & themes, it's free.\r\n", 4000 );
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['patched'] = array(); $GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $n_at ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a note of 4,000 characters as typed, with 83 line breaks, is longer than that as the cleaner stores it', array( substr_count( $n_at, "\r\n" ), mb_strlen( sanitize_textarea_field( $n_at ) ) > 4000 ), array( 83, true ) );
ck( 'and is sent whole: the mail carries every character as typed', array( $r[0], isset( $GLOBALS['sent'][0][3]['body'] ) && false !== strpos( $GLOBALS['sent'][0][3]['body'], 'note: ' . $n_at . "\n\n" ) ), array( 'interest-sent', true ) );
ck( 'and so does the history, in the stored form', false !== strpos( end( $GLOBALS['patched'] )[1][0]['fields']['Sponsorship interests'], 'note: ' . str_replace( "\r\n", ' ', sanitize_textarea_field( $n_at ) ) ), true );
$GLOBALS['sent'] = array(); $GLOBALS['patched'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['left_detail'] = null;
$before_five = $GLOBALS['buckets'];
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $n_at . 'x' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'one character more is refused, with a sentence that says how much to take out', array( $r, $GLOBALS['left_detail'] ), array( array( 'interest-long', 'interests', $A ), 'Your note is 1 character over the limit of 4000. Shorten it and send it again.' ) );
ck( 'and nothing is written, mailed or logged, and none of the day\'s five is spent', array( $GLOBALS['patched'], $GLOBALS['sent'], $GLOBALS['audit'], $GLOBALS['buckets'] ), array( array(), array(), array(), $before_five ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $n_at . 'xyz' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'three more says three', $GLOBALS['left_detail'], 'Your note is 3 characters over the limit of 4000. Shorten it and send it again.' );
ck( 'the messages map words the refusal', WPCPM_Sponsor_Interests::messages()['interest-long'] ?? null, array( 'error', 'Nothing was sent.' ) );
unset( $GLOBALS['live_fields'] );

echo "\n=== The Contact person: counted, drawn and kept as typed ===\n";
$GLOBALS['patched'] = array(); $GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_contact_person' => $t_team ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a contact person with "<b>team</b>" in it is refused now, because the cleaner would take "<b>" and "</b>", and nothing is written', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['patched'] ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Contact person' ), array() ) );
card( 'WPCPM_Sponsor_Profile', $A, $context );
// The form an earlier release stored that typing in, which the base holds still: a "<3" written
// `&lt;3` and a lone ">" after it, the box below draws it back with the ">" held.
WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => $t_team_cl ) );
$box = cards_contact_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'its box shows "We <3 our team &gt; all", the ">" held as four characters, and leaves room for the three', array( $box['markup'] ?? null, $box['shown'] ?? null, $box['maxlength'] ?? null ), array( 'We &lt;3 our team &amp;gt; all', 'We <3 our team &gt; all', 203 ) );
$c_writes = count( $GLOBALS['patched'] );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_contact_person' => $box['shown'] ?? '' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'posted back unedited it is the stored text: nothing is written, and every word stays', array( $r[0], count( $GLOBALS['patched'] ), WPCPM_Sponsors_Index::row( $A )['contact_person'] ), array( 'profile-unchanged', $c_writes, $t_team_cl ) );

$c_at = cards_filled( 'Ages 8 < 12. Q&A: "blocks" & themes, it\'s free. ', 200 );
$GLOBALS['patched'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_contact_person' => $c_at ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a contact person of 200 characters as typed, longer than that as stored, is kept whole', array( $r[0], WPCPM_Sponsors_Index::row( $A )['contact_person'] === sanitize_text_field( $c_at ), mb_strlen( sanitize_text_field( $c_at ) ) > 200, end( $GLOBALS['patched'] )[1][0]['fields'] === array( 'Contact Person Full Name' => sanitize_text_field( $c_at ) ) ), array( 'profile-saved', true, true, true ) );
ck( 'and its box is drawn with the room it was counted in', cards_contact_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) )['maxlength'] ?? null, 200 );
$GLOBALS['patched'] = array(); $GLOBALS['left_detail'] = null;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_contact_person' => $c_at . 'x', 'wpcpm_product_type' => 'Service' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'one character more refuses the whole save, with a sentence that names the field and how much to take out', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['patched'], WPCPM_Sponsors_Index::row( $A )['product_type'] ), array( 'profile-long', '"Contact person" is 1 character over the limit of 200. Shorten it and save again.', array(), 'Plugin' ) );
ck( 'the messages map words the refusal', WPCPM_Sponsor_Profile::messages()['profile-long'] ?? null, array( 'error', 'Nothing was saved.' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $c_at ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'the offer line, the card\'s other one-line text, is counted the same way', array( $r[0], WPCPM_Sponsors_Index::row( $A )['offer'] === sanitize_text_field( $c_at ) ), array( 'profile-saved', true ) );

echo "\n=== The profile's two text areas: every line and word, counted, drawn and kept as typed ===\n";
/** A text area of the profile card as the card draws it: its markup, what a browser shows and posts, and its maxlength. */
function cards_profile_area( $html, $key ) {
	if ( ! preg_match( '/<textarea id="wpcpm-profile-' . $key . '" name="wpcpm_' . $key . '" rows="4" maxlength="(\d+)">(.*?)<\/textarea>/s', $html, $m ) ) {
		return array();
	}
	// The parser reads CR LF in the markup as LF, drops the one line feed right after the opening tag,
	// then reads each entity once; a browser posts every break as CR LF.
	$shown = html_entity_decode( (string) preg_replace( '/^\n/', '', str_replace( "\r\n", "\n", $m[2] ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return array( 'markup' => $m[2], 'shown' => $shown, 'posts' => str_replace( "\n", "\r\n", $shown ), 'maxlength' => (int) $m[1] );
}
$GLOBALS['patched'] = array(); $GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $t_note ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a text with "<b>team</b>" in it is refused now, because the cleaner would take "<b>" and "</b>", and nothing is written', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['patched'] ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Anything else you would like to share' ), array() ) );
card( 'WPCPM_Sponsor_Profile', $A, $context );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $t_sent ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a text with line breaks, a "<3", a "<" and the ampersands after one is saved with its lines, as the cleaner leaves it', array( $r[0], WPCPM_Sponsors_Index::row( $A )['anything'], end( $GLOBALS['patched'] )[1][0]['fields'] ?? null ), array( 'profile-saved', $t_sent_cl, array( "Anything else you'd like to share." => $t_sent_cl ) ) );
// The form an earlier release stored the typing with the tag in, which the base holds still: the
// first line's ">" comes after a "<3" written `&lt;3`, and the box below draws it held.
WPCPM_Sponsors_Index::patch( $A, array( 'anything' => $t_note_cl ) );
$area = cards_profile_area( card( 'WPCPM_Sponsor_Profile', $A, $context ), 'anything' );
ck( 'its box shows the words as typed, line by line, the ">" before the first "<" with no closing ">" held, with room for the three', array( $area['shown'] ?? null, $area['maxlength'] ?? null ), array( "We <3 our team &gt; all\nAges 8 < 12 welcome, adults > 18 pay.\nAges 8 < 12. Q&A: \"blocks\" & themes, it's free.", 4003 ) );
$GLOBALS['patched'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $area['posts'] ?? '' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'posted back unedited, every break as CR LF, it is the stored text: nothing is written', array( $r[0], $GLOBALS['patched'], WPCPM_Sponsors_Index::row( $A )['anything'] ), array( 'profile-unchanged', array(), $t_note_cl ) );
// The sponsors sync reads the base's cell as it stands, and the base can hand the break back as LF.
WPCPM_Sponsors_Index::patch( $A, array( 'anything' => "Line one\nLine two" ) );
$area = cards_profile_area( card( 'WPCPM_Sponsor_Profile', $A, $context ), 'anything' );
$r    = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $area['posts'] ?? '' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a text kept with LF and posted back with CR LF is the same text', array( $r[0], $GLOBALS['patched'], WPCPM_Sponsors_Index::row( $A )['anything'] ), array( 'profile-unchanged', array(), "Line one\nLine two" ) );

$p_at = cards_filled( "Ages 8 < 12. Q&A: \"blocks\" & themes, it's free.\r\n", 4000 );
$r    = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $p_at ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a text of 4,000 characters as typed, with 83 line breaks and longer as stored, is kept whole', array( $r[0], WPCPM_Sponsors_Index::row( $A )['anything'] === sanitize_textarea_field( $p_at ), mb_strlen( sanitize_textarea_field( $p_at ) ) > 4000 ), array( 'profile-saved', true, true ) );
ck( 'and its box is drawn with the room it was counted in', cards_profile_area( card( 'WPCPM_Sponsor_Profile', $A, $context ), 'anything' )['maxlength'] ?? null, 4000 );
$GLOBALS['patched'] = array(); $GLOBALS['left_detail'] = null;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $p_at . 'x', 'wpcpm_product_type' => 'Service' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'one character more refuses the whole save, with a sentence that names the field and how much to take out', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['patched'], WPCPM_Sponsors_Index::row( $A )['product_type'] ), array( 'profile-long', '"Anything else you would like to share" is 1 character over the limit of 4000. Shorten it and save again.', array(), 'Plugin' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_instructions' => $t_note ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'How students use it, the other text area, refuses the typing with the tag the same way', array( $r[0], $GLOBALS['left_detail'] ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'How students use it' ) ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_instructions' => $t_sent ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'and keeps its lines the same way', array( $r[0], WPCPM_Sponsors_Index::row( $A )['instructions'] ), array( 'profile-saved', $t_sent_cl ) );

echo "\n=== The events: each counted as typed, refused rather than cut, ten at most ===\n";
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['patched'] = array(); $GLOBALS['audit'] = array();
unset( $GLOBALS['managers_reachable'] );
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => 'recTEAM0000000001' ) );
$GLOBALS['live_fields'] = array( 'Sponsorship interests' => '2026-09-01 by Rep One: Sponsor tools or services' );
// "<Penang>" is a tag the cleaner takes; "< 3 talks" is a "<" that opens none, written `&lt;`: 119
// characters as typed, 122 as stored.
$e_line = 'WordCamp Asia 2027 <Penang> & WordCamp Europe 2027 in Malaga, Q&A day < 3 talks, workshops, contributor day and the after party';
$e_said = 'WordCamp Asia 2027 & WordCamp Europe 2027 in Malaga, Q&A day < 3 talks, workshops, contributor day and the after party';
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => $e_line . "\r\nWordCamp US 2027" ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'an event with "<Penang>" in it is refused now, because the cleaner would take it, and nothing is sent', array( $r[0], $GLOBALS['sent'] ), array( 'interest-loss', array() ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => $e_said . "\r\nWordCamp US 2027" ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'the same event without the tag, 118 characters as typed and 121 as stored, is sent whole: the mail names it as typed', array( $r[0], mb_strlen( $e_said ), mb_strlen( sanitize_text_field( $e_said ) ), isset( $GLOBALS['sent'][0][3]['body'] ) && false !== strpos( $GLOBALS['sent'][0][3]['body'], 'events: ' . $e_said . ', WordCamp US 2027' ) ), array( 'interest-sent', 118, 121, true ) );
$e_at = cards_filled( 'Q&A < 3 talks, "Asia" & more, ok ', 120 );
$GLOBALS['sent'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => $e_at ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'an event of 120 characters as typed, longer as stored, is sent whole', array( $r[0], mb_strlen( sanitize_text_field( $e_at ) ) > 120, isset( $GLOBALS['sent'][0][3]['body'] ) && false !== strpos( $GLOBALS['sent'][0][3]['body'], 'events: ' . $e_at ) ), array( 'interest-sent', true, true ) );
$GLOBALS['sent'] = array(); $GLOBALS['patched'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['left_detail'] = null;
$before_five = $GLOBALS['buckets'];
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => "WordCamp US 2027\r\n" . $e_at . 'x' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'one character more is refused with a sentence that names the event and how much to take out', array( $r, $GLOBALS['left_detail'] ), array( array( 'interest-long', 'interests', $A ), 'Event 2 is 1 character over the limit of 120. Shorten it and send it again.' ) );
ck( 'and nothing is written, mailed or logged, and none of the day\'s five is spent', array( $GLOBALS['patched'], $GLOBALS['sent'], $GLOBALS['audit'], $GLOBALS['buckets'] ), array( array(), array(), array(), $before_five ) );
$ten = array();
for ( $i = 1; $i <= 10; $i++ ) { $ten[] = 'WordCamp ' . $i; }
$GLOBALS['sent'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => implode( "\r\n", $ten ) . "\r\n\r\n" ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'ten events are sent, every one of them, a blank line counting for none', array( $r[0], isset( $GLOBALS['sent'][0][3]['body'] ) && false !== strpos( $GLOBALS['sent'][0][3]['body'], 'events: ' . implode( ', ', $ten ) . "\n" ) ), array( 'interest-sent', true ) );
$GLOBALS['sent'] = array(); $GLOBALS['patched'] = array(); $GLOBALS['left_detail'] = null;
$before_five = $GLOBALS['buckets'];
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => implode( "\r\n", $ten ) . "\r\nWordCamp 11" ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'an eleventh is refused, with a sentence that says how many, rather than dropped without a word', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['patched'], $GLOBALS['sent'], $GLOBALS['buckets'] ), array( 'interest-long', 'You named 11 events, and the limit is 10. Name the others in another message.', array(), array(), $before_five ) );
/** The interests form as a browser shows it: what each text area holds, and the choices ticked. */
function cards_interest_form( $html ) {
	$out = array();
	foreach ( array( 'events', 'note' ) as $key ) {
		// The parser drops the one line feed right after the opening tag.
		$out[ $key ] = preg_match( '/<textarea id="wpcpm-interest-' . $key . '" name="wpcpm_' . $key . '"[^>]*>(.*?)<\/textarea>/s', $html, $m ) ? html_entity_decode( (string) preg_replace( '/^\n/', '', str_replace( "\r\n", "\n", $m[1] ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : null;
	}
	preg_match_all( '/name="wpcpm_support\[\]" value="([^"]*)" checked="checked"/', $html, $m );
	$out['ticked'] = $m[1];
	return $out;
}
ck( 'the page the refusal lands on draws the eleven back in their box, as they were typed', cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) )['events'], implode( "\n", $ten ) . "\nWordCamp 11" );
ck( 'the box holds no more than ten events of 120 characters with their nine line breaks, and says so', 1 === preg_match( '#<textarea id="wpcpm-interest-events" name="wpcpm_events" rows="3" maxlength="1209" placeholder="WordCamp Europe 2027"></textarea><span class="wpcpm-student__note">One per line: up to 10 events of up to 120 characters each.</span>#', card( 'WPCPM_Sponsor_Interests', $A, $context ) ), true );

echo "\n=== The history folds white space as the one-line read always did ===\n";
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['patched'] = array(); $GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => "Tab\there\r\nTwo  spaces" ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a tab and a run of spaces are one space in the history, as a line break is; the mail keeps them', array( $r[0], end( $GLOBALS['audit'] )['message'], isset( $GLOBALS['sent'][0][3]['body'] ) && false !== strpos( $GLOBALS['sent'][0][3]['body'], "note: Tab\there\r\nTwo  spaces" ) ), array( 'interest-sent', '1970-01-01 by Rep One: note: Tab here Two spaces', true ) );
unset( $GLOBALS['live_fields'] );

echo "\n=== A value the base holds over its limit does not refuse a save that did not touch it ===\n";
/** A one-line box of the profile card as the card draws it: what a browser shows and posts, and its maxlength. */
function cards_line_box( $html, $key ) {
	if ( ! preg_match( '/<input type="text" id="wpcpm-profile-' . $key . '" name="wpcpm_' . $key . '" value="([^"]*)" maxlength="(\d+)" \/>/', $html, $m ) ) {
		return array();
	}
	return array( 'shown' => html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'maxlength' => (int) $m[2] );
}
// Written in the base's grid, which bounds neither column: an offer line of 251 characters, and a
// text of 4,050. The browser posts a prefilled value as it is, whatever the box's maxlength.
$o_251 = cards_filled( 'One year of hosting free. ', 251 );
$a_4050 = cards_filled( "We can offer licenses.\n", 4050 );
WPCPM_Sponsors_Index::patch( $A, array( 'offer' => $o_251, 'anything' => $a_4050 ) );
$html  = card( 'WPCPM_Sponsor_Profile', $A, $context );
$o_box = cards_line_box( $html, 'offer' );
$a_box = cards_profile_area( $html, 'anything' );
$GLOBALS['patched'] = array(); $GLOBALS['left_detail'] = null;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://plugins.mango-example.test/new/', 'wpcpm_offer' => $o_box['shown'] ?? '', 'wpcpm_anything' => $a_box['posts'] ?? '' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'posted back unedited, the offer line of 251 and the text of 4,050 are the stored ones: the website is saved and nothing else is written', array( $r[0], $GLOBALS['left_detail'], end( $GLOBALS['patched'] )[1][0]['fields'] ?? null, WPCPM_Sponsors_Index::row( $A )['offer'] === $o_251, WPCPM_Sponsors_Index::row( $A )['anything'] === $a_4050 ), array( 'profile-saved', '', array( 'Website' => 'https://plugins.mango-example.test/new/' ), true, true ) );
$GLOBALS['patched'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => ( $o_box['shown'] ?? '' ) . 'y' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'edited, it is measured, and refused', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['patched'] ), array( 'profile-long', '"Your offer, in one line" is 52 characters over the limit of 200. Shorten it and save again.', array() ) );

echo "\n=== Anything else and the events: a typing the cleaner would take words from is refused, and kept ===\n";
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['patched'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$GLOBALS['live_fields'] = array( 'Sponsorship interests' => '2026-09-01 by Rep One: Sponsor tools or services' );
$i_save  = array( 'WPCPM_Sponsor_Interests', 'handle_save' );
$i_note  = "Kids <12 free, adults >18 pay.\r\nA second line, \"quoted\" & C:\\drafts";
$i_event = "WordCamp Europe 2027\r\nWordCamp <Asia> 2027";
$i_row   = WPCPM_Sponsors_Index::row( $A );
$i_base  = array_values( array_intersect( WPCPM_Sponsor_Interests::CHOICES, (array) $i_row['support'] ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_support' => array( 'Sponsor a mentor or multiple mentors', 'Made up' ), 'wpcpm_events' => 'WordCamp Europe 2027', 'wpcpm_note' => $i_note ), $i_save );
ck( 'a note the cleaner would cut to "Kids 18 pay." is refused, with the sentence for a form that sends, naming the box by its label', array( $r, $GLOBALS['left_detail'] ), array( array( 'interest-loss', 'interests', $A ), 'Nothing was sent, because WordPress would remove part of what you typed in "Anything else". To keep every word, put a space after each "<" (or write "less than") and after each "%", then send it again.' ) );
ck( 'and nothing is written, mailed or logged, and none of the day\'s five is spent', array( $GLOBALS['patched'], $GLOBALS['sent'], $GLOBALS['audit'], $GLOBALS['buckets'], WPCPM_Sponsors_Index::row( $A ) === $i_row ), array( array(), array(), array(), array(), true ) );
ck( 'the messages map has the refusal, its whole sentence the detail', WPCPM_Sponsor_Interests::messages()['interest-loss'] ?? null, array( 'error', '' ) );
ck( 'the card draws the form again with each box as it was typed, a line break as the box counts it, and the choices as they were ticked', cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) ), array( 'events' => 'WordCamp Europe 2027', 'note' => str_replace( "\r\n", "\n", $i_note ), 'ticked' => array( 'Sponsor a mentor or multiple mentors' ) ) );
ck( 'and only once: drawn again, the boxes are empty and the choices are the program records\' again', cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) ), array( 'events' => '', 'note' => '', 'ticked' => $i_base ) );

$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => $i_event, 'wpcpm_note' => 'Plain words' ), $i_save );
ck( 'an event the cleaner would take words from is refused the same way, the box named by its label, and spends nothing', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['buckets'], $GLOBALS['patched'] ), array( 'interest-loss', WPCPM_Typed_Text::loss_message( 'Flagship events you would sponsor students to attend', 'send' ), array(), array() ) );
ck( 'and comes back as typed, with the note beside it, and nothing ticked, as it was posted', cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) ), array( 'events' => str_replace( "\r\n", "\n", $i_event ), 'note' => 'Plain words', 'ticked' => array() ) );

$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $i_note ), $i_save );
$GLOBALS['uid'] = 1;
$i_manager = cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, array( 'can_manage' => true, 'open' => '', 'viewer' => $GLOBALS['users'][1] ) ) );
$GLOBALS['uid'] = 5;
$i_profile = card( 'WPCPM_Sponsor_Profile', $A, $context );
$i_other   = cards_interest_form( card( 'WPCPM_Sponsor_Interests', 'recSPONSOR0000002', $context ) );
$i_own     = cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) );
ck( 'another person never sees it, a manager drawing the same card included, nor does another form: the profile card, or this card for another sponsor', array( $i_manager['note'], false !== strpos( $i_profile, 'Kids &lt;12' ), $i_other['note'], $i_own['note'] ), array( '', false, '', str_replace( "\r\n", "\n", $i_note ) ) );

$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_support' => array( 'Sponsor tools or services' ), 'wpcpm_note' => $n_at . 'xyz' ), $i_save );
$i_form = cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) );
ck( 'a note over its limit is refused as before, and the form now comes back with it, kept up to the box\'s limit of 4,000', array( $r[0], $GLOBALS['left_detail'], $i_form['note'] === str_replace( "\r\n", "\n", $n_at ), $i_form['ticked'], $GLOBALS['buckets'] ), array( 'interest-long', 'Your note is 3 characters over the limit of 4000. Shorten it and send it again.', true, array( 'Sponsor tools or services' ), array() ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => "WordCamp US 2027\r\n" . $e_at . 'x' ), $i_save );
ck( 'and so does an event over its limit', array( $r[0], cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) )['events'] ), array( 'interest-long', "WordCamp US 2027\n" . $e_at . 'x' ) );

$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $i_note ), $i_save );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'Sent this time.' ), $i_save );
ck( 'a typing a refusal kept goes when the form is sent again, so the page after a message that went through draws the boxes empty', array( $r[0], cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) )['note'] ), array( 'interest-sent', '' ) );
$i_keeps = "We <3 WordPress.\r\nAges 8 < 12 welcome, adults > 18 pay, 100% of them, Q&A: \"blocks\" & themes.";
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $i_keeps, 'wpcpm_events' => 'WordCamp Europe 2027 & < 3 talks' ), $i_save );
ck( 'a note and an event the cleaner keeps whole are sent as before, and keep nothing for the form', array( $r[0], isset( $GLOBALS['sent'][0][3]['body'] ) && false !== strpos( end( $GLOBALS['sent'] )[3]['body'], 'note: ' . $i_keeps ), isset( $GLOBALS['flash'][5][ 'typed:interests:' . $A ] ) ), array( 'interest-sent', true, false ) );
unset( $GLOBALS['live_fields'] );

echo "\n=== The profile: a typing the cleaner would take words from is refused, and kept ===\n";
/** The profile form as a browser shows it: each input's value, what each text area holds, and the product chosen. */
function cards_profile_form( $html ) {
	$out = array();
	foreach ( array( 'website', 'contact_person', 'contact_email', 'offer', 'more_info' ) as $key ) {
		$out[ $key ] = preg_match( '/name="wpcpm_' . $key . '" value="([^"]*)"/', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : null;
	}
	foreach ( array( 'instructions', 'anything' ) as $key ) {
		// The parser drops the one line feed right after the opening tag.
		$out[ $key ] = preg_match( '/<textarea id="wpcpm-profile-' . $key . '" name="wpcpm_' . $key . '"[^>]*>(.*?)<\/textarea>/s', $html, $m ) ? html_entity_decode( (string) preg_replace( '/^\n/', '', str_replace( "\r\n", "\n", $m[1] ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : null;
	}
	$out['product_type'] = preg_match( '/<option value="([^"]*)" selected="selected"/', $html, $m ) ? $m[1] : null;
	return $out;
}
/** The profile form posted as a browser posts it, every text area's breaks as CR LF, with the fields in $change replaced. */
function cards_profile_post( $record, array $shown, array $change = array() ) {
	$post = array( 'wpcpm_sponsor' => $record );
	foreach ( $shown as $key => $value ) {
		$post[ 'wpcpm_' . $key ] = in_array( $key, array( 'instructions', 'anything' ), true ) ? str_replace( "\n", "\r\n", (string) $value ) : (string) $value;
	}
	return array_merge( $post, $change );
}
$GLOBALS['uid'] = 5; $GLOBALS['buckets'] = array(); $GLOBALS['patched'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
unset( $GLOBALS['offers'] );
$p_save  = array( 'WPCPM_Sponsor_Profile', 'handle_save' );
$p_row   = WPCPM_Sponsors_Index::row( $A );
$p_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$p_lossy = 'Ana <ana@example.test>';
$p_site  = 'https://plugins.mango-example.test/kept/';
ck( 'the form drawn as the base holds it: an offer line of 251 and a text of 4,050, each over its limit', array( mb_strlen( (string) $p_shown['offer'] ), mb_strlen( (string) $p_shown['anything'] ) ), array( 251, 4050 ) );
$r = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_contact_person' => $p_lossy, 'wpcpm_website' => $p_site, 'wpcpm_product_type' => 'Service' ) ), $p_save );
ck( 'a contact person the cleaner would cut to "Ana" is refused, with the sentence that names the box by its label', array( $r, $GLOBALS['left_detail'] ), array( array( 'profile-loss', 'profile', $A ), WPCPM_Typed_Text::loss_message( 'Contact person' ) ) );
ck( 'and nothing is written: the program records, the index and the log are as they were, and nothing is spent', array( $GLOBALS['patched'], WPCPM_Sponsors_Index::row( $A ) === $p_row, $GLOBALS['audit'], $GLOBALS['buckets'] ), array( array(), true, array(), array() ) );
ck( 'the messages map has the refusal, its whole sentence the detail', WPCPM_Sponsor_Profile::messages()['profile-loss'] ?? null, array( 'error', '' ) );
$p_form = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'the card draws the form again with every field as it was left: the contact person and the website as typed, the product as chosen, the rest as posted', $p_form, array_merge( $p_shown, array( 'contact_person' => $p_lossy, 'website' => $p_site, 'product_type' => 'Service' ) ) );
ck( 'the offer line and the text the base holds over their limits come back whole: cut to the box\'s limit, the next save would store them short', array( mb_strlen( (string) $p_form['offer'] ), mb_strlen( (string) $p_form['anything'] ) ), array( 251, 4050 ) );
$r = post( cards_profile_post( $A, $p_form, array( 'wpcpm_contact_person' => 'Ana (ana@example.test)' ) ), $p_save );
ck( 'posted back with the fix, the save goes through and writes what changed, and nothing of the two the base holds over their limits', array( $r[0], end( $GLOBALS['patched'] )[1][0]['fields'] ?? null ), array( 'profile-saved', array( 'Website' => $p_site, 'Contact Person Full Name' => 'Ana (ana@example.test)', 'Type of product' => 'Service' ) ) );

$p_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_offer' => 'Get 10%cashback' ) ), $p_save );
$p_said  = $GLOBALS['left_detail'];
$p_first = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$p_again = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'the offer line the same way, named by its label, and drawn back as typed only once', array( $r[0], $p_said, $p_first['offer'], $p_again['offer'] ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Your offer, in one line' ), 'Get 10%cashback', $p_shown['offer'] ) );
$r       = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_instructions' => "Use the code\r\n<b>at checkout</b>" ) ), $p_save );
$p_said  = $GLOBALS['left_detail'];
$p_first = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r2      = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_anything' => "Kids <12 free,\r\nadults >18 pay" ) ), $p_save );
$p_said2 = $GLOBALS['left_detail'];
$p_again = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'and the two text areas, each named by its own label and drawn back as typed, a line break as the box counts it', array( $r[0], $p_said, $p_first['instructions'], $r2[0], $p_said2, $p_again['anything'] ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'How students use it' ), "Use the code\n<b>at checkout</b>", 'profile-loss', WPCPM_Typed_Text::loss_message( 'Anything else you would like to share' ), "Kids <12 free,\nadults >18 pay" ) );

$r   = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_contact_person' => 'Q&amp;A <b>x</b>' ) ), $p_save );
$box = cards_contact_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( '"Q&amp;A <b>x</b>" is refused and comes back in the text input exactly as typed, the "&amp;" five characters, in the room the box had', array( $r[0], $box['shown'] ?? null, $box['markup'] ?? null, $box['maxlength'] ?? null ), array( 'profile-loss', 'Q&amp;A <b>x</b>', 'Q&amp;amp;A &lt;b&gt;x&lt;/b&gt;', 200 ) );

$r = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_contact_person' => $p_lossy ) ), $p_save );
$GLOBALS['uid'] = 1;
$p_manager = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, array( 'can_manage' => true, 'open' => '', 'viewer' => $GLOBALS['users'][1] ) ) );
$GLOBALS['uid'] = 5;
$p_other = cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) );
$p_own   = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'another person never sees it, a manager drawing the same card included, nor does another form', array( $p_manager['contact_person'], $p_other['note'], $p_own['contact_person'] ), array( $p_shown['contact_person'], '', $p_lossy ) );

$r      = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_contact_person' => str_repeat( 'x', 205 ), 'wpcpm_product_type' => 'Hosting' ) ), $p_save );
$p_said = $GLOBALS['left_detail'];
$p_form = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'a contact person over its limit is refused as before, and the form now comes back with the typing, kept up to the box\'s limit of 200', array( $r[0], $p_said, $p_form['contact_person'], $p_form['product_type'] ), array( 'profile-long', '"Contact person" is 5 characters over the limit of 200. Shorten it and save again.', str_repeat( 'x', 200 ), 'Hosting' ) );
$r      = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_offer' => $p_shown['offer'] . 'y' ) ), $p_save );
$p_form = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'an offer line the base holds over its limit, edited longer, is refused as before and kept up to what its box was drawn with', array( $r[0], $p_form['offer'] ), array( 'profile-long', $p_shown['offer'] ) );

$r      = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_contact_person' => "Bad \xC3\x28 bytes", 'wpcpm_product_type' => 'Plugin' ) ), $p_save );
$p_form = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'a contact person that is not valid UTF-8, which the cleaner empties, is refused as a loss and not kept: its box shows the stored name, the choice beside it is kept', array( $r[0], $p_form['contact_person'], $p_form['product_type'] ), array( 'profile-loss', $p_shown['contact_person'], 'Plugin' ) );

$r = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_contact_person' => $p_lossy ) ), $p_save );
$r = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_product_type' => 'Plugin' ) ), $p_save );
ck( 'a typing a refusal kept goes when the form is posted again, so a save that went through before the page drew it is drawn as saved', array( $r[0], cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) )['contact_person'], WPCPM_Sponsors_Index::row( $A )['product_type'] ), array( 'profile-saved', $p_shown['contact_person'], 'Plugin' ) );
$p_keeps = "Ages 8 < 12 welcome, adults > 18 pay.\r\nQ&A: \"blocks\" & themes, it's free, 100% of them, &amp; and &copy; typed out.";
$r = post( cards_profile_post( $A, cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) ), array( 'wpcpm_contact_person' => 'We <3 WordPress', 'wpcpm_instructions' => $p_keeps ) ), $p_save );
ck( 'a typing the cleaner keeps whole saves as before, and keeps nothing for the form', array( $r[0], WPCPM_Sponsors_Index::row( $A )['contact_person'], WPCPM_Sponsors_Index::row( $A )['instructions'], isset( $GLOBALS['flash'][5][ 'typed:profile:' . $A ] ) ), array( 'profile-saved', 'We &lt;3 WordPress', sanitize_textarea_field( $p_keeps ), false ) );

echo "\n=== A kept typing that starts with a line break comes back with it ===\n";
// The parser drops the one line feed right after a text area's opening tag, so a box drawn with a
// kept typing writes one of its own first.
$GLOBALS['buckets'] = array(); $GLOBALS['flash'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => $i_event, 'wpcpm_note' => "\r\nleading break" ), $i_save );
ck( 'on the interests card', array( $r[0], cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) )['note'] ), array( 'interest-loss', "\nleading break" ) );
$r = post( cards_profile_post( $A, cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) ), array( 'wpcpm_contact_person' => $p_lossy, 'wpcpm_anything' => "\r\nleading break" ) ), $p_save );
ck( 'and on the profile', array( $r[0], cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) )['anything'] ), array( 'profile-loss', "\nleading break" ) );

echo "\n=== A box posted back as it was drawn is the stored text, whatever the base holds in it ===\n";
// Written in the base's grid, which no cleaner reads: a tag and a percent octet the cleaner would take.
$GLOBALS['uid'] = 5; $GLOBALS['patched'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['left_detail'] = null;
$s_offer = 'Use code <SAVE20>';
$s_instr = "Get 10%cashback\nat checkout.";
$s_site  = 'https://plugins.mango-example.test/stored/';
WPCPM_Sponsors_Index::patch( $A, array( 'offer' => $s_offer, 'instructions' => $s_instr ) );
$s_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r = post( cards_profile_post( $A, $s_shown, array( 'wpcpm_website' => $s_site ) ), $p_save );
ck( 'a profile whose offer line holds "<SAVE20>" and whose instructions hold "10%cashback", posted back as drawn with the website changed, saves the website alone', array( $r[0], end( $GLOBALS['patched'] )[1][0]['fields'] ?? null ), array( 'profile-saved', array( 'Website' => $s_site ) ) );
ck( 'and the two keep their bytes in the index: a box posted back as drawn is neither asked about losses nor rewritten', array( WPCPM_Sponsors_Index::row( $A )['offer'] === $s_offer, WPCPM_Sponsors_Index::row( $A )['instructions'] === $s_instr ), array( true, true ) );
$GLOBALS['patched'] = array();
$r = post( cards_profile_post( $A, $s_shown, array( 'wpcpm_website' => $s_site, 'wpcpm_offer' => 'Use code <SAVE20> now' ) ), $p_save );
ck( 'the same box edited to "Use code <SAVE20> now" is refused, and nothing is written', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['patched'], WPCPM_Sponsors_Index::row( $A )['offer'] === $s_offer ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Your offer, in one line' ), array(), true ) );
card( 'WPCPM_Sponsor_Profile', $A, $context );
$r = post( cards_profile_post( $A, $s_shown, array( 'wpcpm_website' => $s_site, 'wpcpm_instructions' => "Get 10%cashback\r\nat checkout, now." ) ), $p_save );
ck( 'and so are the instructions, edited', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['patched'], WPCPM_Sponsors_Index::row( $A )['instructions'] === $s_instr ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'How students use it' ), array(), true ) );
card( 'WPCPM_Sponsor_Profile', $A, $context );
// The unedited test reads what was typed, never what the cleaner makes of it: a typing the cleaner
// cuts back to the stored text is an edit, and the words it would take are the person's.
WPCPM_Sponsors_Index::patch( $A, array( 'offer' => 'Use code SAVE20' ) );
$s_plain = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$GLOBALS['patched'] = array(); $GLOBALS['left_detail'] = null;
$r = post( cards_profile_post( $A, $s_plain, array( 'wpcpm_offer' => 'Use code SAVE20<b></b>' ) ), $p_save );
ck( 'a stored "Use code SAVE20" typed as "Use code SAVE20<b></b>", which the cleaner cuts back to the stored text, is refused, and the stored text is unchanged', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['patched'], WPCPM_Sponsors_Index::row( $A )['offer'] ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Your offer, in one line' ), array(), 'Use code SAVE20' ) );
card( 'WPCPM_Sponsor_Profile', $A, $context );

echo "\n=== A stored value with white space at an end, posted back as drawn ===\n";
// The cleaner trims what it reads. Written in the base's grid, or left by an older card's cut, a
// stored value can end in a space or begin with a line break, and a box can hand it back without
// them: a text area drops a line break its text begins with, and a link's box drops the spaces at
// its ends. A box nobody touched is still the stored value, and is not rewritten.
$GLOBALS['patched'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => 'Test Sponsor Contact ', 'instructions' => 'Use the code at checkout.' ) );
$w_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $w_shown ), $p_save );
ck( 'a stored "Test Sponsor Contact " is drawn with its space and, posted back unedited, writes nothing, logs nothing and says nothing changed', array( $w_shown['contact_person'], $r[0], $GLOBALS['patched'], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['contact_person'] ), array( 'Test Sponsor Contact ', 'profile-unchanged', array(), array(), 'Test Sponsor Contact ' ) );
WPCPM_Sponsors_Index::patch( $A, array( 'instructions' => "\nUse the code at checkout." ) );
$GLOBALS['patched'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$w_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $w_shown ), $p_save );
ck( 'instructions stored with a line break first, which their box drops, posted back unedited: nothing written or logged, nothing changed, the stored text kept', array( $w_shown['instructions'], $r[0], $GLOBALS['patched'], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['instructions'] ), array( 'Use the code at checkout.', 'profile-unchanged', array(), array(), "\nUse the code at checkout." ) );
WPCPM_Sponsors_Index::patch( $A, array( 'instructions' => "\nGet 10%cashback at checkout." ) );
$GLOBALS['patched'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$w_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $w_shown ), $p_save );
ck( 'and the same with a "%" the cleaner would take: not refused for a box the member did not touch', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['patched'], WPCPM_Sponsors_Index::row( $A )['instructions'] ), array( 'profile-unchanged', '', array(), "\nGet 10%cashback at checkout." ) );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => 'https://plugins.mango-example.test/ ', 'instructions' => 'Use the code at checkout.' ) );
$GLOBALS['patched'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$w_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$w_post  = cards_profile_post( $A, $w_shown, array( 'wpcpm_website' => trim( (string) $w_shown['website'] ) ) );
$r       = post( $w_post, $p_save );
ck( 'a website stored with a space after it, posted back by its box without the space: nothing written or logged, nothing changed', array( $r[0], $GLOBALS['patched'], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['website'] ), array( 'profile-unchanged', array(), array(), 'https://plugins.mango-example.test/ ' ) );
$GLOBALS['patched'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$r = post( array_merge( $w_post, array( 'wpcpm_contact_person' => 'Test Sponsor Contact, Sales' ) ), $p_save );
ck( 'an edit beside them is written as before, and only the edit', array( $r[0], end( $GLOBALS['patched'] )[1][0]['fields'] ?? null, end( $GLOBALS['audit'] )['data']['fields'] ?? null ), array( 'profile-saved', array( 'Contact Person Full Name' => 'Test Sponsor Contact, Sales' ), array( 'contact_person' ) ) );

echo "\n=== The two links keep every percent octet, typed or posted back as drawn ===\n";
// A link is a code, not prose: the one-line cleaner removes every "%" followed by two hexadecimal
// digits, which takes "%C3%B3" out of an address written in another alphabet and "%20" out of a
// query. A link box posted back as it was drawn is the stored link, as the text boxes are.
$l_wiki = 'https://pl.wikipedia.org/wiki/Krak%C3%B3w';
$l_utm  = 'https://plugins.mango-example.test/?utm_source=wordpress%20education';
WPCPM_Sponsors_Index::patch( $A, array( 'website' => $l_wiki, 'more_info' => $l_utm, 'contact_person' => 'Test Sponsor Contact' ) );
$GLOBALS['patched'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$l_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $l_shown, array( 'wpcpm_contact_person' => 'Test Sponsor Contact, Sales' ) ), $p_save );
ck( 'a website and a "Link with more information" stored with percent octets are drawn as stored', array( $l_shown['website'], $l_shown['more_info'] ), array( $l_wiki, $l_utm ) );
ck( 'posted back untouched while the contact person changes, the PATCH holds the contact person only, and the one audit line names no link', array( $r[0], $GLOBALS['patched'][0][1][0]['fields'] ?? null, count( $GLOBALS['patched'] ), array_column( array_column( $GLOBALS['audit'], 'data' ), 'fields' ) ), array( 'profile-saved', array( 'Contact Person Full Name' => 'Test Sponsor Contact, Sales' ), 1, array( array( 'contact_person' ) ) ) );
ck( 'and the index keeps both links byte for byte', array( WPCPM_Sponsors_Index::row( $A )['website'], WPCPM_Sponsors_Index::row( $A )['more_info'] ), array( $l_wiki, $l_utm ) );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => 'https://plugins.mango-example.test/', 'more_info' => '' ) );
$GLOBALS['patched'] = array(); $GLOBALS['audit'] = array();
$l_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $l_shown, array( 'wpcpm_website' => $l_wiki, 'wpcpm_more_info' => $l_utm ) ), $p_save );
ck( 'the same two links typed fresh are written and stored with their octets', array( $r[0], end( $GLOBALS['patched'] )[1][0]['fields'] ?? null, WPCPM_Sponsors_Index::row( $A )['website'], WPCPM_Sponsors_Index::row( $A )['more_info'] ), array( 'profile-saved', array( 'Website' => $l_wiki, 'More info link' => $l_utm ), $l_wiki, $l_utm ) );
// Written in the base's grid, a link can lack the scheme the card would add, or carry a name before
// its host, which the card refuses when it is typed. Neither is rewritten or refused when its box
// comes back as it was drawn.
WPCPM_Sponsors_Index::patch( $A, array( 'website' => 'plugins.mango-example.test/grid', 'more_info' => 'https://name@host.test/grid' ) );
$GLOBALS['patched'] = array(); $GLOBALS['audit'] = array();
$l_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $l_shown, array( 'wpcpm_contact_person' => 'Test Sponsor Contact' ) ), $p_save );
ck( 'a website stored without a scheme and a link stored with a name before its host, posted back untouched beside an edit: only the edit is written', array( $r[0], end( $GLOBALS['patched'] )[1][0]['fields'] ?? null, WPCPM_Sponsors_Index::row( $A )['website'], WPCPM_Sponsors_Index::row( $A )['more_info'] ), array( 'profile-saved', array( 'Contact Person Full Name' => 'Test Sponsor Contact' ), 'plugins.mango-example.test/grid', 'https://name@host.test/grid' ) );
$GLOBALS['patched'] = array();
$r = post( cards_profile_post( $A, cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) ), array( 'wpcpm_more_info' => 'https://name@host.test/typed' ) ), $p_save );
ck( 'while the same kind of link typed into the box is refused as before, and nothing is written', array( $r[0], $GLOBALS['patched'], WPCPM_Sponsors_Index::row( $A )['more_info'] ), array( 'profile-rejected', array(), 'https://name@host.test/grid' ) );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
