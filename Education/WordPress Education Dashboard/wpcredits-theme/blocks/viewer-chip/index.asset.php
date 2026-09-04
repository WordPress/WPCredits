<?php
/**
 * Dependencies for blocks/viewer-chip/index.js.
 *
 * Hand-maintained because the theme has no build step - this is the file
 * `@wordpress/scripts` would otherwise generate. Without it WordPress registers
 * the editor script with no dependencies and `wp.blocks` is undefined by the time
 * it runs.
 *
 * @package WPCredits_Theme
 */

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n' ),
	'version'      => '1.0.0',
);
