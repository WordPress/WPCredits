<?php
/**
 * Does the cohort timeline count the right students in the right months, and say the truth about
 * the mentors' places?
 *
 * Pins the rules of `WPCPM_Cohort_Timeline` (design of 10 November 2026): who is on the timeline and
 * as what, the window a row with no end date is given, the per-month load, starts, finishes and
 * students without a mentor, the state of each month against the places, the half-year bands and
 * the six-month summary the card reads.
 *
 * Run from the plugin root:  php bin/test-cohort-timeline.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['today'] = '2026-11-10';

function __( $s, $d = null ) { return $s; }
function wp_date( $f, $t = null ) { return 'Y-m-d' === $f ? $GLOBALS['today'] : gmdate( $f, null === $t ? time() : $t ); }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once __DIR__ . '/stubs/compiled-seeds.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roster-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-cohort-timeline.php';

WPCPM_Tracks::init();
wpcpm_seed_compiled_options( $GLOBALS['opts'] );

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

$active = array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Paused' );
$today  = $GLOBALS['today'];

/**
 * A roster row with the fields the timeline reads.
 */
function trow( $status, $start, $end = '', $mentor = true ) {
	return array(
		'record_id'  => 'rec' . substr( md5( $status . $start . $end . mt_rand() ), 0, 14 ),
		'status'     => $status,
		'start'      => $start,
		'end'        => $end,
		'has_mentor' => $mentor,
		'reports'    => array(),
	);
}

// ---------------------------------------------------------------------------------------------
// Dates.
// ---------------------------------------------------------------------------------------------

ck( 'add_months clamps the day: 31 January plus one month is the last of February', WPCPM_Cohort_Timeline::add_months( '2027-01-31', 1 ), '2027-02-28' );
ck( 'add_months crosses a year', WPCPM_Cohort_Timeline::add_months( '2026-11-15', 3 ), '2027-02-15' );
ck( 'add_months on a leap year', WPCPM_Cohort_Timeline::add_months( '2027-12-31', 2 ), '2028-02-29' );
ck( 'last_day of February 2027', WPCPM_Cohort_Timeline::last_day( '2027-02-10' ), '2027-02-28' );
ck( 'day refuses an impossible date', WPCPM_Cohort_Timeline::day( '2027-02-31' ), '' );
ck( 'day refuses a datetime', WPCPM_Cohort_Timeline::day( '2027-02-01T00:00:00' ), '' );
ck( 'day trims a stray space', WPCPM_Cohort_Timeline::day( ' 2027-02-01 ' ), '2027-02-01' );

// ---------------------------------------------------------------------------------------------
// Classification.
// ---------------------------------------------------------------------------------------------

$kind = static function ( $row ) use ( $active, $today ) {
	$s = WPCPM_Cohort_Timeline::classify_row( $row, $active, $today, 'School' );
	return null === $s ? null : $s['kind'];
};

ck( 'a tracked status started before today is continuing', $kind( trow( 'In Sensei', '2026-09-01', '2027-01-31' ) ), 'continuing' );
ck( 'a tracked status starting after today is starting', $kind( trow( 'Developer Track', '2027-02-01', '2027-06-30' ) ), 'starting' );
ck( 'a start of today is continuing, not starting', $kind( trow( 'In Sensei', $today, '2027-03-01' ) ), 'continuing' );
ck( 'Paused is still a mentor\'s student', $kind( trow( 'Paused', '2026-09-01', '2027-01-31' ) ), 'continuing' );
ck( 'no status is a registration in review', $kind( trow( '', '2027-02-01', '2027-06-30', false ) ), 'review' );
ck( 'Interested is a lead and left out', $kind( trow( 'Interested', '2027-02-01', '2027-06-30' ) ), null );
ck( 'SPAM is left out', $kind( trow( 'SPAM', '2027-02-01', '2027-06-30' ) ), null );
ck( 'Duplicated is left out', $kind( trow( 'Duplicated', '2027-02-01', '2027-06-30' ) ), null );
ck( 'a past status is left out', $kind( trow( 'Graduate', '2026-02-01', '2026-06-30' ) ), null );
ck( 'Not moving forward is left out', $kind( trow( 'Not moving forward', '2027-02-01', '2027-06-30' ) ), null );
ck( 'no usable start date is left out', $kind( trow( 'In Sensei', '', '2027-06-30' ) ), null );
ck( 'a review row whose window is over is left out', $kind( trow( '', '2025-02-01', '2025-06-30', false ) ), null );

$no_end = WPCPM_Cohort_Timeline::classify_row( trow( 'In Sensei', '2027-01-15' ), $active, $today );
ck( 'no end date: four months after the start, marked estimated', array( $no_end['end'], $no_end['estimated'] ), array( '2027-05-15', true ) );

$backwards = WPCPM_Cohort_Timeline::classify_row( trow( 'In Sensei', '2027-01-15', '2027-01-01' ), $active, $today );
ck( 'an end before the start is estimated the same way', array( $backwards['end'], $backwards['estimated'] ), array( '2027-05-15', true ) );

$overdue = WPCPM_Cohort_Timeline::classify_row( trow( 'In Sensei', '2026-03-01', '2026-08-31' ), $active, $today );
ck( 'a student in progress past their end is held to today and marked overdue', array( $overdue['kind'], $overdue['end'], $overdue['overdue'] ), array( 'continuing', $today, true ) );

$waiting = WPCPM_Cohort_Timeline::classify_row( trow( 'Developer Track', '2027-02-01', '2027-06-30', false ), $active, $today );
ck( 'a confirmed student with neither a mentor nor a report has no mentor', $waiting['mentor'], false );
ck( 'and carries the track key of the status', $waiting['track'], 'dev' );

$reg = WPCPM_Cohort_Timeline::classify_registration( array( 'start' => '2027-03-01', 'end' => '2027-07-31' ), $today, 'Uni' );
ck( 'a site registration is in review, with no mentor and no track', array( $reg['kind'], $reg['mentor'], $reg['track'], $reg['institution'] ), array( 'review', false, '', 'Uni' ) );
ck( 'a site registration with no start date is left out', WPCPM_Cohort_Timeline::classify_registration( array( 'end' => '2027-07-31' ), $today ), null );

// ---------------------------------------------------------------------------------------------
// The months.
// ---------------------------------------------------------------------------------------------

$students = array_values(
	array_filter(
		array(
			// Three in progress: two end in January, one in March.
			WPCPM_Cohort_Timeline::classify_row( trow( 'In Sensei', '2026-09-01', '2027-01-31' ), $active, $today, 'North' ),
			WPCPM_Cohort_Timeline::classify_row( trow( 'In Sensei', '2026-09-15', '2027-01-20' ), $active, $today, 'North' ),
			WPCPM_Cohort_Timeline::classify_row( trow( 'Designer Track', '2026-10-01', '2027-03-31', false ), $active, $today, 'South' ),
			// Four confirmed for February.
			WPCPM_Cohort_Timeline::classify_row( trow( 'Developer Track', '2027-02-01', '2027-06-30', false ), $active, $today, 'North' ),
			WPCPM_Cohort_Timeline::classify_row( trow( 'Developer Track', '2027-02-08', '2027-06-30', false ), $active, $today, 'North' ),
			WPCPM_Cohort_Timeline::classify_row( trow( 'In Sensei', '2027-02-15', '2027-06-30' ), $active, $today, 'South' ),
			WPCPM_Cohort_Timeline::classify_row( trow( 'In Sensei', '2027-02-20', '2027-06-30', false ), $active, $today, 'North' ),
			// Two in review for February, one for March.
			WPCPM_Cohort_Timeline::classify_registration( array( 'start' => '2027-02-01', 'end' => '2027-06-30' ), $today, 'East' ),
			WPCPM_Cohort_Timeline::classify_row( trow( '', '2027-02-01', '2027-05-31', false ), $active, $today, 'East' ),
			WPCPM_Cohort_Timeline::classify_registration( array( 'start' => '2027-03-01' ), $today, 'East' ),
			// Left out.
			WPCPM_Cohort_Timeline::classify_row( trow( 'Interested', '2027-02-01', '2027-06-30' ), $active, $today ),
		)
	)
);

ck( 'ten students reach compute()', count( $students ), 10 );

$out = WPCPM_Cohort_Timeline::compute( $students, 2, 3, $today );
$m   = array();
foreach ( $out['months'] as $month ) {
	$m[ $month['key'] ] = $month;
}

ck( 'twelve months, the current one first', array( count( $out['months'] ), $out['months'][0]['key'], $out['months'][11]['key'] ), array( 12, '2026-11', '2027-10' ) );
ck( 'places are mentors times the ratio, the same every month', array( $out['places'], $m['2027-06']['places'] ), array( 6, 6 ) );
ck( 'November: the three in progress', array( $m['2026-11']['load'], $m['2026-11']['total'] ), array( array( 'continuing' => 3, 'starting' => 0, 'review' => 0 ), 3 ) );
ck( 'November fits', $m['2026-11']['state'], 'ok' );
ck( 'January: two finish', $m['2027-01']['ending'], 2 );
ck( 'February: one still in progress, four confirmed new, two in review', $m['2027-02']['load'], array( 'continuing' => 1, 'starting' => 4, 'review' => 2 ) );
ck( 'March: the March registration joins them', $m['2027-03']['load'], array( 'continuing' => 1, 'starting' => 4, 'review' => 3 ) );
ck( 'February starts: four confirmed, two in review (the March registration starts in March)', $m['2027-02']['starts'], array( 'confirmed' => 4, 'review' => 2 ) );
ck( 'February: five confirmed fit six places, seven do not: tight', array( $m['2027-02']['confirmed'], $m['2027-02']['total'], $m['2027-02']['state'] ), array( 5, 7, 'tight' ) );
ck( 'February balances', array( $m['2027-02']['balance_confirmed'], $m['2027-02']['balance_total'] ), array( 1, -1 ) );
ck( 'February: without a mentor are the four confirmed with none plus the two in review', $m['2027-02']['unassigned'], 6 );
ck( 'February needs three mentors at three each for seven students', $m['2027-02']['needed'], 3 );
ck( 'February\'s intake by institution, largest first', $m['2027-02']['institutions'], array( 'North' => 3, 'East' => 2, 'South' => 1 ) );
ck( 'February\'s intake by track, the registrations under no track', $m['2027-02']['tracks'], array( 'dev' => 2, '150h' => 2, '' => 2 ) );
ck( 'March: the estimated registration counts, the designer finishes', array( $m['2027-03']['starts']['review'], $m['2027-03']['ending'] ), array( 1, 1 ) );
ck( 'the March registration with no end is held to the start of July', array( $m['2027-06']['load']['review'], $m['2027-07']['load']['review'] ), array( 2, 1 ) );
ck( 'August is empty', $m['2027-08']['total'], 0 );

ck( 'the bands follow the half-years: two months of H2 2026, six of H1 2027, four of H2 2027', $out['bands'], array( array( 'key' => '2026-H2', 'span' => 2 ), array( 'key' => '2027-H1', 'span' => 6 ), array( 'key' => '2027-H2', 'span' => 4 ) ) );

$s = $out['summary'];
ck( 'summary: six months ahead', $s['horizon'], 6 );
ck( 'summary: four confirmed starts and three in review in the next six months', array( $s['starting_confirmed'], $s['starting_review'] ), array( 4, 3 ) );
ck( 'summary: the peak is March with eight', array( $s['peak_month'], $s['peak_load'] ), array( '2027-03', 8 ) );
ck( 'summary: tight from February, never short', array( $s['state'], $s['first_tight'], $s['first_short'] ), array( 'tight', '2027-02', '' ) );
ck( 'summary: three mentors cover the peak of eight, one more than the two', array( $s['needed'], $s['recruit'], $s['recruit_confirmed'] ), array( 3, 1, 0 ) );
ck( 'summary: one estimated end date counted', $s['estimated'], 1 );

// With a single mentor, the confirmed load is short.
$short = WPCPM_Cohort_Timeline::compute( $students, 1, 3, $today );
ck( 'one mentor: February is short', $short['months'][3]['state'], 'short' );
ck( 'one mentor: the summary says short and the month', array( $short['summary']['state'], $short['summary']['first_short'] ), array( 'short', '2027-02' ) );
ck( 'one mentor: two more for the confirmed load, two for the whole', array( $short['summary']['recruit_confirmed'], $short['summary']['recruit'] ), array( 1, 2 ) );

// No mentors and no students: everything is zero and nothing divides by zero.
$empty = WPCPM_Cohort_Timeline::compute( array(), 0, 3, $today );
ck( 'empty: no places, no load, every month fits', array( $empty['places'], $empty['summary']['peak_load'], $empty['summary']['state'] ), array( 0, 0, 'ok' ) );

// The ratio is held to its bounds.
ck( 'a ratio of 0 is read as 1', WPCPM_Cohort_Timeline::compute( array(), 2, 0, $today )['places'], 2 );
ck( 'a ratio of 99 is read as 20', WPCPM_Cohort_Timeline::compute( array(), 1, 99, $today )['ratio'], 20 );

// A malformed student is ignored.
ck( 'a malformed student is ignored', WPCPM_Cohort_Timeline::compute( array( array( 'kind' => 'nope' ), 'x' ), 1, 3, $today )['months'][0]['total'], 0 );

// The state rule.
ck( 'state: equal to the places fits', WPCPM_Cohort_Timeline::state( 6, 6, 6 ), 'ok' );
ck( 'state: one over in review is tight', WPCPM_Cohort_Timeline::state( 6, 7, 6 ), 'tight' );
ck( 'state: one over confirmed is short', WPCPM_Cohort_Timeline::state( 7, 7, 6 ), 'short' );

// ---------------------------------------------------------------------------------------------
// The drawing: the card and the view (WPCPM_Cohort_Timeline_View).
// ---------------------------------------------------------------------------------------------

function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function admin_url( $p ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $k, $v, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }

class WPCPM_Administrators_Dashboard {
	const STYLE = 'wpcpm-administrator-dashboard';
	public static $url = 'https://example.test/administrator-dashboard/';
	public static function page_url() { return self::$url; }
}
class WPCPM_Administrators_Cards {
	const SHELL = array( 'prefix' => 'wpcpm-', 'section_class' => 'wpcpm-administrator__card', 'details_class' => 'wpcpm-administrator__disclosure', 'id_on' => 'section', 'title_id' => true );
	public static function tracks() { return array( 'dev' => 'Developer Track', '150h' => '150-hour track' ); }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-card.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-cohort-timeline-view.php';

function draw( $fn ) {
	ob_start();
	$fn();
	return (string) ob_get_clean();
}

ck( 'scale: a peak of 9 gets ticks of 5 to 10', WPCPM_Cohort_Timeline_View::scale( 9 ), array( 'top' => 10, 'ticks' => array( 0, 5, 10 ) ) );
ck( 'scale: a peak on a tick gets room above it', WPCPM_Cohort_Timeline_View::scale( 40 )['top'], 50 );
ck( 'scale: nothing at all still has a scale', WPCPM_Cohort_Timeline_View::scale( 0 )['top'], 2 );

ck( 'the view\'s address is the dashboard\'s with the argument', WPCPM_Cohort_Timeline_View::url(), 'https://example.test/administrator-dashboard/?wpcpm_view=cohort-timeline' );
WPCPM_Administrators_Dashboard::$url = '';
ck( 'no dashboard page, no address', WPCPM_Cohort_Timeline_View::url(), '' );
WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';

$_GET = array();
ck( 'not asked for without the argument', WPCPM_Cohort_Timeline_View::is_requested(), false );
$_GET = array( 'wpcpm_view' => 'Cohort-Timeline' );
ck( 'asked for with the argument, whatever its case', WPCPM_Cohort_Timeline_View::is_requested(), true );
$_GET = array( 'wpcpm_view' => array( 'cohort-timeline' ) );
ck( 'an array is not the argument', @WPCPM_Cohort_Timeline_View::is_requested(), false );
$_GET = array();

// A name holding markup is drawn as text.
$students[] = WPCPM_Cohort_Timeline::classify_registration( array( 'start' => '2027-02-03', 'end' => '2027-05-01' ), $today, '<b>Evil</b> & Co' );
$report     = WPCPM_Cohort_Timeline::compute( $students, 2, 3, $today );
$report['read'] = 0;

// Before the students sync has read any roster there is no forecast: the view says so and the card is
// not drawn, rather than twelve empty months read as covered (the release's review, 1.123.69).
$unread_view = draw( static function () use ( $report ) { WPCPM_Cohort_Timeline_View::render_view( $report ); } );
$unread_card = draw( static function () use ( $report ) { WPCPM_Cohort_Timeline_View::render_card( $report ); } );
ck( 'with no roster read yet the view draws no chart, no table and no outlook but the sentence that says the sync has not read the Students table, and the card is not drawn',
	array( substr_count( $unread_view, '<figure class="wpcpm-timeline"' ), substr_count( $unread_view, '<table>' ), false !== strpos( $unread_view, 'the students sync has not read the Students table' ), '' === trim( $unread_card ) ),
	array( 0, 0, true, true ) );

$report['read'] = strtotime( $today . ' 06:00:00 UTC' );

$view = draw( static function () use ( $report ) { WPCPM_Cohort_Timeline_View::render_view( $report ); } );
ck( 'the view has its title, chart, table and intakes', array( substr_count( $view, 'Cohort timeline</h2>' ), substr_count( $view, '<figure class="wpcpm-timeline"' ), substr_count( $view, '<table>' ), substr_count( $view, 'wpcpm-timeline-intakes__item' ) ), array( 1, 1, 1, 2 ) );
ck( 'twelve columns, each a tab stop with a name', array( substr_count( $view, 'class="wpcpm-timeline__month ' ), substr_count( $view, 'tabindex="0" aria-label="' ) ), array( 12, 12 ) );
ck( 'twelve rows in the month table', substr_count( $view, '<tr><th scope="row">' ), 12 );
ck( 'an institution\'s name is escaped', array( false !== strpos( $view, '<b>Evil</b>' ), false !== strpos( $view, '&lt;b&gt;Evil&lt;/b&gt; &amp; Co' ) ), array( false, true ) );
ck( 'the tracks are named, the unplaced under their own words', array( false !== strpos( $view, 'Developer Track <span>' ), false !== strpos( $view, 'Track not assigned yet <span>' ) ), array( true, true ) );
ck( 'the outlook says tight in a word, with its icon', array( false !== strpos( $view, 'wpcpm-timeline-outlook--tight' ), false !== strpos( $view, '<strong>Tight</strong>' ) ), array( true, true ) );
ck( 'a tight month carries its flag', substr_count( $view, 'wpcpm-timeline__flag' ) > 0, true );
ck( 'the notes link the setting', false !== strpos( $view, 'admin.php?page=wpcpm-settings&amp;tab=people#cohort-planning' ), true );
ck( 'the estimated end date is owned up to', false !== strpos( $view, 'have no end date and are counted for 4 months' ) || false !== strpos( $view, 'has no end date and is counted for 4 months' ), true );
ck( 'no style carries a comma for a decimal', preg_match( '/style="[^"]*\d,\d/', $view ), 0 );

$card = draw( static function () use ( $report ) { WPCPM_Cohort_Timeline_View::render_card( $report ); } );
ck( 'the card lands on its anchor, in the Administrator Dashboard\'s shell', false !== strpos( $card, '<section class="wpcpm-administrator__card" id="wpcpm-cohort-timeline">' ), true );
ck( 'the card counts the starts of the next six months', false !== strpos( $card, '<span class="wpcpm-group__count">8</span>' ), true );
ck( 'the card\'s phrase says the first tight month', false !== strpos( $card, 'Tight from February 2027 if the registrations in review are placed' ), true );
ck( 'the card is folded while nothing is short', false !== strpos( $card, ' open>' ), false );
ck( 'the card draws six columns and the way to the view', array( substr_count( $card, 'class="wpcpm-timeline__month ' ), substr_count( $card, '?wpcpm_view=cohort-timeline' ) ), array( 6, 2 ) );

$short_report = WPCPM_Cohort_Timeline::compute( $students, 1, 3, $today );
$short_report['read'] = $report['read'];
$short_card   = draw( static function () use ( $short_report ) { WPCPM_Cohort_Timeline_View::render_card( $short_report ); } );
ck( 'short: the card is drawn open and says how many and by when', array( false !== strpos( $short_card, ' open>' ), false !== strpos( $short_card, '1 more mentor needed by February 2027' ) ), array( true, true ) );

ck( 'an empty report draws no card', draw( static function () { WPCPM_Cohort_Timeline_View::render_card( array() ); } ), '' );

$none = WPCPM_Cohort_Timeline::compute( array(), 4, 3, $today );
$none['read'] = $report['read'];
ck( 'nothing ahead: the phrase says the mentors cover it', WPCPM_Cohort_Timeline_View::phrase( $none['summary'] ), 'The mentors cover the next six months' );
ck( 'nothing ahead: the view says no intakes yet', false !== strpos( draw( static function () use ( $none ) { WPCPM_Cohort_Timeline_View::render_view( $none ); } ), 'No students start in the next twelve months yet.' ), true );

echo "\n" . ( $fail ? "$fail FAILED" : 'ALL PASS' ) . "\n";
exit( $fail ? 1 : 0 );
