<?php
/**
 * Public-facing [education_programs_map] shortcode.
 *
 * @package Education_Programs_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPM_Shortcode {

	public function __construct() {
		add_shortcode( 'education_programs_map', array( $this, 'render' ) );
		add_action( 'init', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (but don't yet enqueue) the map assets, so they only load on pages
	 * using the shortcode or block. Hooked to 'init' (rather than 'wp_enqueue_scripts')
	 * so the handles also exist for the block editor and block.json's asset references.
	 */
	public function register_assets() {
		wp_register_style( 'epm-leaflet', EPM_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_register_script( 'epm-leaflet', EPM_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );

		wp_register_style( 'epm-map', EPM_PLUGIN_URL . 'assets/css/map.css', array( 'epm-leaflet' ), EPM_VERSION );
		wp_register_script( 'epm-map', EPM_PLUGIN_URL . 'assets/js/map.js', array( 'epm-leaflet' ), EPM_VERSION, true );
	}

	/**
	 * Shortcode callback. Delegates to the static renderer shared with the block.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		return self::render_output( (array) $atts );
	}

	/**
	 * Render the map container. Data is fetched client-side from the REST endpoint.
	 * Shared by both the [education_programs_map] shortcode and the epm/education-programs-map block.
	 *
	 * @param array $atts Shortcode/block attributes.
	 * @return string
	 */
	public static function render_output( $atts ) {
		$settings = EPM_Settings::get();

		$atts = shortcode_atts(
			array(
				'program' => '',
				'height'  => $settings['height'],
				'width'   => $settings['width'],
			),
			$atts,
			'education_programs_map'
		);

		if ( ! EPM_Settings::is_valid_dimension( $atts['height'] ) ) {
			$atts['height'] = $settings['height'];
		}

		if ( ! EPM_Settings::is_valid_dimension( $atts['width'] ) ) {
			$atts['width'] = $settings['width'];
		}

		wp_enqueue_style( 'epm-leaflet' );
		wp_enqueue_script( 'epm-leaflet' );
		wp_enqueue_style( 'epm-map' );
		wp_enqueue_script( 'epm-map' );

		static $instance = 0;
		++$instance;
		$id = 'epm-map-' . $instance;

		ob_start();
		?>
		<div class="epm-map-wrapper">
			<div class="epm-map-filters" data-target="<?php echo esc_attr( $id ); ?>" role="group" aria-label="<?php esc_attr_e( 'Filter map by program', 'education-programs-map' ); ?>"></div>
			<div
				id="<?php echo esc_attr( $id ); ?>"
				class="epm-map"
				style="height: <?php echo esc_attr( $atts['height'] ); ?>; width: <?php echo esc_attr( $atts['width'] ); ?>;"
				data-program="<?php echo esc_attr( $atts['program'] ); ?>"
				data-rest-url="<?php echo esc_url( rest_url( 'education-programs-map/v1/institutions' ) ); ?>"
				data-programs="<?php echo esc_attr( wp_json_encode( EPM_DB::get_programs() ) ); ?>"
				data-program-colors="<?php echo esc_attr( wp_json_encode( EPM_Programs::get_colors() ) ); ?>"
				data-label-events="<?php echo esc_attr( __( 'events', 'education-programs-map' ) ); ?>"
				data-label-all="<?php echo esc_attr( __( 'All programs', 'education-programs-map' ) ); ?>"
			></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
