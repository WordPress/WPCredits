<?php
/**
 * Build the four seed definitions from the hand-written tracks (Track Builder, phase T2a).
 *
 * Shared by bin/build-seeds.php, which writes them to includes/tracks/seeds/, and
 * bin/test-track-definitions.php, which holds the files to them. The caller loads `WPCPM_Program`,
 * `WPCPM_Student_Report_Form` and `WPCPM_Track_Definition` first.
 *
 * Everything is read from the repository: the forms from `builtin_fields()`, each column's
 * Airtable type from bin/fixtures/reports-table-fields.json (`all_types`, read from the base's
 * metadata API) and the lessons from bin/fixtures/learn-course-<id>.json. Nothing reaches the
 * network, so every build gives the same bytes.
 */

/**
 * The four built-in tracks: key => the status that holds it, and the palette hue its chip is
 * painted in by the stylesheets.
 *
 * @return array
 */
function wpcpm_seed_tracks() {
	return array(
		'150h'   => array(
			'status' => WPCPM_Program::STATUS_150H,
			'hue'    => 'blue',
		),
		'50h'    => array(
			'status' => WPCPM_Program::STATUS_50H,
			'hue'    => 'purple',
		),
		'dev'    => array(
			'status' => WPCPM_Program::STATUS_DEV,
			'hue'    => 'teal',
		),
		'design' => array(
			'status' => WPCPM_Program::STATUS_DESIGN,
			'hue'    => 'pink',
		),
	);
}

/**
 * Where a track's seed is kept.
 *
 * @param string $key Track key.
 * @return string
 */
function wpcpm_seed_path( $key ) {
	return dirname( __DIR__ ) . '/includes/tracks/seeds/' . $key . '.json';
}

/**
 * A seed as it is stored: pretty-printed, so a change reads as a diff, and ending in a newline.
 *
 * @param array $definition Track definition.
 * @return string
 */
function wpcpm_seed_json( array $definition ) {
	return json_encode( $definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
}

/**
 * One track's seed, built from the repository alone.
 *
 * @param string $key Track key.
 * @return array
 */
function wpcpm_seed_build( $key ) {
	$status  = wpcpm_seed_tracks()[ $key ]['status'];
	$reports = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/reports-table-fields.json' ), true );
	$course  = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/learn-course-' . WPCPM_Program::course_ids()[ $status ] . '.json' ), true );

	return wpcpm_seed_definition( $key, (array) $reports['all_types'], (array) $course );
}

/**
 * One track's definition: its PHP form, with what the form never shows recorded beside each
 * question - the column's Airtable type, the Learn lesson it reports on, and why its name looks
 * the way it does.
 *
 * @param string $key    Track key.
 * @param array  $types  Column => Airtable type.
 * @param array  $course The track's Learn course: `modules`, each with `title` and `lessons`.
 * @return array
 */
function wpcpm_seed_definition( $key, array $types, array $course ) {
	$track     = wpcpm_seed_tracks()[ $key ];
	$status    = $track['status'];
	$notes     = wpcpm_seed_why();
	$notes     = array_merge( $notes['*'], isset( $notes[ $key ] ) ? $notes[ $key ] : array() );
	$hours     = WPCPM_Program::hours_targets();
	$questions = array();

	foreach ( WPCPM_Student_Report_Form::builtin_fields( $key ) as $column => $spec ) {
		if ( isset( $types[ $column ] ) ) {
			$spec['airtable_type'] = $types[ $column ];
		}

		$lesson = wpcpm_seed_lesson( $spec, $course );

		if ( $lesson > 0 ) {
			$spec['learn_lesson_id'] = $lesson;
		}

		if ( isset( $notes[ $column ] ) ) {
			$spec['why'] = $notes[ $column ];
		}

		$questions[ $column ] = $spec;
	}

	return WPCPM_Track_Definition::normalize(
		array(
			'schema_version'  => WPCPM_Track_Definition::SCHEMA_VERSION,
			'status'          => $status,
			'key'             => $key,
			'label'           => WPCPM_Program::labels()[ $status ],
			'course_url'      => WPCPM_Program::courses()[ $status ],
			'learn_course_id' => WPCPM_Program::course_ids()[ $status ],
			'hours_target'    => $hours[ $status ],
			'hue'             => $track['hue'],
			'questions'       => $questions,
		)
	);
}

/**
 * The Learn lesson a question reports on: the lesson its heading names, once case and
 * apostrophes are folded (the design's 2.6).
 *
 * The subgroup is tried before the lead, being the nearer heading, and the lessons of the
 * question's own module before the others.
 *
 * @param array $spec   The question.
 * @param array $course The track's Learn course.
 * @return int The lesson's post ID, or 0 when no heading names a lesson.
 */
function wpcpm_seed_lesson( array $spec, array $course ) {
	$module_of = array(
		'onboarding' => 'Onboarding',
		'project'    => 'Project',
		'wrapup'     => 'Wrap-up',
	);
	$own       = isset( $spec['group'], $module_of[ $spec['group'] ] ) ? $module_of[ $spec['group'] ] : '';
	$modules   = isset( $course['modules'] ) ? (array) $course['modules'] : array();
	$ordered   = array_merge(
		array_filter(
			$modules,
			static function ( $module ) use ( $own ) {
				return $module['title'] === $own;
			}
		),
		array_filter(
			$modules,
			static function ( $module ) use ( $own ) {
				return $module['title'] !== $own;
			}
		)
	);

	foreach ( array( 'subgroup', 'lead' ) as $heading ) {
		if ( empty( $spec[ $heading ] ) ) {
			continue;
		}

		foreach ( $ordered as $module ) {
			foreach ( (array) $module['lessons'] as $lesson ) {
				if ( wpcpm_seed_fold( $lesson['title'] ) === wpcpm_seed_fold( $spec[ $heading ] ) ) {
					return (int) $lesson['id'];
				}
			}
		}
	}

	return 0;
}

/**
 * A heading or a lesson title as the match sees it: the typographic apostrophe read as the
 * plain one, one space for any run of them, trimmed, lower case.
 *
 * @param string $text Text.
 * @return string
 */
function wpcpm_seed_fold( $text ) {
	$text = str_replace( array( "\u{2019}", "\u{2018}" ), "'", (string) $text );

	return mb_strtolower( trim( (string) preg_replace( '/\s+/', ' ', $text ) ), 'UTF-8' );
}

/**
 * Why a column's name looks like a slip, by track, and under `*` for every track that asks it.
 *
 * Written by hand for the developer who reads a seed and would otherwise correct the name (the
 * design's section 8). No student sees these: compiling strips `why`.
 *
 * @return array<string, array<string, string>>
 */
function wpcpm_seed_why() {
	$site      = "The base spells Site\u{2019}s with the typographic apostrophe (U+2019) where Learn writes a plain one. The name is the base's column: a plain apostrophe here names a column that does not exist.";
	$library   = 'The base shortens the lesson Duplicate and Explore the WordPress Design Library to this name and ends its link and image columns in lower case. The names are the base\'s columns, not slips to correct.';
	$portfolio = 'On this track the lesson is Create your portfolio, so the column the other tracks ask as a personal website is asked as a portfolio: one site, one column, whatever a course calls it.';
	$meetings  = 'On this track the question is asked inside the Alumni Program lesson and heads that run, rather than sitting beside the team list as on the 150-hour course. It keeps its column, so its answers stay where every track keeps them.';

	return array(
		'*'      => array(
			'Advance WordPress User - final grade' => 'The column says Advance and the label says Advanced: the base\'s spelling is the column\'s name, and the label is what a student reads.',
		),
		'dev'    => array(
			'Slack/GitHub/Blog WordPress Community meetings/discussions' => $meetings,
		),
		'design' => array(
			'Personal Website URL'                            => $portfolio,
			'Post Reflection: Building Your Personal Website' => $portfolio,
			'Practical: Duplicate & Explore WP Design Library - Reflection' => $library,
			'Practical: Duplicate & Explore WP Design Library - link' => $library,
			'Practical: Duplicate & Explore WP Design Library - image' => $library,
			'Practical: Local WordPress Environment for Design Testing - Tool used' => 'The choices are spelled as the base spells them, MAAMP included: a choice spelled any other way is one the column does not have, and Airtable refuses the whole save.',
			"Practical: Change Your Site\u{2019}s Global Styles - Notes" => $site,
			"Practical: Change Your Site\u{2019}s Global Styles - Before Screenshot" => $site,
			"Practical: Change Your Site\u{2019}s Global Styles - After Screenshot" => $site,
			'Slack/GitHub/Blog WordPress Community meetings/discussions' => $meetings,
		),
	);
}
