<?php
/**
 * Resolves a city/country pair to coordinates using the Photon geocoder
 * (an open, OpenStreetMap-based service maintained by Komoot).
 *
 * @package Education_Programs_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPM_Geocoder {

	const API_URL = 'https://photon.komoot.io/api/';

	/**
	 * Look up coordinates for a city/country pair.
	 *
	 * @param string $city    City name.
	 * @param string $country Country name.
	 * @return array{lat:float,lng:float}|null Null if no match was found or the request failed.
	 */
	public static function geocode( $city, $country ) {
		$query = trim( trim( (string) $city ) . ', ' . trim( (string) $country ), ', ' );

		if ( '' === $query ) {
			return null;
		}

		$url = add_query_arg(
			array(
				'q'     => $query,
				'limit' => 1,
			),
			self::API_URL
		);

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get -- vip_safe_wp_remote_get() only exists on WordPress VIP hosting; this plugin targets general WordPress.
		$response = wp_remote_get(
			$url,
			array(
				// phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- called only during an explicit, occasional admin-triggered sync, never on a public page load.
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$feature = $body['features'][0] ?? null;
		$coords  = $feature['geometry']['coordinates'] ?? null;

		if ( ! is_array( $coords ) || 2 !== count( $coords ) ) {
			return null;
		}

		// GeoJSON orders coordinates as [longitude, latitude].
		return array(
			'lat' => (float) $coords[1],
			'lng' => (float) $coords[0],
		);
	}
}
