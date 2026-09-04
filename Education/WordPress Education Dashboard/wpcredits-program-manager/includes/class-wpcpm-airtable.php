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
 *
 * Every request goes through `request()`, which keeps this process under Airtable's
 * five-a-second ceiling and honours a 429 for as long as Airtable asked. Both matter
 * because a 429 does not refuse one request, it refuses every request for the base for
 * thirty seconds - from every client - and two live-read paths on this site can be
 * reached by Subscriber-based accounts.
 */
class WPCPM_Airtable {

	const API_BASE  = 'https://api.airtable.com/v0';
	const PAGE_SIZE = 100;

	/**
	 * The shape of an Airtable record ID: `rec` and fourteen alphanumerics.
	 *
	 * Held on the client because it is a fact about Airtable, and every module that stores,
	 * receives or fences on a record ID tests it. The only shared copy used to live on the
	 * Mentors sync, which made the Institutions policy depend on the Mentors module for the
	 * one check its whole fence rests on; `WPCPM_Mentors_Sync::is_record_id()` is an alias of
	 * `is_record_id()` below for one release.
	 */
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';

	/**
	 * Airtable's published ceiling: five requests per second per base.
	 *
	 * The sixth is not queued, it is answered 429, and from that moment the base
	 * refuses every request for thirty seconds. RATE_WINDOW is the second the five are
	 * counted over.
	 */
	const RATE_LIMIT  = 5;
	const RATE_WINDOW = 1;

	/** Seconds to stay away after a 429 that does not say. Airtable sends 30. */
	const BACKOFF_DEFAULT = 30;

	/** Longest remaining backoff a cron or WP-CLI process sleeps out rather than refuses. */
	const BACKOFF_SLEEP_MAX = 5;

	/** Option holding the Unix time until which the base has asked to be left alone. */
	const BACKOFF_OPTION = 'wpcpm_airtable_backoff';

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Start times of the requests this process made within the last RATE_WINDOW, oldest first.
	 *
	 * @var float[]
	 */
	private static $recent = array();

	/**
	 * Unix time until which requests are refused, 0 when they are not.
	 *
	 * Mirrors BACKOFF_OPTION for the process that saw the 429, so it does not go back
	 * to the database to learn what it was just told.
	 *
	 * @var int
	 */
	private static $backoff_until = 0;

	/**
	 * Stand-in for `microtime( true )`. Null means the real clock. See for_tests().
	 *
	 * @var callable|null
	 */
	private static $clock = null;

	/**
	 * Stand-in for sleeping. Null means really sleeping. See for_tests().
	 *
	 * @var callable|null
	 */
	private static $sleeper = null;

	/**
	 * Stand-in for the "may this process block for a few seconds" check. See for_tests().
	 *
	 * @var callable|null
	 */
	private static $can_wait = null;

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

		$url = $this->query_url( $table, $query );

		// Airtable expects repeated `fields[]=` params, which http_build_query() would
		// write as `fields[0]=`, so they are appended by hand, encoded the same way.
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
	 * token - a scope a records-only token will not have, which is why callers
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

		$response = $this->request( $this->query_url( $table, array( 'maxRecords' => 1 ) ) );

		return is_wp_error( $response ) ? $response : true;
	}

	/**
	 * Build an `OR({Field}='a',{Field}='b')` formula, or a bare equality for one value.
	 *
	 * With `$lower`, each test becomes `LOWER({Field}) = 'value'` with the value lowercased
	 * here before it is quoted, so an address stored as `Ann@Example.org` still finds its
	 * row. The field side is lowercased by Airtable's own LOWER() because that is the side
	 * this code does not hold, and Airtable's LOWER() is Unicode-aware: it folds `Ł` in a
	 * name as readily as `A` in an address. The needle side is lowercased by PHP, and PHP's
	 * folding is not guaranteed to agree with Airtable's outside ASCII - `strtolower()`
	 * leaves `Ł` alone, and even `mb_strtolower()` follows its own tables, which nobody has
	 * checked against Airtable's for every letter of every script. That is why this flag
	 * is only right for values that are ASCII by nature, which is to say email addresses,
	 * and never for names: a name formula built this way prints 0 students for
	 * Uniwersytet Łódzki, with every line of code looking correct.
	 *
	 * @param string   $field  Field name.
	 * @param string[] $values Accepted values.
	 * @param bool     $lower  Compare case-insensitively. Only for ASCII-natured values such as emails.
	 * @return string Empty string when there is nothing to filter on.
	 */
	public function formula_in( $field, array $values, $lower = false ) {
		$values = array_values( array_filter( array_map( 'strval', $values ), 'strlen' ) );

		if ( empty( $values ) ) {
			return '';
		}

		$field = $this->escape_field_name( $field );
		$tests = array();

		foreach ( $values as $value ) {
			if ( $lower ) {
				// mbstring is on every host this plugin has met but is not something
				// WordPress requires; for the ASCII values this flag is meant for the two
				// agree anyway, so the fallback loses nothing.
				$needle  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
				$tests[] = sprintf( 'LOWER({%s}) = %s', $field, $this->quote( $needle ) );
			} else {
				$tests[] = sprintf( '{%s} = %s', $field, $this->quote( $value ) );
			}
		}

		if ( 1 === count( $tests ) ) {
			return $tests[0];
		}

		return 'OR(' . implode( ',', $tests ) . ')';
	}

	/**
	 * A formula matching rows whose field contains any of these needles.
	 *
	 * **`FIND()` is a substring test, and the caller must finish the job in PHP.** This exists
	 * for the columns that hold a WordPress.org profile, where the base has a URL and the thing
	 * being looked for is a handle, so equality would miss every row: `annak` has to find
	 * `https://profiles.wordpress.org/annak/`. The cost of that is that `ann` also finds
	 * `joanna`, so every row this returns is a candidate and not a match, and the caller
	 * normalises both sides and compares them exactly before believing it.
	 *
	 * The quoting lives here, with `formula_in()` and the escaper, rather than in the modules
	 * that build queries. A second place that assembles a formula out of somebody's file is a
	 * second place to get the escaping wrong, and the value being searched for here arrives in a
	 * CSV from outside the program.
	 *
	 * @param string   $field   Column name.
	 * @param string[] $needles Values to look for.
	 * @param bool     $lower   Compare against `LOWER()` of the column, which is the usual case
	 *                          for a handle: the base's URLs are cased however they were pasted.
	 * @return string A formula, or '' when there is nothing to look for.
	 */
	public function formula_contains( $field, array $needles, $lower = true ) {
		$needles = array_values( array_filter( array_map( 'strval', $needles ), 'strlen' ) );

		if ( empty( $needles ) ) {
			return '';
		}

		$field  = $this->escape_field_name( $field );
		$column = $lower ? sprintf( 'LOWER({%s})', $field ) : sprintf( '{%s}', $field );
		$tests  = array();

		foreach ( $needles as $needle ) {
			if ( $lower ) {
				$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $needle ) : strtolower( $needle );
			}

			$tests[] = sprintf( 'FIND(%s, %s) > 0', $this->quote( $needle ), $column );
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
			// linked record or lookup is an array - of scalars or of objects.
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
	 * Whether a value is an Airtable record ID.
	 *
	 * Surrounding whitespace is forgiven, because IDs arrive out of cells, query strings and
	 * pasted CSVs. A non-scalar is not one, rather than a string-conversion warning followed
	 * by the same answer.
	 *
	 * @param mixed $value Value to test.
	 * @return bool
	 */
	public static function is_record_id( $value ) {
		return is_scalar( $value ) && 1 === preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) );
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
	 * Seconds until Airtable will take requests again, 0 when it will now.
	 *
	 * For screens: a page that gets `wpcpm_airtable_rate_limited` back can say how long
	 * the wait is instead of "something went wrong", and a sync panel can say why the
	 * last tick did nothing.
	 *
	 * @return int
	 */
	public static function backoff_remaining() {
		return self::seconds_until( self::backoff_until() );
	}

	/**
	 * Replace the clock, the sleeper and the may-this-process-block check. Test-only.
	 *
	 * Pacing and backoff are about elapsed time, and a suite that really slept through
	 * them would take half a minute per assertion. Nothing in the plugin calls this;
	 * bin/test-airtable.php does, with a clock it advances by hand, a sleeper that
	 * records what it was asked for, and a check it flips between "page render" and
	 * "cron", which the suite could not otherwise reach because it runs under the CLI
	 * itself. Each argument is a callable, or null for the real thing. Recent request
	 * starts and any recorded backoff are forgotten too, so every scenario begins clean.
	 *
	 * @param callable|null $clock    Returns the current Unix time as a float.
	 * @param callable|null $sleeper  Receives the number of seconds to pause for.
	 * @param callable|null $can_wait Returns whether this process may block for a few seconds.
	 */
	public static function for_tests( $clock = null, $sleeper = null, $can_wait = null ) {
		self::$clock         = $clock;
		self::$sleeper       = $sleeper;
		self::$can_wait      = $can_wait;
		self::$recent        = array();
		self::$backoff_until = 0;
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
		// A 429 seen earlier - by this process, or through the option by any other - is
		// honoured before anything is sent. Airtable counts the requests it refuses, so
		// every one sent inside the window pushes the base's thirty seconds out again,
		// for everybody.
		$until = self::backoff_until();

		if ( $until > 0 ) {
			$wait = self::seconds_until( $until );

			if ( $wait > 0 ) {
				// A sync under cron or WP-CLI has nobody waiting on it, so a few seconds'
				// pause is cheaper than abandoning the tick and coming back next time.
				// A page render cannot sit for thirty seconds - the visitor sees a hung
				// tab, and PHP's own time limit is not much longer - so it gets the error
				// at once and the screen can say what is happening. Cron gets the same
				// answer for a long wait, because a tick spent asleep is a tick that did
				// no work and the next one is minutes away anyway.
				if ( $wait <= self::BACKOFF_SLEEP_MAX && self::can_wait() ) {
					self::pause( $wait );

					// Another process may have been refused while this one slept and pushed the
					// window out again. Its record outranks the memory of our own.
					$again = self::seconds_until( self::backoff_until() );

					if ( $again > 0 ) {
						return self::rate_limited( $again );
					}
				} else {
					return self::rate_limited( $wait );
				}
			}

			// Slept out or long gone: forget it, so the option does not linger for the
			// next reader, and so a fresh 429 below is recorded as the new one it is.
			self::clear_backoff();
		}

		self::pace();

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

		// Told off. Record how long for, where the next request - from this process or
		// any other - will see it, and hand back an error the caller can tell apart from
		// a real failure, because the right response to this one is "try again later",
		// not "check the token".
		if ( 429 === $code ) {
			$wait = self::retry_after( $response );

			self::record_backoff( $wait );

			return self::rate_limited( $wait );
		}

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
	 * Hold the next request back until it is not the sixth to start within a second.
	 *
	 * In-process only: the record of recent starts is a static, so two PHP processes
	 * talking to the base at once do not see each other's requests. Cross-process
	 * protection is the 429 handling in request(), where the first process to be told
	 * off records the backoff in an option every other process reads before sending.
	 * This pacing exists so that one sync - the one caller that fires requests back to
	 * back - does not earn that 429 on its own.
	 */
	private static function pace() {
		$now = self::now();

		self::forget_old_starts( $now );

		if ( count( self::$recent ) >= self::RATE_LIMIT ) {
			// The next request may start the moment the oldest of the last five falls
			// out of the window, and not a moment sooner.
			$wait = self::$recent[ count( self::$recent ) - self::RATE_LIMIT ] + self::RATE_WINDOW - $now;

			if ( $wait > 0 ) {
				self::pause( $wait );

				$now = self::now();
				self::forget_old_starts( $now );
			}
		}

		self::$recent[] = $now;
	}

	/**
	 * Drop recorded request starts that are older than the rate window.
	 *
	 * @param float $now Current Unix time.
	 */
	private static function forget_old_starts( $now ) {
		$floor = $now - self::RATE_WINDOW;

		self::$recent = array_values(
			array_filter(
				self::$recent,
				static function ( $started ) use ( $floor ) {
					return $started > $floor;
				}
			)
		);
	}

	/**
	 * How long a 429 asked us to stay away, in whole seconds.
	 *
	 * @param array $response Raw HTTP API response.
	 * @return int
	 */
	private static function retry_after( $response ) {
		$header = wp_remote_retrieve_header( $response, 'retry-after' );

		// Requests hands back a list when a header was sent twice; the first is as good
		// as any.
		if ( is_array( $header ) ) {
			$header = reset( $header );
		}

		$header = trim( (string) $header );

		// The header may also carry an HTTP date. Airtable never sends one, and thirty
		// seconds is what its 429 means whatever the header says, so anything that is
		// not a plain number of seconds falls back to that rather than being parsed.
		if ( '' === $header || ! is_numeric( $header ) || (float) $header <= 0 ) {
			return self::BACKOFF_DEFAULT;
		}

		return (int) ceil( (float) $header );
	}

	/**
	 * The error every refused request returns, distinguishable from a real failure.
	 *
	 * @param int $seconds Seconds until Airtable will take requests again.
	 * @return WP_Error
	 */
	private static function rate_limited( $seconds ) {
		return new WP_Error(
			'wpcpm_airtable_rate_limited',
			sprintf(
				/* translators: %d: number of seconds. */
				_n(
					'Airtable asked us to wait %d second before sending more requests.',
					'Airtable asked us to wait %d seconds before sending more requests.',
					$seconds,
					'wpcredits-program-manager'
				),
				$seconds
			),
			array(
				'status'      => 429,
				'retry_after' => $seconds,
			)
		);
	}

	/**
	 * Unix time until which the base has asked to be left alone, 0 when it has not.
	 *
	 * The static answers for the process that saw the 429; the option answers for every
	 * other one, and is only consulted when the static has nothing. The common case
	 * therefore costs at most one query per process, and none at all once WordPress has
	 * cached the option as missing.
	 *
	 * @return int
	 */
	private static function backoff_until() {
		// The later of the two, always. The static is this process's own memory; the option
		// is every other process's, and one of them may have been refused again while this
		// one was counting down its own window. Reading the option on every call costs one
		// cached lookup, against the HTTP request it precedes.
		return max( self::$backoff_until, (int) get_option( self::BACKOFF_OPTION, 0 ) );
	}

	/**
	 * Remember a 429, for this process and every other.
	 *
	 * Not autoloaded: the option exists for thirty seconds at a time, and every page
	 * load that never talks to Airtable has no use for it.
	 *
	 * @param int $seconds Seconds to stay away, from now.
	 */
	private static function record_backoff( $seconds ) {
		self::$backoff_until = (int) ceil( self::now() ) + (int) $seconds;

		update_option( self::BACKOFF_OPTION, self::$backoff_until, false );
	}

	/**
	 * Forget a recorded 429, for this process and every other.
	 */
	private static function clear_backoff() {
		self::$backoff_until = 0;

		delete_option( self::BACKOFF_OPTION );
	}

	/**
	 * Whole seconds from now until a Unix time, 0 when it has passed.
	 *
	 * @param int $until Unix time.
	 * @return int
	 */
	private static function seconds_until( $until ) {
		$left = $until - self::now();

		return $left > 0 ? (int) ceil( $left ) : 0;
	}

	/**
	 * Whether this process may block for a few seconds without anyone noticing.
	 *
	 * @return bool
	 */
	private static function can_wait() {
		if ( null !== self::$can_wait ) {
			return (bool) call_user_func( self::$can_wait );
		}

		return wp_doing_cron() || 'cli' === PHP_SAPI;
	}

	/**
	 * Current Unix time, with fractions.
	 *
	 * @return float
	 */
	private static function now() {
		if ( null !== self::$clock ) {
			return (float) call_user_func( self::$clock );
		}

		return microtime( true );
	}

	/**
	 * Pause this process.
	 *
	 * Whole seconds go through sleep() and the remainder through usleep(), because
	 * usleep() with more than a second is not something every platform accepts.
	 *
	 * @param float $seconds Seconds to pause for.
	 */
	private static function pause( $seconds ) {
		if ( null !== self::$sleeper ) {
			call_user_func( self::$sleeper, $seconds );
			return;
		}

		$whole = (int) floor( $seconds );

		if ( $whole > 0 ) {
			sleep( $whole );
		}

		$fraction = $seconds - $whole;

		if ( $fraction > 0 ) {
			usleep( (int) ceil( $fraction * 1000000 ) );
		}
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
	 * A table URL with a query string, every value percent-encoded.
	 *
	 * Not `add_query_arg()`. That function does not encode the values it is handed (core
	 * says the caller must), and the URL normaliser WP_Http then runs leaves `+`, `&` and
	 * `=` exactly as they are. So a formula built from a plus-addressed email reached
	 * Airtable with the plus decoded as a space: `anna+wp@example.org` matched no row, and
	 * the roster fence told an institution a real student was not on its roster. An
	 * ampersand inside a value ended the parameter and started another. RFC 3986 encoding
	 * makes a value a value whatever is in it, which is the treatment `fields[]` always had.
	 *
	 * @param string $table Table ID or name.
	 * @param array  $query Query parameters, unencoded.
	 * @return string
	 */
	private function query_url( $table, array $query ) {
		return $this->table_url( $table ) . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
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
