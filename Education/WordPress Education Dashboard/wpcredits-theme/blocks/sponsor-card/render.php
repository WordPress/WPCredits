<?php
/**
 * Server render for the sponsors card of the homepage's roles band (theme 1.32.12): Sponsors, one line,
 * and Become a sponsor, to the sponsor application, in the doors' card, as the band's other three cards
 * are written in its pattern.
 *
 * **A block and not a card in the pattern, because it shows only while the Partners page is published**
 * (`wpcredits_partners_live()`, the test the header's Partners link is hooked by; the product owner,
 * 5 November 2026), and a card written into a saved template would show whatever the switch says. With
 * the page not public the block prints nothing at all, so the band's grid holds three cards and gives
 * each a column (hub.css). The address is the plugin's form while it is published, and the WordPress
 * Credits handbook otherwise, as every link to one of the site's own pages falls back (`wpcredits_hub_url()`).
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

if ( ! function_exists( 'wpcredits_partners_live' ) || ! wpcredits_partners_live() ) {
	return;
}

printf(
	'<div %1$s><h3 class="wp-block-heading">%2$s</h3><p class="wpc-hub-door__text">%3$s</p><p class="wpc-hub-door__go"><a href="%4$s">%5$s<span aria-hidden="true"> →</span></a></p></div>',
	get_block_wrapper_attributes( array( 'class' => 'wp-block-group is-style-card wpc-hub-door wpc-hub-role wpc-hub-role--sponsors' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core.
	esc_html__( 'Sponsors', 'wpcredits-theme' ),
	esc_html__( 'Back the next contributors', 'wpcredits-theme' ),
	esc_url( wpcredits_hub_url( 'sponsor_application' ) ),
	esc_html__( 'Become a sponsor', 'wpcredits-theme' )
);
