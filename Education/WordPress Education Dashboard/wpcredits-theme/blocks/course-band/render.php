<?php
/**
 * Server render for a band of courses (theme 1.32.0): a program's courses under the Program index,
 * drawn from the band block's attributes and the Course cards it holds, which Administrators edit in
 * the Site Editor.
 *
 * WordPress Credits alone has courses: its band's ID is the program's slug and `-courses`, the
 * address its card's "Compare the five courses" links to, an attribute of the band. The band is a
 * section named by its heading, with the programs section's head (an eyebrow, a heading, a lede that
 * says access to a course comes through the institution) and a card for each course it holds
 * (blocks/course-card/render.php). It sits after the Program index, out of the index's reach, so the
 * filter chips leave it alone: under "For faculty" it stays, since an institution picks the course.
 *
 * Nothing empty is printed: a band with no anchor has no ID and no name to point at, a band with no
 * heading is named by none, a head with no text is left out, and a band with no course to draw prints
 * nothing at all.
 *
 * The lines are indented as theme 1.31's index printed the band, so the page's markup for the same
 * texts is byte for byte what it was. A line the band may leave out is wrapped in a condition opened
 * at the start of its line, which prints nothing either way.
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

$wpcredits_courses = wpcredits_hub_band_courses( $block ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its block.

if ( ! $wpcredits_courses ) {
	return;
}

$wpcredits_band    = wpcredits_hub_band( $attributes ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its attributes.
$wpcredits_anchor  = $wpcredits_band['anchor'];
$wpcredits_eyebrow = $wpcredits_band['eyebrow'];
$wpcredits_heading = $wpcredits_band['heading'];
$wpcredits_lede    = $wpcredits_band['lede'];
$wpcredits_named   = '' !== $wpcredits_anchor && ! wpcredits_hub_blank( $wpcredits_heading );
$wpcredits_head    = ! wpcredits_hub_blank( $wpcredits_eyebrow ) || ! wpcredits_hub_blank( $wpcredits_heading ) || ! wpcredits_hub_blank( $wpcredits_lede );

?>
		<section class="wpc-hub-courses"<?php echo '' !== $wpcredits_anchor ? ' id="' . esc_attr( $wpcredits_anchor ) . '"' : ''; ?><?php echo $wpcredits_named ? ' aria-labelledby="' . esc_attr( $wpcredits_anchor ) . '-title"' : ''; ?>>
<?php if ( $wpcredits_head ) : ?>
			<div class="wp-block-group wpc-hub-head">
<?php endif; ?>
<?php if ( ! wpcredits_hub_blank( $wpcredits_eyebrow ) ) : ?>
				<p class="is-style-eyebrow"><?php echo wp_kses( $wpcredits_eyebrow, wpcredits_hub_text_tags() ); ?></p>
<?php endif; ?>
<?php if ( ! wpcredits_hub_blank( $wpcredits_heading ) ) : ?>
				<h3<?php echo '' !== $wpcredits_anchor ? ' id="' . esc_attr( $wpcredits_anchor ) . '-title"' : ''; ?>><?php echo wp_kses( $wpcredits_heading, wpcredits_hub_text_tags() ); ?></h3>
<?php endif; ?>
<?php if ( ! wpcredits_hub_blank( $wpcredits_lede ) ) : ?>
				<p class="wpc-hub-lede"><?php echo wp_kses( $wpcredits_lede, wpcredits_hub_text_tags() ); ?></p>
<?php endif; ?>
<?php if ( $wpcredits_head ) : ?>
			</div>
<?php endif; ?>
			<ul class="wpc-hub-courses__grid">
				<?php foreach ( $wpcredits_courses as $wpcredits_course ) : ?>
					<?php echo $wpcredits_course . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The course card block's own render, escaped as it is built. ?>
				<?php endforeach; ?>
			</ul>
		</section>
