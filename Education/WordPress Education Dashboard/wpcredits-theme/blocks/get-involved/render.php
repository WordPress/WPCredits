<?php
/**
 * Server render for the Get involved block: the bar's menu, a button and the panel of role rows it
 * opens, as inc/get-involved.php draws it (`wpcredits_involve_menu_html()`), or nothing for a reader
 * offered no row.
 *
 * The theme hooks the block as the last item of a visitor's navigation (`wpcredits_involve_hook()`), so
 * its markup is a navigation item, and core lists it with the navigation's links. Its view script opens
 * and closes the panel; WordPress loads it only on a page that draws the block.
 *
 * @package WPCredits_Theme
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo wpcredits_involve_menu_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
