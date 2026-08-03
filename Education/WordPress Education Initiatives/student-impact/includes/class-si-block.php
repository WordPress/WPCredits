<?php
/**
 * Registers the plugin's dynamic blocks (server-rendered, no build step):
 * "Student Stories" (showcase) and "Graduate Stats" (class-wide totals).
 *
 * @package Student_Impact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SI_Block {

	/** @var SI_Block|null */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return SI_Block
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register' ), 20 );
	}

	/**
	 * Register the blocks from their block.json with PHP render callbacks.
	 */
	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		register_block_type(
			SI_DIR . 'blocks/showcase',
			array( 'render_callback' => array( $this, 'render' ) )
		);
		register_block_type(
			SI_DIR . 'blocks/stats',
			array( 'render_callback' => array( $this, 'render_stats' ) )
		);
	}

	/**
	 * Render callback for the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$attributes = wp_parse_args(
			is_array( $attributes ) ? $attributes : array(),
			array(
				'count'       => 0,
				'columns'     => 3,
				'title'       => __( 'Student Stories', 'student-impact' ),
				'subtitle'    => __( 'Our top graduating contributors, ranked by real WordPress.org impact.', 'student-impact' ),
				'showFilters' => true,
			)
		);

		$inner = SI_Render::render(
			array(
				'count'    => (int) $attributes['count'],
				'columns'  => (int) $attributes['columns'],
				'title'    => (string) $attributes['title'],
				'subtitle' => (string) $attributes['subtitle'],
				'filters'  => ! empty( $attributes['showFilters'] ),
			)
		);

		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';
		return '<div ' . $wrapper . '>' . $inner . '</div>';
	}

	/**
	 * Render callback for the Graduate Stats block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_stats( $attributes ) {
		$attributes = wp_parse_args(
			is_array( $attributes ) ? $attributes : array(),
			array(
				'title'    => __( 'Class Impact', 'student-impact' ),
				'subtitle' => __( 'What our graduates have contributed to WordPress.', 'student-impact' ),
				'showNote' => true,
				'layout'   => 'grid',
			)
		);

		$inner = SI_Render::render_stats(
			array(
				'title'    => (string) $attributes['title'],
				'subtitle' => (string) $attributes['subtitle'],
				'note'     => ! empty( $attributes['showNote'] ),
				'layout'   => (string) $attributes['layout'],
			)
		);

		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';
		return '<div ' . $wrapper . '>' . $inner . '</div>';
	}
}
