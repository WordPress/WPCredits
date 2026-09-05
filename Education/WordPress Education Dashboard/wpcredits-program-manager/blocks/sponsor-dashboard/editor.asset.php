<?php
/**
 * Script dependencies for the sponsor dashboard block editor script.
 *
 * Hand-written rather than generated: editor.js is plain ES5 against the `wp.*`
 * globals, so there is no build step to produce this file. WordPress reads it
 * because block.json points `editorScript` at `file:./editor.js`.
 *
 * @package WPCreditsProgramManager
 */

return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-block-editor',
		'wp-components',
		'wp-element',
		'wp-i18n',
		'wp-server-side-render',
	),
	'version'      => '1.93.0',
);
