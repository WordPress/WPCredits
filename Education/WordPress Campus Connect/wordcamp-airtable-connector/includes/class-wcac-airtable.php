<?php
/**
 * Minimal Airtable Web API client, scoped to what the connector needs.
 *
 * @package WordCamp_Airtable_Connector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes records to Airtable using the upsert form of the records endpoint.
 *
 * Only data scopes are required (data.records:read, data.records:write) -- the
 * table schema is provisioned by hand, not by this plugin.
 */
class WCAC_Airtable {

	const API_ROOT = 'https://api.airtable.com/v0';

	/**
	 * Airtable accepts at most 10 records per create/update request.
	 */
	const BATCH = 10;

	/**
	 * Airtable allows 5 requests/second/base. Stay just under it.
	 */
	const MIN_INTERVAL_US = 220000;

	/**
	 * Personal access token.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Base ID.
	 *
	 * @var string
	 */
	protected $base;

	/**
	 * Microtime of the last request, for throttling.
	 *
	 * Deliberately static. The 5 requests/second ceiling is per BASE, not per
	 * client object, and one PHP process can now hold more than one client: the
	 * CLI preflight and dry run read through their own WCAC_Airtable while
	 * WCAC_Sync writes through another. Per-instance state would let two of them
	 * fire back to back, and request() answers the resulting 429 with a 30
	 * second sleep, up to three times.
	 *
	 * @var float
	 */
	protected static $last_request = 0.0;

	/**
	 * Constructor.
	 *
	 * @param string|null $token Personal access token; falls back to settings.
	 * @param string|null $base  Base ID; falls back to settings.
	 */
	public function __construct( $token = null, $base = null ) {
		$this->token = null === $token ? (string) WCAC_Settings::get( 'api_key' ) : $token;
		$this->base  = null === $base ? (string) WCAC_Settings::get( 'base_id' ) : $base;
	}

	/**
	 * Sleep just long enough to stay inside Airtable's per-base rate limit.
	 *
	 * @return void
	 */
	protected function throttle() {
		$elapsed = ( microtime( true ) - self::$last_request ) * 1000000;

		if ( self::$last_request > 0 && $elapsed < self::MIN_INTERVAL_US ) {
			usleep( (int) ( self::MIN_INTERVAL_US - $elapsed ) );
		}

		self::$last_request = microtime( true );
	}

	/**
	 * Perform a request, retrying on rate limits and transient server errors.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Path below the base, e.g. "tblXXXX".
	 * @param array  $body   Optional JSON body.
	 * @return array|WP_Error Decoded response body.
	 */
	protected function request( $method, $path, ?array $body = null ) {
		if ( '' === $this->token || '' === $this->base ) {
			return new WP_Error( 'wcac_airtable_unconfigured', __( 'Airtable API key or base ID is missing.', 'wordcamp-airtable-connector' ) );
		}

		$url  = self::API_ROOT . '/' . rawurlencode( $this->base ) . '/' . ltrim( $path, '/' );
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->token,
				'Content-Type'  => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$attempts = 0;
		$max      = 4;

		while ( true ) {
			$attempts++;
			$this->throttle();

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				if ( $attempts >= $max ) {
					return $response;
				}
				sleep( min( 8, $attempts * 2 ) );
				continue;
			}

			$code    = (int) wp_remote_retrieve_response_code( $response );
			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code >= 200 && $code < 300 ) {
				return is_array( $decoded ) ? $decoded : array();
			}

			// 429 is the documented rate-limit response; 5xx is transient.
			if ( ( 429 === $code || $code >= 500 ) && $attempts < $max ) {
				sleep( 429 === $code ? 30 : min( 8, $attempts * 2 ) );
				continue;
			}

			$message = isset( $decoded['error']['message'] )
				? $decoded['error']['message']
				: wp_remote_retrieve_body( $response );

			return new WP_Error(
				'wcac_airtable_http',
				sprintf( 'Airtable HTTP %d: %s', $code, is_string( $message ) ? $message : wp_json_encode( $message ) ),
				array( 'status' => $code )
			);
		}
	}

	/**
	 * Upsert records into a table, merging on a single field.
	 *
	 * Airtable matches an incoming record against existing rows by the value of
	 * $merge_field; matches are updated in place, misses are created. That
	 * removes any need to read the table first or store record IDs locally.
	 *
	 * @param string $table       Table ID or name.
	 * @param array  $records     List of field arrays (not wrapped in "fields").
	 * @param string $merge_field Field name to merge on. Must be unique per row.
	 * @param array  $options     'typecast' (bool, default true) and
	 *                            'continue_on_error' (bool, default false).
	 *                            With continue_on_error, a failing chunk is
	 *                            recorded and the remaining chunks are still
	 *                            sent -- without it, upsert() abandons every
	 *                            later chunk, which on a stable-ordered feed
	 *                            starves the same rows on every future run.
	 * @return array|WP_Error array( 'created' => int, 'updated' => int,
	 *                        'ids' => array<string,string>, 'errors' => array )
	 *                        Each 'errors' entry is
	 *                        array( 'chunk' => int, 'message' => string, 'keys' => array ).
	 *                        The WP_Error returned when continue_on_error is off
	 *                        carries array( 'status', 'created', 'updated', 'chunk' )
	 *                        so the caller can report the partial progress that
	 *                        was already committed in Airtable.
	 */
	public function upsert( $table, array $records, $merge_field, array $options = array() ) {
		$typecast = ! isset( $options['typecast'] ) || (bool) $options['typecast'];
		$continue = ! empty( $options['continue_on_error'] );
		$created  = 0;
		$updated  = 0;
		$ids      = array();
		$errors   = array();

		foreach ( array_chunk( $records, self::BATCH ) as $index => $chunk ) {
			$payload = array(
				'performUpsert' => array( 'fieldsToMergeOn' => array( $merge_field ) ),
				'typecast'      => $typecast,
				'records'       => array_map(
					function ( $fields ) {
						return array( 'fields' => $fields );
					},
					$chunk
				),
			);

			// This MUST stay PATCH. Airtable's PATCH-upsert leaves fields that
			// are absent from the payload untouched; PUT clears them. The
			// Campus Connect mapper's whole non-destructive guarantee -- and the
			// safety of the 142 hand-imported rows in tbld8niqsLWyNbcVF -- rests
			// on that one verb.
			$result = $this->request( 'PATCH', rawurlencode( $table ), $payload );

			if ( is_wp_error( $result ) ) {
				if ( ! $continue ) {
					return new WP_Error(
						$result->get_error_code(),
						$result->get_error_message(),
						array(
							'status'  => $this->status_of( $result ),
							'created' => $created,
							'updated' => $updated,
							'chunk'   => $index,
						)
					);
				}

				$keys = array();

				foreach ( $chunk as $fields ) {
					if ( isset( $fields[ $merge_field ] ) && is_scalar( $fields[ $merge_field ] ) ) {
						$keys[] = $fields[ $merge_field ];
					}
				}

				$errors[] = array(
					'chunk'   => $index,
					'message' => $result->get_error_message(),
					'keys'    => $keys,
				);

				continue;
			}

			$created += isset( $result['createdRecords'] ) ? count( $result['createdRecords'] ) : 0;
			$updated += isset( $result['updatedRecords'] ) ? count( $result['updatedRecords'] ) : 0;

			// Map the merge value back to the Airtable record ID, so children
			// can be linked to their parent camp without a second read.
			if ( isset( $result['records'] ) && is_array( $result['records'] ) ) {
				foreach ( $result['records'] as $record ) {
					if ( isset( $record['id'], $record['fields'][ $merge_field ] ) ) {
						$ids[ (string) $record['fields'][ $merge_field ] ] = $record['id'];
					}
				}
			}
		}

		return array(
			'created' => $created,
			'updated' => $updated,
			'ids'     => $ids,
			'errors'  => $errors,
		);
	}

	/**
	 * The HTTP status carried by an error, if any.
	 *
	 * Mirrors WCAC_Sync::error_status(), so that re-wrapping a chunk failure
	 * with partial counters does not lose the status the caller classifies on.
	 *
	 * @param WP_Error $error Error object.
	 * @return int Zero when the error was not an HTTP response.
	 */
	private function status_of( WP_Error $error ) {
		$data = $error->get_error_data();

		return is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
	}

	/**
	 * Read selected fields from every row of a table, following Airtable's
	 * offset cursor. Needs only data.records:read, which the PAT already has.
	 *
	 * Used by the CLI preflight and dry run only -- the cron sync path never
	 * reads Airtable.
	 *
	 * Airtable OMITS a field from a record's `fields` object when the cell is
	 * empty; it does not send null. Every requested field is therefore filled
	 * in with null here, because the blank merge values this exists to find are
	 * exactly the ones that would otherwise be invisible.
	 *
	 * Every page goes through request(), so the per-base throttle and the
	 * 429/5xx retry apply to a long read exactly as they do to a write.
	 *
	 * @param string $table     Table ID or name.
	 * @param array  $fields    Field names to request; empty for all fields.
	 * @param int    $max_pages Safety cap on cursor pages.
	 * @return array|WP_Error array( recId => array( field => mixed|null ) )
	 */
	public function list_records( $table, array $fields = array(), $max_pages = 50 ) {
		$rows   = array();
		$page   = 0;
		$offset = '';
		$cap    = max( 1, (int) $max_pages );
		$query  = '?pageSize=100';

		foreach ( $fields as $field ) {
			$query .= '&fields%5B%5D=' . rawurlencode( (string) $field );
		}

		do {
			$page++;
			$path = rawurlencode( $table ) . $query;

			if ( '' !== $offset ) {
				$path .= '&offset=' . rawurlencode( $offset );
			}

			$result = $this->request( 'GET', $path );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$records = isset( $result['records'] ) && is_array( $result['records'] ) ? $result['records'] : array();

			foreach ( $records as $record ) {
				if ( ! isset( $record['id'] ) ) {
					continue;
				}

				$cells = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();

				if ( empty( $fields ) ) {
					$rows[ (string) $record['id'] ] = $cells;
					continue;
				}

				$row = array();

				foreach ( $fields as $field ) {
					$row[ $field ] = array_key_exists( $field, $cells ) ? $cells[ $field ] : null;
				}

				$rows[ (string) $record['id'] ] = $row;
			}

			$offset = isset( $result['offset'] ) && is_string( $result['offset'] ) ? $result['offset'] : '';
		} while ( '' !== $offset && $page < $cap );

		if ( '' !== $offset ) {
			// Fail closed rather than returning a short list. The preflight
			// stamps a gate on the strength of this read, and a silently
			// truncated one would report a table clean on the rows it never saw.
			return new WP_Error(
				'wcac_airtable_truncated',
				sprintf( 'Airtable returned more than %d pages for this table; the read was stopped and is incomplete.', $cap )
			);
		}

		return $rows;
	}

	/**
	 * Cheap connectivity check: ask for a single record from a table.
	 *
	 * @param string $table Table ID or name.
	 * @return true|WP_Error
	 */
	public function ping( $table ) {
		$result = $this->request( 'GET', rawurlencode( $table ) . '?maxRecords=1' );

		return is_wp_error( $result ) ? $result : true;
	}
}
