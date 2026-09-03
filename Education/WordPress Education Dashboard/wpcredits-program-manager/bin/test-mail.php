<?php
/**
 * The mail layer and the calendar invitations.
 *
 * These assertions are here because every one of them stands for something that was
 * silently wrong or missing before, and none of it is visible from reading a template:
 * a message built outside the recipient's locale is in the wrong language and looks fine
 * in code; a cancellation with a fresh calendar UID adds an event rather than removing one;
 * a folded line that splits a multi-byte character produces a file some calendars refuse.
 *
 * Run from the plugin root:  php bin/test-mail.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']    = array();
$GLOBALS['umeta']   = array();
$GLOBALS['users']   = array();
$GLOBALS['mail']    = array();
$GLOBALS['cron']    = array();
$GLOBALS['locales'] = array();
$GLOBALS['uid']     = 0;
$GLOBALS['manage']  = array();
$GLOBALS['filters'] = array();

class WP_Error {
	private $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->data = $d; }
	public function get_error_data() { return $this->data; }
	public function get_error_message() { return ''; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email;
		$this->user_login = strtolower( str_replace( ' ', '', $name ) ); $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post { public $ID = 0, $post_content = '', $post_type = '', $post_status = 'publish'; }

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url_raw( $u, $p = null ) {
	$u = trim( (string) $u );
	return preg_match( '#^https?://#i', $u ) ? $u : '';
}
function sanitize_text_field( $s ) { return trim( str_replace( array( "\r", "\n" ), '', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $n ); }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_specialchars_decode( $s, $q = null ) { return html_entity_decode( (string) $s, ENT_QUOTES ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_bloginfo( $k = 'name' ) { return 'WordPress Education Dashboard'; }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function get_temp_dir() { return sys_get_temp_dir() . '/'; }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
function wp_generate_password( $l = 12, $s = true, $e = false ) { return substr( md5( (string) mt_rand() ), 0, (int) $l ); }
function wp_delete_file( $p ) { if ( file_exists( $p ) ) { unlink( $p ); } }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function user_can( $u, $c ) { $id = is_object( $u ) ? $u->ID : (int) $u; return in_array( $id, $GLOBALS['manage'], true ); }
function current_user_can( $c ) { return user_can( $GLOBALS['uid'], $c ); }
function number_format_i18n( $n ) { return (string) $n; }
function human_time_diff( $a, $b = 0 ) { return '4 hours'; }
function wp_timezone_string() { return 'UTC'; }
function wp_date( $format, $ts = null, $zone = null ) {
	$d = new DateTime( '@' . (int) $ts );
	$d->setTimezone( $zone instanceof DateTimeZone ? $zone : new DateTimeZone( 'UTC' ) );
	return $d->format( $format );
}
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['filters'][ $h ][] = $c; }
function add_filter( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['filters'][ $h ][] = $c; }
/**
 * `apply_filters()`, running what was registered rather than handing the value straight back.
 *
 * A stub that ignored the callbacks could never show what `wpcpm_mail` is handed as the
 * recipient. Nothing loaded here registers a callback on a hook the plugin applies, so
 * the assertions that predate this see the same values they always did.
 */
function apply_filters( $h, $v ) {
	$args = array_slice( func_get_args(), 1 );
	foreach ( $GLOBALS['filters'][ $h ] ?? array() as $cb ) { $args[0] = call_user_func_array( $cb, $args ); }
	return $args[0];
}
function do_action( $h ) {
	$args = array_slice( func_get_args(), 1 );
	foreach ( $GLOBALS['filters'][ $h ] ?? array() as $cb ) { call_user_func_array( $cb, $args ); }
}
function wp_next_scheduled( $h ) { return $GLOBALS['cron'][ $h ] ?? false; }
function wp_schedule_single_event( $w, $h ) { $GLOBALS['cron'][ $h ] = $w; return true; }
function wp_schedule_event( $w, $r, $h ) { $GLOBALS['cron'][ $h ] = $w; return true; }
function wp_clear_scheduled_hook( $h ) { unset( $GLOBALS['cron'][ $h ] ); }
function wp_new_user_notification( $id, $dep = null, $notify = '' ) { $GLOBALS['invited'][] = (int) $id; }

function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function add_query_arg( $args, $url = '' ) {
	$sep = false === strpos( $url, '?' ) ? '?' : '&';

	return $url . $sep . http_build_query( (array) $args );
}
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { return true; }
function wp_die( $m = '' ) { throw new Exception( 'wp_die: ' . $m ); }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect: ' . $to ); }

/** Stands in for the admin screen the sample handler redirects back to. */
class WPCPM_Admin { public static function settings_url() { return 'https://example.test/wp-admin/admin.php?page=wpcpm-settings'; } }

/**
 * Locale switching, recorded rather than ignored.
 *
 * The mail layer's central claim is that a template is built *inside* the recipient's
 * locale. A stub that returned true and did nothing would let that regress in silence, so
 * these keep a stack and the assertions read it: a user ID when the switch was to somebody's
 * profile language, a locale code when the caller named one for an address with no account.
 */
function switch_to_user_locale( $user_id ) { $GLOBALS['locales'][] = (int) $user_id; return true; }
function switch_to_locale( $locale ) { $GLOBALS['locales'][] = (string) $locale; return true; }
function restore_previous_locale() { array_pop( $GLOBALS['locales'] ); return true; }

/**
 * `wp_mail()`, recording what it was handed and firing the outcome hook the log listens to.
 */
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	// Whether each attachment still exists *at send time* is the assertion that matters:
	// the file is written by the builder and deleted immediately afterwards, and an order
	// mistake there means every invitation goes out with nothing attached.
	$present = array();

	foreach ( (array) $attachments as $path ) {
		$present[ $path ] = file_exists( $path );
	}

	$GLOBALS['mail'][] = compact( 'to', 'subject', 'body', 'headers', 'attachments', 'present' );

	if ( ! empty( $GLOBALS['mail_fails'] ) ) {
		do_action( 'wp_mail_failed', new WP_Error( 'fail', 'nope', compact( 'to', 'subject' ) ) );

		return false;
	}

	do_action( 'wp_mail_succeeded', compact( 'to', 'subject' ) );

	return true;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ics.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-dashboard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-availability.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php';

/* ---- fixtures ----------------------------------------------------------- */
$GLOBALS['opts'][ WPCPM_Settings::OPTION ] = WPCPM_Settings::defaults();

$GLOBALS['users'][20] = new WP_User( 20, 'Kel Santiago-Pilarski', 'kel@example.test', array( WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['users'][30] = new WP_User( 30, 'Moldir Bekezhanova', 'moldir@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );

WPCPM_Mail::init();

/* ---- runner ------------------------------------------------------------- */
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

/* ---- the send wrapper --------------------------------------------------- */

echo "=== WPCPM_Mail::send ===\n";

$GLOBALS['mail'] = array();
$seen_locale = null;

$sent = WPCPM_Mail::send(
	30,
	'test-context',
	function ( $user ) use ( &$seen_locale ) {
		// Read from *inside* the builder: this is the only moment at which the claim
		// "templates are built in the recipient's locale" is either true or false.
		$seen_locale = end( $GLOBALS['locales'] );

		return array( 'subject' => 'Hello', 'body' => 'Body' );
	}
);

ck( 'a message is handed off', array( $sent ), array( true ) );
ck( 'the template is built inside the recipient locale', array( $seen_locale ), array( 30 ) );
ck( 'and the locale is restored afterwards', $GLOBALS['locales'], array() );
ck( 'it goes to the recipient', array( $GLOBALS['mail'][0]['to'] ), array( 'moldir@example.test' ) );

// A subject is a header. A newline in one is how a name from Airtable becomes extra headers.
$GLOBALS['mail'] = array();
WPCPM_Mail::send( 30, 'test', function () {
	return array( 'subject' => "Booked\r\nBcc: attacker@example.test", 'body' => 'x' );
} );
ck( 'newlines are stripped from the subject',
    array( false !== strpos( $GLOBALS['mail'][0]['subject'], "\n" ), false !== strpos( $GLOBALS['mail'][0]['subject'], 'Bcc' ) ),
    array( false, true ) );

$GLOBALS['mail'] = array();
$sent = WPCPM_Mail::send( 30, 'test', function () { return array( 'subject' => '   ', 'body' => 'x' ); } );
ck( 'a message with no subject is not sent', array( $sent, count( $GLOBALS['mail'] ) ), array( false, 0 ) );

$sent = WPCPM_Mail::send( 999, 'test', function () { return array( 'subject' => 'x', 'body' => 'y' ); } );
ck( 'a recipient who does not exist is not mailed', array( $sent ), array( false ) );

/* ---- send_to: an address with no account ------------------------------- */

echo "\n=== WPCPM_Mail::send_to ===\n";

// An applicant is an address and nothing else. Before `send_to()`, mail to one went to
// `wp_mail()` directly and so past the filter, the log and the subject sanitising - which is
// why every claim made about `send()` above is made again here, against a bare address.
WPCPM_Mail::clear_log();
$GLOBALS['mail'] = array();
$seen_locale     = null;
$seen_to         = null;
$seen_recipient  = 'never called';

$GLOBALS['filters']['wpcpm_mail'][] = function ( $mail, $context, $recipient ) use ( &$seen_recipient ) {
	$seen_recipient = $recipient;

	return $mail;
};

$sent = WPCPM_Mail::send_to(
	'applicant@example.test',
	'institution-applied',
	function ( $to ) use ( &$seen_locale, &$seen_to ) {
		$seen_locale = end( $GLOBALS['locales'] );
		$seen_to     = $to;

		return array( 'subject' => 'Application received', 'body' => 'Body' );
	},
	'es_ES'
);

ck( 'an address with no account is mailed', array( $sent, $GLOBALS['mail'][0]['to'] ), array( true, 'applicant@example.test' ) );
ck( 'the builder is handed the address, there being no user', array( $seen_to ), array( 'applicant@example.test' ) );
ck( 'the template is built inside the locale the caller named', array( $seen_locale ), array( 'es_ES' ) );
ck( 'and that locale is restored afterwards', $GLOBALS['locales'], array() );
ck( 'the send is logged under its context',
    array( WPCPM_Mail::log()[0]['context'], WPCPM_Mail::log()[0]['to'] ),
    array( 'institution-applied', 'applicant@example.test' ) );
ck( 'the filter runs, with nobody as the recipient', array( $seen_recipient ), array( null ) );

// Null is the exception for an address with no account, not a change: the same filter still
// sees the account when there is one.
$seen_recipient = 'never called';
WPCPM_Mail::send( 30, 'test', function () { return array( 'subject' => 'x', 'body' => 'y' ); } );
ck( 'while send() still hands it the user', array( $seen_recipient instanceof WP_User ), array( true ) );

unset( $GLOBALS['filters']['wpcpm_mail'] );

// No locale named means no switch: the message is built in whatever is in force.
$seen_locale = null;
$sent        = WPCPM_Mail::send_to( 'applicant@example.test', 'test', function () use ( &$seen_locale ) {
	$seen_locale = end( $GLOBALS['locales'] );

	return array( 'subject' => 'x', 'body' => 'y' );
} );
ck( 'no locale means no switching', array( $sent, $seen_locale, $GLOBALS['locales'] ), array( true, false, array() ) );

// The same header discipline as send(): this is the other way a subject reaches wp_mail().
$GLOBALS['mail'] = array();
WPCPM_Mail::send_to( 'applicant@example.test', 'test', function () {
	return array( 'subject' => "Received\r\nBcc: attacker@example.test", 'body' => 'x' );
} );
ck( 'newlines are stripped from its subject too',
    array( false !== strpos( $GLOBALS['mail'][0]['subject'], "\n" ), false !== strpos( $GLOBALS['mail'][0]['subject'], 'Bcc' ) ),
    array( false, true ) );

$GLOBALS['mail'] = array();
$sent = WPCPM_Mail::send_to( 'not-an-address', 'test', function () { return array( 'subject' => 'x', 'body' => 'y' ); } );
ck( 'something that is not an address is refused', array( $sent, count( $GLOBALS['mail'] ) ), array( false, 0 ) );

/* ---- Reply-To ----------------------------------------------------------- */

echo "\n=== Reply-To ===\n";

ck( 'points at the other party',
    WPCPM_Mail::reply_to( $GLOBALS['users'][20] ),
    array( 'Reply-To: "Kel Santiago-Pilarski" <kel@example.test>' ) );
ck( 'nobody to reply to means no header', WPCPM_Mail::reply_to( null ), array() );

$hostile = new WP_User( 40, "Ke\"l\r\nBcc: attacker@example.test", 'x@example.test' );
$header  = WPCPM_Mail::reply_to( $hostile );
ck( 'a name cannot break out of the header',
    array( false !== strpos( $header[0], "\n" ), false !== strpos( $header[0], '"Ke l Bcc: attacker@example.test"' ) ),
    array( false, true ) );

/* ---- the log ------------------------------------------------------------ */

echo "\n=== The log ===\n";

WPCPM_Mail::clear_log();
WPCPM_Mail::send( 30, 'call-booked', function () { return array( 'subject' => 'Booked', 'body' => 'x' ); } );

$log = WPCPM_Mail::log();
ck( 'a send is recorded with its outcome and context',
    array( count( $log ), $log[0]['context'], $log[0]['sent'], $log[0]['to'] ),
    array( 1, 'call-booked', true, 'moldir@example.test' ) );

$GLOBALS['mail_fails'] = true;
WPCPM_Mail::send( 30, 'call-booked', function () { return array( 'subject' => 'Booked', 'body' => 'x' ); } );
$GLOBALS['mail_fails'] = false;

$log = WPCPM_Mail::log();
ck( 'a refusal is recorded as one', array( $log[0]['sent'] ), array( false ) );
ck( 'and counted', array( WPCPM_Mail::failures() ), array( 1 ) );

// Mail belonging to WordPress or another plugin must not be swept into this log.
WPCPM_Mail::clear_log();
wp_mail( 'someone@example.test', 'Comment awaiting moderation', 'body' );
ck( 'somebody else\'s mail is not recorded', array( count( WPCPM_Mail::log() ) ), array( 0 ) );

/* ---- the invitation queue ---------------------------------------------- */

echo "\n=== The invitation queue ===\n";

WPCPM_Mail::clear_queue();
$GLOBALS['invited'] = array();

foreach ( range( 1, 25 ) as $i ) {
	$GLOBALS['users'][ 100 + $i ] = new WP_User( 100 + $i, 'Student ' . $i, "s$i@example.test", array( WPCPM_Roles::ROLE_STUDENT ) );
	WPCPM_Mail::queue_invite( 100 + $i );
}

ck( 'everybody queued is waiting', array( WPCPM_Mail::queued() ), array( 25 ) );
WPCPM_Mail::queue_invite( 101 );
ck( 'queueing the same person twice does not duplicate them', array( WPCPM_Mail::queued() ), array( 25 ) );
ck( 'a run is scheduled', array( false !== wp_next_scheduled( WPCPM_Mail::CRON_QUEUE ) ), array( true ) );

WPCPM_Mail::drain_queue();
ck( 'one run sends a batch, not the lot',
    array( count( $GLOBALS['invited'] ), WPCPM_Mail::queued() ),
    array( WPCPM_Mail::QUEUE_BATCH, 25 - WPCPM_Mail::QUEUE_BATCH ) );

WPCPM_Mail::drain_queue();
WPCPM_Mail::drain_queue();
ck( 'and it drains to empty', array( count( $GLOBALS['invited'] ), WPCPM_Mail::queued() ), array( 25, 0 ) );
ck( 'everybody drained is stamped as invited',
    array( (int) get_user_meta( 105, 'wpcpm_student_invited', true ) > 0 ), array( true ) );

/* ---- bulk invitations --------------------------------------------------- */

echo "\n=== Bulk invitations ===\n";

WPCPM_Mail::clear_queue();
WPCPM_Mail::dismiss_run();
$GLOBALS['invited'] = array();

$bulk = range( 200, 240 );

foreach ( $bulk as $id ) {
	$GLOBALS['users'][ $id ] = new WP_User( $id, 'Student ' . $id, "b$id@example.test", array( WPCPM_Roles::ROLE_STUDENT ) );
}

$added = WPCPM_Mail::queue_invites( $bulk );

ck( 'everybody named is queued', array( $added, WPCPM_Mail::queued() ), array( 41, 41 ) );

// The run is what makes progress reportable: the queue alone only knows who is left, so a screen
// reading it could say "31 waiting" but never "10 of 41 sent".
$run = WPCPM_Mail::run();

ck( 'and the run records what it started with', $run['total'], 41 );
ck( 'and is not finished yet', $run['finished'], 0 );

WPCPM_Mail::drain_queue();

$run = WPCPM_Mail::run();

ck( 'progress is the total less what is left',
    $run['total'] - WPCPM_Mail::queued(), WPCPM_Mail::QUEUE_BATCH );

ck( 'and the run stays open while there is more to send', WPCPM_Mail::run()['finished'], 0 );

// **Pressing the button twice must not send twice.** The second press adds only people the first
// did not already queue.
$again = WPCPM_Mail::queue_invites( $bulk );

ck( 'queueing the same people again adds nobody', $again, 0 );

foreach ( range( 1, 5 ) as $ignored ) {
	WPCPM_Mail::drain_queue();
}

ck( 'it drains to empty', array( count( $GLOBALS['invited'] ), WPCPM_Mail::queued() ), array( 41, 0 ) );

// Kept rather than deleted, so the screen can say what happened instead of the card vanishing.
ck( 'and the run is then marked finished', WPCPM_Mail::run()['finished'] > 0, true );
ck( 'while still remembering the total', WPCPM_Mail::run()['total'], 41 );

WPCPM_Mail::dismiss_run();
ck( 'dismissing forgets it', WPCPM_Mail::run(), array() );

// The abort. Batches already sent cannot be recalled; the rest can, and that is the only remedy
// that exists after the mistake rather than before it.
//
// A fresh range, because everybody in `$bulk` now carries an invited stamp and `queue_invites()`
// refuses those — which is the previous assertion, from the other side.
WPCPM_Mail::clear_queue();
$GLOBALS['invited'] = array();
$abort = range( 300, 340 );

foreach ( $abort as $id ) {
	$GLOBALS['users'][ $id ] = new WP_User( $id, 'Student ' . $id, "a$id@example.test", array( WPCPM_Roles::ROLE_STUDENT ) );
}

WPCPM_Mail::queue_invites( $abort );
WPCPM_Mail::drain_queue();
$sent_before_stop = count( $GLOBALS['invited'] );
WPCPM_Mail::clear_queue();
WPCPM_Mail::drain_queue();

ck( 'stopping sends nothing further',
    array( $sent_before_stop, count( $GLOBALS['invited'] ) ),
    array( WPCPM_Mail::QUEUE_BATCH, WPCPM_Mail::QUEUE_BATCH ) );

// An empty list must not open a run, or the screen reports on a send that never happened.
WPCPM_Mail::clear_queue();
WPCPM_Mail::dismiss_run();

ck( 'queueing nobody starts no run', array( WPCPM_Mail::queue_invites( array() ), WPCPM_Mail::run() ), array( 0, array() ) );

// Zeroes and repeats are what a hand-built list of IDs arrives with.
WPCPM_Mail::clear_queue();
WPCPM_Mail::dismiss_run();

foreach ( array( 401, 402 ) as $id ) {
	$GLOBALS['users'][ $id ] = new WP_User( $id, 'Student ' . $id, "d$id@example.test", array( WPCPM_Roles::ROLE_STUDENT ) );
}

ck( 'duplicates and empty IDs are dropped',
    array( WPCPM_Mail::queue_invites( array( 401, 401, 0, '', 402 ) ), WPCPM_Mail::queued() ),
    array( 2, 2 ) );

// The other half of the guard: somebody already sent to is not queued again, whichever stamp
// they carry.
WPCPM_Mail::clear_queue();
WPCPM_Mail::dismiss_run();
$GLOBALS['users'][ 500 ] = new WP_User( 500, 'Sent already', 'sent@example.test', array( WPCPM_Roles::ROLE_MENTOR ) );
update_user_meta( 500, 'wpcpm_mentor_invited', time() );

ck( 'somebody already invited is never queued again',
    array( WPCPM_Mail::queue_invites( array( 500 ) ), WPCPM_Mail::queued() ), array( 0, 0 ) );

// The third kind of account carries the third stamp, and the guard has to read that one too.
$GLOBALS['users'][ 510 ] = new WP_User( 510, 'Invited institution', 'inst@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
update_user_meta( 510, 'wpcpm_inst_invited', time() );

ck( 'nor is an institution already invited',
    array( WPCPM_Mail::queue_invites( array( 510 ) ), WPCPM_Mail::queued() ), array( 0, 0 ) );

// Which stamp goes on is what the guards above read back, so each kind of account has to get
// its own: an institution stamped as a student would pass the guard and still show as never
// invited on its own screen, which lists by role and stamp together.
WPCPM_Mail::clear_queue();
WPCPM_Mail::dismiss_run();
$GLOBALS['invited'] = array();

$GLOBALS['users'][601] = new WP_User( 601, 'A mentor', 'm601@example.test', array( WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['users'][602] = new WP_User( 602, 'A student', 's602@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][603] = new WP_User( 603, 'An institution', 'i603@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );

WPCPM_Mail::queue_invites( array( 601, 602, 603 ) );
WPCPM_Mail::drain_queue();

ck( 'each kind of account is stamped with its own invited meta',
    array(
        (int) get_user_meta( 601, 'wpcpm_mentor_invited', true ) > 0,
        (int) get_user_meta( 602, 'wpcpm_student_invited', true ) > 0,
        (int) get_user_meta( 603, 'wpcpm_inst_invited', true ) > 0,
        get_user_meta( 603, 'wpcpm_student_invited', true ),
        get_user_meta( 603, 'wpcpm_mentor_invited', true ),
    ),
    array( true, true, true, '', '' ) );
ck( 'and each of the three was actually notified', $GLOBALS['invited'], array( 601, 602, 603 ) );

WPCPM_Mail::clear_queue();
WPCPM_Mail::dismiss_run();

/* ---- inviting one institution ------------------------------------------- */

echo "\n=== Inviting one institution ===\n";

// The Students screen can be narrowed to an institution so an invitation reaches one cohort
// rather than every student on the site. The narrowing has to hold at the *send*, not only in the
// list: the button posts to `admin-post.php`, which never sees the screen's query string, so a
// handler reading the filter from the URL would quietly send to everybody.

// Written as the sync writes it, so `get_program()` is the real one reading its real meta key.
foreach ( array(
	701 => 'Pundra University of Science & Technology',
	702 => 'Pundra University of Science & Technology',
	703 => 'IES Azarquiel',
	704 => '',
) as $id => $institution ) {
	update_user_meta( $id, WPCPM_Students_Sync::META_PROGRAM, array( 'institution' => $institution ) );
}

// 705 gets no row at all — the other way a student can be incomplete.

$everyone = array( 701, 702, 703, 704, 705 );

ck( 'one institution is the students in it',
    WPCPM_Mail::only_institution( $everyone, 'Pundra University of Science & Technology' ),
    array( 701, 702 ) );

ck( 'and a different one is a different set',
    WPCPM_Mail::only_institution( $everyone, 'IES Azarquiel' ), array( 703 ) );

// The unfiltered screen still invites everybody, so the empty string cannot mean "nobody".
ck( 'no filter means no narrowing', WPCPM_Mail::only_institution( $everyone, '' ), $everyone );
ck( 'and whitespace is no filter either', WPCPM_Mail::only_institution( $everyone, '   ' ), $everyone );

// A name nobody carries reaches nobody. It arrives from a posted field, so it is matched against
// what the site holds rather than trusted — the failure has to be an empty send, not a wide one.
ck( 'an institution nobody is at reaches nobody',
    WPCPM_Mail::only_institution( $everyone, 'Somewhere Else' ), array() );

// A student with no institution, or no program row at all, is not swept into somebody else's
// cohort — the two ways a row can be incomplete.
ck( 'a student with no institution is in no institution',
    WPCPM_Mail::only_institution( array( 704, 705 ), 'IES Azarquiel' ), array() );

/* ---- the welcome email -------------------------------------------------- */

echo "\n=== The invitation template ===\n";

$core = array(
	'to'      => 'moldir@example.test',
	'subject' => '[%s] Login Details',
	'message' => "Username: moldir\r\n\r\nTo set your password, visit the following address:\r\n\r\nhttps://example.test/reset\r\n",
	'headers' => '',
);

$student = WPCPM_Mail::welcome_email( $core, $GLOBALS['users'][30], 'WordPress Education Dashboard' );
$mentor  = WPCPM_Mail::welcome_email( $core, $GLOBALS['users'][20], 'WordPress Education Dashboard' );

ck( 'the student subject names the program, not "Login Details"',
    array( $student['subject'] ), array( '[WordPress Education Dashboard] Welcome to the WordPress Credits Program' ) );
ck( 'the mentor gets a different subject',
    array( $mentor['subject'] ), array( '[WordPress Education Dashboard] Your mentor account is ready' ) );
ck( 'both keep the reset link WordPress generated',
    array(
        false !== strpos( $student['message'], 'https://example.test/reset' ),
        false !== strpos( $mentor['message'], 'https://example.test/reset' ),
    ),
    array( true, true ) );
ck( 'both keep the username',
    array( false !== strpos( $student['message'], 'Username: moldir' ) ), array( true ) );
ck( 'both say what to do when the link has expired',
    array(
        false !== strpos( $student['message'], 'Lost your password?' ),
        false !== strpos( $mentor['message'], 'Lost your password?' ),
    ),
    array( true, true ) );
ck( 'the two audiences are told different things',
    array( $student['message'] === $mentor['message'] ), array( false ) );

$stranger = new WP_User( 50, 'Someone Else', 'else@example.test', array( 'subscriber' ) );
$left     = WPCPM_Mail::welcome_email( $core, $stranger, 'Site' );
ck( 'an account that is not ours is left alone', array( $left === $core ), array( true ) );

// The third audience. An account holding only the institution role was "not one of ours" and
// went out as WordPress's bare "Login Details", which is the bug these pin.
$GLOBALS['users'][70] = new WP_User( 70, 'Pundra Contact', 'contact@pundra.example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );

$institution = WPCPM_Mail::welcome_email( $core, $GLOBALS['users'][70], 'WordPress Education Dashboard' );

ck( 'an institution account is one of ours', array( $institution === $core ), array( false ) );
ck( 'and its subject says whose account it is',
    array( $institution['subject'] ), array( '[WordPress Education Dashboard] Your institution account is ready' ) );
ck( 'it keeps the username and the reset link WordPress generated',
    array( false !== strpos( $institution['message'], 'Username: moldir' ), false !== strpos( $institution['message'], 'https://example.test/reset' ) ),
    array( true, true ) );
ck( 'a new institution is told the agreement is the first step',
    array(
        false !== strpos( $institution['message'], 'Collaboration Agreement' ),
        false !== strpos( $institution['message'], 'generate' ),
        false !== strpos( $institution['message'], 'on file' ),
    ),
    array( true, true, false ) );
ck( 'and the institution wording is its own',
    array( $institution['message'] === $mentor['message'], $institution['message'] === $student['message'] ), array( false, false ) );

// The context is observed the way production observes it: WordPress calls `wp_mail()` itself
// straight after the filter, and the outcome hook reads what the filter left behind.
WPCPM_Mail::clear_log();
WPCPM_Mail::welcome_email( $core, $GLOBALS['users'][70], 'Site' );
wp_mail( 'contact@pundra.example.test', $institution['subject'], $institution['message'] );
ck( 'the invitation is logged as an institution\'s', array( WPCPM_Mail::log()[0]['context'] ), array( 'invite-institution' ) );

// The agreement module answers `is_settled()` once it exists; the next phase ships it and the
// members module beside it. A legacy institution whose agreement is already on file must not
// be told to produce one. Stand-ins, declared here rather than with the other stubs so that the
// invitation above was first built without them - the way it is in the plugin today.
if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	/** Stands in for the members module: which institution an account belongs to. */
	class WPCPM_Institution_Members { public static function institution_of( $user ) { return $GLOBALS['institution_of'][ $user->ID ] ?? ''; } }
	/** Stands in for the agreement module: whether that institution's agreement is on file. */
	class WPCPM_Institution_Agreement { public static function is_settled( $record ) { return in_array( $record, $GLOBALS['settled'] ?? array(), true ); } }
}
$GLOBALS['institution_of'] = array( 70 => 'recLEGACY' );
$GLOBALS['settled']        = array( 'recLEGACY' );
$on_file                   = WPCPM_Mail::welcome_email( $core, $GLOBALS['users'][70], 'WordPress Education Dashboard' );

ck( 'an institution whose agreement is on file is told its account is open',
    array(
        false !== strpos( $on_file['message'], 'on file' ),
        false !== stripos( $on_file['message'], 'generat' ),
        false !== strpos( $on_file['message'], 'upload' ),
        $on_file['subject'],
    ),
    array( true, false, false, '[WordPress Education Dashboard] Your institution account is ready' ) );

// The dashboard line. There is no institution dashboard until Phase 1, and until there is one
// there must be no line pointing at it. The stand-in below is declared here rather than with
// the other stubs so the invitation is first built with no such page at all - the way it is in
// the plugin today - and only then with one.
ck( 'no dashboard line while the module has no page',
    array( false !== strpos( $institution['message'], 'Your institution dashboard:' ) ), array( false ) );

if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
	/** Stands in for the module once it has a page; empty means the page is not set up yet. */
	class WPCPM_Institutions_Dashboard { public static function page_url() { return $GLOBALS['institution_page'] ?? ''; } }
}

$GLOBALS['institution_page'] = '';
$no_page                     = WPCPM_Mail::welcome_email( $core, $GLOBALS['users'][70], 'Site' );
$GLOBALS['institution_page'] = 'https://example.test/institution-dashboard/';
$with_page                   = WPCPM_Mail::welcome_email( $core, $GLOBALS['users'][70], 'Site' );

ck( 'nor while the page answers with nothing',
    array( false !== strpos( $no_page['message'], 'Your institution dashboard:' ) ), array( false ) );
ck( 'and the line appears once there is a page to point at',
    array( false !== strpos( $with_page['message'], "Your institution dashboard:\r\nhttps://example.test/institution-dashboard/" ) ), array( true ) );

// Same page, different job: before the agreement is accepted the dashboard is where the
// signed copy goes, and the line says so.
$GLOBALS['settled'] = array();
$to_upload          = WPCPM_Mail::welcome_email( $core, $GLOBALS['users'][70], 'Site' );
$GLOBALS['settled'] = array( 'recLEGACY' );

ck( 'an institution still to sign is pointed at the same page as the place to upload',
    array(
        false !== strpos( $to_upload['message'], "Where the agreement is uploaded:\r\nhttps://example.test/institution-dashboard/" ),
        false !== strpos( $to_upload['message'], 'Your institution dashboard:' ),
        false !== strpos( $to_upload['message'], 'once it is in place' ),
        false !== strpos( $with_page['message'], 'once it is in place' ),
    ),
    array( true, false, true, false ) );

/* ---- calendar invitations ---------------------------------------------- */

echo "\n=== Calendar invitations ===\n";

$facts = array(
	'id'         => 77,
	'start'      => 1786000000,
	'end'        => 1786001800,
	'mentor_id'  => 20,
	'student_id' => 30,
	'record'     => 'recSTUDENT1234567',
	'name'       => 'Moldir Bekezhanova',
	'zone'       => 'Asia/Tokyo',
	'topic'      => "Reviewing my first PR;\nwith a newline, and a comma",
	'booked'     => 1785000000,
);

$request = WPCPM_ICS::build( $facts, WPCPM_ICS::METHOD_REQUEST, $GLOBALS['users'][20], $GLOBALS['users'][30], 'Mentor call', "Line one\nLine two", 'https://meet.example.test/kel' );
$cancel  = WPCPM_ICS::build( $facts, WPCPM_ICS::METHOD_CANCEL, $GLOBALS['users'][20], $GLOBALS['users'][30], 'Mentor call', 'Gone', '' );

ck( 'a booking is a REQUEST and a cancellation a CANCEL',
    array(
        false !== strpos( $request, 'METHOD:REQUEST' ),
        false !== strpos( $cancel, 'METHOD:CANCEL' ),
        false !== strpos( $cancel, 'STATUS:CANCELLED' ),
    ),
    array( true, true, true ) );

// The single most important property in the file: a cancellation that does not carry the
// booking's own UID adds a second event to the calendar instead of withdrawing the first.
ck( 'both name the same event',
    array( WPCPM_ICS::uid( 77 ), false !== strpos( $request, 'UID:' . WPCPM_ICS::uid( 77 ) ), false !== strpos( $cancel, 'UID:' . WPCPM_ICS::uid( 77 ) ) ),
    array( 'wpcpm-call-77@example.test', true, true ) );
ck( 'and the cancellation outranks the booking',
    array( false !== strpos( $request, 'SEQUENCE:0' ), false !== strpos( $cancel, 'SEQUENCE:1' ) ),
    array( true, true ) );

// A session that is moved is sent again with the same UID, so the revision has to climb or a
// calendar holding the original is entitled to treat the move as a stale duplicate and keep the
// old time. This is what makes editing a group session reach the students rather than only the
// mentor's screen.
$moved  = WPCPM_ICS::build( $facts, WPCPM_ICS::METHOD_REQUEST, $GLOBALS['users'][20], $GLOBALS['users'][30], 'Mentor call', 'Moved', '', 2 );
$again  = WPCPM_ICS::build( $facts, WPCPM_ICS::METHOD_REQUEST, $GLOBALS['users'][20], $GLOBALS['users'][30], 'Mentor call', 'Moved twice', '', 3 );
$scrubbed = WPCPM_ICS::build( $facts, WPCPM_ICS::METHOD_REQUEST, $GLOBALS['users'][20], $GLOBALS['users'][30], 'Mentor call', 'Nonsense', '', -5 );

ck( 'a moved session carries the revision it was given',
    array( false !== strpos( $moved, 'SEQUENCE:2' ), false !== strpos( $again, 'SEQUENCE:3' ) ),
    array( true, true ) );
ck( 'and keeps the UID it was booked under, so it moves rather than duplicating',
    substr_count( $moved, WPCPM_ICS::uid( $facts['id'] ) ) > 0, true );
ck( 'a nonsense revision cannot go below zero', false !== strpos( $scrubbed, 'SEQUENCE:0' ), true );
ck( 'and a booking with no revision still reads as the first one',
    false !== strpos( $request, 'SEQUENCE:0' ), true );

ck( 'times are UTC, not a floating local time',
    array( false !== strpos( $request, 'DTSTART:' . gmdate( 'Ymd\THis\Z', 1786000000 ) ), false !== strpos( $request, 'TZID' ) ),
    array( true, false ) );

ck( 'the meeting link is the location',
    array( false !== strpos( $request, 'LOCATION:https://meet.example.test/kel' ) ), array( true ) );

ck( 'semicolons, commas and newlines in a description are escaped',
    array(
        false !== strpos( $request, 'DESCRIPTION:Line one\nLine two' ),
        // A raw newline would end the property and produce an unparseable file.
        (bool) preg_match( '/DESCRIPTION:[^\r\n]*\r\n(?! )/', $request ),
    ),
    array( true, true ) );

// Every line has to be CRLF and at most 75 octets, counted in bytes.
$too_long = array();
foreach ( explode( "\r\n", trim( $request ) ) as $line ) {
	if ( strlen( $line ) > 75 ) { $too_long[] = $line; }
}
ck( 'every line is folded to 75 octets', array( count( $too_long ) ), array( 0 ) );
ck( 'lines are CRLF-delimited', array( false !== strpos( $request, "\r\n" ), false !== strpos( str_replace( "\r\n", '', $request ), "\n" ) ), array( true, false ) );

// Folding on a byte boundary in the middle of a multi-byte character makes a file some
// calendars refuse outright, and a mentor with an accent in their name is enough to hit it.
$accented = new WP_User( 60, str_repeat( 'Zoë Ćwiąkalski ', 8 ), 'zoe@example.test' );
$folded   = WPCPM_ICS::build( $facts, WPCPM_ICS::METHOD_REQUEST, $accented, $GLOBALS['users'][30], str_repeat( 'Zoë ', 30 ), 'x', '' );
$unfolded = str_replace( "\r\n ", '', $folded );
ck( 'folding never splits a multi-byte character',
    array( $unfolded === mb_convert_encoding( $unfolded, 'UTF-8', 'UTF-8' ) ), array( true ) );

/* ---- the attachment lifecycle ------------------------------------------ */

echo "\n=== The attachment ===\n";

$path = WPCPM_ICS::tempfile( $request );
ck( 'the calendar is written where wp_mail can attach it',
    array( '' !== $path, file_exists( $path ), basename( $path ) ),
    array( true, true, 'mentor-call.ics' ) );

WPCPM_ICS::cleanup( $path );
ck( 'and cleaned up afterwards', array( file_exists( $path ), is_dir( dirname( $path ) ) ), array( false, false ) );

// The ordering assertion: the file must still be on disk when wp_mail sees it. Cleanup
// running first would send every invitation with nothing attached, and nothing in the
// plugin's own output would look wrong.
$GLOBALS['mail'] = array();
WPCPM_Mail::send( 30, 'call-booked', function () use ( $request ) {
	$file = WPCPM_ICS::tempfile( $request );

	return array( 'subject' => 'Booked', 'body' => 'x', 'attachments' => array( $file ), 'cleanup' => array( $file ) );
} );

$attached = $GLOBALS['mail'][0];
ck( 'the attachment exists at the moment it is sent',
    array( count( $attached['attachments'] ), reset( $attached['present'] ) ),
    array( 1, true ) );
ck( 'and is gone once the send is over',
    array( file_exists( reset( $attached['attachments'] ) ) ), array( false ) );

/* ---- format_range ------------------------------------------------------ */

echo "\n=== format_range ===\n";

$GLOBALS['opts']['date_format'] = 'F j, Y';
$GLOBALS['opts']['time_format'] = 'g:i a';

$tokyo = new DateTimeZone( 'Asia/Tokyo' );

// A 30-minute call inside one day needs no date on the end.
$same = WPCPM_Mentor_Calls::format_range( 1786000000, 1786001800, $tokyo );
ck( 'a call inside one day states the end as a time only',
    array( 1 === substr_count( $same, ',' ) ), array( true ) );

// The same call read on a clock where it crosses midnight has to say so, or "11:45 pm –
// 12:15 am" reads as ending fourteen hours before it starts.
$start = strtotime( '2026-08-03 23:45:00 UTC' );
$cross = WPCPM_Mentor_Calls::format_range( $start, $start + 1800, new DateTimeZone( 'UTC' ) );
ck( 'a call crossing midnight dates its end',
    array( false !== strpos( $cross, 'August 4, 2026' ) ), array( true ) );

/* ---- the sample invitation ---------------------------------------------- */

echo "\n=== The sample invitation ===\n";

// Two bugs lived here and nothing was watching. The sample stood the plain login URL in for
// *both* of core's addresses, so it printed the same link twice; and the audience was read
// from `$_GET` while the buttons post it, so both of them sent the student template. Both
// were found by somebody pressing the button, which is the wrong way to find out.
//
// **Driven through `$_POST`**, because that is where the real form puts the field. The first
// version of this test set `$_GET` by hand and passed against the broken code — a harness
// that arranges the world to suit the implementation tests nothing.
$GLOBALS['uid']    = 20;
$GLOBALS['manage'] = array( 20 );

/**
 * Press one of the two sample buttons and return the message it produced.
 *
 * @param string $kind `student` or `mentor`.
 * @return array The mail, or an empty array if none was sent.
 */
function press_sample_button( $kind ) {
	$GLOBALS['mail'] = array();

	$_GET  = array();
	$_POST = array( 'kind' => $kind );

	try {
		WPCPM_Mail::handle_test();
	} catch ( Exception $e ) {
		// The handler ends in a redirect, which the stub raises.
		$GLOBALS['last_redirect'] = $e->getMessage();
	}

	return $GLOBALS['mail'] ? $GLOBALS['mail'][0] : array();
}

$student_sample = press_sample_button( 'student' );
$mentor_sample  = press_sample_button( 'mentor' );

ck( 'the sample handler finishes and redirects',
    array( isset( $GLOBALS['last_redirect'] ), ! empty( $student_sample ) ), array( true, true ) );

// The audience actually asked for. Both buttons sending the same template is the bug this
// pins, and it is invisible from the plugin's own screens.
ck( 'the student button sends the student invitation',
    array( $student_sample['subject'] ),
    array( '[WordPress Education Dashboard] Welcome to the WordPress Credits Program' ) );
ck( 'the mentor button sends the mentor invitation',
    array( $mentor_sample['subject'] ),
    array( '[WordPress Education Dashboard] Your mentor account is ready' ) );
ck( 'the two samples say different things',
    array( $student_sample['body'] === $mentor_sample['body'] ), array( false ) );
ck( 'and each is logged under its own audience',
    array( WPCPM_Mail::log()[0]['context'] ), array( 'test-mentor' ) );

$institution_sample = press_sample_button( 'institution' );

ck( 'the institution button sends the institution invitation',
    array( $institution_sample['subject'], WPCPM_Mail::log()[0]['context'] ),
    array( '[WordPress Education Dashboard] Your institution account is ready', 'test-institution' ) );

// A kind nobody offers a button for falls back to the student template rather than to nothing.
ck( 'an unknown kind previews the student invitation',
    array( press_sample_button( 'sponsor' )['subject'] ),
    array( '[WordPress Education Dashboard] Welcome to the WordPress Credits Program' ) );

$sample = $student_sample['body'];

preg_match_all( '#https?://\S+#', $sample, $urls );
$found  = $urls[0];
$unique = array_values( array_unique( $found ) );

ck( 'no address appears twice', array( count( $found ) === count( $unique ) ), array( true ) );
ck( 'the reset stand-in is marked as an example, not a live link',
    array(
        false !== strpos( $sample, 'EXAMPLE-KEY-NOT-A-REAL-LINK' ),
        false !== strpos( $sample, 'action=rp' ),
    ),
    array( true, true ) );
ck( 'and the login page is there once, on its own',
    array( count( preg_grep( '#/wp-login\.php$#', $found ) ) ), array( 1 ) );
ck( 'the sample says which address does what',
    array( false !== strpos( $sample, 'Of the two addresses above' ) ), array( true ) );

/* ---- the meeting link -------------------------------------------------- */

echo "\n=== The meeting link ===\n";

ck( 'an https room is kept', array( WPCPM_Mentor_Availability::meeting_url( 'https://meet.example.test/kel' ) ), array( 'https://meet.example.test/kel' ) );
ck( 'a javascript URL is not',  array( WPCPM_Mentor_Availability::meeting_url( 'javascript:alert(1)' ) ), array( '' ) );
ck( 'nor a data URL',           array( WPCPM_Mentor_Availability::meeting_url( 'data:text/html,<script>' ) ), array( '' ) );
ck( 'blank stays blank',        array( WPCPM_Mentor_Availability::meeting_url( '   ' ) ), array( '' ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
