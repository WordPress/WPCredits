<?php
/**
 * Registers the plugin's blocks (all server-rendered, no build step):
 *   - the combined "WPCC-Tracker" (full dashboard), and
 *   - one block per dashboard section, so the dashboard is fully modular.
 * All blocks share the same synced data and bundled assets, and are grouped
 * under a "WPCC-Tracker" block category.
 *
 * @package WPCC_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCCT_Block {

	/** @var WPCCT_Block|null */
	private static $instance = null;

	const CATEGORY = 'wpcc-tracker';

	/**
	 * Section blocks: key => [ title, description, dashicon ]. The key is both
	 * the block name suffix and the data-wpcct-sec value the front-end reads.
	 *
	 * @return array
	 */
	public static function sections() {
		return array(
			'scale'    => array( __( 'WPCC: Scale & Momentum', 'wpcc-tracker' ), __( 'Completed, scheduled, in planning, attendees and countries.', 'wpcc-tracker' ), 'chart-bar' ),
			'regions'  => array( __( 'WPCC: Regions', 'wpcc-tracker' ), __( 'Events, institutions and attendance by world region.', 'wpcc-tracker' ), 'admin-site-alt3' ),
			'map'      => array( __( 'WPCC: Event Map', 'wpcc-tracker' ), __( 'A world map of Campus Connect events.', 'wpcc-tracker' ), 'location-alt' ),
			'timeline' => array( __( 'WPCC: Timeline', 'wpcc-tracker' ), __( 'Events per month and cumulative attendance.', 'wpcc-tracker' ), 'chart-line' ),
			'pipeline' => array( __( 'WPCC: Pipeline', 'wpcc-tracker' ), __( 'Every organiser status, including cancelled and declined.', 'wpcc-tracker' ), 'filter' ),
			'events'   => array( __( 'WPCC: Events', 'wpcc-tracker' ), __( 'Recent and upcoming events with institution and attendance.', 'wpcc-tracker' ), 'list-view' ),
		);
	}

	/**
	 * Singleton.
	 *
	 * @return WPCCT_Block
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
	 * Add a "WPCC-Tracker" block category.
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
				'title' => __( 'WPCC-Tracker', 'wpcc-tracker' ),
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

		$this->register_editor_script();

		// Combined, full dashboard (metadata from its block.json).
		register_block_type(
			WPCCT_DIR . 'blocks/tracker',
			array( 'render_callback' => array( $this, 'render_full' ) )
		);

		// One block per section - registered from PHP metadata (no block.json),
		// all sharing the wpcct-blocks-editor client script.
		foreach ( self::sections() as $key => $meta ) {
			register_block_type(
				'wpcc-tracker/' . $key,
				array(
					'api_version'     => 3,
					'title'           => $meta[0],
					'description'     => $meta[1],
					'category'        => self::CATEGORY,
					'icon'            => $meta[2],
					'editor_script'   => 'wpcct-blocks-editor',
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
	 * Register the shared editor script used by every section block. Idempotent
	 * - safe even if something else has already registered the handle.
	 */
	private function register_editor_script() {
		if ( ! wp_script_is( 'wpcct-blocks-editor', 'registered' ) ) {
			wp_register_script(
				'wpcct-blocks-editor',
				WPCCT_URL . 'assets/js/blocks-editor.js',
				array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
				WPCCT_VERSION,
				true
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
		$inner   = WPCCT_Render::render();
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
		$inner   = WPCCT_Render::section( $key );
		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';
		return '<div ' . $wrapper . '>' . $inner . '</div>';
	}
}
