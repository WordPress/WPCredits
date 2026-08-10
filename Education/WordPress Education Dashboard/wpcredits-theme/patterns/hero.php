<?php
/**
 * Title: Program hero
 * Slug: wpcredits/hero
 * Categories: wpcredits, featured
 * Description: Headline, standfirst and two calls to action beside a photo with a floating statistic.
 * Keywords: hero, program, headline
 * Viewport Width: 1400
 *
 * The photo ships with the theme rather than being a dashed placeholder: a landing
 * page whose main image is an empty box reads as broken, and every install had to
 * do the same first job before the page was showable. Replace it with an Image
 * block from the media library to use your own — 1240x745 is the size it is drawn
 * at, and anything of that shape will do.
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:group {"align":"wide","className":"wpc-hero","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-hero"><!-- wp:columns {"verticalAlignment":"center","className":"wpc-hero__cols","style":{"spacing":{"padding":{"top":"56px","bottom":"48px"}}}} -->
<div class="wp-block-columns are-vertically-aligned-center wpc-hero__cols" style="padding-top:56px;padding-bottom:48px"><!-- wp:column {"verticalAlignment":"center","width":"52%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:52%"><!-- wp:paragraph {"className":"wpc-pill","style":{"spacing":{"margin":{"top":"0","bottom":"20px"}}}} -->
<p class="wpc-pill" style="margin-top:0;margin-bottom:20px"><?php esc_html_e( 'WordPress Credits Program', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"wpc-hero__title","fontSize":"display","style":{"spacing":{"margin":{"top":"0","bottom":"18px"}}}} -->
<h1 class="wp-block-heading wpc-hero__title has-display-font-size" style="margin-top:0;margin-bottom:18px"><?php esc_html_e( 'Real contributions,', 'wpcredits-theme' ); ?><br><em><?php esc_html_e( 'real academic credit', 'wpcredits-theme' ); ?></em></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hero__lede","textColor":"ink-70","style":{"spacing":{"margin":{"top":"0","bottom":"26px"}}}} -->
<p class="wpc-hero__lede has-ink-70-color has-text-color" style="margin-top:0;margin-bottom:26px"><?php esc_html_e( 'Students contribute to WordPress as part of their degree. Institutions award the credit. Mentors guide every internship, from first commit to final report.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"wpc-hero__actions","style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-buttons wpc-hero__actions"><!-- wp:button {"className":"wpc-button--large"} -->
<div class="wp-block-button wpc-button--large"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Apply as a student', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline wpc-button--large"} -->
<div class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Become a mentor', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"48%","className":"wpc-hero__media"} -->
<div class="wp-block-column is-vertically-aligned-center wpc-hero__media" style="flex-basis:48%"><!-- wp:image {"className":"wpc-hero__photo","sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large wpc-hero__photo"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/hero-mentoring.jpg' ) ); ?>" alt="<?php esc_attr_e( 'A student taking notes during a video call with their mentor.', 'wpcredits-theme' ); ?>" width="1240" height="745"/></figure>
<!-- /wp:image -->

<!-- wp:group {"className":"wpc-hero__stat","layout":{"type":"constrained"}} -->
<div class="wp-block-group wpc-hero__stat"><!-- wp:wpcredits/program-stat {"stat":"mentors","fallback":"90","className":"wpc-hero__stat-number","textColor":"brand","fontSize":"display","style":{"typography":{"fontWeight":"700","lineHeight":"1.14"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:paragraph {"className":"wpc-hero__stat-label","fontSize":"medium","textColor":"ink-70","style":{"typography":{"fontWeight":"600"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="wpc-hero__stat-label has-ink-70-color has-text-color has-medium-font-size" style="margin-top:0;margin-bottom:0;font-weight:600"><?php esc_html_e( 'mentors currently active in the program', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
