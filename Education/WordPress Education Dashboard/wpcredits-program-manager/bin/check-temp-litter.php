<?php
/**
 * Does the battery leave the system temp directory as it found it?
 *
 * Over eight days in September 2026 the suites under bin/ left about 28,600 entries in the temp
 * directory of the machine they ran on: a zero-byte file for every tempnam() whose name a suite
 * then wrote to with an extension added, the fixture files themselves, and an uploads folder per
 * run that nothing removed. Since 1.110.1 every suite keeps what it writes in a folder of its
 * own and removes it when it ends (bin/stubs/temp-dir.php). This is the proof: it lists the temp
 * directory, runs every bin/test-*.php and the three checkers one after another, each in a PHP
 * process of its own with its output discarded, lists the directory again and names every entry
 * that appeared and is still there.
 *
 * It never runs itself, and never bin/check-standards.sh, which is phpcs over the whole plugin:
 * bin/test-tooling.php runs that script four times against a stand-in phpcs, and those runs are
 * where its own temporary files are made and removed.
 *
 * Each script's exit status is noted, and one that did not exit 0 is named: a suite that died
 * early may have died before it wrote, or before it removed, what it writes.
 *
 * The temp directory is shared with everything else the machine runs, so an entry another
 * program made while the battery ran is named too. Run it again: the battery's leftovers come
 * back under the same prefixes, a stranger's do not.
 *
 * Run from the plugin root:  php bin/check-temp-litter.php   (exits 1 and names what stayed)
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = dirname( __DIR__ );
$temp = sys_get_temp_dir();

/**
 * The entries of a directory, the two dot entries aside.
 *
 * @param string $dir The directory.
 * @return array Names.
 */
function temp_entries( $dir ) {
	$names = scandir( $dir );

	if ( false === $names ) {
		fwrite( STDERR, 'Could not list ' . $dir . ".\n" );
		exit( 1 );
	}

	return array_values( array_diff( $names, array( '.', '..' ) ) );
}

// Every suite, then the three checkers. Neither this file nor bin/check-standards.sh matches.
$scripts = glob( __DIR__ . '/test-*.php' );

if ( empty( $scripts ) ) {
	// A gate that passes over nothing is not a gate.
	fwrite( STDERR, 'No suites found in ' . __DIR__ . ".\n" );
	exit( 1 );
}

foreach ( array( 'check-references.php', 'check-spelling.php', 'check-dead-annotations.php' ) as $checker ) {
	$scripts[] = __DIR__ . '/' . $checker;
}

$before = temp_entries( $temp );
$failed = array();

foreach ( $scripts as $script ) {
	// From the plugin root, as each of them asks to be run, with nothing read and nothing shown:
	// what is under test is what a run leaves on disk, not what it says.
	$pipes   = array();
	$process = proc_open(
		array( PHP_BINARY, $script ),
		array(
			0 => array( 'file', '/dev/null', 'r' ),
			1 => array( 'file', '/dev/null', 'w' ),
			2 => array( 'file', '/dev/null', 'w' ),
		),
		$pipes,
		$root
	);
	$status  = is_resource( $process ) ? proc_close( $process ) : -1;

	if ( 0 !== $status ) {
		$failed[] = sprintf( 'bin/%s exited %d', basename( $script ), $status );
	}
}

$appeared = array_values( array_diff( temp_entries( $temp ), $before ) );
sort( $appeared );

if ( $failed ) {
	printf( "Ran %d scripts from bin/, each in a PHP process of its own; %d did not exit 0 (run each alone to see why):\n  %s\n", count( $scripts ), count( $failed ), implode( "\n  ", $failed ) );
} else {
	printf( "Ran %d scripts from bin/, each in a PHP process of its own, and every one exited 0.\n", count( $scripts ) );
}

if ( $appeared ) {
	$said = 1 === count( $appeared ) ? '%d entry appeared in %s and is still there:' : '%d entries appeared in %s and are still there:';
	printf( $said . "\n  %s\n", count( $appeared ), $temp, implode( "\n  ", $appeared ) );
	exit( 1 );
}

echo "The battery leaves the temp directory as it found it.\n";
exit( 0 );
