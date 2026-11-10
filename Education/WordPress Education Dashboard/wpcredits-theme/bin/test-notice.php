<?php
/**
 * The two pages drawn in the homepage hero's layout (theme 1.32.12, approved by the product owner on
 * 5 November 2026): the "Notice" page template (templates/page-notice.html), which draws a page's own
 * content beside the hero's mountain and forwards to the homepage after 5 seconds unless the reader stays,
 * with its starter content (patterns/hub-back-soon.php); and the 404 template (templates/404.html,
 * patterns/hub-not-found.php), which only looks the same. Both draw the hero's mountain of role words,
 * shared with the hero (`wpcredits_hub_mountain_svg()`), in patterns/hub-mountain.php.
 *
 * Checked: the templates' and patterns' markup, the hub stylesheet loaded on both by the one rule
 * (`wpcredits_hub_assets()`), the 404 status left to WordPress, the hero's picture byte for byte as
 * 1.32.11 drew it, the pages' picture following the hero's rule on a phone (read from hub.css as the
 * theme's suites read a sheet), and the forward's markup. What the forward's
 * script does on the page is bin/test-notice-js.php's to run.
 *
 * Run from the theme root:  php bin/test-notice.php
 *
 * @package WPCredits_Theme
 */

// phpcs:ignoreFile -- a command-line check, not theme code.

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';

// Core's is_404(), answering what the request says.
if ( ! function_exists( 'is_404' ) ) {
	function is_404() { return ! empty( $GLOBALS['request']['404'] ); }
}

require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';
require_once WPCREDITS_THEME_DIR . 'inc/template-tags.php';

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

function theme_file( $path ) {
	return is_file( WPCREDITS_THEME_DIR . $path ) ? (string) file_get_contents( WPCREDITS_THEME_DIR . $path ) : '';
}

function theme_call( $name, ...$args ) {
	return function_exists( $name ) ? $name( ...$args ) : null;
}

function dom_of( $html ) {
	$document = new DOMDocument();

	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();

	return new DOMXPath( $document );
}

function found( DOMXPath $xpath, $query, $attribute = '' ) {
	$said = array();

	foreach ( $xpath->query( $query ) as $node ) {
		$said[] = '' === $attribute ? trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ) : (string) $node->getAttribute( $attribute );
	}

	return $said;
}

function has( $class ) {
	return 'contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")';
}

/** A pattern file's output, or '' for a file not there. */
function pattern( $file ) {
	if ( ! is_file( WPCREDITS_THEME_DIR . 'patterns/' . $file ) ) {
		return '';
	}

	return wpcredits_test_pattern( $file );
}

/** A pattern file's header fields. */
function header_of( $file ) {
	$header = array();

	preg_match_all( '/^ \* ([A-Z][A-Za-z ]+): (.+)$/m', theme_file( 'patterns/' . $file ), $fields, PREG_SET_ORDER );

	foreach ( $fields as $field ) {
		$header[ $field[1] ] = $field[2];
	}

	return $header;
}

/**
 * Every style rule of hub.css with the at-rules it sits in, comments left out, in order: `at`, `selector`
 * and `declarations` (one string, whitespace run together). `@keyframes` blocks are read whole as one rule
 * whose selector is the at-rule.
 *
 * @return array[]
 */
function hub_rules() {
	static $rules = null;

	if ( null !== $rules ) {
		return $rules;
	}

	$css   = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) );
	$rules = array();
	$stack = array();

	preg_match_all( '/@keyframes[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}|\{|\}|(?:(?!@keyframes)[^{}])+/s', $css, $tokens );

	$prelude = '';
	$body    = null;

	foreach ( $tokens[0] as $token ) {
		if ( 0 === strpos( ltrim( $token ), '@keyframes' ) ) {
			$rules[] = array( 'at' => implode( ' > ', $stack ), 'selector' => trim( preg_replace( '/\s+/', ' ', substr( $token, 0, strpos( $token, '{' ) ) ) ), 'declarations' => trim( preg_replace( '/\s+/', ' ', substr( $token, strpos( $token, '{' ) ) ) ) );
			continue;
		}

		if ( '{' === $token ) {
			$name = trim( preg_replace( '/\s+/', ' ', $prelude ) );

			if ( preg_match( '/^@(?:media|supports|container)\b/', $name ) ) {
				$stack[] = $name;
				$prelude = '';
			} else {
				$body = '';
			}

			continue;
		}

		if ( '}' === $token ) {
			if ( null !== $body ) {
				$rules[] = array( 'at' => implode( ' > ', $stack ), 'selector' => trim( preg_replace( '/\s+/', ' ', $prelude ) ), 'declarations' => trim( preg_replace( '/\s+/', ' ', $body ) ) );
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

function hub_rule( $selector, $at = '' ) {
	$found = array();

	foreach ( hub_rules() as $rule ) {
		if ( $rule['selector'] === $selector && $rule['at'] === $at ) {
			$found[] = $rule['declarations'];
		}
	}

	return $found;
}

echo "=== The Notice template ===\n";

$theme_json = json_decode( theme_file( 'theme.json' ), true );
$notice     = theme_file( 'templates/page-notice.html' );

ck( 'theme.json registers the template as a page template named "Notice", so a page can choose it',
	array_values( array_filter( $theme_json['customTemplates'] ?? array(), static function ( $one ) { return 'page-notice' === $one['name']; } ) ),
	array( array( 'name' => 'page-notice', 'title' => 'Notice', 'postTypes' => array( 'page' ) ) ) );

preg_match_all( '#<!-- wp:([a-z/-]+)( \{.*?\})? /?-->#', $notice, $notice_blocks, PREG_SET_ORDER );

ck( 'the template draws the hero\'s layout: the WordPress.org header and the site header, a main marked as the hub, the hero\'s group and columns, the page\'s own content in the copy column with the forward under it, the mountain in the picture\'s column, then the WordPress.org footer; no post title, which the content\'s heading is',
	array(
		array_map( static function ( $one ) { return $one[1] . ( isset( $one[2] ) && preg_match( '/"(?:slug|className)":"([^"]+)"/', $one[2], $named ) ? ' ' . $named[1] : '' ); }, $notice_blocks ),
		false !== strpos( $notice, 'post-title' ),
	),
	array(
		array(
			'template-part wporg-header',
			'template-part header',
			'group wpc-main wpc-hub',
			'group wpc-hub-hero wpc-hub-notice',
			'columns wpc-hub-hero__cols wpc-hub-stack',
			'column wpc-hub-hero__copy',
			'post-content',
			'wpcredits/notice-forward',
			'column wpc-hub-hero__art',
			'pattern wpcredits/hub-mountain',
			'template-part wporg-footer',
		),
		false,
	) );

$back_soon = pattern( 'hub-back-soon.php' );
$bx        = dom_of( $back_soon );

ck( 'its starter content is a pattern a new page is offered (the Notice page\'s own content, as a page in that template starts): in the theme\'s category, for a page\'s content, hidden from no one',
	array_intersect_key( header_of( 'hub-back-soon.php' ), array_flip( array( 'Title', 'Slug', 'Categories', 'Block Types', 'Post Types' ) ) ),
	array( 'Title' => 'Back soon notice', 'Slug' => 'wpcredits/hub-back-soon', 'Categories' => 'wpcredits', 'Block Types' => 'core/post-content', 'Post Types' => 'page' ) );

ck( 'the content: the hero\'s pill line, the title "This page is being updated. Back soon." with "Back soon." in the hero\'s brush inside an em, as the hero sets it, the lede with the education address as a mailto link, and two buttons, "Find where to start", the hero\'s large primary button to the homepage\'s programs, and "Write to us", outlined, the mailto',
	array(
		found( $bx, '//p[' . has( 'wpc-pill' ) . ']' ),
		found( $bx, '//h1[' . has( 'wpc-hero__title' ) . ']' ),
		found( $bx, '//h1/em/span[' . has( 'wpc-hub-brush' ) . ']' ),
		found( $bx, '//h1/em' ),
		found( $bx, '//p[' . has( 'wpc-hub-lede' ) . ']' ),
		found( $bx, '//p[' . has( 'wpc-hub-lede' ) . ']/a', 'href' ),
		found( $bx, '//div[' . has( 'wpc-hero__actions' ) . ']/div', 'class' ),
		found( $bx, '//div[' . has( 'wpc-hero__actions' ) . ']//a' ),
		found( $bx, '//div[' . has( 'wpc-hero__actions' ) . ']//a', 'href' ),
	),
	array(
		array( 'Education programs from the WordPress project' ),
		array( 'This page is being updated. Back soon.' ),
		array( 'Back soon.' ),
		array( 'Back soon.' ),
		array( 'We\'re reworking this part of the Education Directory. In the meantime, find a program to start with, or write to us at education@wordpressfoundation.org.' ),
		array( 'mailto:education@wordpressfoundation.org' ),
		array( 'wp-block-button wpc-button--large', 'wp-block-button is-style-outline wpc-button--large' ),
		array( 'Find where to start', 'Write to us' ),
		array( 'https://site.example/#programs', 'mailto:education@wordpressfoundation.org' ),
	) );

ck( 'the address breaks cleanly on a phone: the lede\'s links may break anywhere where a line cannot hold them, two classes deep, and nowhere they fit',
	hub_rule( '.wpc-hub-hero .wpc-hub-lede a' ),
	array( 'overflow-wrap: anywhere;' ) );

echo "\n=== The forward to the homepage ===\n";

$forward_json = json_decode( theme_file( 'blocks/notice-forward/block.json' ), true );
$forward      = is_file( WPCREDITS_THEME_DIR . 'blocks/notice-forward/render.php' ) ? wpcredits_test_block( 'wpcredits/notice-forward' ) : '';
$fx           = dom_of( $forward );
$counts       = json_decode( (string) ( found( $fx, '//div[' . has( 'wpc-notice-forward' ) . ']', 'data-counts' )[0] ?? '' ), true );

ck( 'the forward is a block of the theme\'s, placed by the Notice template alone (never the 404 template or its pattern), registered in functions.php, whose view script counts down and forwards, and whose editor preview the server draws',
	array(
		is_array( $forward_json ) ? array( $forward_json['name'], $forward_json['viewScript'] ?? null, $forward_json['editorScript'] ?? null, $forward_json['render'] ?? null, $forward_json['style'] ?? null ) : null,
		false !== strpos( theme_file( 'functions.php' ), "register_block_type( get_theme_file_path( 'blocks/notice-forward' ) );" ),
		array( substr_count( $notice, 'wp:wpcredits/notice-forward' ), substr_count( theme_file( 'templates/404.html' ), 'notice-forward' ), substr_count( theme_file( 'patterns/hub-not-found.php' ), 'notice-forward' ), substr_count( theme_file( 'patterns/hub-mountain.php' ), 'notice-forward' ) ),
	),
	array(
		array( 'wpcredits/notice-forward', 'file:./view.js', 'file:./index.js', 'file:./render.php', 'wpcredits-hub' ),
		true,
		array( 1, 0, 0, 0 ),
	) );

ck( 'its markup: the homepage\'s address, the line for each second from 1 to 5, a translated plural each, and the stopped line, for the script; the line says 5 seconds, then "Go now", a link to the homepage, and "Stay on this page", a button; an empty polite live region, for screen readers, which the script fills twice, at the start and when the reader stays',
	array(
		found( $fx, '//div[' . has( 'wpc-notice-forward' ) . ']', 'data-home' ),
		$counts,
		found( $fx, '//div[' . has( 'wpc-notice-forward' ) . ']', 'data-stayed' ),
		found( $fx, '//*[' . has( 'wpc-notice-forward__count' ) . ']' ),
		found( $fx, '//a[' . has( 'wpc-notice-forward__go' ) . ']', 'href' ),
		found( $fx, '//a[' . has( 'wpc-notice-forward__go' ) . ']' ),
		found( $fx, '//button[' . has( 'wpc-notice-forward__stay' ) . ']', 'type' ),
		found( $fx, '//button[' . has( 'wpc-notice-forward__stay' ) . ']' ),
		found( $fx, '//*[@aria-live]', 'aria-live' ),
		found( $fx, '//*[@aria-live]' ),
	),
	array(
		array( 'https://site.example/' ),
		array( 'Taking you to the homepage in 1 second.', 'Taking you to the homepage in 2 seconds.', 'Taking you to the homepage in 3 seconds.', 'Taking you to the homepage in 4 seconds.', 'Taking you to the homepage in 5 seconds.' ),
		array( 'You\'re staying on this page.' ),
		array( 'Taking you to the homepage in 5 seconds.' ),
		array( 'https://site.example/' ),
		array( 'Go now' ),
		array( 'button' ),
		array( 'Stay on this page' ),
		array( 'polite' ),
		array( '' ),
	) );

ck( 'without JavaScript there is no forward and no countdown line: the line shows only where the page runs JavaScript (`wpc-js`, set first thing in the head, so it never shifts in), and the page keeps its buttons; there is no meta refresh anywhere',
	array(
		hub_rule( '.wpc-notice-forward' ),
		hub_rule( '.wpc-js .wpc-notice-forward' ),
		preg_match( '/http-equiv\s*=\s*["\']?refresh/i', $notice . $forward . theme_file( 'inc/hub.php' ) . theme_file( 'functions.php' ) ),
	),
	array( array( 'display: none;' ), array( 'display: block;' ), 0 ) );

ck( 'the countdown\'s line is a row that wraps, its count, Go now and Stay its items 12px apart and its wrapped rows 4px apart, so a control that wraps (Stay at 375px) starts flush with the line\'s left edge, with no space or margin before it; the stopped line, one text, is the row\'s one item',
	array(
		hub_rule( '.wpc-notice-forward .wpc-notice-forward__line' ),
		(bool) preg_match( '/margin-left/', implode( ' ', hub_rule( '.wpc-notice-forward .wpc-notice-forward__stay' ) ) ),
	),
	array(
		array( 'align-items: baseline; column-gap: 12px; display: flex; flex-wrap: wrap; margin: 0; row-gap: 4px;' ),
		false,
	) );

echo "\n=== The 404 template ===\n";

$not_found_template = theme_file( 'templates/404.html' );
$not_found          = pattern( 'hub-not-found.php' );
$nx                 = dom_of( $not_found );

preg_match_all( '#<!-- wp:([a-z/-]+)( \{.*?\})? /?-->#', $not_found_template, $not_found_blocks, PREG_SET_ORDER );

ck( 'the 404 template draws the same layout from its pattern: the two headers, a main marked as the hub holding the not-found pattern, the footer; no search block and no post content',
	array(
		array_map( static function ( $one ) { return $one[1] . ( isset( $one[2] ) && preg_match( '/"(?:slug|className)":"([^"]+)"/', $one[2], $named ) ? ' ' . $named[1] : '' ); }, $not_found_blocks ),
		array_intersect_key( header_of( 'hub-not-found.php' ), array_flip( array( 'Title', 'Slug', 'Categories', 'Inserter' ) ) ),
	),
	array(
		array( 'template-part wporg-header', 'template-part header', 'group wpc-main wpc-hub', 'pattern wpcredits/hub-not-found', 'template-part wporg-footer' ),
		array( 'Title' => 'Page not found', 'Slug' => 'wpcredits/hub-not-found', 'Categories' => 'wpcredits', 'Inserter' => 'no' ),
	) );

ck( 'its content: the pill "Page not found", the title "This path doesn\'t lead anywhere. Keep climbing." with "Keep climbing." in the brush, the lede, and two buttons, "Find where to start" (primary) to the homepage\'s programs and "Go to the homepage" (outlined); the mountain in the picture\'s column',
	array(
		found( $nx, '//div[' . has( 'wpc-hub-hero' ) . ']', 'class' ),
		found( $nx, '//p[' . has( 'wpc-pill' ) . ']' ),
		found( $nx, '//h1[' . has( 'wpc-hero__title' ) . ']' ),
		found( $nx, '//h1/em/span[' . has( 'wpc-hub-brush' ) . ']' ),
		found( $nx, '//p[' . has( 'wpc-hub-lede' ) . ']' ),
		found( $nx, '//div[' . has( 'wpc-hero__actions' ) . ']/div', 'class' ),
		found( $nx, '//div[' . has( 'wpc-hero__actions' ) . ']//a' ),
		found( $nx, '//div[' . has( 'wpc-hero__actions' ) . ']//a', 'href' ),
		found( $nx, '//div[' . has( 'wpc-hub-hero__art' ) . ']', 'class' ),
		substr_count( $not_found, '<!-- wp:pattern {"slug":"wpcredits/hub-mountain"} /-->' ),
	),
	array(
		array( 'wp-block-group alignwide wpc-hub-hero wpc-hub-notice' ),
		array( 'Page not found' ),
		array( 'This path doesn\'t lead anywhere. Keep climbing.' ),
		array( 'Keep climbing.' ),
		array( 'The address may have changed, or the page may have moved. Start from the programs, or go back to the homepage.' ),
		array( 'wp-block-button wpc-button--large', 'wp-block-button is-style-outline wpc-button--large' ),
		array( 'Find where to start', 'Go to the homepage' ),
		array( 'https://site.example/#programs', 'https://site.example/' ),
		array( 'wp-block-column is-vertically-aligned-bottom wpc-hub-hero__art' ),
		1,
	) );

// WordPress answers a request that finds nothing with the 404 status before it picks a template, in
// WP::handle_404(), and draws templates/404.html for it; a template changes only the look. The theme leaves
// the status to WordPress: nothing in it sets a status or skips WordPress's own 404 handling.
$php = '';

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPCREDITS_THEME_DIR, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	$below = substr( $file->getPathname(), strlen( WPCREDITS_THEME_DIR ) );

	if ( 'php' === $file->getExtension() && ! preg_match( '#^(bin|\.git|\.worktrees|node_modules)/#', $below ) ) {
		$php .= file_get_contents( $file->getPathname() );
	}
}

ck( 'the 404 page keeps the 404 status: the template is templates/404.html, which WordPress draws for a request that found nothing after it has sent the status, and no file of the theme sets a status, hooks `pre_handle_404`, `status_header` or `wp_headers`, or sends a redirect from a 404',
	array(
		is_file( WPCREDITS_THEME_DIR . 'templates/404.html' ),
		preg_match_all( '/\bstatus_header\s*\(|[\'"](?:pre_handle_404|status_header|wp_headers)[\'"]|\bwp_redirect\s*\(|\bwp_safe_redirect\s*\(/', $php ),
	),
	array( true, 0 ) );

echo "\n=== The hub's stylesheet on both ===\n";

/**
 * Whether the hub's stylesheet is enqueued for a request, as `wpcredits_hub_assets()` decides it.
 *
 * @param array $request `singular`, `id`, `templates` and `404`.
 * @return bool
 */
function hub_styled( array $request ) {
	$GLOBALS['request']  = $request + array( 'singular' => '', 'id' => 0, 'templates' => array(), '404' => false );
	$GLOBALS['enqueued'] = array();

	wpcredits_hub_assets();

	$styled = in_array( 'wpcredits-hub', (array) $GLOBALS['enqueued'], true );

	$GLOBALS['request'] = array( 'singular' => '', 'id' => 0, 'templates' => array() );

	return $styled;
}

ck( 'the hub\'s stylesheet, its fonts with it, loads on a page drawn by the hub template, on a page drawn by the Notice template and on the 404 page, and on no other page, post or archive',
	array(
		hub_styled( array( 'singular' => 'page', 'id' => 5, 'templates' => array( 5 => 'page-education-hub' ) ) ),
		hub_styled( array( 'singular' => 'page', 'id' => 6, 'templates' => array( 6 => 'page-notice' ) ) ),
		hub_styled( array( '404' => true ) ),
		hub_styled( array( 'singular' => 'page', 'id' => 7, 'templates' => array( 7 => '' ) ) ),
		hub_styled( array( 'singular' => 'post', 'id' => 6, 'templates' => array( 6 => 'page-notice' ) ) ),
		hub_styled( array() ),
	),
	array( true, true, true, false, false, false ) );

$hub_php = theme_file( 'inc/hub.php' );

ck( 'one rule decides it: `wpcredits_is_hub_page()` takes the template to ask about, the hub\'s unless told, and the enqueue asks it for the hub and for the Notice template and asks `is_404()`; the template\'s slug is written once, as a constant; the page-hierarchy rule still asks about the hub alone',
	array(
		(bool) preg_match( '/function wpcredits_is_hub_page\( \$template = WPCREDITS_HUB_TEMPLATE \)/', $hub_php ),
		substr_count( $hub_php, "'page-notice'" ),
		(bool) preg_match( '/wpcredits_is_hub_page\(\) \|\| wpcredits_is_hub_page\( WPCREDITS_NOTICE_TEMPLATE \) \|\| is_404\(\)/', $hub_php ),
		theme_call( 'wpcredits_is_hub_page' ),
	),
	array( true, 1, true, false ) );

echo "\n=== The mountain ===\n";

$hero     = pattern( 'hub-hero.php' );
$mountain = (string) theme_call( 'wpcredits_hub_mountain_svg' );
$art      = pattern( 'hub-mountain.php' );
$ax       = dom_of( $art );

ck( 'the hero draws the picture it drew in 1.32.11, byte for byte, its mountain now the shared `wpcredits_hub_mountain_svg()`, which the Notice and 404 pages draw too, written once',
	array( sha1( $hero ), strlen( $hero ), sha1( $mountain ), '' !== $mountain && 1 === substr_count( $hero, $mountain ), '' !== $mountain && 1 === substr_count( $art, $mountain ), substr_count( theme_file( 'patterns/hub-hero.php' ) . theme_file( 'patterns/hub-mountain.php' ), 'wpc-hub-mountain-words' ) ),
	array( '00aecce88473cd6b4f33853983c6d859154d1a88', 15941, 'c5570c50c2083f794d6504b1b00655167233c0df', true, true, 0 ) );

ck( 'the pages\' picture is a Custom HTML block hidden from screen readers, the hero\'s picture box holding the mountain of role words alone, and nothing to focus',
	array(
		substr_count( $art, '<!-- wp:html -->' ),
		found( $ax, '/html/body/div', 'class' ),
		found( $ax, '/html/body/div', 'aria-hidden' ),
		found( $ax, '/html/body/div/*', 'class' ),
		count( $ax->query( '//a | //button | //*[@tabindex]' ) ),
	),
	array( 1, array( 'wpc-hub-art' ), array( 'true' ), array( 'wpc-hub-art__mountain' ), 0 ) );

// The product owner, after the Docker trial: no goat (theme 1.32.12, before release). Nothing of it is left:
// no function, no markup on either page, no rule, no motion, no word in the theme's files or its template.
$theme_text = '';

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPCREDITS_THEME_DIR, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	$below = substr( $file->getPathname(), strlen( WPCREDITS_THEME_DIR ) );

	if ( in_array( $file->getExtension(), array( 'php', 'html', 'css', 'js', 'json', 'txt', 'pot' ), true ) && ! preg_match( '#^(bin|\.git|\.worktrees|\.superpowers|node_modules)/#', $below ) ) {
		$theme_text .= file_get_contents( $file->getPathname() );
	}
}

ck( 'neither page prints any goat: the pages\' picture, the 404 page\'s pattern and the Notice template hold none, no function draws one, hub.css has no rule, animation or keyframes for one, and no file of the theme, its readme and translation template among them, names a goat',
	array(
		preg_match_all( '/goat|tumbl/i', $art . $not_found . $notice . $not_found_template ),
		function_exists( 'wpcredits_hub_goat_svg' ),
		count( array_filter( hub_rules(), static function ( $rule ) { return false !== stripos( $rule['selector'] . ' ' . $rule['declarations'], 'goat' ); } ) ),
		count( array_filter( hub_rules(), static function ( $rule ) { return 0 === strpos( $rule['selector'], '@keyframes' ); } ) ),
		preg_match_all( '/goat/i', $theme_text ),
	),
	array( 0, false, 0, 0, 0 ) );

$phone_at = '@media ( max-width: 899px )';
$overrides = array_values( array_filter( hub_rules(), static function ( $rule ) { return false !== strpos( $rule['selector'], 'wpc-hub-hero__art' ) && false !== strpos( $rule['declarations'], 'display' ); } ) );

ck( 'the pages\' picture follows the hero\'s own rule: its column is the hero\'s picture column, which hub.css hides below 900px and no rule shows again, so on a phone the copy column, the stacked columns\' only one, takes the whole width as the hero\'s does; the Notice template and the 404 page\'s pattern each have the hero\'s stacking columns and its copy column',
	array(
		array_map( static function ( $rule ) { return array( $rule['at'], $rule['selector'], $rule['declarations'] ); }, $overrides ),
		hub_rule( '.wp-block-columns.wpc-hub-stack > .wp-block-column', $phone_at ),
		false !== strpos( theme_file( 'assets/css/hub.css' ), 'wpc-hub-notice__art' ),
		array( substr_count( $notice, '"className":"wpc-hub-hero__cols wpc-hub-stack"' ), substr_count( $not_found, '"className":"wpc-hub-hero__cols wpc-hub-stack"' ) ),
		array( substr_count( $notice, '{"width":"47%","className":"wpc-hub-hero__copy"}' ), substr_count( $not_found, '{"width":"47%","className":"wpc-hub-hero__copy"}' ) ),
		array( substr_count( $notice, '"className":"wpc-hub-hero__art"' ), substr_count( $not_found, '"className":"wpc-hub-hero__art"' ) ),
	),
	array(
		array( array( $phone_at, '.wp-block-column.wpc-hub-hero__art', 'display: none;' ) ),
		array( 'flex-basis: 100% !important;' ),
		false,
		array( 1, 1 ),
		array( 1, 1 ),
		array( 1, 1 ),
	) );

echo "\n=== The Landing page template and the mentors' mountain (theme 1.32.14) ===\n";

$landing = theme_file( 'templates/page-landing.html' );

ck( 'the Landing page template is the hub\'s main around the page\'s own content, between the site\'s headers and footer, with no title and no pattern of its own',
	array(
		substr_count( $landing, '<main class="wp-block-group wpc-main wpc-hub wpc-landing">' ),
		substr_count( $landing, '<!-- wp:post-content {"align":"wide","className":"wpc-landing__content","layout":{"type":"default"}} /-->' ),
		substr_count( $landing, 'wp:post-title' ) + substr_count( $landing, 'wp:pattern' ),
		substr_count( $landing, '"slug":"wporg-header"' ) + substr_count( $landing, '"slug":"header"' ) + substr_count( $landing, '"slug":"wporg-footer"' ),
	),
	array( 1, 1, 0, 3 ) );

ck( 'the hub\'s stylesheet loads on a page it draws, and on no post that stores its name',
	array(
		hub_styled( array( 'singular' => 'page', 'id' => 8, 'templates' => array( 8 => 'page-landing' ) ) ),
		hub_styled( array( 'singular' => 'post', 'id' => 8, 'templates' => array( 8 => 'page-landing' ) ) ),
		substr_count( $hub_php, "'page-landing'" ),
	),
	array( true, false, 1 ) );

$team      = (string) theme_call( 'wpcredits_hub_mountain_team_svg' );
$mentors   = pattern( 'mentors-mountain.php' );
$with_team = (string) theme_call( 'wpcredits_hub_mountain_svg', $team );

ck( 'the mentors\' mountain is the hero\'s own with a party on its trail: the same drawing to its last shape, then a rope, three students, a guide and two labels, inside the one svg',
	array(
		0 === strpos( $with_team, substr( $mountain, 0, -6 ) ),
		'</svg>' === substr( $with_team, -6 ) && substr( $mountain, 0, -6 ) . $team . '</svg>' === $with_team,
		substr_count( $team, 'wpc-hub-art__person--student' ),
		substr_count( $team, 'wpc-hub-art__person--guide' ),
		substr_count( $team, 'class="wpc-hub-art__rope"' ),
		substr_count( $team, '>You, the guide</text>' ) + substr_count( $team, '>Your students</text>' ),
		1 === substr_count( $mentors, $with_team ),
	),
	array( true, true, 3, 1, 1, 2, true ) );

ck( 'and its pattern is a Custom HTML block hidden from screen readers, with nothing to focus, as the pages\' mountain is',
	array(
		substr_count( $mentors, '<div class="wpc-hub-art" aria-hidden="true">' ),
		substr_count( $mentors, '<!-- wp:html -->' ),
		preg_match( '/<a\b|<button\b|tabindex/', $mentors ),
	),
	array( 1, 1, 0 ) );

$graduation         = (string) theme_call( 'wpcredits_hub_graduation_svg' );
$graduation_pattern = pattern( 'students-graduation.php' );

ck( 'the students\' graduation cap (theme 1.32.15) is cut from the mountain\'s own field of words, with IDs of its own, a tassel, a button and a tuft, no person and one label, and its pattern is a Custom HTML block hidden from screen readers with nothing to focus',
	array(
		1 === substr_count( $graduation, (string) theme_call( 'wpcredits_hub_word_rows' ) ) && 1 === substr_count( $mountain, (string) theme_call( 'wpcredits_hub_word_rows' ) ),
		substr_count( $graduation, 'id="wpc-hub-graduation-' ),
		substr_count( $graduation, 'wpc-hub-mountain-' ),
		substr_count( $graduation, 'class="wpc-hub-art__route"' ) + substr_count( $graduation, 'class="wpc-hub-art__button"' ) + substr_count( $graduation, 'class="wpc-hub-art__pennant"' ),
		substr_count( $graduation, 'wpc-hub-art__person ' ),
		substr_count( $graduation, 'class="wpc-hub-art__tag ' ),
		substr_count( $graduation, '>Academic credit</text>' ),
		1 === substr_count( $graduation_pattern, $graduation ),
		substr_count( $graduation_pattern, '<div class="wpc-hub-art" aria-hidden="true">' ),
		preg_match( '/<a\b|<button\b|tabindex/', $graduation_pattern ),
	),
	array( true, 3, 0, 3, 0, 1, 1, true, 1, 0 ) );

$hub_css = theme_file( 'assets/css/hub.css' );

ck( 'hub.css dresses every class the party is drawn with',
	array_map(
		static function ( $class ) use ( $hub_css ) {
			return false !== strpos( $hub_css, '.' . $class );
		},
		array( 'wpc-hub-art__rope', 'wpc-hub-art__person--student', 'wpc-hub-art__person--guide', 'wpc-hub-art__badge', 'wpc-hub-art__tag', 'wpc-hub-art__tag--guide', 'wpc-hub-art__button' )
	),
	array( true, true, true, true, true, true, true ) );

echo "\n=== The words ===\n";

$sources = theme_file( 'templates/page-notice.html' ) . theme_file( 'templates/404.html' ) . theme_file( 'patterns/hub-back-soon.php' ) . theme_file( 'patterns/hub-not-found.php' ) . theme_file( 'patterns/hub-mountain.php' ) . theme_file( 'blocks/notice-forward/render.php' ) . theme_file( 'blocks/notice-forward/view.js' );

ck( 'no em dash, no en dash, no exclamation mark in the pages\' words, and never "Education Hub"; the patterns\' words are translated strings',
	array(
		preg_match( "/\xE2\x80\x94|\xE2\x80\x93/", $sources ),
		preg_match( '/(?:__|_e|esc_html__|esc_html_e|_n)\( \'[^\']*!/', $sources ),
		false !== strpos( $sources, 'Education Hub' ),
		false !== strpos( theme_file( 'patterns/hub-back-soon.php' ), "esc_html_e( 'Find where to start', 'wpcredits-theme' )" ),
	),
	array( 0, 0, false, true ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
