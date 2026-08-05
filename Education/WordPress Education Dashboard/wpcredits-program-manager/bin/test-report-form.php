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
require_once __DIR__ . '/../includes/class-wpcpm-wporg-profile.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentors-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-sync.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-notes.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-students-dashboard.php';
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
	'Open source basics and WordPress - final grade',
	'How decisions are made in the WordPress project - final grade',
	'Community meeting etiquette - final grade',
	'Writing in the WordPress voice - final grade',
	'Beginner WordPress User - final grade',
	'Intermediate WordPress User - final grade',
	'Advance WordPress User - final grade',
	'Beginner WordPress Developer',
	'Intermediate Theme Developer',
	'Beginner WordPress Designer',
	'Contribution Project Description',
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
	'Open source basics and WordPress - final grade',
	'How decisions are made in the WordPress project - final grade',
	'Basic principles of conflict resolution - final grade',
	'Contribution Project Description',
	'Slack/GitHub/Blog WordPress Community meetings/discussions',
	'Final Contribution Project Report',
	'Personal Website URL',
);

$sensei = array_keys( WPCPM_Student_Report_Form::fields( false ) );
$fifty  = array_keys( WPCPM_Student_Report_Form::fields( true ) );

sort( $sensei );
sort( $fifty );

$want_sensei = $sensei_expected;
$want_fifty  = $fifty_expected;
sort( $want_sensei );
sort( $want_fifty );

ck( 'In Sensei holds exactly its 20 fields', $sensei, $want_sensei );
ck( 'In Sensei 50h holds exactly its 8 fields', $fifty, $want_fifty );

// Both were removed deliberately, and each for its own reason: contribution teams are chosen once,
// in My profile, and a second control for the same Airtable column invited two answers; Company waits
// for the Sponsors module, which will own it. Asserted so neither drifts back in unnoticed.
ck( 'contribution teams are not on the report form',
    array( in_array( 'Main Contribution Team', $sensei, true ), in_array( 'Main Contribution Team', $fifty, true ) ),
    array( false, false ) );

ck( 'the sponsor company is not on the report form',
    array( in_array( 'Company ', $sensei, true ), in_array( 'Company ', $fifty, true ) ),
    array( false, false ) );

// The three fields the program excluded must not be back, on either track.
foreach ( array( 'Name', 'WordPress Profile', 'Slack Name' ) as $excluded ) {
	ck( sprintf( '"%s" is on neither form', $excluded ),
	    array( in_array( $excluded, $sensei, true ), in_array( $excluded, $fifty, true ) ),
	    array( false, false ) );
}

// Deprecated columns, deliberately left out.
ck( 'nothing marked (DELETED) is offered',
    count( preg_grep( '/DELETED/', array_merge( $sensei, $fifty ) ) ), 0 );

// Computed columns cannot be written at all, so offering one would guarantee a failed save.
foreach ( array( 'Personal link', '50h personal link', "Mentor's email" ) as $computed ) {
	ck( sprintf( 'the computed "%s" is not offered', $computed ),
	    in_array( $computed, array_merge( $sensei, $fifty ), true ), false );
}

// The one difference between the tracks that is easy to get backwards.
ck( 'conflict resolution belongs to the 50h track alone',
    array(
        in_array( 'Basic principles of conflict resolution - final grade', $fifty, true ),
        in_array( 'Basic principles of conflict resolution - final grade', $sensei, true ),
    ),
    array( true, false ) );

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

foreach ( array( 'In Sensei' => false, 'In Sensei 50h' => true ) as $label => $is_50h ) {
	$orphans = array();

	foreach ( WPCPM_Student_Report_Form::fields( $is_50h ) as $name => $spec ) {
		if ( ! isset( $spec['group'] ) || ! in_array( $spec['group'], $groups, true ) ) {
			$orphans[] = $name;
		}
	}

	ck( sprintf( 'every %s field is in a declared group', $label ), $orphans, array() );
}

// A group with a legend but no fields would draw an empty box.
ck( 'the 50h form has no reflection posts group',
    count( array_filter( WPCPM_Student_Report_Form::fields( true ), static function ( $spec ) { return 'posts' === $spec['group']; } ) ),
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

// The reason `clean_url()` exists rather than trusting the browser: a javascript: URL must not
// survive, and prefixing it with https would be worse than dropping it.
ck( 'a javascript: URL does not survive', clean( 'javascript:alert(1)', $url ), array( true, '' ) );

echo "\n=== Form keys ===\n";

// Airtable names contain spaces, slashes and colons, and one ends in a space. The key has to be
// stable and survive a round trip through a form.
$keys = array();

foreach ( array_keys( WPCPM_Student_Report_Form::fields( false ) ) as $name ) {
	$keys[] = WPCPM_Student_Report_Form::key( $name );
}

ck( 'every field has a distinct key', count( array_unique( $keys ) ), count( $keys ) );
ck( 'keys are safe in a form name', count( preg_grep( '/^[a-z0-9]+$/', $keys ) ), count( $keys ) );
ck( '"Company " and "Company" would not collide',
    WPCPM_Student_Report_Form::key( 'Company ' ) === WPCPM_Student_Report_Form::key( 'Company' ), false );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
