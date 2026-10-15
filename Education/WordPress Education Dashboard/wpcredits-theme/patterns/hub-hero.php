<?php
/**
 * Title: Education Hub hero
 * Slug: wpcredits/hub-hero
 * Categories: wpcredits, featured
 * Description: The Education Hub's opening: what the programs offer, two ways in, and the program's three outcome figures with their source.
 * Keywords: hub, hero, education, figures, outcomes
 * Viewport Width: 1400
 *
 * The figures are program figure blocks, each the plugin's stored figure or nothing: with no figure
 * to show, the figures column is not displayed and the copy takes the whole width (hub.css).
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:group {"align":"wide","className":"wpc-hub-hero","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-hub-hero"><!-- wp:columns {"className":"wpc-hub-hero__cols wpc-hub-stack","style":{"spacing":{"padding":{"top":"56px","bottom":"56px","left":"48px","right":"48px"}}}} -->
<div class="wp-block-columns wpc-hub-hero__cols wpc-hub-stack" style="padding-top:56px;padding-right:48px;padding-bottom:56px;padding-left:48px"><!-- wp:column {"width":"58%","className":"wpc-hub-hero__copy"} -->
<div class="wp-block-column wpc-hub-hero__copy" style="flex-basis:58%"><!-- wp:paragraph {"className":"wpc-pill","style":{"spacing":{"margin":{"top":"0","bottom":"20px"}}}} -->
<p class="wpc-pill" style="margin-top:0;margin-bottom:20px"><?php esc_html_e( 'Education programs from the WordPress project', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"wpc-hero__title","fontSize":"display","style":{"spacing":{"margin":{"top":"0","bottom":"18px"}}}} -->
<h1 class="wp-block-heading wpc-hero__title has-display-font-size" style="margin-top:0;margin-bottom:18px"><?php esc_html_e( 'Start learning with WordPress.', 'wpcredits-theme' ); ?> <em><?php esc_html_e( 'Every step counts. Keep climbing.', 'wpcredits-theme' ); ?></em></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede","textColor":"ink-70","style":{"spacing":{"margin":{"top":"0","bottom":"26px"}}}} -->
<p class="wpc-hub-lede has-ink-70-color has-text-color" style="margin-top:0;margin-bottom:26px"><?php esc_html_e( 'Students earn academic credit, credentials, and a portfolio employers can check, and each program leads to the next. Institutions can start with one workshop or a small pilot, and every group of students they run moves them up a partner level with more support.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"wpc-hero__actions","style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-buttons wpc-hero__actions"><!-- wp:button {"className":"wpc-button--large"} -->
<div class="wp-block-button wpc-button--large"><a class="wp-block-button__link wp-element-button" href="#programs"><?php esc_html_e( 'Find where to start', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline wpc-button--large"} -->
<div class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="#pathway"><?php esc_html_e( 'Explore the pathway', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"42%","className":"wpc-hub-hero__figures"} -->
<div class="wp-block-column wpc-hub-hero__figures" style="flex-basis:42%"><!-- wp:wpcredits/program-stat {"stat":"graduation"} /-->

<!-- wp:wpcredits/program-stat {"stat":"recommend"} /-->

<!-- wp:wpcredits/program-stat {"stat":"keep"} /-->

<!-- wp:wpcredits/program-stat {"stat":"outcomes-source"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
