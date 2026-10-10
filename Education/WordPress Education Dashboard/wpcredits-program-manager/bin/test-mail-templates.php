<?php
/**
 * The template registry: what each email says, and how a text is filled.
 *
 * - **The catalog stays the one list of names.** Every template's id is a catalog id, its name, area
 *   and audience are the catalog's, and the Invitations are the four invitations and the institution's
 *   second one, for an institution whose Collaboration Agreement is on file.
 * - **Every template is whole.** Each placeholder its text uses is one it declares, each required one
 *   is in the body, none that goes in the body alone is in the subject, every declared one says what
 *   it means and has an example, and filled with the examples no placeholder is left. The
 *   set-password link is required in every invitation, in its body.
 * - **Filling.** A value is never read as a placeholder; an optional sentence with nothing to say
 *   goes, and so does a line left with nothing, the colon line above it and the blank line it leaves
 *   doubled; only a full stop followed by a space ends a sentence; a required placeholder's line
 *   never goes; a subject is never shortened, and is kept to one line.
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

$GLOBALS['opts']     = array();
$GLOBALS['autoload'] = array();
$GLOBALS['locales']  = array();

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
// Core's `add_query_arg( $args, $url )`, as the example set-password link is built over the login page.
function add_query_arg( $args, $url ) { foreach ( $args as $k => $v ) { $url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v; } return $url; }
function get_bloginfo( $k = 'name' ) { $GLOBALS['site_name_read'] = ( $GLOBALS['site_name_read'] ?? 0 ) + 1; return 'WordPress Credits'; }
function is_email( $s ) { return false !== filter_var( (string) $s, FILTER_VALIDATE_EMAIL ) ? $s : false; }
// Nobody here may manage the program, so each account is the kind its role says.
function user_can( $user, $cap ) { return false; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function current_user_can( $cap ) { return false; }
// The language the fingerprint of a shipped text is built in, recorded: the site's.
function get_locale() { return 'en_US'; }
function switch_to_locale( $locale ) { $GLOBALS['locales'][] = $locale; return true; }
function restore_previous_locale() { $GLOBALS['locales'][] = 'restored'; return true; }
function add_action() {}
function add_filter() {}
function apply_filters( $hook, $value ) { return $value; }
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

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
// Which kind of person an account is, which the footer's dashboard line reads.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-capture.php';

/** The one setting the footer reads, Contact address in emails, as a check sets it. */
class WPCPM_Settings {
	public static $contact = '';
	public static function get_value( $key, $fallback = null ) { return 'mail_contact' === $key ? self::$contact : $fallback; }
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
/** As above. */
class WPCPM_Sponsors_Dashboard {
	public static function page_url() { return 'https://example.test/sponsor-dashboard/'; }
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

ck( 'the Invitations are the four invitations and the institution\'s second one, in that order', array_keys( $all ), array( 'invite-student', 'invite-mentor', 'invite-institution', 'invite-institution-settled', 'invite-sponsor' ) );

$named = array();
foreach ( $all as $id => $entry ) {
	$catalog      = WPCPM_Mail_Catalog::get( $id );
	$named[ $id ] = null !== $catalog && $catalog['label'] === $entry['label'] && $catalog['module'] === $entry['module'] && $catalog['audience'] === $entry['audience'];
}
ck( 'every template is a catalog id, and its name, area and audience are the catalog\'s', $named, array_fill_keys( array_keys( $all ), true ) );

$areas = array();
foreach ( WPCPM_Mail_Templates::AREAS as $module => $file ) {
	$defined = require __DIR__ . '/../includes/mail/templates/' . $file . '.php';
	foreach ( array_keys( $defined ) as $id ) {
		$areas[ $id ] = array( $module, WPCPM_Mail_Catalog::module_of( $id ) );
	}
}
ck( 'each area\'s file defines that area\'s emails, and every id it defines is the catalog\'s', array_values( array_filter( $areas, function ( $pair ) { return $pair[0] !== $pair[1]; } ) ), array() );
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
$want_whole = array_fill_keys(
	array_keys( $all ),
	array( 'trigger' => true, 'undeclared' => array(), 'unused' => array(), 'missing' => array(), 'in_subject' => array(), 'about' => true, 'left' => array(), 'eol' => "\r\n", 'link' => array( array( 'set_password_link' ), array( 'set_password_link' ) ) )
);
ck( 'every template says what sends it, uses only the placeholders it declares and every one it declares, holds its required ones in its body and none that goes in the body in its subject, says what each means with an example, and leaves none unfilled; every invitation needs its set-password link, in its body, and keeps WordPress\'s line ending', $whole, $want_whole );

$student = WPCPM_Mail_Templates::render( 'invite-student', WPCPM_Mail_Templates::example_values( 'invite-student' ) );
ck( 'filled with its examples, an invitation carries a marked example link and the dashboard of its audience', array( false !== strpos( $student['body'], 'key=EXAMPLE-KEY-NOT-A-REAL-LINK' ), false !== strpos( $student['body'], "Your Student Report Card:\r\nhttps://example.test/student-report-card/" ), $student['subject'] ), array( true, true, '[WordPress Credits] Welcome to the WordPress Credits Program' ) );

$words = array();
foreach ( $all as $entry ) {
	$words[] = $entry['trigger'];
	foreach ( $entry['placeholders'] as $placeholder ) {
		$words[] = $placeholder['means'];
	}
}
ck( 'no trigger or placeholder description says module', array_values( preg_grep( '/\bmodules?\b/i', $words ) ), array() );
ck( 'and no em or en dash in the registry or its definitions', preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php' ) . (string) file_get_contents( __DIR__ . '/../includes/mail/templates/invitations.php' ) ), 0 );

echo "\n=== Filling a text ===\n";

$ph = array(
	'name'  => array( 'means' => 'A name.', 'example' => 'Ada', 'required' => true ),
	'topic' => array( 'means' => 'A topic.', 'example' => 'Blocks', 'required' => false ),
	'link'  => array( 'means' => 'A link.', 'example' => 'https://example.test/', 'required' => false ),
	'mail'  => array( 'means' => 'An address.', 'example' => 'help@example.test', 'required' => false ),
);

ck( 'each placeholder is replaced by its value, and a value is never read as a placeholder itself', WPCPM_Mail_Templates::fill( 'Hi {name}, about {topic}.', array( 'name' => '{topic}', 'topic' => 'Blocks' ), $ph ), 'Hi {topic}, about Blocks.' );
ck( 'a placeholder the email does not have is left as it was written', WPCPM_Mail_Templates::fill( 'Hi {name}, {nmae} and {Name}.', array( 'name' => 'Ada' ), $ph ), 'Hi Ada, {nmae} and {Name}.' );
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
ck( 'the email is Edited, and goes out as edited, what was typed read back as typed', array( WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), WPCPM_Mail_Templates::render( 'invite-mentor', array( 'site_name' => 'WordPress Credits', 'set_password_link' => 'LINK' ) ) ), array( 'Edited', array( 'subject' => '[WordPress Credits] Hi <3', 'body' => "Welcome, mentor.\r\n\r\nLINK\r\n\r\nWe <3 our mentors & their students." ) ) );
ck( 'its edit is the mentor invitation\'s alone', WPCPM_Mail_Templates::text_of( 'invite-student' )['subject'], WPCPM_Mail_Templates::get( 'invite-student' )['subject'] );

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

ck( 'Restore the original drops the edit, and the email is shipped text again', array( WPCPM_Mail_Templates::restore( 'invite-mentor' ), WPCPM_Mail_Templates::edit( 'invite-mentor' ), WPCPM_Mail_Templates::state( 'invite-mentor' )['state'] ), array( true, null, 'shipped' ) );
ck( 'and there is nothing to restore a second time', WPCPM_Mail_Templates::restore( 'invite-mentor' ), false );

ck( 'a typing that reads as the shipped text stores nothing, so the email goes on translating', array( save_mentor( $shipped['subject'], str_replace( "\n", "\r\n", $shipped['body'] ) ), WPCPM_Mail_Templates::edit( 'invite-mentor' ) ), array( true, null ) );
$unheld = WPCPM_Mail_Templates::save( 'call-booked', 'Hi', 'Body', 7 );
ck( 'an email the registry does not hold is refused with its own sentence', is_wp_error( $unheld ) ? $unheld->get_error_message() : $unheld, 'Nothing was saved: the site has no such email.' );

echo "\n=== The signature and footer ===\n";

ck( 'the footer ships as the owner approved it, word for word', WPCPM_Mail_Templates::footer_shipped(), "WordPress Credits, WordPress Education\n{dashboard_link}\nThis email comes from the WordPress Credits program. Questions? Write to {program_contact}." );

$student_account = new WP_User( 30, 'Lu Example', 'lu@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$mentor_student  = new WP_User( 31, 'Mo Example', 'mo@example.test', array( WPCPM_Roles::ROLE_STUDENT, WPCPM_Roles::ROLE_MENTOR ) );

$GLOBALS['site_name_read'] = 0;
ck( 'to an applicant with no account: a blank line, the "-- " line, then the footer with no dashboard line, and no contact sentence while Contact address in emails is empty', WPCPM_Mail_Templates::add_footer( "Thanks for applying.\nWe will write soon.", null, 'institution-applied' ), "Thanks for applying.\nWe will write soon.\n\n-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program." );
ck( 'and the footer reads nothing it does not print: the site\'s name is not asked for', $GLOBALS['site_name_read'], 0 );
ck( 'to a student, her dashboard is on its line', WPCPM_Mail_Templates::add_footer( 'Booked.', $student_account, 'call-booked' ), "Booked.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/student-report-card/\nThis email comes from the WordPress Credits program." );
ck( 'and to an account of two kinds, the email\'s audience decides which dashboard', WPCPM_Mail_Templates::add_footer( 'Ready.', $mentor_student, 'invite-mentor' ), "Ready.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/mentor-report-card/\nThis email comes from the WordPress Credits program." );
ck( 'and a body that already names that dashboard gets no second copy of it in the footer, by the plugin\'s rule that no address appears twice', WPCPM_Mail_Templates::add_footer( "Booked.\nYour Student Report Card:\nhttps://example.test/student-report-card/", $student_account, 'call-booked' ), "Booked.\nYour Student Report Card:\nhttps://example.test/student-report-card/\n\n-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program." );

WPCPM_Settings::$contact = 'programs@example.test';
ck( 'with Contact address in emails set, its sentence says where to write', WPCPM_Mail_Templates::add_footer( 'Booked.', null, 'call-booked' ), "Booked.\n\n-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program. Questions? Write to programs@example.test." );
WPCPM_Settings::$contact = 'not an address';
ck( 'and a setting that holds no address leaves the sentence out', false !== strpos( WPCPM_Mail_Templates::add_footer( 'Booked.', null, 'call-booked' ), 'Questions?' ), false );
WPCPM_Settings::$contact = '';

ck( 'a body written with CR LF that ends with one gets the footer in CR LF, and ends with one', WPCPM_Mail_Templates::add_footer( "Hello.\r\n", null, 'call-booked' ), "Hello.\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nThis email comes from the WordPress Credits program.\r\n" );
ck( 'a test hands its own values over, in place of the recipient\'s', WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked', array( 'dashboard_link' => 'https://example.test/x/', 'program_contact' => 'help@example.test' ) ), "Hi.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/x/\nThis email comes from the WordPress Credits program. Questions? Write to help@example.test." );
ck( 'and an email\'s examples are its audience\'s dashboard and the contact as set', array( WPCPM_Mail_Templates::footer_examples( 'invite-sponsor' )['dashboard_link'], WPCPM_Mail_Templates::footer_examples( 'call-booked' )['dashboard_link'], WPCPM_Mail_Templates::footer_examples( 'call-booked' )['program_contact'] ), array( 'https://example.test/sponsor-dashboard/', 'https://example.test/student-report-card/', '' ) );

/** What a save of the footer answers: true, or the sentence that refused it. */
function save_footer( $body ) {
	$saved = WPCPM_Mail_Templates::save( WPCPM_Mail_Templates::FOOTER, null, $body, 7 );

	return is_wp_error( $saved ) ? $saved->get_error_message() : $saved;
}

ck( 'the footer\'s save is refused for a placeholder it does not have, for words WordPress would take, and past 10,000 characters', array( save_footer( "Thanks.\n{student_name}" ), save_footer( 'Kids <12 free, adults >18 pay' ), save_footer( str_repeat( 'f', 10001 ) ) ), array( 'Nothing was saved: {student_name} is not a placeholder of this email.', WPCPM_Typed_Text::loss_message( 'Signature and footer' ), 'Nothing was saved: the signature and footer is longer than 10,000 characters.' ) );
ck( 'an edited footer is stored on its own and ends every email', array( save_footer( "Kind regards,\n{site_name}" ), array_keys( $GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates'] ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER ) ) ), array( true, array(), "Hi.\n\n-- \nKind regards,\nWordPress Credits", 'Edited' ) );
ck( 'an empty footer is refused, since every email ends with it, and the one saved stays; a footer whose every line is left out adds nothing, not even the "-- " line', array( save_footer( '' ), save_footer( " \n " ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), save_footer( '{dashboard_link}' ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'institution-applied' ) ), array( 'Nothing was saved: the signature and footer is empty.', 'Nothing was saved: the signature and footer is empty.', "Hi.\n\n-- \nKind regards,\nWordPress Credits", true, 'Hi.' ) );
ck( 'Restore the original brings the shipped footer back', array( WPCPM_Mail_Templates::restore( WPCPM_Mail_Templates::FOOTER ), WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER )['state'], false !== strpos( WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), 'WordPress Credits, WordPress Education' ) ), array( true, 'shipped', true ) );

echo "\n=== Send me a test ===\n";

$admin           = new WP_User( 1, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );
$GLOBALS['mail'] = array();
$sent            = WPCPM_Mail_Templates::send_test( 'invite-student', $admin );
$test            = isset( $GLOBALS['mail'][0] ) ? $GLOBALS['mail'][0] : array();

ck( 'a test is one email, to the Administrator who asked, and to nobody else', array( $sent, count( $GLOBALS['mail'] ), $test['to'] ?? null, $test['headers'] ?? null ), array( true, 1, 'ida@example.test', array() ) );
ck( 'with "[Test] " before its subject, under the template\'s own id, marked as a test', array( $test['subject'] ?? null, $test['context'] ?? null, $test['test'] ?? null ), array( '[Test] [WordPress Credits] Welcome to the WordPress Credits Program', 'invite-student', true ) );
ck( 'filled with its examples: the marked example link, the example username, and the footer as a student\'s ends, the Student Report Card\'s address named once, in the body', array( false !== strpos( $test['body'] ?? '', 'key=EXAMPLE-KEY-NOT-A-REAL-LINK' ), false !== strpos( $test['body'] ?? '', 'Your username is ada.example.' ), substr( $test['body'] ?? '', -strlen( "\r\nYour Student Report Card:\r\nhttps://example.test/student-report-card/\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nThis email comes from the WordPress Credits program.\r\n" ) ), substr_count( $test['body'] ?? '', 'https://example.test/student-report-card/' ) ), array( true, true, "\r\nYour Student Report Card:\r\nhttps://example.test/student-report-card/\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nThis email comes from the WordPress Credits program.\r\n", 1 ) );

save_mentor( 'Hi <3, {site_name}', $good_body );
$GLOBALS['mail'] = array();
WPCPM_Mail_Templates::send_test( 'invite-mentor', $admin );
ck( 'a test sends the email as it goes out: the edit, its subject kept as typed', array( $GLOBALS['mail'][0]['subject'] ?? null, false !== strpos( $GLOBALS['mail'][0]['body'] ?? '', 'We <3 our mentors' ) ), array( '[Test] Hi <3, WordPress Credits', true ) );
WPCPM_Mail_Templates::restore( 'invite-mentor' );

$GLOBALS['mail'] = array();
WPCPM_Mail_Templates::send_test( WPCPM_Mail_Templates::FOOTER, $admin );
ck( 'the footer\'s test is a short email that ends with it, filled as an Administrator\'s, under the catalog\'s footer-test', array( $GLOBALS['mail'][0]['subject'] ?? null, $GLOBALS['mail'][0]['body'] ?? null, $GLOBALS['mail'][0]['context'] ?? null, $GLOBALS['mail'][0]['test'] ?? null ), array( '[Test] [WordPress Credits] The signature and footer', "This is how every email the plugin sends ends, filled as an Administrator's own would be.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/administrator-dashboard/\nThis email comes from the WordPress Credits program.", 'footer-test', true ) );

$GLOBALS['mail'] = array();
ck( 'and an email the registry does not hold sends nothing', array( WPCPM_Mail_Templates::send_test( 'call-booked', $admin ), $GLOBALS['mail'] ), array( false, array() ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
