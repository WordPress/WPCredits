<?php
/**
 * Runs the hub's view scripts on the hub's own server markup, in node (theme 1.24.15).
 *
 * Run from the theme root:  php bin/test-hub-js.php
 *
 * The markup is rendered here, by the blocks' render.php files on the WordPress of
 * bin/stubs/hub-wp.php, and handed to bin/js/hub-harness.js on stdin, which runs each block's
 * view.js on a stand-in for the page (bin/js/hub-dom.js) and drives it as a person does. Each scenario
 * the harness prints is shown here as one line, and its summary, "N scenarios, M differ", is read as
 * well: a harness that stopped early with a status of 0 shows fewer lines than it counts.
 *
 * Every other check of the theme is PHP and needs nothing installed, so a machine without node on the
 * path gets one `skip` line and an exit of 0 rather than a failure.
 *
 * @package WPCredits_Theme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';
require_once WPCREDITS_THEME_DIR . 'inc/template-tags.php';

$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "skip the hub's view scripts were not run: node is not on the path\n";
	exit( 0 );
}

// The pages the scenarios open: each block on its own, with links of the kind the rest of the page
// holds pointing at it, and the whole hub page, as the template draws it.
$pages = array(
	'index'   => '<main>' . wpcredits_test_block( 'wpcredits/program-index' ) . '<nav><a href="#program-wordpress-credits">Start WordPress Credits</a> <a href="#program-nothing">Nowhere</a> <a href="#program-wordpress-credits">WordPress Credits<span aria-hidden="true"> →</span></a></nav></main>',
	'pathway' => '<main>' . wpcredits_test_block( 'wpcredits/pathway' ) . '<nav><a href="#wpc-hub-step-students-6">Community Leader<span aria-hidden="true"> →</span></a></nav></main>',
	'page'    => '<main>' . wpcredits_test_page() . '</main>',
);

$pipes   = array();
$process = proc_open(
	escapeshellarg( $node ) . ' ' . escapeshellarg( __DIR__ . '/js/hub-harness.js' ) . ' ' . escapeshellarg( WPCREDITS_THEME_DIR ),
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

exit( $whole ? 0 : 1 );
