<?php
/**
 * What changed between two copies of a track (Track Builder, phase T3b).
 *
 * `WPCPM_Track_Diff` compares two definitions and touches neither WordPress nor Airtable, so this
 * suite loads the real class and stands nothing in for it.
 *
 * Run from the plugin root:  php bin/test-track-diff.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-diff.php';

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

/** A track of twelve questions, in the shape the store holds. */
function track() {
	$questions = array( 'Hours' => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours', 'min' => 0, 'max' => 1000, 'step' => 1 ) );

	foreach ( array( 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K' ) as $letter ) {
		$questions[ 'Question ' . $letter ] = array( 'type' => 'text', 'label' => 'Question ' . $letter, 'group' => 'project', 'help' => 'Help ' . $letter );
	}

	return array(
		'schema_version' => 1,
		'status'         => 'Marketing Track',
		'key'            => 'marketing',
		'label'          => 'Marketing Track',
		'hue'            => 'teal',
		'hours_target'   => 50,
		'questions'      => $questions,
	);
}

/** The same track with one change applied by a callback. */
function changed( callable $change ) {
	$track = track();
	$change( $track );

	return $track;
}

echo "=== Nothing changed ===\n";

ck( 'two identical copies differ in nothing, and say so',
    WPCPM_Track_Diff::between( track(), track() ),
    array( 'track' => array(), 'added' => array(), 'removed' => array(), 'moved' => array(), 'changed' => array(), 'same' => true ) );

ck( 'a stored 100 and a posted "100" are the same length limit, and a flag stored true is a flag posted 1',
    WPCPM_Track_Diff::between(
        changed( function ( &$t ) { $t['questions']['Question A']['maxlength'] = 100; $t['questions']['Question A']['required'] = true; } ),
        changed( function ( &$t ) { $t['questions']['Question A']['maxlength'] = '100'; $t['questions']['Question A']['required'] = '1'; } )
    )['same'],
    true );

ck( 'a copy kept under a newer schema version is the same track: the version is the format\'s, not the track\'s',
    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { $t['schema_version'] = 2; } ) )['same'],
    true );

echo "\n=== The track's own properties ===\n";

ck( 'a renamed track and a changed hours target are named, in the order the properties are kept',
    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { $t['label'] = 'Marketing'; $t['hours_target'] = 60; } ) )['track'],
    array( 'label', 'hours_target' ) );

ck( 'a course added where there was none is a change',
    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { $t['course_url'] = 'https://learn.wordpress.org/course/marketing/'; } ) )['track'],
    array( 'course_url' ) );

ck( 'and the questions are not the track',
    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { $t['questions']['Question A']['label'] = 'Renamed'; } ) )['track'],
    array() );

echo "\n=== Added and removed ===\n";

$with_new = changed( function ( &$t ) { $t['questions']['Your blog'] = array( 'type' => 'url', 'label' => 'Your blog', 'group' => 'onboarding' ); } );

ck( 'a new question is added, nothing else moves',
    array_intersect_key( WPCPM_Track_Diff::between( track(), $with_new ), array( 'added' => 1, 'removed' => 1, 'moved' => 1, 'changed' => 1, 'same' => 1 ) ),
    array( 'added' => array( 'Your blog' ), 'removed' => array(), 'moved' => array(), 'changed' => array(), 'same' => false ) );

ck( 'the other way round it is removed',
    WPCPM_Track_Diff::between( $with_new, track() )['removed'],
    array( 'Your blog' ) );

ck( 'an empty track against a full one adds every question and the columns come verbatim',
    WPCPM_Track_Diff::between( array( 'questions' => array() ), changed( function ( &$t ) { $t['questions'] = array( 'Company ' => array( 'type' => 'text' ), 'Hours' => array( 'type' => 'number' ) ); } ) )['added'],
    array( 'Company ', 'Hours' ) );

ck( 'a full track against an empty one removes them',
    count( WPCPM_Track_Diff::between( track(), array( 'questions' => array() ) )['removed'] ),
    12 );

ck( 'a definition with no questions at all reads as empty rather than failing',
    WPCPM_Track_Diff::between( array(), array() )['same'],
    true );

echo "\n=== Moved ===\n";

/** The questions of a track reordered: `$order` names the letters in their new order. */
function reordered( array $order ) {
    return changed( function ( &$t ) use ( $order ) {
        $questions = array( 'Hours' => $t['questions']['Hours'] );

        foreach ( $order as $letter ) {
            $questions[ 'Question ' . $letter ] = $t['questions'][ 'Question ' . $letter ];
        }

        $t['questions'] = $questions;
    } );
}

ck( 'a question dragged from first to last past ten others is one move, not eleven',
    WPCPM_Track_Diff::between( track(), reordered( array( 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'A' ) ) )['moved'],
    array( 'Question A' ) );

ck( 'and dragged from last to first, one move as well',
    WPCPM_Track_Diff::between( track(), reordered( array( 'K', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J' ) ) )['moved'],
    array( 'Question K' ) );

$swap = WPCPM_Track_Diff::between( track(), reordered( array( 'B', 'A', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K' ) ) )['moved'];

ck( 'two neighbors swapped is one move, whichever of the two is named',
    array( count( $swap ), in_array( $swap[0], array( 'Question A', 'Question B' ), true ) ),
    array( 1, true ) );

ck( 'two questions each dragged elsewhere are two moves',
    count( WPCPM_Track_Diff::between( track(), reordered( array( 'B', 'C', 'D', 'A', 'E', 'F', 'G', 'H', 'K', 'I', 'J' ) ) )['moved'] ),
    2 );

ck( 'a move is reported in the newer copy\'s order',
    WPCPM_Track_Diff::between( track(), reordered( array( 'K', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'A' ) ) )['moved'],
    array( 'Question K', 'Question A' ) );

ck( 'a question added in the middle moves nothing',
    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) {
        $questions = array();
        foreach ( $t['questions'] as $column => $spec ) {
            $questions[ $column ] = $spec;
            if ( 'Question C' === $column ) {
                $questions['Your blog'] = array( 'type' => 'url', 'label' => 'Your blog', 'group' => 'project' );
            }
        }
        $t['questions'] = $questions;
    } ) )['moved'],
    array() );

ck( 'and a question removed from the middle moves nothing',
    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { unset( $t['questions']['Question C'] ); } ) )['moved'],
    array() );

echo "\n=== Changed properties ===\n";

ck( 'a reworded question names the property',
    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { $t['questions']['Question B']['label'] = 'Question B, reworded'; } ) )['changed'],
    array( 'Question B' => array( 'label' ) ) );

ck( 'a property added and one removed are both changes, the added one first',
    WPCPM_Track_Diff::between(
        track(),
        changed( function ( &$t ) { $t['questions']['Question B']['note'] = 'One of these.'; unset( $t['questions']['Question B']['help'] ); } )
    )['changed'],
    array( 'Question B' => array( 'note', 'help' ) ) );

ck( 'a control change names type and the Airtable type it brought',
    WPCPM_Track_Diff::between(
        track(),
        changed( function ( &$t ) { $t['questions']['Question B']['type'] = 'textarea'; $t['questions']['Question B']['airtable_type'] = 'multilineText'; } )
    )['changed'],
    array( 'Question B' => array( 'type', 'airtable_type' ) ) );

$select = changed( function ( &$t ) { $t['questions']['Question B'] = array( 'type' => 'select', 'label' => 'Question B', 'group' => 'project', 'options' => array( 'Yes', 'No' ) ); } );

ck( 'reordered choices are a change, because Airtable keeps their order',
    WPCPM_Track_Diff::between( $select, changed( function ( &$t ) { $t['questions']['Question B'] = array( 'type' => 'select', 'label' => 'Question B', 'group' => 'project', 'options' => array( 'No', 'Yes' ) ); } ) )['changed'],
    array( 'Question B' => array( 'options' ) ) );

ck( 'the same choices are not',
    WPCPM_Track_Diff::between( $select, $select )['same'],
    true );

ck( 'several questions changed are each named, in the newer copy\'s order',
    array_keys( WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { $t['questions']['Question D']['help'] = 'x'; $t['questions']['Question A']['required'] = true; } ) )['changed'] ),
    array( 'Question A', 'Question D' ) );

ck( 'a change, a move, an addition and a removal all at once are each reported where they belong',
    array_intersect_key(
        WPCPM_Track_Diff::between(
            track(),
            changed( function ( &$t ) {
                unset( $t['questions']['Question K'] );
                $t['questions']['Question A']['label'] = 'First, reworded';
                $t['questions']['Your blog'] = array( 'type' => 'url', 'label' => 'Your blog', 'group' => 'project' );
                $moved = $t['questions']['Question B'];
                unset( $t['questions']['Question B'] );
                $t['questions']['Question B'] = $moved;
            } )
        ),
        array( 'added' => 1, 'removed' => 1, 'moved' => 1, 'changed' => 1 )
    ),
    array( 'added' => array( 'Your blog' ), 'removed' => array( 'Question K' ), 'moved' => array( 'Question B' ), 'changed' => array( 'Question A' => array( 'label' ) ) ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
