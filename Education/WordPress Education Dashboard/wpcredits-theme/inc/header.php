<?php
/**
 * The site header's request-time markup.
 *
 * The header part is a template file, so what depends on the request is added to its rendered blocks
 * here: the mark that says the page runs JavaScript, the class a lone navigation item gets, and, for a signed-in reader, the Menu button below desktop width, the ID of
 * the group it opens and the script that opens it. The stylesheet and the script that act on this
 * markup name its classes and its ID; this file is the one place that prints them.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mark the page as running JavaScript, first thing in the head.
 *
 * The stylesheet lays the one-row header out only under this class, so a reader without JavaScript
 * keeps the wrapped row with every link shown rather than links behind a button that cannot open. It
 * is printed ahead of everything else in the head so the layout is right at the first paint, and for
 * every reader: it says what the page can do, not who is reading it.
 */
function wpcredits_js_mark() {
	wp_print_inline_script_tag( "document.documentElement.classList.add( 'wpc-js' );" );
}
add_action( 'wp_head', 'wpcredits_js_mark', 1 );

/**
 * Mark a navigation that holds a single item, so it is not hidden behind a toggle.
 *
 * Below 960px the header collapses its menu into a hamburger, which is the right thing for a
 * menu. This site's menu currently holds one entry, and a mentor testing on a phone reported the
 * obvious: tapping a hamburger to reveal one link looks broken. It is not worth a toggle, an
 * overlay and two taps to reach something that fits beside the logo.
 *
 * So the count is taken here, where the rendered list actually is, and the stylesheet shows a lone
 * item inline instead. Counted from the markup rather than from `$block['innerBlocks']` because a
 * navigation that stores its items in a `wp_navigation` post resolves them at render time and has
 * no inner blocks to count. The moment a second item is added the class stops being applied and
 * the hamburger comes back on its own, with nothing to remember.
 *
 * A lone item that leads to the home page is marked `wpc-nav--home` as well: on a signed-out phone
 * the site title already leads there, so the stylesheet leaves that one link out, and only
 * that one.
 *
 * @param string $content Rendered block HTML.
 * @return string
 */
function wpcredits_lone_nav_item( $content ) {
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) || false === strpos( $content, 'wpc-nav' ) ) {
		return $content;
	}

	$items = 0;
	$count = new WP_HTML_Tag_Processor( $content );

	while ( $count->next_tag( array( 'class_name' => 'wp-block-navigation-item' ) ) ) {
		++$items;

		// Two is enough to know it is a real menu; there is no need to walk a long one.
		if ( $items > 1 ) {
			return $content;
		}
	}

	if ( 1 !== $items ) {
		return $content;
	}

	$link = new WP_HTML_Tag_Processor( $content );
	$href = $link->next_tag( array( 'class_name' => 'wp-block-navigation-item__content' ) ) ? $link->get_attribute( 'href' ) : null;

	$nav = new WP_HTML_Tag_Processor( $content );

	if ( ! $nav->next_tag( array( 'tag_name' => 'NAV' ) ) ) {
		return $content;
	}

	$nav->add_class( 'wpc-nav--lone' );

	if ( untrailingslashit( (string) $href ) === untrailingslashit( home_url( '/' ) ) ) {
		$nav->add_class( 'wpc-nav--home' );
	}

	return $nav->get_updated_html();
}
add_filter( 'render_block_core/navigation', 'wpcredits_lone_nav_item' );

/**
 * How many entries the header's menu would list.
 *
 * An entry is a navigation item (a submenu's items and the submenu itself each count), the Need
 * help? button, a Student Report Card or Mentor Report Card link, or Log out; the navigation
 * block's own open and close buttons are not entries. Needs `WP_HTML_Tag_Processor`, which the
 * header filter checks first.
 *
 * @param string $html The header's rendered HTML.
 * @return int
 */
function wpcredits_header_menu_entries( $html ) {
	$entries = 0;
	$tags    = new WP_HTML_Tag_Processor( (string) $html );

	while ( $tags->next_tag() ) {
		foreach ( array( 'wp-block-navigation-item', 'wpc-ask', 'wpc-viewer__link', 'wpc-viewer__logout' ) as $class ) {
			if ( $tags->has_class( $class ) ) {
				++$entries;
				break;
			}
		}
	}

	return $entries;
}

/**
 * The Menu button: a real button that names the group it opens and says whether it is open.
 *
 * Both icons are in it and the stylesheet shows the one that fits the state. The icons escape their
 * own attributes.
 *
 * @return string
 */
function wpcredits_header_menu_button() {
	return sprintf(
		'<button type="button" class="wpc-menu-toggle" aria-controls="wpc-header-menu" aria-expanded="false" aria-label="%1$s">%2$s%3$s</button>',
		/* translators: The label of the button that opens the site menu on narrow screens. */
		esc_attr__( 'Menu', 'wpcredits-theme' ),
		wpcredits_get_icon( 'menu', 24, 'wpc-menu-toggle__open' ),
		wpcredits_get_icon( 'close', 24, 'wpc-menu-toggle__close' )
	);
}

/**
 * Give a signed-in reader's header its menu.
 *
 * On the header part's outer group, the one whose class list holds `wpc-header`: the header gets
 * `wpc-header--menu`, the group holding the links (`wpc-nav__center`) gets the ID `wpc-header-menu`,
 * and the Menu button goes in right before that group, so Tab leads from the button into it. The
 * links stay the same elements in the same order. It is the outer group's render that sees every
 * entry at once, which is why the filter is here and not on the blocks that print them.
 *
 * Nothing changes when the reader is signed out, when the group is not the header, or when the menu
 * would list fewer than two entries: a menu holding one link looks broken, and the header keeps its
 * row.
 *
 * @param string $content Rendered block HTML.
 * @param array  $block   Parsed block.
 * @return string
 */
function wpcredits_header_menu( $content, $block ) {
	$class   = is_array( $block ) && isset( $block['attrs']['className'] ) && is_string( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
	$classes = preg_split( '/\s+/', $class, -1, PREG_SPLIT_NO_EMPTY );

	if ( ! in_array( 'wpc-header', $classes, true ) || ! is_user_logged_in() || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $content;
	}

	if ( wpcredits_header_menu_entries( $content ) < 2 ) {
		return $content;
	}

	$tags = new WP_HTML_Tag_Processor( $content );

	if ( ! $tags->next_tag( array( 'tag_name' => 'HEADER' ) ) ) {
		return $content;
	}

	$tags->add_class( 'wpc-header--menu' );

	if ( ! $tags->next_tag( array( 'class_name' => 'wpc-nav__center' ) ) ) {
		return $content;
	}

	$tags->set_attribute( 'id', 'wpc-header-menu' );

	// The button goes in at the start of the group's tag. The tag processor writes a new attribute
	// into the tag, so the tag's `<` is the last one before the ID.
	$html  = $tags->get_updated_html();
	$at    = strpos( $html, 'id="wpc-header-menu"' );
	$start = false === $at ? false : strrpos( substr( $html, 0, $at ), '<' );

	return false === $start ? $content : substr_replace( $html, wpcredits_header_menu_button(), $start, 0 );
}
add_filter( 'render_block_core/group', 'wpcredits_header_menu', 10, 2 );

/**
 * The Menu button's script, for a signed-in reader.
 *
 * Only a signed-in reader's header can have the button, so no one else loads the script; on a page
 * whose header has none (one entry) it does nothing. Deferred, so it never holds up the page, and it
 * needs nothing else loaded first.
 */
function wpcredits_header_menu_script() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	wp_enqueue_script(
		'wpcredits-header-menu',
		get_theme_file_uri( 'assets/js/header-menu.js' ),
		array(),
		WPCREDITS_VERSION,
		array( 'strategy' => 'defer' )
	);
}
add_action( 'wp_enqueue_scripts', 'wpcredits_header_menu_script' );
