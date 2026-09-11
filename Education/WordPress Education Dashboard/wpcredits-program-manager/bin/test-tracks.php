<?php
/**
 * The Track Builder's tracks as the live site reads them (1.100.0): the program map, the form,
 * the chips and the automation guard, from the compiled options alone.
 *
 * The filters here run for real, so a check can ask `WPCPM_Program` the questions every page asks
 * it and see the answer a compiled track makes. With nothing compiled, every answer is the one
 * 1.99.3 gave, and the second section holds that.
 *
 * Run from the plugin root:  php bin/test-tracks.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['filters'] = array();
$GLOBALS['hooked']  = array();
$GLOBALS['actions'] = array();
$GLOBALS['opts']    = array();
$GLOBALS['reads']   = 0;
$GLOBALS['inline']  = array();
$GLOBALS['assets']  = 0;

function __( $s, $d = null ) { return $s; }
function apply_filters( $tag, $value, ...$args ) {
	foreach ( $GLOBALS['filters'][ $tag ] ?? array() as $callback ) {
		$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
	}

	return $value;
}
function add_filter( $tag, $callback, $priority = 10, $accepted = 1 ) {
	$GLOBALS['filters'][ $tag ][] = $callback;
	$GLOBALS['hooked'][]          = array( $tag, is_array( $callback ) ? $callback[1] : 'closure', $priority, $accepted );
}
function add_action( $tag, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['actions'][] = array( $tag, $callback[1], $priority ); }
function get_option( $k, $d = false ) {
	if ( 'wpcpm_tracks' === $k ) {
		++$GLOBALS['reads'];
	}
	return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d;
}
function wp_add_inline_style( $handle, $css ) { $GLOBALS['inline'][] = array( $handle, $css ); return true; }

/** The mentor dashboard, for the one constant and the one call the chips use. */
class WPCPM_Mentors_Dashboard {
	const STYLE = 'wpcpm-mentor-dashboard';
	public static function register_assets() { ++$GLOBALS['assets']; }
}

/** The sync's column map, its report half: all the Track Builder reads of the sync. */
class WPCPM_Mentors_Sync {
	public static function fields() {
		return array(
			'report_name'       => 'Name',
			'report_email'      => 'Email',
			'report_status'     => 'Status',
			'report_mentor'     => 'Mentor',
			'report_instituton' => 'Educational institution',
			'report_profile'    => 'WordPress Profile',
			'report_slack'      => 'Slack Name',
			'report_team'       => 'Main Contribution Team',
			'report_website'    => 'Personal Website URL',
			'report_start'      => 'Internship Start Date',
			'report_end'        => 'Internship End Date',
			'report_hours'      => 'Hours',
			'report_link'       => 'Personal link',
			'report_link_50h'   => '50h personal link',
			'report_link_dev'   => 'Dev Track ONLY personal link',
		);
	}
}

/** The settings, for the one list the status rule reads. */
class WPCPM_Settings {
	public static function get() {
		return array( 'past_statuses' => array( 'Graduate', 'Dropped out' ) );
	}
}

require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-tracks.php';

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

/** Put a compiled store in place, as `WPCPM_Track_Store::compile()` leaves it. */
function compiled( array $rows, array $forms = array() ) {
	$GLOBALS['opts'] = array( WPCPM_Tracks::OPT_TRACKS => $rows );
	foreach ( $forms as $key => $form ) {
		$GLOBALS['opts'][ WPCPM_Tracks::OPT_FIELDS_PREFIX . $key ] = $form;
	}
	WPCPM_Tracks::flush();
}

/** One compiled row. */
function row( $key, $label, array $overrides = array() ) {
	return array_merge(
		array( 'key' => $key, 'label' => $label, 'course_url' => '', 'course_id' => 0, 'hours' => null, 'hue' => 'cyan', 'source' => 'definition', 'automation' => false, 'post' => 1 ),
		$overrides
	);
}

$form           = array( 'Practical: Campaign Brief - Notes' => array( 'label' => 'Your notes', 'type' => 'textarea', 'group' => 'project' ) );
$builtin_labels = array( 'In Sensei' => 'WordPress Credits Program 150h', 'In Sensei 50h' => 'WordPress Credits Program 50h', 'Developer Track' => 'Developer Track', 'Designer Track' => 'Designer Track' );

echo "=== init() ===\n";

WPCPM_Tracks::init();
ck(
	'the five program filters and the form filter, the form one taking its track',
	$GLOBALS['hooked'],
	array(
		array( 'wpcpm_program_labels', 'filter_labels', 10, 1 ),
		array( 'wpcpm_program_courses', 'filter_courses', 10, 1 ),
		array( 'wpcpm_program_hours_targets', 'filter_hours', 10, 1 ),
		array( 'wpcpm_program_tracks', 'filter_tracks', 10, 1 ),
		array( 'wpcpm_program_course_ids', 'filter_course_ids', 10, 1 ),
		array( 'wpcpm_report_form_fields', 'filter_fields', 10, 2 ),
	)
);
ck( 'and the chips after init 10, where the dashboards register the stylesheet they live in', $GLOBALS['actions'], array( array( 'init', 'add_badge_styles', 20 ) ) );

echo "\n=== With nothing compiled, every answer is 1.99.3's ===\n";

compiled( array() );
ck( 'the four tracks and nothing else', WPCPM_Program::labels(), $builtin_labels );
ck( 'the hours targets', WPCPM_Program::hours_targets(), array( 'In Sensei' => 150, 'In Sensei 50h' => 50, 'Developer Track' => 0, 'Designer Track' => 150 ) );
ck( 'the four course links, as one map', WPCPM_Program::courses(), array( 'In Sensei' => 'https://learn.wordpress.org/course/wordpress-credits/', 'In Sensei 50h' => 'https://learn.wordpress.org/course/50-hours-wordpress-credits/', 'Developer Track' => 'https://learn.wordpress.org/course/wordpress-credits-developer-track/', 'Designer Track' => 'https://learn.wordpress.org/course/wordpress-credits-designer-track/' ) );
ck( 'the four course IDs, as one map', WPCPM_Program::course_ids(), array( 'In Sensei' => 297853, 'In Sensei 50h' => 322343, 'Developer Track' => 402893, 'Designer Track' => 403425 ) );
ck( 'and an unknown status has no course ID', WPCPM_Program::course_id( 'Not a track at all' ), 0 );
ck( 'track() gives each of the four statuses its key', array( 'In Sensei' => WPCPM_Program::track( 'In Sensei' ), 'In Sensei 50h' => WPCPM_Program::track( 'In Sensei 50h' ), 'Developer Track' => WPCPM_Program::track( 'Developer Track' ), 'Designer Track' => WPCPM_Program::track( 'Designer Track' ) ), array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ) );
ck( 'and an unknown status whatever track() gives it today', WPCPM_Program::track( 'Not a track at all' ), '' );
ck( 'badge() gives the four statuses their track chip', array( 'In Sensei' => WPCPM_Program::badge( 'In Sensei' ), 'In Sensei 50h' => WPCPM_Program::badge( 'In Sensei 50h' ), 'Developer Track' => WPCPM_Program::badge( 'Developer Track' ), 'Designer Track' => WPCPM_Program::badge( 'Designer Track' ) ), array( 'In Sensei' => 'sensei', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ) );
ck( 'and the two states their own chip', array( 'Paused' => WPCPM_Program::badge( 'Paused' ), 'Pending graduation' => WPCPM_Program::badge( 'Pending graduation' ) ), array( 'Paused' => 'paused', 'Pending graduation' => 'pending' ) );
ck( 'and an unknown status whatever badge() gives it today', WPCPM_Program::badge( 'Not a track at all' ), '' );
ck( 'the form filter hands back what it was given', apply_filters( 'wpcpm_report_form_fields', array( 'x' => 1 ), 'marketing' ), array( 'x' => 1 ) );
ck( 'no chip rule', WPCPM_Tracks::badge_css(), '' );
WPCPM_Tracks::add_badge_styles();
ck( 'and not even the stylesheet is touched', array( $GLOBALS['assets'], $GLOBALS['inline'] ), array( 0, array() ) );
ck( 'no track is known to the automation', WPCPM_Tracks::confirmed_automation_statuses(), array() );

echo "\n=== An authored track, published ===\n";

compiled(
	array(
		'Marketing Track' => row( 'marketing', 'Marketing Track', array( 'course_url' => 'https://learn.wordpress.org/course/wordpress-credits-marketing-track/', 'course_id' => 500001, 'hours' => 120, 'automation' => true ) ),
		'In Sensei 50h'   => row( '50h', 'Should not appear', array( 'source' => 'builtin', 'hours' => 1 ) ),
	),
	array( 'marketing' => $form, '50h' => array( 'Should not appear' => array() ) )
);
ck( 'it is a track', WPCPM_Program::is_track( 'Marketing Track' ), true );
ck( 'with its name, after the four', WPCPM_Program::labels(), $builtin_labels + array( 'Marketing Track' => 'Marketing Track' ) );
ck( 'its key, and the chip painted from it', array( WPCPM_Program::track( 'Marketing Track' ), WPCPM_Program::badge( 'Marketing Track' ) ), array( 'marketing', 'marketing' ) );
ck( 'its Learn course, by link and by ID', array( WPCPM_Program::course_url( 'Marketing Track' ), WPCPM_Program::course_id( 'Marketing Track' ) ), array( 'https://learn.wordpress.org/course/wordpress-credits-marketing-track/', 500001 ) );
ck( 'its hours target', array( WPCPM_Program::hours_target( 'Marketing Track' ), WPCPM_Program::has_hours_target( 'Marketing Track' ) ), array( 120, true ) );
ck( 'its own form, from its option', apply_filters( 'wpcpm_report_form_fields', array( 'the 150-hour set' => array() ), 'marketing' ), $form );
ck( 'a built-in track\'s form is left to its PHP', apply_filters( 'wpcpm_report_form_fields', array( 'the 150-hour set' => array() ), '150h' ), array( 'the 150-hour set' => array() ) );
ck( 'a track its PHP still runs is skipped by every callback', array( WPCPM_Program::label( 'In Sensei 50h' ), WPCPM_Program::hours_target( 'In Sensei 50h' ), apply_filters( 'wpcpm_report_form_fields', array( 'php' => array() ), '50h' ) ), array( 'WordPress Credits Program 50h', 50, array( 'php' => array() ) ) );
ck( 'its chip rule, from the palette', WPCPM_Tracks::badge_css(), '.wpcpm-badge--marketing{background:rgba(8,145,178,0.12);border-color:rgba(8,145,178,0.35);}' );
WPCPM_Tracks::add_badge_styles();
ck( 'printed with the dashboard stylesheet, registered first so the rule has somewhere to go', array( $GLOBALS['assets'], $GLOBALS['inline'] ), array( 1, array( array( 'wpcpm-mentor-dashboard', WPCPM_Tracks::badge_css() ) ) ) );
ck( 'and its automation item, ticked, puts it on the guard\'s list', WPCPM_Tracks::confirmed_automation_statuses(), array( 'Marketing Track' ) );

echo "\n=== A track with no hours and no course ===\n";

compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ), array( 'marketing' => $form ) );
ck( 'no target: absent from the map, so nothing prints a denominator', array( array_key_exists( 'Marketing Track', WPCPM_Program::hours_targets() ), WPCPM_Program::has_hours_target( 'Marketing Track' ) ), array( false, false ) );
ck( 'no course: no link and no ID', array( WPCPM_Program::course_url( 'Marketing Track' ), WPCPM_Program::course_id( 'Marketing Track' ) ), array( '', 0 ) );
ck( 'and with its automation item unticked, the guard does not know it', WPCPM_Tracks::confirmed_automation_statuses(), array() );

echo "\n=== A built-in track, switched to its definition ===\n";

compiled( array( 'Designer Track' => row( 'design', 'Designer Track', array( 'hue' => 'pink', 'course_id' => 403425, 'course_url' => 'https://learn.wordpress.org/course/wordpress-credits-designer-track/' ) ) ), array( 'design' => $form ) );
ck( 'its definition names no hours, and the PHP\'s 150 gives way', WPCPM_Program::hours_target( 'Designer Track' ), 0 );
ck( 'its form is the definition\'s', apply_filters( 'wpcpm_report_form_fields', array( 'php' => array() ), 'design' ), $form );
ck( 'but its chip keeps the hand-written rule: none is generated', WPCPM_Tracks::badge_css(), '' );

echo "\n=== When a compiled option is not what it should be ===\n";

compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ) );
ck( 'a live track with no form option draws an empty form, never the 150-hour set it was handed', apply_filters( 'wpcpm_report_form_fields', array( 'the 150-hour set' => array() ), 'marketing' ), array() );
compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track', array( 'hue' => 'mauve' ) ) ), array( 'marketing' => $form ) );
ck( 'a hue outside the palette prints no rule', WPCPM_Tracks::badge_css(), '' );

echo "\n=== Read once a request ===\n";

compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ), array( 'marketing' => $form ) );
$GLOBALS['reads'] = 0;
WPCPM_Program::labels();
WPCPM_Program::labels();
WPCPM_Program::track( 'Marketing Track' );
ck( 'the index is read once, however often the map is asked', $GLOBALS['reads'], 1 );
WPCPM_Tracks::flush();
WPCPM_Program::labels();
ck( 'and again after a flush', $GLOBALS['reads'], 2 );

echo "\n=== What the rules are told about the site ===\n";

ck( 'the columns the syncs own: every report column the sync maps that is not a question', WPCPM_Tracks::reserved_columns(), array( 'Name', 'Email', 'Status', 'Mentor', 'Educational institution', 'Internship Start Date', 'Internship End Date', 'Personal link', '50h personal link', 'Dev Track ONLY personal link' ) );
$context = WPCPM_Tracks::validation_context( 'Marketing Track' );
ck( 'the other tracks by status and key, the track being checked left out', $context['tracks'], array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ) );
ck( 'and their names, which a new track may not take', $context['labels'], $builtin_labels );
ck( 'the statuses that mean something else: past students, and the two states', $context['refused_statuses'], array( 'Graduate', 'Dropped out', 'Paused', 'Pending graduation' ) );
ck( 'the sync columns, and nothing locked', array( $context['reserved_columns'], $context['locked'] ), array( WPCPM_Tracks::reserved_columns(), null ) );
ck( 'and the track it describes passes with it', WPCPM_Track_Definition::validate( array( 'schema_version' => 1, 'status' => 'Marketing Track', 'key' => 'marketing', 'label' => 'Marketing Track', 'hue' => 'cyan', 'questions' => $form ), $context ), array() );

echo "\n=== The program map as its PHP alone describes it ===\n";

compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ), array( 'marketing' => $form ) );
ck( 'with a track compiled, the context counts it', array_key_exists( 'Marketing Track', WPCPM_Tracks::validation_context()['tracks'] ), true );
ck( 'read without the compiled tracks, it holds the four the PHP knows', WPCPM_Tracks::validation_context( '', false )['tracks'], array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ) );
ck( 'and the map answers with the compiled track again afterwards', WPCPM_Program::is_track( 'Marketing Track' ), true );
ck( 'the key the PHP gives a built-in status', array( WPCPM_Tracks::builtin_key( 'Designer Track' ), WPCPM_Tracks::builtin_key( 'In Sensei' ) ), array( 'design', '150h' ) );
ck( 'and none for a compiled track, or for a status no track holds', array( WPCPM_Tracks::builtin_key( 'Marketing Track' ), WPCPM_Tracks::builtin_key( 'Graduate' ) ), array( '', '' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
