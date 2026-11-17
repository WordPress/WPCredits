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
 * The Sponsor Dashboard's Usage table is dressed the same way on this line (theme 1.31.8): plugin
 * 1.123.17 wraps it in `div.wpcpm-usage__scroll`, a tab stop and a named region that scrolls from
 * 782px down, so the frame is on that box and the table inside has none, in the same query, and the
 * card's CSV form, which now follows the box and not the table, keeps its 16px above it.
 * The render itself is measured in a browser at 320, 375, 390 and 1280px.
 *
 * Since plugin 1.123.92 the Administrator Dashboard draws its queues with the shared interface parts, which
 * stack a table on a phone and print neither the box nor the queues' table class, so the theme's rules for
 * them went with the Institution Dashboard's frame, and the file pins that they are gone and that the shared
 * rule and the phone query hold the Usage card's alone.
 *
 * The same file pins the Sponsor Dashboard's At a glance strip (theme 1.32.8): the tier tile's figure
 * is a word, and a word at the numbers' 28px ran up to 14.5px past its tile's content where the tile is
 * narrowest, so the tile is an inline-size container and the word takes its size from the tile's width.
 * Since theme 1.32.27 (plugin 1.123.78) the page draws no strip, and the file pins that those rules are gone.
 * The Administrator Dashboard's strip, which shared the tile's and the number's rules, went with plugin
 * 1.123.92's parts, and so did those two rules.
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
 * Every rule of dashboard.css with a selector, in a list, that names a scroll box.
 *
 * @param string $class The box's class, without the dot.
 * @return array[] The rules, as `sheet_rules()` gives them, with the one selector that names the box.
 */
function box_rules( $class ) {
	$found = array();

	foreach ( sheet_rules( 'assets/css/dashboard.css' ) as $rule ) {
		foreach ( explode( ',', $rule['selector'] ) as $selector ) {
			if ( preg_match( '/\.' . preg_quote( $class, '/' ) . '\b/', $selector ) ) {
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
$ubox  = '.wpc-dashboard-page .wpcpm-usage__scroll';
$uin   = '.wpc-dashboard-page .wpcpm-usage__scroll .wpcpm-usage';
$csv   = '.wpc-sponsor-page .wpcpm-usage__scroll + .wpcpm-inline-form';

echo "=== Above 782px nothing changes ===\n";

$shared = array();

foreach ( sheet_rules( 'assets/css/dashboard.css' ) as $rule ) {
	$members = array_map( 'trim', explode( ',', $rule['selector'] ) );

	if ( '' === $rule['at'] && in_array( '.wpc-dashboard-page .wpcpm-usage', $members, true ) ) {
		$shared[] = $rule;
	}
}

ck( 'one shared rule, outside every at-rule, still draws the frame (border, surface, collapsed borders, 14px face, full width) on the three dashboard tables left to it: the student\'s, the Institution Dashboard\'s and the Usage card\'s, the Administrator Dashboard\'s queues being the parts\' tables now',
	array(
		count( $shared ),
		$shared ? $shared[0]['selector'] : '',
		$shared ? $shared[0]['declarations'] : array(),
	),
	array(
		1,
		'.wpc-dashboard-page .wpcpm-student__table, .wpc-dashboard-page .wpcpm-mentee__table, .wpc-dashboard-page .wpcpm-usage',
		array(
			'background: var( --wpc-surface );',
			'border: ' . $frame . ';',
			'border-collapse: collapse;',
			'font: 14px/20px var( --wpc-font );',
			'width: 100%;',
		),
	) );

$unamed = array_values( array_filter( box_rules( 'wpcpm-usage__scroll' ), static function ( $rule ) use ( $csv ) { return $csv !== $rule['one']; } ) );

ck( 'no rule of dashboard.css names the Administrator Dashboard\'s old box or its queues\' old table any more: plugin 1.123.92 draws the queues with the shared parts, which print neither',
	array( count( box_rules( 'wpcpm-administrator__scroll' ) ), count( box_rules( 'wpcpm-admin-table' ) ) ),
	array( 0, 0 ) );

ck( 'the Usage card\'s box is named by two rules for its frame, both in the phone query, and by one more, the CSV form\'s top margin; nothing else above 782px reaches the box or the table in it',
	array( count( $unamed ), array_values( array_unique( array_column( $unamed, 'at' ) ) ), count( box_rules( 'wpcpm-usage__scroll' ) ) ),
	array( 2, array( $phone ), 3 ) );

echo "\n=== The Sponsor Dashboard's Usage card, from 782px down ===\n";

ck( 'the phone query is the plugin\'s own (sponsor.css lets the Usage card\'s box scroll in `max-width: 782px`), and each of the box\'s and the table\'s rules is written once in it',
	array( count( dashboard_rules_of( $ubox, $phone ) ), count( dashboard_rules_of( $uin, $phone ) ) ),
	array( 1, 1 ) );

ck( 'the box takes the frame, said as the shared rule says it, and nothing else (no outline, no overflow, no offset: the box\'s ring and its scrolling are the plugin\'s)',
	dashboard_rules_of( $ubox, $phone ),
	array( array( 'border: ' . $frame . ';' ) ) );

ck( 'the Usage table inside the box has none, and nothing else is said of it: its surface, its collapsed borders, its face and its width stay the shared rule\'s',
	dashboard_rules_of( $uin, $phone ),
	array( array( 'border: 0;' ) ) );

ck( 'the table\'s rule is heavier than the shared one (three classes against two), so it wins wherever the sheet puts it, and every rule naming the box is under the dashboards\' body class or the sponsor page\'s, as the rest of the sheet is',
	array(
		classes_in( $uin ) > classes_in( '.wpc-dashboard-page .wpcpm-usage' ),
		classes_in( $ubox ) > classes_in( '.wpcpm-usage__scroll' ),
		array_values( array_unique( array_map( static function ( $rule ) { return 1 === preg_match( '/^\.wpc-(?:dashboard|sponsor)-page /', $rule['one'] ); }, box_rules( 'wpcpm-usage__scroll' ) ) ) ),
	),
	array( true, true, array( true ) ) );

// The Usage card's two rules are one block of the sheet, next to each other, in the one query the Administrator
// Dashboard's two shared until they went.
$sheet_text = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/dashboard.css' ) );
$in_one     = 0;

if ( preg_match_all( '/@media \( max-width: 782px \) \{((?:[^{}]|\{[^{}]*\})*)\}/', $sheet_text, $queries ) ) {
	foreach ( $queries[1] as $inside ) {
		if ( false !== strpos( $inside, $ubox . ' {' ) && false !== strpos( $inside, $uin . ' {' ) ) {
			++$in_one;
		}
	}
}

ck( 'the Usage card\'s two rules sit in one `@media ( max-width: 782px )` block, next to each other',
	$in_one,
	1 );

ck( 'the card\'s CSV form is now the box\'s next sibling and not the table\'s, so its 16px above is said of the box (`.wpcpm-usage__scroll + .wpcpm-inline-form`), once, under the sponsor page\'s body class, and no rule still looks for the form after the table',
	array(
		dashboard_rules_of( $csv ),
		count( dashboard_rules_of( '.wpc-sponsor-page .wpcpm-usage + .wpcpm-inline-form' ) ),
	),
	array( array( array( 'margin-top: 16px;' ) ), 0 ) );

echo "\n=== The box's focus ring ===\n";

$ring = array();

foreach ( sheet_rules( 'style.css' ) as $rule ) {
	if ( '' === $rule['at'] && ':where( a, button, input, select, textarea, summary, [tabindex] ):focus-visible' === $rule['selector'] ) {
		$ring[] = $rule['declarations'];
	}
}

ck( 'the Usage card\'s box is a `[tabindex]`, so it takes the theme\'s one ring: 2px solid brand at an offset of 2px, which sits outside the border-box, so the frame on the box leaves it whole',
	$ring,
	array( array( 'outline: 2px solid var( --wpc-brand );', 'outline-offset: 2px;' ) ) );

// The label-and-value tables (an institution's student rows, the student card, the Mentor Report
// Card) print each row's label in its own first cell, so on a phone nothing is drawn before a value:
// a rule that printed the cell's data-label there showed "Value: " before every value (a review of
// 22 October 2026). No rule of the sheet, in any query, draws before a value cell of these tables.
$before = array();
foreach ( sheet_rules( 'assets/css/dashboard.css' ) as $rule ) {
	if ( false !== strpos( $rule['selector'], 'wpcpm-mentee__table' ) && false !== strpos( $rule['selector'], 'td::before' ) ) {
		$before[] = $rule['selector'];
	}
}
ck( 'no rule draws a label before a value of the label-and-value tables', $before, array() );

echo "\n=== The Sponsor Dashboard's strip: a tier's name fits its tile (theme 1.32.8) ===\n";

$sheet    = sheet_rules( 'assets/css/dashboard.css' );
$position = static function ( $selector, $declaration = null ) use ( $sheet ) {
	foreach ( $sheet as $at => $rule ) {
		if ( '' === $rule['at'] && $rule['selector'] === $selector && ( null === $declaration || in_array( $declaration, $rule['declarations'], true ) ) ) {
			return $at;
		}
	}

	return -1;
};

$figure = '.wpc-administrator-page .wpcpm-attention__n, .wpc-sponsor-page .wpcpm-attention__n';
$tile   = '.wpc-sponsor-page .wpcpm-attention__tile';
$word   = '.wpc-sponsor-page .wpcpm-attention__n--word';

$shared_tile = '.wpc-administrator-page .wpcpm-attention__tile, .wpc-sponsor-page .wpcpm-attention__tile';

// The Sponsor Dashboard draws no At a glance strip since WPCredits Program Manager 1.123.78, so the rules that
// sized a tier's name to its tile went with it (theme 1.32.27): no tile is a container and no rule sizes a word.
// The Administrator Dashboard's strip went with plugin 1.123.92's parts, so the number's and the tile's rules
// both strips shared went too.
ck( 'no rule draws the strips\' numbers or tiles any more, none makes the Sponsor Dashboard\'s tile a container and none sizes a tier\'s word: neither page draws a strip',
	array( dashboard_rules_of( $figure ), dashboard_rules_of( $shared_tile ), dashboard_rules_of( $tile ), dashboard_rules_of( $word ) ),
	array( array(), array(), array(), array() ) );

$administrator = array();

foreach ( $sheet as $rule ) {
	if ( false !== strpos( $rule['selector'], '.wpc-administrator-page' ) && ( false !== strpos( $rule['selector'], '--word' ) || preg_grep( '/^container-type:/', $rule['declarations'] ) ) ) {
		$administrator[] = $rule['selector'];
	}
}

ck( 'no rule under the Administrator Dashboard\'s body class makes a tile a container or sizes a word',
	$administrator,
	array() );

echo "\n=== The parts' choice is dressed once ===\n";

// The shared parts draw a choice as `label.wpcpm-ui-choice` holding its box, a 24px target with the box beside the
// first line of words (the parts' design 2.4). The sheet's rules for a label holding a checkbox or a radio, and for
// the box, weigh more than the part's own and centered the box and padded the label 3px, so they leave the part out;
// the report's and the rating's exclusions keep their bytes.
$choice_rules = array();

foreach ( $sheet as $rule ) {
	if ( '' === $rule['at'] && ( false !== strpos( $rule['selector'], 'label:has( > input[type="checkbox"] )' ) || 0 === strpos( $rule['selector'], '.wpc-dashboard-page input[type="checkbox"]:not(' ) ) ) {
		$choice_rules[] = array( $rule['selector'], $rule['declarations'] );
	}
}

ck( 'the label rule and the box rule leave the parts\' choice out, keep the report\'s and the rating\'s exclusions as they were, and give every other label a 24px flex target and its box 16px with no margin, as before',
	$choice_rules,
	array(
		array(
			'.wpc-dashboard-page label:has( > input[type="checkbox"] ):not( .wpcpm-report__check ):not( .wpcpm-rating__step ):not( .wpcpm-ui-choice ), .wpc-dashboard-page label:has( > input[type="radio"] ):not( .wpcpm-report__check ):not( .wpcpm-rating__step ):not( .wpcpm-ui-choice )',
			array( 'align-items: center;', 'display: flex;', 'gap: 8px;', 'min-height: 24px;', 'padding: 3px 0;' ),
		),
		array(
			'.wpc-dashboard-page input[type="checkbox"]:not( .wpcpm-rating__step input ):not( .wpcpm-report__check input ):not( .wpcpm-ui-choice input ), .wpc-dashboard-page input[type="radio"]:not( .wpcpm-rating__step input ):not( .wpcpm-report__check input ):not( .wpcpm-ui-choice input )',
			array( 'height: 16px;', 'margin: 0;', 'width: 16px;' ),
		),
	) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
