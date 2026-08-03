<?php
/**
 * Gutenberg block registration for the Contributor Team Matcher quiz.
 *
 * @package Find_Your_Team
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the contributor-team-matcher/quiz block.
 */
class CONTTEMA_Block {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	/**
	 * Register the block type using block.json, with a server-side render callback.
	 */
	public static function register_block() {
		register_block_type(
			CONTTEMA_PLUGIN_DIR . 'blocks/contributor-team-matcher-quiz/',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * Render callback — outputs the shortcode on the frontend.
	 *
	 * @return string
	 */
	public static function render_block() {
		return wp_kses_post( do_shortcode( '[conttema_quiz]' ) );
	}
}
