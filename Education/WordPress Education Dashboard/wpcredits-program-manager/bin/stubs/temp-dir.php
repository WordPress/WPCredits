<?php
/**
 * One temporary directory per run, and it goes when the run does.
 *
 * The suites write real files: GD images the image handler reads, PDFs the agreement checks
 * read, the uploads folder a Media Library stand-in stores into, the calendar files the mail
 * path attaches. They used to write them straight into the system temp directory. tempnam()
 * reserves a name by creating an empty file under it, and a suite that then wrote its fixture
 * under that name plus ".png" left the empty file beside it; the uploads folders were never
 * removed at all. Over eight days in September 2026 that was about 28,600 entries on the
 * machine the battery ran on.
 *
 * What every suite promises since 1.110.1: nothing of its own outlives the run. Whatever it
 * writes goes in this run's directory, sys_get_temp_dir() . '/wpcpm-test-<suite>-<pid>/', named
 * after the script that included this file and its process ID, made the first time it is asked
 * for and open to this user alone. The directory and everything in it are removed at shutdown,
 * which PHP reaches on exit() and after a fatal error as well as at the end of the file. Only a
 * run that is killed or interrupted leaves it behind, and the suite's next run under that process
 * ID clears it first. A suite whose plugin code writes through get_temp_dir() answers that
 * stand-in with this directory too, so the image handler's wpcpm-image-* copies and the
 * calendar's wpcpm-* folders land here, and a check that counts them counts this run's and
 * nobody else's.
 *
 * bin/check-temp-litter.php runs the whole battery and proves the promise.
 *
 * Loaded with `require_once __DIR__ . '/stubs/temp-dir.php';` from a suite's header, and from
 * bin/check-dead-annotations.php, whose phpcs report is the one file a checker writes.
 */

/**
 * This run's temporary directory, made the first time it is asked for.
 *
 * @return string Absolute path, with a trailing slash, as core's get_temp_dir() answers.
 */
function wpcpm_test_temp_dir() {
	static $dir = null;

	if ( null === $dir ) {
		$included = get_included_files();
		$suite    = preg_replace( '/^test-/', '', basename( $included[0], '.php' ) );
		$dir      = sys_get_temp_dir() . '/wpcpm-test-' . $suite . '-' . getmypid() . '/';

		// A directory under this name was left by a run of this suite killed under this process
		// ID: no live process shares the ID, so what it holds is nobody's, and a count taken here
		// must not count it.
		wpcpm_test_remove_tree( $dir );
		register_shutdown_function( 'wpcpm_test_remove_tree', $dir );
	}

	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0700, true );
	}

	return $dir;
}

/**
 * A new, empty file in this run's temporary directory, with an extension when one is asked for.
 *
 * The file tempnam() reserves is renamed to the name with the extension, so the name stays
 * unique and no empty file is left beside the one the suite writes.
 *
 * @param string $prefix    The start of the file name.
 * @param string $extension The extension, without the dot; none by default.
 * @return string Absolute path.
 */
function wpcpm_test_tempnam( $prefix, $extension = '' ) {
	$reserved = tempnam( wpcpm_test_temp_dir(), $prefix );

	if ( false === $reserved ) {
		fwrite( STDERR, 'tempnam() could not make a file in ' . wpcpm_test_temp_dir() . ".\n" );
		exit( 1 );
	}

	if ( '' === $extension ) {
		return $reserved;
	}

	$path = $reserved . '.' . $extension;

	// Only the reserved name is certain to be free: a taken one is drawn again, never written over.
	if ( file_exists( $path ) ) {
		$again = wpcpm_test_tempnam( $prefix, $extension );
		unlink( $reserved );

		return $again;
	}

	rename( $reserved, $path );

	return $path;
}

/**
 * Remove a directory and everything in it, when it is a directory inside the system temp directory.
 *
 * Any other path is refused, and said so on stderr: this runs at the end of every suite, and a
 * path that resolves outside the temp directory is a mistake to report, never something to
 * delete. A symbolic link inside is removed as a link and never followed.
 *
 * @param string $dir The directory.
 * @return bool Whether nothing of it is left.
 */
function wpcpm_test_remove_tree( $dir ) {
	$dir = rtrim( (string) $dir, '/\\' );

	if ( ! file_exists( $dir ) && ! is_link( $dir ) ) {
		return true;
	}

	$temp   = realpath( sys_get_temp_dir() );
	$real   = realpath( $dir );
	$inside = false !== $temp && false !== $real && 0 === strpos( $real, $temp . DIRECTORY_SEPARATOR );

	if ( ! $inside || is_link( $dir ) || ! is_dir( $real ) ) {
		fwrite( STDERR, 'Refused to remove ' . $dir . ': not a directory inside ' . sys_get_temp_dir() . ".\n" );

		return false;
	}

	$walk = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $real, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $walk as $entry ) {
		if ( $entry->isDir() && ! $entry->isLink() ) {
			rmdir( $entry->getPathname() );
		} else {
			unlink( $entry->getPathname() );
		}
	}

	return rmdir( $real );
}
