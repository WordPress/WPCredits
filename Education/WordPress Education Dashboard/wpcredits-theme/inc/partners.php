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
 * hooked into the menu as its last child (the owner, 21 October 2026). It leads to the sponsorship
 * application. While the levels are not public there is no item, so nothing points at what visitors
 * cannot see yet.
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

	$block['attrs'] = array(
		'label'     => __( 'Become a partner', 'wpcredits-theme' ),
		'url'       => wpcredits_hub_url( 'sponsor_application' ),
		'kind'      => 'custom',
		'className' => 'wpc-nav__partner',
	);

	return $block;
}
add_filter( 'hooked_block_core/navigation-link', 'wpcredits_partners_menu_item_block', 10, 4 );
