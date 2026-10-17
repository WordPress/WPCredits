<?php
/**
 * The mentor application emails, word for word: what each of the five says in 1.122.31, as
 * `wp_mail()` is handed it.
 *
 * The five are new in 1.122.31, written from the program's Airtable automations (design of 16
 * October 2026, section 2.1). M2 adds the course's completion and the activation beside them, and
 * must change nothing the recipients of these five read but what its suite lists, as
 * bin/test-invitation-text.php lists the one change since its recording. So this suite recorded,
 * through the real senders and the real mail layer, every email of every case below into
 * bin/fixtures/mentor-applications-1-122-31.json: who it went to, the id it was logged under, its
 * subject, its body with the signature and footer, and its headers. From then on it sends the same
 * cases and compares them with the recording, byte for byte.
 *
 * The real senders: the form's submission (`WPCPM_Mentor_Application_Form::handle_submit()`), the
 * confirming link it sends, followed as an applicant follows it (`handle_verify()`), the queue's
 * three decisions that email and its Put back in the queue, which sends the acknowledgement a row
 * never had (`WPCPM_Mentor_Application_Queue`), and the Administrators' notice through
 * `WPCPM_Institutions::notify_managers()`. The real mail layer: `WPCPM_Mail`, the template
 * registry and the signature and footer. Only WordPress, the settings and the Administrator
 * Dashboard's address are stood in.
 *
 * The cases: a good application with nobody named to hear of it, and one with the setting naming an
 * address with no account and an Administrator's; one the comment disallowed list holds, with no
 * contact address set, then selected, so the selection's line naming the program's address is left
 * out and its request for a reply stays; one asked for more information and then selected; one
 * declined; one whose name was typed with quotes, an ampersand, a "<" and a letter past ASCII; and
 * one the honeypot filed as spam, put back in the queue, which is when it is acknowledged. Every
 * decision is pressed with a note, which no email carries. The names are invented and every address is example.test's.
 * The recording writes every address "at example.test" and the signature of every confirming link
 * as a fake, so it holds no address and no secret; what is sent now is written the same way before
 * it is compared.
 *
 * The recording is made once: `--record` refuses when the file is there, and the suite pins the
 * file's sha256, so a recording made again from later code can never stand in for 1.122.31's.
 *
 * Run from the plugin root:  php bin/test-mentor-applications-text.php
 * Record (once, against 1.122.31):  php bin/test-mentor-applications-text.php --record
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['sent']       = array();
$GLOBALS['settings']   = array();
$GLOBALS['disallowed'] = false;

function get_bloginfo( $show = 'name' ) {
	return 'WordPress Credits';
}
function wp_check_comment_disallowed_list( $author, $email, $url, $comment, $ip, $agent ) {
	return (bool) $GLOBALS['disallowed'];
}
/** What the mail layer hands WordPress, kept in the order it was handed. */
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['sent'][] = array(
		'to'      => $to,
		'context' => WPCPM_Mail::take_context(),
		'subject' => $subject,
		'body'    => $body,
		'headers' => array_values( (array) $headers ),
		'files'   => count( (array) $attachments ),
	);

	WPCPM_Mail::take_test();

	return true;
}

/** The settings the senders read, as a case sets them. */
class WPCPM_Settings {
	public static function get() {
		return $GLOBALS['settings'];
	}
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

/** The Administrator Dashboard, published: the page an Administrator's footer names. */
class WPCPM_Administrators_Dashboard {
	public static function page_url() {
		return 'https://example.test/administrator-dashboard/';
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-queue.php';

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

/** The note every decision is pressed with: the site's alone, which no email may carry. */
const NOTE = 'A note for the Administrators, NEVER-SENT, about this application.';

/**
 * The cases, each a world and what happens in it, in order: `apply` posts the form, its honeypot
 * filled where the case says so, `confirm` follows the confirming link of the acknowledgement just
 * sent, and `info`, `select`, `decline` and `reopen` press that decision on the one row, as the
 * first Administrator.
 *
 * @return array[]
 */
function cases() {
	return array(
		'applied'                        => array(
			'steps' => array( 'apply' ),
		),
		'applied-notice-to-the-list'     => array(
			'settings' => array( 'mentor_notify' => 'mentors-team@example.test, ida@example.test' ),
			'steps'    => array( 'apply' ),
		),
		'held-with-no-contact-address'   => array(
			'settings'   => array( 'mail_contact' => '' ),
			'disallowed' => true,
			'steps'      => array( 'apply', 'confirm', 'select' ),
		),
		'more-information-then-selected' => array(
			'steps' => array( 'apply', 'confirm', 'info', 'select' ),
		),
		'declined'                       => array(
			'steps' => array( 'apply', 'confirm', 'decline' ),
		),
		'a-name-as-typed'                => array(
			'answers' => array(
				'name'    => 'Zoë "Z" Example & Co <3',
				'profile' => '@zoe-example',
				'email'   => 'zoe@example.test',
			),
			'steps'   => array( 'apply', 'confirm', 'select' ),
		),
		'put-back-after-the-honeypot'    => array(
			'honeypot' => true,
			'steps'    => array( 'apply', 'reopen' ),
		),
	);
}

/** The answers a good application carries, as the form posts them. */
function answers( array $more = array() ) {
	return array_merge(
		array(
			'name'      => 'Ada Okonjo',
			'email'     => 'ada@example.test',
			'profile'   => 'https://profiles.wordpress.org/ada-example/',
			'country'   => 'Poland',
			'teacher'   => 'No',
			'languages' => array( 'Polish', 'English' ),
			'expertise' => array( 'Training', 'Polyglots' ),
			'hours'     => '3',
			'gpl'       => '1',
			'anything'  => 'I lead a meetup.',
			'consent'   => '1',
		),
		$more
	);
}

/** A dwell token half a minute old, signed the way the guard signs one for this form. */
function dwell_token() {
	$issued = time() - 30;
	$random = wp_generate_password( 12, false, false );

	return $issued . '.' . $random . '.' . substr( wp_hash( WPCPM_Mentor_Application_Form::DWELL_SCOPE . '|' . $issued . '|' . $random . '|' . wp_create_nonce( WPCPM_Mentor_Application_Form::ACTION_SUBMIT ) ), 0, 32 );
}

/**
 * A site where the form is open, with two Administrators, the course link set, and nothing sent.
 *
 * @param array $case The case.
 */
function fresh( array $case ) {
	$GLOBALS['opts']       = array( 'date_format' => 'F j, Y' );
	$GLOBALS['transients'] = array();
	$GLOBALS['posts']      = array();
	$GLOBALS['pmeta']      = array();
	$GLOBALS['users']      = array();
	$GLOBALS['umeta']      = array();
	$GLOBALS['slugs']      = array();
	$GLOBALS['sent']       = array();
	$GLOBALS['uid']        = 0;
	$GLOBALS['next_id']    = 500;
	$GLOBALS['policy']     = 'https://example.test/privacy/';
	$GLOBALS['disallowed'] = ! empty( $case['disallowed'] );
	$GLOBALS['settings']   = array_merge(
		array(
			'mentor_applications_enabled' => true,
			'application_trusted_proxy'   => '',
			'mail_contact'                => 'programs@example.test',
			'mentor_notify'               => '',
			'mentor_course_link'          => 'https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE',
			'checker_course_title'        => 'WordPress Credits Mentor\'s Course',
		),
		isset( $case['settings'] ) ? $case['settings'] : array()
	);

	$_POST                      = array();
	$_GET                       = array();
	$_REQUEST                   = array();
	$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test)';

	$page                 = new WP_Post();
	$page->ID             = 42;
	$page->post_type      = 'page';
	$page->post_status    = 'publish';
	$GLOBALS['posts'][42] = $page;
	$GLOBALS['slugs'][42] = 'become-a-mentor';
	$GLOBALS['opts'][ WPCPM_Mentor_Application_Form::OPT_PAGE ] = 42;

	$policy                    = new WP_Post();
	$policy->ID                = 43;
	$policy->post_type         = 'page';
	$policy->post_status       = 'publish';
	$policy->post_modified_gmt = '2026-08-20 11:30:00';
	$GLOBALS['posts'][43]                          = $policy;
	$GLOBALS['opts']['wp_page_for_privacy_policy'] = 43;

	$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ]       = array(
		'v'    => WPCPM_Countries::VERSION,
		'read' => 1,
		'rows' => array(
			'recCOUNTRY0000001' => array( 'name' => 'Poland', 'manager' => '', 'email' => '', 'calendly' => '' ),
		),
	);
	$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ] = array(
		'v'     => WPCPM_Mentors_Sync::LOOKUPS_VERSION,
		'teams' => array(
			'recTEAM0000000001' => 'Polyglots',
			'recTEAM0000000003' => 'Training',
		),
	);

	an_account( 40, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );
	an_account( 41, 'Ben Admin', 'ben@example.test', array( 'administrator' ) );
	$GLOBALS['manage'] = array( 40, 41 );
}

/**
 * One step of a case, as the request that takes it.
 *
 * @param string $step The step.
 * @param array  $case The case.
 */
function take_step( $step, array $case ) {
	if ( 'apply' === $step ) {
		$GLOBALS['uid'] = 0;
		$_POST          = array(
			'action'                                     => WPCPM_Mentor_Application_Form::ACTION_SUBMIT,
			'_wpnonce'                                   => wp_create_nonce( WPCPM_Mentor_Application_Form::ACTION_SUBMIT ),
			WPCPM_Mentor_Application_Form::FIELD_ANSWERS => wp_slash( answers( isset( $case['answers'] ) ? $case['answers'] : array() ) ),
			WPCPM_Mentor_Application_Form::TOKEN_FIELD   => dwell_token(),
			WPCPM_Mentor_Application_Form::HONEYPOT      => empty( $case['honeypot'] ) ? '' : 'https://spam.example.test/',
		);
		$_REQUEST       = $_POST;

		run( array( 'WPCPM_Mentor_Application_Form', 'handle_submit' ) );
		return;
	}

	if ( 'confirm' === $step ) {
		$link = '';

		foreach ( $GLOBALS['sent'] as $mail ) {
			if ( 'mentor-application-received' === $mail['context'] && preg_match( '#https://example\.test/wp-admin/admin-post\.php\?\S+#', $mail['body'], $found ) ) {
				$link = $found[0];
			}
		}

		parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );

		$GLOBALS['uid'] = 0;
		$_POST          = array();
		$_GET           = $query;
		$_REQUEST       = $query;

		run( array( 'WPCPM_Mentor_Application_Form', 'handle_verify' ) );
		return;
	}

	$decisions = array(
		'info'    => array( WPCPM_Mentor_Application_Queue::ACTION_INFO, 'handle_info' ),
		'select'  => array( WPCPM_Mentor_Application_Queue::ACTION_SELECT, 'handle_select' ),
		'decline' => array( WPCPM_Mentor_Application_Queue::ACTION_DECLINE, 'handle_decline' ),
		'reopen'  => array( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, 'handle_reopen' ),
	);
	$rows      = WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::states() );
	$id        = isset( $rows[0] ) ? (int) $rows[0]->ID : 0;

	$GLOBALS['uid'] = 40;
	$_GET           = array();
	$_POST          = array(
		'action'                                          => $decisions[ $step ][0],
		'_wpnonce'                                        => wp_create_nonce( $decisions[ $step ][0] . '_' . $id ),
		WPCPM_Mentor_Application_Queue::FIELD_APPLICATION => (string) $id,
		WPCPM_Mentor_Application_Queue::FIELD_NOTE        => NOTE,
	);
	$_REQUEST       = $_POST;

	run( array( 'WPCPM_Mentor_Application_Queue', $decisions[ $step ][1] ) );
}

/**
 * Every email a case sends, in order, as the recording holds them.
 *
 * @param array $case The case.
 * @return array[]
 */
function emails_of( array $case ) {
	fresh( $case );

	foreach ( $case['steps'] as $step ) {
		take_step( $step, $case );
	}

	return array_map( 'recorded_form', $GLOBALS['sent'] );
}

/**
 * An email as the recording holds it: every address written "at example.test", and the signature
 * of a confirming link written as a fake.
 *
 * @param array $mail What `wp_mail()` was handed.
 * @return array
 */
function recorded_form( array $mail ) {
	$plain = static function ( $text ) {
		$text = preg_replace( '/([A-Za-z0-9._%+-]+)@example\.test/', '$1 at example.test', (string) $text );

		return preg_replace( '/([?&]t=)[A-Za-z0-9]+/', '$1EXAMPLE-NOT-A-REAL-SIGNATURE', $text );
	};

	$mail['to']      = $plain( $mail['to'] );
	$mail['subject'] = $plain( $mail['subject'] );
	$mail['body']    = $plain( $mail['body'] );
	$mail['headers'] = array_map( $plain, $mail['headers'] );

	return $mail;
}

$fixture = __DIR__ . '/fixtures/mentor-applications-1-122-31.json';

if ( in_array( '--record', $argv, true ) ) {
	// Made once, from 1.122.31: a recording made again would be made from whatever the code says
	// then, and would prove nothing.
	if ( file_exists( $fixture ) ) {
		fwrite( STDERR, "The recording exists and is never made twice.\n" );
		exit( 1 );
	}

	$recorded = array(
		'_comment' => 'What each Mentor applications email said in 1.122.31, recorded through the real senders and WPCPM_Mail by bin/test-mentor-applications-text.php --record. Invented names, every address written "at example.test", and the signature of every confirming link a fake. Never edit by hand: a later release is held to these bytes, but for the changes its suite lists.',
		'cases'    => array(),
	);

	foreach ( cases() as $name => $case ) {
		$recorded['cases'][ $name ] = emails_of( $case );
	}

	// A text JSON cannot hold stops the recording here, before a file is written.
	file_put_contents( $fixture, json_encode( $recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . "\n" );
	printf( "Recorded %d cases, %d emails, in %s\n", count( $recorded['cases'] ), array_sum( array_map( 'count', $recorded['cases'] ) ), basename( $fixture ) );

	exit( 0 );
}

echo "=== The recording ===\n";

$recorded = is_file( $fixture ) ? json_decode( (string) file_get_contents( $fixture ), true ) : null;

ck( 'the recording is there, and holds every case', is_array( $recorded ) && isset( $recorded['cases'] ) ? array_keys( $recorded['cases'] ) : null, array_keys( cases() ) );
ck( 'the recording is the one made from 1.122.31', is_file( $fixture ) ? hash_file( 'sha256', $fixture ) : null, '44d03b315434a0d1ce1624fd159aa89fbdc63db5923e3584ba1539eec4fcbf3d' );

echo "\n=== Every email of every case, as recorded ===\n";

$as_sent = array();

foreach ( cases() as $name => $case ) {
	$want             = isset( $recorded['cases'][ $name ] ) ? $recorded['cases'][ $name ] : null;
	$got              = emails_of( $case );
	$as_sent[ $name ] = $got;

	ck( $name . ': the same emails, to the same people, in the same order', array_column( $got, 'to' ), null === $want ? null : array_column( $want, 'to' ) );

	foreach ( (array) $want as $n => $mail ) {
		$sent = isset( $got[ $n ] ) ? $got[ $n ] : array_fill_keys( array_keys( $mail ), null );

		ck( $name . ' #' . $n . ': logged under its own id', $sent['context'], $mail['context'] );
		ck( $name . ' #' . $n . ': the subject, byte for byte', $sent['subject'], $mail['subject'] );
		ck( $name . ' #' . $n . ': the body, byte for byte, the signature and footer included', $sent['body'], $mail['body'] );
		ck( $name . ' #' . $n . ': the headers, byte for byte, and no file attached', array( $sent['headers'], $sent['files'] ), array( $mail['headers'], 0 ) );
	}
}

echo "\n=== What the cases say, apart from the recording ===\n";

ck(
	'each case sends what its steps say: the acknowledgement for any row that is not spam, and for one put back in the queue that never had it, the notice for a new row alone, to each Administrator or to each address the setting names, and each decision\'s email',
	array_map(
		static function ( $mails ) {
			return array_map(
				static function ( $mail ) {
					return $mail['context'] . ' > ' . $mail['to'];
				},
				$mails
			);
		},
		$as_sent
	),
	array(
		'applied'                        => array( 'mentor-application-received > ada at example.test', 'mentor-application-notice > ida at example.test', 'mentor-application-notice > ben at example.test' ),
		'applied-notice-to-the-list'     => array( 'mentor-application-received > ada at example.test', 'mentor-application-notice > mentors-team at example.test', 'mentor-application-notice > ida at example.test' ),
		'held-with-no-contact-address'   => array( 'mentor-application-received > ada at example.test', 'mentor-selected > ada at example.test' ),
		'more-information-then-selected' => array( 'mentor-application-received > ada at example.test', 'mentor-application-notice > ida at example.test', 'mentor-application-notice > ben at example.test', 'mentor-more-information > ada at example.test', 'mentor-selected > ada at example.test' ),
		'declined'                       => array( 'mentor-application-received > ada at example.test', 'mentor-application-notice > ida at example.test', 'mentor-application-notice > ben at example.test', 'mentor-declined > ada at example.test' ),
		'a-name-as-typed'                => array( 'mentor-application-received > zoe at example.test', 'mentor-application-notice > ida at example.test', 'mentor-application-notice > ben at example.test', 'mentor-selected > zoe at example.test' ),
		'put-back-after-the-honeypot'    => array( 'mentor-application-received > ada at example.test' ),
	)
);

$noted = array();

foreach ( $as_sent as $name => $mails ) {
	foreach ( $mails as $mail ) {
		if ( false !== strpos( wp_json_encode( $mail ), 'NEVER-SENT' ) ) {
			$noted[] = $name . ': ' . $mail['context'];
		}
	}
}

ck( 'a decision\'s note is the site\'s alone: no email carries it', $noted, array() );

$text = is_file( $fixture ) ? (string) file_get_contents( $fixture ) : '';
preg_match_all( '/[?&]t=([A-Za-z0-9-]+)/', $text, $tokens );
ck(
	'and the recording holds no address, and no secret but a fake one',
	is_file( $fixture ) ? array( preg_match( '/[A-Za-z0-9._%+-]*@[A-Za-z0-9-]+\.[A-Za-z]{2,}/', $text ), array_values( array_unique( $tokens[1] ) ) ) : null,
	array( 0, array( 'EXAMPLE-NOT-A-REAL-SIGNATURE' ) )
);
ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
