<?php
/**
 * Static check: every `.wpcpm-*` class the theme dresses is one the plugin or the theme prints.
 *
 * The theme skins markup it does not write. The plugin prints the dashboards, these
 * stylesheets name their classes, and nothing ties the two together: a class the plugin
 * stops printing leaves its rule behind, styling nothing, and the file reads exactly as it
 * did. Nine such rules sat in dashboard.css after the triage and the student edit form moved
 * into the plugin, and anybody tuning one of them would have changed nothing on any page.
 *
 * So every `.wpcpm-*` selector in the theme's stylesheets is read off the comment-stripped
 * source and looked for, as a whole class name, in every PHP, JS, HTML and JSON file of the
 * plugin and the theme. A modifier the plugin assembles at run time - `wpcpm-badge--50h` is
 * printed as `'wpcpm-badge--' . $modifier` - passes when both halves are in the source: the
 * stem with its `--`, and the modifier as a quoted literal. Those are listed, so a reader can
 * see what was taken on trust.
 *
 * A class a current plugin no longer prints, but whose rule stays so that an older plugin still
 * looks right after a rollback, is listed in `$kept` with the last plugin version that printed it,
 * and is reported as kept rather than missed.
 *
 * The Education Hub's own classes (theme 1.24.15) are looked up the same way, in the theme alone:
 * every `.wpc-hub-*` and `.wpc-figure*` selector has to be printed by one of its patterns, templates,
 * blocks or `inc/` files, since nothing else prints them. Those files are read without their PHP and
 * JavaScript comments, so a class that only a comment names does not count as printed.
 *
 * The plugin's classes the theme's scripts read (theme 1.24.20) are looked up the other way round:
 * every `wpcpm-` name inside a quoted string of the theme's `assets/js/*.js` and of each block's
 * `view.js`, read without comments, has to be in the plugin's own PHP, JS, HTML and JSON source,
 * read without comments too. The plugin's alone, since in the theme the script that reads a name
 * would vouch for it: the header's menu script reads `wpcpm-hb-open`, the class the plugin puts on
 * the root element while its handbook assistant is open. A name that follows a hyphen is part of
 * another, so the data attribute in `[data-wpcpm-handbook-open]` is not read as a class. A miss is
 * listed with the file and line that read it.
 *
 * Usage:  php bin/check-selectors.php [plugin-path]      (run from the theme root)
 *
 * The plugin defaults to `../wpcredits-program-manager`, beside the theme's own folder, which
 * is where both live in the working copy. Exits 1 on a miss, so it sits beside `phpcs` in the
 * same run and fails it the day a class goes.
 *
 * @package WPCreditsTheme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$theme  = dirname( __DIR__ );
$plugin = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : dirname( $theme ) . '/wpcredits-program-manager';

if ( ! is_dir( $plugin . '/includes' ) ) {
	fwrite( STDERR, "No plugin at $plugin - pass its path as the first argument.\n" );
	exit( 1 );
}

/**
 * Whether a file sits in one of the folders a walk leaves out, judged by its path below the
 * root.
 *
 * @param string   $root Directory the walk started from.
 * @param string   $path The file's whole path.
 * @param string[] $skip Folder names, each between slashes.
 * @return bool
 */
function wpcredits_skipped( $root, $path, array $skip ) {
	$below = substr( $path, strlen( rtrim( $root, '/' ) ) );

	foreach ( $skip as $part ) {
		if ( false !== strpos( $below, $part ) ) {
			return true;
		}
	}

	return false;
}

/**
 * The source files under a root, by extension.
 *
 * Skips what is not source: version control, the hidden folders where linked worktrees and
 * session files hold other copies of the plugin and the theme (a class only another line of
 * development prints must not vouch for a rule here), dependencies, the development scripts
 * (this one names classes in its own comments) and the documentation. Each is looked for in
 * the path below the root, not in the root's own: a root that is itself a linked worktree
 * sits under `.worktrees/`, and matching the whole path would skip every file of it and
 * leave the check passing with nothing checked.
 *
 * @param string   $root       Directory.
 * @param string[] $extensions Lower-case extensions, without the dot.
 * @return string[] Paths, sorted.
 */
function wpcredits_source_files( $root, array $extensions ) {
	$skip  = array( '/.git/', '/.worktrees/', '/.superpowers/', '/node_modules/', '/vendor/', '/bin/', '/docs/' );
	$files = array();

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$path = $file->getPathname();

		if ( ! $file->isFile() || ! in_array( strtolower( $file->getExtension() ), $extensions, true ) ) {
			continue;
		}

		if ( wpcredits_skipped( $root, $path, $skip ) ) {
			continue;
		}

		$files[] = $path;
	}

	sort( $files );

	return $files;
}

/**
 * Every `.wpcpm-*` class a stylesheet's selectors name, or every class of another family, with the
 * line each is on.
 *
 * Comments are blanked first, line for line, so a class mentioned in prose is not counted
 * and the line numbers still point into the file. Only the selector of each rule is read -
 * the text between the previous brace and the next `{` - and at-rules are skipped.
 *
 * @param string $css     The stylesheet.
 * @param string $pattern The family, as a pattern whose first group is the class name.
 * @return array[] Rows of `class`, `line`.
 */
function wpcredits_classes_dressed( $css, $pattern = '/\.(wpcpm-[A-Za-z0-9_-]+)/' ) {
	$marked = preg_replace_callback(
		'#/\*.*?\*/#s',
		function ( $m ) {
			return str_repeat( "\n", substr_count( $m[0], "\n" ) );
		},
		$css
	);

	preg_match_all( '/([^{}]*)\{/', $marked, $preludes, PREG_OFFSET_CAPTURE );

	$rows = array();

	foreach ( $preludes[1] as $prelude ) {
		$selector = trim( $prelude[0] );

		if ( '' === $selector || '@' === $selector[0] ) {
			continue;
		}

		preg_match_all( $pattern, $prelude[0], $classes, PREG_OFFSET_CAPTURE );

		foreach ( $classes[1] as $class ) {
			$rows[] = array(
				'class' => $class[0],
				'line'  => substr_count( $marked, "\n", 0, $prelude[1] + $class[1] ) + 1,
			);
		}
	}

	return $rows;
}

/**
 * Whether a name appears whole somewhere in the source.
 *
 * Whole, so `wpcpm-student` is not vouched for by `wpcpm-student__grid`.
 *
 * @param string $corpus Every source file, concatenated.
 * @param string $name   Class name, or a stem ending in `--`.
 * @return bool
 */
function wpcredits_printed( $corpus, $name ) {
	return (bool) preg_match( '/(?<![A-Za-z0-9_-])' . preg_quote( $name, '/' ) . '(?![A-Za-z0-9_-])/', $corpus );
}

/**
 * The alternatives that match one JavaScript string literal, double-quoted, single-quoted or a template
 * literal, with its escapes: what both the comment stripper below and the scan of the scripts' quoted
 * names read a string by, so they cannot come to disagree about where a string ends.
 */
const WPCREDITS_JS_STRING = <<<'REGEX'
"(?:[^"\\\n]|\\.)*"|'(?:[^'\\\n]|\\.)*'|`(?:[^`\\]|\\.)*`
REGEX;

/**
 * A PHP or JavaScript file's source without its comments, so a class named only in a comment is not
 * taken as printed. PHP is read with the tokenizer, which knows a comment from a string holding two
 * slashes; JavaScript by a pattern that keeps every string and drops what is between its quotes only
 * if it is a comment, which a regular expression literal holding a quote would confuse (none of the
 * theme's scripts has one). A JavaScript comment leaves its line breaks behind, or a space, so a line
 * counted in what is returned is the file's line. Any other kind of file is returned as it is: an
 * HTML comment in a template holds a block's attributes, which are printed.
 *
 * @param string $source    The file's contents.
 * @param string $extension Its lower-case extension.
 * @return string
 */
function wpcredits_without_comments( $source, $extension ) {
	if ( 'php' === $extension ) {
		$code = '';

		foreach ( token_get_all( $source ) as $token ) {
			if ( ! is_array( $token ) ) {
				$code .= $token;
			} elseif ( ! in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				$code .= $token[1];
			}
		}

		return $code;
	}

	if ( 'js' === $extension ) {
		// A string is kept whole; a block comment or a line comment, met outside one, is dropped.
		$pattern = '~(' . WPCREDITS_JS_STRING . ')|/\*.*?\*/|//[^\n]*~s';

		return (string) preg_replace_callback(
			$pattern,
			function ( $m ) {
				if ( isset( $m[1] ) && '' !== $m[1] ) {
					return $m[1];
				}

				$breaks = substr_count( $m[0], "\n" );

				return $breaks ? str_repeat( "\n", $breaks ) : ' ';
			},
			$source
		);
	}

	return $source;
}

$corpus = '';

foreach ( array( $plugin, $theme ) as $root ) {
	foreach ( wpcredits_source_files( $root, array( 'php', 'js', 'html', 'json' ) ) as $path ) {
		$corpus .= "\n" . file_get_contents( $path );
	}
}

// Class name => every `file:line` that dresses it.
$dressed = array();
$sheets  = wpcredits_source_files( $theme, array( 'css' ) );

foreach ( $sheets as $sheet ) {
	foreach ( wpcredits_classes_dressed( (string) file_get_contents( $sheet ) ) as $row ) {
		$dressed[ $row['class'] ][] = substr( $sheet, strlen( $theme ) + 1 ) . ':' . $row['line'];
	}
}

ksort( $dressed );

// Classes the plugin stopped printing in a release and the theme still dresses, so that a plugin
// rolled back to that release looks right. Each is named with the last plugin version that printed
// it; the entry and its rule go together once that version is no longer supported.
$kept = array(
	'wpcpm-dashboard__switcher-none' => '1.122.14',
);

$misses = 0;
$built  = 0;
$stayed = 0;

foreach ( $dressed as $class => $where ) {
	if ( wpcredits_printed( $corpus, $class ) ) {
		continue;
	}

	if ( isset( $kept[ $class ] ) ) {
		++$stayed;
		printf( "  kept   .%-44s last printed by plugin %s  (%s)\n", $class, $kept[ $class ], implode( ', ', $where ) );
		continue;
	}

	// A modifier assembled at run time: the stem is printed with its `--`, and the modifier
	// is a quoted literal somewhere in the source.
	$at = strpos( $class, '--' );

	if ( false !== $at ) {
		$stem     = substr( $class, 0, $at + 2 );
		$modifier = substr( $class, $at + 2 );

		if ( wpcredits_printed( $corpus, $stem ) && preg_match( '/[\'"]' . preg_quote( $modifier, '/' ) . '[\'"]/', $corpus ) ) {
			++$built;
			printf( "  built  .%-44s '%s' . '%s'  (%s)\n", $class, $stem, $modifier, implode( ', ', $where ) );
			continue;
		}
	}

	++$misses;
	printf( "  MISS   .%-44s nothing prints it  (%s)\n", $class, implode( ', ', $where ) );
}

printf(
	"\n%d classes dressed across %d stylesheets: %d built at run time, %d kept for an older plugin, %d printed by nothing.\n",
	count( $dressed ),
	count( $sheets ),
	$built,
	$stayed,
	$misses
);

// The Education Hub's classes, printed by the theme alone: its patterns, its templates, its blocks
// and its `inc/` files, which is where the hub's markup is written, read without their comments.
$hub_corpus = '';

foreach ( array( 'patterns', 'templates', 'blocks', 'inc' ) as $folder ) {
	if ( is_dir( $theme . '/' . $folder ) ) {
		foreach ( wpcredits_source_files( $theme . '/' . $folder, array( 'php', 'js', 'html', 'json' ) ) as $path ) {
			$hub_corpus .= "\n" . wpcredits_without_comments( (string) file_get_contents( $path ), strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) );
		}
	}
}

$hub_dressed = array();

foreach ( $sheets as $sheet ) {
	foreach ( wpcredits_classes_dressed( (string) file_get_contents( $sheet ), '/\.(wpc-(?:hub|figure)(?:[A-Za-z0-9_-]*))/' ) as $row ) {
		$hub_dressed[ $row['class'] ][] = substr( $sheet, strlen( $theme ) + 1 ) . ':' . $row['line'];
	}
}

ksort( $hub_dressed );

$hub_misses = 0;

foreach ( $hub_dressed as $class => $where ) {
	if ( ! wpcredits_printed( $hub_corpus, $class ) ) {
		++$hub_misses;
		printf( "  MISS   .%-44s nothing in the theme prints it  (%s)\n", $class, implode( ', ', $where ) );
	}
}

printf( "%d hub and figure classes dressed, %d printed by nothing.\n", count( $hub_dressed ), $hub_misses );

// Every custom property the theme reads must be one it declares. A `var( --wpc-x )` that is
// never defined resolves to its fallback or to nothing, and the browser says nothing: 1.19.2
// found the report card's input borders drawn in the card hairline and the rating stars
// without a focus ring for exactly this reason.
$token_files = array_merge( glob( $theme . '/*.css' ) ?: array(), glob( $theme . '/assets/css/*.css' ) ?: array() );
$tokens_used = array();
$tokens_set  = array();
foreach ( $token_files as $path ) {
	$css = (string) file_get_contents( $path );
	preg_match_all( '/var\(\s*(--wpc-[a-z0-9-]+)/', $css, $m );
	foreach ( $m[1] as $token ) {
		$tokens_used[ $token ][ basename( $path ) ] = true;
	}
	preg_match_all( '/(--wpc-[a-z0-9-]+)\s*:/', $css, $m );
	foreach ( $m[1] as $token ) {
		$tokens_set[ $token ] = true;
	}
}
$undefined = array_diff_key( $tokens_used, $tokens_set );
foreach ( $undefined as $token => $where ) {
	printf( "  TOKEN  %-44s read but never declared  (%s)\n", $token, implode( ', ', array_keys( $where ) ) );
}
printf( "%d tokens read, %d declared, %d undefined.\n", count( $tokens_used ), count( $tokens_set ), count( $undefined ) );
if ( $undefined ) {
	exit( 1 );
}

// The shared dashboard-table dressing keeps its scope.
//
// One block dresses the Student Report Card's tables, the Institution Dashboard's, the Sponsor
// Dashboard's Usage card and the Administrator Dashboard's queues, so that a manager moving
// between the pages sees one table drawn one way (deep check FFRNT-7). Writing it once meant
// writing it without the page prefixes the rest of the sheet uses, which widened it from "a
// table on a dashboard" to "any table this theme draws", on a post as much as on a dashboard;
// nothing shows it today only because no plugin rule outweighs it yet (the whole-branch review
// of 1.99.0). A rule that dresses several pages has to say which pages it is for.
//
// Two rules, both read off the comment-stripped source:
//   - a block whose selector list names more than one of the four table classes is the shared
//     dressing, and every selector in it has to carry a `.wpc-...-page` body class;
//   - `.wpcpm-usage` and `.wpcpm-admin-table` are dressed nowhere else in this theme, so every
//     selector naming either one has to carry one too.
//
// The Institution Dashboard's own mentee-table card and its narrow-screen stack are left out on
// purpose: those are one page's layout, they were never prefixed, and widening this check to
// them would be a cascade change rather than a scope restored.
$table_classes = array( 'wpcpm-student__table', 'wpcpm-mentee__table', 'wpcpm-usage', 'wpcpm-admin-table' );
$only_here     = array( 'wpcpm-usage', 'wpcpm-admin-table' );
$unscoped      = array();

foreach ( $sheets as $path ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $path ) );

	if ( ! preg_match_all( '/([^{}]+)\{/', $css, $blocks ) ) {
		continue;
	}

	foreach ( $blocks[1] as $block ) {
		$selectors = array();
		$named     = array();

		foreach ( explode( ',', $block ) as $selector ) {
			$selector = trim( preg_replace( '/\s+/', ' ', $selector ) );

			if ( '' === $selector || 0 === strpos( $selector, '@' ) ) {
				continue;
			}

			$selectors[] = $selector;

			foreach ( $table_classes as $class ) {
				if ( preg_match( '/\.' . preg_quote( $class, '/' ) . '\b/', $selector ) ) {
					$named[ $class ] = true;
				}
			}
		}

		if ( ! $named ) {
			continue;
		}

		$shared = count( $named ) > 1 || array_intersect( array_keys( $named ), $only_here );

		if ( ! $shared ) {
			continue;
		}

		foreach ( $selectors as $selector ) {
			if ( ! preg_match( '/\.wpc-[a-z0-9-]*-page\b/', $selector ) && preg_match( '/\.wpcpm-/', $selector ) ) {
				$unscoped[ basename( $path ) . '  ' . $selector ] = true;
			}
		}
	}
}

foreach ( array_keys( $unscoped ) as $where ) {
	printf( "  SCOPE  %s\n", $where );
}

printf( "%d shared table selectors without a dashboard body class.\n", count( $unscoped ) );

if ( $unscoped ) {
	exit( 1 );
}

// The plugin's classes the theme's scripts read.
//
// The header's menu script gives the focus back to the Menu button when the handbook assistant
// closes, and it knows the assistant is open by the class the plugin puts on the root element,
// `wpcpm-hb-open`. A class the plugin stops setting leaves the script waiting for a change that
// never comes, and nothing on the page says so. So every `wpcpm-` name inside a quoted string of the
// theme's scripts, read without their comments, has to be in the plugin's own source, read without
// its comments: the plugin's alone, not the corpus above, in which the theme's script would vouch
// for itself. A name right after a hyphen is part of another, as `data-wpcpm-handbook-open` is.
$plugin_corpus = '';

foreach ( wpcredits_source_files( $plugin, array( 'php', 'js', 'html', 'json' ) ) as $path ) {
	$plugin_corpus .= "\n" . wpcredits_without_comments( (string) file_get_contents( $path ), strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) );
}

$script_files = array_merge( glob( $theme . '/assets/js/*.js' ) ?: array(), glob( $theme . '/blocks/*/view.js' ) ?: array() );
$script_reads = array();
$quoted       = '~' . WPCREDITS_JS_STRING . '~s';

sort( $script_files );

foreach ( $script_files as $path ) {
	$code = wpcredits_without_comments( (string) file_get_contents( $path ), 'js' );

	preg_match_all( $quoted, $code, $strings, PREG_OFFSET_CAPTURE );

	foreach ( $strings[0] as $string ) {
		preg_match_all( '/(?<![A-Za-z0-9_-])wpcpm-[A-Za-z0-9_-]+/', $string[0], $names, PREG_OFFSET_CAPTURE );

		foreach ( $names[0] as $name ) {
			$script_reads[ $name[0] ][] = substr( $path, strlen( $theme ) + 1 ) . ':' . ( substr_count( $code, "\n", 0, $string[1] + $name[1] ) + 1 );
		}
	}
}

ksort( $script_reads );

$script_misses = 0;

foreach ( $script_reads as $name => $where ) {
	if ( ! wpcredits_printed( $plugin_corpus, $name ) ) {
		++$script_misses;
		printf( "  MISS   %-45s the plugin sets it nowhere  (%s)\n", $name, implode( ', ', $where ) );
	}
}

printf( "%d plugin %s read by the theme's scripts, %d set by nothing.\n", count( $script_reads ), 1 === count( $script_reads ) ? 'class' : 'classes', $script_misses );

// The house rule, enforced here too: plain hyphens only, in every text file of the theme. The
// hidden folders are left out as above: another line's working copy is not this theme.
$dashes = array();
$walk   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme, FilesystemIterator::SKIP_DOTS ) );
foreach ( $walk as $file ) {
	$path = $file->getPathname();
	if ( wpcredits_skipped( $theme, $path, array( '/.git/', '/.worktrees/', '/.superpowers/', '/node_modules/' ) ) || ! preg_match( '/\.(php|css|js|json|md|txt|html)$/', $path ) ) {
		continue;
	}
	$text = (string) file_get_contents( $path );
	if ( false !== strpos( $text, "\xE2\x80\x94" ) || false !== strpos( $text, "\xE2\x80\x93" ) ) {
		$dashes[] = substr( $path, strlen( $theme ) + 1 );
	}
}
if ( $dashes ) {
	echo "Em or en dashes (U+2014 / U+2013) in: ", implode( ', ', $dashes ), "\n";
	exit( 1 );
}
echo "No em or en dashes in the theme.\n";

exit( $misses || $hub_misses || $script_misses ? 1 : 0 );
