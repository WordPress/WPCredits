<?php
/**
 * Runs assets/js/admin.js: the invitations card's poller and the sync panels' poller (1.123.58), each
 * on a stand-in for the page.
 *
 * Run from the plugin root:  php bin/test-admin-js.php
 *
 * node loads assets/js/admin.js into the stand-in of each harness and drives it through the
 * scenarios there: bin/js/admin-invites.js (the count moved in place, a screen reader told at each
 * quarter and at the finish, a refused answer and a dropped network stopping the panel, a hidden tab
 * asking nothing), and bin/js/admin-sync.js (the panel busy while a run goes, each step, the stall
 * and the finish said once, a stopped run ending the poll with its sentence). Each scenario a
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
	echo "skip admin.js was not run: node is not on the path\n";
	exit( 0 );
}

$fail = 0;
function ck( $l, $a, $e = true ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $l . "\n";
	if ( ! $ok ) { echo "       exp: " . var_export( $e, true ) . "  got: " . var_export( $a, true ) . "\n"; }
}

foreach ( array( 'admin-invites.js', 'admin-sync.js' ) as $harness ) {
	echo "--- bin/js/$harness\n";

	$out    = array();
	$status = 0;

	exec(
		escapeshellarg( $node ) . ' ' . escapeshellarg( $root . '/bin/js/' . $harness ) . ' ' . escapeshellarg( $root . '/assets/js/admin.js' ) . ' 2>&1',
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

	ck( "$harness ran scenarios", $ran > 0, true );
	ck( 'and its own summary counts the scenarios and the failures shown above', $summary, array( $ran, $differ ) );
	ck( 'and node ended with a status of 0, or of 1 when a scenario differed', $status, $differ ? 1 : 0 );
}

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
