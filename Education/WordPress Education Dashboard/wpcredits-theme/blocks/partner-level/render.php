<?php
/**
 * Server render for a partnership level (theme 1.32.1): one card of the Partner levels, drawn from the
 * level block's attributes, which Administrators edit in the Site Editor.
 *
 * The card's heading is the level's name, its tag after it in a span of its own where it has one, which
 * marks the card a program's (`wpc-partners-level--program`); then its amount, the line before its list
 * and the list of what it includes. Nothing empty is printed: a level with no tag has no tag and no
 * program class, an empty amount or lead no paragraph, a level with no item no list, and a level with
 * no name is a draft and draws nothing. The name, tag, amount and lead keep the editor's bold and
 * italic; the items, typed in the block's settings, are text.
 *
 * The markup is the list's own: the Partner levels block draws each level it holds by this render,
 * and the lines are indented as theme 1.31.11's Custom HTML block held them, so the page's markup for
 * the same texts is byte for byte what it was. A line the card may leave out is wrapped in a condition
 * opened at the start of its line, which prints nothing either way.
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

$wpcredits_level = wpcredits_partner_level( $attributes ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its attributes.

if ( wpcredits_hub_blank( $wpcredits_level['name'] ) ) {
	return;
}

$wpcredits_tagged = ! wpcredits_hub_blank( $wpcredits_level['tag'] );
$wpcredits_tag    = $wpcredits_tagged ? ' <span class="wpc-partners-tag">' . wp_kses( $wpcredits_level['tag'], wpcredits_hub_text_tags() ) . '</span>' : '';

?>
	<li class="wpc-partners-level<?php echo $wpcredits_tagged ? ' wpc-partners-level--program' : ''; ?>">
		<h3 class="wpc-partners-level__name"><?php echo wp_kses( $wpcredits_level['name'], wpcredits_hub_text_tags() ); ?><?php echo $wpcredits_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built. ?></h3>
<?php if ( ! wpcredits_hub_blank( $wpcredits_level['amount'] ) ) : ?>
		<p class="wpc-partners-level__amount"><?php echo wp_kses( $wpcredits_level['amount'], wpcredits_hub_text_tags() ); ?></p>
<?php endif; ?>
<?php if ( ! wpcredits_hub_blank( $wpcredits_level['lead'] ) ) : ?>
		<p class="wpc-partners-level__lead"><?php echo wp_kses( $wpcredits_level['lead'], wpcredits_hub_text_tags() ); ?></p>
<?php endif; ?>
<?php if ( $wpcredits_level['items'] ) : ?>
		<ul class="wpc-partners-level__items">
		<?php foreach ( $wpcredits_level['items'] as $wpcredits_item ) : ?>
			<li><?php echo esc_html( $wpcredits_item ); ?></li>
		<?php endforeach; ?>
		</ul>
<?php endif; ?>
	</li>
