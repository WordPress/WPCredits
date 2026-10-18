<?php
/**
 * Title: Education Hub programs
 * Slug: wpcredits/hub-programs
 * Categories: wpcredits
 * Description: Every education program in one list, filtered by audience, and the fields of study WordPress Credits matches students to.
 * Keywords: hub, programs, education, field of study
 * Viewport Width: 1400
 *
 * The cards are the program index block's. "Find where you fit" is a flow, one row per field of
 * study read left to right: the field, an arrow, the teams it joins as tags, an arrow, and what the
 * student will have to show behind a check mark (the owner's option A, 18 October 2026). It is a
 * Custom HTML block, so its words are edited in the Site Editor as markup. The rows are a list: a
 * screen reader hears each row as one item, the field, then each step's name from a visually hidden
 * label and the step itself; the header line above the rows says the same names to the eye and is
 * hidden from screen readers, as are the arrows and the check. Both lists say `role="list"`, since
 * WebKit stops announcing a list whose bullets the sheet removes. Below 768px each row stacks (hub.css).
 *
 * @package WPCredits_Theme
 */

$wpcredits_fields = array(
	array(
		'field' => __( 'Computer science, engineering', 'wpcredits-theme' ),
		'teams' => array(
			_x( 'Core', 'a WordPress team a student joins', 'wpcredits-theme' ),
			_x( 'Plugins', 'a WordPress team a student joins', 'wpcredits-theme' ),
			_x( 'Testing', 'a WordPress team a student joins', 'wpcredits-theme' ),
			_x( 'Accessibility', 'a WordPress team a student joins', 'wpcredits-theme' ),
		),
		'proof' => __( 'Merged code and reviewed patches', 'wpcredits-theme' ),
	),
	array(
		'field' => __( 'Design, arts, UX', 'wpcredits-theme' ),
		'teams' => array(
			_x( 'Design', 'a WordPress team a student joins', 'wpcredits-theme' ),
			_x( 'Block patterns', 'a WordPress team a student joins', 'wpcredits-theme' ),
			_x( 'Themes', 'a WordPress team a student joins', 'wpcredits-theme' ),
		),
		'proof' => __( 'Visual work in use on real sites', 'wpcredits-theme' ),
	),
	array(
		'field' => __( 'Communications, journalism, marketing', 'wpcredits-theme' ),
		'teams' => array(
			_x( 'Documentation', 'a WordPress team a student joins', 'wpcredits-theme' ),
			_x( 'Learn WordPress', 'a WordPress team a student joins', 'wpcredits-theme' ),
			_x( 'Marketing', 'a WordPress team a student joins', 'wpcredits-theme' ),
		),
		'proof' => __( 'Published work with a byline', 'wpcredits-theme' ),
	),
	array(
		'field' => __( 'Translation, languages', 'wpcredits-theme' ),
		'teams' => array(
			_x( 'Polyglots', 'a WordPress team a student joins', 'wpcredits-theme' ),
		),
		'proof' => __( 'Published translations', 'wpcredits-theme' ),
	),
	array(
		'field' => __( 'Business, events, tourism', 'wpcredits-theme' ),
		'teams' => array(
			_x( 'Community', 'a WordPress team a student joins', 'wpcredits-theme' ),
			_x( 'WordCamps', 'a WordPress team a student joins', 'wpcredits-theme' ),
		),
		'proof' => __( 'An event you ran, with attendance numbers', 'wpcredits-theme' ),
	),
	array(
		'field' => _x( 'Education', 'a field of study', 'wpcredits-theme' ),
		'teams' => array(
			_x( 'Learn WordPress lesson plans', 'a WordPress team a student joins', 'wpcredits-theme' ),
			_x( 'Training', 'a WordPress team a student joins', 'wpcredits-theme' ),
		),
		'proof' => __( 'Course material in public use', 'wpcredits-theme' ),
	),
	array(
		'field' => __( 'Accessibility, social work', 'wpcredits-theme' ),
		'teams' => array(
			_x( 'Accessibility', 'a WordPress team a student joins', 'wpcredits-theme' ),
			_x( 'Testing', 'a WordPress team a student joins', 'wpcredits-theme' ),
		),
		'proof' => __( 'Audit work on a global platform', 'wpcredits-theme' ),
	),
);

// The two pictures, decoration only: a right arrow (turned down on a phone by hub.css) and a check
// mark, each drawn in its rule's color through currentColor.
$wpcredits_arrow = '<svg class="wpc-hub-flow__arrow" viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false"><path d="M1.5 10h16M10.5 3l7 7-7 7"/></svg>';
$wpcredits_check = '<svg class="wpc-hub-flow__check" viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M2.5 8.5 6 12l7.5-8"/></svg>';

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

<!-- wp:html -->
<div class="wpc-hub-flow">
<div class="wpc-hub-flow__head" aria-hidden="true"><span class="wpc-hub-flow__step wpc-hub-flow__step--field"><?php esc_html_e( 'Your field of study', 'wpcredits-theme' ); ?></span><span class="wpc-hub-flow__step wpc-hub-flow__step--teams"><?php esc_html_e( 'The team you join', 'wpcredits-theme' ); ?></span><span class="wpc-hub-flow__step wpc-hub-flow__step--proof"><?php esc_html_e( 'What you\'ll have to show', 'wpcredits-theme' ); ?></span></div>
<ul class="wpc-hub-flow__rows" role="list" aria-labelledby="wpc-hub-fit">
<?php foreach ( $wpcredits_fields as $wpcredits_field ) : ?>
<li class="wpc-hub-flow__row"><p class="wpc-hub-flow__field"><?php echo esc_html( $wpcredits_field['field'] ); ?></p><?php echo $wpcredits_arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup, written above. ?><div class="wpc-hub-flow__teams"><span class="screen-reader-text"><?php /* translators: Read by screen readers before the teams a field of study joins; the header line above the rows shows "The team you join" to the eye. */ esc_html_e( 'The team you join:', 'wpcredits-theme' ); ?></span><ul class="wpc-hub-flow__tags" role="list">
	<?php foreach ( $wpcredits_field['teams'] as $wpcredits_team ) : ?>
	<li class="wpc-hub-flow__tag"><?php echo esc_html( $wpcredits_team ); ?></li>
	<?php endforeach; ?>
</ul></div><?php echo $wpcredits_arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup, written above. ?><p class="wpc-hub-flow__proof"><?php echo $wpcredits_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup, written above. ?><span class="wpc-hub-flow__shown"><span class="screen-reader-text"><?php /* translators: Read by screen readers before what a student will have to show for a field of study; the header line above the rows shows "What you'll have to show" to the eye. */ esc_html_e( 'What you\'ll have to show:', 'wpcredits-theme' ); ?></span> <?php echo esc_html( $wpcredits_field['proof'] ); ?></span></p></li>
<?php endforeach; ?>
</ul>
</div>
<!-- /wp:html --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
