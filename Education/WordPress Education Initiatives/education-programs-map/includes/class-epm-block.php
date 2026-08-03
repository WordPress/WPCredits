<?php
/**
 * Registers the "Education Programs Map" Gutenberg block as a wrapper around the
 * [education_programs_map] shortcode, so editors can insert it without typing shortcode syntax.
 *
 * @package Education_Programs_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPM_Block {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the block editor script and the block type itself.
	 */
	public function register() {
		wp_register_script(
			'epm-block-editor',
			EPM_PLUGIN_URL . 'assets/js/block.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-server-side-render',
				'wp-api-fetch',
				'wp-i18n',
			),
			EPM_VERSION,
			true
		);

		wp_register_style(
			'epm-block-editor',
			EPM_PLUGIN_URL . 'assets/css/block-editor.css',
			array(),
			EPM_VERSION
		);

		register_block_type(
			EPM_PLUGIN_DIR . 'block',
			array(
				'render_callback' => array( 'EPM_Shortcode', 'render_output' ),
			)
		);
	}
}
