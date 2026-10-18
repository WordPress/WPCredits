<?php
/**
 * The frame of the Administrator Dashboard's tables on a phone (theme 1.24.27), read from the sheet
 * as it is written.
 *
 * Plugin 1.122.35 wraps each table with column headers in `div.wpcpm-administrator__scroll`, a tab
 * stop and a named region, and from 782px down that box, not the table, scrolls sideways. The theme
 * draws the table's 1px frame on the table (the shared dashboard-table rule in `dashboard.css`), so
 * on a phone the frame scrolled with the table and its side edges slid out of view. From 782px down
 * the frame is on the box and the table inside has none, so the frame stays around the visible window.
 *
 * A static check cannot see the render; it pins what the render rests on:
 *   - above 782px nothing changed: the shared rule still draws the frame on the table, for all four
 *     dashboard tables, and no rule outside the phone query names the box;
 *   - the phone query is the plugin's own, `max-width: 782px`, so the box and its frame switch at the
 *     same width;
 *   - in it, the box takes the frame and the table inside takes none, each rule saying that and
 *     nothing else (a box that declared `outline` or `overflow` could clip or move its focus ring),
 *     and the table's rule is heavier than the shared one, whatever the order of the sheet;
 *   - every selector that names the box carries the dashboards' body class, as the shared table rule
 *     does (`bin/check-selectors.php` refuses a shared table rule without it);
 *   - the box's focus ring is the theme's one ring, 2px at an offset of 2px, which sits outside the
 *     box's border, so the border on the box does not hide it.
 * The render itself is measured in a browser at 320, 375, 390 and 1280px.
 *
 * Run from the theme root:  php bin/test-dashboard-table.php
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
 * A file of the theme.
 *
 * @param string $path Below the theme root.
 * @return string
 */
function theme_file( $path ) {
	return (string) file_get_contents( dirname( __DIR__ ) . '/' . $path );
}

/**
 * Every style rule of a stylesheet of the theme in the order the sheet has them, each with the
 * at-rules it sits in, comments left out. A rule is a prelude and a body with no braces inside; an
 * at-rule that holds rules (`@media`, `@supports`) is entered, whatever its depth.
 *
 * @param string $path Below the theme root.
 * @return array[] Each as `array( 'at' => at-rules joined by " > " or '', 'selector' => the selector
 *                 list, whitespace run together, 'declarations' => the declarations, one per entry,
 *                 whitespace run together )`.
 */
function sheet_rules( $path ) {
	$css   = preg_replace( '#/\*.*?\*/#s', '', theme_file( $path ) );
	$rules = array();
	$stack = array();

	preg_match_all( '/\{|\}|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|[^{}"\']+/s', $css, $tokens );

	$prelude = '';
	$body    = null;
	$depth   = 0;

	foreach ( $tokens[0] as $token ) {
		if ( '{' === $token ) {
			if ( null !== $body ) {
				// A brace inside a rule's body: not a sheet this reader can read.
				$body .= $token;
				++$depth;
				continue;
			}

			$name = trim( preg_replace( '/\s+/', ' ', $prelude ) );

			if ( preg_match( '/^@(?:media|supports|container|layer)\b/', $name ) ) {
				$stack[] = $name;
				$prelude = '';
			} else {
				$body = '';
			}

			continue;
		}

		if ( '}' === $token ) {
			if ( null !== $body ) {
				if ( $depth > 0 ) {
					--$depth;
					$body .= $token;
					continue;
				}

				$declarations = array();

				foreach ( explode( ';', $body ) as $declaration ) {
					$declaration = trim( preg_replace( '/\s+/', ' ', $declaration ) );

					if ( '' !== $declaration ) {
						$declarations[] = $declaration . ';';
					}
				}

				$rules[] = array(
					'at'           => implode( ' > ', $stack ),
					'selector'     => trim( preg_replace( '/\s+/', ' ', $prelude ) ),
					'declarations' => $declarations,
				);

				$body    = null;
				$prelude = '';
			} else {
				array_pop( $stack );
				$prelude = '';
			}

			continue;
		}

		if ( null !== $body ) {
			$body .= $token;
		} else {
			$prelude .= $token;
		}
	}

	return $rules;
}

/**
 * The declarations of every rule of dashboard.css whose selector list is exactly the one given,
 * inside exactly the at-rule given (`''` for none), in the order the sheet has them.
 *
 * @param string $selector The selector list, as the sheet writes it.
 * @param string $at       The at-rule, as the sheet writes it.
 * @return array[] Each rule's declarations.
 */
function dashboard_rules_of( $selector, $at = '' ) {
	$found = array();

	foreach ( sheet_rules( 'assets/css/dashboard.css' ) as $rule ) {
		if ( $rule['selector'] === $selector && $rule['at'] === $at ) {
			$found[] = $rule['declarations'];
		}
	}

	return $found;
}

/**
 * Every rule of dashboard.css with a selector, in a list, that names the scroll box.
 *
 * @return array[] The rules, as `sheet_rules()` gives them, with the one selector that names the box.
 */
function box_rules() {
	$found = array();

	foreach ( sheet_rules( 'assets/css/dashboard.css' ) as $rule ) {
		foreach ( explode( ',', $rule['selector'] ) as $selector ) {
			if ( preg_match( '/\.wpcpm-administrator__scroll\b/', $selector ) ) {
				$found[] = $rule + array( 'one' => trim( $selector ) );
			}
		}
	}

	return $found;
}

/**
 * How many classes a compound selector names, which is its weight when it names no id and no element.
 *
 * @param string $selector One selector, with no comma.
 * @return int
 */
function classes_in( $selector ) {
	return preg_match_all( '/\.[A-Za-z_][A-Za-z0-9_-]*/', $selector );
}

$phone = '@media ( max-width: 782px )';
$frame = '1px solid var( --wpc-line-card )';
$box   = '.wpc-dashboard-page .wpcpm-administrator__scroll';
$inner = '.wpc-dashboard-page .wpcpm-administrator__scroll .wpcpm-admin-table';

echo "=== Above 782px nothing changes ===\n";

$shared = array();

foreach ( sheet_rules( 'assets/css/dashboard.css' ) as $rule ) {
	$members = array_map( 'trim', explode( ',', $rule['selector'] ) );

	if ( '' === $rule['at'] && in_array( '.wpc-dashboard-page .wpcpm-admin-table', $members, true ) ) {
		$shared[] = $rule;
	}
}

ck( 'one shared rule, outside every at-rule, still draws the frame (border, surface, collapsed borders, 14px face, full width) on the four dashboard tables: the student\'s, the Institution Dashboard\'s, the Usage card\'s and the Administrator Dashboard\'s',
	array(
		count( $shared ),
		$shared ? $shared[0]['selector'] : '',
		$shared ? $shared[0]['declarations'] : array(),
	),
	array(
		1,
		'.wpc-dashboard-page .wpcpm-student__table, .wpc-dashboard-page .wpcpm-mentee__table, .wpc-dashboard-page .wpcpm-usage, .wpc-dashboard-page .wpcpm-admin-table',
		array(
			'background: var( --wpc-surface );',
			'border: ' . $frame . ';',
			'border-collapse: collapse;',
			'font: 14px/20px var( --wpc-font );',
			'width: 100%;',
		),
	) );

$named = box_rules();

ck( 'the box is named by two rules of dashboard.css and both sit in the phone query: nothing above 782px reaches it',
	array( count( $named ), array_values( array_unique( array_column( $named, 'at' ) ) ) ),
	array( 2, array( $phone ) ) );

echo "\n=== From 782px down the frame is on the box ===\n";

ck( 'the phone query is the plugin\'s own, `max-width: 782px` (administrator.css lets the box scroll in it), written once in this form for the box\'s rule and once for the table\'s',
	array( count( dashboard_rules_of( $box, $phone ) ), count( dashboard_rules_of( $inner, $phone ) ) ),
	array( 1, 1 ) );

ck( 'the box takes the frame: the table\'s own 1px line, said as the shared rule says it, and nothing else (no outline, no overflow, no offset: the box\'s ring and its scrolling are not this rule\'s to touch)',
	dashboard_rules_of( $box, $phone ),
	array( array( 'border: ' . $frame . ';' ) ) );

ck( 'the table inside the box has none, and nothing else is said of it: its surface, its collapsed borders, its face and its width stay the shared rule\'s',
	dashboard_rules_of( $inner, $phone ),
	array( array( 'border: 0;' ) ) );

ck( 'the table\'s rule is heavier than the shared one (three classes against two), so it wins wherever the sheet puts it, and is under the dashboards\' body class like the rest of the sheet',
	array(
		classes_in( $inner ) > classes_in( '.wpc-dashboard-page .wpcpm-admin-table' ),
		classes_in( $box ) > classes_in( '.wpcpm-administrator__scroll' ),
		array_values( array_unique( array_map( static function ( $rule ) { return 0 === strpos( $rule['one'], '.wpc-dashboard-page ' ); }, $named ) ) ),
	),
	array( true, true, array( true ) ) );

ck( 'the box is not drawn a second time by the Administrator Dashboard\'s own block: no `.wpc-administrator-page` rule names it',
	count( array_filter( $named, static function ( $rule ) { return false !== strpos( $rule['one'], 'wpc-administrator-page' ); } ) ),
	0 );

echo "\n=== The box's focus ring ===\n";

$ring = array();

foreach ( sheet_rules( 'style.css' ) as $rule ) {
	if ( '' === $rule['at'] && ':where( a, button, input, select, textarea, summary, [tabindex] ):focus-visible' === $rule['selector'] ) {
		$ring[] = $rule['declarations'];
	}
}

ck( 'the box is a `[tabindex]`, so it takes the theme\'s one ring: 2px solid brand at an offset of 2px, which sits outside the border-box, so the frame on the box leaves it whole',
	$ring,
	array( array( 'outline: 2px solid var( --wpc-brand );', 'outline-offset: 2px;' ) ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
