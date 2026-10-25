<?php
/**
 * The template registry: what each email says, and how a text is filled.
 *
 * - **The catalog stays the one list of names.** Every template's id is a catalog id, its name, area
 *   and audience are the catalog's; the Invitations are the four invitations and the institution's
 *   second one, for an institution whose Collaboration Agreement is on file, and the Mentor calls, the
 *   Institutions, the Semester reports and the Sponsors follow, each email that says different words
 *   a template of its own. Each area's file is read in a scope of its own and leans on no other's.
 * - **Every template is whole.** Each placeholder its text uses is one it declares, each required one
 *   is in the body, none that goes in the body alone is in the subject, every declared one says what
 *   it means and has an example, and filled with the examples no placeholder is left. The
 *   set-password link is required in every invitation, in its body; an email about a call needs its
 *   time, and a meeting link, and what a student or a mentor typed, go in the body alone; a link
 *   that carries a secret is needed, in the body alone, and so is what an Administrator or a sponsor
 *   wrote. The examples read as a real email to the Administrator looking would: a call tomorrow at
 *   3 pm on their own clock, an agreement decided today, invented names, links of the site's or of
 *   example.test, and fake secrets; and no area's file works a date out by adding seconds.
 * - **Built once a request for each language**, and an edit whose subject fills to nothing, as
 *   `sanitize_text_field()` cleans it, is sent with the shipped subject, since the mail layer sends
 *   no email without one.
 * - **Filling.** A value is never read as a placeholder; an optional sentence with nothing to say
 *   goes, and so does a line left with nothing, the colon line above it and the blank line it leaves
 *   doubled; only a full stop followed by a space ends a sentence; a required placeholder's line
 *   never goes; a subject is never shortened, and is kept to one line.
 * - **The footer's two links** (1.122.37). `{site_link}` is the site's home page, the same for
 *   everybody, left out where the body names it standing whole, never where it names a page under
 *   it; and what `{dashboard_link}` becomes for each kind of person, the page of their kind while it
 *   is published, which the footer's test says, since it goes to an Administrator.
 * - **The site's address in every email** (1.123.24). The shipped footer prints `{site_link}` on
 *   the line under the signature's first, the owner having approved it on 22 October 2026: left out,
 *   with no empty line, where the body names the home page whole, and printed once where a
 *   recipient's dashboard is the home page itself.
 *
 * Run from the plugin root:  php bin/test-mail-templates.php
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

$GLOBALS['opts']     = array(
	'date_format' => 'F j, Y',
	'time_format' => 'g:i a',
);
$GLOBALS['autoload'] = array();
$GLOBALS['locales']  = array();

// Each string asked for is counted, so a check can tell whether the registry was built again.
function __( $s, $d = null ) { $GLOBALS['translated'] = ( $GLOBALS['translated'] ?? 0 ) + 1; return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
// The site's public home page, which `{site_link}` names: every dashboard's address starts with it.
function home_url( $p = '' ) { return 'https://example.test' . $p; }
// Core's two forms, `add_query_arg( $args, $url )`, as the example set-password link is built over the
// login page, and `add_query_arg( $key, $value, $url )`, as a report's address is.
function add_query_arg( ...$args ) {
	$pairs = is_array( $args[0] ) ? $args[0] : array( $args[0] => $args[1] );
	$url   = (string) ( is_array( $args[0] ) ? $args[1] : $args[2] );

	foreach ( $pairs as $k => $v ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
	}

	return $url;
}
function get_bloginfo( $k = 'name' ) { $GLOBALS['site_name_read'] = ( $GLOBALS['site_name_read'] ?? 0 ) + 1; return 'WordPress Credits'; }
function is_email( $s ) { return false !== filter_var( (string) $s, FILTER_VALIDATE_EMAIL ) ? $s : false; }
// Nobody here may manage the program, so each account is the kind its role says.
function user_can( $user, $cap ) { return false; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function current_user_can( $cap ) { return false; }
// The language the fingerprint of a shipped text is built in, recorded: the site's, or the one a
// check sets.
function get_locale() { return $GLOBALS['locale'] ?? 'en_US'; }
// Who is looking at an example, and the clock their account keeps: a call's examples are on it.
$GLOBALS['uid']   = 0;
$GLOBALS['umeta'] = array();
function get_current_user_id() { return $GLOBALS['uid']; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
// The site's clock: Warsaw's, unless a check sets another.
function wp_timezone_string() { return $GLOBALS['site_zone'] ?? 'Europe/Warsaw'; }
function wp_timezone() { return new DateTimeZone( wp_timezone_string() ); }
function wp_date( $format, $timestamp = null, $zone = null ) { return ( new DateTimeImmutable( '@' . (int) ( null === $timestamp ? time() : $timestamp ) ) )->setTimezone( $zone instanceof DateTimeZone ? $zone : wp_timezone() )->format( $format ); }
function human_time_diff( $from, $to = 0 ) { return round( abs( (int) $to - (int) $from ) / HOUR_IN_SECONDS ) . ' hours'; }
function switch_to_locale( $locale ) { $GLOBALS['locales'][] = $locale; return true; }
function restore_previous_locale() { $GLOBALS['locales'][] = 'restored'; return true; }
function add_action() {}
function add_filter() {}
// What the `wpcpm_mail` filter is handed, kept, so a check can read whether a subject went as plain text.
$GLOBALS['filtered'] = array();
function apply_filters( $hook, $value ) {
	if ( 'wpcpm_mail' === $hook ) {
		$GLOBALS['filtered'][] = $value;
	}

	return $value;
}
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
// What the mail layer hands WordPress, with the id and the test mark the mail log's capture takes.
$GLOBALS['mail'] = array();
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['mail'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body, 'headers' => $headers, 'context' => WPCPM_Mail::take_context(), 'test' => WPCPM_Mail::take_test() );
	return true;
}
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';

// The site's date format, which an invitation's example day is printed in.
require_once __DIR__ . '/../includes/class-wpcpm-dates.php';
require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
// Which kind of person an account is, which the footer's dashboard line reads.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-capture.php';
// What the examples of the Mentor calls and the Semester reports are worked out with, as their emails
// are: a call's times on a clock, and a semester and its report's address.
require_once __DIR__ . '/../includes/class-wpcpm-cohort.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-availability.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-semester-report-screen.php';
// And the Institutions' and the Sponsors' examples: an application's confirmation link, an
// invitation's link and how long it works, a sponsor's dated line, the sponsor an Offers link names,
// and the scan's notes in words.
require_once __DIR__ . '/../includes/class-wpcpm-pdf-check.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-application.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-invite.php';
// And a school's details' (Institutions release 2): the lines that tell what changed.
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-details.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
// And the Mentor applications' (1.122.31): the confirming link's handler.
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-application.php';

/**
 * The settings the footer and the emails' examples read, as a check sets them: Contact address in
 * emails, the mentors' course link, and the course's title, which stays unset (so an email's own
 * fallback shows) until a check gives it a value of its own.
 */
class WPCPM_Settings {
	public static $contact      = '';
	public static $course       = '';
	public static $course_title = null;
	public static function get_value( $key, $fallback = null ) {
		$set = array(
			'mail_contact'         => self::$contact,
			'mentor_course_link'   => self::$course,
			'checker_course_title' => self::$course_title,
		);
		return isset( $set[ $key ] ) ? $set[ $key ] : $fallback;
	}
}

/** The dashboards a template's example links, each published at an address of its own. */
class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student-report-card/'; }
}
/** As above. */
class WPCPM_Mentors_Dashboard {
	public static function page_url() { return 'https://example.test/mentor-report-card/'; }
}
/** As above. */
class WPCPM_Institutions_Dashboard {
	public static function page_url() { return 'https://example.test/institution-dashboard/'; }
}
/** As above, its page unpublished in a check that says so. */
class WPCPM_Sponsors_Dashboard {
	public static $url = 'https://example.test/sponsor-dashboard/';
	public static function page_url() { return self::$url; }
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

echo "=== The registry ===\n";

$all = WPCPM_Mail_Templates::all();

ck(
	'the registry holds the Invitations, the four invitations and the institution\'s second one, then the Mentor calls, the Institutions, the Semester reports and the Sponsors, the Sponsor events and the Mentor applications, in that order',
	array_keys( $all ),
	array(
		'invite-student',
		'invite-mentor',
		'invite-institution',
		'invite-institution-settled',
		'invite-sponsor',
		'call-booked-mentor',
		'call-booked-mentor-no-link',
		'call-booked-student',
		'session-joined-mentor',
		'session-joined-mentor-no-link',
		'session-joined-student',
		'series-joined-mentor',
		'series-joined-mentor-no-link',
		'series-joined-student',
		'session-moved-student',
		'session-moved-mentor',
		'session-left',
		'session-released',
		'call-cancelled-mentor',
		'call-cancelled-student',
		'session-cancelled',
		'call-reminder-mentor',
		'call-reminder-student',
		'session-reminder',
		'institution-applied',
		'institution-applied-no-call',
		'institution-application',
		'institution-information',
		'institution-declined',
		'institution-invite',
		'institution-invite-no-sender',
		'agreement-received',
		'agreement-landed',
		'agreement-landed-flagged',
		'agreement-accepted',
		'agreement-reinstated',
		'agreement-returned',
		'agreement-revoked',
		'agreement-revoked-no-contact',
		'agreement-reminder',
		'agreement-template',
		'member-last',
		'institution-details-saved',
		'institution-details-request',
		'institution-details-approved',
		'institution-details-declined',
		'institution-details-withdrawn',
		'report-drafted',
		'report-approved',
		'report-consent',
		'sponsor-applied',
		'sponsor-applied-no-logo',
		'sponsor-application',
		'sponsor-information',
		'sponsor-declined',
		'sponsor-agreement-received',
		'sponsor-agreement-accepted',
		'sponsor-agreement-returned',
		'sponsor-agreement-revoked',
		'sponsor-agreement-reinstated',
		'sponsor-interest',
		'sponsor-mentor-interest',
		'offer-low-stock',
		'claim-problem',
		'sponsor-post-returned',
		'sponsor-offer-returned',
		'sponsor-term-ending',
		'sponsor-term-ended',
		'event-joined',
		'event-joined-program',
		'event-left',
		'event-left-program',
		'event-changed',
		'event-changed-program',
		'event-canceled',
		'event-canceled-program',
		'event-reminder',
		'event-reminder-program',
		'event-reminder-sponsor',
		'event-approved',
		'event-returned',
		'event-returned-no-events',
		'event-canceled-with-note',
		'event-changed-sponsor',
		'event-changed-sponsor-for',
		'event-canceled-sponsor',
		'event-canceled-sponsor-for',
		'event-planned',
		'event-planned-for',
		'event-changed-waiting',
		'event-changed-waiting-for',
		'event-changed-approved',
		'event-changed-approved-for',
		'event-canceled-by-sponsor',
		'event-canceled-for',
		'mentor-application-received',
		'mentor-selected',
		'mentor-more-information',
		'mentor-declined',
		'mentor-application-notice',
	)
);

$named = array();
foreach ( $all as $id => $entry ) {
	$catalog      = WPCPM_Mail_Catalog::get( $id );
	$named[ $id ] = null !== $catalog && $catalog['label'] === $entry['label'] && $catalog['module'] === $entry['module'] && $catalog['audience'] === $entry['audience'];
}
ck( 'every template is a catalog id, and its name, area and audience are the catalog\'s', $named, array_fill_keys( array_keys( $all ), true ) );

// Each area's file read alone, in a scope of its own, as the registry reads it: a file that leaned on a
// variable another file set would find it unset here, and say so.
$areas    = array();
$warnings = array();
set_error_handler(
	function ( $number, $message, $file, $line ) use ( &$warnings ) {
		$warnings[] = basename( $file ) . ':' . $line . ': ' . $message;
		return true;
	}
);
foreach ( WPCPM_Mail_Templates::AREAS as $module => $file ) {
	$defined = ( static function ( $path ) {
		return require $path;
	} )( __DIR__ . '/../includes/mail/templates/' . $file . '.php' );
	foreach ( array_keys( (array) $defined ) as $id ) {
		$areas[ $id ] = array( $module, WPCPM_Mail_Catalog::module_of( $id ) );
	}
}
restore_error_handler();
ck( 'each area\'s file defines that area\'s emails, and every id it defines is the catalog\'s', array_values( array_filter( $areas, function ( $pair ) { return $pair[0] !== $pair[1]; } ) ), array() );
ck( 'and each, read alone, leans on nothing another file set: no warning', $warnings, array() );
// The registry read by its tokens, so a comment or a string that names a require counts for nothing,
// and the way the code is spaced or wrapped does not matter: the class includes a file in one place
// alone, area(), whose body is that one statement, and all() reads each area through it.
$registry = array_values(
	array_filter(
		token_get_all( (string) file_get_contents( __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php' ) ),
		function ( $token ) {
			return ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
		}
	)
);
$spelled = function ( $token ) {
	return is_array( $token ) ? $token[1] : $token;
};
// A method's body, its tokens one space apart: what lies between the brace after its name and the
// brace that closes it. Null for a method the class does not have.
$body_of = function ( $method ) use ( $registry, $spelled ) {
	foreach ( $registry as $at => $token ) {
		if ( ! is_array( $token ) || T_FUNCTION !== $token[0] || ! isset( $registry[ $at + 1 ] ) || $method !== $spelled( $registry[ $at + 1 ] ) ) {
			continue;
		}

		$depth = 0;
		$body  = array();

		for ( $i = $at + 2, $n = count( $registry ); $i < $n; $i++ ) {
			$opens = '{' === $registry[ $i ] || ( is_array( $registry[ $i ] ) && in_array( $registry[ $i ][0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) );

			if ( '}' === $registry[ $i ] && 1 === $depth ) {
				return implode( ' ', $body );
			}

			if ( $depth > 0 ) {
				$body[] = $spelled( $registry[ $i ] );
			}

			$depth += $opens ? 1 : ( '}' === $registry[ $i ] ? -1 : 0 );
		}
	}

	return null;
};
$included = array();
foreach ( $registry as $token ) {
	if ( is_array( $token ) && in_array( $token[0], array( T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE ), true ) ) {
		$included[] = $token[1];
	}
}
ck(
	'and the registry reads each in a scope of its own: read by its tokens, it includes a file once, with a require, in area(), whose body is that one statement, and all() reads each area through it',
	array( $included, $body_of( 'area' ), false !== strpos( (string) $body_of( 'all' ), '$defined = self :: area ( $file ) ;' ) ),
	array( array( 'require' ), 'return require __DIR__ . \'/templates/\' . $file . \'.php\' ;', true )
);
// No example day is worked out by adding seconds: a day is not always 86,400 of them, and a date so
// built is an hour off across a change of summer time (1.122.21's fix). Each file moves along the
// clock, with modify().
$by_seconds = array();
foreach ( glob( __DIR__ . '/../includes/mail/templates/*.php' ) as $path ) {
	if ( preg_match( '/\b(?:DAY|WEEK)_IN_SECONDS\b/', (string) file_get_contents( $path ) ) ) {
		$by_seconds[] = basename( $path );
	}
}
ck( 'no area\'s file builds a date by adding seconds', $by_seconds, array() );
ck( 'and the registry names no email itself: no label, area or audience in a definition', array_values( array_filter( array_map( function ( $file ) { return preg_match( "/'(label|module|audience)'\s*=>/", (string) file_get_contents( __DIR__ . '/../includes/mail/templates/' . $file . '.php' ) ) ? $file : null; }, WPCPM_Mail_Templates::AREAS ) ) ), array() );
ck( 'the institution invitation for an agreement on file is the catalog\'s, in the Invitations area, to institution members', WPCPM_Mail_Catalog::get( 'invite-institution-settled' ), array( 'label' => 'Invitation: institution, agreement on file', 'module' => 'invitations', 'audience' => 'institution', 'test' => false ) );
ck( 'an id the registry does not hold is null', array( WPCPM_Mail_Templates::get( 'call-booked' ), WPCPM_Mail_Templates::render( 'no-such', array() ) ), array( null, null ) );

$whole = array();
foreach ( $all as $id => $entry ) {
	$used     = WPCPM_Mail_Templates::placeholders_in( $entry['subject'] . "\n" . $entry['body'] );
	$declared = array_keys( $entry['placeholders'] );
	$about    = array();

	foreach ( $entry['placeholders'] as $name => $placeholder ) {
		$about[] = '' !== (string) $placeholder['means'] && '.' === substr( $placeholder['means'], -1 ) && ( is_string( $placeholder['example'] ) || $placeholder['example'] instanceof Closure ) && is_bool( $placeholder['required'] ) && ( ! isset( $placeholder['body_only'] ) || is_bool( $placeholder['body_only'] ) );
	}

	$filled = WPCPM_Mail_Templates::render( $id, WPCPM_Mail_Templates::example_values( $id ) );

	$whole[ $id ] = array(
		'trigger'    => '' !== $entry['trigger'] && '.' === substr( $entry['trigger'], -1 ),
		'undeclared' => array_values( array_diff( $used, $declared ) ),
		'unused'     => array_values( array_diff( $declared, $used ) ),
		'missing'    => array_values( array_diff( WPCPM_Mail_Templates::required( $entry['placeholders'] ), WPCPM_Mail_Templates::placeholders_in( $entry['body'] ) ) ),
		'in_subject' => array_values( array_intersect( WPCPM_Mail_Templates::body_only( $entry['placeholders'] ), WPCPM_Mail_Templates::placeholders_in( $entry['subject'] ) ) ),
		'about'      => ! in_array( false, $about, true ),
		'left'       => WPCPM_Mail_Templates::placeholders_in( $filled['subject'] . $filled['body'] ),
		'eol'        => $entry['eol'],
		'link'       => array( WPCPM_Mail_Templates::required( $entry['placeholders'] ), WPCPM_Mail_Templates::body_only( $entry['placeholders'] ) ),
	);
}
// Each template's line ending, the placeholders it needs, and the ones that go in its body alone: the
// invitations, the two moved emails and most of the Institutions' and the Sponsors' keep the line
// ending their code wrote, "\r\n"; every email about a call needs its time; the mentor's
// confirmations sent only while the mentor has a meeting link need it, and so does each email sent
// only with a value its other words lack (a call to book, the scan's notes, a country contact); every
// link that carries a secret is needed, in the body alone; what an Administrator or a sponsor wrote
// is needed where it is the email's point, and goes in the body alone; a post's title and its
// author's name are needed, since either may be empty and its line must stay; and a meeting link, a
// code's last characters and what somebody typed, an applicant's own answers among them, go in the
// body wherever they are.
$shape = array(
	'invite-student'                => array( "\r\n", array( 'set_password_link' ), array( 'set_password_link' ) ),
	'invite-mentor'                 => array( "\r\n", array( 'set_password_link' ), array( 'set_password_link' ) ),
	'invite-institution'            => array( "\r\n", array( 'set_password_link' ), array( 'set_password_link' ) ),
	'invite-institution-settled'    => array( "\r\n", array( 'set_password_link' ), array( 'set_password_link' ) ),
	'invite-sponsor'                => array( "\r\n", array( 'set_password_link' ), array( 'set_password_link' ) ),
	'call-booked-mentor'            => array( "\n", array( 'when', 'meeting_place' ), array( 'meeting_place', 'topic' ) ),
	'call-booked-mentor-no-link'    => array( "\n", array( 'when' ), array( 'topic' ) ),
	'call-booked-student'           => array( "\n", array( 'when' ), array( 'meeting_place', 'topic' ) ),
	'session-joined-mentor'         => array( "\n", array( 'when', 'meeting_place' ), array( 'meeting_place', 'session_topic' ) ),
	'session-joined-mentor-no-link' => array( "\n", array( 'when' ), array( 'session_topic' ) ),
	'session-joined-student'        => array( "\n", array( 'when' ), array( 'meeting_place', 'session_topic' ) ),
	'series-joined-mentor'          => array( "\n", array( 'session_dates', 'meeting_place' ), array( 'meeting_place', 'session_topic' ) ),
	'series-joined-mentor-no-link'  => array( "\n", array( 'session_dates' ), array( 'session_topic' ) ),
	'series-joined-student'         => array( "\n", array( 'session_dates' ), array( 'meeting_place', 'session_topic' ) ),
	'session-moved-student'         => array( "\r\n", array( 'when' ), array( 'session_topic' ) ),
	'session-moved-mentor'          => array( "\r\n", array( 'when' ), array( 'session_topic' ) ),
	'session-left'                  => array( "\n", array( 'when' ), array( 'session_topic' ) ),
	'session-released'              => array( "\n", array( 'when' ), array( 'session_topic' ) ),
	'call-cancelled-mentor'         => array( "\n", array( 'when' ), array() ),
	'call-cancelled-student'        => array( "\n", array( 'when' ), array() ),
	'session-cancelled'             => array( "\n", array( 'when' ), array() ),
	'call-reminder-mentor'          => array( "\n", array( 'when' ), array( 'meeting_place', 'topic' ) ),
	'call-reminder-student'         => array( "\n", array( 'when' ), array( 'meeting_place', 'topic' ) ),
	'session-reminder'              => array( "\n", array( 'when' ), array( 'meeting_place', 'session_topic' ) ),
	'institution-applied'           => array( "\r\n", array( 'verify_link', 'booking_link' ), array( 'verify_link', 'applicant_email' ) ),
	'institution-applied-no-call'   => array( "\r\n", array( 'verify_link' ), array( 'verify_link', 'applicant_email' ) ),
	'institution-application'       => array( "\r\n", array(), array( 'city', 'contact_name', 'contact_email' ) ),
	'institution-information'       => array( "\r\n", array( 'question' ), array( 'question' ) ),
	'institution-declined'          => array( "\r\n", array(), array() ),
	'institution-invite'            => array( "\r\n", array( 'accept_link' ), array( 'accept_link' ) ),
	'institution-invite-no-sender'  => array( "\r\n", array( 'accept_link' ), array( 'accept_link' ) ),
	'agreement-received'            => array( "\r\n", array(), array() ),
	'agreement-landed'              => array( "\r\n", array(), array() ),
	'agreement-landed-flagged'      => array( "\r\n", array( 'scan_flags' ), array() ),
	'agreement-accepted'            => array( "\r\n", array(), array() ),
	'agreement-reinstated'          => array( "\r\n", array(), array() ),
	'agreement-returned'            => array( "\r\n", array( 'note' ), array( 'note' ) ),
	'agreement-revoked'             => array( "\r\n", array( 'note', 'country_contact' ), array( 'note' ) ),
	'agreement-revoked-no-contact'  => array( "\r\n", array( 'note' ), array( 'note' ) ),
	'agreement-reminder'            => array( "\r\n", array(), array() ),
	'agreement-template'            => array( "\r\n", array(), array( 'name_asked_for' ) ),
	'member-last'                   => array( "\r\n", array(), array() ),
	// A school's details (Institutions release 2): what changed is needed, in the body alone, in the
	// three that tell it; the Administrator's note is, in the one that carries it; the link to the
	// request is not, since an Administrator's dashboard may have no page to link.
	'institution-details-saved'     => array( "\r\n", array( 'changes' ), array( 'changes' ) ),
	'institution-details-request'   => array( "\r\n", array( 'changes' ), array( 'changes' ) ),
	'institution-details-approved'  => array( "\r\n", array( 'changes' ), array( 'changes' ) ),
	'institution-details-declined'  => array( "\r\n", array( 'note' ), array( 'note' ) ),
	'institution-details-withdrawn' => array( "\r\n", array(), array() ),
	'report-drafted'                => array( "\n", array( 'semester' ), array() ),
	'report-approved'               => array( "\n", array( 'semester' ), array() ),
	'report-consent'                => array( "\n", array( 'semester' ), array() ),
	'sponsor-applied'               => array( "\r\n", array(), array( 'applicant_email' ) ),
	'sponsor-applied-no-logo'       => array( "\r\n", array(), array( 'applicant_email' ) ),
	'sponsor-application'           => array( "\r\n", array(), array( 'website', 'contact_name', 'contact_email' ) ),
	'sponsor-information'           => array( "\r\n", array( 'question' ), array( 'question' ) ),
	'sponsor-declined'              => array( "\r\n", array(), array() ),
	'sponsor-agreement-received'    => array( "\r\n", array(), array() ),
	'sponsor-agreement-accepted'    => array( "\r\n", array(), array() ),
	'sponsor-agreement-returned'    => array( "\r\n", array( 'note' ), array( 'note' ) ),
	'sponsor-agreement-revoked'     => array( "\r\n", array( 'note' ), array( 'note' ) ),
	'sponsor-agreement-reinstated'  => array( "\r\n", array(), array() ),
	'sponsor-interest'              => array( "\n", array( 'interest' ), array( 'interest' ) ),
	'sponsor-mentor-interest'       => array( "\n", array(), array() ),
	'offer-low-stock'               => array( "\n", array(), array() ),
	'claim-problem'                 => array( "\n", array(), array( 'code_ending' ) ),
	'sponsor-post-returned'         => array( "\n", array( 'post_title', 'author_name', 'note' ), array( 'note' ) ),
	'sponsor-offer-returned'        => array( "\n", array( 'offer_title', 'member_name', 'note' ), array( 'note' ) ),
	// The Sponsors' term mails, the sponsor's name, the tier and the day needed in both, and the
	// Sponsor events, every one about an event needing its time, the call link and the description in
	// the body alone, and an Administrator's note needed where the email is about it.
	'sponsor-term-ending'           => array( "\n", array( 'company_name', 'tier', 'term_end' ), array() ),
	'sponsor-term-ended'            => array( "\n", array( 'company_name', 'tier', 'term_end' ), array() ),
	'event-joined'                  => array( "\n", array( 'event_title', 'company_name', 'when' ), array( 'call_link' ) ),
	'event-joined-program'          => array( "\n", array( 'event_title', 'when' ), array( 'call_link' ) ),
	'event-left'                    => array( "\n", array( 'company_name' ), array() ),
	'event-left-program'            => array( "\n", array(), array() ),
	'event-changed'                 => array( "\n", array( 'event_title', 'company_name', 'when' ), array( 'call_link' ) ),
	'event-changed-program'         => array( "\n", array( 'event_title', 'when' ), array( 'call_link' ) ),
	'event-canceled'                => array( "\n", array( 'event_title', 'company_name', 'when' ), array() ),
	'event-canceled-program'        => array( "\n", array( 'event_title', 'when' ), array() ),
	'event-reminder'                => array( "\n", array( 'event_title', 'company_name', 'when' ), array( 'call_link' ) ),
	'event-reminder-program'        => array( "\n", array( 'event_title', 'when' ), array( 'call_link' ) ),
	'event-reminder-sponsor'        => array( "\n", array( 'event_title', 'when' ), array() ),
	'event-approved'                => array( "\n", array( 'event_title', 'when' ), array( 'call_link' ) ),
	'event-returned'                => array( "\n", array( 'event_title', 'note', 'when' ), array( 'note' ) ),
	'event-returned-no-events'      => array( "\n", array( 'event_title', 'note', 'when' ), array( 'note' ) ),
	'event-canceled-with-note'      => array( "\n", array( 'event_title', 'note', 'when' ), array( 'note' ) ),
	'event-changed-sponsor'         => array( "\n", array( 'event_title', 'when' ), array( 'call_link' ) ),
	'event-changed-sponsor-for'     => array( "\n", array( 'event_title', 'company_name', 'when' ), array( 'call_link' ) ),
	'event-canceled-sponsor'        => array( "\n", array( 'event_title', 'when' ), array() ),
	'event-canceled-sponsor-for'    => array( "\n", array( 'event_title', 'company_name', 'when' ), array() ),
	'event-planned'                 => array( "\n", array( 'company_name', 'event_title', 'when', 'description' ), array( 'call_link', 'description' ) ),
	'event-planned-for'             => array( "\n", array( 'company_name', 'event_title', 'when', 'description' ), array( 'call_link', 'description' ) ),
	'event-changed-waiting'         => array( "\n", array( 'company_name', 'event_title', 'when', 'description' ), array( 'call_link', 'description' ) ),
	'event-changed-waiting-for'     => array( "\n", array( 'company_name', 'event_title', 'when', 'description' ), array( 'call_link', 'description' ) ),
	'event-changed-approved'        => array( "\n", array( 'company_name', 'event_title', 'when', 'description' ), array( 'call_link', 'description' ) ),
	'event-changed-approved-for'    => array( "\n", array( 'company_name', 'event_title', 'when', 'description' ), array( 'call_link', 'description' ) ),
	'event-canceled-by-sponsor'     => array( "\n", array( 'company_name', 'event_title', 'when', 'description' ), array( 'call_link', 'description' ) ),
	'event-canceled-for'            => array( "\n", array( 'company_name', 'event_title', 'when', 'description' ), array( 'call_link', 'description' ) ),
	// The Mentor applications (1.122.31): the confirming link and the course link each needed, in the
	// body alone, and the applicant's name, address and profile in the body alone. The request for
	// more information names nobody and has no placeholder, as its automation had none.
	'mentor-application-received'   => array( "\n", array( 'verify_link' ), array( 'verify_link', 'applicant_email' ) ),
	'mentor-selected'               => array( "\n", array( 'course_link' ), array( 'applicant_name', 'course_link' ) ),
	'mentor-more-information'       => array( "\n", array(), array() ),
	'mentor-declined'               => array( "\n", array(), array( 'applicant_name' ) ),
	'mentor-application-notice'     => array( "\n", array(), array( 'applicant_name', 'profile_link' ) ),
);
$want_whole = array();
foreach ( $shape as $id => $want ) {
	$want_whole[ $id ] = array( 'trigger' => true, 'undeclared' => array(), 'unused' => array(), 'missing' => array(), 'in_subject' => array(), 'about' => true, 'left' => array(), 'eol' => $want[0], 'link' => array( $want[1], $want[2] ) );
}
// The two emails that name the institution's record no longer say it, and still list it: an
// Administrator's saved edit that uses {institution_record} would otherwise be set aside, without
// anyone noticing (`WPCPM_Mail_Templates::state()`). It is the one placeholder declared and not used.
$want_whole['agreement-template']['unused'] = array( 'institution_record' );
$want_whole['member-last']['unused']        = array( 'institution_record' );
ck( 'every template says what sends it, uses only the placeholders it declares and every one it declares, holds its required ones in its body and none that goes in the body in its subject, says what each means with an example, and leaves none unfilled; every invitation needs its set-password link, in its body, every email about a call its time, every link with a secret is needed, in the body alone, and a meeting link and what somebody typed go in the body alone', $whole, $want_whole );

// The Institutions' and the Semester reports' emails (1.123.27): true about what the site does, the
// site called by its name, no Airtable record in front of a school or an Administrator, and a
// subject prefix on every one.
//
// Removing a placeholder from an email's list is a silent break: an Administrator's saved edit that
// uses a placeholder the email no longer has is set aside (`WPCPM_Mail_Templates::state()`). So the
// lists of 1.123.26, below, only grow, and what is added is optional. `institution_record` stays in
// the lists of the two emails that said it and leaves only their shipped text.
$lists_1_123_26 = array(
	'institution-applied'          => 'site_name institution_name reference verify_link*^ booking_link* applicant_email^',
	'institution-applied-no-call'  => 'site_name institution_name reference verify_link*^ applicant_email^',
	'institution-application'      => 'site_name institution_name city^ country contact_name^ contact_email^ queue_link',
	'institution-information'      => 'site_name reference question*^',
	'institution-declined'         => 'site_name',
	'institution-invite'           => 'site_name inviter_name institution_name accept_link*^ expiry_date replies_reach',
	'institution-invite-no-sender' => 'site_name institution_name accept_link*^ expiry_date replies_reach',
	'agreement-received'           => 'site_name uploaded_on uploaded_by review_window',
	'agreement-landed'             => 'site_name institution_name uploaded_on uploaded_by queue_link',
	'agreement-landed-flagged'     => 'site_name institution_name uploaded_on uploaded_by scan_flags* queue_link',
	'agreement-accepted'           => 'site_name accepted_on dashboard_link',
	'agreement-reinstated'         => 'site_name reinstated_on dashboard_link',
	'agreement-returned'           => 'site_name returned_on administrator_name note*^',
	'agreement-revoked'            => 'site_name revoked_on administrator_name note*^ country country_contact*',
	'agreement-revoked-no-contact' => 'site_name revoked_on administrator_name note*^',
	'agreement-reminder'           => 'site_name agreements_waiting agreements_overdue review_days overdue_list queue_link',
	'agreement-template'           => 'site_name reason language institution_record name_asked_for^',
	'member-last'                  => 'site_name member_name institution_name institution_record left_on ended_because',
	'report-drafted'               => 'institution_name semester* students_in_progress review_link',
	'report-approved'              => 'semester* report_link',
	'report-consent'               => 'semester* dashboard_link',
);
$lists_now = array();
foreach ( $all as $id => $entry ) {
	if ( ! in_array( $entry['module'], array( WPCPM_Mail_Catalog::MODULE_INSTITUTIONS, WPCPM_Mail_Catalog::MODULE_REPORTS ), true ) ) {
		continue;
	}

	$lists_now[ $id ] = array_map(
		static function ( $name, $placeholder ) {
			return $name . ( ! empty( $placeholder['required'] ) ? '*' : '' ) . ( ! empty( $placeholder['body_only'] ) ? '^' : '' );
		},
		array_keys( $entry['placeholders'] ),
		$entry['placeholders']
	);
}
$removed = array();
$added   = array();
foreach ( $lists_1_123_26 as $id => $list ) {
	$was = explode( ' ', $list );

	// A token carries its marks, so a placeholder made required, or moved to the body alone, is
	// a token missing here and one added there.
	if ( array() !== array_diff( $was, $lists_now[ $id ] ) ) {
		$removed[ $id ] = array_values( array_diff( $was, $lists_now[ $id ] ) );
	}

	if ( array() !== array_diff( $lists_now[ $id ], $was ) ) {
		$added[ $id ] = array_values( array_diff( $lists_now[ $id ], $was ) );
	}
}
$details_emails = array( 'institution-details-saved', 'institution-details-request', 'institution-details-approved', 'institution-details-declined', 'institution-details-withdrawn' );

ck( 'the Institutions and the Semester reports have the 21 emails they had, and the five about a school\'s details of Institutions release 2 after the Institutions\' own', array_keys( $lists_now ), array_merge( array_slice( array_keys( $lists_1_123_26 ), 0, 18 ), $details_emails, array_slice( array_keys( $lists_1_123_26 ), 18 ) ) );
ck( 'no placeholder is taken out of any of their lists, none is made required and none is moved to the body alone, so no saved edit is set aside', $removed, array() );
ck(
	'and what the lists gain is optional and in the body or the subject: the institution\'s name, in the notice of a template that cannot be used, and the site\'s name in the three Semester reports emails\' subjects',
	$added,
	array(
		'agreement-template' => array( 'institution_name' ),
		'report-drafted'     => array( 'site_name' ),
		'report-approved'    => array( 'site_name' ),
		'report-consent'     => array( 'site_name' ),
	)
);

$asked  = array( 'agreement panel', 'airtable', '{institution_record}' );
$saying = array();
foreach ( $lists_now as $id => $list ) {
	$text = strtolower( $all[ $id ]['subject'] . "\n" . $all[ $id ]['body'] );

	foreach ( $asked as $words ) {
		if ( false !== strpos( $text, $words ) ) {
			$saying[ $id ][] = $words;
		}
	}
}
ck( 'no shipped subject or body of the Institutions or the Semester reports says "agreement panel", "Airtable" or an {institution_record}', $saying, array() );

$paragraphs = static function ( $id ) {
	return explode( "\n\n", str_replace( "\r\n", "\n", rtrim( WPCPM_Mail_Templates::get( $id )['body'], "\r\n" ) ) );
};
$said       = array(
	'institution-applied'          => array(
		'What happens next: an Administrator reads every application by hand and writes back, usually within a few working days. If the program can take your institution, we will set up an account for it on the site, where you generate the program\'s Collaboration Agreement or upload your own, then sign it and upload the signed copy.',
		'This message went to {applicant_email} because that address was given on the application form at the {site_name}. If it was not you, you can ignore it: nothing happens until the address is confirmed.',
	),
	'institution-applied-no-call'  => array(
		'What happens next: an Administrator reads every application by hand and writes back, usually within a few working days. If the program can take your institution, we will set up an account for it on the site, where you generate the program\'s Collaboration Agreement or upload your own, then sign it and upload the signed copy.',
		'This message went to {applicant_email} because that address was given on the application form at the {site_name}. If it was not you, you can ignore it: nothing happens until the address is confirmed.',
	),
	'institution-invite'           => array( '{inviter_name} has invited you to see {institution_name}\'s students in the WordPress Credits Program on the {site_name}.' ),
	'institution-invite-no-sender' => array( 'You have been invited to see {institution_name}\'s students in the WordPress Credits Program on the {site_name}.' ),
	'agreement-revoked'            => array( 'Until an agreement is in force again, your Institution Dashboard shows only the Collaboration Agreement card. Nothing about your students has been deleted.' ),
	'agreement-revoked-no-contact' => array( 'Until an agreement is in force again, your Institution Dashboard shows only the Collaboration Agreement card. Nothing about your students has been deleted.' ),
	'agreement-received'           => array( 'If it was the wrong file, withdraw it from the Collaboration Agreement card on your Institution Dashboard and upload the right one.' ),
	'agreement-returned'           => array( 'Upload the corrected copy from the Collaboration Agreement card on your Institution Dashboard. Replying to this message reaches the Administrator who wrote the note.' ),
	'agreement-template'           => array(
		'The reason given was: {reason} (template language {language}, institution {institution_name}, name asked for "{name_asked_for}").',
		'The template is part of the plugin, so a developer fixes it. A bracketed word other than the institution\'s name is refused on purpose: it would otherwise be printed on a document somebody signs. Nothing can be generated in this language until it is fixed.',
	),
	'member-last'                  => array(
		'{member_name} is no longer a member of {institution_name} as of {left_on} ({ended_because}). No account can act for this institution on the site now.',
		'The institution\'s record, its agreement, its students and its history are untouched. A former member can be re-added in one click from the Institutions screen, or a new account created there.',
	),
);
$missing    = array();
foreach ( $said as $id => $wanted_paragraphs ) {
	foreach ( $wanted_paragraphs as $paragraph ) {
		if ( ! in_array( $paragraph, $paragraphs( $id ), true ) ) {
			$missing[ $id ][] = $paragraph;
		}
	}
}
ck( 'each changed paragraph is the one worded for it, a paragraph of its own, in the acknowledgements, the invitations, the revocation, the received and returned notices, the notice of a template that cannot be used and the last member\'s', $missing, array() );

// The five emails about a school's details (Institutions release 2), word for word: who they go to,
// what sends them, their subject, each paragraph of their body and the placeholders they use, with
// the marks of the lists above (`*` needed, `^` in the body alone).
$details = array(
	'institution-details-saved'     => array(
		'audience' => 'administrator',
		'trigger'  => 'When an institution\'s member saves a change to the institution\'s contact and agreement details, or an Administrator saves any detail: to the people the setting names.',
		'subject'  => '[{site_name}] {institution_name}\'s details were corrected',
		'body'     => array(
			'{actor_name} changed {institution_name}\'s details on the Institution Dashboard:',
			'{changes}',
			'They are saved, and the program\'s records follow. Nothing waits for you.',
		),
		'list'     => 'site_name institution_name actor_name changes*^',
	),
	'institution-details-request'   => array(
		'audience' => 'administrator',
		'trigger'  => 'When an institution asks to correct its name, website, city, country or contact\'s email: to the people the setting names.',
		'subject'  => '[{site_name}] {institution_name} asks to correct its details',
		'body'     => array(
			'{actor_name} asks to correct {institution_name}\'s details:',
			'{changes}',
			'Nothing is changed until an Administrator approves it. Approve or decline it on the Administrator Dashboard: {request_link}',
		),
		'list'     => 'site_name institution_name actor_name changes*^ request_link',
	),
	'institution-details-approved'  => array(
		'audience' => 'institution',
		'trigger'  => 'When an Administrator approves an institution\'s request to correct its details: to the person who asked.',
		'subject'  => '[{site_name}] Your institution\'s details are updated',
		'body'     => array(
			'An Administrator approved the change you asked for, and the program\'s records now read:',
			'{changes}',
		),
		'list'     => 'site_name changes*^',
	),
	'institution-details-declined'  => array(
		'audience' => 'institution',
		'trigger'  => 'When an Administrator declines an institution\'s request to correct its details: to the person who asked.',
		'subject'  => '[{site_name}] Your request to correct your institution\'s details',
		'body'     => array(
			'An Administrator did not make the change you asked for, and wrote:',
			'{note}',
			'The details on record are unchanged. You can ask again from the Institution Dashboard: {dashboard_link}',
		),
		'list'     => 'site_name note*^ dashboard_link',
	),
	'institution-details-withdrawn' => array(
		'audience' => 'administrator',
		'trigger'  => 'When an institution withdraws its request to correct its details: to the people the setting names.',
		'subject'  => '[{site_name}] {institution_name} withdrew its request',
		'body'     => array(
			'{actor_name} withdrew the request to correct {institution_name}\'s details. Nothing waits for you.',
		),
		'list'     => 'site_name institution_name actor_name',
	),
);
$details_said = array();
foreach ( $details as $id => $want ) {
	$details_said[ $id ] = array(
		$all[ $id ]['audience'],
		$all[ $id ]['trigger'],
		$all[ $id ]['subject'],
		$paragraphs( $id ),
		implode( ' ', $lists_now[ $id ] ),
		$all[ $id ]['module'],
	);
}
ck(
	'each of the five is to the people the setting names or to the asking account, says what sends it, and has the subject, the paragraphs and the placeholders it was worded with, in the Institutions area',
	$details_said,
	array_map(
		static function ( $want ) {
			return array( $want['audience'], $want['trigger'], $want['subject'], $want['body'], $want['list'], 'institutions' );
		},
		$details
	)
);

$filled = array();
foreach ( array_keys( $details ) as $id ) {
	$mail           = WPCPM_Mail_Templates::render( $id, WPCPM_Mail_Templates::example_values( $id ) );
	$filled[ $id ] = array( $mail['subject'], $mail['body'] );
}
ck(
	'filled with their examples they read as the emails they are, every line break the template\'s own "\\r\\n", the lines of what changed among them: the people named, the institution, what changed and where to decide',
	$filled,
	array(
		'institution-details-saved'     => array(
			'[WordPress Credits] Example University\'s details were corrected',
			"Ada Okonjo changed Example University's details on the Institution Dashboard:\r\n\r\nName: Example College, now Example University\r\nCity: Example City, now Example Town\r\n\r\nThey are saved, and the program's records follow. Nothing waits for you.",
		),
		'institution-details-request'   => array(
			'[WordPress Credits] Example University asks to correct its details',
			"Ada Okonjo asks to correct Example University's details:\r\n\r\nName: Example College, asked to be Example University\r\nCity: Example City, asked to be Example Town\r\n\r\nNothing is changed until an Administrator approves it. Approve or decline it on the Administrator Dashboard: https://example.test/administrator-dashboard/",
		),
		'institution-details-approved'  => array(
			'[WordPress Credits] Your institution\'s details are updated',
			"An Administrator approved the change you asked for, and the program's records now read:\r\n\r\nName: Example College, now Example University\r\nCity: Example City, now Example Town",
		),
		'institution-details-declined'  => array(
			'[WordPress Credits] Your request to correct your institution\'s details',
			"An Administrator did not make the change you asked for, and wrote:\r\n\r\nPlease give the name as it appears on your website.\r\n\r\nThe details on record are unchanged. You can ask again from the Institution Dashboard: https://example.test/institution-dashboard/",
		),
		'institution-details-withdrawn' => array(
			'[WordPress Credits] Example University withdrew its request',
			"Ada Okonjo withdrew the request to correct Example University's details. Nothing waits for you.",
		),
	)
);
ck(
	'the request\'s sentence about where to decide, and the decline\'s about asking again, are left out while their link is empty, and the rest of each stays',
	array(
		WPCPM_Mail_Templates::render( 'institution-details-request', array( 'request_link' => '' ) + WPCPM_Mail_Templates::example_values( 'institution-details-request' ) )['body'],
		WPCPM_Mail_Templates::render( 'institution-details-declined', array( 'dashboard_link' => '' ) + WPCPM_Mail_Templates::example_values( 'institution-details-declined' ) )['body'],
	),
	array(
		"Ada Okonjo asks to correct Example University's details:\r\n\r\nName: Example College, asked to be Example University\r\nCity: Example City, asked to be Example Town\r\n\r\nNothing is changed until an Administrator approves it.",
		"An Administrator did not make the change you asked for, and wrote:\r\n\r\nPlease give the name as it appears on your website.\r\n\r\nThe details on record are unchanged.",
	)
);
ck(
	'who made the change is explained as a name the email opens with, with the words to use when there is none, and what changed as the lines the email needs',
	array( $all['institution-details-saved']['placeholders']['actor_name']['means'], $all['institution-details-saved']['placeholders']['changes']['means'], $all['institution-details-request']['placeholders']['request_link']['means'] ),
	array(
		'Who made the change or asked for it: their name, or "A member of the institution" when their account holds none.',
		'What changed, a line to each detail: its name, what the program had, and what it reads now, or what the institution asked for. The email needs it, in its body.',
		'The Administrator Dashboard, where the request waits for an Administrator to approve or decline it. Empty leaves its sentence out.',
	)
);

$subjects = array(
	'report-drafted'  => '[{site_name}] Semester report drafted: {institution_name}',
	'report-approved' => '[{site_name}] Your semester report is ready',
	'report-consent'  => '[{site_name}] May your institution mention you in its semester report?',
);
ck( 'the three Semester reports emails\' subjects open with the site\'s name in brackets, as every other email\'s does', array_map( static function ( $id ) use ( $all ) { return $all[ $id ]['subject']; }, array_keys( $subjects ) ), array_values( $subjects ) );
ck(
	'filled with their examples they read so, the site\'s name in the brackets',
	array_map( static function ( $id ) { return WPCPM_Mail_Templates::render( $id, WPCPM_Mail_Templates::example_values( $id ) )['subject']; }, array_keys( $subjects ) ),
	array( '[WordPress Credits] Semester report drafted: Example University', '[WordPress Credits] Your semester report is ready', '[WordPress Credits] May your institution mention you in its semester report?' )
);

$about_the_name = array();
foreach ( $lists_now as $id => $list ) {
	foreach ( array( 'institution_name' => 'The institution\'s name, or its record ID when the site holds no name for it.', 'site_name' => 'The site\'s name.' ) as $name => $means ) {
		if ( isset( $all[ $id ]['placeholders'][ $name ] ) && $means !== $all[ $id ]['placeholders'][ $name ]['means'] && ! in_array( $id, array( 'institution-applied', 'institution-applied-no-call', 'institution-application' ), true ) ) {
			$about_the_name[] = $id . ' {' . $name . '}';
		}
	}
}
ck( 'the institution\'s name is explained as its name, or its record ID when the site holds none, in every email of both areas but the three about an application, and the site\'s name as the site\'s name', $about_the_name, array() );
ck(
	'who uploaded it is explained for both of its copies, the institution\'s and the Administrators\'',
	array( $all['agreement-received']['placeholders']['uploaded_by']['means'], $all['agreement-landed']['placeholders']['uploaded_by']['means'], $all['agreement-landed-flagged']['placeholders']['uploaded_by']['means'] ),
	array_fill( 0, 3, 'Who uploaded it: their name, or, when their account holds none, "a member of your institution" in the institution\'s copy and "a member of the institution" in the Administrators\'.' )
);

$filled = WPCPM_Mail_Templates::render( 'member-last', WPCPM_Mail_Templates::example_values( 'member-last' ) );
ck( 'the last member\'s notice, filled with its examples, names the member, the institution, the day and why, in that order', explode( "\r\n\r\n", $filled['body'] )[0], 'Ada Okonjo is no longer a member of Example University as of ' . wp_date( 'F j, Y' ) . ' (left). No account can act for this institution on the site now.' );
$filled = WPCPM_Mail_Templates::render( 'agreement-template', WPCPM_Mail_Templates::example_values( 'agreement-template' ) );
ck( 'and the template notice names the institution, not a record', explode( "\r\n\r\n", $filled['body'] )[1], 'The reason given was: The agreement template needs attention: it contains a placeholder the generator does not fill in. (template language en, institution Example University, name asked for "Example University").' );

// An edit saved under 1.123.26 that uses {institution_record} still fits the two emails that
// stopped saying it: it is the shipped text that has changed since, which the state says, and the
// edit is what is sent, with the record in it. Saved now, an edit that uses it is taken too.
$was_shipped = array(
	'member-last'        => array(
		'subject' => '[{site_name}] {institution_name} has no members left',
		'body'    => "{member_name} is no longer a member of {institution_name} ({institution_record}) as of {left_on} ({ended_because}). No account can act for this institution on the site now.\n\nThe Airtable record, the agreement, the students and the history are untouched. A former member can be re-added in one click from the Institutions screen, or a new account created there.",
		'values'  => array( 'member_name' => 'Ada Okonjo', 'institution_name' => 'Example University', 'institution_record' => 'recEXAMPLE0000001', 'left_on' => 'March 9, 2036', 'ended_because' => 'left' ),
		'says'    => 'Ada Okonjo is no longer a member of Example University (recEXAMPLE0000001) as of March 9, 2036 (left).',
	),
	'agreement-template' => array(
		'subject' => '[{site_name}] The Collaboration Agreement template needs attention',
		'body'    => "An institution asked the site to generate the Collaboration Agreement and the template could not be used.\n\nThe reason given was: {reason} (template language {language}, institution {institution_record}, name asked for \"{name_asked_for}\").",
		'values'  => array( 'reason' => 'It holds a placeholder.', 'language' => 'en', 'institution_record' => 'recEXAMPLE0000001', 'name_asked_for' => 'Example University' ),
		'says'    => 'The reason given was: It holds a placeholder. (template language en, institution recEXAMPLE0000001, name asked for "Example University").',
	),
);
$kept_edits = array();
foreach ( $was_shipped as $id => $edit ) {
	$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates'][ $id ] = array( 'subject' => $edit['subject'], 'body' => $edit['body'], 'by' => 7, 'at' => 1, 'shipped' => md5( 'the text 1.123.26 shipped' ) );
	$stored_state                                                         = WPCPM_Mail_Templates::state( $id );
	$sent                                                                 = WPCPM_Mail_Templates::render( $id, array( 'site_name' => 'WordPress Credits' ) + $edit['values'] );
	$kept_edits[ $id ]                                                    = array( $stored_state['state'], $stored_state['reason'], WPCPM_Mail_Templates::sent_as_edited( $id ), false !== strpos( str_replace( "\r\n", "\n", $sent['body'] ), $edit['says'] ) );
	unset( $GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates'][ $id ] );

	$saved                      = WPCPM_Mail_Templates::save( $id, $edit['subject'], $edit['body'], 7 );
	$kept_edits[ $id ]['saved'] = array( $saved, WPCPM_Mail_Templates::state( $id )['state'], WPCPM_Mail_Templates::sent_as_edited( $id ) );
	WPCPM_Mail_Templates::restore( $id );
}
ck(
	'an edit of the last member\'s notice or the template notice that uses {institution_record} is not set aside: it is edited, the shipped text having changed since, it is sent as typed with the record, and one saved now is kept',
	$kept_edits,
	array(
		'member-last'        => array( 'changed', '', true, true, 'saved' => array( true, 'edited', true ) ),
		'agreement-template' => array( 'changed', '', true, true, 'saved' => array( true, 'edited', true ) ),
	)
);
unset( $GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ], $GLOBALS['autoload'][ WPCPM_Mail_Templates::OPTION ] );

// The two sentences an event email says only when they are true, the calendar file's and how many
// of the people on a canceled event were told, are values an Administrator can move or delete but
// not reword: every email that has one shows it in its preview and its test, its own example, the
// sentence that email says.
$conditional = array(
	'calendar_note' => array(),
	'told_note'     => array(),
);
foreach ( $all as $id => $entry ) {
	foreach ( array_keys( $conditional ) as $name ) {
		if ( isset( $entry['placeholders'][ $name ] ) ) {
			$examples                    = WPCPM_Mail_Templates::example_values( $id );
			$preview                     = WPCPM_Mail_Templates::render( $id, $examples );
			$conditional[ $name ][ $id ] = '' !== $examples[ $name ] && false !== strpos( $preview['body'], $examples[ $name ] ) ? $examples[ $name ] : '';
		}
	}
}
ck(
	'each Sponsor events email that may say what its calendar file does, or how many were told, says it in its preview, in its own words: the approval adds the event, a change replaces the file, a cancel or a leave takes it off',
	array( array_count_values( $conditional['calendar_note'] ), $conditional['told_note'] ),
	array(
		array(
			'The calendar file attached adds it to your calendar.'  => 3,
			'The calendar file attached takes it off your calendar.' => 7,
			'The calendar file attached replaces the one you had.'   => 4,
		),
		array( 'event-canceled-with-note' => 'The 3 people who joined it have been told.' ),
	)
);

// Nobody has joined an event that waits for approval, so the four emails about a waiting event leave
// the people who joined out of their preview and their test, as the email does; the four about an
// approved event, or a canceled one, which may have been either, show the line.
$joined_line = array();
foreach ( $all as $id => $entry ) {
	if ( isset( $entry['placeholders']['joined'] ) ) {
		$joined_line[ $id ] = false !== strpos( WPCPM_Mail_Templates::render( $id, WPCPM_Mail_Templates::example_values( $id ) )['body'], 'People who joined:' );
	}
}
ck(
	'the program contact\'s mail about a waiting event shows no line of the people who joined in its preview, the mail about an approved event or a canceled one does',
	$joined_line,
	array(
		'event-planned'              => false,
		'event-planned-for'          => false,
		'event-changed-waiting'      => false,
		'event-changed-waiting-for'  => false,
		'event-changed-approved'     => true,
		'event-changed-approved-for' => true,
		'event-canceled-by-sponsor'  => true,
		'event-canceled-for'         => true,
	)
);

// A term that ended is explained as one that ended: the tier whose term ended, held until its last day.
ck(
	'the tier is explained in the past tense in the email about a term that ended, and in the present tense in the one about a term that ends',
	array(
		false !== strpos( $all['sponsor-term-ended']['placeholders']['tier']['means'], 'term ended' ),
		false !== strpos( $all['sponsor-term-ended']['placeholders']['tier']['means'], 'term ends' ),
		false !== strpos( $all['sponsor-term-ending']['placeholders']['tier']['means'], 'term ends' ),
		false !== strpos( $all['sponsor-term-ending']['placeholders']['tier']['means'], 'term ended' ),
	),
	array( true, false, true, false )
);

// The site's name as WordPress stores it, its "&" an entity (`WPCPM_Mail::site_name()` hands it over
// so), is read back once by the registry, whatever the email: the subject of each of the term mails
// and the Sponsor events emails names the site as a person typed it, and neither its subject nor its
// body shows "&amp;".
$amp_checked = 0;
$amp_wrong   = array();
foreach ( $all as $id => $entry ) {
	if ( WPCPM_Mail_Catalog::MODULE_EVENTS !== $entry['module'] && 0 !== strpos( $id, 'sponsor-term-' ) ) {
		continue;
	}

	$mail = WPCPM_Mail_Templates::mail( $id, array( 'site_name' => 'Arts &amp; Sciences Dashboard' ) + WPCPM_Mail_Templates::example_values( $id ) );
	++$amp_checked;

	if ( 0 !== strpos( $mail['subject'], '[Arts & Sciences Dashboard] ' ) || false !== strpos( $mail['subject'] . $mail['body'], '&amp;' ) ) {
		$amp_wrong[] = $id;
	}
}
ck(
	'a site name holding an "&", stored as an entity, reads as typed in the subject of each of the 2 term emails and the 27 Sponsor events emails, and "&amp;" is in none of their subjects or bodies',
	array( $amp_checked, $amp_wrong ),
	array( 29, array() )
);

// In the emails about an application the institution's or the company's name is the one the
// application gave, and the placeholder says so there; everywhere else it keeps its area's words.
$as_applied = array();
foreach ( $all as $id => $entry ) {
	foreach ( array( 'institution_name' => 'The institution\'s name as the application gave it.', 'company_name' => 'The company\'s name as the application gave it.' ) as $name => $applied_means ) {
		if ( isset( $entry['placeholders'][ $name ] ) && ( $applied_means === $entry['placeholders'][ $name ]['means'] ) !== in_array( $id, array( 'institution-applied', 'institution-applied-no-call', 'institution-application', 'sponsor-applied', 'sponsor-applied-no-logo', 'sponsor-application' ), true ) ) {
			$as_applied[] = $id . ' {' . $name . '}';
		}
	}
}
ck( 'the institution\'s and the company\'s name say they are the name the application gave in the six emails about an application, and only there', $as_applied, array() );

$student = WPCPM_Mail_Templates::render( 'invite-student', WPCPM_Mail_Templates::example_values( 'invite-student' ) );
ck( 'filled with its examples, an invitation carries a marked example link and the dashboard of its audience', array( false !== strpos( $student['body'], 'key=EXAMPLE-KEY-NOT-A-REAL-LINK' ), false !== strpos( $student['body'], "Your Student Report Card:\r\nhttps://example.test/student-report-card/" ), $student['subject'] ), array( true, true, '[WordPress Credits] Welcome to the WordPress Credits Program' ) );

// The mentor's invitation carries the welcome a new mentor is sent (1.122.33). Its private links, the
// student courses' group invitation links and the mentor resource folder's, are never in the shipped
// text: one bracketed line stands where they go, and the mentor coordinator types them on the site.
$shipped = WPCPM_Mail_Templates::get( 'invite-mentor' );
preg_match_all( '#https?://[^\s]+#', $shipped['body'], $links );
$found = WPCPM_Mail_Templates::placeholders_in( $shipped['body'] );
sort( $found );
ck( 'the mentor\'s invitation holds the seven public links of its welcome and no other link', $links[0], array(
	'https://wordpresseducation.org/2026/08/03/welcome-to-your-mentor-report-card/',
	'https://wordpresseducation.org/2026/08/03/welcome-to-your-student-report-card/',
	'https://learn.wordpress.org/course/wordpress-credits-foundations/',
	'https://learn.wordpress.org/course/wordpress-credits-developer-track/',
	'https://learn.wordpress.org/course/wordpress-credits-designer-track/',
	'https://learn.wordpress.org/course/wordpress-credits-translation-track/',
	'https://learn.wordpress.org/course/wordpress-credits-intro-to-contribution/',
) );
ck( 'and says once, in brackets, where the private links go', substr_count( $shipped['body'], '[Each course\'s invitation link and the mentor resource folder\'s link are added here in Tools > Emails before this email is sent to mentors.]' ), 1 );
ck( 'and its placeholders are the invitation\'s own: the welcome adds none', $found, array( 'dashboard_link', 'login_url', 'set_password_link', 'username' ) );
ck( 'and its trigger names the three ways a mentor is sent it', $shipped['trigger'], 'When a mentor is sent the invitation to their account: when an application to mentor is activated, from the Mentors screen, or as the sync creates the account with invitation emails on.' );
ck( 'and the other invitations hold no welcome and no link of the welcome\'s',
	array_map(
		static function ( $id ) {
			$body = WPCPM_Mail_Templates::get( $id )['body'];
			return array( substr_count( $body, 'learn.wordpress.org' ), substr_count( $body, 'Report Card, https' ), false !== strpos( $body, 'Tools > Emails' ) );
		},
		array( 'invite-student', 'invite-institution', 'invite-institution-settled', 'invite-sponsor' )
	),
	array_fill( 0, 4, array( 0, 0, false ) ) );
ck( 'and it ends the way every invitation does, with a line break, and the welcome sits below the dashboard line after one blank line', array(
	"\n" === substr( $shipped['body'], -1 ),
	false !== strpos( $shipped['body'], "\n{dashboard_link}\n\nThank you, and congratulations on completing the WordPress Credits Mentor's Course! " ),
	substr_count( $shipped['body'], "\r" ),
), array( true, true, 0 ) );
ck( 'and an Administrator can save it as shipped: the cleaner takes nothing from it', WPCPM_Mail_Templates::refusal( $shipped['placeholders'], $shipped['subject'], $shipped['body'] ), '' );

$words = array();
foreach ( $all as $entry ) {
	$words[] = $entry['trigger'];
	foreach ( $entry['placeholders'] as $placeholder ) {
		$words[] = $placeholder['means'];
	}
}
ck( 'no trigger or placeholder description says module', array_values( preg_grep( '/\bmodules?\b/i', $words ) ), array() );
$sources = (string) file_get_contents( __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php' );
foreach ( WPCPM_Mail_Templates::AREAS as $file ) {
	$sources .= (string) file_get_contents( __DIR__ . '/../includes/mail/templates/' . $file . '.php' );
}
ck( 'and no em or en dash in the registry or its definitions', preg_match( '/\x{2013}|\x{2014}/u', $sources ), 0 );

echo "\n=== The examples ===\n";

// The Administrator looking at a preview, or pressing Send me a test, keeps Tokyo's clock: an email
// about a call is filled with a call tomorrow at 3 pm on that clock, half an hour long.
$GLOBALS['uid']                             = 9;
$GLOBALS['umeta'][9]['wpcpm_timezone']      = 'Asia/Tokyo';
$tokyo                                      = new DateTimeZone( 'Asia/Tokyo' );
$tomorrow                                   = ( new DateTimeImmutable( 'tomorrow 15:00', $tokyo ) )->getTimestamp();
$booked                                     = WPCPM_Mail_Templates::example_values( 'call-booked-mentor' );
$series                                     = WPCPM_Mail_Templates::example_values( 'series-joined-student' );
ck(
	'an email about a call is filled with a call tomorrow at 3 pm on the clock of the Administrator looking at it, that clock named, and the day and the hours to go worked out from it',
	array( $booked['when'], $booked['time_zone'], WPCPM_Mail_Templates::example_values( 'call-cancelled-mentor' )['call_date'], WPCPM_Mail_Templates::example_values( 'call-reminder-mentor' )['in_how_long'] ),
	array( wp_date( 'F j, Y', $tomorrow, $tokyo ) . ' 3:00 pm to 3:30 pm', 'Asia / Tokyo', wp_date( 'F j, Y', $tomorrow, $tokyo ), WPCPM_Mentor_Calls::relative( $tomorrow ) )
);
ck(
	'a series, three weekly sessions from then, one to a line, counted in words',
	array( $series['session_dates'], $series['date_count'], $series['group_session_count'], WPCPM_Mail_Templates::example_values( 'series-joined-mentor' )['session_count'] ),
	array(
		implode(
			"\n",
			array_map(
				function ( $days ) use ( $tomorrow, $tokyo ) {
					return wp_date( 'F j, Y', $tomorrow + $days * DAY_IN_SECONDS, $tokyo ) . ' 3:00 pm to 3:30 pm';
				},
				array( 0, 7, 14 )
			)
		),
		'3 dates',
		'3 group sessions',
		'3 sessions',
	)
);

// The same examples on a clock that changes. An Administrator in Warsaw, whose summer time ends on
// 25 Oct 2026, reads every example session at 3:00 pm, the three of a series and the time a session had
// before it moved, as a real session, kept on its clock and not moved by a count of seconds, would be.
// Read on the real clock, and on Warsaw's alone, so it can fail only while Warsaw's clock changes
// within a fortnight of tomorrow, as it does in the days before 25 Oct 2026 and 28 Mar 2027.
$GLOBALS['umeta'][9]['wpcpm_timezone'] = 'Europe/Warsaw';
$warsaw                                = new DateTimeZone( 'Europe/Warsaw' );
$warsaw_start                          = new DateTimeImmutable( 'tomorrow 15:00', $warsaw );
$warsaw_series                         = WPCPM_Mail_Templates::example_values( 'series-joined-student' )['session_dates'];
$warsaw_moved                          = WPCPM_Mail_Templates::example_values( 'session-moved-student' );
ck(
	'on a clock that changes, every session of a series, and the time a session had before it moved, keeps its clock time',
	array( $warsaw_series, $warsaw_moved['was_when'], $warsaw_moved['when'] ),
	array(
		implode(
			"\n",
			array_map(
				function ( $days ) use ( $warsaw_start, $warsaw ) {
					return wp_date( 'F j, Y', $warsaw_start->modify( '+' . $days . ' days' )->getTimestamp(), $warsaw ) . ' 3:00 pm to 3:30 pm';
				},
				array( 0, 7, 14 )
			)
		),
		wp_date( 'F j, Y', $warsaw_start->modify( '+1 day' )->getTimestamp(), $warsaw ) . ' 3:00 pm to 3:30 pm',
		wp_date( 'F j, Y', $warsaw_start->getTimestamp(), $warsaw ) . ' 3:00 pm to 3:30 pm',
	)
);
unset( $GLOBALS['umeta'][9]['wpcpm_timezone'] );

$GLOBALS['uid'] = 0;
ck( 'and with nobody signed in, on the site\'s clock', WPCPM_Mail_Templates::example_values( 'session-reminder' )['time_zone'], 'Europe / Warsaw' );

$links = array();
$names = array();
foreach ( array_keys( $all ) as $id ) {
	foreach ( WPCPM_Mail_Templates::example_values( $id ) as $name => $value ) {
		if ( preg_match_all( '#https?://[^\s/]+#', $value, $hosts ) ) {
			$links = array_merge( $links, $hosts[0] );
		}
		if ( in_array( $name, array( 'student_name', 'mentor_name', 'with_whom', 'canceled_by', 'institution_name', 'company_name', 'contact_name', 'inviter_name', 'uploaded_by', 'administrator_name', 'member_name', 'returned_by', 'claimant_name', 'author_name', 'name_asked_for', 'applicant_name' ), true ) ) {
			$names[] = $value;
		}
	}
}
ck( 'every example link is the site\'s own, example.test\'s or a WordPress.org profile of an invented handle, and every example name is an invented one', array( array_values( array_unique( $links ) ), array_values( array_unique( $names ) ) ), array( array( 'https://example.test', 'https://meet.example.test', 'https://calendly.example.test', 'https://hosting.example.test', 'https://profiles.wordpress.org' ), array( 'Ada Okonjo', 'Ben Lindqvist', 'Example University', 'Cleo Marsh', 'Example Hosting' ) ) );
ck(
	'a semester report\'s examples name the semester running now and its report on the Institution Dashboard, and how many students were still in progress, with their verb',
	array_intersect_key( WPCPM_Mail_Templates::example_values( 'report-drafted' ), array_flip( array( 'semester', 'students_in_progress', 'review_link' ) ) ),
	array(
		'semester'             => WPCPM_Cohort::label( WPCPM_Cohort::current() ),
		'students_in_progress' => '2 students in this semester were',
		'review_link'          => 'https://example.test/institution-dashboard/?wpcpm_report=' . WPCPM_Cohort::current() . '#wpcpm-report',
	)
);

// The Institutions' and the Sponsors' examples read as a real email would on the day it is looked at,
// on the site's clock: an agreement accepted, returned, revoked or put back in force today and
// uploaded yesterday, an invitation that works fourteen days from today, a membership ended today,
// the program's working days as set (three, by default), and every link that carries a secret
// carrying a fake one. Read twice, by an Administrator whose clock and the site's are a day apart at
// every hour, on the two sides of the date line, each the site's once: a day on the Administrator's
// clock, or on UTC's, cannot then pass for the site's. Zones the server knows by name, since one it
// does not know is read as the site's.
$examples = array();
$wanted   = array();
foreach ( array( array( 'Pacific/Pago_Pago', 'Pacific/Kiritimati' ), array( 'Pacific/Kiritimati', 'Pacific/Pago_Pago' ) ) as $zones ) {
	$who                                   = 'the site in ' . $zones[0] . ', the Administrator in ' . $zones[1];
	$GLOBALS['site_zone']                  = $zones[0];
	$GLOBALS['uid']                        = 9;
	$GLOBALS['umeta'][9]['wpcpm_timezone'] = $zones[1];
	$today                                 = new DateTimeImmutable( 'now', wp_timezone() );
	// Each an email's id and one of its placeholders: an email may be read for two.
	foreach ( array( array( 'agreement-accepted', 'accepted_on' ), array( 'agreement-reinstated', 'reinstated_on' ), array( 'agreement-returned', 'returned_on' ), array( 'agreement-revoked', 'revoked_on' ), array( 'agreement-received', 'uploaded_on' ), array( 'institution-invite', 'expiry_date' ), array( 'member-last', 'left_on' ), array( 'agreement-received', 'review_window' ), array( 'sponsor-agreement-accepted', 'accepted_on' ), array( 'sponsor-agreement-received', 'review_window' ), array( 'sponsor-term-ending', 'term_end' ), array( 'sponsor-term-ended', 'term_end' ) ) as $pair ) {
		list( $id, $name )                    = $pair;
		$examples[ $who ][ $id . ' ' . $name ] = WPCPM_Mail_Templates::example_values( $id )[ $name ];
	}
	$wanted[ $who ] = array(
		'agreement-accepted accepted_on'         => $today->format( 'Y-m-d' ),
		'agreement-reinstated reinstated_on'     => $today->format( 'Y-m-d' ),
		'agreement-returned returned_on'         => $today->format( 'Y-m-d' ),
		'agreement-revoked revoked_on'           => $today->format( 'Y-m-d' ),
		'agreement-received uploaded_on'         => $today->modify( '-1 day' )->format( 'Y-m-d' ),
		'institution-invite expiry_date'         => $today->modify( '+14 days' )->format( 'F j, Y' ),
		'member-last left_on'                    => $today->format( 'F j, Y' ),
		'agreement-received review_window'       => '3 working days',
		'sponsor-agreement-accepted accepted_on' => $today->format( 'Y-m-d' ),
		'sponsor-agreement-received review_window' => '3 working days',
		'sponsor-term-ending term_end'           => $today->modify( '+30 days' )->format( 'Y-m-d' ),
		'sponsor-term-ended term_end'            => $today->modify( '-5 days' )->format( 'Y-m-d' ),
	);
}
unset( $GLOBALS['site_zone'], $GLOBALS['umeta'][9]['wpcpm_timezone'] );
$GLOBALS['uid'] = 0;
ck(
	'an agreement is decided today and was uploaded yesterday, an invitation works fourteen days from today, a membership ended today, a term ends thirty days from today and the one that ended did so five days ago, and the program takes the working days set, all on the site\'s clock, whatever the clock of the Administrator looking at them',
	array( array_values( array_diff( array( 'Pacific/Pago_Pago', 'Pacific/Kiritimati' ), DateTimeZone::listIdentifiers() ) ), $examples ),
	array( array(), $wanted )
);
$secrets = array();
foreach ( array( 'institution-applied' => 'verify_link', 'institution-invite' => 'accept_link', 'mentor-application-received' => 'verify_link' ) as $id => $name ) {
	parse_str( (string) parse_url( WPCPM_Mail_Templates::example_values( $id )[ $name ], PHP_URL_QUERY ), $query );
	$secrets[ $id ] = array( $query['action'] ?? '', $query['t'] ?? '' );
}
ck(
	'and an application\'s confirmation link and an invitation\'s link are the site\'s own, to the handlers they open, each with a fake secret',
	$secrets,
	array(
		'institution-applied'         => array( WPCPM_Institution_Application::ACTION_VERIFY, 'EXAMPLE-NOT-A-REAL-SIGNATURE' ),
		'institution-invite'          => array( WPCPM_Institution_Invite::ACTION_ACCEPT, 'EXAMPLE-NOT-A-REAL-TOKEN' ),
		'mentor-application-received' => array( WPCPM_Mentor_Application::ACTION_VERIFY, 'EXAMPLE-NOT-A-REAL-SIGNATURE' ),
	)
);
ck(
	'the mentors\' course link of the example is the site\'s own, with a fake invitation in it, and the queue the Mentors screen\'s Waiting for review tab',
	array( WPCPM_Mail_Templates::example_values( 'mentor-selected' )['course_link'], WPCPM_Mail_Templates::example_values( 'mentor-application-notice' )['queue_link'] ),
	array( 'https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE', 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue' )
);
// Once an Administrator sets the course link, a preview or a test of the selection shows that link,
// and the program's address and the course's title as set, so the email tried is the email an
// applicant gets. Each is set to a value of its own, not the one its example falls back to, so an
// example that ignored its setting would fail here.
WPCPM_Settings::$course       = 'https://example.test/learn/mentors/?invite=SET-IN-SETTINGS';
WPCPM_Settings::$contact      = 'team@example.test';
WPCPM_Settings::$course_title = 'The Example Mentors Course';
$set_selection                = WPCPM_Mail_Templates::example_values( 'mentor-selected' );
WPCPM_Settings::$course       = '';
WPCPM_Settings::$contact      = '';
WPCPM_Settings::$course_title = null;
$unset_selection              = WPCPM_Mail_Templates::example_values( 'mentor-selected' );
ck(
	'and with the course link, the contact address and the course title set, the selection\'s examples are the three as set; with none set, the contact is an example address and the course the default title',
	array( $set_selection['course_link'], $set_selection['program_contact'], $set_selection['course_name'], $unset_selection['program_contact'], $unset_selection['course_name'] ),
	array( 'https://example.test/learn/mentors/?invite=SET-IN-SETTINGS', 'team@example.test', 'The Example Mentors Course', 'programs@example.test', 'WordPress Credits Mentor\'s Course' )
);
$with_contact = WPCPM_Mail_Templates::render( 'mentor-selected', WPCPM_Mail_Templates::example_values( 'mentor-selected' ) );
$no_contact   = WPCPM_Mail_Templates::render( 'mentor-selected', array_merge( WPCPM_Mail_Templates::example_values( 'mentor-selected' ), array( 'program_contact' => '' ) ) );
ck(
	'the selection asks for a reply when the course is done and names the program\'s address on a line of its own, which no contact address leaves out, so the paragraph reads whole either way',
	array( false !== strpos( $with_contact['body'], "When you finish the course, please reply to this email to let us know.\nYou can also write to programs@example.test.\n\nOnce we receive your message" ), false !== strpos( $no_contact['body'], "When you finish the course, please reply to this email to let us know.\n\nOnce we receive your message" ), false !== strpos( $no_contact['body'], 'You can also write to' ) ),
	array( true, true, false )
);
ck(
	'a sponsor\'s uploaded agreement links the agreements waiting on the Sponsors screen, as its email does, and a sponsor application the screen\'s queue',
	array( WPCPM_Mail_Templates::example_values( 'sponsor-agreement-received' )['queue_link'], WPCPM_Mail_Templates::example_values( 'sponsor-application' )['queue_link'] ),
	array( 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-agreements', 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue' )
);

echo "\n=== Built once a request for each language ===\n";

WPCPM_Mail_Templates::all();
$GLOBALS['translated'] = 0;
$again                 = WPCPM_Mail_Templates::all();
$read_again            = $GLOBALS['translated'];
$GLOBALS['locale']     = 'pl_PL';
$GLOBALS['translated'] = 0;
$polish                = WPCPM_Mail_Templates::all();
$read_polish           = $GLOBALS['translated'];
unset( $GLOBALS['locale'] );
ck( 'asked again in the same language, the registry is the one already built, and nothing is translated again; asked in another, it is built again, in that one', array( $read_again, $read_polish > 0, array_keys( $polish ) === array_keys( $again ) ), array( 0, true, true ) );

echo "\n=== Filling a text ===\n";

$ph = array(
	'name'  => array( 'means' => 'A name.', 'example' => 'Ada', 'required' => true ),
	'topic' => array( 'means' => 'A topic.', 'example' => 'Blocks', 'required' => false ),
	'link'  => array( 'means' => 'A link.', 'example' => 'https://example.test/', 'required' => false ),
	'mail'  => array( 'means' => 'An address.', 'example' => 'help@example.test', 'required' => false ),
);

ck( 'each placeholder is replaced by its value, and a value is never read as a placeholder itself', WPCPM_Mail_Templates::fill( 'Hi {name}, about {topic}.', array( 'name' => '{topic}', 'topic' => 'Blocks' ), $ph ), 'Hi {topic}, about Blocks.' );
ck( 'a placeholder the email does not have is left as it was written', WPCPM_Mail_Templates::fill( 'Hi {name}, {nmae} and {Name}.', array( 'name' => 'Ada' ), $ph ), 'Hi Ada, {nmae} and {Name}.' );
// A name or a title as WordPress stores it, "&", "<", ">" and a quote written as entities, is read
// as a person reads it, in a body and in a subject alike; a value with none is unchanged by it; and
// each value is read back once and no deeper, so a typed-out `&amp;lt;`, stored as typed, arrives as
// `&lt;`. A builder hands its values over as stored for that reason.
ck(
	'every value is read as a person reads it, once: a name stored with its "&", "<", ">" and quotes as entities arrives as typed, a value with none is unchanged, and a typed-out entity is read back once and no deeper',
	array(
		WPCPM_Mail_Templates::fill( 'Hi {name}, about {topic}.', array( 'name' => 'Gus &amp; Gwen &lt;Ops&gt; &quot;G&quot; O&#039;Brien', 'topic' => 'Q&A <3 "both"' ), $ph ),
		WPCPM_Mail_Templates::fill( '[{name}] {topic}', array( 'name' => 'Arts &amp; Sciences', 'topic' => "Q&amp;A\nnext" ), $ph, "\n", false ),
		WPCPM_Mail_Templates::fill( 'Write {topic} for bold.', array( 'topic' => '&amp;lt;b&amp;gt;' ), $ph ),
	),
	array( 'Hi Gus & Gwen <Ops> "G" O\'Brien, about Q&A <3 "both".', '[Arts & Sciences] Q&A next', 'Write &lt;b&gt; for bold.' )
);
ck( 'placeholders_in() reads a word in braces, each once, in order', WPCPM_Mail_Templates::placeholders_in( '{b} {a} {b} {not one} {set_password_link} {x-y}' ), array( 'b', 'a', 'set_password_link', 'x-y' ) );
ck( 'an optional sentence with nothing to say goes, and the rest of its line stays as it was spaced', WPCPM_Mail_Templates::fill( "You take part.  Questions? Write to {mail}.  Thanks.\n", array(), $ph ), "You take part.  Thanks.\n" );
ck( 'and with a value it stays', WPCPM_Mail_Templates::fill( 'You take part. Questions? Write to {mail}.', array( 'mail' => 'help@example.test' ), $ph ), 'You take part. Questions? Write to help@example.test.' );
// What a sentence is, for an Administrator's own text and for a translation: the rule reads English
// punctuation, and the editor's placeholder note and the guide say so.
ck( 'only a full stop followed by a space ends a sentence: "!" and "?" end none, so the words before them go with the sentence, and the full stop of "e.g." ends one, so what follows it goes on its own', array( WPCPM_Mail_Templates::fill( 'Thanks! Write to {mail}. Bye.', array(), $ph ), WPCPM_Mail_Templates::fill( 'See you then, e.g. {mail} or the site.', array(), $ph ) ), array( 'Bye.', 'See you then, e.g.' ) );
ck( 'a line left with nothing goes, and so does the colon line above it and the blank line it would leave at the end', WPCPM_Mail_Templates::fill( "Hello.\n\nYour page:\n{link}\n", array(), $ph ), "Hello.\n" );
// The colon line above a line left out goes with it only when it is a bare label: once the sentences
// that went are taken out, it holds no placeholder at all. One that still holds a placeholder, a
// required one or one whose value prints, always stays, since its line says something.
$ph_link = $ph;

$ph_link['link']['required'] = true;

ck( 'a colon line holding a required placeholder stays above a line left out, and so does its value', WPCPM_Mail_Templates::fill( "Hello.\n\nUse {link} and visit:\n{topic}\n\nBye.", array( 'link' => 'LINK' ), $ph_link ), "Hello.\n\nUse LINK and visit:\n\nBye." );
ck( 'and a colon line holding a required placeholder stays even when its value is empty', WPCPM_Mail_Templates::fill( "Dear {name}:\n{topic}\nBye.", array(), $ph ), "Dear :\nBye." );
ck( 'and one holding an optional placeholder that has a value stays', WPCPM_Mail_Templates::fill( "Write to {mail}:\n{topic}\nBye.", array( 'mail' => 'help@example.test' ), $ph ), "Write to help@example.test:\nBye." );
ck( 'but a colon line whose only placeholder was in a sentence that went is a bare label by then, and goes with the line below it', WPCPM_Mail_Templates::fill( "Hi {topic}. Your page:\n{link}\nBye.", array(), $ph ), 'Bye.' );
ck( 'in the middle, the blank line it would leave doubled goes', WPCPM_Mail_Templates::fill( "Hello.\n\nTopic: {topic}\n\nBye.", array(), $ph ), "Hello.\n\nBye." );
ck( 'at the start, the blank line it would leave first goes', WPCPM_Mail_Templates::fill( "Topic: {topic}\n\nBye.", array(), $ph ), 'Bye.' );
ck( 'inside a paragraph, only the line goes', WPCPM_Mail_Templates::fill( "When: today\nTopic: {topic}\nWhere: online", array(), $ph ), "When: today\nWhere: online" );
ck( 'a line holding a required placeholder never goes, even when its value is empty', WPCPM_Mail_Templates::fill( "Hi {name}\nBye.", array(), $ph ), "Hi \nBye." );
ck( 'and its sentence stays, while an optional one beside it goes', WPCPM_Mail_Templates::fill( 'Hi {name}. Topic: {topic}.', array(), $ph ), 'Hi .' );
ck( 'a subject is filled and never shortened', WPCPM_Mail_Templates::fill( '[{topic}] Your account is ready', array(), $ph, "\n", false ), '[] Your account is ready' );
ck( 'and kept to one line: each line break in a value is one space there, since a subject is a header', WPCPM_Mail_Templates::fill( 'A {topic}', array( 'topic' => "x\r\ny" ), $ph, "\n", false ), 'A x y' );
ck( 'and so is each line break of the subject text itself, folded to one space, whatever line ending is asked for', WPCPM_Mail_Templates::fill( "A\nB {topic}", array( 'topic' => "x\r\ny" ), $ph, "\r\n", false ), 'A B x y' );
ck( 'the lines are joined with the line ending asked for, a value\'s own left as they are, and the last line break kept', WPCPM_Mail_Templates::fill( "A {topic}\r\nB\n", array( 'topic' => "x\ny" ), $ph, "\r\n" ), "A x\ny\r\nB\r\n" );
ck( 'an indented sentence keeps its indent when the one before it goes', WPCPM_Mail_Templates::fill( "  About {topic}. Then this.", array(), $ph ), '  Then this.' );

echo "\n=== Edits ===\n";

$shipped = WPCPM_Mail_Templates::get( 'invite-mentor' );
$values  = WPCPM_Mail_Templates::example_values( 'invite-mentor' );

/** What a save of the mentor invitation answers: true, or the sentence that refused it. */
function save_mentor( $subject, $body ) {
	$saved = WPCPM_Mail_Templates::save( 'invite-mentor', $subject, $body, 7 );

	return is_wp_error( $saved ) ? $saved->get_error_message() : $saved;
}

ck( 'with no edit, an email is shipped text and goes out as shipped', array( WPCPM_Mail_Templates::state( 'invite-mentor' )['state'], WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), WPCPM_Mail_Templates::text_of( 'invite-mentor' ) ), array( 'shipped', 'Shipped text', array( 'subject' => $shipped['subject'], 'body' => $shipped['body'] ) ) );

$good_body = "Welcome, mentor.\n\n{set_password_link}\n\nWe <3 our mentors & their students.";
$refusals  = array(
	'the subject left empty'                      => save_mentor( '  ', $good_body ),
	'the body left empty'                         => save_mentor( 'Hi', " \n " ),
	'the set-password link dropped'               => save_mentor( 'Hi', 'Welcome, mentor.' ),
	'the set-password link misspelled'            => save_mentor( 'Hi', "Welcome.\n{set_pasword_link}" ),
	'a placeholder the email does not have'       => save_mentor( 'Hi {student_nmae}', $good_body ),
	'two of them'                                 => save_mentor( 'Hi {a}', $good_body . ' {b}' ),
	'a subject WordPress would take words from'   => save_mentor( 'Get 10%cashback', $good_body ),
	'a body WordPress would take words from'      => save_mentor( 'Hi', $good_body . "\nKids <12 free, adults >18 pay" ),
	'a subject of 201 characters'                 => save_mentor( str_repeat( 'a', 201 ), $good_body ),
	'a body of 10,001 characters'                 => save_mentor( 'Hi', $good_body . str_repeat( 'b', 10001 - mb_strlen( $good_body ) ) ),
);
ck(
	'a save is refused, each with its own sentence, when a box is empty, a required placeholder is missing or misspelled, a placeholder is not the email\'s, WordPress would take words away, or a box is too long',
	$refusals,
	array(
		'the subject left empty'                    => 'Nothing was saved: the subject is empty.',
		'the body left empty'                       => 'Nothing was saved: the body is empty.',
		'the set-password link dropped'             => 'Nothing was saved: this email needs {set_password_link}.',
		'the set-password link misspelled'          => 'Nothing was saved: this email needs {set_password_link}.',
		'a placeholder the email does not have'     => 'Nothing was saved: {student_nmae} is not a placeholder of this email.',
		'two of them'                               => 'Nothing was saved: {a}, {b} are not placeholders of this email.',
		'a subject WordPress would take words from' => WPCPM_Typed_Text::loss_message( 'Subject' ),
		'a body WordPress would take words from'    => WPCPM_Typed_Text::loss_message( 'Body' ),
		'a subject of 201 characters'               => 'Nothing was saved: the subject is longer than 200 characters.',
		'a body of 10,001 characters'               => 'Nothing was saved: the body is longer than 10,000 characters.',
	)
);
// The set-password link goes in the body, never in the subject: a subject is no link a person can
// follow, and the mail log keeps it for 30 days.
ck( 'the set-password link in the subject alone is missing from the body, and refused as missing', save_mentor( 'Link: {set_password_link}', 'Welcome, mentor.' ), 'Nothing was saved: this email needs {set_password_link}.' );
ck( 'and in the subject beside the body\'s, it is refused with its own sentence', save_mentor( 'Link: {set_password_link}', $good_body ), 'Nothing was saved: {set_password_link} goes in the body, never in the subject.' );
// Only the footer has no subject: for any other email a null one is a subject left empty, so it is
// refused as one and nothing is stored.
ck( 'a save of an email that is not the footer with no subject at all is refused as an empty subject, and nothing is stored', array( save_mentor( null, $good_body ), WPCPM_Mail_Templates::edit( 'invite-mentor' ) ), array( 'Nothing was saved: the subject is empty.', null ) );
ck( 'and a refused save stores nothing', array_key_exists( WPCPM_Mail_Templates::OPTION, $GLOBALS['opts'] ), false );
ck( 'while 200 and 10,000 characters are allowed', array( save_mentor( str_repeat( 'a', 200 ), $good_body . str_repeat( 'b', 10000 - mb_strlen( $good_body ) ) ), WPCPM_Mail_Templates::restore( 'invite-mentor' ) ), array( true, true ) );

$GLOBALS['locales'] = array();
$before             = time();
ck( 'a good save is stored', save_mentor( '[{site_name}] Hi <3', $good_body ), true );
$edit = WPCPM_Mail_Templates::edit( 'invite-mentor' );
ck( 'as WordPress\'s cleaner keeps it, with who saved it, when, and the fingerprint of the shipped text it was made from', array( $edit['subject'], $edit['body'], $edit['by'], $edit['at'] >= $before, $edit['shipped'] === md5( $shipped['subject'] . "\n\n" . $shipped['body'] ) ), array( '[{site_name}] Hi &lt;3', "Welcome, mentor.\n\n{set_password_link}\n\nWe &lt;3 our mentors &amp; their students.", 7, true, true ) );
ck( 'in one option, not loaded on every page', array( array_keys( $GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates'] ), $GLOBALS['autoload'][ WPCPM_Mail_Templates::OPTION ] ), array( array( 'invite-mentor' ), false ) );
ck( 'the fingerprint is built in the site\'s own language, whatever the Administrator reads', $GLOBALS['locales'], array( 'en_US', 'restored' ) );
ck( 'the email is Edited, and goes out as edited, what was typed read back as typed, ending with a line break as its shipped body does', array( WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), WPCPM_Mail_Templates::render( 'invite-mentor', array( 'site_name' => 'WordPress Credits', 'set_password_link' => 'LINK' ) ) ), array( 'Edited', array( 'subject' => '[WordPress Credits] Hi <3', 'body' => "Welcome, mentor.\r\n\r\nLINK\r\n\r\nWe <3 our mentors & their students.\r\n" ) ) );
ck( 'its edit is the mentor invitation\'s alone', WPCPM_Mail_Templates::text_of( 'invite-student' )['subject'], WPCPM_Mail_Templates::get( 'invite-student' )['subject'] );
ck( 'and sent_as_edited(), which nothing in the plugin asks since 1.122.23, still says which email goes out as edited', array( WPCPM_Mail_Templates::sent_as_edited( 'invite-mentor' ), WPCPM_Mail_Templates::sent_as_edited( 'invite-student' ), WPCPM_Mail_Templates::sent_as_edited( 'call-booked' ) ), array( true, false, false ) );

// An edit saved before 1.122.23 keeps working. One of the low codes, written while `{codes_left}` was
// a number, still fits and is sent as edited, its count counted words now, as the guide and the
// changelog say; one of the interest email, written before it had `{site_name}`, still fits, the new
// placeholder optional. Each says the shipped text has changed since.
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['offer-low-stock']  = array( 'subject' => '[{site_name}] {offer_title}: {codes_left} codes left', 'body' => "Only {codes_left} codes left of {offer_title}.\n\n{offers_link}", 'by' => 7, 'at' => 1, 'shipped' => md5( 'the text 1.122.22 shipped' ) );
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['sponsor-interest'] = array( 'subject' => '[WordPress Credits] {company_name} would like to help', 'body' => "{company_name} said:\n\n{interest}", 'by' => 7, 'at' => 1, 'shipped' => md5( 'the text 1.122.22 shipped' ) );
ck(
	'an edit saved before 1.122.23 still fits and is sent as edited: the low codes\' count is counted words now, and the interest email\'s new {site_name} is optional',
	array(
		WPCPM_Mail_Templates::state( 'offer-low-stock' )['state'],
		WPCPM_Mail_Templates::render( 'offer-low-stock', array( 'site_name' => 'WordPress Credits', 'offer_title' => 'Hosting', 'codes_left' => '3 codes', 'low_mark' => '5', 'offers_link' => '' ) ),
		WPCPM_Mail_Templates::state( 'sponsor-interest' )['state'],
		WPCPM_Mail_Templates::render( 'sponsor-interest', array( 'site_name' => 'WordPress Credits', 'company_name' => 'Acme', 'interest' => 'A line.' ) )['subject'],
	),
	array( 'changed', array( 'subject' => '[WordPress Credits] Hosting: 3 codes codes left', 'body' => 'Only 3 codes codes left of Hosting.' ), 'changed', '[WordPress Credits] Acme would like to help' )
);
unset( $GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['offer-low-stock'], $GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['sponsor-interest'] );

// The low codes' preview leaves the Offers card's line out while the Sponsor Dashboard's page is not
// published, as the email does, and names the card while it is.
$with_page = WPCPM_Mail_Templates::render( 'offer-low-stock', WPCPM_Mail_Templates::example_values( 'offer-low-stock' ) )['body'];
WPCPM_Sponsors_Dashboard::$url = '';
$no_page = WPCPM_Mail_Templates::render( 'offer-low-stock', WPCPM_Mail_Templates::example_values( 'offer-low-stock' ) )['body'];
WPCPM_Sponsors_Dashboard::$url = 'https://example.test/sponsor-dashboard/';
ck(
	'the low codes\' example names the Offers card while the Sponsor Dashboard is published, and leaves its line out while it is not',
	array( false !== strpos( $with_page, 'from the Offers card: https://example.test/sponsor-dashboard/?wpcpm_sponsor_view=recEXAMPLE0000001#wpcpm-sponsor-offers' ), false !== strpos( $no_page, 'Offers card' ), false !== strpos( $no_page, 'wpcpm_sponsor_view' ) ),
	array( true, false, false )
);

// An update that changes the shipped text an edit was made from: the edit is still sent, and the
// state says the shipped text has changed since. The stored fingerprint stands in for an older text.
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-mentor']['shipped'] = md5( 'the text an earlier version shipped' );
ck( 'when the shipped text has changed since the edit, the edit is still sent and the state says so', array( WPCPM_Mail_Templates::state( 'invite-mentor' )['state'], WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), WPCPM_Mail_Templates::text_of( 'invite-mentor' )['subject'] ), array( 'changed', 'Edited, and the shipped text has changed since', '[{site_name}] Hi <3' ) );

// An edit an update no longer fits, as one stored before the update is left: set aside, and the
// shipped text sent.
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-mentor']['body'] = $edit['body'] . ' About {topic}.';
ck( 'an edit that uses a placeholder the email no longer has is set aside, says why, and the shipped text is sent', array( WPCPM_Mail_Templates::state( 'invite-mentor' ), WPCPM_Mail_Templates::text_of( 'invite-mentor' ) ), array( array( 'state' => 'set-aside', 'reason' => 'Edit set aside: it uses {topic}, which this email no longer has.', 'by' => 7, 'at' => $edit['at'] ), array( 'subject' => $shipped['subject'], 'body' => $shipped['body'] ) ) );
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-mentor']['body'] = 'Welcome, mentor. Sign in when you can.';
ck( 'and so is one without the set-password link, which never goes out without it', array( WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), false !== strpos( WPCPM_Mail_Templates::render( 'invite-mentor', array( 'set_password_link' => 'LINK' ) )['body'], 'LINK' ) ), array( 'Edit set aside: it lacks {set_password_link}, which this email needs.', true ) );
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-mentor']['subject'] = 'Your link: {set_password_link}';
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-mentor']['body']    = "Welcome, mentor.\n\n{set_password_link}";
ck( 'and so is one whose subject holds the set-password link, so no subject carries the key: the shipped subject is sent', array( WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), WPCPM_Mail_Templates::render( 'invite-mentor', array( 'site_name' => 'WordPress Credits', 'set_password_link' => 'LINK' ) )['subject'] ), array( 'Edit set aside: its subject uses {set_password_link}, which goes in the body.', '[WordPress Credits] Your mentor account is ready' ) );

// An edit whose every word is an optional placeholder can fill to nothing, or to a value that
// `sanitize_text_field()` removes whole, such as a tag: the mail layer sends no email without a
// subject, so the shipped subject is sent, filled, and the edit's body. The plain-text subject a
// registry email goes out with would keep the tag; the test is the stricter cleaner's (render()).
$late = array( 'site_name' => 'WordPress Credits', 'call_date' => 'July 15, 2036', 'when' => 'July 15, 2036 9:00 am to 9:30 am' );
ck( 'an edit whose subject is optional placeholders alone saves', WPCPM_Mail_Templates::save( 'call-cancelled-student', '{canceled_by}', 'The call on {when} is off.', 7 ), true );
ck(
	'and while they fill to nothing, the email goes out with the shipped subject and the edit\'s body, and so when they fill to a tag alone; with a value, with the edit\'s subject',
	array( WPCPM_Mail_Templates::render( 'call-cancelled-student', $late ), WPCPM_Mail_Templates::render( 'call-cancelled-student', $late + array( 'canceled_by' => 'Ben Lindqvist' ) )['subject'], WPCPM_Mail_Templates::render( 'call-cancelled-student', $late + array( 'canceled_by' => '<b></b>' ) )['subject'] ),
	array( array( 'subject' => '[WordPress Credits] Call on July 15, 2036 canceled', 'body' => 'The call on July 15, 2036 9:00 am to 9:30 am is off.' ), 'Ben Lindqvist', '[WordPress Credits] Call on July 15, 2036 canceled' )
);
WPCPM_Mail_Templates::restore( 'call-cancelled-student' );

ck( 'Restore the original drops the edit, and the email is shipped text again', array( WPCPM_Mail_Templates::restore( 'invite-mentor' ), WPCPM_Mail_Templates::edit( 'invite-mentor' ), WPCPM_Mail_Templates::state( 'invite-mentor' )['state'] ), array( true, null, 'shipped' ) );
ck( 'and there is nothing to restore a second time', WPCPM_Mail_Templates::restore( 'invite-mentor' ), false );

ck( 'a typing that reads as the shipped text stores nothing, so the email goes on translating', array( save_mentor( $shipped['subject'], str_replace( "\n", "\r\n", $shipped['body'] ) ), WPCPM_Mail_Templates::edit( 'invite-mentor' ) ), array( true, null ) );
$unheld = WPCPM_Mail_Templates::save( 'call-booked', 'Hi', 'Body', 7 );
ck( 'an email the registry does not hold is refused with its own sentence', is_wp_error( $unheld ) ? $unheld->get_error_message() : $unheld, 'Nothing was saved: the site has no such email.' );

// An edit keeps the shipped body's ending. A Semester reports email whose shipped body ends with a
// line break, and so ends, its signature and footer included, with one, ends so edited too: the
// cleaner a save goes through lets go of the white space at either end, so the edit is stored
// without it, as the editor's box draws it, and until 1.122.23 it was sent without it too. A body
// whose shipped text has no line break at its end is sent as typed.
$reader = new WP_User( 1, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );
$ending = function () use ( $reader ) {
	$GLOBALS['mail'] = array();
	WPCPM_Mail::send(
		$reader,
		'report-drafted',
		function () {
			return WPCPM_Mail_Templates::mail( 'report-drafted', WPCPM_Mail_Templates::example_values( 'report-drafted' ) );
		}
	);

	return $GLOBALS['mail'][0]['body'] ?? null;
};
$report_shipped = $ending();
$drafted        = WPCPM_Mail_Templates::get( 'report-drafted' );
WPCPM_Mail_Templates::save( 'report-drafted', $drafted['subject'], str_replace( "\n", "\r\n", "A line the Administrators added.\n\n" . $drafted['body'] ), 7 );
$as_edited = $ending();
$stored    = WPCPM_Mail_Templates::edit( 'report-drafted' )['body'];
WPCPM_Mail_Templates::restore( 'report-drafted' );
WPCPM_Mail_Templates::save( 'call-cancelled-student', '{canceled_by}', "The call on {when} is off.\r\n", 7 );
$no_ending = WPCPM_Mail_Templates::render( 'call-cancelled-student', array( 'when' => 'July 15, 2036 9:00 am to 9:30 am' ) )['body'];
WPCPM_Mail_Templates::restore( 'call-cancelled-student' );
ck(
	'an edit keeps the shipped body\'s ending: an edited Semester reports email ends, as it does shipped, with its footer and a line break, its edit stored as the cleaner keeps it; and an email whose shipped body has no line break at its end is sent as typed',
	array( substr( (string) $report_shipped, -1 ), $as_edited, substr( $stored, -1 ), $no_ending ),
	array( "\n", "A line the Administrators added.\n\n" . $report_shipped, '}', 'The call on July 15, 2036 9:00 am to 9:30 am is off.' )
);
// An edit that already ends with a line break, as one written into the option by other code can, a
// save's cleaner never keeping one, is not given a second: its email ends as the shipped one does.
$raw_endings = array();
foreach ( array( "\n", "\r\n" ) as $kept_ending ) {
	$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['report-drafted'] = array( 'subject' => $drafted['subject'], 'body' => str_replace( "\n", $kept_ending, "A line the Administrators added.\n\n" . $drafted['body'] ), 'by' => 7, 'at' => 1, 'shipped' => md5( 'the text an earlier release shipped' ) );
	$raw_endings[] = $ending();
}
unset( $GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['report-drafted'] );
ck(
	'and an edit stored with its line break at the end, LF or CRLF, keeps the one: its email ends as the shipped one, never with two',
	$raw_endings,
	array( "A line the Administrators added.\n\n" . $report_shipped, "A line the Administrators added.\n\n" . $report_shipped )
);

echo "\n=== The signature and footer ===\n";

// Four lines since 1.123.24: the site's address joined the signature, the owner approving it on 22
// October 2026, under the wording approved on 10 October 2026.
ck( 'the footer ships as the owner approved it, word for word, the site\'s address on its second line', WPCPM_Mail_Templates::footer_shipped(), "WordPress Credits, WordPress Education\n{site_link}\n{dashboard_link}\nThis email comes from the WordPress Credits program. Questions? Write to {program_contact}." );

$student_account = new WP_User( 30, 'Lu Example', 'lu@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$mentor_student  = new WP_User( 31, 'Mo Example', 'mo@example.test', array( WPCPM_Roles::ROLE_STUDENT, WPCPM_Roles::ROLE_MENTOR ) );

$GLOBALS['site_name_read'] = 0;
ck( 'to an applicant with no account: a blank line, the "-- " line, then the footer with the site\'s address and no dashboard line, and no contact sentence while Contact address in emails is empty', WPCPM_Mail_Templates::add_footer( "Thanks for applying.\nWe will write soon.", null, 'institution-applied' ), "Thanks for applying.\nWe will write soon.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/\nThis email comes from the WordPress Credits program." );
ck( 'and the footer reads nothing it does not print: the site\'s name is not asked for', $GLOBALS['site_name_read'], 0 );
ck( 'to a student, the site\'s address and then her dashboard, each on its line', WPCPM_Mail_Templates::add_footer( 'Booked.', $student_account, 'call-booked' ), "Booked.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/\nhttps://example.test/student-report-card/\nThis email comes from the WordPress Credits program." );
ck( 'and to an account of two kinds, the email\'s audience decides which dashboard', WPCPM_Mail_Templates::add_footer( 'Ready.', $mentor_student, 'invite-mentor' ), "Ready.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/\nhttps://example.test/mentor-report-card/\nThis email comes from the WordPress Credits program." );
ck( 'and a body that already names that dashboard gets no second copy of it in the footer, by the plugin\'s rule that no address appears twice, and keeps the site\'s address, which it does not name whole', WPCPM_Mail_Templates::add_footer( "Booked.\nYour Student Report Card:\nhttps://example.test/student-report-card/", $student_account, 'call-booked' ), "Booked.\nYour Student Report Card:\nhttps://example.test/student-report-card/\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/\nThis email comes from the WordPress Credits program." );
// The shipped footer's site line by the same rule (1.123.24): a body that names the home page whole
// leaves the line out, with no empty line in its place; and a dashboard that is the home page itself,
// a site that sets a dashboard as its front page, is printed once, on the site's line, whether its
// address is written with its last slash or without it.
ck( 'a body that names the home page whole gets no second copy of it, and no empty line where its line was', array( WPCPM_Mail_Templates::add_footer( 'Our site is https://example.test.', null, 'institution-applied' ), WPCPM_Mail_Templates::add_footer( "Our site:\r\nhttps://example.test/\r\n", $student_account, 'call-booked' ) ), array( "Our site is https://example.test.\n\n-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program.", "Our site:\r\nhttps://example.test/\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nhttps://example.test/student-report-card/\r\nThis email comes from the WordPress Credits program.\r\n" ) );
ck( 'a dashboard that is the home page itself is printed once, on the site\'s line', array( WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked', array( 'site_link' => 'https://example.test/', 'dashboard_link' => 'https://example.test/' ) ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked', array( 'site_link' => 'https://example.test/', 'dashboard_link' => 'https://example.test' ) ) ), array_fill( 0, 2, "Hi.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/\nThis email comes from the WordPress Credits program." ) );

WPCPM_Settings::$contact = 'programs@example.test';
ck( 'with Contact address in emails set, its sentence says where to write: the four lines, in full', WPCPM_Mail_Templates::add_footer( 'Booked.', $student_account, 'call-booked' ), "Booked.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/\nhttps://example.test/student-report-card/\nThis email comes from the WordPress Credits program. Questions? Write to programs@example.test." );
WPCPM_Settings::$contact = 'not an address';
ck( 'and a setting that holds no address leaves the sentence out', false !== strpos( WPCPM_Mail_Templates::add_footer( 'Booked.', null, 'call-booked' ), 'Questions?' ), false );
WPCPM_Settings::$contact = '';

ck( 'a body written with CR LF that ends with one gets the footer in CR LF, and ends with one', WPCPM_Mail_Templates::add_footer( "Hello.\r\n", null, 'call-booked' ), "Hello.\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nhttps://example.test/\r\nThis email comes from the WordPress Credits program.\r\n" );
ck( 'a test hands its own values over, in place of the recipient\'s, and one it leaves out leaves its line out, with no empty line', WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked', array( 'dashboard_link' => 'https://example.test/x/', 'program_contact' => 'help@example.test' ) ), "Hi.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/x/\nThis email comes from the WordPress Credits program. Questions? Write to help@example.test." );
ck( 'and an email\'s examples are its audience\'s dashboard and the contact as set', array( WPCPM_Mail_Templates::footer_examples( 'invite-sponsor' )['dashboard_link'], WPCPM_Mail_Templates::footer_examples( 'call-booked' )['dashboard_link'], WPCPM_Mail_Templates::footer_examples( 'call-booked' )['program_contact'] ), array( 'https://example.test/sponsor-dashboard/', 'https://example.test/student-report-card/', '' ) );
// An email to several kinds of people is previewed with the dashboard of those it is written for
// first: the codes running low, to a sponsor's accounts and an Administrator, with the Sponsor
// Dashboard's; a group session's, to its students and their mentor, with the Student Report Card's.
ck( 'and an email to several kinds of people shows the Sponsor Dashboard in the Sponsors area and the Student Report Card elsewhere', array( WPCPM_Mail_Templates::footer_examples( 'offer-low-stock' )['dashboard_link'], WPCPM_Mail_Templates::footer_examples( 'session-cancelled' )['dashboard_link'], WPCPM_Mail_Templates::footer_examples( 'session-reminder' )['dashboard_link'] ), array( 'https://example.test/sponsor-dashboard/', 'https://example.test/student-report-card/', 'https://example.test/student-report-card/' ) );

/** What a save of the footer answers: true, or the sentence that refused it. */
function save_footer( $body ) {
	$saved = WPCPM_Mail_Templates::save( WPCPM_Mail_Templates::FOOTER, null, $body, 7 );

	return is_wp_error( $saved ) ? $saved->get_error_message() : $saved;
}

ck( 'the footer\'s save is refused for a placeholder it does not have, for words WordPress would take, and past 10,000 characters', array( save_footer( "Thanks.\n{student_name}" ), save_footer( 'Kids <12 free, adults >18 pay' ), save_footer( str_repeat( 'f', 10001 ) ) ), array( 'Nothing was saved: {student_name} is not a placeholder of this email.', WPCPM_Typed_Text::loss_message( 'Signature and footer' ), 'Nothing was saved: the signature and footer is longer than 10,000 characters.' ) );
ck( 'an edited footer is stored on its own and ends every email', array( save_footer( "Kind regards,\n{site_name}" ), array_keys( $GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates'] ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER ) ) ), array( true, array(), "Hi.\n\n-- \nKind regards,\nWordPress Credits", 'Edited' ) );
ck( 'an empty footer is refused, since every email ends with it, and the one saved stays; a footer whose every line is left out adds nothing, not even the "-- " line', array( save_footer( '' ), save_footer( " \n " ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), save_footer( '{dashboard_link}' ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'institution-applied' ) ), array( 'Nothing was saved: the signature and footer is empty.', 'Nothing was saved: the signature and footer is empty.', "Hi.\n\n-- \nKind regards,\nWordPress Credits", true, 'Hi.' ) );
ck( 'Restore the original brings the shipped footer back', array( WPCPM_Mail_Templates::restore( WPCPM_Mail_Templates::FOOTER ), WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER )['state'], false !== strpos( WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), 'WordPress Credits, WordPress Education' ) ), array( true, 'shipped', true ) );

echo "\n=== The site's public address, {site_link} (1.122.37) ===\n";

// A program lead asked for the public site's address in the footer, since most people the program
// writes to cannot open the Administrator Dashboard a footer test showed (WordPress/WPCredits#255).
// `{site_link}` is the site's home page, the same for everybody; the shipped footer uses it since
// 1.123.24. The checks below save a footer of their own, and Restore the original ends them.
$site_link = WPCPM_Mail_Templates::footer_placeholders()['site_link'] ?? array();
ck(
	'the footer knows {site_link}, after {site_name}: the site\'s public address, the same for everyone, left out of an email that names it already, not required, its example the home page',
	array( array_keys( WPCPM_Mail_Templates::footer_placeholders() ), $site_link['means'] ?? null, $site_link['required'] ?? null, WPCPM_Mail_Templates::examples_of( array( 'site_link' => $site_link ) ) ),
	array( array( 'site_name', 'site_link', 'dashboard_link', 'program_contact' ), 'The site\'s public address, the same for everyone. Empty for an email that names it already, which leaves its line out.', false, array( 'site_link' => 'https://example.test/' ) )
);
ck( 'the shipped footer uses it since 1.123.24, on the line before the dashboard, and it is the text sent while nobody has edited the footer', array( WPCPM_Mail_Templates::placeholders_in( WPCPM_Mail_Templates::footer_shipped() ), WPCPM_Mail_Templates::text_of( WPCPM_Mail_Templates::FOOTER )['body'] ), array( array( 'site_link', 'dashboard_link', 'program_contact' ), WPCPM_Mail_Templates::footer_shipped() ) );
ck( 'it is filled with home_url( \'/\' ), read only while the footer\'s text uses it, and a test\'s examples carry it', array( WPCPM_Mail_Templates::footer_values( null, 'call-booked', array( 'site_link' ) ), WPCPM_Mail_Templates::footer_values( $student_account, 'call-booked', array( 'site_name' ) ), WPCPM_Mail_Templates::footer_examples( 'footer-test' )['site_link'] ?? null, WPCPM_Mail_Templates::footer_examples( 'invite-student' )['site_link'] ?? null ), array( array( 'site_link' => 'https://example.test/' ), array( 'site_name' => 'WordPress Credits' ), 'https://example.test/', 'https://example.test/' ) );
ck(
	'a footer that uses it is saved, and every email ends with the home page on its line: to a student, above her dashboard, and to an address with no account, alone',
	array(
		save_footer( "WordPress Credits, WordPress Education\n{site_link}\n{dashboard_link}" ),
		WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER )['state'],
		WPCPM_Mail_Templates::add_footer( 'Booked.', $student_account, 'call-booked' ),
		WPCPM_Mail_Templates::add_footer( 'Thanks for applying.', null, 'institution-applied' ),
	),
	array(
		true,
		'edited',
		"Booked.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/\nhttps://example.test/student-report-card/",
		"Thanks for applying.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/",
	)
);
ck( 'empty, as a value handed over can be, its line is left out', WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked', array( 'site_link' => '', 'dashboard_link' => '' ) ), "Hi.\n\n-- \nWordPress Credits, WordPress Education" );
ck(
	'a body that names the home page already gets no second copy of it, by the rule that no address appears twice: with its last slash, without it, or ending a sentence',
	array(
		WPCPM_Mail_Templates::add_footer( "Read more at https://example.test/\nThanks.", null, 'institution-applied' ),
		WPCPM_Mail_Templates::add_footer( 'Our site is https://example.test.', null, 'institution-applied' ),
		WPCPM_Mail_Templates::add_footer( 'See the site (https://example.test/), then reply.', null, 'institution-applied' ),
	),
	array(
		"Read more at https://example.test/\nThanks.\n\n-- \nWordPress Credits, WordPress Education",
		"Our site is https://example.test.\n\n-- \nWordPress Credits, WordPress Education",
		"See the site (https://example.test/), then reply.\n\n-- \nWordPress Credits, WordPress Education",
	)
);
// Every address of the site starts with the home page's, so the home page counts as named only where
// it stands whole: a body that names a page of the site, a dashboard or the sign-in page, keeps it.
ck(
	'but a body that names a page of the site keeps the line: the home page\'s address counts only where it stands whole',
	array(
		WPCPM_Mail_Templates::add_footer( "Your Student Report Card:\nhttps://example.test/student-report-card/", $student_account, 'call-booked' ),
		WPCPM_Mail_Templates::add_footer( 'Sign in at https://example.test/wp-login.php?redirect_to=x then reply.', null, 'institution-applied' ),
		WPCPM_Mail_Templates::add_footer( 'Not ours: https://example.test.example/ or https://example.testing/.', null, 'institution-applied' ),
	),
	array(
		"Your Student Report Card:\nhttps://example.test/student-report-card/\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/",
		"Sign in at https://example.test/wp-login.php?redirect_to=x then reply.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/",
		"Not ours: https://example.test.example/ or https://example.testing/.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/",
	)
);
ck( 'a footer of the home page alone is saved too, and Restore the original brings the shipped one back', array( save_footer( '{site_link}' ), WPCPM_Mail_Templates::add_footer( 'Hi.', $student_account, 'call-booked' ), WPCPM_Mail_Templates::restore( WPCPM_Mail_Templates::FOOTER ), WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER )['state'] ), array( true, "Hi.\n\n-- \nhttps://example.test/", true, 'shipped' ) );

echo "\n=== What {dashboard_link} becomes, kind by kind (1.122.37) ===\n";

// The Signature and footer tab and the footer's test list it, so the Administrator who reads an
// Administrator Dashboard there knows every other person gets their own page.
$every_page = array(
	'student'       => array( 'who' => 'Students', 'page' => 'Student Report Card', 'url' => 'https://example.test/student-report-card/' ),
	'mentor'        => array( 'who' => 'Mentors', 'page' => 'Mentor Report Card', 'url' => 'https://example.test/mentor-report-card/' ),
	'institution'   => array( 'who' => 'Institution members', 'page' => 'Institution Dashboard', 'url' => 'https://example.test/institution-dashboard/' ),
	'sponsor'       => array( 'who' => 'Sponsor members', 'page' => 'Sponsor Dashboard', 'url' => 'https://example.test/sponsor-dashboard/' ),
	'administrator' => array( 'who' => 'Administrators', 'page' => 'Administrator Dashboard', 'url' => 'https://example.test/administrator-dashboard/' ),
);
WPCPM_Sponsors_Dashboard::$url = '';
$without_sponsors              = WPCPM_Mail_Templates::dashboard_pages();
WPCPM_Sponsors_Dashboard::$url = 'https://example.test/sponsor-dashboard/';
ck( 'each kind of person with an account, by the page of their kind, its full name and its address, in the order of the five audiences', WPCPM_Mail_Templates::dashboard_pages(), $every_page );
ck( 'and a kind whose page is not published is left out', array_keys( $without_sponsors ), array( 'student', 'mentor', 'institution', 'administrator' ) );
ck( 'each address is the one an email to that kind fills in', array_map( function ( $page ) { return $page['url']; }, WPCPM_Mail_Templates::dashboard_pages() ), array_map( array( 'WPCPM_Mail_Templates', 'page_of_type' ), array_combine( array_keys( $every_page ), array_keys( $every_page ) ) ) );

echo "\n=== Send me a test ===\n";

$admin           = new WP_User( 1, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );
$GLOBALS['mail'] = array();
$sent            = WPCPM_Mail_Templates::send_test( 'invite-student', $admin );
$test            = isset( $GLOBALS['mail'][0] ) ? $GLOBALS['mail'][0] : array();

ck( 'a test is one email, to the Administrator who asked, and to nobody else', array( $sent, count( $GLOBALS['mail'] ), $test['to'] ?? null, $test['headers'] ?? null ), array( true, 1, 'ida@example.test', array() ) );
ck( 'with "[Test] " before its subject, under the template\'s own id, marked as a test', array( $test['subject'] ?? null, $test['context'] ?? null, $test['test'] ?? null ), array( '[Test] [WordPress Credits] Welcome to the WordPress Credits Program', 'invite-student', true ) );
ck( 'filled with its examples: the marked example link, the example username, and the footer as a student\'s ends, the site\'s address on its line and the Student Report Card\'s address named once, in the body', array( false !== strpos( $test['body'] ?? '', 'key=EXAMPLE-KEY-NOT-A-REAL-LINK' ), false !== strpos( $test['body'] ?? '', 'Your username is ada.example.' ), substr( $test['body'] ?? '', -strlen( "\r\nYour Student Report Card:\r\nhttps://example.test/student-report-card/\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nhttps://example.test/\r\nThis email comes from the WordPress Credits program.\r\n" ) ), substr_count( $test['body'] ?? '', 'https://example.test/student-report-card/' ) ), array( true, true, "\r\nYour Student Report Card:\r\nhttps://example.test/student-report-card/\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nhttps://example.test/\r\nThis email comes from the WordPress Credits program.\r\n", 1 ) );

save_mentor( 'Hi <3, {site_name}', $good_body );
$GLOBALS['mail'] = array();
WPCPM_Mail_Templates::send_test( 'invite-mentor', $admin );
ck( 'a test sends the email as it goes out: the edit, its subject kept as typed', array( $GLOBALS['mail'][0]['subject'] ?? null, false !== strpos( $GLOBALS['mail'][0]['body'] ?? '', 'We <3 our mentors' ) ), array( '[Test] Hi <3, WordPress Credits', true ) );
WPCPM_Mail_Templates::restore( 'invite-mentor' );

$GLOBALS['mail'] = array();
WPCPM_Mail_Templates::send_test( WPCPM_Mail_Templates::FOOTER, $admin );
// Since 1.122.37 the test says why its link is the Administrator Dashboard, and what everybody else
// gets in its place (WordPress/WPCredits#255). The list leaves the Administrators out: the test's own
// link names their page, and a body naming it would leave the footer's dashboard line out.
$footer_test_body = "This is how every email the plugin sends ends. This test went to you, an Administrator, so the dashboard link below is the Administrator Dashboard.\n\n"
	. "In everybody else's emails, that line links to their own page, unless the email names that page already:\n"
	. "Students: Student Report Card, https://example.test/student-report-card/\n"
	. "Mentors: Mentor Report Card, https://example.test/mentor-report-card/\n"
	. "Institution members: Institution Dashboard, https://example.test/institution-dashboard/\n"
	. "Sponsor members: Sponsor Dashboard, https://example.test/sponsor-dashboard/\n\n"
	. "Somebody with no account, such as an applicant, gets no dashboard link, and its line is left out.\n\n"
	. "-- \nWordPress Credits, WordPress Education\nhttps://example.test/\nhttps://example.test/administrator-dashboard/\nThis email comes from the WordPress Credits program.";
ck( 'the footer\'s test is a short email that ends with it, under the catalog\'s footer-test, and says its link is the Administrator Dashboard because it went to an Administrator, what everybody else gets in its place, and that somebody with no account gets none', array( $GLOBALS['mail'][0]['subject'] ?? null, $GLOBALS['mail'][0]['body'] ?? null, $GLOBALS['mail'][0]['context'] ?? null, $GLOBALS['mail'][0]['test'] ?? null ), array( '[Test] [WordPress Credits] The signature and footer', $footer_test_body, 'footer-test', true ) );
ck( 'its footer keeps the Administrator Dashboard\'s line: the address is named once, in the footer', substr_count( $GLOBALS['mail'][0]['body'] ?? '', 'https://example.test/administrator-dashboard/' ), 1 );

WPCPM_Sponsors_Dashboard::$url = '';
$GLOBALS['mail']               = array();
WPCPM_Mail_Templates::send_test( WPCPM_Mail_Templates::FOOTER, $admin );
WPCPM_Sponsors_Dashboard::$url = 'https://example.test/sponsor-dashboard/';
ck( 'a kind whose page is not published is left out of the test\'s list', array( false !== strpos( $GLOBALS['mail'][0]['body'] ?? '', 'Sponsor' ), false !== strpos( $GLOBALS['mail'][0]['body'] ?? '', "Institution members: Institution Dashboard, https://example.test/institution-dashboard/\n\nSomebody with no account" ) ), array( false, true ) );

save_footer( "Kind regards,\n{site_link}" );
$GLOBALS['mail'] = array();
WPCPM_Mail_Templates::send_test( WPCPM_Mail_Templates::FOOTER, $admin );
WPCPM_Mail_Templates::restore( WPCPM_Mail_Templates::FOOTER );
ck( 'and a footer with no {dashboard_link} has no dashboard to explain: its test says what it is and ends with the footer, the site\'s address on its line', $GLOBALS['mail'][0]['body'] ?? null, "This is how every email the plugin sends ends.\n\n-- \nKind regards,\nhttps://example.test/" );

$GLOBALS['mail'] = array();
ck( 'and an email the registry does not hold sends nothing', array( WPCPM_Mail_Templates::send_test( 'call-booked', $admin ), $GLOBALS['mail'] ), array( false, array() ) );

echo "\n=== An email as a builder hands it over ===\n";

// What a builder of the Institutions and the Sponsors returns, `mail()`: the email `render()` fills,
// with what stays in the sender's code beside it, or, for an id the registry does not hold, no subject
// and no body, so the mail layer sends nothing, with what the sender handed over still beside them.
$declined_values = array( 'site_name' => 'WordPress Credits' );
$reply           = array( 'headers' => array( 'Reply-To: cleo@example.test' ) );
$GLOBALS['mail'] = array();
ck(
	'mail() is render() with what the sender hands over beside it, and for an id the registry does not hold, an empty subject and body beside it, which the mail layer sends nothing for',
	array(
		WPCPM_Mail_Templates::mail( 'institution-declined', $declined_values, $reply ),
		WPCPM_Mail_Templates::mail( 'institution-declined', $declined_values ),
		WPCPM_Mail_Templates::mail( 'no-such', $declined_values, $reply ),
		WPCPM_Mail::send(
			$admin,
			'no-such',
			function () use ( $declined_values ) {
				return WPCPM_Mail_Templates::mail( 'no-such', $declined_values );
			}
		),
		$GLOBALS['mail'],
	),
	array(
		WPCPM_Mail_Templates::render( 'institution-declined', $declined_values ) + $reply,
		WPCPM_Mail_Templates::render( 'institution-declined', $declined_values ),
		array(
			'subject' => '',
			'body'    => '',
		) + $reply,
		false,
		array(),
	)
);

echo "\n=== An edited subject, as it is sent ===\n";

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

// An Administrator's edit goes out as they typed it: a "<" or an "&" in its subject reaches every
// recipient as a character, as the preview and Send me a test show it, never as an entity, and the
// values around it arrive exactly as the email sent as shipped carries them, a name WordPress stores
// with an entity in it read as a person reads it, as every value is since 1.122.23. The edit puts
// its typing before the shipped subject, so every value follows a "<". One email of each kind of
// sender, each through its own builder and the real mail layer: the Institutions' and the Sponsors'
// `mail()`, the Mentor calls' `mail_of()`, the Semester reports' `rendered()`; each sent as shipped,
// then edited, then restored. The builder hands over no mark of its own: the mail layer marks the
// subject of every email the registry fills plain text, shipped or edited (since 1.122.23; an
// edited one since 1.122.22), once, for all of them, and the `wpcpm_mail` filter is handed the
// mark.
$typed    = 'Codes < 5 & we <3 you: ';
$site     = 'Credits & Co';
$builders = array(
	'institution-application'    => function () use ( $site ) {
		return WPCPM_Mail_Templates::mail( 'institution-application', array( 'site_name' => $site, 'institution_name' => 'Arts &amp; Sciences "North"' ) + WPCPM_Mail_Templates::example_values( 'institution-application' ) );
	},
	'sponsor-agreement-received' => function () use ( $site ) {
		return WPCPM_Mail_Templates::mail( 'sponsor-agreement-received', array( 'site_name' => $site, 'company_name' => 'Example Hosting &amp; "Partners"' ) + WPCPM_Mail_Templates::example_values( 'sponsor-agreement-received' ) );
	},
	'call-booked-student'        => function () use ( $site ) {
		return reach( 'WPCPM_Mentor_Calls', 'mail_of' )->invoke( null, 'call-booked-student', array( 'site_name' => $site, 'mentor_name' => 'Seán O\'Example &amp; Ana' ) + WPCPM_Mail_Templates::example_values( 'call-booked-student' ), array() );
	},
	'report-drafted'             => function () use ( $site ) {
		return reach( 'WPCPM_Semester_Report_Screen', 'rendered' )->invoke( null, 'report-drafted', array( 'site_name' => $site, 'institution_name' => 'Example College &amp; "Arts"' ) + WPCPM_Mail_Templates::example_values( 'report-drafted' ) );
	},
);
$as_shipped = array(
	'institution-application'    => '[Credits & Co] New institution application from Arts & Sciences "North"',
	'sponsor-agreement-received' => '[Credits & Co] Example Hosting & "Partners" uploaded a signed Collaboration Agreement',
	'call-booked-student'        => '[Credits & Co] Your call with Seán O\'Example & Ana is booked',
	'report-drafted'             => '[Credits & Co] Semester report drafted: Example College & "Arts"',
);
/** The subject an email's real send hands `wp_mail()`, and whether the filter was handed it marked plain text. */
$sent_subject = function ( $id ) use ( $admin, &$builders ) {
	$GLOBALS['mail']     = array();
	$GLOBALS['filtered'] = array();
	WPCPM_Mail::send( $admin, $id, $builders[ $id ] );

	return array( $GLOBALS['mail'][0]['subject'] ?? null, $GLOBALS['filtered'][0]['plain_subject'] ?? null );
};
$edited_sends = array();
$wanted_sends = array();
foreach ( $builders as $id => $build ) {
	$shipped_send = $sent_subject( $id );
	$saved        = WPCPM_Mail_Templates::save( $id, $typed . WPCPM_Mail_Templates::get( $id )['subject'], WPCPM_Mail_Templates::get( $id )['body'], 7 );
	$edited_send  = $sent_subject( $id );
	$handed       = $build();
	WPCPM_Mail_Templates::restore( $id );

	$edited_sends[ $id ] = array(
		'shipped'  => $shipped_send,
		'saved'    => $saved,
		'edited'   => $edited_send,
		'builder'  => array( $handed['subject'] ?? null, array_key_exists( 'plain_subject', $handed ) ),
		'restored' => $sent_subject( $id ),
	);
	$wanted_sends[ $id ] = array(
		'shipped'  => array( $as_shipped[ $id ], true ),
		'saved'    => true,
		'edited'   => array( $typed . $as_shipped[ $id ], true ),
		'builder'  => array( $typed . $as_shipped[ $id ], false ),
		'restored' => array( $as_shipped[ $id ], true ),
	);
}
ck( 'an edited subject reaches every recipient as the Administrator typed it, "<" and "&" as characters, with each value as the email sent as shipped carries it, for an email of the Institutions, the Sponsors, the Mentor calls and the Semester reports alike; the builder hands over no mark, the mail layer marks it, the filter sees the mark, and the email restored goes out as shipped again', $edited_sends, $wanted_sends );

// An edit set aside sends the shipped text, its subject plain text as every email the registry fills
// is since 1.122.23: a value holding a "<" keeps it, where 1.122.22 sent `&lt;`.
$builders['institution-application'] = function () use ( $site ) {
	return WPCPM_Mail_Templates::mail( 'institution-application', array( 'site_name' => $site, 'institution_name' => 'Arts <3 Sciences' ) + WPCPM_Mail_Templates::example_values( 'institution-application' ) );
};

$shipped_lt = $sent_subject( 'institution-application' );

$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['institution-application'] = array(
	'subject' => 'Codes &lt; 5 from {institution_name}',
	'body'    => 'It is on the queue here: {queue_link} {no_such}',
	'by'      => 7,
	'at'      => time(),
	'shipped' => WPCPM_Mail_Templates::shipped_fingerprint( 'institution-application' ),
);
ck(
	'an edit set aside goes out as shipped, its subject plain text as a shipped one is, a value holding a "<" keeping it',
	array( WPCPM_Mail_Templates::state( 'institution-application' )['state'], $sent_subject( 'institution-application' ), $shipped_lt ),
	array( 'set-aside', array( '[Credits & Co] New institution application from Arts <3 Sciences', true ), array( '[Credits & Co] New institution application from Arts <3 Sciences', true ) )
);
WPCPM_Mail_Templates::restore( 'institution-application' );

// A plain subject is still one line, and no header can be added through it: a value or a builder's
// subject holding a line break and a header of its own reaches `wp_mail()` as one line, its "<" kept,
// and the headers the builder handed over are the only ones; an email the registry does not hold
// is cleaned as it always was.
$builders['institution-application'] = function () use ( $site ) {
	return WPCPM_Mail_Templates::mail( 'institution-application', array( 'site_name' => $site, 'institution_name' => "Arts <3\r\nBcc: someone@example.test\nX-Extra: 1" ) + WPCPM_Mail_Templates::example_values( 'institution-application' ) );
};
$builders['claim-problem'] = function () {
	return array( 'subject' => "Typed <3\r\nBcc: someone@example.test", 'body' => 'x', 'headers' => array( 'Reply-To: person@example.test' ) );
};
$builders['call-booked'] = function () {
	return array( 'subject' => "Old <b>tag</b> & id\r\nBcc: someone@example.test", 'body' => 'x' );
};
$lines = array();
foreach ( array( 'institution-application', 'claim-problem', 'call-booked' ) as $id ) {
	$GLOBALS['mail'] = array();
	WPCPM_Mail::send( $admin, $id, $builders[ $id ] );
	$lines[ $id ] = array( $GLOBALS['mail'][0]['subject'] ?? null, $GLOBALS['mail'][0]['headers'] ?? null );
}
ck(
	'a plain subject is one line, its "<" kept, and adds no header: a value and a builder\'s subject with a line break and a header in them arrive as one line, and the only headers are the builder\'s; a retired id is cleaned as before',
	$lines,
	array(
		'institution-application' => array( '[Credits & Co] New institution application from Arts <3 Bcc: someone@example.test X-Extra: 1', array() ),
		'claim-problem'           => array( 'Typed <3 Bcc: someone@example.test', array( 'Reply-To: person@example.test' ) ),
		'call-booked'             => array( 'Old tag & id Bcc: someone@example.test', array() ),
	)
);

echo "\n=== The sponsor emails after Offer more (1.123.22) ===\n";
// The Sponsor Dashboard's Offer more card left the page in 1.123.22, so an interest's line comes from
// Talk to your program contact alone, the one press that files one through WPCPM_Sponsor_Interests::
// file(); the Mentors card's request and the event planner's mails are emails of their own. The
// interest email's trigger and its line's description say so, and its example is such a line. And the
// sponsor's invitation no longer asks the company to tell the program how it would like to support it.
$interest_mail = WPCPM_Mail_Templates::get( 'sponsor-interest' );
$interest_line = $interest_mail['placeholders']['interest'] ?? array();
$invite_body   = (string) WPCPM_Mail_Templates::get( 'invite-sponsor' )['body'];
ck( 'the interest email\'s trigger and its line\'s description name Talk to your program contact, and its example is such a line', array(
	$interest_mail['trigger'],
	$interest_line['means'] ?? null,
	WPCPM_Mail_Templates::examples_of( array( 'interest' => $interest_line ) )['interest'] ?? null,
), array(
	'When a sponsor presses Talk to your program contact on the Sponsor Dashboard, to talk about the next tier or to renew its term: to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.',
	'What the sponsor asked, as the dated line its history on the Sponsors screen keeps: the day, who pressed, and the request, for example "wants to move to Backer" or "wants to renew as Champion". The email needs it, in its body.',
	wp_date( 'Y-m-d' ) . ' by Ada Okonjo: note: wants to move to Backer',
) );
ck( 'the sponsor\'s invitation asks the company to set its password and check its profile, and no more', array( substr_count( $invite_body, 'Set your password using the link below, then check your company profile.' ), strpos( $invite_body, 'how you would like to support' ) ), array( 1, false ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
