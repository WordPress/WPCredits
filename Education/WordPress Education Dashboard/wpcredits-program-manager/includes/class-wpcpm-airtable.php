<?php
/**
 * Airtable REST API client.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Airtable client, shared by every module and tool.
 *
 * Pages are fetched one at a time rather than in a `do…while` loop so a sync can
 * stop mid-table when its time budget runs out and resume from the stored offset
 * on the next cron tick.
 *
 * Reads are the common case; `update_records()` is the one write path, used by the
 * Mentor Status Checker to move a mentor's status. Writing needs the
 * `data.records:write` scope on the token, which a read-only token will not have.
 */
class WPCPM_Airtable {

	const API_BASE  = 'https://api.airtable.com/v0';
	const PAGE_SIZE = 100;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array|null $settings Optional settings override.
	 */
	public function __construct( $settings = null ) {
		$this->settings = is_array( $settings ) ? $settings : WPCPM_Settings::get();
	}

	/**
	 * Fetch a single page of records.
	 *
	 * @param string $table  Table ID or name.
	 * @param array  $args   {
	 *     Optional query arguments.
	 *
	 *     @type string   $formula filterByFormula expression.
	 *     @type string[] $fields  Field names to return. All fields when empty.
	 *     @type string   $offset  Pagination offset from a previous response.
	 * }
	 * @return array|WP_Error `array( 'records' => array, 'offset' => string|null )`.
	 */
	public function fetch_page( $table, array $args = array() ) {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		if ( empty( $table ) ) {
			return new WP_Error( 'wpcpm_no_table', __( 'No Airtable table was specified.', 'wpcredits-program-manager' ) );
		}

		$query = array( 'pageSize' => self::PAGE_SIZE );

		if ( ! empty( $args['formula'] ) ) {
			$query['filterByFormula'] = $args['formula'];
		}
		if ( ! empty( $args['offset'] ) ) {
			$query['offset'] = $args['offset'];
		}

		$url = add_query_arg( $query, $this->table_url( $table ) );

		// Airtable expects repeated `fields[]=` params, which add_query_arg()
		// cannot express, so they are appended by hand.
		if ( ! empty( $args['fields'] ) ) {
			foreach ( (array) $args['fields'] as $field ) {
				$url .= '&fields%5B%5D=' . rawurlencode( $field );
			}
		}

		$response = $this->request( $url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'records' => ( ! empty( $response['records'] ) && is_array( $response['records'] ) ) ? $response['records'] : array(),
			'offset'  => isset( $response['offset'] ) ? (string) $response['offset'] : null,
		);
	}

	/**
	 * Walk every page of a table. Use only where the record count is known small.
	 *
	 * @param string $table Table ID or name.
	 * @param array  $args  See fetch_page().
	 * @return array|WP_Error Flat list of records.
	 */
	public function fetch_all( $table, array $args = array() ) {
		$records = array();
		$offset  = null;
		$safety  = 0;

		do {
			$args['offset'] = $offset;
			$page           = $this->fetch_page( $table, $args );

			if ( is_wp_error( $page ) ) {
				return $page;
			}

			$records = array_merge( $records, $page['records'] );
			$offset  = $page['offset'];
			++$safety;
		} while ( $offset && $safety < 100 );

		return $records;
	}

	/**
	 * Read a single record.
	 *
	 * Used to re-confirm a value immediately before writing over it, so a change
	 * someone else made in Airtable since the queue was built is not clobbered.
	 *
	 * @param string $table     Table ID or name.
	 * @param string $record_id Airtable record ID.
	 * @return array|WP_Error Record array with `id` and `fields`.
	 */
	public function get_record( $table, $record_id ) {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$response = $this->request( $this->table_url( $table ) . '/' . rawurlencode( $record_id ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'id'     => isset( $response['id'] ) ? (string) $response['id'] : (string) $record_id,
			'fields' => ( ! empty( $response['fields'] ) && is_array( $response['fields'] ) ) ? $response['fields'] : array(),
		);
	}

	/**
	 * Create records.
	 *
	 * `typecast` is deliberately **not** sent: it lets Airtable coerce an unknown single-select
	 * value into a new choice, so one typo would add an option to the column rather than fail.
	 * Everything written here is validated against the choices the base already has.
	 *
	 * @param string $table   Table ID or name.
	 * @param array  $records List of `array( 'fields' => array )`.
	 * @return array|WP_Error List of created record IDs, in order.
	 */
	public function create_records( $table, array $records ) {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$records = array_values(
			array_filter(
				$records,
				static function ( $record ) {
					return ! empty( $record['fields'] ) && is_array( $record['fields'] );
				}
			)
		);

		if ( empty( $records ) ) {
			return array();
		}

		$created = array();

		foreach ( array_chunk( $records, 10 ) as $chunk ) {
			$response = $this->request(
				$this->table_url( $table ),
				'POST',
				array( 'records' => array_values( $chunk ) )
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			foreach ( ( isset( $response['records'] ) && is_array( $response['records'] ) ) ? $response['records'] : array() as $record ) {
				if ( ! empty( $record['id'] ) ) {
					$created[] = (string) $record['id'];
				}
			}
		}

		return $created;
	}

	/**
	 * Update records.
	 *
	 * Airtable accepts at most ten records per PATCH, so longer lists are chunked.
	 *
	 * @param string $table   Table ID or name.
	 * @param array  $records List of `array( 'id' => string, 'fields' => array )`.
	 * @return array|WP_Error Map of record ID => true, or the first failing chunk's error.
	 */
	public function update_records( $table, array $records ) {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$records = array_values(
			array_filter(
				$records,
				static function ( $record ) {
					return ! empty( $record['id'] ) && ! empty( $record['fields'] );
				}
			)
		);

		if ( empty( $records ) ) {
			return array();
		}

		$updated = array();

		foreach ( array_chunk( $records, 10 ) as $chunk ) {
			$response = $this->request(
				$this->table_url( $table ),
				'PATCH',
				array( 'records' => array_values( $chunk ) )
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			foreach ( $chunk as $record ) {
				$updated[ $record['id'] ] = true;
			}
		}

		return $updated;
	}

	/**
	 * Read the base schema, including each field's description.
	 *
	 * Field descriptions live on the schema endpoint, not on records, so they
	 * need this separate call. It needs the `schema.bases:read` scope on the
	 * token — a scope a records-only token will not have, which is why callers
	 * are expected to treat a failure here as cosmetic and carry on.
	 *
	 * @return array|WP_Error Map of table ID => array( 'name' => string, 'primary' => string, 'fields' => array( field name => description ) ).
	 */
	public function fetch_schema() {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$url      = trailingslashit( self::API_BASE ) . 'meta/bases/' . rawurlencode( $this->settings['base_id'] ) . '/tables';
		$response = $this->request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['tables'] ) || ! is_array( $response['tables'] ) ) {
			return new WP_Error( 'wpcpm_airtable_no_schema', __( 'Airtable returned no table schema.', 'wpcredits-program-manager' ) );
		}

		$schema = array();

		foreach ( $response['tables'] as $table ) {
			if ( empty( $table['id'] ) ) {
				continue;
			}

			$fields     = array();
			$primary_id = isset( $table['primaryFieldId'] ) ? (string) $table['primaryFieldId'] : '';
			$primary    = '';

			if ( ! empty( $table['fields'] ) && is_array( $table['fields'] ) ) {
				foreach ( $table['fields'] as $field ) {
					if ( empty( $field['name'] ) ) {
						continue;
					}

					// Keyed by name, because that is what the rest of the plugin
					// addresses fields by. An absent description is stored as an
					// empty string so the caller can tell "read, but blank" from
					// "never read".
					$fields[ (string) $field['name'] ] = isset( $field['description'] ) ? trim( (string) $field['description'] ) : '';

					// The primary field's *name*, resolved from the ID the schema
					// reports. This is the only reliable way to know which column
					// carries a record's display value: a records response gives no
					// hint, and its field order cannot be relied on.
					if ( '' !== $primary_id && isset( $field['id'] ) && (string) $field['id'] === $primary_id ) {
						$primary = (string) $field['name'];
					}
				}
			}

			$schema[ (string) $table['id'] ] = array(
				'name'    => isset( $table['name'] ) ? (string) $table['name'] : '',
				'primary' => $primary,
				'fields'  => $fields,
			);
		}

		return $schema;
	}

	/**
	 * Verify the token and base by asking one table for one record.
	 *
	 * @param string $table Table ID or name.
	 * @return true|WP_Error
	 */
	public function test_connection( $table ) {
		$guard = $this->guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$url      = add_query_arg( array( 'maxRecords' => 1 ), $this->table_url( $table ) );
		$response = $this->request( $url );

		return is_wp_error( $response ) ? $response : true;
	}

	/**
	 * Build an `OR({Field}='a',{Field}='b')` formula, or a bare equality for one value.
	 *
	 * @param string   $field  Field name.
	 * @param string[] $values Accepted values.
	 * @return string Empty string when there is nothing to filter on.
	 */
	public function formula_in( $field, array $values ) {
		$values = array_values( array_filter( array_map( 'strval', $values ), 'strlen' ) );

		if ( empty( $values ) ) {
			return '';
		}

		$field = $this->escape_field_name( $field );
		$tests = array();

		foreach ( $values as $value ) {
			$tests[] = sprintf( '{%s} = %s', $field, $this->quote( $value ) );
		}

		if ( 1 === count( $tests ) ) {
			return $tests[0];
		}

		return 'OR(' . implode( ',', $tests ) . ')';
	}

	/**
	 * Reduce an Airtable cell to a scalar string.
	 *
	 * Handles the three shapes the API returns for the fields this plugin reads:
	 * scalars, single-select objects, and arrays of linked-record objects.
	 *
	 * @param mixed  $value Raw cell value.
	 * @param string $glue  Separator for multi-value cells.
	 * @return string
	 */
	public static function flatten( $value, $glue = ', ' ) {
		if ( is_array( $value ) ) {
			// A single-select read through the REST API is a plain string, but a
			// linked record or lookup is an array — of scalars or of objects.
			if ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) {
				return (string) $value['name'];
			}

			$parts = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$parts[] = (string) $item;
				} elseif ( is_array( $item ) && isset( $item['name'] ) && is_scalar( $item['name'] ) ) {
					$parts[] = (string) $item['name'];
				}
			}

			return implode( $glue, array_filter( $parts, 'strlen' ) );
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return '';
	}

	/**
	 * Extract linked record IDs from a link cell.
	 *
	 * @param mixed $value Raw cell value.
	 * @return string[]
	 */
	public static function link_ids( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array();
		foreach ( $value as $item ) {
			if ( is_string( $item ) && 0 === strpos( $item, 'rec' ) ) {
				$ids[] = $item;
			} elseif ( is_array( $item ) && ! empty( $item['id'] ) ) {
				$ids[] = (string) $item['id'];
			}
		}

		return $ids;
	}

	/**
	 * Perform an authenticated request and decode the response.
	 *
	 * @param string     $url    Absolute request URL.
	 * @param string     $method HTTP method.
	 * @param array|null $body   Optional payload, JSON-encoded.
	 * @return array|WP_Error Decoded response body.
	 */
	private function request( $url, $method = 'GET', $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->settings['api_token'],
				'Accept'        => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code > 299 ) {
			$detail = '';
			if ( isset( $data['error'] ) ) {
				if ( is_array( $data['error'] ) ) {
					$detail = isset( $data['error']['message'] ) ? $data['error']['message'] : ( isset( $data['error']['type'] ) ? $data['error']['type'] : '' );
				} else {
					$detail = (string) $data['error'];
				}
			}

			$message = sprintf(
				/* translators: 1: HTTP status code, 2: error detail returned by Airtable. */
				__( 'Airtable request failed (HTTP %1$d): %2$s', 'wpcredits-program-manager' ),
				$code,
				$detail ? $detail : __( 'no further detail', 'wpcredits-program-manager' )
			);

			// A 403 almost always means a missing token scope rather than a bad
			// request, and Airtable's own message does not say which one. Naming the
			// scope turns a dead end into something fixable.
			if ( 403 === $code || 401 === $code ) {
				if ( 'GET' !== strtoupper( $method ) ) {
					$message .= ' ' . __( 'This was a write, so the token most likely lacks the "data.records:write" scope. Add it at airtable.com/create/tokens, or use report-only mode.', 'wpcredits-program-manager' );
				} elseif ( false !== strpos( $url, '/meta/bases/' ) ) {
					$message .= ' ' . __( 'This was a schema read, so the token most likely lacks the "schema.bases:read" scope.', 'wpcredits-program-manager' );
				} else {
					$message .= ' ' . __( 'Check that the token has the "data.records:read" scope and access to this base.', 'wpcredits-program-manager' );
				}
			}

			return new WP_Error(
				'wpcpm_airtable_error',
				$message,
				array( 'status' => $code )
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'wpcpm_airtable_bad_response', __( 'Airtable returned an unexpected response.', 'wpcredits-program-manager' ) );
		}

		return $data;
	}

	/**
	 * Bail early when the connection settings are incomplete.
	 *
	 * @return true|WP_Error
	 */
	private function guard() {
		if ( empty( $this->settings['api_token'] ) ) {
			return new WP_Error( 'wpcpm_no_token', __( 'No Airtable Personal Access Token is configured. Add one on the WPCredits Program → Settings screen.', 'wpcredits-program-manager' ) );
		}

		if ( empty( $this->settings['base_id'] ) ) {
			return new WP_Error( 'wpcpm_no_base', __( 'The Airtable Base ID is missing from the plugin settings.', 'wpcredits-program-manager' ) );
		}

		return true;
	}

	/**
	 * Base URL for one table.
	 *
	 * @param string $table Table ID or name.
	 * @return string
	 */
	private function table_url( $table ) {
		return trailingslashit( self::API_BASE ) . rawurlencode( $this->settings['base_id'] ) . '/' . rawurlencode( $table );
	}

	/**
	 * Escape a field name for use inside curly braces in a formula.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	private function escape_field_name( $name ) {
		return str_replace( array( '\\', '}' ), array( '\\\\', '\\}' ), $name );
	}

	/**
	 * Quote and escape a string literal for a formula.
	 *
	 * @param string $value Literal value.
	 * @return string
	 */
	private function quote( $value ) {
		return "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $value ) . "'";
	}
}
