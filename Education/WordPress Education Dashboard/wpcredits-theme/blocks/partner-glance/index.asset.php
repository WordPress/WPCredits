<?php
/**
 * Dependencies for blocks/partner-glance/index.js, the block's editor.
 *
 * Hand-maintained because the theme has no build step; without it WordPress registers the editor
 * script with no dependencies, and `wp.blocks` is undefined by the time it runs. The editor's data
 * store is among them: the table is drawn from the Partner levels block the editor holds.
 *
 * @package WPCredits_Theme
 */

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-i18n', 'wp-data' ),
	'version'      => WPCREDITS_VERSION,
);
