<?php
/**
 * Progress bars are native `<progress>` elements (1.123.87).
 *
 * The six bars (the three syncs, the Student Duplicate Finder's scan, the invitations run and the
 * Mentor Status Checker) were `<div role="progressbar">` with a span or div for the fill, moved by
 * width. They are `<progress>` now: the browser gives the role and announces the value, and a
 * script moves `value`. This suite reads the source, so a hand-built bar cannot come back unseen,
 * and each `<progress>` keeps a scale and a name.
 *
 * Run: php bin/test-progress-bars.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = dirname( __DIR__ );
$fail = 0;

function ck( $l, $a, $e = true ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $l . "\n";
	if ( ! $ok ) { echo "       exp: " . var_export( $e, true ) . "  got: " . var_export( $a, true ) . "\n"; }
}

$files = array();
foreach ( array( 'includes', 'blocks', 'assets/js' ) as $dir ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( preg_match( '/\.(php|js|html)$/', $file->getFilename() ) ) {
			$files[ substr( $file->getPathname(), strlen( $root ) + 1 ) ] = (string) file_get_contents( $file->getPathname() );
		}
	}
}
ksort( $files );

$hand_built = array();
$bars       = array();
foreach ( $files as $path => $source ) {
	if ( preg_match( '/role=\\\\?["\']progressbar/', $source ) ) {
		$hand_built[] = $path;
	}
	if ( preg_match_all( '/<progress\b[^>]*>/', $source, $found ) ) {
		foreach ( $found[0] as $tag ) {
			$bars[] = array( $path, $tag );
		}
	}
}

ck( 'no bar is a div or span with role="progressbar"', $hand_built, array() );
ck( 'the six bars are <progress> elements', count( $bars ), 6 );

$unscaled = array();
$unnamed  = array();
foreach ( $bars as $bar ) {
	list( $path, $tag ) = $bar;
	if ( false === strpos( $tag, 'max="100"' ) || false === strpos( $tag, 'value="' ) ) {
		$unscaled[] = $path;
	}
	if ( false === strpos( $tag, 'aria-label="' ) && false === strpos( $tag, 'aria-labelledby="' ) ) {
		$unnamed[] = $path;
	}
}
ck( 'each one has max="100" and a value, so it is never shown as running without a figure', $unscaled, array() );
ck( 'each one is named, by aria-label or by its visible label', $unnamed, array() );

$scripts = $files['assets/js/admin.js'] . $files['assets/js/mentor-checker.js'];
ck( 'the scripts move the value, and no longer a fill width or aria-valuenow', array(
	false !== strpos( $files['assets/js/admin.js'], 'el.bar.value = data.percent;' ),
	false !== strpos( $files['assets/js/mentor-checker.js'], 'progressBar.value = percent;' ),
	false === strpos( $scripts, 'aria-valuenow' ),
	false === strpos( $scripts, 'fill.style.width' ),
), array( true, true, true, true ) );

// The styles: each fill rule is written once per browser, never in one rule that names both
// (a rule naming -webkit- and -moz- pseudo-elements together is dropped whole by both).
$css = (string) file_get_contents( $root . '/assets/css/admin.css' ) . (string) file_get_contents( $root . '/assets/css/mentor-checker.css' );
ck( 'no style rule names the Chrome and Firefox fills together', 0, preg_match( '/::-webkit-progress-value[^{]*::-moz-progress-bar|::-moz-progress-bar[^{]*::-webkit-progress-value/', $css ) );
foreach ( array( '.wpcpm-bar', '.wpcpm-invites__bar', '.wpcpm-checker-progress__bar' ) as $class ) {
	ck( $class . ' draws its own bar in both browsers', array(
		1 === preg_match( '/' . preg_quote( $class, '/' ) . ' \{[^}]*appearance: none;/', $css ),
		false !== strpos( $css, $class . '::-webkit-progress-value {' ),
		false !== strpos( $css, $class . '::-moz-progress-bar {' ),
	), array( true, true, true ) );
}
ck( 'reduced motion stops the stripes and the fill moving', array(
	1 === preg_match( '/prefers-reduced-motion: reduce \)\s*\{\s*\.wpcpm-bar \{\s*--wpcpm-bar-motion: none;\s*--wpcpm-bar-move: none;/', $css ),
	false !== strpos( $css, '--wpcpm-checker-move: none;' ),
), array( true, true ) );

echo "\n" . ( 0 === $fail ? 'ALL PASS' : "$fail FAILED" ) . "\n";
exit( $fail ? 1 : 0 );
