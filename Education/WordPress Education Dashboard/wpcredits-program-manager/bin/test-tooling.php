<?php
/**
 * Do the scripts under bin/ behave the way a gate has to behave?
 *
 * The three checked here are the ones another script, a hook or a release step is meant to be
 * able to call, and the deep check of 7 September 2026 found all three unusable as gates:
 *
 * - `bin/build` assigned a version from a pipeline ending in `grep -m1` under
 *   `set -euo pipefail`, so a grep that matched nothing aborted the script at that line: no
 *   "built" line, no error, exit 1, and a zip left on disk (FSUIT-10);
 * - `bin/check-standards.sh` ended with phpcs itself, and phpcs exits 2 whenever it reports
 *   anything at all, warnings included, so a clean tree failed the check (FSUIT-11);
 * - `bin/check-spelling.php` read the interface strings out of PHP but not the block titles
 *   and descriptions the inserter shows, which are translated the same way (FSUIT-12).
 *
 * A section checks that the checkers keep to this branch's own tree. Other branches' linked
 * worktrees can sit inside the checkout, under the git-ignored `.worktrees/`, and on 28 September
 * 2026 one of them made `bin/check-references.php` report 84 problems that were the other branch's
 * code checked against this branch's classes, and ran phpcs out of memory, so neither the dead
 * annotations check nor the standards could run at all.
 *
 * The last checks that the suites can run on PHP 7.4, the oldest PHP the plugin supports. A suite
 * that reaches a private or protected method through reflection has to make it accessible first
 * before PHP 8.1, which refuses the call otherwise; since 8.1 the step does nothing, so a suite
 * missing it passes on the PHP here and throws on 7.4, as three did until September 2026. There is
 * no PHP 7.4 here to run them on, so the suites' source is read for it.
 *
 * phpcs is stood in for rather than run: the real thing takes twelve seconds over this plugin
 * and is not installed everywhere, while what is under test is the script's arithmetic on the
 * counts, not phpcs's opinion. The stand-in writes the summary report phpcs would write and
 * exits 2 the way phpcs does, which is the whole point of the finding.
 *
 * Run from the plugin root:  php bin/test-tooling.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/temp-dir.php';

$root = dirname( __DIR__ );

// The scratch tree, under this run's own folder (bin/stubs/temp-dir.php): the probe zips, the
// stand-in phpcs and the trees the spelling check reads all go with it when the run ends.
$scratch = wpcpm_test_temp_dir() . 'scratch';

$fail = 0;

/**
 * One assertion.
 *
 * @param string $label    What is being asked.
 * @param mixed  $actual   What happened.
 * @param mixed  $expected What should have happened.
 */
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * Run a command and hand back its status and both streams.
 *
 * @param string $command Shell command, run from the plugin root.
 * @return array{status:int,out:string,err:string}
 */
function run( $command ) {
	$pipes = array();
	$proc  = proc_open(
		$command,
		array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
		$pipes,
		dirname( __DIR__ )
	);

	if ( ! is_resource( $proc ) ) {
		return array( 'status' => -1, 'out' => '', 'err' => 'could not start' );
	}

	$out = stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );

	return array( 'status' => proc_close( $proc ), 'out' => (string) $out, 'err' => (string) $err );
}

/**
 * Write a file, making its directory first.
 *
 * @param string $path     Absolute path.
 * @param string $contents What to write.
 */
function put( $path, $contents ) {
	if ( ! is_dir( dirname( $path ) ) ) {
		mkdir( dirname( $path ), 0777, true );
	}
	file_put_contents( $path, $contents );
}

mkdir( $scratch, 0777, true );

/* ---- bin/build ----------------------------------------------------------- */

echo "=== bin/build ===\n";

$zip     = $scratch . '/probe.zip';
$built   = run( 'bash bin/build ' . escapeshellarg( $zip ) . ' 2>&1' );
$header  = (string) file_get_contents( $root . '/wpcredits-program-manager.php' );
$version = preg_match( '/^ \* Version:\s*(.+)$/m', $header, $m ) ? trim( $m[1] ) : 'no version in the plugin header';

ck( 'a build of this tree succeeds', array( $built['status'], is_file( $zip ) ), array( 0, true ) );
ck( 'and says what it built, from inside the archive', array(
	false !== strpos( $built['out'], 'built ' . $zip ),
	false !== strpos( $built['out'], 'top-level: wpcredits-program-manager' ),
	false !== strpos( $built['out'], 'version inside: ' . $version ),
), array( true, true, true ) );

// The finding: a zip that cannot be written left the script dead with nothing said. Anything
// that goes wrong has to reach the person who ran it, on stderr, with a non-zero status.
$nowhere = run( 'bash bin/build ' . escapeshellarg( $scratch . '/no/such/directory/probe.zip' ) );

ck( 'a target it cannot write fails, loudly', array(
	0 !== $nowhere['status'],
	'' !== trim( $nowhere['err'] ),
	false !== strpos( $nowhere['err'], 'ERROR' ),
), array( true, true, true ) );

// The mechanism the fix rests on: under `set -e` with `pipefail`, an assignment from a
// pipeline whose grep matches nothing aborts the script, so the line after it can never run.
// That is what made the style.css fallback dead code, and what `|| true` undoes.
$aborts  = run( 'bash -c ' . escapeshellarg( 'set -euo pipefail; v="$(echo | grep -m1 -E "^NOPE")"; echo reached' ) );
$reaches = run( 'bash -c ' . escapeshellarg( 'set -euo pipefail; v="$(echo | grep -m1 -E "^NOPE" || true)"; echo reached' ) );

ck( 'a grep that matches nothing ends the script at the assignment', array( $aborts['status'], trim( $aborts['out'] ) ), array( 1, '' ) );
ck( 'and does not once the assignment cannot fail', array( $reaches['status'], trim( $reaches['out'] ) ), array( 0, 'reached' ) );

$build_src = (string) file_get_contents( $root . '/bin/build' );

ck( 'the builder keeps every failure fatal', false !== strpos( $build_src, 'set -euo pipefail' ), true );
ck( 'the version line cannot abort the script in silence', 1 === preg_match( '/version="\$\(unzip[^\n]*\|\| true\)"/', $build_src ), true );
ck( 'and a version it cannot read is said out loud', false !== strpos( $build_src, 'ERROR: no Version line' ), true );
ck( 'the theme fallback that could never run is gone', false !== strpos( $build_src, 'style.css' ), false );

// The same shape twice more: the entry list was read by its own `unzip` in two separate
// command substitutions, one of them inside printf's own arguments, so an archive that could
// not be read back ended the build at that line with nothing said. One guarded read, reused.
$report_line = preg_match( '/printf \'built[^\n]*\n[^\n]*\n/', $build_src, $m ) ? $m[0] : '';

ck( 'the archive is read back once, and a read that fails is said out loud', array(
	1 === preg_match( '/entries="\$\(unzip -Z1 "\$out" \|\| true\)"/', $build_src ),
	false !== strpos( $build_src, 'ERROR: could not read' ),
	substr_count( $build_src, 'unzip -Z1' ),
), array( true, true, 1 ) );
ck( 'and the line that reports the build runs no command of its own', array( '' !== $report_line, false !== strpos( $report_line, '$(' ) ), array( true, false ) );

/* ---- bin/check-standards.sh ---------------------------------------------- */

echo "\n=== bin/check-standards.sh ===\n";

/**
 * A stand-in for phpcs that writes the summary report phpcs writes and exits the way it does.
 *
 * @param string $dir      Directory to put the executable in.
 * @param int    $errors   Errors to report.
 * @param int    $warnings Warnings to report.
 * @return string The directory, to put on PATH.
 */
function fake_phpcs( $dir, $errors, $warnings ) {
	$total = sprintf(
		'A TOTAL OF %d ERROR%s AND %d WARNING%s WERE FOUND IN 29 FILES',
		$errors,
		1 === $errors ? '' : 'S',
		$warnings,
		1 === $warnings ? '' : 'S'
	);

	put(
		$dir . '/phpcs',
		"#!/bin/sh\n"
		. "# Stand-in for phpcs (bin/test-tooling.php). Writes the summary, exits 2 when it\n"
		. "# reported anything at all, which is what phpcs does and what FSUIT-11 was about.\n"
		. "for arg in \"\$@\"; do\n"
		. "\tcase \"\$arg\" in --report-summary=*) printf '%s\\n' \"" . $total . "\" > \"\${arg#--report-summary=}\" ;; esac\n"
		. "done\n"
		. "echo 'FILE: somewhere.php'\n"
		. "test " . (int) ( $errors + $warnings ) . " -eq 0 && exit 0\n"
		. "exit 2\n"
	);

	chmod( $dir . '/phpcs', 0755 );

	return $dir;
}

/**
 * A stand-in for a phpcs that cannot run at all: no summary report, and the status phpcs
 * returns when it hits a processing error rather than a coding standard finding.
 *
 * @param string $dir Directory to put the executable in.
 * @return string The directory, to put on PATH.
 */
function broken_phpcs( $dir ) {
	put(
		$dir . '/phpcs',
		"#!/bin/sh\n"
		. "# Stand-in for a phpcs that cannot run (bin/test-tooling.php). It writes no summary\n"
		. "# report and exits 3, which is what phpcs does when it cannot load a standard.\n"
		. "echo 'ERROR: the \"WordPress\" coding standard is not installed.' >&2\n"
		. "exit 3\n"
	);

	chmod( $dir . '/phpcs', 0755 );

	return $dir;
}

$clean_dir = fake_phpcs( $scratch . '/clean', 0, 96 );
$dirty_dir = fake_phpcs( $scratch . '/dirty', 3, 5 );
$none_dir  = fake_phpcs( $scratch . '/none', 0, 0 );
$broke_dir = broken_phpcs( $scratch . '/broken' );

$clean = run( 'PATH=' . escapeshellarg( $clean_dir ) . ':$PATH bash bin/check-standards.sh' );
$dirty = run( 'PATH=' . escapeshellarg( $dirty_dir ) . ':$PATH bash bin/check-standards.sh' );
$none  = run( 'PATH=' . escapeshellarg( $none_dir ) . ':$PATH bash bin/check-standards.sh' );
$broke = run( 'PATH=' . escapeshellarg( $broke_dir ) . ':$PATH bash bin/check-standards.sh' );

ck( 'warnings alone are not a failure, whatever status phpcs itself returns', $clean['status'], 0 );
ck( 'and the count is printed for the record', false !== strpos( $clean['out'], '96 warnings' ), true );
ck( 'the report itself is still printed', false !== strpos( $clean['out'], 'FILE: somewhere.php' ), true );
ck( 'an error is a failure', $dirty['status'], 1 );
ck( 'and is named with its count', false !== strpos( $dirty['out'] . $dirty['err'], '3 errors' ), true );
ck( 'a tree with nothing to report passes too', array( $none['status'], false !== strpos( $none['out'], '0 warnings' ) ), array( 0, true ) );

// A gate that passes when its own checker is broken is a worse failure than the one being
// fixed: with no summary line the error count read 0 and the script announced a clean tree.
ck( 'a phpcs that cannot run is a failure, never a clean tree', $broke['status'], 1 );
ck( 'and it says so, naming the status phpcs returned', array(
	false !== strpos( $broke['out'] . $broke['err'], 'phpcs could not be run' ),
	false !== strpos( $broke['out'] . $broke['err'], 'exited 3' ),
	false !== strpos( $broke['out'], 'warnings, no errors' ),
), array( true, true, false ) );

/* ---- bin/check-spelling.php ---------------------------------------------- */

echo "\n=== bin/check-spelling.php ===\n";

ck( 'this tree is US English throughout', run( 'php bin/check-spelling.php' )['status'], 0 );

// A gate that goes green over nothing is not a gate: a mistyped path, and an argument meant
// as a flag, both read as a root holding no files at all and passed without reading a word.
$bad_root = run( 'php bin/check-spelling.php /no/such/tree' );
$stray    = run( 'php bin/check-spelling.php --fix' );

ck( 'a root that is not a plugin tree is refused', array( $bad_root['status'], $stray['status'] ), array( 1, 1 ) );
ck( 'and the message carries the path it was given, and claims nothing', array(
	false !== strpos( $bad_root['out'] . $bad_root['err'], '/no/such/tree' ),
	false !== strpos( $bad_root['out'], 'US English throughout' ),
), array( true, false ) );

// The finding: a block's title and description are what the inserter shows and what
// translators translate, and neither was read. Checked against a scratch tree, so the check
// can be caught being wrong without a British spelling being committed to prove it.
put( $scratch . '/tree/blocks/probe/block.json', "{\n\t\"title\": \"Sponsor Application Form\",\n\t\"description\": \"The form a company fills in.\"\n}\n" );
put( $scratch . '/tree/readme.txt', "Nothing to see.\n" );

$ok_tree = run( 'php bin/check-spelling.php ' . escapeshellarg( $scratch . '/tree' ) );

ck( 'a clean scratch tree passes', $ok_tree['status'], 0 );
ck( 'and says the blocks were read', false !== strpos( $ok_tree['out'], 'block' ), true );

put( $scratch . '/tree/blocks/probe/block.json', "{\n\t\"title\": \"Sponsor Application Programme\",\n\t\"description\": \"The form a company fills in.\"\n}\n" );
$title = run( 'php bin/check-spelling.php ' . escapeshellarg( $scratch . '/tree' ) );

ck( 'a British spelling in a block title is a failure', $title['status'], 1 );
ck( 'named with the file and the word', array(
	false !== strpos( $title['out'], 'blocks/probe/block.json' ),
	false !== strpos( $title['out'], 'Programme' ),
), array( true, true ) );

put( $scratch . '/tree/blocks/probe/block.json', "{\n\t\"title\": \"Sponsor Application Form\",\n\t\"description\": \"Shows the colour of the offer.\"\n}\n" );
$description = run( 'php bin/check-spelling.php ' . escapeshellarg( $scratch . '/tree' ) );

ck( 'and so is one in a description', array( $description['status'], false !== strpos( $description['out'], 'colour' ) ), array( 1, true ) );

// The final fix wave, item 13: the checker matches whole words and its list named "grey", so
// "greyed" passed, as did every -ed and -ing form of a stem the list did not spell out.
$inflected = array();

foreach ( array( 'greyed', 'greying', 'colouring', 'centred', 'catalogued', 'customising', 'summarising', 'authorising', 'prioritised', 'optimising', 'finalised', 'realising', 'practising', 'honoured', 'neighbouring', 'modelling', 'travelling' ) as $word ) {
	put( $scratch . '/tree/blocks/probe/block.json', "{\n\t\"title\": \"Sponsor Application Form\",\n\t\"description\": \"The button is " . $word . " for now.\"\n}\n" );
	$inflected_run      = run( 'php bin/check-spelling.php ' . escapeshellarg( $scratch . '/tree' ) );
	$inflected[ $word ] = array( $inflected_run['status'], false !== strpos( $inflected_run['out'], $word ) );
}

put( $scratch . '/tree/blocks/probe/block.json', "{\n\t\"title\": \"Sponsor Application Form\",\n\t\"description\": \"Grayed, coloring, centered, cataloged, customizing, summarizing, authorizing, prioritized, optimizing, finalized, realizing, practicing, honored, neighboring, modeling, traveling, and two analyses.\"\n}\n" );
$us_forms = run( 'php bin/check-spelling.php ' . escapeshellarg( $scratch . '/tree' ) );

ck( 'a British spelling ending in -ed or -ing is a failure too, named with the word, and the US spelling of each is not',
	array( $inflected, $us_forms['status'] ),
	array( array_fill_keys( array_keys( $inflected ), array( 1, true ) ), 0 ) );

// bin/ is published on the public GitHub mirror, so the prose that explains the fixtures is
// read there like any other text, and one of those files is committed data. Four British
// spellings reached a fixture's own comment before this pass existed.
put( $scratch . '/tree/blocks/probe/block.json', "{\n\t\"title\": \"Sponsor Application Form\",\n\t\"description\": \"The form a company fills in.\"\n}\n" );
put( $scratch . '/tree/bin/fixtures/probe.json', "{\n\t\"_comment\": \"Recorded for one organisation.\"\n}\n" );

$fixture_prose = run( 'php bin/check-spelling.php ' . escapeshellarg( $scratch . '/tree' ) );

ck( 'a British spelling in a fixture comment is a failure', array( $fixture_prose['status'], false !== strpos( $fixture_prose['out'], 'organisation' ) ), array( 1, true ) );

unlink( $scratch . '/tree/bin/fixtures/probe.json' );
put( $scratch . '/tree/bin/anonymize-fixtures.php', "<?php\n/**\n * A script whose docblock names an organisation.\n */\n" );

$script_prose = run( 'php bin/check-spelling.php ' . escapeshellarg( $scratch . '/tree' ) );

ck( 'and so is one in a script that makes or checks them', array( $script_prose['status'], false !== strpos( $script_prose['out'], 'anonymize-fixtures.php' ) ), array( 1, true ) );

// The seed definitions' why notes ship in the zip under includes/tracks/seeds/, and the Track
// Builder shows each to a program manager as a question's Developer note. A script writes them
// into committed data, the reason given above for reading the anonymizer, and no pass read them
// (the deep check of 1.109.1, TESTS-DOCS-7). Only the notes: a question's key is the base's own
// column name, which is left as the base spells it.
unlink( $scratch . '/tree/bin/anonymize-fixtures.php' );
put( $scratch . '/tree/includes/tracks/seeds/probe.json', "{\n\t\"questions\": {\n\t\t\"Favourite colour\": {\n\t\t\t\"label\": \"Favorite color\",\n\t\t\t\"why\": \"The column is the base's, spelled as the base spells it.\"\n\t\t}\n\t}\n}\n" );

$seed_key = run( 'php bin/check-spelling.php ' . escapeshellarg( $scratch . '/tree' ) );

put( $scratch . '/tree/includes/tracks/seeds/probe.json', "{\n\t\"questions\": {\n\t\t\"Hours\": {\n\t\t\t\"label\": \"Hours contributed\",\n\t\t\t\"why\": \"The colour of the chip is the base's.\"\n\t\t}\n\t}\n}\n" );

$seed_note = run( 'php bin/check-spelling.php ' . escapeshellarg( $scratch . '/tree' ) );

ck( 'a British spelling in a seed\'s why note is a failure, named with the seed and the word, and one in a column name the base spells is not', array(
	$seed_key['status'],
	$seed_note['status'],
	false !== strpos( $seed_note['out'], 'includes/tracks/seeds/probe.json' ),
	false !== strpos( $seed_note['out'], 'colour' ),
), array( 0, 1, true, true ) );

/* ---- The other branches' worktrees --------------------------------------- */

echo "\n=== The other branches' worktrees ===\n";

// The references check reads the tree it sits in, so a copy of it is run inside a scratch tree
// holding one sound class and, in a worktree, a class whose references resolve nowhere. The line
// it ends on is pinned whole: the one class read, and its one reference, show the check did not go
// green over nothing. The tree's own folder is named worktrees, without the dot, so a skip wider
// than the `.worktrees/` folder would skip the sound class too.
$tree = $scratch . '/worktrees';

put( $tree . '/bin/check-references.php', (string) file_get_contents( $root . '/bin/check-references.php' ) );
put( $tree . '/includes/class-wpcpm-probe.php', "<?php\nclass WPCPM_Probe {\n\tconst HERE = 1;\n\n\tpublic function here() {\n\t\treturn self::HERE;\n\t}\n}\n" );
put( $tree . '/.worktrees/other-branch/includes/class-wpcpm-elsewhere.php', "<?php\nclass WPCPM_Elsewhere {\n\tpublic function gone() {\n\t\treturn self::GONE + WPCPM_Probe::GONE;\n\t}\n}\n" );

$refs = run( 'php ' . escapeshellarg( $tree . '/bin/check-references.php' ) );

ck( 'the references check reads this checkout\'s own classes and none under .worktrees/, where the other branches\' worktrees sit',
    array( $refs['status'], trim( $refs['out'] ) ),
    array( 0, '1 classes, 1 references checked, 0 form handlers scanned - all resolve' ) );

// phpcs is not run here (the header says why), so its ruleset is read the way phpcs reads it: as
// elements, so a pattern left in a comment counts for nothing. The dead annotations check runs
// phpcs through the same ruleset.
$ruleset  = new DOMDocument();
$loaded   = $ruleset->load( $root . '/phpcs.xml.dist' );
$patterns = array();

foreach ( $ruleset->getElementsByTagName( 'exclude-pattern' ) as $pattern ) {
	$patterns[] = trim( $pattern->textContent );
}

ck( 'the standards, and the dead annotations check through them, leave out .worktrees/ as they leave out .superpowers/',
    array( $loaded, in_array( '*/.worktrees/*', $patterns, true ), in_array( '*/.superpowers/*', $patterns, true ) ),
    array( true, true, true ) );

// The dash scan in bin/check-standards.sh is grep over the tree it is run from, so it is run from a
// scratch plugin tree whose one dash is in a worktree, with the stand-in phpcs reporting nothing.
// The dash is written as its bytes, because this file is scanned by the same rule.
put( $scratch . '/dashes/phpcs.xml.dist', "<?xml version=\"1.0\"?>\n<ruleset name=\"Probe\"/>\n" );
put( $scratch . '/dashes/readme.txt', "Nothing to see.\n" );
put( $scratch . '/dashes/bin/check-spelling.php', (string) file_get_contents( $root . '/bin/check-spelling.php' ) );
put( $scratch . '/dashes/.worktrees/other-branch/includes/probe.php', "<?php\n// The other branch's text, its dash and all: \xe2\x80\x94\n" );

$dashes = run( 'cd ' . escapeshellarg( $scratch . '/dashes' ) . ' && PATH=' . escapeshellarg( $none_dir ) . ':$PATH bash ' . escapeshellarg( $root . '/bin/check-standards.sh' ) );

ck( 'and so does the standards\' dash scan: a dash in another branch\'s worktree is that branch\'s to check',
    array( $dashes['status'], false !== strpos( $dashes['out'] . $dashes['err'], 'Em or en dashes found' ), false !== strpos( $dashes['out'], '0 warnings, no errors.' ) ),
    array( 0, false, true ) );

/* ---- The suites on PHP 7.4 ----------------------------------------------- */

echo "\n=== The suites on PHP 7.4 ===\n";

/**
 * The private and protected members a plugin tree's suites reach through reflection without making
 * them accessible first, as "suite: Class::member".
 *
 * Read from the source: each `$name = new ReflectionMethod( 'Class', 'member' )`, or
 * `ReflectionProperty`, with the names written out; the member's visibility where the plugin
 * declares the class; and whether the suite invokes it, reads it or writes it through `$name`, and
 * calls `$name->setAccessible( true )`. A class the plugin does not declare is a suite's own
 * stand-in, and a public member needs no step.
 *
 * @param string $plugin The plugin tree's root.
 * @return string[]
 */
function wpcpm_reflection_reaches( $plugin ) {
	$declared = array();
	$files    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin . '/includes', FilesystemIterator::SKIP_DOTS ) );

	foreach ( $files as $file ) {
		if ( '.php' === substr( (string) $file, -4 ) && preg_match_all( '/^(?:abstract |final )?class (\w+)/m', (string) file_get_contents( (string) $file ), $classes ) ) {
			foreach ( $classes[1] as $class ) {
				$declared[ $class ] = (string) $file;
			}
		}
	}

	$problems = array();

	foreach ( glob( $plugin . '/bin/test-*.php' ) as $suite ) {
		$source = (string) file_get_contents( $suite );

		preg_match_all( '/\$(\w+)\s*=\s*\(?\s*new Reflection(Method|Property)\(\s*\'(\w+)\'\s*,\s*\'(\w+)\'\s*\)/', $source, $reaches, PREG_SET_ORDER );

		foreach ( $reaches as $reach ) {
			list( , $variable, $kind, $class, $member ) = $reach;

			if ( ! isset( $declared[ $class ] ) ) {
				continue;
			}

			$declaration = 'Method' === $kind
				? '/^\s*((?:(?:public|protected|private|static|abstract|final)\s+)*)function\s+' . $member . '\s*\(/m'
				: '/^\s*((?:(?:public|protected|private|static|var)\s+)*)\$' . $member . '\b/m';

			if ( ! preg_match( $declaration, (string) file_get_contents( $declared[ $class ] ), $found ) || ! preg_match( '/\b(private|protected)\b/', $found[1] ) ) {
				continue;
			}

			$used       = preg_match( '/\$' . $variable . '->(invoke|invokeArgs|getValue|setValue)\(/', $source );
			$accessible = preg_match( '/\$' . $variable . '->setAccessible\(\s*true\s*\)/', $source );

			if ( $used && ! $accessible ) {
				$problems[] = basename( $suite ) . ': ' . $class . '::' . $member;
			}
		}
	}

	sort( $problems );

	return $problems;
}

// A scratch tree first, so the reading is seen to find what it is for: one class with a private
// method and a public one, and three suites reaching them.
$reflected = $scratch . '/reflection';
$reach     = "<?php\n\$m = new ReflectionMethod( 'WPCPM_Probe', '%s' );\n%s\$m->invoke( new WPCPM_Probe() );\n";
$guard     = "if ( PHP_VERSION_ID < 80100 ) {\n\t\$m->setAccessible( true );\n}\n";

put( $reflected . '/includes/class-wpcpm-probe.php', "<?php\nclass WPCPM_Probe {\n\tprivate function hidden() {\n\t\treturn 1;\n\t}\n\n\tpublic function shown() {\n\t\treturn 2;\n\t}\n}\n" );
put( $reflected . '/bin/test-bare.php', sprintf( $reach, 'hidden', '' ) );
put( $reflected . '/bin/test-guarded.php', sprintf( $reach, 'hidden', $guard ) );
put( $reflected . '/bin/test-public.php', sprintf( $reach, 'shown', '' ) );

ck( 'a suite invoking a private method through reflection without making it accessible is found, one that makes it accessible is not, and a public method needs nothing',
    wpcpm_reflection_reaches( $reflected ),
    array( 'test-bare.php: WPCPM_Probe::hidden' ) );
ck( 'and no suite here reaches a private or protected member that way, so each can run on PHP 7.4',
    wpcpm_reflection_reaches( $root ),
    array() );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
