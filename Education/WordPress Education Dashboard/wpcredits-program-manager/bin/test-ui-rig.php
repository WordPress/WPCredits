<?php
/**
 * The picture and measure rig under bin/ui/ (UI shared parts, release 1, Task 2): read as files, never run
 * against a site.
 *
 * Run from the plugin root:  php bin/test-ui-rig.php
 *
 * Checks that the rig never ships (bin/ is in .distignore, which bin/build reads), that every file of it is
 * there, that no file of it holds a personal path, an account's address or a person's name, that every part
 * that talks to a site refuses anything but the local copy at http://localhost:8900 before it starts a
 * browser, and that the must-use plugin leaves a form's record IDs and hidden values alone when it puts
 * invented names in place of real ones. The must-use plugin and real-names.php are loaded in a php process of
 * their own, on stand-ins for the WordPress functions they call.
 *
 * Then node runs bin/js/ui-rig.js, which imports scenes.mjs and compare.mjs: every scene's file name unique at
 * each width, every card and view of the Administrator Dashboard, every other surface and every wp-admin tab
 * photographed, and the comparer's verdict. A machine without node on the path gets one `skip` line for that
 * part, as bin/test-admin-js.php does.
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = dirname( __DIR__ );
$ui   = $root . '/bin/ui';

$fail = 0;
function ck( $l, $a, $e = true ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $l . "\n";
	if ( ! $ok ) { echo "       exp: " . var_export( $e, true ) . "  got: " . var_export( $a, true ) . "\n"; }
}

function read_rig( $path ) {
	return is_file( $path ) ? (string) file_get_contents( $path ) : '';
}

/**
 * Runs PHP code in a process of its own and answers what it printed and its exit status.
 */
function run_php( $code ) {
	$proc = proc_open( array( PHP_BINARY, '-d', 'display_errors=stderr' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $proc ) ) {
		return array( '', -1, 'no process' );
	}
	fwrite( $pipes[0], $code );
	fclose( $pipes[0] );
	$out = stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	return array( (string) $out, proc_close( $proc ), (string) $err );
}

// ------------------------------------------------------------------ the zip carries nothing of bin/ui/
echo "--- kept out of the zip\n";
$distignore = array_map(
	static function ( $l ) {
		return trim( $l );
	},
	explode( "\n", read_rig( $root . '/.distignore' ) )
);
ck( '.distignore holds a line "bin"', in_array( 'bin', $distignore, true ) );
// bin/build's own loop, from `excludes=()` to the `done < "$root/.distignore"` that feeds it, run in bash on
// this repository's .distignore: every line becomes an exclusion of the zip, bin/ and everything under it.
$build = read_rig( $root . '/bin/build' );
$loop  = preg_match( '/^excludes=\(\)\n.*?^done < "\$root\/\.distignore"$/ms', $build, $m ) ? $m[0] : '';
ck( 'bin/build builds its exclusions in a loop over .distignore', '' !== $loop );
ck( 'and hands every one of them to zip', false !== strpos( $build, 'zip -rqX "$out" "$slug" -x "${excludes[@]}"' ) );
$excl = '' === $loop ? '' : (string) shell_exec( 'root=' . escapeshellarg( $root ) . " slug=wpcpm bash -c " . escapeshellarg( $loop . "\nprintf '%s\\n' \"\${excludes[@]}\"" ) . ' 2>&1' );
$excl = array_filter( explode( "\n", $excl ) );
ck( 'and that loop, run on .distignore, excludes bin and everything under it', array( in_array( 'wpcpm/bin', $excl, true ), in_array( 'wpcpm/bin/*', $excl, true ) ), array( true, true ) );

// ------------------------------------------------------------------ every file is there
echo "--- the files\n";
$files = array( 'copy.mjs', 'copy.sh', 'wpcpm-ui-rig.php', 'real-names.php', 'seed.mjs', 'seed-states.php', 'scenes.mjs', 'snap.mjs', 'measure.mjs', 'collect.mjs', 'compare.mjs', 'README.md', 'layout/dump.php', 'layout/page.mjs', 'layout/measure.js', 'layout/run.mjs', 'layout/global-styles.php', 'layout/refresh-global-styles.mjs', 'layout/global-styles.css', 'layout/block-library.css', 'layout/global-styles.json' );
foreach ( $files as $f ) {
	ck( "bin/ui/$f exists", is_file( "$ui/$f" ) );
}
ck( 'bin/js/ui-rig.js exists', is_file( $root . '/bin/js/ui-rig.js' ) );

// ------------------------------------------------------------------ nothing personal in a committed file
echo "--- nothing personal\n";
// The words the prototype's own refuse list wrote out, kept here only as hashes so that no name is
// written in this file either: a word of a rig file whose hash is one of these is a person's name.
$names_hashed = array( 'eee91371f07697da2ffc72fd309f9034b3ba768b', '33c1fdf481c8e628d4c6db7ea8dc77f49f2fa5d7' );
$home         = strtolower( basename( (string) getenv( 'HOME' ) ) );
$rig_texts    = array();
foreach ( $files as $f ) {
	$rig_texts[ "bin/ui/$f" ] = read_rig( "$ui/$f" );
}
$rig_texts['bin/js/ui-rig.js']      = read_rig( $root . '/bin/js/ui-rig.js' );
$personal = array();
foreach ( $rig_texts as $name => $text ) {
	foreach ( array( '/Users/', '@a8c.com', 'a8c' ) as $needle ) {
		if ( false !== stripos( $text, $needle ) ) {
			$personal[] = "$name holds $needle";
		}
	}
	if ( strlen( $home ) >= 4 && false !== stripos( $text, $home ) ) {
		$personal[] = "$name holds the home folder's name";
	}
	preg_match_all( '/\p{L}{3,}/u', $text, $words );
	$lower = array_map(
		static function ( $w ) {
			return mb_strtolower( $w );
		},
		$words[0]
	);
	foreach ( array_unique( $lower ) as $word ) {
		if ( in_array( sha1( $word ), $names_hashed, true ) ) {
			$personal[] = "$name holds a person's name";
		}
	}
	if ( preg_match( '/[\x{2013}\x{2014}]/u', $text ) ) {
		$personal[] = "$name holds an en or em dash";
	}
}
ck( 'no rig file holds a home path, an a8c address, the home folder, a name from the old refuse list or a long dash', $personal, array() );
ck( 'and neither does this suite (no long dash)', (bool) preg_match( '/[\x{2013}\x{2014}]/u', read_rig( __FILE__ ) ), false );

$snap = $rig_texts['bin/ui/snap.mjs'];
ck( 'the names to refuse are read from the copy at run time: copy.sh runs real-names.php on the copy into real-names.json', 1 === preg_match( '/eval-file "[^"]*real-names\.php"[^\n]*> "\$WORK\/real-names\.json"/', $rig_texts['bin/ui/copy.sh'] ) );
ck( 'and snap.mjs reads real-names.json', false !== strpos( $snap, "'real-names.json'" ) );
ck( 'and snap.mjs writes no list of names of its own (no array of quoted capitalized words)', 0 === preg_match( "/\\[\\s*(?:\\.\\.\\.\\w+,\\s*)?'[A-Z][a-z]+'\\s*,\\s*'[A-Z][a-z]+'/", $snap ) );
ck( 'snap.mjs lets through only the role addresses the plugin prints, by the exact address', false !== strpos( $snap, 'const ROLE_ADDRESSES = [\'education\', \'info\'].map(box => `${box}@wordpressfoundation.org`);' ) );
ck( 'and the invented domains, the copy\'s own and the scrub\'s, but no other domain whole', 1 === preg_match( '/^const okMail = m => ROLE_ADDRESSES\.includes\(m\.toLowerCase\(\)\) \|\| \/@\(example\\\\\.\(org\|com\|test\)\|localhost\)\$\/i\.test\(m\);$/m', $snap ) );
$string_callbacks = array();
foreach ( array( 'bin/ui/wpcpm-ui-rig.php', 'bin/ui/real-names.php' ) as $name ) {
	// array_map takes its callback first; array_filter, usort and array_walk take it second.
	if ( preg_match( '/\barray_map\(\s*\'/', $rig_texts[ $name ] ) || preg_match( '/\b(?:array_filter|usort|uasort|uksort|array_walk)\([^;]*?,\s*\'[a-z_]+\'\s*[,)]/', $rig_texts[ $name ] ) ) {
		$string_callbacks[] = $name;
	}
}
if ( preg_match( '/\barray_map\(\s*\'[a-z_]+\'\s*,/', read_rig( __FILE__ ) ) ) {
	$string_callbacks[] = 'bin/test-ui-rig.php';
}
ck( 'no string callbacks in the rig\'s PHP or in this suite: closures', $string_callbacks, array() );

// ------------------------------------------------------------------ the local copy only
echo "--- the local copy only\n";
$copy = $rig_texts['bin/ui/copy.mjs'];
ck( 'copy.mjs holds http://localhost:8900 exactly once', substr_count( $copy, 'http://localhost:8900' ), 1 );
ck( 'and that once is BASE', false !== strpos( $copy, "export const BASE = 'http://localhost:8900';" ) );
ck( 'refuseUnlessCopy throws "refusing: ..." on a base that is not BASE', 1 === preg_match( '/export function refuseUnlessCopy\(base[^)]*\)\s*\{\s*if \(base !== BASE\) throw new Error\(`refusing: /', $copy ) );
ck( 'and reads both siteurl and home from the copy', false !== strpos( $copy, "ask(['option', 'get', 'siteurl'])" ) && false !== strpos( $copy, "ask(['option', 'get', 'home'])" ) && false !== strpos( $copy, 'export function refuseUnlessCopy(base = BASE, ask = wp)' ) );
ck( "WP-CLI is found through WPCPM_DOCKER, with the home folder's default", false !== strpos( $copy, "process.env.WPCPM_DOCKER || join(homedir(), 'Projects/wpcredits-docker')" ) );
ck( 'copy.mjs exports wp and cookiesFor', false !== strpos( $copy, 'export function wp(args' ) && false !== strpos( $copy, 'export function cookiesFor(login)' ) );
$sh     = $rig_texts['bin/ui/copy.sh'];
$guard  = strpos( $sh, '[ "$SITEURL" = "$COPY" ] && [ "$HOMEURL" = "$COPY" ] || { echo "refusing: ' );
$casec  = strpos( $sh, 'case "${1:-}" in' );
ck( 'copy.sh: COPY is http://localhost:8900, and SITEURL and HOMEURL are read from the copy', false !== strpos( $sh, 'COPY="http://localhost:8900"' ) && 1 === preg_match( '/^SITEURL=\$\("\$WP" option get siteurl /m', $sh ) && 1 === preg_match( '/^HOMEURL=\$\("\$WP" option get home /m', $sh ) );
ck( 'and both are compared with the copy\'s address, refusing with exit 1, before the case that acts', false !== $guard && false !== $casec && $guard < $casec && 1 === preg_match( '/\[ "\$SITEURL" = "\$COPY" \] && \[ "\$HOMEURL" = "\$COPY" \] \|\| \{ echo "refusing: [^"]*" >&2; exit 1; \}/', $sh ) );
$restore = preg_match( '/^restore\)\n(.*?)^  ;;$/ms', $sh, $m ) ? $m[1] : '';
$refusal = strpos( $restore, '[ -s "$WORK/plugin-before.tgz" ] && [ -e snapshots/before-ui-rig.tgz ] || { echo "refusing: ' );
$first_rm = strpos( $restore, 'rm ' );
ck( 'restore refuses, before its first rm, unless the plugin\'s tarball is there and not empty and the snapshot before-ui-rig is there', false !== $refusal && false !== $first_rm && $refusal < $first_rm && 1 === preg_match( '/\|\| \{ echo "refusing: [^"]*" >&2; exit 1; \}/', substr( $restore, (int) $refusal, 400 ) ) );
ck( 'restore removes the files the seed made from the list in the work folder, one named file at a time', 1 === preg_match( '/done < "\$WORK\/created-files\.txt"/', $restore ) && 1 === preg_match( '/^\s*rm -f -- "\$DOCKER\/site\/wp-content\/\$rel"; n=\$\(\(n\+1\)\)/m', $restore ) );
$rm_lines = preg_match_all( '/^.*\brm -f -- "\$DOCKER\/site\/wp-content\/\$rel".*$/m', $restore, $mm ) ? $mm[0] : array();
ck( 'and that removal holds no wildcard', 1 === count( $rm_lines ) && false === strpbrk( $rm_lines[0], '*?[' ) );
ck( 'and says how many it removed', false !== strpos( $restore, 'echo "files the seed made, removed: $n"' ) );
$tail_rm = strrpos( $restore, 'rm -f "$WORK/plugin-before.tgz" "$WORK/version-before.txt" "$WORK/real-names.json" "$WORK/created-files.txt"' );
ck( 'a restore that got to its end removes the rig\'s work files, the tarball included, after the copy is checked', false !== $tail_rm && $tail_rm > strpos( $restore, 'bin/check' ) );
ck( 'the seed records every file it makes on the copy in that list', false !== strpos( $rig_texts['bin/ui/seed.mjs'], "join(WORK, 'created-files.txt')" ) );

// The plugin folder's old .worktrees (worktrees and a symlink the container cannot follow) is never taken
// away or unpacked: prepare's install and restore's put-back run here, in bash, on a stand-in folder.
$prepare = preg_match( '/^prepare\)\n(.*?)^  ;;$/ms', $sh, $m ) ? $m[1] : '';
ck( 'copy.sh never removes the plugin folder whole and never installs through WP-CLI', array( false, false ), array( 1 === preg_match( '/rm -rf "\$PLUGINS\/wpcredits-program-manager"\s*$/m', $sh ), false !== strpos( $sh, 'plugin install' ) ) );
ck( 'restore puts the copy\'s own theme back (when theme replaced it) before it checks the copy, and theme saves it first', false !== strpos( $restore, 'tar -xzf "$WORK/theme-before.tgz" -C "$THEMES"' ) && strpos( $restore, 'theme-before.tgz' ) < strpos( $restore, 'bin/check' ) && false !== strpos( $sh, '[ -s "$WORK/theme-before.tgz" ] || tar -czf "$WORK/theme-before.tgz" -C "$THEMES" wpcredits-theme' ) );
ck( 'restore puts the plugin folder back before it restores the database', false !== strpos( $restore, 'tar -xzf' ) && strpos( $restore, 'tar -xzf' ) < strpos( $restore, 'bin/restore before-ui-rig' ) );
$funcs   = preg_match( '/^check_zip\(\) \{\n.*?^\}\n.*?^install_zip\(\) \{\n.*?^\}\n/ms', $sh, $m ) ? $m[0] : '';
$install = '' === $funcs ? '' : $funcs . 'install_zip "$T/new.zip"' . "\n";
$putback = preg_match( '/^  find "\$PLUGINS[^\n]*\n  tar -xzf [^\n]*\n/m', $restore, $m ) ? $m[0] : '';
$lister  = preg_match( '/^worktrees_listing\(\) \{.*\}$/m', $sh, $m ) ? $m[0] : '';
ck( 'the install (check_zip and install_zip), restore\'s put-back and the .worktrees listing are found in copy.sh', array( true, true, true ), array( '' !== $install, '' !== $putback, '' !== $lister ) );
ck( 'prepare installs through install_zip, after it keeps the .worktrees listing', 1 === preg_match( '/worktrees_listing > "\$WORK\/worktrees-before\.txt"\n  install_zip "\$ZIP"/', $prepare ) );
$tmp  = sys_get_temp_dir() . '/wpcpm-ui-rig-test-' . getmypid();
$zips = '(cd "$T/src" && zip -qr "$T/new.zip" wpcredits-program-manager); (cd "$T/src2" && zip -qr "$T/other.zip" wpcredits-program-manager); ';
$run  = '' === $install || '' === $putback || '' === $lister || ! is_executable( '/usr/bin/zip' ) ? '' : (string) shell_exec(
	'set -euo pipefail; T=' . escapeshellarg( $tmp ) . '; rm -rf "$T"; mkdir -p "$T/plugins/wpcredits-program-manager/.worktrees/old" "$T/src/wpcredits-program-manager" "$T/src2/wpcredits-program-manager" "$T/stage" "$T/work"; '
	. 'echo old > "$T/plugins/wpcredits-program-manager/old.php"; echo w > "$T/plugins/wpcredits-program-manager/.worktrees/old/a.php"; ln -s /nonexistent "$T/plugins/wpcredits-program-manager/.worktrees/link"; '
	. 'echo new > "$T/src/wpcredits-program-manager/new.php"; echo b > "$T/src2/wpcredits-program-manager/b.php"; ' . $zips
	. 'tar -czf "$T/work/plugin-before.tgz" -C "$T/plugins" wpcredits-program-manager; '
	. 'PLUGINS="$T/plugins"; STAGE="$T/stage"; WORK="$T/work"; WP=true; ' . $lister . "\n"
	. 'worktrees_listing > "$T/before.txt"; ' . "\n" . $install
	. 'echo "after install: $(ls -A "$PLUGINS/wpcredits-program-manager" | tr "\n" " ")"; ' . "\n"
	. 'install_zip "$T/other.zip"; install_zip "$T/new.zip"; install_zip "$T/other.zip"' . "\n"
	. 'echo "after swaps: $(ls -A "$PLUGINS/wpcredits-program-manager" | tr "\n" " ")"; ' . "\n" . $putback
	. 'echo "after restore: $(ls -A "$PLUGINS/wpcredits-program-manager" | tr "\n" " ")"; worktrees_listing | cmp -s - "$T/before.txt" && echo "worktrees same"; rm -rf "$T"' . "\n"
	. 'true'
);
ck( 'the install, on a folder with .worktrees and a dangling link: the old files go, the zip\'s come in, .worktrees stays', true, false !== strpos( $run, 'after install: .worktrees new.php ' ) );
ck( 'three swaps after it leave only the last build\'s files beside .worktrees', true, false !== strpos( $run, 'after swaps: .worktrees b.php ' ) );
ck( 'and restore\'s put-back brings the old files back, leaves .worktrees as it was and unpacks no copy of it', true, false !== strpos( $run, 'after restore: .worktrees old.php ' ) && false !== strpos( $run, 'worktrees same' ) );
$zip_refusal = '' === $install ? '' : (string) shell_exec(
	'set -euo pipefail; T=' . escapeshellarg( $tmp . '-z' ) . '; rm -rf "$T"; mkdir -p "$T/src/other" "$T/plugins/wpcredits-program-manager" "$T/stage"; echo x > "$T/src/other/x.php"; echo keep > "$T/plugins/wpcredits-program-manager/keep.php"; (cd "$T/src" && zip -qr "$T/new.zip" other); '
	. 'PLUGINS="$T/plugins"; STAGE="$T/stage"; WORK="$T"; WP=true; ' . $lister . "\n" . '( ' . $install . ' ) 2>&1 || echo "exit $?"; ls "$T/plugins/wpcredits-program-manager"; rm -rf "$T"'
);
ck( 'and a zip that holds another folder is refused before anything is removed', true, false !== strpos( $zip_refusal, 'refusing: the zip is empty or holds more' ) && false !== strpos( $zip_refusal, 'exit 1' ) && false !== strpos( $zip_refusal, 'keep.php' ) );

// swap: another build on a prepared copy, from ui-ready, nothing else changed.
$swap = preg_match( '/^swap\)\n(.*?)^  ;;$/ms', $sh, $m ) ? $m[1] : '';
$swap_refuse = strpos( $swap, '|| { echo "refusing: the copy is not prepared' );
ck( 'swap refuses unless the copy is prepared (before-ui-rig, the saved plugin folder, the rig\'s must-use plugin) and ui-ready is there, before it changes anything', false !== $swap_refuse && false !== strpos( $swap, '[ -e snapshots/before-ui-rig.tgz ] && [ -s "$WORK/plugin-before.tgz" ] && [ -f "$MU/wpcpm-ui-rig.php" ]' ) && false !== strpos( $swap, '[ -e snapshots/ui-ready.tgz ] || { echo "refusing: ' ) && $swap_refuse < strpos( $swap, 'bin/restore' ) );
ck( 'swap checks the zip, puts the database back to ui-ready, waits for the copy, then installs the zip the way prepare does and waits again', 1 === preg_match( '/check_zip "\$ZIP"\n  bin\/restore ui-ready\n  wait_for_copy\n  install_zip "\$ZIP"\n  wait_for_copy\n/', $swap ) );
ck( 'restore waits for the copy to answer 200 right after its database restore, before any check', 1 === preg_match( '/bin\/restore before-ui-rig\n  wait_for_copy\n/', $restore ) && strpos( $restore, 'wait_for_copy' ) < strpos( $restore, 'bin/check' ) );
ck( 'every bin/restore in copy.sh is followed by wait_for_copy on the next line', preg_match_all( '/bin\/restore /', $sh ), preg_match_all( '/bin\/restore [^\n]*\n  wait_for_copy\n/', $sh ) );
ck( 'snap.mjs restores only through restore(), which waits for the copy; measure.mjs waits right after its restore', array( true, false, true ), array( false !== strpos( $rig_texts['bin/ui/snap.mjs'], "const restore = async name => { docker('restore', name); await waitForCopy(); };" ), 1 === preg_match( "/docker\('restore'/", str_replace( "const restore = async name => { docker('restore', name);", '', $rig_texts['bin/ui/snap.mjs'] ) ), 1 === preg_match( "/execFileSync\(join\(DOCKER, 'bin\/restore'\), \['ui-ready'\][^\n]*\nawait waitForCopy\(\);/", $rig_texts['bin/ui/measure.mjs'] ) ) );
ck( 'the proxy restart is the copy\'s own proxy service, in the Docker folder, and nothing else', array( 1, 1 ), array( preg_match_all( '/docker compose --progress quiet restart proxy\)/', $sh ), substr_count( $rig_texts['bin/ui/copy.mjs'], "['compose', '--progress', 'quiet', 'restart', 'proxy'], { cwd: DOCKER" ) ) );
// Gate 2: the guide's pictures (images/<file> in the built guide) are pointed at the copy's uploads before the page
// is written, so the guide is not drawn with broken pictures (which snap.mjs refuses to save).
$gi = preg_match( '/^guide_images\(\) \{[^\n]*\}\n/m', $sh, $m ) ? $m[0] : '';
ck( 'guide_images: an images/ picture of the built guide is read from the copy\'s uploads, and nothing else changes', trim( (string) shell_exec( 'bash -c ' . escapeshellarg( $gi . 'printf "%s\n" "<img src=\"images/admin-overview.png\" alt=\"images/x\"><a href=\"images/y\">y</a>" | guide_images' ) ) ), '<img src="http://localhost:8900/wp-content/uploads/2026/08/admin-overview.png" alt="images/x"><a href="images/y">y</a>' );
ck( 'prepare writes the guide through guide_images', false !== strpos( $sh, 'guide_images < "$REPO/docs/build/administrators.html" > "$STAGE/administrators.html"' ) );
// wait_for_copy, run here in bash with stand-ins for the copy's answer and the proxy's restart.
$wait = preg_match( '/^wait_for_copy\(\) \{\n.*?^\}\n/ms', $sh, $m ) ? $m[0] : '';
$wait_run = static function ( $codes ) use ( $wait ) {
	return '' === $wait ? '' : (string) shell_exec( 'bash -c ' . escapeshellarg( 'set -euo pipefail; CODES=(' . $codes . '); copy_code() { local n; n=$(grep -c -v restart "$T/log" || true); local c=${CODES[$n]:-${CODES[${#CODES[@]}-1]}}; echo "$c" >> "$T/log"; echo "$c"; }; restart_proxy() { echo restart >> "$T/log"; }; T=$(mktemp -d); : > "$T/log"; WAIT_STEP=0; ' . "\n" . $wait . '( wait_for_copy ) 2>/dev/null && echo "went on" || echo "stopped $?"; tr "\n" " " < "$T/log"; rm -rf "$T"' ) );
};
ck( 'wait_for_copy: 502 three times restarts the proxy once, then goes on at 200', trim( $wait_run( '502 502 502 200' ) ), "went on\n502 502 502 restart 200" );
ck( 'wait_for_copy: a copy that never answers 200 stops with exit 1 after two restarts', 1 === preg_match( '/^stopped 1\n(502 ){3}restart (502 ){12}restart (502 ){15}$/', $wait_run( '502' ) ), true );
ck( 'wait_for_copy: a copy at 200 is asked once and not touched', trim( $wait_run( '200' ) ), "went on\n200" );
$measure = $rig_texts['bin/ui/measure.mjs'] . $rig_texts['bin/ui/collect.mjs']; // collect.mjs holds what the page is read for
ck( 'measure.mjs counts no item title and no Tools title as an in-card heading', false !== strpos( $measure, ".wpcpm-head__eyebrow, .wpcpm-ui-item__title, .wpcpm-tools__title') || h.matches('.wpcpm-report__sub')) continue;" ) );
ck( 'measure.mjs reads the heading under the phone\'s help line as its own gap, 16 (design 2.3)', false !== strpos( $measure, "p.matches('.wpcpm-ui-fold__help--phone')" ) && false !== strpos( $measure, "kind: h.afterHelp ? 'help-heading' : 'heading-above'" ) && false !== strpos( $measure, "'help-heading': [16]" ) );
ck( 'measure.mjs reads a run of inner folds as its own gap, 0 (design 2.3), and only folds of two cards or two other folds as fold-fold', false !== strpos( $measure, "const run = f.matches('.wpcpm-ui-fold--inner') && n && n.matches('.wpcpm-ui-fold--inner');" ) && false !== strpos( $measure, "'inner-fold-run': [0]" ) );
// Task 28 fix round 2, X1: a fold alone in an item of a run as a list (`WPCPM_UI::inner_list()`) takes the next item's
// fold as its neighbor, so the students' run is read as a run again.
ck( 'measure.mjs reads a run of inner folds as a list as a run: a fold alone in its item takes the next item\'s fold', false !== strpos( $measure, "const item = !n && f.matches('.wpcpm-ui-fold--inner') && f.parentElement && f.parentElement.matches('.wpcpm-ui-folds__item') ? sib(f.parentElement, 1) : null;" ) && false !== strpos( $measure, "if (item && item.matches('.wpcpm-ui-folds__item')) n = item.querySelector(':scope > .wpcpm-ui-fold--inner');" ) );
ck( 'snap.mjs saves no picture in which a picture of the copy is drawn broken (asked again once first)', false !== strpos( $rig_texts['bin/ui/snap.mjs'], 'const lost = await brokenImages(page, BASE);' ) && strpos( $rig_texts['bin/ui/snap.mjs'], 'const lost = await brokenImages(page, BASE);' ) < strpos( $rig_texts['bin/ui/snap.mjs'], 'await page.screenshot(' ) && false !== strpos( $rig_texts['bin/ui/scenes.mjs'], 'export async function brokenImages(page, base, pause = 3000)' ) );
ck( 'stage() refuses an error page before any act and again before the picture, as it refuses a sign-in page', array( 1, 1 ), array( substr_count( $rig_texts['bin/ui/scenes.mjs'], 'const failed = errorPage(await read());' ), substr_count( $rig_texts['bin/ui/scenes.mjs'], "const after = errorPage({ ...(await read()), status: 0 });" ) ) );
ck( 'swap removes nothing, scrubs nothing, snapshots nothing and leaves the must-use plugin and the work folder alone', array( false, false, false, false, false ), array( 1 === preg_match( '/\brm\b/', $swap ), false !== strpos( $swap, 'scrub' ), false !== strpos( $swap, 'bin/snapshot' ), false !== strpos( $swap, '$MU/wpcpm-ui-rig.php"' ) && 1 === preg_match( '/(cp|mv)[^\n]*\$MU/', $swap ), 1 === preg_match( '/>\s*"\$WORK/', $swap ) ) );
ck( 'a relative zip path is resolved for swap as for prepare', false !== strpos( $sh, 'if { [ "${1:-}" = prepare ] || [ "${1:-}" = swap ]; } && [ -n "${2:-}" ]' ) );

foreach ( array( 'snap.mjs', 'measure.mjs' ) as $f ) {
	$t      = $rig_texts[ "bin/ui/$f" ];
	$refuse = strpos( $t, 'refuseUnlessCopy(' );
	$launch = strpos( $t, 'chromium.launch(' );
	ck( "$f calls refuseUnlessCopy( before any chromium.launch(", false !== $refuse && false !== $launch && $refuse < $launch );
	ck( "$f finds Playwright through WPCPM_PLAYWRIGHT, with the home folder's default", false !== strpos( $t, "process.env.WPCPM_PLAYWRIGHT || join(homedir(), 'Projects/wpcredits-release/a11y/node_modules/playwright-core/index.mjs')" ) );
	ck( "$f launches Chrome headless", false !== strpos( $t, "chromium.launch({ channel: 'chrome', headless: true" ) );
	ck( "$f loads no prototype (no wpcpm_proto cookie, no wpcpm-proto)", false === stripos( $t, 'wpcpm_proto' ) && false === stripos( $t, 'wpcpm-proto' ) && false === strpos( $t, '--proto' ) );
}
// Gate 4: an unbroken word of 40 characters in each field a long value can widen a card with, starting no sync.
preg_match( '/\$long = array\((.*?)\n\);/s', $rig_texts['bin/ui/seed-states.php'], $long_m );
preg_match_all( "/=> '([A-Za-z0-9]+)'/", isset( $long_m[1] ) ? $long_m[1] : '', $long_w );
ck( 'the seed holds eleven invented words, each of 40 characters, each beginning with Example', array( 11, true ), array( count( $long_w[1] ), array() === array_filter( $long_w[1], static function ( $w ) { return 40 !== strlen( $w ) || 0 !== stripos( $w, 'example' ); } ) ) );
ck( 'and writes the sync error as an option only, never starting a sync', array( 1, 0 ), array( substr_count( $rig_texts['bin/ui/seed-states.php'], 'update_option( WPCPM_Mentors_Sync::OPT_ERROR' ), preg_match( '/Sync::(start|run|begin|kick)/', $rig_texts['bin/ui/seed-states.php'] ) ) );
ck( 'seed-states.php refuses unless it runs on the prepared local copy, before it writes anything', 1 === preg_match( "/^<\\?php.*?if \\( 'http:\\/\\/localhost:8900' !== \\(string\\) get_option\\( 'siteurl' \\) \\|\\| 'http:\\/\\/localhost:8900' !== \\(string\\) get_option\\( 'home' \\) \\|\\| ! defined\\( 'WPCPM_UI_RIG' \\) \\) \\{\\s*echo [^;]+;\\s*return;/s", $rig_texts['bin/ui/seed-states.php'] ) && strpos( $rig_texts['bin/ui/seed-states.php'], 'WPCPM_UI_RIG' ) < strpos( $rig_texts['bin/ui/seed-states.php'], 'wp_insert_post' ) );
ck( 'the seed records the logo pictures it writes on the copy for restore', 1 === preg_match( '/copyFileSync\(tmp, file\);\s*if \(!mine\) appendFileSync\(CREATED, LOGOS\[key\]/', $rig_texts['bin/ui/seed.mjs'] ) );
ck( 'seed() refuses unless the copy answers as BASE, before it makes anything', 1 === preg_match( '/export async function seed\(newContext, BASE\) \{\s*refuseUnlessCopy\(BASE\);/', $rig_texts['bin/ui/seed.mjs'] ) );
ck( 'compare.mjs needs no site: no refuseUnlessCopy, no browser', false === strpos( $rig_texts['bin/ui/compare.mjs'], 'refuseUnlessCopy' ) && false === strpos( $rig_texts['bin/ui/compare.mjs'], 'chromium' ) );

// The must-use plugin and real-names.php, each loaded on stand-ins in a process of its own.
$stubs = <<<'PHP'
<?php
define( 'ABSPATH', '/' );
$GLOBALS['opts'] = json_decode( getenv( 'RIG_OPTS' ), true );
function get_option( $k, $d = false ) { return isset( $GLOBALS['opts'][ $k ] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_action() {}
function add_filter() {}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wptexturize( $s ) { return str_replace( "'", '&#8217;', (string) $s ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
PHP;

// real-names.php gets stand-ins for everything it reads past its guard, so without the guard it would run
// to the end and print its list: only the guard's own refusal passes the checks below. RIG_REAL_NAMES names
// another copy of the file, which is how a copy with the guard removed is shown to fail them.
$names_stubs = <<<'PHP'
class WPCPM_Rig_Db {
	public $posts = 'wp_posts';
	public function prepare( $q ) { return $q; }
	public function get_col( $q ) { return false !== strpos( $q, 'post_title' ) ? array( 'Quillmoor Polytechnic' ) : array(); }
}
$GLOBALS['wpdb'] = new WPCPM_Rig_Db();
function get_users( $a = array() ) { return array( (object) array( 'ID' => 1, 'display_name' => 'Rowan Adminsworth', 'user_login' => 'admin' ) ); }
function get_user_meta( $id, $k = '', $s = false ) { return 'first_name' === $k ? 'Ellery' : ''; }
function get_post_meta( $id, $k = '', $s = false ) { return ''; }
PHP;
$real_names = getenv( 'RIG_REAL_NAMES' ) ? (string) getenv( 'RIG_REAL_NAMES' ) : "$ui/real-names.php";
$wrong      = array(
	'a site that is not the copy'       => array( 'siteurl' => 'https://example.org', 'home' => 'https://example.org' ),
	'the copy\'s siteurl, another home' => array( 'siteurl' => 'http://localhost:8900', 'home' => 'https://example.org' ),
	'another siteurl, the copy\'s home' => array( 'siteurl' => 'https://example.org', 'home' => 'http://localhost:8900' ),
);
foreach ( $wrong as $label => $opts ) {
	putenv( 'RIG_OPTS=' . json_encode( $opts ) );
	list( $out ) = run_php( $stubs . "\ninclude " . var_export( "$ui/wpcpm-ui-rig.php", true ) . ";\necho defined( 'WPCPM_UI_RIG' ) ? 'LOADED' : 'NOT';\necho '|END';" );
	ck( "wpcpm-ui-rig.php on $label: returns before doing anything", $out, 'NOT|END' );
	list( $out, $status, $err ) = run_php( $stubs . "\n" . $names_stubs . "\ninclude " . var_export( $real_names, true ) . ";\necho '|END';" );
	ck( "real-names.php on $label: prints its refusal, nothing else, and exits 1", array( $out, $status, false !== strpos( $err, "refusing: the copy's siteurl and home are not both http://localhost:8900" ) ), array( '', 1, true ) );
}
putenv( 'RIG_OPTS=' . json_encode( array( 'siteurl' => 'http://localhost:8900', 'home' => 'http://localhost:8900' ) ) );
list( $out, $status, $err ) = run_php( $stubs . "\n" . $names_stubs . "\ninclude " . var_export( $real_names, true ) . ";" );
ck( 'real-names.php on the copy (both addresses http://localhost:8900): prints a JSON list of the names, the logins of the Administrators among them', array( json_decode( $out, true ), $status, $err ), array( array( 'Quillmoor Polytechnic', 'Ellery', 'admin', 'Rowan Adminsworth' ), 0, '' ) );
putenv( 'RIG_OPTS=' . json_encode( array( 'siteurl' => 'http://localhost:8900', 'home' => 'http://localhost:8900' ) ) );
list( $out ) = run_php( $stubs . "\ninclude " . var_export( "$ui/wpcpm-ui-rig.php", true ) . ";\necho defined( 'WPCPM_UI_RIG' ) ? 'LOADED' : 'NOT';" );
ck( 'wpcpm-ui-rig.php on the copy (both addresses http://localhost:8900): defines WPCPM_UI_RIG', $out, 'LOADED' );

// The prototype's hazard: its name replacement rewrote the invented school's own record ID in a form's
// values. A record ID, a hidden value and an option's value are what a form posts: never touched.
$page = '<html><body><p>Quillmoor Polytechnic sent a report.</p>'
	. '<form><input type="hidden" name="wpcpm_institution" value="recSEED0000000042">'
	. '<input type="hidden" name="wpcpm_school" value="Quillmoor Polytechnic">'
	. '<input type="text" name="wpcpm_name" value="Quillmoor Polytechnic">'
	. '<select name="wpcpm_pick"><option value="Quillmoor Polytechnic">Quillmoor Polytechnic</option><option value="recSEED0000000042">recSEED0000000042</option></select>'
	. '<input type="text" name="wpcpm_record" value="recSEED0000000042">'
	. '<input type="checkbox" name="wpcpm_c" value="Quillmoor Polytechnic"><input type="radio" name="wpcpm_r" value="Quillmoor Polytechnic">'
	. '<input type="submit" name="wpcpm_s" value="Quillmoor Polytechnic"><input type="button" name="wpcpm_b" value="Quillmoor Polytechnic">'
	. '<input type="image" name="wpcpm_i" value="Quillmoor Polytechnic"></form>'
	. '<script>var a = {"n":"Escola Polit\\u00e8cnica Quillmoor","r":"recSEED0000000042"};</script>'
	. '<footer class="global-footer"><script>var f = {"n":"Escola Polit\\u00e8cnica Quillmoor"};</script><p>Quillmoor Polytechnic</p></footer>'
	. '<script>var b = {"n":"Escola Polit\\u00e8cnica Quillmoor"};</script></body></html>';
list( $out, , $err ) = run_php( $stubs . "\ninclude " . var_export( "$ui/wpcpm-ui-rig.php", true ) . ";\necho wpcpm_ui_rig_replace_names_in( " . var_export( $page, true ) . ", array( 'Quillmoor Polytechnic', 'Escola Polit\u{00e8}cnica Quillmoor', 'recSEED0000000042' ) );" );
ck( 'the replacement puts a stand-in in place of a real name in the page text', false !== strpos( $out, '<p>Sample College A sent a report.</p>' ) );
ck( 'and in a visible field\'s value', false !== strpos( $out, 'name="wpcpm_name" value="Sample College A"' ) );
ck( 'and in an option\'s text', false !== strpos( $out, '>Sample College A</option>' ) );
ck( 'a hidden input\'s value is left as it is (it is posted)', false !== strpos( $out, 'name="wpcpm_school" value="Quillmoor Polytechnic"' ) && false !== strpos( $out, 'name="wpcpm_institution" value="recSEED0000000042"' ) );
ck( 'an option\'s value is left as it is (it is posted)', false !== strpos( $out, '<option value="Quillmoor Polytechnic">' ) && false !== strpos( $out, '<option value="recSEED0000000042">' ) );
ck( 'a value that is a record ID is never replaced, even in a visible field or as a listed name', false !== strpos( $out, 'name="wpcpm_record" value="recSEED0000000042"' ) && false !== strpos( $out, '>recSEED0000000042</option>' ) );
ck( 'a checkbox\'s, a radio\'s, a submit\'s, a button\'s and an image input\'s value are left as they are, as the hidden one\'s and the option\'s are (seven values a form posts)', substr_count( $out, 'value="Quillmoor Polytechnic"' ), 7 );
ck( 'a name in JSON a script reads is replaced before the footer and after it', array( false !== strpos( $out, 'var a = {"n":"Sample College B"' ), false !== strpos( $out, 'var b = {"n":"Sample College B"}' ) ), array( true, true ) );
ck( 'a record ID in that JSON is left as it is', false !== strpos( $out, '"r":"recSEED0000000042"' ) );
ck( 'the footer is left as it is, its JSON and its text included', false !== strpos( $out, '<footer class="global-footer"><script>var f = {"n":"Escola Polit\\u00e8cnica Quillmoor"};</script><p>Quillmoor Polytechnic</p></footer>' ) );
ck( 'and nothing was printed to stderr', $err, '' );
list( $out ) = run_php( $stubs . "\ninclude " . var_export( "$ui/wpcpm-ui-rig.php", true ) . ";\necho json_encode( array( wpcpm_ui_rig_is_name( 'recSEED0000000042' ), wpcpm_ui_rig_is_name( 'recSEED0000000043' ), wpcpm_ui_rig_is_name( 'Example School of Design' ), wpcpm_ui_rig_is_name( 'Quillmoor Polytechnic' ), wpcpm_ui_rig_is_name( 'ab' ) ) );" );
ck( 'a record ID, an invented school and a two-letter string never enter the list of real names', $out, '[false,false,false,true,false]' );
list( $out ) = run_php( $stubs . "\ninclude " . var_export( "$ui/wpcpm-ui-rig.php", true ) . ";\necho json_encode( array( wpcpm_ui_rig_is_name( 'localhost:8900' ), wpcpm_ui_rig_is_name( 'http://localhost:8900/' ), wpcpm_ui_rig_is_name( 'localhost' ) ) );" );
ck( 'the copy\'s own address is not a name (a school whose website is stored as localhost)', $out, '[false,false,false]' );

// A plain name (no accent, so its JSON form is the name itself) that a script carries or a search attribute
// holds is replaced too, as a whole word; a script's own tag, its src and its id, is never touched.
$page = '<html><body><div class="row" data-wpc-search="student 0001 quillmoor polytechnic photos">x</div>'
	. '<script id="d" src="http://localhost:8900/a.js?quillmoor polytechnic"></script>'
	. '<script>var r = {"institution":"Quillmoor Polytechnic","other":"Quillmoor Polytechnicum"};</script></body></html>';
list( $out ) = run_php( $stubs . "\ninclude " . var_export( "$ui/wpcpm-ui-rig.php", true ) . ";\necho wpcpm_ui_rig_replace_names_in( " . var_export( $page, true ) . ", array( 'Quillmoor Polytechnic', 'localhost:8900' ) );" );
ck( 'a plain name in a script\'s JSON and in a data attribute is replaced', array( false !== strpos( $out, '{"institution":"Sample College A"' ), false !== strpos( $out, 'data-wpc-search="student 0001 Sample College A photos"' ) ), array( true, true ) );
ck( 'but not inside a longer word', false !== strpos( $out, '"other":"Quillmoor Polytechnicum"' ) );
ck( 'and a script tag\'s src is left as it is, the copy\'s address included', false !== strpos( $out, '<script id="d" src="http://localhost:8900/a.js?quillmoor polytechnic"></script>' ) );

// Gate 1: the sponsor agreement's Accept it asked its question (a button's data-wpcpm-confirm) with a real
// company's name in it, because a button's and a form's attributes were never walked. Their words are now;
// what they post and where (name, value, type, form, method, action, formaction) never is.
$page = '<html><body><form method="post" action="http://localhost:8900/wp-admin/admin-post.php?quillmoor polytechnic" data-wpcpm-confirm="Remove Quillmoor Polytechnic&#039;s file?">'
	. '<button type="submit" class="wpcpm-ui-button" name="wpcpm_co" value="Quillmoor Polytechnic" form="f1" formaction="http://localhost:8900/?quillmoor polytechnic" data-wpcpm-confirm="Accept the signed agreement from Quillmoor Polytechnic? It is recorded." aria-label="Accept Quillmoor Polytechnic">Accept it</button>'
	. '</form></body></html>';
list( $out, , $err ) = run_php( $stubs . "\ninclude " . var_export( "$ui/wpcpm-ui-rig.php", true ) . ";\necho wpcpm_ui_rig_replace_names_in( " . var_export( $page, true ) . ", array( 'Quillmoor Polytechnic' ) );" );
ck( 'a button\'s question and label and a form\'s question are walked: the name is replaced in each', array( false !== strpos( $out, 'data-wpcpm-confirm="Accept the signed agreement from Sample College A? It is recorded."' ), false !== strpos( $out, 'aria-label="Accept Sample College A"' ), false !== strpos( $out, 'data-wpcpm-confirm="Remove Sample College A&#039;s file?"' ), substr_count( $out, 'Quillmoor' ) ), array( true, true, true, 1 ) );
ck( 'and a button\'s value, form and formaction and a form\'s action are left as they are', array( false !== strpos( $out, 'name="wpcpm_co" value="Quillmoor Polytechnic" form="f1" formaction="http://localhost:8900/?quillmoor polytechnic"' ), false !== strpos( $out, 'action="http://localhost:8900/wp-admin/admin-post.php?quillmoor polytechnic"' ), $err ), array( true, true, '' ) );

// Gate 2: a size of a picture the copy does not hold is left out of the srcset (the guide's pictures were drawn broken).
$tmp_up = sys_get_temp_dir() . '/wpcpm-rig-up-' . getmypid();
@mkdir( $tmp_up . '/2026/08', 0700, true );
touch( $tmp_up . '/2026/08/a.png' );
touch( $tmp_up . '/2026/08/a-300x200.png' );
putenv( 'RIG_UP=' . $tmp_up );
$srcset_stubs = str_replace( 'function add_filter() {}', 'function add_filter( $h, $f = null ) { $GLOBALS["filters"][ $h ] = $f; } function wp_get_upload_dir() { return array( "baseurl" => "http://localhost:8900/wp-content/uploads", "basedir" => getenv( "RIG_UP" ) ); }', $stubs );
list( $out, , $err ) = run_php( $srcset_stubs . "\ninclude " . var_export( "$ui/wpcpm-ui-rig.php", true ) . ";\n\$u = 'http://localhost:8900/wp-content/uploads/2026/08/';\necho json_encode( array_keys( call_user_func( \$GLOBALS['filters']['wp_calculate_image_srcset'], array( 1320 => array( 'url' => \$u . 'a-1320x943.png' ), 300 => array( 'url' => \$u . 'a-300x200.png' ), 999 => array( 'url' => 'http://other.example.org/b.png' ) ) ) ) );" );
ck( 'a srcset keeps the sizes the copy holds and any other address, and leaves out a size whose file is missing', array( $out, $err ), array( '[300,999]', '' ) );
foreach ( (array) glob( $tmp_up . '/2026/08/*' ) as $tmp_file ) {
	unlink( $tmp_file );
}
@rmdir( $tmp_up . '/2026/08' );
@rmdir( $tmp_up . '/2026' );
@rmdir( $tmp_up );

// Gate B, fix round 1: a read of one invented student's record answers what the live claim and the Student Report Card's
// route read (the reports row's Students link, Name and Email; the Students row's institution link and dates), and nothing
// for any other record.
$student_stubs = <<<'PHP'
class WPCPM_Settings { public static function get() { return array( 'reports_table' => 'tblReports', 'students_table' => 'tblStudents' ); } }
class WPCPM_Students_Sync { const META_RECORD_ID = 'wpcpm_student_record_id'; const META_INSTITUTION = 'wpcpm_student_institution'; }
class WPCPM_Roster_Index { public static function rows( $i ) { return 'recSchoolExample01' === $i ? array( 'recRigStudentAAAAA' => array( 'record_id' => 'recRigStudentAAAAA', 'status' => 'Active', 'start' => '2026-02-02', 'end' => '2026-06-26' ) ) : array(); } }
function get_users( $a ) { $u = array( 'recRigStudentAAAAA' => 'drew.example@example.org', 'recRigStudentBBBBB' => 'someone@elsewhere.test' ); return isset( $u[ $a['meta_value'] ] ) ? array( (object) array( 'ID' => 7, 'display_name' => 'Drew Example', 'user_email' => $u[ $a['meta_value'] ] ) ) : array(); }
function get_user_meta( $id, $k = '', $s = false ) { return 'wpcpm_student_institution' === $k ? 'recSchoolExample01' : ''; }
PHP;
list( $out, , $err ) = run_php( $stubs . "\n" . $student_stubs . "\ninclude " . var_export( "$ui/wpcpm-ui-rig.php", true ) . ";\necho json_encode( array( wpcpm_ui_rig_student_fields( 'tblReports', 'recRigStudentAAAAA' ), wpcpm_ui_rig_student_fields( 'tblStudents', 'recRigStudentAAAAA' ), wpcpm_ui_rig_student_fields( 'tblStudents', 'recRealStudent0001' ), wpcpm_ui_rig_student_fields( 'tblStudents', 'recRigStudentBBBBB' ), wpcpm_ui_rig_student_fields( 'tblOther', 'recRigStudentAAAAA' ) ) );" );
ck(
	'the stand-in answers an invented student\'s reports row (Name, Email, Students link) and Students row (institution link, dates), and nothing for a real record, an address that is not invented or another table',
	array( json_decode( $out, true ), $err ),
	array(
		array(
			array( 'Name' => 'Drew Example', 'Email' => 'drew.example@example.org', 'Students' => array( 'recRigStudentAAAAA' ) ),
			array( 'Full Name' => 'Drew Example', 'Email' => 'drew.example@example.org', 'Status' => 'Active', 'Educational Institutions' => array( 'recSchoolExample01' ), 'Start Date' => '2026-02-02', 'End Date' => '2026-06-26' ),
			array(),
			array(),
			array(),
		),
		'',
	)
);
ck( 'and the one-record GET answers with those fields', false !== strpos( $rig_texts['bin/ui/wpcpm-ui-rig.php'], "\$fields = wpcpm_ui_rig_student_fields( \$table, \$record );" ) );

// ------------------------------------------------------------------ the layout harness (institution dashboard frame, Task 1)
echo "--- bin/ui/layout\n";
$all = implode( "\n", array_map( static function ( $f ) { return (string) @file_get_contents( $f ); }, (array) glob( $root . '/bin/ui/layout/*' ) ) );
ck( 'the harness holds no home folder', preg_match( '#/(?:Users|home)/[A-Za-z]#', $all ), 0 );
ck( 'the harness holds no site address', preg_match( '#https?://(?!www\.w3\.org)#', $all ), 0 );
ck( 'the harness never calls the copy', false === strpos( $all, 'refuseUnlessCopy' ), true );
ck( 'the eight main-area widths', (bool) preg_match( '/export const WIDTHS = \[358, 569, 736, 799, 800, 880, 936, 960\];/', $all ), true );
ck( 'dump.php writes only when asked', false !== strpos( $all, "getenv( 'WPCPM_LAYOUT_OUT' )" ), true );

// ------------------------------------------------------------------ node: scenes and the comparer
echo "--- bin/js/ui-rig.js\n";
$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
if ( '' === $node ) {
	echo "skip bin/js/ui-rig.js was not run: node is not on the path\n";
} else {
	$out    = array();
	$status = 0;
	exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $root . '/bin/js/ui-rig.js' ) . ' ' . escapeshellarg( $root ) . ' 2>&1', $out, $status );
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
	ck( 'ui-rig.js ran scenarios', $ran > 0, true );
	ck( 'and its own summary counts the scenarios and the failures shown above', $summary, array( $ran, $differ ) );
	ck( 'and node ended with a status of 0, or of 1 when a scenario differed', $status, $differ ? 1 : 0 );
}

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
