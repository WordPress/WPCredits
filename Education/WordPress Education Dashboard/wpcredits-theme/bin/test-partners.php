<?php
/**
 * Checks for the Partners page (templates/page-partners.html, the partners-* patterns,
 * inc/partners.php and assets/css/partners.css), run with `php bin/test-partners.php` on the
 * WordPress of bin/stubs/hub-wp.php. The page presents the WordPress Credits partnership levels in
 * a program lead's words (21 October 2026), in the Education Hub's look (the owner, the same day).
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
 * The Partners page as WordPress draws it from the template, each pattern's content in place, the
 * page's own content left as its block comment.
 *
 * @return string
 */
function partners_page() {
	$template = (string) file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-partners.html' );

	return preg_replace_callback(
		'#<!-- wp:pattern \{"slug":"wpcredits/([a-z-]+)"\} /-->#',
		static function ( $part ) {
			return wpcredits_test_pattern( $part[1] . '.php' );
		},
		$template
	);
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

$GLOBALS['marked'] = true;
$marked_px         = dom_of( partners_page() );
$GLOBALS['marked'] = false;

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

$sources .= file_get_contents( WPCREDITS_THEME_DIR . 'inc/partners.php' ) . file_get_contents( WPCREDITS_THEME_DIR . 'assets/css/partners.css' ) . $template;

ck( 'no em or en dash in the page\'s files', preg_match( '/[\x{2013}\x{2014}]/u', $sources ), 0 );

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

$levels = function_exists( 'wpcredits_partner_levels' ) ? wpcredits_partner_levels() : array();

ck( 'the five levels are one list in a function, in the program lead\'s order: each its name, amount, short amount, lead and three items, and the Partner level also its tag',
	array(
		array_column( $levels, 'name' ),
		array_map( 'array_keys', $levels ),
		array_map( static function ( $level ) { return count( $level['items'] ); }, $levels ),
		array_column( $levels, 'tag' ),
	),
	array(
		array( 'Friend', 'Supporter', 'Backer', 'Champion', 'Partner' ),
		array_merge( array_fill( 0, 4, array( 'name', 'amount', 'short', 'lead', 'items' ) ), array( array( 'name', 'amount', 'short', 'tag', 'lead', 'items' ) ) ),
		array( 3, 3, 3, 3, 3 ),
		array( 'Funds a named program' ),
	) );

ck( 'the page draws from that list: in the cards each level\'s name (and the tag beside the Partner\'s), amount, lead and items, and in the table\'s head its name and short amount',
	array(
		found( $px, '//li[contains(@class,"wpc-partners-level")]/h3' ),
		found( $px, '//li[contains(@class,"wpc-partners-level")]/p[@class="wpc-partners-level__amount"]' ),
		found( $px, '//li[contains(@class,"wpc-partners-level")]/p[@class="wpc-partners-level__lead"]' ),
		found( $px, '//ul[@class="wpc-partners-level__items"]/li' ),
		found( $px, '//table/thead/tr/th' ),
	),
	array(
		array_map( static function ( $level ) { return $level['name'] . ( isset( $level['tag'] ) ? ' ' . $level['tag'] : '' ); }, $levels ),
		array_column( $levels, 'amount' ),
		array_column( $levels, 'lead' ),
		array_merge( ...array_column( $levels, 'items' ) ),
		array_merge( array( 'Recognition' ), array_map( static function ( $level ) { return $level['name'] . ' ' . $level['short']; }, $levels ) ),
	) );

// The plugin asks while the levels are public; the answer does not depend on it.
WPCPM_Sponsor_Tiers::$live = false;
$closed = apply_filters( 'wpcpm_partner_levels', array( 'a left-over level' ) );

WPCPM_Sponsor_Tiers::$live = true;
$open = apply_filters( 'wpcpm_partner_levels', array() );

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
		array_column( $open, 'name' ) === array_column( $levels, 'name' ),
		array_column( $open, 'amount' ) === array_column( $levels, 'amount' ),
		array_column( $open, 'lead' ) === array_column( $levels, 'lead' ),
		array_column( $open, 'items' ) === array_column( $levels, 'items' ),
		array_column( $open, 'tag' ) === array_column( $levels, 'tag' ),
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

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
