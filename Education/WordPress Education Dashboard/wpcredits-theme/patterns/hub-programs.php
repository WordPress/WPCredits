<?php
/**
 * Title: Education Hub programs
 * Slug: wpcredits/hub-programs
 * Categories: wpcredits
 * Description: Every education program in one list, filtered by audience, and the fields of study WordPress Credits matches students to.
 * Keywords: hub, programs, education, field of study
 * Viewport Width: 1400
 *
 * The cards are the program index block's Program cards, one for each program of
 * `wpcredits_hub_programs()`, written with its texts translated (theme 1.32.0), so an Administrator
 * edits each in the Site Editor; under the index, the Courses band of WordPress Credits' courses, its
 * head and its Course cards written the same way. "Find where you fit" is a flow, one row per field of
 * study read left to right: the field, an arrow, the teams it joins as tags, an arrow, and what the
 * student will have to show behind a check mark (the owner's option A, 18 October 2026). It is the
 * Field of study rows block since theme 1.32.0 (a Custom HTML block before), a row block for each row
 * of `wpcredits_hub_fit_rows()`, so each of its words is edited in the Site Editor where it is shown;
 * the section's eyebrow, heading and lede stay core blocks. The rows are a list: a
 * screen reader hears each row as one item, the field, then each step's name from a visually hidden
 * label and the step itself; the header line above the rows says the same names to the eye and is
 * hidden from screen readers, as are the arrows and the check. Both lists say `role="list"`, since
 * WebKit stops announcing a list whose bullets the sheet removes. Below 768px each row stacks (hub.css).
 *
 * @package WPCredits_Theme
 */

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

<?php echo wpcredits_hub_program_index_markup( wpcredits_hub_programs() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup, its texts escaped as they are written. ?>

<?php echo wpcredits_hub_course_bands_markup( wpcredits_hub_programs() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup, its texts escaped as they are written. ?>


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

<?php echo wpcredits_hub_fit_list_markup( wpcredits_hub_fit_rows() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup, its texts escaped as they are written. ?></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
