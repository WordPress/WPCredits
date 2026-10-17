<?php
/**
 * Server render for the handbook launcher.
 *
 * The button and the panel it opens live on opposite sides of the plugin/theme line: the
 * plugin owns the assistant and prints the panel, the theme decides where the way in
 * belongs. They meet at one data attribute, `data-wpcpm-handbook-open` - so this theme can
 * move the button, and another theme can leave it out, without either side changing. The
 * header's menu script also reads the class `wpcpm-hb-open` the plugin puts on the root
 * element while its panel is open, to give the focus back to the Menu button when the panel
 * closes; bin/check-selectors.php checks that the plugin still sets it.
 *
 * Renders nothing at all unless the plugin says this reader can use the assistant. That
 * single call covers the plugin being inactive, the assistant being switched off, the
 * handbook never having been synced, and the reader being logged out or outside the
 * configured audience - none of which the theme should be second-guessing on its own.
 *
 * @package WPCredits_Theme
 */

if ( ! wpcredits_handbook_available() ) {
	return;
}

printf(
	// get_block_wrapper_attributes() returns an already-escaped attribute string;
	// running it through an escaper again would mangle the quotes.
	'<button type="button" %1$s data-wpcpm-handbook-open>%2$s</button>',
	get_block_wrapper_attributes( array( 'class' => 'wpc-ask' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_html__( 'Need help?', 'wpcredits-theme' )
);
