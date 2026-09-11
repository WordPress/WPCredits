<?php
/**
 * The four seed definitions (Track Builder, phase T2a): each is its hand-written track, byte for byte.
 *
 * `includes/tracks/seeds/<key>.json` is what a site with no tracks starts from, and a built-in
 * track may switch to its definition only while the two are identical (the design's decision 3.5
 * and section 8). So each seed is held to the PHP it was taken from: its form to
 * `builtin_fields()`, its map to `WPCPM_Program`, its column types to the base's, its lessons to its
 * Learn course. And each is what bin/build-seeds.php builds today, so a form changed without the
 * seeds rebuilt fails here.
 *
 * Run from the plugin root:  php bin/test-track-definitions.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $s, $d = null ) { return $s; }
function apply_filters( $tag, $value ) { return $value; }

require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
require_once __DIR__ . '/seed-definitions.php';

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

$reports = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/reports-table-fields.json' ), true );
$types   = isset( $reports['all_types'] ) ? (array) $reports['all_types'] : array();

// The columns the syncs own. bin/test-report-form.php holds this list to the real sync's map.
$reserved = array( 'Name', 'Email', 'Status', 'Mentor', 'Educational institution', 'Internship Start Date', 'Internship End Date', 'Personal link', '50h personal link', 'Dev Track ONLY personal link' );

echo "=== Four seeds, one for each hand-written track ===\n";

ck(
	'the four built-in tracks, by key, with their statuses and the hues their chips are painted in',
	wpcpm_seed_tracks(),
	array(
		'150h'   => array( 'status' => 'In Sensei', 'hue' => 'blue' ),
		'50h'    => array( 'status' => 'In Sensei 50h', 'hue' => 'purple' ),
		'dev'    => array( 'status' => 'Developer Track', 'hue' => 'teal' ),
		'design' => array( 'status' => 'Designer Track', 'hue' => 'pink' ),
	)
);

$lessons_named = array();
$why_named     = array();

foreach ( wpcpm_seed_tracks() as $key => $track ) {
	echo "\n=== The {$key} seed ===\n";

	$status = $track['status'];
	$file   = wpcpm_seed_path( $key );
	$stored = is_file( $file ) ? (string) file_get_contents( $file ) : '';
	$seed   = WPCPM_Track_Definition::decode( $stored );

	ck( 'is a stored definition', is_array( $seed ), true );

	if ( ! is_array( $seed ) ) {
		continue;
	}

	ck( 'and is what bin/build-seeds.php builds from the PHP today (run it, and commit what it writes)', $stored, wpcpm_seed_json( wpcpm_seed_build( $key ) ) );
	ck( 'its form is the hand-written one, byte for byte', WPCPM_Track_Definition::compile_fields( $seed ), WPCPM_Student_Report_Form::builtin_fields( $key ) );
	ck(
		'its status, key, name, course and hours are the program map\'s',
		array( $seed['status'], $seed['key'], $seed['label'], $seed['course_url'], $seed['learn_course_id'], $seed['hours_target'] ),
		array( $status, WPCPM_Program::track( $status ), WPCPM_Program::labels()[ $status ], WPCPM_Program::courses()[ $status ], WPCPM_Program::course_ids()[ $status ], WPCPM_Program::hours_targets()[ $status ] )
	);
	ck( 'it is stored normalized: normalizing it changes nothing', WPCPM_Track_Definition::normalize( $seed ), $seed );

	$context = array(
		'tracks'           => array(),
		'labels'           => array(),
		'refused_statuses' => array( 'Graduate', 'Dropped out', 'Paused', 'Pending graduation' ),
		'reserved_columns' => $reserved,
		'locked'           => array(
			'status' => $status,
			'key'    => $key,
		),
	);

	foreach ( wpcpm_seed_tracks() as $other_key => $other ) {
		if ( $other_key !== $key ) {
			$context['tracks'][ $other['status'] ] = $other_key;
			$context['labels'][ $other['status'] ] = WPCPM_Program::labels()[ $other['status'] ];
		}
	}

	ck( 'every rule accepts it, its reserved key kept by the lock a built-in track has', WPCPM_Track_Definition::validate( $seed, $context ), array() );

	$wrong_types = array();
	$lesson_ids  = array();

	foreach ( $seed['questions'] as $column => $spec ) {
		if ( ! isset( $spec['airtable_type'], $types[ $column ] ) || $spec['airtable_type'] !== $types[ $column ] ) {
			$wrong_types[] = $column;
		}

		if ( isset( $spec['learn_lesson_id'] ) ) {
			$lesson_ids[] = $spec['learn_lesson_id'];
		}

		if ( isset( $spec['why'] ) ) {
			$why_named[ $key ][] = $column;
		}
	}

	ck( 'every question records its column\'s Airtable type, as the base has it', $wrong_types, array() );

	$course = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/learn-course-' . WPCPM_Program::course_ids()[ $status ] . '.json' ), true );
	$known  = array();

	foreach ( (array) $course['modules'] as $module ) {
		foreach ( (array) $module['lessons'] as $lesson ) {
			$known[] = $lesson['id'];
		}
	}

	ck( 'and every Learn lesson it names is a lesson of its own course', array_values( array_diff( $lesson_ids, $known ) ), array() );

	$lessons_named[ $key ] = count( $lesson_ids );
}

echo "\n=== What the seeds say about Learn, and about the base ===\n";

ck( 'the Designer Track is organized by lesson, the other three by the program\'s own headings: the questions that report on a Learn lesson (the design\'s 2.6)', $lessons_named, array( '150h' => 1, '50h' => 0, 'dev' => 1, 'design' => 11 ) );
ck(
	'every column whose name looks like a slip says why, on each track that asks it (the design\'s section 8)',
	$why_named,
	array(
		'150h' => array(
			'Advance WordPress User - final grade',
		),
		'dev' => array(
			'Advance WordPress User - final grade',
			'Slack/GitHub/Blog WordPress Community meetings/discussions',
		),
		'design' => array(
			'Advance WordPress User - final grade',
			'Personal Website URL',
			'Post Reflection: Building Your Personal Website',
			'Practical: Duplicate & Explore WP Design Library - Reflection',
			'Practical: Duplicate & Explore WP Design Library - link',
			'Practical: Duplicate & Explore WP Design Library - image',
			'Practical: Local WordPress Environment for Design Testing - Tool used',
			'Practical: Change Your Site’s Global Styles - Notes',
			'Practical: Change Your Site’s Global Styles - Before Screenshot',
			'Practical: Change Your Site’s Global Styles - After Screenshot',
			'Slack/GitHub/Blog WordPress Community meetings/discussions',
		),
	)
);

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
