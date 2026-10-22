<?php
/**
 * Server render for "Find where you fit" (theme 1.32.0): the Field of study rows block, drawn from its
 * attributes and the rows it holds, which Administrators edit in the Site Editor.
 *
 * A flow, one row per field of study read left to right (the owner's option A, 18 October 2026): the
 * header line names the three steps to the eye and is hidden from screen readers, and the rows are a
 * list named by the section's heading (#wpc-hub-fit), each row drawn by its own render
 * (blocks/fit-row/render.php) with the two labels a screen reader hears, which this block hands its
 * rows as block context. The list says `role="list"`, since WebKit stops announcing a list whose
 * bullets the sheet removes. Below 768px each row stacks (hub.css). A header line with no name is left
 * out, and a list with no row to draw prints nothing.
 *
 * Written as theme 1.31's Custom HTML block held the flow, so the page's markup for the same texts is
 * byte for byte what it was.
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

$wpcredits_rows = wpcredits_hub_fit_list_rows( $block ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its block.

if ( ! $wpcredits_rows ) {
	return;
}

$wpcredits_heads = array();

foreach ( array( 'field', 'teams', 'proof' ) as $wpcredits_step ) {
	$wpcredits_heads[ $wpcredits_step ] = isset( $attributes[ $wpcredits_step . 'Heading' ] ) && is_scalar( $attributes[ $wpcredits_step . 'Heading' ] ) ? (string) $attributes[ $wpcredits_step . 'Heading' ] : ''; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its attributes.
}

?>
<div class="wpc-hub-flow">
<?php if ( ! wpcredits_hub_blank( implode( '', $wpcredits_heads ) ) ) : ?>
<div class="wpc-hub-flow__head" aria-hidden="true"><span class="wpc-hub-flow__step wpc-hub-flow__step--field"><?php echo wp_kses( $wpcredits_heads['field'], wpcredits_hub_text_tags() ); ?></span><span class="wpc-hub-flow__step wpc-hub-flow__step--teams"><?php echo wp_kses( $wpcredits_heads['teams'], wpcredits_hub_text_tags() ); ?></span><span class="wpc-hub-flow__step wpc-hub-flow__step--proof"><?php echo wp_kses( $wpcredits_heads['proof'], wpcredits_hub_text_tags() ); ?></span></div>
<?php endif; ?>
<ul class="wpc-hub-flow__rows" role="list" aria-labelledby="wpc-hub-fit">
<?php echo implode( "\n", $wpcredits_rows ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each row block's own render, escaped as it is built. ?>
</ul>
</div>
