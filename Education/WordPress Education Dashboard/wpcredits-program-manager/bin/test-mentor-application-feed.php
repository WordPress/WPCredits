<?php
/**
 * The public feed of mentor application counts (1.122.31): `WPCPM_Mentor_Application_Feed`.
 *
 * Design of 16 October 2026, section 3, decision 10. What is pinned here and why:
 *
 * - **The route** (`GET /wp-json/wpcpm/v1/mentor-applications`) is public and reads no argument.
 * - **The months**: every calendar month from the first application to this one, on the site's
 *   clock (half past eleven at night UTC on the last day of a month is the next month on a site two
 *   hours ahead), each with received, selected, activated, declined and lapsed, a month with
 *   nothing a month of zeros, between two others or this one; a spam row counts for nothing; a
 *   withdrawal counts as lapsed; what deleted rows counted is in its months still. A month's figure
 *   is the applications that reached the step that month (1.122.33), so an application selected
 *   twice in a month counts once there and again in a later month it is selected in, and an event
 *   that is no decision (the Airtable row written, refused or settled, an applicant filed as already
 *   a mentor, an acknowledgement) is never counted. A count in a
 *   corrupted option is left out when it is not a row of counts, or sits under a key that is no
 *   month of the year or comes after this month, and the walk still runs from the first real month
 *   to this one. With no application, the months are an empty object.
 * - **Today**: how many applications are open, and how many are selected and waiting for the course.
 * - **Counts, never people**: rows whose every answer, reference and note holds a sentinel, and none
 *   of it reaches the answer, whose keys are the three above and nothing else.
 * - **Cached and metered**: fifteen minutes, read back without a rebuild; the sixty-first request
 *   in an hour from one address is refused with 429 before anything is read, another address is
 *   not; and the answer says how long a cache may keep it.
 *
 * Run from the plugin root:  php bin/test-mentor-application-feed.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['routes']   = array();
$GLOBALS['settings'] = array();

class WP_REST_Request {}

class WP_REST_Response {
	public $data, $status, $headers = array();
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
	public function header( $key, $value ) {
		$this->headers[ $key ] = $value;
	}
}

function register_rest_route( $namespace, $route, $args = array() ) {
	$GLOBALS['routes'][ $namespace ][ $route ] = $args;
	return true;
}

/** The one setting the address reading consults. */
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-feed.php';

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

/** A clean site, its clock on 16 March 2027, two hours ahead of UTC. */
function fresh() {
	$GLOBALS['opts']        = array();
	$GLOBALS['transients']  = array();
	$GLOBALS['posts']       = array();
	$GLOBALS['pmeta']       = array();
	$GLOBALS['next_id']     = 500;
	$GLOBALS['site_offset'] = 2 * HOUR_IN_SECONDS;
	$GLOBALS['now']         = gmmktime( 12, 0, 0, 3, 16, 2027 );
	$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
}

/** A row that arrived at a moment, with what then happened to it, each `array( event, state, at )`. */
function a_row( $arrived, $state, array $events = array(), array $answers = array() ) {
	$GLOBALS['now'] = $arrived;
	$id             = WPCPM_Mentor_Application::store(
		array_merge(
			array(
				'name'    => 'Ada Okonjo',
				'profile' => 'ada-example',
				'email'   => 'ada@example.test',
			),
			$answers
		),
		in_array( $state, array( 'new', 'held', 'spam' ), true ) ? $state : 'new',
		array(),
		array()
	);

	foreach ( $events as $event ) {
		WPCPM_Mentor_Application::decide( $id, $event[1], $event[0], 7, isset( $event[3] ) ? $event[3] : '', $event[2] );
	}

	$GLOBALS['now'] = gmmktime( 12, 0, 0, 3, 16, 2027 );

	return $id;
}

/** One month's five counts. */
function month( $received, $selected = 0, $activated = 0, $declined = 0, $lapsed = 0 ) {
	return array(
		'received'  => $received,
		'selected'  => $selected,
		'activated' => $activated,
		'declined'  => $declined,
		'lapsed'    => $lapsed,
	);
}

echo "=== The route ===\n";

fresh();
WPCPM_Mentor_Application_Feed::register_route();
$route = isset( $GLOBALS['routes']['wpcpm/v1']['/mentor-applications'] ) ? $GLOBALS['routes']['wpcpm/v1']['/mentor-applications'] : array();
ck( 'the feed is GET /wp-json/wpcpm/v1/mentor-applications, public, reading no argument', array( $route['methods'] ?? null, $route['permission_callback'] ?? null, $route['callback'] ?? null, isset( $route['args'] ) ), array( 'GET', '__return_true', array( 'WPCPM_Mentor_Application_Feed', 'rest_counts' ), false ) );

echo "\n=== The months and today ===\n";

fresh();
$empty = WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] );
ck( 'with no application the months are an empty object, and today is two zeros', array( wp_json_encode( $empty['months'] ), $empty['today'] ), array( '{}', array( 'open' => 0, 'selected' => 0 ) ) );
ck( 'and today() alone says the same, two zeros', WPCPM_Mentor_Application_Feed::today(), array( 'open' => 0, 'selected' => 0 ) );

fresh();
// November: one received and declined in December. December: one received, selected in January
// (on the site's clock: 23:30 UTC on December 31 is January there), withdrawn in February. February:
// one spam, and one received and still waiting. March: one selected and waiting for the course.
a_row( gmmktime( 9, 0, 0, 11, 20, 2026 ), 'declined', array( array( 'declined', 'declined', gmmktime( 9, 0, 0, 12, 3, 2026 ) ) ) );
a_row( gmmktime( 9, 0, 0, 12, 10, 2026 ), 'lapsed', array( array( 'selected', 'selected', gmmktime( 23, 30, 0, 12, 31, 2026 ) ), array( 'withdrawn', 'lapsed', gmmktime( 9, 0, 0, 2, 4, 2027 ) ) ) );
a_row( gmmktime( 9, 0, 0, 2, 11, 2027 ), 'spam' );
a_row( gmmktime( 9, 0, 0, 2, 12, 2027 ), 'held' );
a_row( gmmktime( 9, 0, 0, 3, 1, 2027 ), 'selected', array( array( 'selected', 'selected', gmmktime( 9, 0, 0, 3, 8, 2027 ) ) ) );
$built = WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] );
ck(
	'every month from the first application to this one, each with its five counts, an empty month zeros, a spam row counted nowhere, a withdrawal as lapsed',
	$built['months'],
	array(
		'2026-11' => month( 1 ),
		'2026-12' => month( 1, 0, 0, 1 ),
		'2027-01' => month( 0, 1 ),
		'2027-02' => month( 1, 0, 0, 0, 1 ),
		'2027-03' => month( 1, 1 ),
	)
);
ck( 'today: one open, the held one, and one selected and waiting for the course', $built['today'], array( 'open' => 1, 'selected' => 1 ) );
ck( 'today() gives those two numbers on their own, the open count first, and build() takes its today from it, so the answer is the same', array( WPCPM_Mentor_Application_Feed::today(), WPCPM_Mentor_Application_Feed::today() === $built['today'], array_keys( $built ) ), array( array( 'open' => 1, 'selected' => 1 ), true, array( 'months', 'today', 'generated' ) ) );
$today_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-feed.php' );
preg_match( '/public static function today\(\) \{\n(.*?)\n\t\}\n/s', $today_source, $today_body );
ck( 'and today() counts the posts by their state and walks no history: it names neither the months nor an application\'s events', array( isset( $today_body[1] ), false !== strpos( isset( $today_body[1] ) ? $today_body[1] : '', 'waiting_count()' ), false !== strpos( isset( $today_body[1] ) ? $today_body[1] : '', 'months' ), false !== strpos( isset( $today_body[1] ) ? $today_body[1] : '', 'events' ), false !== strpos( isset( $today_body[1] ) ? $today_body[1] : '', 'kept_counts' ), false !== strpos( isset( $today_body[1] ) ? $today_body[1] : '', 'month_counts_of' ) ), array( true, true, false, false, false, false ) );
ck( 'and when it was built', $built['generated'], '2027-03-16T12:00:00+00:00' );

$declined = WPCPM_Mentor_Application::applications( array( 'declined' ) );
WPCPM_Mentor_Application::forget( $declined[0], 365, 0 );
ck( 'a row retention deleted still counts in its months', WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] )['months'], $built['months'] );

fresh();
// An application counts once per figure in a month (1.122.33), as the design words the feed: the
// applications that received, selected, activated, declined or lapsed, never the events. One selected
// twice in January is one selection there; the other, selected in January and again in March, is one
// in each month; one that was withdrawn and then lapsed in the same month is one lapse.
a_row( gmmktime( 9, 0, 0, 1, 4, 2027 ), 'selected', array( array( 'selected', 'selected', gmmktime( 9, 0, 0, 1, 5, 2027 ) ), array( 'withdrawn', 'lapsed', gmmktime( 9, 0, 0, 1, 6, 2027 ) ), array( 'selected', 'selected', gmmktime( 9, 0, 0, 1, 12, 2027 ) ) ) );
a_row( gmmktime( 9, 0, 0, 1, 6, 2027 ), 'lapsed', array( array( 'selected', 'selected', gmmktime( 9, 0, 0, 1, 8, 2027 ) ), array( 'withdrawn', 'lapsed', gmmktime( 9, 0, 0, 2, 3, 2027 ) ), array( 'lapsed', 'lapsed', gmmktime( 9, 0, 0, 2, 10, 2027 ) ), array( 'selected', 'selected', gmmktime( 9, 0, 0, 3, 2, 2027 ) ) ) );
ck(
	'a month\'s figure is the applications that reached the step that month: two applications, one selected twice, are two selections',
	WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] )['months'],
	array(
		'2027-01' => month( 2, 2, 0, 0, 1 ),
		'2027-02' => month( 0, 0, 0, 0, 1 ),
		'2027-03' => month( 0, 1 ),
	)
);

fresh();
// What a row holds that is no decision is no figure (ruling R2): the Airtable row refused, written and
// settled, the applicant filed as already a mentor, the acknowledgement. Each falls in a month that
// holds no decision of the same row, so counting any of them as a figure would show; an activation is
// one activation in its month, however many entries surround it.
a_row(
	gmmktime( 9, 0, 0, 1, 28, 2027 ),
	'active',
	array(
		array( 'selected', 'selected', gmmktime( 9, 0, 0, 1, 29, 2027 ) ),
		array( 'activated', 'active', gmmktime( 9, 0, 0, 1, 30, 2027 ) ),
		array( 'activated', 'active', gmmktime( 9, 0, 0, 1, 31, 2027 ) ),
		array( 'Airtable row refused', 'active', gmmktime( 9, 0, 0, 2, 2, 2027 ) ),
		array( 'Airtable row written', 'active', gmmktime( 9, 0, 0, 2, 5, 2027 ) ),
		array( 'Airtable row settled', 'active', gmmktime( 9, 0, 0, 3, 6, 2027 ) ),
		array( 'acknowledgement sent', 'active', gmmktime( 9, 0, 0, 3, 7, 2027 ) ),
	)
);
a_row( gmmktime( 9, 0, 0, 2, 9, 2027 ), 'active', array( array( 'already a mentor', 'active', gmmktime( 9, 0, 0, 2, 10, 2027 ) ) ) );
ck(
	'the Airtable row refused, written or settled, the acknowledgement and an applicant filed as already a mentor are no figure, and an activation counts once',
	WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] )['months'],
	array(
		'2027-01' => month( 1, 1, 1 ),
		'2027-02' => month( 1 ),
		'2027-03' => month( 0 ),
	)
);

fresh();
// Nothing happened in December, January or March: the rows arrived in November and February, and
// this month is March.
a_row( gmmktime( 9, 0, 0, 11, 20, 2026 ), 'new' );
a_row( gmmktime( 9, 0, 0, 2, 11, 2027 ), 'new' );
ck(
	'a month with nothing in it, between two others or this one, is a month of zeros',
	WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] )['months'],
	array(
		'2026-11' => month( 1 ),
		'2026-12' => month( 0 ),
		'2027-01' => month( 0 ),
		'2027-02' => month( 1 ),
		'2027-03' => month( 0 ),
	)
);

fresh();
// Half past eleven at night UTC on the last day of March is already the first of April on this
// site's clock, two hours ahead: the months end at April, as the site's own calendar would.
a_row( gmmktime( 9, 0, 0, 3, 5, 2027 ), 'new' );
ck(
	'half past eleven at night UTC on the last day of a month is the next month on a site two hours ahead, and the months end there',
	WPCPM_Mentor_Application_Feed::build( gmmktime( 23, 30, 0, 3, 31, 2027 ) )['months'],
	array(
		'2027-03' => month( 1 ),
		'2027-04' => month( 0 ),
	)
);

fresh();
// What a corrupted or hand-edited option of kept counts can hold: keys that look like a month and
// are not one (a month 00 and a month 13, which read as a neighboring month if they were let in),
// keys after this month (the next one, and the last a four-digit year allows), and a number where a
// row of counts belongs, in the month a live row counts in. None is a month of the answer, and the
// walk still runs from the first real month to this one.
a_row( gmmktime( 9, 0, 0, 1, 12, 2027 ), 'new' );
$GLOBALS['opts'][ WPCPM_Mentor_Application::OPT_KEPT ] = array(
	'2025-13' => month( 7 ),
	'2026-00' => month( 7 ),
	'2026-13' => month( 7 ),
	'2027-01' => 5,
	'2027-02' => month( 2 ),
	'2027-04' => month( 7 ),
	'2027-13' => month( 7 ),
	'9999-12' => month( 7 ),
);
ck(
	'a kept count that is no row of counts, or sits under a key that is no month of the year or comes after this month, is left out, and the walk runs from the first real month to this one',
	WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] )['months'],
	array(
		'2027-01' => month( 1 ),
		'2027-02' => month( 2 ),
		'2027-03' => month( 0 ),
	)
);

echo "\n=== Counts, never people ===\n";

fresh();
$sentinel = 'SENTINEL-<b>"&\'';
$answers  = array();
foreach ( array( 'name', 'profile', 'email', 'country', 'hours', 'anything' ) as $key ) {
	$answers[ $key ] = $sentinel . $key;
}
$answers['languages'] = array( $sentinel . 'language' );
$planted              = a_row( gmmktime( 9, 0, 0, 3, 2, 2027 ), 'second', array( array( 'second opinion', 'second', gmmktime( 9, 0, 0, 3, 3, 2027 ), $sentinel . 'note' ) ), $answers );
update_post_meta( $planted, WPCPM_Mentor_Application::META_REFERENCE, $sentinel . 'reference' );
$stored = wp_json_encode( WPCPM_Mentor_Application::fields_of( get_post( $planted ) ) ) . wp_json_encode( WPCPM_Mentor_Application::events( $planted ) );
$answer = wp_json_encode( WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] ) );
ck( 'every sentinel is planted where a person\'s words are kept, so the walk cannot pass by planting nothing', substr_count( $stored, 'SENTINEL' ), 8 );
ck( 'and none of them, nor anything of the reference, reaches the answer', array( substr_count( $answer, 'SENTINEL' ), substr_count( $answer, 'MAPP-' ), substr_count( $answer, '@' ) ), array( 0, 0, 0 ) );
ck( 'whose keys are the months, today and when it was built, and nothing else', array_keys( WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] ) ), array( 'months', 'today', 'generated' ) );

echo "\n=== Cached and metered ===\n";

fresh();
a_row( gmmktime( 9, 0, 0, 3, 1, 2027 ), 'new' );
$first = WPCPM_Mentor_Application_Feed::rest_counts( new WP_REST_Request() );
ck( 'an answer is built, kept fifteen minutes, and says a cache may keep it as long', array( $first->status, $first->data['today']['open'], get_transient( WPCPM_Mentor_Application_Feed::TRANSIENT ) === $first->data, $first->headers ), array( 200, 1, true, array( 'Cache-Control' => 'public, max-age=900' ) ) );
set_transient( WPCPM_Mentor_Application_Feed::TRANSIENT, array( 'kept' => 'sentinel' ) );
ck( 'and read back without a rebuild while it is kept', WPCPM_Mentor_Application_Feed::rest_counts( new WP_REST_Request() )->data, array( 'kept' => 'sentinel' ) );

fresh();
for ( $i = 0; $i < WPCPM_Mentor_Application_Feed::PER_HOUR; $i++ ) {
	WPCPM_Mentor_Application_Feed::rest_counts( new WP_REST_Request() );
}
delete_transient( WPCPM_Mentor_Application_Feed::TRANSIENT );
$refused = WPCPM_Mentor_Application_Feed::rest_counts( new WP_REST_Request() );
ck( 'the sixty-first request from one address in an hour is refused with 429, before anything is read', array( $refused->status, $refused->data['code'] ?? null, get_transient( WPCPM_Mentor_Application_Feed::TRANSIENT ) ), array( 429, 'wpcpm_feed_busy', false ) );
$_SERVER['REMOTE_ADDR'] = '198.51.100.20';
ck( 'and another address is answered', WPCPM_Mentor_Application_Feed::rest_counts( new WP_REST_Request() )->status, 200 );
ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
