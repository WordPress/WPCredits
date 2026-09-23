<?php
/**
 * A track's questions as a list somebody edits (Track Builder, phase T3a).
 *
 * `WPCPM_Track_Questions` holds every rule the question editor needs and touches neither WordPress
 * nor Airtable, so this suite loads the real class and stands nothing in for it.
 *
 * Run from the plugin root:  php bin/test-track-questions.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-questions.php';

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

/** A track in the shape the definition stores: four questions across three groups. */
function questions() {
	return array(
		'Hours'        => array( 'type' => 'number', 'group' => 'hours', 'label' => 'Hours' ),
		'Slack name'   => array( 'type' => 'text', 'group' => 'onboarding', 'label' => 'Your Slack name' ),
		'WP.org name'  => array( 'type' => 'text', 'group' => 'onboarding', 'label' => 'Your WordPress.org name' ),
		'What you did' => array( 'type' => 'textarea', 'group' => 'project', 'label' => 'What you did' ),
	);
}

echo "=== Adding ===\n";

ck( 'a new onboarding question lands after the last onboarding question, not at the end',
    array_keys( WPCPM_Track_Questions::add( questions(), 'Your blog', array( 'type' => 'url', 'group' => 'onboarding' ) ) ),
    array( 'Hours', 'Slack name', 'WP.org name', 'Your blog', 'What you did' ) );

ck( 'a question of a group nobody uses yet goes at the end',
    array_keys( WPCPM_Track_Questions::add( questions(), 'Anything else', array( 'type' => 'textarea', 'group' => 'wrapup' ) ) ),
    array( 'Hours', 'Slack name', 'WP.org name', 'What you did', 'Anything else' ) );

ck( 'a column another question already uses is refused',
    WPCPM_Track_Questions::add( questions(), 'Slack name', array( 'type' => 'text', 'group' => 'project' ) ),
    null );

ck( 'a column name is taken verbatim, trailing space and all',
    array_keys( WPCPM_Track_Questions::add( questions(), 'Company ', array( 'type' => 'text', 'group' => 'project' ) ) ),
    array( 'Hours', 'Slack name', 'WP.org name', 'What you did', 'Company ' ) );

echo "\n=== Moving ===\n";

ck( 'up swaps with the question above it in the same group',
    array_keys( WPCPM_Track_Questions::move( questions(), 'WP.org name', 'up' ) ),
    array( 'Hours', 'WP.org name', 'Slack name', 'What you did' ) );

ck( 'down swaps the other way',
    array_keys( WPCPM_Track_Questions::move( questions(), 'Slack name', 'down' ) ),
    array( 'Hours', 'WP.org name', 'Slack name', 'What you did' ) );

ck( 'the first of its group cannot go up, and nothing else moves either',
    array_keys( WPCPM_Track_Questions::move( questions(), 'Slack name', 'up' ) ),
    array( 'Hours', 'Slack name', 'WP.org name', 'What you did' ) );

ck( 'the last of its group cannot go down',
    array_keys( WPCPM_Track_Questions::move( questions(), 'WP.org name', 'down' ) ),
    array( 'Hours', 'Slack name', 'WP.org name', 'What you did' ) );

ck( 'the only question of its group cannot move in either direction',
    array(
        array_keys( WPCPM_Track_Questions::move( questions(), 'What you did', 'up' ) ),
        array_keys( WPCPM_Track_Questions::move( questions(), 'What you did', 'down' ) ),
    ),
    array(
        array( 'Hours', 'Slack name', 'WP.org name', 'What you did' ),
        array( 'Hours', 'Slack name', 'WP.org name', 'What you did' ),
    ) );

ck( 'a column no question holds moves nothing',
    array_keys( WPCPM_Track_Questions::move( questions(), 'Nothing', 'up' ) ),
    array( 'Hours', 'Slack name', 'WP.org name', 'What you did' ) );

ck( 'a move keeps every question whole, not just its order',
    WPCPM_Track_Questions::move( questions(), 'WP.org name', 'up' )['Slack name'],
    array( 'type' => 'text', 'group' => 'onboarding', 'label' => 'Your Slack name' ) );

echo "\n=== Removing and renaming ===\n";

ck( 'remove takes one out and leaves the rest in order',
    array_keys( WPCPM_Track_Questions::remove( questions(), 'Slack name' ) ),
    array( 'Hours', 'WP.org name', 'What you did' ) );

ck( 'removing a column no question holds changes nothing',
    array_keys( WPCPM_Track_Questions::remove( questions(), 'Nothing' ) ),
    array( 'Hours', 'Slack name', 'WP.org name', 'What you did' ) );

ck( 'rename keeps the place the question held',
    array_keys( WPCPM_Track_Questions::rename( questions(), 'Slack name', 'Slack handle' ) ),
    array( 'Hours', 'Slack handle', 'WP.org name', 'What you did' ) );

ck( 'and keeps what it held',
    WPCPM_Track_Questions::rename( questions(), 'Slack name', 'Slack handle' )['Slack handle'],
    array( 'type' => 'text', 'group' => 'onboarding', 'label' => 'Your Slack name' ) );

ck( 'renaming onto another question is refused rather than overwriting it',
    WPCPM_Track_Questions::rename( questions(), 'Slack name', 'WP.org name' ),
    null );

ck( 'renaming a column no question holds is refused',
    WPCPM_Track_Questions::rename( questions(), 'Nothing', 'Something' ),
    null );

ck( 'renaming a question to the name it already has is allowed and changes nothing',
    array_keys( WPCPM_Track_Questions::rename( questions(), 'Slack name', 'Slack name' ) ),
    array( 'Hours', 'Slack name', 'WP.org name', 'What you did' ) );

echo "\n=== Who else writes this column ===\n";

/** Every other track, as the editor gathers them: two published and one draft. */
function others() {
    return array(
        array( 'label' => '150-hour Track', 'published' => true, 'columns' => array( 'Hours', 'Slack name' ) ),
        array( 'label' => 'Developer Track', 'published' => true, 'columns' => array( 'Slack name', 'What you did' ) ),
        array( 'label' => 'Marketing Track', 'published' => false, 'columns' => array( 'Slack name' ) ),
    );
}

ck( 'a column three tracks hold names all three, in the order they were given',
    WPCPM_Track_Questions::owners( 'Slack name', others() ),
    array(
        array( 'label' => '150-hour Track', 'published' => true ),
        array( 'label' => 'Developer Track', 'published' => true ),
        array( 'label' => 'Marketing Track', 'published' => false ),
    ) );

ck( 'a draft is named as one, because it does not write the column yet but will',
    WPCPM_Track_Questions::owners( 'Slack name', others() )[2]['published'],
    false );

ck( 'a column one track holds names one',
    WPCPM_Track_Questions::owners( 'Hours', others() ),
    array( array( 'label' => '150-hour Track', 'published' => true ) ) );

ck( 'a column nobody else holds names nobody',
    WPCPM_Track_Questions::owners( 'Your blog', others() ),
    array() );

ck( 'the comparison is exact: a trailing space is a different column',
    WPCPM_Track_Questions::owners( 'Slack name ', others() ),
    array() );

echo "\n=== Forking ===\n";

$text = array( 'type' => 'text', 'airtable_type' => 'singleLineText', 'group' => 'onboarding', 'label' => 'Your Slack name' );

ck( 'rewording the label keeps the column',
    WPCPM_Track_Questions::forks( array_merge( $text, array( 'label' => 'Your Slack name, please' ) ), $text ),
    false );

ck( 'so does a lead, a subgroup, help and a note',
    WPCPM_Track_Questions::forks( array_merge( $text, array( 'lead' => 'Before you start', 'subgroup' => 'Accounts', 'help' => 'The one you use in Slack', 'note' => 'Complete one of these.' ) ), $text ),
    false );

ck( 'changing the control forks',
    WPCPM_Track_Questions::forks( array_merge( $text, array( 'type' => 'textarea', 'airtable_type' => 'multilineText' ) ), $text ),
    true );

ck( 'and so does an Airtable type that no longer agrees with the control',
    WPCPM_Track_Questions::forks( array_merge( $text, array( 'airtable_type' => 'multilineText' ) ), $text ),
    true );

$select = array( 'type' => 'select', 'airtable_type' => 'singleSelect', 'group' => 'project', 'options' => array( 'Yes', 'No' ) );

ck( 'a new option forks',
    WPCPM_Track_Questions::forks( array_merge( $select, array( 'options' => array( 'Yes', 'No', 'Maybe' ) ) ), $select ),
    true );

ck( 'the same options in a different order forks, because Airtable keeps their order',
    WPCPM_Track_Questions::forks( array_merge( $select, array( 'options' => array( 'No', 'Yes' ) ) ), $select ),
    true );

ck( 'the same options unchanged do not',
    WPCPM_Track_Questions::forks( $select, $select ),
    false );

ck( 'a fork takes the column and the track key',
    WPCPM_Track_Questions::fork_name( 'Slack name', 'marketing' ),
    'Slack name - marketing' );

echo "\n=== A fork, afterwards ===\n";

ck( 'a forked column says what it came from while another track still holds that column',
    WPCPM_Track_Questions::forked_from( 'Slack name - marketing', 'marketing', others() ),
    'Slack name' );

ck( 'a column of the same shape whose stem nobody holds is not a fork',
    WPCPM_Track_Questions::forked_from( 'Coffee - marketing', 'marketing', others() ),
    '' );

ck( 'and neither is a column ending in another track key',
    WPCPM_Track_Questions::forked_from( 'Slack name - design', 'marketing', others() ),
    '' );

ck( 'a forked column is shared with nobody, which is what stops it forking a second time',
    WPCPM_Track_Questions::owners( 'Slack name - marketing', others() ),
    array() );

ck( 'so putting the control back reports a fork but finds no one to fork away from',
    array(
        WPCPM_Track_Questions::forks( $text, array_merge( $text, array( 'type' => 'textarea', 'airtable_type' => 'multilineText' ) ) ),
        WPCPM_Track_Questions::owners( 'Slack name - marketing', others() ),
    ),
    array( true, array() ) );

echo "\n=== A published column is fixed ===\n";

$published = array( 'Hours' => array( 'type' => 'number' ), 'Slack name' => array( 'type' => 'text' ) );

ck( 'a question in the published copy is locked',
    WPCPM_Track_Questions::locked( 'Slack name', $published ),
    true );

ck( 'a question added since the last publish is not',
    WPCPM_Track_Questions::locked( 'Your blog', $published ),
    false );

ck( 'and a track never published locks nothing',
    WPCPM_Track_Questions::locked( 'Slack name', array() ),
    false );


echo "\n=== Under a lesson (T3c) ===\n";

// Two questions of one lesson, then one of another, all in the project group, and one in wrap-up.
$lessoned = array(
	'Hours'    => array( 'type' => 'number', 'group' => 'hours' ),
	'Figma'    => array( 'type' => 'url', 'group' => 'project', 'lead' => 'Introduction to Figma', 'learn_lesson_id' => 403465 ),
	'Figma 2'  => array( 'type' => 'text', 'group' => 'project', 'learn_lesson_id' => 403465 ),
	'Library'  => array( 'type' => 'url', 'group' => 'project', 'lead' => 'Explore the library', 'learn_lesson_id' => 403471 ),
	'Feedback' => array( 'type' => 'textarea', 'group' => 'wrapup', 'learn_lesson_id' => 403507 ),
);

ck( "the last question of a lesson is the one a new question of that lesson goes after",
    array( WPCPM_Track_Questions::last_of_lesson( $lessoned, 403465 ), WPCPM_Track_Questions::last_of_lesson( $lessoned, 403471 ), WPCPM_Track_Questions::last_of_lesson( $lessoned, 403507 ) ),
    array( 'Figma 2', 'Library', 'Feedback' ) );
ck( 'a lesson with no question yet has no last question, and neither has no lesson at all',
    array( WPCPM_Track_Questions::last_of_lesson( $lessoned, 403499 ), WPCPM_Track_Questions::last_of_lesson( $lessoned, 0 ) ), array( '', '' ) );
ck( 'add() after a named column places the question right after it, inside the group',
    array_keys( WPCPM_Track_Questions::add( $lessoned, 'Figma 3', array( 'type' => 'text', 'group' => 'project', 'learn_lesson_id' => 403465 ), 'Figma 2' ) ),
    array( 'Hours', 'Figma', 'Figma 2', 'Figma 3', 'Library', 'Feedback' ) );
ck( 'and after a column the map does not hold, at the end of its group as before',
    array_keys( WPCPM_Track_Questions::add( $lessoned, 'Deploy', array( 'type' => 'url', 'group' => 'project' ), 'Gone' ) ),
    array( 'Hours', 'Figma', 'Figma 2', 'Library', 'Deploy', 'Feedback' ) );
ck( 'a column already used is still refused, wherever it was to go',
    WPCPM_Track_Questions::add( $lessoned, 'Library', array( 'type' => 'url', 'group' => 'project' ), 'Figma' ), null );

echo "\n=== Matching lessons again on a course change (T3c) ===\n";

$captured = json_decode( file_get_contents( __DIR__ . '/fixtures/learn-course-403425.json' ), true );
$lessons  = array();

foreach ( $captured['modules'] as $module ) {
	foreach ( $module['lessons'] as $lesson ) {
		$lessons[] = $lesson;
	}
}

$before = array(
	'Portfolio' => array( 'type' => 'url', 'group' => 'project', 'subgroup' => 'Create your portfolio', 'learn_lesson_id' => 111 ),
	'Styles'    => array( 'type' => 'url', 'group' => 'project', 'lead' => "practical: change your site's global styles", 'learn_lesson_id' => 222 ),
	'Event'     => array( 'type' => 'text', 'group' => 'wrapup', 'lead' => 'Participate at a WordPress Event (online or in person)', 'learn_lesson_id' => 333 ),
	'Reflect'   => array( 'type' => 'textarea', 'group' => 'wrapup', 'lead' => 'Your reflection posts', 'learn_lesson_id' => 444 ),
	'Nameless'  => array( 'type' => 'text', 'group' => 'project', 'learn_lesson_id' => 555 ),
	'Hours'     => array( 'type' => 'number', 'group' => 'hours', 'lead' => 'Get your certificate' ),
);

$result = WPCPM_Track_Questions::rematch( $before, $lessons );

ck( 'a question whose heading is a lesson of the new course, in lead or in subgroup, exact once case and apostrophes are folded, takes that lesson',
    array( $result['questions']['Portfolio']['learn_lesson_id'], $result['questions']['Styles']['learn_lesson_id'], $result['questions']['Event']['learn_lesson_id'], $result['matched'] ),
    array( 403457, 403477, 403501, array( 'Portfolio', 'Styles', 'Event' ) ) );
ck( 'one whose heading is no lesson of the new course, or that has no heading, loses its lesson and is listed when no question of its lesson matched',
    array( array_key_exists( 'learn_lesson_id', $result['questions']['Reflect'] ), array_key_exists( 'learn_lesson_id', $result['questions']['Nameless'] ), $result['cleared'] ),
    array( false, false, array( 'Reflect', 'Nameless' ) ) );
ck( 'a question that had no lesson is left alone, heading or not',
    array( array_key_exists( 'learn_lesson_id', $result['questions']['Hours'] ), $result['questions']['Hours'] ), array( false, $before['Hours'] ) );
ck( 'the order and every other property survive',
    array( array_keys( $result['questions'] ), $result['questions']['Styles']['lead'] ), array( array_keys( $before ), $before['Styles']['lead'] ) );
ck( 'against no lessons at all, every lesson is cleared',
    WPCPM_Track_Questions::rematch( $before, array() )['cleared'], array( 'Portfolio', 'Styles', 'Event', 'Reflect', 'Nameless' ) );

// The folding, on the two differences that actually turn up: a heading typed with the curly
// apostrophe a word processor leaves behind against a title Learn writes with the straight one,
// and a double space nobody sees (the final review of T3c).
$typography = WPCPM_Track_Questions::rematch(
	array( 'Journal' => array( 'type' => 'textarea', 'group' => 'project', 'lead' => "Write  your mentor\u{2019}s  feedback", 'learn_lesson_id' => 777 ) ),
	array( array( 'id' => 403601, 'title' => "Write your mentor's feedback" ) )
);

ck( 'a heading with a curly apostrophe and doubled spaces matches a title with a straight one and single spaces',
    array( $typography['questions']['Journal']['learn_lesson_id'], $typography['matched'], $typography['cleared'] ),
    array( 403601, array( 'Journal' ), array() ) );

// Two lessons a course could well hold: one heading cannot point at both, so the first the course
// lists is the one taken, and the choice is the same on every save.
$twins = WPCPM_Track_Questions::rematch(
	array( 'Post' => array( 'type' => 'url', 'group' => 'project', 'lead' => 'Write your first post', 'learn_lesson_id' => 888 ) ),
	array( array( 'id' => 403611, 'title' => 'Write your first post' ), array( 'id' => 403612, 'title' => 'Write Your First Post' ) )
);

ck( 'of two lessons whose titles fold to one, the first the course lists wins',
    $twins['questions']['Post']['learn_lesson_id'], 403611 );

// PUBLISH-LEARN-6: a lesson's first question carries its title as its heading and a question added
// after it under the same lesson carries none (decision 32), so a course change must move the
// whole lesson, not its first question alone. The two Adds below follow handle_add().
$lesson_pair = WPCPM_Track_Questions::add( array(), 'Figma file link', array( 'type' => 'url', 'group' => 'project', 'learn_lesson_id' => 403465, 'lead' => 'Introduction to Figma' ) );
$lesson_pair = WPCPM_Track_Questions::add( $lesson_pair, 'Figma screenshot', array( 'type' => 'image', 'group' => 'project', 'learn_lesson_id' => 403465 ), WPCPM_Track_Questions::last_of_lesson( $lesson_pair, 403465 ) );
$lesson_pair['Figma notes'] = array( 'type' => 'textarea', 'group' => 'project', 'learn_lesson_id' => 403465, 'subgroup' => 'What you noticed' );
$lesson_pair['Old lesson']  = array( 'type' => 'url', 'group' => 'project', 'learn_lesson_id' => 403466, 'lead' => 'A lesson the new course does not have' );
$lesson_pair['Old follow']  = array( 'type' => 'text', 'group' => 'project', 'learn_lesson_id' => 403466 );

$moved_lesson = WPCPM_Track_Questions::rematch( $lesson_pair, array( array( 'id' => 900111, 'title' => 'Introduction to Figma' ) ) );

ck( 'every question of a lesson whose first question matched takes the new lesson, with no heading or with one that is no lesson title',
    array( $moved_lesson['questions']['Figma file link']['learn_lesson_id'], $moved_lesson['questions']['Figma screenshot']['learn_lesson_id'], $moved_lesson['questions']['Figma notes']['learn_lesson_id'], $moved_lesson['matched'] ),
    array( 900111, 900111, 900111, array( 'Figma file link', 'Figma screenshot', 'Figma notes' ) ) );
ck( 'while a lesson none of whose questions matched loses its lesson on every one of them, and they are listed',
    array( array_key_exists( 'learn_lesson_id', $moved_lesson['questions']['Old lesson'] ), array_key_exists( 'learn_lesson_id', $moved_lesson['questions']['Old follow'] ), $moved_lesson['cleared'] ),
    array( false, false, array( 'Old lesson', 'Old follow' ) ) );
ck( 'and the order and every other property survive',
    array( array_keys( $moved_lesson['questions'] ), $moved_lesson['questions']['Figma notes']['subgroup'] ),
    array( array_keys( $lesson_pair ), 'What you noticed' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
