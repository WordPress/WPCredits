<?php
/**
 * The markup the site header's blocks render, for the header's suites.
 *
 * bin/test-header.php runs the header's filters over these, and bin/test-header-js.php renders the
 * header's script pages from them. Their shapes are copied from a real render of the header part on
 * WordPress 7.1: core's navigation with its open and close buttons, responsive container and dialog,
 * a submenu opened on click, the header part's groups, the handbook launcher's button and the viewer
 * chip's links, Log out and picture, or a guest's Log in. Nothing here is translated: a check that
 * turns the stand-in's translated strings into brackets sees only what the theme's own filters print.
 *
 * Loaded with `require_once __DIR__ . '/stubs/header-markup.php';` after bin/stubs/hub-wp.php and the
 * theme's inc/header.php, which draws the logo and marks a lone navigation item.
 *
 * @package WPCredits_Theme
 */

/**
 * One navigation item, or a submenu opened on click, as WordPress 7.1 prints it.
 *
 * @param array $item The item: `array( label, address )`, or `array( label, address, children )` for a
 *                    submenu, whose children are items in the same shape.
 * @return string
 */
function wpcredits_test_navigation_item( array $item ) {
	$label = '<span class="wp-block-navigation-item__label">' . esc_html( $item[0] ) . '</span>';

	if ( empty( $item[2] ) ) {
		return '<li class="wp-block-navigation-item wp-block-navigation-link"><a class="wp-block-navigation-item__content"  href="' . esc_url( $item[1] ) . '">' . $label . '</a></li>';
	}

	$children = '';

	foreach ( $item[2] as $child ) {
		$children .= wpcredits_test_navigation_item( $child );
	}

	return '<li data-wp-context="{ &quot;submenuOpenedBy&quot;: { &quot;click&quot;: false, &quot;hover&quot;: false, &quot;focus&quot;: false }, &quot;type&quot;: &quot;submenu&quot;, &quot;modal&quot;: null, &quot;previousFocus&quot;: null }" data-wp-interactive="core/navigation" data-wp-on--focusout="actions.handleMenuFocusout" data-wp-on--keydown="actions.handleMenuKeydown" data-wp-watch="callbacks.initMenu" tabindex="-1" class="wp-block-navigation-item has-child open-on-click wp-block-navigation-submenu">'
		. '<button data-wp-bind--aria-expanded="state.isSubmenuOpen" data-wp-on--click="actions.toggleMenuOnClick" aria-label="' . esc_attr( $item[0] ) . ' submenu" class="wp-block-navigation-item__content wp-block-navigation-submenu__toggle" aria-expanded="false">' . $label . '</button>'
		. '<span class="wp-block-navigation__submenu-icon"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" focusable="false"><path d="M1.50002 4L6.00002 8L10.5 4" stroke-width="1.5"></path></svg></span>'
		. '<ul data-wp-on--focus="actions.openMenuOnFocus" class="wp-block-navigation__submenu-container wp-block-navigation-submenu">' . $children . '</ul></li>';
}

/**
 * The navigation block as core renders it in the header part, before the theme's filter: the `nav`
 * with its open button, the responsive container, the dialog with its close button, and the list.
 * A navigation with no items is not rendered at all, as core renders one.
 *
 * @param array[] $items The items, each `array( label, address )`, or `array( label, address,
 *                       children )` for a submenu opened on click.
 * @return string
 */
function wpcredits_test_navigation( array $items ) {
	if ( ! $items ) {
		return '';
	}

	$list = '';

	foreach ( $items as $item ) {
		$list .= wpcredits_test_navigation_item( $item );
	}

	return '<nav class="is-responsive items-justified-center wpc-nav wp-block-navigation is-content-justification-center is-layout-flex wp-container-core-navigation-is-layout-1b6d916d wp-block-navigation-is-layout-flex"
		 data-wp-interactive="core/navigation" data-wp-context=\'{"overlayOpenedBy":{"click":false,"hover":false,"focus":false},"type":"overlay","roleAttribute":"","ariaLabel":"Menu"}\'><button aria-haspopup="dialog" aria-label="Open menu" class="wp-block-navigation__responsive-container-open"
				data-wp-on--click="actions.openMenuOnClick"
				data-wp-on--keydown="actions.handleMenuKeydown"
			><svg width="24" height="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5 5v1.5h14V5H5z"></path><path d="M5 12.8h14v-1.5H5v1.5z"></path><path d="M5 19h14v-1.5H5V19z"></path></svg></button>
				<div class="wp-block-navigation__responsive-container"  id="modal-5"
				data-wp-class--has-modal-open="state.isMenuOpen"
				data-wp-class--is-menu-open="state.isMenuOpen"
				data-wp-watch="callbacks.initMenu"
				data-wp-on--keydown="actions.handleMenuKeydown"
				data-wp-on--focusout="actions.handleMenuFocusout"
				tabindex="-1"
			>
					<div class="wp-block-navigation__responsive-close" tabindex="-1">
						<div class="wp-block-navigation__responsive-dialog"
				data-wp-bind--aria-modal="state.ariaModal"
				data-wp-bind--aria-label="state.ariaLabel"
				data-wp-bind--role="state.roleAttribute"
			>
							<button aria-label="Close menu" class="wp-block-navigation__responsive-container-close"
				data-wp-on--click="actions.closeMenuOnClick"
			><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="m13.06 12 6.47-6.47-1.06-1.06L12 10.94 5.53 4.47 4.47 5.53 10.94 12l-6.47 6.47 1.06 1.06L12 13.06l6.47 6.47 1.06-1.06L13.06 12Z"></path></svg></button>
							<div class="wp-block-navigation__responsive-container-content"
				data-wp-watch="callbacks.focusFirstElement"
			 id="modal-5-content">
								<ul class="wp-block-navigation__container is-responsive items-justified-center wpc-nav wp-block-navigation">' . $list . '</ul>

							</div>
						</div>
					</div>
				</div></nav>';
}

/**
 * The navigation both header suites use for a menu with a submenu: three items, the third a submenu of
 * two opened on click.
 *
 * @return array[] The items, as wpcredits_test_navigation() takes them.
 */
function wpcredits_test_three_with_submenu() {
	return array(
		array( 'Homepage', 'https://site.example/' ),
		array( 'About the program', 'https://site.example/about/' ),
		array(
			'Programs',
			'',
			array(
				array( 'WordPress Credits', 'https://site.example/credits/' ),
				array( 'Campus Connect', 'https://site.example/campus/' ),
			),
		),
	);
}

/**
 * One of the viewer chip's report card links, as the block prints it.
 *
 * @param string $label   The link's text.
 * @param string $address Where it leads.
 * @param bool   $current Whether it is the page being viewed.
 * @return string
 */
function wpcredits_test_viewer_link( $label, $address, $current ) {
	return "\t\t\t<a\n\t\t\tclass=\"wpc-viewer__link" . ( $current ? ' is-current' : '' ) . "\"\n\t\t\thref=\"" . esc_url( $address ) . "\"\n\t\t\t" . ( $current ? 'aria-current="page"' : '' ) . "\n\t\t>\n\t\t\t" . esc_html( $label ) . "\t\t</a>\n\t\n";
}

/**
 * The site header as `render_block_core/group` receives it for the header part's outer group: the
 * `header` element around the inner group, which holds the brand (the logo the theme draws, and the
 * site title) and the group of links (the navigation, the Need help? button and the viewer chip).
 *
 * @param array $reader Who is reading and what the header holds, each key optional:
 *                      - `signed_in` (true): a signed-in reader's chip, or a guest's Log in;
 *                      - `nav` (Homepage): the navigation's items, as wpcredits_test_navigation() takes
 *                        them; none leaves the navigation out;
 *                      - `help` (true): the Need help? button, for a signed-in reader only, as the
 *                        launcher prints nothing for anyone else;
 *                      - `student` and `mentor` (true): the report card links, for a signed-in reader;
 *                      - `current` (''): `student` or `mentor` for the link that is the page being
 *                        viewed, marked `is-current` and `aria-current="page"`.
 * @return string
 */
function wpcredits_test_header( array $reader = array() ) {
	$reader = array_merge(
		array(
			'signed_in' => true,
			'nav'       => array( array( 'Homepage', 'https://site.example/' ) ),
			'help'      => true,
			'student'   => true,
			'mentor'    => true,
			'current'   => '',
		),
		$reader
	);

	$nav  = wpcredits_lone_nav_item( wpcredits_test_navigation( $reader['nav'] ) );
	$logo = wpcredits_site_logo_fallback( '' );
	$ask  = $reader['signed_in'] && $reader['help'] ? '<button type="button" class="wpc-ask wp-block-wpcredits-handbook-launcher" data-wpcpm-handbook-open>Need help?</button>' : '';

	if ( $reader['signed_in'] ) {
		$chip = '<div class="wpc-viewer wp-block-wpcredits-viewer-chip">' . "\n"
			. ( $reader['student'] ? wpcredits_test_viewer_link( 'Student Report Card', 'https://site.example/student-dashboard/', 'student' === $reader['current'] ) : '' )
			. ( $reader['mentor'] ? wpcredits_test_viewer_link( 'Mentor Report Card', 'https://site.example/mentor-dashboard/', 'mentor' === $reader['current'] ) : '' )
			. "\t" . '<a class="wpc-viewer__logout" href="https://site.example/wp-login.php?action=logout&#038;redirect_to=https%3A%2F%2Fsite.example%2F&#038;_wpnonce=76b915537c">' . "\n\t\tLog out\t</a>\n\n"
			. "\t" . '<span class="wpc-viewer__avatar" data-initials="LA">' . "\n\t\t\t" . '</span>' . "\n</div>";
	} else {
		$chip = '<div class="wpc-viewer wpc-viewer--guest wp-block-wpcredits-viewer-chip"><a class="wpc-viewer__signin" href="https://site.example/wp-login.php">Log in</a></div>';
	}

	return '<header class="wp-block-group alignfull wpc-header is-layout-flow wp-block-group-is-layout-flow">
<div class="wp-block-group wpc-header__inner is-nowrap is-layout-flex wp-container-core-group-is-layout-38348eec wp-block-group-is-layout-flex">
<div class="wp-block-group wpc-brand is-nowrap is-layout-flex wp-container-core-group-is-layout-38348eec wp-block-group-is-layout-flex">' . $logo . '

<p class="wpc-brand__name wp-block-site-title"><a href="https://site.example" target="_self" rel="home">WordPress <em>Education</em> <i>Dashboard</i></a></p></div>



<div class="wp-block-group wpc-nav__center is-content-justification-center is-layout-flex wp-container-core-group-is-layout-178b3da6 wp-block-group-is-layout-flex">' . $nav . '

' . $ask . '

' . $chip . '
</div>
</div>
</header>';
}
