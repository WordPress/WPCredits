<?php
/**
 * Title: Education Hub closing band
 * Slug: wpcredits/hub-cta
 * Categories: wpcredits
 * Description: Two cards to close the page: companies looking for WordPress talent, and sponsors and schools who want to help.
 * Keywords: hub, call to action, hire, sponsor, partner
 * Viewport Width: 1400
 *
 * The sponsorship and institution applications are the site's own pages, read when the pattern
 * renders. The Companies card, which leads to the jobs site, is parked with the hub's planned content
 * (a program lead, 21 October 2026).
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:group {"align":"wide","className":"wpc-hub-cta","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-hub-cta" style="padding-top:48px;padding-bottom:48px"><!-- wp:columns {"className":"wpc-hub-cta__grid wpc-hub-stack"} -->
<div class="wp-block-columns wpc-hub-cta__grid wpc-hub-stack"><?php if ( wpcredits_hub_shows_planned() ) : // Parked: the jobs site's card (a program lead, 21 October 2026), back with the planned content. ?><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"is-style-card wpc-hub-cta__card","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-card wpc-hub-cta__card"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php esc_html_e( 'Companies', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"fontSize":"large"} -->
<h2 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Looking for WordPress talent?', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-cta__text"} -->
<p class="wpc-hub-cta__text"><?php esc_html_e( 'See what graduates are trained to do and where to find them.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"wpc-button--large"} -->
<div class="wp-block-button wpc-button--large"><a class="wp-block-button__link wp-element-button" href="https://jobs.wordpress.net/"><?php esc_html_e( 'Hire from WordPress', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<?php endif; ?><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"is-style-card wpc-hub-cta__card","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-card wpc-hub-cta__card"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php esc_html_e( 'Sponsors and schools', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"fontSize":"large"} -->
<h2 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Help more students reach the next step', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-cta__text"} -->
<p class="wpc-hub-cta__text"><?php esc_html_e( 'Fund the programs, or bring them to your campus.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline wpc-button--large"} -->
<div class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( wpcredits_hub_url( 'sponsor_application' ) ); ?>"><?php esc_html_e( 'Sponsor', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline wpc-button--large"} -->
<div class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( wpcredits_hub_url( 'institution_application' ) ); ?>"><?php esc_html_e( 'Partner as an institution', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
