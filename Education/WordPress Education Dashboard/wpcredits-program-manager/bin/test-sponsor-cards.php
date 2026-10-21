<?php
/**
 * The Sponsor Dashboard's acting cards: profile, interests, sponsored mentors, and the tier cards.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The profile is an allowlist: five fields, spelled as the base once spelled them, and their
 *   choices pinned word for word in this suite, because a stored row keeps those spellings. One
 *   rejected field rejects the whole save, and the audit row names which fields changed, never
 *   their values. Anything else keeps its line breaks, its limit counts what was typed and a
 *   longer text is refused, never cut, and its box shows what was typed, as the listing
 *   description's below.
 * - The interests card writes the six choices to the row, appends one dated line to the row's
 *   own history, mails the assigned manager (or every manager when none is assigned, through
 *   WPCPM_Institutions::notify_managers()), and is ceilinged at five a day per account, the
 *   same as the mentor-interest form.
 * - The sponsored-mentors card never names a student: only mentors and their counts, read
 *   through WPCPM_Mentors_Sync::sponsorship() so a mentor who is not Active is counted and not
 *   named. The one press to say "I would like to sponsor this mentor" goes to the same mailer
 *   and the same ceiling.
 * - The tier cards (1.112.0): while the launch switch is off WPCPM_Sponsor_Tier_Card prints
 *   nothing and its button leaves with tier-off, writing nothing. While it is on, the
 *   Sponsorship card reads the effective tier, its term, its funded line, what it includes and what
 *   the next tier adds. Its button files through WPCPM_Sponsor_Interests::file(), the road the
 *   interests form takes too, so the line, the mail and the log cannot drift apart, and the two
 *   share the interests card's ceiling. Events (Champion up) lists the events the tier includes,
 *   with one more line under the co-hosted event while the program has granted more for the
 *   running term (none for a number granted for another day),
 *   says what the planner under the list is for, and holds the planner (1.117.0): its form while
 *   a plan is allowed, the sponsor's event still to take place with Change and Cancel and the
 *   sentence in the form's place, the events that took place, read only, and no Plan it now
 *   anywhere. A sponsor whose tier no longer has events keeps the card while an event of its own
 *   is still to take place, and reads that event as no longer offered. The planner's presses are
 *   pinned in bin/test-sponsor-event-planner.php; here the event store's clock is the fixture's
 *   today, and its events are written the way the store writes them.
 *   The profile's description for the partner page is drawn and saved for every tier from
 *   Supporter up, the tiers the Partners page prints it for, behind the same switch. Its limit counts what was
 *   typed, through the text area cleaner's stand-in that writes entities as core's does, and a
 *   longer one is refused, never cut; its box shows what was typed, and an unedited post-back
 *   changes nothing. Its maxlength is the limit and three for each > the box draws as `&gt;`, so
 *   the browser never refuses what the save accepts. A description the cleaner would take words
 *   from, "Kids <12 free, adults >18 pay, and 10%cashback", is refused before anything is written,
 *   the box named, after the five fields in the form's order, and the card comes back with it as
 *   typed, once, to the person who typed it alone; a refusal for its length brings the typing back
 *   too, up to the box's room. With a space after each sign every word is saved, and one the row
 *   holds with such words, posted back as drawn, is not asked and keeps what the row holds. The
 *   wp_date() stand-in
 *   answers $GLOBALS['today'] (6 October 2026) for today's date, so every term below is a day
 *   counted from it. A Friend's line links to the Partners page once it is
 *   published (1.113.0), read through a WPCPM_Sponsors_Page stand-in whose page_url() answers
 *   $GLOBALS['sponsors_page_url'], '' until a check sets it.
 * - The Reports card (1.115.0) prints nothing while the launch switch is off, and nothing for a
 *   sponsor with no final report: a draft is the program's, not the sponsor's. Otherwise it lists
 *   the sponsor's final reports, the newest term first, each by its title with Open (the printed
 *   document, in a new tab) and, when a PDF is attached, PDF, in the classes the theme dresses.
 *   The real report store and files class read the posts, through a get_posts() stand-in that
 *   honors the store's sponsor clause, so another sponsor's report on the card is a failure
 *   here; a student's name planted in a report's narratives and in its file's name must not
 *   reach the card.
 * - The rows (the design the owner approved on 27 September 2026): the cards take noun titles
 *   without "Your" (Sponsorship, Offer more, Mentors you sponsor, Company profile and logo); the
 *   Sponsorship card and Mentors you sponsor are open whatever a flash says, Events while an event
 *   of the sponsor's is still to take place, and the rest fold until a flash names them. Each row
 *   says its state in one phrase inside its summary and, where the design gives it one, offers its
 *   action after the fold, a link to the card that names for a screen reader what it does: Plan an
 *   event while the planner would take a plan, Tell us, Edit, and Upload while the agreement's
 *   state asks for a file. A card a flash opened prints its action too, which the stylesheets hide
 *   while the card is open. The Reports card's finals() is public, the one list its row and the At
 *   a glance tile read, and so is the tier card's draws_events(), the Events card's own test for
 *   whether it is drawn, which the tile asks too. "Profile complete" counts the four details and
 *   leaves the optional "Anything else" out; an event the program has not approved yet waits for
 *   the program on the Events row, rather than counting who joined.
 * - Every handler claims through WPCPM_Sponsor_Roster::claim() before it reads or writes
 *   anything else, asserted by counting the calls in the source.
 * - WPCPM_Sponsors_Dashboard is a stub here, called by array callable exactly as the
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

$GLOBALS['opts']  = array( 'date_format' => 'F j, Y', 'time_format' => 'g:i a' );
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
 * The constructor's argument order (id, roles, name, email) is the Sponsors screen suite's own
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
 * The two outcomes `post()` tells apart, in the Sponsors screen suite's shape: `wp_die()` raises one, and here
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
// Recorded, so the tier card's init() can be read back: which callback each admin-post action reaches.
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['hooks'][ $h ] = $c; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
// The flash slashes what it writes for core's user meta, which unslashes it; this one keeps what it
// is handed, so the slash is the identity here (bin/test-flash.php holds the flash to core's).
function wp_slash( $v ) { return $v; }
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
	// The action checked, kept so a check can read which nonce a handler asked for.
	$GLOBALS['referer'] = $action;
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
// Today's `Y-m-d`, asked with no timestamp, is the fixture's pinned day when one is set (the
// tier card counts a term's days from it); every other call formats the timestamp it is given.
function wp_date( $format, $ts = null, $zone = null ) { return ( null === $ts && 'Y-m-d' === $format && isset( $GLOBALS['today'] ) ) ? $GLOBALS['today'] : gmdate( $format, (int) $ts ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function wp_nonce_url( $url, $a ) { return $url . '&_wpnonce=' . rawurlencode( $a ); }
function add_query_arg( $args, $url = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
// The event planner's clock (1.117.0): the site keeps UTC here, so a viewer with no zone of their
// own reads every time on it; the cache the store drops before a fresh read holds nothing here.
function wp_timezone_string() { return 'UTC'; }
function wp_timezone() { return new DateTimeZone( 'UTC' ); }
function wp_cache_delete( $key, $group = '' ) { return true; }
/** Enough of `WP_Post` for `post_date()`'s `instanceof` check, the fixture below, and the store. */
class WP_Post {
	public $ID = 0;
	public $post_date = '';
	public $post_type = '';
	public $post_status = '';
	public $post_title = '';
	public $post_name = '';
	public $post_author = 0;
	public $post_content = '';
	public function __construct( $id = 0, $post_date = '' ) {
		$this->ID        = (int) $id;
		$this->post_date = $post_date;
	}
}
// The agreement card's own two direct reads, keyed like the real functions: `false` (not `true`
// or an array) for a post nobody staged, and every value of one meta key for `$single = false`.
// Checked second: `$GLOBALS['posts']`/`$GLOBALS['pmeta']` is where the store (1.111.0) and
// stubs/posts.php's own writers keep a sponsor post, a different fixture from this card's one
// hand-built document, and the two never share an ID in practice.
function get_post( $p ) {
	if ( $p instanceof WP_Post ) { return $p; }
	if ( isset( $GLOBALS['posts'][ (int) $p ] ) ) { return $GLOBALS['posts'][ (int) $p ]; }
	return isset( $GLOBALS['agr_posts'][ (int) $p ] ) ? $GLOBALS['agr_posts'][ (int) $p ] : null;
}
function get_post_meta( $id, $k, $single = false ) {
	if ( isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ) ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ]; }
	$rows = isset( $GLOBALS['agr_pmeta'][ (int) $id ][ $k ] ) ? $GLOBALS['agr_pmeta'][ (int) $id ][ $k ] : array();
	return $single ? ( isset( $rows[0] ) ? $rows[0] : '' ) : $rows;
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
	// Every method reads $_POST directly, exactly as the real class does (includes/class-wpcpm-request.php):
	// the cards' own direct `isset( $_POST[...] )` presence checks (verbatim from the brief, matching
	// the codebase's established pattern of checking $_POST before reading it through this class) must
	// see the same field this class reads, which only holds if both read the one superglobal. Each
	// body is the real one: the one-line cleaner folds a line break into a space, and the text area
	// reader keeps every break as it was posted, CR LF included, and every line as it was written.
	public static function posted_text( $n, $f = '' ) { return isset( $_POST[ $n ] ) && is_scalar( $_POST[ $n ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $n ] ) ) ) : $f; }
	public static function posted_lines( $n, $f = '' ) { return isset( $_POST[ $n ] ) && is_scalar( $_POST[ $n ] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST[ $n ] ) ) ) : $f; }
	/** The real class's slug-shaped read of a posted field, `sanitize_key()` as it cleans it. */
	public static function posted_key( $n, $f = '' ) { return isset( $_POST[ $n ] ) ? sanitize_key( (string) $_POST[ $n ] ) : $f; }
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
	public static function site_name() { return 'WP Credits'; }
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
// The Partners page, whose address the Friend's line links to: '' (no published
// page, the real class's answer while the page is a draft) unless a check sets one.
class WPCPM_Sponsors_Page { public static function page_url() { return isset( $GLOBALS['sponsors_page_url'] ) ? $GLOBALS['sponsors_page_url'] : ''; } }
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
			'agreement-uploaded'  => array( 'success', 'Your signed agreement is uploaded. An Administrator reads it and you will get an email either way.' ),
			'agreement-withdrawn' => array( 'success', 'The agreement is withdrawn and its file is deleted. Upload another whenever you are ready.' ),
			'refused'             => array( 'error', 'That is not something your account can do here.' ),
		);
	}

	public static function summary( $record ) {
		return isset( $GLOBALS['summary'] ) ? $GLOBALS['summary'] : array(
			'state'        => self::SUMMARY_NONE,
			'kind'         => '',
			'accepted_at'  => '',
			'agreement_id' => 0,
			'pending_id'   => 0,
			'drive_url'    => '',
		);
	}

	public static function posts_for( $record ) {
		return isset( $GLOBALS['agr_fixture_posts'] ) ? $GLOBALS['agr_fixture_posts'] : array();
	}
}
/**
 * The shared stub's get_posts() (bin/stubs/posts.php), line for line, and the two arguments the
 * sponsor report's store adds that the shared one does not read: a `meta_query` of plain equality
 * clauses, and `posts_per_page`. Declared before the stub loads, so the stub's own stands down.
 * The Reports card (1.115.0) lists one sponsor's reports, and a query that dropped the sponsor
 * clause would put every sponsor's reports on every sponsor's card, the one fault the card's
 * section below exists to catch.
 *
 * @param array $args The query.
 * @return array Posts, or IDs for `fields` => `ids`.
 */
function get_posts( $args = array() ) {
	$GLOBALS['last_get_posts'] = $args;

	$out = array();

	foreach ( (array) $GLOBALS['posts'] as $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}

		if ( isset( $args['post_status'] ) ) {
			if ( 'any' === $args['post_status'] ) {
				if ( in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
					continue;
				}
			} elseif ( ! in_array( $post->post_status, (array) $args['post_status'], true ) ) {
				continue;
			}
		}

		if ( isset( $args['meta_key'] ) ) {
			$have = get_post_meta( $post->ID, $args['meta_key'], true );

			if ( '' === $have || (string) $have !== (string) ( isset( $args['meta_value'] ) ? $args['meta_value'] : '' ) ) {
				continue;
			}
		}

		foreach ( isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array() as $clause ) {
			if ( is_array( $clause ) && isset( $clause['key'] ) && (string) get_post_meta( $post->ID, $clause['key'], true ) !== (string) $clause['value'] ) {
				continue 2;
			}
		}

		$out[] = $post;
	}

	usort(
		$out,
		static function ( $a, $b ) {
			return $a->ID - $b->ID;
		}
	);

	$limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : ( isset( $args['numberposts'] ) ? (int) $args['numberposts'] : -1 );

	if ( $limit > 0 ) {
		$out = array_slice( $out, 0, $limit );
	}

	if ( 'ids' === ( isset( $args['fields'] ) ? $args['fields'] : '' ) ) {
		$out = array_map(
			static function ( $post ) {
				return $post->ID;
			},
			$out
		);
	}

	return $out;
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/posts.php';
require_once __DIR__ . '/stubs/cleaners.php';
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
// The shell every card is drawn in.
require_once __DIR__ . '/../includes/class-wpcpm-dashboard-card.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-profile.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-mentors.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tier-card.php';
// The Events card's planner (1.117.0) and what it reads: the zone readers, the calls' range
// formatter, the report's period (a term's first day) and the event store. A refused plan or change
// keeps what was typed on a channel of the flash stand-in above, which nothing here sets. The
// planner's own file is loaded only when it is there, so that before it exists the card's checks
// fail as checks.
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-availability.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event.php';
if ( file_exists( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-planner.php' ) ) {
	require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-planner.php';
}

// The fixture: one sponsor with a member (user 5), a manager (user 1), three mentors.
// 1.111.0: the manager is a site user id now, not a Team Members record, so the assignment
// below is the numeric form of the same "user 1 is the manager" the comment always meant.
$A = 'recSPONSOR0000001'; $M1 = 'recMENTOR00000001'; $M2 = 'recMENTOR00000002'; $M3 = 'recMENTOR00000003'; $M4 = 'recMENTOR00000004';
WPCPM_Sponsors_Index::write( array( $A => array( 'name' => 'Mango Example ', 'status' => 'Approved', 'website' => 'https://plugins.mango-example.test/', 'contact_person' => 'Rep One', 'contact_email' => 'maciej@a8c.com', 'product_type' => 'Hosting', 'offer' => 'One year free', 'instructions' => 'Use the code.', 'more_info' => '', 'support' => array( 'Sponsor tools or services' ), 'interests' => '2026-09-01 by Rep One: Sponsor tools or services', 'manager' => '1', 'mentors' => array( $M1, $M2, $M4 ) ) ), time() );
$GLOBALS['settings'] = array();
$GLOBALS['users'] = array( 1 => new WP_User( 1, array( 'administrator' ), 'Manager', 'maciej@a8c.com' ), 5 => new WP_User( 5, array( 'wpcpm_sponsor' ), 'Rep One', 'maciej@a8c.com' ), 42 => new WP_User( 42, array( 'wpcpm_mentor' ), 'Ines', 'maciej@a8c.com' ) );
$GLOBALS['manage'] = array( 1 );
$GLOBALS['umeta'][5] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $A, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['sponsorship'] = array(
	$M1 => array( 'name' => 'Ines Example', 'profile' => 'https://profiles.wordpress.org/ines-example/', 'status' => 'Active', 'user_id' => 42, 'sponsored' => true, 'wants' => false, 'expertise' => array( 'Core' ) ),
	$M2 => array( 'name' => 'Sam Example', 'profile' => 'https://profiles.wordpress.org/sam-example/', 'status' => 'Active', 'user_id' => 0, 'sponsored' => true, 'wants' => false, 'expertise' => array() ),
	$M3 => array( 'name' => 'Ana Looking', 'profile' => 'https://profiles.wordpress.org/mentor-one/', 'status' => 'Active', 'user_id' => 0, 'sponsored' => false, 'wants' => true, 'expertise' => array( 'Polyglots', 'Community' ) ),
);
$GLOBALS['mentees'][42] = array( array( 'name' => 'Student One', 'is_past' => false ), array( 'name' => 'Student Two', 'is_past' => false ), array( 'name' => 'Old Student', 'is_past' => true ) );
$GLOBALS['uid'] = 5; $GLOBALS['nonce_ok'] = true; $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['buckets'] = array();
$GLOBALS['today'] = '2026-10-06';
$context = array( 'can_manage' => false, 'open' => '', 'viewer' => $GLOBALS['users'][5] );
// The event store reads "now" through its own seam (1.117.0): noon on the fixture's today, so an
// event the card draws as still to take place, or as taken place, stays that way however old the
// suite grows.
WPCPM_Sponsor_Event::for_tests( static function () { return (int) strtotime( $GLOBALS['today'] . ' 12:00:00 UTC' ); } );

// $_POST itself, not a side channel: both new cards check `isset( $_POST[...] )` directly
// before reading a field (the established pattern for a presence check, matching how the real
// WPCPM_Request::posted_text() reads $_POST directly too), so a helper that populated anything
// else would leave that check always false.
function post( array $fields, $action ) { $_POST = $fields; $GLOBALS['left'] = null; try { call_user_func( $action ); } catch ( WPCPM_Test_Redirect $e ) { return $GLOBALS['left']; } catch ( WPCPM_Test_Die $e ) { return array( 'die', $e->getMessage() ); } return array( 'fell-through' ); }
function card( $class, $record, $context ) { ob_start(); call_user_func( array( $class, 'render' ), $record, $context ); return ob_get_clean(); }
/** A sponsor's tier, the last day of its term and its funded line, through the store's own patch(). */
function set_tier( $record, $tier, $until = '', $funded = '' ) { WPCPM_Sponsors_Index::patch( $record, array( 'tier' => $tier, 'tier_until' => $until, 'funded_line' => $funded ) ); }
/** The `Y-m-d` day this many days after the fixture's today; a negative count is a day already past. */
function day_from_today( $days ) { return gmdate( 'Y-m-d', strtotime( $GLOBALS['today'] . ' 00:00:00 UTC' ) + (int) $days * DAY_IN_SECONDS ); }
/** The markup between the first `$open` and the next `$close` after it; '' when `$open` is absent. */
function between( $html, $open, $close ) { $at = strpos( $html, $open ); if ( false === $at ) { return ''; } $rest = substr( $html, $at + strlen( $open ) ); $end = strpos( $rest, $close ); return false === $end ? $rest : substr( $rest, 0, $end ); }
/** Whether both are in the text and the first comes first; false when either is missing. */
function before( $text, $first, $second ) { $a = strpos( (string) $text, $first ); $b = strpos( (string) $text, $second ); return is_int( $a ) && is_int( $b ) && $a < $b; }
/** What a profile text box shows, by its name: the text a browser reads from its markup, entities decoded once; null when it is not drawn. */
function text_box( $html, $name ) { return preg_match( '#<textarea id="[^"]*" name="' . preg_quote( $name, '#' ) . '"[^>]*>(.*?)</textarea>#s', (string) $html, $m ) ? htmlspecialchars_decode( $m[1], ENT_QUOTES ) : null; }
/** What a browser posts from a text box showing this text: each line break, however it was drawn, as CR LF. */
function browser_lines( $shown ) { return str_replace( "\n", "\r\n", str_replace( array( "\r\n", "\r" ), "\n", (string) $shown ) ); }
/** A profile text box's maxlength, by its name; 0 when it is not drawn. */
function box_maxlength( $html, $name ) { return preg_match( '#<textarea id="[^"]*" name="' . preg_quote( $name, '#' ) . '"[^>]*maxlength="(\d+)"#', (string) $html, $m ) ? (int) $m[1] : 0; }
/** What the profile's description box shows: the text a browser reads from its markup, the line feed right after the opening tag dropped as the parser drops it, entities decoded once; null when it is not drawn. */
function description_box( $html ) { return preg_match( '#<textarea id="wpcpm-profile-description" name="wpcpm_description"[^>]*>(.*?)</textarea>#s', (string) $html, $m ) ? htmlspecialchars_decode( (string) preg_replace( '/^\n/', '', $m[1] ), ENT_QUOTES ) : null; }
/**
 * What a card's row says: the phrase in its summary, as printed, or null when the summary holds
 * none or the card is not drawn.
 *
 * @param string $html The markup.
 * @param string $key  The card's key.
 * @return string|null
 */
function row_state( $html, $key ) {
	$summary = between( $html, '<details id="wpcpm-sponsor-' . $key . '"', '</summary>' );
	return preg_match( '#<span class="wpcpm-sponsor__state">(.*?)</span>#s', $summary, $m ) ? $m[1] : null;
}
/**
 * A card's action, as printed after its fold and before its section ends, or null when it has none.
 *
 * @param string $html The markup.
 * @param string $key  The card's key.
 * @return string|null
 */
function row_action( $html, $key ) {
	$card  = between( $html, '<details id="wpcpm-sponsor-' . $key . '"', '</section>' );
	$after = substr( $card, (int) strrpos( $card, '</details>' ) );
	return preg_match( '#^</details><p class="wpcpm-sponsor__row-action">(.*?)</p>$#s', $after, $m ) ? $m[1] : null;
}
/**
 * Whether a card is drawn open.
 *
 * @param string $html The markup.
 * @param string $key  The card's key.
 * @return bool
 */
function is_open( $html, $key ) { return false !== strpos( $html, '<details id="wpcpm-sponsor-' . $key . '" class="wpcpm-group wpcpm-group__disclosure" open>' ); }
/**
 * A sponsor event written the way WPCPM_Sponsor_Event::plan() writes one (bin/test-sponsor-events.php
 * proves that layout), under an ID far above the store's own posts, with the people on it as one list
 * of user IDs, which is how this file's get_post_meta() answers a read of every row.
 *
 * @param string $record  Sponsor key.
 * @param string $state   `planned`, `approved` or `cancelled`.
 * @param int    $start   A UTC timestamp.
 * @param int    $minutes How long it runs.
 * @param string $title   The title.
 * @param int[]  $people  The user IDs on it.
 * @return int The event's ID.
 */
function seed_sevent( $record, $state, $start, $minutes, $title, array $people = array() ) {
	static $next = 9000;
	$id                      = $next++;
	$post                    = new WP_Post( $id );
	$post->post_type         = 'wpcpm_sponsor_event';
	$post->post_status       = 'private';
	$post->post_title        = $title;
	$post->post_content      = 'What the event covers.';
	$GLOBALS['posts'][ $id ] = $post;
	$GLOBALS['pmeta'][ $id ] = array(
		'_wpcpm_sevent_sponsor'  => $record,
		'_wpcpm_sevent_kind'     => 'co_hosted',
		'_wpcpm_sevent_start'    => (int) $start,
		'_wpcpm_sevent_end'      => (int) $start + (int) $minutes * 60,
		'_wpcpm_sevent_zone'     => 'UTC',
		'_wpcpm_sevent_minutes'  => (int) $minutes,
		'_wpcpm_sevent_places'   => 0,
		'_wpcpm_sevent_link'     => 'https://call.example/room',
		'_wpcpm_sevent_state'    => $state,
		'_wpcpm_sevent_revision' => 0,
		'_wpcpm_sevent_person'   => $people,
	);
	return $id;
}

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
ck( 'the five fields, and no offer field', array_keys( WPCPM_Sponsor_Profile::FIELDS ), array( 'website', 'contact_person', 'contact_email', 'product_type', 'anything' ) );
ck( 'each spelled as the base once spelled it', array_column( WPCPM_Sponsor_Profile::FIELDS, 'name' ), array( 'Website', 'Contact Person Full Name', 'Contact Email', 'Type of product', "Anything else you'd like to share." ) );
// Pinned word for word here since the Sponsors table's fixture went with the sync (1.111.0): a
// stored row keeps these exact spellings, so one changed here would stop matching its own data.
ck( 'the product choices are the three words the base offered, byte for byte', WPCPM_Sponsor_Profile::CHOICES['Type of product'], array( 'Hosting', 'Plugin', 'Service' ) );
ck( 'and the support choices are the six it offered, in its order', WPCPM_Sponsor_Interests::CHOICES, array( 'Provide financial support (for program costs)', 'Sponsor a member of the WP Credits admin team', 'Sponsor a mentor or multiple mentors', 'Sponsor a scholarship for students to attend flagship events', 'Sponsor tools or services', 'Other (please specify)' ) );
ck( 'a URL without a scheme is completed', WPCPM_Sponsor_Profile::clean( 'website', 'plugins.mango-example.test' ), array( 'ok' => true, 'value' => 'https://plugins.mango-example.test' ) );
ck( 'a URL with a user is refused', WPCPM_Sponsor_Profile::clean( 'website', 'https://name@host.test' )['ok'], false );
ck( 'and a URL with a password too', WPCPM_Sponsor_Profile::clean( 'website', 'https://user:pw@host.test/x' )['ok'], false );
ck( 'a choice spelled another way is refused', WPCPM_Sponsor_Profile::clean( 'product_type', 'hosting' )['ok'], false );
ck( 'an empty choice clears the cell', WPCPM_Sponsor_Profile::clean( 'product_type', '' ), array( 'ok' => true, 'value' => null ) );
ck( 'text is kept to MAX_TEXT as typed: 4,000 characters are kept whole, and one more is refused, never cut', array( WPCPM_Sponsor_Profile::clean( 'anything', str_repeat( 'x', 4000 ) ), WPCPM_Sponsor_Profile::clean( 'anything', str_repeat( 'x', 4001 ) )['ok'] ), array( array( 'ok' => true, 'value' => str_repeat( 'x', 4000 ) ), false ) );
ck( 'a text over MAX_TEXT is refused, with how far over it is, not cut', WPCPM_Sponsor_Profile::clean( 'anything', str_repeat( 'x', 5000 ) ), array( 'ok' => false, 'value' => str_repeat( 'x', 5000 ), 'over' => 1000 ) );
ck( 'an address that is not one is refused', WPCPM_Sponsor_Profile::clean( 'contact_email', 'not-an-address' )['ok'], false );
ck( 'a field outside the allowlist is refused', WPCPM_Sponsor_Profile::clean( 'status', 'Approved' )['ok'], false );
ck( 'the offer fields are outside it too, now that the primary offer owns them', array( WPCPM_Sponsor_Profile::clean( 'offer', 'x' )['ok'], WPCPM_Sponsor_Profile::clean( 'instructions', 'x' )['ok'], WPCPM_Sponsor_Profile::clean( 'more_info', 'x' )['ok'] ), array( false, false, false ) );

echo "\n=== Profile: saving ===\n";
// 1.111.0: offer, instructions and more_info left the row's shape and this card's FIELDS alike
// (the primary offer is the record now, and these three were only ever Airtable's copy of it),
// so a realistic resubmission never posts them at all, and the card writes to the store, never
// to Airtable - every assertion below reads WPCPM_Sponsors_Index::row() back, not a PATCH.
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://plugins.mango-example.test/', 'wpcpm_contact_person' => 'Rep One', 'wpcpm_contact_email' => 'maciej@a8c.com', 'wpcpm_product_type' => 'Plugin', 'wpcpm_anything' => '' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a member saves, and lands on the profile card', $r, array( 'profile-saved', 'profile', $A ) );
ck( 'a changed field is written to the store', WPCPM_Sponsors_Index::row( $A )['product_type'], 'Plugin' );
ck( 'and audited by name, never by value', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data']['fields'], end( $GLOBALS['audit'] )['ground'] ), array( 'profile_saved', array( 'product_type' ), 'member' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://plugins.mango-example.test/', 'wpcpm_contact_person' => 'Rep One', 'wpcpm_contact_email' => 'maciej@a8c.com', 'wpcpm_product_type' => 'Plugin' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'nothing changed is said', $r[0], 'profile-unchanged' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => 'We can offer licences.' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'the free-text field saves, to the store', array( $r[0], WPCPM_Sponsors_Index::row( $A )['anything'] ), array( 'profile-saved', 'We can offer licences.' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => 'We can offer licences.' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'and is remembered, so the same text again is no change', $r[0], 'profile-unchanged' );
// product_type rides along so the second element below proves something: it is a stored field,
// posted with a value that differs from what is on file and still a valid choice (CHOICES is
// Hosting, Plugin, Service - 'Service' against the 'Plugin' already on record).
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://x@y', 'wpcpm_product_type' => 'Service' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'one rejected field rejects the whole save', array( $r[0], WPCPM_Sponsors_Index::row( $A )['product_type'] ), array( 'profile-rejected', 'Plugin' ) );
$GLOBALS['uid'] = 9; $GLOBALS['users'][9] = new WP_User( 9, array( 'subscriber' ), 'Stranger', 'maciej@a8c.com' );
$before_website = WPCPM_Sponsors_Index::row( $A )['website'];
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://hijacked.test/' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a stranger is refused with the one message, metered, and nothing written', array( $r[0], $GLOBALS['buckets']['sponsor-refused:9'], WPCPM_Sponsors_Index::row( $A )['website'] ), array( 'refused', 1, $before_website ) );
$GLOBALS['uid'] = 5;
$html = card( 'WPCPM_Sponsor_Profile', $A, $context );
ck( 'the card is the canonical disclosure with the five fields prefilled', substr_count( $html, 'name="wpcpm_' ) >= 6 && false !== strpos( $html, 'id="wpcpm-sponsor-profile"' ) && false !== strpos( $html, 'wpcpm-group__disclosure' ) && false !== strpos( $html, 'value="Plugin"' ), true );
ck( 'closed until a flash names it', strpos( $html, '<details' ) !== false && strpos( $html, ' open>' ) === false, true );
$profile_opened = card( 'WPCPM_Sponsor_Profile', $A, array_merge( $context, array( 'open' => 'profile' ) ) );
ck( 'and open when one does, its form on screen and its Edit still after the fold, which the stylesheets hide while the card is open', array( strpos( $profile_opened, ' open>' ) !== false, 1 === substr_count( $profile_opened, 'wpcpm-sponsor__row-action' ), false !== strpos( $profile_opened, 'name="action" value="wpcpm_sponsor_profile_save"' ) ), array( true, true, true ) );
// The row a folded card shows (the design the owner approved on 27 September 2026): the title
// without "Your", one phrase saying how much of the profile is filled in, and Edit after the fold,
// a link to the card that the page's opener opens. What the card keeps is said once, as the card's
// first line, since the group's lead no longer says it: what a sponsor saves here is the program's
// record of the company.
$profile_edit = '</div></details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-profile">Edit<span class="screen-reader-text"> the company profile and logo</span></a></p></section>';
ck( 'the row: Company profile and logo, the profile complete, and Edit after the fold, linking to the card; the card\'s first line says the program keeps what is saved', array(
	false !== strpos( $html, '<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Company profile and logo</h3><span class="wpcpm-sponsor__state">Profile complete</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>' ),
	substr( $html, -strlen( $profile_edit ) ) === $profile_edit,
	false !== strpos( $html, '<div class="wpcpm-group__body"><p class="wpcpm-student__note">Keep the profile current: what you save here is kept by the program.</p><div class="wpcpm-profile__cols">' ),
	strpos( $html, 'Your company profile and logo' ),
), array( true, true, true, false ) );
// filled() counts the four details the row asks for that hold something: the website, the contact
// person and address, and the type of product. A value of spaces holds nothing; "Anything else you'd
// like to share." is optional and not counted, so a sponsor with nothing else to share has a complete
// profile; and the description, a tier's benefit and none of the five fields, is not counted either.
$filled = is_callable( array( 'WPCPM_Sponsor_Profile', 'filled' ) ) ? array( 'WPCPM_Sponsor_Profile', 'filled' ) : static function () { return 'no filled()'; };
ck( 'filled() counts the four details that hold something, a value of spaces as nothing, and neither the optional Anything else nor the description', array(
	call_user_func( $filled, WPCPM_Sponsors_Index::row( $A ) ),
	call_user_func( $filled, array( 'website' => '  ', 'contact_person' => 'Rep One', 'contact_email' => '', 'product_type' => 'Plugin', 'anything' => 'Not one of the four.', 'description' => 'Not one of the five.' ) ),
	call_user_func( $filled, array() ),
), array( 4, 2, 0 ) );
WPCPM_Sponsors_Index::patch( $A, array( 'product_type' => '', 'contact_person' => '' ) );
$partial = card( 'WPCPM_Sponsor_Profile', $A, $context );
// The four details filled again and nothing else to share: the optional "Anything else" left empty.
WPCPM_Sponsors_Index::patch( $A, array( 'product_type' => 'Plugin', 'contact_person' => 'Rep One', 'anything' => '' ) );
$nothing_else = card( 'WPCPM_Sponsor_Profile', $A, $context );
WPCPM_Sponsors_Index::patch( $A, array( 'anything' => 'We can offer licences.' ) );
ck( 'a profile with two of its four details empty says how many are filled, and still offers Edit; one with nothing else to share is complete once the four are', array(
	false !== strpos( $partial, '<h3 class="wpcpm-group__title">Company profile and logo</h3><span class="wpcpm-sponsor__state">2 of 4 fields filled</span>' ),
	substr( $partial, -strlen( $profile_edit ) ) === $profile_edit,
	false !== strpos( $nothing_else, '<h3 class="wpcpm-group__title">Company profile and logo</h3><span class="wpcpm-sponsor__state">Profile complete</span>' ),
), array( true, true, true ) );
ck( 'the form says what changing the address does, and carries the guard', false !== strpos( $html, 'changes the address the program holds' ) && false !== strpos( $html, 'data-wpcpm-once' ), true );

echo "\n=== Profile: the note that replaces the three offer fields ===\n";
ck( 'with no offer yet, the card says nothing about one', false !== strpos( card( 'WPCPM_Sponsor_Profile', $A, $context ), 'edited on the Offers card' ), false );
$GLOBALS['offers'][ $A ] = array( 900 => array( 'id' => 900, 'primary' => true ) );
$with_offer = card( 'WPCPM_Sponsor_Profile', $A, $context );
ck( 'with any offer, the card points to the Offers card instead', false !== strpos( $with_offer, 'text, instructions and link are edited on the Offers card.' ), true );
ck( 'and the three fields are not inputs here, and never were', array( strpos( $with_offer, 'name="wpcpm_offer"' ), strpos( $with_offer, 'name="wpcpm_instructions"' ), strpos( $with_offer, 'name="wpcpm_more_info"' ) ), array( false, false, false ) );
$before_row = WPCPM_Sponsors_Index::row( $A );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 'Posted anyway' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a posted offer field is simply not read: there is no field left to ignore it from', array( $r[0], WPCPM_Sponsors_Index::row( $A ) ), array( 'profile-unchanged', $before_row ) );
unset( $GLOBALS['offers'] );

echo "\n=== Profile: the description for the partner page (1.112.0) ===\n";
// A Supporter's alone, behind the launch switch. The box and the save both read the effective
// tier, so a term that has ended reads as a Friend here too, and the box goes with it.
$GLOBALS['settings'] = array();
WPCPM_Sponsors_Index::patch( $A, array( 'description' => "Mango & friends\nhost students" ) );
set_tier( $A, 'supporter' );
$desc_off = card( 'WPCPM_Sponsor_Profile', $A, $context );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
$desc_on = card( 'WPCPM_Sponsor_Profile', $A, $context );
set_tier( $A, '' );
$desc_mentor = card( 'WPCPM_Sponsor_Profile', $A, $context );
set_tier( $A, 'supporter', day_from_today( -1 ) );
$desc_lapsed = card( 'WPCPM_Sponsor_Profile', $A, $context );
ck( 'the description box is drawn for a Supporter while the switch is on, and for nobody while it is off or below Supporter', array(
	false !== strpos( $desc_on, '<p class="wpcpm-sponsor__field"><label for="wpcpm-profile-description">Description for the partner page</label><textarea id="wpcpm-profile-description" name="wpcpm_description" rows="3" maxlength="300">Mango &amp; friends' . "\n" . 'host students</textarea><span class="wpcpm-student__note">A sentence or two about your company, shown with your logo where the program lists its sponsors. 300 characters at most.</span></p>' ),
	before( $desc_on, 'name="wpcpm_anything"', 'name="wpcpm_description"' ) && before( $desc_on, 'name="wpcpm_description"', '>Save profile</button>' ),
	strpos( $desc_off, 'name="wpcpm_description"' ),
	strpos( $desc_mentor, 'name="wpcpm_description"' ),
	strpos( $desc_lapsed, 'name="wpcpm_description"' ),
), array( true, true, false, false, false ) );
set_tier( $A, 'supporter' );
WPCPM_Sponsors_Index::patch( $A, array( 'description' => '' ) );
// Two lines, the second filled to the limit with a two-byte letter: counted in bytes it would be
// far over three hundred. One letter more is over the limit, and is refused, the save as a whole:
// nothing is cut, nothing written and nothing audited.
$desc_lead   = "Mango Example hosts the program's sites.\nWe fund cafe visits for students: ";
$desc_posted = $desc_lead . str_repeat( "\u{e9}", 300 - mb_strlen( $desc_lead ) );
$desc_audits = count( $GLOBALS['audit'] );
$r_long      = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => $desc_posted . "\u{e9}" ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$desc_long   = array( $r_long, WPCPM_Sponsors_Index::row( $A )['description'], count( $GLOBALS['audit'] ) - $desc_audits );
$r           = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => $desc_posted ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$desc_saved  = WPCPM_Sponsors_Index::row( $A )['description'];
ck( 'a description of 300 characters is saved whole, kept with its line breaks, and audited as description; one character more is refused and nothing is written', array(
	$desc_long,
	$r,
	$desc_saved === $desc_posted,
	mb_strlen( $desc_saved ),
	substr_count( $desc_saved, "\n" ),
	end( $GLOBALS['audit'] )['data']['fields'],
	end( $GLOBALS['audit'] )['message'],
), array( array( array( 'profile-too-long', 'profile', $A ), '', 0 ), array( 'profile-saved', 'profile', $A ), true, 300, 1, array( 'description' ), 'Profile fields changed: description.' ) );
$GLOBALS['settings'] = array();
$r_off               = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => 'Posted while the switch is off.' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
set_tier( $A, '' );
$r_mentor = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => 'Posted below Supporter.' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a description posted while the box is not shown is ignored', array( $r_off[0], $r_mentor[0], WPCPM_Sponsors_Index::row( $A )['description'] ), array( 'profile-unchanged', 'profile-unchanged', $desc_saved ) );
// A browser counts each line break in the box as one character against maxlength but sends it as
// CRLF: 298 characters, a break and one more fill the box and arrive as 301. They are stored
// whole, the break as one LF. One character more is over the limit, the break still counted once,
// and is refused, the stored description left as it was.
set_tier( $A, 'supporter' );
$full       = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => str_repeat( 'a', 298 ) . "\r\n" . 'z' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$full_saved = WPCPM_Sponsors_Index::row( $A )['description'];
$over       = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => str_repeat( 'b', 299 ) . "\r\n" . 'z' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$over_saved = WPCPM_Sponsors_Index::row( $A )['description'];
ck( 'a description\'s CRLF line breaks are stored as LF and the 300-character limit counts them once', array(
	array( $full[0], $full_saved === str_repeat( 'a', 298 ) . "\n" . 'z', mb_strlen( $full_saved ) ),
	array( $over[0], $over_saved === $full_saved ),
), array( array( 'profile-saved', true, 300 ), array( 'profile-too-long', true ) ) );
// What a sponsor types is what the box shows. Core's cleaner writes a "<" that opens no tag as
// `&lt;`, and the quote marks and the ampersand after it as entities, so "Kids < 12 learn
// "blocks" & themes." is stored longer than it was typed: the limit counts what was typed, and the
// box reads the entities back. Posted back unedited, as a browser posts the box, it changes
// nothing. The same text one typed character longer is refused, not cut.
WPCPM_Sponsors_Index::patch( $A, array( 'description' => '' ) );
$kids_typed  = 'Kids < 12 learn "blocks" & themes.' . "\n" . "Mango Example's mentors host them every week, online and at the community center, and help each student build a first site, a first block theme and a first plugin, then share it at a WordPress meetup near them, with the program's own mentors and their own teachers.";
$kids_posted = str_replace( "\n", "\r\n", $kids_typed );
$kids_r      = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => $kids_posted ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$kids_saved  = WPCPM_Sponsors_Index::row( $A )['description'];
$kids_box    = description_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$kids_audits = count( $GLOBALS['audit'] );
$kids_again  = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => str_replace( "\n", "\r\n", (string) $kids_box ) ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$kids_after  = array( WPCPM_Sponsors_Index::row( $A )['description'] === $kids_saved, count( $GLOBALS['audit'] ) - $kids_audits );
$kids_long   = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => $kids_posted . '!' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a 300-character description holding Kids < 12 learn "blocks" & themes. keeps every character, its box shows it as typed, an unedited post-back changes nothing, and one typed character more is refused', array(
	mb_strlen( $kids_typed ),
	$kids_r,
	0 === strpos( $kids_saved, 'Kids &lt; 12 learn &quot;blocks&quot; &amp; themes.' . "\n" . 'Mango Example&#039;s mentors' ),
	mb_strlen( $kids_saved ) > 300,
	wp_specialchars_decode( $kids_saved, ENT_QUOTES ) === $kids_typed,
	$kids_box === $kids_typed,
	$kids_again,
	$kids_after,
	$kids_long,
	WPCPM_Sponsors_Index::row( $A )['description'] === $kids_saved,
), array( 300, array( 'profile-saved', 'profile', $A ), true, true, true, true, array( 'profile-unchanged', 'profile', $A ), array( true, 0 ), array( 'profile-too-long', 'profile', $A ), true ) );
ck( 'the refusal names the box and its limit, and says nothing was saved', WPCPM_Sponsor_Profile::messages()['profile-too-long'] ?? null, array( 'error', 'The description for the partner page is longer than 300 characters, so nothing was saved.' ) );
// The page the refusal for its length lands on draws the typing it kept, once.
card( 'WPCPM_Sponsor_Profile', $A, $context );
// The cleaner takes a "<" with the words up to a ">" for a tag, so in "We <3 our <b>team</b> > all"
// it takes the two tags out and writes the "<" of the "<3" as `&lt;`. Such a typing is refused now
// (below), and the row holds it as a release before this one stored it. The box shows the ">"
// after the "<3" as `&gt;`, which the cleaner leaves as it is, so the post-back is no tag to it:
// posted back unedited, the words are kept and nothing changes. A ">" with nothing tag-like before
// it, and a "<" a space follows, are kept as typed and drawn as typed.
WPCPM_Sponsors_Index::patch( $A, array( 'description' => sanitize_textarea_field( 'We <3 our <b>team</b> > all' ) ) );
$tag_saved  = WPCPM_Sponsors_Index::row( $A )['description'];
$tag_box    = description_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$tag_audits = count( $GLOBALS['audit'] );
$tag_again  = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => (string) $tag_box ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$tag_after  = array( WPCPM_Sponsors_Index::row( $A )['description'] === $tag_saved, count( $GLOBALS['audit'] ) - $tag_audits );
$raw_r      = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => "Use 2 < 3 then 5 > 4 in the demo.\r\nAges 8 & up." ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$raw_saved  = WPCPM_Sponsors_Index::row( $A )['description'];
$raw_box    = description_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$raw_again  = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => str_replace( "\n", "\r\n", (string) $raw_box ) ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'words the cleaner took a tag out of, as a release before this one stored them, keep the rest through an unedited post-back, and a "<" a space follows is stored and drawn as typed', array(
	array( $tag_saved, $tag_box, $tag_again[0], $tag_after ),
	array( $raw_r[0], $raw_saved, $raw_box, $raw_again[0], WPCPM_Sponsors_Index::row( $A )['description'] === $raw_saved ),
), array(
	array( 'We &lt;3 our team > all', 'We <3 our team &gt; all', 'profile-unchanged', array( true, 0 ) ),
	array( 'profile-saved', "Use 2 < 3 then 5 > 4 in the demo.\nAges 8 & up.", "Use 2 < 3 then 5 > 4 in the demo.\nAges 8 & up.", 'profile-unchanged', true ),
) );
// A ">" drawn as `&gt;` is four characters in the box and one to the limit. A description stored
// from 300 characters typed, its tags taken out by the cleaner as a release before this one did and
// three ">" held after the "<3", is counted 293 and drawn at 302: the box's maxlength is 300 and
// three for each, so the browser never refuses what the save accepts. With nothing held the box
// keeps its 300.
$held_typed  = str_pad( 'We <3 <b>WordPress</b>: install -> configure -> publish -> share ', 300, 'x' );
WPCPM_Sponsors_Index::patch( $A, array( 'description' => sanitize_textarea_field( $held_typed ) ) );
$held_html   = card( 'WPCPM_Sponsor_Profile', $A, $context );
$held_box    = description_box( $held_html );
$held_max    = preg_match( '#<textarea id="wpcpm-profile-description" name="wpcpm_description" rows="3" maxlength="(\d+)">#', $held_html, $held_m ) ? (int) $held_m[1] : 0;
$held_again  = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => (string) $held_box ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$held_count  = WPCPM_Sponsor_Event::typed_length( WPCPM_Sponsors_Index::row( $A )['description'] );
WPCPM_Sponsors_Index::patch( $A, array( 'description' => 'Nothing held here.' ) );
$plain_max   = preg_match( '#<textarea id="wpcpm-profile-description" name="wpcpm_description" rows="3" maxlength="(\d+)">#', card( 'WPCPM_Sponsor_Profile', $A, $context ), $plain_m ) ? (int) $plain_m[1] : 0;
ck( 'a description box that draws a ">" as `&gt;` raises its maxlength by three for each: stored from 300 characters typed, counted 293 and drawn at 302, it has a maxlength of 309, and posts back unchanged; with nothing held the box keeps 300', array(
	mb_strlen( $held_typed ),
	$held_count,
	mb_strlen( (string) $held_box ),
	$held_max,
	$held_again[0],
	$plain_max,
), array( 300, 293, 302, 309, 'profile-unchanged', 300 ) );
// Stored values are not rewritten: what an earlier release stored is the same cleaned text, and
// for a description typed with a "<" it was cut to 300 characters after the cleaner. Its box reads
// it back to the words it kept, and an unedited post-back changes nothing.
$kids_cut = mb_substr( sanitize_textarea_field( $kids_typed ), 0, 300 );
WPCPM_Sponsors_Index::patch( $A, array( 'description' => $kids_cut ) );
$cut_box    = description_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$cut_audits = count( $GLOBALS['audit'] );
$cut_again  = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => str_replace( "\n", "\r\n", (string) $cut_box ) ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a description an earlier release cut after the cleaner is drawn read back to the words it kept, and posts back unchanged', array(
	$cut_box === wp_specialchars_decode( $kids_cut, ENT_QUOTES ),
	0 === strpos( $kids_typed, (string) $cut_box ) && mb_strlen( (string) $cut_box ) < 300,
	$cut_again,
	WPCPM_Sponsors_Index::row( $A )['description'] === $kids_cut,
	count( $GLOBALS['audit'] ) - $cut_audits,
), array( true, true, array( 'profile-unchanged', 'profile', $A ), true, 0 ) );
WPCPM_Sponsors_Index::patch( $A, array( 'description' => $full_saved ) );
// The Partners page prints a description under every tier from Supporter up, in one format
// (1.123.18), so the box is asked of each of them, with what the row holds in it. A Friend is
// listed by name alone: its box is not drawn, a description posted by hand is ignored, and one
// saved before stays on the row until the sponsor is a Supporter or above again.
WPCPM_Sponsors_Index::patch( $A, array( 'description' => 'Saved while a Supporter.' ) );
$desc_higher = array();
foreach ( array( 'backer', 'champion', 'partner' ) as $desc_tier ) {
	set_tier( $A, $desc_tier );
	$desc_card                 = card( 'WPCPM_Sponsor_Profile', $A, $context );
	$desc_higher[ $desc_tier ] = array(
		false !== strpos( $desc_card, 'name="wpcpm_description"' ),
		false !== strpos( $desc_card, '>Saved while a Supporter.</textarea>' ),
	);
}
set_tier( $A, '' );
$desc_card   = card( 'WPCPM_Sponsor_Profile', $A, $context );
$desc_friend = array(
	strpos( $desc_card, 'name="wpcpm_description"' ),
	strpos( $desc_card, 'Saved while a Supporter.' ),
	post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => 'Posted by hand.' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) )[0],
	WPCPM_Sponsors_Index::row( $A )['description'],
);
set_tier( $A, 'supporter' );
ck( 'the box is drawn from Supporter up with what the row holds; a Friend\'s is not drawn, a description it posts by hand is ignored, and one saved before stays on the row until the sponsor is a Supporter again', array(
	$desc_higher,
	$desc_friend,
	false !== strpos( card( 'WPCPM_Sponsor_Profile', $A, $context ), '>Saved while a Supporter.</textarea>' ),
), array(
	array_fill_keys( array( 'backer', 'champion', 'partner' ), array( true, true ) ),
	array( false, false, 'profile-unchanged', 'Saved while a Supporter.' ),
	true,
) );
// The description is one of this card's boxes of typed text: one the cleaner would take words from
// is refused before anything is written, the box named, and the card comes back with it as typed,
// once, for the person who typed it; posted back as drawn it is not asked, and keeps what the row
// holds. A refusal for its length brings the typing back too.
$GLOBALS['flash'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['left_detail'] = null;
WPCPM_Sponsors_Index::patch( $A, array( 'description' => 'Mango Example hosts students.' ) );
$dl_save  = array( 'WPCPM_Sponsor_Profile', 'handle_save' );
$dl_row   = WPCPM_Sponsors_Index::row( $A );
$dl_typed = "Kids <12 free,\r\nadults >18 pay, and 10%cashback";
$dl_shown = "Kids <12 free,\nadults >18 pay, and 10%cashback";
$r        = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => $dl_typed ), $dl_save );
ck( 'a description the cleaner would take words from, a tag over two lines and a percent octet, is refused before anything is written, the box named', array( $r, $GLOBALS['left_detail'], WPCPM_Sponsors_Index::row( $A ) === $dl_row, $GLOBALS['audit'] ), array( array( 'profile-loss', 'profile', $A ), 'Nothing was saved, because WordPress would remove part of what you typed in "Description for the partner page". To keep every word, put a space after each "<" (or write "less than") and after each "%", then save again.', true, array() ) );
$dl_first  = description_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$dl_second = description_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'and the card comes back with it as typed, each break one LF, once; drawn again, the box holds the row\'s', array( $dl_first, $dl_second ), array( $dl_shown, 'Mango Example hosts students.' ) );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => $dl_typed ), $dl_save );
$GLOBALS['uid'] = 1;
$dl_manager = description_box( card( 'WPCPM_Sponsor_Profile', $A, array( 'can_manage' => true, 'open' => '', 'viewer' => $GLOBALS['users'][1] ) ) );
$GLOBALS['uid'] = 5;
$dl_own = description_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'another person never sees it, a manager drawing the same card included', array( $dl_manager, $dl_own ), array( 'Mango Example hosts students.', $dl_shown ) );
$r       = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_contact_person' => 'Ana <ana@example.test>', 'wpcpm_description' => $dl_typed ), $dl_save );
$dl_said = $GLOBALS['left_detail'];
$dl_html = card( 'WPCPM_Sponsor_Profile', $A, $context );
ck( 'with words lost from the Contact person as well, the Contact person is named, as the form orders its boxes, and the description comes back with it', array( $r[0], $dl_said, cards_contact_box( $dl_html )['shown'] ?? null, description_box( $dl_html ), WPCPM_Sponsors_Index::row( $A ) === $dl_row ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Contact person' ), 'Ana <ana@example.test>', $dl_shown, true ) );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => $dl_typed ), $dl_save );
$dl_spaced = "Kids < 12 free,\r\nadults > 18 pay, and 10% cashback";
$r         = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => $dl_spaced ), $dl_save );
ck( '"Kids < 12 free, adults > 18 pay, and 10% cashback" is saved with every word, each break one LF, its box shows it as typed, and the refusal before it, never drawn, left nothing', array( $r[0], WPCPM_Sponsors_Index::row( $A )['description'], end( $GLOBALS['audit'] )['data']['fields'] ?? null, description_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) ), isset( $GLOBALS['flash'][5][ 'typed:profile:' . $A ] ) ), array( 'profile-saved', "Kids < 12 free,\nadults > 18 pay, and 10% cashback", array( 'description' ), "Kids < 12 free,\nadults > 18 pay, and 10% cashback", false ) );
// No form of the plugin stores a "%" with two letters after it in the description, but a row
// written by other means can hold one.
WPCPM_Sponsors_Index::patch( $A, array( 'description' => "Get 10%cashback\nat the booth." ) );
$dl_box = description_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => str_replace( "\n", "\r\n", (string) $dl_box ), 'wpcpm_website' => 'https://mango-example.test/listing/' ), $dl_save );
ck( 'a description the row holds with words the cleaner would take, posted back as drawn beside a change of the website, is not asked and keeps what the row holds', array( $dl_box, $r[0], WPCPM_Sponsors_Index::row( $A )['description'], end( $GLOBALS['audit'] )['data']['fields'] ?? null ), array( "Get 10%cashback\nat the booth.", 'profile-saved', "Get 10%cashback\nat the booth.", array( 'website' ) ) );
$dl_row = WPCPM_Sponsors_Index::row( $A );
$r      = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => "Get 10%cashback\r\nat the booth, again." ), $dl_save );
ck( 'edited, it is asked as any typing is, and refused', array( $r[0], description_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) ), WPCPM_Sponsors_Index::row( $A ) === $dl_row ), array( 'profile-loss', "Get 10%cashback\nat the booth, again.", true ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => "\r\n" . $dl_typed ), $dl_save );
ck( 'a kept description that begins with a line break comes back with it: the box prints a line feed of its own after its opening tag, which the parser drops', array( $r[0], description_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) ) ), array( 'profile-loss', "\n" . $dl_shown ) );
$dl_long = 'z' . str_repeat( 'y', 300 );
$r       = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_description' => $dl_long, 'wpcpm_product_type' => 'Hosting' ), $dl_save );
$dl_html = card( 'WPCPM_Sponsor_Profile', $A, $context );
ck( 'a description over its limit is refused as before, nothing written, and the card now comes back with the typing, kept up to the box\'s room of 300, and the product as chosen', array( $r[0], description_box( $dl_html ), cards_profile_form( $dl_html )['product_type'], WPCPM_Sponsors_Index::row( $A ) === $dl_row ), array( 'profile-too-long', mb_substr( $dl_long, 0, 300 ), 'Hosting', true ) );
set_tier( $A, '' );
WPCPM_Sponsors_Index::patch( $A, array( 'description' => '' ) );
$GLOBALS['settings'] = array();

echo "\n=== Profile: Anything else keeps what was typed ===\n";
// The box's limit counts what was typed, as the box counts it, and a longer text is refused as a
// whole, never cut. Core's cleaner writes a "<" that opens no tag, and the quote marks and the
// ampersand after it, as entities, so 4,000 typed characters are stored longer. The box keeps its
// line breaks, reads the entities back and shows what was typed, and an unedited post-back, as a
// browser posts the box, changes nothing.
$else_before = WPCPM_Sponsors_Index::row( $A )['anything'];
$else_typed  = str_repeat( 'Kids < 12 learn "blocks" & themes.' . "\n", 114 ) . "It's done.";
$else_posted = str_replace( "\n", "\r\n", $else_typed );
$else_r      = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $else_posted ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$else_saved  = WPCPM_Sponsors_Index::row( $A )['anything'];
$else_html   = card( 'WPCPM_Sponsor_Profile', $A, $context );
$else_audits = count( $GLOBALS['audit'] );
$else_again  = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => str_replace( "\n", "\r\n", (string) text_box( $else_html, 'wpcpm_anything' ) ) ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$else_after  = array( WPCPM_Sponsors_Index::row( $A )['anything'] === $else_saved, count( $GLOBALS['audit'] ) - $else_audits );
$else_long   = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $else_posted . '!' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'Anything else at its 4,000 typed characters, with a "<", an "&", quotes and line breaks, is kept whole and its box shows it as typed; an unedited post-back changes nothing; one typed character more is refused', array(
	mb_strlen( $else_typed ),
	$else_r,
	mb_strlen( $else_saved ) > 4000,
	substr_count( $else_saved, "\n" ),
	wp_specialchars_decode( $else_saved, ENT_QUOTES ) === $else_typed,
	text_box( $else_html, 'wpcpm_anything' ) === $else_typed,
	box_maxlength( $else_html, 'wpcpm_anything' ),
	$else_again,
	$else_after,
	$else_long,
	WPCPM_Sponsors_Index::row( $A )['anything'] === $else_saved,
), array( 4000, array( 'profile-saved', 'profile', $A ), true, 114, true, true, 4000, array( 'profile-unchanged', 'profile', $A ), array( true, 0 ), array( 'profile-text-long', 'profile', $A ), true ) );
ck( 'the refusal of Anything else names the box and its limit, and says nothing was saved', WPCPM_Sponsor_Profile::messages()['profile-text-long'] ?? null, array( 'error', 'The text for Anything else you would like to share is longer than 4000 characters, so nothing was saved.' ) );
// The cleaner would take the tags out of "We <3 our <b>team</b> > all", so that typing is refused
// and nothing is written; the page it lands on draws it back once. Saved before the refusal came,
// it was stored with the tags taken out and the "<" of the "<3" written `&lt;`, and a row can hold
// that form still. The box holds the ">" after it as `&gt;`, four characters in the box and one to
// the limit, so its maxlength is three more; posted back unedited, the words are kept and nothing
// changes.
$else_tag_r     = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => 'We <3 our <b>team</b> > all' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$else_tag_kept  = WPCPM_Sponsors_Index::row( $A )['anything'] === $else_saved;
card( 'WPCPM_Sponsor_Profile', $A, $context );
WPCPM_Sponsors_Index::patch( $A, array( 'anything' => 'We &lt;3 our team > all' ) );
$else_tag_saved = WPCPM_Sponsors_Index::row( $A )['anything'];
$else_tag_html  = card( 'WPCPM_Sponsor_Profile', $A, $context );
$else_tag_again = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => (string) text_box( $else_tag_html, 'wpcpm_anything' ) ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'Anything else typed with a tag is refused and nothing is written; held in the form the cleaner once left it, it keeps the rest through an unedited post-back, its box three characters longer for the ">" it holds', array(
	$else_tag_r[0],
	$else_tag_kept,
	$else_tag_saved,
	text_box( $else_tag_html, 'wpcpm_anything' ),
	box_maxlength( $else_tag_html, 'wpcpm_anything' ),
	$else_tag_again[0],
	WPCPM_Sponsors_Index::row( $A )['anything'] === $else_tag_saved,
), array( 'profile-loss', true, 'We &lt;3 our team > all', 'We <3 our team &gt; all', 4003, 'profile-unchanged', true ) );
// An application's answer is copied onto the row as the application stored it, its line breaks
// CR LF, where a save of the box stores LF. Posted back unedited it is the same text, so nothing
// changes and the row keeps it as it was; a save that changes only the website names the website
// alone.
$app_text    = "We host WordPress sites.\r\nHappy to help.";
$app_site    = WPCPM_Sponsors_Index::row( $A )['website'];
WPCPM_Sponsors_Index::patch( $A, array( 'anything' => $app_text ) );
$app_box     = text_box( card( 'WPCPM_Sponsor_Profile', $A, $context ), 'wpcpm_anything' );
$app_audits  = count( $GLOBALS['audit'] );
$app_again   = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => browser_lines( $app_box ) ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$app_after   = array( WPCPM_Sponsors_Index::row( $A )['anything'] === $app_text, count( $GLOBALS['audit'] ) - $app_audits );
WPCPM_Sponsors_Index::patch( $A, array( 'anything' => $app_text ) );
$app_website = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://www.mango-example.test/', 'wpcpm_anything' => browser_lines( $app_box ) ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'an application\'s Anything else, its line breaks CR LF, posted back unedited changes nothing and is kept as it was; a save of the website alongside it names the website alone', array(
	$app_again,
	$app_after,
	$app_website,
	WPCPM_Sponsors_Index::row( $A )['anything'] === $app_text,
	end( $GLOBALS['audit'] )['data']['fields'],
), array( array( 'profile-unchanged', 'profile', $A ), array( true, 0 ), array( 'profile-saved', 'profile', $A ), true, array( 'website' ) ) );
// A text stored longer than the box's 4,000 before the limit counted what was typed, as a row kept
// from an earlier store can hold: only a changed text is measured, so posted back unedited it
// changes nothing, and a save of the website beside it saves the website and keeps the text as it
// was. Edited and still too long, it is refused, and nothing is written.
$old_text    = trim( str_repeat( 'We host WordPress sites for students. ', 112 ) );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => $app_site, 'anything' => $old_text ) );
$old_box     = text_box( card( 'WPCPM_Sponsor_Profile', $A, $context ), 'wpcpm_anything' );
$old_audits  = count( $GLOBALS['audit'] );
$old_again   = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => browser_lines( $old_box ) ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$old_website = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://www.mango-example.test/', 'wpcpm_anything' => browser_lines( $old_box ) ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
$old_saved   = WPCPM_Sponsors_Index::row( $A );
$old_fields  = end( $GLOBALS['audit'] )['data']['fields'];
$old_edited  = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => browser_lines( $old_box ) . ' More.' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'an Anything else stored longer than 4,000 before is measured only once it is changed: posted back unedited it changes nothing, a save of the website beside it saves the website alone, and an edit still too long is refused', array(
	WPCPM_Sponsor_Event::typed_length( $old_text ) > 4000,
	$old_box === $old_text,
	$old_again,
	array( $old_website, $old_saved['website'], $old_saved['anything'] === $old_text, $old_fields ),
	$old_edited,
	WPCPM_Sponsors_Index::row( $A )['anything'] === $old_text,
	count( $GLOBALS['audit'] ) - $old_audits,
), array( true, true, array( 'profile-unchanged', 'profile', $A ), array( array( 'profile-saved', 'profile', $A ), 'https://www.mango-example.test/', true, array( 'website' ) ), array( 'profile-text-long', 'profile', $A ), true, 1 ) );
// Only the text is compared before its rule: a website the rule refuses is refused even where what
// the rule leaves of it, nothing, is what the row holds.
WPCPM_Sponsors_Index::patch( $A, array( 'website' => '' ) );
ck( 'a refused website is refused even where the row holds what the rule leaves of it', post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://x@y' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) ), array( 'profile-rejected', 'profile', $A ) );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => $app_site, 'anything' => $else_before ) );

echo "\n=== Interests ===\n";
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_support' => array( 'Sponsor tools or services', 'Made up' ), 'wpcpm_events' => "WordCamp Europe 2027\nWordCamp Asia 2027", 'wpcpm_note' => 'Happy to help.' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'an interest is sent', $r, array( 'interest-sent', 'interests', $A ) );
$row = WPCPM_Sponsors_Index::row( $A );
ck( 'the multiple select is written with the known choices only', $row['support'], array( 'Sponsor tools or services' ) );
ck( 'and one dated line is appended to the history', substr_count( $row['interests'], "\n" ) === 1 && false !== strpos( $row['interests'], 'by Rep One: Sponsor tools or services; events: WordCamp Europe 2027, WordCamp Asia 2027; note: Happy to help.' ), true );
ck( 'the assigned manager is mailed, once, in the interest context', array( count( $GLOBALS['sent'] ), $GLOBALS['sent'][0][0], $GLOBALS['sent'][0][1], $GLOBALS['sent'][0][2] ), array( 1, 'user', 1, 'sponsor-interest' ) );
ck( 'the mail names the sponsor and what it said', false !== strpos( $GLOBALS['sent'][0][3]['body'], 'Mango Example' ) && false !== strpos( $GLOBALS['sent'][0][3]['body'], 'WordCamp Europe 2027' ), true );
ck( 'and its subject names the site, as every other email does', $GLOBALS['sent'][0][3]['subject'], '[WP Credits] Mango Example would like to support the program' );
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
// A noun for a title among nouns, where a question stood (the design the owner approved on 27
// September 2026): the row says what the card is for, and Tell us, after the fold, opens the card
// at the form.
$tell_us = '</div></details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-interests">Tell us<span class="screen-reader-text"> what more your company would offer</span></a></p></section>';
ck( 'the row: Offer more, folded until a flash names it, the phrase that says what it is for, and Tell us after the fold', array(
	0 === strpos( $html, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-interests" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Offer more</h3><span class="wpcpm-sponsor__state">Tell the program what else you would support</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>' ),
	substr( $html, -strlen( $tell_us ) ) === $tell_us,
	false !== strpos( card( 'WPCPM_Sponsor_Interests', $A, array_merge( $context, array( 'open' => 'interests' ) ) ), '<details id="wpcpm-sponsor-interests" class="wpcpm-group wpcpm-group__disclosure" open>' ),
	strpos( $html, 'What else would you like to support?' ),
), array( true, true, true, false ) );
$interests_opened = card( 'WPCPM_Sponsor_Interests', $A, array_merge( $context, array( 'open' => 'interests' ) ) );
ck( 'opened by a flash, Offer more holds its form on screen and still prints Tell us after its fold, which the stylesheets hide while the card is open', array( is_open( $interests_opened, 'interests' ), substr( $interests_opened, -strlen( $tell_us ) ) === $tell_us, substr_count( $interests_opened, 'name="wpcpm_support[]"' ) ), array( true, true, 6 ) );

echo "\n=== An interest appends one dated line to the store's history and mails the manager ===\n";
// User 5's ceiling is already at five for today (the loop above); this scenario is about the
// append across two saves, not the ceiling, so it gets a fresh bucket.
$GLOBALS['buckets'] = array();
WPCPM_Sponsors_Index::patch( $A, array( 'interests' => '' ) );
$GLOBALS['sent']    = array();
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'first save' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'second save' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
$lines = explode( "\n", WPCPM_Sponsors_Index::row( $A )['interests'] );
ck( 'an interest appends one dated line to the store\'s history and mails the manager', array(
	count( $lines ),
	false !== strpos( $lines[0], 'note: first save' ),
	false !== strpos( $lines[1], 'note: second save' ),
	count( $GLOBALS['sent'] ),
), array( 2, true, true, 2 ) );

echo "\n=== The support choices are written to the row when ticked and left alone when not ===\n";
$GLOBALS['buckets'] = array();
WPCPM_Sponsors_Index::patch( $A, array( 'support' => array( 'Sponsor tools or services' ) ) );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'no choices this time' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'left alone when nothing is ticked', WPCPM_Sponsors_Index::row( $A )['support'], array( 'Sponsor tools or services' ) );
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_support' => array( 'Sponsor a mentor or multiple mentors' ), 'wpcpm_note' => 'a choice this time' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'written to the row when ticked', WPCPM_Sponsors_Index::row( $A )['support'], array( 'Sponsor a mentor or multiple mentors' ) );

echo "\n=== A member whose sponsor left the index cannot be written back to ===\n";
// A stamp naming a record the index no longer holds: the roster's claim() still allows the
// member (the stamp is well-formed and the ground is ungated), but WPCPM_Sponsors_Index::row()
// answers null. A different account from every other check in this file, so its own ceiling
// is untouched by anything above or below.
$GLOBALS['users'][6] = new WP_User( 6, array( 'wpcpm_sponsor' ), 'Departed Rep', 'maciej@a8c.com' );
$GLOBALS['umeta'][6] = array( WPCPM_Sponsor_Members::META_RECORD_ID => 'recSPONSOR0000009', WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['uid']      = 6;
$r = post( array( 'wpcpm_sponsor' => 'recSPONSOR0000009', 'wpcpm_website' => 'https://hijacked.test/' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'an unknown sponsor is refused before anything is written (profile)', array( $r[0], WPCPM_Sponsors_Index::row( 'recSPONSOR0000009' ) ), array( 'profile-failed', null ) );
$GLOBALS['sent'] = array();
$r = post( array( 'wpcpm_sponsor' => 'recSPONSOR0000009', 'wpcpm_note' => 'hello' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a sponsor the index does not hold cannot be written back to (interests)', array( $r[0], $GLOBALS['sent'] ), array( 'interest-failed', array() ) );
$GLOBALS['uid'] = 5;

echo "\n=== A store that refuses is said, and no mail goes ===\n";
// The scenario above leaves at the row guard before patch() is ever called: the roster's
// claim() itself answers row() null for a key the index never held. A real store refusal has
// to get past that guard, which means claim() has to find a row - so the request cache is
// warmed with a plain read first, and the post is then removed without calling anything that
// would forget it (patch()/insert()/write() all do; a bare unset() does not). claim() finds
// the still-cached row and passes; patch()'s own post_id_of() call is never cached, so it
// finds nothing to write to and returns false, same as it would for any other post gone missing
// between a read and a write.
WPCPM_Sponsors_Index::row( $A );
$a_post_id        = WPCPM_Sponsors_Index::post_id_of( $A );
$a_post           = $GLOBALS['posts'][ $a_post_id ];
unset( $GLOBALS['posts'][ $a_post_id ] );
$GLOBALS['sent']  = array();
$GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'a real store refusal' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a store that refuses is said, and no mail goes', array( $r[0], $GLOBALS['sent'], $GLOBALS['audit'] ), array( 'interest-failed', array(), array() ) );
$GLOBALS['posts'][ $a_post_id ] = $a_post;

echo "\n=== file(): the one way an interest is filed (1.112.0) ===\n";
// handle_save() above and the tier card's two buttons below all end here, so the line, the
// append, the mail and the log are written once. The ground is the caller's: 'manager' below is
// passed on purpose for member 5, so the audit row can only carry it if file() took it as given.
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array();
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => '1', 'support' => array( 'Sponsor tools or services' ) ) );
$lines_before = explode( "\n", WPCPM_Sponsors_Index::row( $A )['interests'] );
$filed        = WPCPM_Sponsor_Interests::file( $A, WPCPM_Sponsors_Index::row( $A ), array(), array( 'WordCamp Asia 2027' ), '', 'Rep One', 'manager' );
$lines_after  = explode( "\n", WPCPM_Sponsors_Index::row( $A )['interests'] );
$no_choices   = array( $filed, count( $lines_after ) - count( $lines_before ), end( $lines_after ), WPCPM_Sponsors_Index::row( $A )['support'], count( $GLOBALS['sent'] ), $GLOBALS['sent'][0][0], $GLOBALS['sent'][0][1], $GLOBALS['sent'][0][2], end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['ground'], end( $GLOBALS['audit'] )['actor'] );
WPCPM_Sponsor_Interests::file( $A, WPCPM_Sponsors_Index::row( $A ), array( 'Sponsor a mentor or multiple mentors' ), array(), 'with a choice', 'Rep One', 'member' );
ck( 'file() appends the line, writes support only when choices were given, mails the manager and logs the kind with the ground given', array(
	$no_choices,
	WPCPM_Sponsors_Index::row( $A )['support'],
	end( $GLOBALS['audit'] )['ground'],
), array(
	array( array( 'ok' => true, 'mailed' => 1, 'line' => '2026-10-06 by Rep One: events: WordCamp Asia 2027' ), 1, '2026-10-06 by Rep One: events: WordCamp Asia 2027', array( 'Sponsor tools or services' ), 1, 'user', 1, 'sponsor-interest', 'sponsor_interest', 'manager', 5 ),
	array( 'Sponsor a mentor or multiple mentors' ),
	'member',
) );
// The same removed post as the scenario above: the cache is warmed, the post goes without
// anything that would forget it, and patch() finds nothing to write to.
WPCPM_Sponsors_Index::row( $A );
$a_post_id = WPCPM_Sponsors_Index::post_id_of( $A );
$a_post    = $GLOBALS['posts'][ $a_post_id ];
unset( $GLOBALS['posts'][ $a_post_id ] );
$GLOBALS['sent'] = array(); $GLOBALS['audit'] = array();
$filed = WPCPM_Sponsor_Interests::file( $A, WPCPM_Sponsors_Index::row( $A ), array(), array(), 'a refused note', 'Rep One', 'member' );
$GLOBALS['posts'][ $a_post_id ] = $a_post;
ck( 'file() answers not ok when the store refuses, and then mails and logs nothing', array( $filed['ok'], $filed['mailed'], $GLOBALS['sent'], $GLOBALS['audit'] ), array( false, 0, array(), array() ) );
// The stand-in's today moves a day on for this one call, so the line can start with 2026-10-06
// only if file() dated it by the day it was given.
$GLOBALS['today'] = '2026-10-07';
$filed            = WPCPM_Sponsor_Interests::file( $A, WPCPM_Sponsors_Index::row( $A ), array(), array(), 'a dated note', 'Rep One', 'member', '2026-10-06' );
$GLOBALS['today'] = '2026-10-06';
ck( 'file() takes the day it is given', array( $filed['ok'], $filed['line'] ), array( true, '2026-10-06 by Rep One: note: a dated note' ) );

echo "\n=== The tier card: Sponsorship and Events (1.112.0) ===\n";
// A is the one sponsor throughout; its tier, term and funded line move through the store's own
// patch() between the checks, and every term is a day counted from the fixture's today.
$T = 'WPCPM_Sponsor_Tier_Card';
// The Events row offers Plan an event on the planner's own answer, so the allowance the planner
// draws its form by is public, asked by both: asked here first, before any card is drawn with it.
ck( 'the planner\'s allowance is the one answer the Events row and the planner\'s form share, public for the card', is_callable( array( 'WPCPM_Sponsor_Event_Planner', 'allowance' ) ), true );
// And what that answer means for whether the sponsor holds the planner is the planner's own reading,
// public too, so the card asks the allowance once and reads both from it.
ck( 'and whether the answer leaves the sponsor holding the planner is the planner\'s own reading, public for the card, which asks the allowance once for both', is_callable( array( 'WPCPM_Sponsor_Event_Planner', 'holds' ) ), true );
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array();
$GLOBALS['settings'] = array();
set_tier( $A, 'supporter', day_from_today( 100 ), 'Two mentor stipends' );
ck( 'the card prints nothing while the switch is off', card( $T, $A, $context ), '' );

$GLOBALS['settings']['sponsor_tiers_live'] = true;
$html = card( $T, $A, $context );
ck( 'a Supporter reads its tier and term, what it includes, what Backer adds, and the button', array(
	false !== strpos( $html, '<p class="wpcpm-sponsorship__tier"><strong>Supporter</strong> until ' . WPCPM_Sponsor_Tiers::format_day( day_from_today( 100 ) ) . '.</p>' ),
	substr_count( between( $html, '<ul class="wpcpm-sponsorship__includes">', '</ul>' ), '<li>' ),
	false !== strpos( $html, '<h4 class="wpcpm-sponsor__subheading">Your tier includes</h4><p class="wpcpm-sponsorship__lead">Everything in Friend, plus:</p><ul class="wpcpm-sponsorship__includes">' ),
	between( $html, '<ul class="wpcpm-sponsorship__includes">', '</ul>' ) === '<li>Logo and description on the partner page</li><li>A badge for your own site</li><li>The annual program report</li>',
	false !== strpos( $html, '<p class="wpcpm-student__note">Backer names your company for the part of the program you fund in program communications and adds a report covering that cost line and one self-written educational post about the sponsored product.</p>' ),
	false !== strpos( $html, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-sponsor__form wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Sending">' ),
	false !== strpos( $html, 'value="nonce-wpcpm_tier_talk_' . $A . '"' ) && false !== strpos( $html, 'name="action" value="wpcpm_tier_talk"' ) && false !== strpos( $html, 'name="wpcpm_sponsor" value="' . $A . '"' ),
	false !== strpos( $html, '<button type="submit" class="wpcpm-button wpcpm-button--secondary">Talk to your program contact</button></form><p class="wpcpm-student__note">One press tells your program contact you would like to talk about the next tier.</p>' ),
	strpos( $html, 'wpcpm-sponsorship__funded' ),
	strpos( $html, 'Your term ends on' ),
	strpos( $html, 'id="wpcpm-sponsor-events"' ),
), array( true, 3, true, true, true, true, true, true, false, false, false ) );
// The card that says how the sponsorship stands is open whatever a flash says (the design the owner
// approved on 27 September 2026), under a title without "Your", and its row says the tier, the
// term's last day and the days left in it; it offers no action of its own, since Talk is inside it.
ck( 'the sponsorship card is always open, titled Sponsorship, its row the tier to the term\'s last day with the days left, and no action', array(
	0 === strpos( $html, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-sponsorship" class="wpcpm-group wpcpm-group__disclosure" open><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Sponsorship</h3><span class="wpcpm-sponsor__state">Supporter to ' . WPCPM_Sponsor_Tiers::format_day( day_from_today( 100 ) ) . ', 100 days left</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>' ),
	substr( $html, -strlen( '</div></details></section>' ) ),
	row_action( $html, 'sponsorship' ),
	strpos( $html, 'Your sponsorship<' ),
), array( true, '</div></details></section>', null, false ) );

set_tier( $A, '' );
$html   = card( $T, $A, $context );
// The tier line names the tier and, once the Partners page is published, links it; what the tier
// includes is the checklist's to say, once, and it says the company's name is on the partner page.
$listed = '<p class="wpcpm-sponsorship__tier"><strong>Friend</strong></p>';
// A date left on a Friend's row is no term (the Sponsors screen never writes one there),
// whether it lies ahead or behind: the same line, and no note.
set_tier( $A, '', day_from_today( 10 ) );
$stray_ahead = card( $T, $A, $context );
set_tier( $A, 'mentor', day_from_today( -3 ) );
$stray_behind = card( $T, $A, $context );
ck( 'a Friend reads its tier, the partner page\'s three lines saying once that its name is on the partner page, what Supporter adds, and the button', array(
	false !== strpos( $html, $listed ),
	substr_count( between( $html, '<ul class="wpcpm-sponsorship__includes">', '</ul>' ), '<li>' ),
	substr_count( $html, 'Company name on the partner page' ),
	false !== strpos( between( $html, '<ul class="wpcpm-sponsorship__includes">', '</ul>' ), '<li>Company name on the partner page</li><li>Mention in the mentor feature</li><li>Acknowledgment in the annual partner thank you post</li>' ),
	false !== strpos( $html, '<p class="wpcpm-student__note">Supporter adds your logo and description on the partner page, a badge for your own site and the annual program report.</p>' ),
	false !== strpos( $html, 'name="action" value="wpcpm_tier_talk"' ),
	array( false !== strpos( $stray_ahead, $listed ), strpos( $stray_ahead, 'Your term ends on' ), false !== strpos( $stray_behind, $listed ), strpos( $stray_behind, 'term ended' ) ),
), array( true, 3, 1, true, true, true, array( true, false, true, false ) ) );
ck( 'a Friend\'s list has no lead line: Friend is the first level, with nothing below it', strpos( $html, 'wpcpm-sponsorship__lead' ), false );
// The row says what the tier line says, the tier's name: the checklist under it already says the
// company's name is on the partner page, once. A date on a Friend's row is no term, so none is counted.
ck( 'a Friend\'s row names its tier and no term, whatever date is left on its row, and the card is open', array( row_state( $html, 'sponsorship' ), row_state( $stray_ahead, 'sponsorship' ), row_state( $stray_behind, 'sponsorship' ), is_open( $html, 'sponsorship' ) ), array( 'Friend', 'Friend', 'Friend', true ) );

// The same line links to the Partners page once it is published, inside the paragraph and after
// the tier's name; with no published page it is the line above, word for word.
set_tier( $A, '' );
$GLOBALS['sponsors_page_url'] = 'https://site.example/partners/';
$linked                       = card( $T, $A, $context );
$GLOBALS['sponsors_page_url'] = '';
$unlinked                     = card( $T, $A, $context );
ck( 'and the line links to the Partners page once it is published, and is unchanged while it is not', array(
	false !== strpos( $linked, '<p class="wpcpm-sponsorship__tier"><strong>Friend</strong> <a href="https://site.example/partners/">See the program&#039;s partners.</a></p>' ),
	substr_count( $linked, 'Company name on the partner page' ),
	false !== strpos( $unlinked, $listed ),
), array( true, 1, true ) );

set_tier( $A, 'backer', day_from_today( 12 ), 'Two mentor stipends' );
$html  = card( $T, $A, $context );
$notes = array();
foreach ( array( 30, 1, 0, 31 ) as $days ) {
	set_tier( $A, 'backer', day_from_today( $days ) );
	$notes[ $days ] = card( $T, $A, $context );
}
ck( 'a Backer reads its funded line, and the note thirty days before the term ends', array(
	false !== strpos( $html, '<p class="wpcpm-sponsorship__funded">Your sponsorship funds: Two mentor stipends</p>' ),
	false !== strpos( $html, '<p class="wpcpm-student__note">Your term ends on ' . WPCPM_Sponsor_Tiers::format_day( day_from_today( 12 ) ) . ', in 12 days. Your program contact can renew it.</p>' ),
	false !== strpos( $notes[30], '<p class="wpcpm-student__note">Your term ends on ' . WPCPM_Sponsor_Tiers::format_day( day_from_today( 30 ) ) . ', in 30 days. Your program contact can renew it.</p>' ),
	false !== strpos( $notes[1], '<p class="wpcpm-student__note">Your term ends on ' . WPCPM_Sponsor_Tiers::format_day( day_from_today( 1 ) ) . ', tomorrow. Your program contact can renew it.</p>' ),
	false !== strpos( $notes[0], '<p class="wpcpm-student__note">Your term ends on ' . WPCPM_Sponsor_Tiers::format_day( $GLOBALS['today'] ) . ', today. Your program contact can renew it.</p>' ),
	// The last day of a term is still the tier.
	false !== strpos( $notes[0], '<p class="wpcpm-sponsorship__tier"><strong>Backer</strong> until ' . WPCPM_Sponsor_Tiers::format_day( $GLOBALS['today'] ) . '.</p>' ),
	strpos( $notes[31], 'Your term ends on' ),
), array( true, true, true, true, true, true, false ) );
// Days are counted as the thirty-day note counts them; one is a day, and the last day is not a
// count of nothing left: the term runs through it, so the row says it ends today, as the note does.
ck( 'the row counts the days left, one of them a day, and on the term\'s last day says it ends today', array(
	row_state( $html, 'sponsorship' ),
	row_state( $notes[31], 'sponsorship' ),
	row_state( $notes[1], 'sponsorship' ),
	row_state( $notes[0], 'sponsorship' ),
), array(
	'Backer to ' . WPCPM_Sponsor_Tiers::format_day( day_from_today( 12 ) ) . ', 12 days left',
	'Backer to ' . WPCPM_Sponsor_Tiers::format_day( day_from_today( 31 ) ) . ', 31 days left',
	'Backer to ' . WPCPM_Sponsor_Tiers::format_day( day_from_today( 1 ) ) . ', 1 day left',
	'Backer to ' . WPCPM_Sponsor_Tiers::format_day( $GLOBALS['today'] ) . ', ending today',
) );

set_tier( $A, 'backer', day_from_today( -1 ), 'Two mentor stipends' );
$html = card( $T, $A, $context );
ck( 'a lapsed term reads as a Friend with the renewal sentence', array(
	false !== strpos( $html, '<p class="wpcpm-sponsorship__tier">Your Backer term ended on ' . WPCPM_Sponsor_Tiers::format_day( day_from_today( -1 ) ) . '. Your company reads as a Friend now; your program contact can renew it.</p>' ),
	strpos( $html, '<strong>' ),
	substr_count( between( $html, '<ul class="wpcpm-sponsorship__includes">', '</ul>' ), '<li>' ),
	strpos( $html, 'wpcpm-sponsorship__funded' ),
	strpos( $html, 'Your term ends on' ),
), array( true, false, 3, false, false ) );
ck( 'a lapsed term\'s row says the sponsor reads as a Friend since that term ended, naming the term and its last day', row_state( $html, 'sponsorship' ), 'Friend since the Backer term ended on ' . WPCPM_Sponsor_Tiers::format_day( day_from_today( -1 ) ) );

set_tier( $A, 'partner' );
$html = card( $T, $A, $context );
ck( 'a Partner reads the partner page\'s lead above its three lines for the level, the lead its own line and no checked item, no next tier and no button', array(
	false !== strpos( $html, '<p class="wpcpm-sponsorship__tier"><strong>Partner</strong></p>' ) && false !== strpos( $html, '<p class="wpcpm-sponsorship__lead">Everything in Champion, plus:</p><ul class="wpcpm-sponsorship__includes">' ),
	between( $html, '<ul class="wpcpm-sponsorship__includes">', '</ul>' ),
	strpos( $html, ' adds ' ),
	strpos( $html, 'wpcpm_tier_talk' ),
	strpos( $html, 'talk about the next tier' ),
), array( true, '<li>Named as program partner on the program you fund, with the exclusive role in that program</li><li>Named in launch communications</li><li>A report specific to that program</li>', false, false, false ) );
ck( 'a tier with no term recorded: the row names the tier alone, as the tier line does', row_state( $html, 'sponsorship' ), 'Partner' );

// The Events card (1.117.0): the events the tier includes, the sentence that says what the planner
// under them is for, naming the launch communications only for a tier that includes them, and the
// planner, whose form an Approved sponsor with nothing planned is drawn. Plan it now is gone.
$events_of = array();
$cards_of  = array();
foreach ( array( 'backer', 'champion', 'partner' ) as $tier ) {
	set_tier( $A, $tier );
	$events_html        = card( $T, $A, $context );
	$cards_of[ $tier ]  = between( $events_html, 'id="wpcpm-sponsor-events"', '</section>' );
	$events_of[ $tier ] = array( false !== strpos( $events_html, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-events" class="wpcpm-group wpcpm-group__disclosure">' ), substr_count( $events_html, 'name="action" value="wpcpm_sevent_plan"' ), substr_count( $events_html, 'Plan it now' ), substr_count( $events_html, 'wpcpm_tier_plan' ) );
}
ck( 'the Events card is drawn from Champion up, holds the planner\'s form while nothing is planned, and has no Plan it now anywhere', array(
	$events_of,
	before( $events_html, 'id="wpcpm-sponsor-sponsorship"', 'id="wpcpm-sponsor-events"' ),
	false !== strpos( $cards_of['partner'], '<h3 class="wpcpm-group__title">Events</h3>' ),
	false !== strpos( $cards_of['partner'], 'value="nonce-wpcpm_sevent_plan_' . $A . '"' ) && false !== strpos( $cards_of['partner'], 'name="wpcpm_sponsor" value="' . $A . '"' ),
	strpos( $cards_of['partner'], 'name="wpcpm_event"' ),
), array(
	array( 'backer' => array( false, 0, 0, 0 ), 'champion' => array( true, 1, 0, 0 ), 'partner' => array( true, 1, 0, 0 ) ),
	true, true, true, false,
) );
ck( 'it lists the events the tier includes, then says what the planner under them is for, naming the launch communications only where the tier includes them', array(
	false !== strpos( $cards_of['partner'], '<div class="wpcpm-group__body"><h4 class="wpcpm-sponsor__subheading">Events your tier includes</h4><ul class="wpcpm-sponsorship__events"><li>An invitation to the annual education partners session</li><li>A webinar or event co-hosted with the education program</li><li>Named in launch communications</li></ul><p class="wpcpm-student__note">The planner below is for your co-hosted webinar or event; the program plans the partners session and the launch communications with you.</p>' ),
	false !== strpos( $cards_of['champion'], '<ul class="wpcpm-sponsorship__events"><li>An invitation to the annual education partners session</li><li>A webinar or event co-hosted with the education program</li></ul><p class="wpcpm-student__note">The planner below is for your co-hosted webinar or event; the program plans the partners session with you.</p>' ),
	before( $cards_of['partner'], '<p class="wpcpm-student__note">The planner below', '<h4 class="wpcpm-sponsor__subheading">Plan your co-hosted event</h4>' ),
), array( true, true, true ) );

// More events the program granted for the running term, on the Sponsors screen's Tier box: the list
// says how many under the co-hosted event, and says nothing of a number granted for another day.
set_tier( $A, 'champion', day_from_today( 100 ) );
WPCPM_Sponsors_Index::patch( $A, array( 'event_allowance' => array( 'until' => day_from_today( 100 ), 'extra' => 2 ) ) );
$granted_card = between( card( $T, $A, $context ), 'id="wpcpm-sponsor-events"', '</section>' );
WPCPM_Sponsors_Index::patch( $A, array( 'event_allowance' => array( 'until' => day_from_today( 50 ), 'extra' => 2 ) ) );
$other_day_card = between( card( $T, $A, $context ), 'id="wpcpm-sponsor-events"', '</section>' );
WPCPM_Sponsors_Index::patch( $A, array( 'event_allowance' => array() ) );
ck( 'more events granted for the running term are one more line under the co-hosted event, and a number granted for another day is none', array(
	false !== strpos( $granted_card, '<ul class="wpcpm-sponsorship__events"><li>An invitation to the annual education partners session</li><li>A webinar or event co-hosted with the education program</li><li>and 2 more this term, granted by the program</li></ul>' ),
	strpos( $other_day_card, 'granted by the program' ),
	false !== strpos( $other_day_card, '<ul class="wpcpm-sponsorship__events"><li>An invitation to the annual education partners session</li><li>A webinar or event co-hosted with the education program</li></ul>' ),
), array( true, false, true ) );

set_tier( $A, 'champion' );
$closed   = card( $T, $A, $context );
$opened_s = card( $T, $A, array_merge( $context, array( 'open' => 'sponsorship' ) ) );
$opened_e = card( $T, $A, array_merge( $context, array( 'open' => 'events' ) ) );
// Each card opens by what it holds (the design the owner approved on 27 September 2026): the
// sponsorship card always, Events while an event of the sponsor's is still to take place; a flash
// still opens the card it names. A Champion with nothing planned holds the planner with nothing on
// it, so its Events row says so and offers Plan an event after the fold, a link to the card, where
// the form is.
$plan_an_event = '<a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-events">Plan an event<span class="screen-reader-text"> with the program</span></a>';
ck( 'each card is a section wrapping its disclosure: the sponsorship card open whatever the flash says, Events folded with nothing to come, its row saying so and offering Plan an event, and opened when a flash names it', array(
	substr_count( $closed, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-' ),
	substr_count( $closed, '</div></details></section>' ),
	substr_count( $closed, '</div></details><p class="wpcpm-sponsor__row-action">' . $plan_an_event . '</p></section>' ),
	array( is_open( $closed, 'sponsorship' ), is_open( $closed, 'events' ) ),
	array( is_open( $opened_s, 'sponsorship' ), is_open( $opened_s, 'events' ) ),
	array( is_open( $opened_e, 'sponsorship' ), is_open( $opened_e, 'events' ) ),
	row_state( $closed, 'events' ),
	row_action( $closed, 'events' ),
	false !== strpos( $closed, '<h3 class="wpcpm-group__title">Events</h3><span class="wpcpm-sponsor__state">No event planned</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>' ),
), array( 2, 1, 1, array( true, false ), array( true, false ), array( true, true ), 'No event planned', $plan_an_event, true ) );
ck( 'opened by a flash with nothing to come, the Events card holds the planner\'s form on screen and still prints Plan an event after its fold, which the stylesheets hide while the card is open', array( is_open( $opened_e, 'events' ), row_action( $opened_e, 'events' ), substr_count( $opened_e, 'name="action" value="wpcpm_sevent_plan"' ) ), array( true, $plan_an_event, 1 ) );

// The planner inside the card (1.117.0), for the Champion: the card hands it the sponsor and the
// planner draws the event still to take place, with Change and Cancel keyed to the sponsor and
// naming the event, and the one sentence in the form's place, then the events that took place, with
// how many joined and never who. How the planner words and marks each of these up is pinned in
// bin/test-sponsor-event-planner.php; this card's part is that it is handed in. The events are
// written the way the store writes them, dated from the fixture's today.
set_tier( $A, 'champion' );
$sevent_next = seed_sevent( $A, 'planned', strtotime( '2026-10-20 15:00:00 UTC' ), 60, 'Mango "live" & friends' );
$sevent_past = seed_sevent( $A, 'approved', strtotime( '2026-09-20 15:00:00 UTC' ), 90, 'Mango launch', array( 42, 43 ) );
$with_both   = between( card( $T, $A, $context ), 'id="wpcpm-sponsor-events"', '</section>' );
$GLOBALS['settings'] = array();
$switched_off        = card( $T, $A, $context );
$GLOBALS['settings']['sponsor_tiers_live'] = true;
ck( 'an event still to take place is its own line, with Change and Cancel keyed to the sponsor and naming the event, and the sentence stands in the form\'s place; nothing at all while the switch is off', array(
	false !== strpos( $with_both, '<div class="wpcpm-sevent__own">' ) && false !== strpos( $with_both, 'Mango &quot;live&quot; &amp; friends' ),
	false !== strpos( $with_both, 'value="nonce-wpcpm_sevent_change_' . $A . '"' ) && false !== strpos( $with_both, 'name="action" value="wpcpm_sevent_change"' ),
	false !== strpos( $with_both, 'value="nonce-wpcpm_sevent_cancel_' . $A . '"' ) && false !== strpos( $with_both, 'name="action" value="wpcpm_sevent_cancel"' ),
	substr_count( $with_both, 'name="wpcpm_sevent_id" value="' . $sevent_next . '"' ),
	false !== strpos( $with_both, 'Your next event can be planned once this one has taken place.' ),
	strpos( $with_both, 'value="wpcpm_sevent_plan"' ),
	$switched_off,
), array( true, true, true, 2, true, false, '' ) );
$with_both_page = card( $T, $A, $context );
ck( 'an event still to take place opens the card, and its row names the event and its day, the title escaped, and, planned and not yet approved, that it waits for the program rather than how many joined; no Plan an event while one is planned', array(
	is_open( $with_both_page, 'events' ),
	row_state( $with_both_page, 'events' ),
	row_action( $with_both_page, 'events' ),
), array( true, 'Mango &quot;live&quot; &amp; friends on October 20, 2026, waiting for the program', null ) );
// An event still to come stops a new plan and leaves the planner the sponsor's: the card still lists
// what the tier includes and says what the planner is for.
ck( 'while its event is still to come the Champion still holds the planner: the card lists the events the tier includes and says what the planner under them is for', array( false !== strpos( $with_both, '<ul class="wpcpm-sponsorship__events">' ), false !== strpos( $with_both, 'The planner below is for your co-hosted webinar or event' ) ), array( true, true ) );
ck( 'the events that took place, read only: listed by title with how many joined, never who, and with no form of their own', array(
	false !== strpos( $with_both, '<ul class="wpcpm-sevent__past">' ) && false !== strpos( $with_both, 'Mango launch' ) && false !== strpos( $with_both, '2 people joined.' ),
	substr_count( $with_both, 'value="' . $sevent_past . '"' ),
	strpos( $with_both, 'Ines' ),
), array( true, 0, false ) );

$GLOBALS['pmeta'][ $sevent_next ]['_wpcpm_sevent_state'] = 'cancelled';
$past_only = between( card( $T, $A, $context ), 'id="wpcpm-sponsor-events"', '</section>' );
ck( 'with nothing still to take place the form is drawn again, above the events that took place', array(
	before( $past_only, 'name="action" value="wpcpm_sevent_plan"', '<h4 class="wpcpm-sponsor__subheading">Past events</h4>' ),
	strpos( $past_only, 'wpcpm-sevent__own' ),
), array( true, false ) );
$past_only_page = card( $T, $A, $context );
ck( 'and the card folds again, its row saying no event is planned and offering Plan an event: an event that took place is no event planned', array( is_open( $past_only_page, 'events' ), row_state( $past_only_page, 'events' ), row_action( $past_only_page, 'events' ) ), array( false, 'No event planned', $plan_an_event ) );
// With a term, the event that took place in it spends the term: the sponsor still holds the planner,
// its card drawn and folded with nothing to come, and the planner says so in the form's place, so the
// row offers no Plan an event, a plan the form would not take.
set_tier( $A, 'champion', day_from_today( 100 ) );
$term_used_page = card( $T, $A, $context );
set_tier( $A, 'champion' );
ck( 'a Champion whose event for the term took place keeps the card, folded, and its row offers no Plan an event, a plan the planner would refuse, saying so in the form\'s place', array( false !== strpos( $term_used_page, 'id="wpcpm-sponsor-events"' ), is_open( $term_used_page, 'events' ), row_state( $term_used_page, 'events' ), row_action( $term_used_page, 'events' ), false !== strpos( $term_used_page, 'Your event for this term has taken place already.' ) ), array( true, false, 'No event planned', null, true ) );

// An event is offered only while its sponsor's tier includes events, and the sponsor's card shows it
// as no longer offered once the tier does not. A Champion whose term ended yesterday reads as a
// Friend, a tier with no events, yet its approved event is still to take place.
$sevent_kept = seed_sevent( $A, 'approved', strtotime( '2026-11-02 15:00:00 UTC' ), 60, 'Mango after the term', array( 42 ) );
set_tier( $A, 'champion', day_from_today( -1 ) );
$lapsed      = card( $T, $A, $context );
ck( 'a sponsor that no longer holds the planner keeps its event\'s card open, the row naming the event and how many joined, and offers no Plan an event', array( is_open( $lapsed, 'events' ), row_state( $lapsed, 'events' ), row_action( $lapsed, 'events' ) ), array( true, 'Mango after the term on November 2, 2026, 1 joined', null ) );
$lapsed_card = between( $lapsed, 'id="wpcpm-sponsor-events"', '</section>' );
$GLOBALS['pmeta'][ $sevent_kept ]['_wpcpm_sevent_state'] = 'cancelled';
$lapsed_none = card( $T, $A, $context );
ck( 'a sponsor whose tier no longer has events keeps the card while an event of its own is to take place: no list, no sentence, no form, the event no longer offered, with Cancel alone', array(
	false !== strpos( $lapsed, 'id="wpcpm-sponsor-events"' ),
	strpos( $lapsed_card, 'wpcpm-sponsorship__events' ),
	strpos( $lapsed_card, 'The planner below' ),
	strpos( $lapsed_card, 'value="wpcpm_sevent_plan"' ),
	false !== strpos( $lapsed_card, 'Approved, but no longer offered: ' ),
	false !== strpos( $lapsed_card, 'Cancel the event</button>' ),
	strpos( $lapsed_card, 'wpcpm_sevent_change' ),
	strpos( $lapsed_none, 'id="wpcpm-sponsor-events"' ),
), array( true, false, false, false, true, true, false, false ) );

// Whether the Events card is drawn is the card's own public test, draws_events(), which the At a
// glance tile asks too, so a tile never points at a card the page did not draw: asked in four states
// and read against the card itself.
$draws = static function () use ( $A, $T, $context ) {
	$row  = WPCPM_Sponsors_Index::row( $A );
	$tier = WPCPM_Sponsor_Tiers::effective( $row, WPCPM_Sponsor_Tiers::today() );
	return array(
		false !== strpos( card( $T, $A, $context ), 'id="wpcpm-sponsor-events"' ),
		is_callable( array( 'WPCPM_Sponsor_Tier_Card', 'draws_events' ) ) ? WPCPM_Sponsor_Tier_Card::draws_events( $A, $row, $tier ) : 'draws_events() is not public',
	);
};
set_tier( $A, 'backer' );
$draws_backer = $draws();
set_tier( $A, 'champion' );
$draws_champion = $draws();
set_tier( $A, 'champion', day_from_today( -1 ) );
$draws_lapsed_none  = $draws();
$sevent_draws       = seed_sevent( $A, 'approved', strtotime( '2026-11-04 15:00:00 UTC' ), 60, 'Mango drawn or not', array( 42 ) );
$draws_lapsed_event = $draws();
$GLOBALS['pmeta'][ $sevent_draws ]['_wpcpm_sevent_state'] = 'cancelled';
ck( 'the Events card is drawn exactly when its own public test says so, the one the At a glance tile asks: not for a Backer, for a Champion holding the planner, not for a lapsed Champion with nothing to come, and for one whose event is still to come', array( $draws_backer, $draws_champion, $draws_lapsed_none, $draws_lapsed_event ), array( array( false, false ), array( true, true ), array( false, false ), array( true, true ) ) );

// A Champion set to Paused holds no planner, though its tier still lists events: its card reads its
// approved event as no longer offered, with Cancel alone, and neither the tier's events nor the
// sentence about a planner that is not there; with nothing still to take place there is no card.
set_tier( $A, 'champion' );
WPCPM_Sponsors_Index::patch( $A, array( 'status' => 'Paused' ) );
$sevent_paused = seed_sevent( $A, 'approved', strtotime( '2026-11-03 15:00:00 UTC' ), 60, 'Mango while paused' );
$paused        = card( $T, $A, $context );
$paused_card   = between( $paused, 'id="wpcpm-sponsor-events"', '</section>' );
$GLOBALS['pmeta'][ $sevent_paused ]['_wpcpm_sevent_state'] = 'cancelled';
$paused_none = card( $T, $A, $context );
WPCPM_Sponsors_Index::patch( $A, array( 'status' => 'Approved' ) );
ck( 'a Paused Champion\'s card reads its event as no longer offered, with Cancel alone, and neither the tier\'s events nor the sentence; with nothing to take place, no card', array(
	false !== strpos( $paused, 'id="wpcpm-sponsor-events"' ),
	strpos( $paused_card, 'wpcpm-sponsorship__events' ),
	strpos( $paused_card, 'The planner below' ),
	strpos( $paused_card, 'value="wpcpm_sevent_plan"' ),
	false !== strpos( $paused_card, 'Approved, but no longer offered: ' ),
	false !== strpos( $paused_card, 'Cancel the event</button>' ),
	strpos( $paused_card, 'wpcpm_sevent_change' ),
	strpos( $paused_none, 'id="wpcpm-sponsor-events"' ),
	WPCPM_Sponsors_Index::row( $A )['status'],
), array( true, false, false, false, true, true, false, false, 'Approved' ) );

foreach ( array( $sevent_next, $sevent_past, $sevent_kept, $sevent_paused ) as $sevent_id ) {
	unset( $GLOBALS['posts'][ $sevent_id ], $GLOBALS['pmeta'][ $sevent_id ] );
}

set_tier( $A, 'supporter', day_from_today( 100 ) );
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array();
$r       = post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) );
$history = explode( "\n", WPCPM_Sponsors_Index::row( $A )['interests'] );
// The request is the line's note, not one of its events, so the audit row counts no event.
ck( 'Talk to your program contact files an interest line naming the next tier, mails the manager and leaves with tier-sent', array(
	$r,
	end( $history ),
	count( $GLOBALS['sent'] ), $GLOBALS['sent'][0][0], $GLOBALS['sent'][0][1], $GLOBALS['sent'][0][2],
	end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['ground'], end( $GLOBALS['audit'] )['data']['events'],
), array(
	array( 'tier-sent', 'sponsorship', $A ),
	'2026-10-06 by Rep One: note: wants to move to Backer',
	1, 'user', 1, 'sponsor-interest',
	'sponsor_interest', 'member', 0,
) );

// The Talk button files its line through the interests card's file(), whose mail reads each entity
// back (`WPCPM_Typed_Text::mail_text()`): a display name WordPress stored with its "&" escaped, as
// it stores every display name, mails as the person wrote it, while the history keeps the line as
// stored, as every line before it.
$GLOBALS['users'][ $GLOBALS['uid'] ]->display_name = 'Bob &amp; Co';
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array();
$r       = post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) );
$history = explode( "\n", WPCPM_Sponsors_Index::row( $A )['interests'] );
$GLOBALS['users'][ $GLOBALS['uid'] ]->display_name = 'Rep One';
ck( 'Talk to your program contact mails a display name stored as "Bob &amp; Co" as "Bob & Co", and the history keeps the line as stored', array(
	$r,
	isset( $GLOBALS['sent'][0][3]['body'] ) ? $GLOBALS['sent'][0][3]['body'] : null,
	end( $history ),
), array(
	array( 'tier-sent', 'sponsorship', $A ),
	"Mango Example said on the Sponsor Dashboard:\n\n2026-10-06 by Bob & Co: note: wants to move to Backer\n\nThe full history is on the Sponsors screen.",
	'2026-10-06 by Bob &amp; Co: note: wants to move to Backer',
) );

// A term that has ended asks for its renewal, not a downgrade: the
// Backer recorded ranks above the Friend the row reads as, so the card offers the
// renewal in place of what Supporter adds, and the press names the Backer term.
set_tier( $A, 'backer', day_from_today( -1 ) );
$html    = card( $T, $A, $context );
$r       = post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) );
$history = explode( "\n", WPCPM_Sponsors_Index::row( $A )['interests'] );
ck( 'a lapsed Backer\'s press asks for a renewal as a Backer, not a move to Supporter', array(
	$r,
	end( $history ),
	false !== strpos( $html, '<button type="submit" class="wpcpm-button wpcpm-button--secondary">Talk to your program contact</button></form><p class="wpcpm-student__note">One press tells your program contact you would like to renew your term.</p>' ),
	strpos( $html, ' adds ' ),
	strpos( $html, 'talk about the next tier' ),
), array( array( 'tier-sent', 'sponsorship', $A ), '2026-10-06 by Rep One: note: wants to renew as Backer', true, false, false ) );

set_tier( $A, 'champion' );
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array();
$GLOBALS['settings'] = array();
$kept   = array( WPCPM_Sponsors_Index::row( $A ), $GLOBALS['sent'], $GLOBALS['audit'], $GLOBALS['buckets'] );
$r_talk = post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) );
ck( 'a press with the switch off leaves with tier-off and writes nothing', array(
	$r_talk,
	array( WPCPM_Sponsors_Index::row( $A ), $GLOBALS['sent'], $GLOBALS['audit'], $GLOBALS['buckets'] ) === $kept,
), array( array( 'tier-off', 'sponsorship', $A ), true ) );
$GLOBALS['settings']['sponsor_tiers_live'] = true;

set_tier( $A, 'partner' );
$kept      = array( WPCPM_Sponsors_Index::row( $A ), $GLOBALS['sent'], $GLOBALS['audit'], $GLOBALS['buckets'] );
$r_partner = post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) );
ck( 'a Partner has no next tier to talk about: its press leaves with tier-off and writes nothing', array(
	$r_partner,
	array( WPCPM_Sponsors_Index::row( $A ), $GLOBALS['sent'], $GLOBALS['audit'], $GLOBALS['buckets'] ) === $kept,
), array( array( 'tier-off', 'sponsorship', $A ), true ) );

set_tier( $A, 'champion' );
$GLOBALS['buckets'] = array();
$presses = array(
	post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'first of five' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) )[0],
	post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) )[0],
	post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) )[0],
	post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) )[0],
	post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) )[0],
	post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) )[0],
	post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'the seventh' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) )[0],
);
ck( 'Talk to your program contact shares the interests card\'s ceiling', array( $presses, $GLOBALS['buckets'] ), array(
	array( 'interest-sent', 'tier-sent', 'tier-sent', 'tier-sent', 'tier-sent', 'tier-ceiling', 'interest-ceiling' ),
	array( 'sponsor-interest:5' => 5 ),
) );

// The roads a press can end on besides the ones above: a stranger's claim is refused (and lands
// with no sponsor named), a member whose sponsor left the index has no row to write back to, a
// store that refuses the write is said, and nobody to mail is said as such.
$GLOBALS['buckets'] = array();
$GLOBALS['uid']     = 9;
$refused            = post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) );
$GLOBALS['uid']     = 6;
$left_index         = post( array( 'wpcpm_sponsor' => 'recSPONSOR0000009' ), array( $T, 'handle_talk' ) );
$GLOBALS['uid']     = 5;
WPCPM_Sponsors_Index::row( $A );
$a_post_id = WPCPM_Sponsors_Index::post_id_of( $A );
$a_post    = $GLOBALS['posts'][ $a_post_id ];
unset( $GLOBALS['posts'][ $a_post_id ] );
$store_refused = post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) );
$GLOBALS['posts'][ $a_post_id ] = $a_post;
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => '' ) );
$GLOBALS['managers_reachable'] = 0;
$unsent = post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) );
unset( $GLOBALS['managers_reachable'] );
ck( 'a stranger is refused, a sponsor the index does not hold or a store that refuses fails, and nobody to mail is said', array( $refused, $left_index, $store_refused, $unsent ), array(
	array( 'refused', 'sponsorship', '' ),
	array( 'tier-failed', 'sponsorship', 'recSPONSOR0000009' ),
	array( 'tier-failed', 'sponsorship', $A ),
	array( 'tier-unsent', 'sponsorship', $A ),
) );

$GLOBALS['hooks'] = array();
WPCPM_Sponsor_Tier_Card::init();
$GLOBALS['buckets'] = array();
post( array( 'wpcpm_sponsor' => $A ), array( $T, 'handle_talk' ) );
$talk_nonce = $GLOBALS['referer'];
ck( 'the module boots the card, the card hooks its one button and no Plan it now, and the press checks a nonce keyed to the sponsor', array(
	1 === preg_match( "/foreach \( array\([^)]*'WPCPM_Sponsor_Tier_Card'[^)]*\) as \\\$front \)/", (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' ) ),
	isset( $GLOBALS['hooks']['admin_post_wpcpm_tier_talk'] ) ? $GLOBALS['hooks']['admin_post_wpcpm_tier_talk'] : null,
	isset( $GLOBALS['hooks']['admin_post_wpcpm_tier_plan'] ) ? $GLOBALS['hooks']['admin_post_wpcpm_tier_plan'] : null,
	method_exists( $T, 'handle_plan' ),
	$talk_nonce,
), array( true, array( $T, 'handle_talk' ), null, false, 'wpcpm_tier_talk_' . $A ) );

$tier_card_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tier-card.php' );
ck( 'the tier card holds no em or en dash, has a sentence for every status its handlers flash, and refuses in the interests card\'s words', array(
	preg_match( '/\x{2013}|\x{2014}/u', $tier_card_src ),
	array_values( array_diff( array( 'tier-sent', 'tier-unsent', 'tier-ceiling', 'tier-failed', 'tier-off', 'refused' ), array_keys( WPCPM_Sponsor_Tier_Card::messages() ) ) ),
	WPCPM_Sponsor_Tier_Card::messages()['refused'],
), array( 0, array(), WPCPM_Sponsor_Interests::messages()['refused'] ) );

set_tier( $A, '' );
$GLOBALS['settings'] = array();
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array();

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
ck( 'two cards: Mentors you sponsor and Mentors looking for a sponsor, each its own section and disclosure', array( substr_count( $html, '<section class="wpcpm-sponsor__card">' ), false !== strpos( $html, 'id="wpcpm-sponsor-mentors"' ), false !== strpos( $html, 'id="wpcpm-sponsor-looking"' ), strpos( $html, 'id="wpcpm-sponsor-mentors"' ) < strpos( $html, 'id="wpcpm-sponsor-looking"' ), strpos( $html, 'wpcpm-sponsor__subheading' ) ), array( 2, true, true, true, false ) );
ck( 'the summaries count the linked records and the mentors looking', array( false !== strpos( $html, 'Mentors you sponsor <span class="wpcpm-group__count">3</span>' ), false !== strpos( $html, 'Mentors looking for a sponsor <span class="wpcpm-group__count">1</span>' ) ), array( true, true ) );
// The company's own mentors are live state and open whatever a flash says; the list of mentors
// looking for a sponsor is long and folds until a flash names it (the design the owner approved on
// 27 September 2026). Each row says how many, in words, and neither offers an action: the one press
// is on each looking mentor's tile.
$linked_before = WPCPM_Sponsors_Index::row( $A )['mentors'];
WPCPM_Sponsors_Index::patch( $A, array( 'mentors' => array() ) );
$none_linked = card( 'WPCPM_Sponsor_Mentors', $A, $context );
WPCPM_Sponsors_Index::patch( $A, array( 'mentors' => array( $M1 ) ) );
$one_linked = card( 'WPCPM_Sponsor_Mentors', $A, $context );
WPCPM_Sponsors_Index::patch( $A, array( 'mentors' => $linked_before ) );
$looking_flashed = card( 'WPCPM_Sponsor_Mentors', $A, array_merge( $context, array( 'open' => 'looking' ) ) );
ck( 'Mentors you sponsor is open, its row how many mentors, or none yet; Mentors looking for a sponsor folds until a flash names it, its row how many are looking; neither offers an action', array(
	false !== strpos( $html, '<details id="wpcpm-sponsor-mentors" class="wpcpm-group wpcpm-group__disclosure" open><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Mentors you sponsor <span class="wpcpm-group__count">3</span></h3><span class="wpcpm-sponsor__state">3 mentors</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>' ),
	false !== strpos( $html, '<details id="wpcpm-sponsor-looking" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Mentors looking for a sponsor <span class="wpcpm-group__count">1</span></h3><span class="wpcpm-sponsor__state">1 looking</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>' ),
	array( is_open( $none_linked, 'mentors' ), row_state( $none_linked, 'mentors' ), false !== strpos( $none_linked, 'Mentors you sponsor <span class="wpcpm-group__count">0</span>' ), row_state( $one_linked, 'mentors' ) ),
	array( is_open( $looking_flashed, 'mentors' ), is_open( $looking_flashed, 'looking' ) ),
	array( row_action( $html, 'mentors' ), row_action( $html, 'looking' ), substr_count( $html, 'wpcpm-sponsor__row-action' ) ),
	strpos( $html, 'Your mentors' ),
), array( true, true, array( true, 'None yet', true, '1 mentor' ), array( true, true ), array( null, null, 0 ), false ) );
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
// A sponsor reads the note, so it names no sync: "program sync" until 1.111.0, then "mentors sync".
ck( 'before the mentors sync has filled the index the card says so, instead of calling every linked mentor inactive', array( false !== strpos( $stale, 'The mentor list refreshes with the next update of the mentor records.' ), strpos( $stale, 'not currently active' ) ), array( true, false ) );
ck( 'and the summary still counts the linked records', false !== strpos( $stale, '<span class="wpcpm-group__count">3</span>' ), true );
$GLOBALS['sponsorship'] = $saved_sponsorship;
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array();
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => '1' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_mentor' => $M3 ), array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
ck( 'interest in a mentor is sent to the manager under its own id, a sponsor\'s interest\'s until 1.122.22, and logged, and lands on the looking card', array( $r[0], $r[1], $GLOBALS['sent'][0][2], end( $GLOBALS['audit'] )['kind'] ), array( 'mentor-interest-sent', 'looking', 'sponsor-mentor-interest', 'sponsor_interest_mentor' ) );
ck( 'the mail names the mentor', false !== strpos( $GLOBALS['sent'][0][3]['body'], 'Ana Looking' ), true );
ck( 'and its subject names the site, as every other email does', $GLOBALS['sent'][0][3]['subject'], '[WP Credits] Mango Example would like to sponsor Ana Looking' );
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

$GLOBALS['summary'] = array( 'state' => 'none', 'kind' => '', 'accepted_at' => '', 'agreement_id' => 0, 'pending_id' => 0, 'drive_url' => '' );
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
// The one card whose body carries a class of its own beside the shared one, kept through the shell.
ck( 'the card is the section around its disclosure, keyed to its anchor, its row the state, and its body keeps the agreement\'s own class', array(
	0 === strpos( $html, '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-agreement" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Collaboration Agreement</h3><span class="wpcpm-sponsor__state">On file with the program since September 6, 2026</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary><div class="wpcpm-group__body wpcpm-agreement">' ),
	substr( $html, -strlen( '</div></details></section>' ) ),
), array( true, '</div></details></section>' ) );
// The row a folded agreement card shows (the design the owner approved on 27 September 2026, whose
// own example is "Accepted on 3 September 2026"): the state in the pill's own words, with the day
// for the two states that have one, written the way the site writes a day, and Upload after the
// fold only where the state asks the company for a file: an agreement sent back, or one no longer
// in force. Nothing on file asks for none, since nothing on the dashboard depends on an agreement.
// Folded until a flash names it.
$agr_upload = '<a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-agreement">Upload<span class="screen-reader-text"> the signed Collaboration Agreement</span></a>';
$agr_rows   = array();
foreach ( array( 'none', 'submitted', 'returned', 'revoked', 'accepted', 'on_file' ) as $agr_state ) {
	$GLOBALS['summary']       = array_merge( $GLOBALS['summary'], array( 'state' => $agr_state, 'accepted_at' => in_array( $agr_state, array( 'accepted', 'on_file' ), true ) ? '2026-09-06' : '' ) );
	$agr_html                 = agreement_card( $A );
	$agr_rows[ $agr_state ]   = array( row_state( $agr_html, 'agreement' ), row_action( $agr_html, 'agreement' ), is_open( $agr_html, 'agreement' ) );
}
ob_start();
WPCPM_Sponsor_Agreement_Card::render( $A, array( 'can_manage' => false, 'open' => 'agreement', 'viewer' => wp_get_current_user() ) );
$agr_flashed = (string) ob_get_clean();
ck( 'each state\'s row, with the day where there is one and Upload where the state asks for a file, folded until a flash names it', array( $agr_rows, is_open( $agr_flashed, 'agreement' ) ), array(
	array(
		'none'      => array( 'Nothing on file', null, false ),
		'submitted' => array( 'Waiting for the program', null, false ),
		'returned'  => array( 'Sent back to you', $agr_upload, false ),
		'revoked'   => array( 'Out of force', $agr_upload, false ),
		'accepted'  => array( 'Accepted on September 6, 2026', null, false ),
		'on_file'   => array( 'On file with the program since September 6, 2026', null, false ),
	),
	true,
) );
$agr_before         = $GLOBALS['summary'];
$GLOBALS['summary'] = array_merge( $GLOBALS['summary'], array( 'state' => 'returned', 'accepted_at' => '' ) );
ob_start();
WPCPM_Sponsor_Agreement_Card::render( $A, array( 'can_manage' => false, 'open' => 'agreement', 'viewer' => wp_get_current_user() ) );
$agr_returned_open  = (string) ob_get_clean();
$GLOBALS['summary'] = $agr_before;
ck( 'an agreement sent back that a flash opened holds its upload form on screen and still prints Upload after its fold, which the stylesheets hide while the card is open', array( is_open( $agr_returned_open, 'agreement' ), row_action( $agr_returned_open, 'agreement' ), false !== strpos( $agr_returned_open, 'name="wpcpm_sponsor_agr_file"' ) ), array( true, $agr_upload, true ) );
ck( 'the dashboard\'s message map now finds this card\'s words', isset( WPCPM_Sponsors_Dashboard::messages()['agreement-uploaded'] ), true );

$card_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-agreement-card.php' );
ck( 'no em or en dash in the card', preg_match( '/\x{2013}|\x{2014}/u', $card_src ), 0 );
ck( 'the card decides nothing: no policy call, no write', preg_match( '/update_post_meta|update_option|WPCPM_Airtable/', $card_src ), 0 );

echo "\n=== The Reports card: a sponsor's final annual reports (1.115.0) ===\n";
// The store and the files class are the real ones, reading the report posts through the
// get_posts() stand-in above, which keeps a report to its own sponsor. The card's own file is
// loaded only when it is there, so that before it exists every check below fails as a check
// rather than the whole suite as a fatal.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-files.php';
$report_card_file = __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-card.php';
if ( file_exists( $report_card_file ) ) {
	require_once $report_card_file;
}

/**
 * The Reports card's markup for one sponsor, or '' while the class is not there.
 *
 * @param string $record  Sponsor key.
 * @param array  $context What the dashboard hands every card.
 * @return string
 */
function report_card( $record, array $context ) {
	return class_exists( 'WPCPM_Sponsor_Report_Card' ) ? card( 'WPCPM_Sponsor_Report_Card', $record, $context ) : '';
}

/**
 * One report post as the store writes one: its sponsor, its last day, its state and, when a name
 * is given, a PDF recorded as the files class records one, under the name a manager uploaded.
 *
 * @param string $record    Sponsor key.
 * @param string $until     The term's last day.
 * @param string $title     The title: the sponsor's name and the months the term runs.
 * @param string $state     `draft` or `final`.
 * @param string $file_name The uploaded file's name, or '' for no PDF.
 * @return int The post ID.
 */
function seed_report( $record, $until, $title, $state, $file_name = '' ) {
	$id = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Report::POST_TYPE, 'post_status' => 'private', 'post_title' => $title ) );
	update_post_meta( $id, WPCPM_Sponsor_Report::META_SPONSOR, $record );
	update_post_meta( $id, WPCPM_Sponsor_Report::META_UNTIL, $until );
	update_post_meta( $id, WPCPM_Sponsor_Report::META_STATE, $state );
	if ( '' !== $file_name ) {
		update_post_meta( $id, WPCPM_Sponsor_Report::META_FILE, array( 'path' => 'sreport-' . $id . '.bin', 'sha256' => str_repeat( 'a', 64 ), 'size' => 2048 ) );
		update_post_meta( $id, WPCPM_Sponsor_Report::META_FILE_NAME, $file_name );
	}
	return (int) $id;
}

// A (Mango Example) has a draft first, while another sponsor already has a final report of its
// own; A's finals come after, a student's name planted in one's narratives and in the name the
// other's PDF was uploaded under, and a title holding markup to be escaped.
$RB       = 'spoAAAA0000000002';
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$r_draft  = seed_report( $A, '2027-09-30', 'Mango Example, October 2026 to September 2027', 'draft', 'draft-copy.pdf' );
$r_other  = seed_report( $RB, '2026-09-30', 'Other Example, October 2025 to September 2026', 'final', 'other.pdf' );
$drafts_only = report_card( $A, $context );
$other_on    = report_card( $RB, $context );
$r_new    = seed_report( $A, '2026-09-30', 'Mango Example, October 2025 to September 2026', 'final' );
update_post_meta( $r_new, WPCPM_Sponsor_Report::META_SECTIONS, array( 'ahead' => 'Student One and Student Two start with a new mentor in October.' ) );
$r_old    = seed_report( $A, '2025-09-30', 'Mango Example, October 2024 to September 2025', 'final', 'Student One transcript.pdf' );
$r_marked = seed_report( $A, '2024-09-30', '<b>Mango</b> & Example, October 2023 to September 2024', 'final' );
$GLOBALS['settings'] = array();
// The switch is read before the store: while it is off, no sponsor's dashboard load asks for its
// reports at all, so the store's stand-in must not have been asked either.
unset( $GLOBALS['last_get_posts'] );
$off      = report_card( $A, $context );
$off_read = isset( $GLOBALS['last_get_posts'] );
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$on     = report_card( $A, $context );
$opened = report_card( $A, array_merge( $context, array( 'open' => 'reports' ) ) );
$other  = report_card( $RB, $context );

ck( 'the card prints nothing while the switch is off, without reading a report to learn it, and nothing for a sponsor with drafts only, whatever another sponsor has; with a final report and the switch on it is drawn', array( $off, $off_read, $drafts_only, false !== strpos( $on, 'id="wpcpm-sponsor-reports"' ) ), array( '', false, '', true ) );

// Folded, its row how many yearly reports there are, the final ones a sponsor reads, and which term
// the latest is for, in the months the report's own title names; no action, since Open and PDF belong
// to each report (the design the owner approved on 27 September 2026).
$shell_open  = '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-reports" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Reports</h3><span class="wpcpm-sponsor__state">3 yearly reports, the latest for October 2025 to September 2026</span><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary><div class="wpcpm-group__body">';
$shell_close = '</ul></div></details></section>';
ck( 'it is the canonical disclosure, keyed wpcpm-sponsor-reports, headed Reports, folded until a flash names it and open when one does', array(
	0 === strpos( $on, $shell_open ),
	substr( $on, -strlen( $shell_close ) ) === $shell_close,
	substr_count( $on, ' open>' ),
	false !== strpos( $opened, '<details id="wpcpm-sponsor-reports" class="wpcpm-group wpcpm-group__disclosure" open>' ) && 1 === substr_count( $opened, ' open>' ),
), array( true, true, 0, true ) );

// The note's words are the owner's to change, so the check reads where the note stands and that it
// says something, not the sentence itself.
preg_match( '#<p class="wpcpm-student__note">(.*?)</p>#s', $on, $report_note );
ck( 'a note opens the card\'s body, in words of its own, and the list follows it', array(
	false !== strpos( $on, '<div class="wpcpm-group__body"><p class="wpcpm-student__note">' ),
	isset( $report_note[1] ) && '' !== trim( $report_note[1] ),
	before( $on, '<p class="wpcpm-student__note">', '<ul class="wpcpm-sponsor-reports__list">' ),
), array( true, true, true ) );

$print_new = 'https://example.test/wp-admin/admin-post.php?action=wpcpm_sreport_print&report=' . $r_new . '&_wpnonce=wpcpm_sreport_print_' . $r_new;
$print_old = 'https://example.test/wp-admin/admin-post.php?action=wpcpm_sreport_print&report=' . $r_old . '&_wpnonce=wpcpm_sreport_print_' . $r_old;
$pdf_old   = 'https://example.test/wp-admin/admin-post.php?action=wpcpm_sreport_pdf&report=' . $r_old . '&_wpnonce=wpcpm_sreport_pdf_' . $r_old;
ck( 'each final report is one item: its title, Open to the printed document keyed to that report in a new tab, and PDF to the attached file only where one is attached', array(
	false !== strpos( $on, '<li class="wpcpm-sponsor-reports__item"><span class="wpcpm-sponsor-reports__title">Mango Example, October 2025 to September 2026</span> <a class="wpcpm-button wpcpm-button--secondary" href="' . $print_new . '" target="_blank" rel="noopener">Open<span class="screen-reader-text"> Mango Example, October 2025 to September 2026</span></a></li>' ),
	false !== strpos( $on, '<li class="wpcpm-sponsor-reports__item"><span class="wpcpm-sponsor-reports__title">Mango Example, October 2024 to September 2025</span> <a class="wpcpm-button wpcpm-button--secondary" href="' . $print_old . '" target="_blank" rel="noopener">Open<span class="screen-reader-text"> Mango Example, October 2024 to September 2025</span></a> <a class="wpcpm-button wpcpm-button--secondary" href="' . $pdf_old . '">PDF<span class="screen-reader-text"> Mango Example, October 2024 to September 2025</span></a></li>' ),
	$print_new === WPCPM_Sponsor_Report_Files::print_url( get_post( $r_new ) ) && $pdf_old === WPCPM_Sponsor_Report_Files::download_url( get_post( $r_old ) ),
), array( true, true, true ) );

ck( 'the sponsor\'s final reports alone, the newest term first: no draft, and nothing of another sponsor\'s', array(
	substr_count( $on, '<li class="wpcpm-sponsor-reports__item">' ),
	before( $on, 'October 2025 to September 2026', 'October 2024 to September 2025' ) && before( $on, 'October 2024 to September 2025', 'October 2023 to September 2024' ),
	strpos( $on, 'October 2026 to September 2027' ),
	strpos( $on, 'report=' . $r_draft . '&' ),
	strpos( $on, 'Other Example' ),
	strpos( $on, 'report=' . $r_other . '&' ),
), array( 3, true, false, false, false, false ) );

ck( 'and the other sponsor\'s card, drawn before A had any final report and after, lists its own report and none of A\'s', array(
	substr_count( $other_on, '<li class="wpcpm-sponsor-reports__item">' ),
	$other_on === $other,
	false !== strpos( $other, 'Other Example, October 2025 to September 2026' ),
	strpos( $other, 'Mango' ),
), array( 1, true, true, false ) );
// One of a thing is one, and the row, the list and the At a glance tile read one list: the card's
// own finals(), the final reports alone, the newest term first.
ck( 'one yearly report is one, and finals() is the card\'s list: the final reports alone, newest term first', array(
	row_state( $other, 'reports' ),
	is_callable( array( 'WPCPM_Sponsor_Report_Card', 'finals' ) ) ? array_map( static function ( $post ) { return (int) $post->ID; }, WPCPM_Sponsor_Report_Card::finals( $A ) ) : 'finals() is not public',
	is_callable( array( 'WPCPM_Sponsor_Report_Card', 'finals' ) ) ? WPCPM_Sponsor_Report_Card::finals( 'recNOREPORTS00001' ) : 'finals() is not public',
), array( '1 yearly report, the latest for October 2025 to September 2026', array( $r_new, $r_old, $r_marked ), array() ) );
// A report whose last day was lost is still listed, and its row counts it without naming a term it
// cannot read, rather than a sentence with the months left blank.
$RC      = 'spoAAAA0000000003';
$r_blank = seed_report( $RC, 'not-a-day', 'Blank Example, a report with no period', 'final' );
ck( 'a report whose last day is unreadable is counted, and no term is named', row_state( report_card( $RC, $context ), 'reports' ), '1 yearly report' );
unset( $GLOBALS['posts'][ $r_blank ], $GLOBALS['pmeta'][ $r_blank ] );

ck( 'a title is escaped, in the item and in the words a screen reader hears', array(
	false !== strpos( $on, '<span class="wpcpm-sponsor-reports__title">&lt;b&gt;Mango&lt;/b&gt; &amp; Example, October 2023 to September 2024</span>' ),
	false !== strpos( $on, '<span class="screen-reader-text"> &lt;b&gt;Mango&lt;/b&gt; &amp; Example, October 2023 to September 2024</span>' ),
	strpos( $on, '<b>' ),
), array( true, true, false ) );

// The narratives and the uploaded file's name are the two places a student's name could reach a
// report; the card prints neither, and the PDF button's words are the site's, never the file's.
ck( 'no student is named on the card, nor the name a PDF was uploaded under', array(
	false !== strpos( $on, '<ul class="wpcpm-sponsor-reports__list">' ),
	strpos( $on, 'Student' ),
	strpos( $on, 'transcript' ),
), array( true, false, false ) );

ck( 'the card has no form, so it has no words of its own to merge', class_exists( 'WPCPM_Sponsor_Report_Card' ) ? WPCPM_Sponsor_Report_Card::messages() : null, array() );

$report_card_src = file_exists( $report_card_file ) ? (string) file_get_contents( $report_card_file ) : '';
ck( 'the card file holds no em or en dash, no sponsor key compared with ===, no Airtable, and writes nothing', array(
	'' !== $report_card_src,
	preg_match( '/\x{2013}|\x{2014}/u', $report_card_src ),
	preg_match( '/wpcpm_sponsor(_record_id)?[^;]*===/', $report_card_src ),
	preg_match( '/update_post_meta|update_option|wp_insert_post|wp_update_post|WPCPM_Airtable/', $report_card_src ),
), array( true, 0, 0, 0 ) );

$GLOBALS['settings'] = array();
foreach ( array( $r_draft, $r_other, $r_new, $r_old, $r_marked ) as $report_id ) {
	unset( $GLOBALS['posts'][ $report_id ], $GLOBALS['pmeta'][ $report_id ] );
}

echo "\n=== House rules ===\n";
$all = '';
foreach ( array( 'profile', 'interests', 'mentors' ) as $f ) { $all .= file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-' . $f . '.php' ); }
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $all ), 0 );
ck( 'every handler claims before it writes', substr_count( $all, 'WPCPM_Sponsor_Roster::claim(' ) >= 3, true );
ck( 'the messages maps cover every status the handlers flash', array_values( array_diff( array( 'profile-saved', 'profile-unchanged', 'profile-rejected', 'profile-too-long', 'profile-text-long', 'profile-long', 'profile-failed', 'interest-sent', 'interest-unsent', 'interest-empty', 'interest-long', 'interest-ceiling', 'interest-failed', 'mentor-interest-sent', 'mentor-interest-unknown', 'mentor-interest-ceiling', 'mentor-interest-failed', 'refused' ), array_merge( array_keys( WPCPM_Sponsor_Profile::messages() ), array_keys( WPCPM_Sponsor_Interests::messages() ), array_keys( WPCPM_Sponsor_Mentors::messages() ) ) ) ), array() );


$GLOBALS['summary'] = array_merge( $GLOBALS['summary'], array( 'state' => 'none', 'agreement_id' => 0, 'pending_id' => 0, 'accepted_at' => '' ) );
$none               = agreement_card( $A );
ck( 'the card names the product, and both upload fields carry the Required mark in the label\'s voice', array(
	substr_count( $none, '>Collaboration Agreement<' ),
	substr_count( $none, '<span class="wpcpm-field__required">Required</span>' ),
), array( 1, 2 ) );

// Since 1.111.0 interests keeps its record on the site alone, so the card's history
// read, its store patch and its mail all go through the real store, never Airtable.
ck( 'interest-failed reads the new sentence', WPCPM_Sponsor_Interests::messages()['interest-failed'][1], 'Your message could not be saved right now. Try again later.' );
$interests_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php' );
ck( 'the mail no longer names the base\'s own column', false !== strpos( $interests_src, 'Sponsorship interests column' ), false );
ck( 'no Airtable class exists', class_exists( 'WPCPM_Airtable' ), false );

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
// On this line the history is the sponsor's own row's (`interests`), the line is dated by wp_date(),
// and the mail ends with this line's sentence: the full history is on the Sponsors screen.
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array();
unset( $GLOBALS['managers_reachable'] );
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => 'recTEAM0000000001', 'interests' => '2026-09-01 by Rep One: Sponsor tools or services' ) );
$i_day = wp_date( 'Y-m-d' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $t_note ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a note with "<b>team</b>" in it is refused now, because the cleaner would take "<b>" and "</b>", and nothing is sent or written', array( $r, $GLOBALS['sent'], WPCPM_Sponsors_Index::row( $A )['interests'], $GLOBALS['audit'] ), array( array( 'interest-loss', 'interests', $A ), array(), '2026-09-01 by Rep One: Sponsor tools or services', array() ) );
// The same words without the tag: a "<3" whose "<" opens no tag, a "<" that a ">" closes after a
// space, and a "<" with no ">" after it, the quote marks and the ampersands after it entities too.
$t_sent    = "We <3 our team, all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 < 12. Q&A: \"blocks\" & themes, it's free.";
$t_sent_cl = "We &lt;3 our team, all\r\nAges 8 < 12 welcome, adults > 18 pay.\r\nAges 8 &lt; 12. Q&amp;A: &quot;blocks&quot; &amp; themes, it&#039;s free.";
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $t_sent ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a note with line breaks, a "<3", a "<" and the ampersands after one is sent', $r, array( 'interest-sent', 'interests', $A ) );
ck( 'and its mail says what was typed, line by line, with no entity in it', isset( $GLOBALS['sent'][0][3]['body'] ) ? $GLOBALS['sent'][0][3]['body'] : null, "Mango Example said on the Sponsor Dashboard:\n\n" . $i_day . " by Rep One: note: " . $t_sent . "\n\nThe full history is on the Sponsors screen." );
$i_line  = $i_day . ' by Rep One: note: ' . str_replace( "\r\n", ' ', $t_sent_cl );
$i_story = (string) WPCPM_Sponsors_Index::row( $A )['interests'];
ck( 'the sponsor\'s row keeps one dated line for it, in the form the cleaner stored it, its line breaks one space each: the history is read line by line', array( $i_story, substr_count( $i_story, "\n" ) ), array( '2026-09-01 by Rep One: Sponsor tools or services' . "\n" . $i_line, 1 ) );
ck( 'the audit row carries that one line', end( $GLOBALS['audit'] )['message'], $i_line );
ck( 'and the card lists it as one entry, under the one before it', substr_count( card( 'WPCPM_Sponsor_Interests', $A, $context ), '<li>' ), 2 );

$n_at = cards_filled( "Ages 8 < 12. Q&A: \"blocks\" & themes, it's free.\r\n", 4000 );
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $n_at ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a note of 4,000 characters as typed, with 83 line breaks, is longer than that as the cleaner stores it', array( substr_count( $n_at, "\r\n" ), mb_strlen( sanitize_textarea_field( $n_at ) ) > 4000 ), array( 83, true ) );
ck( 'and is sent whole: the mail carries every character as typed', array( $r[0], isset( $GLOBALS['sent'][0][3]['body'] ) && false !== strpos( $GLOBALS['sent'][0][3]['body'], 'note: ' . $n_at . "\n\n" ) ), array( 'interest-sent', true ) );
ck( 'and so does the history, in the stored form', false !== strpos( (string) WPCPM_Sponsors_Index::row( $A )['interests'], 'note: ' . str_replace( "\r\n", ' ', sanitize_textarea_field( $n_at ) ) ), true );
$GLOBALS['sent'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['left_detail'] = null;
$before_five = $GLOBALS['buckets'];
$i_story     = WPCPM_Sponsors_Index::row( $A )['interests'];
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $n_at . 'x' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'one character more is refused, with a sentence that says how much to take out', array( $r, $GLOBALS['left_detail'] ), array( array( 'interest-long', 'interests', $A ), 'Your note is 1 character over the limit of 4000. Shorten it and send it again.' ) );
ck( 'and nothing is written, mailed or logged, and none of the day\'s five is spent', array( WPCPM_Sponsors_Index::row( $A )['interests'] === $i_story, $GLOBALS['sent'], $GLOBALS['audit'], $GLOBALS['buckets'] ), array( true, array(), array(), $before_five ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => $n_at . 'xyz' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'three more says three', $GLOBALS['left_detail'], 'Your note is 3 characters over the limit of 4000. Shorten it and send it again.' );
ck( 'the messages map words the refusal', WPCPM_Sponsor_Interests::messages()['interest-long'] ?? null, array( 'error', 'Nothing was sent.' ) );

echo "\n=== The Contact person: counted, drawn and kept as typed ===\n";
// On this line a save is seen in its audit row: the store is the one copy, and nothing else is written.
$GLOBALS['audit'] = array();
$c_before = WPCPM_Sponsors_Index::row( $A )['contact_person'];
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_contact_person' => $t_team ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a contact person with "<b>team</b>" in it is refused now, because the cleaner would take "<b>" and "</b>", and nothing is written', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['contact_person'] === $c_before ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Contact person' ), array(), true ) );
card( 'WPCPM_Sponsor_Profile', $A, $context );
// The form an earlier release stored that typing in, which the row holds still: a "<3" written
// `&lt;3` and a lone ">" after it, the box below draws it back with the ">" held.
WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => $t_team_cl ) );
$box = cards_contact_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'its box shows "We <3 our team &gt; all", the ">" held as four characters, and leaves room for the three', array( $box['markup'] ?? null, $box['shown'] ?? null, $box['maxlength'] ?? null ), array( 'We &lt;3 our team &amp;gt; all', 'We <3 our team &gt; all', 203 ) );
$c_writes = count( $GLOBALS['audit'] );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_contact_person' => $box['shown'] ?? '' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'posted back unedited it is the stored text: nothing is written, and every word stays', array( $r[0], count( $GLOBALS['audit'] ), WPCPM_Sponsors_Index::row( $A )['contact_person'] ), array( 'profile-unchanged', $c_writes, $t_team_cl ) );

$c_at = cards_filled( 'Ages 8 < 12. Q&A: "blocks" & themes, it\'s free. ', 200 );
$GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_contact_person' => $c_at ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a contact person of 200 characters as typed, longer than that as stored, is kept whole', array( $r[0], WPCPM_Sponsors_Index::row( $A )['contact_person'] === sanitize_text_field( $c_at ), mb_strlen( sanitize_text_field( $c_at ) ) > 200, end( $GLOBALS['audit'] )['data']['fields'] ?? null ), array( 'profile-saved', true, true, array( 'contact_person' ) ) );
ck( 'and its box is drawn with the room it was counted in', cards_contact_box( card( 'WPCPM_Sponsor_Profile', $A, $context ) )['maxlength'] ?? null, 200 );
$GLOBALS['audit'] = array(); $GLOBALS['left_detail'] = null;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_contact_person' => $c_at . 'x', 'wpcpm_product_type' => 'Service' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'one character more refuses the whole save, with a sentence that names the field and how much to take out', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['product_type'] ), array( 'profile-long', '"Contact person" is 1 character over the limit of 200. Shorten it and save again.', array(), 'Plugin' ) );
ck( 'the messages map words the refusal', WPCPM_Sponsor_Profile::messages()['profile-long'] ?? null, array( 'error', 'Nothing was saved.' ) );
// The offer line is the Offers card's on this line, not one of the profile's fields.
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => $c_at ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'the offer line is not this card\'s: posted here, it is not read', array( $r[0], array_key_exists( 'offer', WPCPM_Sponsors_Index::row( $A ) ) ), array( 'profile-unchanged', false ) );

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
// This line stores each line break of a text area as one LF, the way the box shows it.
$t_note_lf = str_replace( "\r\n", "\n", $t_note_cl );
$GLOBALS['audit'] = array();
$a_before  = WPCPM_Sponsors_Index::row( $A )['anything'];
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $t_note ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a text with "<b>team</b>" in it is refused now, because the cleaner would take "<b>" and "</b>", and nothing is written', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['anything'] === $a_before ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Anything else you would like to share' ), array(), true ) );
card( 'WPCPM_Sponsor_Profile', $A, $context );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $t_sent ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a text with line breaks, a "<3", a "<" and the ampersands after one is saved with its lines, as the cleaner leaves it', array( $r[0], WPCPM_Sponsors_Index::row( $A )['anything'], end( $GLOBALS['audit'] )['data']['fields'] ?? null ), array( 'profile-saved', str_replace( "\r\n", "\n", $t_sent_cl ), array( 'anything' ) ) );
// The form an earlier release stored the typing with the tag in, which the row holds still: the
// first line's ">" comes after a "<3" written `&lt;3`, and the box below draws it held.
WPCPM_Sponsors_Index::patch( $A, array( 'anything' => $t_note_lf ) );
$area = cards_profile_area( card( 'WPCPM_Sponsor_Profile', $A, $context ), 'anything' );
ck( 'its box shows the words as typed, line by line, the ">" before the first "<" with no closing ">" held, with room for the three', array( $area['shown'] ?? null, $area['maxlength'] ?? null ), array( "We <3 our team &gt; all\nAges 8 < 12 welcome, adults > 18 pay.\nAges 8 < 12. Q&A: \"blocks\" & themes, it's free.", 4003 ) );
$GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $area['posts'] ?? '' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'posted back unedited, every break as CR LF, it is the stored text: nothing is written', array( $r[0], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['anything'] ), array( 'profile-unchanged', array(), $t_note_lf ) );
// A text another store wrote can hold its breaks either way.
WPCPM_Sponsors_Index::patch( $A, array( 'anything' => "Line one\nLine two" ) );
$area = cards_profile_area( card( 'WPCPM_Sponsor_Profile', $A, $context ), 'anything' );
$r    = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $area['posts'] ?? '' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a text kept with LF and posted back with CR LF is the same text', array( $r[0], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['anything'] ), array( 'profile-unchanged', array(), "Line one\nLine two" ) );

$p_at = cards_filled( "Ages 8 < 12. Q&A: \"blocks\" & themes, it's free.\r\n", 4000 );
$r    = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $p_at ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a text of 4,000 characters as typed, with 83 line breaks and longer as stored, is kept whole', array( $r[0], WPCPM_Sponsors_Index::row( $A )['anything'] === str_replace( "\r\n", "\n", sanitize_textarea_field( $p_at ) ), mb_strlen( sanitize_textarea_field( $p_at ) ) > 4000 ), array( 'profile-saved', true, true ) );
ck( 'and its box is drawn with the room it was counted in', cards_profile_area( card( 'WPCPM_Sponsor_Profile', $A, $context ), 'anything' )['maxlength'] ?? null, 4000 );
// This line refuses it with its own sentence, which names the box and its limit
// (`profile-text-long`), with no sentence after it.
$GLOBALS['audit'] = array(); $GLOBALS['left_detail'] = null;
// The character more goes first, so the box's first 4,000 characters are not the text the row holds.
$p_over   = 'y' . $p_at;
$p_stored = WPCPM_Sponsors_Index::row( $A )['anything'];
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => $p_over, 'wpcpm_product_type' => 'Service' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'one character more refuses the whole save, with a sentence that names the field and its limit', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['product_type'] ), array( 'profile-text-long', '', array(), 'Plugin' ) );
$p_form = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'and the card comes back with the typing: Anything else as the first 4,000 characters typed, each break one LF, the product as chosen, and the row as it was', array( $p_form['anything'] === mb_substr( str_replace( "\r\n", "\n", $p_over ), 0, 4000 ), $p_form['anything'] === WPCPM_Typed_Text::typed_text( $p_stored ), $p_form['product_type'], WPCPM_Sponsors_Index::row( $A )['product_type'], WPCPM_Sponsors_Index::row( $A )['anything'] === $p_stored ), array( true, false, 'Service', 'Plugin', true ) );
// How students use it is the Offers card's instructions on this line, not one of the profile's fields.
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_instructions' => $t_note ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'How students use it is not this card\'s: posted here, it is not read', array( $r[0], array_key_exists( 'instructions', WPCPM_Sponsors_Index::row( $A ) ) ), array( 'profile-unchanged', false ) );

echo "\n=== The events: each counted as typed, refused rather than cut, ten at most ===\n";
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array();
unset( $GLOBALS['managers_reachable'] );
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => 'recTEAM0000000001', 'interests' => '2026-09-01 by Rep One: Sponsor tools or services' ) );
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
$GLOBALS['sent'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['left_detail'] = null;
$before_five = $GLOBALS['buckets'];
$i_story     = WPCPM_Sponsors_Index::row( $A )['interests'];
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => "WordCamp US 2027\r\n" . $e_at . 'x' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'one character more is refused with a sentence that names the event and how much to take out', array( $r, $GLOBALS['left_detail'] ), array( array( 'interest-long', 'interests', $A ), 'Event 2 is 1 character over the limit of 120. Shorten it and send it again.' ) );
ck( 'and nothing is written, mailed or logged, and none of the day\'s five is spent', array( WPCPM_Sponsors_Index::row( $A )['interests'] === $i_story, $GLOBALS['sent'], $GLOBALS['audit'], $GLOBALS['buckets'] ), array( true, array(), array(), $before_five ) );
$ten = array();
for ( $i = 1; $i <= 10; $i++ ) { $ten[] = 'WordCamp ' . $i; }
$GLOBALS['sent'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => implode( "\r\n", $ten ) . "\r\n\r\n" ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'ten events are sent, every one of them, a blank line counting for none', array( $r[0], isset( $GLOBALS['sent'][0][3]['body'] ) && false !== strpos( $GLOBALS['sent'][0][3]['body'], 'events: ' . implode( ', ', $ten ) . "\n" ) ), array( 'interest-sent', true ) );
$GLOBALS['sent'] = array(); $GLOBALS['left_detail'] = null;
$before_five = $GLOBALS['buckets'];
$i_story     = WPCPM_Sponsors_Index::row( $A )['interests'];
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => implode( "\r\n", $ten ) . "\r\nWordCamp 11" ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'an eleventh is refused, with a sentence that says how many, rather than dropped without a word', array( $r[0], $GLOBALS['left_detail'], WPCPM_Sponsors_Index::row( $A )['interests'] === $i_story, $GLOBALS['sent'], $GLOBALS['buckets'] ), array( 'interest-long', 'You named 11 events, and the limit is 10. Name the others in another message.', true, array(), $before_five ) );
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
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => "Tab\there\r\nTwo  spaces" ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a tab and a run of spaces are one space in the history, as a line break is; the mail keeps them', array( $r[0], end( $GLOBALS['audit'] )['message'], isset( $GLOBALS['sent'][0][3]['body'] ) && false !== strpos( $GLOBALS['sent'][0][3]['body'], "note: Tab\there\r\nTwo  spaces" ) ), array( 'interest-sent', $i_day . ' by Rep One: note: Tab here Two spaces', true ) );

echo "\n=== A value the base holds over its limit does not refuse a save that did not touch it ===\n";
/** A one-line box of the profile card as the card draws it: what a browser shows and posts, and its maxlength. */
function cards_line_box( $html, $key ) {
	if ( ! preg_match( '/<input type="text" id="wpcpm-profile-' . $key . '" name="wpcpm_' . $key . '" value="([^"]*)" maxlength="(\d+)" \/>/', $html, $m ) ) {
		return array();
	}
	return array( 'shown' => html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'maxlength' => (int) $m[2] );
}
// Written into the store from the program records, which bound neither column: on this line the
// card's one-line text is the Contact person, here of 251 characters, beside a text of 4,050. The
// browser posts a prefilled value as it is, whatever the box's maxlength.
$o_251 = cards_filled( 'One year of hosting free. ', 251 );
$a_4050 = cards_filled( "We can offer licenses.\n", 4050 );
WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => $o_251, 'anything' => $a_4050 ) );
$html  = card( 'WPCPM_Sponsor_Profile', $A, $context );
$o_box = cards_line_box( $html, 'contact_person' );
$a_box = cards_profile_area( $html, 'anything' );
$GLOBALS['audit'] = array(); $GLOBALS['left_detail'] = null;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://plugins.mango-example.test/new/', 'wpcpm_contact_person' => $o_box['shown'] ?? '', 'wpcpm_anything' => $a_box['posts'] ?? '' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'posted back unedited, the contact person of 251 and the text of 4,050 are the stored ones: the website is saved and nothing else is written', array( $r[0], $GLOBALS['left_detail'], end( $GLOBALS['audit'] )['data']['fields'] ?? null, WPCPM_Sponsors_Index::row( $A )['contact_person'] === $o_251, WPCPM_Sponsors_Index::row( $A )['anything'] === $a_4050 ), array( 'profile-saved', '', array( 'website' ), true, true ) );
$GLOBALS['audit'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_contact_person' => ( $o_box['shown'] ?? '' ) . 'y' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'edited, it is measured, and refused', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['audit'] ), array( 'profile-long', '"Contact person" is 52 characters over the limit of 200. Shorten it and save again.', array() ) );

echo "\n=== Anything else and the events: a typing the cleaner would take words from is refused, and kept ===\n";
// On this line a message is written to the sponsor's own row (`interests`, `support`), so "nothing is
// written" is the row as it was.
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$i_save  = array( 'WPCPM_Sponsor_Interests', 'handle_save' );
$i_note  = "Kids <12 free, adults >18 pay.\r\nA second line, \"quoted\" & C:\\drafts";
$i_event = "WordCamp Europe 2027\r\nWordCamp <Asia> 2027";
$i_row   = WPCPM_Sponsors_Index::row( $A );
$i_base  = array_values( array_intersect( WPCPM_Sponsor_Interests::CHOICES, (array) $i_row['support'] ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_support' => array( 'Sponsor a mentor or multiple mentors', 'Made up' ), 'wpcpm_events' => 'WordCamp Europe 2027', 'wpcpm_note' => $i_note ), $i_save );
ck( 'a note the cleaner would cut to "Kids 18 pay." is refused, with the sentence for a form that sends, naming the box by its label', array( $r, $GLOBALS['left_detail'] ), array( array( 'interest-loss', 'interests', $A ), 'Nothing was sent, because WordPress would remove part of what you typed in "Anything else". To keep every word, put a space after each "<" (or write "less than") and after each "%", then send it again.' ) );
ck( 'and nothing is written, mailed or logged, and none of the day\'s five is spent', array( $GLOBALS['sent'], $GLOBALS['audit'], $GLOBALS['buckets'], WPCPM_Sponsors_Index::row( $A ) === $i_row ), array( array(), array(), array(), true ) );
ck( 'the messages map has the refusal, its whole sentence the detail', WPCPM_Sponsor_Interests::messages()['interest-loss'] ?? null, array( 'error', '' ) );
ck( 'the card draws the form again with each box as it was typed, a line break as the box counts it, and the choices as they were ticked', cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) ), array( 'events' => 'WordCamp Europe 2027', 'note' => str_replace( "\r\n", "\n", $i_note ), 'ticked' => array( 'Sponsor a mentor or multiple mentors' ) ) );
ck( 'and only once: drawn again, the boxes are empty and the choices are the row\'s again', cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) ), array( 'events' => '', 'note' => '', 'ticked' => $i_base ) );

$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => $i_event, 'wpcpm_note' => 'Plain words' ), $i_save );
ck( 'an event the cleaner would take words from is refused the same way, the box named by its label, and spends nothing', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['buckets'], WPCPM_Sponsors_Index::row( $A ) === $i_row ), array( 'interest-loss', WPCPM_Typed_Text::loss_message( 'Flagship events you would sponsor students to attend', 'send' ), array(), true ) );
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

echo "\n=== The profile: a typing the cleaner would take words from is refused, and kept ===\n";
// On this line the profile's fields are the website, the Contact person, the contact email, the
// product and Anything else (the offer's line, instructions and link are the Offers card's), and a
// save is seen in the row and its audit row: the store is the one copy.
/** The profile form as a browser shows it: each input's value, what the text area holds, and the product chosen. */
function cards_profile_form( $html ) {
	$out = array();
	foreach ( array( 'website', 'contact_person', 'contact_email' ) as $key ) {
		// A text input drops every line break from its value, so a break in the markup is not in the box.
		$out[ $key ] = preg_match( '/name="wpcpm_' . $key . '" value="([^"]*)"/', $html, $m ) ? str_replace( array( "\r", "\n" ), '', html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : null;
	}
	// The parser drops the one line feed right after the opening tag.
	$out['anything']     = preg_match( '/<textarea id="wpcpm-profile-anything" name="wpcpm_anything"[^>]*>(.*?)<\/textarea>/s', $html, $m ) ? html_entity_decode( (string) preg_replace( '/^\n/', '', str_replace( "\r\n", "\n", $m[1] ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : null;
	$out['product_type'] = preg_match( '/<option value="([^"]*)" selected="selected"/', $html, $m ) ? $m[1] : null;
	return $out;
}
/** The profile form posted as a browser posts it, the text area's breaks as CR LF, with the fields in $change replaced. */
function cards_profile_post( $record, array $shown, array $change = array() ) {
	$post = array( 'wpcpm_sponsor' => $record );
	foreach ( $shown as $key => $value ) {
		$post[ 'wpcpm_' . $key ] = 'anything' === $key ? str_replace( "\n", "\r\n", (string) $value ) : (string) $value;
	}
	return array_merge( $post, $change );
}
$GLOBALS['uid'] = 5; $GLOBALS['buckets'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
unset( $GLOBALS['offers'] );
$p_save  = array( 'WPCPM_Sponsor_Profile', 'handle_save' );
$p_row   = WPCPM_Sponsors_Index::row( $A );
$p_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$p_lossy = 'Ana <ana@example.test>';
$p_note  = "Kids <12 free,\r\nadults >18 pay";
$p_site  = 'https://plugins.mango-example.test/kept/';
ck( 'the form drawn as the row holds it: a contact person of 251 and a text of 4,050, each over its limit', array( mb_strlen( (string) $p_shown['contact_person'] ), mb_strlen( (string) $p_shown['anything'] ) ), array( 251, 4050 ) );
$r = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_anything' => $p_note, 'wpcpm_website' => $p_site, 'wpcpm_product_type' => 'Service' ) ), $p_save );
ck( 'an Anything else the cleaner would cut to "Kids 18 pay" is refused, with the sentence that names the box by its label', array( $r, $GLOBALS['left_detail'] ), array( array( 'profile-loss', 'profile', $A ), WPCPM_Typed_Text::loss_message( 'Anything else you would like to share' ) ) );
ck( 'and nothing is written: the row and the log are as they were, and nothing is spent', array( WPCPM_Sponsors_Index::row( $A ) === $p_row, $GLOBALS['audit'], $GLOBALS['buckets'] ), array( true, array(), array() ) );
ck( 'the messages map has the refusal, its whole sentence the detail', WPCPM_Sponsor_Profile::messages()['profile-loss'] ?? null, array( 'error', '' ) );
$p_form = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'the card draws the form again with every field as it was left: Anything else and the website as typed, a line break as the box counts it, the product as chosen, the rest as posted', $p_form, array_merge( $p_shown, array( 'anything' => str_replace( "\r\n", "\n", $p_note ), 'website' => $p_site, 'product_type' => 'Service' ) ) );
ck( 'the contact person the row holds over its limit comes back whole: cut to the box\'s limit, the next save would store it short', mb_strlen( (string) $p_form['contact_person'] ), 251 );
$r      = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_contact_person' => $p_lossy, 'wpcpm_website' => $p_site, 'wpcpm_product_type' => 'Service' ) ), $p_save );
$p_said = $GLOBALS['left_detail'];
$p_form = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'a contact person the cleaner would cut to "Ana" is refused the same way, named by its label, and the text the row holds over its limit comes back whole', array( $r[0], $p_said, $p_form['contact_person'], $p_form['website'], mb_strlen( (string) $p_form['anything'] ), WPCPM_Sponsors_Index::row( $A ) === $p_row ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Contact person' ), $p_lossy, $p_site, 4050, true ) );
$r = post( cards_profile_post( $A, $p_form, array( 'wpcpm_contact_person' => 'Ana (ana@example.test)' ) ), $p_save );
ck( 'posted back with the fix, the save goes through and writes what changed, and not the text the row holds over its limit', array( $r[0], end( $GLOBALS['audit'] )['data']['fields'] ?? null, WPCPM_Sponsors_Index::row( $A )['contact_person'], WPCPM_Sponsors_Index::row( $A )['website'], WPCPM_Sponsors_Index::row( $A )['product_type'], WPCPM_Sponsors_Index::row( $A )['anything'] === $p_row['anything'] ), array( 'profile-saved', array( 'website', 'contact_person', 'product_type' ), 'Ana (ana@example.test)', $p_site, 'Service', true ) );

$p_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_contact_person' => 'Ana, 10%cashback' ) ), $p_save );
$p_said  = $GLOBALS['left_detail'];
$p_first = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$p_again = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'a contact person with a "%" the cleaner would take, with the two characters after it, is refused the same way, and drawn back as typed only once', array( $r[0], $p_said, $p_first['contact_person'], $p_again['contact_person'] ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Contact person' ), 'Ana, 10%cashback', $p_shown['contact_person'] ) );

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
// This line refuses a text area over its limit with its own sentence, which names the box and its
// limit (`profile-text-long`); the form comes back with the typing the same way.
$r      = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_anything' => str_replace( "\n", "\r\n", (string) $p_shown['anything'] ) . 'y' ) ), $p_save );
$p_said = $GLOBALS['left_detail'];
$p_form = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'an Anything else the row holds over its limit, edited longer, is refused as before and kept up to what its box was drawn with', array( $r[0], $p_said, $p_form['anything'] ), array( 'profile-text-long', '', $p_shown['anything'] ) );

$r      = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_contact_person' => "Bad \xC3\x28 bytes", 'wpcpm_product_type' => 'Plugin' ) ), $p_save );
$p_form = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
ck( 'a contact person that is not valid UTF-8, which the cleaner empties, is refused as a loss and not kept: its box shows the stored name, the choice beside it is kept', array( $r[0], $p_form['contact_person'], $p_form['product_type'] ), array( 'profile-loss', $p_shown['contact_person'], 'Plugin' ) );

$r = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_contact_person' => $p_lossy ) ), $p_save );
$r = post( cards_profile_post( $A, $p_shown, array( 'wpcpm_product_type' => 'Plugin' ) ), $p_save );
ck( 'a typing a refusal kept goes when the form is posted again, so a save that went through before the page drew it is drawn as saved', array( $r[0], cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) )['contact_person'], WPCPM_Sponsors_Index::row( $A )['product_type'] ), array( 'profile-saved', $p_shown['contact_person'], 'Plugin' ) );
$p_keeps = "Ages 8 < 12 welcome, adults > 18 pay.\r\nQ&A: \"blocks\" & themes, it's free, 100% of them, &amp; and &copy; typed out.";
$r = post( cards_profile_post( $A, cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) ), array( 'wpcpm_contact_person' => 'We <3 WordPress', 'wpcpm_anything' => $p_keeps ) ), $p_save );
ck( 'a typing the cleaner keeps whole saves as before, each line break one LF, and keeps nothing for the form', array( $r[0], WPCPM_Sponsors_Index::row( $A )['contact_person'], WPCPM_Sponsors_Index::row( $A )['anything'], isset( $GLOBALS['flash'][5][ 'typed:profile:' . $A ] ) ), array( 'profile-saved', 'We &lt;3 WordPress', str_replace( "\r\n", "\n", sanitize_textarea_field( $p_keeps ) ), false ) );

echo "\n=== A kept typing that starts with a line break comes back with it ===\n";
// The parser drops the one line feed right after a text area's opening tag, so a box drawn with a
// kept typing writes one of its own first.
$GLOBALS['buckets'] = array(); $GLOBALS['flash'] = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_events' => $i_event, 'wpcpm_note' => "\r\nleading break" ), $i_save );
ck( 'on the interests card', array( $r[0], cards_interest_form( card( 'WPCPM_Sponsor_Interests', $A, $context ) )['note'] ), array( 'interest-loss', "\nleading break" ) );
$r = post( cards_profile_post( $A, cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) ), array( 'wpcpm_contact_person' => $p_lossy, 'wpcpm_anything' => "\r\nleading break" ) ), $p_save );
ck( 'and on the profile', array( $r[0], cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) )['anything'] ), array( 'profile-loss', "\nleading break" ) );

echo "\n=== A box posted back as it was drawn is the stored text, whatever the row holds in it ===\n";
// Brought over from the program records, which no cleaner read: a tag and a percent octet the
// cleaner would take.
$GLOBALS['uid'] = 5; $GLOBALS['flash'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['left_detail'] = null;
$s_contact = 'Ana <Sales>';
$s_else    = "Get 10%cashback\nat checkout.";
$s_site    = 'https://plugins.mango-example.test/stored/';
WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => $s_contact, 'anything' => $s_else ) );
$s_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r = post( cards_profile_post( $A, $s_shown, array( 'wpcpm_website' => $s_site ) ), $p_save );
ck( 'a profile whose contact person holds "<Sales>" and whose Anything else holds "10%cashback", posted back as drawn with the website changed, saves the website alone', array( $r[0], end( $GLOBALS['audit'] )['data']['fields'] ?? null, WPCPM_Sponsors_Index::row( $A )['website'] ), array( 'profile-saved', array( 'website' ), $s_site ) );
ck( 'and the two keep their bytes in the row: a box posted back as drawn is neither asked about losses nor rewritten', array( WPCPM_Sponsors_Index::row( $A )['contact_person'] === $s_contact, WPCPM_Sponsors_Index::row( $A )['anything'] === $s_else ), array( true, true ) );
$GLOBALS['audit'] = array();
$r = post( cards_profile_post( $A, $s_shown, array( 'wpcpm_website' => $s_site, 'wpcpm_contact_person' => 'Ana <Sales> now' ) ), $p_save );
ck( 'the same box edited to "Ana <Sales> now" is refused, and nothing is written', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['contact_person'] === $s_contact ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Contact person' ), array(), true ) );
card( 'WPCPM_Sponsor_Profile', $A, $context );
$r = post( cards_profile_post( $A, $s_shown, array( 'wpcpm_website' => $s_site, 'wpcpm_anything' => "Get 10%cashback\r\nat checkout, now." ) ), $p_save );
ck( 'and so is Anything else, edited', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['anything'] === $s_else ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Anything else you would like to share' ), array(), true ) );
card( 'WPCPM_Sponsor_Profile', $A, $context );
// The unedited test reads what was typed, never what the cleaner makes of it: a typing the cleaner
// cuts back to the stored text is an edit, and the words it would take are the person's.
WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => 'Ana Sales' ) );
$s_plain = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$GLOBALS['audit'] = array(); $GLOBALS['left_detail'] = null;
$r = post( cards_profile_post( $A, $s_plain, array( 'wpcpm_contact_person' => 'Ana Sales<b></b>' ) ), $p_save );
ck( 'a stored "Ana Sales" typed as "Ana Sales<b></b>", which the cleaner cuts back to the stored text, is refused, and the stored text is unchanged', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['contact_person'] ), array( 'profile-loss', WPCPM_Typed_Text::loss_message( 'Contact person' ), array(), 'Ana Sales' ) );
card( 'WPCPM_Sponsor_Profile', $A, $context );

echo "\n=== A stored value with white space at an end, posted back as drawn ===\n";
// The cleaner trims what it reads. Brought over from the program records, or left by an older
// card's cut, a stored value can end in a space or begin with a line break, and a box can hand it
// back without them: a text area drops a line break its text begins with, and a link's box drops
// the spaces at its ends. A box nobody touched is still the stored value, and is not rewritten.
$GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => 'Test Sponsor Contact ', 'anything' => 'Use the code at checkout.' ) );
$w_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $w_shown ), $p_save );
ck( 'a stored "Test Sponsor Contact " is drawn with its space and, posted back unedited, writes nothing, logs nothing and says nothing changed', array( $w_shown['contact_person'], $r[0], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['contact_person'] ), array( 'Test Sponsor Contact ', 'profile-unchanged', array(), 'Test Sponsor Contact ' ) );
WPCPM_Sponsors_Index::patch( $A, array( 'anything' => "\nUse the code at checkout." ) );
$GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$w_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $w_shown ), $p_save );
ck( 'an Anything else stored with a line break first, which its box drops, posted back unedited: nothing written or logged, nothing changed, the stored text kept', array( $w_shown['anything'], $r[0], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['anything'] ), array( 'Use the code at checkout.', 'profile-unchanged', array(), "\nUse the code at checkout." ) );
WPCPM_Sponsors_Index::patch( $A, array( 'anything' => "\nGet 10%cashback at checkout." ) );
$GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$w_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $w_shown ), $p_save );
ck( 'and the same with a "%" the cleaner would take: not refused for a box the member did not touch', array( $r[0], $GLOBALS['left_detail'], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['anything'] ), array( 'profile-unchanged', '', array(), "\nGet 10%cashback at checkout." ) );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => 'https://plugins.mango-example.test/ ', 'anything' => 'Use the code at checkout.' ) );
$GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$w_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$w_post  = cards_profile_post( $A, $w_shown, array( 'wpcpm_website' => trim( (string) $w_shown['website'] ) ) );
$r       = post( $w_post, $p_save );
ck( 'a website stored with a space after it, posted back by its box without the space: nothing written or logged, nothing changed', array( $r[0], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['website'] ), array( 'profile-unchanged', array(), 'https://plugins.mango-example.test/ ' ) );
$GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$r = post( array_merge( $w_post, array( 'wpcpm_contact_person' => 'Test Sponsor Contact, Sales' ) ), $p_save );
ck( 'an edit beside them is written as before, and only the edit', array( $r[0], WPCPM_Sponsors_Index::row( $A )['contact_person'], WPCPM_Sponsors_Index::row( $A )['website'], end( $GLOBALS['audit'] )['data']['fields'] ?? null ), array( 'profile-saved', 'Test Sponsor Contact, Sales', 'https://plugins.mango-example.test/ ', array( 'contact_person' ) ) );

echo "\n=== The website keeps every percent octet, typed or posted back as drawn ===\n";
// A link is a code, not prose: the one-line cleaner removes every "%" followed by two hexadecimal
// digits, which takes "%C3%B3" out of an address written in another alphabet and "%20" out of a
// query. A link box posted back as it was drawn is the stored link, as the text boxes are. On this
// line the profile holds one link, the website: the Link with more information is the Offers card's.
$l_wiki = 'https://pl.wikipedia.org/wiki/Krak%C3%B3w';
$l_utm  = 'https://plugins.mango-example.test/?utm_source=wordpress%20education';
WPCPM_Sponsors_Index::patch( $A, array( 'website' => $l_wiki, 'contact_person' => 'Test Sponsor Contact' ) );
$GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$l_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $l_shown, array( 'wpcpm_contact_person' => 'Test Sponsor Contact, Sales' ) ), $p_save );
ck( 'a website stored with percent octets is drawn as stored', $l_shown['website'], $l_wiki );
ck( 'posted back untouched while the contact person changes, the save writes the contact person only, and the one audit line names no link', array( $r[0], WPCPM_Sponsors_Index::row( $A )['contact_person'], array_column( array_column( $GLOBALS['audit'], 'data' ), 'fields' ) ), array( 'profile-saved', 'Test Sponsor Contact, Sales', array( array( 'contact_person' ) ) ) );
ck( 'and the row keeps the link byte for byte', WPCPM_Sponsors_Index::row( $A )['website'], $l_wiki );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => 'https://plugins.mango-example.test/' ) );
$GLOBALS['audit'] = array();
$r = post( cards_profile_post( $A, cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) ), array( 'wpcpm_website' => $l_utm ) ), $p_save );
ck( 'a link with "%20" in its query typed fresh is written and stored with its octets', array( $r[0], end( $GLOBALS['audit'] )['data']['fields'] ?? null, WPCPM_Sponsors_Index::row( $A )['website'] ), array( 'profile-saved', array( 'website' ), $l_utm ) );
$r = post( cards_profile_post( $A, cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) ), array( 'wpcpm_website' => $l_wiki ) ), $p_save );
ck( 'and so is one with "%C3%B3" in its path', array( $r[0], WPCPM_Sponsors_Index::row( $A )['website'] ), array( 'profile-saved', $l_wiki ) );
// Brought over from the program records, a link can lack the scheme the card would add, or carry a
// name before its host, which the card refuses when it is typed. Neither is rewritten or refused
// when its box comes back as it was drawn.
WPCPM_Sponsors_Index::patch( $A, array( 'website' => 'plugins.mango-example.test/grid' ) );
$GLOBALS['audit'] = array();
$r = post( cards_profile_post( $A, cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) ), array( 'wpcpm_contact_person' => 'Test Sponsor Contact' ) ), $p_save );
ck( 'a website stored without a scheme, posted back untouched beside an edit: only the edit is written', array( $r[0], end( $GLOBALS['audit'] )['data']['fields'] ?? null, WPCPM_Sponsors_Index::row( $A )['website'] ), array( 'profile-saved', array( 'contact_person' ), 'plugins.mango-example.test/grid' ) );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => 'https://name@host.test/grid' ) );
$GLOBALS['audit'] = array();
$r = post( cards_profile_post( $A, cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) ), array( 'wpcpm_contact_person' => 'Test Sponsor Contact, Sales' ) ), $p_save );
ck( 'and one stored with a name before its host the same way', array( $r[0], end( $GLOBALS['audit'] )['data']['fields'] ?? null, WPCPM_Sponsors_Index::row( $A )['website'] ), array( 'profile-saved', array( 'contact_person' ), 'https://name@host.test/grid' ) );
$GLOBALS['audit'] = array();
$r = post( cards_profile_post( $A, cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) ), array( 'wpcpm_website' => 'https://name@host.test/typed' ) ), $p_save );
ck( 'while the same kind of link typed into the box is refused as before, and nothing is written', array( $r[0], $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['website'] ), array( 'profile-rejected', array(), 'https://name@host.test/grid' ) );

echo "\n=== A one-line box drawn from a stored value with a line break inside it ===\n";
// Brought over from the program records, a one-line value can hold a line break, and a text input
// drops it from its value: "Free hosting" and "for students" drawn as stored would post back as
// "Free hostingfor students". On this card the one-line text is the Contact person. The break is
// drawn as a space, and a box posted back as it was drawn is the stored value, kept byte for byte.
WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => "Free hosting\nfor students", 'website' => 'https://plugins.mango-example.test/', 'anything' => '' ) );
$GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$b_row   = WPCPM_Sponsors_Index::row( $A );
$b_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $b_shown ), $p_save );
ck( 'a stored "Free hosting" and "for students" on two lines is drawn as "Free hosting for students"', $b_shown['contact_person'], 'Free hosting for students' );
ck( 'posted back unedited it is the stored value: nothing written, no audit line, nothing changed, the stored bytes kept', array( $r[0], WPCPM_Sponsors_Index::row( $A ) === $b_row, $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['contact_person'] ), array( 'profile-unchanged', true, array(), "Free hosting\nfor students" ) );

$r = post( cards_profile_post( $A, $b_shown, array( 'wpcpm_contact_person' => 'Free hosting for all students' ) ), $p_save );
ck( 'edited, the box is written as the person left it, and the one audit line names the Contact person', array( $r[0], WPCPM_Sponsors_Index::row( $A )['contact_person'], end( $GLOBALS['audit'] )['data']['fields'] ?? null ), array( 'profile-saved', 'Free hosting for all students', array( 'contact_person' ) ) );

WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => "Free hosting\r\nfor students\r\n\r\nnow", 'website' => "https://plugins.mango-example.test/\r\nabout" ) );
$GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$b_row   = WPCPM_Sponsors_Index::row( $A );
$b_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $b_shown ), $p_save );
ck( 'each break is one space, a CR LF one too, so a contact person on four lines and a website on two are drawn so', array( $b_shown['contact_person'], $b_shown['website'] ), array( 'Free hosting for students  now', 'https://plugins.mango-example.test/ about' ) );
ck( 'and posted back unedited, both keep their stored bytes with nothing written or logged', array( $r[0], WPCPM_Sponsors_Index::row( $A ) === $b_row, $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['contact_person'], WPCPM_Sponsors_Index::row( $A )['website'] ), array( 'profile-unchanged', true, array(), "Free hosting\r\nfor students\r\n\r\nnow", "https://plugins.mango-example.test/\r\nabout" ) );

WPCPM_Sponsors_Index::patch( $A, array( 'contact_person' => 'Test Sponsor Contact', 'website' => "https://plugins.mango-example.test/\nabout" ) );
$GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$b_row   = WPCPM_Sponsors_Index::row( $A );
$b_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $b_shown ), $p_save );
ck( 'a link box is drawn the same way, and posted back unedited keeps the stored link', array( $b_shown['website'], $r[0], WPCPM_Sponsors_Index::row( $A ) === $b_row, $GLOBALS['audit'], WPCPM_Sponsors_Index::row( $A )['website'] ), array( 'https://plugins.mango-example.test/ about', 'profile-unchanged', true, array(), "https://plugins.mango-example.test/\nabout" ) );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => 'https://plugins.mango-example.test/' ) );
// An address is drawn as it is stored, its break and all: the email box drops the break, as it did
// before, while a space there would be an address that is not one, which the browser refuses, and
// with it the whole save until the person found and took the space out.
WPCPM_Sponsors_Index::patch( $A, array( 'contact_email' => "maciej@\na8c.com" ) );
$GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$b_html  = card( 'WPCPM_Sponsor_Profile', $A, $context );
$b_shown = cards_profile_form( $b_html );
$b_value = preg_match( '/name="wpcpm_contact_email" value="([^"]*)"/', $b_html, $b_m ) ? html_entity_decode( $b_m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : null;
$r       = post( cards_profile_post( $A, $b_shown ), $p_save );
ck( 'an address stored with a line break inside is drawn as stored, never with a space, so the email box shows an address and the save goes through', array( $b_value, $b_shown['contact_email'], is_email( $b_shown['contact_email'] ), $r[0] ), array( "maciej@\na8c.com", 'maciej@a8c.com', true, 'profile-saved' ) );
WPCPM_Sponsors_Index::patch( $A, array( 'contact_email' => 'maciej@a8c.com' ) );

echo "\n=== The same boxes as the Sponsors screen's Edit form ===\n";
// The Website, the Contact person and the Contact email are the Sponsors screen's boxes too, and a
// row reads one way on both: a stored entity in the Website is drawn so that the box shows it and
// posts it back as stored (`WPCPM_Typed_Text::kept_attr()`), a stored line break in it or in the
// Contact person is a space, and an address stored with an entity is drawn as the address it is. The
// values below are the ones bin/test-sponsors-screen.php draws the Edit form with for the same row,
// and posted back untouched, neither screen writes anything.
$same_row = array(
	'website'        => "https://example.com/?a=1&amp;b=2\r\nabout",
	'contact_person' => "Bob &amp; Co\r\n\r\nSales",
	'contact_email'  => 'contact+a&amp;b@example.test',
);
WPCPM_Sponsors_Index::patch( $A, $same_row );
$GLOBALS['audit'] = array(); $GLOBALS['flash'] = array(); $GLOBALS['left_detail'] = null;
$b_row   = WPCPM_Sponsors_Index::row( $A );
$b_shown = cards_profile_form( card( 'WPCPM_Sponsor_Profile', $A, $context ) );
$r       = post( cards_profile_post( $A, $b_shown ), $p_save );
ck( 'the profile card draws the Website, the Contact person and the Contact email as the Sponsors screen\'s Edit form draws them: the entity of the link shown as typed, each break a space, and the address as the address it stores',
	array( $b_shown['website'], $b_shown['contact_person'], $b_shown['contact_email'] ),
	array( 'https://example.com/?a=1&amp;b=2 about', 'Bob & Co  Sales', 'contact+a&b@example.test' ) );
ck( 'and posted back untouched, it writes nothing: no audit line, every stored byte kept',
	array( $r[0], WPCPM_Sponsors_Index::row( $A ) === $b_row, $GLOBALS['audit'], array( WPCPM_Sponsors_Index::row( $A )['website'], WPCPM_Sponsors_Index::row( $A )['contact_person'], WPCPM_Sponsors_Index::row( $A )['contact_email'] ) ),
	array( 'profile-unchanged', true, array(), array_values( $same_row ) ) );
$r = post( cards_profile_post( $A, $b_shown, array( 'wpcpm_website' => 'https://example.com/?a=1&b=2', 'wpcpm_contact_email' => 'maciej+ab@a8c.com' ) ), $p_save );
ck( 'while the link and the address edited are edits as before: both saved, the audit line naming the two',
	array( $r[0], WPCPM_Sponsors_Index::row( $A )['website'], WPCPM_Sponsors_Index::row( $A )['contact_email'], WPCPM_Sponsors_Index::row( $A )['contact_person'], end( $GLOBALS['audit'] )['data']['fields'] ?? null ),
	array( 'profile-saved', 'https://example.com/?a=1&b=2', 'maciej+ab@a8c.com', $same_row['contact_person'], array( 'website', 'contact_email' ) ) );
WPCPM_Sponsors_Index::patch( $A, array( 'website' => 'https://plugins.mango-example.test/', 'contact_person' => 'Test Sponsor Contact', 'contact_email' => 'maciej@a8c.com' ) );
// An address reaches the unedited test as `sanitize_email()` hands it back, which holds no space, so
// a stored address with its breaks read as spaces can never be it: the fold is asked of a one-line
// text and a link, and of nothing else. An address is read with each entity read alone, the rule of
// the Sponsors screen's Contact email.
$b_test = new ReflectionMethod( 'WPCPM_Sponsor_Profile', 'unchanged' );
$b_body = implode( '', array_slice( file( $b_test->getFileName() ), $b_test->getStartLine() - 1, $b_test->getEndLine() - $b_test->getStartLine() + 1 ) );
ck( 'the unedited test folds a stored line break for a one-line text and a link only, never for an address, which it reads with each entity read and nothing else', array( substr_count( $b_body, 'one_line(' ), preg_match( '/if \( \'email\' === \$kind \) \{\s*return WPCPM_Typed_Text::same_text\( \$next, \$current \);\s*\}/', $b_body ) ), array( 2, 1 ) );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
