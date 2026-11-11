<?php
/**
 * Writes the translation template translators are given: the strings students and mentors read.
 *
 * `languages/wpcredits-program-manager.pot` lists every string in the plugin, and most of them are
 * screens only Administrators, sponsors and institutions open. translate.wordpress.org is given a
 * smaller template: the entries of the full one that are used in a file students or mentors see, or
 * in an email the site sends them. The full template stays as it is; this one is cut from it, so the
 * two can never disagree about a sentence, and nothing but PHP is needed to make it.
 *
 * A file is on the list whole. Some of them hold a few lines only an Administrator sees (a call's
 * admin note, say), and those go to translators too: a file is the unit, because a line's reader is
 * not something the template records.
 *
 * The domain is unchanged, so a translation of this template loads as the plugin's own: a string
 * that is not in it stays in English.
 *
 * Usage, from the plugin root:
 *
 *     php bin/translators-pot.php <output.pot>      write the template
 *     php bin/translators-pot.php --count           print how many strings it would hold
 *
 * The theme's strings are cut the same way, from its own template and its own list (a text file,
 * one path to a line, `#` starting a comment), so the one cutter serves both:
 *
 *     php bin/translators-pot.php <output.pot> --source=<theme>/languages/wpcredits-theme.pot \
 *         --files=<theme>/bin/translators-files.txt
 *
 * Exit status 1, with a sentence on STDERR, when the full template is missing, a listed file no
 * longer has a string in it (renamed or removed: the list needs the new name), or nothing matched.
 *
 * @package WPCredits_Program_Manager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

/**
 * The files whose strings translators are given, relative to the plugin root.
 *
 * @return string[]
 */
function wpcpm_translators_files() {
	return array(
		// What a student opens: the Student Report Card, its forms and the pages a new student is sent to.
		'includes/modules/class-wpcpm-students-dashboard.php',
		'includes/modules/class-wpcpm-student-checklist.php',
		'includes/modules/class-wpcpm-student-feedback.php',
		'includes/modules/class-wpcpm-student-report-form.php',
		'includes/modules/class-wpcpm-student-registration-form.php',
		'includes/modules/class-wpcpm-student-interest-form.php',
		'includes/modules/class-wpcpm-start-pages.php',

		// What a mentor opens: their students page, availability, call notes and the application form.
		'includes/modules/class-wpcpm-mentors-dashboard.php',
		'includes/modules/class-wpcpm-mentor-availability.php',
		'includes/modules/class-wpcpm-mentor-notes.php',
		'includes/modules/class-wpcpm-mentor-application-form.php',

		// What both see: calls, group sessions, the sponsors' tools, the program's updates, their own
		// language, the dashboards' frame and what a refused form says.
		'includes/modules/class-wpcpm-mentor-calls.php',
		'includes/modules/class-wpcpm-call-calendar.php',
		'includes/modules/class-wpcpm-group-sessions.php',
		'includes/modules/class-wpcpm-sponsor-tools.php',
		'includes/class-wpcpm-updates.php',
		'includes/class-wpcpm-dashboard-frame.php',
		'includes/class-wpcpm-language.php',
		'includes/class-wpcpm-dashboards.php',
		'includes/class-wpcpm-form-errors.php',
		'includes/class-wpcpm-form-guard.php',
		'includes/class-wpcpm-form-stash.php',
		'includes/class-wpcpm-typed-text.php',
		'includes/class-wpcpm-image-upload.php',
		'includes/class-wpcpm-notices.php',
		'includes/class-wpcpm-program.php',
		'includes/class-wpcpm-cohort.php',
		'includes/class-wpcpm-learn.php',
		'includes/tools/class-wpcpm-handbook-assistant.php',

		// The emails the site sends students and mentors.
		'includes/mail/templates/students.php',
		'includes/mail/templates/student-registrations.php',
		'includes/mail/templates/student-interests.php',
		'includes/mail/templates/calls.php',
		'includes/mail/templates/invitations.php',
		'includes/mail/templates/mentor-applications.php',
	);
}

/**
 * Cuts the full template down to the entries used in one of the listed files.
 *
 * @param string   $pot   The full template's text.
 * @param string[] $files The files to keep, relative to the plugin root.
 * @return array{pot:string,count:int,unused:string[]} The smaller template, how many strings it
 *                                                     holds, and the listed files no entry names.
 */
function wpcpm_translators_pot( $pot, array $files ) {
	$pot    = str_replace( "\r\n", "\n", $pot );
	$blocks = preg_split( "/\n{2,}/", trim( $pot ) );
	$wanted = array_fill_keys( $files, 0 );
	$kept   = array();
	$header = '';

	foreach ( $blocks as $block ) {
		// The header is the one entry with an empty msgid.
		if ( '' === $header && preg_match( '/^msgid ""\nmsgstr ""\n"/m', $block ) ) {
			$header = $block;
			continue;
		}
		if ( ! preg_match_all( '/^#: (.+)$/m', $block, $lines ) ) {
			continue;
		}
		$keep = false;
		foreach ( $lines[1] as $line ) {
			foreach ( preg_split( '/\s+/', trim( $line ) ) as $reference ) {
				$file = preg_replace( '/:\d+$/', '', $reference );
				if ( isset( $wanted[ $file ] ) ) {
					++$wanted[ $file ];
					$keep = true;
				}
			}
		}
		if ( $keep ) {
			$kept[] = $block;
		}
	}

	$unused = array_keys( array_filter( $wanted, static function ( $uses ) {
		return 0 === $uses;
	} ) );

	return array(
		'pot'    => $header . "\n\n" . implode( "\n\n", $kept ) . "\n",
		'count'  => count( $kept ),
		'unused' => $unused,
	);
}

/**
 * Reads a list of files kept as text: one path to a line, blank lines and `#` comments passed over.
 *
 * @param string $text The list file's content.
 * @return string[]
 */
function wpcpm_translators_list( $text ) {
	$files = array();
	foreach ( preg_split( '/\r?\n/', (string) $text ) as $line ) {
		$line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
		if ( '' !== $line ) {
			$files[] = $line;
		}
	}
	return $files;
}

// Loaded by the test for the functions above: nothing below runs.
if ( defined( 'WPCPM_TRANSLATORS_POT_AS_LIBRARY' ) ) {
	return;
}

$wpcpm_root   = dirname( __DIR__ );
$wpcpm_source = $wpcpm_root . '/languages/wpcredits-program-manager.pot';
$wpcpm_files  = wpcpm_translators_files();
$wpcpm_list   = 'bin/translators-pot.php';
$wpcpm_target = '';

foreach ( array_slice( $argv, 1 ) as $wpcpm_arg ) {
	if ( 0 === strpos( $wpcpm_arg, '--source=' ) ) {
		$wpcpm_source = substr( $wpcpm_arg, 9 );
	} elseif ( 0 === strpos( $wpcpm_arg, '--files=' ) ) {
		$wpcpm_list = substr( $wpcpm_arg, 8 );
		if ( ! is_readable( $wpcpm_list ) ) {
			fwrite( STDERR, "The list of files is missing: $wpcpm_list.\n" );
			exit( 1 );
		}
		$wpcpm_files = wpcpm_translators_list( (string) file_get_contents( $wpcpm_list ) );
	} elseif ( '' === $wpcpm_target ) {
		$wpcpm_target = $wpcpm_arg;
	} else {
		fwrite( STDERR, "Unknown argument: $wpcpm_arg.\n" );
		exit( 1 );
	}
}

if ( '' === $wpcpm_target ) {
	fwrite( STDERR, "Say where to write the template: php bin/translators-pot.php <output.pot>, or --count.\n" );
	exit( 1 );
}
if ( ! is_readable( $wpcpm_source ) ) {
	fwrite( STDERR, "The full template is missing: $wpcpm_source. Run sh bin/make-pot.sh first.\n" );
	exit( 1 );
}

$wpcpm_result = wpcpm_translators_pot( (string) file_get_contents( $wpcpm_source ), $wpcpm_files );

if ( $wpcpm_result['unused'] ) {
	fwrite( STDERR, "No string in the template comes from: " . implode( ', ', $wpcpm_result['unused'] ) . ". A renamed or removed file needs its new name in $wpcpm_list.\n" );
	exit( 1 );
}
if ( 0 === $wpcpm_result['count'] ) {
	fwrite( STDERR, "Nothing matched, so nothing was written.\n" );
	exit( 1 );
}

if ( '--count' === $wpcpm_target ) {
	echo $wpcpm_result['count'] . "\n";
	exit( 0 );
}
if ( false === file_put_contents( $wpcpm_target, $wpcpm_result['pot'] ) ) {
	fwrite( STDERR, "The template could not be written to $wpcpm_target.\n" );
	exit( 1 );
}
echo $wpcpm_result['count'] . " strings written to $wpcpm_target\n";
