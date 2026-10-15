<?php
/**
 * Dependencies for blocks/program-stat/index.js, the editor's preview.
 *
 * Hand-maintained because the theme has no build step; without it WordPress registers the editor
 * script with no dependencies, and `wp.blocks` is undefined by the time it runs.
 *
 * @package WPCredits_Theme
 */

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render' ),
	'version'      => WPCREDITS_VERSION,
);
