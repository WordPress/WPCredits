<?php
/**
 * The Education Hub homepage (theme 1.24.15): its template and the rule that lets the front page be
 * drawn by it, its stylesheet, its content, its two blocks, the figures and its patterns, rendered
 * on the WordPress of bin/stubs/hub-wp.php.
 *
 * What a person meets without JavaScript is pinned here, from the server markup: everything shown,
 * every control printed hidden, every string translated and every link where the design note says.
 * What the view scripts do with that markup is bin/test-hub-js.php's.
 *
 * Run from the theme root:  php bin/test-hub.php
 *
 * @package WPCredits_Theme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';
require_once WPCREDITS_THEME_DIR . 'inc/template-tags.php';

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
 * A request: what it shows, and the page templates the site's pages store.
 *
 * @param string $singular  The post type a singular request shows, or ''.
 * @param int    $id        The queried object's ID.
 * @param array  $templates Page ID => template slug.
 */
function request( $singular, $id = 0, array $templates = array() ) {
	$GLOBALS['request']  = array(
		'singular'  => $singular,
		'id'        => $id,
		'templates' => $templates,
	);
	$GLOBALS['enqueued'] = array();
}

/**
 * A file of the theme.
 *
 * @param string $path Below the theme root.
 * @return string
 */
function theme_file( $path ) {
	return (string) file_get_contents( WPCREDITS_THEME_DIR . $path );
}

/**
 * A color of theme.json, by its palette slug or its `settings.custom` path, such as `status.green`.
 *
 * @param string $name The slug or the path.
 * @return string `#rrggbb`.
 */
function color_of( $name ) {
	$json = json_decode( theme_file( 'theme.json' ), true );

	foreach ( $json['settings']['color']['palette'] as $color ) {
		if ( $color['slug'] === $name ) {
			return $color['color'];
		}
	}

	$at = $json['settings']['custom'];

	foreach ( explode( '.', $name ) as $key ) {
		$at = $at[ $key ];
	}

	return $at;
}

/**
 * The contrast ratio of two colors, as WCAG 2 defines it, to two decimals.
 *
 * @param string $one A color, `#rrggbb`.
 * @param string $two Another.
 * @return string
 */
function contrast( $one, $two ) {
	$luminance = static function ( $hex ) {
		$channels = array_map(
			static function ( $pair ) {
				$value = hexdec( $pair ) / 255;

				return $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
			},
			str_split( ltrim( $hex, '#' ), 2 )
		);

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	};

	$a = $luminance( $one );
	$b = $luminance( $two );

	return number_format( ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 ), 2 );
}

/**
 * The declarations of every rule in hub.css (or another sheet) whose selector list is exactly the one
 * given, in a media query, in a supports rule or in neither, comments left out, in order.
 *
 * @param string $selector The selector list, as the sheet writes it.
 * @param string $sheet    The sheet, below the theme root.
 * @return string[] Each rule's declarations, whitespace run together.
 */
function rules_for( $selector, $sheet = 'assets/css/hub.css' ) {
	$css = preg_replace( array( '#/\*.*?\*/#s', '/@(?:media|supports)[^{]*\{/' ), '', theme_file( $sheet ) );

	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $found, PREG_SET_ORDER );

	$rules = array();

	foreach ( $found as $rule ) {
		if ( preg_replace( '/\s+/', ' ', trim( $rule[1] ) ) === $selector ) {
			$rules[] = trim( preg_replace( '/\s+/', ' ', $rule[2] ) );
		}
	}

	return $rules;
}

/**
 * The rules hub.css declares inside `@supports selector( :has( * ) )`, in order, each written
 * `selector { declarations }` with its whitespace run together: a focus ring drawn through `:has()`
 * and the `outline: none` it stands in for are declared together there, so a browser without `:has()`
 * keeps the theme's own ring.
 *
 * @return string[]
 */
function under_has_support() {
	$css = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) );

	preg_match_all( '/@supports selector\( :has\( \* \) \) \{((?:[^{}]*\{[^{}]*\})*)[^{}]*\}/', $css, $blocks );

	$rules = array();

	foreach ( $blocks[1] as $block ) {
		preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $block, $found, PREG_SET_ORDER );

		foreach ( $found as $rule ) {
			$rules[] = preg_replace( '/\s+/', ' ', trim( $rule[1] ) . ' { ' . trim( $rule[2] ) . ' }' );
		}
	}

	return $rules;
}

/**
 * Some markup, parsed, to be asked with XPath.
 *
 * @param string $html The markup.
 * @return DOMXPath
 */
function dom_of( $html ) {
	$document = new DOMDocument();

	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();

	return new DOMXPath( $document );
}

/**
 * What an XPath query finds: each node's text, whitespace run together, or one attribute of each.
 *
 * @param DOMXPath $xpath     The markup.
 * @param string   $query     The query.
 * @param string   $attribute An attribute's name, or '' for the text.
 * @return string[]
 */
function found( DOMXPath $xpath, $query, $attribute = '' ) {
	$said = array();

	foreach ( $xpath->query( $query ) as $node ) {
		$said[] = '' === $attribute ? trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ) : (string) $node->getAttribute( $attribute );
	}

	return $said;
}

/**
 * The texts and the read-out attributes of some markup that are not translated strings: with the
 * translations marked, the text of every paragraph, heading, list item, term, cell and button, and
 * every `aria-label`, `data-announce` and `alt`, is bracketed strings, one inside another where a name
 * sits in a sentence, and numbers alone. A text is read whole where it is written, so a sentence with
 * a link in it is one text; the decorative arrows, and the `//` between the names of the hero
 * mountain's rows, are left out.
 *
 * @param string $html The markup, rendered with `$GLOBALS['marked']` on.
 * @return string[]
 */
function unmarked( $html ) {
	$xpath = dom_of( $html );
	$texts = array();
	$whole = array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'dt', 'dd', 'button', 'th', 'td', 'figcaption' );

	foreach ( $xpath->query( '//body//text()[normalize-space()]' ) as $node ) {
		$holder = $node->parentNode;

		while ( $holder->parentNode && ! in_array( strtolower( $holder->nodeName ), $whole, true ) && 'body' !== strtolower( $holder->parentNode->nodeName ) ) {
			$holder = $holder->parentNode;
		}

		$holder = in_array( strtolower( $holder->nodeName ), $whole, true ) ? $holder : $node;

		$texts[ $holder->getNodePath() ] = $holder->textContent;
	}

	foreach ( $xpath->query( '//@aria-label | //@data-announce | //@alt' ) as $node ) {
		$texts[ $node->getNodePath() ] = $node->nodeValue;
	}

	$bare = array();

	foreach ( $texts as $text ) {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		$left = $text;

		// A translated string may hold another, a name inside a sentence: the innermost first.
		do {
			$before = $left;
			$left   = preg_replace( '/\[[^\[\]]*\]/u', '', $left );
		} while ( $left !== $before );

		if ( '' !== trim( preg_replace( '#(?://|[0-9,.%→\s])+#u', '', $left ) ) ) {
			$bare[] = $text;
		}
	}

	return $bare;
}

/**
 * The rules hub.css declares inside `@media <query>`, in order, each written `selector { declarations }`
 * with its whitespace run together, comments left out.
 *
 * @param string $query The query as the sheet writes it, such as `( max-width: 899px )`.
 * @return string[]
 */
function rules_in_media( $query ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) );

	preg_match_all( '/@media ' . preg_quote( $query, '/' ) . ' \{((?:[^{}]*\{[^{}]*\})*)[^{}]*\}/', $css, $blocks );

	$rules = array();

	foreach ( $blocks[1] as $block ) {
		preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $block, $found, PREG_SET_ORDER );

		foreach ( $found as $rule ) {
			$rules[] = preg_replace( '/\s+/', ' ', trim( $rule[1] ) . ' { ' . trim( $rule[2] ) . ' }' );
		}
	}

	return $rules;
}

/**
 * Every rule of hub.css whose selector list is exactly the one given, in the order the sheet has them,
 * each with the at-rule it sits in: `rules_for()` runs a rule and its narrower-screen twin together;
 * this tells them apart and keeps their order, which decides which wins at equal weight.
 *
 * @param string $selector The selector list, as the sheet writes it.
 * @return array[] Each rule as `array( at-rule or '', declarations )`, comments left out, whitespace
 *                 run together, such as `array( '@media ( max-width: 899px )', 'gap: 28px;' )`.
 */
function rules_placed( $selector ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) );

	preg_match_all( '/(@[^{}]+)\{|([^{}]+)\{([^{}]*)\}|\}/', $css, $found, PREG_SET_ORDER );

	$rules = array();
	$at    = '';

	foreach ( $found as $token ) {
		if ( '}' === $token[0] ) {
			$at = '';
		} elseif ( '' !== $token[1] ) {
			$at = trim( preg_replace( '/\s+/', ' ', $token[1] ) );
		} elseif ( preg_replace( '/\s+/', ' ', trim( $token[2] ) ) === $selector ) {
			$rules[] = array( $at, trim( preg_replace( '/\s+/', ' ', $token[3] ) ) );
		}
	}

	return $rules;
}

/**
 * Whether a media query of hub.css applies at a window width: every `( min-width: Npx )` and
 * `( max-width: Npx )` in it holds, and it asks nothing else.
 *
 * @param string $at    The at-rule, as `rules_placed()` gives it, or '' for none.
 * @param int    $width The window's width in pixels.
 * @return bool
 */
function media_applies( $at, $width ) {
	if ( '' === $at ) {
		return true;
	}

	$rest = preg_replace( '/\(\s*(min|max)-width:\s*\d+px\s*\)/', '', $at );

	if ( ! preg_match( '/^@media(?:\s+and)*\s*$/', $rest ) ) {
		return false;
	}

	preg_match_all( '/\(\s*(min|max)-width:\s*(\d+)px\s*\)/', $at, $limits, PREG_SET_ORDER );

	foreach ( $limits as $limit ) {
		if ( 'min' === $limit[1] ? $width < (int) $limit[2] : $width > (int) $limit[2] ) {
			return false;
		}
	}

	return true;
}

/**
 * Whether the n-th child of a list is picked by a pseudo-class of the band's stylesheet: `:first-child`,
 * `:last-child` or `:nth-child( odd | even | An+B | N )`.
 *
 * @param string $pseudo The pseudo-class, such as `:nth-child(-n+2)`.
 * @param int    $place  The child's place, counting from 1.
 * @param int    $total  How many children there are.
 * @return bool
 */
function pseudo_picks( $pseudo, $place, $total ) {
	if ( ':first-child' === $pseudo ) {
		return 1 === $place;
	}

	if ( ':last-child' === $pseudo ) {
		return $total === $place;
	}

	if ( ! preg_match( '/^:nth-child\(\s*([^)]+?)\s*\)$/', $pseudo, $nth ) ) {
		return false;
	}

	$expression = str_replace( ' ', '', $nth[1] );

	if ( 'odd' === $expression ) {
		return 1 === $place % 2;
	}

	if ( 'even' === $expression ) {
		return 0 === $place % 2;
	}

	if ( preg_match( '/^([+-]?\d*)n([+-]\d+)?$/', $expression, $part ) ) {
		$step   = in_array( $part[1], array( '', '+' ), true ) ? 1 : ( '-' === $part[1] ? -1 : (int) $part[1] );
		$offset = isset( $part[2] ) ? (int) $part[2] : 0;

		if ( 0 === $step ) {
			return $place === $offset;
		}

		return 0 === ( $place - $offset ) % $step && ( $place - $offset ) / $step >= 0;
	}

	return (string) $place === $expression;
}

/**
 * The band of courses laid out by hub.css at a window width, worked out from the sheet's own rules: the
 * grid's columns, then each card's span from the rules that pick it (`.wpc-hub-courses .wpc-hub-course`
 * with a pseudo-class), the heavier rule winning and, at equal weight, the later one; the cards placed
 * in order, a new row when one does not fit.
 *
 * @param int $width The window's width in pixels.
 * @param int $count How many cards the band holds.
 * @return array{columns: int, rows: int[][]} The columns, and each row as the spans of its cards.
 */
function band_layout( $width, $count ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) );

	preg_match_all( '/(@[^{}]+)\{|([^{}]+)\{([^{}]*)\}|\}/', $css, $tokens, PREG_SET_ORDER );

	$at      = '';
	$columns = 1;
	$spans   = array_fill( 1, $count, array( -1, -1, 1 ) );
	$order   = 0;

	foreach ( $tokens as $token ) {
		if ( '}' === $token[0] ) {
			$at = '';
			continue;
		}

		if ( '' !== $token[1] ) {
			$at = trim( preg_replace( '/\s+/', ' ', $token[1] ) );
			continue;
		}

		++$order;

		$selector = trim( preg_replace( '/\s+/', ' ', $token[2] ) );
		$body     = trim( preg_replace( '/\s+/', ' ', $token[3] ) );

		if ( ! media_applies( $at, $width ) ) {
			continue;
		}

		if ( '.wpc-hub-courses .wpc-hub-courses__grid' === $selector && preg_match( '/grid-template-columns: (?:repeat\( (\d+), |minmax\()/', $body, $grid ) ) {
			$columns = isset( $grid[1] ) && '' !== $grid[1] ? (int) $grid[1] : 1;
			continue;
		}

		if ( ! preg_match( '/^\.wpc-hub-courses \.wpc-hub-course((?::[a-z-]+(?:\([^)]*\))?)+)$/', $selector, $card ) || ! preg_match( '/grid-column: ([^;]+);/', $body, $column ) ) {
			continue;
		}

		preg_match_all( '/:[a-z-]+(?:\([^)]*\))?/', $card[1], $pseudos );

		$weight = 2 * 10 + count( $pseudos[0] );
		$span   = 'span ' === substr( $column[1], 0, 5 ) ? (int) substr( $column[1], 5 ) : ( '1 / -1' === $column[1] ? PHP_INT_MAX : 1 );

		for ( $place = 1; $place <= $count; $place++ ) {
			foreach ( $pseudos[0] as $pseudo ) {
				if ( ! pseudo_picks( $pseudo, $place, $count ) ) {
					continue 2;
				}
			}

			if ( array( $weight, $order ) >= array( $spans[ $place ][0], $spans[ $place ][1] ) ) {
				$spans[ $place ] = array( $weight, $order, $span );
			}
		}
	}

	$rows = array();
	$row  = array();
	$used = 0;

	foreach ( $spans as $span ) {
		$size = min( $span[2], $columns );

		if ( $used + $size > $columns ) {
			$rows[] = $row;
			$row    = array();
			$used   = 0;
		}

		$row[]  = $size;
		$used  += $size;
	}

	$rows[] = $row;

	return array(
		'columns' => $columns,
		'rows'    => $rows,
	);
}

echo "=== The template, and the front page ===\n";

$theme_json = json_decode( theme_file( 'theme.json' ), true );

ck( 'theme.json registers "Education Hub home" for pages, after the two page templates it had',
	array_column( $theme_json['customTemplates'], 'title', 'name' ),
	array( 'page-full-width' => 'Full width page', 'page-documentation' => 'Documentation', 'page-education-hub' => 'Education Hub home' ) );

ck( 'for pages alone',
	end( $theme_json['customTemplates'] )['postTypes'], array( 'page' ) );

$front = explode( "\n", theme_file( 'templates/front-page.html' ) );
$hub   = explode( "\n", theme_file( 'templates/page-education-hub.html' ) );

ck( 'the template holds the front page\'s chrome, line for line: the WordPress.org global header, the site header part, and the global footer',
	array( array_slice( $hub, 0, 4 ), $hub[ count( $hub ) - 2 ] ),
	array( array_slice( $front, 0, 4 ), $front[ count( $front ) - 2 ] ) );

// The main's children in the page's order: each pattern by its slug, and the sign-in block, which the
// template places on its own, by its name.
preg_match_all( '#<!-- wp:(?:pattern \{"slug":"([a-z/-]+)"\}|(wpcredits/[a-z-]+)) /-->#', theme_file( 'templates/page-education-hub.html' ), $children, PREG_SET_ORDER );

$patterns = array_map( static function ( $child ) { return '' !== $child[1] ? $child[1] : $child[2]; }, $children );

ck( 'and between them a main of the front page\'s kind, marked as the hub, holding the hero, the sign-in block, then the doors, programs, pathway and band, in the page\'s order and no post content',
	array( $hub[4], $hub[5] === '<main class="wp-block-group wpc-main wpc-hub"><!-- wp:pattern {"slug":"wpcredits/hub-hero"} /-->', $patterns, false !== strpos( theme_file( 'templates/page-education-hub.html' ), 'post-content' ) ),
	array( '<!-- wp:group {"tagName":"main","className":"wpc-main wpc-hub","layout":{"type":"constrained"}} -->', true, array( 'wpcredits/hub-hero', 'wpcredits/sign-in', 'wpcredits/hub-doors', 'wpcredits/hub-programs', 'wpcredits/hub-pathway', 'wpcredits/hub-cta' ), false ) );

ck( 'the sign-in section is the block itself, placed in the template, not a pattern: a pattern saved into a template in the Site Editor is written out as static blocks, which would freeze one visitor\'s links for everyone',
	array( substr_count( theme_file( 'templates/page-education-hub.html' ), '<!-- wp:wpcredits/sign-in /-->' ), file_exists( WPCREDITS_THEME_DIR . 'patterns/hub-sign-in.php' ) ),
	array( 1, false ) );

/**
 * The front page's template hierarchy, as the hub's filter leaves it, on a site whose Reading
 * settings and pages are as a check says.
 *
 * @param string $show      `show_on_front`.
 * @param int    $front     `page_on_front`.
 * @param array  $templates Page ID => template slug.
 * @return string[]
 */
function front_hierarchy( $show, $front, array $templates ) {
	$GLOBALS['opts']['show_on_front'] = $show;
	$GLOBALS['opts']['page_on_front'] = $front;
	request( '', 0, $templates );

	return apply_filters( 'frontpage_template_hierarchy', array( 'front-page.php' ) );
}

ck( 'the live homepage, page 11 with no template of its own: the hierarchy is untouched, so the saved front-page template keeps drawing it',
	front_hierarchy( 'page', 11, array( 11 => '' ) ), array( 'front-page.php' ) );

ck( 'a homepage set to a page that uses the hub template is drawn by it',
	front_hierarchy( 'page', 2201, array( 2201 => 'page-education-hub', 11 => '' ) ), array( 'page-education-hub.php', 'front-page.php' ) );

// With no page chosen the ID is 0, and core's get_post( 0 ) falls back to the global post, so the slug
// read for 0 could be another post's: the stand-in gives 0 the hub template, to see the guard hold.
ck( 'a homepage of latest posts, or no page chosen (even where the template is stored under ID 0), or a page with another template, is untouched',
	array( front_hierarchy( 'posts', 2201, array( 2201 => 'page-education-hub' ) ), front_hierarchy( 'page', 0, array( 0 => 'page-education-hub', 2201 => 'page-education-hub' ) ), front_hierarchy( 'page', 11, array( 11 => 'page-full-width' ) ) ),
	array( array( 'front-page.php' ), array( 'front-page.php' ), array( 'front-page.php' ) ) );

/**
 * The block template that draws a page, as core picks it: the template hierarchy `get_page_template()`
 * builds (the template the page stores, as its slug with no suffix; then `page-{slug}.php`,
 * `page-{id}.php` and `page.php`), through the `page_template_hierarchy` filter, and the first
 * candidate that is a template of the theme, its suffix taken off as `resolve_block_template()` does.
 *
 * @param string $slug   The page's slug.
 * @param int    $id     The page's ID.
 * @param string $stored The template the page stores, or ''.
 * @return string|null The template's name, such as `page`.
 */
function template_drawing_page( $slug, $id, $stored ) {
	request( 'page', $id, array( $id => $stored ) );

	$hierarchy = array_values( array_filter( array( $stored, 'page-' . $slug . '.php', 'page-' . $id . '.php', 'page.php' ) ) );

	foreach ( apply_filters( 'page_template_hierarchy', $hierarchy ) as $candidate ) {
		$name = preg_replace( '/\.php$/', '', $candidate );

		if ( is_file( WPCREDITS_THEME_DIR . 'templates/' . $name . '.html' ) ) {
			return $name;
		}
	}

	return null;
}

// `page-education-hub` is the hub template's file name and also the file WordPress tries for a page whose
// slug is education-hub, before page.html. Such a page is drawn by the hub only when it stores the template.
ck( 'a page that is only called education-hub, storing no template, is drawn by page.html as any page is, not by the hub; one storing the hub template is drawn by it, whatever its slug; one storing another template keeps that one',
	array(
		template_drawing_page( 'education-hub', 2301, '' ),
		template_drawing_page( 'education-hub', 2301, 'page-education-hub' ),
		template_drawing_page( 'education-hub-preview', 2201, 'page-education-hub' ),
		template_drawing_page( 'education-hub', 2301, 'page-documentation' ),
		template_drawing_page( 'about', 2302, '' ),
	),
	array( 'page', 'page-education-hub', 'page-education-hub', 'page-documentation', 'page' ) );

request( 'page', 2301, array( 2301 => '' ) );

ck( 'the filter takes the one candidate out and leaves the rest as they were: the page that stores the hub template keeps its list whole, the template it stores and the slug\'s candidate both; and a request that is no page, the Site Editor\'s own hierarchy for a custom template, is untouched',
	array(
		apply_filters( 'page_template_hierarchy', array( 'page-education-hub.php', 'page-2301.php', 'page.php' ) ),
		( static function () { request( 'page', 2201, array( 2201 => 'page-education-hub' ) ); return apply_filters( 'page_template_hierarchy', array( 'page-education-hub', 'page-education-hub.php', 'page-2201.php', 'page.php' ) ); } )(),
		( static function () { request( '' ); return apply_filters( 'page_template_hierarchy', array( 'page', 'singular', 'index' ) ); } )(),
	),
	array(
		array( 'page-2301.php', 'page.php' ),
		array( 'page-education-hub', 'page-education-hub.php', 'page-2201.php', 'page.php' ),
		array( 'page', 'singular', 'index' ),
	) );

echo "\n=== The stylesheet ===\n";

foreach ( $GLOBALS['hooks']['init'] as $hooked ) {
	call_user_func( $hooked[0] );
}

ck( 'wpcredits-hub is registered on init before the blocks are, from assets/css/hub.css, after the theme\'s own stylesheet, at the theme\'s version',
	array( $GLOBALS['styles']['wpcredits-hub'] ?? null, $GLOBALS['hooks']['init'][0][1] ?? null ),
	array( array( 'https://site.example/wp-content/themes/wpcredits-theme/assets/css/hub.css', array( 'wpcredits-style' ), '9.9.9' ), 9 ) );

// The editor's request: the theme's own stylesheet is registered on the front end only (functions.php),
// so a dependency on it there would have WordPress drop this stylesheet with a notice.
$GLOBALS['is_admin'] = true;
wpcredits_hub_register_style();

$editor_style = $GLOBALS['styles']['wpcredits-hub'] ?? null;

$GLOBALS['is_admin'] = false;
wpcredits_hub_register_style();

ck( 'in the editor it registers the same address and version with no dependency, since the theme\'s own stylesheet is not registered there; the front end keeps its dependency',
	array( $editor_style, $GLOBALS['styles']['wpcredits-hub'] ?? null ),
	array(
		array( 'https://site.example/wp-content/themes/wpcredits-theme/assets/css/hub.css', array(), '9.9.9' ),
		array( 'https://site.example/wp-content/themes/wpcredits-theme/assets/css/hub.css', array( 'wpcredits-style' ), '9.9.9' ),
	) );

/**
 * Whether the hub's stylesheet is enqueued on a request.
 *
 * @param string $singular  What the request shows.
 * @param int    $id        The queried ID.
 * @param array  $templates Page ID => template slug.
 * @return bool
 */
function hub_enqueued( $singular, $id, array $templates ) {
	request( $singular, $id, $templates );

	foreach ( $GLOBALS['hooks']['wp_enqueue_scripts'] as $hooked ) {
		call_user_func( $hooked[0] );
	}

	return in_array( 'wpcredits-hub', $GLOBALS['enqueued'], true );
}

ck( 'it is enqueued on a page the hub template draws, the preview page or the homepage set to it, and on no other request',
	array(
		hub_enqueued( 'page', 2201, array( 2201 => 'page-education-hub' ) ),
		hub_enqueued( 'page', 11, array( 11 => '' ) ),
		hub_enqueued( 'page', 1606, array( 1606 => 'page-documentation' ) ),
		hub_enqueued( 'post', 2201, array( 2201 => 'page-education-hub' ) ),
		hub_enqueued( '', 0, array() ),
	),
	array( true, false, false, false, false ) );

foreach ( $GLOBALS['hooks']['after_setup_theme'] as $hooked ) {
	call_user_func( $hooked[0] );
}

ck( 'and the editor draws the hub with it too',
	$GLOBALS['editor_styles'], array( 'assets/css/hub.css' ) );

ck( 'functions.php loads inc/hub.php, then inc/hub-content.php, with the theme\'s other files',
	false !== strpos( theme_file( 'functions.php' ), "require_once get_theme_file_path( 'inc/guide.php' );\nrequire_once get_theme_file_path( 'inc/hub.php' );\nrequire_once get_theme_file_path( 'inc/hub-content.php' );" ), true );

// Asked of git itself, which reads every line of .gitattributes in order, so a later line that unsets
// the rule is seen. The directory is named: for a file inside it git says "unspecified", though
// `git archive` leaves the whole directory out.
$git_says = trim( (string) shell_exec( 'git -C ' . escapeshellarg( WPCREDITS_THEME_DIR ) . ' check-attr export-ignore -- bin 2>/dev/null' ) );

ck( 'the installable zip leaves bin/ out: git says the directory is export-ignore, and bin/ stays in the repository',
	array( $git_says, is_file( WPCREDITS_THEME_DIR . 'bin/check-selectors.php' ) ),
	array( 'bin: export-ignore: set', true ) );

$css = theme_file( 'assets/css/hub.css' );

ck( 'below 640px the hub takes the header\'s phone gutter, 20px, in place of the 40px root padding',
	(bool) preg_match( '/@media \( max-width: 640px \) \{\s*\.wpc-main\.wpc-hub \{\s*padding-left: var\( --wpc-chrome-gutter \);\s*padding-right: var\( --wpc-chrome-gutter \);/', $css ), true );

ck( 'and no rule caps a width in characters',
	(bool) preg_match( '/\d\s*ch\b/', preg_replace( '#/\*.*?\*/#s', '', $css ) ), false );

// The owner's answer to the design note's question on the fonts: bundled in the theme, so a page view
// asks no one else for them. Each address is read as the browser reads it, from the sheet's own folder.
$font_faces = array_map(
	static function ( $face ) {
		return array(
			preg_match( '/(?:^|; )font-family: "?([^";]+)"?;/', $face, $family ) ? $family[1] : '',
			preg_match( '/(?:^|; )src: url\( "([^"]+)" \) format\( "woff2" \);/', $face, $src ) ? $src[1] : '',
			preg_match( '/(?:^|; )font-display: ([a-z]+);/', $face, $display ) ? $display[1] : '',
		);
	},
	rules_for( '@font-face' )
);
$font_files = array_map( static function ( $face ) { return realpath( WPCREDITS_THEME_DIR . 'assets/css/' . $face[1] ); }, $font_faces );
$font_named = array_filter( $font_files );
$bundled    = array_map( 'realpath', glob( WPCREDITS_THEME_DIR . 'assets/fonts/*/*.woff2' ) );

sort( $font_named );
sort( $bundled );

ck( 'the two typefaces ship with the theme: four @font-face rules in hub.css, EB Garamond and Inter, each in Latin and Latin Extended, each a woff2 named by an address relative to the sheet, shown in the fallback while it loads; each file there, a woff2 by its first bytes, every bundled file named and nothing else, each family\'s folder holding its SIL Open Font License; and the hub\'s two type tokens name them first, each before a fallback of its kind',
	array(
		$font_faces,
		count( preg_grep( '#^(?:[a-z][a-z0-9+.-]*:|/)#i', array_column( $font_faces, 1 ) ) ),
		array_map( static function ( $file ) { return false !== $file && 0 === strpos( $file, realpath( WPCREDITS_THEME_DIR ) . '/assets/fonts/' ) && 'wOF2' === (string) file_get_contents( $file, false, null, 0, 4 ); }, $font_files ),
		array( count( $bundled ), $font_named === $bundled ),
		array_map( static function ( $folder ) { $license = $folder . '/OFL.txt'; return is_file( $license ) && false !== strpos( (string) file_get_contents( $license ), 'SIL Open Font License, Version 1.1' ); }, array_values( array_unique( array_map( 'dirname', array_filter( $font_files ) ) ) ) ),
		rules_for( ':root' ),
	),
	array(
		array(
			array( 'EB Garamond', '../fonts/eb-garamond/eb-garamond-latin.woff2', 'swap' ),
			array( 'EB Garamond', '../fonts/eb-garamond/eb-garamond-latin-ext.woff2', 'swap' ),
			array( 'Inter', '../fonts/inter/inter-latin.woff2', 'swap' ),
			array( 'Inter', '../fonts/inter/inter-latin-ext.woff2', 'swap' ),
		),
		0,
		array( true, true, true, true ),
		array( 4, true ),
		array( true, true ),
		array( '--wpc-hub-serif: "EB Garamond", Georgia, "Times New Roman", serif; --wpc-hub-sans: Inter, var( --wpc-font );' ),
	) );

// Every stylesheet, PHP file, template and theme.json of the theme, as the zip ships them: bin/ is left
// out of the zip (checked above), and this suite names the two hosts it looks for. The folders git
// ignores and the zip never holds (.superpowers and .worktrees: working notes and other branches'
// copies) are left out too, as bin/check-selectors.php leaves them out.
$font_scanned = array();
$font_asking  = array();

foreach ( new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator( new RecursiveDirectoryIterator( WPCREDITS_THEME_DIR, FilesystemIterator::SKIP_DOTS ), static function ( $file ) { return ! ( $file->isDir() && in_array( $file->getFilename(), array( '.git', '.superpowers', '.worktrees', 'bin', 'node_modules' ), true ) ); } ) ) as $font_file ) {
	if ( ! in_array( strtolower( $font_file->getExtension() ), array( 'css', 'php', 'html', 'json' ), true ) ) {
		continue;
	}

	$font_path      = substr( $font_file->getPathname(), strlen( WPCREDITS_THEME_DIR ) );
	$font_scanned[] = $font_path;

	if ( preg_match( '/fonts\.(?:googleapis|gstatic)\.com/i', (string) file_get_contents( $font_file->getPathname() ) ) ) {
		$font_asking[] = $font_path;
	}
}

ck( 'no page asks a third party for a font: no stylesheet, PHP file, template or theme.json of the theme names fonts.googleapis.com or fonts.gstatic.com, hub.css, style.css and functions.php among those read',
	array( $font_asking, array_values( array_intersect( array( 'assets/css/hub.css', 'style.css', 'functions.php', 'theme.json' ), $font_scanned ) ) ),
	array( array(), array( 'assets/css/hub.css', 'style.css', 'functions.php', 'theme.json' ) ) );

echo "\n=== The content: six programs, seven steps, four levels ===\n";

$programs = wpcredits_hub_programs();
$steps    = wpcredits_hub_steps();
$levels   = wpcredits_hub_levels();

ck( 'six programs in the program team\'s order, each with the anchor its card is linked by',
	array_column( $programs, 'name', 'slug' ),
	array(
		'campus-connect'                  => 'Campus Connect',
		'wordpress-credits'               => 'WordPress Credits',
		'facilitator-training'            => 'Facilitator Training',
		'learn-wordpress'                 => 'Learn WordPress',
		'ai-leaders'                      => 'AI Leaders',
		'wordpress-builder-certification' => 'WordPress Builder Certification',
	) );

/**
 * How many programs an audience filter shows.
 *
 * @param array[] $programs The programs.
 * @param string  $audience An audience, or 'all'.
 * @return int
 */
function shown_for( array $programs, $audience ) {
	return count( array_filter( $programs, static function ( $program ) use ( $audience ) { return 'all' === $audience || in_array( $audience, $program['audiences'], true ); } ) );
}

ck( 'the filters\' counts: all 6, for students 3, for faculty 2, career and credentials 3',
	array( shown_for( $programs, 'all' ), shown_for( $programs, 'student' ), shown_for( $programs, 'faculty' ), shown_for( $programs, 'career' ) ), array( 6, 3, 2, 3 ) );

ck( 'each program\'s audiences, status, step line and time',
	array_map( static function ( $p ) { return implode( ' ', $p['audiences'] ) . ' | ' . $p['status'] . ' | ' . $p['step'] . ' | ' . $p['time']; }, $programs ),
	array(
		'student | live | Step 1 · Attendee | Half or full day',
		'student career | live | Steps 3 and 4 · Contributor, Graduate | 50h or 150h+, about a semester',
		'faculty | live | Step 6 · Community Leader | Self-paced',
		'student faculty | live | Any step | Self-paced',
		'career | live | Step 7 · Credentialed | Cohort-based',
		'career | development | Step 7 · Credentialed | Exam',
	) );

ck( 'the seven steps and the four levels, in order',
	array( array_column( $steps, 'name' ), array_column( $levels, 'name' ) ),
	array( array( 'Attendee', 'Builder', 'Contributor', 'Graduate', 'Specialist', 'Community Leader', 'Credentialed' ), array( 'Participating', 'Partner', 'Established', 'Flagship' ) ) );

ck( 'each step\'s staircase line is its sentence without the full stop, and so is each level\'s',
	array( array_map( static function ( $s ) { return $s['by'] . '.' === $s['earned']; }, $steps ), array_map( static function ( $l ) { return $l['by'] . '.' === $l['reached']; }, $levels ) ),
	array( array_fill( 0, 7, true ), array_fill( 0, 4, true ) ) );

// The sentences the page prints that the checks above do not name: what each program is, and what
// earns each step and reaches each level, as the staircase prints it and, with its full stop, as the
// detail does. The words of the page are the program team's; a change to one is a change to be seen.
$program_what = array(
	'A half or full-day hands-on WordPress workshop on campus. No experience needed, and anyone connected to the institution can organize one.',
	'Mentored work on the WordPress open source project, matched to your field of study. Ends with a presentation and a Foundation certificate.',
	'Free, self-guided courses plus step-by-step facilitation guides with timed agendas and activity sheets, so faculty can teach WordPress themselves.',
	'Free courses, lessons, and online workshops for every skill level, from building a first site to developing blocks.',
	'The first Foundation-backed micro-credential. Piloted in 2026 with students from Illinois and Louisiana.',
	'A certification exam validating the skills to build and run a personal or company website on WordPress.',
);
$step_by      = array(
	'Attend a Campus Connect event',
	'Launch a live site with real content',
	'Get your first contribution accepted',
	'Complete a WordPress Credits track',
	'Complete a second or third program',
	'Facilitate, mentor, organize, or speak',
	'Earn a micro-credential',
);
$level_by     = array(
	'Host one Campus Connect event',
	'Sign a partnership agreement and complete one WordPress Credits group',
	'Run recurring groups with growing student numbers, at least one trained faculty facilitator, and students sitting certification exams',
	'Integrate WordPress into the curriculum, with faculty mentoring across institutions and sustained numbers of certified students',
);

ck( 'what each program is, what earns each step and what reaches each level, word for word: six, seven and four sentences, each step\'s and level\'s again with its full stop in the detail',
	array(
		array_column( $programs, 'what' ),
		array_column( $steps, 'by' ),
		array_column( $steps, 'earned' ),
		array_column( $levels, 'by' ),
		array_column( $levels, 'reached' ),
	),
	array(
		$program_what,
		$step_by,
		array_map( static function ( $sentence ) { return $sentence . '.'; }, $step_by ),
		$level_by,
		array_map( static function ( $sentence ) { return $sentence . '.'; }, $level_by ),
	) );

/**
 * Every benefit of some places, each as `place: text`, the planned ones with ` (planned)`.
 *
 * @param array[] $places The programs, steps or levels.
 * @param string  $key    Where their items are: `gets` or `items`.
 * @param string  $name   Where their names are.
 * @return string[]
 */
function benefits_of( array $places, $key, $name ) {
	$said = array();

	foreach ( $places as $place ) {
		foreach ( $place[ $key ] as $item ) {
			$said[] = $place[ $name ] . ': ' . $item['text'] . ( $item['planned'] ? ' (planned)' : '' );
		}
	}

	return $said;
}

$on_steps  = benefits_of( $steps, 'items', 'name' );
$on_levels = benefits_of( $levels, 'items', 'name' );
$on_cards  = benefits_of( $programs, 'gets', 'name' );
$all       = array_merge( $on_steps, $on_levels, $on_cards );
$planned   = preg_grep( '/ \(planned\)$/', $all );

ck( 'forty-nine benefits, 26 on the steps, 12 on the levels, 11 on the cards: 28 planned and 21 not, the one on the card in development among those, its tag saying it',
	array( count( $all ), count( $on_steps ), count( $on_levels ), count( $on_cards ), count( $planned ), count( $all ) - count( $planned ) ),
	array( 49, 26, 12, 11, 28, 21 ) );

// The Builder step lists only what a Builder earns: a program team member (16 October 2026), "should just be:
// showcase eligibility, badge on .org profile"; a program lead: the domain and hosting are a WordPress.com
// perk, and the page names no host. The theme and plugin library goes with them.
ck( 'every benefit, as the design note\'s 6.11 checked it on 14 October 2026, the Builder step\'s since 16 October',
	$all,
	array(
		'Attendee: Certificate of participation',
		'Attendee: A seeded WordPress.org profile (planned)',
		'Attendee: A profile badge',
		'Builder: Showcase eligibility (planned)',
		'Builder: A profile badge (planned)',
		'Contributor: Contribution badge',
		'Contributor: Make team channels',
		'Contributor: Mentor matching',
		'Contributor: Retention support starts here (planned)',
		'Graduate: Foundation certificate',
		'Graduate: Digital badge',
		'Graduate: Verified hours transcript (planned)',
		'Graduate: Alumni directory listing (planned)',
		'Graduate: WordCamp ticket support (planned)',
		'Specialist: Deeper specialization (planned)',
		'Specialist: Scoped project work (planned)',
		'Specialist: Priority mentor matching (planned)',
		'Specialist: A specialist badge (planned)',
		'Community Leader: Listing in the facilitator and speaker directory (planned)',
		'Community Leader: Discounted or free WordCamp access (planned)',
		'Community Leader: Priority on paid teaching leads (planned)',
		'Community Leader: Organizing support',
		'Community Leader: A profile badge',
		'Credentialed: An Open to Work listing on jobs.wordpress.net',
		'Credentialed: Credentials shown on your profile (planned)',
		'Credentialed: Showcase eligibility (planned)',
		'Participating: A listing on the WordPress education page (planned)',
		'Participating: A logo kit (planned)',
		'Partner: A partner badge for your site',
		'Partner: An Institution Dashboard',
		'Partner: An auto-generated impact report each semester',
		'Established: Named regional support (planned)',
		'Established: A WordCamp speaking slot (planned)',
		'Established: Co-marketing (planned)',
		'Flagship: A case study (planned)',
		'Flagship: A Foundation announcement (planned)',
		'Flagship: Input on the program\'s direction (planned)',
		'Flagship: Event hosting (planned)',
		'Campus Connect: Certificate of participation',
		'WordPress Credits: Certificate',
		'WordPress Credits: Badge',
		'WordPress Credits: Hours transcript (planned)',
		'WordPress Credits: Credit',
		'Facilitator Training: Ready-to-run sessions',
		'Facilitator Training: Facilitator listing (planned)',
		'Learn WordPress: Course completions on your profile',
		'AI Leaders: Micro-credential',
		'AI Leaders: $1,000 for completers (planned)',
		'WordPress Builder Certification: Industry certification',
	) );

ck( 'each step\'s academic recognition',
	array_column( $steps, 'recognition' ),
	array(
		'Recognized co-curricular activity or event credit, where the school offers it.',
		'A portfolio piece that can be assessed within an existing course.',
		'Open source work logged toward course or program requirements.',
		'Credit-bearing.',
		'Credit-bearing as an advanced elective, independent study, practicum, or capstone.',
		'A role the school already records: teaching assistant, peer mentor, or student organization leader.',
		'A stackable credential accepted toward a degree, certificate, or transcript notation.',
	) );

echo "\n=== The links ===\n";

/**
 * Some links, each as `label > address`, the targets resolved as a block resolves them.
 *
 * @param array[] $links The links.
 * @return string[]
 */
function links_said( array $links ) {
	return array_map( static function ( $link ) { return $link['label'] . ' > ' . wpcredits_hub_url( $link['target'] ); }, $links );
}

ck( 'the nine links of the program cards, each where the design note\'s 6.5 says, WordPress Credits\' two track links giving way, since 16 October 2026, to the band of its five courses and the program page',
	links_said( array_merge( ...array_column( $programs, 'links' ) ) ),
	array(
		'Find or host an event > https://events.wordpress.org/campusconnect/',
		'Compare the five courses > #wordpress-credits-courses',
		'About the program > https://wordpress.org/education/credits/',
		'Impact dashboard > https://wordpress.github.io/WPCredits/',
		'Facilitation guides > https://make.wordpress.org/community/handbook/education/wordpress-facilitator-training-program/',
		'Courses > https://learn.wordpress.org/',
		'Browse courses > https://learn.wordpress.org/',
		'Learn more > https://ai-leaders.org/',
		'Learn more > https://wordpresscertifications.com/wordpress-builder-certification/',
	) );

// A program lead (15 Oct 2026, #wpcredits-admins on WordPress Slack), a program team member agreeing: the
// site should list the WordPress Credits courses, so schools and students can pick one; the owner (16 Oct
// 2026): as a band of cards under the index. Each says what its public Learn page says; the hours are Intro
// to Contribution's page's and, for Foundations, the owner's (16 Oct 2026), and the tracks, whose pages
// give none, say who they are for. The Translation track joined them on 19 Oct 2026, a program lead's
// review comment on the section: a fifth card, last, after the Designer track.
ck( 'WordPress Credits alone has courses: five, each its Learn page, its line above the name and its line on what it asks, the Translation track last',
	array(
		array_map( static function ( $program ) { return count( isset( $program['courses'] ) ? $program['courses'] : array() ); }, $programs ),
		array_map( static function ( $course ) { return $course['name'] . ' > ' . $course['url'] . ' : ' . $course['eyebrow'] . ' / ' . $course['line']; }, $programs[1]['courses'] ),
	),
	array(
		array( 0, 5, 0, 0, 0, 0 ),
		array(
			'Intro to Contribution > https://learn.wordpress.org/course/wordpress-credits-intro-to-contribution/ : 50 hours / One guided contribution project: a first look at open source, for a shorter course or an elective.',
			'Foundations > https://learn.wordpress.org/course/wordpress-credits-foundations/ : 150 hours or more / Your own site and a contribution that ships, for an internship, a capstone, or a full semester.',
			'Developer track > https://learn.wordpress.org/course/wordpress-credits-developer-track/ : For developers / WordPress developer training and patch testing practice, toward a contribution that ships.',
			'Designer track > https://learn.wordpress.org/course/wordpress-credits-designer-track/ : For designers / A portfolio, Figma and the Site Editor\'s design tools, and a block pattern of your own.',
			'Translation track > https://learn.wordpress.org/course/wordpress-credits-translation-track/ : For translators / Translation skills and real work with the Polyglots team: translate.wordpress.org and GlotPress, locale glossaries, software strings, and Learn WordPress lessons.',
		),
	) );

ck( 'the seven steps\' next links',
	links_said( array_column( $steps, 'next' ) ),
	array(
		'Find a Campus Connect event > https://events.wordpress.org/campusconnect/',
		'Learn to build a site > https://learn.wordpress.org/',
		'Start WordPress Credits > #program-wordpress-credits',
		'See the courses > #wordpress-credits-courses',
		'Explore programs > #programs',
		'Try Facilitator Training > #program-facilitator-training',
		'Where this leads: WordPress Jobs > https://jobs.wordpress.net/',
	) );

ck( 'the four levels\' next links, the institution application going to the WordPress Credits handbook while the plugin is not there to say where its page is',
	links_said( array_column( $levels, 'next' ) ),
	array(
		'Request a Campus Connect event > https://events.wordpress.org/campusconnect/organize/',
		'Partner as an institution > https://make.wordpress.org/community/handbook/education/credits/',
		'Try Facilitator Training > #program-facilitator-training',
		'Talk to the program team > https://make.wordpress.org/community/handbook/education/credits/',
	) );

// The plugin's two application pages, stood in from here on: published, or not.
if ( ! class_exists( 'WPCPM_Institution_Application' ) ) {
	/** The institution application's page, as the plugin reports it: '' while it is not published. */
	class WPCPM_Institution_Application {
		public static $url = 'https://wordpresseducation.org/apply/';
		public static function page_url() { return self::$url; }
	}

	/** The sponsorship application's page, the same way. */
	class WPCPM_Sponsor_Application {
		public static $url = 'https://wordpresseducation.org/sponsor-application/';
		public static function page_url() { return self::$url; }
	}
}

$published_links = array( wpcredits_hub_url( 'institution_application' ), wpcredits_hub_url( 'sponsor_application' ) );
WPCPM_Institution_Application::$url = '';
WPCPM_Sponsor_Application::$url     = '';
$unpublished_links = array( wpcredits_hub_url( 'institution_application' ), wpcredits_hub_url( 'sponsor_application' ) );
WPCPM_Institution_Application::$url = 'https://wordpresseducation.org/apply/';
WPCPM_Sponsor_Application::$url     = 'https://wordpresseducation.org/sponsor-application/';

ck( 'with the plugin, the site\'s own pages are read when a block renders: the published applications, or the handbook for one that is not published',
	array( $published_links, $unpublished_links ),
	array(
		array( 'https://wordpresseducation.org/apply/', 'https://wordpresseducation.org/sponsor-application/' ),
		array( 'https://make.wordpress.org/community/handbook/education/credits/', 'https://make.wordpress.org/community/handbook/education/credits/' ),
	) );

echo "\n=== The benefit lists ===\n";

ck( 'a list prints its items one to a line, a planned one followed by "(planned)" in its own span after a no-break space, every text escaped',
	wpcredits_hub_items_html( array( wpcredits_hub_item( 'Certificate' ), wpcredits_hub_item( 'Hours <transcript>', true ) ) ),
	'<ul class="wpc-hub-items"><li>Certificate</li><li>Hours &lt;transcript&gt;&nbsp;<span class="wpc-hub-planned">(planned)</span></li></ul>' );

$GLOBALS['marked'] = true;

$texts = array();

foreach ( array( wpcredits_hub_programs(), wpcredits_hub_steps(), wpcredits_hub_levels() ) as $places ) {
	array_walk_recursive(
		$places,
		static function ( $value, $key ) use ( &$texts ) {
			if ( in_array( $key, array( 'name', 'step', 'what', 'time', 'text', 'label', 'eyebrow', 'line', 'by', 'earned', 'reached', 'recognition' ), true ) ) {
				$texts[] = $value;
			}
		}
	);
}

$untranslated = preg_grep( '/^\[[^\[\]]+\]$/', $texts, PREG_GREP_INVERT );
$mark         = wpcredits_hub_items_html( array( wpcredits_hub_item( 'X', true ) ) );

$GLOBALS['marked'] = false;

ck( 'every text is one translated string, seen whole by a translator: 148 of them, 59 on the cards and the band of courses (the band\'s fifteen among them), 61 on the steps and 28 on the levels, and "(planned)" among them',
	array( count( $texts ), array_values( $untranslated ), false !== strpos( $mark, '<span class="wpc-hub-planned">[(planned)]</span>' ) ),
	array( 148, array(), true ) );

// The one-word strings, which a translator meets with nothing around them. Credit, Graduate and Partner
// are words that mean something else as a verb or in another place, so each has a context of its own;
// Badge and Exam, and the one sum, a translators note above them, which the .pot carries.
$hub_source = theme_file( 'inc/hub-content.php' );

// Foundations, the name of a course in the band, reads as organizations out of context, so it has a
// note saying it is a course's name, as each name and each line above a name in the band has.
ck( 'the course band\'s names and the lines above them each carry a translators note, Foundations\' saying it is not organizations',
	array(
		preg_match_all( "#/\* translators: The name of a WordPress Credits course on Learn WordPress[^*]*\*/\n\s*__\( '([^']+)', 'wpcredits-theme' \)#", $hub_source, $named ) ? $named[1] : null,
		preg_match_all( "#/\* translators: The line above a course's name: [^*]+\*/\n\s*__\( '([^']+)', 'wpcredits-theme' \)#", $hub_source, $above ) ? $above[1] : null,
		(bool) preg_match( "#/\* translators: [^*]*not foundations as organizations[^*]*\*/\n\s*__\( 'Foundations', 'wpcredits-theme' \)#", $hub_source ),
	),
	array( array( 'Intro to Contribution', 'Foundations', 'Developer track', 'Designer track', 'Translation track' ), array( '50 hours', '150 hours or more', 'For developers', 'For designers', 'For translators' ), true ) );

ck( 'the one-word strings tell a translator what they are: Credit, Graduate and Partner each a context saying which sense, Badge and Exam a translators note above them, and "$1,000 for completers" a note that the sum is in US dollars',
	array(
		preg_match_all( "/_x\( '(Credit|Graduate|Partner)', '[^']{12,}', 'wpcredits-theme' \)/", $hub_source, $with_context ) ? $with_context[1] : null,
		preg_match_all( "#/\* translators: [^*]{12,}\*/\n\s*(?:wpcredits_hub_item\( |'time'\s+=> )__\( '(Badge|Exam)', 'wpcredits-theme' \)#", $hub_source, $with_note ) ? $with_note[1] : null,
		(bool) preg_match( "#/\* translators: [^*]*US dollars[^*]*\*/\n\s*wpcredits_hub_item\( __\( '\\\$1,000 for completers', 'wpcredits-theme' \), true \)#", $hub_source ),
		preg_match_all( "/__\( '(Credit|Graduate|Partner|Badge|Exam)', 'wpcredits-theme' \)/", $hub_source, $left_plain ) ? $left_plain[1] : array(),
	),
	array( array( 'Credit', 'Graduate', 'Partner' ), array( 'Badge', 'Exam' ), true, array( 'Badge', 'Exam' ) ) );

echo "\n=== The program index, without JavaScript ===\n";

$index_json = json_decode( theme_file( 'blocks/program-index/block.json' ), true );

ck( 'block.json: wpcredits/program-index, once a page, server-rendered, with its view script, its editor preview and the hub\'s stylesheet as its style',
	array( $index_json['name'], $index_json['supports']['multiple'], $index_json['render'], $index_json['viewScript'], $index_json['editorScript'], $index_json['style'], $index_json['textdomain'] ),
	array( 'wpcredits/program-index', false, 'file:./render.php', 'file:./view.js', 'file:./index.js', 'wpcredits-hub', 'wpcredits-theme' ) );

ck( 'its view script needs nothing and carries the theme\'s version; its editor preview needs the editor\'s packages and the server render',
	array( include WPCREDITS_THEME_DIR . 'blocks/program-index/view.asset.php', ( include WPCREDITS_THEME_DIR . 'blocks/program-index/index.asset.php' )['dependencies'] ),
	array( array( 'dependencies' => array(), 'version' => '9.9.9' ), array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render' ) ) );

ck( 'the theme registers the block with its others',
	false !== strpos( theme_file( 'functions.php' ), "register_block_type( get_theme_file_path( 'blocks/program-index' ) );" ), true );

$index = wpcredits_test_block( 'wpcredits/program-index' );
$xp    = dom_of( $index );

ck( 'the wrapper is the block\'s, marked as the index',
	found( $xp, '/html/body/div', 'class' ), array( 'wpc-hub-index wp-block-wpcredits-program-index' ) );

ck( 'the chips are a labeled group printed hidden, for the script to show: four buttons, All pressed',
	array(
		found( $xp, '//div[@role="group"]', 'aria-label' ),
		found( $xp, '//div[@role="group"]', 'hidden' ),
		found( $xp, '//div[@role="group"]/button' ),
		found( $xp, '//div[@role="group"]/button', 'type' ),
		found( $xp, '//div[@role="group"]/button', 'data-filter' ),
		found( $xp, '//div[@role="group"]/button', 'aria-pressed' ),
	),
	array(
		array( 'Filter programs by audience' ),
		array( '' ),
		array( 'All', 'For students', 'For faculty', 'Career and credentials' ),
		array( 'button', 'button', 'button', 'button' ),
		array( 'all', 'student', 'faculty', 'career' ),
		array( 'true', 'false', 'false', 'false' ),
	) );

ck( 'each chip carries what its press announces, and names its audience: all 6, then 3 for students, 2 for faculty and 3 in Career and credentials, of 6',
	found( $xp, '//button', 'data-announce' ),
	array( 'Showing all 6 programs', 'Showing 3 of 6 programs for students', 'Showing 2 of 6 programs for faculty', 'Showing 3 of 6 programs in Career and credentials' ) );

ck( 'no two chips announce the same sentence, though two of them show three programs',
	count( array_unique( found( $xp, '//button', 'data-announce' ) ) ),
	4 );

ck( 'a polite status line, empty, for the script to fill',
	array( found( $xp, '//p[@role="status"]', 'class' ), found( $xp, '//p[@role="status"]' ) ),
	array( array( 'screen-reader-text' ), array( '' ) ) );

ck( 'six cards, every one shown: nothing but the chips carries hidden',
	array( found( $xp, '//article', 'id' ), found( $xp, '//article', 'data-audiences' ), count( $xp->query( '//*[@hidden]' ) ) ),
	array(
		array( 'program-campus-connect', 'program-wordpress-credits', 'program-facilitator-training', 'program-learn-wordpress', 'program-ai-leaders', 'program-wordpress-builder-certification' ),
		array( 'student', 'student career', 'faculty', 'student faculty', 'career', 'career' ),
		1,
	) );

ck( 'each card: its status tag, its step line, its name, what it is, its time and what you get',
	array(
		found( $xp, '//article//span[contains(@class,"wpc-hub-tag")]', 'class' ),
		found( $xp, '//article//span[contains(@class,"wpc-hub-tag")]' ),
		found( $xp, '//article/h3' ),
		found( $xp, '//article//dt' ),
	),
	array(
		array( 'wpc-hub-tag wpc-hub-tag--live', 'wpc-hub-tag wpc-hub-tag--live', 'wpc-hub-tag wpc-hub-tag--live', 'wpc-hub-tag wpc-hub-tag--live', 'wpc-hub-tag wpc-hub-tag--live', 'wpc-hub-tag wpc-hub-tag--development' ),
		array( 'Live', 'Live', 'Live', 'Live', 'Live', 'In development' ),
		array( 'Campus Connect', 'WordPress Credits', 'Facilitator Training', 'Learn WordPress', 'AI Leaders', 'WordPress Builder Certification' ),
		array_merge( ...array_fill( 0, 6, array( 'Time', 'You get' ) ) ),
	) );

ck( 'under the cards, the band of the five courses: a section named by its heading, the programs section\'s head (eyebrow, heading, lede), and a card each, its name a link to its Learn page with a decorative arrow, then its line (drawn above the name), and what it asks',
	array(
		found( $xp, '//div[contains(@class,"wpc-hub-programs")]/following-sibling::section', 'id' ),
		found( $xp, '//section[@id="wordpress-credits-courses"]', 'aria-labelledby' ),
		found( $xp, '//section[@id="wordpress-credits-courses"]/div[@class="wp-block-group wpc-hub-head"]/*' ),
		found( $xp, '//section[@id="wordpress-credits-courses"]/div[@class="wp-block-group wpc-hub-head"]/h3', 'id' ),
		found( $xp, '//section[@id="wordpress-credits-courses"]/ul[@class="wpc-hub-courses__grid"]/li[@class="wpc-hub-course"]/h4/following-sibling::*[1][self::p][@class="wpc-hub-course__meta"]' ),
		found( $xp, '//li[@class="wpc-hub-course"]/h4[@class="wpc-hub-course__name"]/a' ),
		found( $xp, '//li[@class="wpc-hub-course"]/h4/a', 'href' ),
		found( $xp, '//li[@class="wpc-hub-course"]/p[@class="wpc-hub-course__line"]' ),
		count( $xp->query( '//li[@class="wpc-hub-course"]/h4/a/span[@aria-hidden="true"]' ) ),
		count( $xp->query( '//article[@class="wpc-hub-program"]//*[contains(@class,"wpc-hub-course")]' ) ),
	),
	array(
		array( 'wordpress-credits-courses' ),
		array( 'wordpress-credits-courses-title' ),
		array( 'Choosing a course', 'Which WordPress Credits course?', 'What each asks of a student, so a school and its students can choose. Access to each course comes through the student\'s institution.' ),
		array( 'wordpress-credits-courses-title' ),
		array( '50 hours', '150 hours or more', 'For developers', 'For designers', 'For translators' ),
		array( 'Intro to Contribution →', 'Foundations →', 'Developer track →', 'Designer track →', 'Translation track →' ),
		array( 'https://learn.wordpress.org/course/wordpress-credits-intro-to-contribution/', 'https://learn.wordpress.org/course/wordpress-credits-foundations/', 'https://learn.wordpress.org/course/wordpress-credits-developer-track/', 'https://learn.wordpress.org/course/wordpress-credits-designer-track/', 'https://learn.wordpress.org/course/wordpress-credits-translation-track/' ),
		array( 'One guided contribution project: a first look at open source, for a shorter course or an elective.', 'Your own site and a contribution that ships, for an internship, a capstone, or a full semester.', 'WordPress developer training and patch testing practice, toward a contribution that ships.', 'A portfolio, Figma and the Site Editor\'s design tools, and a block pattern of your own.', 'Translation skills and real work with the Polyglots team: translate.wordpress.org and GlotPress, locale glossaries, software strings, and Learn WordPress lessons.' ),
		5,
		0,
	) );

ck( 'what you get is a list, one item to a line, the three planned ones marked on their own line',
	array( found( $xp, '//article[@id="program-wordpress-credits"]//ul[@class="wpc-hub-items"]/li' ), found( $xp, '//article//span[@class="wpc-hub-planned"]/..' ) ),
	array( array( 'Certificate', 'Badge', 'Hours transcript (planned)', 'Credit' ), array( 'Hours transcript (planned)', 'Facilitator listing (planned)', '$1,000 for completers (planned)' ) ) );

ck( 'the mark is kept on its item\'s line by a no-break space',
	false !== strpos( $index, '<li>Hours transcript&nbsp;<span class="wpc-hub-planned">(planned)</span></li>' ), true );

ck( 'the band sits 56px under the cards, as the field-of-study flow sits under it, and lands below the sticky header; its cards the program cards\' white boxes on the card hairline with 2px corners, one column, two from 640px and six from 900px, the five cards\' layout; its line an eyebrow drawn above the name it follows, the name in the serif at 26px, regular weight, in the brand, what it asks ink-70 at 14px; two classes deep',
	array(
		rules_for( '.wpc-hub-index .wpc-hub-courses' ),
		rules_placed( '.wpc-hub-courses .wpc-hub-courses__grid' ),
		rules_for( '.wpc-hub-courses .wpc-hub-course' ),
		rules_for( '.wpc-hub-course .wpc-hub-course__meta' ),
		rules_for( '.wpc-hub-course .wpc-hub-course__name' ),
		rules_for( '.wpc-hub-course .wpc-hub-course__name a' ),
		rules_for( '.wpc-hub-course .wpc-hub-course__line' ),
	),
	array(
		array( 'margin-top: 56px; scroll-margin-top: var( --wpc-landing-offset );' ),
		array(
			array( '', 'display: grid; gap: 16px; grid-template-columns: minmax( 0, 1fr ); list-style: none; margin: 0; padding: 0;' ),
			array( '@media ( min-width: 640px )', 'grid-template-columns: repeat( 2, minmax( 0, 1fr ) );' ),
			array( '@media ( min-width: 900px )', 'grid-template-columns: repeat( 6, minmax( 0, 1fr ) );' ),
		),
		array( 'background: var( --wpc-surface ); border: 1px solid var( --wpc-line-card ); border-radius: var( --wpc-radius-control ); display: flex; flex-direction: column; gap: 8px; margin: 0; min-width: 0; padding: 24px;' ),
		array( 'color: var( --wpc-ink-60 ); font: 500 12px/18px var( --wpc-font ); letter-spacing: .06em; margin: 0; order: -1; text-transform: uppercase;' ),
		array( 'font-family: var( --wpc-hub-serif ); font-size: 26px; font-weight: 400; line-height: 1.15; margin: 0;' ),
		array( 'color: var( --wpc-brand ); text-decoration: none;' ),
		array( 'color: var( --wpc-ink-70 ); font-size: 14px; line-height: 1.55; margin: 0;' ),
	) );

// Five cards in a grid of four or two columns leave one alone on a row (the owner, 19 Oct 2026, adding the
// Translation track). From 900px the grid has six columns: the two courses by length, Intro to Contribution
// and Foundations, span three each on the first row, and the three tracks, Developer, Designer and
// Translation, two each on the second. From 640px to 899px it keeps two columns and the odd card out, the
// last, spans both. On a phone it is one column. The gaps are the band's 16px at every width, rows and columns.
ck( 'the five cards\' layout is in the sheet: from 900px the first two cards span three of six columns and the rest two, and from 640px to 899px the odd card out, the last, spans both of two; two classes deep, declaring nothing but the column, so the grid\'s one 16px gap stays the only gap',
	array(
		rules_placed( '.wpc-hub-courses .wpc-hub-course:nth-child( -n+2 )' ),
		rules_placed( '.wpc-hub-courses .wpc-hub-course:nth-child( n+3 )' ),
		rules_placed( '.wpc-hub-courses .wpc-hub-course:last-child:nth-child( odd )' ),
	),
	array(
		array( array( '@media ( min-width: 900px )', 'grid-column: span 3;' ) ),
		array( array( '@media ( min-width: 900px )', 'grid-column: span 2;' ) ),
		array( array( '@media ( min-width: 640px ) and ( max-width: 899px )', 'grid-column: 1 / -1;' ) ),
	) );

// What the sheet lays out, worked out from its rules at each width the band changes at and beside it: the
// columns and each row's cards by their spans. No row is left short: every row's spans fill the columns, so
// no card sits alone with an empty cell beside it; a card alone in a row fills it (a phone's one column, the
// last card across two).
$band_widths = array( 320, 390, 639, 640, 768, 899, 900, 1024, 1280 );
$band_seen   = array();
$band_short  = array();

foreach ( $band_widths as $band_width ) {
	$layout                  = band_layout( $band_width, 5 );
	$band_seen[ $band_width ] = $layout['columns'] . ' col: ' . implode( ' | ', array_map( static function ( $row ) { return implode( '+', $row ); }, $layout['rows'] ) );

	foreach ( $layout['rows'] as $band_row ) {
		if ( array_sum( $band_row ) !== $layout['columns'] ) {
			$band_short[] = $band_width . 'px: ' . implode( '+', $band_row ) . ' of ' . $layout['columns'];
		}
	}
}

ck( 'the five cards fill every row at every width: one column up to 639px, two from 640px to 899px with the last card across both, six from 900px as three-three over two-two-two; no row has an empty cell',
	array( $band_seen, $band_short ),
	array(
		array(
			320  => '1 col: 1 | 1 | 1 | 1 | 1',
			390  => '1 col: 1 | 1 | 1 | 1 | 1',
			639  => '1 col: 1 | 1 | 1 | 1 | 1',
			640  => '2 col: 1+1 | 1+1 | 2',
			768  => '2 col: 1+1 | 1+1 | 2',
			899  => '2 col: 1+1 | 1+1 | 2',
			900  => '6 col: 3+3 | 2+2+2',
			1024 => '6 col: 3+3 | 2+2+2',
			1280 => '6 col: 3+3 | 2+2+2',
		),
		array(),
	) );

// The layout check would pass a layout it cannot read, so it is shown to see a lone card: four cards in the
// six-column grid, the old grid's four across in a sheet that has not changed it, and six cards under 900px.
ck( 'and the layout check does see a card left alone: a sixth card in the six columns leaves one alone on a third row, four cards leave the second row short',
	array( band_layout( 1280, 6 )['rows'], band_layout( 1280, 4 )['rows'] ),
	array( array( array( 3, 3 ), array( 2, 2, 2 ), array( 2 ) ), array( array( 3, 3 ), array( 2, 2 ) ) ) );

// The theme is mirrored publicly, so its comments and texts name roles, never people: the comment above the
// band's list in hub-content.php says who asked, a program lead and a program team member and the owner.
// The names that have turned up are held below as the md5 of the lower-cased word, so that this file names
// no one either. Every text file of the theme is read, word by word; the author's name in style.css's
// Author line, and the template that copies it, are the two places an author is named on purpose.
$courses_comment = preg_match( '#((?:\t+//[^\n]*\n)+)\t+\'courses\'\s+=> array\(#', $hub_source, $above_courses ) ? $above_courses[1] : '';
$people_hashes   = array( '049feab0422c24c75765d182b269f646', 'cc74decfb3ae7964064594151cc0be99', '440635083d08df58d672201e89e44fc6', '1660885848050e9ca9746ddd54e2de25', 'cf9cd8b146b0ff81b8c46765cb503063', '6643f6beb8608653c73cdad8221dce45', 'c37976221ab2fefc5578fb38f7003ef9', '5c812c86ad88774c7be87c93191a562f' );
$people_found    = array();
$people_scanned  = 0;
$people_files    = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( rtrim( WPCREDITS_THEME_DIR, '/' ), FilesystemIterator::SKIP_DOTS ),
		static function ( $file ) {
			return ! in_array( $file->getFilename(), array( '.git', '.superpowers', '.worktrees', 'node_modules', 'fonts', 'languages' ), true );
		}
	)
);

foreach ( $people_files as $people_file ) {
	if ( ! $people_file->isFile() || ! preg_match( '/\.(?:php|js|css|html|json|txt|sh|xml|dist|md)$/', $people_file->getFilename() ) ) {
		continue;
	}

	++$people_scanned;

	$people_path = substr( $people_file->getPathname(), strlen( rtrim( WPCREDITS_THEME_DIR, '/' ) ) + 1 );

	foreach ( file( $people_file->getPathname() ) as $people_line_no => $people_line ) {
		if ( 'style.css' === $people_path && 0 === strpos( $people_line, 'Author:' ) ) {
			continue;
		}

		preg_match_all( '/\b[A-Z][a-z]+\b/', $people_line, $people_words );

		foreach ( $people_words[0] as $people_word ) {
			if ( in_array( md5( strtolower( $people_word ) ), $people_hashes, true ) ) {
				$people_found[] = $people_path . ':' . ( $people_line_no + 1 );
			}
		}
	}
}

ck( 'the comment above the band\'s courses names roles, a program lead, a program team member and the owner; and no file of the theme names a person (the author line of style.css and the template that copies it apart): the files read include this one and the readme',
	array(
		(bool) preg_match( '/a program lead/', $courses_comment ),
		(bool) preg_match( '/a program team member/', $courses_comment ),
		(bool) preg_match( '/the owner/', $courses_comment ),
		$people_found,
		$people_scanned > 60 && file_exists( WPCREDITS_THEME_DIR . 'readme.txt' ),
	),
	array( true, true, true, array(), true ) );

ck( 'the nine links, each its label and a decorative arrow a screen reader skips, opening in the same tab',
	array( found( $xp, '//article//p[@class="wpc-hub-program__links"]/a', 'href' ), found( $xp, '//article//a/span', 'aria-hidden' ), count( $xp->query( '//a[@target]' ) ) ),
	array(
		array(
			'https://events.wordpress.org/campusconnect/',
			'#wordpress-credits-courses',
			'https://wordpress.org/education/credits/',
			'https://wordpress.github.io/WPCredits/',
			'https://make.wordpress.org/community/handbook/education/wordpress-facilitator-training-program/',
			'https://learn.wordpress.org/',
			'https://learn.wordpress.org/',
			'https://ai-leaders.org/',
			'https://wordpresscertifications.com/wordpress-builder-certification/',
		),
		array_fill( 0, 9, 'true' ),
		0,
	) );

// A rule's declarations outside any at-rule: what the toggles look like, forced colors aside (the check
// after this one is theirs).
$plain_rules = static function ( $selector ) {
	return array_values( array_column( array_filter( rules_placed( $selector ), static function ( $rule ) { return '' === $rule[0]; } ), 1 ) );
};

ck( 'the toggles, the chips and the pathway\'s switch alike: white, the control border, 2px corners, ink-70 at 14px and 36px tall; the switch pressed, the brand tint with an inset brand ring and weight 600; a chip pressed, filled in ink with white words (16.67:1) and no ring, at the same weight, one class deeper',
	array(
		$plain_rules( '.wpc-hub-chip, .wpc-hub-switch__option' ),
		$plain_rules( '.wpc-hub-chip[aria-pressed="true"], .wpc-hub-switch__option[aria-pressed="true"]' ),
		$plain_rules( '.wpc-hub-chips .wpc-hub-chip[aria-pressed="true"]' ),
		contrast( color_of( 'base' ), color_of( 'ink' ) ),
	),
	array(
		array( 'align-items: center; background: var( --wpc-surface ); border: 1px solid var( --wpc-control-border ); border-radius: var( --wpc-radius-control ); color: var( --wpc-ink-70 ); cursor: pointer; display: inline-flex; font: 400 14px/20px var( --wpc-font ); min-height: 36px; padding: 7px 14px;' ),
		array( 'background: var( --wpc-brand-tint ); border-color: var( --wpc-brand ); box-shadow: inset 0 0 0 1px var( --wpc-brand ); color: var( --wpc-ink ); font-weight: 600;' ),
		array( 'background: var( --wpc-ink ); border-color: var( --wpc-ink ); box-shadow: none; color: var( --wpc-surface );' ),
		'16.67',
	) );

// How deep a selector is, as specificity counts it among classes: its classes, attributes and
// pseudo-classes. The hub writes no ids, and no `:is()` or `:not()` in these.
$depth_of = static function ( $selector ) {
	return preg_match_all( '/\.[a-z][\w-]*|\[[^\]]+\]|(?<!:):(?!:)[a-z-]+/', $selector );
};

$toggle_sheet = preg_replace( '/\s+/', ' ', preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) ) );

/**
 * Whether forced colors draw a pressed toggle in the system's highlight: some selector of a forced-colors
 * rule that sets the highlight ends with the toggle's own, and is deeper than the rule that colors it
 * pressed, or as deep and later in the sheet. A rule that sets `forced-color-adjust: none` takes the
 * system's forcing away, so whichever colors win the cascade are drawn as written.
 *
 * @param string $toggle The pressed toggle's own selector, such as `.wpc-hub-chip[aria-pressed="true"]`.
 * @param string $fill   The selector list of the rule that colors it pressed, as the sheet writes it.
 * @param string $in     That rule's selector for this toggle.
 * @return bool
 */
$highlight_wins = static function ( $toggle, $fill, $in ) use ( $depth_of, $toggle_sheet ) {
	$fill_at = strpos( $toggle_sheet, $fill . ' {' );

	preg_match_all( '/@media \( forced-colors: active \) \{((?:[^{}]*\{[^{}]*\})*)[^{}]*\}/', $toggle_sheet, $blocks, PREG_OFFSET_CAPTURE );

	foreach ( $blocks[1] as $block ) {
		preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $block[0], $rules, PREG_SET_ORDER );

		foreach ( $rules as $rule ) {
			if ( false === strpos( $rule[2], 'background: Highlight;' ) ) {
				continue;
			}

			foreach ( array_map( 'trim', explode( ',', $rule[1] ) ) as $selector ) {
				$tail = substr( $selector, -strlen( $toggle ) );

				if ( $tail === $toggle && ( $depth_of( $selector ) > $depth_of( $in ) || ( $depth_of( $selector ) === $depth_of( $in ) && false !== $fill_at && $block[1] > $fill_at ) ) ) {
					return true;
				}
			}
		}
	}

	return false;
};

ck( 'in forced colors the switch pressed and a chip pressed are drawn in the system\'s highlight, as the pressed step is, not in their own colors: the highlight names each at least as deep as the rule that fills it, and later in the sheet where it ties; a chip\'s ink fill is three deep, so a highlight two deep loses to it, and the forced-color-adjust: none beside it keeps the ink, which on a dark system theme leaves the chip pressed with no edge while the others keep theirs',
	array(
		'the switch' => $highlight_wins( '.wpc-hub-switch__option[aria-pressed="true"]', '.wpc-hub-chip[aria-pressed="true"], .wpc-hub-switch__option[aria-pressed="true"]', '.wpc-hub-switch__option[aria-pressed="true"]' ),
		'a chip'     => $highlight_wins( '.wpc-hub-chip[aria-pressed="true"]', '.wpc-hub-chips .wpc-hub-chip[aria-pressed="true"]', '.wpc-hub-chips .wpc-hub-chip[aria-pressed="true"]' ),
	),
	array( 'the switch' => true, 'a chip' => true ) );

ck( 'the status tags: Live in green on the green tint, In development in amber on the amber tint with the amber border',
	array( rules_for( '.wpc-hub-tag--live' ), rules_for( '.wpc-hub-tag--development' ) ),
	array( array( 'background: var( --wpc-green-tint ); color: var( --wpc-green );' ), array( 'background: var( --wpc-amber-tint ); border-color: var( --wpc-amber-border ); color: var( --wpc-amber );' ) ) );

ck( 'and the contrast of each, from theme.json: green on its tint 4.86, amber on its tint 4.90 (on the amber chip it would be 4.30), the control border 3.32 and the planned mark, ink-60, 5.45 on white',
	array( contrast( color_of( 'status.green' ), color_of( 'status.green-tint' ) ), contrast( color_of( 'status.amber' ), color_of( 'status.amber-tint' ) ), contrast( color_of( 'status.amber' ), color_of( 'status.amber-chip' ) ), contrast( color_of( 'line.control' ), color_of( 'base' ) ), contrast( color_of( 'ink-60' ), color_of( 'base' ) ) ),
	array( '4.86', '4.90', '4.30', '3.32', '5.45' ) );

ck( 'a card, a chip, a ladder or a detail the script hides stays out of the layout, whatever display its rule gives it',
	rules_for( '.wpc-hub-index [hidden], .wpc-hub-pathway [hidden]' ), array( 'display: none !important;' ) );

$GLOBALS['marked'] = true;
$marked_index      = wpcredits_test_block( 'wpcredits/program-index' );
$GLOBALS['marked'] = false;

ck( 'every word the index prints, and every label and announcement, is a translated string',
	unmarked( $marked_index ), array() );

echo "\n=== The pathway, without JavaScript ===\n";

$pathway_json = json_decode( theme_file( 'blocks/pathway/block.json' ), true );

ck( 'block.json: wpcredits/pathway, once a page, server-rendered, with its view script, its editor preview and the hub\'s stylesheet as its style',
	array( $pathway_json['name'], $pathway_json['supports']['multiple'], $pathway_json['render'], $pathway_json['viewScript'], $pathway_json['editorScript'], $pathway_json['style'] ),
	array( 'wpcredits/pathway', false, 'file:./render.php', 'file:./view.js', 'file:./index.js', 'wpcredits-hub' ) );

ck( 'its two asset files, as the program index\'s, and the theme registers it',
	array( include WPCREDITS_THEME_DIR . 'blocks/pathway/view.asset.php', ( include WPCREDITS_THEME_DIR . 'blocks/pathway/index.asset.php' )['dependencies'], false !== strpos( theme_file( 'functions.php' ), "register_block_type( get_theme_file_path( 'blocks/pathway' ) );" ) ),
	array( array( 'dependencies' => array(), 'version' => '9.9.9' ), array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render' ), true ) );

$pathway = wpcredits_test_block( 'wpcredits/pathway' );
$px      = dom_of( $pathway );

ck( 'the switch is a group labeled Choose a pathway, printed hidden: For students pressed, For institutions not, each naming the ladder it shows',
	array( found( $px, '//div[@role="group"]', 'aria-label' ), count( $px->query( '//div[@role="group"][@hidden]' ) ), found( $px, '//div[@role="group"]/button' ), found( $px, '//div[@role="group"]/button', 'aria-pressed' ), found( $px, '//div[@role="group"]/button', 'aria-controls' ), found( $px, '//div[@role="group"]/button', 'type' ) ),
	array( array( 'Choose a pathway' ), 1, array( 'For students', 'For institutions' ), array( 'true', 'false' ), array( 'wpc-hub-ladder-students', 'wpc-hub-ladder-institutions' ), array( 'button', 'button' ) ) );

ck( 'a polite status line, empty, for the script to fill, and nothing hides it from a screen reader',
	array( found( $px, '//p[@role="status"]', 'class' ), found( $px, '//p[@role="status"]' ), count( $px->query( '//*[@aria-hidden][.//p[@role="status"] or self::p[@role="status"]]' ) ) ),
	array( array( 'screen-reader-text' ), array( '' ), 0 ) );

ck( 'both ladders, each a section named by its heading, opening on step 3 and on level 2',
	array( found( $px, '//section', 'id' ), found( $px, '//section', 'aria-labelledby' ), found( $px, '//section/h2', 'id' ), found( $px, '//section/h2' ), found( $px, '//section', 'data-start' ) ),
	array(
		array( 'wpc-hub-ladder-students', 'wpc-hub-ladder-institutions' ),
		array( 'wpc-hub-ladder-students-title', 'wpc-hub-ladder-institutions-title' ),
		array( 'wpc-hub-ladder-students-title', 'wpc-hub-ladder-institutions-title' ),
		array( 'Seven steps from first workshop to first job', 'Four partner levels, measured on student outcomes' ),
		array( '3', '2' ),
	) );

ck( 'each lede, and right under it, before the staircase, the line that says a step or a level opens its detail, printed hidden for the script to show',
	array(
		found( $px, '//p[@class="wpc-hub-ladder__lede"]' ),
		found( $px, '//p[@class="wpc-hub-ladder__hint"]' ),
		count( $px->query( '//p[@class="wpc-hub-ladder__hint"][@hidden]' ) ),
		count( $px->query( '//p[@class="wpc-hub-ladder__lede"]/following-sibling::*[1][self::p][@class="wpc-hub-ladder__hint"]/following-sibling::*[1][self::ol]' ) ),
	),
	array(
		array(
			'Each step earns a badge on your WordPress.org profile, the same profile employers look at. Enter at whichever step fits.',
			'Levels grow with the number of students you put through the programs and get certified, not the number of groups you run. Partners appear in an activity directory sorted by recent activity. We don\'t rank schools.',
		),
		array( 'Select a step to see what it unlocks.', 'Select a level to see what it brings.' ),
		2,
		2,
	) );

ck( 'the staircases: seven steps and four levels, named, each a link to its detail, described by its line, its rise from 0 to 1',
	array(
		found( $px, '//ol', 'aria-label' ),
		found( $px, '//ol', 'style' ),
		found( $px, '//ol/li', 'style' ),
		found( $px, '//ol/li/a' ),
		found( $px, '//ol/li/a', 'href' ),
		found( $px, '//ol/li/span[@class="wpc-hub-stair__by"]', 'id' ) === found( $px, '//ol/li/a', 'aria-describedby' ),
	),
	array(
		array( 'Steps for students', 'Levels for institutions' ),
		array( '--wpc-count:7', '--wpc-count:4' ),
		array( '--wpc-t:0', '--wpc-t:0.1667', '--wpc-t:0.3333', '--wpc-t:0.5', '--wpc-t:0.6667', '--wpc-t:0.8333', '--wpc-t:1', '--wpc-t:0', '--wpc-t:0.3333', '--wpc-t:0.6667', '--wpc-t:1' ),
		array( 'Step 1 Attendee', 'Step 2 Builder', 'Step 3 Contributor', 'Step 4 Graduate', 'Step 5 Specialist', 'Step 6 Community Leader', 'Step 7 Credentialed', 'Level 1 Participating', 'Level 2 Partner', 'Level 3 Established', 'Level 4 Flagship' ),
		array( '#wpc-hub-step-students-1', '#wpc-hub-step-students-2', '#wpc-hub-step-students-3', '#wpc-hub-step-students-4', '#wpc-hub-step-students-5', '#wpc-hub-step-students-6', '#wpc-hub-step-students-7', '#wpc-hub-step-institutions-1', '#wpc-hub-step-institutions-2', '#wpc-hub-step-institutions-3', '#wpc-hub-step-institutions-4' ),
		true,
	) );

// The owner (15 Oct 2026, with a program team member): make it plain that a step opens more. Every step ends with a
// cue, outside its link so the link (a button once the script has run) keeps its name, and hidden from
// screen readers, which hear the link and the line it is described by.
ck( 'every step and level ends with a cue that it opens its detail, outside its link and kept from screen readers: what it unlocks or brings, and "Shown below" for the one pressed',
	array(
		count( $px->query( '//ol/li/*[last()][self::span][@class="wpc-hub-stair__cue"][@aria-hidden="true"]' ) ),
		found( $px, '//span[@class="wpc-hub-stair__cue"]/span[@class="wpc-hub-stair__see"]' ),
		array_values( array_unique( found( $px, '//span[@class="wpc-hub-stair__cue"]/span[@class="wpc-hub-stair__shown"]' ) ) ),
		count( $px->query( '//ol/li/a//span[@class="wpc-hub-stair__cue"]' ) ),
	),
	array( 11, array_merge( array_fill( 0, 7, 'See what it unlocks' ), array_fill( 0, 4, 'See what it brings' ) ), array( 'Shown below' ), 0 ) );

ck( 'each step carries what its press announces',
	found( $px, '//ol/li/a', 'data-announce' ),
	array( 'Step 1 of 7: Attendee', 'Step 2 of 7: Builder', 'Step 3 of 7: Contributor', 'Step 4 of 7: Graduate', 'Step 5 of 7: Specialist', 'Step 6 of 7: Community Leader', 'Step 7 of 7: Credentialed', 'Level 1 of 4: Participating', 'Level 2 of 4: Partner', 'Level 3 of 4: Established', 'Level 4 of 4: Flagship' ) );

ck( 'eleven details, every one shown, each with the address its step links to, its count, its name, and its columns',
	array(
		found( $px, '//article', 'id' ) === array_map( static function ( $href ) { return substr( $href, 1 ); }, found( $px, '//ol/li/a', 'href' ) ),
		found( $px, '//article/p[@class="wpc-hub-detail__count"]' ),
		found( $px, '//article/h3' ),
		found( $px, '//section[@id="wpc-hub-ladder-students"]//article[1]//dt' ),
		found( $px, '//section[@id="wpc-hub-ladder-institutions"]//article[1]//dt' ),
		count( $px->query( '//*[@hidden]' ) ),
	),
	array(
		true,
		array( 'Step 1 of 7', 'Step 2 of 7', 'Step 3 of 7', 'Step 4 of 7', 'Step 5 of 7', 'Step 6 of 7', 'Step 7 of 7', 'Level 1 of 4', 'Level 2 of 4', 'Level 3 of 4', 'Level 4 of 4' ),
		array( 'Attendee', 'Builder', 'Contributor', 'Graduate', 'Specialist', 'Community Leader', 'Credentialed', 'Participating', 'Partner', 'Established', 'Flagship' ),
		array( 'Earned by', 'Unlocks', 'Academic recognition' ),
		array( 'Reached by', 'Gets' ),
		3,
	) );

ck( 'a detail: what earns the step, what it unlocks as a list with its planned items marked, and the academic recognition',
	array( found( $px, '//article[@id="wpc-hub-step-students-1"]//dd[not(ul)]' ), found( $px, '//article[@id="wpc-hub-step-students-1"]//dd/ul/li' ) ),
	array( array( 'Attend a Campus Connect event.', 'Recognized co-curricular activity or event credit, where the school offers it.' ), array( 'Certificate of participation', 'A seeded WordPress.org profile (planned)', 'A profile badge' ) ) );

// The owner (17 October 2026): the footnote under each ladder goes; each mark says it itself.
ck( 'every planned item of the steps and the levels is marked, 25 of them, each mark saying it itself: no line under a ladder explains the mark any more',
	array( count( $px->query( '//article//span[@class="wpc-hub-planned"]' ) ), found( $px, '//p[@class="wpc-hub-ladder__key"]' ), found( $px, '//section[@class="wpc-hub-ladder"]/*[last()]', 'class' ) ),
	array( 25, array(), array( 'wpc-hub-details', 'wpc-hub-details' ) ) );

ck( 'what each leads to, the last of each ladder saying where it ends',
	found( $px, '//article//span[@class="wpc-hub-detail__leads"]' ),
	array( 'Leads to step 2: Builder', 'Leads to step 3: Contributor', 'Leads to step 4: Graduate', 'Leads to step 5: Specialist', 'Leads to step 6: Community Leader', 'Leads to step 7: Credentialed', 'Leads to work', 'Leads to level 2: Partner', 'Leads to level 3: Established', 'Leads to level 4: Flagship', 'The top level' ) );

ck( 'and each next link is the core outline button\'s markup, where the design note\'s 6.7 says, the institution application read from the plugin',
	array( found( $px, '//article//span[contains(@class,"wp-block-button")]', 'class' ), found( $px, '//article//a[contains(@class,"wp-block-button__link")]', 'href' ) ),
	array(
		array_fill( 0, 11, 'wp-block-button is-style-outline wpc-button--large' ),
		array(
			'https://events.wordpress.org/campusconnect/',
			'https://learn.wordpress.org/',
			'#program-wordpress-credits',
			'#wordpress-credits-courses',
			'#programs',
			'#program-facilitator-training',
			'https://jobs.wordpress.net/',
			'https://events.wordpress.org/campusconnect/organize/',
			'https://wordpresseducation.org/apply/',
			'#program-facilitator-training',
			'https://wordpresseducation.org/apply/',
		),
	) );

$GLOBALS['marked'] = true;
$marked_pathway    = wpcredits_test_block( 'wpcredits/pathway' );
$GLOBALS['marked'] = false;

ck( 'every word the pathway prints, and every label and announcement, is a translated string',
	unmarked( $marked_pathway ), array() );

ck( 'the second ladder sits 48px below the first only where the first is shown, as it is without JavaScript; with the script showing one ladder alone, nothing sits above the second',
	array( rules_for( '.wpc-hub-ladder:not( [hidden] ) + .wpc-hub-ladder' ), rules_for( '.wpc-hub-ladder + .wpc-hub-ladder' ) ),
	array( array( 'margin-top: 48px;' ), array() ) );

ck( 'the staircase\'s count and rise are declared in the sheet, as every token the theme reads is, and a step is a white card with 2px corners on a 1px hairline, the strong line, drawn inset so the step keeps its width; the same white at every rise, which only its height shows',
	array( rules_for( '.wpc-hub-stairs' )[0], rules_for( '.wpc-hub-stair' )[0] ),
	array(
		'--wpc-count: 7; display: grid; gap: 8px; list-style: none; margin: 0; padding: 0;',
		'--wpc-t: 0; background: var( --wpc-surface ); border-radius: var( --wpc-radius-control ); box-shadow: inset 0 0 0 1px var( --wpc-line-strong ); display: flex; flex-direction: column; gap: 6px; padding: 14px; position: relative;',
	) );

// The bar's width at each ladder's first and last step, from the markup's own count and rise.
$bar_ends = array();

foreach ( array( 1, 2 ) as $ladder ) {
	$count      = (int) preg_replace( '/\D/', '', found( $px, '//ol', 'style' )[ $ladder - 1 ] );
	$widths     = array_map(
		static function ( $style ) use ( $count ) {
			return round( 100 / $count * ( 1 + ( $count - 1 ) * (float) substr( $style, strlen( '--wpc-t:' ) ) ), 2 );
		},
		found( $px, '(//ol)[' . $ladder . ']/li', 'style' )
	);
	$bar_ends[] = array( $widths[0], end( $widths ) );
}

$bar_width = 'calc( 100% / var( --wpc-count ) * ( 1 + ( var( --wpc-count ) - 1 ) * var( --wpc-t ) ) )';

ck( 'at 900px and wider the steps rise from 160px to 360px, their top corners 2px; below, a white row each, ink-70 on it at 9.54:1, with a 6px brand bar along its bottom edge widening from one step\'s share of the row to the whole row and 20px of padding under the text; the step pressed filled in brand with its hairline taken off, and its row\'s bar in the brand tint',
	array(
		rules_for( '.wpc-hub-stair' )[1],
		rules_for( '.wpc-hub-stair' )[2],
		rules_for( '.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] )' ),
		$bar_ends,
		contrast( color_of( 'ink-70' ), color_of( 'base' ) ),
	),
	array(
		'border-radius: var( --wpc-radius-control ) var( --wpc-radius-control ) 0 0; min-height: calc( 160px + 200px * var( --wpc-t ) );',
		'--wpc-bar: var( --wpc-brand ); background: linear-gradient( var( --wpc-bar ), var( --wpc-bar ) ) left bottom / ' . $bar_width . ' 6px no-repeat var( --wpc-surface ); padding-bottom: 20px;',
		array( 'background-color: var( --wpc-brand ); box-shadow: none;', 'background: Highlight; forced-color-adjust: none;', '--wpc-bar: var( --wpc-brand-tint );' ),
		array( array( 14.29, 100.0 ), array( 25.0, 100.0 ) ),
		'9.54',
	) );

ck( 'the step\'s one movement, the transition and the lift under the pointer or with the keyboard\'s focus on it (not a click\'s), each lift a rule of its own, is declared only for a reader who has not asked for less',
	array(
		(bool) preg_match( '/@media \( prefers-reduced-motion: no-preference \) \{\s*\.wpc-hub-pathway\.is-interactive \.wpc-hub-stair \{\s*transition: transform \.15s ease;\s*\}\s*\.wpc-hub-pathway\.is-interactive \.wpc-hub-stair:hover \{\s*transform: translateY\( -4px \);\s*\}\s*\.wpc-hub-pathway\.is-interactive \.wpc-hub-stair:has\( > \.wpc-hub-stair__pick:focus-visible \) \{\s*transform: translateY\( -4px \);\s*\}\s*\}/', theme_file( 'assets/css/hub.css' ) ),
		substr_count( theme_file( 'assets/css/hub.css' ), 'translateY(' ),
		substr_count( theme_file( 'assets/css/hub.css' ), ':focus-within' ),
	),
	array( true, 2, 0 ) );

ck( 'the hint is a prompt in a white box on the card hairline with 2px corners, in the dark brand (7.70:1 on the white), 14px, a down chevron before it, 16px under the lede while the script shows it (22px, the lede\'s own, without it) and 22px above the steps; two classes deep, over the theme\'s paragraph rules',
	array(
		rules_for( '.wpc-hub-pathway .wpc-hub-ladder__hint' ),
		rules_for( '.wpc-hub-pathway .wpc-hub-ladder__hint::before' ),
		rules_for( '.wpc-hub-pathway.is-interactive .wpc-hub-ladder__lede' ),
		contrast( color_of( 'brand-dark' ), color_of( 'base' ) ),
	),
	array(
		array( 'align-items: center; background: var( --wpc-surface ); border: 1px solid var( --wpc-line-card ); border-radius: var( --wpc-radius-control ); color: var( --wpc-brand-dark ); display: flex; font-size: 14px; font-weight: 500; gap: 8px; line-height: 20px; margin: 0 0 22px; padding: 6px 14px 6px 12px; width: fit-content;' ),
		array( 'border-bottom: 2px solid currentColor; border-right: 2px solid currentColor; content: ""; height: 7px; margin-top: -4px; transform: rotate( 45deg ); width: 7px;' ),
		array( 'margin-bottom: 16px;' ),
		'7.70',
	) );

ck( 'the cue sits at the foot of its step in the dark brand at 13px (7.70:1 on the step\'s white), white on the step pressed (5.61), its second line shown only there and the first then not, with a down chevron after it',
	array(
		rules_for( '.wpc-hub-stair .wpc-hub-stair__cue' ),
		rules_for( '.wpc-hub-stair .wpc-hub-stair__cue::after' ),
		rules_for( '.wpc-hub-stair .wpc-hub-stair__shown' ),
		rules_for( '.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) .wpc-hub-stair__see' ),
		rules_for( '.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) .wpc-hub-stair__shown' ),
		rules_for( '.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) .wpc-hub-stair__cue' ),
		array( contrast( color_of( 'brand-dark' ), color_of( 'base' ) ), contrast( color_of( 'base' ), color_of( 'brand' ) ) ),
	),
	array(
		array( 'align-items: center; color: var( --wpc-brand-dark ); display: inline-flex; font-size: 13px; font-weight: 600; gap: 6px; line-height: 18px; margin-top: auto; padding-top: 6px;' ),
		array( 'border-bottom: 2px solid currentColor; border-right: 2px solid currentColor; content: ""; height: 6px; margin-top: -3px; transform: rotate( 45deg ); width: 6px;' ),
		array( 'display: none;' ),
		array( 'display: none;' ),
		array( 'display: inline;' ),
		array( 'color: var( --wpc-surface );' ),
		array( '7.70', '5.61' ),
	) );

ck( 'with the script and the steps side by side, the shown detail points up at its step: a 14px square turned on its corner, its two upper edges the card\'s, at the middle the script sets (the card\'s middle until then), from 900px only',
	array( array_values( preg_grep( '/\.wpc-hub-detail(?: |::)/', rules_in_media( '( min-width: 900px )' ) ) ), false !== strpos( implode( ' ', rules_for( '.wpc-hub-details' ) ), '--wpc-notch: 50%;' ) ),
	array( array(
		'.wpc-hub-pathway.is-interactive .wpc-hub-detail { position: relative; }',
		'.wpc-hub-pathway.is-interactive .wpc-hub-detail::before { background: var( --wpc-surface ); border-left: 1px solid var( --wpc-line-card ); border-top: 1px solid var( --wpc-line-card ); content: ""; height: 14px; left: calc( var( --wpc-notch ) - 8px ); position: absolute; top: -8px; transform: rotate( 45deg ); width: 14px; }',
	), true ) );

ck( 'a step\'s detail and a program\'s card, each an address a link names, land below the sticky header',
	array_map( static function ( $selector ) { return false !== strpos( implode( ' ', rules_for( $selector ) ), 'scroll-margin-top: var( --wpc-landing-offset );' ); }, array( '.wpc-hub-detail', '.wpc-hub-program' ) ),
	array( true, true ) );

ck( 'the step\'s own focus outline gives way to the ring around the whole step only where :has() draws it; elsewhere the theme\'s own ring stays',
	array_values( preg_grep( '/wpc-hub-stair/', under_has_support() ) ),
	array( '.wpc-hub-stair__pick:focus-visible { outline: none; }', '.wpc-hub-stair:has( > .wpc-hub-stair__pick:focus-visible ) { outline: 2px solid var( --wpc-brand ); outline-offset: 2px; }' ) );

ck( 'and the contrast: on a step\'s white, its line in ink-70 9.54 and "Step 1" to "Step 7" in the serif at 32px in the brand 5.61; on the step pressed, white 5.61 and the brand tint 5.03',
	array( rules_for( '.wpc-hub-stair__n' ), contrast( color_of( 'ink-70' ), color_of( 'base' ) ), contrast( color_of( 'brand' ), color_of( 'base' ) ), contrast( color_of( 'base' ), color_of( 'brand' ) ), contrast( color_of( 'brand-tint' ), color_of( 'brand' ) ) ),
	array( array( 'color: var( --wpc-brand ); font: 400 32px/1 var( --wpc-hub-serif );' ), '9.54', '5.61', '5.61', '5.03' ) );

// Forced colors drop a step's fill (background-color is forced to Canvas) and the phone bar (a gradient
// is not kept), and a step has no border, so every step but the pressed one loses its edge. Each step
// gets a 1px CanvasText border there, written two classes deep over the step's own one-class rules; the
// pressed step keeps its Highlight and its HighlightText.
ck( 'in forced colors every step keeps its edge, a 1px CanvasText border written with two classes, and the pressed step keeps the system\'s highlight, its number, line and cue in its text too, written as deep as their own colors and later',
	array_values( preg_grep( '/wpc-hub-stair/', rules_in_media( '( forced-colors: active )' ) ) ),
	array(
		'.wpc-hub-stairs .wpc-hub-stair { border: 1px solid CanvasText; }',
		'.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) { background: Highlight; forced-color-adjust: none; }',
		'.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) * { color: HighlightText; }',
		'.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) :is( .wpc-hub-stair__n, .wpc-hub-stair__by, .wpc-hub-stair__cue ) { color: HighlightText; }',
	) );

echo "\n=== The program figure: the counts, as before ===\n";

/**
 * The program figure block's markup, for some attributes.
 *
 * @param array $attributes The block's attributes.
 * @return string
 */
function stat_block( array $attributes ) {
	return wpcredits_test_block( 'wpcredits/program-stat', $attributes );
}

$counts_without_plugin = array(
	stat_block( array( 'stat' => 'mentors', 'fallback' => '90' ) ),
	stat_block( array( 'stat' => 'students' ) ),
);
$outcome_without_plugin = stat_block( array( 'stat' => 'graduation', 'fallback' => '90' ) );

// The sign-in section, drawn while the plugin is still not there; the checks on it are in the section
// of their own below. A missing file is said in words, so a suite run before the code exists counts
// failures instead of stopping.
$sign_in_without_plugin = is_file( WPCREDITS_THEME_DIR . 'blocks/sign-in/render.php' ) ? wpcredits_test_block( 'wpcredits/sign-in' ) : 'no blocks/sign-in/render.php';

// The plugin's mentors dashboard, which is how the theme tells the plugin is active; its page is a
// check's to set, as the dashboards' that follow in the sign-in section.
if ( ! class_exists( 'WPCPM_Mentors_Dashboard' ) ) {
	/** Present, so the plugin reads as active; its page as the plugin reports it: '' while it is not published. */
	class WPCPM_Mentors_Dashboard {
		public static $url = '';
		public static function page_url() { return self::$url; }
	}
}

$GLOBALS['role_count'] = array( 'wpcpm_mentor' => 88, 'wpcpm_institution' => 1234 );
$counts_with_plugin    = array(
	stat_block( array( 'stat' => 'mentors', 'fallback' => '90' ) ),
	stat_block( array( 'stat' => 'institutions' ) ),
	stat_block( array( 'stat' => 'sponsors', 'fallback' => ' 12 ' ) ),
	stat_block( array( 'stat' => 'students' ) ),
	stat_block( array() ),
	stat_block( array( 'stat' => 'nothing', 'fallback' => '7' ) ),
);

ck( 'the four count stats are drawn as 1.24.14 drew them, byte for byte: a count, or the typed fallback for a zero or with no plugin, or nothing',
	array( $counts_without_plugin, $counts_with_plugin, $GLOBALS['transients'] ),
	array(
		array( '<p class="wp-block-wpcredits-program-stat">90</p>', '' ),
		array( '<p class="wp-block-wpcredits-program-stat">88</p>', '<p class="wp-block-wpcredits-program-stat">1,234</p>', '<p class="wp-block-wpcredits-program-stat">12</p>', '', '<p class="wp-block-wpcredits-program-stat">88</p>', '<p class="wp-block-wpcredits-program-stat">7</p>' ),
		array( 'wpcredits_count_wpcpm_mentor' => 88, 'wpcredits_count_wpcpm_institution' => 1234, 'wpcredits_count_wpcpm_sponsor' => 0, 'wpcredits_count_wpcpm_student' => 0 ),
	) );

$said_before = $GLOBALS['said'];

stat_block( array( 'stat' => 'mentors', 'fallback' => '90' ) );
stat_block( array( 'stat' => 'nothing', 'fallback' => '7' ) );
stat_block( array() );

ck( 'a count stat looks no string up to translate: the figure helper tells it is not an outcome figure before it builds a label, so the live homepage\'s mentors card costs what it did',
	$GLOBALS['said'] - $said_before, 0 );

echo "\n=== The program figure: the outcome figures ===\n";

$figure_stats = array( 'graduation', 'recommend', 'keep', 'outcomes-source' );

/**
 * What the block prints for each outcome stat, in order.
 *
 * @param array $attributes Attributes every block shares.
 * @return string[]
 */
function outcome_blocks( array $attributes = array() ) {
	global $figure_stats;

	return array_map( static function ( $stat ) use ( $attributes ) { return stat_block( array_merge( $attributes, array( 'stat' => $stat ) ) ); }, $figure_stats );
}

// The outcome stats are one list, which the guard of the figure helper and the loop over the plugin's
// figures both read, so a stat added to it is an outcome stat in both and needs no third edit.
ck( 'the block\'s outcome stats are one list: the three figures and their source line are outcome stats to the figure helper, and the four counts and any other name are not',
	array(
		defined( 'WPCREDITS_OUTCOME_FIGURES' ) ? WPCREDITS_OUTCOME_FIGURES : 'no WPCREDITS_OUTCOME_FIGURES',
		array_map( static function ( $name ) { return null === wpcredits_outcome_stat_html( $name ); }, array( 'graduation', 'recommend', 'keep', 'outcomes-source' ) ),
		array_map( static function ( $name ) { return null === wpcredits_outcome_stat_html( $name ); }, array( 'mentors', 'students', 'institutions', 'sponsors', '', 'Graduation', 'outcomes' ) ),
	),
	array( array( 'graduation', 'recommend', 'keep' ), array_fill( 0, 4, false ), array_fill( 0, 7, true ) ) );

ck( 'with a plugin older than 1.122.24, or none, no figure and no source line, a typed fallback ignored, with the plugin off as with it on',
	array( $outcome_without_plugin, outcome_blocks( array( 'fallback' => '90' ) ), function_exists( 'wpcredits_outcome_figures' ) ? wpcredits_outcome_figures() : 'no wpcredits_outcome_figures()' ), array( '', array( '', '', '', '' ), array() ) );

// The plugin's outcome figures, from here on: what published() hands over is a check's.
if ( ! class_exists( 'WPCPM_Outcome_Figures' ) ) {
	/** What the plugin may show, as a check sets it. */
	class WPCPM_Outcome_Figures {
		public static $published = array();
		public static function published() { return self::$published; }
	}
}

$monday = array(
	'read'    => 1791788400,
	'figures' => array(
		'graduation' => array( 'share' => 87, 'count' => 361, 'n' => 413 ),
		'recommend'  => array( 'share' => 88, 'count' => 277, 'n' => 315 ),
		'keep'       => array( 'share' => 79, 'count' => 245, 'n' => 310 ),
	),
);

WPCPM_Outcome_Figures::$published = $monday;

ck( 'the figures the helper reads from the plugin are the list\'s, in its order',
	defined( 'WPCREDITS_OUTCOME_FIGURES' ) ? array_keys( wpcredits_outcome_figures()['figures'] ) : 'no WPCREDITS_OUTCOME_FIGURES', defined( 'WPCREDITS_OUTCOME_FIGURES' ) ? WPCREDITS_OUTCOME_FIGURES : null );

ck( 'each figure in its own block: the number, the label and the base, the label and base from the stored counts',
	outcome_blocks(),
	array(
		'<div class="wpc-figure wp-block-wpcredits-program-stat"><p class="wpc-figure__value">87%</p><p class="wpc-figure__label">of WordPress Credits students graduate rather than drop out</p><p class="wpc-figure__base">361 of 413 students who graduated or dropped out</p></div>',
		'<div class="wpc-figure wp-block-wpcredits-program-stat"><p class="wpc-figure__value">88%</p><p class="wpc-figure__label">of graduates would recommend the program</p><p class="wpc-figure__base">315 answers to the final survey</p></div>',
		'<div class="wpc-figure wp-block-wpcredits-program-stat"><p class="wpc-figure__value">79%</p><p class="wpc-figure__label">of graduates plan to keep contributing to WordPress</p><p class="wpc-figure__base">310 answers to the final survey</p></div>',
		'<p class="wpc-figure-source wp-block-wpcredits-program-stat">As of October 12, 2026, counted by this site from the program\'s records with the same definitions as the <a href="https://wordpress.github.io/WPCredits/">WordPress Credits impact dashboard</a>.</p>',
	) );

$GLOBALS['opts']['date_format'] = 'j F Y';
$GLOBALS['opts']['timezone']    = 'UTC';
$other_site                     = stat_block( array( 'stat' => 'outcomes-source' ) );
$GLOBALS['opts']['timezone']    = 'Pacific/Honolulu';
$day_before                     = stat_block( array( 'stat' => 'outcomes-source' ) );
$GLOBALS['opts']['date_format'] = 'F j, Y';
$GLOBALS['opts']['timezone']    = 'Europe/Warsaw';

ck( 'the date is the site\'s own: its date format and its time zone',
	array( false !== strpos( $other_site, 'As of 12 October 2026,' ), false !== strpos( $day_before, 'As of 11 October 2026,' ) ), array( true, true ) );

ck( 'a label typed into the block replaces the figure\'s own; one of spaces does not',
	array( stat_block( array( 'stat' => 'keep', 'label' => 'of graduates keep <contributing>' ) ), stat_block( array( 'stat' => 'keep', 'label' => '   ' ) ) === outcome_blocks()[2] ),
	array( '<div class="wpc-figure wp-block-wpcredits-program-stat"><p class="wpc-figure__value">79%</p><p class="wpc-figure__label">of graduates keep &lt;contributing&gt;</p><p class="wpc-figure__base">310 answers to the final survey</p></div>', true ) );

WPCPM_Outcome_Figures::$published = array(
	'read'    => 1791788400,
	'figures' => array( 'graduation' => array( 'share' => 100, 'count' => 1, 'n' => 1 ), 'recommend' => array( 'share' => 100, 'count' => 1, 'n' => 1 ) ),
);

ck( 'a figure the plugin leaves out, under its minimum sample, is not drawn and the others are; one answer or one student reads in the singular',
	array( outcome_blocks()[2], false !== strpos( outcome_blocks()[0], '1 of 1 student who graduated or dropped out' ), false !== strpos( outcome_blocks()[1], '1 answer to the final survey' ), '' !== outcome_blocks()[3] ),
	array( '', true, true, true ) );

/**
 * The text of each outcome figure's base line, in order.
 *
 * @return string[]
 */
function figure_bases() {
	return found( dom_of( implode( '', outcome_blocks() ) ), '//p[@class="wpc-figure__base"]' );
}

WPCPM_Outcome_Figures::$published = array(
	'read'    => 1791788400,
	'figures' => array(
		'graduation' => array( 'share' => 96, 'count' => 1361, 'n' => 1413 ),
		'recommend'  => array( 'share' => 90, 'count' => 1177, 'n' => 1315 ),
		'keep'       => array( 'share' => 77, 'count' => 1010, 'n' => 1310 ),
	),
);

ck( 'the counts and the bases are grouped in thousands as the site\'s language groups them: 1,361 of 1,413 students, 1,315 answers',
	figure_bases(),
	array( '1,361 of 1,413 students who graduated or dropped out', '1,315 answers to the final survey', '1,310 answers to the final survey' ) );

WPCPM_Outcome_Figures::$published = array(
	'read'    => 1791788400,
	'figures' => array(
		'graduation' => array( 'share' => 3, 'count' => 1, 'n' => 30 ),
		'recommend'  => array( 'share' => 3, 'count' => 1, 'n' => 30 ),
		'keep'       => array( 'share' => 3, 'count' => 1, 'n' => 30 ),
	),
);

ck( 'the singular and the plural follow the base, not the count: one of 30 students, 30 answers',
	figure_bases(),
	array( '1 of 30 students who graduated or dropped out', '30 answers to the final survey', '30 answers to the final survey' ) );

WPCPM_Outcome_Figures::$published = array();

ck( 'with nothing the plugin may show, nothing at all: no figure, no source line',
	outcome_blocks(), array( '', '', '', '' ) );

$junk = array(
	'not a list'               => 'figures',
	'no read time'             => array( 'figures' => $monday['figures'] ),
	'a read time as a string'  => array( 'read' => '1791788400', 'figures' => $monday['figures'] ),
	'figures not a list'       => array( 'read' => 1791788400, 'figures' => 'all' ),
	'a share over 100'         => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => 187, 'count' => 361, 'n' => 413 ) ) ),
	'a negative share'         => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => -1, 'count' => 361, 'n' => 413 ) ) ),
	'a share as a string'      => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => '87', 'count' => 361, 'n' => 413 ) ) ),
	'a count over its n'       => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => 87, 'count' => 414, 'n' => 413 ) ) ),
	'a zero count'             => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => 0, 'count' => 0, 'n' => 413 ) ) ),
	'a figure missing its n'   => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => 87, 'count' => 361 ) ) ),
	'a read time of zero'      => array( 'read' => 0, 'figures' => $monday['figures'] ),
	'a count as a string'      => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => 87, 'count' => '361', 'n' => 413 ) ) ),
	'an n as a string'         => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => 87, 'count' => 361, 'n' => '413' ) ) ),
);

$drawn = array();

foreach ( $junk as $what => $published ) {
	WPCPM_Outcome_Figures::$published = $published;
	$drawn[ $what ]                   = implode( '', outcome_blocks() );
}

ck( 'whatever else the plugin hands over draws nothing, never a figure made of it',
	$drawn, array_fill_keys( array_keys( $junk ), '' ) );

WPCPM_Outcome_Figures::$published = $monday;
$GLOBALS['marked']                = true;
$marked_figures                   = implode( '', outcome_blocks() );
$GLOBALS['marked']                = false;

ck( 'every word the figures print is a translated string, the percent sign among them',
	array( unmarked( $marked_figures ), false !== strpos( $marked_figures, '[87%]' ) ), array( array(), true ) );

ck( 'block.json: the label attribute, empty by default, beside the stat and the fallback',
	json_decode( theme_file( 'blocks/program-stat/block.json' ), true )['attributes'],
	array( 'stat' => array( 'type' => 'string', 'default' => 'mentors' ), 'fallback' => array( 'type' => 'string', 'default' => '' ), 'label' => array( 'type' => 'string', 'default' => '' ) ) );

$stat_asset = WPCREDITS_THEME_DIR . 'blocks/program-stat/index.asset.php';

ck( 'block.json: an editor preview, as the program index and the pathway have, its asset file naming the editor\'s packages and the server render at the theme\'s version',
	array( json_decode( theme_file( 'blocks/program-stat/block.json' ), true )['editorScript'] ?? null, is_file( $stat_asset ) ? include $stat_asset : null ),
	array( 'file:./index.js', array( 'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render' ), 'version' => '9.9.9' ) ) );

$stat_editor = is_file( WPCREDITS_THEME_DIR . 'blocks/program-stat/index.js' ) ? theme_file( 'blocks/program-stat/index.js' ) : '';

ck( 'the preview registers the block and hands the server each instance\'s own attributes, so the hero\'s four figures and the homepage\'s mentors card each preview their own stat',
	array( false !== strpos( $stat_editor, "blocks.registerBlockType( 'wpcredits/program-stat', {" ), false !== strpos( $stat_editor, "el( ServerSideRender, { block: 'wpcredits/program-stat', attributes: props.attributes } )" ), false !== strpos( $stat_editor, 'return null;' ) ),
	array( true, true, true ) );

ck( 'the figure\'s number: the serif at 56px in ink (16.67:1 on the page\'s white), regular weight, in tabular figures, 10px above its label, the same size at every width',
	array( rules_placed( '.wpc-figure__value' ), contrast( color_of( 'ink' ), color_of( 'base' ) ) ),
	array( array( array( '', 'color: var( --wpc-ink ); font: 400 56px/1 var( --wpc-hub-serif ); font-variant-numeric: tabular-nums; letter-spacing: -.01em; margin-bottom: 10px;' ) ), '16.67' ) );

ck( 'the figures side by side, each an equal share of the row, padded 28px above, 26px below and 32px at each side, a card hairline beside each after the first; below 900px one to a row with no inner indent, so the numbers line up with the text above, the hairline above each after the first; the children\'s margins reset, and the figure\'s own margin reset with two classes, so a block gap (WordPress prints it with one class, often later) never opens a gap above a figure',
	array( rules_placed( '.wpc-figure' ), rules_placed( '.wpc-figure + .wpc-figure' ), rules_for( '.wpc-figure > *' ), rules_for( '.wp-block-wpcredits-program-stat.wpc-figure' ) ),
	array(
		array( array( '', 'flex: 1 1 0; min-width: 0; padding: 28px 32px 26px;' ), array( '@media ( max-width: 899px )', 'flex-basis: 100%; padding: 28px 0 26px;' ) ),
		array( array( '', 'border-left: 1px solid var( --wpc-line-card );' ), array( '@media ( max-width: 899px )', 'border-left: 0; border-top: 1px solid var( --wpc-line-card );' ) ),
		array( 'margin: 0;' ),
		array( 'margin-block: 0;' ),
	) );

ck( 'the label and the base: 14px ink-70 and 12px ink-60 under the number, 6px between them; the source line across the whole row on a hairline of its own, 12px ink-60, 12px above and below it and 32px at each side, as the figures, none at the sides below 900px, where the figures have none; written with two classes so the same block gap cannot beat it, and no rule for it with one',
	array( rules_for( '.wpc-figure__label' ), rules_for( '.wpc-figure__base' ), rules_placed( '.wp-block-wpcredits-program-stat.wpc-figure-source' ), rules_for( '.wpc-figure-source' ) ),
	array(
		array( 'color: var( --wpc-ink-70 ); font-size: 14px; line-height: 1.45;' ),
		array( 'color: var( --wpc-ink-60 ); font-size: 12px; line-height: 1.5; margin-top: 6px;' ),
		array(
			array( '', 'border-top: 1px solid var( --wpc-line-card ); color: var( --wpc-ink-60 ); flex-basis: 100%; font-size: 12px; margin: 0; padding: 12px 32px;' ),
			array( '@media ( max-width: 899px )', 'padding: 12px 0;' ),
		),
		array(),
	) );

echo "\n=== The sign-in section ===\n";

$sign_dir    = WPCREDITS_THEME_DIR . 'blocks/sign-in/';
$sign_json   = (array) json_decode( is_file( $sign_dir . 'block.json' ) ? theme_file( 'blocks/sign-in/block.json' ) : '', true );
$sign_editor = is_file( $sign_dir . 'index.js' ) ? theme_file( 'blocks/sign-in/index.js' ) : '';

ck( 'block.json: wpcredits/sign-in, once a page, not reusable, server-rendered, with an editor preview, the hub\'s stylesheet as its style and no view script, since nothing on it needs one',
	array( $sign_json['name'] ?? null, $sign_json['supports']['html'] ?? null, $sign_json['supports']['reusable'] ?? null, $sign_json['supports']['multiple'] ?? null, $sign_json['supports']['align'] ?? null, $sign_json['render'] ?? null, $sign_json['editorScript'] ?? null, $sign_json['style'] ?? null, $sign_json['textdomain'] ?? null, array_key_exists( 'viewScript', $sign_json ), is_file( $sign_dir . 'view.js' ), is_file( $sign_dir . 'view.asset.php' ) ),
	array( 'wpcredits/sign-in', false, false, false, false, 'file:./render.php', 'file:./index.js', 'wpcredits-hub', 'wpcredits-theme', false, false, false ) );

ck( 'its editor preview needs the editor\'s packages and the server render at the theme\'s version, registers the block and lets the server draw it; the theme registers the block after the pathway',
	array(
		is_file( $sign_dir . 'index.asset.php' ) ? include $sign_dir . 'index.asset.php' : null,
		false !== strpos( $sign_editor, "blocks.registerBlockType( 'wpcredits/sign-in', {" ),
		false !== strpos( $sign_editor, "el( ServerSideRender, { block: 'wpcredits/sign-in' } )" ),
		false !== strpos( $sign_editor, 'return null;' ),
		false !== strpos( theme_file( 'functions.php' ), "register_block_type( get_theme_file_path( 'blocks/pathway' ) );\n\tregister_block_type( get_theme_file_path( 'blocks/sign-in' ) );" ),
	),
	array( array( 'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render' ), 'version' => '9.9.9' ), true, true, true, true ) );

ck( 'and the Site Editor previews it at the width the front end draws it, the wide one: its wrapper carries alignwide, as the server\'s section does, since it is the one hub block placed directly in the template\'s constrained main and would else be capped at the content width',
	false !== strpos( $sign_editor, "blockEditor.useBlockProps( { className: 'alignwide' } )" ), true );

/**
 * The sign-in block's markup, or a word that it is not there yet or threw.
 *
 * @return string
 */
function sign_in_block() {
	if ( ! is_file( WPCREDITS_THEME_DIR . 'blocks/sign-in/render.php' ) ) {
		return 'no blocks/sign-in/render.php';
	}

	try {
		return wpcredits_test_block( 'wpcredits/sign-in' );
	} catch ( Throwable $thrown ) {
		return 'threw: ' . $thrown->getMessage();
	}
}

/**
 * What the Institution Dashboard's and the Sponsor Dashboard's page helpers answer in a world of their
 * own: a fresh PHP run with the stub and the theme's template tags and only the dashboards named
 * stood in, each reporting what a check gives it, so a helper's guard on the plugin and its guard on
 * the class can each be seen alone (the plugin is the mentors' dashboard being there).
 *
 * @param array $dashboards Class name => the page it reports, a string or null.
 * @return array|null The two answers, or null when the run did not print them.
 */
function sign_in_world( array $dashboards ) {
	$code = 'require ' . var_export( __DIR__ . '/stubs/hub-wp.php', true ) . '; require ' . var_export( WPCREDITS_THEME_DIR . 'inc/template-tags.php', true ) . ';';

	foreach ( $dashboards as $class => $page ) {
		$code .= ' class ' . $class . ' { public static function page_url() { return ' . var_export( $page, true ) . '; } }';
	}

	$code .= ' echo json_encode( array( wpcredits_institution_page_url(), wpcredits_sponsor_page_url() ) );';

	return json_decode( (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1' ), true );
}

/**
 * The pages of the four dashboards, as the plugin reports them: an address, or '' for one that is
 * not published.
 *
 * @param string $students     The Student Report Card's.
 * @param string $mentors      The Mentor Report Card's.
 * @param string $institutions The Institution Dashboard's.
 * @param string $sponsors     The Sponsor Dashboard's.
 */
function sign_in_pages( $students, $mentors, $institutions, $sponsors ) {
	WPCPM_Students_Dashboard::$url     = $students;
	WPCPM_Mentors_Dashboard::$url      = $mentors;
	WPCPM_Institutions_Dashboard::$url = $institutions;
	WPCPM_Sponsors_Dashboard::$url     = $sponsors;
}

/**
 * What the block shows, card by card: the eyebrows, the ids of the titles, and how many cards the grid
 * says it holds.
 *
 * @param string $html The block's markup.
 * @return array
 */
function sign_in_cards( $html ) {
	$xpath = dom_of( $html );

	return array( found( $xpath, '//div[@class="wpc-hub-sign-in__grid"]/div/p[1]' ), found( $xpath, '//div[@class="wpc-hub-sign-in__grid"]/div/h3', 'id' ), found( $xpath, '//div[@class="wpc-hub-sign-in__grid"]', 'style' ) );
}

// With the mentors' dashboard there, so the plugin is active, and the other three not yet: nothing
// without a page published, and one card for the mentors' page alone.
$sign_in_no_classes = sign_in_block();

WPCPM_Mentors_Dashboard::$url = 'https://wordpresseducation.org/mentor/';
$sign_in_only_mentors         = sign_in_block();

// The other three dashboards, stood in from here on: each reports its page, or '' while it is not
// published, as the plugin's do.
if ( ! class_exists( 'WPCPM_Students_Dashboard' ) ) {
	/** The Student Report Card's page, as the plugin reports it. */
	class WPCPM_Students_Dashboard {
		public static $url = '';
		public static function page_url() { return self::$url; }
	}

	/** The Institution Dashboard's page. */
	class WPCPM_Institutions_Dashboard {
		public static $url = '';
		public static function page_url() { return self::$url; }
	}

	/** The Sponsor Dashboard's page. */
	class WPCPM_Sponsors_Dashboard {
		public static $url = '';
		public static function page_url() { return self::$url; }
	}
}

$sign_pages = array(
	'students'     => 'https://wordpresseducation.org/student/',
	'mentors'      => 'https://wordpresseducation.org/mentor/',
	'institutions' => 'https://wordpresseducation.org/institution/',
	'sponsors'     => 'https://wordpresseducation.org/sponsor/',
);
$sign_login  = static function ( $page ) { return 'https://site.example/wp-login.php?redirect_to=' . rawurlencode( $page ); };
$sign_eyes   = array( 'Students', 'Mentors', 'Institutions', 'Sponsors' );
$sign_names  = array( 'Student Report Card', 'Mentor Report Card', 'Institution Dashboard', 'Sponsor Dashboard' );
$sign_ids    = array( 'wpc-hub-sign-in-students', 'wpc-hub-sign-in-mentors', 'wpc-hub-sign-in-institutions', 'wpc-hub-sign-in-sponsors' );
$sign_sorted = static function ( $classes ) { $set = array_values( array_filter( explode( ' ', $classes ) ) ); sort( $set ); return $set; };

$GLOBALS['logged_in'] = false;
sign_in_pages( ...array_values( $sign_pages ) );

$signed_out = sign_in_block();
$sign_out   = dom_of( $signed_out );

ck( 'signed out, with all four pages published: one section, named by its heading, whose classes are the block\'s, wide and the sign-in section\'s, in any order',
	array(
		found( $sign_out, '/html/body/*', 'class' ) ? $sign_out->query( '/html/body/*' )->item( 0 )->nodeName : null,
		count( $sign_out->query( '/html/body/*' ) ),
		array_map( $sign_sorted, found( $sign_out, '/html/body/section', 'class' ) ),
		found( $sign_out, '/html/body/section', 'aria-labelledby' ),
		found( $sign_out, '//h2', 'id' ),
	),
	array( 'section', 1, array( array( 'alignwide', 'wp-block-wpcredits-sign-in', 'wpc-hub-sign-in' ) ), array( 'wpc-hub-sign-in-title' ), array( 'wpc-hub-sign-in-title' ) ) );

ck( 'its head is the programs section\'s head, the same element and classes, so it spaces as that one does: the eyebrow "Already in a program?" and the heading "Sign in to your page", an h2',
	array( found( $sign_out, '/html/body/section/div[1]', 'class' ), found( $sign_out, '/html/body/section/div[1]/*', 'class' ), found( $sign_out, '/html/body/section/div[1]/p' ), found( $sign_out, '/html/body/section/div[1]/h2' ), count( $sign_out->query( '/html/body/section/div[1]/*' ) ) ),
	array( array( 'wp-block-group wpc-hub-head' ), array( 'is-style-eyebrow', 'wp-block-heading' ), array( 'Already in a program?' ), array( 'Sign in to your page' ), 2 ) );

ck( 'four cards in a grid that says how many, in the order students, mentors, institutions, sponsors; each the doors\' card, with an eyebrow, a title naming the page by its product name, and its link line',
	array(
		found( $sign_out, '/html/body/section/div[2]', 'class' ),
		found( $sign_out, '/html/body/section/div[2]', 'style' ),
		found( $sign_out, '/html/body/section/div[2]/div', 'class' ),
		found( $sign_out, '/html/body/section/div[2]/div/p[1]' ),
		found( $sign_out, '/html/body/section/div[2]/div/p[1]', 'class' ),
		found( $sign_out, '/html/body/section/div[2]/div/h3' ),
		found( $sign_out, '/html/body/section/div[2]/div/h3', 'id' ),
		found( $sign_out, '/html/body/section/div[2]/div/h3', 'class' ),
		found( $sign_out, '/html/body/section/div[2]/div[1]/*', 'class' ),
		count( $sign_out->query( '/html/body/section/div[2]/div/*' ) ),
	),
	array(
		array( 'wpc-hub-sign-in__grid' ),
		array( '--wpc-cards:4' ),
		array_fill( 0, 4, 'wp-block-group is-style-card wpc-hub-door wpc-hub-sign-in__card' ),
		$sign_eyes,
		array_fill( 0, 4, 'is-style-eyebrow' ),
		$sign_names,
		$sign_ids,
		array_fill( 0, 4, 'wp-block-heading' ),
		array( 'is-style-eyebrow', 'wp-block-heading', 'wpc-hub-door__go' ),
		12,
	) );

ck( 'each link signs in and returns to its card\'s page, reads "Sign in", is named by one whole sentence that starts with those words and names its page, so a list of links does not hold four that say the same (nothing is described by the title, which would be heard twice), keeps its arrow from screen readers, and opens in the same tab',
	array(
		found( $sign_out, '//p[@class="wpc-hub-door__go"]/a', 'href' ),
		found( $sign_out, '//p[@class="wpc-hub-door__go"]/a/text()' ),
		found( $sign_out, '//p[@class="wpc-hub-door__go"]/a', 'aria-label' ),
		count( $sign_out->query( '//*[@aria-describedby]' ) ),
		found( $sign_out, '//p[@class="wpc-hub-door__go"]/a/span', 'aria-hidden' ),
		found( $sign_out, '//p[@class="wpc-hub-door__go"]/a/span' ),
		substr_count( $signed_out, '→' ),
		count( $sign_out->query( '//a[@target]' ) ),
		count( $sign_out->query( '//a' ) ),
		array_map( static function ( $label, $words ) { return 0 === strpos( $label, $words ); }, found( $sign_out, '//p[@class="wpc-hub-door__go"]/a', 'aria-label' ), found( $sign_out, '//p[@class="wpc-hub-door__go"]/a/text()' ) ),
	),
	array(
		array_map( $sign_login, array_values( $sign_pages ) ),
		array_fill( 0, 4, 'Sign in' ),
		array( 'Sign in to your Student Report Card', 'Sign in to your Mentor Report Card', 'Sign in to your Institution Dashboard', 'Sign in to your Sponsor Dashboard' ),
		0,
		array_fill( 0, 4, 'true' ),
		array_fill( 0, 4, '→' ),
		4,
		0,
		4,
		array_fill( 0, 4, true ),
	) );

ck( 'the sign-in addresses written out: the login page, and after it each card\'s page percent-encoded as the address to return to',
	found( $sign_out, '//a', 'href' ),
	array(
		'https://site.example/wp-login.php?redirect_to=https%3A%2F%2Fwordpresseducation.org%2Fstudent%2F',
		'https://site.example/wp-login.php?redirect_to=https%3A%2F%2Fwordpresseducation.org%2Fmentor%2F',
		'https://site.example/wp-login.php?redirect_to=https%3A%2F%2Fwordpresseducation.org%2Finstitution%2F',
		'https://site.example/wp-login.php?redirect_to=https%3A%2F%2Fwordpresseducation.org%2Fsponsor%2F',
	) );

$GLOBALS['logged_in'] = true;

$signed_in = sign_in_block();
$sign_in   = dom_of( $signed_in );

ck( 'signed in, the same four cards in the same order, the heading "Open your page", each link the page itself and reading "Open", named by one whole sentence that starts with that word and names its page, and described by nothing',
	array(
		found( $sign_in, '//h2' ),
		sign_in_cards( $signed_in ),
		found( $sign_in, '//p[@class="wpc-hub-door__go"]/a', 'href' ),
		found( $sign_in, '//p[@class="wpc-hub-door__go"]/a/text()' ),
		found( $sign_in, '//p[@class="wpc-hub-door__go"]/a', 'aria-label' ),
		count( $sign_in->query( '//*[@aria-describedby]' ) ),
		found( $sign_in, '//p[@class="wpc-hub-door__go"]/a/span', 'aria-hidden' ),
		found( $sign_in, '/html/body/section/div[1]/*', 'class' ),
		array_map( static function ( $label, $words ) { return 0 === strpos( $label, $words ); }, found( $sign_in, '//p[@class="wpc-hub-door__go"]/a', 'aria-label' ), found( $sign_in, '//p[@class="wpc-hub-door__go"]/a/text()' ) ),
	),
	array(
		array( 'Open your page' ),
		array( $sign_eyes, $sign_ids, array( '--wpc-cards:4' ) ),
		array_values( $sign_pages ),
		array_fill( 0, 4, 'Open' ),
		array( 'Open your Student Report Card', 'Open your Mentor Report Card', 'Open your Institution Dashboard', 'Open your Sponsor Dashboard' ),
		0,
		array_fill( 0, 4, 'true' ),
		array( 'is-style-eyebrow', 'wp-block-heading' ),
		array_fill( 0, 4, true ),
	) );

$GLOBALS['logged_in'] = false;

$sign_some = array();

foreach ( array( array( 'sponsors' ), array( 'mentors' ), array( 'mentors', 'institutions', 'sponsors' ), array( 'students', 'mentors' ) ) as $sign_left_out ) {
	$sign_set = array_map( static function ( $sign_key ) use ( $sign_pages, $sign_left_out ) { return in_array( $sign_key, $sign_left_out, true ) ? '' : $sign_pages[ $sign_key ]; }, array_keys( $sign_pages ) );

	sign_in_pages( ...$sign_set );

	$sign_html   = sign_in_block();
	$sign_some[] = array( sign_in_cards( $sign_html ), found( dom_of( $sign_html ), '//a', 'href' ) );
}

ck( 'a page that is not published leaves its card out and the others keep their order, the grid saying how many are left: without the sponsors, three; without the mentors, three; the institutions and sponsors alone, two; the students alone, one',
	$sign_some,
	array(
		array( array( array( 'Students', 'Mentors', 'Institutions' ), array_slice( $sign_ids, 0, 3 ), array( '--wpc-cards:3' ) ), array_map( $sign_login, array( $sign_pages['students'], $sign_pages['mentors'], $sign_pages['institutions'] ) ) ),
		array( array( array( 'Students', 'Institutions', 'Sponsors' ), array( $sign_ids[0], $sign_ids[2], $sign_ids[3] ), array( '--wpc-cards:3' ) ), array_map( $sign_login, array( $sign_pages['students'], $sign_pages['institutions'], $sign_pages['sponsors'] ) ) ),
		array( array( array( 'Students' ), array( $sign_ids[0] ), array( '--wpc-cards:1' ) ), array( $sign_login( $sign_pages['students'] ) ) ),
		array( array( array( 'Institutions', 'Sponsors' ), array( $sign_ids[2], $sign_ids[3] ), array( '--wpc-cards:2' ) ), array_map( $sign_login, array( $sign_pages['institutions'], $sign_pages['sponsors'] ) ) ),
	) );

ck( 'with only the mentors\' dashboard there, its page published, the block is one card, the mentors\'',
	sign_in_cards( $sign_in_only_mentors ),
	array( array( 'Mentors' ), array( 'wpc-hub-sign-in-mentors' ), array( '--wpc-cards:1' ) ) );

sign_in_pages( '', '', '', '' );

$said_before = $GLOBALS['said'];
$sign_none  = array( sign_in_block() );

$GLOBALS['logged_in'] = true;
$sign_none[]          = sign_in_block();
$GLOBALS['logged_in'] = false;

ck( 'with no page to go to the block prints nothing at all, no empty box, and looks no string up: the plugin not there, the plugin there without its other dashboards, and every page unpublished, signed out or in',
	array( $sign_in_without_plugin, $sign_in_no_classes, $sign_none, $GLOBALS['said'] - $said_before ),
	array( '', '', array( '', '' ), 0 ) );

// Escaping: an address holding & and a quote, signed in (the page itself) and signed out (what a
// login_url filter adds to the login address), and translations holding markup.
$sign_awkward = 'https://wordpresseducation.org/student/?a=1&b="2"';

sign_in_pages( $sign_awkward, $sign_pages['mentors'], $sign_pages['institutions'], $sign_pages['sponsors'] );

$GLOBALS['logged_in'] = true;
$awkward_in           = sign_in_block();

$GLOBALS['logged_in']             = false;
$GLOBALS['hooks']['login_url'][] = array( static function ( $url ) { return $url . '&reauth="1"'; }, 10 );
$awkward_out                     = sign_in_block();
unset( $GLOBALS['hooks']['login_url'] );

ck( 'an address holding an ampersand and a quote comes out escaped in the href, signed in or out, and breaks nothing: four links, and the address as it was when the page reads it',
	array(
		false !== strpos( $awkward_in, 'href="https://wordpresseducation.org/student/?a=1&#038;b=&quot;2&quot;"' ),
		found( dom_of( $awkward_in ), '//a', 'href' )[0] ?? null,
		count( dom_of( $awkward_in )->query( '//a' ) ),
		false !== strpos( $awkward_out, 'student%2F%3Fa%3D1%26b%3D%222%22&#038;reauth=&quot;1&quot;"' ),
		found( dom_of( $awkward_out ), '//a', 'href' )[0] ?? null,
		count( dom_of( $awkward_out )->query( '//a' ) ),
	),
	array( true, $sign_awkward, 4, true, $sign_login( $sign_awkward ) . '&reauth="1"', 4 ) );

sign_in_pages( ...array_values( $sign_pages ) );

$sign_markup = array();

foreach ( array( 'Already in a program?', 'Sign in to your page', 'Open your page', 'Students', 'Mentors', 'Institutions', 'Sponsors', 'Student Report Card', 'Mentor Report Card', 'Institution Dashboard', 'Sponsor Dashboard', 'Sign in', 'Open', 'Sign in to your Student Report Card', 'Sign in to your Mentor Report Card', 'Sign in to your Institution Dashboard', 'Sign in to your Sponsor Dashboard', 'Open your Student Report Card', 'Open your Mentor Report Card', 'Open your Institution Dashboard', 'Open your Sponsor Dashboard' ) as $sign_text ) {
	$sign_markup[ $sign_text ] = '<em>' . $sign_text . '</em> & "q"';
}

$GLOBALS['translated'] = $sign_markup;
$markup_said           = array();

foreach ( array( false, true ) as $sign_who ) {
	$GLOBALS['logged_in'] = $sign_who;

	$sign_html = sign_in_block();
	$sign_xp   = dom_of( $sign_html );

	$markup_said[] = array(
		count( $sign_xp->query( '//em' ) ),
		found( $sign_xp, '//p[@class="is-style-eyebrow"]' ),
		found( $sign_xp, '//h2' ),
		found( $sign_xp, '//h3' ),
		found( $sign_xp, '//p[@class="wpc-hub-door__go"]/a/text()' ),
		found( $sign_xp, '//p[@class="wpc-hub-door__go"]/a', 'aria-label' ),
	);
}

$GLOBALS['translated'] = array();
$GLOBALS['logged_in']  = false;

$sign_quoted = static function ( $texts ) use ( $sign_markup ) { return array_map( static function ( $sign_text ) use ( $sign_markup ) { return $sign_markup[ $sign_text ]; }, $texts ); };

ck( 'a translation holding markup, an ampersand or a quote is printed as text, in the eyebrow, the heading, the titles, the links and the links\' names, signed out and signed in',
	$markup_said,
	array(
		array( 0, $sign_quoted( array_merge( array( 'Already in a program?' ), $sign_eyes ) ), $sign_quoted( array( 'Sign in to your page' ) ), $sign_quoted( $sign_names ), $sign_quoted( array_fill( 0, 4, 'Sign in' ) ), $sign_quoted( array( 'Sign in to your Student Report Card', 'Sign in to your Mentor Report Card', 'Sign in to your Institution Dashboard', 'Sign in to your Sponsor Dashboard' ) ) ),
		array( 0, $sign_quoted( array_merge( array( 'Already in a program?' ), $sign_eyes ) ), $sign_quoted( array( 'Open your page' ) ), $sign_quoted( $sign_names ), $sign_quoted( array_fill( 0, 4, 'Open' ) ), $sign_quoted( array( 'Open your Student Report Card', 'Open your Mentor Report Card', 'Open your Institution Dashboard', 'Open your Sponsor Dashboard' ) ) ),
	) );

// Every text is one translated string: the eyebrow and the heading, and for each card an eyebrow, a
// title, a link and the link's whole name, eighteen with all four cards and the same eighteen
// whoever is looking.
$GLOBALS['marked'] = true;
$marked_sign_in    = array();
$looked_up         = array();

foreach ( array( false, true ) as $sign_who ) {
	$GLOBALS['logged_in'] = $sign_who;
	$said_before          = $GLOBALS['said'];
	$marked_sign_in[]     = sign_in_block();
	$looked_up[]          = $GLOBALS['said'] - $said_before;
}

$GLOBALS['marked']    = false;
$GLOBALS['logged_in'] = false;

ck( 'every text is one translated string, seen whole by a translator: 18 of them with four cards, signed out or in, none printed as written, and the strings looked up are the strings printed',
	array( unmarked( $marked_sign_in[0] ), unmarked( $marked_sign_in[1] ), $looked_up, array_map( static function ( $html ) { return preg_match_all( '/\[[^\[\]]+\]/', preg_replace( '/<[^>]*>/', '', $html ) ) + preg_match_all( '/aria-label="\[[^\[\]]+\]"/', $html ); }, $marked_sign_in ) ),
	array( array(), array(), array( 18, 18 ), array( 18, 18 ) ) );

// The helpers that read the two pages the block adds, each in a world of its own.
$mentors_there = 'WPCPM_Mentors_Dashboard';
$institutions  = 'WPCPM_Institutions_Dashboard';
$sponsors      = 'WPCPM_Sponsors_Dashboard';
$institution   = 'https://wordpresseducation.org/institution/';
$sponsor_page  = 'https://wordpresseducation.org/sponsor/';

ck( 'the Institution Dashboard\'s and the Sponsor Dashboard\'s pages are read as the Student Report Card\'s is: nothing without the plugin, even where the class is there, nothing without the class, the class\'s own answer otherwise (an address, or nothing for one not published), and always a string',
	array(
		sign_in_world( array( $institutions => $institution, $sponsors => $sponsor_page ) ),
		sign_in_world( array( $mentors_there => 'https://wordpresseducation.org/mentor/' ) ),
		sign_in_world( array( $mentors_there => '', $institutions => $institution ) ),
		sign_in_world( array( $mentors_there => '', $sponsors => $sponsor_page ) ),
		sign_in_world( array( $mentors_there => '', $institutions => $institution, $sponsors => $sponsor_page ) ),
		sign_in_world( array( $mentors_there => '', $institutions => '', $sponsors => '' ) ),
		sign_in_world( array( $mentors_there => '', $institutions => null, $sponsors => null ) ),
	),
	array(
		array( '', '' ),
		array( '', '' ),
		array( $institution, '' ),
		array( '', $sponsor_page ),
		array( $institution, $sponsor_page ),
		array( '', '' ),
		array( '', '' ),
	) );

// The look: the section, the grid and its three widths, every selector two classes deep or more so
// core's layout styles never outrank them in either print order, and the head and the cards the
// programs' and the doors' own, with no rule of their own.
$sign_css = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) );

preg_match_all( '/([^{}]+)\{/', $sign_css, $sign_preludes );

$sign_selectors = array();

foreach ( $sign_preludes[1] as $sign_prelude ) {
	$sign_prelude = trim( preg_replace( '/\s+/', ' ', $sign_prelude ) );

	if ( false !== strpos( $sign_prelude, 'wpc-hub-sign-in' ) ) {
		$sign_selectors[] = $sign_prelude . ' (' . preg_match_all( '/\.[a-z]/', $sign_prelude ) . ' classes)';
	}
}

ck( 'the sign-in section\'s rules: the section padded 48px above and below, the grid and its columns, four rules and all of them written with two classes, none with one',
	array( rules_for( '.wp-block-wpcredits-sign-in.wpc-hub-sign-in' ), rules_for( '.wpc-hub-sign-in' ), rules_for( '.wpc-hub-sign-in__grid' ), $sign_selectors ),
	array(
		array( 'padding-bottom: 48px; padding-top: 48px;' ),
		array(),
		array(),
		array(
			'.wp-block-wpcredits-sign-in.wpc-hub-sign-in (2 classes)',
			'.wpc-hub-sign-in .wpc-hub-sign-in__grid (2 classes)',
			'.wpc-hub-sign-in .wpc-hub-sign-in__grid (2 classes)',
			'.wpc-hub-sign-in .wpc-hub-sign-in__grid (2 classes)',
		),
	) );

ck( 'the cards are a grid with 16px between them: one column below 640px, two from 640px, and from 900px one column for each card printed (--wpc-cards, which the sheet declares at 4 for the grid to read before the block sets it, as every token the theme reads is declared), so three cards fill the row as four do',
	array(
		rules_for( '.wpc-hub-sign-in .wpc-hub-sign-in__grid' ),
		array_values( preg_grep( '/sign-in__grid/', rules_in_media( '( min-width: 640px )' ) ) ),
		array_values( preg_grep( '/sign-in__grid/', rules_in_media( '( min-width: 900px )' ) ) ),
	),
	array(
		array(
			'--wpc-cards: 4; display: grid; gap: 16px; grid-template-columns: minmax( 0, 1fr );',
			'grid-template-columns: repeat( 2, minmax( 0, 1fr ) );',
			'grid-template-columns: repeat( var( --wpc-cards ), minmax( 0, 1fr ) );',
		),
		array( '.wpc-hub-sign-in .wpc-hub-sign-in__grid { grid-template-columns: repeat( 2, minmax( 0, 1fr ) ); }' ),
		array( '.wpc-hub-sign-in .wpc-hub-sign-in__grid { grid-template-columns: repeat( var( --wpc-cards ), minmax( 0, 1fr ) ); }' ),
	) );

ck( 'the head sits 22px above the grid by the programs\' own head rule, which is not scoped to a section, and the cards are the doors\' by their classes: the doors\' card, link line and focus rules are not written again',
	array( rules_for( '.wp-block-group.wpc-hub-head' ), count( $sign_out->query( '//div[@class="wp-block-group wpc-hub-head"]' ) ), count( $sign_out->query( '//div[contains(concat(" ", @class, " "), " wp-block-group ")][contains(concat(" ", @class, " "), " is-style-card ")][contains(concat(" ", @class, " "), " wpc-hub-door ")]' ) ), preg_grep( '/sign-in__(card|go|text)/', $sign_selectors ) ),
	array( array( 'margin-bottom: 22px;' ), 1, 4, array() ) );

// The page, from here on, with the four pages published and nobody signed in, as the checks of the
// page that follow read it.
sign_in_pages( ...array_values( $sign_pages ) );
$GLOBALS['logged_in'] = false;

echo "\n=== The patterns ===\n";

$pattern_files = array( 'hub-hero.php', 'hub-doors.php', 'hub-programs.php', 'hub-pathway.php', 'hub-cta.php' );

/**
 * A pattern file's header, as WordPress reads it.
 *
 * @param string $file The file in patterns/.
 * @return array<string, string>
 */
function pattern_header( $file ) {
	$header = array();

	preg_match_all( '/^ \* (Title|Slug|Categories): (.+)$/m', theme_file( 'patterns/' . $file ), $found, PREG_SET_ORDER );

	foreach ( $found as $field ) {
		$header[ $field[1] ] = $field[2];
	}

	return $header;
}

ck( 'five patterns, each in the theme\'s own category, with the slug the template names',
	array_map( 'pattern_header', $pattern_files ),
	array(
		array( 'Title' => 'Education Hub hero', 'Slug' => 'wpcredits/hub-hero', 'Categories' => 'wpcredits, featured' ),
		array( 'Title' => 'Education Hub doors', 'Slug' => 'wpcredits/hub-doors', 'Categories' => 'wpcredits' ),
		array( 'Title' => 'Education Hub programs', 'Slug' => 'wpcredits/hub-programs', 'Categories' => 'wpcredits' ),
		array( 'Title' => 'Education Hub pathway', 'Slug' => 'wpcredits/hub-pathway', 'Categories' => 'wpcredits' ),
		array( 'Title' => 'Education Hub closing band', 'Slug' => 'wpcredits/hub-cta', 'Categories' => 'wpcredits' ),
	) );

/**
 * What is wrong with a pattern's block grammar: a block comment whose attributes are not JSON, a
 * closer that does not close the block open, a block left open, or a block the hub does not use.
 *
 * @param string $html The pattern's output.
 * @return string[]
 */
function grammar_of( $html ) {
	$known = array( 'group', 'columns', 'column', 'paragraph', 'heading', 'buttons', 'button', 'table', 'html', 'wpcredits/program-stat', 'wpcredits/program-index', 'wpcredits/pathway' );
	$open  = array();
	$wrong = array();

	preg_match_all( '#<!-- (/?)wp:([a-z0-9/-]+)(?: (\{.*?\}))? (/?)-->#', $html, $comments, PREG_SET_ORDER );

	foreach ( $comments as $comment ) {
		if ( ! in_array( $comment[2], $known, true ) ) {
			$wrong[] = 'unknown ' . $comment[2];
		}

		if ( isset( $comment[3] ) && '' !== $comment[3] && null === json_decode( $comment[3] ) ) {
			$wrong[] = 'not JSON ' . $comment[3];
		}

		if ( '/' === $comment[1] ) {
			if ( array_pop( $open ) !== $comment[2] ) {
				$wrong[] = 'closes ' . $comment[2];
			}
		} elseif ( '/' !== $comment[4] ) {
			$open[] = $comment[2];
		}
	}

	foreach ( $open as $left ) {
		$wrong[] = 'open ' . $left;
	}

	return $wrong;
}

ck( 'every pattern is core blocks and the hub\'s three, its comments JSON, every block closed in order',
	array_map( static function ( $file ) { return grammar_of( wpcredits_test_pattern( $file ) ); }, $pattern_files ), array_fill( 0, 5, array() ) );

ck( 'every section is wide, padded 48px above and below; the hero\'s columns none above (the eyebrow 44px under the header), 52px below and none at the sides, since no panel is drawn around them',
	array_map(
		static function ( $file ) {
			$xpath = dom_of( wpcredits_test_pattern( $file ) );

			return implode( ' | ', array( found( $xpath, '/html/body/div', 'class' )[0], found( $xpath, '/html/body/div | /html/body/div/div[1]', 'style' )[ 'hub-hero.php' === $file ? 1 : 0 ] ) );
		},
		$pattern_files
	),
	array(
		'wp-block-group alignwide wpc-hub-hero | padding-top:0;padding-right:0;padding-bottom:52px;padding-left:0',
		'wp-block-group alignwide wpc-hub-doors | padding-top:48px;padding-bottom:48px',
		'wp-block-group alignwide wpc-hub-anchored | padding-top:48px;padding-bottom:48px',
		'wp-block-group alignwide wpc-hub-anchored | padding-top:48px;padding-bottom:48px',
		'wp-block-group alignwide wpc-hub-cta | padding-top:48px;padding-bottom:48px',
	) );

$hero              = wpcredits_test_pattern( 'hub-hero.php' );
$GLOBALS['marked'] = true;
$marked_hero       = wpcredits_test_pattern( 'hub-hero.php' );
$GLOBALS['marked'] = false;
$hx                = dom_of( $hero );

// The design note's call: "Every step counts. Keep climbing." became two strings, so the brush can sit
// on the second, and the heading still reads as one, in order.
ck( 'the hero: its title in three strings, read as one heading in order, the second and the third in an em, the third, "Keep climbing.", in the span the brush is drawn on, and the 1.24.17 string of the two together gone; and after the columns, in a row of its own inside the hero, four figure blocks, three figures and their source',
	array(
		found( dom_of( $marked_hero ), '//h1' ),
		found( $hx, '//h1' ),
		found( $hx, '//h1/em' ),
		found( $hx, '//h1/em/span', 'class' ),
		found( $hx, '//h1/em/span' ),
		count( $hx->query( '//h1//*' ) ),
		substr_count( theme_file( 'patterns/hub-hero.php' ), "'Every step counts. Keep climbing.'" ),
		found( $hx, '/html/body/div/*', 'class' ),
		preg_match( '#<!-- wp:group \{"className":"wpc-hub-figures","layout":\{"type":"default"\}\} -->\s*<div class="wp-block-group wpc-hub-figures">((?:\s*<!-- wp:wpcredits/program-stat \{"stat":"[a-z-]+"\} /-->)+)</div>\s*<!-- /wp:group --></div>\s*<!-- /wp:group -->\s*$#', $hero, $figures_row ) && preg_match_all( '#<!-- wp:wpcredits/program-stat \{"stat":"([a-z-]+)"\} /-->#', $figures_row[1], $stats ) ? $stats[1] : array(),
		preg_match_all( '#<!-- wp:wpcredits/program-stat #', $hero ),
	),
	array(
		array( '[Start learning with WordPress.] [Every step counts.] [Keep climbing.]' ),
		array( 'Start learning with WordPress. Every step counts. Keep climbing.' ),
		array( 'Every step counts. Keep climbing.' ),
		array( 'wpc-hub-brush' ),
		array( 'Keep climbing.' ),
		2,
		0,
		array( 'wp-block-columns wpc-hub-hero__cols wpc-hub-stack', 'wp-block-group wpc-hub-figures' ),
		array( 'graduation', 'recommend', 'keep', 'outcomes-source' ),
		4,
	) );

// What a keyboard or a pointer could land on, inside a picture that must offer neither.
// An SVG use element's href names what it draws, not a place to go, and takes no focus.
$focusable = 'self::a or self::button or self::input or self::select or self::textarea or self::iframe or self::summary or self::object or self::embed or self::audio or self::video or ( @href and not( self::use ) ) or @tabindex or @contenteditable or @onclick';

/**
 * The markup of every element an XPath query finds in some markup, one after another.
 *
 * @param string $html  The markup.
 * @param string $query The query.
 * @return string
 */
function markup_of( $html, $query ) {
	$xpath = dom_of( $html );
	$said  = '';

	foreach ( $xpath->query( $query ) as $node ) {
		$said .= $node->ownerDocument->saveHTML( $node );
	}

	return $said;
}

// The picture's steps, each the zoom it is drawn at and the widest column it is drawn in, and whether
// it fits the narrowest one, the next step's widest plus a pixel, or 320px for the last, to the pixel
// the browser paints, so no step is wider than the narrowest column it is drawn in. The steps up, from
// the narrowest column each is drawn in, the same way: each no wider than that column.
$art_rules = rules_placed( '.wpc-hub-art' );
$art_zoom  = array();
$art_grow  = array();

foreach ( $art_rules as $art_rule ) {
	if ( preg_match( '/^@container wpc-hub-art \( max-width: (\d+)px \)$/', $art_rule[0], $art_width ) && preg_match( '/^zoom: ([\d.]+);$/', $art_rule[1], $art_by ) ) {
		$art_zoom[ (int) $art_width[1] ] = (float) $art_by[1];
	} elseif ( preg_match( '/^@container wpc-hub-art \( min-width: (\d+)px \)$/', $art_rule[0], $art_width ) && preg_match( '/^zoom: ([\d.]+);$/', $art_rule[1], $art_by ) ) {
		$art_grow[ (int) $art_width[1] ] = (float) $art_by[1];
	}
}

krsort( $art_zoom );
ksort( $art_grow );

$art_drawn = isset( $art_rules[0] ) && preg_match( '/(?:^|; )width: (\d+)px;/', $art_rules[0][1], $art_wide ) ? (int) $art_wide[1] : 0;
$art_steps = array_keys( $art_zoom );
$art_fits  = array();

foreach ( $art_steps as $art_at => $art_widest ) {
	$art_fits[ $art_widest ] = round( $art_drawn * $art_zoom[ $art_widest ] ) <= ( isset( $art_steps[ $art_at + 1 ] ) ? $art_steps[ $art_at + 1 ] + 1 : 320 );
}

$art_grows = array();

foreach ( $art_grow as $art_narrowest => $art_by ) {
	$art_grows[ $art_narrowest ] = $art_by > 1 && round( $art_drawn * $art_by ) <= $art_narrowest;
}

// The room the picture keeps in the column for the mountain, from what the pattern draws: above the
// picture as far as the flag's top, the highest point of its pole and pennant, below it as far as the
// mountain's foot runs past its 380px, so the column holds the whole drawing, the flag's top is its
// top and the mountain's foot is its foot.
$art_flag = preg_match_all( '/class="wpc-hub-art__(?:pole|pennant)" d="([^"]+)"/', $hero, $art_paths ) ? $art_paths[1] : array();
$art_foot = preg_match( '/<mask id="wpc-hub-mountain-body"[^>]*><path d="([^"]+)"/', $hero, $art_body ) ? $art_body[1] : '';
$art_ys   = static function ( $path ) {
	preg_match_all( '/(?:[MLHVZ]\s*)?(-?\d+)(?:\s+(-?\d+))?/', $path, $pairs, PREG_SET_ORDER );
	$ys = array();
	foreach ( $pairs as $pair ) {
		if ( 'V' === substr( ltrim( $pair[0] ), 0, 1 ) ) {
			$ys[] = (int) $pair[1];
		} elseif ( isset( $pair[2] ) && '' !== $pair[2] ) {
			$ys[] = (int) $pair[2];
		}
	}
	return $ys;
};
$art_box  = $art_flag && '' !== $art_foot ? array( -min( array_merge( ...array_map( $art_ys, $art_flag ) ) ), max( $art_ys( $art_foot ) ) - 380 ) : array();

ck( 'beside the copy, the picture is decoration: a column of its own, 49% to the copy\'s 47% and set on the copy\'s foot, one Custom HTML block whose one element is hidden from screen readers and holds no link, button, field or tab stop, its two drawings, the mountain and the chart, taking no focus; not shown below 900px, and from 900px drawn at 520 by 380px against the column\'s right edge, keeping the mountain\'s room above and below it, three classes deep so the flow layout\'s first- and last-child rules leave it, zoomed down in steps with its column, each step small enough for the narrowest column it is drawn in, down to 320px, so no piece is cut, and up in two steps, from 560px and from 600px, each no wider than the narrowest column it is drawn in',
	array(
		found( $hx, '//div[contains(concat(" ", @class, " "), " wpc-hub-hero__cols ")]/div', 'class' ),
		found( $hx, '//div[contains(concat(" ", @class, " "), " wpc-hub-hero__cols ")]/div', 'style' ),
		preg_match_all( '#<!-- wp:column \{"verticalAlignment":"bottom","width":"49%","className":"wpc-hub-hero__art"\} -->\s*<div class="wp-block-column is-vertically-aligned-bottom wpc-hub-hero__art" style="flex-basis:49%"><!-- wp:html -->\s*<div class="wpc-hub-art" aria-hidden="true">#', $hero ),
		found( $hx, '//div[contains(concat(" ", @class, " "), " wpc-hub-hero__art ")]/*', 'class' ),
		found( $hx, '//div[contains(concat(" ", @class, " "), " wpc-hub-hero__art ")]/*', 'aria-hidden' ),
		count( $hx->query( '//div[@class="wpc-hub-art"]//*[' . $focusable . ']' ) ),
		found( $hx, '//div[@class="wpc-hub-art"]//svg', 'focusable' ),
		rules_placed( '.wp-block-column.wpc-hub-hero__art' ),
		$art_rules[0] ?? null,
		rules_placed( '.wp-block-column.wpc-hub-hero__art > .wpc-hub-art' ),
		$art_box,
		$art_zoom,
		$art_fits,
		$art_grow,
		$art_grows,
	),
	array(
		array( 'wp-block-column wpc-hub-hero__copy', 'wp-block-column is-vertically-aligned-bottom wpc-hub-hero__art' ),
		array( 'flex-basis:47%', 'flex-basis:49%' ),
		1,
		array( 'wpc-hub-art' ),
		array( 'true' ),
		0,
		array( 'false', 'false' ),
		array(
			array( '', 'container-name: wpc-hub-art; container-type: inline-size; min-width: 0;' ),
			array( '@media ( max-width: 899px )', 'display: none;' ),
		),
		array( '', 'height: 380px; margin-left: auto; position: relative; width: 520px;' ),
		array( array( '', 'margin-bottom: 4px; margin-top: 98px;' ) ),
		array( 98, 4 ),
		array( 519 => 0.92, 479 => 0.845, 439 => 0.765, 399 => 0.69, 359 => 0.615 ),
		array( 519 => true, 479 => true, 439 => true, 399 => true, 359 => true ),
		array( 560 => 1.077, 600 => 1.154 ),
		array( 560 => true, 600 => true ),
	) );

// The glyphs the sheet draws inside the pictures, in its order: the content of every rule that names a
// piece of the hero's picture or of a door's.
preg_match_all( '/([^{}]*wpc-hub-(?:art|pic)[^{}]*)\{([^{}]*)\}/', preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) ), $drawn_rules, PREG_SET_ORDER );

$drawn_glyphs = array();

foreach ( $drawn_rules as $drawn_rule ) {
	if ( preg_match( '/(?:^|;)\s*content: "([^"]*)";/', $drawn_rule[2], $drawn_glyph ) ) {
		$drawn_glyphs[] = $drawn_glyph[1];
	}
}

$art_marked = markup_of( $marked_hero, '//div[@class="wpc-hub-art"]' );

ck( 'every word in the picture is a translated string, one the page says or the design gives it, the pieces\' seven with the third step\'s read from the hub\'s own list of steps: "Step 3 of 7", the step\'s name, what earns it and its first item, the headline\'s "Every step counts.", the chart\'s line and where the badge shows; the sheet draws only the editor\'s glyphs in it (the toolbar\'s, the W mark, the type card\'s "Aa", the plus and the ticks), no word',
	array(
		unmarked( $art_marked ),
		found( dom_of( markup_of( $hero, '//div[@class="wpc-hub-art"]' ) ), '//span[normalize-space(text())]' ),
		preg_match_all( '/\[[^\[\]]+\]/', strip_tags( markup_of( $marked_hero, '//div[@class="wpc-hub-art"]/div' ) ) ),
		$drawn_glyphs,
	),
	array(
		array(),
		array( 'Step 3 of 7', $steps[2]['name'], $steps[2]['earned'], 'Every step counts.', 'From first workshop to first job', $steps[2]['items'][0]['text'], 'On your WordPress.org profile' ),
		7,
		array( '\25A6', '\22EE', '\25B2', 'B', 'I', '\2197', 'W', 'Aa', '', '+', '\2713' ),
	) );

// The mountain behind the pieces (1.24.22). Its rows are read for what they promise rather than
// rebuilt: where each begins in the hub's list, that each name is followed by the next, and that each
// reaches across the drawing from where it starts, at the monospace's 0.6em a character.
$mountain      = '//div[@class="wpc-hub-art"]/svg[@class="wpc-hub-art__mountain"]';
$mountain_rows = found( $hx, $mountain . '/defs/g[@id="wpc-hub-mountain-words"]/text' );
$mountain_xs   = array_map( 'intval', found( $hx, $mountain . '/defs/g/text', 'x' ) );
$names         = array_column( $steps, 'name' );
$row_firsts    = array();
$row_order     = array();
$row_across    = array();

foreach ( $mountain_rows as $row_at => $row_text ) {
	$row_names    = explode( ' // ', $row_text );
	$row_firsts[] = array_search( $row_names[0], $names, true );

	foreach ( array_slice( $row_names, 1 ) as $name_at => $row_name ) {
		$row_order[] = ( array_search( $row_names[ $name_at ], $names, true ) + 1 ) % count( $names ) === array_search( $row_name, $names, true );
	}

	$row_across[] = $mountain_xs[ $row_at ] <= -10 && $mountain_xs[ $row_at ] + mb_strlen( $row_text ) * 9 * 0.6 >= 530 && ( 0 === $row_at || $mountain_xs[ $row_at ] !== $mountain_xs[ $row_at - 1 ] );
}

$mountain_path = static function ( $query ) use ( $hx, $mountain ) {
	return found( $hx, $mountain . $query, 'd' );
};

$shape       = $mountain_path( '/defs/mask[@id="wpc-hub-mountain-body"]/path[1]' )[0] ?? '';
$trail       = $mountain_path( '/path[@class="wpc-hub-art__route"]' )[0] ?? '';
$cap         = $mountain_path( '/defs/mask[@id="wpc-hub-mountain-body"]/path[2]' )[0] ?? '';
$shape_at    = preg_match_all( '/(-?\d+) (-?\d+)/', $shape, $shape_points ) ? array_map( 'intval', $shape_points[2] ) : array( 0 );
$summit      = $shape_points[0][ array_search( min( $shape_at ), $shape_at, true ) ] ?? '';
$trail_ends  = preg_match_all( '/(-?\d+) (-?\d+)/', $trail, $trail_points ) ? array( $trail_points[0][0], end( $trail_points[0] ) ) : array();
$view_box    = preg_match( '/<svg class="wpc-hub-art__mountain" viewBox="([^"]*)"/', $hero, $view_said ) ? explode( ' ', $view_said[1] ) : array();
$mountain_on = preg_match_all( '/id="(wpc-hub-mountain-[a-z]+)"/', $hero, $mountain_ids ) ? array_count_values( $mountain_ids[1] ) : array();

ck( 'behind the pieces, the picture\'s first element, a mountain drawn in words: 42 rows 12px apart, from 96px above the picture to 4px under it, each the step names in the hub\'s order, joined by " //", the first row begun at the first step and each row three names further on than the row above, every row starting left of the drawing, set apart from the row above and long enough to cross it; the rows written once and drawn twice, through the mountain\'s mask in grey and the snow cap\'s in the brand blue, both cut by the trail, which runs from the mountain\'s foot to its summit with the dashed route along it and the flag\'s pole on the summit; ids on the page once each',
	array(
		found( $hx, '//div[@class="wpc-hub-art"]/*', 'class' )[0] ?? null,
		count( $mountain_rows ),
		array_map( 'intval', found( $hx, $mountain . '/defs/g/text', 'y' ) ),
		$row_firsts,
		array_unique( $row_order ),
		array_unique( $row_across ),
		found( $hx, $mountain . '/use', 'href' ),
		found( $hx, $mountain . '/use', 'class' ),
		found( $hx, $mountain . '/use', 'mask' ),
		$mountain_path( '/defs/mask[@id="wpc-hub-mountain-body"]/path' ),
		$mountain_path( '/defs/mask[@id="wpc-hub-mountain-cap"]/path' ),
		found( $hx, $mountain . '/defs/mask/path[3]', 'stroke-width' ),
		$trail_ends,
		array( $summit, max( $shape_at ) ),
		$mountain_path( '/path[@class="wpc-hub-art__pole"]' ),
		found( $hx, $mountain, 'focusable' ),
		$mountain_on,
	),
	array(
		'wpc-hub-art__mountain',
		42,
		range( -96, 396, 12 ),
		array_map( static function ( $row ) { return ( $row * 3 ) % 7; }, range( 0, 41 ) ),
		array( true ),
		array( true ),
		array( '#wpc-hub-mountain-words', '#wpc-hub-mountain-words' ),
		array( 'wpc-hub-art__mountain-body', 'wpc-hub-art__mountain-cap' ),
		array( 'url(#wpc-hub-mountain-body)', 'url(#wpc-hub-mountain-cap)' ),
		array( $shape, $cap, $trail ),
		array( $shape, 'M-10 -104 H530 V390 H-10 Z ' . $cap, $trail ),
		array( '14', '14' ),
		array( '150 384', '390 -70' ),
		array( '390 -70', 384 ),
		array( 'M390 -70 V-98' ),
		array( 'false' ),
		array( 'wpc-hub-mountain-words' => 1, 'wpc-hub-mountain-body' => 1, 'wpc-hub-mountain-cap' => 1 ),
	) );

ck( 'the sheet sets the mountain in the picture\'s own space, the box its view box names (10px left of the picture and 104px above it, 540 by 494px), so it zooms with the picture, takes no pointer, and writes its words in the monospace at 9px, the mountain in grey, the cap and the pennant in the brand blue, the route dashed in the blue and the pole in ink',
	array(
		array( $view_box, rules_placed( '.wpc-hub-art__mountain' )[0][1] ?? '' ),
		rules_for( '.wpc-hub-art__mountain-body' ),
		rules_for( '.wpc-hub-art__mountain-cap, .wpc-hub-art__pennant' ),
		rules_for( '.wpc-hub-art__route' ),
		rules_for( '.wpc-hub-art__pole' ),
	),
	array(
		array( array( '-10', '-104', '540', '494' ), 'font-family: var( --wp--preset--font-family--mono ); font-size: 9px; height: 494px; left: -10px; pointer-events: none; position: absolute; top: -104px; width: 540px;' ),
		array( 'fill: var( --wpc-ink-60 );' ),
		array( 'fill: var( --wpc-brand );' ),
		array( 'fill: none; stroke: var( --wpc-brand ); stroke-dasharray: 4 5; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.5;' ),
		array( 'stroke: var( --wpc-ink ); stroke-width: 1.5;' ),
	) );

$doors_html        = wpcredits_test_pattern( 'hub-doors.php' );
$GLOBALS['marked'] = true;
$marked_doors      = markup_of( wpcredits_test_pattern( 'hub-doors.php' ), '//div[@class="wpc-hub-pic"]' );
$GLOBALS['marked'] = false;
$dx                = dom_of( $doors_html );
$door_card         = '//div[contains(concat(" ", @class, " "), " wpc-hub-door ")]';

ck( 'each door opens with its picture, the card\'s first element, from a Custom HTML block: hidden from screen readers and holding no link, button, field or tab stop, so the card is heard once, from its eyebrow on, and its own link stays the one way in; the picture\'s words the door\'s own, each a translated string',
	array(
		found( $dx, $door_card . '/*[1]', 'class' ),
		found( $dx, $door_card . '/*[1]', 'aria-hidden' ),
		found( $dx, $door_card . '/*[2]', 'class' ),
		preg_match_all( '#<!-- wp:html -->\s*<div class="wpc-hub-pic" aria-hidden="true">#', $doors_html ),
		count( $dx->query( '//div[@class="wpc-hub-pic"]//*[' . $focusable . ']' ) ),
		array_map( static function ( $at ) use ( $dx ) { return found( $dx, '(//div[@class="wpc-hub-pic"])[' . $at . ']//span[normalize-space(text())]' ); }, array( 1, 2, 3 ) ),
		unmarked( $marked_doors ),
		preg_match_all( '/\[[^\[\]]+\]/', strip_tags( $marked_doors ) ),
	),
	array(
		array_fill( 0, 3, 'wpc-hub-pic' ),
		array_fill( 0, 3, 'true' ),
		array_fill( 0, 3, 'is-style-eyebrow' ),
		3,
		0,
		array( array( 'Your first contribution', 'Earn credit for it' ), array( 'Ready-to-run program', 'One-day workshop', 'WordPress Credits pilot' ), array( 'Verified WordPress experience', 'Graduates open to work' ) ),
		array(),
		7,
	) );

echo "\n=== The page ===\n";

WPCPM_Outcome_Figures::$published = $monday;
$page_html                        = wpcredits_test_page();
$page                             = dom_of( $page_html );

ck( 'without JavaScript the page holds every part: the three figures and their source, the four sign-in cards, the doors, six programs, the seven rows of the field-of-study flow, both ladders with all eleven details, and the band',
	array( count( $page->query( '//div[contains(@class,"wpc-figure")]' ) ), count( $page->query( '//p[contains(@class,"wpc-figure-source")]' ) ), count( $page->query( '//div[contains(@class,"wpc-hub-sign-in__card")]' ) ), count( $page->query( '//div[contains(concat(" ", @class, " ")," wpc-hub-doors ")]//div[contains(concat(" ", @class, " ")," wpc-hub-door ")]' ) ), count( $page->query( '//article[@class="wpc-hub-program"]' ) ), count( $page->query( '//ul[@class="wpc-hub-flow__rows"]/li[@class="wpc-hub-flow__row"]' ) ), count( $page->query( '//section[@class="wpc-hub-ladder"]' ) ), count( $page->query( '//article[@class="wpc-hub-detail"]' ) ), count( $page->query( '//div[contains(@class,"wpc-hub-cta__card")]' ) ) ),
	array( 3, 1, 4, 3, 6, 7, 2, 11, 2 ) );

ck( 'the sections in the page\'s order: the hero, the sign-in section right under it, the doors, the programs, the pathway and the band',
	array_map( static function ( $class ) { return implode( ' ', array_intersect( array( 'wpc-hub-hero', 'wpc-hub-sign-in', 'wpc-hub-doors', 'wpc-hub-anchored', 'wpc-hub-cta' ), explode( ' ', $class ) ) ); }, found( $page, '/html/body/*', 'class' ) ),
	array( 'wpc-hub-hero', 'wpc-hub-sign-in', 'wpc-hub-doors', 'wpc-hub-anchored', 'wpc-hub-anchored', 'wpc-hub-cta' ) );

ck( 'and nothing on it is hidden but the controls the scripts show: the chips, the switch and each ladder\'s hint',
	found( $page, '//*[@hidden]', 'class' ), array( 'wpc-hub-chips', 'wpc-hub-switch', 'wpc-hub-ladder__hint', 'wpc-hub-ladder__hint' ) );

ck( 'the headings in order: the one h1, a heading for every section, the sign-in section\'s second, the doors\' for screen readers',
	found( $page, '//h1 | //h2' ),
	array( 'Start learning with WordPress. Every step counts. Keep climbing.', 'Sign in to your page', 'Who this is for', 'Every education program, in one list', 'Seven steps from first workshop to first job', 'Four partner levels, measured on student outcomes', 'Looking for WordPress talent?', 'Help more students reach the next step' ) );

/**
 * Every link on the page, in order: its words, then where it goes.
 *
 * @param DOMXPath $xpath The page.
 * @return string[]
 */
function links_on( DOMXPath $xpath ) {
	$said = array();

	foreach ( $xpath->query( '//a[@href]' ) as $link ) {
		$said[] = trim( preg_replace( '/\s+/u', ' ', $link->textContent ) ) . ' > ' . $link->getAttribute( 'href' );
	}

	return $said;
}

$apply   = 'https://wordpresseducation.org/apply/';
$sponsor = 'https://wordpresseducation.org/sponsor-application/';

ck( 'every link of the design note\'s 6.9, in the page\'s order, the site\'s own pages read from the plugin, none opening a new tab',
	array( links_on( $page ), count( $page->query( '//a[@target]' ) ) ),
	array(
		array(
			'Find where to start > #programs',
			'Explore the pathway > #pathway',
			'WordPress Credits impact dashboard > https://wordpress.github.io/WPCredits/',
			'Sign in → > ' . $sign_login( $sign_pages['students'] ),
			'Sign in → > ' . $sign_login( $sign_pages['mentors'] ),
			'Sign in → > ' . $sign_login( $sign_pages['institutions'] ),
			'Sign in → > ' . $sign_login( $sign_pages['sponsors'] ),
			'Browse programs → > #programs',
			'Become a partner → > ' . $apply,
			'Hire from WordPress → > https://jobs.wordpress.net/',
			'Find or host an event → > https://events.wordpress.org/campusconnect/',
			'Compare the five courses → > #wordpress-credits-courses',
			'About the program → > https://wordpress.org/education/credits/',
			'Impact dashboard → > https://wordpress.github.io/WPCredits/',
			'Facilitation guides → > https://make.wordpress.org/community/handbook/education/wordpress-facilitator-training-program/',
			'Courses → > https://learn.wordpress.org/',
			'Browse courses → > https://learn.wordpress.org/',
			'Learn more → > https://ai-leaders.org/',
			'Learn more → > https://wordpresscertifications.com/wordpress-builder-certification/',
			'Intro to Contribution → > https://learn.wordpress.org/course/wordpress-credits-intro-to-contribution/',
			'Foundations → > https://learn.wordpress.org/course/wordpress-credits-foundations/',
			'Developer track → > https://learn.wordpress.org/course/wordpress-credits-developer-track/',
			'Designer track → > https://learn.wordpress.org/course/wordpress-credits-designer-track/',
			'Translation track → > https://learn.wordpress.org/course/wordpress-credits-translation-track/',
			'Step 1 Attendee > #wpc-hub-step-students-1',
			'Step 2 Builder > #wpc-hub-step-students-2',
			'Step 3 Contributor > #wpc-hub-step-students-3',
			'Step 4 Graduate > #wpc-hub-step-students-4',
			'Step 5 Specialist > #wpc-hub-step-students-5',
			'Step 6 Community Leader > #wpc-hub-step-students-6',
			'Step 7 Credentialed > #wpc-hub-step-students-7',
			'Find a Campus Connect event > https://events.wordpress.org/campusconnect/',
			'Learn to build a site > https://learn.wordpress.org/',
			'Start WordPress Credits > #program-wordpress-credits',
			'See the courses > #wordpress-credits-courses',
			'Explore programs > #programs',
			'Try Facilitator Training > #program-facilitator-training',
			'Where this leads: WordPress Jobs > https://jobs.wordpress.net/',
			'Level 1 Participating > #wpc-hub-step-institutions-1',
			'Level 2 Partner > #wpc-hub-step-institutions-2',
			'Level 3 Established > #wpc-hub-step-institutions-3',
			'Level 4 Flagship > #wpc-hub-step-institutions-4',
			'Request a Campus Connect event > https://events.wordpress.org/campusconnect/organize/',
			'Partner as an institution > ' . $apply,
			'Try Facilitator Training > #program-facilitator-training',
			'Talk to the program team > ' . $apply,
			'Hire from WordPress > https://jobs.wordpress.net/',
			'Sponsor > ' . $sponsor,
			'Partner as an institution > ' . $apply,
		),
		0,
	) );

ck( 'every in-page link lands on something on the page',
	array_values( array_filter( array_map( static function ( $href ) use ( $page ) { return 0 === $page->query( '//*[@id="' . substr( $href, 1 ) . '"]' )->length ? $href : null; }, preg_grep( '/^#/', found( $page, '//a', 'href' ) ) ) ) ),
	array() );

ck( 'and every arrow is decorative, read by no screen reader',
	array( count( $page->query( '//a/span[@aria-hidden="true"]' ) ), substr_count( $page_html, '→' ) ),
	array( 21, 21 ) );

WPCPM_Institution_Application::$url = '';
WPCPM_Sponsor_Application::$url     = '';
$unpublished_page                   = links_on( dom_of( wpcredits_test_page() ) );
WPCPM_Institution_Application::$url = 'https://wordpresseducation.org/apply/';
WPCPM_Sponsor_Application::$url     = 'https://wordpresseducation.org/sponsor-application/';

ck( 'with either application\'s page not published, its five links go to the WordPress Credits handbook',
	array_values( preg_grep( '#community/handbook/education/credits/$#', $unpublished_page ) ),
	array(
		'Become a partner → > https://make.wordpress.org/community/handbook/education/credits/',
		'Partner as an institution > https://make.wordpress.org/community/handbook/education/credits/',
		'Talk to the program team > https://make.wordpress.org/community/handbook/education/credits/',
		'Sponsor > https://make.wordpress.org/community/handbook/education/credits/',
		'Partner as an institution > https://make.wordpress.org/community/handbook/education/credits/',
	) );

echo "\n=== Find where you fit: the flow from the field of study ===\n";

// The owner (18 October 2026): the three-column table read as a wall of text; "it needs to visually
// show the flow between the field of study, through the contribution team, finishing on a proper real
// world skill". Option A, approved: one row per field, read left to right, the field, an arrow, the
// teams as tags, an arrow, what you'll have to show behind a check mark.

/**
 * An XPath test that an element carries a class, as a whole word of its class attribute.
 *
 * @param string $name The class.
 * @return string
 */
function flow_class( $name ) {
	return 'contains(concat(" ", normalize-space(@class), " "), " ' . $name . ' ")';
}

/**
 * What a screen reader hears of an element: its text with every part hidden by `aria-hidden="true"`
 * left out, each element's text apart from its neighbor's, whitespace run together.
 *
 * @param DOMNode $node The element.
 * @return string
 */
function heard( DOMNode $node ) {
	if ( XML_TEXT_NODE === $node->nodeType ) {
		return $node->nodeValue;
	}

	if ( XML_ELEMENT_NODE !== $node->nodeType || 'true' === $node->getAttribute( 'aria-hidden' ) ) {
		return '';
	}

	$said = '';

	foreach ( $node->childNodes as $child ) {
		$said .= ( XML_ELEMENT_NODE === $child->nodeType ? ' ' : '' ) . heard( $child );
	}

	return trim( preg_replace( '/\s+/u', ' ', $said ) );
}

/**
 * What an XPath query finds below one element, as found() says it: each node's text, whitespace run
 * together, or one attribute of each.
 *
 * @param DOMNode $node      The element the query starts from.
 * @param string  $query     The query, relative to it.
 * @param string  $attribute An attribute's name, or '' for the text.
 * @return string[]
 */
function found_in( DOMNode $node, $query, $attribute = '' ) {
	$said = array();

	foreach ( ( new DOMXPath( $node->ownerDocument ) )->query( $query, $node ) as $hit ) {
		$said[] = '' === $attribute ? trim( preg_replace( '/\s+/u', ' ', $hit->textContent ) ) : (string) $hit->getAttribute( $attribute );
	}

	return $said;
}

$flow_section = '//div[' . flow_class( 'wpc-hub-fit' ) . ']';
$flow         = $flow_section . '/div[@class="wpc-hub-flow"]';
$flow_rows    = $flow . '/ul[@class="wpc-hub-flow__rows"]/li[@class="wpc-hub-flow__row"]';
$flow_row_els = $page->query( $flow_rows );
$flow_pattern = theme_file( 'patterns/hub-programs.php' );
$flow_drawn   = wpcredits_test_pattern( 'hub-programs.php' );
$look_flow    = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) );

ck( 'the section keeps its head as it was: the eyebrow, the heading "Find where you fit in WordPress Credits" anchored at #wpc-hub-fit, so a link to it lands under the sticky header, and the lede',
	array( found( $page, $flow_section . '/div[' . flow_class( 'wpc-hub-head' ) . ']/*' ), found( $page, $flow_section . '//h3', 'id' ), found( $page, $flow_section . '//h3', 'class' ) ),
	array(
		array( 'Matched to your field of study', 'Find where you fit in WordPress Credits', 'You work in the area of WordPress that fits your degree, so a design student shows design work and a CS student shows reviewed code.' ),
		array( 'wpc-hub-fit' ),
		array( 'wp-block-heading wpc-hub-anchored' ),
	) );

ck( 'the table is gone, and the flow follows the head in its place, in a Custom HTML block: the section holds the head and the flow and nothing else, no table and no element of the old table\'s class',
	array(
		array_map( static function ( $node ) { return $node->getAttribute( 'class' ); }, iterator_to_array( $page->query( $flow_section . '/*' ) ) ),
		count( $page->query( $flow_section . '//table | //*[' . flow_class( 'wpc-hub-fields' ) . ']' ) ),
		(bool) preg_match( '#<!-- /wp:group -->\s*<!-- wp:html -->\s*<div class="wpc-hub-flow">.*</div>\s*<!-- /wp:html --></div>\s*<!-- /wp:group --></div>\s*<!-- /wp:group -->\s*$#s', $flow_drawn ),
		substr_count( $flow_pattern, 'wp:table' ),
	),
	array( array( 'wp-block-group wpc-hub-head', 'wpc-hub-flow' ), 0, true, 0 ) );

ck( 'one header line above the rows names the three steps in the order a row reads them, and is hidden from screen readers, since each row\'s own labels say the same',
	array( found( $page, $flow . '/*[1]', 'class' ), found( $page, $flow . '/div[@class="wpc-hub-flow__head"]', 'aria-hidden' ), found( $page, $flow . '/div[@class="wpc-hub-flow__head"]/span' ), found( $page, $flow . '/div[@class="wpc-hub-flow__head"]/span', 'class' ) ),
	array(
		array( 'wpc-hub-flow__head' ),
		array( 'true' ),
		array( 'Your field of study', 'The team you join', 'What you\'ll have to show' ),
		array( 'wpc-hub-flow__step wpc-hub-flow__step--field', 'wpc-hub-flow__step wpc-hub-flow__step--teams', 'wpc-hub-flow__step wpc-hub-flow__step--proof' ),
	) );

$flow_read = array();

foreach ( $flow_row_els as $flow_row ) {
	$flow_read[] = array(
		found_in( $flow_row, 'p[@class="wpc-hub-flow__field"]' ),
		found_in( $flow_row, 'div[@class="wpc-hub-flow__teams"]/ul[@class="wpc-hub-flow__tags"]/li[@class="wpc-hub-flow__tag"]' ),
		found_in( $flow_row, 'p[@class="wpc-hub-flow__proof"]/span[@class="wpc-hub-flow__shown"]/text()' ),
	);
}

ck( 'seven rows in the table\'s order, each the field, its teams one tag each, and what you\'ll have to show: every field and every proof word for word as before, each team a name of its own, capitalized as one',
	$flow_read,
	array(
		array( array( 'Computer science, engineering' ), array( 'Core', 'Plugins', 'Testing', 'Accessibility' ), array( 'Merged code and reviewed patches' ) ),
		array( array( 'Design, arts, UX' ), array( 'Design', 'Block patterns', 'Themes' ), array( 'Visual work in use on real sites' ) ),
		array( array( 'Communications, journalism, marketing' ), array( 'Documentation', 'Learn WordPress', 'Marketing' ), array( 'Published work with a byline' ) ),
		array( array( 'Translation, languages' ), array( 'Polyglots' ), array( 'Published translations' ) ),
		array( array( 'Business, events, tourism' ), array( 'Community', 'WordCamps' ), array( 'An event you ran, with attendance numbers' ) ),
		array( array( 'Education' ), array( 'Learn WordPress lesson plans', 'Training' ), array( 'Course material in public use' ) ),
		array( array( 'Accessibility, social work' ), array( 'Accessibility', 'Testing' ), array( 'Audit work on a global platform' ) ),
	) );

ck( 'every row reads the same way, left to right: the field, an arrow, the teams, an arrow, the proof, and nothing else',
	array_map( static function ( $row ) { return implode( ' ', found_in( $row, '*', 'class' ) ); }, iterator_to_array( $flow_row_els ) ),
	array_fill( 0, 7, 'wpc-hub-flow__field wpc-hub-flow__arrow wpc-hub-flow__teams wpc-hub-flow__arrow wpc-hub-flow__proof' ) );

ck( 'a screen reader hears each row as one item, in order: the field, "The team you join:" and the teams, "What you\'ll have to show:" and the proof, the arrows and the check left out; the list of rows is named by the section\'s heading',
	array( array_map( 'heard', iterator_to_array( $flow_row_els ) ), found( $page, $flow . '/ul[@class="wpc-hub-flow__rows"]', 'aria-labelledby' ) ),
	array(
		array(
			'Computer science, engineering The team you join: Core Plugins Testing Accessibility What you\'ll have to show: Merged code and reviewed patches',
			'Design, arts, UX The team you join: Design Block patterns Themes What you\'ll have to show: Visual work in use on real sites',
			'Communications, journalism, marketing The team you join: Documentation Learn WordPress Marketing What you\'ll have to show: Published work with a byline',
			'Translation, languages The team you join: Polyglots What you\'ll have to show: Published translations',
			'Business, events, tourism The team you join: Community WordCamps What you\'ll have to show: An event you ran, with attendance numbers',
			'Education The team you join: Learn WordPress lesson plans Training What you\'ll have to show: Course material in public use',
			'Accessibility, social work The team you join: Accessibility Testing What you\'ll have to show: Audit work on a global platform',
		),
		array( 'wpc-hub-fit' ),
	) );

ck( 'both of the flow\'s lists say they are lists, the rows\' and each row\'s teams\', since WebKit stops announcing a list whose bullets the sheet takes away',
	array( found( $page, $flow . '/ul', 'role' ), found( $page, $flow_rows . '/div[@class="wpc-hub-flow__teams"]/ul', 'role' ), count( $page->query( $flow . '//ul[not(@role="list")]' ) ) ),
	array( array( 'list' ), array_fill( 0, 7, 'list' ), 0 ) );

ck( 'each row\'s two step labels are core\'s visually hidden text, each right before what it names: the teams\' before their list, the proof\'s first in its words',
	array(
		count( $page->query( $flow_rows . '//*[' . flow_class( 'screen-reader-text' ) . ']' ) ),
		array_unique( array_map( static function ( $row ) { return implode( ' | ', found_in( $row, 'div[@class="wpc-hub-flow__teams"]/*[1][@class="screen-reader-text"] | div[@class="wpc-hub-flow__teams"]/*[2][@class="wpc-hub-flow__tags"]/@class | p[@class="wpc-hub-flow__proof"]/span[@class="wpc-hub-flow__shown"]/node()[1][@class="screen-reader-text"]' ) ); }, iterator_to_array( $flow_row_els ) ) ),
	),
	array( 14, array( 'The team you join: | wpc-hub-flow__tags | What you\'ll have to show:' ) ) );

ck( 'the decoration is silent and drawn inline: in each row two arrows and a check, each an svg hidden from screen readers and not focusable, with no words in it, drawn in currentColor; no icon font, no image and no character stands for them',
	array(
		count( $page->query( $flow_rows . '/*[@class="wpc-hub-flow__arrow"][local-name()="svg"][@aria-hidden="true"][@focusable="false"]' ) ),
		count( $page->query( $flow_rows . '/p[@class="wpc-hub-flow__proof"]/*[1][@class="wpc-hub-flow__check"][local-name()="svg"][@aria-hidden="true"][@focusable="false"]' ) ),
		count( $page->query( $flow . '//*[local-name()="svg"]' ) ),
		count( $page->query( $flow . '//*[local-name()="svg"][normalize-space()]' ) ),
		array( array_values( array_unique( found( $page, $flow . '//*[local-name()="svg"]', 'stroke' ) ) ), array_values( array_unique( found( $page, $flow . '//*[local-name()="svg"]', 'fill' ) ) ) ),
		count( $page->query( $flow . '//img | ' . $flow . '//i | ' . $flow . '//*[contains(@class,"dashicons")]' ) ),
		preg_match( '/[→↓✓✔]/u', implode( ' ', found( $page, $flow ) ) ),
	),
	array( 14, 7, 21, 0, array( array( 'currentColor' ), array( 'none' ) ), 0, 0 ) );

ck( 'every class the flow prints is the hub\'s own, named for the flow, or core\'s visually hidden text',
	array_values( array_unique( array_merge( ...array_map( static function ( $node ) { return explode( ' ', $node->getAttribute( 'class' ) ); }, iterator_to_array( $page->query( $flow . '/descendant-or-self::*[@class]' ) ) ) ) ) ),
	array( 'wpc-hub-flow', 'wpc-hub-flow__head', 'wpc-hub-flow__step', 'wpc-hub-flow__step--field', 'wpc-hub-flow__step--teams', 'wpc-hub-flow__step--proof', 'wpc-hub-flow__rows', 'wpc-hub-flow__row', 'wpc-hub-flow__field', 'wpc-hub-flow__arrow', 'wpc-hub-flow__teams', 'screen-reader-text', 'wpc-hub-flow__tags', 'wpc-hub-flow__tag', 'wpc-hub-flow__proof', 'wpc-hub-flow__check', 'wpc-hub-flow__shown' ) );

// "Education" is two strings: the field of study in this flow, and the accent word picked out of the
// site title, which must match the word in the translated title. One msgid would allow one translation
// for both; a language that names them differently (Polish: "Pedagogika" and "Edukacja") needs two.
$GLOBALS['translated'] = array(
	'Education'                       => 'Edukacja',
	"a field of study\4Education"      => 'Pedagogika',
);
$fields_translated = found( dom_of( wpcredits_test_pattern( 'hub-programs.php' ) ), '//li[@class="wpc-hub-flow__row"]/p[@class="wpc-hub-flow__field"]' );
$accent_translated = wpcredits_accent_word( 'WordPress Edukacja Initiatives' );
$GLOBALS['translated'] = array();

ck( 'the field of study "Education" and the site title\'s accent word "Education" are translated apart: the flow reads the field\'s own translation, the accent word the plain one',
	array( in_array( 'Pedagogika', $fields_translated, true ), in_array( 'Edukacja', $fields_translated, true ), $accent_translated ),
	array( true, false, 'WordPress <em>Edukacja</em> <i>Initiatives</i>' ) );

// A team's name is a word a translator meets alone ("Core", "Design", "Training"), so each is said
// with the context that tells what it names, and translated apart from the same word elsewhere.
$GLOBALS['translated'] = array(
	'Design'                                  => 'Wzornictwo',
	"a WordPress team a student joins\4Design" => 'Projektowanie',
	"a WordPress team a student joins\4Core"   => 'Rdzeń',
);
$teams_translated      = found( dom_of( wpcredits_test_pattern( 'hub-programs.php' ) ), '//li[@class="wpc-hub-flow__tag"]' );
$GLOBALS['translated'] = array();

preg_match_all( "/_x\( '([^']+)', 'a WordPress team a student joins', 'wpcredits-theme' \)/", $flow_pattern, $team_strings );

ck( 'each team is a string of its own, said with the context "a WordPress team a student joins": seventeen tags from fifteen names, a team translated in that context reads its translation, and the same word elsewhere does not',
	array( count( $team_strings[1] ), count( array_unique( $team_strings[1] ) ), array_slice( $teams_translated, 0, 5 ), substr_count( $flow_pattern, "'Core, plugins, testing, accessibility'" ) ),
	array( 17, 15, array( 'Rdzeń', 'Plugins', 'Testing', 'Accessibility', 'Projektowanie' ), 0 ) );

// Each of a row's three values is escaped where the row prints it: a translation holding an ampersand
// and a tag reads as those characters, not as markup.
$GLOBALS['translated'] = array(
	'Computer science, engineering'                       => 'R&D <i>field',
	"a WordPress team a student joins\4Core"               => 'R&D <i>team',
	'Merged code and reviewed patches'                    => 'R&D <i>proof',
);
$escaped_row           = dom_of( wpcredits_test_pattern( 'hub-programs.php' ) )->query( '//li[@class="wpc-hub-flow__row"]' )->item( 0 );
$GLOBALS['translated'] = array();

ck( 'a row\'s field, team and proof are escaped: translated to "R&D <i>" and a word, each reads exactly that, and the row holds no <i> element',
	array(
		found_in( $escaped_row, 'p[@class="wpc-hub-flow__field"]' ),
		found_in( $escaped_row, 'div[@class="wpc-hub-flow__teams"]/ul/li[1]' ),
		found_in( $escaped_row, 'p[@class="wpc-hub-flow__proof"]/span[@class="wpc-hub-flow__shown"]/text()' ),
		count( found_in( $escaped_row, './/i' ) ),
	),
	array( array( 'R&D <i>field' ), array( 'R&D <i>team' ), array( 'R&D <i>proof' ), 0 ) );

ck( 'the two step labels a screen reader hears are strings of their own, each with a note for translators that says where it is read; the header line\'s three are the steps\' names, "What you\'ll have to show" as the table said it',
	array(
		preg_match_all( "#/\* translators: [^*]+\*/\s*esc_html_e\( '(The team you join:|What you\\\\'ll have to show:)', 'wpcredits-theme' \)#", $flow_pattern ),
		preg_match_all( "#esc_html_e\( '(Your field of study|The team you join|What you\\\\'ll have to show)', 'wpcredits-theme' \)#", $flow_pattern ),
		substr_count( $flow_pattern, "'Field of study'" ) + substr_count( $flow_pattern, "'Where you\\'ll work'" ),
	),
	array( 2, 3, 0 ) );

echo "\n=== Find where you fit: the flow's look ===\n";

ck( 'the flow sits 22px under the section\'s head, as every section\'s content does: the head keeps its 22px, the flow\'s block gap is taken away two classes deep, and the section stays 56px under the courses band',
	array( rules_for( '.wp-block-group.wpc-hub-head' ), rules_for( '.wpc-hub-fit .wpc-hub-flow' ), rules_for( '.wp-block-group.wpc-hub-fit' ) ),
	array( array( 'margin-bottom: 22px;' ), array( 'margin: 0;' ), array( 'margin-top: 56px;' ) ) );

ck( 'the rows are a list without bullets or indent, each row a hairline below it in the theme\'s line color but the last, which ends the section, 96px above the pathway\'s eyebrow as every section ends; the teams\' list the same, its tags 6px apart and wrapping onto more lines when needed',
	array( rules_for( '.wpc-hub-flow .wpc-hub-flow__rows' ), rules_placed( '.wpc-hub-flow .wpc-hub-flow__row' )[0], rules_for( '.wpc-hub-flow .wpc-hub-flow__row:last-child' ), rules_for( '.wpc-hub-flow .wpc-hub-flow__tags' ) ),
	array(
		array( 'list-style: none; margin: 0; padding: 0;' ),
		array( '', 'border-bottom: 1px solid var( --wpc-line ); display: grid; gap: 4px; grid-template-columns: minmax( 0, 1fr ); justify-items: start; margin: 0; padding: 16px 0;' ),
		array( 'border-bottom: 0; padding-bottom: 0;' ),
		array( 'display: flex; flex-wrap: wrap; gap: 6px; list-style: none; margin: 0; padding: 0;' ),
	) );

ck( 'on a phone a row stacks the field, a down arrow, the teams, a down arrow and the proof at the section\'s left edge, 4px apart, the rows 16px from their hairlines and the first right under the head; the header line is not shown, and each arrow is the right arrow turned down, pulled 2px left so its drawn head, 2px inside its box, starts at the text\'s edge',
	array( rules_placed( '.wpc-hub-flow .wpc-hub-flow__head' )[0], rules_placed( '.wpc-hub-flow .wpc-hub-flow__row:first-child' )[0], rules_placed( '.wpc-hub-flow .wpc-hub-flow__arrow' )[0] ),
	array(
		array( '', 'display: none;' ),
		array( '', 'padding-top: 0;' ),
		array( '', 'color: var( --wpc-brand ); height: 18px; margin-left: -2px; transform: rotate( 90deg ); width: 18px;' ),
	) );

ck( 'from 768px a row is the approved sketch\'s grid, and the header line is shown on the same grid, one rule for both, so each name sits over its column: the field, a 28px column for the arrow, the teams at 1.1 parts, another arrow column and the proof, 8px apart and vertically centered',
	rules_placed( '.wpc-hub-flow .wpc-hub-flow__head, .wpc-hub-flow .wpc-hub-flow__row' ),
	array( array( '@media ( min-width: 768px )', 'align-items: center; column-gap: 8px; display: grid; grid-template-columns: minmax( 0, 1fr ) 28px minmax( 0, 1.1fr ) 28px minmax( 0, 1fr );' ) ) );

ck( 'and there each row is padded 12px, the first too, each column as wide as its track; the header line has a hairline under it, its names standing over the field, the teams and the proof and sitting on its foot, so a name that wraps onto two lines at 768px keeps the others 10px above the hairline as it is; the arrows point right, 20px, centered in their column with no margin',
	array(
		rules_placed( '.wpc-hub-flow .wpc-hub-flow__row' )[1],
		rules_placed( '.wpc-hub-flow .wpc-hub-flow__row:first-child' )[1],
		rules_placed( '.wpc-hub-flow .wpc-hub-flow__head' )[1],
		array( rules_for( '.wpc-hub-flow .wpc-hub-flow__step--field' ), rules_for( '.wpc-hub-flow .wpc-hub-flow__step--teams' ), rules_for( '.wpc-hub-flow .wpc-hub-flow__step--proof' ) ),
		rules_placed( '.wpc-hub-flow .wpc-hub-flow__arrow' )[1],
	),
	array(
		array( '@media ( min-width: 768px )', 'justify-items: stretch; padding: 12px 0;' ),
		array( '@media ( min-width: 768px )', 'padding-top: 12px;' ),
		array( '@media ( min-width: 768px )', 'align-items: end; border-bottom: 1px solid var( --wpc-line ); padding-bottom: 10px;' ),
		array( array( 'grid-column: 1;' ), array( 'grid-column: 3;' ), array( 'grid-column: 5;' ) ),
		array( '@media ( min-width: 768px )', 'height: 20px; justify-self: center; margin-left: 0; transform: none; width: 20px;' ),
	) );

ck( 'the step names are eyebrows, as the table\'s column heads were: 12px Inter capitals spaced out, in the theme\'s secondary ink',
	rules_for( '.wpc-hub-flow .wpc-hub-flow__step' ),
	array( 'color: var( --wpc-ink-60 ); font: 500 12px/18px var( --wpc-font ); letter-spacing: .06em; text-transform: uppercase;' ) );

ck( 'the field in the hub\'s serif, as the hub\'s other names are, 18px in ink; the arrows in the brand blue',
	array( rules_for( '.wpc-hub-flow .wpc-hub-flow__field' ), rules_placed( '.wpc-hub-flow .wpc-hub-flow__arrow' )[0][1] ),
	array( array( 'color: var( --wpc-ink ); font-family: var( --wpc-hub-serif ); font-size: 18px; font-weight: 400; line-height: 1.3; margin: 0;' ), 'color: var( --wpc-brand ); height: 18px; margin-left: -2px; transform: rotate( 90deg ); width: 18px;' ) );

ck( 'each team a tag: 13px Inter in the brand blue on the brand tint, a 1px brand-blue hairline, the hub\'s 2px corners; in forced colors only its border changes',
	rules_placed( '.wpc-hub-flow .wpc-hub-flow__tag' ),
	array(
		array( '', 'background: var( --wpc-brand-tint ); border: 1px solid var( --wpc-brand ); border-radius: var( --wpc-radius-control ); color: var( --wpc-brand ); font: 500 13px/18px var( --wpc-font ); margin: 0; padding: 2px 8px;' ),
		array( '@media ( forced-colors: active )', 'border-color: CanvasText;' ),
	) );

ck( 'what you\'ll have to show: 15px Inter in ink, led by a 16px check mark in the success green, set on its first line',
	array( rules_for( '.wpc-hub-flow .wpc-hub-flow__proof' ), rules_for( '.wpc-hub-flow .wpc-hub-flow__check' ) ),
	array(
		array( 'align-items: flex-start; color: var( --wpc-ink ); display: flex; font-size: 15px; gap: 8px; line-height: 1.45; margin: 0;' ),
		array( 'color: var( --wpc-green ); flex: none; height: 16px; margin-top: 3px; width: 16px;' ),
	) );

ck( 'the tags\' blue on the tint clears AA for their 13px text, and the field, the proof and the check read on the page\'s white',
	array( contrast( color_of( 'brand' ), color_of( 'brand-tint' ) ), contrast( color_of( 'ink' ), color_of( 'base' ) ), contrast( color_of( 'status.green' ), color_of( 'base' ) ) ),
	array( '5.03', '16.67', '5.08' ) );

ck( 'in forced colors, which drop the tint, the tags keep a border in the text color, and the arrows and the check, drawn in currentColor, take it too',
	array_values( preg_grep( '/wpc-hub-flow/', rules_in_media( '( forced-colors: active )' ) ) ),
	array(
		'.wpc-hub-flow .wpc-hub-flow__tag { border-color: CanvasText; }',
		'.wpc-hub-flow .wpc-hub-flow__arrow, .wpc-hub-flow .wpc-hub-flow__check { color: CanvasText; }',
	) );

ck( 'the table\'s rules and the filter that made its scroll box a region are gone: hub.css names no wpc-hub-fields, the theme hooks nothing to a rendered table, the pattern\'s note and the content file\'s no longer speak of a table, and the suites\' stand-in page hands no table block to a filter',
	array(
		substr_count( $look_flow, 'wpc-hub-fields' ),
		function_exists( 'wpcredits_hub_fields_region' ),
		isset( $GLOBALS['hooks']['render_block_core/table'] ),
		substr_count( theme_file( 'inc/hub.php' ), 'wpc-hub-fields' ),
		substr_count( $flow_pattern, 'Table block' ),
		substr_count( theme_file( 'inc/hub-content.php' ), 'field-of-study table' ),
		substr_count( theme_file( 'bin/stubs/hub-wp.php' ), 'wp:table' ),
	),
	array( 0, false, false, 0, 0, 0, 0 ) );

echo "\n=== The words ===\n";

$GLOBALS['marked'] = true;
$marked_page       = wpcredits_test_page();
$GLOBALS['marked'] = false;

ck( 'every word on the page, and every label and announcement, is a translated string',
	unmarked( $marked_page ), array() );

// The owner (17 October 2026): "Items marked planned are not offered yet." goes; the mark beside each
// item stays and says it itself.
ck( 'the pathway\'s footnote is gone: "Items marked planned are not offered yet." is not on the page nor in the pathway\'s render, no element carries its line\'s class, and the sheet has no rule for it; the 28 planned items on the page still say "(planned)" each',
	array(
		substr_count( $page_html, 'Items marked planned' ),
		count( $page->query( '//*[contains(@class,"wpc-hub-ladder__key")]' ) ),
		substr_count( theme_file( 'blocks/pathway/render.php' ), 'Items marked planned' ),
		substr_count( preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) ), 'wpc-hub-ladder__key' ),
		found( $page, '//span[@class="wpc-hub-planned"]' ) === array_fill( 0, 28, '(planned)' ),
	),
	array( 0, 0, 0, 0, true ) );

$words = trim( preg_replace( '/\s+/u', ' ', $page->query( '//body' )->item( 0 )->textContent ) );

ck( 'full product names: no "WP Credits", and the Institution Dashboard by its name, on its sign-in card and in the partner level\'s list; the Student Report Card, the Mentor Report Card and the Sponsor Dashboard on their cards',
	array( substr_count( $words, 'WP Credits' ), substr_count( strtolower( $words ), 'institution dashboard' ), substr_count( $words, 'Institution Dashboard' ), substr_count( $words, 'Student Report Card' ), substr_count( $words, 'Mentor Report Card' ), substr_count( $words, 'Sponsor Dashboard' ) ),
	array( 0, 2, 2, 1, 1, 1 ) );

ck( 'nothing says what sponsorship costs or what a sponsor receives: the sign-in card\'s eyebrow and the name of its page, the band\'s eyebrow and its button are the only words of sponsors, and the one sum on the page is a program\'s award',
	array( found( $page, '//body//*[not(*)][contains(translate(., "S", "s"), "sponsor")]' ), preg_match_all( '/\$\s?\d[\d,]*/', $words, $sums ), $sums[0] ),
	array( array( 'Sponsors', 'Sponsor Dashboard', 'Sponsors and schools', 'Sponsor' ), 1, array( '$1,000' ) ) );

echo "\n=== The page's look ===\n";

$class_of     = static function ( $name ) { return 'contains(concat(" ", normalize-space(@class), " "), " ' . $name . ' ")'; };
$figures_row  = '//div[' . $class_of( 'wpc-hub-hero' ) . ']/div[' . $class_of( 'wpc-hub-figures' ) . ']';
$figure_held  = './/*[' . $class_of( 'wpc-figure' ) . ']';
$look_sheet   = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) );
$row_shown_at = static function ( DOMXPath $xpath ) use ( $figures_row, $figure_held ) {
	return array( count( $xpath->query( $figures_row ) ), count( $xpath->query( $figures_row . '[not(' . $figure_held . ')]' ) ) );
};

WPCPM_Outcome_Figures::$published = array();
$page_unfigured                   = dom_of( wpcredits_test_page() );
WPCPM_Outcome_Figures::$published = $monday;

// The row is the hero group's own; it holds a figure only when the plugin has stored one, and its
// source line only then. The rule that hides it ties with the one that lays it out, two classes' worth
// each (`:not( :has( .wpc-figure ) )` weighs one), so it is written after it.
ck( 'with no figure to show, the figures row is not displayed, and its source line is not printed: the rule that hides the row is as deep as the one that lays it out and comes after it, so it wins; on the page it matches the row with no figure and not the row with three, and the 1.24.16 rules for a figures column are gone',
	array(
		rules_for( '.wpc-hub-figures:not( :has( .wpc-figure ) )' ),
		strpos( $look_sheet, '.wpc-hub-figures:not( :has( .wpc-figure ) ) {' ) > strpos( $look_sheet, '.wp-block-group.wpc-hub-figures {' ),
		$row_shown_at( $page_unfigured ),
		count( $page_unfigured->query( $figures_row . '/*' ) ),
		$row_shown_at( $page ),
		array( rules_for( '.wpc-hub-hero__cols:not( :has( .wpc-figure ) ) .wpc-hub-hero__figures' ), rules_for( '.wpc-hub-hero__cols:not( :has( .wpc-figure ) ) .wpc-hub-hero__copy' ) ),
	),
	array( array( 'display: none;' ), true, array( 1, 1 ), 0, array( 1, 0 ), array( array(), array() ) ) );

// In the Site Editor each figure is not one element but three: the block's wrapper (which the editor
// gives its own class, block-editor-block-list__block, beside the generated wp-block-wpcredits-program-stat),
// the preview's own div, and the server's figure. The wrappers are then the row's flex items, the row's
// block gap sits on them, and `.wpc-figure + .wpc-figure` finds no neighbor. The editor's shape is built
// here from the server's own figures; the rules' selectors are read as XPath, once on it and once on the
// front end's page.
$editor_wrapper  = '*[' . $class_of( 'block-editor-block-list__block' ) . '][' . $class_of( 'wp-block-wpcredits-program-stat' ) . ']';
$editor_margin   = '//*[' . $class_of( 'wpc-hub-figures' ) . ']/' . $editor_wrapper;
$editor_last     = $editor_margin . '[not(following-sibling::*)]';
$editor_hairline = $editor_margin . '/following-sibling::' . $editor_wrapper . '//*[' . $class_of( 'wpc-figure' ) . ']';
$in_the_editor   = dom_of( '<div class="wp-block-group wpc-hub-figures">' . implode( '', array_map( static function ( $figure ) { return '<div class="wp-block wp-block-wpcredits-program-stat block-editor-block-list__block"><div>' . $figure . '</div></div>'; }, outcome_blocks() ) ) . '</div>' );

ck( 'in the Site Editor, where each figure is the block\'s wrapper, the preview\'s div and then the server\'s figure, the wrappers share the row as the figures do, their margin reset, the last, the source line\'s, across the whole row, and the hairline drawn beside the figure inside the second and later wrappers: written on the editor\'s own wrapper class, so they match the four wrappers, the one source and the two later figures there, and nothing on the front end, whose figures carry no such class and hold no figure inside',
	array(
		rules_for( '.wpc-hub-figures > .block-editor-block-list__block.wp-block-wpcredits-program-stat' ),
		rules_for( '.wpc-hub-figures > .block-editor-block-list__block.wp-block-wpcredits-program-stat:last-child' ),
		rules_for( '.wpc-hub-figures > .block-editor-block-list__block.wp-block-wpcredits-program-stat ~ .block-editor-block-list__block.wp-block-wpcredits-program-stat .wpc-figure' ),
		array( count( $in_the_editor->query( $editor_margin ) ), count( $in_the_editor->query( $editor_last ) ), count( $in_the_editor->query( $editor_hairline ) ) ),
		array( count( $page->query( $editor_margin ) ), count( $page->query( $editor_last ) ), count( $page->query( $editor_hairline ) ), count( $page->query( '//*[contains(@class,"block-editor-block-list__block")]' ) ) ),
	),
	array( array( 'flex: 1 1 0; margin-block: 0; min-width: 0;' ), array( 'flex-basis: 100%;' ), array( 'border-left: 1px solid var( --wpc-line-card );' ), array( 4, 1, 2 ), array( 0, 0, 0, 0 ) ) );

ck( 'the hero, the doors and the band stack below 900px',
	array( rules_for( '.wp-block-columns.wpc-hub-stack' ), rules_for( '.wp-block-columns.wpc-hub-stack > .wp-block-column' ) ),
	array( array( 'flex-wrap: wrap !important;' ), array( 'flex-basis: 100% !important;' ) ) );

ck( 'a door\'s link covers the card, whose border turns brand blue under the pointer and whose outline is the focus ring',
	array( rules_for( '.wpc-hub-door__go a::after' ), rules_for( '.wp-block-group.wpc-hub-door:hover' ), rules_for( '.wp-block-group.wpc-hub-door:has( .wpc-hub-door__go a:focus-visible )' ) ),
	array( array( 'content: ""; inset: 0; position: absolute;' ), array( 'border-color: var( --wpc-brand );' ), array( 'outline: 2px solid var( --wpc-brand ); outline-offset: 2px;' ) ) );

ck( 'the door\'s link gives up its own focus outline for the card\'s ring only where :has() draws it, as a step does; elsewhere the theme\'s own ring stays',
	array_values( preg_grep( '/wpc-hub-door/', under_has_support() ) ),
	array( '.wpc-hub-door__go a:focus-visible { outline: none; }', '.wp-block-group.wpc-hub-door:has( .wpc-hub-door__go a:focus-visible ) { outline: 2px solid var( --wpc-brand ); outline-offset: 2px; }' ) );

preg_match_all( '/@media \( (?:max|min)-width: \d+px \)/', theme_file( 'assets/css/hub.css' ), $breakpoints );
$breakpoints = array_values( array_unique( $breakpoints[0] ) );
sort( $breakpoints );

ck( 'the hub\'s widths: the hero title\'s 38px up to 360px, the phone gutter up to 640px, the sign-in cards\' second column from 640px, the field-of-study flow\'s three steps side by side from 768px, where its longest team fits its column, and one breakpoint at 900px for the hero, the doors and the band, below it stacked and from it side by side, so at a 900px window nothing is both; the hero panel\'s 1099px went with the panel, and the picture\'s steps follow its column, not the window',
	$breakpoints, array( '@media ( max-width: 360px )', '@media ( max-width: 640px )', '@media ( max-width: 899px )', '@media ( min-width: 640px )', '@media ( min-width: 768px )', '@media ( min-width: 900px )' ) );

ck( 'anchored sections land below the sticky header',
	array( rules_for( '.wpc-hub-anchored' ), count( $page->query( '//*[contains(@class,"wpc-hub-anchored")][@id]' ) ) ),
	array( array( 'scroll-margin-top: var( --wpc-landing-offset );' ), 3 ) );

$has_classes = static function ( $one, $other ) use ( $page ) {
	return count( $page->query( '//div[contains(concat(" ", @class, " "), " ' . $one . ' ")][contains(concat(" ", @class, " "), " ' . $other . ' ")]' ) );
};

// The editor prints `:root :where( .is-layout-flex ) { gap: 20px }` for every flex layout: one class
// deep, so a one-class rule here ties with it, and loses where the sheet prints first.
ck( 'the hero\'s columns, the doors\' grid and the band\'s grid keep their gaps over the editor\'s 20px flex gap: written two classes deep, on elements that print both classes, and no one-class rule for them is left; the hero\'s 40px at every width, since stacked its columns hold the copy alone',
	array(
		array_values( array_filter( array_map( static function ( $rule ) { return preg_match( '/(?:^| )(gap: [^;]+;)/', $rule[1], $gap ) ? $gap[1] : ''; }, rules_placed( '.wp-block-columns.wpc-hub-hero__cols' ) ) ) ),
		rules_for( '.wp-block-columns.wpc-hub-doors__grid' ),
		rules_for( '.wp-block-columns.wpc-hub-cta__grid' ),
		array( rules_for( '.wpc-hub-hero__cols' ), rules_for( '.wpc-hub-doors__grid' ), rules_for( '.wpc-hub-cta__grid' ) ),
		array( $has_classes( 'wp-block-columns', 'wpc-hub-hero__cols' ), $has_classes( 'wp-block-columns', 'wpc-hub-doors__grid' ), $has_classes( 'wp-block-columns', 'wpc-hub-cta__grid' ) ),
	),
	array( array( 'gap: 40px;' ), array( 'gap: 16px;' ), array( 'gap: 16px;' ), array( array(), array(), array() ), array( 1, 1, 1 ) ) );

// The display size is a preset class whose size is `!important`, so the theme's 34px at 900px and
// below (style.css, one class, no `!important`) never applies to the hub's title, and each of the hub's
// own sizes is `!important` too.
ck( 'the hero\'s title is in the serif at regular weight, the display size from 44px on a phone, growing with the window to 70px, and 38px up to 360px so "Every step counts." keeps its line: !important, because the display size preset\'s is, and two classes deep, with no other size below 900px; its second and third sentences on a line of their own in ink, not the brand, since the brush carries the accent',
	array(
		rules_for( '.wpc-hub .wpc-hero__title, .wpc-hub .wpc-hub-head h2, .wpc-hub .wpc-hub-head h3, .wpc-hub .wpc-hub-ladder__title, .wpc-hub .wpc-hub-cta__card h2' ),
		rules_placed( '.wpc-hub-hero .wpc-hero__title' ),
		array_values( preg_grep( '/wpc-hero__title/', rules_in_media( '( max-width: 899px )' ) ) ),
		rules_placed( '.wpc-hub-hero .wpc-hero__title em' ),
	),
	array(
		array( 'color: var( --wpc-ink ); font-family: var( --wpc-hub-serif ); font-weight: 400; letter-spacing: -.01em;' ),
		array( array( '', 'font-size: clamp( 44px, 5.6vw, 70px ) !important; line-height: 1.02;' ), array( '@media ( max-width: 360px )', 'font-size: 38px !important;' ) ),
		array(),
		array( array( '', 'color: var( --wpc-ink ); display: block; font-style: normal; margin-top: .06em;' ) ),
	) );

// The brush is a picture the sheet holds, drawn behind "Keep climbing.", so the heading stays one line of
// text. Forced colors keep a background picture, not its colors, so there the rule drops it.
$brush = rules_placed( '.wpc-hub-hero .wpc-hub-brush' );

ck( 'the brush is the background of "Keep climbing.": a hand-drawn stroke of the brand blue with a lighter streak through it, in a picture the sheet holds, stretched to the words, which are white and kept on one line, the stroke reaching .26em before them and padded wide and tall enough that every letter sits on the blue, .12em lower so it clears the line above; in forced colors the stroke is dropped and the words take the system\'s colors',
	array(
		isset( $brush[0] ) ? array( $brush[0][0], preg_replace( '/^background: url\( "data:image\/svg\+xml,[^"]*" \) /', 'background: url( the brush ) ', $brush[0][1] ) ) : null,
		isset( $brush[0] ) && preg_match( "/fill='%23([0-9a-f]{6})'/", $brush[0][1], $brush_fill ) ? '#' . $brush_fill[1] === color_of( 'brand' ) : false,
		isset( $brush[0] ) && preg_match( "/stroke='%23[0-9a-f]{6}' stroke-width='[\d.]+' fill='none' opacity='[\d.]+'/", $brush[0][1] ),
		array_slice( $brush, 1 ),
	),
	array(
		array( '', 'background: url( the brush ) center / 100% 100% no-repeat; color: var( --wpc-surface ); display: inline-block; margin-left: -.26em; margin-top: .12em; padding: .1em .34em .2em .3em; white-space: nowrap;' ),
		true,
		true,
		array( array( '@media ( forced-colors: active )', 'background: none; color: inherit;' ) ),
	) );

// The hero sits on the page's white (1.24.18): no panel, so its columns draw no background and keep no
// padding at the sides. The pattern stores their padding on the columns themselves, none above and 52px
// below, so the Site Editor's padding control shows what is drawn. An inline style outranks every
// selector, so the rule needs no !important (it says the same, a fallback for a copy of the block that
// stores none), and no narrower width changes it. Read as they are written, in order.
$hero_cols  = '.wp-block-columns.wpc-hub-hero__cols';
$hero_row   = '.wp-block-group.wpc-hub-figures';
$hero_sheet = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) );
$cols_rules = rules_placed( $hero_cols );
$row_rules  = rules_placed( $hero_row );
$hero_px    = static function ( array $placed, $property ) {
	return isset( $placed[0] ) && preg_match( '/(?:^|; )' . $property . ': (\d+)px;/', $placed[0][1], $got ) ? (int) $got[1] : 0;
};
$cols_style = found( $page, '//div[contains(concat(" ", @class, " "), " wpc-hub-hero__cols ")]', 'style' );

preg_match_all( '/([^{}]*wpc-hub-hero[^{}]*)\{([^{}]*)\}/', $hero_sheet, $hero_rules, PREG_SET_ORDER );

// Every background a rule of the hero names, the brush's picture by that name.
$hero_grounds = array();

foreach ( $hero_rules as $hero_rule ) {
	if ( preg_match( '/(?:^|;)\s*background(?:-color|-image)?: ([^;]+);/', $hero_rule[2], $hero_ground ) ) {
		$hero_grounds[] = preg_replace( '/\s+/', ' ', trim( $hero_rule[1] ) ) . ': ' . preg_replace( '/^url\( "data:image\/svg\+xml,[^"]*" \).*$/', 'the brush', $hero_ground[1] );
	}
}

ck( 'the hero sits on the page\'s white, no panel: its columns draw no background and no corners, 40px apart, nothing above them and 52px below and none at the sides; one rule, on the two classes the columns print and on no one-class rule, the padding with no !important because the pattern stores the same on the element itself, which outranks it; and the only backgrounds the hero\'s rules name are the eyebrow\'s none, where the pill was, and the brush\'s stroke, dropped in forced colors',
	array(
		array_slice( $cols_rules, 0, 1 ),
		rules_for( '.wpc-hub-hero__cols' ),
		$has_classes( 'wp-block-columns', 'wpc-hub-hero__cols' ),
		$cols_style,
		$hero_grounds,
	),
	array(
		array( array( '', 'gap: 40px; padding-bottom: 52px; padding-left: 0; padding-right: 0; padding-top: 0;' ) ),
		array(),
		1,
		array( 'padding-top:0;padding-right:0;padding-bottom:52px;padding-left:0' ),
		array( '.wpc-hub-hero .wpc-pill: none', '.wpc-hub-hero .wpc-hub-brush: the brush', '.wpc-hub-hero .wpc-hub-brush: none' ),
	) );

ck( 'the hero\'s padding does not narrow with the screen: no rule for its columns at a narrower width and none with !important, since with no panel there are no sides to narrow, and the page\'s own gutter keeps the copy off the edge',
	array( array_slice( $cols_rules, 1 ), count( preg_grep( '/!important/', array_column( $cols_rules, 1 ) ) ) ),
	array( array(), 0 ) );

// The cascade for the one element, evaluated: the sheet's rules that apply at a window width, in order,
// and the style attribute the pattern prints, which beats a normal declaration of any rule and loses to
// an !important one. Only the properties the columns' rules and their attribute name.
$declared = static function ( $text ) {
	$found = array();

	foreach ( explode( ';', $text ) as $declaration ) {
		if ( false === strpos( $declaration, ':' ) ) {
			continue;
		}

		list( $property, $value ) = array_map( 'trim', explode( ':', $declaration, 2 ) );

		$found[ $property ] = array( trim( preg_replace( '/\s*!important$/', '', $value ) ), (bool) preg_match( '/!important$/', $value ) );
	}

	return $found;
};

$applies_at = static function ( $at, $width ) {
	if ( '' === $at ) {
		return true;
	}

	preg_match_all( '/\( (min|max)-width: (\d+)px \)/', $at, $conditions, PREG_SET_ORDER );

	if ( 0 !== strpos( $at, '@media ' ) || ! $conditions ) {
		return false;
	}

	foreach ( $conditions as $condition ) {
		if ( 'min' === $condition[1] ? $width < (int) $condition[2] : $width > (int) $condition[2] ) {
			return false;
		}
	}

	return true;
};

$cols_padding_at = static function ( $width ) use ( $cols_rules, $cols_style, $declared, $applies_at ) {
	$drawn = array();

	foreach ( $cols_rules as $rule ) {
		if ( ! $applies_at( $rule[0], $width ) ) {
			continue;
		}

		foreach ( $declared( $rule[1] ) as $property => $held ) {
			$rank = $held[1] ? 2 : 0;

			if ( ! isset( $drawn[ $property ] ) || $rank >= $drawn[ $property ][1] ) {
				$drawn[ $property ] = array( $held[0], $rank );
			}
		}
	}

	foreach ( $declared( $cols_style[0] ?? '' ) as $property => $held ) {
		if ( ! isset( $drawn[ $property ] ) || $drawn[ $property ][1] < 1 ) {
			$drawn[ $property ] = array( $held[0], 1 );
		}
	}

	return implode( ' ', array_map( static function ( $side ) use ( $drawn ) { return isset( $drawn[ 'padding-' . $side ] ) ? $drawn[ 'padding-' . $side ][0] : 'none'; }, array( 'top', 'right', 'bottom', 'left' ) ) );
};

$hero_widths = array( 1440, 1100, 1099, 900, 899, 641, 640, 375, 320 );

ck( 'the hero\'s columns are drawn with the padding the design gives them at every window width, the pattern\'s style attribute and the sheet\'s rules together (above, right, below, left): none above, 52px below and none at the sides, from 1440px to 320px',
	array_map( $cols_padding_at, array_combine( $hero_widths, $hero_widths ) ),
	array_fill_keys( $hero_widths, '0 0 52px 0' ) );

ck( 'the hero\'s eyebrow is a block of its own, not the pill\'s inline box riding a line box below the column\'s top, so it sits 44px under the header: the main\'s 20px block gap and the hero group\'s 24px (the owner, 17 October 2026: "remove this gap, its too big")',
	array( rules_for( '.wpc-hub-hero .wpc-pill' ), rules_for( '.wp-block-group.wpc-hub-hero' ) ),
	array(
		array( 'background: none; border-radius: 0; color: var( --wpc-ink-60 ); display: block; letter-spacing: .12em; padding: 0;' ),
		array( 'padding-bottom: 48px; padding-top: 24px;' ),
	) );

// The Columns block prints, for the padding it stores, the same four declarations in core's order (the
// editor's save writes top, right, bottom, left), so the block validates and the padding control of the
// Site Editor holds what the pattern draws.
preg_match( '/<!-- wp:columns (\{.*?\}) -->\s*<div class="([^"]*)" style="([^"]*)">/s', $hero, $columns_block );

$stored_padding = isset( $columns_block[1] ) ? ( json_decode( $columns_block[1], true )['style']['spacing']['padding'] ?? array() ) : array();

ck( 'the Site Editor\'s padding control shows what is drawn: the columns block stores the hero\'s four paddings, none above, 52px below and none at the sides, and prints the same four in its style attribute in core\'s order, top, right, bottom, left, so the block comment and the markup agree',
	array(
		$stored_padding,
		implode( ';', array_map( static function ( $side ) use ( $stored_padding ) { return 'padding-' . $side . ':' . $stored_padding[ $side ]; }, array_values( array_intersect( array( 'top', 'right', 'bottom', 'left' ), array_keys( $stored_padding ) ) ) ) ),
		$columns_block[3] ?? '',
		$columns_block[2] ?? '',
	),
	array(
		array( 'top' => '0', 'bottom' => '52px', 'left' => '0', 'right' => '0' ),
		'padding-top:0;padding-right:0;padding-bottom:52px;padding-left:0',
		'padding-top:0;padding-right:0;padding-bottom:52px;padding-left:0',
		'wp-block-columns wpc-hub-hero__cols wpc-hub-stack',
	) );

ck( 'the figures sit in a row of their own under the hero\'s columns, inside the hero and as wide as its content: between two card hairlines, a wrapping flex row so two figures fill it as three do, no margin of its own, and nothing of the white card left, no background, corners or shadow; on the two classes the row prints, and no rule names the figures column any more',
	array(
		array_slice( $row_rules, 0, 1 ),
		found( $page, '/html/body/div[' . $class_of( 'wpc-hub-hero' ) . ']/*', 'class' ),
		substr_count( $hero_sheet, 'wpc-hub-hero__figures' ),
		$has_classes( 'wp-block-group', 'wpc-hub-figures' ),
	),
	array(
		array( array( '', 'border-bottom: 1px solid var( --wpc-line-card ); border-top: 1px solid var( --wpc-line-card ); display: flex; flex-wrap: wrap; margin: 0;' ) ),
		array( 'wp-block-columns wpc-hub-hero__cols wpc-hub-stack', 'wp-block-group wpc-hub-figures' ),
		0,
		1,
	) );

// Every line the hero and its figures draw, each with the at-rule it sits in: a border of any side or
// its color, the corners left out.
$figure_lines = array();

preg_match_all( '/(@[^{}]+)\{|([^{}]+)\{([^{}]*)\}|\}/', $hero_sheet, $figure_tokens, PREG_SET_ORDER );

$figure_at = '';

foreach ( $figure_tokens as $figure_token ) {
	if ( '}' === $figure_token[0] ) {
		$figure_at = '';
	} elseif ( '' !== $figure_token[1] ) {
		$figure_at = trim( preg_replace( '/\s+/', ' ', $figure_token[1] ) ) . ' ';
	} elseif ( preg_match( '/wpc-hub-hero|wpc-hub-figures|wpc-figure/', $figure_token[2] ) && preg_match_all( '/(?<![\w-])(border(?!-radius)[\w-]*: [^;]+;)/', $figure_token[3], $figure_borders ) ) {
		foreach ( $figure_borders[1] as $figure_border ) {
			$figure_lines[] = $figure_at . preg_replace( '/\s+/', ' ', trim( $figure_token[2] ) ) . ' { ' . $figure_border . ' }';
		}
	}
}

$editor_line = '.wpc-hub-figures > .block-editor-block-list__block.wp-block-wpcredits-program-stat ~ .block-editor-block-list__block.wp-block-wpcredits-program-stat .wpc-figure';

ck( 'no 1px ink rule is drawn in the hero or its figures, and every line the figures draw is the card hairline: the row\'s above and below, the one beside each figure after the first (above it, below 900px), the editor\'s between figures and the source line\'s; in forced colors the row\'s two in the text color',
	array( count( preg_grep( '/var\( --wpc-ink \)/', $figure_lines ) ), $figure_lines ),
	array(
		0,
		array(
			'.wp-block-group.wpc-hub-figures { border-bottom: 1px solid var( --wpc-line-card ); }',
			'.wp-block-group.wpc-hub-figures { border-top: 1px solid var( --wpc-line-card ); }',
			'.wpc-figure + .wpc-figure { border-left: 1px solid var( --wpc-line-card ); }',
			$editor_line . ' { border-left: 1px solid var( --wpc-line-card ); }',
			'.wp-block-wpcredits-program-stat.wpc-figure-source { border-top: 1px solid var( --wpc-line-card ); }',
			'@media ( max-width: 899px ) .wpc-figure + .wpc-figure { border-left: 0; }',
			'@media ( max-width: 899px ) .wpc-figure + .wpc-figure { border-top: 1px solid var( --wpc-line-card ); }',
			'@media ( forced-colors: active ) .wp-block-group.wpc-hub-figures { border-color: CanvasText; }',
		),
	) );

// The figures row's bottom hairline is 96px above the sign-in section's content, as every object edge on
// the page is 96px from the next section's content: the hero group's 48px under the row (which has no
// margin of its own), the sign-in section's 48px above its eyebrow, and the main's block gap taken away
// between the two. The group carries no padding of its own in the pattern, which would outrank these.
// Above the columns it has 24px, so the hero does not sit against the header.
$hero_group = rules_placed( '.wp-block-group.wpc-hub-hero' );
$sign_group = rules_placed( '.wp-block-wpcredits-sign-in.wpc-hub-sign-in' );

ck( 'the hero group is padded 24px above, so the hero does not sit against the header, and 48px below, so the figures row\'s bottom hairline is 96px above the sign-in section\'s eyebrow with the sign-in section\'s 48px and no block gap between; on the two classes the group prints, and no padding of its own in the pattern',
	array(
		$hero_group,
		$hero_px( $hero_group, 'padding-bottom' ) + $hero_px( $sign_group, 'padding-top' ),
		rules_for( '.wpc-main.wpc-hub > *' ),
		found( $page, '/html/body/div[contains(concat(" ", @class, " "), " wpc-hub-hero ")][contains(concat(" ", @class, " "), " wp-block-group ")]', 'style' ),
	),
	array( array( array( '', 'padding-bottom: 48px; padding-top: 24px;' ) ), 96, array( 'margin-block-start: 0;' ), array( '' ) ) );

// With no figure, the row is not displayed, and what was under the columns for it must go with it: a
// rule for a hero with no figure (its condition `:not( :has( .wpc-figure ) )`), drawn on the columns, and
// !important to beat the padding the pattern stores on them, or drawn on the group. The element a rule
// draws is its selector's last part, the condition taken out.
$unfigured_px = static function ( $name, $property, $important, $otherwise ) use ( $hero_sheet ) {
	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $hero_sheet, $found, PREG_SET_ORDER );

	foreach ( $found as $rule ) {
		foreach ( explode( ',', $rule[1] ) as $selector ) {
			if ( false === strpos( $selector, ':not( :has( .wpc-figure ) )' ) ) {
				continue;
			}

			$parts   = preg_split( '/\s*[\s>+~]\s*/', trim( str_replace( ':not( :has( .wpc-figure ) )', '', $selector ) ) );
			$subject = end( $parts );

			if ( preg_match( '/\.' . preg_quote( $name, '/' ) . '(?![\w-])/', $subject ) && preg_match( '/(?:^|;)\s*' . $property . ': (\d+)(?:px)?( !important)?;/', $rule[2], $got ) && ( ! $important || '' !== ( $got[2] ?? '' ) ) ) {
				$otherwise = (int) $got[1];
			}
		}
	}

	return $otherwise;
};
$stored_below = preg_match( '/padding-bottom:(\d+)px/', $cols_style[0] ?? '', $stored_px ) ? (int) $stored_px[1] : 0;

ck( 'with no figure to show, no gap is left behind: the hero\'s last content sits 96px above the sign-in section\'s eyebrow, as the figures row\'s bottom hairline does when it shows, so the 52px the columns keep below them for the row go with the row',
	array(
		'with the row'    => $hero_px( $hero_group, 'padding-bottom' ) + $hero_px( $sign_group, 'padding-top' ),
		'without the row' => $unfigured_px( 'wpc-hub-hero__cols', 'padding-bottom', true, $stored_below ) + $unfigured_px( 'wpc-hub-hero', 'padding-bottom', false, $hero_px( $hero_group, 'padding-bottom' ) ) + $hero_px( $sign_group, 'padding-top' ),
	),
	array( 'with the row' => 96, 'without the row' => 96 ) );

ck( 'in forced colors, which keep a border and drop a background, the figures row\'s two hairlines take the text color; with no panel and no card in the hero there is no other edge to keep',
	array( array_slice( $row_rules, 1 ), rules_placed( '.wp-block-columns.wpc-hub-hero__cols, .wp-block-column.wpc-hub-hero__figures' ) ),
	array( array( array( '@media ( forced-colors: active )', 'border-color: CanvasText;' ) ), array() ) );

// A selector of classes joined (`.a.b`) read as XPath: the elements that print every class.
$hero_match = static function ( DOMXPath $xpath, $selector ) use ( $class_of ) {
	return $xpath->query( '//*[' . implode( '][', array_map( $class_of, explode( '.', ltrim( $selector, '.' ) ) ) ) . ']' );
};

ck( 'the row and the columns are drawn where the markup puts them: the row\'s two classes match the figures group once on the front end, holding the three figures and their source, and once in the Site Editor, where the four figure wrappers sit directly in it, so the editor\'s figure rules above work inside the row; the columns\' two match the columns once',
	array(
		(bool) $row_rules,
		$hero_match( $page, $hero_row )->length,
		count( $page->query( '//*[' . $class_of( 'wp-block-group' ) . '][' . $class_of( 'wpc-hub-figures' ) . ']/*[' . $class_of( 'wp-block-wpcredits-program-stat' ) . ']' ) ),
		$hero_match( $in_the_editor, $hero_row )->length,
		count( $in_the_editor->query( '//*[' . $class_of( 'wp-block-group' ) . '][' . $class_of( 'wpc-hub-figures' ) . ']/' . $editor_wrapper ) ),
		$hero_match( $page, $hero_cols )->length,
	),
	array( true, 1, 4, 1, 4, 1 ) );

// A theme token followed to its hex: its line in style.css's :root, and the palette or the custom colors
// of theme.json that line names.
$token_color = static function ( $token ) {
	if ( preg_match( '/^\s*--wpc-' . preg_quote( $token, '/' ) . ': var\( --wp--preset--color--([a-z0-9-]+) \);/m', theme_file( 'style.css' ), $named ) ) {
		return color_of( $named[1] );
	}

	return preg_match( '/^\s*--wpc-' . preg_quote( $token, '/' ) . ': var\( --wp--custom--([a-z0-9-]+(?:--[a-z0-9-]+)*) \);/m', theme_file( 'style.css' ), $named ) ? color_of( str_replace( '--', '.', $named[1] ) ) : '';
};

// A color of a stylesheet's rule, followed to its hex: the rule's own `color` (or the property given), a
// `var( --wpc-token )`, and that token's hex. The last rule of the selector wins, as the sheet's order
// has it.
$rule_color = static function ( $selector, $sheet, $property = 'color' ) use ( $token_color ) {
	$rules = rules_for( $selector, $sheet );

	if ( ! $rules || ! preg_match( '/(?:^|; )' . $property . ': var\( --wpc-([a-z0-9-]+) \);/', end( $rules ), $token ) ) {
		return '';
	}

	return $token_color( $token[1] );
};

$legible = static function ( $text, $ground ) {
	return '' !== $text && '' !== $ground && (float) contrast( $text, $ground ) >= 4.5;
};

// One color laid over another at an opacity, as the brush's streak is laid over its stroke.
$laid_over = static function ( $over, $under, $opacity ) {
	return '#' . implode( '', array_map( static function ( $top, $bottom ) use ( $opacity ) { return sprintf( '%02x', (int) round( $opacity * hexdec( $top ) + ( 1 - $opacity ) * hexdec( $bottom ) ) ); }, str_split( ltrim( $over, '#' ), 2 ), str_split( ltrim( $under, '#' ), 2 ) ) );
};

$page_white   = color_of( str_replace( 'var:preset|color|', '', $theme_json['styles']['color']['background'] ) );
$outline_link = '.wp-block-button.is-style-outline .wp-block-button__link';
$brush_words  = isset( $brush[0] ) && preg_match( '/(?:^|; )color: var\( --wpc-([a-z0-9-]+) \);/', $brush[0][1], $brush_token ) ? $token_color( $brush_token[1] ) : '';
$brush_blue   = isset( $brush_fill[1] ) ? '#' . $brush_fill[1] : '';
$brush_streak = isset( $brush[0] ) && '' !== $brush_blue && preg_match( "/stroke='%23([0-9a-f]{6})' stroke-width='[\d.]+' fill='none' opacity='([\d.]+)'/", $brush[0][1], $streak ) ? $laid_over( '#' . $streak[1], $brush_blue, (float) $streak[2] ) : '';

ck( 'the hero\'s text is legible on the page\'s white, where it now sits, each color read from the rule that gives it: the title\'s from the theme\'s text color, its second line\'s from the hub\'s rule, the lede\'s and the outline button\'s from theirs, and the eyebrow\'s, its pill\'s background taken off, each at 4.5:1 or more on the page\'s background; and "Keep climbing." in white on the brush, 5.61:1 on its brand blue and 4.62:1 where the lighter streak crosses it, large text either way',
	array(
		'the title'          => $legible( color_of( str_replace( 'var:preset|color|', '', $theme_json['styles']['color']['text'] ) ), $page_white ),
		'its second line'    => $legible( $rule_color( '.wpc-hub-hero .wpc-hero__title em', 'assets/css/hub.css' ), $page_white ),
		'the lede'           => $legible( $rule_color( '.wpc-hub-lede', 'assets/css/hub.css' ), $page_white ),
		'the button'         => $legible( $rule_color( $outline_link, 'style.css' ), $page_white ),
		'the eyebrow'        => false !== strpos( implode( ' ', rules_for( '.wpc-hub-hero .wpc-pill' ) ), 'background: none;' ) && $legible( $rule_color( '.wpc-hub-hero .wpc-pill', 'assets/css/hub.css' ), $page_white ),
		'the brush\'s blue'  => '' !== $brush_words && '' !== $brush_blue ? contrast( $brush_words, $brush_blue ) : '',
		'the brush\'s streak' => '' !== $brush_words && '' !== $brush_streak ? contrast( $brush_words, $brush_streak ) : '',
	),
	array( 'the title' => true, 'its second line' => true, 'the lede' => true, 'the button' => true, 'the eyebrow' => true, 'the brush\'s blue' => '5.61', 'the brush\'s streak' => '4.62' ) );

// The heading before the grid is screen-reader only and takes no room, so the grid is the first
// thing in the section; the flow layout's block gap would put it 20px below the top padding.
ck( 'the doors\' grid sits at the section\'s top padding, where the other sections\' content does: the block gap above it is removed, two classes deep, and it follows the screen-reader heading directly',
	array(
		rules_for( '.wp-block-group.wpc-hub-doors > .wpc-hub-doors__grid' ),
		found( $page, '//div[contains(concat(" ", @class, " "), " wpc-hub-doors ")]/*[1]', 'class' ),
		found( $page, '//div[contains(concat(" ", @class, " "), " wpc-hub-doors ")]/*[2]', 'class' ),
	),
	array( array( 'margin-block-start: 0;' ), array( 'wp-block-heading screen-reader-text' ), array( 'wp-block-columns wpc-hub-doors__grid wpc-hub-stack' ) ) );

$template_file = theme_file( 'templates/page-education-hub.html' );
$main_holds    = preg_match( '#<main[^>]*>(.*?)</main>#s', $template_file, $held ) ? $held[1] : '';

// The hub's main is a constrained layout: its block gap, 20px, sits above every block after the
// first, which put 116px between two sections. One rule takes it away between the sections.
ck( 'the sections are 96px apart, their own paddings: one rule, two classes deep, removes the block gap above the hub\'s sections, on a main that holds the six sections, the five patterns and the sign-in block, one element each, and nothing else',
	array(
		rules_for( '.wpc-main.wpc-hub > *' ),
		found( dom_of( $template_file ), '//main', 'class' ),
		preg_match_all( '#<!-- wp:pattern \{"slug":"wpcredits/hub-[a-z]+"\} /-->#', $main_holds ),
		preg_match_all( '#<!-- wp:wpcredits/sign-in /-->#', $main_holds ),
		trim( preg_replace( '#<!-- wp:(?:pattern [^>]*|wpcredits/sign-in) /-->#', '', $main_holds ) ),
		array_map( static function ( $file ) { return count( dom_of( wpcredits_test_pattern( $file ) )->query( '/html/body/div' ) ); }, $pattern_files ),
		count( dom_of( sign_in_block() )->query( '/html/body/*' ) ),
	),
	array( array( 'margin-block-start: 0;' ), array( 'wp-block-group wpc-main wpc-hub' ), 5, 1, '', array_fill( 0, 5, 1 ), 1 ) );

// The pathway's eyebrow is a paragraph with no margin below it, so the flow layout's 20px block gap
// was all that sat between it and the block; a section's head sits 22px above its content.
ck( 'the pathway block sits 22px under the section\'s eyebrow, as a head sits 22px above its content: two classes and a child combinator over the flow layout\'s block gap, on the block that follows the eyebrow directly',
	array( rules_for( '.wp-block-group.wpc-hub-anchored > .wpc-hub-pathway' ), found( $page, '//div[@id="pathway"]/*', 'class' ) ),
	array( array( 'margin-block-start: 22px;' ), array( 'is-style-eyebrow', 'wpc-hub-pathway wp-block-wpcredits-pathway' ) ) );

// The Buttons block prints no gap class of its own here (the hero's pair gets 12px from its own
// block gap), so it falls to the editor's flex gap, 20px, side by side and stacked.
$band_buttons = '//div[contains(concat(" ", @class, " "), " wpc-hub-cta__card ")]/div[contains(concat(" ", @class, " "), " wp-block-buttons ")]';

ck( 'the band\'s buttons are 12px apart, as the hero\'s pair is, side by side and stacked: two classes over the editor\'s flex gap, on the two Buttons blocks the band\'s cards print, and on no other',
	array( rules_for( '.wpc-hub-cta__card .wp-block-buttons' ), count( $page->query( $band_buttons ) ), found( $page, $band_buttons . '//a' ), count( $page->query( '//div[contains(concat(" ", @class, " "), " wp-block-buttons ")]' ) ) ),
	array( array( 'gap: 12px;' ), 2, array( 'Hire from WordPress', 'Sponsor', 'Partner as an institution' ), 3 ) );

$homepage_files = array(
	'templates/front-page.html' => 'c172f139736a8098a48a68684ff546344856d9a2',
	'patterns/hero.php'         => 'dfbfa1052b95995dcfe4be022f56ea4298aab534',
	'patterns/audiences.php'    => '831a99841c03e31578a3f67a43e3fa05e5c6911b',
	'patterns/steps.php'        => '44559443966fc01246f6f96941cfac6f6001458d',
	'patterns/cta.php'          => '897bc5a00b08bbabcffec41935af3889c44dd539',
);

ck( 'the live homepage\'s template and patterns are as 1.24.14 shipped them, byte for byte',
	array_map( static function ( $file ) { return sha1( theme_file( $file ) ); }, array_combine( array_keys( $homepage_files ), array_keys( $homepage_files ) ) ),
	$homepage_files );

echo "\n=== The translation template ===\n";

$make_pot = theme_file( 'bin/make-pot.sh' );

ck( 'the translation template takes nothing from bin/: the WP-CLI run leaves it out, as the gettext run does',
	array( (bool) preg_match( '#^\s*wp i18n make-pot \. "\$OUT" .*--exclude=bin$#m', $make_pot ), (bool) preg_match( "#^find \. -name '\*\.php' .*-not -path '\./bin/\*'#m", $make_pot ) ),
	array( true, true ) );

$pot = theme_file( 'languages/wpcredits-theme.pot' );

/**
 * Whether the translation template holds a string, with its context when it has one.
 *
 * @param string $pot     The template.
 * @param string $msgid   The string.
 * @param string $context Its context, or '' for none.
 * @return bool
 */
function pot_has( $pot, $msgid, $context = '' ) {
	foreach ( preg_split( '/\n\s*\n/', $pot ) as $entry ) {
		$has_context = preg_match( '/^msgctxt "(.*)"$/m', $entry, $said_context ) ? stripcslashes( $said_context[1] ) : '';

		if ( preg_match( '/^msgid "(.*)"$/m', $entry, $said ) && stripcslashes( $said[1] ) === $msgid && $has_context === $context ) {
			return true;
		}
	}

	return false;
}

ck( 'the translation template holds the flow\'s strings: the three steps\' names and the two labels a screen reader hears, the latter with their notes for translators, and the fifteen team names in their context; the table\'s two other headers and its comma-separated teams are gone',
	array(
		array_map( static function ( $msgid ) use ( $pot ) { return pot_has( $pot, $msgid ); }, array( 'Your field of study', 'The team you join', 'What you\'ll have to show', 'The team you join:', 'What you\'ll have to show:' ) ),
		substr_count( $pot, '#. translators: Read by screen readers before' ),
		count( array_filter( array( 'Core', 'Plugins', 'Testing', 'Accessibility', 'Design', 'Block patterns', 'Themes', 'Documentation', 'Learn WordPress', 'Marketing', 'Polyglots', 'Community', 'WordCamps', 'Learn WordPress lesson plans', 'Training' ), static function ( $msgid ) use ( $pot ) { return pot_has( $pot, $msgid, 'a WordPress team a student joins' ); } ) ),
		array_map( static function ( $msgid ) use ( $pot ) { return pot_has( $pot, $msgid ); }, array( 'Field of study', 'Where you\'ll work', 'Core, plugins, testing, accessibility', 'Design, block patterns, themes', 'Documentation, Learn WordPress, Marketing', 'Community, WordCamps', 'Learn WordPress lesson plans, Training', 'Accessibility, testing' ) ),
		pot_has( $pot, 'Education', 'a field of study' ),
	),
	array( array( true, true, true, true, true ), 2, 15, array_fill( 0, 8, false ), true ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
