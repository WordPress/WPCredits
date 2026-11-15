<?php
/**
 * Checks for the FAQ page (inc/faq.php, assets/css/faq.css, assets/js/faq.js), run with
 * `php bin/test-faq.php` on the WordPress of bin/stubs/hub-wp.php: what loads where, and the rules the
 * sheet must keep (scoped to the page's own classes, a 48px question row with a turning chevron, a
 * focus ring, motion only for a reader who has not asked for less, logical properties).
 *
 * Given the page's saved content as an argument (`php bin/test-faq.php <content.html>`), it also reads
 * that: the block grammar closed, every question a Details block with an anchor of its own, every link
 * to `#...` leading to an element on the page, and the writing rules (no em or en dash, no exclamation
 * mark, the word WordPress never in an eyebrow).
 *
 * @package WPCredits_Theme
 */

// phpcs:ignoreFile -- a command-line check, not theme code.

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post = 0 ) { return isset( $GLOBALS['slugs'][ (int) $post ] ) ? $GLOBALS['slugs'][ (int) $post ] : ''; }
}

// Core's get_page_uri(): a page's path, its parents' slugs before its own; `$GLOBALS['uris']`, page ID => path.
if ( ! function_exists( 'get_page_uri' ) ) {
	function get_page_uri( $page = 0 ) { return isset( $GLOBALS['uris'][ (int) $page ] ) ? $GLOBALS['uris'][ (int) $page ] : false; }
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['registered_scripts'][ $handle ] = array( $src, $deps, $ver, $args ); return true; }
}

$GLOBALS['registered_scripts'] = array();

require_once WPCREDITS_THEME_DIR . 'inc/faq.php';

$fail  = 0;
$total = 0;

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

echo "=== What loads where ===\n";

$GLOBALS['styles'] = array();
wpcredits_faq_register_assets();

ck( 'the sheet is registered after the hub\'s, with the theme\'s version; the script deferred, with the theme\'s version and nothing it waits on',
	array( $GLOBALS['styles']['wpcredits-faq'], $GLOBALS['registered_scripts']['wpcredits-faq'] ),
	array(
		array( 'https://site.example/wp-content/themes/wpcredits-theme/assets/css/faq.css', array( 'wpcredits-hub' ), '9.9.9' ),
		array( 'https://site.example/wp-content/themes/wpcredits-theme/assets/js/faq.js', array(), '9.9.9', array( 'strategy' => 'defer' ) ),
	) );

$GLOBALS['request'] = array( 'singular' => 'page', 'id' => 51, 'templates' => array() );
$GLOBALS['slugs']   = array( 51 => 'faq', 52 => 'students', 53 => 'faq-old', 54 => 'faq' );
$GLOBALS['uris']    = array( 51 => 'faq', 52 => 'students', 53 => 'faq-old', 54 => 'students/faq' );
$GLOBALS['enqueued'] = array();
$GLOBALS['scripts']  = array();
wpcredits_faq_assets();
$on_faq = array( $GLOBALS['enqueued'], array_keys( $GLOBALS['scripts'] ) );

$elsewhere = array();

foreach ( array( 52, 53, 54 ) as $id ) {
	$GLOBALS['request']['id'] = $id;
	$GLOBALS['enqueued']      = array();
	$GLOBALS['scripts']       = array();
	wpcredits_faq_assets();
	$elsewhere[] = array( $GLOBALS['enqueued'], array_keys( $GLOBALS['scripts'] ) );
}

$GLOBALS['request'] = array( 'singular' => '', 'id' => 51, 'templates' => array() );
$GLOBALS['enqueued'] = array();
wpcredits_faq_assets();
$elsewhere[] = array( $GLOBALS['enqueued'], array() );

ck( 'the top-level page at the path faq loads the hub\'s sheet, its own after it and its script; the Students page, a page whose path only starts with faq, a child page with the slug faq (students/faq), and a request that is no page load none of them',
	array( $on_faq, $elsewhere ),
	array( array( array( 'wpcredits-hub', 'wpcredits-faq' ), array( 'wpcredits-faq' ) ), array( array( array(), array() ), array( array(), array() ), array( array(), array() ), array( array(), array() ) ) ) );

$GLOBALS['editor_styles'] = array();
wpcredits_faq_editor_style();
ck( 'the sheet is an editor style too, so the editor draws the questions as the page does', $GLOBALS['editor_styles'], array( 'assets/css/faq.css' ) );

$functions = (string) file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' );
ck( 'functions.php loads the file once', substr_count( $functions, "inc/faq.php" ), 1 );

echo "\n=== The sheet ===\n";

$css   = (string) file_get_contents( WPCREDITS_THEME_DIR . 'assets/css/faq.css' );
$plain = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

preg_match_all( '/([^{}]+)\{/', $plain, $found );

$unscoped = array();

foreach ( $found[1] as $selectors ) {
	$selectors = trim( $selectors );

	if ( '' === $selectors || 0 === strpos( $selectors, '@' ) ) {
		continue;
	}

	foreach ( explode( ',', $selectors ) as $selector ) {
		if ( false === strpos( $selector, '.wpc-faq' ) ) {
			$unscoped[] = trim( $selector );
		}
	}
}

ck( 'every rule names one of the page\'s own wpc-faq classes, so no other page changes, in the editor either', $unscoped, array() );

/**
 * The declarations of the first rule whose selector list is exactly the one given, as name => value.
 *
 * @param string $css      The sheet without comments.
 * @param string $selector The selector list.
 * @return array<string, string>
 */
function wpcredits_test_rule( $css, $selector ) {
	if ( ! preg_match( '/(?:^|\})\s*' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/', $css, $found ) ) {
		return array();
	}

	$out = array();

	foreach ( array_filter( array_map( 'trim', explode( ';', $found[1] ) ) ) as $declaration ) {
		list( $name, $value ) = array_map( 'trim', explode( ':', $declaration, 2 ) );
		$out[ $name ]         = $value;
	}

	return $out;
}

$summary = wpcredits_test_rule( $plain, '.wpc-faq__list > .wp-block-details > summary' );

ck( 'the question is a full-width row at least 48px tall, its words at 17px weight 600, the browser\'s marker hidden',
	array( $summary['display'], $summary['justify-content'], $summary['min-block-size'], $summary['font-size'], $summary['font-weight'], $summary['list-style'], (bool) wpcredits_test_rule( $plain, '.wpc-faq__list > .wp-block-details > summary::-webkit-details-marker' ) ),
	array( 'flex', 'space-between', '48px', '17px', '600', 'none', true ) );

ck( 'a chevron at the row\'s end, the theme\'s icon in the text\'s color, that turns when the question is open',
	array(
		wpcredits_test_rule( $plain, '.wpc-faq__list > .wp-block-details > summary::after' )['mask'],
		wpcredits_test_rule( $plain, '.wpc-faq__list > .wp-block-details > summary::after' )['background-color'],
		wpcredits_test_rule( $plain, '.wpc-faq__list > .wp-block-details[open] > summary::after' ),
	),
	array( 'var( --wpc-icon-chevron-down ) center / 24px 24px no-repeat', 'currentcolor', array( 'transform' => 'rotate( 180deg )' ) ) );

ck( 'hover in the brand blue, and the brand focus ring DESIGN.md names: 2px solid with a 2px offset',
	array( wpcredits_test_rule( $plain, '.wpc-faq__list > .wp-block-details > summary:hover' ), wpcredits_test_rule( $plain, '.wpc-faq__list > .wp-block-details > summary:focus-visible' ) ),
	array( array( 'color' => 'var( --wpc-brand )' ), array( 'outline' => '2px solid var( --wpc-brand )', 'outline-offset' => '2px' ) ) );

ck( 'in forced-colors mode the chevron is drawn in CanvasText, not left as Canvas on Canvas, so open and closed still show',
	(bool) preg_match( '/@media \( forced-colors: active \) \{\s*\.wpc-faq__list > \.wp-block-details > summary::after \{\s*background-color: CanvasText;\s*forced-color-adjust: none;\s*\}/', $plain ),
	true );

ck( 'the questions sit between hairlines, flush, and one an address names lands below the sticky header',
	array(
		wpcredits_test_rule( $plain, '.wp-block-group.wpc-faq__list' )['border-block-start'],
		wpcredits_test_rule( $plain, '.wp-block-group.wpc-faq__list > *' ),
		wpcredits_test_rule( $plain, '.wpc-faq__list > .wp-block-details' )['border-block-end'],
		wpcredits_test_rule( $plain, '.wpc-faq__list > .wp-block-details' )['scroll-margin-block-start'],
	),
	array( '1px solid var( --wpc-line )', array( 'margin-block' => '0' ), '1px solid var( --wpc-line )', 'var( --wpc-landing-offset )' ) );

ck( 'the chevron\'s turn is the sheet\'s only motion, and it is there only for a reader who has not asked for less',
	array( preg_match_all( '/\b(transition|animation)\s*:/', $plain ), (bool) preg_match( '/@media \( prefers-reduced-motion: no-preference \) \{\s*\.wpc-faq__list > \.wp-block-details > summary::after \{\s*transition: transform \.2s ease-out;\s*\}/', $plain ) ),
	array( 1, true ) );

ck( 'logical properties only, so a right-to-left page mirrors: no left, right, top or bottom side named, and no physical text alignment',
	preg_match( '/(margin|padding|border|scroll-margin)-(left|right|top|bottom)|\b(left|right|top|bottom)\s*:|text-align:\s*(left|right)/', $plain ),
	0 );

ck( 'a long word breaks only as a last resort (break-word, never anywhere): the content gives addresses their break points',
	array( wpcredits_test_rule( $plain, '.wp-block-group.wpc-faq-step' )['overflow-wrap'], wpcredits_test_rule( $plain, '.wp-block-group.wpc-faq__list' )['overflow-wrap'], wpcredits_test_rule( $plain, ".wpc-faq-group,\n.wpc-faq-hello" ), substr_count( $plain, 'anywhere' ) ),
	array( 'break-word', 'break-word', array( 'overflow-wrap' => 'break-word' ), 0 ) );

/**
 * The body of a media block, from its opening brace to its matching close.
 *
 * @param string $css   The sheet without comments.
 * @param string $query The query as written, such as `( min-width: 900px )`.
 * @return string
 */
function wpcredits_test_media( $css, $query ) {
	$at = strpos( $css, '@media ' . $query . ' {' );

	if ( false === $at ) {
		return '';
	}

	$depth = 0;

	for ( $i = strpos( $css, '{', $at ); $i < strlen( $css ); $i++ ) {
		$depth += '{' === $css[ $i ] ? 1 : ( '}' === $css[ $i ] ? -1 : 0 );

		if ( 0 === $depth ) {
			return '}' . substr( $css, strpos( $css, '{', $at ) + 1, $i - strpos( $css, '{', $at ) - 1 );
		}
	}

	return '';
}

$wide = wpcredits_test_media( $plain, '( min-width: 900px )' );

ck( 'from 900px a section is two columns: a rail for its heading and a right column of 720px at most, 44px apart, their first lines on one baseline; the heading in the rail spans the rows, the rest goes in the right column',
	array(
		wpcredits_test_rule( $wide, '.wp-block-group.wpc-faq-group' ),
		wpcredits_test_rule( $wide, '.wp-block-group.wpc-faq-group > .wpc-hub-head' ),
		wpcredits_test_rule( $wide, '.wp-block-group.wpc-faq-group > :not( .wpc-hub-head )' ),
	),
	array(
		array( 'align-items' => 'baseline', 'column-gap' => '44px', 'display' => 'grid', 'grid-template-columns' => 'minmax( 0, 1fr ) min( 720px, calc( ( 100% - 44px ) * 2 / 3 ) )' ),
		array( 'grid-column' => '1', 'grid-row' => '1 / span 4', 'margin-block' => '0' ),
		array( 'grid-column' => '2', 'margin-block' => '0', 'max-inline-size' => '720px' ),
	) );

$outside_wide = str_replace( substr( $wide, 1 ), '', $plain );

ck( 'the rail\'s heading is 30px, the hub\'s section heading at its smallest; the 720px cap is the two columns\' alone, so below 900px a list takes the content\'s width',
	array(
		wpcredits_test_rule( $wide, ".wpc-hub .wpc-faq-group > .wpc-hub-head h2,\n\t.wpc-hub .wpc-faq-group > .wpc-hub-head h3" ),
		substr_count( $outside_wide, '720px' ),
	),
	array( array( 'font-size' => '30px', 'line-height' => '1.15' ), 0 ) );

ck( 'a step card is a column whose button takes the room at its foot, so the two buttons sit on one line: the heading 8px under the eyebrow and 16px over its steps; the hello line 24px under the cards, "Stuck on a step?" 48px under the hello line',
	array(
		array_intersect_key( wpcredits_test_rule( $plain, '.wp-block-group.wpc-faq-step' ), array_flip( array( 'display', 'flex-direction' ) ) ),
		wpcredits_test_rule( $plain, '.wp-block-group.wpc-faq-step > *' ),
		wpcredits_test_rule( $plain, '.wp-block-group.wpc-faq-step > .wp-block-heading' ),
		wpcredits_test_rule( $plain, '.wp-block-group.wpc-faq-step > .wp-block-list.wpc-faq-step__list' )['margin-block'],
		wpcredits_test_rule( $plain, '.wp-block-group.wpc-faq-step > .wpc-faq-buttons' ),
		wpcredits_test_rule( $plain, '.wp-block-group.wpc-faq-connect > .wpc-faq-hello' ),
		wpcredits_test_rule( $plain, '.wp-block-group.wpc-faq-connect > .wpc-faq-stuck' ),
	),
	array(
		array( 'display' => 'flex', 'flex-direction' => 'column' ),
		array( 'margin-block' => '0' ),
		array( 'margin-block' => '8px 0' ),
		'16px 24px',
		array( 'margin-block' => 'auto 0' ),
		array( 'margin-block-start' => '24px' ),
		array( 'margin-block-start' => '48px' ),
	) );

ck( 'the head: the links 28px under the lede in the copy column, the head ending 48px under them, so 96px over "Get connected first"; the title 20px under the eyebrow; from 900px the picture is out of the flow in its stretched column (so the copy alone sets the row\'s height), 38px down (level with the title), its height the smaller of the room down to the links\' foot and its column\'s width at its 398 by 338 crop, its width from its proportions, against the right edge, its drawing scaled to its own width, never zoomed by hub.css\'s steps, its overflow hidden',
	array(
		wpcredits_test_rule( $plain, '.wpc-landing .wp-block-group.wpc-faq-head' ),
		wpcredits_test_rule( $plain, '.wpc-faq-head .wpc-hub-hero__copy > .wpc-faq-nav' ),
		wpcredits_test_rule( $plain, '.wpc-faq-head .wpc-hub-hero__copy > p.is-style-eyebrow' ),
		wpcredits_test_rule( $plain, '.wpc-faq-head .wpc-hub-hero__copy > .wpc-faq-title' ),
		wpcredits_test_rule( $plain, '.wpc-faq-head .wp-block-columns > .wp-block-column.wpc-hub-hero__art' ),
		wpcredits_test_rule( $plain, '.wpc-faq-head .wp-block-column.wpc-hub-hero__art > .wpc-hub-art' ),
		wpcredits_test_rule( $plain, '.wpc-faq-head .wpc-hub-art > .wpc-hub-art__faq' ),
	),
	array(
		array( 'padding-block-end' => '48px' ),
		array( 'margin-block' => '28px 0' ),
		array( 'margin-block' => '0' ),
		array( 'margin-block' => '20px 0' ),
		array( 'align-self' => 'stretch', 'flex-grow' => '1', 'position' => 'relative' ),
		array( 'aspect-ratio' => '398 / 338', 'block-size' => 'min( calc( 100% - 38px ), calc( 100cqi * 338 / 398 ) )', 'container-type' => 'inline-size', 'inline-size' => 'auto', 'inset-block-start' => '38px', 'inset-inline-end' => '0', 'margin' => '0', 'overflow' => 'hidden', 'position' => 'absolute', 'zoom' => '1' ),
		array( 'height' => 'calc( 100cqi * 494 / 398 )', 'inset-block-start' => 'calc( 100cqi * -128 / 398 )', 'translate' => 'calc( 100cqi * -74 / 398 + 10px ) 0', 'width' => 'calc( 100cqi * 540 / 398 )' ),
	) );

$tablet = wpcredits_test_media( $plain, '( min-width: 900px ) and ( max-width: 1199px )' );

ck( 'on a tablet, 900px to 1199px, the head is a grid: the copy column\'s children take its cells (one nav, in its place), the eyebrow over the title and the lede on the left and the picture beside the title and the lede, then the links across the full width 28px under them; the picture\'s top is the title\'s and its height the smaller of the title and lede together and its column\'s width at its crop',
	array(
		wpcredits_test_rule( $tablet, '.wpc-faq-head .wp-block-columns.wpc-hub-hero__cols' ),
		wpcredits_test_rule( $tablet, '.wpc-faq-head .wp-block-columns > .wp-block-column.wpc-hub-hero__copy' ),
		wpcredits_test_rule( $tablet, '.wpc-faq-head .wpc-hub-hero__copy > p.is-style-eyebrow' ),
		wpcredits_test_rule( $tablet, '.wpc-faq-head .wpc-hub-hero__copy > .wpc-faq-title' ),
		wpcredits_test_rule( $tablet, '.wpc-faq-head .wpc-hub-hero__copy > .wpc-hub-lede' ),
		wpcredits_test_rule( $tablet, '.wpc-faq-head .wpc-hub-hero__copy > .wpc-faq-nav' ),
		wpcredits_test_rule( $tablet, '.wpc-faq-head .wp-block-columns > .wp-block-column.wpc-hub-hero__art' ),
		wpcredits_test_rule( $tablet, '.wpc-faq-head .wp-block-column.wpc-hub-hero__art > .wpc-hub-art' ),
	),
	array(
		array( 'column-gap' => '40px', 'display' => 'grid', 'grid-template-areas' => "\"eyebrow .\"\n\t\t\t\"title art\"\n\t\t\t\"lede art\"\n\t\t\t\"nav nav\"", 'grid-template-columns' => '47% minmax( 0, 1fr )', 'row-gap' => '0' ),
		array( 'display' => 'contents' ),
		array( 'grid-area' => 'eyebrow', 'margin-block' => '0 20px' ),
		array( 'grid-area' => 'title', 'margin-block' => '0' ),
		array( 'grid-area' => 'lede', 'margin-block' => '20px 0' ),
		array( 'grid-area' => 'nav', 'margin-block' => '28px 0' ),
		array( 'grid-area' => 'art' ),
		array( 'block-size' => 'min( 100%, calc( 100cqi * 338 / 398 ) )', 'inset-block-start' => '0' ),
	) );

ck( 'every button is the hub\'s large one at one height: a 1px border on each (transparent on a filled one) and the same padding',
	array( wpcredits_test_rule( $plain, '.wpc-faq-buttons .wp-block-button.wpc-button--large > .wp-block-button__link' ), wpcredits_test_rule( $plain, '.wpc-faq-buttons .wp-block-button.wpc-button--large:not( .is-style-outline ) > .wp-block-button__link' ) ),
	array( array( 'border-style' => 'solid', 'border-width' => '1px', 'font-size' => '14px', 'line-height' => '1.54', 'padding' => '10px 19px' ), array( 'border-color' => 'transparent' ) ) );

ck( 'the answer is the page\'s body text: the ink color, its size and line height inherited, not a lighter gray',
	wpcredits_test_rule( $plain, '.wpc-faq__list > .wp-block-details > :not( summary )' ),
	array( 'color' => 'var( --wpc-ink )', 'margin-block' => '0 12px', 'max-inline-size' => '100%' ) );

ck( 'a question reached with Tab keeps the header\'s offset above it and 24px below',
	wpcredits_test_rule( $plain, '.wpc-faq__list > .wp-block-details > summary' )['scroll-margin-block'],
	'var( --wpc-landing-offset ) 24px' );

ck( 'whatever an address names inside the list, such as a paragraph of an answer, lands below the sticky header as well: the :target rule, scoped to the list, with the landing offset',
	wpcredits_test_rule( $plain, '.wpc-faq__list :target' ),
	array( 'scroll-margin-block-start' => 'var( --wpc-landing-offset )' ) );

$files = $css . file_get_contents( WPCREDITS_THEME_DIR . 'assets/js/faq.js' ) . file_get_contents( WPCREDITS_THEME_DIR . 'inc/faq.php' );
ck( 'no em or en dash in the page\'s files', preg_match( '/[\x{2013}\x{2014}]/u', $files ), 0 );

if ( isset( $argv[1] ) ) {
	echo "\n=== The page's content: " . basename( $argv[1] ) . " ===\n";

	$content = (string) file_get_contents( $argv[1] );

	preg_match_all( '/<!-- (\/?)wp:([a-z0-9\/-]+)(?: (\{.*?\}))? (\/?)-->/', $content, $delimiters, PREG_SET_ORDER );

	$stack = array();
	$bad   = array();

	foreach ( $delimiters as $one ) {
		$name = $one[2];

		if ( isset( $one[3] ) && '' !== $one[3] && null === json_decode( $one[3] ) ) {
			$bad[] = 'attributes of ' . $name . ' are not JSON';
		}

		if ( '/' === $one[4] ) {
			continue;
		}

		if ( '/' === $one[1] ) {
			$open = array_pop( $stack );

			if ( $open !== $name ) {
				$bad[] = 'closed ' . $name . ' over ' . (string) $open;
			}
		} else {
			$stack[] = $name;
		}
	}

	ck( 'the block grammar is whole: every block closed in order, every attribute list JSON', array( $bad, $stack ), array( array(), array() ) );

	preg_match_all( '/<!-- wp:details(?: \{[^}]*\})? -->\s*<details([^>]*)>/', $content, $details );
	$ids = array();

	foreach ( $details[1] as $attributes ) {
		$ids[] = preg_match( '/\bid="([a-z0-9-]+)"/', $attributes, $id ) ? $id[1] : '';
	}

	ck( 'every question is a Details block with an anchor of its own, short and stable (lowercase words and hyphens)',
		array( count( $details[1] ) > 0, in_array( '', $ids, true ), count( $ids ) === count( array_unique( $ids ) ), substr_count( $content, '<details' ) === count( $ids ) ),
		array( true, false, true, true ) );

	$outside = 0;

	foreach ( preg_split( '/<div class="wp-block-group wpc-faq__list[^"]*"[^>]*>/', $content ) as $at => $piece ) {
		// Before the first list, and after each list's own end, no question may stand.
		$after   = 0 === $at ? $piece : (string) substr( $piece, (int) strpos( $piece, '<!-- /wp:group -->' ) );
		$outside += substr_count( $after, '<details' );
	}

	ck( 'every question sits in a list of questions, the group the sheet dresses', $outside, 0 );

	preg_match_all( '/\bid="([^"]+)"/', $content, $all_ids );
	preg_match_all( '/href="#([^"]+)"/', $content, $jumps );
	ck( 'every link to a fragment leads to an element on the page', array_values( array_diff( array_unique( $jumps[1] ), $all_ids[1] ) ), array() );

	$text = (string) preg_replace( '/<!--.*?-->/s', '', $content );
	ck( 'no em or en dash and no exclamation mark in the words', array( preg_match( '/[\x{2013}\x{2014}]|&mdash;|&ndash;/u', $text ), preg_match( '/!/', (string) preg_replace( '/<[^>]+>/', '', $text ) ) ), array( 0, 0 ) );

	preg_match_all( '/<p class="[^"]*is-style-eyebrow[^"]*"[^>]*>(.*?)<\/p>/s', $content, $eyebrows );
	ck( 'the word WordPress is never in an eyebrow', preg_grep( '/wordpress/i', $eyebrows[1] ), array() );

	$head = (string) strstr( $content, '<!-- wp:group {"align":"wide","className":"wpc-section wpc-faq-connect"', true );

	ck( 'the head is the Students hero\'s two columns: the hero group, its stacking columns with their 52px foot, the copy column at 47% holding the eyebrow "For students", the page\'s only H1, the lede and, right under it, the "On this page" links, and the picture column at 49% (no vertical alignment of its own: it stretches to the copy) holding the speech bubble pattern alone',
		array(
			0 === strpos( $content, '<!-- wp:group {"align":"wide","className":"wpc-hub-hero wpc-faq-head","layout":{"type":"default"}} -->' ),
			substr_count( $head, '<!-- wp:columns {"className":"wpc-hub-hero__cols wpc-hub-stack","style":{"spacing":{"padding":{"top":"0","bottom":"52px","left":"0","right":"0"}}}} -->' ),
			substr_count( $head, '<!-- wp:column {"width":"47%","className":"wpc-hub-hero__copy"} -->' ),
			(bool) preg_match( '/wpc-hub-hero__copy" style="flex-basis:47%"><!-- wp:paragraph \{"className":"is-style-eyebrow"\} -->\s*<p class="is-style-eyebrow">For students<\/p>/', $head ),
			array( substr_count( $content, '<h1' ), substr_count( $head, '<h1 class="wp-block-heading wpc-faq-title">Frequently asked questions</h1>' ) ),
			substr_count( $head, '<nav class="wp-block-group wpc-faq-nav" aria-label="On this page">' ),
			(bool) preg_match( '/<!-- wp:column \{"width":"49%","className":"wpc-hub-hero__art"\} -->\s*<div class="wp-block-column wpc-hub-hero__art" style="flex-basis:49%"><!-- wp:pattern \{"slug":"wpcredits\/faq-question"\} \/--><\/div>/', $head ),
			substr_count( $content, 'wp:pattern' ),
			(bool) preg_match( '/<p class="wpc-hub-lede">[^<]*<\/p>\s*<!-- \/wp:paragraph -->\s*<!-- wp:group \{"ariaLabel":"On this page","tagName":"nav","className":"wpc-faq-nav","layout":\{"type":"default"\}\} -->.*?<\/nav>\s*<!-- \/wp:group --><\/div>\s*<!-- \/wp:column -->/s', $head ),
			(bool) preg_match( '/<!-- \/wp:columns --><\/div>\s*<!-- \/wp:group -->\s*$/', $head ),
		),
		array( true, 1, 1, true, array( 1, 1 ), 1, true, 1, true, true ) );

	ck( 'the last section ends 48px above the page\'s end, its own padding, as the Students page\'s last section does',
		(bool) preg_match( '/wpc-faq-still" style="padding-top:48px;padding-bottom:48px">/', $content ),
		true );

	ck( 'no link to an Airtable address or to the Partners page', preg_match( '/airtable|\/partners\//i', $content ), 0 );

	ck( '"Stuck on a step?" has its anchor, stuck-on-a-step, on an H3 in its own group of the two-column layout, and the step cards hold no question',
		array(
			(bool) preg_match( '/<div class="wp-block-group wpc-faq-group wpc-faq-stuck">\s*<!-- wp:group \{"className":"wpc-hub-head".*? -->\s*<div class="wp-block-group wpc-hub-head"><!-- wp:heading \{"level":3,[^}]*\} -->\s*<h3 class="wp-block-heading wpc-hub-anchored" id="stuck-on-a-step">Stuck on a step\?<\/h3>/', $content ),
			count( array_filter( explode( 'wpc-faq-step"', $content ), static function ( $piece ) { return false !== strpos( (string) strstr( $piece, '<!-- /wp:group -->', true ), '<details' ); } ) ),
		),
		array( true, 0 ) );

	ck( 'every section that holds questions, the school section and the closing one are in the two-column layout: nine groups with the wpc-faq-group class',
		preg_match_all( '/<div class="wp-block-group[^"]*\bwpc-faq-group\b/', $content ),
		9 );

	ck( 'no zero-width character (U+200B, U+200C, U+200D, U+2060, U+FEFF) anywhere but in the invented example address of the Slack step, so every real address or web address a reader copies works',
		preg_match_all( '/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', str_replace( 'example123' . "\u{200B}" . '@' . 'chat.wordpress.org', '', $content ) ),
		0 );

	ck( 'no bare <wbr> (the block editor\'s rich text drops it and calls the block invalid), and no zero-width space inside an attribute, a link\'s target among them',
		array( substr_count( $content, '<wbr' ), preg_match( '/="[^"]*\x{200B}/u', $content ) ),
		array( 0, 0 ) );
}

printf( "\n%s (%d checks)\n", $fail ? $fail . ' FAILURE(S)' : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
