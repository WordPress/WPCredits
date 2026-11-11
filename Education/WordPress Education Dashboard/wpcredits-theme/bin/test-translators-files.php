<?php
/**
 * The list of files translators are given, against the theme (1.32.25).
 *
 * `bin/translators-files.txt` names the files whose strings go to translate.wordpress.org: the
 * pieces around the screens students and mentors work in. The plugin's bin/translators-pot.php
 * cuts the theme's template down to them, and stops when a listed file has no string in it. These
 * checks say so earlier, in the theme's own release: every listed file exists, is listed once, has
 * a string in languages/wpcredits-theme.pot, and holds only what a visitor reads.
 *
 * Run from the theme root:  php bin/test-translators-files.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$fail = 0;
function ck( $label, $got, $want ) {
	global $fail;
	if ( $got === $want ) {
		echo "  ok    $label\n";
		return;
	}
	++$fail;
	echo "  FAIL  $label\n        got:  " . var_export( $got, true ) . "\n        want: " . var_export( $want, true ) . "\n";
}

echo "=== bin/translators-files.txt ===\n";

$root  = dirname( __DIR__ );
$files = array();
foreach ( preg_split( '/\r?\n/', (string) file_get_contents( __DIR__ . '/translators-files.txt' ) ) as $line ) {
	$line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
	if ( '' !== $line ) {
		$files[] = $line;
	}
}

ck( 'the list is not empty', count( $files ) > 0, true );
ck( 'no file is listed twice', count( $files ), count( array_unique( $files ) ) );

$missing = array_values( array_filter( $files, static function ( $file ) use ( $root ) {
	return ! is_file( $root . '/' . $file );
} ) );
ck( 'every listed file exists', $missing, array() );

// A reference is `#: path:line`, or `#: path` for a file read whole (block.json, style.css).
preg_match_all( '/^#: (.+)$/m', (string) file_get_contents( $root . '/languages/wpcredits-theme.pot' ), $lines );
$named = array();
foreach ( $lines[1] as $line ) {
	foreach ( preg_split( '/\s+/', trim( $line ) ) as $reference ) {
		$named[ preg_replace( '/:\d+$/', '', $reference ) ] = true;
	}
}
$silent = array_values( array_filter( $files, static function ( $file ) use ( $named ) {
	return ! isset( $named[ $file ] );
} ) );
ck( 'the template has a string from every listed file', $silent, array() );

$editor = array_values( array_filter( $files, static function ( $file ) {
	return 1 === preg_match( '#(block\.json|index\.js)$#', $file );
} ) );
ck( 'no editor-only file is listed', $editor, array() );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
