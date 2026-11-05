<?php
/**
 * Server render for the Notice template's forward to the homepage (theme 1.32.12, the product owner's ask
 * of 5 November 2026): a line under the page's buttons, "Taking you to the homepage in 5 seconds.", a "Go
 * now" link to the homepage and a "Stay on this page" button, and an empty live region.
 *
 * The block's view script counts the line down each second and, after the fifth, takes the reader to the
 * homepage; Stay stops it and puts "You're staying on this page." in the line's place, since a timed move
 * must be one a reader can stop (WCAG 2.2.1). The live region, polite, is told twice, the first line and the
 * stopped one, never every second. Every line is written here, translated, a plural for each second, so the
 * script carries no words of its own. Without JavaScript the line is not shown (hub.css shows it under the
 * mark `wpc-js` alone) and nothing forwards: the page keeps its own buttons. No meta refresh, which a reader
 * cannot stop. The Notice template places it; the 404 page never does.
 *
 * @package WPCredits_Theme
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpcredits_seconds = 5;
$wpcredits_home    = home_url( '/' );
$wpcredits_counts  = array();

for ( $wpcredits_left = 1; $wpcredits_left <= $wpcredits_seconds; $wpcredits_left++ ) {
	/* translators: %s: how many seconds are left before the page goes to the homepage. */
	$wpcredits_counts[] = sprintf( _n( 'Taking you to the homepage in %s second.', 'Taking you to the homepage in %s seconds.', $wpcredits_left, 'wpcredits-theme' ), number_format_i18n( $wpcredits_left ) );
}

$wpcredits_wrapper = array(
	'class'       => 'wpc-notice-forward',
	'data-home'   => $wpcredits_home,
	'data-counts' => wp_json_encode( $wpcredits_counts ),
	'data-stayed' => __( 'You\'re staying on this page.', 'wpcredits-theme' ),
);

?>
<div <?php echo get_block_wrapper_attributes( $wpcredits_wrapper ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already-escaped attribute string from core. ?>>
	<p class="wpc-notice-forward__line"><span class="wpc-notice-forward__count"><?php echo esc_html( end( $wpcredits_counts ) ); ?></span> <a class="wpc-notice-forward__go" href="<?php echo esc_url( $wpcredits_home ); ?>"><?php esc_html_e( 'Go now', 'wpcredits-theme' ); ?></a> <button type="button" class="wpc-notice-forward__stay"><?php esc_html_e( 'Stay on this page', 'wpcredits-theme' ); ?></button></p>
	<p class="wpc-notice-forward__said screen-reader-text" aria-live="polite"></p>
</div>
