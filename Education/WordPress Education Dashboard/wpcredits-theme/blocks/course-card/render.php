<?php
/**
 * Server render for a course card (theme 1.32.0): one course of a Courses band, drawn from the card
 * block's attributes, which Administrators edit in the Site Editor.
 *
 * Its name, linked to its public page with a decorative arrow a screen reader skips, then the line the
 * stylesheet draws above the name (its hours, or who it is for), so a screen reader that jumps to the
 * name hears the line after it, and what it asks. A course with no address names it unlinked and
 * with no arrow; an empty line is left out; a course with no name is a draft and draws nothing.
 *
 * The markup is the band's own: the band draws each course it holds by this render, inside its list,
 * and the lines are indented as theme 1.31's index printed them, so the page's markup for the same
 * texts is byte for byte what it was. A line the card may leave out is wrapped in a condition opened
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

$wpcredits_course = wpcredits_hub_course_card( $attributes ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its attributes.

if ( wpcredits_hub_blank( $wpcredits_course['name'] ) ) {
	return;
}

$wpcredits_href = esc_url( $wpcredits_course['url'] );

?>
					<li class="wpc-hub-course">
<?php if ( '' !== $wpcredits_href ) : ?>
						<h4 class="wpc-hub-course__name"><a href="<?php echo $wpcredits_href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by esc_url() above. ?>"><?php echo wp_kses( $wpcredits_course['name'], wpcredits_hub_text_tags() ); ?><span aria-hidden="true"> →</span></a></h4>
<?php else : ?>
						<h4 class="wpc-hub-course__name"><?php echo wp_kses( $wpcredits_course['name'], wpcredits_hub_text_tags() ); ?></h4>
<?php endif; ?>
<?php if ( ! wpcredits_hub_blank( $wpcredits_course['eyebrow'] ) ) : ?>
						<p class="wpc-hub-course__meta"><?php echo wp_kses( $wpcredits_course['eyebrow'], wpcredits_hub_text_tags() ); ?></p>
<?php endif; ?>
<?php if ( ! wpcredits_hub_blank( $wpcredits_course['line'] ) ) : ?>
						<p class="wpc-hub-course__line"><?php echo wp_kses( $wpcredits_course['line'], wpcredits_hub_text_tags() ); ?></p>
<?php endif; ?>
					</li>
