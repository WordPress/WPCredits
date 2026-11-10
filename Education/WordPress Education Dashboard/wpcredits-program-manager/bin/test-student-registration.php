<?php
/**
 * Student registration on the site (1.123.54): the settings, the Students columns, the record, the
 * Airtable row, the process, the form and the Registrations tab.
 *
 * Design of 5 November 2026. What each of the seven pins, and why:
 *
 * - **The settings.** No section of their own: each check sets them through the stand-in
 *   `WPCPM_Settings` below (the switch, the institution stages offered, the two retention periods,
 *   the program's contact address, the Airtable connection), and the sections read what each does.
 *   The fields of the Settings tab are bin/test-settings.php's.
 *
 * - **The columns.** Every Students column the row will write is read by `WPCPM_Mentors_Sync::fields()`
 *   under one key, and each name is a column of the Students table as the fixture
 *   `bin/fixtures/students-registration-columns.json` records it: a name Airtable does not know is a
 *   422 for the whole record, since `create_records()` sends no `typecast`. The nine choices of
 *   "Your field of study" are the ones the institutions' student form already offers, in its order.
 *
 * - **The record.** The private post a registration is kept in: the states, the answers kept whole
 *   with a typed backslash, the address kept as its hash, the one registration that holds an address,
 *   the outcome, the tries, Put back's requeue, and retention.
 *
 * - **The row.** The Students row a registration writes, shaped by each column's type, what it leaves
 *   out and the sentence that says so, the copy of the Languages table, and the search by address,
 *   all through the real `WPCPM_Airtable` with the transport stood in.
 *
 * - **The process.** What happens to a registration: the account, the registration held already, the
 *   Students row that holds the address, the new row, Airtable down or refusing, the 72 hours, a
 *   create whose answer was lost, the address's lock and the read again under it, the hourly run with
 *   its forty and the ones it passes over, and the three emails, word for word.
 *
 * - **The form.** The fourteen questions with the Airtable form's words, each refusal with its problem
 *   and its sentence, the page with each reason it is closed, the redraw with every answer kept, the
 *   submit path in the mentor form's order (read off the source, then exercised at each boundary),
 *   the two daily limits, the page that answers before the process runs on `shutdown`, and the one
 *   thank-you every path lands on.
 *
 * - **The tab.** The Registrations tab: who may see it and press, the views and their counts, the
 *   newest first and bounded, what each row says became of it, and the three presses.
 *
 * Run from the plugin root:  php bin/test-student-registration.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['settings'] = array();

/** The settings the registration reads, as a check sets them. */
class WPCPM_Settings {
	public static function get() {
		return $GLOBALS['settings'];
	}
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
	public static function is_connected() {
		$settings = self::get();

		return ! empty( $settings['api_token'] ) && ! empty( $settings['base_id'] );
	}
	public static function airtable_record_url( $table_key, $record_id ) {
		$settings  = self::get();
		$base      = isset( $settings['base_id'] ) ? trim( (string) $settings['base_id'] ) : '';
		$table     = isset( $settings[ $table_key ] ) ? trim( (string) $settings[ $table_key ] ) : '';
		$record_id = trim( (string) $record_id );

		if ( '' === $base || '' === $table || '' === $record_id ) {
			return '';
		}

		return 'https://airtable.com/' . rawurlencode( $base ) . '/' . rawurlencode( $table ) . '/' . rawurlencode( $record_id );
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-student-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-registration.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-airtable-row.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-lock.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-registration-process.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-stash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-test-institutions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';

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

/** The lost-password page, as core draws its address. */
function wp_lostpassword_url( $redirect = '' ) {
	return 'https://example.test/wp-login.php?action=lostpassword';
}

/**
 * `wp_update_post()` as far as the process reaches it: the title, and the slug, which core makes again
 * from the title when it is handed an empty one for a private post. Each call is kept
 * (`$GLOBALS['updated_posts']`, unslashed), and while `$GLOBALS['update_fails']` is set it is refused,
 * as core refuses one the database would not take.
 */
function wp_update_post( $postarr, $wp_error = false ) {
	$data = wp_unslash( (array) $postarr );
	$post = get_post( isset( $data['ID'] ) ? (int) $data['ID'] : 0 );

	$GLOBALS['updated_posts'][] = $data;

	if ( ! $post || ! empty( $GLOBALS['update_fails'] ) ) {
		return $wp_error ? new WP_Error( 'db_update_error', 'Could not update post in the database.' ) : 0;
	}

	if ( isset( $data['post_title'] ) ) {
		$post->post_title = (string) $data['post_title'];
	}

	if ( array_key_exists( 'post_name', $data ) ) {
		$post->post_name = '' !== (string) $data['post_name'] ? (string) $data['post_name'] : trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $post->post_title ) ), '-' );
	}

	return $post->ID;
}

$GLOBALS['mail'] = array();

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

/** A site where registrations are open, on invented IDs and an example.test address. */
function reset_world() {
	$GLOBALS['opts']     = array();
	$GLOBALS['settings'] = array(
		'students_table'                => 'tblSTUDENTS000001',
		'base_id'                       => 'appTESTBASE000001',
		'api_token'                     => 'patTESTNOTAREALTOKEN',
		'student_registrations_enabled' => true,
		'student_registration_stages'   => array( 'Confirmed' ),
		'application_spam_days'         => 30,
		'application_rejected_days'     => 365,
		'mail_contact'                  => 'programs@example.test',
	);
}

reset_world();

require_once __DIR__ . '/stubs/options-lock-wpdb.php';

echo "\n=== The columns ===\n";

$fixture_path = WPCPM_PLUGIN_DIR . 'bin/fixtures/students-registration-columns.json';
$fixture      = is_readable( $fixture_path ) ? json_decode( (string) file_get_contents( $fixture_path ), true ) : null;
$columns      = is_array( $fixture ) && isset( $fixture['columns'] ) ? (array) $fixture['columns'] : array();
$fields       = WPCPM_Mentors_Sync::fields();

$written = array(
	'student_record_name',
	'student_email',
	'student_institution',
	'student_study',
	'student_tutor',
	'student_tutor_email',
	'student_start',
	'student_end',
	'student_hours',
	'student_languages',
	'student_preferred_language',
	'student_access',
	'student_english',
	'student_privacy',
	'student_status',
);

$names = array();

foreach ( $written as $key ) {
	$names[ $key ] = isset( $fields[ $key ] ) ? $fields[ $key ] : null;
}

ck( 'the fixture is there, with the Students table, the day it was read and fifteen columns',
	array( is_array( $fixture ), isset( $fixture['students_table'] ) ? $fixture['students_table'] : null, isset( $fixture['read'] ) ? $fixture['read'] : null, count( $columns ) ),
	array( true, 'tbla8GZg5x6NY7aWt', '2026-11-05', 15 ) );
ck( 'the fifteen keys the row writes are the sync\'s own names, six of them new, the Tutor column with its space and the preferred language with one r',
	$names,
	array(
		'student_record_name'        => 'Full Name',
		'student_email'              => 'Email',
		'student_institution'        => 'Educational Institutions',
		'student_study'              => 'Your field of study',
		'student_tutor'              => 'Tutor ',
		'student_tutor_email'        => 'Tutor email',
		'student_start'              => 'Start Date',
		'student_end'                => 'End Date',
		'student_hours'              => 'Total hours',
		'student_languages'          => 'Languages',
		'student_preferred_language' => 'Prefered language',
		'student_access'             => 'Accessibility needs',
		'student_english'            => 'English and proactivity acceptance',
		'student_privacy'            => 'Privacy Policy Compliance',
		'student_status'             => 'Status',
	) );
ck( 'every name the row writes is a column of the fixture, and the fixture holds no other',
	array( array_diff( array_values( $names ), array_keys( $columns ) ), array_diff( array_keys( $columns ), array_values( $names ) ) ),
	array( array(), array() ) );
ck( 'the Tutor column keeps its space and the preferred language its one r, in the fixture as in the sync',
	array( array_key_exists( 'Tutor ', $columns ), array_key_exists( 'Tutor', $columns ), array_key_exists( 'Prefered language', $columns ), array_key_exists( 'Preferred language', $columns ) ),
	array( true, false, true, false ) );
ck( 'the types are the table\'s: the tutor\'s address an email, the hours a number, the two languages links, the two acceptances checkboxes',
	array( isset( $columns['Tutor email'] ) ? $columns['Tutor email'] : null, isset( $columns['Total hours'] ) ? $columns['Total hours'] : null, isset( $columns['Languages'] ) ? $columns['Languages'] : null, isset( $columns['Prefered language'] ) ? $columns['Prefered language'] : null, isset( $columns['English and proactivity acceptance'] ) ? $columns['English and proactivity acceptance'] : null, isset( $columns['Privacy Policy Compliance'] ) ? $columns['Privacy Policy Compliance'] : null ),
	array( 'email', 'number', 'multipleRecordLinks', 'multipleRecordLinks', 'checkbox', 'checkbox' ) );
ck( 'the nine fields of study the fixture records are the ones the institutions\' student form offers, in its order',
	isset( $fixture['choices']['Your field of study'] ) ? $fixture['choices']['Your field of study'] : null,
	WPCPM_Institution_Student_Form::choices( 'field_of_study' ) );


echo "\n=== The record ===\n";

/** A post of another type, to read a registration through. */
function reg_plain_post() {
	return wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'A page' ) );
}

/** A registration's answers, as the form will hand them over: an invented student on example.test. */
function reg_fields( $email = 'ada@example.test', $name = 'Ada Tester' ) {
	return array(
		'name'  => $name,
		'email' => $email,
		'note'  => 'Typed a backslash: C:\\temp and a "quote"',
	);
}

$consent = array( 'at' => 1790000000, 'text' => 'I agree', 'version' => 'v1' );

ck( 'the post type, the six states in order, the lock and the wait',
	array( WPCPM_Student_Registration::POST_TYPE, WPCPM_Student_Registration::states(), WPCPM_Student_Registration::LOCK_PREFIX, WPCPM_Student_Registration::LOCK_TTL, WPCPM_Student_Registration::WAIT_HOURS ),
	array( 'wpcpm_stu_reg', array( 'waiting', 'written', 'account', 'already', 'failed', 'spam' ), 'wpcpm_sreg_lock_', 300, 72 ) );

WPCPM_Student_Registration::init();
WPCPM_Student_Registration::register_post_type();

ck( 'the post type is private, with no screen, no REST, no query variable and a capability no role holds',
	isset( $GLOBALS['post_types'][ WPCPM_Student_Registration::POST_TYPE ] )
		? array_intersect_key( $GLOBALS['post_types'][ WPCPM_Student_Registration::POST_TYPE ], array_flip( array( 'public', 'show_ui', 'show_in_rest', 'query_var', 'capability_type' ) ) )
		: null,
	array( 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'query_var' => false, 'capability_type' => array( 'wpcpm_stu_reg', 'wpcpm_stu_regs' ) ) );

ck( 'the hash is lower case and trimmed, and empty for no address',
	array( WPCPM_Student_Registration::email_hash( '  Ada@Example.TEST ' ) === WPCPM_Student_Registration::email_hash( 'ada@example.test' ), WPCPM_Student_Registration::email_hash( 'ada@example.test' ) === wp_hash( 'ada@example.test' ), WPCPM_Student_Registration::email_hash( '   ' ) ),
	array( true, true, '' ) );
ck( 'the lock name is the prefix and the first 32 characters of the hash',
	WPCPM_Student_Registration::lock_name( 'Ada@example.test' ),
	'wpcpm_sreg_lock_' . substr( wp_hash( 'ada@example.test' ), 0, 32 ) );

$id   = WPCPM_Student_Registration::store( reg_fields( '  Ada@Example.TEST ' ), 'waiting', array( 'fast' ), $consent );
$post = WPCPM_Student_Registration::registration( $id );

ck( 'a stored registration is private, authored by nobody, titled with the name',
	array( $post instanceof WP_Post, $post ? $post->post_type : null, $post ? $post->post_status : null, $post ? $post->post_author : null, $post ? $post->post_title : null ),
	array( true, 'wpcpm_stu_reg', 'private', 0, 'Ada Tester' ) );
ck( 'it is waiting, and holds the answers whole with the typed backslash, the consent and the signals',
	array( WPCPM_Student_Registration::state_of( $post ), WPCPM_Student_Registration::fields_of( $post ), WPCPM_Student_Registration::consent_of( $post ), WPCPM_Student_Registration::signals_of( $post ), WPCPM_Student_Registration::name_of( $post ) ),
	array( 'waiting', reg_fields( '  Ada@Example.TEST ' ), $consent, array( 'fast' ), 'Ada Tester' ) );
ck( 'the address is kept as its hash, the same for any spelling of it',
	array( get_post_meta( $id, WPCPM_Student_Registration::META_EMAIL, true ), WPCPM_Student_Registration::email_of( $post ) ),
	array( WPCPM_Student_Registration::email_hash( 'ada@example.test' ), WPCPM_Student_Registration::email_hash( 'ada@example.test' ) ) );
ck( 'it is queued now, not settled, and has no first',
	array( abs( (int) get_post_meta( $id, WPCPM_Student_Registration::META_QUEUED, true ) - time() ) < 5, get_post_meta( $id, WPCPM_Student_Registration::META_SETTLED, true ), get_post_meta( $id, WPCPM_Student_Registration::META_FIRST, true ) ),
	array( true, '', '' ) );
ck( 'the outcome reads with every key present',
	WPCPM_Student_Registration::outcome_of( $post ),
	array( 'record' => '', 'records' => array(), 'user' => 0, 'left_out' => array(), 'error' => '', 'tries' => 0, 'tried_at' => 0, 'create_sent' => 0, 'mail' => '' ) );
ck( 'a registration of a post that is not one, or of none, is null',
	array( WPCPM_Student_Registration::registration( 999999 ), WPCPM_Student_Registration::registration( reg_plain_post() ) ),
	array( null, null ) );

$none = WPCPM_Student_Registration::registration( WPCPM_Student_Registration::store( array( 'email' => 'nobody@example.test' ), 'surprise', array(), array() ) );

ck( 'a state that is not one is waiting, and a registration with no name is titled so',
	array( WPCPM_Student_Registration::state_of( $none ), $none->post_title ),
	array( 'waiting', 'Registration with no name' ) );

$spam = WPCPM_Student_Registration::registration( WPCPM_Student_Registration::store( reg_fields( 'bot@example.test', 'Bot' ), 'spam', array( 'hidden-field' ), $consent ) );

ck( 'a spam registration is settled when it is stored',
	array( WPCPM_Student_Registration::state_of( $spam ), abs( (int) get_post_meta( $spam->ID, WPCPM_Student_Registration::META_SETTLED, true ) - time() ) < 5 ),
	array( 'spam', true ) );

$already_id = WPCPM_Student_Registration::store( reg_fields( 'ada@example.test' ), 'already', array(), $consent, $id );
$already    = WPCPM_Student_Registration::registration( $already_id );

ck( 'an already entry keeps no answers, points at the first, and is settled',
	array( WPCPM_Student_Registration::state_of( $already ), isset( $GLOBALS['pmeta'][ $already_id ][ WPCPM_Student_Registration::META_FIELDS ] ), (int) get_post_meta( $already_id, WPCPM_Student_Registration::META_FIRST, true ), abs( (int) get_post_meta( $already_id, WPCPM_Student_Registration::META_SETTLED, true ) - time() ) < 5, WPCPM_Student_Registration::fields_of( $already ), $already->post_title ),
	array( 'already', false, $id, true, array(), 'Registration with no name' ) );

$GLOBALS['insert_fails'] = true;
ck( 'a registration WordPress refuses is 0', WPCPM_Student_Registration::store( reg_fields(), 'waiting', array(), $consent ), 0 );
$GLOBALS['insert_fails'] = false;

echo "\n=== The record: the one that holds an address ===\n";

$hash = WPCPM_Student_Registration::email_hash( 'held@example.test' );
$mk   = function ( $state, $email = 'held@example.test' ) use ( $consent ) {
	$new = WPCPM_Student_Registration::store( reg_fields( $email ), 'waiting', array(), $consent );
	if ( 'waiting' !== $state ) {
		WPCPM_Student_Registration::set_state( $new, $state );
	}

	return $new;
};

ck( 'nothing holds an address nobody registered', WPCPM_Student_Registration::held_for( $hash, 0 ), null );

$first = $mk( 'written' );
$held  = WPCPM_Student_Registration::held_for( $hash, 0 );

ck( 'a written registration holds it', $held ? $held->ID : null, $first );
ck( 'but never the one excluded', WPCPM_Student_Registration::held_for( $hash, $first ), null );

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();

$older = $mk( 'waiting' );
$newer = $mk( 'waiting' );

$held = WPCPM_Student_Registration::held_for( $hash, $newer );
ck( 'an older waiting registration holds it against a newer one', $held ? $held->ID : null, $older );
ck( 'a newer waiting registration holds nothing against an older one', WPCPM_Student_Registration::held_for( $hash, $older ), null );

foreach ( array( 'failed', 'spam', 'account', 'already' ) as $state ) {
	$GLOBALS['posts'] = array();
	$GLOBALS['pmeta'] = array();

	$other = $mk( $state );
	$mine  = $mk( 'waiting' );

	ck( 'a ' . $state . ' registration holds nothing', WPCPM_Student_Registration::held_for( $hash, $mine ), null );
}

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();

echo "\n=== The record: state, tries, requeue ===\n";

$a = $mk( 'waiting', 'a@example.test' );
$b = $mk( 'waiting', 'b@example.test' );

ck( 'a state that is not one is refused and changes nothing',
	array( WPCPM_Student_Registration::set_state( $a, 'lost' ), WPCPM_Student_Registration::state_of( WPCPM_Student_Registration::registration( $a ) ) ),
	array( false, 'waiting' ) );
ck( 'writing a state settles it, merges the outcome and keeps what was there',
	array( WPCPM_Student_Registration::set_state( $a, 'written', array( 'record' => 'recSTUDENT0000001' ) ), WPCPM_Student_Registration::set_state( $a, 'written', array( 'mail' => 'received' ) ) ),
	array( true, true ) );

$post_a  = WPCPM_Student_Registration::registration( $a );
$outcome = WPCPM_Student_Registration::outcome_of( $post_a );

ck( 'the outcome keeps both merges', array( $outcome['record'], $outcome['mail'] ), array( 'recSTUDENT0000001', 'received' ) );
ck( 'and the registration is settled', abs( (int) get_post_meta( $a, WPCPM_Student_Registration::META_SETTLED, true ) - time() ) < 5, true );

WPCPM_Student_Registration::set_state( $b, 'waiting' );
ck( 'setting waiting does not settle it', get_post_meta( $b, WPCPM_Student_Registration::META_SETTLED, true ), '' );

WPCPM_Student_Registration::note_try( $b, 'Airtable said no', 1790000100 );
WPCPM_Student_Registration::note_try( $b, 'Airtable said no again', 1790003700 );

$outcome = WPCPM_Student_Registration::outcome_of( WPCPM_Student_Registration::registration( $b ) );

ck( 'two tries count 2, the last error and time kept', array( $outcome['tries'], $outcome['error'], $outcome['tried_at'] ), array( 2, 'Airtable said no again', 1790003700 ) );

$s = $mk( 'waiting', 's@example.test' );
WPCPM_Student_Registration::set_state( $s, 'spam', array( 'error' => 'caught' ) );

ck( 'requeue puts a spam registration back to waiting, queued now, settled and outcome cleared',
	array( WPCPM_Student_Registration::requeue( $s ), WPCPM_Student_Registration::state_of( WPCPM_Student_Registration::registration( $s ) ), get_post_meta( $s, WPCPM_Student_Registration::META_SETTLED, true ), abs( (int) get_post_meta( $s, WPCPM_Student_Registration::META_QUEUED, true ) - time() ) < 5, WPCPM_Student_Registration::outcome_of( WPCPM_Student_Registration::registration( $s ) )['error'] ),
	array( true, 'waiting', '', true, '' ) );
ck( 'requeue leaves a registration that is not spam alone', array( WPCPM_Student_Registration::requeue( $a ), WPCPM_Student_Registration::state_of( WPCPM_Student_Registration::registration( $a ) ) ), array( false, 'written' ) );

ck( 'in_states lists by date then ID, oldest first when asked, and count_in counts',
	array(
		array_map( function ( $p ) { return $p->ID; }, WPCPM_Student_Registration::in_states( array( 'waiting' ), 0, 'ASC' ) ),
		array_map( function ( $p ) { return $p->ID; }, WPCPM_Student_Registration::in_states( array( 'waiting' ) ) ),
		array_map( function ( $p ) { return $p->ID; }, WPCPM_Student_Registration::in_states( array( 'waiting', 'written' ), 2 ) ),
		WPCPM_Student_Registration::count_in( array( 'waiting' ) ),
		WPCPM_Student_Registration::count_in( array( 'waiting', 'written', 'spam' ) ),
		WPCPM_Student_Registration::count_in( array( 'nonsense' ) ),
	),
	array( array( $b, $s ), array( $s, $b ), array( $s, $b ), 2, 3, 0 ) );

echo "\n=== The record: retention ===\n";

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();

$now  = 1800000000;
$aged = function ( $state, $days_ago, $email ) use ( $mk, $now ) {
	$new = $mk( $state, $email );

	update_post_meta( $new, WPCPM_Student_Registration::META_SETTLED, $now - (int) round( $days_ago * DAY_IN_SECONDS ) );

	return $new;
};

$rows = array(
	'spam_old'     => $aged( 'spam', 31, 'p1@example.test' ),
	'spam_new'     => $aged( 'spam', 29, 'p2@example.test' ),
	'written_old'  => $aged( 'written', 366, 'p3@example.test' ),
	'written_new'  => $aged( 'written', 364, 'p4@example.test' ),
	'account_old'  => $aged( 'account', 366, 'p5@example.test' ),
	'already_old'  => $aged( 'already', 366, 'p6@example.test' ),
	'failed_old'   => $aged( 'failed', 366, 'p7@example.test' ),
	'failed_new'   => $aged( 'failed', 364, 'p8@example.test' ),
	'spam_between' => $aged( 'spam', 100, 'p9@example.test' ),
);

$waiting = $mk( 'waiting', 'p10@example.test' );
update_post_meta( $waiting, WPCPM_Student_Registration::META_QUEUED, $now - 400 * DAY_IN_SECONDS );

ck( 'purge with the defaults answers how many it removed', WPCPM_Student_Registration::purge( $now ), 6 );
ck( 'it removed spam past 30 days and the rest past 365, and kept what is younger and what waits',
	array_map( function ( $id ) { return null !== get_post( $id ); }, $rows + array( 'waiting' => $waiting ) ),
	array( 'spam_old' => false, 'spam_new' => true, 'written_old' => false, 'written_new' => true, 'account_old' => false, 'already_old' => false, 'failed_old' => false, 'failed_new' => true, 'spam_between' => false, 'waiting' => true ) );

$GLOBALS['settings']['application_spam_days']     = 0;
$GLOBALS['settings']['application_rejected_days'] = 0;

$old = $aged( 'spam', 4000, 'q1@example.test' );
$old2 = $aged( 'written', 4000, 'q2@example.test' );

ck( 'with both settings 0 nothing is removed', array( WPCPM_Student_Registration::purge( $now ), null !== get_post( $old ), null !== get_post( $old2 ) ), array( 0, true, true ) );

reset_world();

$one = $mk( 'waiting', 'r1@example.test' );
WPCPM_Student_Registration::forget_cached( $one );

WPCPM_Student_Registration::delete_all();

ck( 'delete_all removes every registration and its meta', array( count( WPCPM_Student_Registration::in_states( WPCPM_Student_Registration::states() ) ), isset( $GLOBALS['pmeta'][ $one ] ) ), array( 0, false ) );
ck( 'and an unmodeled query was never asked', $GLOBALS['unmodeled'] ?? array(), array() );

echo "\n=== The row ===\n";

/** The Students table as `fetch_schema()` gives one table, on the fixture's fifteen columns. */
function students_table_spec( array $changes = array() ) {
	global $columns, $fixture;

	$spec = array();

	foreach ( $columns as $name => $type ) {
		$options = array();

		if ( 'Your field of study' === $name ) {
			$options['choices'] = array();

			foreach ( $fixture['choices']['Your field of study'] as $choice ) {
				$options['choices'][] = array( 'name' => $choice );
			}
		}

		if ( 'Languages' === $name || 'Prefered language' === $name ) {
			$options['linkedTableId'] = 'tblLANGUAGES00001';
		}

		$spec[ $name ] = array(
			'type'    => $type,
			'options' => $options,
		);
	}

	foreach ( $changes as $name => $column ) {
		if ( null === $column ) {
			unset( $spec[ $name ] );
		} else {
			$spec[ $name ] = $column;
		}
	}

	return array(
		'name'    => 'Students',
		'primary' => 'Full Name',
		'columns' => $spec,
	);
}

/** The links the form's draw holds: record ID to name, for the institutions and the languages. */
function links() {
	return array(
		'institutions' => array(
			'recINSTITUTION001' => 'Example University',
			'recINSTITUTION002' => 'Sample College',
		),
		'languages'    => array(
			'recLANGENGLISH001' => 'English',
			'recLANGPOLISH0001' => 'Polish',
			'recLANGSPANISH001' => 'Spanish',
		),
	);
}

/** The answers as the form stores them. */
function answers( array $more = array() ) {
	return array_merge(
		array(
			'name'             => 'Ada Example',
			'email'            => 'ada@example.test',
			'institution'      => 'recINSTITUTION001',
			'institution_name' => 'Example University',
			'study'            => 'technology & engineering',
			'tutor'            => 'Tutor Example',
			'tutor_email'      => 'tutor@example.test',
			'start'            => '2026-12-01',
			'end'              => '2027-03-31',
			'hours'            => '150.5',
			'languages'        => array( 'Polish', 'English' ),
			'preferred'        => 'English',
			'access'           => 'Large text &amp; a screen reader, "please" &lt; 3 steps.',
			'english'          => true,
			'privacy'          => true,
		),
		$more
	);
}

$row = WPCPM_Student_Airtable_Row::cells_for( answers(), students_table_spec(), links() );

ck( 'the row a registration writes, column by column: the answers by each column\'s type, Tutor with its space, the two boxes ticked',
	$row['cells'],
	array(
		'Full Name'                          => 'Ada Example',
		'Email'                              => 'ada@example.test',
		'Educational Institutions'           => array( 'recINSTITUTION001' ),
		'Your field of study'                => 'Technology & Engineering',
		'Tutor '                             => 'Tutor Example',
		'Tutor email'                        => 'tutor@example.test',
		'Start Date'                         => '2026-12-01',
		'End Date'                           => '2027-03-31',
		'Total hours'                        => 150.5,
		'Languages'                          => array( 'recLANGPOLISH0001', 'recLANGENGLISH001' ),
		'Prefered language'                  => array( 'recLANGENGLISH001' ),
		'Accessibility needs'                => 'Large text & a screen reader, "please" < 3 steps.',
		'English and proactivity acceptance' => true,
		'Privacy Policy Compliance'          => true,
	) );
ck( 'and Status is not sent, so Airtable keeps it empty', array_key_exists( 'Status', $row['cells'] ), false );
ck( 'and nothing is left out', $row['left_out'], array() );

$row = WPCPM_Student_Airtable_Row::cells_for( answers( array( 'languages' => array( 'Polish', 'Klingon' ) ) ), students_table_spec(), links() );

ck( 'a language the copy lacks is left out by name and the one it holds is written',
	array( $row['cells']['Languages'], $row['left_out'] ),
	array( array( 'recLANGPOLISH0001' ), array( 'Languages' => array( 'why' => 'unmatched', 'values' => array( 'Klingon' ) ) ) ) );
ck( 'and says so, word for word', WPCPM_Student_Airtable_Row::left_out_sentence( $row['left_out'] ), 'Languages: not in the base: Klingon.' );

$gone = links();
unset( $gone['institutions']['recINSTITUTION001'] );
$row  = WPCPM_Student_Airtable_Row::cells_for( answers(), students_table_spec(), $gone );

ck( 'an institution the links no longer hold is left out by its name, and the rest of the row is written',
	array( array_key_exists( 'Educational Institutions', $row['cells'] ), $row['cells']['Full Name'], $row['left_out'] ),
	array( false, 'Ada Example', array( 'Educational Institutions' => array( 'why' => 'unmatched', 'values' => array( 'Example University' ) ) ) ) );
ck( 'and says so', WPCPM_Student_Airtable_Row::left_out_sentence( $row['left_out'] ), 'Educational Institutions: not in the base: Example University.' );

$row = WPCPM_Student_Airtable_Row::cells_for( answers( array( 'study' => 'Basket Weaving' ) ), students_table_spec(), links() );

ck( 'a field of study the column lacks is left out by name',
	array( array_key_exists( 'Your field of study', $row['cells'] ), $row['left_out'] ),
	array( false, array( 'Your field of study' => array( 'why' => 'unmatched', 'values' => array( 'Basket Weaving' ) ) ) ) );

$row = WPCPM_Student_Airtable_Row::cells_for( answers(), students_table_spec( array( 'Accessibility needs' => null ) ), links() );

ck( 'a column the table lacks is left out as no-column, and said so',
	array( array_key_exists( 'Accessibility needs', $row['cells'] ), $row['left_out'], WPCPM_Student_Airtable_Row::left_out_sentence( $row['left_out'] ) ),
	array( false, array( 'Accessibility needs' => array( 'why' => 'no-column', 'values' => array() ) ), 'Accessibility needs: the Students table has no such column.' ) );

$row = WPCPM_Student_Airtable_Row::cells_for( answers(), students_table_spec( array( 'Total hours' => array( 'type' => 'rating', 'options' => array() ) ) ), links() );

ck( 'a column of a type the site does not write is left out as type, and said so',
	array( array_key_exists( 'Total hours', $row['cells'] ), $row['left_out'], WPCPM_Student_Airtable_Row::left_out_sentence( $row['left_out'] ) ),
	array( false, array( 'Total hours' => array( 'why' => 'type', 'values' => array( 'rating' ) ) ), 'Total hours: the column is a rating, which the site does not write.' ) );

$row = WPCPM_Student_Airtable_Row::cells_for(
	answers( array( 'languages' => array( 'Klingon' ), 'study' => 'Basket Weaving' ) ),
	students_table_spec( array( 'Total hours' => array( 'type' => 'rating', 'options' => array() ), 'Tutor email' => null ) ),
	links()
);

ck( 'several are said in the order of the row, joined by a space',
	WPCPM_Student_Airtable_Row::left_out_sentence( $row['left_out'] ),
	'Your field of study: not in the base: Basket Weaving. Tutor email: the Students table has no such column. Total hours: the column is a rating, which the site does not write. Languages: not in the base: Klingon.' );
ck( 'and nothing left out is no sentence', WPCPM_Student_Airtable_Row::left_out_sentence( array() ), '' );

$row = WPCPM_Student_Airtable_Row::cells_for( answers(), students_table_spec( array( 'Tutor email' => array( 'type' => 'singleLineText', 'options' => array() ) ) ), links() );

ck( 'Tutor email as a single line of text takes the address too', array( $row['cells']['Tutor email'], $row['left_out'] ), array( 'tutor@example.test', array() ) );

$row = WPCPM_Student_Airtable_Row::cells_for( answers( array( 'tutor' => '', 'tutor_email' => '', 'access' => '' ) ), students_table_spec(), links() );

ck( 'an empty tutor, tutor address and accessibility answer send nothing and are not left out',
	array( array_key_exists( 'Tutor ', $row['cells'] ), array_key_exists( 'Tutor email', $row['cells'] ), array_key_exists( 'Accessibility needs', $row['cells'] ), $row['left_out'] ),
	array( false, false, false, array() ) );

/* ---- the transport ---------------------------------------------------------------------------- */

$GLOBALS['now']      = 1700000000.0;
$GLOBALS['sent']     = array();
$GLOBALS['airtable'] = array();

function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['sent'][] = array( 'url' => $url, 'args' => $args );

	$method = isset( $args['method'] ) ? $args['method'] : 'GET';

	if ( false !== strpos( $url, '/meta/bases/' ) ) {
		$route = 'schema';
	} elseif ( 'POST' === $method ) {
		$route = 'create';
	} elseif ( false !== strpos( $url, '/tblLANGUAGES00001' ) ) {
		$route = 'languages';
	} elseif ( false !== strpos( $url, '/tblSTUDENTS000001' ) ) {
		$route = 'find';
	} else {
		throw new Exception( 'wp_remote_request() called for a URL no route holds: ' . $url );
	}

	if ( ! isset( $GLOBALS['airtable'][ $route ] ) ) {
		throw new Exception( 'wp_remote_request() called for ' . $route . ' with nothing set: ' . $url );
	}

	return $GLOBALS['airtable'][ $route ];
}
function wp_remote_retrieve_response_code( $r ) {
	return is_array( $r ) ? ( $r['response']['code'] ?? 200 ) : 0;
}
function wp_remote_retrieve_body( $r ) {
	return is_array( $r ) ? ( $r['body'] ?? '' ) : '';
}
function wp_remote_retrieve_header( $r, $h ) {
	foreach ( ( is_array( $r ) && isset( $r['headers'] ) ) ? $r['headers'] : array() as $name => $value ) {
		if ( strtolower( $name ) === strtolower( $h ) ) {
			return $value;
		}
	}

	return '';
}

/** A canned answer in the shape `wp_remote_request()` returns. */
function api_answer( $code, $body ) {
	return array(
		'response' => array( 'code' => $code ),
		'headers'  => array(),
		'body'     => is_string( $body ) ? $body : json_encode( $body ),
	);
}

/** The base's schema as Airtable's meta endpoint answers it, built from the Students table spec and a Languages table. */
function schema_answer() {
	$fields = array();
	$index  = 0;

	foreach ( students_table_spec()['columns'] as $name => $column ) {
		$fields[] = array(
			'id'      => 'fldSTU' . ++$index,
			'name'    => $name,
			'type'    => $column['type'],
			'options' => $column['options'],
		);
	}

	return api_answer(
		200,
		array(
			'tables' => array(
				array(
					'id'             => 'tblSTUDENTS000001',
					'name'           => 'Students',
					'primaryFieldId' => 'fldSTU1',
					'fields'         => $fields,
				),
				array(
					'id'             => 'tblLANGUAGES00001',
					'name'           => 'Languages',
					'primaryFieldId' => 'fldLANG1',
					'fields'         => array( array( 'id' => 'fldLANG1', 'name' => 'Name', 'type' => 'singleLineText' ) ),
				),
			),
		)
	);
}

/** A client on the suite's clock, with nothing recorded, sent or kept. */
function fresh_client() {
	reset_world();

	$GLOBALS['now']        = 1700000000.0;
	$GLOBALS['sent']       = array();
	$GLOBALS['transients'] = array();
	$GLOBALS['airtable']   = array( 'schema' => schema_answer() );

	WPCPM_Airtable::for_tests(
		static function () {
			return $GLOBALS['now'];
		},
		static function ( $seconds ) {
			$GLOBALS['now'] += $seconds;
		}
	);

	return new WPCPM_Airtable( array( 'api_token' => 'patTESTNOTAREALTOKEN', 'base_id' => 'appTESTBASE000001' ) );
}

$client = fresh_client();
$table    = WPCPM_Student_Airtable_Row::students_table( $client );

ck( 'the Students table is the one the setting names, as the schema gives it',
	array( is_array( $table ), $table['name'] ?? null, $table['primary'] ?? null, array_keys( $table['columns'] ?? array() ) === array_keys( $columns ) ),
	array( true, 'Students', 'Full Name', true ) );

$GLOBALS['settings']['students_table'] = 'tblNOSUCHTABLE01';
$client                              = fresh_client();
$GLOBALS['settings']['students_table'] = 'tblNOSUCHTABLE01';
$missing                               = WPCPM_Student_Airtable_Row::students_table( $client );

ck( 'a table the base lacks is the client\'s own error, as it came', array( is_wp_error( $missing ), $missing->get_error_code() ), array( true, 'wpcpm_airtable_no_table' ) );

$client                          = fresh_client();
$GLOBALS['airtable']['schema']     = api_answer( 403, array( 'error' => array( 'type' => 'NOT_AUTHORIZED' ) ) );
$refused                           = WPCPM_Student_Airtable_Row::students_table( $client );

ck( 'and a schema the token is refused is the error as it came', array( is_wp_error( $refused ), $refused->get_error_code() ), array( true, 'wpcpm_airtable_error' ) );

echo "\n--- Finding a row by address ---\n";

$client                  = fresh_client();
$GLOBALS['airtable']['find'] = api_answer( 200, array( 'records' => array( array( 'id' => 'recSTUDENTROW0001', 'fields' => array() ), array( 'id' => 'not-an-id', 'fields' => array() ) ) ) );
$found                     = WPCPM_Student_Airtable_Row::find_by_email( '  Ada.O\'Brien+wp@Example.test ', $client );

ck( 'the IDs found are the record IDs Airtable gave, and only those', $found, array( 'recSTUDENTROW0001' ) );
ck( 'and one GET was sent', array( count( $GLOBALS['sent'] ), $GLOBALS['sent'][0]['args']['method'] ), array( 1, 'GET' ) );

parse_str( (string) parse_url( $GLOBALS['sent'][0]['url'], PHP_URL_QUERY ), $query );

ck( 'whose formula compares the address trimmed and in lower case, the quote escaped', $query['filterByFormula'] ?? null, "TRIM(LOWER({Email})) = 'ada.o\\'brien+wp@example.test'" );
ck( 'and whose URL carries the plus sign as %2B, so Airtable does not read a space', false !== strpos( $GLOBALS['sent'][0]['url'], '%2B' ), true );
ck( 'and asks for the address column alone', $query['fields'] ?? null, array( 'Email' ) );

$empty = WPCPM_Student_Airtable_Row::find_by_email( '   ', $client );

ck( 'an empty address is an error, and nothing more is sent', array( is_wp_error( $empty ), count( $GLOBALS['sent'] ) ), array( true, 1 ) );

$GLOBALS['airtable']['find'] = api_answer( 200, array( 'records' => array() ) );

ck( 'an address nobody has is no IDs', WPCPM_Student_Airtable_Row::find_by_email( 'nobody@example.test', $client ), array() );

$GLOBALS['airtable']['find'] = api_answer( 500, array( 'error' => 'SERVER' ) );
$failed                      = WPCPM_Student_Airtable_Row::find_by_email( 'ada@example.test', $client );

ck( 'and a failed read is the error', is_wp_error( $failed ), true );

echo "\n--- Creating the row ---\n";

$client                      = fresh_client();
$GLOBALS['airtable']['create'] = api_answer( 200, array( 'records' => array( array( 'id' => 'recSTUDENTROW0001', 'fields' => array() ) ) ) );
$created                       = WPCPM_Student_Airtable_Row::create( array( 'Full Name' => 'Ada Example' ), $client );
$posted                        = json_decode( (string) $GLOBALS['sent'][0]['args']['body'], true );

ck( 'a created row answers its record ID', $created, 'recSTUDENTROW0001' );
ck( 'with one POST of the one record and no typecast', array( count( $GLOBALS['sent'] ), $GLOBALS['sent'][0]['args']['method'], $posted ), array( 1, 'POST', array( 'records' => array( array( 'fields' => array( 'Full Name' => 'Ada Example' ) ) ) ) ) );

$GLOBALS['airtable']['create'] = api_answer( 200, array( 'records' => array() ) );
$none                          = WPCPM_Student_Airtable_Row::create( array( 'Full Name' => 'Ada Example' ), $client );

ck( 'an answer without a record ID is the error that says so', array( is_wp_error( $none ), $none->get_error_message() ), array( true, 'Airtable did not answer with a record ID.' ) );

$GLOBALS['airtable']['create'] = api_answer( 422, array( 'error' => array( 'type' => 'INVALID_VALUE_FOR_COLUMN', 'message' => 'Field is invalid' ) ) );
update_option( WPCPM_Student_Airtable_Row::LANGUAGES_OPTION, array( 'read' => 1700000000, 'table' => 'tblLANGUAGES00001', 'names' => array( 'recLANGENGLISH001' => 'English' ), 'stale' => false ), false );
$refused = WPCPM_Student_Airtable_Row::create( array( 'Full Name' => 'Ada Example' ), $client );

ck( 'a 422 is the error as it came', array( is_wp_error( $refused ), $refused->get_error_data()['status'] ?? null ), array( true, 422 ) );
ck( 'and marks the copy of the Languages table stale, keeping its names', array( get_option( WPCPM_Student_Airtable_Row::LANGUAGES_OPTION )['stale'], WPCPM_Student_Airtable_Row::languages() ), array( true, array( 'recLANGENGLISH001' => 'English' ) ) );

echo "\n--- The copy of the Languages table ---\n";

$languages_answer = api_answer(
	200,
	array(
		'records' => array(
			array( 'id' => 'recLANGSPANISH001', 'fields' => array( 'Name' => 'Spanish' ) ),
			array( 'id' => 'recLANGPOLISH0001', 'fields' => array( 'Name' => ' Polish ' ) ),
			array( 'id' => 'recLANGENGLISH001', 'fields' => array( 'Name' => 'english' ) ),
			array( 'id' => 'recLANGBLANK00001', 'fields' => array() ),
			array( 'id' => 'recLANGDUPPOLISH1', 'fields' => array( 'Name' => 'polish' ) ),
			array( 'id' => 'not-an-id', 'fields' => array( 'Name' => 'Latin' ) ),
		),
	)
);

$client                      = fresh_client();
$GLOBALS['airtable']['languages'] = $languages_answer;
$NOW                           = 1700000000;

ck( 'with no copy, languages() is empty', WPCPM_Student_Airtable_Row::languages(), array() );
ck( 'refresh_languages() reads the table the Languages column links and answers true', WPCPM_Student_Airtable_Row::refresh_languages( $client, $NOW ), true );
ck( 'the copy is record ID to name, A to Z without regard to case, a name held twice, a row with no name and a row with no record ID passed over',
	WPCPM_Student_Airtable_Row::languages(),
	array( 'recLANGENGLISH001' => 'english', 'recLANGPOLISH0001' => 'Polish', 'recLANGSPANISH001' => 'Spanish' ) );

ck( 'and what is stored holds those three alone: the row with no name and the row with no record ID never reach the option',
	array_keys( get_option( WPCPM_Student_Airtable_Row::LANGUAGES_OPTION )['names'] ),
	array( 'recLANGENGLISH001', 'recLANGPOLISH0001', 'recLANGSPANISH001' ) );

$reads = static function () {
	return count( array_filter( $GLOBALS['sent'], static function ( $request ) {
		return false !== strpos( $request['url'], '/tblLANGUAGES00001' );
	} ) );
};

ck( 'and it was one read of the linked table, asking for its first column alone', array( $reads(), false !== strpos( $GLOBALS['sent'][ count( $GLOBALS['sent'] ) - 1 ]['url'], 'fields%5B%5D=Name' ) ), array( 1, true ) );
ck( 'within the day it does not read again', array( WPCPM_Student_Airtable_Row::refresh_languages( $client, $NOW + 3600 ), $reads() ), array( false, 1 ) );
ck( 'a day on it reads again', array( WPCPM_Student_Airtable_Row::refresh_languages( $client, $NOW + 90000 ), $reads() ), array( true, 2 ) );

$GLOBALS['airtable']['create'] = api_answer( 422, array( 'error' => 'INVALID' ) );
WPCPM_Student_Airtable_Row::create( array( 'Full Name' => 'Ada Example' ), $client );

ck( 'a refused create marks it stale, and then it reads again within the day', array( get_option( WPCPM_Student_Airtable_Row::LANGUAGES_OPTION )['stale'], WPCPM_Student_Airtable_Row::refresh_languages( $client, $NOW + 90100 ), $reads() ), array( true, true, 3 ) );
ck( 'and the copy is fresh again', get_option( WPCPM_Student_Airtable_Row::LANGUAGES_OPTION )['stale'], false );

$GLOBALS['airtable']['languages'] = api_answer( 500, array( 'error' => 'SERVER' ) );
$failed                           = WPCPM_Student_Airtable_Row::refresh_languages( $client, $NOW + 2 * 90000 );

ck( 'a failed read answers the error and keeps the old copy', array( is_wp_error( $failed ), array_keys( WPCPM_Student_Airtable_Row::languages() ) ), array( true, array( 'recLANGENGLISH001', 'recLANGPOLISH0001', 'recLANGSPANISH001' ) ) );

echo "\n--- The institutions ---\n";

update_option(
	WPCPM_Institutions_Index::OPT_NAME,
	array(
		'v'    => WPCPM_Institutions_Index::VERSION,
		'read' => 1700000000,
		'rows' => array(
			'recINSTITUTION001' => array( 'record_id' => 'recINSTITUTION001', 'name' => 'Example University ' ),
			'recINSTITUTION002' => array( 'record_id' => 'recINSTITUTION002', 'name' => 'Sample College' ),
		),
	)
);

ck( 'institution_links() is every row of the index, record ID to its name trimmed',
	WPCPM_Student_Airtable_Row::institution_links(),
	array( 'recINSTITUTION001' => 'Example University', 'recINSTITUTION002' => 'Sample College' ) );

echo "\n=== What happens to a registration ===\n";

$T = 1800000000;

/** The transport answers a failed connection, with no status, for each route named (all of them by default). */
function airtable_down( array $routes = array( 'schema', 'find', 'create', 'languages' ) ) {
	foreach ( $routes as $route ) {
		$GLOBALS['airtable'][ $route ] = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
	}
}

/** The create is refused with a status. */
function airtable_refuses( $code ) {
	$GLOBALS['airtable']['create'] = api_answer( $code, array( 'error' => array( 'type' => 'INVALID_VALUE_FOR_COLUMN', 'message' => 'Field is invalid' ) ) );
}

/** The search answers these record IDs. */
function airtable_holds( array $ids ) {
	$records = array();

	foreach ( $ids as $id ) {
		$records[] = array( 'id' => $id, 'fields' => array() );
	}

	$GLOBALS['airtable']['find'] = api_answer( 200, array( 'records' => $records ) );
}

/** A site with registrations open, Airtable answering, the languages and institutions the row needs, and nothing sent. */
function process_world() {
	global $T, $languages_answer;

	$client = fresh_client();

	$GLOBALS['users']     = array();
	$GLOBALS['posts']     = array();
	$GLOBALS['pmeta']     = array();
	$GLOBALS['mail']      = array();
	$GLOBALS['mail_fails'] = false;
	$GLOBALS['cron']      = array();

	$GLOBALS['update_fails']  = false;
	$GLOBALS['updated_posts'] = array();

	airtable_holds( array() );

	$GLOBALS['airtable']['create']    = api_answer( 200, array( 'records' => array( array( 'id' => 'recSTUDENTROW0001', 'fields' => array() ) ) ) );
	$GLOBALS['airtable']['languages'] = $languages_answer;

	update_option( WPCPM_Student_Airtable_Row::LANGUAGES_OPTION, array( 'read' => $T - 100, 'table' => 'tblLANGUAGES00001', 'names' => links()['languages'], 'stale' => false ), false );
	update_option(
		WPCPM_Institutions_Index::OPT_NAME,
		array(
			'v'    => WPCPM_Institutions_Index::VERSION,
			'read' => 1700000000,
			'rows' => array(
				'recINSTITUTION001' => array( 'record_id' => 'recINSTITUTION001', 'name' => 'Example University' ),
				'recINSTITUTION002' => array( 'record_id' => 'recINSTITUTION002', 'name' => 'Sample College' ),
			),
		)
	);

	return $client;
}

/** The answers as the form stores them: the privacy tick is the consent's, not an answer. */
function stored_answers( array $more = array() ) {
	$answers = answers( $more );

	unset( $answers['privacy'] );

	return $answers;
}

/** A waiting registration, queued this many seconds before the suite's now. */
function waiting_reg( array $more = array(), $age = 60 ) {
	global $T, $consent;

	$id = WPCPM_Student_Registration::store( stored_answers( $more ), 'waiting', array(), $consent );

	update_post_meta( $id, WPCPM_Student_Registration::META_QUEUED, $T - $age );

	return $id;
}

function reg_state( $id ) {
	return WPCPM_Student_Registration::state_of( WPCPM_Student_Registration::registration( $id ) );
}

function reg_outcome( $id ) {
	return WPCPM_Student_Registration::outcome_of( WPCPM_Student_Registration::registration( $id ) );
}

function reg_signals( $id ) {
	return WPCPM_Student_Registration::signals_of( WPCPM_Student_Registration::registration( $id ) );
}

/** How many requests of a method the transport has seen. */
function requests( $method ) {
	return count( array_filter( $GLOBALS['sent'], static function ( $request ) use ( $method ) {
		return $method === ( isset( $request['args']['method'] ) ? $request['args']['method'] : 'GET' );
	} ) );
}

/** The contexts and recipients of the mail sent. */
function mail_log() {
	return array_map( static function ( $mail ) {
		return $mail['context'] . ' to ' . $mail['to'];
	}, $GLOBALS['mail'] );
}

ck( 'the constants: the hourly hook, the run lock, its time, the batch, the mail key, the three email ids and the four signals',
	array(
		WPCPM_Student_Registration_Process::CRON_HOURLY,
		WPCPM_Student_Registration_Process::RUN_LOCK,
		WPCPM_Student_Registration_Process::RUN_TTL,
		WPCPM_Student_Registration_Process::BATCH,
		WPCPM_Student_Registration_Process::MAIL_KEY,
		WPCPM_Student_Registration_Process::MAIL_RECEIVED,
		WPCPM_Student_Registration_Process::MAIL_ACCOUNT,
		WPCPM_Student_Registration_Process::MAIL_ALREADY,
		WPCPM_Student_Registration_Process::SIGNAL_SITE_CEILING,
		WPCPM_Student_Registration_Process::SIGNAL_MAIL_CEILING,
		WPCPM_Student_Registration_Process::SIGNAL_MAIL_FAILED,
		WPCPM_Student_Registration_Process::SIGNAL_HELD,
	),
	array( 'wpcpm_student_registrations_hourly', 'wpcpm_sreg_lock_run', 900, 40, 'student-register-mail', 'student-registration-received', 'student-registration-account', 'student-registration-already', 'site-ceiling', 'mail-ceiling', 'mail-failed', 'held' ) );

echo "\n--- The hourly job on the clock ---\n";

process_world();
$GLOBALS['hooks'] = array();
WPCPM_Student_Registration_Process::init();

ck( 'init() hangs the run on the hourly hook and the schedule on init at 20',
	array( isset( $GLOBALS['hooks'][ WPCPM_Student_Registration_Process::CRON_HOURLY ] ), isset( $GLOBALS['hooks']['init'] ) ),
	array( true, true ) );

WPCPM_Student_Registration_Process::schedule();
$first_event = $GLOBALS['cron'][ WPCPM_Student_Registration_Process::CRON_HOURLY ];
WPCPM_Student_Registration_Process::schedule();

ck( 'schedule() puts one hourly event five minutes out, and again changes nothing',
	array( $first_event['every'], abs( $first_event['at'] - ( time() + 5 * MINUTE_IN_SECONDS ) ) < 5, $GLOBALS['cron'][ WPCPM_Student_Registration_Process::CRON_HOURLY ] === $first_event ),
	array( 'hourly', true, true ) );

WPCPM_Student_Registration_Process::unschedule();

ck( 'unschedule() takes it off the clock', isset( $GLOBALS['cron'][ WPCPM_Student_Registration_Process::CRON_HOURLY ] ), false );

echo "\n--- The account path ---\n";

foreach ( array( 'a student' => array( 'wpcpm_student' ), 'a mentor' => array( 'wpcpm_mentor' ), 'an Administrator' => array( 'administrator' ) ) as $who => $roles ) {
	$client = process_world();
	an_account( 77, 'Ada Account', 'Ada@Example.test', $roles );

	$id     = waiting_reg( array( 'email' => 'Ada@Example.test' ) );
	$result = WPCPM_Student_Registration_Process::process( $id, $client, $T );

	ck( 'an account with the address, ' . $who . ': account, its ID kept, no request, one email to the address as typed',
		array( $result, reg_state( $id ), reg_outcome( $id )['user'], $GLOBALS['sent'], mail_log() ),
		array( 'account', 'account', 77, array(), array( 'student-registration-account to Ada@Example.test' ) ) );
}

$body = $GLOBALS['mail'][0]['body'];

ck( 'and the account email carries the sign-in link and the password reset link, and the site\'s name',
	array( false !== strpos( $body, "\nhttps://example.test/wp-login.php\n" ), false !== strpos( $body, 'set a new one here: https://example.test/wp-login.php?action=lostpassword' ), false !== strpos( $body, 'an account on WordPress Credits, so' ) ),
	array( true, true, true ) );

echo "\n--- The existing registration ---\n";

$client = process_world();
$one    = waiting_reg();
$two    = waiting_reg( array( 'email' => 'ADA@example.test', 'name' => 'Ada Again' ) );

// Core makes a private post's slug from its title, so a stored registration's slug holds the name.
$GLOBALS['posts'][ $two ]->post_name = 'ada-again';

ck( 'the first registration is written', WPCPM_Student_Registration_Process::process( $one, $client, $T ), 'written' );

$before = count( $GLOBALS['sent'] );
$result = WPCPM_Student_Registration_Process::process( $two, $client, $T );

ck( 'a second one for the address is already: it points at the first, keeps no answers and no name, sends the already email and asks Airtable nothing',
	array(
		$result,
		reg_state( $two ),
		(int) get_post_meta( $two, WPCPM_Student_Registration::META_FIRST, true ),
		isset( $GLOBALS['pmeta'][ $two ][ WPCPM_Student_Registration::META_FIELDS ] ),
		WPCPM_Student_Registration::registration( $two )->post_title,
		count( $GLOBALS['sent'] ) - $before,
		mail_log(),
		reg_outcome( $two )['mail'],
	),
	array( 'already', 'already', $one, false, 'Registration with no name', 0, array( 'student-registration-received to ada@example.test', 'student-registration-already to ADA@example.test' ), 'sent' ) );
ck( 'and its slug no longer holds the name either: the title and an empty slug are handed over together, and core makes the slug again from the title',
	array( WPCPM_Student_Registration::registration( $two )->post_name, $GLOBALS['updated_posts'] ),
	array( 'registration-with-no-name', array( array( 'ID' => $two, 'post_title' => 'Registration with no name', 'post_name' => '' ) ) ) );

$client = process_world();
$one    = waiting_reg();
$two    = waiting_reg( array( 'name' => 'Ada Again' ) );

WPCPM_Student_Registration_Process::process( $one, $client, $T );

$GLOBALS['mail']         = array();
$GLOBALS['sent']         = array();
$GLOBALS['update_fails'] = true;
$result                  = WPCPM_Student_Registration_Process::process( $two, $client, $T );

ck( 'WordPress refusing to take the name off: not filed as already, a failed try that says why, the answers and the name kept, no email, no request',
	array( $result, reg_state( $two ), reg_outcome( $two )['tries'], reg_outcome( $two )['error'], WPCPM_Student_Registration::name_of( WPCPM_Student_Registration::registration( $two ) ), WPCPM_Student_Registration::registration( $two )->post_title, get_post_meta( $two, WPCPM_Student_Registration::META_FIRST, true ), $GLOBALS['mail'], $GLOBALS['sent'] ),
	array( 'waiting', 'waiting', 1, 'WordPress did not take the name off the registration, so it is not filed as registered already yet.', 'Ada Again', 'Ada Again', '', array(), array() ) );

$GLOBALS['update_fails'] = false;

ck( 'and the next try files it, with the already email',
	array( WPCPM_Student_Registration_Process::process( $two, $client, $T + 3600 ), WPCPM_Student_Registration::registration( $two )->post_title, (int) get_post_meta( $two, WPCPM_Student_Registration::META_FIRST, true ), mail_log() ),
	array( 'already', 'Registration with no name', $one, array( 'student-registration-already to ada@example.test' ) ) );

echo "\n--- The Students row that holds the address ---\n";

$client = process_world();
airtable_holds( array( 'recEXISTINGROW001' ) );

$id     = waiting_reg();
$GLOBALS['posts'][ $id ]->post_name = 'ada-example';
$result = WPCPM_Student_Registration_Process::process( $id, $client, $T );

ck( 'a row that has the address is already, with its record, the already email and no POST',
	array( $result, reg_state( $id ), reg_outcome( $id )['records'], reg_outcome( $id )['record'], mail_log(), requests( 'POST' ) ),
	array( 'already', 'already', array( 'recEXISTINGROW001' ), '', array( 'student-registration-already to ada@example.test' ), 0 ) );
ck( 'and it keeps the answers, the name, the title and the slug: only an entry under an earlier registration of the site\'s own is cut short',
	array( WPCPM_Student_Registration::fields_of( WPCPM_Student_Registration::registration( $id ) ), WPCPM_Student_Registration::registration( $id )->post_title, WPCPM_Student_Registration::registration( $id )->post_name, $GLOBALS['updated_posts'], get_post_meta( $id, WPCPM_Student_Registration::META_FIRST, true ) ),
	array( stored_answers(), 'Ada Example', 'ada-example', array(), '' ) );

echo "\n--- The new row ---\n";

$client = process_world();
$users  = $GLOBALS['users'];
$id     = waiting_reg();
$result = WPCPM_Student_Registration_Process::process( $id, $client, $T );
$posted = null;

foreach ( $GLOBALS['sent'] as $request ) {
	if ( isset( $request['args']['method'] ) && 'POST' === $request['args']['method'] ) {
		$posted = json_decode( (string) $request['args']['body'], true );
	}
}

$expected = WPCPM_Student_Airtable_Row::cells_for( answers(), students_table_spec(), links() );

ck( 'a new address is written: one POST of one record whose fields are the row\'s cells for the same answers',
	array( $result, reg_state( $id ), requests( 'POST' ), $posted, reg_outcome( $id )['record'] ),
	array( 'written', 'written', 1, array( 'records' => array( array( 'fields' => $expected['cells'] ) ) ), 'recSTUDENTROW0001' ) );
ck( 'with the Students row\'s cells for the answers and the privacy tick, and nothing left out', array( $posted['records'][0]['fields']['Privacy Policy Compliance'], reg_outcome( $id )['left_out'], reg_outcome( $id )['create_sent'] ), array( true, array(), 0 ) );
ck( 'and one email, the received one, to the address, naming the institution and the student',
	array( mail_log(), false !== strpos( $GLOBALS['mail'][0]['body'], 'for Example University' ), 0 === strpos( $GLOBALS['mail'][0]['body'], 'Hi Ada Example,' ), reg_outcome( $id )['mail'] ),
	array( array( 'student-registration-received to ada@example.test' ), true, true, 'sent' ) );
ck( 'and nothing else: no PATCH, no DELETE, no invitation, no email to anybody else, no account made',
	array( requests( 'PATCH' ), requests( 'DELETE' ), count( array_filter( array_column( $GLOBALS['mail'], 'context' ), static function ( $c ) { return 0 === strpos( $c, 'invite-' ); } ) ), $GLOBALS['users'] === $users ),
	array( 0, 0, 0, true ) );
ck( 'a written registration is not taken again', array( WPCPM_Student_Registration_Process::process( $id, $client, $T ), count( $GLOBALS['mail'] ), requests( 'POST' ) ), array( 'skipped', 1, 1 ) );
ck( 'nor is a post that is no registration', WPCPM_Student_Registration_Process::process( reg_plain_post(), $client, $T ), 'skipped' );

$left = process_world();
$id   = waiting_reg( array( 'languages' => array( 'Polish', 'Klingon' ) ) );

WPCPM_Student_Registration_Process::process( $id, $left, $T );

ck( 'a language the base lacks is left out and kept on the registration, the rest written', reg_outcome( $id )['left_out'], array( 'Languages' => array( 'why' => 'unmatched', 'values' => array( 'Klingon' ) ) ) );

echo "\n--- Airtable unreachable or refusing ---\n";

$client = process_world();
airtable_down( array( 'find' ) );
$id     = waiting_reg();
$result = WPCPM_Student_Registration_Process::process( $id, $client, $T );

ck( 'down at the search: waiting, one try, the error and the time kept, no email, no create',
	array( $result, reg_state( $id ), reg_outcome( $id )['tries'], reg_outcome( $id )['error'], reg_outcome( $id )['tried_at'], $GLOBALS['mail'], requests( 'POST' ), reg_outcome( $id )['create_sent'] ),
	array( 'waiting', 'waiting', 1, 'cURL error 28: Operation timed out', $T, array(), 0, 0 ) );

$result = WPCPM_Student_Registration_Process::process( $id, $client, $T + 3600 );

ck( 'and an hour on, still down: a second try', array( $result, reg_outcome( $id )['tries'] ), array( 'waiting', 2 ) );

$client = process_world();
airtable_down( array( 'create' ) );
$id     = waiting_reg();
$result = WPCPM_Student_Registration_Process::process( $id, $client, $T );

ck( 'down at the create: waiting, and the create stays marked as sent, since the row may have been made',
	array( $result, reg_state( $id ), reg_outcome( $id )['create_sent'], $GLOBALS['mail'] ),
	array( 'waiting', 'waiting', $T, array() ) );

$client = process_world();
airtable_refuses( 422 );
$id     = waiting_reg();
$result = WPCPM_Student_Registration_Process::process( $id, $client, $T );

ck( 'a 422 at the create: waiting, and the mark cleared, since Airtable said nothing was made',
	array( $result, reg_state( $id ), reg_outcome( $id )['create_sent'], false !== strpos( reg_outcome( $id )['error'], 'HTTP 422' ), $GLOBALS['mail'] ),
	array( 'waiting', 'waiting', 0, true, array() ) );

$client = process_world();
airtable_down( array( 'schema' ) );
$id     = waiting_reg();

ck( 'the schema unreachable is a failed try too, and nothing is created', array( WPCPM_Student_Registration_Process::process( $id, $client, $T ), requests( 'POST' ) ), array( 'waiting', 0 ) );

$client                            = process_world();
$GLOBALS['settings']['api_token'] = '';
$id                                = waiting_reg();

ck( 'with Airtable not connected: a failed try that says so, and no request',
	array( WPCPM_Student_Registration_Process::process( $id, $client, $T ), reg_outcome( $id )['error'], $GLOBALS['sent'] ),
	array( 'waiting', 'Airtable is not connected.', array() ) );

echo "\n--- The 72 hours ---\n";

$client = process_world();
airtable_down();
$young  = waiting_reg( array( 'email' => 'young@example.test' ), 71 * HOUR_IN_SECONDS );
$old    = waiting_reg( array( 'email' => 'old@example.test' ), 73 * HOUR_IN_SECONDS );

ck( 'queued 71 hours ago and down: still waiting; 73 hours ago: failed',
	array( WPCPM_Student_Registration_Process::process( $young, $client, $T ), reg_state( $young ), WPCPM_Student_Registration_Process::process( $old, $client, $T ), reg_state( $old ) ),
	array( 'waiting', 'waiting', 'failed', 'failed' ) );
ck( 'and a failed registration that fails again stays failed, with another try counted',
	array( WPCPM_Student_Registration_Process::process( $old, $client, $T + 100 ), reg_state( $old ), reg_outcome( $old )['tries'] ),
	array( 'failed', 'failed', 2 ) );

$client = process_world();
$stuck  = waiting_reg( array(), 80 * HOUR_IN_SECONDS );

WPCPM_Student_Registration::set_state( $stuck, 'failed', array( 'error' => 'gave up' ) );

ck( 'a failed registration found in time: written, with the received email',
	array( WPCPM_Student_Registration_Process::process( $stuck, $client, $T ), reg_state( $stuck ), mail_log() ),
	array( 'written', 'written', array( 'student-registration-received to ada@example.test' ) ) );

echo "\n--- Review Focus 1: a create whose answer was lost ---\n";

$client = process_world();
airtable_down( array( 'create' ) );
$id     = waiting_reg( array( 'languages' => array( 'Polish', 'Klingon' ) ) );

WPCPM_Student_Registration_Process::process( $id, $client, $T );

$klingon = array( 'Languages' => array( 'why' => 'unmatched', 'values' => array( 'Klingon' ) ) );

ck( 'a create whose answer was lost keeps, beside its mark, what the row it sent left out',
	array( reg_outcome( $id )['create_sent'], reg_outcome( $id )['left_out'] ),
	array( $T, $klingon ) );

airtable_holds( array( 'recSTUDENTROW0001' ) );

$result = WPCPM_Student_Registration_Process::process( $id, $client, $T + 3600 );

ck( 'the next try finds exactly one row: written with that row, the received email once, one POST in all',
	array( $result, reg_state( $id ), reg_outcome( $id )['record'], reg_outcome( $id )['create_sent'], mail_log(), requests( 'POST' ) ),
	array( 'written', 'written', 'recSTUDENTROW0001', 0, array( 'student-registration-received to ada@example.test' ), 1 ) );
ck( 'and the recovered registration says what the row it was built with left out',
	array( reg_outcome( $id )['left_out'], WPCPM_Student_Airtable_Row::left_out_sentence( reg_outcome( $id )['left_out'] ) ),
	array( $klingon, 'Languages: not in the base: Klingon.' ) );

$client = process_world();
airtable_refuses( 422 );
$id     = waiting_reg( array( 'languages' => array( 'Polish', 'Klingon' ) ) );

WPCPM_Student_Registration_Process::process( $id, $client, $T );

ck( 'a create Airtable refused with a status made no row, so nothing is said to be left out of one',
	array( reg_outcome( $id )['create_sent'], reg_outcome( $id )['left_out'] ),
	array( 0, array() ) );

$client = process_world();
airtable_down( array( 'create' ) );
$id     = waiting_reg();

WPCPM_Student_Registration_Process::process( $id, $client, $T );

airtable_holds( array( 'recSTUDENTROW0001', 'recSTUDENTROW0002' ) );

ck( 'but with two rows it is already, and nothing is written again',
	array( WPCPM_Student_Registration_Process::process( $id, $client, $T + 3600 ), reg_outcome( $id )['records'], requests( 'POST' ) ),
	array( 'already', array( 'recSTUDENTROW0001', 'recSTUDENTROW0002' ), 1 ) );

$client = process_world();
$id     = waiting_reg();

airtable_holds( array( 'recSTUDENTROW0001' ) );

ck( 'and a row with no mark that the site sent a create is somebody else\'s: already',
	array( WPCPM_Student_Registration_Process::process( $id, $client, $T ), requests( 'POST' ) ),
	array( 'already', 0 ) );

echo "\n--- Review Focus 2: one at a time under the address's lock ---\n";

$client = process_world();
$id     = waiting_reg();

$GLOBALS['opts'][ WPCPM_Student_Registration::lock_name( 'ada@example.test' ) ] = (string) ( time() + 300 );

ck( 'the address\'s lock held by another request: busy, still waiting, nothing asked, nothing sent',
	array( WPCPM_Student_Registration_Process::process( $id, $client, $T ), reg_state( $id ), $GLOBALS['sent'], $GLOBALS['mail'] ),
	array( 'busy', 'waiting', array(), array() ) );

unset( $GLOBALS['opts'][ WPCPM_Student_Registration::lock_name( 'ada@example.test' ) ] );

$older = $id;
$newer = waiting_reg( array( 'name' => 'Ada Twice' ), 30 );
$run   = WPCPM_Student_Registration_Process::run_hourly( $T, $client );

ck( 'with the lock gone, two waiting registrations for one address: the older is written and the newer filed already under it, one POST',
	array( reg_state( $older ), reg_state( $newer ), (int) get_post_meta( $newer, WPCPM_Student_Registration::META_FIRST, true ) === $older, requests( 'POST' ), mail_log() ),
	array( 'written', 'already', true, 1, array( 'student-registration-received to ada@example.test', 'student-registration-already to ada@example.test' ) ) );
ck( 'and the lock is let go after each', array_values( array_filter( array_keys( $GLOBALS['opts'] ), static function ( $name ) { return 0 === strpos( (string) $name, WPCPM_Student_Registration::LOCK_PREFIX ); } ) ), array() );
ck( 'and the run says what it did',
	array( $run['tried'], $run['written'], $run['settled'], $run['waiting'], $run['failed'], $run['busy'] ),
	array( 2, 1, 2, 0, 0, false ) );

echo "\n--- The registration is read again under the lock ---\n";

$client            = process_world();
$id                = waiting_reg();
$GLOBALS['events'] = array();

WPCPM_Student_Registration_Process::process( $id, $client, $T );

ck( 'the address\'s lock is taken, then this request\'s copy of the registration is dropped, before anything under the lock is read',
	array_slice( $GLOBALS['events'], 0, 2 ), array( 'lock insert', 'drop post_meta ' . $id ) );

unset( $GLOBALS['events'] );

// Another request settles the registration in the moment between this one's first read and its
// lock: a lock that request left behind is taken over, and the registration, read again under it,
// is written.
$client = process_world();
$raced  = waiting_reg();

$GLOBALS['opts'][ WPCPM_Student_Registration::lock_name( 'ada@example.test' ) ] = '1';
$GLOBALS['wpdb']->before_takeover = static function () use ( $raced ) {
	WPCPM_Student_Registration::set_state( $raced, WPCPM_Student_Registration::STATE_WRITTEN, array( 'record' => 'recSTUDENTROW0009' ) );
};

ck( 'a registration settled between the first read and the lock is skipped: no request, no email, the other request\'s row kept, the lock let go',
	array( WPCPM_Student_Registration_Process::process( $raced, $client, $T ), $GLOBALS['sent'], $GLOBALS['mail'], reg_outcome( $raced )['record'], array_key_exists( WPCPM_Student_Registration::lock_name( 'ada@example.test' ), $GLOBALS['opts'] ) ),
	array( 'skipped', array(), array(), 'recSTUDENTROW0009', false ) );

$GLOBALS['wpdb']->before_takeover = null;

echo "\n--- Review Focus 3: an address compared trimmed and in lower case ---\n";

$client = process_world();
an_account( 78, 'Ada Account', "ada.o'brien+wp@example.test", array( 'wpcpm_student' ) );

$id = waiting_reg( array( 'email' => "  Ada.O'Brien+wp@Example.test " ) );

ck( 'an account with the address in lower case answers a registration typed with capitals and spaces',
	array( WPCPM_Student_Registration_Process::process( $id, $client, $T ), reg_outcome( $id )['user'], $GLOBALS['sent'] ),
	array( 'account', 78, array() ) );

$client = process_world();
$one    = waiting_reg( array( 'email' => "ada.o'brien+wp@example.test" ) );
$two    = waiting_reg( array( 'email' => "ADA.O'BRIEN+WP@EXAMPLE.TEST " ) );

WPCPM_Student_Registration_Process::process( $one, $client, $T );

ck( 'a written registration answers the same address in other capitals too',
	array( WPCPM_Student_Registration_Process::process( $two, $client, $T ), (int) get_post_meta( $two, WPCPM_Student_Registration::META_FIRST, true ) === $one, requests( 'POST' ) ),
	array( 'already', true, 1 ) );

echo "\n--- Review Focus 4: at most forty a run, oldest first ---\n";

$client = process_world();
$ids    = array();

for ( $i = 1; $i <= 45; $i++ ) {
	$ids[] = waiting_reg( array( 'email' => 'student' . $i . '@example.test', 'name' => 'Student ' . $i ), ( 100 - $i ) );
}

$run  = WPCPM_Student_Registration_Process::run_hourly( $T, $client );
$left = array_map( static function ( $post ) { return $post->ID; }, WPCPM_Student_Registration::in_states( array( 'waiting' ), 0, 'ASC' ) );

ck( 'forty-five waiting at forty-five addresses: one run tries forty, and the five newest are left', array( $run['tried'], $run['written'], $left === array_slice( $ids, 40 ) ), array( 40, 40, true ) );

$run = WPCPM_Student_Registration_Process::run_hourly( $T + 3600, $client );

ck( 'the next run tries the five', array( $run['tried'], $run['written'], count( WPCPM_Student_Registration::in_states( array( 'waiting' ) ) ) ), array( 5, 5, 0 ) );

echo "\n--- A registration held for an Administrator ---\n";

$client = process_world();
$held   = waiting_reg( array( 'email' => 'held@example.test', 'name' => 'Held Student' ), 200 );

update_post_meta( $held, WPCPM_Student_Registration::META_SIGNALS, array( WPCPM_Student_Registration_Process::SIGNAL_HELD, WPCPM_Student_Registration_Process::SIGNAL_SITE_CEILING ) );

$ids = array();

for ( $i = 1; $i <= 41; $i++ ) {
	$ids[] = waiting_reg( array( 'email' => 'student' . $i . '@example.test', 'name' => 'Student ' . $i ), ( 100 - $i ) );
}

$run  = WPCPM_Student_Registration_Process::run_hourly( $T, $client );
$left = array_map( static function ( $post ) { return $post->ID; }, WPCPM_Student_Registration::in_states( array( 'waiting' ), 0, 'ASC' ) );

ck( 'a held registration, the oldest waiting, is passed over by the hourly run and not counted toward its forty: forty others are tried, and the held one and the newest are left',
	array( $run['tried'], $run['written'], $left === array( $held, $ids[40] ), reg_state( $held ), reg_signals( $held ), reg_outcome( $held )['tries'] ),
	array( 40, 40, true, 'waiting', array( 'held', 'site-ceiling' ), 0 ) );
ck( 'and no request named its address, and nobody was mailed there',
	array( count( array_filter( $GLOBALS['sent'], static function ( $request ) { return false !== strpos( urldecode( $request['url'] ), 'held@example.test' ); } ) ), in_array( 'student-registration-received to held@example.test', mail_log(), true ) ),
	array( 0, false ) );

$run = WPCPM_Student_Registration_Process::run_hourly( $T + 3600, $client );

ck( 'the next run tries the one left and passes over the held one again', array( $run['tried'], $run['written'], reg_state( $held ) ), array( 1, 1, 'waiting' ) );

echo "\n--- The hourly run again ---\n";

$client = process_world();
$id     = waiting_reg();

an_account( 79, 'Ada Later', 'ada@example.test', array( 'wpcpm_student' ) );

$run = WPCPM_Student_Registration_Process::run_hourly( $T, $client );

ck( 'an account made since the registration waited: account, no POST',
	array( reg_state( $id ), requests( 'POST' ), $run['settled'], $run['written'] ),
	array( 'account', 0, 1, 0 ) );

$client = process_world();
$id     = waiting_reg();

$GLOBALS['opts'][ WPCPM_Student_Registration_Process::RUN_LOCK ] = (string) ( time() + 900 );

$run = WPCPM_Student_Registration_Process::run_hourly( $T, $client );

ck( 'the run lock held: busy, nothing tried, nothing asked, the registration still waiting',
	array( $run['busy'], $run['tried'], $GLOBALS['sent'], reg_state( $id ) ),
	array( true, 0, array(), 'waiting' ) );

unset( $GLOBALS['opts'][ WPCPM_Student_Registration_Process::RUN_LOCK ] );

$client = process_world();
delete_option( WPCPM_Student_Airtable_Row::LANGUAGES_OPTION );

$reads = static function () {
	return count( array_filter( $GLOBALS['sent'], static function ( $request ) {
		return false !== strpos( $request['url'], '/tblLANGUAGES00001' );
	} ) );
};

WPCPM_Student_Registration_Process::run_hourly( $T, $client );
$after_first = $reads();
WPCPM_Student_Registration_Process::run_hourly( $T + 3600, $client );

ck( 'the languages are read when absent and not again within the day', array( $after_first, $reads(), array_keys( WPCPM_Student_Airtable_Row::languages() ) ), array( 1, 1, array( 'recLANGENGLISH001', 'recLANGPOLISH0001', 'recLANGSPANISH001' ) ) );

$client = process_world();
delete_option( WPCPM_Student_Airtable_Row::LANGUAGES_OPTION );
$GLOBALS['airtable']['languages'] = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
$id = waiting_reg();

$run = WPCPM_Student_Registration_Process::run_hourly( $T, $client );

ck( 'a failed read of the languages is recorded and the run goes on', array( $run['languages_error'], $run['tried'] ), array( 'cURL error 28: Operation timed out', 1 ) );

$client = process_world();
$aged   = waiting_reg( array( 'email' => 'aged@example.test' ) );

WPCPM_Student_Registration::set_state( $aged, 'written' );
update_post_meta( $aged, WPCPM_Student_Registration::META_SETTLED, $T - 400 * DAY_IN_SECONDS );

WPCPM_Student_Registration_Process::run_hourly( $T, $client );

ck( 'purge() runs at the end: a registration settled over a year ago is gone', null === get_post( $aged ), true );

echo "\n--- The mail ceiling and a failed hand-off ---\n";

$client = process_world();

for ( $i = 0; $i < WPCPM_Form_Guard::MAIL_PER_DAY; $i++ ) {
	WPCPM_Form_Guard::claim_mail( WPCPM_Student_Registration_Process::MAIL_KEY, WPCPM_Form_Guard::MAIL_PER_DAY );
}

$id     = waiting_reg();
$result = WPCPM_Student_Registration_Process::process( $id, $client, $T );

ck( 'the day\'s mail spent: written all the same, no email, the outcome says ceiling and the registration carries the signal',
	array( $result, reg_state( $id ), $GLOBALS['mail'], reg_outcome( $id )['mail'], reg_signals( $id ) ),
	array( 'written', 'written', array(), 'ceiling', array( 'mail-ceiling' ) ) );

$client                = process_world();
$GLOBALS['mail_fails'] = true;
$id                    = waiting_reg( array( 'email' => 'other@example.test' ) );
$result                = WPCPM_Student_Registration_Process::process( $id, $client, $T );

ck( 'a hand-off the mail layer refuses: written all the same, the outcome says failed and the registration carries the signal',
	array( $result, reg_state( $id ), reg_outcome( $id )['mail'], reg_signals( $id ) ),
	array( 'written', 'written', 'failed', array( 'mail-failed' ) ) );

$GLOBALS['mail_fails'] = false;

ck( 'and it is not sent again', array( WPCPM_Student_Registration_Process::process( $id, $client, $T + 3600 ), $GLOBALS['mail'] ), array( 'skipped', array() ) );

echo "\n--- The reply address ---\n";

$client = process_world();
$id     = waiting_reg();

WPCPM_Student_Registration_Process::process( $id, $client, $T );

ck( 'each email carries the program\'s Reply-To', $GLOBALS['mail'][0]['headers'], array( 'Reply-To: programs@example.test' ) );

$client = process_world();
$GLOBALS['settings']['mail_contact'] = '';
$id = waiting_reg();

WPCPM_Student_Registration_Process::process( $id, $client, $T );

ck( 'and with no contact address set, none', $GLOBALS['mail'][0]['headers'], array() );

echo "\n--- The three emails, word for word ---\n";

$all = WPCPM_Mail_Templates::all();

ck( 'the registry holds the three, after the Mentor applications\' and before the Student interests\' two (1.123.64) and the Quiet students\'', array_slice( array_keys( $all ), -8, 3 ), array( 'student-registration-received', 'student-registration-account', 'student-registration-already' ) );
ck( 'the subjects are the design\'s',
	array( $all['student-registration-received']['subject'], $all['student-registration-account']['subject'], $all['student-registration-already']['subject'] ),
	array( 'Registration received - WordPress Credits Program', 'You already have an account - WordPress Credits Program', 'You are already registered - WordPress Credits Program' ) );
ck( 'the received email\'s body is the design\'s', $all['student-registration-received']['body'], implode( "\n\n", array(
	'Hi {student_name},',
	'Thank you for registering for the WordPress Credits Program. We have received your registration for {institution_name}.',
	'A program manager will be in touch by email with the next steps, including your mentor and your course.',
	'If you have any questions in the meantime, reply to this email.',
) ) );
ck( 'the account email\'s body is the design\'s', $all['student-registration-account']['body'], implode( "\n\n", array(
	'Hi,',
	'Someone, probably you, just registered for the WordPress Credits Program with this address. You already have an account on {site_name}, so there is nothing more to fill in: sign in here.',
	'{login_link}',
	'If you do not remember your password, set a new one here: {lost_password_link}',
	'If you would like to join another course, reply to this email and a program manager will help.',
	'If this was not you, you can ignore this email.',
) ) );
ck( 'the already email\'s body is the design\'s', $all['student-registration-already']['body'], implode( "\n\n", array(
	'Hi,',
	'Someone, probably you, just registered for the WordPress Credits Program with this address. We already have your registration, so there is nothing more to fill in. A program manager will be in touch by email with the next steps.',
	'If you have any questions, reply to this email. If this was not you, you can ignore this email.',
) ) );
ck( 'each is in the Student registrations area, for an applicant',
	array_map( static function ( $id ) use ( $all ) { return $all[ $id ]['module'] . ' / ' . $all[ $id ]['audience']; }, array( 'student-registration-received', 'student-registration-account', 'student-registration-already' ) ),
	array( 'student-registrations / applicant', 'student-registrations / applicant', 'student-registrations / applicant' ) );
ck( 'the student\'s name goes in a body alone, and the sign-in and password links are required in the account email',
	array(
		$all['student-registration-received']['placeholders']['student_name']['body_only'],
		$all['student-registration-account']['placeholders']['login_link']['required'],
		$all['student-registration-account']['placeholders']['lost_password_link']['required'],
		array_keys( $all['student-registration-received']['placeholders'] ),
		array_keys( $all['student-registration-account']['placeholders'] ),
		array_keys( $all['student-registration-already']['placeholders'] ),
	),
	array( true, true, true, array( 'student_name', 'institution_name' ), array( 'site_name', 'login_link', 'lost_password_link' ), array() ) );
ck( 'and the triggers say when each is sent',
	array( $all['student-registration-received']['trigger'], $all['student-registration-account']['trigger'], $all['student-registration-already']['trigger'] ),
	array(
		'When a student registers through the form on the site and the registration is written to Airtable: to the address the registration gives.',
		'When somebody registers through the student registration form with the address of an account on this site: to that address.',
		'When somebody registers through the student registration form with an address already registered: to that address.',
	) );

echo "\n=== The form ===\n";

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-start-pages.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-registration-form.php';

/** The institutions the site holds, in the stages the form's setting names and out of them. */
function form_institutions() {
	return array(
		'recINSTITUTION001' => array( 'record_id' => 'recINSTITUTION001', 'name' => 'Example University ', 'stage' => 'Confirmed' ),
		'recINSTITUTION002' => array( 'record_id' => 'recINSTITUTION002', 'name' => 'Sample College', 'stage' => 'Confirmed' ),
		'recINSTITUTION003' => array( 'record_id' => 'recINSTITUTION003', 'name' => 'Agreement Institute', 'stage' => 'Agreement Sent' ),
		'recINSTITUTION004' => array( 'record_id' => 'recINSTITUTION004', 'name' => 'Pipeline Institute', 'stage' => 'Pipeline' ),
		'recINSTITUTION005' => array( 'record_id' => 'recINSTITUTION005', 'name' => 'TEST - Example institution', 'stage' => 'Confirmed' ),
		'recINSTITUTION006' => array( 'record_id' => 'recINSTITUTION006', 'name' => 'test college', 'stage' => 'Confirmed' ),
		'recINSTITUTION007' => array( 'record_id' => 'recINSTITUTION007', 'name' => 'Testing Academy', 'stage' => 'Confirmed' ),
	);
}

/** A site where the form is drawn: registrations on, Airtable answering, the page, the languages and the institutions. */
function form_world() {
	process_world();

	$GLOBALS['uid']        = 0;
	$GLOBALS['manage']     = array();
	$GLOBALS['slugs']      = array();
	$GLOBALS['enqueued']   = array();
	$GLOBALS['registered'] = array();
	$GLOBALS['nocache']    = 0;
	$GLOBALS['next_id']    = 500;
	$GLOBALS['finished']   = array();

	unset( $GLOBALS['on_page'], $GLOBALS['hooks']['shutdown'] );

	// A server that finishes the answer early, as PHP-FPM's `fastcgi_finish_request()` does: the
	// moment it would let the connection go is kept, with what had happened by then.
	ignore_user_abort( false );
	WPCPM_Student_Registration_Form::for_tests( 'form_finish_answer' );

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
	$GLOBALS['slugs'][42] = 'student-registration';
	$GLOBALS['opts'][ WPCPM_Student_Registration_Form::OPT_PAGE ] = 42;

	update_option( WPCPM_Institutions_Index::OPT_NAME, array( 'v' => WPCPM_Institutions_Index::VERSION, 'read' => 1700000000, 'rows' => form_institutions() ) );
}

/** The stand-in for `fastcgi_finish_request()`: what had happened when the answer went out. */
function form_finish_answer() {
	$regs = form_regs();

	$GLOBALS['finished'][] = array(
		'abort ignored' => 1 === (int) ignore_user_abort(),
		'requests'      => count( $GLOBALS['sent'] ),
		'mail'          => count( $GLOBALS['mail'] ),
		'states'        => array_map( 'WPCPM_Student_Registration::state_of', $regs ),
	);

	return true;
}

/**
 * The end of the request after the redirect: what the submit hung on `shutdown`, run once, as core runs
 * it when the request exits. Answers how many callbacks there were.
 *
 * @return int
 */
function form_answered() {
	$callbacks = isset( $GLOBALS['hooks']['shutdown'] ) ? $GLOBALS['hooks']['shutdown'] : array();

	unset( $GLOBALS['hooks']['shutdown'] );

	foreach ( $callbacks as $callback ) {
		call_user_func( $callback );
	}

	return count( $callbacks );
}

/** A dwell token of a given age, signed the way the guard signs one for this form. */
function form_token( $age = 30 ) {
	$issued = time() - (int) $age;
	$random = wp_generate_password( 12, false, false );

	return $issued . '.' . $random . '.' . substr( wp_hash( WPCPM_Student_Registration_Form::DWELL_SCOPE . '|' . $issued . '|' . $random . '|' . wp_create_nonce( WPCPM_Student_Registration_Form::ACTION_SUBMIT ) ), 0, 32 );
}

/** The answers a good registration carries, as the form posts them; null takes one out. */
function form_posted( array $more = array() ) {
	$values = array_merge(
		array(
			'name'        => 'Ada Example',
			'email'       => 'ada@example.test',
			'institution' => 'recINSTITUTION001',
			'study'       => 'Technology & Engineering',
			'tutor'       => 'Tutor Example',
			'tutor_email' => 'tutor@example.test',
			'start'       => '2026-12-01',
			'end'         => '2027-03-31',
			'hours'       => '150,55',
			'languages'   => array( 'Polish', 'English' ),
			'preferred'   => 'English',
			'access'      => 'Large text.',
			'english'     => '1',
			'consent'     => '1',
		),
		$more
	);

	return array_filter( $values, static function ( $value ) {
		return null !== $value;
	} );
}

/** Read a redirect the way the page behind it would. */
function form_landed( $url ) {
	$stash = array();

	if ( preg_match( '/[?&]' . WPCPM_Student_Registration_Form::QUERY_STASH . '=([^&]+)/', $url, $id ) ) {
		$stash = (array) get_transient( WPCPM_Student_Registration_Form::TRANSIENT_PREFIX . $id[1] );
	} elseif ( preg_match( '/[?&]' . WPCPM_Student_Registration_Form::QUERY_OUTCOME . '=([^&]+)/', $url, $said ) ) {
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
		'action'                                         => WPCPM_Student_Registration_Form::ACTION_SUBMIT,
		'_wpnonce'                                       => array_key_exists( 'nonce', $extra ) ? $extra['nonce'] : wp_create_nonce( WPCPM_Student_Registration_Form::ACTION_SUBMIT ),
		WPCPM_Student_Registration_Form::FIELD_ANSWERS   => wp_slash( $answers ),
		WPCPM_Student_Registration_Form::TOKEN_FIELD     => array_key_exists( 'token', $extra ) ? $extra['token'] : form_token(),
		WPCPM_Student_Registration_Form::HONEYPOT        => isset( $extra['honeypot'] ) ? $extra['honeypot'] : '',
	);

	if ( isset( $extra['ip'] ) ) {
		$_SERVER['REMOTE_ADDR'] = $extra['ip'];
	}

	$out = run( array( 'WPCPM_Student_Registration_Form', 'handle_submit' ) );

	return form_landed( str_replace( 'redirect: ', '', $out['stop'] ) );
}

/** The page as the request a redirect led to would draw it. */
function form_redraw( array $landed ) {
	$_GET = array();

	if ( preg_match( '/[?&]' . WPCPM_Student_Registration_Form::QUERY_STASH . '=([^&]+)/', $landed['url'], $id ) ) {
		$_GET[ WPCPM_Student_Registration_Form::QUERY_STASH ] = $id[1];
	} elseif ( preg_match( '/[?&]' . WPCPM_Student_Registration_Form::QUERY_OUTCOME . '=([^&]+)/', $landed['url'], $said ) ) {
		$_GET[ WPCPM_Student_Registration_Form::QUERY_OUTCOME ] = $said[1];
	}

	return WPCPM_Student_Registration_Form::render();
}

/** Every registration, oldest first. */
function form_regs() {
	return WPCPM_Student_Registration::in_states( WPCPM_Student_Registration::states(), 0, 'ASC' );
}

/** The one registration this check made, or null. */
function form_only() {
	$regs = form_regs();

	return 1 === count( $regs ) ? $regs[0] : null;
}

/** The state and the signals of the one registration, or null for none. */
function form_only_state() {
	$reg = form_only();

	return $reg ? array( WPCPM_Student_Registration::state_of( $reg ), WPCPM_Student_Registration::signals_of( $reg ) ) : null;
}

/** One method of a class's source, up to the end of its body. */
function form_method_body( $src, $name ) {
	$body = substr( $src, (int) strpos( $src, 'function ' . $name . '(' ) );

	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}

/** The sentence drawn under one question, as the page prints it, or null for none. */
function form_problem_drawn( $page, $key ) {
	return 1 === preg_match( '#id="wpcpm-student-registration-' . $key . '-problem">(.*?)</span>#', $page, $found ) ? $found[1] : null;
}

echo "\n--- The hooks, the constants and the page's pieces ---\n";

form_world();
$GLOBALS['hooks'] = array();
WPCPM_Student_Registration_Form::init();

ck( 'the form posts for visitors signed in and signed out alike, registers on init and keeps its page out of the cache',
	array( isset( $GLOBALS['hooks']['init'] ), isset( $GLOBALS['hooks']['admin_post_wpcpm_student_register'] ), isset( $GLOBALS['hooks']['admin_post_nopriv_wpcpm_student_register'] ), isset( $GLOBALS['hooks']['template_redirect'] ) ),
	array( true, true, true, true ) );

$GLOBALS['hooks'] = array();
WPCPM_Student_Registration_Form::register();

ck( 'registering adds the shortcode and the block, and the mentor form\'s stylesheet under its own handle',
	array( isset( $GLOBALS['hooks']['shortcode:wpcpm_student_registration'] ), isset( $GLOBALS['hooks']['block:student-registration'] ), $GLOBALS['registered'][ WPCPM_Mentor_Application_Form::STYLE ] ),
	array( true, true, 'https://example.test/wp-content/plugins/wpcredits-program-manager/assets/css/application.css' ) );

ck( 'the names it goes by',
	array(
		WPCPM_Student_Registration_Form::SHORTCODE, WPCPM_Student_Registration_Form::BLOCK, WPCPM_Student_Registration_Form::OPT_PAGE, WPCPM_Student_Registration_Form::SLUG,
		WPCPM_Student_Registration_Form::ACTION_SUBMIT, WPCPM_Student_Registration_Form::DWELL_SCOPE, WPCPM_Student_Registration_Form::ACTOR_PREFIX, WPCPM_Student_Registration_Form::SITE_KEY,
		WPCPM_Student_Registration_Form::TRANSIENT_PREFIX, WPCPM_Student_Registration_Form::TRANSIENT_MINUTES, WPCPM_Student_Registration_Form::PER_HOUR,
		WPCPM_Student_Registration_Form::QUERY_STASH, WPCPM_Student_Registration_Form::QUERY_OUTCOME, WPCPM_Student_Registration_Form::FIELD_ANSWERS,
		WPCPM_Student_Registration_Form::HONEYPOT, WPCPM_Student_Registration_Form::TOKEN_FIELD, WPCPM_Student_Registration_Form::CONTACT,
		WPCPM_Student_Registration_Form::MAX_TEXT,
	),
	array(
		'wpcpm_student_registration', 'wpcpm/student-registration', 'wpcpm_student_registration_page_id', 'student-registration',
		'wpcpm_student_register', 'wpcpm-student-registration-dwell', 'student-register', 'student-register-site',
		'wpcpm_sreg_', 30, 30,
		'wpcpm_sreg', 'wpcpm_sreg_said', 'wpcpm_student_registration',
		'wpcpm_confirm_campus', 'wpcpm_student_registration_token', 'education@wordpressfoundation.org',
		2000,
	) );

$block_json = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/student-registration/block.json' ), true );

ck( 'the block: its name, title, description, keywords and the two attributes the mentor block has',
	array( $block_json['name'], $block_json['title'], $block_json['description'], $block_json['keywords'], array_keys( $block_json['attributes'] ), $block_json['editorScript'] ),
	array( 'wpcpm/student-registration', 'Student Registration Form', 'The public form a student fills in to register for the WordPress Credits Program.', array( 'student', 'registration', 'register', 'wpcredits' ), array( 'title', 'thanks' ), 'file:./editor.js' ) );

$editor = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/student-registration/editor.js' );

ck( 'and its editor script registers it, with the thank-you help in its words and a summary for the preview',
	array( false !== strpos( $editor, "registerBlockType( 'wpcpm/student-registration'" ), false !== strpos( $editor, "'Shown to a student once the form is sent. Leave blank for the plugin\\'s own sentence.'" ), false !== strpos( $editor, "block: 'wpcpm/student-registration'" ), false !== strpos( $editor, 'mentor' ), file_exists( WPCPM_PLUGIN_DIR . 'blocks/student-registration/editor.asset.php' ) ),
	array( true, true, true, false, true ) );

echo "\n--- The institution import's three cleaners, now public ---\n";

ck( 'text() collapses white space and trims, is_formula() reads the first character, has_control() reads a tab, a line break and a bar',
	array( WPCPM_Institution_Import::text( '  Ada   Example ' ), WPCPM_Institution_Import::is_formula( '=SUM(1)' ), WPCPM_Institution_Import::is_formula( 'Ada' ), WPCPM_Institution_Import::has_control( "Ada\tExample" ), WPCPM_Institution_Import::has_control( 'Ada|Example' ), WPCPM_Institution_Import::has_control( 'Ada Example' ) ),
	array( 'Ada Example', true, false, true, true, false ) );

echo "\n--- The fourteen questions ---\n";

$fields = WPCPM_Student_Registration_Form::fields();

ck( 'the fourteen questions, in the Airtable form\'s order', array_keys( $fields ),
	array( 'name', 'email', 'institution', 'study', 'tutor', 'tutor_email', 'start', 'end', 'hours', 'languages', 'preferred', 'access', 'english', 'consent' ) );
ck( 'with its labels, its two en dashes written as hyphens', array_values( array_column( $fields, 'label' ) ),
	array( 'Full Name', 'Email', 'Educational Institution', 'Department / Area of Study', 'Tutor Name - The professor or main contact person at your educational institution', 'Your tutor\'s email address', 'Preferred Start Date', 'Estimated End Date', 'Total Expected Hours (Approximate)', 'Languages You Speak', 'Preferred Language', 'Do you have any accessibility needs we should be aware of?', 'Compliance declaration', 'Privacy Policy Compliance' ) );
ck( 'the help of five, in its words, and none under the dates', array_filter( array_map( function ( $spec ) { return isset( $spec['help'] ) ? $spec['help'] : ''; }, $fields ) ), array(
	'tutor'     => 'If your educational institution doesn\'t require you to indicate a tutor, you can skip this question.',
	'hours'     => 'Format: Decimal',
	'languages' => 'Select as many as you speak.',
	'preferred' => 'Select only one.',
	'access'    => 'Optional - Leave blank if none.',
) );
ck( 'every one required but the tutor, the tutor\'s address and the accessibility needs',
	array_keys( array_filter( $fields, function ( $spec ) { return empty( $spec['required'] ); } ) ), array( 'tutor', 'tutor_email', 'access' ) );
ck( 'the two boxes\' words, the policy\'s address its link', array( $fields['english']['box'], $fields['consent']['box'] ), array(
	'I understand that the course is conducted in English, requires written communication, and involves proactive, independent remote work.',
	'By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (<a href="https://wordpress.org/about/privacy/">https://wordpress.org/about/privacy/</a>).',
) );
ck( 'the institution\'s line under its field, the hours\' bounds', array( $fields['institution']['note'], $fields['hours']['min'], $fields['hours']['max'] ),
	array( 'Is your institution not on the list? Ask a faculty member or your administration office to contact us.', 1, 2000 ) );
ck( 'with the institution application published the line leads to it, on this site, and no line leads to a form on Airtable (1.123.63)',
	array(
		WPCPM_Student_Registration_Form::institution_note( 'https://example.test/institution-application/' ),
		defined( 'WPCPM_Student_Registration_Form::INTEREST_URL' ),
		substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-registration-form.php' ), 'airtable.com' ),
	),
	array( 'Is your institution not on the list? Ask a faculty member or your administration office to fill in the <a href="https://example.test/institution-application/">institution application</a>.', false, 0 ) );

// Since 1.123.64 the line leads to the student interest form first, while that form is open and on a
// published page; the addresses are passed in, and `fields()` passes the two the site has.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-interest.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-interest-form.php';

ck( 'with the student interest form\'s address the line leads to it, whether or not the institution application is published, its address escaped',
	array(
		WPCPM_Student_Registration_Form::institution_note( 'https://example.test/institution-application/', 'https://example.test/student-interest/' ),
		WPCPM_Student_Registration_Form::institution_note( '', 'https://example.test/student-interest/?a=1&b=2' ),
	),
	array(
		'Is your institution not on the list? Fill in the <a href="https://example.test/student-interest/">student interest form</a> and we will get in touch with your institution.',
		'Is your institution not on the list? Fill in the <a href="https://example.test/student-interest/?a=1&#038;b=2">student interest form</a> and we will get in touch with your institution.',
	) );
ck( 'with no address for it the line is what it was: the institution application\'s link, or the sentence to get in touch',
	array(
		WPCPM_Student_Registration_Form::institution_note( 'https://example.test/institution-application/', '' ),
		WPCPM_Student_Registration_Form::institution_note( '', '' ),
		WPCPM_Student_Registration_Form::institution_note( '' ),
	),
	array(
		'Is your institution not on the list? Ask a faculty member or your administration office to fill in the <a href="https://example.test/institution-application/">institution application</a>.',
		'Is your institution not on the list? Ask a faculty member or your administration office to contact us.',
		'Is your institution not on the list? Ask a faculty member or your administration office to contact us.',
	) );

$interest_page              = new WP_Post();
$interest_page->ID          = 77;
$interest_page->post_type   = 'page';
$interest_page->post_status = 'publish';

$GLOBALS['posts'][77] = $interest_page;
$GLOBALS['slugs'][77] = 'student-interest';
$GLOBALS['opts'][ WPCPM_Student_Interest_Form::OPT_PAGE ] = 77;

$hint_open = WPCPM_Student_Registration_Form::fields()['institution']['note'];

$GLOBALS['settings']['student_interest_open'] = false;
$hint_closed = WPCPM_Student_Registration_Form::fields()['institution']['note'];

unset( $GLOBALS['settings']['student_interest_open'] );
$interest_page->post_status = 'draft';
$hint_draft = WPCPM_Student_Registration_Form::fields()['institution']['note'];

unset( $GLOBALS['posts'][77], $GLOBALS['slugs'][77], $GLOBALS['opts'][ WPCPM_Student_Interest_Form::OPT_PAGE ] );

ck( 'the form passes the student interest form\'s address while that form is open and its page published, and none while it is closed or its page is a draft',
	array( $hint_open, $hint_closed, $hint_draft ),
	array(
		'Is your institution not on the list? Fill in the <a href="https://example.test/student-interest/">student interest form</a> and we will get in touch with your institution.',
		'Is your institution not on the list? Ask a faculty member or your administration office to contact us.',
		'Is your institution not on the list? Ask a faculty member or your administration office to contact us.',
	) );
ck( 'the Airtable form\'s date format line is not there', array_key_exists( 'help', $fields['start'] ) || array_key_exists( 'help', $fields['end'] ), false );

echo "\n--- The lists the site holds ---\n";

form_world();

ck( 'the institutions are those in a stage the setting names, trimmed, A to Z, less a test one (the word TEST in capitals, as the site reads it): "test college" and "Testing Academy" are offered',
	WPCPM_Student_Registration_Form::institutions(),
	array( 'recINSTITUTION001' => 'Example University', 'recINSTITUTION002' => 'Sample College', 'recINSTITUTION006' => 'test college', 'recINSTITUTION007' => 'Testing Academy' ) );

$GLOBALS['settings']['student_registration_stages'] = array( 'Confirmed', 'Agreement Sent' );

ck( 'a stage added to the setting adds its institutions, still A to Z',
	array_values( WPCPM_Student_Registration_Form::institutions() ),
	array( 'Agreement Institute', 'Example University', 'Sample College', 'test college', 'Testing Academy' ) );

$GLOBALS['settings']['student_registration_stages'] = array();

ck( 'and with no stage named none is offered', WPCPM_Student_Registration_Form::institutions(), array() );

form_world();

ck( 'the languages are the copy\'s names, A to Z', WPCPM_Student_Registration_Form::languages(), array( 'English', 'Polish', 'Spanish' ) );

echo "\n--- Each answer, cleaned ---\n";

form_world();

$problem = static function ( $key, $raw ) {
	$cleaned = WPCPM_Student_Registration_Form::clean( $key, $raw );

	return $cleaned['ok'] ? 'ok:' . ( is_array( $cleaned['value'] ) ? implode( ',', $cleaned['value'] ) : var_export( $cleaned['value'], true ) ) : $cleaned['problem'];
};

ck( 'a name: kept with its white space collapsed, a formula, a tab or a bar and a length outside 2 to 200 refused',
	array( $problem( 'name', ' Ada   Example ' ), $problem( 'name', '=HYPERLINK("x")' ), $problem( 'name', "Ada\tExample" ), $problem( 'name', 'Ada|Example' ), $problem( 'name', 'A' ), $problem( 'name', str_repeat( 'a', 201 ) ), $problem( 'name', str_repeat( 'a', 200 ) ) === 'ok:' . var_export( str_repeat( 'a', 200 ), true ), $problem( 'name', array( 'x' ) ) ),
	array( "ok:'Ada Example'", 'formula', 'control', 'control', 'name_length', 'name_length', true, 'not_scalar' ) );
ck( 'an empty one is left for requiredness', array( WPCPM_Student_Registration_Form::clean( 'name', '' ), WPCPM_Student_Registration_Form::clean( 'email', '' ), WPCPM_Student_Registration_Form::clean( 'institution', '' ) ),
	array_fill( 0, 3, array( 'ok' => true, 'value' => '', 'problem' => '' ) ) );
ck( 'an email: an address kept, anything else refused, the tutor\'s the same and optional',
	array( $problem( 'email', ' Ada@Example.test ' ), $problem( 'email', 'not-an-address' ), $problem( 'tutor_email', 'nope' ), $problem( 'tutor_email', '' ), $problem( 'tutor_email', 'tutor@example.test' ) ),
	array( "ok:'Ada@Example.test'", 'bad_email', 'bad_email', "ok:''", "ok:'tutor@example.test'" ) );
ck( 'the institution: a record ID the form offers is kept, one at another stage or off the list refused',
	array( $problem( 'institution', 'recINSTITUTION001' ), $problem( 'institution', 'recINSTITUTION003' ), $problem( 'institution', 'recINSTITUTION005' ), $problem( 'institution', 'nonsense' ), $problem( 'institution', 'Example University' ) ),
	array( "ok:'recINSTITUTION001'", 'bad_choice', 'bad_choice', 'bad_choice', 'bad_choice' ) );
ck( 'the field of study: one of the nine, spelled as they are, never as an entity',
	array( $problem( 'study', 'Technology & Engineering' ), $problem( 'study', 'Languages, Communication & Writing' ), $problem( 'study', 'Cooking' ), $problem( 'study', 'Technology &amp; Engineering' ), $problem( 'study', '' ) ),
	array( "ok:'Technology & Engineering'", "ok:'Languages, Communication & Writing'", 'bad_choice', 'bad_choice', "ok:''" ) );
ck( 'the tutor: optional, a formula or a control refused, 120 characters kept and 121 refused',
	array( $problem( 'tutor', '' ), $problem( 'tutor', '+Tutor' ), $problem( 'tutor', "Tu\ttor" ), $problem( 'tutor', str_repeat( 't', 121 ) ), $problem( 'tutor', str_repeat( 't', 120 ) ) === 'ok:' . var_export( str_repeat( 't', 120 ), true ) ),
	array( "ok:''", 'formula', 'control', 'too_long', true ) );
ck( 'the dates: a real day in the browser\'s shape, nothing else',
	array( $problem( 'start', '2026-12-01' ), $problem( 'start', '2026-02-30' ), $problem( 'start', '05/12/2026' ), $problem( 'end', 'tomorrow' ), $problem( 'end', '' ) ),
	array( "ok:'2026-12-01'", 'bad_date', 'bad_date', 'bad_date', "ok:''" ) );
ck( 'the hours: a decimal kept to one place as a dot, a comma read as the point, a bound or a word refused',
	array( $problem( 'hours', '150,55' ), $problem( 'hours', '150.55' ), $problem( 'hours', '2' ), $problem( 'hours', '2.50' ), $problem( 'hours', 'ten' ), $problem( 'hours', '0.5' ), $problem( 'hours', '2000.1' ), $problem( 'hours', '2000' ), $problem( 'hours', '1' ), $problem( 'hours', '' ) ),
	array( "ok:'150.6'", "ok:'150.6'", "ok:'2'", "ok:'2.5'", 'not_a_number', 'below_min', 'above_max', "ok:'2000'", "ok:'1'", "ok:''" ) );
ck( 'the languages: the ones the copy holds, each once, in its order; one it lacks dropped; none left is no refusal here',
	array( $problem( 'languages', array( 'Polish', 'Klingon', 'English', 'Polish' ) ), $problem( 'languages', array( 'Klingon' ) ), $problem( 'languages', 'Spanish' ), $problem( 'languages', array( array( 'English' ) ) ) ),
	array( 'ok:English,Polish', 'ok:', 'ok:Spanish', 'ok:' ) );
ck( 'the preferred language: one the copy holds, one it lacks refused',
	array( $problem( 'preferred', 'Polish' ), $problem( 'preferred', 'Klingon' ), $problem( 'preferred', '' ) ),
	array( "ok:'Polish'", 'bad_choice', "ok:''" ) );
ck( 'the accessibility needs: kept as typed, a tag or a percent code refused, cut to 2,000 characters',
	array( $problem( 'access', 'A ramp, please.' ), $problem( 'access', 'Needs <b>bold</b>' ), $problem( 'access', 'I get 10%cashback' ), strlen( WPCPM_Student_Registration_Form::clean( 'access', str_repeat( 'a', 2001 ) )['value'] ) ),
	array( "ok:'A ramp, please.'", 'markup', 'markup', 2000 ) );
ck( 'the two boxes: ticked only by a 1 or a true, never by a word, a zero or an array',
	array( $problem( 'english', '1' ), $problem( 'english', 'true' ), $problem( 'english', 'yes' ), $problem( 'english', '0' ), $problem( 'consent', '' ), $problem( 'consent', array( '1' ) ), $problem( 'consent', '1' ) ),
	array( 'ok:true', 'ok:true', 'ok:false', 'ok:false', 'ok:false', 'ok:false', 'ok:true' ) );
ck( 'a key nobody asked is refused', $problem( 'website', 'x' ), 'unknown_field' );

echo "\n--- The page ---\n";

$closed_said = '<div class="wpcpm-application"><p class="wpcpm-application__message is-info" role="status">Student registrations through this form are closed for now.</p></div>';
$closed      = array(
	'the switch off'            => array(
		static function () {
			$GLOBALS['settings']['student_registrations_enabled'] = false;
		},
		'The student registration form is switched off. Turn on "Registrations from students" on the Students and mentors tab of WPCredits Program > Settings when the program is ready to take them.',
	),
	'Airtable not connected'    => array(
		static function () {
			$GLOBALS['settings']['api_token'] = '';
		},
		'This form is not shown yet: Airtable is not connected, so no registration could be written. Connect it on the Connection tab of WPCredits Program > Settings.',
	),
	'no institution to offer'   => array(
		static function () {
			$GLOBALS['settings']['student_registration_stages'] = array( 'Pipeline Stage Nobody Holds' );
		},
		'This form is not shown yet: no institution the site holds is at a stage ticked under Institutions offered, on the Students and mentors tab of WPCredits Program > Settings. New partners arrive with the institutions sync, every three hours.',
	),
	'no languages read yet'     => array(
		static function () {
			delete_option( WPCPM_Student_Airtable_Row::LANGUAGES_OPTION );
		},
		'This form is not shown yet: the languages have not been read from Airtable yet. They are read within the hour.',
	),
);

foreach ( $closed as $case => $set ) {
	form_world();
	$set[0]();

	$public            = WPCPM_Student_Registration_Form::render();
	$GLOBALS['uid']    = 9;
	$GLOBALS['manage'] = array( 9 );
	$admin             = WPCPM_Student_Registration_Form::render();

	ck( sprintf( 'closed with %s: the public reads the closed sentence once and no form', $case ), array( $public, WPCPM_Student_Registration_Form::is_open() ), array( $closed_said, 'the switch off' === $case ? false : true ) );
	ck( sprintf( '   an Administrator also reads the reason, in one notice, and the form is not drawn for them either', $case ),
		array( substr_count( $admin, 'Student registrations through this form are closed for now.' ), false !== strpos( $admin, '<p class="wpcpm-application__notice">' . esc_html( $set[1] ) . '</p>' ), false !== strpos( $admin, '<form' ) ),
		array( 1, true, false ) );
	ck( sprintf( '   and a post is refused as closed, nothing stored', $case ), array( form_submit( form_posted() )['outcome'], count( form_regs() ) ), array( 'closed', 0 ) );
}

form_world();
$page = WPCPM_Student_Registration_Form::render();

preg_match_all( '#name="wpcpm_student_registration\[([a-z_]+)\]#', $page, $named );

ck( 'open, the form asks every question in order, under the site\'s keys', array_values( array_unique( $named[1] ) ), array_keys( WPCPM_Student_Registration_Form::fields() ) );
ck( 'it posts to its handler with a nonce, a token and the honeypot, its button and its busy words',
	array( false !== strpos( $page, 'name="action" value="wpcpm_student_register"' ), false !== strpos( $page, 'name="_wpnonce" value="nonce-wpcpm_student_register"' ), false !== strpos( $page, 'name="wpcpm_student_registration_token"' ), false !== strpos( $page, 'name="wpcpm_confirm_campus"' ), false !== strpos( $page, 'data-wpcpm-busy="Sending..." data-wpcpm-status="Sending your registration."' ), false !== strpos( $page, '>Send this registration</button>' ), false !== strpos( $page, 'data-wpcpm-once' ) ),
	array( true, true, true, true, true, true, true ) );
ck( 'the stylesheet is the mentor form\'s, and five legends group the questions, in the form\'s order (1.123.66)',
	array( in_array( WPCPM_Mentor_Application_Form::STYLE, $GLOBALS['enqueued'], true ), in_array( 'wpcpm-forms', $GLOBALS['enqueued'], true ), ( preg_match_all( '#<legend>([^<]+)</legend>#', $page, $legends ) ? $legends[1] : array() ), array_keys( WPCPM_Student_Registration_Form::groups() ) ),
	array( true, true, array( 'About you', 'Your institution', 'Dates and hours', 'Languages and access', 'Before you send this' ), array( 'about', 'institution', 'time', 'languages', 'last' ) ) );
$drawn_order = preg_match_all( '#(?:id|for)="wpcpm-student-registration-([a-z_]+)"#', $page, $drawn ) ? array_values( array_unique( $drawn[1] ) ) : array();
ck( 'every question names a group there is, and the questions are drawn in the order they had',
	array( array_diff( array_column( WPCPM_Student_Registration_Form::fields(), 'group' ), array_keys( WPCPM_Student_Registration_Form::groups() ) ), array_values( array_intersect( $drawn_order, array_keys( WPCPM_Student_Registration_Form::fields() ) ) ) ),
	array( array(), array_keys( WPCPM_Student_Registration_Form::fields() ) ) );
ck( 'the institution is the one field to type and pick: its label marked Required, the list from an empty first entry, the offered institutions A to Z, the field that takes typing beside it',
	array(
		false !== strpos( $page, '<label for="wpcpm-student-registration-institution" id="wpcpm-student-registration-institution-label">Educational Institution <span class="wpcpm-field__required">Required</span></label>' ),
		false !== strpos( $page, '<option value="" selected=\'selected\'>Choose your institution</option><option value="recINSTITUTION001">Example University</option><option value="recINSTITUTION002">Sample College</option><option value="recINSTITUTION006">test college</option><option value="recINSTITUTION007">Testing Academy</option></select>' ),
		false !== strpos( $page, 'role="combobox"' ),
		in_array( WPCPM_Dashboards::SWITCHER_SCRIPT, $GLOBALS['enqueued'], true ),
		array( false !== strpos( $page, 'Pipeline Institute' ), false !== strpos( $page, 'Agreement Institute' ), false !== strpos( $page, 'TEST - Example institution' ) ),
	),
	array( true, true, true, true, array( false, false, false ) ) );
ck( 'and the line for a student whose institution is not listed sits under it',
	array( strpos( $page, 'to contact us.</span>' ) > strpos( $page, 'wpcpm-student-registration-institution-list' ), false !== strpos( $page, '<span class="wpcpm-field__hint" id="wpcpm-student-registration-institution-note">Is your institution not on the list? Ask a faculty member or your administration office to contact us.</span>' ) ),
	array( true, true ) );
ck( 'each group\'s questions sit in a box that sets the short answers two to a row where there is room, the last closed before the button, and the two questions about the tutor each take a whole row (1.123.64, grouped in 1.123.66)',
	array(
		substr_count( $page, '<div class="wpcpm-application__columns">' ),
		false !== strpos( $page, '</div></fieldset><p class="wpcpm-application__send">' ),
		substr_count( $page, 'wpcpm-field--wide' ),
		false !== strpos( $page, '<p class="wpcpm-field wpcpm-field--text wpcpm-field--wide"><label for="wpcpm-student-registration-tutor">' ),
		false !== strpos( $page, '<p class="wpcpm-field wpcpm-field--email wpcpm-field--wide"><label for="wpcpm-student-registration-tutor_email">' ),
	),
	array( 5, true, 2, true, true ) );
ck( 'the preferred language is the other combobox, over the copy\'s names',
	array( false !== strpos( $page, '<label for="wpcpm-student-registration-preferred" id="wpcpm-student-registration-preferred-label">Preferred Language <span class="wpcpm-field__required">Required</span></label>' ), false !== strpos( $page, '<option value="" selected=\'selected\'>Choose a language</option><option value="English">English</option><option value="Polish">Polish</option><option value="Spanish">Spanish</option></select>' ), substr_count( $page, 'role="combobox"' ) ),
	array( true, true, 2 ) );
ck( 'the languages are boxes in a named group, with their help',
	array( substr_count( $page, 'name="wpcpm_student_registration[languages][]"' ), false !== strpos( $page, 'role="group" aria-labelledby="wpcpm-student-registration-languages-label"' ), false !== strpos( $page, '<span class="wpcpm-field__hint" id="wpcpm-student-registration-languages-help">Select as many as you speak.</span>' ) ),
	array( 3, true, true ) );
$study_options = array();

foreach ( WPCPM_Institution_Student_Form::choices( 'field_of_study' ) as $choice ) {
	$study_options[] = false !== strpos( $page, '<option value="' . esc_attr( $choice ) . '">' . esc_html( $choice ) . '</option>' );
}

ck( 'the field of study is a list of the nine, spelled as they are', array( count( $study_options ), array_unique( $study_options ) ), array( 9, array( true ) ) );
ck( 'the two dates are the browser\'s date field, and say nothing of a format',
	array( 1 === preg_match( '#<input type="date" id="wpcpm-student-registration-start" name="wpcpm_student_registration\[start\]" value="" required="required"#', $page ), 1 === preg_match( '#<input type="date" id="wpcpm-student-registration-end" name="wpcpm_student_registration\[end\]" value="" required="required"#', $page ), substr_count( $page, 'type="date"' ), false !== strpos( $page, 'dd/mm/yyyy' ) ),
	array( true, true, 2, false ) );
ck( 'the hours take a decimal to one place, in steps of a tenth, between 1 and 2,000, with their help',
	array( 1 === preg_match( '#<input type="number" id="wpcpm-student-registration-hours" name="wpcpm_student_registration\[hours\]" value="" min="1" max="2000" step="0\.1" inputmode="decimal" required="required"#', $page ), false !== strpos( $page, '<span class="wpcpm-field__hint" id="wpcpm-student-registration-hours-help">Format: Decimal</span>' ) ),
	array( true, true ) );
ck( 'the tutor and the accessibility needs are not marked Required, the free text a box of 2,000',
	array( false !== strpos( $page, 'Tutor Name - The professor or main contact person at your educational institution</label>' ), false !== strpos( $page, 'Your tutor&#039;s email address</label>' ), false !== strpos( $page, 'we should be aware of?</label>' ), false !== strpos( $page, '<textarea id="wpcpm-student-registration-access" name="wpcpm_student_registration[access]" rows="4" maxlength="2000"' ) ),
	array( true, true, true, true ) );
ck( 'the compliance declaration is its heading, marked Required, and the box with its words',
	array( false !== strpos( $page, '<span class="wpcpm-field__label" id="wpcpm-student-registration-english-label">Compliance declaration <span class="wpcpm-field__required">Required</span></span>' ), false !== strpos( $page, '<label for="wpcpm-student-registration-english">I understand that the course is conducted in English, requires written communication, and involves proactive, independent remote work.</label>' ) ),
	array( true, true ) );
ck( 'and the privacy policy is its heading and the Airtable form\'s sentence beside its box, the policy linked by its address',
	false !== strpos( $page, '<span class="wpcpm-field__label" id="wpcpm-student-registration-consent-label">Privacy Policy Compliance <span class="wpcpm-field__required">Required</span></span><span class="wpcpm-field__consent"><input type="checkbox" id="wpcpm-student-registration-consent" name="wpcpm_student_registration[consent]" value="1" required="required" /><label for="wpcpm-student-registration-consent">By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (<a href="https://wordpress.org/about/privacy/">https://wordpress.org/about/privacy/</a>).</label></span>' ),
	true );

echo "\n--- A test institution: left out for the public, offered to an Administrator (1.123.55) ---\n";

/**
 * The institutions of `form_institutions()` and two more, both Confirmed: "TEST: Example Campus", a
 * test one, and "TESTAMENT College", which is not (no break after the word). And a test institution
 * at a stage the setting does not name, which only an Administrator is offered.
 */
function form_test_world() {
	form_world();

	$index = form_institutions();

	$index['recINSTITUTION008'] = array( 'record_id' => 'recINSTITUTION008', 'name' => 'TEST: Example Campus', 'stage' => 'Confirmed' );
	$index['recINSTITUTION009'] = array( 'record_id' => 'recINSTITUTION009', 'name' => 'TESTAMENT College', 'stage' => 'Confirmed' );
	$index['recINSTITUTION010'] = array( 'record_id' => 'recINSTITUTION010', 'name' => 'TEST - Pipeline Campus', 'stage' => 'Pipeline' );

	update_option( WPCPM_Institutions_Index::OPT_NAME, array( 'v' => WPCPM_Institutions_Index::VERSION, 'read' => 1700000000, 'rows' => $index ) );
}

/** Sign in as an Administrator (the stand-in manager) or as nobody. */
function form_sign_in( $administrator ) {
	$GLOBALS['uid']    = $administrator ? 9 : 0;
	$GLOBALS['manage'] = $administrator ? array( 9 ) : array();
}

form_test_world();
form_sign_in( false );

ck( 'the public list offers "TESTAMENT College" and not "TEST: Example Campus", the old four unchanged, A to Z',
	WPCPM_Student_Registration_Form::institutions(),
	array( 'recINSTITUTION001' => 'Example University', 'recINSTITUTION002' => 'Sample College', 'recINSTITUTION006' => 'test college', 'recINSTITUTION009' => 'TESTAMENT College', 'recINSTITUTION007' => 'Testing Academy' ) );
$body   = static function ( $class, $name ) {
	$method = new ReflectionMethod( $class, $name );
	return implode( '', array_slice( file( $method->getFileName() ), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 ) );
};
$shared = $body( 'WPCPM_Institutions_Index', 'offered_to_forms' );
$source = $body( 'WPCPM_Student_Registration_Form', 'offered' );

// The rule moved to the index in 1.123.67, so the mentor application form offers the same
// institutions this form does (WordPress/WPCredits#302). It still has to ask the one helper, and
// this form still has to ask the index rather than keep a second copy of the rule.
ck( 'the one helper is asked, and never the name rule itself', array( false !== strpos( $shared, 'WPCPM_Test_Institutions::is_test(' ), false !== strpos( $shared, 'is_test_name(' ) ), array( true, false ) );
ck( 'and this form asks the index for the list rather than building its own', array( false !== strpos( $source, 'WPCPM_Institutions_Index::offered_to_forms(' ), false !== strpos( $source, 'WPCPM_Test_Institutions::' ) ), array( true, false ) );

$visitor = form_submit( form_posted( array( 'institution' => 'recINSTITUTION008' ) ) );

ck( 'a visitor who posts a test institution\'s record ID is refused under its question, and nothing is stored',
	array( $visitor['outcome'], $visitor['stash']['problems'] ?? null, count( form_regs() ) ),
	array( 'again', array( 'institution' => 'bad_choice' ), 0 ) );

$visitor = form_submit( form_posted( array( 'institution' => 'recINSTITUTION010' ) ) );

ck( 'so is one at a stage the setting does not name',
	array( $visitor['outcome'], $visitor['stash']['problems'] ?? null, count( form_regs() ) ),
	array( 'again', array( 'institution' => 'bad_choice' ), 0 ) );

form_sign_in( true );

ck( 'an Administrator\'s list adds every test institution the index holds, whatever its stage, named "(Test)", A to Z by name',
	WPCPM_Student_Registration_Form::institutions(),
	array(
		'recINSTITUTION001' => 'Example University',
		'recINSTITUTION002' => 'Sample College',
		'recINSTITUTION005' => 'TEST - Example institution (Test)',
		'recINSTITUTION010' => 'TEST - Pipeline Campus (Test)',
		'recINSTITUTION006' => 'test college',
		'recINSTITUTION008' => 'TEST: Example Campus (Test)',
		'recINSTITUTION009' => 'TESTAMENT College',
		'recINSTITUTION007' => 'Testing Academy',
	) );

$GLOBALS['settings']['student_registration_stages'] = array();

ck( 'whatever the setting says: with no stage named an Administrator is still offered the test institutions, a visitor none',
	array( array_keys( WPCPM_Student_Registration_Form::institutions() ), ( static function () { form_sign_in( false ); return WPCPM_Student_Registration_Form::institutions(); } )() ),
	array( array( 'recINSTITUTION005', 'recINSTITUTION010', 'recINSTITUTION008' ), array() ) );

form_test_world();
form_sign_in( true );

$page = WPCPM_Student_Registration_Form::render();

$admin_page = WPCPM_Student_Registration_Form::render();

form_sign_in( false );

$public_page = WPCPM_Student_Registration_Form::render();

ck( 'the form an Administrator is drawn offers the test institution marked, and the public\'s does not',
	array( false !== strpos( $admin_page, 'value="recINSTITUTION008"' ), false !== strpos( $admin_page, 'TEST: Example Campus (Test)' ), false !== strpos( $public_page, 'recINSTITUTION008' ) ),
	array( true, true, false ) );

form_sign_in( true );

form_test_world();
$GLOBALS['option_reads'] = array();
form_sign_in( false );
WPCPM_Student_Registration_Form::institutions();
$public_reads = isset( $GLOBALS['option_reads'][ WPCPM_Institutions_Index::OPT_NAME ] ) ? $GLOBALS['option_reads'][ WPCPM_Institutions_Index::OPT_NAME ] : 0;
$GLOBALS['option_reads'] = array();
form_sign_in( true );
WPCPM_Student_Registration_Form::institutions();
$admin_reads = isset( $GLOBALS['option_reads'][ WPCPM_Institutions_Index::OPT_NAME ] ) ? $GLOBALS['option_reads'][ WPCPM_Institutions_Index::OPT_NAME ] : 0;
unset( $GLOBALS['option_reads'] );

ck( 'the list reads the index once for the ten it holds, for the public and for an Administrator, not once per row', array( $public_reads, $admin_reads ), array( 1, 1 ) );

$admin = form_submit( form_posted( array( 'institution' => 'recINSTITUTION008' ) ) );

ck( 'an Administrator\'s post of the test institution is accepted, as any other, and kept',
	array( $admin['outcome'], count( form_regs() ) ),
	array( 'sent', 1 ) );
ck( 'and the name kept with it is the index\'s, without the "(Test)" of the list',
	array_map( static function ( $post ) { return WPCPM_Student_Registration::fields_of( $post )['institution_name'] ?? null; }, array_values( form_regs() ) ),
	array( 'TEST: Example Campus' ) );

echo "\n--- A redraw keeps every answer and marks each problem ---\n";

form_world();
$bounced = form_submit( form_posted( array( 'email' => 'not-an-address', 'end' => '2026-11-01', 'hours' => 'ten', 'preferred' => 'Spanish', 'tutor' => '+Tutor', 'access' => 'Needs <b>bold</b> help' ) ) );
$problems = $bounced['stash']['problems'] ?? array();
ksort( $problems );
$again = form_redraw( $bounced );

ck( 'a refused post comes back as again with each problem, nothing stored',
	array( $bounced['outcome'], $problems, count( form_regs() ) ),
	array( 'again', array( 'access' => 'markup', 'email' => 'bad_email', 'end' => 'end_before_start', 'hours' => 'not_a_number', 'preferred' => 'preferred_not_spoken', 'tutor' => 'formula' ), 0 ) );
ck( 'the page says what happened, in the summary of the six problems, and draws the form',
	array( false !== strpos( $again, '<div class="wpcpm-errors" role="alert" tabindex="-1"><h2 class="wpcpm-errors__title">Six answers need a change</h2>' ), false !== strpos( $again, '<form' ) ),
	array( true, true ) );
ck( 'every answer that was fine, and the ones refused for how they sit beside another, is back in its box',
	array(
		false !== strpos( $again, 'id="wpcpm-student-registration-name" name="wpcpm_student_registration[name]" value="Ada Example"' ),
		false !== strpos( $again, '<option value="recINSTITUTION001" selected=\'selected\'>Example University</option>' ),
		false !== strpos( $again, '<option value="Technology &amp; Engineering" selected=\'selected\'>' ),
		false !== strpos( $again, 'name="wpcpm_student_registration[tutor_email]" value="tutor@example.test"' ),
		false !== strpos( $again, 'name="wpcpm_student_registration[start]" value="2026-12-01"' ),
		false !== strpos( $again, 'name="wpcpm_student_registration[end]" value="2026-11-01"' ),
		1 === preg_match( '#value="English" checked="checked" /> <span>English</span>#', $again ) && 1 === preg_match( '#value="Polish" checked="checked" /> <span>Polish</span>#', $again ) && 1 === preg_match( '#value="Spanish" /> <span>Spanish</span>#', $again ),
		false !== strpos( $again, '<option value="Spanish" selected=\'selected\'>Spanish</option>' ),
		false !== strpos( $again, 'name="wpcpm_student_registration[english]" value="1" checked="checked"' ),
		// The consent tick is kept with the rest (1.123.58, trial T2).
		false !== strpos( $again, 'name="wpcpm_student_registration[consent]" value="1" checked="checked"' ),
	),
	array_fill( 0, 10, true ) );
// The tick is the sender's own work, kept with the other answers (1.123.58, trial T2): a box drawn
// empty again is refused by the browser itself on the next Send, before anything is said.
form_world();
$ticked = form_submit( form_posted( array( 'name' => '' ) ) );
$drawn  = form_redraw( $ticked );
ck( 'a ticked consent box comes back ticked after a refusal for another answer', array( $ticked['outcome'], array_keys( $ticked['stash']['problems'] ?? array() ), $ticked['stash']['values']['consent'] ?? null, 1 === preg_match( '#name="wpcpm_student_registration\[consent\]" value="1" checked="checked" required="required"#', $drawn ), count( form_regs() ) ), array( 'again', array( 'name' ), true, true, 0 ) );
ck( 'and what was typed in a free text and the tutor, which were refused, comes back as typed and escaped, a tag of it no tag on the page',
	array( false !== strpos( $again, 'name="wpcpm_student_registration[tutor]" value="+Tutor"' ), false !== strpos( $again, '>Needs &lt;b&gt;bold&lt;/b&gt; help</textarea>' ), false !== strpos( $again, '<b>bold' ) ),
	array( true, true, false ) );
ck( 'each problem is marked on its question: the field invalid, described by the sentence under it',
	array( 1 === preg_match( '#id="wpcpm-student-registration-email"[^>]*aria-invalid="true" aria-describedby="wpcpm-student-registration-email-problem"#', $again ), 1 === preg_match( '#id="wpcpm-student-registration-preferred"[^>]*aria-invalid="true"#', $again ), 1 === preg_match( '#id="wpcpm-student-registration-preferred-input"[^>]*aria-invalid="true"#', $again ), 1 === preg_match( '#id="wpcpm-student-registration-institution"[^>]*aria-invalid="true"#', $again ) ),
	array( true, true, true, false ) );

// The sentences, by problem. Four posts between them raise every problem the form words.
$words = array(
	'required'             => 'Fill this in.',
	'formula'              => 'This cannot start with =, +, - or @.',
	'control'              => 'This cannot hold a tab, a line break or a "|".',
	'name_length'          => 'Give your full name, from 2 to 200 characters.',
	'bad_email'            => 'That does not look like an email address.',
	'bad_choice'           => 'Choose one of the options offered.',
	'too_long'             => 'Keep this to 120 characters.',
	'bad_date'             => 'Give a date.',
	'end_before_start'     => 'The end date has to be on or after the start date.',
	'not_a_number'         => 'Give a number of hours.',
	'below_min'            => 'Give at least one hour.',
	'above_max'            => 'Give at most 2,000 hours.',
	'preferred_not_spoken' => 'Your preferred language has to be one of the languages you ticked above.',
	'unticked'             => 'The program can only take students who can say this, so please tick it.',
);
$posts = array(
	array( 'name' => '', 'email' => 'not-an-address', 'institution' => 'nonsense', 'study' => 'Cooking', 'tutor' => str_repeat( 't', 121 ), 'tutor_email' => 'nope', 'start' => '2026-02-30', 'end' => '05/12/2026', 'hours' => '0.5', 'languages' => null, 'preferred' => 'Klingon', 'english' => null ),
	array( 'name' => 'A', 'start' => '2026-12-01', 'end' => '2026-11-01', 'hours' => 'ten', 'preferred' => 'Spanish', 'tutor' => '=1+1' ),
	array( 'name' => "Ada\tExample", 'hours' => '2000.1', 'institution' => 'recINSTITUTION003' ),
);
$drawn = array();

foreach ( $posts as $post ) {
	form_world();

	$landed = form_submit( form_posted( $post ) );
	$page   = form_redraw( $landed );

	foreach ( (array) ( $landed['stash']['problems'] ?? array() ) as $key => $slug ) {
		$drawn[ $slug ][ $key ] = form_problem_drawn( $page, $key );
	}
}

$wrong = array();

foreach ( $words as $slug => $sentence ) {
	foreach ( (array) ( $drawn[ $slug ] ?? array() ) as $key => $said ) {
		if ( esc_html( $sentence ) !== $said ) {
			$wrong[ $slug . ' on ' . $key ] = $said;
		}
	}
}

ck( 'every problem the form raises is worded in the site\'s voice, each as the design words it', array( $wrong, array_keys( array_diff_key( $words, $drawn ) ) ), array( array(), array() ) );
ck( 'and the markup problem is the plugin\'s own sentence for text the cleaner would cut, naming the box',
	form_problem_drawn( $again, 'access' ), esc_html( WPCPM_Typed_Text::loss_message( 'Do you have any accessibility needs we should be aware of?', 'send' ) ) );

echo "\n--- The thank-you, and the page's other words ---\n";

$thanks_said = 'Thank you. We have received your registration and will email you at the address you gave.';
$thanks_html = '<div class="wpcpm-application"><div class="wpcpm-application__done"><h3>' . esc_html( $thanks_said ) . '</h3></div></div>';
$outcomes    = WPCPM_Student_Registration_Form::outcomes();

ck( 'the outcomes beside closed, in the words the design gives and the mentor form\'s for the three it shares',
	array( $outcomes['lost'][1], $outcomes['closed'][1], $outcomes['stale'][1], $outcomes['expired'][1], $outcomes['busy'][1] === WPCPM_Form_Stash::outcomes()['busy'][1], array_keys( $outcomes ) ),
	array(
		'Something went wrong at our end and the registration was not saved. Try once more.',
		'Student registrations through this form are closed for now.',
		'This form had been open for a long time, so it was not sent. Your answers are still here: please send it again.',
		'This form had been open for a while and could not be sent. Your answers are still here: please send it again.',
		true,
		// A refused send says no sentence since 1.123.58: the summary of its problems says it.
		array( 'busy', 'busy-kept', 'busy-unkept', 'stash-gone', 'stale', 'expired', 'lost', 'closed' ),
	) );

form_world();
$_GET = array( WPCPM_Student_Registration_Form::QUERY_OUTCOME => 'sent' );

ck( 'the thank-you is one sentence in one heading, and draws no form', WPCPM_Student_Registration_Form::render(), $thanks_html );

$owner = array();

foreach ( array( 'block' => array( 'WPCPM_Student_Registration_Form', 'render_block' ), 'shortcode' => array( 'WPCPM_Student_Registration_Form', 'render' ) ) as $how => $draw ) {
	$drawn_owner   = call_user_func( $draw, array( 'thanks' => 'Thanks, <b>student</b>: we read every one.' ) );
	$owner[ $how ] = array( false !== strpos( $drawn_owner, '<h3>Thanks, &lt;b&gt;student&lt;/b&gt;: we read every one.</h3>' ), false !== strpos( $drawn_owner, 'We have received your registration' ), false !== strpos( $drawn_owner, '<form' ) );
}

ck( 'the owner\'s thank-you, given to the block or the shortcode, takes the plugin\'s place, escaped', $owner, array_fill_keys( array( 'block', 'shortcode' ), array( true, false, false ) ) );
ck( 'a title given to the block heads the page', 1 === preg_match( '#^<div class="wpcpm-application"><h2 class="wpcpm-application__title">Join us</h2>#', WPCPM_Student_Registration_Form::render_block( array( 'title' => 'Join us' ) ) ), true );

$_GET = array( WPCPM_Student_Registration_Form::QUERY_OUTCOME => 'unheard-of' );

ck( 'an outcome nobody defined is the plain form', array( false !== strpos( WPCPM_Student_Registration_Form::render(), '<form' ), false !== strpos( WPCPM_Student_Registration_Form::render(), 'wpcpm-application__message' ) ), array( true, false ) );

$_GET = array( WPCPM_Student_Registration_Form::QUERY_OUTCOME => 'busy' );

$time_format = $GLOBALS['opts']['time_format'] ?? null;
unset( $GLOBALS['opts']['time_format'] );
ck( 'with no time format on the site, the hour\'s turn is given as H:i, so the sentence never ends "after ."', 1 === preg_match( '/ after \d\d:00\.$/', WPCPM_Form_Stash::outcomes()['busy'][1] ), true );
if ( null !== $time_format ) {
	$GLOBALS['opts']['time_format'] = $time_format;
}
ck( 'a busy answer says so and draws no form', array( false !== strpos( WPCPM_Student_Registration_Form::render(), 'Too many attempts were made from this connection in this hour' ), false !== strpos( WPCPM_Student_Registration_Form::render(), '<form' ) ), array( true, false ) );

$_GET = array();

echo "\n--- The submit path, in the mentor form's order ---\n";

$source      = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-registration-form.php' );
$submit_path = form_method_body( $source, 'handle_submit' );
$offsets     = array(
	'actor'     => strpos( $submit_path, 'WPCPM_Form_Guard::claim_actor(' ),
	'nonce'     => strpos( $submit_path, 'wp_verify_nonce(' ),
	'closed'    => strpos( $submit_path, 'self::closed_reason()' ),
	'honeypot'  => strpos( $submit_path, 'WPCPM_Form_Guard::honeypot_filled(' ),
	'dwell'     => strpos( $submit_path, 'WPCPM_Form_Guard::check_token(' ),
	'answers'   => strpos( $submit_path, '$cleaned  = self::clean_all(' ),
	'required'  => strpos( $submit_path, 'self::add_required(' ),
	'consent'   => strpos( $submit_path, "self::clean( 'consent'" ),
	'hold'      => strpos( $submit_path, 'WPCPM_Form_Guard::claim_site( self::SITE_HOLD_KEY, self::HOLD_PER_DAY )' ),
	'site'      => strpos( $submit_path, 'WPCPM_Form_Guard::claim_site( self::SITE_KEY, WPCPM_Form_Guard::PER_DAY )' ),
	'store'     => strpos( $submit_path, 'WPCPM_Student_Registration::store(' ),
	'answer'    => ( 1 === preg_match( "/add_action\\(\\s*'shutdown',/", $submit_path, $hung, PREG_OFFSET_CAPTURE ) ) ? $hung[0][1] : false,
);
$in_order    = $offsets;
asort( $in_order );
$after_path  = form_method_body( $source, 'process_after_answer' );

ck( 'the submit path reads in the mentor form\'s order, every one of its twelve steps there, the day\'s 200 claimed before its 40 and the process hung on shutdown last', array( array_keys( $in_order ), in_array( false, $offsets, true ) ), array( array_keys( $offsets ), false ) );
ck( 'and it never runs the process itself: the request answers first', false !== strpos( $submit_path, 'WPCPM_Student_Registration_Process::process(' ), false );
ck( 'what runs on shutdown lets the visitor go first and then processes, in that order', array( (int) strpos( $after_path, 'ignore_user_abort( true )' ) > 0, strpos( $after_path, 'ignore_user_abort( true )' ) < strpos( $after_path, 'call_user_func( $finish )' ), strpos( $after_path, 'call_user_func( $finish )' ) < strpos( $after_path, 'WPCPM_Student_Registration_Process::process(' ) ), array( true, true, true ) );
ck( 'and neither holds a lock of its own when it hands the registration to the process, which takes the address\'s', array( false !== strpos( $submit_path, 'WPCPM_Lock::' ), false !== strpos( $after_path, 'WPCPM_Lock::' ) ), array( false, false ) );
ck( 'the answers are stored as the walk of fields() gives them, the consent never one of them', false !== strpos( $source, "unset( \$values['consent'] );" ), true );

form_world();
$outcomes_first = array();

for ( $i = 0; $i < WPCPM_Student_Registration_Form::PER_HOUR; $i++ ) {
	$outcomes_first[] = form_submit( form_posted(), array( 'nonce' => 'nonce-stale' ) )['outcome'];
}

$busy = form_submit( form_posted( array( 'email' => 'more@example.test' ) ) );

ck( '1. the per-actor ceiling comes first: thirty posts from one address in an hour are all taken, even those whose nonce failed, and the thirty-first is refused and stores nothing',
	array( array_unique( $outcomes_first ), count( $outcomes_first ), $busy['outcome'], count( form_regs() ) ),
	array( array( 'expired' ), 30, 'busy-kept', 0 ) );

form_world();
$expired = form_submit( form_posted(), array( 'nonce' => 'nonce-stale' ) );

ck( '2. a nonce that fails stores nothing and hands back every answer, the tick it was sent with among them', array( $expired['outcome'], count( form_regs() ), $expired['stash']['values']['name'] ?? null, $expired['stash']['values']['hours'] ?? null, $expired['stash']['values']['consent'] ?? null ), array( 'expired', 0, 'Ada Example', '150.6', true ) );

form_world();
$GLOBALS['settings']['student_registrations_enabled'] = false;

ck( '3. with the switch off, a post is closed and stores nothing', array( form_submit( form_posted() )['outcome'], count( form_regs() ), count( $GLOBALS['sent'] ) ), array( 'closed', 0, 0 ) );

form_world();
$honey = form_submit( form_posted(), array( 'honeypot' => 'https://spam.example.test' ) );

ck( '4. the honeypot files the registration as spam, says sent, asks Airtable nothing and mails nobody',
	array( $honey['outcome'], form_only_state(), count( $GLOBALS['sent'] ), count( $GLOBALS['mail'] ) ),
	array( 'sent', array( 'spam', array( 'honeypot' ) ), 0, 0 ) );

form_world();
$honey_blank = form_submit( form_posted( array( 'name' => '', 'languages' => array() ) ), array( 'honeypot' => 'https://spam.example.test' ) );

ck( '   and before requiredness: a honeypot post with blank answers is stored as spam, not sent back to be finished', array( $honey_blank['outcome'], form_only_state() ), array( 'sent', array( 'spam', array( 'honeypot' ) ) ) );

form_world();
form_submit( form_posted( array( 'name' => '=HYPERLINK("x")', 'tutor' => '+Tutor' ) ), array( 'honeypot' => 'https://spam.example.test' ) );
$spam_all = form_only() ? json_encode( $GLOBALS['pmeta'][ form_only()->ID ] ) : '';

ck( '   and a spam registration stores no refused typing, only the answers that were cleaned', array( false !== strpos( $spam_all, 'HYPERLINK' ), false !== strpos( $spam_all, 'Tutor"' ), false !== strpos( $spam_all, 'ada@example.test' ) ), array( false, false, true ) );

form_world();
$stale = form_submit( form_posted(), array( 'token' => form_token( 13 * HOUR_IN_SECONDS ) ) );

ck( '5. a stale token bounces with the answers, storing nothing', array( $stale['outcome'], count( form_regs() ), $stale['stash']['values']['name'] ?? null ), array( 'stale', 0, 'Ada Example' ) );

$fast = form_submit( form_posted(), array( 'token' => form_token( 1 ) ) );

ck( '   a token seconds old is taken all the same, marked dwell-fast, and processed once the answer has gone',
	array( $fast['outcome'], form_only_state(), form_answered(), form_only_state(), mail_log() ),
	array( 'sent', array( 'waiting', array( 'dwell-fast' ) ), 1, array( 'written', array( 'dwell-fast' ) ), array( 'student-registration-received to ada@example.test' ) ) );

form_world();
$forged = form_submit( form_posted(), array( 'token' => '1.abc.forged' ) );

ck( '   a token this site never signed is spam: stored, says sent, no request, no email', array( $forged['outcome'], form_only_state(), count( $GLOBALS['sent'] ), count( $GLOBALS['mail'] ) ), array( 'sent', array( 'spam', array( 'dwell' ) ), 0, 0 ) );

form_world();

foreach ( array( null, '0', 'yes', '' ) as $tick ) {
	$refused = form_submit( form_posted( array( 'consent' => $tick ) ) );

	ck( sprintf( '8. the privacy tick %s refuses the whole submission, marked at the box, and stores nothing, not even as spam', var_export( $tick, true ) ), array( $refused['outcome'], $refused['stash']['problems'] ?? null, count( form_regs() ) ), array( 'again', array( 'consent' => 'consent' ), 0 ) );
}

form_world();
$again = form_submit( form_posted( array( 'name' => '', 'english' => null, 'languages' => array(), 'institution' => 'nonsense' ) ) );
$wrong = $again['stash']['problems'] ?? array();
ksort( $wrong );

ck( '7 and 8. a refused answer, a blank one and an unticked declaration come back marked, with the rest of the answers, and nothing is stored',
	array( $again['outcome'], $wrong, $again['stash']['values']['email'] ?? null, isset( $again['stash']['values']['institution_name'] ), count( form_regs() ) ),
	array( 'again', array( 'english' => 'unticked', 'institution' => 'bad_choice', 'languages' => 'required', 'name' => 'required' ), 'ada@example.test', false, 0 ) );

form_world();
$good = form_submit( form_posted() );
$reg  = form_only();

ck( '11. the page answers first: at the redirect the registration is stored waiting, no request has gone to Airtable, nobody is mailed, and one callback waits on shutdown',
	array( $good['outcome'], form_only_state(), $GLOBALS['sent'], $GLOBALS['mail'], count( isset( $GLOBALS['hooks']['shutdown'] ) ? $GLOBALS['hooks']['shutdown'] : array() ), $GLOBALS['finished'] ),
	array( 'sent', array( 'waiting', array() ), array(), array(), 1, array() ) );

$answered = form_answered();

ck( '   and on shutdown, with an early finish to hand, the visitor is let go first and the process runs after: the connection ends with the abort ignored, no request made and nothing settled',
	array( $answered, $GLOBALS['finished'] ),
	array( 1, array( array( 'abort ignored' => true, 'requests' => 0, 'mail' => 0, 'states' => array( 'waiting' ) ) ) ) );

ck( '12. a good one is stored with the answers the walk of fields() gives, the institution\'s name beside its ID, the hours as one dot decimal, the declaration true and the privacy tick no value',
	$reg ? WPCPM_Student_Registration::fields_of( $reg ) : null,
	array(
		'name'             => 'Ada Example',
		'email'            => 'ada@example.test',
		'institution'      => 'recINSTITUTION001',
		'institution_name' => 'Example University',
		'study'            => 'Technology & Engineering',
		'tutor'            => 'Tutor Example',
		'tutor_email'      => 'tutor@example.test',
		'start'            => '2026-12-01',
		'end'              => '2027-03-31',
		'hours'            => '150.6',
		'languages'        => array( 'English', 'Polish' ),
		'preferred'        => 'English',
		'access'           => 'Large text.',
		'english'          => true,
	) );
ck( '   then written through the process: one row created, the received email sent, and the page said sent with nothing stashed',
	array( $good['outcome'], $good['stash'], $reg ? WPCPM_Student_Registration::state_of( $reg ) : null, requests( 'POST' ), mail_log(), $reg ? reg_outcome( $reg->ID )['record'] : null ),
	array( 'sent', array( 'outcome' => 'sent' ), 'written', 1, array( 'student-registration-received to ada@example.test' ), 'recSTUDENTROW0001' ) );

$evidence = $reg ? WPCPM_Student_Registration::consent_of( $reg ) : array();

ck( '   with the consent evidence: the sentence agreed to, the policy\'s address, the time and the truncated address',
	array( $evidence['sentence'] ?? null, $evidence['url'] ?? null, abs( ( $evidence['at'] ?? 0 ) - time() ) < 5, $evidence['ip'] ?? null ),
	array( 'By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (https://wordpress.org/about/privacy/).', 'https://wordpress.org/about/privacy/', true, '203.0.113.0' ) );

echo "\n--- Review focus 4 and 5 ---\n";

form_world();

for ( $i = 0; $i < WPCPM_Form_Guard::PER_DAY; $i++ ) {
	WPCPM_Ceiling::claim( WPCPM_Student_Registration_Form::SITE_KEY, WPCPM_Form_Guard::PER_DAY, DAY_IN_SECONDS );
}

$degraded = form_submit( form_posted() );

ck( 'the site\'s day spent: the registration is stored waiting with site-ceiling, nothing waits on shutdown, no request goes to Airtable, no email, and the page says sent',
	array( $degraded['outcome'], form_only_state(), form_answered(), count( $GLOBALS['sent'] ), count( $GLOBALS['mail'] ) ),
	array( 'sent', array( 'waiting', array( 'site-ceiling' ) ), 0, 0, 0 ) );

echo "\n--- The page answers first, and the second daily limit ---\n";

ck( 'the second daily limit: 200 registrations a day, under its own key', array( WPCPM_Student_Registration_Form::HOLD_PER_DAY, WPCPM_Student_Registration_Form::SITE_HOLD_KEY ), array( 200, 'student-register-hold' ) );

/** Spend part of the day's two ceilings, as so many registrations before this one would have. */
$spend = static function ( $hold, $site ) {
	for ( $i = 0; $i < $hold; $i++ ) {
		WPCPM_Ceiling::claim( WPCPM_Student_Registration_Form::SITE_HOLD_KEY, WPCPM_Student_Registration_Form::HOLD_PER_DAY, DAY_IN_SECONDS );
	}

	for ( $i = 0; $i < $site; $i++ ) {
		WPCPM_Ceiling::claim( WPCPM_Student_Registration_Form::SITE_KEY, WPCPM_Form_Guard::PER_DAY, DAY_IN_SECONDS );
	}
};

$within = array();

foreach ( array( 40, 120, 199 ) as $before ) {
	form_world();
	$spend( $before, 40 );

	$landed = form_submit( form_posted() );

	$within[ $before + 1 ] = array( $landed['outcome'], form_only_state(), form_answered(), count( $GLOBALS['sent'] ) );
}

ck( 'the 41st to the 200th of a day still wait for the hourly job as today: waiting with site-ceiling and not held, nothing on shutdown, no request',
	$within,
	array_fill_keys( array( 41, 121, 200 ), array( 'sent', array( 'waiting', array( 'site-ceiling' ) ), 0, 0 ) ) );

form_world();
$spend( 200, 40 );

$held_landed = form_submit( form_posted() );

ck( 'the 201st of a day is still taken: stored waiting and held, nothing on shutdown, no request, no email, and the student sees the same thank-you',
	array( $held_landed['outcome'], $held_landed['stash'], form_only_state(), form_answered(), $GLOBALS['sent'], $GLOBALS['mail'], form_redraw( $held_landed ) === $thanks_html ),
	array( 'sent', array( 'outcome' => 'sent' ), array( 'waiting', array( 'held', 'site-ceiling' ) ), 0, array(), array(), true ) );

$run = WPCPM_Student_Registration_Process::run_hourly( $T );

ck( '   and the hourly job leaves it held for an Administrator', array( $run['tried'], form_only_state(), $GLOBALS['sent'] ), array( 0, array( 'waiting', array( 'held', 'site-ceiling' ) ), array() ) );

form_world();
$spend( 199, 40 );

form_submit( form_posted( array( 'email' => 'bot@example.test' ) ), array( 'honeypot' => 'https://spam.example.test' ) );
$after_spam = form_submit( form_posted() );
$regs       = form_regs();

ck( 'spam claims none of the 200: after 199 and a spam one, the next is the 200th, and is not held',
	array( $after_spam['outcome'], array_map( 'WPCPM_Student_Registration::state_of', $regs ), array_map( 'WPCPM_Student_Registration::signals_of', $regs ) ),
	array( 'sent', array( 'spam', 'waiting' ), array( array( 'honeypot', 'site-ceiling' ), array( 'site-ceiling' ) ) ) );

form_world();
WPCPM_Student_Registration_Form::for_tests( false );

$no_early = form_submit( form_posted() );

ck( 'on a server that cannot finish the answer early, the callback on shutdown does nothing: the registration stays waiting, with no request and no email',
	array( $no_early['outcome'], form_answered(), $GLOBALS['finished'], form_only_state(), $GLOBALS['sent'], $GLOBALS['mail'] ),
	array( 'sent', 1, array(), array( 'waiting', array() ), array(), array() ) );

$run = WPCPM_Student_Registration_Process::run_hourly( $T );

ck( '   and the next hourly run writes it, with the received email',
	array( $run['tried'], $run['written'], form_only_state(), requests( 'POST' ), mail_log() ),
	array( 1, 1, array( 'written', array() ), 1, array( 'student-registration-received to ada@example.test' ) ) );

form_world();
$page_before = WPCPM_Student_Registration_Form::render();
$index       = form_institutions();

$index['recINSTITUTION001']['stage'] = 'Pipeline';

update_option( WPCPM_Institutions_Index::OPT_NAME, array( 'v' => WPCPM_Institutions_Index::VERSION, 'read' => 1700000000, 'rows' => $index ) );

$moved  = form_submit( form_posted() );
$values = $moved['stash']['values'] ?? array();

ck( 'an institution moved off the stages ticked between the draw and the post: again, the institution refused, every other answer kept, nothing stored',
	array( false !== strpos( $page_before, 'value="recINSTITUTION001"' ), $moved['outcome'], $moved['stash']['problems'] ?? null, isset( $values['institution'] ), $values['name'] ?? null, $values['email'] ?? null, $values['study'] ?? null, $values['hours'] ?? null, count( form_regs() ) ),
	array( true, 'again', array( 'institution' => 'bad_choice' ), false, 'Ada Example', 'ada@example.test', 'Technology & Engineering', '150.6', 0 ) );

echo "\n--- The same page on every path ---\n";

$paths = array(
	'a new student, written' => array(
		static function () {},
		'written', 1,
	),
	'an account with the address' => array(
		static function () {
			an_account( 79, 'Ada Account', 'ada@example.test', array( 'wpcpm_student' ) );
		},
		'account', 0,
	),
	'an address already registered' => array(
		static function () {
			global $consent;

			$first = WPCPM_Student_Registration::store( stored_answers(), 'waiting', array(), $consent );
			WPCPM_Student_Registration::set_state( $first, 'written' );
		},
		'already', 0,
	),
	'Airtable down' => array(
		static function () {
			airtable_down();
		},
		'waiting', 0,
	),
	'spam' => array(
		static function () {
			$GLOBALS['honeypot_post'] = 'https://spam.example.test';
		},
		'spam', 0,
	),
	'the address\'s lock held' => array(
		static function () {
			$GLOBALS['opts'][ WPCPM_Student_Registration::lock_name( 'ada@example.test' ) ] = (string) ( time() + 300 );
		},
		'waiting', 0,
	),
);
$landings   = array();
$at_answer  = array();
$states     = array();

foreach ( $paths as $case => $path ) {
	form_world();
	unset( $GLOBALS['honeypot_post'] );
	$path[0]();

	$landed = form_submit( form_posted(), array( 'honeypot' => isset( $GLOBALS['honeypot_post'] ) ? $GLOBALS['honeypot_post'] : '' ) );
	$regs   = form_regs();
	$last   = $regs ? $regs[ count( $regs ) - 1 ] : null;

	$landings[ $case ]  = array( $landed['outcome'], $landed['stash'], false === strpos( $landed['url'], WPCPM_Student_Registration_Form::QUERY_STASH . '=' ), form_redraw( $landed ) === $thanks_html );
	$at_answer[ $case ] = array( count( $GLOBALS['sent'] ), count( $GLOBALS['mail'] ), $last ? WPCPM_Student_Registration::state_of( $last ) : null, form_answered() );
	$states[ $case ]    = array( $last ? WPCPM_Student_Registration::state_of( $last ) : null, requests( 'POST' ) );
}

ck( 'account, already, written, waiting, spam and a held address lock each land on the same bare answer, with no stash, and the page draws the same thank-you',
	$landings, array_fill_keys( array_keys( $paths ), array( 'sent', array( 'outcome' => 'sent' ), true, true ) ) );
ck( 'and on every path the redirect is issued before any request to Airtable and any email, the registration stored as waiting (spam as spam), with the process waiting on shutdown for all but spam',
	$at_answer,
	array(
		'a new student, written'        => array( 0, 0, 'waiting', 1 ),
		'an account with the address'   => array( 0, 0, 'waiting', 1 ),
		'an address already registered' => array( 0, 0, 'waiting', 1 ),
		'Airtable down'                 => array( 0, 0, 'waiting', 1 ),
		'spam'                          => array( 0, 0, 'spam', 0 ),
		'the address\'s lock held'      => array( 0, 0, 'waiting', 1 ),
	) );
ck( 'while what became of each once the answer had gone is what it should: the state of the registration, and the rows created',
	$states,
	array(
		'a new student, written'        => array( 'written', 1 ),
		'an account with the address'   => array( 'account', 0 ),
		'an address already registered' => array( 'already', 0 ),
		'Airtable down'                 => array( 'waiting', 0 ),
		'spam'                          => array( 'spam', 0 ),
		'the address\'s lock held'      => array( 'waiting', 0 ),
	) );

// A cached copy of any page the form is drawn on would hand one visitor another's token, answers or
// confirmation, so every draw sends the headers, the block's and the shortcode's alike (1.123.58).
form_world();
$GLOBALS['on_page'] = 99;
$GLOBALS['nocache'] = 0;
WPCPM_Student_Registration_Form::render();
ck( 'a draw of the form on a page that is not its configured one sends the no-cache headers', array( $GLOBALS['nocache'], defined( 'DONOTCACHEPAGE' ) ), array( 1, true ) );
unset( $GLOBALS['on_page'] );

echo "\n--- A refusal keeps every answer and says it once (1.123.58) ---\n";

/** The transients written under the form's own prefix. */
function form_kept_transients() {
	return array_values( preg_grep( '/^' . WPCPM_Student_Registration_Form::TRANSIENT_PREFIX . '/', array_keys( $GLOBALS['transients'] ) ) );
}

/** The ceiling at its limit for this connection, at 13:20 on the suite's clock, the time shown as H:i. */
function form_at_the_limit() {
	$GLOBALS['opts']['time_format'] = 'H:i';
	$GLOBALS['site_offset']         = 0;
	WPCPM_Ceiling::for_tests(
		function () {
			return gmmktime( 13, 20, 0, 11, 6, 2026 );
		}
	);
	for ( $i = 0; $i < WPCPM_Student_Registration_Form::PER_HOUR; $i++ ) {
		WPCPM_Form_Guard::claim_actor( WPCPM_Form_Guard::actor_key( WPCPM_Student_Registration_Form::ACTOR_PREFIX ), WPCPM_Student_Registration_Form::PER_HOUR );
	}
}

form_world();
$refused = form_submit( form_posted( array( 'name' => '', 'email' => '' ) ) );
$page    = form_redraw( $refused );
$summary = strpos( $page, '<div class="wpcpm-errors" role="alert" tabindex="-1"><h2 class="wpcpm-errors__title">Two answers need a change</h2>' );
$name_at = strpos( $page, '<a href="#wpcpm-student-registration-name">Full Name</a>' );
$mail_at = strpos( $page, '<a href="#wpcpm-student-registration-email">Email</a>' );
// The form asks its questions with no fieldset, so the summary stands before the first question.
$set_at  = strpos( $page, 'class="wpcpm-field ' );
ck(
	'a refused send opens the form with the summary: its heading counts the problems, and each links its field by the field\'s label',
	array( false !== $summary && $summary < $set_at, false !== $name_at && $summary < $name_at && $name_at < $mail_at, false !== $mail_at && $mail_at < $set_at ),
	array( true, true, true )
);
ck( 'and the old generic sentence is gone', strpos( $page, 'Please look at the answers marked below' ), false );

form_world();
$both = form_submit( form_posted( array( 'name' => '', 'consent' => null ) ) );
$page = form_redraw( $both );
ck(
	'consent is checked in the same pass as the other answers: one bounce marks the box and the missing answer together',
	array( array_keys( $both['stash']['problems'] ?? array() ), false !== strpos( $page, 'Tick this box to send the form.' ), 1 === preg_match( '#id="wpcpm-student-registration-consent" name="[^"]+" value="1" required="required" aria-invalid="true" aria-describedby="wpcpm-student-registration-consent-problem"#', $page ), count( form_regs() ) ),
	array( array( 'name', 'consent' ), true, true, 0 )
);

form_world();
form_at_the_limit();
$busy = form_submit( form_posted() );
$page = form_redraw( $busy );
ck(
	'a busy connection with a good nonce keeps the answers and draws the form, with the sentence that says when to try again',
	array( false !== strpos( $busy['url'], WPCPM_Student_Registration_Form::QUERY_STASH . '=' ), $busy['outcome'], $busy['stash']['values']['name'] ?? null, false !== strpos( $page, 'Too many attempts were made from this connection in this hour, so this one was not taken. Your answers are still here: send the form again after 14:00.' ), false !== strpos( $page, 'Send this registration</button>' ), false !== strpos( $page, 'value="Ada Example"' ), count( form_regs() ) ),
	array( true, 'busy-kept', 'Ada Example', true, true, true, 0 )
);

form_world();
form_at_the_limit();
$busy = form_submit( form_posted(), array( 'nonce' => 'nonce-stale' ) );
$page = form_redraw( $busy );
ck(
	'a busy post with no valid nonce stores no stash',
	array( form_kept_transients(), $busy['url'], false !== strpos( $page, 'Too many attempts were made from this connection in this hour, so this one was not taken. Send the form again after 14:00.' ), strpos( $page, '<form' ) ),
	array( array(), 'https://example.test/student-registration/?' . WPCPM_Student_Registration_Form::QUERY_OUTCOME . '=busy', true, false )
);

form_world();
form_at_the_limit();
$busy = form_submit( form_posted( array( 'access' => str_repeat( 'a', 60001 ) ) ) );
$page = form_redraw( $busy );
ck(
	'a busy post too long to keep keeps nothing and says so',
	array( form_kept_transients(), $busy['outcome'], false !== strpos( $page, 'Too many attempts were made from this connection in this hour, so this one was not taken, and the answers were too long to keep here. Send the form again after 14:00.' ), strpos( $page, '<form' ) ),
	array( array(), 'busy-unkept', true, false )
);
WPCPM_Ceiling::for_tests( null );
$GLOBALS['site_offset'] = 2 * HOUR_IN_SECONDS;

form_world();
$typo = form_submit( form_posted( array( 'email' => 'name@campus' ) ) );
$page = form_redraw( $typo );
ck(
	'a refused address comes back as typed',
	array( $typo['stash']['problems'] ?? null, false !== strpos( $page, 'id="wpcpm-student-registration-email" name="wpcpm_student_registration[email]" value="name@campus"' ) ),
	array( array( 'email' => 'bad_email' ), true )
);

form_world();
$token  = form_token();
$first  = form_submit( form_posted(), array( 'token' => $token ) );
$second = form_submit( form_posted(), array( 'token' => $token ) );
ck(
	'a second press with the same token goes to the first press\'s confirmation and stores nothing',
	array( count( form_regs() ), $first['outcome'], $second['outcome'], $second['url'] ),
	array( 1, 'sent', 'sent', 'https://example.test/student-registration/?' . WPCPM_Student_Registration_Form::QUERY_OUTCOME . '=sent' )
);

form_world();
$token = form_token();
$_POST = array( '_wpnonce' => wp_create_nonce( WPCPM_Student_Registration_Form::ACTION_SUBMIT ) );
WPCPM_Form_Guard::check_token( WPCPM_Student_Registration_Form::DWELL_SCOPE, $token );
$key   = WPCPM_Form_Guard::token_key( WPCPM_Student_Registration_Form::DWELL_SCOPE, $token );
$reads = 0;
$GLOBALS['filtered']['wpcpm_form_guard_pause'] = function () use ( &$reads, $key ) {
	return function ( $microseconds ) use ( &$reads, $key ) {
		if ( 3 === ++$reads ) {
			WPCPM_Form_Guard::landed( $key, WPCPM_Student_Registration::store( array( 'name' => 'Ada Example', 'email' => 'ada@example.test' ), WPCPM_Student_Registration::STATE_WAITING, array(), WPCPM_Form_Guard::consent_evidence( 'x' ) ) );
		}
	};
};
$second = form_submit( form_posted(), array( 'token' => $token ) );
unset( $GLOBALS['filtered'] );
ck(
	'a second press while the first is still in flight waits for its row',
	array( count( form_regs() ), $reads, $second['outcome'] ),
	array( 1, 3, 'sent' )
);

form_world();
$token  = form_token();
$first  = form_submit( form_posted( array( 'consent' => null ) ), array( 'token' => $token ) );
$second = form_submit( form_posted(), array( 'token' => $token ) );
ck(
	'a used token whose first press bounced bounces stale with the answers',
	array( $first['outcome'], $second['outcome'], $second['stash']['values']['name'] ?? null, count( form_regs() ) ),
	array( 'again', 'stale', 'Ada Example', 0 )
);

form_world();
$_GET = array( WPCPM_Student_Registration_Form::QUERY_STASH => 'answersthathaveexpired' );
$page = WPCPM_Student_Registration_Form::render();
ck(
	'a stash id whose answers have expired draws the form empty, with the sentence that says so, and marks no field',
	array( false !== strpos( $page, 'The answers kept for this page have expired, so the form is empty again. If you sent the form, nothing more is needed; if you did not, fill it in and send it again.' ), false !== strpos( $page, '<form' ), strpos( $page, 'aria-invalid' ), strpos( $page, 'wpcpm-errors' ) ),
	array( true, true, false, false )
);
$_GET = array();

echo "\n--- The page the plugin makes ---\n";

form_world();
unset( $GLOBALS['opts'][ WPCPM_Student_Registration_Form::OPT_PAGE ] );
$GLOBALS['posts'][42]->post_content = '<!-- wp:paragraph --><p>Register in Airtable.</p><!-- /wp:paragraph -->';
$adopted                            = WPCPM_Student_Registration_Form::ensure_page();
$adopted_content                    = get_post( 42 )->post_content;

unset( $GLOBALS['posts'][42], $GLOBALS['slugs'][42], $GLOBALS['opts'][ WPCPM_Student_Registration_Form::OPT_PAGE ] );

$made = WPCPM_Student_Registration_Form::ensure_page();

ck( 'the page at student-registration is adopted as it is when it is there, made when it is not',
	array( $adopted, $adopted_content, get_post( $made )->post_name, get_post( $made )->post_title, get_post( $made )->post_status, get_option( WPCPM_Student_Registration_Form::OPT_PAGE ), WPCPM_Student_Registration_Form::ensure_page() ),
	array( 42, '<!-- wp:paragraph --><p>Register in Airtable.</p><!-- /wp:paragraph -->', 'student-registration', 'Student registration', 'publish', $made, $made ) );
ck( 'and a page it makes is three paragraph blocks, the Airtable form\'s introduction with the program\'s address linked, then the form',
	get_post( $made )->post_content,
	"<!-- wp:paragraph {\"className\":\"wpcpm-application-intro\"} -->\n<p class=\"wpcpm-application-intro\">Welcome!</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph {\"className\":\"wpcpm-application-intro\"} -->\n<p class=\"wpcpm-application-intro\">Fill out this short form to express your interest in joining the program. It only takes a few minutes and helps us better understand your background and availability.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph {\"className\":\"wpcpm-application-intro\"} -->\n<p class=\"wpcpm-application-intro\">If you have any questions before submitting your interest, feel free to contact us at <a href=\"mailto:education@wordpressfoundation.org\">education@wordpressfoundation.org</a></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:wpcpm/student-registration /-->" );
$student_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/application.css' );
ck( 'the stylesheet lets the consent label and the introduction break a long word, and no longer holds the flat form\'s button rule, the form being grouped (1.123.66)',
	array(
		1 === preg_match( '#\.wpcpm-field__consent label \{[^}]*overflow-wrap: anywhere;#', $student_css ),
		1 === preg_match( '#\.wpcpm-application-intro \{[^}]*overflow-wrap: anywhere;#', $student_css ),
		false === strpos( $student_css, 'wpcpm-application__send--flat' ),
		false !== strpos( $student_css, '.wpcpm-application__send {' ),
	),
	array( true, true, true, true ) );
ck( 'it is never gated: no access level is written on it', isset( $GLOBALS['pmeta'][ $made ] ), false );
ck( 'the address it is found at is the permalink while it is published, and none while it is not',
	array( WPCPM_Student_Registration_Form::page_url(), ( static function () use ( $made ) { $GLOBALS['posts'][ $made ]->post_status = 'draft'; return WPCPM_Student_Registration_Form::page_url(); } )() ),
	array( 'https://example.test/student-registration/', '' ) );

$GLOBALS['posts'][ $made ]->post_status = 'publish';
$GLOBALS['nocache']                     = 0;
$GLOBALS['on_page']                     = 9999;
WPCPM_Student_Registration_Form::no_cache();
$elsewhere = $GLOBALS['nocache'];
$GLOBALS['on_page'] = $made;
WPCPM_Student_Registration_Form::no_cache();

ck( 'its page alone is kept out of every cache', array( $elsewhere, $GLOBALS['nocache'], defined( 'DONOTCACHEPAGE' ) ), array( 0, 1, true ) );

echo "\n--- The editor's draw ---\n";

form_world();
define( 'REST_REQUEST', true );
$preview = WPCPM_Student_Registration_Form::render();

ck( 'in the editor the block is a static summary of the questions, with no form, no nonce and no token',
	array( false !== strpos( $preview, 'wpcpm-application--preview' ), false !== strpos( $preview, '<form' ), false !== strpos( $preview, 'nonce' ), false !== strpos( $preview, 'Full Name' ), false !== strpos( $preview, 'Privacy Policy Compliance' ) ),
	array( true, false, false, true, true ) );

$GLOBALS['settings']['student_registrations_enabled'] = false;

ck( 'and with the form closed the editor reads why', WPCPM_Student_Registration_Form::render(), '<div class="wpcpm-application"><p class="wpcpm-application__notice">' . esc_html( $closed['the switch off'][1] ) . '</p></div>' );
ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );


echo "\n=== The tab ===\n";

require_once __DIR__ . '/stubs/caps.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-registrations-tab.php';

/** An account's profile screen, as core addresses it. */
function get_edit_user_link( $user_id = null ) {
	return 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $user_id;
}

$tab_consent = array(
	'sentence' => 'By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (https://example.test/privacy/).',
	'url'      => 'https://example.test/privacy/',
	'policy'   => 0,
	'modified' => '',
	'at'       => 1700000000,
	'ip'       => '203.0.113.0',
	'agent'    => 'Mozilla/5.0 (test)',
);

/** A site where an Administrator is signed in, Airtable answering, and nothing registered. */
function tab_world() {
	form_world();

	an_account( 9, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );

	$GLOBALS['uid']    = 9;
	$GLOBALS['manage'] = array( 9 );
	$GLOBALS['umeta']  = array();
	$GLOBALS['opts']['date_format'] = 'F j, Y';
	$GLOBALS['opts']['time_format'] = 'g:i a';
}

/** One registration in a state, with the outcome the process would have left. */
function tab_reg( $state, array $more = array(), array $outcome = array(), array $signals = array() ) {
	global $tab_consent;

	$id = WPCPM_Student_Registration::store( stored_answers( $more ), 'spam' === $state ? 'spam' : 'waiting', $signals, $tab_consent );

	if ( ! in_array( $state, array( 'waiting', 'spam' ), true ) ) {
		WPCPM_Student_Registration::set_state( $id, $state, $outcome );
	} elseif ( array() !== $outcome ) {
		update_post_meta( $id, WPCPM_Student_Registration::META_OUTCOME, array_merge( WPCPM_Student_Registration::outcome_of( WPCPM_Student_Registration::registration( $id ) ), $outcome ) );
	}

	return $id;
}

/** The tab as the signed-in request draws it. */
function tab_html( array $query = array() ) {
	$_GET     = $query;
	$_REQUEST = $query;

	return run( array( 'WPCPM_Student_Registrations_Tab', 'render' ) )['html'];
}

/** One press: the handler, the posted fields (a nonce by default), and how the request ended. */
function tab_press( $method, $action, $id, array $more = array(), $nonce = true ) {
	$_POST = array_merge(
		array(
			'action'                                     => $action,
			WPCPM_Student_Registrations_Tab::FIELD_REGISTRATION => (string) $id,
		),
		$nonce ? array( '_wpnonce' => wp_create_nonce( $action . '_' . $id ) ) : array(),
		$more
	);
	$_REQUEST = $_POST;
	$_GET     = array();

	return run( array( 'WPCPM_Student_Registrations_Tab', $method ) )['stop'];
}

/** The outcome the last press left for the Students screen, read from the store. */
function tab_flashed() {
	$flash = get_user_meta( 9, 'wpcpm_flash', true );

	return is_array( $flash ) && isset( $flash[ WPCPM_Student_Registrations_Tab::FLASH ] ) ? $flash[ WPCPM_Student_Registrations_Tab::FLASH ] : null;
}

$back = 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=registrations';

ck( 'the actions, the field, the view argument, the list\'s bound and the screen\'s flash channel',
	array( WPCPM_Student_Registrations_Tab::ACTION_WRITE, WPCPM_Student_Registrations_Tab::ACTION_RESTORE, WPCPM_Student_Registrations_Tab::ACTION_DELETE, WPCPM_Student_Registrations_Tab::FIELD_REGISTRATION, WPCPM_Student_Registrations_Tab::VIEW_ARG, WPCPM_Student_Registrations_Tab::LIST_MAX, WPCPM_Student_Registrations_Tab::FLASH ),
	array( 'wpcpm_sreg_write', 'wpcpm_sreg_restore', 'wpcpm_sreg_delete', 'wpcpm_sreg_id', 'sreg_view', 50, 'students_admin' ) );

$GLOBALS['hooks'] = array();
WPCPM_Student_Registrations_Tab::init();

ck( 'the three presses are hooked for a signed-in request alone, never for a visitor',
	array_keys( $GLOBALS['hooks'] ),
	array( 'admin_post_wpcpm_sreg_write', 'admin_post_wpcpm_sreg_restore', 'admin_post_wpcpm_sreg_delete' ) );

tab_world();

ck( 'the four views, with their words and the states each lists; spam is listed under Spam alone',
	array_map( static function ( $view ) { return array( $view['label'], $view['states'] ); }, WPCPM_Student_Registrations_Tab::views() ),
	array(
		'all'     => array( 'All', array( 'waiting', 'failed', 'written', 'account', 'already' ) ),
		'waiting' => array( 'Waiting to be written', array( 'waiting' ) ),
		'failed'  => array( 'Could not be written', array( 'failed' ) ),
		'spam'    => array( 'Spam', array( 'spam' ) ),
	) );
ck( 'each view has an address on the tab, All the bare one',
	array( WPCPM_Student_Registrations_Tab::view_url(), WPCPM_Student_Registrations_Tab::view_url( 'all' ), WPCPM_Student_Registrations_Tab::view_url( 'spam' ) ),
	array( 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=registrations', 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=registrations', 'https://example.test/wp-admin/admin.php?page=wpcpm-students&tab=registrations&sreg_view=spam' ) );
ck( 'the eight notices, in the Administrators\' words',
	WPCPM_Student_Registrations_Tab::messages(),
	array(
		'sreg-written' => array( 'success', 'The registration is written to Airtable, and the student is sent the received email.' ),
		'sreg-settled' => array( 'success', 'The registration is settled without a new row: the address has an account or is registered already, and the student is sent that email.' ),
		'sreg-waiting' => array( 'warning', 'Airtable could not take the registration; it waits, and is tried again every hour.' ),
		'sreg-failed'  => array( 'error', 'Airtable refused the registration again. The reason is on it.' ),
		'sreg-busy'    => array( 'warning', 'Another request is writing a registration for that address at this moment. Reload the page in a minute.' ),
		'sreg-deleted' => array( 'success', 'The registration is deleted for good.' ),
		'sreg-unknown' => array( 'error', 'There is no registration with that ID.' ),
		'sreg-state'   => array( 'error', 'That registration is not in a state this press applies to. Reload the page to see where it got to.' ),
	) );

echo "\n--- Who may see and press ---\n";

$spam_id    = tab_reg( 'spam' );
$failed_id  = tab_reg( 'failed', array(), array( 'error' => 'Airtable said no' ) );
$GLOBALS['manage'] = array();

ck( 'an account without the capability draws nothing', tab_html(), '' );

$before = array( reg_state( $spam_id ), reg_state( $failed_id ) );
$denied = array();

foreach ( array(
	array( 'handle_write', WPCPM_Student_Registrations_Tab::ACTION_WRITE, $failed_id ),
	array( 'handle_restore', WPCPM_Student_Registrations_Tab::ACTION_RESTORE, $spam_id ),
	array( 'handle_delete', WPCPM_Student_Registrations_Tab::ACTION_DELETE, $spam_id ),
) as $press ) {
	$denied[ $press[0] ] = array( tab_press( $press[0], $press[1], $press[2], array(), false ), tab_press( $press[0], $press[1], $press[2] ) );
}

ck( 'and each press is refused with a 403 before the nonce or the registration is read, whatever it carries',
	$denied,
	array_fill_keys( array( 'handle_write', 'handle_restore', 'handle_delete' ), array( 'wp_die: You do not have permission to manage the program.', 'wp_die: You do not have permission to manage the program.' ) ) );
ck( 'nothing changed, nothing was asked of Airtable', array( array( reg_state( $spam_id ), reg_state( $failed_id ) ), count( $GLOBALS['sent'] ), get_post( $spam_id ) !== null ), array( $before, 0, true ) );

$GLOBALS['manage'] = array( 9 );

ck( 'with the capability, a press with no nonce or another press\'s nonce is stopped by the nonce, and changes nothing',
	array(
		tab_press( 'handle_write', WPCPM_Student_Registrations_Tab::ACTION_WRITE, $failed_id, array(), false ),
		tab_press( 'handle_write', WPCPM_Student_Registrations_Tab::ACTION_WRITE, $failed_id, array( '_wpnonce' => wp_create_nonce( 'wpcpm_sreg_delete_' . $failed_id ) ) ),
		tab_press( 'handle_delete', WPCPM_Student_Registrations_Tab::ACTION_DELETE, $spam_id, array( '_wpnonce' => wp_create_nonce( 'wpcpm_sreg_delete_' . $failed_id ) ) ),
		reg_state( $failed_id ),
		get_post( $spam_id ) !== null,
		count( $GLOBALS['sent'] ),
	),
	array( 'nonce refused: wpcpm_sreg_write_' . $failed_id, 'nonce refused: wpcpm_sreg_write_' . $failed_id, 'nonce refused: wpcpm_sreg_delete_' . $spam_id, 'failed', true, 0 ) );

echo "\n--- The views and their counts ---\n";

tab_world();

$w = tab_reg( 'waiting' );
$h = tab_reg( 'waiting', array( 'email' => 'held@example.test' ), array(), array( 'held' ) );
$f = tab_reg( 'failed', array(), array( 'error' => 'Airtable said no' ) );
$r = tab_reg( 'written', array(), array( 'record' => 'recSTUDENTROW0001' ) );
$a = tab_reg( 'account', array(), array( 'user' => 79 ) );
tab_reg( 'spam' );
tab_reg( 'spam' );

$page = tab_html();

ck( 'the card is headed Student registrations with the count waiting (a held one among them) and could not be written, then the one sentence',
	array(
		false !== strpos( $page, '<h2>Student registrations <span class="wpcpm-count">3</span></h2>' ),
		false !== strpos( $page, '<p class="description">Students who registered through the form on the site, newest first. A registration is written to the Airtable Students table unless the address has an account or is registered already; one Airtable could not take waits and is tried again every hour, and after 72 hours shows as Could not be written.</p>' ),
	),
	array( true, true ) );
ck( 'the four views carry their counts: All holds everything but spam, and Waiting to be written the held one too',
	array(
		false !== strpos( $page, '<li class="all"><a href="https://example.test/wp-admin/admin.php?page=wpcpm-students&#038;tab=registrations" class="current" aria-current="page">All <span class="count">(5)</span></a>' ),
		false !== strpos( $page, 'Waiting to be written <span class="count">(2)</span>' ),
		false !== strpos( $page, 'Could not be written <span class="count">(1)</span>' ),
		false !== strpos( $page, 'Spam <span class="count">(2)</span>' ),
	),
	array( true, true, true, true ) );
ck( 'All draws five rows and no spam; Spam draws its two alone; Waiting to be written draws the held one with the other',
	array(
		substr_count( $page, 'class="wpcpm-queue-item"' ),
		substr_count( tab_html( array( 'sreg_view' => 'spam' ) ), 'class="wpcpm-queue-item"' ),
		substr_count( tab_html( array( 'sreg_view' => 'spam' ) ), '<strong>Spam</strong>' ),
		substr_count( $page, '<strong>Spam</strong>' ),
		substr_count( tab_html( array( 'sreg_view' => 'waiting' ) ), 'class="wpcpm-queue-item"' ),
		substr_count( tab_html( array( 'sreg_view' => 'nope' ) ), 'class="wpcpm-queue-item"' ),
		false !== strpos( tab_html( array( 'sreg_view' => 'waiting' ) ), 'id="wpcpm-sreg-' . $h . '"' ),
	),
	array( 5, 2, 2, 0, 2, 5, true ) );

tab_world();

ck( 'with nothing registered the tab says Nothing here.', false !== strpos( tab_html(), '<p>Nothing here.</p>' ), true );

echo "\n--- Newest first, and bounded ---\n";

tab_world();

$ids = array();

for ( $i = 1; $i <= 53; $i++ ) {
	$ids[ $i ] = tab_reg( 'written', array( 'email' => 'student' . $i . '@example.test' ), array( 'record' => 'recSTUDENTROW' . sprintf( '%04d', $i ) ) );
}

$GLOBALS['posts'][ $ids[1] ]->post_date     = gmdate( 'Y-m-d H:i:s', 1700000000 + 86400 + 7200 );
$GLOBALS['posts'][ $ids[1] ]->post_date_gmt = gmdate( 'Y-m-d H:i:s', 1700000000 + 86400 );

$page = tab_html();
preg_match_all( '/id="wpcpm-sreg-(\d+)"/', $page, $drawn );

ck( 'fifty are drawn of fifty-three, with the sentence that says so',
	array( count( $drawn[1] ), false !== strpos( $page, '<p class="description">Showing the newest 50 of 53.</p>' ) ),
	array( 50, true ) );
ck( 'the one dated a day later comes first, then the rest by ID, newest first',
	array( (int) $drawn[1][0], (int) $drawn[1][1], (int) $drawn[1][49] ),
	array( $ids[1], $ids[53], $ids[5] ) );

tab_world();
tab_reg( 'written' );

ck( 'under fifty there is no such sentence', false === strpos( tab_html(), 'Showing the newest' ), true );

echo "\n--- A test institution's registration: listed and marked, left out of every count (1.123.55) ---\n";

/** The tab's figures as a reader sees them: the heading's number, the four views' counts, the rows drawn and the "Showing" sentence. */
function tab_figures( array $query = array() ) {
	$page = tab_html( $query );

	preg_match( '#<h2>Student registrations <span class="wpcpm-count">(\d+)</span></h2>#', $page, $heading );
	preg_match_all( '#<span class="count">\((\d+)\)</span>#', $page, $counts );
	preg_match( '#Showing the newest (\d+) of (\d+)\.#', $page, $showing );

	return array( 'heading' => $heading[1] ?? null, 'views' => $counts[1], 'rows' => substr_count( $page, 'class="wpcpm-queue-item"' ), 'showing' => array_slice( $showing, 1 ), 'page' => $page );
}

tab_world();

tab_reg( 'waiting' );
tab_reg( 'waiting', array( 'email' => 'held@example.test' ), array(), array( 'held' ) );
tab_reg( 'failed', array(), array( 'error' => 'Airtable said no' ) );
tab_reg( 'written', array(), array( 'record' => 'recSTUDENTROW0001' ) );
tab_reg( 'spam' );

$without = tab_figures();

$test_waiting = tab_reg( 'waiting', array( 'email' => 'test-waiting@example.test', 'institution' => 'recINSTITUTION005', 'institution_name' => 'TEST - Example institution' ) );
$test_failed  = tab_reg( 'failed', array( 'email' => 'test-failed@example.test', 'institution' => 'recINSTITUTION005' ), array( 'error' => 'Airtable said no' ) );
$test_named   = tab_reg( 'written', array( 'email' => 'test-named@example.test', 'institution' => 'TEST: Typed by name' ), array( 'record' => 'recSTUDENTROW0009' ) );
$with         = tab_figures();

ck( 'with test registrations the heading and every view count are the numbers they were without them',
	array( $with['heading'], $with['views'] ),
	array( $without['heading'], $without['views'] ) );
ck( 'and the three rows are listed all the same, one of them waiting, one failed and one written, each with its institution read "(Test)"',
	array(
		$without['rows'] + 3 === $with['rows'],
		false !== strpos( $with['page'], 'id="wpcpm-sreg-' . $test_waiting . '"' ),
		1 === preg_match( '#id="wpcpm-sreg-' . $test_waiting . '">.*?<p class="wpcpm-queue-age">[^<]*TEST - Example institution \(Test\)</p>#s', $with['page'] ),
		1 === preg_match( '#id="wpcpm-sreg-' . $test_failed . '">.*?<p class="wpcpm-queue-age">[^<]*TEST - Example institution \(Test\)</p>#s', $with['page'] ),
		1 === preg_match( '#id="wpcpm-sreg-' . $test_named . '">.*?<p class="wpcpm-queue-age">[^<]*TEST: Typed by name \(Test\)</p>#s', $with['page'] ),
		1 === preg_match( '#id="wpcpm-sreg-' . $test_waiting . '">.*?<td>TEST - Example institution \(Test\)</td>#s', $with['page'] ),
		false === strpos( $without['page'], '(Test)' ),
	),
	array( true, true, true, true, true, true, true ) );
ck( 'a real institution\'s row reads as it did, with no mark',
	array( substr_count( $with['page'], '(Test)' ) ),
	array( 6 ) );

// The newest first and bounded: fifty rows are drawn, one of them a test registration, and the
// sentence counts the real ones, drawn and all.
tab_world();

for ( $i = 1; $i <= 52; $i++ ) {
	tab_reg( 'written', array( 'email' => 'student' . $i . '@example.test' ), array( 'record' => 'recSTUDENTROW' . sprintf( '%04d', $i ) ) );
}

$full = tab_figures();

tab_reg( 'written', array( 'email' => 'newest-test@example.test', 'institution' => 'recINSTITUTION005' ), array( 'record' => 'recSTUDENTROW0099' ) );
$over = tab_figures();

ck( 'fifty are drawn of fifty-two real ones; the newest test one is among the fifty and not in the count, so the sentence reads 49 of 52',
	array( $full['rows'], $full['showing'], $over['rows'], $over['showing'], $over['views'][0] ),
	array( 50, array( '50', '52' ), 50, array( '49', '52' ), '52' ) );

// A site with no test institution: the same figures, the numbers are plain.
tab_world();
update_option( WPCPM_Institutions_Index::OPT_NAME, array( 'v' => WPCPM_Institutions_Index::VERSION, 'read' => 1700000000, 'rows' => array( 'recINSTITUTION001' => array( 'record_id' => 'recINSTITUTION001', 'name' => 'Example University', 'stage' => 'Confirmed' ) ) ) );
tab_reg( 'waiting' );
tab_reg( 'written', array( 'email' => 'w@example.test' ), array( 'record' => 'recSTUDENTROW0001' ) );

$none = tab_figures();

ck( 'with no test institution the figures are plain, as in 1.123.54',
	array( $none['heading'], $none['views'], $none['rows'], false === strpos( $none['page'], '(Test)' ), WPCPM_Test_Institutions::test_ids() ),
	array( '1', array( '2', '1', '0', '0' ), 2, true, array() ) );

echo "\n--- What happened to each ---\n";

tab_world();
update_option( 'date_format', 'F j, Y' );
$GLOBALS['settings']['base_id']        = 'appTESTBASE000001';
$GLOBALS['settings']['students_table'] = 'tblSTUDENTS000001';

$written = tab_reg( 'written', array( 'name' => 'Ada <b>Example</b>' ), array(
	'record'   => 'recSTUDENTROW0001',
	'left_out' => array( 'Languages' => array( 'why' => 'unmatched', 'values' => array( 'Klingon' ) ) ),
) );
$account = tab_reg( 'account', array( 'email' => 'acct@example.test' ), array( 'user' => 79 ) );
$first   = tab_reg( 'written', array( 'email' => 'first@example.test', 'name' => 'First Student' ), array( 'record' => 'recSTUDENTROW0002' ) );
$later   = WPCPM_Student_Registration::store( array( 'email' => 'first@example.test' ), 'already', array(), $tab_consent, $first );
WPCPM_Student_Registration::set_state( $later, 'already', array( 'records' => array( 'recSTUDENTROW0002' ) ) );
$earlier = WPCPM_Student_Registration::store( array( 'email' => 'first@example.test' ), 'already', array(), $tab_consent, $first );
WPCPM_Student_Registration::set_state( $earlier, 'already', array( 'records' => array() ) );
$waiting = tab_reg( 'waiting', array( 'email' => 'wait@example.test' ), array( 'tries' => 2, 'tried_at' => 1700000000, 'error' => 'cURL error 28: Operation timed out' ) );
$past40  = tab_reg( 'waiting', array( 'email' => 'past40@example.test' ), array(), array( 'site-ceiling' ) );
$held    = tab_reg( 'waiting', array( 'email' => 'held@example.test' ), array(), array( 'held', 'site-ceiling' ) );
$byrow   = tab_reg( 'already', array( 'email' => 'byrow@example.test', 'name' => 'Row Student' ), array( 'records' => array( 'recEXISTINGROW001' ) ) );
$failed  = tab_reg( 'failed', array( 'email' => 'fail@example.test' ), array( 'error' => 'Airtable said: Field is invalid <script>' ) );
$noreach = tab_reg( 'written', array( 'email' => 'cap@example.test' ), array( 'record' => 'recSTUDENTROW0003' ), array( 'mail-ceiling' ) );
$nomail  = tab_reg( 'written', array( 'email' => 'nomail@example.test' ), array( 'record' => 'recSTUDENTROW0004' ), array( 'mail-failed' ) );

$page = tab_html();
$rowof = static function ( $id ) use ( $page ) {
	preg_match( '#<li class="wpcpm-queue-item" id="wpcpm-sreg-' . $id . '">.*?</li>#s', $page, $m );

	return isset( $m[0] ) ? $m[0] : '';
};

ck( 'a row carries its anchor, the name and address escaped, the date and the institution\'s name',
	array(
		false !== strpos( $rowof( $written ), '<span class="wpcpm-inst-name__text">Ada &lt;b&gt;Example&lt;/b&gt;</span> <span class="wpcpm-inst-muted">ada@example.test</span>' ),
		false !== strpos( $rowof( $written ), '<p class="wpcpm-queue-age">November 15, 2023 ' ),
	),
	array( true, true ) );
ck( 'Written to Airtable links the Students row in Airtable',
	false !== strpos( $rowof( $written ), '<strong>Written to Airtable</strong>: <a href="https://airtable.com/appTESTBASE000001/tblSTUDENTS000001/recSTUDENTROW0001" rel="noopener">the row</a>' ), true );
ck( 'and says what the row left out',
	false !== strpos( $rowof( $written ), '<p class="wpcpm-sreg-leftout">Languages: not in the base: Klingon.</p>' ), true );
ck( 'Already has an account links the account\'s edit screen',
	false !== strpos( $rowof( $account ), '<strong>Already has an account</strong>: <a href="https://example.test/wp-admin/user-edit.php?user_id=79">the account</a>' ), true );
ck( 'Already registered links the row Airtable found',
	false !== strpos( $rowof( $later ), '<strong>Already registered</strong>: <a href="https://airtable.com/appTESTBASE000001/tblSTUDENTS000001/recSTUDENTROW0002" rel="noopener">the row</a>' ), true );
ck( 'or, where Airtable named none, an earlier registration on this page, which the entry is also named by',
	array(
		false !== strpos( $rowof( $earlier ), '<strong>Already registered</strong>: <a href="#wpcpm-sreg-' . $first . '">an earlier registration</a>' ),
		false !== strpos( $rowof( $earlier ), '<span class="wpcpm-inst-name__text">First Student</span> <span class="wpcpm-inst-muted">first@example.test</span>' ),
		false !== strpos( $rowof( $earlier ), 'No answers are kept' ),
	),
	array( true, true, true ) );
ck( 'Waiting to be written says why, the tries, the last try and its error',
	false !== strpos( $rowof( $waiting ), '<strong>Waiting to be written</strong>: Airtable could not take it yet; it is tried again every hour. Tried 2 times, last on November 15, 2023 12:13 am. cURL error 28: Operation timed out' ), true );
ck( 'one past the day\'s 40 says so, and offers no press: the hourly job writes it',
	array( false !== strpos( $rowof( $past40 ), '<strong>Waiting to be written</strong>: Past the day&#039;s limit of 40: the hourly job writes it.</p>' ), false !== strpos( $rowof( $past40 ), 'Airtable could not' ), false !== strpos( $rowof( $past40 ), 'wpcpm_sreg_write' ) ),
	array( true, false, false ) );
ck( 'one held past the day\'s 200 says it is held for an Administrator, and offers Write it now with its own nonce',
	array(
		false !== strpos( $rowof( $held ), '<strong>Waiting to be written</strong>: Held for an Administrator: more than 200 registrations arrived that day.</p>' ),
		false !== strpos( $rowof( $held ), 'Past the day' ),
		false !== strpos( $rowof( $held ), '>Write it now</button>' ),
		false !== strpos( $rowof( $held ), 'name="_wpnonce" value="nonce-wpcpm_sreg_write_' . $held . '"' ),
		false !== strpos( $rowof( $waiting ), 'wpcpm_sreg_write' ),
	),
	array( true, false, true, true, false ) );
ck( 'an entry a Students row answered keeps its answers, and its fold shows them',
	array( false !== strpos( $rowof( $byrow ), '<th scope="row">Full Name</th><td>Row Student</td>' ), false !== strpos( $rowof( $byrow ), 'No answers are kept' ), false !== strpos( $rowof( $byrow ), '<span class="wpcpm-inst-name__text">Row Student</span>' ) ),
	array( true, false, true ) );
ck( 'Could not be written gives the error, escaped, and Write it now',
	array(
		false !== strpos( $rowof( $failed ), '<strong>Could not be written</strong>: Airtable said: Field is invalid &lt;script&gt;' ),
		false !== strpos( $rowof( $failed ), 'name="action" value="wpcpm_sreg_write"' ),
		false !== strpos( $rowof( $failed ), '>Write it now</button>' ),
		false !== strpos( $rowof( $failed ), 'name="_wpnonce" value="nonce-wpcpm_sreg_write_' . $failed . '"' ),
		false !== strpos( $rowof( $failed ), 'name="wpcpm_sreg_id" value="' . $failed . '"' ),
		false !== strpos( $rowof( $failed ), 'name="sreg_view" value="all"' ),
		false === strpos( $rowof( $written ), 'wpcpm_sreg_write' ),
	),
	array( true, true, true, true, true, true, true ) );
ck( 'an email that was not sent says why: the limit, or the hand-off',
	array(
		false !== strpos( $rowof( $noreach ), '<p class="wpcpm-sreg-mail">The email was not sent: the day&#039;s limit on emails was reached.</p>' ),
		false !== strpos( $rowof( $nomail ), '<p class="wpcpm-sreg-mail">The email could not be handed off.</p>' ),
		false === strpos( $rowof( $written ), 'wpcpm-sreg-mail' ),
	),
	array( true, true, true ) );
ck( 'the answers and the consent are folded under each row: the labels, the answers escaped, the sentence, the time, the truncated address and the browser',
	array(
		1 === substr_count( $rowof( $written ), '<details class="wpcpm-sreg-details"><summary>Answers and consent</summary>' ),
		false !== strpos( $rowof( $written ), '<th scope="row">Full Name</th><td>Ada &lt;b&gt;Example&lt;/b&gt;</td>' ),
		false !== strpos( $rowof( $written ), '<th scope="row">Languages You Speak</th><td>Polish, English</td>' ),
		false !== strpos( $rowof( $written ), '<th scope="row">Educational Institution</th><td>Example University</td>' ),
		false !== strpos( $rowof( $written ), '<th scope="row">Compliance declaration</th><td>Agreed</td>' ),
		false === strpos( $rowof( $written ), 'Privacy Policy Compliance' ),
		false !== strpos( $rowof( $written ), 'Agreed to: By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (https://example.test/privacy/). Time: November 15, 2023 12:13 am. Address: 203.0.113.0. Browser: Mozilla/5.0 (test).' ),
	),
	array( true, true, true, true, true, true, true ) );

echo "\n--- The three presses ---\n";

tab_world();

$f1 = tab_reg( 'failed', array(), array( 'error' => 'Airtable refused it' ) );
$out = tab_press( 'handle_write', WPCPM_Student_Registrations_Tab::ACTION_WRITE, $f1, array( 'sreg_view' => 'failed' ) );

ck( 'Write it now on a failed registration writes it, and comes back to the view it was pressed on with the written notice',
	array( $out, reg_state( $f1 ), reg_outcome( $f1 )['record'], tab_flashed(), requests( 'POST' ) ),
	array( $back . '&sreg_view=failed', 'written', 'recSTUDENTROW0001', 'sreg-written', 1 ) );

tab_world();
$h1  = tab_reg( 'waiting', array(), array(), array( 'held', 'site-ceiling' ) );
$out = tab_press( 'handle_write', WPCPM_Student_Registrations_Tab::ACTION_WRITE, $h1, array( 'sreg_view' => 'waiting' ) );

ck( 'Write it now on a held registration takes the hold off and writes it at once, back on the view with the written notice',
	array( $out, reg_state( $h1 ), reg_signals( $h1 ), reg_outcome( $h1 )['record'], tab_flashed(), requests( 'POST' ), mail_log() ),
	array( $back . '&sreg_view=waiting', 'written', array( 'site-ceiling' ), 'recSTUDENTROW0001', 'sreg-written', 1, array( 'student-registration-received to ada@example.test' ) ) );

tab_world();
$h2 = tab_reg( 'waiting', array(), array(), array( 'held' ) );
airtable_down();
$out = tab_press( 'handle_write', WPCPM_Student_Registrations_Tab::ACTION_WRITE, $h2 );

ck( 'with Airtable down the hold is off all the same: it waits, and the hourly job tries it from then on',
	array( $out, reg_state( $h2 ), reg_signals( $h2 ), tab_flashed(), reg_outcome( $h2 )['tries'] ),
	array( $back, 'waiting', array(), 'sreg-waiting', 1 ) );

$GLOBALS['airtable']['find']   = api_answer( 200, array( 'records' => array() ) );
$GLOBALS['airtable']['schema'] = schema_answer();
$GLOBALS['airtable']['create'] = api_answer( 200, array( 'records' => array( array( 'id' => 'recSTUDENTROW0001', 'fields' => array() ) ) ) );

$run = WPCPM_Student_Registration_Process::run_hourly( $T );

ck( '   and the next run writes it', array( $run['tried'], reg_state( $h2 ) ), array( 1, 'written' ) );

tab_world();
$plain_wait = tab_reg( 'waiting', array(), array(), array( 'site-ceiling' ) );
$out        = tab_press( 'handle_write', WPCPM_Student_Registrations_Tab::ACTION_WRITE, $plain_wait );

ck( 'Write it now on a waiting registration that is not held is refused as the wrong state, and nothing is asked of Airtable',
	array( $out, tab_flashed(), reg_state( $plain_wait ), reg_signals( $plain_wait ), $GLOBALS['sent'] ),
	array( $back, 'sreg-state', 'waiting', array( 'site-ceiling' ), array() ) );

tab_world();
$f2 = tab_reg( 'failed', array(), array( 'error' => 'Airtable refused it' ) );
airtable_down();
$out = tab_press( 'handle_write', WPCPM_Student_Registrations_Tab::ACTION_WRITE, $f2 );

ck( 'Airtable down again: it is still failed, with the new reason, and the notice says Airtable refused it again',
	array( $out, reg_state( $f2 ), tab_flashed(), '' !== reg_outcome( $f2 )['error'] ),
	array( $back, 'failed', 'sreg-failed', true ) );

tab_world();
$sp = tab_reg( 'spam' );
an_account( 79, 'Ada Account', 'ada@example.test', array( 'wpcpm_student' ) );
$out = tab_press( 'handle_restore', WPCPM_Student_Registrations_Tab::ACTION_RESTORE, $sp, array( 'sreg_view' => 'spam' ) );

ck( 'Put back on spam whose address now has an account settles it as account, sends the account email, and says settled',
	array( $out, reg_state( $sp ), reg_outcome( $sp )['user'], array_map( static function ( $m ) { return $m['to']; }, $GLOBALS['mail'] ), tab_flashed(), requests( 'POST' ) ),
	array( $back . '&sreg_view=spam', 'account', 79, array( 'ada@example.test' ), 'sreg-settled', 0 ) );

tab_world();
$sp  = tab_reg( 'spam' );
$out = tab_press( 'handle_restore', WPCPM_Student_Registrations_Tab::ACTION_RESTORE, $sp );

ck( 'Put back on spam of a new address writes it, as a new registration is', array( $out, reg_state( $sp ), tab_flashed() ), array( $back, 'written', 'sreg-written' ) );

tab_world();
$sp = tab_reg( 'spam' );
airtable_down();
$out = tab_press( 'handle_restore', WPCPM_Student_Registrations_Tab::ACTION_RESTORE, $sp );

ck( 'Put back with Airtable down leaves it waiting, to be tried every hour', array( $out, reg_state( $sp ), tab_flashed() ), array( $back, 'waiting', 'sreg-waiting' ) );

tab_world();
$sp  = tab_reg( 'spam' );
$out = tab_press( 'handle_delete', WPCPM_Student_Registrations_Tab::ACTION_DELETE, $sp, array( 'sreg_view' => 'spam' ) );

ck( 'Delete for good on spam removes it', array( $out, get_post( $sp ), tab_flashed() ), array( $back . '&sreg_view=spam', null, 'sreg-deleted' ) );

tab_world();
$wr = tab_reg( 'written', array(), array( 'record' => 'recSTUDENTROW0001' ) );
$out = tab_press( 'handle_delete', WPCPM_Student_Registrations_Tab::ACTION_DELETE, $wr );

ck( 'Delete for good on a written registration is refused as the wrong state, and it is kept', array( $out, reg_state( $wr ), tab_flashed() ), array( $back, 'written', 'sreg-state' ) );

$out = tab_press( 'handle_write', WPCPM_Student_Registrations_Tab::ACTION_WRITE, $wr );
$spm = tab_reg( 'spam' );
$out2 = tab_press( 'handle_write', WPCPM_Student_Registrations_Tab::ACTION_WRITE, $spm );
$out3 = tab_press( 'handle_restore', WPCPM_Student_Registrations_Tab::ACTION_RESTORE, $wr );

ck( 'Write it now takes a failed one alone, and Put back a spam one alone',
	array( $out, $out2, $out3, reg_state( $wr ), reg_state( $spm ), requests( 'POST' ) ),
	array( $back, $back, $back, 'written', 'spam', 0 ) );

$out = tab_press( 'handle_delete', WPCPM_Student_Registrations_Tab::ACTION_DELETE, 99999 );

ck( 'a press on a registration that is not there says so', array( $out, tab_flashed() ), array( $back, 'sreg-unknown' ) );

$plain = reg_plain_post();
$out   = tab_press( 'handle_delete', WPCPM_Student_Registrations_Tab::ACTION_DELETE, $plain );

ck( 'and a post that is no registration is not one, and is kept', array( $out, tab_flashed(), get_post( $plain ) !== null ), array( $back, 'sreg-unknown', true ) );

tab_world();
$sp = tab_reg( 'spam', array( 'name' => 'Spam "Quoted" <i>Name</i>' ) );
$sh = array_values( array_filter( array( tab_html( array( 'sreg_view' => 'spam' ) ) ) ) )[0];

ck( 'a spam row offers Put back and Delete for good, and Delete asks first, in words with the name escaped',
	array(
		false !== strpos( $sh, 'name="action" value="wpcpm_sreg_restore"' ),
		false !== strpos( $sh, '>Put back</button>' ),
		false !== strpos( $sh, 'name="action" value="wpcpm_sreg_delete"' ),
		false !== strpos( $sh, '>Delete for good</button>' ),
		false !== strpos( $sh, 'data-wpcpm-confirm="Delete the registration of Spam &quot;Quoted&quot; &lt;i&gt;Name&lt;/i&gt; for good? It cannot be brought back."' ),
		false === strpos( $sh, 'wpcpm_sreg_write' ),
		1 === substr_count( $sh, 'data-wpcpm-confirm' ),
	),
	array( true, true, true, true, true, true, true ) );
ck( 'and nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );

reset_world();

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
