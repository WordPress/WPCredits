<?php
/**
 * Airtable REST API client.
 *
 * @package CreditsProgramMentors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches records from an Airtable base using the official REST API.
 *
 * Records are requested with `cellFormat=string` so every cell comes back as a
 * human-readable string. This means single/multi-selects, linked records and
 * URL fields all render cleanly without extra lookups — ideal for display.
 */
class CREDPRME_Airtable_Client {

	const API_BASE = 'https://api.airtable.com/v0';

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings (api_token, base_id, table_id, view_id, cache_ttl).
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Retrieve records, transparently caching the result.
	 *
	 * @param array $args {
	 *     Optional query arguments.
	 *
	 *     @type string $view          View ID or name to pull from (overrides settings).
	 *     @type int    $max_records   Hard cap on the number of records returned.
	 *     @type array  $fields        Field names to request (empty = all).
	 *     @type string $filter        Airtable filterByFormula expression.
	 * }
	 * @return array|WP_Error Array of records ( each: [ 'id' => string, 'fields' => array ] ) or WP_Error.
	 */
	public function get_records( array $args = array() ) {
		if ( empty( $this->settings['api_token'] ) ) {
			return new WP_Error( 'credprme_no_token', __( 'No Airtable Personal Access Token is configured. Add one in the Credits Program Mentors menu.', 'credits-program-mentors' ) );
		}
		if ( empty( $this->settings['base_id'] ) || empty( $this->settings['table_id'] ) ) {
			return new WP_Error( 'credprme_no_base', __( 'The Airtable Base ID or Table ID is missing in the plugin settings.', 'credits-program-mentors' ) );
		}

		$cache_key = 'credprme_records_' . md5( wp_json_encode( array( $args, $this->settings['base_id'], $this->settings['table_id'], $this->settings['view_id'] ) ) );

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$records = $this->fetch_all( $args );

		if ( is_wp_error( $records ) ) {
			return $records;
		}

		$ttl = isset( $this->settings['cache_ttl'] ) ? (int) $this->settings['cache_ttl'] : HOUR_IN_SECONDS;
		if ( $ttl > 0 ) {
			set_transient( $cache_key, $records, $ttl );
		}

		return $records;
	}

	/**
	 * Walk every page of the Airtable table, honoring the offset cursor.
	 *
	 * @param array $args Query arguments (see get_records()).
	 * @return array|WP_Error
	 */
	private function fetch_all( array $args ) {
		$records     = array();
		$offset      = null;
		$max_records = isset( $args['max_records'] ) ? (int) $args['max_records'] : 0;
		$safety      = 0; // Guard against runaway pagination.

		do {
			$response = $this->request_page( $args, $offset );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( ! empty( $response['records'] ) && is_array( $response['records'] ) ) {
				$records = array_merge( $records, $response['records'] );
			}

			$offset = isset( $response['offset'] ) ? $response['offset'] : null;
			$safety++;

			if ( $max_records > 0 && count( $records ) >= $max_records ) {
				$records = array_slice( $records, 0, $max_records );
				break;
			}
		} while ( $offset && $safety < 50 );

		return $records;
	}

	/**
	 * Request a single page of records.
	 *
	 * @param array       $args   Query arguments.
	 * @param string|null $offset Pagination cursor.
	 * @return array|WP_Error Decoded response body or WP_Error.
	 */
	private function request_page( array $args, $offset ) {
		$url = trailingslashit( self::API_BASE ) . rawurlencode( $this->settings['base_id'] ) . '/' . rawurlencode( $this->settings['table_id'] );

		$query = array(
			'pageSize'   => 100,
			'cellFormat' => 'string',
			'timeZone'   => $this->get_timezone(),
			'userLocale' => $this->get_locale(),
		);

		$view = ! empty( $args['view'] ) ? $args['view'] : ( ! empty( $this->settings['view_id'] ) ? $this->settings['view_id'] : '' );
		if ( $view ) {
			$query['view'] = $view;
		}

		if ( ! empty( $args['filter'] ) ) {
			$query['filterByFormula'] = $args['filter'];
		}

		if ( $offset ) {
			$query['offset'] = $offset;
		}

		$url = add_query_arg( $query, $url );

		// Airtable expects repeated field params as fields[]=..., which add_query_arg
		// can't express, so append them by hand.
		if ( ! empty( $args['fields'] ) && is_array( $args['fields'] ) ) {
			foreach ( $args['fields'] as $field ) {
				$url .= '&fields%5B%5D=' . rawurlencode( $field );
			}
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->settings['api_token'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			$message = __( 'Airtable request failed.', 'credits-program-mentors' );
			if ( isset( $data['error'] ) ) {
				$error   = $data['error'];
				$detail  = is_array( $error ) ? ( isset( $error['message'] ) ? $error['message'] : ( isset( $error['type'] ) ? $error['type'] : '' ) ) : (string) $error;
				$message = sprintf(
					/* translators: 1: HTTP status code, 2: error detail from Airtable. */
					__( 'Airtable request failed (HTTP %1$d): %2$s', 'credits-program-mentors' ),
					$code,
					$detail
				);
			}
			return new WP_Error( 'credprme_api_error', $message, array( 'status' => $code ) );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'credprme_bad_response', __( 'Airtable returned an unexpected response.', 'credits-program-mentors' ) );
		}

		return $data;
	}

	/**
	 * Resolve a valid IANA timezone string for the Airtable request.
	 *
	 * @return string
	 */
	private function get_timezone() {
		$tz = wp_timezone_string();
		// wp_timezone_string() can return a UTC offset like "+02:00", which Airtable rejects.
		if ( ! $tz || preg_match( '/^[+-]/', $tz ) ) {
			return 'UTC';
		}
		return $tz;
	}

	/**
	 * Resolve the locale for Airtable's string formatting.
	 *
	 * @return string
	 */
	private function get_locale() {
		$locale = get_locale();
		if ( ! $locale ) {
			return 'en';
		}
		// Airtable expects a BCP-47-ish tag such as "en" or "en-US".
		return str_replace( '_', '-', $locale );
	}

	/**
	 * Delete every cached record set. Called when settings change.
	 */
	public static function flush_cache() {
		global $wpdb;
		$like = $wpdb->esc_like( '_transient_credprme_records_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$like_timeout = $wpdb->esc_like( '_transient_timeout_credprme_records_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
