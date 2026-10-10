<?php
/**
 * The capture: every email the site sends becomes one row per recipient.
 *
 * WordPress's hooks are stood in for with their priorities kept, since the capture's place among
 * other plugins' callbacks is what it relies on, and `send()` runs them the way `wp_mail()` 7.1
 * does: the `wp_mail` filter, `pre_wp_mail`, then one outcome, handed what `wp_mail()` hands it
 * (`to` split on commas, and only the headers it did not read out itself). A `WPCPM_Mail` stands in
 * for the real one, so that only the context passes between the two.
 *
 * Run from the plugin root:  php bin/test-mail-capture.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['opts']  = array();
$GLOBALS['hooks'] = array();
$GLOBALS['users'] = array();

function __( $s, $d = null ) { return $s; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { $had = array_key_exists( $k, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $k ] ); return $had; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooks'][ $h ][ $p ][] = array( $cb, $n ); return true; }
function add_action( $h, $cb, $p = 10, $n = 1 ) { return add_filter( $h, $cb, $p, $n ); }
/** A hook's callbacks in the order WordPress runs them: by priority, then as added. */
function hooked( $h ) {
	$by = isset( $GLOBALS['hooks'][ $h ] ) ? $GLOBALS['hooks'][ $h ] : array();
	ksort( $by );
	$out = array();
	foreach ( $by as $list ) {
		foreach ( $list as $f ) {
			$out[] = $f;
		}
	}
	return $out;
}
function apply_filters( $h, $v, ...$rest ) {
	foreach ( hooked( $h ) as $f ) {
		$v = call_user_func_array( $f[0], array_slice( array_merge( array( $v ), $rest ), 0, max( 1, $f[1] ) ) );
	}
	return $v;
}
function do_action( $h, ...$args ) {
	foreach ( hooked( $h ) as $f ) {
		call_user_func_array( $f[0], array_slice( $args, 0, max( 1, $f[1] ) ) );
	}
}
/**
 * Core's `is_email()` in short: the address as written is valid or it is false. A space or a second
 * @ in it is not valid.
 */
function is_email( $e ) {
	$e = (string) $e;

	if ( strlen( $e ) < 6 || false === strpos( $e, '@', 1 ) ) {
		return false;
	}

	list( $local, $domain ) = explode( '@', $e, 2 );

	if ( ! preg_match( '/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]+$/', $local ) || preg_match( '/\.{2,}/', $domain ) || trim( $domain, " \t\n\r\0\x0B." ) !== $domain ) {
		return false;
	}

	$subs = explode( '.', $domain );

	if ( 2 > count( $subs ) ) {
		return false;
	}

	foreach ( $subs as $sub ) {
		if ( trim( $sub, " \t\n\r\0\x0B-" ) !== $sub || ! preg_match( '/^[a-z0-9-]+$/i', $sub ) ) {
			return false;
		}
	}

	return $e;
}
/**
 * Core's `sanitize_email()` in short: it REPAIRS an address, dropping what one may not hold, so
 * `ada lin@example.test` becomes `adalin@example.test` and `ada@@example.test` becomes
 * `ada@example.test`. The capture must not use it: an address that is not valid as written is not
 * one `wp_mail()` accepts.
 */
function sanitize_email( $e ) {
	$e = (string) $e;

	if ( strlen( $e ) < 6 || false === strpos( $e, '@', 1 ) ) {
		return '';
	}

	list( $local, $domain ) = explode( '@', $e, 2 );

	$local  = preg_replace( '/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]/', '', $local );
	$domain = trim( preg_replace( '/\.{2,}/', '', $domain ), " \t\n\r\0\x0B." );
	$subs   = array();

	foreach ( explode( '.', $domain ) as $sub ) {
		$sub = preg_replace( '/[^a-z0-9-]+/i', '', trim( $sub, " \t\n\r\0\x0B-" ) );

		if ( '' !== $sub ) {
			$subs[] = $sub;
		}
	}

	return '' === $local || 2 > count( $subs ) ? '' : $local . '@' . implode( '.', $subs );
}
function get_user_by( $field, $value ) { return $GLOBALS['users'][ strtolower( (string) $value ) ] ?? false; }
function user_can( $user, $cap ) { return in_array( $cap, $user->caps ?? array(), true ); }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function wp_check_invalid_utf8( $s, $strip = false ) { return (string) $s; }
function wp_next_scheduled( $h ) { return false; }
function wp_schedule_event( $t, $r, $h ) { return true; }

class WP_Error {
	private $m, $d;
	public function __construct( $c = '', $m = '', $d = null ) { $this->m = $m; $this->d = $d; }
	public function get_error_message() { return $this->m; }
	public function get_error_data() { return $this->d; }
}
class WP_User {
	public $ID, $display_name, $user_email, $roles, $caps;
	public function __construct( $id, $name, $email, $roles, $caps = array() ) { $this->ID = $id; $this->display_name = $name; $this->user_email = $email; $this->roles = $roles; $this->caps = $caps; }
	public function exists() { return $this->ID > 0; }
}
class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student';
	const ROLE_MENTOR = 'wpcpm_mentor';
	const ROLE_INSTITUTION = 'wpcpm_institution';
	const ROLE_SPONSOR = 'wpcpm_sponsor';
	const ROLE_ADMIN = 'administrator';
	const CAP_MANAGE = 'wpcpm_manage_program';
}
// Stands in for the real class: only the context passes between the two.
class WPCPM_Mail {
	public static $ctx = '';
	public static function take_context() { $c = self::$ctx; self::$ctx = ''; return $c; }
}

require __DIR__ . '/stubs/sqlite-wpdb.php';
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();
$GLOBALS['wpdb']->create_mail_log();

require __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require __DIR__ . '/../includes/mail/class-wpcpm-mail-log.php';
require __DIR__ . '/../includes/mail/class-wpcpm-mail-capture.php';

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

$GLOBALS['users']['ada@example.test'] = new WP_User( 7, 'Ada Lin', 'ada@example.test', array( 'wpcpm_student' ) );
$GLOBALS['users']['ben@example.test'] = new WP_User( 8, 'Ben Ode', 'ben@example.test', array( 'wpcpm_mentor' ) );
$GLOBALS['users']['cy@example.test']  = new WP_User( 9, 'Cy Ray', 'cy@example.test', array( 'administrator' ), array( 'wpcpm_manage_program' ) );
$GLOBALS['users']['two@example.test'] = new WP_User( 10, 'Two Roles', 'two@example.test', array( 'wpcpm_student', 'wpcpm_mentor' ) );

// Booted as the plugin boots it: the store's init() starts the capture.
WPCPM_Mail_Log::init();

/**
 * Send through the hooks the way `wp_mail()` 7.1 runs them, and hand each outcome what it hands it.
 *
 * @param string|string[] $to      To.
 * @param string          $subject Subject.
 * @param string|string[] $headers Headers, as a string or an array of lines.
 * @param string          $outcome 'sent', 'failed' or 'taken' (another plugin's `pre_wp_mail`).
 * @return mixed What wp_mail() would return.
 */
function send( $to, $subject, $headers = '', $outcome = 'sent' ) {
	$atts = apply_filters( 'wp_mail', array( 'to' => $to, 'subject' => $subject, 'message' => 'BODY-NEVER-STORED', 'headers' => $headers, 'attachments' => array(), 'embeds' => array() ) );
	$pre  = apply_filters( 'pre_wp_mail', 'taken' === $outcome ? true : null, $atts );

	if ( null !== $pre ) {
		return $pre;
	}

	// What wp_mail() has made of the email by its outcome: `to` split on commas, and the headers it
	// kept once it read Cc, Bcc, From, Reply-To and Content-Type out of them, by name.
	$kept  = array();
	$lines = is_array( $atts['headers'] ) ? $atts['headers'] : explode( "\n", str_replace( "\r\n", "\n", (string) $atts['headers'] ) );

	foreach ( $lines as $line ) {
		if ( false === strpos( (string) $line, ':' ) ) {
			continue;
		}

		list( $name, $content ) = explode( ':', trim( (string) $line ), 2 );

		if ( ! in_array( strtolower( trim( $name ) ), array( 'cc', 'bcc', 'from', 'reply-to', 'content-type' ), true ) ) {
			$kept[ trim( $name ) ] = trim( $content );
		}
	}

	$mail_data = array( 'to' => is_array( $atts['to'] ) ? $atts['to'] : explode( ',', (string) $atts['to'] ), 'subject' => $atts['subject'], 'message' => $atts['message'], 'headers' => $kept, 'attachments' => array(), 'embeds' => array() );

	if ( 'failed' === $outcome ) {
		$mail_data['phpmailer_exception_code'] = 2;
		do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'Could not instantiate mail function.', $mail_data ) );

		return false;
	}

	do_action( 'wp_mail_succeeded', $mail_data );

	return true;
}
function rows() { return WPCPM_Mail_Log::find( array() )['rows']; }
function wipe() { $GLOBALS['wpdb']->drop_mail_log(); $GLOBALS['wpdb']->create_mail_log(); WPCPM_Mail_Log::reset(); WPCPM_Mail_Capture::reset(); }
/** Where the capture sits on a hook: its priorities there, in order. */
function priorities( $h ) {
	$found = array();
	foreach ( isset( $GLOBALS['hooks'][ $h ] ) ? $GLOBALS['hooks'][ $h ] : array() as $p => $list ) {
		foreach ( $list as $f ) {
			if ( is_array( $f[0] ) && 'WPCPM_Mail_Capture' === $f[0][0] ) {
				$found[] = array( $f[0][1], $p, $f[1] );
			}
		}
	}
	return $found;
}

ck( 'the capture opens an email first on wp_mail and notes it last there', priorities( 'wp_mail' ), array( array( 'open', PHP_INT_MIN, 1 ), array( 'note', PHP_INT_MAX, 1 ) ) );
ck( 'reads another plugin\'s take-over last, and each outcome first', array( priorities( 'pre_wp_mail' ), priorities( 'wp_mail_succeeded' ), priorities( 'wp_mail_failed' ) ), array( array( array( 'taken_over', PHP_INT_MAX, 2 ) ), array( array( 'succeeded', PHP_INT_MIN, 1 ) ), array( array( 'failed', PHP_INT_MIN, 1 ) ) ) );
ck( 'and writes what is left at shutdown', priorities( 'shutdown' ), array( array( 'flush', 10, 1 ) ) );
ck( 'and every naming filter is hooked', count( array_intersect( array_keys( WPCPM_Mail_Catalog::wordpress_filters() ), array_keys( $GLOBALS['hooks'] ) ) ), count( WPCPM_Mail_Catalog::wordpress_filters() ) );

// A plugin email.
WPCPM_Mail::$ctx = 'call-booked';
send( 'Ada Lin <ada@example.test>', 'Call booked with your mentor' );
$r = rows()[0];
ck( 'a plugin email is named by its context', array( $r['template'], $r['module'], $r['status'], $r['to_email'], (int) $r['user_id'], $r['to_name'], $r['recipient_type'], $r['subject'] ), array( 'call-booked', 'calls', 'sent', 'ada@example.test', 7, 'Ada Lin', 'student', 'Call booked with your mentor' ) );
ck( 'the context is taken, so it cannot name the next email', WPCPM_Mail::$ctx, '' );

// Several recipients, Cc and Bcc in the one header string wp_mail() is often given.
wipe();
WPCPM_Mail::$ctx = 'offer-low-stock';
send( 'Ada Lin <ada@example.test>, ben@example.test, , not-an-address', 'Codes running low', "Cc: cy@example.test\r\nBcc: Dee <dee@example.test>" );
ck( 'one row per recipient of To, Cc and Bcc from a header string, none for a blank or a non-address', array_column( rows(), 'to_email' ), array( 'dee@example.test', 'cy@example.test', 'ben@example.test', 'ada@example.test' ) );
ck( 'an address without an account is Other on an email for several kinds', rows()[0]['recipient_type'], 'other' );

wipe();
WPCPM_Mail::$ctx = 'agreement-reminder';
send( array( 'cy@example.test' ), 'Agreements waiting', array( 'Cc: ben@example.test', 'Bcc: Dee <dee@example.test>, ada@example.test' ) );
ck( 'and from an array of two header lines', array_column( rows(), 'to_email' ), array( 'ada@example.test', 'dee@example.test', 'ben@example.test', 'cy@example.test' ) );
ck( 'recipients() reads a To array, once each without regard to case, and leaves Reply-To out', WPCPM_Mail_Capture::recipients( array( 'ada@example.test', 'ADA@example.test' ), array( 'Cc: ben@example.test', 'Reply-To: x@example.test' ) ), array( 'ada@example.test', 'ben@example.test' ) );

// The email as the last wp_mail filter left it: another plugin's filter changes the subject and adds a
// Bcc, which WordPress then reads out of the headers before the outcome.
wipe();
add_filter(
	'wp_mail',
	function ( $atts ) {
		if ( ! empty( $GLOBALS['rewrite'] ) ) {
			$atts['subject'] = '[Program] ' . $atts['subject'];
			$atts['headers'] = (array) $atts['headers'];
			$atts['headers'][] = 'Bcc: archive@example.test';
		}
		return $atts;
	}
);
$GLOBALS['rewrite'] = true;
WPCPM_Mail::$ctx    = 'report-drafted';
send( 'cy@example.test', 'A report is drafted' );
$GLOBALS['rewrite'] = false;
ck( 'the rows hold the subject and the recipients as the other filters left them, a Bcc WordPress has read out included', array( array_column( rows(), 'to_email' ), array_values( array_unique( array_column( rows(), 'subject' ) ) ) ), array( array( 'archive@example.test', 'cy@example.test' ), array( '[Program] A report is drafted' ) ) );

// Recipient types.
ck( 'type_of: the account decides when the email is for several kinds', array( WPCPM_Mail_Capture::type_of( $GLOBALS['users']['ben@example.test'], '' ), WPCPM_Mail_Capture::type_of( $GLOBALS['users']['cy@example.test'], '' ) ), array( 'mentor', 'administrator' ) );
ck( 'type_of: a person with two roles is shown as the one the email is for', array( WPCPM_Mail_Capture::type_of( $GLOBALS['users']['two@example.test'], 'mentor' ), WPCPM_Mail_Capture::type_of( $GLOBALS['users']['two@example.test'], '' ) ), array( 'mentor', 'student' ) );
ck( 'type_of: no account takes the audience, or Other', array( WPCPM_Mail_Capture::type_of( null, 'applicant' ), WPCPM_Mail_Capture::type_of( null, '' ) ), array( 'applicant', 'other' ) );

// WordPress's own and the Two Factor code are named by their filters.
wipe();
apply_filters( 'retrieve_password_notification_email', array( 'to' => 'ada@example.test' ) );
send( 'ada@example.test', '[Site] Password Reset' );
apply_filters( 'two_factor_token_email_subject', 'Your login confirmation code', 7 );
send( 'ada@example.test', 'Your login confirmation code' );
send( 'ada@example.test', 'Something else' );
ck( 'WordPress\'s reset, the Two Factor code, then an unnamed email', array_column( rows(), 'template' ), array( 'other', 'two-factor-code', 'wp-password-reset' ) );
ck( 'with their areas', array_column( rows(), 'module' ), array( 'other', 'two-factor', 'wordpress' ) );

// The plugin's context wins over WordPress's name: an invitation is WordPress's new-account email.
wipe();
apply_filters( 'wp_new_user_notification_email', array( 'to' => 'ben@example.test' ), null, 'Site' );
WPCPM_Mail::$ctx = 'invite-mentor';
send( 'ben@example.test', 'Your mentor account' );
ck( 'an invitation is the plugin\'s, not WordPress\'s new account', rows()[0]['template'], 'invite-mentor' );

// Outcomes.
wipe();
WPCPM_Mail::$ctx = 'report-drafted';
send( 'cy@example.test', 'A report is drafted', '', 'failed' );
ck( 'a failure is written with WordPress\'s message', array( rows()[0]['status'], rows()[0]['error'] ), array( 'failed', 'Could not instantiate mail function.' ) );

// Nested and unfinished sends.
wipe();
WPCPM_Mail::$ctx = 'call-reminder';
send( 'ada@example.test', 'Reminder', '', 'taken' );
ck( 'an email another plugin took over is Not confirmed, under its own name', array( rows()[0]['status'], rows()[0]['template'] ), array( 'unconfirmed', 'call-reminder' ) );
send( 'ada@example.test', 'Next one' );
ck( 'and the next email is not named after it', rows()[0]['template'], 'other' );

// Another plugin sends an email of its own from its wp_mail_succeeded listener.
wipe();
add_action(
	'wp_mail_succeeded',
	function () {
		static $busy = false;
		if ( empty( $GLOBALS['notify_on_send'] ) || $busy ) {
			return;
		}
		$busy = true;
		send( 'alerts@example.test', 'An email went out' );
		$busy = false;
	}
);
$GLOBALS['notify_on_send'] = true;
WPCPM_Mail::$ctx           = 'call-booked';
send( 'ada@example.test', 'Call booked with your mentor' );
$GLOBALS['notify_on_send'] = false;
ck( 'an email sent from another plugin\'s outcome listener is written, and so is the one it followed, each under its own name', array_map( function ( $r ) { return $r['to_email'] . ' ' . $r['template'] . ' ' . $r['status']; }, rows() ), array( 'alerts@example.test other sent', 'ada@example.test call-booked sent' ) );

// Another plugin sends an email from its own wp_mail filter, while the plugin's is on its way.
wipe();
add_filter(
	'wp_mail',
	function ( $atts ) {
		static $busy = false;
		if ( ! empty( $GLOBALS['copy_on_filter'] ) && ! $busy ) {
			$busy = true;
			send( 'audit@example.test', 'A copy for the audit' );
			$busy = false;
		}
		return $atts;
	}
);
$GLOBALS['copy_on_filter'] = true;
WPCPM_Mail::$ctx           = 'session-moved';
send( 'ben@example.test', 'Group session moved' );
$GLOBALS['copy_on_filter'] = false;
ck( 'an email sent from another plugin\'s wp_mail filter does not take the plugin\'s context, which stays with the plugin\'s email', array_map( function ( $r ) { return $r['to_email'] . ' ' . $r['template'] . ' ' . $r['subject']; }, rows() ), array( 'ben@example.test session-moved Group session moved', 'audit@example.test other A copy for the audit' ) );

// An email whose wp_mail filter ran and whose outcome never came.
wipe();
apply_filters( 'password_change_email', array() );
apply_filters( 'wp_mail', array( 'to' => 'ada@example.test', 'subject' => 'Half sent', 'message' => '', 'headers' => array(), 'attachments' => array(), 'embeds' => array() ) );
send( 'ben@example.test', 'A later one' );
ck( 'an email whose outcome never came lends nothing to the next: its name is not carried', array( count( rows() ), rows()[0]['template'], rows()[0]['to_email'] ), array( 1, 'other', 'ben@example.test' ) );
do_action( 'shutdown' );
ck( 'and at shutdown it is written as Not confirmed, under its own name', array_map( function ( $r ) { return $r['to_email'] . ' ' . $r['template'] . ' ' . $r['status']; }, rows() ), array( 'ada@example.test wp-password-changed unconfirmed', 'ben@example.test other sent' ) );
do_action( 'shutdown' );
ck( 'once', count( rows() ), 2 );

// A fatal error mid-send. Core's fatal error handler is a shutdown function of its own, registered
// before WordPress's `shutdown` action, and it applies `wp_php_error_args` just before it calls
// `wp_die()`: a handler that ends the request there skips every later shutdown function. So the
// capture writes what is open on that filter too, and returns the arguments as it found them.
ck( 'it also flushes on core\'s fatal error filter, first among its callbacks', priorities( 'wp_php_error_args' ), array( array( 'flush_on_fatal', PHP_INT_MIN, 1 ) ) );

wipe();
WPCPM_Mail::$ctx = 'call-booked';
apply_filters( 'wp_mail', array( 'to' => 'ada@example.test', 'subject' => 'Call booked', 'message' => '', 'headers' => array(), 'attachments' => array(), 'embeds' => array() ) );
$error_args = array( 'response' => 500, 'exit' => false );
$returned   = apply_filters( 'wp_php_error_args', $error_args, array( 'type' => E_ERROR, 'message' => 'Allowed memory size exhausted', 'file' => 'x.php', 'line' => 1 ) );
ck( 'an email open when the fatal error filter is applied is written as Not confirmed, under its own name', array_map( function ( $r ) { return $r['to_email'] . ' ' . $r['template'] . ' ' . $r['status']; }, rows() ), array( 'ada@example.test call-booked unconfirmed' ) );
ck( 'and the filter hands back the arguments it was given', $returned, $error_args );
do_action( 'shutdown' );
ck( 'a flush at shutdown after it writes nothing twice', count( rows() ), 1 );

// Two emails open (one sent from another's filter): both are written, the inner one first.
wipe();
apply_filters( 'wp_mail', array( 'to' => 'ada@example.test', 'subject' => 'Outer', 'message' => '', 'headers' => array(), 'attachments' => array(), 'embeds' => array() ) );
apply_filters( 'wp_mail', array( 'to' => 'ben@example.test', 'subject' => 'Inner', 'message' => '', 'headers' => array(), 'attachments' => array(), 'embeds' => array() ) );
apply_filters( 'wp_php_error_args', array( 'response' => 500 ), array() );
ck( 'every email still open is written, each once', array_column( rows(), 'subject' ), array( 'Outer', 'Inner' ) );

// A fatal state must not be made worse: a throwable inside the flush is swallowed.
class Boom_User extends WP_User {
	public function exists() { throw new RuntimeException( 'boom' ); }
}
wipe();
$GLOBALS['users']['boom@example.test'] = new Boom_User( 11, 'Boom', 'boom@example.test', array() );
apply_filters( 'wp_mail', array( 'to' => 'boom@example.test', 'subject' => 'Unlucky', 'message' => '', 'headers' => array(), 'attachments' => array(), 'embeds' => array() ) );
$swallowed = false;
$handed    = null;
try {
	$handed = apply_filters( 'wp_php_error_args', array( 'response' => 500 ), array() );
} catch ( Throwable $e ) {
	$swallowed = true;
}
unset( $GLOBALS['users']['boom@example.test'] );
ck( 'a throwable inside that flush is swallowed, and the arguments still come back', array( $swallowed, $handed ), array( false, array( 'response' => 500 ) ) );
wipe();

// Only addresses wp_mail() accepts are logged: as written, not as sanitize_email() would repair them.
ck( 'an address with a space in it, or two @, is not one wp_mail() accepts: no row for it', WPCPM_Mail_Capture::recipients( 'ada lin@example.test, ada@@example.test, Ada <ada lin@example.test>, ben@example.test', '' ), array( 'ben@example.test' ) );
send( 'ada lin@example.test, ada@@example.test', 'Nobody to log' );
ck( 'an email to such addresses alone writes no row', count( rows() ), 0 );
send( array( 'ada lin@example.test', 'ada@example.test' ), 'One good address', 'Cc: ben @example.test' );
ck( 'and next to a good address it writes the good one only', array_column( rows(), 'to_email' ), array( 'ada@example.test' ) );

// Tests from Settings > Mail.
wipe();
WPCPM_Mail::$ctx = 'test-mentor';
send( 'cy@example.test', 'Sample' );
ck( 'a sample invitation is marked Test', (int) rows()[0]['is_test'], 1 );

// No body is stored: every column of every row written in this run, read back from the table.
wipe();
WPCPM_Mail::$ctx = 'call-booked';
send( 'Ada Lin <ada@example.test>', 'Call booked', "Cc: ben@example.test" );
send( 'cy@example.test', 'Unnamed', '', 'failed' );
WPCPM_Mail::$ctx = 'call-reminder';
send( 'ada@example.test', 'Reminder', '', 'taken' );
$stored = $GLOBALS['wpdb']->mail_log_rows();
$held   = array();
foreach ( $stored as $row ) {
	foreach ( $row as $value ) {
		if ( false !== strpos( (string) $value, 'BODY-NEVER-STORED' ) ) {
			$held[] = $value;
		}
	}
}
ck( 'no body is stored: no column of any of the four rows holds it', array( count( $stored ), $held ), array( 4, array() ) );

// No table: sending goes on.
$GLOBALS['wpdb']->drop_mail_log();
WPCPM_Mail_Log::reset();
WPCPM_Mail::$ctx = 'call-booked';
$threw = false;
try {
	$sent = send( 'ada@example.test', 'Still sent' );
	do_action( 'shutdown' );
} catch ( Throwable $e ) {
	$threw = true;
}
ck( 'with no table, an email still goes through every hook without an error', array( $threw, $sent ), array( false, true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
