<?php
/**
 * Find `phpcs:ignore` annotations that suppress nothing.
 *
 * An annotation is a claim: "this line would trip that sniff, and here is why it is fine." When
 * the line no longer trips the sniff (the code changed, or the ruleset excludes the sniff
 * outright), the claim is stale and the next reader takes it as a real exception. Forty-four
 * of the plugin's 177 annotations were dead when this was first run. It runs phpcs with
 * annotations ignored, so every violation the ruleset would raise is on the table, and then
 * checks each annotation's target line for a violation of the sniff it names (or, for a blanket
 * annotation, for any violation at all). A trailing annotation targets its own line; one on a
 * line of its own targets the next line, which is the rule phpcs itself applies.
 *
 * Usage:  php bin/check-dead-annotations.php        (from the plugin root; exits 1 on a dead one)
 *         bash bin/check-standards.sh --dead        (the same, from the standards script)
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root  = dirname( __DIR__ );
$phpcs = trim( (string) shell_exec( 'command -v phpcs' ) );

if ( '' === $phpcs ) {
	$phpcs = getenv( 'HOME' ) . '/.composer/vendor/bin/phpcs';
}

if ( ! is_executable( $phpcs ) ) {
	fwrite( STDERR, "phpcs not found.\n" );
	exit( 1 );
}

// Quiet, and to a file: the ruleset prints a progress line to stdout, which is not JSON.
$report = tempnam( sys_get_temp_dir(), 'wpcpm-phpcs-' );
shell_exec( escapeshellarg( $phpcs ) . ' -q --ignore-annotations --report=json --report-file=' . escapeshellarg( $report ) . ' 2>/dev/null' );
$data = json_decode( (string) file_get_contents( $report ), true );
unlink( $report );

if ( ! is_array( $data ) || ! isset( $data['files'] ) ) {
	fwrite( STDERR, "phpcs produced no report.\n" );
	exit( 1 );
}

$violations = array();

foreach ( $data['files'] as $file => $info ) {
	foreach ( $info['messages'] as $message ) {
		$violations[ realpath( $file ) ][ (int) $message['line'] ][] = (string) $message['source'];
	}
}

$paths = array_merge(
	array( $root . '/wpcredits-program-manager.php', $root . '/uninstall.php' ),
	array_map( 'strval', (array) glob( $root . '/includes/{,*/,*/*/}*.php', GLOB_BRACE ) )
);

$total = 0;
$dead  = array();

foreach ( array_unique( $paths ) as $path ) {
	$real  = realpath( $path );
	$lines = explode( "\n", (string) file_get_contents( $path ) );

	foreach ( $lines as $index => $line ) {
		if ( ! preg_match( '/phpcs:ignore(?!File)(?:\s+([A-Za-z0-9_.,\s]+?))?(?=\s+--|\s*$|\s*\*\/)/', $line, $m ) ) {
			continue;
		}

		++$total;

		$number     = $index + 1;
		$standalone = (bool) preg_match( '/^\s*(\/\/|\/\*|\*)/', $line );
		$target     = $standalone ? $number + 1 : $number;
		$sniffs     = array_filter( array_map( 'trim', explode( ',', isset( $m[1] ) ? $m[1] : '' ) ) );
		$found      = isset( $violations[ $real ][ $target ] ) ? $violations[ $real ][ $target ] : array();
		$live       = false;

		if ( empty( $sniffs ) ) {
			$live = ! empty( $found );
		} else {
			foreach ( $sniffs as $sniff ) {
				foreach ( $found as $source ) {
					if ( $source === $sniff || 0 === strpos( $source, $sniff . '.' ) ) {
						$live = true;
					}
				}
			}
		}

		if ( ! $live ) {
			$dead[] = sprintf( '%s:%d (line %d) %s', substr( $path, strlen( $root ) + 1 ), $number, $target, empty( $sniffs ) ? '(blanket)' : implode( ', ', $sniffs ) );
		}
	}
}

printf( "%d annotations, %d dead.\n", $total, count( $dead ) );

foreach ( $dead as $row ) {
	echo '  ', $row, "\n";
}

exit( $dead ? 1 : 0 );
