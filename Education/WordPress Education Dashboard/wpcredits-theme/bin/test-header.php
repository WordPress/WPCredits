<?php
/**
 * The site header's wrap above 640px (theme 1.24.16), read from the sheet and the part as they are
 * written.
 *
 * The bar was designed to wrap: `.wpc-header__inner { flex-wrap: wrap }`, so a group that does not fit
 * beside the brand drops to a row of its own. That rule never took effect, because the header part's
 * group declares `"flexWrap":"nowrap"` and core prints `flex-wrap: nowrap` for it at the same single
 * class, after this sheet, and the viewer chip of a signed-in Administrator (462px, unable to shrink)
 * pushed the page sideways from 641px to about 806px. The wrap is now said at two classes above 640px.
 *
 * A static check cannot see the cascade; it pins what the cascade rests on: the rule, the class the
 * part gives its group, and the three one-class rules the two-class one must not replace. The render
 * is measured in a browser, at 641, 700, 760, 806, 807, 848 and 900px, in iframes of fixed width.
 *
 * Run from the theme root:  php bin/test-header.php
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
 * Every style rule of style.css in the order the sheet has them, each with the at-rules it sits in,
 * comments left out. A rule is a prelude and a body with no braces inside; an at-rule that holds rules
 * (`@media`, `@supports`) is entered, whatever its depth, and one that holds declarations
 * (`@font-face`) is read as a rule of its own.
 *
 * @return array[] Each as `array( 'at' => at-rules joined by " > " or '', 'selector' => the selector
 *                 list, whitespace run together, 'declarations' => the declarations, one per entry,
 *                 whitespace run together )`.
 */
function sheet_rules() {
	$css   = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'style.css' ) );
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
 * The declarations of every rule whose selector list is exactly the one given, inside exactly the at-rule
 * given (`''` for none), in the order the sheet has them.
 *
 * @param string $selector The selector list, as the sheet writes it.
 * @param string $at       The at-rule, as the sheet writes it, such as `@media ( min-width: 641px )`.
 * @return array[] Each rule's declarations.
 */
function rules_of( $selector, $at = '' ) {
	$found = array();

	foreach ( sheet_rules() as $rule ) {
		if ( $rule['selector'] === $selector && $rule['at'] === $at ) {
			$found[] = $rule['declarations'];
		}
	}

	return $found;
}

/**
 * How many rules of the sheet name a selector list exactly, wherever they sit.
 *
 * @param string $selector The selector list.
 * @return int
 */
function rules_anywhere( $selector ) {
	return count( array_filter( sheet_rules(), static function ( $rule ) use ( $selector ) { return $rule['selector'] === $selector; } ) );
}

echo "=== The wrap above 640px ===\n";

$wide = '@media ( min-width: 641px )';

ck( 'above 640px the bar wraps and its rows sit 4px apart: one rule for `.wpc-header .wpc-header__inner`, two classes deep so it outranks the one-class `flex-wrap: nowrap` and block gap core prints for the part\'s group, in either print order, declaring the wrap and the row gap and nothing else (the column gap stays what it renders with)',
	rules_of( '.wpc-header .wpc-header__inner', $wide ),
	array( array( 'flex-wrap: wrap;', 'row-gap: 4px;' ) ) );

ck( 'that rule is only for the wide bar: no rule of the same two classes outside the 641px query, which would reach the phone block and outrank its one-class padding and height',
	array( rules_anywhere( '.wpc-header .wpc-header__inner' ), rules_of( '.wpc-header .wpc-header__inner' ) ),
	array( 1, array() ) );

echo "\n=== What the wrap rests on ===\n";

$part = new DOMDocument();

libxml_use_internal_errors( true );
$part->loadHTML( '<?xml encoding="UTF-8"><html><body>' . theme_file( 'parts/header.html' ) . '</body></html>' );
libxml_clear_errors();

$xpath  = new DOMXPath( $part );
$has    = static function ( $name ) { return 'contains(concat(" ", normalize-space(@class), " "), " ' . $name . ' ")'; };
$inner  = $xpath->query( '//header[' . $has( 'wpc-header' ) . ']/div[' . $has( 'wp-block-group' ) . '][' . $has( 'wpc-header__inner' ) . ']' );
$inside = $inner->length ? $xpath->query( './div[' . $has( 'wpc-brand' ) . ']', $inner->item( 0 ) )->length : 0;

ck( 'parts/header.html still gives its inner group the class `wpc-header__inner`, as the header\'s one direct child group holding the brand: the hook the wrap rule needs',
	array( $inner->length, $inside, (bool) preg_match( '/<!-- wp:group \{"className":"wpc-header__inner",/', theme_file( 'parts/header.html' ) ) ),
	array( 1, 1, true ) );

ck( 'the bar\'s one-class rules are still one class: the base rule, once, outside any query, with the wrap and the 4px row gap it was designed with, so a later edit does not turn it into a two-class rule that would outrank the phone block\'s',
	array( count( rules_of( '.wpc-header__inner' ) ), array_intersect( array( 'flex-wrap: wrap;', 'row-gap: 4px;', 'column-gap: 40px;' ), rules_of( '.wpc-header__inner' )[0] ?? array() ) ),
	array( 1, array( 'flex-wrap: wrap;', 'row-gap: 4px;', 'column-gap: 40px;' ) ) );

ck( 'the 960px block still narrows the column gap with its one-class `.wpc-header__inner` rule',
	rules_of( '.wpc-header__inner', '@media ( max-width: 960px )' ),
	array( array( 'column-gap: 16px;' ) ) );

$phone = rules_of( '.wpc-header__inner', '@media ( max-width: 640px )' );

ck( 'the 640px block still carries its one-class `.wpc-header__inner` rule, with the height and the padding it sets for the phone\'s two rows',
	array( count( $phone ), array_values( array_intersect( array( 'min-height: 0;', 'padding-bottom: 10px;', 'padding-top: 10px;' ), $phone[0] ?? array() ) ) ),
	array( 1, array( 'min-height: 0;', 'padding-bottom: 10px;', 'padding-top: 10px;' ) ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
