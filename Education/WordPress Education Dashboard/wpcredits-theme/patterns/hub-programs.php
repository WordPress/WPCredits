<?php
/**
 * Title: Education Hub programs
 * Slug: wpcredits/hub-programs
 * Categories: wpcredits
 * Description: Every education program in one list, filtered by audience, and the fields of study WordPress Credits matches students to.
 * Keywords: hub, programs, education, field of study
 * Viewport Width: 1400
 *
 * The cards are the program index block's; the field-of-study table is a core Table block, so its
 * words are edited in the Site Editor. Below 640px the table scrolls inside its box, and the theme
 * makes that box reachable by keyboard (`wpcredits_hub_fields_region()`).
 *
 * @package WPCredits_Theme
 */

$wpcredits_fields = array(
	array( __( 'Computer science, engineering', 'wpcredits-theme' ), __( 'Core, plugins, testing, accessibility', 'wpcredits-theme' ), __( 'Merged code and reviewed patches', 'wpcredits-theme' ) ),
	array( __( 'Design, arts, UX', 'wpcredits-theme' ), __( 'Design, block patterns, themes', 'wpcredits-theme' ), __( 'Visual work in use on real sites', 'wpcredits-theme' ) ),
	array( __( 'Communications, journalism, marketing', 'wpcredits-theme' ), __( 'Documentation, Learn WordPress, Marketing', 'wpcredits-theme' ), __( 'Published work with a byline', 'wpcredits-theme' ) ),
	array( __( 'Translation, languages', 'wpcredits-theme' ), __( 'Polyglots', 'wpcredits-theme' ), __( 'Published translations', 'wpcredits-theme' ) ),
	array( __( 'Business, events, tourism', 'wpcredits-theme' ), __( 'Community, WordCamps', 'wpcredits-theme' ), __( 'An event you ran, with attendance numbers', 'wpcredits-theme' ) ),
	array( _x( 'Education', 'a field of study', 'wpcredits-theme' ), __( 'Learn WordPress lesson plans, Training', 'wpcredits-theme' ), __( 'Course material in public use', 'wpcredits-theme' ) ),
	array( __( 'Accessibility, social work', 'wpcredits-theme' ), __( 'Accessibility, testing', 'wpcredits-theme' ), __( 'Audit work on a global platform', 'wpcredits-theme' ) ),
);

?>
<!-- wp:group {"anchor":"programs","align":"wide","className":"wpc-hub-anchored","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div id="programs" class="wp-block-group alignwide wpc-hub-anchored" style="padding-top:48px;padding-bottom:48px"><!-- wp:group {"className":"wpc-hub-head","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-hub-head"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php esc_html_e( 'All programs', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Every education program, in one list', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede"} -->
<p class="wpc-hub-lede"><?php esc_html_e( 'Programs are free unless noted. You don\'t need to take them in order, but each one is designed to lead into the next.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:wpcredits/program-index /-->

<!-- wp:group {"className":"wpc-hub-fit","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-hub-fit"><!-- wp:group {"className":"wpc-hub-head","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-hub-head"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php esc_html_e( 'Matched to your field of study', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"anchor":"wpc-hub-fit","className":"wpc-hub-anchored"} -->
<h3 class="wp-block-heading wpc-hub-anchored" id="wpc-hub-fit"><?php esc_html_e( 'Find where you fit in WordPress Credits', 'wpcredits-theme' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede"} -->
<p class="wpc-hub-lede"><?php esc_html_e( 'You work in the area of WordPress that fits your degree, so a design student shows design work and a CS student shows reviewed code.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:table {"hasFixedLayout":false,"className":"wpc-hub-fields"} -->
<figure class="wp-block-table wpc-hub-fields"><table><thead><tr><th><?php esc_html_e( 'Field of study', 'wpcredits-theme' ); ?></th><th><?php esc_html_e( 'Where you\'ll work', 'wpcredits-theme' ); ?></th><th><?php esc_html_e( 'What you\'ll have to show', 'wpcredits-theme' ); ?></th></tr></thead><tbody>
<?php foreach ( $wpcredits_fields as $wpcredits_field ) : ?>
<tr><td><?php echo esc_html( $wpcredits_field[0] ); ?></td><td><?php echo esc_html( $wpcredits_field[1] ); ?></td><td><?php echo esc_html( $wpcredits_field[2] ); ?></td></tr>
<?php endforeach; ?>
</tbody></table></figure>
<!-- /wp:table --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
