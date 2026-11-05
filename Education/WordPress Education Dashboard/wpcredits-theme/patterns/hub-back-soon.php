<?php
/**
 * Title: Back soon notice
 * Slug: wpcredits/hub-back-soon
 * Categories: wpcredits
 * Block Types: core/post-content
 * Post Types: page
 * Description: A page's content for the Notice template: this page is being updated, back soon, with a way to the programs and a way to write to the education team.
 * Keywords: notice, back soon, updating, maintenance
 * Viewport Width: 1400
 *
 * The content a new page is offered to start with (theme 1.32.12, the product owner's words of 5 November
 * 2026), for a page drawn by the Notice template (templates/page-notice.html), which sets it in the
 * homepage hero's layout beside the mountain: the hero's pill line, a title in its display serif with its
 * second sentence in the brush, as the hero's "Keep climbing." is, the lede with the education team's
 * address, and the hero's two button shapes. The page's own content, so every word is edited on the page.
 * The template forwards the reader to the homepage after 5 seconds unless they stay
 * (blocks/notice-forward).
 *
 * @package WPCredits_Theme
 */

$wpcredits_mail = 'education@wordpressfoundation.org';
$wpcredits_lede = sprintf(
	/* translators: %s: the education team's email address, a link. */
	esc_html__( 'We\'re reworking this part of the Education Directory. In the meantime, find a program to start with, or write to us at %s.', 'wpcredits-theme' ),
	'<a href="' . esc_url( 'mailto:' . $wpcredits_mail ) . '">' . esc_html( $wpcredits_mail ) . '</a>'
);

?>
<!-- wp:paragraph {"className":"wpc-pill","style":{"spacing":{"margin":{"top":"0","bottom":"20px"}}}} -->
<p class="wpc-pill" style="margin-top:0;margin-bottom:20px"><?php esc_html_e( 'Education programs from the WordPress project', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"wpc-hero__title","fontSize":"display","style":{"spacing":{"margin":{"top":"0","bottom":"18px"}}}} -->
<h1 class="wp-block-heading wpc-hero__title has-display-font-size" style="margin-top:0;margin-bottom:18px"><?php esc_html_e( 'This page is being updated.', 'wpcredits-theme' ); ?> <em><span class="wpc-hub-brush"><?php esc_html_e( 'Back soon.', 'wpcredits-theme' ); ?></span></em></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede","textColor":"ink-70","style":{"spacing":{"margin":{"top":"0","bottom":"26px"}}}} -->
<p class="wpc-hub-lede has-ink-70-color has-text-color" style="margin-top:0;margin-bottom:26px"><?php echo wp_kses( $wpcredits_lede, array( 'a' => array( 'href' => true ) ) ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"wpc-hero__actions","style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-buttons wpc-hero__actions"><!-- wp:button {"className":"wpc-button--large"} -->
<div class="wp-block-button wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/#programs' ) ); ?>"><?php esc_html_e( 'Find where to start', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline wpc-button--large"} -->
<div class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( 'mailto:' . $wpcredits_mail ); ?>"><?php esc_html_e( 'Write to us', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
