<?php
/**
 * The header menu's own links (theme 1.32.20, the product owner's choice of 10 November 2026).
 *
 * The bar reads: the site's name, Students, Mentors, Institutions (for a visitor), Impact, and then
 * what was there (the Partners link while it is public, Get involved, Log in, or a signed-in reader's
 * own links). Students and Mentors lead
 * to their landing pages, Institutions to the institution application, and Impact opens two rows, the
 * Student impact page and the program in numbers. The forms stay in Get involved.
 *
 * The links are the navigation block's own blocks, put first among its items as it is rendered
 * (`wpcredits_site_menu_navigation()`), so core draws them, the submenu with its button and its keyboard
 * handling, a phone's open menu lists them, and the page being viewed is marked. A link is offered only
 * while its page is published, so a site without one of the pages has no dead link, and a menu with no
 * page to lead to is left as it was.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The published page at a path, or null: the pages the menu leads to are found by the address the
 * design names for each.
 *
 * @param string $path The page's path, as `students`.
 * @return WP_Post|null
 */
function wpcredits_site_menu_page( $path ) {
	$page = get_page_by_path( (string) $path );

	return ( $page instanceof WP_Post && 'publish' === $page->post_status ) ? $page : null;
}

/**
 * The menu's entries, in the bar's order. Each is `key`, `label` and either `page` (a published page)
 * or `url`, or `rows`, the entries a submenu opens. An entry with nowhere to lead is left out, and a
 * submenu with no row is too.
 *
 * @return array[]
 */
function wpcredits_site_menu_entries() {
	$entries = array();

	$pages = array(
		'students' => __( 'Students', 'wpcredits-theme' ),
		'mentors'  => __( 'Mentors', 'wpcredits-theme' ),
	);

	foreach ( $pages as $path => $label ) {
		$page = wpcredits_site_menu_page( $path );

		if ( null !== $page ) {
			$entries[] = array(
				'key'   => $path,
				'label' => $label,
				'page'  => $page,
			);
		}
	}

	// The institution application, while the plugin's page is published: `page_url()` answers '' otherwise.
	// For a visitor alone: a signed-in reader's bar also carries their own links (Need help?, the report
	// cards, Log out), and with a fourth link here an Administrator's bar no longer fits one line at
	// 1200px, where it starts. An institution's form stays in their Get involved list.
	$form        = 'WPCPM_Institution_Application';
	$institution = ( ! is_user_logged_in() && class_exists( $form ) && method_exists( $form, 'page_url' ) ) ? (string) call_user_func( array( $form, 'page_url' ) ) : '';

	if ( '' !== $institution ) {
		$entries[] = array(
			'key'   => 'institutions',
			'label' => __( 'Institutions', 'wpcredits-theme' ),
			'url'   => $institution,
		);
	}

	$rows   = array();
	$impact = array(
		'student-impact' => __( 'Student impact', 'wpcredits-theme' ),
		'stats'          => __( 'The program in numbers', 'wpcredits-theme' ),
	);

	foreach ( $impact as $path => $label ) {
		$page = wpcredits_site_menu_page( $path );

		if ( null !== $page ) {
			$rows[] = array(
				'key'   => $path,
				'label' => $label,
				'page'  => $page,
			);
		}
	}

	if ( $rows ) {
		$entries[] = array(
			'key'   => 'impact',
			'label' => __( 'Impact', 'wpcredits-theme' ),
			'rows'  => $rows,
		);
	}

	return $entries;
}

/**
 * One entry as a parsed navigation link. A page is named by its ID, so core marks it as the page being
 * viewed and leaves it out should it stop being published between this and the render.
 *
 * @param array $entry As `wpcredits_site_menu_entries()` gives one.
 * @return array
 */
function wpcredits_site_menu_link( array $entry ) {
	$attrs = array(
		'label'     => (string) $entry['label'],
		'className' => 'wpc-nav__site wpc-nav__site--' . sanitize_html_class( (string) $entry['key'] ),
	);

	if ( isset( $entry['page'] ) && $entry['page'] instanceof WP_Post ) {
		$attrs['type'] = 'page';
		$attrs['kind'] = 'post-type';
		$attrs['id']   = (int) $entry['page']->ID;
		$attrs['url']  = (string) get_permalink( $entry['page'] );
	} else {
		$attrs['kind'] = 'custom';
		$attrs['url']  = (string) $entry['url'];
	}

	return array(
		'blockName'    => 'core/navigation-link',
		'attrs'        => $attrs,
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	);
}

/**
 * The entries as parsed blocks: a link each, and a submenu for one that opens rows.
 *
 * @return array[]
 */
function wpcredits_site_menu_blocks() {
	$blocks = array();

	foreach ( wpcredits_site_menu_entries() as $entry ) {
		if ( empty( $entry['rows'] ) ) {
			$blocks[] = wpcredits_site_menu_link( $entry );
			continue;
		}

		$rows = array_map( 'wpcredits_site_menu_link', $entry['rows'] );

		$blocks[] = array(
			'blockName'    => 'core/navigation-submenu',
			'attrs'        => array(
				'label'     => (string) $entry['label'],
				'className' => 'wpc-nav__site wpc-nav__site--' . sanitize_html_class( (string) $entry['key'] ),
			),
			'innerBlocks'  => $rows,
			'innerHTML'    => '',
			'innerContent' => array_fill( 0, count( $rows ), null ),
		);
	}

	return $blocks;
}

/**
 * Put the menu's links first among the items of a navigation that stores none of its own, before the
 * items of the site's saved menu and what is hooked at its end (the Partners link, Get involved).
 *
 * The header's navigation (parts/header.html) names no menu and holds no item, so core draws the
 * site's saved menu in it, with the hooked blocks, and hands the parsed blocks here on the way. That is
 * the one place the links are added: a navigation that names a menu or holds its own items is drawn
 * as it is, and the editor, which reads a menu over the REST API, is never handed them.
 *
 * @param array[] $blocks The parsed blocks core is about to draw in the navigation.
 * @return array[]
 */
function wpcredits_site_menu_navigation( $blocks ) {
	if ( ! is_array( $blocks ) ) {
		return $blocks;
	}

	return array_merge( wpcredits_site_menu_blocks(), $blocks );
}
add_filter( 'block_core_navigation_render_fallback', 'wpcredits_site_menu_navigation' );
