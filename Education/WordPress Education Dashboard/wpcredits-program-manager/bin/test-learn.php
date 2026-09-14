<?php
/**
 * WPCPM_Learn: the course resolved from its link and the course's modules and lessons, parsed from
 * answers Learn actually gave, cached for a day, refreshed on demand (the design's decision 30).
 *
 * WordPress is stood in; Learn's HTTP is a table of answers keyed by the address asked, so a check
 * can see what was asked, what was cached, and that a failure was not.
 *
 * Run: php bin/test-learn.php
 *
 * @package WPCredits_Program_Manager
 */

define( 'ABSPATH', '/' );
define( 'DAY_IN_SECONDS', 86400 );

function __( $s, $d = null ) { return $s; }

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }

$GLOBALS['transients'] = array();
$GLOBALS['ttl']        = array();
$GLOBALS['http']       = array();
$GLOBALS['asked']      = array();

function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; $GLOBALS['ttl'][ $k ] = $ttl; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ], $GLOBALS['ttl'][ $k ] ); return true; }

// Learn, as a table of answers by address. An address with no answer is a network failure, the
// shape `wp_remote_get()` gives when nothing answers at all.
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['asked'][] = $url;

	return array_key_exists( $url, $GLOBALS['http'] ) ? $GLOBALS['http'][ $url ] : new WP_Error( 'http_request_failed', 'cURL error 28: Connection timed out' );
}

function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? ( $r['response']['code'] ?? 200 ) : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ?? '' ) : ''; }

require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
require_once __DIR__ . '/../includes/class-wpcpm-learn.php';

$total = 0;
$fail  = 0;

function ck( $label, $actual, $expected ) {
	global $total, $fail;
	++$total;

	if ( $actual === $expected ) {
		echo "ok   $label\n";

		return;
	}

	++$fail;
	echo "FAIL $label\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
}

/**
 * A captured answer from bin/fixtures, as `wp_remote_get()` would hand it over.
 *
 * @param string $name The fixture file.
 * @param int    $code The HTTP status to answer with.
 * @return array
 */
function answer( $name, $code = 200 ) {
	$captured = json_decode( file_get_contents( __DIR__ . '/fixtures/' . $name ), true );

	return array( 'response' => array( 'code' => $code ), 'body' => json_encode( $captured['response'] ) );
}

const COURSES   = 'https://learn.wordpress.org/wp-json/wp/v2/courses?slug=wordpress-credits&_fields=id,slug,status,link,title';
const STRUCTURE = 'https://learn.wordpress.org/wp-json/sensei-internal/v1/course-structure/403425';

echo "=== The link ===\n";

ck( 'a course link gives its slug', WPCPM_Learn::slug( 'https://learn.wordpress.org/course/wordpress-credits/' ), 'wordpress-credits' );
ck( 'with or without the trailing slash', WPCPM_Learn::slug( 'https://learn.wordpress.org/course/wordpress-credits' ), 'wordpress-credits' );
ck( 'a lesson link is not a course', WPCPM_Learn::slug( 'https://learn.wordpress.org/lesson/join-global-slack/' ), '' );
ck( 'nor another host, nor http', array( WPCPM_Learn::slug( 'https://example.test/course/x/' ), WPCPM_Learn::slug( 'http://learn.wordpress.org/course/x/' ) ), array( '', '' ) );

echo "\n=== Resolving a course ===\n";

$refused = WPCPM_Learn::resolve( 'https://learn.wordpress.org/lesson/join-global-slack/' );

ck( 'a link that is not a course link is refused without asking Learn',
    array( is_wp_error( $refused ) ? $refused->get_error_code() : null, $GLOBALS['asked'] ), array( 'wpcpm_learn_not_a_course', array() ) );

// The slug's repeat is capped at 120 characters, so a pasted address of any length is matched in
// one pass rather than backtracked over (the final review of T3c).
$overlong = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/' . str_repeat( 'a', 121 ) . '/' );

ck( 'nor is a name longer than any course has, and Learn is not asked about it either',
    array( is_wp_error( $overlong ) ? $overlong->get_error_code() : null, WPCPM_Learn::slug( 'https://learn.wordpress.org/course/' . str_repeat( 'a', 120 ) . '/' ), $GLOBALS['asked'] ),
    array( 'wpcpm_learn_not_a_course', str_repeat( 'a', 120 ), array() ) );

$GLOBALS['http'][ COURSES ] = answer( 'learn-courses-wordpress-credits.json' );
$course = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' );

ck( 'a course link resolves to the course, asked by slug with only the fields read',
    array( $course, $GLOBALS['asked'] ),
    array( array( 'id' => 297853, 'slug' => 'wordpress-credits', 'title' => 'WordPress Credits' ), array( COURSES ) ) );
ck( 'and the answer is kept for a day, under the slug',
    array( $GLOBALS['transients']['wpcpm_learn_course_wordpress-credits'], $GLOBALS['ttl']['wpcpm_learn_course_wordpress-credits'] ),
    array( $course, 86400 ) );

$GLOBALS['asked'] = array();
$again            = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits' );

ck( 'a second resolve within the day asks Learn nothing', array( $again, $GLOBALS['asked'] ), array( $course, array() ) );

$GLOBALS['transients'] = array();
$GLOBALS['http'][ COURSES ] = array( 'response' => array( 'code' => 200 ), 'body' => '[]' );
$none = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' );

ck( 'no course at that address is said so, and nothing is cached',
    array( is_wp_error( $none ) ? $none->get_error_code() : null, $GLOBALS['transients'] ), array( 'wpcpm_learn_no_course', array() ) );

unset( $GLOBALS['http'][ COURSES ] );
$down = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' );

// The whole sentence, not a fragment: these messages are joined into one notice beside others, so
// each ends where a sentence ends (the final review of T3c).
ck( 'Learn not answering is said so, with what went wrong, and nothing is cached',
    array( is_wp_error( $down ) ? $down->get_error_code() : null, $down->get_error_message(), $GLOBALS['transients'] ),
    array( 'wpcpm_learn_unreachable', 'Learn WordPress did not answer (cURL error 28: Connection timed out).', array() ) );

$GLOBALS['http'][ COURSES ] = array( 'response' => array( 'code' => 500 ), 'body' => 'Internal Server Error' );
$broken = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' );

ck( 'a broken answer is unreachable too', is_wp_error( $broken ) ? $broken->get_error_code() : null, 'wpcpm_learn_unreachable' );

$GLOBALS['http'][ COURSES ] = array( 'response' => array( 'code' => 200 ), 'body' => '[{"id":"x"}]' );
$odd = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' );

ck( 'and an answer without an id and a title is a bad answer', is_wp_error( $odd ) ? $odd->get_error_code() : null, 'wpcpm_learn_bad_answer' );

// A 200 carrying something that is not JSON at all is Learn answering something else, which is
// what the parsers already have a word for; it is not Learn being unreachable (the final review
// of T3c), and a person told the site cannot reach Learn would go looking in the wrong place.
$GLOBALS['http'][ COURSES ] = array( 'response' => array( 'code' => 200 ), 'body' => '<!DOCTYPE html><title>Maintenance</title>' );
$undecodable = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' );

ck( 'a 200 whose body does not decode is a bad answer, not an outage, and nothing is cached',
    array( is_wp_error( $undecodable ) ? $undecodable->get_error_code() : null, $undecodable->get_error_message(), $GLOBALS['transients'] ),
    array( 'wpcpm_learn_bad_answer', 'Learn answered something that is not a list of courses.', array() ) );

$GLOBALS['http'][ COURSES ] = array( 'response' => array( 'code' => 200 ), 'body' => '[{"id":7,"slug":"wordpress-credits","title":{"rendered":"Design &amp; Build &#8217;26"}}]' );

ck( 'a title comes back as words, its entities decoded', WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' )['title'], "Design & Build \u{2019}26" );

echo "\n=== The course's modules and lessons ===\n";

$GLOBALS['transients'] = array();
$GLOBALS['asked']      = array();
$GLOBALS['http'][ STRUCTURE ] = answer( 'learn-structure-403425.json' );
$modules = WPCPM_Learn::structure( 403425 );

ck( 'the Designer course has its three modules, in order, with their lessons counted',
    array( $GLOBALS['asked'], array_map( function ( $m ) { return array( $m['id'], $m['title'], count( $m['lessons'] ) ); }, $modules ) ),
    array( array( STRUCTURE ), array( array( 115363, 'Onboarding', 13 ), array( 115364, 'Project', 23 ), array( 115365, 'Wrap-up', 3 ) ) ) );
ck( 'each lesson is its id and its title, nothing else',
    array( $modules[0]['lessons'][0], array_keys( $modules[2]['lessons'][2] ) ),
    array( array( 'id' => 403435, 'title' => 'Welcome and Essential Communication Guidelines' ), array( 'id', 'title' ) ) );
ck( 'and the answer is kept for a day, under the course id',
    array( $GLOBALS['transients']['wpcpm_learn_structure_403425'] === $modules, $GLOBALS['ttl']['wpcpm_learn_structure_403425'] ), array( true, 86400 ) );

$GLOBALS['asked'] = array();
WPCPM_Learn::structure( 403425 );

ck( 'a second read within the day asks Learn nothing', $GLOBALS['asked'], array() );

// A lesson still in draft on Learn is invisible to a student, so it is not offered; and a lesson
// Learn lists outside any module keeps its place under a module with no name.
$captured = json_decode( file_get_contents( __DIR__ . '/fixtures/learn-structure-403425.json' ), true )['response'];
$captured[0]['lessons'][1]['draft'] = true;
$captured[] = array( 'type' => 'lesson', 'id' => 900001, 'title' => 'A lesson on its own', 'draft' => false );
$GLOBALS['transients'] = array();
$GLOBALS['http'][ STRUCTURE ] = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $captured ) );
$modules = WPCPM_Learn::structure( 403425 );

ck( 'a draft lesson is left out, and a lesson outside every module is kept under a module with no name',
    array( count( $modules[0]['lessons'] ), $modules[0]['lessons'][1]['id'], end( $modules ) ),
    array( 12, 403439, array( 'id' => 0, 'title' => '', 'lessons' => array( array( 'id' => 900001, 'title' => 'A lesson on its own' ) ) ) ) );

$GLOBALS['transients'] = array();
unset( $GLOBALS['http'][ STRUCTURE ] );
$down = WPCPM_Learn::structure( 403425 );

ck( 'Learn not answering for the structure is said so, and nothing is cached',
    array( is_wp_error( $down ) ? $down->get_error_code() : null, $GLOBALS['transients'] ), array( 'wpcpm_learn_unreachable', array() ) );

$GLOBALS['http'][ STRUCTURE ] = array( 'response' => array( 'code' => 200 ), 'body' => '{"code":"rest_no_route"}' );

ck( 'an answer that is not a list is a bad answer', WPCPM_Learn::structure( 403425 )->get_error_code(), 'wpcpm_learn_bad_answer' );

$GLOBALS['http'][ STRUCTURE ] = array( 'response' => array( 'code' => 200 ), 'body' => '[]' );

ck( 'a course with no modules is an empty list, not an error', WPCPM_Learn::structure( 403425 ), array() );

echo "\n=== Forgetting, and the groups ===\n";

$GLOBALS['transients'] = array( 'wpcpm_learn_course_wordpress-credits' => array( 'id' => 297853 ), 'wpcpm_learn_structure_297853' => array(), 'wpcpm_learn_structure_403425' => array(), 'other' => 1 );
WPCPM_Learn::forget( 297853, 'https://learn.wordpress.org/course/wordpress-credits/' );

ck( 'forget drops the course by its link and the structure by its id, and nothing else',
    array_keys( $GLOBALS['transients'] ), array( 'wpcpm_learn_structure_403425', 'other' ) );

WPCPM_Learn::forget( 403425 );

ck( 'and drops the structure alone when no link is given', array_keys( $GLOBALS['transients'] ), array( 'other' ) );

ck( "a module's title names the form's group it is, once case and punctuation are folded",
    array( WPCPM_Learn::group_of( 'Onboarding' ), WPCPM_Learn::group_of( 'Project' ), WPCPM_Learn::group_of( 'Wrap-up' ), WPCPM_Learn::group_of( 'wrap up' ), WPCPM_Learn::group_of( 'Extras' ), WPCPM_Learn::group_of( '' ) ),
    array( 'onboarding', 'project', 'wrapup', 'wrapup', '', '' ) );

$by_group = WPCPM_Learn::by_group( $modules );

ck( 'the lessons by group leave out a module that is no group, and the hours group has none',
    array( array_keys( $by_group ), count( $by_group['onboarding'] ), count( $by_group['project'] ), count( $by_group['wrapup'] ) ),
    array( array( 'onboarding', 'project', 'wrapup' ), 12, 23, 3 ) );

ck( "a lesson's title is found by its id across the modules, and an unknown id has none",
    array( WPCPM_Learn::lesson_title( $modules, 403439 ), WPCPM_Learn::lesson_title( $modules, 900001 ), WPCPM_Learn::lesson_title( $modules, 1 ) ),
    array( 'Join global Slack', 'A lesson on its own', '' ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
