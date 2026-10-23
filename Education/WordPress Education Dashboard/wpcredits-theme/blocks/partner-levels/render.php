<?php
/**
 * Server render for the partnership levels (theme 1.32.1): the Partner levels block, drawn from the
 * Partner level blocks it holds, which Administrators edit in the Site Editor.
 *
 * The cards, one for each level it holds, in its order, each by the level block's own render
 * (blocks/partner-level/render.php), a level with no name left out. With no level to draw it prints
 * nothing at all, no empty list.
 *
 * Written as theme 1.31.11's Custom HTML block held the cards, the blank line before the list and the
 * new line after it among them, so the #levels section's markup for the same texts is byte for byte
 * what it was. The Levels at a glance table and the plugin's sponsor application read the levels of
 * the Partners template in use, this block's (`wpcredits_partner_levels()`), so they say what the cards
 * say.
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

$wpcredits_cards = wpcredits_partner_levels_cards( $block ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its block.

if ( ! $wpcredits_cards ) {
	return;
}

?>

<ul class="wpc-partners-levels__grid">
<?php foreach ( $wpcredits_cards as $wpcredits_card ) : ?>
	<?php echo $wpcredits_card . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The level block's own render, escaped as it is built. ?>
<?php endforeach; ?>
</ul>
