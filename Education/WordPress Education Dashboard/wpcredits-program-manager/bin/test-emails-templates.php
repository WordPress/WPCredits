<?php
/**
 * The Emails tool's Templates and Signature and footer tabs, and the editor's buttons: Save and
 * Send me a test, two buttons of one form, and Restore the original.
 *
 * Drawn and pressed through the real tool, the real registry, the real typed-text rules and the real
 * flash. What each block pins:
 *
 * - **The tabs**: the screen's own address opens Templates, a tab it does not have does too, and
 *   the bar is WordPress's own, Templates, Signature and footer, Log, in that order.
 * - **The Templates tab** lists the templates by area, in the catalog's order, each area in a table
 *   of its own, each row with who receives it, what sends it, where it stands and who edited it last,
 *   and an Edit link to its editor: the Invitations, the Mentor calls, the Institutions, the Semester
 *   reports and the Sponsors, every area of the plugin's emails, since 1.122.22.
 * - **The editor**: the boxes hold the shipped text, then the edit read back as it was typed; the
 *   placeholders say what they mean, the required one marked; the preview is filled with the
 *   examples and ends with the footer; Restore asks first, and is there only for an edit.
 * - **The buttons**: Administrators only, the capability asked before the nonce; a refused save
 *   stores nothing and the editor comes back with what was typed, byte for byte; Send me a test goes
 *   to the presser's own address and to nobody a press names, and only for the text as saved:
 *   pressed with typing not saved yet, it sends nothing and gives the typing back as a refused save
 *   does.
 * - **The Mentor calls, the Institutions, the Semester reports and the Sponsors** work in the same
 *   editor, with the same buttons: each email's preview and test are filled as a real email to the
 *   presser would be, a call tomorrow at 3 pm on the presser's own clock and an agreement decided
 *   today on the site's among them (the site and the presser a day apart, on the two sides of the
 *   date line), and each ends with the footer of its audience's dashboard; a save without a
 *   placeholder the email needs (a call's time, a link that carries a secret, what an Administrator
 *   or a sponsor wrote), or with one that goes in the body alone (a meeting link, a code's last
 *   characters) in the subject, is refused, whichever email of the Institutions or the Sponsors it
 *   is; and an edit is saved, tested and restored, a subject that holds a title as plain text
 *   included.
 *
 * Run from the plugin root:  php bin/test-emails-templates.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', array $roles = array() ) {
		$this->ID           = $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( ' ', '.', $name ) );
		$this->roles        = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Error {
	private $code, $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class RedirectSignal extends Exception {}
class DieSignal extends Exception {}

$GLOBALS['opts']   = array(
	'timezone_string' => 'Europe/Warsaw',
	'date_format'     => 'F j, Y',
	'time_format'     => 'g:i a',
);
$GLOBALS['umeta']  = array();
$GLOBALS['hooked'] = array();
$GLOBALS['mail']   = array();
$GLOBALS['can']    = true;
$GLOBALS['uid']    = 1;
$GLOBALS['users']  = array();

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function wp_slash( $v ) { return $v; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function is_email( $s ) { return false !== filter_var( (string) $s, FILTER_VALIDATE_EMAIL ) ? $s : false; }
function user_can( $user, $cap ) { return false; }
function current_user_can( $cap ) { return 'unfiltered_html' !== $cap && (bool) $GLOBALS['can']; }
function get_bloginfo( $k = 'name' ) { return 'WordPress Credits'; }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
// Core's two forms: `add_query_arg( $key, $value, $url )` and `add_query_arg( $args, $url )`.
function add_query_arg( ...$args ) {
	$pairs = is_array( $args[0] ) ? $args[0] : array( $args[0] => $args[1] );
	$url   = (string) ( is_array( $args[0] ) ? $args[1] : $args[2] );

	foreach ( $pairs as $k => $v ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
	}

	return $url;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce' ) { echo '<input type="hidden" id="' . $name . '" name="' . $name . '" value="nonce:' . $action . '" />'; }
function wp_create_nonce( $action = -1 ) { return 'nonce:' . $action; }
function check_admin_referer( $action = -1, $name = '_wpnonce' ) {
	if ( ! empty( $GLOBALS['nonce_fails'] ) ) {
		throw new DieSignal( 'The link you followed has expired.' );
	}

	$GLOBALS['nonce_checked'] = $action;

	return true;
}
function wp_die( $m = '' ) { throw new DieSignal( (string) $m ); }
function wp_safe_redirect( $to ) { throw new RedirectSignal( (string) $to ); }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_user_by( $field, $value ) { return $GLOBALS['users'][ (int) $value ] ?? false; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function wp_timezone_string() { return (string) get_option( 'timezone_string', 'UTC' ); }
function wp_timezone() { return new DateTimeZone( wp_timezone_string() ); }
function wp_date( $format, $timestamp = null, $zone = null ) { return ( new DateTimeImmutable( '@' . (int) ( null === $timestamp ? time() : $timestamp ) ) )->setTimezone( $zone instanceof DateTimeZone ? $zone : wp_timezone() )->format( $format ); }
function human_time_diff( $from, $to = 0 ) { return round( abs( (int) $to - (int) $from ) / HOUR_IN_SECONDS ) . ' hours'; }
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooked'][] = $h; return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return true; }
function apply_filters( $h, $v ) { return $v; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_enqueue_style( $h, $src = '', $deps = array(), $ver = false ) {}
function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other = array() ) { echo '<p class="submit"><input type="submit" name="' . $name . '" id="' . ( $other['id'] ?? $name ) . '" class="button button-' . $type . '" value="' . esc_attr( (string) $text ) . '" /></p>'; }
// The row's help may hold <code>, which the row lets through as core's kses would.
function wp_kses( $s, $allowed = array() ) { return $s; }
// No page at the path the program manager guide is published at, so the Settings section links none.
function get_page_by_path( $path, $output = 'OBJECT', $type = 'page' ) { return null; }
// What the mail layer hands WordPress, with the id and the test mark the mail log's capture takes.
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['mail'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body, 'headers' => $headers, 'context' => WPCPM_Mail::take_context(), 'test' => WPCPM_Mail::take_test() );
	return true;
}
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-rows.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-emails.php';
// What the Mentor calls' and the Semester reports' examples are worked out with, as their emails are.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-availability.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report-screen.php';
// And the Institutions' and the Sponsors': an application's confirmation link, an invitation's link
// and how long it works, a sponsor's dated line, the sponsor an Offers link names, and the scan's
// notes in words.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-pdf-check.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-invite.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-interests.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-roster.php';

/** The dashboards, each published at an address of its own. */
class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student-report-card/'; }
}
/** As above. */
class WPCPM_Administrators_Dashboard {
	public static function page_url() { return 'https://example.test/administrator-dashboard/'; }
}
/** As above. */
class WPCPM_Mentors_Dashboard {
	public static function page_url() { return 'https://example.test/mentor-report-card/'; }
}
/** As above. */
class WPCPM_Institutions_Dashboard {
	public static function page_url() { return 'https://example.test/institution-dashboard/'; }
}
/** As above. */
class WPCPM_Sponsors_Dashboard {
	public static function page_url() { return 'https://example.test/sponsor-dashboard/'; }
}

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * Sign an Administrator in, a new one for each press and draw: a flash is read once a request, and
 * this whole run is one request.
 *
 * @return WP_User
 */
function new_administrator() {
	$id                     = count( $GLOBALS['users'] ) + 1;
	$GLOBALS['users'][ $id ] = new WP_User( $id, 'Ida Admin', 'ida' . $id . '@example.test', array( 'administrator' ) );
	$GLOBALS['uid']         = $id;

	return $GLOBALS['users'][ $id ];
}

/** The screen as the signed-in Administrator opens it with these query arguments. */
function screen( array $query ) {
	$_GET     = $query;
	$_REQUEST = $query;

	ob_start();
	( new WPCPM_Emails() )->render_admin_page();

	return (string) ob_get_clean();
}

/**
 * Press one of the editor's buttons with these fields, as the signed-in Administrator: where it went,
 * or what it died with. The page it lands on is a new request, which reads the flash afresh, while
 * `WPCPM_Flash::take()` memoizes per person and channel for the life of a request, and a press takes
 * its typing's channel first (`WPCPM_Typed_Text::forget()`). So, as bin/test-institution-request.php
 * does, an Administrator of their own holding what the press left stands in for the presser on the
 * next page.
 */
function press( $handler, array $fields ) {
	$_POST                    = $fields;
	$GLOBALS['nonce_checked'] = null;
	$went                     = 'returned';

	try {
		( new WPCPM_Emails() )->$handler();
	} catch ( RedirectSignal $e ) {
		$went = 'redirect: ' . $e->getMessage();
	} catch ( DieSignal $e ) {
		$went = 'died: ' . $e->getMessage();
	}

	$left = $GLOBALS['umeta'][ $GLOBALS['uid'] ][ WPCPM_Flash::META ] ?? array();
	unset( $GLOBALS['umeta'][ $GLOBALS['uid'] ][ WPCPM_Flash::META ] );
	$next = new_administrator();

	$GLOBALS['umeta'][ $next->ID ][ WPCPM_Flash::META ] = $left;

	return $went;
}

/** The words some markup says, as a person reads them. */
function words_of( $html ) {
	return trim( preg_replace( '/\s+/', ' ', html_entity_decode( preg_replace( '/<[^>]*>/', ' ', (string) $html ), ENT_QUOTES ) ) );
}

/** The value of the input or the text of the text area with this id. */
function box( $html, $id ) {
	if ( preg_match( '#<input type="text" id="' . $id . '"[^>]* value="([^"]*)" />#', $html, $m ) ) {
		return html_entity_decode( $m[1], ENT_QUOTES );
	}

	return preg_match( '#<textarea id="' . $id . '"[^>]*>(.*?)</textarea>#s', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : null;
}

$editor_url = 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-student';

echo "=== The tool and its tabs ===\n";

( new WPCPM_Emails() )->boot();
ck( 'the tool hooks its stylesheet and the editor\'s two forms, and nothing else', $GLOBALS['hooked'], array( 'admin_enqueue_scripts', 'admin_post_wpcpm_email_save', 'admin_post_wpcpm_email_restore' ) );

new_administrator();
$home = screen( array( 'page' => 'wpcpm-tool-emails' ) );
preg_match_all( '#<a href="([^"]*)" class="nav-tab( nav-tab-active)?"[^>]*>([^<]*)</a>#', $home, $bar, PREG_SET_ORDER );
ck( 'the screen\'s own address opens Templates, in a bar of three tabs, each at its own address', array_map( function ( $tab ) { return array( $tab[3], $tab[1], '' !== $tab[2] ); }, $bar ), array( array( 'Templates', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates', true ), array( 'Signature and footer', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer', false ), array( 'Log', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log', false ) ) );
ck( 'and a tab the screen does not have opens Templates too', 1 === preg_match( '#class="nav-tab nav-tab-active" aria-current="page">Templates</a>#', screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'settings' ) ) ), true );

echo "\n=== The Templates tab ===\n";

preg_match_all( '#<h2>([^<]*)</h2>#', $home, $areas );
preg_match_all( '#<tr><td>([^<]*)</td><td>([^<]*)</td><td>([^<]*)</td><td><span class="wpcpm-emails__state wpcpm-emails__state--([a-z-]+)">([^<]*)</span></td><td>([^<]*)</td><td><a href="([^"]*)">Edit<span class="screen-reader-text"> ([^<]*)</span></a></td></tr>#', $home, $rows, PREG_SET_ORDER );
ck( 'the templates are listed by area, in the catalog\'s order: the Invitations, the Mentor calls, the Institutions, the Semester reports and the Sponsors', $areas[1], array( 'Invitations', 'Mentor calls', 'Institutions', 'Semester reports', 'Sponsors' ) );
ck(
	'each row names the email, who receives it, what sends it, its state and who edited it last, and links its editor by its name',
	array_map( function ( $row ) { return array( html_entity_decode( $row[1], ENT_QUOTES ), $row[2], $row[5], $row[6], $row[7], $row[8] ); }, array_slice( $rows, 0, 5 ) ),
	array(
		array( 'Invitation: student', 'Student', 'Shipped text', 'Not edited', $editor_url, 'Invitation: student' ),
		array( 'Invitation: mentor', 'Mentor', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-mentor', 'Invitation: mentor' ),
		array( 'Invitation: institution', 'Institution member', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-institution', 'Invitation: institution' ),
		array( 'Invitation: institution, agreement on file', 'Institution member', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-institution-settled', 'Invitation: institution, agreement on file' ),
		array( 'Invitation: sponsor', 'Sponsor member', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-sponsor', 'Invitation: sponsor' ),
	)
);
ck(
	'and after the invitations, every email of the Mentor calls, the Institutions, the Semester reports and the Sponsors, each with who receives it, as shipped text, its editor linked by its id',
	array_map( function ( $row ) { return html_entity_decode( $row[1], ENT_QUOTES ) . ' / ' . $row[2] . ' / ' . $row[5] . ' / ' . substr( $row[7], strlen( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=' ) ); }, array_slice( $rows, 5 ) ),
	array(
		'Call booked, to the mentor / Mentor / Shipped text / call-booked-mentor',
		'Call booked, to a mentor with no meeting link / Mentor / Shipped text / call-booked-mentor-no-link',
		'Call booked, to the student / Student / Shipped text / call-booked-student',
		'Group session joined, to the mentor / Mentor / Shipped text / session-joined-mentor',
		'Group session joined, to a mentor with no meeting link / Mentor / Shipped text / session-joined-mentor-no-link',
		'Group session joined, to the student / Student / Shipped text / session-joined-student',
		'Session series joined, to the mentor / Mentor / Shipped text / series-joined-mentor',
		'Session series joined, to a mentor with no meeting link / Mentor / Shipped text / series-joined-mentor-no-link',
		'Session series joined, to the student / Student / Shipped text / series-joined-student',
		'Group session moved, to the student / Student / Shipped text / session-moved-student',
		'Group session moved, to the mentor / Mentor / Shipped text / session-moved-mentor',
		'Left a group session, to the student / Student / Shipped text / session-left',
		'Place on a group session released, to the student / Student / Shipped text / session-released',
		'Call canceled, to the mentor / Mentor / Shipped text / call-cancelled-mentor',
		'Call canceled, to the student / Student / Shipped text / call-cancelled-student',
		'Group session canceled / The people it concerns / Shipped text / session-cancelled',
		'Call reminder, to the mentor / Mentor / Shipped text / call-reminder-mentor',
		'Call reminder, to the student / Student / Shipped text / call-reminder-student',
		'Group session reminder / The people it concerns / Shipped text / session-reminder',
		'Application received, to the applicant / Applicant (no account) / Shipped text / institution-applied',
		'Application received, to an applicant with no call to book / Applicant (no account) / Shipped text / institution-applied-no-call',
		'New institution application, to Administrators / Administrator / Shipped text / institution-application',
		'Question about an application / Applicant (no account) / Shipped text / institution-information',
		'Application declined / Applicant (no account) / Shipped text / institution-declined',
		'Invitation to join an institution / Institution member / Shipped text / institution-invite',
		'Invitation to join an institution, with no sender named / Institution member / Shipped text / institution-invite-no-sender',
		'Collaboration Agreement received, to the institution / Institution member / Shipped text / agreement-received',
		'Collaboration Agreement uploaded, to Administrators / Administrator / Shipped text / agreement-landed',
		'Collaboration Agreement uploaded with the scan\'s notes, to Administrators / Administrator / Shipped text / agreement-landed-flagged',
		'Collaboration Agreement accepted, to the institution / Institution member / Shipped text / agreement-accepted',
		'Collaboration Agreement back in force, to the institution / Institution member / Shipped text / agreement-reinstated',
		'Collaboration Agreement returned with a note, to the institution / Institution member / Shipped text / agreement-returned',
		'Collaboration Agreement taken out of force, to the institution / Institution member / Shipped text / agreement-revoked',
		'Collaboration Agreement taken out of force, to an institution with no country contact / Institution member / Shipped text / agreement-revoked-no-contact',
		'Collaboration Agreements waiting for review / Administrator / Shipped text / agreement-reminder',
		'Collaboration Agreement could not be generated / Administrator / Shipped text / agreement-template',
		'Institution has no members left, to Administrators / Administrator / Shipped text / member-last',
		'Semester report drafted / Administrator / Shipped text / report-drafted',
		'Semester report approved / Institution member / Shipped text / report-approved',
		'Consent for the semester report / Student / Shipped text / report-consent',
		'Sponsor application received, to the applicant / Applicant (no account) / Shipped text / sponsor-applied',
		'Sponsor application received, to an applicant whose logo was not kept / Applicant (no account) / Shipped text / sponsor-applied-no-logo',
		'New sponsor application, to Administrators / Administrator / Shipped text / sponsor-application',
		'Question about a sponsor application / Applicant (no account) / Shipped text / sponsor-information',
		'Sponsor application declined / Applicant (no account) / Shipped text / sponsor-declined',
		'Collaboration Agreement received, to Administrators / Administrator / Shipped text / sponsor-agreement-received',
		'Collaboration Agreement accepted, to the sponsor / Sponsor member / Shipped text / sponsor-agreement-accepted',
		'Collaboration Agreement returned with a note, to the sponsor / Sponsor member / Shipped text / sponsor-agreement-returned',
		'Collaboration Agreement taken out of force, to the sponsor / Sponsor member / Shipped text / sponsor-agreement-revoked',
		'Collaboration Agreement back in force, to the sponsor / Sponsor member / Shipped text / sponsor-agreement-reinstated',
		'Sponsor interest / Administrator / Shipped text / sponsor-interest',
		'Request to sponsor a mentor / Administrator / Shipped text / sponsor-mentor-interest',
		'Codes running low / The people it concerns / Shipped text / offer-low-stock',
		'Problem with a claimed code / Administrator / Shipped text / claim-problem',
		'Sponsor post returned with a note / Sponsor member / Shipped text / sponsor-post-returned',
	)
);
ck( 'and what sends each is the registry\'s sentence', array( html_entity_decode( $rows[0][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[5][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[24][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[44][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[59][3] ?? '', ENT_QUOTES ) ), array( WPCPM_Mail_Templates::get( 'invite-student' )['trigger'], WPCPM_Mail_Templates::get( 'call-booked-mentor' )['trigger'], WPCPM_Mail_Templates::get( 'institution-applied' )['trigger'], WPCPM_Mail_Templates::get( 'report-consent' )['trigger'], WPCPM_Mail_Templates::get( 'sponsor-post-returned' )['trigger'] ) );
$templates_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/emails.css' );
preg_match( '#\.wpcpm-emails__scroll\s*\{([^{}]*)\}#', $templates_css, $scroll_rule );
ck( 'each of the five areas has a table of its own, printed inside a box that scrolls sideways on a narrow screen, held by a position, so the page does not', array( substr_count( $home, '<table' ), preg_match_all( '#<h2>[^<]*</h2><div class="wpcpm-emails__scroll"><table class="widefat striped wpcpm-emails__templates">.*?</table></div>#s', $home ), 1 === preg_match( '/(^|;)\s*overflow-x\s*:\s*auto\s*;/', $scroll_rule[1] ?? '' ), 1 === preg_match( '/(^|;)\s*position\s*:\s*relative\s*;/', $scroll_rule[1] ?? '' ) ), array( 5, 5, true, true ) );
ck( 'no word on the tab says module, and no em or en dash', array( preg_match_all( '/\bmodules?\b/i', words_of( $home ) ), preg_match( '/\x{2013}|\x{2014}/u', $home ) ), array( 0, 0 ) );
ck( 'an Edit link naming an email the registry does not hold opens the list', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'call-booked' ) ), '<h2>Invitations</h2>' ), true );

echo "\n=== The editor ===\n";

new_administrator();
$editor = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
$entry  = WPCPM_Mail_Templates::get( 'invite-student' );
preg_match_all( '#<form method="post" action="https://example.test/wp-admin/admin-post.php"([^>]*)>(.*?)</form>#s', $editor, $forms, PREG_SET_ORDER );
$posted = array_map(
	function ( $form ) {
		preg_match( '#name="action" value="([^"]*)"#', $form[2], $action );
		preg_match( '#name="wpcpm_email" value="([^"]*)"#', $form[2], $email );
		preg_match( '#name="_wpnonce" value="([^"]*)"#', $form[2], $nonce );

		return array( trim( $form[1] ), $action[1] ?? null, $email[1] ?? null, $nonce[1] ?? null );
	},
	$forms
);

ck( 'it names the email, who receives it and what sends it, and where it stands', array( false !== strpos( $editor, '<h2>Invitation: student</h2>' ), false !== strpos( words_of( $editor ), 'To: Student. ' . $entry['trigger'] ), false !== strpos( words_of( $editor ), 'State: Shipped text' ), false !== strpos( $editor, '>All templates</a>' ) ), array( true, true, true, true ) );
ck( 'its Subject and its Body hold the shipped text, at most 200 and 10,000 characters', array( box( $editor, 'wpcpm-email-subject' ), box( $editor, 'wpcpm-email-body' ), 1 === preg_match( '#name="subject" class="large-text" maxlength="200"#', $editor ), 1 === preg_match( '#name="body" class="large-text code" rows="18" maxlength="10000"#', $editor ) ), array( $entry['subject'], $entry['body'], true, true ) );
preg_match_all( '#<button type="submit" class="([^"]*)"(?: name="([^"]*)" value="([^"]*)")?>([^<]*)</button>#', $forms[0][2] ?? '', $buttons, PREG_SET_ORDER );
ck(
	'Save is one form, posting its action, the email and its nonce, locked at its first press, and Send me a test is its second button, so a test reads what is in the boxes; there is no Restore while nothing is edited',
	array( $posted, array_map( function ( $button ) { return array( $button[1], $button[2] . '=' . $button[3], $button[4] ); }, $buttons ) ),
	array(
		array( array( 'class="wpcpm-emails__form" data-wpcpm-once data-wpcpm-busy="Working"', 'wpcpm_email_save', 'invite-student', 'nonce:wpcpm_email_save' ) ),
		array( array( 'button button-primary', '=', 'Save' ), array( 'button', 'wpcpm_then=test', 'Send me a test' ) ),
	)
);
preg_match_all( '#<tr><td><code>\{([a-z_]+)\}</code>( <span class="wpcpm-emails__required">Required</span>)?</td><td>([^<]*)</td><td>([^<]*)</td></tr>#', $editor, $placeholders, PREG_SET_ORDER );
ck( 'its placeholders are listed with what each means and its example, the set-password link marked required', array_map( function ( $row ) { return array( $row[1], '' !== $row[2] ); }, $placeholders ), array( array( 'site_name', false ), array( 'set_password_link', true ), array( 'username', false ), array( 'login_url', false ), array( 'dashboard_link', false ) ) );
ck( 'each example on one line, the empty ones said to be empty', array( html_entity_decode( $placeholders[1][4] ?? '', ENT_QUOTES ), html_entity_decode( $placeholders[4][4] ?? '', ENT_QUOTES ) ), array( 'To set your password, visit the following address:  https://example.test/wp-login.php?login=ada.example&key=EXAMPLE-KEY-NOT-A-REAL-LINK&action=rp', 'https://example.test/student-report-card/' ) );
preg_match( '#<div class="wpcpm-emails__preview"><p><strong>Subject:</strong> ([^<]*)</p><pre class="wpcpm-emails__text">(.*?)</pre></div>#s', $editor, $preview );
ck( 'the preview is the email filled with the examples, ending with the footer as a student\'s does, the Student Report Card\'s address named once, in the body', array( html_entity_decode( $preview[1] ?? '', ENT_QUOTES ), false !== strpos( $preview[2] ?? '', 'key=EXAMPLE-KEY-NOT-A-REAL-LINK' ), substr( html_entity_decode( $preview[2] ?? '', ENT_QUOTES ), -strlen( "https://example.test/student-report-card/\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nThis email comes from the WordPress Credits program.\r\n" ) ), substr_count( $preview[2] ?? '', 'https://example.test/student-report-card/' ) ), array( '[WordPress Credits] Welcome to the WordPress Credits Program', true, "https://example.test/student-report-card/\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nThis email comes from the WordPress Credits program.\r\n", 1 ) );

echo "\n=== Who may press ===\n";

$GLOBALS['can'] = false;
$refused        = array();
foreach ( array( 'handle_save' => array(), 'handle_save, Send me a test' => array( 'wpcpm_then' => 'test' ), 'handle_restore' => array() ) as $pressed => $more ) {
	$refused[ $pressed ] = array( press( strtok( $pressed, ',' ), array( 'wpcpm_email' => 'invite-student', 'subject' => 'x', 'body' => 'y' ) + $more ), $GLOBALS['nonce_checked'] );
}
$GLOBALS['can'] = true;
ck( 'without the right to manage the program, each button refuses before it reads anything, its nonce included', $refused, array_fill_keys( array( 'handle_save', 'handle_save, Send me a test', 'handle_restore' ), array( 'died: You do not have permission to manage the program.', null ) ) );
ck( 'and nothing was stored or sent', array( array_key_exists( WPCPM_Mail_Templates::OPTION, $GLOBALS['opts'] ), $GLOBALS['mail'] ), array( false, array() ) );
$GLOBALS['nonce_fails'] = true;
ck( 'a nonce that fails is answered by WordPress\'s own page', press( 'handle_save', array( 'wpcpm_email' => 'invite-student', 'subject' => 'x', 'body' => '{set_password_link}' ) ), 'died: The link you followed has expired.' );
$GLOBALS['nonce_fails'] = false;
ck( 'and each form checks its own nonce', array( press( 'handle_save', array( 'wpcpm_email' => 'nope' ) ), $GLOBALS['nonce_checked'], press( 'handle_restore', array( 'wpcpm_email' => 'nope' ) ), $GLOBALS['nonce_checked'] ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates', 'wpcpm_email_save', 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates', 'wpcpm_email_restore' ) );

echo "\n=== Save ===\n";

// A refused save stores nothing, and the editor comes back with every byte that was typed.
new_administrator();
$typed = array(
	'subject' => 'Welcome <3 & "friends" &amp; co',
	'body'    => "Hi.\r\nSet a password, then sign in.\r\nWe <3 you & your \"team\" &amp; all.",
);
ck( 'a save without the set-password link goes back to the editor', press( 'handle_save', array( 'wpcpm_email' => 'invite-student' ) + $typed ), 'redirect: ' . $editor_url );
ck( 'and stores nothing', array_key_exists( WPCPM_Mail_Templates::OPTION, $GLOBALS['opts'] ), false );
$again = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
ck( 'which says why, and holds what was typed, byte for byte, each line break as its box counts it', array( false !== strpos( $again, '<div class="notice notice-error is-dismissible"><p>Nothing was saved: this email needs {set_password_link}.</p></div>' ), box( $again, 'wpcpm-email-subject' ), box( $again, 'wpcpm-email-body' ) ), array( true, $typed['subject'], str_replace( "\r\n", "\n", $typed['body'] ) ) );
ck( 'once: the typing is taken as the editor draws it', isset( $GLOBALS['umeta'][ $GLOBALS['uid'] ][ WPCPM_Flash::META ][ WPCPM_Typed_Text::KEPT_CHANNEL . 'email:invite-student' ] ), false );

new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'invite-student', 'subject' => 'Hi {studnet_name}', 'body' => '{set_password_link}' ) );
ck( 'a misspelled placeholder is refused by name, and nothing is stored', array( false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) ), 'Nothing was saved: {studnet_name} is not a placeholder of this email.' ), array_key_exists( WPCPM_Mail_Templates::OPTION, $GLOBALS['opts'] ) ), array( true, false ) );

new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'invite-student', 'subject' => 'Your link: {set_password_link}', 'body' => "Hi.\r\n\r\n{set_password_link}" ) );
ck( 'the set-password link in the subject is refused, saying it goes in the body, and nothing is stored', array( false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) ), 'Nothing was saved: {set_password_link} goes in the body, never in the subject.' ), array_key_exists( WPCPM_Mail_Templates::OPTION, $GLOBALS['opts'] ) ), array( true, false ) );

new_administrator();
$good = array(
	'subject' => '[{site_name}] Welcome <3',
	'body'    => "Hi.\r\n\r\n{set_password_link}\r\n\r\nWe <3 you & your team.",
);
ck( 'a good save goes back to the editor', press( 'handle_save', array( 'wpcpm_email' => 'invite-student' ) + $good ), 'redirect: ' . $editor_url );
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-student']['at'] = strtotime( '2026-10-10 12:05:00 UTC' );
$saved = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
preg_match_all( '#<form method="post" action="https://example.test/wp-admin/admin-post.php"([^>]*)>#', $saved, $saved_forms );
ck( 'which says so, and holds the edit as it was typed', array( false !== strpos( $saved, '<div class="notice notice-success is-dismissible"><p>Saved. The email is sent as you wrote it from now on.</p></div>' ), box( $saved, 'wpcpm-email-subject' ), box( $saved, 'wpcpm-email-body' ), false !== strpos( words_of( $saved ), 'State: Edited' ) ), array( true, $good['subject'], str_replace( "\r\n", "\n", $good['body'] ), true ) );
ck( 'Restore the original is there now, a form of its own, and asks first', array_map( 'trim', $saved_forms[1] ), array( 'class="wpcpm-emails__form" data-wpcpm-once data-wpcpm-busy="Working"', 'class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Restoring" data-wpcpm-confirm="Restore the original? Your edit is dropped, and the shipped text is sent from now on."' ) );
ck( 'and under it, folded, the shipped text', 1 === preg_match( '#<details class="wpcpm-emails__shipped"><summary>The shipped text</summary><p><strong>Subject:</strong> \[\{site_name\}\] Welcome to the WordPress Credits Program</p>#', $saved ), true );
preg_match( '#<tr><td>Invitation: student</td>.*?</tr>#s', screen( array( 'page' => 'wpcpm-tool-emails' ) ), $row );
ck( 'the Templates tab says the email is Edited, by whom, and when, in the site\'s time zone', array( false !== strpos( $row[0] ?? '', '>Edited</span>' ), false !== strpos( $row[0] ?? '', '<td>Ida Admin, 10 Oct 2026, 14:05</td>' ) ), array( true, true ) );

// An update changed the shipped text since: the edit is still sent, the editor says so, and the
// shipped text is open beside it.
new_administrator();
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-student']['shipped'] = md5( 'an older shipped text' );
$changed = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
ck( 'when the shipped text has changed since the edit, the editor says the edit is still sent and that saving it again clears this, and opens the shipped text', array( false !== strpos( $changed, 'An update changed the shipped text since this edit was saved.' ), false !== strpos( $changed, 'Saving your edit again clears this.' ), false !== strpos( $changed, '<details class="wpcpm-emails__shipped" open>' ), false !== strpos( words_of( $changed ), 'State: Edited, and the shipped text has changed since' ) ), array( true, true, true, true ) );
new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'invite-student' ) + $good );
ck( 'and saving the edit again clears it: the edit now carries the shipped text\'s fingerprint, and is Edited', WPCPM_Mail_Templates::state( 'invite-student' )['state'], 'edited' );

// An edit set aside: the state says why, and the editor that the shipped text is sent.
new_administrator();
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-student']['body'] = 'Hi {topic}.';
$aside = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
ck( 'an edit set aside says why, and that the shipped text is sent', false !== strpos( $aside, '<div class="notice notice-warning inline"><p>Edit set aside: it uses {topic}, which this email no longer has. The shipped text is sent until the edit is saved again in a form this email can use.</p></div>' ), true );

echo "\n=== Restore the original ===\n";

new_administrator();
ck( 'Restore the original drops the edit and goes back to the editor', array( press( 'handle_restore', array( 'wpcpm_email' => 'invite-student' ) ), WPCPM_Mail_Templates::edit( 'invite-student' ) ), array( 'redirect: ' . $editor_url, null ) );
$restored = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
ck( 'which says so, the boxes hold the shipped text again, and Restore is gone', array( false !== strpos( $restored, 'The original is back: the shipped text is sent from now on.' ), box( $restored, 'wpcpm-email-subject' ), substr_count( $restored, 'wpcpm_email_restore' ) ), array( true, $entry['subject'], 0 ) );
new_administrator();
press( 'handle_restore', array( 'wpcpm_email' => 'invite-student' ) );
ck( 'and a second press says there was nothing to restore', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) ), '<div class="notice notice-info is-dismissible"><p>There was no edit to restore: the shipped text is already the one sent.</p></div>' ), true );

new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'invite-student', 'subject' => $entry['subject'], 'body' => str_replace( "\n", "\r\n", $entry['body'] ) ) );
ck( 'a save of the shipped text as it was drawn stores nothing, and says so', array( WPCPM_Mail_Templates::edit( 'invite-student' ), false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) ), 'Saved. It reads as the shipped text, so it is sent as shipped.' ) ), array( null, true ) );

echo "\n=== Send me a test ===\n";

// The Save form's second button, pressed with the boxes as the editor drew them: here the shipped
// text, since nothing is edited.
$me              = new_administrator();
$GLOBALS['mail'] = array();
$as_drawn        = array(
	'wpcpm_email' => 'invite-student',
	'wpcpm_then'  => 'test',
	'subject'     => $entry['subject'],
	'body'        => str_replace( "\n", "\r\n", $entry['body'] ),
);
$went            = press( 'handle_save', $as_drawn + array( 'to' => 'someone@example.test', 'email' => 'someone@example.test', 'cc' => 'other@example.test', 'user_id' => '2' ) );
ck( 'a test goes to the Administrator who pressed and to nobody the press names: one email, to the presser\'s own address, with no header, and nothing is saved', array( $went, count( $GLOBALS['mail'] ), $GLOBALS['mail'][0]['to'] ?? null, $GLOBALS['mail'][0]['headers'] ?? null, WPCPM_Mail_Templates::edit( 'invite-student' ) ), array( 'redirect: ' . $editor_url, 1, $me->user_email, array(), null ) );
ck( 'marked [Test], under the template\'s own id, as a test', array( $GLOBALS['mail'][0]['subject'] ?? null, $GLOBALS['mail'][0]['context'] ?? null, $GLOBALS['mail'][0]['test'] ?? null ), array( '[Test] [WordPress Credits] Welcome to the WordPress Credits Program', 'invite-student', true ) );
ck( 'and the editor says where it went', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) ), 'The test is on its way to your own address, ' . $me->user_email . '.' ), true );

// Pressed with typing not saved yet, it sends nothing, since the test would show what no recipient
// gets, and gives the typing back as a refused save does.
new_administrator();
$GLOBALS['mail'] = array();
$unsaved         = array(
	'subject' => 'Welcome <3, "new" & improved',
	'body'    => "Hi.\r\n\r\n{set_password_link}\r\n\r\nNot saved & not sent.",
);
ck( 'pressed with typing not saved yet, it sends nothing, stores nothing and goes back to the editor', array( press( 'handle_save', array( 'wpcpm_email' => 'invite-student', 'wpcpm_then' => 'test' ) + $unsaved ), $GLOBALS['mail'], WPCPM_Mail_Templates::edit( 'invite-student' ) ), array( 'redirect: ' . $editor_url, array(), null ) );
$unsent = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
ck( 'which says so, and holds what was typed, byte for byte, each line break as its box counts it', array( false !== strpos( $unsent, '<div class="notice notice-error is-dismissible"><p>Nothing was sent: what you typed is not saved yet. Save it first, then send the test.</p></div>' ), box( $unsent, 'wpcpm-email-subject' ), box( $unsent, 'wpcpm-email-body' ) ), array( true, $unsaved['subject'], str_replace( "\r\n", "\n", $unsaved['body'] ) ) );

// With an edit saved, typing that differs from it only by what the cleaner would strip is typing not
// saved yet as well: a save of it would store something else, so a test of it would show an email
// no recipient gets. This suite's cleaner stand-in (bin/stubs/cleaners.php) strips a tag and a
// percent octet, as core's does.
new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'invite-student' ) + $good );
$edit_saved      = WPCPM_Mail_Templates::edit( 'invite-student' );
$GLOBALS['mail'] = array();
$stripped        = array(
	'subject' => $good['subject'],
	'body'    => str_replace( 'team', '<b>team</b>', $good['body'] ),
);
$refusal         = '<div class="notice notice-error is-dismissible"><p>Nothing was sent: what you typed is not saved yet. Save it first, then send the test.</p></div>';
new_administrator();
ck( 'with an edit saved, a test pressed with a word wrapped in a tag the cleaner strips sends nothing, stores nothing more and goes back to the editor', array( press( 'handle_save', array( 'wpcpm_email' => 'invite-student', 'wpcpm_then' => 'test' ) + $stripped ), $GLOBALS['mail'], WPCPM_Mail_Templates::edit( 'invite-student' ) ), array( 'redirect: ' . $editor_url, array(), $edit_saved ) );
$tagged = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
ck( 'which says so, and holds what was typed, tag and all, byte for byte, each line break as its box counts it', array( false !== strpos( $tagged, $refusal ), box( $tagged, 'wpcpm-email-subject' ), box( $tagged, 'wpcpm-email-body' ) ), array( true, $stripped['subject'], str_replace( "\r\n", "\n", $stripped['body'] ) ) );
new_administrator();
$GLOBALS['mail']     = array();
$stripped['subject'] = $good['subject'] . '%20';
$stripped['body']    = $good['body'];
press( 'handle_save', array( 'wpcpm_email' => 'invite-student', 'wpcpm_then' => 'test' ) + $stripped );
$octet = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
ck( 'and so does one with a percent octet the cleaner removes from the Subject: nothing is sent, and the Subject comes back as typed', array( $GLOBALS['mail'], false !== strpos( $octet, $refusal ), box( $octet, 'wpcpm-email-subject' ) ), array( array(), true, $stripped['subject'] ) );
WPCPM_Mail_Templates::restore( 'invite-student' );

new_administrator();
$GLOBALS['mail'] = array();
ck( 'a press naming an email the screen does not edit goes to the list, saying so, and sends nothing', array( press( 'handle_save', array( 'wpcpm_email' => 'call-booked', 'wpcpm_then' => 'test' ) ), $GLOBALS['mail'], false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails' ) ), 'Nothing was sent: the site has no such email.' ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates', array(), true ) );

echo "\n=== The Mentor calls, the Institutions, the Semester reports and the Sponsors ===\n";

// An Administrator who keeps Tokyo's clock opens the mentor's booking confirmation: an editor as an
// invitation's, its placeholders the call's, the call's time and the meeting link required, and a
// preview of a call tomorrow at 3 pm on that clock.
$tokyo                                                  = new DateTimeZone( 'Asia/Tokyo' );
$tomorrow                                               = ( new DateTimeImmutable( 'tomorrow 15:00', $tokyo ) )->getTimestamp();
$in_tokyo                                               = new_administrator();
$GLOBALS['umeta'][ $in_tokyo->ID ]['wpcpm_timezone']    = 'Asia/Tokyo';
$booking                                                = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'call-booked-mentor' ) );
preg_match_all( '#<tr><td><code>\{([a-z_]+)\}</code>( <span class="wpcpm-emails__required">Required</span>)?</td>#', $booking, $marks, PREG_SET_ORDER );
preg_match( '#<div class="wpcpm-emails__preview"><p><strong>Subject:</strong> ([^<]*)</p><pre class="wpcpm-emails__text">(.*?)</pre></div>#s', $booking, $booked );
ck(
	'the mentor\'s booking confirmation says who receives it and what sends it, and marks the call\'s time and the meeting link required',
	array( false !== strpos( words_of( $booking ), 'To: Mentor. ' . WPCPM_Mail_Templates::get( 'call-booked-mentor' )['trigger'] ), array_map( function ( $mark ) { return $mark[1] . ( empty( $mark[2] ) ? '' : ' (required)' ); }, $marks ) ),
	array( true, array( 'site_name', 'student_name', 'when (required)', 'time_zone', 'meeting_place (required)', 'topic', 'dashboard_link' ) )
);
ck(
	'and its preview is the email as a mentor reads it, the call tomorrow at 3 pm on the Administrator\'s own clock, the Mentor Report Card named once, and the footer',
	array( html_entity_decode( $booked[1] ?? '', ENT_QUOTES ), html_entity_decode( $booked[2] ?? '', ENT_QUOTES ) ),
	array(
		'[WordPress Credits] Call booked with Ada Okonjo',
		'Ada Okonjo booked a call with you on ' . wp_date( 'F j, Y', $tomorrow, $tokyo ) . " 3:00 pm to 3:30 pm.\n\nTimes are shown in Asia / Tokyo.\n\nWhere you will meet:\nhttps://meet.example.test/ben-lindqvist\n\nWhat the student would like to discuss:\nMy first plugin, and how to get it reviewed\n\nhttps://example.test/mentor-report-card/\n\n-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program.",
	)
);

// A save that drops the call's time, or puts the meeting link, which can carry the meeting's passcode,
// or the topic the student typed in the subject the Log keeps, is refused with its sentence, and the
// typing comes back.
$shipped_booking = WPCPM_Mail_Templates::get( 'call-booked-mentor' );
$refused_saves   = array();
foreach ( array(
	'the call\'s time dropped'              => array( $shipped_booking['subject'], str_replace( ' on {when}', '', $shipped_booking['body'] ) ),
	'the meeting link in the subject'       => array( $shipped_booking['subject'] . ' {meeting_place}', $shipped_booking['body'] ),
	'the typed topic in the subject'        => array( $shipped_booking['subject'] . ': {topic}', $shipped_booking['body'] ),
) as $what => $typed ) {
	new_administrator();
	press( 'handle_save', array( 'wpcpm_email' => 'call-booked-mentor', 'subject' => $typed[0], 'body' => $typed[1] ) );
	$back                   = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'call-booked-mentor' ) );
	$refused_saves[ $what ] = array( preg_match( '#<div class="notice notice-error is-dismissible"><p>([^<]*)</p></div>#', $back, $said ) ? html_entity_decode( $said[1], ENT_QUOTES ) : null, box( $back, 'wpcpm-email-subject' ) === $typed[0] && box( $back, 'wpcpm-email-body' ) === $typed[1] );
}
ck(
	'a save of a call\'s email without its time, or with its meeting link or a typed topic in the subject, is refused, stores nothing, and gives the typing back',
	array( $refused_saves, WPCPM_Mail_Templates::edit( 'call-booked-mentor' ) ),
	array(
		array(
			'the call\'s time dropped'        => array( 'Nothing was saved: this email needs {when}.', true ),
			'the meeting link in the subject' => array( 'Nothing was saved: {meeting_place} goes in the body, never in the subject.', true ),
			'the typed topic in the subject'  => array( 'Nothing was saved: {topic} goes in the body, never in the subject.', true ),
		),
		null,
	)
);

// A real edit of it saves, is what its test sends, and Restore the original drops it again, as for
// every email.
$edited = array(
	'subject' => $shipped_booking['subject'],
	'body'    => $shipped_booking['body'] . "\n\nSee you there.",
);
new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'call-booked-mentor' ) + $edited );
$was_edited      = WPCPM_Mail_Templates::state( 'call-booked-mentor' )['state'];
$GLOBALS['mail'] = array();
press( 'handle_save', array( 'wpcpm_email' => 'call-booked-mentor', 'wpcpm_then' => 'test' ) + $edited );
$edit_test = $GLOBALS['mail'][0]['body'] ?? '';
press( 'handle_restore', array( 'wpcpm_email' => 'call-booked-mentor' ) );
ck(
	'a real edit of it is saved, its test sends the edit, and Restore the original drops it',
	array( $was_edited, false !== strpos( $edit_test, "\n\nSee you there.\n\n-- \nWordPress Credits, WordPress Education\n" ), WPCPM_Mail_Templates::edit( 'call-booked-mentor' ), WPCPM_Mail_Templates::state( 'call-booked-mentor' )['state'] ),
	array( 'edited', true, null, 'shipped' )
);

// Every email of the four areas after the Invitations, through its editor and its Send me a test, as
// an Administrator who keeps Tokyo's clock opens and presses them. A preview or a test is only filled
// if each example of a placeholder its text uses is in it, where the text puts the placeholder: an
// empty fill leaves no brace, so "no placeholder left" cannot tell it from a full one. Nor may a
// placeholder its text uses have no example here: every one has, the dashboards being published in
// this suite. And each ends with the footer of its audience: the signature, the address of the
// audience's dashboard unless the text above it names that address already, and the closing line.
// The addresses are written out here, as the stand-ins above publish them, so a footer built for
// another audience, or with no values, cannot pass; an applicant or an audience of no one kind has no
// dashboard to name, and an email to several kinds of people names a sponsor's in the Sponsors area,
// whose one such email is the codes running low, to a sponsor's accounts and an Administrator, and a
// student's elsewhere.
$works = array();
$wants = array();
$plain = function ( $text ) {
	return str_replace( array( "\r\n", "\r" ), "\n", html_entity_decode( (string) $text, ENT_QUOTES ) );
};
$gaps  = function ( array $template, array $examples, $subject, $body ) use ( $plain ) {
	$gone = array();

	foreach ( array( 'subject' => $plain( $subject ), 'body' => $plain( $body ) ) as $part => $text ) {
		foreach ( WPCPM_Mail_Templates::placeholders_in( $template[ $part ] ) as $name ) {
			$example = $plain( $examples[ $name ] ?? '' );
			$example = 'subject' === $part ? preg_replace( '/\n/', ' ', $example ) : $example;

			if ( '' !== $example && false === strpos( $text, $example ) ) {
				$gone[] = $part . ':' . $name;
			}
		}
	}

	return $gone;
};
$dashboards = array(
	WPCPM_Mail_Catalog::TYPE_STUDENT     => 'https://example.test/student-report-card/',
	WPCPM_Mail_Catalog::TYPE_MENTOR      => 'https://example.test/mentor-report-card/',
	WPCPM_Mail_Catalog::TYPE_INSTITUTION => 'https://example.test/institution-dashboard/',
	WPCPM_Mail_Catalog::TYPE_SPONSOR     => 'https://example.test/sponsor-dashboard/',
	WPCPM_Mail_Catalog::TYPE_ADMIN       => 'https://example.test/administrator-dashboard/',
);
// An email to several kinds of people, by its area: the Sponsors' names a sponsor's, every other a
// student's.
$several = array(
	WPCPM_Mail_Catalog::MODULE_SPONSORS => 'https://example.test/sponsor-dashboard/',
	''                                  => 'https://example.test/student-report-card/',
);
// What follows the "-- " line of an email in plain text, and what its template's audience wants there:
// a text that ends with a line break ends with one after the footer as well. A text with no such line
// has no footer, and no footer is ever what is wanted, so what it is given never equals what is asked.
$footer_of = function ( array $template, $text ) use ( $dashboards, $several ) {
	$at = strrpos( $text, "\n-- \n" );

	if ( false === $at ) {
		return array( null, 'a footer after a "-- " line' );
	}

	$page = '' === $template['audience'] ? ( $several[ $template['module'] ] ?? $several[''] ) : ( $dashboards[ $template['audience'] ] ?? '' );

	return array(
		substr( $text, $at + 5 ),
		"WordPress Credits, WordPress Education\n" . ( '' !== $page && false === strpos( substr( $text, 0, $at ), $page ) ? $page . "\n" : '' ) . 'This email comes from the WordPress Credits program.' . ( "\n" === substr( $template['body'], -1 ) ? "\n" : '' ),
	);
};
foreach ( WPCPM_Mail_Templates::all() as $id => $template ) {
	if ( WPCPM_Mail_Catalog::MODULE_INVITATIONS === $template['module'] ) {
		continue;
	}

	$presser                                            = new_administrator();
	$GLOBALS['umeta'][ $presser->ID ]['wpcpm_timezone'] = 'Asia/Tokyo';
	$page                                               = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => $id ) );
	$examples                                           = WPCPM_Mail_Templates::example_values( $id );
	$bare                                               = array_values(
		array_filter(
			WPCPM_Mail_Templates::placeholders_in( $template['subject'] . "\n" . $template['body'] ),
			function ( $name ) use ( $examples ) {
				return '' === ( $examples[ $name ] ?? '' );
			}
		)
	);
	$GLOBALS['mail']                                    = array();
	$went                                               = press( 'handle_save', array( 'wpcpm_email' => $id, 'wpcpm_then' => 'test', 'subject' => $template['subject'], 'body' => str_replace( "\n", "\r\n", $template['body'] ), 'to' => 'someone@example.test' ) );
	$test                                               = $GLOBALS['mail'];

	preg_match( '#<div class="wpcpm-emails__preview">(.*?)</div>#s', $page, $shown );
	preg_match( '#<p><strong>Subject:</strong> ([^<]*)</p>#', $page, $shown_subject );
	preg_match( '#<pre class="wpcpm-emails__text">(.*?)</pre>#s', $page, $shown_body );

	$shown_footer = $footer_of( $template, $plain( $shown_body[1] ?? '' ) );
	$test_footer  = $footer_of( $template, str_replace( "\r\n", "\n", $test[0]['body'] ?? '' ) );
	$works[ $id ] = array(
		'bare'    => $bare,
		'editor'  => false !== strpos( $page, '<h2>' . esc_html( $template['label'] ) . '</h2>' ) && false !== strpos( $page, '>Save</button>' ) && false !== strpos( $page, 'value="test">Send me a test</button>' ),
		'preview' => array(
			'left'   => isset( $shown[1] ) ? WPCPM_Mail_Templates::placeholders_in( html_entity_decode( $shown[1], ENT_QUOTES ) ) : null,
			'gone'   => $gaps( $template, $examples, $shown_subject[1] ?? '', $shown_body[1] ?? '' ),
			'footer' => $shown_footer[0],
		),
		'test'    => array(
			'sent'   => array( $went, count( $test ), $test[0]['to'] ?? null, $test[0]['headers'] ?? null, 0 === strpos( $test[0]['subject'] ?? '', '[Test] ' ), $test[0]['context'] ?? null, $test[0]['test'] ?? null ),
			'braces' => false !== strpbrk( ( $test[0]['subject'] ?? '' ) . ( $test[0]['body'] ?? '' ), '{}' ),
			'gone'   => $gaps( $template, $examples, $test[0]['subject'] ?? '', $test[0]['body'] ?? '' ),
			'footer' => $test_footer[0],
		),
	);
	$wants[ $id ] = array(
		'bare'    => array(),
		'editor'  => true,
		'preview' => array( 'left' => array(), 'gone' => array(), 'footer' => $shown_footer[1] ),
		'test'    => array(
			'sent'   => array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=' . $id, 1, $presser->user_email, array(), true, $id, true ),
			'braces' => false,
			'gone'   => array(),
			'footer' => $test_footer[1],
		),
	);

	if ( 'series-joined-student' === $id ) {
		$series_test = $test[0]['body'] ?? '';
	}

	if ( 'report-drafted' === $id ) {
		$drafted_shipped = array( $plain( $shown_body[1] ?? '' ), str_replace( "\r\n", "\n", $test[0]['body'] ?? '' ) );
	}
}
ck( 'every email of the Mentor calls, the Institutions, the Semester reports and the Sponsors opens in the editor, with Save and Send me a test, every placeholder of its text given an example, its preview filled with them; and its test, pressed as drawn, is one email, to the presser alone, with no header, no Reply-To among them, marked [Test] and Test, under its own id, with no brace left and each example in it where its text puts the placeholder', $works, $wants );
ck(
	'a series\' test names its three weekly sessions on the presser\'s clock, one to a line',
	false !== strpos(
		$series_test ?? '',
		"You are on 3 group sessions with Ben Lindqvist:\n" . implode(
			"\n",
			array_map(
				function ( $days ) use ( $tomorrow, $tokyo ) {
					return wp_date( 'F j, Y', $tomorrow + $days * DAY_IN_SECONDS, $tokyo ) . ' 3:00 pm to 3:30 pm';
				},
				array( 0, 7, 14 )
			)
		) . "\n\nTimes are shown in Asia / Tokyo."
	),
	true
);

// An edit of an email whose shipped body ends with a line break is previewed and tested ending as the
// shipped one does, its signature and footer included, as it is sent: the Semester reports email the
// daily job drafts, with a line the Administrators added above its text (the fix wave).
// Saved first, then tested as the editor draws the edit, since a test sends what is saved.
$presser = new_administrator();
$drafted = WPCPM_Mail_Templates::get( 'report-drafted' );
press( 'handle_save', array( 'wpcpm_email' => 'report-drafted', 'subject' => $drafted['subject'], 'body' => str_replace( "\n", "\r\n", "A line the Administrators added.\n\n" . $drafted['body'] ) ) );
$edited          = WPCPM_Mail_Templates::edit( 'report-drafted' );
$GLOBALS['mail'] = array();
press( 'handle_save', array( 'wpcpm_email' => 'report-drafted', 'wpcpm_then' => 'test', 'subject' => $edited['subject'] ?? '', 'body' => str_replace( "\n", "\r\n", $edited['body'] ?? '' ), 'to' => 'someone@example.test' ) );
$drafted_test = str_replace( "\r\n", "\n", $GLOBALS['mail'][0]['body'] ?? '' );
preg_match( '#<pre class="wpcpm-emails__text">(.*?)</pre>#s', screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'report-drafted' ) ), $drafted_shown );
WPCPM_Mail_Templates::restore( 'report-drafted' );
ck(
	'an edit of an email whose shipped body ends with a line break, stored without it, is previewed and tested ending as the shipped one, after its signature and footer',
	array( "\n" === substr( (string) ( $edited['body'] ?? '' ), -1 ), $plain( $drafted_shown[1] ?? '' ), $drafted_test, "\n" === substr( $drafted_test, -1 ), WPCPM_Mail_Templates::state( 'report-drafted' )['state'] ),
	array( false, "A line the Administrators added.\n\n" . ( $drafted_shipped[0] ?? '' ), "A line the Administrators added.\n\n" . ( $drafted_shipped[1] ?? '' ), true, 'shipped' )
);

// A save of an Institutions or a Sponsors email that drops a placeholder the email needs (a link
// carrying a secret, what an Administrator or a sponsor wrote, a post's title), or that puts one that
// goes in the body alone (a secret, what somebody wrote, a code's last characters) in the subject the
// Log keeps, is refused with its sentence, stores nothing, and gives the typing back. For every email
// of the two areas and every placeholder of it that is needed or body-only, one at a time, so a
// placeholder given either mark later is held to it with no line added here; only the cases that do
// not come out so are listed, the numbers of cases beside them.
$dropped        = 0;
$moved          = 0;
$refusals_wrong = array();
foreach ( WPCPM_Mail_Templates::all() as $id => $template ) {
	if ( ! in_array( $template['module'], array( WPCPM_Mail_Catalog::MODULE_INSTITUTIONS, WPCPM_Mail_Catalog::MODULE_SPONSORS ), true ) ) {
		continue;
	}

	$cases = array();

	foreach ( $template['placeholders'] as $name => $placeholder ) {
		if ( ! empty( $placeholder['required'] ) ) {
			++$dropped;
			$cases[ $id . ' without {' . $name . '}' ] = array( $template['subject'], str_replace( '{' . $name . '}', '', $template['body'] ), 'Nothing was saved: this email needs {' . $name . '}.' );
		}

		if ( ! empty( $placeholder['body_only'] ) ) {
			++$moved;
			$cases[ $id . ' with {' . $name . '} in the subject' ] = array( $template['subject'] . ' {' . $name . '}', $template['body'], 'Nothing was saved: {' . $name . '} goes in the body, never in the subject.' );
		}
	}

	foreach ( $cases as $what => $case ) {
		new_administrator();
		press( 'handle_save', array( 'wpcpm_email' => $id, 'subject' => $case[0], 'body' => $case[1] ) );
		$back = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => $id ) );
		$got  = array( preg_match( '#<div class="notice notice-error is-dismissible"><p>([^<]*)</p></div>#', $back, $said ) ? html_entity_decode( $said[1], ENT_QUOTES ) : null, box( $back, 'wpcpm-email-subject' ) === $case[0] && box( $back, 'wpcpm-email-body' ) === $case[1], WPCPM_Mail_Templates::edit( $id ) );

		if ( array( $case[2], true, null ) !== $got ) {
			$refusals_wrong[ $what ] = $got;
		}
	}
}
ck(
	'a save of an Institutions or a Sponsors email without a placeholder it needs, or with one that goes in the body alone in the subject, is refused with its sentence, stores nothing, and gives the typing back, for every such placeholder of the 33 emails of the two areas',
	array( $dropped, $moved, $refusals_wrong ),
	array( 18, 25, array() )
);

// An agreement returned, previewed as a member of the institution reads it: sent back today, on the
// site's clock, by an invented Administrator, with an example note, and the Institution Dashboard in
// the footer, since the body names none. Previewed twice, by an Administrator whose clock and the
// site's are a day apart at every hour, on the two sides of the date line, each the site's once: the
// day it names can then be only the site's, never the Administrator's or UTC's. Zones the server knows
// by name, since one it does not know is read as the site's.
$site_zone = $GLOBALS['opts']['timezone_string'];
$previews  = array();
$wanted    = array();
foreach ( array( array( 'Pacific/Pago_Pago', 'Pacific/Kiritimati' ), array( 'Pacific/Kiritimati', 'Pacific/Pago_Pago' ) ) as $zones ) {
	$who                                                = 'the site in ' . $zones[0] . ', the Administrator in ' . $zones[1];
	$GLOBALS['opts']['timezone_string']                 = $zones[0];
	$sent_back_on                                       = ( new DateTimeImmutable( 'now', new DateTimeZone( $zones[0] ) ) )->format( 'Y-m-d' );
	$looking                                            = new_administrator();
	$GLOBALS['umeta'][ $looking->ID ]['wpcpm_timezone'] = $zones[1];
	$returned                                           = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'agreement-returned' ) );
	preg_match( '#<div class="wpcpm-emails__preview"><p><strong>Subject:</strong> ([^<]*)</p><pre class="wpcpm-emails__text">(.*?)</pre></div>#s', $returned, $shown_returned );
	$previews[ $who ] = array( html_entity_decode( $shown_returned[1] ?? '', ENT_QUOTES ), str_replace( "\r\n", "\n", html_entity_decode( $shown_returned[2] ?? '', ENT_QUOTES ) ) );
	$wanted[ $who ]   = array(
		'[WordPress Credits] Your signed agreement needs a change',
		'Your signed Collaboration Agreement was sent back on ' . $sent_back_on . " by Cleo Marsh, with this note:\n\nPlease sign page 2 as well, and date it.\n\nUpload the corrected copy from the agreement panel on the site. Replying to this message reaches the Administrator who wrote the note.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/institution-dashboard/\nThis email comes from the WordPress Credits program.",
	);
}
$GLOBALS['opts']['timezone_string'] = $site_zone;
ck(
	'the agreement returned is previewed as a member reads it: sent back today on the site\'s clock, whatever the Administrator\'s, by an invented Administrator, with the note, and the Institution Dashboard in the footer',
	array( array_values( array_diff( array( 'Pacific/Pago_Pago', 'Pacific/Kiritimati' ), DateTimeZone::listIdentifiers() ) ), $previews ),
	array( array(), $wanted )
);

// An edit of a subject that holds a title as plain text, "<3" and all, is saved as the cleaner keeps
// it, "&lt;3", its test sends it as typed, "<3", and Restore the original drops it.
$post_edit = array(
	'subject' => 'Your post "{post_title}" <3 needs a change',
	'body'    => WPCPM_Mail_Templates::get( 'sponsor-post-returned' )['body'],
);
new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'sponsor-post-returned' ) + $post_edit );
$post_saved      = WPCPM_Mail_Templates::state( 'sponsor-post-returned' )['state'];
$post_stored     = WPCPM_Mail_Templates::edit( 'sponsor-post-returned' )['subject'] ?? null;
$GLOBALS['mail'] = array();
press( 'handle_save', array( 'wpcpm_email' => 'sponsor-post-returned', 'wpcpm_then' => 'test' ) + $post_edit );
$post_test = $GLOBALS['mail'][0]['subject'] ?? '';
press( 'handle_restore', array( 'wpcpm_email' => 'sponsor-post-returned' ) );
ck(
	'an edit of a returned post\'s subject, a "<" in it, is saved as the cleaner keeps it, its test sends it as typed, and Restore the original drops it',
	array( $post_saved, $post_stored, $post_test, WPCPM_Mail_Templates::edit( 'sponsor-post-returned' ) ),
	array( 'edited', 'Your post "{post_title}" &lt;3 needs a change', '[Test] Your post "Our first study day" <3 needs a change', null )
);

echo "\n=== The Signature and footer tab ===\n";

new_administrator();
$footer_tab = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'footer' ) );
preg_match_all( '#<tr><td><code>\{([a-z_]+)\}</code>#', $footer_tab, $footer_placeholders );
ck( 'it is the editor without a Subject, its box holding the shipped footer', array( false !== strpos( $footer_tab, '<h2>Signature and footer</h2>' ), box( $footer_tab, 'wpcpm-email-subject' ), box( $footer_tab, 'wpcpm-email-body' ), false !== strpos( $footer_tab, 'All templates' ) ), array( true, null, WPCPM_Mail_Templates::footer_shipped(), false ) );
ck( 'it says where the block goes, the separator line shown as code, and lists its three placeholders', array( false !== strpos( $footer_tab, '<p class="description">Every email the plugin sends ends with this block, after a line holding <code>-- </code>. It is not added to the emails WordPress and the Two Factor plugin send themselves.</p>' ), $footer_placeholders[1] ), array( true, array( 'site_name', 'dashboard_link', 'program_contact' ) ) );
ck( 'its preview ends a line of text with the footer', false !== strpos( $footer_tab, "The text of the email.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/administrator-dashboard/\nThis email comes from the WordPress Credits program.</pre>" ), true );
preg_match( '#<tr><td><code>\{dashboard_link\}</code>.*?</td><td>.*?</td><td>(.*?)</td></tr>#s', $footer_tab, $footer_dashboard_row );
$footer_table_address = $footer_dashboard_row[1] ?? null;
ck( 'and the address its table gives as the example of {dashboard_link} is the one its preview fills in: the Administrator Dashboard\'s', array( $footer_table_address, null !== $footer_table_address && false !== strpos( $footer_tab, "-- \nWordPress Credits, WordPress Education\n" . $footer_table_address . "\nThis email comes from" ) ), array( 'https://example.test/administrator-dashboard/', true ) );

new_administrator();
ck( 'a save of the footer goes back to its tab, and every email ends with it', array( press( 'handle_save', array( 'wpcpm_email' => 'footer', 'body' => "Kind regards,\r\n{site_name}" ) ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer', "Hi.\n\n-- \nKind regards,\nWordPress Credits" ) );
ck( 'and the tab says so', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'footer' ) ), 'Saved. Every email ends with the signature and footer as you wrote it from now on.' ), true );

$me              = new_administrator();
$GLOBALS['mail'] = array();
press( 'handle_save', array( 'wpcpm_email' => 'footer', 'wpcpm_then' => 'test', 'body' => "Kind regards,\r\n{site_name}" ) );
ck( 'its test, pressed with the box as drawn, is one email to the presser, under the footer\'s test, ending with the edited footer', array( count( $GLOBALS['mail'] ), $GLOBALS['mail'][0]['to'] ?? null, $GLOBALS['mail'][0]['context'] ?? null, substr( $GLOBALS['mail'][0]['body'] ?? '', -strlen( "-- \nKind regards,\nWordPress Credits" ) ) ), array( 1, $me->user_email, 'footer-test', "-- \nKind regards,\nWordPress Credits" ) );
new_administrator();
$GLOBALS['mail'] = array();
ck( 'and pressed with typing not saved yet, it sends nothing and gives the typing back, as on a template\'s editor', array( press( 'handle_save', array( 'wpcpm_email' => 'footer', 'wpcpm_then' => 'test', 'body' => "Kind regards,\r\nThe team" ) ), $GLOBALS['mail'], box( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'footer' ) ), 'wpcpm-email-body' ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer', array(), "Kind regards,\nThe team" ) );

new_administrator();
ck( 'and Restore the original brings the shipped footer back', array( press( 'handle_restore', array( 'wpcpm_email' => 'footer' ) ), WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER )['state'] ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer', 'shipped' ) );

echo "\n=== Contact address in emails ===\n";

new_administrator();
$settings_part = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'footer' ) );
preg_match( '#<div class="wpcpm-card"><h2 id="settings">Settings</h2>.*?</form>#s', $settings_part, $section );
ck( 'the tab opens on the tool\'s Settings section, above the footer: one form posting the tool\'s scope, holding Contact address in emails, empty as it ships', array( isset( $section[0] ), false !== strpos( $section[0] ?? '', 'name="wpcpm_tab" value="tool:emails"' ), 1 === preg_match( '#<label for="wpcpm-mail_contact">Contact address in emails</label></th><td><input type="email" id="wpcpm-mail_contact" name="mail_contact" value=""#', $section[0] ?? '' ), strpos( $settings_part, 'id="settings"' ) < strpos( $settings_part, '<h2>Signature and footer</h2>' ) ), array( true, true, true, true ) );
ck( 'while it is empty, the preview leaves out the sentence that names it', 1 === preg_match( '#<div class="wpcpm-emails__preview">[^<]*<pre class="wpcpm-emails__text">[^<]*Questions\?#', $settings_part ), false );

new_administrator();
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array( 'mail_contact' => 'programs@example.test' );
$set = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'footer' ) );
ck( 'set, the field holds it, and the footer names it', array( false !== strpos( $set, 'name="mail_contact" value="programs@example.test"' ), false !== strpos( $set, 'Questions? Write to programs@example.test.</pre>' ), substr( WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'institution-applied' ), -strlen( 'Questions? Write to programs@example.test.' ) ) ), array( true, true, 'Questions? Write to programs@example.test.' ) );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array();

new_administrator();
WPCPM_Flash::set( 'emails-contact-refused', true );
ck( 'and a save refused for what was typed is said on the tab', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'footer' ) ), '<div class="notice notice-error"><p>Contact address in emails was not saved: what was typed is not an email address. The one saved before is kept.</p></div>' ), true );
ck( 'the tool keeps that one setting, and its Settings section is on the Signature and footer tab', array( ( new WPCPM_Emails() )->settings_keys(), ( new WPCPM_Emails() )->settings_url() ), array( array( 'mail_contact' ), 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
