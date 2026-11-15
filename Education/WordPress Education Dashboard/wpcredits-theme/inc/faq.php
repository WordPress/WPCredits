<?php
/**
 * The FAQ page: its stylesheet and its script, loaded only on the page at the path `faq`.
 *
 * The page is an ordinary page on the Landing page template (`page-landing`), written in core blocks
 * so Administrators edit its questions in the editor: a head, the two steps to get connected (a
 * WordPress.org account, then the Making WordPress Slack), the questions in groups, and where to ask.
 * Each question is a core Details block inside a Group with the class `wpc-faq__list`, and each has an
 * HTML anchor, so the program team can answer a question with a link to it.
 *
 * The sheet (`assets/css/faq.css`) dresses what the hub's sheet has no part for: the page's title, the
 * line of jump links, the numbered steps in their cards and the list of questions. Every rule is
 * scoped to the page's own `wpc-faq*` classes, so no other page changes, in the editor either. The
 * script (`assets/js/faq.js`) opens the question an address's fragment names, on load and when the
 * fragment changes, and brings it into view below the sticky header. Without it the page still works:
 * the fragment scrolls to the closed question, and a press on it opens it.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The FAQ page's path, as the header menu and the loading rule look it up. */
const WPCREDITS_FAQ_SLUG = 'faq';

/**
 * Register the FAQ page's stylesheet, after the hub's, whose type, heads, cards and buttons it builds
 * on, and its script.
 */
function wpcredits_faq_register_assets() {
	wp_register_style(
		'wpcredits-faq',
		get_theme_file_uri( 'assets/css/faq.css' ),
		array( 'wpcredits-hub' ),
		WPCREDITS_VERSION
	);

	wp_register_script(
		'wpcredits-faq',
		get_theme_file_uri( 'assets/js/faq.js' ),
		array(),
		WPCREDITS_VERSION,
		array( 'strategy' => 'defer' )
	);
}
add_action( 'init', 'wpcredits_faq_register_assets', 10 );

/**
 * Whether this request is the FAQ page: the top-level page at the path `faq`, the page the header menu
 * finds with `get_page_by_path()`. A page's whole path is compared, not its slug, so a child page that is
 * also called `faq` (`students/faq`) is not taken for it.
 *
 * @return bool
 */
function wpcredits_is_faq_page() {
	return is_singular( 'page' ) && WPCREDITS_FAQ_SLUG === get_page_uri( get_queried_object_id() );
}

/**
 * Enqueue the hub's stylesheet, the FAQ page's and its script on the FAQ page, whatever template the
 * page uses, as the Partners page's are.
 */
function wpcredits_faq_assets() {
	if ( wpcredits_is_faq_page() ) {
		wp_enqueue_style( 'wpcredits-hub' );
		wp_enqueue_style( 'wpcredits-faq' );
		wp_enqueue_script( 'wpcredits-faq' );
	}
}
add_action( 'wp_enqueue_scripts', 'wpcredits_faq_assets', 20 );

/**
 * The FAQ page's stylesheet in the editor too, so the questions look there as they do on the page.
 * Core gives an editor style to every post the editor opens, not to one page: it changes nothing
 * elsewhere because every rule names one of the page's own `wpc-faq*` classes.
 */
function wpcredits_faq_editor_style() {
	add_editor_style( 'assets/css/faq.css' );
}
add_action( 'after_setup_theme', 'wpcredits_faq_editor_style', 13 );
