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
				<button type="button" role="tab" aria-selected="true" class="nav-item active" data-section="global"><?php esc_html_e( 'Overview', 'wpcredits-tracker' ); ?></button>
				<button type="button" role="tab" aria-selected="false" class="nav-item" data-section="feedback"><?php esc_html_e( 'Voices', 'wpcredits-tracker' ); ?></button>
			</div>

			<div class="content">
				<div class="section active" data-wpct-sec="global"></div>
				<div class="section" data-wpct-sec="feedback"></div>
			</div>
		</div>
		<?php
		return trim( ob_get_clean() ) . self::updated();
	}

	/**
	 * The line that says how fresh the figures are: "Figures last updated on <date>."
	 *
	 * Empty until a live sync has succeeded: the bundled snapshot carries no date, and a
	 * date that is a guess would be worse than none.
	 *
	 * @return string HTML, or '' when no sync has succeeded yet.
	 */
	public static function updated() {
		$stamp = get_option( WPCT_OPT_LASTSYNC, 0 );
		$stamp = is_numeric( $stamp ) ? (int) $stamp : 0;
		if ( $stamp <= 0 ) {
			return '';
		}

		wp_enqueue_style( 'wpct-dashboard' );

		$time = '<time datetime="' . esc_attr( wp_date( 'Y-m-d', $stamp ) ) . '">'
			. esc_html( wp_date( (string) get_option( 'date_format', 'F j, Y' ), $stamp ) )
			. '</time>';

		return '<p class="wpct-updated">'
			/* translators: %s: the date of the last successful sync. */
			. sprintf( esc_html( __( 'Figures last updated on %s.', 'wpcredits-tracker' ) ), $time )
			. '</p>';
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

		$markers = self::markers( $data );
		$inline  = 'window.WPCT_DATA=' . wp_json_encode( self::public_data( $data ) ) . ';'
			. 'window.WPCT_MARKERS=' . wp_json_encode( $markers ) . ';'
			. 'window.WPCT_VOICES=' . wp_json_encode( WPCT_Voices::get() ) . ';';
		wp_add_inline_script( 'wpct-dashboard', $inline, 'before' );
	}

	/**
	 * The data blob as the page may carry it. The stored blob also holds what the sync and the
	 * admin screen need and a visitor should not be handed in the page source: the count of
	 * approved sponsors (not public while sponsorship is on hold), the places left off the map,
	 * the statuses nobody has declared, and the markers, which the page gets once as WPCT_MARKERS.
	 *
	 * @param array $data The stored blob.
	 * @return array
	 */
	public static function public_data( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['global'] ) || ! is_array( $data['global'] ) ) {
			return $data;
		}
		unset( $data['global']['sponsorCount'], $data['global']['mapSkipped'], $data['global']['unknownStatuses'], $data['global']['partnerMarkers'] );
		return $data;
	}

	/**
	 * Partner map markers: the ones the last sync built from the confirmed institutions (1.7.0),
	 * or, for data from before that (the bundled snapshot, an older sync), the bundled list.
	 * Filterable.
	 *
	 * @param array $data The public data blob.
	 * @return array
	 */
	private static function markers( $data = array() ) {
		$markers = array();
		$file    = WPCT_DIR . 'data/inst-markers.json';
		if ( isset( $data['global']['partnerMarkers'] ) && is_array( $data['global']['partnerMarkers'] ) && $data['global']['partnerMarkers'] ) {
			$markers = $data['global']['partnerMarkers'];
		} elseif ( is_readable( $file ) ) {
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
