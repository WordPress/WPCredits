<?php
/**
 * The theme's node suites, run from PHP: bin/test-hub-js.php and bin/test-header-js.php.
 *
 * Each runner renders its pages with the theme's own PHP and hands them to a harness in bin/js/ on
 * stdin, as one JSON object of named pages, with the theme's root as the harness's one argument. The
 * harness prints `ok   <scenario>` or `FAIL <scenario>` for each scenario, with what was expected
 * and what happened under a failure, and ends with `N scenarios, M differ`. Each scenario line, and
 * every other line the harness prints on stdout or stderr but its summary, is shown here, then the
 * suite's own summary: `ALL PASS (N scenarios)` or `N FAILURE(S) (N scenarios)`. The harness's
 * summary is read as well: a harness that stopped early with a status of 0 shows fewer lines than it
 * counts, and that, a status other than 0, or no scenario at all is a failure.
 *
 * A machine without node on the path is told so by the runner, with a `skip` line and an exit of 0,
 * before it renders anything: every other check of the theme is PHP and needs nothing installed.
 *
 * Loaded with `require_once __DIR__ . '/stubs/node-suite.php';` from a runner's header.
 *
 * @package WPCredits_Theme
 */

/**
 * Where node is, or '' when it is not on the path.
 *
 * @return string
 */
function wpcredits_node() {
	return trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
}

/**
 * Run a harness on the pages and print what it says, then the suite's summary.
 *
 * @param string $harness The harness's path.
 * @param array  $pages   Name => markup, the pages its scenarios open.
 * @return int The exit status for the runner: 0 when every scenario passed, 1 otherwise.
 */
function wpcredits_run_node_suite( $harness, array $pages ) {
	$pipes   = array();
	$process = proc_open(
		escapeshellarg( wpcredits_node() ) . ' ' . escapeshellarg( $harness ) . ' ' . escapeshellarg( WPCREDITS_THEME_DIR ),
		array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes
	);

	fwrite( $pipes[0], (string) json_encode( $pages ) );
	fclose( $pipes[0] );

	$out = explode( "\n", trim( stream_get_contents( $pipes[1] ) . "\n" . stream_get_contents( $pipes[2] ) ) );

	fclose( $pipes[1] );
	fclose( $pipes[2] );

	$status  = proc_close( $process );
	$ran     = 0;
	$differ  = 0;
	$summary = null;

	foreach ( $out as $line ) {
		if ( 0 === strpos( $line, 'ok   ' ) ) {
			++$ran;
			echo $line . "\n";
		} elseif ( 0 === strpos( $line, 'FAIL ' ) ) {
			++$ran;
			++$differ;
			echo $line . "\n";
		} elseif ( preg_match( '/^(\d+) scenarios, (\d+) differ$/', $line, $found ) ) {
			$summary = array( (int) $found[1], (int) $found[2] );
		} elseif ( '' !== trim( $line ) ) {
			echo $line . "\n";
		}
	}

	$whole = null !== $summary && $summary[0] === $ran && $summary[1] === $differ && 0 === $differ && 0 === $status && $ran > 0;

	printf( "\n%s (%d scenarios)\n", $whole ? 'ALL PASS' : sprintf( '%d FAILURE(S)', max( 1, $differ ) ), $ran );

	return $whole ? 0 : 1;
}
