<?php
/**
 * Title: Partners page hero
 * Slug: wpcredits/partners-hero
 * Categories: wpcredits
 * Description: The Partners page's opening: what the program is and what partner support funds, the two ways to start, and two figures of the program's size.
 * Keywords: partners, sponsors, hero
 * Viewport Width: 1400
 *
 * The Partners page (`templates/page-partners.html`) presents the WordPress Credits partnership
 * levels in the Education Hub's look, its words a program lead's (21 October 2026). The application is
 * the site's own page, read when the pattern renders.
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:group {"align":"wide","className":"wpc-partners-hero","style":{"spacing":{"padding":{"top":"24px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-partners-hero" style="padding-top:24px;padding-bottom:48px"><!-- wp:columns {"className":"wpc-partners-hero__cols wpc-hub-stack"} -->
<div class="wp-block-columns wpc-partners-hero__cols wpc-hub-stack"><!-- wp:column {"width":"60%","className":"wpc-partners-hero__copy"} -->
<div class="wp-block-column wpc-partners-hero__copy" style="flex-basis:60%"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php esc_html_e( 'WordPress Credits Program', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"wpc-partners-hero__title"} -->
<h1 class="wp-block-heading wpc-partners-hero__title"><?php esc_html_e( 'Fund real student contributions to WordPress', 'wpcredits-theme' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede"} -->
<p class="wpc-hub-lede"><?php esc_html_e( 'WordPress Credits is a program of the WordPress Foundation, a 501(c)(3) nonprofit organization. Students at partner institutions contribute to the WordPress project for academic credit, guided by mentors and recognized on their WordPress.org profiles. Partner support funds the curriculum, mentorship, translation and tooling behind those placements.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"wpc-partners-hero__actions"} -->
<div class="wp-block-buttons wpc-partners-hero__actions"><!-- wp:button {"className":"wpc-button--large"} -->
<div class="wp-block-button wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( wpcredits_hub_url( 'sponsor_application' ) ); ?>"><?php esc_html_e( 'Apply to become a partner', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline wpc-button--large"} -->
<div class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="mailto:education@wordpressfoundation.org"><?php esc_html_e( 'Talk to the education team', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"36%","className":"wpc-partners-hero__figures"} -->
<div class="wp-block-column wpc-partners-hero__figures" style="flex-basis:36%"><!-- wp:html -->
<dl class="wpc-partners-figures">
	<div class="wpc-partners-figure"><dt class="wpc-partners-figure__number"><?php echo esc_html_x( '500+', 'a figure: more than five hundred students', 'wpcredits-theme' ); ?></dt><dd class="wpc-partners-figure__label"><?php esc_html_e( 'students currently contributing through the program', 'wpcredits-theme' ); ?></dd></div>
	<div class="wpc-partners-figure"><dt class="wpc-partners-figure__number"><?php echo esc_html_x( '17', 'a figure: seventeen countries', 'wpcredits-theme' ); ?></dt><dd class="wpc-partners-figure__label"><?php esc_html_e( 'countries with participating institutions, from 8 institutions at the start of the year', 'wpcredits-theme' ); ?></dd></div>
</dl>
<!-- /wp:html -->

<!-- wp:paragraph {"className":"wpc-partners-figures__source"} -->
<p class="wpc-partners-figures__source"><?php printf( /* translators: %s: a link to the WordPress Credits dashboard, its words "WordPress Credits dashboard". */ esc_html__( 'Progress is reported weekly on the %s, refreshed from program records and WordPress.org profiles.', 'wpcredits-theme' ), '<a href="https://make.wordpress.org/community/2026/08/13/wpcredits-dashboard/">' . esc_html__( 'WordPress Credits dashboard', 'wpcredits-theme' ) . '</a>' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
