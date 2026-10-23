<?php
/**
 * Server render for the comparison table (theme 1.32.1): the Levels at a glance block, drawn from the
 * levels of the Partners template in use (`wpcredits_partner_levels()`), the levels its Partner levels
 * block holds, so the table and the cards cannot disagree (the design's D5, as ruling R1 has it).
 *
 * A region named by the section's heading (#wpc-partners-glance-title), a tab stop so a keyboard can
 * scroll it on a phone, holding the table: a head of the head word ("Recognition") and a column for
 * each level, its name and its short amount; then a row for each thing each level includes, the lowest
 * level's first, in the level's own words, saying Yes from the level that lists it up and No below it.
 * The three words are the block's, typed in its settings, or the translated strings 1.31.11's pattern
 * printed (`wpcredits_partner_glance_words()`). A level with no short amount has none in the head.
 * With no levels, or none that includes anything, it prints nothing, no empty table.
 *
 * Written as theme 1.31.11's Custom HTML block held the table, the blank line before it and the new
 * line after it among them, so the #glance section's markup for the same texts is byte for byte what
 * it was, but for the rows' words: 1.31.11 kept a row list of its own, and the rows now read as the
 * items do.
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

$wpcredits_levels = wpcredits_partner_levels();
$wpcredits_rows   = wpcredits_partner_glance_rows( $wpcredits_levels );

if ( ! $wpcredits_rows ) {
	return;
}

$wpcredits_words = wpcredits_partner_glance_words( $attributes ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its attributes.
$wpcredits_heads = array();

foreach ( $wpcredits_levels as $wpcredits_level ) {
	$wpcredits_heads[] = '<span class="wpc-partners-glance__level">' . wp_kses( $wpcredits_level['name'], wpcredits_hub_text_tags() ) . '</span>'
		. ( '' !== $wpcredits_level['short'] ? ' <span class="wpc-partners-glance__amount">' . esc_html( $wpcredits_level['short'] ) . '</span>' : '' );
}

?>

<div class="wpc-partners-glance__scroll" tabindex="0" role="region" aria-labelledby="wpc-partners-glance-title">
	<table class="wpc-partners-glance__table">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html( $wpcredits_words['label'] ); ?></th>
				<?php foreach ( $wpcredits_heads as $wpcredits_head ) : ?>
					<th scope="col"><?php echo $wpcredits_head; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built. ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $wpcredits_rows as $wpcredits_row ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $wpcredits_row['item'] ); ?></th>
					<?php foreach ( array_keys( $wpcredits_levels ) as $wpcredits_at ) : ?>
						<?php if ( $wpcredits_at >= $wpcredits_row['from'] ) : ?>
							<td class="wpc-partners-glance__yes"><?php echo esc_html( $wpcredits_words['yes'] ); ?></td>
						<?php else : ?>
							<td class="wpc-partners-glance__no"><?php echo esc_html( $wpcredits_words['no'] ); ?></td>
						<?php endif; ?>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
