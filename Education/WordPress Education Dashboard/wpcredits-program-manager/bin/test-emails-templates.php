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
 * - **The Templates tab** lists the templates by area, each with who receives it, what sends it,
 *   where it stands and who edited it last, and an Edit link to its editor; the Invitations alone,
 *   and nothing about what is not there.
 * - **The editor**: the boxes hold the shipped text, then the edit read back as it was typed; the
 *   placeholders say what they mean, the required one marked; the preview is filled with the
 *   examples and ends with the footer; Restore asks first, and is there only for an edit.
 * - **The buttons**: Administrators only, the capability asked before the nonce; a refused save
 *   stores nothing and the editor comes back with what was typed, byte for byte; Send me a test goes
 *   to the presser's own address and to nobody a press names, and only for the text as saved:
 *   pressed with typing not saved yet, it sends nothing and gives the typing back as a refused save
 *   does.
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

$GLOBALS['opts']   = array( 'timezone_string' => 'Europe/Warsaw' );
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
function wp_timezone() { return new DateTimeZone( (string) get_option( 'timezone_string', 'UTC' ) ); }
function wp_date( $format, $timestamp = null ) { return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( wp_timezone() )->format( $format ); }
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

/** The dashboards, each published at an address of its own. */
class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student-report-card/'; }
}
/** As above. */
class WPCPM_Administrators_Dashboard {
	public static function page_url() { return 'https://example.test/administrator-dashboard/'; }
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
ck( 'the templates are listed by area: the Invitations, and nothing about any other', $areas[1], array( 'Invitations' ) );
ck(
	'each row names the email, who receives it, what sends it, its state and who edited it last, and links its editor by its name',
	array_map( function ( $row ) { return array( html_entity_decode( $row[1], ENT_QUOTES ), $row[2], $row[5], $row[6], $row[7], $row[8] ); }, $rows ),
	array(
		array( 'Invitation: student', 'Student', 'Shipped text', 'Not edited', $editor_url, 'Invitation: student' ),
		array( 'Invitation: mentor', 'Mentor', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-mentor', 'Invitation: mentor' ),
		array( 'Invitation: institution', 'Institution member', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-institution', 'Invitation: institution' ),
		array( 'Invitation: institution, agreement on file', 'Institution member', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-institution-settled', 'Invitation: institution, agreement on file' ),
		array( 'Invitation: sponsor', 'Sponsor member', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-sponsor', 'Invitation: sponsor' ),
	)
);
ck( 'and what sends each is the registry\'s sentence', html_entity_decode( $rows[0][3] ?? '', ENT_QUOTES ), WPCPM_Mail_Templates::get( 'invite-student' )['trigger'] );
$templates_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/emails.css' );
preg_match( '#\.wpcpm-emails__scroll\s*\{([^{}]*)\}#', $templates_css, $scroll_rule );
ck( 'each area\'s table is printed inside a box that scrolls sideways on a narrow screen, held by a position, so the page does not', array( substr_count( $home, '<table' ), preg_match_all( '#<h2>[^<]*</h2><div class="wpcpm-emails__scroll"><table class="widefat striped wpcpm-emails__templates">.*?</table></div>#s', $home ), 1 === preg_match( '/(^|;)\s*overflow-x\s*:\s*auto\s*;/', $scroll_rule[1] ?? '' ), 1 === preg_match( '/(^|;)\s*position\s*:\s*relative\s*;/', $scroll_rule[1] ?? '' ) ), array( 1, 1, true, true ) );
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
