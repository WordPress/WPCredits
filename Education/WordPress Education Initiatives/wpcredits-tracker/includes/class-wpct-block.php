<?php
/**
 * Registers the plugin's blocks (all server-rendered, no build step):
 *   - the combined "WPCredits-Tracker" (tabbed Overview + Voices), and
 *   - one block per dashboard section, so the dashboard is fully modular.
 * All blocks share the same synced data and bundled assets, and are grouped
 * under a "WPCredits-Tracker" block category.
 *
 * @package WPCredits_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCT_Block {

	/** @var WPCT_Block|null */
	private static $instance = null;

	const CATEGORY = 'wpcredits-tracker';

	/**
	 * Section blocks: key => [ title, description, dashicon ]. The key is both
	 * the block name suffix and the data-wpct-section value the front-end reads.
	 *
	 * @return array
	 */
	public static function sections() {
		return array(
			'scale'          => array( __( 'WPCredits: Scale & Momentum', 'wpcredits-tracker' ), __( 'Headline counts: students in the program, joined to date, and countries.', 'wpcredits-tracker' ), 'chart-bar' ),
			'growth'         => array( __( 'WPCredits: Growth Chart', 'wpcredits-tracker' ), __( 'Students joining and graduating, month by month.', 'wpcredits-tracker' ), 'chart-line' ),
			'map'            => array( __( 'WPCredits: Partner Map', 'wpcredits-tracker' ), __( 'A world map of partner institutions.', 'wpcredits-tracker' ), 'location-alt' ),
			'field-of-study' => array( __( 'WPCredits: Field of Study', 'wpcredits-tracker' ), __( 'The academic backgrounds students bring.', 'wpcredits-tracker' ), 'chart-pie' ),
			'skills'         => array( __( 'WPCredits: Skills', 'wpcredits-tracker' ), __( 'Technical and transferable skills students build.', 'wpcredits-tracker' ), 'awards' ),
			'produce'        => array( __( 'WPCredits: What Students Produce', 'wpcredits-tracker' ), __( 'First contributions, sites created, and teams contributed to.', 'wpcredits-tracker' ), 'hammer' ),
			'outcomes'       => array( __( 'WPCredits: Outcomes & Quality', 'wpcredits-tracker' ), __( 'Graduation rate, graduates, impact, and recommendation figures.', 'wpcredits-tracker' ), 'yes-alt' ),
			'voices'         => array( __( 'WPCredits: Voices', 'wpcredits-tracker' ), __( 'Student testimonials, tagged by country.', 'wpcredits-tracker' ), 'format-quote' ),
		);
	}

	/**
	 * Singleton.
	 *
	 * @return WPCT_Block
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register' ), 20 );
		add_filter( 'block_categories_all', array( $this, 'category' ) );
	}

	/**
	 * Add a "WPCredits-Tracker" block category.
	 *
	 * @param array $categories Existing categories.
	 * @return array
	 */
	public function category( $categories ) {
		foreach ( $categories as $cat ) {
			if ( isset( $cat['slug'] ) && self::CATEGORY === $cat['slug'] ) {
				return $categories; // Already present.
			}
		}
		array_unshift(
			$categories,
			array(
				'slug'  => self::CATEGORY,
				'title' => __( 'WPCredits-Tracker', 'wpcredits-tracker' ),
				'icon'  => null,
			)
		);
		return $categories;
	}

	/**
	 * Register the combined block and all section blocks.
	 */
	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Combined, tabbed dashboard (metadata from its block.json).
		register_block_type(
			WPCT_DIR . 'blocks/dashboard',
			array( 'render_callback' => array( $this, 'render_full' ) )
		);

		// One block per section — registered from PHP metadata (no block.json),
		// all sharing the wpct-blocks-editor client script.
		foreach ( self::sections() as $key => $meta ) {
			register_block_type(
				'wpcredits-tracker/' . $key,
				array(
					'api_version'     => 3,
					'title'           => $meta[0],
					'description'     => $meta[1],
					'category'        => self::CATEGORY,
					'icon'            => $meta[2],
					'editor_script'   => 'wpct-blocks-editor',
					'supports'        => array(
						'html'    => false,
						'align'   => array( 'wide', 'full' ),
						'spacing' => array( 'margin' => true ),
					),
					'render_callback' => function ( $attributes, $content, $block ) use ( $key ) {
						return $this->render_section( $key );
					},
				)
			);
		}
	}

	/**
	 * Render callback for the combined dashboard block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_full( $attributes ) {
		$inner   = WPCT_Render::render();
		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';
		return '<div ' . $wrapper . '>' . $inner . '</div>';
	}

	/**
	 * Render callback for a single section block.
	 *
	 * @param string $key Section key.
	 * @return string
	 */
	public function render_section( $key ) {
		$inner   = WPCT_Render::section( $key );
		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';
		return '<div ' . $wrapper . '>' . $inner . '</div>';
	}
}
