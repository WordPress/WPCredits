<?php
/**
 * Checks for the Partners page (templates/page-partners.html, the partners-* patterns,
 * inc/partners.php, the Partner levels, Partner level and Levels at a glance blocks and
 * assets/css/partners.css), run with `php bin/test-partners.php` on the WordPress of
 * bin/stubs/hub-wp.php. The page presents the WordPress Credits partnership levels in a program
 * lead's words (21 October 2026), in the Education Hub's look (the owner, the same day). Since theme
 * 1.32.1 the levels and their table are blocks Administrators edit in the Site Editor, and the plugin's
 * sponsor application reads the levels of the Partners template in use; the checks on 1.31.11's markup
 * read live's saved copy of the template, bin/fixtures/saved-partners.html, saved from 1.31.11 on
 * 22 October 2026 and read on 23 October 2026.
 *
 * @package WPCredits_Theme
 */

// phpcs:ignoreFile -- a command-line check, not theme code.

require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post = 0 ) { return isset( $GLOBALS['slugs'][ (int) $post ] ) ? $GLOBALS['slugs'][ (int) $post ] : ''; }
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) { return isset( $GLOBALS['permalinks'][ (int) $post ] ) ? $GLOBALS['permalinks'][ (int) $post ] : false; }
}

if ( ! function_exists( 'get_stylesheet' ) ) {
	function get_stylesheet() { return 'wpcredits-theme'; }
}

// Core's get_post(), for the synced patterns (wp_block posts) a template refers to: `$GLOBALS['synced']`,
// post ID => the post, as synced() puts it there. Like core's, a post with no ID is the global post, none here.
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post = null ) { return isset( $GLOBALS['synced'][ (int) $post ] ) ? $GLOBALS['synced'][ (int) $post ] : null; }
}

require_once WPCREDITS_THEME_DIR . 'inc/partners.php';

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

function dom_of( $html ) {
	$document = new DOMDocument();

	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();

	return new DOMXPath( $document );
}

/**
 * A theme file's text, or '' for a file that is not there.
 *
 * @param string $path The file's path below the theme's root.
 * @return string
 */
function theme_file( $path ) {
	return is_file( WPCREDITS_THEME_DIR . $path ) ? (string) file_get_contents( WPCREDITS_THEME_DIR . $path ) : '';
}

/**
 * A theme function's answer, or null while the theme has no such function, so a check of one not
 * written yet fails rather than stopping the run.
 *
 * @param string $name    The function.
 * @param mixed  ...$args Its arguments.
 * @return mixed
 */
function theme_call( $name, ...$args ) {
	return function_exists( $name ) ? $name( ...$args ) : null;
}

function found( DOMXPath $xpath, $query, $attribute = '' ) {
	$said = array();

	foreach ( $xpath->query( $query ) as $node ) {
		$said[] = '' === $attribute ? trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ) : (string) $node->getAttribute( $attribute );
	}

	return $said;
}

/** The plugin's sponsorship application, Partners page and launch switch, as the plugin answers them: an address, or '' while a page is not published. */
class WPCPM_Sponsor_Application {
	public static $url = 'https://site.example/sponsor-application/';
	public static function page_url() { return self::$url; }
}

class WPCPM_Sponsors_Page {
	public static $url = 'https://site.example/partners/';
	public static function page_url() { return self::$url; }
}

class WPCPM_Sponsor_Tiers {
	public static $live = false;
	public static function live() { return self::$live; }
}

$template = (string) file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-partners.html' );

/**
 * The Partners template with each pattern's content in place, as the Site Editor saves a copy of it,
 * the levels pattern's in place of its own when one is given.
 *
 * @param string|null $levels The levels section's block markup, or null for the pattern's.
 * @return string Block markup.
 */
function partners_template( $levels = null ) {
	$template = (string) file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-partners.html' );

	return preg_replace_callback(
		'#<!-- wp:pattern \{"slug":"wpcredits/([a-z-]+)"\} /-->#',
		static function ( $part ) use ( $levels ) {
			return null !== $levels && 'partners-levels' === $part[1] ? $levels : wpcredits_test_pattern( $part[1] . '.php' );
		},
		$template
	);
}

/**
 * The Partners page as WordPress draws it from the template: each pattern's content in place and every
 * block rendered, the theme's own by their render.php and core's as their saved markup (the template
 * parts and the page's own content, which core draws, are left out).
 *
 * @param string|null $template The template's block markup, or null for the theme's file.
 * @return string
 */
function partners_page( $template = null ) {
	return do_blocks( null === $template ? partners_template() : $template );
}

echo "=== The template ===\n";

preg_match_all( '#<!-- wp:(pattern \{"slug":"wpcredits/([a-z-]+)"\}|post-content|template-part \{"slug":"([a-z-]+)")#', $template, $parts, PREG_SET_ORDER );

ck( 'the page is drawn by its slug\'s template, the hub\'s: the WordPress.org bar, the header, then a hub main of five patterns with the page\'s own content, the listing, between the founding partners and the close, then the footer',
	array_map( static function ( $part ) { return isset( $part[3] ) && '' !== $part[3] ? 'part:' . $part[3] : ( isset( $part[2] ) && '' !== $part[2] ? $part[2] : 'post-content' ); }, $parts ),
	array( 'part:wporg-header', 'part:header', 'partners-hero', 'partners-funds', 'partners-levels', 'partners-commons', 'post-content', 'partners-apply', 'part:wporg-footer' ) );

ck( 'the main is a hub main, so the hub\'s type, heads, rhythm and buttons are the page\'s; the listing sits in a section of its own, anchored #partners',
	array( substr_count( $template, '<main class="wp-block-group wpc-main wpc-hub wpc-partners">' ), substr_count( $template, '<div id="partners" class="wp-block-group alignwide wpc-partners-listing wpc-hub-anchored" style="padding-top:48px;padding-bottom:48px"><!-- wp:post-content' ) ),
	array( 1, 1 ) );

$page = partners_page();
$px   = dom_of( $page );

echo "\n=== The words ===\n";

ck( 'the hero: the eyebrow, the one h1, the lede, the two ways to start (the application read from the plugin, the education team\'s address) and the two figures with their source',
	array(
		found( $px, '//div[contains(@class,"wpc-partners-hero__copy")]/p[@class="is-style-eyebrow"]' ),
		found( $px, '//h1' ),
		found( $px, '//div[contains(@class,"wpc-partners-hero__actions")]//a' ),
		found( $px, '//div[contains(@class,"wpc-partners-hero__actions")]//a', 'href' ),
		found( $px, '//dl[@class="wpc-partners-figures"]/div/dt' ),
		found( $px, '//dl[@class="wpc-partners-figures"]/div/dd' ),
		found( $px, '//p[@class="wpc-partners-figures__source"]/a', 'href' ),
	),
	array(
		array( 'WordPress Credits Program' ),
		array( 'Fund real student contributions to WordPress' ),
		array( 'Apply to become a partner', 'Talk to the education team' ),
		array( 'https://site.example/sponsor-application/', 'mailto:education@wordpressfoundation.org' ),
		array( '500+', '17' ),
		array( 'students currently contributing through the program', 'countries with participating institutions, from 8 institutions at the start of the year' ),
		array( 'https://make.wordpress.org/community/2026/08/13/wpcredits-dashboard/' ),
	) );

ck( 'the sections\' headings in the page\'s order',
	found( $px, '//h2 | //h3[not(ancestor::li)]' ),
	array( 'What your support funds', '2027 is a pilot year', 'Partnership levels', 'Levels at a glance', 'Products donated for student use', 'How student work is used', 'Automattic and Weglot', 'Become a partner' ) );

ck( 'what support funds: three cards',
	found( $px, '//ul[@class="wpc-partners-funds__grid"]/li/h3' ),
	array( 'Curriculum and mentorship', 'Translation and accessibility', 'Institutional onboarding' ) );

ck( 'the five levels, each its name, its amount, the line that leads its list and the three things it adds; the Partner level a program of its own, with its tag',
	array(
		found( $px, '//li[contains(@class,"wpc-partners-level")]/h3' ),
		found( $px, '//li[contains(@class,"wpc-partners-level")]/p[@class="wpc-partners-level__amount"]' ),
		array_map( static function ( $lead ) { return substr( $lead, 0, 28 ); }, found( $px, '//li[contains(@class,"wpc-partners-level")]/p[@class="wpc-partners-level__lead"]' ) ),
		array_map( 'count', array_map( static function ( $level ) use ( $px ) { return iterator_to_array( $px->query( './/ul[@class="wpc-partners-level__items"]/li', $level ) ); }, iterator_to_array( $px->query( '//li[contains(@class,"wpc-partners-level ")] | //li[@class="wpc-partners-level"]' ) ) ) ),
		found( $px, '//li[contains(@class,"wpc-partners-level--program")]/h3/span[@class="wpc-partners-tag"]' ),
	),
	array(
		array( 'Friend', 'Supporter', 'Backer', 'Champion', 'Partner Funds a named program' ),
		array( 'In-kind, through Five for the Future', '$5,000 a year', '$10,000 a year', '$15,000 a year', 'From $30,000 a year' ),
		array( 'For companies sponsoring men', 'Everything in Friend, plus:', 'Everything in Supporter, plu', 'Everything in Backer, plus:', 'Everything in Champion, plus' ),
		array( 3, 3, 3, 3, 3 ),
		array( 'Funds a named program' ),
	) );

$columns = array();

foreach ( range( 2, 6 ) as $column ) {
	$columns[] = count( $px->query( '//table[@class="wpc-partners-glance__table"]/tbody/tr/td[' . ( $column - 1 ) . '][@class="wpc-partners-glance__yes"]' ) );
}

ck( 'the table: a region named by its heading and a tab stop, a head of the five levels with their amounts, fifteen rows, and Yes from each row\'s first level up, so each level has three more than the one below: 3, 6, 9, 12 and 15',
	array(
		found( $px, '//div[@class="wpc-partners-glance__scroll"]', 'role' ),
		found( $px, '//div[@class="wpc-partners-glance__scroll"]', 'tabindex' ),
		found( $px, '//div[@class="wpc-partners-glance__scroll"]', 'aria-labelledby' ) === found( $px, '//h2[.="Levels at a glance"]', 'id' ),
		found( $px, '//table/thead/tr/th' ),
		count( $px->query( '//table/tbody/tr' ) ),
		$columns,
		count( $px->query( '//table/tbody/tr/td' ) ),
	),
	array(
		array( 'region' ),
		array( '0' ),
		true,
		array( 'Recognition', 'Friend In-kind', 'Supporter $5,000', 'Backer $10,000', 'Champion $15,000', 'Partner From $30,000' ),
		15,
		array( 3, 6, 9, 12, 15 ),
		75,
	) );

ck( 'the close: the application read from the plugin and the education team\'s address, then the Slack line and the tax note',
	array(
		found( $px, '//div[@id="apply"]//a', 'href' ),
		found( $px, '//div[@id="apply"]//a' ),
		count( $px->query( '//p[@class="wpc-partners-apply__more"]' ) ),
		count( $px->query( '//p[@class="wpc-partners-note"]' ) ),
	),
	array(
		array( 'https://site.example/sponsor-application/', 'mailto:education@wordpressfoundation.org' ),
		array( 'Partner application form', 'education@wordpressfoundation.org' ),
		1,
		1,
	) );

// The levels are read once a request: read again for the marked strings, and again after them.
theme_call( 'wpcredits_partner_levels_reset' );
$GLOBALS['marked'] = true;
$marked_px         = dom_of( partners_page() );
$GLOBALS['marked'] = false;
theme_call( 'wpcredits_partner_levels_reset' );

$unmarked = array();

foreach ( $marked_px->query( '//body//text()[normalize-space()]' ) as $node ) {
	$text = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );

	// A sentence with a link in it is one string whose words the link splits: its first part opens the
	// brackets and its last closes them.
	if ( ! preg_match( '/^\[.*\]$|^\[|\]$/u', $text ) && ! in_array( $text, array( '→', 'education@wordpressfoundation.org' ), true ) && 0 !== strpos( $text, '<!--' ) ) {
		$unmarked[] = $text;
	}
}

ck( 'every word on the page is a translated string, the education team\'s address and the arrows apart',
	array_values( array_unique( $unmarked ) ), array() );

$words = $px->query( '//body' )->item( 0 )->textContent;

ck( 'US English and full product names: no "recognised", "organisation" or "acknowledgement letter" spelling of the source, and no "WP Credits"',
	array( preg_match( '/recognis|organis|WP Credits/u', $words ), substr_count( $words, 'acknowledgment letter' ) ),
	array( 0, 1 ) );

$sources = '';

foreach ( glob( WPCREDITS_THEME_DIR . 'patterns/partners-*.php' ) as $file ) {
	$sources .= file_get_contents( $file );
}

foreach ( glob( WPCREDITS_THEME_DIR . 'blocks/partner-*/*' ) as $file ) {
	$sources .= file_get_contents( $file );
}

$sources .= file_get_contents( WPCREDITS_THEME_DIR . 'inc/partners.php' ) . file_get_contents( WPCREDITS_THEME_DIR . 'assets/css/partners.css' ) . $template;

ck( 'no em or en dash in the page\'s files, its blocks\' among them', preg_match( '/[\x{2013}\x{2014}]/u', $sources ), 0 );

echo "\n=== The stylesheets ===\n";

$GLOBALS['styles'] = array();
wpcredits_partners_register_style();

$GLOBALS['request'] = array( 'singular' => 'page', 'id' => 41, 'templates' => array() );
$GLOBALS['slugs']   = array( 41 => 'partners', 42 => 'about' );
$GLOBALS['enqueued'] = array();
wpcredits_partners_assets();
$on_partners = $GLOBALS['enqueued'];

$GLOBALS['request']['id'] = 42;
$GLOBALS['enqueued']      = array();
wpcredits_partners_assets();
$on_other = $GLOBALS['enqueued'];

ck( 'the Partners page loads the hub\'s stylesheet and its own, after the hub\'s; another page neither',
	array( $GLOBALS['styles']['wpcredits-partners'][1], $on_partners, $on_other ),
	array( array( 'wpcredits-hub' ), array( 'wpcredits-hub', 'wpcredits-partners' ), array() ) );

echo "\n=== The menu's item ===\n";

$anchor = array( 'blockName' => 'core/navigation', 'attrs' => array() );

/**
 * The hooked item for a request, as the navigation block asks for it.
 *
 * @return array The item's attributes.
 */
function menu_item_attrs() {
	global $anchor;

	$item = wpcredits_partners_menu_item_block( array( 'blockName' => 'core/navigation-link', 'attrs' => array() ), 'core/navigation-link', 'last_child', $anchor );

	return $item['attrs'];
}

/**
 * Whether core's Navigation Link block marks an item current, the test of
 * `render_block_core_navigation_link()` in WordPress 7.1 (wp-includes/blocks/navigation-link.php): the
 * item has a page's ID, the page being viewed is that page, and the item's kind (a custom link's is
 * `custom`, a page's `post-type`) is a property the viewed object has. It matches no address: only a
 * post type archive's link is matched by its URL.
 *
 * @param array $attrs  The item's attributes.
 * @param int   $viewed The viewed object's ID.
 * @param array $object The viewed object's properties.
 * @return bool
 */
function core_marks_current( array $attrs, $viewed, array $object ) {
	$kind = empty( $attrs['kind'] ) ? 'post_type' : str_replace( '-', '_', $attrs['kind'] );

	return ! empty( $attrs['id'] ) && $viewed === (int) $attrs['id'] && ! empty( $object[ $kind ] );
}

WPCPM_Sponsor_Tiers::$live = false;
$off = wpcredits_partners_menu_item( array(), 'last_child', 'core/navigation' );

WPCPM_Sponsor_Tiers::$live = true;
$on        = wpcredits_partners_menu_item( array(), 'last_child', 'core/navigation' );
$elsewhere = array( wpcredits_partners_menu_item( array(), 'after', 'core/navigation' ), wpcredits_partners_menu_item( array(), 'last_child', 'core/group' ) );
$item      = menu_item_attrs();
$left      = wpcredits_partners_menu_item_block( null, 'core/navigation-link', 'last_child', $anchor );
$other     = wpcredits_partners_menu_item_block( array( 'blockName' => 'core/navigation-link', 'attrs' => array( 'label' => 'Kept' ) ), 'core/navigation-link', 'first_child', $anchor );

ck( 'while the levels are not public the menu gains nothing; once they are, its last item is a link, "Become a partner", to the Partners page, and nowhere else is anything hooked',
	array( $off, $on, $elsewhere, $item, $left, $other['attrs'] ),
	array(
		array(),
		array( 'core/navigation-link' ),
		array( array(), array() ),
		array( 'label' => 'Become a partner', 'url' => 'https://site.example/partners/', 'kind' => 'custom', 'className' => 'wpc-nav__partner' ),
		null,
		array( 'label' => 'Kept' ),
	) );

// The Partners page, then the application, then the handbook: each falls back to the next.
WPCPM_Sponsors_Page::$url = '';
$without_page = menu_item_attrs()['url'];

WPCPM_Sponsor_Application::$url = '';
$without_either = menu_item_attrs()['url'];

WPCPM_Sponsors_Page::$url = 'https://site.example/partners/';
$without_application = menu_item_attrs()['url'];

WPCPM_Sponsor_Application::$url = 'https://site.example/sponsor-application/';

ck( 'the item leads to the Partners page; while the plugin has no published Partners page, to the sponsorship application; with neither, to the WordPress Credits handbook',
	array( $item['url'], $without_page, $without_either, $without_application ),
	array(
		'https://site.example/partners/',
		'https://site.example/sponsor-application/',
		WPCREDITS_HUB_HANDBOOK,
		'https://site.example/partners/',
	) );

// The navigation block marks a link current by a page's ID, not by an address, so the item says which page it is
// on the page it leads to, and nowhere else.
$GLOBALS['permalinks'] = array( 41 => 'https://site.example/partners/', 42 => 'https://site.example/about/' );

$GLOBALS['request'] = array( 'singular' => 'page', 'id' => 41, 'templates' => array() );
$on_partners        = menu_item_attrs();

$GLOBALS['request']['id'] = 42;
$on_other_page            = menu_item_attrs();

$GLOBALS['request'] = array( 'singular' => 'post', 'id' => 41, 'templates' => array() );
$on_a_post          = menu_item_attrs();

$GLOBALS['request'] = array( 'singular' => '', 'id' => 0, 'templates' => array() );
$on_archive         = menu_item_attrs();

$GLOBALS['request'] = array( 'singular' => 'page', 'id' => 41, 'templates' => array() );
WPCPM_Sponsors_Page::$url = '';
$partners_unpublished     = menu_item_attrs();
WPCPM_Sponsors_Page::$url = 'https://site.example/partners/';

ck( 'on the Partners page the item carries the page\'s ID, as a link to a page does, so the navigation block marks it current; on another page, a post or an archive, it is the custom link it was',
	array(
		array( $on_partners['kind'], $on_partners['type'] ?? null, $on_partners['id'] ?? null, $on_partners['url'], $on_partners['label'], $on_partners['className'] ),
		array_map( 'array_keys', array( $on_other_page, $on_a_post, $on_archive, $partners_unpublished ) ),
	),
	array(
		array( 'post-type', 'page', 41, 'https://site.example/partners/', 'Become a partner', 'wpc-nav__partner' ),
		array_fill( 0, 4, array( 'label', 'url', 'kind', 'className' ) ),
	) );

ck( 'core marks the item current on the Partners page and on no other page, as it does a link to that page; the custom link it was before is never marked',
	array(
		core_marks_current( $on_partners, 41, array( 'post_type' => 'page' ) ),
		core_marks_current( $on_other_page, 42, array( 'post_type' => 'page' ) ),
		core_marks_current( $on_partners, 42, array( 'post_type' => 'page' ) ),
		core_marks_current( $item, 41, array( 'post_type' => 'page' ) ),
	),
	array( true, false, false, false ) );

$GLOBALS['request'] = array( 'singular' => '', 'id' => 0, 'templates' => array() );
WPCPM_Sponsor_Tiers::$live = false;

echo "\n=== The levels ===\n";

$defaults = (array) theme_call( 'wpcredits_partner_levels_defaults' );
$levels   = (array) theme_call( 'wpcredits_partner_levels' );

ck( 'the five levels the theme ships are one list in a function, in the program lead\'s order: each its name, amount, short amount, lead and three items, and the Partner level also its tag',
	array(
		array_column( $defaults, 'name' ),
		array_map( 'array_keys', $defaults ),
		array_map( static function ( $level ) { return count( $level['items'] ); }, $defaults ),
		array_column( $defaults, 'tag' ),
	),
	array(
		array( 'Friend', 'Supporter', 'Backer', 'Champion', 'Partner' ),
		array_merge( array_fill( 0, 4, array( 'name', 'amount', 'short', 'lead', 'items' ) ), array( array( 'name', 'amount', 'short', 'tag', 'lead', 'items' ) ) ),
		array( 3, 3, 3, 3, 3 ),
		array( 'Funds a named program' ),
	) );

ck( 'with no copy of the Partners template saved, the theme\'s file is the template in use, and its levels are those five',
	$levels === $defaults && 5 === count( $levels ), true );

ck( 'the page draws from them: in the cards each level\'s name (and the tag beside the Partner\'s), amount, lead and items, and in the table\'s head its name and short amount',
	array(
		found( $px, '//li[contains(@class,"wpc-partners-level")]/h3' ),
		found( $px, '//li[contains(@class,"wpc-partners-level")]/p[@class="wpc-partners-level__amount"]' ),
		found( $px, '//li[contains(@class,"wpc-partners-level")]/p[@class="wpc-partners-level__lead"]' ),
		found( $px, '//ul[@class="wpc-partners-level__items"]/li' ),
		found( $px, '//table/thead/tr/th' ),
	),
	array(
		array_map( static function ( $level ) { return $level['name'] . ( isset( $level['tag'] ) ? ' ' . $level['tag'] : '' ); }, $defaults ),
		array_column( $defaults, 'amount' ),
		array_column( $defaults, 'lead' ),
		array_merge( array(), ...array_column( $defaults, 'items' ) ),
		array_merge( array( 'Recognition' ), array_map( static function ( $level ) { return $level['name'] . ' ' . $level['short']; }, $defaults ) ),
	) );

// The plugin asks while the levels are public; the answer does not depend on it.
WPCPM_Sponsor_Tiers::$live = false;
$closed = (array) apply_filters( 'wpcpm_partner_levels', array( 'a left-over level' ) );

WPCPM_Sponsor_Tiers::$live = true;
$open = (array) apply_filters( 'wpcpm_partner_levels', array() );

WPCPM_Sponsor_Tiers::$live = false;

ck( 'the plugin\'s form is given the levels through its filter: five, in order, each its name, amount, lead and items, and the Partner\'s also its tag, never the table\'s short amount; they replace what the filter received',
	array(
		array_column( $closed, 'name' ),
		array_map( 'array_keys', array_filter( $closed, 'is_array' ) ),
		array_column( $closed, 'tag' ),
		in_array( 'a left-over level', $closed, true ),
	),
	array(
		array( 'Friend', 'Supporter', 'Backer', 'Champion', 'Partner' ),
		array_merge( array_fill( 0, 4, array( 'name', 'amount', 'lead', 'items' ) ), array( array( 'name', 'amount', 'lead', 'items', 'tag' ) ) ),
		array( 'Funds a named program' ),
		false,
	) );

ck( 'the filter\'s words are the page\'s: the same names, amounts, leads, items and tag, each item a string in a list; and the answer is the same while the levels are public or not',
	array(
		array_column( $open, 'name' ) === array_column( $defaults, 'name' ),
		array_column( $open, 'amount' ) === array_column( $defaults, 'amount' ),
		array_column( $open, 'lead' ) === array_column( $defaults, 'lead' ),
		array_column( $open, 'items' ) === array_column( $defaults, 'items' ),
		array_column( $open, 'tag' ) === array_column( $defaults, 'tag' ),
		array_unique( array_map( static function ( $level ) { return array_keys( $level['items'] ) === array( 0, 1, 2 ) && array() === array_filter( $level['items'], static function ( $item ) { return ! is_string( $item ); } ); }, $open ) ),
		$open === $closed,
		5 === count( $open ),
	),
	array( true, true, true, true, true, array( true ), true, true ) );

ck( 'the theme hooks the filter once, at the default priority, with the one argument the plugin passes',
	array_map(
		static function ( $hook ) { return array( $hook[0], $hook[1], $hook[2] ); },
		isset( $GLOBALS['hooks']['wpcpm_partner_levels'] ) ? $GLOBALS['hooks']['wpcpm_partner_levels'] : array()
	),
	array( array( 'wpcredits_partners_levels_for_form', 10, 1 ) ) );

ck( 'the theme loads the Partners page\'s code',
	substr_count( (string) file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' ), "require_once get_theme_file_path( 'inc/partners.php' );" ), 1 );

echo "\n=== The levels and the table as blocks (theme 1.32.1) ===\n";

// The design's D5, as ruling R1 has it: the cards are the Partner levels block, holding a Partner level
// block for each level, in the #levels section; the table is the Levels at a glance block, in the
// #glance section, drawn from the levels of the Partners template in use, so the two cannot disagree.
$attributes_of = static function ( $json ) {
	return array_map( static function ( $attribute ) { return $attribute['type'] . ' ' . json_encode( $attribute['default'] ?? null ); }, $json['attributes'] ?? array() );
};
$levels_json   = (array) json_decode( theme_file( 'blocks/partner-levels/block.json' ), true );
$level_json    = (array) json_decode( theme_file( 'blocks/partner-level/block.json' ), true );
$glance_json   = (array) json_decode( theme_file( 'blocks/partner-glance/block.json' ), true );

ck( 'block.json: wpcredits/partner-levels holds Partner levels and nothing else, once on a page, with no attribute of its own; wpcredits/partner-level sits in it only, with its name, amount, short amount, tag, lead and items; wpcredits/partner-glance with its head word and its two cell words; every text empty by default, so no English is stored where a translation would show; each server-rendered with its editor script and the theme\'s text domain, the two sections with the Partners page\'s stylesheet',
	array(
		array( $levels_json['name'] ?? null, $levels_json['allowedBlocks'] ?? null, $attributes_of( $levels_json ), $levels_json['supports']['multiple'] ?? null, $levels_json['supports']['html'] ?? null, $levels_json['render'] ?? null, $levels_json['editorScript'] ?? null, $levels_json['style'] ?? null, $levels_json['textdomain'] ?? null ),
		array( $level_json['name'] ?? null, $level_json['parent'] ?? null, $attributes_of( $level_json ), $level_json['supports']['html'] ?? null, $level_json['render'] ?? null, $level_json['editorScript'] ?? null, $level_json['textdomain'] ?? null ),
		array( $glance_json['name'] ?? null, $attributes_of( $glance_json ), $glance_json['supports']['html'] ?? null, $glance_json['render'] ?? null, $glance_json['editorScript'] ?? null, $glance_json['style'] ?? null, $glance_json['textdomain'] ?? null ),
	),
	array(
		array( 'wpcredits/partner-levels', array( 'wpcredits/partner-level' ), array(), false, false, 'file:./render.php', 'file:./index.js', 'wpcredits-partners', 'wpcredits-theme' ),
		array( 'wpcredits/partner-level', array( 'wpcredits/partner-levels' ), array( 'name' => 'string ""', 'amount' => 'string ""', 'short' => 'string ""', 'tag' => 'string ""', 'lead' => 'string ""', 'items' => 'array []' ), false, 'file:./render.php', 'file:./index.js', 'wpcredits-theme' ),
		array( 'wpcredits/partner-glance', array( 'label' => 'string ""', 'yes' => 'string ""', 'no' => 'string ""' ), false, 'file:./render.php', 'file:./index.js', 'wpcredits-partners', 'wpcredits-theme' ),
	) );

$asset_of = static function ( $block ) {
	return is_file( WPCREDITS_THEME_DIR . 'blocks/' . $block . '/index.asset.php' ) ? include WPCREDITS_THEME_DIR . 'blocks/' . $block . '/index.asset.php' : null;
};

ck( 'their editor scripts need the editor\'s packages, the table\'s the editor\'s data too, at the theme\'s version; the theme registers the three after the Field of study row, the levels with its inner blocks left to it',
	array(
		$asset_of( 'partner-levels' ),
		$asset_of( 'partner-level' ),
		$asset_of( 'partner-glance' ),
		false !== strpos( theme_file( 'functions.php' ), "register_block_type( get_theme_file_path( 'blocks/fit-row' ) );\n\tregister_block_type( get_theme_file_path( 'blocks/partner-levels' ), array( 'skip_inner_blocks' => true ) );\n\tregister_block_type( get_theme_file_path( 'blocks/partner-level' ) );\n\tregister_block_type( get_theme_file_path( 'blocks/partner-glance' ) );" ),
	),
	array(
		array( 'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element' ), 'version' => '9.9.9' ),
		array( 'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-i18n' ), 'version' => '9.9.9' ),
		array( 'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-i18n', 'wp-data' ), 'version' => '9.9.9' ),
		true,
	) );

$levels_js = theme_file( 'blocks/partner-levels/index.js' );
$level_js  = theme_file( 'blocks/partner-level/index.js' );
$glance_js = theme_file( 'blocks/partner-glance/index.js' );

// A dynamic block saves nothing the editor checks against what a later release would save.
ck( 'in the editor the levels hold Partner levels only and start with one empty level; a level edits its name, tag, amount and lead inline, the lead alone with bold and italic, and its short amount and its items, one to a line, in the block\'s settings; the table is drawn from the editor\'s own Partner levels block; the levels save their levels alone, a level and the table nothing, and none is drawn by the server in the editor',
	array(
		(bool) preg_match( "/allowedBlocks: \[ 'wpcredits\/partner-level' \]/", $levels_js ),
		(bool) preg_match( "/template: \[ \[ 'wpcredits\/partner-level' \] \]/", $levels_js ),
		preg_match_all( '/el\( RichText, \{\s*tagName: \'([a-z0-9]+)\',\s*(?:className: \'[a-z_ -]+\',\s*)?value: attributes\.(name|tag|amount|lead),\s*onChange: [^\n]+\n\s*placeholder: [^\n]+\n\s*allowedFormats: (\[\]|FORMATS),/', $level_js, $level_rich, PREG_SET_ORDER ) ? array_map( static function ( $one ) { return $one[2] . ' ' . $one[1] . ' ' . $one[3]; }, $level_rich ) : array(),
		(bool) preg_match( '/el\( components\.TextControl, \{\s*label: __\( \'[^\']+\', \'wpcredits-theme\' \),\s*value: attributes\.short/', $level_js ),
		(bool) preg_match( '/el\( components\.TextareaControl, \{\s*label: __\( \'[^\']+\', \'wpcredits-theme\' \),\s*value: lines\( attributes\.items \)/', $level_js ),
		(bool) preg_match( "/getBlocksByName\( 'wpcredits\/partner-levels' \)/", $glance_js ) && false !== strpos( $glance_js, 'data.useSelect(' ),
		(bool) preg_match( '/save: function \(\) \{\s*return el\( blockEditor\.InnerBlocks\.Content \);\s*\}/', $levels_js ),
		(bool) preg_match( '/save: function \(\) \{\s*return null;\s*\}/', $level_js ),
		(bool) preg_match( '/save: function \(\) \{\s*return null;\s*\}/', $glance_js ),
		substr_count( $levels_js . $level_js . $glance_js, 'ServerSideRender' ),
	),
	array( true, true, array( 'name span []', 'tag span []', 'amount p []', 'lead p FORMATS' ), true, true, true, true, true, true, 0 ) );

// Ruling P1: no English text default in block.json; the table's three words fall back to the translated
// strings 1.31.11's pattern used, in render.php and in the editor. The final review of theme 1.32.1: in the
// editor each field's value is what is typed, '' while nothing is, and the translated string its
// placeholder, so deleting a field's last character leaves it empty rather than bringing the default back;
// the table's cells say the word the page draws.
ck( 'the table\'s head word and its two cell words are the strings 1.31.11\'s pattern translated, where the page draws them and as the editor\'s placeholders, each field\'s value what is typed',
	array(
		substr_count( $glance_js, "__( 'Recognition', 'wpcredits-theme' )" ),
		substr_count( $glance_js, "_x( 'Yes', 'a partnership level has this recognition', 'wpcredits-theme' )" ),
		substr_count( $glance_js, "_x( 'No', 'a partnership level does not have this recognition', 'wpcredits-theme' )" ),
		preg_match_all( '/value: typed\( attributes\.(label|yes|no) \),\s*placeholder: (labelWord|yesWord|noWord),/', $glance_js, $typed_fields, PREG_SET_ORDER ) ? array_map( static function ( $one ) { return $one[1] . ' ' . $one[2]; }, $typed_fields ) : array(),
		substr_count( theme_file( 'inc/partners.php' ), "__( 'Recognition', 'wpcredits-theme' )" ),
		substr_count( theme_file( 'inc/partners.php' ), "_x( 'Yes', 'a partnership level has this recognition', 'wpcredits-theme' )" ),
		substr_count( theme_file( 'inc/partners.php' ), "_x( 'No', 'a partnership level does not have this recognition', 'wpcredits-theme' )" ),
	),
	array( 1, 1, 1, array( 'label labelWord', 'yes yesWord', 'no noWord' ), 1, 1, 1 ) );

// The pattern's code, its comments left out, so a docblock that names a function does not count.
$pattern_source = implode( '', array_map( static function ( $token ) { return is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token; }, token_get_all( theme_file( 'patterns/partners-levels.php' ) ) ) );
$pattern        = wpcredits_test_pattern( 'partners-levels.php' );
$pattern_levels = wpcredits_test_block_in( $pattern, 'wpcredits/partner-levels' );
$pattern_glance = wpcredits_test_block_in( $pattern, 'wpcredits/partner-glance' );

ck( 'the levels pattern holds the levels block, a level block for each of the five levels with the defaults as its attributes, and the table block with none; no Custom HTML is left in it; it writes the defaults, never wpcredits_partner_levels(), which reads the template that refers to this pattern',
	array(
		$pattern_levels ? array_map( static function ( $parsed ) { return array( $parsed['blockName'], $parsed['attrs'] ); }, $pattern_levels['innerBlocks'] ) : null,
		$pattern_levels ? trim( implode( '', array_filter( $pattern_levels['innerContent'], 'is_string' ) ) ) : null,
		$pattern_glance ? $pattern_glance['attrs'] : null,
		substr_count( $pattern, '<!-- wp:html' ),
		substr_count( $pattern_source, 'wpcredits_partner_levels_defaults()' ),
		preg_match( '/wpcredits_partner_levels\(\s*\)/', $pattern_source ),
	),
	array(
		array_map( static function ( $level ) { return array( 'wpcredits/partner-level', $level ); }, $defaults ),
		'',
		array(),
		0,
		1,
		0,
	) );

ck( 'each sits in its own section, after the section\'s head: the levels in #levels, the table in #glance, named by its heading',
	array(
		count( $px->query( '//div[@id="levels"]/ul[@class="wpc-partners-levels__grid"]' ) ),
		count( $px->query( '//div[@id="glance"]/div[@class="wpc-partners-glance__scroll"]/table' ) ),
		found( $px, '//div[@id="glance"]//h2', 'id' ),
	),
	array( 1, 1, array( 'wpc-partners-glance-title' ) ) );

// The proof the page looks as it did. Live's saved copy (bin/fixtures/saved-partners.html) was saved from
// 1.31.11, whose cards and table 1.32.0 kept: its Custom HTML blocks hold them as 1.31.11 drew them.
$fixture = theme_file( 'bin/fixtures/saved-partners.html' );

/**
 * The cards' list in some markup, from its opening tag to the closing tag at the start of a line.
 *
 * @param string $html Markup.
 * @return string
 */
function grid_of( $html ) {
	return preg_match( '#<ul class="wpc-partners-levels__grid">.*?\n</ul>#s', $html, $found ) ? $found[0] : '';
}

/**
 * The table's scroll region in some markup, the table in it.
 *
 * @param string $html Markup.
 * @return string
 */
function table_of( $html ) {
	return preg_match( '#<div class="wpc-partners-glance__scroll".*?</table>\n</div>#s', $html, $found ) ? $found[0] : '';
}

$drawn_levels = $pattern_levels ? render_block( $pattern_levels ) : '';
$drawn_glance = $pattern_glance ? render_block( $pattern_glance ) : '';
$items        = array_merge( array(), ...array_column( $defaults, 'items' ) );
$row_at       = 0;
$rows_now     = preg_replace_callback(
	'#<th scope="row">[^<]*</th>#',
	static function () use ( &$row_at, $items ) {
		return '<th scope="row">' . esc_html( $items[ $row_at++ ] ?? '' ) . '</th>';
	},
	table_of( $fixture )
);

ck( 'today\'s levels block draws the cards byte for byte as 1.31.11 drew them, between the new lines its Custom HTML block held them in, so the #levels section is what it was',
	array( '' !== grid_of( $fixture ), $drawn_levels === "\n" . grid_of( $fixture ) . "\n" ),
	array( true, true ) );

ck( 'today\'s table block draws 1.31.11\'s table byte for byte but for its fifteen rows\' words, now the levels\' items in their own words: the region and its name, the head, and the Yes and No of every cell as they were',
	array( 15 === $row_at, '' !== table_of( $fixture ), $drawn_glance === "\n" . $rows_now . "\n" ),
	array( true, true, true ) );

ck( 'the rows read as the items do, each level\'s in its order, the lowest level\'s first',
	array(
		found( dom_of( $drawn_glance ), '//tbody/tr/th' ),
		found( dom_of( table_of( $fixture ) ), '//tbody/tr/th' ),
	),
	array(
		$items,
		array( 'Name on the partner page', 'Mention in the mentor feature', 'Acknowledged in the partner thank you post', 'Logo and description on the partner page', 'Badge for your own site', 'Annual program report', 'Named for what you fund in program communications', 'Report covering that cost line', 'A self-written educational post about the sponsored product', 'A second self-written post about additional products', 'Invitation to the annual education partners session', 'Co-hosted webinar or event', 'Named as program partner on the program you fund', 'Named in launch communications', 'Program-specific report' ),
	) );

/**
 * A Partner level block, as block markup.
 *
 * @param array $attributes Its attributes.
 * @return string
 */
function level_block( array $attributes ) {
	return wpcredits_hub_block_markup( 'wpcredits/partner-level', $attributes );
}

/**
 * The Partner levels block holding a level for each of some levels' attributes, as block markup.
 *
 * @param array[] $levels Each level's attributes.
 * @return string
 */
function levels_block( array $levels ) {
	return wpcredits_hub_block_markup( 'wpcredits/partner-levels', array(), array_map( 'level_block', $levels ) );
}

$plain_level = array(
	'name'   => 'Friend',
	'amount' => 'In-kind',
	'short'  => 'In-kind',
	'lead'   => 'For companies.',
	'items'  => array( 'Name on the page' ),
);

$no_tag     = do_blocks( levels_block( array( $plain_level + array( 'tag' => '' ), array_merge( $plain_level, array( 'name' => 'Spaced', 'tag' => " \u{00A0} " ) ) ) ) );
$tagged     = do_blocks( levels_block( array( $plain_level + array( 'tag' => 'Funds &amp; names' ) ) ) );
$bare       = do_blocks( levels_block( array( array( 'name' => 'Bare' ) ) ) );
$no_lead    = do_blocks( levels_block( array( array_merge( $plain_level, array( 'lead' => '', 'amount' => '  ', 'items' => array( '', '  ' ) ) ) ) ) );
$nameless   = do_blocks( levels_block( array( array_merge( $plain_level, array( 'name' => ' <strong></strong> ' ) ) ) ) );
$no_levels  = do_blocks( levels_block( array() ) );
$rich_level = do_blocks( levels_block( array( array_merge( $plain_level, array( 'name' => '<strong>Friend</strong> &amp; <a href="https://site.example/">co</a>', 'lead' => 'For <em>companies</em>.', 'items' => array( 'Logo <b>and</b> a & b' ) ) ) ) ) );

ck( 'nothing empty is printed: an empty tag no tag and no program class, an empty lead or amount no paragraph, no items no list; a level with no name is a draft and draws nothing, and the levels with no level to draw print nothing at all',
	array(
		array( substr_count( $no_tag, 'wpc-partners-tag' ), substr_count( $no_tag, 'wpc-partners-level--program' ), substr_count( $no_tag, '<li class="wpc-partners-level">' ) ),
		array( substr_count( $tagged, '<li class="wpc-partners-level wpc-partners-level--program">' ), substr_count( $tagged, '<h3 class="wpc-partners-level__name">Friend <span class="wpc-partners-tag">Funds &amp; names</span></h3>' ) ),
		$bare,
		array( substr_count( $no_lead, 'wpc-partners-level__lead' ), substr_count( $no_lead, 'wpc-partners-level__amount' ), substr_count( $no_lead, 'wpc-partners-level__items' ), substr_count( $no_lead, '<li>' ) ),
		$nameless,
		$no_levels,
	),
	array(
		array( 0, 0, 2 ),
		array( 1, 1 ),
		"\n<ul class=\"wpc-partners-levels__grid\">\n\t<li class=\"wpc-partners-level\">\n\t\t<h3 class=\"wpc-partners-level__name\">Bare</h3>\n\t</li>\n</ul>\n",
		array( 0, 0, 0, 0 ),
		'',
		'',
	) );

ck( 'the texts edited inline keep the editor\'s bold and italic and lose any other tag, their words kept; an item is text, printed as typed',
	array(
		substr_count( $rich_level, '<h3 class="wpc-partners-level__name"><strong>Friend</strong> &amp; co</h3>' ),
		substr_count( $rich_level, '<p class="wpc-partners-level__lead">For <em>companies</em>.</p>' ),
		substr_count( $rich_level, '<li>Logo &lt;b&gt;and&lt;/b&gt; a &amp; b</li>' ),
	),
	array( 1, 1, 1 ) );

echo "\n=== One source: the levels of the Partners template in use (D6, ruling R2) ===\n";

/**
 * The levels the theme reads, and the plugin's form is handed, with a copy of the Partners template
 * saved, or with none, each read afresh, as a new request reads them.
 *
 * @param string|null $content The saved copy's content, or null for none.
 * @param string      $status  The saved copy's status.
 * @return array{levels: array, form: array, page: string}
 */
function with_saved( $content, $status = 'publish' ) {
	// The template parts saved (theme 1.32.1) stay while a check reads: it puts them there, and takes them away.
	$GLOBALS['block_templates'] = array_filter( $GLOBALS['block_templates'], static function ( $key ) { return 0 === strpos( (string) $key, 'part:' ); }, ARRAY_FILTER_USE_KEY );

	if ( null !== $content ) {
		wpcredits_test_saved_template( 'page-partners', $content, $status );
	}

	theme_call( 'wpcredits_partner_levels_reset' );

	$read = array(
		'levels' => theme_call( 'wpcredits_partner_levels' ),
		'form'   => apply_filters( 'wpcpm_partner_levels', array() ),
		'page'   => null === $content ? partners_page() : partners_page( $content ),
	);

	$GLOBALS['block_templates'] = array_filter( $GLOBALS['block_templates'], static function ( $key ) { return 0 === strpos( (string) $key, 'part:' ); }, ARRAY_FILTER_USE_KEY );
	theme_call( 'wpcredits_partner_levels_reset' );

	return $read;
}

theme_call( 'wpcredits_partner_levels_reset' );
$GLOBALS['template_reads'] = 0;
$asked                     = array( theme_call( 'wpcredits_partner_levels' ), theme_call( 'wpcredits_partner_levels' ), apply_filters( 'wpcpm_partner_levels', array() ) );
$reads_once                = $GLOBALS['template_reads'];
theme_call( 'wpcredits_partner_levels_reset' );
theme_call( 'wpcredits_partner_levels' );
$reads_after_reset = $GLOBALS['template_reads'];

ck( 'the template is read once a request, however often the levels are asked for, and again after a reset',
	array( $asked[0] === $defaults, $asked[1] === $asked[0], 5 === count( $asked[2] ), $reads_once, $reads_after_reset ),
	array( true, true, true, 1, 2 ) );

$edited_levels = $defaults;

$edited_levels[1]['name']     = 'Sustainer';
$edited_levels[1]['items'][2] = 'The annual program report, in print';
unset( $edited_levels[4]['tag'] );
$edited_levels[] = array(
	'name'   => 'Patron',
	'amount' => 'From $50,000 a year',
	'short'  => 'From $50,000',
	'lead'   => 'Everything in Partner, plus:',
	'items'  => array( 'A seat at the program review' ),
);

$edited_markup = (string) theme_call( 'wpcredits_partner_levels_markup', $edited_levels );
$edited        = with_saved( partners_template( str_replace( (string) theme_call( 'wpcredits_partner_levels_markup', $defaults ), $edited_markup, $pattern ) ) );
$edited_px     = dom_of( $edited['page'] );

ck( 'a saved copy with its levels edited in the Site Editor: its levels block\'s levels, found inside the sections that hold it, are the levels, the page\'s cards and table both follow them, and the plugin\'s form is handed them',
	array(
		$edited['levels'] === $edited_levels,
		array_column( $edited['form'], 'name' ),
		$edited['form'][1]['items'] ?? null,
		array_key_exists( 'tag', $edited['form'][4] ?? array() ),
		found( $edited_px, '//li[contains(@class,"wpc-partners-level")]/h3' ),
		found( $edited_px, '//table/thead/tr/th' ),
		count( $edited_px->query( '//table/tbody/tr' ) ),
		found( $edited_px, '//table/tbody/tr[last()]/*' ),
	),
	array(
		true,
		array( 'Friend', 'Sustainer', 'Backer', 'Champion', 'Partner', 'Patron' ),
		array( 'Logo and description on the partner page', 'A badge for your own site', 'The annual program report, in print' ),
		false,
		array( 'Friend', 'Sustainer', 'Backer', 'Champion', 'Partner', 'Patron' ),
		array( 'Recognition', 'Friend In-kind', 'Sustainer $5,000', 'Backer $10,000', 'Champion $15,000', 'Partner From $30,000', 'Patron From $50,000' ),
		16,
		array( 'A seat at the program review', 'No', 'No', 'No', 'No', 'No', 'Yes' ),
	) );

$emptied = with_saved( partners_template( str_replace( (string) theme_call( 'wpcredits_partner_levels_markup', $defaults ), '<!-- wp:wpcredits/partner-levels /-->', $pattern ) ) );

ck( 'a saved copy whose levels block an Administrator emptied: no levels, so no cards and no table, and the plugin\'s form is handed none, so it names the Partners page instead',
	array( $emptied['levels'], $emptied['form'], grid_of( $emptied['page'] ), substr_count( $emptied['page'], '<table' ), substr_count( $emptied['page'], 'wpc-partners-glance__scroll' ) ),
	array( array(), array(), '', 0, 0 ) );

$from_31   = with_saved( $fixture );
$neither   = with_saved( (string) preg_replace( '#<!-- wp:html -->\s*<ul class="wpc-partners-levels__grid">.*?<!-- /wp:html -->#s', '', $fixture ) );
$glance_on_source = str_replace( '<!-- wp:html -->' . "\n" . table_of( $fixture ) . "\n" . '<!-- /wp:html -->', '<!-- wp:wpcredits/partner-glance /-->', (string) preg_replace( '#<!-- wp:html -->\s*<ul class="wpc-partners-levels__grid">.*?<!-- /wp:html -->#s', '', $fixture ) );
$glance_on        = with_saved( $glance_on_source );

ck( 'a saved copy with no levels block but 1.31\'s cards (live\'s, before its migration): the levels are read from those cards, and are the five the theme ships; the form is handed what 1.31.11 handed it',
	array( $from_31['levels'] === $defaults, $from_31['form'] === $open ),
	array( true, true ) );

ck( 'a saved copy with neither the levels block nor 1.31\'s cards: no levels, and the form is handed none; a table block there draws nothing',
	array( $neither['levels'], $neither['form'], substr_count( $glance_on_source, '<!-- wp:wpcredits/partner-glance /-->' ), substr_count( $glance_on_source, 'wpc-partners-levels__grid' ), $glance_on['levels'], substr_count( $glance_on['page'], 'wpc-partners-glance__scroll' ) ),
	array( array(), array(), 1, 0, array(), 0 ) );

$by_pattern = with_saved( (string) file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-partners.html' ) );
$unknown    = with_saved( '<!-- wp:pattern {"slug":"wpcredits/no-such-pattern"} /-->' );
$trashed    = with_saved( partners_template( str_replace( (string) theme_call( 'wpcredits_partner_levels_markup', $defaults ), '<!-- wp:wpcredits/partner-levels /-->', $pattern ) ), 'trash' );

ck( 'a saved copy that refers to the levels pattern is read through the pattern; a pattern the site does not have holds nothing; a saved copy in the trash is not the template in use, so the theme\'s file is',
	array( $by_pattern['levels'] === $defaults, $unknown['levels'], $trashed['levels'] === $defaults ),
	array( true, array(), true ) );

// Theme 1.32.1: an Administrator can turn the #levels group into a synced pattern (a `wp_block` post, which
// the template then refers to with `core/block`) or into a template part (`core/template-part`), as the
// Site Editor offers for any group. The levels block is then in the post's or the part's content, and the
// reading follows the reference as WordPress draws it: a synced pattern only while it is published and has
// no password, a template part of this theme only, its saved copy only while published, else the theme's file.
// The stand-in renders no core block, so the checks are of the levels read, the table drawn from them (a
// block of this theme) and the form's list; the cards are the synced content's own to draw.

/**
 * A synced pattern, as core's get_post() answers for its ID.
 *
 * @param int    $id       The post's ID.
 * @param string $content  Its content, block markup.
 * @param string $status   Its status.
 * @param string $type     Its post type.
 * @param string $password Its password.
 */
function synced( $id, $content, $status = 'publish', $type = 'wp_block', $password = '' ) {
	$GLOBALS['synced'][ $id ] = (object) array(
		'ID'            => $id,
		'post_type'     => $type,
		'post_status'   => $status,
		'post_content'  => $content,
		'post_password' => $password,
	);
}

/**
 * The levels read from a Partners template, the form handed and the table drawn, with the table block
 * placed in the template after the levels group, however that group is held.
 *
 * @param string $content The template's content.
 * @return array{levels: array, form: array, table: int}
 */
function read_with_table( $content ) {
	$read = with_saved( $content );

	return array(
		'levels' => $read['levels'],
		'form'   => $read['form'],
		'table'  => count( dom_of( $read['page'] )->query( '//table' ) ),
	);
}

$levels_group_re = '#(<!-- wp:group \{"align":"wide","anchor":"levels".*?<!-- /wp:group -->)\s*(?=<!-- wp:group \{"align":"wide","anchor":"glance")#s';

preg_match( $levels_group_re, (string) $pattern, $levels_group );

$levels_group_markup = $levels_group[1] ?? '';
$in_template         = static function ( $held ) use ( $levels_group_re, $pattern ) {
	return partners_template( (string) preg_replace( $levels_group_re, $held . "\n\n", (string) $pattern ) );
};
$synced_ref          = '<!-- wp:block {"ref":501} /-->';
$part_ref            = '<!-- wp:template-part {"slug":"partners-levels-part","theme":"wpcredits-theme"} /-->';

$GLOBALS['synced'] = array();
synced( 501, $levels_group_markup );
synced( 502, '<!-- wp:block {"ref":502} /-->' );
synced( 503, '<!-- wp:paragraph --><p>Around.</p><!-- /wp:paragraph -->' . "\n" . '<!-- wp:block {"ref":501} /-->' );
synced( 504, str_replace( (string) theme_call( 'wpcredits_partner_levels_markup', $defaults ), $edited_markup, $levels_group_markup ) );
synced( 505, $levels_group_markup, 'draft' );
synced( 506, $levels_group_markup, 'trash' );
synced( 507, $levels_group_markup, 'publish', 'wp_block', 'secret' );
synced( 508, $levels_group_markup, 'publish', 'page' );
synced( 509, '<!-- wp:block {"ref":510} /-->' );
synced( 510, '<!-- wp:block {"ref":509} /-->' . "\n" . $levels_group_markup );

$by_ref = static function ( $ref ) use ( $in_template ) {
	return read_with_table( $in_template( '<!-- wp:block {"ref":' . $ref . '} /-->' ) );
};

$synced_read  = read_with_table( $in_template( $synced_ref ) );
$synced_edit  = $by_ref( 504 );
$synced_deep  = $by_ref( 503 );
$synced_loop  = $by_ref( 509 );
$synced_gone  = array_map( $by_ref, array( 502, 505, 506, 507, 508, 999, 0 ) );

ck( 'a saved copy whose levels group an Administrator made a synced pattern refers to it with core/block: the levels are read from the pattern\'s content, so the table is drawn and the form is handed the five levels, as with the group in the template',
	array( $synced_read['levels'] === $defaults, $synced_read['form'] === $open, $synced_read['table'] ),
	array( true, true, 1 ) );

ck( 'the synced pattern is the source: an edit of its levels is what the table and the form follow; a synced pattern inside another is followed, and two that refer to each other, or one to itself, are read once, not for ever',
	array(
		array_column( $synced_edit['levels'], 'name' ),
		array_column( $synced_edit['form'], 'name' ),
		$synced_edit['table'],
		$synced_deep['levels'] === $defaults,
		$synced_loop['levels'] === $defaults,
		$by_ref( 502 )['levels'],
	),
	array(
		array( 'Friend', 'Sustainer', 'Backer', 'Champion', 'Partner', 'Patron' ),
		array( 'Friend', 'Sustainer', 'Backer', 'Champion', 'Partner', 'Patron' ),
		1,
		true,
		true,
		array(),
	) );

ck( 'a synced pattern WordPress does not draw is not read, so the page shows no levels and the form names the page: one in draft or in the trash, one with a password, a post that is no synced pattern, an ID no post has and none',
	array_map( static function ( $read ) { return array( $read['levels'], $read['form'], $read['table'] ); }, $synced_gone ),
	array_fill( 0, 7, array( array(), array(), 0 ) ) );

// A template part of this theme holds the group: its saved copy, published.
$GLOBALS['part_reads'] = 0;
wpcredits_test_saved_template( 'partners-levels-part', $levels_group_markup, 'publish', 'wp_template_part' );

$part_read   = read_with_table( $in_template( $part_ref ) );
$part_no_say = read_with_table( $in_template( '<!-- wp:template-part {"slug":"partners-levels-part"} /-->' ) );
$part_reads  = $GLOBALS['part_reads'];
$part_other  = read_with_table( $in_template( '<!-- wp:template-part {"slug":"partners-levels-part","theme":"another-theme"} /-->' ) );
$part_none   = read_with_table( $in_template( '<!-- wp:template-part {"slug":"no-such-part","theme":"wpcredits-theme"} /-->' ) );

wpcredits_test_saved_template( 'partners-levels-part', $levels_group_markup, 'draft', 'wp_template_part' );

$part_draft = read_with_table( $in_template( $part_ref ) );

wpcredits_test_saved_template( 'header', $levels_group_markup, 'publish', 'wp_template_part' );

$part_over_file = read_with_table( $in_template( '<!-- wp:template-part {"slug":"header","theme":"wpcredits-theme"} /-->' ) );

$GLOBALS['block_templates'] = array();
$GLOBALS['synced']          = array();
theme_call( 'wpcredits_partner_levels_reset' );

ck( 'a saved copy whose levels group an Administrator made a template part is read through the part, found by its slug in this theme (a part with no theme named is this theme\'s, as core has it): the levels are read, the table drawn and the form handed them; the part is asked of WordPress, not of the theme\'s files alone',
	array( $part_read['levels'] === $defaults, $part_read['form'] === $open, $part_read['table'], $part_no_say['levels'] === $defaults, $part_reads > 0 ),
	array( true, true, 1, true, true ) );

ck( 'a part of another theme, a part that does not exist, and a saved copy of a part in draft, which WordPress does not draw (the theme\'s own file is, and holds no levels), are no source of levels; a saved copy of a part published takes the place of the theme\'s file of that name',
	array(
		array( $part_other['levels'], $part_other['table'] ),
		array( $part_none['levels'], $part_none['table'] ),
		array( $part_draft['levels'], $part_draft['table'] ),
		array( $part_over_file['levels'] === $defaults, $part_over_file['table'] ),
	),
	array( array( array(), 0 ), array( array(), 0 ), array( array(), 0 ), array( true, 1 ) ) );

// Theme 1.32.1, the final review: the reading looks through the template's own blocks first, at every
// depth, and follows what they refer to (patterns, synced patterns, template parts) only when the levels
// block is not among them. The Partners template opens with two template parts, the WordPress.org bar and
// the header, which hold no levels: a saved copy whose levels block is in its own blocks reads neither.
// Where a copy holds a levels block in its own blocks and another in what it refers to, its own is read.
$GLOBALS['synced'] = array();
synced( 504, str_replace( (string) theme_call( 'wpcredits_partner_levels_markup', $defaults ), $edited_markup, $levels_group_markup ) );

$GLOBALS['part_reads'] = 0;
$own_first             = with_saved( partners_template() );
$own_first_reads       = $GLOBALS['part_reads'];
$own_and_ref           = read_with_table( str_replace( $levels_group_markup, '<!-- wp:block {"ref":504} /-->' . "\n\n" . $levels_group_markup, partners_template() ) );
$GLOBALS['part_reads'] = 0;
$ref_only              = read_with_table( $in_template( '<!-- wp:block {"ref":504} /-->' ) );
$ref_only_reads        = $GLOBALS['part_reads'];

$GLOBALS['synced'] = array();
theme_call( 'wpcredits_partner_levels_reset' );

ck( 'a saved copy whose levels block is in its own blocks is read without a template part being read: the levels are found before the WordPress.org bar and the header are looked into; a copy with the levels only in a synced pattern still finds them there, the parts before it looked into first, in their order',
	array( $own_first['levels'] === $defaults, $own_first_reads, array_column( $ref_only['levels'], 'name' ), $ref_only_reads ),
	array( true, 0, array( 'Friend', 'Sustainer', 'Backer', 'Champion', 'Partner', 'Patron' ), 2 ) );

ck( 'a saved copy with a levels block in its own blocks and another in a synced pattern it refers to, the reference first in the markup: its own block is the source, of the levels, the table and the form',
	array( $own_and_ref['levels'] === $defaults, array_column( $own_and_ref['form'], 'name' ), $own_and_ref['table'] ),
	array( true, array( 'Friend', 'Supporter', 'Backer', 'Champion', 'Partner' ), 1 ) );

// Theme 1.32.1, the final review: core's block visibility (WordPress 7.0 and later). An Administrator can
// hide any block in the Site Editor, from the block's menu, which saves `"metadata":{"blockVisibility":false}`
// in its comment, and core then draws it as nothing (wp_render_block_visibility_support(), hooked here as
// wpcredits_test_block_visibility()): a hidden level has no card, and a hidden levels block, or a hidden
// block around it, draws no card at all. The table and the plugin's form follow the page: a hidden level
// is no level, and a levels block hidden, or under a hidden block, is a template that shows no levels, so
// none, never the 1.31 cards' reading. A block hidden at some window sizes only is still on the page,
// shown at the others, so its levels stay.
add_filter( 'render_block', 'wpcredits_test_block_visibility', 10, 2 );

/**
 * Some block markup with the first block that opens so given a `metadata` attribute, as the Site
 * Editor's block menu saves a block hidden. A check whose block is not there cannot be set up, so it
 * stops the run.
 *
 * @param string $markup  Block markup.
 * @param string $opening The block's opening comment, whole (`<!-- wp:html -->`) or up to the `{` of
 *                        its attributes (`<!-- wp:group {`), the first key after it if need be.
 * @param mixed  $visible The `blockVisibility` value: false, hidden everywhere, or a viewport setting.
 * @return string
 */
function hidden_in( $markup, $opening, $visible = false ) {
	$metadata = '"metadata":' . json_encode( array( 'blockVisibility' => $visible ) );
	$at       = strpos( $markup, $opening );

	if ( false === $at ) {
		throw new RuntimeException( 'hidden_in(): the markup has no ' . $opening );
	}

	$given = false !== strpos( $opening, '{' ) ? str_replace( '{', '{' . $metadata . ',', $opening ) : (string) preg_replace( '#\s*(/?-->)$#', ' {' . $metadata . '} $1', $opening );

	return substr_replace( $markup, $given, $at, strlen( $opening ) );
}

/**
 * What the cards, the table and the form say for a saved Partners template: the levels each names, in
 * its order, and the table's rows.
 *
 * @param string $content The template's content.
 * @return array{levels: string[], cards: string[], table: string[], form: string[], rows: string[], items: string[]}
 */
function what_each_says( $content ) {
	$read = with_saved( $content );
	$page = dom_of( $read['page'] );

	return array(
		'levels' => array_column( (array) $read['levels'], 'name' ),
		'cards'  => found( $page, '//li[contains(concat(" ", normalize-space(@class), " "), " wpc-partners-level ")]/h3/text()[1]' ),
		'table'  => found( $page, '//thead//span[@class="wpc-partners-glance__level"]' ),
		'form'   => array_column( (array) $read['form'], 'name' ),
		'rows'   => found( $page, '//tbody/tr/th' ),
		'items'  => array_merge( array(), ...array_column( (array) $read['form'], 'items' ) ),
	);
}

$levels_markup  = (string) theme_call( 'wpcredits_partner_levels_markup', $defaults );
$hide_supporter = static function ( $visible ) use ( $levels_markup ) {
	return hidden_in( $levels_markup, '<!-- wp:wpcredits/partner-level {"name":"Supporter"', $visible );
};
$with_levels    = static function ( $markup ) use ( $levels_markup ) {
	return partners_template( str_replace( $levels_markup, $markup, wpcredits_test_pattern( 'partners-levels.php' ) ) );
};
$four           = array( 'Friend', 'Backer', 'Champion', 'Partner' );
$five           = array_column( $defaults, 'name' );

$hidden_level    = what_each_says( $with_levels( $hide_supporter( false ) ) );
$level_on_phones = what_each_says( $with_levels( $hide_supporter( array( 'viewport' => array( 'mobile' => false ) ) ) ) );
$phone_page      = with_saved( $with_levels( $hide_supporter( array( 'viewport' => array( 'mobile' => false ) ) ) ) )['page'];

ck( 'a level an Administrator hid is no level: the page draws four cards, the table four columns and the rows of their items alone, and the plugin\'s form is handed the same four; a level hidden on phones only stays a level in all three, its card drawn with core\'s class that hides it at that size',
	array(
		array( $hidden_level['levels'], $hidden_level['cards'], $hidden_level['table'], $hidden_level['form'], $hidden_level['rows'] === $hidden_level['items'], count( $hidden_level['rows'] ) ),
		array( $level_on_phones['levels'], $level_on_phones['cards'], $level_on_phones['table'], $level_on_phones['form'], count( $level_on_phones['rows'] ) ),
		substr_count( $phone_page, '<li class="wpc-partners-level wp-block-hidden-mobile">' ),
	),
	array(
		array( $four, $four, $four, $four, true, 12 ),
		array( $five, $five, $five, $five, 15 ),
		1,
	) );

$none_said     = array( 'levels' => array(), 'cards' => array(), 'table' => array(), 'form' => array(), 'rows' => array(), 'items' => array() );
$levels_hidden = what_each_says( $with_levels( hidden_in( $levels_markup, '<!-- wp:wpcredits/partner-levels -->' ) ) );
$group_hidden  = what_each_says( hidden_in( partners_template(), '<!-- wp:group {"align":"wide","anchor":"levels"', false ) );
$main_hidden   = what_each_says( hidden_in( partners_template(), '<!-- wp:group {"tagName":"main"', false ) );
$with_31_cards = what_each_says( $with_levels( hidden_in( $levels_markup, '<!-- wp:wpcredits/partner-levels -->' ) . "\n\n" . hidden_in( '<!-- wp:html -->' . "\n" . grid_of( $fixture ) . "\n" . '<!-- /wp:html -->', '<!-- wp:html -->' ) ) );
$phones_markup = $with_levels( hidden_in( $levels_markup, '<!-- wp:wpcredits/partner-levels -->', array( 'viewport' => array( 'mobile' => false, 'tablet' => false ) ) ) );
$levels_phones = what_each_says( $phones_markup );
$phones_grid   = substr_count( with_saved( $phones_markup )['page'], '<ul class="wpc-partners-levels__grid wp-block-hidden-mobile wp-block-hidden-tablet">' );

ck( 'a levels block an Administrator hid, or the group around it, or the page\'s main around that: no card, no table, and the plugin\'s form is handed no level, so it names the Partners page; the same beside 1.31\'s cards in a hidden Custom HTML block, which the reading does not fall back to; a levels block hidden on phones and tablets only keeps its five levels',
	array( $levels_hidden, $group_hidden, $main_hidden, $with_31_cards, array( $levels_phones['levels'], $levels_phones['cards'], $levels_phones['table'], $levels_phones['form'], $phones_grid ) ),
	array( $none_said, $none_said, $none_said, $none_said, array( $five, $five, $five, $five, 1 ) ) );

$GLOBALS['synced'] = array();
synced( 501, $levels_group_markup );
synced( 504, str_replace( $levels_markup, $edited_markup, $levels_group_markup ) );
synced( 511, hidden_in( $levels_group_markup, '<!-- wp:group {"align":"wide","anchor":"levels"', false ) );

$ref_hidden    = read_with_table( $in_template( hidden_in( '<!-- wp:block {"ref":501} /-->', '<!-- wp:block {', false ) ) );
$in_ref_hidden = read_with_table( $in_template( '<!-- wp:block {"ref":511} /-->' ) );
$shown_after   = read_with_table( str_replace( $levels_group_markup, hidden_in( $levels_group_markup, '<!-- wp:group {"align":"wide","anchor":"levels"', false ) . "\n\n" . '<!-- wp:block {"ref":504} /-->', partners_template() ) );

$GLOBALS['synced'] = array();
theme_call( 'wpcredits_partner_levels_reset' );

ck( 'a synced pattern holding the levels, hidden where the template refers to it, or with its group hidden inside it: no levels, no table, no form levels; a hidden levels group does not hide another levels block that is drawn, in a synced pattern after it: that one is the source',
	array(
		array( $ref_hidden['levels'], $ref_hidden['form'], $ref_hidden['table'] ),
		array( $in_ref_hidden['levels'], $in_ref_hidden['form'], $in_ref_hidden['table'] ),
		array( array_column( $shown_after['levels'], 'name' ), array_column( $shown_after['form'], 'name' ), $shown_after['table'] ),
	),
	array(
		array( array(), array(), 0 ),
		array( array(), array(), 0 ),
		array( array( 'Friend', 'Sustainer', 'Backer', 'Champion', 'Partner', 'Patron' ), array( 'Friend', 'Sustainer', 'Backer', 'Champion', 'Partner', 'Patron' ), 1 ),
	) );

remove_filter( 'render_block', 'wpcredits_test_block_visibility', 10 );

// Theme 1.32.1: WordPress draws a saved copy of the Partners template only while its post is published
// (get_block_templates() asks for `publish`), though get_block_template() answers with a copy in draft,
// auto-draft or the trash as well (wp-includes/block-template-utils.php): a copy in draft is not the
// template in use either.
$draft_copies = array();

foreach ( array( 'draft', 'auto-draft', 'trash' ) as $not_drawn ) {
	$draft_copies[ $not_drawn ] = with_saved( partners_template( str_replace( (string) theme_call( 'wpcredits_partner_levels_markup', $defaults ), '<!-- wp:wpcredits/partner-levels /-->', $pattern ) ), $not_drawn )['levels'] === $defaults;
}

ck( 'a saved copy of the Partners template that WordPress does not draw is not the template in use, so the theme\'s file is, and its levels the defaults: a copy in draft, auto-draft or the trash; only a published copy is',
	array( $draft_copies, with_saved( partners_template( str_replace( (string) theme_call( 'wpcredits_partner_levels_markup', $defaults ), '<!-- wp:wpcredits/partner-levels /-->', $pattern ) ), 'publish' )['levels'] ),
	array( array_fill_keys( array( 'draft', 'auto-draft', 'trash' ), true ), array() ) );

// Theme 1.32.1: an item that is only a no-break space says nothing, though PHP's trim keeps it: the editor's
// own trim (tidy()) drops it from the list, so the page, the table and the form leave it out as well.
$nbsp = "\u{00A0}";
$nbsp_level = array_merge( $plain_level, array( 'items' => array( 'A', $nbsp, ' ' . $nbsp . ' ', 'B', "\u{2003}" . $nbsp ) ) );
$nbsp_read  = with_saved( levels_block( array( $nbsp_level, array( 'name' => 'Only blanks', 'items' => array( $nbsp, ' ' ) ) ) ) . wpcredits_partner_glance_markup() );
$nbsp_px    = dom_of( $nbsp_read['page'] );

ck( 'an item of only a no-break space (or any white space) is no item: the level the theme reads, the page\'s list, the table\'s rows and the plugin\'s form all hold the two real items, and a level with only such items has no list at all',
	array(
		array_column( $nbsp_read['levels'], 'items' ),
		found( $nbsp_px, '//ul[@class="wpc-partners-level__items"]/li' ),
		count( $nbsp_px->query( '//ul[@class="wpc-partners-level__items"]' ) ),
		found( $nbsp_px, '//tbody/tr/th' ),
		array_column( $nbsp_read['form'], 'items' ),
		count( $nbsp_px->query( '//li[not(normalize-space(translate(., "' . $nbsp . '", " ")))]' ) ),
	),
	array(
		array( array( 'A', 'B' ), array() ),
		array( 'A', 'B' ),
		1,
		array( 'A', 'B' ),
		array( array( 'A', 'B' ), array() ),
		0,
	) );

$rich = with_saved(
	levels_block(
		array(
			array(
				'name'   => '<strong>Friend</strong> &amp; co',
				'amount' => 'In-kind, <em>through</em> Five for the Future',
				'short'  => 'In-kind',
				'tag'    => 'Funds &amp; names',
				'lead'   => 'For companies,<br>sponsoring mentors.',
				'items'  => array( 'Partners&#039; report', '<b>Bold</b> claim', '   ' ),
			),
			array(
				'name'  => '<em></em>',
				'items' => array( 'A draft level, with no name' ),
			),
			array(
				'name' => 'Supporter',
				'tag'  => '<strong> </strong>',
			),
		)
	)
);

ck( 'what the plugin is handed is plain text, as it escapes what it prints: the rich texts\' tags taken out, a line break a space, entities read; an item, plain text already, as the page prints it, a tag in it a few characters; an item that is only spaces, a level with no name and a tag with no words left out; amount, lead and items there for every level',
	$rich['form'],
	array(
		array(
			'name'   => 'Friend & co',
			'amount' => 'In-kind, through Five for the Future',
			'lead'   => 'For companies, sponsoring mentors.',
			'items'  => array( 'Partners\' report', '<b>Bold</b> claim' ),
			'tag'    => 'Funds & names',
		),
		array(
			'name'   => 'Supporter',
			'amount' => '',
			'lead'   => '',
			'items'  => array(),
		),
	) );

ck( 'the levels the theme reads keep what the blocks hold, texts as rich text keeps them, for the page to draw',
	array_column( (array) $rich['levels'], 'name' ),
	array( '<strong>Friend</strong> &amp; co', 'Supporter' ) );

// Theme 1.32.1, the final review: an item is plain text, typed in the level's settings and printed by the
// page and the table with esc_html(), so an angle bracket in it is a character. The form is handed it as
// the page shows it: its entities read and its white space run together, no tag taken out, which cut
// "Up to <5 students" to "Up to " and left "<3 mentors" out. Core's esc_html() leaves an entity as it is
// (it does not encode one again), so a browser shows "A &amp; B" as "A & B"; the stand-in's encodes it
// again, so what the page shows is worked out here as core prints it.
$angled_items = array( 'Up to <5 students', '<3 mentors', 'A &amp; B', 'Two   spaces' );
$angled       = with_saved( levels_block( array( array( 'name' => '<strong>Friend</strong>', 'items' => $angled_items ) ) ) . wpcredits_partner_glance_markup() );
$angled_px    = dom_of( $angled['page'] );
$as_core      = array_map( static function ( $item ) { return found( dom_of( '<li>' . htmlspecialchars( $item, ENT_QUOTES, 'UTF-8', false ) . '</li>' ), '//li' )[0]; }, $angled_items );

ck( 'an item reaches the plugin\'s form as the page shows it: "Up to <5 students" and "<3 mentors" whole, as the cards and the table print them, an entity read and white space run together; the name, rich text, still loses its tags',
	array(
		$angled['form'][0]['items'] ?? null,
		( $angled['form'][0]['items'] ?? null ) === $as_core,
		array_slice( found( $angled_px, '//ul[@class="wpc-partners-level__items"]/li' ), 0, 2 ),
		array_slice( found( $angled_px, '//tbody/tr/th' ), 0, 2 ),
		$angled['form'][0]['name'] ?? null,
	),
	array(
		array( 'Up to <5 students', '<3 mentors', 'A & B', 'Two spaces' ),
		true,
		array( 'Up to <5 students', '<3 mentors' ),
		array( 'Up to <5 students', '<3 mentors' ),
		'Friend',
	) );

echo "\n=== The table: its rows and its words ===\n";

/**
 * The table drawn on a page whose template holds some levels: the Partners template saved with those
 * levels, and the table block given some attributes.
 *
 * @param array[] $levels     Each level's attributes.
 * @param array   $attributes The table block's attributes.
 * @return string
 */
function glance_with( array $levels, array $attributes = array() ) {
	$GLOBALS['block_templates'] = array();
	wpcredits_test_saved_template( 'page-partners', levels_block( $levels ) );
	theme_call( 'wpcredits_partner_levels_reset' );

	$html = do_blocks( wpcredits_hub_block_markup( 'wpcredits/partner-glance', $attributes ) );

	$GLOBALS['block_templates'] = array();
	theme_call( 'wpcredits_partner_levels_reset' );

	return $html;
}

/**
 * Each row of a table body: the words of its cells, the row's head first.
 *
 * @param DOMXPath $xpath The page.
 * @return string[][]
 */
function rows_of( DOMXPath $xpath ) {
	$rows = array();

	foreach ( $xpath->query( '//tbody/tr' ) as $row ) {
		$cells = array();

		foreach ( $xpath->query( './*', $row ) as $cell ) {
			$cells[] = trim( preg_replace( '/\s+/u', ' ', $cell->textContent ) );
		}

		$rows[] = $cells;
	}

	return $rows;
}

$two = array(
	array( 'name' => 'Low', 'short' => '$1', 'items' => array( 'A', 'B' ) ),
	array( 'name' => 'High', 'short' => '', 'items' => array( 'C' ) ),
);
$two_px = dom_of( glance_with( $two ) );

ck( 'a row for every item of every level, the lowest level\'s first, Yes from the level that lists it upward; a level with no short amount has no amount in the head',
	array(
		found( $two_px, '//thead/tr/th' ),
		rows_of( $two_px ),
		count( $two_px->query( '//thead//span[@class="wpc-partners-glance__amount"]' ) ),
	),
	array(
		array( 'Recognition', 'Low $1', 'High' ),
		array( array( 'A', 'Yes', 'Yes' ), array( 'B', 'Yes', 'Yes' ), array( 'C', 'No', 'Yes' ) ),
		1,
	) );

ck( 'levels with no item compare nothing, and no levels at all: the table draws nothing, no empty table',
	array( glance_with( array( array( 'name' => 'Low' ), array( 'name' => 'High' ) ) ), glance_with( array() ) ),
	array( '', '' ) );

$worded = dom_of( glance_with( $two, array( 'label' => 'What it gives', 'yes' => 'Included', 'no' => 'Not included' ) ) );
$blank  = dom_of( glance_with( $two, array( 'label' => '  ', 'yes' => '', 'no' => ' ' ) ) );

$GLOBALS['translated'] = array(
	'Recognition'                                     => 'Reconnaissance & co',
	"a partnership level has this recognition\4Yes"   => 'Oui',
	"a partnership level does not have this recognition\4No" => 'Non',
);
$in_french      = dom_of( glance_with( $two ) );
$in_french_html = glance_with( $two );
$GLOBALS['translated'] = array();

ck( 'the head word and the two cell words: as typed in the block, and with none typed, or only spaces, the translated strings 1.31.11\'s pattern used, escaped where they are printed',
	array(
		array( found( $worded, '//thead/tr/th[1]' ), array_values( array_unique( found( $worded, '//tbody//td' ) ) ) ),
		array( found( $blank, '//thead/tr/th[1]' ), array_values( array_unique( found( $blank, '//tbody//td' ) ) ) ),
		array( found( $in_french, '//thead/tr/th[1]' ), array_values( array_unique( found( $in_french, '//tbody//td' ) ) ), substr_count( $in_french_html, '<th scope="col">Reconnaissance &amp; co</th>' ) ),
	),
	array(
		array( array( 'What it gives' ), array( 'Included', 'Not included' ) ),
		array( array( 'Recognition' ), array( 'Yes', 'No' ) ),
		array( array( 'Reconnaissance & co' ), array( 'Oui', 'Non' ), 1 ),
	) );

echo "\n=== Reading 1.31's cards, and writing the blocks ===\n";

ck( 'live\'s saved copy reads as the five levels exactly as the theme ships them: each its name, amount, tag, lead and items from the cards, and its short amount from the table\'s head, by column',
	theme_call( 'wpcredits_partner_levels_from_html', $fixture ) === $defaults, true );

$cards_only = theme_call( 'wpcredits_partner_levels_from_html', grid_of( $fixture ) );
$edited_31  = theme_call( 'wpcredits_partner_levels_from_html', '<ul class="wpc-partners-levels__grid"><li class="wpc-partners-level"><h3 class="wpc-partners-level__name">Friend &amp; co <span class="wpc-partners-tag"> In  kind </span></h3><p class="wpc-partners-level__lead">For <strong>companies</strong>.</p><ul class="wpc-partners-level__items"><li> One </li><li></li></ul></li><li class="wpc-partners-level"><h3 class="wpc-partners-level__name"> </h3></li></ul>' );

ck( 'cards with no table have no short amount; an Administrator\'s edit of the markup is read as plain text, spaces run together, an empty item and a level with no name left out; markup with no cards reads as no levels',
	array(
		is_array( $cards_only ) ? array_map( 'array_keys', $cards_only ) : null,
		$edited_31,
		theme_call( 'wpcredits_partner_levels_from_html', '' ),
		theme_call( 'wpcredits_partner_levels_from_html', '<p>No cards here.</p>' ),
	),
	array(
		array_merge( array_fill( 0, 4, array( 'name', 'amount', 'lead', 'items' ) ), array( array( 'name', 'amount', 'tag', 'lead', 'items' ) ) ),
		array( array( 'name' => 'Friend & co', 'amount' => '', 'tag' => 'In kind', 'lead' => 'For companies.', 'items' => array( 'One' ) ) ),
		array(),
		array(),
	) );

$special = array(
	array(
		'name'   => 'Friend & <co>',
		'amount' => '$5,000 a year',
		'short'  => '$5,000',
		'tag'    => 'Funds "names"',
		'lead'   => 'Everything & more:',
		'items'  => array( 'A & B', 'C' ),
	),
	array(
		'name'   => 'Supporter',
		'amount' => '',
		'lead'   => '',
		'tag'    => '',
		'items'  => array(),
	),
);
$special_block = wpcredits_test_block_in( (string) theme_call( 'wpcredits_partner_levels_markup', $special ), 'wpcredits/partner-levels' );

ck( 'the levels block as the Site Editor saves it: a level block for each level, its texts edited inline as rich text keeps them, escaped, its short amount and items as typed, an empty one not written, as the editor leaves out an attribute at its default; with no levels, the block alone; the table block with no attribute',
	array(
		$special_block ? array_map( static function ( $parsed ) { return $parsed['attrs']; }, $special_block['innerBlocks'] ) : null,
		theme_call( 'wpcredits_partner_levels_markup', array() ),
		theme_call( 'wpcredits_partner_glance_markup' ),
	),
	array(
		array(
			array( 'name' => 'Friend &amp; &lt;co&gt;', 'amount' => '$5,000 a year', 'short' => '$5,000', 'tag' => 'Funds &quot;names&quot;', 'lead' => 'Everything &amp; more:', 'items' => array( 'A & B', 'C' ) ),
			array( 'name' => 'Supporter' ),
		),
		'<!-- wp:wpcredits/partner-levels /-->',
		'<!-- wp:wpcredits/partner-glance /-->',
	) );

ck( 'live\'s 1.31 cards, read and written as the levels block, draw byte for byte what they were',
	do_blocks( (string) theme_call( 'wpcredits_partner_levels_markup', (array) theme_call( 'wpcredits_partner_levels_from_html', $fixture ) ) ) === "\n" . grid_of( $fixture ) . "\n", true );

$GLOBALS['block_templates'] = array();
theme_call( 'wpcredits_partner_levels_reset' );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
