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

/** The plugin's sponsorship application and launch switch, as the plugin answers them. */
class WPCPM_Sponsor_Application {
	public static function page_url() { return 'https://site.example/sponsor-application/'; }
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

WPCPM_Sponsor_Tiers::$live = false;
$off = wpcredits_partners_menu_item( array(), 'last_child', 'core/navigation' );

WPCPM_Sponsor_Tiers::$live = true;
$on       = wpcredits_partners_menu_item( array(), 'last_child', 'core/navigation' );
$elsewhere = array( wpcredits_partners_menu_item( array(), 'after', 'core/navigation' ), wpcredits_partners_menu_item( array(), 'last_child', 'core/group' ) );
$item     = wpcredits_partners_menu_item_block( array( 'blockName' => 'core/navigation-link', 'attrs' => array() ), 'core/navigation-link', 'last_child', $anchor );
$left     = wpcredits_partners_menu_item_block( null, 'core/navigation-link', 'last_child', $anchor );
WPCPM_Sponsor_Tiers::$live = false;

ck( 'while the levels are not public the menu gains nothing; once they are, its last item is a link, "Become a partner", to the sponsorship application, and nowhere else is anything hooked',
	array( $off, $on, $elsewhere, $item['attrs'], $left ),
	array(
		array(),
		array( 'core/navigation-link' ),
		array( array(), array() ),
		array( 'label' => 'Become a partner', 'url' => 'https://site.example/sponsor-application/', 'kind' => 'custom', 'className' => 'wpc-nav__partner' ),
		null,
	) );

ck( 'the theme loads the Partners page\'s code',
	substr_count( (string) file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' ), "require_once get_theme_file_path( 'inc/partners.php' );" ), 1 );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
