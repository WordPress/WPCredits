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
// `clock_shift` moves the real clock this stand-in reads when no moment is given, so a check can prove a drawing holds
// nothing from it (Task 18c).
function wp_date( $f, $t = null ) { return 'Y-m-d' === $f ? $GLOBALS['today'] : gmdate( $f, null === $t ? time() + (int) ( $GLOBALS['clock_shift'] ?? 0 ) : $t ); }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once __DIR__ . '/stubs/compiled-seeds.php';
require_once __DIR__ . '/stubs/parts-inventory.php';
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
function current_user_can( $cap ) { return true; }
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once __DIR__ . '/stubs/guide-jobs.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-cohort-timeline-view.php';
// The shared parts, which the view and the card are drawn on alone since their old branches left (Task 18b).
function is_admin() { return false; }
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-icons.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ui.php';

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
ck( 'the view has its title, chart, table and intakes', array( substr_count( $view, 'Cohort timeline</h2>' ), substr_count( $view, '<figure class="wpcpm-timeline"' ), substr_count( $view, '<table class="wpcpm-ui-table' ), substr_count( $view, 'wpcpm-timeline-intakes__item' ) ), array( 1, 1, 1, 2 ) );
// The head is the frame's, the Reference view's (1.123.70): the page's name as the dashboard hands it in,
// the view's in the serif, the lede and when the records were read; and no way back of its own, which
// named the Administrator Dashboard a third time (the owner, 11 November 2026: "Duplicated").
$named = draw( static function () use ( $report ) { WPCPM_Cohort_Timeline_View::render_view( $report, '<h1 class="wpcpm-head__eyebrow">Administrator Dashboard</h1>' ); } );
ck( 'the view opens with the frame\'s head, the page\'s name in it once when the dashboard passes it and not at all otherwise, and no link back',
	array(
		0 === strpos( $named, '<div class="wpcpm-timeline-view"><header class="wpcpm-head wpcpm-timeline-view__header"><div class="wpcpm-head__copy"><h1 class="wpcpm-head__eyebrow">Administrator Dashboard</h1><h2 class="wpcpm-head__title">Cohort timeline</h2><p class="wpcpm-head__lede">Who is on the program each month' ),
		1 === preg_match( '#<p class="wpcpm-head__facts">Read from the program records on [^<]+\.</p></div></header>#', $named ),
		array( substr_count( $named, 'Administrator Dashboard' ), substr_count( $view, 'Administrator Dashboard' ) ),
		array( substr_count( $named, 'wpcpm-timeline-view__back' ), substr_count( $named, '&larr;' ) ),
	),
	array( true, true, array( 1, 0 ), array( 0, 0 ) ) );
ck( 'twelve columns, each a tab stop with a name', array( substr_count( $view, 'class="wpcpm-timeline__month ' ), substr_count( $view, 'tabindex="0" aria-label="' ) ), array( 12, 12 ) );
ck( 'an institution\'s name is escaped', array( false !== strpos( $view, '<b>Evil</b>' ), false !== strpos( $view, '&lt;b&gt;Evil&lt;/b&gt; &amp; Co' ) ), array( false, true ) );
ck( 'the tracks are named, the unplaced under their own words', array( false !== strpos( $view, 'Developer Track <span>' ), false !== strpos( $view, 'Track not assigned yet <span>' ) ), array( true, true ) );
ck( 'a tight month carries its flag', substr_count( $view, 'wpcpm-timeline__flag' ) > 0, true );
ck( 'the notes link the setting', false !== strpos( $view, 'admin.php?page=wpcpm-settings&amp;tab=people#cohort-planning' ), true );
ck( 'the estimated end date is owned up to', false !== strpos( $view, 'have no end date and are counted for 4 months' ) || false !== strpos( $view, 'has no end date and is counted for 4 months' ), true );
ck( 'no style carries a comma for a decimal', preg_match( '/style="[^"]*\d,\d/', $view ), 0 );

$card = draw( static function () use ( $report ) { WPCPM_Cohort_Timeline_View::render_card( $report ); } );

// The help link (M3): one on the card, after its disclosure, to the cohort timeline job; none on a site
// with no published guide.

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public $ID = 0, $post_status = 'publish', $post_name = 'administrator-guide';
	}
}
$GLOBALS['wpcpm_guide_page'] = new WP_Post();
WPCPM_Guide_Jobs::forget();
$help_card                    = draw( static function () use ( $report ) { WPCPM_Cohort_Timeline_View::render_card( $report ); } );
unset( $GLOBALS['wpcpm_guide_page'] );
WPCPM_Guide_Jobs::forget();
ck( 'the Upcoming cohorts card carries one help link, after its disclosure, to the cohort job; a site with no guide draws none',
	array( substr_count( $help_card, 'class="wpcpm-job-link"' ), false !== strpos( $help_card, '</details><p class="wpcpm-job-link-row"><a class="wpcpm-job-link" href="https://example.test/administrator-guide/#read-the-cohort-timeline-do-we-need-more-mentors">How to do this<span class="screen-reader-text">: Upcoming cohorts</span></a></p>' ), substr_count( $card, 'class="wpcpm-job-link"' ) ),
	array( 1, true, 0 ) );
ck( 'the card lands on its anchor, in the Administrator Dashboard\'s shell', false !== strpos( $card, '<section class="wpcpm-administrator__card" id="wpcpm-cohort-timeline">' ), true );
ck( 'the card counts the starts of the next six months', false !== strpos( $card, '<span class="wpcpm-group__count">8</span>' ), true );
// Task 18f, fix round 2: the month and its year are whole in the head's phrase.
ck( 'the card\'s phrase says the first tight month, the month and its year whole', false !== strpos( $card, "Tight from February\u{00A0}2027 if the registrations in review are placed" ), true );
ck( 'the card is folded while nothing is short', false !== strpos( $card, ' open>' ), false );
ck( 'the card draws six columns and the way to the view', array( substr_count( $card, 'class="wpcpm-timeline__month ' ), substr_count( $card, '?wpcpm_view=cohort-timeline' ) ), array( 6, 2 ) );

$short_report = WPCPM_Cohort_Timeline::compute( $students, 1, 3, $today );
$short_report['read'] = $report['read'];
$short_card   = draw( static function () use ( $short_report ) { WPCPM_Cohort_Timeline_View::render_card( $short_report ); } );
ck( 'short: the card is drawn open and says how many and by when', array( false !== strpos( $short_card, ' open>' ), false !== strpos( $short_card, "1 more mentor needed by February\u{00A0}2027" ) ), array( true, true ) );


ck( 'an empty report draws no card', draw( static function () { WPCPM_Cohort_Timeline_View::render_card( array() ); } ), '' );

$none = WPCPM_Cohort_Timeline::compute( array(), 4, 3, $today );
$none['read'] = $report['read'];
ck( 'nothing ahead: the phrase says the mentors cover it', WPCPM_Cohort_Timeline_View::phrase( $none['summary'] ), 'The mentors cover the next six months' );
ck( 'nothing ahead: the view says no intakes yet', false !== strpos( draw( static function () use ( $none ) { WPCPM_Cohort_Timeline_View::render_view( $none ); } ), 'No students start in the next twelve months yet.' ), true );

function ct_has( $h, $n ) { return false !== strpos( (string) $h, (string) $n ); }

// ---------------------------------------------------------------------------------------------
// The card on the shared parts, behind the switch (UI shared parts, Task 15b).
// ---------------------------------------------------------------------------------------------

// Each state of the card that changes its markup, and the view, drawn with the switch off, with it on but no surface
// named, on a wp-admin tab, and on the Administrator Dashboard.
$ct_draw = static function () use ( $report, $short_report ) {
	$out = array(
		'card tight' => draw( static function () use ( $report ) { WPCPM_Cohort_Timeline_View::render_card( $report ); } ),
		'card short' => draw( static function () use ( $short_report ) { WPCPM_Cohort_Timeline_View::render_card( $short_report ); } ),
	);
	WPCPM_Administrators_Dashboard::$url = '';
	$out['card no view'] = draw( static function () use ( $report ) { WPCPM_Cohort_Timeline_View::render_card( $report ); } );
	WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';
	$out['view'] = draw( static function () use ( $report ) { WPCPM_Cohort_Timeline_View::render_view( $report, '<h1 class="wpcpm-head__eyebrow">Administrator Dashboard</h1>' ); } );
	return $out;
};
// The card and the view are drawn on the parts alone since their old branches left (Task 18b), and their off digests
// with them.
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$ct_on = $ct_draw();
parts_inventory_add( $ct_on );
WPCPM_UI::leave();
WPCPM_UI::set_live( null );


$ct_card = $ct_on['card tight'];
ck( 'on, the card\'s head is the card shell\'s, as every other card\'s: the parts\' card fold at the card\'s anchor, its title with its id, the phrase, the help slot and the chevron; folded while nothing is short, open when it is', array(
	ct_has( str_replace( "\u{00A0}", ' ', $ct_card ), '<section class="wpcpm-administrator__card wpcpm-ui-fold-card" id="wpcpm-cohort-timeline"><details class="wpcpm-ui-fold"><summary class="wpcpm-ui-fold__head"><h3 class="wpcpm-ui-fold__title" id="wpcpm-cohort-timeline-title">Upcoming cohorts</h3><span class="wpcpm-ui-fold__phrase">Tight from February 2027 if the registrations in review are placed</span><span class="wpcpm-ui-fold__end"><span class="wpcpm-ui-fold__help-slot" aria-hidden="true"></span></span>' ),
	ct_has( str_replace( "\u{00A0}", ' ', $ct_on['card short'] ), '<details class="wpcpm-ui-fold" open><summary class="wpcpm-ui-fold__head"><h3 class="wpcpm-ui-fold__title" id="wpcpm-cohort-timeline-title">Upcoming cohorts</h3><span class="wpcpm-ui-fold__phrase">1 more mentor needed by February 2027</span>' ),
	strpos( $ct_card . $ct_on['card short'], 'wpcpm-group' ),
), array( true, true, false ) );
$ct_foot = '<p class="wpcpm-ui-fold__foot"><a href="https://example.test/administrator-dashboard/?wpcpm_view=cohort-timeline">Open the cohort timeline</a></p></div></details>';
// Nothing after the fold (task V1, ruling 2): the foot is the card's one way out.
$ct_act  = '</section>';
ck( 'on, the card\'s body ends with the fold\'s foot in today\'s words, to today\'s address, with no hidden words as today, in place of today\'s own line, and nothing after the fold; with no view to open, no foot', array(
	ct_has( $ct_card, '</figure>' . $ct_foot . $ct_act ),
	ct_has( $ct_on['card short'], '</figure>' . $ct_foot . $ct_act ),
	strpos( $ct_card, 'wpcpm-administrator__more' ),
	ct_has( $ct_on['card no view'], '</figure></div></details></section>' ),
	strpos( $ct_on['card no view'], 'wpcpm_view=cohort-timeline' ),
), array( true, true, false, true, false ) );
// The four figures and the small chart: no part draws a tile or a chart, so they are today's, byte for byte.
$ct_body = static function ( $html ) { return preg_match( '#<ul class="wpcpm-timeline-stats.*</figure>#s', $html, $m ) ? $m[0] : null; };
// Drawn by the same printers the old card used (`render_stats()`, `render_chart()`); the old card left (Task 18b).
ck( 'on, the four figures and the small chart are drawn, in every state', array_map( static function ( $state ) use ( $ct_on, $ct_body ) { return null !== $ct_body( $ct_on[ $state ] ); }, array( 'card tight', 'card short', 'card no view' ) ), array_fill( 0, 3, true ) );

// ---------------------------------------------------------------------------------------------
// The view on the shared parts, behind the switch (UI shared parts, Task 17).
// ---------------------------------------------------------------------------------------------

// The view's other states, each drawn off, with the switch on but no surface named, on a wp-admin tab and on the
// Administrator Dashboard: short (the outlook and the months short), nothing ahead (no intake: the quiet line) and no
// roster read yet (the sentence alone).
$ct_unread         = $report;
$ct_unread['read'] = 0;
$ct_views          = static function () use ( $short_report, $none, $ct_unread ) {
	$eyebrow = '<h1 class="wpcpm-head__eyebrow">Administrator Dashboard</h1>';
	return array(
		'view short'  => draw( static function () use ( $short_report, $eyebrow ) { WPCPM_Cohort_Timeline_View::render_view( $short_report, $eyebrow ); } ),
		'view none'   => draw( static function () use ( $none, $eyebrow ) { WPCPM_Cohort_Timeline_View::render_view( $none, $eyebrow ); } ),
		'view unread' => draw( static function () use ( $ct_unread, $eyebrow ) { WPCPM_Cohort_Timeline_View::render_view( $ct_unread, $eyebrow ); } ),
	);
};
WPCPM_UI::set_live( array( 'administrator' ) );
WPCPM_UI::enter( 'administrator' );
$cv_on = $ct_views();
parts_inventory_add( $cv_on );
WPCPM_UI::leave();
WPCPM_UI::set_live( null );
$cv_on['view tight'] = $ct_on['view'];
// The layout harness's cases (bin/ui/layout/, the census of 19 Nov 2026): the view in each state on the Administrator
// Dashboard's parts, and the card as Watch draws it (outside its group, which the dashboard's suite draws without it).
if ( function_exists( 'wpcpm_layout_keep' ) ) {
	foreach ( array( 'view tight' => 'tight', 'view short' => 'short', 'view none' => 'nothing ahead', 'view unread' => 'no roster read yet' ) as $lk_key => $lk_name ) {
		wpcpm_layout_keep( 'Administrator Dashboard, Cohort timeline: ' . $lk_name, $cv_on[ $lk_key ], 'administrator/cohort-timeline' );
	}
	wpcpm_layout_keep( 'Administrator Dashboard, Watch: the Upcoming cohorts card, tight', $ct_on['card tight'], 'administrator/watch' );
	wpcpm_layout_keep( 'Administrator Dashboard, Watch: the Upcoming cohorts card, short', $ct_on['card short'], 'administrator/watch' );
}

// What is today's on the parts too: the frame's head, the four figures and the chart (no part draws a tile or a chart),
// the intakes (no part draws an intake's tile, its split bar or a chip with a count) and the notes' lines (sentences, one
// with a link inside it, which no part draws). Drawn by the same printers the old view used; the old view left with its
// branch (Task 18b), so each is pinned to where it stands.
$cv_cut = static function ( $html, $pattern ) { return preg_match( $pattern, $html, $m ) ? $m[0] : null; };
$cv_today = static function ( $state, $pattern ) use ( $cv_on, $cv_cut ) {
	return null !== $cv_cut( $cv_on[ $state ], $pattern );
};
ck( 'on, the frame\'s head, the four figures, the chart, the intakes and the notes\' lines are drawn where they stand, in every state that draws them', array(
	array_map( static function ( $s ) use ( $cv_today ) { return $cv_today( $s, '#^<div class="wpcpm-timeline-view"><header .*?</header>#s' ); }, array( 'view tight', 'view short', 'view none', 'view unread' ) ),
	array_map( static function ( $s ) use ( $cv_today ) { return $cv_today( $s, '#<ul class="wpcpm-timeline-stats">.*?</ul>#s' ); }, array( 'view tight', 'view short', 'view none' ) ),
	array_map( static function ( $s ) use ( $cv_today ) { return $cv_today( $s, '#<figure .*?</figure>#s' ); }, array( 'view tight', 'view short', 'view none' ) ),
	array_map( static function ( $s ) use ( $cv_today ) { return $cv_today( $s, '#<ol class="wpcpm-timeline-intakes">.*?</ol>#s' ); }, array( 'view tight', 'view short' ) ),
	array_map( static function ( $s ) use ( $cv_today ) { return $cv_today( $s, '#</h3><ul><li>.*?</ul></aside></div>$#s' ); }, array( 'view tight', 'view short', 'view none' ) ),
), array( array_fill( 0, 4, true ), array_fill( 0, 3, true ), array_fill( 0, 3, true ), array_fill( 0, 2, true ), array_fill( 0, 3, true ) ) );
// Pinned where they stand (Task 18c: the review of Task 18b found these sections pinned for presence only once the old
// view left): each section's bytes, in every state that draws it. Nothing in them comes from the real clock: the
// fixture's day is this suite's constant, read through the wp_date( 'Y-m-d' ) stand-in; every month is a key counted
// from it, its name drawn from the key; the read time is built from that day and printed through the date and time
// formats, which this suite leaves empty. Proven: the same states drawn with the stand-in's clock moved a day earlier,
// a day later and 400 days earlier give the same bytes.
$cv_sections = array(
	'head'    => '#^<div class="wpcpm-timeline-view"><header .*?</header>#s',
	'stats'   => '#<ul class="wpcpm-timeline-stats">.*?</ul>#s',
	'chart'   => '#<figure .*?</figure>#s',
	'intakes' => '#<ol class="wpcpm-timeline-intakes">.*?</ol>#s',
	'notes'   => '#</h3><ul><li>.*?</ul></aside></div>$#s',
);
$cv_digest = static function ( array $views ) use ( $cv_sections, $cv_cut ) {
	$out = array();
	foreach ( $cv_sections as $name => $pattern ) {
		foreach ( array( 'view tight', 'view short', 'view none', 'view unread' ) as $state ) {
			$cut = $cv_cut( $views[ $state ], $pattern );
			if ( null !== $cut ) {
				$out[ $name . ' ' . $state ] = md5( $cut );
			}
		}
	}
	return $out;
};
$cv_pins = array(
	'head view tight'     => '85c4c41ca04bb8ff457531857b249338',
	'head view short'     => '85c4c41ca04bb8ff457531857b249338',
	'head view none'      => '85c4c41ca04bb8ff457531857b249338',
	'head view unread'    => '47121837b1685b7343f07f3a1e10f252',
	'stats view tight'    => 'f426572e322b04b4ac50d6559d37f7a8',
	'stats view short'    => '13657bb44f8f1f3694dc9c2740a383f4',
	'stats view none'     => '89e708b9778d2bc2f46a198a6da3b379',
	'chart view tight'    => 'af50f83cca8f84d483968cc8f0a4126e',
	'chart view short'    => 'a217242a69b5e23c47dcb295bb963df2',
	'chart view none'     => '6343690a21a748341ea4b627205cb517',
	'intakes view tight'  => '95dff237cab11185712df66934ad76d3',
	'intakes view short'  => '95dff237cab11185712df66934ad76d3',
	'notes view tight'    => '61adbca31d58c82ddc6122f6fd7a80ad',
	'notes view short'    => '003420566b37d1bd948610a992255df0',
	'notes view none'     => '5d18a2b72ba9d84ca8a8288a97a60a33',
);
$cv_moved = array();
foreach ( array( -86400, 86400, -400 * 86400 ) as $shift ) {
	$GLOBALS['clock_shift'] = $shift;
	WPCPM_UI::set_live( array( 'administrator' ) );
	WPCPM_UI::enter( 'administrator' );
	$moved              = $ct_views();
	$moved['view tight'] = draw( static function () use ( $report ) { WPCPM_Cohort_Timeline_View::render_view( $report, '<h1 class="wpcpm-head__eyebrow">Administrator Dashboard</h1>' ); } );
	WPCPM_UI::leave();
	WPCPM_UI::set_live( null );
	$cv_moved[] = $cv_digest( $moved );
}
unset( $GLOBALS['clock_shift'] );
ck( 'on, the frame\'s head, the four figures, the chart, the intakes and the notes\' lines are pinned where they stand, in every state that draws them, and the real clock moved a day either way or 400 days back moves none of them', array( $cv_digest( $cv_on ), $cv_moved ), array( $cv_pins, array_fill( 0, 3, $cv_pins ) ) );

$cv_t = $cv_on['view tight'];
ck( 'on, the outlook is the state as the parts\' tag, its tone the state\'s (Short red, Tight amber, Covered green), then today\'s sentence word for word as the parts\' plain line, still a status for a screen reader; no box, no icon', array(
	ct_has( $cv_t, '</ul><div role="status"><span class="wpcpm-status wpcpm-status--amber">Tight</span><p class="wpcpm-ui-intro">The confirmed students fit. From February 2027 the registrations in review would need more places: 1 more mentor covers them.</p></div><section ' ),
	ct_has( $cv_on['view short'], '</ul><div role="status"><span class="wpcpm-status wpcpm-status--red">Short</span><p class="wpcpm-ui-intro">From February 2027 the confirmed students alone need more places than the mentors have.</p></div><section ' ),
	ct_has( $cv_on['view none'], '</ul><div role="status"><span class="wpcpm-status wpcpm-status--green">Covered</span><p class="wpcpm-ui-intro">The active mentors have places for every student in the next six months, the registrations in review included.</p></div><section ' ),
	strpos( implode( '', $cv_on ), 'wpcpm-timeline-outlook' ),
	// Today's outlook put its word in bold; the chart's tooltips keep their own bold month names.
	strpos( implode( '', $cv_on ), '<p><strong>' ),
), array( true, true, true, false, false ) );
ck( 'on, each section is today\'s named region, its heading through the heading part at today\'s level with today\'s id; the notes\' heading too, with no id as today', array(
	ct_has( $cv_t, '<section class="wpcpm-timeline-view__section" aria-labelledby="wpcpm-timeline-chart-title"><h3 class="wpcpm-ui-h" id="wpcpm-timeline-chart-title">Students on the program each month</h3><figure ' ),
	ct_has( $cv_t, '<section class="wpcpm-timeline-view__section" aria-labelledby="wpcpm-timeline-table-title"><h3 class="wpcpm-ui-h" id="wpcpm-timeline-table-title">Month by month</h3><table ' ),
	ct_has( $cv_t, '<section class="wpcpm-timeline-view__section" aria-labelledby="wpcpm-timeline-intakes-title"><h3 class="wpcpm-ui-h" id="wpcpm-timeline-intakes-title">Intakes</h3><ol class="wpcpm-timeline-intakes">' ),
	ct_has( $cv_t, '<aside class="wpcpm-timeline-notes"><h3 class="wpcpm-ui-h">How these numbers are made</h3><ul><li>' ),
	strpos( implode( '', $cv_on ), 'wpcpm-timeline-view__heading' ),
), array( true, true, true, true, false ) );

// The month table through the table part.
$cv_doc = static function ( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	return new DOMXPath( $doc );
};
$cv_x = $cv_doc( $cv_t );
ck( 'on, the month table is the table part, named by its heading as today\'s box was, with no box that scrolls (the part stacks on a phone): the month as each row\'s header, the counts as numbers, the outlook as a short cell; every value under its column\'s name for the phone', array(
	ct_has( $cv_t, '<h3 class="wpcpm-ui-h" id="wpcpm-timeline-table-title">Month by month</h3><table class="wpcpm-ui-table wpcpm-ui-table--wide wpcpm-ui-table--pairs-6" role="table" aria-labelledby="wpcpm-timeline-table-title"><thead role="rowgroup">' ),
	strpos( $cv_t, 'class="wpcpm-timeline-table"' ),
	array_map( static function ( $th ) { return $th->getAttribute( 'class' ) . ' ' . $th->textContent; }, iterator_to_array( $cv_x->query( '//table/thead//th' ) ) ),
	$cv_x->query( '//table/tbody/tr' )->length,
	array_map( static function ( $th ) { return $th->getAttribute( 'scope' ) . ' ' . $th->getAttribute( 'role' ) . ' ' . $th->getAttribute( 'class' ) . ' ' . $th->textContent; }, iterator_to_array( $cv_x->query( '//table/tbody/tr/th' ) ) ),
	array_values( array_unique( array_map( static function ( $td ) { return $td->getAttribute( 'data-label' ); }, iterator_to_array( $cv_x->query( '//table/tbody//td' ) ) ) ) ),
	$cv_x->query( '//table/tbody//td[not(@data-label)]' )->length,
), array(
	true,
	false,
	array( 'wpcpm-ui-cell--date Month', 'wpcpm-ui-cell--num Starting', 'wpcpm-ui-cell--num Finishing', 'wpcpm-ui-cell--num On the program', 'wpcpm-ui-cell--num Without a mentor', 'wpcpm-ui-cell--num Places', 'wpcpm-ui-cell--num Balance', 'wpcpm-ui-cell--short Outlook' ),
	12,
	array_map( static function ( $key ) { return 'row rowheader wpcpm-ui-cell--date wpcpm-ui-cell--rowhead ' . WPCPM_Cohort_Timeline_View::month_long( $key ); }, array_column( $report['months'], 'key' ) ),
	array( 'Starting', 'Finishing', 'On the program', 'Without a mentor', 'Places', 'Balance', 'Outlook' ),
	0,
) );
ck( 'on, a row: today\'s numbers, the registrations in review as the starting cell\'s second line, the balance in today\'s words with no colour, the outlook as the state\'s tag in its tone; no chip, no icon, no colour class of today\'s', array(
	ct_has( $cv_t, '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--date wpcpm-ui-cell--rowhead">February 2027</th><td class="wpcpm-ui-cell--num" role="cell" data-label="Starting">4 <span class="wpcpm-ui-cell__sub">+3 in review</span></td><td class="wpcpm-ui-cell--num" role="cell" data-label="Finishing">0</td><td class="wpcpm-ui-cell--num" role="cell" data-label="On the program">8</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Without a mentor">7</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Places">6</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Balance">2 short</td><td class="wpcpm-ui-cell--short wpcpm-ui-cell--joins" role="cell" data-label="Outlook"><span class="wpcpm-status wpcpm-status--amber">Tight</span></td></tr>' ),
	ct_has( $cv_t, '<tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--date wpcpm-ui-cell--rowhead">November 2026</th><td class="wpcpm-ui-cell--num" role="cell" data-label="Starting">0</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Finishing">0</td><td class="wpcpm-ui-cell--num" role="cell" data-label="On the program">3</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Without a mentor">1</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Places">6</td><td class="wpcpm-ui-cell--num" role="cell" data-label="Balance">3 free</td><td class="wpcpm-ui-cell--short wpcpm-ui-cell--joins" role="cell" data-label="Outlook"><span class="wpcpm-status wpcpm-status--green">Covered</span></td></tr>' ),
	ct_has( $cv_on['view short'], '<td class="wpcpm-ui-cell--short wpcpm-ui-cell--joins" role="cell" data-label="Outlook"><span class="wpcpm-status wpcpm-status--red">Short</span></td>' ),
	array_map( static function ( $s ) use ( $cv_doc ) { $x = $cv_doc( $s ); return array( $x->query( '//table/tbody/tr' )->length, $x->query( '//table//svg' )->length ); }, array( $cv_on['view short'], $cv_on['view none'] ) ),
	array( strpos( implode( '', $cv_on ), 'wpcpm-timeline-chip' ), strpos( implode( '', $cv_on ), 'wpcpm-timeline-table__' ), strpos( implode( '', $cv_on ), 'is-num' ) ),
), array( true, true, true, array( array( 12, 0 ), array( 12, 0 ) ), array( false, false, false ) ) );
ck( 'on, with nothing ahead the intakes say so in today\'s words as the parts\' muted note (design call 25), with no empty list before it; with no roster read the one sentence is the parts\' muted note too (there is no list), nothing else after the head', array(
	ct_has( $cv_on['view none'], '<h3 class="wpcpm-ui-h" id="wpcpm-timeline-intakes-title">Intakes</h3><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">No students start in the next twelve months yet.</p></section>' ),
	strpos( $cv_on['view none'], 'class="wpcpm-timeline-intakes' ),
	strpos( implode( '', $cv_on ), 'wpcpm-administrator__quiet' ),
	substr( $cv_on['view unread'], (int) strpos( $cv_on['view unread'], '</header>' ) ),
), array( true, false, false, '</header><p class="wpcpm-ui-intro wpcpm-ui-intro--muted">Nothing to show yet: the students sync has not read the Students table.</p></div>' ) );

// No part outside the switch: the dashboard's root prints `wpcpm-ui-on` around the view while it is on, as the
// Administrator Dashboard's suite's parts_outside_switch() walks it.
$cv_outside = static function ( $html ) use ( $cv_doc ) {
	$x       = $cv_doc( $html );
	$outside = array();
	$all     = 0;
	foreach ( $x->query( '//*[@class]' ) as $node ) {
		$part = array_filter( preg_split( '/\s+/', trim( (string) $node->getAttribute( 'class' ) ) ), static function ( $c ) { return 'wpcpm-ui-on' !== $c && 1 === preg_match( '/^wpcpm-(ui-|btn$|btn--|status$|status--)/', $c ); } );
		if ( empty( $part ) ) {
			continue;
		}
		++$all;
		$inside = false;
		for ( $up = $node->parentNode; $up instanceof DOMElement; $up = $up->parentNode ) {
			if ( in_array( 'wpcpm-ui-on', preg_split( '/\s+/', (string) $up->getAttribute( 'class' ) ), true ) ) {
				$inside = true;
				break;
			}
		}
		if ( ! $inside ) {
			$outside[] = $node->nodeName . '.' . implode( '.', $part );
		}
	}
	return array( array_values( array_unique( $outside ) ), $all > 0 );
};
ck( 'on, inside the dashboard\'s root every part of the view is under the switch\'s class', array_map( static function ( $html ) use ( $cv_outside ) { return $cv_outside( '<div class="wpcpm-dashboard wpcpm-administrator wpcpm-ui-on">' . $html . '</div>' ); }, $cv_on ), array_fill_keys( array_keys( $cv_on ), array( array(), true ) ) );

// The inventory (UI shared parts, Task 17b; bin/stubs/parts-inventory.php): every old class this suite's drawings on
// the Administrator Dashboard's parts print inside the switch's root is one the inventory names, and no other.
ck( 'the inventory: the old classes printed inside the parts are exactly the ones named in bin/stubs/parts-inventory.php', parts_inventory_found(), parts_inventory_pinned( 'cohort-timeline' ) );
echo "\n" . ( $fail ? "$fail FAILED" : 'ALL PASS' ) . "\n";
exit( $fail ? 1 : 0 );
