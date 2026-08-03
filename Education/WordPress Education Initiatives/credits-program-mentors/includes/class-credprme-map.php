<?php
/**
 * Renders the optional mentor-countries map (Leaflet).
 *
 * @package CreditsProgramMentors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a Leaflet map of the countries mentors come from, styled and behaving
 * like the Education Programs Map plugin (CARTO Positron basemap, blue circle
 * markers scaled by count, popups, native zoom/pan) for a consistent site feel.
 *
 * Country data (name aliases, labels, and representative lat/lng points) is
 * bundled from Natural Earth (public domain). Only the CARTO map tiles are
 * fetched at runtime, by the visitor's browser — same as the Programs map.
 */
class CREDPRME_Map {

	const COUNTRY_FIELD = 'Country';
	const MAP_HEIGHT    = '520px';

	/**
	 * Cached country data ([labels, aliases, latlng]).
	 *
	 * @var array|null
	 */
	private static $data = null;

	/**
	 * Running instance counter for unique map element ids.
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Extra aliases for country names that differ from the map's data.
	 *
	 * @var array
	 */
	private static $extra_aliases = array(
		'usa'            => 'USA',
		'u.s.a.'         => 'USA',
		'us'             => 'USA',
		'united states'  => 'USA',
		'uk'             => 'GBR',
		'u.k.'           => 'GBR',
		'great britain'  => 'GBR',
		'england'        => 'GBR',
		'scotland'       => 'GBR',
		'wales'          => 'GBR',
		'czech republic' => 'CZE',
		'south korea'    => 'KOR',
		'north korea'    => 'PRK',
		'russia'         => 'RUS',
		'ivory coast'    => 'CIV',
		'uae'            => 'ARE',
	);

	/**
	 * Resolve a country name to its map id (ISO A3), or '' if unknown.
	 *
	 * @param string $name Country name.
	 * @return string
	 */
	public static function resolve_id( $name ) {
		$key = strtolower( trim( (string) $name ) );
		if ( '' === $key ) {
			return '';
		}
		$data = self::data();
		if ( isset( $data['aliases'][ $key ] ) ) {
			return $data['aliases'][ $key ];
		}
		if ( isset( self::$extra_aliases[ $key ] ) ) {
			return self::$extra_aliases[ $key ];
		}
		return '';
	}

	/**
	 * Get the display label for a map id.
	 *
	 * @param string $id Country id.
	 * @return string
	 */
	public static function label( $id ) {
		$data = self::data();
		return isset( $data['labels'][ $id ] ) ? $data['labels'][ $id ] : '';
	}

	/**
	 * Load the bundled country data once.
	 *
	 * @return array
	 */
	private static function data() {
		if ( null === self::$data ) {
			$file       = CREDPRME_PLUGIN_DIR . 'includes/data-countries.php';
			$data       = is_readable( $file ) ? include $file : array();
			self::$data = is_array( $data ) ? $data : array();
		}
		return self::$data;
	}

	/**
	 * Render the map container for a set of records.
	 *
	 * The markers are localized on the element as JSON; the front-end script
	 * (map.js) builds the Leaflet map from them.
	 *
	 * @param array $records Records being displayed.
	 * @return string HTML, or '' if there's nothing to plot.
	 */
	public static function render( array $records ) {
		$data    = self::data();
		$latlng  = isset( $data['latlng'] ) ? $data['latlng'] : array();
		$counts  = self::country_counts( $records );
		$markers = array();

		foreach ( $counts as $id => $count ) {
			if ( ! isset( $latlng[ $id ] ) ) {
				continue;
			}
			$markers[] = array(
				'id'    => $id,
				'name'  => self::label( $id ),
				'count' => $count,
				'lat'   => (float) $latlng[ $id ][0],
				'lng'   => (float) $latlng[ $id ][1],
			);
		}

		if ( empty( $markers ) ) {
			return '';
		}

		++self::$instance;
		$id = 'credprme-map-' . self::$instance;

		return sprintf(
			'<div class="credprme-map"><div id="%1$s" class="credprme-map__canvas" style="height:%2$s;" data-markers="%3$s" data-label-mentors="%4$s"></div></div>',
			esc_attr( $id ),
			esc_attr( self::MAP_HEIGHT ),
			esc_attr( wp_json_encode( $markers ) ),
			esc_attr__( 'mentors', 'credits-program-mentors' )
		);
	}

	/**
	 * Tally mentors per country id, splitting multi-country values.
	 *
	 * @param array $records Records.
	 * @return array id => count
	 */
	private static function country_counts( array $records ) {
		$counts = array();

		foreach ( $records as $record ) {
			$fields = isset( $record['fields'] ) ? $record['fields'] : array();
			if ( ! isset( $fields[ self::COUNTRY_FIELD ] ) ) {
				continue;
			}

			$raw = is_array( $fields[ self::COUNTRY_FIELD ] ) ? implode( ',', $fields[ self::COUNTRY_FIELD ] ) : (string) $fields[ self::COUNTRY_FIELD ];
			foreach ( explode( ',', $raw ) as $piece ) {
				$cid = self::resolve_id( $piece );
				if ( '' === $cid ) {
					continue;
				}
				$counts[ $cid ] = isset( $counts[ $cid ] ) ? $counts[ $cid ] + 1 : 1;
			}
		}

		return $counts;
	}
}
