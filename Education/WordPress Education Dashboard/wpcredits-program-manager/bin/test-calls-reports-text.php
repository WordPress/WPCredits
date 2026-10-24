<?php
/**
 * The Mentor calls and Semester reports emails, word for word: what each says, as 1.122.20 wrote it.
 *
 * These emails move into the template registry in 1.122.21, and the move must change nothing a
 * recipient reads. So before the move this suite recorded, through the real send paths, every email
 * the two areas send for a set of example cases into bin/fixtures/calls-reports-1-122-20.json: who
 * it went to, the id it was logged under, its subject and body as `wp_mail()` was handed them (the
 * signature and footer included, as 1.122.20 adds it), its headers, and each calendar file it
 * carried. From then on it runs the same cases and compares, byte for byte.
 *
 * The send paths: `WPCPM_Mentor_Calls`'s seven senders, booked, joined, joined a series, moved,
 * canceled, left and reminded (the private ones reached by reflection, as they are called from the
 * handlers and the hourly sweep); and the semester report's three builders, handed to the mail layer
 * as the report screen hands them (`notify_managers()` sends to an account, or to an address with
 * none, and the approval and the consent request to accounts).
 *
 * The inputs: a call and a group session, a series of one, two and three sessions, a meeting link
 * set and not, a topic or none (one of spaces alone, which a booking prints and a series or a move
 * leaves out, and one holding "<", "&", quotes, a placeholder's name in braces and a line break), a
 * mentor with no meeting link and a typed topic (both of a booking's, a join's and a series' blocks
 * together), a series with a link and no topic, the dashboard pages published and not (all together,
 * and the Institution Dashboard or the Administrator Dashboard alone, for the three report emails),
 * a mentor in Tokyo and students in New York, Warsaw and the site's own zone (each recipient reads
 * every time on their own clock), a call that crosses midnight on one of those clocks, a canceled
 * call and session whose start falls on a different day on the mentor's clock than on the students'
 * (the subject's date is each recipient's own), a booking with no student name stored, each person who
 * can cancel or move, counts of one and of several, and a site name holding "&", a quote and a "%". The names are
 * invented; every address is example.test's and is written "at example.test" in the recording, which
 * holds no address; a calendar file's DTSTAMP, the moment it was written, is written "recorded".
 * Every call is in 2036, so a reminder reads "in" a while, as it does before a call. The times rest
 * on the time zone rules PHP carried when it was made (timezonedb 2026.1); if a later PHP's rules for
 * 2036 differ, the suite fails on every New York and Warsaw time, and `timezone_version_get()` says
 * why.
 *
 * The recording is made once: `--record` refuses when the file is there, and the suite pins the
 * file's sha256, so a recording made again from later code can never stand in for 1.122.20's. (It was
 * made a second time, and only then, before any sender had moved, to add the cases above that the
 * first held no email for - a mentor with no link and a topic, a date that differs across clocks, and
 * each report dashboard alone; the first recording's 61 emails are byte for byte inside the second.)
 *
 * 1.122.23 prints every name as a person reads it, and keeps every character of a name in a
 * Reply-To. The recording holds no name as WordPress stores it, so the cases that need one are those
 * of `added_cases()`, recorded once from 1.122.22, before anything of 1.122.23 moved, into
 * bin/fixtures/calls-reports-added-1-122-22.json by `--record-added`, which refuses when that file
 * is there, and the suite pins its sha256 too: a mentor's and a student's account name with "&" and
 * "<" written as entities, as WordPress stores a name, a mentor whose name holds a run the subject's
 * cleaner reads as a tag, a mentor whose name holds a comma and a double quote, and an institution's
 * name as the form's cleaner keeps a "<" (a case's `institution`). Every email of both recordings is
 * sent as recorded, byte for byte, but those `changes()` lists, each change with the item of the
 * 1.122.23 plan it comes from and the exact text it changes
 * (docs/plans/2026-10-11-mail-fixes-1-122-23.md); the calendar files are as recorded in every case.
 * What 1.123.24 changes after them, the site's address in the footer, is
 * bin/stubs/mail-since-1-123-24.php's, run over every recorded body where it is compared; what
 * 1.123.27 changes, the site's name in brackets opening the three Semester reports emails' subjects,
 * is bin/stubs/mail-since-1-123-27.php's, run after it, the same way.
 *
 * Run from the plugin root:  php bin/test-calls-reports-text.php
 * Record (once, against 1.122.20, before the move):  php bin/test-calls-reports-text.php --record
 * Record the added cases (once, against 1.122.22):  php bin/test-calls-reports-text.php --record-added
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']      = array();
$GLOBALS['umeta']     = array();
$GLOBALS['users']     = array();
$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['mail']      = array();
$GLOBALS['uid']       = 0;
$GLOBALS['manage']    = array( 40 );
$GLOBALS['site_name'] = 'WordPress Credits';

class WP_Error {
	private $code;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return ''; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID           = $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( array( ' ', "'" ), '', $name ) );
		$this->roles        = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post { public $ID = 0, $post_content = '', $post_type = '', $post_status = 'private'; }

function is_wp_error( $t ) { return $t instanceof WP_Error; }
// The language each string is asked in: English as written, any other marked with its locale, so a
// check can tell which language an email was built in. The recording is English throughout.
function __( $s, $d = null ) { return wpcpm_test_in_language( $s ); }
function _n( $a, $b, $n, $d = null ) { return wpcpm_test_in_language( 1 === (int) $n ? $a : $b ); }
function wpcpm_test_in_language( $s ) { return 'en_US' === determine_locale() ? $s : '[' . determine_locale() . '] ' . $s; }
function esc_url_raw( $u, $p = null ) { $u = trim( (string) $u ); return preg_match( '#^https?://#i', $u ) ? $u : ''; }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $n ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_bloginfo( $k = 'name' ) { return $GLOBALS['site_name']; }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function get_temp_dir() { return wpcpm_test_temp_dir(); }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
function wp_generate_password( $l = 12, $s = true, $e = false ) { return substr( md5( (string) mt_rand() ), 0, (int) $l ); }
function wp_delete_file( $p ) { if ( file_exists( $p ) ) { unlink( $p ); } }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function get_user_by( $f, $v ) { return 'id' === $f ? ( $GLOBALS['users'][ (int) $v ] ?? false ) : false; }
function get_post( $id = null ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
// A single read answers the first row, a rows read answers them all, as WordPress does.
function get_post_meta( $id, $k = '', $single = false ) { $rows = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? array(); $rows = is_array( $rows ) ? $rows : array( $rows ); if ( $single ) { return $rows ? $rows[0] : ''; } return $rows; }
// Every call was booked in 2033, long before its reminder window opens.
function get_post_time( $f, $gmt = false, $post = null ) { return 2000000000; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
// A fixed span, so a reminder reads the same whenever the suite runs.
function human_time_diff( $a, $b = 0 ) { return '1 day'; }
function wp_timezone_string() { return 'UTC'; }
function wp_timezone() { return new DateTimeZone( 'UTC' ); }
function wp_date( $format, $ts = null, $zone = null ) {
	$d = new DateTime( '@' . (int) $ts );
	$d->setTimezone( $zone instanceof DateTimeZone ? $zone : new DateTimeZone( 'UTC' ) );
	return $d->format( $format );
}
function add_action() {}
function add_filter() {}
function apply_filters( $h, $v ) { return $v; }
function do_action( $h ) {}
// A person's language is the one a check gives their account, English when it gives none; switching
// into it and back is a stack, as WordPress's own switcher keeps one.
$GLOBALS['user_locale']  = array();
$GLOBALS['locale_stack'] = array();
function switch_to_user_locale( $user_id ) {
	if ( ! isset( $GLOBALS['user_locale'][ (int) $user_id ] ) ) {
		return false;
	}

	$GLOBALS['locale_stack'][] = $GLOBALS['user_locale'][ (int) $user_id ];

	return true;
}
function switch_to_locale( $locale ) { return false; }
function restore_previous_locale() { array_pop( $GLOBALS['locale_stack'] ); return true; }
function determine_locale() { return array() === $GLOBALS['locale_stack'] ? 'en_US' : end( $GLOBALS['locale_stack'] ); }
/**
 * Core's `add_query_arg()` in its two forms, a fragment kept at the end, as core keeps it.
 *
 * @param mixed ...$args The arguments, in either form.
 * @return string
 */
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$pairs = $args[0];
		$url   = (string) ( $args[1] ?? '' );
	} else {
		$pairs = array( $args[0] => $args[1] );
		$url   = (string) ( $args[2] ?? '' );
	}

	$fragment = '';

	if ( false !== strpos( $url, '#' ) ) {
		$fragment = substr( $url, strpos( $url, '#' ) );
		$url      = substr( $url, 0, strpos( $url, '#' ) );
	}

	foreach ( $pairs as $k => $v ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
	}

	return $url . $fragment;
}
/**
 * `wp_mail()`, keeping what it was handed and the id the mail log's capture takes, and each
 * attachment's bytes as they are at the moment of sending, since the sender removes the file
 * right after.
 */
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$files = array();

	foreach ( (array) $attachments as $path ) {
		$files[] = array(
			'name' => basename( $path ),
			'ics'  => is_file( $path ) ? (string) file_get_contents( $path ) : '',
		);
	}

	$GLOBALS['mail'][] = array(
		'to'          => $to,
		'context'     => WPCPM_Mail::take_context(),
		'subject'     => $subject,
		'body'        => $body,
		'headers'     => array_values( (array) $headers ),
		'attachments' => $files,
	);

	WPCPM_Mail::take_test();

	return true;
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';
require_once __DIR__ . '/stubs/mail-since-1-123-24.php';
require_once __DIR__ . '/stubs/mail-since-1-123-27.php';

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ics.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-availability.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report-screen.php';

/** The dashboards, each at the address a case gives it, '' while its page is not published. */
class WPCPM_Students_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** As above. */
class WPCPM_Mentors_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** As above. */
class WPCPM_Institutions_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** As above. */
class WPCPM_Administrators_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** The institutions the index holds by record, as a case gives them. */
class WPCPM_Institutions_Index {
	public static $rows = array();
	public static function row( $record ) { return self::$rows[ $record ] ?? null; }
}
/** The address argument the Institution Dashboard's switcher reads. */
class WPCPM_Institution_Roster {
	const ARG_VIEW = 'wpcpm_institution_view';
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

/*
 * The people and the clocks
 * --------------------------------------------------------------------
 */

$GLOBALS['opts']['date_format'] = 'F j, Y';
$GLOBALS['opts']['time_format'] = 'g:i a';

$GLOBALS['users'][20] = new WP_User( 20, 'Ben Lindqvist', 'ben@example.test', array( WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['users'][30] = new WP_User( 30, 'Ada Okonjo', 'ada@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][31] = new WP_User( 31, 'Cy Moreau', 'cy@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][32] = new WP_User( 32, "Kai O'Example", 'kai@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][40] = new WP_User( 40, 'Ida Admin', 'ida@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][50] = new WP_User( 50, 'Ines Example', 'ines@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][51] = new WP_User( 51, 'Noor Example', 'noor@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );

// The mentor reads Tokyo's clock, two students their own, and Kai none, so the site's.
$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META_TIMEZONE ] = 'Asia/Tokyo';
$GLOBALS['umeta'][30][ WPCPM_Mentor_Availability::META_TIMEZONE ] = 'America/New_York';
$GLOBALS['umeta'][31][ WPCPM_Mentor_Availability::META_TIMEZONE ] = 'Europe/Warsaw';

/**
 * A topic as a student or a mentor types one: markup-like, an ampersand, quotes, a word in braces that
 * is a placeholder's name, and a line break.
 */
const TYPED_TOPIC = "Blocks <3 & \"the\" 'loop' {when}\nand my first plugin";

/**
 * Every case: what it sends, through which sender, and the world it sends in.
 *
 * Each case names the sender (`send`), the calls it makes (`calls`: post ID to its start, end,
 * capacity, attendees, the student name stored on it and its topic), who is signed in (`actor`),
 * whether the mentor has set a meeting link (`link`), which dashboard pages are published (`pages`:
 * true for all four, false for none, or a list of `students`, `mentors`, `institutions` and
 * `administrators`), the site's name as WordPress stores it (`site`), and what the sender is handed
 * (`args`).
 *
 * @return array<string,array>
 */
function cases() {
	$one   = array( 2099745900, 2099747700 );
	$two   = array( 2100350700, 2100352500 );
	$three = array( 2100934800, 2100936600 );
	$early = array( 2099725200, 2099727000 );
	// 16:30 UTC: still July 15 in New York, Warsaw and on the site's clock, already July 16 in Tokyo.
	$late = array( 2099752200, 2099754000 );

	$call = static function ( $when, $attendees, $name, $topic, $capacity = 1 ) {
		return array(
			'start'     => $when[0],
			'end'       => $when[1],
			'capacity'  => $capacity,
			'attendees' => $attendees,
			'name'      => $name,
			'topic'     => $topic,
		);
	};

	$base = array(
		'actor' => 0,
		'link'  => true,
		'pages' => true,
		'site'  => 'WordPress Credits',
		'args'  => array(),
	);

	$cases = array(
		// Booked: a one-to-one call, and a join of a session, which tells both people the same way.
		'booked-call'                       => array( 'send' => 'booked', 'calls' => array( 601 => $call( $one, array( 30 ), 'Ada Okonjo', TYPED_TOPIC ) ) ),
		'booked-call-bare'                  => array( 'send' => 'booked', 'link' => false, 'pages' => false, 'calls' => array( 601 => $call( $early, array( 32 ), '', '' ) ) ),
		'booked-call-site-name'             => array( 'send' => 'booked', 'site' => 'Ada &amp; Ben&#039;s 100% Credits', 'calls' => array( 601 => $call( $one, array( 31 ), 'Cy Moreau', '' ) ) ),
		'booked-call-topic-of-spaces'       => array( 'send' => 'booked', 'calls' => array( 601 => $call( $one, array( 30 ), 'Ada Okonjo', '   ' ) ) ),
		// No meeting link and a typed topic: the mentor's "no link yet" paragraph and the topic block, together.
		'booked-call-no-link-with-topic'    => array( 'send' => 'booked', 'link' => false, 'calls' => array( 601 => $call( $one, array( 31 ), 'Cy Moreau', TYPED_TOPIC ) ) ),
		'joined-session'                    => array( 'send' => 'joined', 'args' => array( 31 ), 'calls' => array( 611 => $call( $one, array( 30, 31 ), '', 'Release cycle', 6 ) ) ),
		'joined-session-bare'               => array( 'send' => 'joined', 'args' => array( 32 ), 'link' => false, 'pages' => false, 'calls' => array( 611 => $call( $early, array( 32 ), '', '', 6 ) ) ),
		'joined-session-no-link-with-topic' => array( 'send' => 'joined', 'args' => array( 32 ), 'link' => false, 'calls' => array( 611 => $call( $one, array( 30, 32 ), '', TYPED_TOPIC, 6 ) ) ),
		// A series: one email each, the dates listed, the count in the subject and the first line.
		'series-two'                        => array( 'send' => 'series', 'args' => array( 30 ), 'calls' => array( 621 => $call( $one, array( 30 ), '', TYPED_TOPIC, 6 ), 622 => $call( $two, array( 30 ), '', TYPED_TOPIC, 6 ) ) ),
		'series-one-bare'                   => array( 'send' => 'series', 'args' => array( 32 ), 'link' => false, 'pages' => false, 'site' => 'Ada &amp; Ben&#039;s 100% Credits', 'calls' => array( 621 => $call( $early, array( 32 ), '', '', 6 ) ) ),
		'series-three-topic-of-spaces'      => array( 'send' => 'series', 'args' => array( 31 ), 'link' => false, 'calls' => array( 621 => $call( $one, array( 31 ), '', '   ', 6 ), 622 => $call( $two, array( 31 ), '', '   ', 6 ), 623 => $call( $three, array( 31 ), '', '   ', 6 ) ) ),
		'series-two-no-link-with-topic'     => array( 'send' => 'series', 'args' => array( 32 ), 'link' => false, 'calls' => array( 621 => $call( $one, array( 32 ), '', TYPED_TOPIC, 6 ), 622 => $call( $two, array( 32 ), '', TYPED_TOPIC, 6 ) ) ),
		'series-two-with-link-no-topic'     => array( 'send' => 'series', 'args' => array( 31 ), 'calls' => array( 621 => $call( $one, array( 31 ), '', '', 6 ), 622 => $call( $two, array( 31 ), '', '', 6 ) ) ),
		// Moved: the students, and the mentor when somebody else moved it.
		'moved-by-an-administrator'         => array( 'send' => 'moved', 'actor' => 40, 'args' => array( $early[0], 3 ), 'calls' => array( 631 => $call( $one, array( 30, 31 ), '', TYPED_TOPIC, 6 ) ) ),
		'moved-by-the-mentor-bare'          => array( 'send' => 'moved', 'actor' => 20, 'pages' => false, 'args' => array( $early[0], 1 ), 'calls' => array( 631 => $call( $one, array( 32 ), '', '', 6 ) ) ),
		'moved-topic-of-spaces'             => array( 'send' => 'moved', 'actor' => 40, 'args' => array( $two[0], 2 ), 'calls' => array( 631 => $call( $one, array( 30 ), '', '   ', 6 ) ) ),
		// Canceled: everybody but the person who pressed Cancel, each named or "an Administrator".
		'cancelled-call-by-the-student'     => array( 'send' => 'cancelled', 'actor' => 30, 'calls' => array( 641 => $call( $one, array( 30 ), 'Ada Okonjo', TYPED_TOPIC ) ) ),
		'cancelled-call-by-the-mentor'      => array( 'send' => 'cancelled', 'actor' => 20, 'site' => 'Ada &amp; Ben&#039;s 100% Credits', 'calls' => array( 641 => $call( $one, array( 31 ), 'Cy Moreau', '' ) ) ),
		'cancelled-call-by-an-administrator' => array( 'send' => 'cancelled', 'actor' => 40, 'pages' => false, 'calls' => array( 641 => $call( $early, array( 32 ), '', '' ) ) ),
		'cancelled-session-by-the-mentor'   => array( 'send' => 'cancelled', 'actor' => 20, 'args' => array( 4 ), 'calls' => array( 651 => $call( $one, array( 30, 31 ), '', 'Release cycle', 6 ) ) ),
		'cancelled-session-by-an-administrator' => array( 'send' => 'cancelled', 'actor' => 40, 'pages' => false, 'args' => array( 2 ), 'calls' => array( 651 => $call( $early, array( 30, 32 ), '', '', 6 ) ) ),
		// A start on a different day for the mentor in Tokyo than for everybody else: each subject names its own recipient's day.
		'cancelled-call-on-another-day-for-the-mentor' => array( 'send' => 'cancelled', 'actor' => 40, 'calls' => array( 641 => $call( $late, array( 30 ), 'Ada Okonjo', TYPED_TOPIC ) ) ),
		'cancelled-session-on-another-day-for-the-mentor' => array( 'send' => 'cancelled', 'actor' => 40, 'args' => array( 3 ), 'calls' => array( 651 => $call( $late, array( 30, 31, 32 ), '', 'Release cycle', 6 ) ) ),
		// Left: the student who left, and the one the students sync took off for a new mentor.
		'left-session'                      => array( 'send' => 'left', 'args' => array( 30, 5, false ), 'calls' => array( 661 => $call( $one, array( 31 ), '', TYPED_TOPIC, 6 ) ) ),
		'released-from-session-bare'        => array( 'send' => 'left', 'args' => array( 32, 2, true ), 'pages' => false, 'calls' => array( 661 => $call( $early, array(), '', '', 6 ) ) ),
		// Reminded: both people on a call, and everybody on a session, the mentor told how many.
		'reminder-call'                     => array( 'send' => 'reminder', 'calls' => array( 671 => $call( $one, array( 30 ), 'Ada Okonjo', TYPED_TOPIC ) ) ),
		'reminder-call-bare'                => array( 'send' => 'reminder', 'link' => false, 'pages' => false, 'calls' => array( 671 => $call( $early, array( 32 ), '', '' ) ) ),
		'reminder-session-of-one'           => array( 'send' => 'reminder', 'link' => false, 'calls' => array( 681 => $call( $one, array( 31 ), '', '', 6 ) ) ),
		'reminder-session-of-three'         => array( 'send' => 'reminder', 'site' => 'Ada &amp; Ben&#039;s 100% Credits', 'calls' => array( 681 => $call( $two, array( 30, 31, 32 ), '', 'Release cycle', 6 ) ) ),
		// The semester report: drafted (to an account and to an address with none), approved,
		// and the consent request.
		'report-drafted-one-in-progress'    => array( 'send' => 'drafted', 'args' => array( 'recSEED000000016', '2026-H1', 1 ), 'calls' => array() ),
		'report-drafted-three-not-indexed'  => array( 'send' => 'drafted', 'args' => array( 'recSEED000000017', '2026-H2', 3 ), 'calls' => array() ),
		'report-drafted-bare'               => array( 'send' => 'drafted', 'pages' => false, 'args' => array( 'recSEED000000016', '2026-H1', 0 ), 'calls' => array() ),
		'report-drafted-in-progress-bare'   => array( 'send' => 'drafted', 'pages' => false, 'args' => array( 'recSEED000000016', '2026-H2', 2 ), 'calls' => array() ),
		'report-approved'                   => array( 'send' => 'approved', 'args' => array( '2026-H1' ), 'calls' => array() ),
		'report-approved-bare'              => array( 'send' => 'approved', 'pages' => false, 'args' => array( '2026-H2' ), 'calls' => array() ),
		'report-consent'                    => array( 'send' => 'consent', 'args' => array( '2026-H1' ), 'calls' => array() ),
		'report-consent-bare'               => array( 'send' => 'consent', 'pages' => false, 'args' => array( '2026-H2' ), 'calls' => array() ),
		// Each of the two pages the report emails read, published alone.
		'report-drafted-institution-page-only'    => array( 'send' => 'drafted', 'pages' => array( 'institutions' ), 'args' => array( 'recSEED000000016', '2026-H1', 2 ), 'calls' => array() ),
		'report-drafted-administrator-page-only'  => array( 'send' => 'drafted', 'pages' => array( 'administrators' ), 'args' => array( 'recSEED000000016', '2026-H1', 2 ), 'calls' => array() ),
		'report-approved-institution-page-only'   => array( 'send' => 'approved', 'pages' => array( 'institutions' ), 'args' => array( '2026-H1' ), 'calls' => array() ),
		'report-approved-administrator-page-only' => array( 'send' => 'approved', 'pages' => array( 'administrators' ), 'args' => array( '2026-H1' ), 'calls' => array() ),
		'report-consent-institution-page-only'    => array( 'send' => 'consent', 'pages' => array( 'institutions' ), 'args' => array( '2026-H1' ), 'calls' => array() ),
		'report-consent-administrator-page-only'  => array( 'send' => 'consent', 'pages' => array( 'administrators' ), 'args' => array( '2026-H1' ), 'calls' => array() ),
	);

	foreach ( $cases as $name => $case ) {
		$cases[ $name ] = array_merge( $base, $case );
	}

	return $cases;
}

/**
 * The cases 1.122.23 adds: names as WordPress stores an account's, "&" written `&amp;` and a "<"
 * `&lt;`, the mentor's, the student's and the student name a booking stored, a mentor whose name
 * holds a run the subject's cleaner reads as a tag, an institution's name as the form's cleaner keeps
 * a "<", and a mentor whose name holds a comma and a double quote, which a Reply-To has to keep
 * (items 3 and 4 of the 1.122.23 plan), recorded from 1.122.22 (`--record-added`). A case's `names`
 * are the accounts' display names, and its `institution` the institution's name, for that case
 * alone.
 *
 * @return array<string,array>
 */
function added_cases() {
	$one     = array( 2099745900, 2099747700 );
	$stored  = array(
		20 => 'Ben &amp; Bo &lt;Mentor',
		30 => 'Ada &amp; Al Okonjo',
	);
	$base    = array(
		'actor' => 0,
		'link'  => true,
		'pages' => true,
		'site'  => 'WordPress Credits',
		'args'  => array(),
	);
	$call    = static function ( $attendees, $name ) use ( $one ) {
		return array(
			'start'     => $one[0],
			'end'       => $one[1],
			'capacity'  => 1,
			'attendees' => $attendees,
			'name'      => $name,
			'topic'     => 'Release cycle',
		);
	};

	$cases = array(
		'booked-call-names-as-stored'                 => array( 'send' => 'booked', 'names' => $stored, 'calls' => array( 601 => $call( array( 30 ), 'Ada &amp; Al Okonjo' ) ) ),
		'cancelled-call-by-the-mentor-name-as-stored' => array( 'send' => 'cancelled', 'actor' => 20, 'names' => $stored, 'calls' => array( 641 => $call( array( 30 ), 'Ada &amp; Al Okonjo' ) ) ),
		'reminder-call-mentor-name-with-a-comma'      => array( 'send' => 'reminder', 'names' => array( 20 => 'Ben "Benny" Lindqvist, PhD' ), 'calls' => array( 671 => $call( array( 30 ), 'Ada Okonjo' ) ) ),
		'booked-call-mentor-name-tag-like'            => array( 'send' => 'booked', 'names' => array( 20 => 'Ben &lt;Ops&gt; Lindqvist' ), 'calls' => array( 601 => $call( array( 30 ), 'Ada Okonjo' ) ) ),
		'report-drafted-institution-name-as-cleaned'  => array( 'send' => 'drafted', 'institution' => 'Example College &lt;3 &quot;Arts&quot; &amp; Co', 'args' => array( 'recSEED000000016', '2026-H1', 1 ), 'calls' => array() ),
	);

	foreach ( $cases as $name => $case ) {
		$cases[ $name ] = array_merge( $base, $case );
	}

	return $cases;
}

/**
 * A private method of a class, as its own class calls it.
 *
 * @param string $class  The class.
 * @param string $method The method.
 * @return ReflectionMethod
 */
function reach( $class, $method ) {
	$reached = new ReflectionMethod( $class, $method );

	if ( PHP_VERSION_ID < 80100 ) {
		$reached->setAccessible( true );
	}

	return $reached;
}

/**
 * One case's emails, through the real senders and the real mail layer.
 *
 * @param array $case One of `cases()`.
 * @return array[] Each email as `wp_mail()` was handed it, in the order it was sent.
 */
function emails_of( array $case ) {
	$GLOBALS['mail']      = array();
	$GLOBALS['posts']     = array();
	$GLOBALS['pmeta']     = array();
	$GLOBALS['uid']       = (int) $case['actor'];
	$GLOBALS['site_name'] = $case['site'];

	// The accounts' names: as every case of the recording has them, or as a case added in 1.122.23
	// gives them.
	foreach ( array( 20 => 'Ben Lindqvist', 30 => 'Ada Okonjo', 31 => 'Cy Moreau', 32 => "Kai O'Example" ) as $id => $named ) {
		$GLOBALS['users'][ $id ]->display_name = $case['names'][ $id ] ?? $named;
	}

	// All four dashboard pages, none, or the ones a case names.
	$published = static function ( $page ) use ( $case ) {
		return true === $case['pages'] || ( is_array( $case['pages'] ) && in_array( $page, $case['pages'], true ) );
	};

	WPCPM_Students_Dashboard::$url       = $published( 'students' ) ? 'https://example.test/student-report-card/' : '';
	WPCPM_Mentors_Dashboard::$url        = $published( 'mentors' ) ? 'https://example.test/mentor-report-card/' : '';
	WPCPM_Institutions_Dashboard::$url   = $published( 'institutions' ) ? 'https://example.test/institution-dashboard/' : '';
	WPCPM_Administrators_Dashboard::$url = $published( 'administrators' ) ? 'https://example.test/administrator-dashboard/' : '';
	WPCPM_Institutions_Index::$rows      = array( 'recSEED000000016' => array( 'name' => $case['institution'] ?? 'Example University & "Partners"' ) );

	$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ] = array( 'meeting_url' => $case['link'] ? 'https://meet.example.test/ben-lindqvist?pwd=EXAMPLE-NOT-A-REAL-CODE' : '' );

	foreach ( $case['calls'] as $id => $call ) {
		$post               = new WP_Post();
		$post->ID           = $id;
		$post->post_type    = WPCPM_Mentor_Calls::POST_TYPE;
		$post->post_content = $call['topic'];

		$GLOBALS['posts'][ $id ] = $post;
		$GLOBALS['pmeta'][ $id ] = array(
			WPCPM_Mentor_Calls::META_START    => $call['start'],
			WPCPM_Mentor_Calls::META_END      => $call['end'],
			WPCPM_Mentor_Calls::META_MENTOR   => 20,
			WPCPM_Mentor_Calls::META_STUDENT  => $call['attendees'],
			WPCPM_Mentor_Calls::META_NAME     => $call['name'],
			WPCPM_Mentor_Calls::META_CAPACITY => $call['capacity'],
		);
	}

	$mentor = $GLOBALS['users'][20];
	$ids    = array_keys( $case['calls'] );
	$first  = $ids ? $GLOBALS['posts'][ $ids[0] ] : null;
	$args   = $case['args'];

	switch ( $case['send'] ) {
		case 'booked':
			reach( 'WPCPM_Mentor_Calls', 'notify_booked' )->invoke( null, $first->ID, $mentor, $GLOBALS['users'][ $case['calls'][ $first->ID ]['attendees'][0] ] );
			break;
		case 'joined':
			WPCPM_Mentor_Calls::notify_joined( $first->ID, $mentor, $GLOBALS['users'][ $args[0] ], 2 );
			break;
		case 'series':
			WPCPM_Mentor_Calls::notify_joined_series( $ids, $mentor, $GLOBALS['users'][ $args[0] ], array_fill_keys( $ids, 1 ) );
			break;
		case 'moved':
			WPCPM_Mentor_Calls::notify_session_moved( $first->ID, $args[0], $args[1] );
			break;
		case 'cancelled':
			reach( 'WPCPM_Mentor_Calls', 'notify_cancelled' )->invoke( null, $first, WPCPM_Mentor_Calls::details( $first ), $args ? $args[0] : null );
			break;
		case 'left':
			WPCPM_Mentor_Calls::notify_left( $first->ID, $args[0], $args[1], $args[2] );
			break;
		case 'reminder':
			reach( 'WPCPM_Mentor_Calls', 'notify_reminder' )->invoke( null, $first, WPCPM_Mentor_Calls::REMINDER_LEAD );
			break;
		case 'drafted':
			$build = reach( 'WPCPM_Semester_Report_Screen', 'drafted_message' )->invoke( null, $args[0], $args[1], $args[2] );
			WPCPM_Mail::send( $GLOBALS['users'][40], WPCPM_Semester_Report_Screen::MAIL_DRAFTED, $build );
			WPCPM_Mail::send_to( 'reports@example.test', WPCPM_Semester_Report_Screen::MAIL_DRAFTED, $build );
			break;
		case 'approved':
			foreach ( array( 50, 51 ) as $member ) {
				WPCPM_Mail::send( $GLOBALS['users'][ $member ], WPCPM_Semester_Report_Screen::MAIL_APPROVED, reach( 'WPCPM_Semester_Report_Screen', 'approved_message' )->invoke( null, $args[0] ) );
			}
			break;
		case 'consent':
			foreach ( array( 30, 32 ) as $student ) {
				WPCPM_Mail::send( $student, WPCPM_Semester_Report_Screen::MAIL_CONTEXT, reach( 'WPCPM_Semester_Report_Screen', 'ask_message' )->invoke( null, $args[0] ) );
			}
			break;
	}

	return array_map( 'recorded_form', $GLOBALS['mail'] );
}

/**
 * An email as the recording holds it: every address written "at example.test", and each calendar
 * file unfolded, with the moment it was written as "recorded".
 *
 * @param array $mail What `wp_mail()` was handed.
 * @return array
 */
function recorded_form( array $mail ) {
	$plain = static function ( $text ) {
		return preg_replace( '/([A-Za-z0-9._%+-]+)@example\.test/', '$1 at example.test', (string) $text );
	};

	foreach ( $mail['attachments'] as $n => $file ) {
		$mail['attachments'][ $n ]['ics'] = $plain( preg_replace( '/^DTSTAMP:\d{8}T\d{6}Z\r$/m', "DTSTAMP:recorded\r", str_replace( "\r\n ", '', $file['ics'] ) ) );
	}

	$mail['to']      = $plain( $mail['to'] );
	$mail['headers'] = array_map( $plain, $mail['headers'] );

	return $mail;
}

/**
 * The id each recorded email is logged under now: the Mentor calls' template of its own since
 * 1.122.21, each email that says different words one, where the recording holds the one id its
 * context named for all of them; the Semester reports' as recorded.
 *
 * @param string $case     The case.
 * @param int    $index    Which of its emails, from 0.
 * @param string $recorded The id the recording holds.
 * @return string
 */
function sent_as( $case, $index, $recorded ) {
	$ids = array(
		'booked-call'                                     => array( 'call-booked-mentor', 'call-booked-student' ),
		'booked-call-bare'                                => array( 'call-booked-mentor-no-link', 'call-booked-student' ),
		'booked-call-site-name'                           => array( 'call-booked-mentor', 'call-booked-student' ),
		'booked-call-topic-of-spaces'                     => array( 'call-booked-mentor', 'call-booked-student' ),
		'booked-call-no-link-with-topic'                  => array( 'call-booked-mentor-no-link', 'call-booked-student' ),
		'joined-session'                                  => array( 'session-joined-mentor', 'session-joined-student' ),
		'joined-session-bare'                             => array( 'session-joined-mentor-no-link', 'session-joined-student' ),
		'joined-session-no-link-with-topic'               => array( 'session-joined-mentor-no-link', 'session-joined-student' ),
		'series-two'                                      => array( 'series-joined-mentor', 'series-joined-student' ),
		'series-one-bare'                                 => array( 'series-joined-mentor-no-link', 'series-joined-student' ),
		'series-three-topic-of-spaces'                    => array( 'series-joined-mentor-no-link', 'series-joined-student' ),
		'series-two-no-link-with-topic'                   => array( 'series-joined-mentor-no-link', 'series-joined-student' ),
		'series-two-with-link-no-topic'                   => array( 'series-joined-mentor', 'series-joined-student' ),
		'moved-by-an-administrator'                       => array( 'session-moved-student', 'session-moved-student', 'session-moved-mentor' ),
		'moved-by-the-mentor-bare'                        => array( 'session-moved-student' ),
		'moved-topic-of-spaces'                           => array( 'session-moved-student', 'session-moved-mentor' ),
		'cancelled-call-by-the-student'                   => array( 'call-cancelled-mentor' ),
		'cancelled-call-by-the-mentor'                    => array( 'call-cancelled-student' ),
		'cancelled-call-by-an-administrator'              => array( 'call-cancelled-mentor', 'call-cancelled-student' ),
		'cancelled-session-by-the-mentor'                 => array( 'session-cancelled', 'session-cancelled' ),
		'cancelled-session-by-an-administrator'           => array( 'session-cancelled', 'session-cancelled', 'session-cancelled' ),
		'cancelled-call-on-another-day-for-the-mentor'    => array( 'call-cancelled-mentor', 'call-cancelled-student' ),
		'cancelled-session-on-another-day-for-the-mentor' => array( 'session-cancelled', 'session-cancelled', 'session-cancelled', 'session-cancelled' ),
		'left-session'                                    => array( 'session-left' ),
		'released-from-session-bare'                      => array( 'session-released' ),
		'reminder-call'                                   => array( 'call-reminder-mentor', 'call-reminder-student' ),
		'reminder-call-bare'                              => array( 'call-reminder-mentor', 'call-reminder-student' ),
		'reminder-session-of-one'                         => array( 'session-reminder', 'session-reminder' ),
		'reminder-session-of-three'                       => array( 'session-reminder', 'session-reminder', 'session-reminder', 'session-reminder' ),
	);

	return isset( $ids[ $case ][ $index ] ) ? $ids[ $case ][ $index ] : $recorded;
}

/**
 * The body each recorded email is sent with now: as recorded, byte for byte, but for one line in the
 * five cases with the Institution Dashboard page not published (none of the four pages, or the
 * Administrator Dashboard alone). There 1.122.20's report drafted and report approved bodies ended in
 * a blank line, a link's line left out above it, so the footer stood under two blank lines; the
 * registry leaves out a blank line a line left out would leave at the end of a body, as it always has,
 * so one stands there now. The consent request has no such line, and is as recorded in every case.
 *
 * @param string $case     The case.
 * @param string $recorded The body the recording holds.
 * @return string
 */
function body_as( $case, $recorded ) {
	$no_institution_page = array(
		'report-drafted-bare',
		'report-drafted-in-progress-bare',
		'report-drafted-administrator-page-only',
		'report-approved-bare',
		'report-approved-administrator-page-only',
	);

	if ( in_array( $case, $no_institution_page, true ) ) {
		return str_replace( "\n\n\n-- \n", "\n\n-- \n", $recorded );
	}

	return $recorded;
}

/**
 * What 1.122.23 changes on purpose in the emails of the two recordings, each change with the item of
 * the 1.122.23 plan it comes from: a row names a case, its item, and the text that changes in each
 * email it sends, or in those `only` names by their place in the recording (`replace`: a field,
 * `subject`, `body` or `headers`, to pairs of the text the recording holds and the text that stands
 * in its place now). Every other email of both recordings is sent as recorded, byte for byte, its
 * calendar files included.
 *
 * @return array[]
 */
function changes() {
	$rows = array(
		// Item 3: a name as a person reads it, an account's or an institution's, in the body and in the
		// subject, which is plain text now, so a "<" and a run the subject's cleaner read as a tag are kept.
		array(
			'case'    => 'booked-call-names-as-stored',
			'item'    => 3,
			'only'    => array( 0 ),
			'replace' => array(
				'subject' => array( 'Call booked with Ada &amp; Al Okonjo' => 'Call booked with Ada & Al Okonjo' ),
				'body'    => array( 'Ada &amp; Al Okonjo booked' => 'Ada & Al Okonjo booked' ),
			),
		),
		array(
			'case'    => 'booked-call-names-as-stored',
			'item'    => 3,
			'only'    => array( 1 ),
			'replace' => array(
				'subject' => array( 'Ben &amp; Bo &lt;Mentor' => 'Ben & Bo <Mentor' ),
				'body'    => array( 'Ben &amp; Bo &lt;Mentor' => 'Ben & Bo <Mentor' ),
			),
		),
		array(
			'case'    => 'cancelled-call-by-the-mentor-name-as-stored',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Ben &amp; Bo &lt;Mentor' => 'Ben & Bo <Mentor' ) ),
		),
		array(
			'case'    => 'booked-call-mentor-name-tag-like',
			'item'    => 3,
			'only'    => array( 1 ),
			'replace' => array(
				'subject' => array( 'Ben &lt;Ops&gt; Lindqvist' => 'Ben <Ops> Lindqvist' ),
				'body'    => array( 'Ben &lt;Ops&gt; Lindqvist' => 'Ben <Ops> Lindqvist' ),
			),
		),
		array(
			'case'    => 'report-drafted-institution-name-as-cleaned',
			'item'    => 3,
			'replace' => array(
				'subject' => array( 'Example College &lt;3 &quot;Arts&quot; &amp; Co' => 'Example College <3 "Arts" & Co' ),
				'body'    => array( 'Example College &lt;3 &quot;Arts&quot; &amp; Co' => 'Example College <3 "Arts" & Co' ),
			),
		),
		// Item 4: a Reply-To keeps every character of a name: read back, as it is, in no quotes, for the
		// mailer to quote, escape and encode (the fix wave), or in encoded words for a name holding a
		// comma, which `wp_mail()` would split the header at.
		array(
			'case'    => 'booked-call-names-as-stored',
			'item'    => 4,
			'only'    => array( 0 ),
			'replace' => array( 'headers' => array( '"Ada &amp Al Okonjo"' => 'Ada & Al Okonjo' ) ),
		),
		array(
			'case'    => 'booked-call-names-as-stored',
			'item'    => 4,
			'only'    => array( 1 ),
			'replace' => array( 'headers' => array( '"Ben &amp Bo &lt Mentor"' => 'Ben & Bo <Mentor' ) ),
		),
		array(
			'case'    => 'cancelled-call-by-the-mentor-name-as-stored',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Ben &amp Bo &lt Mentor"' => 'Ben & Bo <Mentor' ) ),
		),
		array(
			'case'    => 'reminder-call-mentor-name-with-a-comma',
			'item'    => 4,
			'only'    => array( 1 ),
			'replace' => array( 'headers' => array( '"Ben Benny Lindqvist PhD"' => '=?UTF-8?B?QmVuICJCZW5ueSIgTGluZHF2aXN0LCBQaEQ=?=' ) ),
		),
		array(
			'case'    => 'booked-call-mentor-name-tag-like',
			'item'    => 4,
			'only'    => array( 1 ),
			'replace' => array( 'headers' => array( '"Ben &lt Ops&gt Lindqvist"' => 'Ben <Ops> Lindqvist' ) ),
		),
		// End of the changes.
	);

	// Item 4, as the fix wave made it: `wp_mail()` hands the mailer what stands before the address as
	// the name, quote marks included, and the mailer quotes a name itself, so a name the plugin wrote
	// in quotes reached the recipient as `"Ben Lindqvist"`. A name goes as it is now, and each
	// Reply-To the recordings hold as `Reply-To: "Name"` is `Reply-To: Name`: in every email of a case
	// given one name, and in the emails a case names by their place in the recording.
	$bare = array(
		'booked-call'                                     => array( 0 => 'Ada Okonjo', 1 => 'Ben Lindqvist' ),
		'booked-call-bare'                                => array( 0 => 'Kai O\'Example', 1 => 'Ben Lindqvist' ),
		'booked-call-site-name'                           => array( 0 => 'Cy Moreau', 1 => 'Ben Lindqvist' ),
		'booked-call-topic-of-spaces'                     => array( 0 => 'Ada Okonjo', 1 => 'Ben Lindqvist' ),
		'booked-call-no-link-with-topic'                  => array( 0 => 'Cy Moreau', 1 => 'Ben Lindqvist' ),
		'joined-session'                                  => array( 0 => 'Cy Moreau', 1 => 'Ben Lindqvist' ),
		'joined-session-bare'                             => array( 0 => 'Kai O\'Example', 1 => 'Ben Lindqvist' ),
		'joined-session-no-link-with-topic'               => array( 0 => 'Kai O\'Example', 1 => 'Ben Lindqvist' ),
		'series-two'                                      => array( 0 => 'Ada Okonjo', 1 => 'Ben Lindqvist' ),
		'series-one-bare'                                 => array( 0 => 'Kai O\'Example', 1 => 'Ben Lindqvist' ),
		'series-three-topic-of-spaces'                    => array( 0 => 'Cy Moreau', 1 => 'Ben Lindqvist' ),
		'series-two-no-link-with-topic'                   => array( 0 => 'Kai O\'Example', 1 => 'Ben Lindqvist' ),
		'series-two-with-link-no-topic'                   => array( 0 => 'Cy Moreau', 1 => 'Ben Lindqvist' ),
		'moved-by-an-administrator'                       => array( 0 => 'Ben Lindqvist', 1 => 'Ben Lindqvist' ),
		'moved-by-the-mentor-bare'                        => 'Ben Lindqvist',
		'moved-topic-of-spaces'                           => array( 0 => 'Ben Lindqvist' ),
		'cancelled-call-by-the-student'                   => 'Ada Okonjo',
		'cancelled-call-by-the-mentor'                    => 'Ben Lindqvist',
		'cancelled-call-by-an-administrator'              => array( 0 => 'Kai O\'Example', 1 => 'Ben Lindqvist' ),
		'cancelled-session-by-the-mentor'                 => 'Ben Lindqvist',
		'cancelled-session-by-an-administrator'           => array( 1 => 'Ben Lindqvist', 2 => 'Ben Lindqvist' ),
		'cancelled-call-on-another-day-for-the-mentor'    => array( 0 => 'Ada Okonjo', 1 => 'Ben Lindqvist' ),
		'cancelled-session-on-another-day-for-the-mentor' => array( 1 => 'Ben Lindqvist', 2 => 'Ben Lindqvist', 3 => 'Ben Lindqvist' ),
		'left-session'                                    => 'Ben Lindqvist',
		'released-from-session-bare'                      => 'Ben Lindqvist',
		'reminder-call'                                   => array( 0 => 'Ada Okonjo', 1 => 'Ben Lindqvist' ),
		'reminder-call-bare'                              => array( 0 => 'Kai O\'Example', 1 => 'Ben Lindqvist' ),
		'reminder-session-of-one'                         => array( 1 => 'Ben Lindqvist' ),
		'reminder-session-of-three'                       => array( 1 => 'Ben Lindqvist', 2 => 'Ben Lindqvist', 3 => 'Ben Lindqvist' ),
		'reminder-call-mentor-name-with-a-comma'          => array( 0 => 'Ada Okonjo' ),
		'booked-call-mentor-name-tag-like'                => array( 0 => 'Ada Okonjo' ),
	);

	foreach ( $bare as $case => $names ) {
		foreach ( (array) $names as $n => $name ) {
			$row = array(
				'case'    => $case,
				'item'    => 4,
				'replace' => array( 'headers' => array( 'Reply-To: "' . $name . '"' => 'Reply-To: ' . $name ) ),
			);

			if ( is_array( $names ) ) {
				$row['only'] = array( $n );
			}

			$rows[] = $row;
		}
	}

	return $rows;
}

/**
 * A case's emails as 1.122.23 sends them: as recorded, with the changes `changes()` lists for it
 * made in turn. A pair whose recorded text an email does not hold is noted in `$GLOBALS['stale']`,
 * which a check below holds empty. So is a row whose `only` names a place the recording holds no
 * email at, and a pair for a field the email has not, which would otherwise change nothing and pass.
 *
 * @param string     $case     The case.
 * @param array      $recorded Its emails, as recorded.
 * @param array|null $rows     The changes, `changes()` unless a check hands its own.
 * @return array
 */
function as_changed( $case, array $recorded, $rows = null ) {
	foreach ( null === $rows ? changes() : $rows as $row ) {
		if ( $case !== $row['case'] ) {
			continue;
		}

		foreach ( isset( $row['only'] ) ? array_diff( $row['only'], array_keys( $recorded ) ) : array() as $none ) {
			$GLOBALS['stale'][] = $case . ' #' . $none . ': no such email';
		}

		foreach ( $recorded as $n => $mail ) {
			// A row may name the emails of its case it changes, by their place in the recording.
			if ( isset( $row['only'] ) && ! in_array( $n, $row['only'], true ) ) {
				continue;
			}

			foreach ( $row['replace'] as $field => $pairs ) {
				if ( ! array_key_exists( $field, $mail ) ) {
					$GLOBALS['stale'][] = $case . ' #' . $n . ' ' . $field . ': no such field';
					continue;
				}

				foreach ( $pairs as $old => $new ) {
					$was            = $mail[ $field ];
					$mail[ $field ] = str_replace( (string) $old, $new, $mail[ $field ] );

					if ( $mail[ $field ] === $was ) {
						$GLOBALS['stale'][] = $case . ' #' . $n . ' ' . $field . ': ' . $old;
					}
				}
			}

			$recorded[ $n ] = $mail;
		}
	}

	return $recorded;
}

$fixture = __DIR__ . '/fixtures/calls-reports-1-122-20.json';
$added   = __DIR__ . '/fixtures/calls-reports-added-1-122-22.json';

if ( in_array( '--record-added', $argv, true ) ) {
	// Made once, from 1.122.22, before anything of 1.122.23 moved.
	if ( file_exists( $added ) ) {
		fwrite( STDERR, "The recording exists and is never made twice.\n" );
		exit( 1 );
	}

	$recorded = array(
		'_comment' => 'What the cases 1.122.23 adds said in 1.122.22, recorded through the real senders and WPCPM_Mail by bin/test-calls-reports-text.php --record-added before anything of 1.122.23 moved. Invented names, every address written "at example.test", a fake meeting code, and each calendar file unfolded with its DTSTAMP written "recorded". Never edit by hand: 1.122.23 is held to these bytes but for the changes the suite lists.',
		'cases'    => array(),
	);

	foreach ( added_cases() as $name => $case ) {
		$recorded['cases'][ $name ] = emails_of( $case );
	}

	file_put_contents( $added, json_encode( $recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . "\n" );
	printf( "Recorded %d cases, %d emails, in %s\n", count( $recorded['cases'] ), array_sum( array_map( 'count', $recorded['cases'] ) ), basename( $added ) );

	exit( 0 );
}

if ( in_array( '--record', $argv, true ) ) {
	// Made once, from 1.122.20: a recording made again would be made from whatever the code says
	// then, and would prove nothing.
	if ( file_exists( $fixture ) ) {
		fwrite( STDERR, "The recording exists and is never made twice.\n" );
		exit( 1 );
	}

	$recorded = array(
		'_comment' => 'What each Mentor calls and Semester reports email said in 1.122.20, recorded through the real senders and WPCPM_Mail by bin/test-calls-reports-text.php --record before the emails moved into the template registry. Invented names, every address written "at example.test", a fake meeting code, and each calendar file unfolded with its DTSTAMP written "recorded". Never edit by hand: the registry is held to these bytes.',
		'cases'    => array(),
	);

	foreach ( cases() as $name => $case ) {
		$recorded['cases'][ $name ] = emails_of( $case );
	}

	file_put_contents( $fixture, json_encode( $recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
	printf( "Recorded %d cases, %d emails, in %s\n", count( $recorded['cases'] ), array_sum( array_map( 'count', $recorded['cases'] ) ), basename( $fixture ) );

	exit( 0 );
}

$recorded = is_file( $fixture ) ? json_decode( (string) file_get_contents( $fixture ), true ) : null;
$cases    = cases();

ck( 'the recording is there, and holds every case', is_array( $recorded ) && isset( $recorded['cases'] ) ? array_keys( $recorded['cases'] ) : null, array_keys( $cases ) );
ck( 'the recording is the one made from 1.122.20', is_file( $fixture ) ? hash_file( 'sha256', $fixture ) : null, '29341e03ddb31ae9bd24d7fce8e8a3add2d9131d348d1d7a3d3bae58c3bf1fcc' );

$more = is_file( $added ) ? json_decode( (string) file_get_contents( $added ), true ) : null;

ck( 'the recording of the cases 1.122.23 adds is there, and holds every one of them', is_array( $more ) && isset( $more['cases'] ) ? array_keys( $more['cases'] ) : null, array_keys( added_cases() ) );
ck( 'and it is the one made from 1.122.22', is_file( $added ) ? hash_file( 'sha256', $added ) : null, 'd9461cf1e6a3165a68309b6148a6a9c72b509251d3740280b7332f9fee680ce1' );

// Every case of both recordings, as 1.122.23 sends it: as recorded, but for the changes listed.
$GLOBALS['stale'] = array();
$listed           = array_unique( array_column( changes(), 'case' ) );
$changed          = array();

foreach ( array( array( $cases, $recorded ), array( added_cases(), $more ) ) as $pair ) {
	foreach ( $pair[0] as $name => $case ) {
		$held = isset( $pair[1]['cases'][ $name ] ) ? $pair[1]['cases'][ $name ] : array();
		$want = as_changed( $name, $held );
		$got  = emails_of( $case );
		$as   = in_array( $name, $listed, true ) ? ', as 1.122.23 changes it' : '';

		if ( $want !== $held ) {
			$changed[] = $name;
		}

		ck( $name . ': the same emails, to the same people, in the same order' . $as, array_column( $got, 'to' ), array_column( $want, 'to' ) );

		foreach ( $want as $n => $mail ) {
			$now = isset( $got[ $n ] ) ? $got[ $n ] : array_fill_keys( array_keys( $mail ), null );

			// As 1.123.24 and then 1.123.27 send it: the footer's address line, and a Semester reports email's
			// subject opening with the site's name in brackets.
			$since = wpcpm_mail_since_1_123_27( array( 'body' => wpcpm_mail_since_1_123_24( body_as( $name, $mail['body'] ) ) ) + $mail, array( 'name' => $case['site'] ) );

			ck( $name . ' #' . $n . ': logged under its own id' . $as, $now['context'], sent_as( $name, $n, $mail['context'] ) );
			ck( $name . ' #' . $n . ': the subject, byte for byte' . $as, $now['subject'], $since['subject'] );
			ck( $name . ' #' . $n . ( body_as( $name, $mail['body'] ) === $mail['body'] ? ': the body, byte for byte, the signature and footer included' : ': the body, byte for byte, the signature and footer included, but the blank line the recording doubles above the footer' ) . $as, $now['body'], $since['body'] );
			ck( $name . ' #' . $n . ': the headers and the calendar files, byte for byte' . $as, array( $now['headers'], $now['attachments'] ), array( $mail['headers'], $mail['attachments'] ) );
		}
	}
}

ck( 'every change listed is made, to a case of one of the two recordings, and changes what its recording holds', array( $GLOBALS['stale'], array_values( array_diff( $listed, $changed ) ) ), array( array(), array() ) );

// 1.123.24: the site's address on the line under the signature's first, in every recorded email, none
// of whose bodies names the home page whole, and no sponsor rejection among them.
ck( 'every recorded email ends with the site\'s address in its footer, as 1.123.24 sends it, and nothing else of 1.123.24 is in them', $GLOBALS['wpcpm_since_1_123_24'], array( 'declined' => 0, 'site_line' => 96, 'site_kept' => 0 ) );

// 1.123.27: the subject of each Semester reports email opens with the site's name in brackets, in the
// recorded emails of the three, and nothing else of 1.123.27 is in them.
$reported = 0;
foreach ( array( $recorded, $more ) as $recording ) {
	foreach ( is_array( $recording ) && isset( $recording['cases'] ) ? $recording['cases'] : array() as $mails ) {
		foreach ( $mails as $mail ) {
			$reported += in_array( $mail['context'], array( 'report-drafted', 'report-approved', 'report-consent' ), true ) ? 1 : 0;
		}
	}
}
ck( 'every Semester reports email opens its subject with the site\'s name in brackets, as 1.123.27 sends it, and nothing else of 1.123.27 is in them', array( $GLOBALS['wpcpm_since_1_123_27']['report_subject'], $reported ), array( $reported, $reported ) );
ck( 'and they are the 30 recorded emails of the three, the managers\' notices of a draft, the approvals and the questions to the students', $reported, 30 );
ck( 'no other change of 1.123.27 touched them', array_sum( array_diff_key( $GLOBALS['wpcpm_since_1_123_27'], array( 'report_subject' => 0 ) ) ), 0 );

// The check above holds for every row: a row whose `only` names no email of its case, or whose pair
// names a field no email has, changes nothing, and is noted as a row that does.
$GLOBALS['stale'] = array();
as_changed(
	'a-case',
	array( array( 'subject' => 'Old', 'body' => 'Old', 'headers' => array() ) ),
	array(
		array( 'case' => 'a-case', 'item' => 0, 'only' => array( 0, 5 ), 'replace' => array( 'subject' => array( 'Old' => 'New' ) ) ),
		array( 'case' => 'a-case', 'item' => 0, 'replace' => array( 'subjet' => array( 'Old' => 'New' ) ) ),
	)
);
ck( 'and a row whose `only` names no email of its case, or whose field no email has, is noted as a change that changes nothing', $GLOBALS['stale'], array( 'a-case #5: no such email', 'a-case #0 subjet: no such field' ) );
$GLOBALS['stale'] = array();

// And the one line the move takes out, in those five cases alone: one line ending in each of their
// ten emails, above the footer.
$shortened = array();
$doubled   = array();
foreach ( is_array( $recorded ) && isset( $recorded['cases'] ) ? $recorded['cases'] : array() as $name => $mails ) {
	foreach ( $mails as $n => $mail ) {
		if ( body_as( $name, $mail['body'] ) !== $mail['body'] ) {
			$shortened[] = $name . ' #' . $n . ': ' . ( strlen( $mail['body'] ) - strlen( body_as( $name, $mail['body'] ) ) );
		}

		if ( false !== strpos( $mail['body'], "\n\n\n-- \n" ) ) {
			$doubled[] = $name . ' #' . $n . ': ' . ( strlen( $mail['body'] ) - strlen( str_replace( "\n\n\n-- \n", "\n\n-- \n", $mail['body'] ) ) );
		}
	}
}
ck(
	'the move takes one line ending out of ten recorded emails, the reports\' with no Institution Dashboard page published, and out of no other',
	$shortened,
	array(
		'report-drafted-bare #0: 1',
		'report-drafted-bare #1: 1',
		'report-drafted-in-progress-bare #0: 1',
		'report-drafted-in-progress-bare #1: 1',
		'report-approved-bare #0: 1',
		'report-approved-bare #1: 1',
		'report-drafted-administrator-page-only #0: 1',
		'report-drafted-administrator-page-only #1: 1',
		'report-approved-administrator-page-only #0: 1',
		'report-approved-administrator-page-only #1: 1',
	)
);
ck( 'and those ten are every recorded email whose footer stands under a doubled blank line, in either area', $doubled, $shortened );

// The one thing the move changes on purpose, and so not in the recording: each email of the Mentor
// calls written for the mentor alone has the mentor's audience now (the two a session sends everybody
// in the same words keep an empty one), and for an account that holds two roles the audience decides
// which kind of person it is (`WPCPM_Mail_Capture::type_of()`). A mentor who is also a student was
// sent the mentor's booking under an id that went to both, so the footer named the Student Report
// Card under a body that names the Mentor Report Card; under the mentor's own template the footer's
// dashboard is the Mentor Report Card, which the body names already, so the footer leaves it out, and
// the Log reads the recipient as a mentor.
$GLOBALS['users'][20]->roles = array( WPCPM_Roles::ROLE_STUDENT, WPCPM_Roles::ROLE_MENTOR );
$two_roles                   = emails_of( $cases['booked-call'] );
// The Log is asked while the account still holds both roles: asked after the account is a mentor
// alone, it would read a mentor whatever the template's audience was.
ck(
	'a mentor who is also a student is sent the mentor\'s booking as a mentor: the Mentor Report Card once, in the body, and no Student Report Card in the footer',
	array( isset( $two_roles[0] ) ? $two_roles[0]['body'] : null, WPCPM_Mail_Capture::type_of( $GLOBALS['users'][20], WPCPM_Mail_Catalog::get( isset( $two_roles[0] ) ? $two_roles[0]['context'] : '' )['audience'] ?? '' ) ),
	array( wpcpm_mail_since_1_123_24( $recorded['cases']['booked-call'][0]['body'] ?? '' ), WPCPM_Mail_Catalog::TYPE_MENTOR )
);
$GLOBALS['users'][20]->roles = array( WPCPM_Roles::ROLE_MENTOR );

// Each email is built in its recipient's language, its values too, in one request: a mentor whose
// account reads Polish is sent the session's reminder in Polish, the count of students coming
// included, while the students on it are sent theirs in English. The template registry is built once
// for each of the two languages (`WPCPM_Mail_Templates::all()`).
$GLOBALS['user_locale'][20] = 'pl_PL';
$polish                     = emails_of( $cases['reminder-session-of-three'] );
unset( $GLOBALS['user_locale'][20] );
ck(
	'a mentor who reads Polish is sent the reminder in Polish, the students coming counted in Polish too, and the students theirs in English',
	array_column( $polish, 'subject' ),
	array(
		"[pl_PL] [Ada & Ben's 100% Credits] Reminder: your group session with [pl_PL] 3 students",
		"[Ada & Ben's 100% Credits] Reminder: your group session with Ben Lindqvist",
		"[Ada & Ben's 100% Credits] Reminder: your group session with Ben Lindqvist",
		"[Ada & Ben's 100% Credits] Reminder: your group session with Ben Lindqvist",
	)
);

ck( 'and the recording holds no address, no real meeting code and no record ID but the seeds\'', is_file( $fixture ) ? array( preg_match( '/[A-Za-z0-9._%+-]*@[A-Za-z0-9-]+\.[A-Za-z]{2,}/', (string) file_get_contents( $fixture ) ), preg_match_all( '/pwd=([A-Za-z0-9-]+)/', (string) file_get_contents( $fixture ), $codes ) > 0 ? array_values( array_unique( $codes[1] ) ) : array(), preg_match( '/rec(?!SEED0\d{9})[A-Za-z0-9]{14}/', (string) file_get_contents( $fixture ) ) ) : null, array( 0, array( 'EXAMPLE-NOT-A-REAL-CODE' ), 0 ) );
$text = is_file( $added ) ? (string) file_get_contents( $added ) : '';
ck( 'and so does the recording of the cases 1.122.23 adds', is_file( $added ) ? array( preg_match( '/[A-Za-z0-9._%+-]*@[A-Za-z0-9-]+\.[A-Za-z]{2,}/', $text ), preg_match_all( '/pwd=([A-Za-z0-9-]+)/', $text, $codes ) > 0 ? array_values( array_unique( $codes[1] ) ) : array(), preg_match( '/rec(?!SEED0\d{9})[A-Za-z0-9]{14}/', $text ) ) : null, array( 0, array( 'EXAMPLE-NOT-A-REAL-CODE' ), 0 ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
