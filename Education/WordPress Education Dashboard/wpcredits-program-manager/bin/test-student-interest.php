<?php
/**
 * The student interest form (1.123.64): the record, the form, the two emails, the Interests tab, the
 * privacy tools and what an uninstall removes.
 *
 * A student whose institution is not in the program yet says on the site that they would like to
 * take part, and names a representative of the institution. What each section pins, and why:
 *
 * - **The record.** The private post an interest is kept in: the five states and which leads to
 *   which, the answers kept whole with a typed backslash, the two addresses kept beside them as
 *   hashes, the reference, the consent's two sentences, who changed the state and when, the
 *   duplicate within thirty days, the lists and the counts, retention on the daily job, and
 *   delete_all.
 *
 * - **The form.** The thirteen questions in the old form's order and words, each refusal with its
 *   problem and its sentence, the page with each reason it is closed, the page an editor places the
 *   form on and how the site remembers it, the submit path in the registration form's order (read
 *   off the source, then exercised at each boundary), both boxes needed before anything is stored,
 *   a held interest stored and acknowledged and not announced, a duplicate marked, and the one
 *   thank-you every path lands on.
 *
 * - **The emails.** The acknowledgement to the student and the notice to Administrators, word for
 *   word, who each goes to, the day's ceiling on the first, and that nothing ever goes to the
 *   representative.
 *
 * - **The registration form's hint** is bin/test-student-registration.php's: its three cases are
 *   pinned there, beside the form they are drawn on. Here, the address this form hands it.
 *
 * - **The tab.** The Interests tab: who may see it and press, the views and their counts, newest
 *   first and bounded, what each row prints and how it is escaped, and the five presses with their
 *   guards.
 *
 * - **The privacy tools and the uninstall.** An interest is exported and erased by either address,
 *   and the Students module's uninstall removes every interest, the page's option and the daily job.
 *
 * Nothing here reaches Airtable, and the source is read to say so.
 *
 * Run from the plugin root:  php bin/test-student-interest.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['settings']  = array();
$GLOBALS['countries'] = array();

/** The settings the form reads, as a check sets them. */
class WPCPM_Settings {
	public static function get() {
		return $GLOBALS['settings'];
	}
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

/** The site's copy of the Countries table, as the form reads it: record ID to name, A to Z. */
class WPCPM_Countries {
	public static function options() {
		$options = $GLOBALS['countries'];

		natcasesort( $options );

		return $options;
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

/** The one mechanism that tells Administrators: what it was handed, and what the builder made. */
class WPCPM_Institutions {
	public static function notify_managers( $context, $build, $setting_key = 'agreement_notify' ) {
		$GLOBALS['notices'][] = array(
			'context' => $context,
			'setting' => $setting_key,
			'mail'    => (array) call_user_func( $build, 'ida@example.test' ),
		);

		return 1;
	}
}

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
/** The site's comment disallowed list, as a check sets it: a hit or none. */
function wp_check_comment_disallowed_list( $author, $email, $url, $comment, $ip, $agent ) {
	return ! empty( $GLOBALS['disallowed'] );
}
/** The page a form was posted from, as a check sets it. */
function wp_get_referer() {
	return isset( $GLOBALS['referer'] ) ? $GLOBALS['referer'] : false;
}
/** An address this site may redirect to: its own, and the fallback for any other. */
function wp_validate_redirect( $location, $fallback = '' ) {
	return 0 === strpos( (string) $location, 'https://example.test/' ) ? (string) $location : $fallback;
}
/** An address without the arguments named. */
function remove_query_arg( $keys, $url = false ) {
	foreach ( (array) $keys as $key ) {
		$url = preg_replace( '/([?&])' . preg_quote( $key, '/' ) . '=[^&]*(&|$)/', '$1', (string) $url );
	}

	return rtrim( (string) $url, '?&' );
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-stash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-interest.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-interest-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-interests-tab.php';

$GLOBALS['enqueued']   = array();
$GLOBALS['registered'] = array();
$GLOBALS['mail']       = array();
$GLOBALS['notices']    = array();

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

/** A site where the form is open, on invented IDs and example.test addresses, with nothing stored or sent. */
function world() {
	$GLOBALS['opts']       = array(
		'date_format' => 'F j, Y',
		'time_format' => 'g:i a',
	);
	$GLOBALS['transients'] = array();
	$GLOBALS['posts']      = array();
	$GLOBALS['pmeta']      = array();
	$GLOBALS['users']      = array();
	$GLOBALS['umeta']      = array();
	$GLOBALS['slugs']      = array();
	$GLOBALS['cron']       = array();
	$GLOBALS['mail']       = array();
	$GLOBALS['notices']    = array();
	$GLOBALS['mail_fails'] = false;
	$GLOBALS['disallowed'] = false;
	$GLOBALS['uid']        = 0;
	$GLOBALS['manage']     = array();
	$GLOBALS['enqueued']   = array();
	$GLOBALS['registered'] = array();
	$GLOBALS['nocache']    = 0;
	$GLOBALS['next_id']    = 500;
	$GLOBALS['settings']   = array(
		'mail_contact' => 'programs@example.test',
	);
	$GLOBALS['countries']  = array(
		'recCOUNTRY0000002' => 'Portugal',
		'recCOUNTRY0000001' => 'Argentina',
		'recCOUNTRY0000003' => 'Poland',
	);

	unset( $GLOBALS['on_page'], $GLOBALS['referer'] );

	$_POST                      = array();
	$_GET                       = array();
	$_REQUEST                   = array();
	$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test)';

	// The page an editor placed the form on, published, and remembered.
	$page               = new WP_Post();
	$page->ID           = 42;
	$page->post_type    = 'page';
	$page->post_status  = 'publish';
	$page->post_content = '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph --><!-- wp:wpcpm/student-interest /-->';

	$GLOBALS['posts'][42] = $page;
	$GLOBALS['slugs'][42] = 'student-interest';
	$GLOBALS['opts'][ WPCPM_Student_Interest_Form::OPT_PAGE ] = 42;
}

world();

require_once __DIR__ . '/stubs/options-lock-wpdb.php';
// The lock an interest's state change and its deletion take, over the stand-in's options table.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';

/** The cleaned answers of one interest, as the form stores them. */
function answers( array $more = array() ) {
	return array_merge(
		array(
			'name'           => 'Ines Example',
			'email'          => 'ines@example.test',
			'institution'    => 'Example University',
			'country'        => 'recCOUNTRY0000002',
			'country_name'   => 'Portugal',
			'city'           => 'Porto',
			'website'        => 'https://university.example.test',
			'department'     => 'Computer Science',
			'representative' => 'Rui Example',
			'contact_email'  => 'rui@example.test',
			'internships'    => array( 'Based on required hours (e.g. 150 hours)', 'Other (please specify)' ),
			'anything'       => 'Typed a backslash: C:\\temp and a "quote"',
			'told'           => true,
		),
		$more
	);
}

$consent = array(
	'sentence' => 'By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (https://wordpress.org/about/privacy/).',
	'url'      => 'https://wordpress.org/about/privacy/',
	'policy'   => 0,
	'modified' => '',
	'at'       => 1700000000,
	'ip'       => '203.0.113.0',
	'agent'    => 'Mozilla/5.0 (test)',
	'told'     => 'I have told the representative about this request, and they agreed that I share their name and email address with the WordPress Credits program.',
);

/** One stored interest in a state, moved there the way a press moves it. */
function interest_in( $state, array $more = array(), array $signals = array() ) {
	global $consent;

	$id = WPCPM_Student_Interest::store( answers( $more ), in_array( $state, array( 'held', 'spam' ), true ) ? $state : 'new', $signals, $consent );

	if ( in_array( $state, array( 'contacted', 'closed' ), true ) ) {
		WPCPM_Student_Interest::set_state( $id, $state, 9 );
	}

	return $id;
}

echo "\n=== The record ===\n";

ck( 'the post type, the five states in the tab\'s order, the three periods and the daily job',
	array( WPCPM_Student_Interest::POST_TYPE, strlen( WPCPM_Student_Interest::POST_TYPE ) <= 20, WPCPM_Student_Interest::states(), WPCPM_Student_Interest::DUPLICATE_DAYS, WPCPM_Student_Interest::SPAM_DAYS, WPCPM_Student_Interest::CLOSED_DAYS, WPCPM_Student_Interest::CRON_DAILY ),
	array( 'wpcpm_student_int', true, array( 'new', 'held', 'contacted', 'closed', 'spam' ), 30, 30, 365, 'wpcpm_student_interests_daily' ) );
ck( 'the meta keys, each under the record\'s own prefix',
	array(
		WPCPM_Student_Interest::META_FIELDS, WPCPM_Student_Interest::META_STATE, WPCPM_Student_Interest::META_REFERENCE, WPCPM_Student_Interest::META_CONSENT,
		WPCPM_Student_Interest::META_SIGNALS, WPCPM_Student_Interest::META_EMAIL, WPCPM_Student_Interest::META_CONTACT, WPCPM_Student_Interest::META_INSTITUTION,
		WPCPM_Student_Interest::META_CREATED, WPCPM_Student_Interest::META_CHANGED, WPCPM_Student_Interest::META_CHANGED_BY,
	),
	array(
		'_wpcpm_sint_fields', '_wpcpm_sint_state', '_wpcpm_sint_reference', '_wpcpm_sint_consent',
		'_wpcpm_sint_signals', '_wpcpm_sint_email', '_wpcpm_sint_contact', '_wpcpm_sint_institution',
		'_wpcpm_sint_created', '_wpcpm_sint_changed', '_wpcpm_sint_changed_by',
	) );

$GLOBALS['hooks'] = array();
WPCPM_Student_Interest::init();

ck( 'it registers its post type on init, puts its daily job on the clock, runs it on its hook and adds itself to both privacy tools',
	array( count( $GLOBALS['hooks']['init'] ), isset( $GLOBALS['hooks']['wpcpm_student_interests_daily'] ), isset( $GLOBALS['hooks']['wp_privacy_personal_data_exporters'] ), isset( $GLOBALS['hooks']['wp_privacy_personal_data_erasers'] ) ),
	array( 2, true, true, true ) );

WPCPM_Student_Interest::register_post_type();

ck( 'the post type is private, with no screen, no REST, no query variable and a capability no role holds',
	isset( $GLOBALS['post_types'][ WPCPM_Student_Interest::POST_TYPE ] )
		? array_intersect_key( $GLOBALS['post_types'][ WPCPM_Student_Interest::POST_TYPE ], array_flip( array( 'public', 'publicly_queryable', 'exclude_from_search', 'show_ui', 'show_in_rest', 'query_var', 'capability_type' ) ) )
		: null,
	array( 'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true, 'show_ui' => false, 'show_in_rest' => false, 'query_var' => false, 'capability_type' => array( 'wpcpm_student_int', 'wpcpm_student_ints' ) ) );

WPCPM_Student_Interest::schedule();
$scheduled = $GLOBALS['cron'][ WPCPM_Student_Interest::CRON_DAILY ] ?? null;
WPCPM_Student_Interest::schedule();
$again = $GLOBALS['cron'][ WPCPM_Student_Interest::CRON_DAILY ] ?? null;
WPCPM_Student_Interest::unschedule();

ck( 'the daily job is put on the clock once, daily, and taken off it',
	array( $scheduled['every'] ?? null, $scheduled === $again, isset( $GLOBALS['cron'][ WPCPM_Student_Interest::CRON_DAILY ] ) ),
	array( 'daily', true, false ) );

$id   = WPCPM_Student_Interest::store( answers( array( 'email' => '  Ines@Example.TEST ', 'contact_email' => 'Rui@Example.test' ) ), 'new', array( 'duplicate', 'duplicate' ), $consent );
$post = WPCPM_Student_Interest::interest( $id );

ck( 'a stored interest is private, authored by nobody, titled with the student\'s name',
	array( $post instanceof WP_Post, $post ? $post->post_type : null, $post ? $post->post_status : null, $post ? $post->post_author : null, $post ? $post->post_title : null ),
	array( true, 'wpcpm_student_int', 'private', 0, 'Ines Example' ) );
ck( 'it is new, and holds the answers whole with the typed backslash, the consent with both sentences and the policy\'s address, and each signal once',
	array( WPCPM_Student_Interest::state_of( $post ), WPCPM_Student_Interest::fields_of( $post ), WPCPM_Student_Interest::consent_of( $post ), WPCPM_Student_Interest::signals_of( $post ) ),
	array( 'new', answers( array( 'email' => '  Ines@Example.TEST ', 'contact_email' => 'Rui@Example.test' ) ), $consent, array( 'duplicate' ) ) );
ck( 'the two addresses are kept beside the answers as hashes, the same for any spelling, and the institution in lower case',
	array( get_post_meta( $id, WPCPM_Student_Interest::META_EMAIL, true ), get_post_meta( $id, WPCPM_Student_Interest::META_CONTACT, true ), get_post_meta( $id, WPCPM_Student_Interest::META_INSTITUTION, true ) ),
	array( wp_hash( 'ines@example.test' ), wp_hash( 'rui@example.test' ), 'example university' ) );
ck( 'it has a short reference of the year and its ID, stored, a time it was stored at, and nobody has changed its state',
	array( WPCPM_Student_Interest::reference_of( $post ), get_post_meta( $id, WPCPM_Student_Interest::META_REFERENCE, true ), abs( WPCPM_Student_Interest::created_of( $post ) - time() ) < 5, WPCPM_Student_Interest::changed_of( $post ) ),
	array( 'SINT-2026-0' . $id, 'SINT-2026-0' . $id, true, array( 'by' => 0, 'at' => 0 ) ) );
ck( 'an interest of a post that is not one, or of none, is null',
	array( WPCPM_Student_Interest::interest( 999999 ), WPCPM_Student_Interest::interest( 42 ) ),
	array( null, null ) );
ck( 'the hash is lower case and trimmed, and empty for no address; an institution\'s name is compared with its spaces folded',
	array( WPCPM_Student_Interest::email_hash( '  Ines@Example.TEST ' ) === WPCPM_Student_Interest::email_hash( 'ines@example.test' ), WPCPM_Student_Interest::email_hash( '   ' ), WPCPM_Student_Interest::institution_key( "  Example   UNIVERSITY\t" ) ),
	array( true, '', 'example university' ) );

$odd  = WPCPM_Student_Interest::interest( WPCPM_Student_Interest::store( array( 'email' => 'nobody@example.test' ), 'contacted', array(), array() ) );
$held = WPCPM_Student_Interest::interest( WPCPM_Student_Interest::store( answers(), 'held', array( 'links' ), $consent ) );
$spam = WPCPM_Student_Interest::interest( WPCPM_Student_Interest::store( answers(), 'spam', array( 'honeypot' ), $consent ) );

ck( 'an interest is stored as new, held or spam and as nothing else, and one with no name is titled so',
	array( WPCPM_Student_Interest::state_of( $odd ), $odd->post_title, WPCPM_Student_Interest::state_of( $held ), WPCPM_Student_Interest::state_of( $spam ) ),
	array( 'new', 'Interest with no name', 'held', 'spam' ) );

$GLOBALS['insert_fails'] = true;
ck( 'an interest WordPress refuses is 0', WPCPM_Student_Interest::store( answers(), 'new', array(), $consent ), 0 );
$GLOBALS['insert_fails'] = false;

echo "\n--- The states and their guards ---\n";

$leads = array();

foreach ( WPCPM_Student_Interest::states() as $from ) {
	$leads[ $from ] = array();

	foreach ( WPCPM_Student_Interest::states() as $to ) {
		if ( WPCPM_Student_Interest::can_move( $from, $to ) ) {
			$leads[ $from ][] = $to;
		}
	}
}

ck( 'each state leads where the tab\'s presses go and nowhere else: never to itself, never to held, and from contacted to closed alone',
	$leads,
	array(
		'new'       => array( 'contacted', 'closed', 'spam' ),
		'held'      => array( 'new', 'contacted', 'closed', 'spam' ),
		'contacted' => array( 'closed' ),
		'closed'    => array( 'new' ),
		'spam'      => array( 'new' ),
	) );
ck( 'a state that is not one leads nowhere and is led to from nowhere',
	array( WPCPM_Student_Interest::can_move( 'lost', 'new' ), WPCPM_Student_Interest::can_move( 'new', 'lost' ), WPCPM_Student_Interest::can_move( '', '' ) ),
	array( false, false, false ) );
ck( 'only a spam or a closed interest may be deleted by hand',
	array_map( array( 'WPCPM_Student_Interest', 'can_delete' ), array( 'new', 'held', 'contacted', 'closed', 'spam', 'lost' ) ),
	array( false, false, false, true, true, false ) );

world();

$a = interest_in( 'new' );

ck( 'a move the state does not lead to is refused and changes nothing',
	array( WPCPM_Student_Interest::set_state( $a, 'held', 9 ), WPCPM_Student_Interest::set_state( $a, 'new', 9 ), WPCPM_Student_Interest::set_state( $a, 'lost', 9 ), WPCPM_Student_Interest::set_state( 42, 'closed', 9 ), WPCPM_Student_Interest::state_of( WPCPM_Student_Interest::interest( $a ) ), WPCPM_Student_Interest::changed_of( WPCPM_Student_Interest::interest( $a ) ) ),
	array( false, false, false, false, 'new', array( 'by' => 0, 'at' => 0 ) ) );

$moved   = WPCPM_Student_Interest::set_state( $a, 'contacted', 9 );
$changed = WPCPM_Student_Interest::changed_of( WPCPM_Student_Interest::interest( $a ) );

ck( 'a move it leads to is made, with who made it and when',
	array( $moved, WPCPM_Student_Interest::state_of( WPCPM_Student_Interest::interest( $a ) ), $changed['by'], abs( $changed['at'] - time() ) < 5 ),
	array( true, 'contacted', 9, true ) );
ck( 'a contacted interest cannot be marked as spam or put back, and cannot be deleted; closed, it can be deleted',
	array( WPCPM_Student_Interest::set_state( $a, 'spam', 9 ), WPCPM_Student_Interest::set_state( $a, 'new', 9 ), WPCPM_Student_Interest::delete( $a ), WPCPM_Student_Interest::set_state( $a, 'closed', 9 ), WPCPM_Student_Interest::delete( $a ), WPCPM_Student_Interest::interest( $a ) ),
	array( false, false, false, true, true, null ) );
ck( 'delete leaves a post that is no interest alone', array( WPCPM_Student_Interest::delete( 42 ), null !== get_post( 42 ) ), array( false, true ) );

WPCPM_Student_Interest::add_signal( interest_in( 'new' ), 'mail-ceiling' );
$marked = WPCPM_Student_Interest::in_states( array( 'new' ) )[0];
WPCPM_Student_Interest::add_signal( $marked->ID, 'mail-ceiling' );
WPCPM_Student_Interest::add_signal( $marked->ID, '' );

ck( 'a signal known only once the row exists is added once, and an empty one not at all', WPCPM_Student_Interest::signals_of( $marked ), array( 'mail-ceiling' ) );

echo "\n--- The lists and the counts ---\n";

world();

$n1 = interest_in( 'new', array( 'email' => 'n1@example.test' ) );
$n2 = interest_in( 'new', array( 'email' => 'n2@example.test' ) );
$h1 = interest_in( 'held', array( 'email' => 'h1@example.test' ) );
$c1 = interest_in( 'contacted', array( 'email' => 'c1@example.test' ) );
$s1 = interest_in( 'spam', array( 'email' => 's1@example.test' ) );

$ids = function ( array $posts ) {
	return array_map(
		function ( $p ) {
			return $p->ID;
		},
		$posts
	);
};

ck( 'in_states lists by date then ID, newest first unless asked, bounded when asked; count_in counts, and count_new counts the new',
	array(
		$ids( WPCPM_Student_Interest::in_states( array( 'new' ) ) ),
		$ids( WPCPM_Student_Interest::in_states( array( 'new' ), 0, 'ASC' ) ),
		$ids( WPCPM_Student_Interest::in_states( array( 'new', 'held', 'contacted' ), 2 ) ),
		WPCPM_Student_Interest::count_in( array( 'new', 'held' ) ),
		WPCPM_Student_Interest::count_in( array( 'nonsense' ) ),
		WPCPM_Student_Interest::in_states( array() ),
		WPCPM_Student_Interest::count_new(),
	),
	array( array( $n2, $n1 ), array( $n1, $n2 ), array( $c1, $h1 ), 3, 0, array(), 2 ) );

echo "\n--- A duplicate within thirty days ---\n";

world();

$T     = 1800000000;
$first = interest_in( 'new' );
update_post_meta( $first, WPCPM_Student_Interest::META_CREATED, $T - 29 * DAY_IN_SECONDS );

ck( 'the same student and the same institution within thirty days is a duplicate, whatever the case and the spacing of either',
	array( WPCPM_Student_Interest::has_recent( 'ines@example.test', 'Example University', $T ), WPCPM_Student_Interest::has_recent( ' INES@example.test ', "example   university ", $T ) ),
	array( true, true ) );
ck( 'another student, another institution, or no address or no name is none',
	array( WPCPM_Student_Interest::has_recent( 'other@example.test', 'Example University', $T ), WPCPM_Student_Interest::has_recent( 'ines@example.test', 'Sample College', $T ), WPCPM_Student_Interest::has_recent( '', 'Example University', $T ), WPCPM_Student_Interest::has_recent( 'ines@example.test', '', $T ) ),
	array( false, false, false, false ) );
ck( 'the representative\'s address is not the student\'s: an interest naming somebody as representative is no duplicate of one they send',
	WPCPM_Student_Interest::has_recent( 'rui@example.test', 'Example University', $T ), false );

update_post_meta( $first, WPCPM_Student_Interest::META_CREATED, $T - 30 * DAY_IN_SECONDS );

ck( 'thirty days on it is one no longer', WPCPM_Student_Interest::has_recent( 'ines@example.test', 'Example University', $T ), false );

update_post_meta( $first, WPCPM_Student_Interest::META_CREATED, $T - DAY_IN_SECONDS );
update_post_meta( $first, WPCPM_Student_Interest::META_STATE, 'spam' );

ck( 'and an earlier one counts whatever became of it, spam included', WPCPM_Student_Interest::has_recent( 'ines@example.test', 'Example University', $T ), true );

echo "\n--- Retention ---\n";

world();

$aged = function ( $state, $days_ago, $changed = true ) use ( $T ) {
	$new = interest_in( $state, array( 'email' => 'aged' . $days_ago . $state . '@example.test' ) );
	$at  = $T - (int) round( $days_ago * DAY_IN_SECONDS );

	update_post_meta( $new, WPCPM_Student_Interest::META_CREATED, $at );

	if ( $changed && 'closed' === $state ) {
		update_post_meta( $new, WPCPM_Student_Interest::META_CHANGED, $at );
	}

	return $new;
};

$rows = array(
	'spam at 31 days'      => $aged( 'spam', 31 ),
	'spam at 30 days'      => $aged( 'spam', 30 ),
	'spam at 29 days'      => $aged( 'spam', 29 ),
	'closed at 366 days'   => $aged( 'closed', 366 ),
	'closed at 364 days'   => $aged( 'closed', 364 ),
	'new at 1000 days'     => $aged( 'new', 1000 ),
	'held at 1000 days'    => $aged( 'held', 1000 ),
	'contacted at 1000'    => $aged( 'contacted', 1000 ),
);

// Stored 400 days ago and closed yesterday: the count runs from the close, not from the day it arrived.
$late = $aged( 'closed', 400, false );
update_post_meta( $late, WPCPM_Student_Interest::META_CHANGED, $T - DAY_IN_SECONDS );

// Marked as spam by hand yesterday, 100 days after it arrived: the count runs from the mark.
$marked = $aged( 'new', 100 );
WPCPM_Student_Interest::set_state( $marked, 'spam', 9 );
update_post_meta( $marked, WPCPM_Student_Interest::META_CHANGED, $T - DAY_IN_SECONDS );

ck( 'the purge answers how many it removed', WPCPM_Student_Interest::purge( $T ), 3 );
ck( 'spam goes 30 days after it was filed and a closed interest 365 days after it was closed, each counted from the last change of its state; every other interest stays, however old',
	array_map(
		function ( $id ) {
			return null !== get_post( $id );
		},
		$rows + array( 'closed yesterday, stored long ago' => $late, 'marked as spam yesterday' => $marked )
	),
	array(
		'spam at 31 days'                   => false,
		'spam at 30 days'                   => false,
		'spam at 29 days'                   => true,
		'closed at 366 days'                => false,
		'closed at 364 days'                => true,
		'new at 1000 days'                  => true,
		'held at 1000 days'                 => true,
		'contacted at 1000'                 => true,
		'closed yesterday, stored long ago' => true,
		'marked as spam yesterday'          => true,
	) );

$stale = $aged( 'spam', 400 );
WPCPM_Student_Interest::run_daily();

ck( 'the daily job is the purge, counted from now', get_post( $stale ), null );

WPCPM_Student_Interest::delete_all();

ck( 'delete_all removes every interest and its meta, whatever its state', array( count( WPCPM_Student_Interest::in_states( WPCPM_Student_Interest::states() ) ), isset( $GLOBALS['pmeta'][ $late ] ), null !== get_post( 42 ) ), array( 0, false, true ) );

echo "\n=== The form ===\n";

/** A dwell token of a given age, signed the way the guard signs one for this form. */
function form_token( $age = 30 ) {
	$issued = time() - (int) $age;
	$random = wp_generate_password( 12, false, false );

	return $issued . '.' . $random . '.' . substr( wp_hash( WPCPM_Student_Interest_Form::DWELL_SCOPE . '|' . $issued . '|' . $random . '|' . wp_create_nonce( WPCPM_Student_Interest_Form::ACTION_SUBMIT ) ), 0, 32 );
}

/** The answers a good interest carries, as the form posts them; null takes one out. */
function form_posted( array $more = array() ) {
	$values = array_merge(
		array(
			'name'           => 'Ines Example',
			'email'          => 'ines@example.test',
			'institution'    => 'Example University',
			'country'        => 'recCOUNTRY0000002',
			'city'           => 'Porto',
			'website'        => 'university.example.test',
			'department'     => 'Computer Science',
			'representative' => 'Rui Example',
			'contact_email'  => 'rui@example.test',
			'internships'    => array( 'Based on required hours (e.g. 150 hours)', 'Flexible duration or year-round' ),
			'anything'       => 'Our practices run in the spring.',
			'told'           => '1',
			'consent'        => '1',
		),
		$more
	);

	return array_filter(
		$values,
		static function ( $value ) {
			return null !== $value;
		}
	);
}

/** Read a redirect the way the page behind it would. */
function form_landed( $url ) {
	$stash = array();

	if ( preg_match( '/[?&]' . WPCPM_Student_Interest_Form::QUERY_STASH . '=([^&#]+)/', $url, $id ) ) {
		$stash = (array) get_transient( WPCPM_Student_Interest_Form::TRANSIENT_PREFIX . $id[1] );
	} elseif ( preg_match( '/[?&]' . WPCPM_Student_Interest_Form::QUERY_OUTCOME . '=([^&#]+)/', $url, $said ) ) {
		$stash = array( 'outcome' => $said[1] );
	}

	return array(
		'outcome' => isset( $stash['outcome'] ) ? $stash['outcome'] : '',
		'stash'   => $stash,
		'url'     => $url,
	);
}

/**
 * Post the form once and answer with where it landed and what the stash holds.
 *
 * @param array $answers What to post.
 * @param array $extra   `token`, `honeypot`, `nonce`, `ip`.
 * @return array `outcome`, `stash`, `url`.
 */
function form_submit( array $answers, array $extra = array() ) {
	$_POST = array(
		'action'                                    => WPCPM_Student_Interest_Form::ACTION_SUBMIT,
		'_wpnonce'                                  => array_key_exists( 'nonce', $extra ) ? $extra['nonce'] : wp_create_nonce( WPCPM_Student_Interest_Form::ACTION_SUBMIT ),
		WPCPM_Student_Interest_Form::FIELD_ANSWERS  => wp_slash( $answers ),
		WPCPM_Student_Interest_Form::TOKEN_FIELD    => array_key_exists( 'token', $extra ) ? $extra['token'] : form_token(),
		WPCPM_Student_Interest_Form::HONEYPOT       => isset( $extra['honeypot'] ) ? $extra['honeypot'] : '',
	);

	if ( isset( $extra['ip'] ) ) {
		$_SERVER['REMOTE_ADDR'] = $extra['ip'];
	}

	$out = run( array( 'WPCPM_Student_Interest_Form', 'handle_submit' ) );

	return form_landed( str_replace( 'redirect: ', '', $out['stop'] ) );
}

/** The page as the request a redirect led to would draw it. */
function form_redraw( array $landed ) {
	$_GET = array();

	if ( preg_match( '/[?&]' . WPCPM_Student_Interest_Form::QUERY_STASH . '=([^&#]+)/', $landed['url'], $id ) ) {
		$_GET[ WPCPM_Student_Interest_Form::QUERY_STASH ] = $id[1];
	} elseif ( preg_match( '/[?&]' . WPCPM_Student_Interest_Form::QUERY_OUTCOME . '=([^&#]+)/', $landed['url'], $said ) ) {
		$_GET[ WPCPM_Student_Interest_Form::QUERY_OUTCOME ] = $said[1];
	}

	$html = WPCPM_Student_Interest_Form::render();
	$_GET = array();

	return $html;
}

/** Every interest, oldest first. */
function form_rows() {
	return WPCPM_Student_Interest::in_states( WPCPM_Student_Interest::states(), 0, 'ASC' );
}

/** The one interest this check made, or null. */
function form_only() {
	$rows = form_rows();

	return 1 === count( $rows ) ? $rows[0] : null;
}

/** The state and the signals of the one interest, or null for none. */
function form_only_state() {
	$row = form_only();

	return $row ? array( WPCPM_Student_Interest::state_of( $row ), WPCPM_Student_Interest::signals_of( $row ) ) : null;
}

/** One method of a class's source, up to the end of its body. */
function form_method_body( $src, $name ) {
	$body = substr( $src, (int) strpos( $src, 'function ' . $name . '(' ) );

	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}

/** The sentence drawn under one question, as the page prints it, or null for none. */
function form_problem_drawn( $page, $key ) {
	return 1 === preg_match( '#id="wpcpm-student-interest-' . str_replace( '_', '-', $key ) . '-problem">(.*?)</span>#', $page, $found ) ? $found[1] : null;
}

echo "\n--- The hooks, the names and the block ---\n";

world();
$GLOBALS['hooks'] = array();
WPCPM_Student_Interest_Form::init();

ck( 'the form posts for visitors signed in and signed out alike, registers on init, keeps its page out of the cache and watches pages as they are saved',
	array( isset( $GLOBALS['hooks']['init'] ), isset( $GLOBALS['hooks']['admin_post_wpcpm_student_interest'] ), isset( $GLOBALS['hooks']['admin_post_nopriv_wpcpm_student_interest'] ), isset( $GLOBALS['hooks']['template_redirect'] ), $GLOBALS['hooks']['save_post_page'] ?? null ),
	array( true, true, true, true, array( array( 'WPCPM_Student_Interest_Form', 'track_page' ) ) ) );

$GLOBALS['hooks'] = array();
WPCPM_Student_Interest_Form::register();

ck( 'registering adds the shortcode and the block, and the mentor form\'s stylesheet under its own handle',
	array( isset( $GLOBALS['hooks']['shortcode:wpcpm_student_interest'] ), isset( $GLOBALS['hooks']['block:student-interest'] ), $GLOBALS['registered'][ WPCPM_Mentor_Application_Form::STYLE ] ),
	array( true, true, 'https://example.test/wp-content/plugins/wpcredits-program-manager/assets/css/application.css' ) );
ck( 'the names it goes by',
	array(
		WPCPM_Student_Interest_Form::SHORTCODE, WPCPM_Student_Interest_Form::BLOCK, WPCPM_Student_Interest_Form::OPT_PAGE,
		WPCPM_Student_Interest_Form::ACTION_SUBMIT, WPCPM_Student_Interest_Form::DWELL_SCOPE, WPCPM_Student_Interest_Form::ACTOR_PREFIX, WPCPM_Student_Interest_Form::SITE_KEY, WPCPM_Student_Interest_Form::MAIL_KEY,
		WPCPM_Student_Interest_Form::TRANSIENT_PREFIX, WPCPM_Student_Interest_Form::TRANSIENT_MINUTES, WPCPM_Student_Interest_Form::PER_HOUR,
		WPCPM_Student_Interest_Form::QUERY_STASH, WPCPM_Student_Interest_Form::QUERY_OUTCOME, WPCPM_Student_Interest_Form::FIELD_ANSWERS,
		WPCPM_Student_Interest_Form::HONEYPOT, WPCPM_Student_Interest_Form::TOKEN_FIELD, WPCPM_Student_Interest_Form::MAX_TEXT,
		WPCPM_Student_Interest_Form::SETTING_OPEN, WPCPM_Student_Interest_Form::SETTING_NOTIFY, WPCPM_Student_Interest_Form::MAIL_RECEIVED, WPCPM_Student_Interest_Form::MAIL_NOTICE,
	),
	array(
		'wpcpm_student_interest', 'wpcpm/student-interest', 'wpcpm_student_interest_page_id',
		'wpcpm_student_interest', 'wpcpm-student-interest-dwell', 'student-interest', 'student-interest-site', 'student-interest-mail',
		'wpcpm_sint_', 30, 5,
		'wpcpm_sint', 'wpcpm_sint_said', 'wpcpm_student_interest',
		'wpcpm_confirm_faculty', 'wpcpm_student_interest_token', 2000,
		'student_interest_open', 'student_interest_notify', 'student-interest-received', 'student-interest-notice',
	) );
ck( 'the long text is the registration form\'s length, read off its source',
	1 === preg_match( '/const MAX_TEXT = (\d+);/', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-registration-form.php' ), $registration_max ) ? (int) $registration_max[1] : null,
	WPCPM_Student_Interest_Form::MAX_TEXT );

$block_json = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/student-interest/block.json' ), true );
$model_json = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/student-registration/block.json' ), true );

ck( 'the block: its name, title, description, keywords and the two attributes the registration block has, with its supports',
	array( $block_json['name'], $block_json['title'], $block_json['description'], $block_json['keywords'], $block_json['attributes'] === $model_json['attributes'], $block_json['supports'] === $model_json['supports'], $block_json['editorScript'], $block_json['textdomain'] ),
	array( 'wpcpm/student-interest', 'Student Interest Form', 'The public form a student whose institution is not in the program yet fills in to say they would like to take part.', array( 'student', 'interest', 'institution', 'wpcredits' ), true, true, 'file:./editor.js', 'wpcredits-program-manager' ) );

$editor = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/student-interest/editor.js' );
$asset  = require WPCPM_PLUGIN_DIR . 'blocks/student-interest/editor.asset.php';

ck( 'and its editor script registers it, with a summary for the preview, and names no other form\'s block',
	array( false !== strpos( $editor, "registerBlockType( 'wpcpm/student-interest'" ), false !== strpos( $editor, "block: 'wpcpm/student-interest'" ), false !== strpos( $editor, 'student-registration' ), in_array( 'wp-server-side-render', $asset['dependencies'], true ) ),
	array( true, true, false, true ) );

echo "\n--- The thirteen questions ---\n";

$fields = WPCPM_Student_Interest_Form::fields();

ck( 'the thirteen questions, in the old form\'s order',
	array_keys( $fields ),
	array( 'name', 'email', 'institution', 'country', 'city', 'website', 'department', 'representative', 'contact_email', 'internships', 'anything', 'told', 'consent' ) );
ck( 'their labels, word for word',
	array_column( $fields, 'label' ),
	array(
		'Your Name',
		'Your Email',
		'Institution Name',
		'Country',
		'City',
		'Institution Website',
		'Department',
		'Institution Representative',
		'Contact Email',
		'How do your internships or practices typically work?',
		'Anything else you\'d like us to know?',
		'The representative\'s permission',
		'Privacy Policy Compliance',
	) );
ck( 'their kinds', array_column( $fields, 'type' ), array( 'text', 'email', 'text', 'country', 'text', 'url', 'text', 'text', 'email', 'choices', 'textarea', 'statement', 'consent' ) );
ck( 'every one required but the department and the free text',
	array_keys(
		array_filter(
			$fields,
			function ( $spec ) {
				return empty( $spec['required'] );
			}
		)
	),
	array( 'department', 'anything' ) );
ck( 'the three lines of help, word for word, and no other',
	array_filter( array_map(
		function ( $spec ) {
			return isset( $spec['help'] ) ? $spec['help'] : null;
		},
		$fields
	) ),
	array(
		'name'           => 'Your name and surname.',
		'representative' => 'The name of a representative from your institution. Make sure they know about this request and have agreed to be contacted.',
		'contact_email'  => 'The representative\'s email address.',
	) );
ck( 'the internships question offers the institution application\'s choices, and several may be chosen',
	array( $fields['internships']['choices'], $fields['internships']['choices'] === WPCPM_Institution_Application::internship_choices(), in_array( WPCPM_Institution_Application::other_choice(), $fields['internships']['choices'], true ) ),
	array( WPCPM_Institution_Application::internship_choices(), true, true ) );
ck( 'the two boxes\' words: the representative\'s agreement, and the registration form\'s privacy sentence with the policy\'s address its link',
	array( $fields['told']['box'], $fields['consent']['box'] ),
	array(
		'I have told the representative about this request, and they agreed that I share their name and email address with the WordPress Credits program.',
		'By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (<a href="https://wordpress.org/about/privacy/">https://wordpress.org/about/privacy/</a>).',
	) );
ck( 'the introduction is two sentences, word for word',
	WPCPM_Student_Interest_Form::introduction(),
	array(
		'If you are a student who would like to join WordPress Credits and your institution is not yet part of the program, tell us about it here and we will get in touch with the representative you name.',
		'Before you send this form, tell the representative and get their permission to share their contact details with us.',
	) );
ck( 'the countries are the site\'s copy, record ID to name, A to Z',
	WPCPM_Student_Interest_Form::countries(),
	array( 'recCOUNTRY0000001' => 'Argentina', 'recCOUNTRY0000003' => 'Poland', 'recCOUNTRY0000002' => 'Portugal' ) );

echo "\n--- Each answer, cleaned ---\n";

world();

$problem = static function ( $key, $raw ) {
	$cleaned = WPCPM_Student_Interest_Form::clean( $key, $raw );

	return $cleaned['ok'] ? 'ok:' . ( is_array( $cleaned['value'] ) ? implode( '|', $cleaned['value'] ) : var_export( $cleaned['value'], true ) ) : $cleaned['problem'];
};

ck( 'a name: kept with its white space collapsed, a formula, a tab or a bar and a length outside 2 to 200 refused',
	array( $problem( 'name', ' Ines   Example ' ), $problem( 'name', '=HYPERLINK("x")' ), $problem( 'name', "Ines\tExample" ), $problem( 'name', 'Ines|Example' ), $problem( 'name', 'I' ), $problem( 'name', str_repeat( 'a', 201 ) ), $problem( 'name', array( 'x' ) ) ),
	array( "ok:'Ines Example'", 'formula', 'control', 'control', 'name_length', 'name_length', 'not_scalar' ) );
ck( 'an empty answer is left for requiredness',
	array( WPCPM_Student_Interest_Form::clean( 'name', '' ), WPCPM_Student_Interest_Form::clean( 'email', '' ), WPCPM_Student_Interest_Form::clean( 'country', '' ), WPCPM_Student_Interest_Form::clean( 'website', '' ), WPCPM_Student_Interest_Form::clean( 'anything', '' )['ok'] ),
	array_merge( array_fill( 0, 4, array( 'ok' => true, 'value' => '', 'problem' => '' ) ), array( true ) ) );
ck( 'the two addresses: an address kept, anything else refused',
	array( $problem( 'email', ' ines@example.test ' ), $problem( 'email', 'not-an-address' ), $problem( 'contact_email', 'rui@example.test' ), $problem( 'contact_email', 'rui at example' ), $problem( 'email', array( 'ines@example.test' ) ) ),
	array( "ok:'ines@example.test'", 'bad_email', "ok:'rui@example.test'", 'bad_email', 'not_scalar' ) );
ck( 'the institution, the city, the department and the representative: one line each, a formula or a control refused, each held to its length',
	array(
		$problem( 'institution', '  Example   University ' ),
		$problem( 'institution', '@Example' ),
		$problem( 'institution', str_repeat( 'u', 201 ) ),
		$problem( 'city', "Por\nto" ),
		$problem( 'city', str_repeat( 'c', 121 ) ),
		$problem( 'city', str_repeat( 'c', 120 ) ) === 'ok:' . var_export( str_repeat( 'c', 120 ), true ),
		$problem( 'department', '' ),
		$problem( 'department', str_repeat( 'd', 201 ) ),
		$problem( 'representative', '-Rui' ),
		$problem( 'representative', 'R' ),
	),
	array( "ok:'Example University'", 'formula', 'too_long', 'control', 'too_long', true, "ok:''", 'too_long', 'formula', "ok:'R'" ) );
ck( 'the country: a record ID the list holds is kept, anything else refused, a name among them',
	array( $problem( 'country', 'recCOUNTRY0000002' ), $problem( 'country', 'recCOUNTRY0000009' ), $problem( 'country', 'Portugal' ), $problem( 'country', array( 'recCOUNTRY0000002' ) ) ),
	array( "ok:'recCOUNTRY0000002'", 'bad_choice', 'bad_choice', "ok:''" ) );
ck( 'the website goes through WPCPM_Field_Value: a scheme-less address gains https, a space, a host with no dot and another scheme are refused, and one longer than its box is refused, not cut',
	array(
		$problem( 'website', 'university.example.test' ),
		$problem( 'website', 'http://university.example.test/about' ),
		$problem( 'website', 'not a site' ),
		$problem( 'website', 'university' ),
		$problem( 'website', 'javascript:alert(1)' ),
		$problem( 'website', 'mailto:rui@example.test' ),
		$problem( 'website', 'university.example.test:8080/credits' ),
		$problem( 'website', 'example.test/' . str_repeat( 'a', 255 ) ),
		$problem( 'website', array( 'example.test' ) ),
	),
	array( "ok:'https://university.example.test'", "ok:'http://university.example.test/about'", 'bad_url', 'bad_url', 'bad_url', 'bad_url', "ok:'https://university.example.test:8080/credits'", 'too_long', 'not_scalar' ) );
ck( 'the internships: the choices the list holds, each once, in its order, kept without the space Airtable\'s first choice begins with; one it lacks dropped',
	array(
		$problem( 'internships', array( 'Flexible duration or year-round', ' Based on required hours (e.g. 150 hours)', 'Based on required hours (e.g. 150 hours)', 'Whenever' ) ),
		$problem( 'internships', 'Other (please specify)' ),
		$problem( 'internships', array( 'Whenever' ) ),
		$problem( 'internships', array( array( 'Flexible duration or year-round' ) ) ),
	),
	array( 'ok:Based on required hours (e.g. 150 hours)|Flexible duration or year-round', 'ok:Other (please specify)', 'ok:', 'ok:' ) );
ck( 'the free text: kept with its lines, cut at 2,000 characters, and refused where the cleaner would take words out of it',
	array(
		$problem( 'anything', "One line.\nAnother." ),
		mb_strlen( WPCPM_Student_Interest_Form::clean( 'anything', str_repeat( 'a', 2500 ) )['value'] ),
		$problem( 'anything', 'We use <b>bold</b> a lot' ),
	),
	array( "ok:'One line.\nAnother.'", 2000, 'markup' ) );
ck( 'a box is ticked only by the value its control carries',
	array( $problem( 'told', '1' ), $problem( 'told', 'yes' ), $problem( 'consent', '1' ), $problem( 'consent', array( '1' ) ), $problem( 'consent', '' ) ),
	array( 'ok:true', 'ok:false', 'ok:true', 'ok:false', 'ok:false' ) );
ck( 'a question the form does not ask is refused', $problem( 'role', 'administrator' ), 'unknown_field' );

$said = array();

foreach ( array( 'required', 'formula', 'control', 'name_length', 'bad_email', 'bad_choice', 'bad_url', 'unticked', 'consent', 'not_scalar' ) as $slug ) {
	$said[ $slug ] = WPCPM_Student_Interest_Form::problem_text( $slug, $fields['institution'] );
}

ck( 'every refusal has its sentence, the website\'s the one every form says (WPCPM_Field_Value::bad_url_text())',
	$said,
	array(
		'required'    => 'Fill this in.',
		'formula'     => 'This cannot start with =, +, - or @.',
		'control'     => 'This cannot hold a tab, a line break or a "|".',
		'name_length' => 'Give your full name, from 2 to 200 characters.',
		'bad_email'   => 'That does not look like an email address.',
		'bad_choice'  => 'Choose one of the options offered.',
		'bad_url'     => WPCPM_Field_Value::bad_url_text(),
		'unticked'    => 'Tick this box to send the form.',
		'consent'     => 'Tick this box to send the form.',
		'not_scalar'  => 'That answer could not be read. Write it again.',
	) );
ck( 'a length names the box\'s own, a list of boxes left empty says to tick one, and the free text\'s loss names its box',
	array(
		WPCPM_Student_Interest_Form::problem_text( 'too_long', $fields['city'] ),
		WPCPM_Student_Interest_Form::problem_text( 'too_long', $fields['website'] ),
		WPCPM_Student_Interest_Form::problem_text( 'required', $fields['internships'] ),
		WPCPM_Student_Interest_Form::problem_text( 'markup', $fields['anything'] ) === WPCPM_Typed_Text::loss_message( 'Anything else you\'d like us to know?', 'send' ),
	),
	array( 'Keep this to 120 characters or fewer.', 'Keep this to 255 characters or fewer.', 'Tick at least one.', true ) );

world();

$stays = interest_in( 'new' );
$goes  = interest_in( 'closed', array( 'institution' => 'Sample College' ) );

$GLOBALS['undeletable'] = array( $stays );
$refused                = WPCPM_Student_Interest::erase( 'ines@example.test' );
unset( $GLOBALS['undeletable'] );

ck( 'an interest WordPress would not delete is reported as kept, with a sentence saying where to delete it, and the ones it did delete as removed',
	array( $refused['items_removed'], $refused['items_retained'], $refused['messages'], $refused['done'], null !== get_post( $stays ), null === get_post( $goes ) ),
	array( true, true, array( '1 interest from a student could not be deleted and is still stored. Delete it from the Interests tab of the Students screen.' ), true, true, true ) );

echo "\n--- The page an editor places the form on ---\n";

world();
unset( $GLOBALS['posts'][42], $GLOBALS['slugs'][42], $GLOBALS['opts'][ WPCPM_Student_Interest_Form::OPT_PAGE ] );

ck( 'the form makes no page of its own', method_exists( 'WPCPM_Student_Interest_Form', 'ensure_page' ), false );
ck( 'a page\'s content holds the form by its block, with or without attributes, or by its shortcode; a block or a shortcode of a longer name, or the words alone, are not it',
	array(
		WPCPM_Student_Interest_Form::content_holds_form( '<!-- wp:wpcpm/student-interest /-->' ),
		WPCPM_Student_Interest_Form::content_holds_form( '<p>x</p><!-- wp:wpcpm/student-interest {"title":"Tell us"} /-->' ),
		WPCPM_Student_Interest_Form::content_holds_form( 'Before [wpcpm_student_interest] after' ),
		WPCPM_Student_Interest_Form::content_holds_form( '[wpcpm_student_interest title="Tell us"]' ),
		WPCPM_Student_Interest_Form::content_holds_form( '<!-- wp:wpcpm/student-interests-list /-->' ),
		WPCPM_Student_Interest_Form::content_holds_form( '<!-- wp:wpcpm/student-registration /-->' ),
		WPCPM_Student_Interest_Form::content_holds_form( 'The student interest form is elsewhere.' ),
		WPCPM_Student_Interest_Form::content_holds_form( '' ),
	),
	array( true, true, true, true, false, false, false, false ) );

/** A page with this content and status, saved, as `save_post_page` hands it over. */
function page_saved( $id, $content, $status = 'publish', $type = 'page' ) {
	$page               = new WP_Post();
	$page->ID           = (int) $id;
	$page->post_type    = $type;
	$page->post_status  = $status;
	$page->post_content = $content;

	$GLOBALS['posts'][ (int) $id ] = $page;
	$GLOBALS['slugs'][ (int) $id ] = 'page-' . (int) $id;

	WPCPM_Student_Interest_Form::track_page( (int) $id, $page );

	return get_option( WPCPM_Student_Interest_Form::OPT_PAGE, 'none' );
}

$steps = array();

$steps['a page without the form']                    = page_saved( 60, '<p>About us.</p>' );
$steps['a post of another type holding the block']   = page_saved( 61, '<!-- wp:wpcpm/student-interest /-->', 'publish', 'post' );
$steps['a page holding the block']                   = page_saved( 62, '<!-- wp:wpcpm/student-interest /-->' );
$steps['saved again']                                = page_saved( 62, '<p>Intro.</p><!-- wp:wpcpm/student-interest /-->' );
$steps['a second page holding the shortcode']        = page_saved( 63, '[wpcpm_student_interest]' );
$steps['the second page losing it']                  = page_saved( 63, '<p>Nothing.</p>' );
$steps['a page that never held it, saved']           = page_saved( 60, '<p>About us, again.</p>' );
$steps['the form\'s page as a draft']                = page_saved( 62, '<!-- wp:wpcpm/student-interest /-->', 'draft' );

$takeover = array(
	'a draft holding the form, first'   => ( delete_option( WPCPM_Student_Interest_Form::OPT_PAGE ) || true ) ? page_saved( 70, '[wpcpm_student_interest]', 'draft' ) : 0,
	'a published page holding it, next' => page_saved( 71, '[wpcpm_student_interest]' ),
	'a second published page'           => page_saved( 72, '[wpcpm_student_interest]' ),
);

ck( 'a draft never keeps the published page from being found: a published page holding the form takes a draft\'s place, and no other published page takes its own',
	$takeover,
	array( 'a draft holding the form, first' => 70, 'a published page holding it, next' => 71, 'a second published page' => 71 ) );
update_option( WPCPM_Student_Interest_Form::OPT_PAGE, $steps['the form\'s page as a draft'], false );
unset( $GLOBALS['posts'][70], $GLOBALS['posts'][71], $GLOBALS['posts'][72] );

ck( 'a page holds the form when it has the block or a whole shortcode tag WordPress would run, and not when it shows the shortcode in doubled brackets as an example, or names another shortcode that starts the same',
	array(
		WPCPM_Student_Interest_Form::content_holds_form( '[wpcpm_student_interest]' ),
		WPCPM_Student_Interest_Form::content_holds_form( '<p>Write [[wpcpm_student_interest]] on a page.</p>' ),
		WPCPM_Student_Interest_Form::content_holds_form( '[wpcpm_student_interest_other]' ),
		WPCPM_Student_Interest_Form::content_holds_form( '[[wpcpm_student_interest]] and then [wpcpm_student_interest title="x"]' ),
		WPCPM_Student_Interest_Form::content_holds_form( '<!-- wp:wpcpm/student-interest /-->' ),
	),
	array( true, false, false, true, true ) );
ck( 'a page that writes about the shortcode in a code or a pre element, as the Administrator guide does, is not the form\'s page; one that also holds the shortcode outside it is',
	array(
		WPCPM_Student_Interest_Form::content_holds_form( '<p>Add the <code>[wpcpm_student_interest]</code> shortcode to a page.</p>' ),
		WPCPM_Student_Interest_Form::content_holds_form( "<pre class=\"x\">\n[wpcpm_student_interest]\n</pre>" ),
		WPCPM_Student_Interest_Form::content_holds_form( '<p><code>[wpcpm_student_interest]</code></p>[wpcpm_student_interest]' ),
	),
	array( false, false, true ) );

ck( 'the option follows the page the form is on: set by the first page that holds the block or the shortcode, kept through its saves and a draft of it, never taken by a second page, and untouched by any other save',
	$steps,
	array(
		'a page without the form'                  => 'none',
		'a post of another type holding the block' => 'none',
		'a page holding the block'                 => 62,
		'saved again'                              => 62,
		'a second page holding the shortcode'      => 62,
		'the second page losing it'                => 62,
		'a page that never held it, saved'         => 62,
		'the form\'s page as a draft'              => 62,
	) );

$as_draft = WPCPM_Student_Interest_Form::page_url();
page_saved( 62, '<!-- wp:wpcpm/student-interest /-->' );

ck( 'the address it is found at is the permalink while that page is published, and none while it is a draft',
	array( $as_draft, WPCPM_Student_Interest_Form::page_url() ),
	array( '', 'https://example.test/page-62/' ) );

$cleared = array();

$cleared['the form taken off its page'] = page_saved( 62, '<p>The form moved.</p>' );
$cleared['its address then']            = WPCPM_Student_Interest_Form::page_url();
$cleared['the other page takes over']   = page_saved( 63, '[wpcpm_student_interest]' );
$cleared['that page trashed']           = page_saved( 63, '[wpcpm_student_interest]', 'trash' );
$cleared['its address then, again']     = WPCPM_Student_Interest_Form::page_url();
$cleared['a trashed page is not taken'] = page_saved( 64, '[wpcpm_student_interest]', 'trash' );

ck( 'the option is cleared when its page no longer holds the form, or is trashed, and the next page that holds it is taken',
	$cleared,
	array(
		'the form taken off its page' => 'none',
		'its address then'            => '',
		'the other page takes over'   => 63,
		'that page trashed'           => 'none',
		'its address then, again'     => '',
		'a trashed page is not taken' => 'none',
	) );

// A page deleted for good never passes through `save_post_page`: the option it leaves behind must
// not keep the next page that holds the form from becoming the form's page.
$deleted = array();

$deleted['a page holding the form']         = page_saved( 70, '<!-- wp:wpcpm/student-interest /-->' );
unset( $GLOBALS['posts'][70], $GLOBALS['slugs'][70] );
$deleted['its address once it is gone']     = WPCPM_Student_Interest_Form::page_url();
$deleted['the option still names it']       = get_option( WPCPM_Student_Interest_Form::OPT_PAGE, 'none' );
$deleted['a replacement holding the form']  = page_saved( 71, '<!-- wp:wpcpm/student-interest /-->' );
WPCPM_Student_Interest_Form::forget_page( 99 );
$deleted['another post deleted for good']   = get_option( WPCPM_Student_Interest_Form::OPT_PAGE, 'none' );
WPCPM_Student_Interest_Form::forget_page( 71 );
$deleted['the form\'s page deleted for good'] = get_option( WPCPM_Student_Interest_Form::OPT_PAGE, 'none' );

ck( 'an option naming a page that is gone lets the next page holding the form take its place, and the form\'s page deleted for good is forgotten, as no other post is',
	$deleted,
	array(
		'a page holding the form'          => 70,
		'its address once it is gone'      => '',
		'the option still names it'        => 70,
		'a replacement holding the form'   => 71,
		'another post deleted for good'    => 71,
		'the form\'s page deleted for good' => 'none',
	) );

$GLOBALS['opts'][ WPCPM_Student_Interest_Form::OPT_PAGE ] = 9999;

ck( 'an option naming a page that is gone gives no address', WPCPM_Student_Interest_Form::page_url(), '' );

world();

$hint = array( 'open and published' => WPCPM_Student_Interest_Form::hint_url() );

$GLOBALS['settings']['student_interest_open'] = false;
$hint['closed'] = WPCPM_Student_Interest_Form::hint_url();

$GLOBALS['settings']['student_interest_open'] = true;
$GLOBALS['posts'][42]->post_status            = 'private';
$hint['its page private']                     = WPCPM_Student_Interest_Form::hint_url();

ck( 'the address the registration form\'s hint is handed: the page\'s while the form is open and the page published, none otherwise',
	$hint,
	array( 'open and published' => 'https://example.test/student-interest/', 'closed' => '', 'its page private' => '' ) );

world();
$GLOBALS['on_page'] = 9999;
WPCPM_Student_Interest_Form::no_cache();
$elsewhere          = $GLOBALS['nocache'];
$GLOBALS['on_page'] = 42;
WPCPM_Student_Interest_Form::no_cache();

ck( 'its page alone is kept out of every cache', array( $elsewhere, $GLOBALS['nocache'], defined( 'DONOTCACHEPAGE' ) ), array( 0, 1, true ) );

echo "\n--- The page ---\n";

world();
$page = WPCPM_Student_Interest_Form::render();

ck( 'the switch is on unless an Administrator switches it off', array( WPCPM_Student_Interest_Form::is_open(), ( static function () { $GLOBALS['settings']['student_interest_open'] = false; $open = WPCPM_Student_Interest_Form::is_open(); unset( $GLOBALS['settings']['student_interest_open'] ); return $open; } )() ), array( true, false ) );
ck( 'the open form is drawn under its two sentences, posts to admin-post.php with its action, nonce and token, and carries a honeypot',
	array(
		1 === preg_match( '#^<div class="wpcpm-application" id="wpcpm-student-interest"><p class="wpcpm-application-intro">If you are a student who would like to join WordPress Credits[^<]*</p><p class="wpcpm-application-intro">Before you send this form, tell the representative[^<]*</p><form class="wpcpm-application__form" method="post" action="https://example.test/wp-admin/admin-post.php"#', $page ),
		false !== strpos( $page, '<input type="hidden" name="action" value="wpcpm_student_interest" />' ),
		false !== strpos( $page, '<input type="hidden" name="_wpnonce" value="nonce-wpcpm_student_interest" />' ),
		1 === preg_match( '#<input type="hidden" name="wpcpm_student_interest_token" value="\d+\.[A-Za-z0-9]+\.[a-f0-9]{32}" />#', $page ),
		false !== strpos( $page, 'name="wpcpm_confirm_faculty"' ),
		false !== strpos( $page, '<button type="submit" class="wpcpm-button">Send this form</button>' ),
		$GLOBALS['nocache'] > 0,
		in_array( WPCPM_Mentor_Application_Form::STYLE, $GLOBALS['enqueued'], true ) && in_array( 'wpcpm-forms', $GLOBALS['enqueued'], true ),
	),
	array( true, true, true, true, true, true, true, true ) );

$order = array();

foreach ( array_keys( $fields ) as $key ) {
	$order[ $key ] = strpos( $page, 'id="wpcpm-student-interest-' . str_replace( '_', '-', $key ) . '"' );
}

$sorted = $order;
asort( $sorted );

ck( 'every question is drawn once, in the form\'s order', array( array_keys( $sorted ), in_array( false, $order, true ) ), array( array_keys( $fields ), false ) );

// The groups are how the form is laid out, never what is asked or in which order.
$groups     = WPCPM_Student_Interest_Form::groups();
$group_seq  = array();
$group_rank = array_flip( array_keys( $groups ) );

foreach ( $fields as $key => $spec ) {
	$group_seq[] = isset( $spec['group'], $group_rank[ $spec['group'] ] ) ? $group_rank[ $spec['group'] ] : -1;
}

$ascending = $group_seq;
sort( $ascending );

ck( 'the questions are drawn in five groups, each under its heading; every question names a group that exists, and no group takes a question out of the form\'s order',
	array( $groups, in_array( -1, $group_seq, true ), $group_seq === $ascending, array_values( array_unique( $group_seq ) ) ),
	array(
		array(
			'about'          => 'About you',
			'institution'    => 'Your institution',
			'representative' => 'The representative',
			'more'           => 'More about your institution',
			'last'           => 'Before you send this',
		),
		false,
		true,
		array( 0, 1, 2, 3, 4 ),
	) );
ck( 'each group is a fieldset with its heading as the legend and one box that sets its questions in columns, the institution\'s name alone taking a whole row among the short answers, and the form ends on its button',
	array(
		substr_count( $page, '<fieldset class="wpcpm-application__group"><legend>' ),
		substr_count( $page, '</legend><div class="wpcpm-application__columns">' ),
		substr_count( $page, '</div></fieldset>' ),
		false !== strpos( $page, '<fieldset class="wpcpm-application__group"><legend>About you</legend><div class="wpcpm-application__columns"><p class="wpcpm-field wpcpm-field--text">' ),
		substr_count( $page, 'wpcpm-field--wide' ),
		false !== strpos( $page, '<p class="wpcpm-field wpcpm-field--text wpcpm-field--wide"><label for="wpcpm-student-interest-institution">' ),
		false !== strpos( $page, '</div></fieldset><p class="wpcpm-application__send"><button type="submit" class="wpcpm-button">Send this form</button>' ),
	),
	array( 5, 5, 5, true, 1, true, true ) );
ck( 'each as its kind: the name and the addresses as inputs with their lengths, the website a text input that takes an address, the country one field to type and pick, the internships four boxes, the free text an area of 2,000, and two boxes to tick',
	array(
		false !== strpos( $page, '<input type="text" id="wpcpm-student-interest-name" name="wpcpm_student_interest[name]" value="" maxlength="200" autocomplete="name" required="required" aria-describedby="wpcpm-student-interest-name-help" />' ),
		false !== strpos( $page, '<input type="email" id="wpcpm-student-interest-contact-email" name="wpcpm_student_interest[contact_email]" value="" maxlength="254" required="required" aria-describedby="wpcpm-student-interest-contact-email-help" />' ),
		false !== strpos( $page, '<input type="text" id="wpcpm-student-interest-website" name="wpcpm_student_interest[website]" value="" maxlength="255" inputmode="url" required="required" />' ),
		false !== strpos( $page, '<input type="text" id="wpcpm-student-interest-department" name="wpcpm_student_interest[department]" value="" maxlength="200" />' ),
		false !== strpos( $page, '<select name="wpcpm_student_interest[country]" id="wpcpm-student-interest-country" autocomplete="off">' ),
		false !== strpos( $page, '<option value="" selected=\'selected\'>Choose a country</option><option value="recCOUNTRY0000001">Argentina</option><option value="recCOUNTRY0000003">Poland</option><option value="recCOUNTRY0000002">Portugal</option></select>' ),
		substr_count( $page, 'name="wpcpm_student_interest[internships][]"' ),
		false !== strpos( $page, '<label class="wpcpm-field__check"><input type="checkbox" name="wpcpm_student_interest[internships][]" value="Based on required hours (e.g. 150 hours)" /> <span>Based on required hours (e.g. 150 hours)</span></label>' ),
		false !== strpos( $page, '<textarea id="wpcpm-student-interest-anything" name="wpcpm_student_interest[anything]" rows="4" maxlength="2000"></textarea>' ),
		false !== strpos( $page, '<input type="checkbox" id="wpcpm-student-interest-told" name="wpcpm_student_interest[told]" value="1" required="required" /><label for="wpcpm-student-interest-told">I have told the representative about this request, and they agreed that I share their name and email address with the WordPress Credits program.</label>' ),
		false !== strpos( $page, '<input type="checkbox" id="wpcpm-student-interest-consent" name="wpcpm_student_interest[consent]" value="1" required="required" /><label for="wpcpm-student-interest-consent">By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (<a href="https://wordpress.org/about/privacy/">https://wordpress.org/about/privacy/</a>).</label>' ),
	),
	array( true, true, true, true, true, true, 4, true, true, true, true ) );
ck( 'the eleven required questions carry the mark, the two optional ones none, and each line of help is drawn under its question',
	array(
		substr_count( $page, '<span class="wpcpm-field__required">Required</span>' ),
		false !== strpos( $page, '<label for="wpcpm-student-interest-department">Department</label>' ),
		false !== strpos( $page, '<span class="wpcpm-field__hint" id="wpcpm-student-interest-name-help">Your name and surname.</span>' ),
		false !== strpos( $page, '<span class="wpcpm-field__hint" id="wpcpm-student-interest-representative-help">The name of a representative from your institution. Make sure they know about this request and have agreed to be contacted.</span>' ),
		false !== strpos( $page, '<span class="wpcpm-field__hint" id="wpcpm-student-interest-contact-email-help">The representative&#039;s email address.</span>' ),
	),
	array( 11, true, true, true, true ) );

$closed = array();

world();
$GLOBALS['settings']['student_interest_open'] = false;
$closed['the switch off, to a visitor'] = WPCPM_Student_Interest_Form::render();

an_account( 9, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );
$GLOBALS['uid']    = 9;
$GLOBALS['manage'] = array( 9 );
$closed['the switch off, to an Administrator'] = WPCPM_Student_Interest_Form::render();

world();
$GLOBALS['countries'] = array();
$closed['no country to offer, to a visitor'] = WPCPM_Student_Interest_Form::render();

an_account( 9, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );
$GLOBALS['uid']    = 9;
$GLOBALS['manage'] = array( 9 );
$closed['no country to offer, to an Administrator'] = WPCPM_Student_Interest_Form::render();

$closed_said = '<p class="wpcpm-application__message is-info" role="status">Interest from students through this form is closed for now.</p>';

ck( 'while it cannot be drawn the form says one sentence that it is closed, to everybody, and to an Administrator a second saying why; no form and no introduction',
	$closed,
	array(
		'the switch off, to a visitor'             => '<div class="wpcpm-application" id="wpcpm-student-interest">' . $closed_said . '</div>',
		'the switch off, to an Administrator'      => '<div class="wpcpm-application" id="wpcpm-student-interest">' . $closed_said . '<p class="wpcpm-application__notice">The student interest form is switched off. Turn on &quot;Student interest form&quot; on the Students and mentors tab of WPCredits Program &gt; Settings when the program is ready to take it.</p></div>',
		'no country to offer, to a visitor'        => '<div class="wpcpm-application" id="wpcpm-student-interest">' . $closed_said . '</div>',
		'no country to offer, to an Administrator' => '<div class="wpcpm-application" id="wpcpm-student-interest">' . $closed_said . '<p class="wpcpm-application__notice">This form is not shown yet: the countries list is empty, and every interest has to name a country. Run the institutions sync on the Institutions screen&#039;s Sync and storage tab.</p></div>',
	) );

echo "\n--- The thank-you, and the page's other words ---\n";

world();

$thanks_html = '<div class="wpcpm-application" id="wpcpm-student-interest"><div class="wpcpm-application__done"><h3>Thank you. We have received your interest and will get in touch with the representative you named.</h3><p>Nothing else is needed from you now.</p></div></div>';
$outcomes    = WPCPM_Student_Interest_Form::outcomes();

ck( 'the outcomes, in this form\'s words and the registration form\'s for the ones they share',
	array( $outcomes['lost'][1], $outcomes['closed'][1], $outcomes['stale'][1], $outcomes['expired'][1], $outcomes['busy'][1] === WPCPM_Form_Stash::outcomes()['busy'][1], array_keys( $outcomes ) ),
	array(
		'Something went wrong at our end and your interest was not saved. Try once more.',
		'Interest from students through this form is closed for now.',
		'This form had been open for a long time, so it was not sent. Your answers are still here: please send it again.',
		'This form had been open for a while and could not be sent. Your answers are still here: please send it again.',
		true,
		array( 'busy', 'busy-kept', 'busy-unkept', 'stash-gone', 'stale', 'expired', 'lost', 'closed' ),
	) );

$_GET = array( WPCPM_Student_Interest_Form::QUERY_OUTCOME => 'sent' );

ck( 'the thank-you is the confirmation alone: no form and no introduction', WPCPM_Student_Interest_Form::render(), $thanks_html );

$owner = array();

foreach ( array( 'block' => array( 'WPCPM_Student_Interest_Form', 'render_block' ), 'shortcode' => array( 'WPCPM_Student_Interest_Form', 'render' ) ) as $how => $draw ) {
	$drawn_owner   = call_user_func( $draw, array( 'thanks' => 'Thanks, <b>student</b>.' ) );
	$owner[ $how ] = array( false !== strpos( $drawn_owner, '<h3>Thanks, &lt;b&gt;student&lt;/b&gt;.</h3>' ), false !== strpos( $drawn_owner, 'We have received your interest' ), false !== strpos( $drawn_owner, '<form' ) );
}

ck( 'the owner\'s thank-you, given to the block or the shortcode, takes the plugin\'s place, escaped', $owner, array_fill_keys( array( 'block', 'shortcode' ), array( true, false, false ) ) );

$_GET = array();

ck( 'a title given to the block heads the page, escaped', 1 === preg_match( '#^<div class="wpcpm-application" id="wpcpm-student-interest"><h2 class="wpcpm-application__title">Tell us &lt;now&gt;</h2>#', WPCPM_Student_Interest_Form::render_block( array( 'title' => 'Tell us <now>' ) ) ), true );

$_GET = array( WPCPM_Student_Interest_Form::QUERY_OUTCOME => 'busy' );
$busy = WPCPM_Student_Interest_Form::render();
$_GET = array( WPCPM_Student_Interest_Form::QUERY_OUTCOME => 'unheard-of' );
$odd  = WPCPM_Student_Interest_Form::render();
$_GET = array();

ck( 'a busy answer says so and draws no form, and an outcome nobody defined is the plain form',
	array( false !== strpos( $busy, 'Too many attempts were made from this connection in this hour' ), false !== strpos( $busy, '<form' ), false !== strpos( $odd, '<form' ), false !== strpos( $odd, 'wpcpm-application__message' ) ),
	array( true, false, true, false ) );

echo "\n--- The submit path, in the registration form's order ---\n";

$source      = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-interest-form.php' );
$submit_path = form_method_body( $source, 'handle_submit' );
$offsets     = array(
	'actor'     => strpos( $submit_path, 'WPCPM_Form_Guard::claim_actor(' ),
	'nonce'     => strpos( $submit_path, 'wp_verify_nonce(' ),
	'closed'    => strpos( $submit_path, 'self::closed_reason()' ),
	'honeypot'  => strpos( $submit_path, 'WPCPM_Form_Guard::honeypot_filled(' ),
	'dwell'     => strpos( $submit_path, 'WPCPM_Form_Guard::check_token(' ),
	'answers'   => strpos( $submit_path, '$cleaned  = self::clean_all(' ),
	'required'  => strpos( $submit_path, 'self::add_required(' ),
	'told'      => strpos( $submit_path, "self::\$ticked['told']" ),
	'consent'   => strpos( $submit_path, "self::\$ticked['consent']" ),
	'score'     => strpos( $submit_path, 'self::score(' ),
	'site'      => strpos( $submit_path, 'WPCPM_Form_Guard::claim_site( self::SITE_KEY, WPCPM_Form_Guard::PER_DAY )' ),
	'duplicate' => strpos( $submit_path, 'WPCPM_Student_Interest::has_recent(' ),
	'store'     => strpos( $submit_path, 'WPCPM_Student_Interest::store(' ),
	'student'   => strpos( $submit_path, 'self::mail_student(' ),
	'notice'    => strpos( $submit_path, 'self::mail_administrators(' ),
	'landed'    => strpos( $submit_path, 'WPCPM_Form_Guard::landed(' ),
);
$in_order    = $offsets;
asort( $in_order );

ck( 'the submit path reads in the registration form\'s order, every step there: the ceiling before the nonce, both boxes before anything is scored or stored, the store before either email',
	array( array_keys( $in_order ), in_array( false, $offsets, true ) ), array( array_keys( $offsets ), false ) );

$new_files = array(
	'includes/modules/class-wpcpm-student-interest.php',
	'includes/modules/class-wpcpm-student-interest-form.php',
	'includes/modules/class-wpcpm-student-interests-tab.php',
	'includes/mail/templates/student-interests.php',
);
$reaches   = array();

foreach ( $new_files as $file ) {
	$code = '';

	// The code alone: a comment may say what the module does not do.
	foreach ( token_get_all( (string) file_get_contents( WPCPM_PLUGIN_DIR . $file ) ) as $token ) {
		if ( ! is_array( $token ) || ! in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
			$code .= is_array( $token ) ? $token[1] : $token;
		}
	}

	$reaches[ $file ] = array( (int) preg_match( '/WPCPM_Airtable|airtable\.com|wp_remote_|wp_insert_user|wp_create_user|register_rest_route|wp_register_ability/', $code ), (int) preg_match( '/\$[a-z_]+\[\s*\'(?:contact_email|representative)\'\s*\][^;]*send|send(?:_to)?\([^;]*contact_email/s', $code ) );
}

ck( 'no file of the module reaches Airtable or the network, makes an account, or registers a route or an ability, and none sends to the representative\'s address',
	$reaches, array_fill_keys( $new_files, array( 0, 0 ) ) );

world();
$first_five = array();

for ( $i = 0; $i < WPCPM_Student_Interest_Form::PER_HOUR; $i++ ) {
	$first_five[] = form_submit( form_posted(), array( 'nonce' => 'nonce-stale' ) )['outcome'];
}

$sixth     = form_submit( form_posted( array( 'email' => 'more@example.test' ) ) );
$unsigned  = form_submit( form_posted(), array( 'nonce' => 'nonce-stale' ) );
$too_long  = form_submit( form_posted( array( 'anything' => str_repeat( 'long ', 20000 ) ) ) );
$elsewhere = form_submit( form_posted(), array( 'ip' => '198.51.100.9' ) );

ck( '1. the per-actor ceiling comes first: five posts from one address in an hour are all taken, even those whose nonce failed; the sixth is refused and stores nothing, its answers kept only when its nonce holds and they fit; another address is not held by it',
	array( array_unique( $first_five ), count( $first_five ), $sixth['outcome'], $sixth['stash']['values']['name'] ?? null, $unsigned['outcome'], $too_long['outcome'], $elsewhere['outcome'], count( form_rows() ) ),
	array( array( 'expired' ), 5, 'busy-kept', 'Ines Example', 'busy', 'busy-unkept', 'sent', 1 ) );

world();
$expired = form_submit( form_posted(), array( 'nonce' => 'nonce-stale' ) );

ck( '2. a nonce that fails stores nothing and hands back every answer, the two ticks it was sent with among them',
	array( $expired['outcome'], count( form_rows() ), $expired['stash']['values']['name'] ?? null, $expired['stash']['values']['website'] ?? null, $expired['stash']['values']['told'] ?? null, $expired['stash']['values']['consent'] ?? null, $GLOBALS['mail'] ),
	array( 'expired', 0, 'Ines Example', 'https://university.example.test', true, true, array() ) );

world();
$GLOBALS['settings']['student_interest_open'] = false;
$shut = form_submit( form_posted() );

world();
$GLOBALS['countries'] = array();
$no_countries         = form_submit( form_posted() );

ck( '3. a post to a form that is switched off, or that has no country to offer, is answered closed and stores nothing',
	array( $shut['outcome'], $no_countries['outcome'], count( form_rows() ), $shut['url'] ),
	array( 'closed', 'closed', 0, 'https://example.test/student-interest/?wpcpm_sint_said=closed#wpcpm-student-interest' ) );

world();
$honey = form_submit( form_posted(), array( 'honeypot' => 'https://spam.example.test' ) );

ck( '4. a filled honeypot is stored as spam, thanked like anybody, and sends nothing to anyone',
	array( $honey['outcome'], form_only_state(), $GLOBALS['mail'], $GLOBALS['notices'] ),
	array( 'sent', array( 'spam', array( 'honeypot' ) ), array(), array() ) );

world();
$stale = form_submit( form_posted(), array( 'token' => form_token( 13 * HOUR_IN_SECONDS ) ) );

ck( '5. a token past its life bounces with the answers and stores nothing', array( $stale['outcome'], count( form_rows() ), $stale['stash']['values']['city'] ?? null ), array( 'stale', 0, 'Porto' ) );

world();
$forged = form_submit( form_posted(), array( 'token' => 'not-a-token' ) );

ck( 'a token this site did not sign is spam', array( $forged['outcome'], form_only_state(), $GLOBALS['mail'], $GLOBALS['notices'] ), array( 'sent', array( 'spam', array( 'dwell' ) ), array(), array() ) );

world();
$fast = form_submit( form_posted(), array( 'token' => form_token( 1 ) ) );

ck( 'a form sent within seconds of being opened is held for a person, not filed as spam: the student is acknowledged and Administrators are not told',
	array( $fast['outcome'], form_only_state(), array_column( $GLOBALS['mail'], 'to' ), $GLOBALS['notices'] ),
	array( 'sent', array( 'held', array( 'dwell-fast' ) ), array( 'ines@example.test' ), array() ) );

world();
$token  = form_token();
$once   = form_submit( form_posted(), array( 'token' => $token ) );
$twice  = form_submit( form_posted(), array( 'token' => $token ) );

ck( 'a second press of one token stores nothing more and is answered as the first was',
	array( $once['outcome'], $twice['outcome'], count( form_rows() ), count( $GLOBALS['mail'] ), count( $GLOBALS['notices'] ) ),
	array( 'sent', 'sent', 1, 1, 1 ) );

world();
$again = form_submit(
	form_posted(
		array(
			'name'          => '=Ines',
			'email'         => 'ines@campus',
			'institution'   => null,
			'country'       => 'recCOUNTRY0000009',
			'website'       => 'not a site',
			'contact_email' => '',
			'internships'   => array( 'Whenever' ),
			'anything'      => 'We write <b>HTML</b> here',
		)
	)
);

ck( '6 and 7. each refused or missing answer is named once, nothing is stored, and nobody is written to',
	array( $again['outcome'], $again['stash']['problems'] ?? null, count( form_rows() ), $GLOBALS['mail'], $GLOBALS['notices'] ),
	array(
		'again',
		array( 'name' => 'formula', 'email' => 'bad_email', 'country' => 'bad_choice', 'website' => 'bad_url', 'anything' => 'markup', 'institution' => 'required', 'contact_email' => 'required', 'internships' => 'required' ),
		0,
		array(),
		array(),
	) );
ck( 'the answers that cleaned come back as cleaned, a refused one as it was typed, and both ticks as given',
	array( $again['stash']['values'] ?? null, $again['stash']['typed'] ?? null ),
	array(
		array( 'city' => 'Porto', 'department' => 'Computer Science', 'representative' => 'Rui Example', 'told' => true, 'consent' => true ),
		array( 'name' => '=Ines', 'email' => 'ines@campus', 'website' => 'not a site', 'anything' => 'We write <b>HTML</b> here' ),
	) );

$redrawn = form_redraw( $again );

ck( 'the form drawn again opens with the summary of its problems, marks each question and says each sentence under it, keeps every answer escaped and both boxes ticked',
	array(
		1 === preg_match( '#<form[^>]*><div class="wpcpm-errors" role="alert" tabindex="-1"><h2 class="wpcpm-errors__title">Eight answers need a change</h2>#', $redrawn ),
		substr_count( $redrawn, '<li><a href="#wpcpm-student-interest-' ),
		form_problem_drawn( $redrawn, 'name' ),
		form_problem_drawn( $redrawn, 'email' ),
		form_problem_drawn( $redrawn, 'institution' ),
		form_problem_drawn( $redrawn, 'country' ),
		form_problem_drawn( $redrawn, 'website' ),
		form_problem_drawn( $redrawn, 'internships' ),
		form_problem_drawn( $redrawn, 'city' ),
		false !== strpos( $redrawn, 'name="wpcpm_student_interest[name]" value="=Ines"' ),
		false !== strpos( $redrawn, 'name="wpcpm_student_interest[website]" value="not a site"' ),
		false !== strpos( $redrawn, '>We write &lt;b&gt;HTML&lt;/b&gt; here</textarea>' ),
		false !== strpos( $redrawn, 'name="wpcpm_student_interest[city]" value="Porto"' ),
		substr_count( $redrawn, 'value="1" checked="checked"' ),
		false !== strpos( $redrawn, 'aria-invalid="true" aria-describedby="wpcpm-student-interest-website-problem"' ),
	),
	array(
		true,
		8,
		'This cannot start with =, +, - or @.',
		'That does not look like an email address.',
		'Fill this in.',
		'Choose one of the options offered.',
		esc_html( WPCPM_Field_Value::bad_url_text() ),
		'Tick at least one.',
		null,
		true,
		true,
		true,
		true,
		2,
		true,
	) );

$ticks = array();

foreach ( array(
	'neither box'              => array( 'told' => null, 'consent' => null ),
	'the privacy box alone'    => array( 'told' => null ),
	'the representative alone' => array( 'consent' => null ),
	'a word in place of a tick' => array( 'told' => 'yes', 'consent' => 'on' ),
) as $case => $change ) {
	world();
	$landed = form_submit( form_posted( $change ) );

	$ticks[ $case ] = array( $landed['outcome'], $landed['stash']['problems'] ?? null, count( form_rows() ), count( $GLOBALS['mail'] ), count( $GLOBALS['notices'] ), array_intersect_key( $landed['stash']['values'] ?? array(), array( 'told' => 1, 'consent' => 1 ) ) );
}

ck( '8. both boxes are needed: with either unticked nothing is stored and nobody is written to, each unticked box is named, and a box that was ticked comes back ticked',
	$ticks,
	array(
		'neither box'               => array( 'again', array( 'told' => 'unticked', 'consent' => 'consent' ), 0, 0, 0, array() ),
		'the privacy box alone'     => array( 'again', array( 'told' => 'unticked' ), 0, 0, 0, array( 'consent' => true ) ),
		'the representative alone'  => array( 'again', array( 'consent' => 'consent' ), 0, 0, 0, array( 'told' => true ) ),
		'a word in place of a tick' => array( 'again', array( 'told' => 'unticked', 'consent' => 'consent' ), 0, 0, 0, array() ),
	) );

world();
$bot = form_submit( form_posted( array( 'told' => null ) ), array( 'honeypot' => 'filled' ) );

ck( 'not even as spam: a post caught by the honeypot with a box unticked stores nothing', array( $bot['outcome'], count( form_rows() ) ), array( 'again', 0 ) );

$unticked_page = form_redraw( $bot );

ck( 'and an unticked box says its sentence under it',
	array( form_problem_drawn( $unticked_page, 'told' ), form_problem_drawn( $unticked_page, 'consent' ) ),
	array( 'Tick this box to send the form.', null ) );

echo "\n--- A good interest ---\n";

world();
$good = form_submit( form_posted( array( 'anything' => 'Typed a backslash: C:\\temp and a "quote"' ) ) );
$row  = form_only();

ck( 'a good interest lands on the bare thank-you at the form\'s page, with nothing typed in the address',
	array( $good['outcome'], $good['url'], form_redraw( $good ) === $thanks_html ),
	array( 'sent', 'https://example.test/student-interest/?wpcpm_sint_said=sent#wpcpm-student-interest', true ) );
ck( 'it is stored as new, with no signal, the answers as cleaned: the website with its scheme, the country by ID and by name, the internships as chosen, the backslash kept, the first box an answer and the privacy box none',
	array( form_only_state(), $row ? WPCPM_Student_Interest::fields_of( $row ) : null ),
	array(
		array( 'new', array() ),
		array(
			'name'           => 'Ines Example',
			'email'          => 'ines@example.test',
			'institution'    => 'Example University',
			'country'        => 'recCOUNTRY0000002',
			'country_name'   => 'Portugal',
			'city'           => 'Porto',
			'website'        => 'https://university.example.test',
			'department'     => 'Computer Science',
			'representative' => 'Rui Example',
			'contact_email'  => 'rui@example.test',
			'internships'    => array( 'Based on required hours (e.g. 150 hours)', 'Flexible duration or year-round' ),
			'anything'       => 'Typed a backslash: C:\\temp and a "quote"',
			'told'           => true,
		),
	) );

$evidence = $row ? WPCPM_Student_Interest::consent_of( $row ) : array();

ck( 'the consent evidence is the registration form\'s, with the second sentence beside it: both sentences as drawn, the policy\'s address, the time, the address shortened and the browser',
	array( $evidence['sentence'] ?? null, $evidence['told'] ?? null, $evidence['url'] ?? null, abs( ( $evidence['at'] ?? 0 ) - time() ) < 5, $evidence['ip'] ?? null, $evidence['agent'] ?? null, array_keys( $evidence ) ),
	array(
		'By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (https://wordpress.org/about/privacy/).',
		'I have told the representative about this request, and they agreed that I share their name and email address with the WordPress Credits program.',
		'https://wordpress.org/about/privacy/',
		true,
		'203.0.113.0',
		'Mozilla/5.0 (test)',
		array( 'sentence', 'url', 'policy', 'modified', 'at', 'ip', 'agent', 'told' ),
	) );

echo "\n--- The two emails ---\n";

$templates = WPCPM_Mail_Templates::all();

ck( 'the student is sent the acknowledgement, once, at the address they gave, with the program\'s address to reply to; nothing goes to the representative',
	array( count( $GLOBALS['mail'] ), $GLOBALS['mail'][0]['to'] ?? null, $GLOBALS['mail'][0]['context'] ?? null, $GLOBALS['mail'][0]['headers'] ?? null, in_array( 'rui@example.test', array_column( $GLOBALS['mail'], 'to' ), true ) ),
	array( 1, 'ines@example.test', 'student-interest-received', array( 'Reply-To: programs@example.test' ), false ) );
ck( 'its subject and its body, word for word before the site\'s signature: short, plain, and with no exclamation mark',
	array(
		$GLOBALS['mail'][0]['subject'] ?? null,
		0 === strpos( (string) ( $GLOBALS['mail'][0]['body'] ?? '' ), "Hi Ines Example,\n\nWe received your interest in the WordPress Credits Program for Example University.\n\nWe will get in touch with the representative you named. Nothing else is needed from you now.\n\nIf you have any questions in the meantime, reply to this email." ),
		false !== strpos( $templates['student-interest-received']['subject'] . $templates['student-interest-received']['body'] . $templates['student-interest-notice']['subject'] . $templates['student-interest-notice']['body'], '!' ),
		false !== strpos( (string) ( $GLOBALS['mail'][0]['body'] ?? '' ), 'rui@example.test' ) || false !== strpos( (string) ( $GLOBALS['mail'][0]['body'] ?? '' ), 'Rui Example' ),
	),
	array( 'We received your interest - WordPress Credits Program', true, false, false ) );

$notice = $GLOBALS['notices'][0] ?? array();

ck( 'Administrators are told through the student_interest_notify setting, with the institution\'s name and country, the reference and the way to the Interests tab, and none of the answers',
	array(
		count( $GLOBALS['notices'] ),
		$notice['context'] ?? null,
		$notice['setting'] ?? null,
		$notice['mail']['subject'] ?? null,
		0 === strpos( (string) ( $notice['mail']['body'] ?? '' ), "A student whose institution is not in the program yet has sent the student interest form, and it passed every check.\n\nExample University, Portugal\n\nThe student and the representative to write to are on the Interests tab:\n\nhttps://example.test/wp-admin/admin.php?page=wpcpm-students&tab=interests" ),
		(int) preg_match( '/Ines|ines@|Rui|rui@|Porto|backslash/', (string) ( $notice['mail']['subject'] ?? '' ) . (string) ( $notice['mail']['body'] ?? '' ) ),
	),
	array( 1, 'student-interest-notice', 'student_interest_notify', '[WordPress Credits] New interest from a student: ' . WPCPM_Student_Interest::reference_of( $row ), true, 0 ) );
ck( 'the registry holds the two, after the Student registrations\' and before the Quiet students\', each with what sends it, and the catalog files them under Student interests, for an applicant and for Administrators',
	array(
		array_slice( array_keys( $templates ), -5, 2 ),
		$templates['student-interest-received']['trigger'],
		$templates['student-interest-notice']['trigger'],
		WPCPM_Mail_Catalog::modules()[ WPCPM_Mail_Catalog::MODULE_STUDENT_INTS ],
		array_intersect_key( WPCPM_Mail_Catalog::emails()['student-interest-received'], array( 'module' => 1, 'audience' => 1 ) ),
		array_intersect_key( WPCPM_Mail_Catalog::emails()['student-interest-notice'], array( 'module' => 1, 'audience' => 1 ) ),
	),
	array(
		array( 'student-interest-received', 'student-interest-notice' ),
		'When a student sends the student interest form and the interest is stored as new or held: to the address the student gives. The representative the student names is sent nothing.',
		'When an interest from a student passes every check: to the addresses in "Who hears of a new interest" under Interest from students, or, with none set, to every Administrator. An interest that is held or filed as spam sends none.',
		'Student interests',
		array( 'module' => 'student-interests', 'audience' => 'applicant' ),
		array( 'module' => 'student-interests', 'audience' => 'administrator' ),
	) );

world();
$GLOBALS['settings']['mail_contact'] = '';
form_submit( form_posted() );

ck( 'with no contact address set, the acknowledgement names none to reply to', $GLOBALS['mail'][0]['headers'] ?? null, array() );

echo "\n--- Held, not refused ---\n";

world();
$GLOBALS['disallowed'] = true;
$listed                = form_submit( form_posted() );

ck( '9. an interest the site\'s disallowed list matches is held: stored, thanked and acknowledged, and Administrators are not told',
	array( $listed['outcome'], form_only_state(), array_column( $GLOBALS['mail'], 'context' ), array_column( $GLOBALS['mail'], 'to' ), $GLOBALS['notices'], form_redraw( $listed ) === $thanks_html ),
	array( 'sent', array( 'held', array( 'disallowed' ) ), array( 'student-interest-received' ), array( 'ines@example.test' ), array(), true ) );

world();
$linked = form_submit( form_posted( array( 'anything' => 'See https://a.example.test and https://b.example.test and http://c.example.test' ) ) );

ck( 'and so is one whose free text carries three links; the institution\'s website is no such link',
	array( $linked['outcome'], form_only_state(), count( $GLOBALS['mail'] ), $GLOBALS['notices'] ),
	array( 'sent', array( 'held', array( 'links' ) ), 1, array() ) );

world();

for ( $i = 0; $i < WPCPM_Form_Guard::PER_DAY; $i++ ) {
	WPCPM_Form_Guard::claim_site( WPCPM_Student_Interest_Form::SITE_KEY, WPCPM_Form_Guard::PER_DAY );
}

$flood = form_submit( form_posted() );

ck( '10. past the site\'s forty a day an interest is still taken, held, and acknowledged, and Administrators are not told of each one',
	array( $flood['outcome'], form_only_state(), count( $GLOBALS['mail'] ), $GLOBALS['notices'] ),
	array( 'sent', array( 'held', array( 'site-ceiling' ) ), 1, array() ) );

world();
$one = form_submit( form_posted() );
$two = form_submit( form_posted( array( 'email' => ' INES@example.test', 'institution' => 'example   university', 'city' => 'Lisbon' ) ), array( 'ip' => '198.51.100.9' ) );
$rows = form_rows();

ck( '11. a second interest from the same student for the same institution is stored beside the first, marked duplicate, never merged or refused, and still new: both are acknowledged and announced',
	array(
		$two['outcome'],
		count( $rows ),
		array_map( array( 'WPCPM_Student_Interest', 'state_of' ), $rows ),
		array_map( array( 'WPCPM_Student_Interest', 'signals_of' ), $rows ),
		array( WPCPM_Student_Interest::fields_of( $rows[0] )['city'], WPCPM_Student_Interest::fields_of( $rows[1] )['city'] ),
		count( $GLOBALS['mail'] ),
		count( $GLOBALS['notices'] ),
	),
	array( 'sent', 2, array( 'new', 'new' ), array( array(), array( 'duplicate' ) ), array( 'Porto', 'Lisbon' ), 2, 2 ) );

$three = form_submit( form_posted( array( 'institution' => 'Sample College' ) ), array( 'ip' => '198.51.100.10' ) );

ck( 'the same student for another institution is no duplicate', WPCPM_Student_Interest::signals_of( form_rows()[2] ), array() );

echo "\n--- The acknowledgement's ceiling, and a hand-off refused ---\n";

world();

for ( $i = 0; $i < WPCPM_Form_Guard::MAIL_PER_DAY; $i++ ) {
	WPCPM_Form_Guard::claim_mail( WPCPM_Student_Interest_Form::MAIL_KEY, WPCPM_Form_Guard::MAIL_PER_DAY );
}

$quiet = form_submit( form_posted() );

ck( 'past the day\'s 200 acknowledgements the interest is stored and thanked all the same, the student is sent none and the row says so; Administrators still hear of it',
	array( $quiet['outcome'], form_only_state(), $GLOBALS['mail'], count( $GLOBALS['notices'] ) ),
	array( 'sent', array( 'new', array( 'mail-ceiling' ) ), array(), 1 ) );

world();
$GLOBALS['mail_fails'] = true;
$refused               = form_submit( form_posted() );

ck( 'a hand-off the mail layer refuses leaves its mark on the row, and the page thanks all the same',
	array( $refused['outcome'], form_only_state(), count( $GLOBALS['notices'] ) ),
	array( 'sent', array( 'new', array( 'mail-failed' ) ), 1 ) );

world();
$GLOBALS['insert_fails'] = true;
$lost                    = form_submit( form_posted() );
$GLOBALS['insert_fails'] = false;

ck( 'an interest WordPress would not store says so, keeps the answers, and writes to nobody',
	array( $lost['outcome'], $lost['stash']['values']['name'] ?? null, array_key_exists( 'told', $lost['stash']['values'] ?? array() ) ? $lost['stash']['values']['told'] : null, $GLOBALS['mail'], $GLOBALS['notices'] ),
	array( 'lost', 'Ines Example', true, array(), array() ) );

echo "\n--- Where a submission goes back to ---\n";

world();
unset( $GLOBALS['opts'][ WPCPM_Student_Interest_Form::OPT_PAGE ] );
$GLOBALS['referer'] = 'https://example.test/join/?wpcpm_sint_said=closed&ref=1';
$from_page          = form_submit( form_posted() );

$GLOBALS['referer'] = 'https://elsewhere.example.com/form/';
$from_abroad        = form_submit( form_posted(), array( 'ip' => '198.51.100.11' ) );

unset( $GLOBALS['referer'] );
$from_nowhere = form_submit( form_posted(), array( 'ip' => '198.51.100.12' ) );

ck( 'with no page remembered, a submission goes back to the page it was posted from when that is this site\'s, without the answer before it, and to the home page otherwise',
	array( $from_page['url'], $from_abroad['url'], $from_nowhere['url'] ),
	array( 'https://example.test/join/?ref=1&wpcpm_sint_said=sent#wpcpm-student-interest', 'https://example.test/?wpcpm_sint_said=sent#wpcpm-student-interest', 'https://example.test/?wpcpm_sint_said=sent#wpcpm-student-interest' ) );

echo "\n--- The editor's draw ---\n";

world();
define( 'REST_REQUEST', true );
$preview = WPCPM_Student_Interest_Form::render();

ck( 'in the editor the block is a static summary of the questions, with no form, no nonce and no token',
	array( false !== strpos( $preview, 'wpcpm-application--preview' ), false !== strpos( $preview, '<form' ), false !== strpos( $preview, 'nonce' ), false !== strpos( $preview, '<li>Institution Representative</li>' ), false !== strpos( $preview, '<li>Anything else you&#039;d like us to know?</li>' ) ),
	array( true, false, false, true, true ) );

$GLOBALS['settings']['student_interest_open'] = false;

ck( 'and with the form closed the editor reads why',
	WPCPM_Student_Interest_Form::render(),
	'<div class="wpcpm-application"><p class="wpcpm-application__notice">The student interest form is switched off. Turn on &quot;Student interest form&quot; on the Students and mentors tab of WPCredits Program &gt; Settings when the program is ready to take it.</p></div>' );

echo "\n=== The tab ===\n";

require_once __DIR__ . '/stubs/caps.php';

/** A site where an Administrator is signed in and nothing is stored. */
function tab_world() {
	world();

	an_account( 9, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );
	an_account( 10, 'Sam Student', 'sam@example.test', array( 'wpcpm_student' ) );

	$GLOBALS['users'][9]->display_name = 'Ida <Admin>';

	$GLOBALS['uid']    = 9;
	$GLOBALS['manage'] = array( 9 );
}

/** The tab as the signed-in request draws it. */
function tab_html( array $query = array() ) {
	$_GET     = $query;
	$_REQUEST = $query;

	$html = run( array( 'WPCPM_Student_Interests_Tab', 'render' ) )['html'];

	$_GET     = array();
	$_REQUEST = array();

	return $html;
}

/** One press: the handler, the posted fields (a nonce by default), and how the request ended. */
function tab_press( $method, $action, $id, array $more = array(), $nonce = true ) {
	$_POST = array_merge(
		array(
			'action'                                     => $action,
			WPCPM_Student_Interests_Tab::FIELD_INTEREST  => (string) $id,
		),
		$nonce ? array( '_wpnonce' => wp_create_nonce( $action . '_' . $id ) ) : array(),
		$more
	);
	$_REQUEST = $_POST;

	$out = run( array( 'WPCPM_Student_Interests_Tab', $method ) );

	$_POST    = array();
	$_REQUEST = array();

	return $out['stop'];
}

/** The outcome the last press flashed for the signed-in account, taken as the screen takes it. */
function tab_flashed() {
	$pending = get_user_meta( $GLOBALS['uid'], WPCPM_Flash::META, true );
	$status  = is_array( $pending ) && isset( $pending[ WPCPM_Student_Interests_Tab::FLASH ] ) ? $pending[ WPCPM_Student_Interests_Tab::FLASH ] : '';

	delete_user_meta( $GLOBALS['uid'], WPCPM_Flash::META );

	return $status;
}

/** One interest's row of the tab, as drawn. */
function tab_row( $html, $id ) {
	return 1 === preg_match( '#<li class="wpcpm-queue-item" id="wpcpm-sint-' . (int) $id . '">.*?</li>(?=<li class="wpcpm-queue-item"|</ol>)#s', $html, $found ) ? $found[0] : '';
}

echo "\n--- The hooks and the names ---\n";

tab_world();
$GLOBALS['hooks'] = array();
WPCPM_Student_Interests_Tab::init();

ck( 'the five presses are for a signed-in account alone',
	array( array_keys( $GLOBALS['hooks'] ), (int) preg_grep( '/nopriv/', array_keys( $GLOBALS['hooks'] ) ) ),
	array( array( 'admin_post_wpcpm_sint_contacted', 'admin_post_wpcpm_sint_close', 'admin_post_wpcpm_sint_spam', 'admin_post_wpcpm_sint_restore', 'admin_post_wpcpm_sint_delete' ), 0 ) );
ck( 'the views are the five states, each under its word, New first; the view shown is the one named, or New; and each has its address',
	array(
		WPCPM_Student_Interests_Tab::views(),
		( static function () { $_GET = array( 'sint_view' => 'held' ); $view = WPCPM_Student_Interests_Tab::current_view(); $_GET = array( 'sint_view' => 'nope' ); $other = WPCPM_Student_Interests_Tab::current_view(); $_GET = array(); return array( $view, $other, WPCPM_Student_Interests_Tab::current_view() ); } )(),
		array( WPCPM_Student_Interests_Tab::view_url(), WPCPM_Student_Interests_Tab::view_url( 'spam' ), WPCPM_Student_Interests_Tab::view_url( 'nope' ) ),
	),
	array(
		array( 'new' => 'New', 'held' => 'Held', 'contacted' => 'Contacted', 'closed' => 'Closed', 'spam' => 'Spam' ),
		array( 'held', 'new', 'new' ),
		array( 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=interests', 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=interests&sint_view=spam', 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=interests' ) ),
	);
ck( 'every press leaves a sentence, and every signal the form raises has one',
	array( array_keys( WPCPM_Student_Interests_Tab::messages() ), array_keys( WPCPM_Student_Interests_Tab::signal_texts() ) ),
	array(
		array( 'sint-contacted', 'sint-closed', 'sint-spam', 'sint-restored', 'sint-deleted', 'sint-unknown', 'sint-state' ),
		array( 'honeypot', 'dwell', 'dwell-fast', 'disallowed', 'links', WPCPM_Student_Interest_Form::SIGNAL_SITE_CEILING, WPCPM_Student_Interest::SIGNAL_DUPLICATE, WPCPM_Student_Interest_Form::SIGNAL_MAIL_CEILING, WPCPM_Student_Interest_Form::SIGNAL_MAIL_FAILED ),
	) );

echo "\n--- Who may see it ---\n";

tab_world();
interest_in( 'new' );

$GLOBALS['uid'] = 10;
$as_student     = tab_html();
$GLOBALS['uid'] = 0;
$signed_out     = tab_html();
$GLOBALS['uid'] = 9;
$as_admin       = tab_html();

ck( 'the tab draws nothing for an account without the program\'s capability, or for nobody, and the card for an Administrator',
	array( $as_student, $signed_out, 0 === strpos( $as_admin, '<div class="wpcpm-card" id="wpcpm-student-interests"><h2>Interest from students <span class="wpcpm-count">1</span></h2>' ) ),
	array( '', '', true ) );

echo "\n--- The views and their counts ---\n";

tab_world();

$new_a  = interest_in( 'new', array( 'name' => 'First New', 'email' => 'first@example.test' ) );
$new_b  = interest_in( 'new', array( 'name' => 'Second New', 'email' => 'second@example.test' ) );
$held_a = interest_in( 'held', array( 'name' => 'Held One', 'email' => 'held@example.test' ), array( 'links' ) );
$done_a = interest_in( 'contacted', array( 'name' => 'Contacted One', 'email' => 'contacted@example.test' ) );
$shut_a = interest_in( 'closed', array( 'name' => 'Closed One', 'email' => 'closed@example.test' ) );
$spam_a = interest_in( 'spam', array( 'name' => 'Spam One', 'email' => 'spam@example.test' ), array( 'honeypot' ) );

$opened = tab_html();

preg_match_all( '#<li class="([a-z]+)"><a href="([^"]+)"( class="current" aria-current="page")?>([A-Za-z]+) <span class="count">\((\d+)\)</span></a>#', $opened, $links, PREG_SET_ORDER );

ck( 'the bar lists the five views with their counts, New the one shown when none is named, and the heading counts the new',
	array(
		array_map(
			function ( $link ) {
				return array( $link[1], html_entity_decode( $link[2] ), '' !== $link[3], $link[4], (int) $link[5] );
			},
			$links
		),
		false !== strpos( $opened, '<h2>Interest from students <span class="wpcpm-count">2</span></h2>' ),
	),
	array(
		array(
			array( 'new', 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=interests', true, 'New', 2 ),
			array( 'held', 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=interests&sint_view=held', false, 'Held', 1 ),
			array( 'contacted', 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=interests&sint_view=contacted', false, 'Contacted', 1 ),
			array( 'closed', 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=interests&sint_view=closed', false, 'Closed', 1 ),
			array( 'spam', 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=interests&sint_view=spam', false, 'Spam', 1 ),
		),
		true,
	) );

$listed = array();

foreach ( array( 'new', 'held', 'contacted', 'closed', 'spam', 'nope' ) as $view ) {
	preg_match_all( '#id="wpcpm-sint-(\d+)"#', tab_html( array( 'sint_view' => $view ) ), $drawn_ids );

	$listed[ $view ] = array_map( 'intval', $drawn_ids[1] );
}

ck( 'each view lists its own state, newest first, and a view the tab does not have is New',
	$listed,
	array( 'new' => array( $new_b, $new_a ), 'held' => array( $held_a ), 'contacted' => array( $done_a ), 'closed' => array( $shut_a ), 'spam' => array( $spam_a ), 'nope' => array( $new_b, $new_a ) ) );

tab_world();

ck( 'a view with nothing in it says so', false !== strpos( tab_html( array( 'sint_view' => 'closed' ) ), '<p>Nothing here.</p></div>' ), true );

for ( $i = 0; $i < WPCPM_Student_Interests_Tab::LIST_MAX + 2; $i++ ) {
	interest_in( 'new', array( 'email' => 'many' . $i . '@example.test' ) );
}

$many   = tab_html();
$second = tab_html( array( 'sint_paged' => '2' ) );
$beyond = tab_html( array( 'sint_paged' => '9' ) );
$nav    = static function ( $html ) {
	return preg_match_all( '#<p class="description wpcpm-queue__pages">(.*?)</p>#', $html, $found ) ? $found[1] : array();
};
$tab    = 'https://example.test/wp-admin/admin.php?page=wpcpm-students&#038;tab=interests';

ck( 'a view draws fifty interests a page, the newest first, and says which page it is and how many there are, above the list and under it, with a way to the older ones',
	array( substr_count( $many, '<li class="wpcpm-queue-item"' ), $nav( $many ) ),
	array( 50, array_fill( 0, 2, 'Page 1 of 2, 52 interests in all. <a href="' . $tab . '&#038;sint_paged=2">Older</a>' ) ) );
ck( 'the second page holds the rest, every interest of the view reached, with a way back to the newer ones; a page past the last is the last',
	array( substr_count( $second, '<li class="wpcpm-queue-item"' ), $nav( $second ), $beyond === $second ),
	array( 2, array_fill( 0, 2, 'Page 2 of 2, 52 interests in all. <a href="' . $tab . '">Newer</a>' ), true ) );
ck( 'a view of one page carries no such line', $nav( tab_html( array( 'sint_view' => 'closed' ) ) ), array() );

echo "\n--- What a row prints, escaped ---\n";

tab_world();

$plain = interest_in( 'new' );
$evil  = interest_in(
	'held',
	array(
		'name'           => 'Ines <script>alert(1)</script>',
		'email'          => 'ines+tag@example.test',
		'institution'    => 'Example <b>University</b> & Sons',
		'country_name'   => 'Port<u>gal',
		'city'           => 'Por"to',
		'website'        => 'https://university.example.test/?a=1&b=<2>',
		'department'     => '<i>Computer</i> Science',
		'representative' => 'Rui "Example" <img src=x>',
		'contact_email'  => 'rui@example.test',
		'internships'    => array( 'Other (please specify)', '<b>Odd</b>' ),
		'anything'       => "First line <script>x</script>\nSecond line & more",
	),
	array( 'links', 'duplicate', 'mail-ceiling', 'something<new>' )
);

update_post_meta( $plain, WPCPM_Student_Interest::META_CREATED, 1700000000 );

$new_view  = tab_html();
$held_view = tab_html( array( 'sint_view' => 'held' ) );
$plain_row = tab_row( $new_view, $plain );
$evil_row  = tab_row( $held_view, $evil );

ck( 'a row gives the student by name with the address as a mailto link, the date it was received and its reference, and its state',
	array(
		false !== strpos( $plain_row, '<h3 class="wpcpm-queue-title"><span class="wpcpm-inst-name__text">Ines Example</span> <span class="wpcpm-inst-muted"><a href="mailto:ines@example.test">ines@example.test</a></span></h3>' ),
		false !== strpos( $plain_row, '<p class="wpcpm-queue-age">Received November 15, 2023. Reference ' . WPCPM_Student_Interest::reference_of( WPCPM_Student_Interest::interest( $plain ) ) . '.</p>' ),
		false !== strpos( $plain_row, '<p class="wpcpm-sreg-outcome"><strong>New</strong></p>' ),
		false !== strpos( $plain_row, 'wpcpm-sreg-mail' ),
	),
	array( true, true, true, false ) );
ck( 'then the institution with its city, country and website as a link, the department, the representative with a mailto link, the internships and the free text',
	array(
		false !== strpos( $plain_row, '<tr><th scope="row">Institution</th><td>Example University<br />Porto, Portugal<br /><a href="https://university.example.test" rel="noopener noreferrer nofollow">https://university.example.test</a></td></tr>' ),
		false !== strpos( $plain_row, '<tr><th scope="row">Department</th><td>Computer Science</td></tr>' ),
		false !== strpos( $plain_row, '<tr><th scope="row">Representative</th><td>Rui Example<br /><a href="mailto:rui@example.test">rui@example.test</a></td></tr>' ),
		false !== strpos( $plain_row, '<tr><th scope="row">Internships or practices</th><td>Based on required hours (e.g. 150 hours), Other (please specify)</td></tr>' ),
		false !== strpos( $plain_row, '<tr><th scope="row">Anything else</th><td>Typed a backslash: C:\\temp and a &quot;quote&quot;</td></tr>' ),
	),
	array( true, true, true, true, true ) );
ck( 'and the consent, folded: both sentences, the time, the shortened address and the browser',
	false !== strpos( $plain_row, '<details class="wpcpm-sreg-details"><summary>Consent</summary><p class="wpcpm-sreg-consent">Agreed to: I have told the representative about this request, and they agreed that I share their name and email address with the WordPress Credits program. By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (https://wordpress.org/about/privacy/). Time: November 15, 2023 12:13 am. Address: 203.0.113.0. Browser: Mozilla/5.0 (test).</p></details>' ),
	true );
ck( 'everything a stranger typed is escaped where it is printed: no tag of theirs reaches the page, in a name, a cell, an address or the free text, whose lines are kept',
	array(
		(int) preg_match( '#<(script|b|u|i|img)\b#', $evil_row ),
		false !== strpos( $evil_row, '<span class="wpcpm-inst-name__text">Ines &lt;script&gt;alert(1)&lt;/script&gt;</span>' ),
		false !== strpos( $evil_row, '<a href="mailto:ines%2Btag@example.test">ines+tag@example.test</a>' ),
		false !== strpos( $evil_row, 'Example &lt;b&gt;University&lt;/b&gt; &amp; Sons<br />Por&quot;to, Port&lt;u&gt;gal<br />' ),
		false !== strpos( $evil_row, '<td>&lt;i&gt;Computer&lt;/i&gt; Science</td>' ),
		false !== strpos( $evil_row, '<td>Rui &quot;Example&quot; &lt;img src=x&gt;<br /><a href="mailto:rui@example.test">rui@example.test</a></td>' ),
		false !== strpos( $evil_row, '<td>Other (please specify), &lt;b&gt;Odd&lt;/b&gt;</td>' ),
		false !== strpos( $evil_row, "<td>First line &lt;script&gt;x&lt;/script&gt;<br />\nSecond line &amp; more</td>" ),
	),
	array( 0, true, true, true, true, true, true, true ) );
ck( 'what the form\'s checks raised is said in a sentence each, and a signal with no sentence is printed escaped',
	array(
		false !== strpos( $evil_row, '<p class="wpcpm-sreg-outcome"><strong>Held</strong></p>' ),
		false !== strpos( $evil_row, '<p class="wpcpm-sreg-mail">It holds several links.</p>' ),
		false !== strpos( $evil_row, '<p class="wpcpm-sreg-mail">The same student sent an interest for the same institution in the 30 days before.</p>' ),
		false !== strpos( $evil_row, '<p class="wpcpm-sreg-mail">The student was sent no acknowledgement: the day&#039;s limit on emails was reached.</p>' ),
		false !== strpos( $evil_row, '<p class="wpcpm-sreg-mail">something&lt;new&gt;</p>' ),
	),
	array( true, true, true, true, true ) );

$odd_links = interest_in( 'new', array( 'website' => 'javascript:alert(1)', 'email' => 'not an address', 'contact_email' => '', 'department' => '', 'anything' => '' ) );
$odd_row   = tab_row( tab_html(), $odd_links );

ck( 'a stored website that is no http address, and a stored address that is none, are printed as text and never as a link; an empty answer says so',
	array(
		false !== strpos( $odd_row, 'href="javascript' ),
		false !== strpos( $odd_row, '<br />javascript:alert(1)</td>' ),
		false !== strpos( $odd_row, '<span class="wpcpm-inst-muted">not an address</span>' ),
		false !== strpos( $odd_row, 'mailto:' ),
		false !== strpos( $odd_row, '<tr><th scope="row">Department</th><td><span class="wpcpm-inst-muted">no answer</span></td></tr>' ),
		false !== strpos( $odd_row, '<tr><th scope="row">Representative</th><td>Rui Example</td></tr>' ),
	),
	array( false, true, true, false, true, true ) );

WPCPM_Student_Interest::set_state( $plain, 'contacted', 9 );
update_post_meta( $plain, WPCPM_Student_Interest::META_CHANGED, 1700003600 );

ck( 'a state somebody changed says who and when, the name escaped',
	false !== strpos( tab_row( tab_html( array( 'sint_view' => 'contacted' ) ), $plain ), '<p class="wpcpm-sreg-outcome"><strong>Contacted</strong> Changed on November 15, 2023 1:13 am by Ida &lt;Admin&gt;.</p>' ),
	true );

echo "\n--- The presses each state offers ---\n";

tab_world();

$by_state = array();

foreach ( array( 'new', 'held', 'contacted', 'closed', 'spam' ) as $state ) {
	$one  = interest_in( $state, array( 'name' => 'Ines "Quoted"', 'email' => $state . '@example.test' ) );
	$html = tab_row( tab_html( array( 'sint_view' => $state ) ), $one );

	preg_match_all( '#<button type="submit" class="([a-z-]+)">([^<]+)</button>#', $html, $buttons, PREG_SET_ORDER );

	$by_state[ $state ] = array_map(
		function ( $button ) {
			return $button[2];
		},
		$buttons
	);

	if ( 'spam' === $state ) {
		$spam_html = $html;
		$spam_one  = $one;
	}
}

ck( 'each state offers the presses it takes, in one order, and no other',
	$by_state,
	array(
		'new'       => array( 'Mark contacted', 'Close', 'Mark as spam' ),
		'held'      => array( 'Mark contacted', 'Close', 'Mark as spam', 'Put back to New' ),
		'contacted' => array( 'Close' ),
		'closed'    => array( 'Put back to New', 'Delete' ),
		'spam'      => array( 'Put back to New', 'Delete' ),
	) );
ck( 'each press is a form of its own that posts to admin-post.php, with a nonce keyed to the press and the row, the row, and the view to come back to; Delete asks first, the name escaped',
	array(
		substr_count( $spam_html, '<form class="wpcpm-app-action" method="post" action="https://example.test/wp-admin/admin-post.php"' ),
		false !== strpos( $spam_html, '<input type="hidden" name="_wpnonce" value="nonce-wpcpm_sint_restore_' . $spam_one . '" /><input type="hidden" name="action" value="wpcpm_sint_restore" /><input type="hidden" name="wpcpm_sint_id" value="' . $spam_one . '" /><input type="hidden" name="sint_view" value="spam" />' ),
		false !== strpos( $spam_html, '<input type="hidden" name="_wpnonce" value="nonce-wpcpm_sint_delete_' . $spam_one . '" /><input type="hidden" name="action" value="wpcpm_sint_delete" />' ),
		false !== strpos( $spam_html, 'data-wpcpm-confirm="Delete the interest of Ines &quot;Quoted&quot; for good? It cannot be brought back."' ),
		substr_count( $spam_html, 'data-wpcpm-confirm=' ),
	),
	array( 2, true, true, true, 1 ) );

echo "\n--- The five presses, and their guards ---\n";

$presses = array(
	'handle_contacted' => array( WPCPM_Student_Interests_Tab::ACTION_CONTACTED, 'contacted', 'sint-contacted', array( 'new', 'held' ) ),
	'handle_close'     => array( WPCPM_Student_Interests_Tab::ACTION_CLOSE, 'closed', 'sint-closed', array( 'new', 'held', 'contacted' ) ),
	'handle_spam'      => array( WPCPM_Student_Interests_Tab::ACTION_SPAM, 'spam', 'sint-spam', array( 'new', 'held' ) ),
	'handle_restore'   => array( WPCPM_Student_Interests_Tab::ACTION_RESTORE, 'new', 'sint-restored', array( 'held', 'spam', 'closed' ) ),
	'handle_delete'    => array( WPCPM_Student_Interests_Tab::ACTION_DELETE, null, 'sint-deleted', array( 'spam', 'closed' ) ),
);
$pressed = array();
$wanted  = array();

foreach ( $presses as $method => $press ) {
	foreach ( array( 'new', 'held', 'contacted', 'closed', 'spam' ) as $from ) {
		tab_world();

		$one  = interest_in( $from );
		$stop = tab_press( $method, $press[0], $one, array( 'sint_view' => $from ) );
		$post = WPCPM_Student_Interest::interest( $one );
		$took = in_array( $from, $press[3], true );

		$pressed[ $method ][ $from ] = array( $stop, tab_flashed(), null === $post ? null : WPCPM_Student_Interest::state_of( $post ) );
		$wanted[ $method ][ $from ]  = array(
			'redirect: ' . WPCPM_Student_Interests_Tab::view_url( $from ),
			$took ? $press[2] : 'sint-state',
			$took ? $press[1] : $from,
		);
	}
}

ck( 'each press works on the states it takes and on no other, says what it did, and comes back to the view it was made on; a press on any other state changes nothing and says so',
	$pressed, $wanted );

tab_world();
$one  = interest_in( 'new' );
$sent = count( $GLOBALS['mail'] ) + count( $GLOBALS['notices'] );
tab_press( 'handle_contacted', WPCPM_Student_Interests_Tab::ACTION_CONTACTED, $one );
$who = WPCPM_Student_Interest::changed_of( WPCPM_Student_Interest::interest( $one ) );
tab_press( 'handle_close', WPCPM_Student_Interests_Tab::ACTION_CLOSE, $one );
tab_press( 'handle_restore', WPCPM_Student_Interests_Tab::ACTION_RESTORE, $one );

ck( 'a press notes who made it, and no press sends an email to anybody, a put back to New included',
	array( $who['by'], WPCPM_Student_Interest::state_of( WPCPM_Student_Interest::interest( $one ) ), count( $GLOBALS['mail'] ) + count( $GLOBALS['notices'] ) - $sent ),
	array( 9, 'new', 0 ) );

$guards = array();

foreach ( $presses as $method => $press ) {
	tab_world();

	$one = interest_in( 'held' === $press[3][0] ? 'held' : $press[3][0] );

	$GLOBALS['uid'] = 10;
	$as_student     = tab_press( $method, $press[0], $one );
	$GLOBALS['uid'] = 0;
	$as_nobody      = tab_press( $method, $press[0], $one );
	$GLOBALS['uid'] = 9;
	$no_nonce       = tab_press( $method, $press[0], $one, array(), false );
	$other_nonce    = tab_press( $method, $press[0], $one, array( '_wpnonce' => wp_create_nonce( $press[0] . '_' . ( $one + 1 ) ) ), false );
	$other_press    = tab_press( $method, $press[0], $one, array( '_wpnonce' => wp_create_nonce( 'wpcpm_sreg_delete_' . $one ) ), false );
	$post           = WPCPM_Student_Interest::interest( $one );

	$guards[ $method ] = array( $as_student, $as_nobody, $no_nonce, $other_nonce, $other_press, null === $post ? null : WPCPM_Student_Interest::state_of( $post ), tab_flashed() );
}

ck( 'every press asks the capability first, then the nonce keyed to the press and the row: without either nothing changes and nothing is said',
	$guards,
	array(
		'handle_contacted' => array( 'wp_die: You do not have permission to manage the program.', 'wp_die: You do not have permission to manage the program.', 'nonce refused: wpcpm_sint_contacted_500', 'nonce refused: wpcpm_sint_contacted_500', 'nonce refused: wpcpm_sint_contacted_500', 'new', '' ),
		'handle_close'     => array( 'wp_die: You do not have permission to manage the program.', 'wp_die: You do not have permission to manage the program.', 'nonce refused: wpcpm_sint_close_500', 'nonce refused: wpcpm_sint_close_500', 'nonce refused: wpcpm_sint_close_500', 'new', '' ),
		'handle_spam'      => array( 'wp_die: You do not have permission to manage the program.', 'wp_die: You do not have permission to manage the program.', 'nonce refused: wpcpm_sint_spam_500', 'nonce refused: wpcpm_sint_spam_500', 'nonce refused: wpcpm_sint_spam_500', 'new', '' ),
		'handle_restore'   => array( 'wp_die: You do not have permission to manage the program.', 'wp_die: You do not have permission to manage the program.', 'nonce refused: wpcpm_sint_restore_500', 'nonce refused: wpcpm_sint_restore_500', 'nonce refused: wpcpm_sint_restore_500', 'held', '' ),
		'handle_delete'    => array( 'wp_die: You do not have permission to manage the program.', 'wp_die: You do not have permission to manage the program.', 'nonce refused: wpcpm_sint_delete_500', 'nonce refused: wpcpm_sint_delete_500', 'nonce refused: wpcpm_sint_delete_500', 'spam', '' ),
	) );

tab_world();

$not_one = tab_press( 'handle_delete', WPCPM_Student_Interests_Tab::ACTION_DELETE, 42 );
$flash_a = tab_flashed();
$missing = tab_press( 'handle_close', WPCPM_Student_Interests_Tab::ACTION_CLOSE, 999999, array( 'sint_view' => 'nope' ) );
$flash_b = tab_flashed();

ck( 'a press on a post that is no interest, the form\'s page among them, or on none, changes nothing and says there is none; a view the tab does not have comes back to New',
	array( $not_one, $flash_a, null !== get_post( 42 ), $missing, $flash_b ),
	array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=interests', 'sint-unknown', true, 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=interests', 'sint-unknown' ) );

$tab_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-interests-tab.php' );
$begin      = form_method_body( $tab_source, 'begin' );

ck( 'the capability is asked before the nonce and the nonce before the row is read, and every handler goes through that one door',
	array(
		strpos( $begin, 'current_user_can( WPCPM_Roles::CAP_MANAGE )' ) < strpos( $begin, 'check_admin_referer(' ),
		strpos( $begin, 'check_admin_referer(' ) < strpos( $begin, 'WPCPM_Student_Interest::interest(' ),
		substr_count( $tab_source, 'self::begin( ' ),
		substr_count( $tab_source, 'self::move( self::ACTION_' ),
	),
	array( true, true, 2, 4 ) );

echo "\n=== Writes that fail, and two requests at once ===\n";

world();

$GLOBALS['write_refused'] = array( WPCPM_Student_Interest::META_FIELDS );
$before                   = count( $GLOBALS['posts'] );
$refused_id               = WPCPM_Student_Interest::store( answers(), 'new', array(), $consent );
unset( $GLOBALS['write_refused'] );

ck( 'an interest whose answers WordPress would not write is not stored at all: no ID, and no row left behind without them',
	array( $refused_id, count( $GLOBALS['posts'] ) ),
	array( 0, $before ) );

$GLOBALS['write_refused'] = array( WPCPM_Student_Interest::META_CONSENT );
$no_consent               = WPCPM_Student_Interest::store( answers(), 'new', array(), $consent );
unset( $GLOBALS['write_refused'] );

ck( 'nor one whose consent evidence would not write', array( $no_consent, count( $GLOBALS['posts'] ) ), array( 0, $before ) );

$stuck                    = interest_in( 'new' );
$GLOBALS['write_refused'] = array( WPCPM_Student_Interest::META_STATE );
$moved                    = WPCPM_Student_Interest::set_state( $stuck, 'contacted', 9 );
unset( $GLOBALS['write_refused'] );

ck( 'a state WordPress would not write is not reported as changed: the press is refused, the interest is where it was, and nobody is recorded as having moved it',
	array( $moved, WPCPM_Student_Interest::state_of( get_post( $stuck ) ), isset( $GLOBALS['pmeta'][ $stuck ][ WPCPM_Student_Interest::META_CHANGED_BY ] ) ),
	array( false, 'new', false ) );

ck( 'and the time of a change it could not finish is taken back too', isset( $GLOBALS['pmeta'][ $stuck ][ WPCPM_Student_Interest::META_CHANGED ] ), false );

$untimed                  = interest_in( 'new' );
$GLOBALS['write_refused'] = array( WPCPM_Student_Interest::META_CHANGED );
$moved                    = WPCPM_Student_Interest::set_state( $untimed, 'spam', 9 );
unset( $GLOBALS['write_refused'] );

ck( 'a change whose time would not write is refused whole: retention never counts a spam interest from the day it was stored',
	array( $moved, WPCPM_Student_Interest::state_of( get_post( $untimed ) ) ),
	array( false, 'new' ) );

$GLOBALS['cache_deleted'] = array();
$reread                   = interest_in( 'new' );
WPCPM_Student_Interest::set_state( $reread, 'contacted', 9 );
WPCPM_Student_Interest::purge();

ck( 'a state change and the daily deletion each drop what the request remembers of an interest before reading it under the lock',
	count( array_keys( $GLOBALS['cache_deleted'], array( $reread, 'post_meta' ), true ) ) >= 1,
	true );

// The interest's own lock: a press and the daily deletion never both act on one interest.
$old_spam = interest_in( 'spam' );
$GLOBALS['pmeta'][ $old_spam ][ WPCPM_Student_Interest::META_CREATED ] = array( time() - 40 * DAY_IN_SECONDS );

$theirs      = WPCPM_Lock::take( WPCPM_Student_Interest::LOCK_PREFIX . $old_spam, 30 );
$while_held  = array( WPCPM_Student_Interest::purge(), null !== get_post( $old_spam ), WPCPM_Student_Interest::set_state( $old_spam, 'new', 9 ), WPCPM_Student_Interest::delete( $old_spam ) );
WPCPM_Lock::release( WPCPM_Student_Interest::LOCK_PREFIX . $old_spam, $theirs );
$restored    = WPCPM_Student_Interest::set_state( $old_spam, 'new', 9 );
$after_press = array( $restored, WPCPM_Student_Interest::purge(), null !== get_post( $old_spam ), WPCPM_Student_Interest::state_of( get_post( $old_spam ) ) );

ck( 'while another request holds an interest\'s lock the daily deletion leaves it, and a press on it is refused; once it is free a spam interest put back to New is kept by the next run',
	array( $while_held, $after_press ),
	array( array( 0, true, false, false ), array( true, 0, true, 'new' ) ) );

$gone = interest_in( 'spam' );
$GLOBALS['pmeta'][ $gone ][ WPCPM_Student_Interest::META_CREATED ] = array( time() - 40 * DAY_IN_SECONDS );

ck( 'and an old spam interest nobody is touching is deleted, its lock let go after',
	array( WPCPM_Student_Interest::purge(), null === get_post( $gone ), array_key_exists( WPCPM_Student_Interest::LOCK_PREFIX . $gone, $GLOBALS['opts'] ), array_key_exists( WPCPM_Student_Interest::LOCK_PREFIX . $old_spam, $GLOBALS['opts'] ) ),
	array( 1, true, false, false ) );

echo "\n=== The privacy tools ===\n";

world();

$mine    = interest_in( 'new' );
$other   = interest_in( 'contacted', array( 'name' => 'Otto Example', 'email' => 'otto@example.test', 'institution' => 'Sample College', 'anything' => 'A <b>second</b> student.' ) );
$as_rep  = interest_in( 'spam', array( 'name' => 'Pia Example', 'email' => 'pia@example.test', 'representative' => 'Ines Example', 'contact_email' => 'INES@example.test' ) );
$neither = interest_in( 'new', array( 'name' => 'Nia Example', 'email' => 'nia@example.test', 'contact_email' => 'ned@example.test' ) );

ck( 'both tools are registered under one name, each with its callback',
	array( WPCPM_Student_Interest::register_exporter( array( 'core' => array() ) ), WPCPM_Student_Interest::register_eraser( 'not a list' ) ),
	array(
		array(
			'core'                    => array(),
			'wpcpm-student-interests' => array( 'exporter_friendly_name' => 'Interest from students', 'callback' => array( 'WPCPM_Student_Interest', 'export' ) ),
		),
		array(
			'wpcpm-student-interests' => array( 'eraser_friendly_name' => 'Interest from students', 'callback' => array( 'WPCPM_Student_Interest', 'erase' ) ),
		),
	) );

$export = WPCPM_Student_Interest::export( ' Ines@Example.test ' );
$items  = array_column( $export['data'], 'data', 'item_id' );
$named  = function ( array $rows ) {
	return array_column( $rows, 'value', 'name' );
};

ck( 'an address is found on the interests it sent and on the ones that name it as the representative, whatever their state, and on no other',
	array( array_keys( $items ), $export['done'], array_unique( array_column( $export['data'], 'group_id' ) ), array_unique( array_column( $export['data'], 'group_label' ) ) ),
	array( array( 'wpcpm-student-interests-' . $mine, 'wpcpm-student-interests-' . $as_rep ), true, array( 'wpcpm-student-interests' ), array( 'Interest from students' ) ) );
ck( 'a student is handed everything they sent, each value escaped',
	array_diff_key( $named( $items[ 'wpcpm-student-interests-' . $mine ] ), array( 'Received' => 1 ) ),
	array(
		'Your name'                         => 'Ines Example',
		'Your email'                        => 'ines@example.test',
		'Institution name'                  => 'Example University',
		'Country'                           => 'Portugal',
		'City'                              => 'Porto',
		'Institution website'               => 'https://university.example.test',
		'Department'                        => 'Computer Science',
		'Institution representative'        => 'Rui Example',
		'Contact email'                     => 'rui@example.test',
		'How internships or practices work' => 'Based on required hours (e.g. 150 hours), Other (please specify)',
		'Anything else'                     => 'Typed a backslash: C:\\temp and a &quot;quote&quot;',
		// What the site keeps as evidence of the two ticks is the student's too.
		'Agreed to, about the representative' => 'I have told the representative about this request, and they agreed that I share their name and email address with the WordPress Credits program.',
		'Agreed to, about privacy'          => 'By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (https://wordpress.org/about/privacy/).',
		'Privacy policy'                    => 'https://wordpress.org/about/privacy/',
		'Agreed at'                         => '2023-11-14 22:13:20 UTC',
		'Sent from the address'             => '203.0.113.0',
		'Sent from the browser'             => 'Mozilla/5.0 (test)',
	) );
ck( 'a representative is handed the institution\'s details and their own name and address, and not who named them, what the student wrote or what the student agreed to',
	array_keys( $named( $items[ 'wpcpm-student-interests-' . $as_rep ] ) ),
	array( 'Received', 'Institution name', 'Country', 'City', 'Institution website', 'Institution representative', 'Contact email' ) );
ck( 'an address on no interest, or none, exports nothing',
	array( WPCPM_Student_Interest::export( 'nobody@example.test' ), WPCPM_Student_Interest::export( '' ) ),
	array( array( 'data' => array(), 'done' => true ), array( 'data' => array(), 'done' => true ) ) );
ck( 'a value a stranger typed is escaped in the export', $named( array_column( WPCPM_Student_Interest::export( 'otto@example.test' )['data'], 'data' )[0] )['Anything else'], 'A &lt;b&gt;second&lt;/b&gt; student.' );

// The tools find an interest by the answers it keeps, not by the hashes beside them, which are
// salted with the site's authentication keys: a site that changed its keys still finds what it holds.
foreach ( array( $mine, $as_rep ) as $kept ) {
	$GLOBALS['pmeta'][ $kept ][ WPCPM_Student_Interest::META_EMAIL ]   = array( 'a-hash-under-other-keys' );
	$GLOBALS['pmeta'][ $kept ][ WPCPM_Student_Interest::META_CONTACT ] = array( 'another-hash-under-other-keys' );
}

$after_keys = WPCPM_Student_Interest::export( 'ines@example.test' );

ck( 'with the stored hashes no longer the ones this site would make, both interests are still found, the student\'s own still told from the one that names them',
	array(
		array_column( $after_keys['data'], 'item_id' ),
		isset( $named( array_column( $after_keys['data'], 'data', 'item_id' )[ 'wpcpm-student-interests-' . $mine ] )['Your email'] ),
		isset( $named( array_column( $after_keys['data'], 'data', 'item_id' )[ 'wpcpm-student-interests-' . $as_rep ] )['Your email'] ),
	),
	array( array( 'wpcpm-student-interests-' . $mine, 'wpcpm-student-interests-' . $as_rep ), true, false ) );

world();

for ( $i = 0; $i < WPCPM_Student_Interest::PRIVACY_PAGE + 3; $i++ ) {
	interest_in( 'new', array( 'institution' => 'College ' . $i ) );
}

$page_one = WPCPM_Student_Interest::export( 'ines@example.test', 1 );
$page_two = WPCPM_Student_Interest::export( 'ines@example.test', 2 );

ck( 'the export is handed over fifty interests a page, and says when it is done',
	array( count( $page_one['data'] ), $page_one['done'], count( $page_two['data'] ), $page_two['done'] ),
	array( 50, false, 3, true ) );

world();

for ( $i = 0; $i < 53; $i++ ) {
	interest_in( 'new', array( 'institution' => 'College ' . $i ) );
}
$other        = interest_in( 'new', array( 'name' => 'Nia Example', 'email' => 'nia@example.test', 'contact_email' => 'ned@example.test' ) );
$GLOBALS['posts_asked'] = array();
$first        = WPCPM_Student_Interest::erase( 'ines@example.test' );
$asked        = $GLOBALS['posts_asked'];
unset( $GLOBALS['posts_asked'] );
$second       = WPCPM_Student_Interest::erase( 'ines@example.test' );
$lookups      = array();

foreach ( (array) $asked as $query ) {
	$lookups[] = isset( $query['meta_query'][0]['key'] ) ? $query['meta_query'][0]['key'] : 'every interest';
}

ck( 'an erasure deletes fifty interests a call and says when more are left; it finds them by the two addresses kept for it, never by a read of every interest; and the post type stays out of the export of all content',
	array( $first['done'], $second['done'], $second['items_removed'], null !== get_post( $other ), $lookups, $GLOBALS['post_types'][ WPCPM_Student_Interest::POST_TYPE ]['can_export'] ),
	array( false, true, true, true, array( WPCPM_Student_Interest::META_ADDRESS, WPCPM_Student_Interest::META_ADDRESS_CONTACT ), false ) );

world();

$kept_ids = array();
$others   = count( $GLOBALS['posts'] );
for ( $i = 0; $i < 60; $i++ ) {
	$id = interest_in( 'new', array( 'institution' => 'School ' . $i ) );
	if ( $i < 50 ) {
		$kept_ids[] = $id;
	}
}
$GLOBALS['undeletable'] = $kept_ids;
$blocked                = WPCPM_Student_Interest::erase( 'ines@example.test' );
unset( $GLOBALS['undeletable'] );

ck( 'fifty interests WordPress will not delete do not end an erasure before the rest are tried: the ten after them are deleted, and the fifty are reported as still stored',
	array( $blocked['items_removed'], $blocked['items_retained'], $blocked['done'], count( $GLOBALS['posts'] ) - $others ),
	array( true, true, true, 50 ) );

world();

world();

for ( $i = 0; $i < 510; $i++ ) {
	interest_in( 'spam', array( 'institution' => 'Flood ' . $i ) );
}
$later                   = time() + 40 * DAY_IN_SECONDS;
$GLOBALS['posts_asked'] = array();
$run_one                 = WPCPM_Student_Interest::purge( $later );
$purge_asked             = $GLOBALS['posts_asked'];
unset( $GLOBALS['posts_asked'] );
$run_two = WPCPM_Student_Interest::purge( $later );
$as_ids  = true;

foreach ( $purge_asked as $query ) {
	$as_ids = $as_ids && isset( $query['fields'] ) && 'ids' === $query['fields'];
}

ck( 'a daily run reads the IDs alone and deletes five hundred interests at most; the next run takes up the rest',
	array( $run_one, $run_two, $as_ids, WPCPM_Student_Interest::PURGE_MAX ),
	array( 500, 10, true, 500 ) );

world();

$unfound                  = count( $GLOBALS['posts'] );
$GLOBALS['write_refused'] = array( WPCPM_Student_Interest::META_ADDRESS );
$no_address               = interest_in( 'new' );
unset( $GLOBALS['write_refused'] );

ck( 'an interest whose address the privacy tools could not find it by is not stored', array( $no_address, count( $GLOBALS['posts'] ) ), array( 0, $unfound ) );

world();

$mine    = interest_in( 'new' );
$as_rep  = interest_in( 'contacted', array( 'name' => 'Pia Example', 'email' => 'pia@example.test', 'contact_email' => 'Ines@example.test' ) );
$neither = interest_in( 'new', array( 'name' => 'Nia Example', 'email' => 'nia@example.test', 'contact_email' => 'ned@example.test' ) );

$erased = WPCPM_Student_Interest::erase( 'INES@example.test' );
$again  = WPCPM_Student_Interest::erase( 'ines@example.test' );

ck( 'an erasure deletes every interest the address is on, as the student or as the representative, whatever its state, and leaves every other; asked again, it has nothing to remove',
	array( $erased, $again, null === get_post( $mine ), null === get_post( $as_rep ), null !== get_post( $neither ), isset( $GLOBALS['pmeta'][ $mine ] ), WPCPM_Student_Interest::erase( '' )['items_removed'] ),
	array(
		array( 'items_removed' => true, 'items_retained' => false, 'messages' => array(), 'done' => true ),
		array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ),
		true,
		true,
		true,
		false,
		false,
	) );

echo "\n=== The uninstall, and the wiring ===\n";

$students = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students.php' );
$boot     = form_method_body( $students, 'boot' );
$activate = form_method_body( $students, 'activate' );
$deactive = form_method_body( $students, 'deactivate' );
$removed  = form_method_body( $students, 'uninstall' );

ck( 'the Students module boots the three classes after the registration\'s, puts the daily job on the clock at activation and takes it off at deactivation',
	array(
		strpos( $boot, 'WPCPM_Student_Registrations_Tab::init();' ) < strpos( $boot, 'WPCPM_Student_Interest::init();' ),
		false !== strpos( $boot, 'WPCPM_Student_Interest_Form::init();' ),
		false !== strpos( $boot, 'WPCPM_Student_Interests_Tab::init();' ),
		false !== strpos( $activate, 'WPCPM_Student_Interest::schedule();' ),
		false !== strpos( $activate, 'WPCPM_Student_Interest_Form::ensure_page' ),
		false !== strpos( $deactive, 'WPCPM_Student_Interest::unschedule();' ),
	),
	array( true, true, true, true, false, true ) );
ck( 'its uninstall deletes every interest, the page\'s option and the daily job, and no page',
	array(
		false !== strpos( $removed, 'delete_option( WPCPM_Student_Interest_Form::OPT_PAGE );' ),
		false !== strpos( $removed, 'WPCPM_Student_Interest::delete_all();' ),
		false !== strpos( $removed, 'WPCPM_Student_Interest::unschedule();' ),
		false !== strpos( $removed, 'wp_delete_post' ),
	),
	array( true, true, true, false ) );

// What those three lines do, on a site that holds interests in every state.
world();

foreach ( WPCPM_Student_Interest::states() as $state ) {
	interest_in( $state, array( 'email' => $state . '@example.test' ) );
}

WPCPM_Student_Interest::schedule();

delete_option( WPCPM_Student_Interest_Form::OPT_PAGE );
WPCPM_Student_Interest::delete_all();
WPCPM_Student_Interest::unschedule();

$left = array_filter(
	$GLOBALS['posts'],
	function ( $post ) {
		return WPCPM_Student_Interest::POST_TYPE === $post->post_type;
	}
);

ck( 'run, they leave no interest, no meta of one, no option and no job, and the page an editor made stays',
	array( count( $left ), count( $GLOBALS['pmeta'] ), get_option( WPCPM_Student_Interest_Form::OPT_PAGE, 'gone' ), isset( $GLOBALS['cron'][ WPCPM_Student_Interest::CRON_DAILY ] ), null !== get_post( 42 ) ),
	array( 0, 0, 'gone', false, true ) );

$loader    = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'wpcredits-program-manager.php' );
$uninstall = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'uninstall.php' );
$required  = array();

foreach ( array( 'class-wpcpm-student-interest.php', 'class-wpcpm-student-interest-form.php', 'class-wpcpm-student-interests-tab.php' ) as $file ) {
	$required[ $file ] = array(
		strpos( $loader, "'includes/modules/class-wpcpm-student-registrations-tab.php';" ) < strpos( $loader, "'includes/modules/" . $file . "';" ),
		strpos( $uninstall, "'includes/modules/class-wpcpm-student-registrations-tab.php';" ) < strpos( $uninstall, "'includes/modules/" . $file . "';" ),
		1 === substr_count( $loader, "'includes/modules/" . $file . "';" ),
		1 === substr_count( $uninstall, "'includes/modules/" . $file . "';" ),
	);
}

ck( 'the plugin and its uninstaller each load the three files once, after the registration\'s',
	$required, array_fill_keys( array_keys( $required ), array( true, true, true, true ) ) );
ck( 'the tab is on the Students screen after Registrations and before Sync',
	array_keys( ( static function () use ( $students ) { preg_match( '/const TABS = array\((.*?)\);/s', $students, $tabs ); preg_match_all( '/self::(TAB_[A-Z]+)\s*=>/', $tabs[1], $names ); return array_flip( $names[1] ); } )() ),
	array( 'TAB_ACCOUNTS', 'TAB_REGISTRATIONS', 'TAB_INTERESTS', 'TAB_SYNC' ) );

ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );

echo "\n";

if ( $fails > 0 ) {
	printf( "%d FAILURE(S) (%d checks)\n", $fails, $total );
	exit( 1 );
}

printf( "ALL PASS (%d checks)\n", $total );
