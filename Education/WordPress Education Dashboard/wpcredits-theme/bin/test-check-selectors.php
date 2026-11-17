<?php
/**
 * The scroll boxes' placement in `bin/check-selectors.php` (theme 1.32.28), run against made-up plugins.
 *
 * The check holds the dashboards' wide tables to one phone rule: a table with column headers sits in a box
 * the plugin prints, and under 782px that box, not the table, scrolls sideways. Until plugin 1.123.92 both
 * dashboards with such a table drew it that way, so the check compared the Usage card's box with the
 * Administrator Dashboard's. Plugin 1.123.92 put the Administrator Dashboard on the shared interface parts,
 * which stack `.wpcpm-ui-table` row by row on a phone and print no box, and the comparison failed on every
 * theme. The check now reads each dashboard the way the plugin it is given draws it; this suite proves both
 * directions, so the fix cannot have become a blanket pass:
 *   - the stale case passes: one dashboard on the parts with no box, the other the old way with the
 *     queues' rule; and so do a plugin from before the parts and one with both dashboards on them;
 *   - a real mismatch still fails: two old-way dashboards whose boxes read differently, either way round;
 *   - so does a dashboard with no box that is not on the parts, a dashboard on the parts whose sheet does
 *     not stack the table, a stack only wider than a phone's 782px, and a box still printed by an old
 *     branch behind the switch whose rule is gone.
 *
 * The same made-up plugins prove the kept list (theme 1.32.28): a class the plugin no longer prints that `$kept`
 * lists is reported as kept with its last version, and a class neither printed nor kept is still a miss.
 *
 * Each plugin is a few files in a fresh folder under the system's temporary folder, removed afterwards. Only
 * the lines of the box comparison are read; the class lines a made-up plugin causes are not this suite's.
 *
 * Run from the theme root:  php bin/test-check-selectors.php
 *
 * @package WPCredits_Theme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$fail  = 0;
$total = 0;

/**
 * One check.
 *
 * @param string $label    What is asked.
 * @param mixed  $actual   What happened.
 * @param mixed  $expected What should have happened.
 */
function ck( $label, $actual, $expected ) {
	global $fail, $total;

	++$total;

	$ok = $actual === $expected;

	if ( ! $ok ) {
		++$fail;
	}

	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";

	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n";
		echo '       actual:   ' . var_export( $actual, true ) . "\n";
	}
}

/**
 * A made-up plugin in a fresh folder: the files given, by path below its root, and an `includes/` folder,
 * which the check asks for.
 *
 * @param array $files Path below the root => contents.
 * @return string The plugin's root.
 */
function made_up_plugin( array $files ) {
	$root = sys_get_temp_dir() . '/wpc-test-check-selectors-' . bin2hex( random_bytes( 6 ) );

	mkdir( $root . '/includes', 0700, true );

	foreach ( $files as $path => $contents ) {
		if ( ! is_dir( dirname( $root . '/' . $path ) ) ) {
			mkdir( dirname( $root . '/' . $path ), 0700, true );
		}

		file_put_contents( $root . '/' . $path, $contents );
	}

	return $root;
}

/**
 * A folder and everything in it, gone.
 *
 * @param string $root The folder.
 */
function remove_tree( $root ) {
	$walk = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );

	foreach ( $walk as $file ) {
		if ( $file->isDir() ) {
			rmdir( $file->getPathname() );
		} else {
			unlink( $file->getPathname() );
		}
	}

	rmdir( $root );
}

/**
 * The box comparison's lines the check prints against a made-up plugin, without their `PLACE` mark.
 *
 * @param array $files As `made_up_plugin()` takes them.
 * @return string[]
 */
function box_lines( array $files ) {
	$root   = made_up_plugin( $files );
	$output = array();

	exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/check-selectors.php' ) . ' ' . escapeshellarg( $root ) . ' 2>&1', $output );
	remove_tree( $root );

	$lines = array();

	foreach ( $output as $line ) {
		if ( preg_match( '/^  PLACE  (.*(?:under 782px read|is printed by nothing and the dashboard|does not stack \.wpcpm-ui-table).*)$/', $line, $found ) ) {
			$lines[] = $found[1];
		}
	}

	return $lines;
}

$queues_rule  = "@media ( max-width: 782px ) {\n\t%s {\n\t\tdisplay: block;\n\t\toverflow-x: auto;\n\t\tposition: relative;\n\t}\n}\n";
$admin_rule   = sprintf( $queues_rule, '.wpcpm-administrator__scroll' );
$usage_rule   = sprintf( $queues_rule, '.wpcpm-usage__scroll' );
$admin_box    = "<?php\nprintf( '<div class=\"wpcpm-administrator__scroll\" tabindex=\"0\" role=\"region\">' );\n";
$usage_box    = "<?php\nprintf( '<div class=\"wpcpm-usage__scroll\" tabindex=\"0\" role=\"region\">' );\n";
$parts_stack  = "@media (max-width: 600px) {\n\t.wpcpm-ui-table,\n\t.wpcpm-ui-table tbody {\n\t\tdisplay: block;\n\t}\n\n\t.wpcpm-ui-table tr {\n\t\tdisplay: grid;\n\t}\n}\n";
$admin_live   = "<?php\nclass WPCPM_UI {\n\tconst LIVE = array( 'administrator' );\n}\n";
$both_live    = "<?php\nclass WPCPM_UI {\n\tconst LIVE = array( 'administrator', 'sponsor' );\n}\n";
$none_live    = "<?php\nclass WPCPM_UI {\n\tconst LIVE = array();\n}\n";
$queues_value = 'display: block; overflow-x: auto; position: relative';

// The stale case: plugin 1.123.92 and later.
ck( 'the Administrator Dashboard on the parts with no box, the Usage card the old way with the queues\' rule: no line',
	box_lines(
		array(
			'includes/class-wpcpm-ui.php'            => $admin_live,
			'includes/modules/class-sponsor-usage.php' => $usage_box,
			'assets/css/administrator.css'           => "/* No box rule. */\n",
			'assets/css/sponsor.css'                 => $usage_rule,
			'assets/css/parts.css'                   => $parts_stack,
		)
	),
	array() );

ck( 'a plugin from before the parts, both dashboards the old way with the same rule: no line',
	box_lines(
		array(
			'includes/class-admin.php'               => $admin_box,
			'includes/modules/class-sponsor-usage.php' => $usage_box,
			'assets/css/administrator.css'           => $admin_rule,
			'assets/css/sponsor.css'                 => $usage_rule,
		)
	),
	array() );

ck( 'both dashboards on the parts, no box printed, the parts stack the table: no line',
	box_lines(
		array(
			'includes/class-wpcpm-ui.php'  => $both_live,
			'assets/css/administrator.css' => "/* No box rule. */\n",
			'assets/css/sponsor.css'       => "/* No box rule. */\n",
			'assets/css/parts.css'         => $parts_stack,
		)
	),
	array() );

// A real mismatch between two old-way dashboards still fails, either way round.
ck( 'two old-way dashboards, the Usage card\'s box without overflow-x: its line',
	box_lines(
		array(
			'includes/class-wpcpm-ui.php'            => $none_live,
			'includes/class-admin.php'               => $admin_box,
			'includes/modules/class-sponsor-usage.php' => $usage_box,
			'assets/css/administrator.css'           => $admin_rule,
			'assets/css/sponsor.css'                 => "@media ( max-width: 782px ) {\n\t.wpcpm-usage__scroll {\n\t\tdisplay: block;\n\t\tposition: relative;\n\t}\n}\n",
		)
	),
	array( 'the Usage card\'s box under 782px read display: block; position: relative in the plugin\'s sponsor.css, not the old-way boxes\' ' . $queues_value ) );

ck( 'two old-way dashboards, the Administrator Dashboard\'s boxes without a rule: its line',
	box_lines(
		array(
			'includes/class-admin.php'               => $admin_box,
			'includes/modules/class-sponsor-usage.php' => $usage_box,
			'assets/css/administrator.css'           => "/* The rule went. */\n",
			'assets/css/sponsor.css'                 => $usage_rule,
		)
	),
	array( 'the Administrator Dashboard\'s boxes under 782px read unset in the plugin\'s administrator.css, not the old-way boxes\' ' . $queues_value ) );

// Neither a box nor the parts, or parts that do not stack.
ck( 'the Administrator Dashboard prints no box and is not on the parts: its line',
	box_lines(
		array(
			'includes/class-wpcpm-ui.php'            => $none_live,
			'includes/modules/class-sponsor-usage.php' => $usage_box,
			'assets/css/sponsor.css'                 => $usage_rule,
			'assets/css/parts.css'                   => $parts_stack,
		)
	),
	array( 'the administrator dashboard\'s box .wpcpm-administrator__scroll is printed by nothing and the dashboard is not on the shared parts, so its table has nothing that keeps a phone\'s page from widening' ) );

$no_stack = 'the administrator dashboard draws its tables through the shared parts, and the plugin\'s parts.css does not stack .wpcpm-ui-table on a phone (display block on the table and grid on .wpcpm-ui-table tr, in a max-width query of 782px or less)';

ck( 'the Administrator Dashboard on the parts, the parts\' sheet without a stack: its line',
	box_lines(
		array(
			'includes/class-wpcpm-ui.php'            => $admin_live,
			'includes/modules/class-sponsor-usage.php' => $usage_box,
			'assets/css/sponsor.css'                 => $usage_rule,
			'assets/css/parts.css'                   => ".wpcpm-ui-table {\n\twidth: 100%;\n}\n",
		)
	),
	array( $no_stack ) );

ck( 'the Administrator Dashboard on the parts, the stack only from 900px down, wider than a phone\'s 782px: its line',
	box_lines(
		array(
			'includes/class-wpcpm-ui.php'            => $admin_live,
			'includes/modules/class-sponsor-usage.php' => $usage_box,
			'assets/css/sponsor.css'                 => $usage_rule,
			'assets/css/parts.css'                   => str_replace( '600px', '900px', $parts_stack ),
		)
	),
	array( $no_stack ) );

ck( 'the Administrator Dashboard on the parts, an old branch behind the switch still printing its box, the box\'s rule gone: its line',
	box_lines(
		array(
			'includes/class-wpcpm-ui.php'            => $admin_live,
			'includes/class-admin.php'               => $admin_box,
			'includes/modules/class-sponsor-usage.php' => $usage_box,
			'assets/css/sponsor.css'                 => $usage_rule,
			'assets/css/parts.css'                   => $parts_stack,
		)
	),
	array( 'the Administrator Dashboard\'s boxes under 782px read unset in the plugin\'s administrator.css, not the old-way boxes\' ' . $queues_value ) );

ck( 'a box named only in a comment of the plugin is not printed: the old-way rule is not asked of it',
	box_lines(
		array(
			'includes/class-wpcpm-ui.php'            => $admin_live,
			'includes/class-admin.php'               => "<?php\n// The old .wpcpm-administrator__scroll box went in 1.123.92.\n",
			'includes/modules/class-sponsor-usage.php' => $usage_box,
			'assets/css/sponsor.css'                 => $usage_rule,
			'assets/css/parts.css'                   => $parts_stack,
		)
	),
	array() );

// The classes kept for an older plugin (theme 1.32.28): a class the theme dresses, that the plugin no longer prints
// and that `$kept` lists, is reported as kept with its last version and is no miss; one that is neither printed nor
// kept is still a miss, and the check still fails on it.
$root   = made_up_plugin(
	array(
		'includes/class-admin.php'               => $admin_box . "printf( '<p class=\"wpcpm-dashboard__switcher-none\">' );\n",
		'includes/modules/class-sponsor-usage.php' => $usage_box,
		'assets/css/administrator.css'           => $admin_rule,
		'assets/css/sponsor.css'                 => $usage_rule,
	)
);
$output = array();
$code   = 0;

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/check-selectors.php' ) . ' ' . escapeshellarg( $root ) . ' 2>&1', $output, $code );
remove_tree( $root );

$class_line = static function ( $class ) use ( $output ) {
	foreach ( $output as $line ) {
		if ( preg_match( '/^  (kept|MISS|built)\s+\.' . preg_quote( $class, '/' ) . '\s/', $line, $found ) ) {
			return $found[1];
		}
	}

	return 'printed';
};

ck( 'a kept class nothing prints, .wpcpm-dashboard__detail, is reported as kept, not missed',
	$class_line( 'wpcpm-dashboard__detail' ),
	'kept' );

ck( 'its line names the last plugin that printed it, 1.123.57',
	(bool) preg_grep( '/^  kept   \.wpcpm-dashboard__detail\s+last printed by plugin 1\.123\.57 /', $output ),
	true );

ck( 'a kept class the plugin still prints, .wpcpm-dashboard__switcher-none, is neither kept nor missed',
	$class_line( 'wpcpm-dashboard__switcher-none' ),
	'printed' );

ck( 'a class neither printed nor kept, .wpcpm-usage, is still a miss',
	$class_line( 'wpcpm-usage' ),
	'MISS' );

ck( 'the count says so: some classes printed by nothing',
	(bool) preg_grep( '/ [1-9][0-9]* printed by nothing\.$/', $output ),
	true );

ck( 'and the check still fails on it',
	$code,
	1 );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
