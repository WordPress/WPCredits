<?php
/**
 * Template tags for themes that render their chrome in PHP.
 *
 * In the global namespace, and in its own file because a file cannot mix a namespace declaration
 * with global code. A Classic theme's `header.php` calls `wporg_global_header()` unqualified;
 * `WPORG_Global_Header_Footer\wporg_global_header()` in a template is nobody's idea of readable.
 *
 * A block theme needs none of this — it places `<!-- wp:wporg/global-header /-->` in a template
 * part instead.
 *
 * @package WPORG_Global_Header_Footer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Echo the wordpress.org global header.
 *
 * Guarded, in case a site is on the wordpress.org network and something there already defines it.
 */
if ( ! function_exists( 'wporg_global_header' ) ) {
	function wporg_global_header() {
		echo \WPORG_Global_Header_Footer\header_markup(); // phpcs:ignore WordPress.Security.EscapeOutput -- Block markup, escaped as it renders.
	}
}

/**
 * Echo the wordpress.org global footer.
 */
if ( ! function_exists( 'wporg_global_footer' ) ) {
	function wporg_global_footer() {
		echo \WPORG_Global_Header_Footer\footer_markup(); // phpcs:ignore WordPress.Security.EscapeOutput -- Block markup, escaped as it renders.
	}
}
