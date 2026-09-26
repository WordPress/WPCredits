<?php
/**
 * A program track as data: the Track Builder's palette and definition (1.100.0).
 *
 * Every rule is a function of its arguments, so each is asked here directly: a definition every
 * rule accepts, then one thing broken at a time, each check expecting exactly the one code its
 * rule gives. What the rules must never refuse - the four original tracks' seeds and the forms
 * they compile to - is held in bin/test-track-definitions.php and bin/test-report-form.php.
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
// For their constants alone: the four original tracks' statuses and keys are the plugin's rather
// than the site's, so `status_reserved` reads them from `WPCPM_Tracks::RESERVED_PAIRS`, which names
// the program map's status constants.
require_once __DIR__ . '/../includes/class-wpcpm-program.php';
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

ck( 'seven hues, the four original tracks\' among them', array_keys( WPCPM_Track_Palette::HUES ), array( 'blue', 'cyan', 'teal', 'green', 'red', 'pink', 'purple' ) );
ck( 'slate and amber are no hue: they paint the two states on no track', array( WPCPM_Track_Palette::is_hue( 'slate' ), WPCPM_Track_Palette::is_hue( 'amber' ), WPCPM_Track_Palette::is_hue( 5 ) ), array( false, false, false ) );
ck( 'the chip rule, in the shape dashboard.css gives its own chips', WPCPM_Track_Palette::badge_rule( 'marketing', 'cyan' ), '.wpcpm-badge--marketing{background:rgba(8,145,178,0.12);border-color:rgba(8,145,178,0.35);}' );
ck( 'no rule for a hue outside the palette', WPCPM_Track_Palette::badge_rule( 'marketing', '#ff0000' ), '' );
ck( 'one key pattern, which the definition\'s rule reads as well', array( WPCPM_Track_Palette::KEY_PATTERN, preg_match( WPCPM_Track_Palette::KEY_PATTERN, 'marketing' ), preg_match( WPCPM_Track_Palette::KEY_PATTERN, 'Marketing' ) ), array( '/^[a-z0-9-]{2,20}$/', 1, 0 ) );
ck( 'and none for a key that could carry anything into the stylesheet', array( WPCPM_Track_Palette::badge_rule( 'Marketing', 'cyan' ), WPCPM_Track_Palette::badge_rule( 'a}b{', 'cyan' ), WPCPM_Track_Palette::badge_rule( 'm', 'cyan' ) ), array( '', '', '' ) );

echo "\n=== A definition every rule accepts ===\n";

ck( 'the valid definition passes', WPCPM_Track_Definition::validate( valid(), $context ), array() );
ck( 'and passes with no context at all', WPCPM_Track_Definition::validate( valid() ), array() );

echo "\n=== The track's rules, one at a time ===\n";

ck( 'a property this version does not know', refused( function ( &$d ) { $d['colour'] = 'red'; }, $context ), array( 'unknown_property' ) );
ck( 'a schema version the site does not read', refused( function ( &$d ) { $d['schema_version'] = 2; }, $context ), array( 'schema_version' ) );
ck( 'no status', refused( function ( &$d ) { $d['status'] = ''; }, $context ), array( 'status_empty' ) );
ck( 'a status over 100 characters', refused( function ( &$d ) { $d['status'] = str_repeat( 'a', 101 ); }, $context ), array( 'status_shape' ) );
ck( 'lengths are counted in characters: sixty accented letters are a status', refused( function ( &$d ) { $d['status'] = str_repeat( "\u{00E9}", 60 ); }, $context ), array() );
ck( 'and a hundred and one of them are too many', refused( function ( &$d ) { $d['status'] = str_repeat( "\u{00E9}", 101 ); }, $context ), array( 'status_shape' ) );
ck( 'a status on two lines', refused( function ( &$d ) { $d['status'] = "Marketing\nTrack"; }, $context ), array( 'status_shape' ) );
ck( 'another track\'s status, whatever its case', refused( function ( &$d ) { $d['status'] = 'designer track'; }, $context ), array( 'status_taken' ) );
$curly                                    = $context;
$curly['tracks']["Writer\u{2019}s Track"] = 'writers';
ck( 'and whichever apostrophe it is typed with', refused( function ( &$d ) { $d['status'] = "writer's track"; }, $curly ), array( 'status_taken' ) );
$accented                                = $context;
$accented['tracks']["\u{00C9}cole Track"] = 'ecole';
ck( 'and in any alphabet: an accented capital folds like a plain one', refused( function ( &$d ) { $d['status'] = "\u{00E9}cole track"; }, $accented ), array( 'status_taken' ) );
ck( 'a status that already means a finished student', refused( function ( &$d ) { $d['status'] = 'Graduate'; }, $context ), array( 'status_refused' ) );
ck( 'or a paused one', refused( function ( &$d ) { $d['status'] = 'paused'; }, $context ), array( 'status_refused' ) );
$locked           = $context;
$locked['locked'] = array( 'status' => 'Marketing Track', 'key' => 'marketing' );
ck( 'a published track keeps its status', refused( function ( &$d ) { $d['status'] = 'Marketing Program'; }, $locked ), array( 'status_locked' ) );
ck( 'and its key', refused( function ( &$d ) { $d['key'] = 'marketing-2'; }, $locked ), array( 'key_locked' ) );
// A track of somebody's own, published, is locked to its own copy rather than to an original
// track's pair, so moved onto one of the four statuses under another key again it is told both.
ck( 'a published track moved onto an original track\'s status and another key still hears that a published track keeps its key; a status of theirs with no key at all is refused by name',
	array(
		refused( function ( &$d ) { $d['status'] = 'Designer Track'; $d['key'] = 'marketing-2'; }, $locked ),
		refused( function ( &$d ) { $d['status'] = 'In Sensei'; unset( $d['key'] ); }, array() ),
	),
	array( array( 'status_reserved', 'status_taken', 'status_locked', 'key_locked' ), array( 'status_reserved', 'key_shape' ) ) );
ck( 'a key with capitals', refused( function ( &$d ) { $d['key'] = 'Marketing'; }, $context ), array( 'key_shape' ) );
ck( 'a key of one character, or of twenty-one', array( refused( function ( &$d ) { $d['key'] = 'm'; }, $context ), refused( function ( &$d ) { $d['key'] = str_repeat( 'm', 21 ); }, $context ) ), array( array( 'key_shape' ), array( 'key_shape' ) ) );
ck( 'a key a chip already paints', refused( function ( &$d ) { $d['key'] = 'sensei'; }, $context ), array( 'key_reserved' ) );
ck( 'an original track\'s key, under a status of its own', refused( function ( &$d ) { $d['key'] = 'design'; }, $context ), array( 'key_reserved' ) );
$research                             = $context;
$research['tracks']['Research Track'] = 'research';
ck( 'another Track Builder track\'s key', refused( function ( &$d ) { $d['key'] = 'research'; }, $research ), array( 'key_taken' ) );
// An original track's status belongs with its key whatever the context holds (the design's
// decision 37): refused by name, before `status_taken`, which can only say what the other tracks
// hold.
$originals = array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' );
$by_name   = array();
foreach ( $originals as $original => $original_key ) {
	$by_name[ $original ] = array(
		refused( function ( &$d ) use ( $original ) { $d['status'] = $original; }, array() ),
		refused( function ( &$d ) use ( $original_key ) { $d['key'] = $original_key; }, array() ),
	);
}
ck( 'an original track\'s status under a key of its own is refused by name, and its key under a status of its own as reserved, with no other track in the context', $by_name, array_fill_keys( array_keys( $originals ), array( array( 'status_reserved' ), array( 'key_reserved' ) ) ) );
ck( 'and with the other tracks there, by name before status_taken', refused( function ( &$d ) { $d['status'] = 'Designer Track'; }, $context ), array( 'status_reserved', 'status_taken' ) );
$said = WPCPM_Track_Definition::validate( array_merge( valid(), array( 'status' => 'In Sensei' ) ), array() );
ck( 'saying whose status it is, and the key that track keeps', $said[0]['message'], 'This status belongs to one of the program\'s four original tracks, which keeps the key 150h; a new track needs a status of its own.' );
// The store locks a definition holding one of the four statuses to that status's pair, published or
// not, so under that lock a key other than the pair's is the reserved status's refusal, whose
// sentence names the key the track keeps: "A published track keeps its key" would be untrue of a
// track never published, and the sentence is what a manager reads who typed another key on the
// original track's own page.
$kept  = array();
$moved = array();
$keeps = array();
foreach ( $originals as $original => $original_key ) {
	$lock = array(
		'tracks' => array_diff_key( $originals, array( $original => true ) ),
		'locked' => array( 'status' => $original, 'key' => $original_key ),
	);

	$moved_definition           = valid();
	$moved_definition['status'] = $original;
	$moved_errors               = WPCPM_Track_Definition::validate( $moved_definition, $lock );

	$kept[ $original ]  = refused( function ( &$d ) use ( $original, $original_key ) { $d['status'] = $original; $d['key'] = $original_key; }, $lock );
	$moved[ $original ] = array( codes( $moved_errors ), isset( $moved_errors[0]['message'] ) ? $moved_errors[0]['message'] : '' );
	$keeps[ $original ] = array( array( 'status_reserved' ), 'This status belongs to one of the program\'s four original tracks, which keeps the key ' . $original_key . '; a new track needs a status of its own.' );
}
ck( 'an original track\'s own definition keeps its status and reserved key under the lock the store gives it, and another key under that lock is refused by name, naming the key the track keeps, never as a published track\'s key',
	array( $kept, $moved ),
	array( array_fill_keys( array_keys( $originals ), array() ), $keeps ) );
ck( 'no name', refused( function ( &$d ) { $d['label'] = '  '; }, $context ), array( 'label_empty' ) );
$named           = $context;
$named['labels'] = array( 'In Sensei' => 'WordPress Credits Program 150h', 'Developer Track' => 'Developer Track' );
ck( 'a name another track already goes by, whatever its case', refused( function ( &$d ) { $d['label'] = 'wordpress credits program 150h'; }, $named ), array( 'label_taken' ) );
ck( 'or another track\'s status, which the institution import matches as well', refused( function ( &$d ) { $d['label'] = 'In Sensei 50h'; }, $named ), array( 'label_taken' ) );
ck( 'a name equal to the track\'s own status passes: the Developer Track is named so', refused( function ( &$d ) { $d['label'] = 'Marketing Track'; }, $named ), array() );
// The mirror of `label_taken` (the final review of T2a, its M1): one track's status may not be
// another's name either, so the refusal no longer depends on which of the two is published first.
ck( 'a status another track goes by as its name, whatever its case: the import would match one cell to both', array( refused( function ( &$d ) { $d['status'] = 'WordPress Credits Program 150h'; }, $named ), refused( function ( &$d ) { $d['status'] = 'wordpress credits program 150h'; }, $named ) ), array( array( 'status_named' ), array( 'status_named' ) ) );
ck( 'a course link that is not a Learn course', array( refused( function ( &$d ) { $d['course_url'] = 'http://learn.wordpress.org/course/marketing/'; }, $context ), refused( function ( &$d ) { $d['course_url'] = 'https://example.org/course/marketing/'; }, $context ) ), array( array( 'course_url' ), array( 'course_url' ) ) );
ck( 'no course link at all is fine', refused( function ( &$d ) { $d['course_url'] = ''; unset( $d['learn_course_id'] ); }, $context ), array() );
// The slug is capped at 120 characters here and in `WPCPM_Learn::COURSE_LINK`, which are one rule
// in two files: an unbounded repeat on a link a person can type is the shape that backtracks (the
// final review of T3c). Learn's own longest slug is nowhere near it.
ck( 'a course link whose name runs past 120 characters', refused( function ( &$d ) { $d['course_url'] = 'https://learn.wordpress.org/course/' . str_repeat( 'a', 121 ) . '/'; }, $context ), array( 'course_url' ) );
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
ck( 'or a negative whole number, which PHP turns into an integer key as well', refused( only( array( '-1' => $note ) ), $context ), array( 'column_numeric' ) );
ck( 'while a number with a leading zero stays a string, and passes', refused( only( array( '007' => $note ) ), $context ), array() );
ck( 'a column the syncs own, which a student could then change', refused( only( array( 'Status' => $note ) ), $context ), array( 'column_reserved' ) );
ck( 'or one a capital or a space away from it', array( refused( only( array( 'status' => $note ) ), $context ), refused( only( array( 'STATUS' => $note ) ), $context ), refused( only( array( 'Status ' => $note ) ), $context ) ), array( array( 'column_reserved' ), array( 'column_reserved' ), array( 'column_reserved' ) ) );
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
ck( 'a bound no JSON can hold', refused( only( array( 'Grade' => array( 'max' => INF ) + $grade ) ), $context ), array( 'number_bounds' ) );
ck( 'bounds on something that is not a number', refused( only( array( 'Notes' => $note + array( 'min' => 0 ) ) ), $context ), array( 'number_only' ) );

$pick = array( 'label' => 'Tool', 'type' => 'select', 'group' => 'project', 'options' => array( 'WordPress Studio', 'MAAMP', 'DevKinsta' ) );
ck( 'a select with its choices passes', refused( only( array( 'Tool' => $pick ) ), $context ), array() );
ck( 'a select with no choices', refused( only( array( 'Tool' => array_diff_key( $pick, array( 'options' => 1 ) ) ) ), $context ), array( 'options_shape' ) );
ck( 'a choice written twice', refused( only( array( 'Tool' => array( 'options' => array( 'A', 'A' ) ) + $pick ) ), $context ), array( 'options_shape' ) );
ck( 'an empty choice', refused( only( array( 'Tool' => array( 'options' => array( 'A', ' ' ) ) + $pick ) ), $context ), array( 'options_shape' ) );
ck( 'choices on something that is not a select', refused( only( array( 'Notes' => $note + array( 'options' => array( 'A' ) ) ) ), $context ), array( 'options_only' ) );
ck( 'a length limit on a link', refused( only( array( 'Link' => array( 'label' => 'Link', 'type' => 'url', 'group' => 'project', 'maxlength' => 100 ) ) ), $context ), array( 'maxlength_shape' ) );
ck( 'a length limit of nothing, or written as words', array( refused( only( array( 'Notes' => $note + array( 'maxlength' => 0 ) ) ), $context ), refused( only( array( 'Notes' => $note + array( 'maxlength' => '100' ) ) ), $context ) ), array( array( 'maxlength_shape' ), array( 'maxlength_shape' ) ) );
// Open item 7, settled 12 September 2026: text only. A textarea already draws maxlength="5000"
// and saves capped at the same figure, so a spec value would lower a limit rather than add one,
// and a browser maxlength on a prose box stops typing with no message.
ck( 'a length limit on a text box of many lines', refused( only( array( 'Notes' => $note + array( 'maxlength' => 200 ) ) ), $context ), array( 'maxlength_shape' ) );
ck( 'while one on a single-line box is accepted, which is where all four tracks use it', refused( only( array( 'Slack Name' => array( 'label' => 'Slack', 'type' => 'text', 'group' => 'project', 'maxlength' => 100 ) ) ), $context ), array() );

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

ck( 'the compiled row the program map reads', WPCPM_Track_Definition::row( valid(), 42 ), array( 'key' => 'marketing', 'label' => 'Marketing Track', 'course_url' => 'https://learn.wordpress.org/course/wordpress-credits-marketing-track/', 'course_id' => 500001, 'hours' => 120, 'hue' => 'cyan', 'automation' => false, 'post' => 42 ) );
$bare = valid();
unset( $bare['hours_target'], $bare['course_url'], $bare['learn_course_id'] );
$row = WPCPM_Track_Definition::row( $bare, '7', 1 );
ck( 'no hours target is null, not 0, so the map drops the row rather than printing a denominator', $row['hours'], null );
ck( 'no course is an empty link and ID 0', array( $row['course_url'], $row['course_id'] ), array( '', 0 ) );

// Every track runs from its definition, so a row no longer says where it runs from: `row()` takes the
// definition, its post and the automation tick, the third argument above, and writes no `source`.
ck( 'the row carries no source, and the automation tick and the post, typed', array( array_key_exists( 'source', $row ), $row['automation'], $row['post'] ), array( false, true, 7 ) );

echo "\n=== The hours rule, which check() asks and compile() does not (TRACKS-3) ===\n";

// The Student Report Card draws Total hours one way: as the hours box, which holds the column
// named Hours and nothing else, whatever group that column is in. So another question there
// reaches no student, and Hours anywhere else is drawn twice.
$lab                                       = valid();
$lab['questions']['Hours in research lab'] = array( 'label' => 'Hours in the research lab', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 1000, 'group' => 'hours' );
$elsewhere                                 = valid();
$elsewhere['questions']['Hours']['group']  = 'onboarding';

/** What the hours rule refused: each code with the column it names. */
function hours_refusals( array $definition ) {
	return array_map(
		function ( $error ) {
			return array( $error['code'], $error['where'] );
		},
		WPCPM_Track_Definition::check_hours( $definition )
	);
}

ck( 'the valid definition keeps it: Hours, alone in Total hours', hours_refusals( valid() ), array() );
ck( 'a question other than Hours in Total hours is refused, by its column', hours_refusals( $lab ), array( array( 'hours_only', 'Hours in research lab' ) ) );
ck( 'and Hours in another group', hours_refusals( $elsewhere ), array( array( 'hours_group', 'Hours' ) ) );
ck( 'a form with no Hours at all keeps it: a track may count no hours', hours_refusals( array( 'questions' => array( 'Notes' => $note ) ) ), array() );
ck( 'the rule reads the column verbatim, as the hours box does: hours in lower case is not Hours', hours_refusals( array( 'questions' => array( 'hours' => array( 'group' => 'hours' ) + $grade ) ) ), array( array( 'hours_only', 'hours' ) ) );
ck( 'and validate() does not ask it, since compile() runs validate() on every published track and would leave one out',
	array( refused( function ( &$d ) use ( $lab ) { $d = $lab; }, $context ), refused( function ( &$d ) use ( $elsewhere ) { $d = $elsewhere; }, $context ) ),
	array( array(), array() ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
