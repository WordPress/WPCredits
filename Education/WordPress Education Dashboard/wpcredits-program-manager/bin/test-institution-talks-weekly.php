<?php
/**
 * The Monday email of follow-ups due (Institutions off Airtable, release 2, Task 17).
 *
 * What is pinned here and why:
 *
 * - **One rule.** An email lists exactly the recipient's own Mine follow-ups due, as the Talks view's menu count has
 *   them (`WPCPM_Institution_Talks_View::due_for()`): the schools assigned to them, the not assigned ones in their
 *   area, and the not assigned ones in nobody's area, which are in every recipient's email. A school assigned to an
 *   account that no longer manages the program counts as not assigned. What is due is `WPCPM_Institution_Talks::due()`
 *   alone: Confirmed, Student, Not Moving Forward, SPAM, an unknown stage and a closed lead are never listed.
 * - **Who is mailed:** an account that manages the program and has an area or an assigned school; an account with
 *   neither, a WordPress administrator account among them, never; a recipient with no line, never.
 * - **Two parts, never an empty one:** their own schools under "These follow-ups are due, oldest first:", then the not
 *   assigned ones under "Not assigned to anybody:"; a part with no line is left out with its heading; the subject's
 *   count is the number of lines.
 * - **When:** Mondays (UTC), in site mode, once in an ISO week, the week noted before the first email, under the lock;
 *   the daily event at 07:00 UTC is on the clock in site mode only.
 * - **The template:** its label and audience, subject, body, line ending and placeholders.
 *
 * No mail leaves this suite: `WPCPM_Mail::send()` is a recorder.
 *
 * Run from the plugin root:  php bin/test-institution-talks-weekly.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '' ) {
		$this->ID           = (int) $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( ' ', '.', $name ) );
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Error {
	private $code, $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

$GLOBALS['opts']     = array(
	'date_format' => 'j F Y',
	'time_format' => 'H:i',
);
$GLOBALS['cron']     = array();
$GLOBALS['mails']    = array();
$GLOBALS['held']     = false;
$GLOBALS['taken']    = 0;
$GLOBALS['on_site']  = true;
$GLOBALS['building'] = 0;
$GLOBALS['manage']   = array( 7, 11, 12, 13 );

// A line's words are asked for while an email is built, and say whose language they were built in.
function __( $s, $d = null ) {
	if ( $GLOBALS['building'] > 0 && in_array( $s, array( '%1$s: %2$s, due %3$s', '%1$s, due %2$s' ), true ) ) {
		return '«' . $GLOBALS['building'] . '»' . $s;
	}
	return $s;
}
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function add_query_arg( ...$args ) {
	$pairs = is_array( $args[0] ) ? $args[0] : array( $args[0] => $args[1] );
	$url   = (string) ( is_array( $args[0] ) ? $args[1] : $args[2] );
	foreach ( $pairs as $k => $v ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
	}
	return $url;
}
function get_bloginfo( $k = 'name' ) { return 'Education Directory'; }
function is_email( $s ) { return false !== filter_var( (string) $s, FILTER_VALIDATE_EMAIL ) ? $s : false; }
// The options as core reads them through the object cache: an option this process found absent is answered absent
// from `notoptions` until that list is dropped, whatever another process has written to the table since.
$GLOBALS['cache'] = array();
function wp_cache_get( $k, $g = '' ) { return $GLOBALS['cache'][ $g ][ $k ] ?? false; }
function wp_cache_set( $k, $v, $g = '' ) { $GLOBALS['cache'][ $g ][ $k ] = $v; return true; }
function get_option( $k, $d = false ) {
	$not = wp_cache_get( 'notoptions', 'options' );
	if ( is_array( $not ) && isset( $not[ $k ] ) ) { return $d; }
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return $GLOBALS['opts'][ $k ]; }
	$not       = is_array( $not ) ? $not : array();
	$not[ $k ] = true;
	wp_cache_set( 'notoptions', $not, 'options' );
	return $d;
}
function update_option( $k, $v, $autoload = null ) {
	$GLOBALS['opts'][ $k ] = $v;
	$not                   = wp_cache_get( 'notoptions', 'options' );
	if ( is_array( $not ) ) { unset( $not[ $k ] ); wp_cache_set( 'notoptions', $not, 'options' ); }
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_cache_delete( $k, $g = '' ) { unset( $GLOBALS['cache'][ $g ][ $k ] ); return true; }
function get_locale() { return 'en_US'; }
function get_current_user_id() { return 0; }
function get_user_meta( $id, $k, $single = false ) { return ''; }
function wp_timezone_string() { return 'UTC'; }
function wp_timezone() { return new DateTimeZone( 'UTC' ); }
function wp_date( $format, $timestamp = null, $zone = null ) { return gmdate( $format, null === $timestamp ? time() : (int) $timestamp ); }
function date_i18n( $format, $timestamp = null ) { return gmdate( $format, null === $timestamp ? time() : (int) $timestamp ); }
function add_action() {}
function add_filter() {}
function apply_filters( $hook, $value ) { return $value; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function remove_accents( $text, $locale = '' ) { return (string) $text; }
function wp_mail() { throw new Exception( 'No mail leaves a suite.' ); }
// What the run writes to the PHP log goes to a file of this run's, read by a check.
require_once __DIR__ . '/stubs/temp-dir.php';
$GLOBALS['log_file'] = wpcpm_test_temp_dir() . 'php.log';
ini_set( 'log_errors', '1' );
ini_set( 'error_log', $GLOBALS['log_file'] );
function logged() { clearstatcache(); return is_file( $GLOBALS['log_file'] ) ? (string) file_get_contents( $GLOBALS['log_file'] ) : ''; }
function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['cron'][ $hook ]['at'] ?? false; }
function wp_schedule_event( $at, $recurrence, $hook, $args = array() ) { $GLOBALS['cron'][ $hook ] = array( 'at' => (int) $at, 'every' => $recurrence ); return true; }
function wp_clear_scheduled_hook( $hook, $args = array() ) { $had = isset( $GLOBALS['cron'][ $hook ] ) ? 1 : 0; unset( $GLOBALS['cron'][ $hook ] ); return $had; }

$GLOBALS['users'] = array();
foreach ( array( 7 => 'Ana Admin', 11 => 'Piotr Admin', 12 => 'Mei Admin', 13 => 'Site Technician', 14 => 'Former Admin', 30 => 'Not Staff' ) as $wpcpm_id => $wpcpm_name ) {
	$GLOBALS['users'][ $wpcpm_id ] = new WP_User( $wpcpm_id, $wpcpm_name, strtolower( str_replace( ' ', '.', $wpcpm_name ) ) . '@example.test' );
}
function user_can( $user, $cap ) { $id = is_object( $user ) ? (int) $user->ID : (int) $user; return 'wpcpm_manage_program' === $cap && in_array( $id, $GLOBALS['manage'], true ); }
function get_user_by( $field, $value ) { return 'id' === $field ? ( $GLOBALS['users'][ (int) $value ] ?? false ) : false; }

require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';

class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) { return 'institutions_on_site' === $key ? $GLOBALS['on_site'] : $fallback; }
}
class WPCPM_Airtable {
	public static function is_record_id( $v ) { return is_scalar( $v ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); }
}
class WPCPM_Institutions_Index {
	public static function on_site() { return (bool) $GLOBALS['on_site']; }
	public static function rows() { return $GLOBALS['rows']; }
}
class WPCPM_Institution_Record {
	public static function answers_for( array $ids ) { return array_intersect_key( $GLOBALS['answers'], array_flip( $ids ) ); }
	public static function get( $id ) { return $GLOBALS['answers'][ $id ] ?? array(); }
}
class WPCPM_Institution_Leads {
	public static function all() { return $GLOBALS['leads']; }
	public static function get( $key ) { return $GLOBALS['leads'][ $key ] ?? null; }
}
class WPCPM_Institution_Assignment {
	const RECORD_KEY = 'assigned';
}
/** The areas: country record ID => the Administrator who holds it. */
class WPCPM_Institution_Areas {
	public static function administrator_for( $country ) { return (int) ( $GLOBALS['areas'][ (string) $country ] ?? 0 ); }
	public static function has_area( $user_id ) { return in_array( (int) $user_id, array_map( 'intval', $GLOBALS['areas'] ), true ); }
}
/** Every account that manages the program and has an address, as the module's own answers it. */
class WPCPM_Institutions {
	public static function managers() {
		$out = array();
		foreach ( $GLOBALS['manage'] as $id ) {
			$out[] = $GLOBALS['users'][ $id ];
		}
		return $out;
	}
}
class WPCPM_Lock {
	public static function take( $name, $ttl ) { ++$GLOBALS['taken']; return $GLOBALS['held'] ? '' : 'token'; }
	public static function release( $name, $token ) { return true; }
}
/** The mail layer, recorded: each email is built for its one recipient, in their language, and nothing is sent. */
class WPCPM_Mail {
	public static function site_name() { return 'Education Directory'; }
	public static function send( $recipient, $context, $build ) {
		$user                = $recipient instanceof WP_User ? $recipient : get_user_by( 'id', (int) $recipient );
		$GLOBALS['building'] = (int) $user->ID;
		$mail                = $build( $user );
		$GLOBALS['building'] = 0;
		$GLOBALS['mails'][]  = array(
			'to'      => $user,
			'context' => $context,
			'subject' => $mail['subject'],
			'body'    => $mail['body'],
			'week'    => get_option( 'wpcpm_institution_talks_week', '' ),
			'extra'   => array_diff_key( $mail, array_flip( array( 'subject', 'body' ) ) ),
		);
		return true;
	}
}
require_once __DIR__ . '/stubs/institution-card-url.php';
class WPCPM_Institutions_Dashboard {
	public static function page_url() { return 'https://example.test/institution-dashboard/'; }
	public static function card_url( $card, $record = '', $item = '', array $args = array() ) { return wpcpm_test_institution_card_url( self::page_url(), $card, $record, $item, $args ); }
}
class WPCPM_Administrators_Dashboard {
	const VIEW_TALKS = 'talks';
	public static function page_url() { return 'https://example.test/administrator-dashboard/'; }
	public static function view_url( $view = 'overview' ) { return 'overview' === $view ? self::page_url() : self::page_url() . '?wpcpm_view=' . $view; }
}
class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student-report-card/'; }
}
class WPCPM_Mentors_Dashboard {
	public static function page_url() { return 'https://example.test/mentor-report-card/'; }
}
class WPCPM_Sponsors_Dashboard {
	public static function page_url() { return 'https://example.test/sponsor-dashboard/'; }
}

require_once __DIR__ . '/../includes/class-wpcpm-dates.php';
require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-talks.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-talks-view.php';
if ( is_file( __DIR__ . '/../includes/modules/class-wpcpm-institution-talks-weekly.php' ) ) {
	require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-talks-weekly.php';
}

$total = 0;
$fails = 0;
function ck( $label, $actual, $expected ) {
	global $total, $fails;
	++$total;
	if ( $actual === $expected ) { echo "  ok   {$label}\n"; return; }
	++$fails;
	echo "  FAIL {$label}\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
}
function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, $needle ); }

if ( ! class_exists( 'WPCPM_Institution_Talks_Weekly' ) || ! method_exists( 'WPCPM_Institution_Talks_View', 'due_for' ) ) {
	ck( 'the Monday email and the view\'s due_for() are built', false, true );
	printf( "\n%d FAILED (%d checks)\n", $fails, $total );
	exit( 1 );
}

/* ---- the world ---------------------------------------------------------- */

const PL = 'recCTY0000000PL01'; // Ana's area.
const RO = 'recCTY0000000RO01'; // Mei's area.
const JP = 'recCTY0000000JP01'; // Nobody's area.

// Monday 23 November 2026, 07:00 UTC; ISO week 2026-48. Every date due here is before 19 November 2026, so the view's
// own today agrees with the run's whenever the suite runs.
const MONDAY = 1795417200;

/** An index row and its record's answers. */
function school( $id, $name, $stage, $country, $assigned, $follow, $step = '' ) {
	$GLOBALS['rows'][ $id ]    = array( 'record_id' => $id, 'name' => $name, 'stage' => $stage, 'country' => $country, 'country_name' => '', 'city' => '', 'website' => '', 'contact_person' => '', 'contact_email' => '' );
	$GLOBALS['answers'][ $id ] = array( 'assigned' => $assigned, 'follow_up' => $follow, 'next_step' => $step );
}
/** A lead, as `WPCPM_Institution_Leads::get()` gives one. */
function lead( $key, $name, $assigned, $follow, $step, $closed = '' ) {
	$GLOBALS['leads'][ $key ] = array( 'key' => $key, 'closed' => $closed, 'name' => $name, 'country' => '', 'country_name' => '', 'country_text' => '', 'city' => '', 'contact_person' => '', 'contact_email' => '', 'assigned' => $assigned, 'follow_up' => $follow, 'next_step' => $step, 'estimated_students' => '', 'first_students' => '', 'last_contact' => '', 'help_desk' => '', 'lead_owner' => '' );
}

function world() {
	$GLOBALS['rows']    = array();
	$GLOBALS['answers'] = array();
	$GLOBALS['leads']   = array();
	$GLOBALS['areas']   = array( PL => 7, RO => 12 );
	$GLOBALS['manage']  = array( 7, 11, 12, 13 );
	$GLOBALS['on_site'] = true;
	$GLOBALS['held']    = false;
	$GLOBALS['taken']   = 0;
	$GLOBALS['mails']   = array();
	if ( is_file( $GLOBALS['log_file'] ) ) { unlink( $GLOBALS['log_file'] ); }
	WPCPM_Institution_Talks_View::forget();
	unset( $GLOBALS['opts']['wpcpm_institution_talks_week'] );
	$GLOBALS['cache'] = array();

	// Piotr's own: two due, one with a next step; and every stage that is never due.
	school( 'recEXA00000000001', 'Wyższa Szkoła Górnołąćka', 'Waiting on Reply', PL, 11, '2026-11-10', "Send the agreement\nagain" );
	school( 'recEXA00000000002', 'Universitatea Câmpiei Rîușițeni', 'Agreement Sent', RO, 11, '2026-11-02' );
	school( 'recEXA00000000003', 'Confirmed Example School', 'Confirmed', PL, 11, '2026-10-01', 'Ask for the report' );
	school( 'recEXA00000000004', 'Student Example School', 'Student', PL, 11, '2026-10-02' );
	school( 'recEXA00000000005', 'Not Moving Example School', 'Not Moving Forward', PL, 11, '2026-10-03' );
	school( 'recEXA00000000006', 'Spam Example School', 'SPAM', PL, 11, '2026-10-04' );
	school( 'recEXA00000000007', 'Rejected Example School', 'Rejected', PL, 11, '2026-10-05' );
	school( 'recEXA00000000008', 'Later Example School', 'Agreement Sent', PL, 11, '2099-12-01' );
	// Ana's own, at Revisit Later; and a school in her area assigned to nobody.
	school( 'recEXA00000000009', 'Revisit Example School', 'Revisit Later', PL, 7, '2026-11-18', 'Write in spring' );
	school( 'recEXA00000000010', 'جامعة الواحة الخضراء المتخيلة', 'Call Scheduled', PL, 0, '2026-11-17' );
	// Assigned to nobody, in nobody's area: in every Mine.
	school( 'recEXA00000000011', '架空山学園大学', 'Info Sent', JP, 0, '2026-11-15', 'Call the dean' );
	// Assigned to an account that no longer manages the program, in Mei's area: not assigned, so Mei's.
	school( 'recEXA00000000012', 'Former Example School', 'First Contact Made', RO, 14, '2026-11-16' );
	// Piotr's lead, due; and his closed lead, never.
	lead( 'lead-5', 'Lead Example School', 11, '2026-11-12', 'Call the rector' );
	lead( 'lead-6', 'Closed Lead Example School', 11, '2026-11-01', 'Nothing', '2026-11-05' );
}
function mails_to( $id ) {
	return array_values( array_filter( $GLOBALS['mails'], static function ( $m ) use ( $id ) { return (int) $m['to']->ID === (int) $id; } ) );
}
function url_of( $key ) { return WPCPM_Institution_Talks::url( $key ); }

/* ---- the names ------------------------------------------------------------ */

echo "=== The names ===\n";
ck( 'the hook, the week, the lock and the email id', array( WPCPM_Institution_Talks_Weekly::CRON_DAILY, WPCPM_Institution_Talks_Weekly::OPT_WEEK, WPCPM_Institution_Talks_Weekly::LOCK, WPCPM_Institution_Talks_Weekly::MAIL ), array( 'wpcpm_institution_talks_daily', 'wpcpm_institution_talks_week', 'wpcpm_institution_talks_lock', 'institution-follow-ups-weekly' ) );

/* ---- when ------------------------------------------------------------------ */

echo "\n=== When ===\n";
ck( 'next_run() of Monday 06:59 UTC is 07:00 that day', WPCPM_Institution_Talks_Weekly::next_run( MONDAY - 60 ), MONDAY );
ck( 'and of Monday 07:00 UTC, 07:00 the next day', WPCPM_Institution_Talks_Weekly::next_run( MONDAY ), MONDAY + DAY_IN_SECONDS );

world();
$GLOBALS['cron'] = array();
WPCPM_Institution_Talks_Weekly::sync_schedule();
ck( 'in site mode the daily event is put on the clock, daily, at a 07:00 UTC', array( isset( $GLOBALS['cron']['wpcpm_institution_talks_daily'] ), $GLOBALS['cron']['wpcpm_institution_talks_daily']['every'] ?? '', gmdate( 'H:i', $GLOBALS['cron']['wpcpm_institution_talks_daily']['at'] ?? 0 ) ), array( true, 'daily', '07:00' ) );
$GLOBALS['cron']['wpcpm_institution_talks_daily']['at'] = 42;
WPCPM_Institution_Talks_Weekly::sync_schedule();
ck( 'and one already on it is left as it is', $GLOBALS['cron']['wpcpm_institution_talks_daily']['at'], 42 );
$GLOBALS['on_site'] = false;
WPCPM_Institution_Talks_Weekly::sync_schedule();
ck( 'outside site mode the event is taken off the clock', isset( $GLOBALS['cron']['wpcpm_institution_talks_daily'] ), false );
WPCPM_Institution_Talks_Weekly::sync_schedule();
ck( 'and stays off it', $GLOBALS['cron'], array() );
$GLOBALS['cron']['wpcpm_institution_talks_daily'] = array( 'at' => 42, 'every' => 'daily' );
WPCPM_Institution_Talks_Weekly::unschedule();
ck( 'unschedule() takes it off the clock', $GLOBALS['cron'], array() );

world();
$GLOBALS['on_site'] = false;
ck( 'outside site mode a Monday run sends nothing, notes no week and takes no lock', array( WPCPM_Institution_Talks_Weekly::run( MONDAY ), $GLOBALS['mails'], get_option( 'wpcpm_institution_talks_week', 'none' ), $GLOBALS['taken'] ), array( 0, array(), 'none', 0 ) );

world();
ck( 'a Tuesday run sends nothing and notes no week', array( WPCPM_Institution_Talks_Weekly::run( MONDAY + DAY_IN_SECONDS ), $GLOBALS['mails'], get_option( 'wpcpm_institution_talks_week', 'none' ) ), array( 0, array(), 'none' ) );
ck( 'nor does a Sunday 23:59 UTC run', WPCPM_Institution_Talks_Weekly::run( MONDAY - 7 * HOUR_IN_SECONDS - 60 ), 0 );

world();
ck( 'late_cron_before_seven: a Monday run at 06:59 UTC (Sunday\'s event run late) sends nothing and notes no week', array( WPCPM_Institution_Talks_Weekly::run( MONDAY - 60 ), $GLOBALS['mails'], get_option( 'wpcpm_institution_talks_week', 'none' ) ), array( 0, array(), 'none' ) );
ck( 'late_cron_before_seven: and the same Monday at 07:00 UTC sends', WPCPM_Institution_Talks_Weekly::run( MONDAY ), 3 );

world();
// Another process, B, read the week before A ran and was answered "absent"; A then sent and noted the week in the
// table, which B's cache does not know.
get_option( 'wpcpm_institution_talks_week', '' );
$GLOBALS['opts']['wpcpm_institution_talks_week'] = '2026-48';
ck( 'week_reread_drops_notoptions: B, its cache saying the week is absent, reads it again under the lock from the table and sends nothing', array( WPCPM_Institution_Talks_Weekly::run( MONDAY ), $GLOBALS['mails'] ), array( 0, array() ) );

world();
$GLOBALS['held'] = true;
ck( 'a Monday run that finds the lock held sends nothing and notes no week', array( WPCPM_Institution_Talks_Weekly::run( MONDAY ), $GLOBALS['mails'], get_option( 'wpcpm_institution_talks_week', 'none' ) ), array( 0, array(), 'none' ) );

/* ---- the Monday run ------------------------------------------------------- */

echo "\n=== The Monday run ===\n";
world();
$sent = WPCPM_Institution_Talks_Weekly::run( MONDAY );
ck( 'three emails go, one to each of Ana, Piotr and Mei, each to that one account and with no other address', array( $sent, array_map( static function ( $m ) { return $m['to']->ID; }, $GLOBALS['mails'] ), array_values( array_unique( array_map( static function ( $m ) { return $m['context']; }, $GLOBALS['mails'] ) ) ), array_values( array_filter( array_map( static function ( $m ) { return $m['extra']; }, $GLOBALS['mails'] ) ) ) ), array( 3, array( 7, 11, 12 ), array( 'institution-follow-ups-weekly' ), array() ) );
ck( 'the week was noted before the first email went', array_map( static function ( $m ) { return $m['week']; }, $GLOBALS['mails'] ), array( '2026-48', '2026-48', '2026-48' ) );
ck( 'the Site Technician, who manages the program but has no area and no school, is not mailed, though a school of nobody\'s area is in their Mine', array( mails_to( 13 ), WPCPM_Institution_Talks_View::due_count( 13 ) ), array( array(), 1 ) );
ck( 'nor is the former Administrator a school is still stored as assigned to, nor an account that does not manage the program', array( mails_to( 14 ), mails_to( 30 ) ), array( array(), array() ) );
ck( 'nothing about a recipient is written to a log', logged(), '' );

$piotr = mails_to( 11 )[0];
$want  = "These follow-ups are due, oldest first:\r\n"
	. '«11»Universitatea Câmpiei Rîușițeni, due 2 November 2026: ' . url_of( 'recEXA00000000002' ) . "\r\n"
	. '«11»Wyższa Szkoła Górnołąćka: Send the agreement again, due 10 November 2026: ' . url_of( 'recEXA00000000001' ) . "\r\n"
	. '«11»Lead Example School: Call the rector, due 12 November 2026: ' . url_of( 'lead-5' ) . "\r\n"
	. "\r\n"
	. "Not assigned to anybody:\r\n"
	. '«11»架空山学園大学: Call the dean, due 15 November 2026: ' . url_of( 'recEXA00000000011' ) . "\r\n"
	. "\r\n"
	. 'Open the Talks view: https://example.test/administrator-dashboard/?wpcpm_view=talks';
ck( 'Piotr\'s email: his own three, oldest first, then the school of nobody\'s area under its heading, each line built in his language; a lead\'s link is its page', $piotr['body'], $want );
ck( 'and the lead\'s link is the lead\'s page on the Talks view, a school\'s its Talks card', array( url_of( 'lead-5' ), has( url_of( 'recEXA00000000002' ), 'institution-dashboard' ) ), array( 'https://example.test/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=lead-5', true ) );
ck( 'its subject counts the four lines it holds', $piotr['subject'], '[Education Directory] Follow-ups due this week: 4' );
ck( 'Confirmed, Student, Not Moving Forward, SPAM, an unknown stage, a later follow-up and a closed lead are never listed', array_values( array_filter( array( 'Confirmed Example', 'Student Example', 'Not Moving Example', 'Spam Example', 'Rejected Example', 'Later Example', 'Closed Lead' ), static function ( $n ) { return has( implode( "\n", array_map( static function ( $m ) { return $m['body']; }, $GLOBALS['mails'] ) ), $n ); } ) ), array() );

$ana = mails_to( 7 )[0];
ck( 'Ana\'s email: her own school at Revisit Later, then the two not assigned schools of her Mine, oldest first', array( $ana['subject'], $ana['body'] ), array(
	'[Education Directory] Follow-ups due this week: 3',
	"These follow-ups are due, oldest first:\r\n«7»Revisit Example School: Write in spring, due 18 November 2026: " . url_of( 'recEXA00000000009' ) . "\r\n\r\nNot assigned to anybody:\r\n«7»架空山学園大学: Call the dean, due 15 November 2026: " . url_of( 'recEXA00000000011' ) . "\r\n«7»جامعة الواحة الخضراء المتخيلة, due 17 November 2026: " . url_of( 'recEXA00000000010' ) . "\r\n\r\nOpen the Talks view: https://example.test/administrator-dashboard/?wpcpm_view=talks",
) );

$mei = mails_to( 12 )[0];
ck( 'Mei, with nothing of her own: no first part and no heading over it, only the not assigned ones, the former Administrator\'s school among them', array( $mei['subject'], $mei['body'] ), array(
	'[Education Directory] Follow-ups due this week: 2',
	"Not assigned to anybody:\r\n«12»架空山学園大学: Call the dean, due 15 November 2026: " . url_of( 'recEXA00000000011' ) . "\r\n«12»Former Example School, due 16 November 2026: " . url_of( 'recEXA00000000012' ) . "\r\n\r\nOpen the Talks view: https://example.test/administrator-dashboard/?wpcpm_view=talks",
) );
ck( 'no email says "None of the schools assigned to you is due."', array_values( array_filter( $GLOBALS['mails'], static function ( $m ) { return has( $m['body'], 'None of the schools' ); } ) ), array() );
ck( 'each email holds exactly the lines its reader\'s menu count counts (one function, due_for())', array_map( static function ( $m ) { return array( (int) $m['to']->ID, substr_count( $m['body'], ', due ' ), WPCPM_Institution_Talks_View::due_count( (int) $m['to']->ID ) ); }, $GLOBALS['mails'] ), array( array( 7, 3, 3 ), array( 11, 4, 4 ), array( 12, 2, 2 ) ) );

ck( 'a second run that Monday sends nothing', array( WPCPM_Institution_Talks_Weekly::run( MONDAY + HOUR_IN_SECONDS ), count( $GLOBALS['mails'] ) ), array( 0, 3 ) );
ck( 'nor does a Sunday of the same ISO week', WPCPM_Institution_Talks_Weekly::run( MONDAY + 6 * DAY_IN_SECONDS ), 0 );
ck( 'the next Monday sends again', array( WPCPM_Institution_Talks_Weekly::run( MONDAY + 7 * DAY_IN_SECONDS ), get_option( 'wpcpm_institution_talks_week' ) ), array( 3, '2026-49' ) );

// The Program details are the Administrators' working notes on the card alone (19 Nov 2026): never in an email's lines.
world();
foreach ( array_keys( $GLOBALS['answers'] ) as $id ) { $GLOBALS['answers'][ $id ]['program_details'] = "PROGRAMDETAILS-SECRET hours\nreports"; }
WPCPM_Institution_Talks_View::forget();
WPCPM_Institution_Talks_Weekly::run( MONDAY );
ck( 'Program details: the Monday email\'s lines and due_for() hold nothing of it', array( count( $GLOBALS['mails'] ) > 0, false !== strpos( (string) json_encode( $GLOBALS['mails'] ), 'PROGRAMDETAILS-SECRET' ), false !== strpos( (string) json_encode( array( WPCPM_Institution_Talks_View::due_for( 7, '2026-11-23' ), WPCPM_Institution_Talks_View::due_for( 11, '2026-11-23' ) ) ), 'PROGRAMDETAILS-SECRET' ) ), array( true, false, false ) );

echo "\n=== Who has nothing ===\n";
world();
unset( $GLOBALS['rows']['recEXA00000000011'], $GLOBALS['answers']['recEXA00000000011'], $GLOBALS['rows']['recEXA00000000012'], $GLOBALS['answers']['recEXA00000000012'] );
WPCPM_Institution_Talks_Weekly::run( MONDAY );
ck( 'with no school of nobody\'s area due, Mei, with nothing due in her Mine, gets nothing', array( mails_to( 12 ), WPCPM_Institution_Talks_View::due_count( 12 ) ), array( array(), 0 ) );
ck( 'and Ana and Piotr still get theirs', array_map( static function ( $m ) { return array( $m['to']->ID, $m['subject'] ); }, $GLOBALS['mails'] ), array( array( 7, '[Education Directory] Follow-ups due this week: 2' ), array( 11, '[Education Directory] Follow-ups due this week: 3' ) ) );
ck( 'and Piotr\'s has no "Not assigned to anybody:" part', has( mails_to( 11 )[0]['body'], 'Not assigned to anybody' ), false );

world();
$GLOBALS['areas'] = array();
$GLOBALS['rows']  = array();
$GLOBALS['leads'] = array();
ck( 'with no area and no school, nobody is a recipient and nothing goes', array( WPCPM_Institution_Talks_Weekly::recipients(), WPCPM_Institution_Talks_Weekly::run( MONDAY ) ), array( array(), 0 ) );
world();
ck( 'the recipients are the managers with an area or an assigned school', array_map( static function ( $u ) { return $u->ID; }, WPCPM_Institution_Talks_Weekly::recipients() ), array( 7, 11, 12 ) );

/* ---- the template ----------------------------------------------------------- */

echo "\n=== What a school's words cannot do ===\n";
world();
$GLOBALS['rows']    = array();
$GLOBALS['answers'] = array();
$GLOBALS['leads']   = array();
school( 'recEXA00000000001', "Szkoła\n<b>Pogrubiona</b>\t{talks_link} {count}", 'Info Sent', PL, 7, '2026-11-10', "Call\r\nthe <i>dean</i>\t{follow_ups}" );
WPCPM_Institution_Talks_Weekly::run( MONDAY );
$odd = mails_to( 7 )[0]['body'] ?? '';
ck( 'words_stay_in_line: a name and a next step holding line breaks, a tab, HTML and placeholders stay on their own line, nothing expanded and no line added', $odd, "These follow-ups are due, oldest first:\r\n«7»Szkoła <b>Pogrubiona</b> {talks_link} {count}: Call the <i>dean</i> {follow_ups}, due 10 November 2026: " . url_of( 'recEXA00000000001' ) . "\r\n\r\nOpen the Talks view: https://example.test/administrator-dashboard/?wpcpm_view=talks" );

echo "\n=== An edit that drops both lists ===\n";
$placeholders = WPCPM_Mail_Templates::get( 'institution-follow-ups-weekly' )['placeholders'];
ck( 'edit_needs_a_list: an edit whose body holds neither list is refused', WPCPM_Mail_Templates::refusal( $placeholders, '[{site_name}] Follow-ups: {count}', "Open the Talks view: {talks_link}" ), 'Nothing was saved: this email needs {follow_ups} or {unassigned_follow_ups}.' );
ck( 'edit_needs_a_list: one holding either list is not', array( WPCPM_Mail_Templates::refusal( $placeholders, 'Follow-ups', "{follow_ups}\n\n{talks_link}" ), WPCPM_Mail_Templates::refusal( $placeholders, 'Follow-ups', "{unassigned_follow_ups}\n\n{talks_link}" ) ), array( '', '' ) );
// An edit stored with neither list (saved before the rule, or written around the screen) is set aside: the run
// sends the shipped body.
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ] = array(
	'templates' => array(
		'institution-follow-ups-weekly' => array( 'subject' => 'Due: {count}', 'body' => "Open the Talks view: {talks_link}", 'by' => 7, 'at' => 1795000000, 'shipped' => WPCPM_Mail_Templates::shipped_fingerprint( 'institution-follow-ups-weekly' ) ),
	),
);
world();
WPCPM_Institution_Talks_Weekly::run( MONDAY );
ck( 'stored_edit_without_lists_set_aside: the run sends the shipped subject and body, the lines in them', array( mails_to( 11 )[0]['subject'] ?? '', has( mails_to( 11 )[0]['body'] ?? '', 'These follow-ups are due, oldest first:' ), WPCPM_Mail_Templates::state( 'institution-follow-ups-weekly' )['reason'] ), array( '[Education Directory] Follow-ups due this week: 4', true, 'Edit set aside: it lacks {follow_ups} or {unassigned_follow_ups}, which this email needs.' ) );
unset( $GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ] );
// Round 2, point 4: an email whose one_of holds a single name is refused in the one-name sentence, never " or {x}".
$single = $placeholders;
$single['unassigned_follow_ups']['one_of'] = false;
ck( 'one_of_single_name: a single needed name is said as one required placeholder', WPCPM_Mail_Templates::refusal( $single, '[{site_name}] Follow-ups: {count}', "Open the Talks view: {talks_link}" ), 'Nothing was saved: this email needs {follow_ups}.' );
$templates_src = (string) file_get_contents( __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php' );
ck( 'G1: the refusal and the set-aside reason are one translatable sentence each, with two placeholders, and no "or" translated alone', array(
	false !== strpos( $templates_src, "__( 'Nothing was saved: this email needs %1\$s or %2\$s.', 'wpcredits-program-manager' )" ),
	false !== strpos( $templates_src, "__( 'Edit set aside: it lacks %1\$s or %2\$s, which this email needs.', 'wpcredits-program-manager' )" ),
	false !== strpos( $templates_src, "__( 'or', 'wpcredits-program-manager' )" ),
), array( true, true, false ) );

echo "\n=== The template ===\n";
$catalog = WPCPM_Mail_Catalog::emails();
$entry   = WPCPM_Mail_Templates::get( 'institution-follow-ups-weekly' );
ck( 'the catalog names it, for the Administrator, in the Institutions area', array( $catalog['institution-follow-ups-weekly']['label'] ?? null, $catalog['institution-follow-ups-weekly']['audience'] ?? null, $catalog['institution-follow-ups-weekly']['module'] ?? null ), array( 'Institution follow-ups due this week, to the Administrator', 'administrator', 'institutions' ) );
ck( 'its subject, body and line ending', array( $entry['subject'], $entry['body'], $entry['eol'] ), array( '[{site_name}] Follow-ups due this week: {count}', "These follow-ups are due, oldest first:\n{follow_ups}\n\nNot assigned to anybody:\n{unassigned_follow_ups}\n\nOpen the Talks view: {talks_link}", "\r\n" ) );
ck( 'its placeholders, the Talks view\'s link required, the two lists in the body alone', array( array_keys( $entry['placeholders'] ), WPCPM_Mail_Templates::required( $entry['placeholders'] ), WPCPM_Mail_Templates::body_only( $entry['placeholders'] ) ), array( array( 'site_name', 'count', 'follow_ups', 'unassigned_follow_ups', 'talks_link' ), array( 'talks_link' ), array( 'follow_ups', 'unassigned_follow_ups' ) ) );
$example = WPCPM_Mail_Templates::render( 'institution-follow-ups-weekly', WPCPM_Mail_Templates::example_values( 'institution-follow-ups-weekly' ) );
ck( 'its example holds both parts and the link', array( has( $example['body'], "These follow-ups are due, oldest first:\r\nExample University: Send the agreement again, due " ), has( $example['body'], "\r\n\r\nNot assigned to anybody:\r\nExample Institute: Call the dean, due " ), has( $example['body'], 'Open the Talks view: https://example.test/administrator-dashboard/?wpcpm_view=talks' ) ), array( true, true, true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
