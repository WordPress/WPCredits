<?php
/**
 * Server render for the contents list.
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

$wpcredits_max      = isset( $attributes['maxLevel'] ) ? (int) $attributes['maxLevel'] : 3;
$wpcredits_headings = wpcredits_guide_headings( get_the_ID(), $wpcredits_max );

// A page with two headings does not need a contents list, and an empty box in the sidebar is worse
// than no box at all.
if ( count( $wpcredits_headings ) < 3 ) {
	return;
}

$wpcredits_title = isset( $attributes['title'] ) && '' !== $attributes['title']
	? $attributes['title']
	: __( 'On this page', 'wpcredits-theme' );

printf(
	'<nav %s aria-labelledby="wpc-toc-title">',
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core.
	get_block_wrapper_attributes( array( 'class' => 'wpc-toc' ) )
);

printf( '<h2 class="wpc-toc__title" id="wpc-toc-title">%s</h2>', esc_html( $wpcredits_title ) );

echo '<ul class="wpc-toc__list">';

foreach ( $wpcredits_headings as $wpcredits_heading ) {
	printf(
		'<li class="wpc-toc__item wpc-toc__item--h%1$d"><a class="wpc-toc__link" href="#%2$s">%3$s</a></li>',
		(int) $wpcredits_heading['level'],
		esc_attr( $wpcredits_heading['id'] ),
		esc_html( $wpcredits_heading['text'] )
	);
}

echo '</ul>';
echo '</nav>';
