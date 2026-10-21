<?php
/**
 * Title: Education Hub pathway
 * Slug: wpcredits/hub-pathway
 * Categories: wpcredits
 * Description: The pathway from a first workshop to a first job, and an institution's four partner levels, with a switch between the two.
 * Keywords: hub, pathway, steps, levels
 * Viewport Width: 1400
 *
 * The switch, the staircases and the details are the pathway block's.
 *
 * Parked for now (a program lead, 21 October 2026: "remove the pathway section for now", while its
 * details and badges are settled): the section is printed only when the hub shows its planned
 * content (`wpcredits_hub_shows_planned()`), with the hero's "Explore the pathway" button that leads
 * to it.
 *
 * @package WPCredits_Theme
 */

if ( ! wpcredits_hub_shows_planned() ) {
	return;
}

?>
<!-- wp:group {"anchor":"pathway","align":"wide","className":"wpc-hub-anchored","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div id="pathway" class="wp-block-group alignwide wpc-hub-anchored" style="padding-top:48px;padding-bottom:48px"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php esc_html_e( 'The pathway', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:wpcredits/pathway /--></div>
<!-- /wp:group -->
