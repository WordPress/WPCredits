<?php
/**
 * The Track Builder's tracks as the live site reads them (1.100.0): the program map, the form,
 * the chips and the automation guard, from the compiled options alone.
 *
 * The filters here run for real, so a check can ask `WPCPM_Program` the questions every page asks
 * it and see the answer a compiled track makes. The map holds no rows of its own since the
 * hand-written rows and forms were removed, so with nothing compiled every map is empty and there
 * is no form, and the second section holds that.
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

/** One compiled row, as a compile writes it: with no `source`, which a check adds to stand for a row compiled before. */
function row( $key, $label, array $overrides = array() ) {
	return array_merge(
		array( 'key' => $key, 'label' => $label, 'course_url' => '', 'course_id' => 0, 'hours' => null, 'hue' => 'cyan', 'automation' => false, 'post' => 1 ),
		$overrides
	);
}

$form            = array( 'Practical: Campaign Brief - Notes' => array( 'label' => 'Your notes', 'type' => 'textarea', 'group' => 'project' ) );
$original_labels = array( 'In Sensei' => 'WordPress Credits Program 150h', 'In Sensei 50h' => 'WordPress Credits Program 50h', 'Developer Track' => 'Developer Track', 'Designer Track' => 'Designer Track' );

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

echo "\n=== With nothing compiled, the program map is empty ===\n";

// The five maps hold no rows of their own: the compiled tracks are their only source, so with
// nothing compiled no status is a track, and the two states keep the chips `states()` gives them.
compiled( array() );
ck( 'the five maps are empty, and no status is a track',
	array( WPCPM_Program::labels(), WPCPM_Program::courses(), WPCPM_Program::course_ids(), WPCPM_Program::hours_targets(), WPCPM_Program::track( 'In Sensei' ), WPCPM_Program::track( 'Designer Track' ), WPCPM_Program::is_track( 'In Sensei 50h' ) ),
	array( array(), array(), array(), array(), '', '', false ) );
ck( 'and an unknown status has no course ID and no key', array( WPCPM_Program::course_id( 'Not a track at all' ), WPCPM_Program::track( 'Not a track at all' ) ), array( 0, '' ) );
ck( 'badge() paints no track chip for a status no track holds, the four original tracks\' included', array( WPCPM_Program::badge( 'In Sensei' ), WPCPM_Program::badge( 'Designer Track' ), WPCPM_Program::badge( 'Not a track at all' ) ), array( '', '', '' ) );
ck( 'and the two states their own chip', array( 'Paused' => WPCPM_Program::badge( 'Paused' ), 'Pending graduation' => WPCPM_Program::badge( 'Pending graduation' ) ), array( 'Paused' => 'paused', 'Pending graduation' => 'pending' ) );
ck( 'the form filter hands back what it was given for a key no track holds', apply_filters( 'wpcpm_report_form_fields', array( 'x' => 1 ), 'marketing' ), array( 'x' => 1 ) );
ck( 'no chip rule', WPCPM_Tracks::badge_css(), '' );
WPCPM_Tracks::add_badge_styles();
ck( 'and not even the stylesheet is touched', array( $GLOBALS['assets'], $GLOBALS['inline'] ), array( 0, array() ) );
ck( 'no track is known to the automation', WPCPM_Tracks::confirmed_automation_statuses(), array() );

echo "\n=== An authored track, published ===\n";

compiled(
	array(
		'Marketing Track' => row( 'marketing', 'Marketing Track', array( 'course_url' => 'https://learn.wordpress.org/course/wordpress-credits-marketing-track/', 'course_id' => 500001, 'hours' => 120, 'automation' => true ) ),
	),
	array( 'marketing' => $form )
);
ck( 'it is a track', WPCPM_Program::is_track( 'Marketing Track' ), true );
ck( 'with its name, the map\'s one row', WPCPM_Program::labels(), array( 'Marketing Track' => 'Marketing Track' ) );
ck( 'its key, and the chip painted from it', array( WPCPM_Program::track( 'Marketing Track' ), WPCPM_Program::badge( 'Marketing Track' ) ), array( 'marketing', 'marketing' ) );
ck( 'its Learn course, by link and by ID', array( WPCPM_Program::course_url( 'Marketing Track' ), WPCPM_Program::course_id( 'Marketing Track' ) ), array( 'https://learn.wordpress.org/course/wordpress-credits-marketing-track/', 500001 ) );
ck( 'its hours target', array( WPCPM_Program::hours_target( 'Marketing Track' ), WPCPM_Program::has_hours_target( 'Marketing Track' ) ), array( 120, true ) );
ck( 'its own form, from its option', apply_filters( 'wpcpm_report_form_fields', array(), 'marketing' ), $form );
ck( 'its chip rule, from the palette', WPCPM_Tracks::badge_css(), '.wpcpm-badge--marketing{background:rgba(8,145,178,0.12);border-color:rgba(8,145,178,0.35);}' );
WPCPM_Tracks::add_badge_styles();
ck( 'printed with the dashboard stylesheet, registered first so the rule has somewhere to go', array( $GLOBALS['assets'], $GLOBALS['inline'] ), array( 1, array( array( 'wpcpm-mentor-dashboard', WPCPM_Tracks::badge_css() ) ) ) );
ck( 'and its automation item, ticked, puts it on the guard\'s list', WPCPM_Tracks::confirmed_automation_statuses(), array( 'Marketing Track' ) );

echo "\n=== A track with no hours and no course ===\n";

compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ), array( 'marketing' => $form ) );
ck( 'no target: absent from the map, so nothing prints a denominator', array( array_key_exists( 'Marketing Track', WPCPM_Program::hours_targets() ), WPCPM_Program::has_hours_target( 'Marketing Track' ) ), array( false, false ) );
ck( 'no course: no link and no ID', array( WPCPM_Program::course_url( 'Marketing Track' ), WPCPM_Program::course_id( 'Marketing Track' ) ), array( '', 0 ) );
ck( 'and with its automation item unticked, the guard does not know it', WPCPM_Tracks::confirmed_automation_statuses(), array() );

echo "\n=== One of the four original tracks ===\n";

compiled( array( 'Designer Track' => row( 'design', 'Designer Track', array( 'hue' => 'pink', 'course_id' => 403425, 'course_url' => 'https://learn.wordpress.org/course/wordpress-credits-designer-track/' ) ) ), array( 'design' => $form ) );
ck( 'its hours target is its definition\'s: this one names none, so it has none', WPCPM_Program::hours_target( 'Designer Track' ), 0 );
ck( 'its form is the definition\'s', apply_filters( 'wpcpm_report_form_fields', array(), 'design' ), $form );
ck( 'but its chip keeps the rule written into the stylesheets: none is generated', WPCPM_Tracks::badge_css(), '' );

echo "\n=== When a compiled option is not what it should be ===\n";

// Beside a live 150-hour track, so the fallback a key no live track holds reads is in play: a live
// track whose form went missing, a compile cut short, draws nothing rather than the 150-hour form,
// whose columns its students would then write.
compiled( array( 'In Sensei' => row( '150h', 'WordPress Credits Program 150h' ), 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ), array( '150h' => $form ) );
ck( 'a live track with no form option draws an empty form, never the 150-hour track\'s or what it was handed', apply_filters( 'wpcpm_report_form_fields', array( 'another form' => array() ), 'marketing' ), array() );
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
ck( 'and their names, which a new track may not take', $context['labels'], $original_labels );
ck( 'the statuses that mean something else: past students, and the two states', $context['refused_statuses'], array( 'Graduate', 'Dropped out', 'Paused', 'Pending graduation' ) );
ck( 'the sync columns, and nothing locked', array( $context['reserved_columns'], $context['locked'] ), array( WPCPM_Tracks::reserved_columns(), null ) );
ck( 'and the track it describes passes with it', WPCPM_Track_Definition::validate( array( 'schema_version' => 1, 'status' => 'Marketing Track', 'key' => 'marketing', 'label' => 'Marketing Track', 'hue' => 'cyan', 'questions' => $form ), $context ), array() );

echo "\n=== The context with the compiled tracks and without them ===\n";

compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ), array( 'marketing' => $form ) );
ck( 'with a track compiled, the context counts it', array_key_exists( 'Marketing Track', WPCPM_Tracks::validation_context()['tracks'] ), true );
ck( 'read without the compiled tracks, it holds the four original tracks alone', WPCPM_Tracks::validation_context( '', false )['tracks'], array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ) );

echo "\n=== With nothing compiled, the rules still know the four original tracks ===\n";

// The four come from `WPCPM_Tracks::RESERVED_PAIRS`, never from the program map, which only a
// compile fills: a context read from the map would lose them, and with them what keeps a new track
// off their statuses and names (the design's decision 36).
compiled( array() );
$bare = array( WPCPM_Program::labels(), WPCPM_Tracks::validation_context( '', false ) );
ck( 'with nothing compiled the map is empty, and the context still holds the four by their pairs, named as their seeds name them',
	array( $bare[0], $bare[1]['tracks'], $bare[1]['labels'] ),
	array( array(), array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ), $original_labels ) );

// A row compiled before the removal carries a source, `builtin` on a track published and never
// switched: it is live like any row, so its name is the one kept out of reach too.
compiled(
	array(
		'In Sensei'       => row( '150h', 'Credits 150' ),
		'In Sensei 50h'   => row( '50h', 'Credits 50', array( 'source' => 'builtin' ) ),
		'Marketing Track' => row( 'marketing', 'Marketing Track' ),
	),
	array( '150h' => $form, '50h' => $form, 'marketing' => $form )
);
$renamed = array( WPCPM_Tracks::validation_context( '', false ), WPCPM_Tracks::validation_context() );
ck( 'the name a live row gives is the one kept out of a new track\'s reach, a row compiled before the removal included, and the seeds\' names stand in for the rest',
	$renamed[0]['labels'], array( 'In Sensei' => 'Credits 150', 'In Sensei 50h' => 'Credits 50' ) + $original_labels );
ck( 'and read with the compiled tracks, every other live track joins the four',
	array( $renamed[1]['tracks'], $renamed[1]['labels'] ),
	array( array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design', 'Marketing Track' => 'marketing' ), array( 'In Sensei' => 'Credits 150', 'In Sensei 50h' => 'Credits 50' ) + $original_labels + array( 'Marketing Track' => 'Marketing Track' ) ) );

echo "\n=== The four original tracks' pairs ===\n";

// Held to the seed files a fresh site seeds from, so the lock never keeps a track to a key or a
// name its seed does not have.
$seed_pairs  = array();
$seed_labels = array();

foreach ( array( '150h', '50h', 'dev', 'design' ) as $seed_key ) {
	$seed                           = json_decode( (string) file_get_contents( __DIR__ . '/../includes/tracks/seeds/' . $seed_key . '.json' ), true );
	$seed_pairs[ $seed['status'] ]  = $seed['key'];
	$seed_labels[ $seed['status'] ] = $seed['label'];
}

$original_pairs = array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' );
ck( 'each original track\'s status against its key, as its seed holds them', array( WPCPM_Tracks::RESERVED_PAIRS, $seed_pairs ), array( $original_pairs, $original_pairs ) );
ck( 'reserved_key() answers each one\'s key, of a status trimmed as every status read is, and nothing for any other status, one a capital away included',
	array( WPCPM_Tracks::reserved_key( 'In Sensei' ), WPCPM_Tracks::reserved_key( 'In Sensei 50h' ), WPCPM_Tracks::reserved_key( 'Developer Track' ), WPCPM_Tracks::reserved_key( 'Designer Track' ), WPCPM_Tracks::reserved_key( ' Designer Track ' ), WPCPM_Tracks::reserved_key( 'Graduate' ), WPCPM_Tracks::reserved_key( 'Marketing Track' ), WPCPM_Tracks::reserved_key( 'designer track' ), WPCPM_Tracks::reserved_key( '' ) ),
	array( '150h', '50h', 'dev', 'design', 'design', '', '', '', '' ) );
ck( 'is_reserved() says the same', array( WPCPM_Tracks::is_reserved( 'In Sensei' ), WPCPM_Tracks::is_reserved( 'Designer Track' ), WPCPM_Tracks::is_reserved( 'Graduate' ), WPCPM_Tracks::is_reserved( 'Marketing Track' ) ), array( true, true, false, false ) );
ck( 'reserved_label() names each as its seed spells the name, and nothing for any other status',
	array( WPCPM_Tracks::reserved_label( 'In Sensei' ), WPCPM_Tracks::reserved_label( 'In Sensei 50h' ), WPCPM_Tracks::reserved_label( 'Developer Track' ), WPCPM_Tracks::reserved_label( 'Designer Track' ), WPCPM_Tracks::reserved_label( 'Graduate' ) ),
	array( 'WordPress Credits Program 150h', 'WordPress Credits Program 50h', 'Developer Track', 'Designer Track', '' ) );
ck( 'the four names are the seeds\' own', array( WPCPM_Tracks::RESERVED_LABELS, $seed_labels ), array( $original_labels, $original_labels ) );

echo "\n=== A student on no track reads the 150-hour track's form (TRACKS-5) ===\n";

// The product owner, 23 September 2026: a student on no track, Paused or Pending graduation, whose
// track() is the empty string, is drawn the 150-hour track's form through the filter, so the
// 150-hour definition and every edit published to it reach them as they reach its own students.
// Before, fields( '' ) never matched the filter and read the hand-written form, which is gone.
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php';

$edited = array(
	'Hours' => array( 'label' => 'Hours you contributed, in total', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 10000, 'group' => 'hours' ),
	'Notes' => array( 'label' => 'Your notes', 'type' => 'textarea', 'group' => 'project' ),
);

compiled( array( 'In Sensei' => row( '150h', 'WordPress Credits Program 150h' ) ), array( '150h' => $edited ) );
ck( 'a student on no track reads the 150-hour track\'s definition, as its own students do',
	array( WPCPM_Student_Report_Form::fields( '' ), WPCPM_Student_Report_Form::fields( WPCPM_Program::track( 'In Sensei' ) ) ),
	array( $edited, $edited ) );
ck( 'whichever state keeps them off a track', array( WPCPM_Program::track( 'Paused' ), WPCPM_Program::track( 'Pending graduation' ), WPCPM_Student_Report_Form::fields( WPCPM_Program::track( 'Paused' ) ) ), array( '', '', $edited ) );

echo "\n=== The form a key no live track holds reads, and the rows a site compiled before ===\n";

// Every form comes from the compiled index now: a key no live track holds reads the 150-hour
// track's compiled form, which is what it read while the hand-written 150-hour set was the fallback
// (the design's decision 34), and with nothing compiled there is no form at all.
compiled( array() );
ck( 'with nothing compiled there is no form: a student on no track and a key no track holds read nothing',
	array( WPCPM_Student_Report_Form::fields( '' ), WPCPM_Student_Report_Form::fields( 'nope' ) ),
	array( array(), array() ) );

compiled( array( 'In Sensei' => row( '150h', 'WordPress Credits Program 150h' ), 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ), array( '150h' => $edited, 'marketing' => $form ) );
ck( 'a key no live track holds reads the 150-hour track\'s compiled form, and so does a student on no track; a live key reads its own',
	array( WPCPM_Student_Report_Form::fields( 'nope' ), WPCPM_Student_Report_Form::fields( '' ), WPCPM_Student_Report_Form::fields( 'marketing' ) ),
	array( $edited, $edited, $form ) );

compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ), array( 'marketing' => $form, '150h' => $edited ) );
ck( 'while the 150-hour track is not live, a key no live track holds reads nothing: a form option left behind is not a live track',
	array( WPCPM_Student_Report_Form::fields( 'nope' ), WPCPM_Student_Report_Form::fields( '' ) ),
	array( array(), array() ) );

// A compile writes no `source` now. The rows a site compiled before carry one, `definition` on a
// track switched to its definition and `builtin` on one published and never switched, whose published
// copy the preflight held to the hand-written form it ran from; each is read as the compile writes
// rows now, so no track leaves the map before the next compile.
compiled(
	array(
		'In Sensei'       => row( '150h', 'Credits 150' ),
		'Developer Track' => row( 'dev', 'Developer Track', array( 'source' => 'definition', 'hours' => 0 ) ),
		'Designer Track'  => row( 'design', 'Designer Track', array( 'source' => 'builtin', 'hours' => 150 ) ),
	),
	array( '150h' => $edited, 'dev' => $form, 'design' => $form )
);
ck( 'a compiled row is live with a source or without one, a row compiled from a track never switched included',
	array( WPCPM_Program::labels(), WPCPM_Program::hours_targets(), WPCPM_Student_Report_Form::fields( '150h' ), WPCPM_Student_Report_Form::fields( 'design' ) ),
	array( array( 'In Sensei' => 'Credits 150', 'Developer Track' => 'Developer Track', 'Designer Track' => 'Designer Track' ), array( 'Developer Track' => 0, 'Designer Track' => 150 ), $edited, $form ) );

ck( 'and the reads of the program map with the compiled tracks suspended are gone, with what suspended them',
	array( method_exists( 'WPCPM_Tracks', 'builtin_row' ), method_exists( 'WPCPM_Tracks', 'builtin_key' ), method_exists( 'WPCPM_Tracks', 'unfiltered' ), property_exists( 'WPCPM_Tracks', 'suspended' ) ),
	array( false, false, false, false ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
