<?php
/**
 * Thin read-only client for the Airtable REST API, shared by every sync in the
 * plugin (the per-program institutions sync and the Campus Connect events sync).
 *
 * Knows nothing about institutions, programs, or events — it only builds URLs,
 * performs GETs, and follows pagination.
 *
 * @package Education_Programs_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPM_Airtable_Client {

	/**
	 * Build an Airtable API request URL, encoding repeated params (e.g. fields[]) correctly.
	 *
	 * @param string $base_id Airtable base ID.
	 * @param string $table   Table name or ID.
	 * @param array  $params  Query parameters; array values become repeated `key[]=` params.
	 * @return string
	 */
	private static function build_url( $base_id, $table, $params ) {
		$parts = array();

		foreach ( $params as $key => $value ) {
			foreach ( (array) $value as $single ) {
				$parts[] = rawurlencode( is_array( $value ) ? $key . '[]' : $key ) . '=' . rawurlencode( $single );
			}
		}

		$url = 'https://api.airtable.com/v0/' . rawurlencode( $base_id ) . '/' . rawurlencode( $table );

		return $parts ? $url . '?' . implode( '&', $parts ) : $url;
	}

	/**
	 * Perform a single GET request against the Airtable REST API.
	 *
	 * @param string $token   Airtable personal access token.
	 * @param string $base_id Airtable base ID.
	 * @param string $table   Table name or ID.
	 * @param array  $params  Query parameters.
	 * @return array|WP_Error Decoded response body, or WP_Error on failure.
	 */
	public static function get( $token, $base_id, $table, $params = array() ) {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get -- vip_safe_wp_remote_get() only exists on WordPress VIP hosting; this plugin targets general WordPress.
		$response = wp_remote_get(
			self::build_url( $base_id, $table, $params ),
			array(
				// phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- runs only during an occasional admin-triggered or weekly cron sync, never on a public page load.
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$message = $body['error']['message'] ?? sprintf(
				/* translators: %d: HTTP status code returned by Airtable. */
				__( 'Airtable API request failed with status %d.', 'education-programs-map' ),
				$code
			);
			return new WP_Error( 'epm_airtable_http_error', $message );
		}

		return $body;
	}

	/**
	 * Fetch every record from a table, following pagination automatically.
	 *
	 * @param string $token   Airtable personal access token.
	 * @param string $base_id Airtable base ID.
	 * @param string $table   Table name or ID.
	 * @param array  $params  Query parameters.
	 * @return array|WP_Error
	 */
	public static function fetch_all( $token, $base_id, $table, $params = array() ) {
		$records = array();
		$offset  = null;

		do {
			$page_params = $params;
			if ( $offset ) {
				$page_params['offset'] = $offset;
			}

			$page = self::get( $token, $base_id, $table, $page_params );
			if ( is_wp_error( $page ) ) {
				return $page;
			}

			$records = array_merge( $records, $page['records'] ?? array() );
			$offset  = $page['offset'] ?? null;
		} while ( $offset );

		return $records;
	}
}
