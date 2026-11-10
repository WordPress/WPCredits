<?php
/**
 * The partner map's markers, built from the confirmed institutions a sync reads.
 *
 * Airtable holds an institution's city and country, not where that is on a map, so the
 * coordinates come from a list shipped with the plugin (data/city-coords.json, one entry per
 * city, filterable). A city the list does not know is left off the map and named on the
 * settings screen, so somebody can add it; nothing is looked up from an outside service.
 *
 * @package WPCredits_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCT_Markers {

	/**
	 * The key a city is found under: lower case, no accents, nothing after a comma
	 * ("Chicago, IL"), then the country. "KOLHAPUR" and "Kolhapur" are one city.
	 *
	 * @param string $city    City as the record spells it.
	 * @param string $country Country name.
	 * @return string '' when either part is missing.
	 */
	public static function key( $city, $country ) {
		$city    = is_string( $city ) ? trim( strtok( $city . ',', ',' ) ) : '';
		$country = is_string( $country ) ? trim( $country ) : '';
		if ( '' === $city || '' === $country ) {
			return '';
		}
		$fold = function ( $text ) {
			if ( function_exists( 'remove_accents' ) ) {
				$text = remove_accents( $text );
			}
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		};
		return $fold( $city ) . '|' . $fold( $country );
	}

	/**
	 * The shipped coordinates, by key(). Overridable via the `wpct_city_coords` filter.
	 *
	 * @return array key => array( city, lat, lng ).
	 */
	public static function coords() {
		$coords = array();
		$file   = WPCT_DIR . 'data/city-coords.json';
		if ( is_readable( $file ) ) {
			$decoded = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( is_array( $decoded ) ) {
				$coords = $decoded;
			}
		}
		$coords = apply_filters( 'wpct_city_coords', $coords );
		return is_array( $coords ) ? $coords : array();
	}

	/**
	 * Markers for a list of institutions.
	 *
	 * @param array $rows   Each array( 'name' => , 'city' => , 'country' => ).
	 * @param array $coords What coords() returns.
	 * @return array 'markers' (city, country, lat, lng, institutions; in the order first met) and
	 *               'skipped' ("City, Country" for each place with no coordinates, or
	 *               "(no city), Country" for a record that names none).
	 */
	public static function build( array $rows, array $coords ) {
		$markers = array();
		$skipped = array();

		foreach ( $rows as $row ) {
			$name    = isset( $row['name'] ) && is_string( $row['name'] ) ? trim( $row['name'] ) : '';
			$city    = isset( $row['city'] ) && is_string( $row['city'] ) ? trim( $row['city'] ) : '';
			$country = isset( $row['country'] ) && is_string( $row['country'] ) ? trim( $row['country'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			$key   = self::key( $city, $country );
			$place = ( '' !== $key && isset( $coords[ $key ] ) && is_array( $coords[ $key ] ) ) ? $coords[ $key ] : null;
			if ( ! $place || ! isset( $place['lat'], $place['lng'] ) || ! is_numeric( $place['lat'] ) || ! is_numeric( $place['lng'] ) ) {
				$skipped[ ( '' !== $city ? $city : '(no city)' ) . ', ' . ( '' !== $country ? $country : '(no country)' ) ] = true;
				continue;
			}

			// Two spellings of one city share one marker: the marker is the place, not the key.
			$spot = $place['lat'] . ',' . $place['lng'];
			if ( ! isset( $markers[ $spot ] ) ) {
				$markers[ $spot ] = array(
					'city'         => isset( $place['city'] ) && is_string( $place['city'] ) ? $place['city'] : $city,
					'country'      => $country,
					'lat'          => (float) $place['lat'],
					'lng'          => (float) $place['lng'],
					'institutions' => array(),
				);
			}
			if ( ! in_array( $name, $markers[ $spot ]['institutions'], true ) ) {
				$markers[ $spot ]['institutions'][] = $name;
			}
		}

		return array(
			'markers' => array_values( $markers ),
			'skipped' => array_keys( $skipped ),
		);
	}
}
