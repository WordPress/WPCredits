<?php
/**
 * The invitations, word for word: what every kind of invitation says, as 1.122.18 wrote it.
 *
 * The invitations move into the template registry in 1.122.19, and the move must change nothing a
 * recipient reads but the footer. So before the move this suite recorded, through the real
 * `WPCPM_Mail::welcome_email()`, the subject and the body of every kind and variant of invitation
 * into bin/fixtures/invitations-1-122-18.json; from then on it builds the same invitations from the
 * same inputs and compares them with the recording, byte for byte.
 *
 * The inputs: each of the four kinds, the institution's twice (its Collaboration Agreement on file
 * or not), each with its dashboard page published and without one, each as WordPress 7.1 words its
 * new-account email (the keyed link alone) and as WordPress 6.5, the plugin's floor, does (the
 * username, the keyed link, the plain login page); an account holding two roles; a site name
 * holding entities; and a site name as WordPress hands it over, decoded. The names are invented,
 * the addresses are example.test's, and the key in every link is a fixed fake.
 *
 * The recording is made once: `--record` refuses when the file is there, and the suite pins the
 * file's sha256, so a recording made again from later code can never stand in for 1.122.18's.
 *
 * One sentence has changed since, on purpose, in 1.122.20: the people who run the program are the
 * Administrators in every text a person reads, so the institution's invitation says "An
 * Administrator then accepts it" where 1.122.18 said "A program manager then accepts it". The
 * recording stays as 1.122.18 wrote it; the suite applies that one change to it before comparing,
 * and checks it fell on the institution's three invitations that ask for the Collaboration Agreement
 * and nowhere else.
 *
 * Run from the plugin root:  php bin/test-invitation-text.php
 * Record (once, against 1.122.18, before the move):  php bin/test-invitation-text.php --record
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts'] = array();

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id, $login, array $roles ) {
		$this->ID         = $id;
		$this->user_login = $login;
		$this->user_email = $login . '@example.test';
		$this->roles      = $roles;
	}
	public function exists() { return $this->ID > 0; }
}

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
function get_bloginfo( $k = 'name' ) { return 'WordPress Credits'; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_action() {}
function add_filter() {}
// Nobody here may manage the program, so each account is the kind its role says.
function user_can( $user, $cap ) { return false; }
require_once __DIR__ . '/stubs/specialchars.php';

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
// The template registry the invitations are built from since 1.122.19, and the catalog that names them.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
// Which kind of person an account is, which the footer's dashboard line reads.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-capture.php';

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
class WPCPM_Sponsors_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** Which institution an account belongs to: every institution account here belongs to one. */
class WPCPM_Institution_Members {
	public static function institution_of( $user ) { return 'recSEED000000016'; }
}
/** Whether that institution's Collaboration Agreement is on file, as a case says. */
class WPCPM_Institution_Agreement {
	public static $settled = false;
	public static function is_settled( $record ) { return self::$settled; }
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
 * What WordPress hands the new-account filter for an account, as one version words it.
 *
 * @param string $login   The account's username.
 * @param string $version '7.1' for the keyed link alone, '6.5' for the username, the link and the login page.
 * @return array
 */
function core_email( $login, $version ) {
	if ( '6.5' === $version ) {
		$message = "Username: $login\r\n\r\nTo set your password, visit the following address:\r\n\r\nhttps://example.test/wp-login.php?action=rp&key=TEST-KEY-NOT-A-REAL-LINK&login=$login\r\n\r\nhttps://example.test/wp-login.php\r\n";
	} else {
		$message = "To set your password, visit the following address:\r\n\r\nhttps://example.test/wp-login.php?login=$login&key=TEST-KEY-NOT-A-REAL-LINK&action=rp\r\n";
	}

	return array(
		'to'      => $login . '@example.test',
		'subject' => '[%s] Login Details',
		'message' => $message,
		'headers' => '',
	);
}

/**
 * Every case: the account, its roles, WordPress's wording, whether its dashboard page is published,
 * whether its institution's agreement is on file, and the site's name as WordPress hands it over.
 *
 * @return array<string, array>
 */
function cases() {
	$s = WPCPM_Roles::ROLE_STUDENT;
	$m = WPCPM_Roles::ROLE_MENTOR;
	$i = WPCPM_Roles::ROLE_INSTITUTION;
	$p = WPCPM_Roles::ROLE_SPONSOR;

	$out = array();

	foreach ( array(
		'student'             => array( 'ada.example', array( $s ), false ),
		'mentor'              => array( 'ben.example', array( $m ), false ),
		'institution'         => array( 'cy.example', array( $i ), false ),
		'institution-settled' => array( 'cy.example', array( $i ), true ),
		'sponsor'             => array( 'dee.example', array( $p ), false ),
	) as $kind => $row ) {
		foreach ( array( '7-1' => array( '7.1', true ), '7-1-no-page' => array( '7.1', false ), '6-5' => array( '6.5', true ) ) as $variant => $shape ) {
			$out[ $kind . '-' . $variant ] = array(
				'login'    => $row[0],
				'roles'    => $row[1],
				'version'  => $shape[0],
				'page'     => $shape[1],
				'settled'  => $row[2],
				'blogname' => 'WordPress Credits',
			);
		}
	}

	// A mentor who is also an institution's member is sent the mentor's invitation.
	$out['mentor-and-institution-7-1'] = array(
		'login'    => 'eli.example',
		'roles'    => array( $m, $i ),
		'version'  => '7.1',
		'page'     => true,
		'settled'  => false,
		'blogname' => 'WordPress Credits',
	);

	// A site name holding entities, which the subject reads back.
	$out['student-site-name-with-entities'] = array(
		'login'    => 'ada.example',
		'roles'    => array( $s ),
		'version'  => '7.1',
		'page'     => true,
		'settled'  => false,
		'blogname' => 'Ada &amp; Ben&#039;s Credits',
	);

	// The site's name as WordPress hands it over: decoded before the filter runs, so an "&" and a
	// "'" arrive as typed; and a word in braces in it, which is the site's name and nothing more.
	$out['student-site-name-decoded'] = array(
		'login'    => 'ada.example',
		'roles'    => array( $s ),
		'version'  => '7.1',
		'page'     => true,
		'settled'  => false,
		'blogname' => "Ada & Ben's {Credits}",
	);

	return $out;
}

/**
 * One case's invitation, through the real filter.
 *
 * @param array $case One of `cases()`.
 * @return array subject and message.
 */
function invitation( array $case ) {
	$page = $case['page'];

	WPCPM_Students_Dashboard::$url     = $page ? 'https://example.test/student-report-card/' : '';
	WPCPM_Mentors_Dashboard::$url      = $page ? 'https://example.test/mentor-report-card/' : '';
	WPCPM_Institutions_Dashboard::$url = $page ? 'https://example.test/institution-dashboard/' : '';
	WPCPM_Sponsors_Dashboard::$url     = $page ? 'https://example.test/sponsor-dashboard/' : '';
	WPCPM_Institution_Agreement::$settled = $case['settled'];

	$user  = new WP_User( 70, $case['login'], $case['roles'] );
	$email = WPCPM_Mail::welcome_email( core_email( $case['login'], $case['version'] ), $user, $case['blogname'] );

	return array(
		'subject' => $email['subject'],
		'message' => $email['message'],
	);
}

/**
 * The signature and footer an invitation ends with since 1.122.19, after the body as it was: a blank
 * line, the "-- " line, then the footer as the owner approved it, with no dashboard line, since an
 * invitation names the person's dashboard in its last lines already while its page is published and
 * no address appears twice; and no contact sentence, since Contact address in emails ships empty.
 *
 * @return string
 */
function footer_of() {
	return "\r\n-- \r\nWordPress Credits, WordPress Education\r\nThis email comes from the WordPress Credits program.\r\n";
}

/**
 * The 1.122.18 recording with the one sentence 1.122.20 words differently put in its new words.
 *
 * @param string $message The recorded body.
 * @param int    $count   How many times the sentence was there.
 * @return string
 */
function reworded( $message, &$count = 0 ) {
	return str_replace( 'A program manager then accepts it', 'An Administrator then accepts it', (string) $message, $count );
}

$fixture = __DIR__ . '/fixtures/invitations-1-122-18.json';

if ( in_array( '--record', $argv, true ) ) {
	// Made once, from 1.122.18: a recording made again would be made from whatever the code says
	// then, and would prove nothing.
	if ( file_exists( $fixture ) ) {
		fwrite( STDERR, "The recording exists and is never made twice.\n" );
		exit( 1 );
	}

	$recorded = array(
		'_comment' => 'What each invitation said in 1.122.18, recorded through WPCPM_Mail::welcome_email() by bin/test-invitation-text.php --record before the invitations moved into the template registry. Invented names, example.test addresses and a fake key. Never edit by hand: the registry is held to these bytes.',
		'cases'    => array(),
	);

	foreach ( cases() as $name => $case ) {
		$recorded['cases'][ $name ] = invitation( $case );
	}

	file_put_contents( $fixture, json_encode( $recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
	printf( "Recorded %d invitations in %s\n", count( $recorded['cases'] ), basename( $fixture ) );

	exit( 0 );
}

$recorded = is_file( $fixture ) ? json_decode( (string) file_get_contents( $fixture ), true ) : null;
$cases    = cases();

ck( 'the recording is there, and holds every case', is_array( $recorded ) && isset( $recorded['cases'] ) ? array_keys( $recorded['cases'] ) : null, array_keys( $cases ) );
ck( 'the recording is the one made from 1.122.18', is_file( $fixture ) ? hash_file( 'sha256', $fixture ) : null, 'd6174c852747000d5164f20d25cc42d4568ce442f16224913673eea18622e5f1' );

$reworded_in = array();

foreach ( $cases as $name => $case ) {
	$want = isset( $recorded['cases'][ $name ] ) ? $recorded['cases'][ $name ] : array( 'subject' => null, 'message' => null );
	$got  = invitation( $case );
	$hits = 0;
	$body = null === $want['message'] ? null : reworded( $want['message'], $hits );

	if ( $hits > 0 ) {
		$reworded_in[] = $name . ' x' . $hits;
	}

	ck( $name . ': the subject, byte for byte', $got['subject'], $want['subject'] );
	ck( $name . ': the body, byte for byte, then the signature and footer', $got['message'], null === $body ? null : $body . footer_of() );
}

ck( 'and the one sentence worded differently since 1.122.18 is in the institution\'s three invitations that ask for the Collaboration Agreement, once each, and in no other',
    $reworded_in, array( 'institution-7-1 x1', 'institution-7-1-no-page x1', 'institution-6-5 x1' ) );

// The site's name as core hands it over is read back once more, by the template alone, as every value
// is: until the fix wave of 1.122.23 the plugin read it back first, so a name holding a typed-out
// entity lost it ("R&amp;amp;D" arrived as "R&D").
$named = invitation( array_merge( $cases['student-7-1'], array( 'blogname' => 'R&amp;amp;D Credits' ) ) );
ck( 'and the site\'s name core hands over is read back once more, by the template alone', array( 0 === strpos( $named['subject'], '[R&amp;D Credits] ' ), false !== strpos( $named['message'], 'R&D Credits' ) ), array( true, false ) );

ck( 'and the recording holds no real key: every link carries the fixed fake', is_file( $fixture ) && preg_match_all( '/key=([^&\s]+)/', (string) file_get_contents( $fixture ), $keys ) > 0 ? array_values( array_unique( $keys[1] ) ) : array(), array( 'TEST-KEY-NOT-A-REAL-LINK' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
