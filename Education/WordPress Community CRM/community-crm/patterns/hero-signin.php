<?php
/**
 * Title: Sign-in card (hero + form)
 * Slug: community-crm/hero-signin
 * Categories: community-crm, call-to-action
 * Description: The full front-page card — illustrated hero with headline and buttons, and the WordPress.org sign-in panel below it.
 * Keywords: hero, login, sign in, card, front page
 * Viewport Width: 1040
 *
 * @package Community_CRM
 */

?>
<!-- wp:group {"className":"ccrm-card","backgroundColor":"base","layout":{"type":"default"}} -->
<div class="wp-block-group ccrm-card has-base-background-color has-background"><!-- wp:group {"className":"ccrm-hero","layout":{"type":"default"}} -->
<div class="wp-block-group ccrm-hero"><!-- wp:group {"className":"ccrm-hero__body","layout":{"type":"default"}} -->
<div class="wp-block-group ccrm-hero__body"><!-- wp:paragraph {"className":"ccrm-hero__eyebrow","style":{"spacing":{"margin":{"top":"0","bottom":"14px"}}}} -->
<p class="ccrm-hero__eyebrow" style="margin-top:0;margin-bottom:14px"><?php echo esc_html__( 'Internal tool · Community team', 'community-crm' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"ccrm-hero__title","style":{"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|40"}}},"fontSize":"xxl"} -->
<h1 class="wp-block-heading ccrm-hero__title has-xxl-font-size" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--40)"><?php echo esc_html__( 'One place for the relationships that keep WordPress events running.', 'community-crm' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"ccrm-hero__lede","style":{"spacing":{"margin":{"top":"0","bottom":"0"}},"typography":{"lineHeight":"24px"}},"fontSize":"lg"} -->
<p class="ccrm-hero__lede has-lg-font-size" style="margin-top:0;margin-bottom:0;line-height:24px"><?php echo esc_html__( 'Organizers use the Community CRM to keep track of the people, organizations and sponsors around meetups and WordCamps — without a new spreadsheet every year.', 'community-crm' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:community-crm/login-form /--></div>
<!-- /wp:group -->
