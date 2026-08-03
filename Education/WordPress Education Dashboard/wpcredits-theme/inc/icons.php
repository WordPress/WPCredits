<?php
/**
 * The theme's icon set.
 *
 * Inlined rather than referenced from a sprite file with `<use href="…#id">`.
 * A cross-document `<use>` costs a second request before anything paints, and
 * styling its shadow content relies on inheritance quirks that differ between
 * engines — which matters here because every icon takes its color from the
 * element around it.
 *
 * Paths are the WordPress `@wordpress/icons` set, all on a 24×24 grid.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The icon registry.
 *
 * @return array<string,string> Icon name => path data.
 */
function wpcredits_icon_paths() {
	static $icons = null;

	if ( null !== $icons ) {
		return $icons;
	}

	$icons = array(
		'people'        => 'M15.5 9.5a1 1 0 100-2 1 1 0 000 2zm0 1.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-2.25 6v-2a2.75 2.75 0 00-2.75-2.75h-4A2.75 2.75 0 003.75 15v2h1.5v-2c0-.69.56-1.25 1.25-1.25h4c.69 0 1.25.56 1.25 1.25v2h1.5zm7-2v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H15v-1.5h2.5A2.75 2.75 0 0120.25 15zM9.5 8.5a1 1 0 11-2 0 1 1 0 012 0zm1.5 0a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z',
		'comment'       => 'M18 4H6c-1.1 0-2 .9-2 2v12.9c0 .6.5 1.1 1.1 1.1.3 0 .5-.1.8-.3L8.5 17H18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm.5 11c0 .3-.2.5-.5.5H7.9l-2.4 2.4V6c0-.3.2-.5.5-.5h12c.3 0 .5.2.5.5v9z',
		'home'          => 'M12 4L4 7.9V20h16V7.9L12 4zm6.5 14.5H14V13h-4v5.5H5.5V8.8L12 5.7l6.5 3.1v9.7z',
		'cog'           => 'M10.289 4.836A1 1 0 0111.275 4h1.306a1 1 0 01.987.836l.244 1.466c.787.26 1.503.679 2.108 1.218l1.393-.522a1 1 0 011.216.437l.653 1.13a1 1 0 01-.23 1.273l-1.148.944a6.025 6.025 0 010 2.435l1.149.946a1 1 0 01.23 1.272l-.653 1.13a1 1 0 01-1.216.437l-1.394-.522c-.605.54-1.32.958-2.108 1.218l-.244 1.466a1 1 0 01-.987.836h-1.306a1 1 0 01-.986-.836l-.244-1.466a5.995 5.995 0 01-2.108-1.218l-1.394.522a1 1 0 01-1.217-.436l-.653-1.131a1 1 0 01.23-1.272l1.149-.946a6.026 6.026 0 010-2.435l-1.148-.944a1 1 0 01-.23-1.272l.653-1.131a1 1 0 011.217-.437l1.393.522a5.994 5.994 0 012.108-1.218l.244-1.466zM14.929 12a3 3 0 11-6 0 3 3 0 016 0z',
		'image'         => 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM5 4.5h14c.3 0 .5.2.5.5v8.4l-3-2.9c-.3-.3-.8-.3-1 0L11.9 14 9 12c-.3-.2-.6-.2-.8 0l-3.6 2.6V5c-.1-.3.1-.5.4-.5zm14 15H5c-.3 0-.5-.2-.5-.5v-2.4l4.1-3 3 1.9c.3.2.7.2.9-.1L16 12l3.5 3.4V19c0 .3-.2.5-.5.5z',
		'lock'          => 'M17 10h-1.2V7c0-2.1-1.7-3.8-3.8-3.8-2.1 0-3.8 1.7-3.8 3.8v3H7c-.6 0-1 .4-1 1v8c0 .6.4 1 1 1h10c.6 0 1-.4 1-1v-8c0-.6-.4-1-1-1zm-2.8 0H9.8V7c0-1.2 1-2.2 2.2-2.2s2.2 1 2.2 2.2v3z',
		'bell'          => 'M17 11.5c0 1.353.17 2.368.976 3 .266.209.602.376 1.024.5v1H5v-1c.422-.124.757-.291 1.024-.5.806-.632.976-1.647.976-3V9c0-2.8 2.2-5 5-5s5 2.2 5 5v2.5ZM15.5 9v2.5c0 .93.066 1.98.515 2.897l.053.103H7.932a4.018 4.018 0 0 0 .053-.103c.449-.917.515-1.967.515-2.897V9c0-1.972 1.528-3.5 3.5-3.5s3.5 1.528 3.5 3.5Zm-5 9.008c0-.176.023-.346.065-.508h3.854A1.996 1.996 0 0 1 12 20c-1.1 0-1.992-.892-1.992-1.992Z',
		'check'         => 'M16.5 7.5 10 13.9l-2.5-2.4-1 1 3.5 3.6 7.5-7.6z',
		'external'      => 'M19.5 4.5h-7V6h4.44l-5.97 5.97 1.06 1.06L18 7.06v4.44h1.5v-7Zm-13 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3H17v3a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h3V5.5h-3Z',
		'menu'          => 'M5 5v1.5h14V5H5zm0 7.8h14v-1.5H5v1.5zM5 19h14v-1.5H5V19z',
		'search'        => 'M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.8 3.8 1.1 1.1 3.8-3.8c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6S16.3 5 13 5zm0 10.5c-2.5 0-4.5-2-4.5-4.5s2-4.5 4.5-4.5 4.5 2 4.5 4.5-2 4.5-4.5 4.5z',
		'close'         => 'm13.06 12 6.47-6.47-1.06-1.06L12 10.94 5.53 4.47 4.47 5.53 10.94 12l-6.47 6.47 1.06 1.06L12 13.06l6.47 6.47 1.06-1.06L13.06 12Z',
		'chevron-down'  => 'M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z',
		'chevron-right' => 'M10.6 6L9.4 7l4.6 5-4.6 5 1.2 1 5.4-6z',
		'page'          => 'M15.5 7.5h-7V9h7V7.5Zm-7 3.5h7v1.5h-7V11Zm7 3.5h-7V16h7v-1.5Z M17 4H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2ZM7 5.5h10a.5.5 0 0 1 .5.5v12a.5.5 0 0 1-.5.5H7a.5.5 0 0 1-.5-.5V6a.5.5 0 0 1 .5-.5Z',
	);

	return $icons;
}

/**
 * Icons whose paths cut holes in themselves — the ring of a cog, the bowl of a
 * bell — and so need the even-odd fill rule. Without it they render as blobs.
 *
 * Listed rather than applied to everything: even-odd changes what overlapping
 * subpaths look like, and several of these glyphs rely on the default.
 *
 * @return string[]
 */
function wpcredits_evenodd_icons() {
	return array( 'cog', 'bell', 'trash', 'people' );
}

/**
 * Return one icon as inline SVG.
 *
 * @param string $name Icon name from the registry.
 * @param int    $size Pixel size for both dimensions.
 * @param string $classes Optional extra classes.
 * @return string Empty string for an unknown name.
 */
function wpcredits_get_icon( $name, $size = 20, $classes = '' ) {
	$icons = wpcredits_icon_paths();

	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}

	$rule = in_array( $name, wpcredits_evenodd_icons(), true )
		? ' fill-rule="evenodd" clip-rule="evenodd"'
		: '';

	return sprintf(
		'<svg class="wpc-icon%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="%3$s"%4$s /></svg>',
		$classes ? ' ' . esc_attr( $classes ) : '',
		(int) $size,
		esc_attr( $icons[ $name ] ),
		$rule
	);
}

/**
 * Print one icon.
 *
 * @param string $name  Icon name.
 * @param int    $size  Pixel size.
 * @param string $classes Optional extra classes.
 */
function wpcredits_icon( $name, $size = 20, $classes = '' ) {
	echo wp_kses( wpcredits_get_icon( $name, $size, $classes ), wpcredits_svg_allowed_html() );
}

/**
 * The SVG subset the icon and logo helpers are allowed to output.
 *
 * @return array
 */
function wpcredits_svg_allowed_html() {
	return array(
		'svg'  => array(
			'class'       => true,
			'width'       => true,
			'height'      => true,
			'viewbox'     => true,
			'fill'        => true,
			'aria-hidden' => true,
			'aria-label'  => true,
			'role'        => true,
			'focusable'   => true,
		),
		'path' => array(
			'd'         => true,
			'fill'      => true,
			'fill-rule' => true,
			'clip-rule' => true,
		),
		'g'    => array( 'fill' => true ),
	);
}

/**
 * The WordPress mark, inline so it takes the color around it.
 *
 * @param int $size Pixel size.
 * @return string
 */
function wpcredits_get_logo( $size = 24 ) {
	return sprintf(
		'<svg class="wpc-logo" width="%1$d" height="%1$d" viewBox="0 0 122.523 122.523" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8.708 61.26c0 20.802 12.089 38.779 29.619 47.298L13.258 39.872a52.354 52.354 0 0 0-4.55 21.388zM96.74 58.608c0-6.495-2.333-10.993-4.334-14.494-2.664-4.329-5.161-7.995-5.161-12.324 0-4.831 3.664-9.328 8.825-9.328.233 0 .454.029.681.042-9.35-8.566-21.807-13.796-35.489-13.796-18.36 0-34.513 9.42-43.91 23.688 1.233.037 2.395.063 3.382.063 5.497 0 14.006-.667 14.006-.667 2.833-.167 3.167 3.994.337 4.329 0 0-2.847.335-6.015.501l19.138 56.925L59.706 59.59l-8.188-22.434c-2.83-.166-5.511-.501-5.511-.501-2.832-.166-2.5-4.496.331-4.329 0 0 8.679.667 13.843.667 5.496 0 14.006-.667 14.006-.667 2.835-.167 3.168 3.994.337 4.329 0 0-2.853.335-6.015.501l18.992 56.494 5.242-17.517c2.272-7.269 4-12.49 4-16.989zM62.184 65.857l-15.768 45.819a52.578 52.578 0 0 0 14.832 2.143c5.81 0 11.382-1.005 16.567-2.825a4.689 4.689 0 0 1-.376-.73L62.184 65.857zm45.19-29.812c.226 1.674.354 3.471.354 5.404 0 5.333-.996 11.328-3.996 18.824L87.685 107.83c15.622-9.111 26.13-26.038 26.13-45.426.001-9.137-2.333-17.729-6.441-25.226zM61.262 0C27.483 0 0 27.481 0 61.26c0 33.783 27.483 61.263 61.262 61.263 33.778 0 61.265-27.48 61.265-61.263C122.526 27.481 95.04 0 61.262 0zm0 119.715c-32.23 0-58.453-26.223-58.453-58.455 0-32.23 26.222-58.451 58.453-58.451 32.229 0 58.45 26.221 58.45 58.451 0 32.232-26.221 58.455-58.45 58.455z" /></svg>',
		(int) $size
	);
}
