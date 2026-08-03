<?php
/**
 * Gutenberg block registration.
 *
 * @package CreditsProgramMentors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Credits Program Mentors" dynamic block.
 *
 * The block is server-rendered and delegates to CREDPRME_Shortcode, so the block and
 * the [credits_program_mentors] shortcode always produce identical markup.
 */
class CREDPRME_Block {

	/**
	 * Shortcode renderer, reused for server-side block output.
	 *
	 * @var CREDPRME_Shortcode
	 */
	private $shortcode;

	/**
	 * Constructor.
	 *
	 * @param CREDPRME_Shortcode $shortcode Shared shortcode instance.
	 */
	public function __construct( CREDPRME_Shortcode $shortcode ) {
		$this->shortcode = $shortcode;
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the editor script and the block type.
	 */
	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return; // Classic-only WordPress; nothing to do.
		}

		wp_register_script(
			'credits-program-mentors-block',
			CREDPRME_PLUGIN_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			CREDPRME_VERSION,
			true
		);

		// Register the shared stylesheet here too, so the block.json "style" /
		// "editorStyle" handle exists in both front-end and editor contexts.
		if ( ! wp_style_is( 'credits-program-mentors', 'registered' ) ) {
			wp_register_style(
				'credits-program-mentors',
				CREDPRME_PLUGIN_URL . 'assets/css/credits-program-mentors.css',
				array(),
				CREDPRME_VERSION
			);
		}

		wp_set_script_translations( 'credits-program-mentors-block', 'credits-program-mentors' );

		register_block_type(
			CREDPRME_PLUGIN_DIR . 'blocks/credits-program-mentors',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Server-side render callback: map block attributes onto the shortcode.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$atts = array(
			'layout'   => isset( $attributes['layout'] ) ? $attributes['layout'] : 'grid',
			'columns'  => isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3,
			'limit'    => isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 0,
			'country'  => isset( $attributes['country'] ) ? $attributes['country'] : '',
			'language' => isset( $attributes['language'] ) ? $attributes['language'] : '',
			'search'   => isset( $attributes['search'] ) ? $attributes['search'] : '',
			'fields'     => isset( $attributes['fields'] ) ? $attributes['fields'] : '',
			'view'       => isset( $attributes['view'] ) ? $attributes['view'] : '',
			'photos'     => ( ! isset( $attributes['photos'] ) || $attributes['photos'] ) ? 'yes' : 'no',
			'photo_size' => isset( $attributes['photoSize'] ) ? (int) $attributes['photoSize'] : 116,
			'filters'    => ( ! isset( $attributes['filters'] ) || $attributes['filters'] ) ? 'yes' : 'no',
		);

		$output = $this->shortcode->render( $atts );

		// In the editor preview, never return an empty string (ServerSideRender
		// shows a generic "not available" notice for empty output).
		if ( '' === trim( (string) $output ) && $this->is_editor_preview() ) {
			return '<div class="credprme-mentors credprme-mentors--empty"><p>' . esc_html__( 'Nothing to preview yet. Add your Airtable token in the Credits Program Mentors menu, or adjust the filters.', 'credits-program-mentors' ) . '</p></div>';
		}

		return $output;
	}

	/**
	 * Detect the block-renderer REST request used for editor previews.
	 *
	 * @return bool
	 */
	private function is_editor_preview() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}
}
