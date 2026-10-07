<?php
/**
 * Front-end rendering for the WPCC-Tracker, shared by the block(s) and the
 * shortcode. Emits the mount markup, registers + enqueues the bundled
 * Chart.js + Leaflet + tracker assets, and passes the synced data blob in via
 * an inline script.
 *
 * @package WPCC_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCCT_Render {

	/**
	 * Section keys rendered by the combined view, in display order. Tasks
	 * 11-13 give these keys their JS builders; the markup and dispatch are
	 * already section-agnostic.
	 *
	 * @var string[]
	 */
	const SECTIONS = array( 'scale', 'regions', 'map', 'timeline', 'pipeline', 'events' );

	/**
	 * Shortcode handler: [wpcc_tracker].
	 *
	 * @param array $atts Shortcode attributes (unused).
	 * @return string
	 */
	public static function shortcode( $atts ) {
		return self::render();
	}

	/**
	 * Render the combined view: every section mount, in order, followed by a
	 * single provenance footer.
	 *
	 * @return string HTML.
	 */
	public static function render() {
		$data = WPCCT_Sync::data();
		if ( empty( $data ) ) {
			return self::empty_notice();
		}

		self::enqueue_assets( $data );

		$html = '<div class="wpcct-tracker wpcct-tracker--full">';
		foreach ( self::SECTIONS as $key ) {
			$html .= '<div class="wpcct-tracker" data-wpcct-sec="' . esc_attr( $key ) . '"></div>';
		}
		$html .= self::provenance_html();
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render a single section as a standalone mount (one per block).
	 *
	 * @param string $key Section key. See self::SECTIONS.
	 * @return string HTML.
	 */
	public static function section( $key ) {
		$data = WPCCT_Sync::data();
		if ( empty( $data ) ) {
			return self::empty_notice();
		}

		self::enqueue_assets( $data );

		return '<div class="wpcct-tracker" data-wpcct-sec="' . esc_attr( $key ) . '"></div>' . self::provenance_html();
	}

	/**
	 * Register (if needed) and enqueue the bundled assets, then inline the
	 * data blob. The blob is inlined only once per request, no matter how
	 * many section blocks appear on the page.
	 *
	 * @param array $data The public data blob (from WPCCT_Sync::data()).
	 */
	public static function enqueue_assets( $data ) {
		self::register_assets();

		wp_enqueue_style( 'wpcct-leaflet' );
		wp_enqueue_style( 'wpcct-tracker' );
		wp_enqueue_script( 'wpcct-leaflet' );
		wp_enqueue_script( 'wpcct-chart' );
		wp_enqueue_script( 'wpcct-tracker' );

		static $inlined = false;
		if ( $inlined ) {
			return;
		}
		$inlined = true;

		wp_add_inline_script(
			'wpcct-tracker',
			'window.WPCCT_DATA = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * Register the bundled front-end assets. Idempotent - safe to call from
	 * multiple entry points (block render, shortcode, empty-state notice).
	 */
	private static function register_assets() {
		if ( ! wp_style_is( 'wpcct-leaflet', 'registered' ) ) {
			wp_register_style( 'wpcct-leaflet', WPCCT_URL . 'assets/lib/leaflet/leaflet.css', array(), '1.9.4' );
		}
		if ( ! wp_style_is( 'wpcct-tracker', 'registered' ) ) {
			wp_register_style( 'wpcct-tracker', WPCCT_URL . 'assets/css/tracker.css', array(), wpcct_asset_version( 'assets/css/tracker.css' ) );
		}
		if ( ! wp_script_is( 'wpcct-leaflet', 'registered' ) ) {
			wp_register_script( 'wpcct-leaflet', WPCCT_URL . 'assets/lib/leaflet/leaflet.js', array(), '1.9.4', true );
		}
		if ( ! wp_script_is( 'wpcct-chart', 'registered' ) ) {
			wp_register_script( 'wpcct-chart', WPCCT_URL . 'assets/lib/chart.umd.min.js', array(), '4.4.1', true );
		}
		if ( ! wp_script_is( 'wpcct-tracker', 'registered' ) ) {
			wp_register_script(
				'wpcct-tracker',
				WPCCT_URL . 'assets/js/tracker.js',
				array( 'wpcct-leaflet', 'wpcct-chart' ),
				wpcct_asset_version( 'assets/js/tracker.js' ),
				true
			);
		}
	}

	/**
	 * Provenance line: where the data came from and how old it actually is.
	 *
	 * The age reported here is the SOURCE data's, never this plugin's own last
	 * run. There are two hops - Central to Airtable, written by
	 * wordcamp-airtable-connector, then Airtable to here - and only the first
	 * one tells a reader how old the numbers are. Reporting the second put a
	 * one-day-old date over a six-week-old event list, which is exactly the
	 * false confidence this dashboard exists to remove. The operator's own
	 * view of when this plugin last ran is on the settings screen.
	 *
	 * @return string Plain text (not escaped).
	 */
	public static function provenance() {
		$data = WPCCT_Sync::data();

		if ( ! empty( $data['seed'] ) ) {
			return __( 'Seed data - sync has not run yet.', 'wpcc-tracker' );
		}

		$synced  = empty( $data['source_synced'] ) ? 0 : strtotime( (string) $data['source_synced'] );
		$created = empty( $data['source_created'] ) ? 0 : strtotime( (string) $data['source_created'] );
		$source  = $synced ? $synced : $created;

		if ( ! $source ) {
			/* A blob built before this plugin recorded source dates, or one
			   whose rows carried neither stamp. Saying so is the honest
			   answer and it corrects itself on the next sync; the one thing
			   not to do is fall back to this plugin's own run date, which is
			   what used to make a stale dashboard look fresh. */
			return __( 'WordCamp Central via Airtable · age of source data unknown', 'wpcc-tracker' );
		}

		$date = wp_date( get_option( 'date_format' ), $source );
		$days = (int) floor( ( time() - $source ) / DAY_IN_SECONDS );

		if ( ! $synced ) {
			/* No row carries a sync stamp, so Central has never been read
			   successfully and these rows are still whatever was imported by
			   hand. Always flagged, whatever the age: "never refreshed" is
			   the point, not the number of days. */
			return sprintf(
				/* translators: 1: date the rows were first loaded. 2: whole days since. */
				__( 'WordCamp Central via Airtable · loaded %1$s, never refreshed from Central (%2$d days old)', 'wpcc-tracker' ),
				$date,
				$days
			);
		}

		if ( $days > 14 ) {
			return sprintf(
				/* translators: 1: date Central was last read. 2: whole days since. */
				__( 'WordCamp Central via Airtable · data from Central %1$s (%2$d days old)', 'wpcc-tracker' ),
				$date,
				$days
			);
		}

		return sprintf(
			/* translators: %s: date Central was last read. */
			__( 'WordCamp Central via Airtable · data from Central %s', 'wpcc-tracker' ),
			$date
		);
	}

	/**
	 * The provenance line, wrapped and escaped for output.
	 *
	 * @return string HTML.
	 */
	private static function provenance_html() {
		return '<p class="wpcct-tracker__provenance">' . esc_html( self::provenance() ) . '</p>';
	}

	/**
	 * Muted placeholder shown when there is no data at all (no synced blob
	 * and no readable seed file). This should not normally be reachable
	 * - the plugin ships a seed file - but WPCCT_Sync::data() can still return
	 * an empty array, and every section must degrade to this rather than a
	 * PHP warning or a blank div.
	 *
	 * @return string HTML.
	 */
	private static function empty_notice() {
		self::register_assets();
		wp_enqueue_style( 'wpcct-tracker' );

		$html = '<div class="wpcct-tracker wpcct-tracker--empty"><p class="wpcct-tracker__notice">'
			. esc_html__( 'No Campus Connect data yet.', 'wpcc-tracker' );

		if ( current_user_can( 'manage_options' ) && class_exists( 'WPCCT_Settings' ) ) {
			$url   = esc_url( admin_url( 'admin.php?page=' . WPCCT_Settings::PAGE_SLUG ) );
			$html .= ' <a href="' . $url . '">' . esc_html__( 'Add your Airtable token and run a sync.', 'wpcc-tracker' ) . '</a>';
		}

		$html .= '</p></div>';

		return $html;
	}
}
