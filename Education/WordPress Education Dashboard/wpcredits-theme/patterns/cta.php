<?php
/**
 * Title: Mentor call to action
 * Slug: wpcredits/cta
 * Categories: wpcredits, call-to-action
 * Description: A brand-blue band pointing mentors at their students page.
 * Keywords: cta, mentors, sign in
 * Viewport Width: 1400
 *
 * The button goes to the mentor page for someone already signed in, and to the
 * login form for everyone else — the plugin sends mentors to "My Students" on
 * login, so both routes end in the same place. With the plugin inactive there is
 * no page to point at, so the band links home rather than to a 404.
 *
 * @package WPCredits_Theme
 */

$wpcredits_page   = wpcredits_mentor_page_url();
$wpcredits_target = home_url( '/' );

if ( '' !== $wpcredits_page ) {
	$wpcredits_target = is_user_logged_in() ? $wpcredits_page : wp_login_url( $wpcredits_page );
}

$wpcredits_lede = is_user_logged_in()
	? __( 'Your students, their details and your call notes are on one page.', 'wpcredits-theme' )
	: __( 'Your students, their details and your call notes are on one page. Sign in and it opens straight away.', 'wpcredits-theme' );

?>
<!-- wp:group {"align":"wide","className":"wpc-cta","backgroundColor":"brand","style":{"spacing":{"padding":{"top":"40px","bottom":"40px","left":"40px","right":"40px"},"margin":{"bottom":"48px"}},"border":{"radius":"12px"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group alignwide wpc-cta has-brand-background-color has-background" style="border-radius:12px;margin-bottom:48px;padding:40px"><!-- wp:group {"className":"wpc-cta__copy","layout":{"type":"constrained"}} -->
<div class="wp-block-group wpc-cta__copy"><!-- wp:heading {"level":2,"textColor":"base","style":{"spacing":{"margin":{"top":"0","bottom":"8px"}}}} -->
<h2 class="wp-block-heading has-base-color has-text-color" style="margin-top:0;margin-bottom:8px"><?php esc_html_e( 'Already mentoring?', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"brand-tint","fontSize":"small","style":{"typography":{"lineHeight":"1.57"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-brand-tint-color has-text-color has-small-font-size" style="margin-top:0;margin-bottom:0;line-height:1.57"><?php echo esc_html( $wpcredits_lede ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"wpc-button--light wpc-button--large"} -->
<div class="wp-block-button wpc-button--light wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $wpcredits_target ); ?>"><?php esc_html_e( 'Open My Students', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
