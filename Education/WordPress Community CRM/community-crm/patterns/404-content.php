<?php
/**
 * Title: 404 content
 * Slug: community-crm/404-content
 * Categories: community-crm
 * Description: The "page not found" card — code, headline, search field and a link back to the sign-in screen.
 * Keywords: 404, not found, error
 * Inserter: no
 *
 * @package Community_CRM
 */

?>
<!-- wp:group {"className":"ccrm-panel","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|70","right":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|40"}},"backgroundColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group ccrm-panel has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--70)"><!-- wp:paragraph {"className":"ccrm-hero__eyebrow","style":{"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|30"}}}} -->
<p class="ccrm-hero__eyebrow" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--30)"><?php echo esc_html__( 'Error 404', 'community-crm' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"style":{"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|30"}}},"fontSize":"xl"} -->
<h1 class="wp-block-heading has-xl-font-size" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--30)"><?php echo esc_html__( 'That page is not here.', 'community-crm' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted","style":{"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|50"}}}} -->
<p class="has-muted-color has-text-color" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--50)"><?php echo esc_html__( 'It may have been renamed or moved. Try a search, or head back to the sign-in screen.', 'community-crm' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:search {"label":"<?php echo esc_attr__( 'Search', 'community-crm' ); ?>","showLabel":false,"placeholder":"<?php echo esc_attr__( 'Search', 'community-crm' ); ?>","buttonText":"<?php echo esc_attr__( 'Search', 'community-crm' ); ?>","buttonPosition":"button-outside"} /-->

<!-- wp:buttons {"style":{"spacing":{"blockGap":"var:preset|spacing|30","margin":{"top":"var:preset|spacing|50"}}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--50)"><!-- wp:button {"className":"is-style-ccrm-outline","fontSize":"md"} -->
<div class="wp-block-button has-custom-font-size is-style-ccrm-outline has-md-font-size"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Back to sign in', 'community-crm' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
