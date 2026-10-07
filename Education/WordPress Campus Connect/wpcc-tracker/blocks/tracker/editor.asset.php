<?php
/**
 * Dependency manifest for the block editor script (no build step).
 *
 * @package WPCC_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-element',
		'wp-block-editor',
		'wp-components',
		'wp-i18n',
	),
	'version'      => WPCCT_VERSION,
);
