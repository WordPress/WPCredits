<?php
/**
 * Server render for a row of "Find where you fit" (theme 1.32.0): one field of study, drawn from the
 * row block's attributes, which Administrators edit in the Site Editor.
 *
 * A row reads left to right: the field, an arrow, the teams it joins as tags, an arrow, and what the
 * student will have to show behind a check mark. A screen reader hears it as one item of the list: the
 * field, then the label its list hands it as block context ("The team you join:") and the teams, then
 * the other label ("What you'll have to show:") and the proof; the arrows and the check are hidden
 * from it. The teams' list says `role="list"`, since WebKit stops announcing a list whose bullets the
 * sheet removes. Nothing empty is printed: a row with no field draws nothing, a row with no teams keeps
 * its column empty with no label and no list, a row with no proof draws no arrow pointing at nothing,
 * and an empty label is left out.
 *
 * The markup is the list's own: the list draws each row it holds by this render, and the row is
 * written as theme 1.31's Custom HTML block held it, so the page's markup for the same texts is byte
 * for byte what it was.
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

$wpcredits_row = wpcredits_hub_fit_row( $attributes ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its attributes.

if ( wpcredits_hub_blank( $wpcredits_row['field'] ) ) {
	return;
}

// The labels the list hands its rows, or today's words for a row drawn on its own.
$wpcredits_labels  = wpcredits_hub_fit_labels();
$wpcredits_context = $block instanceof WP_Block ? $block->context : array(); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its block.
$wpcredits_teams   = array_key_exists( 'wpcredits/fitTeamsLabel', $wpcredits_context ) ? trim( (string) $wpcredits_context['wpcredits/fitTeamsLabel'] ) : $wpcredits_labels['teamsLabel'];
$wpcredits_proof   = array_key_exists( 'wpcredits/fitProofLabel', $wpcredits_context ) ? trim( (string) $wpcredits_context['wpcredits/fitProofLabel'] ) : $wpcredits_labels['proofLabel'];

// The two pictures, decoration only: a right arrow (turned down on a phone by hub.css) and a check
// mark, each drawn in its rule's color through currentColor.
$wpcredits_arrow = '<svg class="wpc-hub-flow__arrow" viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false"><path d="M1.5 10h16M10.5 3l7 7-7 7"/></svg>';
$wpcredits_check = '<svg class="wpc-hub-flow__check" viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M2.5 8.5 6 12l7.5-8"/></svg>';

// The teams, a tag each, behind their label; nothing at all for a row with none.
$wpcredits_tags = '';

if ( $wpcredits_row['teams'] ) {
	$wpcredits_tags = ( '' !== $wpcredits_teams ? '<span class="screen-reader-text">' . esc_html( $wpcredits_teams ) . '</span>' : '' ) . '<ul class="wpc-hub-flow__tags" role="list">' . "\n";

	foreach ( $wpcredits_row['teams'] as $wpcredits_team ) {
		$wpcredits_tags .= "\t\t" . '<li class="wpc-hub-flow__tag">' . esc_html( $wpcredits_team ) . '</li>' . "\n";
	}

	$wpcredits_tags .= "\t" . '</ul>';
}

// The arrow to the proof and the proof behind its check and its label; nothing for a row with none.
$wpcredits_shown = '';

if ( ! wpcredits_hub_blank( $wpcredits_row['proof'] ) ) {
	$wpcredits_shown = $wpcredits_arrow . '<p class="wpc-hub-flow__proof">' . $wpcredits_check . '<span class="wpc-hub-flow__shown">' . ( '' !== $wpcredits_proof ? '<span class="screen-reader-text">' . esc_html( $wpcredits_proof ) . '</span> ' : '' ) . wp_kses( $wpcredits_row['proof'], wpcredits_hub_text_tags() ) . '</span></p>';
}

?>
<li class="wpc-hub-flow__row"><p class="wpc-hub-flow__field"><?php echo wp_kses( $wpcredits_row['field'], wpcredits_hub_text_tags() ); ?></p><?php echo $wpcredits_arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup, written above. ?><div class="wpc-hub-flow__teams"><?php echo $wpcredits_tags; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built. ?></div><?php echo $wpcredits_shown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built. ?></li>
