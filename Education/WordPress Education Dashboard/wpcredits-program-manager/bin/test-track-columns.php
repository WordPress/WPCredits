<?php
/**
 * A question as an Airtable column (Track Builder, phase T2c).
 *
 * `WPCPM_Track_Columns` is the one place that knows what control becomes what Airtable field, and
 * what an existing column in the base means for a question that names it. It makes no request, so
 * this suite needs no client: the schema arrives as the array `fetch_schema()` returns.
 *
 * Run from the plugin root:  php bin/test-track-columns.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $s, $d = null ) { return $s; }

require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-columns.php';

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

echo "=== A question becomes a column ===\n";

ck( 'text is a single line',
    WPCPM_Track_Columns::field( 'Slack Name', array( 'type' => 'text' ) ),
    array( 'name' => 'Slack Name', 'type' => 'singleLineText' ) );

ck( 'textarea is many lines',
    WPCPM_Track_Columns::field( 'What you did', array( 'type' => 'textarea' ) ),
    array( 'name' => 'What you did', 'type' => 'multilineText' ) );

ck( 'richtext is rich text, which Airtable stores as Markdown',
    WPCPM_Track_Columns::field( 'Notes', array( 'type' => 'richtext' ) ),
    array( 'name' => 'Notes', 'type' => 'richText' ) );

ck( 'url and email are their own types',
    array(
        WPCPM_Track_Columns::field( 'Portfolio', array( 'type' => 'url' ) ),
        WPCPM_Track_Columns::field( 'Personal email', array( 'type' => 'email' ) ),
    ),
    array(
        array( 'name' => 'Portfolio', 'type' => 'url' ),
        array( 'name' => 'Personal email', 'type' => 'email' ),
    ) );

ck( 'a whole-number step asks for no decimal places',
    WPCPM_Track_Columns::field( 'Hours', array( 'type' => 'number', 'step' => '1' ) ),
    array( 'name' => 'Hours', 'type' => 'number', 'options' => array( 'precision' => 0 ) ) );

ck( 'and a hundredths step asks for two, which is what the forty grades use',
    WPCPM_Track_Columns::field( 'Final grade', array( 'type' => 'number', 'step' => '0.01' ) ),
    array( 'name' => 'Final grade', 'type' => 'number', 'options' => array( 'precision' => 2 ) ) );

ck( 'a step with a trailing zero is trimmed to the significant digits',
    WPCPM_Track_Columns::field( 'Tenths', array( 'type' => 'number', 'step' => '0.10' ) ),
    array( 'name' => 'Tenths', 'type' => 'number', 'options' => array( 'precision' => 1 ) ) );

ck( 'a number with no step at all is whole, rather than refused',
    WPCPM_Track_Columns::field( 'Count', array( 'type' => 'number' ) ),
    array( 'name' => 'Count', 'type' => 'number', 'options' => array( 'precision' => 0 ) ) );

ck( 'a checkbox carries the icon and color the base already uses',
    WPCPM_Track_Columns::field( 'Mentoring opt-in', array( 'type' => 'checkbox' ) ),
    array( 'name' => 'Mentoring opt-in', 'type' => 'checkbox', 'options' => array( 'icon' => 'check', 'color' => 'greenBright' ) ) );

ck( 'a select carries its options as the choices, in the order they were written',
    WPCPM_Track_Columns::field( 'Tool used', array( 'type' => 'select', 'options' => array( 'WordPress Studio', 'MAAMP', 'DevKinsta' ) ) ),
    array(
        'name'    => 'Tool used',
        'type'    => 'singleSelect',
        'options' => array( 'choices' => array( array( 'name' => 'WordPress Studio' ), array( 'name' => 'MAAMP' ), array( 'name' => 'DevKinsta' ) ) ),
    ) );

ck( 'an image column takes attachments',
    WPCPM_Track_Columns::field( 'Screenshot', array( 'type' => 'image' ) ),
    array( 'name' => 'Screenshot', 'type' => 'multipleAttachments' ) );

// The control is written for `Main Contribution Team`, the one column all four tracks share: a
// second link column would carry a reverse field into a second table (the design's 4.2).
ck( 'team never asks for a column', WPCPM_Track_Columns::field( 'Main Contribution Team', array( 'type' => 'team' ) ), null );

ck( 'a control nobody has heard of asks for nothing rather than guessing',
    WPCPM_Track_Columns::field( 'Something', array( 'type' => 'rating' ) ), null );

echo "\n=== The name is the column, verbatim ===\n";

// `key()` hashes the column name and an Airtable name may end in a space, so nothing is trimmed.
ck( 'a name that ends in a space keeps it',
    WPCPM_Track_Columns::field( 'Company ', array( 'type' => 'text' ) ),
    array( 'name' => 'Company ', 'type' => 'singleLineText' ) );

echo "\n=== What an existing column means ===\n";

$columns = array(
    'What you did'            => array( 'type' => 'multilineText' ),
    'Hours'                   => array( 'type' => 'number' ),
    '50h personal link'       => array( 'type' => 'formula' ),
    'Lessons'                 => array( 'type' => 'multipleRecordLinks' ),
    'Main Contribution Team'  => array( 'type' => 'multipleRecordLinks' ),
    "Mentor's email"          => array( 'type' => 'multipleLookupValues' ),
    'Total responses'         => array( 'type' => 'count' ),
    'Aggregate score'         => array( 'type' => 'rollup' ),
);

ck( 'a column that is not there yet is one to create',
    WPCPM_Track_Columns::judge( 'Brand new', array( 'type' => 'text' ), $columns ), 'create' );

ck( 'a column of the right type is ready',
    WPCPM_Track_Columns::judge( 'What you did', array( 'type' => 'textarea' ), $columns ), 'ok' );

ck( 'a column of another type is a mismatch, because the site would write the wrong shape into it',
    WPCPM_Track_Columns::judge( 'Hours', array( 'type' => 'text' ), $columns ), 'type_mismatch' );

ck( 'a formula column is computed, and nothing may be written to it',
    WPCPM_Track_Columns::judge( '50h personal link', array( 'type' => 'text' ), $columns ), 'computed' );

ck( 'so is a lookup',
    WPCPM_Track_Columns::judge( "Mentor's email", array( 'type' => 'email' ), $columns ), 'computed' );

ck( 'a count column is computed, and nothing may be written to it',
    WPCPM_Track_Columns::judge( 'Total responses', array( 'type' => 'number' ), $columns ), 'computed' );

ck( 'a rollup column is computed, and nothing may be written to it',
    WPCPM_Track_Columns::judge( 'Aggregate score', array( 'type' => 'text' ), $columns ), 'computed' );

ck( 'a link column that is not Main Contribution Team reaches into another table',
    WPCPM_Track_Columns::judge( 'Lessons', array( 'type' => 'team' ), $columns ), 'foreign_link' );

ck( 'and Main Contribution Team itself is the one link a team question may use',
    WPCPM_Track_Columns::judge( 'Main Contribution Team', array( 'type' => 'team' ), $columns ), 'ok' );

ck( 'but a non-team question on Main Contribution Team is refused, because it would send the wrong shape',
    WPCPM_Track_Columns::judge( 'Main Contribution Team', array( 'type' => 'text' ), $columns ), 'foreign_link' );

echo "\n=== The base's own columns, as they really are ===\n";

$fixture = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/reports-table-fields.json' ), true );
$real    = array();

foreach ( (array) $fixture['all_types'] as $name => $type ) {
    $real[ $name ] = array( 'type' => (string) $type );
}

$computed = array();
$foreign  = array();

foreach ( $real as $name => $column ) {
    // Main Contribution Team is the one link column that accepts team questions.
    $question_type = 'Main Contribution Team' === $name ? 'team' : 'text';
    $verdict = WPCPM_Track_Columns::judge( $name, array( 'type' => $question_type ), $real );

    if ( 'computed' === $verdict ) {
        $computed[] = $name;
    }

    if ( 'foreign_link' === $verdict ) {
        $foreign[] = $name;
    }
}

sort( $computed );
sort( $foreign );

ck( 'the four computed columns of the real table are named as computed',
    $computed, array( '50h personal link', 'Dev Track ONLY personal link', "Mentor's email", 'Personal link' ) );

ck( 'and its five other link columns are refused while Main Contribution Team is not among them',
    $foreign, array( 'Company ', 'Educational institution', 'Lessons', 'Mentor', 'Students' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
