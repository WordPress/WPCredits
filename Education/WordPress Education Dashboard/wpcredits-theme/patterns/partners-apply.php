<?php
/**
 * Title: Partners page, become a partner
 * Slug: wpcredits/partners-apply
 * Categories: wpcredits
 * Description: The application form and the education team's address to close the page, and the note on tax deductibility.
 * Keywords: partners, sponsors, apply
 * Viewport Width: 1400
 *
 * The application is the site's own page, read when the pattern renders.
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:group {"align":"wide","anchor":"apply","className":"wpc-partners-apply wpc-hub-anchored","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div id="apply" class="wp-block-group alignwide wpc-partners-apply wpc-hub-anchored" style="padding-top:48px;padding-bottom:48px"><!-- wp:group {"className":"is-style-card wpc-hub-cta__card wpc-partners-apply__card","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-card wpc-hub-cta__card wpc-partners-apply__card"><!-- wp:heading {"fontSize":"large"} -->
<h2 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Become a partner', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-cta__text"} -->
<p class="wpc-hub-cta__text"><?php esc_html_e( 'Apply through the form, or write to us first if you would rather talk it through. If you have an idea for supporting the program that does not match any level here, we want to hear it.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"wpc-button--large"} -->
<div class="wp-block-button wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( wpcredits_hub_url( 'sponsor_application' ) ); ?>"><?php esc_html_e( 'Partner application form', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline wpc-button--large"} -->
<div class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="mailto:education@wordpressfoundation.org">education@wordpressfoundation.org</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:paragraph {"className":"wpc-partners-apply__more"} -->
<p class="wpc-partners-apply__more"><?php esc_html_e( 'You can also find us in the #wpcredits channel in the Make WordPress Slack. Current sponsors will hear from a program manager directly, so there is nothing you need to do.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"wpc-partners-note"} -->
<p class="wpc-partners-note"><?php esc_html_e( 'WordPress Credits is a program of the WordPress Foundation, a 501(c)(3) nonprofit organization. Donations may be tax deductible to the extent allowed by law, less the value of any benefits received, and we will note that value in your acknowledgment letter. Donated products and services are not tax deductible. Please check with your own tax advisor.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
