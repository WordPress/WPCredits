<?php
/**
 * The Education Hub homepage: its stylesheet, the rule that lets the front page be drawn by its
 * template, and the rule that keeps a page merely named after it from being drawn by it.
 *
 * The hub is a page template of its own, "Education Hub home" (`templates/page-education-hub.html`,
 * registered in theme.json), made of five patterns and three blocks. It prints no post content: the
 * page it is assigned to is only its address. The live homepage is not changed by any of this; it is
 * shown on a private preview page until the site owner switches the homepage to it.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The hub template's slug, as theme.json registers it and a page stores it. */
const WPCREDITS_HUB_TEMPLATE = 'page-education-hub';

/**
 * Register the hub's stylesheet.
 *
 * Registered rather than enqueued: the hub template enqueues it on the requests it draws, and the
 * three hub blocks name it as their block style, so it also loads wherever one of them is placed on its
 * own. After the theme's own stylesheet, whose tokens and components it builds on.
 */
function wpcredits_hub_register_style() {
	wp_register_style(
		'wpcredits-hub',
		get_theme_file_uri( 'assets/css/hub.css' ),
		// The theme's own stylesheet is registered on the front end only (functions.php); in the editor,
		// which has hub.css as an editor style, depending on it would drop this one with a notice.
		is_admin() ? array() : array( 'wpcredits-style' ),
		WPCREDITS_VERSION
	);
}
add_action( 'init', 'wpcredits_hub_register_style', 9 );

/**
 * Whether this request is a page drawn by the hub template: the preview page, or the homepage once
 * the site owner has set it to the page that uses it.
 *
 * @return bool
 */
function wpcredits_is_hub_page() {
	if ( ! is_singular( 'page' ) ) {
		return false;
	}

	return WPCREDITS_HUB_TEMPLATE === get_page_template_slug( get_queried_object_id() );
}

/**
 * Enqueue the hub's stylesheet on a page the hub template draws.
 */
function wpcredits_hub_assets() {
	if ( wpcredits_is_hub_page() ) {
		wp_enqueue_style( 'wpcredits-hub' );
	}
}
add_action( 'wp_enqueue_scripts', 'wpcredits_hub_assets', 20 );

/**
 * The hub's stylesheet in the editor too, so the Site Editor draws the hub's sections as the front
 * end does.
 */
function wpcredits_hub_editor_style() {
	add_editor_style( 'assets/css/hub.css' );
}
add_action( 'after_setup_theme', 'wpcredits_hub_editor_style', 11 );

/**
 * Let the hub template draw the front page, when the page set as the homepage uses it.
 *
 * WordPress draws the front page with the `front-page` template before it looks at the page's own
 * template, so assigning "Education Hub home" to the homepage would change nothing. When the site
 * shows a page on its front and that page uses the hub template, the template goes first; for every
 * other homepage, the live one included, the hierarchy is returned untouched, and the Site Editor's
 * saved front-page template keeps drawing it.
 *
 * @param string[] $templates The front page's template hierarchy.
 * @return string[]
 */
function wpcredits_hub_front_page( $templates ) {
	if ( 'page' !== get_option( 'show_on_front' ) ) {
		return $templates;
	}

	$front = (int) get_option( 'page_on_front' );

	if ( ! $front || WPCREDITS_HUB_TEMPLATE !== get_page_template_slug( $front ) ) {
		return $templates;
	}

	array_unshift( $templates, WPCREDITS_HUB_TEMPLATE . '.php' );

	return $templates;
}
add_filter( 'frontpage_template_hierarchy', 'wpcredits_hub_front_page' );

/**
 * Keep a page that is only called "education-hub" from being drawn by the hub template.
 *
 * `page-education-hub` is the template's name, and also the file WordPress tries for a page whose slug
 * is `education-hub`, before `page.html`. So a page made later with that slug would show the hub and
 * never its own content, and without the hub's stylesheet, which follows the stored template. The
 * slug's candidate is dropped from the page's hierarchy unless the queried page stores the hub
 * template. The template a page stores is another entry of the list (`page-education-hub`, no suffix)
 * and is not touched, nor is the front page's hierarchy, which has a rule of its own above.
 *
 * @param string[] $templates The page's template hierarchy.
 * @return string[]
 */
function wpcredits_hub_page_hierarchy( $templates ) {
	if ( wpcredits_is_hub_page() ) {
		return $templates;
	}

	return array_values( array_diff( $templates, array( WPCREDITS_HUB_TEMPLATE . '.php' ) ) );
}
add_filter( 'page_template_hierarchy', 'wpcredits_hub_page_hierarchy' );
