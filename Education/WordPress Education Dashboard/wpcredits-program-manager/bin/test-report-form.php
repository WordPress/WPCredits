<?php
/**
 * The report form: the two field sets, and what may be written to Airtable.
 *
 * **The field lists are pinned here on purpose.** Airtable exposes no way to read a view's visible
 * fields, so the two sets are maintained by hand in `WPCPM_Student_Report_Form::fields()`. That makes
 * them the kind of thing that drifts silently — a field renamed in the base, or one dropped from a
 * view, shows up as a box nobody fills in rather than as an error. Asserting the exact names against
 * what the program said the views hold turns that into a test failure.
 *
 * The other half is `clean()`. Every value goes to a live Airtable PATCH, and **one unusable value
 * fails the whole request** — so a mistyped grade must not be able to take the other twenty-one
 * answers down with it, and "cleared" must never be confused with "rejected".
 *
 * Run from the plugin root:  php bin/test-report-form.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['opts']  = array();
$GLOBALS['umeta'] = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
class WP_User {
	public $ID = 0, $display_name = '';
	public function __construct( $id = 0 ) { $this->ID = $id; }
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = 'publish';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
// Close enough to WordPress's own for what is asserted here: strip what an address cannot contain,
// then answer whether what is left is one.
function sanitize_email( $s ) { return trim( preg_replace( '/[^a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~@\-]/', '', (string) $s ) ); }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {}
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u ); }
function number_format_i18n( $n, $d = 0 ) { return (string) round( $n, $d ); }
function human_time_diff( $a, $b = 0 ) { return '2 hours'; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return $GLOBALS['opts'][ 'T_' . $k ] ?? false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['opts'][ 'T_' . $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['opts'][ 'T_' . $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function get_users( $a = array() ) { return array(); }
function get_user_by( $f, $v ) { return new WP_User( (int) $v ); }
function get_current_user_id() { return $GLOBALS['uid'] ?? 0; }
function is_user_logged_in() { return ! empty( $GLOBALS['uid'] ); }
function current_user_can( $c ) { return ! empty( $GLOBALS['caps'] ); }
function user_can( $u, $c ) { return ! empty( $GLOBALS['caps'] ); }
function is_admin() { return false; }
function get_post( $id = null ) { return null; }
function get_posts( $a = array() ) { return array(); }
function get_post_meta( $id, $k = '', $single = false ) { return $single ? '' : array(); }
function wp_next_scheduled( $h ) { return false; }
function wp_schedule_single_event() {} function wp_clear_scheduled_hook() {}
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_create_nonce( $a = -1 ) { return 'nonce'; }
function wp_nonce_field( $a = -1, $n = '_wpnonce', $r = true, $echo = true ) {
	$field = sprintf( '<input type="hidden" name="%s" value="%s" />', $n, wp_create_nonce( $a ) );

	if ( $echo ) {
		echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test double.
	}

	return $field;
}

/**
 * Honours the protocol allow-list, so the URL assertions are not vacuous.
 *
 * A pass-through stub here once made a security assertion prove nothing, which is the reason this
 * is spelled out rather than returning its input.
 */
function esc_url_raw( $url, $protocols = null ) {
	$url    = trim( (string) $url );
	$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

	if ( null !== $protocols && ! in_array( $scheme, (array) $protocols, true ) ) {
		return '';
	}

	return $url;
}

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-settings.php';
require_once __DIR__ . '/../includes/class-wpcpm-flash.php';
require_once __DIR__ . '/../includes/class-wpcpm-airtable.php';
require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/class-wpcpm-icons.php';
require_once __DIR__ . '/../includes/class-wpcpm-contribution-teams.php';
require_once __DIR__ . '/../includes/class-wpcpm-wporg-profile.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-notes.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-dashboard.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php';

$fails = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
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
 * Run the private validator.
 *
 * @param mixed $raw  Posted value.
 * @param array $spec Field spec.
 * @return array{0:bool,1:mixed}
 */
function clean( $raw, array $spec ) {
	static $method = null;

	if ( null === $method ) {
		$method = new ReflectionMethod( 'WPCPM_Student_Report_Form', 'clean' );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
	}

	return $method->invoke( null, $raw, $spec );
}

echo "=== The two field sets, exactly as the program specified them ===\n";

// Written out rather than derived. A list generated from the implementation would agree with
// whatever the implementation happened to contain, which is the one thing this must not do.
$sensei_expected = array(
	'Hours',
	'WordPress Profile',
	'Slack Name',
	'Open source basics and WordPress - final grade',
	'How decisions are made in the WordPress project - final grade',
	'Community meeting etiquette - final grade',
	'Writing in the WordPress voice - final grade',
	'Basic principles of conflict resolution - final grade',
	'Beginner WordPress User - final grade',
	'Intermediate WordPress User - final grade',
	'Advance WordPress User - final grade',
	'Beginner WordPress Developer',
	'Intermediate Theme Developer',
	'Beginner WordPress Designer',
	'Main Contribution Team',
	'Contribution Project Summary',
	'Personal Website URL',
	'Post Reflection: Building Your Personal Website',
	'Slack/GitHub/Blog WordPress Community meetings/discussions',
	'Post Reflection: Choosing Your Team and Project',
	'Post Reflection: Your First Contribution',
	'Post Reflection: Halfway Check-In',
	'WP event participation URL',
	'Closing post URL',
);

$fifty_expected = array(
	'Hours',
	'WordPress Profile',
	'Slack Name',
	'Open source basics and WordPress - final grade',
	'How decisions are made in the WordPress project - final grade',
	'Basic principles of conflict resolution - final grade',
	'Main Contribution Team',
	'Contribution Project Summary',
	'Slack/GitHub/Blog WordPress Community meetings/discussions',
	'Final Contribution Project Report',
	'Personal Website URL',
);

$sensei = array_keys( WPCPM_Student_Report_Form::fields( '150h' ) );
$fifty  = array_keys( WPCPM_Student_Report_Form::fields( '50h' ) );

sort( $sensei );
sort( $fifty );

$want_sensei = $sensei_expected;
$want_fifty  = $fifty_expected;
sort( $want_sensei );
sort( $want_fifty );

ck( 'In Sensei holds exactly its 24 fields', $sensei, $want_sensei );
ck( 'In Sensei 50h holds exactly its 11 fields', $fifty, $want_fifty );

// The contribution team is asked for **here and nowhere else**. It was on the profile editor until
// 1.46.0; two controls writing one Airtable column invited two answers, so the assertion is not that
// it exists but that it exists once. `$profile_src` is read below.
ck( 'contribution teams are on the report form, on both tracks',
    array( in_array( 'Main Contribution Team', $sensei, true ), in_array( 'Main Contribution Team', $fifty, true ) ),
    array( true, true ) );

// It heads the Project section on both, which is where the Airtable form asks it.
$sensei_specs = WPCPM_Student_Report_Form::fields( '150h' );
$fifty_specs  = WPCPM_Student_Report_Form::fields( '50h' );

ck( 'and sits in the project group',
    array( $sensei_specs['Main Contribution Team']['group'], $fifty_specs['Main Contribution Team']['group'] ),
    array( 'project', 'project' ) );

// Above "What you contributed" — the order the fields are declared in is the order they render in.
$order = static function ( array $specs, $name ) {
	return array_search( $name, array_keys( $specs ), true );
};

ck( 'above the project description, on both tracks',
    array(
        $order( $sensei_specs, 'Main Contribution Team' ) < $order( $sensei_specs, 'Contribution Project Summary' ),
        $order( $fifty_specs, 'Main Contribution Team' ) < $order( $fifty_specs, 'Contribution Project Summary' ),
    ),
    array( true, true ) );

// The other half of "once": nothing else may offer it. The profile editor did, and was deleted
// when its last three fields moved here — so the assertion is that the file itself is gone, which
// is the only way "asked once" can be guaranteed rather than hoped for.
ck( 'and the profile editor that used to offer it is gone',
    file_exists( __DIR__ . '/../includes/modules/class-wpcpm-student-profile.php' ), false );

// Company waits for the Sponsors module, which will own it.

ck( 'the sponsor company is not on the report form',
    array( in_array( 'Company ', $sensei, true ), in_array( 'Company ', $fifty, true ) ),
    array( false, false ) );

// `Name` is the student's own and comes from the record; the form never asks it.
//
// `WordPress Profile` and `Slack Name` were on this list until 1.48.0, when the profile editor
// was removed and the form took its three questions over. What has to hold now is the same thing
// that holds for contribution teams: asked here, and nowhere else.
ck( '"Name" is on neither form',
    array( in_array( 'Name', $sensei, true ), in_array( 'Name', $fifty, true ) ),
    array( false, false ) );

ck( 'the contact lessons open Onboarding on both tracks',
    array(
        $sensei_specs['WordPress Profile']['group'],
        $sensei_specs['Slack Name']['group'],
        $order( $sensei_specs, 'WordPress Profile' ) < $order( $sensei_specs, 'Open source basics and WordPress - final grade' ),
        $fifty_specs['WordPress Profile']['group'],
    ),
    array( 'onboarding', 'onboarding', true, 'onboarding' ) );

// The profile editor is gone, so nothing else writes these three columns.
ck( 'the profile editor no longer exists',
    file_exists( __DIR__ . '/../includes/modules/class-wpcpm-student-profile.php' ), false );

// Deprecated columns, deliberately left out.
ck( 'nothing marked (DELETED) is offered',
    count( preg_grep( '/DELETED/', array_merge( $sensei, $fifty ) ) ), 0 );

// Computed columns cannot be written at all, so offering one would guarantee a failed save.
foreach ( array( 'Personal link', '50h personal link', "Mentor's email" ) as $computed ) {
	ck( sprintf( 'the computed "%s" is not offered', $computed ),
	    in_array( $computed, array_merge( $sensei, $fifty ), true ), false );
}

// It was 50h-only until 1.47.0, which was the 50-hour form having been built first rather than a
// difference between the courses. Both ask it.
ck( 'conflict resolution is asked on both tracks',
    array(
        in_array( 'Basic principles of conflict resolution - final grade', $fifty, true ),
        in_array( 'Basic principles of conflict resolution - final grade', $sensei, true ),
    ),
    array( true, true ) );

// Between the voice course and the three user levels, which is the order the long form uses.
ck( 'and sits between the voice course and the user levels',
    array(
        $order( $sensei_specs, 'Writing in the WordPress voice - final grade' )
            < $order( $sensei_specs, 'Basic principles of conflict resolution - final grade' ),
        $order( $sensei_specs, 'Basic principles of conflict resolution - final grade' )
            < $order( $sensei_specs, 'Beginner WordPress User - final grade' ),
    ),
    array( true, true ) );

// Onboarding is four runs, and each says what it is one way or the other: a heading over the
// fields, or a note under them. The assertion is that none of them is unmarked — a run with
// neither reads as a continuation of the one above it, which is how the marks and the two
// contact questions ran together before 1.52.0.
ck( 'every run in Onboarding is marked, by a heading or a note',
    array(
        isset( $sensei_specs['Open source basics and WordPress - final grade']['subgroup'] ),
        isset( $sensei_specs['Beginner WordPress User - final grade']['divider'] ),
        isset( $sensei_specs['Advance WordPress User - final grade']['note'] ),
        isset( $sensei_specs['Beginner WordPress Developer']['divider'] ),
        isset( $sensei_specs['Beginner WordPress Designer']['note'] ),
        isset( $sensei_specs['Personal Website URL']['subgroup'] ),
    ),
    array( true, true, true, true, true, true ) );

ck( 'the final project report belongs to the 50h track alone',
    array(
        in_array( 'Final Contribution Project Report', $fifty, true ),
        in_array( 'Final Contribution Project Report', $sensei, true ),
    ),
    array( true, false ) );

ck( 'the reflection posts belong to In Sensei alone',
    count( preg_grep( '/^Post Reflection/', $fifty ) ), 0 );

echo "\n=== Every field belongs to a group ===\n";

// The form renders group by group, so a field with an unknown group is a field that renders
// nowhere — invisible on the page and impossible to fill in, with nothing to show it went missing.
$groups = array_keys( WPCPM_Student_Report_Form::groups() );

foreach ( array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev' ) as $label => $track ) {
	$orphans = array();

	foreach ( WPCPM_Student_Report_Form::fields( $track ) as $name => $spec ) {
		if ( ! isset( $spec['group'] ) || ! in_array( $spec['group'], $groups, true ) ) {
			$orphans[] = $name;
		}
	}

	ck( sprintf( 'every %s field is in a declared group', $label ), $orphans, array() );
}

// A group with a legend but no fields would draw an empty box.
ck( 'the 50h form has no reflection posts group',
    count( array_filter( WPCPM_Student_Report_Form::fields( '50h' ), static function ( $spec ) { return 'posts' === $spec['group']; } ) ),
    0 );

echo "\n=== Numbers: cleared is not the same as rejected ===\n";

$grade = array( 'label' => 'A grade', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max' => 100 );
$hours = array( 'label' => 'Hours', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 10000 );

ck( 'a grade with decimals is kept to two places', clean( '87.456', $grade ), array( true, 87.46 ) );
ck( 'a whole number stays whole', clean( '120', $hours ), array( true, 120 ) );
ck( 'hours are rounded, not truncated', clean( '119.7', $hours ), array( true, 120 ) );

// The distinction the first draft got wrong: an empty box clears the column, and Airtable clears a
// number with null. Returning "reject" here would have left a stale grade on the record for ever.
ck( 'an empty box clears the column', clean( '', $grade ), array( true, null ) );

ck( 'a comma decimal is understood', clean( '87,5', $grade ), array( true, 87.5 ) );
ck( 'words are refused, not sent as zero', clean( 'eighty-seven', $grade ), array( false, null ) );
ck( 'a grade above 100 is refused', clean( '101', $grade ), array( false, null ) );
ck( 'a negative grade is refused', clean( '-1', $grade ), array( false, null ) );
ck( 'an array where a number belongs is refused', clean( array( 5 ), $grade ), array( false, null ) );

echo "\n=== URLs ===\n";

$url = array( 'label' => 'A link', 'type' => 'url' );

ck( 'a scheme-less address gets https', clean( 'example.com/post', $url ), array( true, 'https://example.com/post' ) );
ck( 'a real URL is left alone', clean( 'http://example.com/x', $url ), array( true, 'http://example.com/x' ) );
ck( 'an empty box clears it', clean( '', $url ), array( true, '' ) );

// The reason `WPCPM_Field_Value::clean_url()` exists rather than trusting the browser: a javascript: URL must not
// survive, and prefixing it with https would be worse than dropping it.
ck( 'a javascript: URL does not survive', clean( 'javascript:alert(1)', $url ), array( true, '' ) );

echo "\n=== Form keys ===\n";

// Airtable names contain spaces, slashes and colons, and one ends in a space. The key has to be
// stable and survive a round trip through a form.
$keys = array();

foreach ( array_keys( WPCPM_Student_Report_Form::fields( '150h' ) ) as $name ) {
	$keys[] = WPCPM_Student_Report_Form::key( $name );
}

ck( 'every field has a distinct key', count( array_unique( $keys ) ), count( $keys ) );
ck( 'keys are safe in a form name', count( preg_grep( '/^[a-z0-9]+$/', $keys ) ), count( $keys ) );
ck( '"Company " and "Company" would not collide',
    WPCPM_Student_Report_Form::key( 'Company ' ) === WPCPM_Student_Report_Form::key( 'Company' ), false );

echo "\n=== Read only ===\n";

/*
 * **This is the half that shipped wrong.** The form was gated on `user_can_edit()` alone, which is
 * true for a program manager — so a manager opening a report from a *mentor's* page got live boxes
 * and a Save button over somebody else's answers. The capability says who may ever edit; the view
 * says whether this place is one where editing happens, and on a mentee card it is not.
 *
 * Asserted on the markup rather than on the flag, because "disabled" that reaches one field type
 * and not another looks read only until somebody types in the box it missed.
 */
$record = 'recStudent0000001';
$sid    = 7;

$GLOBALS['umeta'][ $sid ][ WPCPM_Students_Sync::META_RECORD_ID ] = $record;

// Seeded through the cache `values()` reads first, so nothing here reaches Airtable.
set_transient(
	'wpcpm_report_' . md5( $record ),
	array(
		'Total hours'     => 42,
		'Personal site'   => 'https://example.test/me',
		'Contribution to' => array( 'recTeam0000000001' ),
	)
);

// One real team, so the checkbox branch renders rather than printing its "run a sync" hint.
update_option(
	WPCPM_Mentors_Sync::OPT_LOOKUPS,
	array( 'v' => WPCPM_Mentors_Sync::LOOKUPS_VERSION, 'teams' => array( 'recTeam0000000001' => 'Documentation' ) )
);

/**
 * Render one report body.
 *
 * @param bool $read_only Whether the view forces a record rather than a form.
 * @param bool $manager   Whether the viewer holds the manage capability.
 * @return string
 */
function body( $read_only, $manager ) {
	global $sid;

	$GLOBALS['uid']  = 99;      // Somebody other than the student.
	$GLOBALS['caps'] = $manager;

	$method = new ReflectionMethod( 'WPCPM_Student_Report_Form', 'render_body' );

	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	ob_start();
	$method->invoke( null, new WP_User( $sid ), array( 'is_50h' => false, 'record_id' => $GLOBALS['rec'] ), $read_only );

	return (string) ob_get_clean();
}

$GLOBALS['rec'] = $record;

$read = body( true, true );   // A manager reading a mentor's page: the worst case.
$edit = body( false, true );  // The same manager on the student's own card.

ck( 'a read-only report is not a form at all', false !== strpos( $read, '<form' ), false );
ck( 'no save button',                          false !== strpos( $read, 'Save my report' ), false );
ck( 'no nonce to post with',                   false !== strpos( $read, 'name="_wpnonce"' ), false );

// Counted rather than searched: one enabled box among twenty disabled ones is the failure worth
// catching, and "contains disabled" would pass with nineteen.
preg_match_all( '/<(input|textarea)\b[^>]*/', $read, $m );

$controls = $m[0];
$enabled  = array_values( array_filter( $controls, function ( $tag ) { return false === strpos( $tag, 'disabled' ); } ) );

ck( 'the fields did render',            count( $controls ) > 10, true );
ck( 'every control is disabled',        $enabled, array() );
ck( 'the team checkboxes are among them', count( preg_grep( '/type="checkbox"/', $controls ) ) > 0, true );
ck( 'it says why it cannot be edited',  false !== strpos( $read, 'not editable' ), true );

// The other half of the same rule: forcing read only must not have disabled the form for the
// people who are meant to fill it in.
ck( 'an editable report is still a form', false !== strpos( $edit, '<form' ), true );
ck( 'and still has its save button',      false !== strpos( $edit, 'Save my report' ), true );

preg_match_all( '/<(input|textarea)\b[^>]*/', $edit, $m2 );

ck( 'and nothing in it is disabled', count( preg_grep( '/disabled/', $m2[0] ) ), 0 );

echo "\n=== Every field name is a real Airtable column ===\n";

// **This is the check that was missing.** The form read and wrote
// `Contribution Project Description` for both tracks until 1.61.0; the base's column is
// `Contribution Project Summary`, so that answer neither loaded nor saved, silently, for as long
// as the field existed. Nothing in the code could notice: a field name is just a string until
// Airtable sees it.
//
// The fixture is the table's field list, read from the metadata API. It has to be refreshed when
// the table changes, which is the point — a rename in Airtable should break a test here rather
// than a student's report there.
$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/reports-table-fields.json' ), true );
$real    = isset( $fixture['fields'] ) ? $fixture['fields'] : array();

ck( 'the fixture loaded', count( $real ) > 40, true );

// Trailing spaces are real. `Company ` has one in the base, and trimming the fixture would hide
// exactly the class of bug it exists to catch.
ck( 'and keeps the trailing space on "Company "', in_array( 'Company ', $real, true ), true );

foreach ( array( '150h', '50h', 'dev' ) as $track ) {
	$unknown = array_values( array_diff( array_keys( WPCPM_Student_Report_Form::fields( $track ) ), $real ) );

	ck( sprintf( 'every %s field name exists in Airtable', $track ), $unknown, array() );
}

echo "\n=== Developer Track ===\n";

$dev = WPCPM_Student_Report_Form::fields( 'dev' );
$one = WPCPM_Student_Report_Form::fields( '150h' );

// Asserted as a set relation rather than a field list, so it keeps holding when either form
// changes. The base says the dev view is a superset of the 150-hour one; this says the code agrees.
ck( 'the dev form is a superset of the 150-hour form',
    array_values( array_diff( array_keys( $one ), array_keys( $dev ) ) ), array() );

$added = array_values( array_diff( array_keys( $dev ), array_keys( $one ) ) );
sort( $added );

$want = array(
	'Alumni program: mentoring opt-in',
	'Alumni program: personal email',
	'Contributing beyond WP Credits',
	'Developer Basics: modules completed',
	'Developer basics: Optional modules taken',
	'Optional: Additional Contribution Project Summary',
	'Patch Testing: Trac ticket comments',
);

ck( 'and adds exactly the seven developer fields', $added, $want );

// `Email` is the account's identity and the key both syncs join on. A student who could edit it
// would detach their own record from their account.
ck( 'the email column is not a form field', isset( $dev['Email'] ), false );

// `Post Reflection: Choosing Your Team and Project copy` was field 27 of the dev-track view and was
// left out of the form pending an answer about it. Celi Garoe confirmed on 28 August 2026 that it
// was a duplicated field, and it has been deleted from the base — so there is no assertion here any
// more. Adding it to the form now fails "every dev field name exists in Airtable" above, which is
// the stronger check and the one that catches the whole class rather than this one instance.

$at = static function ( array $specs, $name ) {
	return array_search( $name, array_keys( $specs ), true );
};

// Order inside a group is this array's order, and `insert_after()` is what puts each new field
// where the Airtable view has it. A renamed anchor would silently append instead, so the positions
// are asserted rather than the call.
ck( 'the developer modules follow the user levels',
    $at( $dev, 'Developer Basics: modules completed' ) === $at( $dev, 'Advance WordPress User - final grade' ) + 1, true );

// Lesson 3, "Practical: Patch Testing", which the course puts in the project run rather than among
// the course grades. It was in Onboarding while the Airtable view was the only guide — the view
// lists columns in table order, the course is the order a student actually works through.
ck( 'patch testing is in the project section, not with the course grades',
    $dev['Patch Testing: Trac ticket comments']['group'], 'project' );

// It heads the section, under a heading of its own. A subgroup heading closes whatever row is
// open — `render_body()` has to, or the heading would print inside the pair's grid — so a field
// carrying one cannot also sit in the stacked column beside the team list.
ck( 'and heads the section, on a full-width row of its own',
    array(
        $at( $dev, 'Patch Testing: Trac ticket comments' ) < $at( $dev, 'Main Contribution Team' ),
        isset( $dev['Patch Testing: Trac ticket comments']['row'] ),
    ),
    array( true, false ) );

// The two headings the course names these runs by, without which a student has to guess which
// lesson a question belongs to.
ck( 'the two developer sections are named after their lessons',
    array(
        $dev['Patch Testing: Trac ticket comments']['subgroup'],
        $dev['Slack/GitHub/Blog WordPress Community meetings/discussions']['subgroup'],
    ),
    array( 'Practical', 'Alumni Program' ) );

// On this course the meetings and discussions are asked inside the Alumni Program lesson, so on
// this track the field heads that run instead of sitting in the column beside the team list.
ck( 'the discussions open the alumni run rather than the project column',
    array(
        isset( $dev['Slack/GitHub/Blog WordPress Community meetings/discussions']['row'] ),
        $dev['Slack/GitHub/Blog WordPress Community meetings/discussions']['group'],
    ),
    array( false, 'project' ) );

// **Moved on one track, not on all of them.** The other two courses ask it with the project
// questions, and the field is one Airtable column — a copy left behind would be two boxes writing
// to it, which is the bug the contribution teams had.
$one = WPCPM_Student_Report_Form::fields( '150h' );

ck( 'and the 150-hour form still asks it where it always did',
    array(
        isset( $one['Slack/GitHub/Blog WordPress Community meetings/discussions']['row'] ),
        isset( $one['Slack/GitHub/Blog WordPress Community meetings/discussions']['subgroup'] ),
    ),
    array( true, false ) );

// A rule where the next lesson starts. Only on this track: on the others the team list is the
// first thing in the section, and a rule directly under the legend divides nothing.
ck( 'a rule opens the team lesson on the developer track only',
    array(
        ! empty( $dev['Main Contribution Team']['divider'] ),
        ! empty( $one['Main Contribution Team']['divider'] ),
    ),
    array( true, false ) );

ck( 'the second project summary follows the first',
    $at( $dev, 'Optional: Additional Contribution Project Summary' ) === $at( $dev, 'Contribution Project Summary' ) + 1, true );

// They read as end-of-programme questions, which is why they were in Wrap-up at first. The base's
// dev-track view asks them in the middle of Project, between the first-contribution post and the
// halfway one, and that is the program's decision rather than something to infer from the wording.
ck( 'the alumni questions are in the project section',
    array( $dev['Alumni program: personal email']['group'], $dev['Alumni program: mentoring opt-in']['group'], $dev['Contributing beyond WP Credits']['group'] ),
    array( 'project', 'project', 'project' ) );

// The alumni programme is lesson 7 and the first-contribution reflection is lesson 9, so the
// alumni questions come *before* it. They were after it while the Airtable view was the only
// guide. The three of them also close the pair: they carry no row, so the run of stacked questions
// beside the team list ends here, which is what puts them full width.
ck( 'the alumni questions follow the discussions and precede the first-contribution post',
    array_slice(
        array_keys( $dev ),
        $at( $dev, 'Slack/GitHub/Blog WordPress Community meetings/discussions' ),
        5
    ),
    array(
        'Slack/GitHub/Blog WordPress Community meetings/discussions',
        'Contributing beyond WP Credits',
        'Alumni program: personal email',
        'Alumni program: mentoring opt-in',
        'Post Reflection: Your First Contribution',
    ) );

// The consent label has to say what is being agreed to. "Alumni program: mentoring opt-in" is a
// column name, not a question a person can answer.
ck( 'the consent checkbox says what it is consenting to',
    false !== stripos( $dev['Alumni program: mentoring opt-in']['label'], 'mentoring' )
        && false !== stripos( $dev['Alumni program: mentoring opt-in']['label'], 'happy to be contacted' ), true );

echo "\n=== The email and checkbox controls ===\n";

$email_spec = array( 'label' => 'An email', 'type' => 'email' );
$check_spec = array( 'label' => 'A tick', 'type' => 'checkbox' );

ck( 'a valid address is kept',   clean( 'someone@example.com', $email_spec ), array( true, 'someone@example.com' ) );
ck( 'whitespace around it goes', clean( '  someone@example.com  ', $email_spec ), array( true, 'someone@example.com' ) );
ck( 'an empty box clears it',    clean( '', $email_spec ), array( true, '' ) );
ck( 'a malformed address is rejected, not stored', clean( 'not-an-address', $email_spec ), array( false, null ) );

// Airtable takes a real boolean here, and both directions have to be answers: the hidden `0` the
// form posts beside the box is what makes unticking reach the base at all.
ck( 'a ticked box is true',   clean( '1', $check_spec ), array( true, true ) );
ck( 'an unticked box is false, not nothing', clean( '0', $check_spec ), array( true, false ) );

echo "\n=== Paired fields stay together ===\n";

// **This is the check that was missing, and the reason the Project section came out scattered.**
//
// `render_body()` opens a `.wpcpm-report__pair` at the first field carrying a `row` and closes it
// at the first field that does not. So a field inserted into the middle of a paired run ends the
// pair, and the fields after it open a *second* pair — which, at two columns, renders as a run of
// half-width boxes with an empty column beside them. Nothing errors; it just looks wrong, and only
// on the one track whose form has the extra field.
//
// The property is simple: every field sharing a `row` must be contiguous. Asserting it is what
// makes "you broke a pair" a failing test rather than something to spot in a screenshot.
foreach ( array( '150h', '50h', 'dev' ) as $track ) {
	$specs  = WPCPM_Student_Report_Form::fields( $track );
	$broken = array();
	$seen   = array();
	$last   = '';

	foreach ( $specs as $name => $spec ) {
		$row = isset( $spec['row'] ) ? $spec['row'] : '';

		// Coming back to a row after leaving it is the failure: the run was interrupted.
		if ( '' !== $row && $row !== $last && isset( $seen[ $row ] ) ) {
			$broken[] = $row . ' resumes at ' . $name;
		}

		if ( '' !== $row ) {
			$seen[ $row ] = true;
		}

		$last = $row;
	}

	ck( sprintf( 'no %s pair is interrupted', $track ), $broken, array() );
}

// The stacked column is one cell of the pair, opened at the first field marked `stack`. A field
// between the pair's start and its stack that carries neither would sit outside both.
$dev = WPCPM_Student_Report_Form::fields( 'dev' );

ck( 'the second project summary is in the stacked column, not loose in the group',
    array(
        isset( $dev['Optional: Additional Contribution Project Summary']['row'] ) ? $dev['Optional: Additional Contribution Project Summary']['row'] : '',
        ! empty( $dev['Optional: Additional Contribution Project Summary']['stack'] ),
    ),
    array( 'project', true ) );

// Everything the group lays out itself gets either the number treatment or a row to itself, which
// is the form's whole layout rule. A control type missing from that CSS list becomes a narrow cell
// with whatever fits beside it — how the alumni address first rendered.
//
// Fields inside a pair are exempt: the pair sets its own columns, which is why `Slack Name` can be
// a plain text box without a full-width rule.
$full_width = array( 'textarea', 'richtext', 'url', 'email', 'checkbox', 'team' );
$css        = file_get_contents( __DIR__ . '/../assets/css/calendar.css' );
$missing    = array();

foreach ( array( '150h', '50h', 'dev' ) as $track ) {
	foreach ( WPCPM_Student_Report_Form::fields( $track ) as $name => $spec ) {
		$type = isset( $spec['type'] ) ? $spec['type'] : 'text';

		if ( ! empty( $spec['row'] ) || 'number' === $type || in_array( $type, $full_width, true ) ) {
			continue;
		}

		$missing[ $type ] = $name;
	}
}

ck( 'every control type is either a number or laid out full width', $missing, array() );

foreach ( $full_width as $type ) {
	ck( sprintf( 'the %s control has its full-width rule', $type ),
	    false !== strpos( $css, '.wpcpm-report__group > .wpcpm-field--' . $type . ',' )
	        || false !== strpos( $css, '.wpcpm-report__group > .wpcpm-field--' . $type . ' {' ), true );
}

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
