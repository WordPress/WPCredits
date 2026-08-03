<?php
/**
 * Front-end rendering for the WPCredits-Tracker, shared by the block and the
 * shortcode. Emits the static shell (tabbed nav, section containers), enqueues
 * the bundled Chart.js + Leaflet + dashboard assets, and passes the synced data
 * blob in via an inline script.
 *
 * @package WPCredits_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCT_Render {

	/**
	 * Shortcode handler: [wpcredits_tracker].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		return self::render();
	}

	/**
	 * Render the dashboard.
	 *
	 * @return string HTML.
	 */
	public static function render() {
		$data = get_option( WPCT_OPT_DATA, array() );
		if ( ! is_array( $data ) || empty( $data ) ) {
			return self::empty_notice();
		}

		self::enqueue_assets( $data );

		ob_start();
		?>
		<div class="wpct-dashboard wpct-dashboard--full">
			<div class="nav" role="tablist">
				<div class="nav-item active" data-section="global"><?php esc_html_e( 'Overview', 'wpcredits-tracker' ); ?></div>
				<div class="nav-item" data-section="feedback"><?php esc_html_e( 'Voices', 'wpcredits-tracker' ); ?></div>
			</div>

			<div class="content">
				<div class="section active" data-wpct-sec="global"></div>
				<div class="section" data-wpct-sec="feedback"></div>
			</div>
		</div>
		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Render a single dashboard section as a standalone mount.
	 *
	 * @param string $key Section key (scale, growth, map, field-of-study,
	 *                     skills, produce, outcomes, voices).
	 * @return string HTML.
	 */
	public static function section( $key ) {
		$data = get_option( WPCT_OPT_DATA, array() );
		if ( ! is_array( $data ) || empty( $data ) ) {
			return self::empty_notice();
		}

		self::enqueue_assets( $data );

		return '<div class="wpct-dashboard wpct-dashboard--section" data-wpct-section="' . esc_attr( $key ) . '"></div>';
	}

	/**
	 * Register + enqueue the bundled assets and inline the data blob.
	 *
	 * The data blob is inlined only once per request, even when several section
	 * blocks appear on the same page.
	 *
	 * @param array $data The public data blob.
	 */
	private static function enqueue_assets( $data ) {
		wp_enqueue_style( 'wpct-leaflet' );
		wp_enqueue_style( 'wpct-dashboard' );
		wp_enqueue_script( 'wpct-leaflet' );
		wp_enqueue_script( 'wpct-chart' );
		wp_enqueue_script( 'wpct-dashboard' );

		static $inlined = false;
		if ( $inlined ) {
			return;
		}
		$inlined = true;

		$markers = self::markers();
		$inline  = 'window.WPCT_DATA=' . wp_json_encode( $data ) . ';'
			. 'window.WPCT_MARKERS=' . wp_json_encode( $markers ) . ';';
		wp_add_inline_script( 'wpct-dashboard', $inline, 'before' );
	}

	/**
	 * Partner map markers (bundled JSON, filterable).
	 *
	 * @return array
	 */
	private static function markers() {
		$markers = array();
		$file    = WPCT_DIR . 'data/inst-markers.json';
		if ( is_readable( $file ) ) {
			$decoded = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( is_array( $decoded ) ) {
				$markers = $decoded;
			}
		}
		return apply_filters( 'wpct_markers', $markers );
	}

	/**
	 * Placeholder shown when there is no data yet (admins only).
	 *
	 * @return string
	 */
	private static function empty_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		if ( wp_style_is( 'wpct-dashboard', 'registered' ) ) {
			wp_enqueue_style( 'wpct-dashboard' );
		}
		$url = esc_url( admin_url( 'admin.php?page=' . WPCT_Settings::PAGE ) );
		return '<div class="wpct-dashboard wpct-dashboard--empty"><p>'
			. esc_html__( 'The WPCredits-Tracker has no data yet.', 'wpcredits-tracker' ) . ' '
			. '<a href="' . $url . '">' . esc_html__( 'Add your Airtable token and run a sync.', 'wpcredits-tracker' ) . '</a>'
			. '</p></div>';
	}
}
