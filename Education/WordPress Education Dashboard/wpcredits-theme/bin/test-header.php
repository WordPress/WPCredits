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
 * The markup the menu needs (theme 1.24.20): the head mark, the logo link's name, the lone navigation
 * item's marks, and the Menu button, the panel's ID and the class a signed-in reader's header gets,
 * run on the stand-ins of bin/stubs/hub-wp.php over the markup of bin/stubs/header-markup.php. Each
 * class the entry count names is counted on its own (theme 1.24.21), the three filters are hooked at
 * their priority with the arguments they take, the logo link's name is plain text, and the Menu
 * label has its translators note, in the source and in the template.
 *
 * The menu layout (theme 1.24.20), read from the sheet as it is written: each rule the one-row bar, the
 * panel, the account picture, the rows and the signed-out phone row rest on is in the sheet once, in its
 * at-rule, with the declarations the cascade and the layout depend on; every rule of it is under
 * `.wpc-js .wpc-header--menu` and inside the screen query below 1200px; the submenu rules outrank every
 * rule outside the menu layout that reveals a submenu, and core's reveal on focus, each selector weighed
 * on its own (theme 1.24.21); nothing but the open panel and the picture is made visible; and the layout
 * comes after the header rules it restates, so a tie of specificity goes its way. The signed-out phone
 * row is judged inside its own block of the sheet.
 *
 * The menu script (theme 1.24.20): the callbacks hooked on `wp_enqueue_scripts` enqueue it for a
 * signed-in reader alone, deferred, and the handbook launcher's comment names the plugin's class it
 * reads. What the script does on the page is bin/test-header-js.php's to run.
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

require_once __DIR__ . '/stubs/hub-wp.php';
require_once __DIR__ . '/stubs/header-markup.php';
require_once WPCREDITS_THEME_DIR . 'inc/icons.php';
require_once WPCREDITS_THEME_DIR . 'inc/header.php';

/**
 * Every style rule of style.css in the order the sheet has them, each with the at-rules it sits in,
 * comments left out. A rule is a prelude and a body with no braces inside; an at-rule that holds rules
 * (`@media`, `@supports`) is entered, whatever its depth, and one that holds declarations
 * (`@font-face`) is read as a rule of its own.
 *
 * @return array[] Each as `array( 'at' => at-rules joined by " > " or '', 'selector' => the selector
 *                 list, whitespace run together, 'declarations' => the declarations, one per entry,
 *                 whitespace run together, 'block' => the number of the innermost at-rule block the
 *                 rule sits in, counted from 1 in the order the sheet opens them, or 0 outside any )`.
 */
function sheet_rules() {
	$css    = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'style.css' ) );
	$rules  = array();
	$stack  = array();
	$blocks = array();
	$opened = 0;

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
				$stack[]  = $name;
				$blocks[] = ++$opened;
				$prelude  = '';
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
					'block'        => $blocks ? end( $blocks ) : 0,
				);

				$body    = null;
				$prelude = '';
			} else {
				array_pop( $stack );
				array_pop( $blocks );
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

$site_title = json_decode( theme_file( 'theme.json' ), true )['styles']['blocks']['core/site-title']['typography'] ?? array();
$name_sized = array();

foreach ( sheet_rules() as $rule ) {
	if ( '.wpc-brand__name' === $rule['selector'] && preg_grep( '/^(font-size|line-height):/', $rule['declarations'] ) ) {
		$name_sized[] = $rule['at'];
	}
}

ck( 'the site\'s name is 30px on a 1.2 line, twice the 15px it showed until 1.24.25: theme.json\'s Site Title style says so, which WordPress prints after the sheet at one class; so no one-class rule of the sheet sizes the name, where it could never show, and the phone\'s size, which follows the window so the name stays on one line (theme 1.32.6), is said at two classes',
	array( $site_title, $name_sized, rules_of( '.wpc-header .wpc-brand__name', '@media ( max-width: 640px )' ) ),
	array( array( 'fontSize' => '30px', 'fontWeight' => '600', 'lineHeight' => '1.2' ), array(), array( array( 'font-size: clamp( 16px, calc( 11.7vw - 22px ), 30px );' ) ) ) );

$justified = static function ( $lines ) {
	return array_values( array_filter( $lines, static function ( $line ) { return 0 === strpos( $line, 'justify-content:' ); } ) );
};

ck( 'the group of the navigation, the help button and the viewer chip ends at the right edge of the content on the wide bar: parts/header.html says "justifyContent":"right" for it, the justification core prints at one class after the sheet, agreeing with the sheet\'s own flex-end (while the part said "center", until 1.24.23, the group sat centered between the brand and the edge); the phone\'s second row still starts under the brand, said at two classes so core\'s rule does not move it, and the phone\'s one-class rule no longer says it',
	array(
		preg_match( '/<!-- wp:group \{"className":"wpc-nav__center","layout":\{[^}]*"justifyContent":"([a-z]+)"/', theme_file( 'parts/header.html' ), $group_layout ) ? $group_layout[1] : '',
		$justified( rules_of( '.wpc-nav__center' )[0] ?? array() ),
		rules_of( '.wpc-header .wpc-nav__center', '@media ( max-width: 640px )' ),
		$justified( rules_of( '.wpc-nav__center', '@media ( max-width: 640px )' )[0] ?? array() ),
	),
	array( 'right', array( 'justify-content: flex-end;' ), array( array( 'justify-content: flex-start;' ) ), array() ) );

ck( 'the bar\'s one-class rules are still one class: the base rule, once, outside any query, with the wrap and the 4px row gap it was designed with, so a later edit does not turn it into a two-class rule that would outrank the phone block\'s',
	array( count( rules_of( '.wpc-header__inner' ) ), array_intersect( array( 'flex-wrap: wrap;', 'row-gap: 4px;', 'column-gap: var( --wpc-chrome-space );' ), rules_of( '.wpc-header__inner' )[0] ?? array() ) ),
	array( 1, array( 'flex-wrap: wrap;', 'row-gap: 4px;', 'column-gap: var( --wpc-chrome-space );' ) ) );

ck( 'the 960px block still narrows the column gap with its one-class `.wpc-header__inner` rule',
	rules_of( '.wpc-header__inner', '@media ( max-width: 960px )' ),
	array( array( 'column-gap: 16px;' ) ) );

$phone = rules_of( '.wpc-header__inner', '@media ( max-width: 640px )' );

ck( 'the 640px block still carries its one-class `.wpc-header__inner` rule, with the height and the padding it sets for the phone\'s two rows',
	array( count( $phone ), array_values( array_intersect( array( 'min-height: 0;', 'padding-bottom: 10px;', 'padding-top: 10px;' ), $phone[0] ?? array() ) ) ),
	array( 1, array( 'min-height: 0;', 'padding-bottom: 10px;', 'padding-top: 10px;' ) ) );

echo "\n=== One line at every width (theme 1.32.6) ===\n";

/**
 * The px value of a `clamp( MIN, calc( Avw - Bpx ), MAX )` or `clamp( MIN, calc( Avw + Bpx ), MAX )`
 * at a window of `$w` pixels, or null for any other shape.
 *
 * @param string $value A declared value.
 * @param int    $w     The window's width in px.
 * @return float|null
 */
function clamp_at( $value, $w ) {
	if ( ! preg_match( '/^clamp\( (\d+)px, calc\( ([\d.]+)vw ([-+]) (\d+)px \), (\d+)px \)$/', $value, $m ) ) {
		return null;
	}

	$preferred = $m[2] * $w / 100 + ( '-' === $m[3] ? -1 : 1 ) * $m[4];

	return round( max( (float) $m[1], min( (float) $m[5], $preferred ) ), 1 );
}

$root_tokens = array();

foreach ( rules_of( ':root' ) as $root_rule ) {
	foreach ( $root_rule as $line ) {
		if ( preg_match( '/^(--wpc-chrome-(?:space|item-pad|chip-gap)): (.+);$/', $line, $m ) ) {
			$root_tokens[ $m[1] ] = $m[2];
		}
	}
}

ck( 'the wide bar\'s spacing follows the window, so its items stay on one line from 1200px, where the signed-in bar starts, up: three tokens, each at its old size from 1440px up and shrinking in a straight line to its least at 1200px, the gap between the brand and the menu 40px to 24px, the side padding of each link 12px to 8px, the gap inside the viewer chip 18px to 10px; at 1200px that is 80px less for an Administrator\'s bar, which wrapped from 1200px to about 1235px with the fixed spacing',
	array(
		$root_tokens,
		array_map( static function ( $v ) { return array( clamp_at( $v, 1200 ), clamp_at( $v, 1320 ), clamp_at( $v, 1440 ), clamp_at( $v, 1920 ) ); }, $root_tokens ),
	),
	array(
		array(
			'--wpc-chrome-space'    => 'clamp( 24px, calc( 6.667vw - 56px ), 40px )',
			'--wpc-chrome-item-pad' => 'clamp( 8px, calc( 1.667vw - 12px ), 12px )',
			'--wpc-chrome-chip-gap' => 'clamp( 10px, calc( 3.333vw - 30px ), 18px )',
		),
		array(
			'--wpc-chrome-space'    => array( 24.0, 32.0, 40.0, 40.0 ),
			'--wpc-chrome-item-pad' => array( 8.0, 10.0, 12.0, 12.0 ),
			'--wpc-chrome-chip-gap' => array( 10.0, 14.0, 18.0, 18.0 ),
		),
	) );

ck( 'the wide bar reads the tokens: the gap between the brand and the menu, the side padding of the menu\'s links and submenu toggles, of the outline button (7px above and below, for its 1px border), of Need help? and of the chip\'s links, and the gap inside the chip; each the rule\'s only spacing that changed',
	array(
		array_values( preg_grep( '/^padding:/', rules_of( '.wpc-header .wpc-nav .wp-block-navigation-item__content, .wpc-header .wpc-nav .wp-block-navigation-submenu__toggle' )[0] ?? array() ) ),
		array_values( preg_grep( '/^padding:/', rules_of( '.wpc-header .wpc-nav .wpc-nav__partner > .wp-block-navigation-item__content' )[0] ?? array() ) ),
		array_values( preg_grep( '/^padding:/', rules_of( '.wpc-header .wpc-ask' )[0] ?? array() ) ),
		array_values( preg_grep( '/^padding:/', rules_of( '.wpc-viewer__link' )[0] ?? array() ) ),
		array_values( preg_grep( '/^gap:/', rules_of( '.wpc-viewer' )[0] ?? array() ) ),
	),
	array(
		array( 'padding: 8px var( --wpc-chrome-item-pad );' ),
		array( 'padding: 7px var( --wpc-chrome-item-pad );' ),
		array( 'padding: 8px var( --wpc-chrome-item-pad );' ),
		array( 'padding: 8px var( --wpc-chrome-item-pad );' ),
		array( 'gap: var( --wpc-chrome-chip-gap );' ),
	) );

ck( 'the items of the group, the navigation, Need help? and the viewer chip, stand 6px apart on every page: the one-class `.wpc-nav__center { gap: 6px }` ties with WordPress\'s `:root :where(.is-layout-flex) { gap: 20px }`, which some pages (the Partners page among them) print after the sheet, so the 6px is said again at two classes, as the column gap alone, leaving the row gaps of a wrapped bar to their own rules',
	array( rules_of( '.wpc-header .wpc-nav__center' ), array_values( preg_grep( '/gap/', rules_of( '.wpc-nav__center' )[0] ?? array() ) ) ),
	array( array( array( 'column-gap: 6px;' ) ), array( 'gap: 6px;' ) ) );

// Measured on the Docker copy on 25 October 2026 (headless Chrome, theme 1.32.6): "Education Directory" is
// 255.7px wide at 30px, so 8.523px per px of its size; on a signed-out phone the rest of the row is 169.8px
// (20px gutters, the 12px gap, the 44px menu button, the group's 6px gap, the 67.8px Log in), and on a
// signed-in one 142px (the gutters, 46px kept for the picture, the 12px gap, the 44px Menu button).
$phone_name = rules_of( '.wpc-header .wpc-brand__name', '@media ( max-width: 640px )' )[0][0] ?? '';
$phone_name = preg_replace( '/^font-size: (.+);$/', '$1', $phone_name );
$fits       = array();

foreach ( array( 320, 360, 375, 390, 414, 430, 480, 640 ) as $phone_w ) {
	$name_w = 8.523 * clamp_at( $phone_name, $phone_w );

	$fits[ $phone_w ] = array( $name_w + 169.8 + 12 <= $phone_w, $name_w + 142 + 12 <= $phone_w );
}

ck( 'on a phone the site\'s name is sized to the window, so it fits on one line beside the signed-out bar\'s menu button and Log in and beside the signed-in bar\'s picture and Menu button, at every common phone width from 320px to 640px: 16px at the least, from about 325px down, and 30px, theme.json\'s size, from about 444px up; with at least 12px to spare on either bar, about 18px from 325px up, for a system font wider than the one measured',
	array( $fits, clamp_at( $phone_name, 325 ), clamp_at( $phone_name, 375 ), clamp_at( $phone_name, 445 ) ),
	array( array_fill_keys( array( 320, 360, 375, 390, 414, 430, 480, 640 ), array( true, true ) ), 16.0, 21.9, 30.0 ) );

echo "\n=== The row spans the window (theme 1.32.6) ===\n";

ck( 'the header row spans the browser width: its one base rule has no `max-width` and no centering margin (`margin: 0` keeps the block gap off it), is as wide as the bar (`width: 100%`), and holds the brand and the menu off the window\'s edges by the page gutter (`padding: 0 var( --wpc-chrome-gutter )`); the wrap, the row gap and the 76px height are what they were, and the gap between the brand and the menu follows the window (below)',
	rules_of( '.wpc-header__inner' ),
	array( array( 'column-gap: var( --wpc-chrome-space );', 'flex-wrap: wrap;', 'margin: 0;', 'min-height: var( --wpc-header-height );', 'padding: 0 var( --wpc-chrome-gutter );', 'row-gap: 4px;', 'width: 100%;' ) ) );

$capped = array();

foreach ( sheet_rules() as $rule ) {
	if ( ! preg_grep( '/^max-width:/', $rule['declarations'] ) ) {
		continue;
	}

	foreach ( explode( ',', $rule['selector'] ) as $capped_selector ) {
		if ( preg_match( '/\.wpc-header(__inner)?(\.[\w-]+)*$/', trim( $capped_selector ) ) ) {
			$capped[] = trim( $rule['at'] . ' ' . $rule['selector'] );
		}
	}
}

ck( 'nothing caps the header\'s width at the shell the row had until 1.32.6: no rule whose selector ends at the header or at its row declares a `max-width` (a cap inside the bar, a dropdown\'s say, is not this check\'s), and the shell token (the measure plus both gutters) is gone from the sheet, so a rule that assumed the cap would name a token that is not there',
	array( $capped, false !== strpos( theme_file( 'style.css' ), 'chrome-shell' ) ),
	array( array(), false ) );

$gutters = array();

foreach ( sheet_rules() as $rule ) {
	if ( ':root' === $rule['selector'] ) {
		foreach ( preg_grep( '/^--wpc-chrome-gutter:/', $rule['declarations'] ) as $declaration ) {
			$gutters[] = array( $rule['at'], $declaration );
		}
	}
}

ck( 'the gutter the row reads is the page\'s: 40px, and 20px from 640px down, each said once, so the brand sits 40px (20px on a phone) from the window\'s left edge and the menu the same from its right',
	$gutters,
	array( array( '', '--wpc-chrome-gutter: 40px;' ), array( '@media ( max-width: 640px )', '--wpc-chrome-gutter: 20px;' ) ) );

$positioned = array();

foreach ( sheet_rules() as $rule ) {
	if ( preg_match( '/wpc-header__inner\s*$/', $rule['selector'] ) && preg_grep( '/^(position|transform):/', $rule['declarations'] ) ) {
		$positioned[] = trim( $rule['at'] . ' ' . $rule['selector'] );
	}
}

ck( 'the header, not its row, is the box the open panel hangs from and the picture is placed against, so the row\'s width reaches neither: `.wpc-header` is positioned in the phone\'s query (the panel as wide as the header, at its foot) and the menu layout positions and transforms the header; no rule positions the row',
	array( rules_of( '.wpc-header', '@media ( max-width: 640px )' ), rules_of( '.wpc-js .wpc-header--menu', '@media screen and ( max-width: 1199px )' ), $positioned ),
	array( array( array( 'position: relative;' ) ), array( array( 'position: relative;', 'transform: translateX( 0 );' ) ), array() ) );

echo "\n=== The markup the menu needs ===\n";

$menu = static function ( $html, $class = 'wpc-header' ) {
	return wpcredits_header_menu( $html, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => $class ) ) );
};

$page_of = static function ( $html ) {
	$doc = new DOMDocument();

	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();

	return new DOMXPath( $doc );
};

$attributes_of = static function ( $element ) {
	$attributes = array();

	foreach ( $element->attributes as $attribute ) {
		$attributes[ $attribute->name ] = $attribute->value;
	}

	return $attributes;
};

$next_element = static function ( $node ) {
	do {
		$node = $node->nextSibling;
	} while ( $node && XML_ELEMENT_NODE !== $node->nodeType );

	return $node;
};

$entries_of = static function ( $html ) use ( $page_of, $has ) {
	$classes = array( 'wp-block-navigation-item__content', 'wpc-ask', 'wpc-viewer__link', 'wpc-viewer__logout' );
	$found   = array();

	foreach ( $page_of( $html )->query( '//*[@id="wpc-header-menu"]//*[' . implode( ' or ', array_map( $has, $classes ) ) . ']' ) as $node ) {
		$found[] = trim( preg_replace( '/\s+/', ' ', $node->textContent ) );
	}

	return $found;
};

$buttons_in = static function ( $html ) use ( $page_of, $has ) {
	return $page_of( $html )->query( '//button[' . $has( 'wpc-menu-toggle' ) . ']' )->length;
};

$hooked_priority_of = static function ( $hook, $function ) {
	foreach ( isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array() as $hooked ) {
		if ( $function === $hooked[0] ) {
			return $hooked[1];
		}
	}

	return null;
};

$hooked_as = static function ( $hook, $function ) {
	foreach ( isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array() as $hooked ) {
		if ( $function === $hooked[0] ) {
			return array( $hooked[1], $hooked[2] );
		}
	}

	return null;
};

$printed_by = static function ( $function ) {
	ob_start();
	$function();

	return (string) ob_get_clean();
};

$three_with_submenu = wpcredits_test_three_with_submenu();
$no_extras          = array(
	'help'    => false,
	'student' => false,
	'mentor'  => false,
);

ck( 'the page is marked as running JavaScript first thing in the head: `wpcredits_js_mark()` on `wp_head` at priority 1, printing one line',
	array( $hooked_priority_of( 'wp_head', 'wpcredits_js_mark' ), trim( $printed_by( 'wpcredits_js_mark' ) ) ),
	array( 1, "<script>document.documentElement.classList.add( 'wpc-js' );</script>" ) );

$GLOBALS['logged_in'] = true;
$GLOBALS['marked']    = true;

$admin   = wpcredits_test_header();
$out     = $menu( $admin );
$page    = $page_of( $out );
$button  = $page->query( '//button[' . $has( 'wpc-menu-toggle' ) . ']' )->item( 0 );
$panel   = $button ? $next_element( $button ) : null;
$icons   = $button ? $page->query( './/svg', $button ) : array();
$undone  = str_replace( array( wpcredits_header_menu_button(), ' id="wpc-header-menu"', ' wpc-header--menu' ), '', $out );
$svg_in  = array( 'class' => array(), 'aria-hidden' => array() );

foreach ( $icons as $icon ) {
	$svg_in['class'][]       = $icon->getAttribute( 'class' );
	$svg_in['aria-hidden'][] = $icon->getAttribute( 'aria-hidden' );
}

$GLOBALS['marked'] = false;

ck( 'a signed-in reader\'s header gets one Menu button, right before the group holding the links, which it names: the button, the panel\'s ID and the header\'s class are all the filter adds',
	array(
		$page->query( '//button[' . $has( 'wpc-menu-toggle' ) . ']' )->length,
		$button ? $attributes_of( $button ) : null,
		$panel ? array( $panel->getAttribute( 'id' ), (bool) $page->query( 'self::*[' . $has( 'wpc-nav__center' ) . ']', $panel )->length ) : null,
		(bool) $page->query( '//header[' . $has( 'wpc-header--menu' ) . ']' )->length,
		$undone === $admin,
	),
	array(
		1,
		array(
			'type'          => 'button',
			'class'         => 'wpc-menu-toggle',
			'aria-controls' => 'wpc-header-menu',
			'aria-expanded' => 'false',
			'aria-label'    => '[Menu]',
		),
		array( 'wpc-header-menu', true ),
		true,
		true,
	) );

ck( 'the button draws the menu icon and the close icon, both hidden from screen readers',
	array( $svg_in['class'], $svg_in['aria-hidden'] ),
	array( array( 'wpc-icon wpc-menu-toggle__open', 'wpc-icon wpc-menu-toggle__close' ), array( 'true', 'true' ) ) );

$chip_children = $page->query( '//*[@id="wpc-header-menu"]//div[' . $has( 'wpc-viewer' ) . ']/*' );
$chip_last     = $chip_children->length ? $chip_children->item( $chip_children->length - 1 )->getAttribute( 'class' ) : null;

ck( 'the links stay the same elements in the same order, inside the panel, the picture last in it; a navigation of three items, one a submenu of two, lists its items in order, the submenu\'s under its parent',
	array( $entries_of( $out ), $chip_last, $entries_of( $menu( wpcredits_test_header( array( 'nav' => $three_with_submenu ) ) ) ) ),
	array(
		array( 'Homepage', 'Need help?', 'Student Report Card', 'Mentor Report Card', 'Log out' ),
		'wpc-viewer__avatar',
		array( 'Homepage', 'About the program', 'Programs', 'WordPress Credits', 'Campus Connect', 'Need help?', 'Student Report Card', 'Mentor Report Card', 'Log out' ),
	) );

$log_out_alone                       = wpcredits_test_header( array_merge( $no_extras, array( 'nav' => array() ) ) );
$homepage_and_log_out                = wpcredits_test_header( $no_extras );
$three_with_submenu_no_help_no_cards = wpcredits_test_header( array_merge( $no_extras, array( 'nav' => $three_with_submenu ) ) );

ck( 'a header that would list one entry, Log out alone, is left as it is; Homepage and Log out are two and get the button; so does the navigation of three items with a submenu and nothing else',
	array( $menu( $log_out_alone ) === $log_out_alone, $buttons_in( $menu( $homepage_and_log_out ) ), $buttons_in( $menu( $three_with_submenu_no_help_no_cards ) ) ),
	array( true, 1, 1 ) );

$help_and_log_out        = wpcredits_test_header(
	array(
		'nav'     => array(),
		'student' => false,
		'mentor'  => false,
	)
);
$report_card_and_log_out = wpcredits_test_header(
	array(
		'nav'    => array(),
		'help'   => false,
		'mentor' => false,
	)
);

ck( 'with no navigation, Need help? and Log out are two entries and get the button, and so do one report card link and Log out: each class the entry count names is counted on its own',
	array( $entries_of( $menu( $help_and_log_out ) ), $buttons_in( $menu( $help_and_log_out ) ), $entries_of( $menu( $report_card_and_log_out ) ), $buttons_in( $menu( $report_card_and_log_out ) ) ),
	array( array( 'Need help?', 'Log out' ), 1, array( 'Student Report Card', 'Log out' ), 1 ) );

$GLOBALS['logged_in'] = false;
$signed_out_kept      = $menu( $admin ) === $admin;
$GLOBALS['logged_in'] = true;

ck( 'signed out, and every other group, are left as they are: the inner group, the brand, the part, a group with no class, a class that holds the word only inside another',
	array(
		$signed_out_kept,
		$menu( $admin, 'wpc-header__inner' ) === $admin,
		$menu( $admin, 'wpc-brand' ) === $admin,
		$menu( $admin, 'wpc-header-part' ) === $admin,
		wpcredits_header_menu( $admin, array( 'blockName' => 'core/group', 'attrs' => array() ) ) === $admin,
		$menu( $admin, 'wpc-header-x' ) === $admin,
	),
	array( true, true, true, true, true, true ) );

$from_the_editor = str_replace( 'class="wp-block-group wpc-nav__center', 'id="from-the-editor" class="wp-block-group wpc-nav__center', $admin );
$replaced        = $menu( $from_the_editor );
$replaced_page   = $page_of( $replaced );
$replaced_button = $replaced_page->query( '//button[' . $has( 'wpc-menu-toggle' ) . ']' )->item( 0 );
$replaced_panel  = $replaced_button ? $next_element( $replaced_button ) : null;

ck( 'an ID the Site Editor gave the group is replaced by the one the button names, and the page holds it once',
	array(
		$replaced_panel ? $replaced_panel->getAttribute( 'id' ) : null,
		substr_count( $replaced, 'id="wpc-header-menu"' ),
		$replaced_button ? $replaced_button->getAttribute( 'aria-controls' ) : null,
		array( false !== strpos( $from_the_editor, 'from-the-editor' ), false === strpos( $replaced, 'from-the-editor' ) ),
	),
	array( 'wpc-header-menu', 1, 'wpc-header-menu', array( true, true ) ) );

$lone_and_home = static function ( array $items ) use ( $page_of, $has ) {
	$nav = $page_of( wpcredits_lone_nav_item( wpcredits_test_navigation( $items ) ) );

	return array(
		(bool) $nav->query( '//nav[' . $has( 'wpc-nav--lone' ) . ']' )->length,
		(bool) $nav->query( '//nav[' . $has( 'wpc-nav--home' ) . ']' )->length,
	);
};

ck( 'a navigation of one item is marked lone, and home too when its item leads to the home page, with or without the trailing slash; another lone item, and the three items with a submenu, are not marked home',
	array(
		$lone_and_home( array( array( 'Homepage', 'https://site.example/' ) ) ),
		$lone_and_home( array( array( 'Homepage', 'https://site.example' ) ) ),
		$lone_and_home( array( array( 'About', 'https://site.example/about/' ) ) ),
		$lone_and_home( $three_with_submenu ),
	),
	array( array( true, true ), array( true, true ), array( true, false ), array( false, false ) ) );

/**
 * The PHP files of the theme, below bin/, that hold a text, as paths below the theme's root. Folders
 * of other lines of development and of tools are skipped by the path below the root, so a linked
 * worktree's copy of the theme is never counted, nor a root that is itself one.
 *
 * @param string $needle The text.
 * @return string[] Sorted.
 */
$definitions_of = static function ( $needle ) {
	$root  = dirname( __DIR__ );
	$found = array();

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$below = substr( $file->getPathname(), strlen( $root ) );

		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}

		foreach ( array( '/.git/', '/.worktrees/', '/.superpowers/', '/node_modules/', '/bin/' ) as $skip ) {
			if ( false !== strpos( $below, $skip ) ) {
				continue 2;
			}
		}

		if ( false !== strpos( (string) file_get_contents( $file->getPathname() ), $needle ) ) {
			$found[] = ltrim( $below, '/' );
		}
	}

	sort( $found );

	return $found;
};

ck( 'functions.php requires inc/header.php right after inc/hub-content.php, and the navigation filter is written once, in inc/header.php',
	array(
		false !== strpos( theme_file( 'functions.php' ), "require_once get_theme_file_path( 'inc/hub-content.php' );\nrequire_once get_theme_file_path( 'inc/header.php' );" ),
		$definitions_of( 'function wpcredits_lone_nav_item(' ),
	),
	array( true, array( 'inc/header.php' ) ) );

ck( 'the filters are hooked as WordPress needs them, each as priority and accepted arguments: the menu on `render_block_core/group` at 10 taking two (the content and the block; one would be a fatal error on every page) and the lone item on `render_block_core/navigation` at 10 taking one',
	array(
		$hooked_as( 'render_block_core/group', 'wpcredits_header_menu' ),
		$hooked_as( 'render_block_core/navigation', 'wpcredits_lone_nav_item' ),
	),
	array( array( 10, 2 ), array( 10, 1 ) ) );

$brand_part = preg_match( '#<div class="wp-block-group wpc-brand">(.*?)</div>#s', theme_file( 'parts/header.html' ), $brand_said ) ? $brand_said[1] : '';
$brand_page = $page_of( wpcredits_test_header() );

ck( 'no WordPress mark beside the site\'s name (1.24.24): the header part\'s brand group holds the site title alone, no Site Logo block; nothing in the theme draws the mark in its place, filters the Site Logo block or defines the mark; the stand-in header\'s brand holds no picture; and the login screen\'s brand, the same lockup, prints no mark either',
	array(
		$brand_part,
		$definitions_of( 'render_block_core/site-logo' ),
		$definitions_of( 'function wpcredits_site_logo_fallback(' ),
		$definitions_of( 'function wpcredits_get_logo(' ),
		$brand_page->query( '//div[' . $has( 'wpc-brand' ) . ']//*[self::svg or self::img]' )->length,
		$brand_page->query( '//div[' . $has( 'wpc-brand' ) . ']/*' )->length,
		false !== strpos( theme_file( 'inc/login.php' ), '<svg' ) || false !== strpos( theme_file( 'inc/login.php' ), 'logo(' ),
	),
	array( '<!-- wp:site-title {"level":0,"className":"wpc-brand__name"} /-->', array(), array(), array(), 0, 1, false ) );

/**
 * The classes among those given that a PHP file of the theme does not print in its code, read with the
 * tokenizer without its comments, as whole class names.
 *
 * @param array $files File below the theme root => the classes it prints.
 * @return string[] Each as "file: class".
 */
$classes_missing_from = static function ( array $files ) {
	$missing = array();

	foreach ( $files as $file => $classes ) {
		$code = '';

		foreach ( token_get_all( theme_file( $file ) ) as $token ) {
			if ( ! is_array( $token ) ) {
				$code .= $token;
			} elseif ( ! in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				$code .= $token[1];
			}
		}

		foreach ( $classes as $class ) {
			if ( ! preg_match( '/(?<![A-Za-z0-9_-])' . preg_quote( $class, '/' ) . '(?![A-Za-z0-9_-])/', $code ) ) {
				$missing[] = $file . ': ' . $class;
			}
		}
	}

	return $missing;
};

ck( 'the fixture prints the classes the blocks print: each of wpc-ask, wpc-viewer__link, wpc-viewer__logout, wpc-viewer__avatar and wpc-viewer__signin is in its render.php, read without its comments',
	$classes_missing_from(
		array(
			'blocks/handbook-launcher/render.php' => array( 'wpc-ask' ),
			'blocks/viewer-chip/render.php'       => array( 'wpc-viewer__link', 'wpc-viewer__logout', 'wpc-viewer__avatar', 'wpc-viewer__signin' ),
		)
	),
	array() );

ck( 'the Menu button\'s label tells a translator what it is: a translators note right above it says it is the label of the button that opens the site menu on narrow screens, and the template carries the note',
	array(
		(bool) preg_match( "#/\* translators: [^*]*button that opens the site menu on narrow screens[^*]*\*/\n\s*esc_attr__\( 'Menu', 'wpcredits-theme' \)#", theme_file( 'inc/header.php' ) ),
		(bool) preg_match( '#\n\#\. translators: [^\n]*button that opens the site menu on narrow screens[^\n]*\n\#: inc/header\.php:\d+\nmsgid "Menu"\n#', theme_file( 'languages/wpcredits-theme.pot' ) ),
	),
	array( true, true ) );

echo "\n=== The menu layout ===\n";

$menu_at   = '@media screen and ( max-width: 1199px )';
$forced_at = '@media screen and ( max-width: 1199px ) and ( forced-colors: active )';
$phone_at  = '@media ( max-width: 640px )';
$m         = '.wpc-js .wpc-header--menu';

/**
 * The rows among those given that are not in the sheet exactly once, in the at-rule given, with every
 * declaration pinned for them.
 *
 * @param array[] $rows Selector list as the sheet writes it => the declarations it must hold.
 * @param string  $at   The at-rule the rules sit in, `''` for none.
 * @return string[] Selector list => what is wrong; empty when all is well.
 */
$unpinned = static function ( array $rows, $at = '' ) {
	$problems = array();

	foreach ( $rows as $selector => $pinned ) {
		$found = rules_of( $selector, $at );

		if ( 1 !== count( $found ) ) {
			$problems[ $selector ] = count( $found ) . ' rules';
		} elseif ( array_diff( $pinned, $found[0] ) ) {
			$problems[ $selector ] = 'missing ' . implode( ' ', array_diff( $pinned, $found[0] ) );
		}
	}

	return $problems;
};

/**
 * The selectors of a selector list, split at the commas outside parentheses and brackets.
 *
 * @param string $list The selector list.
 * @return string[]
 */
$selectors_of = static function ( $list ) {
	$found   = array();
	$depth   = 0;
	$current = '';

	foreach ( str_split( $list ) as $char ) {
		if ( '(' === $char || '[' === $char ) {
			++$depth;
		} elseif ( ')' === $char || ']' === $char ) {
			--$depth;
		}

		if ( ',' === $char && 0 === $depth ) {
			$found[] = trim( $current );
			$current = '';
			continue;
		}

		$current .= $char;
	}

	$found[] = trim( $current );

	return $found;
};

/**
 * What a selector weighs against another: its classes, attribute selectors and pseudo-classes. The
 * argument of `:not()` is counted and `:not` itself is not; type selectors and pseudo-elements are not.
 *
 * @param string $selector One selector.
 * @return int
 */
$specificity = static function ( $selector ) {
	$attributes = preg_match_all( '/\[[^\]]*\]/', $selector );
	$selector   = preg_replace( array( '/\[[^\]]*\]/', '/::[a-z-]+/', '/:not\(/' ), array( '', '', '(' ), $selector );

	return $attributes + preg_match_all( '/\.[A-Za-z_-][\w-]*/', $selector ) + preg_match_all( '/:[a-z-]+/', $selector );
};

$index_of_rule = static function ( $selector, $at ) {
	foreach ( sheet_rules() as $index => $rule ) {
		if ( $rule['selector'] === $selector && $rule['at'] === $at ) {
			return $index;
		}
	}

	return -1;
};

$names_any = static function ( $text, array $names ) {
	foreach ( $names as $name ) {
		if ( false !== strpos( $text, $name ) ) {
			return true;
		}
	}

	return false;
};

$index_of_last_rule_naming = static function ( array $names, array $except ) use ( $names_any ) {
	$last = -1;

	foreach ( sheet_rules() as $index => $rule ) {
		if ( $names_any( $rule['selector'], $names ) && ! $names_any( $rule['selector'], $except ) ) {
			$last = $index;
		}
	}

	return $last;
};

$index_of_first_rule_naming = static function ( $name ) {
	foreach ( sheet_rules() as $index => $rule ) {
		if ( false !== strpos( $rule['selector'], $name ) ) {
			return $index;
		}
	}

	return -1;
};

/**
 * How deep the deepest selector is that names a text and is not part of the menu layout.
 *
 * @param string $needle The text.
 * @return int
 */
$deepest_outside_menu = static function ( $needle ) use ( $selectors_of, $specificity ) {
	$deepest = 0;

	foreach ( sheet_rules() as $rule ) {
		foreach ( $selectors_of( $rule['selector'] ) as $selector ) {
			if ( false !== strpos( $selector, $needle ) && false === strpos( $selector, 'wpc-header--menu' ) ) {
				$deepest = max( $deepest, $specificity( $selector ) );
			}
		}
	}

	return $deepest;
};

/**
 * Whether a selector is in the sheet, written as given, as one of a rule's selector list.
 *
 * @param string $wanted The selector.
 * @return bool
 */
$is_in_the_sheet = static function ( $wanted ) use ( $selectors_of ) {
	foreach ( sheet_rules() as $rule ) {
		if ( in_array( $wanted, $selectors_of( $rule['selector'] ), true ) ) {
			return true;
		}
	}

	return false;
};

$selectors_declaring_under = static function ( $class, $declaration ) {
	$found = array();

	foreach ( sheet_rules() as $rule ) {
		if ( false !== strpos( $rule['selector'], $class ) && in_array( $declaration, $rule['declarations'], true ) ) {
			$found[] = $rule['selector'];
		}
	}

	return $found;
};

$at_rules_of_rules_naming = static function ( array $names ) use ( $names_any ) {
	$found = array();

	foreach ( sheet_rules() as $rule ) {
		if ( $names_any( $rule['selector'], $names ) && ! in_array( $rule['at'], $found, true ) ) {
			$found[] = $rule['at'];
		}
	}

	return $found;
};

$selectors_not_starting_with = static function ( $prefix ) use ( $names_any, $selectors_of ) {
	$found = array();

	foreach ( sheet_rules() as $rule ) {
		if ( ! $names_any( $rule['selector'], array( 'wpc-header--menu', 'wpc-header--open' ) ) ) {
			continue;
		}

		foreach ( $selectors_of( $rule['selector'] ) as $selector ) {
			if ( 0 !== strpos( $selector, $prefix ) ) {
				$found[] = $selector;
			}
		}
	}

	return $found;
};

$panel_height = 'calc( 100vh - var( --wpc-header-live, var( --wpc-header-height ) ) - var( --wp-admin--admin-bar--height, 0px ) - var( --wp-global-header-height, 0px ) - var( --wp--style--block-gap, 0px ) )';
$panel_lines  = rules_of( $m . ' .wpc-nav__center', $menu_at )[0] ?? array();

$row_selectors = array(
	$m . ' .wpc-nav .wp-block-navigation-item__content',
	$m . ' .wpc-nav .has-child .wp-block-navigation__submenu-container > .wp-block-navigation-item > .wp-block-navigation-item__content',
	$m . ' .wpc-nav__center .wpc-ask',
	$m . ' .wpc-viewer .wpc-viewer__link',
	$m . ' .wpc-viewer .wpc-viewer__logout',
);
$row_states    = implode(
	', ',
	array_merge(
		array_map( static function ( $selector ) { return $selector . ':hover'; }, $row_selectors ),
		array_map( static function ( $selector ) { return $selector . ':focus-visible'; }, $row_selectors )
	)
);

ck( 'the Menu button takes the navigation toggle\'s rule, 44px square and not displayed until the menu layout shows it, and its two icons follow its state: the menu icon until it is expanded, the close icon after; each rule is in the sheet once, outside any query',
	$unpinned(
		array(
			'.wpc-header .wpc-nav .wp-block-navigation__responsive-container-open:not( .always-shown ), .wpc-header .wpc-menu-toggle' => array( 'display: none;', 'height: 44px;', 'width: 44px;' ),
			'.wpc-menu-toggle .wpc-menu-toggle__close, .wpc-menu-toggle[aria-expanded="true"] .wpc-menu-toggle__open' => array( 'display: none;' ),
			'.wpc-menu-toggle[aria-expanded="true"] .wpc-menu-toggle__close' => array( 'display: block;' ),
		)
	),
	array() );

ck( 'below 1200px the signed-in bar is one row: the header is positioned and transformed, so it is the containing block of the panel and of the fixed picture; the inner group does not wrap and gaps by 12px; the brand keeps 46px clear for the picture, may shrink and wraps its title; the Menu button is shown at the end of the row and tinted while open; each rule once, in the screen query',
	$unpinned(
		array(
			$m => array( 'position: relative;', 'transform: translateX( 0 );' ),
			$m . ' .wpc-header__inner' => array( 'column-gap: 12px;', 'flex-wrap: nowrap;' ),
			$m . ' .wpc-brand' => array( 'flex: 0 1 auto;', 'gap: 12px;', 'margin-right: calc( 34px + 12px );', 'min-width: 0;' ),
			$m . ' .wpc-brand__name' => array( 'min-width: 0;', 'overflow-wrap: anywhere;', 'white-space: normal;' ),
			$m . ' .wpc-menu-toggle' => array( 'display: flex;', 'flex: none;', 'margin-left: auto;' ),
			$m . ' .wpc-menu-toggle[aria-expanded="true"]' => array(),
		),
		$menu_at
	),
	array() );

ck( 'the panel is the group holding the links: a block placed under the bar, as wide as it, scrolling inside itself and capped at the window\'s height less all that lies above it at the top of a page, the bar, the admin bar, the WordPress.org bar and the block gap under it (100vh, then 100svh after it), handing a scroll it cannot take to the page (`overscroll-behavior: auto`, its one overscroll line), hidden by visibility and shown by the open header; each rule once, in the screen query',
	array(
		$unpinned(
			array(
				$m . ' .wpc-nav__center' => array(
					'display: block;',
					'left: 0;',
					'max-height: ' . $panel_height . ';',
					'max-height: ' . str_replace( '100vh', '100svh', $panel_height ) . ';',
					'overflow-y: auto;',
					'position: absolute;',
					'right: 0;',
					'top: calc( 100% + 1px );',
					'visibility: hidden;',
					'z-index: 55;',
				),
				'.wpc-js .wpc-header--menu.wpc-header--open .wpc-nav__center' => array( 'visibility: visible;' ),
			),
			$menu_at
		),
		array_search( 'max-height: ' . $panel_height . ';', $panel_lines, true ) < array_search( 'max-height: ' . str_replace( '100vh', '100svh', $panel_height ) . ';', $panel_lines, true ),
		array_values( preg_grep( '/^overscroll-behavior/', $panel_lines ) ),
	),
	array( array(), true, array( 'overscroll-behavior: auto;' ) ) );

ck( 'the account picture is drawn in the bar: fixed against the header at its middle, 12px left of the Menu button, and shown although the closed panel it sits in is hidden; the chip around it is a block; each rule once, in the screen query',
	$unpinned(
		array(
			$m . ' .wpc-viewer__avatar' => array( 'margin-top: -17px;', 'position: fixed;', 'right: calc( var( --wpc-chrome-gutter ) + 44px + 12px );', 'top: 50%;', 'visibility: visible;' ),
			$m . ' .wpc-viewer' => array( 'display: block;' ),
		),
		$menu_at
	),
	array() );

ck( 'inside the panel the navigation is a plain block list: its own toggle and close button are never displayed, the toggle not even when the navigation is set to always show it, its container, dialog and content stay in the flow, its list and items are blocks, a submenu is listed expanded and indented 12px, and its icon is not displayed; each rule once, in the screen query',
	$unpinned(
		array(
			$m . ' .wpc-nav.wp-block-navigation' => array( 'display: block;', 'position: static;' ),
			$m . ' .wpc-nav .wp-block-navigation__responsive-container-open, ' . $m . ' .wpc-nav .wp-block-navigation__responsive-container-open:not( .always-shown ), ' . $m . ' .wpc-nav .wp-block-navigation__responsive-container-close' => array( 'display: none;' ),
			$m . ' .wpc-nav .wp-block-navigation__responsive-container:not( .is-menu-open )' => array( 'display: block;', 'height: auto;', 'padding: 0;', 'position: static;', 'width: auto;' ),
			$m . ' .wpc-nav .wp-block-navigation__responsive-container:not( .is-menu-open ) .wp-block-navigation__responsive-dialog, ' . $m . ' .wpc-nav .wp-block-navigation__responsive-container:not( .is-menu-open ) .wp-block-navigation__responsive-container-content' => array( 'display: block;', 'padding: 0;', 'position: static;' ),
			$m . ' .wpc-nav .wp-block-navigation__container, ' . $m . ' .wpc-nav .wp-block-navigation-item' => array( 'display: block;', 'width: auto;' ),
			$m . ' .wpc-nav .has-child .wp-block-navigation-submenu__toggle[aria-expanded] ~ .wp-block-navigation__submenu-container, ' . $m . ' .wpc-nav .has-child .wp-block-navigation__submenu-container, ' . $m . ' .wpc-nav.wp-block-navigation .has-child:not( .open-on-click ) .wp-block-navigation__submenu-container' => array( 'display: block;', 'height: auto;', 'min-width: 0;', 'opacity: 1;', 'overflow: visible;', 'padding: 0 0 0 12px;', 'position: static;', 'visibility: inherit;', 'width: auto;' ),
			$m . ' .wpc-nav .wp-block-navigation__submenu-icon' => array( 'display: none;' ),
		),
		$menu_at
	),
	array() );

ck( 'the navigation\'s container, while it is not open, declares no background: core\'s `background-color: inherit !important` for a container that is not open outranks any, so a declaration here would do nothing and the panel\'s own surface shows through; its rule holds the five layout declarations and no more',
	rules_of( $m . ' .wpc-nav .wp-block-navigation__responsive-container:not( .is-menu-open )', $menu_at ),
	array( array( 'display: block;', 'height: auto;', 'padding: 0;', 'position: static;', 'width: auto;' ) ) );

ck( 'every entry is a 44px row (15px type on a 20px line with 12px above and below, a hairline under it, the full width), whatever block prints it; hover and focus turn it brand blue; Log out is last, so it has no hairline; the page being viewed is brand blue; each rule once, in the screen query',
	$unpinned(
		array(
			implode( ', ', $row_selectors ) => array( 'border-bottom: 1px solid var( --wpc-chrome-rule );', 'display: block;', 'font-size: 15px;', 'line-height: 20px;', 'padding: 12px 0;', 'width: 100%;' ),
			$row_states => array(),
			$m . ' .wpc-viewer .wpc-viewer__logout' => array( 'border-bottom: 0;' ),
			$m . ' .wpc-nav [aria-current="page"], ' . $m . ' .wpc-viewer .wpc-viewer__link.is-current' => array(),
		),
		$menu_at
	),
	array() );

ck( 'on the institution form\'s page the menu\'s "Partner as an institution" row is marked current (inc/partners.php), and in the panel it keeps the weight 600 its own rule gives it, which the rule for the page being viewed would take down to 500: one rule in the screen query, deeper than that rule',
	array( rules_of( $m . ' .wpc-nav .wpc-nav__partner > [aria-current="page"]', $menu_at ), rules_of( '.wpc-header .wpc-nav .wpc-nav__partner > .wp-block-navigation-item__content', '' ) ),
	array( array( array( 'font-weight: 600;' ) ), array( array( 'border: 1px solid var( --wpc-brand );', 'color: var( --wpc-brand );', 'font-weight: 600;', 'padding: 7px var( --wpc-chrome-item-pad );' ) ) ) );

$current_rule = '.wpc-header .wpc-nav .current-menu-item > .wp-block-navigation-item__content, .wpc-header .wpc-nav [aria-current="page"]';
$button_rule  = '.wpc-header .wpc-nav .wpc-nav__partner > .wp-block-navigation-item__content';

ck( 'on the wide row a link to the page being viewed is the brand blue at weight 500, the "Partners" link on the Partners page among them, and the outline button keeps its weight 600 on its own page, the form\'s: its rule is as deep as the deepest selector of the current-item rule and comes after it, so the tie goes the button\'s way',
	array(
		rules_of( $current_rule ),
		max( array_map( $specificity, $selectors_of( $current_rule ) ) ) <= $specificity( $button_rule ),
		$index_of_rule( $button_rule, '' ) > $index_of_rule( $current_rule, '' ),
	),
	array( array( array( 'color: var( --wpc-brand );', 'font-weight: 500;' ) ), true, true ) );

ck( 'in the panel "Partner as an institution" is a row like the others: the wide row\'s outline button would keep its top, left and right borders, with the text against the left one, since the row rule replaces only the bottom border; one rule in the screen query takes the three off and leaves the row\'s hairline under it',
	array( rules_of( $m . ' .wpc-nav .wpc-nav__partner > .wp-block-navigation-item__content', $menu_at ) ),
	array( array( array( 'border-left: 0;', 'border-right: 0;', 'border-top: 0;' ) ) ) );

ck( 'in forced colors, where the page being viewed loses its color, the menu underlines it: one rule in the forced-colors twin of the screen query',
	$unpinned( array( $m . ' .wpc-nav__center [aria-current="page"]' => array( 'text-decoration: underline;' ) ), $forced_at ),
	array() );

$signed_out_scope = ':not( :has( .wpc-nav--lone:not( .wpc-nav--home ) ) )';
$signed_out_inner = 'body:not( .wpc-signed-in ) .wpc-header__inner' . $signed_out_scope;
$signed_out_hider = 'body:not( .wpc-signed-in ) .wpc-header .wpc-nav--home';
$signed_out_loose = array();
$signed_out_block = 0;

foreach ( sheet_rules() as $rule ) {
	if ( $phone_at === $rule['at'] && $signed_out_hider === $rule['selector'] ) {
		$signed_out_block = $rule['block'];
	}
}

foreach ( sheet_rules() as $rule ) {
	if ( 0 === $signed_out_block || $signed_out_block !== $rule['block'] ) {
		continue;
	}

	foreach ( $selectors_of( $rule['selector'] ) as $selector ) {
		if ( false !== strpos( $selector, 'wpc-signed-in' ) && false === strpos( $selector, $signed_out_scope ) && $signed_out_hider !== $selector ) {
			$signed_out_loose[] = $selector;
		}
	}
}

ck( 'signed out, a phone is one row (theme 1.32.6): the brand, then the menu button and Log in, or Log in alone where the navigation\'s lone link, the home page, is left out; the bar does not wrap and gaps by 12px, the brand takes the room, may shrink and gaps by 12px, the group is as wide as its buttons, and the navigation of one link home is not displayed; each rule once, in the 640px query, and every rule of that block that names `wpc-signed-in`, but the one that leaves the link out, is scoped with `:has()` to a bar whose lone link is not shown inline, so a lone link with a label of its own keeps the two rows (a rule naming `wpc-signed-in` elsewhere in the sheet is not this block\'s to judge)',
	array(
		$unpinned(
			array(
				$signed_out_inner                        => array( 'column-gap: 12px;', 'flex-wrap: nowrap;' ),
				$signed_out_inner . ' .wpc-brand'        => array( 'flex: 1 1 auto;', 'gap: 12px;', 'min-width: 0;' ),
				$signed_out_inner . ' .wpc-nav__center'  => array( 'flex: none;' ),
				$signed_out_hider                        => array( 'display: none;' ),
			),
			$phone_at
		),
		$signed_out_loose,
	),
	array( array(), array() ) );

ck( 'on a phone the Log in button is 14px with 9px 14px padding, as its 640px rule always said: the rule is two classes deep, as the base rule is, and comes after it; no one-class version is left',
	array( rules_of( '.wpc-header .wpc-viewer__signin, .wpc-header .wpc-viewer--guest a', '@media ( max-width: 640px )' ), $index_of_rule( '.wpc-header .wpc-viewer__signin, .wpc-header .wpc-viewer--guest a', '@media ( max-width: 640px )' ) > $index_of_rule( '.wpc-header .wpc-viewer__signin, .wpc-header .wpc-viewer--guest a', '' ), rules_anywhere( '.wpc-viewer__signin, .wpc-viewer--guest a' ) ),
	array( array( array( 'font-size: 14px;', 'padding: 9px 14px;' ) ), true, 0 ) );

ck( 'nothing in the panel but the picture and the open panel itself is made visible, so nothing shows through the closed menu',
	$selectors_declaring_under( 'wpc-header--menu', 'visibility: visible;' ),
	array( '.wpc-js .wpc-header--menu.wpc-header--open .wpc-nav__center', '.wpc-js .wpc-header--menu .wpc-viewer__avatar' ) );

$submenu_container = '.wp-block-navigation__submenu-container';
$submenu_row       = $submenu_container . ' > .wp-block-navigation-item > .wp-block-navigation-item__content';

// The reveal of core's navigation stylesheet that the sheet does not hold: a submenu opened by focus.
$core_focus_reveal = '.wp-block-navigation .has-child:not(.open-on-click):not(.open-on-hover-click):focus-within > ' . $submenu_container;

// The menu's selectors that decide a submenu, each weighed against the sheet's own deepest one and core's.
$container_with_toggle = $m . ' .wpc-nav .has-child .wp-block-navigation-submenu__toggle[aria-expanded] ~ ' . $submenu_container;
$container_hover_mode  = $m . ' .wpc-nav.wp-block-navigation .has-child:not( .open-on-click ) ' . $submenu_container;
$container_to_outrank  = max( $deepest_outside_menu( $submenu_container ), $specificity( $core_focus_reveal ) );

ck( 'the menu\'s submenu selectors are in the sheet, each deeper than every rule outside the menu layout that reveals or restyles a submenu (6 today) and than core\'s reveal on focus (6): the container of a submenu with a toggle, the container of one that opens on hover and has none, and the submenu row, so the closed panel stays closed after a submenu toggle is pressed or the focus comes into a submenu, and a submenu row keeps the 44px row',
	array(
		$is_in_the_sheet( $container_with_toggle ) && $specificity( $container_with_toggle ) > $container_to_outrank,
		$is_in_the_sheet( $container_hover_mode ) && $specificity( $container_hover_mode ) > $container_to_outrank,
		$is_in_the_sheet( $row_selectors[1] ) && $specificity( $row_selectors[1] ) > $deepest_outside_menu( $submenu_row ),
	),
	array( true, true, true ) );

ck( 'every rule of the menu layout is inside the screen query below 1200px, or its forced-colors twin, and each of its selectors starts with `.wpc-js .wpc-header--menu`, so without JavaScript, signed out, in print and from 1200px nothing of it applies',
	array( $at_rules_of_rules_naming( array( 'wpc-header--menu', 'wpc-header--open' ) ), $selectors_not_starting_with( '.wpc-js .wpc-header--menu' ) ),
	array( array( '@media screen and ( max-width: 1199px )', '@media screen and ( max-width: 1199px ) and ( forced-colors: active )' ), array() ) );

ck( 'the menu layout comes after every header rule it restates, the 640px, 960px and stacked blocks among them, so a tie of specificity goes its way: the 960px rule that shows the navigation\'s own toggle ties the menu\'s rule that hides it, and the navigation\'s links and container are restated alike',
	$index_of_first_rule_naming( 'wpc-header--menu' ) > $index_of_last_rule_naming( array( '.wpc-header--stacked', 'wpc-nav--lone', '.wpc-viewer__logout', '.wpc-nav__center', 'wp-block-navigation-item__content', 'responsive-container' ), array( 'wpc-header--menu', 'wpc-signed-in' ) ),
	true );

echo "\n=== The menu script ===\n";

/**
 * The scripts the callbacks hooked on `wp_enqueue_scripts` enqueue, run in priority order through
 * `$GLOBALS['hooks']` for a reader signed in or out; the globals they read and write are put back.
 *
 * @param bool $logged_in Whether the reader is signed in.
 * @return array Handle => `array( src, deps, ver, args )`.
 */
$scripts_enqueued_for = static function ( $logged_in ) {
	$was       = array( $GLOBALS['logged_in'], $GLOBALS['scripts'] );
	$callbacks = isset( $GLOBALS['hooks']['wp_enqueue_scripts'] ) ? $GLOBALS['hooks']['wp_enqueue_scripts'] : array();

	usort( $callbacks, static function ( $a, $b ) { return $a[1] - $b[1]; } );

	$GLOBALS['logged_in'] = $logged_in;
	$GLOBALS['scripts']   = array();

	foreach ( $callbacks as $callback ) {
		call_user_func( $callback[0] );
	}

	$scripts = $GLOBALS['scripts'];

	list( $GLOBALS['logged_in'], $GLOBALS['scripts'] ) = $was;

	return $scripts;
};

$scripts_when_signed_in  = $scripts_enqueued_for( true );
$scripts_when_signed_out = $scripts_enqueued_for( false );

ck( 'the menu script is enqueued for a signed-in reader alone: deferred, no dependency, the theme\'s version',
	array( $scripts_when_signed_in['wpcredits-header-menu'] ?? null, $scripts_when_signed_out ),
	array( array( 'https://site.example/wp-content/themes/wpcredits-theme/assets/js/header-menu.js', array(), '9.9.9', array( 'strategy' => 'defer' ) ), array() ) );

ck( 'the handbook launcher\'s comment names the class the header\'s menu script reads', false !== strpos( theme_file( 'blocks/handbook-launcher/render.php' ), 'wpcpm-hb-open' ), true );

$GLOBALS['logged_in'] = false;

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
