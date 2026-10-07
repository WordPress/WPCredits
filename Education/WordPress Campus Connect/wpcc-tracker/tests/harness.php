<?php
/**
 * Minimal assertion harness. Run with: php tests/run-tests.php
 *
 * @package WPCC_Tracker
 */

$GLOBALS['wpcct_t'] = array( 'pass' => 0, 'fail' => 0, 'fails' => array() );

function t_ok( $cond, $label ) {
	if ( $cond ) {
		$GLOBALS['wpcct_t']['pass']++;
		return;
	}
	$GLOBALS['wpcct_t']['fail']++;
	$GLOBALS['wpcct_t']['fails'][] = $label;
}

function t_eq( $actual, $expected, $label ) {
	$ok = $actual === $expected;
	if ( ! $ok ) {
		$label .= sprintf(
			' (expected %s, got %s)',
			var_export( $expected, true ),
			var_export( $actual, true )
		);
	}
	t_ok( $ok, $label );
}

function t_report() {
	$t = $GLOBALS['wpcct_t'];
	foreach ( $t['fails'] as $f ) {
		echo "FAIL  " . $f . "\n";
	}
	printf( "\n%d passed, %d failed\n", $t['pass'], $t['fail'] );
	exit( $t['fail'] > 0 ? 1 : 0 );
}

/**
 * Load a frozen Campus Connect Details export.
 *
 * Two are kept, deliberately. The 2026-08-27 one is what the reconciliation
 * table in the design document was derived from, so its numbers are assertions
 * and must not be regenerated. The 2026-10-07 one is a second, later export of
 * the same report, kept so that upstream drift shows up as a failing test
 * rather than as tribal knowledge: between the two, the report gained 23
 * events and changed the spelling of one Status.
 *
 * @param string $name File name inside tests/fixtures.
 * @return array List of rows, each an associative array keyed by column header.
 */
function wpcct_load_fixture( $name = 'campus-connect-details-2026-08-27.tsv' ) {
	static $cache = array();

	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}

	$path  = __DIR__ . '/fixtures/' . $name;
	$lines = file( $path, FILE_IGNORE_NEW_LINES );
	if ( ! $lines ) {
		fwrite( STDERR, "Fixture missing: $path\n" );
		exit( 1 );
	}

	$head = explode( "\t", array_shift( $lines ) );
	$rows = array();
	foreach ( $lines as $line ) {
		if ( '' === trim( $line ) ) {
			continue;
		}
		$cells = explode( "\t", $line );
		$cells = array_pad( $cells, count( $head ), '' );
		$rows[] = array_combine( $head, array_slice( $cells, 0, count( $head ) ) );
	}

	$cache[ $name ] = $rows;

	return $rows;
}
