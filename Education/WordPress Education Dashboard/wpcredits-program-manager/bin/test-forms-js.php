<?php
/**
 * Runs assets/js/forms.js: the confirm reader, the tracker of the last control pressed and the
 * submit guard, on a stand-in for the DOM.
 *
 * Run from the plugin root:  php bin/test-forms-js.php
 *
 * bin/test-submit-guard.php reads the script as text, which cannot tell a reader that asks from
 * one that merely looks like it does. This runs it: node loads assets/js/forms.js into the
 * stand-in under bin/js/forms-harness/ and drives it through the scenarios there (a Cancel that
 * stops the post and leaves the form alone, an OK that locks it once, a press in a browser
 * without `event.submitter`, a form that arrives after the script ran). Each scenario the
 * harness prints is shown here as one line.
 *
 * Every other suite is PHP and needs nothing installed, so a machine without node on the path
 * gets one `skip` line and an exit of 0 rather than a failure.
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = dirname( __DIR__ );
$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "skip forms.js was not run: node is not on the path\n";
	exit( 0 );
}

$fail = 0;
function ck( $l, $a, $e = true ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $l . "\n";
	if ( ! $ok ) { echo "       exp: " . var_export( $e, true ) . "  got: " . var_export( $a, true ) . "\n"; }
}

$out    = array();
$status = 0;

exec(
	escapeshellarg( $node ) . ' ' . escapeshellarg( $root . '/bin/js/forms-harness/scenarios.js' ) . ' ' . escapeshellarg( $root . '/assets/js/forms.js' ) . ' 2>&1',
	$out,
	$status
);

// The harness prints "ok   <scenario>" or "FAIL <scenario>" and, under a FAIL, a line saying what
// was expected and what happened. Those lines are this suite's own; anything else it prints is
// a summary or a crash, and is shown as it came. The summary, "N scenarios, M differ", is read
// as well: a harness that stopped early with a status of 0 shows fewer lines than it counts.
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
		++$fail;
		echo $line . "\n";
	} elseif ( 0 === strpos( $line, '       ' ) ) {
		echo $line . "\n";
	} elseif ( '' !== trim( $line ) ) {
		if ( preg_match( '/^(\d+) scenarios, (\d+) differ$/', trim( $line ), $counts ) ) {
			$summary = array( (int) $counts[1], (int) $counts[2] );
		}

		echo '     ' . $line . "\n";
	}
}

ck( 'the harness ran scenarios', $ran > 0, true );
ck( 'and its own summary counts the scenarios and the failures shown above', $summary, array( $ran, $differ ) );
ck( 'and node ended with a status of 0, or of 1 when a scenario differed', $status, $differ ? 1 : 0 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
