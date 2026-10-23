<?php
/**
 * Title: Partners page, the partnership levels
 * Slug: wpcredits/partners-levels
 * Categories: wpcredits
 * Description: The pilot year, the five partnership levels with what each gives, and a table comparing them.
 * Keywords: partners, sponsors, levels, tiers
 * Viewport Width: 1400
 *
 * The cards are the Partner levels block, holding a Partner level block for each level, and the
 * table is the Levels at a glance block, which draws its columns and rows from the levels of the
 * Partners template in use (theme 1.32.1): Administrators edit both in the Site Editor, and the plugin's
 * sponsor application reads the same levels. The blocks are written with the five levels the theme
 * ships, `wpcredits_partner_levels_defaults()` (inc/partners.php), in a program lead's words, and never
 * with `wpcredits_partner_levels()`, which reads the Partners template, whose file refers to this
 * pattern. The amounts are the program's own, text on the page; the site handles no payment.
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:group {"align":"wide","className":"wpc-partners-pilot-band","style":{"spacing":{"padding":{"top":"48px","bottom":"0"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-partners-pilot-band" style="padding-top:48px;padding-bottom:0"><!-- wp:group {"className":"wpc-partners-pilot","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-partners-pilot"><!-- wp:heading {"level":2,"className":"wpc-partners-pilot__title"} -->
<h2 class="wp-block-heading wpc-partners-pilot__title"><?php esc_html_e( '2027 is a pilot year', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'This is the first time we have offered partnership levels, and we are treating it as a test. We will run it through 2027, publish what we learn, and adjust or retire whatever does not work. If none of the levels below fits what your company wants to do, write to us and we will shape something that does.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","anchor":"levels","className":"wpc-partners-levels wpc-hub-anchored","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div id="levels" class="wp-block-group alignwide wpc-partners-levels wpc-hub-anchored" style="padding-top:48px;padding-bottom:48px"><!-- wp:group {"className":"wpc-hub-head","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-hub-head"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Partnership levels', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede"} -->
<p class="wpc-hub-lede"><?php esc_html_e( 'Five levels, from contributing mentor time through to funding a complete program. Recognition is consistent across all of them: partners are listed in the same format, with descriptions of the same length, in alphabetical order rather than by level.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"wpc-partners-levels__compare"} -->
<p class="wpc-partners-levels__compare"><a href="#glance"><?php esc_html_e( 'Compare all levels', 'wpcredits-theme' ); ?><span aria-hidden="true"> →</span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<?php echo wpcredits_partner_levels_markup( wpcredits_partner_levels_defaults() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup, its texts escaped as they are written. ?></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","anchor":"glance","className":"wpc-partners-glance wpc-hub-anchored","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div id="glance" class="wp-block-group alignwide wpc-partners-glance wpc-hub-anchored" style="padding-top:48px;padding-bottom:48px"><!-- wp:group {"className":"wpc-hub-head","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-hub-head"><!-- wp:heading -->
<h2 class="wp-block-heading" id="wpc-partners-glance-title"><?php esc_html_e( 'Levels at a glance', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<?php echo wpcredits_partner_glance_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup. ?></div>
<!-- /wp:group -->
