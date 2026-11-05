<?php
/**
 * Title: Page not found
 * Slug: wpcredits/hub-not-found
 * Categories: wpcredits
 * Inserter: no
 * Description: The page-not-found page in the homepage hero's layout: what happened, two ways on, and the hero's mountain of role words.
 * Keywords: 404, not found, error
 * Viewport Width: 1400
 *
 * What the 404 template draws (templates/404.html, theme 1.32.12, the product owner's words of 5 November
 * 2026): the homepage hero's layout, its pill line, a title in its display serif with "Keep climbing." in
 * the hero's brush, a lede, the hero's two button shapes, and in the picture's column the hero's mountain of
 * role words (patterns/hub-mountain.php). Only the look: WordPress answers the request with the
 * 404 status before it draws the template, and the theme leaves that as it is. No forward: that is the
 * Notice template's alone.
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:group {"align":"wide","className":"wpc-hub-hero wpc-hub-notice","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-hub-hero wpc-hub-notice"><!-- wp:columns {"className":"wpc-hub-hero__cols wpc-hub-stack","style":{"spacing":{"padding":{"top":"0","bottom":"52px","left":"0","right":"0"}}}} -->
<div class="wp-block-columns wpc-hub-hero__cols wpc-hub-stack" style="padding-top:0;padding-right:0;padding-bottom:52px;padding-left:0"><!-- wp:column {"width":"47%","className":"wpc-hub-hero__copy"} -->
<div class="wp-block-column wpc-hub-hero__copy" style="flex-basis:47%"><!-- wp:paragraph {"className":"wpc-pill","style":{"spacing":{"margin":{"top":"0","bottom":"20px"}}}} -->
<p class="wpc-pill" style="margin-top:0;margin-bottom:20px"><?php esc_html_e( 'Page not found', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"wpc-hero__title","fontSize":"display","style":{"spacing":{"margin":{"top":"0","bottom":"18px"}}}} -->
<h1 class="wp-block-heading wpc-hero__title has-display-font-size" style="margin-top:0;margin-bottom:18px"><?php esc_html_e( 'This path doesn\'t lead anywhere.', 'wpcredits-theme' ); ?> <em><span class="wpc-hub-brush"><?php esc_html_e( 'Keep climbing.', 'wpcredits-theme' ); ?></span></em></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede","textColor":"ink-70","style":{"spacing":{"margin":{"top":"0","bottom":"26px"}}}} -->
<p class="wpc-hub-lede has-ink-70-color has-text-color" style="margin-top:0;margin-bottom:26px"><?php esc_html_e( 'The address may have changed, or the page may have moved. Start from the programs, or go back to the homepage.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"wpc-hero__actions","style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-buttons wpc-hero__actions"><!-- wp:button {"className":"wpc-button--large"} -->
<div class="wp-block-button wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/#programs' ) ); ?>"><?php esc_html_e( 'Find where to start', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline wpc-button--large"} -->
<div class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go to the homepage', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"49%","className":"wpc-hub-hero__art"} -->
<div class="wp-block-column is-vertically-aligned-bottom wpc-hub-hero__art" style="flex-basis:49%"><!-- wp:pattern {"slug":"wpcredits/hub-mountain"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
