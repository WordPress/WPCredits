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
 * a link in it is one text; the decorative arrows are left out.
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

		if ( '' !== trim( preg_replace( '/[0-9,.%→\s]+/u', '', $left ) ) ) {
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

echo "\n=== The content: six programs, seven steps, four levels ===\n";

$programs = wpcredits_hub_programs();
$steps    = wpcredits_hub_steps();
$levels   = wpcredits_hub_levels();

ck( 'six programs in Alana\'s order, each with the anchor its card is linked by',
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

// The Builder step lists only what a Builder earns: Alana Warner (16 October 2026), "should just be:
// showcase eligibility, badge on .org profile"; Isotta Peira: the domain and hosting are a WordPress.com
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

ck( 'the nine links of the program cards, each where the design note\'s 6.5 says, WordPress Credits\' two track links giving way, since 16 October 2026, to the band of its four courses and the program page',
	links_said( array_merge( ...array_column( $programs, 'links' ) ) ),
	array(
		'Find or host an event > https://events.wordpress.org/campusconnect/',
		'Compare the four courses > #wordpress-credits-courses',
		'About the program > https://wordpress.org/education/credits/',
		'Impact dashboard > https://wordpress.github.io/WPCredits/',
		'Facilitation guides > https://make.wordpress.org/community/handbook/education/wordpress-facilitator-training-program/',
		'Courses > https://learn.wordpress.org/',
		'Browse courses > https://learn.wordpress.org/',
		'Learn more > https://ai-leaders.org/',
		'Learn more > https://wordpresscertifications.com/wordpress-builder-certification/',
	) );

// Isotta Peira (15 Oct 2026, #wpcredits-admins on WordPress Slack), Alana Warner agreeing: the site should
// list the WordPress Credits courses, so schools and students can pick one; the owner (16 Oct 2026): as a
// band of four cards under the index. Each says what its public Learn page says; the hours are Intro to
// Contribution's page's and, for Foundations, the owner's (16 Oct 2026), and the two tracks, whose pages
// give none, say who they are for.
ck( 'WordPress Credits alone has courses: four, each its Learn page, its line above the name and its line on what it asks',
	array(
		array_map( static function ( $program ) { return count( isset( $program['courses'] ) ? $program['courses'] : array() ); }, $programs ),
		array_map( static function ( $course ) { return $course['name'] . ' > ' . $course['url'] . ' : ' . $course['eyebrow'] . ' / ' . $course['line']; }, $programs[1]['courses'] ),
	),
	array(
		array( 0, 4, 0, 0, 0, 0 ),
		array(
			'Intro to Contribution > https://learn.wordpress.org/course/wordpress-credits-intro-to-contribution/ : 50 hours / One guided contribution project: a first look at open source, for a shorter course or an elective.',
			'Foundations > https://learn.wordpress.org/course/wordpress-credits-foundations/ : 150 hours or more / Your own site and a contribution that ships, for an internship, a capstone, or a full semester.',
			'Developer track > https://learn.wordpress.org/course/wordpress-credits-developer-track/ : For developers / WordPress developer training and patch testing practice, toward a contribution that ships.',
			'Designer track > https://learn.wordpress.org/course/wordpress-credits-designer-track/ : For designers / A portfolio, Figma and the Site Editor\'s design tools, and a block pattern of your own.',
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

ck( 'every text is one translated string, seen whole by a translator: 145 of them, 56 on the cards and the band of courses (the band\'s twelve among them), 61 on the steps and 28 on the levels, and "(planned)" among them',
	array( count( $texts ), array_values( $untranslated ), false !== strpos( $mark, '<span class="wpc-hub-planned">[(planned)]</span>' ) ),
	array( 145, array(), true ) );

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
	array( array( 'Intro to Contribution', 'Foundations', 'Developer track', 'Designer track' ), array( '50 hours', '150 hours or more', 'For developers', 'For designers' ), true ) );

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

ck( 'under the cards, the band of the four courses: a section named by its heading, the programs section\'s head (eyebrow, heading, lede), and a card each, its name a link to its Learn page with a decorative arrow, then its line (drawn above the name), and what it asks',
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
		array( '50 hours', '150 hours or more', 'For developers', 'For designers' ),
		array( 'Intro to Contribution →', 'Foundations →', 'Developer track →', 'Designer track →' ),
		array( 'https://learn.wordpress.org/course/wordpress-credits-intro-to-contribution/', 'https://learn.wordpress.org/course/wordpress-credits-foundations/', 'https://learn.wordpress.org/course/wordpress-credits-developer-track/', 'https://learn.wordpress.org/course/wordpress-credits-designer-track/' ),
		array( 'One guided contribution project: a first look at open source, for a shorter course or an elective.', 'Your own site and a contribution that ships, for an internship, a capstone, or a full semester.', 'WordPress developer training and patch testing practice, toward a contribution that ships.', 'A portfolio, Figma and the Site Editor\'s design tools, and a block pattern of your own.' ),
		4,
		0,
	) );

ck( 'what you get is a list, one item to a line, the three planned ones marked on their own line',
	array( found( $xp, '//article[@id="program-wordpress-credits"]//ul[@class="wpc-hub-items"]/li' ), found( $xp, '//article//span[@class="wpc-hub-planned"]/..' ) ),
	array( array( 'Certificate', 'Badge', 'Hours transcript (planned)', 'Credit' ), array( 'Hours transcript (planned)', 'Facilitator listing (planned)', '$1,000 for completers (planned)' ) ) );

ck( 'the mark is kept on its item\'s line by a no-break space',
	false !== strpos( $index, '<li>Hours transcript&nbsp;<span class="wpc-hub-planned">(planned)</span></li>' ), true );

ck( 'the band sits 56px under the cards, as the field-of-study table sits under it, and lands below the sticky header; its cards the program cards\' white boxes, one column, two from 640px and four from 900px; its line an eyebrow drawn above the name it follows, the name 18px in the brand, what it asks ink-70 at 14px; two classes deep',
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
			array( '@media ( min-width: 900px )', 'grid-template-columns: repeat( 4, minmax( 0, 1fr ) );' ),
		),
		array( 'background: var( --wpc-surface ); border: 1px solid var( --wpc-line-card ); border-radius: var( --wpc-radius-card ); display: flex; flex-direction: column; gap: 8px; margin: 0; min-width: 0; padding: 24px;' ),
		array( 'color: var( --wpc-ink-60 ); font: 500 12px/18px var( --wpc-font ); letter-spacing: .06em; margin: 0; order: -1; text-transform: uppercase;' ),
		array( 'font-size: 18px; line-height: 1.3; margin: 0;' ),
		array( 'color: var( --wpc-brand ); text-decoration: none;' ),
		array( 'color: var( --wpc-ink-70 ); font-size: 14px; line-height: 1.55; margin: 0;' ),
	) );

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

ck( 'the toggles, the chips and the pathway\'s switch alike: white, the control border, ink-70 at 14px and 36px tall; pressed, the brand tint with an inset brand ring and weight 600; in forced colors, the system\'s highlight',
	array( rules_for( '.wpc-hub-chip, .wpc-hub-switch__option' ), rules_for( '.wpc-hub-chip[aria-pressed="true"], .wpc-hub-switch__option[aria-pressed="true"]' ) ),
	array(
		array( 'align-items: center; background: var( --wpc-surface ); border: 1px solid var( --wpc-control-border ); border-radius: var( --wpc-radius-pill ); color: var( --wpc-ink-70 ); cursor: pointer; display: inline-flex; font: 400 14px/20px var( --wpc-font ); min-height: 36px; padding: 7px 14px;' ),
		array( 'background: var( --wpc-brand-tint ); border-color: var( --wpc-brand ); box-shadow: inset 0 0 0 1px var( --wpc-brand ); color: var( --wpc-ink ); font-weight: 600;', 'background: Highlight; color: HighlightText; forced-color-adjust: none;' ),
	) );

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

// The owner (15 Oct 2026, with Alana Warner): make it plain that a step opens more. Every step ends with a
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

ck( 'every planned item of the steps and the levels is marked, 25 of them, and under each ladder one line says what the mark means',
	array( count( $px->query( '//article//span[@class="wpc-hub-planned"]' ) ), found( $px, '//p[@class="wpc-hub-ladder__key"]' ) ),
	array( 25, array( 'Items marked planned are not offered yet.', 'Items marked planned are not offered yet.' ) ) );

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

ck( 'the staircase\'s count and rise are declared in the sheet, as every token the theme reads is, and a step\'s color runs from the brand tint to the brand border through its rise',
	array( rules_for( '.wpc-hub-stairs' )[0], rules_for( '.wpc-hub-stair' )[0] ),
	array(
		'--wpc-count: 7; display: grid; gap: 8px; list-style: none; margin: 0; padding: 0;',
		'--wpc-t: 0; background: color-mix( in srgb, var( --wpc-brand-border ) calc( var( --wpc-t ) * 100% ), var( --wpc-brand-tint ) ); border-radius: var( --wpc-radius-panel ); display: flex; flex-direction: column; gap: 6px; padding: 14px; position: relative;',
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

ck( 'at 900px and wider the steps rise from 160px to 360px; below, a plain row each, with a 6px brand bar along its bottom edge widening from one step\'s share of the row to the whole row, 20px of padding under the text, and the pressed row\'s bar in the brand tint',
	array(
		rules_for( '.wpc-hub-stair' )[1],
		rules_for( '.wpc-hub-stair' )[2],
		rules_for( '.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] )' ),
		$bar_ends,
		contrast( color_of( 'ink-70' ), color_of( 'surface-band' ) ),
	),
	array(
		'border-radius: var( --wpc-radius-card ) var( --wpc-radius-card ) 0 0; min-height: calc( 160px + 200px * var( --wpc-t ) );',
		'--wpc-bar: var( --wpc-brand ); background: linear-gradient( var( --wpc-bar ), var( --wpc-bar ) ) left bottom / ' . $bar_width . ' 6px no-repeat var( --wpc-surface-band ); padding-bottom: 20px;',
		array( 'background-color: var( --wpc-brand );', 'background: Highlight; forced-color-adjust: none;', '--wpc-bar: var( --wpc-brand-tint );' ),
		array( array( 14.29, 100.0 ), array( 25.0, 100.0 ) ),
		'8.67',
	) );

ck( 'the step\'s one movement, the transition and the lift under the pointer or with the keyboard\'s focus on it (not a click\'s), each lift a rule of its own, is declared only for a reader who has not asked for less',
	array(
		(bool) preg_match( '/@media \( prefers-reduced-motion: no-preference \) \{\s*\.wpc-hub-pathway\.is-interactive \.wpc-hub-stair \{\s*transition: transform \.15s ease;\s*\}\s*\.wpc-hub-pathway\.is-interactive \.wpc-hub-stair:hover \{\s*transform: translateY\( -4px \);\s*\}\s*\.wpc-hub-pathway\.is-interactive \.wpc-hub-stair:has\( > \.wpc-hub-stair__pick:focus-visible \) \{\s*transform: translateY\( -4px \);\s*\}\s*\}/', theme_file( 'assets/css/hub.css' ) ),
		substr_count( theme_file( 'assets/css/hub.css' ), 'translateY(' ),
		substr_count( theme_file( 'assets/css/hub.css' ), ':focus-within' ),
	),
	array( true, 2, 0 ) );

ck( 'the hint is a tinted prompt in the dark brand on the brand tint (6.9:1), 14px, a down chevron before it, 16px under the lede while the script shows it (22px, the lede\'s own, without it) and 22px above the steps; two classes deep, over the theme\'s paragraph rules',
	array(
		rules_for( '.wpc-hub-pathway .wpc-hub-ladder__hint' ),
		rules_for( '.wpc-hub-pathway .wpc-hub-ladder__hint::before' ),
		rules_for( '.wpc-hub-pathway.is-interactive .wpc-hub-ladder__lede' ),
		contrast( color_of( 'brand-dark' ), color_of( 'brand-tint' ) ),
	),
	array(
		array( 'align-items: center; background: var( --wpc-brand-tint ); border-radius: 999px; color: var( --wpc-brand-dark ); display: flex; font-size: 14px; font-weight: 500; gap: 8px; line-height: 20px; margin: 0 0 22px; padding: 6px 14px 6px 12px; width: fit-content;' ),
		array( 'border-bottom: 2px solid currentColor; border-right: 2px solid currentColor; content: ""; height: 7px; margin-top: -4px; transform: rotate( 45deg ); width: 7px;' ),
		array( 'margin-bottom: 16px;' ),
		'6.90',
	) );

ck( 'the cue sits at the foot of its step in the dark brand at 13px (6.9:1 on the lightest step, 5.13 on the darkest), white on the step pressed (5.61), its second line shown only there and the first then not, with a down chevron after it',
	array(
		rules_for( '.wpc-hub-stair .wpc-hub-stair__cue' ),
		rules_for( '.wpc-hub-stair .wpc-hub-stair__cue::after' ),
		rules_for( '.wpc-hub-stair .wpc-hub-stair__shown' ),
		rules_for( '.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) .wpc-hub-stair__see' ),
		rules_for( '.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) .wpc-hub-stair__shown' ),
		rules_for( '.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) .wpc-hub-stair__cue' ),
		array( contrast( color_of( 'brand-dark' ), color_of( 'brand-tint' ) ), contrast( color_of( 'brand-dark' ), color_of( 'brand-border' ) ) ),
	),
	array(
		array( 'align-items: center; color: var( --wpc-brand-dark ); display: inline-flex; font-size: 13px; font-weight: 600; gap: 6px; line-height: 18px; margin-top: auto; padding-top: 6px;' ),
		array( 'border-bottom: 2px solid currentColor; border-right: 2px solid currentColor; content: ""; height: 6px; margin-top: -3px; transform: rotate( 45deg ); width: 6px;' ),
		array( 'display: none;' ),
		array( 'display: none;' ),
		array( 'display: inline;' ),
		array( 'color: var( --wpc-surface );' ),
		array( '6.90', '5.13' ),
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

ck( 'and the contrast: ink-70 on the darkest step 6.36 and on the lightest 8.55, white on the brand 5.61, the brand tint on the brand 5.03',
	array( contrast( color_of( 'ink-70' ), color_of( 'brand-border' ) ), contrast( color_of( 'ink-70' ), color_of( 'brand-tint' ) ), contrast( color_of( 'base' ), color_of( 'brand' ) ), contrast( color_of( 'brand-tint' ), color_of( 'brand' ) ) ),
	array( '6.36', '8.55', '5.61', '5.03' ) );

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

ck( 'the figure\'s number: brand, 44px and bold, in tabular figures, 34px below 900px',
	array( rules_for( '.wpc-figure__value' ) ),
	array( array( 'color: var( --wpc-brand ); font-size: 44px; font-variant-numeric: tabular-nums; font-weight: 700; letter-spacing: -.02em; line-height: 1.14;', 'font-size: 34px;' ) ) );

ck( 'the rows of figures: padded 14px, divided by the card hairline, the children\'s margins reset, and the row\'s own margin reset with two classes, so a column\'s block gap (WordPress prints it with one class, often later) never opens a gap above a row',
	array( rules_for( '.wpc-figure' ), rules_for( '.wpc-figure + .wpc-figure' ), rules_for( '.wpc-figure > *' ), rules_for( '.wp-block-wpcredits-program-stat.wpc-figure' ) ),
	array( array( 'padding: 14px 0;' ), array( 'border-top: 1px solid var( --wpc-line-card );' ), array( 'margin: 0;' ), array( 'margin-block: 0;' ) ) );

ck( 'the label and the base: 16px ink-70 and 14px ink-60 under the number; the source line 14px ink-60 with 10px above, written with two classes so the same block gap cannot beat it, and no rule for it with one',
	array( rules_for( '.wpc-figure__label' ), rules_for( '.wpc-figure__base' ), rules_for( '.wp-block-wpcredits-program-stat.wpc-figure-source' ), rules_for( '.wpc-figure-source' ) ),
	array(
		array( 'color: var( --wpc-ink-70 ); font-size: 16px; line-height: 1.4; margin-top: 4px;' ),
		array( 'color: var( --wpc-ink-60 ); font-size: 14px; margin-top: 2px;' ),
		array( 'color: var( --wpc-ink-60 ); font-size: 14px; margin: 10px 0 0;' ),
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
	$known = array( 'group', 'columns', 'column', 'paragraph', 'heading', 'buttons', 'button', 'table', 'wpcredits/program-stat', 'wpcredits/program-index', 'wpcredits/pathway' );
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

ck( 'every section is wide, padded 48px above and below, the hero\'s panel 56px above and below and 48px at each side',
	array_map(
		static function ( $file ) {
			$xpath = dom_of( wpcredits_test_pattern( $file ) );

			return implode( ' | ', array( found( $xpath, '/html/body/div', 'class' )[0], found( $xpath, '/html/body/div | /html/body/div/div[1]', 'style' )[ 'hub-hero.php' === $file ? 1 : 0 ] ) );
		},
		$pattern_files
	),
	array(
		'wp-block-group alignwide wpc-hub-hero | padding-top:56px;padding-right:48px;padding-bottom:56px;padding-left:48px',
		'wp-block-group alignwide wpc-hub-doors | padding-top:48px;padding-bottom:48px',
		'wp-block-group alignwide wpc-hub-anchored | padding-top:48px;padding-bottom:48px',
		'wp-block-group alignwide wpc-hub-anchored | padding-top:48px;padding-bottom:48px',
		'wp-block-group alignwide wpc-hub-cta | padding-top:48px;padding-bottom:48px',
	) );

$hero = wpcredits_test_pattern( 'hub-hero.php' );

ck( 'the hero: its title in two strings, the second in brand blue, and four figure blocks, three figures and their source',
	array( false !== strpos( $hero, '<h1 class="wp-block-heading wpc-hero__title has-display-font-size" style="margin-top:0;margin-bottom:18px">Start learning with WordPress. <em>Every step counts. Keep climbing.</em></h1>' ), preg_match_all( '#<!-- wp:wpcredits/program-stat \{"stat":"([a-z-]+)"\} /-->#', $hero, $stats ), $stats[1] ),
	array( true, 4, array( 'graduation', 'recommend', 'keep', 'outcomes-source' ) ) );

echo "\n=== The page ===\n";

WPCPM_Outcome_Figures::$published = $monday;
$page_html                        = wpcredits_test_page();
$page                             = dom_of( $page_html );

ck( 'without JavaScript the page holds every part: the three figures and their source, the four sign-in cards, the doors, six programs, the field-of-study table, both ladders with all eleven details, and the band',
	array( count( $page->query( '//div[contains(@class,"wpc-figure")]' ) ), count( $page->query( '//p[contains(@class,"wpc-figure-source")]' ) ), count( $page->query( '//div[contains(@class,"wpc-hub-sign-in__card")]' ) ), count( $page->query( '//div[contains(concat(" ", @class, " ")," wpc-hub-doors ")]//div[contains(concat(" ", @class, " ")," wpc-hub-door ")]' ) ), count( $page->query( '//article[@class="wpc-hub-program"]' ) ), count( $page->query( '//figure[contains(@class,"wpc-hub-fields")]//tbody/tr' ) ), count( $page->query( '//section[@class="wpc-hub-ladder"]' ) ), count( $page->query( '//article[@class="wpc-hub-detail"]' ) ), count( $page->query( '//div[contains(@class,"wpc-hub-cta__card")]' ) ) ),
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
			'Compare the four courses → > #wordpress-credits-courses',
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
	array( 20, 20 ) );

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

echo "\n=== The field-of-study table ===\n";

ck( 'seven fields, three columns, a header section, no fixed layout',
	array( found( $page, '//figure[contains(@class,"wpc-hub-fields")]//th' ), array_chunk( found( $page, '//figure[contains(@class,"wpc-hub-fields")]//tbody/tr/td' ), 3 ), found( $page, '//figure[contains(@class,"wpc-hub-fields")]/table', 'class' ) ),
	array(
		array( 'Field of study', 'Where you\'ll work', 'What you\'ll have to show' ),
		array(
			array( 'Computer science, engineering', 'Core, plugins, testing, accessibility', 'Merged code and reviewed patches' ),
			array( 'Design, arts, UX', 'Design, block patterns, themes', 'Visual work in use on real sites' ),
			array( 'Communications, journalism, marketing', 'Documentation, Learn WordPress, Marketing', 'Published work with a byline' ),
			array( 'Translation, languages', 'Polyglots', 'Published translations' ),
			array( 'Business, events, tourism', 'Community, WordCamps', 'An event you ran, with attendance numbers' ),
			array( 'Education', 'Learn WordPress lesson plans, Training', 'Course material in public use' ),
			array( 'Accessibility, social work', 'Accessibility, testing', 'Audit work on a global platform' ),
		),
		array( '' ),
	) );

// "Education" is two strings: the field of study in this table, and the accent word picked out of the
// site title, which must match the word in the translated title. One msgid would allow one translation
// for both; a language that names them differently (Polish: "Pedagogika" and "Edukacja") needs two.
$GLOBALS['translated'] = array(
	'Education'                       => 'Edukacja',
	"a field of study\4Education"      => 'Pedagogika',
);
$fields_translated = found( dom_of( wpcredits_test_pattern( 'hub-programs.php' ) ), '//tbody/tr/td[1]' );
$accent_translated = wpcredits_accent_word( 'WordPress Edukacja Initiatives' );
$GLOBALS['translated'] = array();

ck( 'the field of study "Education" and the site title\'s accent word "Education" are translated apart: the table reads the field\'s own translation, the accent word the plain one',
	array( in_array( 'Pedagogika', $fields_translated, true ), in_array( 'Edukacja', $fields_translated, true ), $accent_translated ),
	array( true, false, 'WordPress <em>Edukacja</em> <i>Initiatives</i>' ) );

ck( 'its box is a region named by the heading above it, with a tab stop, so the scroll box is reached by keyboard',
	array( found( $page, '//figure[contains(@class,"wpc-hub-fields")]', 'tabindex' ), found( $page, '//figure[contains(@class,"wpc-hub-fields")]', 'role' ), found( $page, '//figure[contains(@class,"wpc-hub-fields")]', 'aria-labelledby' ), found( $page, '//*[@id="wpc-hub-fit"]' ) ),
	array( array( '0' ), array( 'region' ), array( 'wpc-hub-fit' ), array( 'Find where you fit in WordPress Credits' ) ) );

ck( 'the filter: a table edited in the Site Editor, its words, its classes and an anchor changed, is still the region, a tab stop it already had replaced where it stands, as core does; another table is left as it is',
	array(
		apply_filters( 'render_block_core/table', '<figure class="wp-block-table is-style-stripes wpc-hub-fields alignwide" tabindex="-1" id="fields"><table><tbody><tr><td>Law</td></tr></tbody></table></figure>' ),
		apply_filters( 'render_block_core/table', '<figure class="wp-block-table"><table><tbody><tr><td>wpc-hub-fields-like text</td></tr></tbody></table></figure>' ),
		apply_filters( 'render_block_core/table', '<figure class="wp-block-table wpc-hub-fieldset"><table></table></figure>' ),
	),
	array(
		'<figure class="wp-block-table is-style-stripes wpc-hub-fields alignwide" tabindex="0" id="fields" role="region" aria-labelledby="wpc-hub-fit"><table><tbody><tr><td>Law</td></tr></tbody></table></figure>',
		'<figure class="wp-block-table"><table><tbody><tr><td>wpc-hub-fields-like text</td></tr></tbody></table></figure>',
		'<figure class="wp-block-table wpc-hub-fieldset"><table></table></figure>',
	) );

ck( 'below 640px the table keeps its columns and scrolls inside its box',
	(bool) preg_match( '/@media \( max-width: 640px \) \{\s*\.wp-block-table\.wpc-hub-fields table \{\s*min-width: 520px;/', theme_file( 'assets/css/hub.css' ) ), true );

echo "\n=== The words ===\n";

$GLOBALS['marked'] = true;
$marked_page       = wpcredits_test_page();
$GLOBALS['marked'] = false;

ck( 'every word on the page, and every label and announcement, is a translated string',
	unmarked( $marked_page ), array() );

$words = trim( preg_replace( '/\s+/u', ' ', $page->query( '//body' )->item( 0 )->textContent ) );

ck( 'full product names: no "WP Credits", and the Institution Dashboard by its name, on its sign-in card and in the partner level\'s list; the Student Report Card, the Mentor Report Card and the Sponsor Dashboard on their cards',
	array( substr_count( $words, 'WP Credits' ), substr_count( strtolower( $words ), 'institution dashboard' ), substr_count( $words, 'Institution Dashboard' ), substr_count( $words, 'Student Report Card' ), substr_count( $words, 'Mentor Report Card' ), substr_count( $words, 'Sponsor Dashboard' ) ),
	array( 0, 2, 2, 1, 1, 1 ) );

ck( 'nothing says what sponsorship costs or what a sponsor receives: the sign-in card\'s eyebrow and the name of its page, the band\'s eyebrow and its button are the only words of sponsors, and the one sum on the page is a program\'s award',
	array( found( $page, '//body//*[not(*)][contains(translate(., "S", "s"), "sponsor")]' ), preg_match_all( '/\$\s?\d[\d,]*/', $words, $sums ), $sums[0] ),
	array( array( 'Sponsors', 'Sponsor Dashboard', 'Sponsors and schools', 'Sponsor' ), 1, array( '$1,000' ) ) );

echo "\n=== The page's look ===\n";

ck( 'with no figure to show, the figures column is not displayed and the copy takes the whole width',
	array( rules_for( '.wpc-hub-hero__cols:not( :has( .wpc-figure ) ) .wpc-hub-hero__figures' ), rules_for( '.wpc-hub-hero__cols:not( :has( .wpc-figure ) ) .wpc-hub-hero__copy' ) ),
	array( array( 'display: none;' ), array( 'flex-basis: 100% !important;' ) ) );

// In the Site Editor each figure is not one element but three: the block's wrapper (which the editor
// gives its own class, block-editor-block-list__block, beside the generated wp-block-wpcredits-program-stat),
// the preview's own div, and the server's figure. The column's block gap then sits on the wrapper, and
// `.wpc-figure + .wpc-figure` finds no neighbor. The editor's shape is built here from the server's own
// figures; the rules' selectors are read as XPath, once on it and once on the front end's page.
$class_of        = static function ( $name ) { return 'contains(concat(" ", normalize-space(@class), " "), " ' . $name . ' ")'; };
$editor_wrapper  = '*[' . $class_of( 'block-editor-block-list__block' ) . '][' . $class_of( 'wp-block-wpcredits-program-stat' ) . ']';
$editor_margin   = '//*[' . $class_of( 'wpc-hub-hero__figures' ) . ']/' . $editor_wrapper;
$editor_hairline = $editor_margin . '/following-sibling::' . $editor_wrapper . '//*[' . $class_of( 'wpc-figure' ) . ']';
$in_the_editor   = dom_of( '<div class="wp-block-column wpc-hub-hero__figures">' . implode( '', array_map( static function ( $figure ) { return '<div class="wp-block wp-block-wpcredits-program-stat block-editor-block-list__block"><div>' . $figure . '</div></div>'; }, outcome_blocks() ) ) . '</div>' );

ck( 'in the Site Editor, where each figure is the block\'s wrapper, the preview\'s div and then the server\'s figure, the wrapper\'s margin is reset and the hairline is drawn on the figure inside the second and later wrappers: written on the editor\'s own wrapper class, so they match the four wrappers and the two later figures there, and nothing on the front end, whose figures carry no such class and hold no figure inside',
	array(
		rules_for( '.wpc-hub-hero__figures > .block-editor-block-list__block.wp-block-wpcredits-program-stat' ),
		rules_for( '.wpc-hub-hero__figures > .block-editor-block-list__block.wp-block-wpcredits-program-stat ~ .block-editor-block-list__block.wp-block-wpcredits-program-stat .wpc-figure' ),
		array( count( $in_the_editor->query( $editor_margin ) ), count( $in_the_editor->query( $editor_hairline ) ) ),
		array( count( $page->query( $editor_margin ) ), count( $page->query( $editor_hairline ) ), count( $page->query( '//*[contains(@class,"block-editor-block-list__block")]' ) ) ),
	),
	array( array( 'margin-block: 0;' ), array( 'border-top: 1px solid var( --wpc-line-card );' ), array( 4, 2 ), array( 0, 0, 0 ) ) );

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

ck( 'the hub\'s widths: the phone gutter up to 640px, the sign-in cards\' second column from 640px, the hero panel\'s narrower sides up to 1099px, and one breakpoint at 900px, below it stacked and from it side by side, so at a 900px window nothing is both',
	$breakpoints, array( '@media ( max-width: 1099px )', '@media ( max-width: 640px )', '@media ( max-width: 899px )', '@media ( min-width: 640px )', '@media ( min-width: 900px )' ) );

ck( 'anchored sections land below the sticky header',
	array( rules_for( '.wpc-hub-anchored' ), count( $page->query( '//*[contains(@class,"wpc-hub-anchored")][@id]' ) ) ),
	array( array( 'scroll-margin-top: var( --wpc-landing-offset );' ), 3 ) );

$has_classes = static function ( $one, $other ) use ( $page ) {
	return count( $page->query( '//div[contains(concat(" ", @class, " "), " ' . $one . ' ")][contains(concat(" ", @class, " "), " ' . $other . ' ")]' ) );
};

// The editor prints `:root :where( .is-layout-flex ) { gap: 20px }` for every flex layout: one class
// deep, so a one-class rule here ties with it, and loses where the sheet prints first.
ck( 'the hero\'s columns, the doors\' grid and the band\'s grid keep their gaps over the editor\'s 20px flex gap: written two classes deep, on elements that print both classes, and no one-class rule for them is left',
	array(
		array_values( array_filter( array_map( static function ( $rule ) { return preg_match( '/(?:^| )(gap: [^;]+;)/', $rule[1], $gap ) ? $gap[1] : ''; }, rules_placed( '.wp-block-columns.wpc-hub-hero__cols' ) ) ) ),
		rules_for( '.wp-block-columns.wpc-hub-doors__grid' ),
		rules_for( '.wp-block-columns.wpc-hub-cta__grid' ),
		array( rules_for( '.wpc-hub-hero__cols' ), rules_for( '.wpc-hub-doors__grid' ), rules_for( '.wpc-hub-cta__grid' ) ),
		array( $has_classes( 'wp-block-columns', 'wpc-hub-hero__cols' ), $has_classes( 'wp-block-columns', 'wpc-hub-doors__grid' ), $has_classes( 'wp-block-columns', 'wpc-hub-cta__grid' ) ),
	),
	array( array( 'gap: 44px;', 'gap: 28px;' ), array( 'gap: 16px;' ), array( 'gap: 16px;' ), array( array(), array(), array() ), array( 1, 1, 1 ) ) );

// The display size is a preset class whose size is `!important`, so the theme's 34px at 900px and
// below (style.css, one class, no `!important`) never applies to the hub's title.
ck( 'below 900px the hero\'s title is 34px: !important, because the display size preset\'s is, and two classes deep',
	array_values( preg_grep( '/wpc-hero__title/', rules_in_media( '( max-width: 899px )' ) ) ),
	array( '.wpc-hub-hero .wpc-hero__title { font-size: 34px !important; }' ) );

// The hero is a soft brand-tint panel with its figures on a white card (1.24.16). The pattern stores the
// panel's real padding on the columns themselves, 56px above and below and 48px at each side, so the
// Site Editor's padding control shows what is drawn. An inline style outranks every selector, so the rule
// at full width needs no !important (it says the same, a fallback for a copy of the block that stores
// none), and each narrower padding, which has to beat the stored value on all four sides, is !important
// and written after the one it narrows, at the same weight. Read as they are written, in order.
$hero_panel  = '.wp-block-columns.wpc-hub-hero__cols';
$hero_card   = '.wp-block-column.wpc-hub-hero__figures';
$hero_sheet  = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) );
$panel_rules = rules_placed( $hero_panel );
$hero_px     = static function ( array $placed, $property ) {
	return isset( $placed[0] ) && preg_match( '/(?:^|; )' . $property . ': (\d+)px;/', $placed[0][1], $got ) ? (int) $got[1] : 0;
};
$panel_style = found( $page, '//div[contains(concat(" ", @class, " "), " wpc-hub-hero__cols ")]', 'style' );

ck( 'the hero\'s columns are a soft panel: the brand tint, 12px corners, the 44px gap, 56px above and below and 48px at each side; one rule, on the two classes the columns print and on no one-class rule, the padding with no !important because the pattern stores the same 56px and 48px on the element itself, which outranks it; the panel\'s selector names no figure, so with none to show the panel still draws and the copy runs across it',
	array(
		array_slice( $panel_rules, 0, 1 ),
		rules_for( '.wpc-hub-hero__cols' ),
		$has_classes( 'wp-block-columns', 'wpc-hub-hero__cols' ),
		$panel_style,
	),
	array(
		array( array( '', 'background: var( --wpc-brand-tint ); border-radius: 12px; gap: 44px; padding-bottom: 56px; padding-left: 48px; padding-right: 48px; padding-top: 56px;' ) ),
		array(),
		1,
		array( 'padding-top:56px;padding-right:48px;padding-bottom:56px;padding-left:48px' ),
	) );

ck( 'the panel\'s padding narrows with the screen, one rule for each width: 32px at each side up to 1099px so the copy column keeps more room on a tablet in landscape, then 32px above and below and 24px at each side up to 899px, then 28px and 20px up to 640px; !important on all four sides, because the pattern stores all four, each written after the one it narrows',
	array_slice( $panel_rules, 1 ),
	array(
		array( '@media ( max-width: 1099px )', 'padding-left: 32px !important; padding-right: 32px !important;' ),
		array( '@media ( max-width: 899px )', 'gap: 28px; padding-bottom: 32px !important; padding-left: 24px !important; padding-right: 24px !important; padding-top: 32px !important;' ),
		array( '@media ( max-width: 640px )', 'padding-bottom: 28px !important; padding-left: 20px !important; padding-right: 20px !important; padding-top: 28px !important;' ),
	) );

// The cascade for the one element, evaluated: the sheet's rules that apply at a window width, in order,
// and the style attribute the pattern prints, which beats a normal declaration of any rule and loses to
// an !important one. Only the properties the panel's rules and its attribute name.
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

$panel_padding_at = static function ( $width ) use ( $panel_rules, $panel_style, $declared, $applies_at ) {
	$drawn = array();

	foreach ( $panel_rules as $rule ) {
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

	foreach ( $declared( $panel_style[0] ?? '' ) as $property => $held ) {
		if ( ! isset( $drawn[ $property ] ) || $drawn[ $property ][1] < 1 ) {
			$drawn[ $property ] = array( $held[0], 1 );
		}
	}

	return implode( ' ', array_map( static function ( $side ) use ( $drawn ) { return isset( $drawn[ 'padding-' . $side ] ) ? $drawn[ 'padding-' . $side ][0] : 'none'; }, array( 'top', 'right', 'bottom', 'left' ) ) );
};

ck( 'the panel is drawn with the padding the design gives it at each window width, the pattern\'s style attribute and the sheet\'s rules together (above, right, below, left): 56px and 48px from 1100px, 56px and 32px from 900px to 1099px, 32px and 24px from 641px to 899px, 28px and 20px at 640px and under',
	array_map( $panel_padding_at, array_combine( array( 1440, 1100, 1099, 1050, 950, 900, 899, 641, 640, 375 ), array( 1440, 1100, 1099, 1050, 950, 900, 899, 641, 640, 375 ) ) ),
	array(
		1440 => '56px 48px 56px 48px',
		1100 => '56px 48px 56px 48px',
		1099 => '56px 32px 56px 32px',
		1050 => '56px 32px 56px 32px',
		950  => '56px 32px 56px 32px',
		900  => '56px 32px 56px 32px',
		899  => '32px 24px 32px 24px',
		641  => '32px 24px 32px 24px',
		640  => '28px 20px 28px 20px',
		375  => '28px 20px 28px 20px',
	) );

// The Columns block prints, for the padding it stores, the same four declarations in core's order (the
// editor's save writes top, right, bottom, left), so the block validates and the padding control of the
// Site Editor holds what the pattern draws.
preg_match( '/<!-- wp:columns (\{.*?\}) -->\s*<div class="([^"]*)" style="([^"]*)">/s', $hero, $columns_block );

$stored_padding = isset( $columns_block[1] ) ? ( json_decode( $columns_block[1], true )['style']['spacing']['padding'] ?? array() ) : array();

ck( 'the Site Editor\'s padding control shows what is drawn: the columns block stores the panel\'s four paddings, 56px above and below and 48px at each side, and prints the same four in its style attribute in core\'s order, top, right, bottom, left, so the block comment and the markup agree',
	array(
		$stored_padding,
		implode( ';', array_map( static function ( $side ) use ( $stored_padding ) { return 'padding-' . $side . ':' . $stored_padding[ $side ]; }, array_values( array_intersect( array( 'top', 'right', 'bottom', 'left' ), array_keys( $stored_padding ) ) ) ) ),
		$columns_block[3] ?? '',
		$columns_block[2] ?? '',
	),
	array(
		array( 'top' => '56px', 'bottom' => '56px', 'left' => '48px', 'right' => '48px' ),
		'padding-top:56px;padding-right:48px;padding-bottom:56px;padding-left:48px',
		'padding-top:56px;padding-right:48px;padding-bottom:56px;padding-left:48px',
		'wp-block-columns wpc-hub-hero__cols wpc-hub-stack',
	) );

ck( 'the figures column is a white card on the panel: the surface color, the theme\'s card radius, 6px above, 24px at each side and 18px below, 20px at each side up to 640px, the theme\'s card shadow (0 1px 3px at 6% black) with a soft glow of 10% brand blue 10px down and 28px wide; on two classes, the one the column prints beside its own, and none of the 1.24.15 one-class rule',
	array(
		rules_placed( $hero_card ),
		$theme_json['settings']['custom']['shadow']['card'],
		rules_placed( '.wpc-hub-hero__figures' ),
		$has_classes( 'wp-block-column', 'wpc-hub-hero__figures' ),
	),
	array(
		array(
			array( '', 'background: var( --wpc-surface ); border-radius: var( --wpc-radius-card ); box-shadow: var( --wpc-shadow-card ), 0 10px 28px color-mix( in srgb, var( --wpc-brand ) 10%, transparent ); padding: 6px 24px 18px;' ),
			array( '@media ( max-width: 640px )', 'padding-left: 20px; padding-right: 20px;' ),
		),
		'0 1px 3px rgba(0, 0, 0, 0.06)',
		array(),
		1,
	) );

preg_match_all( '/([^{}]*wpc-hub-hero__figures[^{}]*)\{([^{}]*(?<![\w-])border(?!-radius)[\w-]*\s*:[^{}]*)\}/', $hero_sheet, $hero_borders, PREG_SET_ORDER );
preg_match_all( '/([^{}]*wpc-hub-hero[^{}]*)\{([^{}]*)\}/', $hero_sheet, $hero_rules, PREG_SET_ORDER );

ck( 'no 1px ink rule is drawn above the figures any more, in any of the hero\'s rules: the card replaces it, so the card has no top border, only its own 6px of padding above the first figure; the only border rules that name the figures column are the forced-colors edge and the editor\'s hairline between figures',
	array(
		count( array_filter( $hero_rules, static function ( $rule ) { return (bool) preg_match( '/border-top:\s*1px solid var\( --wpc-ink \)/', $rule[2] ); } ) ),
		array_map( static function ( $rule ) { return preg_replace( '/\s+/', ' ', trim( $rule[1] ) . ' { ' . trim( $rule[2] ) . ' }' ); }, $hero_borders ),
	),
	array(
		0,
		array(
			'.wp-block-columns.wpc-hub-hero__cols, .wp-block-column.wpc-hub-hero__figures { border: 1px solid CanvasText; }',
			'.wpc-hub-hero__figures > .block-editor-block-list__block.wp-block-wpcredits-program-stat ~ .block-editor-block-list__block.wp-block-wpcredits-program-stat .wpc-figure { border-top: 1px solid var( --wpc-line-card ); }',
		),
	) );

// The panel's bottom edge is 96px above the sign-in section's content, as every object edge on the page is
// 96px from the next section's content: the hero group's 48px under the panel, the sign-in section's 48px
// above its eyebrow, and the main's block gap taken away between the two. The group carries no padding of
// its own in the pattern, which would outrank these. Above the panel it has 24px, so the panel does not sit
// against the header.
$hero_group = rules_placed( '.wp-block-group.wpc-hub-hero' );
$sign_group = rules_placed( '.wp-block-wpcredits-sign-in.wpc-hub-sign-in' );

ck( 'the hero group is padded 24px above, so the panel does not sit against the header, and 48px below, so the panel\'s bottom edge is 96px above the sign-in section\'s eyebrow with the sign-in section\'s 48px and no block gap between; on the two classes the group prints, and no padding of its own in the pattern',
	array(
		$hero_group,
		$hero_px( $hero_group, 'padding-bottom' ) + $hero_px( $sign_group, 'padding-top' ),
		rules_for( '.wpc-main.wpc-hub > *' ),
		found( $page, '/html/body/div[contains(concat(" ", @class, " "), " wpc-hub-hero ")][contains(concat(" ", @class, " "), " wp-block-group ")]', 'style' ),
	),
	array( array( array( '', 'padding-bottom: 48px; padding-top: 24px;' ) ), 96, array( 'margin-block-start: 0;' ), array( '' ) ) );

ck( 'in forced colors, which drop backgrounds and shadows, the panel and the figures card each keep a 1px edge in the text color',
	rules_placed( $hero_panel . ', ' . $hero_card ),
	array( array( '@media ( forced-colors: active )', 'border: 1px solid CanvasText;' ) ) );

// A selector of classes joined (`.a.b`) read as XPath: the elements that print every class.
$hero_match = static function ( DOMXPath $xpath, $selector ) use ( $class_of ) {
	return $xpath->query( '//*[' . implode( '][', array_map( $class_of, explode( '.', ltrim( $selector, '.' ) ) ) ) . ']' );
};

ck( 'the card and the panel are drawn where the markup puts them: the card\'s two classes match the figures column once on the front end, holding the three figures and their source, and once in the Site Editor, where the four figure wrappers sit directly in it, so the editor\'s figure rules above work inside the white card; the panel\'s two match the columns once',
	array(
		(bool) rules_placed( $hero_card ),
		$hero_match( $page, $hero_card )->length,
		count( $page->query( '//*[' . $class_of( 'wp-block-column' ) . '][' . $class_of( 'wpc-hub-hero__figures' ) . ']/*[' . $class_of( 'wp-block-wpcredits-program-stat' ) . ']' ) ),
		$hero_match( $in_the_editor, $hero_card )->length,
		count( $in_the_editor->query( '//*[' . $class_of( 'wp-block-column' ) . '][' . $class_of( 'wpc-hub-hero__figures' ) . ']/' . $editor_wrapper ) ),
		$hero_match( $page, $hero_panel )->length,
	),
	array( true, 1, 4, 1, 4, 1 ) );

$panel_tint = '';

foreach ( $panel_rules as $panel_rule ) {
	if ( '' === $panel_rule[0] && preg_match( '/(?:^|; )background: var\( --wpc-([a-z-]+) \);/', $panel_rule[1], $tint_found ) ) {
		$panel_tint = $tint_found[1];
	}
}

// A color of a stylesheet's rule, followed to its hex: the rule's own `color` (or the property given), a
// `var( --wpc-token )`, that token's line in style.css's :root, and the palette or the custom colors of
// theme.json that line names. The last rule of the selector wins, as the sheet's order has it.
$rule_color = static function ( $selector, $sheet, $property = 'color' ) {
	$rules = rules_for( $selector, $sheet );

	if ( ! $rules || ! preg_match( '/(?:^|; )' . $property . ': var\( --wpc-([a-z0-9-]+) \);/', end( $rules ), $token ) ) {
		return '';
	}

	if ( preg_match( '/^\s*--wpc-' . preg_quote( $token[1], '/' ) . ': var\( --wp--preset--color--([a-z0-9-]+) \);/m', theme_file( 'style.css' ), $named ) ) {
		return color_of( $named[1] );
	}

	return preg_match( '/^\s*--wpc-' . preg_quote( $token[1], '/' ) . ': var\( --wp--custom--([a-z0-9-]+(?:--[a-z0-9-]+)*) \);/m', theme_file( 'style.css' ), $named ) ? color_of( str_replace( '--', '.', $named[1] ) ) : '';
};

$legible = static function ( $text, $ground ) {
	return '' !== $text && '' !== $ground && (float) contrast( $text, $ground ) >= 4.5;
};

$tint         = '' !== $panel_tint ? color_of( $panel_tint ) : '';
$outline_link = '.wp-block-button.is-style-outline .wp-block-button__link';
$pill         = '.wpc-pill, p.is-style-pill';

ck( 'the hero\'s text is legible on the panel, whatever color the panel is drawn in, each color read from the rule that gives it: the title\'s from the theme\'s text color, its second line\'s, the lede\'s and the outline button\'s from their rules, against the panel\'s own background, and the eyebrow pill\'s text on the pill\'s own, each at 4.5:1 or more',
	array(
		'the title'       => $legible( color_of( str_replace( 'var:preset|color|', '', $theme_json['styles']['color']['text'] ) ), $tint ),
		'its second line' => $legible( $rule_color( '.wpc-hero__title em', 'style.css' ), $tint ),
		'the lede'        => $legible( $rule_color( '.wpc-hub-lede', 'assets/css/hub.css' ), $tint ),
		'the button'      => $legible( $rule_color( $outline_link, 'style.css' ), $tint ),
		'the pill'        => $legible( $rule_color( $pill, 'style.css' ), $rule_color( $pill, 'style.css', 'background' ) ),
	),
	array( 'the title' => true, 'its second line' => true, 'the lede' => true, 'the button' => true, 'the pill' => true ) );

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

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
