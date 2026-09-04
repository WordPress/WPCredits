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
 * The source files under a root, by extension.
 *
 * Skips what is not source: version control, dependencies, the development scripts (this
 * one names classes in its own comments) and the documentation.
 *
 * @param string   $root       Directory.
 * @param string[] $extensions Lower-case extensions, without the dot.
 * @return string[] Paths, sorted.
 */
function wpcredits_source_files( $root, array $extensions ) {
	$skip  = array( '/.git/', '/node_modules/', '/vendor/', '/bin/', '/docs/' );
	$files = array();

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$path = $file->getPathname();

		if ( ! $file->isFile() || ! in_array( strtolower( $file->getExtension() ), $extensions, true ) ) {
			continue;
		}

		foreach ( $skip as $part ) {
			if ( false !== strpos( $path, $part ) ) {
				continue 2;
			}
		}

		$files[] = $path;
	}

	sort( $files );

	return $files;
}

/**
 * Every `.wpcpm-*` class a stylesheet's selectors name, with the line each is on.
 *
 * Comments are blanked first, line for line, so a class mentioned in prose is not counted
 * and the line numbers still point into the file. Only the selector of each rule is read -
 * the text between the previous brace and the next `{` - and at-rules are skipped.
 *
 * @param string $css The stylesheet.
 * @return array[] Rows of `class`, `line`.
 */
function wpcredits_classes_dressed( $css ) {
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

		preg_match_all( '/\.(wpcpm-[A-Za-z0-9_-]+)/', $prelude[0], $classes, PREG_OFFSET_CAPTURE );

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

$misses = 0;
$built  = 0;

foreach ( $dressed as $class => $where ) {
	if ( wpcredits_printed( $corpus, $class ) ) {
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
	"\n%d classes dressed across %d stylesheets: %d built at run time, %d printed by nothing.\n",
	count( $dressed ),
	count( $sheets ),
	$built,
	$misses
);

// The house rule, enforced here too: plain hyphens only, in every text file of the theme.
$dashes = array();
$walk   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme, FilesystemIterator::SKIP_DOTS ) );
foreach ( $walk as $file ) {
	$path = $file->getPathname();
	if ( false !== strpos( $path, '/.git/' ) || ! preg_match( '/\.(php|css|js|json|md|txt|html)$/', $path ) ) {
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

exit( $misses ? 1 : 0 );
