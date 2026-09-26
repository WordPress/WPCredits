<?php
/**
 * The four seed definitions (Track Builder, phase T2a): the program's four original tracks, as a
 * fresh site seeds them.
 *
 * `includes/tracks/seeds/<key>.json` is what a site with no tracks starts from, and since the
 * hand-written forms and the program map's rows they were built from were removed, the files are
 * the source of the four. So each is pinned here byte for byte, and held to what its track is: the
 * status and key its original track is locked to (`WPCPM_Tracks::RESERVED_PAIRS`), every rule of the
 * Track Builder under that lock, the form it has always asked, its columns' types in the base, the
 * lessons of its own Learn course, and the notes bin/seed-definitions.php writes for the columns
 * whose names look like slips. A change to a seed is a change to what a fresh site runs, made on
 * purpose, with the pins moved in the same commit.
 *
 * Run from the plugin root:  php bin/test-track-definitions.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $s, $d = null ) { return $s; }

require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-tracks.php'; // The four original tracks' pairs, which `validate()` reads.
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

// What each track has always been, apart from its form: the Learn course, its ID on Learn and the
// hours it is worked to, as the program map's rows held them until they were removed. The Developer
// Track's 0 is a value, not a gap (`WPCPM_Program::hours_targets()`).
$as_run = array(
	'150h'   => array( 'https://learn.wordpress.org/course/wordpress-credits/', 297853, 150 ),
	'50h'    => array( 'https://learn.wordpress.org/course/50-hours-wordpress-credits/', 322343, 50 ),
	'dev'    => array( 'https://learn.wordpress.org/course/wordpress-credits-developer-track/', 402893, 0 ),
	'design' => array( 'https://learn.wordpress.org/course/wordpress-credits-designer-track/', 403425, 150 ),
);

echo "=== Four seeds, one for each of the program's original tracks ===\n";

ck(
	'the four original tracks, by key, with their statuses and the hues their chips are painted in',
	wpcpm_seed_tracks(),
	array(
		'150h'   => array( 'status' => 'In Sensei', 'hue' => 'blue' ),
		'50h'    => array( 'status' => 'In Sensei 50h', 'hue' => 'purple' ),
		'dev'    => array( 'status' => 'Developer Track', 'hue' => 'teal' ),
		'design' => array( 'status' => 'Designer Track', 'hue' => 'pink' ),
	)
);
ck( 'and they are the pairs the four are locked to, in the program\'s order', array_combine( array_column( wpcpm_seed_tracks(), 'status' ), array_keys( wpcpm_seed_tracks() ) ), WPCPM_Tracks::RESERVED_PAIRS );

$lessons_named = array();
$why_named     = array();
$pinned        = array();
$counts        = array();
$notes         = wpcpm_seed_why();

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

	$pinned[ $key ] = md5( $stored );
	$counts[ $key ] = count( WPCPM_Track_Definition::compile_fields( $seed ) );

	ck( 'written as the seeds are written: pretty-printed, slashes and Unicode as they are, ending in a newline', $stored, wpcpm_seed_json( $seed ) );
	ck( 'its status and key are the pair its original track is locked to, and its name the one the lock names it by',
		array( $seed['status'], $seed['key'], $seed['label'] ),
		array( $status, WPCPM_Tracks::reserved_key( $status ), WPCPM_Tracks::reserved_label( $status ) ) );
	ck( 'its course, its course\'s ID on Learn, its hours target and its hue are the ones its track has always had',
		array( $seed['course_url'], $seed['learn_course_id'], $seed['hours_target'], $seed['hue'] ),
		array_merge( $as_run[ $key ], array( $track['hue'] ) ) );
	ck( 'it is stored normalized: normalizing it changes nothing', WPCPM_Track_Definition::normalize( $seed ), $seed );

	// The others as `WPCPM_Tracks::validation_context()` holds them with nothing compiled, and the
	// lock the store gives a definition holding one of the four statuses (`locked()`).
	$context = array(
		'tracks'           => array(),
		'labels'           => array(),
		'refused_statuses' => array( 'Graduate', 'Dropped out', 'Paused', 'Pending graduation' ),
		'reserved_columns' => $reserved,
		'locked'           => array(
			'status' => $status,
			'key'    => WPCPM_Tracks::reserved_key( $status ),
		),
	);

	foreach ( WPCPM_Tracks::RESERVED_PAIRS as $other_status => $other_key ) {
		if ( $other_status !== $status ) {
			$context['tracks'][ $other_status ] = $other_key;
			$context['labels'][ $other_status ] = WPCPM_Tracks::reserved_label( $other_status );
		}
	}

	ck( 'every rule accepts it under the lock its original track has', WPCPM_Track_Definition::validate( $seed, $context ), array() );
	ck( 'and so does the hours rule Publish asks', WPCPM_Track_Definition::check_hours( $seed ), array() );

	$course      = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/learn-course-' . $seed['learn_course_id'] . '.json' ), true );
	$known       = array();
	$wrong_types = array();
	$lesson_ids  = array();
	$off_lesson  = array();
	$off_note    = array();
	$seed_notes  = array_merge( $notes['*'], isset( $notes[ $key ] ) ? $notes[ $key ] : array() );

	foreach ( (array) $course['modules'] as $module ) {
		foreach ( (array) $module['lessons'] as $lesson ) {
			$known[] = $lesson['id'];
		}
	}

	foreach ( $seed['questions'] as $column => $spec ) {
		if ( ! isset( $spec['airtable_type'], $types[ $column ] ) || $spec['airtable_type'] !== $types[ $column ] ) {
			$wrong_types[] = $column;
		}

		if ( isset( $spec['learn_lesson_id'] ) ) {
			$lesson_ids[] = $spec['learn_lesson_id'];
		}

		if ( wpcpm_seed_lesson( $spec, $course ) !== ( isset( $spec['learn_lesson_id'] ) ? (int) $spec['learn_lesson_id'] : 0 ) ) {
			$off_lesson[] = $column;
		}

		if ( ( isset( $spec['why'] ) ? $spec['why'] : null ) !== ( isset( $seed_notes[ $column ] ) ? $seed_notes[ $column ] : null ) ) {
			$off_note[] = $column;
		}

		if ( isset( $spec['why'] ) ) {
			$why_named[ $key ][] = $column;
		}
	}

	ck( 'every question records its column\'s Airtable type, as the base has it', $wrong_types, array() );
	ck( 'every Learn lesson it names is a lesson of its own course', array_values( array_diff( $lesson_ids, $known ) ), array() );
	ck( 'and each question names the lesson its heading names, and no other', $off_lesson, array() );
	ck( 'each column whose name looks like a slip carries the note written for it, and no other column carries one', $off_note, array() );

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

echo "\n=== The seed files are the source ===\n";

// Pinned byte for byte: a change to a seed is a change to what a fresh site seeds and publishes,
// made on purpose, with these four hashes moved in the same commit.
ck( 'each seed file is the one pinned here, byte for byte', $pinned, array( '150h' => '7b2041736d97ad333ca10af3978716d9', '50h' => '4016e33d297d155a5eac77b885e1ba5e', 'dev' => 'beef3adb04a63dc0fa30ec3a0b1ec0e5', 'design' => '010d48ba2434933b60177473bc8043f9' ) );
ck( 'and each compiles to the form its track has always asked: 24, 11, 31 and 49 questions', $counts, array( '150h' => 24, '50h' => 11, 'dev' => 31, 'design' => 49 ) );
ck( 'nothing builds them from the hand-written forms any more: the builder and the two functions that read the PHP are gone',
	array( is_file( __DIR__ . '/build-seeds.php' ), function_exists( 'wpcpm_seed_build' ), function_exists( 'wpcpm_seed_definition' ) ),
	array( false, false, false ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
