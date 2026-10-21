<?php
/**
 * Title: Partners page, what support funds
 * Slug: wpcredits/partners-funds
 * Categories: wpcredits
 * Description: What partner support pays for, in three cards.
 * Keywords: partners, sponsors, funding
 * Viewport Width: 1400
 *
 * @package WPCredits_Theme
 */

$wpcredits_funds = array(
	array( __( 'Curriculum and mentorship', 'wpcredits-theme' ), __( 'Course materials, mentor time, and the review work that keeps student contributions at project standard.', 'wpcredits-theme' ) ),
	array( __( 'Translation and accessibility', 'wpcredits-theme' ), __( 'Bringing the program, and the work students produce, to languages and communities with thin coverage today.', 'wpcredits-theme' ) ),
	array( __( 'Institutional onboarding', 'wpcredits-theme' ), __( 'Tooling, administration, and the support each new university or school needs to run a cohort well.', 'wpcredits-theme' ) ),
);

?>
<!-- wp:group {"align":"wide","className":"wpc-partners-funds","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-partners-funds" style="padding-top:48px;padding-bottom:48px"><!-- wp:group {"className":"wpc-hub-head","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-hub-head"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'What your support funds', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede"} -->
<p class="wpc-hub-lede"><?php esc_html_e( 'The program has reached the size where curriculum, translation, tooling, and the work of bringing new institutions on board all need steady funding behind them. Partner contributions go into that work directly.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:html -->
<ul class="wpc-partners-funds__grid">
<?php foreach ( $wpcredits_funds as $wpcredits_fund ) : ?>
	<li class="wpc-partners-card"><h3 class="wpc-partners-card__title"><?php echo esc_html( $wpcredits_fund[0] ); ?></h3><p class="wpc-partners-card__text"><?php echo esc_html( $wpcredits_fund[1] ); ?></p></li>
<?php endforeach; ?>
</ul>
<!-- /wp:html --></div>
<!-- /wp:group -->
