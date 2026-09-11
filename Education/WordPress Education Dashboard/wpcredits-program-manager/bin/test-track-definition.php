<?php
/**
 * A program track as data: the Track Builder's palette and definition (1.100.0).
 *
 * Every rule is a function of its arguments, so each is asked here directly: a definition every
 * rule accepts, then one thing broken at a time, each check expecting exactly the one code its
 * rule gives. What the rules must never refuse - the four hand-written forms - is held in
 * bin/test-report-form.php, which loads the form.
 *
 * Run from the plugin root:  php bin/test-track-definition.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $s, $d = null ) { return $s; }
function wp_json_encode( $v, $o = 0 ) { return json_encode( $v, $o ); }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : addslashes( (string) $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }

require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';

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

/** The codes of what `validate()` refused, in the order it found them. */
function codes( array $errors ) {
	return array_column( $errors, 'code' );
}

/** A definition every rule accepts. Each check below breaks one thing about a copy of it. */
function valid() {
	return array(
		'schema_version'  => 1,
		'status'          => 'Marketing Track',
		'key'             => 'marketing',
		'label'           => 'Marketing Track',
		'course_url'      => 'https://learn.wordpress.org/course/wordpress-credits-marketing-track/',
		'learn_course_id' => 500001,
		'hours_target'    => 120,
		'hue'             => 'cyan',
		'questions'       => array(
			'Hours'                               => array( 'label' => 'Hours contributed', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 10000, 'group' => 'hours', 'airtable_type' => 'number' ),
			'Practical: Campaign Brief - Notes'   => array( 'lead' => 'Practical: Write a Campaign Brief', 'label' => 'Your notes', 'type' => 'textarea', 'group' => 'project', 'airtable_type' => 'multilineText', 'learn_lesson_id' => 500101, 'why' => 'The base shortens the lesson name.' ),
			'Practical: Campaign Brief - Link'    => array( 'label' => 'A link to your brief', 'type' => 'url', 'group' => 'project', 'row' => 'brief', 'airtable_type' => 'url' ),
			'Practical: Campaign Brief - Channel' => array( 'label' => 'The channel you chose', 'type' => 'select', 'group' => 'project', 'options' => array( 'Newsletter', 'Social', 'Blog' ), 'row' => 'brief', 'stack' => true, 'airtable_type' => 'singleSelect' ),
			'Main Contribution Team'              => array( 'label' => 'Your contribution team', 'type' => 'team', 'group' => 'project', 'airtable_type' => 'multipleRecordLinks' ),
			'Alumni program: personal email'      => array( 'label' => 'A personal email', 'type' => 'email', 'group' => 'project', 'hide_from_institution' => true, 'airtable_type' => 'email' ),
			'Company '                            => array( 'label' => 'Your company', 'type' => 'text', 'group' => 'wrapup', 'maxlength' => 100, 'required' => true, 'airtable_type' => 'singleLineText' ),
		),
	);
}

$context = array(
	'tracks'           => array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ),
	'refused_statuses' => array( 'Graduate', 'Dropped out', 'Paused', 'Pending graduation' ),
	'reserved_columns' => array( 'Name', 'Email', 'Status', 'Mentor', 'Educational institution', 'Internship Start Date', 'Internship End Date' ),
);

/** What `validate()` says about a copy of the valid definition with one change made to it. */
function refused( callable $change, array $context ) {
	$definition = valid();
	$change( $definition );

	return codes( WPCPM_Track_Definition::validate( $definition, $context ) );
}

/** A change that swaps the valid definition's questions for exactly these. */
function only( array $questions ) {
	return function ( &$d ) use ( $questions ) {
		$d['questions'] = $questions;
	};
}

echo "=== The palette ===\n";

ck( 'seven hues, the four built-in tracks\' among them', array_keys( WPCPM_Track_Palette::HUES ), array( 'blue', 'cyan', 'teal', 'green', 'red', 'pink', 'purple' ) );
ck( 'slate and amber are no hue: they paint the two states on no track', array( WPCPM_Track_Palette::is_hue( 'slate' ), WPCPM_Track_Palette::is_hue( 'amber' ), WPCPM_Track_Palette::is_hue( 5 ) ), array( false, false, false ) );
ck( 'the chip rule, in the shape dashboard.css gives its own chips', WPCPM_Track_Palette::badge_rule( 'marketing', 'cyan' ), '.wpcpm-badge--marketing{background:rgba(8,145,178,0.12);border-color:rgba(8,145,178,0.35);}' );
ck( 'no rule for a hue outside the palette', WPCPM_Track_Palette::badge_rule( 'marketing', '#ff0000' ), '' );
ck( 'and none for a key that could carry anything into the stylesheet', array( WPCPM_Track_Palette::badge_rule( 'Marketing', 'cyan' ), WPCPM_Track_Palette::badge_rule( 'a}b{', 'cyan' ), WPCPM_Track_Palette::badge_rule( 'm', 'cyan' ) ), array( '', '', '' ) );

echo "\n=== A definition every rule accepts ===\n";

ck( 'the valid definition passes', WPCPM_Track_Definition::validate( valid(), $context ), array() );
ck( 'and passes with no context at all', WPCPM_Track_Definition::validate( valid() ), array() );

echo "\n=== The track's rules, one at a time ===\n";

ck( 'a property this version does not know', refused( function ( &$d ) { $d['colour'] = 'red'; }, $context ), array( 'unknown_property' ) );
ck( 'a schema version the site does not read', refused( function ( &$d ) { $d['schema_version'] = 2; }, $context ), array( 'schema_version' ) );
ck( 'no status', refused( function ( &$d ) { $d['status'] = ''; }, $context ), array( 'status_empty' ) );
ck( 'a status over 100 characters', refused( function ( &$d ) { $d['status'] = str_repeat( 'a', 101 ); }, $context ), array( 'status_shape' ) );
ck( 'a status on two lines', refused( function ( &$d ) { $d['status'] = "Marketing\nTrack"; }, $context ), array( 'status_shape' ) );
ck( 'another track\'s status, whatever its case', refused( function ( &$d ) { $d['status'] = 'designer track'; }, $context ), array( 'status_taken' ) );
$curly                                    = $context;
$curly['tracks']["Writer\u{2019}s Track"] = 'writers';
ck( 'and whichever apostrophe it is typed with', refused( function ( &$d ) { $d['status'] = "writer's track"; }, $curly ), array( 'status_taken' ) );
ck( 'a status that already means a finished student', refused( function ( &$d ) { $d['status'] = 'Graduate'; }, $context ), array( 'status_refused' ) );
ck( 'or a paused one', refused( function ( &$d ) { $d['status'] = 'paused'; }, $context ), array( 'status_refused' ) );
$locked           = $context;
$locked['locked'] = array( 'status' => 'Marketing Track', 'key' => 'marketing' );
ck( 'a published track keeps its status', refused( function ( &$d ) { $d['status'] = 'Marketing Program'; }, $locked ), array( 'status_locked' ) );
ck( 'and its key', refused( function ( &$d ) { $d['key'] = 'marketing-2'; }, $locked ), array( 'key_locked' ) );
ck( 'a key with capitals', refused( function ( &$d ) { $d['key'] = 'Marketing'; }, $context ), array( 'key_shape' ) );
ck( 'a key of one character, or of twenty-one', array( refused( function ( &$d ) { $d['key'] = 'm'; }, $context ), refused( function ( &$d ) { $d['key'] = str_repeat( 'm', 21 ); }, $context ) ), array( array( 'key_shape' ), array( 'key_shape' ) ) );
ck( 'a key a chip already paints', refused( function ( &$d ) { $d['key'] = 'sensei'; }, $context ), array( 'key_reserved' ) );
ck( 'a built-in track\'s key', refused( function ( &$d ) { $d['key'] = 'design'; }, $context ), array( 'key_reserved' ) );
$research                             = $context;
$research['tracks']['Research Track'] = 'research';
ck( 'another Track Builder track\'s key', refused( function ( &$d ) { $d['key'] = 'research'; }, $research ), array( 'key_taken' ) );
$builtin = array(
	'tracks' => array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev' ),
	'locked' => array( 'status' => 'Designer Track', 'key' => 'design' ),
);
ck( 'a built-in track\'s own definition keeps its reserved key', refused( function ( &$d ) { $d['status'] = 'Designer Track'; $d['key'] = 'design'; }, $builtin ), array() );
ck( 'no name', refused( function ( &$d ) { $d['label'] = '  '; }, $context ), array( 'label_empty' ) );
ck( 'a course link that is not a Learn course', array( refused( function ( &$d ) { $d['course_url'] = 'http://learn.wordpress.org/course/marketing/'; }, $context ), refused( function ( &$d ) { $d['course_url'] = 'https://example.org/course/marketing/'; }, $context ) ), array( array( 'course_url' ), array( 'course_url' ) ) );
ck( 'no course link at all is fine', refused( function ( &$d ) { $d['course_url'] = ''; unset( $d['learn_course_id'] ); }, $context ), array() );
ck( 'a course ID that is not a whole number', array( refused( function ( &$d ) { $d['learn_course_id'] = -1; }, $context ), refused( function ( &$d ) { $d['learn_course_id'] = '500001'; }, $context ) ), array( array( 'course_id' ), array( 'course_id' ) ) );
ck( 'an hours target that is not a whole number of hours', array( refused( function ( &$d ) { $d['hours_target'] = -5; }, $context ), refused( function ( &$d ) { $d['hours_target'] = '150'; }, $context ) ), array( array( 'hours_target' ), array( 'hours_target' ) ) );
ck( 'no hours target is a track that counts none, and 0 says the same', array( refused( function ( &$d ) { unset( $d['hours_target'] ); }, $context ), refused( function ( &$d ) { $d['hours_target'] = 0; }, $context ) ), array( array(), array() ) );
ck( 'a hue outside the palette, a reserved one included, or none', array( refused( function ( &$d ) { $d['hue'] = 'slate'; }, $context ), refused( function ( &$d ) { unset( $d['hue'] ); }, $context ) ), array( array( 'hue' ), array( 'hue' ) ) );
ck( 'questions that are not a list of questions', refused( function ( &$d ) { $d['questions'] = 'none'; }, $context ), array( 'questions_shape' ) );
ck( 'an empty form is a blank track, not an error', refused( function ( &$d ) { $d['questions'] = array(); }, $context ), array() );

echo "\n=== A question's rules, one at a time ===\n";

$note = array( 'label' => 'Your notes', 'type' => 'textarea', 'group' => 'project' );

ck( 'a column name of spaces', refused( only( array( '   ' => $note ) ), $context ), array( 'column_shape' ) );
ck( 'a column name over 255 characters', refused( only( array( str_repeat( 'c', 256 ) => $note ) ), $context ), array( 'column_shape' ) );
ck( 'a column name that is a number alone', refused( only( array( '2024' => $note ) ), $context ), array( 'column_numeric' ) );
ck( 'a column the syncs own, which a student could then change', refused( only( array( 'Status' => $note ) ), $context ), array( 'column_reserved' ) );
ck( 'a column name ending in a space is taken as it is, like the base\'s own "Company "', refused( only( array( 'Company ' => $note ) ), $context ), array() );
ck( 'a question that is not a question', refused( only( array( 'Notes' => 'textarea' ) ), $context ), array( 'question_shape' ) );
ck( 'a property this version does not know', refused( only( array( 'Notes' => $note + array( 'colour' => 'red' ) ) ), $context ), array( 'unknown_property' ) );
ck( 'a control the form does not draw', refused( only( array( 'Notes' => array( 'type' => 'date' ) + $note ) ), $context ), array( 'type_unknown' ) );
ck( 'no words for the student', refused( only( array( 'Notes' => array( 'label' => '' ) + $note ) ), $context ), array( 'label_empty' ) );
ck( 'a group the form does not have', refused( only( array( 'Notes' => array( 'group' => 'extra' ) + $note ) ), $context ), array( 'group_unknown' ) );
ck( 'a hint that is not words', refused( only( array( 'Notes' => $note + array( 'help' => array( 'x' ) ) ) ), $context ), array( 'text_shape' ) );
ck( 'a flag that is not true', refused( only( array( 'Notes' => $note + array( 'required' => 1 ) ) ), $context ), array( 'flag_shape' ) );
ck( 'or a flag that is null, which isset() would not see', refused( only( array( 'Notes' => $note + array( 'required' => null ) ) ), $context ), array( 'flag_shape' ) );
ck( 'a row named like a sentence', refused( only( array( 'Notes' => $note + array( 'row' => 'Brief A' ) ) ), $context ), array( 'row_shape' ) );
ck( 'a stack outside a row', refused( only( array( 'Notes' => $note + array( 'stack' => true ) ) ), $context ), array( 'stack_without_row' ) );

$grade = array( 'label' => 'Final grade', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max' => 100, 'group' => 'onboarding' );
ck( 'a number with every bound passes', refused( only( array( 'Grade' => $grade ) ), $context ), array() );
ck( 'a number with no step', refused( only( array( 'Grade' => array_diff_key( $grade, array( 'step' => 1 ) ) ) ), $context ), array( 'number_bounds' ) );
ck( 'a number whose lowest value is above its highest', refused( only( array( 'Grade' => array( 'min' => 101 ) + $grade ) ), $context ), array( 'number_bounds' ) );
ck( 'a number with a step of zero', refused( only( array( 'Grade' => array( 'step' => '0' ) + $grade ) ), $context ), array( 'number_bounds' ) );
ck( 'bounds on something that is not a number', refused( only( array( 'Notes' => $note + array( 'min' => 0 ) ) ), $context ), array( 'number_only' ) );

$pick = array( 'label' => 'Tool', 'type' => 'select', 'group' => 'project', 'options' => array( 'WordPress Studio', 'MAAMP', 'DevKinsta' ) );
ck( 'a select with its choices passes', refused( only( array( 'Tool' => $pick ) ), $context ), array() );
ck( 'a select with no choices', refused( only( array( 'Tool' => array_diff_key( $pick, array( 'options' => 1 ) ) ) ), $context ), array( 'options_shape' ) );
ck( 'a choice written twice', refused( only( array( 'Tool' => array( 'options' => array( 'A', 'A' ) ) + $pick ) ), $context ), array( 'options_shape' ) );
ck( 'an empty choice', refused( only( array( 'Tool' => array( 'options' => array( 'A', ' ' ) ) + $pick ) ), $context ), array( 'options_shape' ) );
ck( 'choices on something that is not a select', refused( only( array( 'Notes' => $note + array( 'options' => array( 'A' ) ) ) ), $context ), array( 'options_only' ) );
ck( 'a length limit on a link', refused( only( array( 'Link' => array( 'label' => 'Link', 'type' => 'url', 'group' => 'project', 'maxlength' => 100 ) ) ), $context ), array( 'maxlength_shape' ) );
ck( 'a length limit of nothing, or written as words', array( refused( only( array( 'Notes' => $note + array( 'maxlength' => 0 ) ) ), $context ), refused( only( array( 'Notes' => $note + array( 'maxlength' => '100' ) ) ), $context ) ), array( array( 'maxlength_shape' ), array( 'maxlength_shape' ) ) );
ck( 'monospace on a one-line box', refused( only( array( 'Name on the brief' => array( 'label' => 'Name', 'type' => 'text', 'group' => 'project', 'mono' => true ) ) ), $context ), array( 'mono_only' ) );

$team = array( 'label' => 'Your contribution team', 'type' => 'team', 'group' => 'project' );
ck( 'the team question writing any column but its own', refused( only( array( 'Second Team' => $team ) ), $context ), array( 'team_column' ) );
ck( 'the team asked twice', refused( only( array( 'Main Contribution Team' => $team, 'Second Team' => $team ) ), $context ), array( 'team_column', 'team_twice' ) );
ck( 'a computed Airtable column, which refuses every write', refused( only( array( 'Notes' => $note + array( 'airtable_type' => 'formula' ) ) ), $context ), array( 'airtable_type' ) );
ck( 'a linked-record column under anything but the team question', refused( only( array( 'Link' => array( 'label' => 'Link', 'type' => 'url', 'group' => 'project', 'airtable_type' => 'multipleRecordLinks' ) ) ), $context ), array( 'airtable_link' ) );
ck( 'and the team question under anything but a linked-record column', refused( only( array( 'Main Contribution Team' => $team + array( 'airtable_type' => 'singleLineText' ) ) ), $context ), array( 'airtable_link' ) );
ck( 'a Learn lesson ID that is not a whole number', refused( only( array( 'Notes' => $note + array( 'learn_lesson_id' => -3 ) ) ), $context ), array( 'lesson_shape' ) );

echo "\n=== normalize() ===\n";

$messy                    = valid();
$messy['status']          = '  Marketing Track ';
$messy['label']           = ' Marketing Track  ';
$messy['hours_target']    = '';
$messy['questions']['Company ']['required'] = false;
unset( $messy['schema_version'], $messy['questions']['Alumni program: personal email']['hide_from_institution'] );
$clean = WPCPM_Track_Definition::normalize( $messy );

ck( 'the status and the name are trimmed', array( $clean['status'], $clean['label'] ), array( 'Marketing Track', 'Marketing Track' ) );
ck( 'a column name is not: it is the key, verbatim', array_key_exists( 'Company ', $clean['questions'] ), true );
ck( 'an empty hours target is no target, and is stored as nothing', array_key_exists( 'hours_target', $clean ), false );
$null                 = valid();
$null['hours_target'] = null;
ck( 'so is a null one', array_key_exists( 'hours_target', WPCPM_Track_Definition::normalize( $null ) ), false );
ck( 'the schema version is filled in', $clean['schema_version'], 1 );
ck( 'an email question is kept off the institution\'s view whatever it said', $clean['questions']['Alumni program: personal email']['hide_from_institution'], true );
ck( 'a flag that is false is dropped, so two definitions that mean the same are the same bytes', array_key_exists( 'required', $clean['questions']['Company '] ), false );
$unset                                      = valid();
$unset['questions']['Company ']['required'] = null;
ck( 'and so is a flag that is null', array_key_exists( 'required', WPCPM_Track_Definition::normalize( $unset )['questions']['Company '] ), false );
ck( 'and the questions keep their order', array_keys( $clean['questions'] ), array_keys( valid()['questions'] ) );
ck( 'normalizing twice changes nothing', WPCPM_Track_Definition::normalize( $clean ), $clean );

echo "\n=== Storage ===\n";

$tricky = valid();
$tricky['questions']['Practical: Campaign Brief - Notes']['label'] = 'Back\\slash, "quoted", the Site' . "\u{2019}" . 's own';
$json   = WPCPM_Track_Definition::encode( $tricky );

ck( 'a definition survives encoding byte for byte', WPCPM_Track_Definition::decode( $json ), $tricky );
ck( 'the typographic apostrophe is stored as the character it is', false !== strpos( $json, "Site\u{2019}s" ), true );
ck( 'and a course address reads as one', false !== strpos( $json, 'https://learn.wordpress.org/course/' ), true );
ck( 'slashed on its way into the meta store, it comes back whole', WPCPM_Track_Definition::decode( wp_unslash( wp_slash( $json ) ) ), $tricky );
ck( 'unslashed without the slashing, it would come back as nothing at all', WPCPM_Track_Definition::decode( wp_unslash( $json ) ), null );
ck( 'nothing that is not a stored definition decodes', array( WPCPM_Track_Definition::decode( '' ), WPCPM_Track_Definition::decode( 'not json' ), WPCPM_Track_Definition::decode( 5 ), WPCPM_Track_Definition::decode( '"text"' ) ), array( null, null, null, null ) );

echo "\n=== What the live site runs ===\n";

$fields = WPCPM_Track_Definition::compile_fields( valid() );
$left   = array();
foreach ( $fields as $spec ) {
	$left = array_merge( $left, array_values( array_intersect( array_keys( $spec ), WPCPM_Track_Definition::AUTHORING ) ) );
}

ck( 'the form has every question, in order', array_keys( $fields ), array_keys( valid()['questions'] ) );
ck( 'and none of the three properties the form never sees', $left, array() );
ck( 'the institution flag stays: the form reads it', $fields['Alumni program: personal email']['hide_from_institution'], true );
ck( 'everything else of a question comes through untouched', $fields['Practical: Campaign Brief - Channel'], array( 'label' => 'The channel you chose', 'type' => 'select', 'group' => 'project', 'options' => array( 'Newsletter', 'Social', 'Blog' ), 'row' => 'brief', 'stack' => true ) );

ck( 'the compiled row the program map reads', WPCPM_Track_Definition::row( valid(), 42 ), array( 'key' => 'marketing', 'label' => 'Marketing Track', 'course_url' => 'https://learn.wordpress.org/course/wordpress-credits-marketing-track/', 'course_id' => 500001, 'hours' => 120, 'hue' => 'cyan', 'source' => 'definition', 'automation' => false, 'post' => 42 ) );
$bare = valid();
unset( $bare['hours_target'], $bare['course_url'], $bare['learn_course_id'] );
$row = WPCPM_Track_Definition::row( $bare, '7', 'builtin', 1 );
ck( 'no hours target is null, not 0, so the map drops the row rather than printing a denominator', $row['hours'], null );
ck( 'no course is an empty link and ID 0', array( $row['course_url'], $row['course_id'] ), array( '', 0 ) );
ck( 'the source and the automation tick are carried, typed', array( $row['source'], $row['automation'], $row['post'] ), array( 'builtin', true, 7 ) );
ck( 'any source but builtin is a definition', WPCPM_Track_Definition::row( valid(), 1, 'whatever' )['source'], 'definition' );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
