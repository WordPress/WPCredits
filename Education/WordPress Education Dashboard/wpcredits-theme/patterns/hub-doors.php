<?php
/**
 * Title: Education Hub doors
 * Slug: wpcredits/hub-doors
 * Categories: wpcredits
 * Description: Three cards, one each for students, institutions and companies, each leading to where that reader starts.
 * Keywords: hub, audiences, students, institutions, companies
 * Viewport Width: 1400
 *
 * Each card's link is stretched over the whole card, and the focus ring is drawn around the card
 * (hub.css). The institutions' link is the site's own application, read when the pattern renders.
 * Each card opens with a small picture of a WordPress editor piece in the card's own words: decoration,
 * hidden from screen readers, with nothing in it to focus.
 *
 * @package WPCredits_Theme
 */

$wpcredits_doors = array(
	array(
		'eyebrow' => __( 'Students', 'wpcredits-theme' ),
		'title'   => __( 'Build a site, make your first contribution, earn credit.', 'wpcredits-theme' ),
		'text'    => __( 'Programs matched to your field of study, from translation and design to engineering.', 'wpcredits-theme' ),
		'link'    => __( 'Browse programs', 'wpcredits-theme' ),
		'href'    => '#programs',
		'picture' => '<div class="wpc-hub-pic__piece wpc-hub-pic__badge"><span class="wpc-hub-art__mark"></span><span><span class="wpc-hub-art__strong">' . esc_html__( 'Your first contribution', 'wpcredits-theme' ) . '</span><span class="wpc-hub-art__small">' . esc_html__( 'Earn credit for it', 'wpcredits-theme' ) . '</span></span></div>',
	),
	array(
		'eyebrow' => __( 'Institutions', 'wpcredits-theme' ),
		'title'   => __( 'Bring a free, ready-to-run program to your students.', 'wpcredits-theme' ),
		'text'    => __( 'Start with a one-day workshop or a WordPress Credits pilot. Each new group of students builds a stronger partnership.', 'wpcredits-theme' ),
		'link'    => __( 'Become a partner', 'wpcredits-theme' ),
		'href'    => wpcredits_hub_url( 'institution_application' ),
		'picture' => '<div class="wpc-hub-pic__piece wpc-hub-pic__list"><span class="wpc-hub-art__strong">' . esc_html__( 'Ready-to-run program', 'wpcredits-theme' ) . '</span><span class="wpc-hub-pic__row">' . esc_html__( 'One-day workshop', 'wpcredits-theme' ) . '</span><span class="wpc-hub-pic__row">' . esc_html__( 'WordPress Credits pilot', 'wpcredits-theme' ) . '</span></div>',
	),
	array(
		'eyebrow' => __( 'Companies', 'wpcredits-theme' ),
		'title'   => __( 'Hire people with verified WordPress experience.', 'wpcredits-theme' ),
		'text'    => __( 'Browse graduates open to work, or post internships and entry-level roles.', 'wpcredits-theme' ),
		'link'    => __( 'Hire from WordPress', 'wpcredits-theme' ),
		'href'    => 'https://jobs.wordpress.net/',
		'picture' => '<div class="wpc-hub-pic__piece wpc-hub-pic__profile"><span class="wpc-hub-pic__person"></span><span><span class="wpc-hub-art__strong">' . esc_html__( 'Verified WordPress experience', 'wpcredits-theme' ) . '</span><span class="wpc-hub-art__small">' . esc_html__( 'Graduates open to work', 'wpcredits-theme' ) . '</span></span></div>',
	),
);

?>
<!-- wp:group {"align":"wide","className":"wpc-hub-doors","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-hub-doors" style="padding-top:48px;padding-bottom:48px"><!-- wp:heading {"className":"screen-reader-text"} -->
<h2 class="wp-block-heading screen-reader-text"><?php esc_html_e( 'Who this is for', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:columns {"className":"wpc-hub-doors__grid wpc-hub-stack"} -->
<div class="wp-block-columns wpc-hub-doors__grid wpc-hub-stack">
<?php foreach ( $wpcredits_doors as $wpcredits_door ) : ?>
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"is-style-card wpc-hub-door","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-card wpc-hub-door"><!-- wp:html -->
<div class="wpc-hub-pic" aria-hidden="true"><?php echo $wpcredits_door['picture']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup, its words escaped as it is built above. ?></div>
<!-- /wp:html -->

<!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html( $wpcredits_door['eyebrow'] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html( $wpcredits_door['title'] ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-door__text"} -->
<p class="wpc-hub-door__text"><?php echo esc_html( $wpcredits_door['text'] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"wpc-hub-door__go"} -->
<p class="wpc-hub-door__go"><a href="<?php echo esc_url( $wpcredits_door['href'] ); ?>"><?php echo esc_html( $wpcredits_door['link'] ); ?><span aria-hidden="true"> →</span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->
<?php endforeach; ?>
</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
