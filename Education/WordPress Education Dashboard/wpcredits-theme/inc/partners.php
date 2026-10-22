<?php
/**
 * The Partners page: its stylesheets, and the menu's "Become a partner" item while the partnership
 * levels are public.
 *
 * The page is the plugin's public sponsors page, at `/partners/` (plugin 1.123.18). WordPress draws a
 * page by its slug's template before `page.html`, so `templates/page-partners.html` draws it: the
 * program lead's words about the partnership levels (21 October 2026) in the Education Hub's look,
 * five patterns around the page's own content, which is the plugin's listing of the partners. The
 * plugin keeps the page a draft until the levels go public, and refuses it to visitors until then.
 *
 * The menu's "Become a partner" item leads to this page, whose own "Become a partner" section has
 * the button on to the sponsorship application (a program Administrator asked for it and the site's
 * owner agreed, 22 October 2026; until then it led to the application itself).
 *
 * The five levels' words are kept here, in `wpcredits_partner_levels()`: the page's levels pattern
 * draws its cards and its table from them, and the plugin's sponsor application shows the same words
 * in its "What each level includes" section, asked through its `wpcpm_partner_levels` filter, so the
 * two never say different things.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The Partners page's slug, the template's name after `page-`. */
const WPCREDITS_PARTNERS_SLUG = 'partners';

/**
 * Register the Partners page's stylesheet, after the hub's, whose type, heads, cards and buttons it
 * builds on.
 */
function wpcredits_partners_register_style() {
	wp_register_style(
		'wpcredits-partners',
		get_theme_file_uri( 'assets/css/partners.css' ),
		array( 'wpcredits-hub' ),
		WPCREDITS_VERSION
	);
}
add_action( 'init', 'wpcredits_partners_register_style', 10 );

/**
 * Whether this request is the Partners page.
 *
 * @return bool
 */
function wpcredits_is_partners_page() {
	return is_singular( 'page' ) && WPCREDITS_PARTNERS_SLUG === get_post_field( 'post_name', get_queried_object_id() );
}

/**
 * Enqueue the hub's stylesheet and the Partners page's on the Partners page.
 */
function wpcredits_partners_assets() {
	if ( wpcredits_is_partners_page() ) {
		wp_enqueue_style( 'wpcredits-hub' );
		wp_enqueue_style( 'wpcredits-partners' );
	}
}
add_action( 'wp_enqueue_scripts', 'wpcredits_partners_assets', 20 );

/**
 * The Partners page's stylesheet in the editor too, as the hub's is.
 */
function wpcredits_partners_editor_style() {
	add_editor_style( 'assets/css/partners.css' );
}
add_action( 'after_setup_theme', 'wpcredits_partners_editor_style', 12 );

/**
 * Whether the partnership levels are public: the plugin's launch switch, on.
 *
 * @return bool
 */
function wpcredits_partners_live() {
	return class_exists( 'WPCPM_Sponsor_Tiers' ) && method_exists( 'WPCPM_Sponsor_Tiers', 'live' ) && WPCPM_Sponsor_Tiers::live();
}

/**
 * The header menu's last item, "Become a partner", while the levels are public: a navigation link
 * hooked into the menu as its last child (the site's owner, 21 October 2026). It leads to the
 * Partners page (22 October 2026), where the "Become a partner" section has the button on to the
 * sponsorship application. While the levels are not public there is no item, so nothing points at
 * what visitors cannot see yet.
 *
 * @param string[] $hooked   The block types hooked at this position.
 * @param string   $position Where: before, after, first_child or last_child.
 * @param string   $anchor   The anchor block's type.
 * @return string[]
 */
function wpcredits_partners_menu_item( $hooked, $position, $anchor ) {
	if ( 'last_child' === $position && 'core/navigation' === $anchor && wpcredits_partners_live() ) {
		$hooked[] = 'core/navigation-link';
	}

	return $hooked;
}
add_filter( 'hooked_block_types', 'wpcredits_partners_menu_item', 10, 3 );

/**
 * The hooked item's label, address and class.
 *
 * The address is the Partners page. While the plugin has no published Partners page the item falls
 * back to the sponsorship application, and when it has neither, to the WordPress Credits handbook,
 * as every link to one of the site's own pages does (`wpcredits_hub_url()`).
 *
 * The navigation block marks a link current by a page's ID, not by its address: it matches a post
 * type archive's link by address, but a custom link to a page is never marked. So on the page the
 * item leads to it is written as a link to that page, with the page's ID, and the block marks it as
 * it does a page link, adding `current-menu-item` and `aria-current="page"`. The signed-in phone
 * panel (below 1200px) colors the marked row in the brand blue, at the item's own weight (style.css);
 * everywhere else the item looks the same, its own rules winning over the header's current-item
 * rules. On every other page it stays the custom link it was.
 *
 * @param array|null $block    The hooked block, parsed, or null when another filter removed it.
 * @param string     $type     The hooked block's type.
 * @param string     $position Where it is hooked.
 * @param array      $anchor   The anchor block, parsed.
 * @return array|null
 */
function wpcredits_partners_menu_item_block( $block, $type, $position, $anchor ) {
	if ( null === $block || 'last_child' !== $position || ! isset( $anchor['blockName'] ) || 'core/navigation' !== $anchor['blockName'] ) {
		return $block;
	}

	$url = wpcredits_hub_url( 'partners' );

	// The handbook is what `wpcredits_hub_url()` answers for a page it cannot place.
	if ( WPCREDITS_HUB_HANDBOOK === $url ) {
		$url = wpcredits_hub_url( 'sponsor_application' );
	}

	$attrs = array(
		'label'     => __( 'Become a partner', 'wpcredits-theme' ),
		'url'       => $url,
		'kind'      => 'custom',
		'className' => 'wpc-nav__partner',
	);

	$viewed = is_singular( 'page' ) ? get_queried_object_id() : 0;

	if ( $viewed && untrailingslashit( (string) get_permalink( $viewed ) ) === untrailingslashit( $url ) ) {
		$attrs['kind'] = 'post-type';
		$attrs['type'] = 'page';
		$attrs['id']   = $viewed;
	}

	$block['attrs'] = $attrs;

	return $block;
}
add_filter( 'hooked_block_core/navigation-link', 'wpcredits_partners_menu_item_block', 10, 4 );

/**
 * The five partnership levels in a program lead's words (21 October 2026), the lowest first.
 *
 * Kept in a function, and not in the pattern that draws them, because two places read them: the
 * Partners page's levels pattern (`patterns/partners-levels.php`), for its cards and its comparison
 * table, and the plugin's sponsor application, through `wpcredits_partners_levels_for_form()`. The
 * texts are looked up when this is called, never when the file loads, since translations are not
 * ready until `init`. The amounts are the program's own, text on the page; the site handles no
 * payment.
 *
 * @return array[] Each level: `name`, `amount` (what it costs, as the cards say it), `short` (the
 *                 amount in the comparison table's head), `lead` (the line before its list, or what
 *                 the level funds), `items` (what it adds, a list of strings) and, for the Partner
 *                 level alone, `tag`.
 */
function wpcredits_partner_levels() {
	return array(
		array(
			'name'   => __( 'Friend', 'wpcredits-theme' ),
			'amount' => __( 'In-kind, through Five for the Future', 'wpcredits-theme' ),
			/* translators: A partnership level's amount in the comparison table's head, short: given in kind, not paid. */
			'short'  => __( 'In-kind', 'wpcredits-theme' ),
			'lead'   => __( 'For companies sponsoring mentors through Five for the Future.', 'wpcredits-theme' ),
			'items'  => array(
				__( 'Company name on the partner page', 'wpcredits-theme' ),
				__( 'Mention in the mentor feature', 'wpcredits-theme' ),
				__( 'Acknowledgement in the annual partner thank you post', 'wpcredits-theme' ),
			),
		),
		array(
			'name'   => __( 'Supporter', 'wpcredits-theme' ),
			/* translators: A partnership level's amount, in US dollars a year. */
			'amount' => __( '$5,000 a year', 'wpcredits-theme' ),
			/* translators: A partnership level's amount in the comparison table's head, in US dollars. */
			'short'  => __( '$5,000', 'wpcredits-theme' ),
			'lead'   => __( 'Everything in Friend, plus:', 'wpcredits-theme' ),
			'items'  => array(
				__( 'Logo and description on the partner page', 'wpcredits-theme' ),
				__( 'A badge for your own site', 'wpcredits-theme' ),
				__( 'The annual program report', 'wpcredits-theme' ),
			),
		),
		array(
			'name'   => __( 'Backer', 'wpcredits-theme' ),
			/* translators: A partnership level's amount, in US dollars a year. */
			'amount' => __( '$10,000 a year', 'wpcredits-theme' ),
			/* translators: A partnership level's amount in the comparison table's head, in US dollars. */
			'short'  => __( '$10,000', 'wpcredits-theme' ),
			'lead'   => __( 'Everything in Supporter, plus:', 'wpcredits-theme' ),
			'items'  => array(
				__( 'Named for the part of the program you fund in program communications', 'wpcredits-theme' ),
				__( 'A report covering that cost line', 'wpcredits-theme' ),
				__( 'One self-written educational post about the sponsored product', 'wpcredits-theme' ),
			),
		),
		array(
			'name'   => __( 'Champion', 'wpcredits-theme' ),
			/* translators: A partnership level's amount, in US dollars a year. */
			'amount' => __( '$15,000 a year', 'wpcredits-theme' ),
			/* translators: A partnership level's amount in the comparison table's head, in US dollars. */
			'short'  => __( '$15,000', 'wpcredits-theme' ),
			'lead'   => __( 'Everything in Backer, plus:', 'wpcredits-theme' ),
			'items'  => array(
				__( 'A second self-written post with factual, value-neutral information about additional products', 'wpcredits-theme' ),
				__( 'An invitation to the annual education partners session', 'wpcredits-theme' ),
				__( 'A webinar or event co-hosted with the education program', 'wpcredits-theme' ),
			),
		),
		array(
			'name'   => _x( 'Partner', 'the highest partnership level, a noun: a company that funds a named program', 'wpcredits-theme' ),
			/* translators: A partnership level's amount, in US dollars a year, the least it starts from. */
			'amount' => __( 'From $30,000 a year', 'wpcredits-theme' ),
			/* translators: A partnership level's amount in the comparison table's head, in US dollars, the least it starts from. */
			'short'  => __( 'From $30,000', 'wpcredits-theme' ),
			'tag'    => __( 'Funds a named program', 'wpcredits-theme' ),
			'lead'   => __( 'Everything in Champion, plus a complete program funded end to end, the kind we have not been able to build yet because we have always had to work within the budget available. Students translating WordPress into languages with thin coverage, students working on accessibility, students building sites for nonprofit organizations that could not otherwise commission them.', 'wpcredits-theme' ),
			'items'  => array(
				__( 'Named as program partner on the program you fund, with the exclusive role in that program', 'wpcredits-theme' ),
				__( 'Named in launch communications', 'wpcredits-theme' ),
				__( 'A report specific to that program', 'wpcredits-theme' ),
			),
		),
	);
}

/**
 * The levels the plugin's sponsor application shows in its "What each level includes" section: the
 * Partners page's own, so a company reading the form is told what the page told it.
 *
 * The plugin defines `wpcpm_partner_levels` and asks it while the levels are public, passing an empty
 * list; what is returned replaces it, so the filter's argument is not read. The answer does not
 * depend on whether the levels are public, since the plugin only asks while they are. The contract,
 * as the plugin defines it: a list of levels in order, each with `name` (string), `amount` (string),
 * `lead` (string), `items` (a list of strings) and, optionally, `tag` (string); the plugin skips a
 * malformed entry and escapes everything it prints. The comparison table's `short` is left out, the
 * form has no use for it.
 *
 * @param array $levels What the filter received: the plugin passes an empty list. Not read.
 * @return array[] Each level: `name`, `amount`, `lead`, `items` and, for the Partner level alone, `tag`.
 */
function wpcredits_partners_levels_for_form( $levels ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- replaced, see the docblock.
	$for_form = array();

	foreach ( wpcredits_partner_levels() as $level ) {
		$entry = array(
			'name'   => $level['name'],
			'amount' => $level['amount'],
			'lead'   => $level['lead'],
			'items'  => array_values( $level['items'] ),
		);

		if ( isset( $level['tag'] ) ) {
			$entry['tag'] = $level['tag'];
		}

		$for_form[] = $entry;
	}

	return $for_form;
}
add_filter( 'wpcpm_partner_levels', 'wpcredits_partners_levels_for_form' );
