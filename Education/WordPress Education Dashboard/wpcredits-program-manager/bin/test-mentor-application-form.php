<?php
/**
 * The mentor application form (1.122.31): `WPCPM_Mentor_Application_Form`.
 *
 * Design of 16 October 2026, section 4. What each block pins, and why:
 *
 * - **The questions are the Airtable form's eleven, in its order and with its labels** (section 2.3,
 *   the form as read on 16 October 2026), kept under site keys, never Airtable columns; the
 *   compliance declaration is the form's statement word for word, with its two links; the
 *   contribution areas are the one optional choice; the countries and the contribution teams are
 *   the site's own lists, the languages a fixed list in code.
 * - **Each answer is cleaned by its type.** The WordPress.org profile is a username or an address
 *   that names one on WordPress.org, reduced by the mentors sync's own rule, and anything else is
 *   refused, a host on its own and a page that is nobody's profile included; the hours take a
 *   decimal, held to one place; the free text refuses what the cleaner would take out of it, a "<"
 *   or a "%" alike; a country, a language or a team the lists do not offer is never stored.
 * - **A refused free text and profile come back as typed.** In their boxes, escaped and cut to the
 *   length of the box, from the stash alone: a row filed as spam stores none of it.
 * - **The sponsor form's order of checks**, read off the source in that order, and each exercised at
 *   its boundary with real submissions: the per-actor ceiling (counting posts whose nonce failed),
 *   the nonce, the switch, the honeypot (which files a row as spam before requiredness is asked), the
 *   dwell token, consent, requiredness, the site-wide degrade, content scoring.
 * - **Duplicates hold, read from the site alone**: another application in the queue or waiting for
 *   the course, by address or username; a mentor account of any standing by address, username or
 *   the profile the sync keeps; a mentor the last sync read. A closed application is no duplicate.
 * - **Two emails, gated as the sponsor form gates them**: the acknowledgement, with the confirming
 *   link and the program's Reply-To, for any row that is not spam; the notice for a new row only,
 *   through the `mentor_notify` setting. An acknowledgement the mail layer cannot hand off is
 *   recorded on the row as a signal, and the row is stored as it would have been.
 * - **The confirming link**: a bad signature and a page are one answer; the first visit confirms,
 *   the second says it was confirmed already.
 * - **The page**: closed for the public, with one sentence for an Administrator, while the switch is
 *   off, the policy missing or either list empty; the form when open, the country as the one field
 *   for a long list; the thank-you with the reference, in the owner's words when the block or the
 *   shortcode gives them; the page made with the Airtable form's introduction, or adopted as it is,
 *   never gated.
 *
 * Run from the plugin root:  php bin/test-mentor-application-form.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['mail']       = array();
$GLOBALS['notices']    = array();
$GLOBALS['settings']   = array();
$GLOBALS['disallowed'] = false;
$GLOBALS['enqueued']   = array();
$GLOBALS['registered'] = array();

function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$out = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, (array) $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}
function wp_style_is( $handle, $list = 'enqueued' ) {
	return isset( $GLOBALS['registered'][ $handle ] );
}
function wp_script_is( $handle, $list = 'enqueued' ) {
	return isset( $GLOBALS['registered'][ $handle ] );
}
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {
	$GLOBALS['registered'][ $handle ] = $src;
}
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) {
	$GLOBALS['registered'][ $handle ] = $src;
}
function wp_enqueue_style( $handle ) {
	$GLOBALS['enqueued'][] = $handle;
}
function wp_enqueue_script( $handle ) {
	$GLOBALS['enqueued'][] = $handle;
}
function wp_check_comment_disallowed_list( $author, $email, $url, $comment, $ip, $agent ) {
	return (bool) $GLOBALS['disallowed'];
}

/** The settings the form reads, as a check sets them. */
class WPCPM_Settings {
	public static function get() {
		return $GLOBALS['settings'];
	}
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

/** The mail layer at its contract: what a builder returns, to whom and under which id, or a refusal. */
class WPCPM_Mail {
	public static function send_to( $email, $context, $build, $locale = '' ) {
		if ( ! is_email( $email ) || ! is_callable( $build ) || ! empty( $GLOBALS['mail_fails'] ) ) {
			return false;
		}
		$mail              = (array) call_user_func( $build, $email );
		$mail['to']        = $email;
		$mail['context']   = $context;
		$GLOBALS['mail'][] = $mail;
		return true;
	}
	public static function site_name() {
		return 'WordPress Credits';
	}
}

/** Who the Administrators' notice goes to is the institutions module's; here, which setting it read. */
class WPCPM_Institutions {
	public static function notify_managers( $context, $build, $setting_key = 'agreement_notify' ) {
		$mail                 = (array) call_user_func( $build, null );
		$mail['context']      = $context;
		$mail['setting']      = $setting_key;
		$GLOBALS['notices'][] = $mail;
		return 1;
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';

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
 * A site where the form is drawn: applications on, the page, the two lists, a privacy page of the
 * site's own, which the form no longer links (1.123.24), and nobody's mail sent yet.
 */
function reset_world() {
	$GLOBALS['opts']       = array();
	$GLOBALS['transients'] = array();
	$GLOBALS['posts']      = array();
	$GLOBALS['pmeta']      = array();
	$GLOBALS['users']      = array();
	$GLOBALS['umeta']      = array();
	$GLOBALS['slugs']      = array();
	$GLOBALS['mail']       = array();
	$GLOBALS['mail_fails'] = false;
	$GLOBALS['notices']    = array();
	$GLOBALS['uid']        = 0;
	$GLOBALS['manage']     = array();
	$GLOBALS['policy']     = 'https://example.test/privacy/';
	$GLOBALS['disallowed'] = false;
	$GLOBALS['next_id']    = 500;
	$GLOBALS['settings']   = array(
		'mentor_applications_enabled' => true,
		'application_trusted_proxy'   => '',
		'mail_contact'                => 'programs@example.test',
	);

	$_POST                      = array();
	$_GET                       = array();
	$_REQUEST                   = array();
	$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test)';

	$page              = new WP_Post();
	$page->ID          = 42;
	$page->post_type   = 'page';
	$page->post_status = 'publish';
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

	// The site's own lists: the countries the institutions sync read, the teams the mentors sync read.
	$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ]       = array(
		'v'    => WPCPM_Countries::VERSION,
		'read' => 1,
		'rows' => array(
			'recCOUNTRY0000001' => array( 'name' => 'Poland', 'manager' => '', 'email' => '', 'calendly' => '' ),
			'recCOUNTRY0000002' => array( 'name' => 'Costa Rica', 'manager' => '', 'email' => '', 'calendly' => '' ),
		),
	);
	$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ] = array(
		'v'     => WPCPM_Mentors_Sync::LOOKUPS_VERSION,
		'teams' => array(
			'recTEAM0000000001' => 'Polyglots',
			'recTEAM0000000002' => 'Core',
			'recTEAM0000000003' => 'Training',
		),
	);
}

/** A dwell token of a given age, signed the way the guard signs one for this form. */
function dwell_token( $age = 30 ) {
	$issued = time() - (int) $age;
	$random = wp_generate_password( 12, false, false );

	return $issued . '.' . $random . '.' . substr( wp_hash( WPCPM_Mentor_Application_Form::DWELL_SCOPE . '|' . $issued . '|' . $random . '|' . wp_create_nonce( WPCPM_Mentor_Application_Form::ACTION_SUBMIT ) ), 0, 32 );
}

/** The answers a good application carries, as the form posts them; null takes one out. */
function answers( array $more = array() ) {
	$values = array_merge(
		array(
			'name'      => 'Ada Okonjo',
			'email'     => 'ada@example.test',
			'profile'   => 'https://profiles.wordpress.org/ada-example/',
			'country'   => 'Poland',
			'teacher'   => 'No',
			'languages' => array( 'Polish', 'English' ),
			'expertise' => array( 'Training', 'Polyglots' ),
			'hours'     => '2,5',
			'gpl'       => '1',
			'anything'  => 'I lead a meetup.',
			'consent'   => '1',
		),
		$more
	);

	return array_filter(
		$values,
		function ( $value ) {
			return null !== $value;
		}
	);
}

/**
 * Post the form once and answer with where it landed and what the stash holds.
 *
 * @param array $answers What to post.
 * @param array $extra   `token`, `honeypot`, `nonce`, `ip`.
 * @return array `outcome`, `stash`, `url`.
 */
function submit( array $answers, array $extra = array() ) {
	$_POST = array(
		'action'                                       => WPCPM_Mentor_Application_Form::ACTION_SUBMIT,
		'_wpnonce'                                     => array_key_exists( 'nonce', $extra ) ? $extra['nonce'] : wp_create_nonce( WPCPM_Mentor_Application_Form::ACTION_SUBMIT ),
		WPCPM_Mentor_Application_Form::FIELD_ANSWERS   => wp_slash( $answers ),
		WPCPM_Mentor_Application_Form::TOKEN_FIELD     => array_key_exists( 'token', $extra ) ? $extra['token'] : dwell_token(),
		WPCPM_Mentor_Application_Form::HONEYPOT        => isset( $extra['honeypot'] ) ? $extra['honeypot'] : '',
	);

	if ( isset( $extra['ip'] ) ) {
		$_SERVER['REMOTE_ADDR'] = $extra['ip'];
	}

	$out = run( array( 'WPCPM_Mentor_Application_Form', 'handle_submit' ) );

	return landed( str_replace( 'redirect: ', '', $out['stop'] ) );
}

/** Read a redirect the way the page behind it would. */
function landed( $url ) {
	$stash = array();

	if ( preg_match( '/[?&]' . WPCPM_Mentor_Application_Form::QUERY_STASH . '=([^&]+)/', $url, $id ) ) {
		$stash = (array) get_transient( WPCPM_Mentor_Application_Form::TRANSIENT_PREFIX . $id[1] );
	} elseif ( preg_match( '/[?&]' . WPCPM_Mentor_Application_Form::QUERY_OUTCOME . '=([^&]+)/', $url, $said ) ) {
		$stash = array( 'outcome' => $said[1] );
	}

	return array(
		'outcome' => isset( $stash['outcome'] ) ? $stash['outcome'] : '',
		'stash'   => $stash,
		'url'     => $url,
	);
}

/** Every application row, oldest first. */
function rows() {
	return WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::states() );
}

/** The one row this check made, or null. */
function only_row() {
	$rows = rows();

	return 1 === count( $rows ) ? $rows[0] : null;
}

/** One method of a class's source, up to the end of its body. */
function method_body( $src, $name ) {
	$body = substr( $src, (int) strpos( $src, 'function ' . $name . '(' ) );

	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}

echo "=== The hooks ===\n";

reset_world();
$GLOBALS['hooks'] = array();
WPCPM_Mentor_Application_Form::init();
ck(
	'the form posts, and the confirming link lands, for visitors signed in and signed out alike',
	array( isset( $GLOBALS['hooks']['admin_post_nopriv_wpcpm_mentor_apply'] ), isset( $GLOBALS['hooks']['admin_post_wpcpm_mentor_apply'] ), isset( $GLOBALS['hooks']['admin_post_nopriv_wpcpm_mentor_apply_verify'] ), isset( $GLOBALS['hooks']['admin_post_wpcpm_mentor_apply_verify'] ), isset( $GLOBALS['hooks']['template_redirect'] ) ),
	array( true, true, true, true, true )
);

echo "\n=== The questions ===\n";

ck(
	'the questions are the Airtable form\'s eleven, in its order, with its labels, under the site\'s own keys, and no sponsorship question',
	array_map(
		function ( $spec ) {
			return $spec['label'];
		},
		WPCPM_Mentor_Application_Form::fields()
	),
	array(
		'name'      => 'Full name',
		'email'     => 'Email',
		'profile'   => 'WordPress.org profile URL',
		'country'   => 'Country',
		'teacher'   => 'Are you a teacher from a WordPress Credits participating institution?',
		'languages' => 'Languages in which you could mentor students',
		'expertise' => 'In which WordPress contribution areas do you have expertise?',
		'hours'     => 'Available hours per week to mentor students',
		'gpl'       => 'Compliance declaration',
		'anything'  => 'Anything else you\'d like us to know?',
		'consent'   => 'Privacy policy',
	)
);
ck(
	'the compliance declaration\'s box carries the form\'s statement word for word, its two links to the license and the trademark policy, and nothing else',
	array( wp_strip_all_tags( WPCPM_Mentor_Application_Form::fields()['gpl']['box'] ), preg_match_all( '#<a href="([^"]+)">([^<]+)</a>#', WPCPM_Mentor_Application_Form::fields()['gpl']['box'], $declared, PREG_SET_ORDER ), array_map( function ( $link ) { return $link[2] . ' ' . $link[1]; }, $declared ) ),
	array( 'I confirm that I comply with the GPL and do not violate, endorse, or work for companies that violate the WordPress trademark, misuse the WordPress name or logo, or distribute non-GPL-compliant derivatives of WordPress.', 2, array( 'GPL https://wordpress.org/about/license/', 'WordPress trademark https://wordpressfoundation.org/trademark-policy/' ) )
);
ck(
	'the required ones: everything but the contribution areas and the free text',
	array_keys( array_filter( WPCPM_Mentor_Application_Form::fields(), function ( $spec ) { return ! empty( $spec['required'] ); } ) ),
	array( 'name', 'email', 'profile', 'country', 'teacher', 'languages', 'hours', 'gpl', 'consent' )
);
ck(
	'the help is the form\'s: where a profile is, as many languages and areas as apply, none for somebody new to contributing',
	array( WPCPM_Mentor_Application_Form::fields()['profile']['help'], WPCPM_Mentor_Application_Form::fields()['languages']['help'], WPCPM_Mentor_Application_Form::fields()['expertise']['help'] ),
	array( 'Your profile\'s address, which is https://profiles.wordpress.org/ followed by your username, or just your username. If you do not have a profile yet, create one at https://login.wordpress.org/register.', 'Add as many as apply.', 'This refers to your experience as a WordPress.org contributor. Add as many as apply. If you are new to contributing, select nothing.' )
);
ck( 'the countries are the site\'s own list, by name, A to Z', WPCPM_Mentor_Application_Form::countries(), array( 'Costa Rica', 'Poland' ) );
ck( 'and so are the contribution teams', WPCPM_Mentor_Application_Form::teams(), array( 'Core', 'Polyglots', 'Training' ) );
ck( 'the languages are a fixed list, A to Z, each once', array( count( WPCPM_Mentor_Application_Form::languages() ), WPCPM_Mentor_Application_Form::languages() === array_values( array_unique( WPCPM_Mentor_Application_Form::languages() ) ), array_slice( WPCPM_Mentor_Application_Form::languages(), 0, 3 ) ), array( 38, true, array( 'Bengali', 'Catalan', 'Croatian' ) ) );

echo "\n=== Each answer, cleaned ===\n";

$profile = array();
foreach ( array( '@ada-example', 'ada-example', 'https://profiles.wordpress.org/ada-example/', 'HTTPS://PROFILES.WORDPRESS.ORG/Ada-Example/profile/', ' ada-example ', 'profiles.wordpress.org/ada-example', 'https://wordpress.org/support/users/ada-example/', 'https://profiles.wordpress.org/ada-example', 'https://profiles.wordpress.org/ada-example?locale=en', 'https://wordpress.org/support/users/ada-example', 'www.wordpress.org/support/users/Ada-Example/topics/' ) as $typed ) {
	$profile[ $typed ] = WPCPM_Mentor_Application_Form::clean( 'profile', $typed )['value'];
}
ck( 'a profile is a username, with or without its @, or an address that names one on WordPress.org (the first segment on profiles.wordpress.org, /support/users/<username> on wordpress.org, more path after it allowed), and each is kept as the username', $profile, array_fill_keys( array_keys( $profile ), 'ada-example' ) );
$refused = array();
foreach ( array( 'Ada Example', 'https://github.com/ada-example', 'https://www.linkedin.com/in/ada-example/', 'https://example.test/about', 'my site: ada.example.test', 'https://wordpress.org/', '@@ada-example' ) as $typed ) {
	$refused[ $typed ] = WPCPM_Mentor_Application_Form::clean( 'profile', $typed )['problem'];
}
ck( 'anything else is refused, a name, a link to another site, an address on WordPress.org that names nobody, and a second @', $refused, array_fill_keys( array_keys( $refused ), 'bad_profile' ) );
$hosts = array();
foreach ( array( 'https://wordpress.org', 'wordpress.org', 'wordpress.org?x=1', 'www.wordpress.org', 'https://profiles.wordpress.org', 'profiles.wordpress.org', '.', '-', '..', '_', '@.' ) as $typed ) {
	$hosts[ $typed ] = WPCPM_Mentor_Application_Form::clean( 'profile', $typed )['problem'];
}
ck( 'a host on its own, and text with no letter or digit in it, have the shape of a username and are refused, kept as no username', $hosts, array_fill_keys( array_keys( $hosts ), 'bad_profile' ) );
$nobody = array();
foreach ( array( 'https://profiles.wordpress.org/', 'https://profiles.wordpress.org/ada-example extra/', 'https://wordpress.org/plugins/akismet/', 'https://wordpress.org/support/users/', 'https://wordpress.org/support/forums/', 'https://wordpress.org/support/plugin/akismet/', 'https://wordpress.org/support/topic/hello-world/', 'https://www.wordpress.org/about/' ) as $typed ) {
	$nobody[ $typed ] = WPCPM_Mentor_Application_Form::clean( 'profile', $typed )['problem'];
}
ck( 'and so is any other path on those hosts, which names nobody: no username, words after it that are no path, a plugin, a forum, a topic', $nobody, array_fill_keys( array_keys( $nobody ), 'bad_profile' ) );
$components = array();
foreach ( array( 'profile', 'profiles', 'activity', 'badges', 'notifications', 'messages' ) as $component ) {
	$components[ $component ] = WPCPM_Mentor_Application_Form::clean( 'profile', 'https://profiles.wordpress.org/' . $component . '/' )['problem'];
}
ck( 'and so is a component of a profile on its own, which the sync leaves out of an address', $components, array_fill_keys( array_keys( $components ), 'bad_profile' ) );
$segments = array();
foreach ( array( 'https://profiles.wordpress.org/adaexample/profile/', 'https://profiles.wordpress.org/adaexample/activity/forums/', 'https://wordpress.org/support/users/adaexample/topics/', 'https://www.wordpress.org/support/users/adaexample/replies/page/2/' ) as $typed ) {
	$segments[ $typed ] = WPCPM_Mentor_Application_Form::clean( 'profile', $typed )['value'];
}
ck( 'the username is the first path segment on profiles.wordpress.org and the one after /support/users/ on wordpress.org, whatever path follows it', $segments, array_fill_keys( array_keys( $segments ), 'adaexample' ) );
ck(
	'an empty one is left for requiredness, an array refused, and the refusal says what to give',
	array( WPCPM_Mentor_Application_Form::clean( 'profile', '' ), WPCPM_Mentor_Application_Form::clean( 'profile', array( 'x' ) )['problem'], submit( answers( array( 'profile' => 'Ada Example' ) ) )['stash']['problems']['profile'] ?? null ),
	array( array( 'ok' => true, 'value' => '', 'problem' => '' ), 'not_scalar', 'bad_profile' )
);
ck(
	'a country on the list is kept by name, one off it refused',
	array( WPCPM_Mentor_Application_Form::clean( 'country', 'Poland' )['value'], WPCPM_Mentor_Application_Form::clean( 'country', 'Atlantis' )['problem'], WPCPM_Mentor_Application_Form::clean( 'country', 'recCOUNTRY0000001' )['problem'] ),
	array( 'Poland', 'bad_choice', 'bad_choice' )
);
ck(
	'languages and teams keep the listed ones, each once, in the list\'s order, and drop the rest',
	array( WPCPM_Mentor_Application_Form::clean( 'languages', array( 'Polish', 'Klingon', 'English', 'Polish' ) )['value'], WPCPM_Mentor_Application_Form::clean( 'expertise', 'Core' )['value'], WPCPM_Mentor_Application_Form::clean( 'expertise', array( array( 'Core' ) ) )['value'] ),
	array( array( 'English', 'Polish' ), array( 'Core' ), array() )
);
$hours = array();
foreach ( array( '2.5', '2.25', '3', '3.0', '2,5', '40', '1' ) as $typed ) {
	$hours[ $typed ] = WPCPM_Mentor_Application_Form::clean( 'hours', $typed )['value'];
}
ck( 'hours take a decimal, held to one place, a comma read as the point, and kept as the number they then read', $hours, array( '2.5' => '2.5', '2.25' => '2.3', '3' => '3', '3.0' => '3', '2,5' => '2.5', '40' => '40', '1' => '1' ) );
ck(
	'and are held between 1 and 40, a word refused, an empty answer left for requiredness',
	array( WPCPM_Mentor_Application_Form::clean( 'hours', '0.5' )['problem'], WPCPM_Mentor_Application_Form::clean( 'hours', '40.1' )['problem'], WPCPM_Mentor_Application_Form::clean( 'hours', 'two' )['problem'], WPCPM_Mentor_Application_Form::clean( 'hours', '' )['value'], WPCPM_Mentor_Application_Form::clean( 'hours', array( '2' ) )['problem'] ),
	array( 'below_min', 'above_max', 'not_a_number', '', 'not_scalar' )
);
ck(
	'the teacher question takes Yes or No and nothing else; the declaration is ticked by the value its box carries alone',
	array( WPCPM_Mentor_Application_Form::clean( 'teacher', 'Yes' )['value'], WPCPM_Mentor_Application_Form::clean( 'teacher', 'maybe' )['problem'], WPCPM_Mentor_Application_Form::clean( 'gpl', '1' )['value'], WPCPM_Mentor_Application_Form::clean( 'gpl', 'yes' )['value'] ),
	array( 'Yes', 'bad_choice', true, false )
);
ck(
	'the free text refuses what the cleaner would take out of it, and keeps a lone "<", quotes, an ampersand and an apostrophe',
	array( WPCPM_Mentor_Application_Form::clean( 'anything', 'I wrote the <Navigation> block docs.' )['problem'], WPCPM_Mentor_Application_Form::clean( 'anything', 'a < b' )['ok'], WPCPM_Mentor_Application_Form::clean( 'anything', 'Tom & Jerry\'s "quote"' )['value'] ),
	array( 'markup', true, 'Tom & Jerry\'s "quote"' )
);
ck(
	'a "%" and two hexadecimal digits are refused as well, since the cleaner would take them for a URL octet, and a "%" before anything else is kept',
	array( WPCPM_Mentor_Application_Form::clean( 'anything', 'I get 10%cashback' )['problem'], WPCPM_Mentor_Application_Form::clean( 'anything', 'Save 50% now' )['ok'] ),
	array( 'markup', true )
);
ck(
	'and an empty choice of contribution areas is an answer, not a blank',
	array( WPCPM_Mentor_Application_Form::clean( 'expertise', array() )['value'], submit( answers( array( 'expertise' => null ) ) )['outcome'] ),
	array( array(), 'sent' )
);
ck( 'a key nobody asks about is refused', WPCPM_Mentor_Application_Form::clean( 'role', 'administrator' )['problem'], 'unknown_field' );

echo "\n=== The checks, in the sponsor form's order ===\n";

// The order the design fixes, read off the source: each check must come before the next, so a
// reordering that every boundary below would survive fails here.
$submit_path = method_body( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php' ), 'handle_submit' );
$offsets     = array(
	'actor'      => strpos( $submit_path, 'WPCPM_Form_Guard::claim_actor(' ),
	'nonce'      => strpos( $submit_path, 'wp_verify_nonce(' ),
	'closed'     => strpos( $submit_path, 'self::closed_reason()' ),
	'honeypot'   => strpos( $submit_path, 'WPCPM_Form_Guard::honeypot_filled(' ),
	'dwell'      => strpos( $submit_path, 'WPCPM_Form_Guard::check_token(' ),
	'consent'    => strpos( $submit_path, "self::clean( 'consent'" ),
	'answers'    => strpos( $submit_path, '$cleaned  = self::clean_all(' ),
	'required'   => strpos( $submit_path, 'self::add_required(' ),
	'site'       => strpos( $submit_path, 'WPCPM_Form_Guard::claim_site(' ),
	'scoring'    => strpos( $submit_path, 'self::score(' ),
	'duplicates' => strpos( $submit_path, 'self::duplicate_signals(' ),
	'store'      => strpos( $submit_path, 'WPCPM_Mentor_Application::store(' ),
	'mail'       => strpos( $submit_path, 'self::mail_applicant(' ),
	'notice'     => strpos( $submit_path, 'self::mail_administrators(' ),
);
$in_order    = $offsets;
asort( $in_order );
ck( 'the submit path reads in the order the design fixes, every one of its fourteen steps there', array( array_keys( $in_order ), in_array( false, $offsets, true ) ), array( array_keys( $offsets ), false ) );

reset_world();
for ( $i = 0; $i < WPCPM_Form_Guard::PER_HOUR; $i++ ) {
	submit( answers(), array( 'nonce' => 'nonce-stale' ) );
}
$busy = submit( answers( array( 'email' => 'six@example.test' ) ) );
ck( '1. the per-actor ceiling comes first: after five posts whose nonce failed, the sixth from one address in an hour is refused and stores nothing', array( $busy['outcome'], count( rows() ) ), array( 'busy', 0 ) );

reset_world();
$expired = submit( answers(), array( 'nonce' => 'nonce-stale' ) );
ck( '2. a nonce that fails stores nothing and hands back every answer but the tick', array( $expired['outcome'], count( rows() ), $expired['stash']['values']['name'] ?? null, isset( $expired['stash']['values']['consent'] ) ), array( 'expired', 0, 'Ada Okonjo', false ) );

reset_world();
$GLOBALS['settings']['mentor_applications_enabled'] = false;
ck( '3. with the switch off, a post is closed and stores nothing', array( submit( answers() )['outcome'], count( rows() ) ), array( 'closed', 0 ) );

reset_world();
$honey = submit( answers(), array( 'honeypot' => 'https://spam.example.test' ) );
$row   = only_row();
ck( '4. the honeypot files the row as spam, says sent, and mails nobody', array( $honey['outcome'], $row ? WPCPM_Mentor_Application::state_of( $row ) : null, $row ? WPCPM_Mentor_Application::signals_of( $row ) : null, count( $GLOBALS['mail'] ), count( $GLOBALS['notices'] ) ), array( 'sent', 'spam', array( 'honeypot' ), 0, 0 ) );
reset_world();
$honey_blank = submit( answers( array( 'name' => '', 'languages' => array() ) ), array( 'honeypot' => 'https://spam.example.test' ) );
ck( '   and before requiredness: a honeypot post with blank answers is stored as spam, not sent back to be finished', array( $honey_blank['outcome'], only_row() ? WPCPM_Mentor_Application::state_of( only_row() ) : null ), array( 'sent', 'spam' ) );

reset_world();
$stale = submit( answers(), array( 'token' => dwell_token( 13 * HOUR_IN_SECONDS ) ) );
ck( '5. a stale token bounces with the answers', array( $stale['outcome'], count( rows() ), $stale['stash']['values']['name'] ?? null ), array( 'stale', 0, 'Ada Okonjo' ) );
$fast = submit( answers(), array( 'token' => dwell_token( 1 ) ) );
ck( '   a token seconds old holds the row, and its sender is acknowledged', array( $fast['outcome'], WPCPM_Mentor_Application::state_of( only_row() ), WPCPM_Mentor_Application::signals_of( only_row() ), count( $GLOBALS['mail'] ) ), array( 'sent', 'held', array( 'dwell-fast' ), 1 ) );
reset_world();
$forged = submit( answers(), array( 'token' => '1.abc.forged' ) );
ck( '   a token this site never signed is spam', array( $forged['outcome'], WPCPM_Mentor_Application::state_of( only_row() ) ), array( 'sent', 'spam' ) );

reset_world();
foreach ( array( null, '0', 'yes', '' ) as $tick ) {
	$refused = submit( answers( array( 'consent' => $tick ) ) );
	ck( sprintf( '6. consent %s refuses the whole submission and stores nothing, not even as spam', var_export( $tick, true ) ), array( $refused['outcome'], count( rows() ) ), array( 'consent', 0 ) );
}

reset_world();
$again = submit( answers( array( 'name' => '', 'gpl' => null, 'languages' => array(), 'profile' => 'https://example.test/' ) ) );
ck(
	'8. requiredness: each blank answer, an unticked statement and a profile that is no username come back marked, with the rest of the answers, and nothing is stored',
	array( $again['outcome'], $again['stash']['problems'] ?? null, $again['stash']['values']['email'] ?? null, count( rows() ) ),
	array( 'again', array( 'profile' => 'bad_profile', 'name' => 'required', 'languages' => 'required', 'gpl' => 'unticked' ), 'ada@example.test', 0 )
);

reset_world();
for ( $i = 0; $i < WPCPM_Form_Guard::PER_DAY; $i++ ) {
	WPCPM_Ceiling::claim( WPCPM_Mentor_Application_Form::SITE_KEY, WPCPM_Form_Guard::PER_DAY, DAY_IN_SECONDS );
}
$degraded = submit( answers() );
ck( '9. past the day\'s forty the row is kept and held, and the Administrators are not mailed', array( $degraded['outcome'], WPCPM_Mentor_Application::state_of( only_row() ), WPCPM_Mentor_Application::signals_of( only_row() ), count( $GLOBALS['notices'] ) ), array( 'sent', 'held', array( 'site-ceiling' ), 0 ) );

reset_world();
submit( answers( array( 'anything' => 'https://a.example.test https://b.example.test https://c.example.test' ) ) );
ck( '10. three links in the free text hold the row', WPCPM_Mentor_Application::signals_of( only_row() ), array( 'links' ) );
reset_world();
$GLOBALS['disallowed'] = true;
submit( answers() );
ck( '   and so does a match on the site\'s disallowed list', WPCPM_Mentor_Application::signals_of( only_row() ), array( 'disallowed' ) );

echo "\n=== Duplicates hold, read from the site alone ===\n";

reset_world();
submit( answers() );
$second = submit( answers( array( 'email' => 'ADA@example.test', 'profile' => 'someone-example' ) ) );
$rows   = rows();
ck( 'another open application with the same address, in any case, holds the second and marks it', array( WPCPM_Mentor_Application::state_of( $rows[0] ), WPCPM_Mentor_Application::state_of( $rows[1] ), WPCPM_Mentor_Application::signals_of( $rows[1] ) ), array( 'new', 'held', array( 'duplicate' ) ) );

reset_world();
submit( answers() );
WPCPM_Mentor_Application::decide( rows()[0]->ID, 'selected', 'selected', 7 );
submit( answers( array( 'email' => 'other@example.test', 'profile' => '@Ada-Example' ) ) );
ck( 'one waiting for the course with the same username holds it too', WPCPM_Mentor_Application::signals_of( rows()[1] ), array( 'duplicate' ) );

reset_world();
submit( answers() );
WPCPM_Mentor_Application::decide( rows()[0]->ID, 'declined', 'declined', 7 );
submit( answers() );
ck( 'but a declined one is no duplicate: the same person may apply again', array( WPCPM_Mentor_Application::state_of( rows()[1] ), WPCPM_Mentor_Application::signals_of( rows()[1] ) ), array( 'new', array() ) );

$mentor_cases = array(
	'an account with the address and the Mentor role'         => function () {
		an_account( 30, 'Ada Okonjo', 'ada@example.test', array( 'wpcpm_mentor' ) );
	},
	'a former mentor\'s account, named for the username, with a record and no role' => function () {
		an_account( 31, 'Ada Okonjo', 'old@example.test', array( 'subscriber' ), array( WPCPM_Mentors_Sync::META_RECORD_ID => 'recMENTOR00000001' ) );
		$GLOBALS['users'][31]->user_login = 'ada-example';
	},
	'an account whose kept profile is the same username'      => function () {
		an_account( 32, 'A. Okonjo', 'elsewhere@example.test', array( 'wpcpm_mentor' ), array( WPCPM_Mentors_Sync::META_PROFILE => 'https://profiles.wordpress.org/ada-example/' ) );
	},
	'a mentor the last mentors sync read from Airtable'       => function () {
		$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_SPONSORSHIP ] = array( 'rows' => array( 'recMENTOR00000002' => array( 'name' => 'Ada Okonjo', 'profile' => 'ada-example' ) ) );
	},
);
foreach ( $mentor_cases as $case => $plant ) {
	reset_world();
	$plant();
	submit( answers() );
	ck( sprintf( 'a mentor the site knows holds it: %s', $case ), array( WPCPM_Mentor_Application::state_of( only_row() ), WPCPM_Mentor_Application::signals_of( only_row() ) ), array( 'held', array( 'mentor' ) ) );
}
reset_world();
an_account( 33, 'Ada Okonjo', 'ada@example.test', array( 'wpcpm_student' ) );
submit( answers() );
ck( 'and an account with the address that is no mentor\'s holds nothing', WPCPM_Mentor_Application::state_of( only_row() ), 'new' );

echo "\n=== The row and the two emails ===\n";

reset_world();
$sent = submit( answers( array( 'anything' => 'Line one.' . "\r\n" . 'C:\\path & "quotes"' ) ) );
$row  = only_row();
ck( 'a good application lands as a new row, and the page says sent, with the reference and the address', array( $sent['outcome'], WPCPM_Mentor_Application::state_of( $row ), $sent['stash']['reference'] ?? null, $sent['stash']['email'] ?? null ), array( 'sent', 'new', 'MAPP-2026-0500', 'ada@example.test' ) );
ck(
	'the answers are kept cleaned, the username for the profile and the country by name, the statement as ticked, and no consent among them',
	WPCPM_Mentor_Application::fields_of( $row ),
	array(
		'name'      => 'Ada Okonjo',
		'email'     => 'ada@example.test',
		'profile'   => 'ada-example',
		'country'   => 'Poland',
		'teacher'   => 'No',
		'languages' => array( 'English', 'Polish' ),
		'expertise' => array( 'Polyglots', 'Training' ),
		'hours'     => '2.5',
		'gpl'       => true,
		// As a browser posts a text box, each line break CRLF, and kept so.
		'anything'  => "Line one.\r\nC:\\path & \"quotes\"",
	)
);
// The address agreed to, WordPress.org's, with no post and no modified time: the site's own page is
// not what the applicant read (1.123.24).
$agreed = get_post_meta( $row->ID, WPCPM_Mentor_Application::META_CONSENT, true );
ck( 'the consent evidence names the sentence beside the box and the policy agreed to, no page of this site', array( $agreed['sentence'] ?? null, $agreed['url'] ?? null, $agreed['policy'] ?? null, $agreed['modified'] ?? null ), array( 'I confirm I have read the WordPress.org privacy policy and that my answers can be kept under it while the program reviews my application.', 'https://wordpress.org/about/privacy/', 0, '' ) );

$ack = $GLOBALS['mail'][0] ?? array();
ck( 'the acknowledgement goes to the address, under its own id, with the program\'s Reply-To', array( $ack['to'] ?? null, $ack['context'] ?? null, $ack['headers'] ?? null ), array( 'ada@example.test', 'mentor-application-received', array( 'Reply-To: programs@example.test' ) ) );
ck( 'and carries the automation\'s subject and greeting, and the link that confirms the address', array( $ack['subject'] ?? null, false !== strpos( $ack['body'] ?? '', WPCPM_Mentor_Application::verify_url( $row->ID ) ), 0 === strpos( $ack['body'] ?? '', "Hi!\n\n" ) ), array( 'Mentor application received - WordPress Credits Program', true, true ) );
$notice = $GLOBALS['notices'][0] ?? array();
ck( 'the Administrators are told through the mentor_notify setting, with the name, the profile and the queue, and none of the other answers', array( $notice['context'] ?? null, $notice['setting'] ?? null, false !== strpos( $notice['body'] ?? '', "Ada Okonjo, https://profiles.wordpress.org/ada-example/" ), false !== strpos( $notice['body'] ?? '', 'admin.php?page=wpcpm-mentors&tab=queue' ), false !== strpos( $notice['body'] ?? '', 'meetup' ), false !== strpos( $notice['subject'] ?? '', 'Ada' ) ), array( 'mentor-application-notice', 'mentor_notify', true, true, false, false ) );

reset_world();
$GLOBALS['settings']['mail_contact'] = '';
submit( answers( array( 'anything' => 'https://a.example.test https://b.example.test https://c.example.test' ) ) );
ck( 'a held row is acknowledged and the Administrators are not mailed; with no contact address, no Reply-To', array( count( $GLOBALS['mail'] ), count( $GLOBALS['notices'] ), $GLOBALS['mail'][0]['headers'] ?? null ), array( 1, 0, array() ) );

reset_world();
for ( $i = 0; $i < WPCPM_Form_Guard::MAIL_PER_DAY; $i++ ) {
	WPCPM_Form_Guard::claim_mail( WPCPM_Mentor_Application_Form::MAIL_KEY, WPCPM_Form_Guard::MAIL_PER_DAY );
}
$quiet = submit( answers() );
ck( 'past the day\'s mail ceiling the row is stored, marked, and the page says the acknowledgement could not go', array( $quiet['outcome'], WPCPM_Mentor_Application::signals_of( only_row() ), count( $GLOBALS['mail'] ) ), array( 'sent-quiet', array( 'mail-ceiling' ), 0 ) );
$late = WPCPM_Mentor_Application_Form::mail_applicant( (int) only_row()->ID );
ck(
	'and the acknowledgement\'s sender is public, the one way it goes, for Put back in the queue to send it later to a row that never had it: asked, it goes, the day\'s ceiling being the form\'s alone, with the link that confirms the address',
	array( $late, count( $GLOBALS['mail'] ), $GLOBALS['mail'][0]['context'] ?? null, false !== strpos( $GLOBALS['mail'][0]['body'] ?? '', WPCPM_Mentor_Application::verify_url( (int) only_row()->ID ) ) ),
	array( true, 1, 'mentor-application-received', true )
);

reset_world();
$thanked = submit( answers() );
$good    = only_row();
$state   = function ( $row ) {
	return array( WPCPM_Mentor_Application::state_of( $row ), WPCPM_Mentor_Application::fields_of( $row ), array_column( WPCPM_Mentor_Application::events( (int) $row->ID ), 'event' ), WPCPM_Mentor_Application::reference_of( $row ), get_post_meta( $row->ID, WPCPM_Mentor_Application::META_EMAIL, true ), get_post_meta( $row->ID, WPCPM_Mentor_Application::META_CONSENT, true )['sentence'] ?? null );
};
$whole = $state( $good );
$ok    = WPCPM_Mentor_Application::signals_of( $good );
reset_world();
$GLOBALS['mail_fails'] = true;
$quieted = submit( answers() );
$unsent  = only_row();
ck(
	'an acknowledgement the mail layer cannot hand off is recorded on the row as mail-failed, and nothing else of the row differs: the same state, answers, history, reference, address hash and consent, nothing sent, the Administrators still told',
	array( $ok, WPCPM_Mentor_Application::signals_of( $unsent ), $state( $unsent ) === $whole, count( $GLOBALS['mail'] ), count( $GLOBALS['notices'] ) ),
	array( array(), array( 'mail-failed' ), true, 0, 1 )
);
$_GET       = array( WPCPM_Mentor_Application_Form::QUERY_STASH => substr( $quieted['url'], strpos( $quieted['url'], '=' ) + 1 ) );
$quiet_page = WPCPM_Mentor_Application_Form::render();
ck(
	'and the applicant is shown the quiet thank-you, the one the day\'s limit shows, not the one that says an acknowledgement was sent: the reference kept, and the address told the acknowledgement could not be sent',
	array( $thanked['outcome'], $quieted['outcome'], false !== strpos( $quiet_page, 'MAPP-2026-0500' ), false !== strpos( $quiet_page, 'We could not send the acknowledgement to ada@example.test today' ), false === strpos( $quiet_page, 'We have sent an acknowledgement' ), false === strpos( $quiet_page, '<form' ) ),
	array( 'sent', 'sent-quiet', true, true, true, true )
);

reset_world();
$GLOBALS['mail_fails'] = true;
$held_out = submit( answers( array( 'anything' => 'https://a.example.test https://b.example.test https://c.example.test' ) ) );
ck( 'and the signal is added to what the checks raised: a held row whose acknowledgement could not go carries both, and its applicant is shown the quiet thank-you too', array( WPCPM_Mentor_Application::state_of( only_row() ), WPCPM_Mentor_Application::signals_of( only_row() ), $held_out['outcome'] ), array( 'held', array( 'links', 'mail-failed' ), 'sent-quiet' ) );

reset_world();
for ( $i = 0; $i < WPCPM_Form_Guard::MAIL_PER_DAY; $i++ ) {
	WPCPM_Form_Guard::claim_mail( WPCPM_Mentor_Application_Form::MAIL_KEY, WPCPM_Form_Guard::MAIL_PER_DAY );
}
$GLOBALS['mail_fails'] = true;
submit( answers() );
ck( 'while past the day\'s limit no hand-off is tried, so the row carries the limit and not a failure', WPCPM_Mentor_Application::signals_of( only_row() ), array( 'mail-ceiling' ) );

reset_world();
$GLOBALS['mail_fails'] = true;
submit( answers(), array( 'honeypot' => 'https://spam.example.test' ) );
ck( 'and a row the checks filed as spam is sent nothing, so none is tried and none fails', WPCPM_Mentor_Application::signals_of( only_row() ), array( 'honeypot' ) );

reset_world();
$GLOBALS['insert_fails'] = true;
ck( 'when WordPress refuses the row, the page says so and keeps the answers', array( submit( answers() )['outcome'], count( $GLOBALS['mail'] ) ), array( 'lost', 0 ) );
$GLOBALS['insert_fails'] = false;

echo "\n=== The confirming link ===\n";

reset_world();
submit( answers() );
$row  = only_row();
$link = function ( $app, $sig ) {
	$_GET     = array(
		'action' => WPCPM_Mentor_Application::ACTION_VERIFY,
		'app'    => (string) $app,
		't'      => $sig,
	);
	$_REQUEST = $_GET;

	return landed( str_replace( 'redirect: ', '', run( array( 'WPCPM_Mentor_Application_Form', 'handle_verify' ) )['stop'] ) )['outcome'];
};
ck(
	'a wrong signature and a page are one answer, and confirm nothing',
	array( $link( $row->ID, 'nope' ), $link( 42, WPCPM_Mentor_Application::verify_signature( 42 ) ), WPCPM_Mentor_Application::is_confirmed( $row->ID ) ),
	array( 'verify-failed', 'verify-failed', false )
);
ck(
	'the link confirms the address once, and says so again on a second visit',
	array( $link( $row->ID, WPCPM_Mentor_Application::verify_signature( $row->ID ) ), WPCPM_Mentor_Application::is_confirmed( $row->ID ), $link( $row->ID, WPCPM_Mentor_Application::verify_signature( $row->ID ) ) ),
	array( 'verified', true, 'verified-already' )
);

echo "\n=== The page ===\n";

$closed = array(
	'the switch off'         => function () {
		$GLOBALS['settings']['mentor_applications_enabled'] = false;
	},
	'no countries to offer'  => function () {
		unset( $GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ] );
	},
	'no teams to offer'      => function () {
		unset( $GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ] );
	},
);
// With the switch off the public are told, since 1.123.26, in the one sentence the form's outcomes
// already had for it, as the institution and sponsor forms say theirs; a form that cannot be drawn
// for a want of countries or teams is not switched off, and says nothing to the public.
$closed_said = '<div class="wpcpm-application"><p class="wpcpm-application__message is-info" role="status">The program is not taking mentor applications through this form at the moment.</p></div>';
foreach ( $closed as $case => $close ) {
	reset_world();
	$close();
	$public = WPCPM_Mentor_Application_Form::render();
	$GLOBALS['uid']    = 9;
	$GLOBALS['manage'] = array( 9 );
	$admin  = WPCPM_Mentor_Application_Form::render();
	ck( sprintf( 'closed with %s: the public sees %s, an Administrator one sentence and no form', $case, 'the switch off' === $case ? 'that applications are closed' : 'nothing' ), array( $public, false !== strpos( $admin, 'wpcpm-application__notice' ), false !== strpos( $admin, '<form' ) ), array( 'the switch off' === $case ? $closed_said : '', true, false ) );
}

// Since 1.123.24 the form links the WordPress.org privacy policy (the site's owner, 22 October
// 2026), so a site with no privacy page of its own draws it all the same, for the public and for an
// Administrator, with no sentence about a missing page.
reset_world();
$GLOBALS['policy'] = '';
$public            = WPCPM_Mentor_Application_Form::render();
$GLOBALS['uid']    = 9;
$GLOBALS['manage'] = array( 9 );
$admin             = WPCPM_Mentor_Application_Form::render();
ck( 'with no privacy page on the site the form is drawn all the same, for the public and an Administrator, linking WordPress.org\'s policy', array( false !== strpos( $public, '<form' ), false !== strpos( $public, 'href="https://wordpress.org/about/privacy/" rel="noopener">here</a>' ), false !== strpos( $admin, '<form' ), strpos( $admin, 'wpcpm-application__notice' ) ), array( true, true, true, false ) );

reset_world();
$page = WPCPM_Mentor_Application_Form::render();
preg_match_all( '#name="wpcpm_mentor_application\[([a-z_]+)\]#', $page, $named );
ck( 'open, the form asks every question in order, under the site\'s keys', array_values( array_unique( $named[1] ) ), array_keys( WPCPM_Mentor_Application_Form::fields() ) );
ck(
	'it posts to its handler with a nonce, a token and the honeypot',
	array( false !== strpos( $page, 'name="action" value="wpcpm_mentor_apply"' ), false !== strpos( $page, 'name="_wpnonce" value="nonce-wpcpm_mentor_apply"' ), false !== strpos( $page, 'name="wpcpm_mentor_application_token"' ), false !== strpos( $page, 'name="wpcpm_confirm_handle"' ) ),
	array( true, true, true, true )
);
ck(
	'the country is the one field for a long list: the label marked Required, the list with its empty first entry chosen, the field that takes typing beside it',
	array( false !== strpos( $page, '<label for="wpcpm-mentor-application-country" id="wpcpm-mentor-application-country-label">Country <span class="wpcpm-field__required">Required</span></label>' ), false !== strpos( $page, '<option value="" selected=\'selected\'>Choose a country</option><option value="Costa Rica">Costa Rica</option><option value="Poland">Poland</option>' ), false !== strpos( $page, 'role="combobox"' ), in_array( WPCPM_Dashboards::SWITCHER_SCRIPT, $GLOBALS['enqueued'], true ) ),
	array( true, true, true, true )
);
ck(
	'the languages and the teams are boxes in a named group, the contribution areas not marked Required, the teacher question two radios',
	array( substr_count( $page, 'name="wpcpm_mentor_application[languages][]"' ), substr_count( $page, 'name="wpcpm_mentor_application[expertise][]"' ), false !== strpos( $page, 'id="wpcpm-mentor-application-expertise-label">In which WordPress contribution areas do you have expertise?</span>' ), substr_count( $page, 'type="radio" name="wpcpm_mentor_application[teacher]"' ), false !== strpos( $page, 'role="group" aria-labelledby="wpcpm-mentor-application-languages-label"' ) ),
	array( 38, 3, true, 2, true )
);
ck(
	'the hours take a decimal to one place, in steps of a tenth, between 1 and 40, as the site keeps them',
	1 === preg_match( '#<input type="number" id="wpcpm-mentor-application-hours" name="wpcpm_mentor_application\[hours\]" value="" min="1" max="40" step="0\.1" inputmode="decimal" required="required"#', $page ),
	true
);
ck(
	'the compliance declaration is its heading, marked Required, and the box with the statement and its two links, opening where the form is',
	array( false !== strpos( $page, '<span class="wpcpm-field__label" id="wpcpm-mentor-application-gpl-label">Compliance declaration <span class="wpcpm-field__required">Required</span></span>' ), false !== strpos( $page, '<label for="wpcpm-mentor-application-gpl">I confirm that I comply with the <a href="https://wordpress.org/about/license/">GPL</a> and do not violate, endorse, or work for companies that violate the <a href="https://wordpressfoundation.org/trademark-policy/">WordPress trademark</a>, misuse the WordPress name or logo, or distribute non-GPL-compliant derivatives of WordPress.</label>' ), substr_count( $page, 'target=' ) ),
	array( true, true, 0 )
);
ck(
	'and the privacy policy is its heading and the consent sentence beside its box',
	false !== strpos( $page, '<span class="wpcpm-field__label" id="wpcpm-mentor-application-consent-label">Privacy policy <span class="wpcpm-field__required">Required</span></span><span class="wpcpm-field__consent"><input type="checkbox" id="wpcpm-mentor-application-consent" name="wpcpm_mentor_application[consent]" value="1" required="required" /><label for="wpcpm-mentor-application-consent">I confirm I have read the WordPress.org privacy policy and that my answers can be kept under it while the program reviews my application.</label></span>' ),
	true
);
ck(
	'and the line under it links WordPress.org\'s policy, not the page this site has set, opening where the form is',
	array( false !== strpos( $page, '<span class="wpcpm-field__hint">You can read the WordPress.org privacy policy <a href="https://wordpress.org/about/privacy/" rel="noopener">here</a>.</span>' ), strpos( $page, 'href="https://example.test/privacy/"' ) ),
	array( true, false )
);

reset_world();
$bounced = submit( answers( array( 'profile' => 'Ada Example', 'anything' => 'I wrote the <Navigation> block docs.' ) ) );
$_GET    = array( WPCPM_Mentor_Application_Form::QUERY_STASH => substr( $bounced['url'], strpos( $bounced['url'], '=' ) + 1 ) );
$again   = WPCPM_Mentor_Application_Form::render();
$loss    = esc_html( WPCPM_Typed_Text::loss_message( 'Anything else you\'d like us to know?', 'send' ) );
ck(
	'a profile that is no username and a free text with a tag come back with the sentence that says what to give',
	array( $bounced['outcome'], false !== strpos( $again, '<span class="wpcpm-field__problem" id="wpcpm-mentor-application-profile-problem">Give your WordPress.org username or your profile&#039;s address.</span>' ), false !== strpos( $again, '<span class="wpcpm-field__problem" id="wpcpm-mentor-application-anything-problem">' . $loss . '</span>' ) ),
	array( 'again', true, true )
);
ck(
	'the free text\'s sentence is the plugin\'s own for text the cleaner would cut: it names the "<" and the "%" and how to type them, and the box',
	array( false !== strpos( $loss, 'in &quot;Anything else you&#039;d like us to know?&quot;' ), false !== strpos( $loss, 'put a space after each &quot;&lt;&quot; (or write &quot;less than&quot;) and after each &quot;%&quot;' ) ),
	array( true, true )
);
ck(
	'what was typed comes back in its box, the profile as typed and the free text with its tag escaped, and nothing of it is a tag on the page',
	array( false !== strpos( $again, '<input type="text" id="wpcpm-mentor-application-profile" name="wpcpm_mentor_application[profile]" value="Ada Example" maxlength="200"' ), false !== strpos( $again, 'aria-describedby="wpcpm-mentor-application-anything-problem">I wrote the &lt;Navigation&gt; block docs.</textarea>' ), false !== strpos( $again, '<Navigation>' ), count( rows() ) ),
	array( true, true, false, 0 )
);

reset_world();
$hostile = submit( answers( array( 'profile' => '"><script>alert(1)</script>', 'anything' => '</textarea><script>alert(2)</script>' ) ) );
$_GET    = array( WPCPM_Mentor_Application_Form::QUERY_STASH => substr( $hostile['url'], strpos( $hostile['url'], '=' ) + 1 ) );
$drawn   = WPCPM_Mentor_Application_Form::render();
ck(
	'a typing handed back is escaped where it is drawn: each box holds it whole, as text, and no tag of it reaches the page',
	array( false !== strpos( $drawn, '<script>' ), false !== strpos( $drawn, 'value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"' ), false !== strpos( $drawn, '>&lt;/textarea&gt;&lt;script&gt;alert(2)&lt;/script&gt;</textarea>' ), substr_count( $drawn, '</textarea>' ) ),
	array( false, true, true, 1 )
);

reset_world();
$percent = submit( answers( array( 'anything' => 'I get 10%cashback' ) ) );
$_GET    = array( WPCPM_Mentor_Application_Form::QUERY_STASH => substr( $percent['url'], strpos( $percent['url'], '=' ) + 1 ) );
$drawn   = WPCPM_Mentor_Application_Form::render();
ck(
	'a free text with a percent code is refused with the sentence that names it, and comes back in its box as typed',
	array( $percent['outcome'], $percent['stash']['problems']['anything'] ?? null, false !== strpos( $drawn, 'and after each &quot;%&quot;, then send it again.' ), false !== strpos( $drawn, '>I get 10%cashback</textarea>' ) ),
	array( 'again', 'markup', true, true )
);

reset_world();
$long = submit( answers( array( 'profile' => str_repeat( 'x y ', 100 ), 'anything' => str_repeat( 'a', 3999 ) . '<' . str_repeat( 'b', 1000 ) . '>' ) ) );
ck(
	'what a refused answer was typed as is cut to the length of its box in the stash: 200 characters for the profile, 4000 for the free text',
	array( $long['outcome'], mb_strlen( $long['stash']['typed']['profile'] ?? '' ), mb_strlen( $long['stash']['typed']['anything'] ?? '' ), substr( $long['stash']['typed']['anything'] ?? '', -2 ) ),
	array( 'again', 200, 4000, 'a<' )
);

reset_world();
$crafted = submit( answers( array( 'profile' => array( 'x' ), 'anything' => "bad \xff bytes <b>" ) ) );
ck(
	'an array and text that is not valid UTF-8, which only a crafted post sends, are refused and handed back as no typing at all, the answers that were fine as they were',
	array( $crafted['outcome'], $crafted['stash']['problems']['profile'] ?? null, $crafted['stash']['problems']['anything'] ?? null, isset( $crafted['stash']['typed'] ), $crafted['stash']['values']['name'] ?? null ),
	array( 'again', 'not_scalar', 'markup', false, 'Ada Okonjo' )
);

reset_world();
$filed    = submit( answers( array( 'profile' => 'Ada Example', 'anything' => 'I wrote the <Navigation> block docs.' ) ), array( 'honeypot' => 'https://spam.example.test' ) );
$spam_row = only_row();
$spam_all = $spam_row ? json_encode( $GLOBALS['pmeta'][ $spam_row->ID ] ) : '';
ck(
	'a row filed as spam stores no refused typing: the free text and the profile that were refused are in none of its meta, and the page says sent',
	array( $filed['outcome'], $spam_row ? WPCPM_Mentor_Application::state_of( $spam_row ) : null, $spam_row ? array_keys( WPCPM_Mentor_Application::fields_of( $spam_row ) ) : null, false !== strpos( $spam_all, 'Navigation' ), false !== strpos( $spam_all, 'Ada Example' ) ),
	array( 'sent', 'spam', array( 'name', 'email', 'country', 'teacher', 'languages', 'expertise', 'hours', 'gpl' ), false, false )
);

reset_world();
$sent = submit( answers() );
$_GET = array( WPCPM_Mentor_Application_Form::QUERY_STASH => substr( $sent['url'], strpos( $sent['url'], '=' ) + 1 ) );
$thanks = WPCPM_Mentor_Application_Form::render();
ck( 'the thank-you names the reference and the address, says to confirm it, and draws no form', array( false !== strpos( $thanks, 'MAPP-2026-0500' ), false !== strpos( $thanks, 'ada@example.test with a link that confirms the address' ), false !== strpos( $thanks, '<form' ) ), array( true, true, false ) );
ck( 'and it is one-shot: the stash is gone once read', WPCPM_Mentor_Application_Form::render() === $thanks, false );
$owner = array();
foreach ( array( 'block' => array( 'WPCPM_Mentor_Application_Form', 'render_block' ), 'shortcode' => array( 'WPCPM_Mentor_Application_Form', 'render' ) ) as $how => $draw ) {
	reset_world();
	$sent           = submit( answers() );
	$_GET           = array( WPCPM_Mentor_Application_Form::QUERY_STASH => substr( $sent['url'], strpos( $sent['url'], '=' ) + 1 ) );
	$drawn          = call_user_func( $draw, array( 'thanks' => 'Thanks, <b>mentor</b>: we read every one.' ) );
	$owner[ $how ] = array( false !== strpos( $drawn, '<h3>Thanks, &lt;b&gt;mentor&lt;/b&gt;: we read every one.</h3>' ), false !== strpos( $drawn, 'Thank you for applying to mentor' ), false !== strpos( $drawn, 'MAPP-2026-0500' ) );
}
ck( 'the owner\'s thank-you, given to the block or the shortcode, takes the plugin\'s place, escaped, above the reference', $owner, array_fill_keys( array( 'block', 'shortcode' ), array( true, false, true ) ) );

reset_world();
unset( $GLOBALS['opts'][ WPCPM_Mentor_Application_Form::OPT_PAGE ] );
$GLOBALS['posts'][42]->post_content = '<!-- wp:paragraph --><p>Apply in Airtable.</p><!-- /wp:paragraph -->';
$adopted                            = WPCPM_Mentor_Application_Form::ensure_page();
$adopted_content                    = get_post( 42 )->post_content;
unset( $GLOBALS['posts'][42], $GLOBALS['slugs'][42], $GLOBALS['opts'][ WPCPM_Mentor_Application_Form::OPT_PAGE ] );
$made = WPCPM_Mentor_Application_Form::ensure_page();
ck( 'the page at become-a-mentor is adopted as it is when it is there, made when it is not', array( $adopted, $adopted_content, get_post( $made )->post_name, get_option( WPCPM_Mentor_Application_Form::OPT_PAGE ) ), array( 42, '<!-- wp:paragraph --><p>Apply in Airtable.</p><!-- /wp:paragraph -->', 'become-a-mentor', $made ) );
ck(
	'and a page it makes starts with the Airtable form\'s introduction, the call for mentors page linked by its words (a bare address is wider than a phone screen), then the form',
	get_post( $made )->post_content,
	"<!-- wp:paragraph -->\n<p>We are looking for dedicated mentors to guide the next generation of contributors in WordPress Credits, a flagship educational program of the WordPress Foundation.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>We cannot ensure sponsorship for mentors. By applying you confirm that you have at least two hours each week for your students. Before applying, read <a href=\"https://make.wordpress.org/handbook/wordpress-credits-contribution-internship-program/call-for-mentors/\">the call for mentors page</a>.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:wpcpm/mentor-application /-->"
);
ck( 'and it is never gated: no access level is written on it', isset( $GLOBALS['pmeta'][ $made ] ), false );
ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
